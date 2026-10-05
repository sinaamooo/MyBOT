<?php

declare(strict_types=1);

namespace App;

use App\Telegram\Client;

/**
 * Single entry point for all Telegram updates. /panel shows three buttons (analysis,
 * banner, signal); every button press and admin message is routed to the module that owns it.
 *
 * Routing rules:
 *  - callback data "hub:*"      → hub menu
 *  - callback data "an:*"/"p:*" → analysis module
 *  - callback data "admin:*"    → signal module
 *  - banner panel codes (m, j:, ch, s, st, lg, hp, sc, nop) → banner module
 *  - admin text while a module waits for input → that module (the last one the admin used)
 *  - module specific commands (/test, /send, ...) → their module
 *  - everything from users (private chat, channel direct messages) → analysis module
 *  - my_chat_member → signal module (channel access checks)
 */
final class Hub
{
    private const BANNER_HEADS = ['m', 'nop', 'j', 'sc', 'ch', 's', 'st', 'lg', 'hp'];
    private const ANALYSIS_COMMANDS = ['/test', '/analyze', '/stats', '/user', '/quota', '/addquota', '/bonus', '/reset', '/ban', '/unban', '/off', '/on', '/brain', '/brain_clear', '/logs'];
    private const BANNER_COMMANDS = ['/send', '/status', '/id'];

    private const TITLES = [
        Modules::ANALYSIS => '📊 ربات تحلیل',
        Modules::BANNER => '🖼 ربات بنر',
        Modules::SIGNAL => '📡 ربات سیگنال',
    ];

    private Client $tg;
    private Storage $db;
    private string $timezone;

    public function __construct(private array $config, ?Client $client = null, ?Storage $storage = null)
    {
        $this->tg = $client ?? new Client((string) $config['bot_token']);
        $this->db = $storage ?? new Storage(app_storage() . '/bot.sqlite');
        $this->timezone = (string) ($config['timezone'] ?? 'Asia/Tehran');
    }

    public function handle(array $update): void
    {
        if (isset($update['update_id']) && !$this->db->claimUpdate((int) $update['update_id'])) {
            return;
        }
        unset($update['update_id']); // already de-duplicated here

        if (isset($update['callback_query'])) {
            $this->onCallback($update);
            return;
        }
        if (isset($update['my_chat_member'])) {
            if (Modules::installed(Modules::SIGNAL)) {
                $this->dispatch(Modules::SIGNAL, $update);
            }
            return;
        }
        if (isset($update['message'])) {
            $this->onMessage($update);
        }
    }

    // ------------------------------------------------------------------ routing

    private function onCallback(array $update): void
    {
        $cq = $update['callback_query'];
        $data = (string) ($cq['data'] ?? '');
        $userId = (int) ($cq['from']['id'] ?? 0);

        if (str_starts_with($data, 'hub:')) {
            $this->hubCallback($cq);
            return;
        }
        $module = match (true) {
            str_starts_with($data, 'admin:') => Modules::SIGNAL,
            str_starts_with($data, 'an:'), str_starts_with($data, 'p:') => Modules::ANALYSIS,
            in_array(explode(':', $data)[0], self::BANNER_HEADS, true) => Modules::BANNER,
            default => $this->active($userId),
        };
        if (!Modules::installed($module)) {
            $this->tg->answerCallback((string) $cq['id'], 'این بخش نصب نشده است.');
            return;
        }
        if ($this->isAdmin($userId)) {
            $this->setActive($userId, $module);
        }
        $this->dispatch($module, $update);
    }

    private function onMessage(array $update): void
    {
        $msg = $update['message'];
        $userId = (int) ($msg['from']['id'] ?? 0);
        $private = ($msg['chat']['type'] ?? '') === 'private';

        if (!$private || !$this->isAdmin($userId) || !empty($msg['from']['is_bot'])) {
            // Users (private chat or the channel's direct messages) talk to the analysis bot.
            $this->dispatch(Modules::ANALYSIS, $update);
            return;
        }

        $text = trim((string) ($msg['text'] ?? ''));
        $cmd = str_starts_with($text, '/') ? strtolower((string) preg_replace('/@\w+$/', '', strtok($text, " \n") ?: '')) : '';

        if (in_array($cmd, ['/panel', '/start', '/admin', '/menu'], true)) {
            $this->showMenu(['chat_id' => $msg['chat']['id']]);
            return;
        }
        if ($cmd === '/help') {
            $this->tg->sendMessage(['chat_id' => $msg['chat']['id']], $this->helpText());
            return;
        }
        if (in_array($cmd, self::ANALYSIS_COMMANDS, true)) {
            $this->setActive($userId, Modules::ANALYSIS);
            $this->dispatch(Modules::ANALYSIS, $update);
            return;
        }
        if (in_array($cmd, self::BANNER_COMMANDS, true) && Modules::installed(Modules::BANNER)) {
            $this->setActive($userId, Modules::BANNER);
            $this->dispatch(Modules::BANNER, $update);
            return;
        }

        // Input for a settings prompt, /cancel, or a forwarded channel post goes to the module in use.
        $active = $this->active($userId);
        $forwarded = isset($msg['forward_origin']) || isset($msg['forward_from_chat']);
        if ($active !== Modules::ANALYSIS && Modules::installed($active)
            && ($cmd === '/cancel' || $forwarded || $this->awaitingInput($active, $userId))) {
            $this->dispatch($active, $update);
            return;
        }
        $this->dispatch(Modules::ANALYSIS, $update);
    }

    private function hubCallback(array $cq): void
    {
        $userId = (int) ($cq['from']['id'] ?? 0);
        $chatId = $cq['message']['chat']['id'] ?? $userId;
        $messageId = (int) ($cq['message']['message_id'] ?? 0);
        $id = (string) ($cq['id'] ?? '');
        if (!$this->isAdmin($userId)) {
            $this->tg->answerCallback($id, 'دسترسی ندارید.');
            return;
        }
        $parts = explode(':', (string) $cq['data']);
        $action = $parts[1] ?? 'home';

        if ($action === 'open') {
            $module = (string) ($parts[2] ?? '');
            if (!isset(self::TITLES[$module])) {
                $this->tg->answerCallback($id);
                return;
            }
            if (!Modules::installed($module)) {
                $this->tg->answerCallback($id, $module === Modules::SIGNAL && PHP_VERSION_ID < 80100
                    ? 'ربات سیگنال PHP 8.1 یا بالاتر لازم دارد.'
                    : 'فایل‌های این بخش آپلود نشده است.');
                return;
            }
            $this->setActive($userId, $module);
            if ($module === Modules::ANALYSIS) {
                $this->tg->answerCallback($id);
                (new AdminPanel($this->config, $this->tg, $this->db))->show(['chat_id' => $chatId], $messageId ?: null);
                return;
            }
            // Open the module's own main screen in place of the hub menu.
            $cq['data'] = $module === Modules::SIGNAL ? 'admin:main' : 'm';
            $this->dispatch($module, ['callback_query' => $cq]);
            return;
        }

        // hub:home
        $this->tg->answerCallback($id);
        $this->showMenu(['chat_id' => $chatId], $messageId ?: null);
    }

    private function dispatch(string $module, array $update): void
    {
        try {
            switch ($module) {
                case Modules::BANNER:
                    Modules::dispatchBanner($this->config, $update);
                    break;
                case Modules::SIGNAL:
                    Modules::dispatchSignal($update);
                    break;
                default:
                    date_default_timezone_set($this->timezone);
                    (new Bot($this->config, $this->tg, $this->db))->handle($update);
            }
        } catch (\Throwable $e) {
            app_log("hub: {$module} failed: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        } finally {
            date_default_timezone_set($this->timezone);
        }
    }

    private function awaitingInput(string $module, int $userId): bool
    {
        try {
            return match ($module) {
                Modules::BANNER => Modules::bannerAwaitingInput($this->config, $userId),
                Modules::SIGNAL => Modules::signalAwaitingInput($userId),
                default => (string) $this->db->get('admin_state_' . $userId, '') !== '',
            };
        } catch (\Throwable $e) {
            app_log("hub: state check for {$module} failed: " . $e->getMessage());
            return false;
        } finally {
            date_default_timezone_set($this->timezone);
        }
    }

    // ------------------------------------------------------------------ menu

    public function showMenu(array $to, ?int $editId = null): void
    {
        $text = "🛠 <b>پنل مدیریت</b>\n\nبخش مورد نظر را انتخاب کنید. تنظیمات هر بخش کاملاً جداست.\n\n"
            . self::TITLES[Modules::ANALYSIS] . ' — تحلیل درخواستی کاربران از دایرکت کانال' . "\n"
            . self::TITLES[Modules::BANNER] . ' — ارسال زمان‌بندی‌شده بنرهای بازار' . $this->badge(Modules::BANNER) . "\n"
            . self::TITLES[Modules::SIGNAL] . ' — اسکن بازار و سیگنال خودکار' . $this->badge(Modules::SIGNAL);
        $kb = [];
        foreach (self::TITLES as $module => $title) {
            $kb[] = [['text' => $title, 'callback_data' => 'hub:open:' . $module]];
        }
        if ($editId !== null && $this->tg->editMessage($to['chat_id'], $editId, $text, $kb) !== null) {
            return;
        }
        $this->tg->sendMessage($to, $text, ['reply_markup' => ['inline_keyboard' => $kb]]);
    }

    private function badge(string $module): string
    {
        return Modules::installed($module) ? '' : ' <i>(نصب نشده)</i>';
    }

    private function helpText(): string
    {
        return "🛠 <b>راهنمای مدیر</b>\n\n"
            . "<code>/panel</code> منوی اصلی با سه بخش\n\n"
            . "<b>📊 تحلیل</b>\n"
            . "<code>/test BTC 4h</code> تحلیل فوری · <code>/stats</code> آمار · <code>/logs</code> لاگ‌ها\n"
            . "<code>/user</code> <code>/addquota</code> <code>/reset</code> <code>/ban</code> <code>/brain</code>\n\n"
            . "<b>🖼 بنر</b>\n"
            . "<code>/send prices</code> ارسال فوری یک کارت · <code>/status</code> وضعیت\n\n"
            . "<b>📡 سیگنال</b>\n"
            . "همه تنظیمات از دکمه «ربات سیگنال» در <code>/panel</code>\n\n"
            . '<code>/cancel</code> لغو ورودی در حال انتظار';
    }

    // ------------------------------------------------------------------ helpers

    private function isAdmin(int $id): bool
    {
        return in_array($id, array_map('intval', (array) ($this->config['admin_ids'] ?? [])), true);
    }

    private function active(int $userId): string
    {
        $m = (string) $this->db->get('hub_active_' . $userId, Modules::ANALYSIS);
        return isset(self::TITLES[$m]) ? $m : Modules::ANALYSIS;
    }

    private function setActive(int $userId, string $module): void
    {
        if ($this->active($userId) !== $module) {
            $this->db->set('hub_active_' . $userId, $module);
        }
    }
}

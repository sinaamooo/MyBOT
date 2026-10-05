<?php

declare(strict_types=1);

namespace App;

use App\Market\MarketUnavailable;
use App\Market\SymbolNotFound;
use App\Support\Fa;
use App\Support\SymbolParser;
use App\Telegram\Client;

/**
 * Routes Telegram updates: channel direct messages (and optionally private chats)
 * get analyses within the weekly quota on the allowed days; admins get commands.
 */
final class Bot
{
    private Client $tg;
    private Storage $db;

    public function __construct(private array $config, ?Client $client = null, ?Storage $storage = null)
    {
        $this->tg = $client ?? new Client((string) $config['bot_token']);
        $this->db = $storage ?? new Storage(APP_ROOT . '/storage/bot.sqlite');
    }

    public function handle(array $update): void
    {
        if (isset($update['update_id']) && !$this->db->claimUpdate((int) $update['update_id'])) {
            return;
        }
        $msg = $update['message'] ?? null;
        if (!is_array($msg) || empty($msg['from']) || !empty($msg['from']['is_bot'])) {
            return;
        }
        $chat = $msg['chat'];
        $from = $msg['from'];
        $userId = (int) $from['id'];
        $isAdmin = $this->isAdmin($userId);
        $isDm = !empty($chat['is_direct_messages']);
        $isPrivate = ($chat['type'] ?? '') === 'private';
        if (!$isDm && !$isPrivate) {
            return;
        }
        // In the channel's direct messages, skip messages posted by the channel itself / its admins.
        if ($isDm && (!empty($msg['sender_chat']) || $isAdmin)) {
            return;
        }

        $to = [
            'chat_id' => $chat['id'],
            'topic_id' => $msg['direct_messages_topic']['topic_id'] ?? null,
            'reply_to' => $msg['message_id'] ?? null,
        ];
        $text = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
        $this->db->touchUser($from);

        if ($isPrivate && $isAdmin && str_starts_with($text, '/')) {
            $this->adminCommand($to, $text);
            return;
        }
        if ($isPrivate && !$isAdmin && empty($this->config['allow_private_chat'])) {
            $this->tg->sendMessage($to, '🙏 برای دریافت تحلیل به دایرکت کانال ' . htmlspecialchars($this->config['brand']['handle'] ?? '') . ' پیام بدهید.');
            return;
        }
        $this->userMessage($to, $from, $text, $isPrivate, $isAdmin);
    }

    // ---------------------------------------------------------------- users

    private function userMessage(array $to, array $from, string $text, bool $isPrivate, bool $isAdmin): void
    {
        $lower = mb_strtolower($text);
        if ($text === '/start' || $text === '/help' || str_contains($lower, 'راهنما')) {
            $this->tg->sendMessage($to, $this->helpText());
            return;
        }
        if (preg_match('/سهمیه|اعتبار من|چند\s*تا\s*(?:تحلیل\s*)?(?:مونده|مانده)/u', $text)) {
            $this->tg->sendMessage($to, $this->quotaText((int) $from['id']));
            return;
        }

        $req = SymbolParser::parse($text);
        if ($req === null) {
            if ($isPrivate) {
                $this->tg->sendMessage($to, 'برای تحلیل بنویسید مثلاً: <b>تحلیل BTC</b> یا <b>تحلیل ETH 1h</b>' . "\n" . 'راهنما: /help');
            }
            // In channel DMs, anything that is not an analysis request is left for the admin.
            return;
        }

        $user = $this->db->user((int) $from['id']);
        if (!empty($user['banned'])) {
            return;
        }
        if ($this->db->get('paused') === '1' && !$isAdmin) {
            $this->tg->sendMessage($to, '⏸ ربات تحلیل موقتاً غیرفعال است. لطفاً بعداً دوباره پیام بدهید.');
            return;
        }
        if (!$isAdmin && !$this->isAnalysisDay()) {
            $this->tg->sendMessage($to, $this->closedText());
            return;
        }

        $tf = $req['tf'] ?? ($this->config['market']['default_timeframe'] ?? '4h');
        if (!in_array($tf, SymbolParser::TIMEFRAMES, true)) {
            $tf = '4h';
        }
        $this->runAnalysis($to, $from, $req['base'], $tf, $isAdmin);
    }

    private function runAnalysis(array $to, array $from, string $base, string $tf, bool $unlimited): void
    {
        $userId = (int) $from['id'];
        $period = $this->periodKey();
        $limit = $unlimited ? -1 : (int) ($this->config['quota_per_user'] ?? 2);
        $usageId = $this->db->reserve($userId, $period, $limit, $base, $tf);
        if ($usageId === null) {
            $this->tg->sendMessage($to, $this->quotaText($userId, true));
            return;
        }

        $wait = $this->tg->sendMessage($to, '⏳ در حال تحلیل <b>' . htmlspecialchars($base) . '</b> در تایم ' . Fa::tf($tf) . '…' . "\n" . 'بررسی ساختار، اوردر بلاک‌ها و نقدینگی کمی زمان می‌برد.');
        $ok = false;
        try {
            $service = new AnalysisService($this->config, (string) $this->db->get('brain_notes', ''));
            $result = $service->analyze($base, $tf);
            $remaining = $unlimited ? null : max(0, $limit + (int) ($this->db->user($userId)['bonus'] ?? 0) - $this->db->used($userId, $period));
            $caption = $result['caption'];
            if ($remaining !== null) {
                $note = "\n🎟 تحلیل باقی‌مانده شما در این دوره: " . Fa::digits((string) $remaining);
                if (Render\Caption::length($caption . $note) <= 1024) {
                    $caption .= $note;
                } else {
                    $result['extra'] = trim($result['extra'] . "\n" . $note);
                }
            }
            $sent = $this->tg->sendPhoto($to, $result['image'], $caption);
            if ($sent === null) {
                // Fallback: photo without formatting problems
                $sent = $this->tg->sendPhoto($to, $result['image'], strip_tags($caption));
            }
            if ($sent !== null) {
                $ok = true;
                if ($result['extra'] !== '') {
                    $this->tg->sendMessage(['reply_to' => $sent['message_id'] ?? null] + $to, $result['extra']);
                }
                $this->adminLog($from, $result);
            }
        } catch (SymbolNotFound $e) {
            $this->tg->sendMessage($to, '❓ ارز <b>' . htmlspecialchars($base) . '</b> در صرافی‌ها پیدا نشد. نماد را بررسی کنید (مثلاً BTC، ETH، SOL).');
        } catch (MarketUnavailable $e) {
            $this->tg->sendMessage($to, '⚠️ دریافت داده بازار با مشکل مواجه شد. چند دقیقه دیگر دوباره امتحان کنید.');
            app_log('market unavailable: ' . $e->getMessage());
        } catch (\Throwable $e) {
            app_log('analysis failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $this->tg->sendMessage($to, '⚠️ در انجام تحلیل خطایی رخ داد. لطفاً دوباره امتحان کنید.');
        } finally {
            // Failed analyses do not use up the quota.
            $this->db->finish($usageId, $ok);
            if ($wait !== null && isset($wait['message_id'])) {
                $this->tg->deleteMessage($to['chat_id'], (int) $wait['message_id']);
            }
        }
    }

    // ---------------------------------------------------------------- admin

    private function adminCommand(array $to, string $text): void
    {
        $parts = preg_split('/\s+/u', trim($text), 3);
        $cmd = strtolower(preg_replace('/@\w+$/', '', $parts[0]));
        $arg1 = $parts[1] ?? '';
        $arg2 = $parts[2] ?? '';
        $period = $this->periodKey();

        switch ($cmd) {
            case '/start':
            case '/help':
            case '/admin':
                $this->tg->sendMessage($to, $this->adminHelp());
                return;

            case '/test':
            case '/analyze':
                $req = SymbolParser::parse('تحلیل ' . $arg1 . ' ' . $arg2);
                if ($req === null) {
                    $this->tg->sendMessage($to, 'مثال: <code>/test BTC 4h</code>');
                    return;
                }
                $tf = $req['tf'] ?? ($this->config['market']['default_timeframe'] ?? '4h');
                $this->runAnalysis($to, ['id' => $this->config['admin_ids'][0] ?? 0, 'first_name' => 'admin'], $req['base'], $tf, true);
                return;

            case '/stats':
                $s = $this->db->stats($period);
                $top = implode('، ', array_map(static fn ($r) => $r['symbol'] . ' (' . $r['n'] . ')', $s['top'])) ?: '-';
                $this->tg->sendMessage($to, "📊 <b>آمار ربات</b>\n"
                    . 'کاربران: ' . $s['users'] . "\n"
                    . 'تحلیل‌های این دوره: ' . $s['period_done'] . ' (برای ' . $s['period_users'] . " نفر)\n"
                    . 'کل تحلیل‌ها: ' . $s['total_done'] . "\n"
                    . 'پرتکرارها: ' . htmlspecialchars($top) . "\n"
                    . 'وضعیت: ' . ($this->db->get('paused') === '1' ? '⏸ متوقف' : '✅ فعال') . "\n"
                    . 'امروز روز تحلیل است؟ ' . ($this->isAnalysisDay() ? 'بله' : 'خیر'));
                return;

            case '/user':
            case '/quota':
                $u = $this->db->findUser($arg1);
                if (!$u) {
                    $this->tg->sendMessage($to, 'کاربر پیدا نشد. مثال: <code>/user 123456789</code> یا <code>/user @username</code>');
                    return;
                }
                $this->tg->sendMessage($to, '👤 ' . htmlspecialchars(trim(($u['first_name'] ?? '') . ' @' . ($u['username'] ?? ''))) . ' (<code>' . $u['user_id'] . "</code>)\n"
                    . 'استفاده در این دوره: ' . $this->db->used((int) $u['user_id'], $period) . ' از ' . ((int) ($this->config['quota_per_user'] ?? 2) + (int) $u['bonus']) . "\n"
                    . 'مسدود: ' . ($u['banned'] ? 'بله' : 'خیر'));
                return;

            case '/addquota':
            case '/bonus':
                $u = $this->db->findUser($arg1);
                $n = (int) ($arg2 ?: 1);
                if (!$u) {
                    $this->tg->sendMessage($to, 'مثال: <code>/addquota 123456789 2</code>');
                    return;
                }
                $this->db->addBonus((int) $u['user_id'], $n);
                $this->tg->sendMessage($to, "✅ {$n} تحلیل اضافه به کاربر <code>{$u['user_id']}</code> داده شد.");
                return;

            case '/reset':
                $u = $this->db->findUser($arg1);
                if (!$u) {
                    $this->tg->sendMessage($to, 'مثال: <code>/reset 123456789</code>');
                    return;
                }
                $this->db->resetUsage((int) $u['user_id'], $period);
                $this->tg->sendMessage($to, "♻️ سهمیه کاربر <code>{$u['user_id']}</code> در این دوره صفر شد.");
                return;

            case '/ban':
            case '/unban':
                $u = $this->db->findUser($arg1);
                if (!$u) {
                    $this->tg->sendMessage($to, 'مثال: <code>' . $cmd . ' 123456789</code>');
                    return;
                }
                $this->db->setBanned((int) $u['user_id'], $cmd === '/ban');
                $this->tg->sendMessage($to, $cmd === '/ban' ? '🚫 کاربر مسدود شد.' : '✅ کاربر آزاد شد.');
                return;

            case '/off':
                $this->db->set('paused', '1');
                $this->tg->sendMessage($to, '⏸ ربات برای کاربران متوقف شد. برای فعال‌سازی: /on');
                return;

            case '/on':
                $this->db->set('paused', null);
                $this->tg->sendMessage($to, '✅ ربات فعال شد.');
                return;

            case '/brain':
                $notes = trim(mb_substr($text, mb_strlen($parts[0])));
                if ($notes === '') {
                    $cur = (string) $this->db->get('brain_notes', '');
                    $this->tg->sendMessage($to, "🧠 <b>دستورالعمل فعلی مغز ربات:</b>\n" . ($cur !== '' ? htmlspecialchars($cur) : '(خالی)') . "\n\nبرای افزودن: <code>/brain متن دستورالعمل</code>\nبرای پاک کردن: /brain_clear");
                    return;
                }
                $cur = trim((string) $this->db->get('brain_notes', ''));
                $this->db->set('brain_notes', trim($cur . "\n- " . $notes));
                $this->tg->sendMessage($to, "🧠 به مغز ربات اضافه شد. از تحلیل بعدی Gemini این دستورالعمل را رعایت می‌کند.");
                return;

            case '/brain_clear':
                $this->db->set('brain_notes', null);
                $this->tg->sendMessage($to, '🧠 دستورالعمل‌های مغز ربات پاک شد.');
                return;
        }
        $this->tg->sendMessage($to, 'دستور ناشناخته. /help');
    }

    private function adminLog(array $from, array $result): void
    {
        if (empty($this->config['admin_log'])) {
            return;
        }
        $a = $result['analysis'];
        if ($this->isAdmin((int) $from['id'])) {
            return;
        }
        $who = htmlspecialchars(trim(($from['first_name'] ?? '') . (isset($from['username']) ? ' @' . $from['username'] : '')));
        $text = '📝 تحلیل <b>' . $a['base'] . '</b> ' . strtoupper($a['timeframe']) . ' برای ' . $who . ' (<code>' . $from['id'] . '</code>)'
            . "\n" . ($a['plan']['side'] === 'long' ? '🟢 Long' : '🔴 Short') . ' | اعتبار ' . $a['confidence'] . '% | منبع ' . $a['source']
            . ' | Gemini: ' . (!empty($a['ai']['used']) ? ($a['ai']['plan_from_ai'] ? 'پلن + متن' : 'فقط متن') : 'خیر');
        foreach ($this->config['admin_ids'] ?? [] as $admin) {
            $this->tg->sendMessage(['chat_id' => $admin], $text);
        }
    }

    // ---------------------------------------------------------------- helpers

    private function isAdmin(int $id): bool
    {
        return in_array($id, array_map('intval', $this->config['admin_ids'] ?? []), true);
    }

    private function isAnalysisDay(): bool
    {
        return in_array((int) date('w'), array_map('intval', $this->config['analysis_days'] ?? [6, 0]), true);
    }

    /** Quota period id; weeks start on Saturday. */
    public function periodKey(?int $ts = null): string
    {
        $ts ??= time();
        return match ($this->config['quota_period'] ?? 'week') {
            'day' => 'd:' . date('Y-m-d', $ts),
            'lifetime' => 'all',
            default => 'w:' . date('Y-m-d', strtotime('-' . (((int) date('w', $ts) + 1) % 7) . ' days', $ts)),
        };
    }

    private function dayNames(): string
    {
        return implode(' و ', array_map(static fn ($d) => Fa::weekday((int) $d), $this->config['analysis_days'] ?? [6, 0]));
    }

    private function closedText(): string
    {
        $days = array_map('intval', $this->config['analysis_days'] ?? [6, 0]);
        $today = (int) date('w');
        $wait = 7;
        foreach ($days as $d) {
            $wait = min($wait, ($d - $today + 7) % 7 ?: 7);
        }
        return '⏰ تحلیل ارزها فقط روزهای <b>' . $this->dayNames() . '</b> انجام می‌شود.'
            . "\n" . 'روز تحلیل بعدی: ' . Fa::weekday(($today + $wait) % 7) . ' (' . Fa::digits((string) $wait) . ' روز دیگر)'
            . "\n" . 'همان روز پیام بدهید، مثلاً: <b>تحلیل BTC</b>';
    }

    private function quotaText(int $userId, bool $exhausted = false): string
    {
        $limit = (int) ($this->config['quota_per_user'] ?? 2) + (int) ($this->db->user($userId)['bonus'] ?? 0);
        $used = $this->db->used($userId, $this->periodKey());
        $left = max(0, $limit - $used);
        $period = match ($this->config['quota_period'] ?? 'week') {
            'day' => 'امروز',
            'lifetime' => '',
            default => 'این هفته',
        };
        if ($exhausted) {
            return '🎟 سهمیه تحلیل شما ' . $period . ' تمام شده است (' . Fa::digits((string) $limit) . ' تحلیل).'
                . (($this->config['quota_period'] ?? 'week') === 'week' ? "\n" . 'از شنبه آینده دوباره می‌توانید درخواست بدهید.' : '');
        }
        return '🎟 ' . trim($period . ' ' . Fa::digits((string) $left) . ' تحلیل دیگر می‌توانید بگیرید') . ' (از ' . Fa::digits((string) $limit) . ').';
    }

    private function helpText(): string
    {
        $q = Fa::digits((string) ($this->config['quota_per_user'] ?? 2));
        return "🤖 <b>ربات تحلیلگر</b>\n\n"
            . "برای دریافت تحلیل، نام ارز را بفرستید:\n"
            . "• <code>تحلیل BTC</code>\n"
            . "• <code>تحلیل ETH 1h</code>\n"
            . "• <code>تحلیل سولانا روزانه</code>\n\n"
            . "تایم‌فریم‌ها: 15m، 30m، 1h، 4h (پیش‌فرض)، 1d، 1w\n"
            . '📅 روزهای تحلیل: ' . $this->dayNames() . "\n"
            . "🎟 سهمیه هر کاربر: {$q} تحلیل در هفته\n"
            . "برای دیدن سهمیه بنویسید: <code>سهمیه</code>";
    }

    private function adminHelp(): string
    {
        return "🛠 <b>دستورات مدیر</b>\n\n"
            . "<code>/test BTC 4h</code> تحلیل فوری (بدون محدودیت روز و سهمیه)\n"
            . "<code>/stats</code> آمار\n"
            . "<code>/user 123</code> وضعیت کاربر (آیدی عددی یا @یوزرنیم)\n"
            . "<code>/addquota 123 2</code> سهمیه اضافه\n"
            . "<code>/reset 123</code> صفر کردن سهمیه این هفته\n"
            . "<code>/ban 123</code> / <code>/unban 123</code>\n"
            . "<code>/off</code> / <code>/on</code> توقف و فعال‌سازی ربات\n"
            . "<code>/brain متن</code> آموزش سبک تحلیل به Gemini\n"
            . "<code>/brain</code> نمایش آموزش‌ها، <code>/brain_clear</code> پاک کردن";
    }
}

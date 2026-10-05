<?php

declare(strict_types=1);

namespace App;

use App\Render\Caption;
use App\Support\Fa;
use App\Telegram\Client;
use App\Telegram\Entities;

/**
 * /panel: inline-keyboard settings for admins. Values are stored in the settings
 * table and override config.php: publish channel, on/off, quota, days, Gemini, DM notice.
 */
final class AdminPanel
{
    // Display order of the week (Persian week starts on Saturday); values are PHP date('w')
    private const WEEK = [6, 0, 1, 2, 3, 4, 5];

    public function __construct(private array $config, private Client $tg, private Storage $db)
    {
    }

    // ---------------------------------------------------------------- effective settings

    public static function channel(Storage $db): ?array
    {
        $raw = $db->get('channel');
        $c = $raw ? json_decode($raw, true) : null;
        return is_array($c) && isset($c['id']) ? $c : null;
    }

    public static function quota(array $config, Storage $db): int
    {
        return (int) ($db->get('quota_per_user') ?? ($config['quota_per_user'] ?? 2));
    }

    /** @return int[] */
    public static function days(array $config, Storage $db): array
    {
        $raw = $db->get('analysis_days');
        if ($raw !== null) {
            return $raw === '' ? [] : array_map('intval', explode(',', $raw));
        }
        return array_map('intval', $config['analysis_days'] ?? [6, 0]);
    }

    public static function geminiOn(array $config, Storage $db): bool
    {
        return ($config['gemini']['api_key'] ?? '') !== '' && $db->get('gemini') !== 'off';
    }

    public static function dmNotice(Storage $db): bool
    {
        return $db->get('dm_notice') !== 'off';
    }

    // ---------------------------------------------------------------- view

    public function show(array $to, ?int $editId = null, string $view = 'main'): void
    {
        [$text, $kb] = match ($view) {
            'days' => $this->daysView(),
            'texts' => $this->textsView(),
            default => $this->mainView(),
        };
        if ($editId !== null) {
            $this->tg->editMessage($to['chat_id'], $editId, $text, $kb);
            return;
        }
        $this->tg->sendMessage($to, $text, ['reply_markup' => ['inline_keyboard' => $kb]]);
    }

    private function mainView(): array
    {
        $ch = self::channel($this->db);
        $paused = $this->db->get('paused') === '1';
        $quota = self::quota($this->config, $this->db);
        $days = self::days($this->config, $this->db);
        $gem = self::geminiOn($this->config, $this->db);
        $hasKey = ($this->config['gemini']['api_key'] ?? '') !== '';
        $dm = self::dmNotice($this->db);
        $log = $this->db->get('admin_log') === 'on';
        $gemStatus = (string) $this->db->get('gemini_status', '');
        $dayNames = $days ? implode('، ', array_map(static fn ($d) => Fa::weekday($d), array_values(array_filter(self::WEEK, static fn ($d) => in_array($d, $days, true))))) : 'هیچ روزی';

        $text = "📊 <b>پنل ربات تحلیل</b>\n\n"
            . '📢 کانال انتشار تحلیل‌ها: ' . ($ch ? '<b>' . htmlspecialchars($ch['title']) . '</b>' . (!empty($ch['username']) ? ' (@' . htmlspecialchars($ch['username']) . ')' : '') : '❌ تنظیم نشده (تحلیل در دایرکت ارسال می‌شود)') . "\n"
            . '⚙️ وضعیت ربات: ' . ($paused ? '⏸ متوقف' : '✅ فعال') . "\n"
            . '🎟 سهمیه هر کاربر: ' . Fa::digits((string) $quota) . " تحلیل در هفته\n"
            . '📅 روزهای تحلیل: ' . $dayNames . "\n"
            . '🧠 Gemini: ' . (!$hasKey ? 'کلید ندارد' : ($gem ? 'روشن' : 'خاموش'))
            . ($gem && $gemStatus !== '' ? "\n      └ آخرین وضعیت: <code>" . htmlspecialchars(mb_substr($gemStatus, 0, 160)) . '</code>' : '') . "\n"
            . '🔔 اطلاع‌رسانی در دایرکت بعد از انتشار: ' . ($dm ? 'روشن' : 'خاموش') . "\n"
            . '📝 گزارش هر تحلیل برای مدیر: ' . ($log ? 'روشن' : 'خاموش');

        $kb = [
            [['text' => $ch ? '📢 تغییر کانال' : '📢 تنظیم کانال', 'callback_data' => 'an:channel']],
        ];
        if ($ch) {
            $kb[0][] = ['text' => '🗑 حذف کانال', 'callback_data' => 'an:channel_clear'];
        }
        $kb[] = [
            ['text' => $paused ? '▶️ روشن کردن ربات' : '⏸ توقف ربات', 'callback_data' => 'an:pause'],
            ['text' => '🧠 Gemini: ' . ($gem ? 'روشن' : 'خاموش'), 'callback_data' => 'an:gemini'],
        ];
        $kb[] = [
            ['text' => '➖', 'callback_data' => 'an:quota:-1'],
            ['text' => '🎟 سهمیه: ' . Fa::digits((string) $quota), 'callback_data' => 'an:noop'],
            ['text' => '➕', 'callback_data' => 'an:quota:1'],
        ];
        $kb[] = [
            ['text' => '📅 روزهای تحلیل', 'callback_data' => 'an:days'],
            ['text' => '🔔 اطلاع دایرکت: ' . ($dm ? 'روشن' : 'خاموش'), 'callback_data' => 'an:dm'],
        ];
        $kb[] = [
            ['text' => '✏️ ویرایش متن‌ها و کپشن', 'callback_data' => 'an:texts'],
            ['text' => '📝 گزارش مدیر: ' . ($log ? 'روشن' : 'خاموش'), 'callback_data' => 'an:log'],
        ];
        $kb[] = [
            ['text' => '📊 آمار', 'callback_data' => 'an:stats'],
            ['text' => '🔄 بروزرسانی', 'callback_data' => 'an:refresh'],
        ];
        $kb[] = [['text' => '🏠 منوی اصلی ربات', 'callback_data' => 'hub:home']];
        return [$text, $kb];
    }

    private function textsView(): array
    {
        $texts = new Texts($this->db);
        $kb = [];
        $row = [];
        foreach (Texts::DEFS as $key => $def) {
            $row[] = ['text' => ($texts->isCustom($key) ? '✏️ ' : '') . $def['title'], 'callback_data' => 'an:text:' . $key];
            if (count($row) === 2) {
                $kb[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $kb[] = $row;
        }
        $kb[] = [['text' => '↩️ بازگشت', 'callback_data' => 'an:back']];
        return ["✏️ <b>ویرایش متن‌های ربات</b>\n\nمتنی را که می‌خواهید تغییر دهید انتخاب کنید. متن‌هایی که ✏️ دارند قبلاً ویرایش شده‌اند.\n\nدر متن جدید می‌توانید از <b>بولد</b>، <i>ایتالیک</i>، نقل‌قول (Quote)، اسپویلر، لینک و ایموجی پریمیوم استفاده کنید.", $kb];
    }

    private function daysView(): array
    {
        $days = self::days($this->config, $this->db);
        $kb = [];
        $row = [];
        foreach (self::WEEK as $d) {
            $row[] = ['text' => (in_array($d, $days, true) ? '✅ ' : '▫️ ') . Fa::weekday($d), 'callback_data' => 'an:day:' . $d];
            if (count($row) === 3) {
                $kb[] = $row;
                $row = [];
            }
        }
        if ($row) {
            $kb[] = $row;
        }
        $kb[] = [['text' => '↩️ بازگشت', 'callback_data' => 'an:back']];
        return ["📅 <b>روزهای تحلیل</b>\nروی هر روز بزنید تا فعال یا غیرفعال شود.", $kb];
    }

    // ---------------------------------------------------------------- actions

    public function callback(array $cq): void
    {
        $data = (string) ($cq['data'] ?? '');
        $msg = $cq['message'] ?? [];
        $chatId = $msg['chat']['id'] ?? ($cq['from']['id'] ?? 0);
        $to = ['chat_id' => $chatId];
        $editId = isset($msg['message_id']) ? (int) $msg['message_id'] : null;
        $parts = explode(':', $data);
        $toast = '';
        $view = 'main';

        switch ($parts[1] ?? '') {
            case 'channel':
                $this->db->set('admin_state_' . $cq['from']['id'], 'await_channel');
                $this->tg->answerCallback($cq['id']);
                $this->tg->sendMessage($to, "📢 <b>تنظیم کانال انتشار</b>\n\n"
                    . "۱) ربات را در کانال <b>ادمین</b> کنید (با دسترسی ارسال پیام).\n"
                    . "۲) یکی از این‌ها را بفرستید:\n"
                    . "• آیدی کانال، مثلاً <code>@mychannel</code>\n"
                    . "• آیدی عددی، مثلاً <code>-1001234567890</code>\n"
                    . "• یا یک پیام از کانال را همین‌جا فوروارد کنید.\n\n"
                    . 'برای لغو: /cancel');
                return;
            case 'texts':
                $view = 'texts';
                break;
            case 'log':
                $this->db->set('admin_log', $this->db->get('admin_log') === 'on' ? null : 'on');
                break;
            case 'text':
                $key = (string) ($parts[2] ?? '');
                if (!isset(Texts::DEFS[$key])) {
                    break;
                }
                $this->tg->answerCallback($cq['id']);
                $this->startTextEdit($to, (int) $cq['from']['id'], $key);
                return;
            case 'text_reset':
                $key = (string) ($parts[2] ?? '');
                if (isset(Texts::DEFS[$key])) {
                    (new Texts($this->db))->set($key, null);
                    $this->db->set('admin_state_' . $cq['from']['id'], null);
                    $this->tg->answerCallback($cq['id'], 'متن پیش‌فرض برگشت');
                    $this->tg->sendMessage($to, '♻️ متن «' . Texts::DEFS[$key]['title'] . '» به حالت پیش‌فرض برگشت.');
                    $this->show($to, null, 'texts');
                    return;
                }
                break;
            case 'channel_clear':
                $this->db->set('channel', null);
                $toast = 'کانال حذف شد';
                break;
            case 'pause':
                $this->db->set('paused', $this->db->get('paused') === '1' ? null : '1');
                $toast = $this->db->get('paused') === '1' ? 'ربات متوقف شد' : 'ربات فعال شد';
                break;
            case 'gemini':
                if (($this->config['gemini']['api_key'] ?? '') === '') {
                    $toast = 'کلید Gemini در config.php خالی است';
                    break;
                }
                $this->db->set('gemini', $this->db->get('gemini') === 'off' ? null : 'off');
                break;
            case 'quota':
                $q = max(0, min(50, self::quota($this->config, $this->db) + (int) ($parts[2] ?? 0)));
                $this->db->set('quota_per_user', (string) $q);
                break;
            case 'days':
                $view = 'days';
                break;
            case 'day':
                $d = (int) ($parts[2] ?? -1);
                $days = self::days($this->config, $this->db);
                $days = in_array($d, $days, true) ? array_values(array_diff($days, [$d])) : array_merge($days, [$d]);
                sort($days);
                $this->db->set('analysis_days', implode(',', $days));
                $view = 'days';
                break;
            case 'dm':
                $this->db->set('dm_notice', self::dmNotice($this->db) ? 'off' : null);
                break;
            case 'stats':
                $s = $this->db->stats((new Bot($this->config, $this->tg, $this->db))->periodKey());
                $top = implode('، ', array_map(static fn ($r) => $r['symbol'] . ' (' . $r['n'] . ')', $s['top'])) ?: '-';
                $this->tg->answerCallback($cq['id']);
                $this->tg->sendMessage($to, "📊 <b>آمار ربات</b>\n"
                    . 'کاربران: ' . $s['users'] . "\n"
                    . 'تحلیل‌های این هفته: ' . $s['period_done'] . ' (برای ' . $s['period_users'] . " نفر)\n"
                    . 'کل تحلیل‌ها: ' . $s['total_done'] . "\n"
                    . 'پرتکرارها: ' . htmlspecialchars($top));
                return;
            case 'noop':
                $this->tg->answerCallback($cq['id']);
                return;
        }
        $this->tg->answerCallback($cq['id'], $toast);
        if ($editId !== null) {
            $this->show($to, $editId, $view);
        }
    }

    private function startTextEdit(array $to, int $adminId, string $key): void
    {
        $def = Texts::DEFS[$key];
        $texts = new Texts($this->db);
        $this->db->set('admin_state_' . $adminId, 'edit_text:' . $key);
        $info = '✏️ <b>ویرایش: ' . $def['title'] . "</b>\n\n"
            . (!empty($def['plain'])
                ? "متن جدید را بفرستید (فقط متن ساده).\n"
                : "متن جدید را با همان قالب‌بندی دلخواه بفرستید؛ بولد، نقل‌قول، لینک و ایموجی پریمیوم حفظ می‌شوند.\n"
                    . "پیشنهاد: پیام بعدی (متن فعلی) را کپی کنید، تغییر دهید و بفرستید.\n")
            . ($def['vars'] !== '' ? "\n🔤 متغیرها (عیناً بنویسید):\n<code>" . htmlspecialchars($def['vars']) . "</code>\n" : '')
            . ($key === 'caption' ? "\nℹ️ {summary} = خلاصه و ابطال Gemini، {reasons} = لیست دلایل، {liquidity} = نقدینگی. کپشن عکس حداکثر ۱۰۲۴ کاراکتر است.\n" : '')
            . "\nبرای لغو: /cancel";
        $this->tg->sendMessage($to, $info, ['reply_markup' => ['inline_keyboard' => [[
            ['text' => '♻️ بازگردانی متن پیش‌فرض', 'callback_data' => 'an:text_reset:' . $key],
        ]]]]);
        $current = $texts->get($key);
        if ($this->tg->sendMessage($to, !empty($def['plain']) ? htmlspecialchars($current) : $current) === null) {
            $this->tg->sendMessage($to, '<pre>' . htmlspecialchars($current) . '</pre>');
        }
    }

    private function finishTextEdit(array $to, array $msg, string $stateKey, string $key): void
    {
        $def = Texts::DEFS[$key];
        $raw = (string) ($msg['text'] ?? $msg['caption'] ?? '');
        if (trim($raw) === '') {
            $this->tg->sendMessage($to, 'متن خالی است. متن جدید را بفرستید یا /cancel');
            return;
        }
        $texts = new Texts($this->db);
        $old = $this->db->get('text_' . $key);
        if (!empty($def['plain'])) {
            $new = trim($raw);
        } else {
            $new = Entities::toHtml($raw, $msg['entities'] ?? $msg['caption_entities'] ?? []);
        }
        $texts->set($key, $new);

        $preview = !empty($def['plain']) ? htmlspecialchars($new) : Texts::fill($new, Texts::sampleVars());
        $note = '';
        if ($key === 'caption') {
            $len = Caption::length($preview);
            $note = "\n\n📏 طول کپشن با داده نمونه: " . Fa::digits((string) $len) . ' از ۱۰۲۴'
                . ($len > 1024 ? ' ⚠️ (طولانی است؛ بخش‌های اختیاری حذف می‌شوند یا متن جدا ارسال می‌شود)' : '');
        }
        $this->tg->sendMessage($to, '👁 <b>پیش‌نمایش با داده نمونه:</b>');
        if ($this->tg->sendMessage($to, $preview) === null) {
            $texts->set($key, $old);
            $this->tg->sendMessage($to, '❌ تلگرام این قالب را نپذیرفت و ذخیره نشد. دوباره بفرستید یا /cancel');
            return;
        }
        $this->db->set($stateKey, null);
        $this->tg->sendMessage($to, '✅ متن «' . $def['title'] . '» ذخیره شد.' . $note);
        $this->show($to, null, 'texts');
    }

    /**
     * Handles the admin's reply while the panel waits for input. Returns true if consumed.
     */
    public function handleState(array $to, array $msg): bool
    {
        $key = 'admin_state_' . $msg['from']['id'];
        $state = (string) $this->db->get($key, '');
        if ($state === '') {
            return false;
        }
        $text = trim((string) ($msg['text'] ?? ''));
        if ($text === '/cancel') {
            $this->db->set($key, null);
            $this->tg->sendMessage($to, 'لغو شد.');
            $this->show($to);
            return true;
        }
        if (str_starts_with($text, '/')) {
            // Any other command leaves the input mode and runs normally.
            $this->db->set($key, null);
            return false;
        }
        if (str_starts_with($state, 'edit_text:')) {
            $textKey = substr($state, 10);
            if (!isset(Texts::DEFS[$textKey])) {
                $this->db->set($key, null);
                return false;
            }
            $this->finishTextEdit($to, $msg, $key, $textKey);
            return true;
        }
        if ($state !== 'await_channel') {
            $this->db->set($key, null);
            return false;
        }

        // A forwarded channel post, @username, t.me link or numeric id
        $ref = null;
        if (($msg['forward_origin']['type'] ?? '') === 'channel') {
            $ref = $msg['forward_origin']['chat']['id'];
        } elseif (isset($msg['forward_from_chat']['id'])) {
            $ref = $msg['forward_from_chat']['id'];
        } elseif (preg_match('~(?:t\.me/|@)([A-Za-z0-9_]{4,})~', $text, $m)) {
            $ref = '@' . $m[1];
        } elseif (preg_match('/^-?\d{6,}$/', $text)) {
            $ref = $text;
        }
        if ($ref === null) {
            $this->tg->sendMessage($to, 'آیدی کانال را به شکل <code>@channel</code> بفرستید یا یک پیام از کانال فوروارد کنید. لغو: /cancel');
            return true;
        }

        $chat = $this->tg->call('getChat', ['chat_id' => $ref]);
        if ($chat === null || ($chat['type'] ?? '') !== 'channel') {
            $this->tg->sendMessage($to, '❌ کانال پیدا نشد. مطمئن شوید آیدی درست است و ربات عضو و ادمین کانال است.');
            return true;
        }
        $member = $this->tg->call('getChatMember', ['chat_id' => $chat['id'], 'user_id' => $this->tg->botId()]);
        $isAdmin = ($member['status'] ?? '') === 'administrator' && !empty($member['can_post_messages']);
        if (!$isAdmin) {
            $this->tg->sendMessage($to, '❌ ربات در کانال <b>' . htmlspecialchars((string) ($chat['title'] ?? '')) . '</b> ادمین نیست یا اجازه ارسال پیام ندارد. اول ربات را ادمین کنید و دوباره بفرستید.');
            return true;
        }
        $this->db->set('channel', json_encode(['id' => $chat['id'], 'title' => $chat['title'] ?? '', 'username' => $chat['username'] ?? ''], JSON_UNESCAPED_UNICODE));
        $this->db->set($key, null);
        $this->tg->sendMessage($to, '✅ کانال <b>' . htmlspecialchars((string) ($chat['title'] ?? '')) . '</b> تنظیم شد. از این به بعد تحلیل‌ها در این کانال منتشر می‌شوند.');
        $this->show($to);
        return true;
    }
}

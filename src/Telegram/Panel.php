<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Jalali;
use Nikto\Core\Settings;
use Nikto\Jobs\CalendarJob;
use Nikto\Jobs\Dispatcher;
use Nikto\Jobs\PricesJob;
use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Render\Theme;
use Nikto\Text\Persian;

/**
 * پنل مدیریت داخل تلگرام: کانال‌ها، زمان‌بندی، تم و تنظیمات.
 */
final class Panel
{
    private const DAYS = [
        6 => 'شنبه', 7 => 'یکشنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه',
        3 => 'چهارشنبه', 4 => 'پنجشنبه', 5 => 'جمعه',
    ];

    private bool $answered = false;

    public function __construct(private Api $api)
    {
    }

    // ==================================================== ورودی‌ها

    public function handleUpdate(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);
            return;
        }
        if (isset($update['message'])) {
            $this->onMessage($update['message']);
        }
    }

    private function onMessage(array $msg): void
    {
        $chatId = (int) ($msg['chat']['id'] ?? 0);
        $userId = (int) ($msg['from']['id'] ?? 0);
        $text = trim((string) ($msg['text'] ?? ''));

        if (($msg['chat']['type'] ?? '') !== 'private') {
            return; // پنل فقط در چت خصوصی کار می‌کند
        }

        if (!Access::isAdmin($userId)) {
            if (str_starts_with($text, '/start') || str_starts_with($text, '/id')) {
                $this->api->sendMessage(
                    $chatId,
                    "سلام 👋\nاین ربات اختصاصی کانال <b>" . htmlspecialchars(Settings::get('brand')) . "</b> است.\n\n"
                    . 'شناسه‌ی عددی شما: <code>' . $userId . '</code>'
                );
            }
            return;
        }

        // ورودی‌های حالت‌دار
        $state = State::get($userId);
        if ($state['action'] !== '' && !str_starts_with($text, '/')) {
            $this->handleStateInput($chatId, $userId, $state, $msg);
            return;
        }

        // افزودن کانال با فوروارد پست
        $forwardChat = $msg['forward_origin']['chat'] ?? ($msg['forward_from_chat'] ?? null);
        if (is_array($forwardChat) && isset($forwardChat['id'])) {
            $this->addChannel($chatId, (string) $forwardChat['id']);
            return;
        }

        match (true) {
            str_starts_with($text, '/start'), str_starts_with($text, '/panel'), $text === 'پنل' => $this->showMain($chatId),
            str_starts_with($text, '/id')     => $this->api->sendMessage($chatId, 'شناسه‌ی عددی شما: <code>' . $userId . '</code>'),
            str_starts_with($text, '/status') => $this->send($chatId, $this->screenStatus()),
            str_starts_with($text, '/help')   => $this->send($chatId, $this->screenHelp()),
            str_starts_with($text, '/send')   => $this->quickSend($chatId, trim(substr($text, 5))),
            default => $this->showMain($chatId),
        };
    }

    private function onCallback(array $cb): void
    {
        $data = (string) ($cb['data'] ?? '');
        $userId = (int) ($cb['from']['id'] ?? 0);
        $chatId = (int) ($cb['message']['chat']['id'] ?? 0);
        $messageId = (int) ($cb['message']['message_id'] ?? 0);
        $cbId = (string) ($cb['id'] ?? '');

        if (!Access::isAdmin($userId)) {
            $this->api->answerCallback($cbId, 'دسترسی ندارید.', true);
            return;
        }

        $parts = explode(':', $data);
        $toast = '';
        $this->answered = false;

        try {
            $screen = $this->route($parts, $chatId, $userId, $cbId, $toast);
        } catch (\Throwable $e) {
            $this->api->answerCallback($cbId, 'خطا: ' . mb_substr($e->getMessage(), 0, 150), true);
            return;
        }

        if ($screen !== null) {
            $this->api->editMessage($chatId, $messageId, $screen['text'], $screen['kb']);
        }
        if (!$this->answered) {
            $this->api->answerCallback($cbId, $toast);
        }
        $this->answered = false;
    }

    /**
     * مسیریابی دکمه‌ها.
     * @return array{text:string,kb:array}|null
     */
    private function route(array $p, int $chatId, int $userId, string $cbId, string &$toast): ?array
    {
        $head = $p[0] ?? 'm';

        switch ($head) {
            case 'm':
                State::clear($userId);
                return $this->screenMain();

            case 'nop':
                return null;

            // ---------------------------------------------- کارها
            case 'j':
                $key = $p[1] ?? '';
                $job = Registry::refresh($key);
                if ($job === null) {
                    $toast = 'کار نامعتبر';
                    return $this->screenMain();
                }
                $action = $p[2] ?? '';

                switch ($action) {
                    case '':
                        return $this->screenJob($key);

                    case 'on':
                        $job->setEnabled(!$job->enabled());
                        $toast = $job->enabled() ? 'فعال شد ✅' : 'غیرفعال شد ⛔️';
                        return $this->screenJob($key);

                    case 'th':
                        if (isset($p[3])) {
                            $job->setTheme($p[3]);
                            $toast = 'تم تغییر کرد';
                            return $this->screenJob($key);
                        }
                        return $this->screenThemes($key);

                    case 'ch':
                        if (isset($p[3])) {
                            $added = $job->toggleChannel((int) $p[3]);
                            $toast = $added ? 'کانال اضافه شد' : 'کانال حذف شد';
                        }
                        return $this->screenJobChannels($key);

                    case 'sc':
                        if (($p[3] ?? '') === 'add') {
                            State::set($userId, 'time_add', $key);
                            $this->api->sendMessage(
                                $chatId,
                                "⏰ <b>افزودن زمان ارسال</b>\n\nساعت مورد نظر را به وقت <b>"
                                . htmlspecialchars(Settings::get('timezone')) . "</b> بفرستید.\n\n"
                                . "می‌توانید چند زمان را با کاما جدا کنید؛ مثال:\n<code>09:00, 15:30, 21:45</code>\n\n"
                                . 'برای لغو /cancel را بفرستید.'
                            );
                            $toast = 'منتظر ورودی…';
                            return null;
                        }
                        return $this->screenSchedules($key);

                    case 'cap':
                        State::set($userId, 'caption', $key);
                        $this->api->sendMessage(
                            $chatId,
                            "📝 <b>ویرایش کپشن</b>\n\nمتن دلخواه را بفرستید. متغیرهای مجاز:\n"
                            . "<code>{summary}</code> خلاصه‌ی داده‌ها\n<code>{date}</code> تاریخ شمسی\n"
                            . "<code>{time}</code> ساعت\n<code>{brand}</code> نام کانال\n<code>{link}</code> لینک کانال\n\n"
                            . "کپشن فعلی:\n<code>" . htmlspecialchars($job->caption()) . "</code>\n\n"
                            . 'برای خالی‌کردن کپشن کلمه‌ی <code>خالی</code> و برای لغو /cancel را بفرستید.'
                        );
                        $toast = 'منتظر ورودی…';
                        return null;

                    case 'opt':
                        return $this->screenJobOptions($key);

                    case 'coins':
                        State::set($userId, 'coins', $key);
                        $this->api->sendMessage(
                            $chatId,
                            "🪙 <b>انتخاب ارزها</b>\n\nنماد ارزها را با کاما بفرستید (حداکثر ۹ ارز).\nمثال:\n"
                            . "<code>BTC,ETH,XRP,BNB,SOL,TRX</code>\n\nبرای لغو /cancel را بفرستید."
                        );
                        $toast = 'منتظر ورودی…';
                        return null;

                    case 'head':
                        State::set($userId, 'headline', $key);
                        $this->api->sendMessage($chatId, "✏️ عنوان دلخواه کارت را بفرستید.\nبرای لغو /cancel را بفرستید.");
                        $toast = 'منتظر ورودی…';
                        return null;

                    case 'shift':
                        $job->setOption('day_shift', (int) ($p[3] ?? 0));
                        $toast = 'تغییر کرد';
                        return $this->screenJobOptions($key);

                    case 'spark':
                        $job->setOption('sparkline', !$job->option('sparkline', true));
                        $toast = 'تغییر کرد';
                        return $this->screenJobOptions($key);

                    case 'pv':
                        $this->api->answerCallback($cbId, 'در حال ساخت پیش‌نمایش…');
                        $this->answered = true;
                        $this->preview($chatId, $key);
                        return null;

                    case 'go':
                        $this->api->answerCallback($cbId, 'در حال ارسال…');
                        $this->answered = true;
                        $this->sendNow($chatId, $key);
                        return null;
                }

                return $this->screenJob($key);

            // ---------------------------------------------- زمان‌بندی
            case 'sc':
                $sub = $p[1] ?? '';
                $id = (int) ($p[2] ?? 0);
                $row = Db::one('SELECT * FROM schedules WHERE id = :i', [':i' => $id]);
                if ($row === null) {
                    $toast = 'زمان‌بندی یافت نشد';
                    return $this->screenMain();
                }
                $jobKey = (string) $row['job_key'];

                switch ($sub) {
                    case 'd':
                        Db::exec('DELETE FROM schedules WHERE id = :i', [':i' => $id]);
                        $toast = 'حذف شد 🗑';
                        return $this->screenSchedules($jobKey);

                    case 't':
                        Db::exec('UPDATE schedules SET enabled = 1 - enabled WHERE id = :i', [':i' => $id]);
                        $toast = 'تغییر وضعیت';
                        return $this->screenSchedules($jobKey);

                    case 'days':
                        return $this->screenDays($id);

                    case 'all':
                        Db::exec('UPDATE schedules SET days = :d WHERE id = :i', [':d' => '*', ':i' => $id]);
                        $toast = 'همه‌ی روزها';
                        return $this->screenDays($id);

                    case 'day':
                        $day = (int) ($p[3] ?? 0);
                        $current = (string) $row['days'];
                        $list = $current === '*' ? array_keys(self::DAYS) : array_map('intval', array_filter(explode(',', $current), 'strlen'));
                        if (in_array($day, $list, true)) {
                            $list = array_values(array_diff($list, [$day]));
                        } else {
                            $list[] = $day;
                        }
                        sort($list);
                        $value = ($list === [] || count($list) === 7) ? '*' : implode(',', $list);
                        Db::exec('UPDATE schedules SET days = :d WHERE id = :i', [':d' => $value, ':i' => $id]);
                        return $this->screenDays($id);
                }

                return $this->screenSchedules($jobKey);

            // ---------------------------------------------- کانال‌ها
            case 'ch':
                $sub = $p[1] ?? '';
                if ($sub === 'add') {
                    State::set($userId, 'chan_add');
                    $this->api->sendMessage(
                        $chatId,
                        "📡 <b>افزودن کانال مقصد</b>\n\nیکی از روش‌های زیر:\n"
                        . "۱) یک پست از کانال را به اینجا فوروارد کنید\n"
                        . "۲) آیدی کانال را بفرستید؛ مثل <code>@my_channel</code>\n"
                        . "۳) شناسه‌ی عددی کانال؛ مثل <code>-1001234567890</code>\n\n"
                        . "⚠️ ربات باید در کانال <b>ادمین</b> باشد.\n\nبرای لغو /cancel را بفرستید."
                    );
                    $toast = 'منتظر ورودی…';
                    return null;
                }
                if ($sub === 'del') {
                    Channels::remove((int) ($p[2] ?? 0));
                    $toast = 'کانال حذف شد';
                    return $this->screenChannels();
                }
                if ($sub === 'chk') {
                    $report = Channels::check($this->api);
                    $ok = count(array_filter($report, static fn ($r) => $r['ok']));
                    $toast = sprintf('%d از %d کانال سالم', $ok, count($report));
                    return $this->screenChannels();
                }
                return $this->screenChannels();

            // ---------------------------------------------- تنظیمات
            case 's':
                return $this->routeSettings($p, $chatId, $userId, $toast);

            case 'st':
                return $this->screenStatus();

            case 'lg':
                return $this->screenLogs();

            case 'hp':
                return $this->screenHelp();
        }

        return $this->screenMain();
    }

    private function routeSettings(array $p, int $chatId, int $userId, string &$toast): ?array
    {
        $sub = $p[1] ?? '';

        $ask = function (string $action, string $prompt) use ($chatId, $userId, &$toast): ?array {
            State::set($userId, $action);
            $this->api->sendMessage($chatId, $prompt . "\n\nبرای لغو /cancel را بفرستید.");
            $toast = 'منتظر ورودی…';
            return null;
        };

        switch ($sub) {
            case '':
                return $this->screenSettings();

            case 'tz':
                return $ask('set_timezone', "🌍 <b>منطقه‌ی زمانی</b>\n\nنام منطقه را بفرستید؛ مثال:\n<code>Asia/Tehran</code>\n<code>Europe/Istanbul</code>\n<code>UTC</code>");

            case 'brand':
                return $ask('set_brand', "🏷 <b>نام کانال روی کارت‌ها</b>\n\nمتن دلخواه (ترجیحاً انگلیسی و کوتاه) را بفرستید؛ مثال:\n<code>NIKTO CRYPTO</code>");

            case 'link':
                return $ask('set_link', "🔗 <b>آیدی/لینک کانال</b>\n\nمتنی که پایین کارت و در کپشن می‌آید؛ مثال:\n<code>@nikto_crypto</code>");

            case 'cur':
                return $ask('set_currencies', "💱 <b>ارزهای تقویم اقتصادی</b>\n\nکدهای ارز را با کاما بفرستید؛ مثال:\n<code>USD,EUR,GBP,JPY,CAD</code>");

            case 'catch':
                return $ask('set_catchup', "⏱ <b>جبران تأخیر</b>\n\nحداکثر تأخیر مجاز برای ارسال (بر حسب دقیقه) را بفرستید؛ مثلاً <code>10</code>");

            case 'coins':
                return $ask('set_coins', "🪙 <b>ارزهای پیش‌فرض</b>\n\nنمادها را با کاما بفرستید؛ مثال:\n<code>BTC,ETH,XRP,BNB,SOL,TRX</code>");

            case 'digits':
                Settings::set('digits', Settings::get('digits') === 'fa' ? 'en' : 'fa');
                $toast = 'تغییر کرد';
                return $this->screenSettings();

            case 'ddata':
                Settings::set('digits_data', Settings::get('digits_data') === 'fa' ? 'en' : 'fa');
                $toast = 'تغییر کرد';
                return $this->screenSettings();

            case 'q':
                Settings::set('quality', Settings::get('quality') === 'high' ? 'normal' : 'high');
                $toast = 'تغییر کرد';
                return $this->screenSettings();

            case 'mode':
                Settings::set('send_mode', Settings::get('send_mode') === 'photo' ? 'document' : 'photo');
                $toast = 'تغییر کرد';
                return $this->screenSettings();

            case 'psrc':
                $order = ['auto' => 'binance', 'binance' => 'coingecko', 'coingecko' => 'auto'];
                Settings::set('price_source', $order[Settings::get('price_source')] ?? 'auto');
                $toast = 'منبع قیمت: ' . Settings::get('price_source');
                return $this->screenSettings();

            case 'csrc':
                $order = ['auto' => 'forexfactory', 'forexfactory' => 'tradingview', 'tradingview' => 'auto'];
                Settings::set('calendar_source', $order[Settings::get('calendar_source')] ?? 'auto');
                $toast = 'منبع تقویم: ' . Settings::get('calendar_source');
                return $this->screenSettings();

            case 'imp':
                $next = Settings::int('calendar_min_impact', 2) % 3 + 1;
                Settings::set('calendar_min_impact', (string) $next);
                $toast = 'حداقل اهمیت: ' . $next;
                return $this->screenSettings();

            case 'adm':
                $action = $p[2] ?? '';
                if ($action === 'add') {
                    return $ask('admin_add', "👤 <b>افزودن مدیر</b>\n\nشناسه‌ی عددی کاربر را بفرستید؛ مثال:\n<code>123456789</code>\n\nکاربر می‌تواند با دستور /id شناسه‌اش را ببیند.");
                }
                if ($action === 'del') {
                    if (!Access::isOwner($userId)) {
                        $toast = 'فقط مالک ربات می‌تواند مدیر حذف کند';
                        return $this->screenAdmins();
                    }
                    Access::remove((int) ($p[3] ?? 0));
                    $toast = 'حذف شد';
                }
                return $this->screenAdmins();
        }

        return $this->screenSettings();
    }

    // ==================================================== ورودی متنی

    private function handleStateInput(int $chatId, int $userId, array $state, array $msg): void
    {
        $text = trim((string) ($msg['text'] ?? ''));
        $action = $state['action'];
        $payload = $state['payload'];

        if ($text === '/cancel' || $text === 'لغو') {
            State::clear($userId);
            $this->api->sendMessage($chatId, 'لغو شد.');
            $this->showMain($chatId);
            return;
        }

        // فوروارد کانال در حالت افزودن کانال
        $forwardChat = $msg['forward_origin']['chat'] ?? ($msg['forward_from_chat'] ?? null);
        if ($action === 'chan_add' && is_array($forwardChat) && isset($forwardChat['id'])) {
            State::clear($userId);
            $this->addChannel($chatId, (string) $forwardChat['id']);
            return;
        }

        State::clear($userId);

        switch ($action) {
            case 'chan_add':
                $this->addChannel($chatId, $text);
                return;

            case 'time_add':
                $this->addTimes($chatId, $payload, $text);
                return;

            case 'caption':
                $job = Registry::refresh($payload);
                if ($job !== null) {
                    $job->setCaption($text === 'خالی' ? '' : $text);
                    $this->api->sendMessage($chatId, '✅ کپشن ذخیره شد.');
                    $this->send($chatId, $this->screenJob($payload));
                }
                return;

            case 'coins':
                $job = Registry::refresh($payload);
                if ($job instanceof PricesJob) {
                    $list = $this->parseCoins($text);
                    if ($list === []) {
                        $this->api->sendMessage($chatId, '❌ نماد معتبری پیدا نشد. دوباره تلاش کنید.');
                        return;
                    }
                    $job->setOption('coins', implode(',', $list));
                    $this->api->sendMessage($chatId, '✅ ارزها ذخیره شد: <code>' . implode(', ', $list) . '</code>');
                    $this->send($chatId, $this->screenJobOptions($payload));
                }
                return;

            case 'headline':
                $job = Registry::refresh($payload);
                if ($job !== null) {
                    $job->setOption('headline', mb_substr($text, 0, 60));
                    $this->api->sendMessage($chatId, '✅ عنوان ذخیره شد.');
                    $this->send($chatId, $this->screenJobOptions($payload));
                }
                return;

            case 'set_timezone':
                try {
                    new \DateTimeZone($text);
                } catch (\Throwable) {
                    $this->api->sendMessage($chatId, '❌ منطقه‌ی زمانی نامعتبر است. مثال: <code>Asia/Tehran</code>');
                    return;
                }
                Settings::set('timezone', $text);
                $this->api->sendMessage($chatId, '✅ منطقه‌ی زمانی: <code>' . htmlspecialchars($text) . '</code>');
                $this->send($chatId, $this->screenSettings());
                return;

            case 'set_brand':
                Settings::set('brand', mb_substr($text, 0, 40));
                $this->api->sendMessage($chatId, '✅ نام کانال ذخیره شد.');
                $this->send($chatId, $this->screenSettings());
                return;

            case 'set_link':
                Settings::set('brand_link', mb_substr($text, 0, 60));
                $this->api->sendMessage($chatId, '✅ ذخیره شد.');
                $this->send($chatId, $this->screenSettings());
                return;

            case 'set_currencies':
                $list = array_values(array_filter(array_map(
                    static fn ($s) => strtoupper(trim($s)),
                    explode(',', $text)
                ), static fn ($s) => preg_match('/^[A-Z]{3}$/', $s) === 1));
                if ($list === []) {
                    $this->api->sendMessage($chatId, '❌ فهرست نامعتبر است.');
                    return;
                }
                Settings::set('calendar_currencies', implode(',', $list));
                $this->api->sendMessage($chatId, '✅ ارزهای تقویم: <code>' . implode(',', $list) . '</code>');
                $this->send($chatId, $this->screenSettings());
                return;

            case 'set_coins':
                $list = $this->parseCoins($text);
                if ($list === []) {
                    $this->api->sendMessage($chatId, '❌ نماد معتبری پیدا نشد.');
                    return;
                }
                Settings::set('coins', implode(',', $list));
                $this->api->sendMessage($chatId, '✅ ذخیره شد.');
                $this->send($chatId, $this->screenSettings());
                return;

            case 'set_catchup':
                $minutes = (int) Persian::enDigits($text);
                Settings::set('catchup_minutes', (string) max(0, min(180, $minutes)));
                $this->api->sendMessage($chatId, '✅ ذخیره شد.');
                $this->send($chatId, $this->screenSettings());
                return;

            case 'admin_add':
                $id = (int) Persian::enDigits($text);
                if ($id <= 0) {
                    $this->api->sendMessage($chatId, '❌ شناسه نامعتبر است.');
                    return;
                }
                Access::add($id);
                $this->api->sendMessage($chatId, '✅ مدیر افزوده شد: <code>' . $id . '</code>');
                $this->send($chatId, $this->screenAdmins());
                return;
        }

        $this->showMain($chatId);
    }

    /** @return string[] */
    private function parseCoins(string $text): array
    {
        $raw = preg_split('/[,\s]+/u', Persian::enDigits(strtoupper(trim($text)))) ?: [];
        $list = [];
        foreach ($raw as $symbol) {
            $symbol = preg_replace('/[^A-Z0-9]/', '', $symbol) ?? '';
            if ($symbol !== '' && strlen($symbol) <= 10) {
                $list[] = $symbol;
            }
        }

        return array_slice(array_values(array_unique($list)), 0, 9);
    }

    private function addTimes(int $chatId, string $jobKey, string $text): void
    {
        $job = Registry::refresh($jobKey);
        if ($job === null) {
            return;
        }
        $raw = preg_split('/[,،\s]+/u', Persian::enDigits(trim($text))) ?: [];
        $added = [];
        $bad = [];

        foreach ($raw as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            $piece = str_replace(['.', '-'], ':', $piece);
            if (preg_match('/^(\d{1,2}):?(\d{2})?$/', $piece, $m)) {
                $h = (int) $m[1];
                $i = (int) ($m[2] ?? 0);
                if ($h < 0 || $h > 23 || $i < 0 || $i > 59) {
                    $bad[] = $piece;
                    continue;
                }
                $time = sprintf('%02d:%02d', $h, $i);
                Db::exec(
                    'INSERT OR IGNORE INTO schedules(job_key, at_time, days, enabled, created_at)
                     VALUES(:j, :t, :d, 1, :c)',
                    [':j' => $jobKey, ':t' => $time, ':d' => '*', ':c' => time()]
                );
                $added[] = $time;
            } else {
                $bad[] = $piece;
            }
        }

        $lines = [];
        if ($added !== []) {
            $lines[] = '✅ زمان‌های ثبت‌شده: <code>' . implode('، ', $added) . '</code>';
        }
        if ($bad !== []) {
            $lines[] = '⚠️ نامعتبر: <code>' . htmlspecialchars(implode('، ', $bad)) . '</code>';
        }
        if ($lines === []) {
            $lines[] = '❌ زمانی ثبت نشد. قالب درست: <code>09:00</code>';
        }

        $this->api->sendMessage($chatId, implode("\n", $lines));
        $this->send($chatId, $this->screenSchedules($jobKey));
    }

    private function addChannel(int $chatId, string $raw): void
    {
        $result = Channels::add($this->api, $raw);
        $this->api->sendMessage($chatId, ($result['ok'] ? '✅ ' : '❌ ') . $result['message']);

        if ($result['ok'] && isset($result['channel']['id'])) {
            $channelId = (int) $result['channel']['id'];
            $attached = [];
            foreach (Registry::all() as $key => $job) {
                if ($job->channels() === []) {
                    $job->toggleChannel($channelId);
                    $attached[] = $job->icon() . ' ' . $job->title();
                }
            }
            if ($attached !== []) {
                $this->api->sendMessage(
                    $chatId,
                    "این کانال به‌صورت خودکار برای کارهای زیر انتخاب شد:\n• " . implode("\n• ", $attached)
                );
            }
        }

        $this->send($chatId, $this->screenChannels());
    }

    // ==================================================== خروجی‌ها

    private function showMain(int $chatId): void
    {
        $this->send($chatId, $this->screenMain());
    }

    /** @param array{text:string,kb:array} $screen */
    private function send(int $chatId, array $screen): void
    {
        $this->api->sendMessage($chatId, $screen['text'], $screen['kb']);
    }

    private function preview(int $chatId, string $jobKey): void
    {
        $job = Registry::refresh($jobKey);
        if ($job === null) {
            return;
        }
        $this->api->sendChatAction($chatId);
        $result = Dispatcher::build($jobKey);

        if (!($result['ok'] ?? false)) {
            $this->api->sendMessage($chatId, '❌ ' . ($result['message'] ?? 'خطای نامشخص'));
            return;
        }
        $this->api->sendPhoto($chatId, (string) $result['path'], (string) $result['caption']);
        $this->send($chatId, $this->screenJob($jobKey));
    }

    private function sendNow(int $chatId, string $jobKey): void
    {
        $this->api->sendChatAction($chatId);
        $result = Dispatcher::run($jobKey);
        $this->api->sendMessage($chatId, ($result['ok'] ? '✅ ' : '❌ ') . $result['message']);
        $this->send($chatId, $this->screenJob($jobKey));
    }

    private function quickSend(int $chatId, string $arg): void
    {
        $key = trim($arg);
        if (!in_array($key, Registry::keys(), true)) {
            $this->api->sendMessage(
                $chatId,
                "قالب درست: <code>/send prices</code>\nکارهای موجود: <code>" . implode('</code>, <code>', Registry::keys()) . '</code>'
            );
            return;
        }
        $this->sendNow($chatId, $key);
    }

    // ==================================================== صفحه‌ها

    /** @return array{text:string,kb:array} */
    private function screenMain(): array
    {
        $now = Settings::now();
        $lines = [
            '🤖 <b>پنل مدیریت ' . htmlspecialchars(Settings::get('brand')) . '</b>',
            '',
            '🗓 ' . $this->fa(Jalali::format($now, 'l j F Y')) . ' — ⏰ ' . $this->fa($now->format('H:i')),
            '📡 کانال‌ها: ' . $this->fa((string) count(Channels::all(true))),
            '',
        ];

        foreach (Registry::all() as $key => $job) {
            $next = Scheduler::nextRun($key, $now);
            $lines[] = sprintf(
                '%s <b>%s</b> — %s%s',
                $job->icon(),
                htmlspecialchars($job->title()),
                $job->enabled() ? 'فعال ✅' : 'خاموش ⛔️',
                $next !== null ? ' | ارسال بعدی: ' . $this->fa($next->format('H:i')) : ' | بدون زمان‌بندی'
            );
        }

        $kb = [];
        foreach (Registry::all() as $key => $job) {
            $kb[] = [$this->btn($job->icon() . ' ' . $job->title(), 'j:' . $key)];
        }
        $kb[] = [$this->btn('📡 کانال‌ها', 'ch'), $this->btn('⚙️ تنظیمات', 's')];
        $kb[] = [$this->btn('📊 وضعیت', 'st'), $this->btn('🧾 گزارش‌ها', 'lg')];
        $kb[] = [$this->btn('❓ راهنما', 'hp')];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenJob(string $key): array
    {
        $job = Registry::refresh($key);
        if ($job === null) {
            return $this->screenMain();
        }
        $theme = new Theme($job->theme());
        $channels = $job->channels();
        $schedules = $job->schedules();

        $times = [];
        foreach ($schedules as $row) {
            $times[] = ($row['enabled'] ? '' : '⏸') . $this->fa((string) $row['at_time']);
        }

        $lines = [
            $job->icon() . ' <b>' . htmlspecialchars($job->title()) . '</b>',
            '<i>' . htmlspecialchars($job->description()) . '</i>',
            '',
            'وضعیت: ' . ($job->enabled() ? 'فعال ✅' : 'خاموش ⛔️'),
            'تم: ' . htmlspecialchars($theme->label()),
            'کانال‌های مقصد: ' . ($channels === [] ? '— انتخاب نشده —' : $this->fa((string) count($channels)) . ' کانال'),
            'زمان‌های ارسال: ' . ($times === [] ? '— تنظیم نشده —' : implode('، ', $times)),
        ];

        if ($job instanceof PricesJob) {
            $lines[] = 'ارزها: <code>' . implode(', ', $job->coins()) . '</code>';
        }
        if ($job instanceof CalendarJob) {
            $shift = (int) $job->option('day_shift', 0);
            $lines[] = 'روز نمایش: ' . ($shift === 0 ? 'امروز' : ($shift === 1 ? 'فردا' : 'دیروز'));
        }

        $next = Scheduler::nextRun($key);
        if ($next !== null) {
            $lines[] = 'ارسال بعدی: ' . $this->fa(Jalali::format($next, 'l j F')) . ' ساعت ' . $this->fa($next->format('H:i'));
        }

        $kb = [
            [
                $this->btn($job->enabled() ? '⛔️ خاموش کن' : '✅ روشن کن', 'j:' . $key . ':on'),
                $this->btn('🎨 تم کارت', 'j:' . $key . ':th'),
            ],
            [
                $this->btn('📡 کانال‌های مقصد', 'j:' . $key . ':ch'),
                $this->btn('⏰ زمان‌بندی', 'j:' . $key . ':sc'),
            ],
            [
                $this->btn('📝 کپشن', 'j:' . $key . ':cap'),
                $this->btn('🔧 تنظیمات کارت', 'j:' . $key . ':opt'),
            ],
            [
                $this->btn('👁 پیش‌نمایش', 'j:' . $key . ':pv'),
                $this->btn('🚀 ارسال فوری', 'j:' . $key . ':go'),
            ],
            [$this->btn('⬅️ بازگشت', 'm')],
        ];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenThemes(string $key): array
    {
        $job = Registry::refresh($key);
        $current = $job?->theme() ?? 'aurora';

        $kb = [];
        foreach (Theme::options() as $name => $label) {
            $kb[] = [$this->btn(($name === $current ? '✅ ' : '') . $label, 'j:' . $key . ':th:' . $name)];
        }
        $kb[] = [$this->btn('⬅️ بازگشت', 'j:' . $key)];

        return [
            'text' => "🎨 <b>تم کارت</b>\n\nرنگ‌بندی قاب و نمودارها را انتخاب کنید.\nبرای دیدن نتیجه از «پیش‌نمایش» استفاده کنید.",
            'kb'   => $kb,
        ];
    }

    private function screenJobChannels(string $key): array
    {
        $job = Registry::refresh($key);
        $channels = Channels::all();

        if ($channels === []) {
            return [
                'text' => "📡 هنوز کانالی ثبت نشده است.\n\nابتدا از منوی «کانال‌ها» یک کانال اضافه کنید.",
                'kb'   => [[$this->btn('➕ افزودن کانال', 'ch:add')], [$this->btn('⬅️ بازگشت', 'j:' . $key)]],
            ];
        }

        $kb = [];
        foreach ($channels as $channel) {
            $on = $job !== null && $job->hasChannel((int) $channel['id']);
            $kb[] = [$this->btn(
                ($on ? '✅ ' : '⬜️ ') . Channels::label($channel) . ((int) $channel['active'] === 0 ? ' ⚠️' : ''),
                'j:' . $key . ':ch:' . $channel['id']
            )];
        }
        $kb[] = [$this->btn('⬅️ بازگشت', 'j:' . $key)];

        return [
            'text' => "📡 <b>کانال‌های مقصد</b>\n\nهر کانالی که می‌خواهید این کارت در آن منتشر شود را انتخاب کنید.\n⚠️ = ربات در کانال ادمین نیست.",
            'kb'   => $kb,
        ];
    }

    private function screenSchedules(string $key): array
    {
        $job = Registry::refresh($key);
        if ($job === null) {
            return $this->screenMain();
        }
        $rows = $job->schedules();

        $lines = [
            '⏰ <b>زمان‌بندی — ' . htmlspecialchars($job->title()) . '</b>',
            'منطقه‌ی زمانی: <code>' . htmlspecialchars(Settings::get('timezone')) . '</code>',
            '',
        ];
        if ($rows === []) {
            $lines[] = 'هنوز زمانی ثبت نشده است. با دکمه‌ی زیر اضافه کنید.';
        } else {
            foreach ($rows as $i => $row) {
                $lines[] = sprintf(
                    '%s <b>%s</b> — %s %s',
                    $this->fa((string) ($i + 1)) . ')',
                    $this->fa((string) $row['at_time']),
                    $this->daysLabel((string) $row['days']),
                    (int) $row['enabled'] === 1 ? '✅' : '⏸'
                );
            }
        }

        $kb = [[$this->btn('➕ افزودن زمان', 'j:' . $key . ':sc:add')]];
        foreach ($rows as $row) {
            $kb[] = [
                $this->btn('🗑 ' . $this->fa((string) $row['at_time']), 'sc:d:' . $row['id']),
                $this->btn((int) $row['enabled'] === 1 ? '⏸ توقف' : '▶️ فعال', 'sc:t:' . $row['id']),
                $this->btn('📆 روزها', 'sc:days:' . $row['id']),
            ];
        }
        $kb[] = [$this->btn('⬅️ بازگشت', 'j:' . $key)];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenDays(int $scheduleId): array
    {
        $row = Db::one('SELECT * FROM schedules WHERE id = :i', [':i' => $scheduleId]);
        if ($row === null) {
            return $this->screenMain();
        }
        $days = (string) $row['days'];
        $list = $days === '*' ? array_keys(self::DAYS) : array_map('intval', array_filter(explode(',', $days), 'strlen'));

        $kb = [];
        $buffer = [];
        foreach (self::DAYS as $num => $label) {
            $buffer[] = $this->btn((in_array($num, $list, true) ? '✅ ' : '⬜️ ') . $label, 'sc:day:' . $scheduleId . ':' . $num);
            if (count($buffer) === 2) {
                $kb[] = $buffer;
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            $kb[] = $buffer;
        }
        $kb[] = [$this->btn('📅 همه‌ی روزها', 'sc:all:' . $scheduleId)];
        $kb[] = [$this->btn('⬅️ بازگشت', 'j:' . $row['job_key'] . ':sc')];

        return [
            'text' => '📆 <b>روزهای ارسال ساعت ' . $this->fa((string) $row['at_time']) . "</b>\n\n"
                . 'وضعیت فعلی: ' . $this->daysLabel($days),
            'kb'   => $kb,
        ];
    }

    private function screenJobOptions(string $key): array
    {
        $job = Registry::refresh($key);
        if ($job === null) {
            return $this->screenMain();
        }

        $lines = ['🔧 <b>تنظیمات کارت — ' . htmlspecialchars($job->title()) . '</b>', ''];
        $kb = [];

        $lines[] = 'عنوان کارت: <code>' . htmlspecialchars((string) $job->option('headline', '—')) . '</code>';
        $kb[] = [$this->btn('✏️ عنوان کارت', 'j:' . $key . ':head')];

        if ($job instanceof PricesJob) {
            $lines[] = 'ارزها: <code>' . implode(', ', $job->coins()) . '</code>';
            $lines[] = 'نمودار کوچک: ' . ($job->option('sparkline', true) ? 'روشن ✅' : 'خاموش ⛔️');
            $kb[] = [$this->btn('🪙 انتخاب ارزها', 'j:' . $key . ':coins')];
            $kb[] = [$this->btn('📉 نمودار کوچک: ' . ($job->option('sparkline', true) ? 'روشن' : 'خاموش'), 'j:' . $key . ':spark')];
        }

        if ($job instanceof CalendarJob) {
            $shift = (int) $job->option('day_shift', 0);
            $lines[] = 'روز نمایش: ' . ($shift === 0 ? 'امروز' : ($shift === 1 ? 'فردا' : 'دیروز'));
            $lines[] = 'حداقل اهمیت: ' . $this->fa((string) Settings::int('calendar_min_impact', 2)) . ' (از ۳)';
            $kb[] = [
                $this->btn(($shift === 0 ? '✅ ' : '') . 'امروز', 'j:' . $key . ':shift:0'),
                $this->btn(($shift === 1 ? '✅ ' : '') . 'فردا', 'j:' . $key . ':shift:1'),
            ];
            $kb[] = [$this->btn('🎯 حداقل اهمیت رویدادها', 's:imp')];
        }

        $kb[] = [$this->btn('⬅️ بازگشت', 'j:' . $key)];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenChannels(): array
    {
        $channels = Channels::all();
        $lines = ['📡 <b>کانال‌های مقصد</b>', ''];

        if ($channels === []) {
            $lines[] = 'هنوز کانالی ثبت نشده است.';
            $lines[] = '';
            $lines[] = 'برای افزودن، ربات را در کانال ادمین کنید و سپس دکمه‌ی زیر را بزنید.';
        } else {
            foreach ($channels as $i => $channel) {
                $jobs = [];
                foreach (Registry::all() as $key => $job) {
                    if ($job->hasChannel((int) $channel['id'])) {
                        $jobs[] = $job->icon();
                    }
                }
                $lines[] = sprintf(
                    '%s %s%s — <code>%s</code> %s',
                    $this->fa((string) ($i + 1)) . ')',
                    htmlspecialchars(Channels::label($channel)),
                    (int) $channel['active'] === 1 ? '' : ' ⚠️',
                    $channel['chat_id'],
                    $jobs === [] ? '' : '| ' . implode(' ', $jobs)
                );
            }
        }

        $kb = [[$this->btn('➕ افزودن کانال', 'ch:add'), $this->btn('🔄 بررسی دسترسی', 'ch:chk')]];
        foreach ($channels as $channel) {
            $kb[] = [$this->btn('🗑 حذف ' . Channels::label($channel), 'ch:del:' . $channel['id'])];
        }
        $kb[] = [$this->btn('⬅️ بازگشت', 'm')];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenSettings(): array
    {
        $lines = [
            '⚙️ <b>تنظیمات کلی</b>',
            '',
            '🌍 منطقه‌ی زمانی: <code>' . htmlspecialchars(Settings::get('timezone')) . '</code>',
            '🏷 نام روی کارت: <code>' . htmlspecialchars(Settings::get('brand')) . '</code>',
            '🔗 آیدی کانال: <code>' . htmlspecialchars(Settings::get('brand_link') ?: '—') . '</code>',
            '🔢 ارقام متن: ' . (Settings::get('digits') === 'fa' ? 'فارسی ۱۲۳' : 'لاتین 123'),
            '💵 ارقام داده: ' . (Settings::get('digits_data') === 'fa' ? 'فارسی ۱۲۳' : 'لاتین 123'),
            '🖼 کیفیت تصویر: ' . (Settings::get('quality') === 'high' ? 'بالا' : 'معمولی'),
            '📤 حالت ارسال: ' . (Settings::get('send_mode') === 'photo' ? 'عکس' : 'فایل'),
            '💹 منبع قیمت: <code>' . Settings::get('price_source') . '</code>',
            '📅 منبع تقویم: <code>' . Settings::get('calendar_source') . '</code>',
            '🎯 حداقل اهمیت خبر: ' . $this->fa((string) Settings::int('calendar_min_impact', 2)),
            '💱 ارزهای تقویم: <code>' . htmlspecialchars(Settings::get('calendar_currencies')) . '</code>',
            '🪙 ارزهای پیش‌فرض: <code>' . htmlspecialchars(Settings::get('coins')) . '</code>',
            '⏱ جبران تأخیر: ' . $this->fa((string) Settings::int('catchup_minutes', 10)) . ' دقیقه',
        ];

        $kb = [
            [$this->btn('🌍 منطقه‌ی زمانی', 's:tz'), $this->btn('🏷 نام کانال', 's:brand')],
            [$this->btn('🔗 آیدی کانال', 's:link'), $this->btn('🪙 ارزهای پیش‌فرض', 's:coins')],
            [$this->btn('🔢 ارقام متن', 's:digits'), $this->btn('💵 ارقام داده', 's:ddata')],
            [$this->btn('🖼 کیفیت تصویر', 's:q'), $this->btn('📤 حالت ارسال', 's:mode')],
            [$this->btn('💹 منبع قیمت', 's:psrc'), $this->btn('📅 منبع تقویم', 's:csrc')],
            [$this->btn('🎯 حداقل اهمیت', 's:imp'), $this->btn('💱 ارزهای تقویم', 's:cur')],
            [$this->btn('⏱ جبران تأخیر', 's:catch'), $this->btn('👤 مدیران', 's:adm')],
            [$this->btn('⬅️ بازگشت', 'm')],
        ];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenAdmins(): array
    {
        $admins = Access::list();
        $lines = ['👤 <b>مدیران ربات</b>', ''];
        foreach ($admins as $i => $admin) {
            $lines[] = sprintf(
                '%s <code>%s</code>%s',
                $this->fa((string) ($i + 1)) . ')',
                $admin['user_id'],
                isset($admin['fixed']) ? ' — ثابت (config)' : ''
            );
        }

        $kb = [[$this->btn('➕ افزودن مدیر', 's:adm:add')]];
        foreach ($admins as $admin) {
            if (isset($admin['fixed'])) {
                continue;
            }
            $kb[] = [$this->btn('🗑 حذف ' . $admin['user_id'], 's:adm:del:' . $admin['user_id'])];
        }
        $kb[] = [$this->btn('⬅️ بازگشت', 's')];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenStatus(): array
    {
        $now = Settings::now();
        $lines = [
            '📊 <b>وضعیت سامانه</b>',
            '',
            '🕒 اکنون: ' . $this->fa(Jalali::format($now, 'l j F Y')) . ' — ' . $this->fa($now->format('H:i:s')),
            '🌍 منطقه‌ی زمانی: <code>' . htmlspecialchars(Settings::get('timezone')) . '</code>',
            '',
        ];

        foreach (Registry::all() as $key => $job) {
            $last = Db::one(
                'SELECT * FROM runs WHERE job_key = :k ORDER BY created_at DESC LIMIT 1',
                [':k' => $key]
            );
            $next = Scheduler::nextRun($key, $now);
            $lines[] = $job->icon() . ' <b>' . htmlspecialchars($job->title()) . '</b>';
            $lines[] = '   وضعیت: ' . ($job->enabled() ? 'فعال ✅' : 'خاموش ⛔️')
                . ' | کانال: ' . $this->fa((string) count($job->channels()));
            $lines[] = '   ارسال بعدی: ' . ($next !== null
                ? $this->fa($next->format('Y-m-d H:i'))
                : '—');
            if ($last !== null) {
                $lines[] = '   آخرین اجرا: ' . $this->fa(date('Y-m-d H:i', (int) $last['created_at']))
                    . ' (' . $last['status'] . ')';
                if ((string) $last['detail'] !== '') {
                    $lines[] = '   <i>' . htmlspecialchars(mb_substr((string) $last['detail'], 0, 120)) . '</i>';
                }
            }
            $lines[] = '';
        }

        $heartbeat = Settings::get('scheduler_heartbeat', '');
        $lines[] = '⚙️ زمان‌بند: ' . ($heartbeat !== ''
            ? 'آخرین تیک ' . $this->fa(date('H:i:s', (int) $heartbeat))
                . ((time() - (int) $heartbeat) < 180 ? ' — فعال ✅' : ' — متوقف؟ ⚠️')
            : 'هنوز اجرا نشده ⚠️');

        return ['text' => implode("\n", $lines), 'kb' => [[$this->btn('🔄 به‌روزرسانی', 'st')], [$this->btn('⬅️ بازگشت', 'm')]]];
    }

    private function screenLogs(): array
    {
        $rows = Db::all('SELECT * FROM runs ORDER BY created_at DESC LIMIT 12');
        $lines = ['🧾 <b>آخرین اجراها</b>', ''];

        if ($rows === []) {
            $lines[] = 'هنوز اجرایی ثبت نشده است.';
        }
        foreach ($rows as $row) {
            $icon = match ((string) $row['status']) {
                'ok'      => '✅',
                'partial' => '⚠️',
                'running' => '⏳',
                default   => '❌',
            };
            $job = Registry::get((string) $row['job_key']);
            $lines[] = sprintf(
                '%s <b>%s</b> — %s',
                $icon,
                htmlspecialchars($job?->title() ?? (string) $row['job_key']),
                $this->fa(date('m-d H:i', (int) $row['created_at']))
            );
            if ((string) $row['detail'] !== '') {
                $lines[] = '   <i>' . htmlspecialchars(mb_substr((string) $row['detail'], 0, 110)) . '</i>';
            }
        }

        $errors = Db::all("SELECT * FROM logs WHERE level IN ('error','warn') ORDER BY created_at DESC LIMIT 5");
        if ($errors !== []) {
            $lines[] = '';
            $lines[] = '⚠️ <b>آخرین خطاها</b>';
            foreach ($errors as $row) {
                $lines[] = '• ' . $this->fa(date('m-d H:i', (int) $row['created_at'])) . ' — '
                    . htmlspecialchars(mb_substr((string) $row['message'], 0, 90));
            }
        }

        return ['text' => implode("\n", $lines), 'kb' => [[$this->btn('🔄 به‌روزرسانی', 'lg')], [$this->btn('⬅️ بازگشت', 'm')]]];
    }

    private function screenHelp(): array
    {
        $text = "❓ <b>راهنمای سریع</b>\n\n"
            . "<b>۱) کانال مقصد</b>\nربات را در کانال ادمین کنید، سپس از «📡 کانال‌ها ← ➕ افزودن کانال» "
            . "یک پست از کانال را فوروارد کنید یا آیدی کانال را بفرستید.\n\n"
            . "<b>۲) زمان ارسال</b>\nوارد هر کارت شوید ← «⏰ زمان‌بندی» ← «➕ افزودن زمان» و ساعت را بفرستید "
            . "(مثل <code>09:00</code> یا چند ساعت با کاما). سپس در صورت نیاز روزهای هفته را مشخص کنید.\n\n"
            . "<b>۳) روشن‌کردن</b>\nهر کارت را با دکمه‌ی «✅ روشن کن» فعال کنید تا زمان‌بند آن را ارسال کند.\n\n"
            . "<b>۴) پیش‌نمایش</b>\nبا «👁 پیش‌نمایش» کارت فقط برای شما ساخته و ارسال می‌شود.\n\n"
            . "<b>دستورها</b>\n<code>/panel</code> پنل مدیریت\n<code>/status</code> وضعیت\n"
            . "<code>/send prices</code> ارسال فوری یک کارت\n<code>/id</code> شناسه‌ی عددی شما\n\n"
            . "⚠️ زمان‌بند باید روی سرور در حال اجرا باشد:\n<code>php scheduler.php</code>";

        return ['text' => $text, 'kb' => [[$this->btn('⬅️ بازگشت', 'm')]]];
    }

    // ==================================================== کمکی‌ها

    private function btn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    private function daysLabel(string $days): string
    {
        $days = trim($days);
        if ($days === '' || $days === '*') {
            return 'همه‌ی روزها';
        }
        $list = array_map('intval', array_filter(explode(',', $days), 'strlen'));
        $names = [];
        foreach (self::DAYS as $num => $label) {
            if (in_array($num, $list, true)) {
                $names[] = $label;
            }
        }

        return $names === [] ? 'هیچ روزی' : implode('، ', $names);
    }

    private function fa(string $text): string
    {
        return Settings::get('digits') === 'fa' ? Persian::faDigits($text) : $text;
    }
}

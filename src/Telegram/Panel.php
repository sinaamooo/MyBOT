<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use Nikto\Core\Db;
use Nikto\Core\Jalali;
use Nikto\Core\Settings;
use Nikto\Jobs\CalendarJob;
use Nikto\Jobs\Dispatcher;
use Nikto\Jobs\LiquidityJob;
use Nikto\Jobs\MoversJob;
use Nikto\Jobs\PricesJob;
use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Render\Theme;
use Nikto\Text\Persian;

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
        $messageId = (int) ($msg['message_id'] ?? 0);
        $text = trim((string) ($msg['text'] ?? ''));

        if (($msg['chat']['type'] ?? '') !== 'private') {
            return;
        }

        if (!Access::isAdmin($userId)) {
            if (str_starts_with($text, '/start') || str_starts_with($text, '/id')) {
                $this->api->sendMessage(
                    $chatId,
                    'این ربات اختصاصی کانال ' . htmlspecialchars(Settings::get('brand')) . " است.\n\n"
                    . 'شناسه عددی شما: <code>' . $userId . '</code>'
                );
            }
            return;
        }

        $state = State::get($userId);
        if ($state['action'] !== '' && !str_starts_with($text, '/')) {
            $this->handleStateInput($chatId, $userId, $messageId, $state, $msg);
            return;
        }

        if ($state['action'] !== '' && str_starts_with($text, '/')) {
            State::clear($userId);
            if (str_starts_with($text, '/cancel')) {
                $this->api->deleteMessage($chatId, $messageId);
                $this->render($chatId, $userId, $this->screenMain(), 'لغو شد.');
                return;
            }
        }

        $forwardChat = $msg['forward_origin']['chat'] ?? ($msg['forward_from_chat'] ?? null);
        if (is_array($forwardChat) && isset($forwardChat['id'])) {
            $this->api->deleteMessage($chatId, $messageId);
            $result = Channels::add($this->api, (string) $forwardChat['id']);
            $this->autoAssign($result);
            $this->render($chatId, $userId, $this->screenChannels(), $result['message']);
            return;
        }

        match (true) {
            str_starts_with($text, '/id') => $this->api->sendMessage(
                $chatId,
                'شناسه عددی شما: <code>' . $userId . '</code>'
            ),
            str_starts_with($text, '/status') => $this->fresh($chatId, $userId, $this->screenStatus()),
            str_starts_with($text, '/help')   => $this->fresh($chatId, $userId, $this->screenHelp()),
            str_starts_with($text, '/send')   => $this->quickSend($chatId, $userId, trim(substr($text, 5))),
            default => $this->fresh($chatId, $userId, $this->screenMain()),
        };

        if (str_starts_with($text, '/start') || str_starts_with($text, '/panel')) {
            $this->api->deleteMessage($chatId, $messageId);
        }
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

        Anchor::set($userId, $chatId, $messageId);

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

    private function route(array $p, int $chatId, int $userId, string $cbId, string &$toast): ?array
    {
        $head = $p[0] ?? 'm';

        switch ($head) {
            case 'm':
                State::clear($userId);
                return $this->screenMain();

            case 'nop':
                return null;

            case 'j':
                return $this->routeJob($p, $chatId, $userId, $cbId, $toast);

            case 'sc':
                return $this->routeSchedule($p, $toast);

            case 'ch':
                $sub = $p[1] ?? '';
                if ($sub === 'add') {
                    State::set($userId, 'chan_add');
                    $toast = 'منتظر ورودی';
                    return $this->screenPrompt(
                        'افزودن کانال مقصد',
                        "یکی از این سه کار را انجام دهید:\n\n"
                        . "۱) یک پست از کانال را به همین چت فوروارد کنید\n"
                        . "۲) آیدی کانال را بفرستید؛ مثل <code>@my_channel</code>\n"
                        . "۳) شناسه عددی کانال؛ مثل <code>-1001234567890</code>\n\n"
                        . 'توجه: ربات باید در آن کانال ادمین باشد.',
                        'ch'
                    );
                }
                if ($sub === 'del') {
                    Channels::remove((int) ($p[2] ?? 0));
                    $toast = 'حذف شد';
                    return $this->screenChannels();
                }
                if ($sub === 'chk') {
                    $report = Channels::check($this->api);
                    $ok = count(array_filter($report, static fn ($r) => $r['ok']));
                    $toast = sprintf('%d از %d کانال سالم', $ok, count($report));
                    return $this->screenChannels();
                }
                return $this->screenChannels();

            case 's':
                return $this->routeSettings($p, $userId, $toast);

            case 'st':
                return $this->screenStatus();

            case 'lg':
                return $this->screenLogs();

            case 'hp':
                return $this->screenHelp();
        }

        return $this->screenMain();
    }

    private function routeJob(array $p, int $chatId, int $userId, string $cbId, string &$toast): ?array
    {
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
                $toast = $job->enabled() ? 'فعال شد' : 'غیرفعال شد';
                return $this->screenJob($key);

            case 'th':
                if (isset($p[3])) {
                    if (!isset(Theme::PALETTES[$p[3]])) {
                        $toast = 'تم نامعتبر';
                        return $this->screenThemes($key);
                    }
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
                    $toast = 'منتظر ورودی';
                    return $this->screenPrompt(
                        'افزودن زمان ارسال',
                        'ساعت مورد نظر را به وقت <b>' . htmlspecialchars(Settings::get('timezone')) . "</b> بفرستید.\n\n"
                        . "می‌توانید چند زمان را با کاما جدا کنید؛ مثال:\n<code>09:00, 15:30, 21:45</code>",
                        'j:' . $key . ':sc'
                    );
                }
                return $this->screenSchedules($key);

            case 'cap':
                State::set($userId, 'caption', $key);
                $toast = 'منتظر ورودی';
                return $this->screenPrompt(
                    'ویرایش کپشن',
                    "متن دلخواه را بفرستید. می‌توانید از قالب‌بندی خود تلگرام استفاده کنید:\n"
                    . "بولد، ایتالیک، زیرخط، نقل‌قول، کد و لینک — همه حفظ می‌شوند.\n\n"
                    . "متغیرهای مجاز:\n"
                    . "<code>{summary}</code> خلاصه داده‌ها\n"
                    . "<code>{date}</code> تاریخ شمسی\n"
                    . "<code>{time}</code> ساعت\n"
                    . "<code>{brand}</code> نام کانال\n"
                    . "<code>{link}</code> امضای کانال\n\n"
                    . $this->captionPreview($job->caption())
                    . 'برای خالی‌کردن کپشن کلمه <code>خالی</code> را بفرستید.',
                    'j:' . $key
                );

            case 'opt':
                return $this->screenJobOptions($key);

            case 'coins':
                State::set($userId, 'coins', $key);
                $toast = 'منتظر ورودی';
                return $this->screenPrompt(
                    'انتخاب ارزها',
                    "نماد ارزها را با کاما بفرستید (حداکثر ۹ ارز).\n\nمثال:\n<code>BTC,ETH,XRP,BNB,SOL,TRX</code>",
                    'j:' . $key . ':opt'
                );

            case 'head':
                State::set($userId, 'headline', $key);
                $toast = 'منتظر ورودی';
                return $this->screenPrompt(
                    'عنوان کارت',
                    "عنوانی که بالای کارت نوشته می‌شود را بفرستید.\n\nعنوان فعلی:\n<code>"
                    . htmlspecialchars((string) $job->option('headline', '')) . '</code>',
                    'j:' . $key . ':opt'
                );

            case 'shift':
                $job->setOption('day_shift', (int) ($p[3] ?? 0));
                $toast = 'تغییر کرد';
                return $this->screenJobOptions($key);

            case 'spark':
                $job->setOption('sparkline', !$job->option('sparkline', true));
                $toast = 'تغییر کرد';
                return $this->screenJobOptions($key);

            case 'cnt':
                $n = (int) ($p[3] ?? 0);
                if ($job instanceof MoversJob && in_array($n, MoversJob::COUNTS, true)) {
                    $job->setOption('count', $n);
                    $toast = 'تغییر کرد';
                }
                return $this->screenJobOptions($key);

            case 'vol':
                $v = (int) ($p[3] ?? 0);
                if ($job instanceof MoversJob && in_array($v, MoversJob::VOLUMES, true)) {
                    $job->setOption('min_volume', $v);
                    $toast = 'تغییر کرد';
                }
                return $this->screenJobOptions($key);

            case 'lvl':
                $n = (int) ($p[3] ?? 0);
                if ($job instanceof LiquidityJob && in_array($n, LiquidityJob::LEVELS, true)) {
                    $job->setOption('levels', $n);
                    $toast = 'تغییر کرد';
                }
                return $this->screenJobOptions($key);

            case 'pv':
                $this->api->answerCallback($cbId, 'در حال ساخت پیش‌نمایش…');
                $this->answered = true;
                $this->preview($chatId, $userId, $key);
                return null;

            case 'go':
                $this->api->answerCallback($cbId, 'در حال ارسال…');
                $this->answered = true;
                $result = Dispatcher::run($key);
                $this->render($chatId, $userId, $this->screenJob($key), $result['message']);
                return null;
        }

        return $this->screenJob($key);
    }

    private function routeSchedule(array $p, string &$toast): ?array
    {
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
                $toast = 'حذف شد';
                return $this->screenSchedules($jobKey);

            case 't':
                Db::exec('UPDATE schedules SET enabled = 1 - enabled WHERE id = :i', [':i' => $id]);
                $toast = 'تغییر وضعیت';
                return $this->screenSchedules($jobKey);

            case 'days':
                return $this->screenDays($id);

            case 'all':
                Db::exec('UPDATE schedules SET days = :d WHERE id = :i', [':d' => '*', ':i' => $id]);
                $toast = 'همه روزها';
                return $this->screenDays($id);

            case 'day':
                $day = (int) ($p[3] ?? 0);
                $current = (string) $row['days'];
                $list = $current === '*'
                    ? array_keys(self::DAYS)
                    : array_map('intval', array_filter(explode(',', $current), 'strlen'));
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
    }

    private function routeSettings(array $p, int $userId, string &$toast): ?array
    {
        $sub = $p[1] ?? '';

        $ask = function (string $action, string $title, string $body) use ($userId, &$toast): array {
            State::set($userId, $action);
            $toast = 'منتظر ورودی';
            return $this->screenPrompt($title, $body, 's');
        };

        switch ($sub) {
            case '':
                return $this->screenSettings();

            case 'tz':
                return $ask('set_timezone', 'منطقه زمانی', "نام منطقه زمانی را بفرستید؛ مثال:\n<code>Asia/Tehran</code>\n<code>Europe/Istanbul</code>\n<code>UTC</code>");

            case 'brand':
                return $ask('set_brand', 'نام نمایشی ربات', 'این نام فقط در پنل و پیام‌ها دیده می‌شود و روی کارت‌ها چاپ نمی‌شود.');

            case 'link':
                return $ask('set_link', 'امضای زیر کارت', "متنی که پایین کارت و در کپشن می‌آید؛ مثال:\n<code>@nikto_crypto</code>\n\nبرای حذف، کلمه <code>خالی</code> را بفرستید.");

            case 'cur':
                return $ask('set_currencies', 'ارزهای تقویم اقتصادی', "کدهای ارز را با کاما بفرستید؛ مثال:\n<code>USD,EUR,GBP,JPY,CAD</code>");

            case 'catch':
                return $ask('set_catchup', 'جبران تأخیر', "حداکثر تأخیر مجاز برای ارسال (به دقیقه) را بفرستید؛ مثلاً <code>10</code>");

            case 'coins':
                return $ask('set_coins', 'ارزهای پیش‌فرض', "نمادها را با کاما بفرستید؛ مثال:\n<code>BTC,ETH,XRP,BNB,SOL,TRX</code>");

            case 'digits':
                Settings::set('digits', Settings::get('digits') === 'fa' ? 'en' : 'fa');
                $toast = 'تغییر کرد';
                return $this->screenSettings();

            case 'ddata':
                Settings::set('digits_data', Settings::get('digits_data') === 'fa' ? 'en' : 'fa');
                $toast = 'تغییر کرد';
                return $this->screenSettings();

            case 'q':
                $order = ['normal' => 'high', 'high' => 'ultra', 'ultra' => 'normal'];
                Settings::set('quality', $order[Settings::get('quality')] ?? 'high');
                $toast = 'کیفیت: ' . $this->qualityLabel();
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
                    State::set($userId, 'admin_add');
                    $toast = 'منتظر ورودی';
                    return $this->screenPrompt(
                        'افزودن مدیر',
                        "شناسه عددی کاربر را بفرستید؛ مثال:\n<code>123456789</code>\n\n"
                        . 'کاربر می‌تواند با دستور /id شناسه‌اش را ببیند.',
                        's:adm'
                    );
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

    private function handleStateInput(int $chatId, int $userId, int $messageId, array $state, array $msg): void
    {
        $text = trim((string) ($msg['text'] ?? ''));
        $action = $state['action'];
        $payload = $state['payload'];

        $this->api->deleteMessage($chatId, $messageId);

        if ($text === '/cancel' || $text === 'لغو') {
            State::clear($userId);
            $this->render($chatId, $userId, $this->screenMain(), 'لغو شد.');
            return;
        }

        $forwardChat = $msg['forward_origin']['chat'] ?? ($msg['forward_from_chat'] ?? null);
        if ($action === 'chan_add' && is_array($forwardChat) && isset($forwardChat['id'])) {
            State::clear($userId);
            $result = Channels::add($this->api, (string) $forwardChat['id']);
            $this->autoAssign($result);
            $this->render($chatId, $userId, $this->screenChannels(), $result['message']);
            return;
        }

        State::clear($userId);

        switch ($action) {
            case 'chan_add':
                $result = Channels::add($this->api, $text);
                $this->autoAssign($result);
                $this->render($chatId, $userId, $this->screenChannels(), $result['message']);
                return;

            case 'time_add':
                $notice = $this->addTimes($payload, $text);
                $this->render($chatId, $userId, $this->screenSchedules($payload), $notice);
                return;

            case 'caption':
                $job = Registry::refresh($payload);
                if ($job !== null) {
                    $formatted = $text === 'خالی'
                        ? ''
                        : (Entities::looksLikeHtml($text)
                            ? $text
                            : Entities::toHtml($text, (array) ($msg['entities'] ?? [])));
                    $job->setCaption($formatted);
                }
                $this->render($chatId, $userId, $this->screenJob($payload), 'کپشن ذخیره شد.');
                return;

            case 'coins':
                $list = $this->parseCoins($text);
                if ($list === []) {
                    $this->render($chatId, $userId, $this->screenJobOptions($payload), 'نماد معتبری پیدا نشد.');
                    return;
                }
                Registry::refresh($payload)?->setOption('coins', implode(',', $list));
                $this->render($chatId, $userId, $this->screenJobOptions($payload), 'ارزها ذخیره شد: ' . implode(', ', $list));
                return;

            case 'headline':
                Registry::refresh($payload)?->setOption('headline', mb_substr($text, 0, 60));
                $this->render($chatId, $userId, $this->screenJobOptions($payload), 'عنوان ذخیره شد.');
                return;

            case 'set_timezone':
                try {
                    new \DateTimeZone($text);
                } catch (\Throwable) {
                    $this->render($chatId, $userId, $this->screenSettings(), 'منطقه زمانی نامعتبر است.');
                    return;
                }
                Settings::set('timezone', $text);
                $this->render($chatId, $userId, $this->screenSettings(), 'منطقه زمانی ذخیره شد.');
                return;

            case 'set_brand':
                Settings::set('brand', mb_substr($text, 0, 40));
                $this->render($chatId, $userId, $this->screenSettings(), 'ذخیره شد.');
                return;

            case 'set_link':
                Settings::set('brand_link', $text === 'خالی' ? '' : mb_substr($text, 0, 60));
                $this->render($chatId, $userId, $this->screenSettings(), 'ذخیره شد.');
                return;

            case 'set_currencies':
                $list = array_values(array_filter(
                    array_map(static fn ($s) => strtoupper(trim($s)), explode(',', $text)),
                    static fn ($s) => preg_match('/^[A-Z]{3}$/', $s) === 1
                ));
                if ($list === []) {
                    $this->render($chatId, $userId, $this->screenSettings(), 'فهرست نامعتبر است.');
                    return;
                }
                Settings::set('calendar_currencies', implode(',', $list));
                $this->render($chatId, $userId, $this->screenSettings(), 'ذخیره شد.');
                return;

            case 'set_coins':
                $list = $this->parseCoins($text);
                if ($list === []) {
                    $this->render($chatId, $userId, $this->screenSettings(), 'نماد معتبری پیدا نشد.');
                    return;
                }
                Settings::set('coins', implode(',', $list));
                $this->render($chatId, $userId, $this->screenSettings(), 'ذخیره شد.');
                return;

            case 'set_catchup':
                $minutes = (int) Persian::enDigits($text);
                Settings::set('catchup_minutes', (string) max(0, min(180, $minutes)));
                $this->render($chatId, $userId, $this->screenSettings(), 'ذخیره شد.');
                return;

            case 'admin_add':
                $id = (int) Persian::enDigits($text);
                if ($id <= 0) {
                    $this->render($chatId, $userId, $this->screenAdmins(), 'شناسه نامعتبر است.');
                    return;
                }
                Access::add($id);
                $this->render($chatId, $userId, $this->screenAdmins(), 'مدیر افزوده شد: ' . $id);
                return;
        }

        $this->render($chatId, $userId, $this->screenMain());
    }

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

    private function addTimes(string $jobKey, string $text): string
    {
        if (Registry::refresh($jobKey) === null) {
            return 'کار نامعتبر';
        }
        $raw = preg_split('/[,،\s]+/u', Persian::enDigits(trim($text))) ?: [];
        $added = [];
        $bad = [];

        foreach ($raw as $piece) {
            $piece = trim(str_replace(['.', '-'], ':', $piece));
            if ($piece === '') {
                continue;
            }
            if (preg_match('/^(\d{1,2}):?(\d{2})?$/', $piece, $m)) {
                $h = (int) $m[1];
                $i = (int) ($m[2] ?? 0);
                if ($h > 23 || $i > 59) {
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

        $parts = [];
        if ($added !== []) {
            $parts[] = 'ثبت شد: ' . implode('، ', $added);
        }
        if ($bad !== []) {
            $parts[] = 'نامعتبر: ' . implode('، ', $bad);
        }

        return $parts === [] ? 'زمانی ثبت نشد. قالب درست: 09:00' : implode(' | ', $parts);
    }

    private function autoAssign(array $result): void
    {
        if (!($result['ok'] ?? false) || !isset($result['channel']['id'])) {
            return;
        }
        $channelId = (int) $result['channel']['id'];
        foreach (Registry::all() as $job) {
            if ($job->channels() === []) {
                $job->toggleChannel($channelId);
            }
        }
    }

    private function render(int $chatId, int $userId, array $screen, string $notice = ''): void
    {
        $text = $screen['text'];
        if (trim($notice) !== '') {
            $text = '<b>' . strip_tags($notice) . "</b>\n\n" . $text;
        }

        $anchor = Anchor::get($userId);
        if ($anchor !== null && (string) $chatId === $anchor['chat_id']) {
            $res = $this->api->editMessage($chatId, $anchor['message_id'], $text, $screen['kb']);
            if ($res['ok'] ?? false) {
                return;
            }
        }
        $this->fresh($chatId, $userId, ['text' => $text, 'kb' => $screen['kb']]);
    }

    private function fresh(int $chatId, int $userId, array $screen): void
    {
        $res = $this->api->sendMessage($chatId, $screen['text'], $screen['kb']);
        $messageId = (int) ($res['result']['message_id'] ?? 0);
        if ($messageId > 0) {
            Anchor::set($userId, $chatId, $messageId);
        }
    }

    private function preview(int $chatId, int $userId, string $jobKey): void
    {
        $this->api->sendChatAction($chatId);
        $result = Dispatcher::build($jobKey);

        if (!($result['ok'] ?? false)) {
            $this->render($chatId, $userId, $this->screenJob($jobKey), (string) ($result['message'] ?? 'خطای نامشخص'));
            return;
        }

        $anchor = Anchor::get($userId);
        if ($anchor !== null && $anchor['preview_message_id'] > 0) {
            $this->api->deleteMessage($chatId, $anchor['preview_message_id']);
        }

        $sent = $this->api->sendPhoto($chatId, (string) $result['path'], (string) $result['caption']);
        $messageId = (int) ($sent['result']['message_id'] ?? 0);
        if ($messageId > 0) {
            Anchor::setPreview($userId, $messageId);
        }

        $this->render($chatId, $userId, $this->screenJob($jobKey), 'پیش‌نمایش ساخته شد.');
    }

    private function quickSend(int $chatId, int $userId, string $arg): void
    {
        $key = trim($arg);
        if (!in_array($key, Registry::keys(), true)) {
            $this->render(
                $chatId,
                $userId,
                $this->screenMain(),
                'قالب درست: /send prices — کارهای موجود: ' . implode(', ', Registry::keys())
            );
            return;
        }
        $result = Dispatcher::run($key);
        $this->render($chatId, $userId, $this->screenJob($key), $result['message']);
    }

    private function screenPrompt(string $title, string $body, string $back): array
    {
        return [
            'text' => '<b>' . $title . "</b>\n\n" . $body . "\n\n"
                . 'پاسخ را همین‌جا بفرستید یا دکمه انصراف را بزنید.',
            'kb'   => [[$this->btn('انصراف', $back)]],
        ];
    }

    private function screenMain(): array
    {
        $now = Settings::now();
        $lines = [
            '<b>پنل مدیریت ' . htmlspecialchars(Settings::get('brand')) . '</b>',
            '',
            $this->fa(Jalali::format($now, 'l j F Y')) . ' — ساعت ' . $this->fa($now->format('H:i')),
            'کانال‌های ثبت‌شده: ' . $this->fa((string) count(Channels::all(true))),
            '',
        ];

        foreach (Registry::all() as $key => $job) {
            $next = Scheduler::nextRun($key, $now);
            $lines[] = sprintf(
                '<b>%s</b> — %s%s',
                htmlspecialchars($job->title()),
                $job->enabled() ? 'فعال' : 'خاموش',
                $next !== null ? ' | ارسال بعدی ' . $this->fa($next->format('H:i')) : ' | بدون زمان‌بندی'
            );
        }

        $kb = [];
        foreach (Registry::all() as $key => $job) {
            $kb[] = [$this->btn($job->title(), 'j:' . $key)];
        }
        $kb[] = [$this->btn('کانال‌ها', 'ch'), $this->btn('تنظیمات', 's')];
        $kb[] = [$this->btn('وضعیت', 'st'), $this->btn('گزارش‌ها', 'lg')];
        $kb[] = [$this->btn('راهنما', 'hp')];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function captionPreview(string $caption): string
    {
        if (trim($caption) === '') {
            return "کپشن فعلی: <i>خالی</i>\n\n";
        }

        $nested = (bool) preg_match('#<(blockquote|pre)\b#i', $caption);

        $out = "کپشن فعلی (همان‌طور که در کانال دیده می‌شود):\n"
            . ($nested ? $caption . "\n" : '<blockquote>' . $caption . "</blockquote>\n");

        if ($caption !== strip_tags($caption)) {
            $out .= mb_strlen($caption) > 700
                ? "\n(متن خام طولانی است و نشان داده نمی‌شود؛ برای تغییر، کپشن تازه بفرستید.)\n"
                : "\nمتن خام (برای ویرایش، همین را کپی و اصلاح کنید):\n"
                    . '<code>' . htmlspecialchars($caption) . "</code>\n";
        }

        return $out . "\n";
    }

    private function screenJob(string $key): array
    {
        $job = Registry::refresh($key);
        if ($job === null) {
            return $this->screenMain();
        }
        $theme = new Theme($job->theme());
        $schedules = $job->schedules();

        $times = [];
        foreach ($schedules as $row) {
            $times[] = $this->fa((string) $row['at_time']) . ((int) $row['enabled'] === 1 ? '' : ' (متوقف)');
        }

        $lines = [
            '<b>' . htmlspecialchars($job->title()) . '</b>',
            '<i>' . htmlspecialchars($job->description()) . '</i>',
            '',
            'وضعیت: ' . ($job->enabled() ? 'فعال' : 'خاموش'),
            'تم: ' . htmlspecialchars($theme->label()),
            'کانال‌های مقصد: ' . ($job->channels() === [] ? 'انتخاب نشده' : $this->fa((string) count($job->channels())) . ' کانال'),
            'زمان‌های ارسال: ' . ($times === [] ? 'تنظیم نشده' : implode('، ', $times)),
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
                $this->btn($job->enabled() ? 'خاموش کن' : 'روشن کن', 'j:' . $key . ':on'),
                $this->btn('تم کارت', 'j:' . $key . ':th'),
            ],
            [
                $this->btn('کانال‌های مقصد', 'j:' . $key . ':ch'),
                $this->btn('زمان‌بندی', 'j:' . $key . ':sc'),
            ],
            [
                $this->btn('کپشن', 'j:' . $key . ':cap'),
                $this->btn('تنظیمات کارت', 'j:' . $key . ':opt'),
            ],
            [
                $this->btn('پیش‌نمایش', 'j:' . $key . ':pv'),
                $this->btn('ارسال فوری', 'j:' . $key . ':go'),
            ],
            [$this->btn('بازگشت', 'm')],
        ];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenThemes(string $key): array
    {
        $current = Registry::refresh($key)?->theme() ?? Theme::DEFAULT;

        $kb = [];
        foreach (Theme::options() as $name => $label) {
            $kb[] = [$this->btn($label . ($name === $current ? ' (انتخاب‌شده)' : ''), 'j:' . $key . ':th:' . $name)];
        }
        $kb[] = [$this->btn('بازگشت', 'j:' . $key)];

        return [
            'text' => "<b>تم کارت</b>\n\nرنگ‌بندی کارت را انتخاب کنید.\n"
                . 'برای دیدن نتیجه از دکمه پیش‌نمایش استفاده کنید.',
            'kb'   => $kb,
        ];
    }

    private function screenJobChannels(string $key): array
    {
        $job = Registry::refresh($key);
        $channels = Channels::all();

        if ($channels === []) {
            return [
                'text' => "<b>کانال‌های مقصد</b>\n\nهنوز کانالی ثبت نشده است.",
                'kb'   => [[$this->btn('افزودن کانال', 'ch:add')], [$this->btn('بازگشت', 'j:' . $key)]],
            ];
        }

        $kb = [];
        foreach ($channels as $channel) {
            $on = $job !== null && $job->hasChannel((int) $channel['id']);
            $kb[] = [$this->btn(
                Channels::label($channel)
                    . ($on ? ' (انتخاب‌شده)' : '')
                    . ((int) $channel['active'] === 0 ? ' (بدون دسترسی)' : ''),
                'j:' . $key . ':ch:' . $channel['id']
            )];
        }
        $kb[] = [$this->btn('بازگشت', 'j:' . $key)];

        return [
            'text' => "<b>کانال‌های مقصد</b>\n\nکانال‌هایی که این کارت باید در آن‌ها منتشر شود را انتخاب کنید.",
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
            '<b>زمان‌بندی — ' . htmlspecialchars($job->title()) . '</b>',
            'منطقه زمانی: <code>' . htmlspecialchars(Settings::get('timezone')) . '</code>',
            '',
        ];
        if ($rows === []) {
            $lines[] = 'هنوز زمانی ثبت نشده است.';
        } else {
            foreach ($rows as $i => $row) {
                $lines[] = sprintf(
                    '%s) <b>%s</b> — %s%s',
                    $this->fa((string) ($i + 1)),
                    $this->fa((string) $row['at_time']),
                    $this->daysLabel((string) $row['days']),
                    (int) $row['enabled'] === 1 ? '' : ' (متوقف)'
                );
            }
        }

        $kb = [[$this->btn('افزودن زمان', 'j:' . $key . ':sc:add')]];
        foreach ($rows as $row) {
            $kb[] = [
                $this->btn('حذف ' . $this->fa((string) $row['at_time']), 'sc:d:' . $row['id']),
                $this->btn((int) $row['enabled'] === 1 ? 'توقف' : 'فعال', 'sc:t:' . $row['id']),
                $this->btn('روزها', 'sc:days:' . $row['id']),
            ];
        }
        $kb[] = [$this->btn('بازگشت', 'j:' . $key)];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenDays(int $scheduleId): array
    {
        $row = Db::one('SELECT * FROM schedules WHERE id = :i', [':i' => $scheduleId]);
        if ($row === null) {
            return $this->screenMain();
        }
        $days = (string) $row['days'];
        $list = $days === '*'
            ? array_keys(self::DAYS)
            : array_map('intval', array_filter(explode(',', $days), 'strlen'));

        $kb = [];
        $buffer = [];
        foreach (self::DAYS as $num => $label) {
            $buffer[] = $this->btn($label . (in_array($num, $list, true) ? ' (روشن)' : ''), 'sc:day:' . $scheduleId . ':' . $num);
            if (count($buffer) === 2) {
                $kb[] = $buffer;
                $buffer = [];
            }
        }
        if ($buffer !== []) {
            $kb[] = $buffer;
        }
        $kb[] = [$this->btn('همه روزها', 'sc:all:' . $scheduleId)];
        $kb[] = [$this->btn('بازگشت', 'j:' . $row['job_key'] . ':sc')];

        return [
            'text' => '<b>روزهای ارسال ساعت ' . $this->fa((string) $row['at_time']) . "</b>\n\n"
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

        $lines = ['<b>تنظیمات کارت — ' . htmlspecialchars($job->title()) . '</b>', ''];
        $lines[] = 'عنوان کارت: <code>' . htmlspecialchars((string) $job->option('headline', '—')) . '</code>';

        $kb = [[$this->btn('عنوان کارت', 'j:' . $key . ':head')]];

        if ($job instanceof PricesJob) {
            $lines[] = 'ارزها: <code>' . implode(', ', $job->coins()) . '</code>';
            $lines[] = 'نمودار کوچک: ' . ($job->option('sparkline', true) ? 'روشن' : 'خاموش');
            $kb[] = [$this->btn('انتخاب ارزها', 'j:' . $key . ':coins')];
            $kb[] = [$this->btn('نمودار کوچک: ' . ($job->option('sparkline', true) ? 'روشن' : 'خاموش'), 'j:' . $key . ':spark')];
        }

        if ($job instanceof CalendarJob) {
            $shift = (int) $job->option('day_shift', 0);
            $lines[] = 'روز نمایش: ' . ($shift === 0 ? 'امروز' : ($shift === 1 ? 'فردا' : 'دیروز'));
            $lines[] = 'حداقل اهمیت: ' . $this->fa((string) Settings::int('calendar_min_impact', 2)) . ' از ۳';
            $kb[] = [
                $this->btn('امروز' . ($shift === 0 ? ' (انتخاب‌شده)' : ''), 'j:' . $key . ':shift:0'),
                $this->btn('فردا' . ($shift === 1 ? ' (انتخاب‌شده)' : ''), 'j:' . $key . ':shift:1'),
            ];
            $kb[] = [$this->btn('حداقل اهمیت رویدادها', 's:imp')];
        }

        if ($job instanceof MoversJob) {
            $count = $job->count();
            $volume = (int) $job->minVolume();
            $lines[] = 'تعداد ارز در هر ستون: ' . $this->fa((string) $count);
            $lines[] = 'حداقل حجم ۲۴ ساعته: ' . $this->fa($this->money($volume));
            $lines[] = '';
            $lines[] = 'قراردادهای کم‌حجم‌تر از این مقدار در رتبه‌بندی حساب نمی‌شوند.';
            $row = [];
            foreach (MoversJob::COUNTS as $n) {
                $row[] = $this->btn($this->fa((string) $n) . ' ارز' . ($n === $count ? ' (انتخاب‌شده)' : ''), 'j:' . $key . ':cnt:' . $n);
            }
            $kb[] = array_slice($row, 0, 2);
            $kb[] = array_slice($row, 2);
            $row = [];
            foreach (MoversJob::VOLUMES as $v) {
                $row[] = $this->btn('حجم ' . $this->fa($this->money($v)) . ($v === $volume ? ' (انتخاب‌شده)' : ''), 'j:' . $key . ':vol:' . $v);
            }
            $kb[] = array_slice($row, 0, 2);
            $kb[] = array_slice($row, 2);
        }

        if ($job instanceof LiquidityJob) {
            $levels = $job->levels();
            $lines[] = 'تعداد دیوار در هر سمت: ' . $this->fa((string) $levels);
            $lines[] = '';
            $lines[] = 'منبع: دفتر سفارش فیوچرز و اسپات بایننس (رایگان، بدون کلید).';
            $row = [];
            foreach (LiquidityJob::LEVELS as $n) {
                $row[] = $this->btn($this->fa((string) $n) . ' سطح' . ($n === $levels ? ' (انتخاب‌شده)' : ''), 'j:' . $key . ':lvl:' . $n);
            }
            $kb[] = array_slice($row, 0, 2);
            $kb[] = array_slice($row, 2);
        }

        $kb[] = [$this->btn('بازگشت', 'j:' . $key)];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function money(int $usd): string
    {
        return $usd >= 1_000_000 ? '$' . intdiv($usd, 1_000_000) . 'M' : '$' . number_format($usd);
    }

    private function screenChannels(): array
    {
        $channels = Channels::all();
        $lines = ['<b>کانال‌های مقصد</b>', ''];

        if ($channels === []) {
            $lines[] = 'هنوز کانالی ثبت نشده است.';
            $lines[] = '';
            $lines[] = 'ابتدا ربات را در کانال ادمین کنید، سپس دکمه افزودن کانال را بزنید.';
        } else {
            foreach ($channels as $i => $channel) {
                $jobs = [];
                foreach (Registry::all() as $job) {
                    if ($job->hasChannel((int) $channel['id'])) {
                        $jobs[] = $job->title();
                    }
                }
                $lines[] = sprintf(
                    '%s) %s%s',
                    $this->fa((string) ($i + 1)),
                    htmlspecialchars(Channels::label($channel)),
                    (int) $channel['active'] === 1 ? '' : ' — بدون دسترسی'
                );
                $lines[] = '    <code>' . $channel['chat_id'] . '</code>';
                if ($jobs !== []) {
                    $lines[] = '    کارت‌ها: ' . htmlspecialchars(implode('، ', $jobs));
                }
            }
        }

        $kb = [[$this->btn('افزودن کانال', 'ch:add'), $this->btn('بررسی دسترسی', 'ch:chk')]];
        foreach ($channels as $channel) {
            $kb[] = [$this->btn('حذف ' . Channels::label($channel), 'ch:del:' . $channel['id'])];
        }
        $kb[] = [$this->btn('بازگشت', 'm')];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenSettings(): array
    {
        $lines = [
            '<b>تنظیمات کلی</b>',
            '',
            'منطقه زمانی: <code>' . htmlspecialchars(Settings::get('timezone')) . '</code>',
            'نام ربات: <code>' . htmlspecialchars(Settings::get('brand')) . '</code>',
            'امضای زیر کارت: <code>' . htmlspecialchars(Settings::get('brand_link') ?: '—') . '</code>',
            'ارقام متن: ' . (Settings::get('digits') === 'fa' ? 'فارسی' : 'لاتین'),
            'ارقام داده: ' . (Settings::get('digits_data') === 'fa' ? 'فارسی' : 'لاتین'),
            'کیفیت تصویر: ' . $this->qualityLabel(),
            'حالت ارسال: ' . (Settings::get('send_mode') === 'photo' ? 'عکس' : 'فایل (بدون فشرده‌سازی)'),
            'منبع قیمت: <code>' . Settings::get('price_source') . '</code>',
            'منبع تقویم: <code>' . Settings::get('calendar_source') . '</code>',
            'حداقل اهمیت خبر: ' . $this->fa((string) Settings::int('calendar_min_impact', 2)),
            'ارزهای تقویم: <code>' . htmlspecialchars(Settings::get('calendar_currencies')) . '</code>',
            'ارزهای پیش‌فرض: <code>' . htmlspecialchars(Settings::get('coins')) . '</code>',
            'جبران تأخیر: ' . $this->fa((string) Settings::int('catchup_minutes', 10)) . ' دقیقه',
        ];

        $kb = [
            [$this->btn('منطقه زمانی', 's:tz'), $this->btn('نام ربات', 's:brand')],
            [$this->btn('امضای زیر کارت', 's:link'), $this->btn('ارزهای پیش‌فرض', 's:coins')],
            [$this->btn('ارقام متن', 's:digits'), $this->btn('ارقام داده', 's:ddata')],
            [$this->btn('کیفیت تصویر', 's:q'), $this->btn('حالت ارسال', 's:mode')],
            [$this->btn('منبع قیمت', 's:psrc'), $this->btn('منبع تقویم', 's:csrc')],
            [$this->btn('حداقل اهمیت', 's:imp'), $this->btn('ارزهای تقویم', 's:cur')],
            [$this->btn('جبران تأخیر', 's:catch'), $this->btn('مدیران', 's:adm')],
            [$this->btn('بازگشت', 'm')],
        ];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenAdmins(): array
    {
        $admins = Access::list();
        $lines = ['<b>مدیران ربات</b>', ''];
        foreach ($admins as $i => $admin) {
            $lines[] = sprintf(
                '%s) <code>%s</code>%s',
                $this->fa((string) ($i + 1)),
                $admin['user_id'],
                isset($admin['fixed']) ? ' — ثابت' : ''
            );
        }

        $kb = [[$this->btn('افزودن مدیر', 's:adm:add')]];
        foreach ($admins as $admin) {
            if (isset($admin['fixed'])) {
                continue;
            }
            $kb[] = [$this->btn('حذف ' . $admin['user_id'], 's:adm:del:' . $admin['user_id'])];
        }
        $kb[] = [$this->btn('بازگشت', 's')];

        return ['text' => implode("\n", $lines), 'kb' => $kb];
    }

    private function screenStatus(): array
    {
        $now = Settings::now();
        $lines = [
            '<b>وضعیت سامانه</b>',
            '',
            'اکنون: ' . $this->fa(Jalali::format($now, 'l j F Y')) . ' — ' . $this->fa($now->format('H:i:s')),
            'منطقه زمانی: <code>' . htmlspecialchars(Settings::get('timezone')) . '</code>',
            '',
        ];

        foreach (Registry::all() as $key => $job) {
            $last = Db::one('SELECT * FROM runs WHERE job_key = :k ORDER BY created_at DESC LIMIT 1', [':k' => $key]);
            $next = Scheduler::nextRun($key, $now);
            $lines[] = '<b>' . htmlspecialchars($job->title()) . '</b>';
            $lines[] = '    وضعیت: ' . ($job->enabled() ? 'فعال' : 'خاموش')
                . ' | کانال: ' . $this->fa((string) count($job->channels()));
            $lines[] = '    ارسال بعدی: ' . ($next !== null ? $this->fa($next->format('Y-m-d H:i')) : '—');
            if ($last !== null) {
                $lines[] = '    آخرین اجرا: ' . $this->fa(date('Y-m-d H:i', (int) $last['created_at']))
                    . ' (' . $last['status'] . ')';
                if ((string) $last['detail'] !== '') {
                    $lines[] = '    <i>' . htmlspecialchars(mb_substr((string) $last['detail'], 0, 120)) . '</i>';
                }
            }
            $lines[] = '';
        }

        $heartbeat = Settings::get('scheduler_heartbeat', '');
        $lines[] = 'زمان‌بند: ' . ($heartbeat !== ''
            ? 'آخرین بررسی ' . $this->fa(date('H:i:s', (int) $heartbeat))
                . ((time() - (int) $heartbeat) < 180 ? ' — در حال اجرا' : ' — متوقف است')
            : 'هنوز اجرا نشده');

        return [
            'text' => implode("\n", $lines),
            'kb'   => [[$this->btn('به‌روزرسانی', 'st')], [$this->btn('بازگشت', 'm')]],
        ];
    }

    private function screenLogs(): array
    {
        $rows = Db::all('SELECT * FROM runs ORDER BY created_at DESC LIMIT 12');
        $lines = ['<b>آخرین اجراها</b>', ''];

        if ($rows === []) {
            $lines[] = 'هنوز اجرایی ثبت نشده است.';
        }
        foreach ($rows as $row) {
            $status = match ((string) $row['status']) {
                'ok'      => 'موفق',
                'partial' => 'ناقص',
                'running' => 'در حال اجرا',
                default   => 'خطا',
            };
            $job = Registry::get((string) $row['job_key']);
            $lines[] = sprintf(
                '<b>%s</b> — %s — %s',
                htmlspecialchars($job?->title() ?? (string) $row['job_key']),
                $this->fa(date('m-d H:i', (int) $row['created_at'])),
                $status
            );
            if ((string) $row['detail'] !== '') {
                $lines[] = '    <i>' . htmlspecialchars(mb_substr((string) $row['detail'], 0, 110)) . '</i>';
            }
        }

        $errors = Db::all("SELECT * FROM logs WHERE level IN ('error','warn') ORDER BY created_at DESC LIMIT 5");
        if ($errors !== []) {
            $lines[] = '';
            $lines[] = '<b>آخرین خطاها</b>';
            foreach ($errors as $row) {
                $lines[] = '- ' . $this->fa(date('m-d H:i', (int) $row['created_at'])) . ' — '
                    . htmlspecialchars(mb_substr((string) $row['message'], 0, 90));
            }
        }

        return [
            'text' => implode("\n", $lines),
            'kb'   => [[$this->btn('به‌روزرسانی', 'lg')], [$this->btn('بازگشت', 'm')]],
        ];
    }

    private function screenHelp(): array
    {
        $text = "<b>راهنمای سریع</b>\n\n"
            . "<b>۱) کانال مقصد</b>\nربات را در کانال ادمین کنید، سپس از بخش کانال‌ها گزینه افزودن کانال را بزنید و "
            . "یک پست از کانال را فوروارد کنید یا آیدی کانال را بفرستید.\n\n"
            . "<b>۲) زمان ارسال</b>\nوارد هر کارت شوید، زمان‌بندی را باز کنید، افزودن زمان را بزنید و ساعت را بفرستید "
            . "(مثل 09:00 یا چند ساعت با کاما). در صورت نیاز روزهای هفته را هم مشخص کنید.\n\n"
            . "<b>۳) روشن‌کردن</b>\nهر کارت را با دکمه روشن کن فعال کنید تا زمان‌بند آن را ارسال کند.\n\n"
            . "<b>۴) پیش‌نمایش</b>\nبا دکمه پیش‌نمایش، کارت فقط برای شما ساخته و ارسال می‌شود.\n\n"
            . "<b>دستورها</b>\n<code>/panel</code> پنل مدیریت\n<code>/status</code> وضعیت\n"
            . "<code>/send prices</code> ارسال فوری یک کارت\n<code>/id</code> شناسه عددی شما\n\n"
            . 'توجه: زمان‌بند باید روی سرور در حال اجرا باشد، وگرنه کارت‌ها سر ساعت ارسال نمی‌شوند.';

        return ['text' => $text, 'kb' => [[$this->btn('بازگشت', 'm')]]];
    }

    private function btn(string $text, string $data): array
    {
        return ['text' => $text, 'callback_data' => $data];
    }

    private function qualityLabel(): string
    {
        return match (Settings::get('quality')) {
            'normal' => 'معمولی (۱۸۰۰ پیکسل)',
            'ultra'  => 'حداکثر (۳۶۰۰ پیکسل)',
            default  => 'بالا (۳۰۰۰ پیکسل)',
        };
    }

    private function daysLabel(string $days): string
    {
        $days = trim($days);
        if ($days === '' || $days === '*') {
            return 'همه روزها';
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

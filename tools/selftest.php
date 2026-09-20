<?php
/**
 * تست خودکار آفلاین: پنل، زمان‌بند و کارت‌ها را بدون اینترنت بررسی می‌کند.
 *
 *   php tools/selftest.php
 *
 * از یک دیتابیس موقت و یک تلگرام تقلبی استفاده می‌کند و به داده‌های واقعی دست نمی‌زند.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Settings;
use Nikto\Data\Mock;
use Nikto\Jobs\Dispatcher;
use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Telegram\Api;
use Nikto\Telegram\Panel;

/** تلگرام تقلبی: به‌جای ارسال، فراخوانی‌ها را ثبت می‌کند */
final class FakeApi extends Api
{
    /** @var array<int,array{method:string,params:array}> */
    public array $calls = [];

    public function call(string $method, array $params = [], int $timeout = 60): array
    {
        $this->calls[] = ['method' => $method, 'params' => $params];

        return match ($method) {
            'getMe'   => ['ok' => true, 'result' => ['id' => 8870139346, 'username' => 'nikto_test_bot']],
            'getChat' => ['ok' => true, 'result' => [
                'id' => -1001234567890, 'title' => 'NIKTO CRYPTO', 'username' => 'nikto_crypto', 'type' => 'channel',
            ]],
            'getChatMember' => ['ok' => true, 'result' => ['status' => 'administrator']],
            default   => ['ok' => true, 'result' => ['message_id' => count($this->calls)]],
        };
    }

    public function lastText(): string
    {
        for ($i = count($this->calls) - 1; $i >= 0; $i--) {
            if (in_array($this->calls[$i]['method'], ['sendMessage', 'editMessageText'], true)) {
                return (string) ($this->calls[$i]['params']['text'] ?? '');
            }
        }

        return '';
    }

    public function countOf(string $method): int
    {
        return count(array_filter($this->calls, static fn ($c) => $c['method'] === $method));
    }
}

// ---------------------------------------------------------------- راه‌اندازی

$tmpDb = sys_get_temp_dir() . '/nikto-selftest-' . getmypid() . '.sqlite';
@unlink($tmpDb);
Config::set('db_path', $tmpDb);
Config::set('bot_token', '8870139346:TEST');
Config::set('bot_id', 8870139346);
Config::set('admins', [6595849261]);
Config::set('owner_id', 6595849261);
Mock::enable();

Db::migrate();
Registry::all();
Settings::set('timezone', 'Asia/Tehran');
Settings::set('brand', 'NIKTO CRYPTO');
Settings::set('brand_link', '@nikto_crypto');

$admin = 6595849261;
$api = new FakeApi();
Dispatcher::setApi($api);
$panel = new Panel($api);

$passed = 0;
$failed = 0;
$check = function (string $name, callable $fn) use (&$passed, &$failed): void {
    try {
        $result = $fn();
        if ($result === true || $result === null) {
            echo "  ✅ {$name}\n";
            $passed++;
        } else {
            echo "  ❌ {$name} — {$result}\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "  ❌ {$name} — استثنا: " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
        $failed++;
    }
};

$message = static fn (string $text): array => [
    'update_id' => random_int(1, 1000000),
    'message' => [
        'message_id' => random_int(1, 1000000),
        'from' => ['id' => 6595849261, 'first_name' => 'Owner'],
        'chat' => ['id' => 6595849261, 'type' => 'private'],
        'text' => $text,
    ],
];

$callback = static fn (string $data): array => [
    'update_id' => random_int(1, 1000000),
    'callback_query' => [
        'id' => (string) random_int(1, 1000000),
        'from' => ['id' => 6595849261],
        'message' => ['message_id' => 42, 'chat' => ['id' => 6595849261, 'type' => 'private']],
        'data' => $data,
    ],
];

echo "\n🧪 تست خودکار NIKTO CRYPTO BOT\n" . str_repeat('─', 52) . "\n";

// ---------------------------------------------------------------- متن فارسی
echo "\n▸ موتور متن فارسی\n";
$check('شکل‌دهی حروف و ادغام لام‌الف', function () {
    $out = \Nikto\Text\Persian::prepare('سلام');
    // خروجی بصری: م(پایانی) + لا(ligature) + س(ابتدایی)
    $codes = array_map(static fn ($c) => mb_ord($c), preg_split('//u', $out, -1, PREG_SPLIT_NO_EMPTY));
    return $codes === [0xFEE1, 0xFEFC, 0xFEB3] ? true : 'خروجی: ' . implode(',', array_map('dechex', $codes));
});
$check('ترتیب اعداد در متن راست‌چین', function () {
    $out = \Nikto\Text\Persian::prepare('قیمت 62,979 دلار');
    return str_contains($out, '62,979') ? true : 'عدد جابه‌جا شد: ' . $out;
});
$check('درصد و علامت منفی', function () {
    $out = \Nikto\Text\Persian::prepare('تغییر -3.08% بود');
    return str_contains($out, '-3.08%') ? true : 'خروجی: ' . $out;
});
$check('متن لاتین دست‌نخورده', fn () => \Nikto\Text\Persian::prepare('BTC/USDT') === 'BTC/USDT' ? true : 'تغییر کرد');

// ---------------------------------------------------------------- تاریخ
echo "\n▸ تاریخ شمسی\n";
$check('۱ فروردین ۱۴۰۵', function () {
    $out = \Nikto\Core\Jalali::format(new DateTimeImmutable('2026-03-21'), 'j F Y');
    return $out === '1 فروردین 1405' ? true : $out;
});
$check('سال کبیسه (۳۰ اسفند)', function () {
    $out = \Nikto\Core\Jalali::format(new DateTimeImmutable('2026-03-20'), 'j F Y');
    return $out === '30 اسفند 1404' ? true : $out;
});

// ---------------------------------------------------------------- داده‌ها
echo "\n▸ لایه‌ی داده (حالت نمونه)\n";
$check('قیمت‌ها', function () {
    $rows = \Nikto\Data\PriceProvider::fetch(['BTC', 'ETH', 'XRP']);
    return count($rows) === 3 && $rows[0]['price'] > 0 && count($rows[0]['spark']) === 24 ? true : 'ناقص';
});
$check('شاخص ترس و طمع', function () {
    $d = \Nikto\Data\FearGreedProvider::fetch();
    return isset($d['value'], $d['zone']['fa']) && count($d['history']) === 3 ? true : 'ناقص';
});
$check('تقویم اقتصادی', function () {
    $rows = \Nikto\Data\CalendarProvider::forDay();
    return count($rows) > 5 && $rows[0]['title_fa'] !== '' ? true : 'ردیفی نیست';
});
$check('ترجمه‌ی رویدادها', function () {
    $out = \Nikto\Data\EventTranslator::translate('ADP Non-Farm Employment Change');
    return str_contains($out, 'اشتغال') ? true : $out;
});
$check('تشخیص سخنرانی', fn () => \Nikto\Data\CalendarProvider::isSpeech('BOC Gov Macklem Speaks') ? true : 'تشخیص نداد');

// ---------------------------------------------------------------- کارت‌ها
echo "\n▸ ساخت کارت‌ها\n";
foreach (Registry::keys() as $key) {
    $check('کارت ' . $key, function () use ($key) {
        $job = Registry::refresh($key);
        $data = $job->fetch();
        $path = $job->card($data)->save(APP_STORAGE . '/cards/selftest-' . $key . '.png');
        $size = filesize($path);
        [$w, $h] = getimagesize($path);

        // عرض بسته به کیفیت انتخابی: ۱۸۰۰ تا ۳۶۰۰ پیکسل
        return ($size > 20000 && in_array($w, [1200, 1800, 2400, 3000, 3600], true) && $h > 400 && $h < $w)
            ? true
            : sprintf('ابعاد %dx%d حجم %d', $w, $h, $size);
    });
}
$check('کپشن با متغیرها', function () {
    $job = Registry::refresh('prices');
    $caption = $job->renderCaption($job->fetch());
    return str_contains($caption, 'BTC') && !str_contains($caption, '{') ? true : $caption;
});

// ---------------------------------------------------------------- پنل
echo "\n▸ پنل مدیریت\n";
$check('نمایش منوی اصلی با /start', function () use ($panel, $message, $api) {
    $panel->handleUpdate($message('/start'));
    return str_contains($api->lastText(), 'پنل مدیریت') ? true : $api->lastText();
});
$check('رد کاربر غیرمدیر', function () use ($panel, $api) {
    $api->calls = [];
    $panel->handleUpdate([
        'update_id' => 1,
        'message' => [
            'message_id' => 1,
            'from' => ['id' => 111222333],
            'chat' => ['id' => 111222333, 'type' => 'private'],
            'text' => '/panel',
        ],
    ]);
    $text = $api->lastText();
    return !str_contains($text, 'پنل مدیریت') && !str_contains($text, 'زمان‌بندی')
        ? true
        : 'به غیرمدیر پنل نشان داد';
});
$check('افزودن کانال با فوروارد', function () use ($panel, $api) {
    $panel->handleUpdate([
        'update_id' => 2,
        'message' => [
            'message_id' => 2,
            'from' => ['id' => 6595849261],
            'chat' => ['id' => 6595849261, 'type' => 'private'],
            'forward_origin' => ['type' => 'channel', 'chat' => ['id' => -1001234567890, 'title' => 'NIKTO CRYPTO']],
        ],
    ]);
    $channels = \Nikto\Telegram\Channels::all();
    return count($channels) === 1 && $channels[0]['chat_id'] === '-1001234567890' ? true : 'ثبت نشد';
});
$check('اتصال خودکار کانال به کارها', function () {
    foreach (Registry::all() as $job) {
        if ($job->channels() === []) {
            return 'کار ' . $job->key() . ' کانال ندارد';
        }
    }
    return true;
});
$check('روشن‌کردن کارت قیمت‌ها', function () use ($panel, $callback) {
    $panel->handleUpdate($callback('j:prices:on'));
    return Registry::refresh('prices')->enabled() ? true : 'فعال نشد';
});
$check('تغییر تم', function () use ($panel, $callback) {
    $panel->handleUpdate($callback('j:prices:th:ocean'));
    return Registry::refresh('prices')->theme() === 'ocean' ? true : 'تم عوض نشد';
});
$check('افزودن چند زمان ارسال', function () use ($panel, $callback, $message) {
    $panel->handleUpdate($callback('j:prices:sc:add'));
    $panel->handleUpdate($message('09:00, 21:30, 8'));
    $rows = Registry::refresh('prices')->schedules();
    $times = array_column($rows, 'at_time');
    sort($times);
    return $times === ['08:00', '09:00', '21:30'] ? true : implode(',', $times);
});
$check('رد زمان نامعتبر', function () use ($panel, $callback, $message) {
    $panel->handleUpdate($callback('j:feargreed:sc:add'));
    $panel->handleUpdate($message('99:99'));
    return Registry::refresh('feargreed')->schedules() === [] ? true : 'زمان نامعتبر ثبت شد';
});
$check('انتخاب روزهای هفته', function () use ($panel, $callback) {
    $row = Db::one("SELECT * FROM schedules WHERE job_key = 'prices' ORDER BY at_time LIMIT 1");
    $panel->handleUpdate($callback('sc:day:' . $row['id'] . ':5'));
    $after = Db::one('SELECT days FROM schedules WHERE id = :i', [':i' => $row['id']]);
    return $after['days'] !== '*' && !str_contains((string) $after['days'], '5') ? true : 'روزها: ' . $after['days'];
});
$check('حذف زمان‌بندی', function () use ($panel, $callback) {
    $row = Db::one("SELECT * FROM schedules WHERE job_key = 'prices' ORDER BY at_time DESC LIMIT 1");
    $before = count(Registry::refresh('prices')->schedules());
    $panel->handleUpdate($callback('sc:d:' . $row['id']));
    return count(Registry::refresh('prices')->schedules()) === $before - 1 ? true : 'حذف نشد';
});
$check('تغییر ارزهای کارت', function () use ($panel, $callback, $message) {
    $panel->handleUpdate($callback('j:prices:coins'));
    $panel->handleUpdate($message('btc, eth, sol, doge'));
    $coins = Registry::refresh('prices')->coins();
    return $coins === ['BTC', 'ETH', 'SOL', 'DOGE'] ? true : implode(',', $coins);
});
$check('ویرایش کپشن', function () use ($panel, $callback, $message) {
    $panel->handleUpdate($callback('j:prices:cap'));
    $panel->handleUpdate($message('تست {date}'));
    return Registry::refresh('prices')->caption() === 'تست {date}' ? true : 'ذخیره نشد';
});
$check('قالب‌بندی بولد و نقل‌قول در کپشن حفظ می‌شود', function () use ($panel, $callback, $api) {
    $panel->handleUpdate($callback('j:feargreed:cap'));
    $panel->handleUpdate([
        'update_id' => random_int(1, 1000000),
        'message' => [
            'message_id' => random_int(1, 1000000),
            'from' => ['id' => 6595849261],
            'chat' => ['id' => 6595849261, 'type' => 'private'],
            'text' => 'عنوان تست' . "\n" . 'نقل قول',
            'entities' => [
                ['type' => 'bold', 'offset' => 0, 'length' => 9],
                ['type' => 'blockquote', 'offset' => 10, 'length' => 8],
            ],
        ],
    ]);
    $caption = Registry::refresh('feargreed')->caption();

    return (str_contains($caption, '<b>') && str_contains($caption, '<blockquote>'))
        ? true
        : 'کپشن ذخیره‌شده: ' . $caption;
});
$check('تنظیمات: تغییر ارقام', function () use ($panel, $callback) {
    $before = Settings::get('digits');
    $panel->handleUpdate($callback('s:digits'));
    return Settings::get('digits') !== $before ? true : 'تغییر نکرد';
});
$check('تنظیمات: منطقه‌ی زمانی نامعتبر رد شود', function () use ($panel, $callback, $message) {
    $panel->handleUpdate($callback('s:tz'));
    $panel->handleUpdate($message('Mars/Olympus'));
    return Settings::get('timezone') === 'Asia/Tehran' ? true : Settings::get('timezone');
});
$check('پنل پیام تازه نمی‌سازد و همان پیام را ویرایش می‌کند', function () use ($panel, $callback, $message, $api) {
    $panel->handleUpdate($message('/start'));      // یک پیام پنل ساخته می‌شود
    $api->calls = [];
    $panel->handleUpdate($callback('j:prices'));
    $panel->handleUpdate($callback('j:prices:sc'));
    $panel->handleUpdate($callback('j:prices:sc:add'));
    $panel->handleUpdate($message('07:15'));       // ورودی متنی
    $panel->handleUpdate($callback('m'));

    $sent = $api->countOf('sendMessage');
    $edited = $api->countOf('editMessageText');

    return ($sent === 0 && $edited >= 4)
        ? true
        : sprintf('پیام تازه: %d — ویرایش: %d', $sent, $edited);
});
$check('پیام ورودی کاربر پاک می‌شود', function () use ($panel, $callback, $message, $api) {
    $api->calls = [];
    $panel->handleUpdate($callback('s:brand'));
    $panel->handleUpdate($message('NIKTO TEST'));

    return $api->countOf('deleteMessage') >= 1 ? true : 'پیام کاربر پاک نشد';
});
$check('هیچ ایموجی‌ای در پنل نیست', function () {
    $source = (string) file_get_contents(APP_SRC . '/Telegram/Panel.php');
    $pattern = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{2190}-\x{21FF}]/u';

    return preg_match($pattern, $source) === 0 ? true : 'ایموجی پیدا شد';
});
$check('پیمایش همه‌ی صفحه‌ها بدون خطا', function () use ($panel, $callback) {
    $routes = ['m', 'ch', 's', 's:adm', 'st', 'lg', 'hp', 'j:prices', 'j:prices:th', 'j:prices:ch',
        'j:prices:sc', 'j:prices:opt', 'j:calendar', 'j:calendar:opt', 'j:feargreed', 's:imp', 's:psrc', 's:csrc'];
    foreach ($routes as $route) {
        $panel->handleUpdate($callback($route));
    }
    return true;
});

// ---------------------------------------------------------------- زمان‌بند
echo "\n▸ زمان‌بند\n";
$check('تشخیص زمان سررسیده', function () {
    Db::exec('DELETE FROM schedules');
    Db::exec('DELETE FROM runs');
    $now = Settings::now();
    Db::exec(
        'INSERT INTO schedules(job_key, at_time, days, enabled, created_at) VALUES(:j, :t, :d, 1, :c)',
        [':j' => 'prices', ':t' => $now->format('H:i'), ':d' => '*', ':c' => time()]
    );
    $due = Scheduler::due($now);

    return count($due) === 1 && $due[0]['job_key'] === 'prices' ? true : 'تعداد: ' . count($due);
});
$check('ارسال و جلوگیری از تکرار', function () use ($api) {
    $before = $api->countOf('sendPhoto');
    Scheduler::tick();
    $afterFirst = $api->countOf('sendPhoto');
    Scheduler::tick(); // نباید دوباره بفرستد
    $afterSecond = $api->countOf('sendPhoto');

    return ($afterFirst === $before + 1 && $afterSecond === $afterFirst)
        ? true
        : sprintf('ارسال‌ها: %d → %d → %d', $before, $afterFirst, $afterSecond);
});
$check('کار خاموش اجرا نشود', function () {
    Db::exec('DELETE FROM runs');
    Registry::refresh('prices')->setEnabled(false);
    $fired = Scheduler::tick();
    Registry::refresh('prices')->setEnabled(true);

    return $fired === [] ? true : 'کار خاموش اجرا شد';
});
$check('محدودیت روزهای هفته', function () {
    $now = Settings::now();
    $today = (int) $now->format('N');
    $other = $today === 7 ? 1 : $today + 1;
    return (Scheduler::matchesDay((string) $other, $now) === false
        && Scheduler::matchesDay((string) $today, $now) === true
        && Scheduler::matchesDay('*', $now) === true) ? true : 'منطق روزها اشتباه است';
});
$check('محاسبه‌ی زمان اجرای بعدی', function () {
    Db::exec('DELETE FROM schedules');
    Db::exec(
        'INSERT INTO schedules(job_key, at_time, days, enabled, created_at) VALUES(:j, :t, :d, 1, :c)',
        [':j' => 'calendar', ':t' => '07:30', ':d' => '*', ':c' => time()]
    );
    $next = Scheduler::nextRun('calendar');

    return $next !== null && $next->format('H:i') === '07:30' && $next > Settings::now()
        ? true
        : 'محاسبه نشد';
});
$check('ارسال دستی به کانال', function () use ($api) {
    $before = $api->countOf('sendPhoto');
    $result = Dispatcher::run('feargreed');

    return ($result['ok'] && $api->countOf('sendPhoto') === $before + 1) ? true : $result['message'];
});

// ---------------------------------------------------------------- نسخه‌ی دو فایلی
echo "\n▸ نسخه‌ی دو فایلی\n";
$check('همه‌ی کلاس‌ها داخل فایل نهایی هستند', function () {
    $out = sys_get_temp_dir() . '/nikto-build-' . getmypid();
    @mkdir($out, 0775, true);
    exec(
        'php ' . escapeshellarg(APP_ROOT . '/tools/build.php') . ' --out=' . escapeshellarg($out) . ' 2>&1',
        $lines,
        $status
    );
    $bundle = $out . '/nikto-bot.php';

    if ($status !== 0 || !is_file($bundle)) {
        return 'ساخت ناموفق: ' . implode(' | ', array_slice($lines, -3));
    }

    $source = (string) file_get_contents($bundle);
    $missing = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_SRC, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php' || $file->getFilename() === 'bootstrap.php') {
            continue;
        }
        $body = (string) file_get_contents($file->getPathname());
        if (preg_match('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+(\w+)/m', $body, $m)) {
            if (!str_contains($source, $m[1])) {
                $missing[] = $m[1];
            }
        }
    }

    foreach (glob($out . '/*') ?: [] as $file) {
        @unlink($file);
    }
    @rmdir($out);

    return $missing === [] ? true : 'کلاس‌های جامانده: ' . implode(', ', $missing);
});

// ---------------------------------------------------------------- پایان
echo "\n" . str_repeat('─', 52) . "\n";
printf("نتیجه: %d موفق، %d ناموفق\n\n", $passed, $failed);

@unlink($tmpDb);
foreach (glob(sys_get_temp_dir() . '/nikto-selftest-*') ?: [] as $file) {
    @unlink($file);
}

exit($failed === 0 ? 0 : 1);

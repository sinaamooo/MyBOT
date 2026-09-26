<?php
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

final class FakeApi extends Api
{
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

$tmpDb = sys_get_temp_dir() . '/nikto-selftest-' . getmypid() . '.sqlite';
@unlink($tmpDb);
Config::set('db_path', $tmpDb);
Config::set('bot_token', '8870139346:' . str_repeat('T', 35));
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

echo "\n▸ موتور متن فارسی\n";
$check('شکل‌دهی حروف و ادغام لام‌الف', function () {
    $out = \Nikto\Text\Persian::prepare('سلام');
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

echo "\n▸ تاریخ شمسی\n";
$check('۱ فروردین ۱۴۰۵', function () {
    $out = \Nikto\Core\Jalali::format(new DateTimeImmutable('2026-03-21'), 'j F Y');
    return $out === '1 فروردین 1405' ? true : $out;
});
$check('سال کبیسه (۳۰ اسفند)', function () {
    $out = \Nikto\Core\Jalali::format(new DateTimeImmutable('2026-03-20'), 'j F Y');
    return $out === '30 اسفند 1404' ? true : $out;
});

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

$check('فیوچرز: فقط قراردادهای دائمی فعال و پرحجم رتبه می‌گیرند', function () {
    $r = \Nikto\Data\FuturesProvider::movers(5, 5e6, false);
    $all = array_merge(array_column($r['gainers'], 'pair'), array_column($r['losers'], 'pair'));
    $traps = array_intersect($all, ['DEADUSDT', 'BTCUSDT_251226', 'ETHUSDC', 'TINYUSDT', 'ZEROUSDT']);
    if ($traps !== []) {
        return 'رتبه گرفتند: ' . implode(', ', $traps);
    }
    return $r['gainers'][0]['symbol'] === 'WIF' && $r['losers'][0]['symbol'] === 'PEOPLE'
        && count($r['gainers']) === 5 && count($r['losers']) === 5
        ? true
        : 'ترتیب اشتباه: ' . $r['gainers'][0]['symbol'] . ' / ' . $r['losers'][0]['symbol'];
});
$check('فیوچرز: بدون exchangeInfo هم قرارداد سررسیددار و USDC کنار می‌روند', function () {
    $r = \Nikto\Data\FuturesProvider::rank(\Nikto\Data\Mock::futuresTickers(), null, 5, 5e6);
    $all = array_merge(array_column($r['gainers'], 'pair'), array_column($r['losers'], 'pair'));
    return array_intersect($all, ['BTCUSDT_251226', 'ETHUSDC', 'TINYUSDT', 'ZEROUSDT']) === [] ? true : implode(',', $all);
});
$check('فیوچرز: بازار کوچک — هیچ ارزی در هر دو ستون نمی‌آید', function () {
    $r = \Nikto\Data\FuturesProvider::rank(array_slice(\Nikto\Data\Mock::futuresTickers(), 0, 6), null, 5, 0);
    $both = array_intersect(array_column($r['gainers'], 'pair'), array_column($r['losers'], 'pair'));
    return $both === [] && $r['breadth']['total'] === 6 ? true : 'تکرار: ' . implode(',', $both);
});
$check('فیوچرز: نماد لوگو بدون پیشوند ضریب', function () {
    $f = [\Nikto\Data\FuturesProvider::class, 'logoSymbol'];
    return $f('1000PEPE') === 'PEPE' && $f('1MBABYDOGE') === 'BABYDOGE' && $f('BTC') === 'BTC' && $f('1INCH') === '1INCH'
        ? true
        : 'نتیجه: ' . $f('1000PEPE') . ' ' . $f('1INCH');
});
$check('نقدینگی: دیوارهای کاشته‌شده دقیقاً پیدا می‌شوند', function () {
    $r = \Nikto\Data\LiquidityProvider::btc(5);
    $asks = array_map('intval', array_column($r['ask_walls'], 'price'));
    $bids = array_map('intval', array_column($r['bid_walls'], 'price'));
    sort($asks);
    rsort($bids);
    $wantAsks = [64600, 65000, 65400, 66000, 66400];
    $wantBids = [63800, 63500, 63100, 62800, 62500];
    if ($asks !== $wantAsks || $bids !== $wantBids) {
        return 'فروش: ' . implode(',', $asks) . ' | خرید: ' . implode(',', $bids);
    }
    foreach ($r['ask_walls'] as $w) {
        if ($w['price'] <= $r['mid']) {
            return 'دیوار فروش زیر قیمت';
        }
    }
    foreach ($r['bid_walls'] as $w) {
        if ($w['price'] >= $r['mid']) {
            return 'دیوار خرید بالای قیمت';
        }
    }
    return $r['imbalance'] > 0 && $r['imbalance'] < 1 && count($r['candles']) === 48 ? true : 'توازن یا نمودار نادرست';
});
$check('نقدینگی: فقط سطح‌های پرحجم و از هم جدا', function () {
    $r = \Nikto\Data\LiquidityProvider::btc(6);
    $qtys = array_column($r['profile']['asks'], 1);
    sort($qtys);
    $median = $qtys[intdiv(count($qtys), 2)];
    foreach (['ask_walls', 'bid_walls'] as $side) {
        $prices = array_column($r[$side], 'price');
        sort($prices);
        for ($i = 1; $i < count($prices); $i++) {
            if ($prices[$i] - $prices[$i - 1] < $r['bucket'] * 2.5) {
                return 'دو دیوار چسبیده: ' . $prices[$i - 1] . ' و ' . $prices[$i];
            }
        }
    }
    foreach ($r['ask_walls'] as $w) {
        if ($w['qty'] < $median * 1.5) {
            return 'سطح کم‌حجم: ' . $w['price'];
        }
    }
    return true;
});
$check('نقدینگی: دفتر خراب رد می‌شود و یک منبع هم کافی است', function () {
    $bad = \Nikto\Data\LiquidityProvider::analyze(['x' => ['bids' => [], 'asks' => []], 'y' => ['code' => -1121]], []);
    $one = \Nikto\Data\LiquidityProvider::analyze(['Binance Spot' => \Nikto\Data\Mock::btcDepth('spot')], []);
    return $bad === null && $one !== null && $one['sources'] === ['Binance Spot'] && $one['price'] === $one['mid']
        ? true
        : 'رفتار نادرست با دفتر ناقص';
});
$check('نقدینگی: گام بازه‌ها خوش‌رقم است', function () {
    $f = [\Nikto\Data\LiquidityProvider::class, 'niceStep'];
    return $f(64.2) === 100.0 && $f(98.0) === 100.0 && $f(23.0) === 25.0 && $f(3.1) === 5.0 && $f(0.12) === 0.25
        ? true
        : sprintf('%s %s %s', $f(64.2), $f(23.0), $f(0.12));
});

echo "\n▸ ساخت کارت‌ها\n";
foreach (Registry::keys() as $key) {
    $check('کارت ' . $key, function () use ($key) {
        $job = Registry::refresh($key);
        $data = $job->fetch();
        $path = $job->card($data)->save(APP_STORAGE . '/cards/selftest-' . $key . '.png');
        $size = filesize($path);
        [$w, $h] = getimagesize($path);

        return ($size > 20000 && in_array($w, [1200, 1800, 2400, 3000, 3600], true) && $h > 400 && $h < $w)
            ? true
            : sprintf('ابعاد %dx%d حجم %d', $w, $h, $size);
    });
}
$check('چیدمان کارت‌ها بدون هم‌پوشانی', function () {
    exec('php ' . escapeshellarg(APP_ROOT . '/tools/layout-check.php') . ' 2>&1', $lines, $status);
    if ($status === 0) {
        return true;
    }
    $found = array_values(array_filter($lines, static fn ($l) => str_contains($l, '•')));

    return implode(' | ', array_slice($found, 0, 3)) ?: 'بازرسی چیدمان شکست خورد';
});
$check('کپشن با متغیرها', function () {
    $job = Registry::refresh('prices');
    $caption = $job->renderCaption($job->fetch());
    return str_contains($caption, 'BTC') && !str_contains($caption, '{') ? true : $caption;
});

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
    $panel->handleUpdate($callback('j:prices:th:mono'));
    return Registry::refresh('prices')->theme() === 'mono' ? true : 'تم عوض نشد';
});
$check('تم نامعتبر ذخیره نمی‌شود', function () use ($panel, $callback) {
    $panel->handleUpdate($callback('j:prices:th:hacked'));
    $raw = (string) (Db::one("SELECT theme FROM jobs WHERE key = 'prices'")['theme'] ?? '');
    return $raw === 'mono' ? true : 'ذخیره شد: ' . $raw;
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
$check('تم‌های قدیمی ذخیره‌شده (neo، light، mint) به تم شیشه‌ای جدید نگاشت می‌شوند', function () {
    $seen = [];
    foreach (['neo', 'light', 'mint'] as $old) {
        Db::exec('UPDATE jobs SET theme = :t WHERE key = :k', [':t' => $old, ':k' => 'prices']);
        $seen[$old] = Registry::refresh('prices')->theme();
    }
    Registry::refresh('prices')->setTheme(\Nikto\Render\Theme::DEFAULT);

    return array_unique(array_values($seen)) === ['glass'] ? true : json_encode($seen);
});
$check('توکن نمونه (PUT-YOUR-BOT-TOKEN-HERE) تنظیم‌شده حساب نمی‌شود', function () {
    $real = Config::token();
    Config::set('bot_token', 'PUT-YOUR-BOT-TOKEN-HERE');
    $placeholder = Config::isConfigured();
    Config::set('bot_token', $real);

    return !$placeholder && Config::isConfigured() ? true : 'تشخیص توکن اشتباه است';
});
$check('دستور /cancel ورودی نیمه‌کاره را واقعاً لغو می‌کند', function () use ($panel, $callback, $message) {
    $job = Registry::refresh('prices');
    $before = $job->caption();
    $panel->handleUpdate($callback('j:prices:cap'));
    $panel->handleUpdate($message('/cancel'));
    $panel->handleUpdate($message('این نباید کپشن شود'));
    $after = Registry::refresh('prices')->caption();

    return $after === $before ? true : 'کپشن عوض شد: ' . $after;
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
    $panel->handleUpdate($message('/start'));
    $api->calls = [];
    $panel->handleUpdate($callback('j:prices'));
    $panel->handleUpdate($callback('j:prices:sc'));
    $panel->handleUpdate($callback('j:prices:sc:add'));
    $panel->handleUpdate($message('07:15'));
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
$check('تنظیمات کارت فیوچرز و نقدینگی (با رد مقدار نامعتبر)', function () use ($panel, $callback) {
    $panel->handleUpdate($callback('j:movers:cnt:4'));
    $panel->handleUpdate($callback('j:movers:vol:20000000'));
    $panel->handleUpdate($callback('j:movers:cnt:99'));
    $panel->handleUpdate($callback('j:movers:vol:7'));
    $panel->handleUpdate($callback('j:liquidity:lvl:3'));
    $panel->handleUpdate($callback('j:liquidity:lvl:40'));
    $m = Registry::refresh('movers');
    $l = Registry::refresh('liquidity');
    $ok = $m->count() === 4 && (int) $m->minVolume() === 20000000 && $l->levels() === 3;
    $m->setOption('count', 5);
    $m->setOption('min_volume', 5000000);
    $l->setOption('levels', 5);
    return $ok ? true : sprintf('تعداد %d، حجم %d، سطح %d', $m->count(), $m->minVolume(), $l->levels());
});
$check('هیچ ایموجی‌ای در پنل نیست', function () {
    $source = (string) file_get_contents(APP_SRC . '/Telegram/Panel.php');
    $pattern = '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{2190}-\x{21FF}]/u';

    return preg_match($pattern, $source) === 0 ? true : 'ایموجی پیدا شد';
});
$check('پیمایش همه‌ی صفحه‌ها بدون خطا', function () use ($panel, $callback) {
    $routes = ['m', 'ch', 's', 's:adm', 'st', 'lg', 'hp', 'j:prices', 'j:prices:th', 'j:prices:ch',
        'j:prices:sc', 'j:prices:opt', 'j:calendar', 'j:calendar:opt', 'j:feargreed', 's:imp', 's:psrc', 's:csrc',
        'j:movers', 'j:movers:opt', 'j:movers:sc', 'j:movers:th', 'j:liquidity', 'j:liquidity:opt', 'j:liquidity:ch'];
    foreach ($routes as $route) {
        $panel->handleUpdate($callback($route));
    }
    return true;
});

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
    Scheduler::tick();
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
$check('قطعی لحظه‌ای: دقیقه‌ی بعد دوباره فرستاده می‌شود (فقط یک بار)', function () use ($api) {
    $flaky = new class extends Api {
        public int $attempts = 0;
        public int $delivered = 0;
        public function __construct() {}
        public function call(string $method, array $params = [], int $timeout = 60): array
        {
            if ($method === 'sendPhoto' || $method === 'sendDocument') {
                if (++$this->attempts === 1) {
                    return ['ok' => false, 'description' => 'Connection timed out'];
                }
                $this->delivered++;
            }
            return ['ok' => true, 'result' => ['message_id' => 1]];
        }
    };
    Db::exec('DELETE FROM schedules');
    Db::exec('DELETE FROM runs');
    $slot = Settings::now()->setTime(9, 0);
    Db::exec(
        'INSERT INTO schedules(job_key, at_time, days, enabled, created_at) VALUES(:j, :t, :d, 1, :c)',
        [':j' => 'prices', ':t' => '09:00', ':d' => '*', ':c' => time()]
    );

    Dispatcher::setApi($flaky);
    Scheduler::tick($slot);
    Scheduler::tick($slot->modify('+1 minute'));
    Scheduler::tick($slot->modify('+2 minutes'));
    Dispatcher::setApi($api);

    return $flaky->attempts === 2 && $flaky->delivered === 1
        ? true
        : sprintf('تلاش: %d، ارسال موفق: %d', $flaky->attempts, $flaky->delivered);
});
$check('خرابی دائمی: بیش از سه بار تلاش نمی‌شود', function () use ($api) {
    $down = new class extends Api {
        public int $attempts = 0;
        public function __construct() {}
        public function call(string $method, array $params = [], int $timeout = 60): array
        {
            if ($method === 'sendPhoto' || $method === 'sendDocument') {
                $this->attempts++;
                return ['ok' => false, 'description' => 'Bad Gateway'];
            }
            return ['ok' => true, 'result' => []];
        }
    };
    Db::exec('DELETE FROM runs');
    $slot = Settings::now()->setTime(9, 0);
    Dispatcher::setApi($down);
    for ($i = 0; $i < 8; $i++) {
        Scheduler::tick($slot->modify("+{$i} minutes"));
    }
    Dispatcher::setApi($api);

    return $down->attempts === Scheduler::MAX_ATTEMPTS ? true : 'تلاش‌ها: ' . $down->attempts;
});
$check('زمان نزدیک نیمه‌شب اگر سرور دیر بیدار شود از دست نمی‌رود', function () {
    Db::exec('DELETE FROM schedules');
    Db::exec('DELETE FROM runs');
    Db::exec(
        'INSERT INTO schedules(job_key, at_time, days, enabled, created_at) VALUES(:j, :t, :d, 1, :c)',
        [':j' => 'prices', ':t' => '23:58', ':d' => '*', ':c' => time()]
    );
    $afterMidnight = Settings::now()->setTime(0, 3);
    $due = Scheduler::due($afterMidnight, 10);
    $expected = $afterMidnight->modify('-1 day')->format('Y-m-d') . ' 23:58';

    return count($due) === 1 && $due[0]['slot'] === $expected
        ? true
        : 'سررسیدها: ' . json_encode($due, JSON_UNESCAPED_UNICODE);
});
$check('ارسال کارت فیوچرز و نقدینگی با کپشن', function () use ($api) {
    $before = $api->countOf('sendPhoto');
    $a = Dispatcher::run('movers');
    $b = Dispatcher::run('liquidity');
    $caption = '';
    foreach (array_reverse($api->calls) as $call) {
        if ($call['method'] === 'sendPhoto') {
            $caption = (string) ($call['params']['caption'] ?? '');
            break;
        }
    }
    return $a['ok'] && $b['ok'] && $api->countOf('sendPhoto') === $before + 2
        && str_contains($caption, 'مقاومت') && str_contains($caption, '65,000')
        ? true
        : ($a['message'] ?? '') . ' | ' . ($b['message'] ?? '') . ' | ' . mb_substr($caption, 0, 80);
});
$check('ارسال دستی به کانال', function () use ($api) {
    $before = $api->countOf('sendPhoto');
    $result = Dispatcher::run('feargreed');

    return ($result['ok'] && $api->countOf('sendPhoto') === $before + 1) ? true : $result['message'];
});

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

echo "\n" . str_repeat('─', 52) . "\n";
printf("نتیجه: %d موفق، %d ناموفق\n\n", $passed, $failed);

@unlink($tmpDb);
foreach (glob(sys_get_temp_dir() . '/nikto-selftest-*') ?: [] as $file) {
    @unlink($file);
}

exit($failed === 0 ? 0 : 1);

<?php
/**
 * ساخت نسخه‌ی «دو فایلی» برای هاست اشتراکی.
 *
 *   php tools/build.php                     خروجی در پوشه‌ی build/
 *   php tools/build.php --out=/tmp/x        مسیر خروجی دلخواه
 *   php tools/build.php --with-secrets      توکن فعلی را داخل فایل بنویس
 *   php tools/build.php --name=mybot        نام فایل‌ها (پیش‌فرض nikto)
 *
 * خروجی:
 *   <name>-bot.php    همه‌ی کدها + فونت‌ها + لوگوها (وب‌هوک، پنل و خط فرمان)
 *   <name>-cron.php   زمان‌بند (کرون سروری یا کرون اینترنتی)
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Bundle\Runtime;
use Nikto\Core\Config;

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $args[$m[1]] = $m[2] ?? '1';
    }
}

$outDir = rtrim($args['out'] ?? (APP_ROOT . '/build'), '/');
$name = preg_replace('/[^a-z0-9_-]/i', '', $args['name'] ?? 'nikto') ?: 'nikto';
$withSecrets = isset($args['with-secrets']);

if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "❌ ساخت پوشه‌ی خروجی ممکن نبود: {$outDir}\n");
    exit(1);
}

echo "\n📦 ساخت نسخه‌ی دو فایلی\n" . str_repeat('─', 48) . "\n";

// ───────────────────────────────────────────── ۱) کدها
$sources = [
    'src/Core/Config.php', 'src/Core/Db.php', 'src/Core/Settings.php', 'src/Core/Http.php',
    'src/Core/Log.php', 'src/Core/Jalali.php',
    'src/Text/Persian.php',
    'src/Data/Coins.php', 'src/Data/Countries.php', 'src/Data/EventTranslator.php',
    'src/Data/PriceProvider.php', 'src/Data/FearGreedProvider.php', 'src/Data/CalendarProvider.php',
    'src/Data/Mock.php',
    'src/Render/Canvas.php', 'src/Render/Theme.php', 'src/Render/Frame.php', 'src/Render/Flags.php',
    'src/Render/CoinLogo.php', 'src/Render/Card.php', 'src/Render/PriceCard.php',
    'src/Render/FearGreedCard.php', 'src/Render/CalendarCard.php',
    'src/Jobs/Job.php', 'src/Jobs/PricesJob.php', 'src/Jobs/FearGreedJob.php', 'src/Jobs/CalendarJob.php',
    'src/Jobs/Registry.php', 'src/Jobs/Dispatcher.php', 'src/Jobs/Scheduler.php',
    'src/Telegram/Api.php', 'src/Telegram/Access.php', 'src/Telegram/State.php',
    'src/Telegram/Channels.php', 'src/Telegram/Panel.php',
    'src/Bundle/Runtime.php',
];

$code = '';
$classCount = 0;
foreach ($sources as $relative) {
    $path = APP_ROOT . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "❌ فایل پیدا نشد: {$relative}\n");
        exit(1);
    }
    $body = (string) file_get_contents($path);
    $body = preg_replace('/^<\?php\s*/', '', $body, 1) ?? $body;
    $body = preg_replace('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/m', '', $body, 1) ?? $body;

    if (!preg_match('/^\s*namespace\s+([^;]+);\s*/m', $body, $m)) {
        fwrite(STDERR, "❌ فضای‌نام پیدا نشد: {$relative}\n");
        exit(1);
    }
    $namespace = trim($m[1]);
    $body = str_replace($m[0], '', $body);

    $code .= "namespace {$namespace} {\n" . trim($body) . "\n}\n\n";
    $classCount++;
}
printf("  ✅ %d کلاس ادغام شد\n", $classCount);

// ───────────────────────────────────────────── ۲) دارایی‌ها
$assetFiles = [];
$addAsset = static function (string $source, string $target) use (&$assetFiles): void {
    if (!is_file($source)) {
        return;
    }
    $assetFiles[$target] = (string) file_get_contents($source);
};

foreach (['Regular', 'Medium', 'SemiBold', 'Bold', 'Black'] as $weight) {
    $addAsset(APP_ASSETS . "/fonts/Vazirmatn-{$weight}.ttf", "assets/fonts/Vazirmatn-{$weight}.ttf");
}
foreach (['color', 'white'] as $variant) {
    foreach (glob(APP_ASSETS . "/coins/{$variant}/*.png") ?: [] as $logo) {
        $addAsset($logo, "assets/coins/{$variant}/" . basename($logo));
    }
}
foreach (glob(APP_FIXTURES . '/*.json') ?: [] as $fixture) {
    $addAsset($fixture, 'fixtures/' . basename($fixture));
}

$rawBytes = array_sum(array_map('strlen', $assetFiles));
$assetsPhp = '';
foreach ($assetFiles as $target => $bytes) {
    $packed = base64_encode((string) gzdeflate($bytes, 9));
    $assetsPhp .= "            " . var_export($target, true) . " => '" . $packed . "',\n";
}
printf(
    "  ✅ %d فایل دارایی (%s کیلوبایت خام) فشرده شد\n",
    count($assetFiles),
    number_format($rawBytes / 1024, 0)
);

$assetsVersion = substr(sha1(implode('', array_keys($assetFiles)) . $rawBytes), 0, 12);
$assetsClass = <<<PHP
namespace Nikto\\Bundle {
    /** فونت‌ها، لوگوها و داده‌های نمونه — فشرده‌شده داخل همین فایل */
    final class Assets
    {
        public const VERSION = '{$assetsVersion}';

        /** @return array<string,string> */
        public static function files(): array
        {
            \$packed = self::PACKED;
            \$out = [];
            foreach (\$packed as \$path => \$blob) {
                \$raw = @gzinflate((string) base64_decode(\$blob, true));
                if (\$raw !== false) {
                    \$out[\$path] = base64_encode(\$raw);
                }
            }

            return \$out;
        }

        private const PACKED = [
{$assetsPhp}        ];
    }
}


PHP;

// ───────────────────────────────────────────── ۳) تنظیمات بالای فایل
$token  = $withSecrets ? (string) Config::token() : 'PUT-YOUR-BOT-TOKEN-HERE';
$owner  = (int) Config::get('owner_id', 0);
$secret = (string) Config::get('webhook_secret', '') ?: bin2hex(random_bytes(16));
$webhook = (string) Config::get('webhook_url', '');
if ($webhook !== '') {
    $webhook = preg_replace('~/[^/]*$~', '/' . $name . '-bot.php', $webhook) ?? $webhook;
}
$timezone = (string) Config::get('timezone', 'Asia/Tehran');
$brand = (string) Config::get('brand', 'NIKTO CRYPTO');

$header = <<<PHP
<?php

/**
 * ███ NIKTO CRYPTO BOT — نسخه‌ی تک‌فایلی (نسخه {$name}) ███
 *
 * این فایل شامل همه‌ی کدها، فونت فارسی و لوگوی ارزهاست.
 * فقط همین فایل و {$name}-cron.php را روی هاست آپلود کنید.
 *
 * اجرا:
 *   • وب‌هوک:   آدرس همین فایل را با «php {$name}-bot.php webhook» ثبت کنید
 *   • یا پولینگ: php {$name}-bot.php
 *   • زمان‌بند:  php {$name}-cron.php   (یا کرون اینترنتی روی همان فایل)
 *
 * ساخته‌شده در %BUILD_DATE% — نسخه‌ی هسته %CORE_VERSION%
 */

declare(strict_types=1);

// ═══════════════════ تنظیمات (فقط این بخش را ویرایش کنید) ═══════════════════

namespace {

const NIKTO_CONFIG = [
    // توکن ربات از @BotFather
    'bot_token' => '{$token}',

    // شناسه‌ی عددی مدیر اصلی (با دستور /id در ربات دیده می‌شود)
    'owner_id'  => {$owner},

    // مدیران بیشتر: [111111111, 222222222]
    'admins'    => [],

    // آدرس عمومی همین فایل روی دامنه (برای حالت وب‌هوک)
    'webhook_url'    => '{$webhook}',

    // کلید امنیتی وب‌هوک و کرون اینترنتی — عوضش نکنید مگر اینکه بخواهید
    'webhook_secret' => '{$secret}',

    'timezone'  => '{$timezone}',
    'brand'     => '{$brand}',

    // در صورت نیاز به پروکسی: 'socks5h://127.0.0.1:1080'
    'http_proxy' => '',
];

}

// ════════════════════════ از اینجا به بعد را دست نزنید ════════════════════════


PHP;

$header = str_replace(
    ['%BUILD_DATE%', '%CORE_VERSION%'],
    [date('Y-m-d H:i'), Runtime::VERSION],
    $header
);

$entry = <<<'PHP'
namespace {

    \Nikto\Bundle\Runtime::boot(NIKTO_CONFIG + ['root' => __DIR__]);

    // اگر این فایل از cron فراخوانی شده باشد، فقط نقش کتابخانه را دارد
    if (!defined('NIKTO_LIBRARY')) {
        if (PHP_SAPI === 'cli') {
            exit(\Nikto\Bundle\Runtime::handleCli($argv ?? []));
        }
        \Nikto\Bundle\Runtime::handleWeb();
    }
}

PHP;

$botFile = $header . $assetsClass . $code . $entry;

// ───────────────────────────────────────────── ۴) فایل کرون
$cronFile = <<<PHP
<?php

/**
 * ███ NIKTO CRYPTO BOT — زمان‌بند ███
 *
 * این فایل کنار {$name}-bot.php قرار می‌گیرد و کارت‌ها را سر ساعت می‌فرستد.
 *
 * روش‌های اجرا:
 *   ۱) کرون سرور (هر دقیقه):
 *        * * * * * /usr/bin/php %PATH%/{$name}-cron.php >/dev/null 2>&1
 *   ۲) اجرای دائمی:
 *        php {$name}-cron.php --loop
 *   ۳) کرون اینترنتی (هاست‌هایی که کرون خط فرمان ندارند) — هر دقیقه این آدرس را صدا بزنید:
 *        https://your-domain/{$name}-cron.php?key={$secret}
 */

declare(strict_types=1);

define('NIKTO_LIBRARY', true);

\$bot = __DIR__ . '/{$name}-bot.php';
if (!is_file(\$bot)) {
    exit("فایل {$name}-bot.php کنار این فایل پیدا نشد.\\n");
}
require \$bot;

if (PHP_SAPI === 'cli') {
    \$loop = in_array('--loop', \$argv, true);
    exit(\Nikto\Bundle\Runtime::handleCli(['cron', \$loop ? 'cron:loop' : 'cron']));
}

\Nikto\Bundle\Runtime::handleWebCron();

PHP;
$cronFile = str_replace('%PATH%', '/home/user/public_html', $cronFile);

// ───────────────────────────────────────────── ۵) نوشتن و بررسی
$botPath = $outDir . '/' . $name . '-bot.php';
$cronPath = $outDir . '/' . $name . '-cron.php';
file_put_contents($botPath, $botFile);
file_put_contents($cronPath, $cronFile);

foreach ([$botPath, $cronPath] as $path) {
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) {
        fwrite(STDERR, "❌ خطای نحوی در " . basename($path) . ":\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

printf("  ✅ %s (%s کیلوبایت)\n", basename($botPath), number_format(filesize($botPath) / 1024, 0));
printf("  ✅ %s (%s کیلوبایت)\n", basename($cronPath), number_format(filesize($cronPath) / 1024, 0));
echo str_repeat('─', 48) . "\n";
echo "📁 {$outDir}\n";
echo $withSecrets
    ? "🔐 توکن داخل فایل نوشته شد — فایل را جایی امن نگه دارید.\n\n"
    : "ℹ️  توکن نوشته نشد؛ آن را در بالای فایل bot وارد کنید (یا --with-secrets).\n\n";

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
// همه‌ی فایل‌های src خودکار پیدا می‌شوند تا هیچ کلاسی جا نماند.
$sources = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(APP_SRC, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = 'src/' . ltrim(str_replace(APP_SRC, '', $file->getPathname()), '/\\');
    $relative = str_replace('\\', '/', $relative);
    if ($relative === 'src/bootstrap.php') {
        continue; // بارگذارنده در نسخه‌ی فشرده لازم نیست
    }
    $sources[] = $relative;
}
sort($sources);

$code = '';
$classCount = 0;
$bundledClasses = [];
foreach ($sources as $relative) {
    $path = APP_ROOT . '/' . $relative;
    $body = (string) file_get_contents($path);
    $body = preg_replace('/^<\?php\s*/', '', $body, 1) ?? $body;
    $body = preg_replace('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*/m', '', $body, 1) ?? $body;

    if (!preg_match('/^\s*namespace\s+([^;]+);\s*/m', $body, $m)) {
        fwrite(STDERR, "❌ فضای‌نام پیدا نشد: {$relative}\n");
        exit(1);
    }
    $namespace = trim($m[1]);
    $body = str_replace($m[0], '', $body);

    if (preg_match('/^\s*(?:final\s+|abstract\s+)?(?:class|interface|trait|enum)\s+([A-Za-z_]\w*)/m', $body, $cm)) {
        $bundledClasses[] = $namespace . '\\' . $cm[1];
    }

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
foreach (['Medium', 'SemiBold'] as $weight) {
    $addAsset(APP_ASSETS . "/fonts/NiktoNum-{$weight}.ttf", "assets/fonts/NiktoNum-{$weight}.ttf");
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

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "NIKTO CRYPTO BOT\\n\\n";
    echo "PHP " . PHP_VERSION . " — this bot needs PHP 8.0 or newer.\\n";
    echo "نسخه PHP هاست را از پنل هاست روی 8.1 یا بالاتر بگذارید.\\n";
    exit;
}

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

// بررسی اینکه هیچ کلاسی از قلم نیفتاده باشد
$bundle = (string) file_get_contents($botPath);
$missing = [];
foreach ($bundledClasses as $class) {
    $short = substr((string) strrchr($class, '\\'), 1);
    if (!preg_match('/\b(?:class|interface|trait|enum)\s+' . preg_quote($short, '/') . '\b/', $bundle)) {
        $missing[] = $class;
    }
}
if ($missing !== []) {
    fwrite(STDERR, "❌ این کلاس‌ها در خروجی نیستند:\n  " . implode("\n  ", $missing) . "\n");
    exit(1);
}

// بررسی اینکه هر کلاسی که در کد صدا زده می‌شود واقعاً وجود دارد
$referenced = [];
preg_match_all('/\\\\?(Nikto\\\\[A-Za-z_\\\\]+)::/', $bundle, $refMatches);
foreach ($refMatches[1] as $ref) {
    $referenced[str_replace('\\\\\\\\', '\\\\', $ref)] = true;
}
$unknown = [];
foreach (array_keys($referenced) as $ref) {
    $short = substr((string) strrchr($ref, '\\'), 1);
    if ($short === '' || $short === 'class') {
        continue;
    }
    if (!preg_match('/\b(?:class|interface|trait|enum)\s+' . preg_quote($short, '/') . '\b/', $bundle)) {
        $unknown[] = $ref;
    }
}
if ($unknown !== []) {
    fwrite(STDERR, "❌ به این کلاس‌ها ارجاع داده شده ولی تعریفشان در خروجی نیست:\n  "
        . implode("\n  ", array_unique($unknown)) . "\n");
    exit(1);
}
printf("  ✅ %d کلاس بررسی شد — همه موجودند\n", count($bundledClasses));

printf("  ✅ %s (%s کیلوبایت)\n", basename($botPath), number_format(filesize($botPath) / 1024, 0));
printf("  ✅ %s (%s کیلوبایت)\n", basename($cronPath), number_format(filesize($cronPath) / 1024, 0));
echo str_repeat('─', 48) . "\n";
echo "📁 {$outDir}\n";
echo $withSecrets
    ? "🔐 توکن داخل فایل نوشته شد — فایل را جایی امن نگه دارید.\n\n"
    : "ℹ️  توکن نوشته نشد؛ آن را در بالای فایل bot وارد کنید (یا --with-secrets).\n\n";

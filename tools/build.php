<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

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
$public = isset($args['public']);
if ($public && $withSecrets) {
    fwrite(STDERR, "❌ --public و --with-secrets با هم معنا ندارند.\n");
    exit(1);
}

if (!is_dir($outDir) && !@mkdir($outDir, 0775, true)) {
    fwrite(STDERR, "❌ ساخت پوشه‌ی خروجی ممکن نبود: {$outDir}\n");
    exit(1);
}

echo "\n📦 ساخت نسخه‌ی دو فایلی\n" . str_repeat('─', 48) . "\n";

$sources = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(APP_SRC, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $relative = 'src/' . ltrim(str_replace(APP_SRC, '', $file->getPathname()), '/\\');
    $relative = str_replace('\\', '/', $relative);
    if ($relative === 'src/bootstrap.php') {
        continue;
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
    final class Assets
    {
        public const VERSION = '{$assetsVersion}';

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

$token  = $withSecrets ? (string) Config::token() : 'PUT-YOUR-BOT-TOKEN-HERE';
$owner  = $public ? 0 : (int) Config::get('owner_id', 0);
$secret = $public ? bin2hex(random_bytes(16)) : ((string) Config::get('webhook_secret', '') ?: bin2hex(random_bytes(16)));
$webhook = $public ? '' : (string) Config::get('webhook_url', '');
if ($webhook !== '') {
    $webhook = preg_replace('~/[^/]*$~', '/' . $name . '-bot.php', $webhook) ?? $webhook;
}
$timezone = (string) Config::get('timezone', 'Asia/Tehran');
$brand = (string) Config::get('brand', 'NIKTO CRYPTO');

$header = <<<PHP
<?php
declare(strict_types=1);

namespace {

const NIKTO_CONFIG = [
    'bot_token'      => '{$token}',
    'owner_id'       => {$owner},
    'admins'         => [],
    'webhook_url'    => '{$webhook}',
    'webhook_secret' => '{$secret}',
    'timezone'       => '{$timezone}',
    'brand'          => '{$brand}',
    'http_proxy'     => '',
];

}

PHP;

$entry = <<<'PHP'
namespace {

    \Nikto\Bundle\Runtime::boot(NIKTO_CONFIG + ['root' => __DIR__]);

    if (!defined('NIKTO_LIBRARY')) {
        if (PHP_SAPI === 'cli') {
            exit(\Nikto\Bundle\Runtime::handleCli($argv ?? []));
        }
        \Nikto\Bundle\Runtime::handleWeb();
    }
}

PHP;

$botFile = $header . $assetsClass . $code . $entry;

$cronFile = <<<PHP
<?php

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

$botPath = $outDir . '/' . $name . '-bot.php';
$cronPath = $outDir . '/' . $name . '-cron.php';
$botFile = strip_comments($botFile);
$cronFile = strip_comments($cronFile);
foreach (['bot' => $botFile, 'cron' => $cronFile] as $label => $source) {
    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            fwrite(STDERR, "❌ فایل {$label} هنوز توضیح دارد\n");
            exit(1);
        }
    }
}

file_put_contents($botPath, $botFile);
file_put_contents($cronPath, $cronFile);

foreach ([$botPath, $cronPath] as $path) {
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);
    if ($status !== 0) {
        fwrite(STDERR, "❌ خطای نحوی در " . basename($path) . ":\n" . implode("\n", $output) . "\n");
        exit(1);
    }
}

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

function strip_comments(string $code): string
{
    $out = [];
    $dropNewline = false;
    foreach (token_get_all($code) as $tok) {
        [$id, $text] = is_array($tok) ? [$tok[0], $tok[1]] : [null, $tok];

        if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
            $last = count($out) - 1;
            $prev = $last >= 0 ? $out[$last] : ['code', ''];
            if ($prev[0] === 'ws' && preg_match('/\n[ \t]*$/', $prev[1])) {
                $out[$last][1] = rtrim($prev[1], " \t");
                $dropNewline = !str_ends_with($text, "\n");
            } else {
                if ($prev[0] === 'ws') {
                    $out[$last][1] = rtrim($prev[1], " \t");
                }
                if (str_ends_with($text, "\n")) {
                    $out[] = ['ws', "\n"];
                }
                $dropNewline = false;
            }
            continue;
        }

        if ($id === T_WHITESPACE) {
            if ($dropNewline) {
                $text = (string) preg_replace('/^[ \t]*\n/', '', $text, 1);
                $dropNewline = false;
            }
            $last = count($out) - 1;
            if ($last >= 0 && $out[$last][0] === 'ws') {
                $out[$last][1] .= $text;
            } else {
                $out[] = ['ws', $text];
            }
            continue;
        }

        $dropNewline = false;
        $kind = in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_START_HEREDOC, T_END_HEREDOC, T_INLINE_HTML], true)
            ? 'str' : 'code';
        $out[] = [$kind, $text];
    }

    $result = '';
    foreach ($out as $i => [$kind, $text]) {
        if ($kind === 'ws') {
            $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);
            $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
            $prev = $out[$i - 1][1] ?? '';
            $next = $out[$i + 1][1] ?? '';
            if (str_ends_with($prev, '{') || str_ends_with($prev, '[') || str_ends_with($prev, '(')) {
                $text = (string) preg_replace('/^\n\n+/', "\n", $text);
            }
            if (str_starts_with($next, '}') || str_starts_with($next, ']') || str_starts_with($next, ')')) {
                $text = (string) preg_replace('/\n\n+([ \t]*)$/', "\n$1", $text);
            }
        }
        $result .= $text;
    }

    return $result;
}

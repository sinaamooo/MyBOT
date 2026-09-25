<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Jobs\Registry;

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z_]+)=(.*)$/', $arg, $m)) {
        $args[$m[1]] = $m[2];
    }
}

$target = APP_ROOT . '/config.local.php';
$ask = static function (string $question, string $default = ''): string {
    if (!stream_isatty(STDIN)) {
        return $default;
    }
    echo $question . ($default !== '' ? " [{$default}]" : '') . ': ';
    $answer = trim((string) fgets(STDIN));

    return $answer !== '' ? $answer : $default;
};

echo "\n⚙️  نصب NIKTO CRYPTO BOT\n" . str_repeat('─', 46) . "\n\n";

if (is_file($target) && !isset($args['force'])) {
    $overwrite = $ask('فایل config.local.php از قبل وجود دارد. بازنویسی شود؟ (y/n)', 'n');
    if (strtolower($overwrite) !== 'y') {
        echo "لغو شد. برای بازنویسی از --force استفاده کنید.\n";
        exit(0);
    }
}

$token = $args['token'] ?? $ask('توکن ربات (از @BotFather)');
$owner = $args['owner'] ?? (string) (Config::get('owner_id', 0) ?: $ask('شناسه‌ی عددی مالک ربات'));
$tz    = $args['timezone'] ?? (string) Config::get('timezone', 'Asia/Tehran');
$brand = $args['brand'] ?? (string) Config::get('brand', 'NIKTO CRYPTO');
$proxy = $args['proxy'] ?? '';
$domain = $args['domain'] ?? '';
$webhookUrl = $domain === ''
    ? (string) Config::get('webhook_url', '')
    : 'https://' . preg_replace('~^https?://|/.*$~', '', $domain) . '/webhook.php';

if (!preg_match('/^\d+:[A-Za-z0-9_\-]{20,}$/', trim($token))) {
    fwrite(STDERR, "\n❌ توکن نامعتبر است. قالب درست: 123456789:AAH...\n");
    exit(1);
}

$botId = (int) strtok(trim($token), ':');

$content = "<?php\n\n"
    . "// ⚠️ این فایل محرمانه است و نباید در گیت ذخیره شود (در .gitignore قرار دارد).\n"
    . "// ساخته‌شده توسط tools/install.php در " . date('Y-m-d H:i:s') . "\n"
    . "// مقادیر اینجا روی config.php را بازنویسی می‌کنند.\n\n"
    . "return [\n"
    . "    'bot_token' => " . var_export(trim($token), true) . ",\n"
    . "    'bot_id'    => " . $botId . ",\n"
    . "    'owner_id'  => " . (int) $owner . ",\n"
    . "    'admins'    => [" . (int) $owner . "],\n"
    . "    'webhook_url'    => " . var_export($webhookUrl, true) . ",\n"
    . "    'webhook_secret' => " . var_export(bin2hex(random_bytes(16)), true) . ",\n"
    . "    'http_proxy' => " . var_export($proxy, true) . ",\n"
    . "];\n";

if (file_put_contents($target, $content) === false) {
    fwrite(STDERR, "❌ نوشتن config.local.php ناموفق بود.\n");
    exit(1);
}
@chmod($target, 0600);

Db::migrate();
Config::set('timezone', $tz);
\Nikto\Core\Settings::set('timezone', $tz);
\Nikto\Core\Settings::set('brand', $brand);
Registry::all();

echo "\n✅ config.local.php ساخته شد.\n";
echo "   شناسه‌ی ربات: {$botId}\n";
echo "   مالک: {$owner}\n\n";
if ($webhookUrl !== '') {
    echo "   وب‌هوک: {$webhookUrl}\n\n";
}
echo "گام بعدی:\n";
echo "  1) php tools/doctor.php        بررسی سلامت\n";
echo "  2) php bot.php                 روشن‌کردن ربات (یا ثبت وب‌هوک)\n";
echo "  3) php scheduler.php           روشن‌کردن زمان‌بند\n\n";

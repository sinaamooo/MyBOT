<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Telegram\Api;

$api = new Api();
$configured = (string) Config::get('webhook_url', '');
$arg = $argv[1] ?? ($configured !== '' ? $configured : '--info');

if ($arg === '--url') {
    echo $configured !== '' ? $configured . "\n" : "در config.php مقدار webhook_url تنظیم نشده است.\n";
    exit;
}

if ($arg === '--delete') {
    $res = $api->deleteWebhook();
    echo ($res['ok'] ?? false) ? "✅ وب‌هوک حذف شد.\n" : '❌ ' . ($res['description'] ?? 'خطا') . "\n";
    exit;
}

if ($arg === '--info') {
    $res = $api->getWebhookInfo();
    echo json_encode($res['result'] ?? $res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    exit;
}

if (!filter_var($arg, FILTER_VALIDATE_URL) || !str_starts_with($arg, 'https://')) {
    fwrite(STDERR, "❌ آدرس باید یک URL معتبر با https باشد.\n");
    exit(1);
}

$res = $api->setWebhook($arg, (string) Config::get('webhook_secret', ''));
if ($res['ok'] ?? false) {
    echo "✅ وب‌هوک تنظیم شد:\n   {$arg}\n\n";
    echo "از این پس نیازی به اجرای bot.php نیست؛ فقط scheduler.php باید روشن بماند.\n";
} else {
    echo '❌ ' . ($res['description'] ?? 'خطا') . "\n";
    exit(1);
}

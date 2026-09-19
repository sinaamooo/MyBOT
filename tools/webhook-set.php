<?php
/**
 * تنظیم یا حذف وب‌هوک
 *
 *   php tools/webhook-set.php https://example.com/webhook.php
 *   php tools/webhook-set.php --delete
 *   php tools/webhook-set.php --info
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Telegram\Api;

$api = new Api();
$arg = $argv[1] ?? '--info';

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
echo ($res['ok'] ?? false)
    ? "✅ وب‌هوک تنظیم شد: {$arg}\n"
    : '❌ ' . ($res['description'] ?? 'خطا') . "\n";

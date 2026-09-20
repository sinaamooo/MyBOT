<?php
/**
 * NIKTO CRYPTO BOT — نقطه‌ی ورود وب‌هوک (جایگزین bot.php برای هاست‌های وب)
 *
 * تنظیم:
 *   php tools/webhook-set.php https://example.com/webhook.php
 */
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Log;
use Nikto\Core\PublicUrl;
use Nikto\Telegram\Api;
use Nikto\Telegram\Panel;

// درخواست معمولی مرورگر: نمایش آدرس درست وب‌هوک
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(200);
    exit(PublicUrl::statusPage());
}

$secret = (string) Config::get('webhook_secret', '');
if ($secret !== '' && ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== $secret) {
    http_response_code(403);
    exit('forbidden');
}

$raw = file_get_contents('php://input') ?: '';
$update = json_decode($raw, true);
if (!is_array($update)) {
    http_response_code(400);
    exit('bad request');
}

http_response_code(200);
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

try {
    Db::migrate();
    (new Panel(new Api()))->handleUpdate($update);
} catch (\Throwable $e) {
    Log::error('Webhook handling failed', ['error' => $e->getMessage()]);
}

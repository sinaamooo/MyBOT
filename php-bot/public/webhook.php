<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\AppFactory;
use App\Config;
use App\Telegram\UpdateHandler;

// Verify the request really came from Telegram (see setup_webhook.php).
$expectedSecret = Config::env('TELEGRAM_WEBHOOK_SECRET', '');
if ($expectedSecret) {
    $received = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (!hash_equals($expectedSecret, $received)) {
        http_response_code(403);
        exit;
    }
}

$raw = file_get_contents('php://input');
$update = json_decode((string) $raw, true);

// Acknowledge Telegram immediately where supported, then keep processing -
// Telegram only needs the HTTP 200, it doesn't wait for our work to finish.
if (function_exists('fastcgi_finish_request')) {
    http_response_code(200);
    echo 'OK';
    fastcgi_finish_request();
} else {
    http_response_code(200);
}

if (is_array($update)) {
    $ctx = AppFactory::buildContext();
    (new UpdateHandler($ctx))->handle($update);
}

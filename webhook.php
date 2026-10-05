<?php
/**
 * Telegram webhook endpoint. Point the bot's webhook here (tools/set_webhook.php).
 */
require __DIR__ . '/src/bootstrap.php';

$config = app_config();

$secret = (string) ($config['webhook_secret'] ?? '');
if ($secret !== '' && !hash_equals($secret, (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
    http_response_code(403);
    exit('forbidden');
}

$update = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($update)) {
    http_response_code(200);
    exit('ok');
}

// Answer Telegram right away; an analysis can take up to a minute.
ignore_user_abort(true);
set_time_limit(300);
http_response_code(200);
header('Content-Type: text/plain');
header('Content-Length: 2');
header('Connection: close');
echo 'ok';
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} elseif (function_exists('litespeed_finish_request')) {
    litespeed_finish_request();
} else {
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

try {
    (new App\Bot($config))->handle($update);
} catch (Throwable $e) {
    app_log('webhook error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
}

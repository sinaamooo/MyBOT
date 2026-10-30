<?php

require __DIR__ . '/src/bootstrap.php';

DB::connect();
$secret = (string)DB::kvGet('webhook_secret', '');
if ($secret !== '' && !hash_equals($secret, (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
    http_response_code(403);
    exit;
}
$update = json_decode((string)file_get_contents('php://input'), true);
ignore_user_abort(true);
set_time_limit(180);
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
if (!is_array($update) || !isset($update['update_id'])) {
    exit;
}
if (Cache::throttled('upd:' . $update['update_id'], 86400)) {
    exit;
}
Bot::handle($update);

<?php

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/data/php-error.log');
ob_start();
require __DIR__ . '/src/bootstrap.php';

DB::connect();
$secret = (string)DB::kvGet('webhook_secret', '');
if ($secret !== '' && !hash_equals($secret, (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
    if (!Cache::throttled('secret_mismatch', 300)) {
        error_log('webhook secret mismatch: open setup.php?key=... again to register the webhook');
    }
    http_response_code(403);
    exit;
}
$update = json_decode((string)file_get_contents('php://input'), true);
ignore_user_abort(true);
set_time_limit(180);
TG::$hook = true;
register_shutdown_function([TG::class, 'closeHook']);
if (!is_array($update) || !isset($update['update_id']) || Cache::throttled('upd:' . $update['update_id'], 86400)) {
    TG::closeHook();
    exit;
}
Bot::handle($update);
TG::closeHook();
if (Tick::due()) {
    Tick::run();
}

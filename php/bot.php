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
TG::$hook = true;
register_shutdown_function([TG::class, 'closeHook']);
if (!is_array($update) || !isset($update['update_id']) || Cache::throttled('upd:' . $update['update_id'], 86400)) {
    TG::closeHook();
    exit;
}
Bot::handle($update);
TG::closeHook();

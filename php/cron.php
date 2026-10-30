<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
require __DIR__ . '/src/bootstrap.php';

$lock = fopen(sys_get_temp_dir() . '/mybot_cron_' . md5(__DIR__) . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit;
}
set_time_limit(110);
DB::connect();
Bot::processDeletes();
try {
    $n = Whales::run();
    if ($n) {
        echo date('c') . " whale alerts: $n\n";
    }
} catch (Throwable $e) {
    error_log('cron: ' . $e->getMessage());
}
try {
    Market::refresh([], true, true);
} catch (Throwable $e) {
    error_log('cron market: ' . $e->getMessage());
}
if (time() - (int)DB::kvGet('emoji_checked_at', 0) > 86400) {
    UI::validateEmoji();
}
if (time() - (int)DB::kvGet('last_cleanup', 0) > 6 * 3600) {
    DB::cleanup();
    DB::kvSet('last_cleanup', time());
}
Bot::processDeletes();
flock($lock, LOCK_UN);

<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/data/php-error.log');
require __DIR__ . '/src/bootstrap.php';

set_time_limit(110);
DB::connect();
$end = time() + 55;
do {
    $t = microtime(true);
    Tick::run();
    $wait = 15 - (microtime(true) - $t);
    if ($wait > 0 && time() + $wait < $end) {
        usleep((int)($wait * 1000000));
    }
} while (time() < $end - 5);

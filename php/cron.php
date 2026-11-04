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
Tick::run();

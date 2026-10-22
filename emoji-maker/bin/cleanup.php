<?php

declare(strict_types=1);

/*
 * Manual cleanup: expired outputs (output_retention_days), temporary work
 * directories, orphan files, old updates / rate limits / jobs, old logs and
 * admin sessions. Prints the report as JSON.
 *
 *   php bin/cleanup.php
 */

use EmojiMaker\App;
use EmojiMaker\Maintenance\Cleanup;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/autoload.php';

$app = App::boot();
$app->registerErrorHandlers(true);
if (!$app->dbAvailable()) {
    fwrite(STDERR, "Database is not reachable\n");
    exit(2);
}
$report = (new Cleanup($app))->run();
@file_put_contents($app->storage()->root() . '/cache/last_cleanup', (string) time());
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";

<?php

declare(strict_types=1);

/*
 * Single cron entry for shared hosting (cPanel "Cron Jobs", every minute):
 *
 *   * * * * * /usr/local/bin/php /home/USER/emoji-maker/bin/cron.php >/dev/null 2>&1
 *
 * Works on queued jobs for up to ~55 seconds and runs the hourly cleanup.
 * Several overlapping runs are safe: the queue limits parallel jobs itself.
 */

use EmojiMaker\App;
use EmojiMaker\Queue\Worker;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/autoload.php';

$app = App::boot();
$app->registerErrorHandlers(true);
@ini_set('memory_limit', (string) $app->config()->get('worker.memory_limit', '512M'));
@set_time_limit(0);
if (!$app->dbAvailable()) {
    exit(2);
}
$limit = 55;
foreach ($argv as $a) {
    if (str_starts_with($a, '--time-limit=')) {
        $limit = max(10, (int) substr($a, 13));
    }
}
$worker = new Worker($app, ['once' => true, 'time_limit' => $limit, 'maintenance' => true]);
$worker->installSignals();
$worker->hourlyCleanup();
exit($worker->run());

<?php

declare(strict_types=1);

/**
 * Send worker: drains the queue, starts scheduled campaigns, reports progress.
 *
 *   php bin/worker.php
 *
 * Safe to run more than one instance — queue rows are claimed atomically.
 */

use MyBot\App;

$root = \dirname(__DIR__);
require_once $root . '/src/Autoload.php';

$app = App::boot($root);

$app->logger->info('worker online');

$recovered = $app->dispatcher->recoverStuck();
if ($recovered > 0) {
    $app->logger->warn('recovered stuck queue items', ['count' => $recovered]);
}

$running = true;

if (\function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(\SIGINT, static function () use (&$running): void { $running = false; });
    pcntl_signal(\SIGTERM, static function () use (&$running): void { $running = false; });
}

$idleSleep   = max(1, $app->config->int('worker.idle_sleep', 3));
$pruneEvery  = 3600;
$lastPruneAt = 0;

while ($running) {
    $attempted = $app->dispatcher->tick();

    if ($attempted === 0) {
        sleep($idleSleep);
    }

    if (time() - $lastPruneAt > $pruneEvery) {
        $lastPruneAt = time();

        $days    = $app->config->int('worker.activity_retention_days', 90);
        $removed = $app->activity->prune($days);

        if ($removed > 0) {
            $app->logger->info('pruned old activity rows', ['count' => $removed]);
        }

        $app->dispatcher->recoverStuck();
    }
}

$app->logger->info('worker stopped');

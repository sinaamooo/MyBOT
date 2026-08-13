<?php

declare(strict_types=1);

// Run every ~1 minute via cPanel Cron Jobs:
//   * * * * * /usr/local/bin/php /home/USER/crypto-signal-bot/cron/monitor.php >> /home/USER/crypto-signal-bot/logs/cron.log 2>&1

require_once __DIR__ . '/../bootstrap.php';

use App\AppFactory;
use App\Services\SettingsService;
use App\Support\CronLock;

set_time_limit(120);

if (!CronLock::acquire('monitor')) {
    echo '[' . date('c') . "] Previous monitor run still in progress, skipping this tick\n";
    exit(0);
}

$ctx = AppFactory::buildContext();
$params = SettingsService::getAll();
$ctx->monitor->checkOnce($params);
$ctx->dailyMessenger->checkOnce($params);

echo '[' . date('c') . "] Monitor check complete\n";

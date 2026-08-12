<?php

declare(strict_types=1);

// Run every ~1 minute via cPanel Cron Jobs:
//   * * * * * /usr/local/bin/php /home/USER/crypto-signal-bot/cron/monitor.php >> /home/USER/crypto-signal-bot/logs/cron.log 2>&1

require_once __DIR__ . '/../bootstrap.php';

use App\AppFactory;
use App\Services\SettingsService;

set_time_limit(120);

$ctx = AppFactory::buildContext();
$params = SettingsService::getAll();
$ctx->monitor->checkOnce($params);

echo '[' . date('c') . "] Monitor check complete\n";

<?php

declare(strict_types=1);

// Run every ~4 minutes via cPanel Cron Jobs:
//   */4 * * * * /usr/local/bin/php /home/USER/crypto-signal-bot/cron/scanner.php >> /home/USER/crypto-signal-bot/logs/cron.log 2>&1

require_once __DIR__ . '/../bootstrap.php';

use App\AppFactory;
use App\Config;
use App\Services\SymbolService;

set_time_limit(280);

SymbolService::ensureDefaults(Config::defaultSymbols());

$ctx = AppFactory::buildContext();
$ctx->scanner->scanOnce();

echo '[' . date('c') . "] Scan complete\n";

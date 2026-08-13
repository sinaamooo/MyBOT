<?php

declare(strict_types=1);

// Run every ~4 minutes via cPanel Cron Jobs:
//   */4 * * * * /usr/local/bin/php /home/USER/crypto-signal-bot/cron/scanner.php >> /home/USER/crypto-signal-bot/logs/cron.log 2>&1

require_once __DIR__ . '/../bootstrap.php';

use App\AppFactory;
use App\Config;
use App\Services\LogService;
use App\Services\SettingsService;
use App\Services\SymbolService;
use App\Support\CronLock;

// A large Top-N watch-list means many more requests per pass (~5 per
// symbol) - give it more room than the default 4-minute cron interval
// before PHP itself would time the script out.
set_time_limit(600);

if (!CronLock::acquire('scanner')) {
    echo '[' . date('c') . "] Previous scan still in progress, skipping this tick\n";
    exit(0);
}

SymbolService::ensureDefaults(Config::defaultSymbols());

$ctx = AppFactory::buildContext();

$params = SettingsService::getAll();
if (!empty($params['symbol_auto_sync_enabled'])) {
    try {
        $ranked = $ctx->exchange->listUsdtTickersRankedByVolume();
        $added = SymbolService::syncTopByVolume($ranked, (int) $params['symbol_top_n']);
        if ($added > 0) {
            LogService::log('INFO', 'symbols', "Auto-sync added {$added} new symbol(s) from top {$params['symbol_top_n']}");
        }
    } catch (\Throwable $e) {
        LogService::log('WARNING', 'symbols', 'Auto-sync failed: ' . $e->getMessage());
    }
}

$ctx->scanner->scanOnce();

echo '[' . date('c') . "] Scan complete\n";

<?php

declare(strict_types=1);

// One-off connectivity check - run manually via SSH right after deploying:
//   php cron/test_exchange.php BTCUSDT

require_once __DIR__ . '/../bootstrap.php';

use App\Exchange\MexcClient;

$symbol = $argv[1] ?? 'BTCUSDT';
$exchange = new MexcClient();

echo "Testing MEXC connectivity for {$symbol}...\n\n";

try {
    $ticker = $exchange->getTicker($symbol);
    echo "Ticker OK:\n";
    print_r($ticker);
} catch (\Throwable $e) {
    echo 'Ticker FAILED: ' . $e->getMessage() . "\n";
}

try {
    $klines = $exchange->getKlines($symbol, '15m', 10);
    echo "\nKlines OK (" . count($klines) . " candles), most recent 2:\n";
    print_r(array_slice($klines, -2));
} catch (\Throwable $e) {
    echo "\nKlines FAILED: " . $e->getMessage() . "\n";
}

try {
    $precision = $exchange->getPricePrecision($symbol);
    echo "\nPrice precision: {$precision} decimals\n";
} catch (\Throwable $e) {
    echo "\nPrecision FAILED: " . $e->getMessage() . "\n";
}

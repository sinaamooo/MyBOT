<?php

declare(strict_types=1);

namespace App\Market;

use App\Config;
use App\Exchange\MexcClient;
use App\Logger;

final class MarketDataService
{
    public function __construct(private readonly MexcClient $exchange)
    {
    }

    /**
     * Fetches everything the analysis pipeline needs for one symbol.
     * Returns null if market data could not be retrieved reliably (network
     * error, or fewer than 50 candles on any required timeframe).
     *
     * @param string[]|null $timeframes
     * @return array{symbol:string, price:float, change_24h:?float, quote_volume_24h:?float, funding_rate:?float, open_interest:?float, candles: array<string, array>}|null
     */
    public function getSnapshot(string $symbol, ?array $timeframes = null, int $candleCount = 300): ?array
    {
        $timeframes ??= Config::pipelineTimeframes();

        try {
            $ticker = $this->exchange->getTicker($symbol);

            $candles = [];
            foreach ($timeframes as $tf) {
                $rows = $this->exchange->getKlines($symbol, $tf, $candleCount);
                if (count($rows) < 50) {
                    Logger::bot('WARNING', "Insufficient candles for {$symbol} {$tf} (" . count($rows) . ' rows)');
                    return null;
                }
                $candles[$tf] = $rows;
            }
        } catch (\Throwable $e) {
            Logger::bot('ERROR', "Failed to build market snapshot for {$symbol}: " . $e->getMessage());
            return null;
        }

        return [
            'symbol' => $symbol,
            'price' => $ticker['last'],
            'change_24h' => $ticker['percentage'],
            'quote_volume_24h' => $ticker['quote_volume'],
            'funding_rate' => $ticker['funding_rate'],
            'open_interest' => $ticker['open_interest'],
            'candles' => $candles,
        ];
    }
}

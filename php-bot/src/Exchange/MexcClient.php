<?php

declare(strict_types=1);

namespace App\Exchange;

use App\Config;
use App\Logger;

/**
 * Thin cURL-only client for MEXC Futures (Contract) public REST API.
 * No SDK/Composer dependency - see https://mexcdevelop.github.io/apidocs/contract_v1_en/
 *
 * NOTE: this was written from documented MEXC Contract API behavior and
 * could not be exercised against the live API from the build environment
 * (outbound network to exchange hosts is not reachable there). Run
 * `php cron/test_exchange.php` right after deploying to confirm
 * connectivity and catch any endpoint/field drift before relying on it.
 */
final class MexcClient
{
    private const BASE_URL = 'https://contract.mexc.com';

    /** @var array<string, string> internal timeframe -> MEXC kline interval */
    private const INTERVAL_MAP = [
        '1m' => 'Min1',
        '5m' => 'Min5',
        '15m' => 'Min15',
        '30m' => 'Min30',
        '1h' => 'Min60',
        '4h' => 'Hour4',
        '8h' => 'Hour8',
        '1d' => 'Day1',
    ];

    /** @var array<string, int> MEXC kline interval -> seconds */
    private const INTERVAL_SECONDS = [
        'Min1' => 60,
        'Min5' => 300,
        'Min15' => 900,
        'Min30' => 1800,
        'Min60' => 3600,
        'Hour4' => 14400,
        'Hour8' => 28800,
        'Day1' => 86400,
    ];

    /** @var array<string, array<string, mixed>> per-symbol contract detail cache (one HTTP request lifetime) */
    private array $detailCache = [];

    public function toMexcSymbol(string $rawSymbol): string
    {
        $rawSymbol = strtoupper($rawSymbol);
        if (str_contains($rawSymbol, '_')) {
            return $rawSymbol;
        }
        $base = str_ends_with($rawSymbol, 'USDT') ? substr($rawSymbol, 0, -4) : $rawSymbol;
        return $base . '_USDT';
    }

    /**
     * @return array<int, array{ts:int, open:float, high:float, low:float, close:float, volume:float}>
     */
    public function getKlines(string $symbol, string $timeframe, int $limit = 300): array
    {
        $interval = self::INTERVAL_MAP[$timeframe] ?? null;
        if ($interval === null) {
            throw new \InvalidArgumentException("Unsupported timeframe: {$timeframe}");
        }
        $intervalSeconds = self::INTERVAL_SECONDS[$interval];
        $end = time();
        $start = $end - ($limit + 5) * $intervalSeconds; // small buffer

        $symbolId = $this->toMexcSymbol($symbol);
        $response = $this->request('GET', "/api/v1/contract/kline/{$symbolId}", [
            'interval' => $interval,
            'start' => $start,
            'end' => $end,
        ]);

        $data = $response['data'] ?? null;
        if (!is_array($data) || !isset($data['time'], $data['open'], $data['high'], $data['low'], $data['close'], $data['vol'])) {
            throw new \RuntimeException("Unexpected kline response shape for {$symbol} {$timeframe}");
        }

        $candles = [];
        $count = count($data['time']);
        for ($i = 0; $i < $count; $i++) {
            $candles[] = [
                'ts' => (int) $data['time'][$i],
                'open' => (float) $data['open'][$i],
                'high' => (float) $data['high'][$i],
                'low' => (float) $data['low'][$i],
                'close' => (float) $data['close'][$i],
                'volume' => (float) $data['vol'][$i],
            ];
        }
        usort($candles, fn($a, $b) => $a['ts'] <=> $b['ts']);

        if (count($candles) > $limit) {
            $candles = array_slice($candles, -$limit);
        }
        return $candles;
    }

    /**
     * One call gets price, 24h change, quote volume, funding rate AND open
     * interest (`holdVol`) - MEXC's ticker payload is a superset, so we
     * don't need three separate requests like a typical exchange wrapper.
     *
     * @return array{symbol:string, last:float, percentage:?float, quote_volume:?float, funding_rate:?float, open_interest:?float, high:?float, low:?float}
     */
    public function getTicker(string $symbol): array
    {
        $symbolId = $this->toMexcSymbol($symbol);
        $response = $this->request('GET', '/api/v1/contract/ticker', ['symbol' => $symbolId]);
        $data = $response['data'] ?? null;
        if (!is_array($data) || !isset($data['lastPrice'])) {
            throw new \RuntimeException("Unexpected ticker response shape for {$symbol}");
        }

        return [
            'symbol' => $symbol,
            'last' => (float) $data['lastPrice'],
            'percentage' => isset($data['riseFallRate']) ? (float) $data['riseFallRate'] * 100 : null,
            'quote_volume' => isset($data['amount24']) ? (float) $data['amount24'] : null,
            'funding_rate' => isset($data['fundingRate']) ? (float) $data['fundingRate'] : null,
            'open_interest' => isset($data['holdVol']) ? (float) $data['holdVol'] : null,
            'high' => isset($data['high24Price']) ? (float) $data['high24Price'] : null,
            'low' => isset($data['lower24Price']) ? (float) $data['lower24Price'] : null,
        ];
    }

    public function getPricePrecision(string $symbol): int
    {
        $detail = $this->getContractDetail($symbol);
        if (isset($detail['priceScale']) && is_numeric($detail['priceScale'])) {
            return (int) $detail['priceScale'];
        }
        return 4; // sane fallback if the field is ever missing/renamed
    }

    public function roundPrice(string $symbol, float $price): float
    {
        $decimals = $this->getPricePrecision($symbol);
        return round($price, $decimals);
    }

    /** @return array<string, mixed> */
    private function getContractDetail(string $symbol): array
    {
        $symbolId = $this->toMexcSymbol($symbol);
        if (isset($this->detailCache[$symbolId])) {
            return $this->detailCache[$symbolId];
        }
        $response = $this->request('GET', '/api/v1/contract/detail', ['symbol' => $symbolId]);
        $data = $response['data'] ?? [];
        $this->detailCache[$symbolId] = is_array($data) ? $data : [];
        return $this->detailCache[$symbolId];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $params = []): array
    {
        $url = self::BASE_URL . $path;
        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        $attempts = 0;
        $lastError = null;

        while ($attempts < 3) {
            $attempts++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
                CURLOPT_USERAGENT => 'crypto-signal-bot-php/1.0',
            ]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($errno !== 0) {
                $lastError = "cURL error ({$errno}): {$error}";
                usleep((int) (300000 * $attempts)); // 0.3s, 0.6s, 0.9s backoff
                continue;
            }
            if ($httpCode >= 500) {
                $lastError = "HTTP {$httpCode} from MEXC for {$path}";
                usleep((int) (300000 * $attempts));
                continue;
            }
            if ($httpCode >= 400) {
                throw new \RuntimeException("MEXC API error HTTP {$httpCode} for {$path}: " . substr((string) $body, 0, 300));
            }

            $decoded = json_decode((string) $body, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException("MEXC API returned invalid JSON for {$path}: " . substr((string) $body, 0, 300));
            }
            if (array_key_exists('success', $decoded) && $decoded['success'] !== true) {
                throw new \RuntimeException("MEXC API reported failure for {$path}: " . substr((string) $body, 0, 300));
            }
            return $decoded;
        }

        Logger::bot('ERROR', "MEXC request failed after {$attempts} attempts ({$path}): {$lastError}");
        throw new \RuntimeException("MEXC request failed: {$lastError}");
    }
}

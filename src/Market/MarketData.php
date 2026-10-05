<?php

declare(strict_types=1);

namespace App\Market;

use App\Support\Http;

/**
 * Fetches candles and order books from public exchange APIs (no API key needed).
 * Providers are tried in order; the first one that knows the symbol wins.
 */
final class MarketData
{
    private string $quote;
    private array $providers;
    private bool $demo;
    private string $cacheDir;
    private int $cacheTtl = 60;
    public string $lastProvider = '';

    public function __construct(array $marketConfig, string $cacheDir)
    {
        $this->quote = strtoupper($marketConfig['quote'] ?? 'USDT');
        $this->providers = $marketConfig['providers'] ?? ['binance', 'bybit', 'okx', 'kucoin'];
        $this->demo = (bool) ($marketConfig['demo'] ?? false);
        $this->cacheDir = $cacheDir;
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
    }

    public function quote(): string
    {
        return $this->quote;
    }

    public function candles(string $base, string $tf, int $limit = 300): Series
    {
        $base = strtoupper($base);
        if ($this->demo) {
            $this->lastProvider = 'demo';
            return DemoMarket::series($base, $this->quote, $tf, $limit);
        }

        $notFound = 0;
        foreach ($this->providers as $provider) {
            $cacheKey = "{$provider}_{$base}{$this->quote}_{$tf}_{$limit}";
            $rows = $this->cacheGet($cacheKey);
            if ($rows === null) {
                try {
                    $rows = $this->fetchCandles($provider, $base, $tf, $limit);
                } catch (SymbolNotFound $e) {
                    $notFound++;
                    continue;
                }
                if ($rows === null || count($rows) < 30) {
                    continue;
                }
                $this->cachePut($cacheKey, $rows);
            }
            $this->lastProvider = $provider;
            return Series::fromRows($base . $this->quote, $tf, $provider, $rows);
        }

        if ($notFound > 0) {
            throw new SymbolNotFound($base);
        }
        throw new MarketUnavailable('No market data provider responded');
    }

    /**
     * Order book of the provider that served the last candles.
     * @return array{bids: array<array{0:float,1:float}>, asks: array<array{0:float,1:float}>}|null
     */
    public function orderBook(string $base): ?array
    {
        $base = strtoupper($base);
        if ($this->demo) {
            return DemoMarket::orderBook($base, $this->quote);
        }
        $order = array_unique(array_merge([$this->lastProvider], $this->providers));
        foreach ($order as $provider) {
            $book = $this->fetchOrderBook($provider, $base);
            if ($book !== null && $book['bids'] && $book['asks']) {
                return $book;
            }
        }
        return null;
    }

    private function fetchCandles(string $provider, string $base, string $tf, int $limit): ?array
    {
        $q = $this->quote;
        switch ($provider) {
            case 'binance':
            case 'binance_vision':
                $host = $provider === 'binance' ? 'https://api.binance.com' : 'https://data-api.binance.vision';
                $res = Http::request('GET', "$host/api/v3/klines?symbol={$base}{$q}&interval={$tf}&limit=" . min($limit, 1000));
                if ($res['status'] === 400 && str_contains($res['body'], 'Invalid symbol')) {
                    throw new SymbolNotFound($base);
                }
                $data = json_decode($res['body'], true);
                if ($res['status'] !== 200 || !is_array($data)) {
                    return null;
                }
                return array_map(static fn ($k) => [(int) $k[0], (float) $k[1], (float) $k[2], (float) $k[3], (float) $k[4], (float) $k[5]], $data);

            case 'bybit':
                $map = ['1m' => '1', '3m' => '3', '5m' => '5', '15m' => '15', '30m' => '30', '1h' => '60', '2h' => '120', '4h' => '240', '6h' => '360', '12h' => '720', '1d' => 'D', '1w' => 'W'];
                if (!isset($map[$tf])) {
                    return null;
                }
                $data = Http::getJson("https://api.bybit.com/v5/market/kline?category=spot&symbol={$base}{$q}&interval={$map[$tf]}&limit=" . min($limit, 1000));
                if ($data === null) {
                    return null;
                }
                if (($data['retCode'] ?? 0) !== 0 || empty($data['result']['list'])) {
                    throw new SymbolNotFound($base);
                }
                return array_map(static fn ($k) => [(int) $k[0], (float) $k[1], (float) $k[2], (float) $k[3], (float) $k[4], (float) $k[5]], $data['result']['list']);

            case 'okx':
                $map = ['1m' => '1m', '3m' => '3m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '1H', '2h' => '2H', '4h' => '4H', '6h' => '6Hutc', '12h' => '12Hutc', '1d' => '1Dutc', '1w' => '1Wutc'];
                if (!isset($map[$tf])) {
                    return null;
                }
                $data = Http::getJson("https://www.okx.com/api/v5/market/candles?instId={$base}-{$q}&bar={$map[$tf]}&limit=" . min($limit, 300));
                if ($data === null) {
                    return null;
                }
                if (($data['code'] ?? '0') !== '0' || empty($data['data'])) {
                    throw new SymbolNotFound($base);
                }
                return array_map(static fn ($k) => [(int) $k[0], (float) $k[1], (float) $k[2], (float) $k[3], (float) $k[4], (float) $k[5]], $data['data']);

            case 'kucoin':
                $map = ['1m' => '1min', '3m' => '3min', '5m' => '5min', '15m' => '15min', '30m' => '30min', '1h' => '1hour', '2h' => '2hour', '4h' => '4hour', '6h' => '6hour', '12h' => '12hour', '1d' => '1day', '1w' => '1week'];
                if (!isset($map[$tf])) {
                    return null;
                }
                $data = Http::getJson("https://api.kucoin.com/api/v1/market/candles?type={$map[$tf]}&symbol={$base}-{$q}");
                if ($data === null) {
                    return null;
                }
                if (($data['code'] ?? '') !== '200000' || empty($data['data'])) {
                    throw new SymbolNotFound($base);
                }
                // KuCoin row order: time(sec), open, close, high, low, volume
                $rows = array_map(static fn ($k) => [(int) $k[0] * 1000, (float) $k[1], (float) $k[3], (float) $k[4], (float) $k[2], (float) $k[5]], $data['data']);
                return array_slice($rows, 0, $limit);
        }
        return null;
    }

    private function fetchOrderBook(string $provider, string $base): ?array
    {
        $q = $this->quote;
        $norm = static fn (array $side) => array_map(static fn ($r) => [(float) $r[0], (float) $r[1]], $side);
        switch ($provider) {
            case 'binance':
            case 'binance_vision':
                $host = $provider === 'binance' ? 'https://api.binance.com' : 'https://data-api.binance.vision';
                $d = Http::getJson("$host/api/v3/depth?symbol={$base}{$q}&limit=1000");
                return $d ? ['bids' => $norm($d['bids'] ?? []), 'asks' => $norm($d['asks'] ?? [])] : null;
            case 'bybit':
                $d = Http::getJson("https://api.bybit.com/v5/market/orderbook?category=spot&symbol={$base}{$q}&limit=200");
                return $d && isset($d['result']['b']) ? ['bids' => $norm($d['result']['b']), 'asks' => $norm($d['result']['a'])] : null;
            case 'okx':
                $d = Http::getJson("https://www.okx.com/api/v5/market/books?instId={$base}-{$q}&sz=400");
                return $d && isset($d['data'][0]) ? ['bids' => $norm($d['data'][0]['bids']), 'asks' => $norm($d['data'][0]['asks'])] : null;
            case 'kucoin':
                $d = Http::getJson("https://api.kucoin.com/api/v1/market/orderbook/level2_100?symbol={$base}-{$q}");
                return $d && isset($d['data']['bids']) ? ['bids' => $norm($d['data']['bids']), 'asks' => $norm($d['data']['asks'])] : null;
        }
        return null;
    }

    private function cacheGet(string $key): ?array
    {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (is_file($file) && filemtime($file) > time() - $this->cacheTtl) {
            $data = json_decode((string) file_get_contents($file), true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    private function cachePut(string $key, array $rows): void
    {
        @file_put_contents($this->cacheDir . '/' . $key . '.json', json_encode($rows));
    }
}

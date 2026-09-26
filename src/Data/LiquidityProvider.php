<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Http;
use Nikto\Core\Log;

final class LiquidityProvider
{
    private const FAPI = 'https://fapi.binance.com/fapi/v1';
    private const SPOT = ['https://api.binance.com/api/v3', 'https://data-api.binance.vision/api/v3'];

    private const WALL_FACTOR = 1.5;

    public static function btc(int $levels = 5): ?array
    {
        $levels = max(2, min(8, $levels));

        if (Mock::enabled()) {
            return self::analyze(
                ['Binance Futures' => Mock::btcDepth('futures'), 'Binance Spot' => Mock::btcDepth('spot')],
                Mock::btcKlines(),
                $levels
            );
        }

        $books = [];
        $klines = [];
        if (CoinGlass::enabled()) {
            foreach (['Binance', 'Bybit'] as $exchange) {
                $book = CoinGlass::book($exchange, 'BTCUSDT');
                if ($book !== null) {
                    $books[($books === [] ? 'CoinGlass · ' : '') . $exchange] = $book;
                }
            }
            $klines = CoinGlass::klines('Binance', 'BTCUSDT', 48);
            if ($books !== []) {
                return self::analyze($books, $klines !== [] ? $klines : self::klines(null), $levels);
            }
        }

        $futures = Http::getJson(self::FAPI . '/depth?symbol=BTCUSDT&limit=1000', [], 15, 1);
        if (self::isBook($futures)) {
            $books['Binance Futures'] = $futures;
        }
        foreach (self::SPOT as $base) {
            $spot = Http::getJson($base . '/depth?symbol=BTCUSDT&limit=5000', [], 20, 1);
            if (self::isBook($spot)) {
                $books['Binance Spot'] = $spot;
                break;
            }
        }

        $fallback = null;
        if ($books === []) {
            foreach (Exchanges::BOOKS as $exchange => $name) {
                $book = Exchanges::book($exchange, 'BTCUSDT');
                if ($book === null) {
                    continue;
                }
                $books[$name] = $book;
                $fallback ??= $exchange;
                if (count($books) >= 3) {
                    break;
                }
            }
            if (count($books) > 1 && isset($books['Hyperliquid'])) {
                $grouped = $books['Hyperliquid'];
                unset($books['Hyperliquid']);
                $books['Hyperliquid'] = $grouped;
            }
            if ($books !== []) {
                Log::warn('BTC order book served by fallback exchanges', ['sources' => array_keys($books)]);
            }
        }
        if ($books === []) {
            Log::error('BTC order book unavailable');
            return null;
        }

        return self::analyze($books, $klines !== [] ? $klines : self::klines($fallback), $levels);
    }

    private static function klines(?string $preferred): array
    {
        $klines = Http::getJson(self::FAPI . '/klines?symbol=BTCUSDT&interval=1h&limit=48', [], 15, 1);
        if (!is_array($klines)) {
            foreach (self::SPOT as $base) {
                $klines = Http::getJson($base . '/klines?symbol=BTCUSDT&interval=1h&limit=48', [], 15, 1);
                if (is_array($klines)) {
                    break;
                }
            }
        }
        if (!is_array($klines) || $klines === []) {
            $order = array_unique(array_merge($preferred !== null ? [$preferred] : [], ['Hyperliquid'], Exchanges::SPOT));
            foreach ($order as $exchange) {
                $klines = Exchanges::klines($exchange, 'spot', 'BTCUSDT', 48);
                if ($klines !== []) {
                    break;
                }
            }
        }

        return is_array($klines) ? $klines : [];
    }

    private static function isBook(mixed $data): bool
    {
        return is_array($data) && is_array($data['bids'] ?? null) && is_array($data['asks'] ?? null)
            && $data['bids'] !== [] && $data['asks'] !== [];
    }

    public static function analyze(array $books, array $klines, int $levels = 5): ?array
    {
        $parsed = [];
        foreach ($books as $name => $book) {
            if (!self::isBook($book)) {
                continue;
            }
            $bids = self::side($book['bids']);
            $asks = self::side($book['asks']);
            if ($bids !== [] && $asks !== []) {
                $parsed[$name] = [$bids, $asks];
            }
        }
        if ($parsed === []) {
            return null;
        }

        [$refBids, $refAsks] = reset($parsed);
        $bestBid = max(array_column($refBids, 0));
        $bestAsk = min(array_column($refAsks, 0));
        $mid = ($bestBid + $bestAsk) / 2;

        $candles = self::candles($klines);
        $price = $candles !== [] ? (float) end($candles)['c'] : $mid;

        $bucket = self::niceStep($mid * 0.001);

        $bidBuckets = [];
        $askBuckets = [];
        $bidFloor = $mid;
        $askCeil = $mid;
        foreach ($parsed as [$bids, $asks]) {
            foreach ($bids as [$p, $q]) {
                if ($p > $mid) {
                    continue;
                }
                $key = (string) (round($p / $bucket) * $bucket);
                $bidBuckets[$key] = ($bidBuckets[$key] ?? 0.0) + $q;
                $bidFloor = min($bidFloor, $p);
            }
            foreach ($asks as [$p, $q]) {
                if ($p < $mid) {
                    continue;
                }
                $key = (string) (round($p / $bucket) * $bucket);
                $askBuckets[$key] = ($askBuckets[$key] ?? 0.0) + $q;
                $askCeil = max($askCeil, $p);
            }
        }
        if ($bidBuckets === [] || $askBuckets === []) {
            return null;
        }

        $range = min($mid - $bidFloor, $askCeil - $mid);
        $bidTotal = 0.0;
        $askTotal = 0.0;
        foreach ($bidBuckets as $p => $q) {
            if ((float) $p >= $mid - $range) {
                $bidTotal += $q * (float) $p;
            }
        }
        foreach ($askBuckets as $p => $q) {
            if ((float) $p <= $mid + $range) {
                $askTotal += $q * (float) $p;
            }
        }

        $bidWalls = self::walls($bidBuckets, $bucket, $mid, $levels);
        $askWalls = self::walls($askBuckets, $bucket, $mid, $levels);
        $maxQty = max(array_merge([1e-9], array_column($bidWalls, 'qty'), array_column($askWalls, 'qty')));
        foreach ([&$bidWalls, &$askWalls] as &$list) {
            foreach ($list as &$w) {
                $w['strength'] = $w['qty'] / $maxQty;
            }
            unset($w);
        }
        unset($list);

        return [
            'symbol'      => 'BTC',
            'price'       => $price,
            'mid'         => $mid,
            'best_bid'    => $bestBid,
            'best_ask'    => $bestAsk,
            'spread'      => $bestAsk - $bestBid,
            'bucket'      => $bucket,
            'range_pct'   => $range / $mid * 100,
            'bid_total'   => $bidTotal,
            'ask_total'   => $askTotal,
            'imbalance'   => ($bidTotal + $askTotal) > 0 ? $bidTotal / ($bidTotal + $askTotal) : 0.5,
            'bid_walls'   => $bidWalls,
            'ask_walls'   => $askWalls,
            'profile'     => self::profile($bidBuckets, $askBuckets),
            'candles'     => $candles,
            'sources'     => array_keys($parsed),
        ];
    }

    private static function side(array $levels): array
    {
        $out = [];
        foreach ($levels as $level) {
            if (!is_array($level) || !isset($level[0], $level[1])) {
                continue;
            }
            $p = (float) $level[0];
            $q = (float) $level[1];
            if ($p > 0 && $q > 0) {
                $out[] = [$p, $q];
            }
        }

        return $out;
    }

    private static function walls(array $buckets, float $bucket, float $mid, int $levels): array
    {
        $qtys = array_values($buckets);
        sort($qtys);
        $median = $qtys[intdiv(count($qtys), 2)] ?? 0.0;
        $threshold = $median * self::WALL_FACTOR;

        arsort($buckets);
        $picked = [];
        foreach ($buckets as $p => $q) {
            if ($q < $threshold || count($picked) >= $levels) {
                break;
            }
            $price = (float) $p;
            foreach ($picked as $w) {
                if (abs($w['price'] - $price) < $bucket * 2.5) {
                    continue 2;
                }
            }
            $picked[] = [
                'price'        => $price,
                'qty'          => $q,
                'usd'          => $q * $price,
                'distance_pct' => ($price - $mid) / $mid * 100,
            ];
        }

        usort($picked, static fn (array $a, array $b): int => abs($a['distance_pct']) <=> abs($b['distance_pct']));

        return $picked;
    }

    private static function profile(array $bidBuckets, array $askBuckets): array
    {
        $toList = static function (array $buckets): array {
            $out = [];
            foreach ($buckets as $p => $q) {
                $out[] = [(float) $p, (float) $q];
            }
            usort($out, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
            return $out;
        };

        return ['bids' => $toList($bidBuckets), 'asks' => $toList($askBuckets)];
    }

    private static function candles(array $klines): array
    {
        $out = [];
        foreach ($klines as $k) {
            if (!is_array($k) || !isset($k[0], $k[1], $k[2], $k[3], $k[4])) {
                continue;
            }
            $c = ['t' => (int) ((int) $k[0] / 1000), 'o' => (float) $k[1], 'h' => (float) $k[2], 'l' => (float) $k[3], 'c' => (float) $k[4]];
            if ($c['h'] > 0 && $c['l'] > 0 && $c['h'] >= $c['l']) {
                $out[] = $c;
            }
        }

        return $out;
    }

    public static function niceStep(float $raw): float
    {
        if ($raw <= 0) {
            return 1.0;
        }
        $pow = 10 ** floor(log10($raw));
        foreach ([1, 2.5, 5, 10] as $m) {
            if ($m * $pow >= $raw - 1e-9) {
                return $m * $pow;
            }
        }

        return 10 * $pow;
    }

    public static function formatBtc(float $qty): string
    {
        return number_format($qty, $qty >= 100 ? 0 : 1) . ' BTC';
    }

    public static function formatUsd(float $usd): string
    {
        return FuturesProvider::formatVolume($usd);
    }
}

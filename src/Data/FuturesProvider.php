<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Http;
use Nikto\Core\Log;

final class FuturesProvider
{
    public const SOURCE_BINANCE = 'Binance Futures';

    private const FAPI = 'https://fapi.binance.com/fapi/v1';
    private const SPOT_MIRROR = 'https://data-api.binance.vision/api/v3';

    public static function movers(int $count = 5, float $minVolume = 5_000_000, bool $withSparkline = true): ?array
    {
        $count = max(1, min(8, $count));

        if (Mock::enabled()) {
            $tickers = Mock::futuresTickers();
            $tradable = self::tradable(Mock::futuresExchangeInfo());
            $exchange = 'Binance';
            $coinIds = [];
        } else {
            [$tickers, $tradable, $exchange, $coinIds] = self::tickers();
        }

        if (!is_array($tickers) || $tickers === [] || array_values($tickers) !== $tickers) {
            Log::error('Futures tickers unavailable from every source');
            return null;
        }

        $result = self::rank($tickers, $tradable ?: null, $count, $minVolume);
        if ($result === null) {
            return null;
        }
        $result['exchange'] = $exchange === 'CoinGecko' ? 'Binance' : $exchange;
        $result['source'] = match ($exchange) {
            'CoinGlass' => 'CoinGlass · All exchanges',
            'CoinGecko' => 'CoinGecko · Binance Futures',
            default     => $exchange . ' Futures',
        };

        if ($withSparkline) {
            $shown = array_merge(array_column($result['gainers'], 'pair'), array_column($result['losers'], 'pair'));
            $geckoSparks = $exchange === 'CoinGecko'
                ? CoinGeckoApi::sparklines(array_map(static fn (string $p): string => $coinIds[$p] ?? '', $shown))
                : [];
            foreach (['gainers', 'losers'] as $side) {
                foreach ($result[$side] as $i => $row) {
                    $pair = (string) $row['pair'];
                    $result[$side][$i]['spark'] = $geckoSparks[$coinIds[$pair] ?? ''] ?? self::sparkline($pair, (float) $row['change_pct'], $exchange);
                }
            }
        }

        return $result;
    }

    private static function tickers(): array
    {
        if (CoinGlass::enabled()) {
            $rows = CoinGlass::futuresTickers();
            if ($rows !== []) {
                return [$rows, null, 'CoinGlass', []];
            }
        }
        if (CoinGeckoApi::enabled()) {
            [$rows, $ids] = CoinGeckoApi::futuresTickers();
            if ($rows !== []) {
                return [$rows, null, 'CoinGecko', $ids];
            }
        }

        $tickers = Http::getJson(self::FAPI . '/ticker/24hr', [], 20, 1);
        if (is_array($tickers) && $tickers !== [] && array_values($tickers) === $tickers) {
            $tradable = Http::remember('fapi:tradable', 6 * 3600, static function (): ?array {
                $info = Http::getJson(self::FAPI . '/exchangeInfo', [], 25, 1);
                return is_array($info) ? self::tradable($info) : null;
            });

            return [$tickers, $tradable, 'Binance', []];
        }

        foreach (Exchanges::FUTURES as $exchange) {
            $rows = Exchanges::futuresTickers($exchange);
            if ($rows !== []) {
                Log::warn('Futures tickers served by fallback exchange', ['exchange' => $exchange]);
                return [$rows, null, $exchange, []];
            }
        }

        return [null, null, 'Binance', []];
    }

    public static function tradable(array $exchangeInfo): array
    {
        $out = [];
        foreach ((array) ($exchangeInfo['symbols'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            if (($s['contractType'] ?? '') !== 'PERPETUAL'
                || ($s['status'] ?? '') !== 'TRADING'
                || ($s['quoteAsset'] ?? '') !== 'USDT'
            ) {
                continue;
            }
            $symbol = (string) ($s['symbol'] ?? '');
            if ($symbol !== '') {
                $out[$symbol] = (string) ($s['baseAsset'] ?? substr($symbol, 0, -4));
            }
        }

        return $out;
    }

    public static function rank(array $tickers, ?array $tradable, int $count, float $minVolume): ?array
    {
        $rows = [];
        foreach ($tickers as $t) {
            if (!is_array($t) || !is_string($t['symbol'] ?? null)) {
                continue;
            }
            $pair = $t['symbol'];

            if ($tradable !== null) {
                if (!isset($tradable[$pair])) {
                    continue;
                }
                $base = $tradable[$pair];
            } else {
                if (!preg_match('/^([A-Z0-9]+)USDT$/', $pair, $m)) {
                    continue;
                }
                $base = $m[1];
            }

            $price = (float) ($t['lastPrice'] ?? 0);
            if ($price <= 0 || (int) ($t['count'] ?? 1) === 0) {
                continue;
            }

            $rows[] = [
                'symbol'       => $base,
                'logo'         => self::logoSymbol($base),
                'pair'         => $pair,
                'price'        => $price,
                'change_pct'   => (float) ($t['priceChangePercent'] ?? 0),
                'change_abs'   => (float) ($t['priceChange'] ?? 0),
                'high'         => (float) ($t['highPrice'] ?? 0),
                'low'          => (float) ($t['lowPrice'] ?? 0),
                'quote_volume' => (float) ($t['quoteVolume'] ?? 0),
                'spark'        => [],
            ];
        }

        if ($rows === []) {
            return null;
        }

        $up = $down = 0;
        $sum = 0.0;
        foreach ($rows as $r) {
            $sum += $r['change_pct'];
            if ($r['change_pct'] > 0.005) {
                $up++;
            } elseif ($r['change_pct'] < -0.005) {
                $down++;
            }
        }
        $breadth = [
            'up'    => $up,
            'down'  => $down,
            'flat'  => count($rows) - $up - $down,
            'total' => count($rows),
            'avg'   => $sum / count($rows),
        ];

        $liquid = array_values(array_filter($rows, static fn (array $r): bool => $r['quote_volume'] >= $minVolume));
        if (count($liquid) < $count * 2) {
            $liquid = $rows;
        }

        usort($liquid, static fn (array $a, array $b): int => $b['change_pct'] <=> $a['change_pct']);
        $gainers = array_slice($liquid, 0, $count);
        $losers = array_slice(array_reverse($liquid), 0, $count);

        $taken = array_column($gainers, 'pair');
        $losers = array_values(array_filter($losers, static fn (array $r): bool => !in_array($r['pair'], $taken, true)));

        return [
            'gainers'    => $gainers,
            'losers'     => $losers,
            'breadth'    => $breadth,
            'min_volume' => $minVolume,
        ];
    }

    public static function logoSymbol(string $base): string
    {
        return (string) (preg_replace('/^(1000000|100000|10000|1000|1M)(?=[A-Z])/', '', strtoupper($base)) ?: $base);
    }

    private static function sparkline(string $pair, float $changePct, string $exchange = 'Binance'): array
    {
        if (Mock::enabled()) {
            return Mock::series(crc32($pair), 24, $changePct);
        }

        $candles = match ($exchange) {
            'Binance'   => Http::getJson(self::FAPI . '/klines?symbol=' . rawurlencode($pair) . '&interval=1h&limit=24', [], 12, 1),
            'CoinGlass' => CoinGlass::klines('Binance', $pair, 24),
            'CoinGecko' => [],
            default     => Exchanges::klines($exchange, 'futures', $pair, 24),
        };
        if (!is_array($candles) || $candles === []) {
            $candles = Http::getJson(self::SPOT_MIRROR . '/klines?symbol=' . rawurlencode($pair) . '&interval=1h&limit=24', [], 12, 0);
        }

        $out = [];
        foreach (is_array($candles) ? $candles : [] as $candle) {
            if (is_array($candle) && isset($candle[4]) && is_numeric($candle[4])) {
                $out[] = (float) $candle[4];
            }
        }

        return $out;
    }

    public static function formatVolume(float $usd): string
    {
        foreach ([[1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$unit, $suffix]) {
            if ($usd >= $unit) {
                $v = $usd / $unit;
                return '$' . number_format($v, $v >= 100 ? 0 : 1) . $suffix;
            }
        }

        return '$' . number_format($usd, 0);
    }
}

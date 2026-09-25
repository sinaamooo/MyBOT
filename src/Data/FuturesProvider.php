<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Http;
use Nikto\Core\Log;

final class FuturesProvider
{
    private const FAPI = 'https://fapi.binance.com/fapi/v1';

    public static function movers(int $count = 5, float $minVolume = 5_000_000, bool $withSparkline = true): ?array
    {
        $count = max(1, min(8, $count));

        if (Mock::enabled()) {
            $tickers = Mock::futuresTickers();
            $tradable = self::tradable(Mock::futuresExchangeInfo());
        } else {
            $tickers = Http::getJson(self::FAPI . '/ticker/24hr', [], 20, 2);
            $tradable = Http::remember('fapi:tradable', 6 * 3600, static function (): ?array {
                $info = Http::getJson(self::FAPI . '/exchangeInfo', [], 25, 1);
                return is_array($info) ? self::tradable($info) : null;
            });
        }

        if (!is_array($tickers) || array_values($tickers) !== $tickers) {
            Log::error('Futures tickers unavailable');
            return null;
        }

        $result = self::rank($tickers, $tradable ?: null, $count, $minVolume);
        if ($result === null) {
            return null;
        }

        if ($withSparkline) {
            foreach (['gainers', 'losers'] as $side) {
                foreach ($result[$side] as $i => $row) {
                    $result[$side][$i]['spark'] = self::sparkline((string) $row['pair'], (float) $row['change_pct']);
                }
            }
        }

        return $result;
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

    private static function sparkline(string $pair, float $changePct): array
    {
        if (Mock::enabled()) {
            return Mock::series(crc32($pair), 24, $changePct);
        }
        $data = Http::getJson(self::FAPI . '/klines?symbol=' . rawurlencode($pair) . '&interval=1h&limit=24', [], 12, 1);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $candle) {
            if (is_array($candle) && isset($candle[4])) {
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

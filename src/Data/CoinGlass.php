<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Config;
use Nikto\Core\Http;

final class CoinGlass
{
    public const NAME_FA = 'کوین‌گلس';

    private const BASE = 'https://open-api-v4.coinglass.com/api';

    public static function enabled(): bool
    {
        return trim((string) Config::get('coinglass_key', '')) !== '';
    }

    public static function get(string $path, array $query = []): mixed
    {
        if (!self::enabled()) {
            return null;
        }
        $url = self::BASE . $path . ($query !== [] ? '?' . http_build_query($query) : '');
        $res = Http::getJson($url, ['CG-API-KEY: ' . trim((string) Config::get('coinglass_key'))], 20, 0);
        if (!is_array($res)) {
            return null;
        }
        if ((string) ($res['code'] ?? '') !== '0') {
            Http::note($url, 0, 'CoinGlass ' . (string) ($res['code'] ?? '?') . ': ' . (string) ($res['msg'] ?? 'unknown error'));
            return null;
        }

        return $res['data'] ?? null;
    }

    public static function book(string $exchange, string $pair): ?array
    {
        $data = self::get('/futures/orderbook/history', ['exchange' => $exchange, 'symbol' => $pair, 'interval' => '1m', 'limit' => 1]);
        $snap = is_array($data) && $data !== [] ? end($data) : null;
        if (!is_array($snap) || !is_array($snap[1] ?? null) || !is_array($snap[2] ?? null)) {
            return null;
        }
        $a = self::levels($snap[1]);
        $b = self::levels($snap[2]);
        if ($a === [] || $b === []) {
            return null;
        }
        return self::median($a) >= self::median($b) ? ['bids' => $b, 'asks' => $a] : ['bids' => $a, 'asks' => $b];
    }

    public static function klines(string $exchange, string $pair, int $limit): array
    {
        $data = self::get('/futures/price/history', ['exchange' => $exchange, 'symbol' => $pair, 'interval' => '1h', 'limit' => $limit]);
        $out = [];
        foreach (is_array($data) ? $data : [] as $k) {
            if (is_array($k) && is_numeric($k['time'] ?? null) && is_numeric($k['close'] ?? null)) {
                $out[] = [(int) $k['time'], (float) ($k['open'] ?? 0), (float) ($k['high'] ?? 0), (float) ($k['low'] ?? 0), (float) $k['close']];
            }
        }
        usort($out, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return array_slice($out, -$limit);
    }

    public static function futuresTickers(): array
    {
        $out = [];
        for ($page = 1; $page <= 5; $page++) {
            $data = self::get('/futures/coins-markets', ['per_page' => 200, 'page' => $page]);
            if (!is_array($data) || $data === []) {
                break;
            }
            foreach ($data as $c) {
                $base = strtoupper((string) ($c['symbol'] ?? ''));
                if (!is_array($c) || !preg_match('/^[A-Z0-9]+$/', $base) || !is_numeric($c['current_price'] ?? null)) {
                    continue;
                }
                $ratio = (float) ($c['open_interest_volume_ratio'] ?? 0);
                $out[] = Exchanges::ticker(
                    $base . 'USDT',
                    (float) $c['current_price'],
                    (float) ($c['price_change_percent_24h'] ?? 0),
                    0.0,
                    0.0,
                    $ratio > 0 ? (float) ($c['open_interest_usd'] ?? 0) / $ratio : 0.0
                );
            }
            if (count($data) < 200) {
                break;
            }
        }

        return $out;
    }

    private static function levels(array $levels): array
    {
        $out = [];
        foreach ($levels as $l) {
            if (is_array($l) && is_numeric($l[0] ?? null) && is_numeric($l[1] ?? null) && (float) $l[1] > 0) {
                $out[] = [(float) $l[0], (float) $l[1]];
            }
        }

        return $out;
    }

    private static function median(array $levels): float
    {
        $prices = array_column($levels, 0);
        sort($prices);

        return (float) $prices[intdiv(count($prices), 2)];
    }
}

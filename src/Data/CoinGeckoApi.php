<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Config;
use Nikto\Core\Http;

final class CoinGeckoApi
{
    public const BASE = 'https://api.coingecko.com/api/v3';

    public static function enabled(): bool
    {
        return trim((string) Config::get('coingecko_key', '')) !== '';
    }

    public static function headers(): array
    {
        return self::enabled() ? ['x-cg-demo-api-key: ' . trim((string) Config::get('coingecko_key'))] : [];
    }

    public static function get(string $path, array $query = [], int $timeout = 25): ?array
    {
        return Http::getJson(self::BASE . $path . ($query !== [] ? '?' . http_build_query($query) : ''), self::headers(), $timeout, 1);
    }

    public static function futuresTickers(string $exchangeId = 'binance_futures'): array
    {
        $data = self::get('/derivatives/exchanges/' . $exchangeId, ['include_tickers' => 'unexpired'], 30);
        $rows = [];
        $ids = [];
        foreach ((array) ($data['tickers'] ?? []) as $t) {
            $base = strtoupper((string) ($t['base'] ?? ''));
            if (!is_array($t) || ($t['contract_type'] ?? '') !== 'perpetual' || strtoupper((string) ($t['target'] ?? '')) !== 'USDT'
                || !preg_match('/^[A-Z0-9]+$/', $base)
            ) {
                continue;
            }
            $pair = $base . 'USDT';
            $last = (float) ($t['converted_last']['usd'] ?? 0) ?: (float) ($t['last'] ?? 0);
            $rows[] = Exchanges::ticker($pair, $last, (float) ($t['h24_percentage_change'] ?? 0), 0.0, 0.0, (float) ($t['converted_volume']['usd'] ?? 0));
            if (($t['coin_id'] ?? '') !== '') {
                $ids[$pair] = (string) $t['coin_id'];
            }
        }

        return [$rows, $ids];
    }

    public static function sparklines(array $coinIds): array
    {
        $coinIds = array_values(array_unique(array_filter($coinIds)));
        if ($coinIds === []) {
            return [];
        }
        $data = self::get('/coins/markets', ['vs_currency' => 'usd', 'ids' => implode(',', $coinIds), 'sparkline' => 'true', 'per_page' => 250]);
        $out = [];
        foreach (is_array($data) ? $data : [] as $row) {
            $prices = $row['sparkline_in_7d']['price'] ?? null;
            if (is_array($row) && isset($row['id']) && is_array($prices)) {
                $out[(string) $row['id']] = array_slice(array_map('floatval', $prices), -24);
            }
        }

        return $out;
    }
}

<?php
declare(strict_types=1);

namespace Nikto\Data;

use Nikto\Core\Http;
use Nikto\Core\Log;
use Nikto\Core\Settings;

/**
 * دریافت قیمت و نوسان ۲۴ ساعته‌ی ارزها (بایننس با جایگزین کوین‌گکو).
 *
 * خروجی هر ارز:
 *   symbol, name, name_fa, color, price, change_pct, change_abs, high, low, volume, spark[]
 */
final class PriceProvider
{
    private const BINANCE = 'https://api.binance.com/api/v3';
    private const GECKO   = 'https://api.coingecko.com/api/v3';

    /**
     * @param string[] $symbols
     * @return array<int,array<string,mixed>>
     */
    public static function fetch(array $symbols, bool $withSparkline = true): array
    {
        $symbols = array_values(array_unique(array_map('strtoupper', $symbols)));
        if ($symbols === []) {
            return [];
        }
        if (Mock::enabled()) {
            return Mock::prices($symbols);
        }

        $source = Settings::get('price_source');
        $rows = [];

        if ($source === 'binance' || $source === 'auto') {
            $rows = self::fromBinance($symbols, $withSparkline);
        }
        if ($rows === [] && ($source === 'coingecko' || $source === 'auto')) {
            $rows = self::fromGecko($symbols);
        }
        if ($rows === []) {
            Log::error('Price fetch failed from every source', ['symbols' => $symbols]);
        }

        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    private static function fromBinance(array $symbols, bool $withSparkline): array
    {
        $pairs = array_map(static fn (string $s): string => self::pair($s), $symbols);
        $query = '["' . implode('","', $pairs) . '"]';
        $data = Http::getJson(self::BINANCE . '/ticker/24hr?symbols=' . rawurlencode($query));

        if (!is_array($data) || $data === []) {
            return [];
        }
        $bySymbol = [];
        foreach ($data as $row) {
            if (isset($row['symbol'])) {
                $bySymbol[(string) $row['symbol']] = $row;
            }
        }

        $out = [];
        foreach ($symbols as $i => $symbol) {
            $row = $bySymbol[$pairs[$i]] ?? null;
            if ($row === null) {
                continue;
            }
            $out[] = self::row(
                $symbol,
                (float) ($row['lastPrice'] ?? 0),
                (float) ($row['priceChangePercent'] ?? 0),
                (float) ($row['priceChange'] ?? 0),
                (float) ($row['highPrice'] ?? 0),
                (float) ($row['lowPrice'] ?? 0),
                (float) ($row['quoteVolume'] ?? 0),
                $withSparkline ? self::sparkline($pairs[$i]) : []
            );
        }

        return $out;
    }

    /** @return float[] */
    private static function sparkline(string $pair): array
    {
        $data = Http::getJson(self::BINANCE . '/klines?symbol=' . $pair . '&interval=1h&limit=24', [], 15, 1);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $candle) {
            if (isset($candle[4])) {
                $out[] = (float) $candle[4];
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private static function fromGecko(array $symbols): array
    {
        $ids = [];
        foreach ($symbols as $s) {
            $id = Coins::geckoId($s);
            if ($id !== null) {
                $ids[$id] = $s;
            }
        }
        if ($ids === []) {
            return [];
        }
        $url = self::GECKO . '/coins/markets?vs_currency=usd&ids=' . implode(',', array_keys($ids))
            . '&order=market_cap_desc&sparkline=true&price_change_percentage=24h';
        $data = Http::getJson($url);
        if (!is_array($data)) {
            return [];
        }

        $byId = [];
        foreach ($data as $row) {
            if (isset($row['id'])) {
                $byId[(string) $row['id']] = $row;
            }
        }

        $out = [];
        foreach ($ids as $id => $symbol) {
            $row = $byId[$id] ?? null;
            if ($row === null) {
                continue;
            }
            $spark = $row['sparkline_in_7d']['price'] ?? [];
            $spark = is_array($spark) ? array_slice(array_map('floatval', $spark), -24) : [];
            $out[] = self::row(
                $symbol,
                (float) ($row['current_price'] ?? 0),
                (float) ($row['price_change_percentage_24h'] ?? 0),
                (float) ($row['price_change_24h'] ?? 0),
                (float) ($row['high_24h'] ?? 0),
                (float) ($row['low_24h'] ?? 0),
                (float) ($row['total_volume'] ?? 0),
                $spark
            );
        }

        // ترتیب درخواستی کاربر حفظ شود
        usort($out, static function (array $a, array $b) use ($symbols): int {
            return array_search($a['symbol'], $symbols, true) <=> array_search($b['symbol'], $symbols, true);
        });

        return $out;
    }

    /** @return array<string,mixed> */
    public static function row(
        string $symbol,
        float $price,
        float $changePct,
        float $changeAbs,
        float $high = 0,
        float $low = 0,
        float $volume = 0,
        array $spark = []
    ): array {
        return [
            'symbol'     => strtoupper($symbol),
            'name'       => Coins::name($symbol),
            'name_fa'    => Coins::nameFa($symbol),
            'color'      => Coins::color($symbol),
            'price'      => $price,
            'change_pct' => $changePct,
            'change_abs' => $changeAbs,
            'high'       => $high,
            'low'        => $low,
            'volume'     => $volume,
            'spark'      => $spark,
        ];
    }

    private static function pair(string $symbol): string
    {
        $symbol = strtoupper($symbol);
        if ($symbol === 'USDT') {
            return 'USDCUSDT';
        }
        return $symbol . 'USDT';
    }

    /** قالب‌بندی قیمت بر اساس بزرگی عدد */
    public static function formatPrice(float $price): string
    {
        if ($price >= 1000) {
            return number_format($price, 0);
        }
        if ($price >= 100) {
            return self::trim(number_format($price, 2));
        }
        if ($price >= 1) {
            return self::trim(number_format($price, 3));
        }
        if ($price >= 0.01) {
            return self::trim(number_format($price, 4));
        }
        if ($price >= 0.0001) {
            return self::trim(number_format($price, 6));
        }
        return self::trim(sprintf('%.8f', $price));
    }

    public static function formatChange(float $value): string
    {
        $abs = abs($value);
        if ($abs >= 100000) {
            return number_format($value, 0);
        }
        if ($abs >= 1000) {
            return number_format($value, 2);
        }
        if ($abs >= 1) {
            return number_format($value, 2);
        }
        if ($abs >= 0.01) {
            return number_format($value, 4);
        }
        return rtrim(rtrim(sprintf('%.6f', $value), '0'), '.') ?: '0';
    }

    /** حذف صفرهای انتهایی اعشار */
    private static function trim(string $value): string
    {
        if (!str_contains($value, '.')) {
            return $value;
        }
        return rtrim(rtrim($value, '0'), '.');
    }

    public static function formatCompact(float $value): string
    {
        foreach ([[1e12, 'T'], [1e9, 'B'], [1e6, 'M'], [1e3, 'K']] as [$unit, $suffix]) {
            if ($value >= $unit) {
                return number_format($value / $unit, $value / $unit >= 100 ? 0 : 2) . $suffix;
            }
        }
        return number_format($value, 0);
    }
}

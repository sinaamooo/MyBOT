<?php
declare(strict_types=1);

namespace Nikto\Data;

use DateTimeImmutable;

final class Mock
{
    private static ?bool $forced = null;

    public static function enable(bool $on = true): void
    {
        self::$forced = $on;
    }

    public static function enabled(): bool
    {
        if (self::$forced !== null) {
            return self::$forced;
        }
        return in_array(strtolower((string) getenv('NIKTO_MOCK')), ['1', 'true', 'yes'], true);
    }

    public static function prices(array $symbols): array
    {
        $fixture = self::load('prices.json');
        $out = [];
        foreach ($symbols as $i => $symbol) {
            $symbol = strtoupper($symbol);
            $data = $fixture[$symbol] ?? [
                'price'      => 12.5 + $i * 7.3,
                'change_pct' => ((($i * 37) % 11) - 5) / 1.7,
            ];
            $price = (float) $data['price'];
            $pct   = (float) $data['change_pct'];
            $abs   = $data['change_abs'] ?? ($price - $price / (1 + $pct / 100));

            $out[] = PriceProvider::row(
                $symbol,
                $price,
                $pct,
                (float) $abs,
                $price * 1.035,
                $price * 0.962,
                (float) ($data['volume'] ?? 1.2e9 / ($i + 1)),
                self::sparkline($price, $pct, crc32($symbol))
            );
        }

        return $out;
    }

    private static function sparkline(float $price, float $pct, int $seed): array
    {
        mt_srand($seed);
        $start = $price / (1 + $pct / 100);
        $points = [];
        for ($i = 0; $i < 24; $i++) {
            $t = $i / 23;
            $trend = $start + ($price - $start) * $t;
            $noise = $trend * (mt_rand(-45, 45) / 10000);
            $points[] = round($trend + $noise, 8);
        }
        $points[23] = $price;
        mt_srand();

        return $points;
    }

    public static function fearGreed(): array
    {
        $fixture = self::load('feargreed.json');
        $series = [];
        foreach (($fixture['series'] ?? [27, 26, 24, 22, 19, 18, 17, 16]) as $i => $value) {
            $series[] = [
                'value'     => (int) $value,
                'class'     => '',
                'timestamp' => time() - $i * 86400,
            ];
        }
        while (count($series) < 32) {
            $series[] = ['value' => 11 + (count($series) % 7), 'class' => '', 'timestamp' => time() - count($series) * 86400];
        }

        return FearGreedProvider::build($series);
    }

    public static function calendar(DateTimeImmutable $day): array
    {
        $fixture = self::load('calendar.json');
        $rows = [];
        foreach ($fixture as $item) {
            $time = (string) ($item['time'] ?? '');
            $allDay = $time === '';
            $at = $allDay
                ? $day->setTime(0, 0)
                : $day->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));

            $title = (string) ($item['title'] ?? '');
            $currency = strtoupper((string) ($item['currency'] ?? 'USD'));

            $rows[] = [
                'timestamp' => $at->getTimestamp(),
                'time'      => $allDay ? '' : $at->format('H:i'),
                'all_day'   => $allDay,
                'currency'  => $currency,
                'country'   => Countries::code($currency),
                'impact'    => (int) ($item['impact'] ?? 2),
                'title_en'  => $title,
                'title_fa'  => EventTranslator::translate($title),
                'previous'  => (string) ($item['previous'] ?? ''),
                'forecast'  => (string) ($item['forecast'] ?? ''),
                'actual'    => (string) ($item['actual'] ?? ''),
                'speech'    => CalendarProvider::isSpeech($title),
            ];
        }

        return $rows;
    }

    private static function load(string $file): array
    {
        $path = APP_FIXTURES . '/' . $file;
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private const FUTURES = [
        ['BTC', 64210.5, -1.12, 1.8e10], ['ETH', 3120.4, -2.35, 9.2e9], ['SOL', 148.62, 3.84, 2.1e9],
        ['BNB', 581.3, 0.62, 6.1e8], ['XRP', 0.5231, -0.84, 8.4e8], ['DOGE', 0.12871, 4.11, 1.2e9],
        ['ADA', 0.4412, -1.93, 3.1e8], ['TRX', 0.1204, 0.21, 1.4e8], ['AVAX', 27.84, -3.22, 3.8e8],
        ['LINK', 13.92, 2.07, 3.3e8], ['DOT', 6.214, -2.61, 1.9e8], ['TON', 6.882, 1.45, 2.2e8],
        ['LTC', 71.35, -0.55, 2.6e8], ['BCH', 382.1, -1.74, 2.4e8], ['NEAR', 5.412, 6.83, 3.9e8],
        ['APT', 7.921, -4.41, 1.7e8], ['ARB', 0.7834, -5.12, 2.5e8], ['OP', 1.742, -3.98, 1.6e8],
        ['SUI', 1.0214, 11.62, 6.4e8], ['SEI', 0.3912, 8.24, 1.8e8], ['TIA', 6.121, -6.73, 1.5e8],
        ['INJ', 21.45, 5.36, 1.9e8], ['1000PEPE', 0.010821, 18.42, 1.4e9], ['1000SHIB', 0.017432, 2.94, 3.4e8],
        ['1000BONK', 0.021874, 24.73, 5.6e8], ['WIF', 2.1034, 31.58, 9.8e8], ['FET', 1.312, -8.91, 2.7e8],
        ['RENDER', 6.842, -7.64, 1.4e8], ['ORDI', 34.21, 9.73, 2.3e8], ['STX', 1.684, -2.13, 9.1e7],
        ['FIL', 4.412, -1.18, 1.2e8], ['ATOM', 6.734, -0.92, 1.1e8], ['ETC', 22.14, -1.41, 1.3e8],
        ['XLM', 0.0943, 0.37, 6.2e7], ['HBAR', 0.0712, -2.84, 7.4e7], ['ICP', 8.821, -11.37, 2.1e8],
        ['IMX', 1.421, -3.36, 6.8e7], ['LDO', 1.612, -4.02, 9.4e7], ['AAVE', 94.31, 3.12, 1.6e8],
        ['UNI', 7.412, -2.25, 1.7e8], ['MKR', 2412.0, -1.64, 7.1e7], ['CRV', 0.3021, -9.84, 8.6e7],
        ['DYDX', 1.542, -6.12, 6.4e7], ['GALA', 0.02412, 7.35, 7.9e7], ['SAND', 0.3312, -1.71, 5.4e7],
        ['APE', 0.8731, -12.84, 1.1e8], ['1000FLOKI', 0.14234, 14.66, 3.1e8], ['JUP', 0.9412, -5.47, 1.3e8],
        ['PYTH', 0.3812, -3.72, 7.7e7], ['ENA', 0.5421, 21.37, 7.3e8], ['ETHFI', 2.812, -14.26, 1.9e8],
        ['ONDO', 1.0834, 4.57, 2.4e8], ['PENDLE', 4.412, -10.63, 1.2e8], ['WLD', 2.341, 12.94, 4.4e8],
        ['STRK', 0.6412, -7.21, 9.9e7], ['ZK', 0.1634, -17.38, 2.6e8], ['NOT', 0.01521, 16.21, 4.1e8],
        ['1000SATS', 0.000312, -4.84, 6.1e7], ['BOME', 0.009312, 13.47, 3.6e8], ['PEOPLE', 0.08121, -21.64, 3.3e8],
    ];

    public static function futuresTickers(): array
    {
        $out = [];
        foreach (self::FUTURES as [$base, $price, $pct, $volume]) {
            $out[] = self::ticker($base . 'USDT', $price, $pct, $volume);
        }
        $out[] = self::ticker('DEADUSDT', 0.0421, 95.0, 5e8);
        $out[] = self::ticker('BTCUSDT_251226', 65890.0, 12.0, 4e8);
        $out[] = self::ticker('ETHUSDC', 3121.0, 30.0, 6e8);
        $out[] = self::ticker('TINYUSDT', 0.0021, 60.0, 2e5);
        $zero = self::ticker('ZEROUSDT', 0.12, -80.0, 9e8);
        $zero['count'] = 0;
        $out[] = $zero;

        return $out;
    }

    public static function futuresExchangeInfo(): array
    {
        $symbols = [];
        foreach (self::FUTURES as [$base]) {
            $symbols[] = self::contract($base . 'USDT', $base, 'USDT', 'PERPETUAL', 'TRADING');
        }
        $symbols[] = self::contract('DEADUSDT', 'DEAD', 'USDT', 'PERPETUAL', 'SETTLING');
        $symbols[] = self::contract('BTCUSDT_251226', 'BTC', 'USDT', 'CURRENT_QUARTER', 'TRADING');
        $symbols[] = self::contract('ETHUSDC', 'ETH', 'USDC', 'PERPETUAL', 'TRADING');
        $symbols[] = self::contract('TINYUSDT', 'TINY', 'USDT', 'PERPETUAL', 'TRADING');
        $symbols[] = self::contract('ZEROUSDT', 'ZERO', 'USDT', 'PERPETUAL', 'TRADING');

        return ['timezone' => 'UTC', 'symbols' => $symbols];
    }

    private static function ticker(string $pair, float $price, float $pct, float $quoteVolume): array
    {
        $open = $price / (1 + $pct / 100);

        return [
            'symbol'             => $pair,
            'priceChange'        => (string) round($price - $open, 8),
            'priceChangePercent' => number_format($pct, 3, '.', ''),
            'weightedAvgPrice'   => (string) round(($price + $open) / 2, 8),
            'lastPrice'          => (string) $price,
            'openPrice'          => (string) round($open, 8),
            'highPrice'          => (string) round(max($price, $open) * 1.021, 8),
            'lowPrice'           => (string) round(min($price, $open) * 0.978, 8),
            'volume'             => (string) round($quoteVolume / $price, 3),
            'quoteVolume'        => (string) $quoteVolume,
            'count'              => 184233,
        ];
    }

    private static function contract(string $pair, string $base, string $quote, string $type, string $status): array
    {
        return [
            'symbol'       => $pair,
            'pair'         => $base . $quote,
            'contractType' => $type,
            'status'       => $status,
            'baseAsset'    => $base,
            'quoteAsset'   => $quote,
        ];
    }

    public static function series(int $seed, int $points, float $changePct, float $end = 100.0): array
    {
        mt_srand($seed);
        $start = $end / (1 + $changePct / 100);
        $out = [];
        $wobble = 0.0;
        for ($i = 0; $i < $points; $i++) {
            $t = $i / max(1, $points - 1);
            $wobble = $wobble * 0.6 + mt_rand(-60, 60) / 10000;
            $out[] = round(($start + ($end - $start) * $t) * (1 + $wobble), 8);
        }
        $out[$points - 1] = $end;
        mt_srand();

        return $out;
    }

    public const BTC_MID = 64210.5;

    public static function btcDepth(string $market): array
    {
        $futures = $market === 'futures';
        mt_srand($futures ? 7001 : 7002);
        $levels = $futures ? 1000 : 5000;
        $tick = $futures ? 0.1 : 0.01;
        $meanGap = $futures ? 1.6 : 0.55;

        $walls = $futures
            ? ['bids' => [63800 => 44.0, 63500 => 78.0, 63150 => 36.0, 62900 => 31.0],
               'asks' => [64600 => 51.0, 65000 => 108.0, 65400 => 40.0, 65750 => 33.0]]
            : ['bids' => [63500 => 34.0, 62800 => 58.0, 62500 => 41.0, 63950 => 22.0],
               'asks' => [65000 => 69.0, 66000 => 88.0, 65400 => 21.0, 66400 => 37.0]];

        $book = ['lastUpdateId' => 4815162342, 'bids' => [], 'asks' => []];
        foreach (['bids' => -1, 'asks' => 1] as $side => $dir) {
            $price = self::BTC_MID + $dir * $tick * 5;
            $planted = $walls[$side];
            for ($i = 0; $i < $levels; $i++) {
                $price += $dir * max($tick, round((mt_rand(1, 200) / 100) * $meanGap / $tick) * $tick);
                $qty = (mt_rand(1, 100) <= 92 ? mt_rand(2, 180) / 1000 : mt_rand(200, 2600) / 1000) * ($futures ? 1.6 : 1.0);
                foreach ($planted as $wallPrice => $wallQty) {
                    $d = abs($price - $wallPrice);
                    if ($d < 40) {
                        $qty += $wallQty * (1 - $d / 40) / 12;
                    }
                }
                $book[$side][] = [number_format($price, 2, '.', ''), number_format($qty, 3, '.', '')];
            }
        }
        mt_srand();

        return $book;
    }

    public static function btcKlines(): array
    {
        $closes = self::series(9101, 48, 2.4, self::BTC_MID);
        mt_srand(9102);
        $out = [];
        $start = (int) (floor(time() / 3600) - 47) * 3600;
        $prev = $closes[0] * 0.998;
        foreach ($closes as $i => $close) {
            $open = $prev;
            $high = max($open, $close) * (1 + mt_rand(5, 45) / 10000);
            $low = min($open, $close) * (1 - mt_rand(5, 45) / 10000);
            $t = ($start + $i * 3600) * 1000;
            $out[] = [$t, (string) round($open, 2), (string) round($high, 2), (string) round($low, 2),
                (string) round($close, 2), '1843.221', $t + 3599999, '118300422.19', 51234, '921.1', '59111203.4', '0'];
            $prev = $close;
        }
        mt_srand();

        return $out;
    }
}

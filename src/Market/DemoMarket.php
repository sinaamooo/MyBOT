<?php

declare(strict_types=1);

namespace App\Market;

/**
 * Deterministic synthetic market (random walk with trending regimes and volatility clusters).
 * Used for testing the bot and the card design without internet access.
 */
final class DemoMarket
{
    private const BASE_PRICES = [
        'BTC' => 98450.0, 'ETH' => 3650.0, 'SOL' => 186.4, 'BNB' => 712.0, 'XRP' => 2.41,
        'DOGE' => 0.238, 'TON' => 5.82, 'ADA' => 0.89, 'AVAX' => 34.7, 'LINK' => 21.3,
        'PEPE' => 0.00001834, 'SHIB' => 0.00002291, 'TRX' => 0.271, 'DOT' => 6.95,
    ];

    private static array $hourly = [];

    public static function series(string $base, string $quote, string $tf, int $limit): Series
    {
        $tfSec = Series::timeframeSeconds($tf);
        $hours = self::hourly($base);
        $rows = [];
        if ($tfSec < 3600) {
            // Not used for real analysis; split hours so the call still works.
            foreach ($hours as $h) {
                $rows[] = $h;
            }
        } else {
            $bucket = null;
            foreach ($hours as $h) {
                $key = intdiv(intdiv($h[0], 1000), $tfSec);
                if ($bucket === null || $bucket[6] !== $key) {
                    if ($bucket !== null) {
                        $rows[] = array_slice($bucket, 0, 6);
                    }
                    $bucket = [$key * $tfSec * 1000, $h[1], $h[2], $h[3], $h[4], $h[5], $key];
                    continue;
                }
                $bucket[2] = max($bucket[2], $h[2]);
                $bucket[3] = min($bucket[3], $h[3]);
                $bucket[4] = $h[4];
                $bucket[5] += $h[5];
            }
            if ($bucket !== null) {
                $rows[] = array_slice($bucket, 0, 6);
            }
        }
        return Series::fromRows($base . $quote, $tf, 'demo', array_slice($rows, -$limit));
    }

    public static function orderBook(string $base, string $quote): array
    {
        $hours = self::hourly($base);
        $price = $hours[count($hours) - 1][4];
        mt_srand(crc32($base . 'book'));
        $bids = [];
        $asks = [];
        for ($i = 1; $i <= 400; $i++) {
            $step = $price * 0.0002 * $i;
            $bq = (mt_rand(1, 100) / 10) * (mt_rand(0, 40) === 0 ? 25 : 1);
            $aq = (mt_rand(1, 100) / 10) * (mt_rand(0, 40) === 0 ? 25 : 1);
            $bids[] = [$price - $step, $bq * 50000 / $price];
            $asks[] = [$price + $step, $aq * 50000 / $price];
        }
        mt_srand();
        return ['bids' => $bids, 'asks' => $asks];
    }

    /** One year of hourly candles ending at the current hour. */
    private static function hourly(string $base): array
    {
        if (isset(self::$hourly[$base])) {
            return self::$hourly[$base];
        }
        $n = 24 * 400;
        mt_srand(crc32($base));
        $gauss = static function (): float {
            $u = max(mt_rand() / mt_getrandmax(), 1e-12);
            $v = mt_rand() / mt_getrandmax();
            return sqrt(-2 * log($u)) * cos(2 * M_PI * $v);
        };

        $baseVol = 0.0038;
        $var = $baseVol ** 2;
        $drift = 0.0;
        $regimeLeft = 0;
        $logP = 0.0;
        $raw = [];
        for ($i = 0; $i < $n; $i++) {
            if ($regimeLeft-- <= 0) {
                $regimeLeft = mt_rand(60, 320);
                $drift = (mt_rand(-100, 100) / 100) * 0.0011;
            }
            $sigma = sqrt($var);
            $r = $drift + $sigma * $gauss();
            if (mt_rand(0, 400) === 0) {
                $r += (mt_rand(0, 1) ? 1 : -1) * $sigma * mt_rand(4, 8);
            }
            $var = 0.000002 + 0.08 * $r * $r + 0.88 * $var;
            $var = min(max($var, ($baseVol * 0.4) ** 2), ($baseVol * 3) ** 2);
            $open = $logP;
            $logP += $r;
            $close = $logP;
            $hi = max($open, $close) + abs($gauss()) * $sigma * 0.55;
            $lo = min($open, $close) - abs($gauss()) * $sigma * 0.55;
            $vol = exp($gauss() * 0.35) * (1 + 2.5 * abs($r) / $baseVol);
            $raw[] = [$open, $hi, $lo, $close, $vol];
        }
        mt_srand();

        $target = self::BASE_PRICES[$base] ?? (1 + (crc32($base) % 5000) / 1000);
        $shift = log($target) - $raw[$n - 1][3];
        $end = intdiv(time(), 3600) * 3600;
        $start = $end - ($n - 1) * 3600;
        $volScale = 1500000 / $target;
        $rows = [];
        foreach ($raw as $i => [$o, $h, $l, $c, $v]) {
            $rows[] = [($start + $i * 3600) * 1000, exp($o + $shift), exp($h + $shift), exp($l + $shift), exp($c + $shift), $v * $volScale];
        }
        return self::$hourly[$base] = $rows;
    }
}

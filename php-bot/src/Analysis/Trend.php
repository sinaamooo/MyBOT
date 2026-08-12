<?php

declare(strict_types=1);

namespace App\Analysis;

final class Trend
{
    /** @return array{0:int,1:int} [bullishPairs 0-3, bearishPairs 0-3] using the last row of $indexed */
    public static function emaAlignment(array $indexed): array
    {
        $i = count($indexed['close']) - 1;
        $fast = $indexed['ema_fast'][$i];
        $medium = $indexed['ema_medium'][$i];
        $slow = $indexed['ema_slow'][$i];
        $trend = $indexed['ema_trend'][$i];

        $bull = (int) ($fast > $medium) + (int) ($medium > $slow) + (int) ($slow > $trend);
        $bear = (int) ($fast < $medium) + (int) ($medium < $slow) + (int) ($slow < $trend);
        return [$bull, $bear];
    }

    /** Strict trend: full EMA stack alignment AND ADX above the configured minimum. */
    public static function detect(array $indexed, array $params): string
    {
        [$bull, $bear] = self::emaAlignment($indexed);
        $strong = Indicators::last($indexed['adx']) >= (float) $params['min_adx'];

        if ($bull === 3 && $strong) {
            return 'BULLISH';
        }
        if ($bear === 3 && $strong) {
            return 'BEARISH';
        }
        return 'NEUTRAL';
    }
}

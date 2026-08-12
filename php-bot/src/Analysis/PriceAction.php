<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * Lightweight candlestick / price-action pattern detection - only feeds the
 * scoring engine's "Price Action" component, never triggers a signal alone.
 */
final class PriceAction
{
    /** @return array{bullish_engulfing:bool,bearish_engulfing:bool,hammer:bool,shooting_star:bool,breakout:bool,breakdown:bool} */
    public static function detect(array $indexed, int $breakoutLookback = 20): array
    {
        $result = [
            'bullish_engulfing' => false,
            'bearish_engulfing' => false,
            'hammer' => false,
            'shooting_star' => false,
            'breakout' => false,
            'breakdown' => false,
        ];

        $n = count($indexed['close']);
        if ($n < $breakoutLookback + 2) {
            return $result;
        }

        $i = $n - 1;
        $prevOpen = $indexed['open'][$i - 1];
        $prevClose = $indexed['close'][$i - 1];
        $open = $indexed['open'][$i];
        $close = $indexed['close'][$i];
        $high = $indexed['high'][$i];
        $low = $indexed['low'][$i];

        $prevBearish = $prevClose < $prevOpen;
        $prevBullish = $prevClose > $prevOpen;
        $currBullish = $close > $open;
        $currBearish = $close < $open;

        $result['bullish_engulfing'] = $prevBearish && $currBullish && $open <= $prevClose && $close >= $prevOpen;
        $result['bearish_engulfing'] = $prevBullish && $currBearish && $open >= $prevClose && $close <= $prevOpen;

        $body = abs($close - $open);
        $range = $high - $low;
        if ($range > 0) {
            $upperWick = $high - max($close, $open);
            $lowerWick = min($close, $open) - $low;
            $smallBody = $body <= $range * 0.35;
            $result['hammer'] = $smallBody && $lowerWick >= $body * 2 && $upperWick <= $body * 0.5;
            $result['shooting_star'] = $smallBody && $upperWick >= $body * 2 && $lowerWick <= $body * 0.5;
        }

        $windowHigh = array_slice($indexed['high'], $i - $breakoutLookback, $breakoutLookback);
        $windowLow = array_slice($indexed['low'], $i - $breakoutLookback, $breakoutLookback);
        $result['breakout'] = $close > max($windowHigh);
        $result['breakdown'] = $close < min($windowLow);

        return $result;
    }

    public static function bullishHits(array $pa): int
    {
        return (int) $pa['bullish_engulfing'] + (int) $pa['hammer'] + (int) $pa['breakout'];
    }

    public static function bearishHits(array $pa): int
    {
        return (int) $pa['bearish_engulfing'] + (int) $pa['shooting_star'] + (int) $pa['breakdown'];
    }

    /** @return string[] */
    public static function labels(array $pa): array
    {
        return array_keys(array_filter($pa));
    }
}

<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * ICT/SMC Fair Value Gap (FVG) detection: a 3-candle imbalance where the
 * wicks of candle[i-2] and candle[i] don't overlap, left behind by a strong
 * directional (displacement) move. Price is expected to be drawn back into
 * an unfilled gap before continuing in the original direction, so a retest
 * of one is treated as a same-direction support/resistance zone.
 */
final class FairValueGap
{
    /**
     * @return array{
     *   bullish: list<array{top: float, bottom: float, index: int, filled: bool}>,
     *   bearish: list<array{top: float, bottom: float, index: int, filled: bool}>
     * }
     */
    public static function detect(array $indexed, int $lookback = 150): array
    {
        $high = $indexed['high'];
        $low = $indexed['low'];
        $n = count($high);
        $start = max(2, $n - $lookback);

        $bullish = [];
        $bearish = [];

        for ($i = $start; $i < $n; $i++) {
            if ($low[$i] > $high[$i - 2]) {
                $bullish[] = ['top' => $low[$i], 'bottom' => $high[$i - 2], 'index' => $i - 1, 'filled' => false];
            }
            if ($high[$i] < $low[$i - 2]) {
                $bearish[] = ['top' => $low[$i - 2], 'bottom' => $high[$i], 'index' => $i - 1, 'filled' => false];
            }
        }

        // A gap is "filled" once a later candle trades all the way through it.
        foreach ($bullish as &$gap) {
            for ($j = $gap['index'] + 2; $j < $n; $j++) {
                if ($low[$j] <= $gap['bottom']) {
                    $gap['filled'] = true;
                    break;
                }
            }
        }
        unset($gap);
        foreach ($bearish as &$gap) {
            for ($j = $gap['index'] + 2; $j < $n; $j++) {
                if ($high[$j] >= $gap['top']) {
                    $gap['filled'] = true;
                    break;
                }
            }
        }
        unset($gap);

        return ['bullish' => $bullish, 'bearish' => $bearish];
    }

    /**
     * Is the latest close currently sitting inside an unfilled gap in this
     * direction - i.e. price is in the middle of retesting it?
     *
     * @param array{bullish: list<array>, bearish: list<array>} $zones
     * @return array{hit: bool, reason: string}
     */
    public static function evaluate(array $zones, array $indexed, bool $bullish): array
    {
        $close = $indexed['close'];
        $i = count($close) - 1;
        if ($i < 0) {
            return ['hit' => false, 'reason' => ''];
        }
        $price = (float) $close[$i];

        $candidates = array_values(array_filter(
            $bullish ? $zones['bullish'] : $zones['bearish'],
            fn(array $g) => !$g['filled']
        ));
        usort($candidates, fn(array $a, array $b) => $b['index'] <=> $a['index']);

        foreach ($candidates as $gap) {
            if ($price >= $gap['bottom'] && $price <= $gap['top']) {
                $side = $bullish ? 'bullish' : 'bearish';
                return ['hit' => true, 'reason' => "Price filling {$side} FVG"];
            }
        }

        return ['hit' => false, 'reason' => ''];
    }
}

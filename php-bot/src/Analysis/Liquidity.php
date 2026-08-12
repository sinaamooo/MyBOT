<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * ICT/SMC liquidity pools + sweeps. Clustered swing highs mark buyside
 * liquidity (short stop-losses / breakout buy orders resting above);
 * clustered swing lows mark sellside liquidity (long stop-losses / breakout
 * sell orders resting below). A "sweep" is a wick that runs through one of
 * these pools to trigger the resting orders, then closes back on the other
 * side - a classic stop-hunt-then-reverse signal.
 */
final class Liquidity
{
    /** @return array{buyside: float[], sellside: float[]} */
    public static function detectPools(array $indexed, int $order = 3, int $lookback = 150): array
    {
        $levels = SupportResistance::findLevels($indexed, $order, $lookback);
        return ['buyside' => $levels['resistances'], 'sellside' => $levels['supports']];
    }

    /**
     * @param array{buyside: float[], sellside: float[]} $pools
     * @return array{hit: bool, reason: string}
     */
    public static function detectSweep(array $pools, array $indexed, bool $bullish): array
    {
        $open = $indexed['open'];
        $high = $indexed['high'];
        $low = $indexed['low'];
        $close = $indexed['close'];
        $i = count($close) - 1;
        if ($i < 0) {
            return ['hit' => false, 'reason' => ''];
        }

        if ($bullish) {
            foreach ($pools['sellside'] as $level) {
                if ($low[$i] < $level && $close[$i] > $level && $close[$i] > $open[$i]) {
                    return ['hit' => true, 'reason' => 'Bullish liquidity sweep (sellside grabbed, reversed up)'];
                }
            }
        } else {
            foreach ($pools['buyside'] as $level) {
                if ($high[$i] > $level && $close[$i] < $level && $close[$i] < $open[$i]) {
                    return ['hit' => true, 'reason' => 'Bearish liquidity sweep (buyside grabbed, reversed down)'];
                }
            }
        }

        return ['hit' => false, 'reason' => ''];
    }
}

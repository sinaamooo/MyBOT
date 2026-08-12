<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * ICT/SMC market structure: tracks the sequence of confirmed fractal swing
 * highs/lows to classify the most recent structural break as either a BOS
 * (Break of Structure - price breaks past the last swing in the direction
 * of the prevailing bias, confirming trend continuation) or a CHoCH (Change
 * of Character - price breaks against the prevailing bias, the earliest
 * warning of a trend reversal and the bias flips from that point on).
 */
final class MarketStructure
{
    /**
     * @return array{bias: string, last_break: string, last_break_index: int|null}
     *   bias/last_break in {'BULLISH','BEARISH','NEUTRAL'} / {'BOS_BULLISH','BOS_BEARISH','CHOCH_BULLISH','CHOCH_BEARISH','NONE'}
     */
    public static function detect(array $indexed, int $swingLen = 5, int $lookback = 150): array
    {
        $high = $indexed['high'];
        $low = $indexed['low'];
        $close = $indexed['close'];
        $n = count($close);
        $start = max($swingLen, $n - $lookback);

        if ($n - $swingLen <= $start) {
            return ['bias' => 'NEUTRAL', 'last_break' => 'NONE', 'last_break_index' => null];
        }

        $swingHighs = [];
        $swingLows = [];
        for ($i = $start; $i < $n - $swingLen; $i++) {
            $windowHigh = array_slice($high, $i - $swingLen, 2 * $swingLen + 1);
            $windowLow = array_slice($low, $i - $swingLen, 2 * $swingLen + 1);
            if ($high[$i] == max($windowHigh)) {
                $swingHighs[] = ['index' => $i, 'level' => $high[$i]];
            }
            if ($low[$i] == min($windowLow)) {
                $swingLows[] = ['index' => $i, 'level' => $low[$i]];
            }
        }

        $bias = 'NEUTRAL';
        $lastBreak = 'NONE';
        $lastBreakIndex = null;

        $hPtr = 0;
        $lPtr = 0;
        $activeHigh = null;
        $activeLow = null;

        for ($i = $start; $i < $n; $i++) {
            while ($hPtr < count($swingHighs) && $swingHighs[$hPtr]['index'] + $swingLen <= $i) {
                $activeHigh = $swingHighs[$hPtr];
                $hPtr++;
            }
            while ($lPtr < count($swingLows) && $swingLows[$lPtr]['index'] + $swingLen <= $i) {
                $activeLow = $swingLows[$lPtr];
                $lPtr++;
            }

            if ($activeHigh !== null && $close[$i] > $activeHigh['level']) {
                $lastBreak = $bias === 'BEARISH' ? 'CHOCH_BULLISH' : 'BOS_BULLISH';
                $bias = 'BULLISH';
                $lastBreakIndex = $i;
                $activeHigh = null;
            }

            if ($activeLow !== null && $close[$i] < $activeLow['level']) {
                $lastBreak = $bias === 'BULLISH' ? 'CHOCH_BEARISH' : 'BOS_BEARISH';
                $bias = 'BEARISH';
                $lastBreakIndex = $i;
                $activeLow = null;
            }
        }

        return ['bias' => $bias, 'last_break' => $lastBreak, 'last_break_index' => $lastBreakIndex];
    }

    /**
     * Was the most recent structural break - within the last $freshnessBars
     * candles, so it's still relevant to the current setup - a BOS or CHoCH
     * in this trade's direction?
     *
     * @param array{bias: string, last_break: string, last_break_index: int|null} $structure
     * @return array{hit: bool, reason: string}
     */
    public static function evaluate(array $structure, array $indexed, bool $bullish, int $freshnessBars = 12): array
    {
        $lastBreak = $structure['last_break'];
        $lastIndex = $structure['last_break_index'];
        if ($lastBreak === 'NONE' || $lastIndex === null) {
            return ['hit' => false, 'reason' => ''];
        }

        $n = count($indexed['close']);
        if (($n - 1) - $lastIndex > $freshnessBars) {
            return ['hit' => false, 'reason' => ''];
        }

        $match = $bullish
            ? in_array($lastBreak, ['BOS_BULLISH', 'CHOCH_BULLISH'], true)
            : in_array($lastBreak, ['BOS_BEARISH', 'CHOCH_BEARISH'], true);
        if (!$match) {
            return ['hit' => false, 'reason' => ''];
        }

        $label = str_starts_with($lastBreak, 'CHOCH') ? 'CHoCH' : 'BOS';
        $side = $bullish ? 'bullish' : 'bearish';
        return ['hit' => true, 'reason' => "Market structure {$label} ({$side})"];
    }
}

<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * ICT/SMC Order Block + Breaker Block detection. An Order Block is the last
 * opposite-colour candle before a Break of Structure (BOS) - the zone
 * where institutional orders are presumed to rest, expected to act as
 * support/resistance on a retest. Once price closes all the way through a
 * zone, it is "broken": the zone flips polarity and becomes a Breaker Block
 * expected to hold in the opposite direction on the next retest.
 */
final class OrderBlocks
{
    private const SEARCH_BACK = 15;
    private const MIN_PROXIMITY_PCT = 0.0015;

    /**
     * @return array{
     *   bullish: list<array{top: float, bottom: float, index: int, broken: bool}>,
     *   bearish: list<array{top: float, bottom: float, index: int, broken: bool}>
     * }
     */
    public static function detect(array $indexed, int $swingLen = 5, int $lookback = 150): array
    {
        $open = $indexed['open'];
        $high = $indexed['high'];
        $low = $indexed['low'];
        $close = $indexed['close'];
        $n = count($close);
        $start = max($swingLen, $n - $lookback);

        if ($n - $swingLen <= $start) {
            return ['bullish' => [], 'bearish' => []];
        }

        // Pass 1: fractal swing highs/lows, in chronological order. A swing at
        // index $i is only confirmed once $swingLen bars have printed after it.
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

        // Pass 2: walk forward, tracking the most recently confirmed swing not
        // yet broken; a close beyond it is a Break of Structure. The Order
        // Block is the last opposite-colour candle before that breakout.
        $bullish = [];
        $bearish = [];
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
                $obIndex = self::lastOppositeCandle($open, $close, $i, true);
                if ($obIndex !== null) {
                    $bullish[] = ['top' => $high[$obIndex], 'bottom' => $low[$obIndex], 'index' => $obIndex, 'broken' => false];
                }
                $activeHigh = null;
            }

            if ($activeLow !== null && $close[$i] < $activeLow['level']) {
                $obIndex = self::lastOppositeCandle($open, $close, $i, false);
                if ($obIndex !== null) {
                    $bearish[] = ['top' => $high[$obIndex], 'bottom' => $low[$obIndex], 'index' => $obIndex, 'broken' => false];
                }
                $activeLow = null;
            }
        }

        // Pass 3: a zone is "broken" once a later close fully violates it -
        // it then flips into a Breaker Block for the opposite side.
        foreach ($bullish as &$zone) {
            for ($j = $zone['index'] + 1; $j < $n; $j++) {
                if ($close[$j] < $zone['bottom']) {
                    $zone['broken'] = true;
                    break;
                }
            }
        }
        unset($zone);
        foreach ($bearish as &$zone) {
            for ($j = $zone['index'] + 1; $j < $n; $j++) {
                if ($close[$j] > $zone['top']) {
                    $zone['broken'] = true;
                    break;
                }
            }
        }
        unset($zone);

        return ['bullish' => $bullish, 'bearish' => $bearish];
    }

    /** Scans backward from just before $breakoutIndex for the last candle against the breakout's direction. */
    private static function lastOppositeCandle(array $open, array $close, int $breakoutIndex, bool $bullishMove): ?int
    {
        $floor = max(0, $breakoutIndex - self::SEARCH_BACK);
        for ($k = $breakoutIndex - 1; $k >= $floor; $k--) {
            $isBearishCandle = $close[$k] < $open[$k];
            if ($bullishMove && $isBearishCandle) {
                return $k;
            }
            if (!$bullishMove && !$isBearishCandle && $close[$k] > $open[$k]) {
                return $k;
            }
        }
        return null;
    }

    /**
     * Does the latest close sit inside (or just outside, within a small
     * tolerance of) a zone that supports a trade in this direction - either
     * an unbroken Order Block on our side, or an opposite-side Order Block
     * that has since broken and flipped into a Breaker Block for us?
     *
     * @param array{bullish: list<array>, bearish: list<array>} $zones
     * @return array{hit: bool, breaker: bool, reason: string}
     */
    public static function evaluate(array $zones, array $indexed, bool $bullish): array
    {
        $close = $indexed['close'];
        $i = count($close) - 1;
        if ($i < 0) {
            return ['hit' => false, 'breaker' => false, 'reason' => ''];
        }
        $price = (float) $close[$i];

        $sameSide = $bullish ? $zones['bullish'] : $zones['bearish'];
        $oppositeSide = $bullish ? $zones['bearish'] : $zones['bullish'];

        $candidates = [];
        foreach ($sameSide as $zone) {
            if (!$zone['broken']) {
                $candidates[] = ['zone' => $zone, 'breaker' => false];
            }
        }
        foreach ($oppositeSide as $zone) {
            if ($zone['broken']) {
                $candidates[] = ['zone' => $zone, 'breaker' => true];
            }
        }
        // Prefer the most recently formed zone.
        usort($candidates, fn(array $a, array $b) => $b['zone']['index'] <=> $a['zone']['index']);

        foreach ($candidates as $candidate) {
            $zone = $candidate['zone'];
            $tolerance = max($zone['top'] - $zone['bottom'], $price * self::MIN_PROXIMITY_PCT) * 0.2;
            if ($price >= $zone['bottom'] - $tolerance && $price <= $zone['top'] + $tolerance) {
                $side = $bullish ? 'bullish' : 'bearish';
                $label = $candidate['breaker'] ? 'Breaker Block' : 'Order Block';
                return ['hit' => true, 'breaker' => $candidate['breaker'], 'reason' => "Price at {$side} {$label}"];
            }
        }

        return ['hit' => false, 'breaker' => false, 'reason' => ''];
    }
}

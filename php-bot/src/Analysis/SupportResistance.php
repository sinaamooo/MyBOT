<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * Fractal-based swing high/low & support/resistance detection, so Take
 * Profit targets don't land directly inside an obvious S/R zone.
 */
final class SupportResistance
{
    /** @return array{resistances: float[], supports: float[]} */
    public static function findLevels(array $indexed, int $order = 3, int $lookback = 150): array
    {
        $high = $indexed['high'];
        $low = $indexed['low'];
        $n = count($high);
        $start = max($order, $n - $lookback);

        $swingHighs = [];
        $swingLows = [];
        for ($i = $start; $i < $n - $order; $i++) {
            $windowHigh = array_slice($high, $i - $order, 2 * $order + 1);
            $windowLow = array_slice($low, $i - $order, 2 * $order + 1);
            if ($high[$i] == max($windowHigh)) {
                $swingHighs[] = $high[$i];
            }
            if ($low[$i] == min($windowLow)) {
                $swingLows[] = $low[$i];
            }
        }

        return [
            'resistances' => self::cluster($swingHighs),
            'supports' => self::cluster($swingLows),
        ];
    }

    /** @param float[] $levels @return float[] */
    private static function cluster(array $levels, float $tolerancePct = 0.0025): array
    {
        if ($levels === []) {
            return [];
        }
        sort($levels);
        $clusters = [[$levels[0]]];
        for ($k = 1, $count = count($levels); $k < $count; $k++) {
            $lvl = $levels[$k];
            $lastIndex = count($clusters) - 1;
            $lastVal = end($clusters[$lastIndex]);
            if ($lastVal != 0.0 && abs($lvl - $lastVal) / $lastVal <= $tolerancePct) {
                $clusters[$lastIndex][] = $lvl;
            } else {
                $clusters[] = [$lvl];
            }
        }
        return array_map(fn(array $c) => array_sum($c) / count($c), $clusters);
    }

    /**
     * Pulls a TP back to just in front of the nearest S/R level sitting
     * between entry and the raw target, instead of placing it inside/beyond it.
     *
     * @param array{resistances: float[], supports: float[]} $levels
     */
    public static function adjustTargetForLevels(float $target, string $side, float $entry, array $levels, float $bufferPct = 0.0015): float
    {
        if ($side === 'LONG') {
            $blocking = array_filter($levels['resistances'], fn(float $r) => $r > $entry && $r <= $target);
            if ($blocking !== []) {
                $nearest = min($blocking);
                return round($nearest * (1 - $bufferPct), 8);
            }
        } else {
            $blocking = array_filter($levels['supports'], fn(float $s) => $s >= $target && $s < $entry);
            if ($blocking !== []) {
                $nearest = max($blocking);
                return round($nearest * (1 + $bufferPct), 8);
            }
        }
        return $target;
    }
}

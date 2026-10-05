<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * Classic indicators. Every function returns an array aligned with the input
 * (index i = value at candle i, null while there is not enough history).
 */
final class Indicators
{
    public static function sma(array $values, int $period): array
    {
        $out = array_fill(0, count($values), null);
        $sum = 0.0;
        foreach ($values as $i => $v) {
            $sum += $v;
            if ($i >= $period) {
                $sum -= $values[$i - $period];
            }
            if ($i >= $period - 1) {
                $out[$i] = $sum / $period;
            }
        }
        return $out;
    }

    public static function ema(array $values, int $period): array
    {
        $n = count($values);
        $out = array_fill(0, $n, null);
        if ($n < $period) {
            return $out;
        }
        $k = 2 / ($period + 1);
        $prev = array_sum(array_slice($values, 0, $period)) / $period;
        $out[$period - 1] = $prev;
        for ($i = $period; $i < $n; $i++) {
            $prev = $values[$i] * $k + $prev * (1 - $k);
            $out[$i] = $prev;
        }
        return $out;
    }

    /** Wilder's RSI */
    public static function rsi(array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        if ($n <= $period) {
            return $out;
        }
        $gain = 0.0;
        $loss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $d = $closes[$i] - $closes[$i - 1];
            $gain += max($d, 0);
            $loss += max(-$d, 0);
        }
        $gain /= $period;
        $loss /= $period;
        $out[$period] = $loss == 0.0 ? 100.0 : 100 - 100 / (1 + $gain / $loss);
        for ($i = $period + 1; $i < $n; $i++) {
            $d = $closes[$i] - $closes[$i - 1];
            $gain = ($gain * ($period - 1) + max($d, 0)) / $period;
            $loss = ($loss * ($period - 1) + max(-$d, 0)) / $period;
            $out[$i] = $loss == 0.0 ? 100.0 : 100 - 100 / (1 + $gain / $loss);
        }
        return $out;
    }

    /** Wilder's ATR */
    public static function atr(array $highs, array $lows, array $closes, int $period = 14): array
    {
        $n = count($closes);
        $out = array_fill(0, $n, null);
        if ($n <= $period) {
            return $out;
        }
        $tr = [];
        for ($i = 0; $i < $n; $i++) {
            $tr[$i] = $i === 0
                ? $highs[0] - $lows[0]
                : max($highs[$i] - $lows[$i], abs($highs[$i] - $closes[$i - 1]), abs($lows[$i] - $closes[$i - 1]));
        }
        $prev = array_sum(array_slice($tr, 1, $period)) / $period;
        $out[$period] = $prev;
        for ($i = $period + 1; $i < $n; $i++) {
            $prev = ($prev * ($period - 1) + $tr[$i]) / $period;
            $out[$i] = $prev;
        }
        return $out;
    }

    /** @return array{macd: array, signal: array, hist: array} */
    public static function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $n = count($closes);
        $emaFast = self::ema($closes, $fast);
        $emaSlow = self::ema($closes, $slow);
        $macd = array_fill(0, $n, null);
        $compact = [];
        for ($i = 0; $i < $n; $i++) {
            if ($emaFast[$i] !== null && $emaSlow[$i] !== null) {
                $macd[$i] = $emaFast[$i] - $emaSlow[$i];
                $compact[$i] = $macd[$i];
            }
        }
        $sig = array_fill(0, $n, null);
        $hist = array_fill(0, $n, null);
        if ($compact) {
            $keys = array_keys($compact);
            $sigCompact = self::ema(array_values($compact), $signal);
            foreach ($keys as $j => $i) {
                if ($sigCompact[$j] !== null) {
                    $sig[$i] = $sigCompact[$j];
                    $hist[$i] = $macd[$i] - $sig[$i];
                }
            }
        }
        return ['macd' => $macd, 'signal' => $sig, 'hist' => $hist];
    }

    /** @return array{upper: array, middle: array, lower: array} */
    public static function bollinger(array $closes, int $period = 20, float $mult = 2.0): array
    {
        $n = count($closes);
        $mid = self::sma($closes, $period);
        $up = array_fill(0, $n, null);
        $lo = array_fill(0, $n, null);
        for ($i = $period - 1; $i < $n; $i++) {
            $slice = array_slice($closes, $i - $period + 1, $period);
            $mean = $mid[$i];
            $var = 0.0;
            foreach ($slice as $v) {
                $var += ($v - $mean) ** 2;
            }
            $sd = sqrt($var / $period);
            $up[$i] = $mean + $mult * $sd;
            $lo[$i] = $mean - $mult * $sd;
        }
        return ['upper' => $up, 'middle' => $mid, 'lower' => $lo];
    }

    public static function last(array $series, int $offset = 0)
    {
        $i = count($series) - 1 - $offset;
        return $i >= 0 ? $series[$i] : null;
    }
}

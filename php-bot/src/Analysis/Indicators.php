<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * Manual, dependency-free indicator math operating on plain PHP arrays
 * (no bcmath/pandas equivalent needed). Mirrors the Python version's
 * indicators.py formulas exactly (EWM with adjust=False semantics, Wilder
 * smoothing for RSI/ATR/ADX) so behavior matches across both codebases.
 *
 * All series are plain 0-indexed arrays aligned to the input candles
 * (oldest -> newest), so `end($series)` is always "the latest closed bar".
 */
final class Indicators
{
    /** @param float[] $values @return float[] */
    public static function ema(array $values, int $period): array
    {
        $alpha = 2.0 / ($period + 1);
        $result = [];
        $prev = null;
        foreach ($values as $i => $v) {
            $prev = $prev === null ? $v : ($alpha * $v + (1 - $alpha) * $prev);
            $result[$i] = $prev;
        }
        return $result;
    }

    /** Wilder smoothing: EWM with alpha = 1/period, adjust=False. @param float[] $values @return float[] */
    private static function wilder(array $values, int $period): array
    {
        return self::ema($values, 2 * $period - 1); // span such that alpha = 2/(span+1) = 1/period
    }

    /** @param float[] $closes @return float[] */
    public static function rsi(array $closes, int $period): array
    {
        $n = count($closes);
        $gains = [];
        $losses = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $gains[$i] = 0.0;
                $losses[$i] = 0.0;
                continue;
            }
            $delta = $closes[$i] - $closes[$i - 1];
            $gains[$i] = $delta > 0 ? $delta : 0.0;
            $losses[$i] = $delta < 0 ? -$delta : 0.0;
        }
        $avgGain = self::wilder($gains, $period);
        $avgLoss = self::wilder($losses, $period);

        $rsi = [];
        for ($i = 0; $i < $n; $i++) {
            if ($avgLoss[$i] == 0.0 && $avgGain[$i] == 0.0) {
                $rsi[$i] = 50.0;
            } elseif ($avgLoss[$i] == 0.0) {
                $rsi[$i] = 100.0;
            } else {
                $rs = $avgGain[$i] / $avgLoss[$i];
                $rsi[$i] = 100.0 - (100.0 / (1.0 + $rs));
            }
        }
        return $rsi;
    }

    /** @param float[] $closes @return array{0: float[], 1: float[], 2: float[]} [macd, signal, histogram] */
    public static function macd(array $closes, int $fast, int $slow, int $signal): array
    {
        $emaFast = self::ema($closes, $fast);
        $emaSlow = self::ema($closes, $slow);
        $macdLine = [];
        foreach ($closes as $i => $_) {
            $macdLine[$i] = $emaFast[$i] - $emaSlow[$i];
        }
        $signalLine = self::ema($macdLine, $signal);
        $hist = [];
        foreach ($macdLine as $i => $v) {
            $hist[$i] = $v - $signalLine[$i];
        }
        return [$macdLine, $signalLine, $hist];
    }

    /** @param array<int, array{open:float,high:float,low:float,close:float,volume:float}> $candles @return float[] */
    public static function trueRange(array $candles): array
    {
        $tr = [];
        foreach ($candles as $i => $c) {
            if ($i === 0) {
                $tr[$i] = $c['high'] - $c['low'];
                continue;
            }
            $prevClose = $candles[$i - 1]['close'];
            $tr[$i] = max($c['high'] - $c['low'], abs($c['high'] - $prevClose), abs($c['low'] - $prevClose));
        }
        return $tr;
    }

    /** @return float[] */
    public static function atr(array $candles, int $period): array
    {
        return self::wilder(self::trueRange($candles), $period);
    }

    /** @return array{0: float[], 1: float[], 2: float[]} [adx, plusDI, minusDI] */
    public static function adx(array $candles, int $period): array
    {
        $n = count($candles);
        $plusDm = [];
        $minusDm = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i === 0) {
                $plusDm[$i] = 0.0;
                $minusDm[$i] = 0.0;
                continue;
            }
            $upMove = $candles[$i]['high'] - $candles[$i - 1]['high'];
            $downMove = $candles[$i - 1]['low'] - $candles[$i]['low'];
            $plusDm[$i] = ($upMove > $downMove && $upMove > 0) ? $upMove : 0.0;
            $minusDm[$i] = ($downMove > $upMove && $downMove > 0) ? $downMove : 0.0;
        }

        $atr = self::wilder(self::trueRange($candles), $period);
        $smoothedPlusDm = self::wilder($plusDm, $period);
        $smoothedMinusDm = self::wilder($minusDm, $period);

        $plusDi = [];
        $minusDi = [];
        $dx = [];
        for ($i = 0; $i < $n; $i++) {
            $plusDi[$i] = $atr[$i] != 0.0 ? 100 * $smoothedPlusDm[$i] / $atr[$i] : 0.0;
            $minusDi[$i] = $atr[$i] != 0.0 ? 100 * $smoothedMinusDm[$i] / $atr[$i] : 0.0;
            $sum = $plusDi[$i] + $minusDi[$i];
            $dx[$i] = $sum != 0.0 ? 100 * abs($plusDi[$i] - $minusDi[$i]) / $sum : 0.0;
        }
        $adx = self::wilder($dx, $period);
        return [$adx, $plusDi, $minusDi];
    }

    /** @param float[] $closes @return array{0: float[], 1: float[], 2: float[], 3: float[]} [mid, upper, lower, width] */
    public static function bollingerBands(array $closes, int $period, float $std): array
    {
        $n = count($closes);
        $mid = [];
        $upper = [];
        $lower = [];
        $width = [];
        for ($i = 0; $i < $n; $i++) {
            if ($i < $period - 1) {
                $window = array_slice($closes, 0, $i + 1);
            } else {
                $window = array_slice($closes, $i - $period + 1, $period);
            }
            $mean = array_sum($window) / count($window);
            $variance = 0.0;
            if (count($window) > 1) {
                foreach ($window as $v) {
                    $variance += ($v - $mean) ** 2;
                }
                $variance /= (count($window) - 1); // sample stdev, ddof=1 (matches pandas default)
            }
            $sd = sqrt($variance);
            $mid[$i] = $mean;
            $upper[$i] = $mean + $std * $sd;
            $lower[$i] = $mean - $std * $sd;
            $width[$i] = $mean != 0.0 ? ($upper[$i] - $lower[$i]) / $mean : 0.0;
        }
        return [$mid, $upper, $lower, $width];
    }

    /** @param float[] $closes @return array{0: float[], 1: float[]} [k, d] */
    public static function stochasticRsi(array $closes, int $rsiPeriod, int $k, int $d): array
    {
        $rsi = self::rsi($closes, $rsiPeriod);
        $n = count($rsi);
        $stoch = [];
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $rsiPeriod + 1);
            $window = array_slice($rsi, $start, $i - $start + 1);
            $min = min($window);
            $max = max($window);
            $stoch[$i] = ($max - $min) != 0.0 ? ($rsi[$i] - $min) / ($max - $min) * 100 : 50.0;
        }
        $kLine = self::simpleMovingAverage($stoch, $k);
        $dLine = self::simpleMovingAverage($kLine, $d);
        return [$kLine, $dLine];
    }

    /** @param float[] $values @return float[] */
    public static function simpleMovingAverage(array $values, int $period): array
    {
        $n = count($values);
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            $start = max(0, $i - $period + 1);
            $window = array_slice($values, $start, $i - $start + 1);
            $result[$i] = array_sum($window) / count($window);
        }
        return $result;
    }

    /**
     * @param array<int, array{ts:int,open:float,high:float,low:float,close:float,volume:float}> $candles
     * @param array<string, mixed> $params
     * @return array<string, array> columnar indicator table, index-aligned with $candles
     */
    public static function compute(array $candles, array $params): array
    {
        $open = array_column($candles, 'open');
        $high = array_column($candles, 'high');
        $low = array_column($candles, 'low');
        $close = array_column($candles, 'close');
        $volume = array_column($candles, 'volume');

        [$macdLine, $macdSignal, $macdHist] = self::macd($close, (int) $params['macd_fast'], (int) $params['macd_slow'], (int) $params['macd_signal']);
        [$adx, $plusDi, $minusDi] = self::adx($candles, (int) $params['adx_period']);
        [$bbMid, $bbUpper, $bbLower, $bbWidth] = self::bollingerBands($close, (int) $params['bb_period'], (float) $params['bb_std']);
        [$stochK, $stochD] = self::stochasticRsi($close, (int) $params['stoch_rsi_period'], (int) $params['stoch_k'], (int) $params['stoch_d']);

        return [
            'open' => $open,
            'high' => $high,
            'low' => $low,
            'close' => $close,
            'volume' => $volume,
            'ema_fast' => self::ema($close, (int) $params['ema_fast']),
            'ema_medium' => self::ema($close, (int) $params['ema_medium']),
            'ema_slow' => self::ema($close, (int) $params['ema_slow']),
            'ema_trend' => self::ema($close, (int) $params['ema_trend']),
            'rsi' => self::rsi($close, (int) $params['rsi_period']),
            'macd' => $macdLine,
            'macd_signal' => $macdSignal,
            'macd_hist' => $macdHist,
            'adx' => $adx,
            'plus_di' => $plusDi,
            'minus_di' => $minusDi,
            'atr' => self::atr($candles, (int) $params['atr_period']),
            'bb_mid' => $bbMid,
            'bb_upper' => $bbUpper,
            'bb_lower' => $bbLower,
            'bb_width' => $bbWidth,
            'stoch_rsi_k' => $stochK,
            'stoch_rsi_d' => $stochD,
            'volume_ma' => self::simpleMovingAverage($volume, (int) $params['volume_ma_period']),
        ];
    }

    /** Convenience: latest value of a computed column. */
    public static function last(array $series): float
    {
        if ($series === []) {
            return 0.0;
        }
        return (float) $series[count($series) - 1];
    }
}

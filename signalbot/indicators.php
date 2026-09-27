<?php

declare(strict_types=1);

// Ports of the TradingView indicators supplied for this bot. Every engine works on
// closed candles only (the caller drops the live candle) and never looks ahead, so the
// output of bar i depends only on bars 0..i — the same guarantee as "non-repainting"
// in Pine. Engines return plain arrays so the strategy and the backtest can share them.

final class Series
{
    public static function opens(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->open, $candles);
    }

    public static function highs(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->high, $candles);
    }

    public static function lows(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->low, $candles);
    }

    public static function hlc3(array $candles): array
    {
        return array_map(static fn(Candle $c) => ($c->high + $c->low + $c->close) / 3, $candles);
    }

    public static function hl2(array $candles): array
    {
        return array_map(static fn(Candle $c) => ($c->high + $c->low) / 2, $candles);
    }

    public static function trueRange(array $candles): array
    {
        $out = [];
        foreach ($candles as $i => $c) {
            if ($i === 0) {
                $out[] = $c->high - $c->low;
                continue;
            }
            $pc = $candles[$i - 1]->close;
            $out[] = max($c->high - $c->low, abs($c->high - $pc), abs($c->low - $pc));
        }
        return $out;
    }

    // Pine ta.ema: SMA seed over the first `len` valid values, NaN-aware.
    public static function ema(array $src, int $len): array
    {
        return self::smoothed($src, $len, 2.0 / ($len + 1));
    }

    // Pine ta.rma (Wilder): SMA seed, alpha = 1/len.
    public static function rma(array $src, int $len): array
    {
        return self::smoothed($src, $len, 1.0 / $len);
    }

    private static function smoothed(array $src, int $len, float $alpha): array
    {
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        $len = max(1, $len);
        $run = 0;
        $sum = 0.0;
        $prev = NAN;
        for ($i = 0; $i < $n; $i++) {
            $v = $src[$i];
            if (is_nan($v)) {
                if (!is_nan($prev)) {
                    $out[$i] = $prev;
                }
                $run = 0;
                $sum = 0.0;
                continue;
            }
            if (is_nan($prev)) {
                $run++;
                $sum += $v;
                if ($run >= $len) {
                    $prev = $sum / $len;
                    $out[$i] = $prev;
                }
                continue;
            }
            $prev = $alpha * $v + (1 - $alpha) * $prev;
            $out[$i] = $prev;
        }
        return $out;
    }

    public static function sma(array $src, int $len): array
    {
        return Ta::sma(array_values($src), max(1, $len));
    }

    public static function wma(array $src, int $len): array
    {
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        $len = max(1, $len);
        $den = $len * ($len + 1) / 2;
        for ($i = $len - 1; $i < $n; $i++) {
            $sum = 0.0;
            $ok = true;
            for ($k = 0; $k < $len; $k++) {
                $v = $src[$i - $k];
                if (is_nan($v)) {
                    $ok = false;
                    break;
                }
                $sum += $v * ($len - $k);
            }
            if ($ok) {
                $out[$i] = $sum / $den;
            }
        }
        return $out;
    }

    public static function hma(array $src, int $len): array
    {
        $half = self::wma($src, max(1, intdiv($len, 2)));
        $full = self::wma($src, $len);
        $diff = [];
        foreach ($half as $i => $h) {
            $diff[$i] = (is_nan($h) || is_nan($full[$i])) ? NAN : 2 * $h - $full[$i];
        }
        return self::wma($diff, max(1, (int) round(sqrt($len))));
    }

    public static function stdev(array $src, int $len): array
    {
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        for ($i = $len - 1; $i < $n; $i++) {
            $slice = array_slice($src, $i - $len + 1, $len);
            if (in_array(true, array_map('is_nan', $slice), true)) {
                continue;
            }
            $mean = array_sum($slice) / $len;
            $acc = 0.0;
            foreach ($slice as $v) {
                $acc += ($v - $mean) ** 2;
            }
            $out[$i] = sqrt($acc / $len);
        }
        return $out;
    }

    public static function atr(array $candles, int $len): array
    {
        return self::rma(self::trueRange($candles), $len);
    }

    public static function rsi(array $src, int $len): array
    {
        return Ta::rsi(array_values($src), $len);
    }

    // Pine ta.dmi: returns [+DI, -DI, ADX] series.
    public static function dmi(array $candles, int $diLen, int $adxLen): array
    {
        $n = count($candles);
        $plusDm = [0.0];
        $minusDm = [0.0];
        for ($i = 1; $i < $n; $i++) {
            $up = $candles[$i]->high - $candles[$i - 1]->high;
            $down = $candles[$i - 1]->low - $candles[$i]->low;
            $plusDm[] = ($up > $down && $up > 0) ? $up : 0.0;
            $minusDm[] = ($down > $up && $down > 0) ? $down : 0.0;
        }
        $tr = self::rma(self::trueRange($candles), $diLen);
        $pSm = self::rma($plusDm, $diLen);
        $mSm = self::rma($minusDm, $diLen);
        $plus = array_fill(0, $n, NAN);
        $minus = array_fill(0, $n, NAN);
        $dx = array_fill(0, $n, NAN);
        for ($i = 0; $i < $n; $i++) {
            if (is_nan($tr[$i]) || $tr[$i] <= 0 || is_nan($pSm[$i]) || is_nan($mSm[$i])) {
                continue;
            }
            $plus[$i] = 100 * $pSm[$i] / $tr[$i];
            $minus[$i] = 100 * $mSm[$i] / $tr[$i];
            $sum = $plus[$i] + $minus[$i];
            $dx[$i] = abs($plus[$i] - $minus[$i]) / ($sum == 0.0 ? 1.0 : $sum);
        }
        $adx = self::rma($dx, $adxLen);
        foreach ($adx as $i => $v) {
            $adx[$i] = is_nan($v) ? NAN : 100 * $v;
        }
        return [$plus, $minus, $adx];
    }

    // Ehlers two-pole Super Smoother (MRC mean line).
    public static function superSmoother(array $src, int $len): array
    {
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        $a1 = exp(-M_SQRT2 * M_PI / $len);
        $b1 = 2 * $a1 * cos(M_SQRT2 * M_PI / $len);
        $c3 = -$a1 * $a1;
        $c2 = $b1;
        $c1 = 1 - $c2 - $c3;
        for ($i = 0; $i < $n; $i++) {
            $p1 = $i >= 1 ? (is_nan($out[$i - 1]) ? $src[$i - 1] : $out[$i - 1]) : $src[$i];
            $p2 = $i >= 2 ? (is_nan($out[$i - 2]) ? $src[$i - 2] : $out[$i - 2]) : $p1;
            $out[$i] = $c1 * $src[$i] + $c2 * $p1 + $c3 * $p2;
        }
        return $out;
    }

    public static function mcGinley(array $src, int $len): array
    {
        $ema = self::ema($src, $len);
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        $prev = NAN;
        for ($i = 0; $i < $n; $i++) {
            if (is_nan($prev)) {
                $prev = $ema[$i];
                $out[$i] = $prev;
                continue;
            }
            $ratio = $prev != 0.0 ? $src[$i] / $prev : 1.0;
            $prev = $prev + ($src[$i] - $prev) / ($len * $ratio ** 4);
            $out[$i] = $prev;
        }
        return $out;
    }

    public static function last(array $series, int $back = 0): float
    {
        $n = count($series);
        return $n - 1 - $back >= 0 ? (float) $series[$n - 1 - $back] : NAN;
    }

    public static function barsSince(array $flags): ?int
    {
        for ($i = count($flags) - 1; $i >= 0; $i--) {
            if ($flags[$i]) {
                return count($flags) - 1 - $i;
            }
        }
        return null;
    }

    public static function dirOf(int $state): ?string
    {
        return $state > 0 ? 'bullish' : ($state < 0 ? 'bearish' : null);
    }
}

// Twin Range Filter (colinmck) — smoothed range filter with a fast and a slow band.
final class TwinRangeFilter
{
    public static function analyse(array $candles): array
    {
        $none = ['trend' => null, 'flip_age' => null, 'filter' => null];
        $n = count($candles);
        if ($n < 120) {
            return $none;
        }
        $x = Ta::closes($candles);
        $r1 = self::smoothRange($x, 27, 1.6);
        $r2 = self::smoothRange($x, 55, 2.0);

        $filt = NAN;
        $up = 0;
        $down = 0;
        $state = 0;
        $flips = array_fill(0, $n, false);
        for ($i = 1; $i < $n; $i++) {
            if (is_nan($r1[$i]) || is_nan($r2[$i])) {
                continue;
            }
            $r = ($r1[$i] + $r2[$i]) / 2;
            $prev = $filt;
            if (is_nan($prev)) {
                $filt = $x[$i];
                continue;
            }
            $filt = $x[$i] > $prev
                ? ($x[$i] - $r < $prev ? $prev : $x[$i] - $r)
                : ($x[$i] + $r > $prev ? $prev : $x[$i] + $r);
            if ($filt > $prev) {
                $up++;
                $down = 0;
            } elseif ($filt < $prev) {
                $down++;
                $up = 0;
            }
            $long = $x[$i] > $filt && $up > 0;
            $short = $x[$i] < $filt && $down > 0;
            $before = $state;
            $state = $long ? 1 : ($short ? -1 : $state);
            $flips[$i] = $before !== 0 && $state !== $before;
        }

        return ['trend' => Series::dirOf($state), 'flip_age' => Series::barsSince($flips), 'filter' => is_nan($filt) ? null : $filt];
    }

    private static function smoothRange(array $x, int $t, float $m): array
    {
        $diff = [NAN];
        for ($i = 1; $i < count($x); $i++) {
            $diff[] = abs($x[$i] - $x[$i - 1]);
        }
        $avg = Series::ema($diff, $t);
        $smooth = Series::ema($avg, $t * 2 - 1);
        return array_map(static fn(float $v) => is_nan($v) ? NAN : $v * $m, $smooth);
    }
}

// Gain Buy Sell (GBS) — Bollinger breakout that drives a ratcheting ATR stop line.
final class GainBuySell
{
    public static function analyse(array $candles, int $atrLen = 5, int $bbLen = 21, float $bbDev = 1.0): array
    {
        $none = ['trend' => null, 'flip_age' => null, 'line' => null];
        $n = count($candles);
        if ($n < $bbLen + 10) {
            return $none;
        }
        $closes = Ta::closes($candles);
        $basis = Series::sma($closes, $bbLen);
        $dev = Series::stdev($closes, $bbLen);
        $atr = Series::atr($candles, $atrLen);

        $bb = 0;
        $line = NAN;
        $trend = 0;
        $flips = array_fill(0, $n, false);
        for ($i = 0; $i < $n; $i++) {
            if (is_nan($basis[$i]) || is_nan($dev[$i]) || is_nan($atr[$i])) {
                continue;
            }
            $c = $candles[$i];
            if ($c->close > $basis[$i] + $dev[$i] * $bbDev) {
                $bb = 1;
            } elseif ($c->close < $basis[$i] - $dev[$i] * $bbDev) {
                $bb = -1;
            }
            $prev = $line;
            $next = $line;
            if ($bb === 1) {
                $next = $c->low - $atr[$i];
                if (!is_nan($prev) && $next < $prev) {
                    $next = $prev;
                }
            } elseif ($bb === -1) {
                $next = $c->high + $atr[$i];
                if (!is_nan($prev) && $next > $prev) {
                    $next = $prev;
                }
            }
            $line = $next;
            if (is_nan($prev) || is_nan($line)) {
                continue;
            }
            $before = $trend;
            if ($line > $prev) {
                $trend = 1;
            } elseif ($line < $prev) {
                $trend = -1;
            }
            $flips[$i] = $before !== 0 && $trend !== $before;
        }
        return ['trend' => Series::dirOf($trend), 'flip_age' => Series::barsSince($flips), 'line' => is_nan($line) ? null : $line];
    }
}

// SSL Channel Pro — EMA(high)/EMA(low) channel state plus the DMI/RSI/width quality score.
final class SslChannel
{
    public static function analyse(array $candles, int $len = 10): array
    {
        $none = ['trend' => null, 'flip_age' => null, 'adx' => null, 'plus_di' => null, 'minus_di' => null, 'quality' => 0.0, 'confirmed' => null];
        $n = count($candles);
        if ($n < 60) {
            return $none;
        }
        $highMa = Series::ema(Series::highs($candles), $len);
        $lowMa = Series::ema(Series::lows($candles), $len);
        $closes = Ta::closes($candles);
        $rsi = Series::rsi($closes, 14);
        [$plus, $minus, $adx] = Series::dmi($candles, 14, 14);
        $atr = Series::atr($candles, 14);
        $volMa = Series::sma(Ta::volumes($candles), 20);

        $state = 1;
        $states = array_fill(0, $n, 1);
        $flips = array_fill(0, $n, false);
        $flipIndex = null;
        for ($i = 0; $i < $n; $i++) {
            if (is_nan($highMa[$i]) || is_nan($lowMa[$i])) {
                continue;
            }
            $before = $state;
            $c = $closes[$i];
            $state = $c > $highMa[$i] ? 1 : ($c < $lowMa[$i] ? -1 : $state);
            $states[$i] = $state;
            if ($state !== $before) {
                $flips[$i] = true;
                $flipIndex = $i;
            }
        }

        $last = $n - 1;
        $a = $adx[$last];
        $p = $plus[$last];
        $m = $minus[$last];
        $atrNow = $atr[$last];
        if (is_nan($a) || is_nan($p) || is_nan($m) || is_nan($atrNow) || $atrNow <= 0) {
            return array_merge($none, ['trend' => Series::dirOf($state), 'flip_age' => Series::barsSince($flips)]);
        }

        $isLong = $state === 1;
        $width = abs($highMa[$last] - $lowMa[$last]) / $atrNow;
        $rsiNow = $rsi[$last];
        $rsiDelta = $isLong ? $rsiNow - 50 : 50 - $rsiNow;
        $momentum = self::scale($rsiDelta, -10.0, 15.0) * 16.0 + (($isLong ? $rsiNow > $rsi[$last - 1] : $rsiNow < $rsi[$last - 1]) ? 4.0 : 0.0);
        $diTotal = max($p + $m, 0.0001);
        $dominance = max(0.0, min(1.0, ($isLong ? $p - $m : $m - $p) / $diTotal));
        $aligned = $isLong ? $p > $m : $m > $p;
        $trendScore = $aligned
            ? 6.0 + self::scale($a, 20.0, 45.0) * 8.0 + $dominance * 4.0 + ($a > $adx[$last - 1] ? 2.0 : 0.0)
            : 0.0;
        $strong = $width >= 0.20;
        $prevAtr = $atr[$last - 1];
        $prevWidth = !is_nan($prevAtr) && $prevAtr > 0 ? abs($highMa[$last - 1] - $lowMa[$last - 1]) / $prevAtr : $width;
        $regime = $strong
            ? 8.0 + self::scale($width, 0.20, 0.50) * 4.0 + ($width > $prevWidth ? 3.0 : 0.0)
            : self::scale($width, 0.0, 0.20) * 8.0;
        $relVol = !is_nan($volMa[$last]) && $volMa[$last] > 0 ? $candles[$last]->volume / $volMa[$last] : NAN;
        $participation = is_nan($relVol) ? 5.0 : self::scale($relVol, 0.70, 1.70) * 10.0;
        $sslLine = $isLong ? $lowMa[$last] : $highMa[$last];
        $prevSslLine = $states[$last - 1] === 1 ? $lowMa[$last - 1] : $highMa[$last - 1];
        $context = (($isLong ? $sslLine > $prevSslLine : $sslLine < $prevSslLine) ? 5.0 : 0.0)
            + self::scale(max(0.0, ($isLong ? $closes[$last] - $sslLine : $sslLine - $closes[$last])) / $atrNow, 0.0, 1.0) * 5.0;
        $range = max($candles[$last]->high - $candles[$last]->low, 1e-12);
        $opposingWick = $isLong
            ? ($candles[$last]->high - max($candles[$last]->open, $candles[$last]->close)) / $range
            : (min($candles[$last]->open, $candles[$last]->close) - $candles[$last]->low) / $range;
        $extension = abs($closes[$last] - $sslLine) / $atrNow;
        $penalty = $opposingWick * 4.0 + self::scale($extension, 1.75, 3.50) * 6.0;
        $live = $momentum + $trendScore + $regime + $participation + $context - $penalty;
        $quality = max(0.0, min(100.0, $live / 75.0 * 100.0));

        $confirmed = null;
        if ($flipIndex === $last) {
            $confirmations = ($isLong ? $rsiNow > 50 : $rsiNow < 50) && $a > 20.0 && $aligned && $strong;
            $breakout = $isLong ? max($closes[$last] - $highMa[$last], 0.0) : max($lowMa[$last] - $closes[$last], 0.0);
            $closeLoc = $isLong ? ($closes[$last] - $candles[$last]->low) / $range : ($candles[$last]->high - $closes[$last]) / $range;
            $transition = 12.0 + self::scale($breakout / $atrNow, 0.0, 0.40) * 6.0 + ($candles[$last]->bodySize() / $range) * 4.0 + $closeLoc * 3.0;
            if ($confirmations && $transition + $live >= 70.0) {
                $confirmed = $isLong ? 'long' : 'short';
            }
        }

        return [
            'trend' => Series::dirOf($state),
            'flip_age' => Series::barsSince($flips),
            'adx' => $a,
            'plus_di' => $p,
            'minus_di' => $m,
            'quality' => round($quality, 1),
            'confirmed' => $confirmed,
        ];
    }

    private static function scale(float $v, float $lo, float $hi): float
    {
        return $hi > $lo ? max(0.0, min(1.0, ($v - $lo) / ($hi - $lo))) : 0.0;
    }
}

// WaveTrend (Cipher B) — oscillator crosses from overbought/oversold plus fractal divergences.
final class WaveTrend
{
    public const OB = 53.0;
    public const OS = -53.0;
    public const EXTREME = 60.0;

    public static function analyse(array $candles, int $chLen = 9, int $avgLen = 12, int $maLen = 3): array
    {
        $none = ['wt1' => null, 'wt2' => null, 'signal' => null, 'signal_age' => null, 'divergence' => null, 'overbought' => false, 'oversold' => false];
        $n = count($candles);
        if ($n < 60) {
            return $none;
        }
        $src = Series::hlc3($candles);
        $esa = Series::ema($src, $chLen);
        $absDev = [];
        foreach ($src as $i => $v) {
            $absDev[$i] = is_nan($esa[$i]) ? NAN : abs($v - $esa[$i]);
        }
        $de = Series::ema($absDev, $chLen);
        $ci = [];
        foreach ($src as $i => $v) {
            $ci[$i] = (is_nan($esa[$i]) || is_nan($de[$i]) || $de[$i] == 0.0) ? NAN : ($v - $esa[$i]) / (0.015 * $de[$i]);
        }
        $wt1 = Series::ema($ci, $avgLen);
        $wt2 = Series::sma($wt1, $maLen);

        $signal = null;
        $signalAge = null;
        for ($k = 0; $k <= 2; $k++) {
            $i = $n - 1 - $k;
            if ($i < 1 || is_nan($wt1[$i]) || is_nan($wt2[$i]) || is_nan($wt1[$i - 1]) || is_nan($wt2[$i - 1])) {
                continue;
            }
            $crossUp = $wt1[$i - 1] <= $wt2[$i - 1] && $wt1[$i] > $wt2[$i];
            $crossDown = $wt1[$i - 1] >= $wt2[$i - 1] && $wt1[$i] < $wt2[$i];
            if ($crossUp && $wt2[$i] <= self::OS) {
                $signal = 'long';
                $signalAge = $k;
                break;
            }
            if ($crossDown && $wt2[$i] >= self::OB) {
                $signal = 'short';
                $signalAge = $k;
                break;
            }
        }

        $w2 = Series::last($wt2);
        return [
            'wt1' => Series::last($wt1),
            'wt2' => $w2,
            'signal' => $signal,
            'signal_age' => $signalAge,
            'divergence' => self::divergence($candles, $wt2),
            'overbought' => !is_nan($w2) && $w2 >= self::EXTREME,
            'oversold' => !is_nan($w2) && $w2 <= -self::EXTREME,
        ];
    }

    // f_findDivs: 5-bar fractal on wt2 compared with the previous fractal of the same kind.
    private static function divergence(array $candles, array $wt2): ?string
    {
        $n = count($wt2);
        $topPrev = null;
        $botPrev = null;
        $result = null;
        for ($i = 4; $i < $n; $i++) {
            $s = [$wt2[$i - 4], $wt2[$i - 3], $wt2[$i - 2], $wt2[$i - 1], $wt2[$i]];
            if (in_array(true, array_map('is_nan', $s), true)) {
                continue;
            }
            $isTop = $s[0] < $s[2] && $s[1] < $s[2] && $s[2] > $s[3] && $s[2] > $s[4];
            $isBot = $s[0] > $s[2] && $s[1] > $s[2] && $s[2] < $s[3] && $s[2] < $s[4];
            $pivot = $i - 2;
            $fresh = $i >= $n - 2;
            if ($isTop && $s[2] >= 45.0) {
                if ($fresh && $topPrev !== null && $candles[$pivot]->high > $topPrev['price'] && $s[2] < $topPrev['osc']) {
                    $result = 'bearish';
                }
                $topPrev = ['price' => $candles[$pivot]->high, 'osc' => $s[2]];
            }
            if ($isBot && $s[2] <= -65.0) {
                if ($fresh && $botPrev !== null && $candles[$pivot]->low < $botPrev['price'] && $s[2] > $botPrev['osc']) {
                    $result = 'bullish';
                }
                $botPrev = ['price' => $candles[$pivot]->low, 'osc' => $s[2]];
            }
        }
        return $result;
    }
}

// TD Sequential setup count (Reversal Signals): nine closes beyond close[4] mark exhaustion.
final class TdSequential
{
    public static function analyse(array $candles): array
    {
        $n = count($candles);
        $up = 0;
        $down = 0;
        $upDone = null;
        $downDone = null;
        for ($i = 4; $i < $n; $i++) {
            if ($candles[$i]->close < $candles[$i - 4]->close) {
                $down = $down === 9 ? 1 : $down + 1;
                $up = 0;
                if ($down === 9) {
                    $downDone = $i;
                }
            } else {
                $up = $up === 9 ? 1 : $up + 1;
                $down = 0;
                if ($up === 9) {
                    $upDone = $i;
                }
            }
        }
        return [
            'up_count' => $up,
            'down_count' => $down,
            // Nine higher closes = buyers exhausted (bearish), nine lower closes = sellers exhausted.
            'up_exhausted_age' => $upDone === null ? null : $n - 1 - $upDone,
            'down_exhausted_age' => $downDone === null ? null : $n - 1 - $downDone,
        ];
    }
}

// Braid Filter (Infinity Algo) — McGinley 3/7/20 separation versus ATR; grey = chop.
final class BraidFilter
{
    public static function analyse(array $candles, float $minSepPercent = 60.0): array
    {
        $n = count($candles);
        if ($n < 40) {
            return ['state' => null, 'separation' => 0.0];
        }
        $ma1 = Series::mcGinley(Ta::closes($candles), 3);
        $ma2 = Series::mcGinley(Series::opens($candles), 7);
        $ma3 = Series::mcGinley(Ta::closes($candles), 20);
        $atr = Series::atr($candles, 14);
        $a = Series::last($ma1);
        $b = Series::last($ma2);
        $c = Series::last($ma3);
        $atrNow = Series::last($atr);
        if (is_nan($a) || is_nan($b) || is_nan($c) || is_nan($atrNow) || $atrNow <= 0) {
            return ['state' => null, 'separation' => 0.0];
        }
        $dif = max($a, $b, $c) - min($a, $b, $c);
        $filter = $atrNow * $minSepPercent / 100;
        $state = $dif <= $filter ? 'chop' : ($a > $b ? 'bullish' : 'bearish');
        return ['state' => $state, 'separation' => round($dif / $atrNow, 3)];
    }
}

// Classic ATR SuperTrend. (11, 4) on close is the NAS / Mutanabby / Diamond signal line and
// flips on the current band; (40, 3.2) on hl2 is the slow HSN Algo line and flips on the
// previous band, exactly as the two Pine scripts do.
final class ClassicSuperTrend
{
    public static function analyse(array $candles, int $atrLen, float $factor, bool $hsn = false): array
    {
        $n = count($candles);
        if ($n < $atrLen + 5) {
            return ['trend' => null, 'flip_age' => null, 'line' => null];
        }
        $atr = Series::atr($candles, $atrLen);
        $upper = NAN;
        $lower = NAN;
        $trend = 0;
        $line = NAN;
        $flips = array_fill(0, $n, false);
        for ($i = 1; $i < $n; $i++) {
            if (is_nan($atr[$i])) {
                continue;
            }
            $c = $candles[$i];
            $src = $hsn ? ($c->high + $c->low) / 2 : $c->close;
            $up = $src + $factor * $atr[$i];
            $dn = $src - $factor * $atr[$i];
            $prevClose = $candles[$i - 1]->close;
            $newLower = is_nan($lower) || $dn > $lower || $prevClose < $lower ? $dn : $lower;
            $newUpper = is_nan($upper) || $up < $upper || $prevClose > $upper ? $up : $upper;
            $refLower = $hsn && !is_nan($lower) ? $lower : $newLower;
            $refUpper = $hsn && !is_nan($upper) ? $upper : $newUpper;
            $before = $trend;
            if ($trend === 0) {
                $trend = $c->close >= ($newLower + $newUpper) / 2 ? 1 : -1;
            } elseif ($trend === -1 && $c->close > $refUpper) {
                $trend = 1;
            } elseif ($trend === 1 && $c->close < $refLower) {
                $trend = -1;
            }
            $lower = $newLower;
            $upper = $newUpper;
            $line = $trend === 1 ? $lower : $upper;
            $flips[$i] = $before !== 0 && $trend !== $before;
        }
        return ['trend' => Series::dirOf($trend), 'flip_age' => Series::barsSince($flips), 'line' => is_nan($line) ? null : $line];
    }
}

// UT Bot — ATR trailing stop (key 2, ATR 6) with position state.
final class UtBot
{
    public static function analyse(array $candles, float $key = 2.0, int $atrLen = 6): array
    {
        $n = count($candles);
        if ($n < $atrLen + 5) {
            return ['trend' => null, 'flip_age' => null];
        }
        $atr = Series::atr($candles, $atrLen);
        $stop = 0.0;
        $pos = 0;
        $flips = array_fill(0, $n, false);
        for ($i = 1; $i < $n; $i++) {
            if (is_nan($atr[$i])) {
                continue;
            }
            $src = $candles[$i]->close;
            $prevSrc = $candles[$i - 1]->close;
            $loss = $key * $atr[$i];
            $prevStop = $stop;
            if ($src > $prevStop && $prevSrc > $prevStop) {
                $stop = max($prevStop, $src - $loss);
            } elseif ($src < $prevStop && $prevSrc < $prevStop) {
                $stop = min($prevStop, $src + $loss);
            } else {
                $stop = $src > $prevStop ? $src - $loss : $src + $loss;
            }
            $before = $pos;
            if ($prevSrc < $prevStop && $src > $prevStop) {
                $pos = 1;
            } elseif ($prevSrc > $prevStop && $src < $prevStop) {
                $pos = -1;
            }
            $flips[$i] = $before !== 0 && $pos !== $before;
        }
        return ['trend' => Series::dirOf($pos), 'flip_age' => Series::barsSince($flips)];
    }
}

// Hull Suite — HMA(55) compared with its value two bars back.
final class HullTrend
{
    public static function analyse(array $candles, int $len = 55): array
    {
        $hma = Series::hma(Ta::closes($candles), $len);
        $now = Series::last($hma);
        $prev = Series::last($hma, 2);
        if (is_nan($now) || is_nan($prev)) {
            return ['trend' => null];
        }
        return ['trend' => $now > $prev ? 'bullish' : ($now < $prev ? 'bearish' : null)];
    }
}

// Swing Failure Pattern (Advanced SMC) — 20/20 swing taken by a wick, no close beyond it,
// then three closes back inside confirm the failure.
final class SwingFailure
{
    public static function detect(array $candles, int $len = 20, int $confirmBars = 3, int $maxAge = 3): array
    {
        $none = ['direction' => null, 'age' => null, 'level' => null, 'extreme' => null];
        $n = count($candles);
        if ($n < $len * 2 + $confirmBars + 5) {
            return $none;
        }
        $highs = Series::highs($candles);
        $lows = Series::lows($candles);
        $closes = Ta::closes($candles);
        $pivLow = array_flip(Ta::pivots($lows, $len, $len, false));
        $pivHigh = array_flip(Ta::pivots($highs, $len, $len, true));

        $pLow = array_fill(0, $n, NAN);
        $pHigh = array_fill(0, $n, NAN);
        $curLow = NAN;
        $curHigh = NAN;
        for ($i = 0; $i < $n; $i++) {
            if (isset($pivLow[$i - $len])) {
                $curLow = $lows[$i - $len];
            }
            if (isset($pivHigh[$i - $len])) {
                $curHigh = $highs[$i - $len];
            }
            $pLow[$i] = $curLow;
            $pHigh[$i] = $curHigh;
        }

        $bull = array_fill(0, $n, false);
        $bear = array_fill(0, $n, false);
        for ($i = $len; $i < $n; $i++) {
            $c = $candles[$i];
            $window = array_slice($candles, $i - $len + 1, $len);
            $lowest = min(array_map(static fn(Candle $x) => $x->low, $window));
            $highest = max(array_map(static fn(Candle $x) => $x->high, $window));
            $lowestClose = min(array_map(static fn(Candle $x) => $x->close, $window));
            $highestClose = max(array_map(static fn(Candle $x) => $x->close, $window));
            $pl = $pLow[$i];
            $ph = $pHigh[$i];
            $bull[$i] = !is_nan($pl) && $c->low < $pl && $c->close > $pl && $c->open > $pl && $c->low <= $lowest && $lowestClose >= $pl;
            $bear[$i] = !is_nan($ph) && $c->high > $ph && $c->close < $ph && $c->open < $ph && $c->high >= $highest && $highestClose <= $ph;
        }

        for ($age = 0; $age <= $maxAge; $age++) {
            $i = $n - 1 - $age;
            $s = $i - $confirmBars;
            if ($s < 0) {
                break;
            }
            if ($bull[$s]) {
                $held = true;
                for ($k = 0; $k < $confirmBars; $k++) {
                    if (!($closes[$i - $k] > $pLow[$i - $k])) {
                        $held = false;
                    }
                }
                if ($held) {
                    return ['direction' => 'bullish', 'age' => $age, 'level' => $pLow[$s], 'extreme' => $lows[$s]];
                }
            }
            if ($bear[$s]) {
                $held = true;
                for ($k = 0; $k < $confirmBars; $k++) {
                    if (!($closes[$i - $k] < $pHigh[$i - $k])) {
                        $held = false;
                    }
                }
                if ($held) {
                    return ['direction' => 'bearish', 'age' => $age, 'level' => $pHigh[$s], 'extreme' => $highs[$s]];
                }
            }
        }
        return $none;
    }
}

// Advanced Liquidity Sweep — major (10) and minor (5) swing levels, equal-level clustering and a
// 0-100 sweep strength score (wick, pierce, volume, body, EMA distance, equal touches).
final class LiquiditySweepScore
{
    public static function detect(array $candles, int $maxAge = 3): array
    {
        $none = ['direction' => null, 'score' => 0.0, 'age' => null, 'equal' => false, 'level' => null];
        $n = count($candles);
        if ($n < 60) {
            return $none;
        }
        $atr = Series::atr($candles, 14);
        $ema50 = Series::ema(Ta::closes($candles), 50);
        $volSma = Series::sma(Ta::volumes($candles), 20);
        $highs = Series::highs($candles);
        $lows = Series::lows($candles);

        $births = [];
        foreach ([10, 5] as $len) {
            foreach (Ta::pivots($highs, $len, $len, true) as $idx) {
                $births[$idx + $len][] = ['price' => $highs[$idx], 'high' => true];
            }
            foreach (Ta::pivots($lows, $len, $len, false) as $idx) {
                $births[$idx + $len][] = ['price' => $lows[$idx], 'high' => false];
            }
        }

        $levels = [];
        $best = $none;
        for ($i = 0; $i < $n; $i++) {
            $tol = is_nan($atr[$i]) ? 0.0 : $atr[$i] * 0.10;
            foreach ($births[$i] ?? [] as $b) {
                $dup = false;
                $touches = 1;
                foreach ($levels as &$lv) {
                    if ($lv['high'] === $b['high'] && abs($lv['price'] - $b['price']) <= max($tol, 1e-12)) {
                        if (abs($lv['price'] - $b['price']) < 1e-12) {
                            $dup = true;
                        } else {
                            $lv['touches']++;
                            $touches = $lv['touches'];
                        }
                        break;
                    }
                }
                unset($lv);
                if (!$dup) {
                    $levels[] = ['price' => $b['price'], 'high' => $b['high'], 'touches' => $touches];
                }
            }
            if (count($levels) > 40) {
                $levels = array_slice($levels, -40);
            }
            if (is_nan($atr[$i]) || $atr[$i] <= 0) {
                continue;
            }
            $c = $candles[$i];
            foreach ($levels as $k => $lv) {
                $swept = null;
                if ($lv['high'] && $c->high > $lv['price'] && $c->close < $lv['price']) {
                    $swept = 'bearish';
                } elseif (!$lv['high'] && $c->low < $lv['price'] && $c->close > $lv['price']) {
                    $swept = 'bullish';
                } elseif ($lv['high'] ? $c->close > $lv['price'] : $c->close < $lv['price']) {
                    unset($levels[$k]);
                    continue;
                }
                if ($swept === null) {
                    continue;
                }
                unset($levels[$k]);
                if ($i < $n - 1 - $maxAge) {
                    continue;
                }
                $score = self::score($c, $swept === 'bullish', $swept === 'bullish' ? $lv['price'] - $c->low : $c->high - $lv['price'], $lv['touches'], $atr[$i], $volSma[$i], $ema50[$i]);
                if ($score > $best['score']) {
                    $best = ['direction' => $swept, 'score' => round($score, 1), 'age' => $n - 1 - $i, 'equal' => $lv['touches'] > 1, 'level' => $lv['price']];
                }
            }
            $levels = array_values($levels);
        }
        return $best;
    }

    private static function score(Candle $c, bool $bullish, float $pierce, int $touches, float $atr, float $volSma, float $ema): float
    {
        $rng = max($c->high - $c->low, 1e-12);
        $wick = max($bullish ? ($c->close - $c->low) / $rng : ($c->high - $c->close) / $rng, 0.0);
        $body = min(max($bullish ? $c->close - $c->open : $c->open - $c->close, 0.0) / $rng, 1.0);
        $pierceScore = $atr > 0 ? min($pierce / $atr, 1.0) : 0.0;
        $vol = !is_nan($volSma) && $volSma > 0 ? min($c->volume / ($volSma * 2.0), 1.0) : 0.0;
        $emaScore = !is_nan($ema) && $atr > 0 ? min(abs($c->close - $ema) / ($atr * 3.0), 1.0) : 0.0;
        $eq = min($touches / 3.0, 1.0);
        $raw = $wick * 1.0 + $pierceScore * 1.0 + $vol * 0.8 + $body * 1.0 + $emaScore * 0.6 + $eq * 0.8;
        return min($raw / 5.2 * 100.0, 100.0);
    }
}

// Combined Trendlines Breakouts — line through the last two lower highs (or higher lows);
// a close through the projected line is the breakout.
final class TrendlineBreak
{
    public static function detect(array $candles, int $left = 10, int $right = 5, int $maxAge = 3): array
    {
        $none = ['direction' => null, 'age' => null, 'level' => null];
        $n = count($candles);
        if ($n < $left + $right + 20) {
            return $none;
        }
        $highs = Series::highs($candles);
        $lows = Series::lows($candles);
        $closes = Ta::closes($candles);

        $best = $none;
        $ph = Ta::pivots($highs, $left, $right, true);
        if (count($ph) >= 2) {
            [$a, $b] = array_slice($ph, -2);
            if ($highs[$b] < $highs[$a]) {
                $slope = ($highs[$b] - $highs[$a]) / ($b - $a);
                for ($i = max($b + $right + 1, $n - 1 - $maxAge); $i < $n; $i++) {
                    $line = $highs[$a] + $slope * ($i - $a);
                    $prevLine = $highs[$a] + $slope * ($i - 1 - $a);
                    if ($closes[$i - 1] <= $prevLine && $closes[$i] > $line) {
                        $best = ['direction' => 'bullish', 'age' => $n - 1 - $i, 'level' => $line];
                    }
                }
            }
        }
        $pl = Ta::pivots($lows, $left, $right, false);
        if (count($pl) >= 2) {
            [$a, $b] = array_slice($pl, -2);
            if ($lows[$b] > $lows[$a]) {
                $slope = ($lows[$b] - $lows[$a]) / ($b - $a);
                for ($i = max($b + $right + 1, $n - 1 - $maxAge); $i < $n; $i++) {
                    $line = $lows[$a] + $slope * ($i - $a);
                    $prevLine = $lows[$a] + $slope * ($i - 1 - $a);
                    if ($closes[$i - 1] >= $prevLine && $closes[$i] < $line) {
                        $age = $n - 1 - $i;
                        if ($best['direction'] === null || $age < $best['age']) {
                            $best = ['direction' => 'bearish', 'age' => $age, 'level' => $line];
                        }
                    }
                }
            }
        }
        return $best;
    }
}

// PVSRA vector candles (ICT HTF Candles) — climax volume = 2x the 10-bar average or the
// highest volume*range of the last 10 bars.
final class PvsraVolume
{
    public static function analyse(array $candles, int $lookback = 3): array
    {
        $none = ['climax' => null, 'age' => null, 'rising' => 0];
        $n = count($candles);
        if ($n < 12) {
            return $none;
        }
        $vr = array_map(static fn(Candle $c) => $c->volume * ($c->high - $c->low), $candles);
        $rising = 0;
        for ($k = 0; $k < $lookback; $k++) {
            $i = $n - 1 - $k;
            $window = array_slice($candles, $i - 9, 10);
            $avg = array_sum(array_map(static fn(Candle $c) => $c->volume, $window)) / 10;
            $hi = max(array_slice($vr, $i - 9, 10));
            $c = $candles[$i];
            if ($c->volume >= $avg * 2 || $vr[$i] >= $hi) {
                if ($c->close != $c->open) {
                    return ['climax' => $c->close > $c->open ? 'bullish' : 'bearish', 'age' => $k, 'rising' => $rising];
                }
            } elseif ($c->volume >= $avg * 1.5) {
                $rising++;
            }
        }
        return array_merge($none, ['rising' => $rising]);
    }
}

// Mean Reversion Channel (MRC) — SuperSmoother mean ± pi * smoothed true range.
final class MeanReversionChannel
{
    public static function analyse(array $candles, int $len = 200, float $inner = 1.0, float $outer = 2.415): array
    {
        $none = ['zone' => null, 'position' => 0.0, 'mean' => null];
        $n = count($candles);
        if ($n < 100) {
            return $none;
        }
        $len = min($len, $n);
        $mean = Series::last(Series::superSmoother(Series::hlc3($candles), $len));
        $range = Series::last(Series::superSmoother(Series::trueRange($candles), $len));
        if (is_nan($mean) || is_nan($range) || $range <= 0) {
            return $none;
        }
        $close = $candles[$n - 1]->close;
        $innerW = $range * M_PI * $inner;
        $outerW = $range * M_PI * $outer;
        $position = ($close - $mean) / $outerW;
        $zone = match (true) {
            $close >= $mean + $outerW => 'upper_extreme',
            $close >= $mean + $innerW => 'upper',
            $close <= $mean - $outerW => 'lower_extreme',
            $close <= $mean - $innerW => 'lower',
            default => 'mid',
        };
        return ['zone' => $zone, 'position' => round($position, 3), 'mean' => $mean];
    }
}

// Dynamic Swing Anchored VWAP (Zeiierman) — EWMA VWAP re-anchored at every new 50-bar swing.
final class AnchoredSwingVwap
{
    public static function analyse(array $candles, int $period = 50, float $apt = 20.0): array
    {
        $none = ['direction' => null, 'vwap' => null, 'side' => null];
        $n = count($candles);
        if ($n < $period + 5) {
            return $none;
        }
        $ph = NAN;
        $pl = NAN;
        $phL = 0;
        $plL = 0;
        $dir = 0;
        $p = 0.0;
        $vol = 0.0;
        $alpha = 1.0 - exp(-log(2.0) / max(1.0, $apt));
        for ($i = 0; $i < $n; $i++) {
            $from = max(0, $i - $period + 1);
            $win = array_slice($candles, $from, $i - $from + 1);
            $hh = max(array_map(static fn(Candle $c) => $c->high, $win));
            $ll = min(array_map(static fn(Candle $c) => $c->low, $win));
            $c = $candles[$i];
            if ($c->high >= $hh) {
                $ph = $c->high;
                $phL = $i;
            }
            if ($c->low <= $ll) {
                $pl = $c->low;
                $plL = $i;
            }
            $newDir = $phL > $plL ? 1 : -1;
            if ($newDir !== $dir) {
                $dir = $newDir;
                $x = $dir > 0 ? $plL : $phL;
                $y = $dir > 0 ? $pl : $ph;
                if (is_nan($y)) {
                    continue;
                }
                $p = $y * $candles[$x]->volume;
                $vol = $candles[$x]->volume;
                for ($k = $x + 1; $k <= $i; $k++) {
                    $hlc = ($candles[$k]->high + $candles[$k]->low + $candles[$k]->close) / 3;
                    $p = (1 - $alpha) * $p + $alpha * $hlc * $candles[$k]->volume;
                    $vol = (1 - $alpha) * $vol + $alpha * $candles[$k]->volume;
                }
                continue;
            }
            $hlc = ($c->high + $c->low + $c->close) / 3;
            $p = (1 - $alpha) * $p + $alpha * $hlc * $c->volume;
            $vol = (1 - $alpha) * $vol + $alpha * $c->volume;
        }
        if ($vol <= 0 || $dir === 0) {
            return $none;
        }
        $vwap = $p / $vol;
        $close = $candles[$n - 1]->close;
        return [
            // dir > 0: anchored at the swing low under the latest high → rising VWAP acts as support.
            'direction' => $dir > 0 ? 'bullish' : 'bearish',
            'vwap' => $vwap,
            'side' => $close >= $vwap ? 'above' : 'below',
        ];
    }
}

// Market Gravity — unswept liquidity pools scored by strength, distance and freshness;
// net gravity > 0 means the stronger magnet sits above price.
final class LiquidityGravity
{
    public static function analyse(array $candles, int $pivot = 5, float $clusterAtr = 0.5, float $kAtr = 3.0, float $halfLife = 120.0): array
    {
        $none = ['net' => 0.0, 'upper' => null, 'lower' => null, 'upper_score' => 0.0, 'lower_score' => 0.0];
        $n = count($candles);
        if ($n < 60) {
            return $none;
        }
        $atr = Series::last(Series::atr($candles, 14));
        if (is_nan($atr) || $atr <= 0) {
            return $none;
        }
        $highs = Series::highs($candles);
        $lows = Series::lows($candles);
        $volumes = Ta::volumes($candles);
        $avgVol = array_sum($volumes) / max(1, count($volumes));
        $close = $candles[$n - 1]->close;

        $members = [];
        foreach (Ta::pivots($highs, $pivot, $pivot, true) as $i) {
            $members[] = ['price' => $highs[$i], 'high' => true, 'bar' => $i, 'src' => 0.5, 'prom' => self::prominence($candles, $i, true, $pivot, $atr), 'vol' => $volumes[$i]];
        }
        foreach (Ta::pivots($lows, $pivot, $pivot, false) as $i) {
            $members[] = ['price' => $lows[$i], 'high' => false, 'bar' => $i, 'src' => 0.5, 'prom' => self::prominence($candles, $i, false, $pivot, $atr), 'vol' => $volumes[$i]];
        }
        foreach (self::previousDay($candles) as $m) {
            $members[] = $m;
        }

        $alive = [];
        foreach ($members as $m) {
            $taken = false;
            for ($k = $m['bar'] + 1; $k < $n; $k++) {
                if ($m['high'] ? $highs[$k] > $m['price'] + 0.05 * $atr : $lows[$k] < $m['price'] - 0.05 * $atr) {
                    $taken = true;
                    break;
                }
            }
            if (!$taken && ($m['high'] ? $m['price'] > $close : $m['price'] < $close)) {
                $alive[] = $m;
            }
        }
        usort($alive, static fn($a, $b) => $a['price'] <=> $b['price']);

        $pools = [];
        foreach ($alive as $m) {
            $last = count($pools) - 1;
            if ($last >= 0 && $pools[$last]['high'] === $m['high'] && abs($m['price'] - $pools[$last]['hi']) <= $clusterAtr * $atr) {
                $pools[$last]['members'][] = $m;
                $pools[$last]['hi'] = max($pools[$last]['hi'], $m['price']);
                $pools[$last]['lo'] = min($pools[$last]['lo'], $m['price']);
                continue;
            }
            $pools[] = ['high' => $m['high'], 'hi' => $m['price'], 'lo' => $m['price'], 'members' => [$m]];
        }

        $up = [];
        $down = [];
        foreach ($pools as $pool) {
            $count = count($pool['members']);
            $density = min($count / 3.0, 1.0);
            $prom = min(max(array_column($pool['members'], 'prom')) / 3.0, 1.0);
            $touch = min(($count - 1) / 2.0, 1.0);
            $tfImportance = max(array_column($pool['members'], 'src'));
            $vol = $avgVol > 0 ? min((array_sum(array_column($pool['members'], 'vol')) / $count) / ($avgVol * 2.0), 1.0) : 0.0;
            $strength = 100.0 * ($density * 0.30 + $prom * 0.25 + $touch * 0.20 + $tfImportance * 0.15 + $vol * 0.10);
            $mid = ($pool['hi'] + $pool['lo']) / 2;
            $dist = abs($mid - $close) / $atr;
            $access = 1.0 / (1.0 + ($dist / $kAtr) ** 2);
            $age = $n - 1 - max(array_column($pool['members'], 'bar'));
            $fresh = exp(-0.6931471805599453 * $age / ($halfLife * (0.75 + 0.75 * $strength / 100)));
            $gravity = min(100.0, $strength * (0.55 + 0.45 * $access) * (0.70 + 0.30 * $fresh));
            $entry = ['price' => $mid, 'score' => $gravity];
            if ($pool['high']) {
                $up[] = $entry;
            } else {
                $down[] = $entry;
            }
        }
        usort($up, static fn($a, $b) => $b['score'] <=> $a['score']);
        usort($down, static fn($a, $b) => $b['score'] <=> $a['score']);
        $pull = static function (array $side): float {
            $w = [1.0, 0.5, 0.25];
            $sum = 0.0;
            foreach (array_slice($side, 0, 3) as $i => $p) {
                $sum += $p['score'] * $w[$i];
            }
            return $sum;
        };
        $upPull = $pull($up);
        $downPull = $pull($down);
        $total = $upPull + $downPull;
        return [
            'net' => $total > 0 ? round(($upPull - $downPull) / $total * 100.0, 1) : 0.0,
            'upper' => $up[0]['price'] ?? null,
            'lower' => $down[0]['price'] ?? null,
            'upper_score' => round($up[0]['score'] ?? 0.0, 1),
            'lower_score' => round($down[0]['score'] ?? 0.0, 1),
        ];
    }

    private static function prominence(array $candles, int $i, bool $high, int $len, float $atr): float
    {
        $from = max(0, $i - $len * 2);
        $to = min(count($candles) - 1, $i + $len * 2);
        $slice = array_slice($candles, $from, $to - $from + 1);
        $extreme = $high
            ? $candles[$i]->high - min(array_map(static fn(Candle $c) => $c->low, $slice))
            : max(array_map(static fn(Candle $c) => $c->high, $slice)) - $candles[$i]->low;
        return $atr > 0 ? $extreme / $atr : 0.0;
    }

    private static function previousDay(array $candles): array
    {
        $byDay = [];
        foreach ($candles as $i => $c) {
            $day = (int) floor($c->openTime / 86400000);
            $byDay[$day] ??= ['high' => $c->high, 'low' => $c->low, 'hi' => $i, 'li' => $i];
            if ($c->high > $byDay[$day]['high']) {
                $byDay[$day]['high'] = $c->high;
                $byDay[$day]['hi'] = $i;
            }
            if ($c->low < $byDay[$day]['low']) {
                $byDay[$day]['low'] = $c->low;
                $byDay[$day]['li'] = $i;
            }
        }
        if (count($byDay) < 2) {
            return [];
        }
        $days = array_keys($byDay);
        $prev = $byDay[$days[count($days) - 2]];
        return [
            ['price' => $prev['high'], 'high' => true, 'bar' => $prev['hi'], 'src' => 1.0, 'prom' => 3.0, 'vol' => $candles[$prev['hi']]->volume],
            ['price' => $prev['low'], 'high' => false, 'bar' => $prev['li'], 'src' => 1.0, 'prom' => 3.0, 'vol' => $candles[$prev['li']]->volume],
        ];
    }
}

// Three Bar Reversal (LuxAlgo, in Wave Cipher SMC Flow).
final class ThreeBarReversal
{
    public static function detect(array $candles, int $maxAge = 1): ?string
    {
        $n = count($candles);
        for ($age = 0; $age <= $maxAge; $age++) {
            $i = $n - 1 - $age;
            if ($i < 2) {
                return null;
            }
            [$a, $b, $c] = [$candles[$i - 2], $candles[$i - 1], $candles[$i]];
            if ($a->close < $a->open && $b->low < $a->low && $b->high < $a->high && $b->close < $b->open
                && $c->close > $c->open && $c->high > $a->high) {
                return 'bullish';
            }
            if ($a->close > $a->open && $b->high > $a->high && $b->low > $a->low && $b->close > $b->open
                && $c->close < $c->open && $c->low < $a->low) {
                return 'bearish';
            }
        }
        return null;
    }
}

// Multi-timeframe EMA200 matrix (Diamond Algo / Monster Arrows dashboards).
final class MtfTrendMatrix
{
    public static function analyse(MarketSnapshot $snapshot): array
    {
        $up = 0;
        $down = 0;
        $frames = [];
        foreach ($snapshot->candles as $tf => $candles) {
            $n = count($candles);
            if ($n < 60) {
                continue;
            }
            $ema = Series::last(Series::ema(Ta::closes($candles), $n >= 210 ? 200 : 50));
            if (is_nan($ema)) {
                continue;
            }
            $bull = $candles[$n - 1]->close > $ema;
            $frames[$tf] = $bull ? 'bullish' : 'bearish';
            $bull ? $up++ : $down++;
        }
        return ['up' => $up, 'down' => $down, 'total' => $up + $down, 'frames' => $frames];
    }
}

final class IndicatorPack
{
    public const TREND_ENGINES = [
        'twin_range' => 'Twin Range Filter',
        'gbs' => 'Gain Buy Sell',
        'ssl' => 'SSL Channel',
        'supertrend_fast' => 'SuperTrend 11/4',
        'supertrend_slow' => 'HSN SuperTrend',
        'ut_bot' => 'UT Bot',
        'hull' => 'Hull Suite',
        'braid' => 'Braid Filter',
        'anchored_vwap' => 'Swing VWAP',
        'supertrend_ai' => 'SuperTrend AI',
        'trend_wave' => 'Trend Wave',
    ];

    // Computes every ported engine for one timeframe. Trend engines vote on direction,
    // triggers are fresh events, and the rest feed the exhaustion / chop / gravity gates.
    public static function analyse(array $candles, ?MarketSnapshot $snapshot = null): array
    {
        $twin = TwinRangeFilter::analyse($candles);
        $gbs = GainBuySell::analyse($candles);
        $ssl = SslChannel::analyse($candles);
        $stFast = ClassicSuperTrend::analyse($candles, 11, 4.0);
        $stSlow = ClassicSuperTrend::analyse($candles, 40, 3.2, true);
        $ut = UtBot::analyse($candles);
        $hull = HullTrend::analyse($candles);
        $braid = BraidFilter::analyse($candles);
        $avwap = AnchoredSwingVwap::analyse($candles);
        $stAi = SuperTrendAiEngine::detect($candles);
        $wave = TrendWave::analyse($candles);

        $avwapTrend = null;
        if ($avwap['direction'] !== null) {
            $avwapTrend = $avwap['direction'] === 'bullish' && $avwap['side'] === 'above'
                ? 'bullish'
                : ($avwap['direction'] === 'bearish' && $avwap['side'] === 'below' ? 'bearish' : null);
        }

        $trend = [
            'twin_range' => $twin['trend'],
            'gbs' => $gbs['trend'],
            'ssl' => $ssl['trend'],
            'supertrend_fast' => $stFast['trend'],
            'supertrend_slow' => $stSlow['trend'],
            'ut_bot' => $ut['trend'],
            'hull' => $hull['trend'],
            'braid' => in_array($braid['state'], ['bullish', 'bearish'], true) ? $braid['state'] : null,
            'anchored_vwap' => $avwapTrend,
            'supertrend_ai' => $stAi['direction'],
            'trend_wave' => $wave['direction'] === 'neutral' ? null : $wave['direction'],
        ];
        $flips = [
            'twin_range' => [$twin['trend'], $twin['flip_age']],
            'gbs' => [$gbs['trend'], $gbs['flip_age']],
            'ssl' => [$ssl['trend'], $ssl['flip_age']],
            'supertrend_fast' => [$stFast['trend'], $stFast['flip_age']],
            'ut_bot' => [$ut['trend'], $ut['flip_age']],
        ];

        [, , $adx15] = Series::dmi($candles, 15, 15);
        $rsi22 = Series::rsi(Ta::closes($candles), 22);

        return [
            'trend' => $trend,
            'flips' => $flips,
            'ssl' => $ssl,
            'braid' => $braid,
            'wavetrend' => WaveTrend::analyse($candles),
            'td' => TdSequential::analyse($candles),
            'sfp' => SwingFailure::detect($candles),
            'sweep' => LiquiditySweepScore::detect($candles),
            'trendline' => TrendlineBreak::detect($candles),
            'pvsra' => PvsraVolume::analyse($candles),
            'mrc' => MeanReversionChannel::analyse($candles),
            'gravity' => LiquidityGravity::analyse($candles),
            'three_bar' => ThreeBarReversal::detect($candles),
            'adx15' => Series::last($adx15),
            'rsi22' => Series::last($rsi22),
            'rsi22_prev' => Series::last($rsi22, 1),
            'mtf' => $snapshot !== null ? MtfTrendMatrix::analyse($snapshot) : ['up' => 0, 'down' => 0, 'total' => 0, 'frames' => []],
        ];
    }

    public static function consensus(array $pack, bool $isLong): array
    {
        $want = $isLong ? 'bullish' : 'bearish';
        $with = 0;
        $against = 0;
        $names = [];
        foreach ($pack['trend'] as $key => $dir) {
            if ($dir === $want) {
                $with++;
                $names[] = self::TREND_ENGINES[$key] ?? $key;
            } elseif ($dir !== null) {
                $against++;
            }
        }
        $total = count($pack['trend']);
        return ['with' => $with, 'against' => $against, 'total' => $total, 'ratio' => $total > 0 ? $with / $total : 0.0, 'names' => $names];
    }

    // Fresh events in the trade direction, with a Persian label each.
    public static function triggers(array $pack, bool $isLong, int $maxAge): array
    {
        $want = $isLong ? 'bullish' : 'bearish';
        $side = $isLong ? 'long' : 'short';
        $out = [];

        $wt = $pack['wavetrend'];
        if ($wt['signal'] === $side) {
            $out['wavetrend'] = $isLong ? 'WaveTrend از اشباع فروش برگشت' : 'WaveTrend از اشباع خرید برگشت';
        }
        if ($wt['divergence'] === $want) {
            $out['wt_divergence'] = 'واگرایی WaveTrend';
        }
        if ($pack['sfp']['direction'] === $want) {
            $out['sfp'] = 'Swing Failure (جاروی سقف/کف ماژور و تایید ۳ کندل)';
        }
        if ($pack['sweep']['direction'] === $want && $pack['sweep']['score'] >= 70.0) {
            $out['sweep'] = sprintf('جاروی نقدینگی قوی (امتیاز %.0f%s)', $pack['sweep']['score'], $pack['sweep']['equal'] ? '، سقف/کف برابر' : '');
        }
        if ($pack['trendline']['direction'] === $want && $pack['trendline']['age'] !== null && $pack['trendline']['age'] <= $maxAge) {
            $out['trendline'] = 'شکست خط روند';
        }
        if ($pack['three_bar'] === $want) {
            $out['three_bar'] = 'الگوی برگشتی سه‌کندلی';
        }
        $td = $pack['td'];
        $tdAge = $isLong ? $td['down_exhausted_age'] : $td['up_exhausted_age'];
        if ($tdAge !== null && $tdAge <= $maxAge) {
            $out['td9'] = 'شمارش ۹ تی‌دی (خستگی طرف مقابل)';
        }
        if ($pack['ssl']['confirmed'] === $side) {
            $out['ssl'] = 'تغییر جهت SSL با تایید کیفیت';
        }
        foreach ($pack['flips'] as $key => [$dir, $age]) {
            if ($dir === $want && $age !== null && $age <= $maxAge) {
                $out['flip_' . $key] = 'تغییر جهت ' . (self::TREND_ENGINES[$key] ?? $key);
            }
        }
        return $out;
    }

    // Reasons to stand aside even when the setup looks good.
    public static function exhaustion(array $pack, bool $isLong): ?string
    {
        $td = $pack['td'];
        $sameAge = $isLong ? $td['up_exhausted_age'] : $td['down_exhausted_age'];
        $sameCount = $isLong ? $td['up_count'] : $td['down_count'];
        if (($sameAge !== null && $sameAge <= 2) || $sameCount >= 8) {
            return 'شمارش تی‌دی به ۹ رسیده؛ حرکت در این جهت خسته است';
        }
        $wt = $pack['wavetrend'];
        if ($isLong ? $wt['overbought'] : $wt['oversold']) {
            return sprintf('WaveTrend در اشباع %s است (%.0f)', $isLong ? 'خرید' : 'فروش', $wt['wt2']);
        }
        $rsi = $pack['rsi22'];
        $rsiPrev = $pack['rsi22_prev'];
        if (!is_nan($rsi)) {
            if ($isLong && ($rsi >= 72.0 || (!is_nan($rsiPrev) && $rsiPrev >= 70.0 && $rsi < 70.0))) {
                return sprintf('RSI(22) اشباع خرید / برگشت از بالای ۷۰ (%.0f)', $rsi);
            }
            if (!$isLong && ($rsi <= 28.0 || (!is_nan($rsiPrev) && $rsiPrev <= 30.0 && $rsi > 30.0))) {
                return sprintf('RSI(22) اشباع فروش / برگشت از زیر ۳۰ (%.0f)', $rsi);
            }
        }
        $zone = $pack['mrc']['zone'];
        if ($isLong ? $zone === 'upper_extreme' : $zone === 'lower_extreme') {
            return 'قیمت بیرون باند بیرونی کانال بازگشت به میانگین (MRC) است';
        }
        return null;
    }

    public static function isChop(array $pack): bool
    {
        $adx = $pack['adx15'];
        return !is_nan($adx) && $adx < Config::chopAdxMax() && $pack['braid']['state'] === 'chop';
    }
}

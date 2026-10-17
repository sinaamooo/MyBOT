<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

final class Easing
{
    public static function clamp(float $t): float
    {
        return $t < 0.0 ? 0.0 : ($t > 1.0 ? 1.0 : $t);
    }

    public static function frac(float $x): float
    {
        return $x - floor($x);
    }

    public static function linear(float $t): float
    {
        return self::clamp($t);
    }

    public static function inOutSine(float $t): float
    {
        $t = self::clamp($t);
        return -(cos(M_PI * $t) - 1) / 2;
    }

    public static function inOutCubic(float $t): float
    {
        $t = self::clamp($t);
        return $t < 0.5 ? 4 * $t * $t * $t : 1 - ((-2 * $t + 2) ** 3) / 2;
    }

    public static function outCubic(float $t): float
    {
        $t = self::clamp($t);
        return 1 - (1 - $t) ** 3;
    }

    public static function inCubic(float $t): float
    {
        $t = self::clamp($t);
        return $t * $t * $t;
    }

    public static function outBack(float $t, float $s = 1.70158): float
    {
        $t = self::clamp($t) - 1;
        return $t * $t * (($s + 1) * $t + $s) + 1;
    }

    public static function inBack(float $t, float $s = 1.70158): float
    {
        $t = self::clamp($t);
        return $t * $t * (($s + 1) * $t - $s);
    }

    public static function outElastic(float $t, float $amp = 1.0): float
    {
        $t = self::clamp($t);
        if ($t === 0.0 || $t === 1.0) {
            return $t;
        }
        $p = 0.32;
        return 2 ** (-10 * $t) * sin(($t - $p / 4) * (2 * M_PI) / $p) * $amp + 1;
    }

    /** 0 -> 1 -> 0 smooth bump over one cycle. */
    public static function bump(float $q): float
    {
        return 0.5 - 0.5 * cos(2 * M_PI * $q);
    }

    /** Gaussian pulse centred at $c with width $w (periodic friendly for small widths). */
    public static function gauss(float $q, float $c, float $w): float
    {
        $d = ($q - $c) / max(1e-4, $w);
        return exp(-$d * $d);
    }

    /**
     * Envelope for enter/hold/exit animations within one cycle:
     * returns [inProgress 0..1, outProgress 0..1].
     */
    public static function inHoldOut(float $q, float $in, float $out): array
    {
        $in = max(0.01, min(0.5, $in));
        $out = max(0.01, min(0.5, $out));
        $pin = self::clamp($q / $in);
        $pout = self::clamp(($q - (1 - $out)) / $out);
        return [$pin, $pout];
    }
}

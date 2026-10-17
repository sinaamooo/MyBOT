<?php

declare(strict_types=1);

namespace EmojiMaker\Geometry;

/**
 * Converts polylines into fillable polygons (segment quads + joins + caps) so
 * strokes can be rasterized with the non-zero fill rule.
 * Joins: miter (with miter limit) / round / bevel. Caps: butt / round / square.
 * Matches the Lottie stroke options (lj 1/2/3, lc 1/2/3, ml).
 */
final class Stroke
{
    public const JOIN_MITER = 1;
    public const JOIN_ROUND = 2;
    public const JOIN_BEVEL = 3;
    public const CAP_BUTT = 1;
    public const CAP_ROUND = 2;
    public const CAP_SQUARE = 3;

    /**
     * @param list<float> $pts flat [x0,y0,x1,y1,...]
     * @return list<list<float>>
     */
    public static function polyline(array $pts, float $width, bool $closed = false, int $join = self::JOIN_ROUND, int $cap = self::CAP_ROUND, float $miterLimit = 4.0): array
    {
        $n = intdiv(count($pts), 2);
        if ($n < 2 || $width <= 0) {
            return [];
        }
        // drop duplicate consecutive points
        $p = [];
        for ($i = 0; $i < $n; $i++) {
            $x = $pts[$i * 2];
            $y = $pts[$i * 2 + 1];
            if ($p && abs(end($p)[0] - $x) < 1e-6 && abs(end($p)[1] - $y) < 1e-6) {
                continue;
            }
            $p[] = [$x, $y];
        }
        if ($closed && count($p) > 2 && abs($p[0][0] - end($p)[0]) < 1e-6 && abs($p[0][1] - end($p)[1]) < 1e-6) {
            array_pop($p);
        }
        $n = count($p);
        if ($n < 2) {
            if ($n === 1 && $cap === self::CAP_ROUND) {
                return [self::circle($p[0][0], $p[0][1], $width / 2, 16)];
            }
            return [];
        }
        $hw = $width / 2;
        $polys = [];
        $segCount = $closed ? $n : $n - 1;
        $dirs = [];
        for ($i = 0; $i < $segCount; $i++) {
            $a = $p[$i];
            $b = $p[($i + 1) % $n];
            $dx = $b[0] - $a[0];
            $dy = $b[1] - $a[1];
            $len = sqrt($dx * $dx + $dy * $dy);
            if ($len < 1e-9) {
                $dirs[$i] = null;
                continue;
            }
            $ux = $dx / $len;
            $uy = $dy / $len;
            $dirs[$i] = [$ux, $uy];
            $nx = -$uy * $hw;
            $ny = $ux * $hw;
            $polys[] = [$a[0] + $nx, $a[1] + $ny, $b[0] + $nx, $b[1] + $ny, $b[0] - $nx, $b[1] - $ny, $a[0] - $nx, $a[1] - $ny];
        }
        $roundSteps = max(8, min(32, (int) ceil($hw * 1.5)));
        // joins
        $first = $closed ? 0 : 1;
        $last = $closed ? $n - 1 : $n - 2;
        for ($i = $first; $i <= $last; $i++) {
            $d1 = $dirs[($i - 1 + $segCount) % $segCount] ?? null;
            $d2 = $dirs[$i % $segCount] ?? null;
            if ($d1 === null || $d2 === null) {
                continue;
            }
            $c = $p[$i];
            if ($join === self::JOIN_ROUND) {
                $polys[] = self::circle($c[0], $c[1], $hw, $roundSteps);
                continue;
            }
            $n1 = [-$d1[1], $d1[0]];
            $n2 = [-$d2[1], $d2[0]];
            foreach ([1, -1] as $side) {
                // outer side: the next segment turns away from this side
                if ($side * ($n1[0] * $d2[0] + $n1[1] * $d2[1]) > 0) {
                    continue;
                }
                $ax = $c[0] + $side * $n1[0] * $hw;
                $ay = $c[1] + $side * $n1[1] * $hw;
                $bx = $c[0] + $side * $n2[0] * $hw;
                $by = $c[1] + $side * $n2[1] * $hw;
                if ($join === self::JOIN_MITER) {
                    $mx = $n1[0] + $n2[0];
                    $my = $n1[1] + $n2[1];
                    $ml = sqrt($mx * $mx + $my * $my);
                    if ($ml > 1e-6) {
                        $cosHalf = $ml / 2;
                        $ratio = 1 / max(1e-6, $cosHalf);
                        if ($ratio <= $miterLimit) {
                            $tipx = $c[0] + $side * $mx / $ml * $hw * $ratio;
                            $tipy = $c[1] + $side * $my / $ml * $hw * $ratio;
                            $polys[] = [$c[0], $c[1], $ax, $ay, $tipx, $tipy, $bx, $by];
                            continue;
                        }
                    }
                }
                $polys[] = [$c[0], $c[1], $ax, $ay, $bx, $by];
            }
        }
        // caps
        if (!$closed && $cap !== self::CAP_BUTT) {
            $ends = [[$p[0], $dirs[0] ?? null, -1], [$p[$n - 1], $dirs[$segCount - 1] ?? null, 1]];
            foreach ($ends as [$pt, $d, $sgn]) {
                if ($d === null) {
                    continue;
                }
                if ($cap === self::CAP_ROUND) {
                    $polys[] = self::circle($pt[0], $pt[1], $hw, $roundSteps);
                } else {
                    $ex = $d[0] * $hw * $sgn;
                    $ey = $d[1] * $hw * $sgn;
                    $nx = -$d[1] * $hw;
                    $ny = $d[0] * $hw;
                    $polys[] = [$pt[0] + $nx, $pt[1] + $ny, $pt[0] + $nx + $ex, $pt[1] + $ny + $ey, $pt[0] - $nx + $ex, $pt[1] - $ny + $ey, $pt[0] - $nx, $pt[1] - $ny];
                }
            }
        }
        return self::normalizeWinding($polys);
    }

    /** @return list<float> */
    public static function circle(float $cx, float $cy, float $r, int $steps = 16): array
    {
        $out = [];
        for ($k = 0; $k < $steps; $k++) {
            $a = 2 * M_PI * $k / $steps;
            $out[] = $cx + $r * cos($a);
            $out[] = $cy + $r * sin($a);
        }
        return $out;
    }

    /**
     * Forces every polygon to the same orientation so that overlapping parts
     * add up under the non-zero rule.
     * @param list<list<float>> $polys
     * @return list<list<float>>
     */
    private static function normalizeWinding(array $polys): array
    {
        foreach ($polys as $k => $p) {
            $area = 0.0;
            $n = intdiv(count($p), 2);
            for ($i = 0; $i < $n; $i++) {
                $j = ($i + 1) % $n;
                $area += $p[$i * 2] * $p[$j * 2 + 1] - $p[$j * 2] * $p[$i * 2 + 1];
            }
            if ($area < 0) {
                $rev = [];
                for ($i = $n - 1; $i >= 0; $i--) {
                    $rev[] = $p[$i * 2];
                    $rev[] = $p[$i * 2 + 1];
                }
                $polys[$k] = $rev;
            }
        }
        return $polys;
    }
}

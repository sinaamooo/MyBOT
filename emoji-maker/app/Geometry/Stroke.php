<?php

declare(strict_types=1);

namespace EmojiMaker\Geometry;

/**
 * Converts polylines into fillable polygons (segment quads + round joins/caps)
 * so strokes can be rasterized with the non-zero fill rule.
 */
final class Stroke
{
    /**
     * @param list<float> $pts flat [x0,y0,x1,y1,...]
     * @return list<list<float>>
     */
    public static function polyline(array $pts, float $width, bool $closed = false): array
    {
        $polys = [];
        $n = intdiv(count($pts), 2);
        if ($n < 2 || $width <= 0) {
            return [];
        }
        $hw = $width / 2;
        $segments = $closed ? $n : $n - 1;
        for ($i = 0; $i < $segments; $i++) {
            $j = ($i + 1) % $n;
            $x0 = $pts[$i * 2];
            $y0 = $pts[$i * 2 + 1];
            $x1 = $pts[$j * 2];
            $y1 = $pts[$j * 2 + 1];
            $dx = $x1 - $x0;
            $dy = $y1 - $y0;
            $len = sqrt($dx * $dx + $dy * $dy);
            if ($len < 1e-6) {
                continue;
            }
            $nx = -$dy / $len * $hw;
            $ny = $dx / $len * $hw;
            // consistent winding for every quad (non-zero union)
            $polys[] = [$x0 + $nx, $y0 + $ny, $x1 + $nx, $y1 + $ny, $x1 - $nx, $y1 - $ny, $x0 - $nx, $y0 - $ny];
        }
        $steps = max(8, min(24, (int) ceil($hw)));
        for ($i = 0; $i < $n; $i++) {
            $polys[] = self::circle($pts[$i * 2], $pts[$i * 2 + 1], $hw, $steps);
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

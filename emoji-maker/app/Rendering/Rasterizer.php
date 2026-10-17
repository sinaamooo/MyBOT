<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;

/**
 * Anti-aliased scanline polygon rasterizer (pure PHP).
 * Vertical super-sampling + exact horizontal span coverage, non-zero or
 * even-odd fill rule. Produces a Mask (coverage 0..1 per pixel).
 */
final class Rasterizer
{
    public const NONZERO = 1;
    public const EVENODD = 2;

    public function __construct(private int $subSamples = 4)
    {
        $this->subSamples = max(1, min(16, $subSamples));
    }

    public function fillPath(Path $path, int $width, int $height, ?Matrix $m = null, int $rule = self::NONZERO, ?Mask $into = null, float $alpha = 1.0): Mask
    {
        $p = $m ? $path->transform($m) : $path;
        return $this->fillPolygons($p->flatten(0.2), $width, $height, $rule, $into, $alpha);
    }

    /**
     * @param list<list<float>> $polygons flat [x0,y0,x1,y1...] (closed implicitly)
     */
    public function fillPolygons(array $polygons, int $width, int $height, int $rule = self::NONZERO, ?Mask $into = null, float $alpha = 1.0): Mask
    {
        $mask = $into ?? new Mask($width, $height);
        $edges = [];
        $minY = INF;
        $maxY = -INF;
        foreach ($polygons as $poly) {
            $count = intdiv(count($poly), 2);
            if ($count < 3) {
                continue;
            }
            for ($i = 0; $i < $count; $i++) {
                $x0 = $poly[$i * 2];
                $y0 = $poly[$i * 2 + 1];
                $j = ($i + 1) % $count;
                $x1 = $poly[$j * 2];
                $y1 = $poly[$j * 2 + 1];
                if ($y0 === $y1) {
                    continue;
                }
                $dir = 1;
                if ($y0 > $y1) {
                    [$x0, $y0, $x1, $y1] = [$x1, $y1, $x0, $y0];
                    $dir = -1;
                }
                $edges[] = [$y0, $y1, $x0, ($x1 - $x0) / ($y1 - $y0), $dir];
                if ($y0 < $minY) { $minY = $y0; }
                if ($y1 > $maxY) { $maxY = $y1; }
            }
        }
        if (!$edges) {
            return $mask;
        }
        usort($edges, static fn ($a, $b) => $a[0] <=> $b[0]);
        $ss = $this->subSamples;
        $ws = $alpha / $ss;
        $rowStart = max(0, (int) floor($minY));
        $rowEnd = min($height - 1, (int) ceil($maxY));
        $edgeCount = count($edges);
        $next = 0;
        $active = [];
        $data = &$mask->data;
        $w = $width;
        for ($row = $rowStart; $row <= $rowEnd; $row++) {
            $frac = [];
            $diff = [];
            $rowTouched = false;
            $minX = $w;
            $maxX = 0;
            for ($s = 0; $s < $ss; $s++) {
                $sy = $row + ($s + 0.5) / $ss;
                while ($next < $edgeCount && $edges[$next][0] <= $sy) {
                    $active[] = $edges[$next];
                    $next++;
                }
                $xs = [];
                foreach ($active as $k => $e) {
                    if ($e[1] <= $sy) {
                        unset($active[$k]);
                        continue;
                    }
                    if ($e[0] > $sy) {
                        continue;
                    }
                    $xs[] = [$e[2] + ($sy - $e[0]) * $e[3], $e[4]];
                }
                if (count($xs) < 2) {
                    continue;
                }
                usort($xs, static fn ($a, $b) => $a[0] <=> $b[0]);
                $winding = 0;
                $nx = count($xs);
                for ($k = 0; $k < $nx - 1; $k++) {
                    $winding += $xs[$k][1];
                    $inside = $rule === self::EVENODD ? (($k + 1) % 2 === 1) : ($winding !== 0);
                    if (!$inside) {
                        continue;
                    }
                    $xa = $xs[$k][0];
                    $xb = $xs[$k + 1][0];
                    if ($xa < 0) { $xa = 0.0; }
                    if ($xb > $w) { $xb = (float) $w; }
                    if ($xb <= $xa) {
                        continue;
                    }
                    $rowTouched = true;
                    $ia = (int) $xa;
                    $ib = (int) $xb;
                    if ($ia < $minX) { $minX = $ia; }
                    if ($ib > $maxX) { $maxX = $ib; }
                    if ($ia === $ib) {
                        $frac[$ia] = ($frac[$ia] ?? 0.0) + ($xb - $xa) * $ws;
                    } else {
                        $frac[$ia] = ($frac[$ia] ?? 0.0) + ($ia + 1 - $xa) * $ws;
                        if ($ia + 1 < $ib) {
                            $diff[$ia + 1] = ($diff[$ia + 1] ?? 0.0) + $ws;
                            $diff[$ib] = ($diff[$ib] ?? 0.0) - $ws;
                        }
                        if ($ib < $w) {
                            $frac[$ib] = ($frac[$ib] ?? 0.0) + ($xb - $ib) * $ws;
                        }
                    }
                }
            }
            if (!$rowTouched) {
                continue;
            }
            $run = 0.0;
            $base = $row * $w;
            $end = min($w - 1, $maxX);
            for ($x = $minX; $x <= $end; $x++) {
                if (isset($diff[$x])) {
                    $run += $diff[$x];
                }
                $v = $run + ($frac[$x] ?? 0.0);
                if ($v > 0.0005) {
                    $nv = $data[$base + $x] + $v;
                    $data[$base + $x] = $nv > 1.0 ? 1.0 : $nv;
                }
            }
        }
        $mask->markDirty();
        return $mask;
    }
}

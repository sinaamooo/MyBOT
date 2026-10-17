<?php

declare(strict_types=1);

namespace EmojiMaker\Vector;

use EmojiMaker\Geometry\Path;

/**
 * Pure PHP raster -> vector tracer for logos (needed for .TGS, which cannot
 * embed images). Steps: downscale, k-means colour quantisation, stacked colour
 * layers (largest area at the bottom), marching squares contours, Ramer-
 * Douglas-Peucker simplification and Catmull-Rom curve smoothing with sharp
 * corner preservation. A vertex budget keeps the final TGS under 64 KB.
 */
final class Vectorizer
{
    public function __construct(private int $workSize = 200, private int $maxVertices = 1500)
    {
    }

    /**
     * @return array{width:int,height:int,layers:list<array{path:Path,color:array{0:int,1:int,2:int}}>,silhouette:Path,vertices:int}
     */
    public function trace(\GdImage $src, int $maxColors = 6): array
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $s = min(1.0, $this->workSize / max($sw, $sh));
        $w = max(8, (int) round($sw * $s));
        $h = max(8, (int) round($sh * $s));
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, 0x7F000000);
        imagecopyresampled($im, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);

        $pixels = [];   // index => [r,g,b]
        $opaque = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                if ((($c >> 24) & 0x7F) < 64) {
                    $i = $y * $w + $x;
                    $opaque[$i] = true;
                    $pixels[$i] = [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
                }
            }
        }
        if (!$pixels) {
            return ['width' => $w, 'height' => $h, 'layers' => [], 'silhouette' => new Path(), 'vertices' => 0];
        }
        [$centers, $assign] = $this->kmeans($pixels, max(1, min(8, $maxColors)));
        // drop tiny clusters into their nearest bigger neighbour
        $counts = array_fill(0, count($centers), 0);
        foreach ($assign as $c) {
            $counts[$c]++;
        }
        $total = count($assign);
        $keep = [];
        foreach ($counts as $ci => $n) {
            if ($n >= max(4, $total * 0.012)) {
                $keep[] = $ci;
            }
        }
        if (!$keep) {
            $keep = [array_search(max($counts), $counts, true)];
        }
        foreach ($assign as $i => $c) {
            if (!in_array($c, $keep, true)) {
                $assign[$i] = $this->nearest($pixels[$i], $centers, $keep);
            }
        }
        $counts = array_fill(0, count($centers), 0);
        foreach ($assign as $c) {
            $counts[$c]++;
        }
        usort($keep, static fn ($a, $b) => $counts[$b] <=> $counts[$a]);
        $rank = array_flip($keep);

        $eps = 0.6;
        $minArea = 5.0;
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $layers = [];
            $vertices = 0;
            $silhouette = null;
            foreach ($keep as $li => $ci) {
                $mask = [];
                foreach ($assign as $i => $c) {
                    if ($rank[$c] >= $li) {
                        $mask[$i] = true;
                    }
                }
                $loops = $this->contours($mask, $w, $h);
                $path = new Path();
                foreach ($loops as $loop) {
                    if (abs(self::area($loop)) < $minArea) {
                        continue;
                    }
                    $simple = self::rdpClosed($loop, $eps);
                    if (count($simple) < 3) {
                        continue;
                    }
                    $vertices += count($simple);
                    self::appendSmooth($path, $simple);
                }
                if ($path->isEmpty()) {
                    continue;
                }
                if ($li === 0) {
                    $silhouette = $path;
                }
                $layers[] = ['path' => $path, 'color' => array_map('intval', array_map('round', $centers[$ci]))];
            }
            if ($vertices <= $this->maxVertices || $attempt === 5) {
                return [
                    'width' => $w,
                    'height' => $h,
                    'layers' => $layers,
                    'silhouette' => $silhouette ?? ($layers[0]['path'] ?? new Path()),
                    'vertices' => $vertices,
                ];
            }
            $eps *= 1.6;
            $minArea *= 2.2;
        }
        throw new \LogicException('unreachable');
    }

    /**
     * Deterministic k-means (k-means++ style seeding by farthest point).
     * @param array<int,array{0:int,1:int,2:int}> $pixels
     * @return array{0:list<array{0:float,1:float,2:float}>,1:array<int,int>}
     */
    private function kmeans(array $pixels, int $k): array
    {
        $keys = array_keys($pixels);
        $sample = [];
        $step = max(1, intdiv(count($keys), 4000));
        for ($i = 0, $n = count($keys); $i < $n; $i += $step) {
            $sample[] = $pixels[$keys[$i]];
        }
        // seeding: mean colour first, then farthest points
        $mean = [0.0, 0.0, 0.0];
        foreach ($sample as $p) {
            $mean[0] += $p[0];
            $mean[1] += $p[1];
            $mean[2] += $p[2];
        }
        $cnt = count($sample);
        $centers = [[$mean[0] / $cnt, $mean[1] / $cnt, $mean[2] / $cnt]];
        while (count($centers) < $k) {
            $best = null;
            $bestD = -1;
            foreach ($sample as $p) {
                $d = INF;
                foreach ($centers as $c) {
                    $d = min($d, ($p[0] - $c[0]) ** 2 + ($p[1] - $c[1]) ** 2 + ($p[2] - $c[2]) ** 2);
                }
                if ($d > $bestD) {
                    $bestD = $d;
                    $best = $p;
                }
            }
            if ($best === null || $bestD < 400) {
                break; // remaining colours are too similar
            }
            $centers[] = [(float) $best[0], (float) $best[1], (float) $best[2]];
        }
        for ($iter = 0; $iter < 10; $iter++) {
            $sums = array_fill(0, count($centers), [0.0, 0.0, 0.0, 0]);
            foreach ($sample as $p) {
                $ci = $this->nearest($p, $centers);
                $sums[$ci][0] += $p[0];
                $sums[$ci][1] += $p[1];
                $sums[$ci][2] += $p[2];
                $sums[$ci][3]++;
            }
            $moved = 0.0;
            foreach ($sums as $ci => $sum) {
                if ($sum[3] === 0) {
                    continue;
                }
                $nc = [$sum[0] / $sum[3], $sum[1] / $sum[3], $sum[2] / $sum[3]];
                $moved += abs($nc[0] - $centers[$ci][0]) + abs($nc[1] - $centers[$ci][1]) + abs($nc[2] - $centers[$ci][2]);
                $centers[$ci] = $nc;
            }
            if ($moved < 1.0) {
                break;
            }
        }
        $assign = [];
        foreach ($pixels as $i => $p) {
            $assign[$i] = $this->nearest($p, $centers);
        }
        return [$centers, $assign];
    }

    /** @param list<int>|null $allowed */
    private function nearest(array $p, array $centers, ?array $allowed = null): int
    {
        $best = 0;
        $bestD = INF;
        foreach ($centers as $ci => $c) {
            if ($allowed !== null && !in_array($ci, $allowed, true)) {
                continue;
            }
            $d = ($p[0] - $c[0]) ** 2 + ($p[1] - $c[1]) ** 2 + ($p[2] - $c[2]) ** 2;
            if ($d < $bestD) {
                $bestD = $d;
                $best = $ci;
            }
        }
        return $best;
    }

    /**
     * Marching squares over a binary mask -> closed loops (image coordinates).
     * @param array<int,bool> $mask
     * @return list<list<array{0:float,1:float}>>
     */
    private function contours(array $mask, int $w, int $h): array
    {
        $in = static fn (int $x, int $y): bool => $x >= 0 && $y >= 0 && $x < $w && $y < $h && isset($mask[$y * $w + $x]);
        $segments = [];
        // grid corner (i,j) is pixel (i-1, j-1)
        for ($j = 0; $j <= $h; $j++) {
            for ($i = 0; $i <= $w; $i++) {
                $tl = $in($i - 1, $j - 1);
                $tr = $in($i, $j - 1);
                $br = $in($i, $j);
                $bl = $in($i - 1, $j);
                $case = ($tl ? 8 : 0) | ($tr ? 4 : 0) | ($br ? 2 : 0) | ($bl ? 1 : 0);
                if ($case === 0 || $case === 15) {
                    continue;
                }
                $T = "h$i,$j";
                $B = 'h' . $i . ',' . ($j + 1);
                $L = "v$i,$j";
                $R = 'v' . ($i + 1) . ',' . $j;
                $pairs = match ($case) {
                    1, 14 => [[$L, $B]],
                    2, 13 => [[$B, $R]],
                    3, 12 => [[$L, $R]],
                    4, 11 => [[$T, $R]],
                    5 => [[$L, $B], [$T, $R]],
                    6, 9 => [[$T, $B]],
                    7, 8 => [[$L, $T]],
                    10 => [[$L, $T], [$B, $R]],
                    default => [],
                };
                foreach ($pairs as $p) {
                    $segments[] = $p;
                }
            }
        }
        $adj = [];
        foreach ($segments as $si => [$a, $b]) {
            $adj[$a][] = $si;
            $adj[$b][] = $si;
        }
        $used = [];
        $loops = [];
        foreach ($segments as $si => [$a, $b]) {
            if (isset($used[$si])) {
                continue;
            }
            $used[$si] = true;
            $loop = [$a];
            $cur = $b;
            $guard = 0;
            while ($cur !== $a && $guard++ < 100000) {
                $loop[] = $cur;
                $next = null;
                foreach ($adj[$cur] as $cand) {
                    if (!isset($used[$cand])) {
                        $next = $cand;
                        break;
                    }
                }
                if ($next === null) {
                    break;
                }
                $used[$next] = true;
                $cur = $segments[$next][0] === $cur ? $segments[$next][1] : $segments[$next][0];
            }
            if (count($loop) >= 3) {
                $loops[] = array_map([self::class, 'keyPoint'], $loop);
            }
        }
        return $loops;
    }

    /** @return array{0:float,1:float} */
    private static function keyPoint(string $key): array
    {
        [$i, $j] = array_map('intval', explode(',', substr($key, 1)));
        // corner (i,j) == pixel centre (i-0.5, j-0.5); edge midpoints:
        return $key[0] === 'h' ? [(float) $i, $j - 0.5] : [$i - 0.5, (float) $j];
    }

    /** @param list<array{0:float,1:float}> $pts */
    private static function area(array $pts): float
    {
        $a = 0.0;
        $n = count($pts);
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $a += $pts[$i][0] * $pts[$j][1] - $pts[$j][0] * $pts[$i][1];
        }
        return $a / 2;
    }

    /**
     * @param list<array{0:float,1:float}> $pts
     * @return list<array{0:float,1:float}>
     */
    private static function rdpClosed(array $pts, float $eps): array
    {
        $n = count($pts);
        if ($n < 4) {
            return $pts;
        }
        // split at the two farthest points for a stable closed simplification
        $far = 0;
        $farD = -1;
        for ($i = 1; $i < $n; $i++) {
            $d = ($pts[$i][0] - $pts[0][0]) ** 2 + ($pts[$i][1] - $pts[0][1]) ** 2;
            if ($d > $farD) {
                $farD = $d;
                $far = $i;
            }
        }
        $a = self::rdp(array_slice($pts, 0, $far + 1), $eps);
        $b = self::rdp(array_merge(array_slice($pts, $far), [$pts[0]]), $eps);
        array_pop($a);
        array_pop($b);
        return array_merge($a, $b);
    }

    /**
     * @param list<array{0:float,1:float}> $pts
     * @return list<array{0:float,1:float}>
     */
    private static function rdp(array $pts, float $eps): array
    {
        $n = count($pts);
        if ($n < 3) {
            return $pts;
        }
        $keep = array_fill(0, $n, false);
        $keep[0] = $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];
        while ($stack) {
            [$s, $e] = array_pop($stack);
            $ax = $pts[$s][0];
            $ay = $pts[$s][1];
            $bx = $pts[$e][0];
            $by = $pts[$e][1];
            $dx = $bx - $ax;
            $dy = $by - $ay;
            $len = sqrt($dx * $dx + $dy * $dy);
            $maxD = -1;
            $idx = -1;
            for ($i = $s + 1; $i < $e; $i++) {
                $d = $len > 1e-9
                    ? abs($dy * $pts[$i][0] - $dx * $pts[$i][1] + $bx * $ay - $by * $ax) / $len
                    : sqrt(($pts[$i][0] - $ax) ** 2 + ($pts[$i][1] - $ay) ** 2);
                if ($d > $maxD) {
                    $maxD = $d;
                    $idx = $i;
                }
            }
            if ($maxD > $eps && $idx > 0) {
                $keep[$idx] = true;
                $stack[] = [$s, $idx];
                $stack[] = [$idx, $e];
            }
        }
        $out = [];
        foreach ($pts as $i => $p) {
            if ($keep[$i]) {
                $out[] = $p;
            }
        }
        return $out;
    }

    /**
     * Appends a closed Catmull-Rom spline through the points; vertices with a
     * sharp turn keep a corner.
     * @param list<array{0:float,1:float}> $p
     */
    private static function appendSmooth(Path $path, array $p): void
    {
        $n = count($p);
        $sharp = [];
        for ($i = 0; $i < $n; $i++) {
            $a = $p[($i - 1 + $n) % $n];
            $b = $p[$i];
            $c = $p[($i + 1) % $n];
            $v1x = $b[0] - $a[0];
            $v1y = $b[1] - $a[1];
            $v2x = $c[0] - $b[0];
            $v2y = $c[1] - $b[1];
            $l1 = sqrt($v1x * $v1x + $v1y * $v1y);
            $l2 = sqrt($v2x * $v2x + $v2y * $v2y);
            $cos = ($l1 > 0 && $l2 > 0) ? ($v1x * $v2x + $v1y * $v2y) / ($l1 * $l2) : 1.0;
            $sharp[$i] = $cos < 0.35; // turn sharper than ~70 degrees
        }
        $path->moveTo($p[0][0], $p[0][1]);
        for ($i = 0; $i < $n; $i++) {
            $p0 = $p[($i - 1 + $n) % $n];
            $p1 = $p[$i];
            $p2 = $p[($i + 1) % $n];
            $p3 = $p[($i + 2) % $n];
            $t = 1 / 6;
            $c1 = $sharp[$i] ? $p1 : [$p1[0] + ($p2[0] - $p0[0]) * $t, $p1[1] + ($p2[1] - $p0[1]) * $t];
            $c2 = $sharp[($i + 1) % $n] ? $p2 : [$p2[0] - ($p3[0] - $p1[0]) * $t, $p2[1] - ($p3[1] - $p1[1]) * $t];
            if ($c1 === $p1 && $c2 === $p2) {
                $path->lineTo($p2[0], $p2[1]);
            } else {
                $path->cubicTo($c1[0], $c1[1], $c2[0], $c2[1], $p2[0], $p2[1]);
            }
        }
        $path->close();
    }
}

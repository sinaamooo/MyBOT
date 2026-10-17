<?php

declare(strict_types=1);

namespace EmojiMaker\Geometry;

/**
 * Vector path made of closed (or open) contours with line, quadratic and
 * cubic Bezier segments. Used for glyph outlines, decorations and traced
 * logos; rendered by the PHP rasterizer and exported to Lottie.
 *
 * Contour layout: ['x'=>float,'y'=>float,'segs'=>list<array>,'closed'=>bool]
 * Segment layout: [0,x,y] line | [1,cx,cy,x,y] quad | [2,c1x,c1y,c2x,c2y,x,y] cubic
 */
final class Path
{
    public const LINE = 0;
    public const QUAD = 1;
    public const CUBIC = 2;

    /** @var list<array{x:float,y:float,segs:list<array<int,float|int>>,closed:bool}> */
    public array $contours = [];
    private int $current = -1;

    public function moveTo(float $x, float $y): self
    {
        $this->contours[] = ['x' => $x, 'y' => $y, 'segs' => [], 'closed' => false];
        $this->current = count($this->contours) - 1;
        return $this;
    }

    public function lineTo(float $x, float $y): self
    {
        $this->ensure();
        $this->contours[$this->current]['segs'][] = [self::LINE, $x, $y];
        return $this;
    }

    public function quadTo(float $cx, float $cy, float $x, float $y): self
    {
        $this->ensure();
        $this->contours[$this->current]['segs'][] = [self::QUAD, $cx, $cy, $x, $y];
        return $this;
    }

    public function cubicTo(float $c1x, float $c1y, float $c2x, float $c2y, float $x, float $y): self
    {
        $this->ensure();
        $this->contours[$this->current]['segs'][] = [self::CUBIC, $c1x, $c1y, $c2x, $c2y, $x, $y];
        return $this;
    }

    public function close(): self
    {
        if ($this->current >= 0) {
            $this->contours[$this->current]['closed'] = true;
        }
        return $this;
    }

    private function ensure(): void
    {
        if ($this->current < 0) {
            $this->moveTo(0, 0);
        }
    }

    public function isEmpty(): bool
    {
        foreach ($this->contours as $c) {
            if ($c['segs']) {
                return false;
            }
        }
        return true;
    }

    public function append(Path $other): self
    {
        foreach ($other->contours as $c) {
            $this->contours[] = $c;
        }
        $this->current = count($this->contours) - 1;
        return $this;
    }

    public function transform(Matrix $m): Path
    {
        $out = new Path();
        foreach ($this->contours as $c) {
            [$x, $y] = $m->apply($c['x'], $c['y']);
            $segs = [];
            foreach ($c['segs'] as $s) {
                $t = [$s[0]];
                $n = count($s);
                for ($i = 1; $i < $n; $i += 2) {
                    [$px, $py] = $m->apply($s[$i], $s[$i + 1]);
                    $t[] = $px;
                    $t[] = $py;
                }
                $segs[] = $t;
            }
            $out->contours[] = ['x' => $x, 'y' => $y, 'segs' => $segs, 'closed' => $c['closed']];
        }
        $out->current = count($out->contours) - 1;
        return $out;
    }

    /** Control-point bounding box [minX,minY,maxX,maxY] or null. */
    public function bounds(): ?array
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach ($this->contours as $c) {
            $minX = min($minX, $c['x']);
            $maxX = max($maxX, $c['x']);
            $minY = min($minY, $c['y']);
            $maxY = max($maxY, $c['y']);
            foreach ($c['segs'] as $s) {
                $n = count($s);
                for ($i = 1; $i < $n; $i += 2) {
                    if ($s[$i] < $minX) { $minX = $s[$i]; }
                    if ($s[$i] > $maxX) { $maxX = $s[$i]; }
                    if ($s[$i + 1] < $minY) { $minY = $s[$i + 1]; }
                    if ($s[$i + 1] > $maxY) { $maxY = $s[$i + 1]; }
                }
            }
        }
        if ($minX === INF) {
            return null;
        }
        return [$minX, $minY, $maxX, $maxY];
    }

    /**
     * Flattens curves into polygons.
     * @return list<list<float>> flat [x0,y0,x1,y1,...] per contour
     */
    public function flatten(float $tolerance = 0.25): array
    {
        $polys = [];
        $tol = max(0.01, $tolerance);
        foreach ($this->contours as $c) {
            $pts = [$c['x'], $c['y']];
            $px = $c['x'];
            $py = $c['y'];
            foreach ($c['segs'] as $s) {
                if ($s[0] === self::LINE) {
                    $pts[] = $s[1];
                    $pts[] = $s[2];
                    $px = $s[1];
                    $py = $s[2];
                } elseif ($s[0] === self::QUAD) {
                    $dd = abs($px - 2 * $s[1] + $s[3]) + abs($py - 2 * $s[2] + $s[4]);
                    $n = (int) max(1, min(64, ceil(sqrt($dd / (4 * $tol)))));
                    for ($i = 1; $i <= $n; $i++) {
                        $t = $i / $n;
                        $mt = 1 - $t;
                        $pts[] = $mt * $mt * $px + 2 * $mt * $t * $s[1] + $t * $t * $s[3];
                        $pts[] = $mt * $mt * $py + 2 * $mt * $t * $s[2] + $t * $t * $s[4];
                    }
                    $px = $s[3];
                    $py = $s[4];
                } else {
                    $dd = max(
                        abs($px - 2 * $s[1] + $s[3]) + abs($py - 2 * $s[2] + $s[4]),
                        abs($s[1] - 2 * $s[3] + $s[5]) + abs($s[2] - 2 * $s[4] + $s[6])
                    );
                    $n = (int) max(1, min(96, ceil(sqrt(3 * $dd / (4 * $tol)))));
                    for ($i = 1; $i <= $n; $i++) {
                        $t = $i / $n;
                        $mt = 1 - $t;
                        $a = $mt * $mt * $mt;
                        $b = 3 * $mt * $mt * $t;
                        $cc = 3 * $mt * $t * $t;
                        $d = $t * $t * $t;
                        $pts[] = $a * $px + $b * $s[1] + $cc * $s[3] + $d * $s[5];
                        $pts[] = $a * $py + $b * $s[2] + $cc * $s[4] + $d * $s[6];
                    }
                    $px = $s[5];
                    $py = $s[6];
                }
            }
            if (count($pts) >= 6) {
                $polys[] = $pts;
            }
        }
        return $polys;
    }

    /** Number of segments (used for size budgeting). */
    public function segmentCount(): int
    {
        $n = 0;
        foreach ($this->contours as $c) {
            $n += count($c['segs']) + 1;
        }
        return $n;
    }

    // ------------------------------------------------------------------
    // Shape helpers (in any coordinate space)
    // ------------------------------------------------------------------

    public static function rect(float $x, float $y, float $w, float $h): Path
    {
        $p = new Path();
        return $p->moveTo($x, $y)->lineTo($x + $w, $y)->lineTo($x + $w, $y + $h)->lineTo($x, $y + $h)->close();
    }

    public static function roundedRect(float $x, float $y, float $w, float $h, float $r): Path
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        if ($r <= 0.0) {
            return self::rect($x, $y, $w, $h);
        }
        $k = 0.5522847498 * $r;
        $p = new Path();
        $p->moveTo($x + $r, $y)
            ->lineTo($x + $w - $r, $y)
            ->cubicTo($x + $w - $r + $k, $y, $x + $w, $y + $r - $k, $x + $w, $y + $r)
            ->lineTo($x + $w, $y + $h - $r)
            ->cubicTo($x + $w, $y + $h - $r + $k, $x + $w - $r + $k, $y + $h, $x + $w - $r, $y + $h)
            ->lineTo($x + $r, $y + $h)
            ->cubicTo($x + $r - $k, $y + $h, $x, $y + $h - $r + $k, $x, $y + $h - $r)
            ->lineTo($x, $y + $r)
            ->cubicTo($x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y)
            ->close();
        return $p;
    }

    public static function ellipse(float $cx, float $cy, float $rx, float $ry): Path
    {
        $kx = 0.5522847498 * $rx;
        $ky = 0.5522847498 * $ry;
        $p = new Path();
        $p->moveTo($cx + $rx, $cy)
            ->cubicTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry)
            ->cubicTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy)
            ->cubicTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry)
            ->cubicTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy)
            ->close();
        return $p;
    }

    /** @param list<array{0:float,1:float}> $points */
    public static function polygon(array $points, bool $closed = true): Path
    {
        $p = new Path();
        foreach ($points as $i => [$x, $y]) {
            $i === 0 ? $p->moveTo($x, $y) : $p->lineTo($x, $y);
        }
        if ($closed) {
            $p->close();
        }
        return $p;
    }

    /** Four-point sparkle / diamond star made of curves (not a Lottie "star" shape). */
    public static function sparkle(float $cx, float $cy, float $r, float $inner = 0.18): Path
    {
        $i = $r * $inner;
        $p = new Path();
        $p->moveTo($cx, $cy - $r)
            ->quadTo($cx + $i, $cy - $i, $cx + $r, $cy)
            ->quadTo($cx + $i, $cy + $i, $cx, $cy + $r)
            ->quadTo($cx - $i, $cy + $i, $cx - $r, $cy)
            ->quadTo($cx - $i, $cy - $i, $cx, $cy - $r)
            ->close();
        return $p;
    }

    /** Converts every segment to cubic Bezier form (Lottie only knows cubics). */
    public function toCubic(): Path
    {
        $out = new Path();
        foreach ($this->contours as $c) {
            $px = $c['x'];
            $py = $c['y'];
            $segs = [];
            foreach ($c['segs'] as $s) {
                if ($s[0] === self::LINE) {
                    $segs[] = [self::CUBIC, $px, $py, $s[1], $s[2], $s[1], $s[2]];
                    $px = $s[1];
                    $py = $s[2];
                } elseif ($s[0] === self::QUAD) {
                    $c1x = $px + 2 / 3 * ($s[1] - $px);
                    $c1y = $py + 2 / 3 * ($s[2] - $py);
                    $c2x = $s[3] + 2 / 3 * ($s[1] - $s[3]);
                    $c2y = $s[4] + 2 / 3 * ($s[2] - $s[4]);
                    $segs[] = [self::CUBIC, $c1x, $c1y, $c2x, $c2y, $s[3], $s[4]];
                    $px = $s[3];
                    $py = $s[4];
                } else {
                    $segs[] = $s;
                    $px = $s[5];
                    $py = $s[6];
                }
            }
            $out->contours[] = ['x' => $c['x'], 'y' => $c['y'], 'segs' => $segs, 'closed' => $c['closed']];
        }
        return $out;
    }
}

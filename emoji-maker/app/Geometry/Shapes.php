<?php

declare(strict_types=1);

namespace EmojiMaker\Geometry;

/**
 * Frame / badge / background shapes. Every shape is built from lines and
 * Bezier curves only (no Lottie "star" primitives) so it is valid in TGS.
 * Coordinates: any box [x0,y0,x1,y1] in design space.
 */
final class Shapes
{
    /** key => Persian label */
    public const TYPES = [
        'none' => 'بدون قاب',
        'circle' => 'دایره',
        'rounded' => 'مربع گرد',
        'square' => 'مربع',
        'pill' => 'کپسولی',
        'shield' => 'سپر',
        'hexagon' => 'شش‌ضلعی',
        'heart' => 'قلب',
        'seal' => 'مهر کنگره‌ای',
        'tag' => 'برچسب فلش‌دار',
        'diamond' => 'لوزی',
        'coin' => 'سکه',
        'bubble' => 'حباب گفتگو',
    ];

    /** Shapes that look identical when rotated around their centre. */
    public const ROTATION_INVARIANT = ['circle', 'coin'];

    /** @param array{0:float,1:float,2:float,3:float} $b */
    public static function make(string $type, array $b): ?Path
    {
        [$x0, $y0, $x1, $y1] = $b;
        $w = $x1 - $x0;
        $h = $y1 - $y0;
        $cx = ($x0 + $x1) / 2;
        $cy = ($y0 + $y1) / 2;
        return match ($type) {
            'circle', 'coin' => Path::ellipse($cx, $cy, $w / 2, $h / 2),
            'rounded' => Path::roundedRect($x0, $y0, $w, $h, min($w, $h) * 0.22),
            'square' => Path::roundedRect($x0, $y0, $w, $h, min($w, $h) * 0.04),
            'pill' => Path::roundedRect($x0, $y0, $w, $h, min($w, $h) / 2),
            'shield' => self::shield($x0, $y0, $w, $h),
            'hexagon' => self::hexagon($cx, $cy, $w / 2, $h / 2),
            'heart' => self::heart($x0, $y0, $w, $h),
            'seal' => self::seal($cx, $cy, min($w, $h) / 2, 18),
            'tag' => self::tag($x0, $y0, $w, $h),
            'diamond' => Path::polygon([[$cx, $y0], [$x1, $cy], [$cx, $y1], [$x0, $cy]]),
            'bubble' => self::bubble($x0, $y0, $w, $h),
            default => null,
        };
    }

    public static function shield(float $x, float $y, float $w, float $h): Path
    {
        $r = $w * 0.12;
        $p = new Path();
        $p->moveTo($x + $r, $y)
            ->lineTo($x + $w - $r, $y)
            ->quadTo($x + $w, $y, $x + $w, $y + $r)
            ->lineTo($x + $w, $y + $h * 0.5)
            ->cubicTo($x + $w, $y + $h * 0.78, $x + $w * 0.72, $y + $h * 0.9, $x + $w / 2, $y + $h)
            ->cubicTo($x + $w * 0.28, $y + $h * 0.9, $x, $y + $h * 0.78, $x, $y + $h * 0.5)
            ->lineTo($x, $y + $r)
            ->quadTo($x, $y, $x + $r, $y)
            ->close();
        return $p;
    }

    public static function hexagon(float $cx, float $cy, float $rx, float $ry): Path
    {
        $pts = [];
        for ($i = 0; $i < 6; $i++) {
            $a = deg2rad(-90 + 60 * $i);
            $pts[] = [$cx + $rx * cos($a), $cy + $ry * sin($a)];
        }
        return self::roundedPolygon($pts, min($rx, $ry) * 0.12);
    }

    public static function heart(float $x, float $y, float $w, float $h): Path
    {
        $p = new Path();
        $cx = $x + $w / 2;
        $p->moveTo($cx, $y + $h * 0.28)
            ->cubicTo($cx, $y + $h * 0.1, $x + $w * 0.78, $y, $x + $w * 0.88, $y + $h * 0.06)
            ->cubicTo($x + $w * 1.02, $y + $h * 0.14, $x + $w * 1.02, $y + $h * 0.42, $x + $w * 0.9, $y + $h * 0.55)
            ->lineTo($cx, $y + $h)
            ->lineTo($x + $w * 0.1, $y + $h * 0.55)
            ->cubicTo($x - $w * 0.02, $y + $h * 0.42, $x - $w * 0.02, $y + $h * 0.14, $x + $w * 0.12, $y + $h * 0.06)
            ->cubicTo($x + $w * 0.22, $y, $cx, $y + $h * 0.1, $cx, $y + $h * 0.28)
            ->close();
        return $p;
    }

    /** Scalloped "seal" badge built from quadratic curves. */
    public static function seal(float $cx, float $cy, float $r, int $bumps): Path
    {
        $p = new Path();
        $inner = $r * 0.86;
        for ($i = 0; $i <= $bumps; $i++) {
            $a = 2 * M_PI * $i / $bumps - M_PI / 2;
            $x = $cx + $inner * cos($a);
            $y = $cy + $inner * sin($a);
            if ($i === 0) {
                $p->moveTo($x, $y);
                continue;
            }
            $am = 2 * M_PI * ($i - 0.5) / $bumps - M_PI / 2;
            $p->quadTo($cx + $r * 1.08 * cos($am), $cy + $r * 1.08 * sin($am), $x, $y);
        }
        return $p->close();
    }

    /** Arrow shaped tag (like numbered target tags). */
    public static function tag(float $x, float $y, float $w, float $h): Path
    {
        $tip = min($w * 0.32, $h * 0.5);
        return self::roundedPolygon([
            [$x, $y], [$x + $w - $tip, $y], [$x + $w, $y + $h / 2], [$x + $w - $tip, $y + $h], [$x, $y + $h],
        ], min($w, $h) * 0.08);
    }

    public static function bubble(float $x, float $y, float $w, float $h): Path
    {
        $bh = $h * 0.82;
        $r = min($w, $bh) * 0.2;
        $p = new Path();
        $p->moveTo($x + $r, $y)
            ->lineTo($x + $w - $r, $y)->quadTo($x + $w, $y, $x + $w, $y + $r)
            ->lineTo($x + $w, $y + $bh - $r)->quadTo($x + $w, $y + $bh, $x + $w - $r, $y + $bh)
            ->lineTo($x + $w * 0.42, $y + $bh)
            ->lineTo($x + $w * 0.22, $y + $h)
            ->lineTo($x + $w * 0.26, $y + $bh)
            ->lineTo($x + $r, $y + $bh)->quadTo($x, $y + $bh, $x, $y + $bh - $r)
            ->lineTo($x, $y + $r)->quadTo($x, $y, $x + $r, $y)
            ->close();
        return $p;
    }

    /** @param list<array{0:float,1:float}> $pts */
    public static function roundedPolygon(array $pts, float $radius): Path
    {
        $n = count($pts);
        $p = new Path();
        for ($i = 0; $i < $n; $i++) {
            $prev = $pts[($i - 1 + $n) % $n];
            $cur = $pts[$i];
            $next = $pts[($i + 1) % $n];
            $d1 = hypot($cur[0] - $prev[0], $cur[1] - $prev[1]);
            $d2 = hypot($next[0] - $cur[0], $next[1] - $cur[1]);
            $r = min($radius, $d1 / 2, $d2 / 2);
            $a = [$cur[0] + ($prev[0] - $cur[0]) * $r / max(1e-6, $d1), $cur[1] + ($prev[1] - $cur[1]) * $r / max(1e-6, $d1)];
            $b = [$cur[0] + ($next[0] - $cur[0]) * $r / max(1e-6, $d2), $cur[1] + ($next[1] - $cur[1]) * $r / max(1e-6, $d2)];
            if ($i === 0) {
                $p->moveTo($a[0], $a[1]);
            } else {
                $p->lineTo($a[0], $a[1]);
            }
            $p->quadTo($cur[0], $cur[1], $b[0], $b[1]);
        }
        return $p->close();
    }

    /** Inner content box (where a logo/text fits nicely) for a frame. */
    public static function innerBox(string $type, array $b): array
    {
        [$x0, $y0, $x1, $y1] = $b;
        $w = $x1 - $x0;
        $h = $y1 - $y0;
        $f = match ($type) {
            'circle', 'coin', 'seal' => [0.2, 0.2, 0.2, 0.2],
            'shield' => [0.17, 0.12, 0.17, 0.26],
            'hexagon' => [0.2, 0.2, 0.2, 0.2],
            'heart' => [0.24, 0.2, 0.24, 0.32],
            'diamond' => [0.26, 0.26, 0.26, 0.26],
            'tag' => [0.08, 0.14, 0.3, 0.14],
            'bubble' => [0.1, 0.08, 0.1, 0.26],
            'pill' => [0.12, 0.12, 0.12, 0.12],
            default => [0.1, 0.1, 0.1, 0.1],
        };
        return [$x0 + $w * $f[0], $y0 + $h * $f[1], $x1 - $w * $f[2], $y1 - $h * $f[3]];
    }
}

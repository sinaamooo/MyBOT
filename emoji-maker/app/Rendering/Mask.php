<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

/**
 * Single channel coverage buffer (0..1) with the morphological operations
 * needed for outlines (exact Euclidean distance transform), glows and
 * shadows (3-pass box blur ~ gaussian).
 */
final class Mask
{
    /** @var list<float> */
    public array $data;
    private ?array $boundsCache = null;

    public function __construct(public readonly int $width, public readonly int $height, ?array $data = null)
    {
        $this->data = $data ?? array_fill(0, $width * $height, 0.0);
    }

    public function markDirty(): void
    {
        $this->boundsCache = null;
    }

    public function copy(): Mask
    {
        return new Mask($this->width, $this->height, $this->data);
    }

    /** Bounding box of pixels above threshold: [x0,y0,x1,y1] inclusive or null. */
    public function bounds(float $threshold = 0.002): ?array
    {
        if ($this->boundsCache !== null) {
            return $this->boundsCache ?: null;
        }
        $w = $this->width;
        $x0 = $w;
        $y0 = $this->height;
        $x1 = -1;
        $y1 = -1;
        foreach ($this->data as $i => $v) {
            if ($v > $threshold) {
                $y = intdiv($i, $w);
                $x = $i - $y * $w;
                if ($x < $x0) { $x0 = $x; }
                if ($x > $x1) { $x1 = $x; }
                if ($y < $y0) { $y0 = $y; }
                if ($y > $y1) { $y1 = $y; }
            }
        }
        if ($x1 < 0) {
            $this->boundsCache = [];
            return null;
        }
        return $this->boundsCache = [$x0, $y0, $x1, $y1];
    }

    public function isEmpty(): bool
    {
        return $this->bounds() === null;
    }

    public function union(Mask $o): Mask
    {
        $d = $this->data;
        foreach ($o->data as $i => $v) {
            if ($v > $d[$i]) {
                $d[$i] = $v;
            }
        }
        return new Mask($this->width, $this->height, $d);
    }

    public function subtract(Mask $o): Mask
    {
        $d = $this->data;
        foreach ($o->data as $i => $v) {
            if ($v > 0.0) {
                $nv = $d[$i] * (1.0 - $v);
                $d[$i] = $nv < 0 ? 0.0 : $nv;
            }
        }
        return new Mask($this->width, $this->height, $d);
    }

    public function scaleValues(float $f): Mask
    {
        $d = $this->data;
        foreach ($d as $i => $v) {
            if ($v > 0.0) {
                $nv = $v * $f;
                $d[$i] = $nv > 1.0 ? 1.0 : $nv;
            }
        }
        return new Mask($this->width, $this->height, $d);
    }

    public function offset(int $dx, int $dy): Mask
    {
        $w = $this->width;
        $h = $this->height;
        $out = array_fill(0, $w * $h, 0.0);
        $b = $this->bounds();
        if ($b === null) {
            return new Mask($w, $h, $out);
        }
        for ($y = $b[1]; $y <= $b[3]; $y++) {
            $ny = $y + $dy;
            if ($ny < 0 || $ny >= $h) {
                continue;
            }
            for ($x = $b[0]; $x <= $b[2]; $x++) {
                $nx = $x + $dx;
                if ($nx < 0 || $nx >= $w) {
                    continue;
                }
                $out[$ny * $w + $nx] = $this->data[$y * $w + $x];
            }
        }
        return new Mask($w, $h, $out);
    }

    /**
     * 3D extrusion: the shape is swept downwards by $px pixels (max filter
     * along the y axis, computed with log2 doubling steps).
     */
    public function extrude(int $px): Mask
    {
        if ($px <= 0) {
            return $this->copy();
        }
        $w = $this->width;
        $h = $this->height;
        $cur = $this->data;
        $done = 1; // window size covered so far
        while ($done <= $px) {
            $shift = min($done, $px + 1 - $done);
            if ($shift <= 0) {
                break;
            }
            $next = $cur;
            $offset = $shift * $w;
            $n = $w * $h;
            for ($i = $offset; $i < $n; $i++) {
                $v = $cur[$i - $offset];
                if ($v > $next[$i]) {
                    $next[$i] = $v;
                }
            }
            $cur = $next;
            $done += $shift;
        }
        return new Mask($w, $h, $cur);
    }

    /** Intersection (multiply) with another mask. */
    public function intersect(Mask $o): Mask
    {
        $d = $this->data;
        foreach ($d as $i => $v) {
            if ($v > 0.0) {
                $d[$i] = $v * $o->data[$i];
            }
        }
        return new Mask($this->width, $this->height, $d);
    }

    /**
     * Grows the shape by $radius pixels (round joins) using an exact EDT.
     * Result keeps anti-aliased edges.
     */
    public function dilate(float $radius): Mask
    {
        $w = $this->width;
        $h = $this->height;
        if ($radius <= 0.0) {
            return $this->copy();
        }
        $b = $this->bounds(0.5);
        if ($b === null) {
            return $this->copy();
        }
        $r = (int) ceil($radius) + 2;
        $x0 = max(0, $b[0] - $r);
        $y0 = max(0, $b[1] - $r);
        $x1 = min($w - 1, $b[2] + $r);
        $y1 = min($h - 1, $b[3] + $r);
        $bw = $x1 - $x0 + 1;
        $bh = $y1 - $y0 + 1;
        $inf = 1e20;
        $f = [];
        for ($y = 0; $y < $bh; $y++) {
            $row = ($y + $y0) * $w + $x0;
            for ($x = 0; $x < $bw; $x++) {
                $f[$y * $bw + $x] = $this->data[$row + $x] >= 0.5 ? 0.0 : $inf;
            }
        }
        $d2 = self::edt2d($f, $bw, $bh);
        $out = $this->data;
        $rp = $radius + 0.5;
        for ($y = 0; $y < $bh; $y++) {
            $row = ($y + $y0) * $w + $x0;
            for ($x = 0; $x < $bw; $x++) {
                $dd = $d2[$y * $bw + $x];
                if ($dd >= $inf) {
                    continue;
                }
                $c = $rp - sqrt($dd);
                if ($c <= 0.0) {
                    continue;
                }
                if ($c > 1.0) {
                    $c = 1.0;
                }
                $idx = $row + $x;
                if ($c > $out[$idx]) {
                    $out[$idx] = $c;
                }
            }
        }
        return new Mask($w, $h, $out);
    }

    /**
     * 2D squared Euclidean distance transform (Felzenszwalb & Huttenlocher).
     * @param list<float> $f
     * @return list<float>
     */
    private static function edt2d(array $f, int $w, int $h): array
    {
        // columns
        for ($x = 0; $x < $w; $x++) {
            $col = [];
            for ($y = 0; $y < $h; $y++) {
                $col[] = $f[$y * $w + $x];
            }
            $col = self::edt1d($col, $h);
            for ($y = 0; $y < $h; $y++) {
                $f[$y * $w + $x] = $col[$y];
            }
        }
        // rows
        for ($y = 0; $y < $h; $y++) {
            $row = array_slice($f, $y * $w, $w);
            $row = self::edt1d($row, $w);
            array_splice($f, $y * $w, $w, $row);
        }
        return $f;
    }

    /**
     * @param list<float> $f
     * @return list<float>
     */
    private static function edt1d(array $f, int $n): array
    {
        $inf = 1e20;
        $d = array_fill(0, $n, 0.0);
        $v = array_fill(0, $n, 0);
        $z = array_fill(0, $n + 1, 0.0);
        $k = 0;
        // first finite sample
        $first = -1;
        for ($q = 0; $q < $n; $q++) {
            if ($f[$q] < $inf) {
                $first = $q;
                break;
            }
        }
        if ($first < 0) {
            return array_fill(0, $n, $inf);
        }
        $v[0] = $first;
        $z[0] = -$inf;
        $z[1] = $inf;
        for ($q = $first + 1; $q < $n; $q++) {
            if ($f[$q] >= $inf) {
                continue;
            }
            $fq = $f[$q] + $q * $q;
            while (true) {
                $vk = $v[$k];
                $s = ($fq - ($f[$vk] + $vk * $vk)) / (2 * $q - 2 * $vk);
                if ($s <= $z[$k] && $k > 0) {
                    $k--;
                    continue;
                }
                if ($s <= $z[$k]) {
                    // k == 0 and parabola dominates
                    $v[0] = $q;
                    $z[0] = -$inf;
                    $z[1] = $inf;
                    break;
                }
                $k++;
                $v[$k] = $q;
                $z[$k] = $s;
                $z[$k + 1] = $inf;
                break;
            }
        }
        $k = 0;
        for ($q = 0; $q < $n; $q++) {
            while ($z[$k + 1] < $q) {
                $k++;
            }
            $vk = $v[$k];
            $d[$q] = ($q - $vk) * ($q - $vk) + $f[$vk];
        }
        return $d;
    }

    /** Approximate gaussian blur: 3 box blur passes, separable, bbox-limited. */
    public function blur(float $radius): Mask
    {
        if ($radius < 0.5) {
            return $this->copy();
        }
        $w = $this->width;
        $h = $this->height;
        $b = $this->bounds();
        if ($b === null) {
            return $this->copy();
        }
        $r = max(1, (int) round($radius / 1.73));
        $pad = $r * 3 + 1;
        $x0 = max(0, $b[0] - $pad);
        $y0 = max(0, $b[1] - $pad);
        $x1 = min($w - 1, $b[2] + $pad);
        $y1 = min($h - 1, $b[3] + $pad);
        $data = $this->data;
        for ($pass = 0; $pass < 3; $pass++) {
            // horizontal
            for ($y = $y0; $y <= $y1; $y++) {
                $base = $y * $w;
                $src = array_slice($data, $base + $x0, $x1 - $x0 + 1);
                $n = count($src);
                $sum = 0.0;
                $inv = 1.0 / (2 * $r + 1);
                for ($i = -$r; $i <= $r; $i++) {
                    $sum += ($i >= 0 && $i < $n) ? $src[$i] : 0.0;
                }
                for ($i = 0; $i < $n; $i++) {
                    $data[$base + $x0 + $i] = $sum * $inv;
                    $add = $i + $r + 1;
                    $rem = $i - $r;
                    $sum += ($add < $n ? $src[$add] : 0.0) - ($rem >= 0 ? $src[$rem] : 0.0);
                }
            }
            // vertical
            for ($x = $x0; $x <= $x1; $x++) {
                $src = [];
                for ($y = $y0; $y <= $y1; $y++) {
                    $src[] = $data[$y * $w + $x];
                }
                $n = count($src);
                $sum = 0.0;
                $inv = 1.0 / (2 * $r + 1);
                for ($i = -$r; $i <= $r; $i++) {
                    $sum += ($i >= 0 && $i < $n) ? $src[$i] : 0.0;
                }
                for ($i = 0; $i < $n; $i++) {
                    $data[($y0 + $i) * $w + $x] = $sum * $inv;
                    $add = $i + $r + 1;
                    $rem = $i - $r;
                    $sum += ($add < $n ? $src[$add] : 0.0) - ($rem >= 0 ? $src[$rem] : 0.0);
                }
            }
        }
        return new Mask($w, $h, $data);
    }

    /** Builds a mask from the alpha channel of a GD true colour image. */
    public static function fromImageAlpha(\GdImage $im): Mask
    {
        $w = imagesx($im);
        $h = imagesy($im);
        $data = array_fill(0, $w * $h, 0.0);
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                $a = (imagecolorat($im, $x, $y) >> 24) & 0x7F;
                if ($a < 127) {
                    $data[$row + $x] = (127 - $a) / 127.0;
                }
            }
        }
        return new Mask($w, $h, $data);
    }

    /**
     * Paints this mask with a solid colour (or vertical gradient) onto an image.
     * @param array{0:int,1:int,2:int} $rgb
     * @param array{0:int,1:int,2:int}|null $rgb2 bottom colour for gradients
     */
    public function paint(\GdImage $im, array $rgb, float $opacity = 1.0, ?array $rgb2 = null, ?array $gradBox = null): void
    {
        $w = $this->width;
        $b = $this->bounds();
        if ($b === null || $opacity <= 0.0) {
            return;
        }
        imagealphablending($im, true);
        $gy0 = $gradBox[1] ?? $b[1];
        $gy1 = $gradBox[3] ?? $b[3];
        $gh = max(1, $gy1 - $gy0);
        $cache = [];
        for ($y = $b[1]; $y <= $b[3]; $y++) {
            $row = $y * $w;
            if ($rgb2 !== null) {
                $t = min(1.0, max(0.0, ($y - $gy0) / $gh));
                $r = (int) round($rgb[0] + ($rgb2[0] - $rgb[0]) * $t);
                $g = (int) round($rgb[1] + ($rgb2[1] - $rgb[1]) * $t);
                $bl = (int) round($rgb[2] + ($rgb2[2] - $rgb[2]) * $t);
                $cache = [];
            } else {
                [$r, $g, $bl] = $rgb;
            }
            $colorBase = ($r << 16) | ($g << 8) | $bl;
            for ($x = $b[0]; $x <= $b[2]; $x++) {
                $v = $this->data[$row + $x];
                if ($v <= 0.002) {
                    continue;
                }
                $a = 127 - (int) round($v * $opacity * 127);
                if ($a >= 127) {
                    continue;
                }
                imagesetpixel($im, $x, $y, ($a << 24) | $colorBase);
            }
        }
    }
}

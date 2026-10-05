<?php

declare(strict_types=1);

namespace App\Render;

use GdImage;

/**
 * Small drawing layer over GD. Everything is drawn at $scale× resolution and
 * downsampled on save, which gives smooth (anti-aliased) shapes and lines.
 * All coordinates passed in are logical (final image) pixels.
 */
final class Canvas
{
    public GdImage $im;
    private array $colors = [];
    private string $fontDir;

    public function __construct(public int $w, public int $h, private int $s = 2, string $fontDir = '')
    {
        $this->im = imagecreatetruecolor($w * $s, $h * $s);
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
        $this->fontDir = $fontDir;
    }

    /** @param float $opacity 0..1 */
    public function color(string $hex, float $opacity = 1.0): int
    {
        $key = $hex . '|' . $opacity;
        if (isset($this->colors[$key])) {
            return $this->colors[$key];
        }
        $hex = ltrim($hex, '#');
        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $alpha = 127 - (int) round(max(0, min(1, $opacity)) * 127);
        return $this->colors[$key] = imagecolorallocatealpha($this->im, $r, $g, $b, $alpha);
    }

    public static function mix(string $a, string $b, float $t): string
    {
        $a = ltrim($a, '#');
        $b = ltrim($b, '#');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $x = hexdec(substr($a, $i * 2, 2));
            $y = hexdec(substr($b, $i * 2, 2));
            $out .= sprintf('%02x', (int) round($x + ($y - $x) * $t));
        }
        return $out;
    }

    private function p(float $v): int
    {
        return (int) round($v * $this->s);
    }

    // ------------------------------------------------------------------ shapes

    public function rect(float $x1, float $y1, float $x2, float $y2, int $color): void
    {
        if ($x2 < $x1) {
            [$x1, $x2] = [$x2, $x1];
        }
        if ($y2 < $y1) {
            [$y1, $y2] = [$y2, $y1];
        }
        imagefilledrectangle($this->im, $this->p($x1), $this->p($y1), max($this->p($x1), $this->p($x2) - 1), max($this->p($y1), $this->p($y2) - 1), $color);
    }

    /**
     * Rounded rectangle filled row by row (each pixel painted once, so translucent fills stay even).
     * $fill may be a color or a [hexTop, hexBottom, opacityTop, opacityBottom] vertical gradient,
     * or ['h', hexLeft, hexRight, opLeft, opRight] for a horizontal one.
     */
    public function roundRect(float $x, float $y, float $w, float $h, float $r, int|array $fill): void
    {
        $X = $this->p($x);
        $Y = $this->p($y);
        $W = $this->p($w);
        $H = $this->p($h);
        $R = min($this->p($r), intdiv($W, 2), intdiv($H, 2));
        $horizontal = is_array($fill) && $fill[0] === 'h';
        if ($horizontal) {
            // Horizontal gradient: draw columns, inset by the corner radius at top/bottom.
            for ($col = 0; $col < $W; $col++) {
                $inset = 0;
                if ($col < $R) {
                    $dx = $R - $col - 0.5;
                    $inset = (int) round($R - sqrt(max(0, $R * $R - $dx * $dx)));
                } elseif ($col >= $W - $R) {
                    $dx = $col - ($W - $R) + 0.5;
                    $inset = (int) round($R - sqrt(max(0, $R * $R - $dx * $dx)));
                }
                $t = $W > 1 ? $col / ($W - 1) : 0;
                $c = $this->color(self::mix($fill[1], $fill[2], $t), $fill[3] + ($fill[4] - $fill[3]) * $t);
                imageline($this->im, $X + $col, $Y + $inset, $X + $col, $Y + $H - 1 - $inset, $c);
            }
            return;
        }
        for ($row = 0; $row < $H; $row++) {
            $inset = 0;
            if ($row < $R) {
                $dy = $R - $row - 0.5;
                $inset = (int) round($R - sqrt(max(0, $R * $R - $dy * $dy)));
            } elseif ($row >= $H - $R) {
                $dy = $row - ($H - $R) + 0.5;
                $inset = (int) round($R - sqrt(max(0, $R * $R - $dy * $dy)));
            }
            if (is_array($fill)) {
                $t = $H > 1 ? $row / ($H - 1) : 0;
                $c = $this->color(self::mix($fill[0], $fill[1], $t), $fill[2] + ($fill[3] - $fill[2]) * $t);
            } else {
                $c = $fill;
            }
            imageline($this->im, $X + $inset, $Y + $row, $X + $W - 1 - $inset, $Y + $row, $c);
        }
    }

    /** Rounded outline drawn as a thin ring (outer fill minus inner fill is not possible with alpha, so we draw strokes). */
    public function roundRectStroke(float $x, float $y, float $w, float $h, float $r, int $color, float $width = 1): void
    {
        $pts = [];
        $seg = 10;
        $corners = [[$x + $w - $r, $y + $r, -90], [$x + $w - $r, $y + $h - $r, 0], [$x + $r, $y + $h - $r, 90], [$x + $r, $y + $r, 180]];
        foreach ($corners as [$cx, $cy, $start]) {
            for ($i = 0; $i <= $seg; $i++) {
                $a = deg2rad($start + 90 * $i / $seg);
                $pts[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
            }
        }
        $pts[] = $pts[0];
        $this->polyline($pts, $color, $width, false);
    }

    public function line(float $x1, float $y1, float $x2, float $y2, int $color, float $width = 1): void
    {
        $t = max(1, $this->p($width));
        if ($t <= 1) {
            imagesetthickness($this->im, 1);
            imageline($this->im, $this->p($x1), $this->p($y1), $this->p($x2), $this->p($y2), $color);
            return;
        }
        // Thick line as a polygon so translucent strokes blend once.
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = sqrt($dx * $dx + $dy * $dy);
        if ($len < 0.01) {
            return;
        }
        $nx = -$dy / $len * $width / 2;
        $ny = $dx / $len * $width / 2;
        $this->polygon([[$x1 + $nx, $y1 + $ny], [$x2 + $nx, $y2 + $ny], [$x2 - $nx, $y2 - $ny], [$x1 - $nx, $y1 - $ny]], $color);
    }

    public function dashed(float $x1, float $y1, float $x2, float $y2, int $color, float $width = 1, float $dash = 8, float $gap = 6): void
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = sqrt($dx * $dx + $dy * $dy);
        if ($len < 0.01) {
            return;
        }
        $ux = $dx / $len;
        $uy = $dy / $len;
        for ($d = 0; $d < $len; $d += $dash + $gap) {
            $e = min($len, $d + $dash);
            $this->line($x1 + $ux * $d, $y1 + $uy * $d, $x1 + $ux * $e, $y1 + $uy * $e, $color, $width);
        }
    }

    public function polyline(array $pts, int $color, float $width = 1, bool $roundJoins = true): void
    {
        $c = count($pts);
        for ($i = 1; $i < $c; $i++) {
            $this->line($pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], $color, $width);
        }
        if ($roundJoins && $width > 1.5) {
            for ($i = 1; $i < $c - 1; $i++) {
                $this->circle($pts[$i][0], $pts[$i][1], $width / 2, $color);
            }
        }
    }

    public function dashedPolyline(array $pts, int $color, float $width = 2, float $dash = 10, float $gap = 7): void
    {
        $c = count($pts);
        for ($i = 1; $i < $c; $i++) {
            $this->dashed($pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], $color, $width, $dash, $gap);
        }
    }

    public function polygon(array $pts, int $color): void
    {
        $flat = [];
        foreach ($pts as [$x, $y]) {
            $flat[] = $this->p($x);
            $flat[] = $this->p($y);
        }
        if (count($flat) >= 6) {
            imagefilledpolygon($this->im, $flat, $color);
        }
    }

    public function circle(float $cx, float $cy, float $r, int $color): void
    {
        $d = max(1, $this->p($r * 2));
        imagefilledellipse($this->im, $this->p($cx), $this->p($cy), $d, $d, $color);
    }

    /** Thick arc (ring segment). Angles in degrees, 0 = 3 o'clock, clockwise. */
    public function arc(float $cx, float $cy, float $r, float $width, float $from, float $to, int $color): void
    {
        $outer = [];
        $inner = [];
        $steps = max(8, (int) (abs($to - $from) / 3));
        for ($i = 0; $i <= $steps; $i++) {
            $a = deg2rad($from + ($to - $from) * $i / $steps);
            $outer[] = [$cx + ($r + $width / 2) * cos($a), $cy + ($r + $width / 2) * sin($a)];
            $inner[] = [$cx + ($r - $width / 2) * cos($a), $cy + ($r - $width / 2) * sin($a)];
        }
        $this->polygon(array_merge($outer, array_reverse($inner)), $color);
        // round caps
        $a0 = deg2rad($from);
        $a1 = deg2rad($to);
        $this->circle($cx + $r * cos($a0), $cy + $r * sin($a0), $width / 2, $color);
        $this->circle($cx + $r * cos($a1), $cy + $r * sin($a1), $width / 2, $color);
    }

    /** Soft radial glow made of stacked translucent discs. */
    public function glow(float $cx, float $cy, float $r, string $hex, float $opacity = 0.25, int $steps = 18): void
    {
        for ($i = $steps; $i >= 1; $i--) {
            $this->circle($cx, $cy, $r * $i / $steps, $this->color($hex, $opacity / $steps));
        }
    }

    public function triangle(float $cx, float $cy, float $size, bool $up, int $color): void
    {
        $h = $size * 0.9;
        $this->polygon($up
            ? [[$cx, $cy - $h / 2], [$cx + $size / 2, $cy + $h / 2], [$cx - $size / 2, $cy + $h / 2]]
            : [[$cx, $cy + $h / 2], [$cx + $size / 2, $cy - $h / 2], [$cx - $size / 2, $cy - $h / 2]], $color);
    }

    public function arrowHead(float $x, float $y, float $angle, float $size, int $color): void
    {
        $a1 = $angle + deg2rad(150);
        $a2 = $angle - deg2rad(150);
        $this->polygon([
            [$x + cos($angle) * $size * 0.35, $y + sin($angle) * $size * 0.35],
            [$x + cos($a1) * $size, $y + sin($a1) * $size],
            [$x + cos($a2) * $size, $y + sin($a2) * $size],
        ], $color);
    }

    public function check(float $cx, float $cy, float $size, int $color, float $width = 2.4): void
    {
        $this->polyline([[$cx - $size * 0.45, $cy], [$cx - $size * 0.1, $cy + $size * 0.35], [$cx + $size * 0.5, $cy - $size * 0.35]], $color, $width);
    }

    // -------------------------------------------------------------------- text

    public function font(string $weight): string
    {
        return $this->fontDir !== '' ? $this->fontDir . '/Vazirmatn-' . $weight . '.ttf' : app_font($weight);
    }

    /** @return array{0: float, 1: float} width and height in logical px */
    public function measure(string $text, float $size, string $weight = 'Regular'): array
    {
        $v = Persian::visual($text);
        $box = imagettfbbox($size * $this->s, 0, $this->font($weight), $v);
        return [($box[2] - $box[0]) / $this->s, ($box[1] - $box[7]) / $this->s];
    }

    /**
     * Draws text with its baseline at $y. $align: left | right | center.
     * Returns the drawn width.
     */
    public function text(float $x, float $y, string $text, float $size, int $color, string $weight = 'Regular', string $align = 'left'): float
    {
        if ($text === '') {
            return 0;
        }
        $v = Persian::visual($text);
        $font = $this->font($weight);
        $box = imagettfbbox($size * $this->s, 0, $font, $v);
        $w = ($box[2] - $box[0]) / $this->s;
        $sx = match ($align) {
            'right' => $x - $w,
            'center' => $x - $w / 2,
            default => $x,
        };
        imagettftext($this->im, $size * $this->s, 0, $this->p($sx) - $box[0], $this->p($y), $color, $font, $v);
        return $w;
    }

    /** Height of a capital letter, used to center text vertically. */
    public function capHeight(float $size, string $weight = 'Regular'): float
    {
        $box = imagettfbbox($size * $this->s, 0, $this->font($weight), 'H');
        return ($box[1] - $box[7]) / $this->s;
    }

    /** Shortens text with an ellipsis until it fits $maxWidth. */
    public function fit(string $text, float $size, string $weight, float $maxWidth): string
    {
        if ($this->measure($text, $size, $weight)[0] <= $maxWidth) {
            return $text;
        }
        $words = preg_split('/\s+/u', $text);
        while (count($words) > 1) {
            array_pop($words);
            $candidate = implode(' ', $words) . '…';
            if ($this->measure($candidate, $size, $weight)[0] <= $maxWidth) {
                return $candidate;
            }
        }
        return mb_substr($text, 0, 12) . '…';
    }

    /** Pill-shaped label; returns its width. */
    public function pill(float $x, float $y, string $text, float $size, int $textColor, int|array $bg, string $weight = 'Bold', string $align = 'left', float $padX = 12, float $h = 0): float
    {
        [$tw] = $this->measure($text, $size, $weight);
        $h = $h ?: $size * 1.9;
        $w = $tw + $padX * 2;
        $left = match ($align) {
            'right' => $x - $w,
            'center' => $x - $w / 2,
            default => $x,
        };
        $this->roundRect($left, $y, $w, $h, $h / 2, $bg);
        $this->text($left + $padX, $y + $h / 2 + $this->capHeight($size, $weight) / 2, $text, $size, $textColor, $weight);
        return $w;
    }

    public function save(string $path): void
    {
        $out = imagecreatetruecolor($this->w, $this->h);
        imagecopyresampled($out, $this->im, 0, 0, 0, 0, $this->w, $this->h, $this->w * $this->s, $this->h * $this->s);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'jpg' || $ext === 'jpeg') {
            imagejpeg($out, $path, 93);
        } else {
            imagepng($out, $path, 6);
        }
        imagedestroy($out);
    }
}

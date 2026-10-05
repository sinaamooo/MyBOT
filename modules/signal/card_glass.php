<?php

declare(strict_types=1);

// "Glass" card design (default): only the coin symbol on a frosted glass panel.
// Black background, two orange orbs behind the glass, white symbol. Every trade detail
// (entry, targets, stop, result) lives in the caption, so the same card is used for
// signals and results. 1600 x 900 (16:9).

final class GlassSymbolCard
{
    public const W = 1600;
    public const H = 900;

    private const STEP = 4;   // frosted area
    private const FINE = 2;   // sharp area outside the glass
    // x, y, w, h, radius — centred
    private const GLASS = [330.0, 200.0, 940.0, 500.0, 72.0];
    private const BASE = [0, 0, 0];
    private const WHITE = [255, 255, 255];
    private const ORANGE = [255, 138, 0];
    // centre x, centre y, radius, colour — the first two sit behind the glass corners
    private const ORBS = [
        [455.0, 250.0, 235.0, [255, 138, 0]],
        [1165.0, 655.0, 215.0, [255, 92, 0]],
        [1295.0, 175.0, 70.0, [255, 186, 102]],
    ];

    public static function render(array $d): string
    {
        $c = self::backdrop();
        self::rim($c);
        self::symbol($c, self::coin((string) ($d['symbol'] ?? '')));
        $png = $c->toPng();
        $c->destroy();
        return $png;
    }

    /** Base asset only: "BTC/USDT", "BTCUSDT", "BTC-USDT-PERP" -> "BTC". */
    public static function coin(string $symbol): string
    {
        $s = strtoupper(trim($symbol));
        $s = (string) preg_replace('~[\s._/-]*(PERP|SWAP)$~', '', $s);
        $s = str_replace(['-', '_', ' '], '/', $s);
        if (str_contains($s, '/')) {
            $s = explode('/', $s)[0];
        } else {
            foreach (['USDT', 'USDC', 'FDUSD', 'BUSD', 'USD'] as $q) {
                if (str_ends_with($s, $q) && strlen($s) > strlen($q)) {
                    $s = substr($s, 0, -strlen($q));
                    break;
                }
            }
        }
        return $s !== '' ? $s : '—';
    }

    private static function backdrop(): CardCanvas
    {
        $c = new CardCanvas(self::W, self::H);
        // Outside the glass: crisp orbs (fine grid). Behind the glass: frosted copy (coarse grid, blurred).
        [$outside, $lw, $lh] = self::field(self::FINE, true);
        $c->blitField($outside, $lw, $lh, self::FINE);
        [$behind, $lw, $lh] = self::field(self::STEP, false);
        $c->blitField(self::frost($behind, $lw, $lh), $lw, $lh, self::STEP, self::GLASS);
        return $c;
    }

    /** @return array{0: array, 1: int, 2: int} [rgb planes, width, height] sampled every $step px */
    private static function field(int $step, bool $withShadow): array
    {
        [$gx, $gy, $gw, $gh, $gr] = self::GLASS;
        $lw = intdiv(self::W, $step) + 1;
        $lh = intdiv(self::H, $step) + 1;
        $planes = [[], [], []];
        for ($j = 0; $j < $lh; $j++) {
            $y = (float) ($j * $step);
            for ($i = 0; $i < $lw; $i++) {
                $x = (float) ($i * $step);
                $rgb = self::BASE;
                foreach (self::ORBS as [$ox, $oy, $or, $col]) {
                    [$shade, $cover] = self::sphere($x, $y, $ox, $oy, $or);
                    if ($cover > 0.0) {
                        $rgb = CardCanvas::mix([$col[0] * $shade, $col[1] * $shade, $col[2] * $shade], $rgb, $cover);
                    }
                }
                // soft shadow of the glass panel, dropped 40px down
                $shadow = $withShadow ? 1.0 - 0.55 / (1.0 + exp(1.702 * self::roundedDistance($x, $y - 40.0, $gx, $gy, $gw, $gh, $gr) / 44.0)) : 1.0;
                $planes[0][] = $rgb[0] * $shadow;
                $planes[1][] = $rgb[1] * $shadow;
                $planes[2][] = $rgb[2] * $shadow;
            }
        }
        return [$planes, $lw, $lh];
    }

    /** Lit sphere: [brightness, coverage] at a point. */
    private static function sphere(float $x, float $y, float $cx, float $cy, float $r): array
    {
        $d = hypot($x - $cx, $y - $cy);
        $cover = 1.0 / (1.0 + exp(($d - $r) / 0.7));
        if ($cover < 0.002) {
            return [0.0, 0.0];
        }
        // highlight up-left, falling off towards the lower right edge
        $t = hypot($x - ($cx - $r * 0.35), $y - ($cy - $r * 0.4)) / ($r * 1.75);
        return [max(0.28, 1.0 - 0.72 * $t * $t), $cover];
    }

    private static function roundedDistance(float $px, float $py, float $x, float $y, float $w, float $h, float $r): float
    {
        $qx = abs($px - ($x + $w / 2)) - ($w / 2 - $r);
        $qy = abs($py - ($y + $h / 2)) - ($h / 2 - $r);
        return hypot(max($qx, 0.0), max($qy, 0.0)) + min(max($qx, $qy), 0.0) - $r;
    }

    /** Frosted glass: heavy blur of what is behind, darkened a little, with a white sheen from the top. */
    private static function frost(array $field, int $lw, int $lh): array
    {
        $sigma = 72.0 / self::STEP;
        $out = [
            CardCanvas::blur($field[0], $lw, $lh, $sigma),
            CardCanvas::blur($field[1], $lw, $lh, $sigma),
            CardCanvas::blur($field[2], $lw, $lh, $sigma),
        ];
        [, $gy, , $gh] = self::GLASS;
        $n = $lw * $lh;
        for ($k = 0; $k < $n; $k++) {
            $t = max(0.0, min(1.0, (intdiv($k, $lw) * self::STEP - $gy) / $gh));
            $sheen = 0.095 - 0.045 * $t;
            for ($ch = 0; $ch < 3; $ch++) {
                $v = $out[$ch][$k] * 0.74;
                $out[$ch][$k] = $v + (255.0 - $v) * $sheen;
            }
        }
        return $out;
    }

    private static function rim(CardCanvas $c): void
    {
        [$x, $y, $w, $h, $r] = self::GLASS;
        // thin bright line along the top edge
        $c->ring($x + 0.5, $y + 0.5, $w - 1.0, $h - 1.0, $r - 0.5, 1.0, self::WHITE, static fn(float $px, float $py): float => 0.45 * max(0.0, 1.0 - ($py - $y - 0.5) / 16.0));
        // diagonal gradient border: bright top-left, faint middle, brighter bottom-right
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $len = ($w + $h) * M_SQRT1_2;
        $stops = [[0.0, 0.6], [0.45, 0.07], [0.7, 0.04], [1.0, 0.28]];
        $c->ring($x + 0.75, $y + 0.75, $w - 1.5, $h - 1.5, $r - 0.75, 1.5, self::WHITE, static function (float $px, float $py) use ($cx, $cy, $len, $stops): float {
            $t = max(0.0, min(1.0, (($px - $cx) + ($py - $cy)) * M_SQRT1_2 / $len + 0.5));
            for ($i = 1, $n = count($stops); $i < $n; $i++) {
                if ($t <= $stops[$i][0]) {
                    $k = ($t - $stops[$i - 1][0]) / ($stops[$i][0] - $stops[$i - 1][0]);
                    return $stops[$i - 1][1] + ($stops[$i][1] - $stops[$i - 1][1]) * $k;
                }
            }
            return $stops[count($stops) - 1][1];
        });
    }

    private static function symbol(CardCanvas $c, string $coin): void
    {
        [$x, $y, $w, $h] = self::GLASS;
        $tracking = 0.02;
        $size = $c->fit($coin, 240.0, $w - 180.0, 'heavy', $tracking, 72.0);
        $cap = $c->capHeight($size, 'heavy', $coin);
        $barGap = max(28.0, $cap * 0.24);
        $barH = 6.0;
        $block = $cap + $barGap + $barH;
        $capTop = $y + ($h - $block) / 2;
        $cx = $x + $w / 2;

        $c->glowText($coin, $cx, $capTop, $size, self::WHITE, 'heavy', 'center', $tracking, 0.32, 44.0);
        $c->write($coin, $cx, $capTop, $size, self::WHITE, 'heavy', 'center', $tracking);
        // small orange accent under the symbol
        $c->roundRect($cx - 40.0, $capTop + $cap + $barGap, 80.0, $barH, $barH / 2, self::ORANGE);
    }
}

<?php

declare(strict_types=1);

// "Glass" card design (default): a frosted glass panel over orange orbs on black.
//  - Signal card: only the coin symbol. Entry, targets and stop live in the caption.
//  - Result card (profit shot): coin + target hit, the leveraged PnL in large type, side and leverage.
// 1600 x 900 (16:9).

final class GlassSymbolCard
{
    public const W = 1600;
    public const H = 900;

    private const STEP = 4;   // frosted area
    private const FINE = 2;   // sharp area outside the glass
    // x, y, w, h, radius — centred
    public const GLASS = [330.0, 200.0, 940.0, 500.0, 72.0];
    private const BASE = [0, 0, 0];
    public const WHITE = [255, 255, 255];
    public const ORANGE = [255, 138, 0];
    // centre x, centre y, radius, colour — the first two sit behind the glass corners
    private const ORBS = [
        [455.0, 250.0, 235.0, [255, 138, 0]],
        [1165.0, 655.0, 215.0, [255, 92, 0]],
        [1295.0, 175.0, 70.0, [255, 186, 102]],
    ];

    public static function render(array $d): string
    {
        $c = self::canvas();
        self::symbol($c, self::coin((string) ($d['symbol'] ?? '')));
        $png = $c->toPng();
        $c->destroy();
        return $png;
    }

    /** Background, frosted glass and its rim — shared by the signal and result cards. */
    public static function canvas(): CardCanvas
    {
        $c = self::backdrop();
        self::rim($c);
        return $c;
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

final class GlassResultCard
{
    private const LABELS = [
        'tp1' => 'TARGET 1',
        'tp2' => 'TARGET 2',
        'tp3' => 'TARGET 3',
        'tp4' => 'ALL TARGETS',
        'trail' => 'PROFIT LOCKED',
        'be' => 'BREAK EVEN',
        'sl' => 'STOP LOSS',
        'timeout' => 'TIME LIMIT',
    ];
    private const PROFIT = [38, 230, 150];
    private const LOSS = [255, 77, 90];

    public static function render(array $d): string
    {
        [$x, $y, $w, $h] = GlassSymbolCard::GLASS;
        $white = GlassSymbolCard::WHITE;
        $kind = strtolower((string) ($d['kind'] ?? ''));
        $headline = trim((string) ($d['headline'] ?? ''));
        $headline = $headline !== '' ? $headline : '0.00%';
        $pnl = CardFormat::number($headline) ?? 0.0;
        $tone = $pnl > 0.004 ? self::PROFIT : ($pnl < -0.004 ? self::LOSS : $white);
        $label = self::LABELS[$kind] ?? 'TRADE CLOSED';
        $side = strtoupper(trim((string) ($d['direction'] ?? '')));
        $lev = strtoupper(trim((string) ($d['leverage'] ?? '')));
        $sub = implode('  ·  ', array_filter([$side, $lev], static fn(string $v): bool => $v !== '' && $v !== 'X'));

        $c = GlassSymbolCard::canvas();
        $cx = $x + $w / 2;

        // line 1: COIN  •  LABEL
        $coin = GlassSymbolCard::coin((string) ($d['symbol'] ?? ''));
        $s1 = 50.0;
        $s2 = 26.0;
        $tracking = 0.16;
        $coinSize = $c->fit($coin, $s1, $w * 0.45, 'heavy', 0.02, 28.0);
        $wCoin = $c->measure($coin, $coinSize, 'heavy', 0.02);
        $wDot = 52.0;
        $wLabel = $c->measure($label, $s2, 'semi', $tracking);
        $cap1 = $c->capHeight($s1, 'heavy');
        $cap2 = $c->capHeight($s2, 'semi');

        // the result, big
        $head = CardFormat::signed($headline);
        $sH = $c->fit($head, 200.0, $w - 150.0, 'heavy', 0.0, 80.0);
        $capH = $c->capHeight($sH, 'heavy', '0');

        $s3 = 22.0;
        $cap3 = $sub !== '' ? $c->capHeight($s3, 'semi') : 0.0;
        $g1 = 54.0;
        $g2 = $sub !== '' ? 50.0 : 0.0;
        $top = $y + ($h - ($cap1 + $g1 + $capH + $g2 + $cap3)) / 2;

        $lx = $cx - ($wCoin + $wDot + $wLabel) / 2;
        $c->write($coin, $lx, $top + ($cap1 - $c->capHeight($coinSize, 'heavy')) / 2, $coinSize, $white, 'heavy', 'left', 0.02);
        $c->roundRect($lx + $wCoin + $wDot / 2 - 4, $top + $cap1 / 2 - 4, 8, 8, 4, $white, 0.45);
        $c->write($label, $lx + $wCoin + $wDot, $top + ($cap1 - $cap2) / 2, $s2, GlassSymbolCard::ORANGE, 'semi', 'left', $tracking);

        $hy = $top + $cap1 + $g1;
        $c->glowText($head, $cx, $hy, $sH, $tone, 'heavy', 'center', 0.0, 0.55, 56.0);
        $c->write($head, $cx, $hy, $sH, $tone, 'heavy', 'center');

        if ($sub !== '') {
            $c->write($sub, $cx, $hy + $capH + $g2, $s3, $white, 'semi', 'center', 0.18, 0.55);
        }

        $png = $c->toPng();
        $c->destroy();
        return $png;
    }
}

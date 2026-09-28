<?php

declare(strict_types=1);

// "Neo" card design: aurora background, coin badge, a glowing headline figure and a glass chart
// panel that plots the recent price with the stop, entry and targets drawn as levels. The previous
// glass design stays in card.php as the "classic" style (CARD_STYLE=classic).

final class NeoPalette
{
    public const BG = [6, 8, 16];
    public const PANEL = [14, 18, 32];
    public const WHITE = [255, 255, 255];
    public const DIM = [152, 163, 190];
    public const FAINT = [92, 103, 130];
    public const MINT = [22, 229, 160];
    public const MINT_SOFT = [150, 250, 214];
    public const ROSE = [255, 72, 112];
    public const ROSE_SOFT = [255, 160, 182];
    public const AMBER = [255, 196, 87];
    public const INK = [4, 16, 12];

    // [accent, soft accent, second aurora colour, third aurora colour]
    public static function scheme(string $tone): array
    {
        return match ($tone) {
            'long', 'profit' => [self::MINT, self::MINT_SOFT, [40, 150, 255], [120, 90, 255]],
            'short', 'loss' => [self::ROSE, self::ROSE_SOFT, [255, 138, 61], [150, 70, 255]],
            default => [[128, 150, 205], [200, 212, 236], [70, 110, 200], [110, 90, 200]],
        };
    }
}

final class NeoFormat
{
    // Adds thousands separators to plain numeric prices ("64250.5" → "64,250.5").
    public static function price(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return 'N/A';
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            return $value;
        }
        [$int, $dec] = array_pad(explode('.', $value, 2), 2, null);
        $neg = str_starts_with($int, '-');
        $int = ltrim($int, '-');
        $grouped = strrev(implode(',', str_split(strrev($int), 3)));
        return ($neg ? '−' : '') . $grouped . ($dec !== null ? '.' . $dec : '');
    }

    public static function splitPair(string $pair): array
    {
        $pair = strtoupper(trim($pair));
        if (str_contains($pair, '/')) {
            [$base, $quote] = explode('/', $pair, 2);
            return [$base, '/' . $quote];
        }
        return [$pair, ''];
    }

    public static function number(?string $value): ?float
    {
        $clean = str_replace([',', ' ', '−'], ['', '', '-'], trim((string) $value));
        return is_numeric($clean) ? (float) $clean : null;
    }
}

// Small deterministic generator so decorative price paths are stable per symbol without
// touching PHP's global mt_rand state.
final class NeoNoise
{
    private int $state;

    public function __construct(string $seed)
    {
        $this->state = crc32($seed) ?: 1;
    }

    public function next(): float
    {
        $this->state = ($this->state * 1103515245 + 12345) & 0x7fffffff;
        return $this->state / 0x7fffffff * 2 - 1;
    }
}

final class NeoLayout
{
    public const W = 1600;
    public const H = 900;
    public const LEFT = 88.0;
    private const STEP = 4;

    // Glass chart panel and the plot area inside it.
    public const PANEL = [612.0, 84.0, 940.0, 560.0, 36.0];
    public const PLOT = [648.0, 132.0, 684.0, 468.0];
    public const TAG_X = 1346.0;

    // Everything soft is baked into one low-resolution field: aurora glows, vignette, the glass
    // panel tint and the gradient under the price line. Crisp strokes are drawn on top.
    public static function begin(string $tone, array $curve): CardCanvas
    {
        [$accent, , $second, $third] = NeoPalette::scheme($tone);
        [$px, $py, $pw, $ph, $pr] = self::PANEL;
        [$ax, $ay, $aw, $ah] = self::PLOT;
        $plotBottom = $ay + $ah;
        $lw = intdiv(self::W, self::STEP) + 1;
        $lh = intdiv(self::H, self::STEP) + 1;

        $curveAt = self::sampler($curve);
        $field = [[], [], []];
        for ($j = 0; $j < $lh; $j++) {
            $y = (float) ($j * self::STEP);
            for ($i = 0; $i < $lw; $i++) {
                $x = (float) ($i * self::STEP);
                $rgb = NeoPalette::BG;
                $rgb = CardCanvas::mix($accent, $rgb, 0.46 * self::falloff($x, $y, 1320.0, 60.0, 760.0));
                $rgb = CardCanvas::mix($second, $rgb, 0.30 * self::falloff($x, $y, 120.0, 900.0, 720.0));
                $rgb = CardCanvas::mix($third, $rgb, 0.22 * self::falloff($x, $y, 760.0, -120.0, 520.0));
                $rgb = CardCanvas::mix($accent, $rgb, 0.14 * self::falloff($x, $y, 300.0, 430.0, 420.0));

                $inPanel = self::roundedDistance($x, $y, $px, $py, $pw, $ph, $pr) < 0;
                if ($inPanel) {
                    $rgb = CardCanvas::mix(NeoPalette::PANEL, $rgb, 0.62);
                    $cy = $curveAt($x);
                    if ($cy !== null && $y >= $cy && $y <= $plotBottom) {
                        $t = ($y - $cy) / max(1.0, $plotBottom - $cy);
                        $rgb = CardCanvas::mix($accent, $rgb, 0.34 * (1.0 - $t) ** 1.6);
                    }
                }

                $dx = ($x - self::W / 2) / (self::W / 2);
                $dy = ($y - self::H / 2) / (self::H / 2);
                $vignette = 1.0 - 0.34 * min(1.0, ($dx * $dx + $dy * $dy) / 2.0);
                $field[0][] = $rgb[0] * $vignette;
                $field[1][] = $rgb[1] * $vignette;
                $field[2][] = $rgb[2] * $vignette;
            }
        }
        $c = new CardCanvas(self::W, self::H);
        $c->blitField($field, $lw, $lh, self::STEP);

        $c->ring($px + 0.75, $py + 0.75, $pw - 1.5, $ph - 1.5, $pr - 0.75, 1.5, NeoPalette::WHITE,
            static fn(float $x, float $y): float => 0.05 + 0.20 * max(0.0, 1.0 - hypot($x - ($px + $pw), $y - $py) / 700.0));
        return $c;
    }

    private static function sampler(array $curve): callable
    {
        return static function (float $x) use ($curve): ?float {
            $n = count($curve);
            if ($n < 2 || $x < $curve[0][0] || $x > $curve[$n - 1][0]) {
                return null;
            }
            for ($k = 1; $k < $n; $k++) {
                if ($x <= $curve[$k][0]) {
                    [$x1, $y1] = $curve[$k - 1];
                    [$x2, $y2] = $curve[$k];
                    return $x2 > $x1 ? $y1 + ($y2 - $y1) * ($x - $x1) / ($x2 - $x1) : $y2;
                }
            }
            return null;
        };
    }

    private static function falloff(float $x, float $y, float $cx, float $cy, float $r): float
    {
        $d = hypot($x - $cx, $y - $cy) / $r;
        return $d >= 1.0 ? 0.0 : (1.0 - $d) ** 2.0;
    }

    private static function roundedDistance(float $px, float $py, float $x, float $y, float $w, float $h, float $r): float
    {
        $qx = abs($px - ($x + $w / 2)) - ($w / 2 - $r);
        $qy = abs($py - ($y + $h / 2)) - ($h / 2 - $r);
        return hypot(max($qx, 0.0), max($qy, 0.0)) + min(max($qx, $qy), 0.0) - $r;
    }

    public static function finish(CardCanvas $c): string
    {
        $png = $c->toPng();
        $c->destroy();
        return $png;
    }

    // Maps prices to plot y and returns [curve points, level→y mapper].
    public static function project(array $path, array $levels): array
    {
        [$ax, $ay, $aw, $ah] = self::PLOT;
        $values = array_merge($path, array_filter($levels, static fn($v) => $v !== null));
        $lo = min($values);
        $hi = max($values);
        $pad = ($hi - $lo) * 0.08 ?: max(abs($hi) * 0.001, 1e-9);
        $lo -= $pad;
        $hi += $pad;
        $toY = static fn(float $price): float => $ay + $ah - ($price - $lo) / ($hi - $lo) * $ah;
        $n = count($path);
        $points = [];
        foreach (array_values($path) as $i => $price) {
            $points[] = [$ax + ($n > 1 ? $i / ($n - 1) : 1.0) * $aw, $toY((float) $price)];
        }
        return [$points, $toY];
    }

    public static function chartLine(CardCanvas $c, array $points, array $accent, array $soft): void
    {
        [$ax, $ay, $aw, $ah] = self::PLOT;
        $c->glow([$ax - 40.0, $ay - 40.0, $aw + 80.0, $ah + 80.0], static function ($mask, int $white, callable $map, float $k) use ($points): void {
            imagesetthickness($mask, max(2, (int) round(7 * $k)));
            for ($i = 1; $i < count($points); $i++) {
                [$x1, $y1] = $map($points[$i - 1][0], $points[$i - 1][1]);
                [$x2, $y2] = $map($points[$i][0], $points[$i][1]);
                imageline($mask, (int) round($x1), (int) round($y1), (int) round($x2), (int) round($y2), $white);
            }
        }, $accent, 0.9, 5, 6);
        $c->polyline($points, 3.4, $soft, 1.0);
    }

    public static function dashed(CardCanvas $c, float $x1, float $x2, float $y, array $rgb, float $opacity, bool $solid = false): void
    {
        if ($solid) {
            $c->roundRect($x1, $y - 1.0, $x2 - $x1, 2.0, 1.0, $rgb, $opacity);
            return;
        }
        for ($x = $x1; $x < $x2; $x += 16.0) {
            $c->roundRect($x, $y - 1.0, min(9.0, $x2 - $x), 2.0, 1.0, $rgb, $opacity);
        }
    }

    // Level tags on the right edge of the plot; pushed apart so they never overlap.
    public static function tags(CardCanvas $c, array $tags): void
    {
        usort($tags, static fn($a, $b) => $a['y'] <=> $b['y']);
        [, $ay, , $ah] = self::PLOT;
        $h = 38.0;
        $gap = 6.0;
        $ys = array_map(static fn($t) => $t['y'], $tags);
        for ($pass = 0; $pass < 4; $pass++) {
            for ($i = 1; $i < count($ys); $i++) {
                if ($ys[$i] - $ys[$i - 1] < $h + $gap) {
                    $ys[$i] = $ys[$i - 1] + $h + $gap;
                }
            }
            $overflow = end($ys) - ($ay + $ah);
            if ($overflow > 0) {
                foreach ($ys as $i => $y) {
                    $ys[$i] = $y - $overflow;
                }
                for ($i = count($ys) - 2; $i >= 0; $i--) {
                    if ($ys[$i + 1] - $ys[$i] < $h + $gap) {
                        $ys[$i] = $ys[$i + 1] - $h - $gap;
                    }
                }
            }
        }
        [$px, , $pw] = self::PANEL;
        $right = $px + $pw - 22.0;
        foreach ($tags as $i => $t) {
            $cy = max($ay - 10.0, $ys[$i]);
            $x = self::TAG_X;
            $w = $right - $x;
            if (abs($cy - $t['y']) > 2.0) {
                $c->polyline([[self::PLOT[0] + self::PLOT[2], $t['y']], [$x - 4.0, $cy]], 1.4, $t['rgb'], 0.45);
            }
            $fill = $t['solid'] ? $t['rgb'] : NeoPalette::PANEL;
            $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, $fill, $t['solid'] ? 1.0 : 0.92);
            if (!$t['solid']) {
                $c->ring($x + 0.75, $cy - $h / 2 + 0.75, $w - 1.5, $h - 1.5, $h / 2 - 0.75, 1.5, $t['rgb'], static fn(): float => $t['dim'] ? 0.30 : 0.75);
            }
            $ink = $t['solid'] ? NeoPalette::INK : ($t['dim'] ? NeoPalette::FAINT : $t['rgb']);
            $labelW = $c->write($t['label'], $x + 16.0, $cy - $c->capHeight(15.0, 'bold', $t['label']) / 2, 15.0, $ink, 'bold', 'left', 0.1);
            $size = $c->fit($t['price'], 17.0, $w - 32.0 - $labelW - 12.0, 'bold', 0.0, 11.0);
            $c->write($t['price'], $right - 16.0, $cy - $c->capHeight($size, 'bold', $t['price']) / 2, $size, $t['solid'] ? NeoPalette::INK : ($t['dim'] ? NeoPalette::FAINT : NeoPalette::WHITE), 'bold', 'right', 0.0);
        }
    }

    public static function dot(CardCanvas $c, float $x, float $y, array $rgb, float $r = 9.0): void
    {
        $c->roundRect($x - $r * 3, $y - $r * 3, $r * 6, $r * 6, $r * 3, $rgb, 0.10);
        $c->roundRect($x - $r * 1.9, $y - $r * 1.9, $r * 3.8, $r * 3.8, $r * 1.9, $rgb, 0.22);
        $c->roundRect($x - $r, $y - $r, $r * 2, $r * 2, $r, $rgb, 1.0);
        $c->roundRect($x - $r * 0.42, $y - $r * 0.42, $r * 0.84, $r * 0.84, $r * 0.42, NeoPalette::WHITE, 0.9);
    }

    // Round coin badge with the ticker initials and a glossy top.
    public static function badge(CardCanvas $c, string $base, float $cx, float $cy, float $r, array $accent, array $second): void
    {
        $c->roundRect($cx - $r - 14.0, $cy - $r - 14.0, ($r + 14.0) * 2, ($r + 14.0) * 2, $r + 14.0, $accent, 0.14);
        for ($k = 0; $k < 8; $k++) {
            $t = $k / 7;
            $rr = $r * (1.0 - 0.1 * $t);
            $c->roundRect($cx - $rr, $cy - $rr - $r * 0.06 * $t, $rr * 2, $rr * 2, $rr, CardCanvas::mix($second, $accent, 0.9 - 0.9 * $t), 1.0);
        }
        $c->roundRect($cx - $r * 0.72, $cy - $r * 0.9, $r * 1.44, $r * 0.9, $r * 0.45, NeoPalette::WHITE, 0.16);
        $c->ring($cx - $r + 0.75, $cy - $r + 0.75, $r * 2 - 1.5, $r * 2 - 1.5, $r - 0.75, 1.5, NeoPalette::WHITE, static fn(float $x, float $y): float => $y < $cy ? 0.45 : 0.12);
        $letters = substr((string) preg_replace('/^\d+/', '', $base) ?: $base, 0, 4);
        $size = $c->fit($letters, $r * 0.66, $r * 1.28, 'heavy', -0.02, 12.0);
        $c->write($letters, $cx, $cy - $c->capHeight($size, 'heavy', $letters) / 2, $size, NeoPalette::INK, 'heavy', 'center', -0.02, 0.92);
    }

    public static function header(CardCanvas $c, string $pair, array $accent, array $second): void
    {
        [$base, $quote] = NeoFormat::splitPair($pair);
        self::badge($c, $base, self::LEFT + 46.0, 146.0, 46.0, $accent, $second);
        $x = self::LEFT + 118.0;
        $size = $c->fit($base, 72.0, 430.0, 'heavy', -0.02, 34.0);
        $c->write($base, $x, 146.0 - $c->capHeight($size, 'heavy', $base) / 2 - 12.0, $size, NeoPalette::WHITE, 'heavy', 'left', -0.02);
        if ($quote !== '') {
            $c->write(ltrim($quote, '/') . ' PERPETUAL', $x + 2.0, 146.0 + $c->capHeight($size, 'heavy', $base) / 2 + 4.0, 17.0, NeoPalette::DIM, 'semi', 'left', 0.18, 0.9);
        }
    }

    // Pills: [text, style, arrow] where style is 'solid' (filled with accent) or 'ghost'.
    public static function chips(CardCanvas $c, float $cy, array $chips, array $accent): void
    {
        $x = self::LEFT;
        foreach ($chips as [$text, $style, $arrow]) {
            $size = 20.0;
            $padL = $arrow !== null ? 48.0 : 22.0;
            $w = $padL + $c->measure($text, $size, 'bold', 0.1) + 22.0;
            $h = 48.0;
            if ($style === 'solid') {
                $c->glow([$x - 30.0, $cy - $h / 2 - 30.0, $w + 60.0, $h + 60.0], static function ($mask, int $white, callable $map) use ($x, $cy, $w, $h): void {
                    [$x1, $y1] = $map($x, $cy - $h / 2);
                    [$x2, $y2] = $map($x + $w, $cy + $h / 2);
                    imagefilledrectangle($mask, (int) round($x1), (int) round($y1), (int) round($x2), (int) round($y2), $white);
                }, $accent, 0.8, 5, 6);
                $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, $accent, 1.0);
                $c->roundRect($x + 6.0, $cy - $h / 2 + 3.0, $w - 12.0, $h * 0.42, $h * 0.21, NeoPalette::WHITE, 0.16);
                $ink = NeoPalette::INK;
            } else {
                $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, NeoPalette::WHITE, 0.07);
                $c->ring($x + 0.75, $cy - $h / 2 + 0.75, $w - 1.5, $h - 1.5, $h / 2 - 0.75, 1.5, NeoPalette::WHITE, static fn(): float => 0.24);
                $ink = NeoPalette::WHITE;
            }
            if ($arrow !== null) {
                self::arrow($c, $x + 27.0, $cy, 8.5, $arrow === 'up', $ink, 1.0);
            }
            $c->write($text, $x + $padL, $cy - $c->capHeight($size, 'bold', $text) / 2, $size, $ink, 'bold', 'left', 0.1);
            $x += $w + 12.0;
        }
    }

    public static function arrow(CardCanvas $c, float $cx, float $cy, float $s, bool $up, array $rgb, float $opacity): void
    {
        $c->filledPolygon($up
            ? [[$cx, $cy - $s], [$cx + $s * 1.05, $cy + $s * 0.7], [$cx - $s * 1.05, $cy + $s * 0.7]]
            : [[$cx, $cy + $s], [$cx + $s * 1.05, $cy - $s * 0.7], [$cx - $s * 1.05, $cy - $s * 0.7]], $rgb, $opacity);
    }

    public static function hero(CardCanvas $c, string $label, array $labelRgb, string $value, array $valueRgb, array $glowRgb, float $maxSize): void
    {
        $c->write($label, self::LEFT, 346.0, 18.0, $labelRgb, 'semi', 'left', 0.22, 0.95);
        $size = $c->fit($value, $maxSize, 500.0, 'heavy', -0.03, 44.0);
        $capTop = 384.0 + ($maxSize - $size) * 0.35;
        $c->glowText($value, self::LEFT - 4.0, $capTop, $size, $glowRgb, 'heavy', 'left', -0.03, 0.75, 46.0);
        $c->write($value, self::LEFT - 4.0, $capTop, $size, $valueRgb, 'heavy', 'left', -0.03);
    }

    // Bottom row of glass tiles: [label, value, valueRgb, sub, subRgb].
    public static function tiles(CardCanvas $c, array $tiles, array $accent): void
    {
        $y = 672.0;
        $h = 132.0;
        $gap = 16.0;
        $width = self::W - self::LEFT * 2;
        $n = max(1, count($tiles));
        $w = ($width - $gap * ($n - 1)) / $n;
        foreach (array_values($tiles) as $i => $tile) {
            [$label, $value, $rgb] = $tile;
            $sub = $tile[3] ?? '';
            $subRgb = $tile[4] ?? NeoPalette::DIM;
            $x = self::LEFT + $i * ($w + $gap);
            $c->roundRect($x, $y, $w, $h, 26.0, NeoPalette::PANEL, 0.72);
            $c->roundRect($x + 1.0, $y + 1.0, $w - 2.0, $h * 0.45, 25.0, NeoPalette::WHITE, 0.025);
            $c->ring($x + 0.75, $y + 0.75, $w - 1.5, $h - 1.5, 25.25, 1.5, $accent,
                static fn(float $px, float $py): float => 0.04 + 0.30 * max(0.0, 1.0 - hypot($px - $x, $py - $y) / ($w * 0.9)));
            $c->write($label, $x + 26.0, $y + 26.0, 15.0, NeoPalette::DIM, 'semi', 'left', 0.2, 0.95);
            $size = $c->fit($value, 36.0, $w - 52.0, 'bold', -0.01, 18.0);
            $c->write($value, $x + 26.0, $y + 56.0, $size, $rgb, 'bold', 'left', -0.01);
            if ($sub !== '') {
                $c->write($sub, $x + 26.0, $y + 100.0, 17.0, $subRgb, 'semi', 'left', 0.04);
            }
        }
    }

    public static function footer(CardCanvas $c, string $time, array $accent): void
    {
        $y = self::H - 58.0;
        $time = trim($time);
        if ($time !== '') {
            $c->write($time, self::LEFT, $y, 15.0, NeoPalette::WHITE, 'semi', 'left', 0.16, 0.42);
        }
        $right = self::W - self::LEFT;
        $logo = CardConfig::logoPath();
        if ($logo !== null && $c->image($logo, $right - 200.0, $y - 12.0, 200.0, 32.0)) {
            return;
        }
        $brand = CardConfig::brand();
        $brand = PersianShaper::containsPersian($brand) ? $brand : strtoupper($brand);
        $tracking = PersianShaper::containsPersian($brand) ? 0.0 : 0.2;
        $w = $c->write($brand, $right, $y - 2.0, 17.0, NeoPalette::WHITE, 'bold', 'right', $tracking, 0.78);
        $c->roundRect($right - $w - 22.0, $y + 3.0, 9.0, 9.0, 4.5, $accent, 1.0);
    }

    public static function check(CardCanvas $c, float $cx, float $cy, float $s, array $rgb): void
    {
        $c->polyline([[$cx - $s, $cy], [$cx - $s * 0.3, $cy + $s * 0.7], [$cx + $s, $cy - $s * 0.75]], $s * 0.36, $rgb, 1.0);
    }
}

final class NeoSignalCard
{
    public static function render(array $d): string
    {
        $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
        $tone = $isLong ? 'long' : 'short';
        [$accent, $soft, $second] = NeoPalette::scheme($tone);
        $pair = CardLayout::pair((string) ($d['symbol'] ?? ''));

        $entry = NeoFormat::number($d['entry'] ?? null) ?? 0.0;
        $sl = NeoFormat::number($d['sl'] ?? null);
        $tps = [];
        foreach ([1, 2, 3, 4] as $k) {
            $v = NeoFormat::number($d['tp' . $k] ?? null);
            if ($v !== null) {
                $tps[$k] = $v;
            }
        }
        $path = self::path($d, $pair, $entry, $sl);
        [$points, $toY] = NeoLayout::project($path, array_merge([$sl, $entry], array_values($tps)));

        $c = NeoLayout::begin($tone, $points);
        NeoLayout::header($c, $pair, $accent, $second);

        $chips = [[$isLong ? 'LONG' : 'SHORT', 'solid', $isLong ? 'up' : 'down']];
        foreach (['leverage', 'timeframe'] as $key) {
            $v = strtoupper(trim((string) ($d[$key] ?? '')));
            if ($v !== '') {
                $chips[] = [$v, 'ghost', null];
            }
        }
        NeoLayout::chips($c, 262.0, $chips, $accent);
        NeoLayout::hero($c, 'ENTRY PRICE', NeoPalette::DIM, NeoFormat::price($d['entry'] ?? null), NeoPalette::WHITE, $accent, 96.0);
        $c->write('MARKET ORDER · ' . strtoupper((string) ($d['confidence'] ?? '')) . ' CONFIDENCE', NeoLayout::LEFT, 506.0, 16.0, $soft, 'semi', 'left', 0.16, 0.9);

        // Levels across the plot, then the price line and the entry marker on top.
        [$ax, , $aw] = NeoLayout::PLOT;
        $tags = [];
        foreach ($tps as $k => $price) {
            $y = $toY($price);
            NeoLayout::dashed($c, $ax, $ax + $aw, $y, NeoPalette::MINT, 0.42);
            $tags[] = ['y' => $y, 'label' => 'TP' . $k, 'price' => NeoFormat::price((string) ($d['tp' . $k] ?? '')), 'rgb' => NeoPalette::MINT, 'solid' => false, 'dim' => false];
        }
        if ($sl !== null) {
            $y = $toY($sl);
            NeoLayout::dashed($c, $ax, $ax + $aw, $y, NeoPalette::ROSE, 0.55);
            $tags[] = ['y' => $y, 'label' => 'SL', 'price' => NeoFormat::price($d['sl'] ?? null), 'rgb' => NeoPalette::ROSE, 'solid' => false, 'dim' => false];
        }
        $entryY = $toY($entry);
        NeoLayout::dashed($c, $ax, $ax + $aw, $entryY, NeoPalette::AMBER, 0.5);
        $tags[] = ['y' => $entryY, 'label' => 'ENTRY', 'price' => NeoFormat::price($d['entry'] ?? null), 'rgb' => NeoPalette::AMBER, 'solid' => true, 'dim' => false];
        NeoLayout::chartLine($c, $points, $accent, $soft);
        $last = end($points);
        NeoLayout::dot($c, $last[0], $last[1], NeoPalette::AMBER);
        NeoLayout::tags($c, $tags);

        $tiles = [
            ['STOP LOSS', NeoFormat::price($d['sl'] ?? null), NeoPalette::WHITE, (string) ($d['sl_pct'] ?? ''), NeoPalette::ROSE_SOFT],
            ['TARGET 1', NeoFormat::price($d['tp1'] ?? null), NeoPalette::WHITE, (string) ($d['tp1_pct'] ?? ''), NeoPalette::MINT_SOFT],
            ['FINAL TARGET', NeoFormat::price($d['tp4'] ?? ($d['tp3'] ?? null)), NeoPalette::WHITE, (string) ($d['tp4_pct'] ?? ''), NeoPalette::MINT_SOFT],
        ];
        $consensus = trim((string) ($d['consensus'] ?? ''));
        $rr = trim((string) ($d['rr'] ?? ''));
        $tiles[] = $consensus !== ''
            ? ['INDICATORS', $consensus, $soft, $rr !== '' ? 'REWARD ' . $rr : '', NeoPalette::DIM]
            : ['REWARD : RISK', $rr !== '' ? $rr : 'N/A', $soft, '', NeoPalette::DIM];
        NeoLayout::tiles($c, $tiles, $accent);
        NeoLayout::footer($c, (string) ($d['time'] ?? ''), $accent);

        return NeoLayout::finish($c);
    }

    // Real recent closes when the signal carries them; otherwise a smooth decorative path
    // that drifts into the entry from the stop side.
    private static function path(array $d, string $pair, float $entry, ?float $sl): array
    {
        $spark = array_values(array_filter((array) ($d['spark'] ?? []), 'is_numeric'));
        if (count($spark) >= 12) {
            $spark[count($spark) - 1] = $entry;
            return array_map('floatval', $spark);
        }
        $noise = new NeoNoise($pair . '|' . $entry);
        $risk = $sl !== null ? abs($entry - $sl) : max($entry * 0.01, 1e-9);
        $dir = $sl !== null && $sl < $entry ? 1.0 : -1.0;
        $out = [];
        $level = $entry - $dir * $risk * 0.55;
        for ($i = 0; $i < 60; $i++) {
            $t = $i / 59;
            $level += $noise->next() * $risk * 0.12;
            $target = $entry - $dir * $risk * 0.55 * (1 - $t) + sin($t * 9.0) * $risk * 0.08;
            $level += ($target - $level) * 0.25;
            $out[] = $level;
        }
        $out[59] = $entry;
        return $out;
    }
}

final class NeoResultCard
{
    private const STATUS = [
        'tp1' => 'TARGET 1 HIT',
        'tp2' => 'TARGET 2 HIT',
        'tp3' => 'TARGET 3 HIT',
        'tp4' => 'ALL TARGETS HIT',
        'trail' => 'PROFIT LOCKED',
        'be' => 'RISK FREE EXIT',
        'sl' => 'STOP LOSS HIT',
        'timeout' => 'TIME LIMIT',
    ];

    public static function render(array $d): string
    {
        $kind = strtolower((string) ($d['kind'] ?? 'tp1'));
        $headline = trim((string) ($d['headline'] ?? '')) ?: '0.00%';
        $pnl = CardFormat::number($headline) ?? 0.0;
        $tone = $pnl > 0.004 ? 'profit' : ($pnl < -0.004 || $kind === 'sl' ? 'loss' : 'flat');
        [$accent, $soft, $second] = NeoPalette::scheme($tone);
        $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
        $status = self::STATUS[$kind] ?? 'TRADE CLOSED';
        if ($tone === 'loss' && $kind !== 'sl') {
            $status = 'TRADE CLOSED';
        }
        $running = in_array($kind, ['tp1', 'tp2', 'tp3'], true);
        $hits = max(0, min(4, (int) ($d['hits'] ?? 0)));
        $pair = CardLayout::pair((string) ($d['symbol'] ?? ''));

        $entry = NeoFormat::number($d['entry'] ?? null) ?? 0.0;
        $exit = NeoFormat::number($d['exit'] ?? null) ?? $entry;
        $sl = NeoFormat::number($d['sl'] ?? null);
        $tps = [];
        foreach ([1, 2, 3, 4] as $k) {
            $v = NeoFormat::number($d['tp' . $k] ?? null);
            if ($v !== null) {
                $tps[$k] = $v;
            }
        }
        $peak = $hits > 0 && isset($tps[$hits]) ? $tps[$hits] : null;
        $path = self::path($pair, $entry, $exit, $peak, $sl);
        [$points, $toY] = NeoLayout::project($path, array_merge([$sl, $entry, $exit], array_values($tps)));

        $c = NeoLayout::begin($tone, $points);
        NeoLayout::header($c, $pair, $accent, $second);
        $chips = [[$isLong ? 'LONG' : 'SHORT', 'ghost', $isLong ? 'up' : 'down']];
        foreach (['leverage', 'timeframe'] as $key) {
            $v = strtoupper(trim((string) ($d[$key] ?? '')));
            if ($v !== '') {
                $chips[] = [$v, 'ghost', null];
            }
        }
        NeoLayout::chips($c, 262.0, $chips, $accent);
        NeoLayout::hero($c, $status, $soft, CardFormat::signed($headline), $soft, $accent, 124.0);
        $move = trim((string) ($d['move'] ?? ''));
        $c->write('ROI WITH ' . strtoupper((string) ($d['leverage'] ?? '')) . ($move !== '' ? '  ·  PRICE MOVE ' . CardFormat::signed($move) : ''),
            NeoLayout::LEFT, 536.0, 16.0, NeoPalette::DIM, 'semi', 'left', 0.14, 0.9);

        [$ax, , $aw] = NeoLayout::PLOT;
        $tags = [];
        foreach ($tps as $k => $price) {
            $y = $toY($price);
            $reached = $k <= $hits;
            NeoLayout::dashed($c, $ax, $ax + $aw, $y, NeoPalette::MINT, $reached ? 0.55 : 0.18, $reached);
            $tags[] = ['y' => $y, 'label' => 'TP' . $k, 'price' => NeoFormat::price((string) ($d['tp' . $k] ?? '')), 'rgb' => NeoPalette::MINT, 'solid' => $reached, 'dim' => !$reached];
        }
        if ($sl !== null) {
            $y = $toY($sl);
            NeoLayout::dashed($c, $ax, $ax + $aw, $y, NeoPalette::ROSE, $kind === 'sl' ? 0.7 : 0.3, $kind === 'sl');
            $tags[] = ['y' => $y, 'label' => 'SL', 'price' => NeoFormat::price($d['sl'] ?? null), 'rgb' => NeoPalette::ROSE, 'solid' => $kind === 'sl', 'dim' => $kind !== 'sl'];
        }
        $entryY = $toY($entry);
        NeoLayout::dashed($c, $ax, $ax + $aw, $entryY, NeoPalette::AMBER, 0.45);
        $tags[] = ['y' => $entryY, 'label' => 'ENTRY', 'price' => NeoFormat::price($d['entry'] ?? null), 'rgb' => NeoPalette::AMBER, 'solid' => false, 'dim' => false];

        NeoLayout::chartLine($c, $points, $accent, $soft);
        $entryPoint = $points[(int) floor(count($points) * 0.35)];
        NeoLayout::dot($c, $entryPoint[0], $entryY, NeoPalette::AMBER, 7.5);
        $last = end($points);
        NeoLayout::dot($c, $last[0], $last[1], $accent, 9.0);
        NeoLayout::tags($c, $tags);

        $tiles = [
            ['ENTRY', NeoFormat::price($d['entry'] ?? null), NeoPalette::WHITE],
            [$running ? 'HIT PRICE' : 'EXIT', NeoFormat::price($d['exit'] ?? null), NeoPalette::WHITE],
            ['TARGETS', $hits . ' / ' . max(1, count($tps)), $hits > 0 ? NeoPalette::MINT_SOFT : NeoPalette::WHITE],
            ['DURATION', CardFormat::duration(CardFormat::orNA($d['duration'] ?? null)), NeoPalette::WHITE],
        ];
        NeoLayout::tiles($c, $tiles, $accent);
        self::pips($c, $hits, count($tps));
        NeoLayout::footer($c, (string) ($d['time'] ?? ''), $accent);

        return NeoLayout::finish($c);
    }

    // Progress pips under the TARGETS tile value.
    private static function pips(CardCanvas $c, int $hits, int $total): void
    {
        $gap = 16.0;
        $w = (NeoLayout::W - NeoLayout::LEFT * 2 - $gap * 3) / 4;
        $x = NeoLayout::LEFT + 2 * ($w + $gap) + 26.0;
        $y = 672.0 + 104.0;
        for ($k = 1; $k <= max(1, $total); $k++) {
            $on = $k <= $hits;
            $c->roundRect($x, $y, 34.0, 8.0, 4.0, $on ? NeoPalette::MINT : NeoPalette::WHITE, $on ? 1.0 : 0.14);
            $x += 42.0;
        }
    }

    // Stylised trade path: drift around the entry, run to the furthest target reached, then
    // settle at the exit. It illustrates the result; the tags carry the real prices.
    private static function path(string $pair, float $entry, float $exit, ?float $peak, ?float $sl): array
    {
        $noise = new NeoNoise($pair . '|' . $entry . '|' . $exit);
        $scale = abs(($peak ?? $exit) - $entry) ?: ($sl !== null ? abs($entry - $sl) : max($entry * 0.01, 1e-9));
        $out = [];
        $level = $entry;
        for ($i = 0; $i < 24; $i++) {
            $level += ($entry - $level) * 0.3 + $noise->next() * $scale * 0.07;
            $out[] = $level;
        }
        $out[] = $entry;
        $waypoints = $peak !== null && abs($peak - $exit) > $scale * 0.05 ? [$peak, $exit] : [$exit];
        $segment = (int) floor(42 / count($waypoints));
        $from = $entry;
        foreach ($waypoints as $to) {
            for ($i = 1; $i <= $segment; $i++) {
                $t = $i / $segment;
                $ease = $t * $t * (3 - 2 * $t);
                $wiggle = sin($t * M_PI) * $noise->next() * $scale * 0.08;
                $out[] = $from + ($to - $from) * $ease + $wiggle;
            }
            $out[count($out) - 1] = $to;
            $from = $to;
        }
        return $out;
    }
}

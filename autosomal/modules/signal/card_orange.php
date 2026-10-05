<?php

declare(strict_types=1);

// "Orange" card design (default): black background, white typography, orange brand accents.
// Meaning colours are kept: long / targets green, short / stop loss red.
// 1600 x 900 (16:9) with a 72px margin on every side; Telegram shows it at 1280 x 720.

final class OrangePalette
{
    public const BG = [0, 0, 0];
    public const PANEL = [11, 11, 11];
    public const TILE = [13, 13, 13];
    public const LINE = [42, 42, 42];
    public const WHITE = [255, 255, 255];
    public const DIM = [150, 150, 150];
    public const FAINT = [88, 88, 88];
    public const ORANGE = [255, 138, 0];
    public const ORANGE_SOFT = [255, 186, 102];
    public const GREEN = [22, 199, 132];
    public const GREEN_SOFT = [120, 232, 186];
    public const RED = [240, 70, 79];
    public const RED_SOFT = [255, 150, 156];
    public const INK = [0, 0, 0];

    /** [main, soft] colour for a tone. */
    public static function tone(string $tone): array
    {
        return match ($tone) {
            'long', 'profit' => [self::GREEN, self::GREEN_SOFT],
            'short', 'loss' => [self::RED, self::RED_SOFT],
            default => [self::WHITE, self::DIM],
        };
    }
}

final class OrangeLayout
{
    public const W = 1600;
    public const H = 900;
    public const M = 72.0;
    private const STEP = 4;

    // Chart panel [x, y, w, h, radius] and the plot area inside it [x, y, w, h].
    public const PANEL = [600.0, 72.0, 928.0, 568.0, 28.0];
    public const PLOT = [632.0, 116.0, 660.0, 492.0];
    public const TAG_X = 1316.0;
    public const LEFT_W = 488.0;
    public const TILES_Y = 668.0;
    public const TILES_H = 136.0;

    /** Black background, subtle orange light, the chart panel and the orange glow under the price line. */
    public static function begin(array $curve): CardCanvas
    {
        [$px, $py, $pw, $ph, $pr] = self::PANEL;
        [, , , $ah] = self::PLOT;
        $plotBottom = self::PLOT[1] + $ah;
        $lw = intdiv(self::W, self::STEP) + 1;
        $lh = intdiv(self::H, self::STEP) + 1;
        $curveAt = self::sampler($curve);

        $field = [[], [], []];
        for ($j = 0; $j < $lh; $j++) {
            $y = (float) ($j * self::STEP);
            for ($i = 0; $i < $lw; $i++) {
                $x = (float) ($i * self::STEP);
                $rgb = OrangePalette::BG;
                $rgb = CardCanvas::mix(OrangePalette::ORANGE, $rgb, 0.16 * self::falloff($x, $y, 120.0, 90.0, 520.0));
                $rgb = CardCanvas::mix(OrangePalette::ORANGE, $rgb, 0.10 * self::falloff($x, $y, 1560.0, 900.0, 640.0));
                if (self::roundedDistance($x, $y, $px, $py, $pw, $ph, $pr) < 0) {
                    $rgb = CardCanvas::mix(OrangePalette::PANEL, $rgb, 0.9);
                    $cy = $curveAt($x);
                    if ($cy !== null && $y >= $cy && $y <= $plotBottom) {
                        $t = ($y - $cy) / max(1.0, $plotBottom - $cy);
                        $rgb = CardCanvas::mix(OrangePalette::ORANGE, $rgb, 0.24 * (1.0 - $t) ** 1.8);
                    }
                }
                $field[0][] = $rgb[0];
                $field[1][] = $rgb[1];
                $field[2][] = $rgb[2];
            }
        }
        $c = new CardCanvas(self::W, self::H);
        $c->blitField($field, $lw, $lh, self::STEP);

        $c->ring($px + 0.75, $py + 0.75, $pw - 1.5, $ph - 1.5, $pr - 0.75, 1.5, OrangePalette::WHITE,
            static fn(float $x, float $y): float => 0.10);
        // Orange accent on the panel's top edge
        $c->roundRect($px + 36.0, $py - 1.5, 120.0, 3.0, 1.5, OrangePalette::ORANGE, 1.0);
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

    /** Maps prices to plot y; returns [curve points, price→y]. */
    public static function project(array $path, array $levels): array
    {
        [$ax, $ay, $aw, $ah] = self::PLOT;
        $values = array_merge($path, array_values(array_filter($levels, static fn($v) => $v !== null)));
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

    public static function priceLine(CardCanvas $c, array $points): void
    {
        [$ax, $ay, $aw, $ah] = self::PLOT;
        $c->glow([$ax - 30.0, $ay - 30.0, $aw + 60.0, $ah + 60.0], static function ($mask, int $white, callable $map, float $k) use ($points): void {
            imagesetthickness($mask, max(2, (int) round(6 * $k)));
            for ($i = 1; $i < count($points); $i++) {
                [$x1, $y1] = $map($points[$i - 1][0], $points[$i - 1][1]);
                [$x2, $y2] = $map($points[$i][0], $points[$i][1]);
                imageline($mask, (int) round($x1), (int) round($y1), (int) round($x2), (int) round($y2), $white);
            }
        }, OrangePalette::ORANGE, 0.55, 5, 6);
        $c->polyline($points, 3.0, OrangePalette::WHITE, 1.0);
    }

    public static function level(CardCanvas $c, float $y, array $rgb, float $opacity, bool $solid = false): void
    {
        [$ax, , $aw] = self::PLOT;
        if ($solid) {
            $c->roundRect($ax, $y - 1.0, $aw, 2.0, 1.0, $rgb, $opacity);
            return;
        }
        for ($x = $ax; $x < $ax + $aw; $x += 14.0) {
            $c->roundRect($x, $y - 1.0, min(8.0, $ax + $aw - $x), 2.0, 1.0, $rgb, $opacity);
        }
    }

    /** Level tags on the right of the plot, pushed apart so they never overlap. */
    public static function tags(CardCanvas $c, array $tags): void
    {
        usort($tags, static fn($a, $b) => $a['y'] <=> $b['y']);
        [, $ay, , $ah] = self::PLOT;
        $h = 36.0;
        $gap = 6.0;
        $ys = array_map(static fn($t) => $t['y'], $tags);
        for ($pass = 0; $pass < 4; $pass++) {
            for ($i = 1; $i < count($ys); $i++) {
                if ($ys[$i] - $ys[$i - 1] < $h + $gap) {
                    $ys[$i] = $ys[$i - 1] + $h + $gap;
                }
            }
            $overflow = $ys ? end($ys) - ($ay + $ah) : 0;
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
        $right = $px + $pw - 20.0;
        $x = self::TAG_X;
        $w = $right - $x;
        foreach ($tags as $i => $t) {
            $cy = max($ay - 8.0, $ys[$i]);
            if (abs($cy - $t['y']) > 2.0) {
                $c->polyline([[self::PLOT[0] + self::PLOT[2], $t['y']], [$x - 4.0, $cy]], 1.4, $t['rgb'], 0.45);
            }
            $fill = $t['solid'] ? $t['rgb'] : OrangePalette::PANEL;
            $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, $fill, 1.0);
            if (!$t['solid']) {
                $c->ring($x + 0.75, $cy - $h / 2 + 0.75, $w - 1.5, $h - 1.5, $h / 2 - 0.75, 1.5, $t['rgb'], static fn(): float => $t['dim'] ? 0.30 : 0.85);
            }
            $ink = $t['solid'] ? OrangePalette::INK : ($t['dim'] ? OrangePalette::FAINT : $t['rgb']);
            $labelW = $c->write($t['label'], $x + 14.0, $cy - $c->capHeight(14.0, 'bold', $t['label']) / 2, 14.0, $ink, 'bold', 'left', 0.08);
            $size = $c->fit($t['price'], 16.0, $w - 28.0 - $labelW - 10.0, 'bold', 0.0, 10.0);
            $priceInk = $t['solid'] ? OrangePalette::INK : ($t['dim'] ? OrangePalette::FAINT : OrangePalette::WHITE);
            $c->write($t['price'], $right - 14.0, $cy - $c->capHeight($size, 'bold', $t['price']) / 2, $size, $priceInk, 'bold', 'right', 0.0);
        }
    }

    public static function dot(CardCanvas $c, float $x, float $y, array $rgb, float $r = 8.0): void
    {
        $c->roundRect($x - $r * 2.4, $y - $r * 2.4, $r * 4.8, $r * 4.8, $r * 2.4, $rgb, 0.14);
        $c->roundRect($x - $r, $y - $r, $r * 2, $r * 2, $r, $rgb, 1.0);
        $c->roundRect($x - $r * 0.4, $y - $r * 0.4, $r * 0.8, $r * 0.8, $r * 0.4, OrangePalette::INK, 1.0);
    }

    /** Coin badge: black disc, orange ring, white ticker. */
    public static function badge(CardCanvas $c, string $base, float $cx, float $cy, float $r): void
    {
        $c->roundRect($cx - $r - 10.0, $cy - $r - 10.0, ($r + 10.0) * 2, ($r + 10.0) * 2, $r + 10.0, OrangePalette::ORANGE, 0.10);
        $c->roundRect($cx - $r, $cy - $r, $r * 2, $r * 2, $r, OrangePalette::ORANGE, 1.0);
        $c->roundRect($cx - $r + 4.0, $cy - $r + 4.0, $r * 2 - 8.0, $r * 2 - 8.0, $r - 4.0, OrangePalette::INK, 1.0);
        $letters = substr((string) preg_replace('/^\d+/', '', $base) ?: $base, 0, 4);
        $size = $c->fit($letters, $r * 0.62, $r * 1.3, 'heavy', -0.02, 12.0);
        $c->write($letters, $cx, $cy - $c->capHeight($size, 'heavy', $letters) / 2, $size, OrangePalette::WHITE, 'heavy', 'center', -0.02);
    }

    public static function header(CardCanvas $c, string $pair): void
    {
        [$base, $quote] = NeoFormat::splitPair($pair);
        $cy = self::M + 60.0;
        self::badge($c, $base, self::M + 46.0, $cy, 46.0);
        $x = self::M + 116.0;
        $size = $c->fit($base, 64.0, self::LEFT_W - 116.0, 'heavy', -0.02, 30.0);
        $c->write($base, $x, $cy - $c->capHeight($size, 'heavy', $base) / 2 - 12.0, $size, OrangePalette::WHITE, 'heavy', 'left', -0.02);
        $sub = ($quote !== '' ? ltrim($quote, '/') . ' ' : '') . 'PERPETUAL';
        $c->write($sub, $x + 2.0, $cy + $c->capHeight($size, 'heavy', $base) / 2 + 2.0, 15.0, OrangePalette::DIM, 'semi', 'left', 0.2);
    }

    /** Pills: [text, kind, arrow, rgb] kind = solid | outline. */
    public static function chips(CardCanvas $c, float $cy, array $chips): void
    {
        $x = self::M;
        $h = 46.0;
        $size = 19.0;
        foreach ($chips as [$text, $kind, $arrow, $rgb]) {
            $padL = $arrow !== null ? 46.0 : 22.0;
            $w = $padL + $c->measure($text, $size, 'bold', 0.08) + 22.0;
            if ($x + $w > self::M + self::LEFT_W) {
                break;
            }
            if ($kind === 'solid') {
                $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, $rgb, 1.0);
                $ink = OrangePalette::INK;
            } else {
                $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, $rgb, 0.08);
                $c->ring($x + 0.75, $cy - $h / 2 + 0.75, $w - 1.5, $h - 1.5, $h / 2 - 0.75, 1.5, $rgb, static fn(): float => 0.9);
                $ink = $rgb;
            }
            if ($arrow !== null) {
                self::arrow($c, $x + 25.0, $cy, 8.0, $arrow === 'up', $ink);
            }
            $c->write($text, $x + $padL, $cy - $c->capHeight($size, 'bold', $text) / 2, $size, $ink, 'bold', 'left', 0.08);
            $x += $w + 10.0;
        }
    }

    public static function arrow(CardCanvas $c, float $cx, float $cy, float $s, bool $up, array $rgb): void
    {
        $c->filledPolygon($up
            ? [[$cx, $cy - $s], [$cx + $s * 1.05, $cy + $s * 0.7], [$cx - $s * 1.05, $cy + $s * 0.7]]
            : [[$cx, $cy + $s], [$cx + $s * 1.05, $cy - $s * 0.7], [$cx - $s * 1.05, $cy - $s * 0.7]], $rgb, 1.0);
    }

    /** Label + big figure on the left; returns the y below the figure. */
    public static function hero(CardCanvas $c, string $label, array $labelRgb, string $value, array $valueRgb, float $maxSize, ?array $glow = null): float
    {
        $c->write($label, self::M, 316.0, 17.0, $labelRgb, 'semi', 'left', 0.2);
        $size = $c->fit($value, $maxSize, self::LEFT_W, 'heavy', -0.03, 40.0);
        $capTop = 352.0 + ($maxSize - $size) * 0.3;
        if ($glow !== null) {
            $c->glowText($value, self::M - 3.0, $capTop, $size, $glow, 'heavy', 'left', -0.03, 0.55, 40.0);
        }
        $c->write($value, self::M - 3.0, $capTop, $size, $valueRgb, 'heavy', 'left', -0.03);
        $bottom = $capTop + $c->capHeight($size, 'heavy', $value);
        $c->roundRect(self::M, $bottom + 26.0, 64.0, 5.0, 2.5, OrangePalette::ORANGE, 1.0);
        return $bottom + 31.0;
    }

    /** Bottom tiles: [label, value, valueRgb, sub, subRgb]. */
    public static function tiles(CardCanvas $c, array $tiles): void
    {
        $y = self::TILES_Y;
        $h = self::TILES_H;
        $gap = 16.0;
        $width = self::W - self::M * 2;
        $n = max(1, count($tiles));
        $w = ($width - $gap * ($n - 1)) / $n;
        foreach (array_values($tiles) as $i => $tile) {
            [$label, $value, $rgb] = $tile;
            $sub = $tile[3] ?? '';
            $subRgb = $tile[4] ?? OrangePalette::DIM;
            $x = self::M + $i * ($w + $gap);
            $c->roundRect($x, $y, $w, $h, 22.0, OrangePalette::TILE, 1.0);
            $c->ring($x + 0.75, $y + 0.75, $w - 1.5, $h - 1.5, 21.25, 1.5, OrangePalette::WHITE, static fn(): float => 0.10);
            $c->roundRect($x + 24.0, $y, 34.0, 4.0, 2.0, OrangePalette::ORANGE, 1.0);
            $c->write($label, $x + 24.0, $y + 26.0, 14.0, OrangePalette::DIM, 'semi', 'left', 0.2);
            $size = $c->fit($value, 34.0, $w - 48.0, 'bold', -0.01, 16.0);
            $c->write($value, $x + 24.0, $y + 54.0, $size, $rgb, 'bold', 'left', -0.01);
            if ($sub !== '') {
                $subSize = $c->fit($sub, 16.0, $w - 48.0, 'semi', 0.04, 11.0);
                $c->write($sub, $x + 24.0, $y + 100.0, $subSize, $subRgb, 'semi', 'left', 0.04);
            }
        }
    }

    public static function footer(CardCanvas $c, string $time): void
    {
        $y = self::H - 52.0;
        $time = trim($time);
        if ($time !== '') {
            $c->write($time, self::M, $y, 14.0, OrangePalette::DIM, 'semi', 'left', 0.14);
        }
        $right = self::W - self::M;
        $logo = CardConfig::logoPath();
        if ($logo !== null && $c->image($logo, $right - 200.0, $y - 12.0, 200.0, 32.0)) {
            return;
        }
        $brand = CardConfig::brand();
        $persian = PersianShaper::containsPersian($brand);
        $brand = $persian ? $brand : strtoupper($brand);
        $w = $c->write($brand, $right, $y - 2.0, 16.0, OrangePalette::WHITE, 'bold', 'right', $persian ? 0.0 : 0.2);
        $c->roundRect($right - $w - 20.0, $y + 3.0, 9.0, 9.0, 4.5, OrangePalette::ORANGE, 1.0);
    }
}

final class OrangeSignalCard
{
    public static function render(array $d): string
    {
        $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
        [$dirRgb] = OrangePalette::tone($isLong ? 'long' : 'short');
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
        [$points, $toY] = OrangeLayout::project($path, array_merge([$sl, $entry], array_values($tps)));

        $c = OrangeLayout::begin($points);
        OrangeLayout::header($c, $pair);

        $chips = [[$isLong ? 'LONG' : 'SHORT', 'solid', $isLong ? 'up' : 'down', $dirRgb]];
        $lev = strtoupper(trim((string) ($d['leverage'] ?? '')));
        if ($lev !== '') {
            $chips[] = [$lev, 'outline', null, OrangePalette::ORANGE];
        }
        $tf = strtoupper(trim((string) ($d['timeframe'] ?? '')));
        if ($tf !== '') {
            $chips[] = [$tf, 'outline', null, OrangePalette::WHITE];
        }
        OrangeLayout::chips($c, 236.0, $chips);

        $below = OrangeLayout::hero($c, 'ENTRY PRICE', OrangePalette::DIM, NeoFormat::price($d['entry'] ?? null), OrangePalette::WHITE, 92.0);
        $conf = strtoupper(trim((string) ($d['confidence'] ?? '')));
        $c->write('MARKET ORDER' . ($conf !== '' ? ' · ' . $conf . ' CONFIDENCE' : ''), OrangeLayout::M, $below + 24.0, 15.0, OrangePalette::ORANGE_SOFT, 'semi', 'left', 0.14);

        // Max risk / max reward with leverage
        $stats = [
            ['MAX RISK', trim((string) ($d['sl_pct'] ?? '')), OrangePalette::RED_SOFT],
            ['MAX REWARD', trim((string) ($d['tp4_pct'] ?? ($d['tp3_pct'] ?? ''))), OrangePalette::GREEN_SOFT],
        ];
        $sx = OrangeLayout::M;
        foreach ($stats as [$label, $value, $rgb]) {
            if ($value === '') {
                continue;
            }
            $c->write($label, $sx, 566.0, 13.0, OrangePalette::DIM, 'semi', 'left', 0.2);
            $c->write(CardFormat::signed($value), $sx, 592.0, 30.0, $rgb, 'bold', 'left', -0.01);
            $sx += 210.0;
        }

        $tags = [];
        foreach ($tps as $k => $price) {
            $y = $toY($price);
            OrangeLayout::level($c, $y, OrangePalette::GREEN, 0.55);
            $tags[] = ['y' => $y, 'label' => 'TP' . $k, 'price' => NeoFormat::price((string) ($d['tp' . $k] ?? '')), 'rgb' => OrangePalette::GREEN, 'solid' => false, 'dim' => false];
        }
        if ($sl !== null) {
            $y = $toY($sl);
            OrangeLayout::level($c, $y, OrangePalette::RED, 0.7);
            $tags[] = ['y' => $y, 'label' => 'SL', 'price' => NeoFormat::price($d['sl'] ?? null), 'rgb' => OrangePalette::RED, 'solid' => false, 'dim' => false];
        }
        $entryY = $toY($entry);
        OrangeLayout::level($c, $entryY, OrangePalette::ORANGE, 0.85, true);
        $tags[] = ['y' => $entryY, 'label' => 'ENTRY', 'price' => NeoFormat::price($d['entry'] ?? null), 'rgb' => OrangePalette::ORANGE, 'solid' => true, 'dim' => false];
        OrangeLayout::priceLine($c, $points);
        $last = end($points);
        OrangeLayout::dot($c, $last[0], $last[1], OrangePalette::ORANGE);
        OrangeLayout::tags($c, $tags);

        $consensus = trim((string) ($d['consensus'] ?? ''));
        $rr = trim((string) ($d['rr'] ?? ''));
        $tiles = [
            ['STOP LOSS', NeoFormat::price($d['sl'] ?? null), OrangePalette::WHITE, (string) ($d['sl_pct'] ?? ''), OrangePalette::RED_SOFT],
            ['TARGET 1', NeoFormat::price($d['tp1'] ?? null), OrangePalette::WHITE, (string) ($d['tp1_pct'] ?? ''), OrangePalette::GREEN_SOFT],
            ['FINAL TARGET', NeoFormat::price($d['tp4'] ?? ($d['tp3'] ?? null)), OrangePalette::WHITE, (string) ($d['tp4_pct'] ?? ($d['tp3_pct'] ?? '')), OrangePalette::GREEN_SOFT],
            $consensus !== ''
                ? ['INDICATORS', $consensus, OrangePalette::ORANGE, $rr !== '' ? 'REWARD ' . $rr : '', OrangePalette::DIM]
                : ['REWARD : RISK', $rr !== '' ? $rr : 'N/A', OrangePalette::ORANGE, '', OrangePalette::DIM],
        ];
        OrangeLayout::tiles($c, $tiles);
        OrangeLayout::footer($c, (string) ($d['time'] ?? ''));

        return OrangeLayout::finish($c);
    }

    // Real recent closes when available; otherwise a smooth illustrative path ending at the entry.
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

final class OrangeResultCard
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
        [$main, $soft] = OrangePalette::tone($tone);
        $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
        [$dirRgb] = OrangePalette::tone($isLong ? 'long' : 'short');
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
        [$points, $toY] = OrangeLayout::project($path, array_merge([$sl, $entry, $exit], array_values($tps)));

        $c = OrangeLayout::begin($points);
        OrangeLayout::header($c, $pair);
        $chips = [[$isLong ? 'LONG' : 'SHORT', 'outline', $isLong ? 'up' : 'down', $dirRgb]];
        $lev = strtoupper(trim((string) ($d['leverage'] ?? '')));
        if ($lev !== '') {
            $chips[] = [$lev, 'outline', null, OrangePalette::ORANGE];
        }
        $tf = strtoupper(trim((string) ($d['timeframe'] ?? '')));
        if ($tf !== '') {
            $chips[] = [$tf, 'outline', null, OrangePalette::WHITE];
        }
        OrangeLayout::chips($c, 236.0, $chips);

        $below = OrangeLayout::hero($c, $status, $tone === 'flat' ? OrangePalette::ORANGE_SOFT : $soft, CardFormat::signed($headline), $main, 116.0, $tone === 'flat' ? null : $main);
        $move = trim((string) ($d['move'] ?? ''));
        $c->write('ROI WITH ' . ($lev !== '' ? $lev : 'LEVERAGE') . ($move !== '' ? '  ·  PRICE MOVE ' . CardFormat::signed($move) : ''),
            OrangeLayout::M, $below + 24.0, 15.0, OrangePalette::DIM, 'semi', 'left', 0.12);

        $tags = [];
        foreach ($tps as $k => $price) {
            $y = $toY($price);
            $reached = $k <= $hits;
            OrangeLayout::level($c, $y, OrangePalette::GREEN, $reached ? 0.7 : 0.22, $reached);
            $tags[] = ['y' => $y, 'label' => 'TP' . $k, 'price' => NeoFormat::price((string) ($d['tp' . $k] ?? '')), 'rgb' => OrangePalette::GREEN, 'solid' => $reached, 'dim' => !$reached];
        }
        if ($sl !== null) {
            $y = $toY($sl);
            OrangeLayout::level($c, $y, OrangePalette::RED, $kind === 'sl' ? 0.8 : 0.3, $kind === 'sl');
            $tags[] = ['y' => $y, 'label' => 'SL', 'price' => NeoFormat::price($d['sl'] ?? null), 'rgb' => OrangePalette::RED, 'solid' => $kind === 'sl', 'dim' => $kind !== 'sl'];
        }
        $entryY = $toY($entry);
        OrangeLayout::level($c, $entryY, OrangePalette::ORANGE, 0.7);
        $tags[] = ['y' => $entryY, 'label' => 'ENTRY', 'price' => NeoFormat::price($d['entry'] ?? null), 'rgb' => OrangePalette::ORANGE, 'solid' => false, 'dim' => false];

        OrangeLayout::priceLine($c, $points);
        $entryPoint = $points[(int) floor(count($points) * 0.35)];
        OrangeLayout::dot($c, $entryPoint[0], $entryY, OrangePalette::ORANGE, 7.0);
        $last = end($points);
        OrangeLayout::dot($c, $last[0], $last[1], $tone === 'flat' ? OrangePalette::WHITE : $main, 8.5);
        OrangeLayout::tags($c, $tags);

        $tiles = [
            ['ENTRY', NeoFormat::price($d['entry'] ?? null), OrangePalette::WHITE],
            [$running ? 'HIT PRICE' : 'EXIT', NeoFormat::price($d['exit'] ?? null), OrangePalette::WHITE],
            ['TARGETS', $hits . ' / ' . max(1, count($tps)), $hits > 0 ? OrangePalette::GREEN_SOFT : OrangePalette::WHITE],
            ['DURATION', CardFormat::duration(CardFormat::orNA($d['duration'] ?? null)), OrangePalette::WHITE],
        ];
        OrangeLayout::tiles($c, $tiles);
        self::pips($c, $hits, count($tps));
        OrangeLayout::footer($c, (string) ($d['time'] ?? ''));

        return OrangeLayout::finish($c);
    }

    // Progress pips inside the TARGETS tile.
    private static function pips(CardCanvas $c, int $hits, int $total): void
    {
        $gap = 16.0;
        $w = (OrangeLayout::W - OrangeLayout::M * 2 - $gap * 3) / 4;
        $x = OrangeLayout::M + 2 * ($w + $gap) + 24.0;
        $y = OrangeLayout::TILES_Y + 104.0;
        for ($k = 1; $k <= max(1, $total); $k++) {
            $on = $k <= $hits;
            $c->roundRect($x, $y, 32.0, 8.0, 4.0, $on ? OrangePalette::GREEN : OrangePalette::WHITE, $on ? 1.0 : 0.14);
            $x += 40.0;
        }
    }

    // Illustrative trade path: around the entry, to the furthest target reached, then to the exit.
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

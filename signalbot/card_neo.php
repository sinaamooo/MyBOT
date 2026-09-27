<?php

declare(strict_types=1);

// "Neo" card design: dark terminal look with a direction-coloured glow, the pair and entry on
// the left and a vertical trade ladder (stop, entry, four targets) on the right. The previous
// glass design stays in card.php as the "classic" style (CARD_STYLE=classic).

final class NeoPalette
{
    public const BG = [5, 8, 15];
    public const PANEL = [12, 16, 27];
    public const WHITE = [255, 255, 255];
    public const DIM = [150, 161, 186];
    public const FAINT = [88, 99, 124];
    public const MINT = [22, 229, 160];
    public const MINT_SOFT = [134, 247, 207];
    public const ROSE = [255, 72, 112];
    public const ROSE_SOFT = [255, 155, 176];
    public const VIOLET = [124, 92, 255];
    public const AMBER = [255, 196, 87];
    public const SLATE = [120, 142, 190];
    public const INK = [4, 20, 14];

    public static function tone(string $tone): array
    {
        return match ($tone) {
            'long', 'profit' => self::MINT,
            'short', 'loss' => self::ROSE,
            default => self::SLATE,
        };
    }

    public static function toneSoft(string $tone): array
    {
        return match ($tone) {
            'long', 'profit' => self::MINT_SOFT,
            'short', 'loss' => self::ROSE_SOFT,
            default => [196, 208, 232],
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
}

final class NeoLayout
{
    public const W = 1600;
    public const H = 900;
    private const STEP = 8;

    public const LEFT = 96.0;
    public const PANEL = [968.0, 64.0, 544.0, 772.0, 40.0];

    // Background: deep navy, a large glow in the trade colour behind the headline, a violet
    // counter-glow in the far corner and a soft vignette.
    public static function begin(string $tone, array $heroGlow): CardCanvas
    {
        $accent = NeoPalette::tone($tone);
        $lw = intdiv(self::W, self::STEP) + 1;
        $lh = intdiv(self::H, self::STEP) + 1;
        $field = [[], [], []];
        [$hx, $hy, $hr, $ha] = $heroGlow;
        for ($j = 0; $j < $lh; $j++) {
            $y = (float) ($j * self::STEP);
            for ($i = 0; $i < $lw; $i++) {
                $x = (float) ($i * self::STEP);
                $rgb = NeoPalette::BG;
                $rgb = CardCanvas::mix($accent, $rgb, 0.42 * self::falloff($x, $y, 170.0, 120.0, 560.0));
                $rgb = CardCanvas::mix(NeoPalette::VIOLET, $rgb, 0.30 * self::falloff($x, $y, 1500.0, 900.0, 640.0));
                $rgb = CardCanvas::mix($accent, $rgb, $ha * self::falloff($x, $y, $hx, $hy, $hr));
                $dx = ($x - self::W / 2) / (self::W / 2);
                $dy = ($y - self::H / 2) / (self::H / 2);
                $vignette = 1.0 - 0.38 * min(1.0, ($dx * $dx + $dy * $dy) / 2.0);
                $field[0][] = $rgb[0] * $vignette;
                $field[1][] = $rgb[1] * $vignette;
                $field[2][] = $rgb[2] * $vignette;
            }
        }
        $c = new CardCanvas(self::W, self::H);
        $c->blitField($field, $lw, $lh, self::STEP);
        self::grid($c);
        return $c;
    }

    private static function falloff(float $x, float $y, float $cx, float $cy, float $r): float
    {
        $d = hypot($x - $cx, $y - $cy) / $r;
        return $d >= 1.0 ? 0.0 : (1.0 - $d) ** 2.2;
    }

    // Faint dot grid on the left half — a chart-paper texture.
    private static function grid(CardCanvas $c): void
    {
        for ($y = 36.0; $y < self::H - 20; $y += 36.0) {
            for ($x = 36.0; $x < self::PANEL[0] - 20; $x += 36.0) {
                $c->roundRect($x - 1.2, $y - 1.2, 2.4, 2.4, 1.2, NeoPalette::WHITE, 0.055);
            }
        }
    }

    public static function finish(CardCanvas $c): string
    {
        $png = $c->toPng();
        $c->destroy();
        return $png;
    }

    public static function brandRow(CardCanvas $c, float $y, array $accent): void
    {
        $logo = CardConfig::logoPath();
        if ($logo !== null && $c->image($logo, self::LEFT, $y - 18.0, 220.0, 36.0)) {
            return;
        }
        $c->roundRect(self::LEFT, $y - 6.0, 12.0, 12.0, 6.0, $accent, 1.0);
        $c->roundRect(self::LEFT - 5.0, $y - 11.0, 22.0, 22.0, 11.0, $accent, 0.18);
        $brand = CardConfig::brand();
        $c->write(PersianShaper::containsPersian($brand) ? $brand : strtoupper($brand), self::LEFT + 30.0, $y - $c->capHeight(20.0, 'semi', $brand) / 2, 20.0, NeoPalette::WHITE, 'semi', 'left', 0.16, 0.62);
    }

    // Big base asset with the quote asset dimmed on the same baseline.
    public static function pair(CardCanvas $c, string $pair, float $capTop, float $maxWidth): void
    {
        [$base, $quote] = NeoFormat::splitPair($pair);
        $quoteSize = 52.0;
        $quoteW = $quote !== '' ? $c->measure($quote, $quoteSize, 'bold', 0.0) + 14.0 : 0.0;
        $size = $c->fit($base, 150.0, $maxWidth - $quoteW, 'heavy', -0.03, 60.0);
        $c->write($base, self::LEFT, $capTop, $size, NeoPalette::WHITE, 'heavy', 'left', -0.03);
        if ($quote !== '') {
            $baseW = $c->measure($base, $size, 'heavy', -0.03);
            $baseline = $capTop + $c->capHeight($size, 'heavy', $base);
            $c->write($quote, self::LEFT + $baseW + 14.0, $baseline - $c->capHeight($quoteSize, 'bold', $quote), $quoteSize, NeoPalette::DIM, 'bold', 'left', 0.0, 0.85);
        }
    }

    // Pills: [text, style, arrow] where style is 'solid' (filled with accent) or 'ghost'.
    public static function chips(CardCanvas $c, float $cy, array $chips, array $accent): void
    {
        $x = self::LEFT;
        foreach ($chips as [$text, $style, $arrow]) {
            $size = 21.0;
            $padL = $arrow !== null ? 50.0 : 24.0;
            $w = $padL + $c->measure($text, $size, 'bold', 0.08) + 24.0;
            $h = 50.0;
            if ($style === 'solid') {
                $c->roundRect($x - 6.0, $cy - $h / 2 - 6.0, $w + 12.0, $h + 12.0, ($h + 12.0) / 2, $accent, 0.16);
                $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, $accent, 1.0);
                $ink = NeoPalette::INK;
            } else {
                $c->roundRect($x, $cy - $h / 2, $w, $h, $h / 2, NeoPalette::WHITE, 0.06);
                $c->ring($x + 0.75, $cy - $h / 2 + 0.75, $w - 1.5, $h - 1.5, $h / 2 - 0.75, 1.5, NeoPalette::WHITE, static fn(): float => 0.22);
                $ink = NeoPalette::WHITE;
            }
            if ($arrow !== null) {
                self::arrow($c, $x + 28.0, $cy, 9.0, $arrow === 'up', $ink, 1.0);
            }
            $c->write($text, $x + $padL, $cy - $c->capHeight($size, 'bold', $text) / 2, $size, $ink, 'bold', 'left', 0.08);
            $x += $w + 12.0;
        }
    }

    public static function arrow(CardCanvas $c, float $cx, float $cy, float $s, bool $up, array $rgb, float $opacity): void
    {
        $c->filledPolygon($up
            ? [[$cx, $cy - $s], [$cx + $s * 1.05, $cy + $s * 0.7], [$cx - $s * 1.05, $cy + $s * 0.7]]
            : [[$cx, $cy + $s], [$cx + $s * 1.05, $cy - $s * 0.7], [$cx - $s * 1.05, $cy - $s * 0.7]], $rgb, $opacity);
    }

    // Row of small stat tiles: [label, value, rgb].
    public static function tiles(CardCanvas $c, float $y, array $tiles, float $width): void
    {
        $gap = 16.0;
        $n = max(1, count($tiles));
        $w = ($width - $gap * ($n - 1)) / $n;
        $h = 118.0;
        foreach (array_values($tiles) as $i => [$label, $value, $rgb]) {
            $x = self::LEFT + $i * ($w + $gap);
            $c->roundRect($x, $y, $w, $h, 22.0, NeoPalette::PANEL, 0.78);
            $c->ring($x + 0.5, $y + 0.5, $w - 1.0, $h - 1.0, 21.5, 1.0, NeoPalette::WHITE, static fn(float $px, float $py): float => 0.14 * max(0.0, 1.0 - ($py - $y) / $h) + 0.03);
            $c->write($label, $x + 24.0, $y + 26.0, 17.0, NeoPalette::DIM, 'semi', 'left', 0.14, 0.9);
            $size = $c->fit($value, 38.0, $w - 48.0, 'bold', -0.01, 20.0);
            $c->write($value, $x + 24.0, $y + 62.0, $size, $rgb, 'bold', 'left', -0.01);
        }
    }

    // Vertical trade ladder inside the right panel. $rows top→bottom: [label, price, pct, kind, state]
    // kind: tp|entry|sl, state: normal|hit|dim|exit.
    public static function ladder(CardCanvas $c, string $title, array $rows, bool $upArrow, array $accent): void
    {
        [$px, $py, $pw, $ph, $pr] = self::PANEL;
        $c->roundRect($px, $py, $pw, $ph, $pr, NeoPalette::PANEL, 0.80);
        $c->ring($px + 0.75, $py + 0.75, $pw - 1.5, $ph - 1.5, $pr - 0.75, 1.5, NeoPalette::WHITE, static fn(float $x, float $y): float => 0.16 * max(0.0, 1.0 - ($y - $py) / 260.0) + 0.05);

        $c->write($title, $px + 44.0, $py + 46.0, 18.0, NeoPalette::DIM, 'semi', 'left', 0.2, 0.9);
        self::arrow($c, $px + $pw - 56.0, $py + 54.0, 9.0, $upArrow, $accent, 1.0);

        $top = $py + 132.0;
        $bottom = $py + $ph - 72.0;
        $count = count($rows);
        $step = $count > 1 ? ($bottom - $top) / ($count - 1) : 0.0;
        $trackX = $px + 64.0;
        $entryIndex = null;
        foreach ($rows as $i => $row) {
            if ($row[3] === 'entry') {
                $entryIndex = $i;
            }
        }

        // Track: coloured toward the targets, red toward the stop.
        for ($i = 0; $i < $count - 1; $i++) {
            $y1 = $top + $step * $i;
            $y2 = $top + $step * ($i + 1);
            $towardStop = $rows[$i][3] === 'sl' || $rows[$i + 1][3] === 'sl';
            $rgb = $towardStop ? NeoPalette::ROSE : NeoPalette::MINT;
            $lit = $rows[$i][4] !== 'dim' && $rows[$i + 1][4] !== 'dim';
            $c->roundRect($trackX - 2.0, $y1, 4.0, $y2 - $y1, 2.0, $rgb, $lit ? 0.95 : 0.34);
        }

        foreach ($rows as $i => $row) {
            [$label, $price, $pct, $kind, $state] = $row;
            $tag = $row[5] ?? 'EXIT';
            $cy = $top + $step * $i;
            $rgb = match ($kind) {
                'sl' => NeoPalette::ROSE,
                'entry' => NeoPalette::AMBER,
                default => NeoPalette::MINT,
            };
            $soft = match ($kind) {
                'sl' => NeoPalette::ROSE_SOFT,
                'entry' => [255, 222, 160],
                default => NeoPalette::MINT_SOFT,
            };
            $dim = $state === 'dim';
            if ($kind === 'entry') {
                $c->roundRect($px + 20.0, $cy - 44.0, $pw - 40.0, 88.0, 24.0, NeoPalette::AMBER, 0.07);
            }
            if ($state === 'hit' || $state === 'exit' || $kind === 'entry') {
                $c->roundRect($trackX - 22.0, $cy - 22.0, 44.0, 44.0, 22.0, $rgb, 0.16);
            }
            $reached = $state === 'hit' || $state === 'exit';
            $c->roundRect($trackX - 12.0, $cy - 12.0, 24.0, 24.0, 12.0, $rgb, $dim ? 0.35 : 1.0);
            if (!$reached && $kind === 'tp') {
                $c->roundRect($trackX - 6.5, $cy - 6.5, 13.0, 13.0, 6.5, NeoPalette::PANEL, 1.0);
            }
            if ($reached && $kind === 'tp') {
                self::check($c, $trackX, $cy, NeoPalette::INK);
            }

            $labelX = $trackX + 40.0;
            if ($pct !== '') {
                $c->write($label, $labelX, $cy - 26.0, 20.0, $dim ? NeoPalette::FAINT : $soft, 'bold', 'left', 0.12);
                $c->write($pct, $labelX, $cy + 8.0, 19.0, $dim ? NeoPalette::FAINT : $rgb, 'semi', 'left', 0.02, $dim ? 0.8 : 1.0);
            } else {
                $c->write($label, $labelX, $cy - $c->capHeight(20.0, 'bold', $label) / 2, 20.0, $dim ? NeoPalette::FAINT : $soft, 'bold', 'left', 0.12);
            }
            $size = $c->fit($price, 34.0, $pw - ($labelX - $px) - 170.0, 'bold', -0.01, 20.0);
            $c->write($price, $px + $pw - 44.0, $cy - $c->capHeight($size, 'bold', $price) / 2, $size, $dim ? NeoPalette::FAINT : NeoPalette::WHITE, 'bold', 'right', -0.01);
            if ($state === 'exit') {
                $tw = $c->measure($tag, 14.0, 'bold', 0.14) + 20.0;
                $tx = $px + $pw - 44.0 - $c->measure($price, $size, 'bold', -0.01) - $tw - 14.0;
                $c->roundRect($tx, $cy - 14.0, $tw, 28.0, 14.0, $rgb, 1.0);
                $c->write($tag, $tx + 10.0, $cy - $c->capHeight(14.0, 'bold', $tag) / 2, 14.0, NeoPalette::INK, 'bold', 'left', 0.14);
            }
        }
    }

    private static function check(CardCanvas $c, float $cx, float $cy, array $rgb): void
    {
        $pts = [[-6.0, 0.0], [-2.0, 4.5], [6.5, -5.0]];
        for ($k = 0; $k < 2; $k++) {
            [$ax, $ay] = $pts[$k];
            [$bx, $by] = $pts[$k + 1];
            $len = hypot($bx - $ax, $by - $ay);
            $nx = -($by - $ay) / $len * 1.6;
            $ny = ($bx - $ax) / $len * 1.6;
            $c->filledPolygon([
                [$cx + $ax + $nx, $cy + $ay + $ny], [$cx + $bx + $nx, $cy + $by + $ny],
                [$cx + $bx - $nx, $cy + $by - $ny], [$cx + $ax - $nx, $cy + $ay - $ny],
            ], $rgb, 1.0);
        }
    }

    public static function footer(CardCanvas $c, string $time): void
    {
        $time = trim($time);
        if ($time !== '') {
            $c->write($time, self::LEFT, self::H - 64.0, 18.0, NeoPalette::WHITE, 'semi', 'left', 0.14, 0.40);
        }
    }
}

final class NeoSignalCard
{
    public static function render(array $d): string
    {
        $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
        $tone = $isLong ? 'long' : 'short';
        $accent = NeoPalette::tone($tone);
        $c = NeoLayout::begin($tone, [420.0, 560.0, 520.0, 0.16]);

        NeoLayout::brandRow($c, 104.0, $accent);
        NeoLayout::pair($c, CardLayout::pair((string) ($d['symbol'] ?? '')), 168.0, 820.0);

        $chips = [[$isLong ? 'LONG' : 'SHORT', 'solid', $isLong ? 'up' : 'down']];
        $leverage = strtoupper(trim((string) ($d['leverage'] ?? '')));
        if ($leverage !== '') {
            $chips[] = [$leverage, 'ghost', null];
        }
        $tf = strtoupper(trim((string) ($d['timeframe'] ?? '')));
        if ($tf !== '') {
            $chips[] = [$tf, 'ghost', null];
        }
        NeoLayout::chips($c, 372.0, $chips, $accent);

        $c->write('ENTRY PRICE', NeoLayout::LEFT, 446.0, 20.0, NeoPalette::DIM, 'semi', 'left', 0.2, 0.9);
        $entry = NeoFormat::price($d['entry'] ?? null);
        $size = $c->fit($entry, 118.0, 820.0, 'heavy', -0.03, 56.0);
        $c->write($entry, NeoLayout::LEFT - 4.0, 486.0, $size, NeoPalette::WHITE, 'heavy', 'left', -0.03);

        $tiles = [
            ['STOP LOSS', trim((string) ($d['sl_pct'] ?? '')) !== '' ? (string) $d['sl_pct'] : NeoFormat::price($d['sl'] ?? null), NeoPalette::ROSE_SOFT],
            ['REWARD : RISK', trim((string) ($d['rr'] ?? '')) !== '' ? (string) $d['rr'] : 'N/A', NeoPalette::WHITE],
        ];
        $third = trim((string) ($d['consensus'] ?? ''));
        $tiles[] = $third !== ''
            ? ['INDICATORS', $third, NeoPalette::MINT_SOFT]
            : ['CONFIDENCE', strtoupper(trim((string) ($d['confidence'] ?? ''))) ?: 'N/A', NeoPalette::MINT_SOFT];
        NeoLayout::tiles($c, 648.0, $tiles, 820.0);
        NeoLayout::footer($c, (string) ($d['time'] ?? ''));

        $levels = [
            ['TARGET 4', 'tp4'], ['TARGET 3', 'tp3'], ['TARGET 2', 'tp2'], ['TARGET 1', 'tp1'],
        ];
        $rows = [];
        foreach ($levels as [$label, $key]) {
            $price = trim((string) ($d[$key] ?? ''));
            if ($price === '' || $price === '-') {
                continue;
            }
            $rows[] = [$label, NeoFormat::price($price), (string) ($d[$key . '_pct'] ?? ''), 'tp', 'normal'];
        }
        $rows[] = ['ENTRY', $entry, '', 'entry', 'normal'];
        $rows[] = ['STOP LOSS', NeoFormat::price($d['sl'] ?? null), (string) ($d['sl_pct'] ?? ''), 'sl', 'normal'];
        if (!$isLong) {
            $rows = array_reverse($rows);
        }
        NeoLayout::ladder($c, 'TRADE PLAN', $rows, $isLong, $accent);

        return NeoLayout::finish($c);
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
        'sl' => 'STOP LOSS',
        'timeout' => 'TIME LIMIT',
    ];

    public static function render(array $d): string
    {
        $kind = strtolower((string) ($d['kind'] ?? 'tp1'));
        $headline = trim((string) ($d['headline'] ?? '')) ?: '0.00%';
        $pnl = CardFormat::number($headline) ?? 0.0;
        $tone = $pnl > 0.004 ? 'profit' : ($pnl < -0.004 || $kind === 'sl' ? 'loss' : 'flat');
        $accent = NeoPalette::tone($tone);
        $soft = NeoPalette::toneSoft($tone);
        $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
        $status = self::STATUS[$kind] ?? 'TRADE CLOSED';
        if ($tone === 'loss' && $kind !== 'sl') {
            $status = 'TRADE CLOSED';
        }

        $c = NeoLayout::begin($tone, [400.0, 520.0, 560.0, 0.24]);
        NeoLayout::brandRow($c, 104.0, $accent);
        NeoLayout::pair($c, CardLayout::pair((string) ($d['symbol'] ?? '')), 168.0, 820.0);

        $chips = [[$isLong ? 'LONG' : 'SHORT', 'ghost', $isLong ? 'up' : 'down']];
        $leverage = strtoupper(trim((string) ($d['leverage'] ?? '')));
        if ($leverage !== '') {
            $chips[] = [$leverage, 'ghost', null];
        }
        $tf = strtoupper(trim((string) ($d['timeframe'] ?? '')));
        if ($tf !== '') {
            $chips[] = [$tf, 'ghost', null];
        }
        NeoLayout::chips($c, 372.0, $chips, $accent);

        $c->write($status, NeoLayout::LEFT, 446.0, 22.0, $soft, 'bold', 'left', 0.2);
        $hero = CardFormat::signed($headline);
        $size = $c->fit($hero, 150.0, 820.0, 'heavy', -0.03, 64.0);
        $c->write($hero, NeoLayout::LEFT - 6.0, 486.0, $size, $soft, 'heavy', 'left', -0.03);

        // tp1..tp3 are progress updates on a trade that is still running; the rest close it.
        $running = in_array($kind, ['tp1', 'tp2', 'tp3'], true);
        NeoLayout::tiles($c, 668.0, [
            ['ENTRY', NeoFormat::price($d['entry'] ?? null), NeoPalette::WHITE],
            [$running ? 'HIT PRICE' : 'EXIT', NeoFormat::price($d['exit'] ?? null), NeoPalette::WHITE],
            ['DURATION', CardFormat::duration(CardFormat::orNA($d['duration'] ?? null)), NeoPalette::WHITE],
        ], 820.0);
        NeoLayout::footer($c, (string) ($d['time'] ?? ''));

        $hits = max(0, min(4, (int) ($d['hits'] ?? 0)));
        $rows = [];
        foreach ([4, 3, 2, 1] as $k) {
            $price = trim((string) ($d['tp' . $k] ?? ''));
            if ($price === '' || $price === '-') {
                continue;
            }
            $rows[] = ['TARGET ' . $k, NeoFormat::price($price), $k <= $hits ? 'REACHED' : '', 'tp', $k <= $hits ? 'hit' : 'dim'];
        }
        $rows[] = ['ENTRY', NeoFormat::price($d['entry'] ?? null), '', 'entry', $kind === 'be' ? 'exit' : 'hit'];
        $rows[] = ['STOP LOSS', NeoFormat::price($d['sl'] ?? null), $kind === 'sl' ? 'HIT' : '', 'sl', $kind === 'sl' ? 'exit' : 'dim'];
        if (!$isLong) {
            $rows = array_reverse($rows);
        }
        // Marker: the target just reached on progress updates, the target the trailing stop sat
        // on for a trailing close, the last target for a full close.
        $marker = match (true) {
            $running, $kind === 'tp4' => [$hits, $running ? 'HIT' : 'EXIT'],
            $kind === 'trail' => [$hits - 1, 'EXIT'],
            default => null,
        };
        if ($marker !== null && $marker[0] > 0) {
            foreach ($rows as $i => $row) {
                if ($row[0] === 'TARGET ' . $marker[0]) {
                    $rows[$i][4] = 'exit';
                    $rows[$i][5] = $marker[1];
                }
            }
        }
        NeoLayout::ladder($c, 'TRADE RESULT', $rows, $isLong, $accent);

        return NeoLayout::finish($c);
    }
}

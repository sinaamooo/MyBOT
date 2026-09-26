<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\LiquidityProvider;

final class LiquidityCard extends Card
{
    private const MAP_H   = 392.0;
    private const HEAD_H  = 46.0;
    private const LABEL_H = 26.0;
    private const ROW_H   = 42.0;
    private const STRIP_H = 70.0;
    private const GAP     = 16.0;
    private const PROF_W  = 168.0;
    private const GUTTER  = 92.0;

    private array $data;
    private string $title;

    public function __construct(array $data, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->data = $data;
        $this->title = (string) ($options['title'] ?? 'نقشه‌ی نقدینگی بیت‌کوین');
    }

    public function render(): Canvas
    {
        $rows = max(1, count($this->data['bid_walls'] ?? []), count($this->data['ask_walls'] ?? []));
        $tableH = self::HEAD_H + self::LABEL_H + $rows * self::ROW_H + 10;
        $height = (int) round(84 + Frame::HEADER_H + self::MAP_H + self::GAP + $tableH + self::GAP + self::STRIP_H);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header(
            $c,
            $t,
            $rect,
            $this->title,
            $this->dateLine(),
            '',
            fn (float $x, float $y) => $this->priceWidget($c, $t, $x, $y)
        );

        $this->map($c, $t, $rect['x'], $top, $rect['w'], self::MAP_H);

        $tableY = $top + self::MAP_H + self::GAP;
        $colW = ($rect['w'] - 18) / 2;
        $this->table($c, $t, true, (array) ($this->data['bid_walls'] ?? []), $rect['x'] + $colW + 18, $tableY, $colW, $tableH);
        $this->table($c, $t, false, (array) ($this->data['ask_walls'] ?? []), $rect['x'], $tableY, $colW, $tableH);

        $this->balance($c, $t, $rect['x'], $tableY + $tableH + self::GAP, $rect['w'], self::STRIP_H);

        return $c;
    }

    private function price(float $p): string
    {
        $bucket = (float) ($this->data['bucket'] ?? 100);

        return $this->dnum(number_format($p, $bucket < 1 ? 2 : 0));
    }

    private function priceWidget(Canvas $c, Theme $t, float $x, float $y): void
    {
        $text = '$' . $this->dnum(number_format((float) $this->data['price'], 1));
        $w = $c->textWidth($text, 15, Canvas::W_NUM_BOLD) + 30;
        $h = 32.0;
        $c->roundRect($x, $y, $w, $h, 9, $t->c('line'), 1.0);
        $c->text($text, $x + $w / 2, $y + $h / 2, 15, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 1.0, 'num');
        $c->text('BTC / USDT', $x + $w + 10, $y + $h / 2, 11, $t->c('ink'), Canvas::W_NUM_BOLD, 'left', 1.0, 'num');
        $c->text(
            implode(' + ', (array) ($this->data['sources'] ?? [])),
            $x,
            $y + $h + 14,
            9.5,
            $t->c('ink_dim'),
            Canvas::W_NUM,
            'left',
            1.0,
            'num'
        );
    }

    private function map(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        Frame::panel($c, $t, $x, $y, $w, $h, 20);

        $pad = 20.0;
        $titleY = $y + 26;
        $c->text('دیوارهای نقدینگی روی نمودار ۴۸ ساعته', $x + $w - $pad, $titleY, 12.5, $t->c('ink'), Canvas::W_BOLD, 'right', 1.0, 'middle');
        $this->legend($c, $t, $x + $pad, $titleY);

        $top = $y + 56;
        $bottom = $y + $h - 40;
        $chartL = $x + $pad;
        $profL = $x + $w - $pad - self::PROF_W;
        $chartR = $profL - self::GUTTER - 8;
        $gutterL = $chartR + 8;

        $candles = (array) ($this->data['candles'] ?? []);
        $bidWalls = (array) ($this->data['bid_walls'] ?? []);
        $askWalls = (array) ($this->data['ask_walls'] ?? []);
        $price = (float) $this->data['price'];

        $prices = [$price];
        foreach ($candles as $k) {
            $prices[] = (float) $k['h'];
            $prices[] = (float) $k['l'];
        }
        foreach (array_merge($bidWalls, $askWalls) as $wall) {
            $prices[] = (float) $wall['price'];
        }
        $lo = min($prices);
        $hi = max($prices);
        $padP = max(($hi - $lo) * 0.06, $price * 0.001);
        $lo -= $padP;
        $hi += $padP;
        $yOf = static fn (float $p): float => $bottom - ($p - $lo) / ($hi - $lo) * ($bottom - $top);

        for ($i = 0; $i <= 4; $i++) {
            $gy = $top + ($bottom - $top) * $i / 4;
            $c->rect($chartL, $gy, $chartR - $chartL, 1, $t->c('line_soft'), 0.8);
        }
        $c->rect($profL, $top, 1.2, $bottom - $top, $t->c('line'), 0.35);

        foreach ([[$bidWalls, $t->c('up')], [$askWalls, $t->c('down')]] as [$walls, $color]) {
            foreach ($walls as $wall) {
                $wy = $yOf((float) $wall['price']);
                $s = (float) ($wall['strength'] ?? 0.5);
                $band = 3 + 9 * $s;
                $c->rect($chartL, $wy - $band / 2, $chartR - $chartL, $band, $color, 0.10 + 0.22 * $s);
                $c->rect($chartL, $wy - 0.7, $chartR - $chartL, 1.4, $color, 0.9);
            }
        }

        $this->candles($c, $t, $candles, $chartL + 4, $chartR - 4, $yOf);

        $py = $yOf($price);
        $c->dashedLine($chartL, $py, $chartR, $py, $t->c('line'), 1.4, 6, 4, 0.9);

        $this->profile($c, $t, $profL + 8, $x + $w - $pad, $lo, $hi, $yOf);

        $labels = [['y' => $py, 'text' => $this->dnum(number_format($price, 1)), 'color' => $t->c('line'), 'fixed' => true]];
        foreach ([[$bidWalls, $t->c('up')], [$askWalls, $t->c('down')]] as [$walls, $color]) {
            foreach ($walls as $wall) {
                $labels[] = ['y' => $yOf((float) $wall['price']), 'text' => $this->price((float) $wall['price']), 'color' => $color, 'fixed' => false];
            }
        }
        $this->gutterLabels($c, $t, $labels, $gutterL, $chartR, $top, $bottom);

        $timeY = $bottom + 20;
        $c->text('۴۸ ساعت پیش', $chartL, $timeY, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');
        $c->text('اکنون', $chartR, $timeY, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
        $c->text('عمق دفتر سفارش', $x + $w - $pad, $timeY, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
    }

    private function legend(Canvas $c, Theme $t, float $x, float $y): void
    {
        $items = [
            ['قیمت فعلی', $t->c('line'), true],
            ['دیوار فروش', $t->c('down'), false],
            ['دیوار خرید', $t->c('up'), false],
        ];
        $cursor = $x;
        foreach ($items as [$label, $color, $dashed]) {
            if ($dashed) {
                $c->dashedLine($cursor, $y, $cursor + 18, $y, $color, 1.6, 5, 3, 1.0);
            } else {
                $c->roundRect($cursor, $y - 5, 18, 10, 3, $color, 1.0);
            }
            $c->text($label, $cursor + 24, $y, 10, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'left', 1.0, 'middle');
            $cursor += 24 + $c->textWidth($label, 10, Canvas::W_SEMIBOLD) + 18;
        }
    }

    private function candles(Canvas $c, Theme $t, array $candles, float $left, float $right, callable $yOf): void
    {
        $n = count($candles);
        if ($n === 0) {
            return;
        }
        $slot = ($right - $left) / $n;
        $bodyW = max(2.0, min(10.0, $slot * 0.62));
        $ink = $t->c('line');

        foreach (array_values($candles) as $i => $k) {
            $cx = $left + $slot * ($i + 0.5);
            $o = (float) $k['o'];
            $cl = (float) $k['c'];
            $c->line($cx, $yOf((float) $k['h']), $cx, $yOf((float) $k['l']), $ink, 1.2, 0.8);
            $yTop = $yOf(max($o, $cl));
            $bodyH = max(1.4, $yOf(min($o, $cl)) - $yTop);
            if ($cl >= $o) {
                $c->rect($cx - $bodyW / 2, $yTop, $bodyW, $bodyH, '#FFFFFF', 1.0);
                $c->strokeRoundRect($cx - $bodyW / 2, $yTop, $bodyW, $bodyH, 0.8, $ink, 1.2, 0.85);
            } else {
                $c->rect($cx - $bodyW / 2, $yTop, $bodyW, $bodyH, $ink, 0.85);
            }
        }
    }

    private function profile(Canvas $c, Theme $t, float $left, float $right, float $lo, float $hi, callable $yOf): void
    {
        $profile = (array) ($this->data['profile'] ?? []);
        $bucket = (float) ($this->data['bucket'] ?? 100);
        $walls = [];
        foreach (array_merge((array) ($this->data['bid_walls'] ?? []), (array) ($this->data['ask_walls'] ?? [])) as $w) {
            $walls[number_format((float) $w['price'], 2, '.', '')] = true;
        }

        $visible = [];
        foreach (['bids' => $t->c('up'), 'asks' => $t->c('down')] as $side => $color) {
            foreach ((array) ($profile[$side] ?? []) as [$p, $q]) {
                if ($p >= $lo && $p <= $hi) {
                    $visible[] = [(float) $p, (float) $q, $color];
                }
            }
        }
        if ($visible === []) {
            return;
        }

        $maxQ = max(array_column($visible, 1));
        $px = abs($yOf(0.0) - $yOf($bucket));
        $barH = max(1.5, min(11.0, $px - 2));
        $width = $right - $left;

        foreach ($visible as [$p, $q, $color]) {
            $len = max(1.5, $width * $q / $maxQ);
            $isWall = isset($walls[number_format($p, 2, '.', '')]);
            $c->roundRect($left, $yOf($p) - $barH / 2, $len, $barH, min(3.0, $barH / 2), $color, $isWall ? 1.0 : 0.32);
        }
    }

    private function gutterLabels(Canvas $c, Theme $t, array $labels, float $left, float $chartR, float $top, float $bottom): void
    {
        $h = 20.0;
        $gap = 3.0;
        usort($labels, static fn (array $a, array $b): int => $a['y'] <=> $b['y']);
        $n = count($labels);
        $pos = array_column($labels, 'y');

        for ($pass = 0; $pass < 6; $pass++) {
            for ($i = 1; $i < $n; $i++) {
                if ($pos[$i] - $pos[$i - 1] < $h + $gap) {
                    $pos[$i] = $pos[$i - 1] + $h + $gap;
                }
            }
            $pos[$n - 1] = min($pos[$n - 1], $bottom - $h / 2);
            for ($i = $n - 2; $i >= 0; $i--) {
                if ($pos[$i + 1] - $pos[$i] < $h + $gap) {
                    $pos[$i] = $pos[$i + 1] - $h - $gap;
                }
            }
            $pos[0] = max($pos[0], $top + $h / 2);
        }

        $w = self::GUTTER - 6;
        foreach ($labels as $i => $label) {
            $ly = $pos[$i];
            if (abs($ly - $label['y']) > 0.5) {
                $c->line($chartR, $label['y'], $left, $ly, $label['color'], 1.2, 0.7);
            }
            $c->roundRect($left, $ly - $h / 2, $w, $h, 6, $label['color'], 1.0);
            $c->textFit($label['text'], $left + $w / 2, $ly, $w - 10, 11, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 8, 1.0, 'num');
        }
    }

    private function table(Canvas $c, Theme $t, bool $bids, array $walls, float $x, float $y, float $w, float $h): void
    {
        $tone = $bids ? $t->c('up') : $t->c('down');
        Frame::panel($c, $t, $x, $y, $w, $h, 18);
        $c->roundRect($x + 18, $y - 0.5, $w - 36, 5, 2.5, $tone, 1.0);

        $pad = 18.0;
        $right = $x + $w - $pad;
        $headMid = $y + self::HEAD_H / 2 + 2;
        $c->roundRect($right - 26, $headMid - 13, 26, 26, 8, $tone, 1.0);
        $c->roundRect($right - 19, $headMid - 2, 12, 4, 2, '#FFFFFF', 1.0);
        $c->text(
            $bids ? 'دیوارهای خرید — حمایت' : 'دیوارهای فروش — مقاومت',
            $right - 36,
            $headMid,
            13.5,
            $t->c('ink'),
            Canvas::W_BLACK,
            'right',
            1.0,
            'middle'
        );
        $c->text($bids ? 'زیر قیمت فعلی' : 'بالای قیمت فعلی', $x + $pad, $headMid, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');

        $cols = [
            'price' => $right - 16,
            'qty'   => $right - 150,
            'usd'   => $right - 250,
            'dist'  => $right - 322,
            'bar'   => $x + $pad,
        ];

        $labelY = $y + self::HEAD_H + self::LABEL_H / 2;
        Frame::well($c, $t, $x + 8, $y + self::HEAD_H, $w - 16, self::LABEL_H, 8, 1.3);
        foreach ([['قیمت', $cols['price']], ['حجم', $cols['qty']], ['ارزش', $cols['usd']], ['فاصله', $cols['dist']]] as [$label, $lx]) {
            $c->text($label, $lx, $labelY, 10, $t->c('ink'), Canvas::W_BOLD, 'right', 1.0, 'middle');
        }
        $c->text('قدرت', $cols['bar'], $labelY, 10, $t->c('ink'), Canvas::W_BOLD, 'left', 1.0, 'middle');

        $rowY = $y + self::HEAD_H + self::LABEL_H;
        if ($walls === []) {
            $c->text('دیوار قابل‌توجهی پیدا نشد', $x + $w / 2, $rowY + self::ROW_H / 2, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'center', 1.0, 'middle');
            return;
        }

        foreach (array_values($walls) as $i => $wall) {
            $ry = $rowY + $i * self::ROW_H;
            $mid = $ry + self::ROW_H / 2;
            if ($i > 0) {
                $c->rect($x + $pad, $ry, $w - $pad * 2, 1, $t->c('line_soft'), 1.0);
            }

            $c->circle($right - 4, $mid, 4, $tone);
            $c->text($this->price((float) $wall['price']), $cols['price'], $mid, 14, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');
            $c->text($this->dnum(LiquidityProvider::formatBtc((float) $wall['qty'])), $cols['qty'], $mid, 12, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');
            $c->text($this->dnum(LiquidityProvider::formatUsd((float) $wall['usd'])), $cols['usd'], $mid, 12, $t->c('ink_dim'), Canvas::W_NUM, 'right', 1.0, 'num');

            $d = (float) $wall['distance_pct'];
            $c->text(
                ($d > 0 ? '+' : ($d < 0 ? '−' : '')) . $this->dnum(number_format(abs($d), 2)) . '%',
                $cols['dist'],
                $mid,
                12,
                $t->c('ink_dim'),
                Canvas::W_NUM,
                'right',
                1.0,
                'num'
            );

            $barW = $cols['dist'] - 62 - $cols['bar'];
            $c->roundRect($cols['bar'], $mid - 3, $barW, 6, 3, $t->c('line_soft'), 1.0);
            $c->roundRect($cols['bar'], $mid - 3, max(6.0, $barW * (float) ($wall['strength'] ?? 0)), 6, 3, $tone, 1.0);
        }
    }

    private function balance(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $share = max(0.0, min(1.0, (float) ($this->data['imbalance'] ?? 0.5)));
        $bidTotal = (float) ($this->data['bid_total'] ?? 0);
        $askTotal = (float) ($this->data['ask_total'] ?? 0);
        $range = (float) ($this->data['range_pct'] ?? 0);

        Frame::panel($c, $t, $x, $y, $w, $h, 16);

        $pad = 20.0;
        $mid = $y + $h / 2;
        $right = $x + $w - $pad;

        $c->text('توازن خرید و فروش', $right, $mid - 10, 12, $t->c('ink'), Canvas::W_BOLD, 'right', 1.0, 'middle');
        $c->text(
            'در محدوده‌ی ±' . $this->dnum(number_format($range, 1)) . '% از قیمت',
            $right,
            $mid + 12,
            9.5,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'right',
            1.0,
            'middle'
        );

        [$verdict, $vColor] = $share > 0.55
            ? ['فشار خرید', $t->c('up')]
            : ($share < 0.45 ? ['فشار فروش', $t->c('down')] : ['متعادل', $t->c('flat')]);
        $vw = $c->textWidth($verdict, 12, Canvas::W_BOLD) + 30;
        $c->roundRect($x + $pad, $mid - 14, $vw, 28, 9, $vColor, 1.0);
        $c->text($verdict, $x + $pad + $vw / 2, $mid, 12, '#FFFFFF', Canvas::W_BOLD, 'center', 1.0, 'ink');

        $barR = $right - 150;
        $barL = $x + $pad + $vw + 24;
        $barW = $barR - $barL;
        $barY = $mid + 5;
        $gap = 2.0;
        $bidW = max(6.0, ($barW - $gap) * $share);
        $c->roundRect($barR - $bidW, $barY, $bidW, 10, 4, $t->c('up'), 1.0);
        $c->roundRect($barL, $barY, max(6.0, $barW - $gap - $bidW), 10, 4, $t->c('down'), 1.0);

        $labelY = $barY - 13;
        $c->text(
            'خرید ' . $this->dnum(number_format($share * 100, 1)) . '% · ' . $this->dnum(LiquidityProvider::formatUsd($bidTotal)),
            $barR,
            $labelY,
            10.5,
            $t->c('ink'),
            Canvas::W_BOLD,
            'right',
            1.0,
            'middle'
        );
        $c->text(
            'فروش ' . $this->dnum(number_format((1 - $share) * 100, 1)) . '% · ' . $this->dnum(LiquidityProvider::formatUsd($askTotal)),
            $barL,
            $labelY,
            10.5,
            $t->c('ink'),
            Canvas::W_BOLD,
            'left',
            1.0,
            'middle'
        );
    }
}

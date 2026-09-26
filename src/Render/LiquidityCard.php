<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\LiquidityProvider;

final class LiquidityCard extends Card
{
    private const MAP_H     = 330.0;
    private const LIST_HEAD = 50.0;
    private const ROW_H     = 36.0;
    private const BALANCE_H = 64.0;
    private const GAP       = 16.0;
    private const GUTTER    = 86.0;

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
        $listH = self::LIST_HEAD + $rows * self::ROW_H + self::BALANCE_H;
        $height = (int) round(84 + Frame::HEADER_H + self::MAP_H + self::GAP + $listH);

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
        $this->walls($c, $t, $rect['x'], $top + self::MAP_H + self::GAP, $rect['w'], $listH, $rows);

        return $c;
    }

    private function price(float $p): string
    {
        $bucket = (float) ($this->data['bucket'] ?? 100);

        return $this->dnum(number_format($p, $bucket < 1 ? 2 : 0));
    }

    private function priceWidget(Canvas $c, Theme $t, float $x, float $y): void
    {
        $c->text('$' . $this->dnum(number_format((float) $this->data['price'], 1)), $x, $y + 14, 17, $t->c('ink'), Canvas::W_NUM_BOLD, 'left', 1.0, 'num');
        $c->text(
            'BTC / USDT · ' . implode(' + ', (array) ($this->data['sources'] ?? [])),
            $x,
            $y + 42,
            9.5,
            $t->c('ink_faint'),
            Canvas::W_NUM,
            'left',
            1.0,
            'num'
        );
    }

    private function map(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        Frame::panel($c, $t, $x, $y, $w, $h, 20);

        $pad = 24.0;
        $top = $y + $pad + 6;
        $bottom = $y + $h - $pad - 6;
        $chartL = $x + $pad;
        $chartR = $x + $w - $pad - self::GUTTER - 10;
        $gutterL = $chartR + 10;

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
        $padP = max(($hi - $lo) * 0.05, $price * 0.001);
        $lo -= $padP;
        $hi += $padP;
        $yOf = static fn (float $p): float => $bottom - ($p - $lo) / ($hi - $lo) * ($bottom - $top);

        $labels = [['y' => $yOf($price), 'text' => $this->dnum(number_format($price, 1)), 'color' => $t->c('line'), 'pill' => true]];
        foreach ([[$bidWalls, $t->c('up')], [$askWalls, $t->c('down')]] as [$walls, $color]) {
            foreach ($walls as $wall) {
                $wy = $yOf((float) $wall['price']);
                $c->rect($chartL, $wy - 4, $chartR - $chartL, 8, $color, 0.07);
                $c->rect($chartL, $wy - 0.7, $chartR - $chartL, 1.4, $color, 0.85);
                $labels[] = ['y' => $wy, 'text' => $this->price((float) $wall['price']), 'color' => $color, 'pill' => false];
            }
        }

        $this->candles($c, $t, $candles, $chartL, $chartR, $yOf);

        $py = $yOf($price);
        $c->dashedLine($chartL, $py, $chartR, $py, $t->c('line'), 1.2, 5, 4, 0.8);

        $this->labels($c, $t, $labels, $gutterL, $chartR, $top, $bottom);
    }

    private function candles(Canvas $c, Theme $t, array $candles, float $left, float $right, callable $yOf): void
    {
        $n = count($candles);
        if ($n === 0) {
            return;
        }
        $slot = ($right - $left) / $n;
        $bodyW = max(2.0, min(8.0, $slot * 0.56));
        $ink = $t->c('line');

        foreach (array_values($candles) as $i => $k) {
            $cx = $left + $slot * ($i + 0.5);
            $o = (float) $k['o'];
            $cl = (float) $k['c'];
            $c->line($cx, $yOf((float) $k['h']), $cx, $yOf((float) $k['l']), $ink, 1.0, 0.45);
            $yTop = $yOf(max($o, $cl));
            $bodyH = max(1.4, $yOf(min($o, $cl)) - $yTop);
            if ($cl >= $o) {
                $c->rect($cx - $bodyW / 2, $yTop, $bodyW, $bodyH, '#FFFFFF', 1.0);
                $c->strokeRoundRect($cx - $bodyW / 2, $yTop, $bodyW, $bodyH, 0.6, $ink, 1.0, 0.6);
            } else {
                $c->rect($cx - $bodyW / 2, $yTop, $bodyW, $bodyH, $ink, 0.6);
            }
        }
    }

    private function labels(Canvas $c, Theme $t, array $labels, float $left, float $chartR, float $top, float $bottom): void
    {
        $h = 20.0;
        $gap = 2.0;
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

        $w = self::GUTTER;
        foreach ($labels as $i => $label) {
            $ly = $pos[$i];
            if (abs($ly - $label['y']) > 0.5) {
                $c->line($chartR, $label['y'], $left, $ly, $label['color'], 1.0, 0.5);
            }
            if ($label['pill']) {
                $c->roundRect($left, $ly - $h / 2, $w, $h, 6, $label['color'], 1.0);
                $c->textFit($label['text'], $left + $w / 2, $ly, $w - 10, 11, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 8, 1.0, 'num');
            } else {
                $c->textFit($label['text'], $left + 6, $ly, $w - 6, 11.5, $label['color'], Canvas::W_NUM_BOLD, 'left', 8, 1.0, 'num');
            }
        }
    }

    private function walls(Canvas $c, Theme $t, float $x, float $y, float $w, float $h, int $rows): void
    {
        Frame::panel($c, $t, $x, $y, $w, $h, 20);

        $pad = 28.0;
        $mid = $x + $w / 2;
        $this->side($c, $t, 'حمایت‌ها', (array) ($this->data['bid_walls'] ?? []), $t->c('up'), $mid + $pad, $x + $w - $pad, $y);
        $this->side($c, $t, 'مقاومت‌ها', (array) ($this->data['ask_walls'] ?? []), $t->c('down'), $x + $pad, $mid - $pad, $y);
        $c->rect($mid, $y + 22, 1, self::LIST_HEAD + $rows * self::ROW_H - 30, $t->c('line'), 0.07);

        $share = max(0.0, min(1.0, (float) ($this->data['imbalance'] ?? 0.5)));
        $barY = $y + self::LIST_HEAD + $rows * self::ROW_H + 34;
        $barL = $x + $pad;
        $barR = $x + $w - $pad;
        $gap = 3.0;
        $bidW = max(6.0, ($barR - $barL - $gap) * $share);
        $c->roundRect($barR - $bidW, $barY, $bidW, 6, 3, $t->c('up'), 1.0);
        $c->roundRect($barL, $barY, max(6.0, $barR - $barL - $gap - $bidW), 6, 3, $t->c('down'), 1.0);

        $labelY = $barY - 14;
        $c->text('خرید ' . $this->dnum(number_format($share * 100, 1)) . '%', $barR, $labelY, 10.5, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');
        $c->text('فروش ' . $this->dnum(number_format((1 - $share) * 100, 1)) . '%', $barL, $labelY, 10.5, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'left', 1.0, 'middle');
    }

    private function side(Canvas $c, Theme $t, string $title, array $walls, string $color, float $left, float $right, float $y): void
    {
        $headMid = $y + self::LIST_HEAD / 2 + 2;
        $c->circle($right - 4, $headMid, 4, $color);
        $c->text($title, $right - 14, $headMid, 13, $t->c('ink'), Canvas::W_BLACK, 'right', 1.0, 'middle');

        if ($walls === []) {
            $c->text('سطح پرحجمی پیدا نشد', $right, $y + self::LIST_HEAD + self::ROW_H / 2, 10.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
            return;
        }

        foreach (array_values($walls) as $i => $wall) {
            $ry = $y + self::LIST_HEAD + $i * self::ROW_H;
            $rowMid = $ry + self::ROW_H / 2;
            $c->rect($left, $ry, $right - $left, 1, $t->c('line'), 0.06);
            $c->text($this->price((float) $wall['price']), $right, $rowMid, 13.5, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');
            $c->text($this->dnum(LiquidityProvider::formatBtc((float) $wall['qty'])), $left, $rowMid, 12, $t->c('ink_faint'), Canvas::W_NUM, 'left', 1.0, 'num');
        }
    }
}

<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\FuturesProvider;
use Nikto\Data\PriceProvider;

final class MoversCard extends Card
{
    private const ROW_H   = 68.0;
    private const HEAD_H  = 50.0;
    private const STRIP_H = 64.0;
    private const GAP     = 16.0;

    private array $data;
    private string $title;

    public function __construct(array $data, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->data = $data;
        $this->title = (string) ($options['title'] ?? 'برترین‌های فیوچرز بایننس');
    }

    public function render(): Canvas
    {
        $rows = max(1, count($this->data['gainers'] ?? []), count($this->data['losers'] ?? []));
        $columnH = self::HEAD_H + $rows * self::ROW_H + 10;
        $height = (int) round(84 + Frame::HEADER_H + self::STRIP_H + self::GAP + $columnH);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;
        CoinLogo::$lightSurface = true;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $source = (string) ($this->data['source'] ?? 'Binance Futures');
        $top = Frame::header($c, $t, $rect, $this->title, $this->dateLine(), $source . ' · 24h');

        $this->breadth($c, $t, $rect['x'], $top, $rect['w'], self::STRIP_H);

        $colY = $top + self::STRIP_H + self::GAP;
        $colW = ($rect['w'] - 18) / 2;
        $this->column($c, $t, 'بیشترین رشد', (array) ($this->data['gainers'] ?? []), true, $rect['x'] + $colW + 18, $colY, $colW, $columnH);
        $this->column($c, $t, 'بیشترین افت', (array) ($this->data['losers'] ?? []), false, $rect['x'], $colY, $colW, $columnH);

        return $c;
    }

    private function breadth(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $b = (array) ($this->data['breadth'] ?? []);
        $up = (int) ($b['up'] ?? 0);
        $down = (int) ($b['down'] ?? 0);
        $flat = (int) ($b['flat'] ?? 0);
        $total = max(1, (int) ($b['total'] ?? ($up + $down + $flat)));
        $avg = (float) ($b['avg'] ?? 0);

        Frame::panel($c, $t, $x, $y, $w, $h, 16);

        $pad = 20.0;
        $mid = $y + $h / 2;
        $right = $x + $w - $pad;

        $c->text('وضعیت کل بازار', $right, $mid - 10, 11.5, $t->c('ink'), Canvas::W_BOLD, 'right', 1.0, 'middle');
        $c->text(
            $this->tnum((string) $total) . ' قرارداد فعال',
            $right,
            $mid + 11,
            10,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'right',
            1.0,
            'middle'
        );

        $avgText = ($avg > 0 ? '+' : ($avg < 0 ? '−' : '')) . $this->dnum(number_format(abs($avg), 2)) . '%';
        $avgColor = $t->trend($avg);
        $chipW = $c->textWidth($avgText, 12, Canvas::W_NUM_BOLD) + 24;
        $c->text('میانگین تغییر', $x + $pad + $chipW + 10, $mid, 10.5, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'left', 1.0, 'middle');
        $c->roundRect($x + $pad, $mid - 12, $chipW, 24, 8, $avgColor, 1.0);
        $c->text($avgText, $x + $pad + $chipW / 2, $mid, 12, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 1.0, 'num');

        $barRight = $right - 150;
        $barLeft = $x + $pad + $chipW + 110;
        $barW = $barRight - $barLeft;
        $barH = 12.0;
        $barY = $mid + 2;

        $labelY = $barY - 13;
        $c->triangle($barRight - 5, $labelY, 8, true, $t->c('up'));
        $c->text($this->tnum((string) $up) . ' صعودی', $barRight - 15, $labelY, 10.5, $t->c('ink'), Canvas::W_BOLD, 'right', 1.0, 'middle');
        $c->triangle($barLeft + 5, $labelY, 8, false, $t->c('down'));
        $c->text($this->tnum((string) $down) . ' نزولی', $barLeft + 15, $labelY, 10.5, $t->c('ink'), Canvas::W_BOLD, 'left', 1.0, 'middle');

        $gap = 2.0;
        $segments = [[$up, $t->c('up')], [$flat, $t->c('line_soft')], [$down, $t->c('down')]];
        $visible = array_values(array_filter($segments, static fn (array $s): bool => $s[0] > 0));
        $usable = $barW - $gap * max(0, count($visible) - 1);
        $cursor = $barRight;
        foreach ($visible as [$n, $color]) {
            $segW = max(4.0, $usable * $n / $total);
            $c->roundRect($cursor - $segW, $barY, $segW, $barH, 4, $color, 1.0);
            $cursor -= $segW + $gap;
        }
    }

    private function column(Canvas $c, Theme $t, string $title, array $rows, bool $gainers, float $x, float $y, float $w, float $h): void
    {
        $tone = $gainers ? $t->c('up') : $t->c('down');

        Frame::panel($c, $t, $x, $y, $w, $h, 18);
        $c->roundRect($x + 18, $y - 0.5, $w - 36, 5, 2.5, $tone, 1.0);

        $pad = 18.0;
        $headMid = $y + self::HEAD_H / 2 + 2;
        $iconX = $x + $w - $pad - 13;
        $c->roundRect($iconX - 13, $headMid - 13, 26, 26, 8, $tone, 1.0);
        $c->triangle($iconX, $headMid, 10, $gainers, '#FFFFFF');
        $c->text($title, $iconX - 22, $headMid, 14, $t->c('ink'), Canvas::W_BLACK, 'right', 1.0, 'middle');
        $c->text('۲۴ ساعت اخیر', $x + $pad, $headMid, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');

        $maxPct = 0.0;
        foreach ($rows as $row) {
            $maxPct = max($maxPct, abs((float) $row['change_pct']));
        }

        $rowY = $y + self::HEAD_H;
        $c->rect($x + $pad, $rowY, $w - $pad * 2, 1.2, $t->c('line'), 0.5);

        if ($rows === []) {
            $c->text('موردی نیست', $x + $w / 2, $rowY + self::ROW_H / 2, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'center', 1.0, 'middle');
            return;
        }

        foreach (array_values($rows) as $i => $row) {
            $this->row($c, $t, $row, $i + 1, $x + 8, $rowY + $i * self::ROW_H, $w - 16, $maxPct, $i > 0);
        }
    }

    private function row(Canvas $c, Theme $t, array $row, int $rank, float $x, float $y, float $w, float $maxPct, bool $divider): void
    {
        $pct = (float) $row['change_pct'];
        $trend = $t->trend($pct);
        $mid = $y + self::ROW_H / 2;
        $right = $x + $w - 10;

        if ($divider) {
            $c->rect($x + 10, $y, $w - 20, 1, $t->c('line_soft'), 1.0);
        }

        if ($maxPct > 0) {
            $barW = ($w - 4) * min(1.0, abs($pct) / $maxPct);
            $c->roundRect($x + $w - 2 - $barW, $y + 5, $barW, self::ROW_H - 10, 10, $trend, 0.07);
        }

        $rankS = 26.0;
        $c->roundRect($right - $rankS, $mid - $rankS / 2, $rankS, $rankS, 8, $t->c('line'), 1.0);
        $c->text($this->dnum((string) $rank), $right - $rankS / 2, $mid, 12, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 1.0, 'num');

        $logoS = 36.0;
        $logoCx = $right - $rankS - 10 - $logoS / 2;
        CoinLogo::draw($c, (string) $row['logo'], $logoCx, $mid, $logoS, true);

        $textRight = $logoCx - $logoS / 2 - 10;
        $c->textFit((string) $row['symbol'], $textRight, $mid - 9, 112, 14.5, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 10, 1.0, 'num');
        $c->textFit(
            'حجم ' . $this->dnum(FuturesProvider::formatVolume((float) $row['quote_volume'])),
            $textRight,
            $mid + 12,
            112,
            9.5,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'right',
            8,
            1.0,
            'middle'
        );

        $pillText = ($pct > 0 ? '+' : ($pct < 0 ? '−' : '')) . $this->dnum(number_format(abs($pct), 2)) . '%';
        $pillW = max(92.0, $c->textWidth($pillText, 13, Canvas::W_NUM_BOLD) + 38);
        $pillH = 30.0;
        $pillX = $x + 10;
        $c->roundRect($pillX, $mid - $pillH / 2, $pillW, $pillH, 9, $trend, 1.0);
        if (abs($pct) >= 0.005) {
            $c->triangle($pillX + 15, $mid, 9, $pct > 0, '#FFFFFF');
        }
        $c->text($pillText, $pillX + $pillW - 12, $mid, 13, '#FFFFFF', Canvas::W_NUM_BOLD, 'right', 1.0, 'num');

        $sparkX = $pillX + $pillW + 14;
        $sparkW = 92.0;
        $spark = array_values(array_map('floatval', (array) ($row['spark'] ?? [])));
        if (count($spark) >= 4) {
            $this->sparkline($c, $spark, $sparkX, $mid - 15, $sparkW, 30, $trend);
        }

        $priceRight = $textRight - 124;
        $priceLeft = $sparkX + $sparkW + 12;
        $c->textFit(
            '$' . $this->dnum(PriceProvider::formatPrice((float) $row['price'])),
            $priceRight,
            $mid,
            max(60.0, $priceRight - $priceLeft),
            14,
            $t->c('ink'),
            Canvas::W_NUM_BOLD,
            'right',
            10,
            1.0,
            'num'
        );
    }

    private function sparkline(Canvas $c, array $values, float $x, float $y, float $w, float $h, string $color): void
    {
        $min = min($values);
        $max = max($values);
        $span = ($max - $min) ?: max(1e-9, abs($max) * 0.001);
        $n = count($values);

        $points = [];
        foreach ($values as $i => $v) {
            $points[] = [$x + $w * ($i / ($n - 1)), $y + $h - (($v - $min) / $span) * ($h - 4) - 2];
        }

        $c->areaGradient($points, $x, $y, $w, $h, $color, 0.20);
        $c->polyline($points, $color, 2.0, 1.0);
        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 3.6, '#FFFFFF');
        $c->circle($last[0], $last[1], 2.6, $color);
    }
}

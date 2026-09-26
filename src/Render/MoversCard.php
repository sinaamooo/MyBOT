<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\PriceProvider;

final class MoversCard extends Card
{
    private const ROW_H  = 56.0;
    private const HEAD_H = 52.0;

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
        $columnH = self::HEAD_H + $rows * self::ROW_H + 12;
        $height = (int) round(84 + Frame::HEADER_H + $columnH);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;
        CoinLogo::$lightSurface = true;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $source = (string) ($this->data['source'] ?? 'Binance Futures');
        $top = Frame::header($c, $t, $rect, $this->title, $this->dateLine(), $source . ' · 24h');

        $colW = ($rect['w'] - 16) / 2;
        $this->column($c, $t, 'بیشترین رشد', (array) ($this->data['gainers'] ?? []), true, $rect['x'] + $colW + 16, $top, $colW, $columnH);
        $this->column($c, $t, 'بیشترین افت', (array) ($this->data['losers'] ?? []), false, $rect['x'], $top, $colW, $columnH);

        return $c;
    }

    private function column(Canvas $c, Theme $t, string $title, array $rows, bool $gainers, float $x, float $y, float $w, float $h): void
    {
        Frame::panel($c, $t, $x, $y, $w, $h, 18);

        $pad = 22.0;
        $right = $x + $w - $pad;
        $headMid = $y + self::HEAD_H / 2 + 2;
        $c->triangle($right - 5, $headMid, 9, $gainers, $gainers ? $t->c('up') : $t->c('down'));
        $c->text($title, $right - 16, $headMid, 14, $t->c('ink'), Canvas::W_BLACK, 'right', 1.0, 'middle');

        $rowY = $y + self::HEAD_H;
        if ($rows === []) {
            $c->text('موردی نیست', $x + $w / 2, $rowY + self::ROW_H / 2, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'center', 1.0, 'middle');
            return;
        }

        foreach (array_values($rows) as $i => $row) {
            $ry = $rowY + $i * self::ROW_H;
            $c->rect($x + $pad, $ry, $w - $pad * 2, 1, $t->c('line'), 0.07);
            $this->row($c, $t, $row, $i + 1, $x + $pad, $ry, $w - $pad * 2);
        }
    }

    private function row(Canvas $c, Theme $t, array $row, int $rank, float $x, float $y, float $w): void
    {
        $pct = (float) $row['change_pct'];
        $mid = $y + self::ROW_H / 2;
        $right = $x + $w;

        $c->text($this->dnum((string) $rank), $right, $mid, 12, $t->c('ink_faint'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');

        $logoS = 28.0;
        $logoCx = $right - 30 - $logoS / 2;
        CoinLogo::draw($c, (string) ($row['logo'] ?? $row['symbol']), $logoCx, $mid, $logoS, false);

        $c->textFit((string) $row['symbol'], $logoCx - $logoS / 2 - 10, $mid, 130, 14, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 10, 1.0, 'num');

        $pctText = ($pct > 0 ? '+' : ($pct < 0 ? '−' : '')) . $this->dnum(number_format(abs($pct), 2)) . '%';
        $c->text($pctText, $x, $mid, 14, $t->trend($pct), Canvas::W_NUM_BOLD, 'left', 1.0, 'num');

        $c->textFit(
            '$' . $this->dnum(PriceProvider::formatPrice((float) $row['price'])),
            $x + 200,
            $mid,
            100,
            13,
            $t->c('ink_dim'),
            Canvas::W_NUM,
            'right',
            10,
            1.0,
            'num'
        );
    }
}

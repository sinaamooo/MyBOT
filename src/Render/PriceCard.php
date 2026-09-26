<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\PriceProvider;

final class PriceCard extends Card
{
    private const TILE_H = 172.0;
    private const GAP    = 16.0;

    private array $coins;
    private string $title;

    public function __construct(array $coins, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->coins = array_slice(array_values($coins), 0, 9);
        $this->title = (string) ($options['title'] ?? 'نوسان ۲۴ ساعته بازار');
    }

    public function render(): Canvas
    {
        $count = max(1, count($this->coins));
        $cols  = $count <= 4 ? min(2, $count) : 3;
        $rows  = (int) ceil($count / $cols);

        $height = (int) round(84 + Frame::HEADER_H + $rows * self::TILE_H + ($rows - 1) * self::GAP);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;
        CoinLogo::$lightSurface = true;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header($c, $t, $rect, $this->title, $this->dateLine());

        $tileW = ($rect['w'] - self::GAP * ($cols - 1)) / $cols;

        foreach ($this->coins as $i => $coin) {
            $col = $i % $cols;
            $row = (int) floor($i / $cols);
            $x = $rect['x'] + ($cols - 1 - $col) * ($tileW + self::GAP);
            $y = $top + $row * (self::TILE_H + self::GAP);
            $this->tile($c, $t, $coin, $x, $y, $tileW, self::TILE_H);
        }

        return $c;
    }

    private function tile(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, float $h): void
    {
        $pct   = (float) $coin['change_pct'];
        $trend = $t->trend($pct);

        Frame::panel($c, $t, $x, $y, $w, $h, 18);

        $pad   = 18.0;
        $left  = $x + $pad;
        $right = $x + $w - $pad;
        $inner = $w - $pad * 2;

        $logoSize = 32.0;
        $logoCx = $right - $logoSize / 2;
        $logoCy = $y + 34;
        CoinLogo::draw($c, (string) $coin['symbol'], $logoCx, $logoCy, $logoSize, false);

        $labelRight = $logoCx - $logoSize / 2 - 10;
        $c->text(strtoupper((string) $coin['symbol']), $labelRight, $logoCy - 10, 14.5, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');
        $c->textFit((string) $coin['name_fa'], $labelRight, $logoCy + 12, $w * 0.44, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 8.5, 1.0, 'middle');

        $this->change($c, $t, $pct, $left, $logoCy, $trend);

        $price = '$' . PriceProvider::formatPrice((float) $coin['price']);
        $c->textFit($this->dnum($price), $right, $y + 86, $inner, 28, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 16, 1.0, 'num');

        $spark = array_values(array_map('floatval', (array) ($coin['spark'] ?? [])));
        if (count($spark) >= 4) {
            $this->sparkline($c, $spark, $left, $y + 114, $inner, $h - 114 - 16, $trend);
        }
    }

    private function change(Canvas $c, Theme $t, float $pct, float $x, float $cy, string $color): void
    {
        if (abs($pct) < 0.005) {
            $c->rect($x, $cy - 1, 8, 2, $color);
        } else {
            $c->triangle($x + 4, $cy, 8, $pct > 0, $color);
        }
        $c->text($this->dnum(number_format(abs($pct), 2) . '%'), $x + 13, $cy, 13, $color, Canvas::W_NUM_BOLD, 'left', 1.0, 'num');
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

        $c->areaGradient($points, $x, $y, $w, $h, $color, 0.10);
        $c->polyline($points, $color, 1.8, 1.0);
    }
}

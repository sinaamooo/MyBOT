<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\PriceProvider;

/**
 * کارت نوسان ۲۴ ساعته — جعبه‌های شیشه‌ای با لوگوی هر ارز.
 */
final class PriceCard extends Card
{
    private const TILE_H = 193.0;
    private const GAP    = 16.0;

    /** @var array<int,array<string,mixed>> */
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

        $height = (int) round(76 + Frame::HEADER_H + $rows * self::TILE_H + ($rows - 1) * self::GAP);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header($c, $t, $rect, $this->title, $this->dateLine(), $this->marketBadge());

        $tileW = ($rect['w'] - self::GAP * ($cols - 1)) / $cols;

        foreach ($this->coins as $i => $coin) {
            $col = $i % $cols;
            $row = (int) floor($i / $cols);
            // چیدمان از راست به چپ
            $x = $rect['x'] + ($cols - 1 - $col) * ($tileW + self::GAP);
            $y = $top + $row * (self::TILE_H + self::GAP);
            $this->tile($c, $t, $coin, $x, $y, $tileW, self::TILE_H);
        }

        return $c;
    }

    private function marketBadge(): string
    {
        $up = 0;
        foreach ($this->coins as $coin) {
            if ((float) $coin['change_pct'] > 0) {
                $up++;
            }
        }

        return $this->dnum($up . '/' . count($this->coins)) . ' صعودی';
    }

    private function tile(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, float $h): void
    {
        $pct    = (float) $coin['change_pct'];
        $isUp   = $pct > 0;
        $isFlat = abs($pct) < 0.005;
        $trend  = $t->trend($pct);

        $c->glass($x, $y, $w, $h, 18, ['fill' => 0.065, 'border' => 0.14, 'tint' => $t->c('tint')]);

        $pad   = 16.0;
        $left  = $x + $pad;
        $right = $x + $w - $pad;

        // ── ردیف نماد و لوگو
        $logoSize = 30.0;
        $logoCx = $right - $logoSize / 2;
        $logoCy = $y + $pad + $logoSize / 2;
        CoinLogo::draw($c, (string) $coin['symbol'], $logoCx, $logoCy, $logoSize);

        $symbolRight = $logoCx - $logoSize / 2 - 9;
        $c->text(
            strtoupper((string) $coin['symbol']),
            $symbolRight,
            $logoCy - 7,
            13.5,
            $t->c('ink'),
            Canvas::W_BOLD,
            'right',
            1.0,
            'middle'
        );
        $c->textFit(
            (string) $coin['name_fa'],
            $symbolRight,
            $logoCy + 9,
            $w * 0.44,
            9.5,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'right',
            8,
            1.0,
            'middle'
        );

        // ── قیمت
        $price = '$' . PriceProvider::formatPrice((float) $coin['price']);
        $c->textFit(
            $this->dnum($price),
            $right,
            $y + $h * 0.385,
            $w - $pad * 2,
            22,
            $t->c('ink'),
            Canvas::W_BLACK,
            'right',
            13,
            1.0,
            'middle'
        );

        // ── درصد تغییر و مقدار تغییر (بدون هم‌پوشانی)
        $pctText = $this->dnum(number_format(abs($pct), 2) . '%');
        $pillH = 22.0;
        $pillY = $y + $h * 0.505;
        $pctW  = $c->textWidth($pctText, 11.5, Canvas::W_BOLD);
        $pillW = $pctW + 30;
        $pillX = $right - $pillW;

        $c->roundRect($pillX, $pillY, $pillW, $pillH, 7, $trend, $t->isMono() ? 0.13 : 0.16);
        $c->strokeRoundRect($pillX, $pillY, $pillW, $pillH, 7, $trend, 1, 0.30);
        if ($isFlat) {
            $c->rect($pillX + 9, $pillY + $pillH / 2 - 1, 8, 2, $trend);
        } else {
            $c->triangle($pillX + 13, $pillY + $pillH / 2, 8, $isUp, $trend);
        }
        $c->text($pctText, $right - 9, $pillY + $pillH / 2, 11.5, $trend, Canvas::W_BOLD, 'right', 1.0, 'middle');

        $absText = '(' . $this->dnum(PriceProvider::formatChange((float) $coin['change_abs'])) . ')';
        $c->textFit(
            $absText,
            $pillX - 9,
            $pillY + $pillH / 2,
            max(40.0, $pillX - $left - 12),
            9.5,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'right',
            8,
            1.0,
            'middle'
        );

        // ── نمودار ۲۴ ساعته
        $chartY = $y + $h * 0.72;
        $chartH = $y + $h - $pad - $chartY;
        $spark = array_values(array_map('floatval', (array) ($coin['spark'] ?? [])));
        if (count($spark) >= 4 && $chartH > 14) {
            $this->sparkline($c, $spark, $left, $chartY, $w - $pad * 2, $chartH, $trend);
        } else {
            $c->dashedLine($left, $chartY + $chartH / 2, $right, $chartY + $chartH / 2, $t->c('ink_faint'), 1, 5, 4, 0.5);
        }
    }

    private function sparkline(Canvas $c, array $values, float $x, float $y, float $w, float $h, string $color): void
    {
        $min = min($values);
        $max = max($values);
        $span = ($max - $min) ?: max(1e-9, abs($max) * 0.001);
        $n = count($values);

        $points = [];
        foreach ($values as $i => $v) {
            $points[] = [
                $x + $w * ($i / ($n - 1)),
                $y + $h - (($v - $min) / $span) * ($h - 4) - 2,
            ];
        }

        $area = $points;
        $area[] = [$x + $w, $y + $h];
        $area[] = [$x, $y + $h];
        $c->polygon($area, $color, 0.11);
        $c->polyline($points, $color, 1.7, 0.95);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 2.9, $color);
        $c->circle($last[0], $last[1], 1.4, '#0B0C10');
    }
}

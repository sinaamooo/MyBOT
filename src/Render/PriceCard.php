<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\PriceProvider;

/**
 * کارت نوسان ۲۴ ساعته — کاشی‌های شیشه‌ای با هاله‌ی رنگی، نوار دامنه و نمودار گرادیانی.
 */
final class PriceCard extends Card
{
    private const TILE_H = 202.0;
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
        $top = Frame::header(
            $c,
            $t,
            $rect,
            $this->title,
            $this->dateLine(),
            '',
            fn (float $x, float $y) => $this->pulse($c, $t, $x, $y)
        );

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

    /** نوار نبض بازار در سربرگ: هر ارز یک قطعه */
    private function pulse(Canvas $c, Theme $t, float $x, float $y): void
    {
        $count = max(1, count($this->coins));
        $up = 0;
        foreach ($this->coins as $coin) {
            if ((float) $coin['change_pct'] > 0) {
                $up++;
            }
        }

        $segW = 15.0;
        $gap = 5.0;
        $barY = $y + 4;

        foreach ($this->coins as $i => $coin) {
            $pct = (float) $coin['change_pct'];
            $c->roundRect($x + $i * ($segW + $gap), $barY, $segW, 6, 3, $t->trend($pct), 0.95);
        }

        $c->text(
            $this->dnum($up . '/' . $count) . ' صعودی',
            $x,
            $barY + 24,
            11,
            $t->c('ink_dim'),
            Canvas::W_MEDIUM,
            'left',
            1.0,
            'middle'
        );
    }

    private function tile(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, float $h): void
    {
        $pct    = (float) $coin['change_pct'];
        $isUp   = $pct > 0;
        $isFlat = abs($pct) < 0.005;
        $trend  = $t->trend($pct);

        $c->glass($x, $y, $w, $h, 20, ['fill' => 0.055, 'border' => 0.11, 'tint' => $t->c('tint')]);

        $pad   = 18.0;
        $left  = $x + $pad;
        $right = $x + $w - $pad;
        $inner = $w - $pad * 2;

        // ── لوگو با هاله‌ی رنگی روند
        $logoSize = 34.0;
        $logoCx = $right - $logoSize / 2;
        $logoCy = $y + $pad + $logoSize / 2 - 2;
        $c->radialGlow($logoCx, $logoCy, $logoSize * 1.15, $trend, 0.28);
        CoinLogo::draw($c, (string) $coin['symbol'], $logoCx, $logoCy, $logoSize, false);
        $c->ring($logoCx, $logoCy, $logoSize / 2 + 3, 1.2, $trend, 0, 360, 0.55);

        // ── نماد و نام
        $labelRight = $logoCx - $logoSize / 2 - 11;
        $c->text(
            strtoupper((string) $coin['symbol']),
            $labelRight,
            $logoCy - 8,
            15,
            $t->c('ink'),
            Canvas::W_NUM_BOLD,
            'right',
            1.0,
            'ink'
        );
        $c->textFit(
            (string) $coin['name_fa'],
            $labelRight,
            $logoCy + 10,
            $w * 0.42,
            9.5,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'right',
            8,
            1.0,
            'ink'
        );

        // ── قیمت و تغییر
        $priceY = $y + $h * 0.455;
        $price = '$' . PriceProvider::formatPrice((float) $coin['price']);
        $c->textFit($this->dnum($price), $right, $priceY, $inner * 0.72, 27, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 15, 1.0, 'ink');

        $pctText = $this->dnum(number_format(abs($pct), 2) . '%');
        $chipH = 23.0;
        $chipW = $c->textWidth($pctText, 12.5, Canvas::W_NUM_BOLD) + 31;
        $chipY = $priceY - $chipH / 2;

        $c->roundRect($left, $chipY, $chipW, $chipH, $chipH / 2, $trend, 0.16);
        $c->strokeRoundRect($left, $chipY, $chipW, $chipH, $chipH / 2, $trend, 1, 0.38);
        if ($isFlat) {
            $c->rect($left + 10, $priceY - 1, 8, 2, $trend);
        } else {
            $c->triangle($left + 14, $priceY, 8, $isUp, $trend);
        }
        $c->text($pctText, $left + $chipW - 10, $priceY, 12.5, $trend, Canvas::W_NUM_BOLD, 'right', 1.0, 'ink');

        // ── نوار دامنه‌ی ۲۴ ساعته
        $this->rangeBar($c, $t, $coin, $left, $y + $h * 0.605, $inner, $trend);

        // ── نمودار
        $chartY = $y + $h * 0.70;
        $chartH = $y + $h - $pad + 4 - $chartY;
        $spark = array_values(array_map('floatval', (array) ($coin['spark'] ?? [])));
        if (count($spark) >= 4 && $chartH > 16) {
            $this->sparkline($c, $spark, $left - 4, $chartY, $inner + 8, $chartH, $trend);
        }
    }

    /** جای قیمت فعلی بین کمترین و بیشترین ۲۴ ساعت */
    private function rangeBar(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, string $trend): void
    {
        $low = (float) ($coin['low'] ?? 0);
        $high = (float) ($coin['high'] ?? 0);
        $price = (float) $coin['price'];
        if ($high <= $low) {
            return;
        }
        $ratio = max(0.02, min(0.98, ($price - $low) / ($high - $low)));

        $c->roundRect($x, $y, $w, 4, 2, '#FFFFFF', 0.09);
        $c->roundRect($x, $y, $w * $ratio, 4, 2, $trend, 0.55);
        $c->circle($x + $w * $ratio, $y + 2, 4.2, $trend);
        $c->circle($x + $w * $ratio, $y + 2, 1.8, $t->c('bg_from'));

        $c->text('L', $x, $y + 15, 8.5, $t->c('ink_faint'), Canvas::W_NUM, 'left', 0.8, 'ink');
        $c->text('H', $x + $w, $y + 15, 8.5, $t->c('ink_faint'), Canvas::W_NUM, 'right', 0.8, 'ink');
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
                $y + $h - (($v - $min) / $span) * ($h - 5) - 2.5,
            ];
        }

        $c->areaGradient($points, $x, $y, $w, $h, $color, 0.30);
        $c->polyline($points, $color, 1.9, 0.98);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 3.2, $color);
        $c->circle($last[0], $last[1], 1.4, '#04070A');
    }
}

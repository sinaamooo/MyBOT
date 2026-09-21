<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\PriceProvider;

/**
 * کارت نوسان ۲۴ ساعته — کاشی‌های سفید با نوار رنگی بالا، پلاک درصد توپر و نمودار.
 */
final class PriceCard extends Card
{
    private const TILE_H = 222.0;
    private const GAP    = 18.0;

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

        $height = (int) round(84 + Frame::HEADER_H + $rows * self::TILE_H + ($rows - 1) * self::GAP);

        $c = new Canvas(1200, $height, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;
        CoinLogo::$lightSurface = true;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header(
            $c,
            $t,
            $rect,
            $this->title,
            $this->dateLine(),
            '',
            fn (float $x, float $y) => $this->score($c, $t, $x, $y)
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

    /** شمارنده‌ی صعودی و نزولی در سربرگ */
    private function score(Canvas $c, Theme $t, float $x, float $y): void
    {
        $up = 0;
        $down = 0;
        foreach ($this->coins as $coin) {
            $pct = (float) $coin['change_pct'];
            if ($pct > 0.005) {
                $up++;
            } elseif ($pct < -0.005) {
                $down++;
            }
        }

        $cursor = $x;
        foreach ([[$up, 'صعودی', $t->c('up'), true], [$down, 'نزولی', $t->c('down'), false]] as [$n, $label, $color, $isUp]) {
            $text = $this->dnum((string) $n);
            $boxW = 34.0;
            $boxH = 26.0;

            $c->roundRect($cursor, $y + 2, $boxW, $boxH, 8, $color, 1.0);
            $c->text($text, $cursor + $boxW / 2, $y + 2 + $boxH / 2, 13, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 1.0, 'ink');
            $c->triangle($cursor + $boxW + 16, $y + 2 + $boxH / 2, 8, $isUp, $color);

            $c->text($label, $cursor + $boxW + 27, $y + 2 + $boxH / 2, 11, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'left', 1.0, 'ink');
            $cursor += $boxW + 30 + $c->textWidth($label, 11, Canvas::W_SEMIBOLD) + 18;
        }

        $c->text(
            'از ' . $this->dnum((string) count($this->coins)) . ' ارز',
            $x,
            $y + 46,
            10.5,
            $t->c('ink_faint'),
            Canvas::W_MEDIUM,
            'left',
            1.0,
            'ink'
        );
    }

    private function tile(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, float $h): void
    {
        $pct    = (float) $coin['change_pct'];
        $isUp   = $pct > 0;
        $isFlat = abs($pct) < 0.005;
        $trend  = $t->trend($pct);
        $radius = 18.0;

        Frame::panel($c, $t, $x, $y, $w, $h, $radius, ['border_alpha' => 0.14]);

        // نوار رنگی روند روی لبه‌ی بالا
        $c->roundRect($x + $radius * 0.55, $y - 0.5, $w - $radius * 1.1, 5, 2.5, $trend, 1.0);

        $pad   = 18.0;
        $left  = $x + $pad;
        $right = $x + $w - $pad;
        $inner = $w - $pad * 2;

        // ── لوگو و نماد
        $logoSize = 38.0;
        $logoCx = $right - $logoSize / 2;
        $logoCy = $y + 20 + $logoSize / 2;
        CoinLogo::draw($c, (string) $coin['symbol'], $logoCx, $logoCy, $logoSize, true);

        $labelRight = $logoCx - $logoSize / 2 - 12;
        $c->text(
            strtoupper((string) $coin['symbol']),
            $labelRight,
            $logoCy - 9,
            15.5,
            $t->c('ink'),
            Canvas::W_NUM_BOLD,
            'right',
            1.0,
            'ink'
        );
        $c->textFit(
            (string) $coin['name_fa'],
            $labelRight,
            $logoCy + 11,
            $w * 0.44,
            10.5,
            $t->c('ink_dim'),
            Canvas::W_MEDIUM,
            'right',
            8.5,
            1.0,
            'ink'
        );

        // ── پلاک درصد (توپر، متن سفید)
        $pctText = $this->dnum(number_format(abs($pct), 2) . '%');
        $chipH = 28.0;
        $chipW = $c->textWidth($pctText, 13, Canvas::W_NUM_BOLD) + 38;
        $chipCy = $logoCy;
        $c->roundRect($left, $chipCy - $chipH / 2, $chipW, $chipH, 9, $trend, 1.0);
        if ($isFlat) {
            $c->rect($left + 12, $chipCy - 1.2, 9, 2.4, '#FFFFFF');
        } else {
            $c->triangle($left + 16, $chipCy, 9, $isUp, '#FFFFFF');
        }
        $c->text($pctText, $left + $chipW - 12, $chipCy, 13, '#FFFFFF', Canvas::W_NUM_BOLD, 'right', 1.0, 'ink');

        // ── قیمت
        $priceY = $y + 96;
        $price = '$' . PriceProvider::formatPrice((float) $coin['price']);
        $c->textFit($this->dnum($price), $right, $priceY, $inner * 0.80, 30, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 16, 1.0, 'ink');

        $c->text('قیمت لحظه‌ای', $left, $priceY, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'ink');

        // ── خط جداکننده
        $c->rect($left, $y + 118, $inner, 1, $t->c('line_soft'), 1.0);

        // ── کمترین و بیشترین ۲۴ ساعت
        $this->range($c, $t, $coin, $left, $y + 130, $inner, $trend);

        // ── نمودار
        $chartY = $y + 160;
        $chartH = $y + $h - 14 - $chartY;
        $spark = array_values(array_map('floatval', (array) ($coin['spark'] ?? [])));
        if (count($spark) >= 4 && $chartH > 20) {
            $c->roundRect($left - 4, $chartY, $inner + 8, $chartH, 10, $t->c('surface_alt'), 1.0);
            $this->sparkline($c, $t, $spark, $left - 2, $chartY + 4, $inner + 4, $chartH - 8, $trend);
        }
    }

    /** ردیف کمترین/بیشترین با مقدار واقعی و نوار جایگاه قیمت */
    private function range(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, string $trend): void
    {
        $low = (float) ($coin['low'] ?? 0);
        $high = (float) ($coin['high'] ?? 0);
        $price = (float) $coin['price'];

        $c->text('بیشترین', $x + $w, $y, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'top');
        $c->text('کمترین', $x, $y, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'top');

        $valueY = $y + 13;
        $c->textFit(
            $this->dnum(PriceProvider::formatPrice($high)),
            $x + $w,
            $valueY,
            $w * 0.42,
            11,
            $t->c('up'),
            Canvas::W_NUM_BOLD,
            'right',
            8.5,
            1.0,
            'top'
        );
        $c->textFit(
            $this->dnum(PriceProvider::formatPrice($low)),
            $x,
            $valueY,
            $w * 0.42,
            11,
            $t->c('down'),
            Canvas::W_NUM_BOLD,
            'left',
            8.5,
            1.0,
            'top'
        );

        if ($high <= $low) {
            return;
        }

        // نوار جایگاه قیمت بین کف و سقف
        $barY = $y + 1;
        $barX = $x + $w * 0.32;
        $barW = $w * 0.36;
        $ratio = max(0.02, min(0.98, ($price - $low) / ($high - $low)));

        $c->roundRect($barX, $barY, $barW, 5, 2.5, $t->c('line_soft'), 1.0);
        $c->roundRect($barX, $barY, $barW * $ratio, 5, 2.5, $trend, 1.0);
        $c->circle($barX + $barW * $ratio, $barY + 2.5, 4.6, $t->c('line'));
        $c->circle($barX + $barW * $ratio, $barY + 2.5, 2.8, '#FFFFFF');
    }

    private function sparkline(Canvas $c, Theme $t, array $values, float $x, float $y, float $w, float $h, string $color): void
    {
        $min = min($values);
        $max = max($values);
        $span = ($max - $min) ?: max(1e-9, abs($max) * 0.001);
        $n = count($values);

        $points = [];
        foreach ($values as $i => $v) {
            $points[] = [
                $x + $w * ($i / ($n - 1)),
                $y + $h - (($v - $min) / $span) * ($h - 8) - 4,
            ];
        }

        // خط پایه‌ی کم‌رنگ
        $c->dashedLine($x, $y + $h / 2, $x + $w, $y + $h / 2, $t->c('line_soft'), 1, 5, 5, 0.9);

        $c->areaGradient($points, $x, $y, $w, $h, $color, 0.22);
        $c->polyline($points, $color, 2.1, 1.0);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 4.0, '#FFFFFF');
        $c->circle($last[0], $last[1], 3.0, $color);
    }
}

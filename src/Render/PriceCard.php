<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\PriceProvider;

/**
 * کارت نوسان ۲۴ ساعته‌ی ارزها (شبکه‌ی ۳×۲).
 */
final class PriceCard extends Card
{
    /** @var array<int,array<string,mixed>> */
    private array $coins;
    private string $title;

    /** @param array<int,array<string,mixed>> $coins */
    public function __construct(array $coins, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->coins = array_slice(array_values($coins), 0, 9);
        $this->title = (string) ($options['title'] ?? 'نوسان ۲۴ ساعته بازار');
    }

    public function render(): Canvas
    {
        $count = max(1, count($this->coins));
        $cols  = $count <= 4 ? 2 : 3;
        $rows  = (int) ceil($count / $cols);

        $width  = 1200;
        $height = $rows >= 3 ? 860 : ($rows === 2 ? 675 : 500);

        $c = new Canvas($width, $height, $this->quality);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, [
            'brand'  => $this->brand,
            'footer' => $this->footer,
        ]);

        $top = Frame::panelHeader(
            $c,
            $t,
            $rect,
            $this->title,
            $this->dateLine(),
            $this->marketBadge()
        );

        $padX = 30.0;
        $gap  = 18.0;
        $areaW = $rect['w'] - $padX * 2;
        $areaH = $rect['y'] + $rect['h'] - $top - 26;
        $tileW = ($areaW - $gap * ($cols - 1)) / $cols;
        $tileH = ($areaH - $gap * ($rows - 1)) / $rows;

        foreach ($this->coins as $i => $coin) {
            $col = $i % $cols;
            $row = (int) floor($i / $cols);
            // چیدمان از راست به چپ
            $x = $rect['x'] + $padX + ($cols - 1 - $col) * ($tileW + $gap);
            $y = $top + $row * ($tileH + $gap);
            $this->tile($c, $t, $coin, $x, $y, $tileW, $tileH);
        }

        return $c;
    }

    /** برچسب خلاصه‌ی بازار در گوشه‌ی چپ سربرگ */
    private function marketBadge(): string
    {
        $up = 0;
        foreach ($this->coins as $coin) {
            if ((float) $coin['change_pct'] >= 0) {
                $up++;
            }
        }
        $total = max(1, count($this->coins));

        return $this->dnum($up . '/' . $total) . ' صعودی';
    }

    private function tile(Canvas $c, Theme $t, array $coin, float $x, float $y, float $w, float $h): void
    {
        $pct     = (float) $coin['change_pct'];
        $isUp    = $pct > 0;
        $isFlat  = abs($pct) < 0.005;
        $trend   = $isFlat ? $t->c('flat') : ($isUp ? $t->c('up') : $t->c('down'));
        $brand   = (string) $coin['color'];

        // بدنه‌ی کارت
        $c->shadow($x, $y, $w, $h, 20, '#243154', 0.16, 14, 5);
        $c->roundRect($x, $y, $w, $h, 20, '#FFFFFF');
        $c->gradient($x + 2, $y + $h * 0.45, $w - 4, $h * 0.55 - 2, '#FFFFFF', '#F3F6FC', 'v', 0, 0.9);
        $c->strokeRoundRect($x, $y, $w, $h, 20, $t->c('line'), 1.2, 0.9);
        // نوار رنگی ارز در لبه‌ی راست
        $c->roundRect($x + $w - 7, $y + 16, 5, $h - 32, 3, $brand, 0.95);

        $padX  = 20.0;
        $left  = $x + $padX;
        $right = $x + $w - $padX - 10;
        $inner = $w - $padX * 2 - 10;

        // چیدمان عمودی متناسب با ارتفاع کارت
        $symY  = $y + $h * 0.075;
        $priceY = $y + $h * 0.245;
        $pillY  = $y + $h * 0.50;
        $pillH  = min(34.0, $h * 0.185);
        $chartY = $pillY + $pillH + $h * 0.045;
        $chartH = max(26.0, $y + $h - $chartY - $h * 0.085);

        // ردیف نماد
        $dotR = 8.5;
        $c->circle($right - $dotR, $symY + 12, $dotR + 4.5, $brand, 0.16);
        $c->circle($right - $dotR, $symY + 12, $dotR, $brand);
        $c->text(
            strtoupper((string) $coin['symbol']),
            $right - $dotR * 2 - 10,
            $symY,
            23,
            $t->c('ink'),
            Canvas::W_BLACK,
            'right'
        );
        $c->textFit(
            (string) $coin['name_fa'],
            $left,
            $symY + 4,
            $w * 0.40,
            15,
            $t->c('ink_soft'),
            Canvas::W_MEDIUM,
            'left',
            11
        );

        // قیمت
        $price = '$' . PriceProvider::formatPrice((float) $coin['price']);
        $c->textFit($this->dnum($price), $right, $priceY, $inner, 37, $t->c('value'), Canvas::W_BLACK, 'right', 19);

        // درصد تغییر
        $pctText = $this->dnum(number_format(abs($pct), 2) . '%');
        $absText = $this->dnum(PriceProvider::formatChange((float) $coin['change_abs']));
        $pctW  = $c->textWidth($pctText, 19, Canvas::W_BOLD);
        $pillW = $pctW + 52;

        $c->roundRect($right - $pillW, $pillY, $pillW, $pillH, 10, $trend, 0.13);
        if (!$isFlat) {
            $c->arrow($right - $pillW + 20, $pillY + $pillH / 2, 15, $isUp, $trend);
        } else {
            $c->rect($right - $pillW + 12, $pillY + $pillH / 2 - 1.5, 16, 3, $trend);
        }
        $c->text($pctText, $right - 14, $pillY + ($pillH - 22) / 2, 19, $trend, Canvas::W_BOLD, 'right');

        $c->textFit(
            '(' . $absText . ')',
            $right - $pillW - 12,
            $pillY + ($pillH - 19) / 2,
            $inner - $pillW - 16,
            15,
            $t->c('ink_soft'),
            Canvas::W_MEDIUM,
            'right',
            10
        );

        // نمودار کوچک
        $c->rect($left - 4, $chartY - $h * 0.035, $inner + 8, 1, $t->c('line'), 0.75);
        $spark = array_values(array_filter(array_map('floatval', (array) ($coin['spark'] ?? []))));
        if (count($spark) >= 4) {
            $this->sparkline($c, $spark, $left - 6, $chartY, $w - $padX * 2 - 4, $chartH, $trend);
        } else {
            $c->dashedLine($left, $chartY + $chartH / 2, $right, $chartY + $chartH / 2, $t->c('line'), 2, 8, 6);
        }
    }

    /** نمودار خطی کوچک با سایه‌ی گرادیانی */
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
                $y + $h - (($v - $min) / $span) * ($h - 6) - 3,
            ];
        }

        // ناحیه‌ی زیر نمودار
        $area = $points;
        $area[] = [$x + $w, $y + $h];
        $area[] = [$x, $y + $h];
        $c->polygon($area, $color, 0.13);

        $c->polyline($points, $color, 2.6);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 5.2, '#FFFFFF');
        $c->circle($last[0], $last[1], 3.6, $color);
    }
}

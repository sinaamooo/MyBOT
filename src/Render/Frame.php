<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * قاب و پس‌زمینه‌ی کارت‌ها: تیره، مینیمال و شیشه‌ای.
 */
final class Frame
{
    /** ارتفاع سربرگ کارت (عنوان + تاریخ + خط جداکننده) */
    public const HEADER_H = 74.0;

    /**
     * پس‌زمینه و قاب را می‌کشد و ناحیه‌ی محتوا را برمی‌گرداند.
     *
     * @param array{footer?:string,margin?:float} $options
     * @return array{x:float,y:float,w:float,h:float}
     */
    public static function draw(Canvas $c, Theme $t, array $options = []): array
    {
        $margin = (float) ($options['margin'] ?? 18);
        $footer = trim((string) ($options['footer'] ?? ''));

        self::background($c, $t);
        self::border($c, $t, $margin);

        $pad = $margin + 20;
        $bottom = $pad + ($footer !== '' ? 22 : 0);

        if ($footer !== '') {
            $c->text(
                $footer,
                $c->width() / 2,
                $c->height() - $margin - 26,
                10.5,
                $t->c('ink_faint'),
                Canvas::W_MEDIUM,
                'center',
                0.9,
                false,
                2.0
            );
        }

        return [
            'x' => $pad,
            'y' => $pad,
            'w' => $c->width() - $pad * 2,
            'h' => $c->height() - $pad - $bottom,
        ];
    }

    private static function background(Canvas $c, Theme $t): void
    {
        $w = $c->width();
        $h = $c->height();

        $c->gradient(0, 0, $w, $h, $t->c('bg_from'), $t->c('bg_to'), 'v');

        // درخشش‌های بسیار ملایم
        $c->radialGlow($w * 0.16, -$h * 0.05, $w * 0.46, $t->c('glow'), $t->isMono() ? 0.10 : 0.20);
        $c->radialGlow($w * 0.92, $h * 1.02, $w * 0.40, $t->c('glow_alt'), $t->isMono() ? 0.08 : 0.16);

        self::dotGrid($c, $t);

        // باریکه‌ی نور مورب
        $c->polygon([
            [$w * 0.58, 0], [$w * 0.74, 0], [$w * 0.34, $h], [$w * 0.18, $h],
        ], '#FFFFFF', 0.012);

        self::vignette($c);
    }

    /** شبکه‌ی نقطه‌چین محو در پس‌زمینه */
    private static function dotGrid(Canvas $c, Theme $t): void
    {
        $step = 30;
        $color = $t->c('tint');
        for ($y = $step; $y < $c->height(); $y += $step) {
            for ($x = $step; $x < $c->width(); $x += $step) {
                $c->rect($x, $y, 1.2, 1.2, $color, 0.045);
            }
        }
    }

    private static function vignette(Canvas $c): void
    {
        $w = $c->width();
        $h = $c->height();

        $band = (int) ($h * 0.26);
        for ($i = 0; $i < $band; $i += 2) {
            $a = (1 - $i / $band) ** 2;
            $c->rect(0, $i, $w, 2, '#000000', $a * 0.30);
            $c->rect(0, $h - $i - 2, $w, 2, '#000000', $a * 0.38);
        }
        $bandX = (int) ($w * 0.16);
        for ($i = 0; $i < $bandX; $i += 2) {
            $a = (1 - $i / $bandX) ** 2;
            $c->rect($i, 0, 2, $h, '#000000', $a * 0.32);
            $c->rect($w - $i - 2, 0, 2, $h, '#000000', $a * 0.32);
        }
    }

    private static function border(Canvas $c, Theme $t, float $margin): void
    {
        $w = $c->width();
        $h = $c->height();
        $bw = $w - $margin * 2;
        $bh = $h - $margin * 2;

        $c->strokeRoundRect($margin, $margin, $bw, $bh, 22, '#FFFFFF', 1, 0.10);

        // گوشه‌های ظریف
        $len = 26.0;
        $col = $t->c('accent');
        foreach ([[0, 0, 1, 1], [1, 0, -1, 1], [0, 1, 1, -1], [1, 1, -1, -1]] as [$ix, $iy, $sx, $sy]) {
            $x = $margin + $ix * $bw;
            $y = $margin + $iy * $bh;
            $c->line($x, $y, $x + $sx * $len, $y, $col, 1.4, 0.55);
            $c->line($x, $y, $x, $y + $sy * $len, $col, 1.4, 0.55);
        }
    }

    /**
     * سربرگ کوچک کارت — عنوان راست، تاریخ زیرش، برچسب سمت چپ.
     * مقدار بازگشتی: y شروع محتوا.
     */
    public static function header(
        Canvas $c,
        Theme $t,
        array $rect,
        string $title,
        string $subtitle = '',
        string $badge = ''
    ): float {
        $x = $rect['x'];
        $w = $rect['w'];
        $y = $rect['y'];

        $c->text($title, $x + $w, $y, 17, $t->c('ink'), Canvas::W_BOLD, 'right');
        if ($subtitle !== '') {
            $c->text($subtitle, $x + $w, $y + 34, 10.5, $t->c('ink_dim'), Canvas::W_MEDIUM, 'right');
        }

        if ($badge !== '') {
            $bw = $c->textWidth($badge, 10, Canvas::W_SEMIBOLD) + 22;
            $c->glass($x, $y + 2, $bw, 24, 8, ['fill' => 0.07, 'border' => 0.12, 'blur' => 2, 'shadow' => false]);
            $c->text($badge, $x + $bw / 2, $y + 8, 10, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'center');
        }

        $lineY = $y + ($subtitle !== '' ? 58 : 34);
        $c->rect($x, $lineY, $w, 1, '#FFFFFF', 0.08);
        $c->rect($x + $w - 54, $lineY, 54, 1.6, $t->c('accent'), 0.75);

        return $lineY + 16;
    }
}

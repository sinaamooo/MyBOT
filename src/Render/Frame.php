<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * پس‌زمینه و قاب کارت‌ها.
 */
final class Frame
{
    public const HEADER_H = 76.0;

    /**
     * @param array{footer?:string,margin?:float} $options
     * @return array{x:float,y:float,w:float,h:float}
     */
    public static function draw(Canvas $c, Theme $t, array $options = []): array
    {
        $margin = (float) ($options['margin'] ?? 16);
        $footer = trim((string) ($options['footer'] ?? ''));

        self::background($c, $t);
        self::border($c, $t, $margin);

        $pad = $margin + 22;
        $bottom = $pad + ($footer !== '' ? 20 : 0);

        if ($footer !== '') {
            $c->text(
                $footer,
                $c->width() / 2,
                $c->height() - $margin - 22,
                10,
                $t->c('ink_faint'),
                Canvas::W_MEDIUM,
                'center',
                0.85,
                'middle',
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

        // دو هاله‌ی بزرگ و نرم در دو گوشه
        $c->radialGlow($w * 0.88, -$h * 0.10, $w * 0.52, $t->c('glow'), 0.24);
        $c->radialGlow($w * 0.06, $h * 1.06, $w * 0.46, $t->c('glow_alt'), 0.22);

        self::texture($c, $t);
        self::vignette($c);
    }

    /** بافت خطوط مورب بسیار محو */
    private static function texture(Canvas $c, Theme $t): void
    {
        $w = $c->width();
        $h = $c->height();
        $step = 26;
        for ($x = -$h; $x < $w; $x += $step) {
            $c->line($x, 0, $x + $h, $h, $t->c('tint'), 1, 0.016);
        }
    }

    private static function vignette(Canvas $c): void
    {
        $w = $c->width();
        $h = $c->height();

        $band = (int) ($h * 0.30);
        for ($i = 0; $i < $band; $i += 2) {
            $a = (1 - $i / $band) ** 2;
            $c->rect(0, $i, $w, 2, '#000000', $a * 0.34);
            $c->rect(0, $h - $i - 2, $w, 2, '#000000', $a * 0.42);
        }
        $bandX = (int) ($w * 0.14);
        for ($i = 0; $i < $bandX; $i += 2) {
            $a = (1 - $i / $bandX) ** 2;
            $c->rect($i, 0, 2, $h, '#000000', $a * 0.34);
            $c->rect($w - $i - 2, 0, 2, $h, '#000000', $a * 0.34);
        }
    }

    private static function border(Canvas $c, Theme $t, float $margin): void
    {
        $w = $c->width();
        $h = $c->height();
        $bw = $w - $margin * 2;
        $bh = $h - $margin * 2;

        $c->strokeRoundRect($margin, $margin, $bw, $bh, 26, '#FFFFFF', 1, 0.09);

        // نوار تأکید در لبه‌ی بالا: سبز به آبی
        $c->gradientLine(
            $margin + $bw * 0.30,
            $margin + 1,
            $margin + $bw * 0.70,
            $margin + 1,
            $t->c('accent'),
            $t->c('accent_alt'),
            2.4,
            0.9
        );
    }

    /**
     * سربرگ کارت: عنوان راست، تاریخ زیرش، و یک ویجت دلخواه در سمت چپ.
     *
     * @param callable(float,float):void|null $left ویجت سمت چپ (x و y بالای آن)
     */
    public static function header(
        Canvas $c,
        Theme $t,
        array $rect,
        string $title,
        string $subtitle = '',
        string $badge = '',
        ?callable $left = null
    ): float {
        $x = $rect['x'];
        $w = $rect['w'];
        $y = $rect['y'];

        $c->text($title, $x + $w, $y, 18, $t->c('ink'), Canvas::W_BLACK, 'right');
        if ($subtitle !== '') {
            $c->text($subtitle, $x + $w, $y + 34, 10.5, $t->c('ink_dim'), Canvas::W_MEDIUM, 'right');
        }

        if ($left !== null) {
            $left($x, $y);
        } elseif ($badge !== '') {
            $bw = $c->textWidth($badge, 10, Canvas::W_SEMIBOLD) + 26;
            $c->glass($x, $y + 1, $bw, 26, 9, ['fill' => 0.07, 'border' => 0.13, 'blur' => 2, 'shadow' => false]);
            $c->text($badge, $x + $bw / 2, $y + 14, 10, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'center', 1.0, 'middle');
        }

        $lineY = $y + 58;
        $c->rect($x, $lineY, $w, 1, '#FFFFFF', 0.07);
        $c->gradientLine($x + $w - 74, $lineY, $x + $w, $lineY, $t->c('accent_alt'), $t->c('accent'), 2.2, 0.95);

        return $lineY + 18;
    }

    /** برچسب کوچک شیشه‌ای */
    public static function chip(
        Canvas $c,
        Theme $t,
        string $text,
        float $x,
        float $y,
        float $size = 10,
        ?string $color = null,
        string $weight = Canvas::W_SEMIBOLD
    ): float {
        $w = $c->textWidth($text, $size, $weight) + 22;
        $h = $size + 14;
        $tint = $color ?? '#FFFFFF';

        $c->roundRect($x, $y, $w, $h, $h / 2, $tint, $color !== null ? 0.14 : 0.07);
        $c->strokeRoundRect($x, $y, $w, $h, $h / 2, $tint, 1, $color !== null ? 0.35 : 0.12);
        $c->text($text, $x + $w / 2, $y + $h / 2, $size, $color ?? $t->c('ink_dim'), $weight, 'center', 1.0, 'ink');

        return $w;
    }
}

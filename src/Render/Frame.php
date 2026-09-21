<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * قاب و پس‌زمینه‌ی کارت‌ها — کاغذ سفید با حاشیه‌ی مشکی و تأکید سبز/قرمز.
 */
final class Frame
{
    public const HEADER_H = 84.0;

    /** ضخامت حاشیه‌ی بیرونی */
    private const BORDER_W = 2.4;

    /**
     * @param array{footer?:string,margin?:float} $options
     * @return array{x:float,y:float,w:float,h:float}
     */
    public static function draw(Canvas $c, Theme $t, array $options = []): array
    {
        $margin = (float) ($options['margin'] ?? 18);
        $footer = trim((string) ($options['footer'] ?? ''));

        self::background($c, $t);
        self::border($c, $t, $margin);

        $pad = $margin + 24;
        $bottom = $pad + ($footer !== '' ? 22 : 0);

        if ($footer !== '') {
            self::footer($c, $t, $footer, $margin);
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

        // دو لکه‌ی رنگی بسیار ملایم؛ کاغذ سفید می‌ماند اما مرده نیست
        $c->radialGlow($w * 0.86, -$h * 0.06, $w * 0.44, $t->c('glow'), 0.07);
        $c->radialGlow($w * 0.08, $h * 1.04, $w * 0.40, $t->c('glow_alt'), 0.055);

        self::texture($c, $t);
    }

    /** بافت نقطه‌چین بسیار محو روی کاغذ */
    private static function texture(Canvas $c, Theme $t): void
    {
        $w = $c->width();
        $h = $c->height();
        $step = 24;
        $color = $t->c('line_soft');

        for ($y = $step; $y < $h; $y += $step) {
            for ($x = $step; $x < $w; $x += $step) {
                $c->rect($x, $y, 1.4, 1.4, $color, 0.55);
            }
        }
    }

    private static function border(Canvas $c, Theme $t, float $margin): void
    {
        $w = $c->width();
        $h = $c->height();
        $bw = $w - $margin * 2;
        $bh = $h - $margin * 2;
        $r = 28.0;

        // حاشیه‌ی مشکی اصلی
        $c->strokeRoundRect($margin, $margin, $bw, $bh, $r, $t->c('line'), self::BORDER_W, 0.92);
        // خط داخلی نازک برای عمق
        $c->strokeRoundRect($margin + 5, $margin + 5, $bw - 10, $bh - 10, $r - 5, $t->c('line_soft'), 1, 0.85);

        // سه قطعه‌ی رنگی روی لبه‌ی بالا: سبز، مشکی، قرمز
        $segY = $margin + self::BORDER_W / 2;
        $segW = $bw * 0.085;
        $x0 = $margin + $bw * 0.5 - ($segW * 3 + 12) / 2;
        foreach ([$t->c('up'), $t->c('line'), $t->c('down')] as $i => $color) {
            $c->roundRect($x0 + $i * ($segW + 6), $segY - 2.6, $segW, 5.2, 2.6, $color, 1.0);
        }
    }

    private static function footer(Canvas $c, Theme $t, string $text, float $margin): void
    {
        $y = $c->height() - $margin - 24;
        $tw = $c->textWidth($text, 10.5, Canvas::W_SEMIBOLD) + $c->textWidth($text, 10.5, Canvas::W_SEMIBOLD) * 0.14;
        $cx = $c->width() / 2;

        $c->line($cx - $tw / 2 - 40, $y, $cx - $tw / 2 - 14, $y, $t->c('line_soft'), 1.4, 1.0);
        $c->line($cx + $tw / 2 + 14, $y, $cx + $tw / 2 + 40, $y, $t->c('line_soft'), 1.4, 1.0);
        $c->text($text, $cx, $y, 10.5, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'center', 1.0, 'middle', 2.0);
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

        // میله‌ی مشکی کنار عنوان
        $c->roundRect($x + $w - 5, $y + 1, 5, 26, 2.5, $t->c('line'), 1.0);
        $c->text($title, $x + $w - 17, $y + 14, 19, $t->c('ink'), Canvas::W_BLACK, 'right', 1.0, 'middle');

        if ($subtitle !== '') {
            $c->text($subtitle, $x + $w, $y + 40, 11, $t->c('ink_dim'), Canvas::W_MEDIUM, 'right', 1.0, 'top');
        }

        if ($left !== null) {
            $left($x, $y);
        } elseif ($badge !== '') {
            self::tagBadge($c, $t, $badge, $x, $y + 2);
        }

        $lineY = $y + 66;
        $c->rect($x, $lineY, $w, 1, $t->c('line_soft'), 1.0);
        $c->roundRect($x + $w - 120, $lineY - 1.4, 120, 3, 1.5, $t->c('line'), 0.95);
        $c->roundRect($x + $w - 120, $lineY - 1.4, 42, 3, 1.5, $t->c('accent'), 1.0);

        return $lineY + 18;
    }

    /** برچسب مستطیلی با حاشیه‌ی مشکی */
    public static function tagBadge(Canvas $c, Theme $t, string $text, float $x, float $y, float $size = 10.5): float
    {
        $w = $c->textWidth($text, $size, Canvas::W_SEMIBOLD) + 26;
        $h = $size + 15;

        $c->roundRect($x, $y, $w, $h, 8, $t->c('surface_alt'), 1.0);
        $c->strokeRoundRect($x, $y, $w, $h, 8, $t->c('line'), 1.2, 0.55);
        $c->text($text, $x + $w / 2, $y + $h / 2, $size, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'center', 1.0, 'ink');

        return $w;
    }

    /**
     * جعبه‌ی سفید کارت: سایه‌ی ملایم، پرکننده‌ی سفید و حاشیه‌ی مشکی نازک.
     *
     * @param array{fill?:string,radius?:float,border?:string,border_alpha?:float,border_w?:float,shadow?:bool} $o
     */
    public static function panel(Canvas $c, Theme $t, float $x, float $y, float $w, float $h, float $radius = 18, array $o = []): void
    {
        $fill        = (string) ($o['fill'] ?? $t->c('surface'));
        $border      = (string) ($o['border'] ?? $t->c('line'));
        $borderAlpha = (float) ($o['border_alpha'] ?? 0.16);
        $borderW     = (float) ($o['border_w'] ?? 1.4);

        if ($o['shadow'] ?? true) {
            $c->shadow($x, $y, $w, $h, $radius, $t->c('shadow'), 0.10, 15, 5);
        }
        $c->roundRect($x, $y, $w, $h, $radius, $fill, 1.0);
        if ($borderAlpha > 0) {
            $c->strokeRoundRect($x, $y, $w, $h, $radius, $border, $borderW, $borderAlpha);
        }
    }

    /** برچسب رنگی کوچک (پر یا تو‌خالی) */
    public static function chip(
        Canvas $c,
        Theme $t,
        string $text,
        float $x,
        float $y,
        float $size = 10,
        ?string $color = null,
        string $weight = Canvas::W_SEMIBOLD,
        bool $solid = false
    ): float {
        $w = $c->textWidth($text, $size, $weight) + 22;
        $h = $size + 14;
        $tone = $color ?? $t->c('line');

        if ($solid) {
            $c->roundRect($x, $y, $w, $h, $h / 2, $tone, 1.0);
            $c->text($text, $x + $w / 2, $y + $h / 2, $size, '#FFFFFF', $weight, 'center', 1.0, 'ink');

            return $w;
        }

        $c->roundRect($x, $y, $w, $h, $h / 2, $tone, 0.10);
        $c->strokeRoundRect($x, $y, $w, $h, $h / 2, $tone, 1.2, 0.55);
        $c->text($text, $x + $w / 2, $y + $h / 2, $size, $color ?? $t->c('ink_dim'), $weight, 'center', 1.0, 'ink');

        return $w;
    }
}

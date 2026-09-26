<?php
declare(strict_types=1);

namespace Nikto\Render;

final class Frame
{
    public const HEADER_H = 84.0;

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
        $smoke = $t->c('smoke', $t->c('line'));

        $c->gradient(0, 0, $w, $h, $t->c('bg_from'), $t->c('bg_to'), 'v');

        $c->radialGlow($w * 0.06, $h * 0.10, $w * 0.34, $smoke, 0.055);
        $c->radialGlow($w * 0.97, $h * 1.00, $w * 0.40, $smoke, 0.075);
        $c->radialGlow($w * 0.84, $h * 0.02, $w * 0.40, '#FFFFFF', 0.60);
        $c->radialGlow($w * 0.22, $h * 0.92, $w * 0.30, '#FFFFFF', 0.40);

        for ($x = -$h; $x < $w; $x += 34) {
            $c->line($x, 0, $x + $h, $h, '#FFFFFF', 1, 0.10);
        }
    }

    private static function border(Canvas $c, Theme $t, float $margin): void
    {
        $w = $c->width();
        $h = $c->height();
        $bw = $w - $margin * 2;
        $bh = $h - $margin * 2;
        $r = 28.0;

        $c->strokeRoundRect($margin, $margin, $bw, $bh, $r, $t->c('line'), 1.3, 0.30);
        $c->strokeRoundRect($margin + 1.6, $margin + 1.6, $bw - 3.2, $bh - 3.2, $r - 1.6, '#FFFFFF', 1.2, 0.85);

        $capW = 64.0;
        $c->roundRect($w / 2 - $capW / 2, $margin - 2, $capW, 4.4, 2.2, $t->c('line'), 0.92);
    }

    private static function footer(Canvas $c, Theme $t, string $text, float $margin): void
    {
        $y = $c->height() - $margin - 24;
        $tw = $c->textWidth($text, 10.5, Canvas::W_SEMIBOLD) * 1.14;
        $cx = $c->width() / 2;

        $c->line($cx - $tw / 2 - 40, $y, $cx - $tw / 2 - 14, $y, $t->c('line'), 1.2, 0.18);
        $c->line($cx + $tw / 2 + 14, $y, $cx + $tw / 2 + 40, $y, $t->c('line'), 1.2, 0.18);
        $c->text($text, $cx, $y, 10.5, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'center', 1.0, 'middle', 2.0);
    }

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
        $c->rect($x, $lineY, $w, 1, $t->c('line'), 0.12);
        $c->rect($x, $lineY + 1, $w, 1, '#FFFFFF', 0.7);
        $c->roundRect($x + $w - 96, $lineY - 1.2, 96, 3, 1.5, $t->c('line'), 0.95);

        return $lineY + 18;
    }

    public static function tagBadge(Canvas $c, Theme $t, string $text, float $x, float $y, float $size = 10.5): float
    {
        $w = $c->textWidth($text, $size, Canvas::W_SEMIBOLD) + 26;
        $h = $size + 15;

        $c->roundRect($x, $y, $w, $h, $h / 2, '#FFFFFF', 0.72);
        $c->strokeRoundRect($x, $y, $w, $h, $h / 2, $t->c('line'), 1.0, 0.22);
        $c->strokeRoundRect($x + 1, $y + 1, $w - 2, $h - 2, $h / 2 - 1, '#FFFFFF', 1.0, 0.9);
        $c->text($text, $x + $w / 2, $y + $h / 2, $size, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'center', 1.0, 'ink');

        return $w;
    }

    public static function panel(Canvas $c, Theme $t, float $x, float $y, float $w, float $h, float $radius = 18, array $o = []): void
    {
        if (isset($o['fill']) && $o['fill'] !== $t->c('surface')) {
            self::well($c, $t, $x, $y, $w, $h, $radius);
            return;
        }

        if ($o['shadow'] ?? true) {
            $c->shadowOutside($x, $y, $w, $h, $radius, $t->c('shadow'), 0.10, 20, 8);
        }
        $c->backdropBlur($x, $y, $w, $h, $radius, 4);
        $c->roundRect($x, $y, $w, $h, $radius, '#FFFFFF', $t->glassAlpha());

        $band = min($h * 0.45, 72.0);
        for ($i = 0; $i < $band; $i += 2) {
            $a = 0.30 * (1 - $i / $band) ** 2;
            if ($a < 0.004) {
                break;
            }
            $inset = $i < $radius ? $radius - sqrt(max(0.0, $radius ** 2 - ($radius - $i) ** 2)) : 0.0;
            $c->rect($x + $inset + 1, $y + $i + 1, $w - ($inset + 1) * 2, 2, '#FFFFFF', $a);
        }

        $c->strokeRoundRect($x - 0.6, $y - 0.6, $w + 1.2, $h + 1.2, $radius + 0.6, $t->c('line'), 1.0, 0.15);
        $c->strokeRoundRect($x + 0.8, $y + 0.8, $w - 1.6, $h - 1.6, $radius - 0.8, '#FFFFFF', 1.4, 0.95);
    }

    public static function well(Canvas $c, Theme $t, float $x, float $y, float $w, float $h, float $radius = 10, float $strength = 1.0): void
    {
        $c->roundRect($x, $y, $w, $h, $radius, $t->c('line'), 0.045 * $strength);
        $c->strokeRoundRect($x, $y, $w, $h, $radius, '#FFFFFF', 1.0, 0.75 * $strength);
    }

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

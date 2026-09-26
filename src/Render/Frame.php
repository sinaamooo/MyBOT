<?php
declare(strict_types=1);

namespace Nikto\Render;

final class Frame
{
    public const HEADER_H = 78.0;

    public static function draw(Canvas $c, Theme $t, array $options = []): array
    {
        $margin = (float) ($options['margin'] ?? 18);
        $footer = trim((string) ($options['footer'] ?? ''));

        $w = $c->width();
        $h = $c->height();
        $c->gradient(0, 0, $w, $h, $t->c('bg_from'), $t->c('bg_to'), 'v');
        $c->strokeRoundRect($margin, $margin, $w - $margin * 2, $h - $margin * 2, 28, $t->c('line'), 1.0, 0.10);

        $pad = $margin + 24;
        $bottom = $pad + ($footer !== '' ? 22 : 0);

        if ($footer !== '') {
            $c->text($footer, $w / 2, $h - $margin - 24, 10.5, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'center', 1.0, 'middle', 2.0);
        }

        return [
            'x' => $pad,
            'y' => $pad,
            'w' => $w - $pad * 2,
            'h' => $h - $pad - $bottom,
        ];
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

        $c->text($title, $x + $w, $y + 14, 19, $t->c('ink'), Canvas::W_BLACK, 'right', 1.0, 'middle');
        if ($subtitle !== '') {
            $c->text($subtitle, $x + $w, $y + 40, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'top');
        }

        if ($left !== null) {
            $left($x, $y);
        } elseif ($badge !== '') {
            self::tagBadge($c, $t, $badge, $x, $y + 2);
        }

        $lineY = $y + 62;
        $c->rect($x, $lineY, $w, 1, $t->c('line'), 0.08);

        return $lineY + 16;
    }

    public static function tagBadge(Canvas $c, Theme $t, string $text, float $x, float $y, float $size = 10.5): float
    {
        $c->text($text, $x, $y + 12, $size, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');

        return $c->textWidth($text, $size, Canvas::W_MEDIUM);
    }

    public static function panel(Canvas $c, Theme $t, float $x, float $y, float $w, float $h, float $radius = 18, array $o = []): void
    {
        if (isset($o['fill']) && $o['fill'] !== $t->c('surface')) {
            self::well($c, $t, $x, $y, $w, $h, $radius);
            return;
        }

        if ($o['shadow'] ?? true) {
            $c->shadowOutside($x, $y, $w, $h, $radius, $t->c('shadow'), 0.06, 18, 6);
        }
        $c->roundRect($x, $y, $w, $h, $radius, '#FFFFFF', $t->glassAlpha());
        $c->strokeRoundRect($x + 0.6, $y + 0.6, $w - 1.2, $h - 1.2, $radius - 0.6, '#FFFFFF', 1.2, 0.9);
    }

    public static function well(Canvas $c, Theme $t, float $x, float $y, float $w, float $h, float $radius = 10, float $strength = 1.0): void
    {
        $c->roundRect($x, $y, $w, $h, $radius, $t->c('line'), 0.03 * $strength);
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

        $c->text($text, $x + $w / 2, $y + $h / 2, $size, $color ?? $t->c('ink_dim'), $weight, 'center', 1.0, 'ink');

        return $w;
    }
}

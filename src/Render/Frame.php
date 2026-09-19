<?php
declare(strict_types=1);

namespace Nikto\Render;

/**
 * قاب نئونی کارت‌ها: پس‌زمینه، درخشش، حاشیه‌ی تکنولوژیک،
 * پلاک نام کانال و پنل سفید محتوا.
 */
final class Frame
{
    /**
     * @param array{brand?:string,footer?:string,pad?:float,panel?:bool} $options
     * @return array{x:float,y:float,w:float,h:float}
     */
    public static function draw(Canvas $c, Theme $t, array $options = []): array
    {
        $brand  = (string) ($options['brand'] ?? 'NIKTO CRYPTO');
        $footer = (string) ($options['footer'] ?? '');
        $pad    = (float) ($options['pad'] ?? 26);
        $withPanel = (bool) ($options['panel'] ?? true);

        $w = $c->width();
        $h = $c->height();

        self::background($c, $t);
        self::neonBorder($c, $t, $pad);
        self::cornerBrackets($c, $t, $pad);
        self::brandPlate($c, $t, $brand, $pad);
        self::footerPlate($c, $t, $footer, $pad);

        $inset = $pad + 24;
        $top   = $pad + 46;
        $bottom = $pad + ($footer !== '' ? 46 : 34);

        $rect = [
            'x' => $inset,
            'y' => $top,
            'w' => $w - $inset * 2,
            'h' => $h - $top - $bottom,
        ];

        if ($withPanel) {
            $c->shadow($rect['x'], $rect['y'], $rect['w'], $rect['h'], 24, '#000000', 0.45, 26, 10);
            $c->roundRect($rect['x'], $rect['y'], $rect['w'], $rect['h'], 24, $t->c('panel'));
            // درخشش ملایم بالای پنل
            $c->gradient($rect['x'] + 2, $rect['y'] + 2, $rect['w'] - 4, 90, '#FFFFFF', $t->c('panel'), 'v', 0, 0.55);
            $c->strokeRoundRect($rect['x'], $rect['y'], $rect['w'], $rect['h'], 24, '#FFFFFF', 1.5, 0.75);
        }

        return $rect;
    }

    private static function background(Canvas $c, Theme $t): void
    {
        $w = $c->width();
        $h = $c->height();

        $c->gradient(0, 0, $w, $h, $t->c('bg_from'), $t->c('bg_to'), 'v');

        // درخشش‌های گوشه
        $c->radialGlow($w * 0.04, $h * 0.06, $w * 0.34, $t->c('glow_a'), 0.62);
        $c->radialGlow($w * 0.97, $h * 0.95, $w * 0.32, $t->c('glow_b'), 0.52);
        $c->radialGlow($w * 0.92, $h * 0.04, $w * 0.20, $t->c('glow_c'), 0.34);
        $c->radialGlow($w * 0.06, $h * 0.97, $w * 0.18, $t->c('glow_c'), 0.24);

        // عمق‌بخشی: لایه‌ی تیره روی درخشش‌ها
        $c->rect(0, 0, $w, $h, $t->c('bg_from'), 0.42);

        self::circuit($c, $t);

        // نوار نور مورب
        $c->polygon([
            [$w * 0.10, 0], [$w * 0.30, 0], [$w * 0.10, $h], [-$w * 0.10, $h],
        ], '#FFFFFF', 0.022);

        self::vignette($c);
    }

    /** خطوط مدار چاپی محو در پس‌زمینه */
    private static function circuit(Canvas $c, Theme $t): void
    {
        $w = $c->width();
        $h = $c->height();
        mt_srand(20260919);
        $col = $t->c('neon');

        for ($i = 0; $i < 26; $i++) {
            $x = mt_rand(0, (int) $w);
            $y = mt_rand(0, (int) $h);
            $len = mt_rand((int) ($w * 0.05), (int) ($w * 0.22));
            $horizontal = mt_rand(0, 1) === 1;
            $alpha = mt_rand(4, 11) / 100;

            if ($horizontal) {
                $c->line($x, $y, $x + $len, $y, $col, 1.4, $alpha);
                $c->line($x + $len, $y, $x + $len + 26, $y + 26, $col, 1.4, $alpha);
                $c->circle($x + $len + 26, $y + 26, 3.2, $col, $alpha * 1.6);
            } else {
                $c->line($x, $y, $x, $y + $len, $col, 1.4, $alpha);
                $c->line($x, $y + $len, $x + 24, $y + $len + 24, $col, 1.4, $alpha);
                $c->circle($x + 24, $y + $len + 24, 3.2, $col, $alpha * 1.6);
            }
        }
        mt_srand();
    }

    private static function vignette(Canvas $c): void
    {
        $w = $c->width();
        $h = $c->height();
        $band = (int) ($h * 0.30);
        for ($i = 0; $i < $band; $i += 2) {
            $a = (1 - $i / $band) ** 2;
            $c->rect(0, $i, $w, 2, '#000000', $a * 0.42);
            $c->rect(0, $h - $i - 2, $w, 2, '#000000', $a * 0.50);
        }
        $bandX = (int) ($w * 0.20);
        for ($i = 0; $i < $bandX; $i += 2) {
            $a = (1 - $i / $bandX) ** 2;
            $c->rect($i, 0, 2, $h, '#000000', $a * 0.42);
            $c->rect($w - $i - 2, 0, 2, $h, '#000000', $a * 0.42);
        }
    }

    private static function neonBorder(Canvas $c, Theme $t, float $pad): void
    {
        $w = $c->width();
        $h = $c->height();
        $x = $pad;
        $y = $pad;
        $bw = $w - $pad * 2;
        $bh = $h - $pad * 2;

        // هاله‌ی بیرونی
        for ($i = 9; $i >= 1; $i--) {
            $c->strokeRoundRect($x - $i, $y - $i, $bw + $i * 2, $bh + $i * 2, 28 + $i, $t->c('neon'), 2.2, 0.055);
        }
        $c->strokeRoundRect($x, $y, $bw, $bh, 28, $t->c('neon'), 3.2, 0.95);
        $c->strokeRoundRect($x + 7, $y + 7, $bw - 14, $bh - 14, 22, $t->c('neon_alt'), 1.2, 0.45);

        // بریدگی‌های تزئینی وسط لبه‌ها
        $c->rect($x - 2, $y + $bh / 2 - 46, 5, 92, $t->c('neon_alt'), 0.95);
        $c->rect($x + $bw - 3, $y + $bh / 2 - 46, 5, 92, $t->c('neon_alt'), 0.95);
    }

    private static function cornerBrackets(Canvas $c, Theme $t, float $pad): void
    {
        $w = $c->width();
        $h = $c->height();
        $len = 78.0;
        $th = 6.0;
        $col = $t->c('neon_alt');
        $x1 = $pad + 2;
        $y1 = $pad + 2;
        $x2 = $w - $pad - 2;
        $y2 = $h - $pad - 2;

        foreach ([[$x1, $y1, 1, 1], [$x2, $y1, -1, 1], [$x1, $y2, 1, -1], [$x2, $y2, -1, -1]] as [$bx, $by, $sx, $sy]) {
            $c->rect(min($bx, $bx + $sx * $len), $by - ($sy > 0 ? 0 : $th), $len, $th, $col, 0.92);
            $c->rect($bx - ($sx > 0 ? 0 : $th), min($by, $by + $sy * $len), $th, $len, $col, 0.92);
            $c->circle($bx + $sx * ($len + 16), $by + $sy * 3.5, 4.0, $t->c('neon'), 0.8);
        }
    }

    /** پلاک نام کانال روی لبه‌ی بالا */
    private static function brandPlate(Canvas $c, Theme $t, string $brand, float $pad): void
    {
        if (trim($brand) === '') {
            return;
        }
        $w = $c->width();
        $cx = $w / 2;
        $size = 19.0;
        $spacing = 5.0;
        $textW = $c->textWidth($brand, $size, Canvas::W_BLACK) + $spacing * max(0, mb_strlen($brand) - 1);
        $plateW = max(280.0, $textW + 96);
        $plateH = 46.0;
        $top = $pad - $plateH / 2 + 1;
        $cut = 20.0;

        $left = $cx - $plateW / 2;
        $right = $cx + $plateW / 2;

        $c->radialGlow($cx, $top + $plateH / 2, $plateW * 0.55, $t->c('neon'), 0.30);
        $c->polygon([
            [$left + $cut, $top],
            [$right - $cut, $top],
            [$right, $top + $plateH / 2],
            [$right - $cut, $top + $plateH],
            [$left + $cut, $top + $plateH],
            [$left, $top + $plateH / 2],
        ], $t->c('plate'), 0.97);

        // خط دور پلاک
        $pts = [
            [$left + $cut, $top], [$right - $cut, $top], [$right, $top + $plateH / 2],
            [$right - $cut, $top + $plateH], [$left + $cut, $top + $plateH], [$left, $top + $plateH / 2],
            [$left + $cut, $top],
        ];
        for ($i = 1; $i < count($pts); $i++) {
            $c->line($pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], $t->c('neon'), 2.0, 0.9);
        }

        $c->text($brand, $cx, $top + $plateH / 2 - 10, $size, $t->c('brand_ink'), Canvas::W_BLACK, 'center', 1.0, false, $spacing);
    }

    private static function footerPlate(Canvas $c, Theme $t, string $footer, float $pad): void
    {
        $w = $c->width();
        $h = $c->height();
        $cx = $w / 2;
        $y = $h - $pad;

        // لوزی تزئینی
        $c->polygon([[$cx, $y - 11], [$cx + 11, $y], [$cx, $y + 11], [$cx - 11, $y]], $t->c('neon'), 0.95);
        $c->polygon([[$cx, $y - 5], [$cx + 5, $y], [$cx, $y + 5], [$cx - 5, $y]], $t->c('plate'), 1.0);

        if (trim($footer) === '') {
            return;
        }
        $size = 15.0;
        $tw = $c->textWidth($footer, $size, Canvas::W_MEDIUM);
        $plateW = $tw + 64;
        $c->roundRect($cx - $plateW / 2, $y - 17, $plateW, 34, 17, $t->c('plate'), 0.92);
        $c->strokeRoundRect($cx - $plateW / 2, $y - 17, $plateW, 34, 17, $t->c('neon'), 1.4, 0.7);
        $c->text($footer, $cx, $y - 9, $size, $t->c('brand_ink'), Canvas::W_MEDIUM, 'center', 0.92);
    }

    /** سربرگ داخل پنل: عنوان راست، تاریخ چپ */
    public static function panelHeader(
        Canvas $c,
        Theme $t,
        array $rect,
        string $title,
        string $right = '',
        string $left = ''
    ): float {
        $padX = 30.0;
        $y = $rect['y'] + 26;

        $c->text($title, $rect['x'] + $rect['w'] - $padX, $y, 27, $t->c('ink'), Canvas::W_BLACK, 'right');
        if ($right !== '') {
            $c->text($right, $rect['x'] + $rect['w'] - $padX, $y + 38, 15, $t->c('ink_soft'), Canvas::W_MEDIUM, 'right');
        }
        if ($left !== '') {
            $c->text($left, $rect['x'] + $padX, $y + 6, 16, $t->c('ink_soft'), Canvas::W_SEMIBOLD, 'left');
        }

        $lineY = $y + ($right !== '' ? 70 : 48);
        $c->rect($rect['x'] + $padX, $lineY, $rect['w'] - $padX * 2, 1.4, $t->c('line'));
        $c->rect($rect['x'] + $rect['w'] - $padX - 90, $lineY - 0.5, 90, 2.6, $t->c('value'));

        return $lineY + 18;
    }
}

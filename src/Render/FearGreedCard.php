<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\FearGreedProvider;

/**
 * کارت شاخص ترس و طمع — حلقه‌ی کامل، نردبان ناحیه‌ها و نمودار ۳۰ روزه.
 */
final class FearGreedCard extends Card
{
    /** @var array<string,mixed> */
    private array $data;

    private const HISTORY_LABELS = [
        'yesterday' => 'دیروز',
        'week'      => '۷ روز پیش',
        'month'     => '۱ ماه پیش',
    ];

    private const RANGES = ['0 – 24', '25 – 44', '45 – 55', '56 – 75', '76 – 100'];

    public function __construct(array $data, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->data = $data;
    }

    public function render(): Canvas
    {
        $c = new Canvas(1200, 628, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header($c, $t, $rect, 'شاخص ترس و طمع بازار', $this->dateLine(), 'Fear & Greed Index');

        $gap = 14.0;
        $bodyH = $rect['y'] + $rect['h'] - $top;
        $topH = $bodyH * 0.645;
        $dialW = $rect['w'] * 0.40;
        $ladderW = $rect['w'] - $dialW - $gap;

        $this->dial($c, $t, $rect['x'] + $ladderW + $gap, $top, $dialW, $topH);
        $this->ladder($c, $t, $rect['x'], $top, $ladderW, $topH);
        $this->trend($c, $t, $rect['x'], $top + $topH + $gap, $rect['w'], $bodyH - $topH - $gap);

        return $c;
    }

    private function zoneIndex(int $value): int
    {
        foreach (FearGreedProvider::ZONES as $i => $zone) {
            if ($value <= $zone['max']) {
                return $i;
            }
        }

        return count(FearGreedProvider::ZONES) - 1;
    }

    /** رنگ ناحیه در پالت سبز/آبی */
    private function zoneColor(Theme $t, int $index): string
    {
        $ramp = [
            $t->c('down'),                                     // ترس شدید — آبی
            Canvas::mix($t->c('down'), $t->c('ink_dim'), 0.45),
            $t->c('ink_dim'),                                  // خنثی — خاکستری روشن
            Canvas::mix($t->c('up'), $t->c('ink_dim'), 0.35),
            $t->c('up'),                                       // طمع شدید — سبز
        ];

        return $ramp[$index] ?? $t->c('ink');
    }

    /** حلقه‌ی کامل با عدد در مرکز */
    private function dial(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $value = max(0, min(100, (int) $this->data['value']));
        $index = $this->zoneIndex($value);
        $color = $this->zoneColor($t, $index);
        $zone = $this->data['zone'];

        $c->glass($x, $y, $w, $h, 22, ['fill' => 0.05, 'border' => 0.11, 'tint' => $t->c('tint')]);

        $cx = $x + $w / 2;
        $cy = $y + $h * 0.47;
        $radius = min($w * 0.26, $h * 0.30);
        $thickness = $radius * 0.20;

        // هاله‌ی پشت حلقه
        $c->radialGlow($cx, $cy, $radius * 1.7, $color, 0.16);

        // مسیر خالی
        $c->ring($cx, $cy, $radius, $thickness, '#FFFFFF', 0, 360, 0.08);

        // کمان پیشرفت از بالا
        $sweep = max(2.0, $value * 3.6);
        $c->ring($cx, $cy, $radius, $thickness, $color, -90, -90 + $sweep, 0.96);
        // سر گرد کمان
        $endAngle = deg2rad(-90 + $sweep);
        $c->circle($cx + cos($endAngle) * $radius, $cy + sin($endAngle) * $radius, $thickness / 2, $color);
        $c->circle($cx, $cy - $radius, $thickness / 2, $color);

        // حلقه‌ی نازک ناحیه‌ها بیرون
        $bounds = [0, 25, 45, 56, 76, 101];
        foreach (FearGreedProvider::ZONES as $i => $zoneDef) {
            $a1 = -90 + $bounds[$i] * 3.6 + 1.2;
            $a2 = -90 + ($bounds[$i + 1] - 1) * 3.6 - 1.2;
            $c->ring($cx, $cy, $radius + $thickness * 0.92, 2.6, $this->zoneColor($t, $i), $a1, $a2, $i === $index ? 0.95 : 0.35);
        }

        // عدد وسط حلقه
        $c->text($this->dnum((string) $value), $cx, $cy, 44, $t->c('ink'), Canvas::W_BLACK, 'center', 1.0, 'ink');

        $label = (string) $zone['fa'];
        $chipW = $c->textWidth($label, 13, Canvas::W_BOLD) + 34;
        $chipY = min($y + $h - 40, $cy + $radius + $thickness + 16);
        $c->roundRect($cx - $chipW / 2, $chipY, $chipW, 28, 14, $color, 0.16);
        $c->strokeRoundRect($cx - $chipW / 2, $chipY, $chipW, 28, 14, $color, 1, 0.45);
        $c->text($label, $cx, $chipY + 14, 13, $color, Canvas::W_BOLD, 'center', 1.0, 'ink');
    }

    /** نردبان عمودی ناحیه‌ها */
    private function ladder(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $value = max(0, min(100, (int) $this->data['value']));
        $active = $this->zoneIndex($value);

        $c->glass($x, $y, $w, $h, 22, ['fill' => 0.05, 'border' => 0.11, 'tint' => $t->c('tint')]);

        $pad = 16.0;
        $c->text('ناحیه‌های شاخص', $x + $w - $pad, $y + $pad + 6, 11, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'ink');

        $rows = count(FearGreedProvider::ZONES);
        $gap = 7.0;
        $listY = $y + $pad + 26;
        $rowH = ($h - ($listY - $y) - $pad - $gap * ($rows - 1)) / $rows;

        // از بالا: طمع شدید تا پایین: ترس شدید
        foreach (array_reverse(FearGreedProvider::ZONES, true) as $i => $zoneDef) {
            $order = $rows - 1 - $i;
            $rowY = $listY + $order * ($rowH + $gap);
            $color = $this->zoneColor($t, $i);
            $isActive = $i === $active;

            $c->roundRect($x + $pad, $rowY, $w - $pad * 2, $rowH, 12, $isActive ? $color : '#FFFFFF', $isActive ? 0.13 : 0.035);
            if ($isActive) {
                $c->strokeRoundRect($x + $pad, $rowY, $w - $pad * 2, $rowH, 12, $color, 1.2, 0.5);
            }

            $mid = $rowY + $rowH / 2;
            $c->roundRect($x + $w - $pad - 8, $rowY + 6, 4, $rowH - 12, 2, $color, $isActive ? 1.0 : 0.5);

            $c->text(
                (string) $zoneDef['fa'],
                $x + $w - $pad - 22,
                $mid,
                12.5,
                $isActive ? $t->c('ink') : $t->c('ink_dim'),
                $isActive ? Canvas::W_BOLD : Canvas::W_MEDIUM,
                'right',
                1.0,
                'ink'
            );

            $c->text(
                $this->dnum(self::RANGES[$i] ?? ''),
                $x + $pad + 16,
                $mid,
                10.5,
                $isActive ? $color : $t->c('ink_faint'),
                Canvas::W_SEMIBOLD,
                'left',
                1.0,
                'ink'
            );

            if ($isActive) {
                // عدد فعلی داخل یک باکس کوچک، وسط ردیف
                $valueText = $this->dnum((string) $value);
                $boxW = max(46.0, $c->textWidth($valueText, 12, Canvas::W_BLACK) + 26);
                $boxH = min(26.0, $rowH - 12);
                $boxX = $x + $w / 2 - $boxW / 2;
                $boxY = $mid - $boxH / 2;

                $c->roundRect($boxX, $boxY, $boxW, $boxH, $boxH / 2, $color, 0.20);
                $c->strokeRoundRect($boxX, $boxY, $boxW, $boxH, $boxH / 2, $color, 1, 0.55);
                $c->text($valueText, $x + $w / 2, $mid, 12, $color, Canvas::W_BLACK, 'center', 1.0, 'ink');
            }
        }
    }

    /** نمودار ۳۰ روزه با برچسب‌های تاریخچه */
    private function trend(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $c->glass($x, $y, $w, $h, 20, ['fill' => 0.05, 'border' => 0.11, 'tint' => $t->c('tint')]);

        $pad = 18.0;
        $rowCy = $y + $pad + 11;   // عنوان و چیپ‌ها روی یک خط می‌نشینند

        $c->text('روند ۳۰ روز اخیر', $x + $w - $pad, $rowCy, 11, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'ink');

        // هر چیپ: برچسب و عدد و فلش، همه در یک ردیف
        $history = (array) ($this->data['history'] ?? []);
        $current = (int) $this->data['value'];
        $chipH = 26.0;
        $cursor = $x + $pad;

        foreach (self::HISTORY_LABELS as $key => $label) {
            if (!isset($history[$key])) {
                continue;
            }
            $v = (int) $history[$key]['value'];
            $diff = $current - $v;
            $color = $diff > 0 ? $t->c('up') : ($diff < 0 ? $t->c('down') : $t->c('flat'));

            $valueText = $this->dnum((string) $v);
            $labelW = $c->textWidth($label, 10, Canvas::W_MEDIUM);
            $valueW = $c->textWidth($valueText, 11, Canvas::W_BLACK);
            $chipW = $labelW + $valueW + ($diff !== 0 ? 16 : 6) + 30;

            $c->roundRect($cursor, $rowCy - $chipH / 2, $chipW, $chipH, $chipH / 2, '#FFFFFF', 0.05);
            $c->strokeRoundRect($cursor, $rowCy - $chipH / 2, $chipW, $chipH, $chipH / 2, '#FFFFFF', 1, 0.10);

            // از راست به چپ داخل چیپ: برچسب، سپس عدد، سپس فلش
            $inner = $cursor + $chipW - 13;
            $c->text($label, $inner, $rowCy, 10, $t->c('ink_dim'), Canvas::W_MEDIUM, 'right', 1.0, 'ink');
            $inner -= $labelW + 8;
            $c->text($valueText, $inner, $rowCy, 11, $t->c('ink'), Canvas::W_BLACK, 'right', 1.0, 'ink');
            if ($diff !== 0) {
                $inner -= $valueW + 9;
                $c->triangle($inner, $rowCy, 7, $diff > 0, $color);
            }

            $cursor += $chipW + 8;
        }

        $series = array_values(array_map('intval', (array) ($this->data['series'] ?? [])));
        if (count($series) < 5) {
            return;
        }

        $chartX = $x + $pad;
        $chartY = $rowCy + 22;
        $chartW = $w - $pad * 2;
        $chartH = $y + $h - $pad - $chartY;
        if ($chartH < 30) {
            return;
        }

        $min = min($series);
        $max = max($series);
        $span = max(1, $max - $min);
        $n = count($series);

        $points = [];
        foreach ($series as $i => $v) {
            $points[] = [
                $chartX + $chartW * ($i / max(1, $n - 1)),
                $chartY + $chartH - (($v - $min) / $span) * ($chartH - 8) - 4,
            ];
        }

        // خط میانه
        $c->dashedLine($chartX, $chartY + $chartH / 2, $chartX + $chartW, $chartY + $chartH / 2, $t->c('ink_faint'), 1, 6, 6, 0.25);

        $color = $this->zoneColor($t, $this->zoneIndex($current));
        $c->areaGradient($points, $chartX, $chartY, $chartW, $chartH, $color, 0.32);
        $c->polyline($points, $color, 2.0, 0.98);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 4.0, $color);
        $c->circle($last[0], $last[1], 1.8, $t->c('bg_from'));

        $c->text($this->dnum((string) $max), $chartX + $chartW, $chartY + 6, 9, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 0.9, 'ink');
        $c->text($this->dnum((string) $min), $chartX + $chartW, $chartY + $chartH - 6, 9, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 0.9, 'ink');
    }
}

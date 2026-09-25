<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\FearGreedProvider;

/**
 * کارت شاخص ترس و طمع — سنجه‌ی افقی قرمز به سبز، کارت‌های تاریخچه و نمودار ۳۰ روزه.
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

    /** مرز ناحیه‌ها روی سنجه */
    private const BOUNDS = [0, 25, 45, 56, 76, 101];

    public function __construct(array $data, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->data = $data;
    }

    public function render(): Canvas
    {
        $c = new Canvas(1200, 660, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header($c, $t, $rect, 'شاخص ترس و طمع بازار', $this->dateLine(), 'Fear & Greed Index');

        $gap = 14.0;
        $bottom = $rect['y'] + $rect['h'];
        $body = $bottom - $top;

        $meterH = round($body * 0.44);
        $histH  = 86.0;
        $chartH = $body - $meterH - $histH - $gap * 2;

        $this->meter($c, $t, $rect['x'], $top, $rect['w'], $meterH);
        $this->history($c, $t, $rect['x'], $top + $meterH + $gap, $rect['w'], $histH);
        $this->chart($c, $t, $rect['x'], $top + $meterH + $histH + $gap * 2, $rect['w'], $chartH);

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

    /** رنگ ناحیه: از قرمز (ترس) تا سبز (طمع) */
    private function zoneColor(Theme $t, int $index): string
    {
        $down = $t->c('down');
        $up = $t->c('up');

        return [
            $down,                                  // ترس شدید — قرمز پررنگ
            Canvas::mix($down, '#FFFFFF', 0.42),    // ترس — قرمز روشن
            Canvas::mix($t->c('flat'), '#FFFFFF', 0.25),
            Canvas::mix($up, '#FFFFFF', 0.42),      // طمع — سبز روشن
            $up,                                    // طمع شدید — سبز پررنگ
        ][$index] ?? $t->c('ink');
    }

    /** رنگ پررنگ ناحیه برای پلاک و نمودار (بدون روشن‌سازی) */
    private function zoneStrong(Theme $t, int $index): string
    {
        return match (true) {
            $index <= 1 => $t->c('down'),
            $index === 2 => $t->c('flat'),
            default     => $t->c('up'),
        };
    }

    /** پنل اصلی: عدد بزرگ در راست و سنجه‌ی افقی در چپ */
    private function meter(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $value = max(0, min(100, (int) $this->data['value']));
        $index = $this->zoneIndex($value);
        $color = $this->zoneStrong($t, $index);
        $zone = $this->data['zone'];

        Frame::panel($c, $t, $x, $y, $w, $h, 20);

        $pad = 24.0;
        $boxW = 246.0;
        $boxX = $x + $w - $pad - $boxW;

        // ── بلوک عدد امروز
        $boxY = $y + $pad;
        $boxH = $h - $pad * 2;
        $c->roundRect($boxX, $boxY, $boxW, $boxH, 16, $t->c('surface_alt'), 1.0);
        $c->strokeRoundRect($boxX, $boxY, $boxW, $boxH, 16, $t->c('line'), 1.6, 0.85);
        // نوار رنگی ناحیه در لبه‌ی راست بلوک
        $c->roundRect($boxX + $boxW - 10, $boxY + 12, 5, $boxH - 24, 2.5, $color, 1.0);

        // سه جزء (عنوان، عدد، برچسب ناحیه) با فاصله‌های کاملاً برابر روی هم چیده می‌شوند.
        // فاصله‌ها از ارتفاع واقعی جوهر حروف حساب می‌شوند، نه از اندازه‌ی فونت.
        $boxCx = $boxX + ($boxW - 14) / 2;
        $title = 'شاخص امروز';
        $number = $this->dnum((string) $value);
        $label = (string) $zone['fa'];

        $titleSize = 11.5;
        $numberSize = 40.0;
        $chipH = 30.0;
        $titleH = $c->inkHeight($title, $titleSize, Canvas::W_SEMIBOLD);
        $numberH = $c->inkHeight($number, $numberSize, Canvas::W_NUM_BOLD);
        $gap = ($boxH - $titleH - $numberH - $chipH) / 4;

        $titleCy = $boxY + $gap + $titleH / 2;
        $numberCy = $titleCy + $titleH / 2 + $gap + $numberH / 2;
        $chipY = $numberCy + $numberH / 2 + $gap;

        $c->text($title, $boxCx, $titleCy, $titleSize, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'center', 1.0, 'ink');
        $c->text($number, $boxCx, $numberCy, $numberSize, $t->c('ink'), Canvas::W_NUM_BOLD, 'center', 1.0, 'num');

        $chipW = $c->textWidth($label, 13, Canvas::W_BOLD) + 34;
        $c->roundRect($boxCx - $chipW / 2, $chipY, $chipW, $chipH, 9, $color, 1.0);
        $c->text($label, $boxCx, $chipY + $chipH / 2, 13, '#FFFFFF', Canvas::W_BOLD, 'center', 1.0, 'ink');

        // ── سنجه‌ی افقی
        $mx = $x + $pad;
        $mw = $boxX - 20 - $mx;
        $barH = 26.0;
        $barY = $y + $h * 0.52 - $barH / 2;

        $c->text('۰ ترس شدید تا ۱۰۰ طمع شدید', $mx + $mw, $y + $pad + 4, 11, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');

        $segGap = 4.0;
        $usable = $mw - $segGap * 4;
        foreach (FearGreedProvider::ZONES as $i => $zoneDef) {
            $from = self::BOUNDS[$i];
            $to = self::BOUNDS[$i + 1];
            $segX = $mx + $usable * ($from / 100) + $segGap * $i;
            $segW = $usable * (($to - $from) / 100);
            $active = $i === $index;

            // طیف از قرمز تا سبز؛ قطعه‌ی فعال بلندتر و با حاشیه‌ی مشکی
            $segColor = $this->zoneColor($t, $i);
            $segY = $active ? $barY - 5 : $barY;
            $segH = $active ? $barH + 10 : $barH;
            $c->roundRect($segX, $segY, $segW, $segH, 7, $segColor, 1.0);
            if ($active) {
                $c->strokeRoundRect($segX, $segY, $segW, $segH, 7, $t->c('line'), 1.8, 0.9);
            }

            // نام ناحیه زیر همان قطعه
            $c->textFit(
                (string) $zoneDef['fa'],
                $segX + $segW / 2,
                $barY + $barH + 26,
                $segW + 10,
                10.5,
                $active ? $t->c('ink') : $t->c('ink_faint'),
                $active ? Canvas::W_BOLD : Canvas::W_MEDIUM,
                'center',
                8.5,
                1.0,
                'middle'
            );
            $c->text(
                $this->dnum((string) $from . ' – ' . (string) ($to - 1)),
                $segX + $segW / 2,
                $barY + $barH + 47,
                9.5,
                $t->c('ink_faint'),
                Canvas::W_NUM,
                'center',
                1.0,
                'num'
            );
        }

        // ── نشانگر مقدار
        $markX = $mx + $usable * ($value / 100) + $segGap * min(4, $index);
        $markX = max($mx + 6, min($mx + $mw - 6, $markX));

        $bubbleW = max(52.0, $c->textWidth($this->dnum((string) $value), 14, Canvas::W_NUM_BOLD) + 30);
        $bubbleH = 28.0;
        $bubbleX = max($mx, min($mx + $mw - $bubbleW, $markX - $bubbleW / 2));
        $bubbleY = $barY - 18 - $bubbleH;

        $c->roundRect($bubbleX, $bubbleY, $bubbleW, $bubbleH, 8, $t->c('line'), 1.0);
        $c->text($this->dnum((string) $value), $bubbleX + $bubbleW / 2, $bubbleY + $bubbleH / 2, 14, '#FFFFFF', Canvas::W_NUM_BOLD, 'center', 1.0, 'num');
        $c->triangle($markX, $bubbleY + $bubbleH + 4, 9, false, $t->c('line'));
        $c->line($markX, $barY - 7, $markX, $barY + $barH + 7, $t->c('line'), 2.2, 0.9);
    }

    /** سه کارت کوچک تاریخچه */
    private function history(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $history = (array) ($this->data['history'] ?? []);
        $current = (int) $this->data['value'];

        $items = [];
        foreach (self::HISTORY_LABELS as $key => $label) {
            if (isset($history[$key])) {
                $items[] = [$label, (int) $history[$key]['value']];
            }
        }
        if ($items === []) {
            return;
        }

        $gap = 12.0;
        $cellW = ($w - $gap * (count($items) - 1)) / count($items);

        foreach ($items as $i => [$label, $v]) {
            // راست‌به‌چپ: دیروز در راست‌ترین خانه
            $cx = $x + $w - ($i + 1) * $cellW - $i * $gap;
            $diff = $current - $v;
            $color = $diff > 0 ? $t->c('up') : ($diff < 0 ? $t->c('down') : $t->c('flat'));

            Frame::panel($c, $t, $cx, $y, $cellW, $h, 14, ['fill' => $t->c('surface'), 'border_alpha' => 0.14]);
            $c->roundRect($cx + $cellW - 6, $y + 10, 4, $h - 20, 2, $color, 0.9);

            $mid = $y + $h / 2;
            $c->text($label, $cx + $cellW - 18, $mid, 11.5, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');
            $c->text($this->dnum((string) $v), $cx + $cellW / 2 + 6, $mid, 22, $t->c('ink'), Canvas::W_NUM_BOLD, 'center', 1.0, 'num');

            $deltaText = ($diff > 0 ? '+' : ($diff < 0 ? '−' : '')) . $this->dnum((string) abs($diff));
            $dw = $c->textWidth($deltaText, 11.5, Canvas::W_NUM_BOLD) + 24;
            $dh = 24.0;
            $c->roundRect($cx + 14, $mid - $dh / 2, $dw, $dh, 7, $color, $diff === 0 ? 0.18 : 1.0);
            $c->text(
                $deltaText,
                $cx + 14 + $dw / 2,
                $mid,
                11.5,
                $diff === 0 ? $t->c('ink_dim') : '#FFFFFF',
                Canvas::W_NUM_BOLD,
                'center',
                1.0,
                'num'
            );
        }
    }

    /** نمودار ۳۰ روز اخیر */
    private function chart(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        if ($h < 60) {
            return;
        }
        Frame::panel($c, $t, $x, $y, $w, $h, 18);

        $pad = 18.0;
        $titleCy = $y + $pad + 8;
        $c->text('روند ۳۰ روز اخیر', $x + $w - $pad, $titleCy, 11.5, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');

        $series = array_values(array_map('intval', (array) ($this->data['series'] ?? [])));
        if (count($series) < 5) {
            return;
        }

        $min = min($series);
        $max = max($series);
        $span = max(1, $max - $min);
        $n = count($series);

        $chartX = $x + $pad + 34;
        $chartY = $titleCy + 16;
        $chartW = $w - $pad * 2 - 34;
        $chartH = $y + $h - $pad - 16 - $chartY;
        if ($chartH < 26) {
            return;
        }

        // خطوط راهنما و مقیاس عمودی
        foreach ([0.0, 0.5, 1.0] as $f) {
            $gy = $chartY + $chartH * $f;
            $c->dashedLine($chartX, $gy, $chartX + $chartW, $gy, $t->c('line_soft'), 1, 6, 6, 1.0);
            $v = (int) round($max - ($max - $min) * $f);
            $c->text($this->dnum((string) $v), $chartX - 10, $gy, 9.5, $t->c('ink_faint'), Canvas::W_NUM, 'right', 1.0, 'num');
        }

        $points = [];
        foreach ($series as $i => $v) {
            $points[] = [
                $chartX + $chartW * ($i / max(1, $n - 1)),
                $chartY + $chartH - (($v - $min) / $span) * $chartH,
            ];
        }

        $color = $this->zoneStrong($t, $this->zoneIndex((int) $this->data['value']));
        $c->areaGradient($points, $chartX, $chartY, $chartW, $chartH, $color, 0.24);
        $c->polyline($points, $color, 2.3, 1.0);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 5.2, $t->c('line'));
        $c->circle($last[0], $last[1], 3.4, '#FFFFFF');

        $labelY = $y + $h - $pad - 2;
        $c->text('۳۰ روز پیش', $chartX, $labelY, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');
        $c->text('امروز', $chartX + $chartW, $labelY, 9.5, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
    }
}

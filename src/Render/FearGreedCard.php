<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\FearGreedProvider;

/**
 * کارت شاخص ترس و طمع — گیج شیشه‌ای، مقادیر تاریخی و روند ۳۰ روزه.
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

    public function __construct(array $data, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->data = $data;
    }

    public function render(): Canvas
    {
        $c = new Canvas(1200, 505, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header($c, $t, $rect, 'شاخص ترس و طمع بازار', $this->dateLine(), 'Fear & Greed');

        $bodyH = $rect['y'] + $rect['h'] - $top;
        $gaugeW = $rect['w'] * 0.56;
        $sideW = $rect['w'] - $gaugeW - 16;

        // ستون راست: گیج
        $this->gauge($c, $t, $rect['x'] + $sideW + 16, $top, $gaugeW, $bodyH);

        // ستون چپ: تاریخچه + روند
        $this->history($c, $t, $rect['x'], $top, $sideW, $bodyH);

        return $c;
    }

    /** رنگ ناحیه؛ در تم مونو به طیف خاکستری تا سفید تبدیل می‌شود */
    private function zoneColor(Theme $t, int $index, string $original): string
    {
        if (!$t->isMono()) {
            return $original;
        }

        return ['#5E6470', '#828996', '#A6ADB8', '#CDD3DC', '#FFFFFF'][$index] ?? '#FFFFFF';
    }

    private function gauge(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $value = max(0, min(100, (int) $this->data['value']));
        $zone  = $this->data['zone'];
        $zoneIndex = $this->zoneIndex($value);
        $zoneColor = $this->zoneColor($t, $zoneIndex, (string) $zone['color']);

        $c->glass($x, $y, $w, $h, 20, ['fill' => 0.05, 'border' => 0.12, 'tint' => $t->c('tint')]);

        $cx = $x + $w / 2;
        $radius = min($w * 0.33, $h * 0.46);
        $cy = $y + $h * 0.60;
        $thickness = $radius * 0.16;

        // مسیر پس‌زمینه
        $c->ring($cx, $cy, $radius, $thickness + 3, '#FFFFFF', 179, 361, 0.05);

        $bounds = [0, 25, 45, 56, 76, 101];
        foreach (FearGreedProvider::ZONES as $i => $zoneDef) {
            $a1 = 180 + $bounds[$i] * 1.8 + 0.9;
            $a2 = 180 + ($bounds[$i + 1] - 1) * 1.8 - 0.9;
            $c->ring($cx, $cy, $radius, $thickness, $this->zoneColor($t, $i, $zoneDef['color']), $a1, $a2, 0.95);
        }

        // مدرج‌ها
        for ($v = 0; $v <= 100; $v += 5) {
            $ang = deg2rad(180 + $v * 1.8);
            $major = $v % 25 === 0;
            $r1 = $radius + $thickness / 2 + 4;
            $r2 = $r1 + ($major ? 8 : 4);
            $c->line(
                $cx + cos($ang) * $r1,
                $cy + sin($ang) * $r1,
                $cx + cos($ang) * $r2,
                $cy + sin($ang) * $r2,
                $t->c('ink_dim'),
                $major ? 1.6 : 1,
                $major ? 0.75 : 0.35
            );
            if ($major) {
                $lr = $r2 + 12;
                $c->text(
                    $this->dnum((string) $v),
                    $cx + cos($ang) * $lr,
                    $cy + sin($ang) * $lr,
                    9.5,
                    $t->c('ink_faint'),
                    Canvas::W_SEMIBOLD,
                    'center',
                    1.0,
                    'middle'
                );
            }
        }

        // عقربه
        $ang = deg2rad(180 + $value * 1.8);
        $tip = $radius + $thickness / 2 - 1;
        $baseR = 9.0;
        $perp = $ang + M_PI / 2;
        $c->polygon([
            [$cx + cos($ang) * $tip, $cy + sin($ang) * $tip],
            [$cx + cos($perp) * $baseR * 0.45, $cy + sin($perp) * $baseR * 0.45],
            [$cx - cos($ang) * $baseR * 0.8, $cy - sin($ang) * $baseR * 0.8],
            [$cx - cos($perp) * $baseR * 0.45, $cy - sin($perp) * $baseR * 0.45],
        ], $t->c('ink'), 0.95);
        $c->circle($cx, $cy, $baseR, $t->c('ink'));
        $c->circle($cx, $cy, $baseR - 3.4, $t->c('bg_from'));

        // مقدار و برچسب
        $c->text($this->dnum((string) $value), $cx, $cy + 52, 33, $t->c('ink'), Canvas::W_BLACK, 'center', 1.0, 'middle');

        $label = (string) $zone['fa'];
        $labelW = $c->textWidth($label, 13, Canvas::W_BOLD) + 32;
        $legendY = $y + $h - 16;
        // برچسب ناحیه هیچ‌وقت روی راهنمای پایین کارت نمی‌افتد
        $pillY = min($cy + 92, $legendY - 20 - 26);
        $c->roundRect($cx - $labelW / 2, $pillY, $labelW, 26, 9, $zoneColor, 0.16);
        $c->strokeRoundRect($cx - $labelW / 2, $pillY, $labelW, 26, 9, $zoneColor, 1, 0.40);
        $c->text($label, $cx, $pillY + 13, 13, $zoneColor, Canvas::W_BOLD, 'center', 1.0, 'middle');

        $this->legend($c, $t, $cx, $legendY, $w - 24);
    }

    private function legend(Canvas $c, Theme $t, float $cx, float $y, float $maxWidth): void
    {
        $size = 9.0;
        $dot = 4.0;
        $gap = 13.0;

        $items = [];
        $total = 0.0;
        foreach (FearGreedProvider::ZONES as $i => $zoneDef) {
            $tw = $c->textWidth($zoneDef['fa'], $size, Canvas::W_SEMIBOLD);
            $items[] = ['fa' => $zoneDef['fa'], 'color' => $this->zoneColor($t, $i, $zoneDef['color']), 'w' => $tw];
            $total += $tw + $dot * 2 + 5 + $gap;
        }
        $total -= $gap;
        if ($total > $maxWidth) {
            return;
        }

        $cursor = $cx + $total / 2;
        foreach ($items as $item) {
            $c->circle($cursor - $dot, $y, $dot, $item['color'], 0.95);
            $c->text($item['fa'], $cursor - $dot * 2 - 5, $y, $size, $t->c('ink_faint'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');
            $cursor -= $item['w'] + $dot * 2 + 5 + $gap;
        }
    }

    private function history(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $history = (array) ($this->data['history'] ?? []);
        $items = [];
        foreach (self::HISTORY_LABELS as $key => $label) {
            if (isset($history[$key])) {
                $items[] = [
                    'label' => $label,
                    'value' => (int) $history[$key]['value'],
                    'zone'  => $history[$key]['zone'],
                ];
            }
        }

        $gap = 12.0;
        $count = max(1, count($items));
        $cardW = ($w - $gap * ($count - 1)) / $count;
        $cardH = min(104.0, $h * 0.30);

        foreach ($items as $i => $item) {
            $cx = $x + $w - ($i + 1) * $cardW - $i * $gap;
            $this->historyCard($c, $t, $item, $cx, $y, $cardW, $cardH);
        }

        $series = array_values(array_map('intval', (array) ($this->data['series'] ?? [])));
        $chartY = $y + $cardH + $gap;
        $chartH = $y + $h - $chartY;
        if (count($series) >= 5 && $chartH > 60) {
            $this->trend($c, $t, $series, $x, $chartY, $w, $chartH);
        }
    }

    private function historyCard(Canvas $c, Theme $t, array $item, float $x, float $y, float $w, float $h): void
    {
        $zoneIndex = $this->zoneIndex((int) $item['value']);
        $color = $this->zoneColor($t, $zoneIndex, (string) $item['zone']['color']);

        $c->glass($x, $y, $w, $h, 14, ['fill' => 0.06, 'border' => 0.12, 'tint' => $t->c('tint')]);
        $c->text($item['label'], $x + $w / 2, $y + 16, 9.5, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'center', 1.0, 'middle');

        // حلقه‌ی کوچک با عدد ریز داخلش
        $ringR = min($w * 0.15, $h * 0.20);
        $ringCx = $x + $w / 2;
        $ringCy = $y + $h * 0.54;
        $c->ring($ringCx, $ringCy, $ringR, $ringR * 0.30, '#FFFFFF', 0, 360, 0.10);
        $sweep = 360 * max(0.02, min(1, $item['value'] / 100));
        $c->ring($ringCx, $ringCy, $ringR, $ringR * 0.30, $color, -90, -90 + $sweep, 0.95);
        $c->text(
            $this->dnum((string) $item['value']),
            $ringCx,
            $ringCy,
            $ringR * 0.80,
            $t->c('ink'),
            Canvas::W_BOLD,
            'center',
            1.0,
            'middle'
        );

        $c->textFit(
            (string) $item['zone']['fa'],
            $ringCx,
            $y + $h - 13,
            $w - 14,
            9,
            $t->isMono() ? $t->c('ink_dim') : $color,
            Canvas::W_SEMIBOLD,
            'center',
            8,
            1.0,
            'middle'
        );
    }

    private function trend(Canvas $c, Theme $t, array $series, float $x, float $y, float $w, float $h): void
    {
        $c->glass($x, $y, $w, $h, 16, ['fill' => 0.06, 'border' => 0.12, 'tint' => $t->c('tint')]);

        $padX = 16.0;
        $padTop = 26.0;
        $padBottom = 14.0;
        $innerW = $w - $padX * 2;
        $innerH = $h - $padTop - $padBottom;

        $c->text('۳۰ روز اخیر', $x + $w - $padX, $y + 15, 10, $t->c('ink_dim'), Canvas::W_SEMIBOLD, 'right', 1.0, 'middle');

        $min = min($series);
        $max = max($series);
        $span = max(1, $max - $min);
        $n = count($series);

        $points = [];
        foreach ($series as $i => $v) {
            $points[] = [
                $x + $padX + $innerW * ($i / max(1, $n - 1)),
                $y + $padTop + $innerH - (($v - $min) / $span) * $innerH,
            ];
        }

        $area = $points;
        $area[] = [$x + $padX + $innerW, $y + $padTop + $innerH];
        $area[] = [$x + $padX, $y + $padTop + $innerH];
        $c->polygon($area, $t->c('accent'), 0.10);
        $c->polyline($points, $t->c('accent'), 1.7, 0.9);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 3, $t->c('accent'));

        $c->text($this->dnum((string) $max), $x + $padX, $y + $padTop - 8, 9, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');
        $c->text($this->dnum((string) $min), $x + $padX, $y + $h - $padBottom - 6, 9, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');
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
}

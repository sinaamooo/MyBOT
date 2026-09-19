<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\FearGreedProvider;

/**
 * کارت شاخص ترس و طمع: گیج عقربه‌ای + مقادیر تاریخی + نمودار ۳۰ روزه.
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
        $c = new Canvas(1200, 675, $this->quality);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['brand' => $this->brand, 'footer' => $this->footer]);
        $top = Frame::panelHeader(
            $c,
            $t,
            $rect,
            'شاخص ترس و طمع بازار',
            $this->dateLine(),
            'Fear & Greed Index'
        );

        $bodyH = $rect['y'] + $rect['h'] - $top - 26;
        $splitX = $rect['x'] + $rect['w'] * 0.385;

        // ستون راست: گیج
        $this->gauge($c, $t, $splitX + 14, $top, $rect['x'] + $rect['w'] - 30 - ($splitX + 14), $bodyH);

        // خط جداکننده
        $c->dashedLine($splitX, $top + 10, $splitX, $top + $bodyH - 10, $t->c('line'), 2, 7, 7);

        // ستون چپ: تاریخچه و نمودار
        $this->history($c, $t, $rect['x'] + 30, $top, $splitX - $rect['x'] - 44, $bodyH);

        return $c;
    }

    private function gauge(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $value = max(0, min(100, (int) $this->data['value']));
        $zone  = $this->data['zone'];

        $cx = $x + $w / 2;
        $cy = $y + $h * 0.50;
        $radius = min($w * 0.36, $h * 0.44);
        $thickness = $radius * 0.21;

        // مسیر خاکستری پشت قوس
        $c->ring($cx, $cy, $radius, $thickness + 5, $t->c('panel_alt'), 178, 362, 0.9);

        // قوس‌های رنگی ناحیه‌ها
        $bounds = [0, 25, 45, 56, 76, 101];
        foreach (FearGreedProvider::ZONES as $i => $zoneDef) {
            $a1 = 180 + $bounds[$i] * 1.8 + 0.7;
            $a2 = 180 + ($bounds[$i + 1] - 1) * 1.8 - 0.7;
            $c->ring($cx, $cy, $radius, $thickness, $zoneDef['color'], $a1, $a2, 0.97);
        }

        // شیارهای مدرج و اعداد
        for ($v = 0; $v <= 100; $v += 5) {
            $ang = deg2rad(180 + $v * 1.8);
            $isMajor = $v % 25 === 0;
            $r1 = $radius + $thickness / 2 + 5;
            $r2 = $r1 + ($isMajor ? 12 : 6);
            $c->line(
                $cx + cos($ang) * $r1,
                $cy + sin($ang) * $r1,
                $cx + cos($ang) * $r2,
                $cy + sin($ang) * $r2,
                $t->c('ink_soft'),
                $isMajor ? 2.4 : 1.4,
                $isMajor ? 0.8 : 0.4
            );
            if ($isMajor) {
                $lr = $r2 + 16;
                $c->text(
                    $this->dnum((string) $v),
                    $cx + cos($ang) * $lr,
                    $cy + sin($ang) * $lr - 9,
                    14,
                    $t->c('ink_soft'),
                    Canvas::W_SEMIBOLD,
                    'center'
                );
            }
        }

        // عقربه
        $ang = deg2rad(180 + $value * 1.8);
        $tip = $radius + $thickness / 2 - 2;
        $baseR = 15.0;
        $perp = $ang + M_PI / 2;
        $c->polygon([
            [$cx + cos($ang) * $tip, $cy + sin($ang) * $tip],
            [$cx + cos($perp) * $baseR * 0.5, $cy + sin($perp) * $baseR * 0.5],
            [$cx - cos($ang) * $baseR * 0.85, $cy - sin($ang) * $baseR * 0.85],
            [$cx - cos($perp) * $baseR * 0.5, $cy - sin($perp) * $baseR * 0.5],
        ], $t->c('ink'), 0.95);
        $c->circle($cx, $cy, $baseR, $t->c('ink'));
        $c->circle($cx, $cy, $baseR - 5.5, '#FFFFFF');

        // مقدار و برچسب ناحیه
        $c->text($this->dnum((string) $value), $cx, $cy + 18, 62, $zone['color'], Canvas::W_BLACK, 'center');
        $labelW = $c->textWidth($zone['fa'], 23, Canvas::W_BLACK) + 50;
        $pillY = $cy + 92;
        $c->roundRect($cx - $labelW / 2, $pillY, $labelW, 42, 21, $zone['color'], 0.15);
        $c->strokeRoundRect($cx - $labelW / 2, $pillY, $labelW, 42, 21, $zone['color'], 1.4, 0.55);
        $c->text($zone['fa'], $cx, $pillY + 8, 23, $zone['color'], Canvas::W_BLACK, 'center');

        // راهنمای رنگ‌ها
        $this->legend($c, $t, $cx, $y + $h - 22, $w);
    }

    /** راهنمای ناحیه‌های شاخص */
    private function legend(Canvas $c, Theme $t, float $cx, float $y, float $maxWidth): void
    {
        $size = 13.0;
        $dot = 5.5;
        $gap = 16.0;
        $items = [];
        $total = 0.0;
        foreach (FearGreedProvider::ZONES as $zoneDef) {
            $tw = $c->textWidth($zoneDef['fa'], $size, Canvas::W_SEMIBOLD);
            $items[] = ['fa' => $zoneDef['fa'], 'color' => $zoneDef['color'], 'w' => $tw];
            $total += $tw + $dot * 2 + 7 + $gap;
        }
        $total -= $gap;
        if ($total > $maxWidth) {
            return;
        }

        $cursor = $cx + $total / 2; // از راست به چپ
        foreach ($items as $item) {
            $c->circle($cursor - $dot, $y + 7, $dot, $item['color']);
            $c->text($item['fa'], $cursor - $dot * 2 - 7, $y, $size, $t->c('ink_soft'), Canvas::W_SEMIBOLD, 'right');
            $cursor -= $item['w'] + $dot * 2 + 7 + $gap;
        }
    }

    private function history(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $c->text('روند اخیر شاخص', $x + $w, $y + 4, 20, $t->c('ink'), Canvas::W_BOLD, 'right');

        $history = (array) ($this->data['history'] ?? []);
        $items = [];
        foreach (self::HISTORY_LABELS as $key => $label) {
            if (isset($history[$key])) {
                $items[] = ['label' => $label, 'value' => (int) $history[$key]['value'], 'zone' => $history[$key]['zone']];
            }
        }

        $count = max(1, count($items));
        $gap = 14.0;
        $cardW = ($w - $gap * ($count - 1)) / $count;
        $cardH = min(178.0, $h * 0.53);
        $topY = $y + 40;

        foreach ($items as $i => $item) {
            $cx = $x + $w - ($i + 1) * $cardW - $i * $gap;
            $this->historyCard($c, $t, $item, $cx, $topY, $cardW, $cardH);
        }

        // نمودار ۳۰ روزه
        $series = array_values(array_map('intval', (array) ($this->data['series'] ?? [])));
        $chartY = $topY + $cardH + 26;
        $chartH = $y + $h - $chartY - 4;
        if (count($series) >= 5 && $chartH > 60) {
            $this->trend($c, $t, $series, $x, $chartY, $w, $chartH);
        }
    }

    private function historyCard(Canvas $c, Theme $t, array $item, float $x, float $y, float $w, float $h): void
    {
        $zone = $item['zone'];
        $c->roundRect($x, $y, $w, $h, 18, $t->c('panel_alt'));
        $c->strokeRoundRect($x, $y, $w, $h, 18, $t->c('line'), 1.2, 0.9);

        $c->text($item['label'], $x + $w / 2, $y + 14, 15, $t->c('ink_soft'), Canvas::W_SEMIBOLD, 'center');

        $ringR = min($w * 0.28, $h * 0.30);
        $ringCx = $x + $w / 2;
        $ringCy = $y + $h * 0.56;
        $c->ring($ringCx, $ringCy, $ringR, $ringR * 0.34, $t->c('line'), 0, 360, 0.9);
        $sweep = 360 * max(0.02, min(1, $item['value'] / 100));
        $c->ring($ringCx, $ringCy, $ringR, $ringR * 0.34, $zone['color'], -90, -90 + $sweep, 1.0);
        $c->text($this->dnum((string) $item['value']), $ringCx, $ringCy - $ringR * 0.52, $ringR * 0.95, $t->c('ink'), Canvas::W_BLACK, 'center');

        $c->textFit($zone['fa'], $ringCx, $y + $h - 30, $w - 16, 14, $zone['color'], Canvas::W_BOLD, 'center', 10);
    }

    private function trend(Canvas $c, Theme $t, array $series, float $x, float $y, float $w, float $h): void
    {
        $c->roundRect($x, $y, $w, $h, 18, $t->c('panel_alt'));
        $c->strokeRoundRect($x, $y, $w, $h, 18, $t->c('line'), 1.2, 0.9);

        $padX = 16.0;
        $padTop = 30.0;
        $padBottom = 16.0;
        $innerW = $w - $padX * 2;
        $innerH = $h - $padTop - $padBottom;

        $c->text('۳۰ روز اخیر', $x + $w - $padX, $y + 8, 14, $t->c('ink_soft'), Canvas::W_SEMIBOLD, 'right');

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
        $c->polygon($area, $t->c('value'), 0.14);
        $c->polyline($points, $t->c('value'), 2.6);

        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 5.4, '#FFFFFF');
        $c->circle($last[0], $last[1], 3.6, $t->c('value'));

        $c->text($this->dnum((string) $max), $x + $padX, $y + $padTop - 18, 13, $t->c('ink_soft'), Canvas::W_MEDIUM, 'left');
        $c->text($this->dnum((string) $min), $x + $padX, $y + $h - $padBottom - 16, 13, $t->c('ink_soft'), Canvas::W_MEDIUM, 'left');
    }
}

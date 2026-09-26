<?php
declare(strict_types=1);

namespace Nikto\Render;

use Nikto\Data\FearGreedProvider;

final class FearGreedCard extends Card
{
    private array $data;

    private const HISTORY_LABELS = [
        'yesterday' => 'دیروز',
        'week'      => '۷ روز پیش',
        'month'     => '۱ ماه پیش',
    ];

    private const BOUNDS = [0, 25, 45, 56, 76, 101];

    private const TOP_H = 188.0;
    private const GAP   = 16.0;

    public function __construct(array $data, ?string $theme = null, array $options = [])
    {
        parent::__construct($theme, $options);
        $this->data = $data;
    }

    public function render(): Canvas
    {
        $c = new Canvas(1200, 616, $this->quality);
        $c->setOutputScale($this->outputScale);
        $t = $this->theme;

        $rect = Frame::draw($c, $t, ['footer' => $this->footer]);
        $top = Frame::header($c, $t, $rect, 'شاخص ترس و طمع بازار', $this->dateLine(), 'Fear & Greed Index');

        $this->today($c, $t, $rect['x'], $top, $rect['w'], self::TOP_H);

        $chartY = $top + self::TOP_H + self::GAP;
        $this->trend($c, $t, $rect['x'], $chartY, $rect['w'], $rect['y'] + $rect['h'] - $chartY);

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

    private function zoneColor(Theme $t, int $index): string
    {
        $down = $t->c('down');
        $up = $t->c('up');

        return [
            $down,
            Canvas::mix($down, '#FFFFFF', 0.45),
            Canvas::mix($t->c('flat'), '#FFFFFF', 0.35),
            Canvas::mix($up, '#FFFFFF', 0.45),
            $up,
        ][$index] ?? $t->c('ink');
    }

    private function zoneStrong(Theme $t, int $index): string
    {
        return match (true) {
            $index <= 1 => $t->c('down'),
            $index === 2 => $t->c('ink_dim'),
            default     => $t->c('up'),
        };
    }

    private function today(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        $value = max(0, min(100, (int) $this->data['value']));
        $index = $this->zoneIndex($value);

        Frame::panel($c, $t, $x, $y, $w, $h, 20);

        $pad = 32.0;
        $right = $x + $w - $pad;
        $c->text('شاخص امروز', $right, $y + 38, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
        $c->text($this->dnum((string) $value), $right, $y + 94, 50, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');
        $c->text((string) $this->data['zone']['fa'], $right, $y + 146, 15, $this->zoneStrong($t, $index), Canvas::W_BOLD, 'right', 1.0, 'middle');

        $barL = $x + $pad;
        $barR = $x + $w - 260;
        $barW = $barR - $barL;
        $barY = $y + 94;
        $gap = 3.0;
        $usable = $barW - $gap * 4;

        foreach (array_keys(FearGreedProvider::ZONES) as $i) {
            $from = self::BOUNDS[$i];
            $to = self::BOUNDS[$i + 1];
            $segX = $barL + $usable * ($from / 100) + $gap * $i;
            $c->roundRect($segX, $barY - 4, $usable * (($to - $from) / 100), 8, 4, $this->zoneColor($t, $i), 1.0);
        }

        $markX = $barL + $usable * ($value / 100) + $gap * min(4, $index);
        $c->circle($markX, $barY, 11, '#FFFFFF');
        $c->circle($markX, $barY, 8, $t->c('line'));

        $c->text('ترس شدید', $barL, $barY + 32, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');
        $c->text('طمع شدید', $barR, $barY + 32, 10, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
    }

    private function trend(Canvas $c, Theme $t, float $x, float $y, float $w, float $h): void
    {
        Frame::panel($c, $t, $x, $y, $w, $h, 20);

        $pad = 32.0;
        $rowY = $y + 34;
        $c->text('۳۰ روز اخیر', $x + $pad, $rowY, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'left', 1.0, 'middle');

        $history = (array) ($this->data['history'] ?? []);
        $cursor = $x + $w - $pad;
        foreach (self::HISTORY_LABELS as $key => $label) {
            if (!isset($history[$key])) {
                continue;
            }
            $c->text($label, $cursor, $rowY, 11, $t->c('ink_faint'), Canvas::W_MEDIUM, 'right', 1.0, 'middle');
            $cursor -= $c->textWidth($label, 11, Canvas::W_MEDIUM) + 8;
            $valueText = $this->dnum((string) (int) $history[$key]['value']);
            $c->text($valueText, $cursor, $rowY, 15, $t->c('ink'), Canvas::W_NUM_BOLD, 'right', 1.0, 'num');
            $cursor -= $c->textWidth($valueText, 15, Canvas::W_NUM_BOLD) + 34;
        }

        $series = array_values(array_map('intval', (array) ($this->data['series'] ?? [])));
        $n = count($series);
        if ($n < 5) {
            return;
        }

        $chartX = $x + $pad;
        $chartW = $w - $pad * 2;
        $chartY = $rowY + 28;
        $chartH = $y + $h - 30 - $chartY;
        $min = min($series);
        $span = max(1, max($series) - $min);

        $points = [];
        foreach ($series as $i => $v) {
            $points[] = [$chartX + $chartW * ($i / ($n - 1)), $chartY + $chartH - (($v - $min) / $span) * $chartH];
        }

        $color = $this->zoneStrong($t, $this->zoneIndex((int) $this->data['value']));
        $c->areaGradient($points, $chartX, $chartY, $chartW, $chartH, $color, 0.10);
        $c->polyline($points, $color, 2.0, 1.0);
        $last = $points[$n - 1];
        $c->circle($last[0], $last[1], 4.5, $color);
    }
}

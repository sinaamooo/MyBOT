<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Color Shift: continuous hue rotation (rainbow) or hue swing. */
final class ColorShift extends Effect
{
    public function key(): string { return 'color_shift'; }
    public function nameFa(): string { return 'تغییر رنگ (Color Shift)'; }
    public function nameEn(): string { return 'Color Shift'; }
    public function icon(): string { return '🌈'; }
    public function category(): string { return 'color'; }
    public function description(): string { return 'تغییر تدریجی رنگ طرح (رنگین‌کمانی یا نوسانی)'; }

    protected function extraSchema(): array
    {
        return [
            'style' => ['type' => 'enum', 'options' => ['rainbow', 'swing'], 'default' => 'rainbow', 'label' => 'نوع',
                'labels' => ['rainbow' => 'رنگین‌کمانی', 'swing' => 'نوسانی']],
            'direction' => ['type' => 'enum', 'options' => ['forward', 'backward'], 'default' => 'forward', 'label' => 'جهت',
                'labels' => ['forward' => 'رو به جلو', 'backward' => 'معکوس']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $sign = $c->param('direction') === 'backward' ? -1 : 1;
        if ($c->param('style') === 'swing') {
            $s->hue += $sign * (25 + 95 * $c->intensity()) * sin(2 * M_PI * $c->q());
            return;
        }
        $s->hue += $sign * 360.0 * $c->cycles() * $c->p;
    }
}

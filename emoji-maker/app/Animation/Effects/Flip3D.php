<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** 3D flip: rotation around the vertical (or horizontal) axis like a spinning coin. */
final class Flip3D extends Effect
{
    public function key(): string { return 'flip3d'; }
    public function nameFa(): string { return 'چرخش سه‌بعدی (سکه‌ای)'; }
    public function nameEn(): string { return '3D Flip'; }
    public function icon(): string { return '🪙'; }
    public function description(): string { return 'چرخش دور محور عمودی یا افقی با سایه‌روشن سه‌بعدی'; }

    protected function extraSchema(): array
    {
        return [
            'axis' => ['type' => 'enum', 'options' => ['y', 'x'], 'default' => 'y', 'label' => 'محور',
                'labels' => ['y' => 'عمودی (سکه)', 'x' => 'افقی (ورق)']],
            'style' => ['type' => 'enum', 'options' => ['eased', 'linear', 'peek'], 'default' => 'eased', 'label' => 'نوع',
                'labels' => ['eased' => 'نرم با مکث', 'linear' => 'یکنواخت', 'peek' => 'نیم‌چرخش و بازگشت']],
            'mirror' => ['type' => 'bool', 'default' => false, 'label' => 'نمایش پشت (آینه‌ای)'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $cycles = $c->cycles();
        $style = $c->param('style');
        if ($style === 'peek') {
            $angle = (35 + 45 * $c->intensity()) * sin(2 * M_PI * $c->q());
        } else {
            $pos = $c->p * $cycles;
            $turn = floor($pos);
            $q = $pos - $turn;
            $angle = 360.0 * ($style === 'linear' ? $q : Easing::inOutCubic($q));
        }
        $cos = cos(deg2rad($angle));
        $factor = (bool) $c->param('mirror', false) ? $cos : abs($cos);
        // never collapse to an invisible line
        if (abs($factor) < 0.02) {
            $factor = $factor < 0 ? -0.02 : 0.02;
        }
        if ($c->param('axis') === 'x') {
            $s->sy *= $factor;
        } else {
            $s->sx *= $factor;
        }
        $s->shade = max($s->shade, (1 - abs($cos)) * (0.25 + 0.35 * $c->intensity()));
    }
}

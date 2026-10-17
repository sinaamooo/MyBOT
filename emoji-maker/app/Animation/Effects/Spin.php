<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Spin: full turns (eased or linear) or partial back-and-forth rotation. */
final class Spin extends Effect
{
    public function key(): string { return 'spin'; }
    public function nameFa(): string { return 'چرخش (Spin)'; }
    public function nameEn(): string { return 'Spin'; }
    public function icon(): string { return '🔄'; }
    public function description(): string { return 'چرخش کامل یا جزئی با حلقه‌ی بدون پرش'; }

    protected function extraSchema(): array
    {
        return [
            'direction' => ['type' => 'enum', 'options' => ['cw', 'ccw'], 'default' => 'cw', 'label' => 'جهت',
                'labels' => ['cw' => 'ساعتگرد', 'ccw' => 'پادساعتگرد']],
            'style' => ['type' => 'enum', 'options' => ['full', 'eased', 'partial'], 'default' => 'eased', 'label' => 'نوع چرخش',
                'labels' => ['full' => 'کامل یکنواخت', 'eased' => 'کامل نرم', 'partial' => 'جزئی (رفت و برگشت)']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $sign = $c->param('direction') === 'ccw' ? -1 : 1;
        $cycles = $c->cycles();
        $style = $c->param('style');
        if ($style === 'partial') {
            $max = 8 + 37 * $c->intensity();
            $s->rot += $sign * $max * sin(2 * M_PI * $c->q());
            return;
        }
        $pos = $c->p * $cycles;
        $turn = floor($pos);
        $q = $pos - $turn;
        $e = $style === 'eased' ? Easing::inOutCubic($q) : $q;
        $s->rot += $sign * 360.0 * ($turn + $e);
    }
}

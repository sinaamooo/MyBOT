<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Pulse: smooth grow / shrink (breathing). */
final class Pulse extends Effect
{
    public function key(): string { return 'pulse'; }
    public function nameFa(): string { return 'تپش (Pulse)'; }
    public function nameEn(): string { return 'Pulse'; }
    public function icon(): string { return '💓'; }
    public function description(): string { return 'بزرگ و کوچک شدن نرم و پیوسته'; }

    protected function extraSchema(): array
    {
        return [
            'direction' => ['type' => 'enum', 'options' => ['out', 'in'], 'default' => 'out', 'label' => 'جهت',
                'labels' => ['out' => 'بزرگ‌شدن', 'in' => 'کوچک‌شدن']],
            'repeat' => ['type' => 'int', 'min' => 1, 'max' => 8, 'default' => 2, 'label' => 'تعداد تکرار'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $k = Easing::bump($c->q());
        $amp = 0.05 + 0.20 * $c->intensity();
        $s->scale *= $c->param('direction') === 'in' ? 1 - $amp * 0.6 * $k : 1 + $amp * $k;
        $s->glow *= 1 + 0.35 * $k;
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Swing: pendulum rotation around the top edge. */
final class Swing extends Effect
{
    public function key(): string { return 'swing'; }
    public function nameFa(): string { return 'تاب خوردن (Swing)'; }
    public function nameEn(): string { return 'Swing'; }
    public function icon(): string { return '🪀'; }
    public function description(): string { return 'حرکت آونگی از لبه‌ی بالا'; }

    protected function extraSchema(): array
    {
        return [
            'pivot' => ['type' => 'enum', 'options' => ['top', 'bottom', 'center'], 'default' => 'top', 'label' => 'محور',
                'labels' => ['top' => 'بالا', 'bottom' => 'پایین', 'center' => 'وسط']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $deg = (6 + 22 * $c->intensity()) * sin(2 * M_PI * $c->q());
        $pivot = $c->param('pivot');
        $py = $pivot === 'top' ? -$c->height() / 2 : ($pivot === 'bottom' ? $c->height() / 2 : 0.0);
        self::rotateAroundPivot($s, $deg, 0.0, $py * $s->scale);
    }
}

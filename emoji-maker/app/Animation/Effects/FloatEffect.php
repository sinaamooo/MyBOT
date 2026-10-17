<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Float: gentle hovering up and down. */
final class FloatEffect extends Effect
{
    public function key(): string { return 'float'; }
    public function nameFa(): string { return 'شناور (Float)'; }
    public function nameEn(): string { return 'Float'; }
    public function icon(): string { return '🎈'; }
    public function description(): string { return 'معلق شدن آرام در هوا'; }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $amp = (0.025 + 0.07 * $c->intensity()) * 512;
        $s->dy += -$amp * sin(2 * M_PI * $q);
        $s->rot += 2.5 * $c->intensity() * sin(4 * M_PI * $q);
    }
}

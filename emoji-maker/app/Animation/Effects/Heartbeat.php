<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Heartbeat: double beat pulse. */
final class Heartbeat extends Effect
{
    public function key(): string { return 'heartbeat'; }
    public function nameFa(): string { return 'ضربان قلب'; }
    public function nameEn(): string { return 'Heartbeat'; }
    public function icon(): string { return '❤️'; }
    public function description(): string { return 'دو تپش پشت سر هم مثل ضربان قلب'; }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $k = Easing::gauss($q, 0.2, 0.06) + 0.75 * Easing::gauss($q, 0.42, 0.06);
        $amp = 0.06 + 0.2 * $c->intensity();
        $s->scale *= 1 + $amp * $k;
        $s->glow *= 1 + 0.4 * $k;
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** 3D tilt: the design leans left/right in depth with light shading (no full turn). */
final class Tilt3D extends Effect
{
    public function key(): string { return 'tilt3d'; }
    public function nameFa(): string { return 'تکان سه‌بعدی (Tilt)'; }
    public function nameEn(): string { return '3D Tilt'; }
    public function icon(): string { return '🧊'; }
    public function description(): string { return 'خم شدن نرم به چپ و راست در عمق با سایه‌روشن'; }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $amp = 0.18 + 0.4 * $c->intensity();          // max "depth" angle factor
        $a = sin(2 * M_PI * $q);
        $s->sx *= 1 - $amp * 0.45 * abs($a);
        $s->rot += 4 * $c->intensity() * $a;
        $s->dx += 0.04 * 512 * $c->intensity() * $a;
        $s->dy -= 0.015 * 512 * $c->intensity() * abs($a);
        $s->shade = max($s->shade, 0.18 * abs($a) * $c->intensity());
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Jelly: wobbling squash and stretch. */
final class Jelly extends Effect
{
    public function key(): string { return 'jelly'; }
    public function nameFa(): string { return 'ژله‌ای (Jelly)'; }
    public function nameEn(): string { return 'Jelly'; }
    public function icon(): string { return '🍮'; }
    public function description(): string { return 'لرزش کشسان مثل ژله'; }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $env = sin(M_PI * $q);
        $w = sin(2 * M_PI * 3 * $q);
        $amp = (0.05 + 0.13 * $c->intensity()) * $env;
        $s->sx *= 1 + $amp * $w;
        $s->sy *= 1 - $amp * $w;
    }
}

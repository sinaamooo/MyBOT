<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Pop: quick shrink then an elastic punchy re-appearance. */
final class Pop extends Effect
{
    public function key(): string { return 'pop'; }
    public function nameFa(): string { return 'ظهور ضربه‌ای (Pop)'; }
    public function nameEn(): string { return 'Pop'; }
    public function icon(): string { return '💥'; }
    public function category(): string { return 'enter'; }
    public function description(): string { return 'ظاهر شدن ناگهانی با حالت فنری'; }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        if ($q < 0.12) {
            $t = Easing::inBack($q / 0.12, 2.0);
            $s->scale *= max(0.0, 1 - $t);
            $s->opacity *= 1 - Easing::clamp(($q - 0.08) / 0.04);
        } elseif ($q < 0.2) {
            $s->scale *= 0.0;
            $s->opacity *= 0.0;
        } elseif ($q < 0.6) {
            $t = ($q - 0.2) / 0.4;
            $amp = 0.6 + 0.8 * $c->intensity();
            $e = Easing::outElastic($t, 1.0);
            $e = 1 + ($e - 1) * $amp;
            $s->scale *= max(0.0, $e);
            $s->opacity *= Easing::clamp($t * 6);
        }
    }
}

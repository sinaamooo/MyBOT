<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Fade: disappears and comes back smoothly (or breathes). */
final class Fade extends Effect
{
    public function key(): string { return 'fade'; }
    public function nameFa(): string { return 'محو و ظاهر (Fade)'; }
    public function nameEn(): string { return 'Fade'; }
    public function icon(): string { return '🌫'; }
    public function category(): string { return 'enter'; }
    public function description(): string { return 'محو شدن و دوباره ظاهر شدن نرم'; }

    protected function extraSchema(): array
    {
        return [
            'style' => ['type' => 'enum', 'options' => ['outin', 'breathe'], 'default' => 'outin', 'label' => 'نوع',
                'labels' => ['outin' => 'محو و ظاهر', 'breathe' => 'تنفسی']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $min = 1 - $c->intensity();
        if ($c->param('style') === 'breathe') {
            $s->opacity *= 1 - (1 - $min) * Easing::bump($q);
            return;
        }
        // visible -> fade out -> hidden -> fade in -> visible
        $speed = (float) $c->param('speed', 1.0);
        $len = max(0.12, min(0.4, 0.3 / max(0.5, $speed)));
        $center = 0.5;
        $dist = abs($q - $center);
        $hold = 0.06;
        if ($dist <= $hold) {
            $v = 0.0;
        } elseif ($dist >= $hold + $len) {
            $v = 1.0;
        } else {
            $v = Easing::inOutSine(($dist - $hold) / $len);
        }
        $s->opacity *= $min + (1 - $min) * $v;
    }
}

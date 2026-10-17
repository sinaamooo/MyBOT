<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Bounce: soft jump with squash & stretch. */
final class Bounce extends Effect
{
    public function key(): string { return 'bounce'; }
    public function nameFa(): string { return 'پرش (Bounce)'; }
    public function nameEn(): string { return 'Bounce'; }
    public function icon(): string { return '🏀'; }
    public function description(): string { return 'پرش نرم با فشردگی هنگام فرود'; }

    protected function extraSchema(): array
    {
        return [
            'repeat' => ['type' => 'int', 'min' => 1, 'max' => 8, 'default' => 2, 'label' => 'تعداد پرش'],
            'squash' => ['type' => 'bool', 'default' => true, 'label' => 'فشردگی'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $h = $c->height();
        $height = (0.06 + 0.2 * $c->intensity()) * 512;
        $air = 0.82;
        $squash = (bool) $c->param('squash', true);
        if ($q < $air) {
            $t = $q / $air;
            $y = 4 * $t * (1 - $t);
            $s->dy -= $height * $y;
            if ($squash) {
                $stretch = 0.07 * $c->intensity() * (1 - abs(2 * $t - 1)) ;
                $s->sy *= 1 + $stretch;
                $s->sx *= 1 - $stretch * 0.6;
                $s->dy -= ($stretch * $h) / 2;
            }
        } elseif ($squash) {
            $k = Easing::bump(($q - $air) / (1 - $air));
            $amt = (0.08 + 0.14 * $c->intensity()) * $k;
            $s->sy *= 1 - $amt;
            $s->sx *= 1 + $amt * 0.8;
            $s->dy += ($amt * $h) / 2; // keep the bottom on the ground
        }
    }
}

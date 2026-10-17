<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Slide: exits to one side and re-enters from the other (or same) side. */
final class Slide extends Effect
{
    public function key(): string { return 'slide'; }
    public function nameFa(): string { return 'ورود کشویی (Slide)'; }
    public function nameEn(): string { return 'Slide'; }
    public function icon(): string { return '↔️'; }
    public function category(): string { return 'enter'; }
    public function description(): string { return 'ورود از چپ یا راست با حرکت نرم'; }
    public function allowsOverflow(): bool { return true; }

    protected function extraSchema(): array
    {
        return [
            'direction' => ['type' => 'enum', 'options' => ['left', 'right', 'left_back', 'right_back'], 'default' => 'left', 'label' => 'جهت',
                'labels' => ['left' => 'ورود از چپ', 'right' => 'ورود از راست', 'left_back' => 'چپ و بازگشت', 'right_back' => 'راست و بازگشت']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $dir = (string) $c->param('direction');
        $fromLeft = str_starts_with($dir, 'left');
        $back = str_ends_with($dir, '_back');
        // In vector mode objects must stay on the canvas: shorter distance + fade
        $dist = $c->isVector() ? (0.18 + 0.2 * $c->intensity()) * 512 : (0.75 + 0.35 * $c->intensity()) * 512;
        $enterSign = $fromLeft ? -1 : 1;           // where it enters from
        $exitSign = $back ? $enterSign : -$enterSign;
        $fade = $c->isVector();
        if ($q < 0.28) {
            $t = Easing::inBack($q / 0.28, 1.2);
            $s->dx += $exitSign * $dist * $t;
            if ($fade) {
                $s->opacity *= 1 - Easing::clamp($q / 0.28);
            }
        } elseif ($q < 0.36) {
            $s->dx += $enterSign * $dist;
            $s->opacity *= $fade ? 0.0 : 1.0;
        } elseif ($q < 0.72) {
            $t = Easing::outBack(($q - 0.36) / 0.36, 1.4);
            $s->dx += $enterSign * $dist * (1 - $t);
            if ($fade) {
                $s->opacity *= Easing::clamp(($q - 0.36) / 0.2);
            }
        }
    }
}

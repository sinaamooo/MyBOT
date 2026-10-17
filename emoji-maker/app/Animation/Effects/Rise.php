<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Rise: moves upward and fades out, then rises again from below. */
final class Rise extends Effect
{
    public function key(): string { return 'rise'; }
    public function nameFa(): string { return 'صعود (Rise)'; }
    public function nameEn(): string { return 'Rise'; }
    public function icon(): string { return '⬆️'; }
    public function category(): string { return 'enter'; }
    public function description(): string { return 'حرکت رو به بالا با ورود و خروج نرم'; }

    protected function extraSchema(): array
    {
        return [
            'direction' => ['type' => 'enum', 'options' => ['up', 'down'], 'default' => 'up', 'label' => 'جهت',
                'labels' => ['up' => 'رو به بالا', 'down' => 'رو به پایین']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $dist = (0.12 + 0.3 * $c->intensity()) * 512;
        $sign = $c->param('direction') === 'down' ? 1 : -1;
        // 0..0.3 move away + fade out ; 0.3..0.4 hidden ; 0.4..0.75 come back ; hold
        if ($q < 0.3) {
            $t = Easing::inCubic($q / 0.3);
            $s->dy += $sign * $dist * $t;
            $s->opacity *= 1 - $t;
        } elseif ($q < 0.4) {
            $s->opacity *= 0.0;
            $s->dy -= $sign * $dist;
        } elseif ($q < 0.75) {
            $t = Easing::outCubic(($q - 0.4) / 0.35);
            $s->dy -= $sign * $dist * (1 - $t);
            $s->opacity *= $t;
        }
    }
}

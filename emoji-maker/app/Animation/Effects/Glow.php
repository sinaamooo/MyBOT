<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Glow: breathing light / halo around the design. */
final class Glow extends Effect
{
    public function key(): string { return 'glow'; }
    public function nameFa(): string { return 'درخشش (Glow)'; }
    public function nameEn(): string { return 'Glow'; }
    public function icon(): string { return '🌟'; }
    public function category(): string { return 'light'; }
    public function description(): string { return 'هاله‌ی نورانی که نرم کم و زیاد می‌شود'; }
    public function requiresGlow(): bool { return true; }

    protected function extraSchema(): array
    {
        return [
            'color' => ['type' => 'color', 'default' => '#FFE066', 'label' => 'رنگ نور'],
            'repeat' => ['type' => 'int', 'min' => 1, 'max' => 8, 'default' => 2, 'label' => 'تعداد تکرار'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $k = Easing::bump($c->q());
        $min = 0.12 + 0.45 * (1 - $c->intensity());
        $s->glow *= 1 - (1 - $min) * $k;
        $s->scale *= 1 + 0.015 * (1 - $k);
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Flash: light blink / white flash / light sweep across the design. */
final class Flash extends Effect
{
    public function key(): string { return 'flash'; }
    public function nameFa(): string { return 'چشمک نور (Flash)'; }
    public function nameEn(): string { return 'Flash'; }
    public function icon(): string { return '⚡'; }
    public function category(): string { return 'light'; }
    public function description(): string { return 'چشمک نوری، فلاش سفید یا عبور برق از روی طرح'; }

    protected function extraSchema(): array
    {
        return [
            'style' => ['type' => 'enum', 'options' => ['flash', 'shine', 'strobe'], 'default' => 'shine', 'label' => 'نوع',
                'labels' => ['flash' => 'فلاش', 'shine' => 'عبور نور', 'strobe' => 'چشمک دوتایی']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $i = $c->intensity();
        $style = $c->param('style');
        if ($style === 'shine' && !$c->isVector()) {
            // band travels from -0.3 to 1.3 during the first 60% of the cycle
            if ($q < 0.6) {
                $s->shine = -0.3 + 1.6 * Easing::inOutSine($q / 0.6);
                $s->shineStrength = max($s->shineStrength, 0.45 + 0.55 * $i);
            }
            return;
        }
        if ($style === 'strobe') {
            $b = Easing::gauss($q, 0.3, 0.035) + Easing::gauss($q, 0.45, 0.035);
        } else {
            $b = Easing::gauss($q, 0.4, $style === 'shine' ? 0.1 : 0.07);
        }
        $s->bright = min(1.0, max($s->bright, $b * (0.35 + 0.6 * $i)));
        $s->glow *= 1 + 0.6 * $b;
    }
}

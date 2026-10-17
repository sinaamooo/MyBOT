<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Shake: short bursts of vibration. */
final class Shake extends Effect
{
    public function key(): string { return 'shake'; }
    public function nameFa(): string { return 'لرزش (Shake)'; }
    public function nameEn(): string { return 'Shake'; }
    public function icon(): string { return '📳'; }
    public function description(): string { return 'لرزش کوتاه و ریتمیک'; }

    protected function extraSchema(): array
    {
        return [
            'direction' => ['type' => 'enum', 'options' => ['horizontal', 'vertical', 'both', 'rotate'], 'default' => 'horizontal', 'label' => 'جهت',
                'labels' => ['horizontal' => 'افقی', 'vertical' => 'عمودی', 'both' => 'هر دو', 'rotate' => 'چرخشی']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        // vibration during the first 55% of each cycle, rest after
        $burst = 0.55;
        if ($q >= $burst) {
            return;
        }
        $t = $q / $burst;
        $env = sin(M_PI * $t);
        $osc = sin(2 * M_PI * 6 * $t);
        $osc2 = sin(2 * M_PI * 5 * $t + 1.3);
        $amp = (0.012 + 0.05 * $c->intensity()) * 512;
        $dir = $c->param('direction');
        if ($dir === 'horizontal' || $dir === 'both') {
            $s->dx += $amp * $env * $osc;
        }
        if ($dir === 'vertical' || $dir === 'both') {
            $s->dy += $amp * 0.8 * $env * ($dir === 'both' ? $osc2 : $osc);
        }
        $rotAmp = $dir === 'rotate' ? 6 + 14 * $c->intensity() : 1.5 + 3 * $c->intensity();
        $s->rot += $rotAmp * $env * ($dir === 'rotate' ? $osc : $osc2);
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;

/** Typewriter: characters (or logo parts) appear one by one with a caret. */
final class Typewriter extends Effect
{
    public function key(): string { return 'typewriter'; }
    public function nameFa(): string { return 'تایپ تدریجی (Typewriter)'; }
    public function nameEn(): string { return 'Typewriter'; }
    public function icon(): string { return '⌨️'; }
    public function category(): string { return 'enter'; }
    public function description(): string { return 'ظاهر شدن حرف به حرف متن (یا تکه به تکه لوگو)'; }

    protected function extraSchema(): array
    {
        return [
            'caret' => ['type' => 'bool', 'default' => true, 'label' => 'نشانگر تایپ'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        $units = max(1, $c->units);
        if ($q < 0.06) {
            $s->opacity *= 1 - Easing::clamp($q / 0.06);
            return;
        }
        if ($q < 0.12) {
            $s->reveal = 0.0;
            $s->caret = (bool) $c->param('caret', true);
            return;
        }
        if ($q < 0.72) {
            $t = ($q - 0.12) / 0.6;
            $visible = min($units, (int) floor($t * $units) + 1);
            $s->reveal = min($s->reveal, $visible / $units);
            $s->caret = (bool) $c->param('caret', true) && (fmod($q * $c->duration * 3, 1.0) < 0.6);
        }
    }
}

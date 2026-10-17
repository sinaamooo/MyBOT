<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

/**
 * Base class of every animation effect.
 *
 * To add a new effect: create a class in app/Animation/Effects extending this
 * class, implement key()/names/apply(), and register it in EffectRegistry.
 * Every effect must be "neutral" at local phase 0 and 1 (state equal to the
 * resting design) so loops are seamless and effects can be sequenced.
 */
abstract class Effect
{
    abstract public function key(): string;

    abstract public function nameFa(): string;

    abstract public function nameEn(): string;

    abstract public function apply(EffectContext $c, FrameState $s): void;

    public function icon(): string
    {
        return '✨';
    }

    public function description(): string
    {
        return '';
    }

    /** motion | light | enter | color | decoration */
    public function category(): string
    {
        return 'motion';
    }

    /** Effect adds a glow layer when the design has none. */
    public function requiresGlow(): bool
    {
        return false;
    }

    /** Draws extra shapes (arrow, chart, orbit dots, caret...). */
    public function isDecoration(): bool
    {
        return false;
    }

    /** Allowed targets. */
    public function targets(): array
    {
        return ['all', 'logo', 'text'];
    }

    /** May move content outside the canvas on purpose (raster only). */
    public function allowsOverflow(): bool
    {
        return false;
    }

    /**
     * Parameter definitions (shared + effect specific).
     * type: float|int|enum|color|bool
     * @return array<string,array<string,mixed>>
     */
    public function schema(): array
    {
        return array_replace([
            'intensity' => ['type' => 'float', 'min' => 0.1, 'max' => 1.0, 'default' => 0.65, 'label' => 'شدت'],
            'speed' => ['type' => 'float', 'min' => 0.25, 'max' => 4.0, 'default' => 1.0, 'label' => 'سرعت'],
            'repeat' => ['type' => 'int', 'min' => 1, 'max' => 8, 'default' => 1, 'label' => 'تعداد تکرار'],
            'delay' => ['type' => 'float', 'min' => 0.0, 'max' => 3.0, 'default' => 0.0, 'label' => 'تأخیر (ثانیه)'],
        ], $this->extraSchema());
    }

    /** @return array<string,array<string,mixed>> */
    protected function extraSchema(): array
    {
        return [];
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        $out = [];
        foreach ($this->schema() as $k => $def) {
            $out[$k] = $def['default'];
        }
        return $out;
    }

    /**
     * Validates / clamps parameters. Unknown keys are dropped.
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public function normalize(array $params): array
    {
        $out = [];
        foreach ($this->schema() as $key => $def) {
            $v = $params[$key] ?? $def['default'];
            switch ($def['type']) {
                case 'float':
                    $v = is_numeric($v) ? (float) $v : (float) $def['default'];
                    $v = max((float) $def['min'], min((float) $def['max'], $v));
                    break;
                case 'int':
                    $v = is_numeric($v) ? (int) $v : (int) $def['default'];
                    $v = max((int) $def['min'], min((int) $def['max'], $v));
                    break;
                case 'enum':
                    $v = in_array($v, $def['options'], true) ? $v : $def['default'];
                    break;
                case 'color':
                    $v = is_string($v) && preg_match('/^#[0-9a-fA-F]{6}$/', $v) ? strtoupper($v) : $def['default'];
                    break;
                case 'bool':
                    $v = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                    break;
            }
            $out[$key] = $v;
        }
        return $out;
    }

    /** Applies a translation that rotates the element around another pivot. */
    protected static function rotateAroundPivot(FrameState $s, float $deg, float $pivotDx, float $pivotDy): void
    {
        // pivot given relative to the element centre (design px)
        $r = deg2rad($deg);
        $cos = cos($r);
        $sin = sin($r);
        // centre relative to pivot is (-pivotDx, -pivotDy); after rotation:
        $nx = -$pivotDx * $cos + $pivotDy * $sin;
        $ny = -$pivotDx * $sin - $pivotDy * $cos;
        $s->dx += $pivotDx + $nx;
        $s->dy += $pivotDy + $ny;
        $s->rot += $deg;
    }
}

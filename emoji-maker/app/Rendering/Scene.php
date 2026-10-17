<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Animation\AnimationSpec;

/**
 * The complete composition: elements in z-order (bottom first) plus the
 * animation. Shared by the raster renderer and the Lottie writer.
 */
final class Scene
{
    public const SIZE = 512;

    /** @var array<string,Element> */
    public array $elements = [];
    /** @var array{0:float,1:float,2:float,3:float} */
    public array $groupBounds = [0, 0, 512, 512];
    public bool $rtl = false;
    /** Global shrink factor so the animated design stays on the canvas. */
    public float $fit = 1.0;
    /** @var list<string> human readable warnings (missing glyphs ...) */
    public array $warnings = [];
    /** Design canvas width (512, or tiles x 512 for multi-emoji banners). */
    public float $width = 512.0;
    /** Number of emoji tiles the canvas is split into (1 = normal emoji). */
    public int $tiles = 1;

    public function __construct(public AnimationSpec $animation, public string $mode)
    {
    }

    public function add(Element $e): void
    {
        $this->elements[$e->id] = $e;
    }

    public function has(string $id): bool
    {
        return isset($this->elements[$id]);
    }

    /** @return array<string,array{0:float,1:float,2:float,3:float}> bounds of animatable elements */
    public function elementBounds(): array
    {
        $out = [];
        foreach ($this->elements as $id => $e) {
            if ($id !== 'bg') {
                $out[$id] = $e->bounds;
            }
        }
        return $out;
    }

    /** @return array<string,int> */
    public function units(): array
    {
        $out = [];
        foreach ($this->elements as $id => $e) {
            if ($id !== 'bg') {
                $out[$id] = $e->unitCount();
            }
        }
        return $out;
    }

    public function recomputeGroupBounds(): void
    {
        $b = null;
        foreach ($this->elements as $e) {
            $eb = $e->bounds;
            $b = $b === null ? $eb : [min($b[0], $eb[0]), min($b[1], $eb[1]), max($b[2], $eb[2]), max($b[3], $eb[3])];
        }
        $this->groupBounds = $b ?? [$this->width / 2 - 100, 156, $this->width / 2 + 100, 356];
    }
}

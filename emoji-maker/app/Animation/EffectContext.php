<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

/**
 * Everything an effect needs to compute its contribution for one frame.
 * All geometry is expressed in the 512x512 design space.
 */
final class EffectContext
{
    /**
     * @param array<string,mixed> $params validated parameters
     * @param array{0:float,1:float,2:float,3:float} $bounds target bounds (x0,y0,x1,y1)
     * @param array{0:float,1:float,2:float,3:float} $groupBounds bounds of the whole composition
     */
    public function __construct(
        public readonly int $frame,
        public readonly int $frames,
        public readonly float $u,          // global loop phase 0..1
        public readonly float $p,          // local phase of this effect 0..1
        public readonly float $duration,   // loop duration (s)
        public readonly string $mode,      // raster | vector
        public readonly array $params,
        public readonly array $bounds,
        public readonly array $groupBounds,
        public readonly int $index,        // effect index in the animation spec
        public readonly string $target,    // all | logo | text
        public readonly int $units = 0,    // number of reveal units (characters / parts)
        public readonly bool $rtl = false,
        public readonly int $seed = 0,
    ) {
    }

    public function intensity(): float
    {
        return (float) ($this->params['intensity'] ?? 0.65);
    }

    public function cycles(): int
    {
        $repeat = (int) ($this->params['repeat'] ?? 1);
        $speed = (float) ($this->params['speed'] ?? 1.0);
        return max(1, (int) round($repeat * $speed));
    }

    /** Phase inside the current cycle (0..1). */
    public function q(): float
    {
        return Easing::frac($this->p * $this->cycles());
    }

    public function width(): float
    {
        return max(1.0, $this->bounds[2] - $this->bounds[0]);
    }

    public function height(): float
    {
        return max(1.0, $this->bounds[3] - $this->bounds[1]);
    }

    /** @return array{0:float,1:float} */
    public function center(): array
    {
        return [($this->bounds[0] + $this->bounds[2]) / 2, ($this->bounds[1] + $this->bounds[3]) / 2];
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function isVector(): bool
    {
        return $this->mode === 'vector';
    }
}

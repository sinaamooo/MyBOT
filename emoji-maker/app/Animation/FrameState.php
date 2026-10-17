<?php

declare(strict_types=1);

namespace EmojiMaker\Animation;

/**
 * Animated properties of one element (or of the whole group) at one frame.
 * Effects modify this state; renderers (raster and Lottie) consume it.
 */
final class FrameState
{
    public float $scale = 1.0;
    public float $sx = 1.0;
    public float $sy = 1.0;
    public float $rot = 0.0;      // degrees, clockwise
    public float $dx = 0.0;       // design px (512 canvas)
    public float $dy = 0.0;
    public float $opacity = 1.0;
    public float $glow = 1.0;     // multiplier of the glow / shadow layer
    public float $bright = 0.0;   // 0..1 flash toward white
    public float $shade = 0.0;    // 0..1 darkening (3D side lighting)
    public float $hue = 0.0;      // degrees of hue rotation
    public float $reveal = 1.0;   // 0..1 share of characters/parts visible
    public ?float $shine = null;  // light sweep position (raster only), null = none
    public float $shineStrength = 0.0;
    public bool $caret = false;   // typewriter caret visible
    /** @var array<int,array<string,mixed>> decoration data keyed by effect index */
    public array $deco = [];

    public function isIdentityTransform(): bool
    {
        return abs($this->scale - 1) < 1e-6 && abs($this->sx - 1) < 1e-6 && abs($this->sy - 1) < 1e-6
            && abs($this->rot) < 1e-6 && abs($this->dx) < 1e-6 && abs($this->dy) < 1e-6;
    }

    /** @return array<string,float|null|bool> */
    public function toArray(): array
    {
        return [
            'scale' => $this->scale, 'sx' => $this->sx, 'sy' => $this->sy, 'rot' => $this->rot,
            'dx' => $this->dx, 'dy' => $this->dy, 'opacity' => $this->opacity, 'glow' => $this->glow,
            'bright' => $this->bright, 'shade' => $this->shade, 'hue' => $this->hue, 'reveal' => $this->reveal,
            'shine' => $this->shine, 'caret' => $this->caret,
        ];
    }
}

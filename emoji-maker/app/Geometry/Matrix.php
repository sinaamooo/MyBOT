<?php

declare(strict_types=1);

namespace EmojiMaker\Geometry;

/**
 * 2D affine matrix  [a c e]
 *                   [b d f]
 * x' = a*x + c*y + e ;  y' = b*x + d*y + f
 * Rotation angles are in degrees, positive = clockwise on screen (y down),
 * which is the same convention Lottie uses.
 */
final class Matrix
{
    public function __construct(
        public float $a = 1.0,
        public float $b = 0.0,
        public float $c = 0.0,
        public float $d = 1.0,
        public float $e = 0.0,
        public float $f = 0.0,
    ) {
    }

    public static function identity(): self
    {
        return new self();
    }

    public static function translation(float $x, float $y): self
    {
        return new self(1, 0, 0, 1, $x, $y);
    }

    public static function scaling(float $sx, ?float $sy = null): self
    {
        return new self($sx, 0, 0, $sy ?? $sx, 0, 0);
    }

    public static function rotation(float $degrees): self
    {
        $r = deg2rad($degrees);
        $cos = cos($r);
        $sin = sin($r);
        return new self($cos, $sin, -$sin, $cos, 0, 0);
    }

    /** Returns $this * $m  (apply $m first, then $this). */
    public function multiply(self $m): self
    {
        return new self(
            $this->a * $m->a + $this->c * $m->b,
            $this->b * $m->a + $this->d * $m->b,
            $this->a * $m->c + $this->c * $m->d,
            $this->b * $m->c + $this->d * $m->d,
            $this->a * $m->e + $this->c * $m->f + $this->e,
            $this->b * $m->e + $this->d * $m->f + $this->f,
        );
    }

    /** Applies $m after this matrix. */
    public function then(self $m): self
    {
        return $m->multiply($this);
    }

    /** @return array{0:float,1:float} */
    public function apply(float $x, float $y): array
    {
        return [$this->a * $x + $this->c * $y + $this->e, $this->b * $x + $this->d * $y + $this->f];
    }

    public function invert(): self
    {
        $det = $this->a * $this->d - $this->b * $this->c;
        if (abs($det) < 1e-12) {
            return self::identity();
        }
        $ia = $this->d / $det;
        $ib = -$this->b / $det;
        $ic = -$this->c / $det;
        $id = $this->a / $det;
        return new self($ia, $ib, $ic, $id, -($ia * $this->e + $ic * $this->f), -($ib * $this->e + $id * $this->f));
    }

    public function isIdentity(): bool
    {
        return abs($this->a - 1) < 1e-9 && abs($this->b) < 1e-9 && abs($this->c) < 1e-9
            && abs($this->d - 1) < 1e-9 && abs($this->e) < 1e-9 && abs($this->f) < 1e-9;
    }

    /** Typical scale factor (for flattening tolerance). */
    public function scaleFactor(): float
    {
        return sqrt(abs($this->a * $this->d - $this->b * $this->c));
    }
}

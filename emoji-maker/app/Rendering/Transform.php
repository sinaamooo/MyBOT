<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Animation\FrameState;
use EmojiMaker\Geometry\Matrix;

/**
 * Builds the design-space matrices of the animation:
 *   total = Fit * Group * Element
 * and decomposes 2x2 linear parts (SVD) for the raster blitter.
 */
final class Transform
{
    /** @param array{0:float,1:float} $pivot */
    public static function fromState(FrameState $s, array $pivot): Matrix
    {
        [$cx, $cy] = $pivot;
        return Matrix::translation($cx + $s->dx, $cy + $s->dy)
            ->multiply(Matrix::rotation($s->rot))
            ->multiply(Matrix::scaling($s->scale * $s->sx, $s->scale * $s->sy))
            ->multiply(Matrix::translation(-$cx, -$cy));
    }

    public static function fit(float $fit): Matrix
    {
        if (abs($fit - 1.0) < 1e-6) {
            return Matrix::identity();
        }
        return Matrix::translation(256, 256)->multiply(Matrix::scaling($fit))->multiply(Matrix::translation(-256, -256));
    }

    /**
     * SVD of the linear part: M = Rot(phi) * diag(s1, s2) * Rot(theta)
     * @return array{phi:float,s1:float,s2:float,theta:float}
     */
    public static function svd(Matrix $m): array
    {
        $m00 = $m->a;
        $m01 = $m->c;
        $m10 = $m->b;
        $m11 = $m->d;
        $e = ($m00 + $m11) / 2;
        $f = ($m00 - $m11) / 2;
        $g = ($m10 + $m01) / 2;
        $h = ($m10 - $m01) / 2;
        $q = sqrt($e * $e + $h * $h);
        $r = sqrt($f * $f + $g * $g);
        $s1 = $q + $r;
        $s2 = $q - $r;
        $a1 = atan2($g, $f);
        $a2 = atan2($h, $e);
        $theta = ($a2 - $a1) / 2;
        $phi = ($a2 + $a1) / 2;
        return ['phi' => rad2deg($phi), 's1' => $s1, 's2' => $s2, 'theta' => rad2deg($theta)];
    }
}

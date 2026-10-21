<?php

declare(strict_types=1);

namespace EmojiMaker\Vector;

use EmojiMaker\Geometry\Path;

/**
 * Helpers that produce Lottie (Bodymovin 5.x) JSON structures limited to the
 * subset Telegram accepts in .TGS files: shape layers, groups, paths, fills,
 * linear gradient fills, strokes, trim paths and transforms with keyframes.
 * Per-frame samples are reduced to a minimal set of linear keyframes.
 */
final class LottieBuilder
{
    public function __construct(private int $lastFrame, private float $tolerance = 1.0)
    {
    }

    public function lastFrame(): int
    {
        return $this->lastFrame;
    }

    public function setTolerance(float $t): void
    {
        $this->tolerance = $t;
    }

    /** Decimal places used for path coordinates (more for small, scaled up hosts). */
    private int $precision = 1;

    public function setPrecision(int $decimals): void
    {
        $this->precision = max(0, min(4, $decimals));
    }

    private static function r(float $v, int $dec = 1): float
    {
        $r = round($v, $dec);
        return $r == 0.0 ? 0.0 : $r; // avoid -0
    }

    /** @return list<array<string,mixed>> one "sh" item per contour */
    public function shape(Path $path, bool $closed = true): array
    {
        $items = [];
        foreach ($path->toCubic()->contours as $ci => $c) {
            $v = [];
            $in = [];
            $out = [];
            $px = $c['x'];
            $py = $c['y'];
            $vertices = [[$px, $py]];
            $outs = [];
            $ins = [[0.0, 0.0]];
            $segs = $c['segs'];
            $n = count($segs);
            foreach ($segs as $si => $s) {
                // s = [CUBIC, c1x, c1y, c2x, c2y, x, y]
                $outs[count($vertices) - 1] = [$s[1] - $px, $s[2] - $py];
                $isClosingSeg = $si === $n - 1 && abs($s[5] - $c['x']) < 1e-6 && abs($s[6] - $c['y']) < 1e-6;
                if ($isClosingSeg && $c['closed']) {
                    $ins[0] = [$s[3] - $c['x'], $s[4] - $c['y']];
                } else {
                    $vertices[] = [$s[5], $s[6]];
                    $ins[count($vertices) - 1] = [$s[3] - $s[5], $s[4] - $s[6]];
                }
                $px = $s[5];
                $py = $s[6];
            }
            foreach ($vertices as $k => [$x, $y]) {
                $v[] = [self::r($x, $this->precision), self::r($y, $this->precision)];
                $o = $outs[$k] ?? [0.0, 0.0];
                $i = $ins[$k] ?? [0.0, 0.0];
                $out[] = [self::r($o[0], $this->precision), self::r($o[1], $this->precision)];
                $in[] = [self::r($i[0], $this->precision), self::r($i[1], $this->precision)];
            }
            if (count($v) < 2) {
                continue;
            }
            $items[] = [
                'ty' => 'sh', 'ind' => $ci, 'ks' => ['a' => 0, 'k' => ['c' => $closed && $c['closed'], 'v' => $v, 'i' => $in, 'o' => $out]],
                'nm' => 'p' . $ci,
            ];
        }
        return $items;
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public static function color(array $rgb): array
    {
        return [round($rgb[0] / 255, 4), round($rgb[1] / 255, 4), round($rgb[2] / 255, 4), 1];
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     * @param array<string,mixed>|float $opacity
     * @param array<string,mixed>|null $animatedColor
     */
    public function fill(array $rgb, array|float $opacity = 100, int $rule = 1, ?array $animatedColor = null): array
    {
        return [
            'ty' => 'fl',
            'c' => $animatedColor ?? ['a' => 0, 'k' => self::color($rgb)],
            'o' => is_array($opacity) ? $opacity : ['a' => 0, 'k' => self::r($opacity, 0)],
            'r' => $rule,
            'nm' => 'fill',
        ];
    }

    /** Vertical linear gradient fill (gradient strokes are forbidden, fills are fine). */
    public function gradientFill(array $top, array $bottom, float $y0, float $y1, float $x, int $rule = 1): array
    {
        return [
            'ty' => 'gf',
            'o' => ['a' => 0, 'k' => 100],
            'r' => $rule,
            's' => ['a' => 0, 'k' => [self::r($x), self::r($y0)]],
            'e' => ['a' => 0, 'k' => [self::r($x), self::r($y1)]],
            't' => 1,
            'g' => ['p' => 2, 'k' => ['a' => 0, 'k' => [
                0, round($top[0] / 255, 4), round($top[1] / 255, 4), round($top[2] / 255, 4),
                1, round($bottom[0] / 255, 4), round($bottom[1] / 255, 4), round($bottom[2] / 255, 4),
            ]]],
            'nm' => 'gradient',
        ];
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     * @param array<string,mixed>|float $opacity
     */
    public function stroke(array $rgb, float $width, array|float $opacity = 100, ?array $animatedColor = null): array
    {
        return [
            'ty' => 'st',
            'c' => $animatedColor ?? ['a' => 0, 'k' => self::color($rgb)],
            'o' => is_array($opacity) ? $opacity : ['a' => 0, 'k' => self::r($opacity, 0)],
            'w' => ['a' => 0, 'k' => self::r($width)],
            'lc' => 2,
            'lj' => 2,
            'ml' => 4,
            'nm' => 'stroke',
        ];
    }

    /** @param array<string,mixed> $end */
    public function trim(array $end): array
    {
        return ['ty' => 'tm', 's' => ['a' => 0, 'k' => 0], 'e' => $end, 'o' => ['a' => 0, 'k' => 0], 'm' => 1, 'nm' => 'trim'];
    }

    /**
     * @param array<int,float>|array<string,mixed> $anchor static [x,y] or property
     * @param array<int,float>|array<string,mixed> $position
     * @param array<string,mixed>|null $scale
     * @param array<string,mixed>|null $rotation
     * @param array<string,mixed>|null $opacity
     */
    public function transform(array $anchor, array $position, ?array $scale = null, ?array $rotation = null, ?array $opacity = null): array
    {
        return [
            'ty' => 'tr',
            'p' => self::prop($position),
            'a' => self::prop($anchor),
            's' => $scale ?? ['a' => 0, 'k' => [100, 100]],
            'r' => $rotation ?? ['a' => 0, 'k' => 0],
            'o' => $opacity ?? ['a' => 0, 'k' => 100],
            'sk' => ['a' => 0, 'k' => 0],
            'sa' => ['a' => 0, 'k' => 0],
            'nm' => 'Transform',
        ];
    }

    private static function prop(array $v): array
    {
        if (isset($v['a'])) {
            return $v;
        }
        return ['a' => 0, 'k' => [self::r((float) $v[0]), self::r((float) $v[1])]];
    }

    /**
     * @param list<array<string,mixed>|list<array<string,mixed>>> $items (lists are flattened)
     * @param array<string,mixed>|null $transform
     */
    public function group(string $name, array $items, ?array $transform = null): array
    {
        $flat = [];
        foreach ($items as $it) {
            if ($it === null || $it === []) {
                continue;
            }
            if (array_is_list($it)) {
                foreach ($it as $sub) {
                    $flat[] = $sub;
                }
            } else {
                $flat[] = $it;
            }
        }
        $flat[] = $transform ?? $this->transform([0, 0], [0, 0]);
        return ['ty' => 'gr', 'it' => $flat, 'nm' => $name, 'np' => count($flat) - 1, 'cix' => 2, 'ix' => 1];
    }

    // ------------------------------------------------------------------
    // Keyframes
    // ------------------------------------------------------------------

    /**
     * Scalar property from per-frame samples (index = frame).
     * @param list<float> $values
     */
    public function animatedScalar(array $values, ?float $tolerance = null, bool $hold = false): array
    {
        return $this->animated(array_map(static fn ($v) => [(float) $v], $values), $tolerance, $hold, 'scalar');
    }

    /**
     * Vector property (position / scale) from per-frame samples.
     * @param list<array<int,float>> $values
     */
    public function animatedVec(array $values, ?float $tolerance = null, bool $hold = false, bool $spatial = false): array
    {
        return $this->animated($values, $tolerance, $hold, $spatial ? 'spatial' : 'vector');
    }

    /** Colour property ([r,g,b] 0..255 samples). */
    public function animatedColor(array $rgbValues, float $tolerance = 3.0): array
    {
        $vals = array_map(static fn ($c) => [(float) $c[0], (float) $c[1], (float) $c[2]], $rgbValues);
        $prop = $this->animated($vals, $tolerance, false, 'color');
        if ($prop['a'] === 0) {
            return ['a' => 0, 'k' => self::color(array_map('intval', $prop['k']))];
        }
        foreach ($prop['k'] as &$kf) {
            if (isset($kf['s'])) {
                $kf['s'] = self::color(array_map(static fn ($x) => (int) round($x), $kf['s']));
            }
        }
        unset($kf);
        return $prop;
    }

    /**
     * @param list<list<float>> $values one sample per frame (0..lastFrame)
     */
    private function animated(array $values, ?float $tolerance, bool $hold, string $kind): array
    {
        $tol = $tolerance ?? $this->tolerance;
        $n = count($values);
        if ($n === 0) {
            return ['a' => 0, 'k' => 0];
        }
        $dec = $kind === 'scalar' || $kind === 'color' ? 1 : 1;
        $first = $values[0];
        $constant = true;
        foreach ($values as $v) {
            foreach ($v as $d => $x) {
                if (abs($x - $first[$d]) > $tol * 0.25) {
                    $constant = false;
                    break 2;
                }
            }
        }
        $fmt = static function (array $v) use ($kind, $dec): array|float {
            $r = array_map(static fn ($x) => self::r((float) $x, $dec), $v);
            return $r;
        };
        if ($constant) {
            $k = $fmt($first);
            return ['a' => 0, 'k' => count($k) === 1 && $kind === 'scalar' ? $k[0] : $k];
        }
        $keep = $hold ? $this->holdKeys($values, $tol) : $this->reduce($values, $tol);
        $kfs = [];
        $last = count($keep) - 1;
        foreach ($keep as $idx => $frame) {
            $frame = min($frame, $this->lastFrame);
            $kf = ['t' => $frame, 's' => $fmt($values[$frame])];
            if ($idx < $last) {
                if ($hold) {
                    $kf['h'] = 1;
                } elseif ($kind === 'spatial') {
                    $kf['i'] = ['x' => 0.833, 'y' => 0.833];
                    $kf['o'] = ['x' => 0.167, 'y' => 0.167];
                    $kf['to'] = [0, 0];
                    $kf['ti'] = [0, 0];
                } else {
                    $dims = $kind === 'scalar' || $kind === 'color' ? 1 : count($values[$frame]);
                    $kf['i'] = ['x' => array_fill(0, $dims, 0.833), 'y' => array_fill(0, $dims, 0.833)];
                    $kf['o'] = ['x' => array_fill(0, $dims, 0.167), 'y' => array_fill(0, $dims, 0.167)];
                }
            }
            $kfs[] = $kf;
        }
        return ['a' => 1, 'k' => $kfs];
    }

    /**
     * Keyframe reduction: keeps the frames needed so that linear interpolation
     * stays within $tol of every sample (iterative RDP on the time series).
     * @param list<list<float>> $values
     * @return list<int>
     */
    private function reduce(array $values, float $tol): array
    {
        $n = count($values);
        $keep = [0 => true, $n - 1 => true];
        $stack = [[0, $n - 1]];
        while ($stack) {
            [$a, $b] = array_pop($stack);
            if ($b - $a < 2) {
                continue;
            }
            $worst = -1.0;
            $idx = -1;
            for ($i = $a + 1; $i < $b; $i++) {
                $t = ($i - $a) / ($b - $a);
                $err = 0.0;
                foreach ($values[$i] as $d => $x) {
                    $interp = $values[$a][$d] + ($values[$b][$d] - $values[$a][$d]) * $t;
                    $err = max($err, abs($x - $interp));
                }
                if ($err > $worst) {
                    $worst = $err;
                    $idx = $i;
                }
            }
            if ($worst > $tol) {
                $keep[$idx] = true;
                $stack[] = [$a, $idx];
                $stack[] = [$idx, $b];
            }
        }
        $frames = array_keys($keep);
        sort($frames);
        return $frames;
    }

    /**
     * Hold keyframes: a key wherever the value changes.
     * @param list<list<float>> $values
     * @return list<int>
     */
    private function holdKeys(array $values, float $tol): array
    {
        $frames = [0];
        $prev = $values[0];
        foreach ($values as $i => $v) {
            foreach ($v as $d => $x) {
                if (abs($x - $prev[$d]) > $tol * 0.25) {
                    $frames[] = $i;
                    $prev = $v;
                    break;
                }
            }
        }
        $last = count($values) - 1;
        if (end($frames) !== $last) {
            $frames[] = $last;
        }
        return $frames;
    }
}

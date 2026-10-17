<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\DecorationEffect;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Vector\LottieBuilder;

/** Orbit: small elements circling around the logo with a 3D depth illusion. */
final class Orbit extends Effect implements DecorationEffect
{
    public function key(): string { return 'orbit'; }
    public function nameFa(): string { return 'مدار (Orbit)'; }
    public function nameEn(): string { return 'Orbit'; }
    public function icon(): string { return '🪐'; }
    public function category(): string { return 'decoration'; }
    public function description(): string { return 'چرخش ستاره یا نقطه‌ها دور لوگو با عمق سه‌بعدی'; }
    public function isDecoration(): bool { return true; }
    public function targets(): array { return ['all']; }

    protected function extraSchema(): array
    {
        return [
            'count' => ['type' => 'int', 'min' => 1, 'max' => 8, 'default' => 4, 'label' => 'تعداد'],
            'shape' => ['type' => 'enum', 'options' => ['sparkle', 'dot'], 'default' => 'sparkle', 'label' => 'شکل',
                'labels' => ['sparkle' => 'ستاره', 'dot' => 'نقطه']],
            'tilt' => ['type' => 'float', 'min' => 0.2, 'max' => 1.0, 'default' => 0.38, 'label' => 'زاویه دید'],
            'direction' => ['type' => 'enum', 'options' => ['cw', 'ccw'], 'default' => 'cw', 'label' => 'جهت',
                'labels' => ['cw' => 'ساعتگرد', 'ccw' => 'پادساعتگرد']],
            'color' => ['type' => 'color', 'default' => '#FFD43B', 'label' => 'رنگ'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $sign = $c->param('direction') === 'ccw' ? -1 : 1;
        $s->deco[$c->index] = ['a' => $sign * 360.0 * $c->cycles() * $c->p];
    }

    /** @return array{0:float,1:float,2:float,3:float,4:float} cx, cy, rx, ry, dot radius */
    private function geometry(array $params, array $gb): array
    {
        $cx = ($gb[0] + $gb[2]) / 2;
        $cy = ($gb[1] + $gb[3]) / 2;
        $w = max(60.0, $gb[2] - $gb[0]);
        $h = max(60.0, $gb[3] - $gb[1]);
        $rx = min(230.0, max($w, $h) * 0.5 + 34);
        $ry = $rx * (float) $params['tilt'];
        $dot = 0.035 * 512 * (0.7 + 0.6 * (float) $params['intensity']);
        return [$cx, $cy, $rx, $ry, $dot];
    }

    /** @return list<array{x:float,y:float,z:float}> */
    private function positions(float $angle, array $params, array $gb): array
    {
        [$cx, $cy, $rx, $ry] = $this->geometry($params, $gb);
        $n = (int) $params['count'];
        $out = [];
        for ($k = 0; $k < $n; $k++) {
            $th = deg2rad($angle + $k * 360 / $n);
            $out[] = ['x' => $cx + $rx * cos($th), 'y' => $cy + $ry * sin($th), 'z' => sin($th)];
        }
        return $out;
    }

    private function dotPath(array $params, float $r): Path
    {
        return $params['shape'] === 'dot' ? Path::ellipse(0, 0, $r, $r) : Path::sparkle(0, 0, $r * 1.6, 0.22);
    }

    public function shapes(array $data, array $params, array $groupBounds, float $canvasWidth = 512.0): array
    {
        [, , , , $dot] = $this->geometry($params, $groupBounds);
        $color = Color::hexToRgb((string) $params['color']);
        $out = [];
        foreach ($this->positions((float) $data['a'], $params, $groupBounds) as $p) {
            $depth = ($p['z'] + 1) / 2;
            $r = $dot * (0.7 + 0.45 * $depth);
            $path = $this->dotPath($params, $r)->transform(\EmojiMaker\Geometry\Matrix::translation($p['x'], $p['y']));
            $out[] = [
                'path' => $path, 'fill' => $color, 'opacity' => 0.55 + 0.45 * $depth,
                'stroke' => null, 'outline' => null,
                'z' => ((float) $params['tilt'] >= 0.95 || $p['z'] >= 0) ? 'front' : 'back',
            ];
        }
        return $out;
    }

    public function lottie(LottieBuilder $lb, array $samples, array $params, array $groupBounds, float $canvasWidth = 512.0): array
    {
        [, , , , $dot] = $this->geometry($params, $groupBounds);
        $color = Color::hexToRgb((string) $params['color']);
        $n = (int) $params['count'];
        $flat = (float) $params['tilt'] >= 0.95;
        $front = [];
        $back = [];
        for ($k = 0; $k < $n; $k++) {
            $pos = [];
            $scale = [];
            $opF = [];
            $opB = [];
            foreach ($samples as $d) {
                $p = $this->positions((float) $d['a'], $params, $groupBounds)[$k];
                $depth = ($p['z'] + 1) / 2;
                $pos[] = [$p['x'], $p['y']];
                $sc = 100 * (0.7 + 0.45 * $depth);
                $scale[] = [$sc, $sc];
                $o = 100 * (0.55 + 0.45 * $depth);
                $isFront = $flat || $p['z'] >= 0;
                $opF[] = $isFront ? $o : 0.0;
                $opB[] = $isFront ? 0.0 : $o;
            }
            $shape = [$lb->shape($this->dotPath($params, $dot)), $lb->fill($color)];
            $posAnim = $lb->animatedVec($pos, 0.35);
            $scaleAnim = $lb->animatedVec($scale, 0.8);
            $front[] = $lb->group('orbit-f' . $k, $shape, $lb->transform([0, 0], $posAnim, $scaleAnim, null, $lb->animatedScalar($opF, 0.5, true)));
            if (!$flat) {
                $back[] = $lb->group('orbit-b' . $k, $shape, $lb->transform([0, 0], $posAnim, $scaleAnim, null, $lb->animatedScalar($opB, 0.5, true)));
            }
        }
        return ['front' => $front, 'back' => $back];
    }
}

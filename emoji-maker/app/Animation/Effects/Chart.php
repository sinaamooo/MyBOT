<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\DecorationEffect;
use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Vector\LottieBuilder;

/** Chart Motion: growing bars and a trend line drawing itself. */
final class Chart extends Effect implements DecorationEffect
{
    public function key(): string { return 'chart'; }
    public function nameFa(): string { return 'حرکت نمودار (Chart)'; }
    public function nameEn(): string { return 'Chart Motion'; }
    public function icon(): string { return '📊'; }
    public function category(): string { return 'decoration'; }
    public function description(): string { return 'نمودار ستونی و خطی که رشد می‌کند'; }
    public function isDecoration(): bool { return true; }
    public function targets(): array { return ['all']; }

    private const BARS_UP = [0.32, 0.5, 0.42, 0.68, 0.92];
    private const BARS_DOWN = [0.92, 0.7, 0.78, 0.48, 0.3];

    protected function extraSchema(): array
    {
        return [
            'style' => ['type' => 'enum', 'options' => ['both', 'bars', 'line'], 'default' => 'both', 'label' => 'نوع نمودار',
                'labels' => ['both' => 'ستونی + خطی', 'bars' => 'ستونی', 'line' => 'خطی']],
            'direction' => ['type' => 'enum', 'options' => ['up', 'down'], 'default' => 'up', 'label' => 'روند',
                'labels' => ['up' => 'صعودی', 'down' => 'نزولی']],
            'color' => ['type' => 'color', 'default' => '#22C55E', 'label' => 'رنگ نمودار'],
            'placement' => ['type' => 'enum', 'options' => ['behind', 'front'], 'default' => 'behind', 'label' => 'محل',
                'labels' => ['behind' => 'پشت طرح', 'front' => 'جلوی طرح']],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $q = $c->q();
        if ($q < 0.1) {
            $o = 1 - Easing::clamp($q / 0.1);
            $g = 1.0;
        } elseif ($q < 0.18) {
            $o = 0.0;
            $g = 0.0;
        } else {
            $o = Easing::clamp(($q - 0.18) / 0.08);
            $g = Easing::clamp(($q - 0.18) / 0.55);
        }
        $s->deco[$c->index] = ['g' => $g, 'o' => $o];
    }

    /** @return array{0:float,1:float,2:float,3:float} area x0,y0,x1,y1 */
    private function area(float $cw = 512.0): array
    {
        return [0.1 * 512, 0.4 * 512, $cw - 0.1 * 512, 0.93 * 512];
    }

    /** @return list<float> */
    private function heights(array $params): array
    {
        return $params['direction'] === 'down' ? self::BARS_DOWN : self::BARS_UP;
    }

    private function barProgress(float $g, int $i, int $n): float
    {
        $start = ($i / $n) * 0.55;
        return Easing::outBack(Easing::clamp(($g - $start) / 0.35), 1.2);
    }

    /** @return list<array{0:float,1:float}> */
    private function linePoints(array $params, float $cw = 512.0): array
    {
        [$x0, $y0, $x1, $y1] = $this->area($cw);
        $hs = $this->heights($params);
        $n = count($hs);
        $bw = ($x1 - $x0) / $n;
        $pts = [];
        foreach ($hs as $i => $h) {
            $pts[] = [$x0 + $bw * ($i + 0.5), $y1 - ($y1 - $y0) * min(1.0, $h + 0.08)];
        }
        return $pts;
    }

    public function shapes(array $data, array $params, array $groupBounds, float $canvasWidth = 512.0): array
    {
        $color = Color::hexToRgb((string) $params['color']);
        $light = Color::mix($color, [255, 255, 255], 0.35);
        [$x0, $y0, $x1, $y1] = $this->area($canvasWidth);
        $z = $params['placement'] === 'front' ? 'front' : 'back';
        $o = (float) $data['o'];
        $g = (float) $data['g'];
        $out = [];
        $hs = $this->heights($params);
        $n = count($hs);
        $bw = ($x1 - $x0) / $n;
        if ($params['style'] !== 'line') {
            foreach ($hs as $i => $h) {
                $p = $this->barProgress($g, $i, $n);
                $bh = ($y1 - $y0) * $h * max(0.0, $p);
                if ($bh < 0.5) {
                    continue;
                }
                $out[] = [
                    'path' => Path::roundedRect($x0 + $bw * $i + $bw * 0.14, $y1 - $bh, $bw * 0.72, $bh, $bw * 0.12),
                    'fill' => $light, 'opacity' => $o * 0.62, 'stroke' => null, 'outline' => null, 'z' => $z,
                ];
            }
        }
        if ($params['style'] !== 'bars') {
            $pts = $this->linePoints($params, $canvasWidth);
            $lineT = Easing::inOutSine(Easing::clamp(($g - 0.25) / 0.75));
            $partial = self::partialPolyline($pts, $lineT);
            if (count($partial) >= 2) {
                $out[] = [
                    'path' => Path::polygon($partial, false),
                    'fill' => null, 'opacity' => $o,
                    'stroke' => ['w' => 0.045 * 512, 'color' => $color],
                    'outline' => ['w' => 0.016 * 512, 'color' => [255, 255, 255]], 'z' => $z,
                ];
                $last = end($partial);
                $out[] = [
                    'path' => Path::ellipse($last[0], $last[1], 0.035 * 512, 0.035 * 512),
                    'fill' => [255, 255, 255], 'opacity' => $o * ($lineT > 0.02 ? 1 : 0), 'stroke' => null,
                    'outline' => ['w' => 0.018 * 512, 'color' => $color], 'z' => $z,
                ];
            }
        }
        return $out;
    }

    /**
     * @param list<array{0:float,1:float}> $pts
     * @return list<array{0:float,1:float}>
     */
    public static function partialPolyline(array $pts, float $t): array
    {
        if ($t <= 0) {
            return [];
        }
        $lens = [];
        $total = 0.0;
        for ($i = 1, $n = count($pts); $i < $n; $i++) {
            $l = hypot($pts[$i][0] - $pts[$i - 1][0], $pts[$i][1] - $pts[$i - 1][1]);
            $lens[] = $l;
            $total += $l;
        }
        $target = $total * min(1.0, $t);
        $out = [$pts[0]];
        $acc = 0.0;
        foreach ($lens as $i => $l) {
            if ($acc + $l >= $target) {
                $f = $l > 0 ? ($target - $acc) / $l : 0;
                $out[] = [$pts[$i][0] + ($pts[$i + 1][0] - $pts[$i][0]) * $f, $pts[$i][1] + ($pts[$i + 1][1] - $pts[$i][1]) * $f];
                return $out;
            }
            $acc += $l;
            $out[] = $pts[$i + 1];
        }
        return $out;
    }

    public function lottie(LottieBuilder $lb, array $samples, array $params, array $groupBounds, float $canvasWidth = 512.0): array
    {
        $color = Color::hexToRgb((string) $params['color']);
        $light = Color::mix($color, [255, 255, 255], 0.35);
        [$x0, $y0, $x1, $y1] = $this->area($canvasWidth);
        $groups = [];
        $op = array_map(static fn ($d) => 100 * (float) $d['o'], $samples);
        $hs = $this->heights($params);
        $n = count($hs);
        $bw = ($x1 - $x0) / $n;
        if ($params['style'] !== 'bars') {
            $pts = $this->linePoints($params, $canvasWidth);
            $trim = array_map(fn ($d) => 100 * Easing::inOutSine(Easing::clamp(((float) $d['g'] - 0.25) / 0.75)), $samples);
            $groups[] = $lb->group('chart-line', [
                $lb->shape(Path::polygon($pts, false), false),
                $lb->trim($lb->animatedScalar($trim, 0.5)),
                $lb->stroke($color, 0.045 * 512),
                $lb->stroke([255, 255, 255], 0.077 * 512),
            ], $lb->transform([0, 0], [0, 0], null, null, $lb->animatedScalar($op, 0.8)));
        }
        if ($params['style'] !== 'line') {
            foreach ($hs as $i => $h) {
                $bh = ($y1 - $y0) * $h;
                $bx = $x0 + $bw * $i + $bw * 0.14;
                $scale = [];
                foreach ($samples as $d) {
                    $p = max(0.0, $this->barProgress((float) $d['g'], $i, $n));
                    $scale[] = [100.0, 100.0 * $p];
                }
                $groups[] = $lb->group('bar' . $i, [
                    $lb->shape(Path::roundedRect(-$bw * 0.36, -$bh, $bw * 0.72, $bh, $bw * 0.12)),
                    $lb->fill($light, 62),
                ], $lb->transform([0, 0], [$bx + $bw * 0.36, $y1], $lb->animatedVec($scale, 0.6), null, $lb->animatedScalar($op, 0.8)));
            }
        }
        return $params['placement'] === 'front' ? ['front' => $groups, 'back' => []] : ['front' => [], 'back' => $groups];
    }
}

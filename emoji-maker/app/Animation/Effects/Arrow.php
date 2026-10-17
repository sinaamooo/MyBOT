<?php

declare(strict_types=1);

namespace EmojiMaker\Animation\Effects;

use EmojiMaker\Animation\DecorationEffect;
use EmojiMaker\Animation\Easing;
use EmojiMaker\Animation\Effect;
use EmojiMaker\Animation\EffectContext;
use EmojiMaker\Animation\FrameState;
use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Vector\LottieBuilder;

/** Arrow Motion: a bold arrow that keeps shooting in a direction (trading style). */
final class Arrow extends Effect implements DecorationEffect
{
    public function key(): string { return 'arrow'; }
    public function nameFa(): string { return 'حرکت فلش (Arrow)'; }
    public function nameEn(): string { return 'Arrow Motion'; }
    public function icon(): string { return '📈'; }
    public function category(): string { return 'decoration'; }
    public function description(): string { return 'فلش متحرک (مناسب ایموجی‌های ترید، رشد و هشدار)'; }
    public function isDecoration(): bool { return true; }
    public function targets(): array { return ['all']; }

    protected function extraSchema(): array
    {
        return [
            'direction' => ['type' => 'enum', 'options' => ['up_right', 'up', 'right', 'down_right', 'down', 'left'], 'default' => 'up_right', 'label' => 'جهت',
                'labels' => ['up_right' => 'بالا-راست', 'up' => 'بالا', 'right' => 'راست', 'down_right' => 'پایین-راست', 'down' => 'پایین', 'left' => 'چپ']],
            'placement' => ['type' => 'enum', 'options' => ['corner', 'center', 'behind'], 'default' => 'corner', 'label' => 'محل',
                'labels' => ['corner' => 'گوشه', 'center' => 'وسط', 'behind' => 'پشت طرح']],
            'color' => ['type' => 'color', 'default' => '#22C55E', 'label' => 'رنگ فلش'],
            'outline' => ['type' => 'color', 'default' => '#FFFFFF', 'label' => 'رنگ حاشیه'],
        ];
    }

    public function apply(EffectContext $c, FrameState $s): void
    {
        $tau = Easing::frac($c->q() + 0.5);
        $opacity = min(Easing::clamp($tau / 0.15), Easing::clamp((1 - $tau) / 0.15));
        $s->deco[$c->index] = ['t' => Easing::inOutSine($tau), 'o' => Easing::inOutSine($opacity)];
    }

    private function angle(string $dir): float
    {
        return match ($dir) {
            'up' => -90.0, 'right' => 0.0, 'down_right' => 45.0, 'down' => 90.0, 'left' => 180.0, default => -45.0,
        };
    }

    /** @return array{0:float,1:float,2:float} anchor x, y and arrow length */
    private function geometry(array $params, float $cw = 512.0): array
    {
        $shift = $cw - 512;
        $dir = (string) $params['direction'];
        $placement = (string) $params['placement'];
        if ($placement === 'corner') {
            $len = 0.36 * 512;
            [$x, $y] = match ($dir) {
                'up' => [0.8 * 512, 0.27 * 512],
                'right' => [0.72 * 512, 0.18 * 512],
                'down_right', 'down' => [0.78 * 512, 0.76 * 512],
                'left' => [0.28 * 512, 0.18 * 512],
                default => [0.76 * 512, 0.25 * 512],
            };
            return [$x + ($x > 256 ? $shift : 0.0), $y, $len];
        }
        return [$cw / 2, 256.0, ($placement === 'center' ? 0.62 : 0.8) * 512];
    }

    /** Arrow pointing to +x, centred on origin. */
    public static function arrowPath(float $len): Path
    {
        $shaft = $len * 0.2;
        $head = $len * 0.5;
        $headLen = $len * 0.42;
        $x0 = -$len / 2;
        $x1 = $len / 2;
        $xh = $x1 - $headLen;
        return Path::polygon([
            [$x0, -$shaft / 2], [$xh, -$shaft / 2], [$xh, -$head / 2], [$x1, 0.0],
            [$xh, $head / 2], [$xh, $shaft / 2], [$x0, $shaft / 2],
        ]);
    }

    public function shapes(array $data, array $params, array $groupBounds, float $canvasWidth = 512.0): array
    {
        [$ax, $ay, $len] = $this->geometry($params, $canvasWidth);
        $angle = $this->angle((string) $params['direction']);
        $travel = $len * 0.45;
        $r = deg2rad($angle);
        $off = ($data['t'] - 0.5) * $travel;
        $x = $ax + cos($r) * $off;
        $y = $ay + sin($r) * $off;
        $m = Matrix::translation($x, $y)->multiply(Matrix::rotation($angle));
        $path = self::arrowPath($len)->transform($m);
        return [[
            'path' => $path,
            'fill' => Color::hexToRgb((string) $params['color']),
            'opacity' => (float) $data['o'] * ($params['placement'] === 'behind' ? 0.55 : 1.0),
            'stroke' => null,
            'outline' => ['w' => $len * 0.045, 'color' => Color::hexToRgb((string) $params['outline'])],
            'z' => $params['placement'] === 'behind' ? 'back' : 'front',
        ]];
    }

    public function lottie(LottieBuilder $lb, array $samples, array $params, array $groupBounds, float $canvasWidth = 512.0): array
    {
        [$ax, $ay, $len] = $this->geometry($params, $canvasWidth);
        $angle = $this->angle((string) $params['direction']);
        $travel = $len * 0.45;
        $r = deg2rad($angle);
        $pos = [];
        $op = [];
        $mul = $params['placement'] === 'behind' ? 0.55 : 1.0;
        foreach ($samples as $d) {
            $off = ($d['t'] - 0.5) * $travel;
            $pos[] = [$ax + cos($r) * $off, $ay + sin($r) * $off];
            $op[] = 100 * (float) $d['o'] * $mul;
        }
        $path = self::arrowPath($len)->transform(Matrix::rotation($angle));
        $group = $lb->group('arrow', [
            $lb->shape($path),
            $lb->fill(Color::hexToRgb((string) $params['color'])),
            $lb->stroke(Color::hexToRgb((string) $params['outline']), $len * 0.09),
        ], $lb->transform([0, 0], $lb->animatedVec($pos, 0.6), null, null, $lb->animatedScalar($op, 0.8)));
        return $params['placement'] === 'behind' ? ['front' => [], 'back' => [$group]] : ['front' => [$group], 'back' => []];
    }
}

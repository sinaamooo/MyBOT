<?php

declare(strict_types=1);

namespace EmojiMaker\Vector;

use EmojiMaker\Animation\DecorationEffect;
use EmojiMaker\Animation\EffectRegistry;
use EmojiMaker\Animation\FrameState;
use EmojiMaker\Animation\Timeline;
use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Rendering\Element;
use EmojiMaker\Rendering\Scene;
use EmojiMaker\Rendering\Transform;

/**
 * Converts a vector Scene + Timeline into a Telegram .TGS animation
 * (Lottie JSON, 512x512, 60 FPS, gzip). Uses only features Telegram allows:
 * one shape layer, groups, paths, fills, gradient fills, strokes, trim paths
 * and keyframed transforms (no masks, effects, images, texts, expressions).
 */
final class LottieWriter
{
    public const FPS = 60;

    public function __construct(private EffectRegistry $effects)
    {
    }

    /**
     * @param array{keyTolerance?:float,glowRings?:int,shadow?:bool} $opts
     * @return array<string,mixed>
     */
    public function build(Scene $scene, Timeline $timeline, array $opts = []): array
    {
        if ($scene->tiles > 1) {
            throw new \InvalidArgumentException('Multi-emoji banners are exported as WEBM only.');
        }
        $frames = min(180, $timeline->frameCount(self::FPS));
        $lb = new LottieBuilder($frames, $opts['keyTolerance'] ?? 0.6);
        $glowRings = $opts['glowRings'] ?? 3;
        $withShadow = $opts['shadow'] ?? true;

        // sample every frame (0..frames inclusive, last == first for a seamless loop)
        $states = [];
        for ($i = 0; $i <= $frames; $i++) {
            $states[] = $timeline->evaluate($i % $frames, $frames);
        }
        $groupPivot = [($scene->groupBounds[0] + $scene->groupBounds[2]) / 2, ($scene->groupBounds[1] + $scene->groupBounds[3]) / 2];
        $fit = $scene->fit;

        // layer transform = Fit * Group
        $pos = [];
        $scale = [];
        $rot = [];
        $op = [];
        foreach ($states as $st) {
            $g = $st['group'];
            $pos[] = [256 + $fit * ($groupPivot[0] + $g->dx - 256), 256 + $fit * ($groupPivot[1] + $g->dy - 256)];
            $scale[] = [100 * $fit * $g->scale * $g->sx, 100 * $fit * $g->scale * $g->sy, 100];
            $rot[] = $g->rot;
            $op[] = 100 * max(0.0, min(1.0, $g->opacity));
        }
        $ks = [
            'o' => $lb->animatedScalar($op, 0.8),
            'r' => $lb->animatedScalar($rot, 0.25),
            'p' => $this->with3($lb->animatedVec($pos, 0.25, false, true)),
            'a' => ['a' => 0, 'k' => [round($groupPivot[0], 1), round($groupPivot[1], 1), 0]],
            's' => $lb->animatedVec($scale, 0.4),
        ];

        $shapesTop = [];
        // front decorations
        [$decoFront, $decoBack] = $this->decorations($lb, $states, $scene);
        foreach ($decoFront as $g) {
            $shapesTop[] = $g;
        }
        // elements top-most first
        foreach (array_reverse($scene->elements, true) as $id => $el) {
            $shapesTop[] = $this->elementGroup($lb, $scene, $timeline, $el, $id, $states, $glowRings, $withShadow);
        }
        foreach ($decoBack as $g) {
            $shapesTop[] = $g;
        }

        return [
            'tgs' => 1,
            'v' => '5.5.2',
            'fr' => self::FPS,
            'ip' => 0,
            'op' => $frames,
            'w' => 512,
            'h' => 512,
            'nm' => 'emoji',
            'ddd' => 0,
            'assets' => [],
            'layers' => [[
                'ddd' => 0, 'ind' => 1, 'ty' => 4, 'nm' => 'emoji', 'sr' => 1,
                'ks' => $ks, 'ao' => 0, 'shapes' => $shapesTop,
                'ip' => 0, 'op' => $frames, 'st' => 0, 'bm' => 0,
            ]],
        ];
    }

    public static function encode(array $doc): string
    {
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        $json = (string) preg_replace('/(\d)\.0(?=[,\]\}])/', '$1', (string) $json);
        return (string) gzencode($json, 9);
    }

    private function with3(array $prop): array
    {
        if ($prop['a'] === 0) {
            $prop['k'] = [$prop['k'][0], $prop['k'][1], 0];
            return $prop;
        }
        foreach ($prop['k'] as &$kf) {
            $kf['s'] = [$kf['s'][0], $kf['s'][1], 0];
            if (isset($kf['to'])) {
                $kf['to'] = [0, 0, 0];
                $kf['ti'] = [0, 0, 0];
            }
        }
        unset($kf);
        return $prop;
    }

    /** @return array{0:list<array>,1:list<array>} */
    private function decorations(LottieBuilder $lb, array $states, Scene $scene): array
    {
        $front = [];
        $back = [];
        foreach ($scene->animation->effects as $index => $inst) {
            $effect = $this->effects->get($inst['type']);
            if (!$effect instanceof DecorationEffect) {
                continue;
            }
            $samples = [];
            foreach ($states as $st) {
                $samples[] = $st['group']->deco[$index] ?? ($samples ? end($samples) : []);
            }
            if (!$samples || !$samples[0]) {
                continue;
            }
            $res = $effect->lottie($lb, $samples, $inst['params'], $scene->groupBounds, $scene->width);
            array_push($front, ...$res['front']);
            array_push($back, ...$res['back']);
        }
        return [$front, $back];
    }

    /**
     * One element = group with its animated transform containing (top first):
     * mains, depth (3D), glow, shadow - each split per reveal unit when a
     * typewriter is active.
     */
    private function elementGroup(LottieBuilder $lb, Scene $scene, Timeline $timeline, Element $el, string $id, array $states, int $glowRings, bool $withShadow): array
    {
        $isRevealTarget = $id === $timeline->revealElement();
        $es = [];
        foreach ($states as $st) {
            $es[] = $id === 'bg' ? new FrameState() : $st['elements'][$id];
        }
        $groupStates = array_map(static fn ($st) => $st['group'], $states);
        [$cx, $cy] = $el->center();

        // element transform
        $pos = $sc = $rot = $op = [];
        foreach ($es as $s) {
            $pos[] = [$cx + $s->dx, $cy + $s->dy];
            $sc[] = [100 * $s->scale * $s->sx, 100 * $s->scale * $s->sy];
            $rot[] = $s->rot;
            $op[] = 100 * max(0.0, min(1.0, $s->opacity));
        }
        $transform = $lb->transform([$cx, $cy], $lb->animatedVec($pos, 0.25, false, true), $lb->animatedVec($sc, 0.4), $lb->animatedScalar($rot, 0.25), $lb->animatedScalar($op, 0.8));

        // units (typewriter) or whole element
        $units = [];
        $unitColors = [];
        if ($el->layers) {
            foreach ($el->layers as $l) {
                $units[] = $l['path'];
                $unitColors[] = $l['color'];
            }
        } elseif ($isRevealTarget && $el->units && $this->hasReveal($states, $id)) {
            $units = $el->units;
        } elseif ($el->path !== null) {
            $units = [$el->path];
        }
        $n = count($units);
        $revealOps = [];
        $useReveal = $isRevealTarget && $this->hasReveal($states, $id);
        for ($u = 0; $u < $n; $u++) {
            if (!$useReveal) {
                $revealOps[$u] = null;
                continue;
            }
            $vals = [];
            foreach ($states as $k => $st) {
                $reveal = min($es[$k]->reveal, $st['group']->reveal);
                $visible = (int) round($reveal * $n);
                $vals[] = $u < $visible ? 100.0 : 0.0;
            }
            $revealOps[$u] = $lb->animatedScalar($vals, 0.5, true);
        }

        // colour animation (hue) and light (flash/shade)
        $hues = [];
        $bright = [];
        $shade = [];
        foreach ($es as $k => $s) {
            $g = $groupStates[$k];
            $hues[] = $g->hue + $s->hue;
            $bright[] = 100 * min(1.0, max($g->bright, $s->bright));
            $shade[] = 100 * min(1.0, max($g->shade, $s->shade));
        }
        $hueAnimated = max(array_map(static fn ($h) => abs(fmod($h, 360.0)), $hues)) > 0.5;
        $colorProp = function (array $rgb) use ($lb, $hues, $hueAnimated): ?array {
            if (!$hueAnimated) {
                return null;
            }
            return $lb->animatedColor(array_map(static fn ($h) => Color::rotateHue($rgb, $h), $hues), 4.0);
        };
        $brightAnimated = max($bright) > 0.5;
        $shadeAnimated = max($shade) > 0.5;

        $outlineW = $el->outline['w'] ?? 0.0;
        $mains = [];
        $depths = [];
        $glows = [];
        $shadows = [];
        $rule = $el->layers ? 2 : 1;
        $bounds = $el->bounds;
        foreach ($units as $u => $path) {
            $shapes = $lb->shape($path);
            if (!$shapes) {
                continue;
            }
            $unitOpacity = $revealOps[$u] ?? null;
            $tr = $lb->transform([0, 0], [0, 0], null, null, $unitOpacity);
            // main: flash / shade overlays, fill, extra stroke, outline
            $items = [$shapes];
            if ($brightAnimated && !$el->layers) {
                $items[] = $lb->fill([255, 255, 255], $lb->animatedScalar($bright, 1.0), $rule);
            }
            if ($shadeAnimated && !$el->layers) {
                $items[] = $lb->fill([0, 0, 0], $lb->animatedScalar($shade, 1.0), $rule);
            }
            if ($el->layers) {
                $items[] = $lb->fill($unitColors[$u], 100, 2, $colorProp($unitColors[$u]));
            } elseif ($el->gradient && !$hueAnimated) {
                $items[] = $lb->gradientFill($el->gradient[0], $el->gradient[1], $bounds[1], $bounds[3], ($bounds[0] + $bounds[2]) / 2, $rule);
            } else {
                $base = $el->gradient ? Color::mix($el->gradient[0], $el->gradient[1], 0.5) : $el->fill;
                $items[] = $lb->fill($base, 100, $rule, $colorProp($base));
            }
            if ($outlineW > 0 && !$el->layers) {
                $items[] = $lb->stroke($el->outline['color'], 2 * $outlineW, 100, $colorProp($el->outline['color']));
            }
            $mains[] = $lb->group('main' . $u, $items, $tr);
            if ($el->depth !== null && !$el->layers) {
                $steps = max(2, (int) ceil($el->depth['d'] / max(4.0, $outlineW > 0 ? $outlineW : 4.0)));
                for ($k = $steps; $k >= 1; $k--) {
                    $dy = $el->depth['d'] * $k / $steps;
                    $depthItems = [$shapes, $lb->fill($el->depth['color'], 100, $rule)];
                    if ($outlineW > 0) {
                        $depthItems[] = $lb->stroke($el->depth['color'], 2 * $outlineW);
                    }
                    $depths[] = $lb->group('depth' . $u . '_' . $k, $depthItems, $lb->transform([0, 0], [0, $dy], null, null, $unitOpacity));
                }
            }
            if ($el->glow !== null) {
                $ringItems = [$shapes];
                $rings = array_slice([[0.38, 45], [0.7, 28], [1.0, 18]], 0, max(1, $glowRings));
                foreach ($rings as [$f, $o]) {
                    $ringItems[] = $lb->stroke($el->glow['color'], 2 * ($outlineW + $el->glow['radius'] * $f), $o);
                }
                $glows[] = $lb->group('glow' . $u, $ringItems, $lb->transform([0, 0], [0, 0], null, null, $unitOpacity));
            }
            if ($el->shadow !== null && $withShadow) {
                $shItems = [$shapes, $lb->fill($el->shadow['color'], 100 * $el->shadow['opacity'], $rule)];
                if ($outlineW > 0) {
                    $shItems[] = $lb->stroke($el->shadow['color'], 2 * $outlineW, 100 * $el->shadow['opacity']);
                }
                $shY = $el->shadow['dy'] + ($el->depth['d'] ?? 0);
                $shadows[] = $lb->group('shadow' . $u, $shItems, $lb->transform([0, 0], [$el->shadow['dx'], $shY], null, null, $unitOpacity));
            }
        }
        if ($el->layers && $outlineW > 0 && $el->silhouette) {
            $mains[] = $lb->group('outline', [$lb->shape($el->silhouette), $lb->stroke($el->outline['color'], 2 * $outlineW)]);
        }
        if ($el->extraStroke !== null) {
            array_unshift($mains, $lb->group('rim', [$lb->shape($el->extraStroke['path']), $lb->stroke($el->extraStroke['color'], $el->extraStroke['w'], 85)]));
        }
        // layers are painted bottom-up in the raster renderer: reverse for Lottie (top first)
        if ($el->layers) {
            $mains = array_reverse($mains);
        }
        $children = [];
        if ($mains) {
            $children[] = $lb->group('mains', $mains);
        }
        if ($depths) {
            $children[] = $lb->group('depth', $depths);
        }
        if ($glows) {
            $glowVals = [];
            foreach ($es as $k => $s) {
                $glowVals[] = 100 * min(1.0, $el->glow['strength'] * $groupStates[$k]->glow * $s->glow);
            }
            $children[] = $lb->group('glow', $glows, $lb->transform([0, 0], [0, 0], null, null, $lb->animatedScalar($glowVals, 1.0)));
        }
        if ($shadows) {
            $children[] = $lb->group('shadow', $shadows);
        }
        if ($isRevealTarget && $this->hasCaret($states, $id)) {
            array_unshift($children, $this->caretGroup($lb, $scene, $el, $states, $es));
        }
        return $lb->group($id, $children, $transform);
    }

    private function hasReveal(array $states, string $id): bool
    {
        foreach ($states as $st) {
            if ($st['group']->reveal < 0.999 || (($st['elements'][$id] ?? null)?->reveal ?? 1.0) < 0.999) {
                return true;
            }
        }
        return false;
    }

    private function hasCaret(array $states, string $id): bool
    {
        foreach ($states as $st) {
            if ($st['group']->caret || (($st['elements'][$id] ?? null)?->caret ?? false)) {
                return true;
            }
        }
        return false;
    }

    private function caretGroup(LottieBuilder $lb, Scene $scene, Element $el, array $states, array $es): array
    {
        $height = $el->bounds[3] - $el->bounds[1];
        $w = max(6.0, $height * 0.07);
        $ub = array_map(static fn (Path $p) => $p->bounds(), $el->units);
        $n = max(1, count($el->units));
        $pos = [];
        $op = [];
        foreach ($states as $k => $st) {
            $reveal = min($es[$k]->reveal, $st['group']->reveal);
            $visible = (int) round($reveal * $n);
            $on = $st['group']->caret || $es[$k]->caret;
            if ($visible <= 0 || !isset($ub[$visible - 1])) {
                $x = $scene->rtl ? $el->bounds[2] - $w : $el->bounds[0];
            } else {
                $b = $ub[$visible - 1];
                $x = $scene->rtl ? $b[0] - $w * 1.8 : $b[2] + $w * 0.8;
            }
            $pos[] = [$x, $el->bounds[1]];
            $op[] = $on ? 95.0 : 0.0;
        }
        $items = [$lb->shape(Path::roundedRect(0, 0, $w, $height, $w / 2)), $lb->fill($el->fill)];
        if ($el->outline) {
            $items[] = $lb->stroke($el->outline['color'], min(16.0, 2 * $el->outline['w']));
        }
        return $lb->group('caret', $items, $lb->transform([0, 0], $lb->animatedVec($pos, 0.5, true), null, null, $lb->animatedScalar($op, 0.5, true)));
    }
}

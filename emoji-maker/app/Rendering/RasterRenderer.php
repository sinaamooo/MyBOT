<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Animation\DecorationEffect;
use EmojiMaker\Animation\EffectRegistry;
use EmojiMaker\Animation\FrameState;
use EmojiMaker\Animation\Timeline;
use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Geometry\Stroke;

/**
 * Renders animation frames with GD.
 *
 * Every element is pre-rendered once into high resolution bitmaps (3D depth,
 * outline, fill, gloss / glow / shadow). Per frame the bitmaps are rotated /
 * scaled and resampled into the output frame with sub-pixel precision, then
 * per-pixel operations (opacity, flash, shading, hue, light sweep) are applied
 * at output size. Decorations (arrow, chart, orbit) are rasterized per frame.
 * Banners (wide canvas) are rendered once and sliced into square tiles.
 */
final class RasterRenderer
{
    private const PAD = 6;

    /** @var array<string,array<string,mixed>> */
    private array $prepared = [];
    private float $k;
    private bool $glowAnimated = false;
    private bool $mergeUnder = false;
    private ?string $revealElement;
    private Rasterizer $ras;
    /** @var array<string,\GdImage> reusable layer canvases per size */
    private array $layerCanvas = [];

    public function __construct(
        private Scene $scene,
        private Timeline $timeline,
        private EffectRegistry $effects,
        private int $workRes = 400,
        private bool $highQuality = true,
    ) {
        $this->k = $workRes / Scene::SIZE;
        $this->ras = new Rasterizer(4);
        $this->revealElement = $timeline->revealElement();
    }

    public function scene(): Scene
    {
        return $this->scene;
    }

    // ------------------------------------------------------------------
    // Preparation
    // ------------------------------------------------------------------

    public function prepare(int $fps = 30): void
    {
        $frames = $this->timeline->frameCount($fps);
        $glowValues = [];
        $light = false;
        $typewriter = in_array('typewriter', $this->timeline->spec()->types(), true);
        for ($i = 0; $i < $frames; $i += max(1, intdiv($frames, 30))) {
            $st = $this->timeline->evaluate($i, $frames);
            $glowValues[] = round($st['group']->glow, 3);
            $light = $light || $st['group']->bright > 0.004 || $st['group']->shine !== null;
            foreach ($st['elements'] as $es) {
                $glowValues[] = round($es->glow, 3);
                $light = $light || $es->bright > 0.004 || $es->shine !== null;
            }
        }
        $this->glowAnimated = count(array_unique($glowValues)) > 1;
        $this->mergeUnder = !$light;
        foreach ($this->scene->elements as $id => $el) {
            $this->prepared[$id] = $this->prepareElement($el, $typewriter && $id === $this->revealElement);
        }
        $this->scene->fit = $this->computeFit($frames);
    }

    /** @return array<string,mixed> */
    private function prepareElement(Element $el, bool $withStates): array
    {
        $k = $this->k;
        [$px, $py] = $el->center();
        $margin = $el->styleMargin() * $k + self::PAD;
        $hx = max(abs($el->bounds[0] - $px), abs($el->bounds[2] - $px)) * $k + $margin;
        $hy = max(abs($el->bounds[1] - $py), abs($el->bounds[3] - $py)) * $k + $margin;
        $cw = (int) (2 * ceil($hx));
        $ch = (int) (2 * ceil($hy));
        $m = Matrix::translation($cw / 2, $ch / 2)->multiply(Matrix::scaling($k))->multiply(Matrix::translation(-$px, -$py));
        $info = ['w' => $cw, 'h' => $ch, 'pivot' => [$px, $py], 'm' => $m, 'states' => []];

        $fillMask = null;
        $silhouette = null;
        if ($el->image !== null && $el->imageRect !== null) {
            [$x0, $y0] = $m->apply($el->imageRect[0], $el->imageRect[1]);
            [$x1, $y1] = $m->apply($el->imageRect[2], $el->imageRect[3]);
            $logo = ImageProcessor::canvas($cw, $ch);
            imagealphablending($logo, true);
            imagecopyresampled($logo, $el->image, (int) round($x0), (int) round($y0), 0, 0,
                max(1, (int) round($x1 - $x0)), max(1, (int) round($y1 - $y0)), imagesx($el->image), imagesy($el->image));
            $silhouette = Mask::fromImageAlpha($logo);
            $info['logo'] = $logo;
        } elseif ($el->layers) {
            $silhouette = $this->ras->fillPath($el->silhouette ?? $el->layers[0]['path'], $cw, $ch, $m, Rasterizer::EVENODD);
        } elseif ($el->path !== null) {
            $fillMask = $this->ras->fillPath($el->path, $cw, $ch, $m, $el->fillRule);
            $silhouette = $fillMask;
        }
        $silhouette ??= new Mask($cw, $ch);
        $info['fillMask'] = $fillMask;

        $full = $this->buildBitmaps($el, $info, $silhouette, $fillMask, null);
        $info += $full;

        if ($withStates && $el->unitCount() > 0 && ($el->units || $el->layers)) {
            $info['unitMasks'] = [];
            foreach ($el->units as $ui => $unitPath) {
                $rule = $el->layers ? Rasterizer::EVENODD : $el->fillRule;
                $info['unitMasks'][$ui] = $this->ras->fillPath($unitPath, $cw, $ch, $m, $rule);
            }
            $info['unitBounds'] = array_map(static fn (Path $p) => $p->bounds(), $el->units);
        }
        return $info;
    }

    /**
     * Builds main (3D depth + outline + fill + gloss), glow and shadow bitmaps.
     * @param array<string,mixed> $info
     * @return array{main:\GdImage,glow:?\GdImage,shadow:?\GdImage}
     */
    private function buildBitmaps(Element $el, array $info, Mask $silhouette, ?Mask $fillMask, ?int $visibleUnits): array
    {
        $k = $this->k;
        $cw = $info['w'];
        $ch = $info['h'];
        $vector = $this->scene->mode === 'vector';
        $main = ImageProcessor::canvas($cw, $ch);
        $outlineMask = null;
        if ($el->outline !== null && !$silhouette->isEmpty()) {
            $outlineMask = $silhouette->dilate($el->outline['w'] * $k);
        }
        $body = $outlineMask ?? $silhouette;
        // 3D thickness below the shape
        if ($el->depth !== null && !$body->isEmpty()) {
            $depthPx = (int) round($el->depth['d'] * $k);
            $side = $body->extrude($depthPx);
            $side->paint($main, $el->depth['color']);
            // darker bottom rim for a rounded 3D feel
            $side->subtract($body->extrude(max(1, (int) ($depthPx * 0.55))))->paint($main, Color::mix($el->depth['color'], [0, 0, 0], 0.25), 0.8);
        }
        if ($outlineMask !== null) {
            $outlineMask->paint($main, $el->outline['color']);
        }
        $gradBox = null;
        if ($el->gradient) {
            [$gx0, $gy0] = $info['m']->apply($el->bounds[0], $el->bounds[1]);
            [$gx1, $gy1] = $info['m']->apply($el->bounds[2], $el->bounds[3]);
            $gradBox = [(int) $gx0, (int) $gy0, (int) $gx1, (int) $gy1];
        }
        if (isset($info['logo']) && $visibleUnits === null) {
            imagealphablending($main, true);
            imagecopy($main, $info['logo'], 0, 0, 0, 0, $cw, $ch);
        } elseif ($el->layers) {
            foreach ($el->layers as $li => $layer) {
                if ($visibleUnits !== null && $li >= $visibleUnits) {
                    break;
                }
                $mask = $info['unitMasks'][$li] ?? $this->ras->fillPath($layer['path'], $cw, $ch, $info['m'], Rasterizer::EVENODD);
                $mask->paint($main, $layer['color']);
            }
        } elseif ($fillMask !== null) {
            $fillMask->paint($main, $el->gradient[0] ?? $el->fill, 1.0, $el->gradient[1] ?? null, $gradBox);
        }
        if ($el->extraStroke !== null && $fillMask !== null) {
            $polys = [];
            foreach ($el->extraStroke['path']->transform($info['m'])->flatten(0.3) as $poly) {
                foreach (Stroke::polyline($poly, $el->extraStroke['w'] * $k, true) as $sp) {
                    $polys[] = $sp;
                }
            }
            $this->ras->fillPolygons($polys, $cw, $ch)->paint($main, $el->extraStroke['color'], 0.85);
        }
        if ($el->gloss && !$vector && !$silhouette->isEmpty()) {
            // specular highlight on the upper part of the shape
            $b = $silhouette->bounds() ?? [0, 0, $cw, $ch];
            $hl = Path::ellipse(($b[0] + $b[2]) / 2, $b[1] + ($b[3] - $b[1]) * 0.12, ($b[2] - $b[0]) * 0.46, ($b[3] - $b[1]) * 0.3);
            $hlMask = $this->ras->fillPath($hl, $cw, $ch)->blur(max(2.0, ($b[3] - $b[1]) * 0.04));
            $hlMask->intersect($fillMask ?? $silhouette)->paint($main, [255, 255, 255], 0.28);
        }

        $glow = null;
        if ($el->glow !== null && !$body->isEmpty()) {
            $glow = ImageProcessor::canvas($cw, $ch);
            $r = $el->glow['radius'] * $k;
            if ($vector) {
                foreach ([[1.0, 0.18], [0.7, 0.28], [0.38, 0.45]] as [$f, $o]) {
                    $body->dilate($r * $f)->paint($glow, $el->glow['color'], $o);
                }
            } else {
                $body->dilate($r * 0.3)->blur($r * 0.75)->scaleValues(1.35)->paint($glow, $el->glow['color'], 1.0);
            }
        }
        $shadow = null;
        if ($el->shadow !== null && !$body->isEmpty()) {
            $shadow = ImageProcessor::canvas($cw, $ch);
            $base = $el->depth !== null ? $body->extrude((int) round($el->depth['d'] * $k)) : $body;
            $sm = $base->offset((int) round($el->shadow['dx'] * $k), (int) round($el->shadow['dy'] * $k));
            if (!$vector && $el->shadow['blur'] > 0) {
                $sm = $sm->blur($el->shadow['blur'] * $k);
            }
            $sm->paint($shadow, $el->shadow['color'], $el->shadow['opacity']);
        }
        if (!$this->glowAnimated && $glow !== null) {
            $merged = $shadow ?? ImageProcessor::canvas($cw, $ch);
            self::scaleAlpha($glow, $el->glow['strength']);
            imagealphablending($merged, true);
            imagecopy($merged, $glow, 0, 0, 0, 0, $cw, $ch);
            $glow = null;
            $shadow = $merged;
        }
        if ($this->mergeUnder && $glow === null && $shadow !== null) {
            imagealphablending($shadow, true);
            imagecopy($shadow, $main, 0, 0, 0, 0, $cw, $ch);
            return ['main' => $shadow, 'glow' => null, 'shadow' => null];
        }
        return ['main' => $main, 'glow' => $glow, 'shadow' => $shadow];
    }

    /** Bitmaps for a reveal state (typewriter), cached per visible unit count. */
    private function stateBitmaps(string $id, float $reveal): array
    {
        $info = &$this->prepared[$id];
        $el = $this->scene->elements[$id];
        $n = $el->unitCount();
        if ($reveal >= 0.999 || !isset($info['unitMasks'])) {
            return ['main' => $info['main'], 'glow' => $info['glow'], 'shadow' => $info['shadow']];
        }
        $visible = (int) round($reveal * $n);
        if (isset($info['states'][$visible])) {
            return $info['states'][$visible];
        }
        if ($visible <= 0) {
            return $info['states'][0] = ['main' => null, 'glow' => null, 'shadow' => null];
        }
        $fill = new Mask($info['w'], $info['h']);
        for ($u = 0; $u < $visible; $u++) {
            $fill = $fill->union($info['unitMasks'][$u]);
        }
        $bitmaps = $this->buildBitmaps($el, $info, $fill, $el->layers ? null : $fill, $el->layers ? $visible : null);
        return $info['states'][$visible] = $bitmaps;
    }

    // ------------------------------------------------------------------
    // Fit (keep the animation on the canvas)
    // ------------------------------------------------------------------

    private function computeFit(int $frames): float
    {
        $vector = $this->scene->mode === 'vector';
        $cx = $this->scene->width / 2;
        $limitX = $this->scene->width / 2 - ($vector ? 4.0 : 6.0);
        $limitY = $vector ? 252.0 : 250.0;
        $overflowAllowed = false;
        foreach ($this->timeline->spec()->effects as $e) {
            if ($this->effects->get($e['type'])->allowsOverflow()) {
                $overflowAllowed = !$vector;
            }
        }
        $need = 1.0;
        $step = max(1, intdiv($frames, 45));
        $check = static function (float $x, float $y) use (&$need, $cx, $limitX, $limitY): void {
            $ex = abs($x - $cx);
            $ey = abs($y - 256);
            if ($ex > $limitX) {
                $need = min($need, $limitX / $ex);
            }
            if ($ey > $limitY) {
                $need = min($need, $limitY / $ey);
            }
        };
        for ($i = 0; $i < $frames; $i += $step) {
            $st = $this->timeline->evaluate($i, $frames);
            $g = $st['group'];
            if ($overflowAllowed && (abs($g->dx) > 30 || abs($g->dy) > 30)) {
                continue;
            }
            $G = Transform::fromState($g, $this->groupPivot());
            foreach ($this->scene->elements as $id => $el) {
                $es = $id === 'bg' ? new FrameState() : $st['elements'][$id];
                if ($g->opacity * $es->opacity < 0.03) {
                    continue;
                }
                if ($overflowAllowed && (abs($es->dx) > 30 || abs($es->dy) > 30)) {
                    continue;
                }
                $M = $G->multiply(Transform::fromState($es, $el->center()));
                foreach ($el->hullPoints($el->styleMargin() * 0.6) as [$x, $y]) {
                    [$tx, $ty] = $M->apply($x, $y);
                    $check($tx, $ty);
                }
            }
            foreach ($this->decorationShapes($g) as $shape) {
                $b = $shape['path']->bounds();
                if ($b === null || $shape['opacity'] < 0.03) {
                    continue;
                }
                $mg = ($shape['outline']['w'] ?? 0) + ($shape['stroke']['w'] ?? 0) / 2;
                foreach ([[$b[0] - $mg, $b[1] - $mg], [$b[2] + $mg, $b[3] + $mg], [$b[0] - $mg, $b[3] + $mg], [$b[2] + $mg, $b[1] - $mg]] as [$x, $y]) {
                    [$tx, $ty] = $G->apply($x, $y);
                    $check($tx, $ty);
                }
            }
        }
        return max(0.35, $need);
    }

    /** @return array{0:float,1:float} */
    public function groupPivot(): array
    {
        $b = $this->scene->groupBounds;
        return [($b[0] + $b[2]) / 2, ($b[1] + $b[3]) / 2];
    }

    /** @return list<array<string,mixed>> */
    private function decorationShapes(FrameState $g): array
    {
        $out = [];
        foreach ($this->timeline->spec()->effects as $index => $inst) {
            $effect = $this->effects->get($inst['type']);
            if (!$effect instanceof DecorationEffect || !isset($g->deco[$index])) {
                continue;
            }
            foreach ($effect->shapes($g->deco[$index], $inst['params'], $this->scene->groupBounds, $this->scene->width) as $shape) {
                $out[] = $shape;
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Frame rendering
    // ------------------------------------------------------------------

    public function outputWidth(int $outH): int
    {
        return (int) round($outH * $this->scene->width / Scene::SIZE);
    }

    /**
     * Renders one frame of the full canvas (outH tall; banners are wider).
     * @param array{0:int,1:int,2:int}|null $background opaque background (previews)
     */
    public function renderFrame(int $i, int $frames, int $outH, ?array $background = null, bool $checker = false): \GdImage
    {
        $outW = $this->outputWidth($outH);
        $st = $this->timeline->evaluate($i, $frames);
        $g = $st['group'];
        $frame = ImageProcessor::canvas($outW, $outH);
        if ($checker) {
            self::drawChecker($frame, $outW, $outH);
        } elseif ($background !== null) {
            imagefilledrectangle($frame, 0, 0, $outW - 1, $outH - 1, imagecolorallocate($frame, $background[0], $background[1], $background[2]));
        }
        imagealphablending($frame, true);
        $F = $this->fitMatrix();
        $G = $F->multiply(Transform::fromState($g, $this->groupPivot()));
        $decos = $this->decorationShapes($g);
        $this->drawShapes($frame, $decos, 'back', $G, $outW, $outH, $g);

        foreach ($this->scene->elements as $id => $el) {
            $es = $id === 'bg' ? new FrameState() : $st['elements'][$id];
            $opacity = $g->opacity * $es->opacity;
            if ($opacity < 0.004) {
                continue;
            }
            $M = $G->multiply(Transform::fromState($es, $el->center()));
            $reveal = $es->reveal;
            if ($id === $this->revealElement) {
                $reveal = min($reveal, $g->reveal);
            }
            $info = $this->prepared[$id];
            $bitmaps = ($el->wipeReveal || $reveal >= 0.999) ? ['main' => $info['main'], 'glow' => $info['glow'], 'shadow' => $info['shadow']] : $this->stateBitmaps($id, $reveal);
            $ops = [
                'opacity' => $opacity,
                'hue' => $g->hue + $es->hue,
                'bright' => max($g->bright, $es->bright),
                'shade' => max($g->shade, $es->shade),
                'shine' => $es->shine ?? $g->shine,
                'shineStrength' => max($es->shineStrength, $g->shineStrength),
                'clip' => ($el->wipeReveal && $reveal < 0.999) ? $this->wipeClip($M, $el, $reveal, $outH) : null,
            ];
            if ($bitmaps['shadow'] !== null) {
                $this->drawBitmap($frame, $bitmaps['shadow'], $M, $info['pivot'], $outW, $outH, ['bright' => 0.0, 'shade' => 0.0, 'shine' => null] + $ops);
            }
            if ($bitmaps['glow'] !== null && $el->glow !== null) {
                $go = min(1.0, $el->glow['strength'] * $g->glow * $es->glow);
                $this->drawBitmap($frame, $bitmaps['glow'], $M, $info['pivot'], $outW, $outH, ['opacity' => $opacity * $go, 'bright' => 0.0, 'shade' => 0.0, 'shine' => null] + $ops);
            }
            if ($bitmaps['main'] !== null) {
                $this->drawBitmap($frame, $bitmaps['main'], $M, $info['pivot'], $outW, $outH, $ops, $el->rotationInvariant);
            }
            if (($g->caret || $es->caret) && $id === $this->revealElement && !$el->wipeReveal) {
                $this->drawCaret($frame, $el, $info, $reveal, $M, $outW, $outH, $opacity, $ops['hue']);
            }
        }
        $this->drawShapes($frame, $decos, 'front', $G, $outW, $outH, $g);
        return $frame;
    }

    /**
     * Splits a banner frame into square tiles (left to right).
     * @return list<\GdImage>
     */
    public static function sliceTiles(\GdImage $frame, int $tiles): array
    {
        if ($tiles <= 1) {
            return [$frame];
        }
        $h = imagesy($frame);
        $w = imagesx($frame);
        $out = [];
        for ($t = 0; $t < $tiles; $t++) {
            $x0 = (int) round($t * $w / $tiles);
            $x1 = (int) round(($t + 1) * $w / $tiles);
            $tile = ImageProcessor::canvas($h, $h);
            imagecopyresampled($tile, $frame, 0, 0, $x0, 0, $h, $h, $x1 - $x0, $h);
            $out[] = $tile;
        }
        return $out;
    }

    private function fitMatrix(): Matrix
    {
        $fit = $this->scene->fit;
        if (abs($fit - 1.0) < 1e-6) {
            return Matrix::identity();
        }
        $cx = $this->scene->width / 2;
        return Matrix::translation($cx, 256)->multiply(Matrix::scaling($fit))->multiply(Matrix::translation(-$cx, -256));
    }

    /** @return array{0:int,1:int} x clip range in output pixels */
    private function wipeClip(Matrix $M, Element $el, float $reveal, int $outH): array
    {
        $s = $outH / Scene::SIZE;
        $b = $el->bounds;
        $xs = [];
        foreach ([[$b[0], $b[1]], [$b[2], $b[1]], [$b[0], $b[3]], [$b[2], $b[3]]] as [$x, $y]) {
            $xs[] = $M->apply($x, $y)[0] * $s;
        }
        $x0 = min($xs);
        $x1 = max($xs);
        return [(int) floor($x0) - 4, (int) round($x0 + ($x1 - $x0) * $reveal)];
    }

    private function drawCaret(\GdImage $frame, Element $el, array $info, float $reveal, Matrix $M, int $outW, int $outH, float $opacity, float $hue): void
    {
        $n = $el->unitCount();
        $visible = (int) round($reveal * $n);
        $ub = $info['unitBounds'] ?? [];
        $height = $el->bounds[3] - $el->bounds[1];
        $w = max(6.0, $height * 0.07);
        if ($visible <= 0 || !isset($ub[$visible - 1]) || $ub[$visible - 1] === null) {
            $x = $this->scene->rtl ? $el->bounds[2] - $w : $el->bounds[0];
            $y0 = $el->bounds[1];
        } else {
            $b = $ub[$visible - 1];
            $x = $this->scene->rtl ? $b[0] - $w * 1.8 : $b[2] + $w * 0.8;
            $y0 = $el->bounds[1];
            if (($b[3] - $b[1]) < $height * 0.6) {
                $y0 = ($b[1] + $b[3]) / 2 - $height * 0.4;
                $height *= 0.8;
            }
        }
        $this->drawShapes($frame, [[
            'path' => Path::roundedRect($x, $y0, $w, $height, $w / 2), 'fill' => $el->fill, 'opacity' => 0.95, 'stroke' => null,
            'outline' => $el->outline ? ['w' => min($el->outline['w'], 8.0), 'color' => $el->outline['color']] : null, 'z' => 'front',
        ]], 'front', $M, $outW, $outH, null, $opacity, $hue);
    }

    /**
     * Rasterizes vector shapes (decorations, caret) at output resolution.
     * @param list<array<string,mixed>> $shapes
     */
    private function drawShapes(\GdImage $frame, array $shapes, string $z, Matrix $G, int $outW, int $outH, ?FrameState $g, float $opacityMul = 1.0, float $hueOverride = 0.0): void
    {
        $s = $outH / Scene::SIZE;
        $M = Matrix::scaling($s)->multiply($G);
        $scaleFactor = $M->scaleFactor();
        $groupOpacity = $g ? $g->opacity : 1.0;
        $hue = $g ? $g->hue : $hueOverride;
        foreach ($shapes as $shape) {
            if ($shape['z'] !== $z) {
                continue;
            }
            $opacity = $shape['opacity'] * $groupOpacity * $opacityMul;
            if ($opacity < 0.004) {
                continue;
            }
            $path = $shape['path']->transform($M);
            if ($shape['stroke'] !== null) {
                $polys = [];
                foreach ($path->flatten(0.15) as $poly) {
                    foreach (Stroke::polyline($poly, $shape['stroke']['w'] * $scaleFactor, false) as $sp) {
                        $polys[] = $sp;
                    }
                }
                $mask = $this->ras->fillPolygons($polys, $outW, $outH, Rasterizer::NONZERO);
                $color = $shape['stroke']['color'];
            } else {
                $mask = $this->ras->fillPath($path, $outW, $outH, null, Rasterizer::NONZERO);
                $color = $shape['fill'] ?? [255, 255, 255];
            }
            if ($shape['outline'] !== null) {
                $mask->dilate($shape['outline']['w'] * $scaleFactor)->paint($frame, Color::rotateHue($shape['outline']['color'], $hue), $opacity);
            }
            $mask->paint($frame, Color::rotateHue($color, $hue), $opacity);
        }
    }

    /**
     * Transforms a pivot-centred bitmap with design matrix $M into the frame.
     * @param array{0:float,1:float} $pivot
     * @param array<string,mixed> $ops
     */
    private function drawBitmap(\GdImage $frame, \GdImage $src, Matrix $M, array $pivot, int $outW, int $outH, array $ops, bool $rotationInvariant = false): void
    {
        $so = $outH / Scene::SIZE;
        [$ox, $oy] = $M->apply($pivot[0], $pivot[1]);
        $ox *= $so;
        $oy *= $so;
        $lin = new Matrix($M->a, $M->b, $M->c, $M->d, 0, 0);
        $img = $src;
        if ($M->a * $M->d - $M->b * $M->c < 0) {
            // mirrored (3D flip showing the back side): flip horizontally first
            $flipped = imagecreatetruecolor(imagesx($src), imagesy($src));
            imagealphablending($flipped, false);
            imagesavealpha($flipped, true);
            imagecopy($flipped, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
            imageflip($flipped, IMG_FLIP_HORIZONTAL);
            $img = $flipped;
            $lin = $lin->multiply(Matrix::scaling(-1, 1));
        }
        $svd = Transform::svd($lin);
        $phi = $svd['phi'];
        $theta = $svd['theta'];
        $s1 = $svd['s1'];
        $s2 = $svd['s2'];
        if ($s1 < 1e-4 || $s2 < 1e-4) {
            return;
        }
        if ($theta > 45) {
            $phi += 90;
            $theta -= 90;
            [$s1, $s2] = [$s2, $s1];
        } elseif ($theta < -45) {
            $phi -= 90;
            $theta += 90;
            [$s1, $s2] = [$s2, $s1];
        }
        $uniform = abs($s1 - $s2) < 0.002 * max($s1, $s2);
        if ($uniform) {
            $rot = $phi + $theta;
            $su = ($s1 + $s2) / 2;
        } else {
            if (abs($theta) > 0.05 && !$rotationInvariant) {
                $img = $this->rotate($img, $theta);
            }
            $ratio = $s1 / $s2;
            $nw = max(2, (int) round(imagesx($img) * $ratio));
            $tmp = ImageProcessor::canvas($nw, imagesy($img));
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, imagesy($img), imagesx($img), imagesy($img));
            $img = $tmp;
            $rot = $phi;
            $su = $s2;
        }
        $rot = fmod($rot, 360.0);
        if ($rotationInvariant) {
            $rot = 0.0;
        }
        if (abs($rot) > 0.05 && abs(abs($rot) - 360) > 0.05) {
            $img = $this->rotate($img, $rot);
        }
        $kb = $su * $so / $this->k;
        $w = imagesx($img);
        $h = imagesy($img);
        $layer = $this->layer($outW, $outH);
        $rect = self::blitScaled($layer, $img, $ox - ($w / 2) * $kb, $oy - ($h / 2) * $kb, $kb, $outW, $outH);
        if ($rect === null) {
            return;
        }
        if ($ops['clip'] !== null) {
            $rect[0] = max($rect[0], $ops['clip'][0]);
            $rect[2] = min($rect[2], $ops['clip'][1]);
            if ($rect[2] <= $rect[0]) {
                return;
            }
        }
        $this->pixelOps($layer, $rect, $ops, $outW);
        imagealphablending($frame, true);
        imagecopy($frame, $layer, $rect[0], $rect[1], $rect[0], $rect[1], $rect[2] - $rect[0], $rect[3] - $rect[1]);
    }

    private function rotate(\GdImage $img, float $deg): \GdImage
    {
        imagesetinterpolation($img, $this->highQuality ? IMG_BILINEAR_FIXED : IMG_NEAREST_NEIGHBOUR);
        $r = imagerotate($img, -$deg, imagecolorallocatealpha($img, 0, 0, 0, 127));
        if (!$r instanceof \GdImage) {
            return $img;
        }
        imagealphablending($r, false);
        imagesavealpha($r, true);
        return $r;
    }

    private function layer(int $w, int $h): \GdImage
    {
        $key = $w . 'x' . $h;
        if (!isset($this->layerCanvas[$key])) {
            $this->layerCanvas[$key] = ImageProcessor::canvas($w, $h);
        }
        $l = $this->layerCanvas[$key];
        imagealphablending($l, false);
        imagefilledrectangle($l, 0, 0, $w - 1, $h - 1, 0x7F000000);
        return $l;
    }

    /**
     * Scaled copy with sub-pixel accurate placement (rounding happens in the
     * high resolution source domain).
     * @return array{0:int,1:int,2:int,3:int}|null dirty rect x0,y0,x1,y1
     */
    public static function blitScaled(\GdImage $dst, \GdImage $src, float $tx, float $ty, float $kb, int $dstW, ?int $dstH = null): ?array
    {
        $dstH ??= $dstW;
        $w = imagesx($src);
        $h = imagesy($src);
        if ($kb <= 0) {
            return null;
        }
        $X0 = max(0.0, $tx);
        $Y0 = max(0.0, $ty);
        $X1 = min((float) $dstW, $tx + $w * $kb);
        $Y1 = min((float) $dstH, $ty + $h * $kb);
        if ($X1 - $X0 < 0.5 || $Y1 - $Y0 < 0.5) {
            return null;
        }
        $dx0 = (int) ceil($X0 - 1e-6);
        $dy0 = (int) ceil($Y0 - 1e-6);
        $dx1 = (int) floor($X1 + 1e-6);
        $dy1 = (int) floor($Y1 + 1e-6);
        if ($dx1 <= $dx0 || $dy1 <= $dy0) {
            return null;
        }
        $sx0 = max(0, min($w - 1, (int) round(($dx0 - $tx) / $kb)));
        $sy0 = max(0, min($h - 1, (int) round(($dy0 - $ty) / $kb)));
        $sx1 = max($sx0 + 1, min($w, (int) round(($dx1 - $tx) / $kb)));
        $sy1 = max($sy0 + 1, min($h, (int) round(($dy1 - $ty) / $kb)));
        imagealphablending($dst, false);
        imagecopyresampled($dst, $src, $dx0, $dy0, $sx0, $sy0, $dx1 - $dx0, $dy1 - $dy0, $sx1 - $sx0, $sy1 - $sy0);
        return [$dx0, $dy0, $dx1, $dy1];
    }

    /**
     * Per-pixel operations at output resolution.
     * @param array{0:int,1:int,2:int,3:int} $rect
     * @param array<string,mixed> $ops
     */
    private function pixelOps(\GdImage $im, array $rect, array $ops, int $outW): void
    {
        $opacity = (float) $ops['opacity'];
        $bright = (float) $ops['bright'];
        $shade = (float) $ops['shade'];
        $hue = (float) $ops['hue'];
        $shine = $ops['shine'];
        $shineStrength = (float) $ops['shineStrength'];
        $doHue = abs(fmod($hue, 360.0)) > 0.5;
        $doShine = $shine !== null && $shineStrength > 0.01;
        $doBright = $bright > 0.004;
        $doShade = $shade > 0.004;
        $doOpacity = $opacity < 0.996;
        if (!$doHue && !$doShine && !$doBright && !$doShade && !$doOpacity) {
            return;
        }
        $m = $doHue ? Color::hueMatrix($hue) : null;
        imagealphablending($im, false);
        $band = 0.16;
        $span = imagesy($im) * 1.55 * max(1.0, $outW / max(1, imagesy($im)) * 0.65);
        for ($y = $rect[1]; $y < $rect[3]; $y++) {
            for ($x = $rect[0]; $x < $rect[2]; $x++) {
                $c = imagecolorat($im, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 127) {
                    continue;
                }
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                if ($m !== null) {
                    $nr = $m[0] * $r + $m[1] * $g + $m[2] * $b;
                    $ng = $m[3] * $r + $m[4] * $g + $m[5] * $b;
                    $nb = $m[6] * $r + $m[7] * $g + $m[8] * $b;
                    $r = $nr < 0 ? 0 : ($nr > 255 ? 255 : (int) $nr);
                    $g = $ng < 0 ? 0 : ($ng > 255 ? 255 : (int) $ng);
                    $b = $nb < 0 ? 0 : ($nb > 255 ? 255 : (int) $nb);
                }
                if ($doShade) {
                    $f = 1 - $shade;
                    $r = (int) ($r * $f);
                    $g = (int) ($g * $f);
                    $b = (int) ($b * $f);
                }
                $w = $doBright ? $bright : 0.0;
                if ($doShine) {
                    $d = ($x + $y * 0.55) / $span;
                    $dist = abs($d - $shine);
                    if ($dist < $band) {
                        $t = 1 - $dist / $band;
                        $w = max($w, $shineStrength * $t * $t);
                    }
                }
                if ($w > 0) {
                    $r = (int) ($r + (255 - $r) * $w);
                    $g = (int) ($g + (255 - $g) * $w);
                    $b = (int) ($b + (255 - $b) * $w);
                }
                if ($doOpacity) {
                    $a = 127 - (int) round((127 - $a) * $opacity);
                }
                imagesetpixel($im, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }
    }

    public static function scaleAlpha(\GdImage $im, float $f): void
    {
        if ($f >= 0.999) {
            return;
        }
        $w = imagesx($im);
        $h = imagesy($im);
        imagealphablending($im, false);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($im, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 127) {
                    continue;
                }
                $na = 127 - (int) round((127 - $a) * $f);
                imagesetpixel($im, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
            }
        }
    }

    public static function drawChecker(\GdImage $im, int $w, ?int $h = null): void
    {
        $h ??= $w;
        $c1 = imagecolorallocate($im, 238, 238, 238);
        $c2 = imagecolorallocate($im, 205, 205, 205);
        $cell = max(4, intdiv($h, 12));
        imagealphablending($im, false);
        for ($y = 0; $y < $h; $y += $cell) {
            for ($x = 0; $x < $w; $x += $cell) {
                imagefilledrectangle($im, $x, $y, min($w - 1, $x + $cell - 1), min($h - 1, $y + $cell - 1), (intdiv($x, $cell) + intdiv($y, $cell)) % 2 ? $c2 : $c1);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Animation\EffectRegistry;
use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Geometry\Shapes;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Text\TextLayout;
use EmojiMaker\Vector\Vectorizer;

/**
 * Builds a Scene (layout + styles) from a DesignSpec.
 *  mode "raster": logo kept as bitmap, soft glow/shadow  -> WEBM / PNG
 *  mode "vector": logo traced to paths, glow as strokes  -> TGS (Lottie)
 * Layouts: single emoji (512x512) or multi-tile banner (N x 512 wide).
 */
final class SceneBuilder
{
    /** @var array<string,\GdImage> processed logo cache (per process) */
    private static array $logoCache = [];

    public function __construct(
        private FontRegistry $fonts,
        private EffectRegistry $effects,
        private int $vectorMaxColors = 6,
    ) {
    }

    public function build(DesignSpec $d, string $mode = 'raster'): Scene
    {
        $scene = new Scene($d->animation, $mode);
        if ($d->isBanner()) {
            return $this->buildBanner($d, $scene);
        }
        $S = Scene::SIZE;
        [$outline, $shadow, $glow] = $this->styles($d, $mode);
        $styleMargin = ($outline['w'] ?? 0) + ($glow ? $glow['radius'] * 0.9 : 0) + ($shadow ? 6 : 0) + $d->textDepth;
        $margin = max(18.0, min(70.0, 14 + $styleMargin));
        $box = [$margin, $margin, $S - $margin, $S - $margin];

        // frame / badge background
        if ($d->bgType !== 'none') {
            $bgBox = $this->bgBox($d);
            $bg = $this->shapeElement('bg', $d->bgType, $bgBox, $d);
            $scene->add($bg);
            $inner = Shapes::innerBox($d->bgType, $bgBox);
            $pad = min(26.0, 6 + ($outline['w'] ?? 0) * 0.6);
            $box = [$inner[0] + $pad, $inner[1] + $pad, $inner[2] - $pad, $inner[3] - $pad];
        }

        $hasText = $d->hasText();
        $hasImage = $d->hasImage();
        $hasSub = $d->subtitle !== '';
        $H = $box[3] - $box[1];
        $subBox = null;
        if ($hasSub) {
            $subH = $H * $d->subtitleSize;
            $subBox = [$box[0] + ($box[2] - $box[0]) * 0.08, $box[3] - $subH, $box[2] - ($box[2] - $box[0]) * 0.08, $box[3]];
            $box[3] -= $subH * 1.12;
            $H = $box[3] - $box[1];
        }
        $logoBox = $box;
        $textBox = $box;
        if ($hasText && $hasImage) {
            $pos = $d->textPosition === 'auto' ? 'bottom' : $d->textPosition;
            if ($pos === 'bottom') {
                $logoBox = [$box[0], $box[1], $box[2], $box[1] + $H * 0.64];
                $textBox = [$box[0], $box[3] - $H * 0.31, $box[2], $box[3]];
            } elseif ($pos === 'top') {
                $textBox = [$box[0], $box[1], $box[2], $box[1] + $H * 0.31];
                $logoBox = [$box[0], $box[3] - $H * 0.64, $box[2], $box[3]];
            } else {
                $textBox = [$box[0], ($box[1] + $box[3]) / 2 - $H * 0.19, $box[2], ($box[1] + $box[3]) / 2 + $H * 0.19];
            }
        }

        if ($hasImage) {
            $logo = $this->buildLogo($d, $logoBox, $mode);
            if ($logo !== null) {
                $logo->outline = $outline;
                $logo->shadow = $shadow;
                $logo->glow = $glow;
                $scene->add($logo);
            }
        }
        if ($hasText) {
            $text = $this->buildText('text', $d->text, $d->font, $d->letterSpacing, $textBox, $d->textSize, $scene);
            if ($text !== null) {
                $this->styleText($text, $d, $outline, $shadow, $glow);
                $scene->add($text);
            }
        }
        if ($hasSub && $subBox !== null) {
            $sub = $this->buildText('sub', $d->subtitle, $d->subtitleFont, 0.02, $subBox, 1.0, $scene);
            if ($sub !== null) {
                $sub->fill = Color::hexToRgb($d->subtitleColor);
                if ($outline !== null && $d->bgType === 'none') {
                    $sub->outline = ['w' => max(4.0, $outline['w'] * 0.45), 'color' => $outline['color']];
                }
                $scene->add($sub);
            }
        }
        if (!$scene->has('logo') && !$scene->has('text') && !$scene->has('bg') && !$this->hasDecoration($d)) {
            throw new ImageException('طرح خالی است؛ یک متن یا تصویر برای ساخت ایموجی لازم است.');
        }
        $scene->recomputeGroupBounds();
        return $scene;
    }

    /**
     * Multi-emoji banner: one wide canvas split into N square tiles which are
     * placed side by side in a message (e.g. "@channel", "LONG", "FULL TP").
     */
    private function buildBanner(DesignSpec $d, Scene $scene): Scene
    {
        if (trim($d->text) === '') {
            throw new ImageException('برای بنر چندتکه، متن لازم است.');
        }
        [$outline, $shadow, $glow] = $this->styles($d, $scene->mode);
        $layout = new TextLayout($this->fonts->shaper($d->font), $d->text, $d->letterSpacing);
        if ($layout->isEmpty()) {
            throw new ImageException('متن بنر قابل نمایش نیست.');
        }
        [$iw, $ih] = $layout->inkSize();
        $style = $d->bannerStyle;
        $boxY = $style === 'none' ? [40.0, 472.0] : ($style === 'pill' ? [112.0, 400.0] : [84.0, 428.0]);
        $tiles = $d->bannerTiles;
        if ($tiles === 0) {
            $aspect = $iw / max(0.01, max($ih, 0.62));
            $textWidth = $aspect * ($boxY[1] - $boxY[0]) * $d->textSize;
            $tiles = (int) max(2, min(8, ceil(($textWidth + 56) / Scene::SIZE)));
        }
        $W = $tiles * Scene::SIZE;
        $scene->width = $W;
        $scene->tiles = $tiles;
        $bandTop = 36.0;
        $bandBottom = 476.0;
        if ($style === 'bar' || $style === 'pill') {
            [$y0, $y1] = $style === 'pill' ? [72.0, 440.0] : [$bandTop, $bandBottom];
            $bg = new Element('bg', [6, $y0, $W - 6, $y1]);
            $bg->path = $style === 'pill' ? Path::roundedRect(6, $y0, $W - 12, $y1 - $y0, ($y1 - $y0) / 2) : Path::roundedRect(4, $y0, $W - 8, $y1 - $y0, 46);
            $this->applyBgStyle($bg, $d);
            $scene->add($bg);
        } elseif ($style === 'lines') {
            $bg = new Element('bg', [0, 28, $W, 484]);
            $p = Path::rect(0, 28, $W, 26);
            $p->append(Path::rect(0, 458, $W, 26));
            $bg->path = $p;
            $bg->fill = Color::hexToRgb($d->bgBorderWidth > 0 ? $d->bgBorderColor : $d->bgColor);
            $scene->add($bg);
        }
        $marginX = 28.0;
        $box = [$marginX, $boxY[0], $W - $marginX, $boxY[1]];
        $text = $this->buildText('text', $d->text, $d->font, $d->letterSpacing, $box, $d->textSize, $scene, $layout);
        if ($text !== null) {
            $this->styleText($text, $d, $outline, $shadow, $glow);
            $scene->add($text);
        }
        $scene->recomputeGroupBounds();
        return $scene;
    }

    /** @return array{0:?array,1:?array,2:?array} outline, shadow, glow */
    private function styles(DesignSpec $d, string $mode): array
    {
        $glow = null;
        if ($d->glow) {
            $glow = ['color' => Color::hexToRgb($d->glowColor), 'radius' => $d->glowRadius, 'strength' => $d->glowStrength];
        }
        foreach ($d->animation->effects as $e) {
            if ($this->effects->get($e['type'])->requiresGlow() && $glow === null) {
                $glow = ['color' => Color::hexToRgb((string) ($e['params']['color'] ?? $d->glowColor)), 'radius' => $d->glowRadius, 'strength' => $d->glowStrength];
            }
        }
        $outline = $d->outlineWidth > 0 ? ['w' => $d->outlineWidth, 'color' => Color::hexToRgb($d->outlineColor)] : null;
        $shadow = $d->shadow ? [
            'color' => Color::hexToRgb($d->shadowColor), 'opacity' => $d->shadowOpacity,
            'dx' => $d->shadowDx, 'dy' => $d->shadowDy, 'blur' => $mode === 'vector' ? 0.0 : $d->shadowBlur,
        ] : null;
        return [$outline, $shadow, $glow];
    }

    private function styleText(Element $text, DesignSpec $d, ?array $outline, ?array $shadow, ?array $glow): void
    {
        $text->outline = $outline;
        $text->shadow = $d->bgType !== 'none' && $d->bgDepth > 0 ? null : $shadow;
        $text->glow = $glow;
        $text->fill = Color::hexToRgb($d->textColor);
        if ($d->textGradient) {
            $g = Color::GRADIENTS[$d->textGradient];
            $text->gradient = [Color::hexToRgb($g[0]), Color::hexToRgb($g[1])];
            $text->fill = Color::mix($text->gradient[0], $text->gradient[1], 0.5);
        } elseif ($d->textGloss) {
            $text->gradient = [Color::mix($text->fill, [255, 255, 255], 0.45), Color::mix($text->fill, [0, 0, 0], 0.12)];
        }
        $text->gloss = $d->textGloss;
        if ($d->textDepth > 0) {
            $base = $outline['color'] ?? $text->fill;
            $color = $d->textDepthColor ? Color::hexToRgb($d->textDepthColor) : Color::mix($base, [0, 0, 0], 0.45);
            $text->depth = ['d' => $d->textDepth, 'color' => $color];
        }
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private function bgBox(DesignSpec $d): array
    {
        $depth = $d->bgDepth;
        $m = 8.0 + $d->bgBorderWidth;
        return match ($d->bgType) {
            'pill' => [$m, 116.0, 512 - $m, 396.0 - $depth * 0.5],
            'tag' => [$m, 96.0, 512 - $m, 416.0 - $depth * 0.5],
            default => [$m, $m, 512 - $m, 512 - $m - $depth],
        };
    }

    /** @param array{0:float,1:float,2:float,3:float} $box */
    private function shapeElement(string $id, string $type, array $box, DesignSpec $d): Element
    {
        $el = new Element($id, $box);
        $el->path = Shapes::make($type, $box);
        $el->rotationInvariant = in_array($type, Shapes::ROTATION_INVARIANT, true) && $d->bgGradient === null && !$d->bgGloss;
        if ($type === 'circle' || $type === 'coin' || $type === 'seal') {
            $cx = ($box[0] + $box[2]) / 2;
            $cy = ($box[1] + $box[3]) / 2;
            $r = ($box[2] - $box[0]) / 2;
            $el->hull = [];
            for ($k = 0; $k < 24; $k++) {
                $a = 2 * M_PI * $k / 24;
                $el->hull[] = [$cx + $r * cos($a), $cy + $r * sin($a)];
            }
        }
        $this->applyBgStyle($el, $d);
        if ($type === 'coin') {
            // metallic rim: inner ring drawn as an extra contour (even-odd ring)
            $cx = ($box[0] + $box[2]) / 2;
            $cy = ($box[1] + $box[3]) / 2;
            $r = ($box[2] - $box[0]) / 2;
            $ring = Path::ellipse($cx, $cy, $r * 0.84, $r * 0.84);
            $el->extraStroke = ['path' => $ring, 'w' => max(6.0, $r * 0.035), 'color' => Color::mix($el->fill, [255, 255, 255], 0.5)];
            if (!$el->gradient) {
                $el->gradient = [Color::mix($el->fill, [255, 255, 255], 0.35), Color::mix($el->fill, [0, 0, 0], 0.25)];
            }
        }
        return $el;
    }

    private function applyBgStyle(Element $el, DesignSpec $d): void
    {
        $el->fill = Color::hexToRgb($d->bgColor);
        if ($d->bgGradient) {
            $g = Color::GRADIENTS[$d->bgGradient];
            $el->gradient = [Color::hexToRgb($g[0]), Color::hexToRgb($g[1])];
        } elseif ($d->bgGloss) {
            $el->gradient = [Color::mix($el->fill, [255, 255, 255], 0.32), Color::mix($el->fill, [0, 0, 0], 0.18)];
        }
        $el->gloss = $d->bgGloss;
        if ($d->bgBorderWidth > 0) {
            $el->outline = ['w' => $d->bgBorderWidth, 'color' => Color::hexToRgb($d->bgBorderColor)];
        }
        if ($d->bgDepth > 0) {
            $base = $d->bgBorderWidth > 0 ? Color::hexToRgb($d->bgBorderColor) : $el->fill;
            if (Color::luminance($base) > 0.85) {
                $base = $el->fill;
            }
            $el->depth = ['d' => $d->bgDepth, 'color' => $d->bgDepthColor ? Color::hexToRgb($d->bgDepthColor) : Color::mix($base, [0, 0, 0], 0.42)];
        }
        if ($d->shadow) {
            $el->shadow = ['color' => [0, 0, 0], 'opacity' => 0.35, 'dx' => 0.0, 'dy' => 8.0, 'blur' => 12.0];
        }
    }

    private function hasDecoration(DesignSpec $d): bool
    {
        foreach ($d->animation->effects as $e) {
            if ($this->effects->get($e['type'])->isDecoration()) {
                return true;
            }
        }
        return false;
    }

    /** @param array{0:float,1:float,2:float,3:float} $box */
    private function buildText(string $id, string $text, string $font, float $spacing, array $box, float $sizeFactor, Scene $scene, ?TextLayout $layout = null): ?Element
    {
        $layout ??= new TextLayout($this->fonts->shaper($font), $text, $spacing);
        if ($layout->isEmpty()) {
            return null;
        }
        foreach ($layout->missingCodepoints() as $cp) {
            $scene->warnings[] = sprintf('نویسه‌ی «%s» در فونت‌ها موجود نیست و نمایش داده نمی‌شود.', mb_chr($cp, 'UTF-8'));
        }
        if ($id === 'text') {
            $scene->rtl = $layout->isRtl();
        }
        $scale = $layout->fitScale($box[2] - $box[0], $box[3] - $box[1], $sizeFactor);
        $p = $layout->paths($scale, ($box[0] + $box[2]) / 2, ($box[1] + $box[3]) / 2);
        $el = new Element($id, $p['bounds']);
        $el->path = $p['all'];
        $el->units = $id === 'text' ? $p['units'] : [];
        return $el;
    }

    /** @param array{0:float,1:float,2:float,3:float} $box */
    private function buildLogo(DesignSpec $d, array $box, string $mode): ?Element
    {
        $key = sha1((string) $d->imagePath . '|' . (filemtime((string) $d->imagePath) ?: 0) . '|' . (int) $d->removeBg . '|' . $d->crop);
        if (!isset(self::$logoCache[$key])) {
            $im = ImageProcessor::load((string) $d->imagePath);
            $im = ImageProcessor::resizeToFit($im, 720);
            if ($d->removeBg) {
                $im = ImageProcessor::removeBackground($im);
            }
            $im = ImageProcessor::trim($im);
            $im = ImageProcessor::cropShape($im, $d->crop);
            if (count(self::$logoCache) > 8) {
                self::$logoCache = [];
            }
            self::$logoCache[$key] = $im;
        }
        $im = self::$logoCache[$key];
        $iw = imagesx($im);
        $ih = imagesy($im);
        $bw = ($box[2] - $box[0]) * $d->imageScale;
        $bh = ($box[3] - $box[1]) * $d->imageScale;
        $s = min($bw / $iw, $bh / $ih);
        $w = $iw * $s;
        $h = $ih * $s;
        $cx = ($box[0] + $box[2]) / 2 + $d->imageOffsetX * Scene::SIZE;
        $cy = ($box[1] + $box[3]) / 2 + $d->imageOffsetY * Scene::SIZE;
        $rect = [$cx - $w / 2, $cy - $h / 2, $cx + $w / 2, $cy + $h / 2];
        $el = new Element('logo', $rect);
        if ($mode === 'vector') {
            $vec = (new Vectorizer())->trace($im, $this->vectorMaxColors);
            if (!$vec['layers']) {
                return null;
            }
            $m = Matrix::translation($rect[0], $rect[1])->multiply(Matrix::scaling($w / $vec['width'], $h / $vec['height']));
            foreach ($vec['layers'] as $layer) {
                $el->layers[] = ['path' => $layer['path']->transform($m), 'color' => $layer['color']];
            }
            $el->silhouette = $vec['silhouette']->transform($m);
            $el->units = array_map(static fn ($l) => $l['path'], $el->layers);
            $el->fillRule = Rasterizer::EVENODD;
            $b = $el->silhouette->bounds();
            if ($b !== null) {
                $el->bounds = $b;
            }
            return $el;
        }
        $el->image = $im;
        $el->imageRect = $rect;
        $el->wipeReveal = true;
        return $el;
    }
}

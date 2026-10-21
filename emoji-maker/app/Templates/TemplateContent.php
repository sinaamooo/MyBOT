<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Rendering\Color;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Text\TextLayout;
use EmojiMaker\Vector\LottieBuilder;

/**
 * Builds the user's content for a template slot as Lottie items placed in the
 * slot box (host content coordinates):
 *   - text: shaped glyph outlines (Persian / English) with fill and optional outline
 *   - logo (vector): a traced logo (for TGS)
 *   - logo (raster): an "emx_img" item drawn by the PHP player (for WEBM / previews)
 * Everything is wrapped in one group named "emx_<role>".
 */
final class TemplateContent
{
    public function __construct(private FontRegistry $fonts)
    {
    }

    /**
     * @param array{0:float,1:float,2:float,3:float} $box
     * @param array{font?:string,color?:string,outline?:?string,outline_width?:float,size?:float,letter_spacing?:float} $style
     * @return array{group:array<string,mixed>,missing:list<int>,bounds:array{0:float,1:float,2:float,3:float}}
     */
    public function text(string $role, string $text, array $box, array $style, float $pad = 0.92): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new TemplateException('متن خالی است.');
        }
        $font = (string) ($style['font'] ?? '');
        if ($font === '' || !$this->fonts->has($font)) {
            $font = $this->fonts->autoSlug($text);
        }
        $layout = new TextLayout($this->fonts->shaper($font), $text, (float) ($style['letter_spacing'] ?? 0.0));
        if ($layout->isEmpty()) {
            throw new TemplateException('هیچ‌کدام از حروف این متن در فونت انتخابی وجود ندارد.');
        }
        [$x0, $y0, $x1, $y1] = $box;
        $w = $x1 - $x0;
        $h = $y1 - $y0;
        $outlineW = max(0.0, min(0.25, (float) ($style['outline_width'] ?? 0.0)));
        $outlineColor = $style['outline'] ?? null;
        $hasOutline = $outlineW > 0 && is_string($outlineColor) && Color::isHex($outlineColor);
        // leave room for the outline inside the box
        $shrink = $hasOutline ? 1.0 / (1.0 + 2 * $outlineW) : 1.0;
        $scale = $layout->fitScale($w * $pad * $shrink, $h * $pad * $shrink, max(0.3, min(1.0, (float) ($style['size'] ?? 1.0))));
        $res = $layout->paths($scale, ($x0 + $x1) / 2, ($y0 + $y1) / 2);
        /** @var Path $path */
        $path = $res['all'];
        $builder = $this->builder($box);
        $items = $builder->shape($path);
        $color = Color::hexToRgb(is_string($style['color'] ?? null) && Color::isHex($style['color']) ? $style['color'] : '#FFFFFF');
        $items[] = $builder->fill($color, 100, 1);
        if ($hasOutline) {
            $textH = $res['bounds'][3] - $res['bounds'][1];
            $items[] = $builder->stroke(Color::hexToRgb($outlineColor), max(0.5, $textH * $outlineW * 2));
        }
        return [
            'group' => self::group($role, $items),
            'missing' => $layout->missingCodepoints(),
            'bounds' => $res['bounds'],
        ];
    }

    /**
     * Traced logo layers fitted into the box.
     * @param array{width:int,height:int,layers:list<array{path:Path,color:array{0:int,1:int,2:int}}>} $trace Vectorizer::trace()
     * @param array{0:float,1:float,2:float,3:float} $box
     * @return array<string,mixed>
     */
    public function vectorLogo(string $role, array $trace, array $box, float $pad = 0.92): array
    {
        if (!$trace['layers']) {
            throw new TemplateException('در تصویر لوگو هیچ بخش غیرشفافی پیدا نشد.');
        }
        $m = self::fitMatrix((float) $trace['width'], (float) $trace['height'], $box, $pad);
        $builder = $this->builder($box);
        $groups = [];
        // tracer layers are bottom-up, Lottie lists the top-most first
        foreach (array_reverse($trace['layers']) as $k => $layer) {
            $items = $builder->shape($layer['path']->transform($m));
            if (!$items) {
                continue;
            }
            $items[] = $builder->fill($layer['color'], 100, 1);
            $items[] = self::identityTransform();
            $groups[] = ['ty' => 'gr', 'nm' => 'c' . $k, 'it' => $items];
        }
        return self::group($role, $groups);
    }

    /**
     * Raster logo item (drawn by the PHP player only).
     * @param array{0:float,1:float,2:float,3:float} $box
     * @return array<string,mixed>
     */
    public function rasterLogo(string $role, string $ref, int $imgW, int $imgH, array $box, float $pad = 0.92): array
    {
        $m = self::fitMatrix((float) $imgW, (float) $imgH, $box, $pad);
        [$ax, $ay] = $m->apply(0, 0);
        [$bx, $by] = $m->apply($imgW, $imgH);
        return self::group($role, [['ty' => 'emx_img', 'ref' => $ref, 'rect' => [$ax, $ay, $bx, $by]]]);
    }

    /** Uniform "contain" fit of a w×h content into the box, centred. */
    public static function fitMatrix(float $w, float $h, array $box, float $pad): Matrix
    {
        [$x0, $y0, $x1, $y1] = $box;
        $bw = ($x1 - $x0) * $pad;
        $bh = ($y1 - $y0) * $pad;
        $s = min($bw / max(1e-6, $w), $bh / max(1e-6, $h));
        $tx = ($x0 + $x1) / 2 - $w * $s / 2;
        $ty = ($y0 + $y1) / 2 - $h * $s / 2;
        return new Matrix($s, 0.0, 0.0, $s, $tx, $ty);
    }

    /** More decimals for small boxes (hosts that are scaled up later). */
    private function builder(array $box): LottieBuilder
    {
        $b = new LottieBuilder(0);
        $size = max(1.0, min($box[2] - $box[0], $box[3] - $box[1]));
        $b->setPrecision($size >= 160 ? 1 : ($size >= 24 ? 2 : 3));
        return $b;
    }

    /** @param list<array<string,mixed>> $items */
    private static function group(string $role, array $items): array
    {
        $items[] = self::identityTransform();
        return ['ty' => 'gr', 'nm' => 'emx_' . $role, 'it' => $items];
    }

    /** @return array<string,mixed> */
    public static function identityTransform(): array
    {
        return [
            'ty' => 'tr',
            'p' => ['a' => 0, 'k' => [0, 0]],
            'a' => ['a' => 0, 'k' => [0, 0]],
            's' => ['a' => 0, 'k' => [100, 100]],
            'r' => ['a' => 0, 'k' => 0],
            'o' => ['a' => 0, 'k' => 100],
        ];
    }
}

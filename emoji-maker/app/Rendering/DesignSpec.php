<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

use EmojiMaker\Animation\AnimationSpec;
use EmojiMaker\Geometry\Shapes;
use EmojiMaker\Text\FontRegistry;
use EmojiMaker\Text\Shaping\Unicode;

/**
 * Normalised, validated design configuration of one emoji (or one multi-tile
 * banner). Built from the JSON stored in projects.config - every user value is
 * clamped / whitelisted here, never trusted.
 */
final class DesignSpec
{
    public const OUTLINE_WIDTHS = ['none' => 0.0, 'thin' => 9.0, 'medium' => 15.0, 'thick' => 22.0];
    public const SIZES = ['s' => 0.62, 'm' => 0.8, 'l' => 0.92, 'xl' => 1.0];
    public const DEPTHS = ['none' => 0.0, 'low' => 9.0, 'medium' => 16.0, 'high' => 24.0];
    public const FORMATS = ['webm', 'tgs', 'png'];
    public const BANNER_STYLES = ['bar' => 'نوار کامل', 'lines' => 'خط بالا و پایین', 'pill' => 'کپسولی', 'none' => 'بدون زمینه'];

    public string $input = 'text';
    public string $text = '';
    public string $font = 'vazirmatn-black';
    public string $textColor = '#FFFFFF';
    public ?string $textGradient = null;
    public bool $textGloss = false;
    public float $textSize = 0.92;
    public float $letterSpacing = 0.0;
    public float $textDepth = 0.0;
    public ?string $textDepthColor = null;
    public float $outlineWidth = 15.0;
    public string $outlineColor = '#111111';
    public bool $shadow = true;
    public string $shadowColor = '#000000';
    public float $shadowOpacity = 0.45;
    public float $shadowDx = 0.0;
    public float $shadowDy = 12.0;
    public float $shadowBlur = 14.0;
    public bool $glow = false;
    public string $glowColor = '#FFE066';
    public float $glowRadius = 22.0;
    public float $glowStrength = 0.85;
    public string $textPosition = 'auto';
    public bool $persianDigits = false;

    public string $subtitle = '';
    public string $subtitleFont = 'vazirmatn-bold';
    public string $subtitleColor = '#FFFFFF';
    public float $subtitleSize = 0.16;

    public ?string $imagePath = null;   // absolute path, resolved by the caller
    public float $imageScale = 0.92;
    public float $imageOffsetX = 0.0;
    public float $imageOffsetY = 0.0;
    public bool $removeBg = false;
    public string $crop = 'none';

    public string $bgType = 'none';
    public string $bgColor = '#1E293B';
    public ?string $bgGradient = null;
    public bool $bgGloss = false;
    public float $bgBorderWidth = 0.0;
    public string $bgBorderColor = '#FFFFFF';
    public float $bgDepth = 0.0;
    public ?string $bgDepthColor = null;

    public string $layout = 'single';
    public int $bannerTiles = 0;        // 0 = automatic
    public string $bannerStyle = 'bar';

    public string $format = 'webm';
    public AnimationSpec $animation;

    public function __construct()
    {
        $this->animation = new AnimationSpec();
    }

    /** @param array<string,mixed> $c */
    public static function fromArray(array $c, ?FontRegistry $fonts = null, int $maxTextLength = 32): self
    {
        $fonts ??= FontRegistry::default();
        $d = new self();
        $d->input = in_array($c['input'] ?? '', ['image', 'text', 'combo'], true) ? $c['input'] : 'text';
        $d->text = self::cleanText((string) ($c['text'] ?? ''), $maxTextLength, 3);
        $font = (string) ($c['font'] ?? 'auto');
        $d->font = $fonts->has($font) ? $font : $fonts->autoSlug($d->text);
        $d->textColor = Color::normalizeHex((string) ($c['text_color'] ?? '#FFFFFF'));
        $grad = $c['text_gradient'] ?? null;
        $d->textGradient = is_string($grad) && isset(Color::GRADIENTS[$grad]) ? $grad : null;
        $d->textGloss = (bool) ($c['text_gloss'] ?? false);
        $size = $c['text_size'] ?? 'l';
        $d->textSize = is_string($size) && isset(self::SIZES[$size]) ? self::SIZES[$size] : (is_numeric($size) ? max(0.3, min(1.0, (float) $size)) : 0.92);
        $d->letterSpacing = is_numeric($c['letter_spacing'] ?? null) ? max(-0.08, min(0.4, (float) $c['letter_spacing'])) : 0.0;
        $d->textDepth = self::depth($c['text_depth'] ?? 'none', 20.0);
        $d->textDepthColor = isset($c['text_depth_color']) && Color::isHex((string) $c['text_depth_color']) ? Color::normalizeHex((string) $c['text_depth_color']) : null;

        $outline = (array) ($c['outline'] ?? []);
        $w = $outline['width'] ?? 'medium';
        $d->outlineWidth = is_string($w) && isset(self::OUTLINE_WIDTHS[$w]) ? self::OUTLINE_WIDTHS[$w] : (is_numeric($w) ? max(0.0, min(30.0, (float) $w)) : 15.0);
        $d->outlineColor = Color::normalizeHex((string) ($outline['color'] ?? '#111111'), '#111111');

        $shadow = (array) ($c['shadow'] ?? []);
        $d->shadow = (bool) ($shadow['enabled'] ?? true);
        $d->shadowColor = Color::normalizeHex((string) ($shadow['color'] ?? '#000000'), '#000000');
        $d->shadowOpacity = self::num($shadow['opacity'] ?? null, 0.45, 0.05, 1.0);
        $d->shadowDx = self::num($shadow['dx'] ?? null, 0.0, -30.0, 30.0);
        $d->shadowDy = self::num($shadow['dy'] ?? null, 12.0, -30.0, 30.0);
        $d->shadowBlur = self::num($shadow['blur'] ?? null, 14.0, 0.0, 40.0);

        $glow = (array) ($c['glow'] ?? []);
        $d->glow = (bool) ($glow['enabled'] ?? false);
        $d->glowColor = Color::normalizeHex((string) ($glow['color'] ?? '#FFE066'), '#FFE066');
        $d->glowRadius = self::num($glow['radius'] ?? null, 22.0, 4.0, 48.0);
        $d->glowStrength = self::num($glow['strength'] ?? null, 0.85, 0.1, 1.0);

        $d->textPosition = in_array($c['text_position'] ?? '', ['auto', 'center', 'top', 'bottom'], true) ? $c['text_position'] : 'auto';
        $d->persianDigits = (bool) ($c['persian_digits'] ?? false);

        $sub = (array) ($c['subtitle'] ?? []);
        $d->subtitle = self::cleanText((string) ($sub['text'] ?? ''), 32, 1);
        $d->subtitleFont = $fonts->has((string) ($sub['font'] ?? '')) ? (string) $sub['font'] : ($fonts->autoSlug($d->subtitle) === 'vazirmatn-black' ? 'vazirmatn-bold' : 'russo');
        $d->subtitleColor = Color::normalizeHex((string) ($sub['color'] ?? '#FFFFFF'));
        $d->subtitleSize = self::num($sub['size'] ?? null, 0.16, 0.08, 0.3);

        $img = (array) ($c['image'] ?? []);
        $d->imagePath = isset($img['path']) && is_string($img['path']) ? $img['path'] : null;
        $d->imageScale = self::num($img['scale'] ?? null, 0.92, 0.3, 1.3);
        $d->imageOffsetX = self::num($img['offset_x'] ?? null, 0.0, -0.4, 0.4);
        $d->imageOffsetY = self::num($img['offset_y'] ?? null, 0.0, -0.4, 0.4);
        $d->removeBg = (bool) ($img['remove_bg'] ?? false);
        $d->crop = in_array($img['crop'] ?? '', ['none', 'circle', 'rounded'], true) ? $img['crop'] : 'none';

        $bg = (array) ($c['background'] ?? []);
        $d->bgType = isset(Shapes::TYPES[$bg['type'] ?? '']) ? (string) $bg['type'] : 'none';
        $d->bgColor = Color::normalizeHex((string) ($bg['color'] ?? '#1E293B'), '#1E293B');
        $bgGrad = $bg['gradient'] ?? null;
        $d->bgGradient = is_string($bgGrad) && isset(Color::GRADIENTS[$bgGrad]) ? $bgGrad : null;
        $d->bgGloss = (bool) ($bg['gloss'] ?? false);
        $border = (array) ($bg['border'] ?? []);
        $bw = $border['width'] ?? 0;
        $d->bgBorderWidth = is_string($bw) && isset(self::OUTLINE_WIDTHS[$bw]) ? self::OUTLINE_WIDTHS[$bw] : self::num($bw, 0.0, 0.0, 30.0);
        $d->bgBorderColor = Color::normalizeHex((string) ($border['color'] ?? '#FFFFFF'));
        $d->bgDepth = self::depth($bg['depth'] ?? 'none', 28.0);
        $d->bgDepthColor = isset($bg['depth_color']) && Color::isHex((string) $bg['depth_color']) ? Color::normalizeHex((string) $bg['depth_color']) : null;

        $d->layout = ($c['layout'] ?? 'single') === 'banner' ? 'banner' : 'single';
        $banner = (array) ($c['banner'] ?? []);
        $tiles = (int) ($banner['tiles'] ?? 0);
        $d->bannerTiles = $tiles === 0 ? 0 : max(2, min(8, $tiles));
        $d->bannerStyle = isset(self::BANNER_STYLES[$banner['style'] ?? '']) ? (string) $banner['style'] : 'bar';
        if ($d->layout === 'banner') {
            $d->input = 'text';
            $d->text = str_replace("\n", ' ', $d->text);
        }

        $d->format = in_array($c['format'] ?? '', self::FORMATS, true) ? $c['format'] : 'webm';
        $d->animation = AnimationSpec::fromArray((array) ($c['animation'] ?? []));
        if ($d->persianDigits) {
            $d->text = Unicode::toPersianDigits($d->text);
        }
        return $d;
    }

    private static function cleanText(string $text, int $max, int $maxLines): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[\x{0000}-\x{0009}\x{000B}-\x{001F}\x{007F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text);
        $lines = array_slice(array_map('trim', explode("\n", $text)), 0, $maxLines);
        return mb_substr(trim(implode("\n", $lines)), 0, $max);
    }

    private static function num(mixed $v, float $default, float $min, float $max): float
    {
        return is_numeric($v) ? max($min, min($max, (float) $v)) : $default;
    }

    private static function depth(mixed $v, float $max): float
    {
        if (is_string($v) && isset(self::DEPTHS[$v])) {
            return min($max, self::DEPTHS[$v]);
        }
        return self::num($v, 0.0, 0.0, $max);
    }

    public function hasText(): bool
    {
        return $this->input !== 'image' && trim($this->text) !== '';
    }

    public function hasImage(): bool
    {
        return $this->input !== 'text' && $this->imagePath !== null && is_file($this->imagePath);
    }

    public function isBanner(): bool
    {
        return $this->layout === 'banner';
    }

    /** Stable hash of everything that influences the rendered output. */
    public function hash(): string
    {
        $vars = get_object_vars($this);
        $vars['animation'] = $this->animation->toArray();
        if ($this->imagePath && is_file($this->imagePath)) {
            $vars['imagePath'] = sha1_file($this->imagePath);
        }
        return sha1((string) json_encode($vars));
    }
}

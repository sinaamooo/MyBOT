<?php

declare(strict_types=1);

namespace EmojiMaker\Rendering;

final class Color
{
    /** Named palette offered in the bot (Persian labels). */
    public const PALETTE = [
        '#FFFFFF' => 'سفید',
        '#111111' => 'مشکی',
        '#22C55E' => 'سبز',
        '#EF4444' => 'قرمز',
        '#3B82F6' => 'آبی',
        '#FACC15' => 'زرد',
        '#F59E0B' => 'طلایی',
        '#A855F7' => 'بنفش',
        '#F97316' => 'نارنجی',
        '#EC4899' => 'صورتی',
        '#06B6D4' => 'فیروزه‌ای',
        '#94A3B8' => 'نقره‌ای',
    ];

    /** Gradient presets: key => [top, bottom, label] */
    public const GRADIENTS = [
        'gold' => ['#FFE680', '#D99A00', 'طلایی براق'],
        'fire' => ['#FFD000', '#FF3D00', 'آتشین'],
        'ocean' => ['#5EEAD4', '#2563EB', 'اقیانوسی'],
        'neon' => ['#F0ABFC', '#7C3AED', 'نئون'],
        'money' => ['#86EFAC', '#15803D', 'سبز پول'],
        'silver' => ['#F8FAFC', '#94A3B8', 'نقره‌ای'],
    ];

    /** @return array{0:int,1:int,2:int} */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [255, 255, 255];
        }
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public static function rgbToHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', max(0, min(255, (int) round($rgb[0]))), max(0, min(255, (int) round($rgb[1]))), max(0, min(255, (int) round($rgb[2]))));
    }

    public static function isHex(string $s): bool
    {
        return (bool) preg_match('/^#?[0-9a-fA-F]{6}$/', trim($s));
    }

    public static function normalizeHex(string $s, string $default = '#FFFFFF'): string
    {
        $s = trim($s);
        if (!self::isHex($s)) {
            return $default;
        }
        return '#' . strtoupper(ltrim($s, '#'));
    }

    /**
     * @param array{0:int,1:int,2:int} $a
     * @param array{0:int,1:int,2:int} $b
     * @return array{0:int,1:int,2:int}
     */
    public static function mix(array $a, array $b, float $t): array
    {
        return [
            (int) round($a[0] + ($b[0] - $a[0]) * $t),
            (int) round($a[1] + ($b[1] - $a[1]) * $t),
            (int) round($a[2] + ($b[2] - $a[2]) * $t),
        ];
    }

    /** Relative luminance 0..1 */
    public static function luminance(array $rgb): float
    {
        return (0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2]) / 255;
    }

    /** Readable contrasting outline colour for a fill. */
    public static function contrast(array $rgb): array
    {
        return self::luminance($rgb) > 0.6 ? [17, 17, 17] : [255, 255, 255];
    }

    /**
     * Rotates the hue with a luminance preserving matrix (same math used for
     * pixels and for Lottie colour keyframes so previews match).
     * @return array{0:float,1:float,2:float} 3x3 matrix rows flattened
     */
    public static function hueMatrix(float $degrees): array
    {
        $a = deg2rad($degrees);
        $c = cos($a);
        $s = sin($a);
        return [
            0.213 + $c * 0.787 - $s * 0.213, 0.715 - $c * 0.715 - $s * 0.715, 0.072 - $c * 0.072 + $s * 0.928,
            0.213 - $c * 0.213 + $s * 0.143, 0.715 + $c * 0.285 + $s * 0.140, 0.072 - $c * 0.072 - $s * 0.283,
            0.213 - $c * 0.213 - $s * 0.787, 0.715 - $c * 0.715 + $s * 0.715, 0.072 + $c * 0.928 + $s * 0.072,
        ];
    }

    /**
     * @param array{0:int,1:int,2:int} $rgb
     * @return array{0:int,1:int,2:int}
     */
    public static function rotateHue(array $rgb, float $degrees): array
    {
        if (abs(fmod($degrees, 360.0)) < 0.01) {
            return $rgb;
        }
        $m = self::hueMatrix($degrees);
        [$r, $g, $b] = $rgb;
        return [
            max(0, min(255, (int) round($m[0] * $r + $m[1] * $g + $m[2] * $b))),
            max(0, min(255, (int) round($m[3] * $r + $m[4] * $g + $m[5] * $b))),
            max(0, min(255, (int) round($m[6] * $r + $m[7] * $g + $m[8] * $b))),
        ];
    }

    /** Hue (0..360) of a colour, -1 for greys. */
    public static function hue(array $rgb): float
    {
        [$r, $g, $b] = [$rgb[0] / 255, $rgb[1] / 255, $rgb[2] / 255];
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $d = $max - $min;
        if ($d < 0.04) {
            return -1.0;
        }
        if ($max === $r) {
            $h = fmod(($g - $b) / $d, 6.0);
        } elseif ($max === $g) {
            $h = ($b - $r) / $d + 2;
        } else {
            $h = ($r - $g) / $d + 4;
        }
        $h *= 60;
        return $h < 0 ? $h + 360 : $h;
    }

    public static function label(string $hex): string
    {
        $hex = strtoupper($hex);
        return self::PALETTE[$hex] ?? $hex;
    }
}

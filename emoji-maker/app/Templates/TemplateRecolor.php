<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

/**
 * Recolours a template animation in the perceptual OKLCH space so shading,
 * highlights and gradients keep their relationships (only the hue family,
 * colourfulness and overall lightness move).
 *
 * Modes
 *   main  only the template's main brand colour (hues close to it) moves to the target
 *   all   every colourful element moves by the same hue / chroma / lightness shift
 *   gray  every colourful element is desaturated
 *
 * Colour sensitive artwork always keeps its default colours:
 *   - elements listed in the spec's recolor.protect (faces, characters, gold,
 *     fire, third party logos ...), whole subtrees included
 *   - neutrals (black / white / gray)
 *   - skin tones, unless the brand colour itself is a skin-like hue
 */
final class TemplateRecolor
{
    public const MODES = ['main', 'all', 'gray'];

    /** slug => [hex, Persian name] */
    public const PRESETS = [
        'red' => ['#E53935', 'قرمز'],
        'orange' => ['#FB8C00', 'نارنجی'],
        'gold' => ['#F2B705', 'طلایی'],
        'yellow' => ['#FDD835', 'زرد'],
        'green' => ['#22C55E', 'سبز'],
        'teal' => ['#14B8A6', 'فیروزه‌ای'],
        'cyan' => ['#06B6D4', 'آبی آسمانی'],
        'blue' => ['#1E5EFF', 'آبی'],
        'navy' => ['#1E3A8A', 'سرمه‌ای'],
        'purple' => ['#8B5CF6', 'بنفش'],
        'pink' => ['#EC4899', 'صورتی'],
        'black' => ['#111111', 'مشکی'],
        'white' => ['#F5F5F5', 'سفید'],
    ];

    private const NEUTRAL_CHROMA = 0.035;

    /** @var list<string> */
    private array $protect = [];

    /**
     * Main brand colour of the animation (weighted hue peak of the colourful,
     * unprotected colours).
     * @param list<string> $protect
     * @return array{L:float,C:float,h:float,hex:string}|null
     */
    public function primary(array $doc, array $protect = []): ?array
    {
        $this->protect = $protect;
        $samples = [];
        $this->walk($doc, '', function (array $rgb, float $weight) use (&$samples): array {
            $samples[] = [$rgb, $weight];
            return $rgb;
        });
        $bins = array_fill(0, 36, 0.0);
        $acc = [];
        foreach ($samples as [$rgb, $w]) {
            [$L, $C, $h] = self::toOklch($rgb);
            if ($C < 0.05) {
                continue;
            }
            $bin = ((int) floor($h / 10)) % 36;
            $bins[$bin] += $w * $C;
            $acc[$bin][] = [$L, $C, $h, $w];
        }
        if (max($bins) <= 0) {
            return null;
        }
        // smoothed peak
        $best = 0;
        $bestV = -1.0;
        for ($i = 0; $i < 36; $i++) {
            $v = $bins[$i] + 0.5 * ($bins[($i + 35) % 36] + $bins[($i + 1) % 36]);
            if ($v > $bestV) {
                $bestV = $v;
                $best = $i;
            }
        }
        $sx = $sy = $sl = $sc = $sw = 0.0;
        foreach ([($best + 35) % 36, $best, ($best + 1) % 36] as $b) {
            foreach ($acc[$b] ?? [] as [$L, $C, $h, $w]) {
                $ww = $w * $C;
                $sx += cos(deg2rad($h)) * $ww;
                $sy += sin(deg2rad($h)) * $ww;
                $sl += $L * $ww;
                $sc += $C * $ww;
                $sw += $ww;
            }
        }
        $h = fmod(rad2deg(atan2($sy, $sx)) + 360.0, 360.0);
        $L = $sl / $sw;
        $C = $sc / $sw;
        return ['L' => $L, 'C' => $C, 'h' => $h, 'hex' => self::toHex(self::fromOklch($L, $C, $h))];
    }

    /**
     * @param array{enabled:bool,primary:?string,tolerance:float,protect:list<string>} $policy spec->recolor
     */
    public function apply(array $doc, string $targetHex, string $mode, array $policy): array
    {
        if (!$policy['enabled'] || !in_array($mode, self::MODES, true)) {
            return $doc;
        }
        $target = self::hexToRgb($targetHex);
        if ($target === null && $mode !== 'gray') {
            throw new TemplateException('رنگ انتخاب‌شده نامعتبر است.');
        }
        $this->protect = $policy['protect'];
        $primary = $policy['primary'] !== null ? self::toOklch((array) self::hexToRgb($policy['primary'])) : null;
        if ($primary === null) {
            $p = $this->primary($doc, $policy['protect']);
            $primary = $p !== null ? [$p['L'], $p['C'], $p['h']] : null;
        }
        if ($primary === null && $mode === 'main') {
            return $doc; // nothing colourful to move
        }
        [$L0, $C0, $h0] = $primary ?? [0.6, 0.12, 0.0];
        [$Lt, $Ct, $ht] = $target !== null ? self::toOklch($target) : [0.6, 0.0, $h0];
        $neutralTarget = $Ct < self::NEUTRAL_CHROMA;
        $tol = (float) $policy['tolerance'];
        $primaryIsSkin = self::isSkin($L0, $C0, $h0);
        $dh = self::hueDelta($h0, $ht);
        $chromaRatio = $C0 > 1e-6 ? max(0.0, min(3.0, $Ct / $C0)) : 1.0;
        $map = function (array $rgb) use ($mode, $L0, $h0, $Lt, $dh, $chromaRatio, $neutralTarget, $tol, $primaryIsSkin): array {
            [$L, $C, $h] = self::toOklch($rgb);
            if ($C < self::NEUTRAL_CHROMA) {
                return $rgb; // black / white / gray never change
            }
            if (!$primaryIsSkin && self::isSkin($L, $C, $h)) {
                return $rgb; // skin tones keep their colour
            }
            if ($mode === 'main') {
                $d = abs(self::hueDelta($h0, $h));
                $w = $d <= $tol ? 1.0 : ($d >= $tol * 1.6 ? 0.0 : 1.0 - ($d - $tol) / (0.6 * $tol));
            } else {
                $w = min(1.0, ($C - self::NEUTRAL_CHROMA) / 0.03);
            }
            if ($w <= 0.0) {
                return $rgb;
            }
            if ($mode === 'gray') {
                $C2 = $C * (1 - $w);
                $L2 = $L;
                $h2 = $h;
            } elseif ($neutralTarget) {
                $C2 = $C * (1 - $w);
                $L2 = $L + $w * ($Lt - $L0) * 0.8;
                $h2 = $h;
            } else {
                $h2 = fmod($h + $w * $dh + 360.0, 360.0);
                $C2 = $C * (1 + $w * ($chromaRatio - 1));
                $L2 = $L + $w * ($Lt - $L0) * 0.8;
            }
            $L2 = max(0.0, min(1.0, $L2));
            return self::fromOklch($L2, $C2, $h2);
        };
        return $this->walk($doc, '', fn (array $rgb, float $weight): array => $map($rgb));
    }

    // ------------------------------------------------------------------
    // Document walk
    // ------------------------------------------------------------------

    private function protectedPath(string $path): bool
    {
        foreach ($this->protect as $p) {
            if (LottieDoc::isWithin($path, $p)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Applies $fn(rgb 0..1, weight) to every colour of the document outside
     * protected subtrees and returns the modified document.
     */
    private function walk(array $node, string $path, callable $fn): array
    {
        if ($path !== '' && $this->protectedPath($path)) {
            return $node;
        }
        $ty = $node['ty'] ?? null;
        if (is_string($ty)) {
            if ($ty === 'fl' || $ty === 'st') {
                if (isset($node['c']) && is_array($node['c'])) {
                    $node['c'] = $this->mapColorProp($node['c'], $fn);
                }
                return $node;
            }
            if ($ty === 'gf' || $ty === 'gs') {
                if (isset($node['g']['k']) && is_array($node['g']['k'])) {
                    $node['g']['k'] = $this->mapGradientProp($node['g']['k'], (int) ($node['g']['p'] ?? 0), $fn);
                }
                return $node;
            }
        } elseif (is_int($ty) && $ty === 1 && isset($node['sc']) && is_string($node['sc'])) {
            $rgb = self::hexToRgb($node['sc']);
            if ($rgb !== null) {
                $node['sc'] = self::toHex($fn($rgb, 1.0));
            }
        }
        foreach ($node as $k => $v) {
            if (!is_array($v)) {
                continue;
            }
            if ($path === '' && $k === 'assets') {
                $node[$k] = $this->walkAssets($v, $fn, $node);
                continue;
            }
            $node[$k] = $this->walk($v, $path === '' ? (string) $k : $path . '/' . $k, $fn);
        }
        return $node;
    }

    private function walkAssets(array $assets, callable $fn, array $doc): array
    {
        foreach ($assets as $i => $a) {
            if (!is_array($a)) {
                continue;
            }
            if (isset($a['layers'])) {
                $assets[$i] = $this->walk($a, 'assets/' . $i, $fn);
            } elseif (isset($a['p']) && is_string($a['p']) && !$this->imageProtected($doc, (string) ($a['id'] ?? ''))) {
                $assets[$i]['p'] = $this->recolorDataUri($a['p'], $fn);
            }
        }
        return $assets;
    }

    /** An embedded image is protected when a protected layer shows it. */
    private function imageProtected(array $doc, string $id): bool
    {
        $protected = false;
        LottieDoc::walkLayers($doc, function (array $layer, string $lp) use ($id, &$protected): void {
            if ((string) ($layer['refId'] ?? '') === $id && $this->protectedPath($lp)) {
                $protected = true;
            }
        });
        return $protected;
    }

    private function recolorDataUri(string $uri, callable $fn): string
    {
        if (!preg_match('~^data:image/(png|jpe?g|webp);base64,~', $uri, $m)) {
            return $uri;
        }
        $bin = base64_decode(substr($uri, strlen($m[0])), true);
        if ($bin === false || strlen($bin) > 8 * 1024 * 1024) {
            return $uri;
        }
        $img = @imagecreatefromstring($bin);
        if (!$img instanceof \GdImage) {
            return $uri;
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        $w = imagesx($img);
        $h = imagesy($img);
        $cache = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a === 127) {
                    continue;
                }
                $key = $c & 0xFCFCFC; // 6 bit per channel cache
                if (!isset($cache[$key])) {
                    $rgb = $fn([(($c >> 16) & 0xFF) / 255, (($c >> 8) & 0xFF) / 255, ($c & 0xFF) / 255], 0.0);
                    $cache[$key] = ((int) round($rgb[0] * 255) << 16) | ((int) round($rgb[1] * 255) << 8) | (int) round($rgb[2] * 255);
                }
                imagesetpixel($img, $x, $y, ($a << 24) | $cache[$key]);
            }
        }
        ob_start();
        imagepng($img, null, 9);
        return 'data:image/png;base64,' . base64_encode((string) ob_get_clean());
    }

    /** fl / st colour property, static or animated. */
    private function mapColorProp(array $prop, callable $fn): array
    {
        $k = $prop['k'] ?? null;
        if (!is_array($k)) {
            return $prop;
        }
        if (!empty($prop['a']) && isset($k[0]) && is_array($k[0])) {
            foreach ($k as $i => $kf) {
                foreach (['s', 'e'] as $key) {
                    if (isset($kf[$key]) && is_array($kf[$key]) && count($kf[$key]) >= 3) {
                        $k[$i][$key] = $this->mapRgbArray($kf[$key], $fn);
                    }
                }
            }
        } elseif (count($k) >= 3 && is_numeric($k[0])) {
            $k = $this->mapRgbArray($k, $fn);
        }
        $prop['k'] = $k;
        return $prop;
    }

    private function mapRgbArray(array $c, callable $fn): array
    {
        $rgb = $fn([self::unit($c[0]), self::unit($c[1]), self::unit($c[2])], 1.0);
        $c[0] = round($rgb[0], 4);
        $c[1] = round($rgb[1], 4);
        $c[2] = round($rgb[2], 4);
        return $c;
    }

    /** gf / gs gradient data: [pos, r, g, b] * count, then opacity pairs. */
    private function mapGradientProp(array $prop, int $count, callable $fn): array
    {
        $k = $prop['k'] ?? null;
        if (!is_array($k)) {
            return $prop;
        }
        $mapFlat = function (array $flat) use ($count, $fn): array {
            $n = $count > 0 ? $count : intdiv(count($flat), 4);
            for ($i = 0; $i < $n && isset($flat[$i * 4 + 3]); $i++) {
                $rgb = $fn([self::unit($flat[$i * 4 + 1]), self::unit($flat[$i * 4 + 2]), self::unit($flat[$i * 4 + 3])], 1.0 / max(1, $n));
                $flat[$i * 4 + 1] = round($rgb[0], 4);
                $flat[$i * 4 + 2] = round($rgb[1], 4);
                $flat[$i * 4 + 3] = round($rgb[2], 4);
            }
            return $flat;
        };
        if (!empty($prop['a']) && isset($k[0]) && is_array($k[0])) {
            foreach ($k as $i => $kf) {
                foreach (['s', 'e'] as $key) {
                    if (isset($kf[$key]) && is_array($kf[$key])) {
                        $k[$i][$key] = $mapFlat($kf[$key]);
                    }
                }
            }
        } elseif (isset($k[0]) && is_numeric($k[0])) {
            $k = $mapFlat($k);
        }
        $prop['k'] = $k;
        return $prop;
    }

    private static function unit(mixed $v): float
    {
        return max(0.0, min(1.0, (float) $v));
    }

    // ------------------------------------------------------------------
    // Colour science (OKLab / OKLCH, sRGB D65)
    // ------------------------------------------------------------------

    /** @param array{0:float,1:float,2:float} $rgb sRGB 0..1 @return array{0:float,1:float,2:float} L, C, h(deg) */
    public static function toOklch(array $rgb): array
    {
        $lin = array_map(static fn ($c) => $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, [$rgb[0], $rgb[1], $rgb[2]]);
        $l = 0.4122214708 * $lin[0] + 0.5363325363 * $lin[1] + 0.0514459929 * $lin[2];
        $m = 0.2119034982 * $lin[0] + 0.6806995451 * $lin[1] + 0.1073969566 * $lin[2];
        $s = 0.0883024619 * $lin[0] + 0.2817188376 * $lin[1] + 0.6299787005 * $lin[2];
        $l_ = self::cbrt($l);
        $m_ = self::cbrt($m);
        $s_ = self::cbrt($s);
        $L = 0.2104542553 * $l_ + 0.7936177850 * $m_ - 0.0040720468 * $s_;
        $a = 1.9779984951 * $l_ - 2.4285922050 * $m_ + 0.4505937099 * $s_;
        $b = 0.0259040371 * $l_ + 0.7827717662 * $m_ - 0.8086757660 * $s_;
        $C = sqrt($a * $a + $b * $b);
        $h = fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);
        return [$L, $C, $h];
    }

    /** OKLCH -> sRGB 0..1, chroma reduced until the colour fits the sRGB gamut. */
    public static function fromOklch(float $L, float $C, float $h): array
    {
        $rgb = self::oklchToLinear($L, $C, $h);
        if (!self::inGamut($rgb)) {
            $lo = 0.0;
            $hi = $C;
            for ($i = 0; $i < 18; $i++) {
                $mid = ($lo + $hi) / 2;
                if (self::inGamut(self::oklchToLinear($L, $mid, $h))) {
                    $lo = $mid;
                } else {
                    $hi = $mid;
                }
            }
            $rgb = self::oklchToLinear($L, $lo, $h);
        }
        return array_map(static function ($c) {
            $c = max(0.0, min(1.0, $c));
            return $c <= 0.0031308 ? 12.92 * $c : 1.055 * $c ** (1 / 2.4) - 0.055;
        }, $rgb);
    }

    private static function oklchToLinear(float $L, float $C, float $h): array
    {
        $a = $C * cos(deg2rad($h));
        $b = $C * sin(deg2rad($h));
        $l_ = $L + 0.3963377774 * $a + 0.2158037573 * $b;
        $m_ = $L - 0.1055613458 * $a - 0.0638541728 * $b;
        $s_ = $L - 0.0894841775 * $a - 1.2914855480 * $b;
        $l = $l_ ** 3;
        $m = $m_ ** 3;
        $s = $s_ ** 3;
        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    private static function inGamut(array $lin): bool
    {
        foreach ($lin as $c) {
            if ($c < -0.0005 || $c > 1.0005) {
                return false;
            }
        }
        return true;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }

    /** Skin tones: warm, moderately colourful, fairly light. */
    public static function isSkin(float $L, float $C, float $h): bool
    {
        return $h >= 35.0 && $h <= 80.0 && $C >= 0.03 && $C <= 0.15 && $L >= 0.55 && $L <= 0.93;
    }

    /** Signed shortest hue difference from $a to $b in degrees. */
    public static function hueDelta(float $a, float $b): float
    {
        $d = fmod($b - $a + 540.0, 360.0) - 180.0;
        return $d;
    }

    /** @return array{0:float,1:float,2:float}|null */
    public static function hexToRgb(?string $hex): ?array
    {
        if ($hex === null || !preg_match('/^#?([0-9A-Fa-f]{6})$/', trim($hex), $m)) {
            return null;
        }
        $v = hexdec($m[1]);
        return [(($v >> 16) & 0xFF) / 255, (($v >> 8) & 0xFF) / 255, ($v & 0xFF) / 255];
    }

    public static function toHex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', (int) round(self::unit($rgb[0]) * 255), (int) round(self::unit($rgb[1]) * 255), (int) round(self::unit($rgb[2]) * 255));
    }
}

<?php

final class PriceCard
{
    const SS = 1;
    const W = 1200;
    const H = 675;
    const PANEL = [180, 98, 1020, 577, 36];
    const COLORS = [
        'BTC' => ['F7931A', '7C4A03'], 'ETH' => ['627EEA', '2B3A78'], 'USDT' => ['26A17B', '0E5C43'], 'TON' => ['0098EA', '00457A'],
        'TRX' => ['EF0027', '7A0015'], 'BNB' => ['F3BA2F', '8A6512'], 'SOL' => ['9945FF', '4B1F8C'], 'XRP' => ['23292F', '000000'],
        'DOGE' => ['C2A633', '6B5A18'], 'ADA' => ['0033AD', '001B5C'], 'NOT' => ['000000', '333333'], 'SHIB' => ['FFA409', '8A5600'],
        'AVAX' => ['E84142', '7C1F20'], 'LINK' => ['2A5ADA', '152E77'], 'DOT' => ['E6007A', '7A0041'], 'MATIC' => ['8247E5', '43227A'],
        'LTC' => ['345D9D', '1B3054'], 'PEPE' => ['3D8130', '1F4318'], 'ATOM' => ['2E3148', '16182A'], 'NEAR' => ['00C08B', '006146'],
        'XLM' => ['14B6E7', '0A5F79'], 'UNI' => ['FF007A', '85003F'], 'STARS' => ['F5B300', '8A5A00'],
    ];

    private static array $fonts = [];
    private static array $capMemo = [];

    private static function asset(string $rel): string
    {
        return Config::$root . '/assets/' . $rel;
    }

    private static function dataDir(): string
    {
        $d = Config::$root . '/data/cards';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d;
    }

    public static function ready(): bool
    {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext') && is_file(self::fa());
    }

    private static function lat(int $w = 600): string
    {
        $n = [500 => 'Onest-Medium.ttf', 600 => 'Onest-SemiBold.ttf', 700 => 'Onest-Bold.ttf'][$w] ?? 'Onest-SemiBold.ttf';
        $f = self::asset('fonts/' . $n);
        return is_file($f) ? $f : self::fa();
    }

    private static function fa(): string
    {
        return self::asset('fonts/Vazirmatn-Bold.ttf');
    }

    private static function hex(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private static function col($im, string $hex, float $op = 1.0): int
    {
        [$r, $g, $b] = self::hex($hex);
        return imagecolorallocatealpha($im, $r, $g, $b, (int)round(127 * (1 - max(0.0, min(1.0, $op)))));
    }

    private static function tint(string $hex, float $pct, string $onto = 'FFFFFF'): string
    {
        [$r, $g, $b] = self::hex($hex);
        [$r2, $g2, $b2] = self::hex($onto);
        $t = max(0.0, min(1.0, $pct));
        return sprintf('%02X%02X%02X', (int)round($r2 + ($r - $r2) * $t), (int)round($g2 + ($g - $g2) * $t), (int)round($b2 + ($b - $b2) * $t));
    }

    private static function hsl(int $h, float $s, float $l): string
    {
        $h = ($h % 360) / 360;
        $s /= 100;
        $l /= 100;
        $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
        $p = 2 * $l - $q;
        $f = function ($t) use ($p, $q) {
            if ($t < 0) {
                $t += 1;
            }
            if ($t > 1) {
                $t -= 1;
            }
            if ($t < 1 / 6) {
                return $p + ($q - $p) * 6 * $t;
            }
            if ($t < 1 / 2) {
                return $q;
            }
            if ($t < 2 / 3) {
                return $p + ($q - $p) * (2 / 3 - $t) * 6;
            }
            return $p;
        };
        return sprintf('%02X%02X%02X', (int)round($f($h + 1 / 3) * 255), (int)round($f($h) * 255), (int)round($f($h - 1 / 3) * 255));
    }

    public static function coinColors(string $sym): array
    {
        $sym = strtoupper($sym);
        if (isset(self::COLORS[$sym])) {
            return self::COLORS[$sym];
        }
        $hue = crc32($sym) % 360;
        return [self::hsl($hue, 62, 48), self::hsl($hue, 66, 24)];
    }

    private static function rowSpan(float $x1, float $x2, float $y1, float $y2, float $r, int $y): array
    {
        $dy = 0.0;
        if ($y < $y1 + $r) {
            $dy = $y1 + $r - $y - 0.5;
        } elseif ($y > $y2 - $r) {
            $dy = $y - ($y2 - $r) + 0.5;
        }
        $dx = $dy > 0 ? $r - sqrt(max(0.0, $r * $r - $dy * $dy)) : 0.0;
        return [(int)round($x1 + $dx), (int)round($x2 - $dx)];
    }

    private static function rrect($im, float $x1, float $y1, float $x2, float $y2, float $r, int $col): void
    {
        $r = max(0, min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2));
        for ($y = (int)$y1; $y <= $y2; $y++) {
            [$a, $b] = self::rowSpan($x1, $x2, $y1, $y2, $r, $y);
            if ($b >= $a) {
                imageline($im, $a, $y, $b, $y, $col);
            }
        }
    }

    private static function rring($im, float $x1, float $y1, float $x2, float $y2, float $r, float $t, int $col): void
    {
        for ($y = (int)$y1; $y <= $y2; $y++) {
            [$a, $b] = self::rowSpan($x1, $x2, $y1, $y2, $r, $y);
            if ($y < $y1 + $t || $y > $y2 - $t) {
                imageline($im, $a, $y, $b, $y, $col);
                continue;
            }
            [$c, $d] = self::rowSpan($x1 + $t, $x2 - $t, $y1 + $t, $y2 - $t, max(0, $r - $t), $y);
            if ($c - 1 >= $a) {
                imageline($im, $a, $y, $c - 1, $y, $col);
            }
            if ($b >= $d + 1) {
                imageline($im, $d + 1, $y, $b, $y, $col);
            }
        }
    }

    private static function ink(string $font, float $size, string $text): array
    {
        if ($text === '') {
            return [0.0, 0.0];
        }
        $b = @imagettfbbox($size * self::SS, 0, $font, $text);
        return $b ? [$b[0] / self::SS, $b[2] / self::SS] : [0.0, 0.0];
    }

    private static function inkW(string $font, float $size, string $text): float
    {
        [$l, $r] = self::ink($font, $size, $text);
        return $r - $l;
    }

    private static function put($im, string $font, float $size, float $x, float $y, int $col, string $text, string $align = 'l'): float
    {
        if ($text === '') {
            return 0.0;
        }
        [$l, $r] = self::ink($font, $size, $text);
        $ox = $align === 'r' ? $x - $r : ($align === 'c' ? $x - ($l + $r) / 2 : $x - $l);
        imagettftext($im, $size * self::SS, 0, (int)round($ox * self::SS), (int)round($y * self::SS), $col, $font, $text);
        return $r - $l;
    }

    private static function fit(string $font, float $size, string $text, float $room, float $min): float
    {
        while ($size > $min && self::inkW($font, $size, $text) > $room) {
            $size -= 1;
        }
        return $size;
    }

    private static function capH(string $font, float $size): float
    {
        $k = $font . '|' . $size;
        if (!isset(self::$capMemo[$k])) {
            $b = @imagettfbbox($size * self::SS, 0, $font, '0');
            self::$capMemo[$k] = $b ? abs($b[7] - $b[1]) / self::SS : $size;
        }
        return self::$capMemo[$k];
    }

    private static function sayFont(string $t): string
    {
        return Persian::isRtl($t) ? self::fa() : self::lat(700);
    }

    private static function sayStr(string $t): string
    {
        return Persian::isRtl($t) ? Persian::visual($t) : $t;
    }

    private static function sayW(float $size, string $t): float
    {
        return self::inkW(self::sayFont($t), $size, self::sayStr($t));
    }

    private static function sayFit(float $size, string $t, float $room, float $min): float
    {
        while ($size > $min && self::sayW($size, $t) > $room) {
            $size -= 1;
        }
        return $size;
    }

    private static function say($im, float $size, float $x, float $y, int $col, string $t, string $align = 'l'): float
    {
        return self::put($im, self::sayFont($t), $size, $x, $y, $col, self::sayStr($t), $align);
    }

    private static function loadImg(string $file)
    {
        if ($file === '' || !is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || strlen($raw) < 32) {
            return null;
        }
        $im = @imagecreatefromstring($raw);
        if (!$im) {
            return null;
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        imagealphablending($im, true);
        imagesavealpha($im, true);
        return $im;
    }

    private static function square($src)
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $side = min($sw, $sh);
        $sq = imagecreatetruecolor($side, $side);
        imagealphablending($sq, false);
        imagesavealpha($sq, true);
        imagecopy($sq, $src, 0, 0, (int)(($sw - $side) / 2), (int)(($sh - $side) / 2), $side, $side);
        imagedestroy($src);
        return $sq;
    }

    private static function disc($src, int $d, bool $pad = false)
    {
        $t = imagecreatetruecolor($d, $d);
        imagealphablending($t, false);
        imagesavealpha($t, true);
        $clear = imagecolorallocatealpha($t, 0, 0, 0, 127);
        imagefilledrectangle($t, 0, 0, $d, $d, $clear);
        imagealphablending($t, true);
        if ($pad) {
            imagefilledellipse($t, (int)($d / 2), (int)($d / 2), $d, $d, imagecolorallocate($t, 255, 255, 255));
            $in = (int)round($d * 0.72);
            $o = (int)round(($d - $in) / 2);
            imagecopyresampled($t, $src, $o, $o, 0, 0, $in, $in, imagesx($src), imagesy($src));
        } else {
            imagecopyresampled($t, $src, 0, 0, 0, 0, $d, $d, imagesx($src), imagesy($src));
        }
        imagealphablending($t, false);
        $r = $d / 2;
        for ($y = 0; $y < $d; $y++) {
            $dy = $y + 0.5 - $r;
            $w = $r * $r - $dy * $dy;
            $half = $w > 0 ? sqrt($w) : 0;
            $a = (int)floor($r - $half);
            $b = (int)ceil($r + $half) - 1;
            if ($half <= 0) {
                imageline($t, 0, $y, $d - 1, $y, $clear);
                continue;
            }
            if ($a > 0) {
                imageline($t, 0, $y, $a - 1, $y, $clear);
            }
            if ($b < $d - 1) {
                imageline($t, $b + 1, $y, $d - 1, $y, $clear);
            }
        }
        return $t;
    }

    private static function logoShape($logo): array
    {
        $s = 24;
        $t = imagecreatetruecolor($s, $s);
        imagealphablending($t, false);
        imagesavealpha($t, true);
        imagefilledrectangle($t, 0, 0, $s, $s, imagecolorallocatealpha($t, 0, 0, 0, 127));
        imagecopyresampled($t, $logo, 0, 0, 0, 0, $s, $s, imagesx($logo), imagesy($logo));
        $in = $holes = $corner = $dark = $opaque = 0;
        $hue = array_fill(0, 24, 0.0);
        $sum = [];
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                $c = imagecolorat($t, $x, $y);
                $a = ($c >> 24) & 127;
                $dx = $x + 0.5 - $s / 2;
                $dy = $y + 0.5 - $s / 2;
                if (($dx * $dx + $dy * $dy) <= ($s / 2 - 1) ** 2) {
                    $in++;
                    if ($a > 90) {
                        $holes++;
                    }
                } elseif (($x < 2 || $x > $s - 3) && ($y < 2 || $y > $s - 3) && $a < 40) {
                    $corner++;
                }
                if ($a > 50) {
                    continue;
                }
                $opaque++;
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;
                $mx = max($r, $g, $b);
                $mn = min($r, $g, $b);
                if ($mx < 70) {
                    $dark++;
                }
                $sat = $mx > 0 ? ($mx - $mn) / $mx : 0;
                if ($sat < 0.22 || $mx < 45 || $mn > 235 || $mx == $mn) {
                    continue;
                }
                if ($mx == $r) {
                    $h = fmod(($g - $b) / ($mx - $mn), 6);
                } elseif ($mx == $g) {
                    $h = ($b - $r) / ($mx - $mn) + 2;
                } else {
                    $h = ($r - $g) / ($mx - $mn) + 4;
                }
                $bk = ((int)floor(($h < 0 ? $h + 6 : $h) * 4)) % 24;
                $wgt = $sat * ($mx / 255);
                $hue[$bk] += $wgt;
                $sum[$bk] = [($sum[$bk][0] ?? 0) + $r * $wgt, ($sum[$bk][1] ?? 0) + $g * $wgt, ($sum[$bk][2] ?? 0) + $b * $wgt];
            }
        }
        imagedestroy($t);
        arsort($hue);
        $top = (int)array_key_first($hue);
        $brand = '';
        if (($hue[$top] ?? 0) > 1.2 && isset($sum[$top])) {
            $w = $hue[$top];
            $brand = sprintf('%02X%02X%02X', (int)min(255, $sum[$top][0] / $w), (int)min(255, $sum[$top][1] / $w), (int)min(255, $sum[$top][2] / $w));
        } elseif ($opaque > 0) {
            $brand = $dark > $opaque * 0.35 ? '1C1F26' : '5B6475';
        }
        return ['pad' => $in > 0 && $holes / $in > 0.18 && $corner < 3, 'brand' => $brand];
    }

    private static function blobs(array $blobs, float $soft, float $gain, int $S = self::SS)
    {
        $gw = 300;
        $gh = 169;
        $t = imagecreatetruecolor($gw, $gh);
        $kx = self::W / $gw;
        $ky = self::H / $gh;
        $cols = array_map(fn($b) => self::hex($b[3]), $blobs);
        for ($y = 0; $y < $gh; $y++) {
            $uy = ($y + 0.5) * $ky;
            for ($x = 0; $x < $gw; $x++) {
                $ux = ($x + 0.5) * $kx;
                $r = 7.0;
                $g = 8.0;
                $b = 10.0;
                foreach ($blobs as $i => [$cx, $cy, $rad]) {
                    $e = ($rad + $soft - hypot($ux - $cx, $uy - $cy)) / (2 * $soft);
                    if ($e <= 0) {
                        continue;
                    }
                    $e = $e >= 1 ? 1.0 : $e * $e * (3 - 2 * $e);
                    $a = $e * $gain;
                    $r += ($cols[$i][0] - $r) * $a;
                    $g += ($cols[$i][1] - $g) * $a;
                    $b += ($cols[$i][2] - $b) * $a;
                }
                imagesetpixel($t, $x, $y, imagecolorallocate($t, (int)$r, (int)$g, (int)$b));
            }
        }
        $out = function_exists('imagescale') ? imagescale($t, self::W * $S, self::H * $S, IMG_BILINEAR_FIXED) : false;
        if (!$out) {
            $out = imagecreatetruecolor(self::W * $S, self::H * $S);
            imagecopyresampled($out, $t, 0, 0, 0, 0, self::W * $S, self::H * $S, $gw, $gh);
        }
        imagedestroy($t);
        return $out;
    }

    private static function base(string $a, string $b)
    {
        $f = self::dataDir() . '/base1_' . md5($a . '|' . $b) . '.png';
        if (is_file($f) && ($im = @imagecreatefrompng($f)) && imagesx($im) === self::W) {
            imagealphablending($im, true);
            return $im;
        }
        $S = 2;
        [$x1, $y1, $x2, $y2, $rad] = self::PANEL;
        $bl = [[292, 202, 182, $a], [967, 520, 182, $b]];
        $big = self::blobs($bl, 7, 1.0, $S);
        $soft = self::blobs($bl, 95, 0.9, $S);
        imagealphablending($big, true);
        for ($y = $y1 * $S; $y <= $y2 * $S; $y++) {
            [$p, $q] = self::rowSpan($x1 * $S, $x2 * $S, $y1 * $S, $y2 * $S, $rad * $S, $y);
            if ($q >= $p) {
                imagecopy($big, $soft, $p, $y, $p, $y, $q - $p + 1, 1);
            }
        }
        imagedestroy($soft);
        self::rrect($big, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, $rad * $S, self::col($big, '141518', 0.52));
        self::rring($big, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, $rad * $S, 3, self::col($big, 'FFFFFF', 0.14));
        $im = imagecreatetruecolor(self::W, self::H);
        imagecopyresampled($im, $big, 0, 0, 0, 0, self::W, self::H, self::W * $S, self::H * $S);
        imagedestroy($big);
        imagealphablending($im, true);
        $tmp = $f . '.' . bin2hex(random_bytes(4));
        if (@imagepng($im, $tmp, 1)) {
            @rename($tmp, $f);
        } else {
            @unlink($tmp);
        }
        return $im;
    }

    public static function cached(string $key, callable $fn, int $ttl = 600): ?string
    {
        $f = self::dataDir() . '/r_' . md5($key) . '.jpg';
        if (is_file($f) && time() - (int)@filemtime($f) < $ttl) {
            $b = @file_get_contents($f);
            if (is_string($b) && strlen($b) > 500) {
                return $b;
            }
        }
        $b = $fn();
        if (is_string($b) && strlen($b) > 500) {
            $tmp = $f . '.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, $b) !== false) {
                @rename($tmp, $f);
            }
        }
        return $b;
    }

    public static function prune(int $age = 900): void
    {
        foreach ((array)glob(self::dataDir() . '/r_*.jpg') as $f) {
            if (time() - (int)@filemtime($f) > $age) {
                @unlink($f);
            }
        }
    }

    public static function warm(array $pairs): int
    {
        $n = 0;
        foreach ($pairs as [$a, $b]) {
            if (!is_file(self::dataDir() . '/base1_' . md5($a . '|' . $b) . '.png')) {
                imagedestroy(self::base($a, $b));
                $n++;
            }
        }
        return $n;
    }

    public static function basePairs(array $syms): array
    {
        $out = [['12C488', '2F72DF'], ['E5486A', '2F72DF']];
        foreach ($syms as $sym) {
            $logo = self::loadImg(self::asset('coins/' . strtolower($sym) . '.png'));
            $brand = $logo ? (self::logoShape($logo)['brand'] ?: self::coinColors($sym)[0]) : self::coinColors($sym)[0];
            if ($logo) {
                imagedestroy($logo);
            }
            $out[] = [self::glowHex($brand), '12C488'];
            $out[] = [self::glowHex($brand), 'E5486A'];
        }
        return $out;
    }

    private static function tag($im, float $x, float $yc, string $text, string $tone = 'gray', string $align = 'r'): array
    {
        $S = self::SS;
        [$ink, $line, $fill] = [
            'green' => ['9CF0CF', '2E9F78', '10352A'],
            'red' => ['FFB3C1', 'B8465F', '3A1720'],
            'blue' => ['B9D3FF', '3F6FC7', '14254A'],
            'gold' => ['FFE3A1', 'B8913A', '3A2E12'],
        ][$tone] ?? ['F2F2F4', '5A5B60', '1E1F22'];
        $isFa = Persian::isRtl($text);
        $font = $isFa ? self::fa() : self::lat(600);
        $str = $isFa ? Persian::visual($text) : $text;
        $h = 34;
        $size = $isFa ? 15 : 14.5;
        $w = self::inkW($font, $size, $str) + 36;
        $a = $align === 'r' ? $x - $w : $x;
        self::rrect($im, $a * $S, ($yc - $h / 2) * $S, ($a + $w) * $S, ($yc + $h / 2) * $S, ($h / 2) * $S, self::col($im, $line));
        self::rrect($im, ($a + 1.5) * $S, ($yc - $h / 2 + 1.5) * $S, ($a + $w - 1.5) * $S, ($yc + $h / 2 - 1.5) * $S, ($h / 2 - 1.5) * $S, self::col($im, $fill));
        self::put($im, $font, $size, $a + $w / 2, $yc + self::capH($font, $size) / 2, self::col($im, $ink), $str, 'c');
        return [$a, $a + $w];
    }

    private static function spaced($im, string $font, float $size, float $cx, float $y, int $col, string $text, float $track): void
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $ws = [];
        foreach ($chars as $c) {
            $ws[] = $c === ' ' ? $size * 0.32 : self::inkW($font, $size, $c);
        }
        $x = $cx - (array_sum($ws) + $track * (count($chars) - 1)) / 2;
        foreach ($chars as $i => $c) {
            if ($c !== ' ') {
                self::put($im, $font, $size, $x, $y, $col, $c);
            }
            $x += $ws[$i] + $track;
        }
    }

    private static function foot($im, string $bot): void
    {
        $tail = 'LIVE MARKET · ' . gmdate('Y-m-d H:i', time() + 12600) . ' TEHRAN';
        $txt = mb_strtoupper(($bot !== '' ? '@' . $bot . ' · ' : '') . $tail, 'UTF-8');
        self::spaced($im, self::lat(600), 13.5, 600, 636, self::col($im, '8C8C92'), $txt, 2.6);
    }

    private static function glowHex(string $hex): string
    {
        [$r, $g, $b] = self::hex($hex);
        $l = (max($r, $g, $b) + min($r, $g, $b)) / 510;
        if ($l < 0.42) {
            return self::tint($hex, 0.62, 'FFFFFF');
        }
        if ($l > 0.78) {
            return self::tint($hex, 0.75, '000000');
        }
        return strtoupper(ltrim($hex, '#'));
    }

    private static function like(float $x, string $ref): string
    {
        $ref = str_replace(',', '', $ref);
        $p = strpos($ref, '.');
        return number_format($x, $p === false ? 0 : strlen($ref) - $p - 1);
    }

    private static function signed(string $s, bool $neg): string
    {
        return ($neg ? '−' : '+') . ltrim($s, '-');
    }

    public static function valueStr(float $v, string $unit): string
    {
        if (in_array($unit, ['تومان', 'ریال'], true)) {
            return number_format(round($v));
        }
        $a = abs($v);
        if ($a == 0.0) {
            return '0';
        }
        if ($a >= 1000) {
            return number_format($v, $v == floor($v) ? 0 : 2);
        }
        if ($a >= 1) {
            return rtrim(rtrim(number_format($v, 3), '0'), '.');
        }
        if ($a >= 0.0001) {
            return rtrim(rtrim(number_format($v, 6), '0'), '.');
        }
        return rtrim(rtrim(number_format($v, 8), '0'), '.');
    }

    private static function star($im, float $cx, float $cy, float $r, int $col): void
    {
        $S = self::SS;
        $pts = [];
        for ($i = 0; $i < 10; $i++) {
            $rad = $i % 2 ? $r * 0.45 : $r;
            $a = -M_PI / 2 + $i * M_PI / 5;
            $pts[] = (int)round(($cx + cos($a) * $rad) * $S);
            $pts[] = (int)round(($cy + sin($a) * $rad) * $S);
        }
        imagefilledpolygon($im, $pts, $col);
    }

    private static function render(array $o): string
    {
        $S = self::SS;
        $up = (float)($o['chg'] ?? 0) >= 0;
        $im = self::base((string)$o['a'], (string)$o['b']);
        $white = self::col($im, 'F5F5F7');
        $gray = self::col($im, '9A9AA0');
        $lat = self::lat(600);
        $rtl = !empty($o['rtl']);
        $disc = $o['disc'] ?? null;
        $title = (string)($o['title'] ?? '');
        if ($rtl) {
            [, $e] = self::tag($im, 232, 172, 'LIVE', 'green', 'l');
            [, $e] = self::tag($im, $e + 10, 172, (string)$o['tag'], 'gray', 'l');
            $tx = $disc ? 896 : 966;
            self::say($im, self::sayFit(31, $title, $tx - $e - 30, 16), $tx, 184, $white, $title, 'r');
            $dx = 938;
        } else {
            [$e] = self::tag($im, 968, 172, (string)$o['tag'], 'gray', 'r');
            [$e] = self::tag($im, $e - 10, 172, 'LIVE', 'green', 'r');
            $tx = $disc ? 304 : 232;
            self::say($im, self::sayFit(31, $title, $e - 30 - $tx, 16), $tx, 184, $white, $title, 'l');
            $dx = 262;
        }
        if ($disc instanceof GdImage) {
            imagefilledellipse($im, $dx * $S, 172 * $S, 54 * $S, 54 * $S, self::col($im, 'FFFFFF', 0.16));
            imagecopy($im, $disc, ($dx - 24) * $S, (172 - 24) * $S, 0, 0, 48 * $S, 48 * $S);
            imagedestroy($disc);
        } elseif (is_array($disc)) {
            imagefilledellipse($im, $dx * $S, 172 * $S, 48 * $S, 48 * $S, self::col($im, (string)$disc[0]));
            if (($disc[1] ?? '') === '*') {
                self::star($im, $dx, 173, 16, $white);
            } else {
                $ini = mb_substr((string)$disc[1], 0, 3);
                $f = self::lat(700);
                self::put($im, $f, self::fit($f, 14, $ini, 36, 8), $dx, 172 + self::capH($f, 14) / 2, $white, $ini, 'c');
            }
        }
        self::say($im, 18, 600, 256, $gray, (string)($o['label'] ?? 'قیمت لحظه‌ای'), 'c');
        $val = (string)$o['value'];
        $pre = (string)($o['prefix'] ?? '');
        $unit = (string)($o['unit'] ?? '');
        $size = self::fit($lat, 58, $pre . $val, 520 - ($unit !== '' ? self::sayW(22, $unit) + 18 : 0), 28);
        $base = 350 - (58 - $size) * 0.3;
        $pw = $pre !== '' ? self::inkW($lat, $size, $pre) + 4 : 0;
        $w = self::inkW($lat, $size, $val);
        $us = max(15, 22 * $size / 58);
        $uw = $unit !== '' ? self::sayW($us, $unit) + 16 : 0;
        $x0 = 600 - ($uw + $pw + $w) / 2;
        if ($unit !== '') {
            self::say($im, $us, $x0, $base, $gray, $unit, 'l');
        }
        if ($pre !== '') {
            self::put($im, $lat, $size, $x0 + $uw, $base, $gray, $pre);
        }
        self::put($im, $lat, $size, $x0 + $uw + $pw, $base, $white, $val);
        imagefilledrectangle($im, 232 * $S, 412 * $S, 968 * $S, 412 * $S + 1, self::col($im, 'FFFFFF', 0.08));
        $tone = $up ? '86EBC9' : 'F7A1B4';
        $pct = self::signed(number_format(abs((float)$o['chg']), 2), !$up) . '%';
        foreach ([[844, '۲۴ ساعت پیش', (string)$o['prev'], 'E4E6EB'], [600, 'تغییر', (string)$o['diff'], $tone], [356, 'درصد تغییر', $pct, $tone]] as [$cx, $lab, $v, $hex]) {
            self::say($im, 15.5, $cx, 462, $gray, $lab, 'c');
            self::put($im, $lat, self::fit($lat, 22, $v, 220, 12), $cx, 510, self::col($im, $hex), $v, 'c');
        }
        self::foot($im, (string)($o['bot'] ?? ''));
        ob_start();
        imagejpeg($im, null, 84);
        $bytes = (string)ob_get_clean();
        imagedestroy($im);
        return $bytes;
    }

    private static function flagDisc(string $file)
    {
        $src = self::loadImg($file);
        return $src ? self::disc(self::square($src), 48 * self::SS) : null;
    }

    public static function fiat(array $o): ?string
    {
        if (!self::ready()) {
            return null;
        }
        $v = (float)($o['value'] ?? 0);
        $chg = (float)($o['chg'] ?? 0);
        $unit = (string)($o['unit'] ?? 'تومان');
        $prev = $chg > -100 ? $v / (1 + $chg / 100) : $v;
        $vs = self::valueStr($v, $unit);
        $badge = (string)($o['badge'] ?? '');
        return self::render([
            'a' => $chg >= 0 ? '12C488' : 'E5486A', 'b' => '2F72DF', 'rtl' => true,
            'disc' => $badge !== '' ? self::flagDisc(self::asset($badge)) : null,
            'title' => (string)($o['name'] ?? ''), 'tag' => (string)($o['tag'] ?? 'IRT'),
            'value' => $vs, 'unit' => $unit, 'chg' => $chg, 'label' => (string)($o['label'] ?? 'قیمت لحظه‌ای'),
            'prev' => self::like($prev, $vs), 'diff' => self::signed(self::like(abs($v - $prev), $vs), $v < $prev),
            'bot' => (string)($o['bot'] ?? ''),
        ]);
    }

    public static function crypto(array $o): ?string
    {
        if (!self::ready()) {
            return null;
        }
        $sym = strtoupper((string)($o['sym'] ?? ''));
        $v = (float)($o['value'] ?? 0);
        $chg = (float)($o['chg'] ?? 0);
        $unit = (string)($o['unit'] ?? '');
        $pre = (string)($o['prefix'] ?? '');
        $prev = $chg > -100 ? $v / (1 + $chg / 100) : $v;
        $logo = $sym === 'STARS' ? null : self::loadImg(self::asset('coins/' . strtolower(preg_replace('/[^A-Za-z0-9]/', '', $sym)) . '.png'));
        $shape = $logo ? self::logoShape($logo) : ['pad' => false, 'brand' => ''];
        $brand = $shape['brand'] !== '' ? $shape['brand'] : self::coinColors($sym)[0];
        $disc = [$brand, $sym === 'STARS' ? '*' : $sym];
        if ($logo) {
            $disc = self::disc($logo, 48 * self::SS, $shape['pad']);
            imagedestroy($logo);
        }
        $vs = self::valueStr($v, $unit);
        return self::render([
            'a' => self::glowHex($brand), 'b' => $chg >= 0 ? '12C488' : 'E5486A', 'rtl' => false, 'disc' => $disc,
            'title' => (string)($o['title'] ?? $sym), 'tag' => (string)($o['pill'] ?? '24H'),
            'label' => (string)($o['label'] ?? 'قیمت لحظه‌ای'),
            'value' => $vs, 'prefix' => $pre, 'unit' => $unit, 'chg' => $chg,
            'prev' => $pre . self::like($prev, $vs),
            'diff' => self::signed($pre . self::like(abs($v - $prev), $vs), $v < $prev),
            'bot' => (string)($o['bot'] ?? ''),
        ]);
    }

    private static function blobsAt(int $w, int $h, array $blobs, float $soft, float $gain)
    {
        $gw = 300;
        $gh = max(60, (int)round($gw * $h / $w));
        $t = imagecreatetruecolor($gw, $gh);
        $kx = $w / $gw;
        $ky = $h / $gh;
        $cols = array_map(fn($b) => self::hex($b[3]), $blobs);
        for ($y = 0; $y < $gh; $y++) {
            $uy = ($y + 0.5) * $ky;
            for ($x = 0; $x < $gw; $x++) {
                $ux = ($x + 0.5) * $kx;
                $r = 7.0;
                $g = 8.0;
                $b = 10.0;
                foreach ($blobs as $i => [$cx, $cy, $rad]) {
                    $e = ($rad + $soft - hypot($ux - $cx, $uy - $cy)) / (2 * $soft);
                    if ($e <= 0) {
                        continue;
                    }
                    $e = $e >= 1 ? 1.0 : $e * $e * (3 - 2 * $e);
                    $a = $e * $gain;
                    $r += ($cols[$i][0] - $r) * $a;
                    $g += ($cols[$i][1] - $g) * $a;
                    $b += ($cols[$i][2] - $b) * $a;
                }
                imagesetpixel($t, $x, $y, imagecolorallocate($t, (int)$r, (int)$g, (int)$b));
            }
        }
        $S = self::SS;
        $out = imagecreatetruecolor($w * $S, $h * $S);
        imagecopyresampled($out, $t, 0, 0, 0, 0, $w * $S, $h * $S, $gw, $gh);
        imagedestroy($t);
        return $out;
    }

    private static function glassPanel($im, array $panel, int $w, int $h, array $blobs): void
    {
        $S = self::SS;
        [$x1, $y1, $x2, $y2, $rad] = $panel;
        $soft = self::blobsAt($w, $h, $blobs, 95, 0.9);
        for ($y = $y1 * $S; $y <= $y2 * $S; $y++) {
            [$p, $q] = self::rowSpan($x1 * $S, $x2 * $S, $y1 * $S, $y2 * $S, $rad * $S, $y);
            if ($q >= $p) {
                imagecopy($im, $soft, $p, $y, $p, $y, $q - $p + 1, 1);
            }
        }
        imagedestroy($soft);
        self::rrect($im, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, $rad * $S, self::col($im, '141518', 0.52));
        self::rring($im, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, $rad * $S, 3, self::col($im, 'FFFFFF', 0.14));
    }

    private static function gradDisc($im, float $cx, float $cy, float $d, array $center, array $edge): void
    {
        $S = self::SS;
        $steps = (int)max(8, $d * $S / 2);
        for ($i = 0; $i < $steps; $i++) {
            $t = $i / max(1, $steps - 1);
            $c = sprintf('%02X%02X%02X', (int)($edge[0] + ($center[0] - $edge[0]) * $t), (int)($edge[1] + ($center[1] - $edge[1]) * $t), (int)($edge[2] + ($center[2] - $edge[2]) * $t));
            $dd = (int)round($d * $S * (1 - $t * 0.85));
            imagefilledellipse($im, (int)round($cx * $S), (int)round($cy * $S), $dd, $dd, self::col($im, $c));
        }
    }

    public static function clean(string $s): string
    {
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($s, Normalizer::FORM_KC);
            if (is_string($n)) {
                $s = $n;
            }
        }
        $small = ['ᴀ' => 'a', 'ʙ' => 'b', 'ᴄ' => 'c', 'ᴅ' => 'd', 'ᴇ' => 'e', 'ғ' => 'f', 'ɢ' => 'g', 'ʜ' => 'h', 'ɪ' => 'i', 'ᴊ' => 'j', 'ᴋ' => 'k', 'ʟ' => 'l', 'ᴍ' => 'm',
            'ɴ' => 'n', 'ᴏ' => 'o', 'ᴘ' => 'p', 'ǫ' => 'q', 'ʀ' => 'r', 'ꜱ' => 's', 'ᴛ' => 't', 'ᴜ' => 'u', 'ᴠ' => 'v', 'ᴡ' => 'w', 'ʏ' => 'y', 'ᴢ' => 'z'];
        $out = '';
        foreach (mb_str_split($s) as $ch) {
            $cp = mb_ord($ch);
            if ($cp >= 0x1D400 && $cp <= 0x1D6A3) {
                $i = ($cp - 0x1D400) % 52;
                $ch = chr($i < 26 ? 65 + $i : 97 + $i - 26);
            } elseif ($cp >= 0x1D7CE && $cp <= 0x1D7FF) {
                $ch = chr(48 + ($cp - 0x1D7CE) % 10);
            } elseif ($cp >= 0xFF01 && $cp <= 0xFF5E) {
                $ch = chr($cp - 0xFEE0);
            } elseif (isset($small[$ch])) {
                $ch = $small[$ch];
            }
            $out .= $ch;
        }
        $out = preg_replace('/[^\x{0020}-\x{007E}\x{00B7}\x{00C0}-\x{024F}\x{0400}-\x{04FF}\x{2010}-\x{2027}\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}\x{200C}]/u', '', $out);
        return trim(preg_replace('/\s+/u', ' ', (string)$out));
    }

    public static function gift(string $owner, ?string $username, array $tiles, float $totalTon, ?float $totalUsd, ?float $totalToman, ?float $plTon, ?float $plPct, int $count, string $bot): ?string
    {
        if (!self::ready()) {
            return null;
        }
        $S = self::SS;
        $W = 1200;
        $owner = self::clean($owner) ?: ($username ? '@' . $username : 'Telegram User');
        $shown = array_slice($tiles, 0, 12);
        foreach ($shown as &$st) {
            $st['title'] = self::clean((string)$st['title']) ?: 'Gift';
            $st['subtitle'] = self::clean((string)($st['subtitle'] ?? ''));
        }
        unset($st);
        $rows = max(1, (int)ceil(count($shown) / 2));
        $tileH = 112;
        $gap = 16;
        $top = 456;
        $H = $top + $rows * ($tileH + $gap) + 120;
        $up = ($plTon ?? 0) >= 0;
        $blobs = [[250, 210, 200, '7C3AED'], [$W - 220, $H - 230, 220, $up ? '12C488' : 'E5486A'], [$W - 260, 260, 150, '2F72DF']];
        $bf = self::dataDir() . '/gbase_' . md5(json_encode($blobs) . "|$W|$H|$S") . '.png';
        $im = is_file($bf) ? @imagecreatefrompng($bf) : null;
        if (!$im) {
            $im = imagecreatetruecolor($W * $S, $H * $S);
            imagefilledrectangle($im, 0, 0, $W * $S, $H * $S, imagecolorallocate($im, 7, 8, 10));
            foreach ($blobs as [$bx, $by, $br, $bc]) {
                imagefilledellipse($im, $bx * $S, $by * $S, $br * 2 * $S, $br * 2 * $S, self::col($im, $bc));
            }
            imagealphablending($im, true);
            self::glassPanel($im, [60, 60, $W - 60, $H - 90, 36], $W, $H, $blobs);
            $tmp = $bf . '.' . bin2hex(random_bytes(4));
            if (@imagepng($im, $tmp, 1)) {
                @rename($tmp, $bf);
            } else {
                @unlink($tmp);
            }
        }
        imagealphablending($im, true);
        $white = self::col($im, 'F5F5F7');
        $gray = self::col($im, '9A9AA0');
        $gold = self::col($im, 'FFD66B');
        [, $e] = self::tag($im, 104, 128, 'GIFT PORTFOLIO', 'gold', 'l');
        self::tag($im, $e + 10, 128, number_format($count) . ' GIFTS', 'gray', 'l');
        self::say($im, self::sayFit(32, $owner, 560, 16), $W - 104, 142, $white, $owner, 'r');
        if ($username) {
            self::put($im, self::lat(500), 17, $W - 104, 178, $gray, '@' . $username, 'r');
        }
        self::say($im, 18, 600, 232, $gray, 'ارزش کل پرتفوی', 'c');
        $val = self::valueStr($totalTon, '');
        $lat = self::lat(700);
        $size = self::fit($lat, 58, $val . ' TON', 760, 28);
        $w1 = self::inkW($lat, $size, $val);
        $w2 = self::inkW(self::lat(600), $size * 0.5, 'TON');
        $x0 = 600 - ($w1 + 14 + $w2) / 2;
        self::put($im, $lat, $size, $x0, 306, $white, $val);
        self::put($im, self::lat(600), $size * 0.5, $x0 + $w1 + 14, 306, $gray, 'TON');
        $sub = trim(($totalUsd ? '$' . number_format($totalUsd, $totalUsd >= 100 ? 0 : 2) : '') . ($totalToman ? '  ·  ' . number_format(round($totalToman)) . ' IRT' : ''), ' ·');
        if ($sub !== '') {
            self::put($im, self::lat(500), 19, 600, 346, $gray, $sub, 'c');
        }
        if ($plTon !== null) {
            $txt = ($up ? '+' : '−') . self::valueStr(abs($plTon), '') . ' TON' . ($plPct !== null ? '  (' . ($up ? '+' : '−') . number_format(abs($plPct), 2) . '%)' : '') . '  ·  7D';
            $tw = self::inkW(self::lat(600), 14.5, $txt) + 36;
            self::tag($im, 600 - $tw / 2, 392, $txt, $up ? 'green' : 'red', 'l');
        }
        imagefilledrectangle($im, 104 * $S, 424 * $S, ($W - 104) * $S, 424 * $S + 1, self::col($im, 'FFFFFF', 0.08));
        $tw = (int)(($W - 208 - $gap) / 2);
        foreach ($shown as $i => $t) {
            $r = intdiv($i, 2);
            $c = $i % 2;
            $x2 = $W - 104 - $c * ($tw + $gap);
            $x1 = $x2 - $tw;
            $y1 = $top + $r * ($tileH + $gap);
            $y2 = $y1 + $tileH;
            self::rrect($im, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, 24 * $S, self::col($im, 'FFFFFF', 0.05));
            self::rring($im, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, 24 * $S, 2, self::col($im, 'FFFFFF', 0.10));
            $d = 80;
            $cx = $x2 - 18 - $d / 2;
            $cy = $y1 + $tileH / 2;
            self::gradDisc($im, $cx, $cy, $d, $t['center'] ?? [90, 110, 200], $t['edge'] ?? [30, 34, 60]);
            if (!empty($t['image']) && ($src = @imagecreatefromstring($t['image']))) {
                $s = ($d - 8) * $S;
                $k = min($s / imagesx($src), $s / imagesy($src));
                $iw = (int)(imagesx($src) * $k);
                $ih = (int)(imagesy($src) * $k);
                imagecopyresampled($im, $src, (int)($cx * $S - $iw / 2), (int)($cy * $S - $ih / 2), 0, 0, $iw, $ih, imagesx($src), imagesy($src));
                imagedestroy($src);
            }
            $v = !empty($t['value']) ? self::valueStr((float)$t['value'], '') . ' TON' : '—';
            $vs = self::fit(self::lat(700), 20, $v, 170, 11);
            self::put($im, self::lat(700), $vs, $x1 + 22, $cy + self::capH(self::lat(700), $vs) / 2, $gold, $v);
            $room = ($cx - $d / 2 - 14) - ($x1 + 22 + self::inkW(self::lat(700), $vs, $v) + 18);
            $title = (string)$t['title'];
            self::say($im, self::sayFit(20, $title, $room, 11), $cx - $d / 2 - 14, $y1 + 48, $white, $title, 'r');
            $subt = (string)($t['subtitle'] ?? '');
            if ($subt !== '') {
                $f = Persian::isRtl($subt) ? self::fa() : self::lat(500);
                $str = Persian::isRtl($subt) ? Persian::visual($subt) : $subt;
                self::put($im, $f, self::fit($f, 14.5, $str, $room, 9), $cx - $d / 2 - 14, $y1 + 80, $gray, $str, 'r');
            }
        }
        $more = count($tiles) - count($shown);
        $tail = ($more > 0 ? '+' . $more . ' MORE · ' : '') . gmdate('Y-m-d H:i', time() + 12600) . ' TEHRAN';
        $txt = mb_strtoupper(($bot !== '' ? '@' . $bot . ' · ' : '') . $tail, 'UTF-8');
        self::spaced($im, self::lat(600), 13.5, 600, $H - 44, self::col($im, '8C8C92'), $txt, 2.6);
        $out = imagecreatetruecolor($W, $H);
        imagecopyresampled($out, $im, 0, 0, 0, 0, $W, $H, imagesx($im), imagesy($im));
        imagedestroy($im);
        ob_start();
        imagejpeg($out, null, 84);
        $bytes = (string)ob_get_clean();
        imagedestroy($out);
        return $bytes;
    }
}

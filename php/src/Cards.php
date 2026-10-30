<?php

final class Persian
{
    const FORMS = [
        0x0621 => [0xFE80], 0x0622 => [0xFE81, 0xFE82], 0x0623 => [0xFE83, 0xFE84], 0x0624 => [0xFE85, 0xFE86],
        0x0625 => [0xFE87, 0xFE88], 0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], 0x0627 => [0xFE8D, 0xFE8E],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], 0x0629 => [0xFE93, 0xFE94], 0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C], 0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], 0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], 0x062F => [0xFEA9, 0xFEAA], 0x0630 => [0xFEAB, 0xFEAC], 0x0631 => [0xFEAD, 0xFEAE],
        0x0632 => [0xFEAF, 0xFEB0], 0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], 0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC], 0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], 0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8], 0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], 0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], 0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], 0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0], 0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], 0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], 0x0648 => [0xFEED, 0xFEEE], 0x0649 => [0xFEEF, 0xFEF0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4], 0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], 0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0698 => [0xFB8A, 0xFB8B], 0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], 0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], 0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
    ];
    const LAM_ALEF = [0x0622 => [0xFEF5, 0xFEF6], 0x0623 => [0xFEF7, 0xFEF8], 0x0625 => [0xFEF9, 0xFEFA], 0x0627 => [0xFEFB, 0xFEFC]];
    const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '<' => '>', '>' => '<', '«' => '»', '»' => '«'];

    private static function isMark(int $c): bool
    {
        return ($c >= 0x064B && $c <= 0x065F) || $c === 0x0670;
    }

    private static function dual(int $c): bool
    {
        return isset(self::FORMS[$c]) && count(self::FORMS[$c]) === 4;
    }

    public static function shape(string $text): string
    {
        $cps = array_map('mb_ord', mb_str_split($text));
        $n = count($cps);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $cps[$i];
            if ($c === 0x200C) {
                continue;
            }
            if (!isset(self::FORMS[$c])) {
                $out[] = $c;
                continue;
            }
            $p = $i - 1;
            while ($p >= 0 && self::isMark($cps[$p])) {
                $p--;
            }
            $prevJoins = $p >= 0 && self::dual($cps[$p]);
            $q = $i + 1;
            while ($q < $n && self::isMark($cps[$q])) {
                $q++;
            }
            $next = $q < $n ? $cps[$q] : null;
            if ($c === 0x0644 && $next !== null && isset(self::LAM_ALEF[$next])) {
                $out[] = self::LAM_ALEF[$next][$prevJoins ? 1 : 0];
                $i = $q;
                continue;
            }
            $nextJoins = $next !== null && isset(self::FORMS[$next]) && $next !== 0x0621;
            $f = self::FORMS[$c];
            if (count($f) === 1) {
                $out[] = $f[0];
            } elseif (count($f) === 2) {
                $out[] = $prevJoins ? $f[1] : $f[0];
            } elseif ($prevJoins && $nextJoins) {
                $out[] = $f[3];
            } elseif ($prevJoins) {
                $out[] = $f[1];
            } elseif ($nextJoins) {
                $out[] = $f[2];
            } else {
                $out[] = $f[0];
            }
        }
        return implode('', array_map('mb_chr', $out));
    }

    public static function isRtl(string $t): bool
    {
        return (bool)preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $t);
    }

    private static function type(string $ch): string
    {
        if (preg_match('/[\x{0600}-\x{065F}\x{066A}-\x{06EF}\x{06FA}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $ch)) {
            return 'R';
        }
        if (preg_match('/[A-Za-z0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $ch)) {
            return 'L';
        }
        return 'N';
    }

    public static function visual(string $text): string
    {
        if (!self::isRtl($text)) {
            return $text;
        }
        $chars = mb_str_split(self::shape($text));
        $types = array_map([self::class, 'type'], $chars);
        $n = count($chars);
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] !== 'N') {
                continue;
            }
            $l = null;
            for ($j = $i - 1; $j >= 0; $j--) {
                if ($types[$j] !== 'N') {
                    $l = $types[$j];
                    break;
                }
            }
            $r = null;
            for ($j = $i + 1; $j < $n; $j++) {
                if ($types[$j] !== 'N') {
                    $r = $types[$j];
                    break;
                }
            }
            $types[$i] = ($l === 'L' && $r === 'L') ? 'L*' : 'R';
        }
        $runs = [];
        foreach ($chars as $i => $ch) {
            $t = $types[$i] === 'R' ? 'R' : 'L';
            if ($runs && end($runs)[0] === $t) {
                $runs[count($runs) - 1][1][] = $ch;
            } else {
                $runs[] = [$t, [$ch]];
            }
        }
        $out = '';
        foreach (array_reverse($runs) as [$t, $cs]) {
            if ($t === 'R') {
                foreach (array_reverse($cs) as $ch) {
                    $out .= self::MIRROR[$ch] ?? $ch;
                }
            } else {
                $out .= implode('', $cs);
            }
        }
        return $out;
    }
}

final class Cards
{
    const BG_TOP = [10, 14, 32];
    const BG_BOTTOM = [22, 28, 58];
    const WHITE = [255, 255, 255];
    const MUTED = [170, 180, 210];
    const GREEN = [52, 211, 153];
    const RED = [248, 113, 113];
    const GOLD = [251, 191, 36];

    private static function font(string $weight): string
    {
        return Config::$root . '/assets/fonts/Vazirmatn-' . $weight . '.ttf';
    }

    private static function col($im, array $rgb, int $alpha255 = 255): int
    {
        $a = 127 - (int)round(max(0, min(255, $alpha255)) * 127 / 255);
        return imagecolorallocatealpha($im, $rgb[0], $rgb[1], $rgb[2], $a);
    }

    private static function canvas(int $w, int $h)
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, true);
        imagesavealpha($im, true);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / max($h - 1, 1);
            $c = [];
            for ($i = 0; $i < 3; $i++) {
                $c[] = (int)(self::BG_TOP[$i] + (self::BG_BOTTOM[$i] - self::BG_TOP[$i]) * $t);
            }
            imageline($im, 0, $y, $w, $y, self::col($im, $c));
        }
        return $im;
    }

    private static function glow($im, int $cx, int $cy, int $r, array $rgb, int $alpha = 110): void
    {
        $steps = 28;
        $per = max(1, (int)($alpha / $steps * 1.6));
        for ($k = $steps; $k >= 1; $k--) {
            $rr = (int)($r * $k / $steps);
            imagefilledellipse($im, $cx, $cy, $rr * 2, $rr * 2, self::col($im, $rgb, $per));
        }
    }

    private static function rrect($im, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        $r = min($r, (int)(($x1 - $x0) / 2), (int)(($y1 - $y0) / 2));
        for ($y = $y0; $y <= $y1; $y++) {
            $inset = 0;
            if ($y < $y0 + $r) {
                $d = $y0 + $r - $y;
                $inset = $r - sqrt(max(0, $r * $r - $d * $d));
            } elseif ($y > $y1 - $r) {
                $d = $y - ($y1 - $r);
                $inset = $r - sqrt(max(0, $r * $r - $d * $d));
            }
            imageline($im, (int)round($x0 + $inset), $y, (int)round($x1 - $inset), $y, $color);
        }
    }

    private static function routline($im, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void
    {
        imagesetthickness($im, 2);
        imageline($im, $x0 + $r, $y0, $x1 - $r, $y0, $color);
        imageline($im, $x0 + $r, $y1, $x1 - $r, $y1, $color);
        imageline($im, $x0, $y0 + $r, $x0, $y1 - $r, $color);
        imageline($im, $x1, $y0 + $r, $x1, $y1 - $r, $color);
        imagearc($im, $x0 + $r, $y0 + $r, $r * 2, $r * 2, 180, 270, $color);
        imagearc($im, $x1 - $r, $y0 + $r, $r * 2, $r * 2, 270, 360, $color);
        imagearc($im, $x0 + $r, $y1 - $r, $r * 2, $r * 2, 90, 180, $color);
        imagearc($im, $x1 - $r, $y1 - $r, $r * 2, $r * 2, 0, 90, $color);
        imagesetthickness($im, 1);
    }

    private static function glass($im, int $x0, int $y0, int $x1, int $y1, int $r = 28, int $fill = 22, int $outline = 55, array $tint = self::WHITE, bool $highlight = true): void
    {
        self::rrect($im, $x0, $y0, $x1, $y1, $r, self::col($im, $tint, $fill));
        if ($highlight) {
            self::rrect($im, $x0 + 3, $y0 + 3, $x1 - 3, $y0 + (int)(($y1 - $y0) / 2), max($r - 3, 4), self::col($im, self::WHITE, 8));
        }
        self::routline($im, $x0, $y0, $x1, $y1, $r, self::col($im, $tint, $outline));
    }

    private static function shadow($im, int $x0, int $y0, int $x1, int $y1, int $r = 24): void
    {
        for ($k = 6; $k >= 1; $k--) {
            self::rrect($im, $x0 - $k * 2, $y0 + 10 - $k * 2, $x1 + $k * 2, $y1 + 10 + $k * 2, $r + $k * 2, self::col($im, [0, 0, 0], 14));
        }
    }

    private static function bbox(string $text, int $px, string $weight): array
    {
        $b = imagettfbbox($px * 0.75, 0, self::font($weight), $text);
        $minX = min($b[0], $b[6]);
        $maxX = max($b[2], $b[4]);
        return [$minX, $maxX - $minX, min($b[5], $b[7]), max($b[1], $b[3])];
    }

    public static function width(string $text, int $px, string $weight = 'Bold'): float
    {
        return self::bbox(Persian::visual($text), $px, $weight)[1];
    }

    public static function fit(string $text, float $max, int $px, string $weight = 'Bold', int $min = 14): int
    {
        while ($px > $min && self::width($text, $px, $weight) > $max) {
            $px -= 2;
        }
        return $px;
    }

    public static function text($im, float $x, float $y, string $text, int $px, string $weight, array $rgb, string $anchor = 'la'): void
    {
        $vis = Persian::visual($text);
        [$minX, $w, $top, $bottom] = self::bbox($vis, $px, $weight);
        $h = $anchor[0];
        $v = $anchor[1] ?? 'a';
        $dx = match ($h) {
            'r' => $x - $w - $minX,
            'm' => $x - $w / 2 - $minX,
            default => $x - $minX,
        };
        $dy = match ($v) {
            'm' => $y - ($top + $bottom) / 2,
            's' => $y,
            default => $y - $top,
        };
        imagettftext($im, $px * 0.75, 0, (int)round($dx), (int)round($dy), self::col($im, $rgb), self::font($weight), $vis);
    }

    private static function cover($src, int $w, int $h)
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int)($w / $scale);
        $ch = (int)($h / $scale);
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2), $w, $h, $cw, $ch);
        return $dst;
    }

    private static function roundCorners($img, int $r): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        imagealphablending($img, false);
        $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
        for ($y = 0; $y < $r; $y++) {
            for ($x = 0; $x < $r; $x++) {
                if (($r - $x - 0.5) ** 2 + ($r - $y - 0.5) ** 2 > $r * $r) {
                    imagesetpixel($img, $x, $y, $clear);
                    imagesetpixel($img, $w - 1 - $x, $y, $clear);
                    imagesetpixel($img, $x, $h - 1 - $y, $clear);
                    imagesetpixel($img, $w - 1 - $x, $h - 1 - $y, $clear);
                }
            }
        }
        imagealphablending($img, true);
    }

    private static function avg($img): array
    {
        $one = imagecreatetruecolor(1, 1);
        imagecopyresampled($one, $img, 0, 0, 0, 0, 1, 1, imagesx($img), imagesy($img));
        $c = imagecolorat($one, 0, 0);
        return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255];
    }

    private static function flag(?string $cc, string $code)
    {
        $path = Config::$root . '/assets/flags/' . $cc . '.png';
        if ($cc && is_file($path)) {
            $img = imagecreatefrompng($path);
            if ($img) {
                return $img;
            }
        }
        $color = ['XAU' => [212, 175, 55], 'XAG' => [192, 192, 200]][$code] ?? [70, 90, 160];
        $img = imagecreatetruecolor(320, 240);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$color));
        self::text($img, 160, 120, $code, 96, 'Black', [20, 20, 30], 'mm');
        return $img;
    }

    private static function png($im): string
    {
        ob_start();
        imagepng($im, null, 6);
        return ob_get_clean();
    }

    public static function fiat(string $code, string $nameFa, string $nameEn, ?string $cc, string $amount, array $rows, string $unitLine, string $bot): string
    {
        $W = 1280;
        $H = 760;
        $im = self::canvas($W, $H);
        $flag = self::flag($cc, $code);
        $accent = self::avg($flag);
        self::glow($im, $W - 160, 120, 260, $accent, 90);
        self::glow($im, 120, $H - 80, 240, [99, 102, 241], 80);
        $fw = 210;
        $fh = 158;
        $fx = $W - 60 - $fw;
        $fy = 50;
        self::shadow($im, $fx, $fy, $fx + $fw, $fy + $fh, 26);
        $fl = self::cover($flag, $fw, $fh);
        self::roundCorners($fl, 26);
        imagecopy($im, $fl, $fx, $fy, 0, 0, $fw, $fh);
        self::routline($im, $fx, $fy, $fx + $fw, $fy + $fh, 26, self::col($im, self::WHITE, 90));
        $tr = $fx - 36;
        self::text($im, $tr, 62, $nameFa, self::fit($nameFa, 640, 64, 'Black'), 'Black', self::WHITE, 'ra');
        self::text($im, $tr, 150, "$nameEn • $code", 30, 'Medium', self::MUTED, 'ra');
        $pill = "$amount $code";
        $pf = self::fit($pill, 420, 60, 'Black');
        $pw = (int)self::width($pill, $pf, 'Black') + 70;
        self::glass($im, 60, 70, 60 + $pw, 190, 60, 40, 55, $accent, false);
        self::text($im, 60 + $pw / 2, 130, $pill, $pf, 'Black', self::WHITE, 'mm');
        $cols = 3;
        $gap = 26;
        $top = 250;
        $bw = (int)(($W - 120 - $gap * ($cols - 1)) / $cols);
        $bh = 180;
        foreach (array_slice($rows, 0, 6) as $i => [$label, $value, $unit]) {
            $r = intdiv($i, $cols);
            $c = $i % $cols;
            $x1 = $W - 60 - $c * ($bw + $gap);
            $x0 = $x1 - $bw;
            $y0 = $top + $r * ($bh + $gap);
            self::glass($im, $x0, $y0, $x1, $y0 + $bh, 30);
            self::text($im, $x1 - 28, $y0 + 22, $label, 30, 'Bold', self::MUTED, 'ra');
            self::text($im, $x0 + $bw / 2, $y0 + 105, $value, self::fit($value, $bw - 56, 52, 'Black', 22), 'Black', self::WHITE, 'mm');
            self::text($im, $x0 + 28, $y0 + $bh - 22, $unit, 24, 'Medium', self::GOLD, 'ls');
        }
        $fy0 = $H - 92;
        self::glass($im, 60, $fy0, $W - 60, $H - 30, 24, 16);
        self::text($im, $W - 90, $fy0 + 31, $unitLine, self::fit($unitLine, 700, 28, 'Bold'), 'Bold', self::WHITE, 'rm');
        $left = Fmt::jalali();
        if ($bot !== '') {
            $left = "@$bot  •  $left";
        }
        self::text($im, 90, $fy0 + 31, $left, 24, 'Medium', self::MUTED, 'lm');
        $png = self::png($im);
        imagedestroy($im);
        return $png;
    }

    private static function disc($im, int $x, int $y, int $size, array $center, array $edge): void
    {
        $steps = 40;
        for ($k = $steps; $k >= 1; $k--) {
            $t = $k / $steps;
            $c = [];
            for ($i = 0; $i < 3; $i++) {
                $c[] = (int)($center[$i] + ($edge[$i] - $center[$i]) * $t);
            }
            $d = (int)($size * $t);
            imagefilledellipse($im, $x + intdiv($size, 2), $y + intdiv($size, 2), $d, $d, self::col($im, $c));
        }
    }

    public static function gift(string $owner, ?string $username, array $tiles, float $totalTon, ?float $totalUsd, ?float $totalToman, ?float $plTon, ?float $plPct, int $count, string $bot): string
    {
        $W = 1280;
        $cols = 2;
        $shown = array_slice($tiles, 0, 20);
        $rows = max(1, (int)ceil(count($shown) / $cols));
        $tileH = 130;
        $gap = 18;
        $headerH = 380;
        $H = $headerH + $rows * ($tileH + $gap) + 120;
        $im = self::canvas($W, $H);
        self::glow($im, $W - 200, 120, 300, [124, 58, 237], 90);
        self::glow($im, 160, 260, 260, [14, 165, 233], 70);
        self::text($im, $W - 60, 48, 'پرتفوی گیفت‌های تلگرام', 30, 'Bold', self::MUTED, 'ra');
        self::text($im, $W - 60, 92, $owner, self::fit($owner, 760, 62, 'Black'), 'Black', self::WHITE, 'ra');
        if ($username) {
            self::text($im, $W - 60, 176, "@$username", 28, 'Medium', self::MUTED, 'ra');
        }
        self::text($im, 60, 60, 'GIFT PORTFOLIO', 26, 'Black', self::GOLD, 'la');
        $stats = [
            ['تعداد گیفت', Fmt::num($count), self::WHITE],
            ['ارزش کل (TON)', Fmt::num($totalTon), self::WHITE],
            ['ارزش (تومان)', $totalToman ? Fmt::num($totalToman) : '—', self::WHITE],
        ];
        if ($plTon !== null) {
            $sign = $plTon >= 0 ? '+' : '';
            $pct = $plPct !== null ? sprintf(' (%s%.1f%%)', $sign, $plPct) : '';
            $stats[] = ['سود/ضرر ۷ روز', $sign . Fmt::num($plTon) . $pct, $plTon >= 0 ? self::GREEN : self::RED];
        } else {
            $stats[] = ['ارزش (دلار)', $totalUsd ? Fmt::num($totalUsd) : '—', self::WHITE];
        }
        $bw = (int)(($W - 120 - 3 * 20) / 4);
        foreach ($stats as $i => [$label, $value, $color]) {
            $x1 = $W - 60 - $i * ($bw + 20);
            $x0 = $x1 - $bw;
            self::glass($im, $x0, 230, $x1, 350, 26);
            self::text($im, $x1 - 22, 246, $label, 24, 'Bold', self::MUTED, 'ra');
            self::text($im, $x0 + $bw / 2, 310, $value, self::fit($value, $bw - 30, 40, 'Black', 18), 'Black', $color, 'mm');
        }
        $tw = (int)(($W - 120 - $gap) / $cols);
        foreach ($shown as $i => $t) {
            $r = intdiv($i, $cols);
            $c = $i % $cols;
            $x1 = $W - 60 - $c * ($tw + $gap);
            $x0 = $x1 - $tw;
            $y0 = $headerH + $r * ($tileH + $gap);
            self::glass($im, $x0, $y0, $x1, $y0 + $tileH, 26, 18);
            $disc = 100;
            $dx = $x1 - 18 - $disc;
            $dy = $y0 + intdiv($tileH - $disc, 2);
            self::disc($im, $dx, $dy, $disc, $t['center'] ?? [90, 110, 200], $t['edge'] ?? [40, 50, 110]);
            if (!empty($t['image'])) {
                $src = @imagecreatefromstring($t['image']);
                if ($src) {
                    $s = $disc - 10;
                    $scale = min($s / imagesx($src), $s / imagesy($src));
                    $w = (int)(imagesx($src) * $scale);
                    $h = (int)(imagesy($src) * $scale);
                    imagecopyresampled($im, $src, $dx + intdiv($disc - $w, 2), $dy + intdiv($disc - $h, 2), 0, 0, $w, $h, imagesx($src), imagesy($src));
                    imagedestroy($src);
                }
            }
            $tr = $dx - 18;
            $val = !empty($t['value']) ? Fmt::num($t['value']) . ' TON' : '—';
            $vf = self::fit($val, 200, 30, 'Black', 16);
            self::text($im, $x0 + 24, $y0 + $tileH / 2, $val, $vf, 'Black', self::GOLD, 'lm');
            $room = $tr - ($x0 + 24 + self::width($val, $vf, 'Black') + 22);
            self::text($im, $tr, $y0 + 22, $t['title'], self::fit($t['title'], $room, 32, 'Black', 14), 'Black', self::WHITE, 'ra');
            self::text($im, $tr, $y0 + 72, $t['subtitle'], self::fit($t['subtitle'], $room, 24, 'Medium', 12), 'Medium', self::MUTED, 'ra');
        }
        $more = count($tiles) - count($shown);
        $footer = Fmt::jalali();
        if ($bot !== '') {
            $footer = "@$bot  •  $footer";
        }
        self::text($im, 60, $H - 60, $footer, 24, 'Medium', self::MUTED, 'lm');
        if ($more > 0) {
            self::text($im, $W - 60, $H - 60, "+$more گیفت دیگر", 26, 'Bold', self::WHITE, 'rm');
        }
        $png = self::png($im);
        imagedestroy($im);
        return $png;
    }
}

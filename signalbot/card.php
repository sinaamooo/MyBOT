<?php

declare(strict_types=1);

final class CardPalette
{
    public const BASE       = [7, 8, 12];
    public const WHITE      = [255, 255, 255];
    public const GREEN_SOFT = [134, 247, 207];
    public const RED_SOFT   = [255, 155, 176];

    public const ORBS = [
        'short'  => [[255, 61, 110], [123, 92, 255]],
        'long'   => [[20, 224, 160], [59, 130, 246]],
        'profit' => [[20, 224, 160], [47, 123, 255]],
        'loss'   => [[255, 61, 110], [255, 138, 61]],
        'flat'   => [[107, 127, 168], [154, 168, 199]],
    ];

    public const PILLS = [
        'short' => [[255, 77, 109], 0.20, [255, 130, 155], 0.55, [255, 194, 207]],
        'long'  => [[20, 224, 160], 0.18, [110, 245, 200], 0.55, [191, 255, 232]],
        'plain' => [[255, 255, 255], 0.08, [255, 255, 255], 0.22, [255, 255, 255]],
    ];
}

final class CardFormat
{
    public static function orNA(?string $value): string
    {
        $value = trim((string) $value);
        return $value === '' || $value === '-' ? 'N/A' : $value;
    }

    public static function number(string $value): ?float
    {
        $clean = str_replace([',', '%', '$', '+', ' ', '−'], ['', '', '', '', '', '-'], trim($value));
        return is_numeric($clean) ? (float) $clean : null;
    }

    public static function signed(string $value): string
    {
        $value = trim($value);
        return str_starts_with($value, '-') ? '−' . substr($value, 1) : $value;
    }

    public static function duration(string $value): string
    {
        $value = trim($value);
        return preg_match('/^(\d+)H\s*(\d+)M$/i', $value, $m) === 1 ? ((int) $m[1]) . 'h ' . $m[2] . 'm' : $value;
    }
}

final class VectorFont
{
    public const TRACKING = 2.0;

    private static ?array $glyphs = null;

    private static function glyphs(): array
    {
        if (self::$glyphs !== null) {
            return self::$glyphs;
        }

        $ring = static function (float $cx, float $cy, float $r): array {
            $pts = [];
            for ($i = 0; $i <= 8; $i++) {
                $a = ($i / 8) * 2 * M_PI;
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            return $pts;
        };

        $dot = static fn(float $x, float $y): array => [[$x, $y], [$x + 0.01, $y]];

        return self::$glyphs = [
            ' ' => [3.6, []],

            'A' => [6.0, [[[0, 10], [3, 0], [6, 10]], [[1.0, 6.7], [5.0, 6.7]]]],
            'B' => [6.0, [
                [[0, 0], [0, 10]],
                [[0, 0], [4.0, 0], [5.6, 1.4], [5.6, 3.6], [4.0, 5.0], [0, 5.0]],
                [[0, 5.0], [4.3, 5.0], [6.0, 6.5], [6.0, 8.5], [4.3, 10], [0, 10]],
            ]],
            'C' => [6.0, [[[6.0, 2.0], [4.6, 0.5], [3.0, 0], [1.4, 0.7], [0, 3.0], [0, 7.0], [1.4, 9.3], [3.0, 10], [4.6, 9.5], [6.0, 8.0]]]],
            'D' => [6.0, [
                [[0, 0], [0, 10]],
                [[0, 0], [3.0, 0], [5.2, 1.8], [6.0, 5.0], [5.2, 8.2], [3.0, 10], [0, 10]],
            ]],
            'E' => [5.8, [[[5.8, 0], [0, 0], [0, 10], [5.8, 10]], [[0, 5.0], [4.4, 5.0]]]],
            'F' => [5.6, [[[5.6, 0], [0, 0], [0, 10]], [[0, 5.0], [4.4, 5.0]]]],
            'G' => [6.2, [[[6.2, 2.0], [4.8, 0.5], [3.0, 0], [1.4, 0.7], [0, 3.0], [0, 7.0], [1.4, 9.3], [3.0, 10], [4.8, 9.4], [6.2, 7.6], [6.2, 5.6], [3.6, 5.6]]]],
            'H' => [6.0, [[[0, 0], [0, 10]], [[6, 0], [6, 10]], [[0, 5.0], [6, 5.0]]]],
            'I' => [4.0, [[[0, 0], [4, 0]], [[2, 0], [2, 10]], [[0, 10], [4, 10]]]],
            'J' => [5.4, [[[1.2, 0], [5.4, 0]], [[3.6, 0], [3.6, 7.6], [2.6, 9.6], [1.0, 10], [0, 8.4]]]],
            'K' => [6.0, [[[0, 0], [0, 10]], [[6.0, 0], [0.6, 5.6]], [[2.2, 4.2], [6.0, 10]]]],
            'L' => [5.4, [[[0, 0], [0, 10], [5.4, 10]]]],
            'M' => [7.2, [[[0, 10], [0, 0], [3.6, 5.6], [7.2, 0], [7.2, 10]]]],
            'N' => [6.4, [[[0, 10], [0, 0], [6.4, 10], [6.4, 0]]]],
            'O' => [6.4, [[[3.2, 0], [5.2, 1.0], [6.4, 3.2], [6.4, 6.8], [5.2, 9.0], [3.2, 10], [1.2, 9.0], [0, 6.8], [0, 3.2], [1.2, 1.0], [3.2, 0]]]],
            'P' => [6.0, [[[0, 10], [0, 0], [4.0, 0], [6.0, 1.8], [6.0, 4.0], [4.0, 5.8], [0, 5.8]]]],
            'Q' => [6.6, [
                [[3.2, 0], [5.2, 1.0], [6.4, 3.2], [6.4, 6.8], [5.2, 9.0], [3.2, 10], [1.2, 9.0], [0, 6.8], [0, 3.2], [1.2, 1.0], [3.2, 0]],
                [[4.0, 7.0], [6.6, 10.4]],
            ]],
            'R' => [6.2, [
                [[0, 10], [0, 0], [4.0, 0], [6.0, 1.8], [6.0, 3.8], [4.0, 5.6], [0, 5.6]],
                [[3.2, 5.6], [6.2, 10]],
            ]],
            'S' => [6.0, [[[6.0, 1.6], [4.4, 0.2], [2.0, 0], [0.4, 1.0], [0, 2.8], [0.8, 4.2], [5.0, 5.8], [6.0, 7.2], [5.6, 9.2], [3.6, 10], [1.4, 9.8], [0, 8.4]]]],
            'T' => [6.0, [[[0, 0], [6, 0]], [[3, 0], [3, 10]]]],
            'U' => [6.0, [[[0, 0], [0, 7.0], [1.6, 9.6], [3.0, 10], [4.4, 9.6], [6.0, 7.0], [6.0, 0]]]],
            'V' => [6.0, [[[0, 0], [3, 10], [6, 0]]]],
            'W' => [8.4, [[[0, 0], [1.7, 10], [4.2, 3.6], [6.7, 10], [8.4, 0]]]],
            'X' => [6.0, [[[0, 0], [6, 10]], [[6, 0], [0, 10]]]],
            'Y' => [6.0, [[[0, 0], [3, 5.2], [6, 0]], [[3, 5.2], [3, 10]]]],
            'Z' => [6.0, [[[0, 0], [6, 0], [0, 10], [6, 10]]]],

            '0' => [6.0, [
                [[3.0, 0], [4.9, 1.0], [6.0, 3.2], [6.0, 6.8], [4.9, 9.0], [3.0, 10], [1.1, 9.0], [0, 6.8], [0, 3.2], [1.1, 1.0], [3.0, 0]],
                [[1.1, 8.2], [4.9, 1.8]],
            ]],
            '1' => [4.2, [[[0, 2.2], [2.2, 0], [2.2, 10]], [[0.2, 10], [4.2, 10]]]],
            '2' => [6.0, [[[0, 2.2], [1.6, 0.3], [3.6, 0], [5.4, 1.0], [6.0, 3.0], [5.0, 4.8], [0, 10], [6.0, 10]]]],
            '3' => [6.0, [
                [[0.2, 1.0], [2.0, 0], [4.4, 0], [6.0, 1.4], [6.0, 3.4], [4.2, 4.8], [2.4, 4.8]],
                [[2.4, 4.8], [4.6, 5.0], [6.0, 6.4], [6.0, 8.6], [4.4, 10], [2.0, 10], [0.2, 9.0]],
            ]],
            '4' => [6.0, [[[4.4, 10], [4.4, 0], [0, 7.2], [6.0, 7.2]]]],
            '5' => [6.0, [[[5.6, 0], [0.8, 0], [0.2, 4.2], [2.0, 3.2], [4.0, 3.2], [6.0, 5.0], [6.0, 8.0], [4.2, 10], [2.0, 10], [0, 9.0]]]],
            '6' => [6.0, [[[5.2, 0.4], [3.0, 0], [1.0, 1.6], [0, 4.8], [0, 7.6], [1.6, 9.8], [3.6, 10], [5.4, 8.8], [6.0, 6.8], [4.8, 4.8], [2.6, 4.4], [0.6, 5.6]]]],
            '7' => [6.0, [[[0, 0], [6, 0], [2.2, 10]]]],
            '8' => [6.0, [
                [[3.0, 4.7], [1.0, 3.8], [0.6, 2.0], [2.0, 0.2], [4.0, 0.2], [5.4, 2.0], [5.0, 3.8], [3.0, 4.7]],
                [[3.0, 4.7], [5.4, 5.8], [6.0, 7.8], [4.4, 10], [1.6, 10], [0, 7.8], [0.6, 5.8], [3.0, 4.7]],
            ]],
            '9' => [6.0, [[[0.8, 9.6], [3.0, 10], [5.0, 8.4], [6.0, 5.2], [6.0, 2.4], [4.4, 0.2], [2.4, 0], [0.6, 1.2], [0, 3.2], [1.2, 5.2], [3.4, 5.6], [5.4, 4.4]]]],

            '.' => [2.0, [$dot(1.0, 9.4)]],
            ',' => [2.4, [[[1.3, 9.2], [0.4, 11.4]]]],
            ':' => [2.0, [$dot(1.0, 3.4), $dot(1.0, 9.4)]],
            ';' => [2.4, [$dot(1.3, 3.4), [[1.3, 9.2], [0.4, 11.4]]]],
            '-' => [4.8, [[[0.4, 5.4], [4.4, 5.4]]]],
            '_' => [6.0, [[[0, 10.8], [6, 10.8]]]],
            '+' => [6.0, [[[3, 2.0], [3, 8.0]], [[0, 5.0], [6, 5.0]]]],
            '=' => [6.0, [[[0, 3.6], [6, 3.6]], [[0, 7.0], [6, 7.0]]]],
            '/' => [5.0, [[[5.0, -0.6], [0, 10.6]]]],
            '\\' => [5.0, [[[0, -0.6], [5.0, 10.6]]]],
            '|' => [1.4, [[[0.7, -0.6], [0.7, 10.6]]]],
            '(' => [3.2, [[[3.2, -0.6], [0.6, 3.0], [0.6, 7.0], [3.2, 10.6]]]],
            ')' => [3.2, [[[0, -0.6], [2.6, 3.0], [2.6, 7.0], [0, 10.6]]]],
            '[' => [3.0, [[[3.0, -0.4], [0.6, -0.4], [0.6, 10.4], [3.0, 10.4]]]],
            ']' => [3.0, [[[0, -0.4], [2.4, -0.4], [2.4, 10.4], [0, 10.4]]]],
            '<' => [5.0, [[[4.4, 1.0], [0.4, 5.4], [4.4, 9.8]]]],
            '>' => [5.0, [[[0.6, 1.0], [4.6, 5.4], [0.6, 9.8]]]],
            '!' => [1.8, [[[0.9, 0], [0.9, 7.0]], $dot(0.9, 9.4)]],
            '?' => [5.6, [[[0.2, 2.0], [1.8, 0.2], [3.8, 0.2], [5.4, 1.8], [5.2, 3.8], [2.8, 5.6], [2.8, 7.0]], $dot(2.8, 9.4)]],
            '*' => [6.0, [[[3, 1.0], [3, 7.0]], [[0.6, 2.4], [5.4, 6.0]], [[5.4, 2.4], [0.6, 6.0]]]],
            '#' => [6.8, [[[2.2, 0], [1.2, 10]], [[5.2, 0], [4.2, 10]], [[0, 3.2], [6.4, 3.2]], [[0, 6.8], [6.4, 6.8]]]],
            '%' => [8.0, [[[8.0, 0], [0, 10]], $ring(1.7, 1.7, 1.5), $ring(6.3, 8.3, 1.5)]],
            '$' => [6.0, [
                [[6.0, 1.6], [4.4, 0.2], [2.0, 0], [0.4, 1.0], [0, 2.8], [0.8, 4.2], [5.0, 5.8], [6.0, 7.2], [5.6, 9.2], [3.6, 10], [1.4, 9.8], [0, 8.4]],
                [[3.0, -1.4], [3.0, 11.4]],
            ]],
            '~' => [6.4, [[[0, 6.2], [1.6, 4.6], [3.2, 6.2], [4.8, 7.8], [6.4, 6.2]]]],
            '°' => [3.6, [$ring(1.8, 1.8, 1.6)]],
            '•' => [4.2, [$dot(2.1, 5.2)]],
            '×' => [5.6, [[[0.8, 2.6], [4.8, 7.4]], [[4.8, 2.6], [0.8, 7.4]]]],
            '→' => [9.2, [[[0, 5.2], [8.4, 5.2]], [[5.8, 2.4], [8.8, 5.2], [5.8, 8.0]]]],
            '↑' => [8.0, [[[4.0, 10], [4.0, 0.6]], [[1.0, 3.6], [4.0, 0.6], [7.0, 3.6]]]],
            '↓' => [8.0, [[[4.0, 0.6], [4.0, 10]], [[1.0, 7.0], [4.0, 10], [7.0, 7.0]]]],
        ];
    }

    public static function keys(string $text): array
    {
        $glyphs = self::glyphs();
        $chars = preg_split('//u', strtoupper($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($chars as $ch) {
            if (isset($glyphs[$ch])) {
                $out[] = $ch;
            } elseif ($ch === "\u{2192}" || $ch === '>') {
                $out[] = '→';
            } elseif (trim($ch) === '') {
                $out[] = ' ';
            }
        }
        return $out;
    }

    public static function glyph(string $key): array
    {
        return self::glyphs()[$key] ?? [3.6, []];
    }
}

final class PersianShaper
{
    private const FORMS = [
        'ا' => ["\u{FE8D}", "\u{FE8E}", "\u{FE8D}", "\u{FE8E}"],
        'آ' => ["\u{FE81}", "\u{FE82}", "\u{FE81}", "\u{FE82}"],
        'أ' => ["\u{FE83}", "\u{FE84}", "\u{FE83}", "\u{FE84}"],
        'إ' => ["\u{FE87}", "\u{FE88}", "\u{FE87}", "\u{FE88}"],
        'ب' => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        'پ' => ["\u{FB56}", "\u{FB57}", "\u{FB58}", "\u{FB59}"],
        'ت' => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        'ث' => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        'ج' => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        'چ' => ["\u{FB7A}", "\u{FB7B}", "\u{FB7C}", "\u{FB7D}"],
        'ح' => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        'خ' => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        'د' => ["\u{FEA9}", "\u{FEAA}", "\u{FEA9}", "\u{FEAA}"],
        'ذ' => ["\u{FEAB}", "\u{FEAC}", "\u{FEAB}", "\u{FEAC}"],
        'ر' => ["\u{FEAD}", "\u{FEAE}", "\u{FEAD}", "\u{FEAE}"],
        'ز' => ["\u{FEAF}", "\u{FEB0}", "\u{FEAF}", "\u{FEB0}"],
        'ژ' => ["\u{FB8A}", "\u{FB8B}", "\u{FB8A}", "\u{FB8B}"],
        'س' => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        'ش' => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        'ص' => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        'ض' => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        'ط' => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        'ظ' => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        'ع' => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        'غ' => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        'ف' => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        'ق' => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        'ک' => ["\u{FB8E}", "\u{FB8F}", "\u{FB90}", "\u{FB91}"],
        'ك' => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        'گ' => ["\u{FB92}", "\u{FB93}", "\u{FB94}", "\u{FB95}"],
        'ل' => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        'م' => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        'ن' => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        'ه' => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        'و' => ["\u{FEED}", "\u{FEEE}", "\u{FEED}", "\u{FEEE}"],
        'ی' => ["\u{FBFC}", "\u{FBFD}", "\u{FBFE}", "\u{FBFF}"],
        'ي' => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
        'ئ' => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        'ء' => ["\u{FE80}", "\u{FE80}", "\u{FE80}", "\u{FE80}"],
        'ة' => ["\u{FE93}", "\u{FE94}", "\u{FE93}", "\u{FE94}"],
    ];

    private const NON_CONNECTING = ['ا', 'آ', 'أ', 'إ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'و', 'ء', 'ة'];

    private const LIGATURES = [
        'لا' => ["\u{FEFB}", "\u{FEFC}"],
        'لآ' => ["\u{FEF5}", "\u{FEF6}"],
        'لأ' => ["\u{FEF7}", "\u{FEF8}"],
        'لإ' => ["\u{FEF9}", "\u{FEFA}"],
    ];

    public static function containsPersian(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    public static function shape(string $text): string
    {
        if (!self::containsPersian($text)) {
            return $text;
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $merged = [];
        for ($i = 0; $i < count($chars); $i++) {
            $pair = $chars[$i] . ($chars[$i + 1] ?? '');
            if (isset(self::LIGATURES[$pair])) {
                $merged[] = ['lig' => self::LIGATURES[$pair]];
                $i++;
                continue;
            }
            $merged[] = ['ch' => $chars[$i]];
        }

        $shaped = [];
        $count = count($merged);
        for ($i = 0; $i < $count; $i++) {
            $prevJoins = self::joinsForward($merged[$i - 1] ?? null);
            $item = $merged[$i];

            if (isset($item['lig'])) {
                $shaped[] = $prevJoins ? $item['lig'][1] : $item['lig'][0];
                continue;
            }

            $ch = $item['ch'];
            if (!isset(self::FORMS[$ch])) {
                $shaped[] = $ch;
                continue;
            }
            $nextJoins = self::joinsBackward($merged[$i + 1] ?? null);
            $selfJoins = !in_array($ch, self::NON_CONNECTING, true);
            $form = match (true) {
                $prevJoins && $nextJoins && $selfJoins => 3,
                $prevJoins && (!$nextJoins || !$selfJoins) => 1,
                !$prevJoins && $nextJoins && $selfJoins => 2,
                default => 0,
            };
            $shaped[] = self::FORMS[$ch][$form];
        }

        return self::toVisualOrder($shaped);
    }

    private static function joinsForward(?array $prev): bool
    {
        if ($prev === null) {
            return false;
        }
        if (isset($prev['lig'])) {
            return false;
        }
        return isset(self::FORMS[$prev['ch']]) && !in_array($prev['ch'], self::NON_CONNECTING, true);
    }

    private static function joinsBackward(?array $next): bool
    {
        if ($next === null) {
            return false;
        }
        if (isset($next['lig'])) {
            return true;
        }
        return isset(self::FORMS[$next['ch']]);
    }

    private static function toVisualOrder(array $shaped): string
    {
        $mirror = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '<' => '>', '>' => '<'];
        $runs = [];
        $ltrRun = [];

        foreach ($shaped as $ch) {
            $isLtr = (bool) preg_match('/[0-9A-Za-z%\$\.,:\-\+\/\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $ch);
            if ($isLtr) {
                $ltrRun[] = $ch;
                continue;
            }
            if (!empty($ltrRun)) {
                $runs[] = implode('', $ltrRun);
                $ltrRun = [];
            }
            $runs[] = $mirror[$ch] ?? $ch;
        }
        if (!empty($ltrRun)) {
            $runs[] = implode('', $ltrRun);
        }

        return implode('', array_reverse($runs));
    }
}

final class CardCanvas
{
    private \GdImage $im;
    private int $scale;
    private array $colorCache = [];

    private static array $metrics = [];

    public function __construct(private int $width, private int $height, ?int $scale = null)
    {
        $this->scale = max(1, min(4, $scale ?? CardConfig::renderScale()));
        $im = imagecreatetruecolor($this->width * $this->scale, $this->height * $this->scale);
        if ($im === false) {
            throw new RuntimeException('imagecreatetruecolor failed');
        }
        $this->im = $im;
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
    }

    public function destroy(): void
    {
        imagedestroy($this->im);
    }

    private function color(array $rgb, float $opacity = 1.0): int
    {
        $alpha = (int) round((1.0 - max(0.0, min(1.0, $opacity))) * 127);
        $r = self::channel($rgb[0]);
        $g = self::channel($rgb[1]);
        $b = self::channel($rgb[2]);
        $key = $r . ',' . $g . ',' . $b . ',' . $alpha;
        if (isset($this->colorCache[$key])) {
            return $this->colorCache[$key];
        }
        $c = imagecolorallocatealpha($this->im, $r, $g, $b, $alpha);
        return $this->colorCache[$key] = ($c === false ? 0 : $c);
    }

    private static function channel(float|int $v): int
    {
        return max(0, min(255, (int) round($v)));
    }

    public static function mix(array $fg, array $bg, float $amount): array
    {
        $t = max(0.0, min(1.0, $amount));
        return [
            $fg[0] * $t + $bg[0] * (1 - $t),
            $fg[1] * $t + $bg[1] * (1 - $t),
            $fg[2] * $t + $bg[2] * (1 - $t),
        ];
    }

    private function fx(float $x): float
    {
        return $x * $this->scale;
    }

    private function fy(float $y): float
    {
        return $y * $this->scale;
    }

    private function px(float $x): int
    {
        return (int) round($x * $this->scale);
    }

    private function py(float $y): int
    {
        return (int) round($y * $this->scale);
    }

    public function blitField(array $field, int $lw, int $lh, int $step, ?array $mask = null): void
    {
        [$red, $green, $blue] = $field;
        if ($mask === null) {
            [$i0, $j0, $i1, $j1] = [0, 0, $lw - 1, $lh - 1];
        } else {
            [$mx, $my, $mw, $mh] = $mask;
            $i0 = max(0, (int) floor($mx / $step) - 1);
            $j0 = max(0, (int) floor($my / $step) - 1);
            $i1 = min($lw - 1, (int) ceil(($mx + $mw) / $step) + 1);
            $j1 = min($lh - 1, (int) ceil(($my + $mh) / $step) + 1);
        }
        $cw = $i1 - $i0 + 1;
        $ch = $j1 - $j0 + 1;
        $low = imagecreatetruecolor($cw, $ch);
        if ($low === false) {
            return;
        }
        for ($j = 0; $j < $ch; $j++) {
            $base = ($j0 + $j) * $lw + $i0;
            for ($i = 0; $i < $cw; $i++) {
                $k = $base + $i;
                imagesetpixel($low, $i, $j, (self::channel($red[$k]) << 16) | (self::channel($green[$k]) << 8) | self::channel($blue[$k]));
            }
        }
        $ratio = $step * $this->scale;
        $up = imagescale($low, $cw * $ratio, $ch * $ratio, IMG_BILINEAR_FIXED);
        imagedestroy($low);
        if ($up === false) {
            return;
        }
        $ox = $i0 * $ratio;
        $oy = $j0 * $ratio;
        if ($mask === null) {
            imagecopy($this->im, $up, $ox, $oy, 0, 0, min(imagesx($up), $this->width * $this->scale - $ox), min(imagesy($up), $this->height * $this->scale - $oy));
        } else {
            [$mx, $my, $mw, $mh, $mr] = $mask;
            $bottom = $this->py($my + $mh) - 1;
            for ($row = $this->py($my); $row <= $bottom; $row++) {
                $yy = ($row + 0.5) / $this->scale;
                $dy = min($yy - $my, $my + $mh - $yy);
                $inset = $dy < $mr ? $mr - sqrt(max(0.0, $mr * $mr - ($mr - $dy) ** 2)) : 0.0;
                $xl = $this->px($mx + $inset);
                $xr = $this->px($mx + $mw - $inset) - 1;
                if ($xr >= $xl) {
                    imagecopy($this->im, $up, $xl, $row, $xl - $ox, $row - $oy, $xr - $xl + 1, 1);
                }
            }
        }
        imagedestroy($up);
    }

    public static function blur(array $plane, int $w, int $h, float $sigma): array
    {
        $r = max(1, (int) round((sqrt(4 * $sigma * $sigma + 1) - 1) / 2));
        for ($pass = 0; $pass < 3; $pass++) {
            $plane = self::boxPass($plane, $w, $h, $r, 1, $w);
            $plane = self::boxPass($plane, $h, $w, $r, $w, 1);
        }
        return $plane;
    }

    private static function boxPass(array $src, int $len, int $lines, int $r, int $step, int $lineStep): array
    {
        $out = $src;
        $norm = 1.0 / (2 * $r + 1);
        for ($line = 0; $line < $lines; $line++) {
            $base = $line * $lineStep;
            $sum = 0.0;
            for ($k = -$r; $k <= $r; $k++) {
                $sum += $src[$base + max(0, min($len - 1, $k)) * $step];
            }
            for ($i = 0; $i < $len; $i++) {
                $out[$base + $i * $step] = $sum * $norm;
                $sum += $src[$base + min($len - 1, $i + $r + 1) * $step] - $src[$base + max(0, $i - $r) * $step];
            }
        }
        return $out;
    }

    private static function roundRectPath(float $x, float $y, float $w, float $h, float $r, int $seg = 16): array
    {
        $r = max(0.0, min($r, $w / 2, $h / 2));
        $pts = [];
        $corners = [
            [$x + $w - $r, $y + $r, -M_PI / 2],
            [$x + $w - $r, $y + $h - $r, 0.0],
            [$x + $r, $y + $h - $r, M_PI / 2],
            [$x + $r, $y + $r, M_PI],
        ];
        foreach ($corners as [$cx, $cy, $a0]) {
            for ($i = 0; $i <= $seg; $i++) {
                $a = $a0 + (M_PI / 2) * ($i / $seg);
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
        }
        return $pts;
    }

    public function roundRect(float $x, float $y, float $w, float $h, float $r, array $rgb, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $this->filledPolygon(self::roundRectPath($x, $y, $w, $h, $r), $rgb, $opacity);
    }

    public function ring(float $x, float $y, float $w, float $h, float $r, float $width, array $rgb, callable $alphaAt): void
    {
        $pts = self::roundRectPath($x, $y, $w, $h, $r, 24);
        $pts[] = $pts[0];
        $stroke = max(1.0, $width * $this->scale);
        $n = count($pts);
        for ($i = 0; $i < $n - 1; $i++) {
            [$x1, $y1] = $pts[$i];
            [$x2, $y2] = $pts[$i + 1];
            $parts = max(1, (int) ceil(hypot($x2 - $x1, $y2 - $y1) / 6.0));
            for ($j = 0; $j < $parts; $j++) {
                $ax = $x1 + ($x2 - $x1) * $j / $parts;
                $ay = $y1 + ($y2 - $y1) * $j / $parts;
                $bx = $x1 + ($x2 - $x1) * ($j + 1) / $parts;
                $by = $y1 + ($y2 - $y1) * ($j + 1) / $parts;
                $a = (float) $alphaAt(($ax + $bx) / 2, ($ay + $by) / 2);
                if ($a >= 0.004) {
                    $this->strokePath([[$this->fx($ax), $this->fy($ay)], [$this->fx($bx), $this->fy($by)]], $stroke, $this->color($rgb, $a), false);
                }
            }
        }
    }

    public function filledPolygon(array $points, array $rgb, float $opacity = 1.0): void
    {
        if (count($points) < 3) {
            return;
        }
        $pts = [];
        foreach ($points as $p) {
            $pts[] = $this->px($p[0]);
            $pts[] = $this->py($p[1]);
        }
        imagefilledpolygon($this->im, $pts, $this->color($rgb, $opacity));
    }

    private function strokePath(array $pts, float $width, int $color, bool $caps = true): void
    {
        $half = $width / 2;
        $n = count($pts);
        for ($i = 0; $i < $n - 1; $i++) {
            [$x1, $y1] = $pts[$i];
            [$x2, $y2] = $pts[$i + 1];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $len = sqrt($dx * $dx + $dy * $dy);
            if ($len < 0.0001) {
                continue;
            }
            $nx = -$dy / $len * $half;
            $ny = $dx / $len * $half;
            imagefilledpolygon($this->im, [
                (int) round($x1 + $nx), (int) round($y1 + $ny),
                (int) round($x2 + $nx), (int) round($y2 + $ny),
                (int) round($x2 - $nx), (int) round($y2 - $ny),
                (int) round($x1 - $nx), (int) round($y1 - $ny),
            ], $color);
        }
        if (!$caps) {
            return;
        }
        $d = (int) round($width);
        foreach ($pts as [$px, $py]) {
            imagefilledellipse($this->im, (int) round($px), (int) round($py), $d, $d, $color);
        }
    }

    public function image(string $path, float $x, float $y, float $boxW, float $boxH): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }
        $src = self::loadImage($path);
        if ($src === false) {
            return false;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw < 1 || $sh < 1) {
            imagedestroy($src);
            return false;
        }
        $s = $this->scale;
        $ratio = min(($boxW * $s) / $sw, ($boxH * $s) / $sh);
        $dw = max(1, (int) round($sw * $ratio));
        $dh = max(1, (int) round($sh * $ratio));
        $dx = (int) round($this->fx($x));
        $dy = (int) round($this->fy($y) + (($boxH * $s) - $dh) / 2);
        imagealphablending($this->im, true);
        imagecopyresampled($this->im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
        return true;
    }

    private static function loadImage(string $path)
    {
        $info = @getimagesize($path);
        $type = is_array($info) ? ($info[2] ?? null) : null;
        $im = match ($type) {
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            IMAGETYPE_GIF => function_exists('imagecreatefromgif') ? @imagecreatefromgif($path) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($im === false) {
            return false;
        }
        imagealphablending($im, true);
        imagesavealpha($im, true);
        return $im;
    }

    private static function ttfBox(string $font, string $text): array
    {
        $box = @imagettfbbox(75.0, 0, $font, $text);
        return $box === false ? [0, 0, 0, 0, 0, 0, 0, 0] : $box;
    }

    private static function capRatio(string $font): float
    {
        if (!isset(self::$metrics[$font]['cap'])) {
            $box = self::ttfBox($font, 'H');
            $h = abs($box[7] - $box[1]);
            self::$metrics[$font]['cap'] = $h > 0 ? $h / 100.0 : 0.72;
        }
        return self::$metrics[$font]['cap'];
    }

    private static function advance(string $font, string $text): float
    {
        $key = 'a:' . $text;
        if (!isset(self::$metrics[$font][$key])) {
            $a = self::ttfBox($font, 'H' . $text . 'H');
            $b = self::ttfBox($font, 'HH');
            self::$metrics[$font][$key] = max(0.0, (($a[2] - $a[0]) - ($b[2] - $b[0])) / 100.0);
        }
        return self::$metrics[$font][$key];
    }

    public function capHeight(float $size, string $role, string $text = 'H'): float
    {
        $font = CardConfig::fontFor($role, $text);
        return $font === null ? $size * 0.72 : $size * self::capRatio($font);
    }

    public function measure(string $text, float $size, string $role, float $tracking = 0.0): float
    {
        $font = CardConfig::fontFor($role, $text);
        if ($font === null) {
            $cap = $size * 0.72;
            $keys = VectorFont::keys($text);
            $w = 0.0;
            foreach ($keys as $key) {
                $w += VectorFont::glyph($key)[0] * $cap / 10;
            }
            return $w + max(0, count($keys) - 1) * (VectorFont::TRACKING * $cap / 10 + $tracking * $size);
        }
        $shaped = PersianShaper::shape($text);
        if (abs($tracking) < 0.0001 || PersianShaper::containsPersian($text)) {
            return self::advance($font, $shaped) * $size;
        }
        $chars = mb_str_split($shaped);
        $w = 0.0;
        foreach ($chars as $ch) {
            $w += self::advance($font, $ch) * $size;
        }
        return $w + $tracking * $size * max(0, count($chars) - 1);
    }

    public function fit(string $text, float $size, float $maxWidth, string $role, float $tracking = 0.0, float $minSize = 16.0): float
    {
        while ($size > $minSize && $this->measure($text, $size, $role, $tracking) > $maxWidth) {
            $size -= 2.0;
        }
        return max($minSize, $size);
    }

    public function write(string $text, float $x, float $capTop, float $size, array $rgb, string $role, string $align = 'left', float $tracking = 0.0, float $opacity = 1.0): float
    {
        $width = $this->measure($text, $size, $role, $tracking);
        $start = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };
        $font = CardConfig::fontFor($role, $text);
        $color = $this->color($rgb, $opacity);
        if ($font === null) {
            $cap = $size * 0.72;
            $this->drawVector($text, $start, $capTop, $cap, VectorFont::TRACKING * $cap / 10 + $tracking * $size, $color);
            return $width;
        }
        $shaped = PersianShaper::shape($text);
        $baseline = $this->py($capTop + $size * self::capRatio($font));
        $pt = $size * 0.75 * $this->scale;
        if (abs($tracking) < 0.0001 || PersianShaper::containsPersian($text)) {
            @imagettftext($this->im, $pt, 0, $this->px($start), $baseline, $color, $font, $shaped);
            return $width;
        }
        $pen = $start;
        foreach (mb_str_split($shaped) as $ch) {
            @imagettftext($this->im, $pt, 0, $this->px($pen), $baseline, $color, $font, $ch);
            $pen += self::advance($font, $ch) * $size + $tracking * $size;
        }
        return $width;
    }

    private function drawVector(string $text, float $x, float $capTop, float $cap, float $gap, int $color): void
    {
        $unit = ($cap / 10) * $this->scale;
        $stroke = max(1.0, $cap * 0.14 * $this->scale);
        $penX = $this->fx($x);
        $originY = $this->fy($capTop);
        foreach (VectorFont::keys($text) as $key) {
            [$adv, $strokes] = VectorFont::glyph($key);
            foreach ($strokes as $path) {
                $pts = [];
                foreach ($path as $p) {
                    $pts[] = [$penX + $p[0] * $unit, $originY + $p[1] * $unit];
                }
                $this->strokePath($pts, $stroke, $color);
            }
            $penX += $adv * $unit + $gap * $this->scale;
        }
    }

    public function toPng(): string
    {
        $out = $this->im;
        if ($this->scale > 1) {
            $scaled = imagecreatetruecolor($this->width, $this->height);
            if ($scaled !== false) {
                imagecopyresampled($scaled, $this->im, 0, 0, 0, 0, $this->width, $this->height, $this->width * $this->scale, $this->height * $this->scale);
                $out = $scaled;
            }
        }
        ob_start();
        imagepng($out, null, 6);
        $png = (string) ob_get_clean();
        if ($out !== $this->im) {
            imagedestroy($out);
        }
        return $png;
    }
}


final class CardConfig
{
    private const FONTS = [
        'heavy' => 'Manrope-ExtraBold.ttf',
        'bold' => 'Manrope-Bold.ttf',
        'semi' => 'Manrope-SemiBold.ttf',
    ];

    private static array $fontCache = [];

    private static function env(string $key, ?string $default = null): ?string
    {
        return class_exists('Env') ? Env::get($key, $default) : $default;
    }

    public static function enabled(): bool
    {
        $v = strtolower((string) (self::env('SIGNAL_CARD_ENABLED', 'true') ?? 'true'));
        return self::available() && in_array($v, ['1', 'true', 'yes', 'on'], true);
    }

    public static function available(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagefilledpolygon')
            && function_exists('imagescale')
            && function_exists('imagepng');
    }

    public static function renderScale(): int
    {
        $v = (int) (self::env('CARD_RENDER_SCALE', '2') ?? '2');
        return max(1, min(4, $v ?: 2));
    }

    private static function ttfSupported(): bool
    {
        return function_exists('imagettftext') && function_exists('imagettfbbox');
    }

    public static function fontPath(bool $bold = false): ?string
    {
        $key = $bold ? 'bold' : 'regular';
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }

        if (!self::ttfSupported()) {
            return self::$fontCache[$key] = null;
        }

        $candidates = $bold
            ? [self::env('CARD_FONT_PATH_BOLD', ''), __DIR__ . '/Vazirmatn-Bold.ttf', __DIR__ . '/fonts/Vazirmatn-Bold.ttf']
            : [self::env('CARD_FONT_PATH', ''), __DIR__ . '/Vazirmatn-Regular.ttf', __DIR__ . '/fonts/Vazirmatn-Regular.ttf'];

        foreach ($candidates as $path) {
            $path = (string) ($path ?? '');
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return self::$fontCache[$key] = $path;
            }
        }

        return self::$fontCache[$key] = ($bold ? self::fontPath(false) : null);
    }

    public static function font(string $role): ?string
    {
        $key = 'role:' . $role;
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }
        if (!self::ttfSupported()) {
            return self::$fontCache[$key] = null;
        }
        $file = self::FONTS[$role] ?? self::FONTS['bold'];
        foreach ([__DIR__ . '/' . $file, __DIR__ . '/fonts/' . $file] as $path) {
            if (is_file($path) && is_readable($path)) {
                return self::$fontCache[$key] = $path;
            }
        }
        return self::$fontCache[$key] = self::fontPath(true);
    }

    public static function fontFor(string $role, string $text): ?string
    {
        return PersianShaper::containsPersian($text) ? self::fontPath(true) : self::font($role);
    }

    public static function hasCardFonts(): bool
    {
        foreach (self::FONTS as $role => $file) {
            $path = self::font($role);
            if ($path === null || basename($path) !== $file) {
                return false;
            }
        }
        return true;
    }

    public static function supportsPersian(): bool
    {
        return self::fontPath() !== null;
    }

    public static function logoPath(): ?string
    {
        $candidates = [self::env('CARD_LOGO_PATH', '')];

        foreach (['png', 'PNG', 'jpg', 'jpeg', 'webp', 'gif'] as $ext) {
            $candidates[] = __DIR__ . '/logo.' . $ext;
        }
        foreach ($candidates as $path) {
            $path = (string) ($path ?? '');
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function brand(): string
    {
        return (string) (self::env('CARD_BRAND', 'AUTO TRADE MARKET') ?? 'AUTO TRADE MARKET');
    }
}


final class CardLayout
{
    public const W = 1600;
    public const H = 900;

    private const STEP = 8;
    private const GLASS = [240.0, 130.0, 1120.0, 640.0, 48.0];
    private const LEFT = 312.0;
    private const RIGHT = 1288.0;
    private const TOP_ROW_Y = 222.0;
    private const LABEL_TOP = 334.0;
    private const HERO_TOP = 401.0;
    private const HERO_SIZE = 150.0;
    private const CELL_LABEL_TOP = 628.0;
    private const CELL_VALUE_TOP = 669.0;
    private const FOOTER_TOP = 836.0;

    public static function begin(string $tone): CardCanvas
    {
        [$orbA, $orbB] = CardPalette::ORBS[$tone] ?? CardPalette::ORBS['flat'];
        [$gx, $gy, $gw, $gh, $gr] = self::GLASS;
        $lw = intdiv(self::W, self::STEP) + 1;
        $lh = intdiv(self::H, self::STEP) + 1;
        $outside = [[], [], []];
        $backdrop = [[], [], []];
        for ($j = 0; $j < $lh; $j++) {
            $y = (float) ($j * self::STEP);
            for ($i = 0; $i < $lw; $i++) {
                $x = (float) ($i * self::STEP);
                $rgb = CardCanvas::mix($orbA, CardPalette::BASE, self::orb($x, $y, 150.0, 60.0, 440.0, 0.35));
                $rgb = CardCanvas::mix($orbB, $rgb, self::orb($x, $y, 1060.0, 470.0, 460.0, 0.60));
                $shade = 1.0 - 0.45 / (1.0 + exp(1.702 * self::roundedDistance($x, $y - 40.0, $gx, $gy, $gw, $gh, $gr) / 40.0));
                $backdrop[0][] = $rgb[0];
                $backdrop[1][] = $rgb[1];
                $backdrop[2][] = $rgb[2];
                $outside[0][] = $rgb[0] * $shade;
                $outside[1][] = $rgb[1] * $shade;
                $outside[2][] = $rgb[2] * $shade;
            }
        }

        $c = new CardCanvas(self::W, self::H);
        $c->blitField($outside, $lw, $lh, self::STEP);
        $c->blitField(self::frost($backdrop, $lw, $lh), $lw, $lh, self::STEP, self::GLASS);
        self::rim($c);
        return $c;
    }

    public static function finish(CardCanvas $c): string
    {
        $png = $c->toPng();
        $c->destroy();
        return $png;
    }

    private static function orb(float $x, float $y, float $left, float $top, float $size, float $focus): float
    {
        $radius = $size / 2;
        $ray = max($focus, 1.0 - $focus) * M_SQRT2 * $size;
        $t = hypot($x - ($left + $size * $focus), $y - ($top + $size * $focus)) / $ray;
        $fill = $t <= 0.55 ? 1.0 - 0.2 * $t / 0.55 : max(0.0, 0.8 * (1.0 - ($t - 0.55) / 0.17));
        $edge = hypot($x - ($left + $radius), $y - ($top + $radius)) - $radius;
        return $fill / (1.0 + exp($edge / 3.5));
    }

    private static function roundedDistance(float $px, float $py, float $x, float $y, float $w, float $h, float $r): float
    {
        $qx = abs($px - ($x + $w / 2)) - ($w / 2 - $r);
        $qy = abs($py - ($y + $h / 2)) - ($h / 2 - $r);
        return hypot(max($qx, 0.0), max($qy, 0.0)) + min(max($qx, $qy), 0.0) - $r;
    }

    private static function frost(array $field, int $lw, int $lh): array
    {
        $sigma = 64.0 / self::STEP;
        $red = CardCanvas::blur($field[0], $lw, $lh, $sigma);
        $green = CardCanvas::blur($field[1], $lw, $lh, $sigma);
        $blue = CardCanvas::blur($field[2], $lw, $lh, $sigma);
        [, $gy, , $gh] = self::GLASS;
        $s = 1.2;
        $bright = 0.62;
        $n = $lw * $lh;
        for ($k = 0; $k < $n; $k++) {
            $r = $red[$k];
            $g = $green[$k];
            $b = $blue[$k];
            $r2 = ((0.213 + 0.787 * $s) * $r + (0.715 - 0.715 * $s) * $g + (0.072 - 0.072 * $s) * $b) * $bright;
            $g2 = ((0.213 - 0.213 * $s) * $r + (0.715 + 0.285 * $s) * $g + (0.072 - 0.072 * $s) * $b) * $bright;
            $b2 = ((0.213 - 0.213 * $s) * $r + (0.715 - 0.715 * $s) * $g + (0.072 + 0.928 * $s) * $b) * $bright;
            $t = max(0.0, min(1.0, (intdiv($k, $lw) * self::STEP - $gy) / $gh));
            $sheen = 0.08 - 0.05 * $t;
            $red[$k] = $r2 + (255.0 - $r2) * $sheen;
            $green[$k] = $g2 + (255.0 - $g2) * $sheen;
            $blue[$k] = $b2 + (255.0 - $b2) * $sheen;
        }
        return [$red, $green, $blue];
    }

    private static function rim(CardCanvas $c): void
    {
        [$x, $y, $w, $h, $r] = self::GLASS;
        $c->ring($x + 0.5, $y + 0.5, $w - 1.0, $h - 1.0, $r - 0.5, 1.0, CardPalette::WHITE, static fn(float $px, float $py): float => 0.35 * max(0.0, 1.0 - ($py - $y - 0.5) / 14.0));
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        $len = ($w + $h) * M_SQRT1_2;
        $stops = [[0.0, 0.55], [0.45, 0.08], [0.70, 0.04], [1.0, 0.25]];
        $c->ring($x + 0.75, $y + 0.75, $w - 1.5, $h - 1.5, $r - 0.75, 1.5, CardPalette::WHITE, static function (float $px, float $py) use ($cx, $cy, $len, $stops): float {
            $t = max(0.0, min(1.0, (($px - $cx) + ($py - $cy)) * M_SQRT1_2 / $len + 0.5));
            for ($i = 1; $i < count($stops); $i++) {
                if ($t <= $stops[$i][0]) {
                    $k = ($t - $stops[$i - 1][0]) / ($stops[$i][0] - $stops[$i - 1][0]);
                    return $stops[$i - 1][1] + ($stops[$i][1] - $stops[$i - 1][1]) * $k;
                }
            }
            return $stops[count($stops) - 1][1];
        });
    }

    public static function topRow(CardCanvas $c, string $pair, bool $isLong, string $leverage): void
    {
        $cy = self::TOP_ROW_Y;
        $pills = [[$isLong ? 'LONG' : 'SHORT', $isLong ? 'long' : 'short']];
        if ($leverage !== '') {
            $pills[] = [$leverage, 'plain'];
        }
        $x = self::RIGHT;
        foreach (array_reverse($pills) as [$text, $style]) {
            [$fill, $fillAlpha, $line, $lineAlpha, $ink] = CardPalette::PILLS[$style];
            $w = 43.0 + $c->measure($text, 20.0, 'bold', 0.06) + 1.2;
            $x -= $w;
            $c->roundRect($x, $cy - 23.0, $w, 46.0, 23.0, $fill, $fillAlpha);
            $c->ring($x + 0.75, $cy - 22.25, $w - 1.5, 44.5, 22.25, 1.5, $line, static fn(): float => $lineAlpha);
            $c->write($text, $x + 21.5, $cy - $c->capHeight(20.0, 'bold', $text) / 2, 20.0, $ink, 'bold', 'left', 0.06);
            $x -= 10.0;
        }
        $size = $c->fit($pair, 56.0, $x - 24.0 - self::LEFT, 'heavy', -0.02, 28.0);
        $c->write($pair, self::LEFT, $cy - $c->capHeight($size, 'heavy', $pair) / 2, $size, CardPalette::WHITE, 'heavy', 'left', -0.02);
    }

    public static function center(CardCanvas $c, string $label, array $labelRgb, float $labelAlpha, string $value, array $valueRgb): void
    {
        $c->write($label, self::W / 2, self::LABEL_TOP, 24.0, $labelRgb, 'semi', 'center', 0.02, $labelAlpha);
        $mid = self::HERO_TOP + $c->capHeight(self::HERO_SIZE, 'heavy') / 2;
        $size = $c->fit($value, self::HERO_SIZE, self::RIGHT - self::LEFT, 'heavy', -0.03, 64.0);
        $c->write($value, self::W / 2, $mid - $c->capHeight($size, 'heavy', $value) / 2, $size, $valueRgb, 'heavy', 'center', -0.03);
    }

    public static function cells(CardCanvas $c, array $cells): void
    {
        $cells = array_values($cells);
        $colW = (self::RIGHT - self::LEFT) / max(1, count($cells));
        $valueMid = self::CELL_VALUE_TOP + $c->capHeight(44.0, 'bold') / 2;
        foreach ($cells as $i => [$label, $value, $rgb]) {
            $cx = self::LEFT + $colW * ($i + 0.5);
            $c->write($label, $cx, self::CELL_LABEL_TOP, 22.0, CardPalette::WHITE, 'semi', 'center', 0.0, 0.58);
            $size = $c->fit($value, 44.0, $colW - 24.0, 'bold', -0.01, 24.0);
            $c->write($value, $cx, $valueMid - $c->capHeight($size, 'bold', $value) / 2, $size, $rgb, 'bold', 'center', -0.01);
        }
    }

    public static function footer(CardCanvas $c, string $time): void
    {
        $time = trim($time);
        $logo = CardConfig::logoPath();
        if ($logo !== null) {
            $info = @getimagesize($logo);
            $ratio = is_array($info) && ($info[1] ?? 0) > 0 ? $info[0] / $info[1] : 1.0;
            $logoW = min(200.0, 30.0 * $ratio);
            $timeW = $time !== '' ? $c->measure($time, 20.0, 'semi', 0.14) : 0.0;
            $x = self::W / 2 - ($logoW + ($time !== '' ? 20.0 + $timeW : 0.0)) / 2;
            if ($c->image($logo, $x, self::FOOTER_TOP + 7.0 - 15.0, $logoW, 30.0)) {
                if ($time !== '') {
                    $c->write($time, $x + $logoW + 20.0, self::FOOTER_TOP, 20.0, CardPalette::WHITE, 'semi', 'left', 0.14, 0.42);
                }
                return;
            }
        }
        $parts = [strtoupper(CardConfig::brand())];
        if ($time !== '') {
            $parts[] = '·';
            $parts[] = $time;
        }
        $gap = 20.0 * 0.14 + $c->measure(' ', 20.0, 'semi');
        $widths = array_map(static fn(string $part): float => $c->measure($part, 20.0, 'semi', 0.14), $parts);
        $x = self::W / 2 - (array_sum($widths) + $gap * (count($parts) - 1)) / 2;
        foreach ($parts as $i => $part) {
            $c->write($part, $x, self::FOOTER_TOP, 20.0, CardPalette::WHITE, 'semi', 'left', 0.14, 0.42);
            $x += $widths[$i] + $gap;
        }
    }

    public static function pair(string $symbol): string
    {
        $symbol = strtoupper(trim($symbol));
        foreach (['-', '_'] as $sep) {
            $symbol = str_replace($sep, '/', $symbol);
        }
        if (!str_contains($symbol, '/')) {
            foreach (['USDT', 'USDC', 'BUSD', 'FDUSD'] as $q) {
                if (str_ends_with($symbol, $q) && strlen($symbol) > strlen($q)) {
                    return substr($symbol, 0, -strlen($q)) . '/' . $q;
                }
            }
        }
        return $symbol;
    }
}

final class SignalCard
{
    public static function render(array $d): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
            $c = CardLayout::begin($isLong ? 'long' : 'short');
            CardLayout::topRow($c, CardLayout::pair((string) ($d['symbol'] ?? '')), $isLong, strtoupper(trim((string) ($d['leverage'] ?? ''))));
            CardLayout::center($c, 'Entry price', CardPalette::WHITE, 0.62, CardFormat::orNA($d['entry'] ?? null), CardPalette::WHITE);
            CardLayout::cells($c, [
                ['Stop loss', CardFormat::orNA($d['sl'] ?? null), CardPalette::RED_SOFT],
                ['Target 1', CardFormat::orNA($d['tp1'] ?? null), CardPalette::GREEN_SOFT],
                ['Target 2', CardFormat::orNA($d['tp2'] ?? null), CardPalette::GREEN_SOFT],
            ]);
            CardLayout::footer($c, (string) ($d['time'] ?? ''));
            return CardLayout::finish($c);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'signal card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}

final class ResultCard
{
    private const STATUS = [
        'tp1' => 'Profit shot · Target 1',
        'tp2' => 'Profit shot · Target 2',
        'tp3' => 'Profit shot · Target 3',
        'tp4' => 'Profit shot · All targets',
        'trail' => 'Profit shot · Profit locked',
        'be' => 'Break even · Risk free',
        'sl' => 'Stop loss',
        'timeout' => 'Time limit',
    ];

    public static function render(array $d): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $kind = strtolower((string) ($d['kind'] ?? 'tp1'));
            $headline = trim((string) ($d['headline'] ?? ''));
            $headline = $headline !== '' ? $headline : '0.00%';
            $pnl = CardFormat::number($headline) ?? 0.0;
            $tone = $pnl > 0.004 ? 'profit' : ($pnl < -0.004 || $kind === 'sl' ? 'loss' : 'flat');
            $status = self::STATUS[$kind] ?? 'Trade closed';
            if ($tone === 'loss' && str_starts_with($status, 'Profit shot')) {
                $status = 'Trade closed';
            }
            [$accent, $labelAlpha] = match ($tone) {
                'profit' => [CardPalette::GREEN_SOFT, 1.0],
                'loss' => [CardPalette::RED_SOFT, 1.0],
                default => [CardPalette::WHITE, 0.62],
            };

            $isLong = strtoupper((string) ($d['direction'] ?? '')) !== 'SHORT';
            $c = CardLayout::begin($tone);
            CardLayout::topRow($c, CardLayout::pair((string) ($d['symbol'] ?? '')), $isLong, strtoupper(trim((string) ($d['leverage'] ?? ''))));
            CardLayout::center($c, $status, $accent, $labelAlpha, CardFormat::signed($headline), $accent);
            CardLayout::cells($c, [
                ['Entry', CardFormat::orNA($d['entry'] ?? null), CardPalette::WHITE],
                ['Exit', CardFormat::orNA($d['exit'] ?? null), CardPalette::WHITE],
                ['Duration', CardFormat::duration(CardFormat::orNA($d['duration'] ?? null)), CardPalette::WHITE],
            ]);
            CardLayout::footer($c, (string) ($d['time'] ?? ''));
            return CardLayout::finish($c);
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'result card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}

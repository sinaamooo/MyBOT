<?php

declare(strict_types=1);

namespace App\Render;

/**
 * GD draws glyphs left-to-right without Arabic shaping, so Persian text has to be
 * converted to presentation forms (connected letters) and reordered visually first.
 */
final class Persian
{
    // char => [isolated, final, initial, medial]; two forms = joins only to the previous letter
    private const FORMS = [
        0x0621 => [0xFE80],
        0x0622 => [0xFE81, 0xFE82],
        0x0623 => [0xFE83, 0xFE84],
        0x0624 => [0xFE85, 0xFE86],
        0x0625 => [0xFE87, 0xFE88],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA],
        0x0630 => [0xFEAB, 0xFEAC],
        0x0631 => [0xFEAD, 0xFEAE],
        0x0632 => [0xFEAF, 0xFEB0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE],
        0x0649 => [0xFEEF, 0xFEF0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0698 => [0xFB8A, 0xFB8B],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
    ];

    // lam + alef variants => [isolated, final]
    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    private const MIRROR = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{', '«' => '»', '»' => '«', '<' => '>', '>' => '<'];

    public static function hasRtl(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FEFF}]/u', $text);
    }

    /** Shapes and reorders a single line for left-to-right glyph drawing. */
    public static function visual(string $text): string
    {
        if (!self::hasRtl($text)) {
            return $text;
        }
        return self::reorder(self::shape($text));
    }

    private static function isTransparent(int $cp): bool
    {
        return ($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670;
    }

    private static function shape(string $text): string
    {
        $cps = array_values(unpack('N*', mb_convert_encoding($text, 'UCS-4BE', 'UTF-8')));
        $n = count($cps);
        $out = [];
        $joinsNext = static fn (int $cp): bool => isset(self::FORMS[$cp]) && count(self::FORMS[$cp]) === 4;

        for ($i = 0; $i < $n; $i++) {
            $cp = $cps[$i];
            if (!isset(self::FORMS[$cp])) {
                if ($cp !== 0x200C) { // ZWNJ only breaks joining
                    $out[] = $cp;
                }
                continue;
            }
            // previous non-transparent char
            $p = $i - 1;
            while ($p >= 0 && self::isTransparent($cps[$p])) {
                $p--;
            }
            $prevJoins = $p >= 0 && $joinsNext($cps[$p]);

            // lam-alef ligature
            if ($cp === 0x0644) {
                $q = $i + 1;
                while ($q < $n && self::isTransparent($cps[$q])) {
                    $q++;
                }
                if ($q < $n && isset(self::LAM_ALEF[$cps[$q]])) {
                    $out[] = self::LAM_ALEF[$cps[$q]][$prevJoins ? 1 : 0];
                    $i = $q;
                    continue;
                }
            }

            $q = $i + 1;
            while ($q < $n && self::isTransparent($cps[$q])) {
                $q++;
            }
            $nextJoinable = $q < $n && isset(self::FORMS[$cps[$q]]);
            $forms = self::FORMS[$cp];
            $dual = count($forms) === 4;
            if ($prevJoins && $nextJoinable && $dual) {
                $out[] = $forms[3];
            } elseif ($prevJoins && count($forms) > 1) {
                $out[] = $forms[1];
            } elseif ($nextJoinable && $dual) {
                $out[] = $forms[2];
            } else {
                $out[] = $forms[0];
            }
        }
        return mb_convert_encoding(pack('N*', ...$out), 'UTF-8', 'UCS-4BE');
    }

    /**
     * Simplified Unicode bidi for an RTL paragraph. Types: R (Persian), L (Latin), E (digits), N (neutral).
     * LTR runs (words, numbers like "98,450.20", "+2.4%", "BTC/USDT") keep their internal order.
     */
    private static function reorder(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $n = count($chars);
        $types = [];
        foreach ($chars as $ch) {
            if (preg_match('/[0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]/u', $ch)) {
                $types[] = 'E';
            } elseif (preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FEFF}]/u', $ch)) {
                $types[] = 'R';
            } elseif (preg_match('/[A-Za-z\x{00C0}-\x{024F}]/u', $ch)) {
                $types[] = 'L';
            } else {
                $types[] = 'N';
            }
        }

        // Number parts: separators between digits, %, and leading sign / currency.
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] !== 'N') {
                continue;
            }
            $c = $chars[$i];
            $prevE = $i > 0 && $types[$i - 1] === 'E';
            $nextE = $i + 1 < $n && $types[$i + 1] === 'E';
            if ($prevE && $nextE && in_array($c, [',', '.', ':', '/', '٫', '٬'], true)) {
                $types[$i] = 'E';
            } elseif ($prevE && in_array($c, ['%', '٪', 'x', '×'], true)) {
                $types[$i] = 'E';
            } elseif ($nextE && in_array($c, ['+', '-', '−', '$'], true) && ($i === 0 || $types[$i - 1] === 'N')) {
                $types[$i] = 'E';
            }
        }

        // Digits that follow Latin text belong to it (EMA200, RSI14).
        $strong = 'R';
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] === 'L' || $types[$i] === 'R') {
                $strong = $types[$i];
            } elseif ($types[$i] === 'E' && $strong === 'L') {
                $types[$i] = 'L';
            }
        }

        // Digits glued to a following Latin word belong to it too (4H, 1D).
        for ($i = $n - 2; $i >= 0; $i--) {
            if ($types[$i] === 'E' && $types[$i + 1] === 'L') {
                $types[$i] = 'L';
            }
        }

        // Neutrals take the direction of their neighbours when both agree (numbers count as R).
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] !== 'N') {
                continue;
            }
            $j = $i;
            while ($j < $n && $types[$j] === 'N') {
                $j++;
            }
            $left = $i > 0 ? $types[$i - 1] : 'R';
            $right = $j < $n ? $types[$j] : 'R';
            $dir = ($left === 'L' && $right === 'L') ? 'L' : 'R';
            for ($k = $i; $k < $j; $k++) {
                $types[$k] = $dir;
            }
            $i = $j - 1;
        }

        $runs = [];
        foreach ($chars as $i => $ch) {
            $t = $types[$i];
            if ($runs && $runs[count($runs) - 1][0] === $t) {
                $runs[count($runs) - 1][1][] = $ch;
            } else {
                $runs[] = [$t, [$ch]];
            }
        }
        $out = '';
        foreach (array_reverse($runs) as [$t, $cs]) {
            if ($t === 'R') {
                $cs = array_map(static fn ($c) => self::MIRROR[$c] ?? $c, array_reverse($cs));
            }
            $out .= implode('', $cs);
        }
        return $out;
    }
}

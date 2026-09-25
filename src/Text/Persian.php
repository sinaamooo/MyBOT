<?php
declare(strict_types=1);

namespace Nikto\Text;

final class Persian
{
    private const FORMS = [
        0x0621 => [0xFE80, 0, 0, 0],
        0x0622 => [0xFE81, 0xFE82, 0, 0],
        0x0623 => [0xFE83, 0xFE84, 0, 0],
        0x0624 => [0xFE85, 0xFE86, 0, 0],
        0x0625 => [0xFE87, 0xFE88, 0, 0],
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        0x0627 => [0xFE8D, 0xFE8E, 0, 0],
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x0629 => [0xFE93, 0xFE94, 0, 0],
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        0x062F => [0xFEA9, 0xFEAA, 0, 0],
        0x0630 => [0xFEAB, 0xFEAC, 0, 0],
        0x0631 => [0xFEAD, 0xFEAE, 0, 0],
        0x0632 => [0xFEAF, 0xFEB0, 0, 0],
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        0x0640 => [0x0640, 0x0640, 0x0640, 0x0640],
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        0x0648 => [0xFEED, 0xFEEE, 0, 0],
        0x0649 => [0xFEEF, 0xFEF0, 0, 0],
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        0x066E => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        0x0698 => [0xFB8A, 0xFB8B, 0, 0],
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],
        0x06C0 => [0xFBA4, 0xFBA5, 0, 0],
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
        0x06D2 => [0xFBAE, 0xFBAF, 0, 0],
    ];

    private const LAM_ALEF = [
        0x0622 => [0xFEF5, 0xFEF6],
        0x0623 => [0xFEF7, 0xFEF8],
        0x0625 => [0xFEF9, 0xFEFA],
        0x0627 => [0xFEFB, 0xFEFC],
    ];

    private const MIRROR = [
        '(' => ')', ')' => '(', '[' => ']', ']' => '[', '{' => '}', '}' => '{',
        '<' => '>', '>' => '<', '«' => '»', '»' => '«', '‹' => '›', '›' => '‹',
    ];

    private const FA_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    private const EN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    public static function normalize(string $text): string
    {
        return strtr($text, [
            'ي' => 'ی', 'ك' => 'ک', 'ﻻ' => 'لا',
            '٤' => '۴', '٥' => '۵', '٦' => '۶',
            "\u{200F}" => '', "\u{200E}" => '', "\u{FEFF}" => '',
        ]);
    }

    public static function faDigits(string $text): string
    {
        return str_replace(self::EN_DIGITS, self::FA_DIGITS, $text);
    }

    public static function enDigits(string $text): string
    {
        return str_replace(
            array_merge(self::FA_DIGITS, ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩']),
            array_merge(self::EN_DIGITS, self::EN_DIGITS),
            $text
        );
    }

    public static function hasRtl(string $text): bool
    {
        return (bool) preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $text);
    }

    public static function prepare(string $text, ?bool $rtlParagraph = null): string
    {
        if ($text === '') {
            return '';
        }
        $text = self::normalize($text);
        if (!self::hasRtl($text)) {
            return $text;
        }
        $rtlParagraph ??= true;

        $shaped = self::shape($text);
        return self::reorder($shaped, $rtlParagraph);
    }

    private static function shape(string $text): array
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);
        $codes = array_map(static fn ($c) => self::ord($c), $chars);

        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $cp = $codes[$i];

            if (self::isDiacritic($cp)) {
                if ($out !== []) {
                    $out[count($out) - 1]['marks'] .= $chars[$i];
                }
                continue;
            }
            if ($cp === 0x200C) {
                continue;
            }

            $prevCp = self::prevJoinable($codes, $i);
            $nextCp = self::nextJoinable($codes, $i);

            if ($cp === 0x0644 && $nextCp !== null && isset(self::LAM_ALEF[$nextCp])) {
                $joinPrev = $prevCp !== null && self::joinsForward($prevCp);
                $lig = self::LAM_ALEF[$nextCp][$joinPrev ? 1 : 0];
                $out[] = ['ch' => self::chr($lig), 'marks' => '', 'type' => 'R'];
                for ($j = $i + 1; $j < $n; $j++) {
                    if ($codes[$j] === $nextCp) {
                        $i = $j;
                        break;
                    }
                }
                continue;
            }

            if (isset(self::FORMS[$cp])) {
                $joinPrev = $prevCp !== null && self::joinsForward($prevCp);
                $joinNext = $nextCp !== null && isset(self::FORMS[$nextCp]);
                $forms = self::FORMS[$cp];

                if ($joinPrev && $joinNext && $forms[3]) {
                    $glyph = $forms[3];
                } elseif ($joinPrev && $forms[1]) {
                    $glyph = $forms[1];
                } elseif ($joinNext && $forms[2]) {
                    $glyph = $forms[2];
                } else {
                    $glyph = $forms[0];
                }
                $out[] = ['ch' => self::chr($glyph), 'marks' => '', 'type' => 'R'];
                continue;
            }

            $out[] = ['ch' => $chars[$i], 'marks' => '', 'type' => self::classify($cp)];
        }

        return $out;
    }

    private static function reorder(array $clusters, bool $rtlParagraph): string
    {
        if ($clusters === []) {
            return '';
        }
        $count = count($clusters);

        $types = array_column($clusters, 'type');

        $isNum = static fn (?string $t): bool => $t === 'N';
        foreach (['CS', 'ES', 'ET'] as $weak) {
            for ($i = 0; $i < $count; $i++) {
                if ($types[$i] !== $weak) {
                    continue;
                }
                $prev = $i > 0 ? $types[$i - 1] : null;
                $next = $i + 1 < $count ? $types[$i + 1] : null;
                $attach = match ($weak) {
                    'CS' => $isNum($prev) && $isNum($next),
                    'ES' => $isNum($next) || ($isNum($prev) && $isNum($next)),
                    default => $isNum($prev) || $isNum($next),
                };
                $types[$i] = $attach ? 'N' : 'X';
            }
        }

        $strong = static fn (string $t): bool => $t === 'R' || $t === 'L' || $t === 'N';

        for ($i = 0; $i < $count; $i++) {
            if ($types[$i] !== 'X') {
                continue;
            }
            $j = $i;
            while ($j < $count && $types[$j] === 'X') {
                $j++;
            }
            $before = null;
            for ($k = $i - 1; $k >= 0; $k--) {
                if ($strong($types[$k])) { $before = $types[$k]; break; }
            }
            $after = null;
            for ($k = $j; $k < $count; $k++) {
                if ($strong($types[$k])) { $after = $types[$k]; break; }
            }
            $norm = static fn (?string $t): ?string => $t === 'N' ? 'R' : $t;
            $b = $norm($before);
            $a = $norm($after);
            $resolved = ($b !== null && $b === $a) ? $b : ($rtlParagraph ? 'R' : 'L');
            for ($k = $i; $k < $j; $k++) {
                $types[$k] = $resolved;
            }
            $i = $j - 1;
        }

        $runs = [];
        $start = 0;
        for ($i = 1; $i <= $count; $i++) {
            $cur = $types[$i - 1] === 'N' ? 'L' : $types[$i - 1];
            $next = $i < $count ? ($types[$i] === 'N' ? 'L' : $types[$i]) : null;
            if ($next !== $cur) {
                $runs[] = ['dir' => $cur, 'from' => $start, 'to' => $i - 1];
                $start = $i;
            }
        }

        $ordered = $rtlParagraph ? array_reverse($runs) : $runs;
        $buffer = '';
        foreach ($ordered as $run) {
            $slice = array_slice($clusters, $run['from'], $run['to'] - $run['from'] + 1);
            $rtlRun = $run['dir'] === 'R';
            if ($rtlRun) {
                $slice = array_reverse($slice);
            }
            foreach ($slice as $cluster) {
                $ch = $cluster['ch'];
                if ($rtlRun && isset(self::MIRROR[$ch])) {
                    $ch = self::MIRROR[$ch];
                }
                $buffer .= $ch . $cluster['marks'];
            }
        }

        return $buffer;
    }

    private static function classify(int $cp): string
    {
        if ($cp >= 0x0030 && $cp <= 0x0039) return 'N';
        if ($cp >= 0x06F0 && $cp <= 0x06F9) return 'N';
        if ($cp >= 0x0660 && $cp <= 0x0669) return 'N';
        if ($cp === 0x002C || $cp === 0x002E || $cp === 0x003A || $cp === 0x002F
            || $cp === 0x066B || $cp === 0x066C) return 'CS';
        if ($cp === 0x002B || $cp === 0x002D || $cp === 0x2212) return 'ES';
        if ($cp === 0x0025 || $cp === 0x0024 || $cp === 0x00B0 || $cp === 0x066A
            || $cp === 0x20AC || $cp === 0x00A3) return 'ET';
        if ($cp >= 0x0041 && $cp <= 0x005A) return 'L';
        if ($cp >= 0x0061 && $cp <= 0x007A) return 'L';
        if ($cp >= 0x00C0 && $cp <= 0x024F) return 'L';
        if ($cp >= 0x0600 && $cp <= 0x06FF) return 'R';
        if ($cp >= 0xFB50 && $cp <= 0xFEFF) return 'R';
        return 'X';
    }

    private static function isDiacritic(int $cp): bool
    {
        return ($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670
            || ($cp >= 0x06D6 && $cp <= 0x06ED) || $cp === 0x0653 || $cp === 0x0654;
    }

    private static function joinsForward(int $cp): bool
    {
        return isset(self::FORMS[$cp]) && (self::FORMS[$cp][2] !== 0 || $cp === 0x0640);
    }

    private static function prevJoinable(array $codes, int $i): ?int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            $cp = $codes[$j];
            if (self::isDiacritic($cp)) {
                continue;
            }
            if ($cp === 0x200C) {
                return null;
            }
            return isset(self::FORMS[$cp]) ? $cp : null;
        }
        return null;
    }

    private static function nextJoinable(array $codes, int $i): ?int
    {
        $n = count($codes);
        for ($j = $i + 1; $j < $n; $j++) {
            $cp = $codes[$j];
            if (self::isDiacritic($cp)) {
                continue;
            }
            if ($cp === 0x200C) {
                return null;
            }
            return isset(self::FORMS[$cp]) ? $cp : null;
        }
        return null;
    }

    private static function ord(string $char): int
    {
        return mb_ord($char, 'UTF-8') ?: 0;
    }

    private static function chr(int $cp): string
    {
        return mb_chr($cp, 'UTF-8') ?: '';
    }
}

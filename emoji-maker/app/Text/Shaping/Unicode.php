<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Shaping;

/**
 * Compact Unicode property tables needed for Persian / Arabic / Latin text:
 * bidi classes, Arabic joining types, mirroring and presentation forms.
 * Data follows UnicodeData.txt / ArabicShaping.txt for the relevant blocks.
 */
final class Unicode
{
    // Bidi classes
    public const L = 'L';
    public const R = 'R';
    public const AL = 'AL';
    public const EN = 'EN';
    public const AN = 'AN';
    public const ES = 'ES';
    public const ET = 'ET';
    public const CS = 'CS';
    public const NSM = 'NSM';
    public const BN = 'BN';
    public const B = 'B';
    public const S = 'S';
    public const WS = 'WS';
    public const ON = 'ON';

    // Joining types
    public const JT_U = 'U'; // non joining
    public const JT_R = 'R'; // right joining (joins to previous letter only)
    public const JT_D = 'D'; // dual joining
    public const JT_C = 'C'; // join causing (tatweel, ZWJ)
    public const JT_T = 'T'; // transparent (marks)

    /** @return array<int,int> */
    public static function codepoints(string $text): array
    {
        $out = [];
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $out[] = mb_ord($ch, 'UTF-8');
        }
        return $out;
    }

    public static function isArabicScript(int $cp): bool
    {
        return ($cp >= 0x0600 && $cp <= 0x06FF) || ($cp >= 0x0750 && $cp <= 0x077F)
            || ($cp >= 0x08A0 && $cp <= 0x08FF) || ($cp >= 0xFB50 && $cp <= 0xFDFF)
            || ($cp >= 0xFE70 && $cp <= 0xFEFF);
    }

    public static function isMark(int $cp): bool
    {
        return ($cp >= 0x0300 && $cp <= 0x036F) || ($cp >= 0x0610 && $cp <= 0x061A)
            || ($cp >= 0x064B && $cp <= 0x065F) || $cp === 0x0670
            || ($cp >= 0x06D6 && $cp <= 0x06DC) || ($cp >= 0x06DF && $cp <= 0x06E4)
            || $cp === 0x06E7 || $cp === 0x06E8 || ($cp >= 0x06EA && $cp <= 0x06ED)
            || ($cp >= 0x08D3 && $cp <= 0x08FF && $cp !== 0x08E2)
            || ($cp >= 0x0591 && $cp <= 0x05BD) || ($cp >= 0x20D0 && $cp <= 0x20FF)
            || ($cp >= 0xFE20 && $cp <= 0xFE2F) || ($cp >= 0xFE00 && $cp <= 0xFE0F);
    }

    public static function bidiClass(int $cp): string
    {
        if ($cp === 0x0A || $cp === 0x0D || ($cp >= 0x1C && $cp <= 0x1E) || $cp === 0x85 || $cp === 0x2029) {
            return self::B;
        }
        if ($cp === 0x09 || $cp === 0x0B || $cp === 0x1F) {
            return self::S;
        }
        if ($cp === 0x20 || $cp === 0x0C || $cp === 0x1680 || ($cp >= 0x2000 && $cp <= 0x200A) || $cp === 0x2028 || $cp === 0x205F || $cp === 0x3000) {
            return self::WS;
        }
        if (($cp >= 0x30 && $cp <= 0x39) || ($cp >= 0x06F0 && $cp <= 0x06F9) || $cp === 0xB2 || $cp === 0xB3 || $cp === 0xB9
            || ($cp >= 0x2070 && $cp <= 0x2079) || ($cp >= 0x2080 && $cp <= 0x2089) || ($cp >= 0xFF10 && $cp <= 0xFF19)) {
            return self::EN;
        }
        if (($cp >= 0x0660 && $cp <= 0x0669) || $cp === 0x066B || $cp === 0x066C || ($cp >= 0x0600 && $cp <= 0x0605) || $cp === 0x06DD) {
            return self::AN;
        }
        if ($cp === 0x2B || $cp === 0x2D || $cp === 0x207A || $cp === 0x207B || $cp === 0x208A || $cp === 0x208B || $cp === 0x2212 || $cp === 0xFB29 || $cp === 0xFE62 || $cp === 0xFE63) {
            return self::ES;
        }
        if ($cp === 0x23 || $cp === 0x24 || $cp === 0x25 || ($cp >= 0xA2 && $cp <= 0xA5) || $cp === 0xB0 || $cp === 0xB1
            || $cp === 0x066A || ($cp >= 0x2030 && $cp <= 0x2034) || ($cp >= 0x20A0 && $cp <= 0x20CF) || $cp === 0x212E || $cp === 0x2213 || $cp === 0x060B) {
            return self::ET;
        }
        if ($cp === 0x2C || $cp === 0x2E || $cp === 0x2F || $cp === 0x3A || $cp === 0xA0 || $cp === 0x060C || $cp === 0x202F
            || $cp === 0x2044 || $cp === 0xFE50 || $cp === 0xFE52 || $cp === 0xFE55 || $cp === 0xFF0C || $cp === 0xFF0E || $cp === 0xFF0F || $cp === 0xFF1A) {
            return self::CS;
        }
        if (($cp >= 0x200B && $cp <= 0x200D) || $cp === 0xFEFF || $cp === 0xAD || ($cp >= 0x2060 && $cp <= 0x2064)
            || ($cp >= 0x202A && $cp <= 0x202E) || ($cp >= 0x2066 && $cp <= 0x2069) || $cp < 0x09 || ($cp > 0x0D && $cp < 0x1C) || ($cp >= 0x7F && $cp < 0xA0 && $cp !== 0x85)) {
            return self::BN;
        }
        if ($cp === 0x200E) {
            return self::L;
        }
        if ($cp === 0x200F) {
            return self::R;
        }
        if ($cp === 0x061C) {
            return self::AL;
        }
        if (self::isMark($cp)) {
            return self::NSM;
        }
        if (($cp >= 0x0590 && $cp <= 0x05FF) || ($cp >= 0x07C0 && $cp <= 0x085F) || ($cp >= 0xFB1D && $cp <= 0xFB4F)) {
            return self::R;
        }
        if (($cp >= 0x0600 && $cp <= 0x07BF) || ($cp >= 0x0860 && $cp <= 0x08FF) || ($cp >= 0xFB50 && $cp <= 0xFDCF)
            || ($cp >= 0xFDF0 && $cp <= 0xFDFF) || ($cp >= 0xFE70 && $cp <= 0xFEFE)) {
            return self::AL;
        }
        // Letters of other scripts are strong LTR; ASCII / general punctuation and symbols are neutral.
        if (($cp >= 0x41 && $cp <= 0x5A) || ($cp >= 0x61 && $cp <= 0x7A) || $cp === 0xAA || $cp === 0xB5 || $cp === 0xBA
            || ($cp >= 0xC0 && $cp <= 0x24F && $cp !== 0xD7 && $cp !== 0xF7) || ($cp >= 0x0370 && $cp <= 0x058F)
            || ($cp >= 0x0900 && $cp <= 0x1FFF) || ($cp >= 0x3040 && $cp <= 0xD7FF) || ($cp >= 0xF900 && $cp <= 0xFAFF)
            || ($cp >= 0xFF21 && $cp <= 0xFF3A) || ($cp >= 0xFF41 && $cp <= 0xFF5A)) {
            return self::L;
        }
        return self::ON;
    }

    public static function joiningType(int $cp): string
    {
        if ($cp === 0x0640 || $cp === 0x200D || $cp === 0x07FA) {
            return self::JT_C;
        }
        if (self::isMark($cp)) {
            return self::JT_T;
        }
        static $r = null, $d = null;
        if ($r === null) {
            $r = [];
            foreach ([[0x0622, 0x0625], [0x0627, 0x0627], [0x0629, 0x0629], [0x062F, 0x0632], [0x0648, 0x0648],
                [0x0671, 0x0673], [0x0675, 0x0677], [0x0688, 0x0699], [0x06C0, 0x06C0], [0x06C3, 0x06CB],
                [0x06CD, 0x06CD], [0x06CF, 0x06CF], [0x06D2, 0x06D3], [0x06D5, 0x06D5], [0x06EE, 0x06EF],
                [0x0759, 0x075B], [0x076B, 0x076C], [0x0771, 0x0771], [0x0773, 0x0774], [0x0778, 0x0779],
                [0x08AA, 0x08AC], [0x08AE, 0x08AE], [0x08B1, 0x08B2], [0x08B9, 0x08B9]] as [$a, $b]) {
                for ($c = $a; $c <= $b; $c++) {
                    $r[$c] = true;
                }
            }
            $d = [];
            foreach ([[0x0620, 0x0620], [0x0626, 0x0626], [0x0628, 0x0628], [0x062A, 0x062E], [0x0633, 0x063F],
                [0x0641, 0x0647], [0x0649, 0x064A], [0x066E, 0x066F], [0x0678, 0x0687], [0x069A, 0x06BF],
                [0x06C1, 0x06C2], [0x06CC, 0x06CC], [0x06CE, 0x06CE], [0x06D0, 0x06D1], [0x06FA, 0x06FC],
                [0x06FF, 0x06FF], [0x0750, 0x0758], [0x075C, 0x076A], [0x076D, 0x0770], [0x0772, 0x0772],
                [0x0775, 0x0777], [0x077A, 0x077F], [0x08A0, 0x08A9], [0x08AF, 0x08B0], [0x08B3, 0x08B8],
                [0x08BA, 0x08C7]] as [$a, $b]) {
                for ($c = $a; $c <= $b; $c++) {
                    $d[$c] = true;
                }
            }
        }
        if (isset($d[$cp])) {
            return self::JT_D;
        }
        if (isset($r[$cp])) {
            return self::JT_R;
        }
        return self::JT_U;
    }

    public static function mirror(int $cp): int
    {
        static $map = [
            0x28 => 0x29, 0x29 => 0x28, 0x3C => 0x3E, 0x3E => 0x3C, 0x5B => 0x5D, 0x5D => 0x5B,
            0x7B => 0x7D, 0x7D => 0x7B, 0xAB => 0xBB, 0xBB => 0xAB, 0x2039 => 0x203A, 0x203A => 0x2039,
            0x2045 => 0x2046, 0x2046 => 0x2045, 0x207D => 0x207E, 0x207E => 0x207D, 0x2264 => 0x2265, 0x2265 => 0x2264,
        ];
        return $map[$cp] ?? $cp;
    }

    /**
     * Arabic Presentation Forms (fallback shaping for fonts without GSUB).
     * cp => [isolated, final, initial, medial] (0 = form does not exist)
     * @return array<int,array{0:int,1:int,2:int,3:int}>
     */
    public static function presentationForms(): array
    {
        return [
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
            0x0649 => [0xFEEF, 0xFEF0, 0xFBE8, 0xFBE9],
            0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
            0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
            0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
            0x0698 => [0xFB8A, 0xFB8B, 0, 0],
            0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
            0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
            0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
            0x06C0 => [0xFBA4, 0xFBA5, 0, 0],
            0x0671 => [0xFB50, 0xFB51, 0, 0],
            0x06BE => [0xFBAA, 0xFBAB, 0xFBAC, 0xFBAD],
            0x06C1 => [0xFBA6, 0xFBA7, 0xFBA8, 0xFBA9],
            0x06D2 => [0xFBAE, 0xFBAF, 0, 0],
            0x0679 => [0xFB66, 0xFB67, 0xFB68, 0xFB69],
            0x0688 => [0xFB88, 0xFB89, 0, 0],
            0x0691 => [0xFB8C, 0xFB8D, 0, 0],
            0x06BA => [0xFB9E, 0xFB9F, 0, 0],
        ];
    }

    /**
     * Lam-Alef presentation ligatures: alef cp => [isolated, final]
     * @return array<int,array{0:int,1:int}>
     */
    public static function lamAlefForms(): array
    {
        return [
            0x0622 => [0xFEF5, 0xFEF6],
            0x0623 => [0xFEF7, 0xFEF8],
            0x0625 => [0xFEF9, 0xFEFA],
            0x0627 => [0xFEFB, 0xFEFC],
        ];
    }

    /** Persian digits (for the "Persian digits" option). */
    public static function toPersianDigits(string $s): string
    {
        return strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }
}

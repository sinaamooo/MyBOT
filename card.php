<?php

declare(strict_types=1);

/**
 * ============================================================================
 * card.php — Signal / profit "card" image rendering (pure GD, zero deps).
 *
 * Produces the PNG images the bot attaches to every message:
 *
 *   SignalCard::render()  the entry card  — symbol, direction, timeframe,
 *                         leverage, entry, stop loss, TP1, TP2, R:R.
 *   ResultCard::render()  the "profit shot" — leveraged PnL%, entry->exit,
 *                         and the RISK FREE / TP1 / TP2 / SL badge.
 *
 * Design: dark glassmorphism, green + yellow accent palette.
 *
 * Typography: the card ships its own built-in vector (stroke) font, so it
 * renders identically on any host with ext-gd and needs no font file at
 * all — which matters, because shared hosting rarely has a usable TTF at a
 * predictable path. Latin labels only, by design: the numbers and the
 * trading vocabulary (ENTRY / STOP / TARGET / LEVERAGE) are the parts that
 * belong on the image, and the full Persian wording travels in the message
 * caption where Telegram renders it natively.
 *
 * If you *do* want Persian directly on the card, drop a TTF next to the
 * project and point CARD_FONT_PATH at it: the TTF backend below takes over
 * and its Persian text goes through PersianShaper (contextual letter forms
 * + visual reordering), because GD/FreeType does no Arabic shaping itself.
 *
 * Everything degrades instead of failing: no ext-gd (or any rendering
 * error) means render() returns null and the caller simply sends the text
 * message on its own.
 * ============================================================================
 */

// ============================================================================
// SECTION 1 — PALETTE
// ============================================================================

final class CardPalette
{
    // Backdrop
    public const BG_TOP       = [10, 16, 13];
    public const BG_BOTTOM    = [4, 6, 5];

    // Accents — the green/yellow identity of every card
    public const GREEN        = [46, 231, 150];
    public const GREEN_DEEP   = [16, 142, 92];
    public const YELLOW       = [245, 209, 66];
    public const YELLOW_DEEP  = [168, 134, 18];
    public const AMBER        = [255, 168, 46];

    // Neutrals
    public const WHITE        = [255, 255, 255];
    public const TEXT         = [232, 245, 238];
    public const MUTED        = [163, 186, 175];
    public const GLASS        = [255, 255, 255];

    /** Direction-driven accent: LONG is green, SHORT is yellow — the card never leaves the palette. */
    public static function forDirection(string $direction): array
    {
        return strtoupper($direction) === 'SHORT' ? self::YELLOW : self::GREEN;
    }
}

// ============================================================================
// SECTION 2 — BUILT-IN VECTOR FONT
//
// Each glyph is a set of polylines in a 10-unit-tall box (y=0 cap top,
// y=10 baseline) plus an ink width; strokes are drawn as round-capped
// thick lines, so one definition serves every weight and size.
// ============================================================================

final class VectorFont
{
    /** Extra space between glyphs, in font units. */
    public const TRACKING = 2.0;

    /** @var array<string,array{0:float,1:array<int,array<int,array{0:float,1:float}>>}>|null */
    private static ?array $glyphs = null;

    /** @return array<string,array{0:float,1:array<int,array<int,array{0:float,1:float}>>}> */
    private static function glyphs(): array
    {
        if (self::$glyphs !== null) {
            return self::$glyphs;
        }

        // A tiny circle used by '%' and '°' — 8 points is plenty once the
        // canvas is supersampled and scaled back down.
        $ring = static function (float $cx, float $cy, float $r): array {
            $pts = [];
            for ($i = 0; $i <= 8; $i++) {
                $a = ($i / 8) * 2 * M_PI;
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            return $pts;
        };

        // A "dot" is a zero-length stroke: the round cap draws the dot.
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

    /**
     * Splits a UTF-8 string into glyph keys, upper-casing Latin (the font
     * is caps-only by design) and dropping anything it has no glyph for,
     * so an unexpected character can never derail a whole card.
     *
     * @return string[]
     */
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

    /** @return array{0:float,1:array<int,array<int,array{0:float,1:float}>>} */
    public static function glyph(string $key): array
    {
        return self::glyphs()[$key] ?? [3.6, []];
    }
}

// ============================================================================
// SECTION 3 — PERSIAN SHAPER (only used by the optional TTF backend)
//
// FreeType draws the code points it is handed, in the order it is handed
// them — it performs no Arabic-script shaping and no bidi reordering. So
// Persian text passed straight to imagettftext() comes out disconnected
// and backwards. This maps each letter to its contextual form (isolated /
// initial / medial / final), applies the LAM-ALEF ligatures, and emits the
// run in visual order.
// ============================================================================

final class PersianShaper
{
    /**
     * char => [isolated, final, initial, medial]. A letter absent from this
     * table is non-joining on its left (it never connects to what follows).
     * @var array<string,array{0:string,1:string,2:string,3:string}>
     */
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

    /** Letters that never connect to the letter after them. */
    private const NON_CONNECTING = ['ا', 'آ', 'أ', 'إ', 'د', 'ذ', 'ر', 'ز', 'ژ', 'و', 'ء', 'ة'];

    /** LAM + ALEF pairs collapse into a single glyph. */
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

    /**
     * Returns the string in visual order with contextual forms applied,
     * ready to hand to imagettftext(). Latin/digit runs keep their own
     * left-to-right order inside the reversed line.
     */
    public static function shape(string $text): string
    {
        if (!self::containsPersian($text)) {
            return $text;
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        // Pass 1 — LAM-ALEF ligatures, before any form is chosen.
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

        // Pass 2 — contextual form per letter.
        $shaped = [];
        $count = count($merged);
        for ($i = 0; $i < $count; $i++) {
            $prevJoins = self::joinsForward($merged[$i - 1] ?? null);
            $item = $merged[$i];

            if (isset($item['lig'])) {
                // A ligature is ALEF-final, so it only ever takes the
                // isolated or final form, exactly like a bare ALEF.
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
                $prevJoins && $nextJoins && $selfJoins => 3, // medial
                $prevJoins && (!$nextJoins || !$selfJoins) => 1, // final
                !$prevJoins && $nextJoins && $selfJoins => 2, // initial
                default => 0, // isolated
            };
            $shaped[] = self::FORMS[$ch][$form];
        }

        return self::toVisualOrder($shaped);
    }

    /** True when the previous item can connect to the current one. */
    private static function joinsForward(?array $prev): bool
    {
        if ($prev === null) {
            return false;
        }
        if (isset($prev['lig'])) {
            return false; // ligature ends in ALEF — never joins forward
        }
        return isset(self::FORMS[$prev['ch']]) && !in_array($prev['ch'], self::NON_CONNECTING, true);
    }

    /** True when the next item is a letter that can accept a connection. */
    private static function joinsBackward(?array $next): bool
    {
        if ($next === null) {
            return false;
        }
        if (isset($next['lig'])) {
            return true; // starts with LAM
        }
        return isset(self::FORMS[$next['ch']]);
    }

    /**
     * Reverses the line for right-to-left display while keeping embedded
     * Latin/number runs readable (a naive strrev would print "150X" as
     * "X051"), and mirroring the brackets that end up flipped.
     *
     * @param string[] $shaped
     */
    private static function toVisualOrder(array $shaped): string
    {
        $mirror = ['(' => ')', ')' => '(', '[' => ']', ']' => '[', '<' => '>', '>' => '<'];
        $runs = [];
        $ltrRun = [];

        foreach ($shaped as $ch) {
            $isLtr = (bool) preg_match('/[0-9A-Za-z%\$\.,:\-\+\/]/u', $ch);
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

        // Trailing spaces on an LTR run belong on its other side once the
        // line is reversed, otherwise words visibly collide.
        return implode('', array_reverse($runs));
    }
}

// ============================================================================
// SECTION 4 — CANVAS
//
// Everything is drawn on a supersampled surface (SCALE x the final size)
// and scaled back down at the end. GD has no antialiasing for filled
// polygons, and every glyph here IS a filled polygon, so supersampling is
// what makes the type and the rounded corners come out smooth.
// ============================================================================

final class CardCanvas
{
    private \GdImage $im;
    private int $scale;
    /** @var array<string,int> */
    private array $colorCache = [];

    /** @var array<string,float> ttf font size (pt) that yields a 100px cap height, per font path */
    private static array $ttfCalibration = [];

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

    // -- colors ------------------------------------------------------------

    /** @param array{0:int,1:int,2:int} $rgb */
    private function color(array $rgb, float $opacity = 1.0): int
    {
        $alpha = (int) round((1.0 - max(0.0, min(1.0, $opacity))) * 127);
        $key = $rgb[0] . ',' . $rgb[1] . ',' . $rgb[2] . ',' . $alpha;
        if (isset($this->colorCache[$key])) {
            return $this->colorCache[$key];
        }
        $c = imagecolorallocatealpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
        return $this->colorCache[$key] = ($c === false ? 0 : $c);
    }

    /**
     * Blends two colors. Text is always drawn fully opaque (a translucent
     * stroke would double-darken everywhere the glyph's own segments and
     * round caps overlap), so "dimmer" text is a pre-mixed color, not alpha.
     *
     * @param array{0:int,1:int,2:int} $fg
     * @param array{0:int,1:int,2:int} $bg
     * @return array{0:int,1:int,2:int}
     */
    public static function mix(array $fg, array $bg, float $amount): array
    {
        $t = max(0.0, min(1.0, $amount));
        return [
            (int) round($fg[0] * $t + $bg[0] * (1 - $t)),
            (int) round($fg[1] * $t + $bg[1] * (1 - $t)),
            (int) round($fg[2] * $t + $bg[2] * (1 - $t)),
        ];
    }

    // -- background --------------------------------------------------------

    /**
     * Gradient + soft colour blooms, drawn at HALF the final card size and
     * scaled up onto the supersampled canvas.
     *
     * That indirection is the whole performance story of this file: the
     * blooms are stacked filled ellipses, and at 3x supersampling a single
     * one of them covers ~13 million pixels — three blooms took over five
     * seconds. The backdrop is smooth by construction, so upscaling it is
     * invisible, and the same three blooms now cost milliseconds.
     *
     * @param array{0:int,1:int,2:int} $top
     * @param array{0:int,1:int,2:int} $bottom
     * @param array<int,array{0:float,1:float,2:float,3:array{0:int,1:int,2:int},4:float}> $glows [cx, cy, radius, rgb, strength]
     */
    public function backdrop(array $top, array $bottom, array $glows): void
    {
        $bw = max(120, (int) round($this->width / 2));
        $bh = max(120, (int) round($this->height / 2));
        $bg = imagecreatetruecolor($bw, $bh);
        if ($bg === false) {
            return;
        }
        imagealphablending($bg, true);
        imagesavealpha($bg, false);

        for ($y = 0; $y < $bh; $y++) {
            $t = $bh > 1 ? $y / ($bh - 1) : 0.0;
            $m = self::mix($bottom, $top, $t);
            $c = imagecolorallocate($bg, $m[0], $m[1], $m[2]);
            imagefilledrectangle($bg, 0, $y, $bw, $y, $c === false ? 0 : $c);
        }

        $k = $bw / $this->width; // card space -> backdrop space
        foreach ($glows as [$cx, $cy, $radius, $rgb, $strength]) {
            $steps = 40;
            // Per-ring opacity chosen so that $steps overlapping rings
            // accumulate to $strength at the centre and fade out smoothly.
            $per = 1 - pow(1 - max(0.0, min(1.0, $strength)), 1 / $steps);
            $col = imagecolorallocatealpha($bg, $rgb[0], $rgb[1], $rgb[2], (int) round((1 - $per) * 127));
            if ($col === false) {
                continue;
            }
            for ($i = $steps; $i >= 1; $i--) {
                $d = (int) round($radius * ($i / $steps) * $k * 2);
                imagefilledellipse($bg, (int) round($cx * $k), (int) round($cy * $k), $d, $d, $col);
            }
        }

        imagecopyresampled(
            $this->im,
            $bg,
            0, 0, 0, 0,
            $this->width * $this->scale,
            $this->height * $this->scale,
            $bw,
            $bh
        );
        imagedestroy($bg);
    }

    // -- shapes ------------------------------------------------------------

    /** @return array<int,float> flat [x1,y1,x2,y2,...] device-space polygon for a rounded rectangle */
    private function roundRectPoints(float $x, float $y, float $w, float $h, float $r): array
    {
        $s = $this->scale;
        $r = max(0.0, min($r, min($w, $h) / 2));
        $x *= $s; $y *= $s; $w *= $s; $h *= $s; $r *= $s;

        $pts = [];
        $corners = [
            [$x + $w - $r, $y + $r, -M_PI / 2, 0.0],          // top-right
            [$x + $w - $r, $y + $h - $r, 0.0, M_PI / 2],      // bottom-right
            [$x + $r, $y + $h - $r, M_PI / 2, M_PI],          // bottom-left
            [$x + $r, $y + $r, M_PI, 3 * M_PI / 2],           // top-left
        ];
        $seg = 10;
        foreach ($corners as [$cx, $cy, $a0, $a1]) {
            for ($i = 0; $i <= $seg; $i++) {
                $a = $a0 + ($a1 - $a0) * ($i / $seg);
                $pts[] = $cx + cos($a) * $r;
                $pts[] = $cy + sin($a) * $r;
            }
        }
        return array_map(static fn($v) => (float) $v, $pts);
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    public function roundRect(float $x, float $y, float $w, float $h, float $r, array $rgb, float $opacity = 1.0): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $pts = array_map('intval', array_map('round', $this->roundRectPoints($x, $y, $w, $h, $r)));
        imagefilledpolygon($this->im, $pts, $this->color($rgb, $opacity));
    }

    /**
     * The glassmorphism primitive: a translucent light fill with a brighter
     * hairline border and a highlight along the top edge, so the panel
     * reads as a lit pane of glass sitting above the background glow.
     *
     * @param array{0:int,1:int,2:int} $tint
     */
    public function glassPanel(
        float $x,
        float $y,
        float $w,
        float $h,
        float $r,
        array $tint = CardPalette::GLASS,
        float $fillOpacity = 0.07,
        float $borderOpacity = 0.20,
        float $borderWidth = 1.6,
        bool $topHighlight = true,
    ): void {
        $this->roundRect($x, $y, $w, $h, $r, $tint, $borderOpacity);
        $this->roundRect(
            $x + $borderWidth,
            $y + $borderWidth,
            $w - 2 * $borderWidth,
            $h - 2 * $borderWidth,
            max(0.0, $r - $borderWidth),
            $tint,
            $fillOpacity
        );
        if ($topHighlight && $h > 12) {
            // A short, brighter band just inside the top edge — the classic
            // "light catching the rim" cue that sells the glass look.
            $inset = $borderWidth + 1.5;
            $this->roundRect($x + $r * 0.6, $y + $inset, max(0.0, $w - $r * 1.2), 1.4, 0.7, CardPalette::WHITE, 0.16);
        }
    }

    /**
     * A recessed panel: darker than what is behind it, with a light rim.
     * Used for the price rows — stacking more translucent-white panes there
     * flattened the whole card into one grey slab.
     *
     * @param array{0:int,1:int,2:int} $rim
     */
    public function insetPanel(float $x, float $y, float $w, float $h, float $r, array $rim = CardPalette::GLASS, float $darkness = 0.34, float $rimOpacity = 0.16, float $rimWidth = 1.5): void
    {
        $this->roundRect($x, $y, $w, $h, $r, $rim, $rimOpacity);
        $this->roundRect($x + $rimWidth, $y + $rimWidth, $w - 2 * $rimWidth, $h - 2 * $rimWidth, max(0.0, $r - $rimWidth), CardPalette::BG_BOTTOM, $darkness);
    }

    /** Straight hairline separator. @param array{0:int,1:int,2:int} $rgb */
    public function rule(float $x, float $y, float $w, array $rgb, float $opacity = 0.12, float $thickness = 1.4): void
    {
        $this->roundRect($x, $y, $w, $thickness, $thickness / 2, $rgb, $opacity);
    }

    // -- text --------------------------------------------------------------

    /**
     * @param array{0:int,1:int,2:int} $rgb
     * @param string $align  left|center|right — $x is the corresponding edge
     * @param float  $size   CAP HEIGHT in final-image pixels (not em size)
     * @param float  $weight stroke thickness as a fraction of $size (vector backend only)
     * @return float the width actually drawn
     */
    public function text(
        string $text,
        float $x,
        float $y,
        float $size,
        array $rgb,
        string $align = 'left',
        float $weight = 0.115,
        float $tracking = 1.0,
    ): float {
        $width = $this->textWidth($text, $size, $tracking, $weight);
        $startX = match ($align) {
            'center' => $x - $width / 2,
            'right' => $x - $width,
            default => $x,
        };

        $ttf = CardConfig::fontPath($this->isBold($weight));
        if ($ttf !== null) {
            $this->drawTtf($text, $startX, $y, $size, $rgb, $ttf);
            return $width;
        }
        $this->drawVector($text, $startX, $y, $size, $rgb, $weight, $tracking);
        return $width;
    }

    public function textWidth(string $text, float $size, float $tracking = 1.0, float $weight = 0.115): float
    {
        $ttf = CardConfig::fontPath($this->isBold($weight));
        if ($ttf !== null) {
            $box = @imagettfbbox($this->ttfSize($size, $ttf), 0, $ttf, $this->ttfText($text));
            return $box === false ? 0.0 : (float) abs($box[2] - $box[0]);
        }

        $unit = $size / 10;
        $keys = VectorFont::keys($text);
        $w = 0.0;
        foreach ($keys as $i => $key) {
            [$adv] = VectorFont::glyph($key);
            $w += $adv * $unit;
            if ($i < count($keys) - 1) {
                $w += VectorFont::TRACKING * $tracking * $unit;
            }
        }
        return $w;
    }

    /**
     * The vector font expresses weight as a stroke thickness; the TTF
     * backend has to pick a different face for it instead. This is the one
     * mapping between the two, so callers keep using a single $weight.
     */
    private function isBold(float $weight): bool
    {
        return $weight >= 0.125;
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private function drawVector(string $text, float $x, float $y, float $size, array $rgb, float $weight, float $tracking): void
    {
        $s = $this->scale;
        $unit = ($size / 10) * $s;
        $color = $this->color($rgb, 1.0);
        $strokeWidth = max(1.0, $size * $weight * $s);
        $penX = $x * $s;
        $originY = $y * $s;

        foreach (VectorFont::keys($text) as $key) {
            [$adv, $strokes] = VectorFont::glyph($key);
            foreach ($strokes as $stroke) {
                $pts = [];
                foreach ($stroke as $p) {
                    $pts[] = [$penX + $p[0] * $unit, $originY + $p[1] * $unit];
                }
                $this->strokePath($pts, $strokeWidth, $color);
            }
            $penX += ($adv + VectorFont::TRACKING * $tracking) * $unit;
        }
    }

    /** Round-capped thick polyline: a quad per segment, a disc per vertex. */
    private function strokePath(array $pts, float $width, int $color): void
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
        $d = (int) round($width);
        foreach ($pts as [$px, $py]) {
            imagefilledellipse($this->im, (int) round($px), (int) round($py), $d, $d, $color);
        }
    }

    /** @param array{0:int,1:int,2:int} $rgb */
    private function drawTtf(string $text, float $x, float $y, float $size, array $rgb, string $ttf): void
    {
        $s = $this->scale;
        // imagettftext() positions by BASELINE; $y here is the cap top.
        @imagettftext(
            $this->im,
            $this->ttfSize($size, $ttf) * $s,
            0,
            (int) round($x * $s),
            (int) round(($y + $size) * $s),
            $this->color($rgb, 1.0),
            $ttf,
            $this->ttfText($text)
        );
    }

    private function ttfText(string $text): string
    {
        return PersianShaper::shape($text);
    }

    /**
     * Converts "cap height in px" into the point size imagettftext wants,
     * by measuring the font's own cap height once and scaling from there —
     * the ratio differs per typeface, so it can't be a constant.
     */
    private function ttfSize(float $capHeightPx, string $ttf): float
    {
        if (!isset(self::$ttfCalibration[$ttf])) {
            $box = @imagettfbbox(100.0, 0, $ttf, 'H');
            $measured = $box === false ? 70.0 : (float) abs($box[7] - $box[1]);
            self::$ttfCalibration[$ttf] = $measured > 0 ? $measured : 70.0;
        }
        return $capHeightPx * 100.0 / self::$ttfCalibration[$ttf];
    }

    // -- output ------------------------------------------------------------

    public function toPng(): string
    {
        $out = $this->im;
        if ($this->scale > 1) {
            $scaled = imagescale($this->im, $this->width, $this->height, IMG_BICUBIC);
            if ($scaled !== false) {
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

// ============================================================================
// SECTION 5 — CARD CONFIG
// ============================================================================

final class CardConfig
{
    /** @var array<string,string|null> resolved font paths, keyed by weight */
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

    /** ext-gd plus the specific calls the renderer needs — a partial GD build must not fatal mid-card. */
    public static function available(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagefilledpolygon')
            && function_exists('imagepng');
    }

    /**
     * Supersampling factor. 2 is the default: side by side with 3 the
     * difference is not visible at Telegram's display size, and it renders
     * in roughly half the time — which is what matters on a shared host
     * where the whole cron pass has a ~50 second budget.
     */
    public static function renderScale(): int
    {
        $v = (int) (self::env('CARD_RENDER_SCALE', '2') ?? '2');
        return max(1, min(4, $v ?: 2));
    }

    /**
     * TTF used for card text. Defaults to the Vazirmatn shipped in fonts/,
     * which is what lets the cards carry Persian labels rather than the
     * Latin-only built-in vector font.
     *
     * Resolution order per weight: the configured path, then the bundled
     * font, then (for bold) the regular weight, then null. Null means the
     * vector font takes over — which still needs no files on disk at all,
     * so deleting fonts/ degrades the cards instead of breaking them.
     */
    public static function fontPath(bool $bold = false): ?string
    {
        $key = $bold ? 'bold' : 'regular';
        if (array_key_exists($key, self::$fontCache)) {
            return self::$fontCache[$key];
        }

        if (!function_exists('imagettftext') || !function_exists('imagettfbbox')) {
            return self::$fontCache[$key] = null;
        }

        $candidates = $bold
            ? [self::env('CARD_FONT_PATH_BOLD', ''), __DIR__ . '/fonts/Vazirmatn-Bold.ttf']
            : [self::env('CARD_FONT_PATH', ''), __DIR__ . '/fonts/Vazirmatn-Regular.ttf'];

        foreach ($candidates as $path) {
            $path = (string) ($path ?? '');
            if ($path !== '' && is_file($path) && is_readable($path)) {
                return self::$fontCache[$key] = $path;
            }
        }

        // A bold-less install still renders; it just renders in one weight.
        return self::$fontCache[$key] = ($bold ? self::fontPath(false) : null);
    }

    /**
     * True when card text can be written in Persian. The built-in vector
     * font is Latin-only by design, so the labels have to fall back to
     * English whenever no TTF is available.
     */
    public static function supportsPersian(): bool
    {
        return self::fontPath() !== null;
    }

    /** Small wordmark printed in the card footer. */
    public static function brand(): string
    {
        return (string) (self::env('CARD_BRAND', 'AUTO SIGNAL') ?? 'AUTO SIGNAL');
    }

    /** Resets memoized state — used by tests that flip CARD_FONT_PATH between renders. */
    public static function resetFontCache(): void
    {
        self::$fontCache = [];
    }
}

// ============================================================================
// SECTION 6 — LABELS
//
// The built-in vector font is Latin-only, so the wording on a card depends
// on whether a Persian-capable TTF is actually available. With the bundled
// Vazirmatn present (the normal case) the cards speak Persian; strip fonts/
// out and they fall back to English rather than rendering empty boxes.
// ============================================================================

final class CardLabels
{
    /** @var array<string,array{0:string,1:string}> key => [persian, english] */
    private const LABELS = [
        'signal'      => ['سیگنال جدید', 'SIGNAL'],
        'entry'       => ['نقطه ورود', 'ENTRY'],
        'stop'        => ['حد ضرر', 'STOP LOSS'],
        'tp1'         => ['تارگت ۱', 'TARGET 1'],
        'tp2'         => ['تارگت ۲', 'TARGET 2'],
        'exit'        => ['خروج', 'EXIT'],
        'entry_short' => ['ورود', 'ENTRY'],
        'move'        => ['حرکت قیمت', 'PRICE MOVE'],
        'score'       => ['امتیاز', 'SCORE'],
        'leverage'    => ['اهرم', ''],
        'risk_free'   => ['ریسک فری', 'RISK FREE'],
        'closed'      => ['بسته شد', 'CLOSED'],
        'no_loss'     => ['بدون ضرر', 'NO LOSS'],
        'tp1_hit'     => ['تارگت ۱ زده شد', 'TP1 HIT'],
        'tp2_hit'     => ['تارگت ۲ زده شد', 'TP2 HIT'],
        'sl_hit'      => ['حد ضرر خورد', 'SL HIT'],
        'title_tp1'   => ['سود گرفته شد', 'PROFIT SHOT'],
        'title_tp2'   => ['تارگت ۲ فعال شد', 'TARGET 2 HIT'],
        'title_sl'    => ['حد ضرر فعال شد', 'STOP LOSS'],
        'title_be'    => ['بدون سود و ضرر', 'BREAK EVEN'],
        'roi_with'    => ['سود با اهرم', 'ROI WITH'],
        'result_with' => ['نتیجه با اهرم', 'RESULT WITH'],
    ];

    public static function get(string $key): string
    {
        $pair = self::LABELS[$key] ?? [$key, $key];
        return CardConfig::supportsPersian() ? $pair[0] : $pair[1];
    }

    /** "اهرم 150x" in Persian, "150X" on its own in English — the word is redundant there. */
    public static function leverage(string $leverage): string
    {
        return CardConfig::supportsPersian()
            ? self::get('leverage') . ' ' . strtolower($leverage)
            : strtoupper($leverage);
    }

    public static function score(string $score): string
    {
        return self::get('score') . ' ' . $score;
    }

    /** Trailing "LEVERAGE" only reads right in English; Persian puts the word first. */
    public static function roi(string $leverage, bool $profitable): string
    {
        $lead = self::get($profitable ? 'roi_with' : 'result_with');
        return CardConfig::supportsPersian()
            ? $lead . ' ' . strtolower($leverage)
            : $lead . ' ' . strtoupper($leverage) . ' LEVERAGE';
    }
}

// ============================================================================
// SECTION 7 — SHARED CARD CHROME
// ============================================================================

final class CardChrome
{
    /**
     * Backdrop shared by every card: a near-black vertical gradient lit by
     * one bloom in the card's accent and one in its complement, then the
     * main glass pane on top.
     *
     * @param array{0:int,1:int,2:int} $accent
     */
    public static function backdrop(CardCanvas $c, int $w, int $h, array $accent, float $margin, float $radius): void
    {
        // Pair each card's accent with its complement so both cards read as
        // green AND yellow. A SHORT card lit only by yellow came out muddy.
        $complement = $accent === CardPalette::GREEN ? CardPalette::YELLOW : CardPalette::GREEN;
        $c->backdrop(CardPalette::BG_TOP, CardPalette::BG_BOTTOM, [
            [$w * 0.08, $h * 0.03, $w * 0.52, $accent, 0.52],
            [$w * 1.00, $h * 0.96, $w * 0.50, $complement, 0.40],
            [$w * 0.54, $h * 0.44, $w * 0.66, CardPalette::GREEN_DEEP, 0.20],
            [$w * 0.04, $h * 0.76, $w * 0.36, CardPalette::GREEN_DEEP, 0.18],
        ]);

        // The pane is tinted with the direction accent rather than plain
        // white: a neutral pane over a coloured bloom washes out to grey.
        $tint = CardCanvas::mix(CardPalette::GLASS, $accent, 0.30);
        $c->glassPanel($margin, $margin, $w - 2 * $margin, $h - 2 * $margin, $radius, $tint, 0.07, 0.20, 1.8);
    }

    /**
     * A pill with a label inside. Returns its width so a row of them can be
     * laid out left to right without measuring twice.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public static function chip(
        CardCanvas $c,
        float $x,
        float $y,
        string $label,
        array $rgb,
        float $textSize = 22,
        float $height = 50,
        float $padding = 22,
        float $fillOpacity = 0.13,
        float $borderOpacity = 0.34,
        float $tracking = 1.4,
        float $weight = 0.125,
    ): float {
        $tw = $c->textWidth($label, $textSize, $tracking, $weight);
        $w = $tw + $padding * 2;
        $c->glassPanel($x, $y, $w, $height, $height / 2, $rgb, $fillOpacity, $borderOpacity, 1.5, false);
        $c->text($label, $x + $padding, $y + ($height - $textSize) / 2, $textSize, $rgb, 'left', $weight, $tracking);
        return $w;
    }

    /** Centers a row of chips as a group — widths must be measured before the first one is drawn. */
    public static function chipRow(
        CardCanvas $c,
        float $centerX,
        float $y,
        array $chips,
        float $textSize = 22,
        float $height = 50,
        float $padding = 22,
        float $fillOpacity = 0.13,
        float $borderOpacity = 0.34,
    ): void {
        $gap = 14.0;
        $chips = array_values(array_filter($chips, static fn($ch) => trim((string) $ch[0]) !== ''));
        if (empty($chips)) {
            return;
        }
        $total = -$gap;
        foreach ($chips as $ch) {
            $total += $c->textWidth((string) $ch[0], $textSize, 1.4, 0.125) + $padding * 2 + $gap;
        }

        $x = $centerX - $total / 2;
        foreach ($chips as [$label, $rgb]) {
            $x += self::chip($c, $x, $y, (string) $label, $rgb, $textSize, $height, $padding, $fillOpacity, $borderOpacity) + $gap;
        }
    }

    /**
     * A recessed tile with a muted label above a large value. This is the
     * unit the landscape layout is built from — four of them across for the
     * entry card, three for the result card.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public static function tile(
        CardCanvas $c,
        float $x,
        float $y,
        float $w,
        float $h,
        string $label,
        string $value,
        array $rgb,
        float $labelSize = 19,
        float $valueSize = 36,
    ): void {
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.96);
        $c->insetPanel($x, $y, $w, $h, 22, CardPalette::GLASS, 0.40, 0.16);
        // Accent bar across the top of the tile: the row's colour identity,
        // read before the text is.
        $c->roundRect($x + $w * 0.30, $y + 14, $w * 0.40, 5, 2.5, $rgb, 0.9);
        $c->text($label, $x + $w / 2, $y + $h * 0.26, $labelSize, $muted, 'center', 0.11, 1.6);

        // 8-decimal shitcoin prices have to shrink rather than overflow.
        $maxWidth = $w - 28;
        while ($valueSize > 18 && $c->textWidth($value, $valueSize, 0.8, 0.125) > $maxWidth) {
            $valueSize -= 2;
        }
        $c->text($value, $x + $w / 2, $y + $h * 0.52, $valueSize, $rgb, 'center', 0.125, 0.8);
    }

    /** Footer wordmark + timestamp, on a hairline rule. */
    public static function footer(CardCanvas $c, float $left, float $right, float $y, string $time): void
    {
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.92);
        $c->rule($left, $y, $right - $left, CardPalette::GLASS, 0.10);
        $c->text(CardConfig::brand(), $left, $y + 26, 18, CardCanvas::mix(CardPalette::GREEN, CardPalette::BG_BOTTOM, 0.9), 'left', 0.11, 3.0);
        $c->text($time, $right, $y + 26, 18, $muted, 'right', 0.11, 1.6);
    }
}

// ============================================================================
// SECTION 8 — ENTRY SIGNAL CARD (landscape)
// ============================================================================

final class SignalCard
{
    private const W = 1200;
    private const H = 660;
    private const MARGIN = 32.0;
    private const RADIUS = 40.0;
    private const PAD = 50.0;

    /**
     * All values arrive pre-formatted as display strings — this class is
     * presentation only and never rounds, converts or re-derives a price.
     *
     * @param array{
     *   symbol:string, exchange:string, direction:string, timeframe:string,
     *   leverage:string, entry:string, sl:string, tp1:string, tp2:string,
     *   rr:string, score:string, time:string
     * } $d
     * @return string|null PNG bytes, or null when the card cannot be drawn
     */
    public static function render(array $d): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $direction = strtoupper((string) ($d['direction'] ?? 'LONG'));
            $accent = CardPalette::forDirection($direction);
            $c = new CardCanvas(self::W, self::H);
            CardChrome::backdrop($c, self::W, self::H, $accent, self::MARGIN, self::RADIUS);

            $left = self::MARGIN + self::PAD;
            $right = self::W - self::MARGIN - self::PAD;
            $inner = $right - $left;

            // -- header --------------------------------------------------
            $c->text(CardLabels::get('signal'), $left, 64, 20, CardPalette::TEXT, 'left', 0.11, 3.2);
            $exchange = strtoupper((string) ($d['exchange'] ?? ''));
            if ($exchange !== '') {
                $ew = $c->textWidth($exchange, 19, 2.2, 0.125) + 40;
                CardChrome::chip($c, $right - $ew, 52, $exchange, CardPalette::GLASS, 19, 44, 20, 0.08, 0.18, 2.2);
            }

            // -- symbol, with the direction badge alongside it -----------
            $symbol = (string) ($d['symbol'] ?? '');
            $symbolWidth = $c->text($symbol, $left, 112, 54, CardPalette::TEXT, 'left', 0.135, 0.7);
            CardChrome::chip($c, $left + $symbolWidth + 26, 118, $direction, $accent, 24, 50, 26, 0.18, 0.46, 2.4);

            // -- meta chips ----------------------------------------------
            $x = $left;
            foreach ([
                [strtoupper((string) ($d['timeframe'] ?? '')), CardPalette::GLASS],
                [CardLabels::leverage((string) ($d['leverage'] ?? '')), CardPalette::YELLOW],
                ['R:R ' . (string) ($d['rr'] ?? ''), CardPalette::GREEN],
                [CardLabels::score((string) ($d['score'] ?? '')), CardPalette::GLASS],
            ] as [$label, $rgb]) {
                if (trim($label) === '') {
                    continue;
                }
                $x += CardChrome::chip($c, $x, 208, $label, $rgb, 22, 50, 22) + 14;
            }

            // -- price tiles, four across --------------------------------
            $tiles = [
                [CardLabels::get('entry'), (string) ($d['entry'] ?? '-'), CardPalette::TEXT],
                [CardLabels::get('stop'), (string) ($d['sl'] ?? '-'), CardPalette::AMBER],
                [CardLabels::get('tp1'), (string) ($d['tp1'] ?? '-'), CardPalette::GREEN],
            ];
            $tp2 = trim((string) ($d['tp2'] ?? ''));
            if ($tp2 !== '' && $tp2 !== '-') {
                $tiles[] = [CardLabels::get('tp2'), $tp2, CardPalette::GREEN];
            }

            $gap = 16.0;
            $n = count($tiles);
            $tileW = ($inner - $gap * ($n - 1)) / $n;
            foreach ($tiles as $i => [$label, $value, $rgb]) {
                CardChrome::tile($c, $left + $i * ($tileW + $gap), 306, $tileW, 186, $label, $value, $rgb);
            }

            CardChrome::footer($c, $left, $right, 546, (string) ($d['time'] ?? ''));

            $png = $c->toPng();
            $c->destroy();
            return $png;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'signal card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}

// ============================================================================
// SECTION 9 — RESULT / "PROFIT SHOT" CARD (landscape)
// ============================================================================

final class ResultCard
{
    private const W = 1200;
    private const H = 660;
    private const MARGIN = 32.0;
    private const RADIUS = 40.0;
    private const PAD = 50.0;

    /**
     * Title, subtitle and badges are derived here from $kind rather than
     * passed in, so their wording follows the card's own language (see
     * CardLabels) instead of being fixed by the caller.
     *
     * @param array{
     *   kind:string, symbol:string, direction:string, timeframe:string,
     *   leverage:string, headline:string, move:string, entry:string,
     *   exit:string, time:string
     * } $d  kind: tp1|tp2|sl|be
     * @return string|null PNG bytes, or null when the card cannot be drawn
     */
    public static function render(array $d): ?string
    {
        if (!CardConfig::available()) {
            return null;
        }

        try {
            $kind = strtolower((string) ($d['kind'] ?? 'tp1'));
            // Wins are green, a stop-out or breakeven is amber/yellow — the
            // card stays inside the green/yellow identity either way.
            $accent = match ($kind) {
                'sl' => CardPalette::AMBER,
                'be' => CardPalette::YELLOW,
                default => CardPalette::GREEN,
            };
            $badges = match ($kind) {
                'tp1' => ['tp1_hit', 'risk_free'],
                'tp2' => ['tp2_hit', 'closed'],
                'sl' => ['sl_hit', 'closed'],
                default => ['risk_free', 'no_loss'],
            };

            $c = new CardCanvas(self::W, self::H);
            CardChrome::backdrop($c, self::W, self::H, $accent, self::MARGIN, self::RADIUS);

            $left = self::MARGIN + self::PAD;
            $right = self::W - self::MARGIN - self::PAD;
            $inner = $right - $left;
            $centerX = self::W / 2;

            $c->text(CardLabels::get('title_' . $kind), $centerX, 60, 20, CardCanvas::mix($accent, CardPalette::BG_BOTTOM, 0.95), 'center', 0.11, 3.2);

            CardChrome::chipRow($c, $centerX, 96, [
                [strtoupper((string) ($d['symbol'] ?? '')), CardPalette::GLASS],
                [strtoupper((string) ($d['direction'] ?? '')), $accent],
                [strtoupper((string) ($d['timeframe'] ?? '')), CardPalette::GLASS],
            ], 20, 46, 20);

            // -- the number the whole card exists for --------------------
            $headline = (string) ($d['headline'] ?? '');
            $headSize = 92.0;
            while ($headSize > 44 && $c->textWidth($headline, $headSize, 0.6, 0.165) > $inner) {
                $headSize -= 4;
            }
            $c->text($headline, $centerX, 178 + (92 - $headSize) / 2, $headSize, $accent, 'center', 0.165, 0.6);

            $profitable = !str_starts_with(trim($headline), '-');
            $c->text(
                CardLabels::roi((string) ($d['leverage'] ?? ''), $profitable),
                $centerX,
                300,
                21,
                CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.96),
                'center',
                0.11,
                2.2
            );

            // Persian badges are read right to left, so the first badge has
            // to sit on the right of the row. The chip row above is left in
            // source order because its contents (symbol, LONG/SHORT, 4H) are
            // Latin either way.
            if (CardConfig::supportsPersian()) {
                $badges = array_reverse($badges);
            }
            CardChrome::chipRow($c, $centerX, 340, array_map(
                static fn(string $b): array => [CardLabels::get($b), $accent],
                $badges
            ), 21, 50, 24, 0.18, 0.44);

            // -- entry / exit / move -------------------------------------
            $gap = 16.0;
            $tileW = ($inner - $gap * 2) / 3;
            CardChrome::tile($c, $left, 424, $tileW, 124, CardLabels::get('entry_short'), (string) ($d['entry'] ?? '-'), CardPalette::TEXT, 17, 32);
            CardChrome::tile($c, $left + $tileW + $gap, 424, $tileW, 124, CardLabels::get('exit'), (string) ($d['exit'] ?? '-'), $accent, 17, 32);
            CardChrome::tile($c, $left + 2 * ($tileW + $gap), 424, $tileW, 124, CardLabels::get('move'), (string) ($d['move'] ?? '-'), $accent, 17, 32);

            CardChrome::footer($c, $left, $right, 578, (string) ($d['time'] ?? ''));

            $png = $c->toPng();
            $c->destroy();
            return $png;
        } catch (Throwable $e) {
            if (class_exists('Logger')) {
                Logger::error('card', 'result card render failed', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }
}

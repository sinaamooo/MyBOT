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
    // Backdrop — black, barely graded. There is no colour anywhere on these
    // cards: hierarchy is carried by brightness, weight and size alone,
    // which is what keeps a monochrome card from going flat.
    public const BG_TOP       = [16, 16, 18];
    public const BG_BOTTOM    = [0, 0, 0];

    // The one scale everything is drawn from.
    public const WHITE        = [255, 255, 255];
    public const TEXT         = [246, 247, 249];
    public const SOFT         = [198, 200, 206];
    public const MUTED        = [138, 141, 148];
    public const DIM          = [84, 87, 94];
    public const GLASS        = [255, 255, 255];

    // Kept so any caller that still names a colour keeps working — and gets
    // white, so a stray reference can never re-introduce a tint.
    public const NEON_GREEN   = self::WHITE;
    public const NEON_CYAN    = self::WHITE;
    public const NEON_PINK    = self::WHITE;
    public const NEON_AMBER   = self::WHITE;
    public const NEON_VIOLET  = self::WHITE;
    public const GREEN        = self::WHITE;
    public const GREEN_DEEP   = self::DIM;
    public const YELLOW       = self::WHITE;
    public const YELLOW_DEEP  = self::DIM;
    public const AMBER        = self::WHITE;
    public const TEAL         = self::WHITE;

    /** Direction changes the icon and the chip, never the colour. */
    public static function forDirection(string $direction): array
    {
        return self::WHITE;
    }

    /** @param array{0:int,1:int,2:int} $accent */
    public static function complementFor(array $accent): array
    {
        return self::WHITE;
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
        float $knockout = 0.0,
    ): void {
        // The rim is drawn as a full rounded rect and then punched back out
        // from the inside. Without the knockout the "border" layer tints the
        // WHOLE panel — which is exactly how a black card ends up charcoal.
        $this->roundRect($x, $y, $w, $h, $r, $tint, $borderOpacity);
        if ($knockout > 0.0) {
            $this->roundRect(
                $x + $borderWidth,
                $y + $borderWidth,
                $w - 2 * $borderWidth,
                $h - 2 * $borderWidth,
                max(0.0, $r - $borderWidth),
                CardPalette::BG_BOTTOM,
                $knockout
            );
        }
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
    public function insetPanel(float $x, float $y, float $w, float $h, float $r, array $rim = CardPalette::GLASS, float $darkness = 0.34, float $rimOpacity = 0.16, float $rimWidth = 1.5, ?array $tint = null): void
    {
        $this->roundRect($x, $y, $w, $h, $r, $rim, $rimOpacity);
        // A pure-black inset reads as a grey hole punched in a coloured card;
        // pulling a little of the row's own accent into the fill keeps the
        // whole surface feeling like one piece of tinted glass.
        $fill = $tint === null ? CardPalette::BG_BOTTOM : self::mix(CardPalette::BG_BOTTOM, $tint, 0.86);
        $this->roundRect($x + $rimWidth, $y + $rimWidth, $w - 2 * $rimWidth, $h - 2 * $rimWidth, max(0.0, $r - $rimWidth), $fill, $darkness);
    }

    /**
     * A soft coloured halo drawn directly on the card (not in the low-res
     * backdrop), for lighting one specific element such as the symbol.
     * Kept small on purpose — stacked ellipses at full supersampled size are
     * the expensive thing this file works hard to avoid.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public function halo(float $cx, float $cy, float $radius, array $rgb, float $strength = 0.30, int $steps = 22): void
    {
        $s = $this->scale;
        $per = 1 - pow(1 - max(0.0, min(1.0, $strength)), 1 / max(1, $steps));
        $color = $this->color($rgb, $per);
        for ($i = $steps; $i >= 1; $i--) {
            $d = (int) round($radius * ($i / $steps) * $s * 2);
            imagefilledellipse($this->im, (int) round($cx * $s), (int) round($cy * $s), $d, $d, $color);
        }
    }

    /**
     * Draws one of CardIcons' paths at a given size.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public function icon(string $name, float $x, float $y, float $size, array $rgb, float $weight = 0.11, float $opacity = 1.0): void
    {
        $paths = CardIcons::paths($name);
        if (empty($paths)) {
            return;
        }
        $s = $this->scale;
        $unit = ($size / 10) * $s;
        $color = $this->color($rgb, $opacity);
        $stroke = max(1.0, $size * $weight * $s);
        foreach ($paths as $path) {
            $pts = [];
            foreach ($path as $p) {
                $pts[] = [($x * $s) + $p[0] * $unit, ($y * $s) + $p[1] * $unit];
            }
            $this->strokePath($pts, $stroke, $color);
        }
    }

    /**
     * Composites a PNG (the brand logo) scaled to fit a box, preserving its
     * alpha. Silently does nothing if the file is unreadable — a missing
     * logo must never cost a card.
     */
    public function image(string $path, float $x, float $y, float $boxW, float $boxH, float $opacity = 1.0): bool
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
        $dx = (int) round($x * $s + (($boxW * $s) - $dw) / 2);
        $dy = (int) round($y * $s + (($boxH * $s) - $dh) / 2);

        imagealphablending($src, true);
        if ($opacity >= 1.0) {
            imagecopyresampled($this->im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        } else {
            // imagecopymerge cannot carry alpha, so scale first and then
            // fade by rewriting the alpha channel of the scaled copy.
            $tmp = imagecreatetruecolor($dw, $dh);
            imagealphablending($tmp, false);
            imagesavealpha($tmp, true);
            imagefilledrectangle($tmp, 0, 0, $dw, $dh, imagecolorallocatealpha($tmp, 0, 0, 0, 127));
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);
            for ($py = 0; $py < $dh; $py++) {
                for ($px = 0; $px < $dw; $px++) {
                    $c = imagecolorat($tmp, $px, $py);
                    $a = ($c >> 24) & 0x7F;
                    $faded = min(127, (int) round($a + (127 - $a) * (1 - $opacity)));
                    imagesetpixel($tmp, $px, $py, ($faded << 24) | ($c & 0xFFFFFF));
                }
            }
            imagealphablending($this->im, true);
            imagecopy($this->im, $tmp, $dx, $dy, 0, 0, $dw, $dh);
            imagedestroy($tmp);
        }
        imagedestroy($src);
        return true;
    }

    /**
     * Opens whichever image format the operator's logo happens to be in.
     * Returns false — never throws — for anything GD on this host cannot
     * read.
     *
     * @return \GdImage|false
     */
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
        // One weight for the whole card: bold. A muted Persian label at
        // regular weight, small, on black, simply disappears — and the
        // design asks for a single voice rather than a mix of two.
        return true;
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
     * TTF used for card text. Defaults to the Vazirmatn shipped alongside
     * this file, which is what lets the cards carry Persian labels rather
     * than the Latin-only built-in vector font.
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

        // The project deploys as a flat pile of files next to each other, so
        // the font sits beside card.php; the fonts/ path is kept as a second
        // candidate for installs that already put it in a subfolder.
        $candidates = $bold
            ? [self::env('CARD_FONT_PATH_BOLD', ''), __DIR__ . '/Vazirmatn-Bold.ttf', __DIR__ . '/fonts/Vazirmatn-Bold.ttf']
            : [self::env('CARD_FONT_PATH', ''), __DIR__ . '/Vazirmatn-Regular.ttf', __DIR__ . '/fonts/Vazirmatn-Regular.ttf'];

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

    /**
     * Brand logo composited onto the result cards. Drop a PNG named
     * logo.png beside the PHP files (transparent background works best) and
     * it appears automatically; point CARD_LOGO_PATH somewhere else to
     * override. Absent, the cards simply render without it.
     */
    public static function logoPath(): ?string
    {
        $candidates = [self::env('CARD_LOGO_PATH', '')];
        // Whatever the operator actually dropped in: a transparent PNG is
        // the best-looking option, but a JPEG straight off a phone must not
        // silently do nothing.
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

    /** Small wordmark printed in the card footer. */
    public static function brand(): string
    {
        return (string) (self::env('CARD_BRAND', 'AUTO TRADE MARKET') ?? 'AUTO TRADE MARKET');
    }

    /** Resets memoized state — used by tests that flip CARD_FONT_PATH between renders. */
    public static function resetFontCache(): void
    {
        self::$fontCache = [];
    }
}

// ============================================================================
// SECTION 5.5 — ICONS
//
// Drawn as stroked vector paths on the same 0..10 grid the font uses, so
// they scale and sit on the baseline exactly like a glyph would. Emoji were
// not an option: the built-in font has none, and a TTF's emoji coverage
// varies per host.
// ============================================================================

final class CardIcons
{
    /**
     * @return array<int,array<int,array{0:float,1:float}>> stroke paths in a 10x10 box
     */
    public static function paths(string $name): array
    {
        $ring = static function (float $cx, float $cy, float $r, int $seg = 12): array {
            $pts = [];
            for ($i = 0; $i <= $seg; $i++) {
                $a = ($i / $seg) * 2 * M_PI;
                $pts[] = [$cx + cos($a) * $r, $cy + sin($a) * $r];
            }
            return $pts;
        };

        return match ($name) {
            // Entry — a crosshair over the price you take the trade at.
            'entry' => [$ring(5, 5, 3.0), [[5, 0.6], [5, 2.2]], [[5, 7.8], [5, 9.4]], [[0.6, 5], [2.2, 5]], [[7.8, 5], [9.4, 5]]],
            // Stop — a shield.
            'stop' => [[[5, 0.8], [9, 2.4], [9, 5.4], [5, 9.2], [1, 5.4], [1, 2.4], [5, 0.8]]],
            // Target — a bullseye.
            'target' => [$ring(5, 5, 4.0), $ring(5, 5, 2.0), [[5, 4.6], [5, 5.4]]],
            // Long / short — a directional arrow.
            'up' => [[[5, 9.2], [5, 1.2]], [[1.6, 4.6], [5, 1.2], [8.4, 4.6]]],
            'down' => [[[5, 0.8], [5, 8.8]], [[1.6, 5.4], [5, 8.8], [8.4, 5.4]]],
            // Leverage — a bolt.
            'bolt' => [[[6.4, 0.6], [2.6, 5.4], [5.2, 5.4], [3.8, 9.4], [7.6, 4.4], [5.0, 4.4], [6.4, 0.6]]],
            // Risk free — a padlock.
            'lock' => [[[2.2, 4.6], [7.8, 4.6], [7.8, 9.2], [2.2, 9.2], [2.2, 4.6]], [[3.4, 4.6], [3.4, 2.8], [6.6, 2.8], [6.6, 4.6]]],
            // Market entry — a live-price pulse.
            'pulse' => [[[0.8, 5.0], [3.0, 5.0], [4.0, 2.2], [5.8, 8.0], [6.9, 5.0], [9.2, 5.0]]],
            // Result — a tick.
            'check' => [[[1.4, 5.2], [4.0, 7.8], [8.8, 2.2]]],
            // Stop-out — a cross.
            'cross' => [[[2.0, 2.0], [8.0, 8.0]], [[8.0, 2.0], [2.0, 8.0]]],
            // Fallback brand mark: a framed candle with a breakout arrow,
            // drawn only when no logo.png has been dropped in beside the
            // PHP files.
            'mark' => [
                [[2.6, 0.6], [7.4, 0.6], [9.4, 2.6], [9.4, 7.4], [7.4, 9.4], [2.6, 9.4], [0.6, 7.4], [0.6, 2.6], [2.6, 0.6]],
                [[3.4, 7.4], [3.4, 4.6]], [[3.4, 3.2], [3.4, 2.2]], [[2.6, 4.6], [4.2, 4.6], [4.2, 3.2], [2.6, 3.2], [2.6, 4.6]],
                [[6.0, 7.6], [6.0, 3.0]], [[4.6, 5.4], [6.0, 3.0], [7.4, 5.4]],
            ],
            default => [],
        };
    }

    public static function has(string $name): bool
    {
        return !empty(self::paths($name));
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
        'market'      => ['ورود بازار', 'MARKET'],
        'market_note' => ['ورود در قیمت بازار', 'ENTER AT MARKET'],
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
     * Backdrop shared by every card: near-black glass lit from the corners
     * by two neon blooms, with a HUD bracket frame on top. The glass itself
     * stays neutral — tinting the pane as well is what used to turn these
     * cards muddy, and it is the black-and-white contrast that makes the
     * neon read as neon.
     *
     * @param array{0:int,1:int,2:int} $accent
     */
    public static function backdrop(CardCanvas $c, int $w, int $h, array $accent, float $margin, float $radius): void
    {
        // Two soft white lights, pushed right out to the corners. Any more
        // than this and a black card turns grey, which is what kills it.
        $c->backdrop(CardPalette::BG_TOP, CardPalette::BG_BOTTOM, [
            [$w * -0.06, $h * -0.18, $w * 0.38, CardPalette::WHITE, 0.13],
            [$w * 1.06, $h * 1.18, $w * 0.32, CardPalette::WHITE, 0.09],
        ]);

        $c->glassPanel($margin, $margin, $w - 2 * $margin, $h - 2 * $margin, $radius, CardPalette::GLASS, 0.020, 0.16, 1.6, true, 0.88);

        // A lit hairline along the top edge — the rim of a pane of glass,
        // and the cheapest way to make the whole card read as one object.
        $c->roundRect($margin + $radius * 0.7, $margin + 1.5, $w - 2 * $margin - $radius * 1.4, 2.0, 1.0, $accent, 0.55);

        self::corners($c, $margin + 16, $margin + 16, $w - 2 * $margin - 32, $h - 2 * $margin - 32, 34, $accent);
    }

    /** Four HUD brackets, drawn as eight short bars. @param array{0:int,1:int,2:int} $rgb */
    private static function corners(CardCanvas $c, float $x, float $y, float $w, float $h, float $len, array $rgb): void
    {
        $t = 2.0;
        $o = 0.30;
        $r = $x + $w - $len;
        $b = $y + $h - $t;
        foreach ([[$x, $y], [$r, $y], [$x, $b], [$r, $b]] as [$bx, $by]) {
            $c->roundRect($bx, $by, $len, $t, $t / 2, $rgb, $o);
        }
        $r = $x + $w - $t;
        $b = $y + $h - $len;
        foreach ([[$x, $y], [$r, $y], [$x, $b], [$r, $b]] as [$bx, $by]) {
            $c->roundRect($bx, $by, $t, $len, $t / 2, $rgb, $o);
        }
    }

    /**
     * The brand mark: logo.png when the operator has dropped one beside the
     * PHP files, and a drawn fallback otherwise, so a missing file costs a
     * bit of personality rather than a hole in the layout.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public static function logo(CardCanvas $c, float $x, float $y, float $size, array $rgb, float $opacity = 1.0): void
    {
        $path = CardConfig::logoPath();
        if ($path !== null && $c->image($path, $x, $y, $size, $size, $opacity)) {
            return;
        }
        $c->icon('mark', $x, $y, $size, $rgb, 0.07, $opacity);
    }

    /** Width a chip will occupy — needed before drawing when a row is centered. */
    public static function chipWidth(CardCanvas $c, string $label, ?string $icon, float $textSize, float $padding): float
    {
        $w = $c->textWidth($label, $textSize, 1.4, 0.125) + $padding * 2;
        if ($icon !== null && CardIcons::has($icon)) {
            $w += $textSize * 1.05 + 9;
        }
        return $w;
    }

    /**
     * A pill with an optional icon and a label inside. Returns its width so
     * a row of them can be laid out left to right without measuring twice.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public static function chip(
        CardCanvas $c,
        float $x,
        float $y,
        string $label,
        array $rgb,
        ?string $icon = null,
        float $textSize = 17,
        float $height = 38,
        float $padding = 18,
        float $fillOpacity = 0.0,
        float $borderOpacity = 0.55,
        float $tracking = 1.4,
        float $weight = 0.125,
        bool $solid = false,
    ): float {
        $w = self::chipWidth($c, $label, $icon, $textSize, $padding);

        // On a black card there are only two kinds of pill: one filled
        // solid white with black type — which is as loud as this design
        // gets — and one that is just an outline. Half-opaque white fills
        // land in between and read as grey mush, so they are not offered.
        $ink = $rgb;
        if ($solid) {
            $c->roundRect($x, $y, $w, $height, $height / 2, CardPalette::WHITE, 1.0);
            $ink = CardPalette::BG_BOTTOM;
        } else {
            $c->glassPanel($x, $y, $w, $height, $height / 2, $rgb, $fillOpacity, $borderOpacity, 1.4, false, 0.94);
        }

        $tx = $x + $padding;
        if ($icon !== null && CardIcons::has($icon)) {
            $size = $textSize * 1.05;
            $c->icon($icon, $tx, $y + ($height - $size) / 2, $size, $ink, 0.15);
            $tx += $size + 9;
        }
        $c->text($label, $tx, $y + ($height - $textSize) / 2, $textSize, $ink, 'left', $weight, $tracking);
        return $w;
    }

    /**
     * Centers a row of chips as a group — widths must be measured before
     * the first one is drawn.
     *
     * @param array<int,array{0:string,1:array{0:int,1:int,2:int},2?:?string}> $chips
     */
    public static function chipRow(
        CardCanvas $c,
        float $centerX,
        float $y,
        array $chips,
        float $textSize = 17,
        float $height = 38,
        float $padding = 18,
        float $fillOpacity = 0.11,
        float $borderOpacity = 0.42,
    ): void {
        $gap = 12.0;
        $chips = array_values(array_filter($chips, static fn($ch) => trim((string) $ch[0]) !== ''));
        if (empty($chips)) {
            return;
        }

        $total = -$gap;
        foreach ($chips as $ch) {
            $total += self::chipWidth($c, (string) $ch[0], $ch[2] ?? null, $textSize, $padding) + $gap;
        }

        $x = $centerX - $total / 2;
        foreach ($chips as $ch) {
            $x += self::chip(
                $c,
                $x,
                $y,
                (string) $ch[0],
                $ch[1],
                $ch[2] ?? null,
                $textSize,
                $height,
                $padding,
                $fillOpacity,
                $borderOpacity,
                1.4,
                0.125,
                (bool) ($ch[3] ?? false)
            ) + $gap;
        }
    }

    /**
     * One statistic: a neon icon and a muted label on top, the figure
     * underneath in white. The neon identifies the row; the number is the
     * only thing on the card printed at full brightness, which is what
     * makes it the first thing read.
     *
     * @param array{0:int,1:int,2:int} $rgb
     */
    public static function stat(
        CardCanvas $c,
        float $x,
        float $y,
        float $w,
        float $h,
        string $icon,
        string $label,
        string $value,
        array $rgb,
        float $labelSize = 17,
        float $valueSize = 34,
    ): void {
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.94);
        $c->insetPanel($x, $y, $w, $h, 20, CardPalette::GLASS, 0.92, 0.15, 1.4);
        // Neon edge down the left of the tile: its identity, read before
        // any of its text is.
        $c->roundRect($x + 2.0, $y + $h * 0.20, 3.0, $h * 0.60, 1.5, $rgb, 0.85);

        $pad = 22.0;
        $iconSize = $labelSize * 1.15;
        $c->icon($icon, $x + $pad, $y + 20 + ($labelSize - $iconSize) / 2, $iconSize, $rgb, 0.13);
        $c->text($label, $x + $pad + $iconSize + 10, $y + 20, $labelSize, $muted, 'left', 0.11, 1.5);

        // 8-decimal shitcoin prices have to shrink rather than overflow.
        $maxWidth = $w - $pad * 2;
        while ($valueSize > 16 && $c->textWidth($value, $valueSize, 0.8, 0.125) > $maxWidth) {
            $valueSize -= 2;
        }
        $c->text($value, $x + $pad, $y + $h - 26 - $valueSize, $valueSize, CardPalette::TEXT, 'left', 0.125, 0.8);
    }

    /** Footer wordmark + timestamp, on a hairline rule. */
    public static function footer(CardCanvas $c, float $left, float $right, float $y, string $time, array $accent): void
    {
        $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.90);
        $c->rule($left, $y, $right - $left, CardPalette::GLASS, 0.09);
        $c->roundRect($left, $y, 64, 2.0, 1.0, $accent, 0.7);
        $c->text(CardConfig::brand(), $left, $y + 20, 16, CardCanvas::mix(CardPalette::TEXT, CardPalette::BG_BOTTOM, 0.72), 'left', 0.11, 2.8);
        $c->text($time, $right, $y + 20, 16, $muted, 'right', 0.11, 1.5);
    }
}

// ============================================================================
// SECTION 8 — ENTRY SIGNAL CARD (landscape)
// ============================================================================

final class SignalCard
{
    private const W = 1200;
    private const H = 460;
    private const MARGIN = 26.0;
    private const RADIUS = 34.0;
    private const PAD = 42.0;

    /**
     * All values arrive pre-formatted as display strings — this class is
     * presentation only and never rounds, converts or re-derives a price.
     *
     * The card deliberately carries only what a reader acts on: symbol,
     * direction, leverage, and the four prices. Timeframe, R:R, score and
     * exchange live in the caption underneath instead, where they inform
     * without competing with the numbers.
     *
     * @param array{
     *   symbol:string, direction:string, leverage:string, entry:string,
     *   sl:string, tp1:string, tp2:string, time:string
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
            $isLong = $direction !== 'SHORT';
            $accent = CardPalette::forDirection($direction);
            $complement = CardPalette::complementFor($accent);

            $c = new CardCanvas(self::W, self::H);
            CardChrome::backdrop($c, self::W, self::H, $accent, self::MARGIN, self::RADIUS);

            $left = self::MARGIN + self::PAD;
            $right = self::W - self::MARGIN - self::PAD;
            $inner = $right - $left;
            $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.92);

            // -- header ---------------------------------------------------
            CardChrome::logo($c, $left, 34, 42, $accent);
            $c->text(CardLabels::get('signal'), $left + 56, 47, 17, $accent, 'left', 0.12, 3.2);
            $c->text(CardLabels::get('market_note'), $right, 47, 16, $muted, 'right', 0.11, 1.6);

            // -- symbol, lit from behind, with its badges alongside --------
            $symbol = (string) ($d['symbol'] ?? '');
            $symbolWidth = $c->textWidth($symbol, 46, 0.7, 0.135);
            $c->halo($left + $symbolWidth * 0.45, 124, 155, $accent, 0.22);
            $c->text($symbol, $left, 100, 46, CardPalette::TEXT, 'left', 0.135, 0.7);

            $x = $left + $symbolWidth + 24;
            // The one solid pill on the card: direction is the thing a
            // reader must not be able to misread at a glance.
            $x += CardChrome::chip($c, $x, 108, $direction, CardPalette::WHITE, $isLong ? 'up' : 'down', 17, 38, 18, 0.0, 0.55, 2.2, 0.125, true) + 12;

            $leverage = CardLabels::leverage((string) ($d['leverage'] ?? ''));
            if (trim($leverage) !== '') {
                $x += CardChrome::chip($c, $x, 108, $leverage, CardPalette::SOFT, 'bolt', 17, 38, 18, 0.0, 0.42, 1.5) + 12;
            }

            // Entries are taken at the price printed on the card, not left
            // resting as a trigger order — the badge says so on the card
            // itself so nobody sets a limit and waits for a fill.
            CardChrome::chip($c, $x, 108, CardLabels::get('market'), CardPalette::SOFT, 'pulse', 17, 38, 18, 0.0, 0.42, 1.5);

            // -- price tiles, across --------------------------------------
            // With no colour left to separate them, the tiles are told
            // apart by their icon and by how brightly each one is lit.
            $tiles = [
                ['entry', CardLabels::get('entry'), (string) ($d['entry'] ?? '-'), CardPalette::WHITE],
                ['stop', CardLabels::get('stop'), (string) ($d['sl'] ?? '-'), CardPalette::MUTED],
                ['target', CardLabels::get('tp1'), (string) ($d['tp1'] ?? '-'), CardPalette::WHITE],
            ];
            $tp2 = trim((string) ($d['tp2'] ?? ''));
            if ($tp2 !== '' && $tp2 !== '-') {
                $tiles[] = ['target', CardLabels::get('tp2'), $tp2, CardPalette::SOFT];
            }

            $gap = 18.0;
            $n = count($tiles);
            $tileW = ($inner - $gap * ($n - 1)) / $n;
            foreach ($tiles as $i => [$icon, $label, $value, $rgb]) {
                CardChrome::stat($c, $left + $i * ($tileW + $gap), 186, $tileW, 132, $icon, $label, $value, $rgb);
            }

            CardChrome::footer($c, $left, $right, 356, (string) ($d['time'] ?? ''), $accent);

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
//
// Laid out like an exchange's own position-share card: the headline number
// dominates one side, with the prices that produced it stacked as a labelled
// column on the other. Persian reads right to left, so the hero sits on the
// right and the figures run down the left.
// ============================================================================

final class ResultCard
{
    private const W = 1200;
    private const H = 520;
    private const MARGIN = 26.0;
    private const RADIUS = 34.0;
    private const PAD = 42.0;

    /**
     * Title, subtitle and badges are derived here from $kind rather than
     * passed in, so their wording follows the card's own language.
     *
     * @param array{
     *   kind:string, symbol:string, direction:string, leverage:string,
     *   headline:string, move:string, entry:string, exit:string, time:string
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
            $accent = match ($kind) {
                'sl' => CardPalette::NEON_PINK,
                'be' => CardPalette::NEON_CYAN,
                default => CardPalette::NEON_GREEN,
            };
            $mark = match ($kind) {
                'sl' => 'cross',
                'be' => 'lock',
                default => 'check',
            };
            /** @var array<int,array{0:string,1:string}> $badges label key + icon */
            $badges = match ($kind) {
                'tp1' => [['tp1_hit', 'check'], ['risk_free', 'lock']],
                'tp2' => [['tp2_hit', 'check'], ['closed', 'check']],
                'sl' => [['sl_hit', 'cross'], ['closed', 'cross']],
                default => [['risk_free', 'lock'], ['no_loss', 'check']],
            };

            $c = new CardCanvas(self::W, self::H);
            CardChrome::backdrop($c, self::W, self::H, $accent, self::MARGIN, self::RADIUS);

            $left = self::MARGIN + self::PAD;
            $right = self::W - self::MARGIN - self::PAD;
            $muted = CardCanvas::mix(CardPalette::MUTED, CardPalette::BG_BOTTOM, 0.94);

            // Right two thirds: the hero. Left third: the figures.
            $statsW = 300.0;
            $statsRight = $left + $statsW;
            $heroRight = $right;
            $heroLeft = $statsRight + 48;
            $heroCenter = ($heroLeft + $heroRight) / 2;

            // The brand, twice: a small mark in the header and a large one
            // washed into the glass behind the number, the way an exchange
            // watermarks its own share cards.
            CardChrome::logo($c, $heroCenter - 105, 140, 210, CardPalette::GLASS, 0.055);
            CardChrome::logo($c, $left, 32, 42, $accent);
            $c->text(CardLabels::get('title_' . $kind), $heroRight, 45, 17, $accent, 'right', 0.12, 3.2);

            CardChrome::chipRow($c, $heroCenter, 92, [
                [strtoupper((string) ($d['symbol'] ?? '')), CardPalette::GLASS, null],
                [strtoupper((string) ($d['direction'] ?? '')), $accent, strtoupper((string) ($d['direction'] ?? '')) === 'SHORT' ? 'down' : 'up'],
                [CardLabels::leverage((string) ($d['leverage'] ?? '')), CardPalette::NEON_AMBER, 'bolt'],
            ], 17, 38, 18, 0.13, 0.48);

            // -- the number the whole card exists for ----------------------
            $headline = (string) ($d['headline'] ?? '');
            $heroWidth = $heroRight - $heroLeft;
            $headSize = 92.0;
            while ($headSize > 40 && $c->textWidth($headline, $headSize, 0.6, 0.165) > $heroWidth) {
                $headSize -= 4;
            }
            $c->halo($heroCenter, 220, 200, $accent, 0.24);
            $c->text($headline, $heroCenter, 172 + (92 - $headSize) / 2, $headSize, $accent, 'center', 0.165, 0.6);

            $profitable = !str_starts_with(trim($headline), '-');
            $c->text(
                CardLabels::roi((string) ($d['leverage'] ?? ''), $profitable),
                $heroCenter,
                288,
                17,
                $muted,
                'center',
                0.11,
                2.0
            );

            $chips = array_map(
                static fn(array $b): array => [CardLabels::get($b[0]), $accent, $b[1]],
                $badges
            );
            CardChrome::chipRow(
                $c,
                $heroCenter,
                334,
                CardConfig::supportsPersian() ? array_reverse($chips) : $chips,
                17,
                40,
                20,
                0.15,
                0.50
            );

            // -- the figures, stacked and labelled -------------------------
            $rows = [
                ['entry', CardLabels::get('entry_short'), (string) ($d['entry'] ?? '-'), CardPalette::TEXT],
                [$mark, CardLabels::get('exit'), (string) ($d['exit'] ?? '-'), $accent],
                ['bolt', CardLabels::get('move'), (string) ($d['move'] ?? '-'), $accent],
            ];
            $rowTop = 96.0;
            $rowH = 108.0;
            foreach ($rows as $i => [$icon, $label, $value, $rgb]) {
                $y = $rowTop + $i * $rowH;
                $labelWidth = $c->text($label, $statsRight, $y, 17, $muted, 'right', 0.11, 1.6);
                $c->icon($icon, $statsRight - $labelWidth - 28, $y - 1, 19, $rgb, 0.13);

                $size = 36.0;
                while ($size > 18 && $c->textWidth($value, $size, 0.8, 0.125) > $statsW) {
                    $size -= 2;
                }
                $c->text($value, $statsRight, $y + 30, $size, $rgb, 'right', 0.125, 0.8);
                if ($i < count($rows) - 1) {
                    $c->rule($left, $y + 82, $statsW, CardPalette::GLASS, 0.10);
                }
            }

            CardChrome::footer($c, $left, $right, 416, (string) ($d['time'] ?? ''), $accent);

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

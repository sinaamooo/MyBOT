<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Shaping;

use EmojiMaker\Text\Font\Gdef;
use EmojiMaker\Text\Font\GlyphBuffer;
use EmojiMaker\Text\Font\TrueTypeFont;

/**
 * Shapes a (multi-line) string into positioned glyphs:
 *  1. bidi levels (Persian RTL + Latin/number LTR runs)
 *  2. font fallback per character
 *  3. Arabic joining analysis (isol/init/medi/fina) + GSUB (rlig = lam-alef ...)
 *     or Presentation Forms fallback when the font has no GSUB for Arabic
 *  4. GPOS kerning and mark attachment
 *  5. letter spacing (kashida/tatweel stretching inside connected words)
 *  6. visual reordering
 */
final class TextShaper
{
    private const F_CCMP = 1, F_LOCL = 2, F_ISOL = 4, F_FINA = 8, F_MEDI = 16, F_INIT = 32,
        F_RLIG = 64, F_CALT = 128, F_LIGA = 256, F_CLIG = 512, F_KERN = 1024, F_MARK = 2048,
        F_MKMK = 4096, F_MSET = 8192, F_DIST = 16384;

    private const FEATURE_BITS = [
        'ccmp' => self::F_CCMP, 'locl' => self::F_LOCL, 'isol' => self::F_ISOL, 'fina' => self::F_FINA,
        'medi' => self::F_MEDI, 'init' => self::F_INIT, 'rlig' => self::F_RLIG, 'calt' => self::F_CALT,
        'liga' => self::F_LIGA, 'clig' => self::F_CLIG, 'kern' => self::F_KERN, 'mark' => self::F_MARK,
        'mkmk' => self::F_MKMK, 'mset' => self::F_MSET, 'dist' => self::F_DIST,
    ];

    /** @param list<TrueTypeFont> $fonts primary font first, then fallbacks */
    public function __construct(private array $fonts)
    {
        if (!$fonts) {
            throw new \InvalidArgumentException('At least one font is required');
        }
    }

    /** @return list<TrueTypeFont> */
    public function fonts(): array
    {
        return $this->fonts;
    }

    /**
     * @return list<array{glyphs:list<array<string,mixed>>,width:float,ascent:float,descent:float,missing:list<int>,base:int}>
     */
    public function shape(string $text, float $letterSpacingEm = 0.0, bool $kashida = true): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = [];
        foreach (explode("\n", $text) as $line) {
            $lines[] = $this->shapeLine($line, $letterSpacingEm, $kashida);
        }
        return $lines;
    }

    /** @return array{glyphs:list<array<string,mixed>>,width:float,ascent:float,descent:float,missing:list<int>,base:int} */
    private function shapeLine(string $line, float $spacingEm, bool $kashida): array
    {
        $cps = Unicode::codepoints($line);
        $primary = $this->fonts[0];
        $ascent = $primary->ascender() / $primary->unitsPerEm();
        $descent = $primary->descender() / $primary->unitsPerEm();
        if (!$cps) {
            return ['glyphs' => [], 'width' => 0.0, 'ascent' => $ascent, 'descent' => $descent, 'missing' => [], 'base' => 0];
        }
        $bidi = Bidi::levels($cps);
        $levels = $bidi['levels'];
        $n = count($cps);

        // mirroring for RTL characters
        $shapedCps = $cps;
        for ($i = 0; $i < $n; $i++) {
            if ($levels[$i] % 2 === 1) {
                $shapedCps[$i] = Unicode::mirror($cps[$i]);
            }
        }

        // font per character (marks and controls follow their base)
        $fontIdx = [];
        $missing = [];
        for ($i = 0; $i < $n; $i++) {
            $cp = $shapedCps[$i];
            $bc = Unicode::bidiClass($cp);
            if (($bc === Unicode::NSM || $bc === Unicode::BN) && $i > 0) {
                $fontIdx[$i] = $fontIdx[$i - 1];
                continue;
            }
            $chosen = -1;
            foreach ($this->fonts as $fi => $font) {
                if ($font->hasChar($cp)) {
                    $chosen = $fi;
                    break;
                }
            }
            if ($chosen < 0) {
                if (!in_array($cp, [0x20, 0x200C, 0x200D, 0xFE0F, 0x200E, 0x200F], true)) {
                    $missing[] = $cp;
                }
                $chosen = 0;
            }
            $fontIdx[$i] = $chosen;
        }

        // joining analysis on the whole line (logical order)
        $joinLeft = array_fill(0, $n, false);
        $forms = array_fill(0, $n, 0); // 0 none, 1 isol, 2 fina, 3 init, 4 medi
        $jt = [];
        foreach ($shapedCps as $i => $cp) {
            $jt[$i] = Unicode::joiningType($cp);
        }
        for ($i = 0; $i < $n; $i++) {
            $t = $jt[$i];
            if ($t !== Unicode::JT_D && $t !== Unicode::JT_R) {
                continue;
            }
            $p = $i - 1;
            while ($p >= 0 && $jt[$p] === Unicode::JT_T) {
                $p--;
            }
            $q = $i + 1;
            while ($q < $n && $jt[$q] === Unicode::JT_T) {
                $q++;
            }
            $right = $p >= 0 && ($jt[$p] === Unicode::JT_D || $jt[$p] === Unicode::JT_C);
            $left = $t === Unicode::JT_D && $q < $n && in_array($jt[$q], [Unicode::JT_D, Unicode::JT_R, Unicode::JT_C], true);
            $joinLeft[$i] = $left;
            $forms[$i] = $right && $left ? 4 : ($left ? 3 : ($right ? 2 : 1));
        }
        for ($i = 0; $i < $n; $i++) {
            if ($jt[$i] === Unicode::JT_C) {
                $joinLeft[$i] = true;
            }
        }

        // itemize: same level + same font + same script class
        $items = [];
        $start = 0;
        for ($i = 1; $i <= $n; $i++) {
            if ($i === $n || $levels[$i] !== $levels[$start] || $fontIdx[$i] !== $fontIdx[$start]
                || $this->scriptClass($shapedCps[$i]) !== $this->scriptClass($shapedCps[$start])) {
                $items[] = [$start, $i];
                $start = $i;
            }
        }

        $glyphs = [];
        foreach ($items as [$a, $b]) {
            $font = $this->fonts[$fontIdx[$a]];
            $isArabic = false;
            for ($k = $a; $k < $b; $k++) {
                if (Unicode::isArabicScript($shapedCps[$k])) {
                    $isArabic = true;
                    break;
                }
            }
            $offset = count($glyphs);
            foreach ($this->shapeItem($font, $fontIdx[$a], $shapedCps, $a, $b, $forms, $joinLeft, $isArabic, $spacingEm, $kashida) as $g) {
                $g['lvl'] = $levels[$g['cl']];
                if ($g['att'] >= 0) {
                    $g['att'] += $offset;
                }
                $glyphs[] = $g;
            }
        }

        // visual order + pen positions
        $glyphLevels = array_map(static fn ($g) => $g['lvl'], $glyphs);
        $order = Bidi::reorder($glyphLevels);
        $pen = 0.0;
        $positions = [];
        foreach ($order as $gi) {
            $g = $glyphs[$gi];
            if ($g['mark']) {
                continue;
            }
            $positions[$gi] = [$pen + $g['xo'], $g['yo']];
            $pen += $g['xa'];
        }
        // marks
        foreach ($glyphs as $gi => $g) {
            if (!$g['mark']) {
                continue;
            }
            $baseIdx = $g['att'];
            if ($baseIdx < 0 || !isset($positions[$baseIdx])) {
                // fallback: centre over previous non-mark glyph (logical)
                for ($k = $gi - 1; $k >= 0; $k--) {
                    if (!$glyphs[$k]['mark'] && isset($positions[$k])) {
                        $baseIdx = $k;
                        break;
                    }
                }
                if ($baseIdx < 0) {
                    $positions[$gi] = [0.0, 0.0];
                    continue;
                }
                $base = $glyphs[$baseIdx];
                $center = $positions[$baseIdx][0] + $base['xa'] / 2;
                $mb = $g['bboxW'];
                $positions[$gi] = [$center - $mb['cx'], $positions[$baseIdx][1]];
                continue;
            }
            $positions[$gi] = [$positions[$baseIdx][0] + $g['xo'], $positions[$baseIdx][1] + $g['yo']];
        }

        $out = [];
        foreach ($order as $gi) {
            $g = $glyphs[$gi];
            [$x, $y] = $positions[$gi] ?? [0.0, 0.0];
            $out[] = [
                'font' => $g['font'],
                'g' => $g['g'],
                'x' => $x,
                'y' => $y,
                'sx' => $g['sx'],
                'cl' => $g['cl'],
                'rtl' => $g['lvl'] % 2 === 1,
            ];
        }
        // maximum ascent/descent of used fonts
        foreach (array_unique($fontIdx) as $fi) {
            $f = $this->fonts[$fi];
            $ascent = max($ascent, $f->ascender() / $f->unitsPerEm());
            $descent = min($descent, $f->descender() / $f->unitsPerEm());
        }
        return ['glyphs' => $out, 'width' => $pen, 'ascent' => $ascent, 'descent' => $descent, 'missing' => array_values(array_unique($missing)), 'base' => $bidi['base']];
    }

    private function scriptClass(int $cp): int
    {
        if (Unicode::isArabicScript($cp)) {
            return 1;
        }
        $bc = Unicode::bidiClass($cp);
        if ($bc === Unicode::L) {
            return 2;
        }
        return 0; // common
    }

    /**
     * Shapes one item (logical order). Returned positions are in em.
     * @param list<int> $cps
     * @param list<int> $forms
     * @param list<bool> $joinLeft
     * @return list<array<string,mixed>>
     */
    private function shapeItem(TrueTypeFont $font, int $fontIndex, array $cps, int $a, int $b, array $forms, array $joinLeft, bool $arabic, float $spacingEm, bool $kashida): array
    {
        $upem = $font->unitsPerEm();
        $gsub = $font->gsub();
        $gpos = $font->gpos();
        $gdef = $font->gdef();
        $script = $arabic ? 'arab' : 'latn';
        $lang = $arabic ? 'FAR ' : null;

        $gsubFeatures = $arabic
            ? ['ccmp', 'locl', 'isol', 'fina', 'medi', 'init', 'rlig', 'calt', 'liga', 'clig', 'mset']
            : ['ccmp', 'locl', 'rlig', 'liga', 'clig', 'calt'];
        $gsubLookups = $gsub ? $gsub->lookupsFor($script, $lang, $gsubFeatures) : [];
        $hasArabicForms = false;
        foreach ($gsubLookups as $tags) {
            if (array_intersect($tags, ['init', 'medi', 'fina'])) {
                $hasArabicForms = true;
                break;
            }
        }
        $usePresentationForms = $arabic && !$hasArabicForms;

        $buffer = new GlyphBuffer();
        $global = self::F_CCMP | self::F_LOCL | self::F_RLIG | self::F_CALT | self::F_LIGA | self::F_CLIG
            | self::F_MSET | self::F_KERN | self::F_MARK | self::F_MKMK | self::F_DIST;
        $pf = Unicode::presentationForms();
        $lamAlef = Unicode::lamAlefForms();

        for ($i = $a; $i < $b; $i++) {
            $cp = $cps[$i];
            $bc = Unicode::bidiClass($cp);
            if ($bc === Unicode::BN || $cp === 0xFE0F || $cp === 0xFE0E) {
                continue; // ZWNJ / ZWJ / marks of direction are invisible
            }
            $formBit = match ($forms[$i]) {
                1 => self::F_ISOL,
                2 => self::F_FINA,
                3 => self::F_INIT,
                4 => self::F_MEDI,
                default => 0,
            };
            $target = $cp;
            if ($usePresentationForms && isset($pf[$cp]) && $forms[$i] > 0) {
                // lam + alef ligature
                if ($cp === 0x0644 && $i + 1 < $b && isset($lamAlef[$cps[$i + 1]])) {
                    $lig = $lamAlef[$cps[$i + 1]][$forms[$i] === 4 || $forms[$i] === 2 ? 1 : 0];
                    if ($font->hasChar($lig)) {
                        $buffer->push(['g' => $font->glyphIndex($lig), 'cl' => $i, 'm' => $global, 'join' => false, 'cp' => $lig]);
                        $i++;
                        continue;
                    }
                }
                $candidate = $pf[$cp][$forms[$i] - 1] ?? 0;
                if ($candidate === 0 && $forms[$i] === 4) {
                    $candidate = $pf[$cp][1]; // medial missing -> final
                } elseif ($candidate === 0 && $forms[$i] === 3) {
                    $candidate = $pf[$cp][0];
                }
                if ($candidate && $font->hasChar($candidate)) {
                    $target = $candidate;
                }
            }
            $gid = $font->glyphIndex($target);
            $buffer->push(['g' => $gid, 'cl' => $i, 'm' => $global | $formBit, 'join' => $joinLeft[$i], 'cp' => $cp]);
        }

        if ($gsub && $gsubLookups) {
            $masks = [];
            foreach ($gsubLookups as $li => $tags) {
                $mask = 0;
                foreach ($tags as $t) {
                    $mask |= self::FEATURE_BITS[$t] ?? 0;
                }
                if ($usePresentationForms) {
                    $mask &= ~(self::F_ISOL | self::F_FINA | self::F_MEDI | self::F_INIT);
                }
                if ($mask) {
                    $masks[$li] = $mask;
                }
            }
            $gsub->apply($buffer, $masks);
        }

        // advances
        foreach ($buffer->items as $k => $item) {
            $isMark = $gdef ? $gdef->isMark($item['g']) : Unicode::isMark((int) $item['cp']);
            $buffer->items[$k]['mark'] = $isMark;
            $buffer->items[$k]['xa'] = $isMark ? 0 : $font->advance($item['g']);
        }

        if ($gpos) {
            $posLookups = $gpos->lookupsFor($script, $lang, ['kern', 'mark', 'mkmk', 'dist']);
            $masks = [];
            foreach ($posLookups as $li => $tags) {
                $mask = 0;
                foreach ($tags as $t) {
                    $mask |= self::FEATURE_BITS[$t] ?? 0;
                }
                if ($mask) {
                    $masks[$li] = $mask;
                }
            }
            if ($masks) {
                $gpos->apply($buffer, $masks);
            }
        }

        // letter spacing / kashida (logical order)
        $spacing = $spacingEm * $upem;
        $tatweel = $font->glyphIndex(0x0640);
        $tatweelAdv = $tatweel ? $font->advance($tatweel) : 0;
        $result = [];
        $pendingKashida = null;
        $indexMap = [];
        $count = count($buffer->items);
        $lastBase = -1;
        foreach ($buffer->items as $k => $item) {
            if (!$item['mark']) {
                $lastBase = $k;
            }
        }
        foreach ($buffer->items as $k => $item) {
            if (!$item['mark'] && $pendingKashida !== null) {
                $result[] = $pendingKashida;
                $pendingKashida = null;
            }
            $indexMap[$k] = count($result);
            $item['sx'] = 1.0;
            if (!$item['mark'] && $k !== $lastBase && abs($spacing) > 0.01) {
                if ($item['join'] && $arabic) {
                    if ($spacing > 0 && $kashida && $tatweel && $tatweelAdv > 0) {
                        $pendingKashida = [
                            'g' => $tatweel, 'cl' => $item['cl'], 'm' => 0, 'xa' => $spacing, 'xo' => 0, 'yo' => 0,
                            'att' => -1, 'join' => true, 'mark' => false, 'sx' => $spacing / $tatweelAdv, 'cp' => 0x0640,
                        ];
                    }
                } else {
                    $item['xa'] += $spacing;
                }
            }
            $result[] = $item;
        }
        if ($pendingKashida !== null) {
            $result[] = $pendingKashida;
        }

        $out = [];
        foreach ($result as $item) {
            $att = $item['att'];
            $bb = $font->glyphBounds($item['g']);
            $out[] = [
                'font' => $fontIndex,
                'g' => $item['g'],
                'cl' => $item['cl'],
                'xa' => $item['xa'] / $upem,
                'xo' => $item['xo'] / $upem,
                'yo' => $item['yo'] / $upem,
                'att' => $att,
                'mark' => $item['mark'],
                'sx' => $item['sx'] ?? 1.0,
                'bboxW' => ['cx' => $bb ? (($bb[0] + $bb[2]) / 2) / $upem : 0.0],
                '_att' => $att >= 0 ? ($indexMap[$att] ?? -1) : -1,
            ];
        }
        foreach ($out as $k => $g) {
            $out[$k]['att'] = $g['_att'];
            unset($out[$k]['_att']);
        }
        return $out;
    }
}

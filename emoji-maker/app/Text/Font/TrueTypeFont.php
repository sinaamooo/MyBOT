<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Font;

use EmojiMaker\Geometry\Path;

/**
 * Pure PHP TrueType (glyf based) font parser.
 * Reads cmap (formats 4 and 12), hmtx, glyf (simple + composite glyphs) and
 * exposes the OpenType layout tables (GSUB/GPOS/GDEF) for text shaping.
 * CFF based OpenType (.otf with "OTTO") is not supported and is rejected.
 */
final class TrueTypeFont extends BinaryReader
{
    /** @var array<string,array{offset:int,length:int}> */
    private array $tables = [];
    private int $unitsPerEm = 1000;
    private int $indexToLocFormat = 0;
    private int $numGlyphs = 0;
    private int $numHMetrics = 0;
    private int $ascender = 800;
    private int $descender = -200;
    private int $lineGap = 0;
    /** @var array{0:int,1:int,2:int,3:int} */
    private array $bbox = [0, 0, 0, 0];

    /** @var list<array{0:int,1:int,2:int}> cmap ranges [start,end,kind/offset] */
    private array $cmapSegments = [];
    private int $cmapFormat = 0;
    private int $cmapOffset = 0;
    /** @var array<int,int> */
    private array $cmapCache = [];
    /** @var array<int,Path> */
    private array $pathCache = [];
    /** @var array<int,array{0:int,1:int,2:int,3:int}|null> */
    private array $bboxCache = [];
    private ?OpenTypeLayout $gsub = null;
    private ?OpenTypeLayout $gpos = null;
    private ?Gdef $gdef = null;
    private bool $layoutLoaded = false;
    private string $familyName = '';

    public function __construct(private string $path)
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new FontException('Font file not readable');
        }
        $size = filesize($path);
        if ($size === false || $size < 64 || $size > 40 * 1024 * 1024) {
            throw new FontException('Invalid font file size');
        }
        parent::__construct((string) file_get_contents($path));
        $this->parseDirectory();
        $this->parseHead();
        $this->parseHhea();
        $this->parseMaxp();
        $this->parseCmap();
        $this->parseName();
    }

    public static function isSupportedFile(string $path): bool
    {
        try {
            $f = new self($path);
            return $f->numGlyphs > 0 && $f->glyphIndex(0x41) + $f->glyphIndex(0x0627) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseDirectory(): void
    {
        $version = $this->u32(0);
        $tag = $this->tag(0);
        if ($tag === 'OTTO') {
            throw new FontException('CFF based OpenType fonts are not supported - please use a TrueType (.ttf) font');
        }
        if ($version !== 0x00010000 && $tag !== 'true') {
            throw new FontException('Not a TrueType font');
        }
        $num = $this->u16(4);
        if ($num < 4 || $num > 100) {
            throw new FontException('Corrupt table directory');
        }
        for ($i = 0; $i < $num; $i++) {
            $rec = 12 + $i * 16;
            $t = $this->tag($rec);
            $off = $this->u32($rec + 8);
            $len = $this->u32($rec + 12);
            if ($off + $len > $this->length() + 3) {
                throw new FontException('Table outside of file: ' . $t);
            }
            $this->tables[$t] = ['offset' => $off, 'length' => $len];
        }
        foreach (['head', 'hhea', 'maxp', 'hmtx', 'cmap', 'loca', 'glyf'] as $required) {
            if (!isset($this->tables[$required])) {
                throw new FontException('Missing required table ' . $required);
            }
        }
    }

    public function table(string $tag): ?int
    {
        return $this->tables[$tag]['offset'] ?? null;
    }

    private function parseHead(): void
    {
        $o = $this->tables['head']['offset'];
        $this->unitsPerEm = max(16, $this->u16($o + 18));
        $this->bbox = [$this->s16($o + 36), $this->s16($o + 38), $this->s16($o + 40), $this->s16($o + 42)];
        $this->indexToLocFormat = $this->s16($o + 50);
    }

    private function parseHhea(): void
    {
        $o = $this->tables['hhea']['offset'];
        $this->ascender = $this->s16($o + 4);
        $this->descender = $this->s16($o + 6);
        $this->lineGap = $this->s16($o + 8);
        $this->numHMetrics = $this->u16($o + 34);
        if (isset($this->tables['OS/2']) && $this->tables['OS/2']['length'] >= 78) {
            $os2 = $this->tables['OS/2']['offset'];
            $typoAsc = $this->s16($os2 + 68);
            $typoDesc = $this->s16($os2 + 70);
            if ($typoAsc > 0 && $typoDesc < 0 && $this->ascender <= 0) {
                $this->ascender = $typoAsc;
                $this->descender = $typoDesc;
            }
        }
    }

    private function parseMaxp(): void
    {
        $this->numGlyphs = $this->u16($this->tables['maxp']['offset'] + 4);
    }

    private function parseName(): void
    {
        if (!isset($this->tables['name'])) {
            return;
        }
        $o = $this->tables['name']['offset'];
        $count = $this->u16($o + 2);
        $strOff = $o + $this->u16($o + 4);
        $best = '';
        for ($i = 0; $i < $count; $i++) {
            $r = $o + 6 + $i * 12;
            $platform = $this->u16($r);
            $nameId = $this->u16($r + 6);
            if ($nameId !== 1 && $nameId !== 4) {
                continue;
            }
            $len = $this->u16($r + 8);
            $off = $this->u16($r + 10);
            $raw = $this->bytes($strOff + $off, $len);
            $str = ($platform === 3 || $platform === 0) ? (string) @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') : $raw;
            if ($nameId === 1 && $str !== '') {
                $best = $str;
                break;
            }
            if ($best === '') {
                $best = $str;
            }
        }
        $this->familyName = trim(preg_replace('/[^\P{C}]+/u', '', $best) ?? '');
    }

    private function parseCmap(): void
    {
        $o = $this->tables['cmap']['offset'];
        $n = $this->u16($o + 2);
        $candidates = [];
        for ($i = 0; $i < $n; $i++) {
            $r = $o + 4 + $i * 8;
            $platform = $this->u16($r);
            $encoding = $this->u16($r + 2);
            $sub = $o + $this->u32($r + 4);
            $format = $this->u16($sub);
            $score = match (true) {
                $platform === 3 && $encoding === 10 && $format === 12 => 100,
                $platform === 0 && $format === 12 => 90,
                $platform === 3 && $encoding === 1 && $format === 4 => 80,
                $platform === 0 && $format === 4 => 70,
                $platform === 3 && $encoding === 0 && $format === 4 => 10,
                default => 0,
            };
            if ($score > 0) {
                $candidates[] = [$score, $sub, $format];
            }
        }
        if (!$candidates) {
            throw new FontException('No usable Unicode cmap');
        }
        usort($candidates, static fn ($a, $b) => $b[0] <=> $a[0]);
        [, $sub, $format] = $candidates[0];
        $this->cmapFormat = $format;
        $this->cmapOffset = $sub;
        if ($format === 4) {
            $segX2 = $this->u16($sub + 6);
            $seg = intdiv($segX2, 2);
            $ends = $this->u16Array($sub + 14, $seg);
            $starts = $this->u16Array($sub + 16 + $segX2, $seg);
            $deltaBase = $sub + 16 + $segX2 * 2;
            $rangeBase = $sub + 16 + $segX2 * 3;
            for ($i = 0; $i < $seg; $i++) {
                $this->cmapSegments[] = [$starts[$i], $ends[$i], $i, $deltaBase + $i * 2, $rangeBase + $i * 2];
            }
        } else {
            $groups = $this->u32($sub + 12);
            for ($i = 0; $i < $groups; $i++) {
                $g = $sub + 16 + $i * 12;
                $this->cmapSegments[] = [$this->u32($g), $this->u32($g + 4), $this->u32($g + 8), 0, 0];
            }
        }
    }

    /** Maps a Unicode code point to a glyph id (0 = .notdef / missing). */
    public function glyphIndex(int $cp): int
    {
        if (isset($this->cmapCache[$cp])) {
            return $this->cmapCache[$cp];
        }
        $gid = 0;
        $lo = 0;
        $hi = count($this->cmapSegments) - 1;
        while ($lo <= $hi) {
            $mid = ($lo + $hi) >> 1;
            $s = $this->cmapSegments[$mid];
            if ($cp < $s[0]) {
                $hi = $mid - 1;
            } elseif ($cp > $s[1]) {
                $lo = $mid + 1;
            } else {
                if ($this->cmapFormat === 12) {
                    $gid = $s[2] + ($cp - $s[0]);
                } else {
                    $delta = $this->s16($s[3]);
                    $rangeOffset = $this->u16($s[4]);
                    if ($rangeOffset === 0) {
                        $gid = ($cp + $delta) & 0xFFFF;
                    } else {
                        $addr = $s[4] + $rangeOffset + ($cp - $s[0]) * 2;
                        $g = $this->u16($addr);
                        $gid = $g === 0 ? 0 : (($g + $delta) & 0xFFFF);
                    }
                }
                break;
            }
        }
        if ($gid >= $this->numGlyphs) {
            $gid = 0;
        }
        return $this->cmapCache[$cp] = $gid;
    }

    public function hasChar(int $cp): bool
    {
        return $this->glyphIndex($cp) > 0;
    }

    public function advance(int $gid): int
    {
        $o = $this->tables['hmtx']['offset'];
        if ($this->numHMetrics === 0) {
            return 0;
        }
        $i = min($gid, $this->numHMetrics - 1);
        return $this->u16($o + $i * 4);
    }

    private function glyphLocation(int $gid): ?array
    {
        if ($gid < 0 || $gid >= $this->numGlyphs) {
            return null;
        }
        $loca = $this->tables['loca']['offset'];
        if ($this->indexToLocFormat === 0) {
            $start = $this->u16($loca + $gid * 2) * 2;
            $end = $this->u16($loca + $gid * 2 + 2) * 2;
        } else {
            $start = $this->u32($loca + $gid * 4);
            $end = $this->u32($loca + $gid * 4 + 4);
        }
        if ($end <= $start) {
            return null; // empty glyph (space)
        }
        return [$this->tables['glyf']['offset'] + $start, $end - $start];
    }

    /** Glyph bounding box in font units (xMin,yMin,xMax,yMax) or null if empty. */
    public function glyphBounds(int $gid): ?array
    {
        if (array_key_exists($gid, $this->bboxCache)) {
            return $this->bboxCache[$gid];
        }
        $loc = $this->glyphLocation($gid);
        if ($loc === null) {
            return $this->bboxCache[$gid] = null;
        }
        $o = $loc[0];
        return $this->bboxCache[$gid] = [$this->s16($o + 2), $this->s16($o + 4), $this->s16($o + 6), $this->s16($o + 8)];
    }

    /**
     * Raw contours: list of contours, each list of [x, y, onCurve] in font units.
     * @return list<list<array{0:float,1:float,2:bool}>>
     */
    public function glyphContours(int $gid, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }
        $loc = $this->glyphLocation($gid);
        if ($loc === null) {
            return [];
        }
        [$o, $len] = $loc;
        $numContours = $this->s16($o);
        if ($numContours >= 0) {
            return $this->simpleGlyph($o, $numContours);
        }
        return $this->compositeGlyph($o + 10, $o + $len, $depth);
    }

    /** @return list<list<array{0:float,1:float,2:bool}>> */
    private function simpleGlyph(int $o, int $numContours): array
    {
        if ($numContours === 0) {
            return [];
        }
        $endPts = $this->u16Array($o + 10, $numContours);
        $numPoints = end($endPts) + 1;
        if ($numPoints <= 0 || $numPoints > 20000) {
            return [];
        }
        $p = $o + 10 + $numContours * 2;
        $instrLen = $this->u16($p);
        $p += 2 + $instrLen;
        $d = $this->data;
        $flags = [];
        while (count($flags) < $numPoints) {
            $f = ord($d[$p++] ?? "\0");
            $flags[] = $f;
            if ($f & 8) {
                $repeat = ord($d[$p++] ?? "\0");
                for ($r = 0; $r < $repeat; $r++) {
                    $flags[] = $f;
                }
            }
        }
        $xs = [];
        $x = 0;
        for ($i = 0; $i < $numPoints; $i++) {
            $f = $flags[$i];
            if ($f & 2) {
                $dx = ord($d[$p++] ?? "\0");
                $x += ($f & 16) ? $dx : -$dx;
            } elseif (!($f & 16)) {
                $v = (ord($d[$p] ?? "\0") << 8) | ord($d[$p + 1] ?? "\0");
                $p += 2;
                $x += $v >= 0x8000 ? $v - 0x10000 : $v;
            }
            $xs[] = $x;
        }
        $ys = [];
        $y = 0;
        for ($i = 0; $i < $numPoints; $i++) {
            $f = $flags[$i];
            if ($f & 4) {
                $dy = ord($d[$p++] ?? "\0");
                $y += ($f & 32) ? $dy : -$dy;
            } elseif (!($f & 32)) {
                $v = (ord($d[$p] ?? "\0") << 8) | ord($d[$p + 1] ?? "\0");
                $p += 2;
                $y += $v >= 0x8000 ? $v - 0x10000 : $v;
            }
            $ys[] = $y;
        }
        $contours = [];
        $start = 0;
        foreach ($endPts as $end) {
            $c = [];
            for ($i = $start; $i <= $end && $i < $numPoints; $i++) {
                $c[] = [(float) $xs[$i], (float) $ys[$i], ($flags[$i] & 1) === 1];
            }
            if (count($c) > 1) {
                $contours[] = $c;
            }
            $start = $end + 1;
        }
        return $contours;
    }

    /** @return list<list<array{0:float,1:float,2:bool}>> */
    private function compositeGlyph(int $p, int $limit, int $depth): array
    {
        $result = [];
        do {
            if ($p + 4 > $limit) {
                break;
            }
            $flags = $this->u16($p);
            $glyph = $this->u16($p + 2);
            $p += 4;
            if ($flags & 0x0001) { // ARG_1_AND_2_ARE_WORDS
                $a1 = ($flags & 0x0002) ? $this->s16($p) : $this->u16($p);
                $a2 = ($flags & 0x0002) ? $this->s16($p + 2) : $this->u16($p + 2);
                $p += 4;
            } else {
                $a1 = ($flags & 0x0002) ? $this->s8($p) : $this->u8($p);
                $a2 = ($flags & 0x0002) ? $this->s8($p + 1) : $this->u8($p + 1);
                $p += 2;
            }
            $a = 1.0; $b = 0.0; $c = 0.0; $dd = 1.0;
            if ($flags & 0x0008) { // WE_HAVE_A_SCALE
                $a = $dd = $this->f2dot14($p);
                $p += 2;
            } elseif ($flags & 0x0040) { // X_AND_Y_SCALE
                $a = $this->f2dot14($p);
                $dd = $this->f2dot14($p + 2);
                $p += 4;
            } elseif ($flags & 0x0080) { // TWO_BY_TWO
                $a = $this->f2dot14($p);
                $b = $this->f2dot14($p + 2);
                $c = $this->f2dot14($p + 4);
                $dd = $this->f2dot14($p + 6);
                $p += 8;
            }
            $dx = ($flags & 0x0002) ? (float) $a1 : 0.0; // point matching unsupported -> 0 offset
            $dy = ($flags & 0x0002) ? (float) $a2 : 0.0;
            foreach ($this->glyphContours($glyph, $depth + 1) as $contour) {
                $t = [];
                foreach ($contour as [$x, $y, $on]) {
                    $t[] = [$a * $x + $c * $y + $dx, $b * $x + $dd * $y + $dy, $on];
                }
                $result[] = $t;
            }
        } while ($flags & 0x0020); // MORE_COMPONENTS
        return $result;
    }

    /** Glyph outline as a Path in font units (y axis pointing up). */
    public function glyphPath(int $gid): Path
    {
        if (isset($this->pathCache[$gid])) {
            return $this->pathCache[$gid];
        }
        $path = new Path();
        foreach ($this->glyphContours($gid) as $pts) {
            $n = count($pts);
            // find a starting on-curve point
            $startIdx = -1;
            for ($i = 0; $i < $n; $i++) {
                if ($pts[$i][2]) {
                    $startIdx = $i;
                    break;
                }
            }
            if ($startIdx === -1) {
                // all off-curve: start at midpoint of first two
                $sx = ($pts[0][0] + $pts[1][0]) / 2;
                $sy = ($pts[0][1] + $pts[1][1]) / 2;
                $ordered = array_merge(array_slice($pts, 1), [$pts[0]]);
                array_unshift($ordered, [$sx, $sy, true]);
            } else {
                $ordered = array_merge(array_slice($pts, $startIdx), array_slice($pts, 0, $startIdx));
            }
            $m = count($ordered);
            $path->moveTo($ordered[0][0], $ordered[0][1]);
            $ctrl = null;
            for ($k = 1; $k <= $m; $k++) {
                $pt = $ordered[$k % $m];
                if ($pt[2]) {
                    if ($ctrl === null) {
                        if ($k < $m) {
                            $path->lineTo($pt[0], $pt[1]);
                        }
                    } else {
                        $path->quadTo($ctrl[0], $ctrl[1], $pt[0], $pt[1]);
                        $ctrl = null;
                    }
                } else {
                    if ($ctrl !== null) {
                        $mx = ($ctrl[0] + $pt[0]) / 2;
                        $my = ($ctrl[1] + $pt[1]) / 2;
                        $path->quadTo($ctrl[0], $ctrl[1], $mx, $my);
                    }
                    $ctrl = $pt;
                }
            }
            if ($ctrl !== null) {
                $path->quadTo($ctrl[0], $ctrl[1], $ordered[0][0], $ordered[0][1]);
            }
            $path->close();
        }
        return $this->pathCache[$gid] = $path;
    }

    private function loadLayout(): void
    {
        if ($this->layoutLoaded) {
            return;
        }
        $this->layoutLoaded = true;
        try {
            if (isset($this->tables['GDEF'])) {
                $this->gdef = new Gdef($this, $this->tables['GDEF']['offset']);
            }
            if (isset($this->tables['GSUB'])) {
                $this->gsub = new OpenTypeLayout($this, $this->tables['GSUB']['offset'], 'GSUB', $this->gdef);
            }
            if (isset($this->tables['GPOS'])) {
                $this->gpos = new OpenTypeLayout($this, $this->tables['GPOS']['offset'], 'GPOS', $this->gdef);
            }
        } catch (\Throwable) {
            // Broken layout tables: shaping falls back to presentation forms.
            $this->gsub = $this->gpos = null;
        }
    }

    public function gsub(): ?OpenTypeLayout
    {
        $this->loadLayout();
        return $this->gsub;
    }

    public function gpos(): ?OpenTypeLayout
    {
        $this->loadLayout();
        return $this->gpos;
    }

    public function gdef(): ?Gdef
    {
        $this->loadLayout();
        return $this->gdef;
    }

    public function unitsPerEm(): int { return $this->unitsPerEm; }
    public function ascender(): int { return $this->ascender; }
    public function descender(): int { return $this->descender; }
    public function lineGap(): int { return $this->lineGap; }
    public function numGlyphs(): int { return $this->numGlyphs; }
    public function familyName(): string { return $this->familyName; }
    public function filePath(): string { return $this->path; }
    /** @return array{0:int,1:int,2:int,3:int} */
    public function bbox(): array { return $this->bbox; }

    public function supportsPersian(): bool
    {
        foreach ([0x0627, 0x0628, 0x067E, 0x0686, 0x06A9, 0x06AF, 0x06CC] as $cp) {
            if (!$this->hasChar($cp)) {
                return false;
            }
        }
        return true;
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Text\Font;

/**
 * OpenType GSUB / GPOS interpreter (subset sufficient for Persian/Arabic and
 * Latin display fonts):
 *  GSUB: 1 single, 2 multiple, 4 ligature, 5 context, 6 chaining context, 7 extension
 *  GPOS: 1 single, 2 pair (kerning), 4 mark-to-base, 5 mark-to-ligature,
 *        6 mark-to-mark, 7/8 (chaining) context, 9 extension
 */
final class OpenTypeLayout
{
    private int $scriptList;
    private int $featureList;
    private int $lookupList;
    private int $depth = 0;

    public function __construct(
        private TrueTypeFont $r,
        private int $base,
        private string $kind,
        private ?Gdef $gdef,
    ) {
        $this->scriptList = $base + $r->u16($base + 4);
        $this->featureList = $base + $r->u16($base + 6);
        $this->lookupList = $base + $r->u16($base + 8);
    }

    /** @return list<string> */
    public function scripts(): array
    {
        $out = [];
        $n = $this->r->u16($this->scriptList);
        for ($i = 0; $i < $n; $i++) {
            $out[] = $this->r->tag($this->scriptList + 2 + $i * 6);
        }
        return $out;
    }

    /**
     * Lookups (index => list of feature tags) for a script/language and feature set.
     * @param list<string> $features
     * @return array<int,list<string>>
     */
    public function lookupsFor(string $script, ?string $lang, array $features): array
    {
        $scriptOffset = null;
        $n = $this->r->u16($this->scriptList);
        $found = [];
        for ($i = 0; $i < $n; $i++) {
            $rec = $this->scriptList + 2 + $i * 6;
            $found[$this->r->tag($rec)] = $this->scriptList + $this->r->u16($rec + 4);
        }
        foreach ([$script, 'DFLT', 'dflt', 'latn'] as $candidate) {
            if (isset($found[$candidate])) {
                $scriptOffset = $found[$candidate];
                break;
            }
        }
        if ($scriptOffset === null) {
            return [];
        }
        $langSys = null;
        $defaultLs = $this->r->u16($scriptOffset);
        $lsCount = $this->r->u16($scriptOffset + 2);
        if ($lang !== null) {
            for ($i = 0; $i < $lsCount; $i++) {
                $rec = $scriptOffset + 4 + $i * 6;
                if ($this->r->tag($rec) === $lang) {
                    $langSys = $scriptOffset + $this->r->u16($rec + 4);
                    break;
                }
            }
        }
        if ($langSys === null) {
            if ($defaultLs) {
                $langSys = $scriptOffset + $defaultLs;
            } elseif ($lsCount > 0) {
                $langSys = $scriptOffset + $this->r->u16($scriptOffset + 4 + 4);
            } else {
                return [];
            }
        }
        $req = $this->r->u16($langSys + 2);
        $fCount = $this->r->u16($langSys + 4);
        $indices = $this->r->u16Array($langSys + 6, $fCount);
        if ($req !== 0xFFFF) {
            $indices[] = $req;
        }
        $result = [];
        $wanted = array_flip($features);
        foreach ($indices as $fi) {
            $rec = $this->featureList + 2 + $fi * 6;
            $tag = $this->r->tag($rec);
            if (!isset($wanted[$tag]) && $fi !== $req) {
                continue;
            }
            $feature = $this->featureList + $this->r->u16($rec + 4);
            $lc = $this->r->u16($feature + 2);
            foreach ($this->r->u16Array($feature + 4, $lc) as $li) {
                $result[$li][] = $tag;
            }
        }
        ksort($result);
        return $result;
    }

    /** @return array{type:int,flag:int,subtables:list<int>,markSet:int} */
    private function lookup(int $index): array
    {
        $count = $this->r->u16($this->lookupList);
        if ($index >= $count) {
            return ['type' => 0, 'flag' => 0, 'subtables' => [], 'markSet' => 0];
        }
        $o = $this->lookupList + $this->r->u16($this->lookupList + 2 + $index * 2);
        $type = $this->r->u16($o);
        $flag = $this->r->u16($o + 2);
        $n = $this->r->u16($o + 4);
        $subs = [];
        foreach ($this->r->u16Array($o + 6, $n) as $s) {
            $sub = $o + $s;
            $t = $type;
            $extType = $this->kind === 'GSUB' ? 7 : 9;
            if ($type === $extType) {
                $t = $this->r->u16($sub + 2);
                $sub += $this->r->u32($sub + 4);
            }
            $subs[] = ['t' => $t, 'o' => $sub];
        }
        $markSet = ($flag & 0x10) ? $this->r->u16($o + 6 + $n * 2) : 0;
        return ['type' => $type, 'flag' => $flag, 'subtables' => $subs, 'markSet' => $markSet];
    }

    private function skip(int $gid, int $flag, int $markSet): bool
    {
        if ($this->gdef === null) {
            return false;
        }
        $cls = $this->gdef->glyphClass($gid);
        if (($flag & 0x2) && $cls === Gdef::BASE) {
            return true;
        }
        if (($flag & 0x4) && $cls === Gdef::LIGATURE) {
            return true;
        }
        if ($cls === Gdef::MARK) {
            if ($flag & 0x8) {
                return true;
            }
            if ($flag & 0x10) {
                return !$this->gdef->inMarkSet($markSet, $gid);
            }
            $mat = ($flag & 0xFF00) >> 8;
            if ($mat && $this->gdef->markAttachClass($gid) !== $mat) {
                return true;
            }
        }
        return false;
    }

    private function nextIdx(GlyphBuffer $b, int $i, int $flag, int $ms): int
    {
        $n = $b->count();
        for ($j = $i + 1; $j < $n; $j++) {
            if (!$this->skip($b->items[$j]['g'], $flag, $ms)) {
                return $j;
            }
        }
        return -1;
    }

    private function prevIdx(GlyphBuffer $b, int $i, int $flag, int $ms): int
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!$this->skip($b->items[$j]['g'], $flag, $ms)) {
                return $j;
            }
        }
        return -1;
    }

    /**
     * Applies lookups in lookup-list order. $lookups: index => feature mask bits.
     * @param array<int,int> $lookups
     */
    public function apply(GlyphBuffer $b, array $lookups): void
    {
        ksort($lookups);
        foreach ($lookups as $index => $mask) {
            $lk = $this->lookup($index);
            if (!$lk['subtables']) {
                continue;
            }
            $i = 0;
            $guard = 0;
            while ($i < $b->count() && $guard++ < 4000) {
                $item = $b->items[$i];
                if (($item['m'] & $mask) === 0 || $this->skip($item['g'], $lk['flag'], $lk['markSet'])) {
                    $i++;
                    continue;
                }
                $next = $this->applyAt($b, $i, $lk);
                $i = $next ?? $i + 1;
            }
        }
    }

    /** @param array{type:int,flag:int,subtables:list<array{t:int,o:int}>,markSet:int} $lk */
    private function applyAt(GlyphBuffer $b, int $i, array $lk): ?int
    {
        foreach ($lk['subtables'] as $sub) {
            $res = $this->kind === 'GSUB'
                ? $this->gsubSubtable($b, $i, $sub['t'], $sub['o'], $lk)
                : $this->gposSubtable($b, $i, $sub['t'], $sub['o'], $lk);
            if ($res !== null) {
                return $res;
            }
        }
        return null;
    }

    private function applyNested(GlyphBuffer $b, int $pos, int $lookupIndex): void
    {
        if ($this->depth > 6 || $pos >= $b->count()) {
            return;
        }
        $this->depth++;
        try {
            $lk = $this->lookup($lookupIndex);
            if ($lk['subtables'] && !$this->skip($b->items[$pos]['g'], $lk['flag'], $lk['markSet'])) {
                $this->applyAt($b, $pos, $lk);
            }
        } finally {
            $this->depth--;
        }
    }

    // ------------------------------------------------------------------ GSUB

    private function gsubSubtable(GlyphBuffer $b, int $i, int $type, int $o, array $lk): ?int
    {
        $r = $this->r;
        $g = $b->items[$i]['g'];
        switch ($type) {
            case 1:
                $cov = Layout::coverage($r, $o + $r->u16($o + 2));
                if (!isset($cov[$g])) {
                    return null;
                }
                if ($r->u16($o) === 1) {
                    $b->items[$i]['g'] = ($g + $r->s16($o + 4)) & 0xFFFF;
                } else {
                    $b->items[$i]['g'] = $r->u16($o + 6 + $cov[$g] * 2);
                }
                return $i + 1;
            case 2:
                $cov = Layout::coverage($r, $o + $r->u16($o + 2));
                if (!isset($cov[$g])) {
                    return null;
                }
                $seq = $o + $r->u16($o + 6 + $cov[$g] * 2);
                $glyphs = $r->u16Array($seq + 2, $r->u16($seq));
                if (!$glyphs) {
                    return null;
                }
                $b->replaceWithSequence($i, $glyphs);
                return $i + count($glyphs);
            case 4:
                $cov = Layout::coverage($r, $o + $r->u16($o + 2));
                if (!isset($cov[$g])) {
                    return null;
                }
                $set = $o + $r->u16($o + 6 + $cov[$g] * 2);
                $ligCount = $r->u16($set);
                for ($l = 0; $l < $ligCount; $l++) {
                    $lig = $set + $r->u16($set + 2 + $l * 2);
                    $ligGlyph = $r->u16($lig);
                    $compCount = $r->u16($lig + 2);
                    $comps = $r->u16Array($lig + 4, $compCount - 1);
                    $matched = [];
                    $j = $i;
                    $ok = true;
                    foreach ($comps as $cg) {
                        $j = $this->nextIdx($b, $j, $lk['flag'], $lk['markSet']);
                        if ($j < 0 || $b->items[$j]['g'] !== $cg) {
                            $ok = false;
                            break;
                        }
                        $matched[] = $j;
                    }
                    if ($ok) {
                        $b->items[$i]['g'] = $ligGlyph;
                        if ($matched) {
                            $last = end($matched);
                            $b->items[$i]['join'] = $b->items[$last]['join'];
                        }
                        $b->removeIndices($matched);
                        return $i + 1;
                    }
                }
                return null;
            case 5:
            case 6:
                return $this->contextual($b, $i, $type, $o, $lk);
        }
        return null;
    }

    // ------------------------------------------------------------- context

    /** Generic (chaining) contextual matching shared by GSUB 5/6 and GPOS 7/8. */
    private function contextual(GlyphBuffer $b, int $i, int $type, int $o, array $lk): ?int
    {
        $r = $this->r;
        $format = $r->u16($o);
        $chain = ($type === 6 && $this->kind === 'GSUB') || ($type === 8 && $this->kind === 'GPOS');
        $g = $b->items[$i]['g'];
        $flag = $lk['flag'];
        $ms = $lk['markSet'];

        $rules = []; // each: [backtrackMatchers, inputMatchers(after first), lookaheadMatchers, records]
        if ($format === 3) {
            if ($chain) {
                $p = $o + 2;
                $btCount = $r->u16($p);
                $bt = $r->u16Array($p + 2, $btCount);
                $p += 2 + $btCount * 2;
                $inCount = $r->u16($p);
                $in = $r->u16Array($p + 2, $inCount);
                $p += 2 + $inCount * 2;
                $laCount = $r->u16($p);
                $la = $r->u16Array($p + 2, $laCount);
                $p += 2 + $laCount * 2;
                $recCount = $r->u16($p);
                $recs = $this->records($p + 2, $recCount);
            } else {
                $inCount = $r->u16($o + 2);
                $recCount = $r->u16($o + 4);
                $in = $r->u16Array($o + 6, $inCount);
                $bt = $la = [];
                $recs = $this->records($o + 6 + $inCount * 2, $recCount);
            }
            if (!$in) {
                return null;
            }
            $first = Layout::coverage($r, $o + $in[0]);
            if (!isset($first[$g])) {
                return null;
            }
            $mk = fn (array $offs) => array_map(fn ($off) => ['cov', Layout::coverage($r, $o + $off)], $offs);
            $rules[] = [$mk($bt), $mk(array_slice($in, 1)), $mk($la), $recs];
        } elseif ($format === 1) {
            $cov = Layout::coverage($r, $o + $r->u16($o + 2));
            if (!isset($cov[$g])) {
                return null;
            }
            $setCount = $r->u16($o + 4);
            if ($cov[$g] >= $setCount) {
                return null;
            }
            $set = $o + $r->u16($o + 6 + $cov[$g] * 2);
            $ruleCount = $r->u16($set);
            for ($k = 0; $k < $ruleCount; $k++) {
                $rule = $set + $r->u16($set + 2 + $k * 2);
                $rules[] = $this->parseRule($rule, $chain, fn ($v) => ['glyph', $v]);
            }
        } elseif ($format === 2) {
            $cov = Layout::coverage($r, $o + $r->u16($o + 2));
            if (!isset($cov[$g])) {
                return null;
            }
            if ($chain) {
                $btCd = Layout::classDef($r, $o + $r->u16($o + 4));
                $inCd = Layout::classDef($r, $o + $r->u16($o + 6));
                $laCd = Layout::classDef($r, $o + $r->u16($o + 8));
                $setCount = $r->u16($o + 10);
                $setsBase = $o + 12;
            } else {
                $inCd = Layout::classDef($r, $o + $r->u16($o + 4));
                $btCd = $laCd = $inCd;
                $setCount = $r->u16($o + 6);
                $setsBase = $o + 8;
            }
            $cls = $inCd[$g] ?? 0;
            if ($cls >= $setCount) {
                return null;
            }
            $setOff = $r->u16($setsBase + $cls * 2);
            if (!$setOff) {
                return null;
            }
            $set = $o + $setOff;
            $ruleCount = $r->u16($set);
            for ($k = 0; $k < $ruleCount; $k++) {
                $rule = $set + $r->u16($set + 2 + $k * 2);
                $parsed = $this->parseRule($rule, $chain, fn ($v) => ['class', $v]);
                // attach class defs
                $parsed[0] = array_map(fn ($m) => ['class', $m[1], $btCd], $parsed[0]);
                $parsed[1] = array_map(fn ($m) => ['class', $m[1], $inCd], $parsed[1]);
                $parsed[2] = array_map(fn ($m) => ['class', $m[1], $laCd], $parsed[2]);
                $rules[] = $parsed;
            }
        } else {
            return null;
        }

        foreach ($rules as [$bt, $in, $la, $recs]) {
            $positions = [$i];
            $j = $i;
            $ok = true;
            foreach ($in as $m) {
                $j = $this->nextIdx($b, $j, $flag, $ms);
                if ($j < 0 || !$this->matches($m, $b->items[$j]['g'])) {
                    $ok = false;
                    break;
                }
                $positions[] = $j;
            }
            if (!$ok) {
                continue;
            }
            $k = $j;
            foreach ($la as $m) {
                $k = $this->nextIdx($b, $k, $flag, $ms);
                if ($k < 0 || !$this->matches($m, $b->items[$k]['g'])) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                continue;
            }
            $k = $i;
            foreach ($bt as $m) {
                $k = $this->prevIdx($b, $k, $flag, $ms);
                if ($k < 0 || !$this->matches($m, $b->items[$k]['g'])) {
                    $ok = false;
                    break;
                }
            }
            if (!$ok) {
                continue;
            }
            $lastPos = end($positions);
            foreach ($recs as [$seqIndex, $lookupIndex]) {
                if (!isset($positions[$seqIndex])) {
                    continue;
                }
                $before = $b->count();
                $this->applyNested($b, $positions[$seqIndex], $lookupIndex);
                $delta = $b->count() - $before;
                if ($delta !== 0) {
                    foreach ($positions as $pi => $pv) {
                        if ($pi > $seqIndex) {
                            $positions[$pi] = $pv + $delta;
                        }
                    }
                    $lastPos += $delta;
                }
            }
            return max($i + 1, $lastPos + 1);
        }
        return null;
    }

    /** @return array{0:list<array>,1:list<array>,2:list<array>,3:list<array{0:int,1:int}>} */
    private function parseRule(int $rule, bool $chain, callable $wrap): array
    {
        $r = $this->r;
        if ($chain) {
            $p = $rule;
            $btCount = $r->u16($p);
            $bt = $r->u16Array($p + 2, $btCount);
            $p += 2 + $btCount * 2;
            $inCount = $r->u16($p);
            $in = $r->u16Array($p + 2, max(0, $inCount - 1));
            $p += 2 + max(0, $inCount - 1) * 2;
            $laCount = $r->u16($p);
            $la = $r->u16Array($p + 2, $laCount);
            $p += 2 + $laCount * 2;
            $recCount = $r->u16($p);
            $recs = $this->records($p + 2, $recCount);
        } else {
            $inCount = $r->u16($rule);
            $recCount = $r->u16($rule + 2);
            $in = $r->u16Array($rule + 4, max(0, $inCount - 1));
            $bt = $la = [];
            $recs = $this->records($rule + 4 + max(0, $inCount - 1) * 2, $recCount);
        }
        return [array_map($wrap, $bt), array_map($wrap, $in), array_map($wrap, $la), $recs];
    }

    /** @return list<array{0:int,1:int}> */
    private function records(int $p, int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = [$this->r->u16($p + $i * 4), $this->r->u16($p + $i * 4 + 2)];
        }
        return $out;
    }

    /** @param array<int,mixed> $m */
    private function matches(array $m, int $gid): bool
    {
        return match ($m[0]) {
            'cov' => isset($m[1][$gid]),
            'glyph' => $m[1] === $gid,
            'class' => (($m[2][$gid] ?? 0) === $m[1]),
            default => false,
        };
    }

    // ------------------------------------------------------------------ GPOS

    /** @return array{0:int,1:int,2:int,3:int,4:int} [xPla,yPla,xAdv,yAdv,size] */
    private function valueRecord(int $p, int $format): array
    {
        $vals = [0, 0, 0, 0];
        $o = $p;
        for ($bit = 0; $bit < 8; $bit++) {
            if ($format & (1 << $bit)) {
                if ($bit < 4) {
                    $vals[$bit] = $this->r->s16($o);
                }
                $o += 2;
            }
        }
        return [$vals[0], $vals[1], $vals[2], $vals[3], $o - $p];
    }

    private static function valueSize(int $format): int
    {
        $n = 0;
        for ($bit = 0; $bit < 8; $bit++) {
            if ($format & (1 << $bit)) {
                $n += 2;
            }
        }
        return $n;
    }

    /** @param array{0:int,1:int,2:int,3:int,4:int} $v */
    private function addValue(GlyphBuffer $b, int $i, array $v): void
    {
        $b->items[$i]['xo'] += $v[0];
        $b->items[$i]['yo'] += $v[1];
        $b->items[$i]['xa'] += $v[2];
    }

    /** @return array{0:int,1:int} */
    private function anchor(int $o): array
    {
        return [$this->r->s16($o + 2), $this->r->s16($o + 4)];
    }

    private function gposSubtable(GlyphBuffer $b, int $i, int $type, int $o, array $lk): ?int
    {
        $r = $this->r;
        $g = $b->items[$i]['g'];
        switch ($type) {
            case 1:
                $cov = Layout::coverage($r, $o + $r->u16($o + 2));
                if (!isset($cov[$g])) {
                    return null;
                }
                $vf = $r->u16($o + 4);
                if ($r->u16($o) === 1) {
                    $this->addValue($b, $i, $this->valueRecord($o + 6, $vf));
                } else {
                    $this->addValue($b, $i, $this->valueRecord($o + 8 + $cov[$g] * self::valueSize($vf), $vf));
                }
                return $i + 1;
            case 2:
                $cov = Layout::coverage($r, $o + $r->u16($o + 2));
                if (!isset($cov[$g])) {
                    return null;
                }
                $j = $this->nextIdx($b, $i, $lk['flag'], $lk['markSet']);
                if ($j < 0) {
                    return null;
                }
                $g2 = $b->items[$j]['g'];
                $vf1 = $r->u16($o + 4);
                $vf2 = $r->u16($o + 6);
                $s1 = self::valueSize($vf1);
                $s2 = self::valueSize($vf2);
                if ($r->u16($o) === 1) {
                    $setCount = $r->u16($o + 8);
                    if ($cov[$g] >= $setCount) {
                        return null;
                    }
                    $set = $o + $r->u16($o + 10 + $cov[$g] * 2);
                    $count = $r->u16($set);
                    $recSize = 2 + $s1 + $s2;
                    $lo = 0;
                    $hi = $count - 1;
                    while ($lo <= $hi) {
                        $mid = ($lo + $hi) >> 1;
                        $rec = $set + 2 + $mid * $recSize;
                        $sg = $r->u16($rec);
                        if ($sg === $g2) {
                            $this->addValue($b, $i, $this->valueRecord($rec + 2, $vf1));
                            if ($vf2) {
                                $this->addValue($b, $j, $this->valueRecord($rec + 2 + $s1, $vf2));
                                return $j + 1;
                            }
                            return $j;
                        }
                        if ($sg < $g2) {
                            $lo = $mid + 1;
                        } else {
                            $hi = $mid - 1;
                        }
                    }
                    return null;
                }
                $cd1 = Layout::classDef($r, $o + $r->u16($o + 8));
                $cd2 = Layout::classDef($r, $o + $r->u16($o + 10));
                $c1Count = $r->u16($o + 12);
                $c2Count = $r->u16($o + 14);
                $c1 = $cd1[$g] ?? 0;
                $c2 = $cd2[$g2] ?? 0;
                if ($c1 >= $c1Count || $c2 >= $c2Count) {
                    return null;
                }
                $rec = $o + 16 + ($c1 * $c2Count + $c2) * ($s1 + $s2);
                $this->addValue($b, $i, $this->valueRecord($rec, $vf1));
                if ($vf2) {
                    $this->addValue($b, $j, $this->valueRecord($rec + $s1, $vf2));
                    return $j + 1;
                }
                return $j;
            case 4:
            case 5:
            case 6:
                return $this->markAttach($b, $i, $type, $o, $lk);
            case 7:
            case 8:
                return $this->contextual($b, $i, $type, $o, $lk);
        }
        return null;
    }

    private function markAttach(GlyphBuffer $b, int $i, int $type, int $o, array $lk): ?int
    {
        $r = $this->r;
        $g = $b->items[$i]['g'];
        $markCov = Layout::coverage($r, $o + $r->u16($o + 2));
        if (!isset($markCov[$g])) {
            return null;
        }
        $baseCov = Layout::coverage($r, $o + $r->u16($o + 4));
        $classCount = $r->u16($o + 6);
        $markArray = $o + $r->u16($o + 8);
        $baseArray = $o + $r->u16($o + 10);

        // locate target glyph
        $j = -1;
        for ($k = $i - 1; $k >= 0; $k--) {
            $kg = $b->items[$k]['g'];
            $isMark = $this->gdef?->isMark($kg) ?? false;
            if ($type === 6) {
                if ($isMark) {
                    $j = $k;
                }
                break;
            }
            if (!$isMark) {
                $j = $k;
                break;
            }
        }
        if ($j < 0) {
            return null;
        }
        $bg = $b->items[$j]['g'];
        if (!isset($baseCov[$bg])) {
            return null;
        }
        $markIdx = $markCov[$g];
        $markRec = $markArray + 2 + $markIdx * 4;
        $markClass = $r->u16($markRec);
        $markAnchor = $this->anchor($markArray + $r->u16($markRec + 2));
        if ($markClass >= $classCount) {
            return null;
        }
        $baseIdx = $baseCov[$bg];
        if ($type === 5) {
            $lig = $baseArray + $r->u16($baseArray + 2 + $baseIdx * 2);
            $compCount = $r->u16($lig);
            if ($compCount === 0) {
                return null;
            }
            $comp = $compCount - 1;
            $anchorOff = $r->u16($lig + 2 + ($comp * $classCount + $markClass) * 2);
            $anchorBase = $lig;
        } else {
            $anchorOff = $r->u16($baseArray + 2 + ($baseIdx * $classCount + $markClass) * 2);
            $anchorBase = $baseArray;
        }
        if (!$anchorOff) {
            return null;
        }
        $baseAnchor = $this->anchor($anchorBase + $anchorOff);
        $b->items[$i]['att'] = $j;
        $b->items[$i]['xo'] = $baseAnchor[0] - $markAnchor[0];
        $b->items[$i]['yo'] = $baseAnchor[1] - $markAnchor[1];
        $b->items[$i]['xa'] = 0;
        return $i + 1;
    }
}

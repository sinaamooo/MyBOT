<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Vector\LottiePlayer;

/**
 * Finds the channel logo / brand text that a pack repeats in its emoji and
 * proposes a template spec for every animation of the pack.
 *
 * 1. Every path ("sh") gets a shape fingerprint: its vertices normalised to
 *    the path's own bounding box (translation / scale invariant).
 * 2. Fingerprints that occur in many animations of the pack (document
 *    frequency) and co-occur with each other form the logo cluster; simple
 *    shapes (rectangles, circles) only count when they sit inside the logo
 *    area of an animation.
 * 3. Matched paths are lifted to whole groups / layers when everything in
 *    them belongs to the logo (so the logo's own fills and strokes go too).
 * 4. Slots: the user's content is inserted into the lowest container that
 *    held the removed artwork, keeping its transform; the slot box is the
 *    removed artwork's bounds in that container at the frame where it is
 *    largest. Artwork split into an upper block and a wide lower block (logo
 *    + brand name) becomes a "main" and a "caption" slot.
 * 5. Mattes: logo artwork inside an alpha/luma matte source keeps the matte
 *    working by also receiving a copy of the user's content; inverted mattes
 *    are never emptied.
 */
final class LogoDetector
{
    /** document being analysed (for path type lookups) */
    private ?array $currentDoc = null;

    public function __construct(
        private float $dfRatio = 0.25,
        private int $minDf = 3,
        private float $jaccard = 0.5,
        private int $complexVertices = 6,
    ) {
    }

    /**
     * @param array<string,array<string,mixed>> $docs slug => decoded Lottie doc
     * @return array{fingerprints:array<string,int>,cluster:list<string>,members:list<string>,specs:array<string,array<string,mixed>>}
     */
    public function analyze(array $docs, string $pack): array
    {
        $n = count($docs);
        $docPaths = [];   // slug => list<[fp, path, vertices]>
        $df = [];
        $docsOf = [];
        foreach ($docs as $slug => $doc) {
            $list = [];
            LottieDoc::walkShapes($doc, function (array $item, string $path) use (&$list): void {
                if (($item['ty'] ?? '') !== 'sh' || !empty($item['hd'])) {
                    return;
                }
                $fp = self::fingerprint($item);
                if ($fp !== null) {
                    $list[] = $fp + ['path' => $path];
                }
            });
            $docPaths[$slug] = $list;
            foreach (array_unique(array_column($list, 'fp')) as $fp) {
                $df[$fp] = ($df[$fp] ?? 0) + 1;
                $docsOf[$fp][$slug] = true;
            }
        }
        $threshold = max($this->minDf, (int) ceil($n * $this->dfRatio));
        $vertices = [];
        foreach ($docPaths as $list) {
            foreach ($list as $e) {
                $vertices[$e['fp']] = max($vertices[$e['fp']] ?? 0, $e['vertices']);
            }
        }
        // seeds: frequent, distinctive shapes
        $seeds = [];
        foreach ($df as $fp => $c) {
            if ($c >= $threshold && ($vertices[$fp] ?? 0) >= $this->complexVertices) {
                $seeds[] = (string) $fp;
            }
        }
        $cluster = $this->cluster($seeds, $docsOf, $df, $n);
        $clusterDocs = [];
        foreach ($cluster as $fp) {
            $clusterDocs += $docsOf[$fp];
        }
        // members: other frequent shapes that live (mostly) in the cluster's documents
        $members = $cluster;
        foreach ($df as $fp => $c) {
            $fp = (string) $fp;
            if (in_array($fp, $cluster, true) || $c < $this->minDf) {
                continue;
            }
            $inside = count(array_intersect_key($docsOf[$fp], $clusterDocs));
            if ($inside >= 0.8 * $c) {
                $members[] = $fp;
            }
        }
        $specs = [];
        foreach ($docs as $slug => $doc) {
            $specs[$slug] = $this->proposeFor($doc, (string) $slug, $pack, $docPaths[$slug], $cluster, $members);
        }
        return ['fingerprints' => $df, 'cluster' => $cluster, 'members' => $members, 'specs' => $specs];
    }

    /**
     * Seed shapes grouped by co-occurrence (Jaccard of their document sets);
     * the logo cluster is the group with the largest total frequency.
     * @param list<string> $seeds
     * @param array<string,array<string,bool>> $docsOf
     * @param array<string,int> $df
     * @return list<string>
     */
    private function cluster(array $seeds, array $docsOf, array $df, int $n): array
    {
        $parent = [];
        foreach ($seeds as $s) {
            $parent[$s] = $s;
        }
        $find = function (string $x) use (&$parent, &$find): string {
            while ($parent[$x] !== $x) {
                $parent[$x] = $parent[$parent[$x]];
                $x = $parent[$x];
            }
            return $x;
        };
        $count = count($seeds);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $docsOf[$seeds[$i]];
                $b = $docsOf[$seeds[$j]];
                $inter = count(array_intersect_key($a, $b));
                $union = count($a + $b);
                if ($union > 0 && $inter / $union >= $this->jaccard) {
                    $parent[$find($seeds[$i])] = $find($seeds[$j]);
                }
            }
        }
        $groups = [];
        foreach ($seeds as $s) {
            $groups[$find($s)][] = $s;
        }
        $best = [];
        $bestScore = 0.0;
        foreach ($groups as $g) {
            $docs = [];
            foreach ($g as $fp) {
                $docs += $docsOf[$fp];
            }
            // a lone shape only counts when it is in at least half of the pack
            if (count($g) < 2 && count($docs) < 0.5 * $n) {
                continue;
            }
            $score = count($docs) * sqrt(count($g));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $g;
            }
        }
        // other clusters that are just as common are separate brand marks (e.g. a text logo)
        foreach ($groups as $g) {
            if ($g === $best || count($g) < 3) {
                continue;
            }
            $docs = [];
            foreach ($g as $fp) {
                $docs += $docsOf[$fp];
            }
            if (count($docs) >= max($this->minDf, 0.2 * $n) && array_sum(array_map(static fn ($fp) => $df[$fp], $g)) / count($g) >= 0.5 * max(1, array_sum(array_map(static fn ($fp) => $df[$fp], $best)) / max(1, count($best)))) {
                $best = array_merge($best, $g);
            }
        }
        sort($best);
        return $best;
    }

    /**
     * Normalised vertex fingerprint of a path item (first keyframe for animated paths).
     * @return array{fp:string,vertices:int}|null
     */
    public static function fingerprint(array $item): ?array
    {
        $k = $item['ks']['k'] ?? null;
        if (($item['ks']['a'] ?? 0) && is_array($k) && isset($k[0]) && is_array($k[0])) {
            $s = $k[0]['s'] ?? null;
            $k = is_array($s) ? ($s[0] ?? null) : null;
        }
        if (!is_array($k) || !isset($k['v']) || !is_array($k['v']) || count($k['v']) < 2) {
            return null;
        }
        $v = $k['v'];
        $xs = array_map(static fn ($p) => (float) ($p[0] ?? 0), $v);
        $ys = array_map(static fn ($p) => (float) ($p[1] ?? 0), $v);
        $minX = min($xs);
        $minY = min($ys);
        $w = max($xs) - $minX;
        $h = max($ys) - $minY;
        $s = max($w, $h);
        if ($s <= 1e-6) {
            return null;
        }
        $q = [];
        foreach ($v as $i => $p) {
            $q[] = (int) round(($xs[$i] - $minX) / $s * 20) . ',' . (int) round(($ys[$i] - $minY) / $s * 20);
        }
        $sig = count($v) . '|' . round($w / $s, 1) . '|' . round($h / $s, 1) . '|' . (!empty($k['c']) ? 'c' : 'o') . '|' . implode(';', $q);
        return ['fp' => substr(md5($sig), 0, 12), 'vertices' => count($v)];
    }

    /**
     * @param list<array{fp:string,vertices:int,path:string}> $paths
     * @param list<string> $cluster
     * @param list<string> $members
     * @return array<string,mixed> spec array + "diagnostics"
     */
    public function proposeFor(array $doc, string $slug, string $pack, array $paths, array $cluster, array $members): array
    {
        $this->currentDoc = $doc;
        $player = new LottiePlayer($doc);
        $frames = self::sampleFrames($doc);
        $seedHits = [];
        $memberHits = [];
        foreach ($paths as $p) {
            if (in_array($p['fp'], $cluster, true)) {
                $seedHits[] = $p['path'];
            } elseif (in_array($p['fp'], $members, true)) {
                $memberHits[] = $p['path'];
            }
        }
        $diag = ['seed_paths' => count($seedHits), 'member_paths' => count($memberHits), 'notes' => []];
        $spec = [
            'slug' => $slug, 'pack' => $pack, 'name' => $slug, 'source' => $slug . '.tgs',
            'status' => 'auto', 'remove' => [], 'slots' => [],
            'meta' => ['fr' => $doc['fr'] ?? 60, 'ip' => $doc['ip'] ?? 0, 'op' => $doc['op'] ?? 0, 'w' => $doc['w'] ?? 512, 'h' => $doc['h'] ?? 512],
        ];
        if (!$seedHits) {
            $spec['status'] = 'disabled';
            $diag['notes'][] = 'no logo artwork found';
            $spec['diagnostics'] = $diag;
            return $spec;
        }
        // logo area in canvas space (union over frames of the seed paths)
        $region = $this->canvasBounds($doc, $seedHits, $frames);
        $matched = $seedHits;
        if ($region !== null) {
            $grow = 0.15 * max($region[2] - $region[0], $region[3] - $region[1]);
            $zone = [$region[0] - $grow, $region[1] - $grow, $region[2] + $grow, $region[3] + $grow];
            foreach ($memberHits as $p) {
                $b = $this->canvasBounds($doc, [$p], $frames);
                if ($b !== null && $b[0] >= $zone[0] && $b[1] >= $zone[1] && $b[2] <= $zone[2] && $b[3] <= $zone[3]) {
                    $matched[] = $p;
                }
            }
        }
        $remove = $this->lift($doc, $matched);
        [$remove, $mattes] = $this->matteSafety($doc, $remove, $diag);
        $spec['remove'] = $remove;
        $still = null;
        $spec['slots'] = $this->slots($doc, $player, $remove, $mattes, $frames, $diag, $still);
        if ($still !== null) {
            $spec['meta']['still'] = $still;
        }
        if (!$spec['slots']) {
            $spec['status'] = 'disabled';
            $diag['notes'][] = 'no slot could be derived';
        }
        $spec['diagnostics'] = $diag;
        return $spec;
    }

    /**
     * Slot box (host content coordinates) covering the elements at $paths at
     * the frame where they are largest; for manual slot definitions.
     * @param list<string> $paths
     * @return array{box:array{0:float,1:float,2:float,3:float},frame:float,index:int}|null
     */
    public function slotBox(array $doc, string $host, array $paths): ?array
    {
        $this->currentDoc = $doc;
        $player = new LottiePlayer($doc);
        $parts = $this->hostBoxes($doc, $player, $host, $paths, self::sampleFrames($doc, 36));
        if (!$parts) {
            return null;
        }
        $box = null;
        foreach ($parts as $p) {
            $box = $box === null ? $p['box'] : self::union($box, $p['box']);
        }
        return ['box' => self::roundBox($box), 'frame' => $parts[0]['frame'], 'index' => $this->insertIndex($doc, $host, $paths)];
    }

    /**
     * Frame where removing $paths changes the picture most (the original
     * artwork is fully visible there): rendered comparison at low resolution.
     * @param list<string> $paths
     * @param list<float> $frames
     */
    public function visibleFrame(array $doc, array $paths, array $frames): float
    {
        $orig = new LottiePlayer($doc);
        $without = new LottiePlayer((new TemplateComposer())->apply($doc, $paths, []));
        $best = $frames[intdiv(count($frames), 2)] ?? 0.0;
        $bestScore = -1.0;
        $mid = ($frames[0] + end($frames)) / 2;
        foreach ($frames as $f) {
            $a = $orig->render($f, 64, 64, [128, 128, 128]);
            $b = $without->render($f, 64, 64, [128, 128, 128]);
            $score = 0.0;
            for ($y = 0; $y < 64; $y += 1) {
                for ($x = 0; $x < 64; $x += 1) {
                    $ca = imagecolorat($a, $x, $y);
                    $cb = imagecolorat($b, $x, $y);
                    $score += abs((($ca >> 16) & 255) - (($cb >> 16) & 255)) + abs((($ca >> 8) & 255) - (($cb >> 8) & 255)) + abs(($ca & 255) - ($cb & 255));
                }
            }
            // prefer the middle of the animation on (near) ties
            $score -= abs($f - $mid) * 0.01;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $f;
            }
        }
        return (float) $best;
    }

    /** @return list<float> frames sampled over the animation */
    public static function sampleFrames(array $doc, int $count = 24): array
    {
        $ip = (float) ($doc['ip'] ?? 0);
        $op = (float) ($doc['op'] ?? 60);
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = floor($ip + ($op - $ip - 1) * $i / max(1, $count - 1));
        }
        return array_values(array_unique($out));
    }

    /**
     * Canvas-space bounds of items (union over frames).
     * @param list<string> $itemPaths
     * @param list<float> $frames
     */
    public function canvasBounds(array $doc, array $itemPaths, array $frames): ?array
    {
        $tagged = $doc;
        foreach ($itemPaths as $p) {
            LottieDoc::set($tagged, $p . '/_emx', 'probe');
        }
        $player = new LottiePlayer($tagged);
        $box = null;
        foreach ($frames as $f) {
            $b = $player->probeBounds($f, 'probe');
            if ($b !== null) {
                $box = $box === null ? $b : [min($box[0], $b[0]), min($box[1], $b[1]), max($box[2], $b[2]), max($box[3], $b[3])];
            }
        }
        return $box;
    }

    /**
     * Replaces sets of matched paths by their whole group / layer when the
     * group or layer contains nothing else.
     * @param list<string> $matched
     * @return list<string>
     */
    public function lift(array $doc, array $matched): array
    {
        $isMatched = array_fill_keys($matched, true);
        $result = [];
        LottieDoc::walkLayers($doc, function (array $layer, string $lp) use (&$result, $isMatched): void {
            if ((int) ($layer['ty'] ?? -1) !== 4) {
                return;
            }
            $items = (array) ($layer['shapes'] ?? []);
            [$full, $any, $paths] = $this->liftItems($items, $lp . '/shapes', $isMatched);
            if (!$any) {
                return;
            }
            if ($full) {
                $result[] = $lp; // whole layer artwork
            } else {
                array_push($result, ...$paths);
            }
        });
        return array_values(array_unique($result));
    }

    /**
     * @param array<string,bool> $isMatched
     * @return array{0:bool,1:bool,2:list<string>} [all geometry matched, any matched, removal paths]
     */
    private function liftItems(array $items, string $base, array $isMatched): array
    {
        $hasGeometry = false;
        $all = true;
        $any = false;
        $paths = [];
        foreach ($items as $i => $it) {
            if (!is_array($it) || !empty($it['hd'])) {
                continue;
            }
            $p = $base . '/' . $i;
            $ty = (string) ($it['ty'] ?? '');
            if ($ty === 'gr') {
                [$full, $anyIn, $sub] = $this->liftItems((array) ($it['it'] ?? []), $p . '/it', $isMatched);
                $subHasGeo = $this->hasGeometry((array) ($it['it'] ?? []));
                if (!$subHasGeo) {
                    continue; // style-only / empty groups do not decide anything
                }
                $hasGeometry = true;
                if ($full) {
                    $any = true;
                    $paths[] = $p;
                } else {
                    $all = false;
                    if ($anyIn) {
                        $any = true;
                        array_push($paths, ...$sub);
                    }
                }
            } elseif (in_array($ty, LottieDoc::GEOMETRY, true)) {
                $hasGeometry = true;
                if (isset($isMatched[$p])) {
                    $any = true;
                    $paths[] = $p;
                } else {
                    $all = false;
                }
            }
        }
        return [$hasGeometry && $all && $any, $any, $paths];
    }

    private function hasGeometry(array $items): bool
    {
        foreach ($items as $it) {
            if (!is_array($it) || !empty($it['hd'])) {
                continue;
            }
            $ty = (string) ($it['ty'] ?? '');
            if (in_array($ty, LottieDoc::GEOMETRY, true)) {
                return true;
            }
            if ($ty === 'gr' && $this->hasGeometry((array) ($it['it'] ?? []))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Never empties inverted matte sources; logo artwork inside alpha / luma
     * matte sources is removed and the matte receives a copy of the content.
     * @param list<string> $remove
     * @return array{0:list<string>,1:array<string,string>} [remove, matte layer path => target layer path]
     */
    private function matteSafety(array $doc, array $remove, array &$diag): array
    {
        $keep = [];
        $mattes = [];
        foreach ($remove as $p) {
            $lp = LottieDoc::layerPath($p);
            if ($lp === null) {
                continue;
            }
            $list = (array) LottieDoc::get($doc, LottieDoc::layerListPath($lp));
            $role = LottieDoc::matteRole($list, LottieDoc::layerIndex($lp));
            if ($role['source']) {
                if (in_array($role['matteType'], [2, 4], true)) {
                    $diag['notes'][] = "kept $p: inverted matte source";
                    continue;
                }
                $mattes[$lp] = LottieDoc::layerListPath($lp) . '/' . $role['matteFor'];
            }
            $keep[] = $p;
        }
        return [$keep, $mattes];
    }

    /**
     * @param list<string> $remove
     * @param array<string,string> $mattes
     * @param list<float> $frames
     * @return list<array<string,mixed>>
     */
    private function slots(array $doc, LottiePlayer $player, array $remove, array $mattes, array $frames, array &$diag, ?float &$still = null): array
    {
        // group removals by layer, then find the lowest common container
        $byLayer = [];
        foreach ($remove as $p) {
            $lp = LottieDoc::layerPath($p);
            if ($lp !== null) {
                $byLayer[$lp][] = $p;
            }
        }
        $candidates = [];
        // reference frame: where the removed artwork is most visible
        $still = $this->visibleFrame($doc, $remove, $frames);
        foreach ($byLayer as $lp => $paths) {
            $host = $this->commonContainer($paths, $lp);
            $index = $this->insertIndex($doc, $host, $paths);
            $parts = $this->hostBoxes($doc, $player, $host, $paths, $frames);
            if ($parts === null) {
                continue;
            }
            foreach ($parts as $part) {
                $canvas = $this->canvasBounds($doc, $part['paths'], [$still]) ?? $this->canvasBounds($doc, $part['paths'], $frames);
                $candidates[] = [
                    'layer' => $lp, 'host' => $host, 'index' => $index, 'box' => $part['box'],
                    'canvas' => $canvas, 'color' => $this->dominantColor($doc, $part['paths'], $host), 'paths' => $part['paths'],
                    'matte' => isset($mattes[$lp]), 'frame' => $part['frame'],
                ];
            }
        }
        if (!$candidates) {
            return [];
        }
        // visible (non-matte) artwork decides the roles; the biggest is the main slot
        $visible = array_values(array_filter($candidates, static fn ($c) => !$c['matte'] && $c['canvas'] !== null));
        if (!$visible) {
            $visible = array_values(array_filter($candidates, static fn ($c) => $c['canvas'] !== null));
        }
        if (!$visible) {
            return [];
        }
        usort($visible, static fn ($a, $b) => self::area($b['canvas']) <=> self::area($a['canvas']));
        $main = $visible[0];
        $slots = ['main' => ['role' => 'main', 'host' => $main['host'], 'index' => $main['index'], 'box' => self::roundBox($main['box']),
            'accepts' => ['logo', 'text'], 'color' => $main['color'], 'pad' => 0.92, 'also' => []]];
        foreach ($candidates as $c) {
            if ($c === $main || $c['canvas'] === null) {
                continue;
            }
            $overlap = self::overlap($c['canvas'], $main['canvas']);
            $isCaption = !$c['matte'] && $overlap < 0.3 && self::isCaptionShape($c['canvas'], $main['canvas']);
            if ($isCaption && !isset($slots['caption'])) {
                $slots['caption'] = ['role' => 'caption', 'host' => $c['host'], 'index' => $c['index'], 'box' => self::roundBox($c['box']),
                    'accepts' => ['text'], 'color' => $c['color'], 'pad' => 0.95, 'also' => []];
                continue;
            }
            if ($isCaption) {
                $slots['caption']['also'][] = ['host' => $c['host'], 'index' => $c['index'], 'box' => self::roundBox($c['box'])];
                continue;
            }
            // duplicates of the logo (shadows, reflections, matte sources) get the same content
            $slots['main']['also'][] = ['host' => $c['host'], 'index' => $c['index'], 'box' => self::roundBox($c['box'])];
            $diag['notes'][] = sprintf('copy of main slot in %s (overlap %.2f%s)', $c['host'], $overlap, $c['matte'] ? ', matte' : '');
        }
        return array_values($slots);
    }

    private static function isCaptionShape(array $c, array $main): bool
    {
        $w = $c[2] - $c[0];
        $h = $c[3] - $c[1];
        $below = $c[1] >= $main[1] + 0.5 * ($main[3] - $main[1]);
        return $h > 0 && $w / $h >= 2.2 && $below;
    }

    /**
     * Lowest container (group or layer) holding all removed elements of a layer.
     * A single removed group is its own host (cleared, its transform kept).
     * @param list<string> $paths
     */
    private function commonContainer(array $paths, string $layerPath): string
    {
        if (in_array($layerPath, $paths, true)) {
            return $layerPath;
        }
        if (count($paths) === 1) {
            return $this->isGroupPath($paths[0]) ? $paths[0] : LottieDoc::containerPath($paths[0]);
        }
        $split = array_map(static fn ($p) => explode('/', LottieDoc::containerPath($p)), $paths);
        $common = array_shift($split);
        foreach ($split as $segs) {
            $n = 0;
            while ($n < count($common) && $n < count($segs) && $common[$n] === $segs[$n]) {
                $n++;
            }
            $common = array_slice($common, 0, $n);
        }
        $path = implode('/', $common);
        while ($path !== $layerPath && !preg_match('~/(shapes|it)/\d+$~', $path)) {
            if (!LottieDoc::isWithin($path, $layerPath) || $path === '') {
                return $layerPath;
            }
            $path = (string) preg_replace('~/[^/]+$~', '', $path);
        }
        return LottieDoc::isWithin($path, $layerPath) ? $path : $layerPath;
    }

    private function isGroupPath(string $path): bool
    {
        if ($this->currentDoc === null || !LottieDoc::has($this->currentDoc, $path)) {
            return false;
        }
        $node = LottieDoc::get($this->currentDoc, $path);
        return is_array($node) && ($node['ty'] ?? '') === 'gr';
    }

    /** Stacking index for the new content: where the first removed item was. */
    private function insertIndex(array $doc, string $host, array $paths): int
    {
        $node = LottieDoc::get($doc, $host);
        $key = LottieDoc::itemsKey($node);
        $items = (array) ($node[$key] ?? []);
        if (in_array($host, $paths, true)) {
            return 0; // whole host cleared: content goes first (above remaining items)
        }
        $prefix = $host . '/' . $key . '/';
        $min = null;
        foreach ($paths as $p) {
            if (str_starts_with($p, $prefix)) {
                $idx = (int) explode('/', substr($p, strlen($prefix)))[0];
                $min = $min === null ? $idx : min($min, $idx);
            }
        }
        if ($min === null) {
            return 0;
        }
        // count surviving items before that position
        $kept = 0;
        for ($i = 0; $i < $min; $i++) {
            if (!in_array($prefix . $i, $paths, true)) {
                $kept++;
            }
        }
        return $kept;
    }

    /**
     * Removed artwork bounds in the host's content coordinates at the frame
     * where it is largest; split into an upper (logo) and a lower wide block
     * (brand name) when they are clearly separated.
     * @param list<string> $paths
     * @param list<float> $frames
     * @return list<array{box:array{0:float,1:float,2:float,3:float},paths:list<string>,frame:float}>|null
     */
    private function hostBoxes(array $doc, LottiePlayer $player, string $host, array $paths, array $frames): ?array
    {
        $boxes = [];
        $bestFrame = null;
        $bestArea = -1.0;
        // a cleared host is measured per child so logo and brand name can be told apart
        $measure = [];
        foreach ($paths as $p) {
            if ($p === $host) {
                $node = LottieDoc::get($doc, $host);
                $key = LottieDoc::itemsKey($node);
                foreach ((array) ($node[$key] ?? []) as $ci => $child) {
                    if (is_array($child) && !in_array($child['ty'] ?? '', ['tr', 'fl', 'st', 'gf', 'gs', 'tm', 'mm', 'rd'], true) && empty($child['hd'])) {
                        $measure[] = $host . '/' . $key . '/' . $ci;
                    }
                }
            } else {
                $measure[] = $p;
            }
        }
        foreach ($frames as $f) {
            $per = [];
            $union = null;
            foreach ($measure as $p) {
                $b = $this->hostLocalBounds($doc, $player, $host, $p, $f);
                if ($b === null) {
                    continue;
                }
                $per[$p] = $b;
                $union = $union === null ? $b : self::union($union, $b);
            }
            if ($union !== null && self::area($union) > $bestArea) {
                $bestArea = self::area($union);
                $bestFrame = $f;
                $boxes = $per;
            }
        }
        if ($bestFrame === null || !$boxes) {
            return null;
        }
        // vertical split: sort by top edge and look for a clear gap
        $entries = [];
        foreach ($boxes as $p => $b) {
            $entries[] = ['p' => $p, 'b' => $b];
        }
        usort($entries, static fn ($a, $b) => $a['b'][1] <=> $b['b'][1]);
        $union = array_reduce(array_column($entries, 'b'), static fn ($c, $b) => $c === null ? $b : self::union($c, $b));
        $height = $union[3] - $union[1];
        $best = null;
        $bottom = -INF;
        for ($i = 0; $i < count($entries) - 1; $i++) {
            $bottom = max($bottom, $entries[$i]['b'][3]);
            $gap = $entries[$i + 1]['b'][1] - $bottom;
            if ($gap > 0.04 * $height && ($best === null || $gap > $best[1])) {
                $best = [$i, $gap];
            }
        }
        if ($best !== null) {
            $top = array_slice($entries, 0, $best[0] + 1);
            $low = array_slice($entries, $best[0] + 1);
            $tb = array_reduce(array_column($top, 'b'), static fn ($c, $b) => $c === null ? $b : self::union($c, $b));
            $lb = array_reduce(array_column($low, 'b'), static fn ($c, $b) => $c === null ? $b : self::union($c, $b));
            $lw = $lb[2] - $lb[0];
            $lh = $lb[3] - $lb[1];
            if ($lh > 0 && $lw / $lh >= 2.2 && $lh < 0.45 * ($tb[3] - $tb[1])) {
                return [
                    ['box' => $tb, 'paths' => array_column($top, 'p'), 'frame' => $bestFrame],
                    ['box' => $lb, 'paths' => array_column($low, 'p'), 'frame' => $bestFrame],
                ];
            }
        }
        return [['box' => $union, 'paths' => array_column($entries, 'p'), 'frame' => $bestFrame]];
    }

    /** Bounds of the element at $path in the content space of $host at frame $f. */
    private function hostLocalBounds(array $doc, LottiePlayer $player, string $host, string $path, float $f): ?array
    {
        $node = LottieDoc::get($doc, $path);
        if (!is_array($node)) {
            return null;
        }
        if ($path === $host) {
            // the host itself was cleared: its own content coordinates
            $key = LottieDoc::itemsKey($node);
            return $player->itemsBounds((array) ($node[$key] ?? []), $f);
        }
        // groups between host and the element contribute their transforms
        $m = Matrix::identity();
        $rel = substr($path, strlen($host) + 1);
        $segs = explode('/', $rel);
        $cursor = $host;
        for ($i = 0; $i + 1 < count($segs); $i += 2) {
            $cursor .= '/' . $segs[$i] . '/' . $segs[$i + 1];
            if ($cursor === $path) {
                break;
            }
            $g = LottieDoc::get($doc, $cursor);
            if (is_array($g) && ($g['ty'] ?? '') === 'gr') {
                $m = $m->multiply($player->groupMatrix($g, $f));
            }
        }
        return $player->itemsBounds([$node], $f, $m);
    }

    /** Most used fill colour of the removed artwork (hex), null when unknown. */
    private function dominantColor(array $doc, array $paths, string $host): ?string
    {
        $votes = [];
        $collect = function (array $items) use (&$collect, &$votes): void {
            foreach ($items as $it) {
                if (!is_array($it) || !empty($it['hd'])) {
                    continue;
                }
                $ty = (string) ($it['ty'] ?? '');
                if ($ty === 'gr') {
                    $collect((array) ($it['it'] ?? []));
                } elseif ($ty === 'fl') {
                    $k = $it['c']['k'] ?? null;
                    if (is_array($k) && isset($k[0]) && is_array($k[0])) {
                        $k = $k[0]['s'] ?? null;
                    }
                    if (is_array($k) && count($k) >= 3 && is_numeric($k[0])) {
                        $hex = sprintf('#%02X%02X%02X', (int) round(min(1, max(0, $k[0])) * 255), (int) round(min(1, max(0, $k[1])) * 255), (int) round(min(1, max(0, $k[2])) * 255));
                        $votes[$hex] = ($votes[$hex] ?? 0) + 1;
                    }
                }
            }
        };
        foreach ($paths as $p) {
            $node = LottieDoc::get($doc, $p);
            if (!is_array($node)) {
                continue;
            }
            if (isset($node['shapes'])) {
                $collect((array) $node['shapes']);
            } elseif (($node['ty'] ?? '') === 'gr') {
                $collect((array) ($node['it'] ?? []));
            } else {
                // a bare path: the styles of its container paint it
                $container = LottieDoc::get($doc, LottieDoc::containerPath($p));
                if (is_array($container)) {
                    $key = LottieDoc::itemsKey($container);
                    $collect(array_filter((array) ($container[$key] ?? []), static fn ($x) => is_array($x) && in_array($x['ty'] ?? '', ['fl'], true)));
                }
            }
        }
        if (!$votes) {
            return null;
        }
        arsort($votes);
        return (string) array_key_first($votes);
    }

    private static function area(?array $b): float
    {
        return $b === null ? 0.0 : max(0.0, $b[2] - $b[0]) * max(0.0, $b[3] - $b[1]);
    }

    private static function union(array $a, array $b): array
    {
        return [min($a[0], $b[0]), min($a[1], $b[1]), max($a[2], $b[2]), max($a[3], $b[3])];
    }

    /** Intersection over the smaller box. */
    private static function overlap(array $a, array $b): float
    {
        $ix = max(0.0, min($a[2], $b[2]) - max($a[0], $b[0]));
        $iy = max(0.0, min($a[3], $b[3]) - max($a[1], $b[1]));
        $small = min(self::area($a), self::area($b));
        return $small > 0 ? $ix * $iy / $small : 0.0;
    }

    private static function roundBox(array $b): array
    {
        return array_map(static fn ($v) => round((float) $v, 2), $b);
    }
}

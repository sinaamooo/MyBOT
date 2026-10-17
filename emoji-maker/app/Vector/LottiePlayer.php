<?php

declare(strict_types=1);

namespace EmojiMaker\Vector;

use EmojiMaker\Geometry\Matrix;
use EmojiMaker\Geometry\Path;
use EmojiMaker\Geometry\Stroke;
use EmojiMaker\Rendering\ImageProcessor;
use EmojiMaker\Rendering\Mask;
use EmojiMaker\Rendering\Rasterizer;

/**
 * Pure PHP Lottie / TGS renderer (subset used by Telegram stickers & emoji).
 *
 * Supported: shape / precomp / null / solid layers, parenting, track mattes
 * (alpha, alpha inverted, luma), layer masks (add/subtract/intersect), groups,
 * paths (static + animated), rectangles, ellipses, polystars, fills, strokes,
 * linear & radial gradient fills and strokes, trim paths, keyframes with
 * bezier easing, hold keys and spatial (bezier) position interpolation.
 *
 * A custom item {"ty":"emx_img","ref":"name","rect":[x0,y0,x1,y1]} draws a
 * bitmap with the current transform; it is used to put a raster logo into
 * the slot of a template while following the template's animation.
 */
final class LottiePlayer
{
    /** @var array<string,mixed> */
    private array $doc;
    /** @var array<string,list<array<string,mixed>>> */
    private array $assets = [];
    private Rasterizer $ras;
    /** @var array<string,\GdImage> */
    private array $images = [];
    /** @var array<string,Path> */
    private array $staticPathCache = [];
    private float $scale = 1.0;
    private int $outW = 0;
    private int $outH = 0;
    /** @var callable|null filter(array $item, array $ctx): bool  (true = skip) */
    private $skipFilter = null;
    /** collected world bounds of tagged items (for slot detection) */
    private ?array $probeBounds = null;
    private ?string $probeTag = null;

    /** @param array<string,mixed> $doc decoded Lottie JSON */
    public function __construct(array $doc)
    {
        $this->doc = $doc;
        foreach ((array) ($doc['assets'] ?? []) as $a) {
            if (isset($a['id'], $a['layers'])) {
                $this->assets[(string) $a['id']] = $a['layers'];
            }
        }
        $this->ras = new Rasterizer(4);
    }

    public static function fromTgs(string $file): self
    {
        $raw = (string) file_get_contents($file);
        $json = str_starts_with($raw, "\x1f\x8b") ? gzdecode($raw, 8 * 1024 * 1024) : $raw;
        $doc = json_decode((string) $json, true);
        if (!is_array($doc) || !isset($doc['layers'])) {
            throw new \RuntimeException('Invalid Lottie/TGS file');
        }
        return new self($doc);
    }

    public function width(): float { return (float) ($this->doc['w'] ?? 512); }
    public function height(): float { return (float) ($this->doc['h'] ?? 512); }
    public function fps(): float { return (float) ($this->doc['fr'] ?? 60); }
    public function inPoint(): float { return (float) ($this->doc['ip'] ?? 0); }
    public function outPoint(): float { return (float) ($this->doc['op'] ?? 60); }
    public function duration(): float { return ($this->outPoint() - $this->inPoint()) / max(1.0, $this->fps()); }

    public function addImage(string $ref, \GdImage $img): void
    {
        $this->images[$ref] = $img;
    }

    /** Items for which $filter returns true are not drawn. */
    public function setSkipFilter(?callable $filter): void
    {
        $this->skipFilter = $filter;
    }

    /**
     * Renders the frame at time $frame (in Lottie frames).
     * @param array{0:int,1:int,2:int}|null $background
     */
    public function render(float $frame, int $outW, int $outH, ?array $background = null): \GdImage
    {
        $this->outW = $outW;
        $this->outH = $outH;
        $this->scale = min($outW / $this->width(), $outH / $this->height());
        $canvas = ImageProcessor::canvas($outW, $outH);
        if ($background !== null) {
            imagefilledrectangle($canvas, 0, 0, $outW - 1, $outH - 1, imagecolorallocate($canvas, $background[0], $background[1], $background[2]));
        }
        $root = Matrix::scaling($this->scale);
        $this->renderLayers($this->doc['layers'], $frame, $root, $canvas);
        return $canvas;
    }

    /**
     * Canvas-space bounding box of the items marked with "_emx" == $tag at $frame.
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    public function probeBounds(float $frame, string $tag): ?array
    {
        $this->probeTag = $tag;
        $this->probeBounds = null;
        $this->outW = (int) $this->width();
        $this->outH = (int) $this->height();
        $this->scale = 1.0;
        $canvas = ImageProcessor::canvas(8, 8); // drawing is skipped while probing
        $this->renderLayers($this->doc['layers'], $frame, Matrix::identity(), $canvas, true);
        $this->probeTag = null;
        return $this->probeBounds;
    }

    // ------------------------------------------------------------------
    // Layers
    // ------------------------------------------------------------------

    /** @param list<array<string,mixed>> $layers */
    private function renderLayers(array $layers, float $t, Matrix $base, \GdImage $target, bool $probe = false): void
    {
        $byInd = [];
        foreach ($layers as $l) {
            if (isset($l['ind'])) {
                $byInd[(int) $l['ind']] = $l;
            }
        }
        $n = count($layers);
        for ($i = $n - 1; $i >= 0; $i--) {
            $layer = $layers[$i];
            if (!empty($layer['td']) && (int) ($layers[$i + 1]['tt'] ?? 0) > 0) {
                continue; // matte source, consumed by the layer below it
            }
            if (!$this->layerVisible($layer, $t) || (int) ($layer['hd'] ?? 0) === 1 || !empty($layer['hd'])) {
                continue;
            }
            $ty = (int) ($layer['ty'] ?? -1);
            if ($ty === 3 || $ty === 2 || $ty === 5 || $ty === 6) {
                continue;
            }
            $matrix = $base->multiply($this->layerMatrix($layer, $t, $byInd));
            $opacity = $this->value($layer['ks']['o'] ?? null, $t, 100) / 100;
            if ($opacity <= 0.002 && !$probe) {
                continue;
            }
            $tt = (int) ($layer['tt'] ?? 0);
            $matte = ($tt > 0 && $i > 0 && !empty($layers[$i - 1]['td'])) ? $layers[$i - 1] : null;
            if ($tt > 0 && $matte === null) {
                continue; // like rlottie: a track matte without matte source hides the layer
            }
            $hasMasks = !empty($layer['masksProperties']) && !empty($layer['hasMask']);
            if ($probe) {
                $this->drawLayerContent($layer, $t, $matrix, $target, 1.0, true);
                continue;
            }
            if ($matte === null && !$hasMasks) {
                $this->drawLayerContent($layer, $t, $matrix, $target, $opacity);
                continue;
            }
            // isolated layer (matte / masks)
            $layerCanvas = ImageProcessor::canvas($this->outW, $this->outH);
            $this->drawLayerContent($layer, $t, $matrix, $layerCanvas, $opacity);
            if ($hasMasks) {
                $this->applyMasks($layerCanvas, $layer, $t, $matrix);
            }
            if ($matte !== null && $this->layerVisible($matte, $t)) {
                $matteCanvas = ImageProcessor::canvas($this->outW, $this->outH);
                $mMatrix = $base->multiply($this->layerMatrix($matte, $t, $byInd));
                $mOpacity = $this->value($matte['ks']['o'] ?? null, $t, 100) / 100;
                $this->drawLayerContent($matte, $t, $mMatrix, $matteCanvas, $mOpacity);
                self::applyMatte($layerCanvas, $matteCanvas, $tt);
            } elseif ($matte !== null) {
                if ($tt === 1 || $tt === 3) {
                    continue; // matte not visible -> nothing shows through
                }
            }
            imagealphablending($target, true);
            imagecopy($target, $layerCanvas, 0, 0, 0, 0, $this->outW, $this->outH);
        }
    }

    private function layerVisible(array $layer, float $t): bool
    {
        $ip = (float) ($layer['ip'] ?? -INF);
        $op = (float) ($layer['op'] ?? INF);
        return $t >= $ip && $t < $op;
    }

    /** @param array<int,array<string,mixed>> $byInd */
    private function layerMatrix(array $layer, float $t, array $byInd, int $depth = 0): Matrix
    {
        $m = $this->transformMatrix((array) ($layer['ks'] ?? []), $t);
        if (isset($layer['parent']) && $depth < 20 && isset($byInd[(int) $layer['parent']])) {
            $m = $this->layerMatrix($byInd[(int) $layer['parent']], $t, $byInd, $depth + 1)->multiply($m);
        }
        return $m;
    }

    private function drawLayerContent(array $layer, float $t, Matrix $matrix, \GdImage $target, float $opacity, bool $probe = false): void
    {
        $ty = (int) ($layer['ty'] ?? -1);
        if ($ty === 4) {
            $this->renderGroup((array) ($layer['shapes'] ?? []), $t, $matrix, $opacity, $target, $probe);
        } elseif ($ty === 0) {
            $refId = (string) ($layer['refId'] ?? '');
            if (!isset($this->assets[$refId])) {
                return;
            }
            $st = (float) ($layer['st'] ?? 0);
            $sr = (float) ($layer['sr'] ?? 1) ?: 1.0;
            $local = ($t - $st) / $sr;
            if (isset($layer['tm'])) {
                $local = $this->value($layer['tm'], $t, 0) * $this->fps();
            }
            if ($probe) {
                $this->renderLayers($this->assets[$refId], $local, $matrix, $target, true);
                return;
            }
            // precomp content is clipped to the precomp size (as rlottie does)
            $c = ImageProcessor::canvas($this->outW, $this->outH);
            $this->renderLayers($this->assets[$refId], $local, $matrix, $c);
            if (isset($layer['w'], $layer['h']) && (float) $layer['w'] > 0 && (float) $layer['h'] > 0) {
                $clip = $this->ras->fillPath(Path::rect(0, 0, (float) $layer['w'], (float) $layer['h']), $this->outW, $this->outH, $matrix);
                self::multiplyAlpha($c, $clip);
            }
            if ($opacity < 0.999) {
                self::scaleAlpha($c, $opacity);
            }
            imagealphablending($target, true);
            imagecopy($target, $c, 0, 0, 0, 0, $this->outW, $this->outH);
        } elseif ($ty === 1 && !$probe) {
            $w = (float) ($layer['sw'] ?? 0);
            $h = (float) ($layer['sh'] ?? 0);
            $color = self::hexColor((string) ($layer['sc'] ?? '#000000'));
            $this->fillPaths([Path::rect(0, 0, $w, $h)], $matrix, $color, $opacity, 1, $target);
        }
    }

    // ------------------------------------------------------------------
    // Shapes
    // ------------------------------------------------------------------

    /**
     * Renders a group's item list (Lottie stacking semantics: earlier items are on top;
     * a style draws all paths listed before it, modifiers in between apply).
     * @param list<array<string,mixed>> $items
     */
    private function renderGroup(array $items, float $t, Matrix $matrix, float $opacity, \GdImage $target, bool $probe = false, ?array $inheritedTag = null): void
    {
        $m = $matrix;
        $op = $opacity;
        foreach ($items as $it) {
            if (($it['ty'] ?? '') === 'tr') {
                $m = $matrix->multiply($this->transformMatrix($it, $t));
                $op = $opacity * $this->value($it['o'] ?? null, $t, 100) / 100;
                break;
            }
        }
        if ($op <= 0.002 && !$probe) {
            return;
        }
        $n = count($items);
        // draw bottom-up
        for ($s = $n - 1; $s >= 0; $s--) {
            $it = $items[$s];
            if (!empty($it['hd'])) {
                continue;
            }
            $ty = (string) ($it['ty'] ?? '');
            if ($this->skipFilter !== null && ($this->skipFilter)($it) && !$probe) {
                continue;
            }
            $itemTag = $it['_emx'] ?? $inheritedTag;
            if ($ty === 'gr') {
                $this->renderGroup((array) ($it['it'] ?? []), $t, $m, $op, $target, $probe, $it['_emx'] ?? $inheritedTag);
                continue;
            }
            if ($ty === 'emx_img') {
                if ($probe) {
                    continue;
                }
                $this->drawImage($it, $m, $op, $target);
                continue;
            }
            if (!in_array($ty, ['fl', 'st', 'gf', 'gs'], true)) {
                continue;
            }
            $paths = $this->collectPaths($items, $s, $t, Matrix::identity(), $probe);
            if (!$paths) {
                continue;
            }
            if ($probe) {
                if ($this->probeTag !== null && ($itemTag === $this->probeTag || $this->anyTagged($items, $s))) {
                    foreach ($paths as $p) {
                        $tagged = $p['tag'] ?? null;
                        if ($tagged === $this->probeTag || $itemTag === $this->probeTag) {
                            $b = $p['path']->transform($m)->bounds();
                            if ($b) {
                                $this->probeBounds = $this->probeBounds === null ? $b : [min($this->probeBounds[0], $b[0]), min($this->probeBounds[1], $b[1]), max($this->probeBounds[2], $b[2]), max($this->probeBounds[3], $b[3])];
                            }
                        }
                    }
                }
                continue;
            }
            $styleOpacity = $op * $this->value($it['o'] ?? null, $t, 100) / 100;
            if ($styleOpacity <= 0.002) {
                continue;
            }
            $plain = array_map(static fn ($p) => $p['path'], $paths);
            switch ($ty) {
                case 'fl':
                    $c = $this->colorValue($it['c'] ?? null, $t);
                    $this->fillPaths($plain, $m, $c, $styleOpacity, (int) ($it['r'] ?? 1), $target);
                    break;
                case 'st':
                    $c = $this->colorValue($it['c'] ?? null, $t);
                    $w = $this->value($it['w'] ?? null, $t, 1);
                    $this->strokePaths($plain, $m, $w, $target, fn () => $c, $styleOpacity);
                    break;
                case 'gf':
                    $this->gradientFill($plain, $it, $t, $m, $styleOpacity, $target);
                    break;
                case 'gs':
                    $w = $this->value($it['w'] ?? null, $t, 1);
                    $this->gradientStroke($plain, $it, $t, $m, $w, $styleOpacity, $target);
                    break;
            }
        }
    }

    private function anyTagged(array $items, int $before): bool
    {
        for ($i = 0; $i < $before; $i++) {
            if (isset($items[$i]['_emx'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Paths affecting the style at index $s: every geometry listed before it
     * (including nested groups with their transforms), modified by trims that
     * sit between the geometry and the style.
     * @return list<array{path:Path,tag:?string}>
     */
    private function collectPaths(array $items, int $s, float $t, Matrix $m, bool $probe, ?string $tag = null): array
    {
        $out = [];
        for ($i = 0; $i < $s; $i++) {
            $it = $items[$i];
            if (!empty($it['hd'])) {
                continue;
            }
            if ($this->skipFilter !== null && !$probe && ($this->skipFilter)($it)) {
                continue;
            }
            $ty = (string) ($it['ty'] ?? '');
            $itTag = $it['_emx'] ?? $tag;
            $geo = null;
            if ($ty === 'sh') {
                $geo = $this->shapePath($it, $t);
            } elseif ($ty === 'rc') {
                $p = $this->vec($it['p'] ?? null, $t, [0, 0]);
                $sz = $this->vec($it['s'] ?? null, $t, [0, 0]);
                $r = $this->value($it['r'] ?? null, $t, 0);
                $geo = Path::roundedRect($p[0] - $sz[0] / 2, $p[1] - $sz[1] / 2, $sz[0], $sz[1], $r);
            } elseif ($ty === 'el') {
                $p = $this->vec($it['p'] ?? null, $t, [0, 0]);
                $sz = $this->vec($it['s'] ?? null, $t, [0, 0]);
                $geo = Path::ellipse($p[0], $p[1], $sz[0] / 2, $sz[1] / 2);
            } elseif ($ty === 'sr') {
                $geo = $this->star($it, $t);
            } elseif ($ty === 'gr') {
                $sub = (array) ($it['it'] ?? []);
                $gm = Matrix::identity();
                foreach ($sub as $si) {
                    if (($si['ty'] ?? '') === 'tr') {
                        $gm = $this->transformMatrix($si, $t);
                        break;
                    }
                }
                foreach ($this->collectPaths($sub, count($sub), $t, $gm, $probe, $itTag) as $p) {
                    $out[] = ['path' => $p['path'], 'tag' => $p['tag'], 'index' => $i];
                }
                continue;
            }
            if ($geo !== null) {
                $out[] = ['path' => $m->isIdentity() ? $geo : $geo->transform($m), 'tag' => $itTag, 'index' => $i];
            }
        }
        // trims between geometry and the style
        for ($i = 0; $i < $s; $i++) {
            if (($items[$i]['ty'] ?? '') === 'tm' && empty($items[$i]['hd'])) {
                foreach ($out as $k => $p) {
                    if (($p['index'] ?? -1) < $i) {
                        $out[$k]['path'] = $this->trim($p['path'], $items[$i], $t);
                    }
                }
            }
        }
        // nested group paths must also receive this level's transform
        if (!$m->isIdentity()) {
            foreach ($out as $k => $p) {
                if (($items[$p['index']]['ty'] ?? '') === 'gr') {
                    $out[$k]['path'] = $p['path']->transform($m);
                }
            }
        }
        return $out;
    }

    private function shapePath(array $it, float $t): ?Path
    {
        $ks = $it['ks'] ?? null;
        if (!is_array($ks)) {
            return null;
        }
        if (($ks['a'] ?? 0) === 0 || !isset($ks['a'])) {
            $data = $ks['k'] ?? null;
            if (!is_array($data)) {
                return null;
            }
            $hash = md5((string) json_encode($data));
            return $this->staticPathCache[$hash] ??= self::bezierPath($data);
        }
        $shape = $this->interpolateShape($ks['k'], $t);
        return $shape ? self::bezierPath($shape) : null;
    }

    /** @param array<string,mixed> $d {v,i,o,c} */
    public static function bezierPath(array $d): Path
    {
        $p = new Path();
        $v = $d['v'] ?? [];
        $in = $d['i'] ?? [];
        $out = $d['o'] ?? [];
        $n = count($v);
        if ($n === 0) {
            return $p;
        }
        $p->moveTo((float) $v[0][0], (float) $v[0][1]);
        for ($k = 1; $k < $n; $k++) {
            $a = $v[$k - 1];
            $b = $v[$k];
            $o = $out[$k - 1] ?? [0, 0];
            $i = $in[$k] ?? [0, 0];
            $p->cubicTo($a[0] + $o[0], $a[1] + $o[1], $b[0] + $i[0], $b[1] + $i[1], (float) $b[0], (float) $b[1]);
        }
        if (!empty($d['c'])) {
            $a = $v[$n - 1];
            $b = $v[0];
            $o = $out[$n - 1] ?? [0, 0];
            $i = $in[0] ?? [0, 0];
            $p->cubicTo($a[0] + $o[0], $a[1] + $o[1], $b[0] + $i[0], $b[1] + $i[1], (float) $b[0], (float) $b[1]);
            $p->close();
        }
        return $p;
    }

    private function star(array $it, float $t): Path
    {
        $c = $this->vec($it['p'] ?? null, $t, [0, 0]);
        $points = max(3, (int) round($this->value($it['pt'] ?? null, $t, 5)));
        $or = $this->value($it['or'] ?? null, $t, 10);
        $ir = $this->value($it['ir'] ?? null, $t, $or / 2);
        $rot = deg2rad($this->value($it['r'] ?? null, $t, 0) - 90);
        $star = (int) ($it['sy'] ?? 1) === 1;
        $pts = [];
        $count = $star ? $points * 2 : $points;
        for ($k = 0; $k < $count; $k++) {
            $r = $star && $k % 2 === 1 ? $ir : $or;
            $a = $rot + 2 * M_PI * $k / $count;
            $pts[] = [$c[0] + $r * cos($a), $c[1] + $r * sin($a)];
        }
        return Path::polygon($pts);
    }

    private function trim(Path $path, array $tm, float $t): Path
    {
        $s = $this->value($tm['s'] ?? null, $t, 0) / 100;
        $e = $this->value($tm['e'] ?? null, $t, 100) / 100;
        $o = $this->value($tm['o'] ?? null, $t, 0) / 360;
        if ($s > $e) {
            [$s, $e] = [$e, $s];
        }
        if ($e - $s >= 0.9999) {
            return $path;
        }
        if ($e - $s <= 0.0001) {
            return new Path();
        }
        $s += $o;
        $e += $o;
        $polys = $path->flatten(0.3);
        $lens = [];
        $total = 0.0;
        foreach ($polys as $pi => $poly) {
            $n = intdiv(count($poly), 2);
            $l = 0.0;
            for ($k = 1; $k < $n; $k++) {
                $l += hypot($poly[$k * 2] - $poly[$k * 2 - 2], $poly[$k * 2 + 1] - $poly[$k * 2 - 1]);
            }
            $lens[$pi] = $l;
            $total += $l;
        }
        if ($total <= 0) {
            return $path;
        }
        $out = new Path();
        $ranges = [];
        $a = fmod($s, 1.0);
        if ($a < 0) {
            $a += 1;
        }
        $b = $a + ($e - $s);
        $ranges[] = [$a, min(1.0, $b)];
        if ($b > 1.0) {
            $ranges[] = [0.0, $b - 1.0];
        }
        foreach ($ranges as [$ra, $rb]) {
            $from = $ra * $total;
            $to = $rb * $total;
            $acc = 0.0;
            foreach ($polys as $pi => $poly) {
                $n = intdiv(count($poly), 2);
                $started = false;
                for ($k = 1; $k < $n; $k++) {
                    $x0 = $poly[$k * 2 - 2];
                    $y0 = $poly[$k * 2 - 1];
                    $x1 = $poly[$k * 2];
                    $y1 = $poly[$k * 2 + 1];
                    $l = hypot($x1 - $x0, $y1 - $y0);
                    $segA = $acc;
                    $segB = $acc + $l;
                    $acc = $segB;
                    if ($segB < $from || $segA > $to || $l <= 0) {
                        continue;
                    }
                    $ta = max(0.0, ($from - $segA) / $l);
                    $tb = min(1.0, ($to - $segA) / $l);
                    $ax = $x0 + ($x1 - $x0) * $ta;
                    $ay = $y0 + ($y1 - $y0) * $ta;
                    $bx = $x0 + ($x1 - $x0) * $tb;
                    $by = $y0 + ($y1 - $y0) * $tb;
                    if (!$started) {
                        $out->moveTo($ax, $ay);
                        $started = true;
                    }
                    $out->lineTo($bx, $by);
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Painting
    // ------------------------------------------------------------------

    /** @param list<Path> $paths */
    private function fillPaths(array $paths, Matrix $m, array $rgb, float $opacity, int $rule, \GdImage $target): void
    {
        $polys = [];
        foreach ($paths as $p) {
            foreach ($p->transform($m)->flatten(0.25) as $poly) {
                $polys[] = $poly;
            }
        }
        if (!$polys) {
            return;
        }
        $mask = $this->ras->fillPolygons($polys, $this->outW, $this->outH, $rule === 2 ? Rasterizer::EVENODD : Rasterizer::NONZERO);
        $mask->paint($target, $rgb, $opacity);
    }

    /**
     * Strokes are built in the local coordinate system of the shape and then
     * transformed (like After Effects / rlottie), so non-uniform scaling also
     * scales the stroke non-uniformly.
     * @param list<Path> $paths
     */
    private function strokeMask(array $paths, Matrix $m, float $width): ?Mask
    {
        $sf = max(1e-6, $m->scaleFactor());
        if ($width * $sf <= 0.05) {
            return null;
        }
        $polys = [];
        foreach ($paths as $p) {
            foreach ($p->contours as $c) {
                $single = new Path();
                $single->contours = [$c];
                foreach ($single->flatten(0.25 / $sf) as $poly) {
                    foreach (Stroke::polyline($poly, $width, $c['closed']) as $sp) {
                        $n = intdiv(count($sp), 2);
                        $tp = [];
                        for ($k = 0; $k < $n; $k++) {
                            [$x, $y] = $m->apply($sp[$k * 2], $sp[$k * 2 + 1]);
                            $tp[] = $x;
                            $tp[] = $y;
                        }
                        $polys[] = $tp;
                    }
                }
            }
        }
        return $polys ? $this->ras->fillPolygons($polys, $this->outW, $this->outH) : null;
    }

    /** @param list<Path> $paths */
    private function strokePaths(array $paths, Matrix $m, float $width, \GdImage $target, callable $color, float $opacity): void
    {
        $mask = $this->strokeMask($paths, $m, $width);
        $mask?->paint($target, $color(), $opacity);
    }

    /** @param list<Path> $paths */
    private function gradientFill(array $paths, array $it, float $t, Matrix $m, float $opacity, \GdImage $target): void
    {
        $polys = [];
        foreach ($paths as $p) {
            foreach ($p->transform($m)->flatten(0.25) as $poly) {
                $polys[] = $poly;
            }
        }
        if (!$polys) {
            return;
        }
        $mask = $this->ras->fillPolygons($polys, $this->outW, $this->outH, (int) ($it['r'] ?? 1) === 2 ? Rasterizer::EVENODD : Rasterizer::NONZERO);
        $this->paintGradient($mask, $it, $t, $m, $opacity, $target);
    }

    /** @param list<Path> $paths */
    private function gradientStroke(array $paths, array $it, float $t, Matrix $m, float $width, float $opacity, \GdImage $target): void
    {
        $mask = $this->strokeMask($paths, $m, $width);
        if ($mask !== null) {
            $this->paintGradient($mask, $it, $t, $m, $opacity, $target);
        }
    }

    private function paintGradient(Mask $mask, array $it, float $t, Matrix $m, float $opacity, \GdImage $target): void
    {
        $b = $mask->bounds();
        if ($b === null) {
            return;
        }
        $count = (int) ($it['g']['p'] ?? 2);
        $raw = $this->arrayValue($it['g']['k'] ?? null, $t);
        $raw = is_array($raw) ? array_values($raw) : [];
        $stops = self::gradientStops($raw, $count);
        if (!$stops) {
            return;
        }
        [$sx, $sy] = $m->apply(...$this->vec($it['s'] ?? null, $t, [0, 0]));
        [$ex, $ey] = $m->apply(...$this->vec($it['e'] ?? null, $t, [0, 0]));
        $radial = (int) ($it['t'] ?? 1) === 2;
        $dx = $ex - $sx;
        $dy = $ey - $sy;
        $len2 = max(1e-9, $dx * $dx + $dy * $dy);
        $len = sqrt($len2);
        // premultiplied lookup table (rlottie interpolates premultiplied colours)
        $lut = [];
        for ($k = 0; $k <= 128; $k++) {
            $lut[$k] = self::samplePremultiplied($stops, $k / 128);
        }
        imagealphablending($target, true);
        $w = $mask->width;
        for ($y = $b[1]; $y <= $b[3]; $y++) {
            $row = $y * $w;
            for ($x = $b[0]; $x <= $b[2]; $x++) {
                $cov = $mask->data[$row + $x];
                if ($cov <= 0.002) {
                    continue;
                }
                $px = $x + 0.5;
                $py = $y + 0.5;
                $pos = $radial ? hypot($px - $sx, $py - $sy) / $len : (($px - $sx) * $dx + ($py - $sy) * $dy) / $len2;
                $pos = $pos < 0 ? 0 : ($pos > 1 ? 1 : $pos);
                [$pr, $pg, $pb, $pa] = $lut[(int) round($pos * 128)];
                $a = $pa * $cov * $opacity;
                if ($a <= 0.002) {
                    continue;
                }
                $r = $pa > 1e-6 ? (int) min(255, round($pr / $pa)) : 0;
                $g = $pa > 1e-6 ? (int) min(255, round($pg / $pa)) : 0;
                $bl = $pa > 1e-6 ? (int) min(255, round($pb / $pa)) : 0;
                $alpha = 127 - (int) round($a * 127);
                imagesetpixel($target, $x, $y, ($alpha << 24) | ($r << 16) | ($g << 8) | $bl);
            }
        }
    }

    /**
     * Gradient stops exactly like rlottie (Telegram's renderer): one stop per
     * colour stop; its opacity is interpolated from the opacity stops that
     * bracket it, the first opacity is used before the first opacity stop and
     * 0 after the last one.
     * @param list<float> $raw
     * @return list<array{0:float,1:float,2:float,3:float,4:float}> pos, r, g, b, alpha
     */
    public static function gradientStops(array $raw, int $count): array
    {
        $stops = [];
        $op = array_slice($raw, $count * 4);
        $opStops = [];
        for ($k = 0; $k + 1 < count($op); $k += 2) {
            $opStops[] = [(float) $op[$k], (float) $op[$k + 1]];
        }
        for ($k = 0; $k < $count; $k++) {
            if (!isset($raw[$k * 4 + 3])) {
                break;
            }
            $pos = (float) $raw[$k * 4];
            $alpha = 1.0;
            if ($opStops) {
                $alpha = 0.0;
                foreach ($opStops as $j => $os) {
                    if ($os[0] >= $pos) {
                        if ($j === 0) {
                            $alpha = $os[1];
                        } else {
                            $prev = $opStops[$j - 1];
                            $span = $os[0] - $prev[0];
                            $alpha = $span > 1e-9 ? $prev[1] + ($pos - $prev[0]) / $span * ($os[1] - $prev[1]) : $os[1];
                        }
                        break;
                    }
                }
            }
            $stops[] = [$pos, (float) $raw[$k * 4 + 1], (float) $raw[$k * 4 + 2], (float) $raw[$k * 4 + 3], max(0.0, min(1.0, $alpha))];
        }
        usort($stops, static fn ($a, $b) => $a[0] <=> $b[0]);
        return $stops;
    }

    /** @return array{0:float,1:float,2:float,3:float} premultiplied r,g,b (0..255*a) and alpha */
    private static function samplePremultiplied(array $stops, float $pos): array
    {
        $n = count($stops);
        $pm = static fn (array $s) => [$s[1] * 255 * $s[4], $s[2] * 255 * $s[4], $s[3] * 255 * $s[4], $s[4]];
        if ($pos <= $stops[0][0]) {
            return $pm($stops[0]);
        }
        for ($k = 1; $k < $n; $k++) {
            if ($pos <= $stops[$k][0]) {
                $a = $pm($stops[$k - 1]);
                $b = $pm($stops[$k]);
                $span = $stops[$k][0] - $stops[$k - 1][0];
                $f = $span > 1e-9 ? ($pos - $stops[$k - 1][0]) / $span : 1.0;
                return [$a[0] + ($b[0] - $a[0]) * $f, $a[1] + ($b[1] - $a[1]) * $f, $a[2] + ($b[2] - $a[2]) * $f, $a[3] + ($b[3] - $a[3]) * $f];
            }
        }
        return $pm($stops[$n - 1]);
    }

    private function drawImage(array $it, Matrix $m, float $opacity, \GdImage $target): void
    {
        $img = $this->images[(string) ($it['ref'] ?? '')] ?? null;
        $rect = $it['rect'] ?? null;
        if (!$img || !is_array($rect)) {
            return;
        }
        // the slot transform is a general affine map: use 4-corner bounds for
        // placement with rotation support through imagerotate
        $cx = ($rect[0] + $rect[2]) / 2;
        $cy = ($rect[1] + $rect[3]) / 2;
        [$ox, $oy] = $m->apply($cx, $cy);
        $svd = \EmojiMaker\Rendering\Transform::svd(new Matrix($m->a, $m->b, $m->c, $m->d, 0, 0));
        $sx = abs($svd['s1']);
        $sy = abs($svd['s2']);
        $rot = $svd['phi'] + $svd['theta'];
        $w = max(1, (int) round(($rect[2] - $rect[0]) * $sx));
        $h = max(1, (int) round(($rect[3] - $rect[1]) * $sy));
        if ($w < 2 || $h < 2) {
            return;
        }
        $scaled = ImageProcessor::canvas($w, $h);
        imagecopyresampled($scaled, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
        if ($m->a * $m->d - $m->b * $m->c < 0) {
            imageflip($scaled, IMG_FLIP_HORIZONTAL);
        }
        if (abs(fmod($rot, 360.0)) > 0.1) {
            imagesetinterpolation($scaled, IMG_BILINEAR_FIXED);
            $r = imagerotate($scaled, -$rot, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
            if ($r instanceof \GdImage) {
                imagealphablending($r, false);
                imagesavealpha($r, true);
                $scaled = $r;
            }
        }
        if ($opacity < 0.999) {
            self::scaleAlpha($scaled, $opacity);
        }
        imagealphablending($target, true);
        imagecopy($target, $scaled, (int) round($ox - imagesx($scaled) / 2), (int) round($oy - imagesy($scaled) / 2), 0, 0, imagesx($scaled), imagesy($scaled));
    }

    private function applyMasks(\GdImage $layerCanvas, array $layer, float $t, Matrix $m): void
    {
        $result = null;
        foreach ((array) $layer['masksProperties'] as $mp) {
            $mode = (string) ($mp['mode'] ?? 'a');
            if ($mode === 'n') {
                continue;
            }
            $shape = $this->shapePath(['ks' => $mp['pt'] ?? null], $t);
            if ($shape === null) {
                continue;
            }
            $mo = $this->value($mp['o'] ?? null, $t, 100) / 100;
            $mask = $this->ras->fillPath($shape, $this->outW, $this->outH, $m)->scaleValues($mo);
            if (!empty($mp['inv'])) {
                $mask = (new Mask($this->outW, $this->outH, array_fill(0, $this->outW * $this->outH, 1.0)))->subtract($mask);
            }
            if ($result === null) {
                $result = $mode === 's' ? (new Mask($this->outW, $this->outH, array_fill(0, $this->outW * $this->outH, 1.0)))->subtract($mask) : $mask;
            } elseif ($mode === 'a') {
                $result = $result->union($mask);
            } elseif ($mode === 's') {
                $result = $result->subtract($mask);
            } elseif ($mode === 'i') {
                $result = $result->intersect($mask);
            }
        }
        if ($result !== null) {
            self::multiplyAlpha($layerCanvas, $result);
        }
    }

    private static function applyMatte(\GdImage $layer, \GdImage $matte, int $tt): void
    {
        $w = imagesx($layer);
        $h = imagesy($layer);
        imagealphablending($layer, false);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($layer, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 127) {
                    continue;
                }
                $mc = imagecolorat($matte, $x, $y);
                $ma = (127 - (($mc >> 24) & 0x7F)) / 127;
                if ($tt === 3 || $tt === 4) {
                    $lum = (0.299 * (($mc >> 16) & 0xFF) + 0.587 * (($mc >> 8) & 0xFF) + 0.114 * ($mc & 0xFF)) / 255;
                    $f = $lum * $ma;
                } else {
                    $f = $ma;
                }
                if ($tt === 2 || $tt === 4) {
                    $f = 1 - $f;
                }
                $na = 127 - (int) round((127 - $a) * $f);
                imagesetpixel($layer, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
            }
        }
    }

    private static function multiplyAlpha(\GdImage $img, Mask $mask): void
    {
        $w = imagesx($img);
        $h = imagesy($img);
        imagealphablending($img, false);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $a = ($c >> 24) & 0x7F;
                if ($a >= 127) {
                    continue;
                }
                $f = $mask->data[$y * $w + $x];
                $na = 127 - (int) round((127 - $a) * $f);
                imagesetpixel($img, $x, $y, ($na << 24) | ($c & 0xFFFFFF));
            }
        }
    }

    private static function scaleAlpha(\GdImage $img, float $f): void
    {
        \EmojiMaker\Rendering\RasterRenderer::scaleAlpha($img, $f);
    }

    // ------------------------------------------------------------------
    // Property evaluation
    // ------------------------------------------------------------------

    public function transformMatrix(array $tr, float $t): Matrix
    {
        $a = $this->vec($tr['a'] ?? null, $t, [0, 0]);
        if (isset($tr['p']['s']) && $tr['p']['s']) {
            $p = [$this->value($tr['p']['x'] ?? null, $t, 0), $this->value($tr['p']['y'] ?? null, $t, 0)];
        } else {
            $p = $this->vec($tr['p'] ?? null, $t, [0, 0]);
        }
        $s = $this->vec($tr['s'] ?? null, $t, [100, 100]);
        $r = $this->value($tr['r'] ?? ($tr['rz'] ?? null), $t, 0);
        $m = Matrix::translation($p[0], $p[1])->multiply(Matrix::rotation($r));
        $sk = $this->value($tr['sk'] ?? null, $t, 0);
        if (abs($sk) > 0.01) {
            $sa = $this->value($tr['sa'] ?? null, $t, 0);
            $skew = new Matrix(1, 0, tan(deg2rad(-$sk)), 1, 0, 0);
            $m = $m->multiply(Matrix::rotation($sa))->multiply($skew)->multiply(Matrix::rotation(-$sa));
        }
        return $m->multiply(Matrix::scaling($s[0] / 100, ($s[1] ?? $s[0]) / 100))->multiply(Matrix::translation(-$a[0], -$a[1]));
    }

    public function value(mixed $prop, float $t, float $default): float
    {
        $v = $this->arrayValue($prop, $t);
        if ($v === null || $v === []) {
            return $default;
        }
        return (float) (is_array($v) ? ($v[0] ?? $default) : $v);
    }

    /** @return list<float> */
    public function vec(mixed $prop, float $t, array $default): array
    {
        $v = $this->arrayValue($prop, $t);
        if (!is_array($v) || $v === []) {
            return is_numeric($v) ? [(float) $v, (float) $v] : $default;
        }
        return array_map('floatval', array_values($v));
    }

    /** @return array{0:int,1:int,2:int} */
    private function colorValue(mixed $prop, float $t): array
    {
        $v = $this->arrayValue($prop, $t) ?? [0, 0, 0];
        $v = is_array($v) ? $v : [$v, $v, $v];
        $scale = (max(abs((float) ($v[0] ?? 0)), abs((float) ($v[1] ?? 0)), abs((float) ($v[2] ?? 0))) > 1.0) ? 1.0 : 255.0;
        return [
            (int) max(0, min(255, round((float) ($v[0] ?? 0) * $scale))),
            (int) max(0, min(255, round((float) ($v[1] ?? 0) * $scale))),
            (int) max(0, min(255, round((float) ($v[2] ?? 0) * $scale))),
        ];
    }

    /**
     * Raw (possibly multi-dimensional) value of an animatable property.
     * @return array<int,float>|float|null
     */
    public function arrayValue(mixed $prop, float $t): array|float|null
    {
        if (!is_array($prop)) {
            return is_numeric($prop) ? (float) $prop : null;
        }
        if (!array_key_exists('k', $prop)) {
            return null;
        }
        $k = $prop['k'];
        $animated = (int) ($prop['a'] ?? 0) === 1 || (is_array($k) && isset($k[0]) && is_array($k[0]) && isset($k[0]['t']));
        if (!$animated) {
            return is_array($k) ? $k : (float) $k;
        }
        return $this->interpolate($k, $t, isset($prop['ix']) ? false : true);
    }

    /** @param list<array<string,mixed>> $kfs */
    private function interpolate(array $kfs, float $t, bool $spatialCandidate = true): array|float
    {
        $n = count($kfs);
        $first = $kfs[0];
        if ($t <= (float) $first['t']) {
            return self::kfStart($first, null);
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $a = $kfs[$i];
            $b = $kfs[$i + 1];
            $ta = (float) $a['t'];
            $tb = (float) $b['t'];
            if ($t >= $ta && $t < $tb) {
                $start = self::kfStart($a, null);
                if (!empty($a['h'])) {
                    return $start;
                }
                $end = isset($a['e']) ? $a['e'] : self::kfStart($b, null);
                $p = ($t - $ta) / max(1e-9, $tb - $ta);
                $start = is_array($start) ? $start : [$start];
                $end = is_array($end) ? $end : [$end];
                $out = [];
                $dims = count($start);
                $hasSpatial = isset($a['to'], $a['ti']) && $dims >= 2 && (abs((float) ($a['to'][0] ?? 0)) + abs((float) ($a['to'][1] ?? 0)) + abs((float) ($a['ti'][0] ?? 0)) + abs((float) ($a['ti'][1] ?? 0))) > 0.001;
                if ($hasSpatial) {
                    $e = self::ease($a, $p, 0);
                    $mt = 1 - $e;
                    for ($d = 0; $d < 2; $d++) {
                        $p0 = (float) $start[$d];
                        $p3 = (float) $end[$d];
                        $p1 = $p0 + (float) ($a['to'][$d] ?? 0);
                        $p2 = $p3 + (float) ($a['ti'][$d] ?? 0);
                        $out[$d] = $mt * $mt * $mt * $p0 + 3 * $mt * $mt * $e * $p1 + 3 * $mt * $e * $e * $p2 + $e * $e * $e * $p3;
                    }
                    for ($d = 2; $d < $dims; $d++) {
                        $out[$d] = (float) $start[$d] + ((float) ($end[$d] ?? $start[$d]) - (float) $start[$d]) * $e;
                    }
                    return $out;
                }
                for ($d = 0; $d < $dims; $d++) {
                    $e = self::ease($a, $p, $d);
                    $sv = $start[$d];
                    $ev = $end[$d] ?? $sv;
                    if (is_array($sv)) {
                        $out[$d] = $sv; // nested (shape) handled elsewhere
                        continue;
                    }
                    $out[$d] = (float) $sv + ((float) $ev - (float) $sv) * $e;
                }
                return $out;
            }
        }
        $last = $kfs[$n - 1];
        if (isset($last['s'])) {
            return self::kfStart($last, null);
        }
        $prev = $kfs[$n - 2] ?? $last;
        return $prev['e'] ?? self::kfStart($prev, null);
    }

    private static function kfStart(array $kf, mixed $default): mixed
    {
        $s = $kf['s'] ?? $default;
        return is_array($s) && count($s) === 1 && !is_array($s[0]) ? (float) $s[0] : $s;
    }

    /** Bezier easing of keyframe $a at linear progress $p for dimension $d. */
    private static function ease(array $a, float $p, int $d): float
    {
        $o = $a['o'] ?? null;
        $i = $a['i'] ?? null;
        if (!is_array($o) || !is_array($i)) {
            return $p;
        }
        $ox = is_array($o['x'] ?? null) ? (float) ($o['x'][$d] ?? $o['x'][0] ?? 0) : (float) ($o['x'] ?? 0);
        $oy = is_array($o['y'] ?? null) ? (float) ($o['y'][$d] ?? $o['y'][0] ?? 0) : (float) ($o['y'] ?? 0);
        $ix = is_array($i['x'] ?? null) ? (float) ($i['x'][$d] ?? $i['x'][0] ?? 1) : (float) ($i['x'] ?? 1);
        $iy = is_array($i['y'] ?? null) ? (float) ($i['y'][$d] ?? $i['y'][0] ?? 1) : (float) ($i['y'] ?? 1);
        if (abs($ox - $oy) < 1e-6 && abs($ix - $iy) < 1e-6) {
            return $p;
        }
        // solve x(u) = p for u (bisection + Newton), return y(u)
        $u = $p;
        for ($k = 0; $k < 8; $k++) {
            $x = self::bez($u, $ox, $ix) - $p;
            $dx = self::bezD($u, $ox, $ix);
            if (abs($x) < 1e-5) {
                break;
            }
            if (abs($dx) < 1e-6) {
                break;
            }
            $u -= $x / $dx;
            $u = max(0.0, min(1.0, $u));
        }
        if (abs(self::bez($u, $ox, $ix) - $p) > 1e-3) {
            $lo = 0.0;
            $hi = 1.0;
            for ($k = 0; $k < 30; $k++) {
                $u = ($lo + $hi) / 2;
                if (self::bez($u, $ox, $ix) < $p) {
                    $lo = $u;
                } else {
                    $hi = $u;
                }
            }
        }
        return self::bez($u, $oy, $iy);
    }

    private static function bez(float $u, float $p1, float $p2): float
    {
        $mu = 1 - $u;
        return 3 * $mu * $mu * $u * $p1 + 3 * $mu * $u * $u * $p2 + $u * $u * $u;
    }

    private static function bezD(float $u, float $p1, float $p2): float
    {
        $mu = 1 - $u;
        return 3 * $mu * $mu * $p1 + 6 * $mu * $u * ($p2 - $p1) + 3 * $u * $u * (1 - $p2);
    }

    /** Animated path keyframes -> interpolated {v,i,o,c}. */
    private function interpolateShape(array $kfs, float $t): ?array
    {
        $n = count($kfs);
        if ($n === 0) {
            return null;
        }
        $get = static function (array $kf, string $key) {
            $s = $kf[$key] ?? null;
            return is_array($s) && isset($s[0]) && is_array($s[0]) && isset($s[0]['v']) ? $s[0] : (is_array($s) && isset($s['v']) ? $s : null);
        };
        if ($t <= (float) $kfs[0]['t']) {
            return $get($kfs[0], 's');
        }
        for ($i = 0; $i < $n - 1; $i++) {
            $a = $kfs[$i];
            $b = $kfs[$i + 1];
            if ($t >= (float) $a['t'] && $t < (float) $b['t']) {
                $sa = $get($a, 's');
                $sb = isset($a['e']) ? $get($a, 'e') : $get($b, 's');
                if ($sa === null) {
                    return null;
                }
                if (!empty($a['h']) || $sb === null || count($sb['v']) !== count($sa['v'])) {
                    return $sa;
                }
                $p = ($t - (float) $a['t']) / max(1e-9, (float) $b['t'] - (float) $a['t']);
                $e = self::ease($a, $p, 0);
                $out = ['c' => $sa['c'] ?? false, 'v' => [], 'i' => [], 'o' => []];
                foreach (['v', 'i', 'o'] as $key) {
                    foreach ($sa[$key] as $k => $pt) {
                        $q = $sb[$key][$k] ?? $pt;
                        $out[$key][] = [$pt[0] + ($q[0] - $pt[0]) * $e, $pt[1] + ($q[1] - $pt[1]) * $e];
                    }
                }
                return $out;
            }
        }
        $last = $kfs[$n - 1];
        return $get($last, 's') ?? $get($kfs[$n - 2] ?? $last, 'e') ?? $get($kfs[$n - 2] ?? $last, 's');
    }

    /** @return array{0:int,1:int,2:int} */
    private static function hexColor(string $hex): array
    {
        return \EmojiMaker\Rendering\Color::hexToRgb($hex);
    }
}

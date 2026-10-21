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
    /** @var array<string,array{w:int,h:int,px:list<array{0:float,1:float,2:float,3:float}>}> */
    private array $imageCache = [];
    /** @var array<string,array<string,mixed>> image assets by id */
    private array $imageAssets = [];
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
    /** tag inherited from a tagged layer while probing */
    private ?string $layerTag = null;

    /** @param array<string,mixed> $doc decoded Lottie JSON */
    public function __construct(array $doc)
    {
        $this->doc = $doc;
        foreach ((array) ($doc['assets'] ?? []) as $a) {
            if (isset($a['id'], $a['layers'])) {
                $this->assets[(string) $a['id']] = $a['layers'];
            } elseif (isset($a['id'], $a['p'])) {
                $this->imageAssets[(string) $a['id']] = $a;
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
        foreach (array_keys($this->imageCache) as $k) {
            if (str_starts_with($k, $ref . '@')) {
                unset($this->imageCache[$k]);
            }
        }
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

    /**
     * Tight bounds of the geometry inside $items (nested groups with their
     * transforms, trims applied) at time $t, mapped through $m. Stroke widths
     * are not included.
     * @param list<array<string,mixed>> $items
     * @return array{0:float,1:float,2:float,3:float}|null
     */
    public function itemsBounds(array $items, float $t, ?Matrix $m = null): ?array
    {
        $geo = $this->buildGeometry($items, $t, true, null);
        $box = null;
        foreach ($this->pathsBefore($geo, PHP_INT_MAX) as $p) {
            $path = $m !== null && !$m->isIdentity() ? $p['path']->transform($m) : $p['path'];
            foreach ($path->flatten(0.5) as $poly) {
                $n = count($poly);
                for ($i = 0; $i < $n; $i += 2) {
                    $x = $poly[$i];
                    $y = $poly[$i + 1];
                    if ($box === null) {
                        $box = [$x, $y, $x, $y];
                        continue;
                    }
                    if ($x < $box[0]) { $box[0] = $x; }
                    if ($y < $box[1]) { $box[1] = $y; }
                    if ($x > $box[2]) { $box[2] = $x; }
                    if ($y > $box[3]) { $box[3] = $y; }
                }
            }
        }
        return $box;
    }

    /** Matrix of a group's "tr" item at time $t (identity when absent). */
    public function groupMatrix(array $group, float $t): Matrix
    {
        foreach ((array) ($group['it'] ?? []) as $it) {
            if (is_array($it) && ($it['ty'] ?? '') === 'tr') {
                return $this->transformMatrix($it, $t);
            }
        }
        return Matrix::identity();
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
            if (!$probe && !empty($layer['td']) && (int) ($layers[$i + 1]['tt'] ?? 0) > 0) {
                continue; // matte source, consumed by the layer below it (probing measures it too)
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
                // a tagged layer tags everything it draws (precomp children included)
                $prevTag = $this->layerTag;
                if (isset($layer['_emx'])) {
                    $this->layerTag = (string) $layer['_emx'];
                }
                $this->drawLayerContent($layer, $t, $matrix, $target, 1.0, true);
                $this->layerTag = $prevTag;
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
            $this->renderGroup((array) ($layer['shapes'] ?? []), $t, $matrix, $opacity, $target, $probe, $probe ? $this->layerTag : null);
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
        } elseif ($ty === 2 && !$probe) {
            // embedded raster image asset (data URI)
            $refId = (string) ($layer['refId'] ?? '');
            $ref = 'asset:' . $refId;
            if (!isset($this->images[$ref])) {
                $asset = $this->imageAssets[$refId] ?? null;
                $img = null;
                if ($asset !== null && preg_match('~^data:image/(png|jpe?g|webp);base64,~', (string) ($asset['p'] ?? ''), $mm)) {
                    $bin = base64_decode(substr((string) $asset['p'], strlen($mm[0])), true);
                    $img = $bin !== false && strlen($bin) < 8 * 1024 * 1024 ? @imagecreatefromstring($bin) : false;
                }
                if (!$img instanceof \GdImage) {
                    return;
                }
                imagealphablending($img, false);
                imagesavealpha($img, true);
                $this->images[$ref] = $img;
            }
            $asset = $this->imageAssets[$refId];
            $w = (float) ($asset['w'] ?? imagesx($this->images[$ref]));
            $h = (float) ($asset['h'] ?? imagesy($this->images[$ref]));
            $this->drawImage(['ref' => $ref, 'rect' => [0, 0, $w, $h]], $matrix, $opacity, $target);
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
     * a style draws all paths listed before it, as modified by every trim of the group).
     * @param list<array<string,mixed>> $items
     * @param array{nodes:array<int,mixed>,all:list<\stdClass>}|null $geo geometry of $items (built when null)
     */
    private function renderGroup(array $items, float $t, Matrix $matrix, float $opacity, \GdImage $target, bool $probe = false, ?string $inheritedTag = null, ?array $geo = null): void
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
        $geo ??= $this->buildGeometry($items, $t, $probe, $inheritedTag);
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
                $sub = $geo['nodes'][$s] ?? null;
                $this->renderGroup((array) ($it['it'] ?? []), $t, $m, $op, $target, $probe, $itemTag, is_array($sub) ? $sub['geo'] : null);
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
            $paths = $this->pathsBefore($geo, $s);
            if (!$paths) {
                continue;
            }
            if ($probe) {
                if ($this->probeTag !== null && ($itemTag === $this->probeTag || $this->anyTagged($items, $s))) {
                    foreach ($paths as $p) {
                        if (($p['tag'] ?? null) === $this->probeTag || $itemTag === $this->probeTag) {
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
                    $this->strokePaths($plain, $m, $w, $target, fn () => $c, $styleOpacity, $it);
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
     * Geometry of a group with its trims applied the way rlottie does: a trim
     * modifies every path listed before it in its group (nested groups included),
     * trims compound in list order, and every style drawing such a path sees the
     * trimmed result even when the style is listed before the trim.
     * Paths stay in their own group's coordinates; nested groups keep their matrix.
     * @return array{nodes:array<int,mixed>,all:list<\stdClass>}
     */
    private function buildGeometry(array $items, float $t, bool $probe, ?string $tag): array
    {
        $nodes = [];
        $all = [];
        foreach ($items as $i => $it) {
            if (!empty($it['hd'])) {
                continue;
            }
            if ($this->skipFilter !== null && !$probe && ($this->skipFilter)($it)) {
                continue;
            }
            $ty = (string) ($it['ty'] ?? '');
            $itTag = $it['_emx'] ?? $tag;
            if ($ty === 'gr') {
                $sub = (array) ($it['it'] ?? []);
                $gm = Matrix::identity();
                foreach ($sub as $si) {
                    if (($si['ty'] ?? '') === 'tr') {
                        $gm = $this->transformMatrix($si, $t);
                        break;
                    }
                }
                $g = $this->buildGeometry($sub, $t, $probe, $itTag);
                $nodes[$i] = ['m' => $gm, 'geo' => $g];
                foreach ($g['all'] as $ref) {
                    $all[] = $ref;
                }
                continue;
            }
            if ($ty === 'tm') {
                $this->applyTrim($all, $it, $t);
                continue;
            }
            $path = match ($ty) {
                'sh' => $this->shapePath($it, $t),
                'rc' => $this->rectPath($it, $t),
                'el' => $this->ellipsePath($it, $t),
                'sr' => $this->star($it, $t),
                default => null,
            };
            if ($path !== null) {
                $ref = new \stdClass();
                $ref->path = $path;
                $ref->tag = $itTag;
                $nodes[$i] = $ref;
                $all[] = $ref;
            }
        }
        return ['nodes' => $nodes, 'all' => $all];
    }

    /**
     * Paths drawn by the style at index $s: geometry listed before it, nested
     * groups mapped into this group's coordinates.
     * @param array{nodes:array<int,mixed>,all:list<\stdClass>} $geo
     * @return list<array{path:Path,tag:?string}>
     */
    private function pathsBefore(array $geo, int $s): array
    {
        $out = [];
        foreach ($geo['nodes'] as $i => $node) {
            if ($i >= $s) {
                break;
            }
            if ($node instanceof \stdClass) {
                $out[] = ['path' => $node->path, 'tag' => $node->tag];
                continue;
            }
            $gm = $node['m'];
            foreach ($this->pathsBefore($node['geo'], PHP_INT_MAX) as $p) {
                $out[] = ['path' => $gm->isIdentity() ? $p['path'] : $p['path']->transform($gm), 'tag' => $p['tag']];
            }
        }
        return $out;
    }

    /** rlottie rectangle: starts at the top-right corner, clockwise unless d=3. */
    private function rectPath(array $it, float $t): Path
    {
        $p = $this->vec($it['p'] ?? null, $t, [0, 0]);
        $sz = $this->vec($it['s'] ?? null, $t, [0, 0]);
        $r = $this->value($it['r'] ?? null, $t, 0);
        $x = $p[0] - $sz[0] / 2;
        $y = $p[1] - $sz[1] / 2;
        $w = $sz[0];
        $h = $sz[1];
        $ccw = (int) ($it['d'] ?? 1) === 3;
        $path = new Path();
        $r = min($r, $w / 2, $h / 2);
        if ($r <= 1e-6) {
            $path->moveTo($x + $w, $y);
            if ($ccw) {
                $path->lineTo($x, $y)->lineTo($x, $y + $h)->lineTo($x + $w, $y + $h);
            } else {
                $path->lineTo($x + $w, $y + $h)->lineTo($x, $y + $h)->lineTo($x, $y);
            }
            $path->lineTo($x + $w, $y);
            return $path->close();
        }
        $k = 0.5522847498 * $r;
        $path->moveTo($x + $w, $y + $r);
        if ($ccw) {
            $path->cubicTo($x + $w, $y + $r - $k, $x + $w - $r + $k, $y, $x + $w - $r, $y)
                ->lineTo($x + $r, $y)
                ->cubicTo($x + $r - $k, $y, $x, $y + $r - $k, $x, $y + $r)
                ->lineTo($x, $y + $h - $r)
                ->cubicTo($x, $y + $h - $r + $k, $x + $r - $k, $y + $h, $x + $r, $y + $h)
                ->lineTo($x + $w - $r, $y + $h)
                ->cubicTo($x + $w - $r + $k, $y + $h, $x + $w, $y + $h - $r + $k, $x + $w, $y + $h - $r)
                ->lineTo($x + $w, $y + $r);
        } else {
            $path->lineTo($x + $w, $y + $h - $r)
                ->cubicTo($x + $w, $y + $h - $r + $k, $x + $w - $r + $k, $y + $h, $x + $w - $r, $y + $h)
                ->lineTo($x + $r, $y + $h)
                ->cubicTo($x + $r - $k, $y + $h, $x, $y + $h - $r + $k, $x, $y + $h - $r)
                ->lineTo($x, $y + $r)
                ->cubicTo($x, $y + $r - $k, $x + $r - $k, $y, $x + $r, $y)
                ->lineTo($x + $w - $r, $y)
                ->cubicTo($x + $w - $r + $k, $y, $x + $w, $y + $r - $k, $x + $w, $y + $r);
        }
        return $path->close();
    }

    /** rlottie ellipse: starts at 12 o'clock, clockwise unless d=3. */
    private function ellipsePath(array $it, float $t): Path
    {
        $p = $this->vec($it['p'] ?? null, $t, [0, 0]);
        $sz = $this->vec($it['s'] ?? null, $t, [0, 0]);
        $cx = $p[0];
        $cy = $p[1];
        $rx = $sz[0] / 2;
        $ry = $sz[1] / 2;
        $kx = 0.5522847498 * $rx;
        $ky = 0.5522847498 * $ry;
        $path = new Path();
        $path->moveTo($cx, $cy - $ry);
        if ((int) ($it['d'] ?? 1) === 3) {
            $path->cubicTo($cx - $kx, $cy - $ry, $cx - $rx, $cy - $ky, $cx - $rx, $cy)
                ->cubicTo($cx - $rx, $cy + $ky, $cx - $kx, $cy + $ry, $cx, $cy + $ry)
                ->cubicTo($cx + $kx, $cy + $ry, $cx + $rx, $cy + $ky, $cx + $rx, $cy)
                ->cubicTo($cx + $rx, $cy - $ky, $cx + $kx, $cy - $ry, $cx, $cy - $ry);
        } else {
            $path->cubicTo($cx + $kx, $cy - $ry, $cx + $rx, $cy - $ky, $cx + $rx, $cy)
                ->cubicTo($cx + $rx, $cy + $ky, $cx + $kx, $cy + $ry, $cx, $cy + $ry)
                ->cubicTo($cx - $kx, $cy + $ry, $cx - $rx, $cy + $ky, $cx - $rx, $cy)
                ->cubicTo($cx - $rx, $cy - $ky, $cx - $kx, $cy - $ry, $cx, $cy - $ry);
        }
        return $path->close();
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
        if ((int) ($it['d'] ?? 1) === 3) {
            $pts = array_merge([$pts[0]], array_reverse(array_slice($pts, 1)));
        }
        return Path::polygon($pts);
    }

    /**
     * Trim path modifier (rlottie semantics). m=1 (simultaneously) trims each
     * path on its own; m=2 (individually) spreads one window over the paths in order.
     * @param list<\stdClass> $refs
     */
    private function applyTrim(array $refs, array $tm, float $t): void
    {
        if (!$refs) {
            return;
        }
        $seg = $this->trimSegment($tm, $t);
        if ($seg === null) {
            return;
        }
        if ($seg === []) {
            foreach ($refs as $ref) {
                $ref->path = new Path();
            }
            return;
        }
        [$start, $end] = $seg;
        if ((int) ($tm['m'] ?? 1) !== 2) {
            foreach ($refs as $ref) {
                $ref->path = self::trimPath($ref->path, $start, $end);
            }
            return;
        }
        if ($start >= $end) {
            return; // rlottie leaves wrapped windows untouched in this mode
        }
        $lens = [];
        $total = 0.0;
        foreach ($refs as $k => $ref) {
            $lens[$k] = self::pathLength($ref->path);
            $total += $lens[$k];
        }
        $from = $total * $start;
        $to = $total * $end;
        $cur = 0.0;
        foreach ($refs as $k => $ref) {
            $len = $lens[$k];
            if ($cur > $to || ($cur < $from && $cur + $len < $from)) {
                $ref->path = new Path();
            } elseif (!($from <= $cur && $to >= $cur + $len) && $len > 0) {
                $ls = ($from > $cur ? $from - $cur : 0) / $len;
                $le = ($cur + $len < $to ? $len : $to - $cur) / $len;
                $ref->path = self::trimPath($ref->path, $ls, $le);
            }
            $cur += $len;
        }
    }

    /**
     * Trim window as rlottie computes it: null = untouched, [] = empty,
     * [start,end] fractions where start > end means the window wraps.
     * @return array{0:float,1:float}|array{}|null
     */
    private function trimSegment(array $tm, float $t): ?array
    {
        $s = $this->value($tm['s'] ?? null, $t, 0) / 100;
        $e = $this->value($tm['e'] ?? null, $t, 100) / 100;
        $o = fmod($this->value($tm['o'] ?? null, $t, 0), 360.0) / 360;
        $diff = abs($s - $e);
        if ($diff < 1e-5) {
            return [];
        }
        if (abs($diff - 1) < 1e-5) {
            return null;
        }
        $s += $o;
        $e += $o;
        $noloop = static fn (float $a, float $b): array => [min($a, $b), max($a, $b)];
        $loop = static fn (float $a, float $b): array => [max($a, $b), min($a, $b)];
        if ($o > 0) {
            if ($s <= 1 && $e <= 1) {
                return $noloop($s, $e);
            }
            if ($s > 1 && $e > 1) {
                return $noloop($s - 1, $e - 1);
            }
            return $s > 1 ? $loop($s - 1, $e) : $loop($s, $e - 1);
        }
        if ($s >= 0 && $e >= 0) {
            return $noloop($s, $e);
        }
        if ($s < 0 && $e < 0) {
            return $noloop(1 + $s, 1 + $e);
        }
        return $s < 0 ? $loop(1 + $s, $e) : $loop($s, 1 + $e);
    }

    /**
     * rlottie VPathMesure::trim: the window is measured against the length of the
     * whole path but (dasher semantics) restarts at every contour. Curves are cut
     * exactly, so the result stays smooth at any scale.
     */
    private static function trimPath(Path $path, float $start, float $end): Path
    {
        if (abs($start - $end) < 1e-6) {
            return new Path();
        }
        if ((abs($start) < 1e-6 && abs($end - 1) < 1e-6) || (abs($start - 1) < 1e-6 && abs($end) < 1e-6)) {
            return $path;
        }
        $contours = [];
        $total = 0.0;
        foreach ($path->contours as $c) {
            $m = self::measureContour($c);
            $contours[] = $m;
            $total += $m['length'];
        }
        if ($total <= 0) {
            return new Path();
        }
        $windows = $start < $end
            ? [[$total * $start, $total * $end]]
            : [[0.0, $total * $end], [$total * $start, $total]];
        $out = new Path();
        foreach ($contours as $m) {
            foreach ($windows as [$a, $b]) {
                self::appendRange($out, $m, $a, min($b, $m['length']));
            }
        }
        return $out;
    }

    private static function pathLength(Path $path): float
    {
        $len = 0.0;
        foreach ($path->contours as $c) {
            $len += self::measureContour($c)['length'];
        }
        return $len;
    }

    /**
     * Splits a contour into cubic segments with arc-length tables.
     * A closed contour includes its closing edge.
     * @param array{x:float,y:float,segs:list<array<int,float|int>>,closed:bool} $c
     * @return array{segs:list<array{p:list<float>,len:float,tab:list<float>}>,length:float}
     */
    private static function measureContour(array $c): array
    {
        $px = (float) $c['x'];
        $py = (float) $c['y'];
        $cubics = [];
        foreach ($c['segs'] as $s) {
            if ($s[0] === Path::LINE) {
                $cubics[] = [$px, $py, $px + ($s[1] - $px) / 3, $py + ($s[2] - $py) / 3, $px + 2 * ($s[1] - $px) / 3, $py + 2 * ($s[2] - $py) / 3, (float) $s[1], (float) $s[2], 1];
                [$px, $py] = [(float) $s[1], (float) $s[2]];
            } elseif ($s[0] === Path::QUAD) {
                $cubics[] = [$px, $py, $px + 2 * ($s[1] - $px) / 3, $py + 2 * ($s[2] - $py) / 3, $s[3] + 2 * ($s[1] - $s[3]) / 3, $s[4] + 2 * ($s[2] - $s[4]) / 3, (float) $s[3], (float) $s[4], 0];
                [$px, $py] = [(float) $s[3], (float) $s[4]];
            } else {
                $cubics[] = [$px, $py, (float) $s[1], (float) $s[2], (float) $s[3], (float) $s[4], (float) $s[5], (float) $s[6], 0];
                [$px, $py] = [(float) $s[5], (float) $s[6]];
            }
        }
        if ($c['closed'] && (abs($px - $c['x']) > 1e-6 || abs($py - $c['y']) > 1e-6)) {
            $x = (float) $c['x'];
            $y = (float) $c['y'];
            $cubics[] = [$px, $py, $px + ($x - $px) / 3, $py + ($y - $py) / 3, $px + 2 * ($x - $px) / 3, $py + 2 * ($y - $py) / 3, $x, $y, 1];
        }
        $segs = [];
        $total = 0.0;
        foreach ($cubics as $q) {
            $tab = [0.0];
            if ($q[8] === 1) {
                $l = hypot($q[6] - $q[0], $q[7] - $q[1]);
                $tab = [0.0, $l];
            } else {
                $poly = hypot($q[2] - $q[0], $q[3] - $q[1]) + hypot($q[4] - $q[2], $q[5] - $q[3]) + hypot($q[6] - $q[4], $q[7] - $q[5]);
                $n = (int) max(4, min(64, ceil($poly / 2)));
                $l = 0.0;
                $lx = $q[0];
                $ly = $q[1];
                for ($k = 1; $k <= $n; $k++) {
                    [$x, $y] = self::cubicPoint($q, $k / $n);
                    $l += hypot($x - $lx, $y - $ly);
                    $tab[] = $l;
                    $lx = $x;
                    $ly = $y;
                }
            }
            $segs[] = ['p' => array_slice($q, 0, 8), 'len' => $l, 'tab' => $tab];
            $total += $l;
        }
        return ['segs' => $segs, 'length' => $total];
    }

    /** @param array<int,float> $q */
    private static function cubicPoint(array $q, float $t): array
    {
        $mt = 1 - $t;
        $a = $mt * $mt * $mt;
        $b = 3 * $mt * $mt * $t;
        $c = 3 * $mt * $t * $t;
        $d = $t * $t * $t;
        return [$a * $q[0] + $b * $q[2] + $c * $q[4] + $d * $q[6], $a * $q[1] + $b * $q[3] + $c * $q[5] + $d * $q[7]];
    }

    /** Curve parameter at a given arc length inside a measured segment. */
    private static function paramAt(array $seg, float $len): float
    {
        $tab = $seg['tab'];
        $n = count($tab) - 1;
        if ($len <= 0 || $seg['len'] <= 0) {
            return 0.0;
        }
        if ($len >= $seg['len']) {
            return 1.0;
        }
        $lo = 0;
        $hi = $n;
        while ($hi - $lo > 1) {
            $mid = ($lo + $hi) >> 1;
            if ($tab[$mid] < $len) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        $span = $tab[$hi] - $tab[$lo];
        $f = $span > 0 ? ($len - $tab[$lo]) / $span : 0.0;
        return ($lo + $f) / $n;
    }

    /**
     * Sub-curve of a cubic between parameters t0 < t1 (de Casteljau).
     * @param list<float> $p
     * @return list<float>
     */
    private static function subCubic(array $p, float $t0, float $t1): array
    {
        $split = static function (array $q, float $t): array {
            $x01 = $q[0] + ($q[2] - $q[0]) * $t;
            $y01 = $q[1] + ($q[3] - $q[1]) * $t;
            $x12 = $q[2] + ($q[4] - $q[2]) * $t;
            $y12 = $q[3] + ($q[5] - $q[3]) * $t;
            $x23 = $q[4] + ($q[6] - $q[4]) * $t;
            $y23 = $q[5] + ($q[7] - $q[5]) * $t;
            $xa = $x01 + ($x12 - $x01) * $t;
            $ya = $y01 + ($y12 - $y01) * $t;
            $xb = $x12 + ($x23 - $x12) * $t;
            $yb = $y12 + ($y23 - $y12) * $t;
            $xm = $xa + ($xb - $xa) * $t;
            $ym = $ya + ($yb - $ya) * $t;
            return [[$q[0], $q[1], $x01, $y01, $xa, $ya, $xm, $ym], [$xm, $ym, $xb, $yb, $x23, $y23, $q[6], $q[7]]];
        };
        if ($t1 < 1.0) {
            $p = $split($p, $t1)[0];
        }
        if ($t0 > 0.0 && $t1 > 0) {
            $p = $split($p, $t0 / $t1)[1];
        }
        return $p;
    }

    /** Appends the part of a measured contour between arc lengths $a and $b. */
    private static function appendRange(Path $out, array $m, float $a, float $b): void
    {
        if ($b - $a <= 1e-6 || $a >= $m['length']) {
            return;
        }
        $acc = 0.0;
        $started = false;
        foreach ($m['segs'] as $seg) {
            $s0 = $acc;
            $s1 = $acc + $seg['len'];
            $acc = $s1;
            if ($s1 <= $a || $s0 >= $b || $seg['len'] <= 0) {
                continue;
            }
            $t0 = $a > $s0 ? self::paramAt($seg, $a - $s0) : 0.0;
            $t1 = $b < $s1 ? self::paramAt($seg, $b - $s0) : 1.0;
            $q = ($t0 > 0 || $t1 < 1) ? self::subCubic($seg['p'], $t0, $t1) : $seg['p'];
            if (!$started) {
                $out->moveTo($q[0], $q[1]);
                $started = true;
            }
            $out->cubicTo($q[2], $q[3], $q[4], $q[5], $q[6], $q[7]);
        }
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
     * Stroke coverage exactly like rlottie: the path is transformed to device
     * space and stroked with a uniform width scaled like rlottie's VMatrix::scale()
     * (length of the mapped (sqrt2, sqrt2) vector / 2); joins / caps / miter limit honoured.
     * @param list<Path> $paths
     * @param array<string,mixed> $item stroke item (lj, lc, ml)
     */
    private function strokeMask(array $paths, Matrix $m, float $width, array $item = []): ?Mask
    {
        $scale = sqrt((($m->a + $m->c) ** 2 + ($m->b + $m->d) ** 2) / 2);
        $w = $width * $scale;
        if ($w <= 0.05) {
            return null;
        }
        $join = (int) ($item['lj'] ?? Stroke::JOIN_ROUND);
        $cap = (int) ($item['lc'] ?? Stroke::CAP_ROUND);
        // rlottie defaults a missing miter limit to 0 and FreeType clamps it to 1
        $ml = max(1.0, (float) ($item['ml'] ?? 0.0));
        $polys = [];
        foreach ($paths as $p) {
            foreach ($p->transform($m)->contours as $c) {
                $single = new Path();
                $single->contours = [$c];
                foreach ($single->flatten(0.2) as $poly) {
                    foreach (Stroke::polyline($poly, $w, $c['closed'], $join, $cap, $ml) as $sp) {
                        $polys[] = $sp;
                    }
                }
            }
        }
        return $polys ? $this->ras->fillPolygons($polys, $this->outW, $this->outH) : null;
    }

    /** @param list<Path> $paths */
    private function strokePaths(array $paths, Matrix $m, float $width, \GdImage $target, callable $color, float $opacity, array $item = []): void
    {
        $mask = $this->strokeMask($paths, $m, $width, $item);
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
        $mask = $this->strokeMask($paths, $m, $width, $it);
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
        // like rlottie, the gradient lives in the shape's local space: every pixel
        // centre is mapped back through the inverse matrix, so non-uniform scale,
        // rotation and skew stretch the gradient together with the shape
        $det = $m->a * $m->d - $m->b * $m->c;
        if (abs($det) < 1e-12) {
            return;
        }
        $inv = $m->invert();
        [$sx, $sy] = $this->vec($it['s'] ?? null, $t, [0, 0]);
        [$ex, $ey] = $this->vec($it['e'] ?? null, $t, [0, 0]);
        $radial = (int) ($it['t'] ?? 1) === 2;
        $dx = $ex - $sx;
        $dy = $ey - $sy;
        $len2 = $dx * $dx + $dy * $dy;
        $lut = self::gradientTable($stops);
        $last = count($lut) - 1;
        if ($radial) {
            $r = sqrt($len2);
            $progress = $this->value($it['h'] ?? null, $t, 0) / 100;
            if (abs($progress - 1.0) < 1e-6) {
                $progress = 0.99;
            }
            $angle = deg2rad(rad2deg(atan2($dy, $dx)) + $this->value($it['a'] ?? null, $t, 0));
            $fx = $sx + cos($angle) * $progress * $r;
            $fy = $sy + sin($angle) * $progress * $r;
            $gdx = $sx - $fx;
            $gdy = $sy - $fy;
            $ga = $r * $r - $gdx * $gdx - $gdy * $gdy;
            if (abs($ga) < 1e-9) {
                return;
            }
            $inv2a = 1 / (2 * $ga);
        } elseif ($len2 < 1e-12) {
            return;
        }
        imagealphablending($target, true);
        $w = $mask->width;
        for ($y = $b[1]; $y <= $b[3]; $y++) {
            $row = $y * $w;
            $py = $y + 0.5;
            for ($x = $b[0]; $x <= $b[2]; $x++) {
                $cov = $mask->data[$row + $x];
                if ($cov <= 0.002) {
                    continue;
                }
                $px = $x + 0.5;
                $lx = $inv->a * $px + $inv->c * $py + $inv->e;
                $ly = $inv->b * $px + $inv->d * $py + $inv->f;
                if ($radial) {
                    $rx = $lx - $fx;
                    $ry = $ly - $fy;
                    $bb = 2 * ($rx * $gdx + $ry * $gdy);
                    $dd = $bb * $bb + 4 * $ga * ($rx * $rx + $ry * $ry);
                    if ($dd < 0) {
                        continue;
                    }
                    $pos = (sqrt($dd) - $bb) * $inv2a;
                } else {
                    $pos = (($lx - $sx) * $dx + ($ly - $sy) * $dy) / $len2;
                }
                $idx = (int) ($pos * $last + 0.5);
                [$pr, $pg, $pb, $pa] = $lut[$idx < 0 ? 0 : ($idx > $last ? $last : $idx)];
                $al = $pa * $cov * $opacity;
                if ($al <= 0.002) {
                    continue;
                }
                $rr = (int) min(255, round($pr / $pa));
                $gg = (int) min(255, round($pg / $pa));
                $bl = (int) min(255, round($pb / $pa));
                $alpha = 127 - (int) round($al * 127);
                imagesetpixel($target, $x, $y, ($alpha << 24) | ($rr << 16) | ($gg << 8) | $bl);
            }
        }
    }

    /**
     * 1024-entry premultiplied colour table built the way rlottie's
     * generateGradientColorTable does (pad spread, 8-bit premultiplied stops,
     * integer interpolate_pixel blending).
     * @param list<array{0:float,1:float,2:float,3:float,4:float}> $stops
     * @return list<array{0:float,1:float,2:float,3:float}> premultiplied r,g,b (0-255) and alpha (0-1)
     */
    private static function gradientTable(array $stops): array
    {
        $size = 1024;
        $pm = static function (array $s): array {
            $alpha = (int) (255 * max(0.0, min(1.0, $s[4])));
            $ch = static fn (float $v): int => (int) (255 * max(0.0, min(1.0, $v)));
            return [intdiv($ch($s[1]) * $alpha, 255), intdiv($ch($s[2]) * $alpha, 255), intdiv($ch($s[3]) * $alpha, 255), $alpha];
        };
        $ints = [];
        $incr = 1.0 / $size;
        $fpos = 1.5 * $incr;
        $cur = $pm($stops[0]);
        $ints[] = $cur;
        while ($fpos <= $stops[0][0] && count($ints) < $size) {
            $ints[] = $cur;
            $fpos += $incr;
        }
        $n = count($stops);
        for ($i = 0; $i < $n - 1; $i++) {
            $span = $stops[$i + 1][0] - $stops[$i][0];
            $delta = abs($span) > 1e-12 ? 1 / $span : 0.0;
            $next = $pm($stops[$i + 1]);
            while ($fpos < $stops[$i + 1][0] && count($ints) < $size) {
                $dist = (int) (255 * ($fpos - $stops[$i][0]) * $delta);
                $idist = 255 - $dist;
                $ints[] = [
                    ($cur[0] * $idist + $next[0] * $dist) >> 8,
                    ($cur[1] * $idist + $next[1] * $dist) >> 8,
                    ($cur[2] * $idist + $next[2] * $dist) >> 8,
                    ($cur[3] * $idist + $next[3] * $dist) >> 8,
                ];
                $fpos += $incr;
            }
            $cur = $next;
        }
        while (count($ints) < $size) {
            $ints[] = $cur;
        }
        $ints[$size - 1] = $cur;
        return array_map(static fn (array $c): array => [(float) $c[0], (float) $c[1], (float) $c[2], $c[3] / 255], $ints);
    }

    /**
     * Gradient stops exactly like rlottie (Telegram's renderer): one stop per
     * colour stop; its opacity is interpolated from the opacity stops that
     * bracket it, the first opacity is used before the first opacity stop and
     * 0 after the last one (or when there is a single opacity stop).
     * @param list<float> $raw
     * @return list<array{0:float,1:float,2:float,3:float,4:float}> pos, r, g, b, alpha
     */
    public static function gradientStops(array $raw, int $count): array
    {
        $stops = [];
        if ($count < 0 || $count * 4 > count($raw)) {
            $count = intdiv(count($raw), 4); // legacy bodymovin
        }
        $op = array_slice($raw, $count * 4);
        $opStops = [];
        if (count($op) % 2 === 0) {
            for ($k = 0; $k + 1 < count($op); $k += 2) {
                $opStops[] = [(float) $op[$k], (float) $op[$k + 1]];
            }
        }
        for ($k = 0; $k < $count; $k++) {
            if (!isset($raw[$k * 4 + 3])) {
                break;
            }
            $pos = (float) $raw[$k * 4];
            $alpha = 1.0;
            if ($opStops) {
                // rlottie getOpacityAtPosition: first bracketing pair, clamped progress, 0 past the end
                $alpha = 0.0;
                for ($j = 1, $n = count($opStops); $j < $n; $j++) {
                    if ($opStops[$j][0] >= $pos) {
                        $prev = $opStops[$j - 1];
                        $span = $opStops[$j][0] - $prev[0];
                        $p = $span > 1e-9 ? ($pos - $prev[0]) / $span : 1.0;
                        $p = $p < 0 ? 0.0 : ($p > 1 ? 1.0 : $p);
                        $alpha = $prev[1] + $p * ($opStops[$j][1] - $prev[1]);
                        break;
                    }
                }
            }
            $stops[] = [$pos, (float) $raw[$k * 4 + 1], (float) $raw[$k * 4 + 2], (float) $raw[$k * 4 + 3], max(0.0, min(1.0, $alpha))];
        }
        usort($stops, static fn ($a, $b) => $a[0] <=> $b[0]);
        return $stops;
    }

    /**
     * Draws a raster image item ({"ty":"emx_img","ref":..,"rect":[x0,y0,x1,y1]})
     * through the full affine matrix with sub-pixel accuracy: every covered
     * pixel centre is mapped back into the image and sampled bilinearly
     * (premultiplied), so moving / rotating / skewed slots stay smooth and
     * never jitter by whole pixels.
     */
    private function drawImage(array $it, Matrix $m, float $opacity, \GdImage $target): void
    {
        $ref = (string) ($it['ref'] ?? '');
        $img = $this->images[$ref] ?? null;
        $rect = $it['rect'] ?? null;
        if (!$img || !is_array($rect) || count($rect) !== 4 || $opacity <= 0.002) {
            return;
        }
        [$rx0, $ry0, $rx1, $ry1] = array_map('floatval', array_values($rect));
        $rw = $rx1 - $rx0;
        $rh = $ry1 - $ry0;
        $det = $m->a * $m->d - $m->b * $m->c;
        if ($rw <= 0 || $rh <= 0 || abs($det) < 1e-12) {
            return;
        }
        // destination bounds
        $xs = [];
        $ys = [];
        foreach ([[$rx0, $ry0], [$rx1, $ry0], [$rx1, $ry1], [$rx0, $ry1]] as [$x, $y]) {
            [$dx, $dy] = $m->apply($x, $y);
            $xs[] = $dx;
            $ys[] = $dy;
        }
        $bx0 = max(0, (int) floor(min($xs)));
        $by0 = max(0, (int) floor(min($ys)));
        $bx1 = min($this->outW - 1, (int) ceil(max($xs)));
        $by1 = min($this->outH - 1, (int) ceil(max($ys)));
        if ($bx1 < $bx0 || $by1 < $by0) {
            return;
        }
        // pick a pre-filtered source close to the on-screen size (avoids aliasing)
        $sx = sqrt($m->a * $m->a + $m->b * $m->b) * $rw;
        $sy = sqrt($m->c * $m->c + $m->d * $m->d) * $rh;
        $src = $this->imageLevel($ref, $img, (int) ceil(max($sx, $sy) * 2));
        $iw = $src['w'];
        $ih = $src['h'];
        $px = $src['px'];
        $inv = $m->invert();
        $ka = $iw / $rw;
        $kb = $ih / $rh;
        imagealphablending($target, true);
        for ($y = $by0; $y <= $by1; $y++) {
            $cy = $y + 0.5;
            for ($x = $bx0; $x <= $bx1; $x++) {
                $cx = $x + 0.5;
                $lx = $inv->a * $cx + $inv->c * $cy + $inv->e;
                $ly = $inv->b * $cx + $inv->d * $cy + $inv->f;
                $u = ($lx - $rx0) * $ka - 0.5;
                $v = ($ly - $ry0) * $kb - 0.5;
                if ($u < -1 || $v < -1 || $u > $iw || $v > $ih) {
                    continue;
                }
                $x0 = (int) floor($u);
                $y0 = (int) floor($v);
                $fx = $u - $x0;
                $fy = $v - $y0;
                $acc = [0.0, 0.0, 0.0, 0.0];
                foreach ([[$x0, $y0, (1 - $fx) * (1 - $fy)], [$x0 + 1, $y0, $fx * (1 - $fy)], [$x0, $y0 + 1, (1 - $fx) * $fy], [$x0 + 1, $y0 + 1, $fx * $fy]] as [$sxp, $syp, $w]) {
                    if ($w <= 0 || $sxp < 0 || $syp < 0 || $sxp >= $iw || $syp >= $ih) {
                        continue;
                    }
                    $p = $px[$syp * $iw + $sxp];
                    $acc[0] += $p[0] * $w;
                    $acc[1] += $p[1] * $w;
                    $acc[2] += $p[2] * $w;
                    $acc[3] += $p[3] * $w;
                }
                $a = $acc[3] * $opacity;
                if ($a <= 0.002) {
                    continue;
                }
                $r = (int) min(255, round($acc[0] / $acc[3]));
                $g = (int) min(255, round($acc[1] / $acc[3]));
                $b = (int) min(255, round($acc[2] / $acc[3]));
                $alpha = 127 - (int) round(min(1.0, $a) * 127);
                imagesetpixel($target, $x, $y, ($alpha << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }
    }

    /**
     * Premultiplied pixel cache of an image downscaled to about $side pixels.
     * @return array{w:int,h:int,px:list<array{0:float,1:float,2:float,3:float}>}
     */
    private function imageLevel(string $ref, \GdImage $img, int $side): array
    {
        $w0 = imagesx($img);
        $h0 = imagesy($img);
        $scale = min(1.0, max(8, $side) / max($w0, $h0));
        // quantise the level so animation frames share the cache
        $scale = $scale >= 1.0 ? 1.0 : 2 ** (ceil(log($scale, 2) * 4) / 4);
        $key = $ref . '@' . $scale;
        if (isset($this->imageCache[$key])) {
            return $this->imageCache[$key];
        }
        $w = max(1, (int) round($w0 * $scale));
        $h = max(1, (int) round($h0 * $scale));
        if ($w !== $w0 || $h !== $h0) {
            $lvl = ImageProcessor::canvas($w, $h);
            imagecopyresampled($lvl, $img, 0, 0, 0, 0, $w, $h, $w0, $h0);
        } else {
            $lvl = $img;
        }
        $px = [];
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($lvl, $x, $y);
                $a = (127 - (($c >> 24) & 0x7F)) / 127;
                $px[] = [(($c >> 16) & 0xFF) * $a, (($c >> 8) & 0xFF) * $a, ($c & 0xFF) * $a, $a];
            }
        }
        if (count($this->imageCache) > 16) {
            $this->imageCache = [];
        }
        return $this->imageCache[$key] = ['w' => $w, 'h' => $h, 'px' => $px];
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
                    // motion along a bezier path: eased progress = share of the arc length
                    $e = self::ease($a, $p, 0);
                    $c = [
                        [(float) $start[0], (float) $start[1]],
                        [(float) $start[0] + (float) ($a['to'][0] ?? 0), (float) $start[1] + (float) ($a['to'][1] ?? 0)],
                        [(float) $end[0] + (float) ($a['ti'][0] ?? 0), (float) $end[1] + (float) ($a['ti'][1] ?? 0)],
                        [(float) $end[0], (float) $end[1]],
                    ];
                    $u = self::bezierParamAtLength($c, $e);
                    $mt = 1 - $u;
                    for ($d = 0; $d < 2; $d++) {
                        $out[$d] = $mt * $mt * $mt * $c[0][$d] + 3 * $mt * $mt * $u * $c[1][$d] + 3 * $mt * $u * $u * $c[2][$d] + $u * $u * $u * $c[3][$d];
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

    /** @var array<string,array{0:list<float>,1:float}> arc length tables */
    private static array $arcCache = [];

    /**
     * Bezier parameter at a given fraction of the curve length.
     * @param array{0:array{0:float,1:float},1:array{0:float,1:float},2:array{0:float,1:float},3:array{0:float,1:float}} $c
     */
    private static function bezierParamAtLength(array $c, float $fraction): float
    {
        if ($fraction <= 0) {
            return 0.0;
        }
        if ($fraction >= 1) {
            return 1.0;
        }
        $key = implode(',', array_map(static fn ($p) => round($p[0], 3) . ':' . round($p[1], 3), $c));
        if (!isset(self::$arcCache[$key])) {
            $n = 64;
            $lens = [0.0];
            $px = $c[0][0];
            $py = $c[0][1];
            $total = 0.0;
            for ($k = 1; $k <= $n; $k++) {
                $u = $k / $n;
                $mt = 1 - $u;
                $x = $mt * $mt * $mt * $c[0][0] + 3 * $mt * $mt * $u * $c[1][0] + 3 * $mt * $u * $u * $c[2][0] + $u * $u * $u * $c[3][0];
                $y = $mt * $mt * $mt * $c[0][1] + 3 * $mt * $mt * $u * $c[1][1] + 3 * $mt * $u * $u * $c[2][1] + $u * $u * $u * $c[3][1];
                $total += sqrt(($x - $px) ** 2 + ($y - $py) ** 2);
                $lens[] = $total;
                $px = $x;
                $py = $y;
            }
            if (count(self::$arcCache) > 2000) {
                self::$arcCache = [];
            }
            self::$arcCache[$key] = [$lens, $total];
        }
        [$lens, $total] = self::$arcCache[$key];
        if ($total <= 1e-9) {
            return $fraction;
        }
        $target = $fraction * $total;
        $n = count($lens) - 1;
        $lo = 0;
        $hi = $n;
        while ($hi - $lo > 1) {
            $mid = ($lo + $hi) >> 1;
            if ($lens[$mid] < $target) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }
        $seg = $lens[$hi] - $lens[$lo];
        $f = $seg > 1e-12 ? ($target - $lens[$lo]) / $seg : 0.0;
        return ($lo + $f) / $n;
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

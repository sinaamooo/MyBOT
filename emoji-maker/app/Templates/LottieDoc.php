<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

/**
 * Helpers for addressing and editing decoded Lottie documents.
 *
 * Paths are slash separated keys/indices from the document root, e.g.
 *   layers/3                       a root layer
 *   layers/3/shapes/2/it/0         an item inside a shape layer group
 *   assets/1/layers/0/shapes/4     an item of a layer inside a precomp asset
 */
final class LottieDoc
{
    public const GEOMETRY = ['sh', 'rc', 'el', 'sr'];
    public const STYLES = ['fl', 'st', 'gf', 'gs'];

    /** Loads a .tgs (gzip) or .json Lottie file. */
    public static function load(string $file, int $maxJsonBytes = 8 * 1024 * 1024): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new TemplateException('فایل قالب پیدا نشد.');
        }
        $raw = (string) file_get_contents($file);
        return self::decode($raw, $maxJsonBytes);
    }

    public static function decode(string $raw, int $maxJsonBytes = 8 * 1024 * 1024): array
    {
        if (str_starts_with($raw, "\x1f\x8b")) {
            $json = @gzdecode($raw, $maxJsonBytes);
            if ($json === false) {
                throw new TemplateException('فایل TGS خراب است یا بیش از حد بزرگ است.');
            }
        } else {
            $json = $raw;
        }
        if (strlen($json) > $maxJsonBytes) {
            throw new TemplateException('فایل Lottie بیش از حد بزرگ است.');
        }
        $doc = json_decode($json, true, 512);
        if (!is_array($doc) || !isset($doc['layers']) || !is_array($doc['layers'])) {
            throw new TemplateException('محتوای فایل، انیمیشن Lottie معتبر نیست.');
        }
        return $doc;
    }

    /** Compact JSON + gzip, the TGS container format. */
    public static function encodeTgs(array $doc): string
    {
        $json = json_encode($doc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if ($json === false) {
            throw new TemplateException('ساخت JSON انیمیشن ناموفق بود.');
        }
        $json = self::stripZeroFractions($json);
        return (string) gzencode($json, 9);
    }

    /** "1.0" -> "1" outside strings (JSON numbers), saves bytes without changing values. */
    private static function stripZeroFractions(string $json): string
    {
        $out = '';
        $len = strlen($json);
        $inStr = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];
            if ($inStr) {
                $out .= $ch;
                if ($ch === '\\') {
                    $out .= $json[++$i] ?? '';
                } elseif ($ch === '"') {
                    $inStr = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inStr = true;
                $out .= $ch;
                continue;
            }
            if ($ch === '.' && isset($json[$i + 1]) && $json[$i + 1] === '0') {
                $j = $i + 1;
                while ($j < $len && $json[$j] === '0') {
                    $j++;
                }
                $next = $json[$j] ?? '';
                if (!ctype_digit($next) && $next !== 'e' && $next !== 'E') {
                    $i = $j - 1;
                    continue;
                }
            }
            $out .= $ch;
        }
        return $out;
    }

    /** @return list<string|int> */
    public static function split(string $path): array
    {
        $path = trim($path, '/');
        if ($path === '') {
            return [];
        }
        $out = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '' || !preg_match('/^[A-Za-z0-9_]+$/', $seg)) {
                throw new TemplateException('مسیر عنصر قالب نامعتبر است: ' . $path);
            }
            $out[] = ctype_digit($seg) ? (int) $seg : $seg;
        }
        return $out;
    }

    public static function has(array $doc, string $path): bool
    {
        $node = $doc;
        foreach (self::split($path) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                return false;
            }
            $node = $node[$seg];
        }
        return true;
    }

    public static function get(array $doc, string $path): mixed
    {
        $node = $doc;
        foreach (self::split($path) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                throw new TemplateException('عنصر قالب پیدا نشد: ' . $path);
            }
            $node = $node[$seg];
        }
        return $node;
    }

    public static function set(array &$doc, string $path, mixed $value): void
    {
        $segs = self::split($path);
        if (!$segs) {
            throw new TemplateException('مسیر خالی است.');
        }
        $node = &$doc;
        $last = array_pop($segs);
        foreach ($segs as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) {
                throw new TemplateException('عنصر قالب پیدا نشد: ' . $path);
            }
            $node = &$node[$seg];
        }
        if (!is_array($node)) {
            throw new TemplateException('عنصر قالب پیدا نشد: ' . $path);
        }
        $node[$last] = $value;
        unset($node);
    }

    /** Layer path that contains $path ("layers/3" or "assets/0/layers/2"), or null. */
    public static function layerPath(string $path): ?string
    {
        if (!preg_match('~^((?:assets/\d+/)?layers/\d+)(?:/|$)~', $path, $m)) {
            return null;
        }
        return $m[1];
    }

    /** "layers" or "assets/N/layers" for a layer path. */
    public static function layerListPath(string $layerPath): string
    {
        return (string) preg_replace('~/\d+$~', '', $layerPath);
    }

    public static function layerIndex(string $layerPath): int
    {
        return (int) substr((string) strrchr($layerPath, '/'), 1);
    }

    /** Path of the item list that holds $itemPath ("…/shapes" or "…/it"). */
    public static function parentListPath(string $itemPath): string
    {
        return (string) preg_replace('~/\d+$~', '', $itemPath);
    }

    /** Path of the container (layer or group) that holds the item at $itemPath. */
    public static function containerPath(string $itemPath): string
    {
        return (string) preg_replace('~/(shapes|it)/\d+$~', '', $itemPath);
    }

    /** Key of the item list inside a container: "shapes" for layers, "it" for groups. */
    public static function itemsKey(array $container): string
    {
        return isset($container['ty']) && is_int($container['ty']) ? 'shapes' : 'it';
    }

    /** True when $ancestor is $path or one of its ancestors. */
    public static function isWithin(string $path, string $ancestor): bool
    {
        return $path === $ancestor || str_starts_with($path, rtrim($ancestor, '/') . '/');
    }

    /**
     * Visits every layer: fn(array $layer, string $path, string $listPath, int $index, ?string $assetId).
     */
    public static function walkLayers(array $doc, callable $fn): void
    {
        foreach ((array) ($doc['layers'] ?? []) as $i => $l) {
            if (is_array($l)) {
                $fn($l, 'layers/' . $i, 'layers', (int) $i, null);
            }
        }
        foreach ((array) ($doc['assets'] ?? []) as $ai => $a) {
            if (!is_array($a) || !isset($a['layers']) || !is_array($a['layers'])) {
                continue;
            }
            foreach ($a['layers'] as $i => $l) {
                if (is_array($l)) {
                    $fn($l, "assets/$ai/layers/$i", "assets/$ai/layers", (int) $i, (string) ($a['id'] ?? ''));
                }
            }
        }
    }

    /**
     * Visits every shape item of every shape layer, depth first:
     * fn(array $item, string $path, string $layerPath, list<string> $groupPaths).
     */
    public static function walkShapes(array $doc, callable $fn): void
    {
        self::walkLayers($doc, function (array $layer, string $lp) use ($fn): void {
            if ((int) ($layer['ty'] ?? -1) !== 4) {
                return;
            }
            self::walkItems((array) ($layer['shapes'] ?? []), $lp . '/shapes', $lp, [], $fn);
        });
    }

    /** @param list<string> $groups */
    private static function walkItems(array $items, string $base, string $layerPath, array $groups, callable $fn): void
    {
        foreach ($items as $i => $it) {
            if (!is_array($it)) {
                continue;
            }
            $p = $base . '/' . $i;
            $fn($it, $p, $layerPath, $groups);
            if (($it['ty'] ?? '') === 'gr') {
                self::walkItems((array) ($it['it'] ?? []), $p . '/it', $layerPath, [...$groups, $p], $fn);
            }
        }
    }

    /** Index of the layer (in its list) whose "ind" equals $ind. */
    public static function findLayerByInd(array $layers, int $ind): ?int
    {
        foreach ($layers as $i => $l) {
            if (is_array($l) && isset($l['ind']) && (int) $l['ind'] === $ind) {
                return (int) $i;
            }
        }
        return null;
    }

    /** Whether any layer of the list uses the layer at $index as its parent. */
    public static function isParent(array $layers, int $index): bool
    {
        $ind = $layers[$index]['ind'] ?? null;
        if ($ind === null) {
            return false;
        }
        foreach ($layers as $l) {
            if (is_array($l) && isset($l['parent']) && (int) $l['parent'] === (int) $ind) {
                return true;
            }
        }
        return false;
    }

    /**
     * Matte role of a layer in its list (rlottie semantics: a layer with "tt"
     * uses the layer right above it, which has "td", as its matte).
     * @return array{source:bool,target:bool,matteFor:?int,matteType:int}
     */
    public static function matteRole(array $layers, int $index): array
    {
        $l = $layers[$index] ?? [];
        $next = $layers[$index + 1] ?? null;
        $isSource = !empty($l['td']) && is_array($next) && !empty($next['tt']);
        return [
            'source' => $isSource,
            'target' => !empty($l['tt']),
            'matteFor' => $isSource ? $index + 1 : null,
            'matteType' => $isSource ? (int) $next['tt'] : (int) ($l['tt'] ?? 0),
        ];
    }

    /** Removes metadata keys that renderers ignore (names, indices, match names) to save bytes. */
    public static function stripMetadata(array $node, int $depth = 0): array
    {
        if ($depth > 64) {
            return $node;
        }
        $out = [];
        foreach ($node as $k => $v) {
            if (is_string($k) && in_array($k, ['nm', 'mn', 'ix', 'cix', 'np', 'cl', 'ln'], true) && !is_array($v)) {
                continue;
            }
            if ($k === 'ddd' && (int) $v === 0 && $depth > 0) {
                continue;
            }
            $out[$k] = is_array($v) ? self::stripMetadata($v, $depth + 1) : $v;
        }
        return $out;
    }

    /** Rounds every float in the document to $decimals (size reduction, last resort). */
    public static function roundFloats(array $node, int $decimals = 3, int $depth = 0): array
    {
        if ($depth > 64) {
            return $node;
        }
        foreach ($node as $k => $v) {
            if (is_float($v)) {
                $r = round($v, $decimals);
                $node[$k] = $r == 0.0 ? 0 : ($r == floor($r) && abs($r) < 1e15 ? (int) $r : $r);
            } elseif (is_array($v)) {
                $node[$k] = self::roundFloats($v, $decimals, $depth + 1);
            }
        }
        return $node;
    }
}

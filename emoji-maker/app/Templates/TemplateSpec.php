<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

/**
 * Description of a ready made animated emoji template built from an original
 * TGS animation: which elements (the original logo / text) are removed and
 * where the user's own logo or text is inserted ("slots").
 *
 * A slot is inserted as a new group into a host container (a shape layer or a
 * group) at a given stacking index, so it inherits every transform, parent,
 * mask and matte of that container: the user's content moves exactly like the
 * original logo did. The box is the area of the removed artwork expressed in
 * the host's own (content) coordinates.
 *
 * JSON layout (resources/templates/<pack>/<slug>.json):
 * {
 *   "slug": "tofan-01", "pack": "tofan", "name": "...", "source": "tofan-01.tgs",
 *   "status": "auto|reviewed|disabled", "category": "...", "sort": 100,
 *   "remove": ["layers/3/shapes/1", ...],
 *   "slots": [{
 *       "role": "main|caption", "host": "layers/3", "index": 1,
 *       "box": [x0, y0, x1, y1], "accepts": ["logo","text"],
 *       "color": "#RRGGBB", "pad": 0.92,
 *       "also": [{"host": "...", "index": 0, "box": [...]}]
 *   }],
 *   "recolor": {"enabled": true, "primary": "#RRGGBB", "tolerance": 35,
 *               "protect": ["layers/5", ...]},
 *   "meta": {...}
 * }
 *
 * Recolouring: users may move the template's main brand colour (or all
 * colours) to another hue. Colour sensitive artwork (faces, characters,
 * gold, fire, third party logos) is listed in recolor.protect and always
 * keeps its original colours; neutrals and skin tones are protected
 * automatically.
 */
final class TemplateSpec
{
    public const ROLES = ['main', 'caption'];
    public const STATUSES = ['auto', 'reviewed', 'disabled'];
    public const ACCEPTS = ['logo', 'text'];

    public string $slug = '';
    public string $pack = '';
    public string $name = '';
    public string $source = '';
    public string $status = 'auto';
    public string $category = '';
    public int $sort = 100;
    /** @var list<string> */
    public array $remove = [];
    /** @var list<array<string,mixed>> */
    public array $slots = [];
    /** @var array{enabled:bool,primary:?string,tolerance:float,protect:list<string>} */
    public array $recolor = ['enabled' => true, 'primary' => null, 'tolerance' => 35.0, 'protect' => []];
    /** @var array<string,mixed> */
    public array $meta = [];
    /** directory of the spec file (set by the library) */
    public string $dir = '';

    public static function fromArray(array $a, string $dir = ''): self
    {
        $s = new self();
        $s->slug = self::slug((string) ($a['slug'] ?? ''));
        $s->pack = self::slug((string) ($a['pack'] ?? ''));
        $s->name = mb_substr(trim((string) ($a['name'] ?? $s->slug)), 0, 96);
        $s->source = basename((string) ($a['source'] ?? ($s->slug . '.tgs')));
        $s->status = in_array($a['status'] ?? '', self::STATUSES, true) ? (string) $a['status'] : 'auto';
        $s->category = mb_substr((string) ($a['category'] ?? ''), 0, 32);
        $s->sort = (int) ($a['sort'] ?? 100);
        foreach ((array) ($a['remove'] ?? []) as $p) {
            if (is_string($p) && $p !== '') {
                LottieDoc::split($p); // validates syntax
                $s->remove[] = trim($p, '/');
            }
        }
        $s->remove = array_values(array_unique($s->remove));
        foreach ((array) ($a['slots'] ?? []) as $slot) {
            if (is_array($slot)) {
                $s->slots[] = self::normalizeSlot($slot);
            }
        }
        $rc = is_array($a['recolor'] ?? null) ? $a['recolor'] : [];
        $protect = [];
        foreach ((array) ($rc['protect'] ?? []) as $p) {
            if (is_string($p) && $p !== '') {
                LottieDoc::split($p);
                $protect[] = trim($p, '/');
            }
        }
        $s->recolor = [
            'enabled' => (bool) ($rc['enabled'] ?? true),
            'primary' => self::hex($rc['primary'] ?? null),
            'tolerance' => max(5.0, min(180.0, (float) ($rc['tolerance'] ?? 35.0))),
            'protect' => array_values(array_unique($protect)),
        ];
        $s->meta = is_array($a['meta'] ?? null) ? $a['meta'] : [];
        $s->dir = $dir;
        return $s;
    }

    /** @return array<string,mixed> */
    private static function normalizeSlot(array $slot): array
    {
        $role = in_array($slot['role'] ?? '', self::ROLES, true) ? (string) $slot['role'] : 'main';
        $accepts = array_values(array_intersect(self::ACCEPTS, (array) ($slot['accepts'] ?? ($role === 'caption' ? ['text'] : ['logo', 'text']))));
        $out = [
            'role' => $role,
            'host' => trim((string) ($slot['host'] ?? ''), '/'),
            'index' => max(0, (int) ($slot['index'] ?? 0)),
            'box' => self::box($slot['box'] ?? null),
            'accepts' => $accepts ?: ['logo', 'text'],
            'color' => self::hex($slot['color'] ?? null),
            'pad' => max(0.3, min(1.2, (float) ($slot['pad'] ?? 0.92))),
            'also' => [],
        ];
        LottieDoc::split($out['host']);
        foreach ((array) ($slot['also'] ?? []) as $copy) {
            if (!is_array($copy)) {
                continue;
            }
            $c = [
                'host' => trim((string) ($copy['host'] ?? ''), '/'),
                'index' => max(0, (int) ($copy['index'] ?? 0)),
                'box' => self::box($copy['box'] ?? null),
            ];
            LottieDoc::split($c['host']);
            if ($c['host'] !== '') {
                $out['also'][] = $c;
            }
        }
        return $out;
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private static function box(mixed $b): array
    {
        if (!is_array($b) || count($b) !== 4) {
            return [0.0, 0.0, 0.0, 0.0];
        }
        $b = array_map('floatval', array_values($b));
        return [min($b[0], $b[2]), min($b[1], $b[3]), max($b[0], $b[2]), max($b[1], $b[3])];
    }

    private static function hex(mixed $c): ?string
    {
        return is_string($c) && preg_match('/^#[0-9A-Fa-f]{6}$/', $c) ? strtoupper($c) : null;
    }

    public static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = (string) preg_replace('/[^a-z0-9_-]+/', '-', $s);
        return trim(substr($s, 0, 64), '-');
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'pack' => $this->pack,
            'name' => $this->name,
            'source' => $this->source,
            'status' => $this->status,
            'category' => $this->category,
            'sort' => $this->sort,
            'remove' => $this->remove,
            'slots' => $this->slots,
            'recolor' => $this->recolor,
            'meta' => $this->meta,
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    }

    public function sourcePath(): string
    {
        return rtrim($this->dir, '/') . '/' . $this->source;
    }

    /** @return array<string,mixed>|null */
    public function slot(string $role): ?array
    {
        foreach ($this->slots as $s) {
            if ($s['role'] === $role) {
                return $s;
            }
        }
        return null;
    }

    public function hasSlot(string $role): bool
    {
        return $this->slot($role) !== null;
    }

    /** @return list<string> roles present */
    public function roles(): array
    {
        return array_values(array_unique(array_map(static fn ($s) => (string) $s['role'], $this->slots)));
    }

    /**
     * Structural checks against the source document.
     * @return list<string> problems (empty = valid)
     */
    public function check(array $doc): array
    {
        $errors = [];
        if ($this->slug === '' || $this->pack === '') {
            $errors[] = 'slug/pack is empty';
        }
        foreach ($this->remove as $p) {
            if (!LottieDoc::has($doc, $p)) {
                $errors[] = "remove path not found: $p";
                continue;
            }
            $node = LottieDoc::get($doc, $p);
            if (!is_array($node)) {
                $errors[] = "remove path is not an element: $p";
            }
        }
        if (!$this->slots) {
            $errors[] = 'no slot defined';
        }
        foreach ($this->recolor['protect'] as $p) {
            if (!LottieDoc::has($doc, $p)) {
                $errors[] = "recolor.protect path not found: $p";
            }
        }
        foreach ($this->slots as $k => $s) {
            foreach ([['host' => $s['host'], 'box' => $s['box']], ...$s['also']] as $target) {
                $err = $this->checkHost($doc, (string) $target['host']);
                if ($err !== null) {
                    $errors[] = "slot $k: $err";
                }
                [$x0, $y0, $x1, $y1] = $target['box'];
                if ($x1 - $x0 < 1 || $y1 - $y0 < 1) {
                    $errors[] = "slot $k: empty box";
                }
            }
            foreach ($this->remove as $p) {
                // removing the host itself clears its content but keeps its transform
                if (LottieDoc::isWithin($s['host'], $p) && $s['host'] !== $p) {
                    $errors[] = "slot $k: host {$s['host']} is inside removed element $p";
                }
            }
        }
        return $errors;
    }

    private function checkHost(array $doc, string $host): ?string
    {
        if ($host === '' || !LottieDoc::has($doc, $host)) {
            return "host not found: $host";
        }
        $node = LottieDoc::get($doc, $host);
        if (!is_array($node)) {
            return "host is not an element: $host";
        }
        if (LottieDoc::layerPath($host) === $host) {
            return (int) ($node['ty'] ?? -1) === 4 ? null : "host layer is not a shape layer: $host";
        }
        return ($node['ty'] ?? '') === 'gr' ? null : "host is not a group: $host";
    }
}

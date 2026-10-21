<?php

declare(strict_types=1);

namespace EmojiMaker\Templates;

use EmojiMaker\Database\Database;

/**
 * All animated emoji templates: the bundled default packs
 * (resources/templates/<pack>/) and packs imported from the admin panel
 * (storage/templates/<pack>/). A spec in a later directory overrides the spec
 * with the same slug from an earlier one (admin edits of bundled templates
 * are stored in storage). Enable / order / name overrides from the
 * lottie_templates table are applied when a database is available.
 *
 * Directory layout of a pack:
 *   pack.json                 {"name": "...", "sort": 10}
 *   <slug>.json               TemplateSpec
 *   <slug>.tgs                original animation (never modified)
 *   previews/<slug>_thumb.png, previews/<slug>_preview.(mp4|gif)
 */
final class TemplateLibrary
{
    /** @var array<string,TemplateSpec>|null */
    private ?array $specs = null;
    /** @var array<string,array{name:string,sort:int,dir:string}> */
    private array $packs = [];
    /** @var array<string,array<string,mixed>> */
    private array $overrides = [];

    /** @param list<string> $dirs base directories, lowest priority first */
    public function __construct(private array $dirs, private ?Database $db = null)
    {
    }

    /** @return array<string,TemplateSpec> slug => spec (every status) */
    public function all(): array
    {
        if ($this->specs !== null) {
            return $this->specs;
        }
        $specs = [];
        foreach ($this->dirs as $base) {
            foreach (glob(rtrim($base, '/') . '/*', GLOB_ONLYDIR) ?: [] as $packDir) {
                $pack = basename($packDir);
                if (TemplateSpec::slug($pack) !== $pack) {
                    continue;
                }
                $meta = is_file($packDir . '/pack.json') ? json_decode((string) file_get_contents($packDir . '/pack.json'), true) : null;
                $this->packs[$pack] = [
                    'name' => is_array($meta) && isset($meta['name']) ? (string) $meta['name'] : $pack,
                    'sort' => is_array($meta) ? (int) ($meta['sort'] ?? 100) : 100,
                    'dir' => $packDir,
                ];
                foreach (glob($packDir . '/*.json') ?: [] as $file) {
                    if (basename($file) === 'pack.json') {
                        continue;
                    }
                    $data = json_decode((string) file_get_contents($file), true);
                    if (!is_array($data)) {
                        continue;
                    }
                    try {
                        $spec = TemplateSpec::fromArray($data, $packDir);
                    } catch (TemplateException) {
                        continue;
                    }
                    if ($spec->slug === '') {
                        continue;
                    }
                    // an override without its own animation uses the earlier copy
                    if (!is_file($spec->sourcePath()) && isset($specs[$spec->slug])) {
                        $spec->dir = $specs[$spec->slug]->dir;
                    }
                    if (is_file($spec->sourcePath())) {
                        $specs[$spec->slug] = $spec;
                    }
                }
            }
        }
        $this->loadOverrides();
        foreach ($specs as $slug => $spec) {
            $o = $this->overrides[$slug] ?? null;
            if ($o !== null) {
                if (isset($o['name_fa']) && $o['name_fa'] !== '') {
                    $spec->name = (string) $o['name_fa'];
                }
                if (isset($o['sort_order'])) {
                    $spec->sort = (int) $o['sort_order'];
                }
            }
        }
        uasort($specs, fn (TemplateSpec $a, TemplateSpec $b) => [$this->packs[$a->pack]['sort'] ?? 100, $a->pack, $a->sort, $a->slug] <=> [$this->packs[$b->pack]['sort'] ?? 100, $b->pack, $b->sort, $b->slug]);
        return $this->specs = $specs;
    }

    private function loadOverrides(): void
    {
        if ($this->db === null) {
            return;
        }
        try {
            foreach ($this->db->fetchAll('SELECT slug, name_fa, is_enabled, sort_order FROM lottie_templates') as $r) {
                $this->overrides[(string) $r['slug']] = $r;
            }
        } catch (\Throwable) {
            $this->overrides = [];
        }
    }

    public function refresh(): void
    {
        $this->specs = null;
        $this->packs = [];
        $this->overrides = [];
    }

    public function get(string $slug): ?TemplateSpec
    {
        return $this->all()[TemplateSpec::slug($slug)] ?? null;
    }

    public function isEnabled(TemplateSpec $spec): bool
    {
        if ($spec->status === 'disabled' || !$spec->slots) {
            return false;
        }
        $o = $this->overrides[$spec->slug] ?? null;
        return $o === null || (int) ($o['is_enabled'] ?? 1) === 1;
    }

    /** @return list<TemplateSpec> usable templates, gallery order */
    public function available(?string $pack = null): array
    {
        $out = [];
        foreach ($this->all() as $spec) {
            if (($pack === null || $spec->pack === $pack) && $this->isEnabled($spec)) {
                $out[] = $spec;
            }
        }
        return $out;
    }

    /** @return array<string,array{name:string,sort:int,count:int}> */
    public function packs(bool $onlyAvailable = true): array
    {
        $this->all();
        $out = [];
        foreach ($this->packs as $slug => $p) {
            $count = count($onlyAvailable ? $this->available($slug) : array_filter($this->all(), static fn ($s) => $s->pack === $slug));
            if ($count > 0 || !$onlyAvailable) {
                $out[$slug] = ['name' => $p['name'], 'sort' => $p['sort'], 'count' => $count];
            }
        }
        uasort($out, static fn ($a, $b) => [$a['sort'], $a['name']] <=> [$b['sort'], $b['name']]);
        return $out;
    }

    public function packName(string $pack): string
    {
        $this->all();
        return $this->packs[$pack]['name'] ?? $pack;
    }

    /** Pre-rendered gallery preview of a template ($kind: thumb | preview). */
    public function previewPath(TemplateSpec $spec, string $kind = 'thumb'): ?string
    {
        foreach (array_reverse($this->dirs) as $base) {
            $dir = rtrim($base, '/') . '/' . $spec->pack . '/previews/';
            $candidates = $kind === 'thumb' ? [$spec->slug . '_thumb.png'] : [$spec->slug . '_preview.mp4', $spec->slug . '_preview.gif'];
            foreach ($candidates as $c) {
                if (is_file($dir . $c)) {
                    return $dir . $c;
                }
            }
        }
        return null;
    }

    /**
     * Saves a spec into the writable (last) directory, keeping the original
     * animation where it is.
     */
    public function save(TemplateSpec $spec): string
    {
        $base = rtrim((string) end($this->dirs), '/');
        $dir = $base . '/' . $spec->pack;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
            throw new TemplateException('پوشه‌ی قالب‌ها قابل نوشتن نیست.');
        }
        $file = $dir . '/' . $spec->slug . '.json';
        $tmp = $file . '.tmp';
        file_put_contents($tmp, $spec->toJson());
        rename($tmp, $file);
        $this->refresh();
        return $file;
    }

    /** Keeps the lottie_templates table in sync with the files (new slugs only). */
    public function syncDatabase(): int
    {
        if ($this->db === null) {
            return 0;
        }
        $n = 0;
        foreach ($this->all() as $spec) {
            $n += $this->db->query(
                'INSERT IGNORE INTO lottie_templates (slug, pack, name_fa, slots, status, is_enabled, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$spec->slug, $spec->pack, $spec->name, implode(',', $spec->roles()), $spec->status, $spec->status === 'disabled' ? 0 : 1, $spec->sort]
            )->rowCount();
        }
        $this->refresh();
        return $n;
    }
}

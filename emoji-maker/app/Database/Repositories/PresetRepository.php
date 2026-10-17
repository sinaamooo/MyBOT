<?php

declare(strict_types=1);

namespace EmojiMaker\Database\Repositories;

use EmojiMaker\Database\Database;

/** Animation presets (effects and combos) and design templates. */
final class PresetRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return list<array<string,mixed>> */
    public function enabled(?bool $premiumAllowed = true): array
    {
        $sql = 'SELECT * FROM animation_presets WHERE is_enabled = 1' . ($premiumAllowed ? '' : ' AND is_premium = 0') . ' ORDER BY sort_order, id';
        return $this->db->fetchAll($sql);
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->db->fetchAll('SELECT * FROM animation_presets ORDER BY sort_order, id');
    }

    /** @return array<string,mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetch('SELECT * FROM animation_presets WHERE slug = ?', [$slug]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM animation_presets WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public function save(?int $id, array $data): int
    {
        if ($id) {
            $this->db->update('animation_presets', $data, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('animation_presets', $data);
    }

    public function delete(int $id): void
    {
        $this->db->delete('animation_presets', ['id' => $id]);
    }

    /** Engine effect keys enabled by the admin. @return list<string> */
    public function enabledEffectKeys(): array
    {
        return array_values(array_filter(array_column($this->db->fetchAll("SELECT effect_type FROM animation_presets WHERE is_enabled = 1 AND kind = 'effect'"), 'effect_type')));
    }

    /** Cost units per output for a list of effect keys (max of the effects). */
    public function costFor(array $effectKeys): int
    {
        if (!$effectKeys) {
            return 1;
        }
        $in = implode(',', array_fill(0, count($effectKeys), '?'));
        $v = $this->db->value("SELECT MAX(cost_units) FROM animation_presets WHERE kind = 'effect' AND effect_type IN ($in)", array_values($effectKeys));
        return max(1, (int) $v);
    }

    // ---- templates ----

    /** @return list<array<string,mixed>> */
    public function templates(bool $enabledOnly = true): array
    {
        return $this->db->fetchAll('SELECT * FROM design_templates' . ($enabledOnly ? ' WHERE is_enabled = 1' : '') . ' ORDER BY sort_order, id');
    }

    /** @return array<string,mixed>|null */
    public function template(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM design_templates WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public function saveTemplate(?int $id, array $data): int
    {
        if ($id) {
            $this->db->update('design_templates', $data, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('design_templates', $data);
    }

    public function deleteTemplate(int $id): void
    {
        $this->db->delete('design_templates', ['id' => $id]);
    }

    // ---- fonts ----

    /** @return list<array<string,mixed>> */
    public function fonts(bool $enabledOnly = true): array
    {
        return $this->db->fetchAll('SELECT * FROM fonts' . ($enabledOnly ? ' WHERE is_enabled = 1' : '') . ' ORDER BY sort_order, id');
    }

    /** @param array<string,mixed> $data */
    public function saveFont(?int $id, array $data): int
    {
        if ($id) {
            $this->db->update('fonts', $data, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('fonts', $data);
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Database\Repositories;

use EmojiMaker\Database\Database;

final class AssetRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        return $this->db->insert('project_assets', $data);
    }

    /** @return array<string,mixed>|null */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM project_assets WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM project_assets WHERE id = ?', [$id]);
    }

    /** @return list<string> paths still referenced (cleanup safety) */
    public function allPaths(): array
    {
        return array_column($this->db->fetchAll('SELECT path FROM project_assets'), 'path');
    }
}

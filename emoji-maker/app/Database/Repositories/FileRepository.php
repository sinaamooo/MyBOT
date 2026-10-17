<?php

declare(strict_types=1);

namespace EmojiMaker\Database\Repositories;

use EmojiMaker\Database\Database;

final class FileRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $existing = $this->db->fetch(
            'SELECT id FROM generated_files WHERE job_id = ? AND item_index = ? AND kind = ? AND format = ?',
            [$data['job_id'], $data['item_index'], $data['kind'], $data['format']]
        );
        if ($existing) {
            $this->db->update('generated_files', $data + ['deleted_at' => null], ['id' => (int) $existing['id']]);
            return (int) $existing['id'];
        }
        return $this->db->insert('generated_files', $data);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM generated_files WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function forJob(int $jobId, ?string $kind = null, bool $selectedOnly = false): array
    {
        $sql = 'SELECT * FROM generated_files WHERE job_id = ? AND deleted_at IS NULL';
        $params = [$jobId];
        if ($kind !== null) {
            $sql .= ' AND kind = ?';
            $params[] = $kind;
        }
        if ($selectedOnly) {
            $sql .= ' AND is_selected = 1';
        }
        return $this->db->fetchAll($sql . ' ORDER BY item_index ASC, id ASC', $params);
    }

    public function maxItemIndex(int $jobId): int
    {
        return (int) ($this->db->value("SELECT MAX(item_index) FROM generated_files WHERE job_id = ? AND kind = 'main' AND deleted_at IS NULL", [$jobId]) ?? -1);
    }

    /** Toggles selection of all tiles of one item (1-based number shown to the user). */
    public function toggleSelected(int $jobId, int $itemIndex): bool
    {
        $row = $this->db->fetch("SELECT is_selected FROM generated_files WHERE job_id = ? AND item_index = ? AND kind = 'main' LIMIT 1", [$jobId, $itemIndex]);
        if (!$row) {
            return false;
        }
        $new = (int) $row['is_selected'] === 1 ? 0 : 1;
        $this->db->query("UPDATE generated_files SET is_selected = ? WHERE job_id = ? AND item_index = ? AND kind = 'main'", [$new, $jobId, $itemIndex]);
        return $new === 1;
    }

    public function selectAll(int $jobId, bool $selected): void
    {
        $this->db->query("UPDATE generated_files SET is_selected = ? WHERE job_id = ? AND kind = 'main'", [$selected ? 1 : 0, $jobId]);
    }

    public function setTelegramIds(int $id, ?string $fileId, ?string $customEmojiId, ?int $setId): void
    {
        $this->db->query('UPDATE generated_files SET telegram_file_id = COALESCE(?, telegram_file_id), custom_emoji_id = COALESCE(?, custom_emoji_id), sticker_set_id = COALESCE(?, sticker_set_id) WHERE id = ?', [$fileId, $customEmojiId, $setId, $id]);
    }

    public function softDelete(int $id): void
    {
        $this->db->query('UPDATE generated_files SET deleted_at = NOW() WHERE id = ?', [$id]);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function adminList(int $page, int $perPage, array $filters): array
    {
        $where = ['f.deleted_at IS NULL'];
        $params = [];
        if (!empty($filters['kind'])) {
            $where[] = 'f.kind = :kind';
            $params['kind'] = (string) $filters['kind'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'f.validation_status = :vs';
            $params['vs'] = (string) $filters['status'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'f.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }
        if (!empty($filters['job_id'])) {
            $where[] = 'f.job_id = :jid';
            $params['jid'] = (int) $filters['job_id'];
        }
        $w = implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM generated_files f WHERE $w", $params);
        $params['lim'] = $perPage;
        $params['off'] = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll("SELECT f.*, u.telegram_id, u.username FROM generated_files f LEFT JOIN users u ON u.id = f.user_id WHERE $w ORDER BY f.id DESC LIMIT :lim OFFSET :off", $params);
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        $r = $this->db->fetch("SELECT COUNT(*) c, COALESCE(SUM(size_bytes),0) s, SUM(kind='main') m FROM generated_files WHERE deleted_at IS NULL");
        return ['files' => (int) ($r['c'] ?? 0), 'bytes' => (int) ($r['s'] ?? 0), 'main' => (int) ($r['m'] ?? 0)];
    }

    /** @return list<array<string,mixed>> expired files for cleanup */
    public function olderThan(int $days, int $limit = 500): array
    {
        return $this->db->fetchAll('SELECT id, path FROM generated_files WHERE deleted_at IS NULL AND created_at < (NOW() - INTERVAL ? DAY) LIMIT ?', [$days, $limit]);
    }

    /** @return list<string> */
    public function livePaths(): array
    {
        return array_column($this->db->fetchAll('SELECT path FROM generated_files WHERE deleted_at IS NULL'), 'path');
    }
}

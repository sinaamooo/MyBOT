<?php

declare(strict_types=1);

namespace EmojiMaker\Database\Repositories;

use EmojiMaker\Database\Database;

final class ProjectRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @param array<string,mixed> $config */
    public function create(int $userId, string $inputType, array $config, ?string $title = null): int
    {
        return $this->db->insert('projects', [
            'user_id' => $userId,
            'title' => $title !== null ? mb_substr($title, 0, 128) : null,
            'input_type' => in_array($inputType, ['image', 'text', 'combo', 'template'], true) ? $inputType : 'text',
            'status' => 'draft',
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM projects WHERE id = ? AND deleted_at IS NULL', [$id]);
    }

    /** Ownership-checked lookup. @return array<string,mixed>|null */
    public function findForUser(int $id, int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM projects WHERE id = ? AND user_id = ? AND deleted_at IS NULL', [$id, $userId]);
    }

    /** @return array<string,mixed> */
    public static function config(array $project): array
    {
        $c = json_decode((string) ($project['config'] ?? ''), true);
        return is_array($c) ? $c : [];
    }

    /** @param array<string,mixed> $config */
    public function saveConfig(int $id, array $config, ?string $inputType = null): void
    {
        $data = ['config' => json_encode($config, JSON_UNESCAPED_UNICODE)];
        if ($inputType !== null) {
            $data['input_type'] = $inputType;
        }
        $title = $config['text'] ?? null;
        if (is_string($title) && $title !== '') {
            $data['title'] = mb_substr(str_replace("\n", ' ', $title), 0, 128);
        }
        $this->db->update('projects', $data, ['id' => $id]);
    }

    public function setStatus(int $id, string $status, ?int $jobId = null): void
    {
        $data = ['status' => $status];
        if ($jobId !== null) {
            $data['last_job_id'] = $jobId;
        }
        $this->db->update('projects', $data, ['id' => $id]);
    }

    public function addOutputs(int $id, int $n): void
    {
        $this->db->query('UPDATE projects SET outputs_count = outputs_count + ? WHERE id = ?', [$n, $id]);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function listForUser(int $userId, int $page, int $perPage = 8): array
    {
        $total = (int) $this->db->value('SELECT COUNT(*) FROM projects WHERE user_id = ? AND deleted_at IS NULL', [$userId]);
        $rows = $this->db->fetchAll(
            'SELECT * FROM projects WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?',
            [$userId, $perPage, max(0, ($page - 1) * $perPage)]
        );
        return ['rows' => $rows, 'total' => $total];
    }

    public function softDelete(int $id, int $userId): bool
    {
        return $this->db->query('UPDATE projects SET deleted_at = NOW(), status = \'archived\' WHERE id = ? AND user_id = ?', [$id, $userId])->rowCount() > 0;
    }

    public function duplicate(int $id, int $userId): ?int
    {
        $p = $this->findForUser($id, $userId);
        if ($p === null) {
            return null;
        }
        return $this->create($userId, (string) $p['input_type'], self::config($p), $p['title'] ? $p['title'] . ' (کپی)' : null);
    }

    /**
     * Keeps a user below $keep projects by archiving the oldest drafts that
     * never produced an output (makes room for one new project).
     */
    public function pruneDrafts(int $userId, int $keep): int
    {
        $total = (int) $this->db->value('SELECT COUNT(*) FROM projects WHERE user_id = ? AND deleted_at IS NULL', [$userId]);
        $excess = $total - max(1, $keep) + 1;
        if ($excess <= 0) {
            return 0;
        }
        return $this->db->query(
            "UPDATE projects SET deleted_at = NOW(), status = 'archived'
             WHERE user_id = ? AND deleted_at IS NULL AND outputs_count = 0 AND status IN ('draft','previewed','failed')
             ORDER BY updated_at ASC, id ASC LIMIT ?",
            [$userId, $excess]
        )->rowCount();
    }

    public function countAll(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL');
    }
}

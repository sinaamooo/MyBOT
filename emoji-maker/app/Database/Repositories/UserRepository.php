<?php

declare(strict_types=1);

namespace EmojiMaker\Database\Repositories;

use EmojiMaker\Database\Database;

final class UserRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE id = ?', [$id]);
    }

    /** @return array<string,mixed>|null */
    public function findByTelegramId(int $telegramId): ?array
    {
        return $this->db->fetch('SELECT * FROM users WHERE telegram_id = ?', [$telegramId]);
    }

    /**
     * Creates or refreshes a user from a Telegram "from" object.
     * @param array<string,mixed> $from
     * @param list<int> $adminIds
     * @return array<string,mixed>
     */
    public function upsertFromTelegram(array $from, array $adminIds = []): array
    {
        $tgId = (int) ($from['id'] ?? 0);
        if ($tgId <= 0) {
            throw new \InvalidArgumentException('Invalid Telegram user');
        }
        $data = [
            'username' => isset($from['username']) ? mb_substr((string) $from['username'], 0, 64) : null,
            'first_name' => isset($from['first_name']) ? mb_substr((string) $from['first_name'], 0, 128) : null,
            'last_name' => isset($from['last_name']) ? mb_substr((string) $from['last_name'], 0, 128) : null,
            'language_code' => isset($from['language_code']) ? mb_substr((string) $from['language_code'], 0, 16) : null,
        ];
        $this->db->query(
            'INSERT INTO users (telegram_id, username, first_name, last_name, language_code, role, last_seen_at)
             VALUES (:tg, :u, :f, :l, :lang, :role, NOW())
             ON DUPLICATE KEY UPDATE username = VALUES(username), first_name = VALUES(first_name),
               last_name = VALUES(last_name), language_code = VALUES(language_code), last_seen_at = NOW(),
               role = IF(:isadmin = 1, \'admin\', role)',
            [
                'tg' => $tgId, 'u' => $data['username'], 'f' => $data['first_name'], 'l' => $data['last_name'],
                'lang' => $data['language_code'], 'role' => in_array($tgId, $adminIds, true) ? 'admin' : 'user',
                'isadmin' => in_array($tgId, $adminIds, true) ? 1 : 0,
            ]
        );
        return (array) $this->findByTelegramId($tgId);
    }

    /** @param array<string,mixed> $data */
    public function setState(int $id, string $state, array $data = [], ?int $projectId = null, bool $keepProject = true): void
    {
        $sql = 'UPDATE users SET state = ?, state_data = ?' . ($keepProject ? '' : ', current_project_id = ?') . ' WHERE id = ?';
        $params = [$state, $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null];
        if (!$keepProject) {
            $params[] = $projectId;
        }
        $params[] = $id;
        $this->db->query($sql, $params);
    }

    public function setCurrentProject(int $id, ?int $projectId): void
    {
        $this->db->query('UPDATE users SET current_project_id = ? WHERE id = ?', [$projectId, $id]);
    }

    /** @return array<string,mixed> */
    public static function stateData(array $user): array
    {
        $d = json_decode((string) ($user['state_data'] ?? ''), true);
        return is_array($d) ? $d : [];
    }

    /** @return array<string,mixed> */
    public static function permissions(array $user): array
    {
        $p = json_decode((string) ($user['permissions'] ?? ''), true);
        return is_array($p) ? $p : [];
    }

    public function setBlocked(int $id, bool $blocked, ?string $reason = null): void
    {
        $this->db->query('UPDATE users SET is_blocked = ?, block_reason = ? WHERE id = ?', [$blocked ? 1 : 0, $blocked ? $reason : null, $id]);
    }

    /** @param array<string,mixed> $fields role, daily_limit, max_batch, credits, permissions */
    public function updateAdminFields(int $id, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['role', 'daily_limit', 'max_batch', 'credits', 'permissions']));
        if (isset($allowed['permissions']) && is_array($allowed['permissions'])) {
            $allowed['permissions'] = json_encode($allowed['permissions']);
        }
        if ($allowed) {
            $this->db->update('users', $allowed, ['id' => $id]);
        }
    }

    public function addGenerated(int $id, int $count): void
    {
        $this->db->query('UPDATE users SET total_generated = total_generated + ? WHERE id = ?', [$count, $id]);
    }

    public function adjustCredits(int $id, int $delta): void
    {
        $this->db->query('UPDATE users SET credits = GREATEST(0, credits + ?) WHERE id = ?', [$delta, $id]);
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function search(string $q, int $page, int $perPage = 25, ?string $filter = null): array
    {
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            // native prepares do not allow re-using a named parameter
            $where[] = '(username LIKE :q1 OR first_name LIKE :q2 OR last_name LIKE :q3 OR telegram_id = :qid)';
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $params['q1'] = $params['q2'] = $params['q3'] = $like;
            $params['qid'] = ctype_digit($q) ? (int) $q : -1;
        }
        if ($filter === 'blocked') {
            $where[] = 'is_blocked = 1';
        } elseif ($filter === 'admin' || $filter === 'vip') {
            $where[] = 'role = :role';
            $params['role'] = $filter;
        }
        $w = implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM users WHERE $w", $params);
        $params['lim'] = $perPage;
        $params['off'] = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll("SELECT * FROM users WHERE $w ORDER BY last_seen_at DESC, id DESC LIMIT :lim OFFSET :off", $params);
        return ['rows' => $rows, 'total' => $total];
    }

    public function count(): int
    {
        return (int) $this->db->value('SELECT COUNT(*) FROM users');
    }
}

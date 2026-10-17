<?php

declare(strict_types=1);

namespace EmojiMaker\Database\Repositories;

use EmojiMaker\Database\Database;

/**
 * Smaller tables: audit_logs, telegram_updates, rate_limits, usage_counters,
 * sticker_sets, admin_users.
 */
final class MiscRepository
{
    public function __construct(private Database $db)
    {
    }

    // ---------------------------------------------------------------- audit

    /** @param array<string,mixed> $details */
    public function audit(string $actorType, ?int $actorId, string $action, ?string $targetType = null, ?int $targetId = null, array $details = [], ?string $ip = null, ?string $ua = null): void
    {
        try {
            $this->db->insert('audit_logs', [
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'action' => mb_substr($action, 0, 64),
                'target_type' => $targetType,
                'target_id' => $targetId,
                'details' => $details ? json_encode(\EmojiMaker\Security\Redactor::value($details), JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $ip,
                'user_agent' => $ua !== null ? mb_substr($ua, 0, 255) : null,
            ]);
        } catch (\Throwable) {
            // auditing must never break the request
        }
    }

    /** @return array{rows:list<array<string,mixed>>,total:int} */
    public function auditList(int $page, int $perPage = 50, string $q = ''): array
    {
        $where = '1=1';
        $params = [];
        if ($q !== '') {
            $where = 'action LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        $total = (int) $this->db->value("SELECT COUNT(*) FROM audit_logs WHERE $where", $params);
        $params['lim'] = $perPage;
        $params['off'] = max(0, ($page - 1) * $perPage);
        return ['rows' => $this->db->fetchAll("SELECT * FROM audit_logs WHERE $where ORDER BY id DESC LIMIT :lim OFFSET :off", $params), 'total' => $total];
    }

    // -------------------------------------------------------------- updates

    /** Stores an incoming update; false when it was already received (duplicate delivery). */
    public function storeUpdate(int $updateId, ?int $telegramId, string $type, string $payload): bool
    {
        return $this->db->insert('telegram_updates', [
            'update_id' => $updateId,
            'telegram_id' => $telegramId,
            'type' => mb_substr($type, 0, 32),
            'payload' => $payload,
            'status' => 'received',
        ], true) !== 0 || $this->db->value('SELECT ROW_COUNT()') > 0;
    }

    public function updateExists(int $updateId): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM telegram_updates WHERE update_id = ?', [$updateId]);
    }

    public function markUpdate(int $updateId, string $status, ?string $error = null): void
    {
        $this->db->query('UPDATE telegram_updates SET status = ?, error = ?, processed_at = NOW() WHERE update_id = ?', [$status, $error !== null ? mb_substr($error, 0, 500) : null, $updateId]);
    }

    /** Claims pending updates for async processing. @return list<array<string,mixed>> */
    public function claimPendingUpdates(int $limit = 20): array
    {
        $rows = $this->db->fetchAll("SELECT update_id FROM telegram_updates WHERE status = 'received' AND created_at < (NOW() - INTERVAL 1 SECOND) ORDER BY update_id ASC LIMIT ?", [$limit]);
        $claimed = [];
        foreach ($rows as $r) {
            if ($this->db->query("UPDATE telegram_updates SET status = 'processing' WHERE update_id = ? AND status = 'received'", [$r['update_id']])->rowCount() === 1) {
                $claimed[] = $this->db->fetch('SELECT * FROM telegram_updates WHERE update_id = ?', [$r['update_id']]);
            }
        }
        return array_values(array_filter($claimed));
    }

    public function purgeUpdates(int $days): int
    {
        return $this->db->query('DELETE FROM telegram_updates WHERE created_at < (NOW() - INTERVAL ? DAY)', [$days])->rowCount();
    }

    // ---------------------------------------------------------- rate limits

    /** Fixed window counter. Returns true while the bucket is under the limit. */
    public function hit(string $bucket, int $limit, int $windowSeconds): bool
    {
        $bucket = mb_substr($bucket, 0, 120);
        $windowStart = date('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);
        $this->db->query(
            'INSERT INTO rate_limits (bucket, window_start, hits) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE hits = IF(window_start = VALUES(window_start), hits + 1, 1), window_start = VALUES(window_start)',
            [$bucket, $windowStart]
        );
        $hits = (int) $this->db->value('SELECT hits FROM rate_limits WHERE bucket = ?', [$bucket]);
        return $hits <= $limit;
    }

    public function hits(string $bucket, int $windowSeconds): int
    {
        $windowStart = date('Y-m-d H:i:s', intdiv(time(), $windowSeconds) * $windowSeconds);
        return (int) $this->db->value('SELECT hits FROM rate_limits WHERE bucket = ? AND window_start = ?', [$bucket, $windowStart]);
    }

    public function resetBucket(string $bucket): void
    {
        $this->db->query('DELETE FROM rate_limits WHERE bucket = ?', [$bucket]);
    }

    public function purgeRateLimits(): void
    {
        $this->db->query('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 1 DAY)');
    }

    // ---------------------------------------------------------------- usage

    public function addUsage(int $userId, int $units, int $outputs): void
    {
        $this->db->query(
            'INSERT INTO usage_counters (user_id, day, units, outputs) VALUES (?, CURDATE(), ?, ?)
             ON DUPLICATE KEY UPDATE units = GREATEST(0, units + VALUES(units)), outputs = GREATEST(0, outputs + VALUES(outputs))',
            [$userId, $units, $outputs]
        );
    }

    public function todayUnits(int $userId): int
    {
        return (int) ($this->db->value('SELECT units FROM usage_counters WHERE user_id = ? AND day = CURDATE()', [$userId]) ?? 0);
    }

    // ---------------------------------------------------------- sticker sets

    /** @param array<string,mixed> $data */
    public function createStickerSet(array $data): int
    {
        return $this->db->insert('sticker_sets', $data);
    }

    /** @param array<string,mixed> $data */
    public function updateStickerSet(int $id, array $data): void
    {
        $this->db->update('sticker_sets', $data, ['id' => $id]);
    }

    /** @return list<array<string,mixed>> */
    public function stickerSetsForUser(int $userId): array
    {
        return $this->db->fetchAll("SELECT * FROM sticker_sets WHERE user_id = ? AND status <> 'deleted' ORDER BY id DESC LIMIT 30", [$userId]);
    }

    /** @return array<string,mixed>|null */
    public function stickerSetForUser(int $id, int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM sticker_sets WHERE id = ? AND user_id = ?', [$id, $userId]);
    }

    public function stickerSetNameExists(string $name): bool
    {
        return (bool) $this->db->value('SELECT 1 FROM sticker_sets WHERE name = ?', [$name]);
    }

    // ---------------------------------------------------------------- admins

    /** @return array<string,mixed>|null */
    public function adminByUsername(string $username): ?array
    {
        return $this->db->fetch('SELECT * FROM admin_users WHERE username = ?', [$username]);
    }

    /** @return array<string,mixed>|null */
    public function admin(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM admin_users WHERE id = ?', [$id]);
    }

    /** @return list<array<string,mixed>> */
    public function admins(): array
    {
        return $this->db->fetchAll('SELECT id, username, display_name, role, is_active, last_login_at, last_login_ip, created_at FROM admin_users ORDER BY id');
    }

    public function createAdmin(string $username, string $password, string $role = 'superadmin', ?string $displayName = null): int
    {
        return $this->db->insert('admin_users', [
            'username' => $username,
            'password_hash' => password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT),
            'display_name' => $displayName ?? $username,
            'role' => $role,
        ]);
    }

    /** @param array<string,mixed> $data */
    public function updateAdmin(int $id, array $data): void
    {
        $this->db->update('admin_users', $data, ['id' => $id]);
    }
}

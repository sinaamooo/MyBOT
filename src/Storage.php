<?php

declare(strict_types=1);

namespace App;

use PDO;

/**
 * SQLite storage: users, quota usage, processed updates and settings.
 */
final class Storage
{
    private PDO $db;

    public function __construct(string $file)
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->db = new PDO('sqlite:' . $file);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA busy_timeout = 5000');
        $this->db->exec('CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            first_name TEXT, username TEXT,
            bonus INTEGER NOT NULL DEFAULT 0,
            banned INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
        )');
        $this->db->exec('CREATE TABLE IF NOT EXISTS usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL, period TEXT NOT NULL,
            symbol TEXT NOT NULL, timeframe TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at INTEGER NOT NULL
        )');
        $this->db->exec('CREATE INDEX IF NOT EXISTS usage_user_period ON usage(user_id, period)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS updates (update_id INTEGER PRIMARY KEY, created_at INTEGER NOT NULL)');
        $this->db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');
    }

    /** Returns false if this update was already handled (Telegram retries webhooks). */
    public function claimUpdate(int $updateId): bool
    {
        $st = $this->db->prepare('INSERT OR IGNORE INTO updates (update_id, created_at) VALUES (?, ?)');
        $st->execute([$updateId, time()]);
        if (random_int(1, 200) === 1) {
            $this->db->prepare('DELETE FROM updates WHERE created_at < ?')->execute([time() - 7 * 86400]);
        }
        return $st->rowCount() === 1;
    }

    public function touchUser(array $from): void
    {
        $this->db->prepare('INSERT INTO users (user_id, first_name, username, created_at) VALUES (?, ?, ?, ?)
            ON CONFLICT(user_id) DO UPDATE SET first_name = excluded.first_name, username = excluded.username')
            ->execute([(int) $from['id'], $from['first_name'] ?? '', $from['username'] ?? '', time()]);
    }

    public function user(int $id): ?array
    {
        $st = $this->db->prepare('SELECT * FROM users WHERE user_id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function findUser(string $ref): ?array
    {
        $ref = ltrim(trim($ref), '@');
        $st = ctype_digit($ref)
            ? $this->db->prepare('SELECT * FROM users WHERE user_id = ?')
            : $this->db->prepare('SELECT * FROM users WHERE lower(username) = lower(?)');
        $st->execute([$ref]);
        return $st->fetch() ?: null;
    }

    /** Counts pending + done requests so parallel messages cannot exceed the quota. */
    public function used(int $userId, string $period): int
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM usage WHERE user_id = ? AND period = ? AND status IN ('pending', 'done')");
        $st->execute([$userId, $period]);
        return (int) $st->fetchColumn();
    }

    /** Atomically reserves one analysis if the user still has quota. Returns the usage id or null. */
    public function reserve(int $userId, string $period, int $limit, string $symbol, string $tf): ?int
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $user = $this->user($userId);
            $allowed = $limit + (int) ($user['bonus'] ?? 0);
            if ($limit >= 0 && $this->used($userId, $period) >= $allowed) {
                $this->db->exec('ROLLBACK');
                return null;
            }
            $this->db->prepare('INSERT INTO usage (user_id, period, symbol, timeframe, created_at) VALUES (?, ?, ?, ?, ?)')
                ->execute([$userId, $period, $symbol, $tf, time()]);
            $id = (int) $this->db->lastInsertId();
            $this->db->exec('COMMIT');
            return $id;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    public function finish(int $usageId, bool $ok): void
    {
        $this->db->prepare('UPDATE usage SET status = ? WHERE id = ?')->execute([$ok ? 'done' : 'failed', $usageId]);
    }

    public function addBonus(int $userId, int $n): void
    {
        $this->db->prepare('INSERT INTO users (user_id, bonus, created_at) VALUES (?, ?, ?)
            ON CONFLICT(user_id) DO UPDATE SET bonus = bonus + excluded.bonus')->execute([$userId, $n, time()]);
    }

    public function resetUsage(int $userId, string $period): void
    {
        $this->db->prepare("UPDATE usage SET status = 'reset' WHERE user_id = ? AND period = ?")->execute([$userId, $period]);
        $this->db->prepare('UPDATE users SET bonus = 0 WHERE user_id = ?')->execute([$userId]);
    }

    public function setBanned(int $userId, bool $banned): void
    {
        $this->db->prepare('INSERT INTO users (user_id, banned, created_at) VALUES (?, ?, ?)
            ON CONFLICT(user_id) DO UPDATE SET banned = excluded.banned')->execute([$userId, $banned ? 1 : 0, time()]);
    }

    public function stats(string $period): array
    {
        $q = fn (string $sql, array $p = []) => (function () use ($sql, $p) {
            $st = $this->db->prepare($sql);
            $st->execute($p);
            return $st;
        })();
        return [
            'users' => (int) $q('SELECT COUNT(*) FROM users')->fetchColumn(),
            'period_done' => (int) $q("SELECT COUNT(*) FROM usage WHERE period = ? AND status = 'done'", [$period])->fetchColumn(),
            'period_users' => (int) $q("SELECT COUNT(DISTINCT user_id) FROM usage WHERE period = ? AND status = 'done'", [$period])->fetchColumn(),
            'total_done' => (int) $q("SELECT COUNT(*) FROM usage WHERE status = 'done'")->fetchColumn(),
            'top' => $q("SELECT symbol, COUNT(*) n FROM usage WHERE period = ? AND status = 'done' GROUP BY symbol ORDER BY n DESC LIMIT 5", [$period])->fetchAll(),
        ];
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $st = $this->db->prepare('SELECT value FROM settings WHERE key = ?');
        $st->execute([$key]);
        $v = $st->fetchColumn();
        return $v === false ? $default : (string) $v;
    }

    public function set(string $key, ?string $value): void
    {
        if ($value === null) {
            $this->db->prepare('DELETE FROM settings WHERE key = ?')->execute([$key]);
            return;
        }
        $this->db->prepare('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')->execute([$key, $value]);
    }
}

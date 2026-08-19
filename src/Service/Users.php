<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;

/**
 * The bot's opt-in audience: people who pressed /start in a private chat.
 * Nobody lands here without contacting the bot first, and /stop removes them.
 */
final class Users
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $from a Telegram User payload */
    public function touch(array $from): void
    {
        $id = (int) ($from['id'] ?? 0);
        if ($id === 0) {
            return;
        }

        $now = time();

        $this->db->run(
            'INSERT INTO users (tg_id, username, first_name, last_name, created_at, last_seen)
             VALUES (:tg_id, :username, :first_name, :last_name, :now, :now)
             ON CONFLICT(tg_id) DO UPDATE SET
                username   = excluded.username,
                first_name = excluded.first_name,
                last_name  = excluded.last_name,
                last_seen  = excluded.last_seen',
            [
                'tg_id'      => $id,
                'username'   => \is_string($from['username'] ?? null) ? $from['username'] : null,
                'first_name' => \is_string($from['first_name'] ?? null) ? $from['first_name'] : null,
                'last_name'  => \is_string($from['last_name'] ?? null) ? $from['last_name'] : null,
                'now'        => $now,
            ],
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $tgId): ?array
    {
        return $this->db->one('SELECT * FROM users WHERE tg_id = :id', ['id' => $tgId]);
    }

    public function subscribe(int $tgId): void
    {
        $this->db->run(
            'UPDATE users SET subscribed = 1, blocked = 0 WHERE tg_id = :id',
            ['id' => $tgId],
        );
    }

    public function unsubscribe(int $tgId): void
    {
        $this->db->run('UPDATE users SET subscribed = 0 WHERE tg_id = :id', ['id' => $tgId]);
    }

    public function markBlocked(int $tgId): void
    {
        $this->db->run(
            'UPDATE users SET blocked = 1, subscribed = 0 WHERE tg_id = :id',
            ['id' => $tgId],
        );
    }

    public function isSubscribed(int $tgId): bool
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM users WHERE tg_id = :id AND subscribed = 1 AND blocked = 0',
            ['id' => $tgId],
        ) > 0;
    }

    /** @return list<int> chat ids of everyone who opted in and has not blocked us */
    public function subscriberIds(): array
    {
        $rows = $this->db->all(
            'SELECT tg_id FROM users WHERE subscribed = 1 AND blocked = 0 ORDER BY tg_id',
        );

        return array_map(static fn (array $r): int => (int) $r['tg_id'], $rows);
    }

    public function countSubscribers(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM users WHERE subscribed = 1 AND blocked = 0');
    }

    public function countAll(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM users');
    }

    public function countBlocked(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM users WHERE blocked = 1');
    }

    public function countUnsubscribed(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM users WHERE subscribed = 0 AND blocked = 0');
    }

    public function countNewSince(int $timestamp): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM users WHERE created_at >= :t',
            ['t' => $timestamp],
        );
    }

    public function countActiveSince(int $timestamp): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM users WHERE last_seen >= :t',
            ['t' => $timestamp],
        );
    }

    public function setAdmin(int $tgId, bool $isAdmin): void
    {
        $this->db->run(
            'UPDATE users SET is_admin = :flag WHERE tg_id = :id',
            ['flag' => $isAdmin ? 1 : 0, 'id' => $tgId],
        );
    }

    public function isAdminInDb(int $tgId): bool
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM users WHERE tg_id = :id AND is_admin = 1',
            ['id' => $tgId],
        ) > 0;
    }
}

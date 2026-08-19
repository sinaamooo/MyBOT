<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;
use MyBot\Support\Text;

/**
 * Per-group message counters, aggregated by day.
 *
 * This is the analytics answer to "who are the active members": the bot counts
 * messages in groups it has been added to and can show you a leaderboard. It is
 * a reporting feature — it never turns into a recipient list, because the send
 * audiences are groups and opt-in subscribers only.
 */
final class Activity
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $message */
    public function record(array $message): void
    {
        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;

        if (!\is_array($chat) || !\is_array($from)) {
            return;
        }

        $chatId = (int) ($chat['id'] ?? 0);
        $userId = (int) ($from['id'] ?? 0);

        if ($chatId >= 0 || $userId === 0 || ($from['is_bot'] ?? false) === true) {
            return;
        }

        $this->db->run(
            'INSERT INTO activity (chat_id, user_id, day, messages, name, username)
             VALUES (:chat_id, :user_id, :day, 1, :name, :username)
             ON CONFLICT(chat_id, user_id, day) DO UPDATE SET
                messages = messages + 1,
                name     = excluded.name,
                username = excluded.username',
            [
                'chat_id'  => $chatId,
                'user_id'  => $userId,
                'day'      => date('Y-m-d'),
                'name'     => Text::fullName($from),
                'username' => \is_string($from['username'] ?? null) ? $from['username'] : null,
            ],
        );
    }

    /**
     * Most active members of one group over the last $days days.
     *
     * @return list<array<string, mixed>>
     */
    public function topMembers(int $chatId, int $days = 7, int $limit = 10): array
    {
        return $this->db->all(
            'SELECT user_id, name, username, SUM(messages) AS total
             FROM activity
             WHERE chat_id = :chat_id AND day >= :since
             GROUP BY user_id
             ORDER BY total DESC
             LIMIT :limit',
            [
                'chat_id' => $chatId,
                'since'   => date('Y-m-d', time() - $days * 86400),
                'limit'   => $limit,
            ],
        );
    }

    /** @return list<array<string, mixed>> */
    public function topGroups(int $days = 7, int $limit = 10): array
    {
        return $this->db->all(
            'SELECT a.chat_id, t.title, SUM(a.messages) AS total,
                    COUNT(DISTINCT a.user_id) AS members
             FROM activity a
             LEFT JOIN targets t ON t.chat_id = a.chat_id
             WHERE a.day >= :since
             GROUP BY a.chat_id
             ORDER BY total DESC
             LIMIT :limit',
            ['since' => date('Y-m-d', time() - $days * 86400), 'limit' => $limit],
        );
    }

    /** @return list<array{day: string, total: int}> */
    public function dailyTotals(int $days = 7): array
    {
        $rows = $this->db->all(
            'SELECT day, SUM(messages) AS total
             FROM activity
             WHERE day >= :since
             GROUP BY day
             ORDER BY day',
            ['since' => date('Y-m-d', time() - $days * 86400)],
        );

        return array_map(
            static fn (array $r): array => ['day' => (string) $r['day'], 'total' => (int) $r['total']],
            $rows,
        );
    }

    public function totalMessages(int $days = 7): int
    {
        return $this->db->count(
            'SELECT COALESCE(SUM(messages), 0) FROM activity WHERE day >= :since',
            ['since' => date('Y-m-d', time() - $days * 86400)],
        );
    }

    public function activeMemberCount(int $days = 7): int
    {
        return $this->db->count(
            'SELECT COUNT(DISTINCT user_id) FROM activity WHERE day >= :since',
            ['since' => date('Y-m-d', time() - $days * 86400)],
        );
    }

    /** Housekeeping: drop counters older than $days. */
    public function prune(int $days = 90): int
    {
        return $this->db->run(
            'DELETE FROM activity WHERE day < :cutoff',
            ['cutoff' => date('Y-m-d', time() - $days * 86400)],
        )->rowCount();
    }
}

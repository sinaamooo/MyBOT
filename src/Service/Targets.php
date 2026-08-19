<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;

/**
 * Groups / channels the banner gets posted into.
 * There is no cap on how many you register.
 */
final class Targets
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Registers (or refreshes) a chat. Called automatically when the bot is
     * added to a group, and manually via the panel.
     *
     * @param array<string, mixed> $chat a Telegram Chat payload
     */
    public function upsert(array $chat, ?int $addedBy = null): int
    {
        $chatId = (int) ($chat['id'] ?? 0);
        if ($chatId === 0) {
            return 0;
        }

        $now      = time();
        $existing = $this->findByChatId($chatId);

        $fields = [
            'type'       => \is_string($chat['type'] ?? null) ? $chat['type'] : 'group',
            'title'      => \is_string($chat['title'] ?? null) ? $chat['title'] : null,
            'username'   => \is_string($chat['username'] ?? null) ? $chat['username'] : null,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            $this->db->update('targets', $fields, 'chat_id = :chat_id', ['chat_id' => $chatId]);

            return (int) $existing['id'];
        }

        return $this->db->insert('targets', $fields + [
            'chat_id'  => $chatId,
            'active'   => 1,
            'can_post' => 1,
            'added_by' => $addedBy,
            'added_at' => $now,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM targets WHERE id = :id', ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function findByChatId(int $chatId): ?array
    {
        return $this->db->one('SELECT * FROM targets WHERE chat_id = :chat_id', ['chat_id' => $chatId]);
    }

    /** @return list<array<string, mixed>> */
    public function page(int $page, int $perPage = 8): array
    {
        return $this->db->all(
            'SELECT * FROM targets ORDER BY active DESC, id DESC LIMIT :limit OFFSET :offset',
            ['limit' => $perPage, 'offset' => max(0, $page) * $perPage],
        );
    }

    /** @return list<array<string, mixed>> */
    public function allActive(): array
    {
        return $this->db->all(
            'SELECT * FROM targets WHERE active = 1 AND can_post = 1 ORDER BY id',
        );
    }

    /** @return list<array<string, mixed>> */
    public function allWithActivity(int $limit = 20): array
    {
        return $this->db->all(
            'SELECT t.*, COALESCE(SUM(a.messages), 0) AS msg_count
             FROM targets t
             LEFT JOIN activity a ON a.chat_id = t.chat_id
             GROUP BY t.id
             ORDER BY msg_count DESC, t.id DESC
             LIMIT :limit',
            ['limit' => $limit],
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->update(
            'targets',
            ['active' => $active ? 1 : 0, 'updated_at' => time()],
            'id = :id',
            ['id' => $id],
        );
    }

    public function toggle(int $id): bool
    {
        $target = $this->find($id);
        if ($target === null) {
            return false;
        }

        $next = ((int) $target['active']) === 1 ? false : true;
        $this->setActive($id, $next);

        return $next;
    }

    public function setThread(int $id, ?int $threadId): void
    {
        $this->db->update(
            'targets',
            ['thread_id' => $threadId, 'updated_at' => time()],
            'id = :id',
            ['id' => $id],
        );
    }

    /** Marks a chat as un-postable (kicked, demoted, deleted…). */
    public function disablePosting(int $chatId, string $reason = ''): void
    {
        $this->db->update(
            'targets',
            ['can_post' => 0, 'active' => 0, 'updated_at' => time()],
            'chat_id = :chat_id',
            ['chat_id' => $chatId],
        );

        $this->db->insert('events', [
            'type'       => 'target_disabled',
            'chat_id'    => $chatId,
            'meta'       => json_encode(['reason' => $reason], \JSON_UNESCAPED_UNICODE),
            'created_at' => time(),
        ]);
    }

    public function setMembers(int $chatId, int $members): void
    {
        $this->db->update(
            'targets',
            ['members' => $members, 'updated_at' => time()],
            'chat_id = :chat_id',
            ['chat_id' => $chatId],
        );
    }

    public function delete(int $id): void
    {
        $this->db->run('DELETE FROM targets WHERE id = :id', ['id' => $id]);
    }

    public function countAll(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM targets');
    }

    public function countActive(): int
    {
        return $this->db->count('SELECT COUNT(*) FROM targets WHERE active = 1 AND can_post = 1');
    }

    public function totalMembers(): int
    {
        return $this->db->count('SELECT COALESCE(SUM(members), 0) FROM targets WHERE active = 1');
    }

    public function title(array $target): string
    {
        $title = \is_string($target['title'] ?? null) ? trim($target['title']) : '';
        if ($title !== '') {
            return $title;
        }

        $username = \is_string($target['username'] ?? null) ? $target['username'] : '';
        if ($username !== '') {
            return '@' . $username;
        }

        return 'چت ' . (string) $target['chat_id'];
    }
}

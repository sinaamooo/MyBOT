<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\Database;

/**
 * A campaign is "this banner, to this audience, at this time".
 *
 * Audiences are deliberately limited to places the operator controls or that
 * people opted into:
 *   groups      — every active group/channel the bot was added to
 *   target      — one specific group/channel
 *   subscribers — private chats with users who started the bot and have not
 *                 sent /stop
 */
final class Campaigns
{
    public const AUDIENCE_GROUPS      = 'groups';
    public const AUDIENCE_TARGET      = 'target';
    public const AUDIENCE_SUBSCRIBERS = 'subscribers';

    public const STATUS_QUEUED  = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE    = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(
        private readonly Database $db,
        private readonly Targets $targets,
        private readonly Users $users,
    ) {
    }

    public function create(
        int $bannerId,
        string $audience,
        ?int $targetChatId,
        int $createdBy,
        int $scheduledAt = 0,
    ): int {
        return $this->db->insert('campaigns', [
            'banner_id'      => $bannerId,
            'audience'       => $audience,
            'target_chat_id' => $targetChatId,
            'status'         => self::STATUS_QUEUED,
            'scheduled_at'   => $scheduledAt,
            'created_by'     => $createdBy,
            'created_at'     => time(),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM campaigns WHERE id = :id', ['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function due(int $limit = 5): array
    {
        return $this->db->all(
            'SELECT * FROM campaigns
             WHERE status = :status AND scheduled_at <= :now
             ORDER BY scheduled_at, id
             LIMIT :limit',
            ['status' => self::STATUS_QUEUED, 'now' => time(), 'limit' => $limit],
        );
    }

    /** @return list<array<string, mixed>> */
    public function running(): array
    {
        return $this->db->all(
            'SELECT * FROM campaigns WHERE status = :status ORDER BY id',
            ['status' => self::STATUS_RUNNING],
        );
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 10): array
    {
        return $this->db->all(
            'SELECT c.*, b.name AS banner_name
             FROM campaigns c
             LEFT JOIN banners b ON b.id = c.banner_id
             ORDER BY c.id DESC
             LIMIT :limit',
            ['limit' => $limit],
        );
    }

    /**
     * Materialises the audience into queue rows and flips the campaign to
     * running. Returns the number of recipients.
     */
    public function expand(int $campaignId): int
    {
        $campaign = $this->find($campaignId);

        if ($campaign === null || $campaign['status'] !== self::STATUS_QUEUED) {
            return 0;
        }

        $recipients = $this->resolveAudience($campaign);

        return $this->db->transaction(function (Database $db) use ($campaign, $recipients): int {
            $now = time();

            foreach ($recipients as $recipient) {
                $db->insert('queue', [
                    'campaign_id'         => (int) $campaign['id'],
                    'chat_id'             => $recipient['chat_id'],
                    'thread_id'           => $recipient['thread_id'],
                    'reply_to_message_id' => $recipient['reply_to_message_id'],
                    'kind'                => $recipient['kind'],
                    'status'              => 'pending',
                    'next_attempt_at'     => 0,
                ]);
            }

            $total = \count($recipients);

            $db->update('campaigns', [
                'status'     => $total > 0 ? self::STATUS_RUNNING : self::STATUS_DONE,
                'total'      => $total,
                'started_at' => $now,
                'finished_at' => $total > 0 ? null : $now,
            ], 'id = :id', ['id' => (int) $campaign['id']]);

            return $total;
        });
    }

    /**
     * @param array<string, mixed> $campaign
     * @return list<array{chat_id: int, thread_id: int|null, reply_to_message_id: int|null, kind: string}>
     */
    private function resolveAudience(array $campaign): array
    {
        $audience = (string) $campaign['audience'];

        if ($audience === self::AUDIENCE_SUBSCRIBERS) {
            return array_map(
                static fn (int $id): array => [
                    'chat_id'             => $id,
                    'thread_id'           => null,
                    'reply_to_message_id' => null,
                    'kind'                => 'user',
                ],
                $this->users->subscriberIds(),
            );
        }

        if ($audience === self::AUDIENCE_TARGET) {
            $chatId = (int) ($campaign['target_chat_id'] ?? 0);
            if ($chatId === 0) {
                return [];
            }

            $target = $this->targets->findByChatId($chatId);
            if ($target === null) {
                return [];
            }

            return [$this->targetRow($target)];
        }

        return array_map($this->targetRow(...), $this->targets->allActive());
    }

    /**
     * @param array<string, mixed> $target
     * @return array{chat_id: int, thread_id: int|null, reply_to_message_id: int|null, kind: string}
     */
    private function targetRow(array $target): array
    {
        $threadId = $target['thread_id'] ?? null;

        return [
            'chat_id'             => (int) $target['chat_id'],
            'thread_id'           => is_numeric($threadId) ? (int) $threadId : null,
            'reply_to_message_id' => null,
            'kind'                => 'chat',
        ];
    }

    /** @return array{pending: int, sent: int, failed: int} */
    public function progress(int $campaignId): array
    {
        $rows = $this->db->all(
            'SELECT status, COUNT(*) AS n FROM queue WHERE campaign_id = :id GROUP BY status',
            ['id' => $campaignId],
        );

        $counts = ['pending' => 0, 'sent' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if (\array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['n'];
            }
        }

        return $counts;
    }

    /** Recomputes counters and closes the campaign when the queue is drained. */
    public function refresh(int $campaignId): void
    {
        $counts = $this->progress($campaignId);

        $fields = [
            'sent'   => $counts['sent'],
            'failed' => $counts['failed'],
        ];

        if ($counts['pending'] === 0) {
            $fields['status']      = self::STATUS_DONE;
            $fields['finished_at'] = time();
        }

        $this->db->update('campaigns', $fields, 'id = :id AND status != :cancelled', [
            'id'        => $campaignId,
            'cancelled' => self::STATUS_CANCELLED,
        ]);
    }

    public function cancel(int $campaignId): int
    {
        return $this->db->transaction(function (Database $db) use ($campaignId): int {
            $dropped = $db->run(
                'UPDATE queue SET status = :skipped, error = :reason
                 WHERE campaign_id = :id AND status = :pending',
                [
                    'skipped' => 'skipped',
                    'reason'  => 'campaign cancelled',
                    'id'      => $campaignId,
                    'pending' => 'pending',
                ],
            )->rowCount();

            $db->update('campaigns', [
                'status'      => self::STATUS_CANCELLED,
                'skipped'     => $dropped,
                'finished_at' => time(),
            ], 'id = :id', ['id' => $campaignId]);

            return $dropped;
        });
    }

    public function countByStatus(string $status): int
    {
        return $this->db->count(
            'SELECT COUNT(*) FROM campaigns WHERE status = :status',
            ['status' => $status],
        );
    }

    public function audienceLabel(array $campaign): string
    {
        return match ((string) $campaign['audience']) {
            self::AUDIENCE_GROUPS      => 'همه گروه‌های فعال',
            self::AUDIENCE_SUBSCRIBERS => 'کاربران عضو ربات',
            self::AUDIENCE_TARGET      => 'یک گروه مشخص',
            default                    => 'نامشخص',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_QUEUED    => '⏳ در صف',
            self::STATUS_RUNNING   => '🚀 در حال ارسال',
            self::STATUS_DONE      => '✅ تمام‌شده',
            self::STATUS_CANCELLED => '🛑 لغو شده',
            default                => $status,
        };
    }
}

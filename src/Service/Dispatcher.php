<?php

declare(strict_types=1);

namespace MyBot\Service;

use MyBot\Support\ApiException;
use MyBot\Support\Database;
use MyBot\Support\Logger;
use MyBot\Support\RateLimiter;

/**
 * Drains the send queue.
 *
 * Every delivery goes through the rate limiter, and every Telegram error is
 * classified into one of three outcomes: back off and retry (flood wait),
 * retry with exponential backoff (transient), or give up and stop targeting
 * that recipient (permanently unreachable).
 */
final class Dispatcher
{
    public function __construct(
        private readonly Database $db,
        private readonly Sender $sender,
        private readonly Banners $banners,
        private readonly Campaigns $campaigns,
        private readonly Targets $targets,
        private readonly Users $users,
        private readonly RateLimiter $limiter,
        private readonly Logger $logger,
        private readonly int $maxAttempts = 3,
        private readonly int $batchSize = 50,
    ) {
    }

    /**
     * Runs one pass: starts any due campaigns, then sends up to batchSize
     * messages. Returns how many messages were actually attempted.
     */
    public function tick(): int
    {
        $this->startDueCampaigns();

        $attempted = 0;

        foreach ($this->claimBatch() as $item) {
            $this->deliver($item);
            $attempted++;
        }

        $this->closeFinishedCampaigns();

        return $attempted;
    }

    private function startDueCampaigns(): void
    {
        foreach ($this->campaigns->due() as $campaign) {
            $total = $this->campaigns->expand((int) $campaign['id']);

            $this->logger->info('campaign started', [
                'campaign'   => (int) $campaign['id'],
                'recipients' => $total,
            ]);

            $this->db->insert('events', [
                'type'        => 'campaign_started',
                'campaign_id' => (int) $campaign['id'],
                'meta'        => json_encode(['total' => $total], \JSON_UNESCAPED_UNICODE),
                'created_at'  => time(),
            ]);
        }
    }

    /**
     * Atomically claims queue rows so that several workers can run side by side.
     *
     * @return list<array<string, mixed>>
     */
    private function claimBatch(): array
    {
        $candidates = $this->db->all(
            'SELECT id FROM queue
             WHERE status = :status AND next_attempt_at <= :now
             ORDER BY id
             LIMIT :limit',
            ['status' => 'pending', 'now' => time(), 'limit' => $this->batchSize],
        );

        $claimed = [];

        foreach ($candidates as $candidate) {
            $id = (int) $candidate['id'];

            $won = $this->db->run(
                'UPDATE queue SET status = :sending WHERE id = :id AND status = :pending',
                ['sending' => 'sending', 'id' => $id, 'pending' => 'pending'],
            )->rowCount();

            if ($won === 0) {
                continue;
            }

            $row = $this->db->one('SELECT * FROM queue WHERE id = :id', ['id' => $id]);
            if ($row !== null) {
                $claimed[] = $row;
            }
        }

        return $claimed;
    }

    /** @param array<string, mixed> $item a queue row */
    private function deliver(array $item): void
    {
        $queueId    = (int) $item['id'];
        $chatId     = (int) $item['chat_id'];
        $campaignId = (int) $item['campaign_id'];

        $campaign = $this->campaigns->find($campaignId);

        if ($campaign === null || $campaign['status'] === Campaigns::STATUS_CANCELLED) {
            $this->finish($queueId, 'skipped', 'campaign cancelled');

            return;
        }

        $banner = $this->banners->find((int) $campaign['banner_id']);

        if ($banner === null) {
            $this->finish($queueId, 'failed', 'banner deleted');

            return;
        }

        // A user who unsubscribed between queueing and sending must not be messaged.
        if ($item['kind'] === 'user' && !$this->users->isSubscribed($chatId)) {
            $this->finish($queueId, 'skipped', 'user unsubscribed');

            return;
        }

        $threadId = is_numeric($item['thread_id'] ?? null) ? (int) $item['thread_id'] : null;
        $replyTo  = is_numeric($item['reply_to_message_id'] ?? null)
            ? (int) $item['reply_to_message_id']
            : null;

        $this->limiter->throttle($chatId);

        try {
            $messageId = $this->sender->send($banner, $chatId, $replyTo, $threadId);

            $this->db->update('queue', [
                'status'     => 'sent',
                'message_id' => $messageId,
                'sent_at'    => time(),
                'attempts'   => (int) $item['attempts'] + 1,
                'error'      => null,
            ], 'id = :id', ['id' => $queueId]);

            $this->db->insert('events', [
                'type'        => 'delivered',
                'chat_id'     => $chatId,
                'campaign_id' => $campaignId,
                'created_at'  => time(),
            ]);
        } catch (ApiException $e) {
            $this->handleFailure($item, $e);
        }
    }

    /** @param array<string, mixed> $item */
    private function handleFailure(array $item, ApiException $e): void
    {
        $queueId  = (int) $item['id'];
        $chatId   = (int) $item['chat_id'];
        $attempts = (int) $item['attempts'] + 1;

        if ($e->isFlood()) {
            $wait = $e->retryAfter() ?? 30;
            $this->limiter->penalise($wait);

            // A flood wait is not the recipient's fault, so it does not burn an attempt.
            $this->db->update('queue', [
                'status'          => 'pending',
                'next_attempt_at' => time() + $wait,
                'error'           => 'flood wait ' . $wait . 's',
            ], 'id = :id', ['id' => $queueId]);

            $this->logger->warn('flood wait', ['chat' => $chatId, 'seconds' => $wait]);

            return;
        }

        if ($e->isUnreachable()) {
            $this->finish($queueId, 'failed', $e->getMessage(), $attempts);
            $this->retire($item, $e);

            return;
        }

        if ($e->isTransient() && $attempts < $this->maxAttempts) {
            $this->db->update('queue', [
                'status'          => 'pending',
                'attempts'        => $attempts,
                'next_attempt_at' => time() + (5 * (2 ** $attempts)),
                'error'           => $e->getMessage(),
            ], 'id = :id', ['id' => $queueId]);

            return;
        }

        $this->finish($queueId, 'failed', $e->getMessage(), $attempts);

        $this->logger->warn('delivery failed', [
            'chat'  => $chatId,
            'code'  => $e->errorCode,
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Stops targeting a recipient we can never reach again.
     *
     * @param array<string, mixed> $item
     */
    private function retire(array $item, ApiException $e): void
    {
        $chatId = (int) $item['chat_id'];

        if ($item['kind'] === 'user') {
            if ($e->isBlockedByUser()) {
                $this->users->markBlocked($chatId);
            } else {
                $this->users->unsubscribe($chatId);
            }

            return;
        }

        $this->targets->disablePosting($chatId, $e->getMessage());
    }

    private function finish(int $queueId, string $status, string $error, ?int $attempts = null): void
    {
        $fields = ['status' => $status, 'error' => mb_substr($error, 0, 400, 'UTF-8')];

        if ($attempts !== null) {
            $fields['attempts'] = $attempts;
        }

        $this->db->update('queue', $fields, 'id = :id', ['id' => $queueId]);
    }

    private function closeFinishedCampaigns(): void
    {
        foreach ($this->campaigns->running() as $campaign) {
            $id     = (int) $campaign['id'];
            $before = (string) $campaign['status'];

            $this->campaigns->refresh($id);

            $after = $this->campaigns->find($id);

            if ($after !== null && $after['status'] === Campaigns::STATUS_DONE && $before !== Campaigns::STATUS_DONE) {
                $this->logger->info('campaign finished', [
                    'campaign' => $id,
                    'sent'     => (int) $after['sent'],
                    'failed'   => (int) $after['failed'],
                ]);

                $this->db->insert('events', [
                    'type'        => 'campaign_finished',
                    'campaign_id' => $id,
                    'meta'        => json_encode([
                        'sent'   => (int) $after['sent'],
                        'failed' => (int) $after['failed'],
                    ], \JSON_UNESCAPED_UNICODE),
                    'created_at'  => time(),
                ]);
            }
        }
    }

    /** Re-queues rows left in `sending` by a worker that died mid-flight. */
    public function recoverStuck(int $olderThanSeconds = 300): int
    {
        return $this->db->run(
            'UPDATE queue SET status = :pending
             WHERE status = :sending AND id IN (
                 SELECT q.id FROM queue q
                 JOIN campaigns c ON c.id = q.campaign_id
                 WHERE q.status = :sending2 AND c.started_at < :cutoff
             )',
            [
                'pending'  => 'pending',
                'sending'  => 'sending',
                'sending2' => 'sending',
                'cutoff'   => time() - $olderThanSeconds,
            ],
        )->rowCount();
    }
}

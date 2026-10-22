<?php

declare(strict_types=1);

namespace EmojiMaker\Queue;

use EmojiMaker\Database\Database;
use EmojiMaker\Security\Redactor;
use EmojiMaker\Support\Logger;

/**
 * Database backed job queue (Redis is not available on shared hosting).
 *
 * Claiming is optimistic: candidates are read first and then taken with
 * "UPDATE ... WHERE id = ? AND status = 'queued'"; only one worker can win a
 * row (rowCount check). A short MySQL named lock serialises the capacity
 * checks (parallel limit, one heavy job per user) between workers, because
 * SKIP LOCKED does not exist on older MariaDB / MySQL versions.
 *
 * "update" and "maintenance" jobs are light (no rendering): they are not
 * counted against the parallel limit nor the one-job-per-user rule, so the
 * bot stays responsive while heavy jobs run.
 */
final class JobQueue
{
    public const TYPES = ['preview', 'generate', 'publish', 'deliver', 'update', 'maintenance'];
    public const LIGHT_TYPES = ['update', 'maintenance'];
    public const STATUSES = ['queued', 'running', 'completed', 'failed', 'cancelled'];

    private const CLAIM_LOCK = 'queue-claim';
    private const MAX_BACKOFF = 900;

    public function __construct(private Database $db, private Logger $logger)
    {
    }

    /** Returns the job id (existing id when $idempotencyKey was already used). */
    public function push(
        string $type,
        array $payload,
        ?int $userId = null,
        ?int $projectId = null,
        int $priority = 5,
        ?int $chatId = null,
        ?string $idempotencyKey = null,
        int $maxAttempts = 3,
        int $delaySeconds = 0,
    ): int {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unknown job type: ' . $type);
        }
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        // any caller supplied key fits CHAR(40) once hashed
        $key = $idempotencyKey !== null && $idempotencyKey !== '' ? sha1($idempotencyKey) : null;
        if ($key !== null && ($existing = $this->idForKey($key)) !== null) {
            return $existing;
        }
        $items = $type === 'generate' && is_array($payload['items'] ?? null) ? count($payload['items']) : 0;
        try {
            $id = $this->db->insert('generation_jobs', [
                'user_id' => $userId,
                'project_id' => $projectId,
                'type' => $type,
                'status' => 'queued',
                'priority' => max(-100, min(100, $priority)),
                'payload' => $json,
                'idempotency_key' => $key,
                'total_items' => $items,
                'max_attempts' => max(1, min(10, $maxAttempts)),
                'available_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
                'chat_id' => $chatId,
            ]);
        } catch (\PDOException $e) {
            // two requests with the same key raced: the other one won
            if ($key !== null && (string) $e->getCode() === '23000' && ($existing = $this->idForKey($key)) !== null) {
                return $existing;
            }
            throw $e;
        }
        $this->logger->info('Job queued', ['job' => $id, 'type' => $type, 'user' => $userId, 'items' => $items]);
        return $id;
    }

    private function idForKey(string $key): ?int
    {
        $id = $this->db->value('SELECT id FROM generation_jobs WHERE idempotency_key = ?', [$key]);
        return $id === null ? null : (int) $id;
    }

    /**
     * Atomically claims the next runnable job (status queued, available_at <= now),
     * honouring the parallel limit and at most one running heavy job per user.
     * @param list<string>|null $types
     * @return array<string,mixed>|null
     */
    public function claim(string $workerId, int $maxRunning, ?array $types = null): ?array
    {
        $types = $types === null ? self::TYPES : array_values(array_intersect(self::TYPES, $types));
        if (!$types) {
            return null;
        }
        $workerId = mb_substr($workerId, 0, 64);
        if (!$this->db->acquireLock(self::CLAIM_LOCK, 3)) {
            return null;
        }
        try {
            $light = $this->lightList();
            $running = (int) $this->db->value("SELECT COUNT(*) FROM generation_jobs WHERE status = 'running' AND type NOT IN ($light)");
            $allowed = $running < max(1, $maxRunning) ? $types : array_values(array_intersect($types, self::LIGHT_TYPES));
            if (!$allowed) {
                return null;
            }
            $in = implode(',', array_fill(0, count($allowed), '?'));
            $candidates = $this->db->fetchAll(
                "SELECT id, user_id, type FROM generation_jobs
                 WHERE status = 'queued' AND available_at <= NOW() AND type IN ($in)
                 ORDER BY priority DESC, available_at ASC, id ASC LIMIT 30",
                $allowed
            );
            if (!$candidates) {
                return null;
            }
            $busy = [];
            foreach ($this->db->fetchAll("SELECT DISTINCT user_id FROM generation_jobs WHERE status = 'running' AND user_id IS NOT NULL AND type NOT IN ($light)") as $r) {
                $busy[(int) $r['user_id']] = true;
            }
            foreach ($candidates as $c) {
                $isLight = in_array($c['type'], self::LIGHT_TYPES, true);
                if (!$isLight && $c['user_id'] !== null && isset($busy[(int) $c['user_id']])) {
                    continue;
                }
                $won = $this->db->query(
                    "UPDATE generation_jobs
                     SET status = 'running', locked_by = ?, locked_at = NOW(), heartbeat_at = NOW(),
                         started_at = NOW(), finished_at = NULL, attempts = attempts + 1
                     WHERE id = ? AND status = 'queued'",
                    [$workerId, (int) $c['id']]
                )->rowCount() === 1;
                if ($won) {
                    $job = $this->find((int) $c['id']);
                    if ($job !== null) {
                        $this->logger->info('Job claimed', ['job' => (int) $job['id'], 'type' => $job['type'], 'attempt' => (int) $job['attempts'], 'worker' => $workerId]);
                        return $job;
                    }
                }
            }
            return null;
        } finally {
            $this->db->releaseLock(self::CLAIM_LOCK);
        }
    }

    private function lightList(): string
    {
        return implode(',', array_map(static fn (string $t) => "'" . $t . "'", self::LIGHT_TYPES));
    }

    public function heartbeat(int $jobId): void
    {
        $this->db->query("UPDATE generation_jobs SET heartbeat_at = NOW() WHERE id = ? AND status = 'running'", [$jobId]);
    }

    public function progress(int $jobId, int $done, int $total, ?string $text = null, int $failed = 0): void
    {
        $total = max(0, $total);
        $done = max(0, $done);
        $pct = $total > 0 ? (int) min(100, floor($done * 100 / $total)) : 0;
        $this->db->query(
            'UPDATE generation_jobs SET done_items = ?, total_items = ?, failed_items = ?, progress = ?,
                progress_text = COALESCE(?, progress_text), heartbeat_at = NOW() WHERE id = ?',
            [$done, $total, max(0, $failed), $pct, $text !== null && $text !== '' ? mb_substr($text, 0, 255) : null, $jobId]
        );
    }

    public function setProgressMessage(int $jobId, int $chatId, int $messageId): void
    {
        $this->db->query('UPDATE generation_jobs SET chat_id = ?, progress_message_id = ? WHERE id = ?', [$chatId, $messageId, $jobId]);
    }

    /** @param array<string,mixed> $result */
    public function complete(int $jobId, array $result): void
    {
        $this->db->query(
            "UPDATE generation_jobs SET status = 'completed', result = ?, progress = 100, finished_at = NOW(),
                locked_by = NULL, error_message = NULL WHERE id = ? AND status <> 'cancelled'",
            [json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), $jobId]
        );
        $this->logger->info('Job completed', ['job' => $jobId]);
    }

    /** Retryable failures go back to queued with exponential backoff while attempts < max_attempts. */
    public function fail(int $jobId, string $error, bool $retryable = true): void
    {
        $job = $this->find($jobId);
        if ($job === null) {
            return;
        }
        $error = mb_substr(Redactor::text($error), 0, 1000);
        $attempts = (int) $job['attempts'];
        if ($retryable && $attempts < (int) $job['max_attempts'] && (int) $job['cancel_requested'] === 0) {
            $delay = min(self::MAX_BACKOFF, 30 * (2 ** max(0, $attempts - 1)));
            $this->db->query(
                "UPDATE generation_jobs SET status = 'queued', available_at = ?, locked_by = NULL, locked_at = NULL,
                    heartbeat_at = NULL, error_message = ? WHERE id = ?",
                [date('Y-m-d H:i:s', time() + $delay), $error, $jobId]
            );
            $this->logger->warning('Job failed, retry scheduled', ['job' => $jobId, 'attempt' => $attempts, 'retry_in' => $delay, 'error' => $error]);
            return;
        }
        $this->db->query(
            "UPDATE generation_jobs SET status = 'failed', finished_at = NOW(), locked_by = NULL, error_message = ? WHERE id = ?",
            [$error, $jobId]
        );
        $this->logger->error('Job failed', ['job' => $jobId, 'attempt' => $attempts, 'error' => $error]);
    }

    /** queued → cancelled, running → cancel_requested=1 (the handler stops between items). */
    public function cancel(int $jobId, ?int $userId = null): bool
    {
        $owner = $userId !== null ? ' AND user_id = ?' : '';
        $params = $userId !== null ? [$jobId, $userId] : [$jobId];
        $n = $this->db->query(
            "UPDATE generation_jobs SET status = 'cancelled', cancel_requested = 1, finished_at = NOW(), locked_by = NULL
             WHERE id = ? AND status = 'queued'" . $owner,
            $params
        )->rowCount();
        if ($n === 0) {
            $this->db->query("UPDATE generation_jobs SET cancel_requested = 1 WHERE id = ? AND status = 'running'" . $owner, $params);
            // an already flagged job does not count as "affected" in MySQL
            $n = (int) $this->db->value("SELECT COUNT(*) FROM generation_jobs WHERE id = ? AND status = 'running' AND cancel_requested = 1" . $owner, $params);
        }
        if ($n > 0) {
            $this->logger->info('Job cancel requested', ['job' => $jobId, 'user' => $userId]);
        }
        return $n > 0;
    }

    public function isCancelRequested(int $jobId): bool
    {
        $row = $this->db->fetch('SELECT status, cancel_requested FROM generation_jobs WHERE id = ?', [$jobId]);
        return $row === null || (int) $row['cancel_requested'] === 1 || $row['status'] === 'cancelled';
    }

    /** Final state of a job whose handler stopped because of a cancel request. */
    public function markCancelled(int $jobId): void
    {
        $this->db->query(
            "UPDATE generation_jobs SET status = 'cancelled', finished_at = NOW(), locked_by = NULL WHERE id = ? AND status IN ('queued','running')",
            [$jobId]
        );
        $this->logger->info('Job cancelled', ['job' => $jobId]);
    }

    /**
     * Puts a running job back into the queue without consuming an attempt
     * (worker shutdown in the middle of a job).
     */
    public function release(int $jobId, int $delaySeconds = 0): void
    {
        $this->db->query(
            "UPDATE generation_jobs SET status = 'queued', attempts = GREATEST(attempts - 1, 0), available_at = ?,
                locked_by = NULL, locked_at = NULL, heartbeat_at = NULL WHERE id = ? AND status = 'running'",
            [date('Y-m-d H:i:s', time() + max(0, $delaySeconds)), $jobId]
        );
        $this->logger->info('Job released', ['job' => $jobId]);
    }

    /** failed / cancelled → queued (admin "retry"). */
    public function retry(int $jobId): bool
    {
        $ok = $this->db->query(
            "UPDATE generation_jobs SET status = 'queued', attempts = 0, cancel_requested = 0, available_at = NOW(),
                error_message = NULL, finished_at = NULL, locked_by = NULL, locked_at = NULL, heartbeat_at = NULL,
                progress = 0, done_items = 0, failed_items = 0, progress_text = NULL
             WHERE id = ? AND status IN ('failed','cancelled')",
            [$jobId]
        )->rowCount() > 0;
        if ($ok) {
            $this->logger->info('Job re-queued', ['job' => $jobId]);
        }
        return $ok;
    }

    /** Running jobs without heartbeat → queued (attempts left) / failed / cancelled. */
    public function recoverStale(int $staleSeconds): int
    {
        return count($this->recoverStaleJobs($staleSeconds));
    }

    /**
     * Same as recoverStale() but tells what happened to every job, so the
     * worker can notify users of jobs that finally failed.
     * @return list<array{id:int,status:string,job:array<string,mixed>}>
     */
    public function recoverStaleJobs(int $staleSeconds): array
    {
        $staleSeconds = max(30, $staleSeconds);
        $rows = $this->db->fetchAll(
            "SELECT * FROM generation_jobs WHERE status = 'running'
               AND COALESCE(heartbeat_at, locked_at, started_at, updated_at) < (NOW() - INTERVAL ? SECOND)
             LIMIT 100",
            [$staleSeconds]
        );
        $out = [];
        foreach ($rows as $job) {
            $status = (int) $job['cancel_requested'] === 1 ? 'cancelled'
                : ((int) $job['attempts'] < (int) $job['max_attempts'] ? 'queued' : 'failed');
            $sql = $status === 'queued'
                ? "UPDATE generation_jobs SET status = 'queued', available_at = NOW(), locked_by = NULL, locked_at = NULL, heartbeat_at = NULL, error_message = ?"
                : "UPDATE generation_jobs SET status = '" . $status . "', finished_at = NOW(), locked_by = NULL, error_message = ?";
            // re-check staleness: a late heartbeat wins over the recovery
            $n = $this->db->query(
                $sql . " WHERE id = ? AND status = 'running'
                    AND COALESCE(heartbeat_at, locked_at, started_at, updated_at) < (NOW() - INTERVAL ? SECOND)",
                ['Worker stopped without finishing the job (no heartbeat)', (int) $job['id'], $staleSeconds]
            )->rowCount();
            if ($n === 1) {
                $out[] = ['id' => (int) $job['id'], 'status' => $status, 'job' => $job];
                $this->logger->warning('Stale job recovered', ['job' => (int) $job['id'], 'type' => $job['type'], 'now' => $status, 'worker' => $job['locked_by']]);
            }
        }
        return $out;
    }

    /** @return array<string,mixed>|null */
    public function find(int $jobId): ?array
    {
        return $this->db->fetch('SELECT * FROM generation_jobs WHERE id = ?', [$jobId]);
    }

    /** @return array<string,mixed> decoded payload */
    public static function payload(array $job): array
    {
        $p = json_decode((string) ($job['payload'] ?? ''), true);
        return is_array($p) ? $p : [];
    }

    /** @return array<string,mixed> decoded result */
    public static function result(array $job): array
    {
        $r = json_decode((string) ($job['result'] ?? ''), true);
        return is_array($r) ? $r : [];
    }

    /** queued + running jobs of a user (light jobs excluded). */
    public function activeCountForUser(int $userId): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM generation_jobs WHERE user_id = ? AND status IN ('queued','running') AND type NOT IN (" . $this->lightList() . ')',
            [$userId]
        );
    }

    /**
     * Admin job list without the (possibly large) payload / result columns;
     * use find() for the details of one job.
     * @param array<string,mixed> $filters status, type, user_id, project_id, q (job id or Telegram id)
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public function listForAdmin(int $page, int $perPage, array $filters): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $where[] = 'j.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['type']) && in_array($filters['type'], self::TYPES, true)) {
            $where[] = 'j.type = :type';
            $params['type'] = (string) $filters['type'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'j.user_id = :uid';
            $params['uid'] = (int) $filters['user_id'];
        }
        if (!empty($filters['project_id'])) {
            $where[] = 'j.project_id = :pid';
            $params['pid'] = (int) $filters['project_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '' && ctype_digit($q)) {
            $where[] = '(j.id = :qid OR u.telegram_id = :qtg)';
            $params['qid'] = (int) $q;
            $params['qtg'] = (int) $q;
        }
        $w = implode(' AND ', $where);
        $total = (int) $this->db->value("SELECT COUNT(*) FROM generation_jobs j LEFT JOIN users u ON u.id = j.user_id WHERE $w", $params);
        $perPage = max(1, min(200, $perPage));
        $params['lim'] = $perPage;
        $params['off'] = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT j.id, j.user_id, j.project_id, j.type, j.status, j.priority, j.total_items, j.done_items, j.failed_items,
                    j.progress, j.progress_text, j.attempts, j.max_attempts, j.cancel_requested, j.available_at, j.locked_by,
                    j.heartbeat_at, j.started_at, j.finished_at, j.error_message, j.chat_id, j.created_at, j.updated_at,
                    TIMESTAMPDIFF(SECOND, j.started_at, COALESCE(j.finished_at, NOW())) AS duration_s,
                    u.telegram_id, u.username, u.first_name
             FROM generation_jobs j LEFT JOIN users u ON u.id = j.user_id
             WHERE $w ORDER BY j.id DESC LIMIT :lim OFFSET :off",
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed> counts by status/type, avg duration, last finished, queue health */
    public function stats(): array
    {
        $byStatus = array_fill_keys(self::STATUSES, 0);
        foreach ($this->db->fetchAll('SELECT status, COUNT(*) c FROM generation_jobs GROUP BY status') as $r) {
            $byStatus[(string) $r['status']] = (int) $r['c'];
        }
        $byType = [];
        foreach ($this->db->fetchAll('SELECT type, status, COUNT(*) c FROM generation_jobs GROUP BY type, status') as $r) {
            $byType[(string) $r['type']][(string) $r['status']] = (int) $r['c'];
        }
        $avg = $this->db->fetch(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) a, COUNT(*) c FROM generation_jobs
             WHERE status = 'completed' AND type = 'generate' AND finished_at > (NOW() - INTERVAL 1 DAY)"
        );
        $avgAll = $this->db->value(
            "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, finished_at)) FROM generation_jobs
             WHERE status = 'completed' AND finished_at > (NOW() - INTERVAL 1 DAY)"
        );
        $today = $this->db->fetch(
            "SELECT SUM(status = 'completed') done, SUM(status = 'failed') failed FROM generation_jobs WHERE finished_at >= CURDATE()"
        );
        return [
            'by_status' => $byStatus,
            'by_type' => $byType,
            'avg_duration' => $avg && $avg['a'] !== null ? round((float) $avg['a'], 1) : null,
            'avg_duration_all' => $avgAll !== null ? round((float) $avgAll, 1) : null,
            'generated_24h' => (int) ($avg['c'] ?? 0),
            'completed_today' => (int) ($today['done'] ?? 0),
            'failed_today' => (int) ($today['failed'] ?? 0),
            'last_finished' => $this->db->value('SELECT MAX(finished_at) FROM generation_jobs'),
            'oldest_queued_seconds' => (int) ($this->db->value(
                "SELECT TIMESTAMPDIFF(SECOND, MIN(available_at), NOW()) FROM generation_jobs WHERE status = 'queued' AND available_at <= NOW()"
            ) ?? 0),
            'stale_running' => (int) $this->db->value(
                "SELECT COUNT(*) FROM generation_jobs WHERE status = 'running' AND COALESCE(heartbeat_at, locked_at) < (NOW() - INTERVAL 5 MINUTE)"
            ),
        ];
    }

    /**
     * Deletes finished jobs older than $days whose outputs are already gone
     * (generated_files rows cascade with their job, so jobs that still own
     * live files are kept until the output retention removed them).
     */
    public function purgeFinished(int $days): int
    {
        $days = max(1, $days);
        $total = 0;
        for ($i = 0; $i < 100; $i++) {
            $n = $this->db->query(
                "DELETE FROM generation_jobs
                 WHERE status IN ('completed','failed','cancelled') AND finished_at IS NOT NULL
                   AND finished_at < (NOW() - INTERVAL ? DAY)
                   AND NOT EXISTS (SELECT 1 FROM generated_files f WHERE f.job_id = generation_jobs.id AND f.deleted_at IS NULL)
                 LIMIT 1000",
                [$days]
            )->rowCount();
            $total += $n;
            if ($n < 1000) {
                break;
            }
        }
        if ($total > 0) {
            $this->logger->info('Finished jobs purged', ['count' => $total, 'days' => $days]);
        }
        return $total;
    }
}

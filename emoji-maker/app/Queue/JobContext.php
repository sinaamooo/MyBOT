<?php

declare(strict_types=1);

namespace EmojiMaker\Queue;

use EmojiMaker\App;
use EmojiMaker\Delivery\OutputSender;

/**
 * Everything a handler needs while running one job: payload, user / chat,
 * progress reporting (DB + a throttled Telegram progress message with a
 * Persian progress bar and a cancel button), heartbeats, cancellation
 * checks and a private temporary work directory that the worker removes
 * after the job.
 */
final class JobContext
{
    private const EDIT_INTERVAL = 3.0;
    private const HEARTBEAT_INTERVAL = 5.0;
    private const CANCEL_CHECK_INTERVAL = 1.0;

    private ?array $payload = null;
    private ?array $user = null;
    private bool $userLoaded = false;
    private ?string $workDir = null;
    private ?OutputSender $sender = null;
    private float $lastEdit = 0.0;
    private float $lastHeartbeat = 0.0;
    private float $lastCancelCheck = 0.0;
    private string $lastProgressText = '';

    /**
     * @param array<string,mixed> $job generation_jobs row
     * @param (\Closure():void)|null $onHeartbeat called with every heartbeat (worker liveness file)
     * @param (\Closure():bool)|null $shouldStop true when the worker is shutting down
     */
    public function __construct(
        private App $app,
        private JobQueue $queue,
        private array $job,
        private ?\Closure $onHeartbeat = null,
        private ?\Closure $shouldStop = null,
    ) {
        $this->lastHeartbeat = microtime(true);
    }

    /** @return array<string,mixed> */
    public function job(): array
    {
        return $this->job;
    }

    public function id(): int
    {
        return (int) $this->job['id'];
    }

    public function type(): string
    {
        return (string) $this->job['type'];
    }

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return $this->payload ??= JobQueue::payload($this->job);
    }

    public function userId(): ?int
    {
        return isset($this->job['user_id']) ? (int) $this->job['user_id'] : null;
    }

    public function projectId(): ?int
    {
        return isset($this->job['project_id']) ? (int) $this->job['project_id'] : null;
    }

    public function chatId(): ?int
    {
        return isset($this->job['chat_id']) && (int) $this->job['chat_id'] !== 0 ? (int) $this->job['chat_id'] : null;
    }

    public function app(): App
    {
        return $this->app;
    }

    public function queue(): JobQueue
    {
        return $this->queue;
    }

    /** users row of the job owner (null for system jobs). @return array<string,mixed>|null */
    public function user(): ?array
    {
        if (!$this->userLoaded) {
            $this->userLoaded = true;
            $this->user = $this->userId() !== null ? $this->app->users()->find($this->userId()) : null;
        }
        return $this->user;
    }

    /** Sender bound to the job's chat (sends nothing when the job has no chat). */
    public function sender(): OutputSender
    {
        return $this->sender ??= new OutputSender($this->app, $this->chatId());
    }

    public function progressMessageId(): ?int
    {
        $id = (int) ($this->job['progress_message_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    /**
     * Progress for DB + throttled (>= 3 s) edit of the Telegram progress
     * message with a Persian bar. The final step (done == total) is always shown.
     */
    public function progress(int $done, int $total, string $text = '', int $failed = 0): void
    {
        $this->queue->progress($this->id(), $done, $total, $text !== '' ? $text : null, $failed);
        $this->touch();
        if ($this->chatId() === null) {
            return;
        }
        $now = microtime(true);
        $final = $total > 0 && $done >= $total;
        if (!$final && $now - $this->lastEdit < self::EDIT_INTERVAL) {
            return;
        }
        $message = self::progressText($done, $total, $text, $failed);
        if ($message === $this->lastProgressText) {
            return;
        }
        $this->lastEdit = $now;
        $this->lastProgressText = $message;
        $old = $this->progressMessageId();
        $id = $this->sender()->progress($old, $message, OutputSender::cancelKeyboard($this->id()));
        if ($id !== null && $id !== $old) {
            $this->queue->setProgressMessage($this->id(), (int) $this->chatId(), $id);
            $this->job['progress_message_id'] = $id;
        }
    }

    /** Removes the progress message (results follow as new messages). */
    public function clearProgress(): void
    {
        $id = $this->progressMessageId();
        if ($id !== null) {
            $this->sender()->delete($id);
            $this->job['progress_message_id'] = null;
        }
    }

    /** Persian progress text: title, bar, counters. */
    public static function progressText(int $done, int $total, string $text = '', int $failed = 0): string
    {
        $pct = $total > 0 ? (int) min(100, floor($done * 100 / $total)) : 0;
        $filled = (int) round($pct / 10);
        $bar = str_repeat('▰', $filled) . str_repeat('▱', 10 - $filled);
        $lines = ['⏳ ' . ($text !== '' ? $text : 'در حال ساخت…')];
        $lines[] = $bar . ' ' . OutputSender::fa($pct) . '٪';
        if ($total > 1) {
            $line = OutputSender::fa($done) . ' از ' . OutputSender::fa($total) . ' انجام شد';
            if ($failed > 0) {
                $line .= ' (' . OutputSender::fa($failed) . ' ناموفق)';
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    public function heartbeat(): void
    {
        if (microtime(true) - $this->lastHeartbeat < self::HEARTBEAT_INTERVAL) {
            return;
        }
        $this->queue->heartbeat($this->id());
        $this->touch();
    }

    private function touch(): void
    {
        $this->lastHeartbeat = microtime(true);
        if ($this->onHeartbeat !== null) {
            ($this->onHeartbeat)();
        }
    }

    /** Throws JobCancelled when the user / admin cancelled (or the worker stops). Call between items. */
    public function checkCancelled(): void
    {
        $this->heartbeat();
        if ($this->shouldStop !== null && ($this->shouldStop)()) {
            throw new JobCancelled('Worker is shutting down');
        }
        $now = microtime(true);
        if ($now - $this->lastCancelCheck < self::CANCEL_CHECK_INTERVAL) {
            return;
        }
        $this->lastCancelCheck = $now;
        if ($this->queue->isCancelRequested($this->id())) {
            throw new JobCancelled('Cancelled by request');
        }
    }

    /** Private temp dir for this job (storage tmp), removed after the job. */
    public function workDir(): string
    {
        return $this->workDir ??= $this->app->storage()->makeTempDir('job' . $this->id());
    }

    public function cleanup(): void
    {
        if ($this->workDir !== null) {
            $this->app->storage()->removeDir($this->workDir);
            $this->workDir = null;
        }
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Queue;

use EmojiMaker\App;
use EmojiMaker\Delivery\OutputSender;
use EmojiMaker\Maintenance\Cleanup;
use EmojiMaker\Queue\Handlers\DeliverHandler;
use EmojiMaker\Queue\Handlers\GenerateHandler;
use EmojiMaker\Queue\Handlers\MaintenanceHandler;
use EmojiMaker\Queue\Handlers\PreviewHandler;
use EmojiMaker\Queue\Handlers\PublishHandler;
use EmojiMaker\Queue\Handlers\UpdateHandler;
use EmojiMaker\Security\Redactor;

/**
 * Job worker loop.
 *
 *   once    (cron, shared hosting) works until the time limit or until the
 *           queue is empty, then exits
 *   daemon  (systemd / supervisor) runs forever, sleeps when idle, stops
 *           gracefully on SIGTERM / SIGINT (a running job is released back to
 *           the queue unless it finishes), exits after max jobs / memory so the
 *           supervisor restarts a fresh process
 *
 * Every loop also recovers stale jobs, drains updates stored in queue mode
 * and refreshes storage/cache/worker.json (shown on the admin dashboard).
 */
final class Worker
{
    public const VERSION = '1.0';

    private bool $stopping = false;
    private int $jobsDone = 0;
    private float $startedAt;
    private string $id;
    private float $lastBeat = 0.0;
    private float $lastRecovery = 0.0;
    /** @var array<string,JobHandler> */
    private array $handlers = [];

    /**
     * @param array{once?:bool,time_limit?:int,max_jobs?:int,memory_mb?:int,sleep?:float,types?:list<string>|null,maintenance?:bool} $opts
     */
    public function __construct(private App $app, private array $opts = [])
    {
        $this->startedAt = microtime(true);
        $this->id = substr((gethostname() ?: 'host') . ':' . getmypid(), 0, 64);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function stop(): void
    {
        $this->stopping = true;
    }

    public function jobsDone(): int
    {
        return $this->jobsDone;
    }

    /** Registers SIGTERM / SIGINT handlers when pcntl is available. */
    public function installSignals(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function (): void {
            $this->stopping = true;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }

    /** Runs the loop; returns the process exit code. */
    public function run(): int
    {
        $once = (bool) ($this->opts['once'] ?? true);
        $limit = (int) ($this->opts['time_limit'] ?? ($once ? 55 : 0));
        $maxJobs = (int) ($this->opts['max_jobs'] ?? 0);
        $memoryLimit = max(64, (int) ($this->opts['memory_mb'] ?? 400)) * 1024 * 1024;
        $sleep = max(0.2, (float) ($this->opts['sleep'] ?? 1.0));
        $this->app->storage()->ensureStructure();
        $this->beat('started');
        while (!$this->stopping) {
            if ($limit > 0 && microtime(true) - $this->startedAt >= $limit) {
                break;
            }
            $this->housekeeping();
            $worked = $this->tick();
            if ($maxJobs > 0 && $this->jobsDone >= $maxJobs) {
                break;
            }
            if (memory_get_usage(true) > $memoryLimit) {
                $this->app->logger('worker')->notice('Memory limit reached, exiting for a fresh process', ['mb' => round(memory_get_usage(true) / 1048576)]);
                break;
            }
            if (!$worked) {
                if ($once) {
                    break;
                }
                usleep((int) ($sleep * 1_000_000));
            }
        }
        $this->beat('stopped');
        return 0;
    }

    /** One step: drain queued updates, claim and run one job. True when something was done. */
    public function tick(): bool
    {
        $worked = false;
        if ($this->app->config()->get('telegram.webhook_mode') === 'queue') {
            $worked = UpdateHandler::drain($this->app, 20) > 0;
        }
        $maxRunning = max(1, (int) $this->app->config()->get('worker.max_parallel', 2));
        $job = $this->app->queue()->claim($this->id, $maxRunning, $this->opts['types'] ?? null);
        if ($job === null) {
            return $worked;
        }
        $this->runJob($job);
        $this->jobsDone++;
        return true;
    }

    /** Runs one claimed job with full error handling. */
    public function runJob(array $job): void
    {
        $queue = $this->app->queue();
        $id = (int) $job['id'];
        $log = $this->app->logger('worker');
        $ctx = new JobContext(
            $this->app,
            $queue,
            $job,
            fn () => $this->beat('working', $id),
            fn (): bool => $this->stopping,
        );
        $t0 = microtime(true);
        try {
            $result = $this->handler((string) $job['type'])->handle($ctx);
            $queue->complete($id, $result + ['seconds' => round(microtime(true) - $t0, 2)]);
        } catch (JobCancelled $e) {
            if ($this->stopping && !$queue->isCancelRequested($id)) {
                // worker shutdown, not a user request: run it again later
                $queue->release($id, 5);
            } else {
                $queue->markCancelled($id);
                $this->notify($ctx, $job, null);
            }
        } catch (JobFailed $e) {
            $queue->fail($id, $e->getMessage(), $e->retryable);
            $this->notifyIfFinal($ctx, $job, $e->getMessage());
        } catch (\Throwable $e) {
            $log->exception($e, 'Job crashed', ['job' => $id, 'type' => $job['type']]);
            $queue->fail($id, get_class($e) . ': ' . $e->getMessage(), true);
            $this->notifyIfFinal($ctx, $job, GenerateHandler::userMessage($e));
        } finally {
            $ctx->cleanup();
            gc_collect_cycles();
            $this->beat('idle');
        }
    }

    private function notifyIfFinal(JobContext $ctx, array $job, string $message): void
    {
        $now = $this->app->queue()->find((int) $job['id']);
        if ($now !== null && $now['status'] === 'failed') {
            $this->notify($ctx, $job, $message);
        }
    }

    /** Tells the user that the job failed ($message) or was cancelled (null). */
    private function notify(JobContext $ctx, array $job, ?string $message): void
    {
        if (in_array($job['type'], JobQueue::LIGHT_TYPES, true) || empty($job['chat_id'])) {
            return;
        }
        try {
            $sender = $ctx->sender();
            $message === null ? $sender->jobCancelled($job) : $sender->jobFailed($job, $message);
        } catch (\Throwable $e) {
            $this->app->logger('worker')->warning('Could not notify user', ['job' => (int) $job['id'], 'error' => Redactor::text($e->getMessage())]);
        }
    }

    public function handler(string $type): JobHandler
    {
        return $this->handlers[$type] ??= match ($type) {
            'preview' => new PreviewHandler(),
            'generate' => new GenerateHandler(),
            'publish' => new PublishHandler(),
            'deliver' => new DeliverHandler(),
            'update' => new UpdateHandler(),
            'maintenance' => new MaintenanceHandler(),
            default => throw new JobFailed('Unknown job type'),
        };
    }

    /** Stale job recovery every 30 s; hourly cleanup when enabled (cron mode). */
    private function housekeeping(): void
    {
        $now = microtime(true);
        if ($now - $this->lastRecovery < 30) {
            return;
        }
        $this->lastRecovery = $now;
        $stale = (int) $this->app->config()->get('worker.stale_after', 600);
        foreach ($this->app->queue()->recoverStaleJobs($stale) as $r) {
            if ($r['status'] !== 'queued' && !empty($r['job']['chat_id']) && !in_array($r['job']['type'], JobQueue::LIGHT_TYPES, true)) {
                try {
                    $sender = new OutputSender($this->app, (int) $r['job']['chat_id']);
                    $r['status'] === 'cancelled'
                        ? $sender->jobCancelled($r['job'])
                        : $sender->jobFailed($r['job'], 'ساخت متوقف شد و پس از چند تلاش انجام نشد. لطفاً دوباره امتحان کنید.');
                } catch (\Throwable) {
                    // notification is best effort
                }
            }
        }
        if (!empty($this->opts['maintenance'])) {
            $this->hourlyCleanup();
        }
    }

    /** Runs the cleanup at most once per hour across all worker processes. */
    public function hourlyCleanup(): void
    {
        $stamp = $this->app->storage()->root() . '/cache/last_cleanup';
        $last = is_file($stamp) ? (int) file_get_contents($stamp) : 0;
        if (time() - $last < 3600) {
            return;
        }
        $lock = $this->app->storage()->root() . '/locks/cleanup.lock';
        $fh = @fopen($lock, 'c');
        if ($fh === false || !flock($fh, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            file_put_contents($stamp, (string) time());
            (new Cleanup($this->app))->run();
        } catch (\Throwable $e) {
            $this->app->logger('worker')->exception($e, 'Cleanup failed');
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** Liveness file for the admin dashboard (throttled). */
    private function beat(string $state, ?int $jobId = null): void
    {
        $now = microtime(true);
        if ($state === 'working' && $now - $this->lastBeat < 5) {
            return;
        }
        $this->lastBeat = $now;
        $file = $this->app->storage()->root() . '/cache/worker.json';
        $data = [
            'pid' => getmypid(),
            'worker' => $this->id,
            'state' => $state,
            'job' => $jobId,
            'at' => time(),
            'started' => (int) $this->startedAt,
            'jobs_done' => $this->jobsDone,
            'mode' => ($this->opts['once'] ?? true) ? 'cron' : 'daemon',
            'version' => self::VERSION,
            'php' => PHP_VERSION,
        ];
        @file_put_contents($file . '.tmp', (string) json_encode($data));
        @rename($file . '.tmp', $file);
    }

    /** @return array<string,mixed>|null last heartbeat written by any worker */
    public static function lastHeartbeat(App $app): ?array
    {
        $file = $app->storage()->root() . '/cache/worker.json';
        if (!is_file($file)) {
            return null;
        }
        $d = json_decode((string) file_get_contents($file), true);
        return is_array($d) ? $d : null;
    }
}

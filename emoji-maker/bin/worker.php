<?php

declare(strict_types=1);

/*
 * Job worker.
 *
 *   php bin/worker.php --once [--time-limit=55]          cron / shared hosting
 *   php bin/worker.php --daemon [--slots=2] [--max-jobs=200] [--memory=400]
 *                                                        systemd / supervisor
 *   php bin/worker.php --daemon --types=update,preview   only some job types
 *
 * With --slots=N (daemon) the process supervises N child workers (restarted
 * when they exit) instead of working itself; without proc_open it falls back
 * to a single worker.
 */

use EmojiMaker\App;
use EmojiMaker\Process\ProcessRunner;
use EmojiMaker\Queue\JobQueue;
use EmojiMaker\Queue\Worker;

if (PHP_SAPI !== 'cli') {
    exit(1);
}
require dirname(__DIR__) . '/app/autoload.php';

$opts = getopt('', ['once', 'daemon', 'time-limit:', 'max-jobs:', 'memory:', 'slots:', 'types:', 'child', 'help']);
if (isset($opts['help'])) {
    $src = (string) file_get_contents(__FILE__);
    preg_match('~/\*(.*?)\*/~s', $src, $m);
    echo trim($m[1] ?? ''), "\n";
    exit(0);
}
$app = App::boot();
$app->registerErrorHandlers(true);
@ini_set('memory_limit', (string) $app->config()->get('worker.memory_limit', '512M'));
if (!$app->dbAvailable()) {
    fwrite(STDERR, "Database is not reachable - check .env / run bin/install.php\n");
    exit(2);
}

$daemon = isset($opts['daemon']);
$slots = max(1, min(16, (int) ($opts['slots'] ?? 1)));
$types = isset($opts['types']) ? array_values(array_intersect(JobQueue::TYPES, array_map('trim', explode(',', (string) $opts['types'])))) : null;

if ($daemon && $slots > 1 && !isset($opts['child']) && ProcessRunner::available()) {
    exit(supervise($app, $slots, $opts));
}

$worker = new Worker($app, [
    'once' => !$daemon,
    'time_limit' => (int) ($opts['time-limit'] ?? ($daemon ? 0 : 55)),
    'max_jobs' => (int) ($opts['max-jobs'] ?? ($daemon ? 500 : 0)),
    'memory_mb' => (int) ($opts['memory'] ?? 400),
    'types' => $types ?: null,
    'maintenance' => !$daemon || !isset($opts['child']),
]);
$worker->installSignals();
exit($worker->run());

/** Keeps $slots child workers running until SIGTERM. */
function supervise(App $app, int $slots, array $opts): int
{
    $stop = false;
    if (function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function () use (&$stop): void {
            $stop = true;
        });
        pcntl_signal(SIGINT, static function () use (&$stop): void {
            $stop = true;
        });
    }
    $args = [PHP_BINARY, __FILE__, '--daemon', '--child'];
    foreach (['max-jobs', 'memory', 'types'] as $k) {
        if (isset($opts[$k])) {
            $args[] = '--' . $k . '=' . $opts[$k];
        }
    }
    $children = [];
    $log = $app->logger('worker');
    while (!$stop) {
        for ($i = 0; $i < $slots; $i++) {
            $alive = isset($children[$i]) && (proc_get_status($children[$i])['running'] ?? false);
            if (!$alive) {
                if (isset($children[$i])) {
                    proc_close($children[$i]);
                }
                $proc = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
                if (is_resource($proc)) {
                    $children[$i] = $proc;
                    $log->info('Worker slot started', ['slot' => $i, 'pid' => proc_get_status($proc)['pid'] ?? null]);
                }
            }
        }
        sleep(2);
    }
    foreach ($children as $proc) {
        $status = proc_get_status($proc);
        if ($status['running'] ?? false) {
            proc_terminate($proc, 15);
        }
    }
    $deadline = time() + 60;
    foreach ($children as $proc) {
        while ((proc_get_status($proc)['running'] ?? false) && time() < $deadline) {
            usleep(200_000);
        }
        proc_close($proc);
    }
    return 0;
}

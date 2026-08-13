<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Single-instance guard for the cron entrypoints. cPanel Cron Jobs fire on
 * a fixed schedule regardless of whether the previous run finished - a slow
 * pass (many active signals, MEXC rate-limit backoff, a temporary network
 * stall) can overlap with the next tick. Two processes racing on the same
 * still-open signal would both see it as open and both act on it (e.g. a
 * stop-loss hit reported twice), so every cron entrypoint must hold this
 * lock for its whole run. The OS releases the underlying flock() the
 * instant the process exits for any reason (including a fatal error), so a
 * crashed run can never leave a stale lock behind.
 */
final class CronLock
{
    /** @var resource|null */
    private static $handle = null;

    /** Returns false (caller should exit immediately) if another instance already holds the lock. */
    public static function acquire(string $name): bool
    {
        $dir = dirname(__DIR__, 2) . '/data';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return true; // can't lock - fail open rather than blocking the cron entirely
        }

        $handle = fopen("{$dir}/{$name}.lock", 'c');
        if ($handle === false) {
            return true;
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }

        self::$handle = $handle;
        return true;
    }
}

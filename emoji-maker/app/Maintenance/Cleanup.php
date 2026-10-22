<?php

declare(strict_types=1);

namespace EmojiMaker\Maintenance;

use EmojiMaker\App;

/**
 * Periodic housekeeping (hourly from cron / the worker, or on demand from the
 * admin panel): expired outputs, temporary work directories, orphan files,
 * old update / rate limit / job rows, old logs and admin sessions.
 */
final class Cleanup
{
    public function __construct(private App $app)
    {
    }

    /** @return array<string,mixed> report */
    public function run(): array
    {
        $s = $this->app->settings();
        $storage = $this->app->storage();
        $files = $this->app->files();
        $report = [];
        $retentionDays = max(1, $s->int('output_retention_days', 30));

        // 1. expired outputs (rows soft deleted, files removed)
        $expired = 0;
        $bytes = 0;
        for ($round = 0; $round < 40; $round++) {
            $rows = $files->olderThan($retentionDays, 500);
            if (!$rows) {
                break;
            }
            foreach ($rows as $r) {
                try {
                    $abs = $storage->path((string) $r['path']);
                    $bytes += is_file($abs) ? (int) filesize($abs) : 0;
                } catch (\InvalidArgumentException) {
                    // invalid path in the database: just mark it deleted
                }
                $storage->delete((string) $r['path']);
                $files->softDelete((int) $r['id']);
                $expired++;
            }
        }
        $report['expired_outputs'] = ['files' => $expired, 'bytes' => $bytes];

        // 2. temporary job directories
        $report['tmp'] = $storage->purgeOlderThan('tmp', max(1, $s->int('tmp_retention_hours', 6)) * 3600);

        // 3. orphan generated / preview files (no live row), older than a day
        $live = array_fill_keys($files->livePaths(), true);
        $keep = static fn (string $rel): bool => isset($live[$rel]);
        $report['orphan_generated'] = $storage->purgeOlderThan('generated', 86400, $keep);
        $report['orphan_previews'] = $storage->purgeOlderThan('previews', 86400, $keep);

        // 4. uploads nobody references any more (and old ones after the retention)
        $assets = array_fill_keys($this->app->assets()->allPaths(), true);
        $report['orphan_uploads'] = $storage->purgeOlderThan('uploads', 86400, static fn (string $rel): bool => isset($assets[$rel]));

        // 5. database rows
        $misc = $this->app->misc();
        $report['updates_purged'] = $misc->purgeUpdates(7);
        $misc->purgeRateLimits();
        $report['jobs_purged'] = $this->app->queue()->purgeFinished(max(7, $retentionDays));

        // 6. logs (30 days) and stale admin sessions
        $report['logs'] = $storage->purgeOlderThan('logs', 30 * 86400);
        $lifetime = (int) $this->app->config()->get('admin.session_lifetime', 7200);
        $report['sessions'] = $storage->purgeOlderThan('sessions', max(3600, $lifetime * 2));

        $this->app->logger('maintenance')->info('Cleanup finished', $report);
        return $report;
    }
}

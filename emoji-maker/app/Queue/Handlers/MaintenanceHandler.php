<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\Maintenance\Cleanup;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobFailed;
use EmojiMaker\Queue\JobHandler;

/** Housekeeping tasks queued by the admin panel or the scheduler. */
final class MaintenanceHandler implements JobHandler
{
    public function handle(JobContext $ctx): array
    {
        $task = (string) ($ctx->payload()['task'] ?? 'cleanup');
        return match ($task) {
            'cleanup', 'purge' => (new Cleanup($ctx->app()))->run(),
            'tools' => $ctx->app()->tools()->capabilities(true),
            default => throw new JobFailed('Unknown maintenance task'),
        };
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\Delivery\OutputSender;
use EmojiMaker\Maintenance\Cleanup;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobFailed;
use EmojiMaker\Queue\JobHandler;

/**
 * Housekeeping tasks queued by the admin panel, the bot (/admin) or the
 * scheduler. A job with a chat (queued from the bot) reports the result there.
 */
final class MaintenanceHandler implements JobHandler
{
    public function handle(JobContext $ctx): array
    {
        $task = (string) ($ctx->payload()['task'] ?? 'cleanup');
        $result = match ($task) {
            'cleanup', 'purge' => (new Cleanup($ctx->app()))->run(),
            'tools' => $ctx->app()->tools()->capabilities(true),
            default => throw new JobFailed('Unknown maintenance task'),
        };
        if ($ctx->chatId() !== null && $task !== 'tools') {
            $files = 0;
            $bytes = 0;
            foreach ($result as $part) {
                if (is_array($part)) {
                    $files += (int) ($part['files'] ?? 0);
                    $bytes += (int) ($part['bytes'] ?? 0);
                }
            }
            $ctx->sender()->message('🧹 پاک‌سازی تمام شد: ' . OutputSender::fa($files) . ' فایل ('
                . OutputSender::fa((string) round($bytes / 1048576, 1)) . ' مگابایت) حذف شد.');
        }
        return $result;
    }
}

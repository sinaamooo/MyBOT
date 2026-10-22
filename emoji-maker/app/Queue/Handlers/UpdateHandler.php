<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\App;
use EmojiMaker\Bot\UpdateProcessor;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobHandler;

/**
 * Processes Telegram updates that were stored by the webhook in queue mode
 * (WEBHOOK_MODE=queue). Used both for explicit "update" jobs and by the
 * worker loop, which drains pending updates directly.
 */
final class UpdateHandler implements JobHandler
{
    public function handle(JobContext $ctx): array
    {
        $id = (int) ($ctx->payload()['update_id'] ?? 0);
        $row = $ctx->app()->db()->fetch('SELECT * FROM telegram_updates WHERE update_id = ?', [$id]);
        if ($row === null) {
            return ['update_id' => $id, 'status' => 'missing'];
        }
        return ['update_id' => $id, 'status' => self::processRow($ctx->app(), $row)];
    }

    /** Runs one stored update through the bot and records the outcome. */
    public static function processRow(App $app, array $row): string
    {
        $id = (int) $row['update_id'];
        $update = json_decode((string) $row['payload'], true);
        if (!is_array($update)) {
            $app->misc()->markUpdate($id, 'ignored', 'invalid payload');
            return 'ignored';
        }
        try {
            (new UpdateProcessor($app))->process($update);
            $app->misc()->markUpdate($id, 'processed');
            return 'processed';
        } catch (\Throwable $e) {
            $app->logger('bot')->exception($e, 'Update failed', ['update_id' => $id]);
            $app->misc()->markUpdate($id, 'failed', $e->getMessage());
            return 'failed';
        }
    }

    /** Drains updates stored in queue mode; returns how many were processed. */
    public static function drain(App $app, int $limit = 20): int
    {
        $n = 0;
        foreach ($app->misc()->claimPendingUpdates($limit) as $row) {
            self::processRow($app, $row);
            $n++;
        }
        return $n;
    }
}

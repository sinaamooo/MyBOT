<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\App;
use EmojiMaker\Delivery\DownloadLinks;
use EmojiMaker\Delivery\OutputSender;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobFailed;
use EmojiMaker\Queue\JobHandler;
use EmojiMaker\Queue\JobQueue;

/**
 * "deliver" job: re-sends the outputs of an earlier generate job as the ZIP,
 * as individual files or as a signed download link. A ZIP that is missing
 * (or whose file vanished) is rebuilt from the main files that still exist.
 *
 * Payload: {"generate_job_id": 55, "mode": "zip|files|link"}
 */
final class DeliverHandler implements JobHandler
{
    public function handle(JobContext $ctx): array
    {
        $app = $ctx->app();
        $p = $ctx->payload();
        $mode = in_array($p['mode'] ?? 'zip', ['zip', 'files', 'link'], true) ? (string) ($p['mode'] ?? 'zip') : 'zip';
        $gen = self::generateJob($ctx, (int) ($p['generate_job_id'] ?? 0));
        $genId = (int) $gen['id'];
        $mains = self::existingMains($app, $genId);
        if (!$mains) {
            throw new JobFailed('فایل‌های این ساخت دیگر موجود نیستند (خروجی‌ها پس از '
                . OutputSender::fa($app->settings()->int('output_retention_days', 30)) . ' روز پاک می‌شوند). لطفاً دوباره بسازید.');
        }
        $sender = $ctx->sender();
        $out = ['mode' => $mode, 'generate_job_id' => $genId, 'sent' => 0];
        if ($mode === 'files') {
            $tiles = [];
            foreach ($mains as $row) {
                $tiles[(int) $row['item_index']] = ($tiles[(int) $row['item_index']] ?? 0) + 1;
            }
            foreach (array_slice($mains, 0, OutputSender::MAX_FILES) as $row) {
                $ctx->checkCancelled();
                if ($sender->sendFileRow($row, OutputSender::fileCaption($row, $tiles[(int) $row['item_index']] ?? 1))) {
                    $out['sent']++;
                }
                if ($sender->blocked()) {
                    break;
                }
                usleep(350000);
            }
            if (count($mains) > OutputSender::MAX_FILES) {
                $zip = self::ensureZip($ctx, $gen, $mains);
                $sender->sendZip($zip, '📦 بقیه‌ی فایل‌ها (و همه‌ی خروجی‌ها) در این ZIP هستند.');
                $out['zip_file_id'] = (int) $zip['id'];
            }
        } elseif ($mode === 'zip') {
            $zip = self::ensureZip($ctx, $gen, $mains);
            $out['sent'] = $sender->sendZip($zip) ? 1 : 0;
            $out['zip_file_id'] = (int) $zip['id'];
        } else {
            $zip = self::ensureZip($ctx, $gen, $mains);
            $url = self::link($app, $zip, (int) $gen['user_id']);
            $hours = max(1, $app->settings()->int('download_link_hours', 48));
            $out['sent'] = $sender->sendLink($url, $hours) ? 1 : 0;
            $out['zip_file_id'] = (int) $zip['id'];
            $out['expires_hours'] = $hours;
        }
        if ($sender->nothingDelivered()) {
            throw new JobFailed('ارسال به تلگرام ممکن نشد. کمی بعد دوباره تلاش می‌شود.', true);
        }
        return $out;
    }

    /** Generate job owned by the job's user. @return array<string,mixed> */
    public static function generateJob(JobContext $ctx, int $genId): array
    {
        $gen = $genId > 0 ? $ctx->queue()->find($genId) : null;
        if ($gen === null || $gen['type'] !== 'generate' || ($ctx->userId() !== null && (int) $gen['user_id'] !== $ctx->userId())) {
            throw new JobFailed('این ساخت پیدا نشد.');
        }
        return $gen;
    }

    /** @return list<array<string,mixed>> main rows whose files still exist */
    public static function existingMains(App $app, int $genId): array
    {
        $storage = $app->storage();
        return array_values(array_filter($app->files()->forJob($genId, 'main'), static fn ($r) => $storage->exists((string) $r['path'])));
    }

    /**
     * The job's ZIP row, rebuilt when missing.
     * @param array<string,mixed> $gen
     * @param list<array<string,mixed>> $mains
     * @return array<string,mixed>
     */
    public static function ensureZip(JobContext $ctx, array $gen, array $mains): array
    {
        $app = $ctx->app();
        foreach ($app->files()->forJob((int) $gen['id'], 'zip') as $row) {
            if ($app->storage()->exists((string) $row['path'])) {
                return $row;
            }
        }
        $payload = JobQueue::payload($gen);
        $result = JobQueue::result($gen);
        $failed = is_array($result['failed'] ?? null) ? array_values(array_filter($result['failed'], 'is_array')) : [];
        $failed = array_map(static fn ($f) => [
            'index' => (int) ($f['index'] ?? 0),
            'label' => (string) ($f['label'] ?? ''),
            'error' => (string) ($f['error'] ?? ''),
        ], $failed);
        $kind = in_array($payload['kind'] ?? '', ['design', 'template'], true) ? (string) $payload['kind'] : 'design';
        $format = (string) ($mains[0]['format'] ?? 'webm');
        return GenerateHandler::buildZip($app, $gen, $mains, $failed, $kind, $format, $ctx->workDir());
    }

    /** Signed download link of a file for its owner. */
    public static function link(App $app, array $fileRow, int $userId): string
    {
        $base = $app->url();
        if ($base === '' || !preg_match('#^https?://#i', $base)) {
            throw new JobFailed('لینک دانلود فعلاً در دسترس نیست (آدرس سایت تنظیم نشده است). از «دریافت ZIP» استفاده کنید.');
        }
        $hours = max(1, $app->settings()->int('download_link_hours', 48));
        return (new DownloadLinks($app->crypto(), $base))->make((int) $fileRow['id'], $userId, $hours * 3600);
    }
}

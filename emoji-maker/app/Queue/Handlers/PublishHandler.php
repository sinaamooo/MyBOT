<?php

declare(strict_types=1);

namespace EmojiMaker\Queue\Handlers;

use EmojiMaker\App;
use EmojiMaker\Database\Repositories\UserRepository;
use EmojiMaker\Delivery\OutputSender;
use EmojiMaker\Queue\JobContext;
use EmojiMaker\Queue\JobFailed;
use EmojiMaker\Queue\JobHandler;
use EmojiMaker\Telegram\StickerPublisher;
use EmojiMaker\Telegram\TelegramException;

/**
 * "publish" job: creates (or extends) a Telegram custom emoji set from the
 * generated files with StickerPublisher, records the set in sticker_sets and
 * the custom emoji ids in generated_files, and sends the addemoji link.
 *
 * Retries: the sticker_sets row is created before talking to Telegram and
 * tied to this job, so a retried job finds it, asks Telegram what already
 * exists and only adds the remaining files (StickerPublisher adds in order).
 *
 * Payload: {"generate_job_id", "file_ids": [..] (empty = selected files), "set_name": null|"x_by_bot",
 *           "title", "emoji": "⭐", "needs_repainting": false, "sticker_set_id": null|7}
 */
final class PublishHandler implements JobHandler
{
    private const MAX_SET = 200;

    public function handle(JobContext $ctx): array
    {
        $app = $ctx->app();
        $p = $ctx->payload();
        if (!$app->settings()->bool('allow_publish', true)) {
            throw new JobFailed('انتشار پک ایموجی فعلاً غیرفعال است.');
        }
        $user = $ctx->user();
        if ($user === null || (int) ($user['telegram_id'] ?? 0) <= 0) {
            throw new JobFailed('کاربر این درخواست پیدا نشد.');
        }
        if ((UserRepository::permissions($user)['publish'] ?? true) === false) {
            throw new JobFailed('امکان انتشار پک برای حساب شما فعال نیست.');
        }
        $tgUser = (int) $user['telegram_id'];
        $userId = (int) $user['id'];
        $gen = DeliverHandler::generateJob($ctx, (int) ($p['generate_job_id'] ?? 0));
        $files = $this->files($app, (int) $gen['id'], $userId, (array) ($p['file_ids'] ?? []));
        $skipped = array_values(array_filter($files, static fn ($r) => $r['validation_status'] === 'invalid'));
        $files = array_values(array_filter($files, static fn ($r) => $r['validation_status'] !== 'invalid'));
        if (!$files) {
            throw new JobFailed($skipped ? 'فایل‌های انتخاب‌شده با استاندارد تلگرام سازگار نیستند و قابل انتشار نیستند.' : 'فایلی برای انتشار پیدا نشد (ممکن است پاک شده باشد).');
        }
        $title = trim((string) ($p['title'] ?? ''));
        $title = $title !== '' ? mb_substr($title, 0, 64) : 'Emoji Pack';
        $emoji = self::emojiList((string) ($p['emoji'] ?? ''), $app->settings()->string('publish_default_emoji', '⭐'));
        $repaint = !empty($p['needs_repainting']);
        $publisher = new StickerPublisher($app->telegram(), $app->botUsername(), (array) $app->config()->get('telegram_formats.pack', []));

        [$set, $existing] = $this->targetSet($ctx, $publisher, $p, $userId, $tgUser, $title, $repaint, (int) $gen['id']);
        $setId = (int) $set['id'];
        if ($existing) {
            // never add the same file twice to a set
            $files = array_values(array_filter($files, static fn ($r) => (int) ($r['sticker_set_id'] ?? 0) !== $setId || empty($r['custom_emoji_id'])));
            $present = $this->telegramCount($app, (string) $set['name']);
            $files = $this->skipAlreadyAdded($files, $set, $present, (int) $ctx->id());
            $room = self::MAX_SET - max($present, (int) $set['item_count']);
            if ($room <= 0) {
                throw new JobFailed('این پک به سقف ۲۰۰ ایموجی رسیده است. یک پک جدید بسازید.');
            }
            $files = array_slice($files, 0, $room);
        } else {
            $files = array_slice($files, 0, self::MAX_SET);
        }
        if (!$files) {
            return $this->finish($ctx, $set, $title, 0, [], $skipped, $existing);
        }

        $items = [];
        $storage = $app->storage();
        foreach ($files as $row) {
            $items[] = [
                'path' => $storage->path((string) $row['path']),
                'format' => GenerateHandler::stickerFormat((string) $row['format']),
                'emoji' => $emoji,
                'keywords' => [],
            ];
        }
        $app->misc()->updateStickerSet($setId, ['status' => 'creating', 'last_error' => null]);
        $ctx->progress(0, count($items), 'در حال ساخت پک ایموجی…');
        try {
            $res = $publisher->publish(
                $tgUser,
                (string) $set['name'],
                $title,
                $items,
                $repaint,
                function (int $done, int $total) use ($ctx): void {
                    $ctx->progress($done, $total, 'در حال افزودن ایموجی‌ها به پک…');
                },
                $existing,
            );
        } catch (TelegramException $e) {
            $app->misc()->updateStickerSet($setId, ['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            $code = $e->errorCode();
            throw new JobFailed(self::telegramMessage($e), $code === 0 || $code === 429 || $code >= 500, $e);
        }

        $failedIdx = [];
        foreach ((array) ($res['failed'] ?? []) as $f) {
            if (is_array($f) && isset($f[0])) {
                $failedIdx[(int) $f[0]] = (string) ($f[1] ?? '');
            }
        }
        $ids = (array) ($res['custom_emoji_ids'] ?? []);
        foreach ($files as $i => $row) {
            if (isset($failedIdx[$i])) {
                continue;
            }
            $app->files()->setTelegramIds((int) $row['id'], null, isset($ids[$i]) ? (string) $ids[$i] : null, $setId);
        }
        $added = (int) ($res['added'] ?? (count($files) - count($failedIdx)));
        $app->db()->query('UPDATE sticker_sets SET item_count = item_count + ? WHERE id = ?', [$added, $setId]);
        $app->misc()->audit('user', $userId, 'sticker_set.publish', 'sticker_set', $setId, [
            'name' => $set['name'], 'added' => $added, 'failed' => count($failedIdx), 'existing' => $existing,
        ]);
        $failedList = [];
        foreach ($failedIdx as $i => $reason) {
            $failedList[] = ['file_id' => (int) $files[$i]['id'], 'number' => (int) $files[$i]['item_index'] + 1, 'reason' => $reason];
        }
        return $this->finish($ctx, $set, $title, $added, $failedList, $skipped, $existing);
    }

    /**
     * Main files of the generate job owned by the user, in item order.
     * @param list<mixed> $fileIds
     * @return list<array<string,mixed>>
     */
    private function files(App $app, int $genId, int $userId, array $fileIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', array_filter($fileIds, 'is_numeric')), static fn ($v) => $v > 0)));
        $rows = $app->files()->forJob($genId, 'main', !$ids);
        $want = array_flip($ids);
        $storage = $app->storage();
        return array_values(array_filter($rows, static fn ($r) => (int) $r['user_id'] === $userId
            && (!$ids || isset($want[(int) $r['id']]))
            && $storage->exists((string) $r['path'])));
    }

    /**
     * Existing set of the user, the set a previous attempt of this job
     * created, or a new pending row with a valid unique name.
     * @return array{0:array<string,mixed>,1:bool} [sticker_sets row, set exists on Telegram]
     */
    private function targetSet(JobContext $ctx, StickerPublisher $publisher, array $p, int $userId, int $tgUser, string $title, bool $repaint, int $genId): array
    {
        $app = $ctx->app();
        $misc = $app->misc();
        if (!empty($p['sticker_set_id'])) {
            $set = $misc->stickerSetForUser((int) $p['sticker_set_id'], $userId);
            if ($set === null || $set['status'] === 'deleted') {
                throw new JobFailed('پک انتخاب‌شده پیدا نشد.');
            }
            return [$set, true];
        }
        $previous = $app->db()->fetch(
            "SELECT * FROM sticker_sets WHERE job_id = ? AND user_id = ? AND status <> 'deleted' ORDER BY id DESC LIMIT 1",
            [$ctx->id(), $userId]
        );
        if ($previous !== null) {
            return [$previous, $previous['status'] === 'published' || $this->telegramCount($app, (string) $previous['name']) > 0];
        }
        $bot = strtolower($app->botUsername());
        $name = is_string($p['set_name'] ?? null) ? trim($p['set_name']) : '';
        $valid = $name !== '' && strlen($name) <= 64 && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)
            && !str_contains($name, '__') && str_ends_with(strtolower($name), '_by_' . $bot);
        if (!$valid) {
            $name = $publisher->makeName(self::nameBase($title, $genId), $tgUser);
        }
        for ($i = 0; $misc->stickerSetNameExists($name) && $i < 5; $i++) {
            $name = $publisher->makeName(self::nameBase($title, $genId) . substr(bin2hex(random_bytes(2)), 0, 3), $tgUser);
        }
        if ($misc->stickerSetNameExists($name)) {
            throw new JobFailed('ساخت نام یکتا برای پک ممکن نشد؛ لطفاً نام دیگری انتخاب کنید.');
        }
        $id = $misc->createStickerSet([
            'user_id' => $userId,
            'project_id' => $ctx->projectId(),
            'job_id' => $ctx->id(),
            'name' => $name,
            'title' => $title,
            'sticker_type' => 'custom_emoji',
            'needs_repainting' => $repaint ? 1 : 0,
            'item_count' => 0,
            'status' => 'pending',
        ]);
        return [(array) $misc->stickerSetForUser($id, $userId), false];
    }

    /** Stickers currently in the set on Telegram (0 when it does not exist). */
    private function telegramCount(App $app, string $name): int
    {
        try {
            $set = $app->telegram()->getStickerSet($name);
            return count((array) ($set['stickers'] ?? []));
        } catch (TelegramException) {
            return 0;
        }
    }

    /**
     * A retried job: files this job already added (in order) before it was
     * interrupted are skipped.
     * @param list<array<string,mixed>> $files
     * @return list<array<string,mixed>>
     */
    private function skipAlreadyAdded(array $files, array $set, int $present, int $jobId): array
    {
        if ((int) ($set['job_id'] ?? 0) !== $jobId || $set['status'] === 'published') {
            return $files;
        }
        $known = (int) $set['item_count'];
        $lost = max(0, $present - $known);
        return array_slice($files, $lost);
    }

    /** ASCII base for a set name from the title (Persian titles fall back to "emoji<job>"). */
    private static function nameBase(string $title, int $genId): string
    {
        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $title));
        $base = trim($base, '_');
        if ($base === '' || !preg_match('/^[a-z]/', $base)) {
            $base = 'emoji' . $genId;
        }
        return substr($base, 0, 24);
    }

    /** @return list<string> 1..20 emoji */
    public static function emojiList(string $emoji, string $default): array
    {
        $parts = preg_split('/[\s,،]+/u', trim($emoji), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // letters and markup never; digits, # and * only as keycaps (1️⃣)
        $parts = array_values(array_filter($parts, static fn ($e) => strlen($e) <= 32 && !preg_match('/[a-zA-Z<>]|[0-9#*](?!\x{FE0F}?\x{20E3})/u', $e)));
        if (!$parts) {
            $parts = [trim($default) !== '' ? trim($default) : '⭐'];
        }
        return array_slice(array_values(array_unique($parts)), 0, 20);
    }

    public static function telegramMessage(TelegramException $e): string
    {
        $d = strtoupper($e->getMessage());
        return match (true) {
            str_contains($d, 'OCCUPIED') => 'این نام پک قبلاً گرفته شده است؛ نام دیگری انتخاب کنید.',
            str_contains($d, 'PEER_ID_INVALID'), str_contains($d, 'USER_NOT_FOUND'), str_contains($d, 'CHAT NOT FOUND') => 'تلگرام حساب شما را پیدا نکرد. ابتدا ربات را استارت کنید و دوباره تلاش کنید.',
            str_contains($d, 'STICKERS_TOO_MUCH'), str_contains($d, 'TOO MUCH') => 'این پک به سقف تعداد ایموجی رسیده است.',
            str_contains($d, 'STICKERSET_INVALID') => 'پک موردنظر در تلگرام پیدا نشد.',
            str_contains($d, 'STICKER_') || str_contains($d, 'FILE') => 'تلگرام فایل‌ها را نپذیرفت: ' . mb_substr($e->getMessage(), 0, 150),
            $e->errorCode() === 0 || $e->errorCode() >= 500 => 'ارتباط با تلگرام برقرار نشد؛ کمی بعد دوباره تلاش می‌شود.',
            default => 'تلگرام ساخت پک را نپذیرفت: ' . mb_substr($e->getMessage(), 0, 150),
        };
    }

    /**
     * @param list<array<string,mixed>> $failed
     * @param list<array<string,mixed>> $skipped
     * @return array<string,mixed>
     */
    private function finish(JobContext $ctx, array $set, string $title, int $added, array $failed, array $skipped, bool $existing): array
    {
        $app = $ctx->app();
        $name = (string) $set['name'];
        $link = 'https://t.me/addemoji/' . $name;
        $status = $added > 0 || $existing ? 'published' : 'failed';
        $app->misc()->updateStickerSet((int) $set['id'], [
            'status' => $status,
            'last_error' => $failed ? mb_substr((string) json_encode($failed, JSON_UNESCAPED_UNICODE), 0, 1000) : null,
        ]);
        if ($status === 'failed') {
            throw new JobFailed('هیچ ایموجی‌ای به پک اضافه نشد' . ($failed ? ': ' . mb_substr((string) $failed[0]['reason'], 0, 150) : '.'));
        }
        $lines = [$existing ? '🎉 ایموجی‌ها به پک شما اضافه شدند!' : '🎉 پک ایموجی شما منتشر شد!'];
        $lines[] = '«' . ($existing ? (string) $set['title'] : $title) . '»';
        $lines[] = $added > 0 || !$existing
            ? OutputSender::fa($added) . ' ایموجی اضافه شد.'
            : 'ایموجی‌های انتخاب‌شده از قبل در این پک بودند؛ چیزی اضافه نشد.';
        if ($failed) {
            $nums = array_map(static fn ($f) => OutputSender::fa((int) $f['number']), array_slice($failed, 0, 10));
            $lines[] = '⚠️ ' . OutputSender::fa(count($failed)) . ' مورد را تلگرام نپذیرفت (شماره‌ی ' . implode('، ', $nums) . ').';
        }
        if ($skipped) {
            $lines[] = '⚠️ ' . OutputSender::fa(count($skipped)) . ' فایل ناسازگار با استاندارد تلگرام کنار گذاشته شد.';
        }
        $lines[] = '';
        $lines[] = 'لینک افزودن پک:';
        $lines[] = $link;
        $lines[] = 'برای استفاده از ایموجی سفارشی در پیام‌ها تلگرام پریمیوم لازم است.';
        $ctx->clearProgress();
        $ctx->sender()->message(implode("\n", $lines), OutputSender::keyboard([[OutputSender::urlButton('➕ افزودن پک ایموجی', $link)]]));
        return [
            'sticker_set_id' => (int) $set['id'],
            'name' => $name,
            'link' => $link,
            'added' => $added,
            'failed' => $failed,
            'skipped_invalid' => count($skipped),
            'existing' => $existing,
        ];
    }
}

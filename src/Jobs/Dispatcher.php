<?php
declare(strict_types=1);

namespace Nikto\Jobs;

use Nikto\Core\Db;
use Nikto\Core\Http;
use Nikto\Core\Log;
use Nikto\Core\Settings;
use Nikto\Telegram\Api;

final class Dispatcher
{
    private static ?Api $api = null;

    public static function setApi(?Api $api): void
    {
        self::$api = $api;
    }

    private static function api(): Api
    {
        return self::$api ??= new Api();
    }

    public static function run(string $jobKey, array $options = []): array
    {
        $job = Registry::refresh($jobKey);
        if ($job === null) {
            return ['ok' => false, 'message' => 'کار ناشناخته: ' . $jobKey, 'sent' => 0, 'failed' => 0];
        }

        $started = microtime(true);
        Http::resetFailures();

        try {
            $data = $job->fetch();
        } catch (\Throwable $e) {
            Log::error('Job fetch threw', ['job' => $jobKey, 'error' => $e->getMessage()]);
            return self::fail($jobKey, $options, 'خطا در دریافت داده: ' . $e->getMessage());
        }

        if ($data === null || $data === []) {
            $empty = $jobKey === CalendarJob::KEY && $data === [];
            if (!$empty) {
                return self::fail($jobKey, $options, self::noDataMessage(), true);
            }
        }

        try {
            $path = $job->card($data)->save();
            $caption = $job->renderCaption($data);
        } catch (\Throwable $e) {
            Log::error('Card render failed', ['job' => $jobKey, 'error' => $e->getMessage()]);
            return self::fail($jobKey, $options, 'خطا در ساخت تصویر: ' . $e->getMessage());
        }

        $targets = $options['chat_ids'] ?? array_map(
            static fn (array $ch) => $ch['chat_id'],
            $job->channels()
        );

        if ($targets === []) {
            return [
                'ok'      => false,
                'message' => 'هیچ کانال مقصدی برای این کار انتخاب نشده است.',
                'sent'    => 0,
                'failed'  => 0,
                'path'    => $path,
            ];
        }

        $api = self::api();
        $asDocument = Settings::get('send_mode') === 'document';
        $sent = 0;
        $failed = 0;
        $errors = [];

        $silent = (bool) ($options['silent'] ?? false);

        foreach ($targets as $chatId) {
            $api->sendChatAction($chatId, $asDocument ? 'upload_document' : 'upload_photo');
            $res = self::deliver($api, $chatId, $path, $caption, $asDocument, $silent);

            if ($res['ok'] ?? false) {
                $sent++;
            } else {
                $failed++;
                $errors[] = $chatId . ': ' . ($res['description'] ?? 'نامشخص');
            }
        }

        $took = round(microtime(true) - $started, 2);
        $message = sprintf('ارسال شد به %d کانال (%s ثانیه)', $sent, $took);
        if ($failed > 0) {
            $message .= sprintf(' — %d خطا: %s', $failed, implode(' | ', array_slice($errors, 0, 3)));
        }

        self::record($jobKey, $options['slot'] ?? null, $failed === 0 ? 'ok' : 'partial', $message);
        Log::info('Job dispatched', ['job' => $jobKey, 'sent' => $sent, 'failed' => $failed, 'took' => $took]);
        self::cleanup();

        return [
            'ok'      => $sent > 0,
            'message' => $message,
            'sent'    => $sent,
            'failed'  => $failed,
            'path'    => $path,
            'retry'   => $sent === 0 && $failed > 0,
        ];
    }

    public static function build(string $jobKey): array
    {
        $job = Registry::refresh($jobKey);
        if ($job === null) {
            return ['ok' => false, 'message' => 'کار ناشناخته'];
        }
        try {
            Http::resetFailures();
            $data = $job->fetch();
            if ($data === null) {
                return ['ok' => false, 'message' => self::noDataMessage()];
            }
            $path = $job->card($data)->save();

            return ['ok' => true, 'path' => $path, 'caption' => $job->renderCaption($data), 'message' => 'ساخته شد'];
        } catch (\Throwable $e) {
            Log::error('Preview failed', ['job' => $jobKey, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'خطا: ' . $e->getMessage()];
        }
    }

    private static function noDataMessage(): string
    {
        $message = 'داده‌ای از سرویس دریافت نشد (اینترنت یا API).';
        $reasons = Http::explainFailures();
        if ($reasons !== '') {
            $message .= "\nعلت:\n" . $reasons;
        }
        if (Http::allRegionBlocked()) {
            $message .= "\n\nهمه‌ی منابع، کشورِ سرورِ ربات را مسدود کرده‌اند. راهِ قطعی: بالای فایل nikto-bot.php مقدارِ "
                . "data_proxy را یک پراکسیِ خارج از این کشور بگذارید (مثلاً socks5h://user:pass@1.2.3.4:1080) "
                . 'یا ربات را روی هاستی در کشورِ دیگر ببرید.';
        }

        return $message;
    }

    private static function deliver(
        Api $api,
        int|string $chatId,
        string $path,
        string $caption,
        bool $asDocument,
        bool $silent
    ): array {
        $extra = ['disable_notification' => $silent];

        return $asDocument
            ? $api->sendDocument($chatId, $path, $caption, $extra)
            : $api->sendPhoto($chatId, $path, $caption, $extra);
    }

    private static function fail(string $jobKey, array $options, string $message, bool $retry = false): array
    {
        self::record($jobKey, $options['slot'] ?? null, 'error', $message);
        Log::error('Job failed', ['job' => $jobKey, 'message' => $message]);

        return ['ok' => false, 'message' => $message, 'sent' => 0, 'failed' => 0, 'retry' => $retry];
    }

    private static function record(string $jobKey, ?string $slot, string $status, string $detail): void
    {
        $slot ??= 'manual-' . Settings::now()->format('Y-m-d H:i:s');
        Db::exec(
            'INSERT INTO runs(job_key, slot, status, detail, created_at) VALUES(:j, :s, :st, :d, :t)
             ON CONFLICT(job_key, slot) DO UPDATE SET status = excluded.status, detail = excluded.detail',
            [':j' => $jobKey, ':s' => $slot, ':st' => $status, ':d' => mb_substr($detail, 0, 500), ':t' => time()]
        );
    }

    private static function cleanup(int $keepHours = 48): void
    {
        $dir = APP_STORAGE . '/cards';
        foreach (glob($dir . '/*.png') ?: [] as $file) {
            if (filemtime($file) < time() - $keepHours * 3600) {
                @unlink($file);
            }
        }
        Db::exec('DELETE FROM runs WHERE created_at < :t', [':t' => time() - 30 * 86400]);
    }
}

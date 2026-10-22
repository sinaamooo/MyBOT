<?php

declare(strict_types=1);

namespace EmojiMaker\Delivery;

use EmojiMaker\App;
use EmojiMaker\Support\Logger;
use EmojiMaker\Telegram\TelegramClient;
use EmojiMaker\Telegram\TelegramException;
use EmojiMaker\Text\Shaping\Unicode;

/**
 * Sends job results to the user's chat: previews, generated files, batch
 * grids, the ZIP, download links, progress / failure notices, with the
 * inline keyboards from the contract.
 *
 * Telegram problems never abort a job: every call is caught and logged.
 * A user who blocked the bot stops all further sends; temporary problems
 * (network, 429, 5xx) are remembered so a handler can retry the delivery
 * (transientFailure()). Without a chat id (admin / test jobs) nothing is
 * sent at all.
 */
final class OutputSender
{
    /** pause between files: Telegram allows about one message per second per chat in bursts */
    private const PACE_US = 350000;
    public const MAX_FILES = 50;
    public const AUTO_FILES = 10;

    private bool $blocked = false;
    private bool $transient = false;
    private int $sent = 0;
    private int $attempted = 0;
    private ?string $lastError = null;
    private ?TelegramClient $client;
    private Logger $logger;

    public function __construct(private App $app, private ?int $chatId, ?TelegramClient $client = null)
    {
        $this->client = $client;
        $this->logger = $app->logger('delivery');
    }

    public function chatId(): ?int
    {
        return $this->chatId;
    }

    public function enabled(): bool
    {
        if ($this->chatId === null || $this->chatId === 0 || $this->blocked) {
            return false;
        }
        return $this->client() !== null;
    }

    private function client(): ?TelegramClient
    {
        if ($this->client === null) {
            try {
                $tg = $this->app->telegram();
                $this->client = $tg->hasToken() ? $tg : null;
            } catch (\Throwable $e) {
                $this->logger->error('Telegram client unavailable', ['error' => $e->getMessage()]);
                return null;
            }
        }
        return $this->client;
    }

    public function sentCount(): int
    {
        return $this->sent;
    }

    /** True when at least one send failed for a temporary reason (worth retrying). */
    public function transientFailure(): bool
    {
        return $this->transient;
    }

    /** Every attempted send failed temporarily (Telegram unreachable). */
    public function nothingDelivered(): bool
    {
        return $this->attempted > 0 && $this->sent === 0 && $this->transient;
    }

    public function blocked(): bool
    {
        return $this->blocked;
    }

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Runs one Telegram call; failures are classified and logged, never thrown.
     * @param callable(TelegramClient):mixed $fn
     */
    private function call(string $what, callable $fn): mixed
    {
        if (!$this->enabled()) {
            return null;
        }
        $this->attempted++;
        try {
            $result = $fn($this->client);
            $this->sent++;
            return $result;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            $code = $e instanceof TelegramException ? $e->errorCode() : -1;
            if ($e instanceof TelegramException && $e->isBlockedByUser()) {
                $this->blocked = true;
                $this->logger->notice('User blocked the bot, delivery stopped', ['chat' => $this->chatId, 'call' => $what]);
                return null;
            }
            if ($code === 0 || $code === 429 || $code >= 500) {
                $this->transient = true;
            }
            $this->logger->warning('Telegram delivery failed', ['call' => $what, 'chat' => $this->chatId, 'code' => $code, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function pace(): void
    {
        if ($this->enabled()) {
            usleep(self::PACE_US);
        }
    }

    // ----------------------------------------------------------- messages

    /** @param array<string,mixed>|null $markup */
    public function message(string $text, ?array $markup = null, ?int $replyTo = null): ?int
    {
        $opts = ['disable_web_page_preview' => true];
        if ($markup !== null) {
            $opts['reply_markup'] = $markup;
        }
        if ($replyTo !== null && $replyTo > 0) {
            $opts['reply_to_message_id'] = $replyTo;
            $opts['allow_sending_without_reply'] = true;
        }
        $r = $this->call('sendMessage', fn (TelegramClient $tg) => $tg->sendMessage($this->chatId, self::cut($text, 4000), $opts));
        return is_array($r) && isset($r['message_id']) ? (int) $r['message_id'] : null;
    }

    /**
     * Shows a progress text: edits the existing progress message, or sends a
     * new one when there is none (or it was deleted). Returns its id.
     * @param array<string,mixed>|null $markup
     */
    public function progress(?int $messageId, string $text, ?array $markup = null): ?int
    {
        if (!$this->enabled()) {
            return $messageId;
        }
        if ($messageId !== null && $messageId > 0) {
            $opts = $markup !== null ? ['reply_markup' => $markup] : [];
            $edited = $this->call('editMessageText', fn (TelegramClient $tg) => $tg->editMessageText($this->chatId, $messageId, self::cut($text, 4000), $opts));
            if ($edited !== null) {
                return $messageId;
            }
            if ($this->blocked || $this->transient) {
                return $messageId;
            }
        }
        return $this->message($text, $markup);
    }

    public function delete(?int $messageId): void
    {
        if ($messageId !== null && $messageId > 0) {
            $this->call('deleteMessage', fn (TelegramClient $tg) => $tg->deleteMessage($this->chatId, $messageId));
        }
    }

    // ------------------------------------------------------------ previews

    /**
     * Sends a rendered preview (EmojiRenderer / TemplateRenderer result) with
     * the "generate / edit / another preview" keyboard.
     * @param array<string,mixed> $render
     */
    public function sendPreview(array $render, string $kind, int $projectId, string $caption, ?int $replyTo = null): bool
    {
        $markup = self::previewKeyboard($kind, $projectId);
        $opts = ['reply_markup' => $markup];
        if ($replyTo !== null && $replyTo > 0) {
            $opts['reply_to_message_id'] = $replyTo;
            $opts['allow_sending_without_reply'] = true;
        }
        $caption = self::cut($caption, 1000);
        $preview = is_string($render['preview'] ?? null) && is_file($render['preview']) ? $render['preview'] : null;
        if ($preview !== null) {
            $sent = $this->call('sendAnimation', fn (TelegramClient $tg) => $tg->sendAnimation($this->chatId, $preview, $caption, $opts));
            if ($sent !== null) {
                return true;
            }
        }
        $thumb = is_string($render['thumb'] ?? null) && is_file($render['thumb']) ? $render['thumb'] : null;
        if ($thumb !== null && !$this->blocked) {
            if ($this->call('sendPhoto', fn (TelegramClient $tg) => $tg->sendPhoto($this->chatId, $thumb, $caption, $opts)) !== null) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------- outputs

    /** Sends one stored file (generated_files row) as a document. */
    public function sendFileRow(array $row, string $caption = ''): bool
    {
        try {
            $abs = $this->app->storage()->path((string) $row['path']);
        } catch (\InvalidArgumentException) {
            return false;
        }
        if (!is_file($abs)) {
            $this->logger->warning('Output file missing on disk', ['file' => (int) ($row['id'] ?? 0)]);
            return false;
        }
        $name = self::safeFilename((string) ($row['filename'] ?? basename($abs)));
        return $this->call('sendDocument', fn (TelegramClient $tg) => $tg->sendDocument($this->chatId, $abs, self::cut($caption, 1000), [], $name)) !== null;
    }

    /** Batch overview pages: animated grid (mp4/gif) or, without animation, the contact sheet. */
    public function sendGrids(array $grids, array $sheets, int $itemCount): int
    {
        $pages = [];
        foreach ($sheets as $s) {
            $pages[(int) $s['tile']]['sheet'] = $s;
        }
        foreach ($grids as $g) {
            $pages[(int) $g['tile']]['grid'] = $g;
        }
        ksort($pages);
        $count = count($pages);
        $n = 0;
        foreach ($pages as $p => $page) {
            $caption = '🧩 پیش‌نمایش همه‌ی ' . self::fa($itemCount) . ' ایموجی'
                . ($count > 1 ? ' — صفحه‌ی ' . self::fa($p + 1) . ' از ' . self::fa($count) : '')
                . "\nشماره‌ی هر خانه همان شماره‌ی فایل است.";
            $sent = false;
            foreach (['grid' => 'sendAnimation', 'sheet' => 'sendPhoto'] as $key => $method) {
                if ($sent || !isset($page[$key]) || $this->blocked) {
                    continue;
                }
                try {
                    $abs = $this->app->storage()->path((string) $page[$key]['path']);
                } catch (\InvalidArgumentException) {
                    continue;
                }
                if (!is_file($abs)) {
                    continue;
                }
                $sent = $this->call($method, fn (TelegramClient $tg) => $method === 'sendAnimation'
                    ? $tg->sendAnimation($this->chatId, $abs, $caption)
                    : $tg->sendPhoto($this->chatId, $abs, $caption)) !== null;
            }
            if ($sent) {
                $n++;
                $this->pace();
            }
        }
        return $n;
    }

    public function sendZip(array $zipRow, string $caption = ''): bool
    {
        if ($caption === '') {
            $caption = '📦 همه‌ی خروجی‌ها در یک فایل ZIP (به همراه manifest.json و راهنمای انتشار).';
        }
        return $this->sendFileRow($zipRow, $caption);
    }

    public function sendLink(string $url, int $hours): bool
    {
        $text = "🔗 لینک دانلود ZIP آماده است:\n" . $url . "\n\n⏳ این لینک " . self::fa($hours)
            . ' ساعت معتبر است و فقط برای شما ساخته شده؛ آن را با دیگران به اشتراک نگذارید.';
        $markup = str_starts_with($url, 'https://') ? self::keyboard([[self::urlButton('⬇️ دانلود ZIP', $url)]]) : null;
        return $this->message($text, $markup) !== null;
    }

    /**
     * Delivers the outputs of a generate job (send = auto | files | zip) and
     * finishes with the summary + action keyboard.
     * @param list<array<string,mixed>> $mains main file rows (all tiles)
     * @param list<array<string,mixed>> $grids grid rows (one per page)
     * @param list<array<string,mixed>> $sheets sheet rows (one per page)
     * @param list<array{index:int,label:string,error:string}> $failed
     * @return array{files:int,grids:int,zip:bool,summary:bool}
     */
    public function deliverGenerated(int $jobId, string $mode, array $mains, array $grids, array $sheets, ?array $zip, array $failed, string $format, bool $allowPublish, int $itemCount): array
    {
        $out = ['files' => 0, 'grids' => 0, 'zip' => false, 'summary' => false];
        if (!$this->enabled()) {
            return $out;
        }
        $mode = in_array($mode, ['auto', 'files', 'zip'], true) ? $mode : 'auto';
        $sendFiles = $mode === 'files' || ($mode === 'auto' && count($mains) <= self::AUTO_FILES);
        $sendZip = !$sendFiles || count($mains) > self::MAX_FILES;
        if ($itemCount > 1 && ($grids || $sheets)) {
            $out['grids'] = $this->sendGrids($grids, $sheets, $itemCount);
        }
        if ($sendFiles) {
            $tilesPerItem = [];
            foreach ($mains as $row) {
                $tilesPerItem[(int) $row['item_index']] = ($tilesPerItem[(int) $row['item_index']] ?? 0) + 1;
            }
            foreach (array_slice($mains, 0, self::MAX_FILES) as $row) {
                if ($this->blocked) {
                    break;
                }
                if ($this->sendFileRow($row, self::fileCaption($row, $tilesPerItem[(int) $row['item_index']] ?? 1))) {
                    $out['files']++;
                }
                $this->pace();
            }
        }
        if ($sendZip && $zip !== null && !$this->blocked) {
            $out['zip'] = $this->sendZip($zip);
            $this->pace();
        }
        $summary = self::summaryText($mains, $failed, $format, $sendZip && $out['zip'], $itemCount);
        $out['summary'] = $this->message($summary, self::deliveryKeyboard($jobId, $allowPublish)) !== null;
        return $out;
    }

    /** Tells the user that a job finally failed (edits the progress message when possible). */
    public function jobFailed(array $job, string $text): void
    {
        $jobId = (int) $job['id'];
        $body = '❌ ' . $text;
        $markup = in_array($job['type'], ['generate', 'preview'], true)
            ? self::keyboard([[self::button('🔁 تلاش دوباره', 'j:again:' . $jobId)]])
            : null;
        $mid = isset($job['progress_message_id']) ? (int) $job['progress_message_id'] : null;
        $this->progress($mid ?: null, $body, $markup);
    }

    public function jobCancelled(array $job): void
    {
        $mid = isset($job['progress_message_id']) ? (int) $job['progress_message_id'] : null;
        $this->progress($mid ?: null, '⛔️ کار لغو شد.');
    }

    // -------------------------------------------------------------- texts

    /** @param array<string,mixed> $row main file row */
    public static function fileCaption(array $row, int $tiles = 1): string
    {
        $variant = json_decode((string) ($row['variant'] ?? ''), true);
        $label = is_array($variant) ? trim((string) ($variant['label'] ?? '')) : '';
        $num = self::fa((int) $row['item_index'] + 1);
        $line = $num . '. ' . ($label !== '' ? $label : 'ایموجی');
        if ($tiles > 1) {
            $line .= ' — تکه‌ی ' . self::fa((int) $row['tile'] + 1) . ' از ' . self::fa($tiles);
        }
        $status = self::validationLine((string) ($row['validation_status'] ?? 'unchecked'));
        return $status !== '' ? $line . "\n" . $status : $line;
    }

    public static function validationLine(string $status): string
    {
        return match ($status) {
            'valid' => '✅ سازگار با استاندارد تلگرام',
            'warning' => '⚠️ قابل استفاده (با هشدار)',
            'invalid' => '❌ با استاندارد تلگرام سازگار نیست',
            default => '',
        };
    }

    public static function formatLabel(string $format): string
    {
        return match ($format) {
            'webm' => 'WEBM (ویدیویی)',
            'tgs' => 'TGS (متحرک برداری)',
            'png' => 'PNG (ثابت)',
            default => strtoupper($format),
        };
    }

    /**
     * @param list<array<string,mixed>> $mains
     * @param list<array{index:int,label:string,error:string}> $failed
     */
    public static function summaryText(array $mains, array $failed, string $format, bool $zipSent, int $itemCount): string
    {
        $items = [];
        $counts = ['valid' => 0, 'warning' => 0, 'invalid' => 0];
        foreach ($mains as $row) {
            $items[(int) $row['item_index']] = true;
            $s = (string) ($row['validation_status'] ?? '');
            if (isset($counts[$s])) {
                $counts[$s]++;
            }
        }
        $lines = ['✅ ساخت ' . self::fa(count($items)) . ' ایموجی با فرمت ' . self::formatLabel($format) . ' تمام شد.'];
        if ($failed) {
            $nums = array_map(static fn ($f) => self::fa((int) $f['index'] + 1), array_slice($failed, 0, 15));
            $lines[] = '⚠️ ' . self::fa(count($failed)) . ' مورد ساخته نشد (شماره‌ی ' . implode('، ', $nums) . (count($failed) > 15 ? '، …' : '') . ').';
        }
        $parts = [];
        if ($counts['valid']) {
            $parts[] = '✅ ' . self::fa($counts['valid']) . ' سازگار';
        }
        if ($counts['warning']) {
            $parts[] = '⚠️ ' . self::fa($counts['warning']) . ' با هشدار';
        }
        if ($counts['invalid']) {
            $parts[] = '❌ ' . self::fa($counts['invalid']) . ' ناسازگار';
        }
        if ($parts) {
            $lines[] = 'بررسی استاندارد تلگرام: ' . implode(' | ', $parts);
        }
        if ($zipSent) {
            $lines[] = '📦 همه‌ی فایل‌ها در ZIP بالا هستند.';
        }
        $lines[] = '';
        $lines[] = 'برای ساخت پک ایموجی تلگرام دکمه‌ی «انتشار» را بزنید؛ ZIP و لینک دانلود هم از دکمه‌های زیر در دسترس است.';
        return implode("\n", $lines);
    }

    // ------------------------------------------------------------ keyboards

    /** @return array{text:string,callback_data:string} */
    public static function button(string $text, string $data): array
    {
        if (strlen($data) > 64) {
            throw new \LengthException('Callback data longer than 64 bytes');
        }
        return ['text' => $text, 'callback_data' => $data];
    }

    /** @return array{text:string,url:string} */
    public static function urlButton(string $text, string $url): array
    {
        return ['text' => $text, 'url' => $url];
    }

    /**
     * @param list<list<array<string,string>>> $rows
     * @return array{inline_keyboard:list<list<array<string,string>>>}
     */
    public static function keyboard(array $rows): array
    {
        return ['inline_keyboard' => array_values(array_filter($rows))];
    }

    /** Preview actions: designs use the wizard namespace "w", templates "t". */
    public static function previewKeyboard(string $kind, int $projectId): array
    {
        $ns = $kind === 'template' ? 't' : 'w';
        return self::keyboard([
            [self::button('✅ ساخت نهایی', $ns . ':gen:' . $projectId)],
            [self::button('✏️ ویرایش', $ns . ':edit:' . $projectId), self::button('🔁 پیش‌نمایش دیگر', $ns . ':prev:' . $projectId)],
        ]);
    }

    public static function deliveryKeyboard(int $jobId, bool $allowPublish = true): array
    {
        $rows = [];
        if ($allowPublish) {
            $rows[] = [self::button('📤 انتشار به‌صورت پک ایموجی', 'pub:new:' . $jobId)];
        }
        $rows[] = [self::button('⬇️ دریافت ZIP', 'j:zip:' . $jobId), self::button('🔗 لینک دانلود', 'j:link:' . $jobId)];
        $rows[] = [self::button('🔁 ساخت دوباره', 'j:again:' . $jobId)];
        return self::keyboard($rows);
    }

    public static function cancelKeyboard(int $jobId): array
    {
        return self::keyboard([[self::button('✖️ لغو', 'j:cancel:' . $jobId)]]);
    }

    // -------------------------------------------------------------- helpers

    public static function fa(int|string $n): string
    {
        return Unicode::toPersianDigits((string) $n);
    }

    public static function cut(string $text, int $max): string
    {
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . '…' : $text;
    }

    /** File names shown to users: no path parts, no control characters. */
    public static function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"\\\\\/:*?<>|]+/u', '_', $name);
        $name = trim($name, ". \t");
        return $name !== '' ? mb_substr($name, 0, 120) : 'file';
    }

    public static function mimeFor(string $ext): string
    {
        return match (strtolower($ext)) {
            'webm' => 'video/webm',
            'tgs' => 'application/x-tgsticker',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'jpg', 'jpeg' => 'image/jpeg',
            'zip' => 'application/zip',
            'json' => 'application/json',
            default => 'application/octet-stream',
        };
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Telegram;

use EmojiMaker\Security\Redactor;
use EmojiMaker\Support\Logger;

/**
 * Bot API client on plain ext-curl (no SDK, works on shared hosting).
 *
 * - Parameters are sent as form fields; arrays (reply_markup, stickers, media
 *   ...) are JSON-encoded automatically, files go as multipart CURLFile.
 * - 429 answers are retried after retry_after (bounded by $maxRetryAfter so a
 *   webhook request never sleeps for minutes), transport failures are retried
 *   only when repeating the request cannot duplicate a side effect.
 * - The token is part of the request URL only; it is registered with the
 *   Redactor and never logged (logs carry method, code, description, time).
 */
final class TelegramClient
{
    private const NETWORK_RETRIES = 2;

    /** Methods that can be repeated after an unclear transport failure without visible duplicates. */
    private const IDEMPOTENT = [
        'setWebhook', 'deleteWebhook', 'setMyCommands', 'deleteMyCommands', 'editMessageText',
        'editMessageReplyMarkup', 'editMessageCaption', 'deleteMessage', 'sendChatAction',
        'answerCallbackQuery', 'uploadStickerFile', 'setStickerSetTitle', 'logOut',
    ];

    private const STICKER_FORMATS = ['static', 'animated', 'video'];

    private const MIME = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'webm' => 'video/webm', 'mp4' => 'video/mp4', 'tgs' => 'application/x-tgsticker',
        'json' => 'application/json', 'zip' => 'application/zip', 'txt' => 'text/plain', 'pdf' => 'application/pdf',
    ];

    private string $apiBase;
    private ?\CurlHandle $curl = null;

    public function __construct(
        private string $token,
        string $apiBase,
        private Logger $logger,
        private int $timeout = 30,
        private int $maxRetryAfter = 30,
    ) {
        $apiBase = rtrim(trim($apiBase), '/');
        if ($apiBase === '') {
            $apiBase = 'https://api.telegram.org';
        }
        if (!preg_match('#^https?://[^\s/?\#]+(/[^\s?\#]*)?$#i', $apiBase)) {
            throw new \InvalidArgumentException('Invalid Bot API base URL');
        }
        $this->apiBase = $apiBase;
        $this->timeout = max(1, $timeout);
        $this->maxRetryAfter = max(0, $maxRetryAfter);
        Redactor::registerSecret($token);
    }

    public function hasToken(): bool
    {
        return $this->token !== '';
    }

    /**
     * Raw call. $files: field => absolute local path (multipart); a value may
     * also be ['path' => .., 'filename' => .., 'mime' => ..] to control the
     * uploaded name. Returns the "result" member.
     *
     * @param array<string,mixed> $params
     * @param array<string,string|array{path:string,filename?:string,mime?:string}> $files
     * @throws TelegramException
     */
    public function call(string $method, array $params = [], array $files = [], ?int $timeout = null, int $maxRetries = 2): mixed
    {
        if (!preg_match('/^[A-Za-z]{2,64}$/', $method)) {
            throw new \InvalidArgumentException('Invalid Bot API method name');
        }
        if ($this->token === '') {
            throw new TelegramException('Telegram bot token is not configured');
        }
        $fields = self::encodeParams($params);
        foreach ($files as $field => $file) {
            if (!is_string($field) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $field)) {
                throw new \InvalidArgumentException('Invalid upload field name');
            }
            if (isset($fields[$field])) {
                throw new \InvalidArgumentException('Upload field collides with a parameter: ' . $field);
            }
            $fields[$field] = self::curlFile($file);
        }
        $multipart = $files !== [];
        $timeout = max(1, $timeout ?? ($multipart ? max($this->timeout, 120) : $this->timeout));
        $url = $this->apiBase . '/bot' . $this->token . '/' . $method;

        $floodRetries = 0;
        $networkRetries = 0;
        while (true) {
            $started = microtime(true);
            $response = $this->post($url, $fields, $multipart, $timeout);
            $ms = (int) round((microtime(true) - $started) * 1000);
            try {
                $result = self::parse($response);
                $this->logger->debug('Bot API call', ['method' => $method, 'ms' => $ms]);
                return $result;
            } catch (TelegramException $e) {
                if ($e->isFlood() && $e->retryAfter !== null && $floodRetries < $maxRetries && $e->retryAfter <= $this->maxRetryAfter) {
                    $floodRetries++;
                    $this->logger->notice('Bot API flood control, waiting', ['method' => $method, 'retry_after' => $e->retryAfter, 'attempt' => $floodRetries]);
                    sleep(max(1, $e->retryAfter));
                    continue;
                }
                if ($networkRetries < self::NETWORK_RETRIES && $this->isRetryable($response, $method)) {
                    $networkRetries++;
                    $this->logger->notice('Bot API transport failure, retrying', ['method' => $method, 'code' => $e->errorCode(), 'error' => $e->getMessage(), 'attempt' => $networkRetries]);
                    usleep(500000 * (3 ** ($networkRetries - 1)));
                    continue;
                }
                $this->logger->warning('Bot API call failed', ['method' => $method, 'code' => $e->errorCode(), 'error' => $e->getMessage(), 'ms' => $ms]);
                throw $e;
            }
        }
    }

    // ------------------------------------------------------------ messages

    /** @return array<string,mixed> */
    public function getMe(): array
    {
        return self::arr($this->call('getMe'), 'getMe');
    }

    /**
     * opts: reply_markup (array), parse_mode, reply_to_message_id, disable_web_page_preview,
     * link_preview_options and any other sendMessage parameter.
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function sendMessage(int|string $chatId, string $text, array $opts = []): array
    {
        return self::arr($this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text] + self::options($opts)), 'sendMessage');
    }

    /**
     * Returns the edited message, or true for inline messages and for
     * "message is not modified" (an edit to the same content is not an error).
     * @param array<string,mixed> $opts
     * @return array<string,mixed>|bool
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $opts = []): array|bool
    {
        try {
            $r = $this->call('editMessageText', ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text] + self::options($opts));
        } catch (TelegramException $e) {
            if ($e->isNotModified()) {
                return true;
            }
            throw $e;
        }
        return is_array($r) ? $r : (bool) $r;
    }

    /**
     * null removes the inline keyboard.
     * @param array<string,mixed>|null $markup
     * @return array<string,mixed>|bool
     */
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, ?array $markup): array|bool
    {
        try {
            $r = $this->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => $markup]);
        } catch (TelegramException $e) {
            if ($e->isNotModified()) {
                return true;
            }
            throw $e;
        }
        return is_array($r) ? $r : (bool) $r;
    }

    /** False (no exception) when the message is already gone or too old to delete. */
    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        try {
            return (bool) $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
        } catch (TelegramException $e) {
            if ($e->errorCode() === 400 && preg_match("/message to delete not found|message can't be deleted|MESSAGE_ID_INVALID/i", $e->getMessage())) {
                return false;
            }
            throw $e;
        }
    }

    /** False when the query expired (slow processing must not break the flow that follows). */
    public function answerCallbackQuery(string $id, string $text = '', bool $alert = false): bool
    {
        $params = ['callback_query_id' => $id];
        if ($text !== '') {
            $params['text'] = mb_substr($text, 0, 200);
        }
        if ($alert) {
            $params['show_alert'] = true;
        }
        try {
            return (bool) $this->call('answerCallbackQuery', $params, [], null, 0);
        } catch (TelegramException $e) {
            if ($e->errorCode() === 400 && preg_match('/query is too old|query ID is invalid|QUERY_ID_INVALID/i', $e->getMessage())) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * $path is a local file (uploaded) or a Telegram file_id / URL (re-sent without upload).
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function sendDocument(int|string $chatId, string $path, string $caption = '', array $opts = [], ?string $filename = null): array
    {
        return $this->sendMedia('sendDocument', 'document', $chatId, $path, $caption, $opts, $filename);
    }

    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function sendPhoto(int|string $chatId, string $path, string $caption = '', array $opts = []): array
    {
        return $this->sendMedia('sendPhoto', 'photo', $chatId, $path, $caption, $opts, null);
    }

    /**
     * mp4/gif previews.
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function sendAnimation(int|string $chatId, string $path, string $caption = '', array $opts = []): array
    {
        return $this->sendMedia('sendAnimation', 'animation', $chatId, $path, $caption, $opts, null);
    }

    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    public function sendVideo(int|string $chatId, string $path, string $caption = '', array $opts = []): array
    {
        return $this->sendMedia('sendVideo', 'video', $chatId, $path, $caption, $opts, null);
    }

    /**
     * media: InputMedia arrays using "attach://<field>"; $files: field => local path.
     * @param list<array<string,mixed>> $media
     * @param array<string,string|array{path:string,filename?:string,mime?:string}> $files
     * @return list<array<string,mixed>>
     */
    public function sendMediaGroup(int|string $chatId, array $media, array $files): array
    {
        $r = $this->call('sendMediaGroup', ['chat_id' => $chatId, 'media' => array_values($media)], $files);
        return array_values(self::arr($r, 'sendMediaGroup'));
    }

    /** Cosmetic only: never throws and never waits for flood control. */
    public function sendChatAction(int|string $chatId, string $action = 'typing'): void
    {
        try {
            $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => $action], [], min(10, $this->timeout), 0);
        } catch (TelegramException $e) {
            $this->logger->debug('sendChatAction ignored', ['code' => $e->errorCode()]);
        }
    }

    // --------------------------------------------------------------- files

    /** @return array<string,mixed> {file_id, file_unique_id, file_size, file_path} */
    public function getFile(string $fileId): array
    {
        return self::arr($this->call('getFile', ['file_id' => $fileId]), 'getFile');
    }

    /**
     * Downloads a file by its getFile file_path into $dest (atomic rename,
     * aborted as soon as it grows beyond $maxBytes). Returns the size.
     * @throws TelegramException code 413 when too large, HTTP status / 0 otherwise
     */
    public function downloadFile(string $filePath, string $dest, int $maxBytes): int
    {
        if ($this->token === '') {
            throw new TelegramException('Telegram bot token is not configured');
        }
        if ($filePath === '' || str_starts_with($filePath, '/') || !preg_match('#^[A-Za-z0-9_\-./]+$#', $filePath)
            || preg_match('#(^|/)\.\.?(/|$)#', $filePath)) {
            throw new \InvalidArgumentException('Invalid Telegram file path');
        }
        $dir = dirname($dest);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new \RuntimeException('Download destination is not writable');
        }
        $url = $this->apiBase . '/file/bot' . $this->token . '/' . $filePath;
        $attempt = 0;
        while (true) {
            $started = microtime(true);
            $tmp = $dir . '/.download-' . bin2hex(random_bytes(8)) . '.part';
            $fh = @fopen($tmp, 'xb');
            if ($fh === false) {
                throw new \RuntimeException('Unable to create the download file');
            }
            $bytes = 0;
            $tooBig = false;
            $writeFailed = false;
            $ch = $this->handle();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_HTTPGET => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => min(10, $this->timeout),
                CURLOPT_TIMEOUT => max(60, $this->timeout),
                CURLOPT_NOSIGNAL => true,
                CURLOPT_USERAGENT => 'EmojiMaker-Bot/1.0',
                CURLOPT_MAXFILESIZE => max(1, $maxBytes),
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($fh, $maxBytes, &$bytes, &$tooBig, &$writeFailed): int {
                    $len = strlen($chunk);
                    if ($bytes + $len > $maxBytes) {
                        $tooBig = true;
                        return 0;
                    }
                    if (fwrite($fh, $chunk) !== $len) {
                        $writeFailed = true;
                        return 0;
                    }
                    $bytes += $len;
                    return $len;
                },
            ]);
            curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_reset($ch);
            fclose($fh);
            $ms = (int) round((microtime(true) - $started) * 1000);

            if ($errno === 0 && $http === 200 && !$tooBig && !$writeFailed) {
                if (!@rename($tmp, $dest)) {
                    @unlink($tmp);
                    throw new \RuntimeException('Unable to move the downloaded file into place');
                }
                @chmod($dest, 0640);
                $this->logger->debug('Bot API file downloaded', ['bytes' => $bytes, 'ms' => $ms]);
                return $bytes;
            }
            @unlink($tmp);
            if ($tooBig || $errno === 63 /* CURLE_FILESIZE_EXCEEDED */) {
                throw new TelegramException('File exceeds the size limit', 413);
            }
            if ($writeFailed) {
                throw new \RuntimeException('Unable to write the downloaded file');
            }
            $retryable = $errno !== 0 || $http >= 500;
            if ($retryable && $attempt < self::NETWORK_RETRIES) {
                $attempt++;
                usleep(500000 * (3 ** ($attempt - 1)));
                continue;
            }
            $e = $errno !== 0
                ? new TelegramException('File download failed: ' . $error, 0)
                : new TelegramException('File download failed (HTTP ' . $http . ')', $http);
            $this->logger->warning('Bot API file download failed', ['code' => $e->errorCode(), 'error' => $e->getMessage(), 'ms' => $ms]);
            throw $e;
        }
    }

    // ------------------------------------------------------------- webhook

    /** @param list<string> $allowedUpdates */
    public function setWebhook(string $url, string $secret, array $allowedUpdates = ['message', 'callback_query', 'my_chat_member'], int $maxConnections = 20, bool $dropPending = false): bool
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
            throw new \InvalidArgumentException('Webhook secret must be 1-256 characters of A-Z a-z 0-9 _ -');
        }
        if (!preg_match('#^https?://[^\s]+$#i', $url)) {
            throw new \InvalidArgumentException('Invalid webhook URL');
        }
        // HTTPS is enforced by api.telegram.org itself; a local Bot API server also accepts http
        return (bool) $this->call('setWebhook', [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => array_values($allowedUpdates),
            'max_connections' => max(1, min(100, $maxConnections)),
            'drop_pending_updates' => $dropPending,
        ]);
    }

    public function deleteWebhook(bool $dropPending = false): bool
    {
        return (bool) $this->call('deleteWebhook', ['drop_pending_updates' => $dropPending]);
    }

    /** @return array<string,mixed> */
    public function getWebhookInfo(): array
    {
        return self::arr($this->call('getWebhookInfo'), 'getWebhookInfo');
    }

    /**
     * $commands: list of ['command' => .., 'description' => ..] or [command => description].
     * @param array<int|string,mixed> $commands
     * @param array<string,mixed>|null $scope BotCommandScope
     */
    public function setMyCommands(array $commands, ?array $scope = null, ?string $languageCode = null): bool
    {
        $list = [];
        foreach ($commands as $key => $value) {
            if (is_array($value)) {
                $command = (string) ($value['command'] ?? '');
                $description = trim((string) ($value['description'] ?? ''));
            } else {
                $command = (string) $key;
                $description = trim((string) $value);
            }
            $command = strtolower(ltrim($command, '/'));
            if (!preg_match('/^[a-z0-9_]{1,32}$/', $command) || $description === '') {
                throw new \InvalidArgumentException('Invalid bot command definition');
            }
            $list[] = ['command' => $command, 'description' => mb_substr($description, 0, 256)];
        }
        $params = ['commands' => $list, 'scope' => $scope];
        if ($languageCode !== null && $languageCode !== '') {
            $params['language_code'] = $languageCode;
        }
        return (bool) $this->call('setMyCommands', $params);
    }

    // ------------------------------------------------------- sticker sets

    /**
     * Uploads one sticker file for later use in createNewStickerSet / addStickerToSet.
     * @return array<string,mixed> File {file_id, file_unique_id, file_size}
     */
    public function uploadStickerFile(int $userId, string $path, string $format): array
    {
        self::assertStickerFormat($format);
        return self::arr($this->call('uploadStickerFile', ['user_id' => $userId, 'sticker_format' => $format], ['sticker' => $path]), 'uploadStickerFile');
    }

    /**
     * $stickers: InputSticker arrays {sticker, format, emoji_list, keywords?} (Bot API 7.2+).
     * @param list<array<string,mixed>> $stickers
     * @param array<string,string|array{path:string,filename?:string,mime?:string}> $files for attach:// references
     */
    public function createNewStickerSet(int $userId, string $name, string $title, array $stickers, string $stickerType = 'custom_emoji', bool $needsRepainting = false, array $files = []): bool
    {
        $params = [
            'user_id' => $userId,
            'name' => $name,
            'title' => $title,
            'stickers' => array_values($stickers),
            'sticker_type' => $stickerType,
        ];
        if ($needsRepainting && $stickerType === 'custom_emoji') {
            $params['needs_repainting'] = true;
        }
        return (bool) $this->call('createNewStickerSet', $params, $files, max($this->timeout, 180));
    }

    /**
     * @param array<string,mixed> $sticker InputSticker
     * @param array<string,string|array{path:string,filename?:string,mime?:string}> $files
     */
    public function addStickerToSet(int $userId, string $name, array $sticker, array $files = []): bool
    {
        return (bool) $this->call('addStickerToSet', ['user_id' => $userId, 'name' => $name, 'sticker' => $sticker], $files, max($this->timeout, 60));
    }

    /** @return array<string,mixed> StickerSet */
    public function getStickerSet(string $name): array
    {
        return self::arr($this->call('getStickerSet', ['name' => $name]), 'getStickerSet');
    }

    public function deleteStickerFromSet(string $sticker): bool
    {
        return (bool) $this->call('deleteStickerFromSet', ['sticker' => $sticker]);
    }

    public function setStickerSetTitle(string $name, string $title): bool
    {
        return (bool) $this->call('setStickerSetTitle', ['name' => $name, 'title' => $title]);
    }

    public function deleteStickerSet(string $name): bool
    {
        return (bool) $this->call('deleteStickerSet', ['name' => $name]);
    }

    // ------------------------------------------------------------ internals

    /**
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    private function sendMedia(string $method, string $field, int|string $chatId, string $path, string $caption, array $opts, ?string $filename): array
    {
        $params = ['chat_id' => $chatId];
        if ($caption !== '') {
            $params['caption'] = $caption;
        }
        $params += self::options($opts);
        $files = [];
        if (is_file($path)) {
            $files[$field] = ['path' => $path, 'filename' => $filename ?? basename($path)];
        } elseif (preg_match('/^[A-Za-z0-9_-]{20,}$/', $path) || preg_match('#^https://#i', $path)) {
            $params[$field] = $path; // already on Telegram's servers (file_id) or a public URL
        } else {
            throw new \InvalidArgumentException('File to send does not exist');
        }
        return self::arr($this->call($method, $params, $files), $method);
    }

    /**
     * Maps legacy options to their current Bot API form.
     * @param array<string,mixed> $opts
     * @return array<string,mixed>
     */
    private static function options(array $opts): array
    {
        if (array_key_exists('reply_to_message_id', $opts)) {
            $replyTo = (int) $opts['reply_to_message_id'];
            if ($replyTo > 0 && !isset($opts['reply_parameters'])) {
                $opts['reply_parameters'] = ['message_id' => $replyTo, 'allow_sending_without_reply' => true];
            }
            unset($opts['reply_to_message_id']);
        }
        if (array_key_exists('disable_web_page_preview', $opts)) {
            if ($opts['disable_web_page_preview'] && !isset($opts['link_preview_options'])) {
                $opts['link_preview_options'] = ['is_disabled' => true];
            }
            unset($opts['disable_web_page_preview']);
        }
        return $opts;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,string>
     */
    private static function encodeParams(array $params): array
    {
        $out = [];
        foreach ($params as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z_][a-z0-9_]{0,63}$/', $key)) {
                throw new \InvalidArgumentException('Invalid Bot API parameter name');
            }
            if ($value === null) {
                continue;
            }
            $out[$key] = match (true) {
                is_bool($value) => $value ? 'true' : 'false',
                is_int($value), is_float($value) => (string) $value,
                is_string($value) => $value,
                is_array($value), $value instanceof \JsonSerializable, $value instanceof \stdClass => json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
                ),
                $value instanceof \Stringable => (string) $value,
                default => throw new \InvalidArgumentException('Unsupported value for Bot API parameter ' . $key),
            };
        }
        return $out;
    }

    /** @param string|array{path:string,filename?:string,mime?:string} $file */
    private static function curlFile(string|array $file): \CURLFile
    {
        $path = is_array($file) ? (string) ($file['path'] ?? '') : $file;
        if ($path === '' || str_contains($path, "\0") || !is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('Upload file is missing or not readable');
        }
        $name = self::safeFilename(is_array($file) && isset($file['filename']) ? (string) $file['filename'] : basename($path));
        $mime = is_array($file) && isset($file['mime']) ? (string) $file['mime'] : self::mimeFor($name);
        return new \CURLFile($path, $mime, $name);
    }

    /** File name shown to the user: no path, no control or quoting characters. */
    private static function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"\'<>:*?|\/\\\\]+/u', '_', $name);
        $name = trim($name, " .\t");
        if (mb_strlen($name) > 120) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr($name, 0, 110) . ($ext !== '' ? '.' . mb_substr($ext, 0, 8) : '');
        }
        return $name !== '' ? $name : 'file';
    }

    private static function mimeFor(string $filename): string
    {
        return self::MIME[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }

    private static function assertStickerFormat(string $format): void
    {
        if (!in_array($format, self::STICKER_FORMATS, true)) {
            throw new \InvalidArgumentException('Sticker format must be static, animated or video');
        }
    }

    /**
     * @param array<string,string|\CURLFile> $fields
     * @return array{body:?string,http:int,errno:int,error:string,sent:bool}
     */
    private function post(string $url, array $fields, bool $multipart, int $timeout): array
    {
        $ch = $this->handle();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $multipart ? $fields : http_build_query($fields, '', '&', PHP_QUERY_RFC3986),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_USERAGENT => 'EmojiMaker-Bot/1.0',
            CURLOPT_HTTPHEADER => ['Expect:', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $response = [
            'body' => is_string($body) ? $body : null,
            'http' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'errno' => curl_errno($ch),
            'error' => curl_error($ch),
            // a request that never reached the server can always be repeated safely
            'sent' => (float) curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME) > 0.0,
        ];
        curl_reset($ch);
        return $response;
    }

    /** One keep-alive connection per client: publishing 200 stickers reuses it. */
    private function handle(): \CurlHandle
    {
        if ($this->curl === null) {
            $ch = curl_init();
            if ($ch === false) {
                throw new TelegramException('curl could not be initialised');
            }
            $this->curl = $ch;
        }
        curl_reset($this->curl);
        return $this->curl;
    }

    /** @param array{body:?string,http:int,errno:int,error:string,sent:bool} $r */
    private static function parse(array $r): mixed
    {
        if ($r['errno'] !== 0 || $r['body'] === null) {
            throw new TelegramException('Network error: ' . ($r['error'] !== '' ? $r['error'] : 'no response'), 0);
        }
        $data = json_decode($r['body'], true);
        if (!is_array($data) || !array_key_exists('ok', $data)) {
            throw new TelegramException(sprintf('Invalid response from the Bot API (HTTP %d)', $r['http']), $r['http'] >= 400 ? $r['http'] : 0);
        }
        if ($data['ok'] === true) {
            return $data['result'] ?? null;
        }
        $parameters = is_array($data['parameters'] ?? null) ? $data['parameters'] : [];
        throw new TelegramException(
            is_string($data['description'] ?? null) ? $data['description'] : 'Unknown Bot API error',
            is_int($data['error_code'] ?? null) ? $data['error_code'] : $r['http'],
            isset($parameters['retry_after']) && is_numeric($parameters['retry_after']) ? (int) $parameters['retry_after'] : null,
            isset($parameters['migrate_to_chat_id']) && is_numeric($parameters['migrate_to_chat_id']) ? (int) $parameters['migrate_to_chat_id'] : null,
        );
    }

    /** @param array{body:?string,http:int,errno:int,error:string,sent:bool} $r */
    private function isRetryable(array $r, string $method): bool
    {
        $idempotent = str_starts_with($method, 'get') || in_array($method, self::IDEMPOTENT, true);
        if ($r['errno'] !== 0) {
            return !$r['sent'] || $idempotent;
        }
        // 5xx / broken gateway pages: Telegram may or may not have executed the call
        return ($r['http'] >= 500 || $r['http'] === 0 || $r['http'] === 200) && $idempotent;
    }

    /** @return array<mixed> */
    private static function arr(mixed $value, string $method): array
    {
        if (!is_array($value)) {
            throw new TelegramException('Unexpected Bot API result for ' . $method);
        }
        return $value;
    }
}

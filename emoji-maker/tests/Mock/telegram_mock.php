<?php

declare(strict_types=1);

/*
 * Mock Telegram Bot API (router script for PHP's built-in web server):
 *
 *   MOCK_LOG=/tmp/calls.jsonl php -S 127.0.0.1:8081 tests/Mock/telegram_mock.php
 *
 * Point TELEGRAM_API_BASE at http://127.0.0.1:8081. It answers like the real
 * API (same envelopes, error codes and the most important validations) and
 * keeps its state (messages, uploaded files, sticker sets, webhook, commands)
 * in a JSON file so it survives between requests.
 *
 * Environment:
 *   MOCK_LOG           JSON lines file; every call is appended: {time, method, http, ok, params, files, error}
 *   MOCK_STATE         state file (default: <tmp>/telegram_mock_<port>.json; uploads next to it)
 *   MOCK_CONTROL       optional JSON control file, re-read on every request:
 *                      {"rules": [{"method": "sendMessage" | "*", "where": {"chat_id": "42"},
 *                                  "error_code": 429, "description": "Too Many Requests: retry after 1",
 *                                  "retry_after": 1, "times": 1},
 *                                 {"method": "getMe", "sleep_ms": 1500, "times": 1},
 *                                 {"method": "*", "http_status": 502, "raw": "<html>Bad Gateway</html>", "times": 1},
 *                                 {"method": "getChat", "result": {...}}],
 *                       "blocked": [555]}           chats that "blocked the bot" (403 on send / edit)
 *                      "times" counts down and the rule disappears at 0 (no "times" = forever).
 *   MOCK_FIXTURES      directory for getFile: a file_id equal to a file name in it is downloadable
 *                      via /file/bot<token>/fixtures/<name> (default tests/fixtures)
 *   MOCK_BOT_USERNAME  bot username (default emoji_maker_dev_bot), sticker set names must end in _by_<it>
 *   MOCK_TOKEN         when set, every other token gets 401 Unauthorized
 *
 * Extra endpoints: POST /__mock/reset (clear state + log), GET /__mock/state.
 * The token is never written to the log.
 */

namespace EmojiMaker\Tests\Mock;

final class MockError extends \RuntimeException
{
    public function __construct(public readonly int $errorCode, string $description, public readonly ?int $retryAfter = null, public readonly ?int $migrateTo = null)
    {
        parent::__construct($description, $errorCode);
    }
}

final class TelegramMock
{
    /** Parameters that carry JSON (decoded in the call log for easy assertions). */
    private const JSON_FIELDS = [
        'reply_markup', 'reply_parameters', 'link_preview_options', 'entities', 'caption_entities', 'media',
        'stickers', 'sticker', 'allowed_updates', 'commands', 'scope', 'emoji_list', 'keywords', 'mask_position',
    ];
    private const STICKER_FORMATS = ['static', 'animated', 'video'];
    private const STICKER_LIMITS = ['static' => 512 * 1024, 'animated' => 64 * 1024, 'video' => 256 * 1024];
    private const CHAT_ACTIONS = ['typing', 'upload_photo', 'record_video', 'upload_video', 'record_voice', 'upload_voice',
        'upload_document', 'choose_sticker', 'find_location', 'record_video_note', 'upload_video_note'];
    private const SCOPES = ['default', 'all_private_chats', 'all_group_chats', 'all_chat_administrators', 'chat', 'chat_administrators', 'chat_member'];
    private const BUTTON_ACTIONS = ['callback_data', 'url', 'web_app', 'login_url', 'switch_inline_query',
        'switch_inline_query_current_chat', 'switch_inline_query_chosen_chat', 'copy_text', 'callback_game', 'pay'];

    /** @var array<string,mixed> */
    private array $state = [];
    /** @var array<string,mixed> */
    private array $params = [];
    /** @var array<string,array{tmp:string,name:string,size:int,type:string}> */
    private array $files = [];
    /** @var array<string,int> upload errors per field */
    private array $uploadErrors = [];
    private string $method = '';
    private string $token = '';

    public function __construct(
        private string $stateFile,
        private ?string $logFile,
        private ?string $controlFile,
        private string $fixtures,
        private string $botUsername,
        private ?string $requiredToken,
    ) {
    }

    public function serve(): void
    {
        $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
        if ($path === '/__mock/reset') {
            $this->locked(function (): void {
                $this->state = [];
                $this->saveState();
            });
            if ($this->logFile !== null) {
                @file_put_contents($this->logFile, '', LOCK_EX);
            }
            $this->json(200, ['ok' => true, 'result' => true]);
            return;
        }
        if ($path === '/__mock/state') {
            $this->locked(function (): void {
                $this->state = $this->loadState();
            });
            $this->json(200, ['ok' => true, 'result' => $this->state]);
            return;
        }
        if (preg_match('#^/file/bot([0-9]+:[A-Za-z0-9_-]+)/(.+)$#', $path, $m)) {
            $this->serveFile($m[1], rawurldecode($m[2]));
            return;
        }
        if (!preg_match('#^/bot([0-9]+:[A-Za-z0-9_-]+)/([A-Za-z]+)$#', $path, $m)) {
            $this->json(404, ['ok' => false, 'error_code' => 404, 'description' => 'Not Found']);
            return;
        }
        $this->token = $m[1];
        $this->method = $m[2];
        $this->collectParams();

        $status = 200;
        $raw = null;
        $response = [];
        $this->locked(function () use (&$status, &$raw, &$response): void {
            $this->state = $this->loadState();
            try {
                if ($this->requiredToken !== null && $this->requiredToken !== '' && !hash_equals($this->requiredToken, $this->token)) {
                    throw new MockError(401, 'Unauthorized');
                }
                $override = $this->applyControl();
                if (isset($override['raw'])) {
                    $status = (int) $override['status'];
                    $raw = (string) $override['raw'];
                    return;
                }
                $result = array_key_exists('result', $override) ? $override['result'] : $this->dispatch();
                $this->saveState();
                $response = ['ok' => true, 'result' => $result];
            } catch (MockError $e) {
                $status = $e->errorCode;
                $response = ['ok' => false, 'error_code' => $e->errorCode, 'description' => $e->getMessage()];
                $parameters = [];
                if ($e->retryAfter !== null) {
                    $parameters['retry_after'] = $e->retryAfter;
                }
                if ($e->migrateTo !== null) {
                    $parameters['migrate_to_chat_id'] = $e->migrateTo;
                }
                if ($parameters !== []) {
                    $response['parameters'] = $parameters;
                }
            }
        });
        $this->record($status, $raw === null ? $response : ['ok' => false, 'description' => 'raw response']);
        if ($raw !== null) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
            echo $raw;
            return;
        }
        $this->json($status, $response);
    }

    // -------------------------------------------------------------- routing

    private function dispatch(): mixed
    {
        return match ($this->method) {
            'getMe' => $this->botUser() + ['can_join_groups' => true, 'can_read_all_group_messages' => false, 'supports_inline_queries' => false],
            'sendMessage' => $this->sendMessage(),
            'editMessageText' => $this->editMessageText(),
            'editMessageReplyMarkup' => $this->editMessageReplyMarkup(),
            'deleteMessage' => $this->deleteMessage(),
            'answerCallbackQuery' => $this->answerCallbackQuery(),
            'sendDocument' => $this->sendMedia('document', 50 * 1024 * 1024),
            'sendPhoto' => $this->sendMedia('photo', 10 * 1024 * 1024),
            'sendAnimation' => $this->sendMedia('animation', 50 * 1024 * 1024),
            'sendVideo' => $this->sendMedia('video', 50 * 1024 * 1024),
            'sendMediaGroup' => $this->sendMediaGroup(),
            'sendChatAction' => $this->sendChatAction(),
            'getFile' => $this->getFile(),
            'setWebhook' => $this->setWebhook(),
            'deleteWebhook' => $this->deleteWebhook(),
            'getWebhookInfo' => $this->getWebhookInfo(),
            'setMyCommands' => $this->setMyCommands(),
            'getMyCommands' => $this->state['commands'][$this->scopeKey()] ?? [],
            'uploadStickerFile' => $this->uploadStickerFile(),
            'createNewStickerSet' => $this->createNewStickerSet(),
            'addStickerToSet' => $this->addStickerToSet(),
            'getStickerSet' => $this->getStickerSet(),
            'deleteStickerFromSet' => $this->deleteStickerFromSet(),
            'setStickerSetTitle' => $this->setStickerSetTitle(),
            'deleteStickerSet' => $this->deleteStickerSet(),
            default => throw new MockError(404, 'Not Found'),
        };
    }

    // ------------------------------------------------------------- messages

    /** @return array<string,mixed> */
    private function sendMessage(): array
    {
        $chat = $this->chatId();
        $text = $this->text('text', 4096, 'message text is empty', 'message is too long');
        $this->parseMode();
        $markup = $this->replyMarkup(false);
        $this->optionalJson('reply_parameters');
        $this->optionalJson('link_preview_options');
        $msg = $this->message($chat, ['text' => $text] + (isset($markup['inline_keyboard']) ? ['reply_markup' => $markup] : []));
        $this->storeMessage($chat, $msg['message_id'], $text, $markup['inline_keyboard'] ?? null);
        return $msg;
    }

    private function editMessageText(): array|bool
    {
        if ($this->has('inline_message_id')) {
            $this->text('text', 4096, 'message text is empty', 'message is too long');
            return true;
        }
        $chat = $this->chatId();
        $id = $this->intParam('message_id');
        $text = $this->text('text', 4096, 'message text is empty', 'message is too long');
        $this->parseMode();
        $markup = $this->replyMarkup(true);
        $keyboard = $markup['inline_keyboard'] ?? null;
        $old = $this->state['messages'][$chat . ':' . $id] ?? null;
        if (is_array($old) && !empty($old['deleted'])) {
            throw new MockError(400, 'Bad Request: message to edit not found');
        }
        if (is_array($old) && $old['text'] === $text && ($old['keyboard'] ?? null) == $keyboard) {
            throw new MockError(400, 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same as a current content and reply markup of the message');
        }
        $this->storeMessage($chat, $id, $text, $keyboard);
        return $this->message($chat, ['text' => $text, 'edit_date' => time()] + ($keyboard !== null ? ['reply_markup' => $markup] : []), $id);
    }

    private function editMessageReplyMarkup(): array|bool
    {
        if ($this->has('inline_message_id')) {
            return true;
        }
        $chat = $this->chatId();
        $id = $this->intParam('message_id');
        $markup = $this->replyMarkup(true);
        $keyboard = $markup['inline_keyboard'] ?? null;
        $key = $chat . ':' . $id;
        $old = $this->state['messages'][$key] ?? null;
        if (is_array($old) && !empty($old['deleted'])) {
            throw new MockError(400, 'Bad Request: message to edit not found');
        }
        if (is_array($old) && ($old['keyboard'] ?? null) == $keyboard) {
            throw new MockError(400, 'Bad Request: message is not modified: specified new message content and reply markup are exactly the same as a current content and reply markup of the message');
        }
        $text = is_array($old) ? (string) $old['text'] : '';
        $this->storeMessage($chat, $id, $text, $keyboard);
        return $this->message($chat, ['text' => $text, 'edit_date' => time()] + ($keyboard !== null ? ['reply_markup' => $markup] : []), $id);
    }

    private function deleteMessage(): bool
    {
        $chat = $this->chatId();
        $id = $this->intParam('message_id');
        $key = $chat . ':' . $id;
        $old = $this->state['messages'][$key] ?? null;
        if (is_array($old) && !empty($old['deleted'])) {
            throw new MockError(400, 'Bad Request: message to delete not found');
        }
        $this->state['messages'][$key] = ['text' => is_array($old) ? $old['text'] : '', 'keyboard' => null, 'deleted' => true];
        return true;
    }

    private function answerCallbackQuery(): bool
    {
        $id = $this->str('callback_query_id', false);
        if ($id === null || $id === '') {
            throw new MockError(400, 'Bad Request: QUERY_ID_INVALID');
        }
        $text = $this->str('text', false) ?? '';
        if ($this->utf16Length($text) > 200) {
            throw new MockError(400, 'Bad Request: MESSAGE_TOO_LONG');
        }
        $this->boolParam('show_alert');
        return true;
    }

    /** @return array<string,mixed> */
    private function sendMedia(string $field, int $limit): array
    {
        $chat = $this->chatId();
        $caption = $this->str('caption', false) ?? '';
        if ($this->utf16Length($caption) > 1024) {
            throw new MockError(400, 'Bad Request: message caption is too long');
        }
        $this->parseMode();
        $markup = $this->replyMarkup(false);
        $this->optionalJson('reply_parameters');
        $file = $this->inputFile($field, $this->params[$field] ?? null, $limit);
        if ($field === 'photo' && !in_array($file['kind'], ['png', 'jpeg', 'webp', 'gif', 'remote'], true)) {
            throw new MockError(400, 'Bad Request: IMAGE_PROCESS_FAILED');
        }
        $extra = $caption !== '' ? ['caption' => $caption] : [];
        if (isset($markup['inline_keyboard'])) {
            $extra['reply_markup'] = $markup;
        }
        return $this->message($chat, $this->mediaPayload($field, $file) + $extra);
    }

    /** @return list<array<string,mixed>> */
    private function sendMediaGroup(): array
    {
        $chat = $this->chatId();
        $media = $this->jsonParam('media');
        if (!array_is_list($media) || count($media) < 2) {
            throw new MockError(400, 'Bad Request: there must be at least 2 media in a media group');
        }
        if (count($media) > 10) {
            throw new MockError(400, 'Bad Request: too many messages to send as an album');
        }
        $types = [];
        foreach ($media as $item) {
            $type = is_array($item) ? ($item['type'] ?? null) : null;
            if (!in_array($type, ['photo', 'video', 'document', 'audio'], true)) {
                throw new MockError(400, 'Bad Request: unsupported media type in a media group');
            }
            $types[$type] = true;
        }
        if (count($types) > 1 && (isset($types['document']) || isset($types['audio']))) {
            throw new MockError(400, "Bad Request: document can't be mixed with other media types");
        }
        $groupId = (string) random_int(10 ** 15, 10 ** 16 - 1);
        $out = [];
        foreach ($media as $item) {
            $caption = is_string($item['caption'] ?? null) ? $item['caption'] : '';
            if ($this->utf16Length($caption) > 1024) {
                throw new MockError(400, 'Bad Request: message caption is too long');
            }
            $file = $this->inputFile((string) $item['type'], $item['media'] ?? null, 50 * 1024 * 1024);
            $out[] = $this->message($chat, $this->mediaPayload((string) $item['type'], $file) + ['media_group_id' => $groupId] + ($caption !== '' ? ['caption' => $caption] : []));
        }
        return $out;
    }

    private function sendChatAction(): bool
    {
        $this->chatId();
        $action = $this->str('action', false);
        if (!in_array($action, self::CHAT_ACTIONS, true)) {
            throw new MockError(400, 'Bad Request: wrong parameter action in request');
        }
        return true;
    }

    // ---------------------------------------------------------------- files

    /** @return array<string,mixed> */
    private function getFile(): array
    {
        $id = $this->str('file_id', false) ?? '';
        if ($id === '') {
            throw new MockError(400, 'Bad Request: file_id is empty');
        }
        $known = $this->state['files'][$id] ?? null;
        if (is_array($known)) {
            if ((int) $known['size'] > 20 * 1024 * 1024) {
                throw new MockError(400, 'Bad Request: file is too big');
            }
            $out = ['file_id' => $id, 'file_unique_id' => $known['unique'], 'file_size' => (int) $known['size']];
            if (($known['stored'] ?? '') !== '') {
                $out['file_path'] = 'uploads/' . $known['stored'];
            }
            return $out;
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,200}$/', $id) && is_file($this->fixtures . '/' . $id)) {
            $size = (int) filesize($this->fixtures . '/' . $id);
            if ($size > 20 * 1024 * 1024) {
                throw new MockError(400, 'Bad Request: file is too big');
            }
            return ['file_id' => $id, 'file_unique_id' => substr(sha1('fx' . $id), 0, 16), 'file_size' => $size, 'file_path' => 'fixtures/' . $id];
        }
        throw new MockError(400, 'Bad Request: invalid file_id');
    }

    private function serveFile(string $token, string $path): void
    {
        $this->token = $token;
        $this->method = 'downloadFile';
        $this->params = ['file_path' => $path];
        if ($this->requiredToken !== null && $this->requiredToken !== '' && !hash_equals($this->requiredToken, $token)) {
            $this->record(401, ['ok' => false, 'description' => 'Unauthorized']);
            $this->json(401, ['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']);
            return;
        }
        $abs = null;
        if (preg_match('#^fixtures/([A-Za-z0-9][A-Za-z0-9._-]{0,200})$#', $path, $m)) {
            $abs = $this->fixtures . '/' . $m[1];
        } elseif (preg_match('#^uploads/([a-f0-9]{32}\.bin)$#', $path, $m)) {
            $abs = $this->filesDir() . '/' . $m[1];
        }
        if ($abs === null || !is_file($abs)) {
            $this->record(404, ['ok' => false, 'description' => 'Not Found']);
            $this->json(404, ['ok' => false, 'error_code' => 404, 'description' => 'Not Found']);
            return;
        }
        $this->record(200, ['ok' => true]);
        http_response_code(200);
        header('Content-Type: application/octet-stream');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
    }

    /**
     * Resolves an InputFile parameter: multipart upload, attach://<field>, known file_id or URL.
     * @return array<string,mixed> file record
     */
    private function inputFile(string $field, mixed $value, int $limit): array
    {
        $upload = null;
        if (isset($this->files[$field]) && ($value === null || $value === '')) {
            $upload = $this->files[$field];
        } elseif (is_string($value) && str_starts_with($value, 'attach://')) {
            $name = substr($value, 9);
            if (!isset($this->files[$name])) {
                throw new MockError(400, 'Bad Request: wrong HTTP URL specified');
            }
            $upload = $this->files[$name];
        } elseif (is_string($value) && $value !== '') {
            if (isset($this->state['files'][$value])) {
                return $this->state['files'][$value] + ['file_id' => $value];
            }
            if (preg_match('#^https?://#i', $value)) {
                return $this->registerFile(null, basename((string) parse_url($value, PHP_URL_PATH)) ?: 'file', 1024, 'remote');
            }
            throw new MockError(400, 'Bad Request: wrong file identifier/HTTP URL specified');
        }
        if ($upload === null) {
            if (isset($this->uploadErrors[$field])) {
                throw new MockError(413, 'Request Entity Too Large');
            }
            throw new MockError(400, 'Bad Request: there is no ' . $field . ' in the request');
        }
        if ($upload['size'] > $limit) {
            throw new MockError(400, 'Bad Request: file is too big');
        }
        if ($upload['size'] === 0) {
            throw new MockError(400, 'Bad Request: file must be non-empty');
        }
        return $this->registerFile($upload['tmp'], $upload['name'], $upload['size'], $this->sniff($upload['tmp']));
    }

    /** @return array<string,mixed> */
    private function registerFile(?string $tmp, string $name, int $size, string $kind, ?string $stickerFormat = null): array
    {
        $stored = '';
        if ($tmp !== null) {
            $dir = $this->filesDir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0700, true);
            }
            $stored = bin2hex(random_bytes(16)) . '.bin';
            if (!@copy($tmp, $dir . '/' . $stored)) {
                $stored = '';
            }
        }
        $id = 'BQACAgIAAx' . rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '=');
        $record = [
            'unique' => 'AgAD' . substr(bin2hex(random_bytes(8)), 0, 12),
            'size' => $size,
            'name' => mb_substr($name, 0, 120),
            'kind' => $kind,
            'stored' => $stored,
            'sticker_format' => $stickerFormat,
        ];
        $this->state['files'][$id] = $record;
        return $record + ['file_id' => $id];
    }

    /**
     * @param array<string,mixed> $file
     * @return array<string,mixed>
     */
    private function mediaPayload(string $field, array $file): array
    {
        $base = ['file_id' => $file['file_id'], 'file_unique_id' => $file['unique'], 'file_size' => (int) $file['size']];
        return match ($field) {
            'photo' => ['photo' => [
                $base + ['width' => 90, 'height' => 90],
                ['file_id' => $file['file_id'] . 'M', 'file_unique_id' => $file['unique'] . 'M', 'file_size' => (int) $file['size'], 'width' => 512, 'height' => 512],
            ]],
            'animation' => [
                'animation' => $base + ['width' => 512, 'height' => 512, 'duration' => 3, 'file_name' => $file['name'], 'mime_type' => self::mime((string) $file['kind'])],
                'document' => $base + ['file_name' => $file['name'], 'mime_type' => self::mime((string) $file['kind'])],
            ],
            'video' => ['video' => $base + ['width' => 512, 'height' => 512, 'duration' => 3, 'mime_type' => self::mime((string) $file['kind'])]],
            'audio' => ['audio' => $base + ['duration' => 3]],
            default => ['document' => $base + ['file_name' => $file['name'], 'mime_type' => self::mime((string) $file['kind'])]],
        };
    }

    // -------------------------------------------------------------- webhook

    private function setWebhook(): bool
    {
        $url = $this->str('url', false) ?? '';
        if ($url === '') {
            throw new MockError(400, 'Bad Request: url is empty');
        }
        if (!preg_match('#^https://[^\s/]+#i', $url)) {
            throw new MockError(400, 'Bad Request: bad webhook: An HTTPS URL must be provided for webhook');
        }
        $secret = $this->str('secret_token', false);
        if ($secret !== null && !preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
            throw new MockError(400, 'Bad Request: secret token contains unallowed characters');
        }
        $max = $this->has('max_connections') ? $this->intParam('max_connections') : 40;
        if ($max < 1 || $max > 100) {
            throw new MockError(400, 'Bad Request: bad webhook: max_connections must be between 1 and 100');
        }
        $allowed = $this->has('allowed_updates') ? $this->jsonParam('allowed_updates') : [];
        foreach ($allowed as $type) {
            if (!is_string($type)) {
                throw new MockError(400, "Bad Request: can't parse allowed_updates");
            }
        }
        $this->state['webhook'] = [
            'url' => $url,
            'secret_token' => $secret,
            'max_connections' => $max,
            'allowed_updates' => array_values($allowed),
            'drop_pending_updates' => $this->boolParam('drop_pending_updates'),
        ];
        return true;
    }

    private function deleteWebhook(): bool
    {
        $this->boolParam('drop_pending_updates');
        unset($this->state['webhook']);
        return true;
    }

    /** @return array<string,mixed> */
    private function getWebhookInfo(): array
    {
        $hook = $this->state['webhook'] ?? null;
        if (!is_array($hook)) {
            return ['url' => '', 'has_custom_certificate' => false, 'pending_update_count' => 0];
        }
        $info = ['url' => $hook['url'], 'has_custom_certificate' => false, 'pending_update_count' => 0, 'max_connections' => $hook['max_connections'], 'ip_address' => '127.0.0.1'];
        if ($hook['allowed_updates'] !== []) {
            $info['allowed_updates'] = $hook['allowed_updates'];
        }
        return $info;
    }

    private function setMyCommands(): bool
    {
        $commands = $this->jsonParam('commands');
        if (!array_is_list($commands) || count($commands) > 100) {
            throw new MockError(400, 'Bad Request: too many commands specified');
        }
        foreach ($commands as $c) {
            if (!is_array($c) || !is_string($c['command'] ?? null) || !preg_match('/^[a-z0-9_]{1,32}$/', $c['command'])) {
                throw new MockError(400, 'Bad Request: BOT_COMMAND_INVALID');
            }
            $d = $c['description'] ?? null;
            if (!is_string($d) || trim($d) === '' || mb_strlen($d) > 256) {
                throw new MockError(400, 'Bad Request: command description must be non-empty');
            }
        }
        $this->state['commands'][$this->scopeKey()] = $commands;
        return true;
    }

    private function scopeKey(): string
    {
        $scope = $this->has('scope') ? $this->jsonParam('scope') : ['type' => 'default'];
        if (!in_array($scope['type'] ?? null, self::SCOPES, true)) {
            throw new MockError(400, 'Bad Request: wrong bot command scope type specified');
        }
        return $scope['type'] . ':' . ($scope['chat_id'] ?? '') . ':' . ($this->str('language_code', false) ?? '');
    }

    // --------------------------------------------------------- sticker sets

    /** @return array<string,mixed> */
    private function uploadStickerFile(): array
    {
        $this->userId();
        $format = $this->str('sticker_format', false);
        if (!in_array($format, self::STICKER_FORMATS, true)) {
            throw new MockError(400, 'Bad Request: wrong sticker_format specified');
        }
        if (!isset($this->files['sticker'])) {
            throw new MockError(400, 'Bad Request: there is no sticker in the request');
        }
        $upload = $this->files['sticker'];
        $this->checkStickerFile($upload['tmp'], $upload['size'], $format);
        $file = $this->registerFile($upload['tmp'], $upload['name'], $upload['size'], $this->sniff($upload['tmp']), $format);
        return ['file_id' => $file['file_id'], 'file_unique_id' => $file['unique'], 'file_size' => $file['size']];
    }

    private function createNewStickerSet(): bool
    {
        $user = $this->userId();
        $name = $this->str('name', false) ?? '';
        $this->checkSetName($name);
        if (isset($this->state['sets'][strtolower($name)])) {
            throw new MockError(400, 'Bad Request: sticker set name is already occupied');
        }
        $title = $this->checkTitle();
        $type = $this->str('sticker_type', false) ?? 'regular';
        if (!in_array($type, ['regular', 'mask', 'custom_emoji'], true)) {
            throw new MockError(400, 'Bad Request: wrong sticker_type specified');
        }
        $repaint = $this->boolParam('needs_repainting');
        if ($repaint && $type !== 'custom_emoji') {
            throw new MockError(400, 'Bad Request: needs_repainting can be used only for custom emoji sticker sets');
        }
        $stickers = $this->jsonParam('stickers');
        if (!array_is_list($stickers) || $stickers === []) {
            throw new MockError(400, 'Bad Request: stickers must be non-empty');
        }
        if (count($stickers) > 50) {
            throw new MockError(400, 'Bad Request: too many stickers specified');
        }
        $resolved = [];
        foreach ($stickers as $s) {
            $resolved[] = $this->inputSticker($s, $type);
        }
        $this->state['sets'][strtolower($name)] = [
            'name' => $name,
            'title' => $title,
            'sticker_type' => $type,
            'needs_repainting' => $repaint,
            'owner' => $user,
            'stickers' => $resolved,
        ];
        return true;
    }

    private function addStickerToSet(): bool
    {
        $user = $this->userId();
        $key = strtolower($this->str('name', false) ?? '');
        $set = $this->state['sets'][$key] ?? null;
        if (!is_array($set)) {
            throw new MockError(400, 'Bad Request: STICKERSET_INVALID');
        }
        if ((int) $set['owner'] !== $user) {
            throw new MockError(400, 'Bad Request: USER_ID_INVALID');
        }
        $limit = $set['sticker_type'] === 'custom_emoji' ? 200 : 120;
        if (count($set['stickers']) >= $limit) {
            throw new MockError(400, 'Bad Request: STICKERS_TOO_MUCH');
        }
        $this->state['sets'][$key]['stickers'][] = $this->inputSticker($this->jsonParam('sticker'), (string) $set['sticker_type']);
        return true;
    }

    /** @return array<string,mixed> */
    private function getStickerSet(): array
    {
        $set = $this->state['sets'][strtolower($this->str('name', false) ?? '')] ?? null;
        if (!is_array($set)) {
            throw new MockError(400, 'Bad Request: STICKERSET_INVALID');
        }
        $size = $set['sticker_type'] === 'custom_emoji' ? 100 : 512;
        $stickers = [];
        foreach ($set['stickers'] as $s) {
            $sticker = [
                'file_id' => $s['file_id'],
                'file_unique_id' => $s['file_unique_id'],
                'type' => $set['sticker_type'],
                'width' => $size,
                'height' => $size,
                'is_animated' => $s['format'] === 'animated',
                'is_video' => $s['format'] === 'video',
                'emoji' => $s['emoji_list'][0] ?? '',
                'set_name' => $set['name'],
                'file_size' => $s['file_size'],
            ];
            if ($s['custom_emoji_id'] !== null) {
                $sticker['custom_emoji_id'] = $s['custom_emoji_id'];
            }
            if (!empty($set['needs_repainting'])) {
                $sticker['needs_repainting'] = true;
            }
            $stickers[] = $sticker;
        }
        return ['name' => $set['name'], 'title' => $set['title'], 'sticker_type' => $set['sticker_type'], 'stickers' => $stickers];
    }

    private function deleteStickerFromSet(): bool
    {
        $fileId = $this->str('sticker', false) ?? '';
        foreach ($this->state['sets'] ?? [] as $key => $set) {
            foreach ($set['stickers'] as $i => $s) {
                if ($s['file_id'] === $fileId) {
                    array_splice($this->state['sets'][$key]['stickers'], $i, 1);
                    return true;
                }
            }
        }
        throw new MockError(400, 'Bad Request: STICKER_INVALID');
    }

    private function setStickerSetTitle(): bool
    {
        $key = strtolower($this->str('name', false) ?? '');
        if (!isset($this->state['sets'][$key])) {
            throw new MockError(400, 'Bad Request: STICKERSET_INVALID');
        }
        $this->state['sets'][$key]['title'] = $this->checkTitle();
        return true;
    }

    private function deleteStickerSet(): bool
    {
        $key = strtolower($this->str('name', false) ?? '');
        if (!isset($this->state['sets'][$key])) {
            throw new MockError(400, 'Bad Request: STICKERSET_INVALID');
        }
        unset($this->state['sets'][$key]);
        return true;
    }

    /** Validates an InputSticker {sticker, format, emoji_list, keywords?} and returns the stored sticker. */
    private function inputSticker(mixed $s, string $setType): array
    {
        if (!is_array($s)) {
            throw new MockError(400, "Bad Request: can't parse InputSticker JSON object");
        }
        $format = $s['format'] ?? null;
        if (!in_array($format, self::STICKER_FORMATS, true)) {
            throw new MockError(400, 'Bad Request: wrong sticker format specified');
        }
        $ref = $s['sticker'] ?? null;
        if (!is_string($ref) || $ref === '') {
            throw new MockError(400, 'Bad Request: sticker is empty');
        }
        if (str_starts_with($ref, 'attach://')) {
            $field = substr($ref, 9);
            if (!isset($this->files[$field])) {
                throw new MockError(400, 'Bad Request: wrong HTTP URL specified');
            }
            $upload = $this->files[$field];
            $this->checkStickerFile($upload['tmp'], $upload['size'], $format);
            $file = $this->registerFile($upload['tmp'], $upload['name'], $upload['size'], $this->sniff($upload['tmp']), $format);
        } elseif (isset($this->state['files'][$ref])) {
            $file = $this->state['files'][$ref] + ['file_id' => $ref];
            if (($file['sticker_format'] ?? null) !== null && $file['sticker_format'] !== $format) {
                throw new MockError(400, 'Bad Request: STICKER_FILE_INVALID');
            }
        } elseif (preg_match('#^https?://#i', $ref)) {
            $file = $this->registerFile(null, 'remote', 1024, 'remote', $format);
        } else {
            throw new MockError(400, 'Bad Request: wrong file identifier/HTTP URL specified');
        }
        $emoji = $s['emoji_list'] ?? null;
        if (!is_array($emoji) || $emoji === [] || count($emoji) > 20) {
            throw new MockError(400, 'Bad Request: emoji_list must contain 1-20 emoji');
        }
        foreach ($emoji as $e) {
            if (!is_string($e) || trim($e) === '' || preg_match('/[A-Za-z0-9\s]/', $e) && !preg_match('/\x{20E3}/u', $e)) {
                throw new MockError(400, 'Bad Request: STICKER_EMOJI_INVALID');
            }
        }
        $keywords = $s['keywords'] ?? [];
        if (!is_array($keywords) || count($keywords) > 20 || mb_strlen(implode('', array_map('strval', $keywords))) > 64) {
            throw new MockError(400, 'Bad Request: keywords must be 0-20 strings with a total length of up to 64 characters');
        }
        if (isset($s['mask_position']) && $setType !== 'mask') {
            throw new MockError(400, 'Bad Request: mask_position can be specified only for mask stickers');
        }
        return [
            'file_id' => 'CAACAgIAAx' . rtrim(strtr(base64_encode(random_bytes(30)), '+/', '-_'), '='),
            'file_unique_id' => $file['unique'],
            'format' => $format,
            'emoji_list' => array_values($emoji),
            'keywords' => array_values($keywords),
            'custom_emoji_id' => $setType === 'custom_emoji' ? (string) random_int(5000000000000000000, PHP_INT_MAX) : null,
            'file_size' => (int) $file['size'],
        ];
    }

    private function checkStickerFile(string $tmp, int $size, string $format): void
    {
        $head = (string) @file_get_contents($tmp, false, null, 0, 16);
        if ($format === 'static' && !(str_starts_with($head, "\x89PNG") || (str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP'))) {
            throw new MockError(400, 'Bad Request: STICKER_PNG_NOPNG');
        }
        if ($format === 'animated' && !str_starts_with($head, "\x1f\x8b")) {
            throw new MockError(400, 'Bad Request: STICKER_TGS_NOTGS');
        }
        if ($format === 'video' && !str_starts_with($head, "\x1a\x45\xdf\xa3")) {
            throw new MockError(400, 'Bad Request: STICKER_VIDEO_NOWEBM');
        }
        if ($size > self::STICKER_LIMITS[$format]) {
            throw new MockError(400, 'Bad Request: file is too big');
        }
    }

    private function checkSetName(string $name): void
    {
        $suffix = '_by_' . strtolower($this->botUsername);
        if ($name === '' || strlen($name) > 64 || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)
            || str_contains($name, '__') || !str_ends_with(strtolower($name), $suffix) || strlen($name) <= strlen($suffix)) {
            throw new MockError(400, 'Bad Request: invalid sticker set name is specified');
        }
    }

    private function checkTitle(): string
    {
        $title = $this->str('title', false) ?? '';
        if (trim($title) === '' || $this->utf16Length($title) > 64) {
            throw new MockError(400, 'Bad Request: invalid sticker set title is specified');
        }
        return $title;
    }

    // ----------------------------------------------------------- validation

    private function chatId(): int|string
    {
        $chat = $this->params['chat_id'] ?? null;
        if (is_int($chat)) {
            $id = $chat;
        } elseif (is_string($chat) && preg_match('/^-?\d{1,20}$/', $chat)) {
            $id = (int) $chat;
        } elseif (is_string($chat) && preg_match('/^@[A-Za-z][A-Za-z0-9_]{3,31}$/', $chat)) {
            $id = $chat;
        } elseif ($chat === null || $chat === '') {
            throw new MockError(400, 'Bad Request: chat_id is empty');
        } else {
            throw new MockError(400, 'Bad Request: chat not found');
        }
        $blocked = $this->control()['blocked'] ?? [];
        if (is_array($blocked) && in_array((string) $id, array_map('strval', $blocked), true)) {
            throw new MockError(403, 'Forbidden: bot was blocked by the user');
        }
        return $id;
    }

    private function userId(): int
    {
        $user = $this->params['user_id'] ?? null;
        if (is_int($user) || (is_string($user) && preg_match('/^\d{1,20}$/', $user))) {
            $user = (int) $user;
            if ($user > 0) {
                return $user;
            }
        }
        throw new MockError(400, 'Bad Request: USER_ID_INVALID');
    }

    private function text(string $name, int $max, string $emptyError, string $longError): string
    {
        $text = $this->str($name, false) ?? '';
        if (trim($text) === '') {
            throw new MockError(400, 'Bad Request: ' . $emptyError);
        }
        if ($this->utf16Length($text) > $max) {
            throw new MockError(400, 'Bad Request: ' . $longError);
        }
        return $text;
    }

    private function parseMode(): void
    {
        $mode = $this->str('parse_mode', false);
        if ($mode !== null && $mode !== '' && !in_array($mode, ['HTML', 'Markdown', 'MarkdownV2'], true)) {
            throw new MockError(400, 'Bad Request: unsupported parse_mode');
        }
    }

    /** @return array<string,mixed>|null */
    private function replyMarkup(bool $inlineOnly): ?array
    {
        if (!$this->has('reply_markup')) {
            return null;
        }
        $markup = $this->jsonParam('reply_markup');
        if (isset($markup['inline_keyboard'])) {
            $rows = $markup['inline_keyboard'];
            if (!is_array($rows) || !array_is_list($rows)) {
                throw new MockError(400, 'Bad Request: field "inline_keyboard" of the InlineKeyboardMarkup should be an Array of Arrays');
            }
            $count = 0;
            foreach ($rows as $row) {
                if (!is_array($row) || !array_is_list($row)) {
                    throw new MockError(400, 'Bad Request: field "inline_keyboard" of the InlineKeyboardMarkup should be an Array of Arrays');
                }
                foreach ($row as $button) {
                    $count++;
                    if (!is_array($button) || !is_string($button['text'] ?? null) || trim($button['text']) === '') {
                        throw new MockError(400, 'Bad Request: text of an inline keyboard button must be non-empty');
                    }
                    $actions = array_intersect(array_keys($button), self::BUTTON_ACTIONS);
                    if (count($actions) === 0) {
                        throw new MockError(400, 'Bad Request: text buttons are unallowed in the inline keyboard');
                    }
                    if (count($actions) > 1) {
                        throw new MockError(400, 'Bad Request: can\'t parse inline keyboard button: exactly one of the optional fields must be used');
                    }
                    if (isset($button['callback_data'])) {
                        $data = $button['callback_data'];
                        if (!is_string($data) || strlen($data) < 1 || strlen($data) > 64) {
                            throw new MockError(400, 'Bad Request: BUTTON_DATA_INVALID');
                        }
                    }
                    if (isset($button['url']) && (!is_string($button['url']) || !preg_match('#^(https?|tg)://\S+$#i', $button['url']))) {
                        throw new MockError(400, 'Bad Request: inline keyboard button URL is invalid: Wrong HTTP URL');
                    }
                }
            }
            if ($count > 100) {
                throw new MockError(400, 'Bad Request: reply markup is too long');
            }
            return $markup;
        }
        if ($inlineOnly) {
            throw new MockError(400, 'Bad Request: inline keyboard expected');
        }
        if (isset($markup['keyboard'])) {
            if (!is_array($markup['keyboard']) || !array_is_list($markup['keyboard'])) {
                throw new MockError(400, 'Bad Request: field "keyboard" of the ReplyKeyboardMarkup should be an Array of Arrays');
            }
            foreach ($markup['keyboard'] as $row) {
                if (!is_array($row) || !array_is_list($row)) {
                    throw new MockError(400, 'Bad Request: field "keyboard" of the ReplyKeyboardMarkup should be an Array of Arrays');
                }
                foreach ($row as $button) {
                    $text = is_string($button) ? $button : (is_array($button) ? ($button['text'] ?? null) : null);
                    if (!is_string($text) || trim($text) === '') {
                        throw new MockError(400, 'Bad Request: keyboard button text must be non-empty');
                    }
                }
            }
            if (isset($markup['input_field_placeholder']) && mb_strlen((string) $markup['input_field_placeholder']) > 64) {
                throw new MockError(400, 'Bad Request: input field placeholder is too long');
            }
            return $markup;
        }
        if (!empty($markup['remove_keyboard']) || !empty($markup['force_reply'])) {
            return $markup;
        }
        throw new MockError(400, "Bad Request: can't parse reply keyboard markup JSON object");
    }

    private function has(string $name): bool
    {
        return isset($this->params[$name]) && $this->params[$name] !== '';
    }

    private function str(string $name, bool $required = true): ?string
    {
        $v = $this->params[$name] ?? null;
        if ($v === null) {
            if ($required) {
                throw new MockError(400, 'Bad Request: ' . $name . ' is empty');
            }
            return null;
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        return is_scalar($v) ? (string) $v : null;
    }

    private function intParam(string $name): int
    {
        $v = $this->params[$name] ?? null;
        if (is_int($v) || (is_string($v) && preg_match('/^-?\d{1,20}$/', $v))) {
            return (int) $v;
        }
        throw new MockError(400, 'Bad Request: ' . ($v === null ? $name . ' is empty' : 'invalid ' . $name . ' specified'));
    }

    private function boolParam(string $name): bool
    {
        $v = $this->params[$name] ?? false;
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower((string) $v), ['1', 'true'], true);
    }

    /** @return array<mixed> */
    private function jsonParam(string $name): array
    {
        $v = $this->params[$name] ?? null;
        if (is_array($v)) {
            return $v;
        }
        if (!is_string($v) || $v === '') {
            throw new MockError(400, 'Bad Request: ' . $name . ' is empty');
        }
        $decoded = json_decode($v, true);
        if (!is_array($decoded)) {
            throw new MockError(400, "Bad Request: can't parse " . $name . ' JSON object');
        }
        return $decoded;
    }

    private function optionalJson(string $name): void
    {
        if ($this->has($name)) {
            $this->jsonParam($name);
        }
    }

    private function utf16Length(string $text): int
    {
        return (int) (strlen((string) mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')) / 2);
    }

    private function sniff(string $file): string
    {
        $head = (string) @file_get_contents($file, false, null, 0, 16);
        return match (true) {
            str_starts_with($head, "\x89PNG") => 'png',
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($head, 'GIF8') => 'gif',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            str_starts_with($head, "\x1a\x45\xdf\xa3") => 'webm',
            str_starts_with($head, "\x1f\x8b") => 'tgs',
            substr($head, 4, 4) === 'ftyp' => 'mp4',
            str_starts_with($head, "PK\x03\x04") => 'zip',
            default => 'bin',
        };
    }

    private static function mime(string $kind): string
    {
        return [
            'png' => 'image/png', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'webm' => 'video/webm',
            'tgs' => 'application/x-tgsticker', 'mp4' => 'video/mp4', 'zip' => 'application/zip',
        ][$kind] ?? 'application/octet-stream';
    }

    // ---------------------------------------------------- messages helpers

    /** @return array<string,mixed> */
    private function botUser(): array
    {
        return ['id' => (int) explode(':', $this->token)[0], 'is_bot' => true, 'first_name' => 'Emoji Maker', 'username' => $this->botUsername];
    }

    /**
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    private function message(int|string $chat, array $extra, ?int $id = null): array
    {
        if ($id === null) {
            $seq = (int) ($this->state['seq'][(string) $chat] ?? 100) + 1;
            $this->state['seq'][(string) $chat] = $seq;
            $id = $seq;
        }
        $chatObj = is_int($chat) && $chat > 0
            ? ['id' => $chat, 'type' => 'private', 'first_name' => 'Test']
            : ['id' => is_int($chat) ? $chat : -1001234567890, 'type' => 'supergroup', 'title' => 'Test'];
        return ['message_id' => $id, 'from' => $this->botUser(), 'chat' => $chatObj, 'date' => time()] + $extra;
    }

    /** @param array<mixed>|null $keyboard */
    private function storeMessage(int|string $chat, int $id, string $text, ?array $keyboard): void
    {
        $this->state['messages'][$chat . ':' . $id] = ['text' => $text, 'keyboard' => $keyboard, 'deleted' => false];
    }

    // ------------------------------------------------- request / state I/O

    private function collectParams(): void
    {
        $params = $_GET;
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));
        if (str_starts_with($type, 'application/json')) {
            $json = json_decode((string) file_get_contents('php://input'), true);
            if (is_array($json)) {
                $params = array_merge($params, $json);
            }
        } else {
            $params = array_merge($params, $_POST);
        }
        $this->params = $params;
        $this->files = [];
        $this->uploadErrors = [];
        foreach ($_FILES as $field => $f) {
            if (!is_array($f) || is_array($f['error'] ?? null)) {
                continue;
            }
            if ((int) $f['error'] === UPLOAD_ERR_OK && is_file((string) $f['tmp_name'])) {
                $this->files[(string) $field] = [
                    'tmp' => (string) $f['tmp_name'],
                    'name' => (string) $f['name'],
                    'size' => (int) $f['size'],
                    'type' => (string) $f['type'],
                ];
            } else {
                $this->uploadErrors[(string) $field] = (int) $f['error'];
            }
        }
    }

    /**
     * Applies the first matching control rule.
     * @return array<string,mixed> ['status','raw'] for a raw answer, ['result'] for a forced result, [] to continue
     */
    private function applyControl(): array
    {
        if ($this->controlFile === null || !is_file($this->controlFile)) {
            return [];
        }
        $fh = fopen($this->controlFile, 'c+');
        if ($fh === false) {
            return [];
        }
        flock($fh, LOCK_EX);
        $control = json_decode((string) stream_get_contents($fh), true);
        $out = [];
        $error = null;
        $sleep = 0;
        if (is_array($control) && is_array($control['rules'] ?? null)) {
            foreach ($control['rules'] as $i => $rule) {
                if (!is_array($rule) || !$this->ruleMatches($rule)) {
                    continue;
                }
                if (isset($rule['times'])) {
                    $control['rules'][$i]['times'] = (int) $rule['times'] - 1;
                    if ($control['rules'][$i]['times'] <= 0) {
                        unset($control['rules'][$i]);
                    }
                    $control['rules'] = array_values($control['rules']);
                    ftruncate($fh, 0);
                    rewind($fh);
                    fwrite($fh, (string) json_encode($control, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
                $sleep = (int) ($rule['sleep_ms'] ?? 0);
                if (isset($rule['error_code'])) {
                    $error = new MockError(
                        (int) $rule['error_code'],
                        (string) ($rule['description'] ?? 'Error'),
                        isset($rule['retry_after']) ? (int) $rule['retry_after'] : null,
                        isset($rule['migrate_to_chat_id']) ? (int) $rule['migrate_to_chat_id'] : null,
                    );
                } elseif (isset($rule['http_status'])) {
                    $out = ['status' => (int) $rule['http_status'], 'raw' => (string) ($rule['raw'] ?? '')];
                } elseif (array_key_exists('result', $rule)) {
                    $out = ['result' => $rule['result']];
                }
                break;
            }
        }
        flock($fh, LOCK_UN);
        fclose($fh);
        if ($sleep > 0) {
            usleep($sleep * 1000);
        }
        if ($error !== null) {
            throw $error;
        }
        return $out;
    }

    /** @param array<string,mixed> $rule */
    private function ruleMatches(array $rule): bool
    {
        $method = (string) ($rule['method'] ?? '*');
        if ($method !== '*' && strcasecmp($method, $this->method) !== 0) {
            return false;
        }
        foreach ((array) ($rule['where'] ?? []) as $param => $value) {
            $actual = $this->params[$param] ?? null;
            if (is_array($actual)) {
                $actual = json_encode($actual);
            }
            if ((string) $actual !== (string) $value) {
                return false;
            }
        }
        return true;
    }

    /** @return array<string,mixed> */
    private function control(): array
    {
        if ($this->controlFile === null || !is_file($this->controlFile)) {
            return [];
        }
        $control = json_decode((string) @file_get_contents($this->controlFile), true);
        return is_array($control) ? $control : [];
    }

    private function locked(callable $fn): void
    {
        $lock = fopen($this->stateFile . '.lock', 'c');
        if ($lock === false) {
            $fn();
            return;
        }
        flock($lock, LOCK_EX);
        try {
            $fn();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array<string,mixed> */
    private function loadState(): array
    {
        $state = is_file($this->stateFile) ? json_decode((string) file_get_contents($this->stateFile), true) : null;
        return is_array($state) ? $state : [];
    }

    private function saveState(): void
    {
        $tmp = $this->stateFile . '.tmp';
        file_put_contents($tmp, (string) json_encode($this->state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
        rename($tmp, $this->stateFile);
    }

    private function filesDir(): string
    {
        return dirname($this->stateFile) . '/mock_files';
    }

    /** @param array<string,mixed> $response */
    private function record(int $status, array $response): void
    {
        if ($this->logFile === null || $this->logFile === '') {
            return;
        }
        $params = [];
        foreach ($this->params as $k => $v) {
            if (is_string($v) && in_array($k, self::JSON_FIELDS, true)) {
                $decoded = json_decode($v, true);
                $params[$k] = $decoded ?? $v;
            } else {
                $params[$k] = $v;
            }
        }
        $files = [];
        foreach ($this->files as $field => $f) {
            $files[$field] = ['name' => $f['name'], 'size' => $f['size'], 'type' => $f['type']];
        }
        $entry = [
            'time' => microtime(true),
            'method' => $this->method,
            'http' => $status,
            'ok' => ($response['ok'] ?? false) === true,
            'params' => $params,
            'files' => $files,
        ];
        if (isset($response['description'])) {
            $entry['error'] = $response['description'];
        }
        file_put_contents($this->logFile, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . "\n", FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $payload */
    private function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}

$stateFile = (string) (getenv('MOCK_STATE') ?: sys_get_temp_dir() . '/telegram_mock_' . (int) ($_SERVER['SERVER_PORT'] ?? 0) . '.json');
$stateDir = dirname($stateFile);
if (!is_dir($stateDir)) {
    @mkdir($stateDir, 0700, true);
}
(new TelegramMock(
    $stateFile,
    getenv('MOCK_LOG') ?: null,
    getenv('MOCK_CONTROL') ?: null,
    rtrim((string) (getenv('MOCK_FIXTURES') ?: dirname(__DIR__) . '/fixtures'), '/'),
    ltrim((string) (getenv('MOCK_BOT_USERNAME') ?: 'emoji_maker_dev_bot'), '@'),
    getenv('MOCK_TOKEN') ?: null,
))->serve();

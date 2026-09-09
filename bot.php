<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';

/**
 * ============================================================================
 * bot.php — Telegram bot: API client, admin panel, channel management,
 * text/entity management (Premium emoji-safe), quote/reply, webhook router.
 * ============================================================================
 */

// ============================================================================
// SECTION 1 — TELEGRAM CLIENT
// ============================================================================

final class TelegramClient
{
    private string $apiBase;

    public function __construct(?string $token = null)
    {
        $this->apiBase = 'https://api.telegram.org/bot' . ($token ?? Config::telegramBotToken());
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    public function request(string $method, array $params = []): array
    {
        $url = $this->apiBase . '/' . $method;
        $body = json_encode($params, JSON_UNESCAPED_UNICODE);

        $attempt = 0;
        while (true) {
            $attempt++;
            $res = HttpClient::request('POST', $url, ['Content-Type' => 'application/json'], $body ?: '{}', 0);
            $json = $res['json'];

            if ($json !== null && ($json['ok'] ?? false) === true) {
                return $json;
            }

            $errorCode = $json['error_code'] ?? $res['status'];
            if ($errorCode === 429 && $attempt <= Config::maxRetries()) {
                $retryAfter = (int) ($json['parameters']['retry_after'] ?? 1);
                Logger::warning('telegram', 'rate limited, retrying', ['method' => $method, 'retry_after' => $retryAfter]);
                sleep(max(1, $retryAfter));
                continue;
            }

            Logger::error('telegram', "$method failed", ['status' => $res['status'], 'description' => $json['description'] ?? null]);
            return $json ?? ['ok' => false, 'description' => 'no response'];
        }
    }

    /**
     * @param array<int,array<string,mixed>> $entities
     * @param array<string,mixed> $opts extra API params: reply_markup, disable_notification, message_thread_id, reply_parameters, etc.
     */
    public function sendMessage(int|string $chatId, string $text, array $entities = [], array $opts = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'entities' => $entities,
        ], $opts);
        return $this->request('sendMessage', $params);
    }

    /**
     * sendPhoto with the image uploaded inline as multipart/form-data — the
     * card is generated in memory and never written to disk, so there is no
     * public URL for Telegram to fetch and nothing to clean up afterwards.
     *
     * Telegram caps a caption at 1024 characters; callers that may exceed
     * that should send the photo bare and follow it with a text message.
     *
     * @param array<int,array<string,mixed>> $captionEntities
     * @param array<string,mixed> $opts extra API params (reply_parameters, disable_notification, ...)
     */
    public function sendPhoto(int|string $chatId, string $photo, string $caption = '', array $captionEntities = [], array $opts = []): array
    {
        $fields = ['chat_id' => (string) $chatId];
        if ($caption !== '') {
            $fields['caption'] = $caption;
            if (!empty($captionEntities)) {
                $fields['caption_entities'] = json_encode($captionEntities, JSON_UNESCAPED_UNICODE) ?: '[]';
            }
        }
        foreach ($opts as $key => $value) {
            $fields[$key] = is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '');
        }
        return $this->requestMultipart('sendPhoto', $fields, ['photo' => ['filename' => 'signal.png', 'type' => 'image/png', 'content' => $photo]]);
    }

    /**
     * @param array<string,string> $fields
     * @param array<string,array{filename:string,type:string,content:string}> $files
     * @return array<string,mixed>
     */
    private function requestMultipart(string $method, array $fields, array $files): array
    {
        $boundary = '----SignalBot' . bin2hex(random_bytes(12));
        $eol = "\r\n";
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Disposition: form-data; name="' . $name . '"' . $eol . $eol;
            $body .= $value . $eol;
        }
        foreach ($files as $name => $file) {
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Disposition: form-data; name="' . $name . '"; filename="' . $file['filename'] . '"' . $eol;
            $body .= 'Content-Type: ' . $file['type'] . $eol . $eol;
            $body .= $file['content'] . $eol;
        }
        $body .= '--' . $boundary . '--' . $eol;

        $attempt = 0;
        while (true) {
            $attempt++;
            $res = HttpClient::request(
                'POST',
                $this->apiBase . '/' . $method,
                ['Content-Type' => 'multipart/form-data; boundary=' . $boundary],
                $body,
                0
            );
            $json = $res['json'];
            if ($json !== null && ($json['ok'] ?? false) === true) {
                return $json;
            }

            $errorCode = $json['error_code'] ?? $res['status'];
            if ($errorCode === 429 && $attempt <= Config::maxRetries()) {
                sleep(max(1, (int) ($json['parameters']['retry_after'] ?? 1)));
                continue;
            }

            Logger::error('telegram', "$method failed", ['status' => $res['status'], 'description' => $json['description'] ?? null]);
            return $json ?? ['ok' => false, 'description' => 'no response'];
        }
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, array $entities = [], array $opts = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'entities' => $entities,
        ], $opts);
        return $this->request('editMessageText', $params);
    }

    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup): array
    {
        return $this->request('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    public function pinChatMessage(int|string $chatId, int $messageId, bool $disableNotification = true): array
    {
        return $this->request('pinChatMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'disable_notification' => $disableNotification,
        ]);
    }

    public function unpinChatMessage(int|string $chatId, ?int $messageId = null): array
    {
        $params = ['chat_id' => $chatId];
        if ($messageId !== null) {
            $params['message_id'] = $messageId;
        }
        return $this->request('unpinChatMessage', $params);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): array
    {
        $params = ['callback_query_id' => $callbackQueryId, 'show_alert' => $showAlert];
        if ($text !== null) {
            $params['text'] = $text;
        }
        return $this->request('answerCallbackQuery', $params);
    }

    public function getChat(int|string $chatId): array
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->request('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    public function getMe(): array
    {
        return $this->request('getMe');
    }

    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member', 'channel_post'],
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->request('deleteWebhook');
    }
}

// ============================================================================
// SECTION 2 — TEXT FORMAT MANAGER (Premium Emoji / entity-preserving store)
// ============================================================================

final class TextFormatManager
{
    /**
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public function get(string $key): array
    {
        $stmt = Database::pdo()->prepare('SELECT text_value, entities FROM text_formats WHERE text_key = :k LIMIT 1');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        if ($row === false) {
            return ['text' => '', 'entities' => []];
        }
        $entities = json_decode((string) $row['entities'], true);
        return ['text' => (string) $row['text_value'], 'entities' => is_array($entities) ? $entities : []];
    }

    /**
     * Persists raw Telegram $text + $entities exactly as received from
     * getUpdates/webhook — never re-derived from Markdown, so
     * custom_emoji/bold/italic/underline/strikethrough/spoiler/code/pre/
     * text_link/mention all survive verbatim.
     *
     * @param array<int,array<string,mixed>> $entities
     */
    public function set(string $key, string $text, array $entities, ?int $updatedBy = null): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO text_formats (text_key, text_value, entities, updated_at, updated_by)
             VALUES (:k, :v, :e, :now, :by)
             ON CONFLICT(text_key) DO UPDATE SET text_value = excluded.text_value, entities = excluded.entities,
                updated_at = excluded.updated_at, updated_by = excluded.updated_by'
        );
        $stmt->execute([
            ':k' => $key, ':v' => $text, ':e' => json_encode($entities, JSON_UNESCAPED_UNICODE),
            ':now' => date('Y-m-d H:i:s'), ':by' => $updatedBy,
        ]);
    }

    /**
     * Renders a stored text with {placeholder} substitution while keeping
     * every entity's offset/length correct (see TelegramEntityUtils).
     * @param array<string,string> $placeholders
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public function render(string $key, array $placeholders = []): array
    {
        $stored = $this->get($key);
        if ($stored['text'] === '') {
            return $stored;
        }
        return TelegramEntityUtils::renderTemplate($stored['text'], $stored['entities'], $placeholders);
    }

    /** @return string[] all known text keys, for the admin panel list */
    public function listKeys(): array
    {
        $stmt = Database::pdo()->query('SELECT text_key FROM text_formats ORDER BY text_key ASC');
        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}

// ============================================================================
// SECTION 3 — CHANNEL MANAGER
// ============================================================================

final class ChannelManager
{
    public function __construct(private TelegramClient $telegram)
    {
    }

    public function add(int $chatId, ?int $addedBy = null): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO channels (chat_id, title, type, is_active, bot_status, can_post, added_by, created_at, updated_at)
             VALUES (:cid, NULL, \'channel\', 0, \'unknown\', 0, :by, :now1, :now2)
             ON CONFLICT(chat_id) DO UPDATE SET updated_at = excluded.updated_at'
        );
        $stmt->execute([':cid' => $chatId, ':by' => $addedBy, ':now1' => $now, ':now2' => $now]);

        $id = (int) Database::pdo()->lastInsertId();
        if ($id === 0) {
            $row = $this->findByChatId($chatId);
            $id = $row !== null ? (int) $row['id'] : 0;
        }
        if ($id > 0) {
            $this->ensureSettings($id);
        }
        $this->refreshAccessStatus($chatId);
        return $id;
    }

    public function edit(int $channelId, array $fields): void
    {
        $allowed = array_intersect_key($fields, array_flip(['title', 'username']));
        if (empty($allowed)) {
            return;
        }
        $sets = [];
        $params = [':id' => $channelId];
        foreach ($allowed as $k => $v) {
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $sets[] = 'updated_at = :now';
        $params[':now'] = date('Y-m-d H:i:s');
        $sql = 'UPDATE channels SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::pdo()->prepare($sql)->execute($params);
    }

    public function remove(int $channelId): void
    {
        Database::pdo()->prepare('DELETE FROM channels WHERE id = :id')->execute([':id' => $channelId]);
    }

    public function toggleActive(int $channelId): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT is_active FROM channels WHERE id = :id');
        $stmt->execute([':id' => $channelId]);
        $current = (int) $stmt->fetchColumn();
        $new = $current ? 0 : 1;
        $pdo->prepare('UPDATE channels SET is_active = :a, updated_at = :now WHERE id = :id')
            ->execute([':a' => $new, ':now' => date('Y-m-d H:i:s'), ':id' => $channelId]);
        return (bool) $new;
    }

    /**
     * Verifies the bot's own membership/admin status in the target chat
     * before it may be activated. Persists bot_status/can_post.
     * @return array{ok:bool, status:string, can_post:bool, reason?:string}
     */
    public function checkBotAccess(int $chatId): array
    {
        $me = $this->telegram->getMe();
        if (!($me['ok'] ?? false)) {
            return ['ok' => false, 'status' => 'unknown', 'can_post' => false, 'reason' => 'bot token invalid'];
        }
        $botId = (int) ($me['result']['id'] ?? 0);

        $member = $this->telegram->getChatMember($chatId, $botId);
        if (!($member['ok'] ?? false)) {
            $this->persistAccessStatus($chatId, 'unknown', false);
            return ['ok' => false, 'status' => 'unknown', 'can_post' => false, 'reason' => $member['description'] ?? 'chat not accessible'];
        }

        $status = (string) ($member['result']['status'] ?? 'unknown');
        $canPost = $status === 'administrator'
            ? (bool) ($member['result']['can_post_messages'] ?? true)
            : false;

        $this->persistAccessStatus($chatId, $status, $canPost);

        $ok = in_array($status, ['administrator', 'creator'], true) && $canPost;
        return ['ok' => $ok, 'status' => $status, 'can_post' => $canPost];
    }

    private function refreshAccessStatus(int $chatId): void
    {
        try {
            $this->checkBotAccess($chatId);
        } catch (Throwable $e) {
            Logger::warning('channel', 'access check failed', ['chat_id' => $chatId, 'error' => $e->getMessage()]);
        }
    }

    private function persistAccessStatus(int $chatId, string $status, bool $canPost): void
    {
        Database::pdo()->prepare(
            'UPDATE channels SET bot_status = :status, can_post = :can_post, updated_at = :now WHERE chat_id = :cid'
        )->execute([':status' => $status, ':can_post' => $canPost ? 1 : 0, ':now' => date('Y-m-d H:i:s'), ':cid' => $chatId]);
    }

    public function findByChatId(int $chatId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM channels WHERE chat_id = :cid LIMIT 1');
        $stmt->execute([':cid' => $chatId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM channels WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM channels ORDER BY created_at DESC')->fetchAll();
    }

    /** @return array<int,array<string,mixed>> active + accessible channels, joined with their settings */
    public function listActiveWithSettings(): array
    {
        $sql = 'SELECT c.*, cs.template_key, cs.min_signal_score, cs.allowed_strategies, cs.allowed_exchanges,
                       cs.enabled AS settings_enabled, cs.pin_signal, cs.quote_enabled
                FROM channels c
                JOIN channel_settings cs ON cs.channel_id = c.id
                WHERE c.is_active = 1 AND c.can_post = 1 AND cs.enabled = 1';
        return Database::pdo()->query($sql)->fetchAll();
    }

    private function ensureSettings(int $channelId): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'INSERT OR IGNORE INTO channel_settings (channel_id, template_key, min_signal_score, enabled, pin_signal, quote_enabled, created_at, updated_at)
             VALUES (:cid, \'signal_template\', :minscore, 1, 0, 0, :now1, :now2)'
        )->execute([':cid' => $channelId, ':minscore' => Config::minSignalScore(), ':now1' => $now, ':now2' => $now]);
    }

    public function updateSettings(int $channelId, array $fields): void
    {
        $this->ensureSettings($channelId);
        $allowed = array_intersect_key($fields, array_flip([
            'template_key', 'min_signal_score', 'allowed_strategies', 'allowed_exchanges', 'enabled', 'pin_signal', 'quote_enabled',
        ]));
        if (empty($allowed)) {
            return;
        }
        $sets = [];
        $params = [':cid' => $channelId];
        foreach ($allowed as $k => $v) {
            if (in_array($k, ['allowed_strategies', 'allowed_exchanges'], true) && is_array($v)) {
                $v = json_encode($v, JSON_UNESCAPED_UNICODE);
            }
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $sets[] = 'updated_at = :now';
        $params[':now'] = date('Y-m-d H:i:s');
        Database::pdo()->prepare('UPDATE channel_settings SET ' . implode(', ', $sets) . ' WHERE channel_id = :cid')->execute($params);
    }

    public function getSettings(int $channelId): ?array
    {
        $this->ensureSettings($channelId);
        $stmt = Database::pdo()->prepare('SELECT * FROM channel_settings WHERE channel_id = :cid');
        $stmt->execute([':cid' => $channelId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

// ============================================================================
// SECTION 4 — QUOTE / REPLY MANAGER
// ============================================================================

final class QuoteManager
{
    /** @param array<int,array<string,mixed>> $entities */
    public function store(string $refType, string $refId, int $chatId, int $messageId, string $quoteText = '', array $entities = []): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO message_refs (ref_type, ref_id, chat_id, message_id, quote_text, quote_entities, created_at)
             VALUES (:t, :r, :cid, :mid, :qt, :qe, :now)'
        );
        $stmt->execute([
            ':t' => $refType, ':r' => $refId, ':cid' => $chatId, ':mid' => $messageId,
            ':qt' => $quoteText, ':qe' => json_encode($entities, JSON_UNESCAPED_UNICODE), ':now' => date('Y-m-d H:i:s'),
        ]);
    }

    public function latest(string $refType, string $refId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM message_refs WHERE ref_type = :t AND ref_id = :r ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([':t' => $refType, ':r' => $refId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Builds the `reply_parameters` (+ optional quote) payload for
     * sendMessage, from a stored ref — used so a Signal can be delivered
     * as a reply/quote to an earlier bot or admin message.
     */
    public function buildReplyParameters(array $ref): array
    {
        $params = [
            'message_id' => (int) $ref['message_id'],
            'chat_id' => (int) $ref['chat_id'],
        ];
        $quoteText = (string) ($ref['quote_text'] ?? '');
        if ($quoteText !== '') {
            $params['quote'] = $quoteText;
            $entities = json_decode((string) ($ref['quote_entities'] ?? '[]'), true);
            if (is_array($entities) && !empty($entities)) {
                $params['quote_entities'] = $entities;
            }
        }
        return $params;
    }
}

// ============================================================================
// SECTION 5 — ADMIN AUTH + STATE
// ============================================================================

final class AdminAuth
{
    public static function isAdmin(int $telegramUserId): bool
    {
        if (in_array($telegramUserId, Config::adminIds(), true)) {
            return true;
        }
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM admins WHERE telegram_user_id = :id');
        $stmt->execute([':id' => $telegramUserId]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

final class AdminStateStore
{
    public function set(int $userId, string $state, array $payload = []): void
    {
        Database::pdo()->prepare(
            'INSERT INTO admin_states (telegram_user_id, state, payload, updated_at) VALUES (:id, :state, :payload, :now)
             ON CONFLICT(telegram_user_id) DO UPDATE SET state = excluded.state, payload = excluded.payload, updated_at = excluded.updated_at'
        )->execute([':id' => $userId, ':state' => $state, ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE), ':now' => date('Y-m-d H:i:s')]);
    }

    public function get(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT state, payload FROM admin_states WHERE telegram_user_id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }
        $payload = json_decode((string) $row['payload'], true);
        return ['state' => $row['state'], 'payload' => is_array($payload) ? $payload : []];
    }

    public function clear(int $userId): void
    {
        Database::pdo()->prepare('DELETE FROM admin_states WHERE telegram_user_id = :id')->execute([':id' => $userId]);
    }
}

// ============================================================================
// SECTION 6 — ADMIN PANEL (inline keyboard tree + callback router)
// ============================================================================

final class AdminPanel
{
    private TextFormatManager $texts;
    private ChannelManager $channels;
    private AdminStateStore $states;
    private SignalRepository $signalRepo;
    private QuoteManager $quotes;

    public function __construct(private TelegramClient $telegram, private ExchangeManager $exchangeManager)
    {
        $this->texts = new TextFormatManager();
        $this->channels = new ChannelManager($telegram);
        $this->states = new AdminStateStore();
        $this->signalRepo = new SignalRepository();
        $this->quotes = new QuoteManager();
    }

    public function sendMainMenu(int $chatId): void
    {
        $this->telegram->sendMessage($chatId, "⚙️ پنل مدیریت\n\nیکی از گزینه‌های زیر را انتخاب کنید:", [], [
            'reply_markup' => $this->mainMenuKeyboard(),
        ]);
    }

    private function mainMenuKeyboard(): array
    {
        return ['inline_keyboard' => [
            [['text' => '📡 صرافی‌ها', 'callback_data' => 'admin:exchanges'], ['text' => '📺 کانال‌ها', 'callback_data' => 'admin:channels']],
            [['text' => '📊 Scanner', 'callback_data' => 'admin:scanner'], ['text' => '🧠 Strategies', 'callback_data' => 'admin:strategies']],
            [['text' => '📈 Indicators', 'callback_data' => 'admin:indicators'], ['text' => '📝 Signal Template', 'callback_data' => 'admin:template']],
            [['text' => '✏️ مدیریت متن‌ها', 'callback_data' => 'admin:texts'], ['text' => '🎯 تنظیمات Signal', 'callback_data' => 'admin:signal_settings']],
            [['text' => '🤖 اتومات', 'callback_data' => 'admin:auto'], ['text' => '🧪 Test Signal', 'callback_data' => 'admin:test_signal']],
            [['text' => '📜 Signal History', 'callback_data' => 'admin:history'], ['text' => '❤️ وضعیت سیستم', 'callback_data' => 'admin:health']],
        ]];
    }

    private function backRow(string $to = 'admin:main'): array
    {
        return [['text' => '🔙 بازگشت', 'callback_data' => $to]];
    }

    /**
     * Central callback router. $callbackQuery is the raw Telegram
     * callback_query update payload.
     */
    public function routeCallback(array $callbackQuery): void
    {
        $userId = (int) ($callbackQuery['from']['id'] ?? 0);
        $data = (string) ($callbackQuery['data'] ?? '');
        $chatId = (int) ($callbackQuery['message']['chat']['id'] ?? 0);
        $messageId = (int) ($callbackQuery['message']['message_id'] ?? 0);
        $callbackId = (string) ($callbackQuery['id'] ?? '');

        if (!AdminAuth::isAdmin($userId)) {
            $this->telegram->answerCallbackQuery($callbackId, 'دسترسی غیرمجاز.', true);
            return;
        }

        $this->telegram->answerCallbackQuery($callbackId);
        $parts = explode(':', $data);
        $section = $parts[1] ?? 'main';

        try {
            match ($section) {
                'main' => $this->render($chatId, $messageId, "⚙️ پنل مدیریت", $this->mainMenuKeyboard()),
                'exchanges' => $this->renderExchanges($chatId, $messageId, $parts, $userId),
                'channels' => $this->renderChannels($chatId, $messageId, $parts, $userId),
                'scanner' => $this->renderScanner($chatId, $messageId, $parts, $userId),
                'strategies' => $this->renderStrategies($chatId, $messageId, $parts),
                'indicators' => $this->renderIndicators($chatId, $messageId),
                'template' => $this->renderTemplate($chatId, $messageId, $parts, $userId),
                'texts' => $this->renderTexts($chatId, $messageId, $parts, $userId),
                'signal_settings' => $this->renderSignalSettings($chatId, $messageId, $parts),
                'auto' => $this->renderAuto($chatId, $messageId, $parts, $userId),
                'test_signal' => $this->renderTestSignal($chatId, $messageId, $parts),
                'history' => $this->renderHistory($chatId, $messageId),
                'health' => $this->renderHealth($chatId, $messageId, $parts),
                default => null,
            };
        } catch (Throwable $e) {
            Logger::error('admin_panel', 'render failed', ['section' => $section, 'error' => $e->getMessage()]);
        }
    }

    private function render(int $chatId, int $messageId, string $text, array $keyboard): void
    {
        $this->telegram->editMessageText($chatId, $messageId, $text, [], ['reply_markup' => $keyboard]);
    }

    // -- 📡 Exchanges ------------------------------------------------------
    private function renderExchanges(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;
        $rows = Database::pdo()->query('SELECT * FROM exchanges ORDER BY priority ASC')->fetchAll();

        if ($action === 'toggle' && isset($parts[3])) {
            $id = (int) $parts[3];
            Database::pdo()->prepare('UPDATE exchanges SET is_enabled = 1 - is_enabled WHERE id = :id')->execute([':id' => $id]);
            $rows = Database::pdo()->query('SELECT * FROM exchanges ORDER BY priority ASC')->fetchAll();
        }

        if ($action === 'apikey' && isset($parts[3])) {
            $this->renderExchangeApiKey($chatId, $messageId, (string) $parts[3]);
            return;
        }

        if ($action === 'apikey_set' && isset($parts[3]) && isset($parts[4])) {
            $this->states->set($userId, 'awaiting_api_credential', ['exchange' => $parts[3], 'field' => $parts[4]]);
            $fieldLabel = $parts[4] === 'secret' ? 'Secret' : 'API Key';
            $this->render($chatId, $messageId, "🔑 مقدار جدید {$fieldLabel} برای " . ucfirst($parts[3]) . " رو ارسال کنید.\n(برای پاک کردن، کلمه «حذف» رو بفرستید.)", ['inline_keyboard' => [$this->backRow("admin:exchanges:apikey:{$parts[3]}")]]);
            return;
        }

        $keyboard = [];
        foreach ($rows as $r) {
            $status = $r['is_enabled'] ? '🟢' : '🔴';
            $keyboard[] = [
                ['text' => "$status {$r['display_name']}", 'callback_data' => "admin:exchanges:toggle:{$r['id']}"],
                ['text' => '🔑 API', 'callback_data' => "admin:exchanges:apikey:{$r['name']}"],
            ];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "📡 صرافی‌ها\n\nبرای فعال/غیرفعال کردن روی نامش بزنید. برای تنظیم API Key دکمه 🔑 رو بزنید (فعلاً فقط Wallex واقعاً ازش استفاده می‌کنه — Binance/MEXC برای داده بازار نیازی به کلید ندارن، ذخیره می‌شه برای استفاده‌های بعدی).", ['inline_keyboard' => $keyboard]);
    }

    private function renderExchangeApiKey(int $chatId, int $messageId, string $exchange): void
    {
        $key = $this->getSetting(strtoupper($exchange) . '_API_KEY', '');
        $secret = $this->getSetting(strtoupper($exchange) . '_API_SECRET', '');
        $mask = static fn(string $v): string => $v === '' ? '(تنظیم نشده)' : (mb_substr($v, 0, 4) . str_repeat('•', max(0, mb_strlen($v) - 4)));

        $text = sprintf("🔑 API - %s\n\nAPI Key: %s\nSecret: %s", ucfirst($exchange), $mask($key), $mask($secret));
        $keyboard = [
            [['text' => '✏️ تنظیم API Key', 'callback_data' => "admin:exchanges:apikey_set:{$exchange}:key"]],
            [['text' => '✏️ تنظیم Secret', 'callback_data' => "admin:exchanges:apikey_set:{$exchange}:secret"]],
            $this->backRow('admin:exchanges'),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    // -- 📺 Channels ---------------------------------------------------------
    private function renderChannels(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'add') {
            $this->states->set($userId, 'awaiting_channel_id');
            $this->render($chatId, $messageId, "➕ افزودن کانال\n\nآیدی عددی کانال را ارسال کنید (مثال: -1001234567890).\nربات باید از قبل به عنوان ادمین به کانال اضافه شده باشد.", ['inline_keyboard' => [$this->backRow('admin:channels')]]);
            return;
        }

        if ($action === 'toggle' && isset($parts[3])) {
            $this->channels->toggleActive((int) $parts[3]);
        }

        if ($action === 'remove' && isset($parts[3])) {
            $this->channels->remove((int) $parts[3]);
        }

        if ($action === 'recheck' && isset($parts[3])) {
            $ch = $this->channels->find((int) $parts[3]);
            if ($ch !== null) {
                $this->channels->checkBotAccess((int) $ch['chat_id']);
            }
        }

        if ($action === 'view' && isset($parts[3])) {
            $this->renderChannelDetail($chatId, $messageId, (int) $parts[3]);
            return;
        }

        if ($action === 'quote_toggle' && isset($parts[3])) {
            $channelId = (int) $parts[3];
            $settings = $this->channels->getSettings($channelId);
            $this->channels->updateSettings($channelId, ['quote_enabled' => (int) $settings['quote_enabled'] === 1 ? 0 : 1]);
            $this->renderChannelDetail($chatId, $messageId, $channelId);
            return;
        }

        if ($action === 'quote_set' && isset($parts[3])) {
            $this->states->set($userId, 'awaiting_quote_forward', ['channel_id' => (int) $parts[3]]);
            $this->render($chatId, $messageId, "💬 تنظیم Quote\n\nپیامی که می‌خواید سیگنال‌ها روش Reply/Quote بشن رو از همون کانال Forward کنید (نه کپی — باید واقعاً Forward باشه تا ربات چت و آیدی پیام اصلی رو تشخیص بده).", ['inline_keyboard' => [$this->backRow("admin:channels:view:{$parts[3]}")]]);
            return;
        }

        if ($action === 'minscore_set' && isset($parts[3])) {
            $this->states->set($userId, 'awaiting_channel_min_score', ['channel_id' => (int) $parts[3]]);
            $this->render($chatId, $messageId, sprintf("✏️ حداقل امتیاز این کانال\n\nمقدار فعلی کلی سیستم: %.1f\nیک عدد جدید فقط برای همین کانال بفرستید (مثلاً 15).", Config::minSignalScore()), ['inline_keyboard' => [$this->backRow("admin:channels:view:{$parts[3]}")]]);
            return;
        }

        $channels = $this->channels->listAll();
        $keyboard = [[['text' => '➕ افزودن کانال', 'callback_data' => 'admin:channels:add']]];
        foreach ($channels as $c) {
            $status = $c['is_active'] ? '🟢' : '⚪️';
            $label = $c['title'] ?: (string) $c['chat_id'];
            $keyboard[] = [['text' => "$status $label", 'callback_data' => "admin:channels:view:{$c['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "📺 مدیریت کانال‌ها\n\nتعداد: " . count($channels), ['inline_keyboard' => $keyboard]);
    }

    private function renderChannelDetail(int $chatId, int $messageId, int $channelId): void
    {
        $c = $this->channels->find($channelId);
        if ($c === null) {
            $this->render($chatId, $messageId, "کانال یافت نشد.", ['inline_keyboard' => [$this->backRow('admin:channels')]]);
            return;
        }
        $settings = $this->channels->getSettings($channelId);
        $accessOk = in_array($c['bot_status'], ['administrator', 'creator'], true) && $c['can_post'];
        $quoteEnabled = (int) ($settings['quote_enabled'] ?? 0) === 1;
        $quoteRef = $this->quotes->latest('channel_pin', (string) $channelId);

        $text = sprintf(
            "📺 %s\n\nChat ID: %s\nوضعیت ربات: %s\nمجاز به ارسال: %s\nفعال: %s\nحداقل امتیاز: %s\nقالب: %s\nQuote: %s%s",
            $c['title'] ?: '-',
            $c['chat_id'],
            $c['bot_status'],
            $accessOk ? '✅' : '❌',
            $c['is_active'] ? '🟢' : '🔴',
            $settings['min_signal_score'] ?? Config::minSignalScore(),
            $settings['template_key'] ?? 'signal_template',
            $quoteEnabled ? '🟢 فعال' : '⚪️ غیرفعال',
            $quoteRef !== null ? '' : "\n⚠️ هنوز پیام مرجعی برای Quote تنظیم نشده"
        );

        $toggleLabel = $c['is_active'] ? '🔴 غیرفعال‌سازی' : '🟢 فعال‌سازی';
        $keyboard = [
            [['text' => $toggleLabel, 'callback_data' => "admin:channels:toggle:$channelId"]],
            [['text' => '🔄 بررسی دسترسی ربات', 'callback_data' => "admin:channels:recheck:$channelId"]],
            [['text' => $quoteEnabled ? '⚪️ غیرفعال‌سازی Quote' : '🟢 فعال‌سازی Quote', 'callback_data' => "admin:channels:quote_toggle:$channelId"]],
            [['text' => '💬 تنظیم پیام Quote', 'callback_data' => "admin:channels:quote_set:$channelId"]],
            [['text' => '✏️ حداقل امتیاز کانال', 'callback_data' => "admin:channels:minscore_set:$channelId"]],
            [['text' => '🗑 حذف کانال', 'callback_data' => "admin:channels:remove:$channelId"]],
            $this->backRow('admin:channels'),
        ];

        if (!$accessOk) {
            $text .= "\n\n⚠️ ربات دسترسی ارسال پیام در این کانال را ندارد یا هنوز ادمین نشده است.";
        }

        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    // -- 📊 Scanner ------------------------------------------------------
    private function renderScanner(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'test') {
            $this->render($chatId, $messageId, "🔍 در حال تست اتصال به صرافی‌ها... چند ثانیه صبر کنید.", ['inline_keyboard' => []]);
            $report = $this->testExchangeConnections();
            $this->render($chatId, $messageId, $report, ['inline_keyboard' => [
                [['text' => '🔄 تست دوباره', 'callback_data' => 'admin:scanner:test']],
                $this->backRow('admin:scanner'),
            ]]);
            return;
        }

        if ($action === 'run') {
            $this->render($chatId, $messageId, "▶️ در حال اجرای Scanner... چند ثانیه صبر کنید.", ['inline_keyboard' => []]);
            $selected = (new MarketScanner())->scan($this->exchangeManager);
            $this->render($chatId, $messageId, "✅ اسکن تمام شد.\n\nنمادهای انتخاب‌شده: $selected\n\nاگر صفر بود، از «🔍 تست اتصال صرافی‌ها» برای دیدن دلیل دقیق استفاده کنید.", ['inline_keyboard' => [$this->backRow('admin:scanner')]]);
            return;
        }

        if ($action === 'filters') {
            $this->renderScannerFilters($chatId, $messageId, $parts);
            return;
        }

        if ($action === 'wallex_raw') {
            $this->render($chatId, $messageId, $this->wallexRawSample(), ['inline_keyboard' => [$this->backRow('admin:scanner')]]);
            return;
        }

        if ($action === 'filter_set' && isset($parts[3])) {
            $this->states->set($userId, 'awaiting_scanner_filter', ['setting' => $parts[3]]);
            $labels = [
                'MIN_VOLUME_USDT' => 'حداقل حجم ۲۴ ساعته (عدد، به USDT)',
                'MAX_SPREAD_PERCENT' => 'حداکثر اسپرد مجاز (عدد، درصد — مثلاً 2 یعنی ۲٪)',
                'ALLOWED_QUOTE_ASSETS' => 'ارزهای مجاز quote (با کاما، مثلاً USDT,USDC — یا خالی برای حذف کامل این فیلتر)',
                'SCANNER_TOP_N' => 'حداکثر تعداد نماد (عدد صحیح)',
                'MIN_SIGNAL_SCORE' => 'حداقل امتیاز لازم برای صدور سیگنال (عدد ۰ تا ۱۰۰)',
            ];
            $label = $labels[$parts[3]] ?? $parts[3];
            $this->render($chatId, $messageId, "✏️ $label رو ارسال کنید.", ['inline_keyboard' => [$this->backRow('admin:scanner:filters')]]);
            return;
        }

        $activeSymbols = (new SymbolRepository())->countActive();
        $lastRun = (new ScannerRunRepository())->lastRunAt();
        $text = sprintf(
            "📊 وضعیت Scanner\n\nنمادهای فعال: %d\nحداکثر مجاز (Top N): %d\nحداقل حجم: %s USDT\nحداکثر اسپرد: %s%%\nآخرین اسکن: %s",
            $activeSymbols,
            Config::scannerTopN(),
            number_format(Config::minVolumeUsdt()),
            Config::maxSpreadPercent(),
            $lastRun ?? 'هنوز اجرا نشده'
        );
        $keyboard = [
            [['text' => '🔍 تست اتصال صرافی‌ها', 'callback_data' => 'admin:scanner:test']],
            [['text' => '▶️ اسکن الان', 'callback_data' => 'admin:scanner:run']],
            [['text' => '⚙️ تنظیمات فیلتر', 'callback_data' => 'admin:scanner:filters']],
            [['text' => '🔬 نمونه خام Wallex', 'callback_data' => 'admin:scanner:wallex_raw']],
            $this->backRow(),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Raw JSON of the first Wallex market's `stats` object, straight from
     * the API — the ground truth needed to fix WallexAdapter's volume/price
     * field-name guessing precisely instead of trying variants blind
     * (this sandbox's own network can't reach Wallex to check directly).
     */
    private function wallexRawSample(): string
    {
        $res = HttpClient::request('GET', Config::wallexRestBase() . '/v1/markets', [], null, 0);
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            return "❌ درخواست ناموفق (HTTP {$res['status']}).";
        }
        $symbols = $res['json']['result']['symbols'] ?? $res['json']['result'] ?? $res['json'];
        if (!is_array($symbols) || empty($symbols)) {
            return "⚠️ ساختار پاسخ غیرمنتظره بود:\n" . mb_strimwidth(json_encode($res['json'], JSON_UNESCAPED_UNICODE), 0, 3800, '…');
        }
        $firstKey = array_key_first($symbols);
        $sample = $symbols[$firstKey];
        $pretty = json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return "🔬 نمونه خام Wallex (نماد: $firstKey)\n\n" . mb_strimwidth((string) $pretty, 0, 3800, '…');
    }

    /** Scanner/signal settings that can be overridden live via the panel — used by both display and reset. */
    private const OVERRIDABLE_SETTINGS = ['MIN_VOLUME_USDT', 'MAX_SPREAD_PERCENT', 'ALLOWED_QUOTE_ASSETS', 'SCANNER_TOP_N', 'MIN_SIGNAL_SCORE'];

    private function renderScannerFilters(int $chatId, int $messageId, array $parts = []): void
    {
        if (($parts[3] ?? null) === 'reset') {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('DELETE FROM bot_settings WHERE setting_key = :k');
            foreach (self::OVERRIDABLE_SETTINGS as $key) {
                $stmt->execute([':k' => $key]);
            }
        }

        $hasOverride = static function (string $key): bool {
            $stmt = Database::pdo()->prepare('SELECT 1 FROM bot_settings WHERE setting_key = :k');
            $stmt->execute([':k' => $key]);
            return $stmt->fetchColumn() !== false;
        };
        $overrideNote = static fn(string $key) => $hasOverride($key) ? ' (override فعال از پنل)' : ' (از env.php)';

        $text = sprintf(
            "⚙️ تنظیمات فیلتر Scanner\n\nحداقل حجم: %s USDT%s\nحداکثر اسپرد: %s%%%s\nارزهای مجاز: %s%s\nحداکثر تعداد نماد: %d%s\nحداقل امتیاز سیگنال: %.1f%s\n\nاین مقادیر بلافاصله بعد ذخیره، توی «▶️ اسکن الان» اعمال می‌شن. اگه یه مقدار از پنل ست بشه، همیشه روی env.php اولویت داره — برای پاک کردن همه override ها و برگشت به env.php از دکمه پایین استفاده کنید.",
            number_format(Config::minVolumeUsdt()), $overrideNote('MIN_VOLUME_USDT'),
            Config::maxSpreadPercent(), $overrideNote('MAX_SPREAD_PERCENT'),
            implode(',', Config::allowedQuoteAssets()) ?: '(بدون فیلتر)', $overrideNote('ALLOWED_QUOTE_ASSETS'),
            Config::scannerTopN(), $overrideNote('SCANNER_TOP_N'),
            Config::minSignalScore(), $overrideNote('MIN_SIGNAL_SCORE')
        );
        $keyboard = [
            [['text' => '✏️ حداقل حجم', 'callback_data' => 'admin:scanner:filter_set:MIN_VOLUME_USDT']],
            [['text' => '✏️ حداکثر اسپرد', 'callback_data' => 'admin:scanner:filter_set:MAX_SPREAD_PERCENT']],
            [['text' => '✏️ ارزهای مجاز', 'callback_data' => 'admin:scanner:filter_set:ALLOWED_QUOTE_ASSETS']],
            [['text' => '✏️ حداکثر تعداد نماد', 'callback_data' => 'admin:scanner:filter_set:SCANNER_TOP_N']],
            [['text' => '✏️ حداقل امتیاز سیگنال', 'callback_data' => 'admin:scanner:filter_set:MIN_SIGNAL_SCORE']],
            [['text' => '🔄 پاک کردن همه override ها', 'callback_data' => 'admin:scanner:filters:reset']],
            $this->backRow('admin:scanner'),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Raw one-shot pings against each exchange's REST base (bypassing both
     * the circuit breaker and the adapters' own error-swallowing parse
     * logic — `?? []` on a malformed/error response silently looks like
     * "zero symbols", hiding the actual HTTP status and error body). This
     * surfaces the real reason a scan finds nothing: a blocked/rate-limited
     * host IP (very common — exchanges frequently reject shared-hosting
     * datacenter IP ranges), an auth error, or a genuinely empty response.
     */
    private function testExchangeConnections(): string
    {
        $pingUrls = [
            'binance' => Config::binanceRestBase() . '/api/v3/exchangeInfo',
            'mexc' => Config::mexcRestBase() . '/api/v3/exchangeInfo',
            'wallex' => Config::wallexRestBase() . '/v1/markets',
            'bybit' => Config::bybitRestBase() . '/v5/market/instruments-info?category=spot',
            'okx' => Config::okxRestBase() . '/api/v5/public/instruments?instType=SPOT',
            'kucoin' => Config::kucoinRestBase() . '/api/v1/symbols',
            'cryptocompare' => Config::cryptocompareRestBase() . '/data/top/totalvolfull?limit=5&tsym=USDT',
        ];

        $lines = ["🔍 نتیجه تست اتصال صرافی‌ها:\n"];
        foreach ($this->exchangeManager->adapters() as $name => $adapter) {
            $url = $pingUrls[$name] ?? null;
            $lines[] = "— " . ucfirst($name) . " —";
            if ($url === null) {
                $lines[] = "⚪️ آدرس تست تعریف نشده";
                $lines[] = '';
                continue;
            }

            $start = microtime(true);
            $res = HttpClient::request('GET', $url, [], null, 0); // 0 retries: one honest attempt
            $elapsed = round((microtime(true) - $start) * 1000);

            if ($res['status'] === 0) {
                $lines[] = "❌ اتصال برقرار نشد ({$elapsed}ms) — شبکه/DNS/فایروال هاست، یا IP این سرور توسط صرافی بلاک شده.";
            } elseif ($res['status'] >= 400) {
                $bodySnippet = mb_strimwidth(trim($res['body']), 0, 200, '…');
                $lines[] = "❌ HTTP {$res['status']} ({$elapsed}ms)";
                if ($bodySnippet !== '') {
                    $lines[] = "پاسخ: $bodySnippet";
                }
                if ($res['status'] === 451 || $res['status'] === 403) {
                    $lines[] = "(این کد معمولاً یعنی IP هاست شما از سمت صرافی مسدود/محدود شده — خیلی رایج برای هاست‌های اشتراکی، مخصوصاً هاست‌های ایرانی روی Binance/MEXC)";
                }
            } else {
                $count = is_array($res['json']) ? (count($res['json']['symbols'] ?? $res['json']['result']['symbols'] ?? $res['json']['Data'] ?? $res['json']) ) : 0;
                $lines[] = "✅ HTTP {$res['status']} ({$elapsed}ms), آیتم‌ها: $count";

                // Connection is fine -- run the real filter funnel to see
                // exactly which stage (quote asset / stablecoin / ticker
                // match / min volume / max spread) eliminates candidates.
                $diag = (new MarketScanner())->diagnoseExchange($name, $adapter);
                if ($diag['ok'] ?? false) {
                    $f = $diag['funnel'];
                    $lines[] = sprintf(
                        "فیلتر: %d کل → %d ارز مجاز → %d غیراستیبل‌کوین → %d با تیکر → %d حجم کافی → %d اسپرد مناسب",
                        $f['total'], $f['quote_ok'], $f['stable_ok'], $f['has_ticker'], $f['volume_ok'], $f['spread_ok']
                    );
                    if ($f['total'] > 0 && $f['quote_ok'] === 0) {
                        $sampleQuotes = implode(', ', array_unique(array_column($diag['sample'], 'quote')));
                        $lines[] = "⚠️ هیچ نمادی از ارزهای مجاز (" . implode(',', Config::allowedQuoteAssets()) . ") پیدا نشد. نمونه quote واقعی این صرافی: $sampleQuotes";
                    } elseif ($f['quote_ok'] > 0 && $f['has_ticker'] === 0) {
                        $lines[] = "⚠️ نماد با ارز مجاز پیدا شد ولی هیچ‌کدوم قیمت/تیکر نداشتن — یعنی احتمالاً ساختار پاسخ fetchTicker24h با انتظار کد فرق داره.";
                    } elseif ($f['has_ticker'] > 0 && $f['volume_ok'] === 0) {
                        $lines[] = "⚠️ تیکر پیدا شد ولی حجمی به‌اندازه MIN_VOLUME_USDT نبود — یا واحد حجم گزارش‌شده با فرض ما (USDT) فرق داره، یا واقعاً حجم پایینه.";
                    }
                } elseif (isset($diag['error'])) {
                    $lines[] = "⚠️ خطا در محاسبه فیلتر: {$diag['error']}";
                }
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    // -- 🧠 Strategies ---------------------------------------------------
    private function renderStrategies(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'toggle' && isset($parts[3])) {
            Database::pdo()->prepare('UPDATE strategies SET is_enabled = 1 - is_enabled WHERE id = :id')->execute([':id' => (int) $parts[3]]);
        }

        // Lazily seed the known strategy names from StrategyEngine into DB so they're manageable.
        $engine = new StrategyEngine();
        foreach ($engine->names() as $name) {
            Database::pdo()->prepare(
                'INSERT OR IGNORE INTO strategies (name, display_name, is_enabled) VALUES (:n1, :n2, 1)'
            )->execute([':n1' => $name, ':n2' => $name]);
        }

        $rows = Database::pdo()->query('SELECT * FROM strategies ORDER BY id ASC')->fetchAll();
        $keyboard = [];
        foreach ($rows as $r) {
            $status = $r['is_enabled'] ? '🟢' : '🔴';
            $keyboard[] = [['text' => "$status {$r['display_name']}", 'callback_data' => "admin:strategies:toggle:{$r['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "🧠 استراتژی‌ها\n\nاستراتژی‌های غیرفعال در تولید سیگنال استفاده نمی‌شوند.", ['inline_keyboard' => $keyboard]);
    }

    // -- 📈 Indicators ---------------------------------------------------
    private function renderIndicators(int $chatId, int $messageId): void
    {
        $engine = new IndicatorEngine();
        $names = $engine->registeredNames();
        $text = "📈 Indicator Engine\n\n";
        $text .= empty($names)
            ? "هیچ اندیکاتوری هنوز ثبت نشده است.\nمعماری آماده است — پس از دریافت قوانین دقیق شما، اندیکاتورها به IndicatorEngine::register() اضافه می‌شوند بدون تغییر در بقیه سیستم."
            : ("اندیکاتورهای فعال:\n• " . implode("\n• ", $names));
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => [$this->backRow()]]);
    }

    // -- 📝 Signal Template ------------------------------------------------
    private function renderTemplate(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'edit') {
            $this->states->set($userId, 'awaiting_text_input', ['key' => 'signal_template']);
            $this->render($chatId, $messageId, "📝 قالب سیگنال\n\nپیام جدید قالب را ارسال کنید (می‌توانید از Bold/Italic/Emoji اختصاصی/Spoiler و placeholder های {symbol} {exchange} {direction} {entry} {sl} {tp1} {tp2} {tp3} {score} {timeframe} {strategy} {reasons} استفاده کنید).", ['inline_keyboard' => [$this->backRow('admin:template')]]);
            return;
        }

        $stored = $this->texts->get('signal_template');
        $preview = mb_strimwidth($stored['text'], 0, 500, '…');
        $this->render($chatId, $messageId, "📝 قالب فعلی سیگنال:\n\n$preview", [
            'inline_keyboard' => [[['text' => '✏️ ویرایش قالب', 'callback_data' => 'admin:template:edit']], $this->backRow()],
        ]);
    }

    // -- ✏️ Text Management ------------------------------------------------
    /**
     * Human names for the editable texts. The stop/target announcements are
     * ordinary rows in text_formats, so they were always editable — but
     * "result_be" tells nobody what it is.
     *
     * @var array<string,string>
     */
    private const TEXT_LABELS = [
        'signal_template' => '📨 متن سیگنال',
        'result_tp1'      => '✅ متن تارگت ۱ + ریسک‌فری',
        'result_tp2'      => '🏆 متن تارگت ۲',
        'result_sl'       => '❌ متن حد ضرر',
        'result_be'       => '🛡 متن بسته شدن بدون ضرر',
        'welcome'         => '👋 خوش‌آمدگویی (/start)',
        'help'            => 'ℹ️ راهنما (/help)',
        'error'           => '⚠️ پیام خطا',
        'channel_added'   => '📺 تأیید افزودن کانال',
        'scanner_status'  => '📊 وضعیت اسکنر',
        'signal_long'     => '🟢 عنوان LONG',
        'signal_short'    => '🟡 عنوان SHORT',
    ];

    private function renderTexts(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'edit' && isset($parts[3])) {
            $key = $parts[3];
            $this->states->set($userId, 'awaiting_text_input', ['key' => $key]);
            $label = self::TEXT_LABELS[$key] ?? $key;
            $this->render(
                $chatId,
                $messageId,
                "✏️ ویرایش «$label»\n\nپیام جدید را همان‌طور که می‌خواهید منتشر شود بفرستید — "
                . "متن را انتخاب کنید و Quote بزنید، ایموجی پریمیوم بگذارید، بولد و اسپویلر هم آزاد است.\n\n"
                . "فقط {placeholder}ها را دست‌نخورده بگذارید؛ ربات آن‌ها را با مقدار واقعی جایگزین می‌کند.",
                ['inline_keyboard' => [$this->backRow('admin:texts')]]
            );
            return;
        }

        // Known texts first, in a sensible order; anything unrecognised after.
        $keys = $this->texts->listKeys();
        $ordered = array_values(array_filter(array_keys(self::TEXT_LABELS), static fn($k) => in_array($k, $keys, true)));
        $ordered = array_merge($ordered, array_values(array_diff($keys, $ordered)));

        $keyboard = [];
        foreach ($ordered as $key) {
            $keyboard[] = [['text' => self::TEXT_LABELS[$key] ?? $key, 'callback_data' => "admin:texts:edit:$key"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "✏️ مدیریت متن‌ها\n\nیکی از متن‌ها را برای ویرایش انتخاب کنید:", ['inline_keyboard' => $keyboard]);
    }

    // -- 🎯 Signal Settings -----------------------------------------------
    private function renderSignalSettings(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'mode') {
            $current = $this->getSetting('run_mode', Config::runMode()->value);
            $new = $current === 'LIVE' ? 'DRY_RUN' : 'LIVE';
            $this->setSetting('run_mode', $new);
        }

        $mode = $this->getSetting('run_mode', Config::runMode()->value);
        $text = sprintf(
            "🎯 تنظیمات Signal\n\nحالت اجرا: %s\nحداقل امتیاز: %.1f\nCooldown: %d ثانیه\nحداقل RR: %.2f",
            $mode,
            Config::minSignalScore(),
            Config::cooldownSeconds(),
            Config::minRiskReward()
        );
        $keyboard = [
            [['text' => $mode === 'LIVE' ? '🔁 سوییچ به DRY_RUN' : '🔁 سوییچ به LIVE', 'callback_data' => 'admin:signal_settings:mode']],
            $this->backRow(),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function getSetting(string $key, string $default): string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v === false ? $default : (string) $v;
    }

    private function setSetting(string $key, string $value): void
    {
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
        )->execute([':k' => $key, ':v' => $value, ':now' => date('Y-m-d H:i:s')]);
    }

    // -- 🤖 Automatic mode -------------------------------------------------

    /**
     * Every knob that governs the unattended behaviour, in one screen:
     * how often the bot may speak, how many trades it may run, which
     * timeframes it trades, where the targets sit, and how leverage is
     * chosen per coin. Each writes to bot_settings, which Config:: reads
     * in preference to env.php — so nothing here needs a redeploy.
     *
     * @var array<string,array{0:string,1:string}> setting key => [label, hint]
     */
    private const AUTO_SETTINGS = [
        'SIGNAL_INTERVAL_SECONDS'     => ['⏱ فاصله بین سیگنال‌ها', 'بر حسب ثانیه. ۹۰۰ یعنی هر ۱۵ دقیقه یک سیگنال.'],
        'MAX_OPEN_POSITIONS'          => ['📌 حداکثر معامله باز', 'عدد صحیح. اگر ۱ بگذارید، تا بسته شدن معامله فعلی سیگنال جدید نمی‌آید.'],
        'SIGNAL_TIMEFRAMES'           => ['🕒 تایم‌فریم سیگنال', 'با کاما جدا کنید. مثال: 15m,1h,4h'],
        'TP1_RR'                      => ['🎯 ریسک/ریوارد تارگت ۱', 'مثال: 1.2'],
        'TP2_RR'                      => ['🎯 ریسک/ریوارد تارگت ۲', 'مثال: 1.3'],
        'LEVERAGE_MAJOR_ASSETS'       => ['💎 ارزهای اصلی', 'با کاما جدا کنید. مثال: BTC,ETH'],
        'LEVERAGE_MAJOR'              => ['⚡️ اهرم ارزهای اصلی', 'عدد صحیح. مثال: 150'],
        'LEVERAGE_ALT_MIN'            => ['🔻 حداقل اهرم آلت/شت‌کوین', 'عدد صحیح. مثال: 20'],
        'LEVERAGE_ALT_MAX'            => ['🔺 حداکثر اهرم آلت/شت‌کوین', 'عدد صحیح. مثال: 25'],
        'LEVERAGE_LIQUIDATION_BUFFER' => ['🛡 ضریب فاصله تا لیکویید', 'بین ۰ و ۱. مثال: 0.75'],
        'SIGNAL_MAX_SYMBOLS_PER_PASS' => ['🔍 تعداد ارز در هر اسکن', 'عدد صحیح. مثال: 60'],
        'MIN_SIGNAL_SCORE'            => ['📊 حداقل امتیاز سیگنال', 'مثال: 45'],
    ];

    private function renderAuto(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'riskfree') {
            $this->setSetting('RISK_FREE_ENABLED', Config::riskFreeEnabled() ? 'false' : 'true');
        }

        if ($action === 'set') {
            $key = (string) ($parts[3] ?? '');
            if (isset(self::AUTO_SETTINGS[$key])) {
                [$label, $hint] = self::AUTO_SETTINGS[$key];
                $this->states->set($userId, 'awaiting_auto_setting', ['key' => $key]);
                $this->telegram->sendMessage($chatId, "مقدار جدید «{$label}» را بفرستید.\n\n{$hint}\n\nبرای بازگشت به مقدار پیش‌فرض، کلمه «حذف» را بفرستید.");
                return;
            }
        }

        $open = $this->signalRepo->countOpen();
        $lastAt = $this->signalRepo->lastDispatchedAt();
        $wait = $lastAt === null ? 0 : max(0, Config::signalIntervalSeconds() - (time() - $lastAt));
        $perf = $this->signalRepo->performance();

        $lines = [
            '🤖 حالت اتومات',
            '',
            sprintf('معامله باز: %d از %d', $open, Config::maxOpenPositions()),
            $wait > 0
                ? sprintf('سیگنال بعدی: تا %d دقیقه دیگر', (int) ceil($wait / 60))
                : 'سیگنال بعدی: آماده (منتظر ستاپ مناسب)',
            sprintf('کارت تصویری: %s', CardConfig::enabled() ? 'فعال ✅' : 'غیرفعال (GD در دسترس نیست) ⚠️'),
            sprintf('ریسک‌فری بعد از تارگت ۱: %s', Config::riskFreeEnabled() ? 'فعال ✅' : 'غیرفعال'),
            '',
            sprintf('کارنامه: %d معامله بسته‌شده | %d برد | %d باخت | %d بدون ضرر | نرخ برد %.1f%%',
                $perf['total'], $perf['wins'], $perf['losses'], $perf['breakeven'], $perf['win_rate']),
            '',
            '— تنظیمات فعلی —',
            sprintf('⏱ فاصله سیگنال: %d ثانیه', Config::signalIntervalSeconds()),
            sprintf('🕒 تایم‌فریم: %s', implode(', ', Config::signalTimeframes())),
            sprintf('🎯 تارگت‌ها: TP1=%.2fR | TP2=%.2fR', Config::tp1RiskReward(), Config::tp2RiskReward()),
            sprintf('💎 %s → اهرم %dx', implode(',', Config::majorAssets()), Config::leverageMajor()),
            sprintf('🪙 بقیه ارزها → اهرم %dx تا %dx (خودکار بر اساس نقدینگی و نوسان)', Config::leverageAltMin(), Config::leverageAltMax()),
            sprintf('🛡 ضریب فاصله تا لیکویید: %.2f', Config::leverageLiquidationBuffer()),
            sprintf('🔍 ارز در هر اسکن: %d', Config::signalMaxSymbolsPerPass()),
            sprintf('📊 حداقل امتیاز: %.1f', Config::minSignalScore()),
        ];

        $keyboard = [[[
            'text' => Config::riskFreeEnabled() ? '🛡 خاموش کردن ریسک‌فری' : '🛡 روشن کردن ریسک‌فری',
            'callback_data' => 'admin:auto:riskfree',
        ]]];
        $row = [];
        foreach (self::AUTO_SETTINGS as $key => [$label, $hint]) {
            $row[] = ['text' => $label, 'callback_data' => 'admin:auto:set:' . $key];
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        $keyboard[] = $this->backRow();

        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => $keyboard]);
    }

    // -- 🧪 Test Signal ---------------------------------------------------
    private function renderTestSignal(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'run') {
            $this->runTestSignal($chatId);
            return;
        }
        if ($action === 'preview') {
            $this->runTemplatePreview($chatId);
            return;
        }
        if ($action === 'channel') {
            $this->runChannelTest($chatId);
            return;
        }
        $this->render($chatId, $messageId, "🧪 Test Signal\n\n▶️ اجرای تست: یک سیگنال واقعی از روی بازار تولید می‌کنه (نیاز به نماد فعال داره).\n🎨 پیش‌نمایش قالب: همین‌جا در چت خصوصی، قالب فعلی رو با داده فرضی می‌فرسته.\n📢 تست روی کانال: همون پیام رو واقعاً به کانال‌های فعال می‌فرسته و گزارش می‌ده که ایموجی پریمیوم توی کانال قبول شد یا نه — و جواب تارگت رو هم به‌صورت ریپلای می‌فرسته تا زنجیره ریپلای رو ببینید.", [
            'inline_keyboard' => [
                [['text' => '▶️ اجرای تست (با بازار)', 'callback_data' => 'admin:test_signal:run']],
                [['text' => '🎨 پیش‌نمایش قالب (فوری)', 'callback_data' => 'admin:test_signal:preview']],
                [['text' => '📢 تست روی کانال', 'callback_data' => 'admin:test_signal:channel']],
                $this->backRow(),
            ],
        ]);
    }

    /**
     * Sends the current signal_template through the exact same
     * SignalFormatter/TelegramEntityUtils pipeline a real signal would use,
     * with made-up values — so premium emoji/entity survival can be
     * checked immediately, without depending on the scanner having found
     * any symbols yet.
     */
    /**
     * A stand-in signal with numbers shaped like a real BTC trade at the
     * configured major leverage, so a preview shows the true stop distance
     * and the true leveraged profit each target is worth — not a cosmetic
     * sample. Shared by the private preview and the channel test so the two
     * can never disagree about what they are demonstrating.
     */
    private function previewSignal(): Signal
    {
        $leverage = Config::leverageMajor();
        $entry = 65000.5;
        $risk = $entry * (Config::leverageLiquidationBuffer() * (100.0 / max(1, $leverage))) / 100;
        return new Signal(
            uuid: SignalGenerator::uuid4(),
            exchange: 'binance',
            symbol: 'BTCUSDT',
            direction: Direction::LONG,
            timeframe: '4h',
            entry: $entry,
            stopLoss: $entry - $risk,
            tp1: $entry + $risk * Config::tp1RiskReward(),
            tp2: $entry + $risk * Config::tp2RiskReward(),
            tp3: null,
            riskReward: round(Config::tp1RiskReward(), 2),
            score: 82.5,
            confidence: 'high',
            strategy: 'default_structure',
            reasons: ['این یک پیش‌نمایش با داده فرضی است', 'روند ساختاری: صعودی', 'نزدیک به Order Block صعودی'],
            fingerprint: 'preview',
            status: 'test',
            leverage: $leverage,
            tier: SymbolClassifier::TIER_MAJOR,
        );
    }

    /** The same stand-in trade shaped as a signals-table row, for result rendering. */
    private function previewRow(Signal $signal): array
    {
        return [
            'id' => 0, 'symbol' => $signal->symbol, 'exchange' => $signal->exchange,
            'direction' => $signal->direction->value, 'timeframe' => $signal->timeframe,
            'entry_price' => $signal->entry, 'stop_loss' => $signal->stopLoss,
            'tp1' => $signal->tp1, 'tp2' => $signal->tp2, 'leverage' => $signal->leverage,
        ];
    }

    private function runTemplatePreview(int $chatId): void
    {
        $dummy = $this->previewSignal();

        $template = $this->texts->get('signal_template');
        if ($template['text'] === '') {
            $this->telegram->sendMessage($chatId, "قالب signal_template هنوز تنظیم نشده.");
            return;
        }
        $formatter = new SignalFormatter();
        $rendered = $formatter->format($dummy, $template['text'], $template['entities']);

        $card = SignalCardFactory::entry($dummy);
        if ($card !== null && TelegramEntityUtils::utf16Length($rendered['text']) <= 1024) {
            $this->telegram->sendPhoto($chatId, $card, $rendered['text'], $rendered['entities']);
        } else {
            if ($card !== null) {
                $this->telegram->sendPhoto($chatId, $card);
            }
            $this->telegram->sendMessage($chatId, $rendered['text'], $rendered['entities']);
        }

        // Second half of the preview: what a TP1 fill will look like, so the
        // "profit shot" and the risk-free wording can be checked too.
        $exit = (float) $dummy->tp1;
        $row = $this->previewRow($dummy);
        $resultTemplate = $this->texts->get('result_tp1');
        if ($resultTemplate['text'] !== '') {
            $resultRendered = $formatter->formatResult($row, 'tp1', $exit, $resultTemplate['text'], $resultTemplate['entities']);
            $resultCard = SignalCardFactory::result($row, 'tp1', $exit);
            if ($resultCard !== null && TelegramEntityUtils::utf16Length($resultRendered['text']) <= 1024) {
                $this->telegram->sendPhoto($chatId, $resultCard, $resultRendered['text'], $resultRendered['entities']);
            } else {
                if ($resultCard !== null) {
                    $this->telegram->sendPhoto($chatId, $resultCard);
                }
                $this->telegram->sendMessage($chatId, $resultRendered['text'], $resultRendered['entities']);
            }
        }
    }

    /**
     * Sends the current templates to the real channels and reports what
     * Telegram did with them.
     *
     * The useful part is the verdict on premium emoji: Telegram echoes the
     * message it stored back in the sendPhoto response, so counting the
     * custom_emoji entities that come back says whether the channel kept
     * them — which the docs and the community disagree about, and which no
     * amount of reading settles for a particular bot and channel.
     *
     * It also sends the target announcement as a reply to the signal, so the
     * reply chain the live bot uses is visible end to end.
     */
    private function runChannelTest(int $chatId): void
    {
        $channels = $this->channels->listActiveWithSettings();
        if (empty($channels)) {
            $this->telegram->sendMessage(
                $chatId,
                "هیچ کانال فعالی پیدا نشد.\n\nاز «📺 کانال‌ها» کانال را اضافه کنید، ربات را در آن ادمین کنید، و بعد کانال را فعال کنید."
            );
            return;
        }

        $dummy = $this->previewSignal();
        $formatter = new SignalFormatter();

        $signalTemplate = $this->texts->get('signal_template');
        $signalRendered = $formatter->format($dummy, $signalTemplate['text'], $signalTemplate['entities']);
        $signalCard = SignalCardFactory::entry($dummy);
        $sentEmoji = $this->countCustomEmoji($signalRendered['entities']);

        $row = $this->previewRow($dummy);
        $exit = (float) $dummy->tp1;
        $resultTemplate = $this->texts->get('result_tp1');
        $resultRendered = $formatter->formatResult($row, 'tp1', $exit, $resultTemplate['text'], $resultTemplate['entities']);
        $resultCard = SignalCardFactory::result($row, 'tp1', $exit);

        $report = ["📢 نتیجه تست روی کانال‌ها:", ''];

        foreach ($channels as $channel) {
            $title = (string) ($channel['title'] ?? $channel['chat_id']);
            $chat = (int) $channel['chat_id'];

            $res = $signalCard !== null
                ? $this->telegram->sendPhoto($chat, $signalCard, $signalRendered['text'], $signalRendered['entities'])
                : $this->telegram->sendMessage($chat, $signalRendered['text'], $signalRendered['entities']);

            if (!($res['ok'] ?? false)) {
                $report[] = sprintf("❌ %s — ارسال نشد: %s", $title, (string) ($res['description'] ?? 'خطای نامشخص'));
                $report[] = '';
                continue;
            }

            $messageId = (int) ($res['result']['message_id'] ?? 0);
            $report[] = sprintf('✅ %s — سیگنال ارسال شد', $title);

            if ($sentEmoji > 0) {
                // Telegram returns the message as it stored it.
                $echoed = $this->countCustomEmoji(
                    $res['result']['caption_entities'] ?? $res['result']['entities'] ?? []
                );
                $report[] = $echoed >= $sentEmoji
                    ? sprintf('   ✨ ایموجی پریمیوم قبول شد (%d از %d)', $echoed, $sentEmoji)
                    : sprintf('   ⚠️ ایموجی پریمیوم در کانال حذف شد (%d از %d باقی ماند)', $echoed, $sentEmoji);
            } else {
                $report[] = '   ℹ️ در قالب فعلی هیچ ایموجی پریمیومی نیست';
            }

            // The announcement replies to the signal, exactly like the live bot.
            $opts = $messageId > 0
                ? ['reply_parameters' => ['message_id' => $messageId, 'allow_sending_without_reply' => true]]
                : [];
            $replyRes = $resultCard !== null
                ? $this->telegram->sendPhoto($chat, $resultCard, $resultRendered['text'], $resultRendered['entities'], $opts)
                : $this->telegram->sendMessage($chat, $resultRendered['text'], $resultRendered['entities'], $opts);

            $report[] = ($replyRes['ok'] ?? false)
                ? '   ↩️ اعلام تارگت به‌صورت ریپلای ارسال شد'
                : sprintf('   ❌ ریپلای ارسال نشد: %s', (string) ($replyRes['description'] ?? 'خطای نامشخص'));
            $report[] = '';
        }

        $report[] = 'اگر ایموجی پریمیوم حذف شده باشد، ربات در ارسال واقعی هم خودکار بدون آن می‌فرستد تا سیگنال از دست نرود.';
        $this->telegram->sendMessage($chatId, implode("\n", $report));
    }

    /** @param array<int,array<string,mixed>> $entities */
    private function countCustomEmoji(array $entities): int
    {
        $n = 0;
        foreach ($entities as $entity) {
            if (($entity['type'] ?? '') === 'custom_emoji') {
                $n++;
            }
        }
        return $n;
    }

    private function runTestSignal(int $chatId): void
    {
        // universe() puts BTC/ETH first, so a manual test lands on a major
        // rather than on whatever alt happened to rank first.
        $symbols = (new SymbolRepository())->universe(1);
        if (empty($symbols)) {
            $this->telegram->sendMessage($chatId, "هیچ نماد فعالی برای تست وجود ندارد. ابتدا Scanner را اجرا کنید.");
            return;
        }
        $target = $symbols[0];
        $adapter = $this->exchangeManager->get($target['exchange']);
        if ($adapter === null) {
            $this->telegram->sendMessage($chatId, "صرافی «{$target['exchange']}» در دسترس نیست.");
            return;
        }

        $store = new MarketDataStore();
        $timeframes = Config::timeframes();
        foreach ($timeframes as $tf) {
            $candles = $this->exchangeManager->withIsolation($target['exchange'], fn($a) => $a->fetchCandles($target['symbol'], $tf, 200));
            if (is_array($candles) && !empty($candles)) {
                $store->candleManager()->upsertMany($target['exchange'], $target['symbol'], $tf, $candles);
            }
        }
        $ticker = $this->exchangeManager->withIsolation($target['exchange'], fn($a) => $a->fetchTicker24h());
        if (is_array($ticker) && isset($ticker[$target['symbol']])) {
            $t = $ticker[$target['symbol']];
            $store->tickerManager()->upsert($target['exchange'], $target['symbol'], $t['lastPrice'], $t['bid'], $t['ask'], $t['volume']);
        }

        $snapshot = $store->buildSnapshot($target['exchange'], $target['symbol'], $timeframes);
        // Diagnose on a timeframe the bot is actually allowed to trade
        // (SIGNAL_TIMEFRAMES), not simply the last of the candle-collection
        // list — otherwise the test reports on 1D while the bot trades 4h.
        $signalTfs = Config::signalTimeframes();
        $mainTf = $signalTfs[array_key_last($signalTfs)] ?? '1h';
        $candles = $snapshot->candlesFor($mainTf);

        // Walk the exact same pipeline SignalGenerator uses, stage by
        // stage, with a diagnostic line at each point -- so "no signal"
        // says WHY instead of leaving it a mystery every single time.
        $diag = [];
        $diag[] = "🧪 تست تشخیصی: {$target['symbol']} ({$target['exchange']}, {$mainTf})";
        $diag[] = "قیمت: {$snapshot->price} | کندل: " . count($candles);

        if (count($candles) < 30) {
            $diag[] = "❌ کندل کافی نیست (حداقل ۳۰ لازمه) — این دلیل اصلی نبود سیگنال است.";
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $zones = (new SupportResistanceEngine())->detect($candles, $mainTf);
        $orderBlocks = (new OrderBlockEngine())->detect($candles, $mainTf);
        $fvgs = (new FvgEngine())->detect($candles, $mainTf);
        $trend = SwingPivots::structuralTrend($candles);
        $confluence = (new ConfluenceEngine())->score($snapshot, $mainTf, $zones, $orderBlocks, $fvgs, [], $trend);

        $diag[] = "روند ساختاری: $trend";
        $diag[] = sprintf(
            "امتیاز Confluence: %.1f (حداقل لازم: %.1f) | جهت‌نما: %.1f → بایاس: %s",
            $confluence['score'],
            Config::minSignalScore(),
            $confluence['directional'],
            $confluence['bias']
        );
        $breakdown = [];
        foreach ($confluence['breakdown'] as $factor => $value) {
            $breakdown[] = "$factor=" . round($value, 1);
        }
        $diag[] = 'تفکیک: ' . implode(', ', $breakdown);

        if ($confluence['bias'] === 'neutral') {
            $diag[] = "❌ بایاس خنثی — استراتژی فعلی فقط وقتی بازار واضح صعودی یا نزولی باشه پلن می‌سازه.";
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $strategy = (new StrategyEngine())->get('default_structure');
        $plan = $strategy?->evaluate($snapshot, $mainTf, $zones, $orderBlocks, $fvgs, $confluence);

        if ($plan === null) {
            $diag[] = "❌ استراتژی نتوانست پلن معامله بسازد (معمولاً یعنی ATR صفر یا داده قیمت ناقص است).";
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $baseAsset = SymbolClassifier::baseAsset($target['symbol'], $target['base_asset'] ?? null);
        $volume24h = (float) ($target['volume_24h'] ?? 0);
        $tradePlan = (new TradePlanner())->plan(
            $plan['direction'],
            (float) $plan['entry'],
            (float) $plan['stop_loss'],
            SwingPivots::atr($candles),
            $baseAsset,
            $volume24h
        );
        if ($tradePlan === null) {
            $diag[] = "❌ TradePlanner نتوانست معامله بسازد (اهرم تنظیم‌شده جایی برای حد ضرر باقی نمی‌گذارد).";
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }
        $diag[] = sprintf(
            "پلن: %s | اهرم %dx (%s) | RR=%.2f | فاصله حد ضرر %.2f%%",
            $plan['direction']->value,
            $tradePlan['leverage'],
            $tradePlan['tier'],
            $tradePlan['rr'],
            $tradePlan['stop_pct']
        );

        if ($confluence['score'] < Config::minSignalScore()) {
            $diag[] = sprintf("❌ امتیاز (%.1f) کمتر از حداقل (%.1f) است — این دلیل رد شدن سیگنال است.", $confluence['score'], Config::minSignalScore());
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $generator = new SignalGenerator(new IndicatorEngine());
        $signal = $generator->generate($snapshot, $mainTf);

        if ($signal === null) {
            $diag[] = "⚠️ طبق این محاسبه باید سیگنال صادر می‌شد اما SignalGenerator چیزی برنگرداند — احتمالاً به‌خاطر Cooldown/Deduplication (سیگنال مشابه اخیراً صادر شده).";
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $signal->status = 'test';
        (new SignalRepository())->updateStatus($signal->id, 'test');

        $formatter = new SignalFormatter();
        $template = $this->texts->get('signal_template');
        $rendered = $formatter->format($signal, $template['text'], $template['entities']);

        $card = SignalCardFactory::entry($signal);
        if ($card !== null && TelegramEntityUtils::utf16Length($rendered['text']) <= 1024) {
            $this->telegram->sendPhoto($chatId, $card, $rendered['text'], $rendered['entities']);
            return;
        }
        if ($card !== null) {
            $this->telegram->sendPhoto($chatId, $card);
        }
        $this->telegram->sendMessage($chatId, $rendered['text'], $rendered['entities']);
    }

    // -- 📜 Signal History --------------------------------------------------
    private function renderHistory(int $chatId, int $messageId): void
    {
        $rows = $this->signalRepo->recent(10);
        if (empty($rows)) {
            $this->render($chatId, $messageId, "📜 هنوز سیگنالی ثبت نشده است.", ['inline_keyboard' => [$this->backRow()]]);
            return;
        }
        $lines = ["📜 ۱۰ سیگنال اخیر:\n"];
        foreach ($rows as $r) {
            $result = $r['result'] ?? ($r['outcome'] ?? null);
            $resultMark = match (true) {
                $result === 'tp2' => '🏆 تارگت ۲',
                $result === 'tp1' => '✅ تارگت ۱',
                $result === 'be' => '🛡 بدون ضرر',
                $result === 'sl' => '❌ حد ضرر',
                ($r['stage'] ?? '') === 'risk_free' => '🛡 ریسک‌فری',
                in_array($r['status'], ['sent', 'queued'], true) => '⏳ باز',
                default => (string) $r['status'],
            };
            $lines[] = sprintf(
                '#%d %s %s [%s] اهرم:%dx امتیاز:%.1f %s',
                $r['id'],
                $r['symbol'],
                $r['direction'],
                $r['timeframe'],
                (int) round((float) ($r['leverage'] ?? 1)),
                (float) $r['score'],
                $resultMark
            );
        }
        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => [$this->backRow()]]);
    }

    // -- ❤️ System Health --------------------------------------------------
    private function renderHealth(int $chatId, int $messageId, array $parts): void
    {
        if (($parts[2] ?? null) === 'errors') {
            $this->renderRecentErrors($chatId, $messageId);
            return;
        }

        $exHealth = $this->exchangeManager->healthSnapshot();
        $exLines = [];
        foreach (['binance', 'mexc', 'wallex', 'bybit', 'okx', 'kucoin', 'cryptocompare'] as $ex) {
            $exLines[] = ($exHealth[$ex] ?? '⚪️') . ' ' . ucfirst($ex);
        }

        $telegramOk = ($this->telegram->getMe()['ok'] ?? false) ? '🟢' : '🔴';
        $symbolCount = (new SymbolRepository())->countActive();
        $signalsToday = $this->signalRepo->countToday();
        $activeSignals = $this->signalRepo->countActive();
        $lastScan = (new ScannerRunRepository())->lastRunAt() ?? '-';
        $errCountStmt = Database::pdo()->prepare("SELECT COUNT(*) FROM logs WHERE level IN ('error','critical') AND created_at >= :since");
        $errCountStmt->execute([':since' => date('Y-m-d H:i:s', time() - 86400)]);
        $errCount = (int) $errCountStmt->fetchColumn();

        $text = "❤️ وضعیت سیستم\n\n"
            . "Worker: " . $this->workerHeartbeatStatus() . "\n"
            . "Telegram: $telegramOk\n"
            . implode("\n", $exLines) . "\n\n"
            . "نمادها: $symbolCount\n"
            . "سیگنال‌های امروز: $signalsToday\n"
            . "سیگنال‌های فعال: $activeSignals\n"
            . "آخرین اسکن: $lastScan\n"
            . "خطاها (۲۴ ساعت اخیر): $errCount";

        $keyboard = [
            [['text' => '📋 آخرین خطاها', 'callback_data' => 'admin:health:errors']],
            $this->backRow(),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    /**
     * Shows the last 10 error/critical log rows directly from Telegram —
     * added after a masked-error bug (Logger fataling on undefined
     * STDERR/STDOUT under the web SAPI, inside the webhook's own error
     * handler) made silent failures very hard to diagnose without raw
     * server log access.
     */
    private function renderRecentErrors(int $chatId, int $messageId): void
    {
        $rows = Database::pdo()->query(
            "SELECT level, channel, message, context, created_at FROM logs WHERE level IN ('error','critical') ORDER BY id DESC LIMIT 10"
        )->fetchAll();

        if (empty($rows)) {
            $this->render($chatId, $messageId, "📋 هیچ خطایی ثبت نشده.", ['inline_keyboard' => [$this->backRow('admin:health')]]);
            return;
        }

        $lines = ["📋 ۱۰ خطای اخیر:\n"];
        foreach ($rows as $r) {
            $ctx = $r['context'] !== null ? (' ' . mb_strimwidth($r['context'], 0, 150, '…')) : '';
            $lines[] = sprintf("%s [%s/%s] %s%s", $r['created_at'], strtoupper($r['level']), $r['channel'], $r['message'], $ctx);
        }
        $text = mb_strimwidth(implode("\n", $lines), 0, 4000, '…');
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => [
            [['text' => '🔄 بروزرسانی', 'callback_data' => 'admin:health:errors']],
            $this->backRow('admin:health'),
        ]]);
    }

    private function workerHeartbeatStatus(): string
    {
        $v = $this->getSetting('worker_heartbeat', '');
        if ($v === '') {
            return '⚪️ نامشخص';
        }
        return (time() - (int) $v) < 120 ? '🟢' : '🔴';
    }

    /**
     * Handles free-text input while an admin is mid-flow (e.g. entering a
     * channel ID or editing a text/template). Returns true if the message
     * was consumed as state input.
     */
    public function handleStateInput(int $userId, int $chatId, array $message): bool
    {
        $state = $this->states->get($userId);
        if ($state === null) {
            return false;
        }

        $text = (string) ($message['text'] ?? '');
        $entities = $message['entities'] ?? [];

        if ($state['state'] === 'awaiting_channel_id') {
            $this->states->clear($userId);
            if (!preg_match('/^-?\d+$/', trim($text))) {
                $this->telegram->sendMessage($chatId, "آیدی نامعتبر است. لطفاً یک عدد صحیح ارسال کنید.");
                return true;
            }
            $targetChatId = (int) trim($text);
            $access = $this->channels->checkBotAccess($targetChatId);
            $id = $this->channels->add($targetChatId, $userId);
            $chatInfo = $this->telegram->getChat($targetChatId);
            if ($chatInfo['ok'] ?? false) {
                $this->channels->edit($id, [
                    'title' => $chatInfo['result']['title'] ?? null,
                    'username' => $chatInfo['result']['username'] ?? null,
                ]);
            }
            $msg = $access['ok']
                ? "✅ کانال با موفقیت اضافه شد و ربات دسترسی ارسال پیام دارد."
                : "⚠️ کانال ثبت شد اما ربات هنوز ادمین کانال نیست یا اجازه ارسال پیام ندارد (وضعیت: {$access['status']}). پس از افزودن ربات به‌عنوان ادمین، از «بررسی دسترسی ربات» استفاده کنید.";
            $this->telegram->sendMessage($chatId, $msg);
            return true;
        }

        if ($state['state'] === 'awaiting_text_input') {
            $this->states->clear($userId);
            $key = (string) ($state['payload']['key'] ?? '');
            if ($key === '') {
                return true;
            }
            $this->texts->set($key, $text, is_array($entities) ? $entities : [], $userId);
            $this->telegram->sendMessage($chatId, "✅ متن «$key» با حفظ کامل فرمت و ایموجی‌های اختصاصی ذخیره شد.");
            return true;
        }

        if ($state['state'] === 'awaiting_scanner_filter') {
            $this->states->clear($userId);
            $setting = (string) ($state['payload']['setting'] ?? '');
            $value = trim($text);
            if ($setting === '') {
                return true;
            }
            if (in_array($setting, ['MIN_VOLUME_USDT', 'MAX_SPREAD_PERCENT', 'MIN_SIGNAL_SCORE'], true) && $value !== '' && !is_numeric($value)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید عدد باشه.");
                return true;
            }
            if ($setting === 'SCANNER_TOP_N' && $value !== '' && !ctype_digit($value)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید عدد صحیح باشه.");
                return true;
            }
            // An empty ALLOWED_QUOTE_ASSETS means "no filter at all" -- but
            // the DB-override lookup treats a stored empty string as "not
            // set" (falls back to env's default), so that intent needs a
            // sentinel instead of a literal empty value.
            if ($setting === 'ALLOWED_QUOTE_ASSETS' && $value === '') {
                $value = '*';
            }
            $this->setSetting($setting, $value);
            $this->telegram->sendMessage($chatId, "✅ ذخیره شد. برای اعمال، «▶️ اسکن الان» رو بزنید.");
            return true;
        }

        if ($state['state'] === 'awaiting_auto_setting') {
            $this->states->clear($userId);
            $key = (string) ($state['payload']['key'] ?? '');
            if (!isset(self::AUTO_SETTINGS[$key])) {
                return true;
            }
            $value = trim($text);

            if ($value === 'حذف') {
                Database::pdo()->prepare('DELETE FROM bot_settings WHERE setting_key = :k')->execute([':k' => $key]);
                $this->telegram->sendMessage($chatId, "✅ به مقدار پیش‌فرض برگشت.");
                return true;
            }

            $numeric = ['SIGNAL_INTERVAL_SECONDS', 'MAX_OPEN_POSITIONS', 'TP1_RR', 'TP2_RR', 'LEVERAGE_MAJOR',
                        'LEVERAGE_ALT_MIN', 'LEVERAGE_ALT_MAX', 'LEVERAGE_LIQUIDATION_BUFFER',
                        'SIGNAL_MAX_SYMBOLS_PER_PASS', 'MIN_SIGNAL_SCORE'];
            if (in_array($key, $numeric, true) && !is_numeric($value)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید عدد باشه.");
                return true;
            }
            if ($key === 'TP2_RR' && (float) $value <= Config::tp1RiskReward()) {
                $this->telegram->sendMessage($chatId, "تارگت ۲ باید بزرگ‌تر از تارگت ۱ باشه (الان تارگت ۱ روی " . Config::tp1RiskReward() . " است).");
                return true;
            }
            if ($key === 'LEVERAGE_ALT_MAX' && (int) $value < Config::leverageAltMin()) {
                $this->telegram->sendMessage($chatId, "حداکثر اهرم نمی‌تواند کمتر از حداقل اهرم (" . Config::leverageAltMin() . ") باشد.");
                return true;
            }
            if ($key === 'SIGNAL_TIMEFRAMES') {
                $known = ['1m', '5m', '15m', '1h', '4h', '1D'];
                $given = array_filter(array_map('trim', explode(',', $value)));
                $unknown = array_diff($given, $known);
                if (empty($given) || !empty($unknown)) {
                    $this->telegram->sendMessage($chatId, "تایم‌فریم نامعتبر. فقط از این‌ها استفاده کنید: " . implode(', ', $known));
                    return true;
                }
                // A signal timeframe with no candles behind it produces
                // nothing, so make sure the collector is fetching it too.
                $missing = array_diff($given, Config::timeframes());
                if (!empty($missing)) {
                    $this->telegram->sendMessage($chatId, "⚠️ توجه: تایم‌فریم(های) " . implode(', ', $missing) . " در TIMEFRAMES فایل env.php نیست، پس کندلی برایشان جمع نمی‌شود و سیگنالی هم نمی‌دهند.");
                }
            }

            $this->setSetting($key, $value);
            $this->telegram->sendMessage($chatId, "✅ ذخیره شد. از همین الان اعمال می‌شود.");
            return true;
        }

        if ($state['state'] === 'awaiting_api_credential') {
            $this->states->clear($userId);
            $exchange = (string) ($state['payload']['exchange'] ?? '');
            $field = (string) ($state['payload']['field'] ?? '');
            if ($exchange === '' || $field === '') {
                return true;
            }
            $value = trim($text) === 'حذف' ? '' : trim($text);
            $settingKey = strtoupper($exchange) . '_API_' . ($field === 'secret' ? 'SECRET' : 'KEY');
            $this->setSetting($settingKey, $value);
            $this->telegram->sendMessage($chatId, $value === '' ? '✅ مقدار پاک شد.' : '✅ ذخیره شد.');
            return true;
        }

        if ($state['state'] === 'awaiting_channel_min_score') {
            $this->states->clear($userId);
            $channelId = (int) ($state['payload']['channel_id'] ?? 0);
            $value = trim($text);
            if ($channelId === 0 || !is_numeric($value)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید عدد باشه.");
                return true;
            }
            $this->channels->updateSettings($channelId, ['min_signal_score' => (float) $value]);
            $this->telegram->sendMessage($chatId, "✅ حداقل امتیاز این کانال روی {$value} تنظیم شد.");
            return true;
        }

        if ($state['state'] === 'awaiting_quote_forward') {
            $this->states->clear($userId);
            $channelId = (int) ($state['payload']['channel_id'] ?? 0);
            if ($channelId === 0) {
                return true;
            }
            $origin = $this->extractForwardOrigin($message);
            if ($origin === null) {
                $this->telegram->sendMessage($chatId, "این پیام Forward نبود. لطفاً پیام مورد نظر رو مستقیماً از کانال Forward کنید (از منوی پیام گزینه Forward، نه کپی/پیست متن).");
                return true;
            }
            $this->quotes->store('channel_pin', (string) $channelId, $origin['chat_id'], $origin['message_id'], $text, is_array($entities) ? $entities : []);
            $this->channels->updateSettings($channelId, ['quote_enabled' => 1]);
            $this->telegram->sendMessage($chatId, "✅ پیام مرجع ذخیره شد و Quote برای این کانال فعال شد. سیگنال‌های بعدی این کانال به این پیام Reply می‌کنن.");
            return true;
        }

        return false;
    }

    /** @return array{chat_id:int, message_id:int}|null */
    private function extractForwardOrigin(array $message): ?array
    {
        if (isset($message['forward_origin']['chat']['id'], $message['forward_origin']['message_id'])) {
            return ['chat_id' => (int) $message['forward_origin']['chat']['id'], 'message_id' => (int) $message['forward_origin']['message_id']];
        }
        if (isset($message['forward_from_chat']['id'], $message['forward_from_message_id'])) {
            return ['chat_id' => (int) $message['forward_from_chat']['id'], 'message_id' => (int) $message['forward_from_message_id']];
        }
        return null;
    }
}

// ============================================================================
// SECTION 7 — BOT APPLICATION (update router)
// ============================================================================

final class BotApplication
{
    private ChannelManager $channels;
    private AdminPanel $adminPanel;
    private QuoteManager $quotes;

    public function __construct(private TelegramClient $telegram, private ExchangeManager $exchangeManager)
    {
        $this->channels = new ChannelManager($telegram);
        $this->adminPanel = new AdminPanel($telegram, $exchangeManager);
        $this->quotes = new QuoteManager();
    }

    public function handleUpdate(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->adminPanel->routeCallback($update['callback_query']);
            } elseif (isset($update['my_chat_member'])) {
                $this->handleMyChatMember($update['my_chat_member']);
            }
        } catch (Throwable $e) {
            Logger::error('bot', 'update handling failed', ['error' => $e->getMessage()]);
        }
    }

    private function handleMessage(array $message): void
    {
        $userId = (int) ($message['from']['id'] ?? 0);
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $text = (string) ($message['text'] ?? '');
        $this->touchUser($message['from'] ?? []);

        if (AdminAuth::isAdmin($userId) && $this->adminPanel->handleStateInput($userId, $chatId, $message)) {
            return;
        }

        if ($text === '/start') {
            $this->replyWithText($chatId, 'welcome');
            return;
        }

        if ($text === '/help') {
            $this->replyWithText($chatId, 'help');
            return;
        }

        if ($text === '/panel' || $text === '/admin') {
            if (!AdminAuth::isAdmin($userId)) {
                $this->replyWithText($chatId, 'error');
                return;
            }
            $this->adminPanel->sendMainMenu($chatId);
            return;
        }
    }

    private function replyWithText(int $chatId, string $key): void
    {
        $texts = new TextFormatManager();
        $rendered = $texts->render($key);
        if ($rendered['text'] === '') {
            return;
        }
        $this->telegram->sendMessage($chatId, $rendered['text'], $rendered['entities']);
    }

    private function handleMyChatMember(array $update): void
    {
        $chat = $update['chat'] ?? [];
        $chatId = (int) ($chat['id'] ?? 0);
        if ($chatId === 0) {
            return;
        }
        $existing = $this->channels->findByChatId($chatId);
        if ($existing === null) {
            return; // Only track chats an admin explicitly added.
        }
        try {
            $this->channels->checkBotAccess($chatId);
        } catch (Throwable $e) {
            Logger::warning('bot', 'chat member update handling failed', ['error' => $e->getMessage()]);
        }
    }

    private function touchUser(array $from): void
    {
        $tgId = (int) ($from['id'] ?? 0);
        if ($tgId === 0 || ($from['is_bot'] ?? false)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            'INSERT INTO users (telegram_user_id, username, first_name, last_name, language_code, is_bot, first_seen_at, last_seen_at)
             VALUES (:id, :u, :f, :l, :lang, 0, :now1, :now2)
             ON CONFLICT(telegram_user_id) DO UPDATE SET username = excluded.username, first_name = excluded.first_name,
                last_name = excluded.last_name, language_code = excluded.language_code, last_seen_at = excluded.last_seen_at'
        )->execute([
            ':id' => $tgId, ':u' => $from['username'] ?? null, ':f' => $from['first_name'] ?? null,
            ':l' => $from['last_name'] ?? null, ':lang' => $from['language_code'] ?? null, ':now1' => $now, ':now2' => $now,
        ]);
    }
}

// ============================================================================
// SECTION 8 — WEBHOOK ENTRYPOINT
// (Only active when this file is hit directly by Telegram's webhook, i.e.
//  served by php-fpm/nginx. When required from worker.php this section is
//  inert — worker.php drives its own loop and never receives webhooks.)
// ============================================================================

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'bot.php') {
    try {
        Database::migrate();

        $secret = Config::telegramWebhookSecret();
        if ($secret !== '') {
            $incoming = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
            if (!hash_equals($secret, (string) $incoming)) {
                http_response_code(403);
                exit;
            }
        }

        $raw = file_get_contents('php://input') ?: '';
        $update = json_decode($raw, true);
        if (!is_array($update)) {
            http_response_code(400);
            exit;
        }

        $telegram = new TelegramClient();
        $exchangeManager = new ExchangeManager();
        $app = new BotApplication($telegram, $exchangeManager);
        $app->handleUpdate($update);

        http_response_code(200);
        echo 'OK';
    } catch (Throwable $e) {
        Logger::critical('bot', 'webhook fatal error', ['error' => $e->getMessage()]);
        http_response_code(200); // Ack to Telegram regardless, to avoid retry storms; error is logged.
        echo 'OK';
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';

final class TelegramClient
{
    private string $apiBase;

    public function __construct(?string $token = null)
    {
        $this->apiBase = 'https://api.telegram.org/bot' . ($token ?? Config::telegramBotToken());
    }

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

    public function sendMessage(int|string $chatId, string $text, array $entities = [], array $opts = []): array
    {
        $params = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'entities' => $entities,
        ], $opts);
        return $this->request('sendMessage', $params);
    }

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

final class TextFormatManager
{
    public function get(string $key): array
    {
        return TextStore::text($key) ?? ['text' => '', 'entities' => []];
    }

    public function set(string $key, string $text, array $entities, ?int $updatedBy = null): void
    {
        TextStore::putText($key, $text, $entities, $updatedBy);
    }

    public function render(string $key, array $placeholders = []): array
    {
        $stored = $this->get($key);
        if ($stored['text'] === '') {
            return $stored;
        }
        return TelegramEntityUtils::renderTemplate($stored['text'], $stored['entities'], $placeholders);
    }

    public function listKeys(): array
    {
        return TextStore::textKeys();
    }
}

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

        $row = $this->findByChatId($chatId);
        $id = $row !== null ? (int) $row['id'] : 0;
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

    public function listAll(): array
    {
        return Database::pdo()->query('SELECT * FROM channels ORDER BY created_at DESC')->fetchAll();
    }

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

final class QuoteManager
{
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
        $this->telegram->sendMessage($chatId, "پنل مدیریت", [], [
            'reply_markup' => $this->mainMenuKeyboard(),
        ]);
    }

    private function mainMenuKeyboard(): array
    {
        return ['inline_keyboard' => [
            [['text' => 'صرافی‌ها', 'callback_data' => 'admin:exchanges'], ['text' => 'کانال‌ها', 'callback_data' => 'admin:channels']],
            [['text' => 'Scanner', 'callback_data' => 'admin:scanner'], ['text' => 'Strategies', 'callback_data' => 'admin:strategies']],
            [['text' => 'Indicators', 'callback_data' => 'admin:indicators'], ['text' => 'Signal Template', 'callback_data' => 'admin:template']],
            [['text' => 'مدیریت متن‌ها', 'callback_data' => 'admin:texts'], ['text' => 'تنظیمات Signal', 'callback_data' => 'admin:signal_settings']],
            [['text' => 'اتومات', 'callback_data' => 'admin:auto'], ['text' => 'Test Signal', 'callback_data' => 'admin:test_signal']],
            [['text' => 'Signal History', 'callback_data' => 'admin:history'], ['text' => 'وضعیت سیستم', 'callback_data' => 'admin:health']],
            [['text' => 'دکمه‌های کانال', 'callback_data' => 'admin:buttons']],
        ]];
    }

    private function backRow(string $to = 'admin:main'): array
    {
        return [['text' => 'بازگشت', 'callback_data' => $to]];
    }

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
                'main' => $this->render($chatId, $messageId, "پنل مدیریت", $this->mainMenuKeyboard()),
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
                'buttons' => $this->renderButtons($chatId, $messageId, $parts, $userId),
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
            $this->render($chatId, $messageId, "مقدار جدید {$fieldLabel} برای " . ucfirst($parts[3]) . " رو ارسال کنید.", ['inline_keyboard' => [$this->backRow("admin:exchanges:apikey:{$parts[3]}")]]);
            return;
        }

        $keyboard = [];
        foreach ($rows as $r) {
            $status = $r['is_enabled'] ? 'فعال' : 'غیرفعال';
            $keyboard[] = [
                ['text' => "[$status] {$r['display_name']}", 'callback_data' => "admin:exchanges:toggle:{$r['id']}"],
                ['text' => 'API', 'callback_data' => "admin:exchanges:apikey:{$r['name']}"],
            ];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "صرافی‌ها", ['inline_keyboard' => $keyboard]);
    }

    private function renderExchangeApiKey(int $chatId, int $messageId, string $exchange): void
    {
        $key = $this->getSetting(strtoupper($exchange) . '_API_KEY', '');
        $secret = $this->getSetting(strtoupper($exchange) . '_API_SECRET', '');
        $mask = static fn(string $v): string => $v === '' ? '(تنظیم نشده)' : (mb_substr($v, 0, 4) . str_repeat('•', max(0, mb_strlen($v) - 4)));

        $text = sprintf("API - %s\n\nAPI Key: %s\nSecret: %s", ucfirst($exchange), $mask($key), $mask($secret));
        $keyboard = [
            [['text' => 'تنظیم API Key', 'callback_data' => "admin:exchanges:apikey_set:{$exchange}:key"]],
            [['text' => 'تنظیم Secret', 'callback_data' => "admin:exchanges:apikey_set:{$exchange}:secret"]],
            $this->backRow('admin:exchanges'),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function renderChannels(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'add') {
            $this->states->set($userId, 'awaiting_channel_id');
            $this->render($chatId, $messageId, "افزودن کانال\n\nآیدی عددی کانال را ارسال کنید (مثال: -1001234567890).", ['inline_keyboard' => [$this->backRow('admin:channels')]]);
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
            $this->render($chatId, $messageId, "تنظیم Quote\n\nپیام مرجع را از کانال Forward کنید.", ['inline_keyboard' => [$this->backRow("admin:channels:view:{$parts[3]}")]]);
            return;
        }

        if ($action === 'minscore_set' && isset($parts[3])) {
            $this->states->set($userId, 'awaiting_channel_min_score', ['channel_id' => (int) $parts[3]]);
            $this->render($chatId, $messageId, sprintf("حداقل امتیاز این کانال\n\nمقدار فعلی کلی سیستم: %.1f\nعدد جدید را بفرستید.", Config::minSignalScore()), ['inline_keyboard' => [$this->backRow("admin:channels:view:{$parts[3]}")]]);
            return;
        }

        $channels = $this->channels->listAll();
        $keyboard = [[['text' => 'افزودن کانال', 'callback_data' => 'admin:channels:add']]];
        foreach ($channels as $c) {
            $status = $c['is_active'] ? 'فعال' : 'غیرفعال';
            $label = $c['title'] ?: (string) $c['chat_id'];
            $keyboard[] = [['text' => "[$status] $label", 'callback_data' => "admin:channels:view:{$c['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "مدیریت کانال‌ها\n\nتعداد: " . count($channels), ['inline_keyboard' => $keyboard]);
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
            "%s\n\nChat ID: %s\nوضعیت ربات: %s\nمجاز به ارسال: %s\nفعال: %s\nحداقل امتیاز: %s\nقالب: %s\nQuote: %s%s",
            $c['title'] ?: '-',
            $c['chat_id'],
            $c['bot_status'],
            $accessOk ? 'بله' : 'خیر',
            $c['is_active'] ? 'فعال' : 'غیرفعال',
            $settings['min_signal_score'] ?? Config::minSignalScore(),
            $settings['template_key'] ?? 'signal_template',
            $quoteEnabled ? 'فعال' : 'غیرفعال',
            $quoteRef !== null ? '' : "\nهنوز پیام مرجعی برای Quote تنظیم نشده"
        );

        $toggleLabel = $c['is_active'] ? 'غیرفعال‌سازی' : 'فعال‌سازی';
        $keyboard = [
            [['text' => $toggleLabel, 'callback_data' => "admin:channels:toggle:$channelId"]],
            [['text' => 'بررسی دسترسی ربات', 'callback_data' => "admin:channels:recheck:$channelId"]],
            [['text' => $quoteEnabled ? 'غیرفعال‌سازی Quote' : 'فعال‌سازی Quote', 'callback_data' => "admin:channels:quote_toggle:$channelId"]],
            [['text' => 'تنظیم پیام Quote', 'callback_data' => "admin:channels:quote_set:$channelId"]],
            [['text' => 'حداقل امتیاز کانال', 'callback_data' => "admin:channels:minscore_set:$channelId"]],
            [['text' => 'حذف کانال', 'callback_data' => "admin:channels:remove:$channelId"]],
            $this->backRow('admin:channels'),
        ];

        if (!$accessOk) {
            $text .= "\n\nربات دسترسی ارسال پیام در این کانال را ندارد.";
        }

        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function renderScanner(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'test') {
            $this->render($chatId, $messageId, "در حال تست اتصال به صرافی‌ها...", ['inline_keyboard' => []]);
            $report = $this->testExchangeConnections();
            $this->render($chatId, $messageId, $report, ['inline_keyboard' => [
                [['text' => 'تست دوباره', 'callback_data' => 'admin:scanner:test']],
                $this->backRow('admin:scanner'),
            ]]);
            return;
        }

        if ($action === 'run') {
            $this->render($chatId, $messageId, "در حال اجرای Scanner...", ['inline_keyboard' => []]);
            $selected = (new MarketScanner())->scan($this->exchangeManager);
            $this->render($chatId, $messageId, "اسکن تمام شد.\n\nنمادهای انتخاب‌شده: $selected", ['inline_keyboard' => [$this->backRow('admin:scanner')]]);
            return;
        }

        if ($action === 'venues') {
            $this->render($chatId, $messageId, "در حال گرفتن لیست صرافی‌ها…", ['inline_keyboard' => []]);
            $this->renderVenues($chatId, $messageId, $parts);
            return;
        }

        if ($action === 'filters') {
            $this->renderScannerFilters($chatId, $messageId, $parts);
            return;
        }

        if ($action === 'filter_set' && isset($parts[3])) {
            $this->states->set($userId, 'awaiting_scanner_filter', ['setting' => $parts[3]]);
            $labels = [
                'MIN_VOLUME_USDT' => 'حداقل حجم ۲۴ ساعته (عدد، به USDT)',
                'MAX_SPREAD_PERCENT' => 'حداکثر اسپرد مجاز (درصد)',
                'ALLOWED_QUOTE_ASSETS' => 'ارزهای مجاز quote (با کاما، مثلاً USDT,USDC)',
                'SCANNER_TOP_N' => 'حداکثر تعداد نماد (عدد صحیح)',
                'MIN_SIGNAL_SCORE' => 'حداقل امتیاز سیگنال (۰ تا ۱۰۰)',
            ];
            $label = $labels[$parts[3]] ?? $parts[3];
            $this->render($chatId, $messageId, "$label رو ارسال کنید.", ['inline_keyboard' => [$this->backRow('admin:scanner:filters')]]);
            return;
        }

        $activeSymbols = (new SymbolRepository())->countActive();
        $lastRun = (new ScannerRunRepository())->lastRunAt();
        $text = sprintf(
            "وضعیت Scanner\n\nنمادهای فعال: %d\nحداکثر مجاز (Top N): %d\nحداقل حجم: %s USDT\nحداکثر اسپرد: %s%%\nآخرین اسکن: %s",
            $activeSymbols,
            Config::scannerTopN(),
            number_format(Config::minVolumeUsdt()),
            Config::maxSpreadPercent(),
            $lastRun ?? 'هنوز اجرا نشده'
        );
        $keyboard = [
            [['text' => 'تست اتصال صرافی‌ها', 'callback_data' => 'admin:scanner:test']],
            [['text' => 'اسکن الان', 'callback_data' => 'admin:scanner:run']],
            [['text' => 'صرافی‌های معاملاتی', 'callback_data' => 'admin:scanner:venues']],
            [['text' => 'تنظیمات فیلتر', 'callback_data' => 'admin:scanner:filters']],
            $this->backRow(),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function renderVenues(int $chatId, int $messageId, array $parts): void
    {
        $refresh = ($parts[3] ?? null) === 'refresh';
        if ($refresh) {
            VenueListings::forget();
        }

        $state = VenueListings::current($refresh);
        $venues = Config::tradableVenues();

        $lines = ['صرافی‌های معاملاتی', ''];
        if (empty($venues)) {
            $lines[] = 'فیلتر خاموش است.';
        } else {
            $lines[] = 'صرافی‌ها:';
            $lines[] = '';
            foreach ($venues as $venue) {
                $row = $state['venues'][$venue] ?? null;
                if ($row === null) {
                    $lines[] = sprintf('• %s — هنوز خوانده نشده', $venue);
                    continue;
                }
                $lines[] = $row['ok']
                    ? sprintf('%s — %d ارز', $venue, $row['count'])
                    : sprintf('%s — در دسترس نبود', $venue);
                $lines[] = '   ' . $row['url'];
            }
            $lines[] = '';
            $lines[] = $state['active']
                ? sprintf('مجموع ارزهای قابل معامله: %d', count($state['assets']))
                : 'هیچ لیستی خوانده نشد؛ فیلتر فعلاً خاموش است.';
            if ($state['fetched_at'] > 0) {
                $lines[] = sprintf('آخرین به‌روزرسانی: %s', date('Y-m-d H:i', $state['fetched_at']));
            }
        }

        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => [
            [['text' => 'گرفتن دوباره لیست‌ها', 'callback_data' => 'admin:scanner:venues:refresh']],
            $this->backRow('admin:scanner'),
        ]]);
    }

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
            "تنظیمات فیلتر Scanner\n\nحداقل حجم: %s USDT%s\nحداکثر اسپرد: %s%%%s\nارزهای مجاز: %s%s\nحداکثر تعداد نماد: %d%s\nحداقل امتیاز سیگنال: %.1f%s",
            number_format(Config::minVolumeUsdt()), $overrideNote('MIN_VOLUME_USDT'),
            Config::maxSpreadPercent(), $overrideNote('MAX_SPREAD_PERCENT'),
            implode(',', Config::allowedQuoteAssets()) ?: '(بدون فیلتر)', $overrideNote('ALLOWED_QUOTE_ASSETS'),
            Config::scannerTopN(), $overrideNote('SCANNER_TOP_N'),
            Config::minSignalScore(), $overrideNote('MIN_SIGNAL_SCORE')
        );
        $keyboard = [
            [['text' => 'حداقل حجم', 'callback_data' => 'admin:scanner:filter_set:MIN_VOLUME_USDT']],
            [['text' => 'حداکثر اسپرد', 'callback_data' => 'admin:scanner:filter_set:MAX_SPREAD_PERCENT']],
            [['text' => 'ارزهای مجاز', 'callback_data' => 'admin:scanner:filter_set:ALLOWED_QUOTE_ASSETS']],
            [['text' => 'حداکثر تعداد نماد', 'callback_data' => 'admin:scanner:filter_set:SCANNER_TOP_N']],
            [['text' => 'حداقل امتیاز سیگنال', 'callback_data' => 'admin:scanner:filter_set:MIN_SIGNAL_SCORE']],
            [['text' => 'پاک کردن همه override ها', 'callback_data' => 'admin:scanner:filters:reset']],
            $this->backRow('admin:scanner'),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function testExchangeConnections(): string
    {
        $pingUrls = [
            'binance' => Config::binanceRestBase() . '/api/v3/exchangeInfo',
            'mexc' => Config::mexcRestBase() . '/api/v3/exchangeInfo',
            'gate' => Config::gateRestBase() . '/api/v4/spot/currency_pairs',
            'bitget' => Config::bitgetRestBase() . '/api/v2/spot/public/symbols',
            'htx' => Config::htxRestBase() . '/v1/common/symbols',
            'bybit' => Config::bybitRestBase() . '/v5/market/instruments-info?category=spot',
            'okx' => Config::okxRestBase() . '/api/v5/public/instruments?instType=SPOT',
            'kucoin' => Config::kucoinRestBase() . '/api/v1/symbols',
            'cryptocompare' => Config::cryptocompareRestBase() . '/data/top/totalvolfull?limit=5&tsym=USDT',
        ];

        $lines = ["نتیجه تست اتصال صرافی‌ها:\n"];
        foreach ($this->exchangeManager->adapters() as $name => $adapter) {
            $url = $pingUrls[$name] ?? null;
            $lines[] = "— " . ucfirst($name) . " —";
            if ($url === null) {
                $lines[] = "آدرس تست تعریف نشده";
                $lines[] = '';
                continue;
            }

            $start = microtime(true);
            $res = HttpClient::request('GET', $url, [], null, 0);
            $elapsed = round((microtime(true) - $start) * 1000);

            if ($res['status'] === 0) {
                $lines[] = "اتصال برقرار نشد ({$elapsed}ms).";
            } elseif ($res['status'] >= 400) {
                $bodySnippet = mb_strimwidth(trim($res['body']), 0, 200, '…');
                $lines[] = "HTTP {$res['status']} ({$elapsed}ms)";
                if ($bodySnippet !== '') {
                    $lines[] = "پاسخ: $bodySnippet";
                }
            } else {
                $count = is_array($res['json']) ? (count($res['json']['symbols'] ?? $res['json']['result']['symbols'] ?? $res['json']['Data'] ?? $res['json']) ) : 0;
                $lines[] = "HTTP {$res['status']} ({$elapsed}ms), آیتم‌ها: $count";

                $diag = (new MarketScanner())->diagnoseExchange($name, $adapter);
                if ($diag['ok'] ?? false) {
                    $f = $diag['funnel'];
                    $lines[] = sprintf(
                        "فیلتر: %d کل → %d ارز مجاز → %d غیراستیبل‌کوین → %d قابل معامله در صرافی شما → %d با تیکر → %d حجم کافی → %d اسپرد مناسب",
                        $f['total'], $f['quote_ok'], $f['stable_ok'], $f['tradable_ok'] ?? $f['stable_ok'],
                        $f['has_ticker'], $f['volume_ok'], $f['spread_ok']
                    );
                    if (($f['stable_ok'] ?? 0) > 0 && ($f['tradable_ok'] ?? 0) === 0) {
                        $lines[] = "هیچ ارزی در لیست صرافی‌های شما (" . implode('، ', Config::tradableVenues()) . ") پیدا نشد.";
                    }
                    if ($f['total'] > 0 && $f['quote_ok'] === 0) {
                        $sampleQuotes = implode(', ', array_unique(array_column($diag['sample'], 'quote')));
                        $lines[] = "هیچ نمادی از ارزهای مجاز (" . implode(',', Config::allowedQuoteAssets()) . ") پیدا نشد. نمونه quote واقعی این صرافی: $sampleQuotes";
                    } elseif ($f['quote_ok'] > 0 && $f['has_ticker'] === 0) {
                        $lines[] = "نماد با ارز مجاز پیدا شد ولی هیچ‌کدوم قیمت/تیکر نداشتن.";
                    } elseif ($f['has_ticker'] > 0 && $f['volume_ok'] === 0) {
                        $lines[] = "تیکر پیدا شد ولی حجمی به‌اندازه MIN_VOLUME_USDT نبود.";
                    }
                } elseif (isset($diag['error'])) {
                    $lines[] = "خطا در محاسبه فیلتر: {$diag['error']}";
                }
            }
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    private function renderStrategies(int $chatId, int $messageId, array $parts): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'toggle' && isset($parts[3])) {
            Database::pdo()->prepare('UPDATE strategies SET is_enabled = 1 - is_enabled WHERE id = :id')->execute([':id' => (int) $parts[3]]);
        }

        $engine = new StrategyEngine();
        foreach ($engine->names() as $name) {
            Database::pdo()->prepare(
                'INSERT OR IGNORE INTO strategies (name, display_name, is_enabled) VALUES (:n1, :n2, 1)'
            )->execute([':n1' => $name, ':n2' => $name]);
        }

        $rows = Database::pdo()->query('SELECT * FROM strategies ORDER BY id ASC')->fetchAll();
        $keyboard = [];
        foreach ($rows as $r) {
            $status = $r['is_enabled'] ? 'فعال' : 'غیرفعال';
            $keyboard[] = [['text' => "[$status] {$r['display_name']}", 'callback_data' => "admin:strategies:toggle:{$r['id']}"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "استراتژی‌ها", ['inline_keyboard' => $keyboard]);
    }

    private const PORTED_INDICATORS = [
        ['MTF Liquidity Stack', 'LiquidityEngine'],
        ['Order Block Detector', 'OrderBlockEngine'],
        ['Supply & Demand MTF', 'SupplyDemandEngine'],
        ['Setup Scanner', 'SetupScanner'],
        ['SMC Clean Wave', 'TrendWave'],
        ['Auto Range Detector', 'RangeEngine'],
        ['Liquidity Sweep → Big Move', 'SetupScanner'],
        ['SMC Analytics', 'SmcContext'],
        ['Dynamic Deviation Channels', 'DeviationChannel'],
        ['SMC Confluence Suite', 'SmcContext'],
        ['MACD', 'Ta'],
        ['EMA200', 'Ta'],
        ['ADX', 'Ta'],
    ];

    private function renderIndicators(int $chatId, int $messageId): void
    {
        $lines = ['موتور اندیکاتورها', ''];
        $lines[] = sprintf('استراتژی فعال: %s', Config::strategyName());
        $lines[] = '';

        foreach (self::PORTED_INDICATORS as $i => [$name, $class]) {
            $ok = class_exists($class);
            $lines[] = sprintf('%s %d. %s', $ok ? '[OK]' : '[FAIL]', $i + 1, $name);
        }

        $lines[] = '';
        $lines[] = sprintf('حداقل امتیاز هم‌گرایی برای انتشار: %.0f', Config::minConfluenceScore());
        $lines[] = sprintf('وزن‌ها — ستاپ %.0f | رنج %.0f | ساختار %.0f | ناحیه %.0f | روند %.0f | نقدینگی %.0f | تایم بالاتر %.0f',
            Config::setupWeight(), Config::rangeWeight(), Config::structureWeight(),
            Config::zoneWeight(), Config::trendWeight(), Config::liquidityWeight(), Config::htfWeight());

        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => [$this->backRow()]]);
    }

    private function renderTemplate(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;
        if ($action === 'edit') {
            $this->states->set($userId, 'awaiting_text_input', ['key' => 'signal_template']);
            $this->render($chatId, $messageId, "قالب سیگنال\n\nقالب جدید را بفرستید.\n\n{symbol} {exchange} {direction} {entry} {sl} {tp1} {tp2} {tp3} {score} {timeframe} {strategy} {reasons}", ['inline_keyboard' => [$this->backRow('admin:template')]]);
            return;
        }

        $stored = $this->texts->get('signal_template');
        $preview = mb_strimwidth($stored['text'], 0, 500, '…');
        $this->render($chatId, $messageId, "قالب فعلی سیگنال:\n\n$preview", [
            'inline_keyboard' => [[['text' => 'ویرایش قالب', 'callback_data' => 'admin:template:edit']], $this->backRow()],
        ]);
    }

    private const TEXT_LABELS = [
        'signal_template' => 'متن سیگنال',
        'result_tp1'      => 'متن تارگت ۱ + ریسک‌فری',
        'result_tp2'      => 'متن تارگت ۲',
        'result_tp3'      => 'متن تارگت ۳',
        'result_tp4'      => 'متن تارگت ۴ (نهایی)',
        'result_trail'    => 'متن بسته شدن با سود قفل‌شده',
        'result_sl'       => 'متن حد ضرر',
        'result_be'       => 'متن بسته شدن بدون ضرر',
        'result_timeout'  => 'متن بسته شدن به‌خاطر طول کشیدن',
        'advisory_stop_warning' => 'متن هشدار نزدیکی به حد ضرر',
        'advisory_stall_warning' => 'متن هشدار توقف بعد از تارگت ۳',
        'welcome'         => 'خوش‌آمدگویی (/start)',
        'help'            => 'راهنما (/help)',
        'error'           => 'پیام خطا',
        'channel_added'   => 'تأیید افزودن کانال',
        'scanner_status'  => 'وضعیت اسکنر',
        'signal_long'     => 'عنوان LONG',
        'signal_short'    => 'عنوان SHORT',
    ];

    private static function placeholdersFor(string $key): array
    {
        $signal = [
            '{symbol}' => 'نماد ارز',
            '{symbol_raw}' => 'نماد خام صرافی',
            '{direction_fa}' => 'جهت (فارسی)',
            '{direction}' => 'جهت (LONG/SHORT)',
            '{leverage}' => 'اهرم',
            '{entry}' => 'قیمت ورود',
            '{entry_mode}' => 'نوع ورود',
            '{balance}' => 'سرمایه مرجع',
            '{risk_per_trade}' => 'درصد ریسک هر معامله',
            '{risk_amount}' => 'مبلغ ریسک این معامله',
            '{margin}' => 'مارجین پیشنهادی',
            '{position_size}' => 'حجم پوزیشن',
            '{sl}' => 'حد ضرر',
            '{tp1}' => 'تارگت ۱',
            '{tp2}' => 'تارگت ۲',
            '{tp1_profit}' => 'سود تارگت ۱ با اهرم',
            '{tp2_profit}' => 'سود تارگت ۲ با اهرم',
            '{sl_loss}' => 'ضرر حد ضرر با اهرم',
            '{rr}' => 'ریسک به ریوارد',
            '{risk_pct}' => 'فاصله حد ضرر (درصد)',
            '{score}' => 'امتیاز سیگنال',
            '{confidence_fa}' => 'اعتبار',
            '{tier}' => 'نوع ارز',
            '{exchange}' => 'صرافی',
            '{timeframe}' => 'تایم‌فریم',
            '{time}' => 'زمان',
            '{reasons}' => 'دلایل تحلیل',
        ];

        $result = [
            '{symbol}' => 'نماد ارز',
            '{symbol_raw}' => 'نماد خام صرافی',
            '{direction_fa}' => 'جهت',
            '{leverage}' => 'اهرم',
            '{entry}' => 'نقطه ورود',
            '{exit}' => 'قیمت خروج',
            '{pnl}' => 'سود/ضرر با اهرم',
            '{move}' => 'حرکت خام قیمت',
            '{sl}' => 'حد ضرر اولیه',
            '{tp1}' => 'تارگت ۱',
            '{tp2}' => 'تارگت ۲',
            '{tp3}' => 'تارگت ۳',
            '{tp4}' => 'تارگت ۴',
            '{timeframe}' => 'تایم‌فریم',
            '{exchange}' => 'صرافی',
            '{time}' => 'زمان',
        ];

        return match ($key) {
            'signal_template' => $signal,
            'result_tp1', 'result_tp2', 'result_tp3', 'result_tp4', 'result_trail', 'result_sl', 'result_be', 'result_timeout' => $result,
            'signal_long', 'signal_short' => ['{symbol}' => 'نماد ارز'],
            'scanner_status' => ['{status}' => 'وضعیت اسکنر'],
            'advisory_stop_warning', 'advisory_stall_warning' => ['{symbol}' => 'نماد ارز'],
            default => [],
        };
    }

    private function renderTexts(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'edit' && isset($parts[3])) {
            $key = $parts[3];
            $this->states->set($userId, 'awaiting_text_input', ['key' => $key]);
            $label = self::TEXT_LABELS[$key] ?? $key;

            $lines = ["$label", ''];
            $vars = self::placeholdersFor($key);
            if (!empty($vars)) {
                $lines[] = 'دستورها:';
                foreach ($vars as $token => $meaning) {
                    $lines[] = $token . ' — ' . $meaning;
                }
                $lines[] = '';
            }
            $lines[] = 'پیام جدید را بفرستید.';

            $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => [$this->backRow('admin:texts')]]);

            $current = $this->texts->get($key);
            if ($current['text'] !== '') {
                $this->telegram->sendMessage($chatId, $current['text'], $current['entities']);
            }
            return;
        }

        $keys = $this->texts->listKeys();
        $ordered = array_keys(self::TEXT_LABELS);
        $ordered = array_merge($ordered, array_values(array_diff($keys, $ordered)));

        $keyboard = [];
        foreach ($ordered as $key) {
            $keyboard[] = [['text' => self::TEXT_LABELS[$key] ?? $key, 'callback_data' => "admin:texts:edit:$key"]];
        }
        $keyboard[] = $this->backRow();
        $this->render($chatId, $messageId, "مدیریت متن‌ها", ['inline_keyboard' => $keyboard]);
    }

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
            "تنظیمات Signal\n\nحالت اجرا: %s\nحداقل امتیاز: %.1f\nCooldown: %d ثانیه\nحداقل RR: %.2f",
            $mode,
            Config::minSignalScore(),
            Config::cooldownSeconds(),
            Config::minRiskReward()
        );
        $keyboard = [
            [['text' => $mode === 'LIVE' ? 'سوییچ به DRY_RUN' : 'سوییچ به LIVE', 'callback_data' => 'admin:signal_settings:mode']],
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

    private const BUTTON_STYLE_LABELS = ['success' => '🟢 سبز', 'primary' => '🔵 آبی', 'danger' => '🔴 قرمز'];

    private function renderButtons(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;
        $slot = 1;

        if ($action === 'edit_text') {
            $this->states->set($userId, 'awaiting_button_text', ['slot' => $slot]);
            $this->render(
                $chatId,
                $messageId,
                "متن جدید دکمه را بفرستید.",
                ['inline_keyboard' => [$this->backRow('admin:buttons')]]
            );
            return;
        }

        if ($action === 'edit_url') {
            $this->states->set($userId, 'awaiting_button_url', ['slot' => $slot]);
            $this->render(
                $chatId,
                $messageId,
                "لینک جدید دکمه را بفرستید.",
                ['inline_keyboard' => [$this->backRow('admin:buttons')]]
            );
            return;
        }

        if ($action === 'edit_emoji') {
            $this->states->set($userId, 'awaiting_button_emoji', ['slot' => $slot]);
            $current = Config::channelButtonEmojiId($slot);
            $lines = [
                'آیکون ایموجی پریمیوم دکمه',
                '',
                'ایموجی پریمیوم را بفرستید.',
            ];
            if ($current !== null) {
                $lines[] = '';
                $lines[] = "آیکون فعلی ثبت شده (شناسه): {$current}";
            }
            $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => [$this->backRow('admin:buttons')]]);
            return;
        }

        if ($action === 'setstyle' && isset($parts[3]) && in_array($parts[3], Config::channelButtonStyles(), true)) {
            TextStore::putValue("BUTTON{$slot}_STYLE", $parts[3]);
        }

        $style = Config::channelButtonStyle($slot);
        $emoji = Config::channelButtonEmojiId($slot);
        $lines = [
            'دکمه شیشه‌ای کانال',
            '',
            sprintf(
                "«%s»\nرنگ: %s\nلینک: %s%s",
                Config::channelButtonText($slot),
                self::BUTTON_STYLE_LABELS[$style] ?? $style,
                Config::channelButtonUrl($slot),
                $emoji !== null ? "\nآیکون پریمیوم: ثبت شده" : ''
            ),
        ];

        $keyboard = [
            [
                ['text' => '✏️ متن دکمه', 'callback_data' => 'admin:buttons:edit_text'],
                ['text' => '🔗 لینک دکمه', 'callback_data' => 'admin:buttons:edit_url'],
            ],
        ];
        $styleRow = [];
        foreach (Config::channelButtonStyles() as $styleOption) {
            $label = self::BUTTON_STYLE_LABELS[$styleOption] ?? $styleOption;
            if ($styleOption === $style) {
                $label = '✅ ' . $label;
            }
            $styleRow[] = ['text' => $label, 'callback_data' => "admin:buttons:setstyle:{$styleOption}"];
        }
        $keyboard[] = $styleRow;
        $keyboard[] = [['text' => '✨ ایموجی پریمیوم دکمه', 'callback_data' => 'admin:buttons:edit_emoji']];
        $keyboard[] = $this->backRow();

        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => $keyboard]);
    }

    private const AUTO_SETTINGS = [
        'SIGNALS_PER_PASS'            => ['سیگنال در هر پاس', 'عدد. مثال: 1'],
        'MIN_VOLUME_USDT'             => ['حداقل حجم ۲۴ ساعته', 'USDT. مثال: 5000000'],
        'ROTATION_BUDGET_SECONDS'     => ['ثانیه‌های چرخش هر اجرا', 'ثانیه. مثال: 32'],
        'REQUIRE_HTF_ALIGNMENT'       => ['اجبار هم‌جهتی تایم بالاتر', 'true یا false'],
        'REQUIRE_DISCOUNT_PREMIUM'    => ['اجبار ورود از ناحیه درست', 'true یا false'],
        'TRADABLE_VENUES'             => ['صرافی‌های معاملاتی', 'با کاما. مثال: toobit,ourbit — یا off'],
        'SIGNAL_TIMEFRAMES'           => ['تایم‌فریم سیگنال', 'با کاما. مثال: 15m,30m,1h,2h'],
        'TP1_LEVERAGED_PCT'           => ['سود تارگت ۱ (با اهرم)', 'درصد. مثال: 28'],
        'TP2_LEVERAGED_PCT'           => ['سود تارگت ۲ (با اهرم)', 'درصد. مثال: 50'],
        'TP3_LEVERAGED_PCT'           => ['سود تارگت ۳ (با اهرم)', 'درصد. مثال: 90'],
        'TP4_LEVERAGED_PCT'           => ['سود تارگت ۴ نهایی (با اهرم)', 'درصد. مثال: 160'],
        'TP1_CLOSE_PERCENT'           => ['درصد خروج در تارگت ۱', 'درصد. مثال: 50'],
        'ADVISORY_STOP_WARN_PCT'      => ['آستانه هشدار قبل تارگت ۱', 'درصد. مثال: 70'],
        'ADVISORY_STALL_RETRACE_PCT'  => ['آستانه هشدار بعد تارگت ۳', 'درصد. مثال: 50'],
        'MAX_STOP_LEVERAGED_PCT'      => ['حداکثر ضرر حد ضرر (با اهرم)', 'درصد. مثال: 35'],
        'LEVERAGE_MAJOR_ASSETS'       => ['ارزهای اصلی', 'با کاما. مثال: BTC,ETH'],
        'LEVERAGE_MAJOR'              => ['اهرم ارزهای اصلی', 'عدد صحیح. مثال: 15'],
        'LEVERAGE_ALT_MIN'            => ['حداقل اهرم آلت/شت‌کوین', 'عدد صحیح. مثال: 8'],
        'LEVERAGE_ALT_MAX'            => ['حداکثر اهرم آلت/شت‌کوین', 'عدد صحیح. مثال: 25'],
        'LEVERAGE_LIQUIDATION_BUFFER' => ['ضریب فاصله تا لیکویید', 'بین ۰ و ۱. مثال: 0.75'],
        'SIGNAL_MAX_SYMBOLS_PER_PASS' => ['تعداد ارز در هر اسکن', 'عدد صحیح. مثال: 60'],
        'MIN_SIGNAL_SCORE'            => ['حداقل امتیاز سیگنال', 'عدد. مثال: 45'],
        'PRIMARY_EXCHANGE'            => ['صرافی اصلی', 'مثال: mexc'],
        'ACCOUNT_BALANCE'             => ['سرمایه مرجع', 'USDT. مثال: 1000'],
        'RISK_PER_TRADE_PCT'          => ['ریسک هر معامله', 'درصد. مثال: 2'],
        'MAX_DAILY_LOSSES'            => ['سقف ضرر روزانه', 'عدد. مثال: 5'],
        'SYMBOL_LOSS_COOLDOWN_SECONDS' => ['استراحت ارز بعد از استاپ', 'ثانیه، ۰ = خاموش. مثال: 10800'],
        'MAX_DAILY_SIGNALS'           => ['سقف سیگنال روزانه', 'عدد، ۰ = بدون سقف. مثال: 10'],
        'MAX_OPEN_TRADES'             => ['حداکثر معامله باز هم‌زمان', 'عدد، ۰ = بدون سقف. مثال: 3'],
        'MIN_SIGNAL_GAP_MINUTES'      => ['حداقل فاصله بین سیگنال‌ها', 'دقیقه، ۰ = خاموش. مثال: 30'],
        'MAX_TRADE_HOURS'             => ['حداکثر عمر معامله قبل از تارگت ۱', 'ساعت، ۰ = خاموش. مثال: 24'],
        'MAX_OPPOSING_VOTE_RATIO'     => ['سقف شواهد خلاف جهت', 'بین 0.1 تا 1، 1 = خاموش. مثال: 0.6'],
        'REQUIRE_BREAKOUT_MOMENTUM'   => ['اجبار تایید مومنتوم برای شکست', 'true یا false'],
        'MIN_CONFLUENCE_SCORE'        => ['حداقل امتیاز هم‌گرایی', 'عدد. مثال: 80'],
        'ZONE_REACH_ATR'              => ['حداکثر فاصله ناحیه حمایتی', 'ATR. مثال: 2.5'],
        'SETUP_VOLUME_MULT'           => ['حجم لازم ستاپ‌ها', 'ضریب. مثال: 1'],
        'BREAK_VOLUME_RATIO'          => ['حجم لازم روی شکست', 'ضریب. مثال: 1.3'],
        'MAX_CHASE_ATR'               => ['حداکثر فاصله از سطح شکست', 'ATR. مثال: 1.5'],
        'REVERSAL_RUN_PCT'            => ['حداقل رشد برای سیگنال برگشتی', 'درصد. مثال: 12'],
        'BASE_RANGE_PCT'              => ['حداکثر عرض کف برای شکست', 'درصد. مثال: 12'],
        'REQUIRE_REVERSAL_CANDLE'     => ['اجبار تایید کندل برگشتی', 'true یا false'],
        'REVERSAL_CONFIRM_TIMEFRAME'  => ['تایم‌فریم تایید کندل برگشتی', 'مثال: 5m'],
        'REQUIRE_FRESH_REVERSAL_ZONE' => ['اجبار تازگی ناحیه برگشت', 'true یا false'],
        'REQUIRE_SWEEP_AT_ZONE'       => ['اجبار جارو دقیقاً روی ناحیه', 'true یا false'],
        'REQUIRE_KILLZONE'            => ['اجبار بازه زمانی پرحجم (Killzone)', 'true یا false'],
        'MIN_FVG_MITIGATION_PCT'      => ['حداقل بازگشت به داخل FVG', 'درصد. مثال: 5'],
        'STRONG_ZONE_VETO_TOUCHES'    => ['آستانه وتوی سطح قوی', 'عدد. مثال: 3'],
        'CONFLUENCE_STRUCTURE_CAP'    => ['سقف امتیاز گروه ساختار', 'عدد. مثال: 30'],
        'CONFLUENCE_LIQUIDITY_CAP'    => ['سقف امتیاز گروه نقدینگی', 'عدد. مثال: 28'],
        'CONFLUENCE_LOCATION_CAP'     => ['سقف امتیاز گروه موقعیت', 'عدد. مثال: 26'],
        'CONFLUENCE_MOMENTUM_CAP'     => ['سقف امتیاز گروه مومنتوم', 'عدد. مثال: 22'],
        'CONFLUENCE_VOLUME_CAP'       => ['سقف امتیاز گروه حجم', 'عدد. مثال: 14'],
        'CONFLUENCE_HTF_CAP'          => ['سقف امتیاز گروه تایم‌فریم بالاتر', 'عدد. مثال: 22'],
        'MIN_ROOM_TO_TARGET_R'        => ['حداقل فاصله تا هدف (بر حسب R)', 'R. مثال: 1.5'],
        'HTF_CONFIRM_MAP'             => ['نقشه تایید تایم‌فریم بالاتر', 'مثال: 15m:1h,30m:2h,1h:4h,2h:4h,4h:1d'],
        'REQUIRE_APLUS_SETUP'         => ['اجبار فیلتر A+ Setup', 'true یا false'],
        'APLUS_MIN_CONFIRMATIONS'     => ['حداقل تاییدیه‌های A+', 'عدد. مثال: 3'],
        'REQUIRE_ADX_FILTER'          => ['اجبار فیلتر قدرت روند (ADX)', 'true یا false'],
        'MIN_ADX'                     => ['حداقل ADX', 'عدد. مثال: 20'],
        'NEWS_BLACKOUT_START'         => ['شروع سکوت خبری', 'تاریخ و ساعت، خالی = خاموش. مثال: 2026-09-20 16:00'],
        'NEWS_BLACKOUT_END'           => ['پایان سکوت خبری', 'تاریخ و ساعت، خالی = خاموش. مثال: 2026-09-20 17:00'],
        'SCANNER_GAINER_SHARE'        => ['سهم بیشترین رشد', 'درصد. مثال: 40'],
        'SCANNER_LOSER_SHARE'         => ['سهم بیشترین ضرر', 'درصد. مثال: 25'],
        'SCANNER_MIN_MOVE_PCT'        => ['حداقل حرکت ۲۴ ساعته', 'درصد. مثال: 4'],
    ];

    private const AUTO_CATEGORIES = [
        'timing' => ['زمان‌بندی و اسکن', [
            'SIGNALS_PER_PASS', 'MIN_VOLUME_USDT',
            'ROTATION_BUDGET_SECONDS', 'TRADABLE_VENUES', 'SIGNAL_TIMEFRAMES', 'SIGNAL_MAX_SYMBOLS_PER_PASS',
            'PRIMARY_EXCHANGE',
        ]],
        'targets' => ['اهرم و تارگت‌ها', [
            'TP1_LEVERAGED_PCT', 'TP2_LEVERAGED_PCT', 'TP3_LEVERAGED_PCT', 'TP4_LEVERAGED_PCT', 'TP1_CLOSE_PERCENT',
            'MAX_STOP_LEVERAGED_PCT', 'LEVERAGE_MAJOR_ASSETS',
            'LEVERAGE_MAJOR', 'LEVERAGE_ALT_MIN', 'LEVERAGE_ALT_MAX', 'LEVERAGE_LIQUIDATION_BUFFER',
            'ADVISORY_STOP_WARN_PCT', 'ADVISORY_STALL_RETRACE_PCT',
        ]],
        'capital' => ['مدیریت سرمایه', [
            'ACCOUNT_BALANCE', 'RISK_PER_TRADE_PCT', 'MAX_DAILY_LOSSES', 'MAX_DAILY_SIGNALS', 'MAX_OPEN_TRADES',
            'MIN_SIGNAL_GAP_MINUTES', 'MAX_TRADE_HOURS', 'SYMBOL_LOSS_COOLDOWN_SECONDS',
        ]],
        'strategy' => ['استراتژی و فیلترها', [
            'REQUIRE_HTF_ALIGNMENT', 'REQUIRE_DISCOUNT_PREMIUM', 'MIN_SIGNAL_SCORE', 'MIN_CONFLUENCE_SCORE',
            'ZONE_REACH_ATR', 'SETUP_VOLUME_MULT', 'BREAK_VOLUME_RATIO', 'MAX_CHASE_ATR',
            'REVERSAL_RUN_PCT', 'BASE_RANGE_PCT', 'REQUIRE_REVERSAL_CANDLE', 'REVERSAL_CONFIRM_TIMEFRAME',
            'REQUIRE_FRESH_REVERSAL_ZONE', 'REQUIRE_SWEEP_AT_ZONE',
            'REQUIRE_KILLZONE', 'MIN_FVG_MITIGATION_PCT', 'STRONG_ZONE_VETO_TOUCHES',
            'CONFLUENCE_STRUCTURE_CAP', 'CONFLUENCE_LIQUIDITY_CAP', 'CONFLUENCE_LOCATION_CAP',
            'CONFLUENCE_MOMENTUM_CAP', 'CONFLUENCE_VOLUME_CAP', 'CONFLUENCE_HTF_CAP',
            'MIN_ROOM_TO_TARGET_R', 'HTF_CONFIRM_MAP', 'REQUIRE_APLUS_SETUP', 'APLUS_MIN_CONFIRMATIONS',
            'REQUIRE_ADX_FILTER', 'MIN_ADX', 'MAX_OPPOSING_VOTE_RATIO', 'REQUIRE_BREAKOUT_MOMENTUM',
            'NEWS_BLACKOUT_START', 'NEWS_BLACKOUT_END',
        ]],
        'scanner' => ['اسکنر', [
            'SCANNER_GAINER_SHARE', 'SCANNER_LOSER_SHARE', 'SCANNER_MIN_MOVE_PCT',
        ]],
    ];

    private function scanVerdict(): string
    {
        $raw = $this->getSetting('last_scan_report', '');
        $report = $raw !== '' ? json_decode($raw, true) : null;

        if (!is_array($report)) {
            $symbols = (new SymbolRepository())->countActive();
            if ($symbols === 0) {
                return "هنوز هیچ اسکنی انجام نشده و نماد فعالی نیست.";
            }
            return "اسکنر نماد دارد ولی هنوز پاس سیگنالی ثبت نشده.";
        }

        $ago = max(0, time() - (int) ($report['at'] ?? 0));
        $when = $ago < 120 ? 'همین الان' : sprintf('%d دقیقه پیش', (int) round($ago / 60));

        if (!empty($report['published'])) {
            return sprintf('آخرین پاس (%s): سیگنال منتشر شد.', $when);
        }

        $today = $this->signalRepo->todayTally();
        if ($today['losses'] >= Config::maxDailyLosses()) {
            return sprintf(
                "همین الان (%s): انتشار تا فردا متوقف است.\nامروز %d معامله با حد ضرر بسته شد و سقف روزانه ضرر (%d) پر شده.",
                $when,
                $today['losses'],
                Config::maxDailyLosses()
            );
        }
        $maxSignals = Config::maxDailySignals();
        if ($maxSignals > 0 && $today['published'] >= $maxSignals) {
            return sprintf(
                "همین الان (%s): سهمیه سیگنال امروز (%d از %d) پر شده و ربات تا فردا منتشر نمی‌کند.",
                $when,
                $today['published'],
                $maxSignals
            );
        }
        $maxOpen = Config::maxOpenTrades();
        $openNow = $this->signalRepo->countOpen();
        if ($maxOpen > 0 && $openNow >= $maxOpen) {
            return sprintf(
                "همین الان (%s): تعداد معاملات باز (%d از %d) به سقف رسیده.",
                $when,
                $openNow,
                $maxOpen
            );
        }
        $gapMinutes = Config::minSignalGapMinutes();
        $lastPublished = $this->signalRepo->lastPublishedAt();
        if ($gapMinutes > 0 && $lastPublished !== null && time() - $lastPublished < $gapMinutes * 60) {
            return sprintf(
                "همین الان (%s): از آخرین سیگنال کمتر از %d دقیقه گذشته — سیگنال بعدی حدود %d دقیقه دیگر.",
                $when,
                $gapMinutes,
                (int) ceil(($gapMinutes * 60 - (time() - $lastPublished)) / 60)
            );
        }

        $symbols = (int) ($report['symbols'] ?? 0);
        if ($symbols === 0) {
            return sprintf("آخرین پاس (%s): هیچ نماد فعالی نبود.", $when);
        }

        $visited = (int) ($report['visited'] ?? 0);
        $symbolsTotal = (int) ($report['symbols'] ?? 0);
        $rotation = '';
        if ($visited > 0 && $symbolsTotal > 0) {
            $minutes = max(1, (int) ceil($symbolsTotal / max(1, $visited)));
            $rotation = sprintf(
                "\nاین اجرا %d ارز از %d ارز بررسی شد (موقعیت چرخش: %d). یک دور کامل بازار حدود %d دقیقه طول می‌کشد.",
                $visited,
                $symbolsTotal,
                (int) ($report['cursor'] ?? 0),
                $minutes
            );
        }

        $qualified = (int) ($report['qualified'] ?? 0);
        if ($qualified > 0) {
            return sprintf(
                "آخرین پاس (%s): %d ستاپ قابل انتشار پیدا شد ولی هیچ‌کدام ارسال نشد.%s",
                $when,
                $qualified,
                $rotation
            );
        }

        $best = $report['best'] ?? null;
        $min = (float) ($report['min_score'] ?? 45);
        if (!is_array($best)) {
            return sprintf('آخرین پاس (%s): %d نماد در بازار، هیچ ستاپی حتی نیمه‌کاره پیدا نشد.%s', $when, $symbols, $rotation);
        }

        $verdict = sprintf(
            "آخرین پاس (%s): %d نماد در بازار، سیگنالی منتشر نشد.%s\nبهترین چیزی که دید: %s (%s) با امتیاز %.1f — حداقل لازم %.1f\nدلیل رد شدن: %s",
            $when,
            $symbols,
            $rotation,
            (string) $best['symbol'],
            (string) $best['timeframe'],
            (float) $best['score'],
            $min,
            (string) $best['reason']
        );

        return $verdict;
    }

    private function renderAuto(int $chatId, int $messageId, array $parts, int $userId): void
    {
        $action = $parts[2] ?? null;

        if ($action === 'riskfree') {
            $this->setSetting('RISK_FREE_ENABLED', Config::riskFreeEnabled() ? 'false' : 'true');
        }

        if ($action === 'cat' && isset($parts[3])) {
            $this->renderAutoCategory($chatId, $messageId, (string) $parts[3]);
            return;
        }

        if ($action === 'closeall') {
            $open = $this->signalRepo->countOpen();
            if ($open === 0) {
                $this->telegram->sendMessage($chatId, 'معامله باز فعالی وجود ندارد.');
            } else {
                $this->render($chatId, $messageId, sprintf(
                    "مطمئنی؟ %d معامله باز با قیمت لحظه‌ای بسته می‌شوند.",
                    $open
                ), [
                    'inline_keyboard' => [
                        [['text' => 'بله، همه رو ببند', 'callback_data' => 'admin:auto:closeall_yes']],
                        [['text' => 'انصراف', 'callback_data' => 'admin:auto']],
                    ],
                ]);
            }
            return;
        }

        if ($action === 'closeall_yes') {
            $this->closeAllOpenPositions($chatId);
        }

        if ($action === 'set') {
            $key = (string) ($parts[3] ?? '');
            if (isset(self::AUTO_SETTINGS[$key])) {
                [$label, $hint] = self::AUTO_SETTINGS[$key];
                $this->states->set($userId, 'awaiting_auto_setting', ['key' => $key]);
                $this->telegram->sendMessage($chatId, "مقدار جدید «{$label}» را بفرستید.\n\n{$hint}");
                return;
            }
        }

        $open = $this->signalRepo->countOpen();
        $perf = $this->signalRepo->performance();
        $today = $this->signalRepo->todayTally();

        $lines = [
            'حالت اتومات',
            '',
            $this->scanVerdict(),
            '',
            sprintf('معامله باز: %d', $open),
            'سیگنال بعدی: آماده (منتظر ستاپ مناسب)',
            sprintf('ریسک‌فری بعد از تارگت ۱: %s', Config::riskFreeEnabled() ? 'فعال' : 'غیرفعال'),
            '',
            sprintf('کارنامه: %d برد | %d باخت | %d بدون ضرر از %d معامله | نرخ برد %.1f%%',
                $perf['wins'], $perf['losses'], $perf['breakeven'], $perf['total'], $perf['win_rate']),
            sprintf('امروز: %d باخت، %d سیگنال', $today['losses'], $today['published']),
        ];

        $keyboard = [[[
            'text' => Config::riskFreeEnabled() ? 'خاموش کردن ریسک‌فری' : 'روشن کردن ریسک‌فری',
            'callback_data' => 'admin:auto:riskfree',
        ]]];
        foreach (self::AUTO_CATEGORIES as $catKey => [$catLabel, $keys]) {
            $keyboard[] = [['text' => $catLabel, 'callback_data' => "admin:auto:cat:{$catKey}"]];
        }
        if ($open > 0) {
            $keyboard[] = [['text' => 'بستن همه معاملات باز', 'callback_data' => 'admin:auto:closeall']];
        }
        $keyboard[] = $this->backRow();

        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => $keyboard]);
    }

    private function renderAutoCategory(int $chatId, int $messageId, string $catKey): void
    {
        if (!isset(self::AUTO_CATEGORIES[$catKey])) {
            $this->render($chatId, $messageId, 'دسته‌بندی نامعتبر.', ['inline_keyboard' => [$this->backRow('admin:auto')]]);
            return;
        }
        [$catLabel, $keys] = self::AUTO_CATEGORIES[$catKey];

        $lines = array_merge(["اتومات ← {$catLabel}", ''], $this->autoCategoryStatusLines($catKey));

        $keyboard = [];
        $row = [];
        foreach ($keys as $key) {
            [$label] = self::AUTO_SETTINGS[$key];
            $row[] = ['text' => $label, 'callback_data' => 'admin:auto:set:' . $key];
            if (count($row) === 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }
        $keyboard[] = $this->backRow('admin:auto');

        $this->render($chatId, $messageId, implode("\n", $lines), ['inline_keyboard' => $keyboard]);
    }

    private function closeAllOpenPositions(int $chatId): void
    {
        $positions = $this->signalRepo->openPositions();
        if (empty($positions)) {
            $this->telegram->sendMessage($chatId, 'معامله باز فعالی وجود ندارد.');
            return;
        }

        $store = new MarketDataStore();
        $lines = ['نتیجه بستن دستی معاملات باز:', ''];
        foreach ($positions as $row) {
            $exchange = (string) $row['exchange'];
            $symbol = (string) $row['symbol'];
            $price = $store->latestPrice($exchange, $symbol);
            if ($price <= 0) {
                $price = (float) $row['entry_price'];
                $lines[] = sprintf('%s: قیمت لحظه‌ای در دسترس نبود — با قیمت ورود بسته شد.', SignalCardFactory::displaySymbol($symbol));
            }
            $this->signalRepo->close((int) $row['id'], 'manual', $price);
            $stats = SignalFormatter::resultStats($row, $price);
            $lines[] = sprintf(
                '%s %s | ورود %s ← خروج %s | %s%%',
                (string) $row['direction'],
                SignalCardFactory::displaySymbol($symbol),
                PriceFormatter::format((float) $row['entry_price']),
                PriceFormatter::format($price),
                $stats['pnl_signed']
            );
        }
        $this->telegram->sendMessage($chatId, implode("\n", $lines));
    }

    private function autoCategoryStatusLines(string $catKey): array
    {
        return match ($catKey) {
            'timing' => [
                sprintf('تا %d سیگنال در هر پاس', Config::signalsPerPass()),
                sprintf('صرافی‌های قابل معامله: %s', empty(Config::tradableVenues()) ? 'همه (فیلتر خاموش)' : implode('، ', Config::tradableVenues())),
                sprintf('تایم‌فریم: %s', implode(', ', Config::signalTimeframes())),
                sprintf('صرافی اصلی: %s', Config::primaryExchange()),
                sprintf('پوشش بازار: %s | هر اجرا %d ثانیه چرخش',
                    Config::signalMaxSymbolsPerPass() === 0 ? 'همه ارزها (بدون محدودیت)' : Config::signalMaxSymbolsPerPass() . ' ارز',
                    Config::rotationBudgetSeconds()),
                sprintf('حداقل حجم ۲۴ ساعته: %s دلار', number_format(Config::minVolumeUsdt())),
            ],
            'targets' => [
                sprintf('تارگت‌ها با اهرم: ۱) %.0f%% | ۲) %.0f%% | ۳) %.0f%% | ۴) %.0f%% | حد ضرر حداکثر %.0f%%',
                    Config::tp1LeveragedPercent(), Config::tp2LeveragedPercent(), Config::tp3LeveragedPercent(),
                    Config::tp4LeveragedPercent(), Config::maxStopLeveragedPercent()),
                sprintf('ریسک به ریوارد: %.2f | %.2f | %.2f | %.2f',
                    Config::tp1RiskReward(), Config::tp2RiskReward(), Config::tp3RiskReward(), Config::tp4RiskReward()),
                sprintf('درصد خروج در تارگت ۱: %.0f%%', Config::tp1ClosePercent()),
                sprintf('هشدار قبل تی۱: %.0f%% | هشدار بعد تی۳: %.0f%%',
                    Config::advisoryStopWarnPercent(), Config::advisoryStallRetracePercent()),
                sprintf('%s → اهرم %dx', implode(',', Config::majorAssets()), Config::leverageMajor()),
                sprintf('بقیه ارزها → اهرم %dx تا %dx', Config::leverageAltMin(), Config::leverageAltMax()),
            ],
            'capital' => [
                sprintf('سرمایه مرجع: %s USDT | ریسک هر معامله: %.1f%%',
                    MoneyManager::money(Config::accountBalance()), Config::riskPerTradePercent()),
                sprintf('سقف روزانه: %d باخت | %s سیگنال',
                    Config::maxDailyLosses(),
                    Config::maxDailySignals() > 0 ? (string) Config::maxDailySignals() : 'بدون سقف'),
                sprintf('حداکثر معامله باز: %s | فاصله بین سیگنال‌ها: %s | عمر معامله تا تارگت ۱: %s',
                    Config::maxOpenTrades() > 0 ? (string) Config::maxOpenTrades() : 'بدون سقف',
                    Config::minSignalGapMinutes() > 0 ? Config::minSignalGapMinutes() . ' دقیقه' : 'خاموش',
                    Config::maxTradeHours() > 0 ? Config::maxTradeHours() . ' ساعت' : 'خاموش'),
            ],
            'strategy' => [
                sprintf('استراتژی: %s (حداقل امتیاز هم‌گرایی %.0f)', Config::strategyName(), Config::minConfluenceScore()),
                sprintf('حداقل امتیاز سیگنال: %.1f', Config::minSignalScore()),
                sprintf('تایید کندل برگشتی: %s | تایم‌فریم %s',
                    Config::requireReversalCandle() ? 'فعال' : 'خاموش', Config::reversalConfirmTimeframe()),
                sprintf('Killzone: %s', Config::requireKillzone() ? 'فعال' : 'خاموش'),
                sprintf('وتوی سطح قوی: %d بار لمس',
                    Config::strongZoneVetoTouches()),
                sprintf('سقف شواهد خلاف جهت: %s | تایید مومنتوم برای شکست: %s',
                    Config::maxOpposingVoteRatio() < 1.0 ? number_format(Config::maxOpposingVoteRatio(), 2) : 'خاموش',
                    Config::requireBreakoutMomentum() ? 'فعال' : 'خاموش'),
            ],
            'scanner' => [
                sprintf('سهم بیشترین رشد/ضرر در لیست اسکن: %.0f%% / %.0f%%', Config::scannerGainerShare(), Config::scannerLoserShare()),
                sprintf('حداقل حرکت ۲۴ ساعته: %.0f%%', Config::scannerMinMovePercent()),
            ],
            default => [],
        };
    }

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
        $this->render($chatId, $messageId, "Test Signal", [
            'inline_keyboard' => [
                [['text' => 'اجرای تست (با بازار)', 'callback_data' => 'admin:test_signal:run']],
                [['text' => 'پیش‌نمایش قالب (فوری)', 'callback_data' => 'admin:test_signal:preview']],
                [['text' => 'تست روی کانال', 'callback_data' => 'admin:test_signal:channel']],
                $this->backRow(),
            ],
        ]);
    }

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
            tp3: $entry + $risk * Config::tp3RiskReward(),
            tp4: $entry + $risk * Config::tp4RiskReward(),
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

    private function previewRow(Signal $signal): array
    {
        return [
            'id' => 0, 'symbol' => $signal->symbol, 'exchange' => $signal->exchange,
            'direction' => $signal->direction->value, 'timeframe' => $signal->timeframe,
            'entry_price' => $signal->entry, 'stop_loss' => $signal->stopLoss,
            'tp1' => $signal->tp1, 'tp2' => $signal->tp2, 'tp3' => $signal->tp3, 'tp4' => $signal->tp4,
            'leverage' => $signal->leverage,
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

    private function runChannelTest(int $chatId): void
    {
        $channels = $this->channels->listActiveWithSettings();
        if (empty($channels)) {
            $this->telegram->sendMessage(
                $chatId,
                "هیچ کانال فعالی پیدا نشد."
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

        $buttons = Config::channelButtonsKeyboard();
        $hasButtonIcon = $buttons !== null && TelegramEntityUtils::hasButtonEmojiIcon($buttons);

        $report = ["نتیجه تست روی کانال‌ها:", ''];

        foreach ($channels as $channel) {
            $title = (string) ($channel['title'] ?? $channel['chat_id']);
            $chat = (int) $channel['chat_id'];

            $opts = $buttons !== null ? ['reply_markup' => $buttons] : [];
            $res = $signalCard !== null
                ? $this->telegram->sendPhoto($chat, $signalCard, $signalRendered['text'], $signalRendered['entities'], $opts)
                : $this->telegram->sendMessage($chat, $signalRendered['text'], $signalRendered['entities'], $opts);

            $buttonIconAccepted = $hasButtonIcon ? true : null;
            if ($hasButtonIcon && !($res['ok'] ?? false)) {
                $opts = ['reply_markup' => TelegramEntityUtils::stripButtonEmojiIcons($buttons)];
                $res = $signalCard !== null
                    ? $this->telegram->sendPhoto($chat, $signalCard, $signalRendered['text'], $signalRendered['entities'], $opts)
                    : $this->telegram->sendMessage($chat, $signalRendered['text'], $signalRendered['entities'], $opts);
                $buttonIconAccepted = false;
            }

            if (!($res['ok'] ?? false)) {
                $report[] = sprintf("%s — ارسال نشد: %s", $title, (string) ($res['description'] ?? 'خطای نامشخص'));
                $report[] = '';
                continue;
            }

            $messageId = (int) ($res['result']['message_id'] ?? 0);
            $report[] = sprintf('%s — سیگنال ارسال شد', $title);

            if ($buttonIconAccepted !== null) {
                $report[] = $buttonIconAccepted
                    ? '   آیکون پریمیوم دکمه قبول شد'
                    : '   آیکون پریمیوم دکمه رد شد؛ بدون آیکون ارسال شد';
            }

            if ($sentEmoji > 0) {
                $echoed = $this->countCustomEmoji(
                    $res['result']['caption_entities'] ?? $res['result']['entities'] ?? []
                );
                $report[] = $echoed >= $sentEmoji
                    ? sprintf('   ایموجی پریمیوم قبول شد (%d از %d)', $echoed, $sentEmoji)
                    : sprintf('   ایموجی پریمیوم در کانال حذف شد (%d از %d باقی ماند)', $echoed, $sentEmoji);
            } else {
                $report[] = '   در قالب فعلی هیچ ایموجی پریمیومی نیست';
            }

            $opts = $messageId > 0
                ? ['reply_parameters' => ['message_id' => $messageId, 'allow_sending_without_reply' => true]]
                : [];
            if ($buttons !== null) {
                $opts['reply_markup'] = $buttonIconAccepted === false
                    ? TelegramEntityUtils::stripButtonEmojiIcons($buttons)
                    : $buttons;
            }
            $replyRes = $resultCard !== null
                ? $this->telegram->sendPhoto($chat, $resultCard, $resultRendered['text'], $resultRendered['entities'], $opts)
                : $this->telegram->sendMessage($chat, $resultRendered['text'], $resultRendered['entities'], $opts);

            $report[] = ($replyRes['ok'] ?? false)
                ? '   ↩اعلام تارگت به‌صورت ریپلای ارسال شد'
                : sprintf('   ریپلای ارسال نشد: %s', (string) ($replyRes['description'] ?? 'خطای نامشخص'));
            $report[] = '';
        }

        $this->telegram->sendMessage($chatId, implode("\n", $report));
    }

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
        $symbols = (new SymbolRepository())->universe(1);
        if (empty($symbols)) {
            $this->telegram->sendMessage($chatId, "هیچ نماد فعالی برای تست وجود ندارد.");
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

        foreach (Config::signalTimeframes() as $tf) {
            $htfTf = Config::htfConfirmTimeframe($tf);
            if ($htfTf !== null && !in_array($htfTf, $timeframes, true)) {
                $timeframes[] = $htfTf;
            }
        }
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

        $signalTfs = Config::signalTimeframes();
        $mainTf = $signalTfs[array_key_last($signalTfs)] ?? '1h';
        $candles = $snapshot->candlesFor($mainTf);

        $diag = [];
        $diag[] = "تست تشخیصی: {$target['symbol']} ({$target['exchange']}, {$mainTf})";
        $diag[] = "قیمت: {$snapshot->price} | کندل: " . count($candles);

        if (count($candles) < 30) {
            $diag[] = "کندل کافی نیست (حداقل ۳۰).";
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
            $diag[] = "بایاس خنثی.";
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $strategy = (new StrategyEngine())->get('default_structure');
        $plan = $strategy?->evaluate($snapshot, $mainTf, $zones, $orderBlocks, $fvgs, $confluence);

        if ($plan === null) {
            $diag[] = "استراتژی پلن معامله نساخت.";
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
            $diag[] = "TradePlanner معامله نساخت (اهرم جایی برای حد ضرر نگذاشت).";
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
            $diag[] = sprintf("امتیاز (%.1f) کمتر از حداقل (%.1f) است.", $confluence['score'], Config::minSignalScore());
            $this->telegram->sendMessage($chatId, implode("\n", $diag));
            return;
        }

        $generator = new SignalGenerator(new IndicatorEngine());
        $signal = $generator->generate($snapshot, $mainTf);

        if ($signal === null) {
            $diag[] = "SignalGenerator سیگنالی برنگرداند (Cooldown/تکراری).";
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

    private function renderHistory(int $chatId, int $messageId): void
    {
        $rows = $this->signalRepo->recent(10);
        if (empty($rows)) {
            $this->render($chatId, $messageId, "هنوز سیگنالی ثبت نشده است.", ['inline_keyboard' => [$this->backRow()]]);
            return;
        }
        $lines = ["۱۰ سیگنال اخیر:\n"];
        foreach ($rows as $r) {
            $result = $r['result'] ?? ($r['outcome'] ?? null);
            $resultMark = match (true) {
                $result === 'tp2' => 'تارگت ۲',
                $result === 'tp1' => 'تارگت ۱',
                $result === 'be' => 'بدون ضرر',
                $result === 'sl' => 'حد ضرر',
                ($r['stage'] ?? '') === 'risk_free' => 'ریسک‌فری',
                in_array($r['status'], ['sent', 'queued'], true) => 'باز',
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

    private function renderHealth(int $chatId, int $messageId, array $parts): void
    {
        if (($parts[2] ?? null) === 'errors') {
            $this->renderRecentErrors($chatId, $messageId);
            return;
        }

        $exHealth = $this->exchangeManager->healthSnapshot();
        $exLines = [];
        foreach (['binance', 'mexc', 'bybit', 'okx', 'kucoin', 'gate', 'bitget', 'htx', 'cryptocompare'] as $ex) {
            $exLines[] = ucfirst($ex) . ': ' . ($exHealth[$ex] ?? 'نامعلوم');
        }

        $telegramOk = ($this->telegram->getMe()['ok'] ?? false) ? 'سالم' : 'قطع';
        $symbolCount = (new SymbolRepository())->countActive();
        $signalsToday = $this->signalRepo->countToday();
        $activeSignals = $this->signalRepo->countActive();
        $lastScan = (new ScannerRunRepository())->lastRunAt() ?? '-';
        $errCountStmt = Database::pdo()->prepare("SELECT COUNT(*) FROM logs WHERE level IN ('error','critical') AND created_at >= :since");
        $errCountStmt->execute([':since' => date('Y-m-d H:i:s', time() - 86400)]);
        $errCount = (int) $errCountStmt->fetchColumn();

        $text = "وضعیت سیستم\n\n"
            . "Worker: " . $this->workerHeartbeatStatus() . "\n"
            . "Telegram: $telegramOk\n"
            . implode("\n", $exLines) . "\n\n"
            . "نمادها: $symbolCount\n"
            . "سیگنال‌های امروز: $signalsToday\n"
            . "سیگنال‌های فعال: $activeSignals\n"
            . "آخرین اسکن: $lastScan\n"
            . "خطاها (۲۴ ساعت اخیر): $errCount";

        $keyboard = [
            [['text' => 'آخرین خطاها', 'callback_data' => 'admin:health:errors']],
            $this->backRow(),
        ];
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => $keyboard]);
    }

    private function renderRecentErrors(int $chatId, int $messageId): void
    {
        $rows = Database::pdo()->query(
            "SELECT level, channel, message, context, created_at FROM logs WHERE level IN ('error','critical') ORDER BY id DESC LIMIT 10"
        )->fetchAll();

        if (empty($rows)) {
            $this->render($chatId, $messageId, "هیچ خطایی ثبت نشده.", ['inline_keyboard' => [$this->backRow('admin:health')]]);
            return;
        }

        $lines = ["۱۰ خطای اخیر:\n"];
        foreach ($rows as $r) {
            $ctx = $r['context'] !== null ? (' ' . mb_strimwidth($r['context'], 0, 150, '…')) : '';
            $lines[] = sprintf("%s [%s/%s] %s%s", $r['created_at'], strtoupper($r['level']), $r['channel'], $r['message'], $ctx);
        }
        $text = mb_strimwidth(implode("\n", $lines), 0, 4000, '…');
        $this->render($chatId, $messageId, $text, ['inline_keyboard' => [
            [['text' => 'بروزرسانی', 'callback_data' => 'admin:health:errors']],
            $this->backRow('admin:health'),
        ]]);
    }

    private function workerHeartbeatStatus(): string
    {
        $v = $this->getSetting('worker_heartbeat', '');
        if ($v === '') {
            return "هرگز اجرا نشده\n"
                . '   php ' . __DIR__ . '/worker.php';
        }

        $ago = time() - (int) $v;
        if ($ago < 180) {
            return sprintf('فعال (%d ثانیه پیش)', max(0, $ago));
        }
        if ($ago < 3600) {
            return sprintf("%d دقیقه پیش", (int) round($ago / 60));
        }
        return sprintf("%d ساعت پیش — کرون متوقف شده\n   php %s/worker.php", (int) round($ago / 3600), __DIR__);
    }

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
                $this->telegram->sendMessage($chatId, "آیدی نامعتبر است.");
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
                ? "کانال با موفقیت اضافه شد و ربات دسترسی ارسال پیام دارد."
                : "کانال ثبت شد اما ربات اجازه ارسال پیام ندارد (وضعیت: {$access['status']}).";
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
            $this->telegram->sendMessage($chatId, "متن «$key» ذخیره شد.");
            return true;
        }

        if ($state['state'] === 'awaiting_scanner_filter') {
            $this->states->clear($userId);
            $setting = (string) ($state['payload']['setting'] ?? '');
            $value = trim($text);
            if ($setting === '') {
                return true;
            }

            if ($value === 'حذف') {
                Database::pdo()->prepare('DELETE FROM bot_settings WHERE setting_key = :k')->execute([':k' => $setting]);
                $this->telegram->sendMessage($chatId, "به مقدار پیش‌فرض (env.php) برگشت.");
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
            if ($setting === 'ALLOWED_QUOTE_ASSETS' && $value !== '' && $value !== '*') {
                $tokens = array_map(static fn($x) => strtoupper(trim($x)), explode(',', $value));
                $invalid = array_filter($tokens, static fn($x) => $x !== '' && !preg_match('/^[A-Z0-9]{2,10}$/', $x));
                if (!empty($invalid)) {
                    $this->telegram->sendMessage($chatId, "این مقدار معتبر نیست: «" . implode('، ', $invalid) . "»");
                    return true;
                }
            }

            if ($setting === 'ALLOWED_QUOTE_ASSETS' && $value === '') {
                $value = '*';
            }
            $this->setSetting($setting, $value);
            $this->telegram->sendMessage($chatId, "ذخیره شد.");
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
                $this->telegram->sendMessage($chatId, "به مقدار پیش‌فرض برگشت.");
                return true;
            }

            $numeric = ['TP1_LEVERAGED_PCT', 'TP2_LEVERAGED_PCT',
                        'TP3_LEVERAGED_PCT', 'TP4_LEVERAGED_PCT', 'TP1_CLOSE_PERCENT',
                        'ADVISORY_STOP_WARN_PCT', 'ADVISORY_STALL_RETRACE_PCT',
                        'MAX_STOP_LEVERAGED_PCT', 'LEVERAGE_MAJOR',
                        'LEVERAGE_ALT_MIN', 'LEVERAGE_ALT_MAX', 'LEVERAGE_LIQUIDATION_BUFFER',
                        'SIGNAL_MAX_SYMBOLS_PER_PASS', 'MIN_SIGNAL_SCORE',
                        'ACCOUNT_BALANCE', 'RISK_PER_TRADE_PCT', 'MAX_DAILY_LOSSES', 'MAX_DAILY_SIGNALS', 'MAX_OPEN_TRADES',
                        'BREAK_VOLUME_RATIO', 'MAX_CHASE_ATR', 'REVERSAL_RUN_PCT', 'BASE_RANGE_PCT',
                        'SCANNER_GAINER_SHARE', 'SCANNER_LOSER_SHARE', 'SCANNER_MIN_MOVE_PCT',
                        'SIGNALS_PER_PASS', 'MIN_CONFLUENCE_SCORE', 'ZONE_REACH_ATR', 'SETUP_VOLUME_MULT',
                        'MIN_VOLUME_USDT', 'ROTATION_BUDGET_SECONDS', 'MIN_FVG_MITIGATION_PCT', 'STRONG_ZONE_VETO_TOUCHES',
                        'CONFLUENCE_STRUCTURE_CAP', 'CONFLUENCE_LIQUIDITY_CAP', 'CONFLUENCE_LOCATION_CAP',
                        'CONFLUENCE_MOMENTUM_CAP', 'CONFLUENCE_VOLUME_CAP', 'CONFLUENCE_HTF_CAP',
                        'MIN_ROOM_TO_TARGET_R', 'SYMBOL_LOSS_COOLDOWN_SECONDS', 'APLUS_MIN_CONFIRMATIONS', 'MIN_ADX',
                        'MIN_SIGNAL_GAP_MINUTES', 'MAX_TRADE_HOURS', 'MAX_OPPOSING_VOTE_RATIO'];
            if (in_array($key, $numeric, true) && !is_numeric($value)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید عدد باشه.");
                return true;
            }
            if ($key === 'TP2_LEVERAGED_PCT' && (float) $value <= Config::tp1LeveragedPercent()) {
                $this->telegram->sendMessage($chatId, "تارگت ۲ باید بزرگ‌تر از تارگت ۱ باشه (الان تارگت ۱ روی " . Config::tp1LeveragedPercent() . "٪ است).");
                return true;
            }
            if ($key === 'TP3_LEVERAGED_PCT' && (float) $value <= Config::tp2LeveragedPercent()) {
                $this->telegram->sendMessage($chatId, "تارگت ۳ باید بزرگ‌تر از تارگت ۲ باشه (الان تارگت ۲ روی " . Config::tp2LeveragedPercent() . "٪ است).");
                return true;
            }
            if ($key === 'TP4_LEVERAGED_PCT' && (float) $value <= Config::tp3LeveragedPercent()) {
                $this->telegram->sendMessage($chatId, "تارگت ۴ باید بزرگ‌تر از تارگت ۳ باشه (الان تارگت ۳ روی " . Config::tp3LeveragedPercent() . "٪ است).");
                return true;
            }
            if (($key === 'TP1_CLOSE_PERCENT' || $key === 'ADVISORY_STALL_RETRACE_PCT') && ((float) $value < 0 || (float) $value > 100)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید بین 0 تا 100 باشه.");
                return true;
            }
            if ($key === 'ADVISORY_STOP_WARN_PCT' && ((float) $value < 1 || (float) $value > 100)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید بین 1 تا 100 باشه.");
                return true;
            }
            if ($key === 'MAX_OPPOSING_VOTE_RATIO' && ((float) $value < 0.1 || (float) $value > 1)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید بین 0.1 تا 1 باشه.");
                return true;
            }
            if ($key === 'LEVERAGE_ALT_MAX' && (int) $value < Config::leverageAltMin()) {
                $this->telegram->sendMessage($chatId, "حداکثر اهرم نمی‌تواند کمتر از حداقل اهرم (" . Config::leverageAltMin() . ") باشد.");
                return true;
            }
            if ($key === 'SIGNAL_TIMEFRAMES') {
                $known = ['1m', '5m', '15m', '30m', '1h', '2h', '4h', '1D'];
                $given = array_filter(array_map('trim', explode(',', $value)));
                $unknown = array_diff($given, $known);
                if (empty($given) || !empty($unknown)) {
                    $this->telegram->sendMessage($chatId, "تایم‌فریم نامعتبر. فقط از این‌ها استفاده کنید: " . implode(', ', $known));
                    return true;
                }

            }
            if ($key === 'REVERSAL_CONFIRM_TIMEFRAME') {
                $known = ['1m', '5m', '15m', '30m', '1h', '2h', '4h', '1D'];
                if (!in_array($value, $known, true)) {
                    $this->telegram->sendMessage($chatId, "تایم‌فریم نامعتبر. فقط از این‌ها استفاده کنید: " . implode(', ', $known));
                    return true;
                }
            }
            if (in_array($key, ['REQUIRE_REVERSAL_CANDLE', 'REQUIRE_KILLZONE', 'REQUIRE_FRESH_REVERSAL_ZONE', 'REQUIRE_SWEEP_AT_ZONE', 'REQUIRE_ADX_FILTER', 'REQUIRE_BREAKOUT_MOMENTUM'], true)
                && !in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off'], true)) {
                $this->telegram->sendMessage($chatId, "این مقدار باید true یا false باشه.");
                return true;
            }

            $this->setSetting($key, $value);
            if ($key === 'TRADABLE_VENUES') {
                VenueListings::forget();
            }

            $this->telegram->sendMessage($chatId, "ذخیره شد.");
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
            $this->telegram->sendMessage($chatId, $value === '' ? 'مقدار پاک شد.' : 'ذخیره شد.');
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
            $this->telegram->sendMessage($chatId, "حداقل امتیاز این کانال روی {$value} تنظیم شد.");
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
                $this->telegram->sendMessage($chatId, "این پیام Forward نبود.");
                return true;
            }
            $this->quotes->store('channel_pin', (string) $channelId, $origin['chat_id'], $origin['message_id'], $text, is_array($entities) ? $entities : []);
            $this->channels->updateSettings($channelId, ['quote_enabled' => 1]);
            $this->telegram->sendMessage($chatId, "پیام مرجع ذخیره شد و Quote فعال شد.");
            return true;
        }

        if ($state['state'] === 'awaiting_button_text') {
            $this->states->clear($userId);
            $slot = (int) ($state['payload']['slot'] ?? 0);
            if ($slot !== 1) {
                return true;
            }
            $value = trim($text);
            if ($value === 'حذف') {
                TextStore::putValue("BUTTON{$slot}_TEXT", null);
                $this->telegram->sendMessage($chatId, "متن دکمه به مقدار پیش‌فرض برگشت.");
                return true;
            }
            if ($value === '') {
                $this->telegram->sendMessage($chatId, "متن دکمه نمی‌تونه خالی باشه.");
                return true;
            }
            TextStore::putValue("BUTTON{$slot}_TEXT", $value);
            $this->telegram->sendMessage($chatId, "متن دکمه ذخیره شد.");
            return true;
        }

        if ($state['state'] === 'awaiting_button_url') {
            $this->states->clear($userId);
            $slot = (int) ($state['payload']['slot'] ?? 0);
            if ($slot !== 1) {
                return true;
            }
            $value = trim($text);
            if ($value === 'حذف') {
                TextStore::putValue("BUTTON{$slot}_URL", null);
                $this->telegram->sendMessage($chatId, "لینک دکمه به مقدار پیش‌فرض برگشت.");
                return true;
            }
            if (!preg_match('#^(https?://|tg://)\S+$#i', $value)) {
                $this->telegram->sendMessage($chatId, "لینک نامعتبره.");
                return true;
            }
            TextStore::putValue("BUTTON{$slot}_URL", $value);
            $this->telegram->sendMessage($chatId, "لینک دکمه ذخیره شد.");
            return true;
        }

        if ($state['state'] === 'awaiting_button_emoji') {
            $this->states->clear($userId);
            $slot = (int) ($state['payload']['slot'] ?? 0);
            if ($slot !== 1) {
                return true;
            }
            if (trim($text) === 'حذف') {
                TextStore::putValue("BUTTON{$slot}_EMOJI_ID", null);
                $this->telegram->sendMessage($chatId, "آیکون دکمه حذف شد.");
                return true;
            }
            $emojiId = null;
            foreach (is_array($entities) ? $entities : [] as $e) {
                if (($e['type'] ?? '') === 'custom_emoji' && !empty($e['custom_emoji_id'])) {
                    $emojiId = (string) $e['custom_emoji_id'];
                    break;
                }
            }
            if ($emojiId === null) {
                $this->telegram->sendMessage($chatId, "توی این پیام ایموجی پریمیومی پیدا نشد.");
                return true;
            }
            TextStore::putValue("BUTTON{$slot}_EMOJI_ID", $emojiId);
            $this->telegram->sendMessage(
                $chatId,
                "آیکون دکمه ذخیره شد."
            );
            return true;
        }

        return false;
    }

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

        $command = preg_match('~^/([a-z]+)(?:@\w+)?(?:\s|$)~i', $text, $m) ? strtolower($m[1]) : '';

        if ($command === 'start') {
            $this->replyWithText($chatId, 'welcome');
            return;
        }

        if ($command === 'help') {
            $this->replyWithText($chatId, 'help');
            return;
        }

        if ($command === 'panel' || $command === 'admin') {
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
            return;
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
        http_response_code(200);
        echo 'OK';
    }
}

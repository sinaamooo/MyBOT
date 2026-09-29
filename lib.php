<?php
/**
 * Shared helpers: configuration, Telegram Bot API client and per-user state storage.
 */
declare(strict_types=1);

const MAX_EMOJIS = 200;   // Telegram limit for one custom emoji set
const CREATE_BATCH = 50;  // createNewStickerSet accepts at most 50 stickers

function config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $file = __DIR__ . '/config.php';
    $local = is_file($file) ? require $file : [];
    $cfg = array_merge([
        'bot_token'   => '',
        'webhook_url' => '',
        'api_url'     => 'https://api.telegram.org',
        'proxy'       => '',
        'data_dir'    => __DIR__ . '/data',
    ], is_array($local) ? $local : []);

    // Environment variables override config.php.
    $env = [
        'bot_token'   => 'BOT_TOKEN',
        'webhook_url' => 'WEBHOOK_URL',
        'api_url'     => 'TELEGRAM_API_URL',
        'proxy'       => 'TELEGRAM_PROXY',
        'data_dir'    => 'BOT_DATA_DIR',
    ];
    foreach ($env as $key => $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            $cfg[$key] = $value;
        }
    }

    if (!preg_match('/^\d+:[\w-]{30,}$/', (string) $cfg['bot_token'])) {
        throw new RuntimeException('Bot token is missing: set bot_token in config.php or the BOT_TOKEN environment variable.');
    }
    return $cfg;
}

/** Secret Telegram sends back in every webhook request, derived from the token. */
function webhookSecret(): string
{
    return hash('sha256', 'webhook:' . config()['bot_token']);
}

/**
 * Calls a Bot API method. Arrays in $params are sent as JSON; $files maps a
 * field name to a local path and switches the request to multipart upload.
 * Waits and retries automatically when Telegram answers 429 Too Many Requests.
 */
function tg(string $method, array $params = [], int $timeout = 60, array $files = []): array
{
    $cfg = config();
    $url = rtrim($cfg['api_url'], '/') . '/bot' . $cfg['bot_token'] . '/' . $method;

    if ($files) {
        $body = [];
        foreach ($params as $key => $value) {
            $body[$key] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        }
        foreach ($files as $key => $path) {
            $body[$key] = new CURLFile($path, mimeFor($path), basename($path));
        }
        $headers = [];
    } else {
        $body = json_encode($params ?: new stdClass(), JSON_UNESCAPED_UNICODE);
        $headers = ['Content-Type: application/json'];
    }

    for ($attempt = 1; ; $attempt++) {
        $raw = httpRequest($url, $body, $headers, $timeout, $error);
        if ($raw === null) {
            $res = ['ok' => false, 'error_code' => 0, 'description' => 'Network error: ' . $error];
        } else {
            $res = json_decode($raw, true);
            if (!is_array($res)) {
                $res = ['ok' => false, 'error_code' => 0, 'description' => 'Invalid response: ' . substr($raw, 0, 200)];
            }
        }

        $retryAfter = (int) ($res['parameters']['retry_after'] ?? 0);
        if (empty($res['ok']) && ($res['error_code'] ?? 0) === 429 && $retryAfter > 0 && $attempt < 5) {
            sleep($retryAfter + 1);
            continue;
        }
        if (empty($res['ok'])) {
            $res['ok'] = false;
            logError($method . ': ' . ($res['description'] ?? 'unknown error'));
        }
        return $res;
    }
}

/** POST when $body is given, GET otherwise. Returns null on network failure. */
function httpRequest(string $url, $body, array $headers, int $timeout, ?string &$error = null): ?string
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_HTTPHEADER     => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $body;
    }
    if (config()['proxy'] !== '') {
        $opts[CURLOPT_PROXY] = config()['proxy'];
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    return $raw === false ? null : (string) $raw;
}

/** Downloads a file previously returned by getFile. */
function downloadFile(string $filePath): ?string
{
    // A local Bot API server (--local) returns absolute paths on disk.
    if ($filePath !== '' && $filePath[0] === '/' && is_file($filePath)) {
        return (string) file_get_contents($filePath);
    }
    $cfg = config();
    $url = rtrim($cfg['api_url'], '/') . '/file/bot' . $cfg['bot_token'] . '/' . $filePath;
    return httpRequest($url, null, [], 60);
}

function mimeFor(string $path): string
{
    $types = ['webp' => 'image/webp', 'png' => 'image/png', 'tgs' => 'application/x-tgsticker', 'webm' => 'video/webm'];
    return $types[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
}

// ---------------------------------------------------------------- messages

function h(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send(int $chatId, string $text, ?array $keyboard = null, bool $preview = false): array
{
    $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if (!$preview) {
        $params['link_preview_options'] = ['is_disabled' => true];
    }
    if ($keyboard !== null) {
        $params['reply_markup'] = ['inline_keyboard' => $keyboard];
    }
    return tg('sendMessage', $params);
}

/** Edits a message, or sends a new one when there is nothing (left) to edit. */
function editOrSend(int $chatId, ?int $messageId, string $text, ?array $keyboard = null, bool $preview = false): array
{
    if ($messageId !== null) {
        $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'HTML'];
        if (!$preview) {
            $params['link_preview_options'] = ['is_disabled' => true];
        }
        if ($keyboard !== null) {
            $params['reply_markup'] = ['inline_keyboard' => $keyboard];
        }
        $res = tg('editMessageText', $params);
        if ($res['ok'] || stripos($res['description'] ?? '', 'not modified') !== false) {
            return $res;
        }
    }
    return send($chatId, $text, $keyboard, $preview);
}

// ---------------------------------------------------------------- storage

function dataDir(): string
{
    $dir = rtrim(config()['data_dir'], '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

/**
 * Runs $fn(array &$state) while holding an exclusive lock on the user's state
 * file, then saves the state if it changed. Returns whatever $fn returns.
 */
function withState(int $userId, callable $fn)
{
    $fp = fopen(dataDir() . '/user_' . $userId . '.json', 'c+');
    flock($fp, LOCK_EX);
    try {
        $state = json_decode((string) stream_get_contents($fp), true);
        $state = is_array($state) ? $state : [];
        $before = $state;

        $result = $fn($state);

        if ($state !== $before) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($state, JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }
        return $result;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function logError(string $message): void
{
    @file_put_contents(dataDir() . '/error.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}

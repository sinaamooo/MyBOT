<?php
/**
 * Minimal fake Telegram Bot API for local end-to-end tests.
 *   php -S 127.0.0.1:8899 tools/mock_telegram.php
 * Set 'telegram_api_base' => 'http://127.0.0.1:8899' in config.php. Every call is appended
 * to MOCK_TG_LOG (default /tmp/mock_telegram.jsonl); uploaded photos are kept next to it.
 */

$log = getenv('MOCK_TG_LOG') ?: '/tmp/mock_telegram.jsonl';
$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if (!preg_match('~^/bot(\d+):[^/]+/(\w+)$~', $path, $m)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error_code' => 404, 'description' => 'Not Found']);
    return;
}
$botId = (int) $m[1];
$method = $m[2];

$params = $_POST;
$raw = (string) file_get_contents('php://input');
if (!$params && $raw !== '') {
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $params = $json;
    } else {
        parse_str($raw, $params);
    }
}
foreach ($params as $k => $v) {
    if (is_string($v) && $v !== '' && ($v[0] === '{' || $v[0] === '[')) {
        $d = json_decode($v, true);
        if (is_array($d)) {
            $params[$k] = $d;
        }
    }
}
foreach ($_FILES as $field => $file) {
    $dest = dirname($log) . '/mock_upload_' . microtime(true) . '_' . $field . '.png';
    @copy($file['tmp_name'], $dest);
    $params[$field] = ['uploaded' => $dest, 'size' => $file['size']];
}

// Message ids
$counter = $log . '.counter';
$id = (int) @file_get_contents($counter) + 1;
file_put_contents($counter, (string) $id);

file_put_contents($log, json_encode(['t' => microtime(true), 'method' => $method, 'params' => $params], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

$chatId = $params['chat_id'] ?? 0;
$message = ['message_id' => $id, 'date' => time(), 'chat' => ['id' => is_numeric($chatId) ? (int) $chatId : $chatId, 'type' => 'private']];
if (isset($params['text'])) {
    $message['text'] = $params['text'];
}

$result = match ($method) {
    'getMe' => ['id' => $botId, 'is_bot' => true, 'first_name' => 'Test Bot', 'username' => 'test_hub_bot', 'can_join_groups' => true],
    'sendMessage', 'sendPhoto', 'sendDocument', 'copyMessage', 'sendAnimation', 'sendVideo' => $message,
    'editMessageText', 'editMessageCaption', 'editMessageReplyMarkup', 'editMessageMedia' => $message,
    'sendMediaGroup' => [$message],
    'getChat' => ['id' => -1001234567890, 'type' => 'channel', 'title' => 'Test Channel', 'username' => 'test_channel'],
    'getChatMember' => ['status' => 'administrator', 'user' => ['id' => $botId, 'is_bot' => true, 'first_name' => 'Test Bot'],
        'can_post_messages' => true, 'can_edit_messages' => true, 'can_delete_messages' => true, 'can_manage_direct_messages' => true],
    'getChatAdministrators' => [],
    'getWebhookInfo' => ['url' => '', 'has_custom_certificate' => false, 'pending_update_count' => 0],
    'getUpdates' => [],
    'getFile' => ['file_id' => 'x', 'file_unique_id' => 'x', 'file_path' => 'photos/x.jpg'],
    default => true,
};

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);

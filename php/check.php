<?php
header('Content-Type: text/plain; charset=utf-8');
$root = __DIR__;
$env = array();
if (is_file($root . '/.env')) {
    foreach (file($root . '/.env', FILE_IGNORE_NEW_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        list($k, $v) = explode('=', $line, 2);
        $env[trim($k)] = trim(trim($v), "\"'");
    }
}
$key = isset($env['SETUP_KEY']) ? $env['SETUP_KEY'] : '';
if ($key === '' || !isset($_GET['key']) || $_GET['key'] !== $key) {
    http_response_code(403);
    exit("forbidden\n");
}
$ok = function ($c) { return $c ? 'OK' : 'FAIL'; };
echo 'bot version: ' . trim((string)@file_get_contents(__DIR__ . '/src/data/version.txt') ?: 'dev') . "\n";
echo 'opcache validate_timestamps: ' . ini_get('opcache.validate_timestamps') . ' | revalidate_freq: ' . ini_get('opcache.revalidate_freq') . "\n";
echo 'php: ' . PHP_VERSION . ' ' . $ok(version_compare(PHP_VERSION, '8.0.0', '>=')) . "\n";
foreach (array('curl', 'gd', 'pdo_sqlite', 'mbstring', 'openssl', 'json', 'intl', 'Zend OPcache') as $ext) {
    echo 'ext ' . $ext . ': ' . (extension_loaded($ext) ? 'OK' : ($ext === 'intl' || $ext === 'Zend OPcache' ? 'off (optional)' : 'MISSING')) . "\n";
}
echo 'gd freetype: ' . $ok(function_exists('imagettftext')) . "\n";
echo 'sapi: ' . PHP_SAPI . ' | early reply: ' . ((function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request')) ? 'OK' : 'NO (set a cron for cron.php)') . "\n";
echo 'max_execution_time: ' . ini_get('max_execution_time') . ' | memory_limit: ' . ini_get('memory_limit') . "\n";
$data = $root . '/data';
echo 'data writable: ' . $ok(is_dir($data) ? is_writable($data) : @mkdir($data, 0755, true)) . "\n";
echo '.env token: ' . $ok(!empty($env['BOT_TOKEN'])) . "\n";
echo 'admin ids: ' . (isset($env['ADMIN_IDS']) ? $env['ADMIN_IDS'] : '-') . "\n";
$tg = function ($method, $params) use ($env) {
    $ch = curl_init('https://api.telegram.org/bot' . $env['BOT_TOKEN'] . '/' . $method);
    $opts = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params);
    $proxy = !empty($env['TELEGRAM_PROXY']) ? $env['TELEGRAM_PROXY'] : (!empty($env['PROXY']) ? $env['PROXY'] : '');
    if ($proxy !== '') {
        $opts[CURLOPT_PROXY] = $proxy;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    $d = $body ? json_decode($body, true) : null;
    return is_array($d) ? $d : array('ok' => false, 'description' => 'network: ' . $err);
};
if (!empty($env['BOT_TOKEN']) && function_exists('curl_init')) {
    $me = $tg('getMe', array());
    echo 'telegram getMe: ' . (!empty($me['ok']) ? 'OK @' . $me['result']['username'] : 'FAIL ' . (isset($me['description']) ? $me['description'] : '')) . "\n";
    $wh = $tg('getWebhookInfo', array());
    if (!empty($wh['ok'])) {
        $r = $wh['result'];
        echo 'webhook url: ' . ($r['url'] !== '' ? $r['url'] : '(not set)') . "\n";
        echo 'pending updates: ' . (isset($r['pending_update_count']) ? $r['pending_update_count'] : 0) . "\n";
        if (!empty($r['last_error_message'])) {
            echo 'last error: ' . $r['last_error_message'] . ' (' . date('Y-m-d H:i:s', $r['last_error_date']) . ")\n";
        } else {
            echo "last error: none\n";
        }
    }
    $admin = isset($env['ADMIN_IDS']) ? trim(explode(',', $env['ADMIN_IDS'])[0]) : '';
    if ($admin !== '') {
        $s = $tg('sendMessage', array('chat_id' => $admin, 'text' => 'check: <tg-emoji emoji-id="5206607081334906820">✅</tg-emoji> ok', 'parse_mode' => 'HTML'));
        echo 'send to admin (premium emoji): ' . (!empty($s['ok']) ? 'OK' : 'FAIL ' . (isset($s['description']) ? $s['description'] : '')) . "\n";
    }
}
if (is_file($data . '/last_update.txt')) {
    echo 'last update handled: ' . trim(file_get_contents($data . '/last_update.txt')) . "\n";
} else {
    echo "last update handled: none yet\n";
}
$perf = $data . '/perf.log';
if (is_file($perf)) {
    echo "\n--- timings (ms) ---\n";
    $lines = file($perf, FILE_IGNORE_NEW_LINES);
    echo implode("\n", array_slice($lines, -15)) . "\n";
}
$log = $data . '/php-error.log';
if (is_file($log)) {
    echo "\n--- last errors ---\n";
    $lines = file($log, FILE_IGNORE_NEW_LINES);
    echo implode("\n", array_slice($lines, -15)) . "\n";
}

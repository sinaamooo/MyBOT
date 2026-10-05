<?php
/**
 * Checks the server setup: php tools/check.php
 */
require dirname(__DIR__) . '/src/bootstrap.php';

// Web access (for hosts without SSH) needs ?key=<webhook_secret>
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals((string) app_config()['webhook_secret'], (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('forbidden');
    }
}

$config = app_config();
$ok = static fn (bool $c, string $label, string $hint = '') => print(($c ? '[OK]   ' : '[FAIL] ') . $label . ($c || $hint === '' ? '' : "  -> $hint") . "\n");

$ok(PHP_VERSION_ID >= 80000, 'PHP ' . PHP_VERSION, 'PHP 8.0 or newer is required');
foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite', 'json', 'simplexml'] as $ext) {
    $ok(extension_loaded($ext), "extension $ext", "enable php-$ext");
}
$ok(function_exists('imagettftext'), 'GD FreeType support', 'GD must be built with FreeType');
$ok(is_file(APP_ROOT . '/config.php'), 'config.php exists', 'copy config.example.php to config.php');
$ok(is_writable(APP_ROOT . '/storage'), 'storage/ writable', 'chmod 775 storage');
$ok(is_file(APP_ROOT . '/assets/fonts/Vazirmatn-Bold.ttf'), 'fonts');

$tg = new App\Telegram\Client((string) $config['bot_token']);
$me = $tg->call('getMe');
$ok($me !== null, 'Telegram bot token' . ($me ? ' (@' . $me['username'] . ')' : ''), 'check bot_token / server access to api.telegram.org');
if ($me) {
    $info = $tg->call('getWebhookInfo');
    echo '       webhook: ' . (($info['url'] ?? '') ?: '(none)') . (isset($info['last_error_message']) ? ' | last error: ' . $info['last_error_message'] : '') . "\n";
}

$market = new App\Market\MarketData($config['market'], APP_ROOT . '/storage/cache');
try {
    $s = $market->candles('BTC', '4h', 50);
    $ok(true, 'market data (' . $market->lastProvider . ', BTC ' . App\Support\Fa::price($s->lastClose()) . ')');
} catch (Throwable $e) {
    $ok(false, 'market data', 'no exchange API reachable from this server (' . $e->getMessage() . '); set market.proxy or use another host');
}

$key = (string) ($config['gemini']['api_key'] ?? '');
if ($key === '') {
    echo "[--]   Gemini disabled (no api_key)\n";
} else {
    $res = App\Support\Http::request('GET', 'https://generativelanguage.googleapis.com/v1beta/models?pageSize=1', ['headers' => ['x-goog-api-key: ' . $key]]);
    $ok($res['status'] === 200, 'Gemini API key', 'HTTP ' . $res['status'] . ' ' . substr($res['body'], 0, 150));
}

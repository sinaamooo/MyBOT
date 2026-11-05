<?php

require __DIR__ . '/src/bootstrap.php';

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
    $key = Config::get('SETUP_KEY');
    if ($key === '' || !hash_equals($key, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit("forbidden\n");
    }
}
if (Config::get('BOT_TOKEN') === '') {
    exit("BOT_TOKEN is empty in .env\n");
}
DB::connect();
$arg = $cli ? ($argv[1] ?? '') : (string)($_GET['url'] ?? '');
if ($arg === 'delete') {
    var_dump(TG::api('deleteWebhook', ['drop_pending_updates' => false]));
    exit;
}
if ($arg === 'info') {
    print_r(TG::api('getWebhookInfo'));
    exit;
}
$url = $arg;
if ($url === '' && !$cli) {
    $url = 'https://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/bot.php';
}
if (!preg_match('#^https://#', $url)) {
    exit("usage: php setup.php https://your-domain.com/path/bot.php\n");
}
$secret = (string)DB::kvGet('webhook_secret', '');
if ($secret === '') {
    $secret = bin2hex(random_bytes(24));
    DB::kvSet('webhook_secret', $secret);
}
$me = TG::api('getMe');
if (!$me) {
    exit('getMe failed: ' . TG::$lastError . "\n");
}
Cache::set(TG::meKey(), $me, 86400);
$ok = TG::api('setWebhook', [
    'url' => $url,
    'secret_token' => $secret,
    'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member', 'chat_member'],
    'max_connections' => max(1, min(100, (int)Config::get('WEBHOOK_MAX_CONN', '12'))),
]);
echo 'bot: @' . $me['username'] . "\n";
echo 'webhook: ' . ($ok ? 'OK' : 'FAILED ' . TG::$lastError) . " -> $url\n";
if (function_exists('opcache_reset')) {
    @opcache_reset();
}
echo 'commands: ' . (Start::registerCommands() ? 'OK' : 'FAILED') . "\n";
$e = UI::validateEmoji();
echo 'premium emoji: ' . ($e ? $e[0] . ' ok, ' . $e[1] . ' invalid, ' . $e[2] . ' matched by pack emoji' : 'check failed') . "\n";
$opc = function_exists('opcache_get_status') ? @opcache_get_status(false) : false;
echo 'opcache: ' . (!empty($opc['opcache_enabled']) ? 'ON' : 'OFF') . "\n";

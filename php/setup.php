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
Cache::set('bot_me', $me, 86400);
$ok = TG::api('setWebhook', [
    'url' => $url,
    'secret_token' => $secret,
    'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member', 'chat_member'],
    'max_connections' => 40,
]);
echo 'bot: @' . $me['username'] . "\n";
echo 'webhook: ' . ($ok ? 'OK' : 'FAILED ' . TG::$lastError) . " -> $url\n";
$private = [
    ['command' => 'start', 'description' => 'شروع'], ['command' => 'help', 'description' => 'راهنما'], ['command' => 'p', 'description' => 'قیمت ارزها'],
    ['command' => 'gift', 'description' => 'گیفت‌های من / @username'], ['command' => 'account', 'description' => 'اطلاعات اکانت'],
    ['command' => 'emoji', 'description' => 'کد ایموجی پریمیوم'], ['command' => 'scam', 'description' => 'گزارش کلاهبردار'],
    ['command' => 'check', 'description' => 'استعلام کلاهبردار'], ['command' => 'wallet', 'description' => 'چکر ولت / هش'],
    ['command' => 'arz', 'description' => 'نرخ ارز بازار آزاد'], ['command' => 'card', 'description' => 'استعلام کارت / شبا'],
    ['command' => 'phone', 'description' => 'چکر شماره تلگرام'], ['command' => 'insta', 'description' => 'اینستاگرام'],
];
$group = [
    ['command' => 'p', 'description' => 'قیمت ارزها'], ['command' => 'gift', 'description' => 'گیفت‌های کاربر'], ['command' => 'account', 'description' => 'اطلاعات اکانت'],
    ['command' => 'emoji', 'description' => 'کد ایموجی پریمیوم'], ['command' => 'scam', 'description' => 'گزارش کلاهبردار'], ['command' => 'check', 'description' => 'استعلام کلاهبردار'],
    ['command' => 'arz', 'description' => 'نرخ ارز بازار آزاد'], ['command' => 'settings', 'description' => 'تنظیمات گروه (ادمین)'], ['command' => 'help', 'description' => 'راهنما'],
];
echo 'commands: ' . (TG::api('setMyCommands', ['commands' => $private, 'scope' => ['type' => 'all_private_chats']]) && TG::api('setMyCommands', ['commands' => $group, 'scope' => ['type' => 'all_group_chats']]) ? 'OK' : 'FAILED') . "\n";

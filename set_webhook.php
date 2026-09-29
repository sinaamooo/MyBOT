<?php
/**
 * Connects the bot to Telegram in webhook mode and registers its commands.
 *
 * Browser: open https://your-domain/.../set_webhook.php (uses webhook_url from config.php)
 * CLI:     php set_webhook.php [https://your-domain/.../bot.php]
 */
declare(strict_types=1);

require __DIR__ . '/lib.php';

header('Content-Type: text/plain; charset=utf-8');

// The URL comes only from config.php (or the CLI), never from the request,
// so visiting this page cannot redirect the bot somewhere else.
$url = (PHP_SAPI === 'cli' && isset($argv[1])) ? $argv[1] : (string) config()['webhook_url'];
if (strpos($url, 'https://') !== 0 || strpos($url, 'example.com') !== false) {
    echo "❌ Set webhook_url in config.php to the public https:// address of bot.php first.\n";
    exit(1);
}

$me = tg('getMe');
if (!$me['ok']) {
    echo '❌ Invalid token or Telegram is unreachable: ' . ($me['description'] ?? '') . "\n";
    exit(1);
}

$res = tg('setWebhook', [
    'url'                  => $url,
    'secret_token'         => webhookSecret(),
    'allowed_updates'      => ['message', 'callback_query'],
    'drop_pending_updates' => true,
]);
tg('setMyCommands', ['commands' => [
    ['command' => 'start', 'description' => 'ساخت پک ایموجی جدید'],
    ['command' => 'done', 'description' => 'ساخت پک با ایموجی‌های فرستاده شده'],
    ['command' => 'cancel', 'description' => 'لغو'],
    ['command' => 'help', 'description' => 'راهنما'],
]]);

if ($res['ok']) {
    echo '✅ Webhook set for @' . $me['result']['username'] . ' → ' . $url . "\n";
} else {
    echo '❌ setWebhook failed: ' . ($res['description'] ?? '') . "\n";
    exit(1);
}

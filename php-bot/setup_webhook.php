<?php

declare(strict_types=1);

// One-off setup script - run manually via SSH after filling in .env:
//   php setup_webhook.php set      (default)
//   php setup_webhook.php delete
//   php setup_webhook.php info

require_once __DIR__ . '/bootstrap.php';

use App\Config;
use App\Telegram\TelegramApi;

$telegram = new TelegramApi();
$action = $argv[1] ?? 'set';

if ($action === 'delete') {
    $ok = $telegram->deleteWebhook();
    echo $ok ? "Webhook deleted.\n" : "Failed to delete webhook.\n";
    exit($ok ? 0 : 1);
}

if ($action === 'info') {
    print_r($telegram->getWebhookInfo());
    exit(0);
}

$url = Config::required('TELEGRAM_WEBHOOK_URL');
$secret = Config::required('TELEGRAM_WEBHOOK_SECRET');

$ok = $telegram->setWebhook($url, $secret);
echo $ok ? "Webhook set to: {$url}\n" : "Failed to set webhook.\n";

echo "\nWebhook info:\n";
print_r($telegram->getWebhookInfo());

$me = $telegram->getMe();
echo "\nBot identity:\n";
print_r($me);

exit($ok ? 0 : 1);

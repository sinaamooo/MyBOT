<?php
/**
 * Long-polling runner (for a VPS or local testing, instead of the webhook):
 *   php poll.php                   run until stopped (Ctrl+C)
 *   php poll.php --delete-webhook  remove an existing webhook first (polling does not work while one is set)
 */
require __DIR__ . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
$config = app_config();
$tg = new App\Telegram\Client((string) $config['bot_token']);
if (in_array('--delete-webhook', $argv, true)) {
    $tg->call('deleteWebhook');
    echo "Webhook removed.\n";
}
$me = $tg->call('getMe');
if ($me === null) {
    exit("Bot token is invalid or Telegram is unreachable.\n");
}
echo 'Running as @' . $me['username'] . "\n";

$bot = new App\Bot($config, $tg);
$offset = 0;
while (true) {
    $updates = $tg->call('getUpdates', ['offset' => $offset, 'timeout' => 50, 'allowed_updates' => ['message']]);
    if ($updates === null) {
        sleep(3);
        continue;
    }
    foreach ($updates as $u) {
        $offset = $u['update_id'] + 1;
        try {
            $bot->handle($u);
        } catch (Throwable $e) {
            app_log('poll error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            echo 'Error: ', $e->getMessage(), "\n";
        }
    }
}

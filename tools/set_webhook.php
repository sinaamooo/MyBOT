<?php
/**
 * Registers webhook.php with Telegram:  php tools/set_webhook.php [https://your.domain/path/webhook.php]
 * Without an argument the 'webhook_url' from config.php is used.
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
$url = $argv[1] ?? ($_GET['url'] ?? $config['webhook_url']);
echo App\Entry::setWebhook($config, $url), "\n";

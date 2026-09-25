<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Log;
use Nikto\Jobs\Registry;
use Nikto\Telegram\Api;
use Nikto\Telegram\Panel;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("این فایل باید از خط فرمان اجرا شود.\n");
}

if (!Config::isConfigured()) {
    fwrite(STDERR, "❌ توکن ربات تنظیم نشده است.\n   فایل config.php را از روی config.sample.php بسازید.\n");
    exit(1);
}

Db::migrate();
Registry::all();

$api = new Api();
$me = $api->getMe();
if (!($me['ok'] ?? false)) {
    fwrite(STDERR, '❌ اتصال به تلگرام ناموفق بود: ' . ($me['description'] ?? 'نامشخص') . "\n");
    exit(1);
}

$username = (string) ($me['result']['username'] ?? '');
$botId = (int) ($me['result']['id'] ?? 0);
Config::set('bot_id', $botId);

$api->deleteWebhook();

echo "✅ ربات @{$username} روشن شد (id: {$botId})\n";
echo "   برای توقف Ctrl+C را بزنید.\n";
Log::info('Bot started', ['username' => $username, 'id' => $botId]);

$panel = new Panel($api);
$offset = 0;
$running = true;

if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $stop = static function () use (&$running): void {
        echo "\n⏹ در حال توقف…\n";
        $running = false;
    };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

while ($running) {
    try {
        $updates = $api->getUpdates($offset, 30);
        foreach ($updates as $update) {
            $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
            try {
                $panel->handleUpdate($update);
            } catch (\Throwable $e) {
                Log::error('Update handling failed', [
                    'error' => $e->getMessage(),
                    'file'  => basename($e->getFile()) . ':' . $e->getLine(),
                ]);
            }
        }
    } catch (\Throwable $e) {
        Log::error('Polling loop error', ['error' => $e->getMessage()]);
        sleep(3);
    }
}

Log::info('Bot stopped');
echo "به امید دیدار 👋\n";

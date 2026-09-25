<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Log;
use Nikto\Core\Settings;
use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("این فایل باید از خط فرمان اجرا شود.\n");
}

if (!Config::isConfigured()) {
    fwrite(STDERR, "❌ توکن ربات تنظیم نشده است (config.php).\n");
    exit(1);
}

Db::migrate();
Registry::all();

$once = in_array('--once', $argv, true);
$running = true;

if (function_exists('pcntl_signal')) {
    pcntl_async_signals(true);
    $stop = static function () use (&$running): void {
        $running = false;
    };
    pcntl_signal(SIGTERM, $stop);
    pcntl_signal(SIGINT, $stop);
}

$tick = static function (): void {
    Settings::set('scheduler_heartbeat', (string) time());
    try {
        $results = Scheduler::tick();
        foreach ($results as $item) {
            printf(
                "[%s] %s → %s\n",
                date('H:i:s'),
                $item['job'],
                $item['result']['message'] ?? '—'
            );
        }
    } catch (\Throwable $e) {
        Log::error('Scheduler tick failed', ['error' => $e->getMessage()]);
    }
};

if ($once) {
    $tick();
    exit(0);
}

echo "⏰ زمان‌بند روشن شد — منطقه‌ی زمانی: " . Settings::get('timezone') . "\n";
Log::info('Scheduler started');

while ($running) {
    $tick();
    $sleep = 60 - (int) date('s');
    for ($i = 0; $i < $sleep && $running; $i++) {
        sleep(1);
    }
}

Log::info('Scheduler stopped');
echo "زمان‌بند متوقف شد.\n";

<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

/**
 * Loads config.php (falls back to config.example.php so tools still run on a fresh checkout).
 */
function app_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $file = APP_ROOT . '/config.php';
    if (!is_file($file)) {
        $file = APP_ROOT . '/config.example.php';
    }
    $config = require $file;
    date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
    \App\Support\Http::$proxy = (string) ($config['proxy'] ?? '');
    return $config;
}

function app_log(string $message): void
{
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/bot-' . date('Y-m-d') . '.log', '[' . date('H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

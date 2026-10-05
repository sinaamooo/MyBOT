<?php

declare(strict_types=1);

define('HUB_ROOT', dirname(__DIR__));

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
    $file = HUB_ROOT . '/config.php';
    if (!is_file($file)) {
        $file = HUB_ROOT . '/config.example.php';
    }
    $config = require $file;
    date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
    \App\Support\Http::$proxy = (string) ($config['proxy'] ?? '');
    // Telegram Bot API address (only changed for testing with a local mock server)
    if (!defined('TG_API_BASE') && !empty($config['telegram_api_base'])) {
        define('TG_API_BASE', rtrim((string) $config['telegram_api_base'], '/'));
    }
    return $config;
}

/**
 * Writable data directory (config 'storage_dir', default ./storage), protected from web access.
 * With $sub a subdirectory is returned (and created).
 */
function app_storage(string $sub = ''): string
{
    static $ready = [];
    $dir = rtrim((string) (app_config()['storage_dir'] ?? ''), '/') ?: HUB_ROOT . '/storage';
    if (!isset($ready[$dir])) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
        if (!is_file($dir . '/index.html')) {
            @file_put_contents($dir . '/index.html', '');
        }
        $ready[$dir] = true;
    }
    if ($sub === '') {
        return $dir;
    }
    if (!is_dir($dir . '/' . $sub)) {
        @mkdir($dir . '/' . $sub, 0775, true);
    }
    return $dir . '/' . $sub;
}

/** Analysis weights: brain/strategy.php, or the copy embedded in the single-file build. */
function app_brain(): array
{
    $file = HUB_ROOT . '/brain/strategy.php';
    if (is_file($file)) {
        return require $file;
    }
    return function_exists('app_brain_default') ? app_brain_default() : [];
}

/** Path of a Vazirmatn font weight; downloaded into storage on first use if not bundled. */
function app_font(string $weight): string
{
    $local = HUB_ROOT . '/assets/fonts/Vazirmatn-' . $weight . '.ttf';
    if (is_file($local)) {
        return $local;
    }
    $path = app_storage('fonts') . '/Vazirmatn-' . $weight . '.ttf';
    if (!is_file($path) || filesize($path) < 50000) {
        $sources = [
            'https://raw.githubusercontent.com/rastikerdar/vazirmatn/master/fonts/ttf/',
            'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@master/fonts/ttf/',
        ];
        foreach ($sources as $base) {
            $res = \App\Support\Http::request('GET', $base . 'Vazirmatn-' . $weight . '.ttf', ['timeout' => 30]);
            if ($res['status'] === 200 && strlen($res['body']) > 50000) {
                file_put_contents($path, $res['body']);
                break;
            }
        }
    }
    return $path;
}

function app_log(string $message): void
{
    $dir = app_storage('logs');
    @file_put_contents($dir . '/bot-' . date('Y-m-d') . '.log', '[' . date('H:i:s') . '] ' . $message . "\n", FILE_APPEND);
}

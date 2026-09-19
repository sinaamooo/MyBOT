<?php
/**
 * NIKTO CRYPTO BOT — bootstrap
 * بارگذارنده‌ی خودکار کلاس‌ها و راه‌اندازی اولیه
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_SRC', APP_ROOT . '/src');
define('APP_DATA', APP_ROOT . '/data');
define('APP_STORAGE', APP_ROOT . '/storage');
define('APP_ASSETS', APP_ROOT . '/assets');
define('APP_FIXTURES', APP_ROOT . '/fixtures');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Nikto\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_SRC . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

foreach ([APP_DATA, APP_STORAGE, APP_STORAGE . '/cards', APP_STORAGE . '/logs'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

mb_internal_encoding('UTF-8');

require_once APP_SRC . '/Core/Config.php';
\Nikto\Core\Config::boot();

date_default_timezone_set('UTC');

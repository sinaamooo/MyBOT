<?php

declare(strict_types=1);

date_default_timezone_set('UTC');

// Minimal PSR-4-ish autoloader: App\Foo\Bar -> src/Foo/Bar.php. No Composer
// required, keeping the whole project dependency-free for easy shared
// hosting deploys.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require_once __DIR__ . '/src/Config.php';

use App\Config;

Config::load(__DIR__ . '/.env');

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak errors into webhook/HTTP responses
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/php-error.log');

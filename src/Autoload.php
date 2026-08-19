<?php

declare(strict_types=1);

/**
 * Minimal PSR-4 autoloader for the MyBot\ namespace.
 * No Composer required.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'MyBot\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

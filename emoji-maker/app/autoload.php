<?php

declare(strict_types=1);

/*
 * Autoloader. Uses Composer's autoloader when "composer install" has been run,
 * otherwise registers a small PSR-4 loader so the project also works on shared
 * hosting where Composer is not available.
 */

$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
    require_once $composer;
}

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'EmojiMaker\\Tests\\' => dirname(__DIR__) . '/tests/',
        'EmojiMaker\\' => __DIR__ . '/',
    ];
    foreach ($prefixes as $prefix => $dir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $dir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
        return;
    }
});

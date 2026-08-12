<?php

declare(strict_types=1);

namespace App;

final class Logger
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public static function write(string $channel, string $level, string $message): void
    {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir . '/' . $channel . '.log';
        self::rotateIfNeeded($file);

        $line = sprintf('%s | %-8s | %s', gmdate('Y-m-d H:i:s'), strtoupper($level), $message) . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function bot(string $level, string $message): void
    {
        self::write('bot', $level, $message);
    }

    public static function signals(string $level, string $message): void
    {
        self::write('signals', $level, $message);
    }

    private static function rotateIfNeeded(string $file): void
    {
        if (is_file($file) && filesize($file) > self::MAX_BYTES) {
            @rename($file, $file . '.' . date('YmdHis') . '.bak');
        }
    }
}

<?php

declare(strict_types=1);

namespace MyBot\Support;

final class Logger
{
    private const LEVELS = ['debug' => 10, 'info' => 20, 'warn' => 30, 'error' => 40];

    private int $threshold;

    public function __construct(
        private readonly ?string $file = null,
        string $level = 'info',
    ) {
        $this->threshold = self::LEVELS[$level] ?? self::LEVELS['info'];

        if ($this->file !== null) {
            $dir = \dirname($this->file);
            if (!is_dir($dir)) {
                @mkdir($dir, 0o775, true);
            }
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warn(string $message, array $context = []): void
    {
        $this->log('warn', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? self::LEVELS['info']) < $this->threshold) {
            return;
        }

        $suffix = '';
        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $suffix = ' ' . ($encoded === false ? '[unencodable context]' : $encoded);
        }

        $line = sprintf(
            '[%s] %-5s %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            $suffix,
        );

        fwrite(\STDOUT, $line . \PHP_EOL);

        if ($this->file !== null) {
            @file_put_contents($this->file, $line . \PHP_EOL, \FILE_APPEND | \LOCK_EX);
        }
    }
}

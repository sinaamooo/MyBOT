<?php

declare(strict_types=1);

namespace EmojiMaker\Support;

use EmojiMaker\Security\Redactor;

/**
 * Daily rotating file logger. All messages and context are passed through the
 * Redactor so tokens and passwords never reach the log files.
 */
final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4, 'critical' => 5];

    public function __construct(
        private string $dir,
        private string $channel = 'app',
        private string $minLevel = 'info',
    ) {
    }

    public function withChannel(string $channel): self
    {
        return new self($this->dir, $channel, $this->minLevel);
    }

    /** @param array<string,mixed> $context */
    public function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 1) < (self::LEVELS[$this->minLevel] ?? 1)) {
            return;
        }
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
        $line = sprintf(
            "[%s] %s.%s: %s%s\n",
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            Redactor::text($message),
            $context ? ' ' . json_encode(Redactor::value($context), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) : ''
        );
        $file = $this->dir . '/' . $this->channel . '-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        if (PHP_SAPI === 'cli' && getenv('EMOJI_LOG_STDERR') === '1') {
            fwrite(STDERR, $line);
        }
    }

    /** @param array<string,mixed> $c */
    public function debug(string $m, array $c = []): void { $this->log('debug', $m, $c); }
    /** @param array<string,mixed> $c */
    public function info(string $m, array $c = []): void { $this->log('info', $m, $c); }
    /** @param array<string,mixed> $c */
    public function notice(string $m, array $c = []): void { $this->log('notice', $m, $c); }
    /** @param array<string,mixed> $c */
    public function warning(string $m, array $c = []): void { $this->log('warning', $m, $c); }
    /** @param array<string,mixed> $c */
    public function error(string $m, array $c = []): void { $this->log('error', $m, $c); }
    /** @param array<string,mixed> $c */
    public function critical(string $m, array $c = []): void { $this->log('critical', $m, $c); }

    /** @param array<string,mixed> $context */
    public function exception(\Throwable $e, string $message = 'Unhandled exception', array $context = []): void
    {
        $context['exception'] = get_class($e);
        $context['error'] = $e->getMessage();
        $context['at'] = basename($e->getFile()) . ':' . $e->getLine();
        $trace = [];
        foreach (array_slice($e->getTrace(), 0, 8) as $f) {
            $trace[] = (isset($f['class']) ? $f['class'] . '::' : '') . ($f['function'] ?? '?')
                . (isset($f['line']) ? ':' . $f['line'] : '');
        }
        $context['trace'] = $trace;
        $this->error($message, $context);
    }

    public function directory(): string
    {
        return $this->dir;
    }
}

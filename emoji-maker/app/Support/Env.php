<?php

declare(strict_types=1);

namespace EmojiMaker\Support;

/**
 * Minimal .env loader (no external dependency).
 * Supports KEY=value, quoted values, "export KEY=value" and # comments.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                continue;
            }
            $value = trim(substr($line, $pos + 1));
            self::$values[$key] = self::parseValue($value);
        }
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $quote = $value[0];
        if ($quote === '"' || $quote === "'") {
            $end = strrpos($value, $quote);
            if ($end !== false && $end > 0) {
                $inner = substr($value, 1, $end - 1);
                if ($quote === '"') {
                    $inner = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $inner);
                }
                return $inner;
            }
        }
        // strip inline comment " # ..."
        $hash = strpos($value, ' #');
        if ($hash !== false) {
            $value = rtrim(substr($value, 0, $hash));
        }
        return $value;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }
        $env = getenv($key);
        if ($env !== false) {
            return $env;
        }
        return $_SERVER[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return ($v === null || $v === '' || !is_numeric($v)) ? $default : (int) $v;
    }

    public static function set(string $key, string $value): void
    {
        self::$values[$key] = $value;
    }

    /**
     * Writes/updates keys in a .env file while keeping the rest of the file intact.
     * @param array<string,string> $pairs
     */
    public static function writeKeys(string $file, array $pairs): void
    {
        $lines = is_file($file) ? (file($file, FILE_IGNORE_NEW_LINES) ?: []) : [];
        foreach ($pairs as $key => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new \InvalidArgumentException('Invalid env key');
            }
            $escaped = self::quote($value);
            $found = false;
            foreach ($lines as $i => $line) {
                if (preg_match('/^\s*(export\s+)?' . preg_quote($key, '/') . '\s*=/', $line)) {
                    $lines[$i] = $key . '=' . $escaped;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $lines[] = $key . '=' . $escaped;
            }
            self::$values[$key] = $value;
        }
        $tmp = $file . '.tmp' . bin2hex(random_bytes(4));
        file_put_contents($tmp, implode("\n", $lines) . "\n", LOCK_EX);
        @chmod($tmp, 0600);
        rename($tmp, $file);
    }

    private static function quote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_:\/\.\-+=,@]+$/', $value)) {
            return $value;
        }
        return '"' . str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value) . '"';
    }
}

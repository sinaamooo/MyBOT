<?php

declare(strict_types=1);

namespace EmojiMaker\Security;

/**
 * Removes secrets (bot tokens, passwords, keys) from anything that is logged
 * or shown in the admin panel.
 */
final class Redactor
{
    /** @var list<string> */
    private static array $secrets = [];

    private const SENSITIVE_KEYS = [
        'password', 'pass', 'passwd', 'token', 'secret', 'api_key', 'app_key',
        'authorization', 'cookie', 'csrf', 'db_pass', 'bot_token', 'secret_token',
    ];

    public static function registerSecret(?string $secret): void
    {
        if ($secret !== null && strlen($secret) >= 6 && !in_array($secret, self::$secrets, true)) {
            self::$secrets[] = $secret;
        }
    }

    public static function text(string $text): string
    {
        foreach (self::$secrets as $secret) {
            $text = str_replace($secret, '[REDACTED]', $text);
        }
        // Telegram bot tokens: 123456789:AA...
        $text = (string) preg_replace('/\b\d{5,12}:[A-Za-z0-9_-]{30,}\b/', '[BOT_TOKEN]', $text);
        // URLs that embed the token (api.telegram.org/bot<token>/ or /file/bot<token>/)
        $text = (string) preg_replace('#/bot[^/\s]+/#', '/bot[REDACTED]/', $text);
        // password=... in DSN / query strings
        $text = (string) preg_replace('/(password|passwd|pass|secret|token)=([^&\s;]+)/i', '$1=[REDACTED]', $text);
        return $text;
    }

    public static function value(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[depth]';
        }
        if (is_string($value)) {
            return self::text($value);
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                if (is_string($k) && self::isSensitiveKey($k)) {
                    $out[$k] = '[REDACTED]';
                    continue;
                }
                $out[$k] = self::value($v, $depth + 1);
            }
            return $out;
        }
        if ($value instanceof \Throwable) {
            return self::text(get_class($value) . ': ' . $value->getMessage());
        }
        if (is_object($value)) {
            return '[' . get_class($value) . ']';
        }
        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $k = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $s) {
            if ($k === $s || str_ends_with($k, '_' . $s) || str_starts_with($k, $s . '_')) {
                return true;
            }
        }
        return false;
    }
}

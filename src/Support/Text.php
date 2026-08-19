<?php

declare(strict_types=1);

namespace MyBot\Support;

final class Text
{
    /** Escapes text for parse_mode=HTML. */
    public static function esc(?string $value): string
    {
        return htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }

    /** 12345 => "۱۲٬۳۴۵" */
    public static function num(int|float $value): string
    {
        $formatted = number_format((float) $value, 0, '.', '٬');

        return strtr($formatted, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    public static function percent(int $part, int $whole): string
    {
        if ($whole <= 0) {
            return '۰٪';
        }

        return self::num((int) round($part / $whole * 100)) . '٪';
    }

    /** A tiny text progress bar: ▰▰▰▱▱▱▱▱▱▱ */
    public static function bar(int $part, int $whole, int $width = 10): string
    {
        if ($whole <= 0) {
            return str_repeat('▱', $width);
        }

        $filled = (int) round(min(1.0, $part / $whole) * $width);

        return str_repeat('▰', $filled) . str_repeat('▱', $width - $filled);
    }

    public static function shorten(?string $value, int $limit = 40): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '—';
        }

        $value = str_replace(["\n", "\r"], ' ', $value);

        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 1, 'UTF-8') . '…';
    }

    public static function datetime(?int $timestamp): string
    {
        if ($timestamp === null || $timestamp <= 0) {
            return '—';
        }

        return date('Y-m-d H:i', $timestamp);
    }

    /** "۲ ساعت و ۵ دقیقه" style duration for stats screens. */
    public static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return self::num($seconds) . ' ثانیه';
        }
        if ($seconds < 3600) {
            return self::num(intdiv($seconds, 60)) . ' دقیقه';
        }
        if ($seconds < 86400) {
            return self::num(intdiv($seconds, 3600)) . ' ساعت';
        }

        return self::num(intdiv($seconds, 86400)) . ' روز';
    }

    /**
     * Decodes a JSON column into an array, tolerating null/invalid values.
     *
     * @return array<mixed>|null
     */
    public static function json(mixed $raw): ?array
    {
        if (!\is_string($raw) || trim($raw) === '') {
            return null;
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true);

        return \is_array($decoded) && $decoded !== [] ? $decoded : null;
    }

    /** Full display name of a Telegram user payload. */
    public static function fullName(array $user): string
    {
        $name = trim(
            (\is_string($user['first_name'] ?? null) ? $user['first_name'] : '')
            . ' '
            . (\is_string($user['last_name'] ?? null) ? $user['last_name'] : ''),
        );

        if ($name !== '') {
            return $name;
        }

        if (\is_string($user['username'] ?? null) && $user['username'] !== '') {
            return '@' . $user['username'];
        }

        return 'کاربر ' . (string) ($user['id'] ?? '?');
    }
}

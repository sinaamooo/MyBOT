<?php

declare(strict_types=1);

namespace MyBot\Support;

use RuntimeException;

final class Config
{
    public function __construct(private readonly array $data)
    {
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException(
                "فایل پیکربندی پیدا نشد: {$path}\n" .
                'ابتدا config.php.example را به config.php کپی و مقداردهی کنید.',
            );
        }

        /** @var mixed $data */
        $data = require $path;

        if (!\is_array($data)) {
            throw new RuntimeException("فایل پیکربندی باید یک آرایه برگرداند: {$path}");
        }

        return new self($data);
    }

    /**
     * Dot-notation lookup: $config->get('limits.global_per_second', 25)
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->data;

        foreach (explode('.', $key) as $segment) {
            if (!\is_array($value) || !\array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return \is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return \is_bool($value) ? $value : (bool) $value;
    }

    /** @return list<int> */
    public function ints(string $key): array
    {
        $value = $this->get($key, []);
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_map(intval(...), array_filter($value, is_numeric(...))));
    }

    public function require(string $key): string
    {
        $value = $this->string($key);
        if ($value === '') {
            throw new RuntimeException("مقدار پیکربندی «{$key}» تنظیم نشده است.");
        }

        return $value;
    }
}

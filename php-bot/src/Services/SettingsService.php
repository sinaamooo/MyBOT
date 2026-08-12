<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;

/**
 * Runtime-configurable settings. Config::tradingDefaults() is the schema +
 * fallback; overrides live in the `settings` table so the Admin Panel can
 * change any knob without a redeploy. Cached for the lifetime of one PHP
 * request/cron run (there is no long-lived process to cache across).
 */
final class SettingsService
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /** @return array<string, mixed> */
    public static function getAll(bool $forceReload = false): array
    {
        if (self::$cache === null || $forceReload) {
            self::$cache = self::load();
        }
        return self::$cache;
    }

    /** @return array<string, mixed> */
    private static function load(): array
    {
        $merged = Config::tradingDefaults();
        $stmt = Database::connection()->query('SELECT `key`, value FROM settings');
        foreach ($stmt->fetchAll() as $row) {
            $key = $row['key'];
            if (array_key_exists($key, $merged)) {
                $merged[$key] = self::coerce($row['value'], $merged[$key]);
            }
        }
        return $merged;
    }

    private static function coerce(string $raw, mixed $default): mixed
    {
        if (is_bool($default)) {
            return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
        }
        if (is_int($default)) {
            return (int) round((float) $raw);
        }
        if (is_float($default)) {
            return (float) $raw;
        }
        return $raw;
    }

    public static function get(string $key): mixed
    {
        return self::getAll()[$key] ?? null;
    }

    public static function set(string $key, mixed $value): void
    {
        $defaults = Config::tradingDefaults();
        if (!array_key_exists($key, $defaults)) {
            throw new \InvalidArgumentException("Unknown setting: {$key}");
        }
        $strValue = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM settings WHERE `key` = :key');
        $stmt->execute(['key' => $key]);
        if ($stmt->fetch()) {
            $upd = $pdo->prepare('UPDATE settings SET value = :value, updated_at = :now WHERE `key` = :key');
            $upd->execute(['value' => $strValue, 'now' => Database::now(), 'key' => $key]);
        } else {
            $ins = $pdo->prepare('INSERT INTO settings (`key`, value, updated_at) VALUES (:key, :value, :now)');
            $ins->execute(['key' => $key, 'value' => $strValue, 'now' => Database::now()]);
        }
        self::getAll(true);
    }

    public static function valueType(string $key): string
    {
        return gettype(Config::tradingDefaults()[$key] ?? null);
    }

    /** @return string[] */
    public static function allKeys(): array
    {
        return array_keys(Config::tradingDefaults());
    }
}

<?php

declare(strict_types=1);

namespace EmojiMaker\Support;

use EmojiMaker\Database\Database;
use EmojiMaker\Security\Crypto;

/**
 * Runtime settings stored in bot_settings (editable from the admin panel)
 * with defaults from config/app.php. Secret values are encrypted with APP_KEY.
 */
final class Settings
{
    /** @var array<string,array{value:?string,type:string}>|null */
    private ?array $rows = null;

    /** @param array<string,mixed> $defaults */
    public function __construct(private Database $db, private array $defaults, private ?Crypto $crypto = null)
    {
    }

    private function load(): array
    {
        if ($this->rows === null) {
            $this->rows = [];
            try {
                foreach ($this->db->fetchAll('SELECT `key`, `value`, `type` FROM bot_settings') as $r) {
                    $this->rows[$r['key']] = ['value' => $r['value'], 'type' => $r['type']];
                }
            } catch (\PDOException) {
                // database not installed yet -> defaults only
            }
        }
        return $this->rows;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $rows = $this->load();
        if (isset($rows[$key])) {
            $r = $rows[$key];
            if ($r['type'] === 'secret') {
                return $r['value'] !== null && $this->crypto ? $this->crypto->decrypt($r['value']) : null;
            }
            return self::cast($r['value'], $r['type']);
        }
        return $this->defaults[$key] ?? $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->get($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $v = $this->get($key, $default);
        return is_numeric($v) ? (float) $v : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $v = $this->get($key, $default);
        return is_bool($v) ? $v : in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    public function string(string $key, string $default = ''): string
    {
        $v = $this->get($key, $default);
        return is_scalar($v) ? (string) $v : $default;
    }

    public function set(string $key, mixed $value, ?string $type = null, ?string $description = null): void
    {
        if (!preg_match('/^[a-z0-9_.]{2,64}$/', $key)) {
            throw new \InvalidArgumentException('Invalid setting key');
        }
        $type ??= match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
        $stored = match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            'secret' => $this->crypto ? $this->crypto->encrypt((string) $value) : throw new \RuntimeException('Encryption unavailable'),
            default => $value === null ? null : (string) $value,
        };
        $this->db->query(
            'INSERT INTO bot_settings (`key`, `value`, `type`, `description`) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `type` = VALUES(`type`), `description` = COALESCE(VALUES(`description`), `description`)',
            [$key, $stored, $type, $description]
        );
        $this->rows = null;
    }

    public function delete(string $key): void
    {
        $this->db->query('DELETE FROM bot_settings WHERE `key` = ?', [$key]);
        $this->rows = null;
    }

    public function has(string $key): bool
    {
        return isset($this->load()[$key]);
    }

    /** @return array<string,mixed> effective non-secret settings */
    public function all(): array
    {
        $out = $this->defaults;
        foreach ($this->load() as $k => $r) {
            $out[$k] = $r['type'] === 'secret' ? '••••••' : self::cast($r['value'], $r['type']);
        }
        return $out;
    }

    public function refresh(): void
    {
        $this->rows = null;
    }

    private static function cast(?string $v, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $v,
            'float' => (float) $v,
            'bool' => $v === '1' || $v === 'true',
            'json' => json_decode((string) $v, true),
            default => $v,
        };
    }
}

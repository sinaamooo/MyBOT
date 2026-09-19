<?php
declare(strict_types=1);

namespace Nikto\Core;

/**
 * پیکربندی ثابت برنامه (فایل config.php یا متغیرهای محیطی).
 * مقادیر پویا (کانال‌ها، زمان‌بندی‌ها) در دیتابیس نگهداری می‌شوند، نه اینجا.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $data = [];
    private static bool $booted = false;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        $defaults = [
            'bot_token'      => '',
            'bot_id'         => 0,
            'owner_id'       => 0,
            'admins'         => [],
            'db_path'        => APP_DATA . '/bot.sqlite',
            'timezone'       => 'Asia/Tehran',
            'brand'          => 'NIKTO CRYPTO',
            'http_timeout'   => 25,
            'http_proxy'     => '',
            'api_keys'       => [],
            'log_level'      => 'info',
            'webhook_secret' => '',
        ];

        $file = APP_ROOT . '/config.php';
        $fromFile = is_file($file) ? (require $file) : [];
        if (!is_array($fromFile)) {
            $fromFile = [];
        }

        $fromEnv = array_filter([
            'bot_token' => getenv('BOT_TOKEN') ?: null,
            'bot_id'    => getenv('BOT_ID') ? (int) getenv('BOT_ID') : null,
            'owner_id'  => getenv('OWNER_ID') ? (int) getenv('OWNER_ID') : null,
            'timezone'  => getenv('BOT_TIMEZONE') ?: null,
            'brand'     => getenv('BOT_BRAND') ?: null,
        ], static fn ($v) => $v !== null);

        self::$data = array_replace($defaults, $fromFile, $fromEnv);

        $admins = self::$data['admins'];
        if (!is_array($admins)) {
            $admins = array_filter(array_map('intval', preg_split('/[,\s]+/', (string) $admins)));
        }
        if (!empty(self::$data['owner_id'])) {
            $admins[] = (int) self::$data['owner_id'];
        }
        self::$data['admins'] = array_values(array_unique(array_filter(array_map('intval', $admins))));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::boot();
        return self::$data[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::boot();
        self::$data[$key] = $value;
    }

    public static function token(): string
    {
        return (string) self::get('bot_token', '');
    }

    /** @return int[] */
    public static function admins(): array
    {
        return (array) self::get('admins', []);
    }

    public static function isConfigured(): bool
    {
        return self::token() !== '';
    }
}

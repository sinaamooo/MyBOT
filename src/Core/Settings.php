<?php
declare(strict_types=1);

namespace Nikto\Core;

/**
 * تنظیمات پویا (قابل تغییر از پنل ربات).
 */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public const DEFAULTS = [
        'timezone'          => 'Asia/Tehran',
        'brand'             => 'NIKTO CRYPTO',   // فقط نام نمایشی در پنل
        'brand_link'        => '',               // اگر پر شود، زیر کارت‌ها نوشته می‌شود
        'digits'            => 'fa',      // ارقام متن و تاریخ‌ها: fa | en
        'digits_data'       => 'en',      // ارقام داده‌ها (قیمت، درصد): fa | en
        'catchup_minutes'   => '10',      // اگر زمانبند دیر اجرا شد، تا چند دقیقه جبران کند
        'quality'           => 'high',    // high | normal  (سوپرسمپلینگ رندر)
        'send_mode'         => 'photo',   // photo | document
        'calendar_source'   => 'auto',    // auto | forexfactory | tradingview
        'price_source'      => 'auto',    // auto | binance | coingecko
        'calendar_min_impact' => '2',     // 1=کم 2=متوسط 3=زیاد
        'calendar_currencies' => 'USD,EUR,GBP,JPY,CAD,AUD,NZD,CHF,CNY',
        'coins'             => 'BTC,ETH,XRP,BNB,SOL,TRX',
        'silent_night'      => '0',
    ];

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        foreach (Db::all('SELECT key, value FROM settings') as $row) {
            self::$cache[(string) $row['key']] = (string) $row['value'];
        }
    }

    public static function get(string $key, ?string $default = null): string
    {
        self::load();
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        if ($default !== null) {
            return $default;
        }
        return self::DEFAULTS[$key] ?? '';
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key, (string) $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key, $default ? '1' : '0');
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function set(string $key, string $value): void
    {
        self::load();
        Db::exec(
            'INSERT INTO settings(key, value) VALUES(:k, :v)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value',
            [':k' => $key, ':v' => $value]
        );
        self::$cache[$key] = $value;
    }

    public static function forget(string $key): void
    {
        Db::exec('DELETE FROM settings WHERE key = :k', [':k' => $key]);
        self::$cache = null;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        self::load();
        return array_replace(self::DEFAULTS, self::$cache ?? []);
    }

    public static function timezone(): \DateTimeZone
    {
        $tz = self::get('timezone');
        try {
            return new \DateTimeZone($tz);
        } catch (\Throwable) {
            return new \DateTimeZone('Asia/Tehran');
        }
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', self::timezone());
    }

    /** ارزهای انتخاب‌شده برای کارت قیمت‌ها */
    public static function coins(): array
    {
        $raw = self::get('coins');
        $list = array_values(array_filter(array_map(
            static fn ($s) => strtoupper(trim($s)),
            explode(',', $raw)
        )));
        return $list ?: ['BTC', 'ETH', 'XRP', 'BNB', 'SOL', 'TRX'];
    }
}

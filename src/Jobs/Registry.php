<?php
declare(strict_types=1);

namespace Nikto\Jobs;

/**
 * فهرست کارهای قابل زمان‌بندی.
 */
final class Registry
{
    private const CLASSES = [
        PricesJob::KEY    => PricesJob::class,
        FearGreedJob::KEY => FearGreedJob::class,
        CalendarJob::KEY  => CalendarJob::class,
    ];

    /** @var array<string,Job> */
    private static array $instances = [];

    public static function get(string $key): ?Job
    {
        if (!isset(self::CLASSES[$key])) {
            return null;
        }
        if (!isset(self::$instances[$key])) {
            $class = self::CLASSES[$key];
            self::$instances[$key] = new $class();
        }

        return self::$instances[$key];
    }

    /** @return array<string,Job> */
    public static function all(): array
    {
        $out = [];
        foreach (array_keys(self::CLASSES) as $key) {
            $job = self::get($key);
            if ($job !== null) {
                $out[$key] = $job;
            }
        }

        return $out;
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::CLASSES);
    }

    public static function refresh(string $key): ?Job
    {
        unset(self::$instances[$key]);

        return self::get($key);
    }
}

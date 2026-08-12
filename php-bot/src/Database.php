<?php

declare(strict_types=1);

namespace App;

/**
 * Defaults to a single SQLite file - zero setup, no database to create.
 * Set DB_DRIVER=mysql in .env (and fill DB_HOST/DB_NAME/DB_USER/DB_PASS)
 * if you'd rather use a real MySQL database.
 */
final class Database
{
    private static ?\PDO $pdo = null;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            $driver = strtolower(Config::env('DB_DRIVER', 'sqlite') ?? 'sqlite');
            self::$pdo = $driver === 'mysql' ? self::connectMysql() : self::connectSqlite();
        }
        return self::$pdo;
    }

    private static function connectMysql(): \PDO
    {
        $host = Config::env('DB_HOST', 'localhost');
        $port = Config::env('DB_PORT', '3306');
        $name = Config::required('DB_NAME');
        $user = Config::required('DB_USER');
        $pass = Config::env('DB_PASS', '') ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    private static function connectSqlite(): \PDO
    {
        $path = Config::env('DB_PATH', 'data/bot.sqlite') ?? 'data/bot.sqlite';
        if (!str_starts_with($path, '/')) {
            $path = dirname(__DIR__) . '/' . $path;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $pdo = new \PDO("sqlite:{$path}", null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL'); // lets the webhook and cron jobs read/write concurrently without locking errors

        self::ensureSqliteSchema($pdo);

        return $pdo;
    }

    private static function ensureSqliteSchema(\PDO $pdo): void
    {
        $exists = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'")->fetch();
        if ($exists) {
            return;
        }
        $sql = (string) file_get_contents(dirname(__DIR__) . '/sql/schema_sqlite.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }

    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return gmdate('Y-m-d');
    }
}

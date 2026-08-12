<?php

declare(strict_types=1);

namespace App;

final class Database
{
    private static ?\PDO $pdo = null;

    public static function connection(): \PDO
    {
        if (self::$pdo === null) {
            $host = Config::env('DB_HOST', 'localhost');
            $port = Config::env('DB_PORT', '3306');
            $name = Config::required('DB_NAME');
            $user = Config::required('DB_USER');
            $pass = Config::env('DB_PASS', '') ?? '';

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
            self::$pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
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

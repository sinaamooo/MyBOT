<?php
declare(strict_types=1);

namespace Nikto\Core;

use PDO;

/**
 * لایه‌ی دیتابیس (SQLite) + ساخت جداول.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $path = (string) Config::get('db_path');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA busy_timeout = 8000');
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::$pdo = $pdo;
        self::migrate();

        return self::$pdo;
    }

    public static function migrate(): void
    {
        $pdo = self::$pdo ?? self::pdo();
        $pdo->exec(<<<'SQL'
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS channels (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            chat_id    TEXT NOT NULL UNIQUE,
            title      TEXT NOT NULL DEFAULT '',
            username   TEXT NOT NULL DEFAULT '',
            type       TEXT NOT NULL DEFAULT 'channel',
            active     INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS jobs (
            key        TEXT PRIMARY KEY,
            title      TEXT NOT NULL,
            enabled    INTEGER NOT NULL DEFAULT 0,
            theme      TEXT NOT NULL DEFAULT 'aurora',
            caption    TEXT NOT NULL DEFAULT '',
            options    TEXT NOT NULL DEFAULT '{}',
            updated_at INTEGER NOT NULL DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS job_channels (
            job_key    TEXT NOT NULL,
            channel_id INTEGER NOT NULL,
            PRIMARY KEY (job_key, channel_id),
            FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS schedules (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            job_key  TEXT NOT NULL,
            at_time  TEXT NOT NULL,
            days     TEXT NOT NULL DEFAULT '*',
            enabled  INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_schedule_unique ON schedules(job_key, at_time, days);

        CREATE TABLE IF NOT EXISTS runs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            job_key    TEXT NOT NULL,
            slot       TEXT NOT NULL,
            status     TEXT NOT NULL,
            detail     TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        );
        CREATE UNIQUE INDEX IF NOT EXISTS idx_runs_slot ON runs(job_key, slot);

        CREATE TABLE IF NOT EXISTS user_state (
            user_id    INTEGER PRIMARY KEY,
            action     TEXT NOT NULL DEFAULT '',
            payload    TEXT NOT NULL DEFAULT '',
            updated_at INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS admins (
            user_id    INTEGER PRIMARY KEY,
            name       TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS logs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            level      TEXT NOT NULL,
            message    TEXT NOT NULL,
            context    TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS cache (
            key        TEXT PRIMARY KEY,
            value      TEXT NOT NULL,
            expires_at INTEGER NOT NULL
        );
        SQL);
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function exec(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_NUM);
        return $row === false ? null : $row[0];
    }
}

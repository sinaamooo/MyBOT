<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Logger;

final class LogService
{
    public static function log(string $level, string $source, string $message): void
    {
        Logger::bot($level, "[{$source}] {$message}");
        $stmt = Database::connection()->prepare('INSERT INTO logs (level, source, message, created_at) VALUES (:l, :s, :m, :now)');
        $stmt->execute(['l' => strtoupper($level), 's' => $source, 'm' => $message, 'now' => Database::now()]);
    }

    /** @return array<int, array<string, mixed>> */
    public static function recent(int $limit = 20): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM logs ORDER BY created_at DESC LIMIT :l');
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

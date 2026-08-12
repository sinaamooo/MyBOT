<?php

declare(strict_types=1);

namespace App\Services;

use App\Config;
use App\Database;

final class AdminService
{
    public static function isAdmin(int $telegramId): bool
    {
        if (in_array($telegramId, Config::adminIds(), true)) {
            return true;
        }
        $stmt = Database::connection()->prepare('SELECT id FROM admins WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
        return (bool) $stmt->fetch();
    }

    public static function add(int $telegramId, ?string $label, int $addedBy): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
        if (!$stmt->fetch()) {
            $ins = $pdo->prepare('INSERT INTO admins (telegram_id, label, added_by, created_at) VALUES (:t, :l, :a, :now)');
            $ins->execute(['t' => $telegramId, 'l' => $label, 'a' => $addedBy, 'now' => Database::now()]);
        }
    }

    public static function remove(int $telegramId): bool
    {
        $stmt = Database::connection()->prepare('DELETE FROM admins WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public static function list(): array
    {
        return Database::connection()->query('SELECT * FROM admins ORDER BY created_at')->fetchAll();
    }
}

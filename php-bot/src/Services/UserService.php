<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;

final class UserService
{
    public static function register(int $telegramId, ?string $username, ?string $firstName): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE telegram_id = :t');
        $stmt->execute(['t' => $telegramId]);
        if ($row = $stmt->fetch()) {
            $upd = $pdo->prepare('UPDATE users SET username = :u, first_name = :f, last_seen_at = :now WHERE id = :id');
            $upd->execute(['u' => $username, 'f' => $firstName, 'now' => Database::now(), 'id' => $row['id']]);
        } else {
            $ins = $pdo->prepare('INSERT INTO users (telegram_id, username, first_name, created_at, last_seen_at) VALUES (:t, :u, :f, :now, :now2)');
            $ins->execute(['t' => $telegramId, 'u' => $username, 'f' => $firstName, 'now' => Database::now(), 'now2' => Database::now()]);
        }
    }

    public static function count(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
    }

    /** @return array<int, array<string, mixed>> */
    public static function list(int $limit = 10, int $offset = 0): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users ORDER BY last_seen_at DESC LIMIT :l OFFSET :o');
        $stmt->bindValue(':l', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':o', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

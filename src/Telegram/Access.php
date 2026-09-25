<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use Nikto\Core\Config;
use Nikto\Core\Db;

final class Access
{
    public static function isAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }
        if (in_array($userId, Config::admins(), true)) {
            return true;
        }

        return Db::one('SELECT 1 FROM admins WHERE user_id = :u', [':u' => $userId]) !== null;
    }

    public static function isOwner(int $userId): bool
    {
        $owner = (int) Config::get('owner_id', 0);

        return $owner > 0 ? $userId === $owner : self::isAdmin($userId);
    }

    public static function add(int $userId, string $name = ''): void
    {
        Db::exec(
            'INSERT INTO admins(user_id, name, created_at) VALUES(:u, :n, :t)
             ON CONFLICT(user_id) DO UPDATE SET name = excluded.name',
            [':u' => $userId, ':n' => $name, ':t' => time()]
        );
    }

    public static function remove(int $userId): void
    {
        Db::exec('DELETE FROM admins WHERE user_id = :u', [':u' => $userId]);
    }

    public static function list(): array
    {
        $rows = Db::all('SELECT * FROM admins ORDER BY created_at');
        $configured = [];
        foreach (Config::admins() as $id) {
            $configured[] = ['user_id' => $id, 'name' => 'پیکربندی‌شده در config', 'created_at' => 0, 'fixed' => true];
        }

        return array_merge($configured, $rows);
    }

    public static function ids(): array
    {
        $ids = Config::admins();
        foreach (Db::all('SELECT user_id FROM admins') as $row) {
            $ids[] = (int) $row['user_id'];
        }

        return array_values(array_unique(array_filter($ids)));
    }
}

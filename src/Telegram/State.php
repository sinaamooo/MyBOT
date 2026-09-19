<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use Nikto\Core\Db;

/**
 * وضعیت گفتگوی کاربر (منتظر ورودی متنی).
 */
final class State
{
    public static function set(int $userId, string $action, string $payload = ''): void
    {
        Db::exec(
            'INSERT INTO user_state(user_id, action, payload, updated_at) VALUES(:u, :a, :p, :t)
             ON CONFLICT(user_id) DO UPDATE SET action = excluded.action, payload = excluded.payload, updated_at = excluded.updated_at',
            [':u' => $userId, ':a' => $action, ':p' => $payload, ':t' => time()]
        );
    }

    /** @return array{action:string,payload:string} */
    public static function get(int $userId): array
    {
        $row = Db::one('SELECT action, payload, updated_at FROM user_state WHERE user_id = :u', [':u' => $userId]);
        if ($row === null) {
            return ['action' => '', 'payload' => ''];
        }
        // وضعیت‌های قدیمی‌تر از ۱۵ دقیقه منقضی می‌شوند
        if ((int) $row['updated_at'] < time() - 900) {
            self::clear($userId);
            return ['action' => '', 'payload' => ''];
        }

        return ['action' => (string) $row['action'], 'payload' => (string) $row['payload']];
    }

    public static function clear(int $userId): void
    {
        Db::exec('DELETE FROM user_state WHERE user_id = :u', [':u' => $userId]);
    }
}

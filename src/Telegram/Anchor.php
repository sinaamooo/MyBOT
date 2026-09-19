<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use Nikto\Core\Db;

/**
 * پیام «لنگر» پنل: هر کاربر یک پیام دارد که به‌جای ارسال پیام تازه، ویرایش می‌شود.
 */
final class Anchor
{
    /** @return array{chat_id:string,message_id:int,preview_message_id:int}|null */
    public static function get(int $userId): ?array
    {
        $row = Db::one('SELECT * FROM panel_anchor WHERE user_id = :u', [':u' => $userId]);
        if ($row === null) {
            return null;
        }

        return [
            'chat_id'            => (string) $row['chat_id'],
            'message_id'         => (int) $row['message_id'],
            'preview_message_id' => (int) $row['preview_message_id'],
        ];
    }

    public static function set(int $userId, int|string $chatId, int $messageId): void
    {
        Db::exec(
            'INSERT INTO panel_anchor(user_id, chat_id, message_id, preview_message_id, updated_at)
             VALUES(:u, :c, :m, 0, :t)
             ON CONFLICT(user_id) DO UPDATE SET chat_id = excluded.chat_id,
                                                message_id = excluded.message_id,
                                                updated_at = excluded.updated_at',
            [':u' => $userId, ':c' => (string) $chatId, ':m' => $messageId, ':t' => time()]
        );
    }

    public static function setPreview(int $userId, int $messageId): void
    {
        Db::exec(
            'UPDATE panel_anchor SET preview_message_id = :m WHERE user_id = :u',
            [':u' => $userId, ':m' => $messageId]
        );
    }

    public static function forget(int $userId): void
    {
        Db::exec('DELETE FROM panel_anchor WHERE user_id = :u', [':u' => $userId]);
    }
}

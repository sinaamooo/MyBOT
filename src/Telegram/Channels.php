<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use Nikto\Core\Config;
use Nikto\Core\Db;

/**
 * مدیریت کانال‌های مقصد.
 */
final class Channels
{
    /** @return array<int,array<string,mixed>> */
    public static function all(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM channels' . ($onlyActive ? ' WHERE active = 1' : '') . ' ORDER BY id';

        return Db::all($sql);
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM channels WHERE id = :i', [':i' => $id]);
    }

    public static function findByChatId(string $chatId): ?array
    {
        return Db::one('SELECT * FROM channels WHERE chat_id = :c', [':c' => $chatId]);
    }

    public static function remove(int $id): void
    {
        Db::exec('DELETE FROM job_channels WHERE channel_id = :i', [':i' => $id]);
        Db::exec('DELETE FROM channels WHERE id = :i', [':i' => $id]);
    }

    /**
     * افزودن کانال با بررسی دسترسی ربات.
     * @return array{ok:bool,message:string,channel?:array<string,mixed>}
     */
    public static function add(Api $api, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ok' => false, 'message' => 'شناسه‌ی کانال خالی است.'];
        }
        if (preg_match('~t\.me/([A-Za-z0-9_]{4,})~', $raw, $m)) {
            $raw = '@' . $m[1];
        }
        if (!str_starts_with($raw, '@') && !preg_match('/^-?\d+$/', $raw)) {
            $raw = '@' . ltrim($raw, '@');
        }

        $chat = $api->getChat($raw);
        if (!($chat['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => "کانال پیدا نشد یا ربات به آن دسترسی ندارد.\n<code>"
                    . htmlspecialchars((string) ($chat['description'] ?? '')) . '</code>',
            ];
        }

        $result = $chat['result'];
        $chatId = (string) $result['id'];
        $title = (string) ($result['title'] ?? ($result['first_name'] ?? 'بدون نام'));
        $username = (string) ($result['username'] ?? '');
        $type = (string) ($result['type'] ?? 'channel');

        $botId = (int) Config::get('bot_id', 0);
        if ($botId <= 0) {
            $me = $api->getMe();
            if ($me['ok'] ?? false) {
                $botId = (int) $me['result']['id'];
            }
        }

        $status = '';
        if ($botId > 0) {
            $member = $api->getChatMember($chatId, $botId);
            if ($member['ok'] ?? false) {
                $status = (string) ($member['result']['status'] ?? '');
            }
        }
        if ($status !== '' && !in_array($status, ['administrator', 'creator'], true)) {
            return [
                'ok' => false,
                'message' => 'ربات در «' . htmlspecialchars($title) . '» ادمین نیست. ابتدا ربات را ادمین کنید و دوباره تلاش کنید.',
            ];
        }

        Db::exec(
            'INSERT INTO channels(chat_id, title, username, type, active, created_at)
             VALUES(:c, :t, :u, :ty, 1, :cr)
             ON CONFLICT(chat_id) DO UPDATE SET title = excluded.title, username = excluded.username, active = 1',
            [':c' => $chatId, ':t' => $title, ':u' => $username, ':ty' => $type, ':cr' => time()]
        );

        return [
            'ok' => true,
            'message' => 'کانال «' . htmlspecialchars($title) . '» با موفقیت اضافه شد.',
            'channel' => self::findByChatId($chatId) ?? [],
        ];
    }

    /** بررسی دسترسی ربات به همه‌ی کانال‌ها */
    public static function check(Api $api): array
    {
        $botId = (int) Config::get('bot_id', 0);
        if ($botId <= 0) {
            $me = $api->getMe();
            $botId = ($me['ok'] ?? false) ? (int) $me['result']['id'] : 0;
        }

        $report = [];
        foreach (self::all() as $channel) {
            $chat = $api->getChat((string) $channel['chat_id']);
            if (!($chat['ok'] ?? false)) {
                $report[] = ['channel' => $channel, 'ok' => false, 'status' => 'دسترسی ندارد'];
                Db::exec('UPDATE channels SET active = 0 WHERE id = :i', [':i' => $channel['id']]);
                continue;
            }
            $status = 'نامشخص';
            if ($botId > 0) {
                $member = $api->getChatMember((string) $channel['chat_id'], $botId);
                $status = ($member['ok'] ?? false) ? (string) ($member['result']['status'] ?? '') : 'خطا';
            }
            $ok = in_array($status, ['administrator', 'creator'], true);
            Db::exec('UPDATE channels SET active = :a, title = :t WHERE id = :i', [
                ':a' => $ok ? 1 : 0,
                ':t' => (string) ($chat['result']['title'] ?? $channel['title']),
                ':i' => $channel['id'],
            ]);
            $report[] = ['channel' => $channel, 'ok' => $ok, 'status' => $status];
        }

        return $report;
    }

    public static function label(array $channel): string
    {
        $title = (string) $channel['title'];
        $username = (string) $channel['username'];

        return $username !== '' ? $title . ' (@' . $username . ')' : $title;
    }
}

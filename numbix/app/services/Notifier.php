<?php

final class Notifier
{
    public static function user(int $userId, string $title, string $body = '', string $link = '', string $icon = 'bell'): void
    {
        DB::insert('notifications', [
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 160),
            'body' => mb_substr($body, 0, 255),
            'link' => $link,
            'icon' => $icon,
        ]);
    }

    public static function admin(string $title, string $body = '', string $link = '', string $icon = 'bell', bool $telegram = true): void
    {
        DB::insert('notifications', [
            'for_admin' => 1,
            'title' => mb_substr($title, 0, 160),
            'body' => mb_substr($body, 0, 255),
            'link' => $link,
            'icon' => $icon,
        ]);
        if ($telegram) {
            self::telegram("🔔 <b>" . e($title) . "</b>\n" . e($body) . ($link ? "\n" . abs_url($link) : ''));
        }
    }

    /** Push a message to the admin's Telegram chat (optional, configured in settings). */
    public static function telegram(string $html): bool
    {
        $token = setting('telegram_bot_token');
        $chat = setting('telegram_admin_chat');
        if (!$token || !$chat) {
            return false;
        }
        $r = http_post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chat,
            'text' => $html,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ], [], 6);
        return $r['ok'] && str_contains($r['body'], '"ok":true');
    }

    public static function mail(string $to, string $subject, string $html): bool
    {
        $from = setting('mail_from', 'no-reply@' . preg_replace('/^www\./', '', (string)parse_url(abs_url(), PHP_URL_HOST)));
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: =?UTF-8?B?' . base64_encode(site_name()) . '?= <' . $from . '>',
        ];
        $body = '<div dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.9;background:#f4f5fb;padding:24px"><div style="max-width:520px;margin:auto;background:#fff;border-radius:16px;padding:28px">'
            . '<h2 style="color:#6C4CF1;margin-top:0">' . e(site_name()) . '</h2>' . $html . '</div></div>';
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    }
}

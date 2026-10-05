<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Support\Http;
use CURLFile;

/**
 * Minimal Telegram Bot API client. In dry-run mode calls are logged instead of sent.
 */
final class Client
{
    public array $sent = [];
    /** Dry-run only: canned results per method name. */
    public array $fake = [];

    public function __construct(private string $token, private bool $dryRun = false)
    {
    }

    public function call(string $method, array $params = []): ?array
    {
        if ($this->dryRun) {
            $this->sent[] = [$method, $params];
            if (array_key_exists($method, $this->fake)) {
                return $this->fake[$method];
            }
            return ['message_id' => count($this->sent), 'chat' => ['id' => $params['chat_id'] ?? 0]];
        }
        $hasFile = false;
        foreach ($params as $k => $v) {
            if ($v instanceof CURLFile) {
                $hasFile = true;
            } elseif (is_array($v)) {
                $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($v)) {
                $params[$k] = $v ? 'true' : 'false';
            }
        }
        $url = "https://api.telegram.org/bot{$this->token}/{$method}";
        $res = Http::request('POST', $url, ['form' => $params, 'timeout' => $hasFile ? 60 : 20]);
        $data = json_decode($res['body'], true);
        if (!is_array($data) || empty($data['ok'])) {
            app_log("telegram {$method} failed: HTTP {$res['status']} " . substr($res['body'], 0, 300) . $res['error']);
            return null;
        }
        return is_array($data['result']) ? $data['result'] : ['value' => $data['result']];
    }

    /** Adds the direct-messages topic (channel DMs) to the target when present. */
    private static function target(array $to): array
    {
        $p = ['chat_id' => $to['chat_id']];
        if (!empty($to['topic_id'])) {
            $p['direct_messages_topic_id'] = $to['topic_id'];
        }
        if (!empty($to['reply_to'])) {
            $p['reply_parameters'] = ['message_id' => $to['reply_to'], 'allow_sending_without_reply' => true];
        }
        return $p;
    }

    public function sendMessage(array $to, string $html, array $extra = []): ?array
    {
        $send = fn (string $text) => $this->call('sendMessage', self::target($to) + [
            'text' => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ] + $extra);
        $res = $send($html);
        if ($res === null && str_contains($html, '<tg-emoji')) {
            // Premium emoji need a Premium bot owner; fall back to the plain emoji.
            $res = $send(Entities::stripCustomEmoji($html));
        }
        return $res;
    }

    public function sendPhoto(array $to, string $path, string $caption, array $extra = []): ?array
    {
        $send = fn (string $text) => $this->call('sendPhoto', self::target($to) + [
            'photo' => $this->dryRun ? $path : new CURLFile($path, 'image/png', basename($path)),
            'caption' => $text,
            'parse_mode' => 'HTML',
        ] + $extra);
        $res = $send($caption);
        if ($res === null && str_contains($caption, '<tg-emoji')) {
            $res = $send(Entities::stripCustomEmoji($caption));
        }
        return $res;
    }

    /** The bot's own user id is the part of the token before the colon. */
    public function botId(): int
    {
        return (int) strtok($this->token, ':');
    }

    public function editMessage(int|string $chatId, int $messageId, string $html, ?array $keyboard = null): ?array
    {
        $p = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $html, 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]];
        if ($keyboard !== null) {
            $p['reply_markup'] = ['inline_keyboard' => $keyboard];
        }
        $res = $this->call('editMessageText', $p);
        if ($res === null && str_contains($html, '<tg-emoji')) {
            $p['text'] = Entities::stripCustomEmoji($html);
            $res = $this->call('editMessageText', $p);
        }
        return $res;
    }

    public function answerCallback(string $id, string $text = ''): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $id] + ($text !== '' ? ['text' => $text] : []));
    }

    public function deleteMessage(int|string $chatId, int $messageId): void
    {
        $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    public function chatAction(array $to, string $action = 'upload_photo'): void
    {
        $p = ['chat_id' => $to['chat_id'], 'action' => $action];
        $this->call('sendChatAction', $p);
    }
}

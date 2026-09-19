<?php
declare(strict_types=1);

namespace Nikto\Telegram;

use CURLFile;
use Nikto\Core\Config;
use Nikto\Core\Log;

/**
 * کلاینت Bot API تلگرام.
 */
class Api
{
    private string $token;
    private string $base;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? Config::token();
        $this->base = 'https://api.telegram.org/bot' . $this->token . '/';
    }

    public function hasToken(): bool
    {
        return trim($this->token) !== '';
    }

    /**
     * فراخوانی متد Bot API.
     * @return array{ok:bool,result?:mixed,description?:string,error_code?:int}
     */
    public function call(string $method, array $params = [], int $timeout = 60): array
    {
        if (!$this->hasToken()) {
            return ['ok' => false, 'description' => 'توکن ربات تنظیم نشده است'];
        }

        $hasFile = false;
        foreach ($params as $value) {
            if ($value instanceof CURLFile) {
                $hasFile = true;
                break;
            }
        }

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $params[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
            } elseif (is_bool($value)) {
                $params[$key] = $value ? 'true' : 'false';
            } elseif ($value === null) {
                unset($params[$key]);
            }
        }

        $ch = curl_init($this->base . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $hasFile ? $params : http_build_query($params),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);
        $proxy = (string) Config::get('http_proxy', '');
        if ($proxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
        }

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            Log::error('Telegram request failed', ['method' => $method, 'error' => $err]);
            return ['ok' => false, 'description' => $err ?: 'خطای شبکه'];
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'description' => 'پاسخ نامعتبر از تلگرام'];
        }
        if (!($data['ok'] ?? false)) {
            Log::warn('Telegram API error', [
                'method' => $method,
                'error'  => $data['description'] ?? '',
                'chat'   => $params['chat_id'] ?? null,
            ]);
        }

        return $data;
    }

    public function getMe(): array
    {
        return $this->call('getMe');
    }

    public function getUpdates(int $offset, int $timeout = 30): array
    {
        $res = $this->call('getUpdates', [
            'offset'          => $offset,
            'timeout'         => $timeout,
            'allowed_updates' => ['message', 'callback_query', 'channel_post', 'my_chat_member'],
        ], $timeout + 20);

        return ($res['ok'] ?? false) && is_array($res['result'] ?? null) ? $res['result'] : [];
    }

    public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null, array $extra = []): array
    {
        return $this->call('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => $keyboard ? ['inline_keyboard' => $keyboard] : null,
        ], $extra));
    }

    public function editMessage(int|string $chatId, int $messageId, string $text, ?array $keyboard = null): array
    {
        $res = $this->call('editMessageText', [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
            'reply_markup' => $keyboard ? ['inline_keyboard' => $keyboard] : null,
        ]);

        // اگر متن تغییری نکرده باشد تلگرام خطا می‌دهد — بی‌اهمیت است
        if (!($res['ok'] ?? false) && str_contains((string) ($res['description'] ?? ''), 'message is not modified')) {
            return ['ok' => true];
        }

        return $res;
    }

    public function answerCallback(string $callbackId, string $text = '', bool $alert = false): array
    {
        return $this->call('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text'              => $text,
            'show_alert'        => $alert,
        ]);
    }

    public function sendPhoto(int|string $chatId, string $path, string $caption = '', array $extra = []): array
    {
        return $this->call('sendPhoto', array_merge([
            'chat_id'    => $chatId,
            'photo'      => new CURLFile($path, 'image/png', basename($path)),
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ], $extra), 180);
    }

    public function sendDocument(int|string $chatId, string $path, string $caption = '', array $extra = []): array
    {
        return $this->call('sendDocument', array_merge([
            'chat_id'    => $chatId,
            'document'   => new CURLFile($path, 'image/png', basename($path)),
            'caption'    => $caption,
            'parse_mode' => 'HTML',
        ], $extra), 180);
    }

    public function sendChatAction(int|string $chatId, string $action = 'upload_photo'): void
    {
        $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => $action], 15);
    }

    public function deleteMessage(int|string $chatId, int $messageId): array
    {
        return $this->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    public function getChat(int|string $chatId): array
    {
        return $this->call('getChat', ['chat_id' => $chatId], 25);
    }

    public function getChatMember(int|string $chatId, int $userId): array
    {
        return $this->call('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId], 25);
    }

    public function setWebhook(string $url, string $secret = ''): array
    {
        return $this->call('setWebhook', [
            'url'             => $url,
            'secret_token'    => $secret ?: null,
            'allowed_updates' => ['message', 'callback_query', 'my_chat_member'],
            'drop_pending_updates' => true,
        ]);
    }

    public function deleteWebhook(): array
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => false]);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo');
    }
}

<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Config;
use App\Logger;

/**
 * Thin cURL wrapper around the Telegram Bot API - no SDK dependency.
 */
final class TelegramApi
{
    private string $baseUrl;

    public function __construct(?string $token = null)
    {
        $token ??= Config::required('TELEGRAM_BOT_TOKEN');
        $this->baseUrl = "https://api.telegram.org/bot{$token}/";
    }

    /** @return array<string, mixed>|null message object on success */
    public function sendMessage(int|string $chatId, string $text, ?array $replyMarkup = null): ?array
    {
        $params = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }
        $result = $this->call('sendMessage', $params);
        return $result['result'] ?? null;
    }

    public function editMessageText(int|string $chatId, int $messageId, string $text, ?array $replyMarkup = null): bool
    {
        $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text, 'parse_mode' => 'HTML'];
        if ($replyMarkup !== null) {
            $params['reply_markup'] = $replyMarkup;
        }
        $result = $this->call('editMessageText', $params, throwOnError: false);
        return (bool) ($result['ok'] ?? false);
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false): bool
    {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text !== null) {
            $params['text'] = $text;
            $params['show_alert'] = $showAlert;
        }
        $result = $this->call('answerCallbackQuery', $params, throwOnError: false);
        return (bool) ($result['ok'] ?? false);
    }

    public function setWebhook(string $url, string $secretToken): bool
    {
        $result = $this->call('setWebhook', ['url' => $url, 'secret_token' => $secretToken]);
        return (bool) ($result['ok'] ?? false);
    }

    public function deleteWebhook(): bool
    {
        $result = $this->call('deleteWebhook', []);
        return (bool) ($result['ok'] ?? false);
    }

    public function getWebhookInfo(): array
    {
        return $this->call('getWebhookInfo', [])['result'] ?? [];
    }

    /** @param array<int, array{command:string, description:string}> $commands */
    public function setMyCommands(array $commands): bool
    {
        $result = $this->call('setMyCommands', ['commands' => $commands]);
        return (bool) ($result['ok'] ?? false);
    }

    public function getMe(): array
    {
        return $this->call('getMe', [])['result'] ?? [];
    }

    /** @param array<string, mixed> $params @return array<string, mixed> */
    private function call(string $method, array $params, bool $throwOnError = true): array
    {
        $ch = curl_init($this->baseUrl . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0) {
            Logger::bot('ERROR', "Telegram API cURL error on {$method}: {$error}");
            if ($throwOnError) {
                throw new \RuntimeException("Telegram API cURL error: {$error}");
            }
            return ['ok' => false];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            Logger::bot('ERROR', "Telegram API invalid JSON on {$method}: " . substr((string) $body, 0, 300));
            return ['ok' => false];
        }
        if (($decoded['ok'] ?? false) !== true) {
            $desc = $decoded['description'] ?? 'unknown error';
            Logger::bot('WARNING', "Telegram API {$method} failed: {$desc}");
        }
        return $decoded;
    }
}

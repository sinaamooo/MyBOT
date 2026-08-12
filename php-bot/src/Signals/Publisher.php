<?php

declare(strict_types=1);

namespace App\Signals;

use App\Services\LogService;
use App\Telegram\TelegramApi;

final class Publisher
{
    /** Telegram's hard limit on photo captions (UTF-16 code units; mb_strlen is a close enough proxy). */
    private const CAPTION_LIMIT = 1024;

    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly string $channelId,
    ) {
    }

    /** Sends the rank-card image and signal text as a single photo+caption message when they fit together. */
    public function publishSignal(string $text, ?string $cardImagePath = null): ?int
    {
        if ($cardImagePath === null) {
            $message = $this->telegram->sendMessage($this->channelId, $text);
            return isset($message['message_id']) ? (int) $message['message_id'] : null;
        }

        if (mb_strlen($text, 'UTF-8') <= self::CAPTION_LIMIT) {
            $message = $this->telegram->sendPhoto($this->channelId, $cardImagePath, $text);
            @unlink($cardImagePath);
            if ($message !== null) {
                return isset($message['message_id']) ? (int) $message['message_id'] : null;
            }
            LogService::log('WARNING', 'signals', 'Signal card+caption send failed, falling back to text only');
            $message = $this->telegram->sendMessage($this->channelId, $text);
            return isset($message['message_id']) ? (int) $message['message_id'] : null;
        }

        // Template renders longer than Telegram's caption limit allows - fall back to two
        // messages rather than truncating the admin's signal text.
        LogService::log('WARNING', 'signals', 'Signal text too long for a photo caption (' . mb_strlen($text, 'UTF-8') . ' chars), sending image and text separately');
        if ($this->telegram->sendPhoto($this->channelId, $cardImagePath) === null) {
            LogService::log('WARNING', 'signals', 'Signal card image failed to send, continuing with text only');
        }
        @unlink($cardImagePath);

        $message = $this->telegram->sendMessage($this->channelId, $text);
        return isset($message['message_id']) ? (int) $message['message_id'] : null;
    }

    public function publishUpdate(string $text, ?int $replyToMessageId = null): void
    {
        $this->telegram->sendMessage($this->channelId, $text, null, $replyToMessageId);
    }
}

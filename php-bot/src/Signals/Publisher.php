<?php

declare(strict_types=1);

namespace App\Signals;

use App\Services\LogService;
use App\Telegram\TelegramApi;

final class Publisher
{
    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly string $channelId,
    ) {
    }

    public function publishSignal(string $text, ?string $cardImagePath = null): ?int
    {
        if ($cardImagePath !== null) {
            if ($this->telegram->sendPhoto($this->channelId, $cardImagePath) === null) {
                LogService::log('WARNING', 'signals', 'Signal card image failed to send, continuing with text only');
            }
            @unlink($cardImagePath);
        }

        $message = $this->telegram->sendMessage($this->channelId, $text);
        return isset($message['message_id']) ? (int) $message['message_id'] : null;
    }

    public function publishUpdate(string $text): void
    {
        $this->telegram->sendMessage($this->channelId, $text);
    }
}

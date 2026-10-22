<?php

declare(strict_types=1);

namespace EmojiMaker\Telegram;

use EmojiMaker\Security\Redactor;

/**
 * Error returned by the Bot API (error_code + description) or a transport
 * failure (code 0). The description is redacted on construction so a token
 * can never travel further inside an exception message.
 */
final class TelegramException extends \RuntimeException
{
    public function __construct(
        string $description,
        int $errorCode = 0,
        public readonly ?int $retryAfter = null,
        public readonly ?int $migrateToChatId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(Redactor::text($description), $errorCode, $previous);
    }

    /** Telegram error_code (400, 403, 429 ...) or 0 for network errors. */
    public function errorCode(): int
    {
        return (int) $this->getCode();
    }

    /** True when the user cannot be reached any more (blocked the bot, deleted account ...). */
    public function isBlockedByUser(): bool
    {
        if ($this->errorCode() !== 403) {
            return false;
        }
        return (bool) preg_match(
            "/blocked by the user|user is deactivated|bot was kicked|can't initiate conversation|bot can't send messages to bots/i",
            $this->getMessage()
        );
    }

    /** "message is not modified": the edit would not change anything (harmless). */
    public function isNotModified(): bool
    {
        return $this->errorCode() === 400 && stripos($this->getMessage(), 'message is not modified') !== false;
    }

    /** Flood control (429) - retryAfter tells how many seconds to wait. */
    public function isFlood(): bool
    {
        return $this->errorCode() === 429;
    }

    /** No usable answer from Telegram (DNS, connect, timeout, invalid response). */
    public function isNetworkError(): bool
    {
        return $this->errorCode() === 0;
    }
}

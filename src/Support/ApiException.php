<?php

declare(strict_types=1);

namespace MyBot\Support;

use RuntimeException;

final class ApiException extends RuntimeException
{
    /** @param array<string, mixed> $parameters */
    public function __construct(
        string $description,
        public readonly int $errorCode = 0,
        public readonly array $parameters = [],
        public readonly string $method = '',
    ) {
        parent::__construct($description, $errorCode);
    }

    /** Seconds Telegram asks us to wait, when it returned 429. */
    public function retryAfter(): ?int
    {
        $value = $this->parameters['retry_after'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    public function isFlood(): bool
    {
        return $this->errorCode === 429 || $this->retryAfter() !== null;
    }

    /** 5xx / network hiccups: worth retrying with a backoff. */
    public function isTransient(): bool
    {
        return $this->errorCode >= 500 || $this->errorCode === 0;
    }

    /**
     * The recipient is permanently unreachable — retrying will never help,
     * and we should stop targeting them.
     */
    public function isUnreachable(): bool
    {
        $haystack = mb_strtolower($this->getMessage());

        foreach (self::UNREACHABLE_MARKERS as $marker) {
            if (str_contains($haystack, $marker)) {
                return true;
            }
        }

        return false;
    }

    /** The user explicitly blocked / removed the bot. */
    public function isBlockedByUser(): bool
    {
        $haystack = mb_strtolower($this->getMessage());

        return str_contains($haystack, 'bot was blocked by the user')
            || str_contains($haystack, 'user is deactivated')
            || str_contains($haystack, 'bot can\'t initiate conversation');
    }

    private const UNREACHABLE_MARKERS = [
        'bot was blocked by the user',
        'user is deactivated',
        'chat not found',
        'peer_id_invalid',
        'bot was kicked',
        'bot is not a member',
        'the group chat was deleted',
        'chat_write_forbidden',
        'not enough rights to send',
        'have no rights to send a message',
        'topic_closed',
        'user_is_bot',
    ];
}

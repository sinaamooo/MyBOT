<?php

declare(strict_types=1);

namespace EmojiMaker\Queue;

/**
 * A job failure whose message is safe to show to the user (Persian).
 * Retryable failures (temporary problems) go back to the queue with backoff.
 */
final class JobFailed extends \RuntimeException
{
    public function __construct(string $userMessage, public readonly bool $retryable = false, ?\Throwable $previous = null)
    {
        parent::__construct($userMessage, 0, $previous);
    }
}

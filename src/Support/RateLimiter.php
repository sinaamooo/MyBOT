<?php

declare(strict_types=1);

namespace MyBot\Support;

/**
 * Keeps the sender inside Telegram's published limits:
 *   - ~30 messages/second overall
 *   - ~20 messages/minute into a single group
 *
 * It is a cooperative limiter: call throttle() immediately before each send.
 * A global 429 (flood wait) can be fed back in via penalise().
 */
final class RateLimiter
{
    /** @var array<int, float> chat_id => unix timestamp when that chat is free again */
    private array $chatReadyAt = [];

    /** @var list<float> timestamps of sends inside the current global window */
    private array $recentSends = [];

    private float $globalPauseUntil = 0.0;

    public function __construct(
        private readonly float $globalPerSecond = 25.0,
        private readonly float $groupInterval = 3.0,
        private readonly float $privateInterval = 0.05,
    ) {
    }

    /** Blocks until it is safe to send to $chatId. */
    public function throttle(int $chatId): void
    {
        $now = microtime(true);

        $waitUntil = max(
            $this->globalPauseUntil,
            $this->chatReadyAt[$chatId] ?? 0.0,
            $this->globalWindowReadyAt($now),
        );

        if ($waitUntil > $now) {
            $this->sleepUntil($waitUntil);
            $now = microtime(true);
        }

        $this->recentSends[] = $now;
        $this->chatReadyAt[$chatId] = $now + $this->intervalFor($chatId);

        // Keep the bookkeeping arrays from growing without bound.
        if (\count($this->chatReadyAt) > 5000) {
            $this->chatReadyAt = array_filter(
                $this->chatReadyAt,
                static fn (float $readyAt): bool => $readyAt > $now,
            );
        }
    }

    /** Telegram told us to back off globally for $seconds. */
    public function penalise(int $seconds): void
    {
        $this->globalPauseUntil = max($this->globalPauseUntil, microtime(true) + $seconds);
    }

    /** Telegram told us to back off for one specific chat. */
    public function penaliseChat(int $chatId, int $seconds): void
    {
        $this->chatReadyAt[$chatId] = max(
            $this->chatReadyAt[$chatId] ?? 0.0,
            microtime(true) + $seconds,
        );
    }

    private function intervalFor(int $chatId): float
    {
        // Negative chat ids are groups, supergroups and channels.
        return $chatId < 0 ? $this->groupInterval : $this->privateInterval;
    }

    private function globalWindowReadyAt(float $now): float
    {
        $windowStart = $now - 1.0;

        $this->recentSends = array_values(array_filter(
            $this->recentSends,
            static fn (float $t): bool => $t > $windowStart,
        ));

        if (\count($this->recentSends) < $this->globalPerSecond) {
            return 0.0;
        }

        // The oldest send in the window frees up a slot one second after it happened.
        return $this->recentSends[0] + 1.0;
    }

    private function sleepUntil(float $timestamp): void
    {
        $microseconds = (int) round(($timestamp - microtime(true)) * 1_000_000);

        if ($microseconds > 0) {
            usleep($microseconds);
        }
    }
}

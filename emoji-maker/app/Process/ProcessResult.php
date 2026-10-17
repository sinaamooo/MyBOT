<?php

declare(strict_types=1);

namespace EmojiMaker\Process;

final class ProcessResult
{
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly bool $timedOut,
        public readonly float $seconds,
    ) {
    }

    public function ok(): bool
    {
        return $this->exitCode === 0 && !$this->timedOut;
    }
}

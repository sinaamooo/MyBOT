<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Exchange\MexcClient;
use App\Signals\Backtester;
use App\Signals\Monitor;
use App\Signals\Scanner;
use App\Signals\SignalGenerator;

/** Shared runtime dependencies passed to every handler - PHP has no
 * long-lived DI container, so this is built fresh per webhook request. */
final class BotContext
{
    public function __construct(
        public readonly TelegramApi $telegram,
        public readonly MexcClient $exchange,
        public readonly SignalGenerator $engine,
        public readonly Scanner $scanner,
        public readonly Monitor $monitor,
        public readonly Backtester $backtester,
    ) {
    }
}

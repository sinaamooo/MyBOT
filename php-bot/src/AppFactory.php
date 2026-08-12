<?php

declare(strict_types=1);

namespace App;

use App\Exchange\MexcClient;
use App\Market\MarketDataService;
use App\Signals\Backtester;
use App\Signals\Monitor;
use App\Signals\Publisher;
use App\Signals\Scanner;
use App\Signals\SignalGenerator;
use App\Telegram\BotContext;
use App\Telegram\TelegramApi;

/** Wires the runtime dependency graph - shared by the webhook and both cron entrypoints. */
final class AppFactory
{
    public static function buildContext(): BotContext
    {
        $exchange = new MexcClient();
        $market = new MarketDataService($exchange);
        $telegram = new TelegramApi();
        $publisher = new Publisher($telegram, Config::required('TELEGRAM_CHANNEL_ID'));
        $engine = new SignalGenerator($market, $exchange);
        $scanner = new Scanner($engine, $publisher);
        $monitor = new Monitor($exchange, $publisher);
        $backtester = new Backtester($exchange);

        return new BotContext($telegram, $exchange, $engine, $scanner, $monitor, $backtester);
    }
}

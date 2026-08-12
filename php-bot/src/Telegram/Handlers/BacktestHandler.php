<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminStateService;
use App\Services\SettingsService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class BacktestHandler
{
    private const MAX_DAYS = 60;

    public static function show(int $chatId, int $messageId, BotContext $ctx): void
    {
        $text = "🧪 <b>Backtest</b>\n\n"
            . 'Replays the live scoring/risk engine bar-by-bar over past 5M candles '
            . '(resampled into 15M/1H/4H), up to ' . self::MAX_DAYS . " days.\n\n"
            . 'Note: this is an approximation for strategy research, not a tick-perfect simulation.';
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::backtest());
    }

    public static function startInput(int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'backtest_input');
        $ctx->telegram->editMessageText($chatId, $messageId, "Send: SYMBOL DAYS\ne.g. <code>BTCUSDT 30</code>", Keyboards::cancel('backtest'));
    }

    public static function run(int $chatId, int $userId, string $text, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        $parts = preg_split('/\s+/', trim($text));
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            $ctx->telegram->sendMessage($chatId, 'Format: SYMBOL DAYS, e.g. BTCUSDT 30');
            return;
        }

        $symbol = strtoupper($parts[0]);
        $days = min((int) $parts[1], self::MAX_DAYS);

        $status = $ctx->telegram->sendMessage($chatId, "⏳ Running backtest for {$symbol} over {$days} days...");
        $params = SettingsService::getAll();

        try {
            $report = $ctx->backtester->run($symbol, $days, $params);
        } catch (\Throwable $e) {
            $ctx->telegram->sendMessage($chatId, "Backtest failed: " . $e->getMessage());
            return;
        }

        if ($report['total_signals'] === 0) {
            $ctx->telegram->sendMessage($chatId, "🧪 {$symbol} - {$days}D\n\nNo qualifying signals found in this period.");
            return;
        }

        $ctx->telegram->sendMessage(
            $chatId,
            "🧪 <b>Backtest Result</b> - {$symbol} ({$days}D)\n\n"
            . "Total Signals: {$report['total_signals']}\n"
            . "Wins: {$report['wins']}   Losses: {$report['losses']}\n"
            . "Win Rate: {$report['win_rate']}%\n"
            . "Average R:R: 1:{$report['avg_rr']}\n"
            . "Average Score: {$report['avg_score']}%\n"
            . "Profit Factor: {$report['profit_factor']}\n"
            . "Max Drawdown: {$report['max_drawdown_r']}R"
        );
    }
}

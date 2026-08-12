<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\SignalService;
use App\Services\StatisticsService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class StatsHandler
{
    private const PAGE_SIZE = 10;

    public static function showStatistics(int $chatId, int $messageId, BotContext $ctx): void
    {
        $overall = SignalService::dashboardStats();
        $today = StatisticsService::refreshToday();

        $text = "📈 <b>Statistics</b>\n\n"
            . "<b>Today</b>\n"
            . "Signals: {$today['total_signals']}\n"
            . "TP1: {$today['tp1_count']}  TP2: {$today['tp2_count']}  TP3: {$today['tp3_count']}\n"
            . "Stopped: {$today['stopped_count']}  Risk Free: {$today['risk_free_count']}  Cancelled: {$today['cancelled_count']}\n"
            . "Win Rate: {$today['win_rate']}%\n\n"
            . "<b>All Time</b>\n"
            . "Total Signals: {$overall['total_signals']}\n"
            . "Winning Signals: {$overall['winning_signals']}\n"
            . "Stopped Signals: {$overall['stopped_signals']}\n"
            . "Win Rate: {$overall['win_rate']}%\n"
            . "Average R:R: 1:{$overall['avg_rr']}\n"
            . "Average Score: {$overall['avg_score']}%";

        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::statistics());
    }

    public static function showHistory(int $chatId, int $messageId, BotContext $ctx): void
    {
        $signals = SignalService::listHistory(self::PAGE_SIZE, 0);
        $text = $signals === [] ? "🗂 <b>Signal History</b>\n\nNo signals yet." : '🗂 <b>Signal History</b>';
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::history($signals, 0));
    }

    public static function paginateHistory(int $page, int $chatId, int $messageId, BotContext $ctx): void
    {
        $signals = SignalService::listHistory(self::PAGE_SIZE, $page * self::PAGE_SIZE);
        $ctx->telegram->editMessageText($chatId, $messageId, '🗂 <b>Signal History</b>', Keyboards::history($signals, $page));
    }
}

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

        $text = "📈 <b>آمار</b>\n\n"
            . "<b>امروز</b>\n"
            . "سیگنال‌ها: {$today['total_signals']}\n"
            . "TP1: {$today['tp1_count']}  TP2: {$today['tp2_count']}  TP3: {$today['tp3_count']}\n"
            . "استاپ‌خورده: {$today['stopped_count']}  ریسک‌فری: {$today['risk_free_count']}  لغوشده: {$today['cancelled_count']}\n"
            . "نرخ برد: {$today['win_rate']}%\n\n"
            . "<b>کل زمان</b>\n"
            . "کل سیگنال‌ها: {$overall['total_signals']}\n"
            . "سیگنال‌های برنده: {$overall['winning_signals']}\n"
            . "سیگنال‌های استاپ‌خورده: {$overall['stopped_signals']}\n"
            . "نرخ برد: {$overall['win_rate']}%\n"
            . "میانگین ریسک‌ریوارد: 1:{$overall['avg_rr']}\n"
            . "میانگین امتیاز: {$overall['avg_score']}%";

        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::statistics());
    }

    public static function showHistory(int $chatId, int $messageId, BotContext $ctx): void
    {
        $signals = SignalService::listHistory(self::PAGE_SIZE, 0);
        $text = $signals === [] ? "🗂 <b>تاریخچه سیگنال‌ها</b>\n\nهنوز سیگنالی وجود ندارد." : '🗂 <b>تاریخچه سیگنال‌ها</b>';
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::history($signals, 0));
    }

    public static function paginateHistory(int $page, int $chatId, int $messageId, BotContext $ctx): void
    {
        $signals = SignalService::listHistory(self::PAGE_SIZE, $page * self::PAGE_SIZE);
        $ctx->telegram->editMessageText($chatId, $messageId, '🗂 <b>تاریخچه سیگنال‌ها</b>', Keyboards::history($signals, $page));
    }
}

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
        $text = "🧪 <b>بک‌تست</b>\n\n"
            . 'موتور امتیازدهی/ریسک زنده را کندل‌به‌کندل روی داده‌های 5 دقیقه‌ای گذشته '
            . '(بازنمونه‌گیری‌شده به 15M/1H/4H) تا سقف ' . self::MAX_DAYS . " روز بازپخش می‌کند.\n\n"
            . 'توجه: این یک تخمین برای پژوهش استراتژی است، نه شبیه‌سازی دقیق تیک‌به‌تیک.';
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::backtest());
    }

    public static function startInput(int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'backtest_input');
        $ctx->telegram->editMessageText($chatId, $messageId, "بفرستید: نماد تعداد‌روز\nمثال: <code>BTCUSDT 30</code>", Keyboards::cancel('backtest'));
    }

    public static function run(int $chatId, int $userId, string $text, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        $parts = preg_split('/\s+/', trim($text));
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            $ctx->telegram->sendMessage($chatId, 'فرمت درست: نماد تعداد‌روز، مثال: BTCUSDT 30');
            return;
        }

        $symbol = strtoupper($parts[0]);
        $days = min((int) $parts[1], self::MAX_DAYS);

        $status = $ctx->telegram->sendMessage($chatId, "⏳ در حال اجرای بک‌تست برای {$symbol} طی {$days} روز...");
        $params = SettingsService::getAll();

        try {
            $report = $ctx->backtester->run($symbol, $days, $params);
        } catch (\Throwable $e) {
            $ctx->telegram->sendMessage($chatId, 'بک‌تست ناموفق بود: ' . $e->getMessage());
            return;
        }

        if ($report['total_signals'] === 0) {
            $ctx->telegram->sendMessage($chatId, "🧪 {$symbol} - {$days} روز\n\nدر این بازه هیچ سیگنال واجد شرایطی یافت نشد.");
            return;
        }

        $ctx->telegram->sendMessage(
            $chatId,
            "🧪 <b>نتیجه بک‌تست</b> - {$symbol} ({$days} روز)\n\n"
            . "کل سیگنال‌ها: {$report['total_signals']}\n"
            . "برد: {$report['wins']}   باخت: {$report['losses']}\n"
            . "نرخ برد: {$report['win_rate']}%\n"
            . "میانگین ریسک‌ریوارد: 1:{$report['avg_rr']}\n"
            . "میانگین امتیاز: {$report['avg_score']}%\n"
            . "ضریب سود: {$report['profit_factor']}\n"
            . "حداکثر افت سرمایه: {$report['max_drawdown_r']}R"
        );
    }
}

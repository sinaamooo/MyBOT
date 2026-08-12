<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\LogService;
use App\Services\SettingsService;
use App\Services\SignalService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

/**
 * Note on "Scanner control" in the PHP/cron edition: there is no long-lived
 * process to start/pause/stop like the Python version's asyncio task - the
 * cron schedule itself (cPanel Cron Jobs) determines *when* cron/scanner.php
 * runs. These buttons instead flip the `scanner_running` flag that
 * Scanner::scanOnce() checks on every cron tick, so "Stop" reliably means
 * "no new signals will be generated," effective on the next tick.
 */
final class DashboardHandler
{
    public static function text(): string
    {
        $stats = SignalService::dashboardStats();
        $params = SettingsService::getAll();
        $running = !empty($params['scanner_running']);
        $icon = $running ? '🟢 در حال اجرا' : '🔴 متوقف';

        return "🖥 <b>داشبورد</b>\n\n"
            . "وضعیت ربات: 🟢 آنلاین\n"
            . "اسکنر: {$icon}\n"
            . 'آخرین اسکن: ' . ($params['last_scan_at'] ?: '-') . "\n"
            . 'اسکن بعدی: ' . ($params['next_scan_at'] ?: '-') . "\n"
            . (!empty($params['last_scan_error']) ? "⚠️ آخرین خطا: {$params['last_scan_error']}\n" : '')
            . "\n"
            . "کل سیگنال‌ها: {$stats['total_signals']}\n"
            . "سیگنال‌های فعال: {$stats['active_signals']}\n"
            . "سیگنال‌های امروز: {$stats['today_signals']}\n"
            . "سیگنال‌های برنده: {$stats['winning_signals']}\n"
            . "سیگنال‌های استاپ‌خورده: {$stats['stopped_signals']}\n\n"
            . "TP1: {$stats['tp1_count']}  TP2: {$stats['tp2_count']}  TP3: {$stats['tp3_count']}\n"
            . "ریسک‌فری: {$stats['risk_free_count']}\n\n"
            . "نرخ برد: {$stats['win_rate']}%\n"
            . "میانگین ریسک‌ریوارد: 1:{$stats['avg_rr']}\n"
            . "میانگین امتیاز: {$stats['avg_score']}%";
    }

    public static function show(int $chatId, int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        $ctx->telegram->editMessageText($chatId, $messageId, self::text(), Keyboards::dashboard(!empty($params['scanner_running'])));
    }

    public static function control(string $action, int $chatId, int $messageId, BotContext $ctx): void
    {
        $running = $action !== 'stop';
        SettingsService::set('scanner_running', $running);
        LogService::log('INFO', 'scanner', $running ? "Scanner marked running ({$action})" : 'Scanner stopped');

        try {
            $ctx->telegram->editMessageText($chatId, $messageId, self::text(), Keyboards::dashboard($running));
        } catch (\Throwable) {
            // message may not have been the dashboard view (e.g. pressed from main menu) - ignore
        }
    }

    public static function startScannerCmd(int $chatId, BotContext $ctx): void
    {
        SettingsService::set('scanner_running', true);
        LogService::log('INFO', 'scanner', 'Scanner marked running (/start_scanner)');
        $ctx->telegram->sendMessage($chatId, 'اسکنر: در حال اجرا 🟢', Keyboards::mainMenu());
    }

    public static function stopScannerCmd(int $chatId, BotContext $ctx): void
    {
        SettingsService::set('scanner_running', false);
        LogService::log('INFO', 'scanner', 'Scanner stopped (/stop_scanner)');
        $ctx->telegram->sendMessage($chatId, 'اسکنر: متوقف شد 🔴', Keyboards::mainMenu());
    }
}

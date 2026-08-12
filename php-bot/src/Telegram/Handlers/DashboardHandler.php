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
        $icon = $running ? '🟢 RUNNING' : '🔴 STOPPED';

        return "🖥 <b>Dashboard</b>\n\n"
            . "Bot Status: 🟢 ONLINE\n"
            . "Scanner: {$icon}\n"
            . 'Last Scan: ' . ($params['last_scan_at'] ?: '-') . "\n"
            . 'Next Scan: ' . ($params['next_scan_at'] ?: '-') . "\n"
            . (!empty($params['last_scan_error']) ? "⚠️ Last error: {$params['last_scan_error']}\n" : '')
            . "\n"
            . "Total Signals: {$stats['total_signals']}\n"
            . "Active Signals: {$stats['active_signals']}\n"
            . "Today Signals: {$stats['today_signals']}\n"
            . "Winning Signals: {$stats['winning_signals']}\n"
            . "Stopped Signals: {$stats['stopped_signals']}\n\n"
            . "TP1: {$stats['tp1_count']}  TP2: {$stats['tp2_count']}  TP3: {$stats['tp3_count']}\n"
            . "Risk Free: {$stats['risk_free_count']}\n\n"
            . "Win Rate: {$stats['win_rate']}%\n"
            . "Average R:R: 1:{$stats['avg_rr']}\n"
            . "Average Score: {$stats['avg_score']}%";
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
        $ctx->telegram->sendMessage($chatId, 'Scanner: RUNNING', Keyboards::mainMenu());
    }

    public static function stopScannerCmd(int $chatId, BotContext $ctx): void
    {
        SettingsService::set('scanner_running', false);
        LogService::log('INFO', 'scanner', 'Scanner stopped (/stop_scanner)');
        $ctx->telegram->sendMessage($chatId, 'Scanner: STOPPED', Keyboards::mainMenu());
    }
}

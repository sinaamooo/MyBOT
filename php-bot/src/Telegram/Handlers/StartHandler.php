<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminService;
use App\Services\SignalService;
use App\Services\StatisticsService;
use App\Telegram\BotContext;

final class StartHandler
{
    private const WELCOME = "👋 Welcome to the Futures Signal Bot.\n\n"
        . "This bot scans the market and publishes LONG/SHORT futures signals to "
        . "the configured channel. It never places real trades.\n\n"
        . "⚠️ Educational / Signal Only - manage your own risk.\n\n"
        . 'Commands: /help /status /signals /stats';

    private const HELP = "<b>Commands</b>\n"
        . "/start - welcome message\n"
        . "/help - this message\n"
        . "/status - bot &amp; scanner status\n"
        . "/signals - currently active signals\n"
        . "/stats - today's statistics\n\n"
        . 'Admins: /admin - open the control panel';

    public static function start(int $chatId, int $userId, BotContext $ctx): void
    {
        $text = self::WELCOME;
        if (AdminService::isAdmin($userId)) {
            $text .= "\n\n🔐 You are an admin. Send /admin to open the control panel.";
        }
        $ctx->telegram->sendMessage($chatId, $text);
    }

    public static function help(int $chatId, BotContext $ctx): void
    {
        $ctx->telegram->sendMessage($chatId, self::HELP);
    }

    public static function status(int $chatId, BotContext $ctx): void
    {
        $params = \App\Services\SettingsService::getAll();
        $active = SignalService::countActive();
        $state = empty($params['scanner_running']) ? 'STOPPED' : 'RUNNING';
        $ctx->telegram->sendMessage(
            $chatId,
            "🖥 Bot: 🟢 ONLINE\n📡 Scanner: {$state}\n📋 Active signals: {$active}"
        );
    }

    public static function signals(int $chatId, BotContext $ctx): void
    {
        $signals = SignalService::getActive();
        if ($signals === []) {
            $ctx->telegram->sendMessage($chatId, 'No active signals right now.');
            return;
        }
        $lines = array_map(
            fn($s) => "• {$s['symbol']} {$s['side']} - {$s['status']} (score " . round((float) $s['score']) . ')',
            array_slice($signals, 0, 20)
        );
        $ctx->telegram->sendMessage($chatId, "📋 <b>Active Signals</b>\n\n" . implode("\n", $lines));
    }

    public static function stats(int $chatId, BotContext $ctx): void
    {
        $stats = StatisticsService::refreshToday();
        $ctx->telegram->sendMessage(
            $chatId,
            "📈 <b>Today</b>\n\n"
            . "Signals: {$stats['total_signals']}\n"
            . "TP1: {$stats['tp1_count']}  TP2: {$stats['tp2_count']}  TP3: {$stats['tp3_count']}\n"
            . "Stopped: {$stats['stopped_count']}  Risk Free: {$stats['risk_free_count']}\n"
            . "Win Rate: {$stats['win_rate']}%"
        );
    }

    public static function denied(int $chatId, BotContext $ctx): void
    {
        $ctx->telegram->sendMessage($chatId, '⛔ Access denied. This panel is for admins only.');
    }

    public static function sigDetails(string $callbackQueryId, int $userId, int $signalId, BotContext $ctx): void
    {
        $signal = SignalService::get($signalId);
        if ($signal === null) {
            $ctx->telegram->answerCallbackQuery($callbackQueryId, 'Signal not found.', true);
            return;
        }
        $reasons = $signal['reasons'] ? json_decode((string) $signal['reasons'], true) : [];
        $text = "📋 {$signal['symbol']} {$signal['side']}\n"
            . "Score: " . round((float) $signal['score']) . "%  |  Risk: {$signal['risk_score']}  |  RR 1:{$signal['rr']}\n"
            . "Entry: {$signal['entry']}\nSL: {$signal['stop_loss']}\n"
            . "TP1: {$signal['tp1']}  TP2: {$signal['tp2']}  TP3: {$signal['tp3']}\n"
            . "Status: {$signal['status']}\n\n"
            . "Reasons:\n" . implode("\n", array_map(fn($r) => "• {$r}", $reasons));

        $sent = $ctx->telegram->sendMessage($userId, $text);
        if ($sent !== null) {
            $ctx->telegram->answerCallbackQuery($callbackQueryId, 'Sent to your DM ✅');
        } else {
            $ctx->telegram->answerCallbackQuery($callbackQueryId, mb_substr($text, 0, 200), true);
        }
    }
}

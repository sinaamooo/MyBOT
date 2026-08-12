<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminStateService;
use App\Services\SettingsService;
use App\Services\SignalService;
use App\Services\SymbolService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class SignalControlHandler
{
    private const DEFAULT_TEST_SYMBOL = 'BTCUSDT';

    public static function show(int $chatId, int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        $enabled = !empty($params['signals_enabled']);
        $text = "📡 <b>Signal Control</b>\n\nSignals: " . ($enabled ? '🟢 ENABLED' : '🔴 DISABLED');
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::signalControl($enabled));
    }

    public static function toggle(bool $enable, int $chatId, int $messageId, BotContext $ctx): void
    {
        SettingsService::set('signals_enabled', $enable);
        self::show($chatId, $messageId, $ctx);
    }

    public static function startManual(string $side, int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'manual_signal_symbol', ['side' => $side]);
        $ctx->telegram->editMessageText($chatId, $messageId, "Send the symbol for a manual {$side} signal (e.g. BTCUSDT):", Keyboards::cancel('signal_control'));
    }

    public static function receiveManualSymbol(int $chatId, int $userId, string $text, array $context, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        $side = $context['side'] ?? 'LONG';
        $symbol = strtoupper(trim($text));

        $params = SettingsService::getAll();
        $candidate = $ctx->engine->manualCandidate($symbol, $side, $params);
        if ($candidate === null) {
            $ctx->telegram->sendMessage($chatId, "Could not fetch market data for {$symbol}.");
            return;
        }
        $ctx->scanner->publishCandidate($candidate, $params, isManual: true);
        $ctx->telegram->sendMessage($chatId, "✅ Manual {$side} signal published for {$symbol}.");
    }

    public static function test(int $chatId, ?int $messageId, BotContext $ctx): void
    {
        $params = SettingsService::getAll();
        $symbols = SymbolService::listEnabled();
        $symbol = $symbols[0] ?? self::DEFAULT_TEST_SYMBOL;

        $candidate = $ctx->engine->manualCandidate($symbol, 'LONG', $params);
        if ($candidate === null) {
            $ctx->telegram->sendMessage($chatId, "Failed to fetch market data for {$symbol}.");
            return;
        }
        $ctx->scanner->publishCandidate($candidate, $params, isTest: true);
        $ctx->telegram->sendMessage($chatId, 'Test signal sent ✅');
    }

    public static function showActive(int $chatId, int $messageId, BotContext $ctx): void
    {
        $signals = SignalService::getActive();
        $text = $signals === []
            ? "📋 <b>Active Signals</b>\n\nNo active signals."
            : "📋 <b>Active Signals</b>\n\nTap one for details.";
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::activeSignals($signals));
    }

    public static function view(int $signalId, int $chatId, int $messageId, BotContext $ctx): void
    {
        $signal = SignalService::get($signalId);
        if ($signal === null) {
            $ctx->telegram->answerCallbackQuery('', 'Not found', true);
            return;
        }
        $reasons = $signal['reasons'] ? json_decode((string) $signal['reasons'], true) : [];
        $currentSl = $signal['current_sl'] ?? $signal['stop_loss'];
        $text = "🪙 {$signal['symbol']}  {$signal['side']}\n"
            . "Status: {$signal['status']}\n"
            . "Score: " . round((float) $signal['score']) . "%  Risk: {$signal['risk_score']}  RR 1:{$signal['rr']}\n\n"
            . "Entry: {$signal['entry']}\nSL: {$currentSl}\n"
            . "TP1: {$signal['tp1']}  TP2: {$signal['tp2']}  TP3: {$signal['tp3']}\n"
            . "Leverage: {$signal['leverage']}x\n\n"
            . "Reasons:\n" . implode("\n", array_map(fn($r) => "• {$r}", $reasons));
        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::signalDetail($signal));
    }

    public static function cancel(int $signalId, int $chatId, int $messageId, BotContext $ctx): void
    {
        $ctx->monitor->cancel($signalId);
        self::showActive($chatId, $messageId, $ctx);
    }

    public static function close(int $signalId, int $chatId, int $messageId, BotContext $ctx): void
    {
        $ctx->monitor->manualClose($signalId);
        self::showActive($chatId, $messageId, $ctx);
    }
}

<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminStateService;
use App\Services\SymbolService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class SymbolsHandler
{
    private static function render(int $chatId, int $messageId, BotContext $ctx): void
    {
        $symbols = SymbolService::listAll();
        $ctx->telegram->editMessageText(
            $chatId,
            $messageId,
            "🪙 <b>Symbols</b>\n\nTap a symbol to enable/disable it, 🗑 to remove it.",
            Keyboards::symbols($symbols)
        );
    }

    public static function show(int $chatId, int $messageId, BotContext $ctx): void
    {
        self::render($chatId, $messageId, $ctx);
    }

    public static function toggle(string $symbol, int $chatId, int $messageId, BotContext $ctx): void
    {
        $current = array_values(array_filter(SymbolService::listAll(), fn($s) => $s['symbol'] === $symbol));
        if ($current !== []) {
            SymbolService::setEnabled($symbol, !$current[0]['enabled']);
        }
        self::render($chatId, $messageId, $ctx);
    }

    public static function remove(string $symbol, int $chatId, int $messageId, BotContext $ctx): void
    {
        SymbolService::removeSymbol($symbol);
        self::render($chatId, $messageId, $ctx);
    }

    public static function startAdd(int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'symbol_add');
        $ctx->telegram->editMessageText($chatId, $messageId, 'Send the symbol to add (e.g. PEPEUSDT):', Keyboards::cancel('symbols'));
    }

    public static function receiveNewSymbol(int $chatId, int $userId, string $text, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        $symbol = strtoupper(trim($text));
        SymbolService::addSymbol($symbol);
        $symbols = SymbolService::listAll();
        $ctx->telegram->sendMessage($chatId, "✅ {$symbol} added.", Keyboards::symbols($symbols));
    }
}

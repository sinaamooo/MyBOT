<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Services\AdminStateService;
use App\Services\LogService;
use App\Services\SettingsService;
use App\Services\SymbolService;
use App\Telegram\BotContext;
use App\Telegram\Keyboards;

final class SymbolsHandler
{
    private const PAGE_SIZE = 20;

    private static function render(int $page, int $chatId, int $messageId, BotContext $ctx, string $note = ''): void
    {
        $total = SymbolService::count();
        $pageOfSymbols = SymbolService::listPage(self::PAGE_SIZE, $page * self::PAGE_SIZE);
        $hasMore = ($page + 1) * self::PAGE_SIZE < $total;

        $text = "🪙 <b>ارزها</b> (مجموع: {$total})\n\n"
            . 'برای فعال/غیرفعال کردن یک ارز روش بزنید، 🗑 برای حذف.'
            . ($note !== '' ? "\n\n{$note}" : '');

        $ctx->telegram->editMessageText($chatId, $messageId, $text, Keyboards::symbols($pageOfSymbols, $page, $hasMore, $total));
    }

    public static function show(int $chatId, int $messageId, BotContext $ctx): void
    {
        self::render(0, $chatId, $messageId, $ctx);
    }

    public static function paginate(int $page, int $chatId, int $messageId, BotContext $ctx): void
    {
        self::render($page, $chatId, $messageId, $ctx);
    }

    public static function toggle(string $symbol, int $chatId, int $messageId, BotContext $ctx): void
    {
        $current = array_values(array_filter(SymbolService::listAll(), fn($s) => $s['symbol'] === $symbol));
        if ($current !== []) {
            SymbolService::setEnabled($symbol, !$current[0]['enabled']);
        }
        self::render(0, $chatId, $messageId, $ctx);
    }

    public static function remove(string $symbol, int $chatId, int $messageId, BotContext $ctx): void
    {
        SymbolService::removeSymbol($symbol);
        self::render(0, $chatId, $messageId, $ctx);
    }

    public static function startAdd(int $chatId, int $messageId, int $userId, BotContext $ctx): void
    {
        AdminStateService::set($userId, 'symbol_add');
        $ctx->telegram->editMessageText($chatId, $messageId, 'نماد را برای افزودن بفرستید (مثلاً PEPEUSDT):', Keyboards::cancel('symbols'));
    }

    public static function receiveNewSymbol(int $chatId, int $userId, string $text, BotContext $ctx): void
    {
        AdminStateService::clear($userId);
        $symbol = strtoupper(trim($text));
        SymbolService::addSymbol($symbol);

        $total = SymbolService::count();
        $pageOfSymbols = SymbolService::listPage(self::PAGE_SIZE, 0);
        $ctx->telegram->sendMessage($chatId, "✅ {$symbol} اضافه شد.", Keyboards::symbols($pageOfSymbols, 0, $total > self::PAGE_SIZE, $total));
    }

    /** Fetches Top-N USDT-M perpetuals by 24h volume from MEXC and adds any not already tracked. */
    public static function sync(int $chatId, int $messageId, BotContext $ctx): void
    {
        $topN = (int) SettingsService::get('symbol_top_n');
        try {
            $ranked = $ctx->exchange->listUsdtTickersRankedByVolume();
        } catch (\Throwable $e) {
            LogService::log('ERROR', 'symbols', 'Top-N sync failed: ' . $e->getMessage());
            self::render(0, $chatId, $messageId, $ctx, "⚠️ همگام‌سازی ناموفق بود: {$e->getMessage()}");
            return;
        }

        $added = SymbolService::syncTopByVolume($ranked, $topN);
        LogService::log('INFO', 'symbols', "Top-{$topN} sync added {$added} new symbol(s)");
        self::render(0, $chatId, $messageId, $ctx, "🔄 Top-{$topN} بر اساس حجم همگام‌سازی شد - {$added} ارز جدید اضافه شد.");
    }
}

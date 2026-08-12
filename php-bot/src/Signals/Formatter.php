<?php

declare(strict_types=1);

namespace App\Signals;

final class Formatter
{
    private const SIDE_EMOJI = ['LONG' => '🟢', 'SHORT' => '🔴'];

    private const UPDATE_TEMPLATES = [
        'TP1' => "✅ {symbol} {side}\n\nTP1 HIT 🎯\n\nProfit Target 1 reached.\nPrice: {price}",
        'TP2' => "🔥 TP2 HIT\n\n{symbol} {side}\n\nProfit Target 2 reached.\nPrice: {price}",
        'TP3' => "🏆 TP3 HIT\n\n{symbol} {side}\n\nTrade Completed.\nPrice: {price}",
        'STOPPED' => "❌ STOP LOSS HIT\n\n{symbol} {side}\n\nTrade Closed.\nPrice: {price}",
        'RISK_FREE' => "🛡 RISK FREE\n\n{symbol} {side}\n\nSL → ENTRY\nOriginal SL protected.",
        'TRAILING_STOP' => "🔧 TRAILING STOP UPDATED\n\n{symbol} {side}\n\nNew SL: {price}",
        'CANCELLED' => "⚪️ SIGNAL CANCELLED\n\n{symbol} {side}",
    ];

    /** @param array<string, mixed> $data */
    public static function formatSignalMessage(string $template, array $data): string
    {
        $data['side_emoji'] ??= self::SIDE_EMOJI[$data['side'] ?? ''] ?? '';
        return self::render($template, $data);
    }

    public static function formatUpdateMessage(string $updateType, string $symbol, string $side, ?float $price = null): string
    {
        $template = self::UPDATE_TEMPLATES[$updateType] ?? '{symbol} {side}: ' . $updateType;
        return self::render($template, ['symbol' => $symbol, 'side' => $side, 'price' => $price]);
    }

    /** @return string[] */
    public static function knownSignalPlaceholders(): array
    {
        return ['symbol', 'side', 'side_emoji', 'score', 'entry', 'stop_loss', 'tp1', 'tp2', 'tp3', 'leverage', 'rr', 'timeframe', 'trend', 'regime'];
    }

    /** Rejects a template that references a placeholder we don't provide (e.g. a typo). */
    public static function validateSignalTemplate(string $template): bool
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $template, $matches);
        $known = array_flip(self::knownSignalPlaceholders());
        foreach ($matches[1] as $name) {
            if (!isset($known[$name])) {
                return false;
            }
        }
        return true;
    }

    /** @param array<string, mixed> $data */
    private static function render(string $template, array $data): string
    {
        $search = [];
        $replace = [];
        foreach ($data as $key => $value) {
            $search[] = '{' . $key . '}';
            $replace[] = (string) $value;
        }
        return str_replace($search, $replace, $template);
    }
}

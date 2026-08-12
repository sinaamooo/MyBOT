<?php

declare(strict_types=1);

namespace App\Signals;

use App\Config;
use App\Telegram\TelegramApi;

final class Publisher
{
    private const TRADINGVIEW_PREFIX = [
        'mexc' => 'MEXC',
        'kucoinfutures' => 'KUCOIN',
        'binanceusdm' => 'BINANCE',
    ];

    public function __construct(
        private readonly TelegramApi $telegram,
        private readonly string $channelId,
    ) {
    }

    /** @return array<string, mixed> */
    public static function buildSignalKeyboard(string $symbol, int $signalId): array
    {
        $exchangeId = strtolower(Config::env('EXCHANGE_ID', 'mexc') ?? 'mexc');
        $prefix = self::TRADINGVIEW_PREFIX[$exchangeId] ?? strtoupper($exchangeId);
        $chartUrl = "https://www.tradingview.com/chart/?symbol={$prefix}%3A{$symbol}.P";

        return [
            'inline_keyboard' => [[
                ['text' => '📊 Chart', 'url' => $chartUrl],
                ['text' => '📋 Details', 'callback_data' => "sig_details:{$signalId}"],
            ]],
        ];
    }

    public function publishSignal(string $text, string $symbol, int $signalId): ?int
    {
        $keyboard = self::buildSignalKeyboard($symbol, $signalId);
        $message = $this->telegram->sendMessage($this->channelId, $text, $keyboard);
        return isset($message['message_id']) ? (int) $message['message_id'] : null;
    }

    public function publishUpdate(string $text): void
    {
        $this->telegram->sendMessage($this->channelId, $text);
    }
}

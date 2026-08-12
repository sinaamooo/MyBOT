<?php

declare(strict_types=1);

namespace App\Signals;

use App\Database;
use App\Exchange\MexcClient;
use App\Services\LogService;
use App\Services\SignalService;
use App\Support\Num;

/**
 * Watches every ACTIVE/TP1/TP2/RISK_FREE signal and reacts to price: TP
 * hits, Risk-Free (SL -> Entry), Trailing Stop, and Stop-Loss closes.
 * Invoked once per cron/monitor.php run.
 */
final class Monitor
{
    private const TP_SEQUENCE = [['tp1', 'TP1'], ['tp2', 'TP2'], ['tp3', 'TP3']];

    public function __construct(
        private readonly MexcClient $exchange,
        private readonly Publisher $publisher,
    ) {
    }

    public function checkOnce(array $params): void
    {
        $signals = SignalService::getActive();
        foreach ($signals as $signal) {
            try {
                $ticker = $this->exchange->getTicker($signal['symbol']);
                if ($ticker['last'] === null) {
                    continue;
                }
                $this->evaluate($signal, (float) $ticker['last'], $params);
            } catch (\Throwable $e) {
                LogService::log('WARNING', 'trade_monitor', "Monitor failed for {$signal['symbol']}#{$signal['id']}: " . $e->getMessage());
            }
        }
    }

    /** @param array<string, mixed> $signal */
    private function evaluate(array $signal, float $price, array $params): void
    {
        $isLong = $signal['side'] === 'LONG';

        foreach (self::TP_SEQUENCE as [$field, $label]) {
            if ($signal["{$field}_hit_at"] !== null) {
                continue;
            }
            $target = (float) $signal[$field];
            $reached = $isLong ? $price >= $target : $price <= $target;
            if (!$reached) {
                break; // targets are monotonic - can't skip ahead
            }

            $now = Database::now();

            if ($label === 'TP3') {
                SignalService::update((int) $signal['id'], ['tp3_hit_at' => $now]);
                SignalService::close((int) $signal['id'], 'TP3', $price);
                $this->publisher->publishUpdate(Formatter::formatUpdateMessage('TP3', $signal['symbol'], $signal['side'], $price), self::replyId($signal));
                LogService::log('INFO', 'signals', "{$signal['symbol']} {$signal['side']} TP3 hit - trade completed");
                return;
            }

            $status = $label === 'TP1' ? 'TP1' : 'TP2';
            SignalService::update((int) $signal['id'], ["{$field}_hit_at" => $now, 'status' => $status]);
            $this->publisher->publishUpdate(Formatter::formatUpdateMessage($label, $signal['symbol'], $signal['side'], $price), self::replyId($signal));
            LogService::log('INFO', 'signals', "{$signal['symbol']} {$signal['side']} {$label} hit @ {$price}");
            $signal["{$field}_hit_at"] = $now;

            if ($label === 'TP1' && (!empty($params['risk_free_enabled']) || !empty($params['trailing_stop_enabled'])) && $signal['risk_free_at'] === null) {
                SignalService::update((int) $signal['id'], ['current_sl' => $signal['entry'], 'risk_free_at' => $now, 'status' => 'RISK_FREE']);
                $this->publisher->publishUpdate(Formatter::formatUpdateMessage('RISK_FREE', $signal['symbol'], $signal['side']), self::replyId($signal));
                LogService::log('INFO', 'signals', "{$signal['symbol']} {$signal['side']} moved to risk-free (SL -> entry)");
                $signal['current_sl'] = $signal['entry'];
            }

            if ($label === 'TP2' && !empty($params['trailing_stop_enabled'])) {
                SignalService::update((int) $signal['id'], ['current_sl' => $signal['tp1']]);
                $this->publisher->publishUpdate(Formatter::formatUpdateMessage('TRAILING_STOP', $signal['symbol'], $signal['side'], (float) $signal['tp1']), self::replyId($signal));
                LogService::log('INFO', 'signals', "{$signal['symbol']} {$signal['side']} trailing stop -> TP1");
                $signal['current_sl'] = $signal['tp1'];
            }
        }

        $currentSl = $signal['current_sl'] !== null ? (float) $signal['current_sl'] : (float) $signal['stop_loss'];
        $slHit = $isLong ? $price <= $currentSl : $price >= $currentSl;
        if ($slHit) {
            SignalService::close((int) $signal['id'], 'STOPPED', $price);
            $this->publisher->publishUpdate(Formatter::formatUpdateMessage('STOPPED', $signal['symbol'], $signal['side'], $price), self::replyId($signal));
            LogService::log('INFO', 'signals', "{$signal['symbol']} {$signal['side']} stop loss hit @ {$price}");
        }
    }

    /** @return array<string, mixed>|null */
    public function manualClose(int $signalId): ?array
    {
        $signal = SignalService::get($signalId);
        if ($signal === null) {
            return null;
        }
        $ticker = $this->exchange->getTicker($signal['symbol']);
        $price = (float) $ticker['last'];
        $closed = SignalService::close($signalId, 'COMPLETED', $price);
        $this->publisher->publishUpdate("⚪️ بسته شد (دستی)\n\n{$signal['symbol']} {$signal['side']}\nقیمت بسته شدن: " . Num::price($price), self::replyId($signal));
        LogService::log('INFO', 'signals', "{$signal['symbol']}#{$signalId} manually closed @ {$price}");
        return $closed;
    }

    /** @return array<string, mixed>|null */
    public function cancel(int $signalId): ?array
    {
        SignalService::update($signalId, ['status' => 'CANCELLED', 'closed_at' => Database::now()]);
        $signal = SignalService::get($signalId);
        if ($signal !== null) {
            $this->publisher->publishUpdate(Formatter::formatUpdateMessage('CANCELLED', $signal['symbol'], $signal['side']), self::replyId($signal));
            LogService::log('INFO', 'signals', "{$signal['symbol']}#{$signalId} cancelled");
        }
        return $signal;
    }

    /** @param array<string, mixed> $signal */
    private static function replyId(array $signal): ?int
    {
        return !empty($signal['channel_message_id']) ? (int) $signal['channel_message_id'] : null;
    }
}

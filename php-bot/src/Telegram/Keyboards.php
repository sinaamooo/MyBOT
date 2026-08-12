<?php

declare(strict_types=1);

namespace App\Telegram;

final class Keyboards
{
    /** @return array<string, mixed> */
    private static function kb(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }

    private static function backRow(string $target = 'root'): array
    {
        return [['text' => '⬅️ Back', 'callback_data' => "menu:{$target}"]];
    }

    public static function mainMenu(): array
    {
        return self::kb([
            [['text' => '🖥 Dashboard', 'callback_data' => 'menu:dashboard']],
            [['text' => '📡 Signal Control', 'callback_data' => 'menu:signal_control']],
            [
                ['text' => '🪙 Symbols', 'callback_data' => 'menu:symbols'],
                ['text' => '📊 Indicators', 'callback_data' => 'menu:indicators'],
            ],
            [
                ['text' => '⚙️ Settings', 'callback_data' => 'menu:settings'],
                ['text' => '📈 Statistics', 'callback_data' => 'menu:statistics'],
            ],
            [
                ['text' => '📋 Active Signals', 'callback_data' => 'menu:active'],
                ['text' => '🗂 Signal History', 'callback_data' => 'menu:history'],
            ],
            [
                ['text' => '📝 Message Templates', 'callback_data' => 'menu:templates'],
                ['text' => '🧪 Backtest', 'callback_data' => 'menu:backtest'],
            ],
            [
                ['text' => '👥 Users', 'callback_data' => 'menu:users'],
                ['text' => '🔐 Admins', 'callback_data' => 'menu:admins'],
            ],
            [['text' => '📜 Logs', 'callback_data' => 'menu:logs']],
            [
                ['text' => '🔄 Restart Scanner', 'callback_data' => 'scanner:restart'],
                ['text' => '⛔ Stop Scanner', 'callback_data' => 'scanner:stop'],
            ],
        ]);
    }

    public static function dashboard(bool $running): array
    {
        $toggle = $running
            ? ['text' => '⛔ Stop Scanner', 'callback_data' => 'scanner:stop']
            : ['text' => '▶️ Start Scanner', 'callback_data' => 'scanner:start'];
        return self::kb([
            [$toggle, ['text' => '🔄 Restart', 'callback_data' => 'scanner:restart']],
            [['text' => '🔃 Refresh', 'callback_data' => 'menu:dashboard']],
            self::backRow(),
        ]);
    }

    public static function signalControl(bool $signalsEnabled): array
    {
        $toggle = $signalsEnabled
            ? ['text' => '🔴 Disable Signals', 'callback_data' => 'signals:disable']
            : ['text' => '🟢 Enable Signals', 'callback_data' => 'signals:enable'];
        return self::kb([
            [$toggle],
            [
                ['text' => '🟢 Manual LONG', 'callback_data' => 'signals:manual:LONG'],
                ['text' => '🔴 Manual SHORT', 'callback_data' => 'signals:manual:SHORT'],
            ],
            [['text' => '🧪 Send Test Signal', 'callback_data' => 'signals:test']],
            self::backRow(),
        ]);
    }

    /** @param array<int, array<string, mixed>> $symbols */
    public static function symbols(array $symbols): array
    {
        $rows = [];
        foreach ($symbols as $s) {
            $dot = $s['enabled'] ? '🟢' : '🔴';
            $rows[] = [
                ['text' => "{$s['symbol']} {$dot}", 'callback_data' => "sym:toggle:{$s['symbol']}"],
                ['text' => '🗑', 'callback_data' => "sym:remove:{$s['symbol']}"],
            ];
        }
        $rows[] = [['text' => '➕ Add Symbol', 'callback_data' => 'sym:add']];
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $params */
    public static function indicators(array $params): array
    {
        $flags = [
            ['EMA', 'indicator_ema_enabled'],
            ['RSI', 'indicator_rsi_enabled'],
            ['MACD', 'indicator_macd_enabled'],
            ['ADX', 'indicator_adx_enabled'],
            ['Volume', 'indicator_volume_enabled'],
            ['Bollinger', 'indicator_bollinger_enabled'],
        ];
        $rows = [];
        foreach ($flags as [$label, $key]) {
            $dot = !empty($params[$key]) ? '🟢' : '🔴';
            $rows[] = [['text' => "{$label} {$dot}", 'callback_data' => "ind:toggle:{$key}"]];
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @return array<string, array<int, array{0:string,1:string}>> category => [[label, key], ...] */
    public static function settingsCategories(): array
    {
        return [
            'scoring' => [
                ['Min Score (LONG/SHORT)', 'min_score'],
                ['Watchlist Min Score', 'watchlist_min_score'],
                ['Min ADX', 'min_adx'],
                ['Volume Confirm Multiplier', 'volume_confirm_multiplier'],
                ['Weight: Trend', 'score_weight_trend'],
                ['Weight: EMA', 'score_weight_ema'],
                ['Weight: RSI', 'score_weight_rsi'],
                ['Weight: MACD', 'score_weight_macd'],
                ['Weight: ADX', 'score_weight_adx'],
                ['Weight: Volume', 'score_weight_volume'],
                ['Weight: Price Action', 'score_weight_price_action'],
                ['Weight: HTF Confirm', 'score_weight_htf'],
            ],
            'risk' => [
                ['ATR SL Multiplier', 'atr_sl_multiplier'],
                ['TP1 R Multiple', 'tp1_r_multiple'],
                ['TP2 R Multiple', 'tp2_r_multiple'],
                ['TP3 R Multiple', 'tp3_r_multiple'],
                ['Min Risk/Reward', 'min_rr'],
                ['Min Leverage', 'min_leverage'],
                ['Max Leverage', 'max_leverage'],
            ],
            'scanner' => [
                ['Scan Interval (seconds)', 'scan_interval_seconds'],
                ['Signal Cooldown (seconds)', 'signal_cooldown_seconds'],
                ['Max Active Signals', 'max_active_signals'],
                ['Max Daily Signals', 'max_daily_signals'],
                ['Max Daily Loss % (0=off)', 'max_daily_loss_percent'],
            ],
            'monitoring' => [
                ['Monitor Interval (seconds)', 'monitor_interval_seconds'],
                ['Risk Free Enabled', 'risk_free_enabled'],
                ['Trailing Stop Enabled', 'trailing_stop_enabled'],
            ],
        ];
    }

    public static function categoryTitles(): array
    {
        return [
            'scoring' => '🎯 Scoring & Thresholds',
            'risk' => '🛡 Risk & Leverage',
            'scanner' => '🔁 Scanner & Cooldown',
            'monitoring' => '👁 Monitoring',
        ];
    }

    public static function settingsMenu(): array
    {
        $titles = self::categoryTitles();
        $rows = [];
        foreach ($titles as $cat => $title) {
            $rows[] = [['text' => $title, 'callback_data' => "set:cat:{$cat}"]];
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $params */
    public static function settingsCategory(string $category, array $params): array
    {
        $rows = [];
        foreach (self::settingsCategories()[$category] as [$label, $key]) {
            $value = $params[$key] ?? null;
            if (is_bool($value)) {
                $display = $value ? '🟢 ON' : '🔴 OFF';
                $rows[] = [['text' => "{$label}: {$display}", 'callback_data' => "set:bool:{$key}"]];
            } else {
                $rows[] = [['text' => "{$label}: {$value}", 'callback_data' => "set:edit:{$key}"]];
            }
        }
        $rows[] = [['text' => '⬅️ Back', 'callback_data' => 'menu:settings']];
        return self::kb($rows);
    }

    public static function statistics(): array
    {
        return self::kb([[['text' => '🔃 Refresh', 'callback_data' => 'menu:statistics']], self::backRow()]);
    }

    /** @param array<int, array<string, mixed>> $signals */
    public static function activeSignals(array $signals): array
    {
        $rows = [];
        foreach ($signals as $s) {
            $rows[] = [['text' => "{$s['symbol']} {$s['side']} ({$s['status']})", 'callback_data' => "sig:view:{$s['id']}"]];
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<string, mixed> $signal */
    public static function signalDetail(array $signal): array
    {
        return self::kb([
            [
                ['text' => '❌ Cancel', 'callback_data' => "sig:cancel:{$signal['id']}"],
                ['text' => '🔒 Close Now', 'callback_data' => "sig:close:{$signal['id']}"],
            ],
            [['text' => '⬅️ Back', 'callback_data' => 'menu:active']],
        ]);
    }

    /** @param array<int, array<string, mixed>> $signals */
    public static function history(array $signals, int $page): array
    {
        $rows = [];
        foreach ($signals as $s) {
            $result = $s['result'] ?? '-';
            $rows[] = [['text' => "{$s['symbol']} {$s['side']} {$s['status']} ({$result})", 'callback_data' => "sig:view:{$s['id']}"]];
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '⬅️ Prev', 'callback_data' => 'hist:page:' . ($page - 1)];
        }
        if (count($signals) === 10) {
            $nav[] = ['text' => 'Next ➡️', 'callback_data' => 'hist:page:' . ($page + 1)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    public static function templates(): array
    {
        return self::kb([
            [['text' => '✏️ Edit Signal Template', 'callback_data' => 'tpl:edit']],
            [['text' => '♻️ Reset to Default', 'callback_data' => 'tpl:reset']],
            self::backRow(),
        ]);
    }

    public static function users(int $page, bool $hasMore): array
    {
        $nav = [];
        if ($page > 0) {
            $nav[] = ['text' => '⬅️ Prev', 'callback_data' => 'usr:page:' . ($page - 1)];
        }
        if ($hasMore) {
            $nav[] = ['text' => 'Next ➡️', 'callback_data' => 'usr:page:' . ($page + 1)];
        }
        $rows = $nav !== [] ? [$nav] : [];
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    /** @param array<int, array<string, mixed>> $admins */
    public static function admins(array $admins): array
    {
        $rows = [];
        foreach ($admins as $a) {
            $label = $a['label'] ?: $a['telegram_id'];
            $rows[] = [['text' => "🔐 {$label}", 'callback_data' => "adm:remove:{$a['telegram_id']}"]];
        }
        $rows[] = [['text' => '➕ Add Admin', 'callback_data' => 'adm:add']];
        $rows[] = self::backRow();
        return self::kb($rows);
    }

    public static function logs(): array
    {
        return self::kb([[['text' => '🔃 Refresh', 'callback_data' => 'menu:logs']], self::backRow()]);
    }

    public static function backtest(): array
    {
        return self::kb([[['text' => '▶️ Run Backtest', 'callback_data' => 'bt:start']], self::backRow()]);
    }

    public static function cancel(string $target = 'root'): array
    {
        return self::kb([self::backRow($target)]);
    }
}

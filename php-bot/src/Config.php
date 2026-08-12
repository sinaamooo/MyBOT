<?php

declare(strict_types=1);

namespace App;

/**
 * Secrets/environment (.env) + strategy defaults, mirroring config.py from
 * the Python version. Trading/strategy knobs are the fallback values used
 * until the Admin Panel writes an override into the `settings` table (see
 * Services\SettingsService) - every number here stays editable live.
 */
final class Config
{
    /** @var array<string, string> */
    private static array $env = [];
    private static bool $loaded = false;

    public static function load(?string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        $path ??= dirname(__DIR__) . '/.env';
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                $pos = strpos($line, '=');
                if ($pos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                $value = trim($value, "\"'");
                self::$env[$key] = $value;
            }
        }
        self::$loaded = true;
    }

    public static function env(string $key, ?string $default = null): ?string
    {
        self::load();
        $value = self::$env[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return $value;
    }

    public static function required(string $key): string
    {
        $value = self::env($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Missing required .env value: {$key}");
        }
        return $value;
    }

    /** @return int[] */
    public static function adminIds(): array
    {
        $raw = self::env('ADMIN_IDS', '') ?? '';
        $ids = [];
        foreach (explode(',', $raw) as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit(ltrim($part, '-'))) {
                $ids[] = (int) $part;
            }
        }
        return $ids;
    }

    public static function tradingMode(): string
    {
        $mode = strtolower(self::env('TRADING_MODE', 'paper') ?? 'paper');
        return in_array($mode, ['paper', 'signal_only'], true) ? $mode : 'paper';
    }

    // -- Watch-list & timeframes --------------------------------------------

    /** @return string[] */
    public static function defaultSymbols(): array
    {
        return [
            'BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT', 'XRPUSDT',
            'DOGEUSDT', 'ADAUSDT', 'AVAXUSDT', 'LINKUSDT', 'SUIUSDT',
            'TONUSDT', 'DOTUSDT', 'LTCUSDT', 'TRXUSDT', 'NEARUSDT',
        ];
    }

    public const TIMEFRAME_TREND = '4h';
    public const TIMEFRAME_CONFIRM = '1h';
    public const TIMEFRAME_SETUP = '15m';
    public const TIMEFRAME_ENTRY = '5m';

    /** @return string[] */
    public static function pipelineTimeframes(): array
    {
        return [self::TIMEFRAME_TREND, self::TIMEFRAME_CONFIRM, self::TIMEFRAME_SETUP, self::TIMEFRAME_ENTRY];
    }

    public static function defaultSignalMessageTemplate(): string
    {
        return "━━━━━━━━━━━━━━\n"
            . "🚨 سیگنال جدید فیوچرز\n\n"
            . "🪙 {symbol}\n"
            . "{side_emoji} {side}\n\n"
            . "📊 اطمینان: {score}%\n\n"
            . "🎯 ورود:\n{entry}\n\n"
            . "🛑 حد ضرر:\n{stop_loss}\n\n"
            . "🎯 TP1:\n{tp1}\n"
            . "🎯 TP2:\n{tp2}\n"
            . "🎯 TP3:\n{tp3}\n\n"
            . "⚡ اهرم پیشنهادی:\n{leverage}X\n\n"
            . "📈 ریسک/ریوارد:\n1 : {rr}\n\n"
            . "⏱ تایم‌فریم:\n{timeframe}\n\n"
            . "📊 روند:\n{trend}\n"
            . "🌐 رژیم بازار:\n{regime}\n"
            . "━━━━━━━━━━━━━━\n\n"
            . "💬 {quote}\n"
            . "━━━━━━━━━━━━━━\n\n"
            . "⚠️ فقط جنبه آموزشی/سیگنال دارد\n"
            . "ریسک خودتان را با دقت مدیریت کنید\n"
            . "━━━━━━━━━━━━━━";
    }

    /**
     * Flat registry of every tunable trading/strategy value. Doubles as the
     * schema the Admin Panel settings editor uses (value type inferred from
     * the default's PHP type). Runtime overrides live in the `settings` table.
     *
     * @return array<string, mixed>
     */
    public static function tradingDefaults(): array
    {
        return [
            // --- Scanner ---
            'scan_interval_seconds' => 240,
            'signal_cooldown_seconds' => 1800,
            'max_active_signals' => 5,
            'max_daily_signals' => 30,
            'max_daily_loss_percent' => 0.0,

            // --- Indicator periods ---
            'ema_fast' => 20,
            'ema_medium' => 50,
            'ema_slow' => 100,
            'ema_trend' => 200,
            'rsi_period' => 14,
            'macd_fast' => 12,
            'macd_slow' => 26,
            'macd_signal' => 9,
            'adx_period' => 14,
            'atr_period' => 14,
            'bb_period' => 20,
            'bb_std' => 2.0,
            'stoch_rsi_period' => 14,
            'stoch_k' => 3,
            'stoch_d' => 3,
            'volume_ma_period' => 20,

            // --- Indicator on/off switches ---
            'indicator_ema_enabled' => true,
            'indicator_rsi_enabled' => true,
            'indicator_macd_enabled' => true,
            'indicator_adx_enabled' => true,
            'indicator_volume_enabled' => true,
            'indicator_bollinger_enabled' => true,
            'indicator_smc_ob_enabled' => true,
            'indicator_fvg_enabled' => true,
            'indicator_liquidity_enabled' => true,
            'indicator_structure_enabled' => true,

            // --- Trend / filters ---
            'min_adx' => 20.0,
            'volume_confirm_multiplier' => 1.2,

            // --- Scoring weights ---
            'score_weight_trend' => 20,
            'score_weight_ema' => 15,
            'score_weight_rsi' => 15,
            'score_weight_macd' => 10,
            'score_weight_adx' => 10,
            'score_weight_volume' => 10,
            'score_weight_price_action' => 10,
            'score_weight_htf' => 10,
            'score_weight_smc_ob' => 10,
            'score_weight_fvg' => 8,
            'score_weight_liquidity' => 8,
            'score_weight_structure' => 10,

            // --- Score thresholds ---
            'min_score' => 75,
            'watchlist_min_score' => 65,

            // --- Market regime adjustments ---
            'ranging_confidence_multiplier' => 0.85,
            'high_volatility_atr_pct' => 0.05,
            'high_volatility_score_buffer' => 10,
            'low_volatility_atr_pct' => 0.008,

            // --- Risk / SL / TP ---
            'atr_sl_multiplier' => 1.5,
            'tp1_r_multiple' => 1.0,
            'tp2_r_multiple' => 2.0,
            'tp3_r_multiple' => 3.0,
            'min_rr' => 1.5,

            // --- Leverage suggestion (three independent tiers by ATR% volatility -
            // NEVER auto-executed, shown as a suggestion only. High leverage does
            // NOT reduce risk, it only changes margin needed for a given exposure -
            // see the disclaimer appended to every published signal.) ---
            'leverage_absolute_min' => 5,
            'leverage_absolute_max' => 150,
            'leverage_low_vol_max' => 150,     // ATR% <= leverage_low_vol_atr_pct
            'leverage_medium_vol_max' => 60,   // ATR% <= leverage_medium_vol_atr_pct
            'leverage_high_vol_max' => 30,     // otherwise
            'leverage_low_vol_atr_pct' => 0.015,
            'leverage_medium_vol_atr_pct' => 0.035,

            // --- Symbol universe (Top-N by 24h volume, synced from MEXC on demand
            // or automatically by the scanner - see Symbols panel) ---
            'symbol_auto_sync_enabled' => false,
            'symbol_top_n' => 100,

            // --- Pump Hunter (opt-in extra scoring bonus for volume-surge +
            // breakout + accelerating-momentum setups, tagged 🚀 in the message) ---
            'pump_hunter_enabled' => false,
            'pump_volume_multiplier' => 3.0,   // last candle volume vs its MA
            'pump_score_bonus' => 15,
            'pump_momentum_lookback' => 3,     // candles to measure EMA acceleration over

            // --- Monitoring ---
            'monitor_interval_seconds' => 60,
            'risk_free_enabled' => true,
            'trailing_stop_enabled' => true,

            // --- Master switches ---
            'signals_enabled' => true,
            'scanner_running' => false,

            // --- Extension points (disabled by default) ---
            'ai_layer_enabled' => false,
            'news_filter_enabled' => false,

            // --- Message template ---
            'signal_message_template' => self::defaultSignalMessageTemplate(),
            'signal_quote_enabled' => true,

            // --- Operational state (not strategy knobs, but PHP has no long-lived
            // process to hold these in memory like the Python version's Scanner
            // object did - persisted here via the same key/value settings store) ---
            'last_scan_at' => '',
            'next_scan_at' => '',
            'last_scan_error' => '',
        ];
    }
}

"""
Central configuration.

Secrets (tokens, keys, admin ids) come ONLY from environment variables / .env
via the pydantic `Settings` object below - never hardcode them.

Trading/strategy knobs live in `TRADING_DEFAULTS`. They are the fallback
values used the first time the bot runs; from that point on the Admin Panel
stores overrides in the `settings` DB table (see app/services/settings_service.py)
so every number in this file can be changed live, without a redeploy.
"""
from __future__ import annotations

from typing import Any

from pydantic import Field, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Secrets & environment - read from .env, never committed to git."""

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8", extra="ignore")

    telegram_bot_token: str = Field(alias="TELEGRAM_BOT_TOKEN")
    telegram_channel_id: str = Field(alias="TELEGRAM_CHANNEL_ID")
    admin_ids: str = Field(default="", alias="ADMIN_IDS")

    exchange_id: str = Field(default="mexc", alias="EXCHANGE_ID")
    exchange_api_key: str = Field(default="", alias="EXCHANGE_API_KEY")
    exchange_api_secret: str = Field(default="", alias="EXCHANGE_API_SECRET")

    database_url: str = Field(default="sqlite+aiosqlite:///./data/bot.db", alias="DATABASE_URL")

    trading_mode: str = Field(default="paper", alias="TRADING_MODE")

    log_level: str = Field(default="INFO", alias="LOG_LEVEL")

    @field_validator("trading_mode")
    @classmethod
    def _validate_mode(cls, v: str) -> str:
        v = v.lower().strip()
        if v not in {"paper", "signal_only"}:
            raise ValueError("TRADING_MODE must be 'paper' or 'signal_only' (live trading is not supported)")
        return v

    @property
    def admin_id_list(self) -> list[int]:
        return [int(x) for x in self.admin_ids.split(",") if x.strip()]


settings = Settings()


# ---------------------------------------------------------------------------
# Default watch-list. Editable at runtime via Admin Panel -> Symbols.
# ---------------------------------------------------------------------------
DEFAULT_SYMBOLS: list[str] = [
    "BTCUSDT", "ETHUSDT", "SOLUSDT", "BNBUSDT", "XRPUSDT",
    "DOGEUSDT", "ADAUSDT", "AVAXUSDT", "LINKUSDT", "SUIUSDT",
    "TONUSDT", "DOTUSDT", "LTCUSDT", "TRXUSDT", "NEARUSDT",
]

# Multi-timeframe roles used by the trend/scoring pipeline.
TIMEFRAME_TREND = "4h"
TIMEFRAME_CONFIRM = "1h"
TIMEFRAME_SETUP = "15m"
TIMEFRAME_ENTRY = "5m"
ALL_TIMEFRAMES = ["1m", "5m", "15m", "1h", "4h"]
PIPELINE_TIMEFRAMES = [TIMEFRAME_TREND, TIMEFRAME_CONFIRM, TIMEFRAME_SETUP, TIMEFRAME_ENTRY]

DEFAULT_SIGNAL_MESSAGE_TEMPLATE = (
    "━━━━━━━━━━━━━━\n"
    "🚨 NEW FUTURES SIGNAL\n\n"
    "🪙 {symbol}\n"
    "{side_emoji} {side}\n\n"
    "📊 Confidence: {score}%\n\n"
    "🎯 Entry:\n{entry}\n\n"
    "🛑 Stop Loss:\n{stop_loss}\n\n"
    "🎯 TP1:\n{tp1}\n"
    "🎯 TP2:\n{tp2}\n"
    "🎯 TP3:\n{tp3}\n\n"
    "⚡ Suggested Leverage:\n{leverage}X\n\n"
    "📈 Risk/Reward:\n1 : {rr}\n\n"
    "⏱ Timeframe:\n{timeframe}\n\n"
    "📊 Trend:\n{trend}\n"
    "🌐 Market Regime:\n{regime}\n"
    "━━━━━━━━━━━━━━\n\n"
    "⚠️ Educational / Signal Only\n"
    "Manage Risk Carefully\n"
    "━━━━━━━━━━━━━━"
)

# ---------------------------------------------------------------------------
# Flat registry of every tunable trading/strategy value. This dict is both
# the hard-coded default AND the schema the Admin Panel settings editor uses
# (value type is inferred from the default's Python type).
# Runtime overrides are stored in the `settings` table - see settings_service.
# ---------------------------------------------------------------------------
TRADING_DEFAULTS: dict[str, Any] = {
    # --- Scanner ---
    "scan_interval_seconds": 240,          # every ~4 minutes
    "signal_cooldown_seconds": 1800,       # 30 minutes per symbol after a signal closes/opens
    "max_active_signals": 5,
    "max_daily_signals": 30,
    "max_daily_loss_percent": 0.0,         # 0 = disabled

    # --- Indicator periods ---
    "ema_fast": 20,
    "ema_medium": 50,
    "ema_slow": 100,
    "ema_trend": 200,
    "rsi_period": 14,
    "macd_fast": 12,
    "macd_slow": 26,
    "macd_signal": 9,
    "adx_period": 14,
    "atr_period": 14,
    "bb_period": 20,
    "bb_std": 2.0,
    "stoch_rsi_period": 14,
    "stoch_k": 3,
    "stoch_d": 3,
    "volume_ma_period": 20,

    # --- Indicator on/off switches ---
    "indicator_ema_enabled": True,
    "indicator_rsi_enabled": True,
    "indicator_macd_enabled": True,
    "indicator_adx_enabled": True,
    "indicator_volume_enabled": True,
    "indicator_bollinger_enabled": True,

    # --- Trend / filters ---
    "min_adx": 20.0,
    "volume_confirm_multiplier": 1.2,

    # --- Scoring weights (LONG & SHORT share the same weight table) ---
    "score_weight_trend": 20,
    "score_weight_ema": 15,
    "score_weight_rsi": 15,
    "score_weight_macd": 10,
    "score_weight_adx": 10,
    "score_weight_volume": 10,
    "score_weight_price_action": 10,
    "score_weight_htf": 10,

    # --- Score thresholds ---
    "min_score": 75,
    "watchlist_min_score": 65,

    # --- Market regime adjustments ---
    "ranging_confidence_multiplier": 0.85,
    "high_volatility_atr_pct": 0.05,       # ATR / price above this => HIGH_VOLATILITY regime
    "high_volatility_score_buffer": 10,    # require min_score + buffer in high-vol regime
    "low_volatility_atr_pct": 0.008,       # ATR / price below this => LOW_VOLATILITY regime

    # --- Risk / SL / TP ---
    "atr_sl_multiplier": 1.5,
    "tp1_r_multiple": 1.0,
    "tp2_r_multiple": 2.0,
    "tp3_r_multiple": 3.0,
    "min_rr": 1.5,

    # --- Leverage suggestion (never used to auto-trade) ---
    "min_leverage": 15,
    "max_leverage": 25,
    "leverage_low_vol_atr_pct": 0.015,     # ATR/price <= this -> max leverage
    "leverage_medium_vol_atr_pct": 0.035,  # ATR/price <= this -> mid leverage

    # --- Monitoring ---
    "monitor_interval_seconds": 30,
    "risk_free_enabled": True,
    "trailing_stop_enabled": True,

    # --- Master switches ---
    "signals_enabled": True,
    "scanner_running": False,

    # --- Optional / future extension points (disabled by default) ---
    "ai_layer_enabled": False,
    "news_filter_enabled": False,

    # --- Message template ---
    "signal_message_template": DEFAULT_SIGNAL_MESSAGE_TEMPLATE,
}

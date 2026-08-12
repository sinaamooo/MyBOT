"""The LONG/SHORT scoring engine.

A signal is never generated from a single indicator. Eight weighted
components (each configurable in TRADING_DEFAULTS / Admin Panel -> Settings)
are combined into a 0-100 score per side:

    Trend (HTF)         Setup-TF EMA confirmation   Setup-TF RSI
    Setup-TF MACD       Setup-TF ADX strength       Entry-TF Volume
    Entry-TF Price Action                            1H Higher-TF confirmation

Timeframe roles (see config.py): 4H = Trend, 1H = Confirmation,
15M = Setup, 5M = Entry.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

import pandas as pd

from app.core.enums import MarketRegime, SignalSide, TrendDirection
from app.analysis.market_regime import detect_regime
from app.analysis.price_action import PriceActionResult, detect_price_action
from app.analysis.trend import detect_trend, ema_alignment
from config import TIMEFRAME_CONFIRM, TIMEFRAME_ENTRY, TIMEFRAME_SETUP, TIMEFRAME_TREND


@dataclass
class ScoringResult:
    decision: str  # "SIGNAL" | "WATCHLIST" | "NO_SIGNAL"
    side: SignalSide | None
    score: float               # regime-adjusted score of the winning side (what gets published)
    raw_long_score: float
    raw_short_score: float
    long_reasons: list[str] = field(default_factory=list)
    short_reasons: list[str] = field(default_factory=list)
    trend: TrendDirection = TrendDirection.NEUTRAL
    regime: MarketRegime = MarketRegime.RANGING
    price_action: PriceActionResult | None = None


def _clamp(value: float, weight: float) -> float:
    return max(0.0, min(weight, value))


def _score_side(indexed: dict[str, pd.DataFrame], pa: PriceActionResult, params: dict[str, Any], bullish: bool) -> tuple[float, list[str]]:
    reasons: list[str] = []
    total = 0.0

    trend_df = indexed[TIMEFRAME_TREND]
    setup_df = indexed[TIMEFRAME_SETUP]
    confirm_df = indexed[TIMEFRAME_CONFIRM]
    entry_df = indexed[TIMEFRAME_ENTRY]

    setup_last = setup_df.iloc[-1]
    entry_last = entry_df.iloc[-1]

    w_trend = params["score_weight_trend"]
    w_ema = params["score_weight_ema"]
    w_rsi = params["score_weight_rsi"]
    w_macd = params["score_weight_macd"]
    w_adx = params["score_weight_adx"]
    w_volume = params["score_weight_volume"]
    w_pa = params["score_weight_price_action"]
    w_htf = params["score_weight_htf"]

    # 1) Trend (4H)
    trend = detect_trend(trend_df, params)
    if params["indicator_ema_enabled"] and params["indicator_adx_enabled"]:
        if (bullish and trend == TrendDirection.BULLISH) or (not bullish and trend == TrendDirection.BEARISH):
            total += w_trend
            reasons.append(f"4H Trend {trend.value}")

    # 2) EMA confirmation (15M)
    if params["indicator_ema_enabled"]:
        bull_pairs, bear_pairs = ema_alignment(setup_last)
        pairs = bull_pairs if bullish else bear_pairs
        credit = w_ema * (pairs / 3)
        if credit > 0:
            total += credit
            reasons.append(f"EMA alignment {pairs}/3 (15M)")

    # 3) RSI confirmation (15M)
    if params["indicator_rsi_enabled"]:
        rsi_val = float(setup_last["rsi"])
        if bullish:
            if 50 < rsi_val < 75:
                total += w_rsi
                reasons.append(f"RSI bullish ({rsi_val:.1f})")
            elif 45 <= rsi_val <= 50:
                total += w_rsi * 0.4
                reasons.append(f"RSI mildly bullish ({rsi_val:.1f})")
        else:
            if 25 < rsi_val < 50:
                total += w_rsi
                reasons.append(f"RSI bearish ({rsi_val:.1f})")
            elif 50 <= rsi_val <= 55:
                total += w_rsi * 0.4
                reasons.append(f"RSI mildly bearish ({rsi_val:.1f})")

    # 4) MACD (15M)
    if params["indicator_macd_enabled"]:
        macd_val, signal_val, hist_val = float(setup_last["macd"]), float(setup_last["macd_signal"]), float(setup_last["macd_hist"])
        if bullish and macd_val > signal_val:
            total += w_macd if hist_val > 0 else w_macd * 0.5
            reasons.append("MACD bullish" + (" (histogram+)" if hist_val > 0 else " (cross)"))
        elif not bullish and macd_val < signal_val:
            total += w_macd if hist_val < 0 else w_macd * 0.5
            reasons.append("MACD bearish" + (" (histogram-)" if hist_val < 0 else " (cross)"))

    # 5) ADX trend strength (15M)
    if params["indicator_adx_enabled"]:
        adx_val, plus_di, minus_di = float(setup_last["adx"]), float(setup_last["plus_di"]), float(setup_last["minus_di"])
        dominant = plus_di > minus_di if bullish else minus_di > plus_di
        if dominant:
            if adx_val >= params["min_adx"]:
                total += w_adx
                reasons.append(f"ADX strong ({adx_val:.1f})")
            else:
                total += w_adx * min(1.0, adx_val / params["min_adx"])

    # 6) Volume confirmation (5M)
    if params["indicator_volume_enabled"]:
        vol, vol_ma = float(entry_last["volume"]), float(entry_last["volume_ma"])
        candle_bullish = entry_last["close"] > entry_last["open"]
        direction_ok = candle_bullish if bullish else not candle_bullish
        if direction_ok and vol_ma > 0:
            ratio = vol / vol_ma
            if ratio >= params["volume_confirm_multiplier"]:
                total += w_volume
                reasons.append(f"Volume confirmed ({ratio:.2f}x avg)")
            elif ratio >= 1.0:
                total += w_volume * 0.5
                reasons.append(f"Volume above average ({ratio:.2f}x)")

    # 7) Price action (5M)
    hits = pa.bullish_hits if bullish else pa.bearish_hits
    if hits:
        total += min(1.0, hits / 2) * w_pa
        reasons.append("Price action: " + ", ".join(pa.labels))

    # 8) Higher timeframe confirmation (1H)
    htf_trend = detect_trend(confirm_df, params)
    if (bullish and htf_trend == TrendDirection.BULLISH) or (not bullish and htf_trend == TrendDirection.BEARISH):
        total += w_htf
        reasons.append(f"1H HTF confirms {htf_trend.value}")
    else:
        confirm_last = confirm_df.iloc[-1]
        dominant = confirm_last["plus_di"] > confirm_last["minus_di"] if bullish else confirm_last["minus_di"] > confirm_last["plus_di"]
        if dominant:
            total += w_htf * 0.4

    max_score = w_trend + w_ema + w_rsi + w_macd + w_adx + w_volume + w_pa + w_htf
    return _clamp(total, max_score), reasons


def score_symbol(indexed: dict[str, pd.DataFrame], params: dict[str, Any]) -> ScoringResult:
    entry_df = indexed[TIMEFRAME_ENTRY]
    pa = detect_price_action(entry_df)

    long_score, long_reasons = _score_side(indexed, pa, params, bullish=True)
    short_score, short_reasons = _score_side(indexed, pa, params, bullish=False)

    regime = detect_regime(indexed[TIMEFRAME_SETUP], params)
    trend = detect_trend(indexed[TIMEFRAME_TREND], params)

    display_long, display_short = long_score, short_score
    effective_min_score = params["min_score"]

    if regime == MarketRegime.RANGING:
        display_long *= params["ranging_confidence_multiplier"]
        display_short *= params["ranging_confidence_multiplier"]
    elif regime == MarketRegime.HIGH_VOLATILITY:
        effective_min_score = params["min_score"] + params["high_volatility_score_buffer"]

    if display_long >= display_short:
        side, score, reasons = SignalSide.LONG, display_long, long_reasons
    else:
        side, score, reasons = SignalSide.SHORT, display_short, short_reasons

    if score >= effective_min_score:
        decision = "SIGNAL"
    elif score >= params["watchlist_min_score"]:
        decision = "WATCHLIST"
    else:
        decision = "NO_SIGNAL"
        side = None

    return ScoringResult(
        decision=decision,
        side=side,
        score=round(score, 2),
        raw_long_score=round(long_score, 2),
        raw_short_score=round(short_score, 2),
        long_reasons=long_reasons,
        short_reasons=short_reasons,
        trend=trend,
        regime=regime,
        price_action=pa,
    )

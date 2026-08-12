"""Market regime classification: TRENDING / RANGING / HIGH_VOLATILITY / LOW_VOLATILITY.

Used by the scoring engine to soften confidence in ranging markets and to
raise the bar for signals during abnormally volatile conditions (see
scoring.py). Also home to the (currently disabled) news-filter extension
point described in the spec as a future enhancement.
"""
from __future__ import annotations

from typing import Any

import pandas as pd

from app.core.enums import MarketRegime


def detect_regime(df: pd.DataFrame, params: dict[str, Any]) -> MarketRegime:
    last = df.iloc[-1]
    price = float(last["close"])
    atr_pct = float(last["atr"]) / price if price else 0.0

    if atr_pct >= params["high_volatility_atr_pct"]:
        return MarketRegime.HIGH_VOLATILITY
    if atr_pct <= params["low_volatility_atr_pct"]:
        return MarketRegime.LOW_VOLATILITY
    if float(last["adx"]) >= params["min_adx"]:
        return MarketRegime.TRENDING
    return MarketRegime.RANGING


def news_risk_flag(symbol: str, params: dict[str, Any]) -> bool:
    """Extension point for a future news/event filter (see spec item 60 & 74).

    When `news_filter_enabled` is turned on and a real news source is wired
    in, this should return True during high-impact-news windows so the
    scanner can suppress/derate new signals. Disabled by default - always
    returns False, i.e. no effect on signal generation.
    """
    if not params.get("news_filter_enabled"):
        return False
    return False

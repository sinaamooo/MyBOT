from __future__ import annotations

from typing import Any

import pandas as pd

from app.core.enums import TrendDirection


def ema_alignment(last: pd.Series) -> tuple[int, int]:
    """How many of the 3 pairwise EMA comparisons (fast/medium/slow/trend)
    agree with a bullish, resp. bearish, stack. Range 0-3 each."""
    bullish_pairs = sum(
        [
            last["ema_fast"] > last["ema_medium"],
            last["ema_medium"] > last["ema_slow"],
            last["ema_slow"] > last["ema_trend"],
        ]
    )
    bearish_pairs = sum(
        [
            last["ema_fast"] < last["ema_medium"],
            last["ema_medium"] < last["ema_slow"],
            last["ema_slow"] < last["ema_trend"],
        ]
    )
    return int(bullish_pairs), int(bearish_pairs)


def detect_trend(df: pd.DataFrame, params: dict[str, Any]) -> TrendDirection:
    """Strict trend: full EMA stack alignment AND ADX above the configured
    minimum trend strength. Used for the 4H "Trend" and 1H "Confirmation" roles."""
    last = df.iloc[-1]
    bullish_pairs, bearish_pairs = ema_alignment(last)
    strong = float(last["adx"]) >= params["min_adx"]

    if bullish_pairs == 3 and strong:
        return TrendDirection.BULLISH
    if bearish_pairs == 3 and strong:
        return TrendDirection.BEARISH
    return TrendDirection.NEUTRAL

"""Lightweight candlestick / price-action pattern detection.

These patterns are intentionally used only as one input into the scoring
engine (the "Price Action" component) - never as a standalone trigger for a
signal, per the project's quality-over-quantity rule.
"""
from __future__ import annotations

from dataclasses import dataclass

import pandas as pd


@dataclass
class PriceActionResult:
    bullish_engulfing: bool = False
    bearish_engulfing: bool = False
    hammer: bool = False
    shooting_star: bool = False
    breakout: bool = False
    breakdown: bool = False

    @property
    def bullish_hits(self) -> int:
        return sum([self.bullish_engulfing, self.hammer, self.breakout])

    @property
    def bearish_hits(self) -> int:
        return sum([self.bearish_engulfing, self.shooting_star, self.breakdown])

    @property
    def labels(self) -> list[str]:
        names = {
            "bullish_engulfing": self.bullish_engulfing,
            "bearish_engulfing": self.bearish_engulfing,
            "hammer": self.hammer,
            "shooting_star": self.shooting_star,
            "breakout": self.breakout,
            "breakdown": self.breakdown,
        }
        return [name for name, hit in names.items() if hit]


def _engulfing(prev: pd.Series, curr: pd.Series) -> tuple[bool, bool]:
    prev_bearish = prev["close"] < prev["open"]
    prev_bullish = prev["close"] > prev["open"]
    curr_bullish = curr["close"] > curr["open"]
    curr_bearish = curr["close"] < curr["open"]

    bullish_engulf = prev_bearish and curr_bullish and curr["open"] <= prev["close"] and curr["close"] >= prev["open"]
    bearish_engulf = prev_bullish and curr_bearish and curr["open"] >= prev["close"] and curr["close"] <= prev["open"]
    return bullish_engulf, bearish_engulf


def _hammer_shooting_star(curr: pd.Series) -> tuple[bool, bool]:
    body = abs(curr["close"] - curr["open"])
    candle_range = curr["high"] - curr["low"]
    if candle_range <= 0:
        return False, False

    upper_wick = curr["high"] - max(curr["close"], curr["open"])
    lower_wick = min(curr["close"], curr["open"]) - curr["low"]

    small_body = body <= candle_range * 0.35
    hammer = small_body and lower_wick >= body * 2 and upper_wick <= body * 0.5
    shooting_star = small_body and upper_wick >= body * 2 and lower_wick <= body * 0.5
    return hammer, shooting_star


def detect_price_action(df: pd.DataFrame, breakout_lookback: int = 20) -> PriceActionResult:
    if len(df) < breakout_lookback + 2:
        return PriceActionResult()

    prev, curr = df.iloc[-2], df.iloc[-1]
    bullish_engulf, bearish_engulf = _engulfing(prev, curr)
    hammer, shooting_star = _hammer_shooting_star(curr)

    lookback_window = df.iloc[-(breakout_lookback + 1):-1]
    breakout = curr["close"] > lookback_window["high"].max()
    breakdown = curr["close"] < lookback_window["low"].min()

    return PriceActionResult(
        bullish_engulfing=bool(bullish_engulf),
        bearish_engulfing=bool(bearish_engulf),
        hammer=bool(hammer),
        shooting_star=bool(shooting_star),
        breakout=bool(breakout),
        breakdown=bool(breakdown),
    )

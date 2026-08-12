"""Simple fractal-based swing high/low & support/resistance detection.

Used so Take-Profit targets aren't placed directly inside an obvious
resistance/support zone (they get nudged just in front of it instead).
"""
from __future__ import annotations

from dataclasses import dataclass, field

import pandas as pd


@dataclass
class SRLevels:
    resistances: list[float] = field(default_factory=list)
    supports: list[float] = field(default_factory=list)


def _find_swing_points(df: pd.DataFrame, order: int) -> tuple[list[float], list[float]]:
    highs, lows = df["high"], df["low"]
    swing_highs: list[float] = []
    swing_lows: list[float] = []
    for i in range(order, len(df) - order):
        window_high = highs.iloc[i - order : i + order + 1]
        window_low = lows.iloc[i - order : i + order + 1]
        if highs.iloc[i] == window_high.max():
            swing_highs.append(float(highs.iloc[i]))
        if lows.iloc[i] == window_low.min():
            swing_lows.append(float(lows.iloc[i]))
    return swing_highs, swing_lows


def _cluster(levels: list[float], tolerance_pct: float = 0.0025) -> list[float]:
    if not levels:
        return []
    levels = sorted(levels)
    clusters: list[list[float]] = [[levels[0]]]
    for lvl in levels[1:]:
        if abs(lvl - clusters[-1][-1]) / clusters[-1][-1] <= tolerance_pct:
            clusters[-1].append(lvl)
        else:
            clusters.append([lvl])
    return [sum(c) / len(c) for c in clusters]


def find_levels(df: pd.DataFrame, order: int = 3, lookback: int = 150) -> SRLevels:
    window = df.iloc[-lookback:] if len(df) > lookback else df
    raw_highs, raw_lows = _find_swing_points(window, order)
    return SRLevels(resistances=_cluster(raw_highs), supports=_cluster(raw_lows))


def adjust_target_for_levels(target: float, side: str, entry: float, levels: SRLevels, buffer_pct: float = 0.0015) -> float:
    """Pull a TP back to just in front of the nearest S/R level that sits between
    entry and the raw target, instead of placing it inside/beyond that zone."""
    if side == "LONG":
        blocking = [r for r in levels.resistances if entry < r <= target]
        if blocking:
            nearest = min(blocking)
            return round(nearest * (1 - buffer_pct), 8)
    else:
        blocking = [s for s in levels.supports if target <= s < entry]
        if blocking:
            nearest = max(blocking)
            return round(nearest * (1 + buffer_pct), 8)
    return target

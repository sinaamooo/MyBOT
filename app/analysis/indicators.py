"""Manual, dependency-light implementations of every indicator the strategy
needs. Implemented directly on pandas/numpy (instead of pandas-ta) to avoid
third-party indicator-library/numpy version incompatibilities and to keep
full control over the exact formulas used by the scoring engine.
"""
from __future__ import annotations

from typing import Any

import numpy as np
import pandas as pd


def ema(series: pd.Series, period: int) -> pd.Series:
    return series.ewm(span=period, adjust=False).mean()


def rsi(series: pd.Series, period: int = 14) -> pd.Series:
    delta = series.diff()
    gain = delta.clip(lower=0)
    loss = -delta.clip(upper=0)
    avg_gain = gain.ewm(alpha=1 / period, min_periods=period, adjust=False).mean()
    avg_loss = loss.ewm(alpha=1 / period, min_periods=period, adjust=False).mean()
    rs = avg_gain / avg_loss.replace(0, np.nan)
    out = 100 - (100 / (1 + rs))
    return out.fillna(50.0)


def macd(series: pd.Series, fast: int = 12, slow: int = 26, signal: int = 9) -> tuple[pd.Series, pd.Series, pd.Series]:
    macd_line = ema(series, fast) - ema(series, slow)
    signal_line = ema(macd_line, signal)
    hist = macd_line - signal_line
    return macd_line, signal_line, hist


def true_range(df: pd.DataFrame) -> pd.Series:
    prev_close = df["close"].shift(1)
    ranges = pd.concat(
        [df["high"] - df["low"], (df["high"] - prev_close).abs(), (df["low"] - prev_close).abs()], axis=1
    )
    return ranges.max(axis=1)


def atr(df: pd.DataFrame, period: int = 14) -> pd.Series:
    tr = true_range(df)
    return tr.ewm(alpha=1 / period, min_periods=period, adjust=False).mean()


def adx(df: pd.DataFrame, period: int = 14) -> tuple[pd.Series, pd.Series, pd.Series]:
    high, low, close = df["high"], df["low"], df["close"]
    up_move = high.diff()
    down_move = -low.diff()

    plus_dm = pd.Series(np.where((up_move > down_move) & (up_move > 0), up_move, 0.0), index=df.index)
    minus_dm = pd.Series(np.where((down_move > up_move) & (down_move > 0), down_move, 0.0), index=df.index)

    tr = true_range(df)
    atr_ = tr.ewm(alpha=1 / period, min_periods=period, adjust=False).mean()

    plus_di = 100 * plus_dm.ewm(alpha=1 / period, min_periods=period, adjust=False).mean() / atr_.replace(0, np.nan)
    minus_di = 100 * minus_dm.ewm(alpha=1 / period, min_periods=period, adjust=False).mean() / atr_.replace(0, np.nan)

    dx = ((plus_di - minus_di).abs() / (plus_di + minus_di).replace(0, np.nan)) * 100
    adx_ = dx.ewm(alpha=1 / period, min_periods=period, adjust=False).mean()
    return adx_.fillna(0.0), plus_di.fillna(0.0), minus_di.fillna(0.0)


def bollinger_bands(series: pd.Series, period: int = 20, std: float = 2.0) -> tuple[pd.Series, pd.Series, pd.Series, pd.Series]:
    mid = series.rolling(period).mean()
    sd = series.rolling(period).std()
    upper = mid + std * sd
    lower = mid - std * sd
    width = (upper - lower) / mid.replace(0, np.nan)
    return mid, upper, lower, width


def stochastic_rsi(series: pd.Series, rsi_period: int = 14, k: int = 3, d: int = 3) -> tuple[pd.Series, pd.Series]:
    rsi_series = rsi(series, rsi_period)
    lowest = rsi_series.rolling(rsi_period).min()
    highest = rsi_series.rolling(rsi_period).max()
    stoch = (rsi_series - lowest) / (highest - lowest).replace(0, np.nan) * 100
    k_line = stoch.rolling(k).mean()
    d_line = k_line.rolling(d).mean()
    return k_line.fillna(50.0), d_line.fillna(50.0)


def volume_ma(series: pd.Series, period: int = 20) -> pd.Series:
    return series.rolling(period).mean()


def compute_indicators(df: pd.DataFrame, params: dict[str, Any]) -> pd.DataFrame:
    """Return a copy of `df` (OHLCV) with every indicator column attached."""
    out = df.copy()
    close = out["close"]

    out["ema_fast"] = ema(close, params["ema_fast"])
    out["ema_medium"] = ema(close, params["ema_medium"])
    out["ema_slow"] = ema(close, params["ema_slow"])
    out["ema_trend"] = ema(close, params["ema_trend"])

    out["rsi"] = rsi(close, params["rsi_period"])

    macd_line, macd_signal, macd_hist = macd(close, params["macd_fast"], params["macd_slow"], params["macd_signal"])
    out["macd"] = macd_line
    out["macd_signal"] = macd_signal
    out["macd_hist"] = macd_hist

    adx_line, plus_di, minus_di = adx(out, params["adx_period"])
    out["adx"] = adx_line
    out["plus_di"] = plus_di
    out["minus_di"] = minus_di

    out["atr"] = atr(out, params["atr_period"])

    bb_mid, bb_upper, bb_lower, bb_width = bollinger_bands(close, params["bb_period"], params["bb_std"])
    out["bb_mid"] = bb_mid
    out["bb_upper"] = bb_upper
    out["bb_lower"] = bb_lower
    out["bb_width"] = bb_width

    stoch_k, stoch_d = stochastic_rsi(close, params["stoch_rsi_period"], params["stoch_k"], params["stoch_d"])
    out["stoch_rsi_k"] = stoch_k
    out["stoch_rsi_d"] = stoch_d

    out["volume_ma"] = volume_ma(out["volume"], params["volume_ma_period"])

    return out

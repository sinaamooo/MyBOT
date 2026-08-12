"""Simplified walk-forward backtester.

Fetches 5M candles for the requested lookback and resamples them into the
same 15M/1H/4H roles the live engine uses, then replays the exact same
scoring + risk pipeline bar-by-bar (fully causal - only data up to each
point in time is used) so results are representative of the live strategy.
This is an approximation for strategy research, not a tick-perfect
simulation: TP/SL ordering within a single 5M candle is resolved
conservatively (Stop Loss checked before further Take Profits).
"""
from __future__ import annotations

import datetime as dt
from dataclasses import dataclass, field
from typing import Any

import pandas as pd

from app.analysis.indicators import compute_indicators
from app.analysis.risk import calculate_risk, passes_min_rr
from app.analysis.scoring import score_symbol
from app.analysis.support_resistance import find_levels
from app.core.enums import SignalSide
from app.market.exchange import ExchangeService
from app.signals.generator import passes_hard_gates
from config import TIMEFRAME_CONFIRM, TIMEFRAME_ENTRY, TIMEFRAME_SETUP, TIMEFRAME_TREND

_MIN_LOOKBACK = 210  # enough bars for EMA200 etc to be meaningful
_RESAMPLE_RULES = {TIMEFRAME_SETUP: "15min", TIMEFRAME_CONFIRM: "1h", TIMEFRAME_TREND: "4h"}


@dataclass
class BacktestTrade:
    timestamp: dt.datetime
    side: str
    entry: float
    stop_loss: float
    tp1: float
    tp2: float
    tp3: float
    rr: float
    score: float
    result: str
    exit_reason: str
    profit_r: float


@dataclass
class BacktestReport:
    symbol: str
    days: int
    total_signals: int = 0
    wins: int = 0
    losses: int = 0
    win_rate: float = 0.0
    avg_rr: float = 0.0
    avg_score: float = 0.0
    profit_factor: float = 0.0
    max_drawdown_r: float = 0.0
    trades: list[BacktestTrade] = field(default_factory=list)


def _resample(df: pd.DataFrame, rule: str) -> pd.DataFrame:
    agg = {"open": "first", "high": "max", "low": "min", "close": "last", "volume": "sum"}
    return df.resample(rule).agg(agg).dropna()


def _slice_upto(df: pd.DataFrame, ts: pd.Timestamp) -> pd.DataFrame:
    pos = df.index.searchsorted(ts, side="right")
    return df.iloc[:pos]


def _simulate_trade(future: pd.DataFrame, side: SignalSide, entry: float, stop_loss: float, tp1: float, tp2: float, tp3: float, params: dict[str, Any]) -> tuple[str, str, float]:
    is_long = side == SignalSide.LONG
    current_sl = stop_loss
    hit_tp1 = hit_tp2 = False

    for _, row in future.iterrows():
        high, low = float(row["high"]), float(row["low"])
        sl_hit = low <= current_sl if is_long else high >= current_sl
        tp1_hit = high >= tp1 if is_long else low <= tp1
        tp2_hit = high >= tp2 if is_long else low <= tp2
        tp3_hit = high >= tp3 if is_long else low <= tp3

        if sl_hit and not hit_tp1:
            return "LOSS", "SL", -1.0
        if tp1_hit and not hit_tp1:
            hit_tp1 = True
            current_sl = entry
        if sl_hit and hit_tp1 and not hit_tp2:
            return "BREAKEVEN", "SL_AFTER_TP1", 0.0
        if tp2_hit and not hit_tp2:
            hit_tp2 = True
            current_sl = tp1
        if sl_hit and hit_tp2:
            return "WIN", "SL_AFTER_TP2", params["tp1_r_multiple"]
        if tp3_hit:
            return "WIN", "TP3", params["tp3_r_multiple"]

    return "PENDING", "OPEN_AT_END", 0.0


class Backtester:
    def __init__(self, exchange: ExchangeService) -> None:
        self._exchange = exchange

    async def run(self, symbol: str, days: int, params: dict[str, Any]) -> BacktestReport:
        candles_needed = min(1500, days * 24 * 12 + _MIN_LOOKBACK)  # 12 five-min candles/hour
        df_5m = await self._exchange.get_ohlcv(symbol, TIMEFRAME_ENTRY, limit=candles_needed)

        raw = {
            TIMEFRAME_ENTRY: df_5m,
            TIMEFRAME_SETUP: _resample(df_5m, _RESAMPLE_RULES[TIMEFRAME_SETUP]),
            TIMEFRAME_CONFIRM: _resample(df_5m, _RESAMPLE_RULES[TIMEFRAME_CONFIRM]),
            TIMEFRAME_TREND: _resample(df_5m, _RESAMPLE_RULES[TIMEFRAME_TREND]),
        }
        indexed = {tf: compute_indicators(df, params) for tf, df in raw.items()}

        setup_df = indexed[TIMEFRAME_SETUP]
        trades: list[BacktestTrade] = []

        for i in range(_MIN_LOOKBACK, len(setup_df) - 1):
            ts = setup_df.index[i]
            sliced = {tf: _slice_upto(df, ts) for tf, df in indexed.items()}
            if any(len(d) < _MIN_LOOKBACK for d in sliced.values()):
                continue

            scoring = score_symbol(sliced, params)
            if scoring.decision != "SIGNAL" or scoring.side is None:
                continue
            if not passes_hard_gates(scoring.side, sliced, params):
                continue

            entry_price = float(sliced[TIMEFRAME_ENTRY]["close"].iloc[-1])
            atr_value = float(sliced[TIMEFRAME_SETUP]["atr"].iloc[-1])
            sr_levels = find_levels(sliced[TIMEFRAME_SETUP])
            risk = calculate_risk(entry_price, scoring.side, atr_value, params, sr_levels)
            if not passes_min_rr(risk, params):
                continue

            future = df_5m[df_5m.index > ts]
            if future.empty:
                continue
            result, exit_reason, profit_r = _simulate_trade(future, scoring.side, entry_price, risk.stop_loss, risk.tp1, risk.tp2, risk.tp3, params)
            if result == "PENDING":
                continue  # trade never resolved within available history

            trades.append(
                BacktestTrade(
                    timestamp=ts.to_pydatetime(),
                    side=scoring.side.value,
                    entry=entry_price,
                    stop_loss=risk.stop_loss,
                    tp1=risk.tp1,
                    tp2=risk.tp2,
                    tp3=risk.tp3,
                    rr=risk.rr,
                    score=scoring.score,
                    result=result,
                    exit_reason=exit_reason,
                    profit_r=profit_r,
                )
            )

        return self._build_report(symbol, days, trades)

    @staticmethod
    def _build_report(symbol: str, days: int, trades: list[BacktestTrade]) -> BacktestReport:
        total = len(trades)
        if total == 0:
            return BacktestReport(symbol=symbol, days=days)

        wins = sum(1 for t in trades if t.result == "WIN")
        losses = sum(1 for t in trades if t.result == "LOSS")
        win_rate = round(wins / total * 100, 2)
        avg_rr = round(sum(t.rr for t in trades) / total, 2)
        avg_score = round(sum(t.score for t in trades) / total, 2)

        gross_profit = sum(t.profit_r for t in trades if t.profit_r > 0)
        gross_loss = abs(sum(t.profit_r for t in trades if t.profit_r < 0))
        profit_factor = round(gross_profit / gross_loss, 2) if gross_loss > 0 else 0.0

        cum, peak, max_dd = 0.0, 0.0, 0.0
        for t in trades:
            cum += t.profit_r
            peak = max(peak, cum)
            max_dd = max(max_dd, peak - cum)

        return BacktestReport(
            symbol=symbol,
            days=days,
            total_signals=total,
            wins=wins,
            losses=losses,
            win_rate=win_rate,
            avg_rr=avg_rr,
            avg_score=avg_score,
            profit_factor=profit_factor,
            max_drawdown_r=round(max_dd, 2),
            trades=trades,
        )

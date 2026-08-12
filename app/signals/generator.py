"""Per-symbol analysis pipeline: market data -> indicators -> score -> risk ->
hard quality gates -> a publishable SignalCandidate (or nothing).

Duplicate-signal and cooldown checks are deliberately NOT done here (they
need DB signal state) - that's the scanner's job. This module is a pure
function of market data + settings.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from typing import Any

from app.analysis.indicators import compute_indicators
from app.analysis.risk import RiskResult, calculate_risk, passes_min_rr
from app.analysis.scoring import ScoringResult, score_symbol
from app.analysis.support_resistance import find_levels
from app.analysis.trend import detect_trend
from app.core.enums import SignalSide, TrendDirection
from app.core.logging_config import get_logger
from app.market.data_fetcher import MarketDataService, MarketSnapshot
from app.market.exchange import ExchangeService
from config import PIPELINE_TIMEFRAMES, TIMEFRAME_CONFIRM, TIMEFRAME_ENTRY, TIMEFRAME_SETUP

logger = get_logger("signal_engine")


@dataclass
class SignalCandidate:
    symbol: str
    side: SignalSide
    entry: float
    stop_loss: float
    tp1: float
    tp2: float
    tp3: float
    leverage: int
    score: float
    risk_score: str
    rr: float
    trend: str
    regime: str
    timeframe: str
    reasons: list[str] = field(default_factory=list)


def passes_hard_gates(side: SignalSide, indexed: dict, params: dict[str, Any]) -> bool:
    """Hard AND-gate quality filter (spec item 35/68): ADX strength, volume
    confirmation and higher-timeframe agreement must ALL hold, on top of the
    weighted score already clearing the minimum threshold."""
    setup_last = indexed[TIMEFRAME_SETUP].iloc[-1]
    entry_last = indexed[TIMEFRAME_ENTRY].iloc[-1]

    adx_ok = float(setup_last["adx"]) >= params["min_adx"]

    vol_ma = float(entry_last["volume_ma"])
    vol_ratio = float(entry_last["volume"]) / vol_ma if vol_ma else 0.0
    candle_direction_ok = (entry_last["close"] > entry_last["open"]) if side == SignalSide.LONG else (entry_last["close"] < entry_last["open"])
    volume_ok = candle_direction_ok and vol_ratio >= params["volume_confirm_multiplier"]

    htf_trend = detect_trend(indexed[TIMEFRAME_CONFIRM], params)
    htf_ok = htf_trend == (TrendDirection.BULLISH if side == SignalSide.LONG else TrendDirection.BEARISH)

    return adx_ok and volume_ok and htf_ok


class SignalEngine:
    def __init__(self, market_service: MarketDataService, exchange: ExchangeService) -> None:
        self._market = market_service
        self._exchange = exchange

    async def analyze_symbol(self, symbol: str, params: dict[str, Any]) -> tuple[ScoringResult | None, SignalCandidate | None, MarketSnapshot | None]:
        snapshot = await self._market.get_snapshot(symbol, timeframes=PIPELINE_TIMEFRAMES)
        if snapshot is None:
            return None, None, None

        indexed = {tf: compute_indicators(df, params) for tf, df in snapshot.candles.items()}
        scoring = score_symbol(indexed, params)

        if scoring.decision != "SIGNAL" or scoring.side is None:
            return scoring, None, snapshot

        if not passes_hard_gates(scoring.side, indexed, params):
            logger.info("%s %s scored %.1f but failed hard quality gates", symbol, scoring.side.value, scoring.score)
            return scoring, None, snapshot

        setup_last = indexed[TIMEFRAME_SETUP].iloc[-1]
        atr_value = float(setup_last["atr"])
        sr_levels = find_levels(indexed[TIMEFRAME_SETUP])
        risk = calculate_risk(snapshot.price, scoring.side, atr_value, params, sr_levels)

        if not passes_min_rr(risk, params):
            logger.info("%s %s scored %.1f but RR %.2f < min %.2f", symbol, scoring.side.value, scoring.score, risk.rr, params["min_rr"])
            return scoring, None, snapshot

        decimals = await self._exchange.get_price_precision(symbol)
        candidate = SignalCandidate(
            symbol=symbol,
            side=scoring.side,
            entry=round(snapshot.price, decimals),
            stop_loss=round(risk.stop_loss, decimals),
            tp1=round(risk.tp1, decimals),
            tp2=round(risk.tp2, decimals),
            tp3=round(risk.tp3, decimals),
            leverage=risk.leverage,
            score=scoring.score,
            risk_score=risk.risk_score.value,
            rr=risk.rr,
            trend=scoring.trend.value,
            regime=scoring.regime.value,
            timeframe=f"{TIMEFRAME_ENTRY.upper()} / {TIMEFRAME_SETUP.upper()}",
            reasons=scoring.long_reasons if scoring.side == SignalSide.LONG else scoring.short_reasons,
        )
        return scoring, candidate, snapshot

    async def manual_candidate(self, symbol: str, side: SignalSide, params: dict[str, Any]) -> SignalCandidate | None:
        """Build a candidate for an admin-forced Manual LONG/SHORT or Test
        Signal. Market data and risk levels are still computed for real -
        only the scoring/quality gates are bypassed, since this is an
        explicit admin override."""
        snapshot = await self._market.get_snapshot(symbol, timeframes=PIPELINE_TIMEFRAMES)
        if snapshot is None:
            return None

        indexed = {tf: compute_indicators(df, params) for tf, df in snapshot.candles.items()}
        scoring = score_symbol(indexed, params)

        setup_last = indexed[TIMEFRAME_SETUP].iloc[-1]
        atr_value = float(setup_last["atr"])
        sr_levels = find_levels(indexed[TIMEFRAME_SETUP])
        risk = calculate_risk(snapshot.price, side, atr_value, params, sr_levels)

        decimals = await self._exchange.get_price_precision(symbol)
        score = scoring.raw_long_score if side == SignalSide.LONG else scoring.raw_short_score
        reasons = list(scoring.long_reasons if side == SignalSide.LONG else scoring.short_reasons)
        reasons.append("Manual override by admin")

        return SignalCandidate(
            symbol=symbol,
            side=side,
            entry=round(snapshot.price, decimals),
            stop_loss=round(risk.stop_loss, decimals),
            tp1=round(risk.tp1, decimals),
            tp2=round(risk.tp2, decimals),
            tp3=round(risk.tp3, decimals),
            leverage=risk.leverage,
            score=score,
            risk_score=risk.risk_score.value,
            rr=risk.rr,
            trend=scoring.trend.value,
            regime=scoring.regime.value,
            timeframe=f"{TIMEFRAME_ENTRY.upper()} / {TIMEFRAME_SETUP.upper()}",
            reasons=reasons,
        )

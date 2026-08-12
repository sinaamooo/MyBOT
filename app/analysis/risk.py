"""Risk engine: ATR-based Stop Loss / Take Profit targets, Risk:Reward, a
suggested (never auto-executed) leverage, and a LOW/MEDIUM/HIGH risk score.

Leverage here only changes how much margin a hypothetical position would
need for a given notional exposure - it does NOT reduce risk. Real risk is
governed by stop distance and position size, which is exactly what this
module computes (see spec item 49); leverage is reported purely as a
suggestion for the reader.
"""
from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from app.core.enums import RiskLevel, SignalSide
from app.analysis.support_resistance import SRLevels, adjust_target_for_levels


@dataclass
class RiskResult:
    stop_loss: float
    tp1: float
    tp2: float
    tp3: float
    rr: float
    leverage: int
    risk_score: RiskLevel
    risk_distance: float
    atr_percent: float


def calculate_risk(entry: float, side: SignalSide, atr: float, params: dict[str, Any], sr_levels: SRLevels | None = None) -> RiskResult:
    sl_distance = atr * params["atr_sl_multiplier"]

    if side == SignalSide.LONG:
        stop_loss = entry - sl_distance
        tp1 = entry + sl_distance * params["tp1_r_multiple"]
        tp2 = entry + sl_distance * params["tp2_r_multiple"]
        tp3 = entry + sl_distance * params["tp3_r_multiple"]
        if sr_levels:
            tp1 = adjust_target_for_levels(tp1, "LONG", entry, sr_levels)
            tp2 = adjust_target_for_levels(tp2, "LONG", entry, sr_levels)
            tp3 = adjust_target_for_levels(tp3, "LONG", entry, sr_levels)
    else:
        stop_loss = entry + sl_distance
        tp1 = entry - sl_distance * params["tp1_r_multiple"]
        tp2 = entry - sl_distance * params["tp2_r_multiple"]
        tp3 = entry - sl_distance * params["tp3_r_multiple"]
        if sr_levels:
            tp1 = adjust_target_for_levels(tp1, "SHORT", entry, sr_levels)
            tp2 = adjust_target_for_levels(tp2, "SHORT", entry, sr_levels)
            tp3 = adjust_target_for_levels(tp3, "SHORT", entry, sr_levels)

    risk_distance = abs(entry - stop_loss)
    avg_reward = sum(abs(tp - entry) for tp in (tp1, tp2, tp3)) / 3
    rr = round(avg_reward / risk_distance, 2) if risk_distance > 0 else 0.0

    atr_pct = (atr / entry) if entry else 0.0
    if atr_pct <= params["leverage_low_vol_atr_pct"]:
        leverage, risk_score = params["max_leverage"], RiskLevel.LOW
    elif atr_pct <= params["leverage_medium_vol_atr_pct"]:
        leverage = round((params["min_leverage"] + params["max_leverage"]) / 2)
        risk_score = RiskLevel.MEDIUM
    else:
        leverage, risk_score = params["min_leverage"], RiskLevel.HIGH

    leverage = int(max(params["min_leverage"], min(params["max_leverage"], leverage)))

    return RiskResult(
        stop_loss=stop_loss,
        tp1=tp1,
        tp2=tp2,
        tp3=tp3,
        rr=rr,
        leverage=leverage,
        risk_score=risk_score,
        risk_distance=risk_distance,
        atr_percent=round(atr_pct * 100, 3),
    )


def passes_min_rr(risk: RiskResult, params: dict[str, Any]) -> bool:
    return risk.rr >= params["min_rr"]

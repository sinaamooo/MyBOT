"""Optional AI layer extension point (spec item 59) - disabled by default.

Architectural rule: the AI layer may only ADD context. It must never
generate or gate a signal on its own - the rules-based scoring engine
(app/analysis/scoring.py) remains the sole source of trading decisions.

To wire in a real model later, implement the three functions below to call
out to whatever LLM/service you choose and flip `ai_layer_enabled` on in
Settings. Until then they are no-ops so the rest of the codebase can call
them unconditionally.
"""
from __future__ import annotations

from typing import Any


async def market_summary(symbol: str, snapshot: dict[str, Any], enabled: bool) -> str | None:
    if not enabled:
        return None
    return None  # pragma: no cover - placeholder for future integration


async def explain_signal(reasons: list[str], enabled: bool) -> str | None:
    if not enabled:
        return None
    return None  # pragma: no cover - placeholder for future integration


async def adjust_confidence(score: float, context: dict[str, Any], enabled: bool) -> float:
    """Must never increase a score above the rules-engine output; may only
    be used, in the future, to slightly temper confidence with context the
    rules engine doesn't see (e.g. news). Currently always a no-op."""
    if not enabled:
        return score
    return score  # pragma: no cover - placeholder for future integration

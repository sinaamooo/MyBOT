from __future__ import annotations

import datetime as dt
from typing import Any

from sqlalchemy import func, select

from app.core.database import get_session
from app.core.enums import SignalResult, SignalStatus
from app.core.models import Signal, SystemStats


def _today_key() -> str:
    return dt.datetime.utcnow().strftime("%Y-%m-%d")


async def refresh_today_stats() -> SystemStats:
    """Recompute today's rollup row from the signals table and upsert it
    into system_stats. Cheap enough to call on every dashboard view / after
    every monitor tick."""
    today_start = dt.datetime.utcnow().replace(hour=0, minute=0, second=0, microsecond=0)
    date_key = _today_key()

    async with get_session() as session:
        todays = (await session.execute(select(Signal).where(Signal.created_at >= today_start))).scalars().all()

        tp1 = sum(1 for s in todays if s.tp1_hit_at is not None)
        tp2 = sum(1 for s in todays if s.tp2_hit_at is not None)
        tp3 = sum(1 for s in todays if s.tp3_hit_at is not None)
        risk_free = sum(1 for s in todays if s.risk_free_at is not None)
        stopped = sum(1 for s in todays if s.status == SignalStatus.STOPPED.value)
        cancelled = sum(1 for s in todays if s.status == SignalStatus.CANCELLED.value)
        decided = [s for s in todays if s.result is not None]
        wins = sum(1 for s in decided if s.result == SignalResult.WIN.value)
        win_rate = round((wins / len(decided)) * 100, 2) if decided else 0.0
        avg_rr = round(sum(s.rr for s in todays) / len(todays), 2) if todays else 0.0
        avg_score = round(sum(s.score for s in todays) / len(todays), 2) if todays else 0.0

        result = await session.execute(select(SystemStats).where(SystemStats.date == date_key))
        row = result.scalar_one_or_none()
        if row is None:
            row = SystemStats(date=date_key)
            session.add(row)

        row.total_signals = len(todays)
        row.tp1_count = tp1
        row.tp2_count = tp2
        row.tp3_count = tp3
        row.stopped_count = stopped
        row.risk_free_count = risk_free
        row.cancelled_count = cancelled
        row.win_rate = win_rate
        row.avg_rr = avg_rr
        row.avg_score = avg_score

        await session.flush()
        await session.refresh(row)
        return row


async def today_summary() -> dict[str, Any]:
    row = await refresh_today_stats()
    return {
        "date": row.date,
        "signals": row.total_signals,
        "tp1": row.tp1_count,
        "tp2": row.tp2_count,
        "tp3": row.tp3_count,
        "stopped": row.stopped_count,
        "risk_free": row.risk_free_count,
        "cancelled": row.cancelled_count,
        "win_rate": row.win_rate,
        "avg_rr": row.avg_rr,
        "avg_score": row.avg_score,
    }

from __future__ import annotations

import datetime as dt
import json
from typing import Any

from sqlalchemy import func, select

from app.core.database import get_session
from app.core.enums import CLOSED_SIGNAL_STATUSES, OPEN_SIGNAL_STATUSES, SignalResult, SignalStatus
from app.core.models import Signal, SignalUpdate


def compute_result(side: str, entry: float, close_price: float) -> tuple[SignalResult, float]:
    direction = 1 if side == "LONG" else -1
    profit_percent = ((close_price - entry) / entry) * 100 * direction
    if profit_percent > 0.05:
        result = SignalResult.WIN
    elif profit_percent < -0.05:
        result = SignalResult.LOSS
    else:
        result = SignalResult.BREAKEVEN
    return result, round(profit_percent, 3)


async def create_signal(**fields: Any) -> Signal:
    reasons = fields.pop("reasons", None)
    if reasons is not None:
        fields["reasons"] = json.dumps(reasons, ensure_ascii=False)
    async with get_session() as session:
        signal = Signal(**fields)
        session.add(signal)
        await session.flush()
        await session.refresh(signal)
        return signal


async def get_signal(signal_id: int) -> Signal | None:
    async with get_session() as session:
        return await session.get(Signal, signal_id)


async def get_open_signal_for_symbol(symbol: str) -> Signal | None:
    async with get_session() as session:
        result = await session.execute(
            select(Signal)
            .where(Signal.symbol == symbol, Signal.status.in_([s.value for s in OPEN_SIGNAL_STATUSES]))
            .order_by(Signal.created_at.desc())
        )
        return result.scalars().first()


async def get_last_signal_time(symbol: str) -> dt.datetime | None:
    async with get_session() as session:
        result = await session.execute(
            select(Signal.created_at).where(Signal.symbol == symbol).order_by(Signal.created_at.desc()).limit(1)
        )
        row = result.first()
        return row[0] if row else None


async def get_active_signals() -> list[Signal]:
    async with get_session() as session:
        result = await session.execute(
            select(Signal)
            .where(Signal.status.in_([s.value for s in OPEN_SIGNAL_STATUSES]))
            .order_by(Signal.created_at.desc())
        )
        return list(result.scalars().all())


async def count_active_signals() -> int:
    async with get_session() as session:
        result = await session.execute(
            select(func.count()).select_from(Signal).where(Signal.status.in_([s.value for s in OPEN_SIGNAL_STATUSES]))
        )
        return result.scalar_one()


async def count_signals_since(since: dt.datetime) -> int:
    async with get_session() as session:
        result = await session.execute(select(func.count()).select_from(Signal).where(Signal.created_at >= since))
        return result.scalar_one()


async def list_history(limit: int = 20, offset: int = 0) -> list[Signal]:
    async with get_session() as session:
        result = await session.execute(select(Signal).order_by(Signal.created_at.desc()).limit(limit).offset(offset))
        return list(result.scalars().all())


async def update_signal(signal_id: int, **fields: Any) -> Signal | None:
    async with get_session() as session:
        signal = await session.get(Signal, signal_id)
        if signal is None:
            return None
        for key, value in fields.items():
            setattr(signal, key, value)
        await session.flush()
        await session.refresh(signal)
        return signal


async def add_signal_update(signal_id: int, update_type: str, message: str) -> SignalUpdate:
    async with get_session() as session:
        update = SignalUpdate(signal_id=signal_id, update_type=update_type, message=message)
        session.add(update)
        await session.flush()
        await session.refresh(update)
        return update


async def close_signal(signal_id: int, status: SignalStatus, close_price: float) -> Signal | None:
    async with get_session() as session:
        signal = await session.get(Signal, signal_id)
        if signal is None:
            return None
        result, profit_percent = compute_result(signal.side, signal.entry, close_price)
        signal.status = status.value
        signal.close_price = close_price
        signal.result = result.value
        signal.profit_percent = profit_percent
        signal.closed_at = dt.datetime.utcnow()
        await session.flush()
        await session.refresh(signal)
        return signal


async def dashboard_stats() -> dict[str, Any]:
    today_start = dt.datetime.utcnow().replace(hour=0, minute=0, second=0, microsecond=0)
    async with get_session() as session:
        total = (await session.execute(select(func.count()).select_from(Signal))).scalar_one()
        active = (
            await session.execute(
                select(func.count()).select_from(Signal).where(Signal.status.in_([s.value for s in OPEN_SIGNAL_STATUSES]))
            )
        ).scalar_one()
        today = (await session.execute(select(func.count()).select_from(Signal).where(Signal.created_at >= today_start))).scalar_one()

        closed = (
            await session.execute(select(Signal).where(Signal.status.in_([s.value for s in CLOSED_SIGNAL_STATUSES])))
        ).scalars().all()

        wins = sum(1 for s in closed if s.result == SignalResult.WIN.value)
        stopped = sum(1 for s in closed if s.status == SignalStatus.STOPPED.value)
        tp1 = sum(1 for s in closed if s.tp1_hit_at is not None)
        tp2 = sum(1 for s in closed if s.tp2_hit_at is not None)
        tp3 = sum(1 for s in closed if s.tp3_hit_at is not None)
        risk_free = sum(1 for s in closed if s.risk_free_at is not None)
        decided = len(closed)
        win_rate = round((wins / decided) * 100, 2) if decided else 0.0

        avg_rr_row = (await session.execute(select(func.avg(Signal.rr)))).scalar()
        avg_score_row = (await session.execute(select(func.avg(Signal.score)))).scalar()

    return {
        "total_signals": total,
        "active_signals": active,
        "today_signals": today,
        "winning_signals": wins,
        "stopped_signals": stopped,
        "tp1_count": tp1,
        "tp2_count": tp2,
        "tp3_count": tp3,
        "risk_free_count": risk_free,
        "win_rate": win_rate,
        "avg_rr": round(avg_rr_row, 2) if avg_rr_row else 0.0,
        "avg_score": round(avg_score_row, 2) if avg_score_row else 0.0,
    }

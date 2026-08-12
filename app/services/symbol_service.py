from __future__ import annotations

from sqlalchemy import select

from app.core.database import get_session
from app.core.models import Symbol


async def ensure_defaults(seed_symbols: list[str]) -> None:
    async with get_session() as session:
        result = await session.execute(select(Symbol))
        existing = {s.symbol for s in result.scalars().all()}
        for sym in seed_symbols:
            if sym not in existing:
                session.add(Symbol(symbol=sym, enabled=True))


async def list_enabled() -> list[str]:
    async with get_session() as session:
        result = await session.execute(select(Symbol).where(Symbol.enabled == True).order_by(Symbol.symbol))  # noqa: E712
        return [s.symbol for s in result.scalars().all()]


async def list_all() -> list[Symbol]:
    async with get_session() as session:
        result = await session.execute(select(Symbol).order_by(Symbol.symbol))
        return list(result.scalars().all())


async def add_symbol(symbol: str) -> Symbol:
    symbol = symbol.upper().strip()
    async with get_session() as session:
        result = await session.execute(select(Symbol).where(Symbol.symbol == symbol))
        row = result.scalar_one_or_none()
        if row is None:
            row = Symbol(symbol=symbol, enabled=True)
            session.add(row)
        else:
            row.enabled = True
        await session.flush()
        await session.refresh(row)
        return row


async def remove_symbol(symbol: str) -> bool:
    async with get_session() as session:
        result = await session.execute(select(Symbol).where(Symbol.symbol == symbol))
        row = result.scalar_one_or_none()
        if row is None:
            return False
        await session.delete(row)
        return True


async def set_enabled(symbol: str, enabled: bool) -> bool:
    async with get_session() as session:
        result = await session.execute(select(Symbol).where(Symbol.symbol == symbol))
        row = result.scalar_one_or_none()
        if row is None:
            return False
        row.enabled = enabled
        return True

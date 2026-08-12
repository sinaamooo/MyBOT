from __future__ import annotations

import datetime as dt

from sqlalchemy import func, select

from app.core.database import get_session
from app.core.models import User


async def register_user(telegram_id: int, username: str | None, first_name: str | None) -> User:
    async with get_session() as session:
        result = await session.execute(select(User).where(User.telegram_id == telegram_id))
        user = result.scalar_one_or_none()
        if user is None:
            user = User(telegram_id=telegram_id, username=username, first_name=first_name)
            session.add(user)
        else:
            user.username = username
            user.first_name = first_name
            user.last_seen_at = dt.datetime.utcnow()
        await session.flush()
        await session.refresh(user)
        return user


async def count_users() -> int:
    async with get_session() as session:
        result = await session.execute(select(func.count()).select_from(User))
        return result.scalar_one()


async def list_users(limit: int = 20, offset: int = 0) -> list[User]:
    async with get_session() as session:
        result = await session.execute(select(User).order_by(User.last_seen_at.desc()).limit(limit).offset(offset))
        return list(result.scalars().all())

from __future__ import annotations

from sqlalchemy import select

from app.core.database import get_session
from app.core.models import Admin
from config import settings


async def is_admin(telegram_id: int) -> bool:
    if telegram_id in settings.admin_id_list:
        return True
    async with get_session() as session:
        result = await session.execute(select(Admin).where(Admin.telegram_id == telegram_id))
        return result.scalar_one_or_none() is not None


async def add_admin(telegram_id: int, label: str | None, added_by: int) -> Admin:
    async with get_session() as session:
        result = await session.execute(select(Admin).where(Admin.telegram_id == telegram_id))
        admin = result.scalar_one_or_none()
        if admin is None:
            admin = Admin(telegram_id=telegram_id, label=label, added_by=added_by)
            session.add(admin)
            await session.flush()
            await session.refresh(admin)
        return admin


async def remove_admin(telegram_id: int) -> bool:
    async with get_session() as session:
        result = await session.execute(select(Admin).where(Admin.telegram_id == telegram_id))
        admin = result.scalar_one_or_none()
        if admin is None:
            return False
        await session.delete(admin)
        return True


async def list_admins() -> list[Admin]:
    async with get_session() as session:
        result = await session.execute(select(Admin).order_by(Admin.created_at))
        return list(result.scalars().all())

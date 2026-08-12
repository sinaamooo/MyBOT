from __future__ import annotations

from sqlalchemy import select

from app.core.database import get_session
from app.core.logging_config import get_logger
from app.core.models import Log

_py_logger = get_logger("bot")

_LEVEL_MAP = {
    "DEBUG": _py_logger.debug,
    "INFO": _py_logger.info,
    "WARNING": _py_logger.warning,
    "ERROR": _py_logger.error,
    "CRITICAL": _py_logger.critical,
}


async def log(level: str, source: str, message: str) -> None:
    level = level.upper()
    _LEVEL_MAP.get(level, _py_logger.info)(f"[{source}] {message}")
    async with get_session() as session:
        session.add(Log(level=level, source=source, message=message))


async def recent_logs(limit: int = 20) -> list[Log]:
    async with get_session() as session:
        result = await session.execute(select(Log).order_by(Log.created_at.desc()).limit(limit))
        return list(result.scalars().all())

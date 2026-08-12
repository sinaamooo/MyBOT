"""Runtime-configurable settings.

TRADING_DEFAULTS (config.py) is the schema + fallback value for every knob.
Overrides are persisted in the `settings` DB table so the Admin Panel can
change any of them without a restart or redeploy. Values are cached in
memory and reloaded whenever something is written.
"""
from __future__ import annotations

from typing import Any

from sqlalchemy import select

from app.core.database import get_session
from app.core.models import Setting
from config import TRADING_DEFAULTS

_cache: dict[str, Any] | None = None


def _coerce(raw: str, default: Any) -> Any:
    if isinstance(default, bool):
        return raw.strip().lower() in ("1", "true", "yes", "on")
    if isinstance(default, int):
        return int(float(raw))
    if isinstance(default, float):
        return float(raw)
    return raw


async def _load() -> dict[str, Any]:
    global _cache
    merged = dict(TRADING_DEFAULTS)
    async with get_session() as session:
        result = await session.execute(select(Setting))
        for row in result.scalars():
            if row.key in merged:
                try:
                    merged[row.key] = _coerce(row.value, merged[row.key])
                except (ValueError, TypeError):
                    pass  # keep default if a stored value became invalid
    _cache = merged
    return merged


async def get_all(force_reload: bool = False) -> dict[str, Any]:
    global _cache
    if _cache is None or force_reload:
        return await _load()
    return _cache


async def get(key: str) -> Any:
    cache = await get_all()
    return cache[key]


async def set(key: str, value: Any) -> None:
    if key not in TRADING_DEFAULTS:
        raise KeyError(f"Unknown setting: {key}")
    str_value = str(value)
    async with get_session() as session:
        result = await session.execute(select(Setting).where(Setting.key == key))
        row = result.scalar_one_or_none()
        if row is None:
            session.add(Setting(key=key, value=str_value))
        else:
            row.value = str_value
    await get_all(force_reload=True)


def value_type(key: str) -> type:
    return type(TRADING_DEFAULTS[key])


def all_keys() -> list[str]:
    return list(TRADING_DEFAULTS.keys())

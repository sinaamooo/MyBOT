from __future__ import annotations

import logging
from dataclasses import dataclass

from aiogram import Bot

from ..config import config
from ..db import get_db

log = logging.getLogger(__name__)
_client = None


@dataclass
class Target:
    kind: str
    id: int
    name: str
    username: str | None


async def _telethon():
    global _client
    if _client is not None:
        return _client
    if not (config.api_id and config.api_hash):
        return None
    try:
        from telethon import TelegramClient
        from telethon.sessions import StringSession
    except ImportError:
        return None
    client = TelegramClient(StringSession(), config.api_id, config.api_hash)
    await client.start(bot_token=config.bot_token)
    _client = client
    return client


async def close() -> None:
    if _client is not None:
        await _client.disconnect()


async def resolve(bot: Bot, username: str) -> Target | None:
    username = username.strip().lstrip("@")
    if not username:
        return None
    row = await get_db().find_user_by_username(username)
    if row:
        name = " ".join(filter(None, [row.get("first_name"), row.get("last_name")])) or username
        return Target("user", row["id"], name, username)
    try:
        chat = await bot.get_chat(f"@{username}")
        if chat.type == "private":
            return Target("user", chat.id, " ".join(filter(None, [chat.first_name, chat.last_name])), chat.username)
        return Target("chat", chat.id, chat.title or username, chat.username)
    except Exception:
        pass
    try:
        client = await _telethon()
        if client:
            entity = await client.get_entity(username)
            from telethon.tl.types import Channel, Chat, User

            if isinstance(entity, User):
                name = " ".join(filter(None, [entity.first_name, entity.last_name])) or username
                return Target("user", entity.id, name, entity.username)
            if isinstance(entity, (Channel, Chat)):
                return Target("chat", int(f"-100{entity.id}") if isinstance(entity, Channel) else -entity.id,
                              entity.title, getattr(entity, "username", None))
    except Exception as exc:
        log.info("telethon resolve failed for %s: %s", username, exc)
    return None

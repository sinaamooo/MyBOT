from __future__ import annotations

import asyncio
import logging
import time
from datetime import datetime, timedelta, timezone

from aiogram import Bot
from aiogram.enums import ChatMemberStatus
from aiogram.types import ChatPermissions, Message

from ..config import config

log = logging.getLogger(__name__)

_admin_cache: dict[tuple[int, int], tuple[float, bool]] = {}
_member_cache: dict[tuple[int, int], tuple[float, bool]] = {}
_bad_channels: dict[int, float] = {}


def is_bot_admin_user(user_id: int) -> bool:
    return user_id in config.admin_ids


async def is_chat_admin(bot: Bot, chat_id: int, user_id: int) -> bool:
    if user_id in config.admin_ids:
        return True
    key = (chat_id, user_id)
    hit = _admin_cache.get(key)
    if hit and time.time() - hit[0] < 300:
        return hit[1]
    try:
        member = await bot.get_chat_member(chat_id, user_id)
        ok = member.status in (ChatMemberStatus.ADMINISTRATOR, ChatMemberStatus.CREATOR)
    except Exception:
        ok = False
    _admin_cache[key] = (time.time(), ok)
    return ok


def forget_admin(chat_id: int, user_id: int) -> None:
    _admin_cache.pop((chat_id, user_id), None)


async def is_member(bot: Bot, channel_id: int | str, user_id: int) -> bool:
    key = (hash(channel_id), user_id)
    hit = _member_cache.get(key)
    if hit:
        ttl = 600 if hit[1] else 20
        if time.time() - hit[0] < ttl:
            return hit[1]
    try:
        member = await bot.get_chat_member(channel_id, user_id)
        ok = member.status not in (ChatMemberStatus.LEFT, ChatMemberStatus.KICKED) and not (
            member.status == ChatMemberStatus.RESTRICTED and not getattr(member, "is_member", True)
        )
    except Exception as exc:
        now = time.time()
        if now - _bad_channels.get(hash(channel_id), 0) > 600:
            log.warning("cannot check membership in %s (make the bot admin there): %s", channel_id, exc)
            _bad_channels[hash(channel_id)] = now
        return True
    _member_cache[key] = (time.time(), ok)
    return ok


def forget_membership(user_id: int) -> None:
    for key in [k for k in _member_cache if k[1] == user_id]:
        _member_cache.pop(key, None)


async def missing_channels(bot: Bot, user_id: int, channels: list[dict]) -> list[dict]:
    results = await asyncio.gather(*(is_member(bot, ch["id"], user_id) for ch in channels))
    return [ch for ch, ok in zip(channels, results) if not ok]


async def mute(bot: Bot, chat_id: int, user_id: int, minutes: int) -> bool:
    until = datetime.now(timezone.utc) + timedelta(minutes=minutes) if minutes else None
    try:
        await bot.restrict_chat_member(
            chat_id, user_id, permissions=ChatPermissions(can_send_messages=False), until_date=until,
            use_independent_chat_permissions=True,
        )
        return True
    except Exception as exc:
        log.info("mute failed in %s: %s", chat_id, exc)
        return False


async def unmute(bot: Bot, chat_id: int, user_id: int) -> bool:
    try:
        chat = await bot.get_chat(chat_id)
        perms = chat.permissions or ChatPermissions(
            can_send_messages=True, can_send_audios=True, can_send_documents=True, can_send_photos=True,
            can_send_videos=True, can_send_video_notes=True, can_send_voice_notes=True, can_send_polls=True,
            can_send_other_messages=True, can_add_web_page_previews=True,
        )
        await bot.restrict_chat_member(chat_id, user_id, permissions=perms, use_independent_chat_permissions=True)
        return True
    except Exception as exc:
        log.info("unmute failed in %s: %s", chat_id, exc)
        return False


async def safe_delete(message: Message | None) -> None:
    if message is None:
        return
    try:
        await message.delete()
    except Exception:
        pass


def delete_later(message: Message | None, seconds: float) -> None:
    if message is None:
        return

    async def _job():
        await asyncio.sleep(seconds)
        await safe_delete(message)

    asyncio.create_task(_job())


_throttle: dict[tuple, float] = {}


def throttled(key: tuple, seconds: float) -> bool:
    now = time.time()
    last = _throttle.get(key, 0)
    if now - last < seconds:
        return True
    _throttle[key] = now
    if len(_throttle) > 20000:
        for k in [k for k, v in _throttle.items() if now - v > 3600]:
            _throttle.pop(k, None)
    return False

"""Stores users / chats and counts activity."""
from __future__ import annotations

import logging
from typing import Any, Awaitable, Callable

from aiogram import BaseMiddleware
from aiogram.types import CallbackQuery, Message, TelegramObject

from ..db import get_db

log = logging.getLogger(__name__)


class TrackingMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        db = get_db()
        try:
            if isinstance(event, Message) and event.from_user and not event.from_user.is_bot:
                is_private = event.chat.type == "private"
                started = is_private and bool(event.text and event.text.startswith("/start"))
                await db.touch_user(event.from_user, count_message=True, started=started or is_private)
                if not is_private:
                    await db.upsert_chat(event.chat)
                if event.reply_to_message and event.reply_to_message.from_user and not event.reply_to_message.from_user.is_bot:
                    await db.touch_user(event.reply_to_message.from_user)
            elif isinstance(event, CallbackQuery) and event.from_user:
                await db.touch_user(event.from_user)
        except Exception:  # noqa: BLE001 - tracking must never block handling
            log.exception("tracking failed")
        return await handler(event, data)

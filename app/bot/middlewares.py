from __future__ import annotations

from typing import Any, Awaitable, Callable

from aiogram import BaseMiddleware
from aiogram.types import CallbackQuery, Message, TelegramObject

from app.services import admin_service, user_service


class UserRegisterMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        user = data.get("event_from_user")
        if user is not None:
            await user_service.register_user(user.id, user.username, user.first_name)
        return await handler(event, data)


class AdminOnlyMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        user = data.get("event_from_user")
        if user is None or not await admin_service.is_admin(user.id):
            if isinstance(event, CallbackQuery):
                await event.answer("⛔ Access denied", show_alert=True)
            elif isinstance(event, Message):
                await event.answer("⛔ Access denied. This panel is for admins only.")
            return None
        return await handler(event, data)

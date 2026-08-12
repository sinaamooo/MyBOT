from __future__ import annotations

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.fsm.storage.memory import MemoryStorage

from config import settings


def create_bot() -> Bot:
    return Bot(token=settings.telegram_bot_token, default=DefaultBotProperties(parse_mode="HTML"))


def create_dispatcher() -> Dispatcher:
    return Dispatcher(storage=MemoryStorage())

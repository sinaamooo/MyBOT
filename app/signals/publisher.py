from __future__ import annotations

from aiogram import Bot
from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup

from app.core.logging_config import get_logger

logger = get_logger("publisher")


def build_signal_keyboard(symbol: str, signal_id: int) -> InlineKeyboardMarkup:
    chart_url = f"https://www.tradingview.com/chart/?symbol=BINANCE%3A{symbol}.P"
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [
                InlineKeyboardButton(text="📊 Chart", url=chart_url),
                InlineKeyboardButton(text="📋 Details", callback_data=f"sig_details:{signal_id}"),
            ]
        ]
    )


class NotificationService:
    def __init__(self, bot: Bot, channel_id: str) -> None:
        self._bot = bot
        self._channel_id = channel_id

    async def publish_signal(self, text: str, keyboard: InlineKeyboardMarkup | None = None) -> int | None:
        try:
            message = await self._bot.send_message(self._channel_id, text, reply_markup=keyboard)
            return message.message_id
        except Exception as exc:
            logger.error("Failed to publish signal to channel: %s", exc)
            return None

    async def publish_update(self, text: str) -> None:
        try:
            await self._bot.send_message(self._channel_id, text)
        except Exception as exc:
            logger.error("Failed to publish signal update: %s", exc)

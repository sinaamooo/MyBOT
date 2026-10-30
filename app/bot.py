"""Bot / dispatcher factory."""
from __future__ import annotations

import asyncio
import logging

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.client.session.aiohttp import AiohttpSession
from aiogram.client.session.middlewares.base import BaseRequestMiddleware
from aiogram.enums import ParseMode
from aiogram.exceptions import TelegramBadRequest
from aiogram.fsm.storage.memory import MemoryStorage
from aiogram.types import (
    BotCommand,
    BotCommandScopeAllGroupChats,
    BotCommandScopeAllPrivateChats,
    ErrorEvent,
    InlineKeyboardMarkup,
)

from . import ui
from .config import config
from .db import get_db, init_db
from .handlers import admin, gifts, group_settings, scam, start, text_router, tools
from .middlewares.guard import GroupGuardMiddleware, PrivateForceJoinMiddleware
from .middlewares.tracking import TrackingMiddleware
from .services import resolver
from .services.whales import WhaleWatcher
from .utils import http

log = logging.getLogger(__name__)

_SKIP_ERRORS = ("not modified", "not found", "rights", "replied message", "too long", "can't be edited",
                "chat_write_forbidden", "blocked", "kicked")


def _has_premium(method) -> bool:
    for attr in ("text", "caption"):
        value = getattr(method, attr, None)
        if isinstance(value, str) and "<tg-emoji" in value:
            return True
    markup = getattr(method, "reply_markup", None)
    if isinstance(markup, InlineKeyboardMarkup):
        return any(b.icon_custom_emoji_id for row in markup.inline_keyboard for b in row)
    return False


def _strip_premium(method):
    update = {}
    for attr in ("text", "caption"):
        value = getattr(method, attr, None)
        if isinstance(value, str) and "<tg-emoji" in value:
            update[attr] = ui.strip_premium(value)
    markup = getattr(method, "reply_markup", None)
    if isinstance(markup, InlineKeyboardMarkup):
        update["reply_markup"] = InlineKeyboardMarkup(inline_keyboard=[
            [b.model_copy(update={"icon_custom_emoji_id": None}) for b in row] for row in markup.inline_keyboard
        ])
    return method.model_copy(update=update) if update else None


class PremiumEmojiFallback(BaseRequestMiddleware):
    """If Telegram rejects premium emoji (e.g. owner without Premium), retry with normal emoji."""

    async def __call__(self, make_request, bot, method):
        try:
            return await make_request(bot, method)
        except TelegramBadRequest as exc:
            msg = str(exc).lower()
            if not _has_premium(method) or any(s in msg for s in _SKIP_ERRORS):
                raise
            stripped = _strip_premium(method)
            if stripped is None:
                raise
            log.warning("premium emoji rejected (%s); retrying without", exc)
            return await make_request(bot, stripped)


def build_bot() -> Bot:
    proxy = config.telegram_proxy or config.proxy or None
    session = AiohttpSession(proxy=proxy) if proxy else AiohttpSession()
    session.middleware(PremiumEmojiFallback())
    return Bot(
        token=config.bot_token,
        session=session,
        default=DefaultBotProperties(parse_mode=ParseMode.HTML, link_preview_is_disabled=True),
    )


def build_dispatcher() -> Dispatcher:
    dp = Dispatcher(storage=MemoryStorage())
    dp.message.outer_middleware(TrackingMiddleware())
    dp.callback_query.outer_middleware(TrackingMiddleware())
    dp.message.outer_middleware(PrivateForceJoinMiddleware())
    dp.callback_query.outer_middleware(PrivateForceJoinMiddleware())
    dp.message.outer_middleware(GroupGuardMiddleware())
    # order matters: specific routers first, free-text dispatcher last
    dp.include_routers(
        admin.router,
        scam.router,
        start.router,
        group_settings.router,
        gifts.router,
        tools.router,
        text_router.router,
    )

    @dp.errors()
    async def on_error(event: ErrorEvent):
        log.exception("update failed: %s", event.exception, exc_info=event.exception)
        return True

    return dp


COMMANDS_PRIVATE = [
    BotCommand(command="start", description="شروع"),
    BotCommand(command="help", description="راهنما"),
    BotCommand(command="p", description="قیمت ارزها"),
    BotCommand(command="gift", description="گیفت‌های من / @username"),
    BotCommand(command="account", description="اطلاعات اکانت"),
    BotCommand(command="emoji", description="کد ایموجی پریمیوم"),
    BotCommand(command="scam", description="گزارش کلاهبردار"),
    BotCommand(command="check", description="استعلام کلاهبردار"),
    BotCommand(command="wallet", description="چکر ولت / هش"),
]
COMMANDS_GROUP = [
    BotCommand(command="p", description="قیمت ارزها"),
    BotCommand(command="gift", description="گیفت‌های کاربر"),
    BotCommand(command="account", description="اطلاعات اکانت"),
    BotCommand(command="emoji", description="کد ایموجی پریمیوم"),
    BotCommand(command="scam", description="گزارش کلاهبردار"),
    BotCommand(command="check", description="استعلام کلاهبردار"),
    BotCommand(command="settings", description="تنظیمات گروه (ادمین)"),
    BotCommand(command="help", description="راهنما"),
]


async def _housekeeping() -> None:
    while True:
        await asyncio.sleep(6 * 3600)
        try:
            await get_db().cleanup()
        except Exception:  # noqa: BLE001
            log.exception("cleanup failed")


async def run() -> None:
    logging.basicConfig(
        level=getattr(logging, config.log_level, logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    if not config.bot_token:
        raise SystemExit("BOT_TOKEN is not set. Copy .env.example to .env and fill it in.")
    if not config.admin_ids:
        log.warning("ADMIN_IDS is empty: admin panel and scam reviews will not work")

    await init_db(config.db_path)
    bot = build_bot()
    dp = build_dispatcher()
    me = await bot.get_me()
    ui.BOT_USERNAME = me.username or ""
    log.info("running as @%s", me.username)

    try:
        await bot.set_my_commands(COMMANDS_PRIVATE, scope=BotCommandScopeAllPrivateChats())
        await bot.set_my_commands(COMMANDS_GROUP, scope=BotCommandScopeAllGroupChats())
    except Exception:  # noqa: BLE001
        log.warning("could not set bot commands")

    watcher = WhaleWatcher(bot)
    watcher.start()
    housekeeping = asyncio.create_task(_housekeeping())
    try:
        await dp.start_polling(bot, allowed_updates=dp.resolve_used_update_types())
    finally:
        housekeeping.cancel()
        await watcher.stop()
        await resolver.close()
        await http.close()
        await get_db().close()
        await bot.session.close()

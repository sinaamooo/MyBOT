"""Group protection middleware + private force-join gate."""
from __future__ import annotations

import logging
from typing import Any, Awaitable, Callable

from aiogram import BaseMiddleware, Bot
from aiogram.types import CallbackQuery, Message, TelegramObject

from .. import ui
from ..db import get_db
from ..settings import feature_on, gget
from ..services.guard import is_scammy, self_detector, tabchi_detector
from ..utils import tg
from ..utils.fmt import duration_fa

log = logging.getLogger(__name__)


def force_join_kb(user_id: int, channels: list[dict]):
    rows = [[ui.button("btn_join", url=ch["url"], title=ch.get("title") or "کانال")] for ch in channels]
    rows.append([ui.button("btn_check_join", callback_data=f"fj:{user_id}")])
    return ui.kb(rows)


def unmute_kb(chat_id: int, user_id: int, kind: str):
    return ui.kb([[ui.button("btn_unmute", callback_data=f"unmute:{user_id}:{kind}")]])


async def handle_self_score(bot: Bot, chat_id: int, user, score: int) -> bool:
    """Warn first, mute on repeat. Returns True if the user got muted."""
    from ..services.guard import SelfDetector

    if score < SelfDetector.THRESHOLD:
        return False
    key = (chat_id, user.id)
    db = get_db()
    self_detector.reset(key)
    strikes = await db.add_strike(chat_id, user.id, "self")
    if strikes <= 1:
        msg = await bot.send_message(chat_id, ui.t("self_warn", mention=ui.mention(user)))
        tg.delete_later(msg, 300)
        return False
    minutes = int(gget("self_mute_minutes") or 0)
    if await tg.mute(bot, chat_id, user.id, minutes):
        await db.reset_strikes(chat_id, user.id, "self")
        await bot.send_message(
            chat_id,
            ui.t("self_muted", mention=ui.mention(user), duration=duration_fa(minutes)),
            reply_markup=unmute_kb(chat_id, user.id, "self"),
        )
        return True
    return False


class GroupGuardMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        if not isinstance(event, Message) or event.chat.type not in ("group", "supergroup"):
            return await handler(event, data)
        user = event.from_user
        if (
            not user
            or user.is_bot
            or event.sender_chat
            or event.is_automatic_forward
            or event.new_chat_members
            or event.left_chat_member
        ):
            return await handler(event, data)
        bot: Bot = data["bot"]
        chat_id = event.chat.id
        if await tg.is_chat_admin(bot, chat_id, user.id):
            return await handler(event, data)
        db = get_db()
        text = event.text or event.caption or ""

        # 1) advertising bots (tabchi)
        if await feature_on(chat_id, "anti_tabchi"):
            score, reasons = tabchi_detector.evaluate(event)
            if score >= tabchi_detector.THRESHOLD:
                minutes = int(gget("tabchi_mute_minutes") or 0)
                await tg.safe_delete(event)
                if await tg.mute(bot, chat_id, user.id, minutes):
                    log.info("tabchi muted %s in %s: %s", user.id, chat_id, reasons)
                    if not tg.throttled((chat_id, user.id, "tabchi"), 60):
                        await bot.send_message(
                            chat_id,
                            ui.t("tabchi_muted", mention=ui.mention(user), duration=duration_fa(minutes)),
                            reply_markup=unmute_kb(chat_id, user.id, "tabchi"),
                        )
                return None

        # 2) known scammers
        scammer = await db.find_scammer(user.id, user.username)
        if scammer:
            if gget("auto_ban_scammers"):
                try:
                    await bot.ban_chat_member(chat_id, user.id)
                except Exception:  # noqa: BLE001
                    pass
            if not tg.throttled((chat_id, user.id, "scammer"), 3600):
                await event.reply(ui.t("scam_known", mention=ui.mention(user), reason=ui.esc(scammer.get("reason") or "—")))

        # 3) forced channel membership
        channels = gget("force_channels") or []
        if channels and await feature_on(chat_id, "force_join_groups"):
            missing = await tg.missing_channels(bot, user.id, channels)
            if missing:
                await tg.safe_delete(event)
                if not tg.throttled((chat_id, user.id, "fj"), 45):
                    msg = await bot.send_message(
                        chat_id, ui.t("force_join_group", mention=ui.mention(user)),
                        reply_markup=force_join_kb(user.id, missing),
                    )
                    tg.delete_later(msg, 90)
                return None

        # 4) self-bots
        if await feature_on(chat_id, "anti_self"):
            reply_delta = None
            rt = event.reply_to_message
            if rt and rt.from_user and rt.from_user.id != user.id and rt.date:
                reply_delta = (event.date - rt.date).total_seconds()
            score = self_detector.on_message(chat_id, user, text, event.entities or event.caption_entities, reply_delta)
            if await handle_self_score(bot, chat_id, user, score):
                return None

        # 5) scam behaviour warning
        if text and await feature_on(chat_id, "anti_scam") and is_scammy(text, gget("scam_extra_keywords")):
            if not tg.throttled((chat_id, user.id, "scamwarn"), 600) and not tg.throttled((chat_id, "scamwarn"), 30):
                msg = await event.reply(ui.t("scam_keyword_warn", mention=ui.mention(user)))
                tg.delete_later(msg, 600)

        return await handler(event, data)


class PrivateForceJoinMiddleware(BaseMiddleware):
    async def __call__(
        self,
        handler: Callable[[TelegramObject, dict[str, Any]], Awaitable[Any]],
        event: TelegramObject,
        data: dict[str, Any],
    ) -> Any:
        channels = gget("force_channels") or []
        if not channels or not gget("force_join_private"):
            return await handler(event, data)
        bot: Bot = data["bot"]
        if isinstance(event, Message):
            if event.chat.type != "private" or not event.from_user or tg.is_bot_admin_user(event.from_user.id):
                return await handler(event, data)
            missing = await tg.missing_channels(bot, event.from_user.id, channels)
            if missing:
                await event.answer(
                    ui.t("force_join_private", name=ui.esc(event.from_user.first_name or "")),
                    reply_markup=force_join_kb(event.from_user.id, missing),
                )
                return None
        elif isinstance(event, CallbackQuery):
            if (
                not event.message
                or event.message.chat.type != "private"
                or (event.data or "").startswith("fj:")
                or tg.is_bot_admin_user(event.from_user.id)
            ):
                return await handler(event, data)
            missing = await tg.missing_channels(bot, event.from_user.id, channels)
            if missing:
                await event.answer(ui.plain("force_join_not_yet"), show_alert=True)
                return None
        return await handler(event, data)

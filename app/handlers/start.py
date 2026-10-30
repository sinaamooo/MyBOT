from __future__ import annotations

import logging

from aiogram import Bot, F, Router
from aiogram.filters import Command, CommandObject, CommandStart
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, ChatMemberUpdated, Message

from .. import ui
from ..db import get_db
from ..middlewares.guard import handle_self_score
from ..services.guard import self_detector, tabchi_detector
from ..settings import feature_on, gget
from ..utils import tg

log = logging.getLogger(__name__)
router = Router(name="start")


@router.message(CommandStart(deep_link=True), F.chat.type == "private")
async def start_deeplink(message: Message, command: CommandObject, state: FSMContext, bot: Bot):
    arg = command.args or ""
    if arg.startswith("scam_"):
        from .scam import begin_evidence

        try:
            scam_id = int(arg.split("_", 1)[1])
        except ValueError:
            scam_id = 0
        await begin_evidence(message, state, scam_id)
        return
    await start_private(message)


@router.message(CommandStart(), F.chat.type == "private")
async def start_private(message: Message):
    rows = [[ui.button("btn_help", callback_data="help")]]
    await message.answer(
        ui.t("start_private", name=ui.esc(message.from_user.first_name or ""), bot_username=ui.BOT_USERNAME),
        reply_markup=ui.footer_kb(rows),
    )


@router.message(Command("help", "راهنما", ignore_case=True))
@router.message(CommandStart())
async def help_cmd(message: Message):
    await message.answer(ui.t("help"), reply_markup=ui.footer_kb())


@router.callback_query(F.data == "help")
async def help_cb(call: CallbackQuery):
    await call.answer()
    await call.message.answer(ui.t("help"), reply_markup=ui.footer_kb())


@router.callback_query(F.data == "noop")
async def noop(call: CallbackQuery):
    await call.answer()


@router.my_chat_member()
async def my_chat_member(event: ChatMemberUpdated, bot: Bot):
    db = get_db()
    status = event.new_chat_member.status
    if event.chat.type in ("group", "supergroup", "channel"):
        active = status in ("member", "administrator")
        await db.upsert_chat(event.chat, active=active)
        if event.chat.type == "channel" or not active:
            return
        key = None
        if status == "administrator" and event.old_chat_member.status != "administrator":
            key = "bot_promoted"
        elif event.old_chat_member.status in ("left", "kicked"):
            key = "bot_added"
        if key:
            try:
                await bot.send_message(event.chat.id, ui.t(key), reply_markup=ui.footer_kb())
            except Exception:
                pass


async def _on_join(bot: Bot, chat_id: int, user) -> None:
    if user.is_bot:
        return
    tabchi_detector.joined(chat_id, user.id)
    scammer = await get_db().find_scammer(user.id, user.username)
    if scammer and await feature_on(chat_id, "anti_scam"):
        if gget("auto_ban_scammers"):
            try:
                await bot.ban_chat_member(chat_id, user.id)
            except Exception:
                pass
        if not tg.throttled((chat_id, user.id, "scammer"), 3600):
            await bot.send_message(chat_id, ui.t("scam_known", mention=ui.mention(user),
                                                  reason=ui.esc(scammer.get("reason") or "—")))


@router.chat_member()
async def chat_member(event: ChatMemberUpdated, bot: Bot):
    old, new = event.old_chat_member.status, event.new_chat_member.status
    if old in ("left", "kicked") and new in ("member", "restricted"):
        await _on_join(bot, event.chat.id, event.new_chat_member.user)
    tg.forget_admin(event.chat.id, event.new_chat_member.user.id)


@router.message(F.new_chat_members)
async def new_members(message: Message, bot: Bot):
    for user in message.new_chat_members:
        await _on_join(bot, message.chat.id, user)


@router.edited_message(F.chat.type.in_({"group", "supergroup"}))
async def edited(message: Message, bot: Bot):
    user = message.from_user
    if not user or user.is_bot or message.sender_chat or not message.edit_date:
        return
    if not await feature_on(message.chat.id, "anti_self"):
        return
    if await tg.is_chat_admin(bot, message.chat.id, user.id):
        return
    delta = message.edit_date - int(message.date.timestamp())
    score = self_detector.on_edit(message.chat.id, user.id, delta)
    await handle_self_score(bot, message.chat.id, user, score)


@router.callback_query(F.data.startswith("fj:"))
async def force_join_check(call: CallbackQuery, bot: Bot):
    owner = int(call.data.split(":", 1)[1])
    if call.from_user.id != owner:
        await call.answer(ui.plain("translate_not_yours"), show_alert=True)
        return
    tg.forget_membership(owner)
    missing = await tg.missing_channels(bot, owner, gget("force_channels") or [])
    if missing:
        await call.answer(ui.plain("force_join_not_yet"), show_alert=True)
        return
    await call.answer(ui.plain("force_join_ok"), show_alert=True)
    if call.message.chat.type == "private":
        await tg.safe_delete(call.message)
        await call.message.answer(
            ui.t("start_private", name=ui.esc(call.from_user.first_name or ""), bot_username=ui.BOT_USERNAME),
            reply_markup=ui.footer_kb([[ui.button("btn_help", callback_data="help")]]),
        )
    else:
        await tg.safe_delete(call.message)


@router.callback_query(F.data.startswith("unmute:"))
async def unmute_cb(call: CallbackQuery, bot: Bot):
    _, uid, kind = call.data.split(":", 2)
    chat_id = call.message.chat.id
    if not await tg.is_chat_admin(bot, chat_id, call.from_user.id):
        await call.answer(ui.plain("only_group_admins"), show_alert=True)
        return
    user_id = int(uid)
    if await tg.unmute(bot, chat_id, user_id):
        await get_db().reset_strikes(chat_id, user_id)
        self_detector.reset((chat_id, user_id))
        await call.answer("✅")
        try:
            member = await bot.get_chat_member(chat_id, user_id)
            mention = ui.mention(member.user)
        except Exception:
            mention = f"<code>{user_id}</code>"
        await call.message.edit_text(ui.t("unmuted", mention=mention))
    else:
        await call.answer("⚠️", show_alert=True)

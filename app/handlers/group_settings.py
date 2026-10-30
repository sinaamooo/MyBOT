from __future__ import annotations

from aiogram import Bot, F, Router
from aiogram.filters import Command
from aiogram.types import CallbackQuery, InlineKeyboardButton, Message

from .. import ui
from ..db import get_db
from ..settings import GROUP_FEATURES, feature_on
from ..utils import tg

router = Router(name="group_settings")


async def _kb(chat_id: int):
    rows = []
    row: list[InlineKeyboardButton] = []
    for key, label in GROUP_FEATURES.items():
        on = await feature_on(chat_id, key)
        row.append(InlineKeyboardButton(text=f"{'✅' if on else '❌'} {label}", callback_data=f"gs:{key}",
                                        style="success" if on else "danger"))
        if len(row) == 2:
            rows.append(row)
            row = []
    if row:
        rows.append(row)
    rows.append([InlineKeyboardButton(text="✖️ بستن", callback_data="gs:close")])
    return ui.kb(rows)


@router.message(Command("settings", "تنظیمات", ignore_case=True), F.chat.type.in_({"group", "supergroup"}))
async def settings_cmd(message: Message, bot: Bot):
    if not message.from_user or not await tg.is_chat_admin(bot, message.chat.id, message.from_user.id):
        await message.reply(ui.t("only_group_admins"))
        return
    await message.reply(ui.t("group_settings", title=ui.esc(message.chat.title or "")),
                        reply_markup=await _kb(message.chat.id))


@router.callback_query(F.data.startswith("gs:"))
async def settings_cb(call: CallbackQuery, bot: Bot):
    chat_id = call.message.chat.id
    if not await tg.is_chat_admin(bot, chat_id, call.from_user.id):
        await call.answer(ui.plain("only_group_admins"), show_alert=True)
        return
    key = call.data.split(":", 1)[1]
    if key == "close":
        await tg.safe_delete(call.message)
        return
    if key not in GROUP_FEATURES:
        await call.answer()
        return
    current = await feature_on(chat_id, key)
    await get_db().set_chat_setting(chat_id, key, not current)
    await call.answer("✅")
    await call.message.edit_reply_markup(reply_markup=await _kb(chat_id))

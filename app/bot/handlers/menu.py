from __future__ import annotations

from aiogram import F
from aiogram.filters import Command
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import main_menu_keyboard
from app.bot.routers import admin_router

PANEL_TITLE = "🖥 <b>Admin Panel</b>\n\nSelect a section:"


@admin_router.message(Command("admin"))
async def cmd_admin(message: Message) -> None:
    await message.answer(PANEL_TITLE, reply_markup=main_menu_keyboard())


@admin_router.callback_query(F.data == "menu:root")
async def show_root(callback: CallbackQuery) -> None:
    await callback.message.edit_text(PANEL_TITLE, reply_markup=main_menu_keyboard())
    await callback.answer()

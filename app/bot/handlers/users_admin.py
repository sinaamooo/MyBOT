from __future__ import annotations

from aiogram import F
from aiogram.filters import StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import admins_keyboard, cancel_keyboard, logs_keyboard, users_keyboard
from app.bot.routers import admin_router
from app.bot.states import AdminAdd
from app.services import admin_service, log_service, user_service

PAGE_SIZE = 10


@admin_router.callback_query(F.data == "menu:users")
async def show_users(callback: CallbackQuery) -> None:
    await _render_users(callback, 0)
    await callback.answer()


@admin_router.callback_query(F.data.startswith("usr:page:"))
async def paginate_users(callback: CallbackQuery) -> None:
    page = int(callback.data.split(":")[-1])
    await _render_users(callback, page)
    await callback.answer()


async def _render_users(callback: CallbackQuery, page: int) -> None:
    total = await user_service.count_users()
    users = await user_service.list_users(limit=PAGE_SIZE, offset=page * PAGE_SIZE)
    lines = [f"• {u.first_name or '-'} (@{u.username or '-'}) - {u.telegram_id}" for u in users]
    text = f"👥 <b>Users</b> ({total} total)\n\n" + ("\n".join(lines) if lines else "No users yet.")
    has_more = (page + 1) * PAGE_SIZE < total
    await callback.message.edit_text(text, reply_markup=users_keyboard(page, has_more))


# -- Admins -----------------------------------------------------------------
@admin_router.callback_query(F.data == "menu:admins")
async def show_admins(callback: CallbackQuery) -> None:
    admins = await admin_service.list_admins()
    text = "🔐 <b>Admins</b>\n\nTap to remove.\n\n(Env-configured ADMIN_IDS are not shown here and cannot be removed via panel.)"
    await callback.message.edit_text(text, reply_markup=admins_keyboard(admins))
    await callback.answer()


@admin_router.callback_query(F.data == "adm:add")
async def start_add_admin(callback: CallbackQuery, state: FSMContext) -> None:
    await state.set_state(AdminAdd.waiting_id)
    await callback.message.edit_text("Send the Telegram numeric ID of the new admin:", reply_markup=cancel_keyboard("admins"))
    await callback.answer()


@admin_router.message(StateFilter(AdminAdd.waiting_id))
async def receive_new_admin(message: Message, state: FSMContext) -> None:
    await state.clear()
    try:
        telegram_id = int(message.text.strip())
    except ValueError:
        await message.answer("That doesn't look like a numeric Telegram ID.")
        return
    await admin_service.add_admin(telegram_id, label=None, added_by=message.from_user.id)
    admins = await admin_service.list_admins()
    await message.answer(f"✅ Added admin {telegram_id}.", reply_markup=admins_keyboard(admins))


@admin_router.callback_query(F.data.startswith("adm:remove:"))
async def remove_admin(callback: CallbackQuery) -> None:
    telegram_id = int(callback.data.split(":")[-1])
    await admin_service.remove_admin(telegram_id)
    admins = await admin_service.list_admins()
    await callback.message.edit_text("🔐 <b>Admins</b>\n\nTap to remove.", reply_markup=admins_keyboard(admins))
    await callback.answer("Removed")


# -- Logs ---------------------------------------------------------------------
@admin_router.callback_query(F.data == "menu:logs")
async def show_logs(callback: CallbackQuery) -> None:
    logs = await log_service.recent_logs(limit=20)
    if not logs:
        text = "📜 <b>Logs</b>\n\nNo logs yet."
    else:
        lines = [f"[{log.level}] {log.source}: {log.message}" for log in logs]
        text = "📜 <b>Logs</b> (latest 20)\n\n" + "\n".join(lines)
    await callback.message.edit_text(text[:4000], reply_markup=logs_keyboard())
    await callback.answer()

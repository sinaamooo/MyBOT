from __future__ import annotations

from aiogram import F
from aiogram.filters import StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import cancel_keyboard, symbols_keyboard
from app.bot.routers import admin_router
from app.bot.states import SymbolAdd
from app.services import symbol_service


async def _render(callback: CallbackQuery) -> None:
    symbols = await symbol_service.list_all()
    await callback.message.edit_text(
        "🪙 <b>Symbols</b>\n\nTap a symbol to enable/disable it, 🗑 to remove it.",
        reply_markup=symbols_keyboard(symbols),
    )


@admin_router.callback_query(F.data == "menu:symbols")
async def show_symbols(callback: CallbackQuery) -> None:
    await _render(callback)
    await callback.answer()


@admin_router.callback_query(F.data.startswith("sym:toggle:"))
async def toggle_symbol(callback: CallbackQuery) -> None:
    symbol = callback.data.split(":", 2)[-1]
    symbols = await symbol_service.list_all()
    current = next((s for s in symbols if s.symbol == symbol), None)
    if current:
        await symbol_service.set_enabled(symbol, not current.enabled)
    await _render(callback)
    await callback.answer("Updated")


@admin_router.callback_query(F.data.startswith("sym:remove:"))
async def remove_symbol(callback: CallbackQuery) -> None:
    symbol = callback.data.split(":", 2)[-1]
    await symbol_service.remove_symbol(symbol)
    await _render(callback)
    await callback.answer("Removed")


@admin_router.callback_query(F.data == "sym:add")
async def start_add_symbol(callback: CallbackQuery, state: FSMContext) -> None:
    await state.set_state(SymbolAdd.waiting_symbol)
    await callback.message.edit_text("Send the symbol to add (e.g. PEPEUSDT):", reply_markup=cancel_keyboard("symbols"))
    await callback.answer()


@admin_router.message(StateFilter(SymbolAdd.waiting_symbol))
async def receive_new_symbol(message: Message, state: FSMContext) -> None:
    symbol = message.text.strip().upper()
    await state.clear()
    await symbol_service.add_symbol(symbol)
    symbols = await symbol_service.list_all()
    await message.answer(f"✅ {symbol} added.", reply_markup=symbols_keyboard(symbols))

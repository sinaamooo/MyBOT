from __future__ import annotations

import json

from aiogram import F
from aiogram.filters import Command, StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import active_signals_keyboard, cancel_keyboard, signal_control_keyboard, signal_detail_keyboard
from app.bot.routers import admin_router
from app.bot.states import ManualSignal
from app.core.enums import SignalSide
from app.services import settings_service, signal_service, symbol_service
from app.signals.generator import SignalEngine
from app.signals.monitor import TradeMonitor
from app.signals.scanner import Scanner

DEFAULT_TEST_SYMBOL = "BTCUSDT"


@admin_router.callback_query(F.data == "menu:signal_control")
async def show_signal_control(callback: CallbackQuery) -> None:
    params = await settings_service.get_all()
    await callback.message.edit_text(
        f"📡 <b>Signal Control</b>\n\nSignals: {'🟢 ENABLED' if params['signals_enabled'] else '🔴 DISABLED'}",
        reply_markup=signal_control_keyboard(params["signals_enabled"]),
    )
    await callback.answer()


@admin_router.callback_query(F.data.in_({"signals:enable", "signals:disable"}))
async def toggle_signals(callback: CallbackQuery) -> None:
    await settings_service.set("signals_enabled", callback.data.endswith("enable"))
    params = await settings_service.get_all(force_reload=True)
    await callback.message.edit_text(
        f"📡 <b>Signal Control</b>\n\nSignals: {'🟢 ENABLED' if params['signals_enabled'] else '🔴 DISABLED'}",
        reply_markup=signal_control_keyboard(params["signals_enabled"]),
    )
    await callback.answer("Updated")


@admin_router.callback_query(F.data.startswith("signals:manual:"))
async def start_manual_signal(callback: CallbackQuery, state: FSMContext) -> None:
    side = callback.data.split(":")[-1]
    await state.set_state(ManualSignal.waiting_symbol)
    await state.update_data(side=side)
    await callback.message.edit_text(f"Send the symbol for a manual {side} signal (e.g. BTCUSDT):", reply_markup=cancel_keyboard("signal_control"))
    await callback.answer()


@admin_router.message(StateFilter(ManualSignal.waiting_symbol))
async def receive_manual_symbol(message: Message, state: FSMContext, engine: SignalEngine, scanner: Scanner) -> None:
    data = await state.get_data()
    side = SignalSide(data["side"])
    symbol = message.text.strip().upper()
    await state.clear()

    params = await settings_service.get_all()
    candidate = await engine.manual_candidate(symbol, side, params)
    if candidate is None:
        await message.answer(f"Could not fetch market data for {symbol}.")
        return

    await scanner.publish_candidate(candidate, params, is_manual=True)
    await message.answer(f"✅ Manual {side.value} signal published for {symbol}.")


@admin_router.callback_query(F.data == "signals:test")
async def send_test_signal(callback: CallbackQuery, engine: SignalEngine, scanner: Scanner) -> None:
    params = await settings_service.get_all()
    symbols = await symbol_service.list_enabled()
    symbol = symbols[0] if symbols else DEFAULT_TEST_SYMBOL
    candidate = await engine.manual_candidate(symbol, SignalSide.LONG, params)
    if candidate is None:
        await callback.answer("Failed to fetch market data for test signal.", show_alert=True)
        return
    await scanner.publish_candidate(candidate, params, is_test=True)
    await callback.answer("Test signal sent ✅")


@admin_router.message(Command("test_signal"))
async def cmd_test_signal(message: Message, engine: SignalEngine, scanner: Scanner) -> None:
    params = await settings_service.get_all()
    symbols = await symbol_service.list_enabled()
    symbol = symbols[0] if symbols else DEFAULT_TEST_SYMBOL
    candidate = await engine.manual_candidate(symbol, SignalSide.LONG, params)
    if candidate is None:
        await message.answer(f"Failed to fetch market data for {symbol}.")
        return
    await scanner.publish_candidate(candidate, params, is_test=True)
    await message.answer("Test signal sent ✅")


@admin_router.callback_query(F.data == "menu:active")
async def show_active(callback: CallbackQuery) -> None:
    signals = await signal_service.get_active_signals()
    if not signals:
        await callback.message.edit_text("📋 <b>Active Signals</b>\n\nNo active signals.", reply_markup=active_signals_keyboard([]))
    else:
        await callback.message.edit_text("📋 <b>Active Signals</b>\n\nTap one for details.", reply_markup=active_signals_keyboard(signals))
    await callback.answer()


@admin_router.callback_query(F.data.startswith("sig:view:"))
async def view_signal(callback: CallbackQuery) -> None:
    signal_id = int(callback.data.split(":")[-1])
    signal = await signal_service.get_signal(signal_id)
    if signal is None:
        await callback.answer("Not found", show_alert=True)
        return
    reasons = json.loads(signal.reasons) if signal.reasons else []
    text = (
        f"🪙 {signal.symbol}  {signal.side}\n"
        f"Status: {signal.status}\n"
        f"Score: {signal.score:.0f}%  Risk: {signal.risk_score}  RR 1:{signal.rr}\n\n"
        f"Entry: {signal.entry}\nSL: {signal.current_sl or signal.stop_loss}\n"
        f"TP1: {signal.tp1}  TP2: {signal.tp2}  TP3: {signal.tp3}\n"
        f"Leverage: {signal.leverage}x\n\n"
        "Reasons:\n" + "\n".join(f"• {r}" for r in reasons)
    )
    await callback.message.edit_text(text, reply_markup=signal_detail_keyboard(signal))
    await callback.answer()


@admin_router.callback_query(F.data.startswith("sig:cancel:"))
async def cancel_signal(callback: CallbackQuery, monitor: TradeMonitor) -> None:
    signal_id = int(callback.data.split(":")[-1])
    await monitor.cancel(signal_id)
    await callback.answer("Signal cancelled")
    await show_active(callback)


@admin_router.callback_query(F.data.startswith("sig:close:"))
async def close_signal(callback: CallbackQuery, monitor: TradeMonitor) -> None:
    signal_id = int(callback.data.split(":")[-1])
    await monitor.manual_close(signal_id)
    await callback.answer("Signal closed")
    await show_active(callback)

from __future__ import annotations

import datetime as dt

from aiogram import F
from aiogram.filters import Command
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import dashboard_keyboard, main_menu_keyboard
from app.bot.routers import admin_router
from app.core.enums import ScannerState
from app.services import signal_service
from app.signals.scanner import Scanner


def _fmt_time(value: dt.datetime | None) -> str:
    return value.strftime("%H:%M:%S") if value else "-"


async def _dashboard_text(scanner: Scanner) -> str:
    stats = await signal_service.dashboard_stats()
    state_icon = {"RUNNING": "🟢", "PAUSED": "🟡", "STOPPED": "🔴"}[scanner.state.value]
    return (
        "🖥 <b>Dashboard</b>\n\n"
        f"Bot Status: 🟢 ONLINE\n"
        f"Scanner: {state_icon} {scanner.state.value}\n"
        f"Last Scan: {_fmt_time(scanner.last_scan_at)}\n"
        f"Next Scan: {_fmt_time(scanner.next_scan_at)}\n\n"
        f"Total Signals: {stats['total_signals']}\n"
        f"Active Signals: {stats['active_signals']}\n"
        f"Today Signals: {stats['today_signals']}\n"
        f"Winning Signals: {stats['winning_signals']}\n"
        f"Stopped Signals: {stats['stopped_signals']}\n\n"
        f"TP1: {stats['tp1_count']}  TP2: {stats['tp2_count']}  TP3: {stats['tp3_count']}\n"
        f"Risk Free: {stats['risk_free_count']}\n\n"
        f"Win Rate: {stats['win_rate']}%\n"
        f"Average R:R: 1:{stats['avg_rr']}\n"
        f"Average Score: {stats['avg_score']}%"
    )


@admin_router.callback_query(F.data == "menu:dashboard")
async def show_dashboard(callback: CallbackQuery, scanner: Scanner) -> None:
    await callback.message.edit_text(await _dashboard_text(scanner), reply_markup=dashboard_keyboard(scanner.state))
    await callback.answer()


@admin_router.callback_query(F.data.startswith("scanner:"))
async def scanner_control(callback: CallbackQuery, scanner: Scanner) -> None:
    action = callback.data.split(":", 1)[1]
    if action == "start":
        await scanner.start()
    elif action == "pause":
        await scanner.pause()
    elif action == "resume":
        await scanner.resume()
    elif action == "restart":
        await scanner.restart()
    elif action == "stop":
        await scanner.stop()

    await callback.answer(f"Scanner: {scanner.state.value}")
    try:
        await callback.message.edit_text(await _dashboard_text(scanner), reply_markup=dashboard_keyboard(scanner.state))
    except Exception:
        pass  # message may not have been the dashboard view (e.g. pressed from main menu)


@admin_router.message(Command("start_scanner"))
async def start_scanner_cmd(message: Message, scanner: Scanner) -> None:
    await scanner.start()
    await message.answer(f"Scanner: {scanner.state.value}", reply_markup=main_menu_keyboard())


@admin_router.message(Command("stop_scanner"))
async def stop_scanner_cmd(message: Message, scanner: Scanner) -> None:
    await scanner.stop()
    await message.answer(f"Scanner: {scanner.state.value}", reply_markup=main_menu_keyboard())

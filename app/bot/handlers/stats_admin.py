from __future__ import annotations

from aiogram import F
from aiogram.types import CallbackQuery

from app.bot.keyboards import history_keyboard, statistics_keyboard
from app.bot.routers import admin_router
from app.services import signal_service, statistics

PAGE_SIZE = 10


@admin_router.callback_query(F.data == "menu:statistics")
async def show_statistics(callback: CallbackQuery) -> None:
    overall = await signal_service.dashboard_stats()
    today = await statistics.today_summary()
    text = (
        "📈 <b>Statistics</b>\n\n"
        "<b>Today</b>\n"
        f"Signals: {today['signals']}\n"
        f"TP1: {today['tp1']}  TP2: {today['tp2']}  TP3: {today['tp3']}\n"
        f"Stopped: {today['stopped']}  Risk Free: {today['risk_free']}  Cancelled: {today['cancelled']}\n"
        f"Win Rate: {today['win_rate']}%\n\n"
        "<b>All Time</b>\n"
        f"Total Signals: {overall['total_signals']}\n"
        f"Winning Signals: {overall['winning_signals']}\n"
        f"Stopped Signals: {overall['stopped_signals']}\n"
        f"Win Rate: {overall['win_rate']}%\n"
        f"Average R:R: 1:{overall['avg_rr']}\n"
        f"Average Score: {overall['avg_score']}%"
    )
    await callback.message.edit_text(text, reply_markup=statistics_keyboard())
    await callback.answer()


@admin_router.callback_query(F.data == "menu:history")
async def show_history(callback: CallbackQuery) -> None:
    signals = await signal_service.list_history(limit=PAGE_SIZE, offset=0)
    if not signals:
        await callback.message.edit_text("🗂 <b>Signal History</b>\n\nNo signals yet.", reply_markup=history_keyboard([], 0))
    else:
        await callback.message.edit_text("🗂 <b>Signal History</b>", reply_markup=history_keyboard(signals, 0))
    await callback.answer()


@admin_router.callback_query(F.data.startswith("hist:page:"))
async def paginate_history(callback: CallbackQuery) -> None:
    page = int(callback.data.split(":")[-1])
    signals = await signal_service.list_history(limit=PAGE_SIZE, offset=page * PAGE_SIZE)
    await callback.message.edit_text("🗂 <b>Signal History</b>", reply_markup=history_keyboard(signals, page))
    await callback.answer()

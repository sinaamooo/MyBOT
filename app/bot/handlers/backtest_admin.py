from __future__ import annotations

from aiogram import F
from aiogram.filters import StateFilter
from aiogram.fsm.context import FSMContext
from aiogram.types import CallbackQuery, Message

from app.bot.keyboards import backtest_keyboard, cancel_keyboard
from app.bot.routers import admin_router
from app.bot.states import BacktestRun
from app.services import settings_service
from app.signals.backtest import Backtester

MAX_DAYS = 60


@admin_router.callback_query(F.data == "menu:backtest")
async def show_backtest(callback: CallbackQuery) -> None:
    await callback.message.edit_text(
        "🧪 <b>Backtest</b>\n\n"
        "Replays the live scoring/risk engine bar-by-bar over past 5M candles "
        f"(resampled into 15M/1H/4H), up to {MAX_DAYS} days.\n\n"
        "Note: this is an approximation for strategy research, not a tick-perfect simulation.",
        reply_markup=backtest_keyboard(),
    )
    await callback.answer()


@admin_router.callback_query(F.data == "bt:start")
async def start_backtest(callback: CallbackQuery, state: FSMContext) -> None:
    await state.set_state(BacktestRun.waiting_input)
    await callback.message.edit_text("Send: SYMBOL DAYS\ne.g. <code>BTCUSDT 30</code>", reply_markup=cancel_keyboard("backtest"))
    await callback.answer()


@admin_router.message(StateFilter(BacktestRun.waiting_input))
async def run_backtest(message: Message, state: FSMContext, backtester: Backtester) -> None:
    await state.clear()
    parts = message.text.strip().split()
    if len(parts) != 2 or not parts[1].isdigit():
        await message.answer("Format: SYMBOL DAYS, e.g. BTCUSDT 30")
        return

    symbol, days = parts[0].upper(), min(int(parts[1]), MAX_DAYS)
    status = await message.answer(f"⏳ Running backtest for {symbol} over {days} days...")

    params = await settings_service.get_all()
    try:
        report = await backtester.run(symbol, days, params)
    except Exception as exc:
        await status.edit_text(f"Backtest failed: {exc}")
        return

    if report.total_signals == 0:
        await status.edit_text(f"🧪 {symbol} - {days}D\n\nNo qualifying signals found in this period.")
        return

    await status.edit_text(
        f"🧪 <b>Backtest Result</b> - {symbol} ({days}D)\n\n"
        f"Total Signals: {report.total_signals}\n"
        f"Wins: {report.wins}   Losses: {report.losses}\n"
        f"Win Rate: {report.win_rate}%\n"
        f"Average R:R: 1:{report.avg_rr}\n"
        f"Average Score: {report.avg_score}%\n"
        f"Profit Factor: {report.profit_factor}\n"
        f"Max Drawdown: {report.max_drawdown_r}R"
    )

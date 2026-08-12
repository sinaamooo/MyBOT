from __future__ import annotations

import json

from aiogram import F
from aiogram.filters import Command
from aiogram.types import CallbackQuery, Message

from app.bot.routers import public_router
from app.core.logging_config import get_logger
from app.services import admin_service, signal_service, statistics
from app.signals.scanner import Scanner

logger = get_logger("bot.start")

WELCOME = (
    "👋 Welcome to the Futures Signal Bot.\n\n"
    "This bot scans the market and publishes LONG/SHORT futures signals to "
    "the configured channel. It never places real trades.\n\n"
    "⚠️ Educational / Signal Only - manage your own risk.\n\n"
    "Commands: /help /status /signals /stats"
)

HELP = (
    "<b>Commands</b>\n"
    "/start - welcome message\n"
    "/help - this message\n"
    "/status - bot &amp; scanner status\n"
    "/signals - currently active signals\n"
    "/stats - today's statistics\n\n"
    "Admins: /admin - open the control panel"
)


@public_router.message(Command("start"))
async def cmd_start(message: Message) -> None:
    text = WELCOME
    if await admin_service.is_admin(message.from_user.id):
        text += "\n\n🔐 You are an admin. Send /admin to open the control panel."
    await message.answer(text)


@public_router.message(Command("help"))
async def cmd_help(message: Message) -> None:
    await message.answer(HELP)


@public_router.message(Command("status"))
async def cmd_status(message: Message, scanner: Scanner) -> None:
    active = await signal_service.count_active_signals()
    await message.answer(
        f"🖥 Bot: 🟢 ONLINE\n"
        f"📡 Scanner: {scanner.state.value}\n"
        f"📋 Active signals: {active}"
    )


@public_router.message(Command("signals"))
async def cmd_signals(message: Message) -> None:
    signals = await signal_service.get_active_signals()
    if not signals:
        await message.answer("No active signals right now.")
        return
    lines = [f"• {s.symbol} {s.side} - {s.status} (score {s.score:.0f})" for s in signals[:20]]
    await message.answer("📋 <b>Active Signals</b>\n\n" + "\n".join(lines))


@public_router.message(Command("stats"))
async def cmd_stats(message: Message) -> None:
    stats = await statistics.today_summary()
    await message.answer(
        "📈 <b>Today</b>\n\n"
        f"Signals: {stats['signals']}\n"
        f"TP1: {stats['tp1']}  TP2: {stats['tp2']}  TP3: {stats['tp3']}\n"
        f"Stopped: {stats['stopped']}  Risk Free: {stats['risk_free']}\n"
        f"Win Rate: {stats['win_rate']}%"
    )


@public_router.callback_query(F.data.startswith("sig_details:"))
async def sig_details(callback: CallbackQuery) -> None:
    signal_id = int(callback.data.split(":", 1)[1])
    signal = await signal_service.get_signal(signal_id)
    if signal is None:
        await callback.answer("Signal not found.", show_alert=True)
        return

    reasons = json.loads(signal.reasons) if signal.reasons else []
    text = (
        f"📋 {signal.symbol} {signal.side}\n"
        f"Score: {signal.score:.0f}%  |  Risk: {signal.risk_score}  |  RR 1:{signal.rr}\n"
        f"Entry: {signal.entry}\nSL: {signal.stop_loss}\n"
        f"TP1: {signal.tp1}  TP2: {signal.tp2}  TP3: {signal.tp3}\n"
        f"Status: {signal.status}\n\n"
        "Reasons:\n" + "\n".join(f"• {r}" for r in reasons)
    )
    try:
        await callback.bot.send_message(callback.from_user.id, text)
        await callback.answer("Sent to your DM ✅")
    except Exception:
        await callback.answer(text[:200], show_alert=True)

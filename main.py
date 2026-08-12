"""Entrypoint: wires the database, exchange client, signal engine, scanner,
trade monitor and Telegram bot together and starts polling.

Run with:  python main.py
"""
from __future__ import annotations

import asyncio

from aiogram.types import BotCommand

from app.bot.bot_instance import create_bot, create_dispatcher
from app.bot.middlewares import AdminOnlyMiddleware, UserRegisterMiddleware
from app.bot.routers import admin_router, public_router
from app.core.database import init_db
from app.core.logging_config import get_logger, setup_logging
from app.market.data_fetcher import MarketDataService
from app.market.exchange import exchange_service
from app.services import log_service, settings_service, symbol_service
from app.signals.backtest import Backtester
from app.signals.generator import SignalEngine
from app.signals.monitor import TradeMonitor
from app.signals.publisher import NotificationService
from app.signals.scanner import Scanner
from config import DEFAULT_SYMBOLS, settings

# Import handler modules for their side effect of registering routes on
# public_router / admin_router (see app/bot/routers.py).
from app.bot.handlers import (  # noqa: F401  E402
    backtest_admin,
    dashboard,
    menu,
    settings_admin,
    signals_admin,
    stats_admin,
    start,
    symbols_admin,
    users_admin,
)

logger = get_logger("main")


async def main() -> None:
    setup_logging()
    logger.info("Starting Telegram Crypto Signal Bot (mode=%s)", settings.trading_mode)

    await init_db()
    await symbol_service.ensure_defaults(DEFAULT_SYMBOLS)

    bot = create_bot()
    dp = create_dispatcher()

    admin_router.message.middleware(AdminOnlyMiddleware())
    admin_router.callback_query.middleware(AdminOnlyMiddleware())
    dp.message.middleware(UserRegisterMiddleware())
    dp.callback_query.middleware(UserRegisterMiddleware())

    dp.include_router(public_router)
    dp.include_router(admin_router)

    market_service = MarketDataService(exchange_service)
    notifier = NotificationService(bot, settings.telegram_channel_id)
    engine = SignalEngine(market_service, exchange_service)
    backtester = Backtester(exchange_service)
    scanner = Scanner(engine, notifier)
    monitor = TradeMonitor(exchange_service, notifier)

    await bot.set_my_commands(
        [
            BotCommand(command="start", description="Welcome message"),
            BotCommand(command="help", description="Help"),
            BotCommand(command="status", description="Bot & scanner status"),
            BotCommand(command="signals", description="Active signals"),
            BotCommand(command="stats", description="Today's statistics"),
            BotCommand(command="admin", description="Open admin panel"),
        ]
    )

    await monitor.start()  # always track existing open signals, even if scanner is paused

    params = await settings_service.get_all()
    if params["scanner_running"]:
        await scanner.start()  # resume previous state across restarts

    await log_service.log("INFO", "main", "Bot started")

    try:
        await dp.start_polling(
            bot,
            scanner=scanner,
            monitor=monitor,
            engine=engine,
            exchange=exchange_service,
            notifier=notifier,
            backtester=backtester,
        )
    finally:
        await scanner.stop()
        await monitor.stop()
        await exchange_service.close()
        await bot.session.close()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except (KeyboardInterrupt, SystemExit):
        pass

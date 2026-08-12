from __future__ import annotations

import logging
from logging.handlers import RotatingFileHandler
from pathlib import Path

from config import settings

LOG_DIR = Path("logs")
LOG_DIR.mkdir(parents=True, exist_ok=True)

_FORMAT = "%(asctime)s | %(levelname)-8s | %(name)s | %(message)s"


def _make_handler(filename: str) -> RotatingFileHandler:
    handler = RotatingFileHandler(LOG_DIR / filename, maxBytes=5 * 1024 * 1024, backupCount=5, encoding="utf-8")
    handler.setFormatter(logging.Formatter(_FORMAT))
    return handler


def setup_logging() -> None:
    root = logging.getLogger()
    root.setLevel(settings.log_level.upper())

    console = logging.StreamHandler()
    console.setFormatter(logging.Formatter(_FORMAT))
    root.addHandler(console)
    root.addHandler(_make_handler("bot.log"))

    signals_logger = logging.getLogger("signals")
    signals_logger.setLevel(logging.INFO)
    signals_logger.addHandler(_make_handler("signals.log"))
    signals_logger.propagate = True

    # Quiet noisy third-party loggers.
    for noisy in ("ccxt", "aiogram.event", "apscheduler"):
        logging.getLogger(noisy).setLevel(logging.WARNING)


def get_logger(name: str) -> logging.Logger:
    return logging.getLogger(name)

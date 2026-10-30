"""Runtime configuration loaded from environment variables / .env file."""
from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

try:
    from dotenv import load_dotenv
except ImportError:  # pragma: no cover
    load_dotenv = None

BASE_DIR = Path(__file__).resolve().parent.parent
ASSETS_DIR = BASE_DIR / "assets"
FONTS_DIR = ASSETS_DIR / "fonts"
FLAGS_DIR = ASSETS_DIR / "flags"
DATA_DIR = BASE_DIR / "data"

if load_dotenv:
    load_dotenv(BASE_DIR / ".env")


def _ids(value: str) -> list[int]:
    out = []
    for part in (value or "").replace(";", ",").split(","):
        part = part.strip()
        if part.lstrip("-").isdigit():
            out.append(int(part))
    return out


@dataclass(frozen=True)
class Config:
    bot_token: str = field(default_factory=lambda: os.getenv("BOT_TOKEN", "").strip())
    admin_ids: list[int] = field(default_factory=lambda: _ids(os.getenv("ADMIN_IDS", "")))
    db_path: str = field(default_factory=lambda: os.getenv("DB_PATH", str(DATA_DIR / "bot.db")))
    channel_url: str = field(default_factory=lambda: os.getenv("CHANNEL_URL", "").strip())
    # HTTP(S)/SOCKS proxy for both Telegram and external APIs (useful on servers inside Iran)
    proxy: str = field(default_factory=lambda: os.getenv("PROXY", "").strip())
    telegram_proxy: str = field(default_factory=lambda: os.getenv("TELEGRAM_PROXY", "").strip())
    # Optional API keys (everything works without them, keys only raise rate limits)
    etherscan_key: str = field(default_factory=lambda: os.getenv("ETHERSCAN_API_KEY", "").strip())
    trongrid_key: str = field(default_factory=lambda: os.getenv("TRONGRID_API_KEY", "").strip())
    tronscan_key: str = field(default_factory=lambda: os.getenv("TRONSCAN_API_KEY", "").strip())
    tonapi_key: str = field(default_factory=lambda: os.getenv("TONAPI_KEY", "").strip())
    toncenter_key: str = field(default_factory=lambda: os.getenv("TONCENTER_API_KEY", "").strip())
    coingecko_key: str = field(default_factory=lambda: os.getenv("COINGECKO_API_KEY", "").strip())
    whale_alert_key: str = field(default_factory=lambda: os.getenv("WHALE_ALERT_API_KEY", "").strip())
    portals_auth: str = field(default_factory=lambda: os.getenv("PORTALS_AUTH", "").strip())
    # Optional MTProto credentials (my.telegram.org) to resolve any @username for /gift
    api_id: int = field(default_factory=lambda: int(os.getenv("API_ID", "0") or 0))
    api_hash: str = field(default_factory=lambda: os.getenv("API_HASH", "").strip())
    log_level: str = field(default_factory=lambda: os.getenv("LOG_LEVEL", "INFO").upper())


config = Config()

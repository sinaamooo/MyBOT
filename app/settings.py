"""Global bot settings (stored in DB) with defaults, plus per-group overrides."""
from __future__ import annotations

from typing import Any

from .db import get_db

# Feature switches that group admins can override per group (key -> Persian label)
GROUP_FEATURES: dict[str, str] = {
    "force_join_groups": "عضویت اجباری",
    "anti_self": "ضد سلف",
    "anti_tabchi": "ضد تبچی",
    "anti_scam": "ضد کلاهبرداری",
    "whale_alerts": "هشدار جابجایی",
    "conversions": "قیمت و تبدیل ارز",
    "checkers": "چکر ولت و هش",
    "gifts": "قیمت گیفت",
    "calculator": "ماشین حساب",
    "translate": "ترجمه",
}

DEFAULTS: dict[str, Any] = {
    # feature switches (global defaults)
    "force_join_groups": True,
    "force_join_private": True,
    "anti_self": True,
    "anti_tabchi": True,
    "anti_scam": True,
    "whale_alerts": True,
    "conversions": True,
    "checkers": True,
    "gifts": True,
    "calculator": True,
    "translate": True,
    # forced-join channels: [{"id": -100.., "title": "...", "url": "https://t.me/..."}]
    "force_channels": [],
    # moderation
    "self_mute_minutes": 1440,  # 0 = forever
    "tabchi_mute_minutes": 0,
    "auto_ban_scammers": False,
    "scam_extra_keywords": [],
    # pricing
    "star_usd": 0.015,  # price of one Telegram Star in USD (Fragment rate)
    "usdt_toman_manual": 0,  # 0 = automatic from Iranian exchanges
    "fa_digits": False,
    # whale alerts
    "whale_min_usd": 5_000_000,
    "whale_btc_min": 500,
    # look & feel
    "channel_url": "",
    "channel_title": "کانال ربات",
    "emoji_map": {},  # {"💎": "5368324170671202286"}
    "buttons": {},  # {"btn_add_group": {"text": "...", "emoji_id": "...", "style": "primary"}}
}


def gget(key: str) -> Any:
    """Global setting with default."""
    return get_db().get(key, DEFAULTS.get(key))


async def gset(key: str, value: Any) -> None:
    await get_db().set(key, value)


async def feature_on(chat_id: int | None, key: str) -> bool:
    """Feature switch honouring per-group override, falling back to global default."""
    if chat_id is not None and chat_id < 0:
        chat = await get_db().chat_settings(chat_id)
        if key in chat:
            return bool(chat[key])
    return bool(gget(key))

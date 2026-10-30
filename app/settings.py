from __future__ import annotations

from typing import Any

from .db import get_db

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
    "cardinfo": "استعلام کارت و شبا",
    "phone_check": "چکر شماره",
    "instagram": "دانلود اینستاگرام",
}

DEFAULTS: dict[str, Any] = {
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
    "cardinfo": True,
    "phone_check": True,
    "instagram": True,
    "bot_enabled": True,
    "force_channels": [],
    "self_mute_minutes": 1440,
    "tabchi_mute_minutes": 0,
    "auto_ban_scammers": False,
    "scam_extra_keywords": [],
    "star_usd": 0.015,
    "usdt_toman_manual": 0,
    "fa_digits": False,
    "whale_min_usd": 5_000_000,
    "whale_btc_min": 500,
    "channel_url": "",
    "channel_title": "کانال ربات",
    "emoji_map": {},
    "buttons": {},
}


def gget(key: str) -> Any:
    return get_db().get(key, DEFAULTS.get(key))


async def gset(key: str, value: Any) -> None:
    await get_db().set(key, value)


async def feature_on(chat_id: int | None, key: str) -> bool:
    if chat_id is not None and chat_id < 0:
        chat = await get_db().chat_settings(chat_id)
        if key in chat:
            return bool(chat[key])
    return bool(gget(key))

from __future__ import annotations

import re

from ..config import config
from ..utils.fmt import en_digits
from ..utils.http import get_json, get_text, safe

STATUS = {
    "🟢": "شماره سالمه و مشکلی ندارد",
    "⚪": "شماره سالم ولی استفاده شده است",
    "🟡": "درخواست بیش از حد، شماره محدود شده",
    "🔴": "شماره محدود شده و باید صبر کنید",
    "⚫": "شماره بن شده از تلگرام و غیر قابل استفاده",
    "🟣": "شماره تلفن اشتباه است",
    "🔵": "مشکل از سمت سرور است",
}

_PHONE = re.compile(r"^\+?\d{7,15}$")


def clean_phone(text: str) -> str | None:
    t = re.sub(r"[\s\-()]", "", en_digits(text or ""))
    if t.startswith("00"):
        t = "+" + t[2:]
    if not _PHONE.match(t):
        return None
    if not t.startswith("+"):
        if t.startswith("09") and len(t) == 11:
            t = "+98" + t[1:]
        else:
            t = "+" + t
    return t


def _status_key(raw: str) -> str | None:
    raw = (raw or "").replace("️", "").strip()
    for key in STATUS:
        if key in raw:
            return key
    return None


async def check_phone(phone: str) -> tuple[str, str] | None:
    if not config.phone_api_url:
        return None
    data = await safe(get_json(config.phone_api_url, params={"phone": phone}, timeout=20), label="phone")
    raw = None
    if isinstance(data, dict):
        raw = str(data.get("status") or data.get("result") or data.get("Status") or "")
    if raw is None or not _status_key(raw):
        text = await safe(get_text(config.phone_api_url, params={"phone": phone}, timeout=20), label="phone_text")
        raw = text or raw
    key = _status_key(raw or "")
    if not key:
        return None
    return key, STATUS[key]

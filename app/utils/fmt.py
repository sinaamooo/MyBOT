"""Number / date formatting helpers (Persian friendly)."""
from __future__ import annotations

import math
from datetime import datetime, timedelta, timezone

try:
    from zoneinfo import ZoneInfo

    TEHRAN = ZoneInfo("Asia/Tehran")
except Exception:  # pragma: no cover - tzdata missing
    TEHRAN = timezone(timedelta(hours=3, minutes=30))

FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
TO_EN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def _fa(s: str) -> str:
    from ..settings import gget

    try:
        return s.translate(FA_DIGITS) if gget("fa_digits") else s
    except Exception:
        return s


def en_digits(s: str) -> str:
    return (s or "").translate(TO_EN_DIGITS)


def _strip(s: str) -> str:
    return s.rstrip("0").rstrip(".") if "." in s else s


def num(x: float | int | None, decimals: int | None = None) -> str:
    """Smart number formatting: big numbers with separators, small with significant digits."""
    if x is None:
        return "—"
    try:
        x = float(x)
    except (TypeError, ValueError):
        return str(x)
    if math.isnan(x) or math.isinf(x):
        return "—"
    ax = abs(x)
    if decimals is not None:
        s = _strip(f"{x:,.{decimals}f}")
    elif ax == 0:
        s = "0"
    elif ax >= 100_000:
        s = f"{x:,.0f}"
    elif ax >= 1_000:
        s = _strip(f"{x:,.2f}")
    elif ax >= 1:
        s = _strip(f"{x:,.4f}")
    else:
        digits = max(2, -int(math.floor(math.log10(ax))) + 3)
        s = _strip(f"{x:.{min(digits, 12)}f}")
    return _fa(s)


def toman(x: float | None) -> str:
    if x is None:
        return "—"
    if abs(x) < 10:
        return num(x, 2)
    return _fa(f"{round(x):,}")


def usd(x: float | None) -> str:
    if x is None:
        return "—"
    if abs(x) >= 1:
        return _fa(_strip(f"{x:,.2f}"))
    return num(x)


def integer(x: float | None) -> str:
    if x is None:
        return "—"
    return _fa(f"{int(round(x)):,}")


def percent(x: float | None) -> str:
    if x is None:
        return "—"
    sign = "+" if x > 0 else ""
    return _fa(f"{sign}{x:.2f}%")


def change_badge(x: float | None) -> str | None:
    if x is None:
        return None
    icon = "🟢" if x >= 0 else "🔴"
    return f"{icon} <b>{percent(x)}</b>"


# ------------------------------------------------------------------ Jalali
def gregorian_to_jalali(gy: int, gm: int, gd: int) -> tuple[int, int, int]:
    g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334]
    gy2 = gy + 1 if gm > 2 else gy
    days = (
        355666
        + (365 * gy)
        + ((gy2 + 3) // 4)
        - ((gy2 + 99) // 100)
        + ((gy2 + 399) // 400)
        + gd
        + g_d_m[gm - 1]
    )
    jy = -1595 + (33 * (days // 12053))
    days %= 12053
    jy += 4 * (days // 1461)
    days %= 1461
    if days > 365:
        jy += (days - 1) // 365
        days = (days - 1) % 365
    if days < 186:
        jm = 1 + (days // 31)
        jd = 1 + (days % 31)
    else:
        jm = 7 + ((days - 186) // 30)
        jd = 1 + ((days - 186) % 30)
    return jy, jm, jd


JALALI_MONTHS = [
    "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
    "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
]


def to_tehran(dt: datetime | int | float | None = None) -> datetime:
    if dt is None:
        return datetime.now(TEHRAN)
    if isinstance(dt, (int, float)):
        return datetime.fromtimestamp(dt, TEHRAN)
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(TEHRAN)


def jalali_date(dt: datetime | int | float | None = None, with_time: bool = True) -> str:
    d = to_tehran(dt)
    jy, jm, jd = gregorian_to_jalali(d.year, d.month, d.day)
    s = f"{jy}/{jm:02d}/{jd:02d}"
    if with_time:
        s += f" - {d.hour:02d}:{d.minute:02d}"
    return _fa(s)


def jalali_long(dt: datetime | int | float | None = None) -> str:
    d = to_tehran(dt)
    jy, jm, jd = gregorian_to_jalali(d.year, d.month, d.day)
    return _fa(f"{jd} {JALALI_MONTHS[jm - 1]} {jy}")


def now_str() -> str:
    return jalali_date()


def duration_fa(minutes: int) -> str:
    if not minutes:
        return "دائمی"
    if minutes < 60:
        return _fa(f"{minutes} دقیقه")
    if minutes < 1440:
        return _fa(f"{minutes // 60} ساعت")
    return _fa(f"{minutes // 1440} روز")


def short(s: str, head: int = 6, tail: int = 6) -> str:
    if not s or len(s) <= head + tail + 3:
        return s or "—"
    return f"{s[:head]}…{s[-tail:]}"


def time_ago(ts: float) -> str:
    sec = max(0, int(datetime.now(timezone.utc).timestamp() - ts))
    if sec < 60:
        return _fa(f"{sec} ثانیه پیش")
    if sec < 3600:
        return _fa(f"{sec // 60} دقیقه پیش")
    if sec < 86400:
        return _fa(f"{sec // 3600} ساعت پیش")
    return _fa(f"{sec // 86400} روز پیش")

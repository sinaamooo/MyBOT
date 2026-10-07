"""Load ``site.toml`` and normalise it into typed, display-ready values."""

from __future__ import annotations

import hashlib
import re
from dataclasses import dataclass
from pathlib import Path

try:
    import tomllib
except ModuleNotFoundError:  # Python < 3.11
    import tomli as tomllib

FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
EN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")

STATUS_TONES = {"ok", "warn", "err"}
NOTICE_TONES = {"info", "success", "warning", "error"}


def to_fa(value: object) -> str:
    """Render Latin digits as Persian digits."""
    return str(value).translate(FA_DIGITS)


def to_en(value: object) -> str:
    """Render Persian/Arabic digits as Latin digits."""
    return str(value).translate(EN_DIGITS)


def format_toman(amount: int) -> str:
    """45000 -> ۴۵٬۰۰۰"""
    return to_fa(f"{amount:,}".replace(",", "٬"))


def placeholder(key: str) -> str:
    """Visible marker for a value that still has to be filled in."""
    return "{{" + key + "}}"


@dataclass(frozen=True)
class Service:
    name: str
    price: str
    price_value: int | None
    category: str
    description: str
    available: bool
    icon: str


@dataclass(frozen=True)
class Notice:
    title: str
    text: str
    date: str
    tone: str


@dataclass(frozen=True)
class Status:
    label: str
    value: str
    tone: str
    note: str


@dataclass(frozen=True)
class Site:
    brand_fa: str
    brand_en: str
    domain: str
    bot_username: str
    owner_name: str
    mobile: str
    mobile_tel: str
    landline: str
    landline_tel: str
    email: str
    address: str
    postal_code: str
    hours: str
    services_line: str
    year: str
    legal_updated: str
    enamad_meta: str
    enamad_seal: str
    services: tuple[Service, ...]
    notices: tuple[Notice, ...]
    statuses: tuple[Status, ...]
    missing: tuple[str, ...]

    @property
    def bot_url(self) -> str:
        return f"https://t.me/{self.bot_username}"

    @property
    def priced(self) -> tuple[Service, ...]:
        return tuple(s for s in self.services if s.price_value is not None)

    @property
    def max_price(self) -> int:
        return max((s.price_value for s in self.priced), default=0)

    @property
    def min_price(self) -> str | None:
        values = [s.price_value for s in self.priced]
        return format_toman(min(values)) if values else None

    @property
    def available_count(self) -> int:
        return sum(s.available for s in self.services)

    @property
    def categories(self) -> tuple[str, ...]:
        return tuple(dict.fromkeys(s.category for s in self.services))

    @property
    def notices_signature(self) -> str:
        raw = "|".join(f"{n.title}{n.date}" for n in self.notices)
        return hashlib.sha1(raw.encode()).hexdigest()[:8]


class _Fields:
    """Reads required strings and remembers which ones are still empty."""

    def __init__(self) -> None:
        self.missing: list[str] = []

    def required(self, table: dict, key: str, marker: str) -> str:
        value = str(table.get(key, "")).strip()
        if value:
            return value
        self.missing.append(marker)
        return placeholder(marker)


def _is_marker(value: str) -> bool:
    return not value or value.startswith("{{")


def _phone(raw: str) -> tuple[str, str]:
    """Return (display, tel-link) forms of a phone number."""
    if _is_marker(raw):
        return raw, raw
    latin = to_en(raw)
    return to_fa(latin), re.sub(r"[^\d+]", "", latin)


def _price(raw: object) -> tuple[str, int | None]:
    digits = re.sub(r"[,٬،\s]", "", to_en(raw))
    if digits.isdigit():
        return format_toman(int(digits)), int(digits)
    return str(raw).strip(), None


def _services(rows: list[dict], fields: _Fields) -> tuple[Service, ...]:
    services = []
    for index, row in enumerate(rows, start=1):
        name = fields.required(row, "name", f"SERVICE_{index}")
        raw_price = str(row.get("price", "")).strip()
        price, value = _price(raw_price) if raw_price else (fields.required(row, "price", f"PRICE_{index}"), None)
        services.append(
            Service(
                name=name,
                price=price,
                price_value=value,
                category=str(row.get("category", "")).strip() or "سایر خدمات",
                description=str(row.get("description", "")).strip(),
                available=bool(row.get("available", True)),
                icon=str(row.get("icon", "")).strip() or "package",
            )
        )
    return tuple(services)


def _choice(value: object, allowed: set[str], default: str) -> str:
    value = str(value).strip()
    return value if value in allowed else default


def load_config(path: Path) -> Site:
    data = tomllib.loads(path.read_text(encoding="utf-8"))
    business = data.get("business", {})
    enamad = data.get("enamad", {})
    fields = _Fields()

    domain = re.sub(r"^https?://|/+$", "", fields.required(business, "domain", "DOMAIN"))
    bot = re.sub(r"^(https?://)?(t\.me/)?@?", "", fields.required(business, "bot_username", "BOT_USERNAME"))
    mobile, mobile_tel = _phone(fields.required(business, "mobile", "MOBILE"))
    landline, landline_tel = _phone(str(business.get("landline", "")).strip())
    postal = fields.required(business, "postal_code", "POSTAL_CODE")

    return Site(
        brand_fa=str(business.get("brand_fa", "نامبیکس")).strip(),
        brand_en=str(business.get("brand_en", "Numbix")).strip(),
        domain=domain,
        bot_username=bot,
        owner_name=fields.required(business, "owner_name", "OWNER_NAME"),
        mobile=mobile,
        mobile_tel=mobile_tel,
        landline=landline,
        landline_tel=landline_tel,
        email=to_en(fields.required(business, "email", "EMAIL")),
        address=fields.required(business, "address", "ADDRESS"),
        postal_code=postal if _is_marker(postal) else to_fa(to_en(postal)),
        hours=str(business.get("hours", "")).strip() or "شنبه تا پنجشنبه، ساعت ۹ تا ۱۸",
        services_line=fields.required(business, "services_line", "SERVICES_LINE").rstrip("."),
        year=to_fa(business.get("year", "۱۴۰۵")),
        legal_updated=str(business.get("legal_updated", "۱۳ مهر ۱۴۰۵")).strip(),
        enamad_meta=str(enamad.get("meta_code", "")).strip(),
        enamad_seal=str(enamad.get("seal_html", "")).strip(),
        services=_services(data.get("services", []), fields),
        notices=tuple(
            Notice(
                title=str(n.get("title", "")).strip(),
                text=str(n.get("text", "")).strip(),
                date=str(n.get("date", "")).strip(),
                tone=_choice(n.get("tone", "info"), NOTICE_TONES, "info"),
            )
            for n in data.get("notices", [])
        ),
        statuses=tuple(
            Status(
                label=str(s.get("label", "")).strip(),
                value=str(s.get("value", "")).strip(),
                tone=_choice(s.get("tone", "ok"), STATUS_TONES, "ok"),
                note=str(s.get("note", "")).strip(),
            )
            for s in data.get("status", [])
        ),
        missing=tuple(fields.missing),
    )

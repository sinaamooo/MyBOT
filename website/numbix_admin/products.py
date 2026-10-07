"""Products and their provider API settings: validation and storage."""

from __future__ import annotations

import json
import re
from dataclasses import dataclass, field
from urllib.parse import urlsplit

from numbix_site.config import format_toman, to_en

from .crypto import Vault

ICONS = {
    "smartphone": "موبایل",
    "message-circle": "پیام‌رسان",
    "package": "بسته",
    "sparkles": "ویژه",
    "zap": "فوری",
    "wallet": "کیف پول",
}
_CONTROL = re.compile(r"[\x00-\x08\x0b-\x1f\x7f]")
LIMITS = {"name": 80, "category": 40, "description": 300, "api_provider": 40, "api_service": 80, "api_base_url": 300}
MAX_PARAMS, MAX_KEY = 4000, 500


@dataclass
class ProductForm:
    values: dict = field(default_factory=dict)
    errors: dict = field(default_factory=dict)
    new_key: str | None = None  # None = keep the stored key, "" = remove it

    @property
    def ok(self) -> bool:
        return not self.errors


def _text(form, name: str, errors: dict, required: bool = False) -> str:
    value = _CONTROL.sub("", form.get(name, "")).strip()
    if required and not value:
        errors[name] = "این فیلد لازم است"
    elif len(value) > LIMITS[name]:
        errors[name] = f"حداکثر {LIMITS[name]} کاراکتر"
    return value


def _https_url(value: str, errors: dict) -> str:
    if not value:
        return ""
    parts = urlsplit(value)
    if parts.scheme != "https" or not parts.hostname or parts.username or parts.password or " " in value:
        errors["api_base_url"] = "فقط نشانی کامل https:// پذیرفته می‌شود"
    return value


def _params(raw: str, errors: dict) -> str:
    raw = raw.strip() or "{}"
    if len(raw) > MAX_PARAMS:
        errors["api_params"] = f"حداکثر {MAX_PARAMS} کاراکتر"
        return raw
    try:
        data = json.loads(raw)
    except ValueError:
        errors["api_params"] = "JSON معتبر نیست"
        return raw
    if not isinstance(data, dict) or not all(
        isinstance(k, str) and (v is None or isinstance(v, (str, int, float, bool))) for k, v in data.items()
    ):
        errors["api_params"] = 'باید یک شیء ساده‌ی JSON باشد، مثل {"country": "ir"}'
        return raw
    return json.dumps(data, ensure_ascii=False)


def parse_form(form) -> ProductForm:
    errors: dict = {}
    values = {
        "name": _text(form, "name", errors, required=True),
        "category": _text(form, "category", errors),
        "description": _text(form, "description", errors),
        "api_provider": _text(form, "api_provider", errors),
        "api_service": _text(form, "api_service", errors),
        "api_base_url": _https_url(_text(form, "api_base_url", errors), errors),
        "api_params": _params(form.get("api_params", ""), errors),
        "available": form.get("available") == "1",
        "api_enabled": form.get("api_enabled") == "1",
    }

    price = re.sub(r"[,٬،\s]", "", to_en(form.get("price", "")))
    if not (price.isdigit() and len(price) <= 12):
        errors["price"] = "قیمت به تومان لازم است (فقط عدد)"
    values["price"] = int(price) if price.isdigit() and len(price) <= 12 else None

    position = to_en(form.get("position", "0")).strip() or "0"
    values["position"] = int(position) if position.isdigit() and int(position) < 10_000 else 0

    icon = form.get("icon", "package")
    values["icon"] = icon if icon in ICONS else "package"

    if values["api_enabled"] and not values["api_base_url"]:
        errors["api_base_url"] = "برای فعال‌کردن API، نشانی آن لازم است"

    result = ProductForm(values=values, errors=errors)
    key = form.get("api_key", "")
    if form.get("api_key_clear") == "1":
        result.new_key = ""
    elif key.strip():
        if len(key) > MAX_KEY or _CONTROL.search(key):
            errors["api_key"] = f"کلید معتبر نیست (حداکثر {MAX_KEY} کاراکتر)"
        result.new_key = key.strip()
    return result


_COLUMNS = (
    "name", "price", "category", "description", "icon", "available", "position",
    "api_provider", "api_base_url", "api_service", "api_params", "api_enabled",
)  # fmt: skip


def save(db, vault: Vault, form: ProductForm, product_id: int | None = None) -> int:
    values = [form.values[c] for c in _COLUMNS]
    if product_id is None:
        columns = ", ".join((*_COLUMNS, "api_key"))
        marks = ", ".join("?" * (len(_COLUMNS) + 1))
        cursor = db.execute(  # column names are the constants above; every value is a bound parameter
            f"INSERT INTO products ({columns}) VALUES ({marks})",  # noqa: S608
            (*values, vault.seal(form.new_key or "")),
        )
        return cursor.lastrowid
    assignments = ", ".join(f"{c} = ?" for c in _COLUMNS)
    db.execute(
        f"UPDATE products SET {assignments}, updated_at = datetime('now') WHERE id = ?",  # noqa: S608 (constant columns)
        (*values, product_id),
    )
    if form.new_key is not None:
        db.execute("UPDATE products SET api_key = ? WHERE id = ?", (vault.seal(form.new_key), product_id))
    return product_id


def all_products(db) -> list:
    return db.execute("SELECT * FROM products ORDER BY position, id").fetchall()


def get_product(db, product_id: int):
    return db.execute("SELECT * FROM products WHERE id = ?", (product_id,)).fetchone()


def masked_key(vault: Vault, token: str) -> str:
    """Show only the last four characters of a stored key."""
    key = vault.open(token)
    return f"•••• {key[-4:]}" if len(key) > 8 else ("••••" if key else "")


def price_label(price: int | None) -> str:
    return format_toman(price) if price is not None else "—"


def for_site(rows) -> list[dict]:
    """Rows in the shape numbix_site expects for [[services]]."""
    return [
        {
            "name": r["name"],
            "price": "" if r["price"] is None else str(r["price"]),
            "category": r["category"],
            "description": r["description"],
            "icon": r["icon"],
            "available": bool(r["available"]),
        }
        for r in rows
    ]

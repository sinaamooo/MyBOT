from __future__ import annotations

import re

from .fmt import en_digits

MULTIPLIERS = {
    "k": 1e3,
    "هزار": 1e3,
    "m": 1e6,
    "میلیون": 1e6,
    "ملیون": 1e6,
    "b": 1e9,
    "میلیارد": 1e9,
    "ملیارد": 1e9,
    "همت": 1e12,
    "تریلیون": 1e12,
}
_MULT = "|".join(sorted((re.escape(k) for k in MULTIPLIERS), key=len, reverse=True))
_NUM = r"\d+(?:\.\d+)?"

_AMOUNT_FIRST = re.compile(
    rf"^(?P<num>{_NUM})\s*(?:(?P<mult>{_MULT})(?![a-z]))?\s*(?P<unit>[^\d]{{1,30}})$", re.I
)
_UNIT_FIRST = re.compile(rf"^(?P<unit>[^\d]{{1,30}}?)\s*(?P<num>{_NUM})\s*(?P<mult>{_MULT})?$", re.I)


def clean_number_text(text: str) -> str:
    text = en_digits(text or "").strip()
    text = text.replace("٫", ".").replace("٬", "").replace("،", "")
    text = re.sub(r"(?<=\d)[,_](?=\d{3}\b)", "", text)
    text = re.sub(r"(?<=\d),(?=\d)", "", text)
    return text


def parse_amount(text: str) -> tuple[float, str] | None:
    if not text or len(text) > 48 or "\n" in text.strip():
        return None
    t = clean_number_text(text)
    m = _AMOUNT_FIRST.match(t) or _UNIT_FIRST.match(t)
    if not m:
        return None
    try:
        amount = float(m.group("num"))
    except ValueError:
        return None
    mult = (m.group("mult") or "").lower()
    if mult:
        amount *= MULTIPLIERS[mult]
    unit = m.group("unit").strip(" .:=-")
    if not unit or unit.lower() in MULTIPLIERS:
        return None
    return amount, unit


_CALC_CHARS = re.compile(r"^[\d\s.+\-*/×÷x^%()=]+$", re.I)
_DATE_LIKE = re.compile(r"^\d{2,4}[/\-]\d{1,2}[/\-]\d{1,4}$")
_PHONE_LIKE = re.compile(r"^(?:\+?\d{1,3}[\s-]?)?0?\d{3}[\s-]\d{3,4}[\s-]?\d{0,4}$")


def looks_like_expression(text: str) -> str | None:
    if not text or len(text) > 120:
        return None
    t = clean_number_text(text).strip().rstrip("=").strip()
    if not _CALC_CHARS.match(t):
        return None
    if _DATE_LIKE.match(t) or _PHONE_LIKE.match(t):
        return None
    if not re.search(r"\d", t):
        return None
    expr = t.replace("×", "*").replace("x", "*").replace("X", "*").replace("÷", "/").replace("^", "**")
    if not re.search(r"[\d)]\s*(\*\*|[+\-*/%])\s*[\d(.-]", expr):
        return None
    if re.fullmatch(r"0\d{2,}(\s*-\s*\d+)+", expr):
        return None
    return expr

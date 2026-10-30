from __future__ import annotations

import re
from dataclasses import dataclass, field

from ..config import config
from ..utils.fmt import en_digits
from ..utils.http import get_json, safe

BINS = {
    "603799": "ملی", "589210": "سپه", "627648": "توسعه صادرات", "207177": "توسعه صادرات",
    "627961": "صنعت و معدن", "603770": "کشاورزی", "639217": "کشاورزی", "628023": "مسکن",
    "627760": "پست بانک", "502908": "توسعه تعاون", "627412": "اقتصاد نوین", "622106": "پارسیان",
    "639194": "پارسیان", "627884": "پارسیان", "502229": "پاسارگاد", "639347": "پاسارگاد",
    "627488": "کارآفرین", "502910": "کارآفرین", "621986": "سامان", "639346": "سینا",
    "639607": "سرمایه", "636214": "آینده", "502806": "شهر", "504706": "شهر", "502938": "دی",
    "603769": "صادرات", "610433": "ملت", "991975": "ملت", "585983": "تجارت", "627353": "تجارت",
    "589463": "رفاه", "627381": "انصار", "639370": "مهر اقتصاد", "505785": "ایران زمین",
    "636795": "مرکزی", "636949": "حکمت ایرانیان", "505416": "گردشگری", "606373": "قرض‌الحسنه مهر ایران",
    "628157": "موسسه اعتباری توسعه", "505801": "کوثر", "639599": "قوامین", "504172": "قرض‌الحسنه رسالت",
    "507677": "موسسه نور", "606256": "موسسه ملل", "585947": "خاورمیانه", "581874": "ایران و ونزوئلا",
    "636769": "ملل", "639238": "حکمت ایرانیان",
}

IBAN_BANKS = {
    "010": "مرکزی", "011": "صنعت و معدن", "012": "ملت", "013": "رفاه", "014": "مسکن", "015": "سپه",
    "016": "کشاورزی", "017": "ملی", "018": "تجارت", "019": "صادرات", "020": "توسعه صادرات",
    "021": "پست بانک", "022": "توسعه تعاون", "051": "موسسه اعتباری توسعه", "053": "کارآفرین",
    "054": "پارسیان", "055": "اقتصاد نوین", "056": "سامان", "057": "پاسارگاد", "058": "سرمایه",
    "059": "سینا", "060": "قرض‌الحسنه مهر ایران", "061": "شهر", "062": "آینده", "063": "انصار",
    "064": "گردشگری", "065": "حکمت ایرانیان", "066": "دی", "069": "ایران زمین", "070": "قرض‌الحسنه رسالت",
    "073": "کوثر", "075": "ملل", "078": "خاورمیانه", "080": "موسسه نور", "095": "ایران و ونزوئلا",
}

_CARD = re.compile(r"^(?:\d[\s\-_.]?){15}\d$")
_IBAN = re.compile(r"^IR\d{24}$", re.I)


def clean_card(text: str) -> str | None:
    t = en_digits(text or "").strip()
    if not _CARD.match(t):
        return None
    digits = re.sub(r"\D", "", t)
    return digits if len(digits) == 16 else None


def clean_iban(text: str) -> str | None:
    t = re.sub(r"[\s\-]", "", en_digits(text or "")).upper()
    if not t.startswith("IR") and re.fullmatch(r"\d{24}", t):
        t = "IR" + t
    return t if _IBAN.match(t) else None


def luhn_ok(digits: str) -> bool:
    total = 0
    for i, ch in enumerate(digits):
        d = int(ch)
        if i % 2 == 0:
            d *= 2
            if d > 9:
                d -= 9
        total += d
    return total % 10 == 0


def iban_ok(iban: str) -> bool:
    moved = iban[4:] + iban[:4]
    numeric = "".join(str(int(ch, 36)) for ch in moved)
    return int(numeric) % 97 == 1


def bank_of_card(digits: str) -> str | None:
    return BINS.get(digits[:6])


def bank_of_iban(iban: str) -> str | None:
    return IBAN_BANKS.get(iban[4:7])


def format_card(digits: str) -> str:
    return "-".join(digits[i:i + 4] for i in range(0, 16, 4))


@dataclass
class CardInfo:
    card: str
    valid: bool
    bank: str | None
    owner: str | None = None
    iban: str | None = None
    extra: dict = field(default_factory=dict)


_OWNER_KEYS = ("name", "owner", "fullname", "full_name", "owner_name", "ownername", "cardowner", "card_owner", "holder")
_BANK_KEYS = ("bank", "bank_name", "bankname", "bankName")
_IBAN_KEYS = ("iban", "sheba", "shaba", "IBAN")


def _flatten(data, prefix: str = "") -> dict:
    out: dict = {}
    if isinstance(data, dict):
        for k, v in data.items():
            out.update(_flatten(v, f"{prefix}{k}." if isinstance(v, (dict, list)) else f"{prefix}{k}"))
    elif isinstance(data, list):
        for i, v in enumerate(data[:5]):
            out.update(_flatten(v, f"{prefix}{i}."))
    else:
        out[prefix.rstrip(".")] = data
    return out


async def lookup_card(digits: str) -> CardInfo:
    info = CardInfo(digits, luhn_ok(digits), bank_of_card(digits))
    if not config.cardinfo_api_url:
        return info
    data = await safe(get_json(
        config.cardinfo_api_url, params={"number": digits},
        headers={"Authorization": f"Bearer {config.cardinfo_api_token}",
                 "Content-Type": "application/x-www-form-urlencoded"},
        timeout=12,
    ), label="cardinfo")
    if not isinstance(data, (dict, list)):
        return info
    flat = _flatten(data)
    skip = {"status", "ok", "success", "code", "message", "number", "card", "cardnumber", "card_number"}
    for key, value in flat.items():
        leaf = key.split(".")[-1]
        low = leaf.lower()
        if value in (None, "", [], {}) or low in skip:
            continue
        if low in _OWNER_KEYS:
            info.owner = info.owner or str(value)
        elif low in {k.lower() for k in _BANK_KEYS}:
            info.bank = info.bank or str(value)
        elif low in {k.lower() for k in _IBAN_KEYS}:
            info.iban = info.iban or str(value)
        elif isinstance(value, (str, int, float)) and len(info.extra) < 6:
            info.extra[leaf] = value
    return info

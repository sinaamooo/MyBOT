from __future__ import annotations

import html as html_lib
import re

from ..utils.fmt import en_digits
from ..utils.http import get_text, safe, ttl_cache
from .currencies import FIAT, normalize

_ROW = re.compile(r"<tr[^>]*id=[\"']f-diff_[^\"']*[\"'][^>]*>(.*?)</tr>", re.S | re.I)
_TH = re.compile(r"<th[^>]*>(.*?)</th>", re.S | re.I)
_TD = re.compile(r"<td[^>]*>(.*?)</td>", re.S | re.I)

_NAME_INDEX = {normalize(v[0]): k for k, v in FIAT.items()}
_NAME_INDEX.update({normalize(a): k for k, v in FIAT.items() for a in v[4] if not a.isascii()})


def _text(fragment: str) -> str:
    return html_lib.unescape(re.sub(r"<[^>]+>", " ", fragment)).strip()


def _number(text: str) -> float | None:
    digits = re.sub(r"[^\d.]", "", en_digits(text).replace(",", "").replace("٬", ""))
    try:
        return float(digits) if digits else None
    except ValueError:
        return None


def parse(page: str) -> dict[str, dict]:
    out: dict[str, dict] = {}
    for row in _ROW.findall(page or ""):
        th = _TH.search(row)
        tds = [_text(td) for td in _TD.findall(row)]
        if not th or not tds:
            continue
        title = re.sub(r"\s+", " ", _text(th.group(1)))
        code_match = re.search(r"\b([A-Z]{3})\b", title)
        code = code_match.group(1) if code_match else None
        if not code:
            name = normalize(re.sub(r"[A-Za-z]", "", title))
            code = _NAME_INDEX.get(name) or next((c for n, c in _NAME_INDEX.items() if n and n in name), None)
        price = _number(tds[0])
        if not code or not price:
            continue
        change = tds[2] if len(tds) > 2 else None
        out[code] = {"rial": price, "toman": price / 10, "change": change, "title": title}
    return out


@ttl_cache(600)
async def market_rates() -> dict[str, dict]:
    page = await safe(get_text("https://www.tgju.org/diff", timeout=15), label="tgju")
    return parse(page or "")


async def toman_rate(code: str, cross_toman: float | None) -> float | None:
    rates = await market_rates()
    row = rates.get(code.upper())
    if not row:
        return None
    value = row["toman"]
    if cross_toman:
        ratio = value / cross_toman
        if 5 < ratio < 15:
            value /= 10
            ratio /= 10
        if not 0.5 < ratio < 2:
            return None
    return value

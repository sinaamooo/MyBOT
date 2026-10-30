from __future__ import annotations

import asyncio
import html as html_lib
import logging
import re
import time
from dataclasses import dataclass, field

from ..config import config
from ..utils.http import get_bytes, get_json, get_text, safe, ttl_cache

log = logging.getLogger(__name__)

DATA_REPO = "ssamy2/TelegramGiftsAssests"
RAW = f"https://raw.githubusercontent.com/{DATA_REPO}"

_LINK = re.compile(
    r"(?:https?://)?(?:t\.me|telegram\.me|telegram\.dog)/nft/([A-Za-z0-9]+)-(\d+)"
    r"|(?:https?://)?nft\.fragment\.com/gift/([A-Za-z0-9]+)-(\d+)",
    re.I,
)


def norm(name: str) -> str:
    return re.sub(r"[^a-z0-9]", "", (name or "").lower())


def parse_link(text: str) -> tuple[str, int] | None:
    m = _LINK.search(text or "")
    if not m:
        return None
    slug = m.group(1) or m.group(3)
    num = m.group(2) or m.group(4)
    return slug, int(num)


def find_links(text: str) -> list[tuple[str, int]]:
    out = []
    for m in _LINK.finditer(text or ""):
        out.append((m.group(1) or m.group(3), int(m.group(2) or m.group(4))))
    return out


@ttl_cache(600)
async def dataset() -> dict:
    data = await safe(get_json(f"{RAW}/main/Gifts_Details.json", timeout=20), {})
    index: dict[str, dict] = {}
    by_id: dict[str, dict] = {}
    for item in (data or {}).get("upgraded", []) or []:
        for key in (item.get("full_name"), item.get("short_name")):
            if key:
                index[norm(key)] = item
        if item.get("regular_id"):
            by_id[str(item["regular_id"])] = item
    unupgraded = {str(i.get("id")): i for i in (data or {}).get("unupgraded", []) or [] if i.get("id")}
    return {"index": index, "by_id": by_id, "unupgraded": unupgraded, "updated": (data or {}).get("last_updated")}


@ttl_cache(600)
async def attribute_floors(short_name: str) -> dict:
    data = await safe(get_json(f"{RAW}/main/models/{short_name}/prices.json", timeout=15), {})
    return {k: {norm(n): v for n, v in (data.get(k) or {}).items()} for k in ("models", "backdrops", "symbols")} if data else {}


@ttl_cache(6 * 3600)
async def floors_days_ago(days: int = 7) -> dict[str, float]:
    until = time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime(time.time() - days * 86400))
    commits = await safe(get_json(f"https://api.github.com/repos/{DATA_REPO}/commits",
                                  params={"until": until, "per_page": 1, "path": "Gifts_Details.json"}, timeout=15))
    if not isinstance(commits, list) or not commits:
        return {}
    sha = commits[0].get("sha")
    old = await safe(get_json(f"{RAW}/{sha}/Gifts_Details.json", timeout=20), {})
    return {norm(i.get("full_name", "")): float(i.get("floor_price_ton") or 0)
            for i in (old or {}).get("upgraded", []) or [] if i.get("floor_price_ton")}


async def portals_floor(short: str) -> float | None:
    if not config.portals_auth:
        return None
    data = await safe(get_json("https://portals-market.com/api/collections/floors",
                               headers={"Authorization": config.portals_auth}, timeout=10))
    floors = (data or {}).get("floorPrices") or {}
    val = floors.get(short) or floors.get(short.replace("_", ""))
    try:
        return float(val) if val else None
    except (TypeError, ValueError):
        return None


@dataclass
class Valuation:
    collection: str | None
    floor: float | None
    model_floor: float | None
    backdrop_floor: float | None
    symbol_floor: float | None
    estimate: float | None
    floor_7d: float | None = None


async def value_of(collection_name: str, model: str | None, backdrop: str | None, symbol: str | None) -> Valuation:
    ds = await dataset()
    item = ds["index"].get(norm(collection_name))
    if not item:
        return Valuation(None, None, None, None, None, None)
    floor = item.get("floor_price_ton")
    live = await portals_floor(item.get("short_name", ""))
    if live:
        floor = min(floor, live) if floor else live
    attrs = await attribute_floors(item.get("short_name", ""))
    mf = (attrs.get("models") or {}).get(norm(model or "")) if model else None
    bf = (attrs.get("backdrops") or {}).get(norm(backdrop or "")) if backdrop else None
    sf = (attrs.get("symbols") or {}).get(norm(symbol or "")) if symbol else None
    candidates = [v for v in (floor, mf, bf, sf) if v]
    estimate = max(candidates) if candidates else None
    old = (await floors_days_ago()).get(norm(item.get("full_name", "")))
    return Valuation(item.get("full_name"), floor, mf, bf, sf, estimate, old)


@dataclass
class GiftMeta:
    slug: str
    number: int
    name: str
    model: str | None = None
    backdrop: str | None = None
    symbol: str | None = None
    model_rarity: str | None = None
    backdrop_rarity: str | None = None
    symbol_rarity: str | None = None
    owner: str | None = None
    quantity: str | None = None
    image: str | None = None
    extra: dict = field(default_factory=dict)

    @property
    def link(self) -> str:
        return f"https://t.me/nft/{self.slug}-{self.number}"


def _clean(s: str) -> str:
    return html_lib.unescape(re.sub(r"<[^>]+>", "", s or "")).strip()


def _split_name(slug: str) -> str:
    return re.sub(r"(?<=[a-z])(?=[A-Z])", " ", slug)


async def _from_fragment(slug: str, number: int) -> GiftMeta | None:
    data = await get_json(f"https://nft.fragment.com/gift/{slug.lower()}-{number}.json", timeout=10)
    if not isinstance(data, dict) or not data.get("attributes"):
        return None
    meta = GiftMeta(slug, number, re.sub(r"\s*#\d+$", "", data.get("name") or _split_name(slug)))
    for attr in data.get("attributes") or []:
        trait = str(attr.get("trait_type", "")).lower()
        if trait in ("model", "backdrop", "symbol"):
            setattr(meta, trait, attr.get("value"))
    meta.image = data.get("image")
    return meta


async def _from_tme(slug: str, number: int) -> GiftMeta | None:
    page = await get_text(f"https://t.me/nft/{slug}-{number}", timeout=10)
    meta = GiftMeta(slug, number, _split_name(slug))
    title = re.search(r'<meta property="og:title" content="([^"]+)"', page)
    if title:
        meta.name = re.sub(r"\s*#\d+$", "", html_lib.unescape(title.group(1)))
    img = re.search(r'<meta property="og:image" content="([^"]+)"', page)
    if img:
        meta.image = img.group(1)
    found = False
    for th, td in re.findall(r"<th[^>]*>(.*?)</th>\s*<td[^>]*>(.*?)</td>", page, re.S | re.I):
        key = _clean(th).lower()
        rarity = re.search(r"<mark[^>]*>(.*?)</mark>", td, re.S)
        value = _clean(re.sub(r"<mark[^>]*>.*?</mark>", "", td, flags=re.S))
        if key in ("model", "backdrop", "symbol"):
            setattr(meta, key, value)
            if rarity:
                setattr(meta, f"{key}_rarity", _clean(rarity.group(1)))
            found = True
        elif key == "owner":
            meta.owner = value
        elif key in ("quantity", "issued"):
            meta.quantity = value
    if not found:
        desc = re.search(r'<meta property="og:description" content="([^"]+)"', page)
        text = html_lib.unescape(desc.group(1)) if desc else ""
        for key in ("Model", "Backdrop", "Symbol"):
            m = re.search(rf"{key}:\s*([^\n(]+?)\s*(?:\(([\d.,]+%)\))?(?:\n|$)", text)
            if m:
                setattr(meta, key.lower(), m.group(1).strip())
                if m.group(2):
                    setattr(meta, f"{key.lower()}_rarity", m.group(2))
                found = True
    return meta if found else None


@ttl_cache(300)
async def gift_meta(slug: str, number: int) -> GiftMeta | None:
    tme = await safe(_from_tme(slug, number), label="tme")
    if tme and tme.model:
        return tme
    frag = await safe(_from_fragment(slug, number), label="fragment")
    if frag:
        if tme:
            frag.owner = tme.owner
            frag.quantity = tme.quantity
        return frag
    return tme


async def gift_image(slug_number: str) -> bytes | None:
    for url in (f"https://nft.fragment.com/gift/{slug_number.lower()}.small.jpg",
                f"https://nft.fragment.com/gift/{slug_number.lower()}.webp"):
        data = await safe(get_bytes(url, timeout=6))
        if data and len(data) > 500:
            return data
    return None


async def gather_limited(coros, limit: int = 8):
    sem = asyncio.Semaphore(limit)

    async def run(c):
        async with sem:
            return await c

    return await asyncio.gather(*(run(c) for c in coros))

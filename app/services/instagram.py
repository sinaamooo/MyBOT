from __future__ import annotations

import re

from ..config import config
from ..utils.http import get_json, safe

LINK = re.compile(r"(?:https?://)?(?:www\.)?instagram\.com/(?:[A-Za-z0-9_.]+/)?(?:p|reel|reels|tv|stories)/[^\s]+", re.I)
USERNAME = re.compile(r"^@?([A-Za-z0-9_.]{1,30})$")


class InstaError(Exception):
    def __init__(self, code: int):
        super().__init__(code)
        self.code = code


def enabled() -> bool:
    return bool(config.instagram_api_key and config.instagram_api_url)


async def _call(**params) -> dict:
    data = await get_json(config.instagram_api_url, params={"key": config.instagram_api_key, **params}, timeout=60)
    if not isinstance(data, dict):
        raise InstaError(0)
    status = int(data.get("status") or 0)
    if status != 200:
        raise InstaError(status)
    return data


def find_link(text: str) -> str | None:
    m = LINK.search(text or "")
    return m.group(0) if m else None


async def post_medias(link: str) -> list[dict]:
    data = await _call(type="postdownloader", link=link)
    medias = (data.get("result") or {}).get("medias") or []
    return [{"url": m.get("url"), "kind": "video" if str(m.get("extension", "")).lower() in ("mp4", "mov") else "photo"}
            for m in medias if m.get("url")]


async def profile(username: str) -> dict:
    data = await _call(type="instainfo", username=username)
    result = data.get("result")
    if isinstance(result, list):
        result = result[0] if result else {}
    return result or {}


async def stories(username: str) -> list[dict]:
    data = await _call(type="pagestory", username=username)
    return [{"url": s.get("story"), "kind": "video" if s.get("type") == "video" else "photo"}
            for s in data.get("result") or [] if s.get("story")]


async def highlights(username: str) -> list[dict]:
    data = await _call(type="pagehightlight", username=username)
    return [{"title": h.get("Title") or "Highlight", "id": str(h.get("hightlightIDS"))}
            for h in data.get("result") or [] if h.get("hightlightIDS")]


async def highlight_items(highlight_id: str) -> list[dict]:
    data = await _call(type="gethightlight", id=highlight_id)
    return [{"url": h.get("highlite"), "kind": "video" if h.get("type") == "video" else "photo"}
            for h in data.get("result") or [] if h.get("highlite")]


async def safe_call(coro, default=None):
    return await safe(coro, default, label="instagram")

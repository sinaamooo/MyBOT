"""Translation with several free back-ends (Google gtx, MyMemory, Lingva)."""
from __future__ import annotations

import re
from urllib.parse import quote

from ..utils.http import get_json, safe

LANGS = {
    "fa": ("فارسی", "fa", "fa-IR"),
    "en": ("انگلیسی", "en", "en-GB"),
    "ru": ("روسی", "ru", "ru-RU"),
    "zh": ("چینی", "zh-CN", "zh-CN"),
    "ar": ("عربی", "ar", "ar-SA"),
}


def guess_source(text: str) -> str:
    if re.search(r"[؀-ۿ]", text):
        return "fa" if re.search(r"[پچژگکی]", text) else "ar"
    if re.search(r"[Ѐ-ӿ]", text):
        return "ru"
    if re.search(r"[一-鿿]", text):
        return "zh-CN"
    return "en"


async def _google(text: str, target: str) -> str | None:
    data = await get_json(
        "https://translate.googleapis.com/translate_a/single",
        params={"client": "gtx", "sl": "auto", "tl": target, "dt": "t", "q": text},
        timeout=10,
    )
    if isinstance(data, list) and data and isinstance(data[0], list):
        return "".join(part[0] for part in data[0] if part and part[0]) or None
    return None


async def _mymemory(text: str, target: str, target_full: str) -> str | None:
    src = guess_source(text)
    if src.split("-")[0] == target.split("-")[0]:
        return text
    data = await get_json(
        "https://api.mymemory.translated.net/get",
        params={"q": text[:480], "langpair": f"{src}|{target_full}"},
        timeout=10,
    )
    out = ((data or {}).get("responseData") or {}).get("translatedText")
    return out if out and "MYMEMORY WARNING" not in out else None


async def _lingva(text: str, target: str) -> str | None:
    for host in ("https://lingva.ml", "https://lingva.lunar.icu"):
        data = await safe(get_json(f"{host}/api/v1/auto/{target}/{quote(text[:1500], safe='')}", timeout=10))
        if data and data.get("translation"):
            return data["translation"]
    return None


async def translate(text: str, lang_key: str) -> str | None:
    _label, target, target_full = LANGS[lang_key]
    chunks = [text[i:i + 3500] for i in range(0, len(text), 3500)] or [text]
    out = []
    for chunk in chunks:
        result = (
            await safe(_google(chunk, target), label="google")
            or await safe(_lingva(chunk, target), label="lingva")
            or await safe(_mymemory(chunk, target, target_full), label="mymemory")
        )
        if result is None:
            return None
        out.append(result)
    return "".join(out)

"""Text rendering (templates + premium emoji) and glass (inline) buttons."""
from __future__ import annotations

import html
import re
import unicodedata
from typing import Any, Iterable

from aiogram.types import CopyTextButton, InlineKeyboardButton, InlineKeyboardMarkup

from .db import get_db
from .settings import gget
from .texts import BUTTONS, TEXTS

BOT_USERNAME = ""  # filled on startup

_PLACEHOLDER = re.compile(r"\{([a-zA-Z_][a-zA-Z0-9_]*)\}")
_TAG = re.compile(r"(<[^>]+>)")
_TAG_NAME = re.compile(r"^</?\s*([a-zA-Z][a-zA-Z0-9-]*)")
_VOID_SAFE = {"br"}


def esc(value: Any) -> str:
    return html.escape(str(value), quote=False)


# ---------------------------------------------------------------- templates
def _unbalanced_tags(line: str) -> tuple[list[str], list[str]]:
    """Return (unclosed opening tags, unmatched closing tags) of a line."""
    opened: list[tuple[str, str]] = []
    closing: list[str] = []
    for tag in _TAG.findall(line):
        m = _TAG_NAME.match(tag)
        if not m:
            continue
        name = m.group(1).lower()
        if name in _VOID_SAFE:
            continue
        if tag.startswith("</"):
            if opened and opened[-1][0] == name:
                opened.pop()
            else:
                closing.append(tag)
        else:
            opened.append((name, tag))
    return [t for _, t in opened], closing


def fill(template: str, **values: Any) -> str:
    """Fill ``{placeholders}``. Lines whose placeholder value is None are dropped."""
    out_lines: list[str] = []
    carry = ""  # opening tags from dropped lines, prepended to the next kept line
    for line in template.split("\n"):
        names = _PLACEHOLDER.findall(line)
        if any(n in values and values[n] is None for n in names):
            opening, closing = _unbalanced_tags(line)
            if closing:
                if out_lines:
                    out_lines[-1] += "".join(closing)
                else:
                    carry += "".join(closing)
            carry += "".join(opening)
            continue
        line = _PLACEHOLDER.sub(
            lambda m: str(values[m.group(1)]) if m.group(1) in values else m.group(0), line
        )
        out_lines.append(carry + line)
        carry = ""
    if carry and out_lines:
        out_lines[-1] += carry
    text = "\n".join(out_lines)
    text = re.sub(r"\n{3,}", "\n\n", text)
    return text.strip()


def template(key: str) -> str:
    override = get_db().text_override(key)
    if override is not None:
        return override
    return TEXTS[key][0] if key in TEXTS else key


def t(key: str, **values: Any) -> str:
    return apply_emoji_map(fill(template(key), **values))


# ---------------------------------------------------------- premium emoji
def _emoji_regex(emoji_map: dict[str, str]) -> re.Pattern | None:
    keys = sorted((k for k in emoji_map if k), key=len, reverse=True)
    if not keys:
        return None
    parts = []
    for key in keys:
        bare = key.replace("️", "")
        parts.append("".join(re.escape(ch) + "️?" for ch in bare))
    return re.compile("|".join(parts))


_emoji_cache: tuple[int, re.Pattern | None] = (0, None)


def apply_emoji_map(text: str) -> str:
    """Wrap configured emojis with <tg-emoji> so they render as premium emoji."""
    global _emoji_cache
    emoji_map: dict[str, str] = gget("emoji_map") or {}
    if not emoji_map or not text:
        return text
    sig = hash(tuple(sorted(emoji_map.items())))
    if _emoji_cache[0] != sig:
        _emoji_cache = (sig, _emoji_regex(emoji_map))
    rx = _emoji_cache[1]
    if rx is None:
        return text
    lookup = {k.replace("️", ""): v for k, v in emoji_map.items()}

    out = []
    skip_depth = 0
    for part in _TAG.split(text):
        if part.startswith("<") and part.endswith(">"):
            m = _TAG_NAME.match(part)
            name = m.group(1).lower() if m else ""
            if name in ("tg-emoji", "code", "pre", "a"):
                skip_depth += -1 if part.startswith("</") else 1
                skip_depth = max(skip_depth, 0)
            out.append(part)
            continue
        if skip_depth:
            out.append(part)
            continue
        out.append(
            rx.sub(
                lambda m: f'<tg-emoji emoji-id="{lookup.get(m.group(0).replace(chr(0xFE0F), ""), "")}">{m.group(0)}</tg-emoji>',
                part,
            )
        )
    return "".join(out)


def strip_premium(text: str) -> str:
    return re.sub(r"<tg-emoji[^>]*>(.*?)</tg-emoji>", r"\1", text or "", flags=re.S)


def _is_emoji_token(token: str) -> bool:
    if not token:
        return False
    for ch in token:
        cp = ord(ch)
        if ch in "‍️⃣" or 0x1F1E6 <= cp <= 0x1F1FF:
            continue
        cat = unicodedata.category(ch)
        if cat.startswith(("L", "N", "P")) and not (0x2000 <= cp <= 0x2BFF or 0x3030 <= cp <= 0x303D):
            return False
    return True


def split_leading_emoji(text: str) -> tuple[str, str]:
    """'💎 Hello' -> ('💎', 'Hello')"""
    text = text.strip()
    head, _, rest = text.partition(" ")
    if rest and _is_emoji_token(head):
        return head, rest.strip()
    return "", text


# ------------------------------------------------------------------ buttons
def button(
    key: str,
    *,
    url: str | None = None,
    callback_data: str | None = None,
    copy_text: str | None = None,
    **values: Any,
) -> InlineKeyboardButton:
    conf: dict = (gget("buttons") or {}).get(key, {})
    default_text, default_style, _ = BUTTONS.get(key, (key, None, ""))
    text = fill(conf.get("text") or default_text, **values)
    style = conf.get("style", default_style)
    if style in ("", "none", None):
        style = None
    emoji_id = conf.get("emoji_id") or None
    lead, rest = split_leading_emoji(text)
    if not emoji_id and lead:
        emoji_map = gget("emoji_map") or {}
        emoji_id = emoji_map.get(lead) or emoji_map.get(lead.replace("️", ""))
    if emoji_id and lead:
        text = rest
    kwargs: dict[str, Any] = {"text": text}
    if url:
        kwargs["url"] = url
    elif copy_text is not None:
        kwargs["copy_text"] = CopyTextButton(text=str(copy_text)[:256])
    else:
        kwargs["callback_data"] = callback_data or "noop"
    if emoji_id:
        kwargs["icon_custom_emoji_id"] = str(emoji_id)
    if style:
        kwargs["style"] = style
    return InlineKeyboardButton(**kwargs)


def kb(rows: Iterable[Iterable[InlineKeyboardButton]]) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=[list(r) for r in rows if r])


def channel_url() -> str:
    from .config import config

    return gget("channel_url") or config.channel_url


def footer_rows() -> list[list[InlineKeyboardButton]]:
    row = []
    if BOT_USERNAME:
        row.append(button("btn_add_group", url=f"https://t.me/{BOT_USERNAME}?startgroup=true"))
    url = channel_url()
    if url:
        row.append(button("btn_channel", url=url))
    return [row] if row else []


def footer_kb(extra_rows: list[list[InlineKeyboardButton]] | None = None) -> InlineKeyboardMarkup | None:
    rows = (extra_rows or []) + footer_rows()
    return kb(rows) if rows else None


def mention(user) -> str:
    name = esc(getattr(user, "full_name", None) or getattr(user, "first_name", None) or "کاربر")
    return f'<a href="tg://user?id={user.id}">{name}</a>'


def username_or_dash(username: str | None) -> str:
    return f"@{esc(username)}" if username else "—"


def plain(key: str, **values: Any) -> str:
    """Rendered text without HTML (for callback popups, max 200 chars)."""
    text = re.sub(r"<[^>]+>", "", fill(template(key), **values))
    return html.unescape(text)[:200]

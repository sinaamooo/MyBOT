from __future__ import annotations

import hashlib
import re
import time
import unicodedata
from collections import defaultdict, deque
from datetime import datetime, timedelta, timezone

from ..utils.fmt import TEHRAN, en_digits

SCAM_PATTERNS = [
    r"بیا\s*(?:پی\s*وی|پیوی|پیو|پی|پ\s*و|خصوصی|دایرکت|دایرکتم)",
    r"(?:پی\s*وی|پیوی|پی)\s*(?:بیا|بیاین|بیاید|پیام\s*بده|چک\s*کن)",
    r"(?:پیام|پی\s*ام)\s*(?:خصوصی|بده\s*خصوصی)",
    r"خصوصی\s*(?:پیام\s*بده|بیا|صحبت\s*کنیم)",
    r"بدون\s*واسطه", r"واسطه\s*(?:نمی\s*خواد|لازم\s*نیست|نمیخواد)",
    r"اول\s*(?:تو|شما)\s*(?:بفرست|واریز|بزن)",
    r"(?:عبارت|کلمات)\s*(?:بازیابی|امنیتی)", r"(?:12|۱۲|24|۲۴)\s*کلمه", r"کلید\s*خصوصی",
    r"کد\s*(?:تایید|ورود|لاگین)\s*(?:رو|را)?\s*(?:بفرست|بده)",
    r"\b(?:pv|pm|dm)\s*(?:bia|biya|me|plz|pls)\b", r"\bbia\s*(?:pv|pm|dm)\b", r"\bcome\s*(?:pv|pm|dm)\b",
    r"\binbox\s*me\b", r"\bseed\s*phrase\b", r"\bprivate\s*key\b", r"\bsend\s*first\b",
]
_SCAM_RX = re.compile("|".join(f"(?:{p})" for p in SCAM_PATTERNS), re.I)


def normalize_fa(text: str) -> str:
    text = en_digits(text or "").lower()
    text = text.replace("ي", "ی").replace("ك", "ک").replace("‌", " ")
    return re.sub(r"\s+", " ", text)


def is_scammy(text: str, extra: list[str] | None = None) -> bool:
    t = normalize_fa(text)
    if _SCAM_RX.search(t):
        return True
    return any(k and normalize_fa(k) in t for k in (extra or []))


_CLOCK = re.compile(r"(?<!\d)([01]?\d|2[0-3])\s*[:：︰∶.]\s*([0-5]\d)(?!\d)")


def _plain_digits(text: str) -> str:
    out = []
    for ch in text or "":
        try:
            d = unicodedata.digit(ch)
            out.append(str(d))
        except (TypeError, ValueError):
            out.append(ch)
    return "".join(out)


def clock_in_name(name: str) -> tuple[int, int] | None:
    m = _CLOCK.search(_plain_digits(name))
    return (int(m.group(1)), int(m.group(2))) if m else None


def clock_is_now(hm: tuple[int, int], tolerance_min: int = 3) -> bool:
    now_utc = datetime.now(timezone.utc)
    zones = [TEHRAN, timezone.utc, timezone(timedelta(hours=3)), timezone(timedelta(hours=4))]
    for tz in zones:
        now = now_utc.astimezone(tz)
        for fmt12 in (False, True):
            h = now.hour % 12 or 12 if fmt12 else now.hour
            diff = abs((h * 60 + now.minute) - (hm[0] * 60 + hm[1]))
            if diff <= tolerance_min or diff >= 24 * 60 - tolerance_min:
                return True
    return False


FULL_FORMAT_TYPES = {"bold", "italic", "underline", "strikethrough", "spoiler", "code", "pre", "blockquote",
                     "expandable_blockquote"}


def _utf16_len(text: str) -> int:
    return len(text.encode("utf-16-le")) // 2


def fully_formatted(text: str | None, entities) -> bool:
    if not text or not entities or len(text) < 2:
        return False
    total = _utf16_len(text)
    for e in entities:
        if e.type in FULL_FORMAT_TYPES and e.offset == 0 and e.length >= total - 1:
            return True
    return False


class SelfDetector:

    WINDOW = 15 * 60
    THRESHOLD = 6

    def __init__(self):
        self.events: dict[tuple[int, int], deque] = defaultdict(deque)
        self.formatted_streak: dict[tuple[int, int], int] = defaultdict(int)
        self.names: dict[int, str] = {}
        self.warned: dict[tuple[int, int], float] = {}

    def add(self, key: tuple[int, int], points: int, reason: str) -> int:
        now = time.time()
        q = self.events[key]
        q.append((now, points, reason))
        while q and now - q[0][0] > self.WINDOW:
            q.popleft()
        return sum(p for _, p, _ in q)

    def score(self, key: tuple[int, int]) -> int:
        now = time.time()
        q = self.events[key]
        while q and now - q[0][0] > self.WINDOW:
            q.popleft()
        return sum(p for _, p, _ in q)

    def reset(self, key: tuple[int, int]) -> None:
        self.events.pop(key, None)
        self.formatted_streak.pop(key, None)

    def on_message(self, chat_id: int, user, text: str | None, entities, reply_delta: float | None) -> int:
        key = (chat_id, user.id)
        score = self.score(key)
        name = f"{user.first_name or ''} {user.last_name or ''}"
        hm = clock_in_name(name)
        prev = self.names.get(user.id)
        self.names[user.id] = name
        if hm:
            if clock_is_now(hm):
                score = self.add(key, 4, "clock_now")
            if prev and prev != name and clock_in_name(prev):
                score = self.add(key, 4, "clock_changed")
        if fully_formatted(text, entities):
            self.formatted_streak[key] += 1
            if self.formatted_streak[key] >= 3:
                score = self.add(key, 2, "auto_format")
        else:
            self.formatted_streak[key] = 0
        if reply_delta is not None and reply_delta <= 0:
            score = self.add(key, 1, "instant_reply")
        return score

    def on_edit(self, chat_id: int, user_id: int, delta: float) -> int:
        key = (chat_id, user_id)
        if delta <= 1:
            return self.add(key, 3, "instant_edit")
        if delta <= 3:
            return self.add(key, 2, "fast_edit")
        return self.score(key)


_LINK = re.compile(r"(?:https?://|www\.|t\.me/|telegram\.(?:me|dog)/|tg://)", re.I)
_INVITE = re.compile(r"(?:t\.me|telegram\.me)/(?:\+|joinchat/)", re.I)
_MENTION = re.compile(r"(?<![\w@])@[A-Za-z][A-Za-z0-9_]{4,}")
AD_WORDS = re.compile(
    r"عضو\s*شو|جوین\s*شو|جوین\s*بده|کانال\s*(?:ما|من)|تبلیغ|ممبر|فالوور|سفارش|ارزان|تخفیف|فروش\s*ویژه|"
    r"درآمد|کسب\s*درآمد|ثبت\s*نام|join|subscribe|promo|free\s*money|airdrop|ایردراپ|بونوس|bonus",
    re.I,
)


class TabchiDetector:
    THRESHOLD = 5

    def __init__(self):
        self.join_times: dict[tuple[int, int], float] = {}
        self.msg_counts: dict[tuple[int, int], int] = defaultdict(int)
        self.history: dict[int, deque] = defaultdict(lambda: deque(maxlen=30))

    def joined(self, chat_id: int, user_id: int) -> None:
        self.join_times[(chat_id, user_id)] = time.time()
        self.msg_counts.pop((chat_id, user_id), None)

    def evaluate(self, message) -> tuple[int, list[str]]:
        user = message.from_user
        chat_id = message.chat.id
        key = (chat_id, user.id)
        text = message.text or message.caption or ""
        reasons: list[str] = []
        score = 0
        now = time.time()

        joined = self.join_times.get(key)
        if joined:
            age = now - joined
            if age < 30:
                score += 3
                reasons.append("joined<30s")
            elif age < 180:
                score += 2
                reasons.append("joined<3m")
        self.msg_counts[key] += 1
        first = self.msg_counts[key] == 1

        has_link = bool(_LINK.search(text)) or any(
            e.type in ("url", "text_link") for e in (message.entities or message.caption_entities or [])
        )
        if _INVITE.search(text):
            score += 3
            reasons.append("invite_link")
        elif has_link:
            score += 2
            reasons.append("link")
        if _MENTION.search(text) and not message.reply_to_message:
            score += 1
            reasons.append("mention")
        if AD_WORDS.search(text):
            score += 1
            reasons.append("ad_words")
        origin = getattr(message, "forward_origin", None)
        if origin is not None and getattr(origin, "type", "") == "channel":
            score += 2
            reasons.append("forward_channel")
        if message.via_bot and message.reply_markup:
            score += 2
            reasons.append("inline_buttons")
        if first and (has_link or origin is not None):
            score += 1
            reasons.append("first_message")

        if text and len(text) >= 15:
            digest = hashlib.md5(normalize_fa(text).encode()).hexdigest()
            hist = self.history[user.id]
            other_chats = {c for ts, c, h in hist if h == digest and c != chat_id and now - ts < 900}
            same_chat = sum(1 for ts, c, h in hist if h == digest and c == chat_id and now - ts < 600)
            hist.append((now, chat_id, digest))
            if other_chats:
                score += 4
                reasons.append("cross_group_duplicate")
            if same_chat >= 2:
                score += 3
                reasons.append("repeated")
        return score, reasons


self_detector = SelfDetector()
tabchi_detector = TabchiDetector()

"""Free-text dispatcher: UID, translate, gift links, wallets / hashes, conversions, prices, calculator."""
from __future__ import annotations

import logging
import re

from aiogram import Bot, F, Router
from aiogram.types import Message

from ..services import gifts
from ..services.chain_common import extract
from ..services.currencies import is_known_crypto_word, normalize
from ..settings import feature_on
from ..utils.parse import looks_like_expression, parse_amount
from . import gifts as gift_handlers
from . import tools

log = logging.getLogger(__name__)
router = Router(name="text")

UID_WORDS = {"uid", "آیدی عددی", "ایدی عددی", "آیدی", "ایدی", "id", "ایدیش", "آیدیش"}
TRANSLATE_WORDS = {"ترجمه", "ترجمه کن", "translate", "tr", "ترجمش کن", "ترجمه شود"}
PRICE_PREFIX = re.compile(r"^(?:قیمت|نرخ|price|ارزش)\s+(.+)$", re.I)


@router.message(F.text | F.caption)
async def on_text(message: Message, bot: Bot):
    text = (message.text or message.caption or "").strip()
    if not text or text.startswith("/"):
        return
    chat_id = message.chat.id
    private = message.chat.type == "private"
    low = normalize(text)

    # 1) UID on reply
    if message.reply_to_message and low in UID_WORDS:
        await tools.send_uid(message)
        return

    # 2) translation request on reply
    if message.reply_to_message and low in TRANSLATE_WORDS:
        if await feature_on(chat_id, "translate"):
            await tools.ask_translation(message)
        return

    # 3) gift links
    link = gifts.parse_link(text)
    if link and (private or await feature_on(chat_id, "gifts")):
        await gift_handlers.answer_gift_link(message, *link)
        return

    # 4) wallet address / transaction hash
    found = extract(text, strict=not private)
    if found and (private or await feature_on(chat_id, "checkers")):
        await tools.run_checker(message, *found)
        return

    if len(text) > 60:
        return

    conversions = private or await feature_on(chat_id, "conversions")

    # 5) "<amount> <unit>"
    parsed = parse_amount(text)
    if parsed and conversions:
        if await tools.answer_amount(message, parsed[0], parsed[1], silent=not private):
            return

    # 6) "قیمت X" / single known coin word
    m = PRICE_PREFIX.match(text)
    if m and conversions:
        target = m.group(1).strip()
        sub = parse_amount(target)
        if sub:
            await tools.answer_amount(message, sub[0], sub[1], silent=not private)
        else:
            await tools.answer_amount(message, 1, target, silent=not private)
        return
    if conversions and is_known_crypto_word(text):
        await tools.answer_amount(message, 1, text, silent=True)
        return

    # 7) calculator
    expr = looks_like_expression(text)
    if expr and (private or await feature_on(chat_id, "calculator")):
        await tools.answer_calc(message, expr)
        return

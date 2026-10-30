from __future__ import annotations

import logging
import re

from aiogram import Bot, F, Router
from aiogram.types import Message

from ..services import banking, gifts, instagram, phone
from ..services.chain_common import extract
from ..services.currencies import is_known_crypto_word, normalize
from ..settings import feature_on
from ..utils.parse import looks_like_expression, parse_amount
from . import extras, tools
from . import gifts as gift_handlers

log = logging.getLogger(__name__)
router = Router(name="text")

UID_WORDS = {"uid", "آیدی عددی", "ایدی عددی", "آیدی", "ایدی", "id", "ایدیش", "آیدیش"}
TRANSLATE_WORDS = {"ترجمه", "ترجمه کن", "translate", "tr", "ترجمش کن", "ترجمه شود"}
PRICE_PREFIX = re.compile(r"^(?:قیمت|نرخ|price|ارزش)\s+(.+)$", re.I)
WALLET_PREFIX = re.compile(r"^(?:wallet|ولت|کیف پول|check|چک|hash|هش|tx)\s+(.+)$", re.I | re.S)
PHONE_PREFIX = re.compile(r"^(?:phone|چک شماره|شماره|number)\s+(.+)$", re.I)


@router.message(F.text | F.caption)
async def on_text(message: Message, bot: Bot):
    text = (message.text or message.caption or "").strip()
    if not text or text.startswith("/"):
        return
    chat_id = message.chat.id
    private = message.chat.type == "private"
    low = normalize(text)

    if message.reply_to_message and low in UID_WORDS:
        await tools.send_uid(message)
        return

    if message.reply_to_message and low in TRANSLATE_WORDS:
        if await feature_on(chat_id, "translate"):
            await tools.ask_translation(message)
        return

    link = gifts.parse_link(text)
    if link and (private or await feature_on(chat_id, "gifts")):
        await gift_handlers.answer_gift_link(message, *link)
        return

    insta = instagram.find_link(text)
    if insta and (private or await feature_on(chat_id, "instagram")):
        await extras.answer_insta_link(message, insta)
        return

    m = WALLET_PREFIX.match(text)
    if m and (private or await feature_on(chat_id, "checkers")):
        found = extract(m.group(1).strip(), strict=False)
        if found:
            await tools.run_checker(message, *found)
            return

    found = extract(text, strict=not private)
    if found and (private or await feature_on(chat_id, "checkers")):
        await tools.run_checker(message, *found)
        return

    digits = banking.clean_card(text)
    if digits:
        if (banking.luhn_ok(digits) or private) and (private or await feature_on(chat_id, "cardinfo")):
            await extras.answer_card(message, digits)
        return
    iban = banking.clean_iban(text)
    if iban and (private or await feature_on(chat_id, "cardinfo")):
        await extras.answer_iban(message, iban)
        return

    m = PHONE_PREFIX.match(text)
    number = phone.clean_phone(m.group(1)) if m else (phone.clean_phone(text) if private and text.startswith("+") else None)
    if number and (private or await feature_on(chat_id, "phone_check")):
        await extras.answer_phone(message, number)
        return

    if private and text.startswith("@") and instagram.enabled():
        um = instagram.USERNAME.match(text)
        if um:
            await extras.answer_insta_profile(message, um.group(1))
            return

    if len(text) > 60:
        return

    conversions = private or await feature_on(chat_id, "conversions")

    parsed = parse_amount(text)
    if parsed and conversions:
        if await tools.answer_amount(message, parsed[0], parsed[1], silent=not private):
            return

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

    expr = looks_like_expression(text)
    if expr and (private or await feature_on(chat_id, "calculator")):
        await tools.answer_calc(message, expr)
        return

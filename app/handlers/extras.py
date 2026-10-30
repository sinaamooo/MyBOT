from __future__ import annotations

import asyncio
import logging

from aiogram import F, Router
from aiogram.filters import Command, CommandObject
from aiogram.types import BufferedInputFile, CallbackQuery, Message

from .. import ui
from ..services import banking, instagram, phone, prices, tgju
from ..services.currencies import FIAT, flag_emoji
from ..utils import fmt, store
from ..utils.http import get_bytes, safe
from .common import reply_params, send

log = logging.getLogger(__name__)
router = Router(name="extras")

ARZ = ["USD", "EUR", "GBP", "AED", "TRY", "CNY", "IQD", "AFN", "CAD", "AUD", "RUB", "SAR", "KWD", "OMR", "QAR",
       "CHF", "JPY", "INR", "AMD", "GEL", "AZN"]


async def answer_card(message: Message, digits: str) -> None:
    info = await banking.lookup_card(digits)
    extra = "\n".join(f"▫️ {ui.esc(k)}: <code>{ui.esc(v)}</code>" for k, v in info.extra.items()) or None
    await send(message, ui.t(
        "card_result",
        card=banking.format_card(digits),
        bank=ui.esc(info.bank) if info.bank else None,
        owner=ui.esc(info.owner) if info.owner else None,
        iban=ui.esc(info.iban) if info.iban else None,
        valid="✅ معتبر" if info.valid else "❌ نامعتبر",
        extra=extra,
    ), markup=ui.footer_kb([[ui.button("btn_copy_id", copy_text=digits)]]))


async def answer_iban(message: Message, iban: str) -> None:
    await send(message, ui.t(
        "iban_result",
        iban=iban,
        bank=ui.esc(banking.bank_of_iban(iban)) if banking.bank_of_iban(iban) else None,
        valid="✅ معتبر" if banking.iban_ok(iban) else "❌ نامعتبر",
    ), markup=ui.footer_kb([[ui.button("btn_copy_id", copy_text=iban)]]))


@router.message(Command("card", "cart", "kart", "کارت", ignore_case=True))
async def card_cmd(message: Message, command: CommandObject):
    arg = command.args or ""
    digits = banking.clean_card(arg)
    if digits:
        await answer_card(message, digits)
        return
    iban = banking.clean_iban(arg)
    if iban:
        await answer_iban(message, iban)
        return
    await send(message, ui.t("card_result", card="—", bank=None, owner=None, iban=None, valid="❌ نامعتبر", extra=None))


async def answer_phone(message: Message, number: str) -> None:
    wait = await send(message, ui.t("loading"), markup=None)
    try:
        result = await phone.check_phone(number)
    finally:
        from ..utils.tg import safe_delete

        await safe_delete(wait)
    if not result:
        await send(message, ui.t("phone_failed"))
        return
    key, desc = result
    await send(message, ui.t("phone_result", phone=ui.esc(number), status=key, description=desc))


@router.message(Command("phone", "number", "shomare", "شماره", ignore_case=True))
async def phone_cmd(message: Message, command: CommandObject):
    number = phone.clean_phone(command.args or "")
    if not number:
        await send(message, ui.t("phone_invalid"), markup=None)
        return
    await answer_phone(message, number)


@router.message(Command("arz", "ارز", "dollar", ignore_case=True))
async def arz_cmd(message: Message):
    rates = await tgju.market_rates()
    usdt = await prices.usdt_toman()
    cross = await safe(prices.fiat_rates(), {})
    lines = []
    source = "tgju.org" if rates else "نرخ جهانی × تتر"
    for code in ARZ:
        name, _en, cc, _sym, _al = FIAT[code]
        cross_toman = (usdt / cross[code]) if usdt and cross.get(code) else None
        value = await tgju.toman_rate(code, cross_toman) if rates else None
        change = None
        if value is not None:
            change = (rates.get(code) or {}).get("change")
        else:
            value = cross_toman
        if not value:
            continue
        lines.append(ui.fill(ui.template("arz_line"), flag=flag_emoji(cc), name=name, toman=fmt.toman(value),
                             change=ui.esc(change) if change else ""))
    if not lines:
        await send(message, ui.t("price_unavailable"))
        return
    await send(message, ui.t("arz_board", lines="\n".join(lines), source=source, time=fmt.now_str()))


async def _send_media(message: Message, items: list[dict], reply: bool = True) -> None:
    count = len(items)
    rp = reply_params(message) if reply and message.chat.type != "private" else None
    for i, item in enumerate(items, 1):
        caption = ui.t("insta_media_caption", index=i, count=count, bot=ui.BOT_USERNAME)
        sender = message.answer_video if item["kind"] == "video" else message.answer_photo
        try:
            await sender(item["url"], caption=caption, reply_parameters=rp)
            continue
        except Exception as exc:
            log.info("send by url failed: %s", exc)
        data = await safe(get_bytes(item["url"], timeout=120))
        if not data or len(data) > 49 * 1024 * 1024:
            continue
        name = "media.mp4" if item["kind"] == "video" else "media.jpg"
        try:
            await sender(BufferedInputFile(data, name), caption=caption, reply_parameters=rp)
        except Exception as exc:
            log.info("upload failed: %s", exc)
        await asyncio.sleep(0.3)


def _insta_error(code: int) -> str:
    return {451: "insta_private", 452: "insta_no_story", 453: "insta_no_highlight"}.get(code, "insta_not_found")


async def answer_insta_link(message: Message, link: str) -> None:
    if not instagram.enabled():
        if message.chat.type == "private":
            await send(message, ui.t("insta_no_key"), markup=None)
        return
    wait = await send(message, ui.t("insta_loading"), markup=None)
    try:
        medias = await instagram.post_medias(link)
    except instagram.InstaError as exc:
        await wait.edit_text(ui.t(_insta_error(exc.code)))
        return
    except Exception:
        await wait.edit_text(ui.t("insta_not_found"))
        return
    if not medias:
        await wait.edit_text(ui.t("insta_not_found"))
        return
    await wait.edit_text(ui.t("insta_count", count=len(medias)))
    await _send_media(message, medias)


async def answer_insta_profile(message: Message, username: str) -> None:
    if not instagram.enabled():
        await send(message, ui.t("insta_no_key"), markup=None)
        return
    wait = await send(message, ui.t("insta_loading"), markup=None)
    try:
        info = await instagram.profile(username)
    except Exception:
        info = None
    from ..utils.tg import safe_delete

    await safe_delete(wait)
    if not info:
        await send(message, ui.t("insta_not_found"))
        return
    is_private = str(info.get("is_private", "")).strip()
    private = is_private in ("✅", "True", "true", "1")
    text = ui.t(
        "insta_info",
        id=ui.esc(info.get("Id") or info.get("id") or "—"),
        name=ui.esc(info.get("name") or "—"),
        username=ui.esc(info.get("username") or username),
        followers=ui.esc(info.get("followers") or 0),
        following=ui.esc(info.get("following") or 0),
        posts=ui.esc(info.get("post_count") or 0),
        verified=ui.esc(info.get("is_verified") or "—"),
        private=ui.esc(is_private or "—"),
        bio=ui.esc(info.get("bio")) if info.get("bio") else None,
    )
    rows = []
    if not private:
        key = store.put({"username": info.get("username") or username, "owner": message.from_user.id})
        rows = [[ui.button("btn_insta_story", callback_data=f"is:{key}")],
                [ui.button("btn_insta_highlight", callback_data=f"ih:{key}")]]
    markup = ui.footer_kb(rows)
    pic = info.get("profile")
    if pic and len(text) <= 1024:
        try:
            await message.answer_photo(pic, caption=text, reply_markup=markup,
                                       reply_parameters=None if message.chat.type == "private" else reply_params(message))
            return
        except Exception:
            pass
    await send(message, text, markup=markup)


@router.message(Command("insta", "instagram", "اینستا", ignore_case=True))
async def insta_cmd(message: Message, command: CommandObject):
    arg = (command.args or "").strip()
    link = instagram.find_link(arg)
    if link:
        await answer_insta_link(message, link)
        return
    m = instagram.USERNAME.match(arg)
    if m:
        await answer_insta_profile(message, m.group(1))
        return
    await send(message, ui.t("insta_not_found"), markup=None)


def _check_owner(call: CallbackQuery, data: dict | None) -> str | None:
    if not data:
        return "expired"
    if data.get("owner") and data["owner"] != call.from_user.id:
        return "not_your_button"
    return None


@router.callback_query(F.data.startswith("is:"))
async def insta_story(call: CallbackQuery):
    data = store.get(call.data.split(":", 1)[1])
    err = _check_owner(call, data)
    if err:
        await call.answer(ui.plain(err), show_alert=True)
        return
    await call.answer()
    wait = await call.message.answer(ui.t("insta_loading"))
    try:
        items = await instagram.stories(data["username"])
    except instagram.InstaError as exc:
        await wait.edit_text(ui.t(_insta_error(exc.code) if exc.code != 453 else "insta_no_story"))
        return
    except Exception:
        await wait.edit_text(ui.t("insta_not_found"))
        return
    if not items:
        await wait.edit_text(ui.t("insta_no_story"))
        return
    await wait.edit_text(ui.t("insta_count", count=len(items)))
    await _send_media(call.message, items, reply=False)


@router.callback_query(F.data.startswith("ih:"))
async def insta_highlights(call: CallbackQuery):
    data = store.get(call.data.split(":", 1)[1])
    err = _check_owner(call, data)
    if err:
        await call.answer(ui.plain(err), show_alert=True)
        return
    await call.answer()
    try:
        items = await instagram.highlights(data["username"])
    except instagram.InstaError as exc:
        await call.message.answer(ui.t(_insta_error(exc.code) if exc.code != 452 else "insta_no_highlight"))
        return
    except Exception:
        await call.message.answer(ui.t("insta_not_found"))
        return
    if not items:
        await call.message.answer(ui.t("insta_no_highlight"))
        return
    from aiogram.types import InlineKeyboardButton

    rows = [[InlineKeyboardButton(text=h["title"][:60], callback_data=f"hi:{store.put({'id': h['id'], 'owner': call.from_user.id})}")]
            for h in items[:40]]
    await call.message.answer(ui.t("insta_highlights", username=ui.esc(data["username"])), reply_markup=ui.kb(rows))


@router.callback_query(F.data.startswith("hi:"))
async def insta_highlight_items(call: CallbackQuery):
    data = store.get(call.data.split(":", 1)[1])
    err = _check_owner(call, data)
    if err:
        await call.answer(ui.plain(err), show_alert=True)
        return
    await call.answer()
    wait = await call.message.answer(ui.t("insta_loading"))
    try:
        items = await instagram.highlight_items(data["id"])
    except Exception:
        items = []
    if not items:
        await wait.edit_text(ui.t("insta_not_found"))
        return
    await wait.edit_text(ui.t("insta_count", count=len(items)))
    await _send_media(call.message, items, reply=False)

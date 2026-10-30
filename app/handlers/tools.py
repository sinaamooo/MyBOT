from __future__ import annotations

import asyncio
import html
import json
import logging

from aiogram import Bot, F, Router
from aiogram.filters import Command, CommandObject
from aiogram.types import BufferedInputFile, CallbackQuery, Message

from .. import ui
from ..db import get_db
from ..services import account as acc
from ..services import calc, prices, translate, txs, wallets
from ..services.chain_common import ADDRESS_KINDS, HASH_KINDS, extract
from ..services.convert import convert
from ..services.currencies import resolve_unit
from ..utils import fmt, store
from ..utils.http import safe
from ..utils.parse import looks_like_expression, parse_amount
from .common import reply_params, send, send_answer

log = logging.getLogger(__name__)
router = Router(name="tools")

MARKET = [("💵", "USDT"), ("🟠", "BTC"), ("🔷", "ETH"), ("💎", "TON"), ("🔺", "TRX"), ("🟡", "BNB"),
          ("🟣", "SOL"), ("🐶", "DOGE"), ("🟨", "NOT")]


async def market_board(message: Message) -> None:
    rate = await prices.usdt_toman()
    quotes = await asyncio.gather(*(prices.crypto_quote(s) for _, s in MARKET))
    lines = []
    for (icon, sym), q in zip(MARKET, quotes):
        if not q:
            continue
        lines.append(ui.fill(
            ui.template("market_line"), icon=icon, symbol=sym, usd=fmt.usd(q.price),
            toman=fmt.toman(q.price * rate) if rate else "—",
            change=fmt.change_badge(q.change24h) if sym != "USDT" else "",
        ))
    if not lines:
        await send(message, ui.t("price_unavailable"))
        return
    await send(message, ui.t("market", lines="\n".join(lines), usdt_toman=fmt.toman(rate) if rate else None,
                             time=fmt.now_str()))


async def answer_amount(message: Message, amount: float, unit: str, silent: bool) -> bool:
    resolved = resolve_unit(unit)
    if not resolved:
        return False
    kind, code = resolved
    if kind == "crypto_guess" and silent:
        pass
    answer = await safe(convert(amount, kind, code), label="convert")
    if answer is None:
        if not silent:
            await send(message, ui.t("currency_not_found", query=ui.esc(unit)))
            return True
        return False
    await send_answer(message, answer)
    return True


@router.message(Command("p", "price", "قیمت", "nerkh", ignore_case=True))
async def price_cmd(message: Message, command: CommandObject):
    arg = (command.args or "").strip()
    if not arg:
        await market_board(message)
        return
    parsed = parse_amount(arg)
    if parsed:
        await answer_amount(message, parsed[0], parsed[1], silent=False)
    else:
        await answer_amount(message, 1, arg, silent=False)


async def run_checker(message: Message, kind: str, token: str, owner_id: int | None = None) -> None:
    owner = owner_id or (message.from_user.id if message.from_user else 0)
    is_hash = kind in HASH_KINDS
    wait = await send(message, ui.t("tx_checking" if is_hash else "wallet_checking"), markup=None)
    try:
        if is_hash or kind in ("hex64x",):
            tx = await txs.check_tx(kind, token)
            if tx:
                await _send_tx(message, tx, owner)
                return
            if kind == "hex64x":
                found = await wallets.check_wallet(kind, token)
                if found:
                    await _send_wallet(message, token, found, owner)
                    return
            await send(message, ui.t("tx_not_found", hash=ui.esc(token)))
            return
        found = await wallets.check_wallet(kind, token)
        if not found and kind == "sol" and len(token) in (43, 44):
            tx = await txs.check_tx("sol", token)
            if tx:
                await _send_tx(message, tx, owner)
                return
        if found:
            await _send_wallet(message, token, found, owner)
        else:
            await send(message, ui.t("wallet_not_found", address=ui.esc(token)))
    finally:
        from ..utils.tg import safe_delete

        await safe_delete(wait)


async def _send_wallet(message: Message, address: str, found: list[wallets.ChainResult], owner: int) -> None:
    rate = await prices.usdt_toman()
    parts = [ui.t("wallet_header", address=ui.esc(address), count=len(found))]
    total = 0.0
    for r in found:
        total += r.total_usd
        tokens = "\n".join(
            f"   • <code>{fmt.num(t.amount)}</code> {ui.esc(t.symbol)}" + (f" (≈ {fmt.usd(t.usd)}$)" if t.usd else "")
            for t in r.tokens[:12]
        ) or None
        parts.append(ui.t(
            "wallet_chain",
            chain=ui.esc(r.chain),
            balance=fmt.num(r.balance),
            symbol=r.symbol,
            usd=fmt.usd(r.usd) if r.usd is not None else None,
            toman=fmt.toman(r.usd * rate) if r.usd is not None and rate else None,
            tokens=tokens,
            txs=fmt.integer(r.txs) if isinstance(r.txs, (int, float)) else (r.txs if r.txs else None),
            nfts=fmt.integer(r.nfts) if r.nfts else None,
            created=fmt.jalali_date(r.created) if r.created else None,
            status=r.status,
            explorer=html.escape(r.explorer, quote=True),
        ))
    parts.append(ui.t("wallet_footer", total_usd=fmt.usd(total), total_toman=fmt.toman(total * rate) if rate else None,
                      time=fmt.now_str()))
    text = "\n".join(parts)
    if len(text) > 4000:
        text = text[:3990].rsplit("\n", 1)[0]
    rows = []
    if any(r.kind == "tron" for r in found):
        key = store.put({"addr": address, "owner": owner})
        rows.append([ui.button("btn_trc20_history", callback_data=f"tt:{key}:1"),
                     ui.button("btn_tokens_file", callback_data=f"tj:{key}")])
    await send(message, text, markup=ui.footer_kb(rows))


async def _send_tx(message: Message, tx: txs.TxResult, owner: int) -> None:
    rate = await prices.usdt_toman()
    amount = f"{fmt.num(tx.amount)} {ui.esc(tx.symbol or '')}" if tx.amount is not None else (ui.esc(tx.symbol) if tx.symbol else None)
    token = None
    if tx.token_type:
        token = f"{ui.esc(tx.token_name or tx.symbol or '')} ({tx.token_type})"
    text = ui.t(
        "tx_result",
        network=ui.esc(tx.network),
        status=tx.status,
        amount=amount,
        usd=fmt.usd(tx.usd) if tx.usd else None,
        toman=fmt.toman(tx.usd * rate) if tx.usd and rate else None,
        token=token,
        contract=ui.esc(tx.contract) if tx.contract else None,
        sender=ui.esc(tx.sender) if tx.sender else None,
        receiver=ui.esc(tx.receiver) if tx.receiver else None,
        fee=ui.esc(tx.fee) if tx.fee else None,
        block=tx.block if tx.block is not None else None,
        confirmations=fmt.integer(tx.confirmations) if isinstance(tx.confirmations, int) else (tx.confirmations or None),
        time=fmt.jalali_date(tx.timestamp) if tx.timestamp else None,
        hash=ui.esc(tx.hash),
        explorer=html.escape(tx.explorer, quote=True),
    )
    row = []
    for addr, key in ((tx.sender, "btn_sender_wallet"), (tx.receiver, "btn_receiver_wallet")):
        found = extract(str(addr or "").split(" ")[0], strict=True)
        if found and found[0] in ADDRESS_KINDS:
            row.append(ui.button(key, callback_data=f"wl:{store.put({'addr': found[1], 'kind': found[0], 'owner': owner})}"))
    await send(message, text, markup=ui.footer_kb([row] if row else None))


def _owned(call: CallbackQuery, data: dict | None) -> str | None:
    if not data:
        return "expired"
    if data.get("owner") and data["owner"] != call.from_user.id:
        return "not_your_button"
    return None


@router.callback_query(F.data.startswith("wl:"))
async def wallet_button(call: CallbackQuery):
    data = store.get(call.data.split(":", 1)[1])
    err = _owned(call, data)
    if err:
        await call.answer(ui.plain(err), show_alert=True)
        return
    await call.answer()
    await run_checker(call.message, data["kind"], data["addr"], owner_id=call.from_user.id)


def _transfers_text(address: str, res: dict) -> str:
    items = []
    for it in res["items"]:
        incoming = (it.get("to") or "") == address
        items.append(ui.fill(
            ui.template("tron_transfer_item"),
            direction="📥" if incoming else "📤",
            amount=fmt.num(it["amount"]),
            symbol=ui.esc(it["symbol"]),
            time=fmt.jalali_date(it["ts"]) if it["ts"] else "—",
            party=ui.esc(it.get("from") if incoming else it.get("to")),
            hash=ui.esc(it.get("hash") or ""),
        ))
    return ui.t("tron_transfers", address=ui.esc(address), page=res["page"], pages=res["pages"], total=fmt.integer(res["total"]),
                items="\n".join(items))


@router.callback_query(F.data.startswith("tt:") | F.data.startswith("tp:"))
async def tron_transfers_button(call: CallbackQuery):
    prefix, key, page_s = call.data.split(":", 2)
    data = store.get(key)
    err = _owned(call, data)
    if err:
        await call.answer(ui.plain(err), show_alert=True)
        return
    page = max(1, int(page_s))
    res = await safe(wallets.tron_trc20_transfers(data["addr"], page))
    if not res:
        await call.answer(ui.plain("price_unavailable"), show_alert=True)
        return
    if not res["items"]:
        await call.answer(ui.plain("tron_transfers_empty"), show_alert=True)
        return
    await call.answer()
    nav = []
    if page > 1:
        nav.append(ui.button("btn_prev", callback_data=f"tp:{key}:{page - 1}"))
    if page < res["pages"]:
        nav.append(ui.button("btn_next", callback_data=f"tp:{key}:{page + 1}"))
    text = _transfers_text(data["addr"], res)
    markup = ui.kb([nav]) if nav else None
    if prefix == "tp":
        try:
            await call.message.edit_text(text, reply_markup=markup)
            return
        except Exception:
            pass
    await call.message.answer(text, reply_markup=markup)


@router.callback_query(F.data.startswith("tj:"))
async def tron_tokens_button(call: CallbackQuery):
    data = store.get(call.data.split(":", 1)[1])
    err = _owned(call, data)
    if err:
        await call.answer(ui.plain(err), show_alert=True)
        return
    res = await safe(wallets.tron_all_tokens(data["addr"]))
    if not res:
        await call.answer(ui.plain("price_unavailable"), show_alert=True)
        return
    await call.answer()
    payload = json.dumps(res, ensure_ascii=False, indent=2).encode()
    await call.message.answer_document(
        BufferedInputFile(payload, f"tokens_{data['addr'][:8]}.json"),
        caption=ui.t("tron_tokens_file", total=res.get("total", 0), address=ui.esc(data["addr"])),
    )


@router.message(Command("wallet", "w", "tx", "hash", "check_wallet", ignore_case=True))
async def wallet_cmd(message: Message, command: CommandObject):
    found = extract(command.args or "", strict=False)
    if not found:
        await send(message, ui.t("help"))
        return
    await run_checker(message, *found)


async def send_uid(message: Message) -> None:
    target = message.reply_to_message.from_user if message.reply_to_message else message.from_user
    if message.reply_to_message and message.reply_to_message.sender_chat:
        chat = message.reply_to_message.sender_chat
        await send(message, ui.t("uid", name=ui.esc(chat.title or ""), id=chat.id,
                                 username=ui.username_or_dash(chat.username)),
                   markup=ui.kb([[ui.button("btn_copy_id", copy_text=str(chat.id))]]))
        return
    if not target:
        return
    await send(message, ui.t("uid", name=ui.mention(target), id=target.id, username=ui.username_or_dash(target.username)),
               markup=ui.kb([[ui.button("btn_copy_id", copy_text=str(target.id))]]))


@router.message(Command("uid", "id", ignore_case=True))
async def uid_cmd(message: Message):
    await send_uid(message)


@router.message(Command("emoji", "emojiid", ignore_case=True))
async def emoji_cmd(message: Message, bot: Bot):
    items: list[tuple[str, str]] = []
    for m in filter(None, (message, message.reply_to_message)):
        text = m.text or m.caption or ""
        for e in m.entities or m.caption_entities or []:
            if e.type == "custom_emoji" and e.custom_emoji_id:
                items.append((e.extract_from(text), e.custom_emoji_id))
        if m.sticker and m.sticker.custom_emoji_id:
            items.append((m.sticker.emoji or "⭐️", m.sticker.custom_emoji_id))
    seen = set()
    items = [i for i in items if not (i[1] in seen or seen.add(i[1]))][:25]
    if not items:
        await send(message, ui.t("emoji_none"))
        return
    packs: dict[str, str] = {}
    stickers = await safe(bot.get_custom_emoji_stickers([i[1] for i in items]), [])
    for st in stickers or []:
        if st.custom_emoji_id and st.set_name:
            packs[st.custom_emoji_id] = st.set_name
    parts = [ui.t("emoji_header", count=len(items))]
    for char, eid in items:
        pack = packs.get(eid)
        parts.append(ui.t(
            "emoji_item",
            emoji=f'<tg-emoji emoji-id="{eid}">{ui.esc(char)}</tg-emoji>',
            id=eid,
            pack=f'<a href="https://t.me/addemoji/{pack}">{ui.esc(pack)}</a>' if pack else None,
            html=ui.esc(f'<tg-emoji emoji-id="{eid}">{char}</tg-emoji>'),
        ))
    text = "\n".join(parts)
    copy = ",".join(i[1] for i in items)
    await send(message, text[:4000], markup=ui.kb([[ui.button("btn_copy_id", copy_text=copy)]]))


@router.message(Command("account", "acc", "me", "info", ignore_case=True))
async def account_cmd(message: Message, bot: Bot, command: CommandObject):
    target = message.from_user
    if message.reply_to_message and message.reply_to_message.from_user:
        target = message.reply_to_message.from_user
    elif command.args and command.args.strip().lstrip("-").isdigit():
        try:
            target = (await bot.get_chat_member(message.chat.id, int(command.args.strip()))).user
        except Exception:
            pass
    db = get_db()
    info, photos, gifts = await asyncio.gather(
        safe(bot.get_chat(target.id)),
        safe(bot.get_user_profile_photos(target.id, limit=1)),
        safe(bot.get_user_gifts(target.id, limit=1)),
    )
    row = await db.get_user(target.id) or {}
    photo_id = photos.photos[0][-1].file_id if photos and photos.total_count else None
    dc = acc.dc_from_file_id(photo_id) if photo_id else None
    level = None
    rating = getattr(info, "rating", None) if info else None
    if rating:
        level = f"⭐️ {rating.level}"
        if rating.next_level_rating:
            level += f" ({fmt.integer(rating.rating)}/{fmt.integer(rating.next_level_rating)})"
    birthdate = None
    if info and getattr(info, "birthdate", None):
        b = info.birthdate
        birthdate = f"{b.year}/{b.month:02d}/{b.day:02d}" if b.year else f"{b.month:02d}/{b.day:02d}"
    xp = int(row.get("xp") or 0)
    text = ui.t(
        "account",
        name=ui.mention(target),
        id=target.id,
        username=ui.username_or_dash(target.username),
        premium="✅ دارد" if target.is_premium else "❌ ندارد",
        level=level,
        created=fmt.jalali_long(acc.estimate_creation(target.id)),
        dc=f"DC{dc} • {acc.DC_LOCATIONS.get(dc, '')}" if dc else None,
        lang=target.language_code.upper() if target.language_code else None,
        birthdate=birthdate,
        bio=ui.esc(info.bio) if info and getattr(info, "bio", None) else None,
        gifts=fmt.integer(gifts.total_count) if gifts else None,
        bot_level=acc.level_from_xp(xp),
        xp=fmt.integer(xp),
        msgs=fmt.integer(row.get("msg_count") or 0),
        balance=fmt.num(row.get("balance") or 0),
        joined=fmt.jalali_date(row["first_seen"], with_time=False) if row.get("first_seen") else None,
    )
    if photo_id and len(text) <= 1024:
        await message.answer_photo(photo_id, caption=text, reply_markup=ui.footer_kb(),
                                   reply_parameters=None if message.chat.type == "private" else reply_params(message))
        return
    await send(message, text)


async def answer_calc(message: Message, expr: str) -> bool:
    try:
        value = calc.evaluate(expr)
    except calc.CalcError:
        return False
    shown = expr.replace("**", "^").replace("*", "×").replace("/", "÷")
    await send(message, ui.t("calc_result", expr=ui.esc(shown), result=calc.format_result(value)), markup=None)
    return True


@router.message(Command("calc", "c", "hesab", ignore_case=True))
async def calc_cmd(message: Message, command: CommandObject):
    expr = looks_like_expression(command.args or "")
    if not expr or not await answer_calc(message, expr):
        await send(message, ui.t("calc_result", expr=ui.esc(command.args or ""), result="؟"), markup=None)


LANG_ROWS = [["fa", "en", "ru"], ["zh", "ar"]]


async def ask_translation(message: Message) -> None:
    src = message.reply_to_message
    text = (src.text or src.caption or "") if src else ""
    if not text.strip():
        await send(message, ui.t("translate_need_reply"), markup=None)
        return
    kb = ui.kb([[ui.button(f"lang_{code}", callback_data=f"tr:{code}") for code in row] for row in LANG_ROWS])
    sent = await src.reply(ui.t("translate_choose"), reply_markup=kb)
    await get_db().save_translation(sent.chat.id, sent.message_id, message.from_user.id, text[:3800])


@router.message(Command("tr", "translate", ignore_case=True))
async def translate_cmd(message: Message):
    await ask_translation(message)


_tr_locks: dict[tuple[int, int], asyncio.Lock] = {}


@router.callback_query(F.data.startswith("tr:"))
async def translate_cb(call: CallbackQuery):
    lang = call.data.split(":", 1)[1]
    if lang not in translate.LANGS or not call.message:
        await call.answer()
        return
    key = (call.message.chat.id, call.message.message_id)
    lock = _tr_locks.setdefault(key, asyncio.Lock())
    async with lock:
        db = get_db()
        row = await db.get_translation(*key)
        if not row:
            await call.answer(ui.plain("translate_used"), show_alert=True)
            return
        if row["requester_id"] != call.from_user.id:
            await call.answer(ui.plain("translate_not_yours"), show_alert=True)
            return
        if not await db.claim_translation(*key):
            await call.answer(ui.plain("translate_used"), show_alert=True)
            return
        result = await translate.translate(row["text"], lang)
        if not result:
            await db.release_translation(*key)
            await call.answer(ui.plain("translate_failed"), show_alert=True)
            return
        await call.answer("✅")
        text = ui.t("translate_result", lang=translate.LANGS[lang][0], text=ui.esc(result[:3700]))
        try:
            await call.message.edit_text(text, reply_markup=ui.footer_kb())
        except Exception:
            await call.message.answer(text, reply_markup=ui.footer_kb())
    _tr_locks.pop(key, None)

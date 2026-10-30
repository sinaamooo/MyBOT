"""Builds conversion answers for '<amount> <unit>' messages."""
from __future__ import annotations

from dataclasses import dataclass

from .. import ui
from ..utils import fmt
from . import cards, prices
from .currencies import FIAT, crypto_name, flag_emoji


@dataclass
class Answer:
    text: str
    photo: bytes | None = None


async def convert(amount: float, kind: str, code: str) -> Answer | None:
    if amount <= 0 or amount > 1e18:
        return None
    if kind in ("crypto", "crypto_guess"):
        return await _crypto(amount, code)
    if kind in ("toman", "rial"):
        return await _toman(amount / 10 if kind == "rial" else amount, rial_input=kind == "rial")
    if kind == "stars":
        return await _stars(amount)
    if kind == "fiat":
        return await _fiat(amount, code)
    return None


async def _crypto(amount: float, symbol: str) -> Answer | None:
    quote = await prices.crypto_quote(symbol)
    if not quote:
        return None
    snap = await prices.snapshot()
    usd = amount * quote.price
    toman = usd * snap.usdt_toman if snap.usdt_toman else None
    text = ui.t(
        "conv_crypto",
        amount=fmt.num(amount),
        symbol=symbol.upper(),
        name=crypto_name(symbol),
        toman=fmt.toman(toman) if toman else None,
        usd=fmt.usd(usd),
        usdt=fmt.num(usd) if symbol.upper() not in prices.STABLES else None,
        ton=fmt.num(usd / snap.ton_usd) if snap.ton_usd and symbol.upper() != "TON" else None,
        trx=fmt.num(usd / snap.trx_usd) if snap.trx_usd and symbol.upper() != "TRX" else None,
        stars=fmt.integer(usd / prices.star_usd()),
        price_usd=fmt.usd(quote.price),
        price_toman=fmt.toman(quote.price * snap.usdt_toman) if snap.usdt_toman else None,
        change=fmt.change_badge(quote.change24h) if symbol.upper() not in prices.STABLES else None,
        time=fmt.now_str(),
    )
    return Answer(text)


async def _toman(amount_toman: float, rial_input: bool = False) -> Answer | None:
    snap = await prices.snapshot()
    if not snap.usdt_toman:
        return Answer(ui.t("price_unavailable"))
    usd = amount_toman / snap.usdt_toman
    text = ui.t(
        "conv_toman",
        amount=fmt.toman(amount_toman),
        usdt=fmt.num(usd),
        ton=fmt.num(usd / snap.ton_usd) if snap.ton_usd else None,
        trx=fmt.num(usd / snap.trx_usd) if snap.trx_usd else None,
        usd=fmt.usd(usd),
        stars=fmt.integer(usd / prices.star_usd()),
        rial=fmt.toman(amount_toman * 10),
        usdt_rate=fmt.toman(snap.usdt_toman),
        time=fmt.now_str(),
    )
    return Answer(text)


async def _stars(amount: float) -> Answer | None:
    snap = await prices.snapshot()
    usd = amount * prices.star_usd()
    text = ui.t(
        "conv_stars",
        amount=fmt.integer(amount),
        toman=fmt.toman(usd * snap.usdt_toman) if snap.usdt_toman else None,
        usd=fmt.usd(usd),
        ton=fmt.num(usd / snap.ton_usd) if snap.ton_usd else None,
        trx=fmt.num(usd / snap.trx_usd) if snap.trx_usd else None,
        star_usd=fmt.num(prices.star_usd()),
        time=fmt.now_str(),
    )
    return Answer(text)


async def _fiat(amount: float, code: str) -> Answer | None:
    code = code.upper()
    rates = await prices.fiat_rates()
    if code not in rates:
        return None
    snap = await prices.snapshot()
    name_fa, name_en, cc, _sym, _aliases = FIAT.get(code, (code, code, None, code, ()))
    usd = amount / rates[code]
    toman = usd * snap.usdt_toman if snap.usdt_toman else None
    eur = usd * rates["EUR"] if rates.get("EUR") else None
    ton = usd / snap.ton_usd if snap.ton_usd else None
    trx = usd / snap.trx_usd if snap.trx_usd else None
    unit_toman = (toman / amount) if toman else None

    rows: list[cards.ConvRow] = []
    if toman is not None:
        rows.append(cards.ConvRow("تومان", fmt.toman(toman), "IRT"))
    if code != "USD":
        rows.append(cards.ConvRow("دلار", fmt.usd(usd), "USD"))
    elif rates.get("AED"):
        rows.append(cards.ConvRow("درهم", fmt.num(usd * rates["AED"]), "AED"))
    if code != "EUR" and eur is not None:
        rows.append(cards.ConvRow("یورو", fmt.num(eur), "EUR"))
    elif rates.get("GBP"):
        rows.append(cards.ConvRow("پوند", fmt.num(usd * rates["GBP"]), "GBP"))
    rows.append(cards.ConvRow("تتر", fmt.num(usd), "USDT"))
    if ton is not None:
        rows.append(cards.ConvRow("تون", fmt.num(ton), "TON"))
    if trx is not None:
        rows.append(cards.ConvRow("ترون", fmt.num(trx), "TRX"))

    unit_line = f"هر ۱ {name_fa} = {fmt.toman(unit_toman)} تومان" if unit_toman else f"1 {code} = {fmt.usd(1 / rates[code])} $"
    photo = await cards.render(
        cards.fiat_card, code, name_fa, name_en, cc, fmt.num(amount), rows, unit_line, ui.BOT_USERNAME
    )
    caption = ui.t(
        "conv_fiat",
        flag=flag_emoji(cc),
        amount=fmt.num(amount),
        name=name_fa,
        code=code,
        toman=fmt.toman(toman) if toman is not None else None,
        usd=fmt.usd(usd) if code != "USD" else None,
        eur=fmt.num(eur) if eur is not None and code != "EUR" else None,
        usdt=fmt.num(usd),
        ton=fmt.num(ton) if ton is not None else None,
        trx=fmt.num(trx) if trx is not None else None,
        unit_toman=fmt.toman(unit_toman) if unit_toman else None,
        time=fmt.now_str(),
    )
    return Answer(caption, photo)

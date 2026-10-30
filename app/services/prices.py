"""Price engine: crypto (many exchanges + aggregators), USDT/Toman (Iranian exchanges), fiat rates."""
from __future__ import annotations

import asyncio
import logging
import statistics
from dataclasses import dataclass

from ..config import config
from ..settings import gget
from ..utils.http import get_json, safe, ttl_cache

log = logging.getLogger(__name__)

STABLES = {"USDT", "USDC", "DAI", "FDUSD", "USDE", "PYUSD", "BUSD", "TUSD", "USDD"}


@dataclass
class Quote:
    symbol: str
    price: float
    change24h: float | None
    sources: list[str]


def _f(x) -> float | None:
    try:
        v = float(x)
        return v if v > 0 else None
    except (TypeError, ValueError):
        return None


# ------------------------------------------------------------------ crypto sources
async def _binance(sym: str):
    for host in ("https://api.binance.com", "https://data-api.binance.vision"):
        data = await safe(get_json(f"{host}/api/v3/ticker/24hr", params={"symbol": f"{sym}USDT"}, timeout=6))
        if data and _f(data.get("lastPrice")):
            return _f(data["lastPrice"]), float(data.get("priceChangePercent") or 0)
    return None


async def _okx(sym: str):
    data = await get_json("https://www.okx.com/api/v5/market/ticker", params={"instId": f"{sym}-USDT"}, timeout=6)
    row = (data.get("data") or [None])[0]
    if row and _f(row.get("last")):
        last, open24 = _f(row["last"]), _f(row.get("open24h"))
        return last, ((last - open24) / open24 * 100) if open24 else None
    return None


async def _bybit(sym: str):
    data = await get_json(
        "https://api.bybit.com/v5/market/tickers", params={"category": "spot", "symbol": f"{sym}USDT"}, timeout=6
    )
    rows = (data.get("result") or {}).get("list") or []
    if rows and _f(rows[0].get("lastPrice")):
        pc = rows[0].get("price24hPcnt")
        return _f(rows[0]["lastPrice"]), float(pc) * 100 if pc not in (None, "") else None
    return None


async def _kucoin(sym: str):
    data = await get_json("https://api.kucoin.com/api/v1/market/stats", params={"symbol": f"{sym}-USDT"}, timeout=6)
    row = data.get("data") or {}
    if _f(row.get("last")):
        cr = row.get("changeRate")
        return _f(row["last"]), float(cr) * 100 if cr not in (None, "") else None
    return None


async def _gate(sym: str):
    data = await get_json(
        "https://api.gateio.ws/api/v4/spot/tickers", params={"currency_pair": f"{sym}_USDT"}, timeout=6
    )
    if isinstance(data, list) and data and _f(data[0].get("last")):
        cp = data[0].get("change_percentage")
        return _f(data[0]["last"]), float(cp) if cp not in (None, "") else None
    return None


async def _mexc(sym: str):
    data = await get_json("https://api.mexc.com/api/v3/ticker/price", params={"symbol": f"{sym}USDT"}, timeout=6)
    if isinstance(data, dict) and _f(data.get("price")):
        return _f(data["price"]), None
    return None


async def _coingecko(sym: str):
    headers = {"x-cg-demo-api-key": config.coingecko_key} if config.coingecko_key else None
    data = await get_json(
        "https://api.coingecko.com/api/v3/simple/price",
        params={"symbols": sym.lower(), "vs_currencies": "usd", "include_24hr_change": "true"},
        headers=headers,
        timeout=7,
    )
    row = data.get(sym.lower()) if isinstance(data, dict) else None
    if row and _f(row.get("usd")):
        return _f(row["usd"]), row.get("usd_24h_change")
    return None


async def _cryptocompare(sym: str):
    data = await get_json(
        "https://min-api.cryptocompare.com/data/pricemultifull", params={"fsyms": sym, "tsyms": "USD"}, timeout=7
    )
    row = ((data.get("RAW") or {}).get(sym) or {}).get("USD") or {}
    if _f(row.get("PRICE")):
        return _f(row["PRICE"]), row.get("CHANGEPCT24HOUR")
    return None


SOURCES = [
    ("Binance", _binance),
    ("CoinGecko", _coingecko),
    ("OKX", _okx),
    ("Bybit", _bybit),
    ("KuCoin", _kucoin),
    ("Gate", _gate),
    ("CryptoCompare", _cryptocompare),
    ("MEXC", _mexc),
]


@ttl_cache(45)
async def crypto_quote(symbol: str) -> Quote | None:
    sym = symbol.upper().strip()
    if sym in STABLES:
        return Quote(sym, 1.0, 0.0, ["peg"])
    results = await asyncio.gather(*(safe(fn(sym), label=name) for name, fn in SOURCES))
    found = [(name, r[0], r[1]) for (name, _), r in zip(SOURCES, results) if r and r[0]]
    if not found:
        return None
    prices = [p for _, p, _ in found]
    med = statistics.median(prices)
    # drop outliers (same ticker for a different coin on a small exchange)
    good = [f for f in found if abs(f[1] - med) / med <= 0.2] or found
    if len(good) >= 3:
        price = statistics.median(p for _, p, _ in good)
    else:
        price = good[0][1]
    change = next((c for _, _, c in good if c is not None), None)
    try:
        change = float(change) if change is not None else None
    except (TypeError, ValueError):
        change = None
    return Quote(sym, price, change, [n for n, _, _ in good])


async def crypto_usd(symbol: str) -> float | None:
    q = await crypto_quote(symbol)
    return q.price if q else None


# ------------------------------------------------------------------ USDT -> Toman
async def _nobitex():
    data = await get_json(
        "https://api.nobitex.ir/market/stats", params={"srcCurrency": "usdt", "dstCurrency": "rls"}, timeout=7
    )
    row = (data.get("stats") or {}).get("usdt-rls") or {}
    v = _f(row.get("latest")) or _f(row.get("bestSell"))
    return v / 10 if v else None


async def _wallex():
    data = await get_json("https://api.wallex.ir/v1/markets", timeout=7)
    sym = ((data.get("result") or {}).get("symbols") or {}).get("USDTTMN") or {}
    stats = sym.get("stats") or {}
    return _f(stats.get("lastPrice")) or _f(stats.get("bidPrice"))


async def _bitpin():
    data = await get_json("https://api.bitpin.org/api/v1/mkt/tickers/", timeout=7)
    rows = data if isinstance(data, list) else data.get("results") or []
    for row in rows:
        if str(row.get("symbol", "")).upper() in ("USDT_IRT", "USDTIRT"):
            return _f(row.get("price"))
    return None


async def _tabdeal():
    data = await get_json("https://api1.tabdeal.org/r/api/v1/ticker/price", params={"symbol": "USDTIRT"}, timeout=7)
    return _f(data.get("price")) if isinstance(data, dict) else None


async def _ramzinex():
    data = await get_json("https://publicapi.ramzinex.com/exchange/api/v1.0/exchange/pairs/11", timeout=7)
    row = data.get("data") or {}
    v = _f((row.get("sell") or 0)) or _f(row.get("buy"))
    return v / 10 if v and v > 1_000_000 else v


@ttl_cache(60)
async def usdt_toman() -> float | None:
    manual = gget("usdt_toman_manual")
    if manual:
        return float(manual)
    results = await asyncio.gather(
        safe(_nobitex(), label="nobitex"),
        safe(_wallex(), label="wallex"),
        safe(_bitpin(), label="bitpin"),
        safe(_tabdeal(), label="tabdeal"),
        safe(_ramzinex(), label="ramzinex"),
    )
    vals = [v for v in results if v and 1_000 < v < 100_000_000]
    if not vals:
        return gget("usdt_toman_last") or None
    med = statistics.median(vals)
    vals = [v for v in vals if abs(v - med) / med < 0.1] or vals
    rate = statistics.median(vals)
    from ..db import get_db

    if abs((gget("usdt_toman_last") or 0) - rate) > 1:
        await safe(get_db().set("usdt_toman_last", rate))
    return rate


# ------------------------------------------------------------------ fiat
@ttl_cache(1800)
async def fiat_rates() -> dict[str, float]:
    """Units of each currency per 1 USD."""
    rates: dict[str, float] = {}
    for url in (
        "https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json",
        "https://latest.currency-api.pages.dev/v1/currencies/usd.json",
    ):
        data = await safe(get_json(url, timeout=10))
        if data and isinstance(data.get("usd"), dict):
            for k, v in data["usd"].items():
                if _f(v):
                    rates[k.upper()] = float(v)
            break
    data = await safe(get_json("https://open.er-api.com/v6/latest/USD", timeout=10))
    if data and isinstance(data.get("rates"), dict):
        for k, v in data["rates"].items():
            if _f(v):
                rates[k.upper()] = float(v)
    if not rates:
        data = await safe(get_json("https://api.frankfurter.dev/v1/latest", params={"base": "USD"}, timeout=10))
        if data and isinstance(data.get("rates"), dict):
            rates.update({k.upper(): float(v) for k, v in data["rates"].items()})
    rates["USD"] = 1.0
    return rates


async def fiat_to_usd(code: str, amount: float) -> float | None:
    rates = await fiat_rates()
    rate = rates.get(code.upper())
    return amount / rate if rate else None


def star_usd() -> float:
    return float(gget("star_usd") or 0.015)


@dataclass
class Snapshot:
    usdt_toman: float | None
    ton_usd: float | None
    trx_usd: float | None
    eur_per_usd: float | None


async def snapshot() -> Snapshot:
    toman_rate, ton, trx, rates = await asyncio.gather(
        usdt_toman(), crypto_usd("TON"), crypto_usd("TRX"), safe(fiat_rates(), {})
    )
    return Snapshot(toman_rate, ton, trx, (rates or {}).get("EUR"))

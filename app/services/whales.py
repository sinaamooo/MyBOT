from __future__ import annotations

import asyncio
import logging
import time
from dataclasses import dataclass

from aiogram import Bot

from .. import ui
from ..config import config
from ..db import get_db
from ..settings import feature_on, gget
from ..utils import fmt
from ..utils.http import get_json, safe
from . import prices
from .chain_common import (
    EVM_CHAINS,
    TRANSFER_TOPIC,
    hex_int,
    rpc,
    topic_to_address,
    tron_headers,
    tron_hex_to_base58,
)

log = logging.getLogger(__name__)

LABELS = {
    "0x28c6c06298d514db089934071355e5743bf21d60": "Binance 14",
    "0xf977814e90da44bfa03b6295a0616a897441acec": "Binance 8",
    "0x21a31ee1afc51d94c2efccaa2092ad1028285549": "Binance 15",
    "0xdfd5293d8e347dfe59e90efd55b2956a1343963d": "Binance 16",
    "0x5754284f345afc66a98fbb0a0afe71e0f007b949": "Tether Treasury",
    "0x8894e0a0c962cb723c1976a4421c95949be2d4e3": "Binance (BSC)",
    "TKHuVq1oKVruCGLvqVexFs6dawKv6fQgFs": "Tether Treasury",
}

USDT_TRON = "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t"


@dataclass
class Alert:
    uid: str
    network: str
    symbol: str
    amount: float
    usd: float
    sender: str
    receiver: str
    link: str


def _label(addr: str) -> str:
    if not addr:
        return "نامشخص"
    name = LABELS.get(addr) or LABELS.get(addr.lower())
    short = fmt.short(addr, 6, 4)
    return f"<b>{name}</b> (<code>{short}</code>)" if name else f"<code>{short}</code>"


class WhaleWatcher:
    def __init__(self, bot: Bot):
        self.bot = bot
        self.evm_state: dict[str, int] = {}
        self.tron_ts: int | None = None
        self.btc_tip: str | None = None
        self.whale_alert_ts: int = int(time.time())
        self._task: asyncio.Task | None = None

    def start(self) -> None:
        self._task = asyncio.create_task(self._loop())

    async def stop(self) -> None:
        if self._task:
            self._task.cancel()

    async def _loop(self) -> None:
        await asyncio.sleep(20)
        while True:
            try:
                if gget("whale_alerts"):
                    alerts = await self.scan()
                    await self.publish(alerts)
            except asyncio.CancelledError:
                raise
            except Exception:
                log.exception("whale scan failed")
            await asyncio.sleep(45)

    @property
    def min_usd(self) -> float:
        return float(gget("whale_min_usd") or 5_000_000)

    async def scan(self) -> list[Alert]:
        results = await asyncio.gather(
            safe(self.scan_evm("eth"), []),
            safe(self.scan_evm("bsc"), []),
            safe(self.scan_eth_native(), []),
            safe(self.scan_tron(), []),
            safe(self.scan_btc(), []),
            safe(self.scan_whale_alert(), []),
        )
        return [a for group in results for a in group]

    async def scan_evm(self, key: str) -> list[Alert]:
        chain = next(c for c in EVM_CHAINS if c.key == key)
        head = hex_int(await rpc(chain.rpcs, "eth_blockNumber", []))
        last = self.evm_state.get(key)
        if last is None or head - last > 400:
            self.evm_state[key] = head
            return []
        if head <= last:
            return []
        to_block = min(head, last + (150 if key == "bsc" else 40))
        tokens = {addr.lower(): (sym, dec) for sym, (addr, dec) in chain.tokens.items()
                  if sym in ("USDT", "USDC", "BUSD")}
        logs = await rpc(chain.rpcs, "eth_getLogs", [{
            "fromBlock": hex(last + 1), "toBlock": hex(to_block),
            "address": list(tokens), "topics": [TRANSFER_TOPIC],
        }], timeout=20)
        self.evm_state[key] = to_block
        out = []
        for item in logs or []:
            topics = item.get("topics") or []
            if len(topics) != 3:
                continue
            sym, dec = tokens.get(item["address"].lower(), ("TOKEN", 18))
            amount = hex_int(item.get("data")) / 10 ** dec
            if amount >= self.min_usd:
                h = item["transactionHash"]
                out.append(Alert(f"{key}:{h}:{item.get('logIndex')}", chain.name, sym, amount, amount,
                                 topic_to_address(topics[1]), topic_to_address(topics[2]), f"{chain.explorer}/tx/{h}"))
        return out

    async def scan_eth_native(self) -> list[Alert]:
        chain = EVM_CHAINS[0]
        head = hex_int(await rpc(chain.rpcs, "eth_blockNumber", []))
        last = self.evm_state.get("eth_native")
        if last is None or head - last > 30:
            self.evm_state["eth_native"] = head
            return []
        eth_usd = await prices.crypto_usd("ETH")
        if not eth_usd:
            return []
        out = []
        for num in range(last + 1, min(head, last + 6) + 1):
            blk = await rpc(chain.rpcs, "eth_getBlockByNumber", [hex(num), True], timeout=20)
            self.evm_state["eth_native"] = num
            for tx in (blk or {}).get("transactions", []) or []:
                amount = hex_int(tx.get("value")) / 1e18
                if amount * eth_usd >= self.min_usd:
                    h = tx["hash"]
                    out.append(Alert(f"eth:{h}", chain.name, "ETH", amount, amount * eth_usd, tx.get("from"),
                                     tx.get("to") or "", f"{chain.explorer}/tx/{h}"))
        return out

    async def scan_tron(self) -> list[Alert]:
        now_ms = int(time.time() * 1000)
        if self.tron_ts is None or now_ms - self.tron_ts > 5 * 60_000:
            self.tron_ts = now_ms - 60_000
        params = {
            "event_name": "Transfer", "only_confirmed": "true", "limit": 200,
            "min_block_timestamp": self.tron_ts, "order_by": "block_timestamp,asc",
        }
        out = []
        max_pages = 20 if config.trongrid_key else 6
        for _ in range(max_pages):
            data = await get_json(f"https://api.trongrid.io/v1/contracts/{USDT_TRON}/events", params=params,
                                  headers=tron_headers(), timeout=15)
            rows = data.get("data") or []
            for ev in rows:
                res = ev.get("result") or {}
                raw = res.get("value") or res.get("2") or "0"
                amount = int(raw) / 1e6
                self.tron_ts = max(self.tron_ts, int(ev.get("block_timestamp") or 0) + 1)
                if amount >= self.min_usd:
                    sender = res.get("from") or res.get("0") or ""
                    receiver = res.get("to") or res.get("1") or ""
                    sender = tron_hex_to_base58(sender[2:] if sender.startswith("0x") else sender) if not sender.startswith("T") else sender
                    receiver = tron_hex_to_base58(receiver[2:] if receiver.startswith("0x") else receiver) if not receiver.startswith("T") else receiver
                    h = ev.get("transaction_id")
                    out.append(Alert(f"tron:{h}:{ev.get('event_index')}", "TRON (TRC20)", "USDT", amount, amount,
                                     sender, receiver, f"https://tronscan.org/#/transaction/{h}"))
            fingerprint = (data.get("meta") or {}).get("fingerprint")
            if not fingerprint or len(rows) < 200:
                break
            params["fingerprint"] = fingerprint
        return out

    async def scan_btc(self) -> list[Alert]:
        from ..utils.http import get_text

        tip = (await get_text("https://mempool.space/api/blocks/tip/hash", timeout=10)).strip()
        if self.btc_tip is None or tip == self.btc_tip:
            self.btc_tip = tip
            return []
        self.btc_tip = tip
        block = await get_json(f"https://blockchain.info/rawblock/{tip}", params={"format": "json"}, timeout=30)
        btc_usd = await prices.crypto_usd("BTC")
        min_btc = float(gget("whale_btc_min") or 500)
        out = []
        for tx in block.get("tx", [])[1:]:
            outs = tx.get("out", [])
            total = sum(o.get("value", 0) for o in outs) / 1e8
            if total < min_btc:
                continue
            ins = [i.get("prev_out", {}).get("addr") for i in tx.get("inputs", [])]
            main = max(outs, key=lambda o: o.get("value", 0)) if outs else {}
            h = tx.get("hash")
            out.append(Alert(f"btc:{h}", "Bitcoin", "BTC", total, total * (btc_usd or 0), ins[0] if ins else "",
                             main.get("addr") or "", f"https://mempool.space/tx/{h}"))
        return out

    async def scan_whale_alert(self) -> list[Alert]:
        if not config.whale_alert_key:
            return []
        data = await get_json("https://api.whale-alert.io/v1/transactions", params={
            "api_key": config.whale_alert_key, "min_value": int(self.min_usd), "start": self.whale_alert_ts,
        }, timeout=15)
        out = []
        for tx in data.get("transactions", []) or []:
            self.whale_alert_ts = max(self.whale_alert_ts, int(tx.get("timestamp", 0)) + 1)
            frm, to = tx.get("from") or {}, tx.get("to") or {}
            owner_f = frm.get("owner") or ""
            owner_t = to.get("owner") or ""
            if owner_f:
                LABELS.setdefault(frm.get("address", ""), owner_f.title())
            if owner_t:
                LABELS.setdefault(to.get("address", ""), owner_t.title())
            chain = str(tx.get("blockchain", "")).title()
            out.append(Alert(f"wa:{tx.get('blockchain')}:{tx.get('hash')}", chain, str(tx.get("symbol", "")).upper(),
                             float(tx.get("amount") or 0), float(tx.get("amount_usd") or 0), frm.get("address", ""),
                             to.get("address", ""), f"https://whale-alert.io/transaction/{tx.get('blockchain')}/{tx.get('hash')}"))
        return out

    async def publish(self, alerts: list[Alert]) -> None:
        if not alerts:
            return
        db = get_db()
        fresh = []
        for a in sorted(alerts, key=lambda x: x.usd, reverse=True):
            if await db.mark_alert(a.uid):
                fresh.append(a)
        if not fresh:
            return
        fresh = fresh[:4]
        groups = await db.active_groups()
        rate = await prices.usdt_toman()
        for alert in fresh:
            whales = min(10, max(1, int(alert.usd // max(self.min_usd, 1))))
            text = ui.t(
                "whale_alert",
                bar="🐋" * min(whales, 5),
                amount=fmt.num(alert.amount),
                symbol=alert.symbol,
                usd=fmt.integer(alert.usd),
                toman=fmt.toman(alert.usd * rate) if rate else None,
                network=alert.network,
                sender=_label(alert.sender),
                receiver=_label(alert.receiver),
                link=alert.link,
                time=fmt.now_str(),
            )
            for g in groups:
                if not await feature_on(g["id"], "whale_alerts"):
                    continue
                try:
                    await self.bot.send_message(g["id"], text)
                except Exception as exc:
                    log.debug("whale alert to %s failed: %s", g["id"], exc)
                    if "kicked" in str(exc).lower() or "not found" in str(exc).lower():
                        await db.set_chat_active(g["id"], False)
                await asyncio.sleep(0.05)

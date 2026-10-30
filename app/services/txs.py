from __future__ import annotations

import asyncio
import base64
import logging
from dataclasses import dataclass

from ..utils.http import get_json, get_text, post_json, safe
from . import prices
from .chain_common import (
    EVM_CHAINS,
    SOL_RPCS,
    SOL_TOKENS,
    TRANSFER_TOPIC,
    TRC20_TOKENS,
    XRP_RPCS,
    EvmChain,
    decode_abi_string,
    hex_int,
    rpc,
    rpc_batch,
    toncenter_params,
    tonapi_headers,
    topic_to_address,
    tron_headers,
    tron_hex_to_base58,
    tronscan_headers,
)

log = logging.getLogger(__name__)

OK = "✅ موفق"
FAIL = "❌ ناموفق"
PENDING = "⏳ در انتظار تایید"


@dataclass
class TxResult:
    network: str
    status: str
    hash: str
    explorer: str
    amount: float | None = None
    symbol: str | None = None
    sender: str | None = None
    receiver: str | None = None
    fee: str | None = None
    block: int | str | None = None
    confirmations: int | str | None = None
    timestamp: int | None = None
    usd: float | None = None
    token_type: str | None = None
    token_name: str | None = None
    contract: str | None = None


async def _usd(amount: float | None, symbol: str | None) -> float | None:
    if amount is None or not symbol:
        return None
    sym = symbol.upper()
    if sym in prices.STABLES or sym in ("USDC.E", "BUSD"):
        return amount
    p = await prices.crypto_usd("BTC" if sym == "WBTC" else sym)
    return amount * p if p else None


_token_meta_cache: dict[tuple[str, str], tuple[str, int]] = {}


async def _token_meta(chain: EvmChain, contract: str) -> tuple[str, int]:
    for sym, (addr, dec) in chain.tokens.items():
        if addr.lower() == contract.lower():
            return sym, dec
    key = (chain.key, contract.lower())
    if key in _token_meta_cache:
        return _token_meta_cache[key]
    res = await rpc_batch(chain.rpcs, [
        ("eth_call", [{"to": contract, "data": "0x95d89b41"}, "latest"]),
        ("eth_call", [{"to": contract, "data": "0x313ce567"}, "latest"]),
    ])
    sym = decode_abi_string(res[0]) or "TOKEN"
    dec = hex_int(res[1]) if res[1] and res[1] != "0x" else 18
    _token_meta_cache[key] = (sym, dec)
    return sym, dec


async def _evm_tx(chain: EvmChain, h: str) -> TxResult | None:
    tx = await rpc(chain.rpcs, "eth_getTransactionByHash", [h])
    if not tx:
        return None
    receipt, head = await asyncio.gather(
        safe(rpc(chain.rpcs, "eth_getTransactionReceipt", [h])),
        safe(rpc(chain.rpcs, "eth_blockNumber", [])),
    )
    block = hex_int(tx.get("blockNumber")) if tx.get("blockNumber") else None
    status = PENDING
    fee = None
    if receipt:
        status = OK if hex_int(receipt.get("status")) == 1 else FAIL
        gas_price = hex_int(receipt.get("effectiveGasPrice") or tx.get("gasPrice"))
        fee = f"{hex_int(receipt.get('gasUsed')) * gas_price / 1e18:.8f}".rstrip("0").rstrip(".") + f" {chain.symbol}"
    ts = None
    if block is not None:
        blk = await safe(rpc(chain.rpcs, "eth_getBlockByNumber", [hex(block), False]))
        ts = hex_int(blk.get("timestamp")) if blk else None
    amount = hex_int(tx.get("value")) / 1e18
    symbol = chain.symbol
    sender, receiver = tx.get("from"), tx.get("to")
    for log_item in (receipt or {}).get("logs", []) or []:
        topics = log_item.get("topics") or []
        if len(topics) == 3 and topics[0].lower() == TRANSFER_TOPIC:
            sym, dec = await _token_meta(chain, log_item["address"])
            amount = hex_int(log_item.get("data")) / 10 ** dec
            symbol = sym
            sender, receiver = topic_to_address(topics[1]), topic_to_address(topics[2])
            break
    else:
        data = tx.get("input") or ""
        if not receipt and data.startswith("0xa9059cbb") and len(data) >= 138:
            sym, dec = await _token_meta(chain, tx["to"])
            receiver = "0x" + data[34:74]
            amount = int(data[74:138], 16) / 10 ** dec
            symbol = sym
    confirmations = (hex_int(head) - block + 1) if head and block is not None else 0
    return TxResult(chain.name, status, h, f"{chain.explorer}/tx/{h}", amount, symbol, sender, receiver, fee, block,
                    confirmations, ts)


async def check_evm_tx(h: str) -> TxResult | None:
    tasks = [asyncio.create_task(safe(_evm_tx(c, h), label=c.key)) for c in EVM_CHAINS]
    for coro in asyncio.as_completed(tasks):
        result = await coro
        if result:
            for t in tasks:
                t.cancel()
            return result
    return None


async def _esplora_tx(base: str, h: str, name: str, symbol: str, explorer: str) -> TxResult | None:
    tx = await get_json(f"{base}/api/tx/{h}", timeout=10)
    if not isinstance(tx, dict) or tx.get("txid") != h:
        return None
    st = tx.get("status") or {}
    tip = await safe(get_text(f"{base}/api/blocks/tip/height", timeout=8))
    conf = int(tip) - st["block_height"] + 1 if st.get("confirmed") and tip and tip.strip().isdigit() else 0
    vin = tx.get("vin") or []
    vout = tx.get("vout") or []
    senders = {v.get("prevout", {}).get("scriptpubkey_address") for v in vin if v.get("prevout")}
    total_out = sum(o.get("value", 0) for o in vout) / 1e8
    main_out = max(vout, key=lambda o: o.get("value", 0)) if vout else {}
    sender = next(iter(senders - {None}), None)
    if len(senders) > 1:
        sender = f"{sender} (+{len(senders) - 1})"
    return TxResult(name, OK if st.get("confirmed") else PENDING, h, f"{explorer}/tx/{h}", total_out, symbol,
                    sender, main_out.get("scriptpubkey_address"), f"{tx.get('fee', 0) / 1e8:g} {symbol}",
                    st.get("block_height"), conf, st.get("block_time"))


async def _blockcypher_tx(coin: str, h: str, name: str, symbol: str, explorer: str) -> TxResult | None:
    tx = await get_json(f"https://api.blockcypher.com/v1/{coin}/main/txs/{h}", timeout=10)
    if not isinstance(tx, dict) or tx.get("error") or tx.get("hash") != h:
        return None
    ins = [a for i in tx.get("inputs", []) for a in (i.get("addresses") or [])]
    outs = sorted(tx.get("outputs", []), key=lambda o: o.get("value", 0), reverse=True)
    receiver = (outs[0].get("addresses") or [None])[0] if outs else None
    conf = tx.get("confirmations", 0)
    return TxResult(name, OK if conf else PENDING, h, explorer, tx.get("total", 0) / 1e8, symbol,
                    ins[0] if ins else None, receiver, f"{tx.get('fees', 0) / 1e8:g} {symbol}",
                    tx.get("block_height") if tx.get("block_height", -1) > 0 else None, conf)


async def check_btc_tx(h: str) -> TxResult | None:
    return (await safe(_esplora_tx("https://mempool.space", h, "Bitcoin", "BTC", "https://mempool.space"))
            or await safe(_esplora_tx("https://blockstream.info", h, "Bitcoin", "BTC", "https://mempool.space")))


async def check_ltc_tx(h: str) -> TxResult | None:
    return await safe(_esplora_tx("https://litecoinspace.org", h, "Litecoin", "LTC", "https://litecoinspace.org"))


async def check_doge_tx(h: str) -> TxResult | None:
    return await safe(_blockcypher_tx("doge", h, "Dogecoin", "DOGE", f"https://blockchair.com/dogecoin/transaction/{h}"))


async def check_dash_tx(h: str) -> TxResult | None:
    return await safe(_blockcypher_tx("dash", h, "Dash", "DASH", f"https://blockchair.com/dash/transaction/{h}"))


async def check_tron_tx(h: str) -> TxResult | None:
    explorer = f"https://tronscan.org/#/transaction/{h}"
    info = await safe(get_json("https://apilist.tronscanapi.com/api/transaction-info", params={"hash": h},
                               headers=tronscan_headers(), timeout=10))
    if isinstance(info, dict) and info.get("hash") and info.get("contractRet") is not None:
        status = OK if info.get("contractRet") == "SUCCESS" and info.get("confirmed") else (
            PENDING if not info.get("confirmed") else FAIL)
        cd = info.get("contractData") or {}
        amount, symbol = None, "TRX"
        token_type, token_name, contract = "COIN", "TRON", None
        sender = cd.get("owner_address") or info.get("ownerAddress")
        receiver = cd.get("to_address") or info.get("toAddress")
        trc20 = info.get("trc20TransferInfo") or info.get("tokenTransferInfo")
        if isinstance(trc20, list) and trc20:
            trc20 = trc20[0]
        if isinstance(trc20, dict) and trc20.get("amount_str"):
            dec = int(trc20.get("decimals") or 6)
            amount = int(trc20["amount_str"]) / 10 ** dec
            symbol = trc20.get("symbol") or "TRC20"
            token_type, token_name = "TRC20", trc20.get("name") or symbol
            contract = trc20.get("contract_address") or cd.get("contract_address")
            sender = trc20.get("from_address") or sender
            receiver = trc20.get("to_address") or receiver
        elif cd.get("asset_name") and cd.get("amount") is not None:
            ti = cd.get("tokenInfo") or {}
            dec = int(ti.get("tokenDecimal") or 0)
            amount = int(cd["amount"]) / 10 ** dec
            symbol = ti.get("tokenAbbr") or str(cd.get("asset_name"))
            token_type, token_name, contract = "TRC10", ti.get("tokenName") or symbol, str(ti.get("tokenId") or cd.get("asset_name"))
        elif cd.get("amount") is not None:
            amount = int(cd["amount"]) / 1e6
        cost = info.get("cost") or {}
        fee_sun = cost.get("fee") or 0
        fee = f"{fee_sun / 1e6:g} TRX"
        if cost.get("energy_usage_total"):
            fee += f" • ⚡️ {cost['energy_usage_total']:,} Energy"
        ts = int(info["timestamp"]) // 1000 if info.get("timestamp") else None
        return TxResult("TRON", status, h, explorer, amount, symbol, sender, receiver, fee,
                        info.get("block"), info.get("confirmations"), ts, None, token_type, token_name, contract)

    tx = await safe(post_json("https://api.trongrid.io/wallet/gettransactionbyid", {"value": h},
                              headers=tron_headers(), timeout=10))
    if not tx or not tx.get("txID"):
        return None
    txinfo = await safe(post_json("https://api.trongrid.io/wallet/gettransactioninfobyid", {"value": h},
                                  headers=tron_headers(), timeout=10)) or {}
    contract = ((tx.get("raw_data") or {}).get("contract") or [{}])[0]
    value = (contract.get("parameter") or {}).get("value") or {}
    amount, symbol = None, "TRX"
    token_type, token_name, token_contract = "COIN", "TRON", None
    sender = tron_hex_to_base58(value.get("owner_address", ""))
    receiver = tron_hex_to_base58(value.get("to_address", "")) if value.get("to_address") else None
    if contract.get("type") == "TransferContract":
        amount = value.get("amount", 0) / 1e6
    elif contract.get("type") == "TransferAssetContract":
        asset = value.get("asset_name", "")
        try:
            asset = bytes.fromhex(asset).decode()
        except ValueError:
            pass
        amount, symbol = value.get("amount", 0), str(asset)
        token_type, token_name, token_contract = "TRC10", str(asset), str(asset)
    elif contract.get("type") == "TriggerSmartContract":
        data = value.get("data", "")
        token_addr = tron_hex_to_base58(value.get("contract_address", ""))
        token_type, token_contract = "TRC20", token_addr
        if data.startswith("a9059cbb") and len(data) >= 136:
            sym, dec = TRC20_TOKENS.get(token_addr, ("TRC20", 6))
            receiver = tron_hex_to_base58("41" + data[32:72])
            amount = int(data[72:136], 16) / 10 ** dec
            symbol = token_name = sym
    ret = ((tx.get("ret") or [{}])[0]).get("contractRet")
    status = OK if ret == "SUCCESS" and txinfo.get("blockNumber") else (FAIL if ret and ret != "SUCCESS" else PENDING)
    fee = f"{txinfo.get('fee', 0) / 1e6:g} TRX"
    ts = txinfo.get("blockTimeStamp", 0) // 1000 or None
    return TxResult("TRON", status, h, explorer, amount, symbol, sender, receiver, fee,
                    txinfo.get("blockNumber"), None, ts, None, token_type, token_name, token_contract)


def _ton_hash_variants(h: str) -> list[str]:
    out = [h]
    try:
        if len(h) == 64:
            out.append(base64.urlsafe_b64encode(bytes.fromhex(h)).decode())
        else:
            raw = base64.urlsafe_b64decode(h.replace("+", "-").replace("/", "_") + "=" * (-len(h) % 4))
            out.append(raw.hex())
    except Exception:
        pass
    return out


async def check_ton_tx(h: str) -> TxResult | None:
    hex_hash = next((v for v in _ton_hash_variants(h) if len(v) == 64), None)
    if hex_hash:
        ev = await safe(get_json(f"https://tonapi.io/v2/events/{hex_hash}", headers=tonapi_headers(), timeout=10))
        if isinstance(ev, dict) and ev.get("actions"):
            action = ev["actions"][0]
            kind = action.get("type")
            body = action.get(kind) or {}
            amount, symbol = None, "TON"
            if kind == "TonTransfer":
                amount = int(body.get("amount", 0)) / 1e9
            elif kind == "JettonTransfer":
                j = body.get("jetton") or {}
                amount = int(body.get("amount", 0)) / 10 ** int(j.get("decimals") or 9)
                symbol = j.get("symbol") or "JETTON"
            elif kind == "NftItemTransfer":
                symbol = "NFT"
            sender = (body.get("sender") or {}).get("address") or ((action.get("simple_preview") or {}).get("accounts") or [{}])[0].get("address")
            receiver = (body.get("recipient") or {}).get("address")
            status = OK if action.get("status") == "ok" else FAIL
            if ev.get("in_progress"):
                status = PENDING
            desc = (action.get("simple_preview") or {}).get("description")
            res = TxResult("TON", status, h, f"https://tonviewer.com/transaction/{hex_hash}", amount, symbol,
                           sender, receiver, None, None, None, ev.get("timestamp"))
            if amount is None and desc:
                res.symbol = desc
            return res
    for variant in _ton_hash_variants(h):
        data = await safe(get_json("https://toncenter.com/api/v3/transactions",
                                   params={"hash": variant, "limit": 1, **toncenter_params()}, timeout=10))
        txs = (data or {}).get("transactions") or []
        if txs:
            tx = txs[0]
            in_msg = tx.get("in_msg") or {}
            book = (data or {}).get("address_book") or {}
            def friendly(a, book=book):
                return (book.get(a) or {}).get("user_friendly", a) if a else None

            aborted = (tx.get("description") or {}).get("aborted")
            return TxResult("TON", FAIL if aborted else OK, h, f"https://tonviewer.com/transaction/{tx.get('hash')}",
                            int(in_msg.get("value") or 0) / 1e9, "TON", friendly(in_msg.get("source")),
                            friendly(in_msg.get("destination")), f"{int(tx.get('total_fees') or 0) / 1e9:g} TON",
                            tx.get("mc_block_seqno"), None, tx.get("now"))
    return None


async def check_sol_tx(sig: str) -> TxResult | None:
    tx = await safe(rpc(SOL_RPCS, "getTransaction",
                        [sig, {"encoding": "jsonParsed", "maxSupportedTransactionVersion": 0}]))
    if not tx:
        return None
    meta = tx.get("meta") or {}
    status = FAIL if meta.get("err") else OK
    instructions = list(((tx.get("transaction") or {}).get("message") or {}).get("instructions") or [])
    for inner in meta.get("innerInstructions") or []:
        instructions.extend(inner.get("instructions") or [])
    amount = symbol = sender = receiver = None
    for ins in instructions:
        parsed = ins.get("parsed")
        if not isinstance(parsed, dict):
            continue
        info = parsed.get("info") or {}
        if parsed.get("type") == "transfer" and "lamports" in info:
            amount, symbol = info["lamports"] / 1e9, "SOL"
            sender, receiver = info.get("source"), info.get("destination")
            break
        if parsed.get("type") in ("transfer", "transferChecked") and ("tokenAmount" in info or "amount" in info):
            ta = info.get("tokenAmount") or {}
            amount = float(ta.get("uiAmount") or 0) if ta else None
            mint = info.get("mint")
            symbol = SOL_TOKENS.get(mint, "SPL")
            if amount is None:
                for b in meta.get("postTokenBalances") or []:
                    if b.get("mint") in SOL_TOKENS:
                        symbol = SOL_TOKENS[b["mint"]]
                        dec = (b.get("uiTokenAmount") or {}).get("decimals", 6)
                        amount = int(info.get("amount", 0)) / 10 ** dec
                        break
            sender, receiver = info.get("authority") or info.get("source"), info.get("destination")
            break
    return TxResult("Solana", status, sig, f"https://solscan.io/tx/{sig}", amount, symbol, sender, receiver,
                    f"{meta.get('fee', 0) / 1e9:g} SOL", tx.get("slot"), None, tx.get("blockTime"))


async def check_xrp_tx(h: str) -> TxResult | None:
    for url in XRP_RPCS:
        data = await safe(post_json(url, {"method": "tx", "params": [{"transaction": h, "binary": False}]},
                                    timeout=10))
        result = (data or {}).get("result") or {}
        if result.get("error") == "txnNotFound":
            return None
        if result.get("hash"):
            txj = result.get("tx_json") or result
            amt = (result.get("meta") or {}).get("delivered_amount") or txj.get("Amount") or txj.get("DeliverMax")
            if isinstance(amt, dict):
                amount, symbol = float(amt.get("value", 0)), amt.get("currency")
            else:
                amount, symbol = (int(amt) / 1e6 if amt else None), "XRP"
            res_code = (result.get("meta") or {}).get("TransactionResult")
            status = OK if res_code == "tesSUCCESS" and result.get("validated") else (
                PENDING if not result.get("validated") else FAIL)
            date = txj.get("date") or result.get("date")
            return TxResult("XRP Ledger", status, h, f"https://xrpscan.com/tx/{h}", amount, symbol,
                            txj.get("Account"), txj.get("Destination"), f"{int(txj.get('Fee', 0)) / 1e6:g} XRP",
                            result.get("ledger_index"), None, date + 946684800 if date else None)
    return None


async def check_tx(kind: str, h: str) -> TxResult | None:
    if kind == "hex64x":
        result = await check_evm_tx(h)
    elif kind == "hex64":
        result = None
        tasks = [
            asyncio.create_task(fn(h))
            for fn in (check_btc_tx, check_tron_tx, check_ltc_tx, check_doge_tx, check_dash_tx, check_xrp_tx,
                       check_ton_tx)
        ]
        for coro in asyncio.as_completed(tasks):
            result = await safe(coro)
            if result:
                break
        for t in tasks:
            t.cancel()
        if not result:
            result = await check_evm_tx("0x" + h)
    elif kind == "solsig":
        result = await check_sol_tx(h)
    elif kind in ("b64hash", "sol"):
        result = await check_ton_tx(h)
    else:
        result = None
    if result and result.usd is None:
        result.usd = await safe(_usd(result.amount, result.symbol))
    return result

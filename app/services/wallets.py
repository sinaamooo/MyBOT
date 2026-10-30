from __future__ import annotations

import asyncio
import logging
from dataclasses import dataclass, field

from ..utils.http import get_json, post_json, safe
from . import prices
from .chain_common import (
    EVM_CHAINS,
    SOL_RPCS,
    SOL_TOKENS,
    SUI_RPCS,
    TRC20_TOKENS,
    XRP_RPCS,
    EvmChain,
    balance_of_data,
    hex_int,
    rpc,
    rpc_batch,
    toncenter_params,
    tonapi_headers,
    tron_headers,
    tronscan_headers,
)

log = logging.getLogger(__name__)


@dataclass
class Token:
    symbol: str
    amount: float
    usd: float | None = None


@dataclass
class ChainResult:
    chain: str
    symbol: str
    balance: float
    explorer: str
    usd: float | None = None
    tokens: list[Token] = field(default_factory=list)
    txs: int | str | None = None
    nfts: int | None = None
    status: str | None = None
    created: int | None = None
    kind: str | None = None

    @property
    def total_usd(self) -> float:
        return (self.usd or 0) + sum(t.usd or 0 for t in self.tokens)

    @property
    def active(self) -> bool:
        return bool(self.balance > 0 or self.tokens or (self.txs not in (None, 0, "0")) or self.nfts)


async def _price(symbol: str) -> float | None:
    sym = symbol.upper().replace(".E", "")
    if sym in prices.STABLES or sym in ("USDC.E", "BUSD"):
        return 1.0
    if sym == "WBTC":
        sym = "BTC"
    return await prices.crypto_usd(sym)


async def _fill_usd(result: ChainResult) -> ChainResult:
    p = await _price(result.symbol)
    result.usd = result.balance * p if p is not None else None
    for tok in result.tokens:
        if tok.usd is None:
            tp = await _price(tok.symbol)
            tok.usd = tok.amount * tp if tp is not None else None
    return result


async def _evm_chain(chain: EvmChain, address: str) -> ChainResult | None:
    calls: list[tuple[str, list]] = [
        ("eth_getBalance", [address, "latest"]),
        ("eth_getTransactionCount", [address, "latest"]),
        ("eth_getCode", [address, "latest"]),
    ]
    token_items = list(chain.tokens.items())
    for _sym, (contract, _dec) in token_items:
        calls.append(("eth_call", [{"to": contract, "data": balance_of_data(address)}, "latest"]))
    res = await rpc_batch(chain.rpcs, calls)
    if res[0] is None and res[1] is None:
        return None
    balance = hex_int(res[0]) / 1e18
    nonce = hex_int(res[1])
    code = res[2] or "0x"
    tokens = []
    for (sym, (_contract, dec)), raw in zip(token_items, res[3:]):
        amount = hex_int(raw) / 10 ** dec if raw and raw != "0x" else 0
        if amount > 0:
            tokens.append(Token(sym, amount))
    status = "📜 قرارداد هوشمند" if code not in ("0x", "0x0", None) else None
    return ChainResult(chain.name, chain.symbol, balance, f"{chain.explorer}/address/{address}", tokens=tokens,
                       txs=nonce, status=status)


async def check_evm(address: str) -> list[ChainResult]:
    results = await asyncio.gather(*(safe(_evm_chain(c, address), label=c.key) for c in EVM_CHAINS))
    return [r for r in results if r]


async def _esplora(base: str, address: str, name: str, symbol: str, explorer: str) -> ChainResult:
    data = await get_json(f"{base}/api/address/{address}", timeout=10)
    cs, ms = data.get("chain_stats", {}), data.get("mempool_stats", {})
    sat = cs.get("funded_txo_sum", 0) - cs.get("spent_txo_sum", 0)
    pending = ms.get("funded_txo_sum", 0) - ms.get("spent_txo_sum", 0)
    status = f"⏳ در انتظار: {pending / 1e8:+g} {symbol}" if pending else None
    return ChainResult(name, symbol, sat / 1e8, f"{explorer}/address/{address}",
                       txs=cs.get("tx_count", 0) + ms.get("tx_count", 0), status=status)


async def _blockcypher(coin: str, address: str, name: str, symbol: str, explorer: str) -> ChainResult:
    data = await get_json(f"https://api.blockcypher.com/v1/{coin}/main/addrs/{address}/balance", timeout=10)
    bal = data.get("final_balance", data.get("balance", 0)) / 1e8
    return ChainResult(name, symbol, bal, explorer, txs=data.get("final_n_tx", data.get("n_tx")))


async def check_btc(address: str) -> list[ChainResult]:
    for base in ("https://mempool.space", "https://blockstream.info"):
        r = await safe(_esplora(base, address, "Bitcoin", "BTC", "https://mempool.space"))
        if r:
            return [r]
    r = await safe(_blockcypher("btc", address, "Bitcoin", "BTC", f"https://mempool.space/address/{address}"))
    return [r] if r else []


async def check_ltc(address: str) -> list[ChainResult]:
    r = await safe(_esplora("https://litecoinspace.org", address, "Litecoin", "LTC", "https://litecoinspace.org"))
    if not r:
        r = await safe(_blockcypher("ltc", address, "Litecoin", "LTC", f"https://litecoinspace.org/address/{address}"))
    return [r] if r else []


async def check_doge(address: str) -> list[ChainResult]:
    explorer = f"https://blockchair.com/dogecoin/address/{address}"
    r = await safe(_blockcypher("doge", address, "Dogecoin", "DOGE", explorer))
    if not r:
        data = await safe(get_json(f"https://dogechain.info/api/v1/address/balance/{address}", timeout=10))
        if data and data.get("success"):
            r = ChainResult("Dogecoin", "DOGE", float(data.get("balance", 0)), explorer)
    return [r] if r else []


async def check_dash(address: str) -> list[ChainResult]:
    r = await safe(_blockcypher("dash", address, "Dash", "DASH", f"https://blockchair.com/dash/address/{address}"))
    return [r] if r else []


async def check_bch(address: str) -> list[ChainResult]:
    addr = address if address.startswith("bitcoincash:") else f"bitcoincash:{address}"
    data = await safe(get_json(f"https://api.blockchair.com/bitcoin-cash/dashboards/address/{addr}", timeout=12))
    if not data:
        return []
    info = next(iter((data.get("data") or {}).values()), {}).get("address", {})
    if not info:
        return []
    return [ChainResult("Bitcoin Cash", "BCH", info.get("balance", 0) / 1e8,
                        f"https://blockchair.com/bitcoin-cash/address/{addr}", txs=info.get("transaction_count"))]


async def tron_all_tokens(address: str) -> dict:
    data = await get_json("https://apilist.tronscanapi.com/api/account/tokens",
                          params={"address": address, "start": 0, "limit": 1000}, headers=tronscan_headers(), timeout=15)
    tokens = []
    for item in data.get("data") or []:
        ttype = str(item.get("tokenType") or "").lower()
        entry = {
            "Amount": item.get("quantity"),
            "Name": item.get("tokenName"),
            "Symbol": item.get("tokenAbbr"),
            "TokenType": "COIN" if item.get("tokenId") == "_" else ttype.upper(),
        }
        if ttype == "trc20":
            entry["Contract_address"] = item.get("tokenId")
        else:
            entry["TokenId"] = item.get("tokenId")
        if item.get("amountInUsd") is not None:
            entry["AmountInUsd"] = item.get("amountInUsd")
        tokens.append(entry)
    return {"total": data.get("total", len(tokens)), "wallet": address, "token": tokens}


async def tron_trc20_transfers(address: str, page: int = 1, per_page: int = 10) -> dict:
    data = await get_json("https://apilist.tronscanapi.com/api/new/token_trc20/transfers",
                          params={"limit": per_page, "start": (page - 1) * per_page, "relatedAddress": address},
                          headers=tronscan_headers(), timeout=15)
    items = []
    for item in data.get("token_transfers") or []:
        info = item.get("tokenInfo") or {}
        dec = int(info.get("tokenDecimal") or 0)
        try:
            amount = int(item.get("quant") or 0) / 10 ** dec
        except (TypeError, ValueError):
            amount = 0
        items.append({
            "amount": amount,
            "symbol": info.get("tokenAbbr") or "TRC20",
            "from": item.get("from_address"),
            "to": item.get("to_address"),
            "ts": int(item.get("block_ts") or 0) // 1000,
            "hash": item.get("transaction_id"),
        })
    total = int(data.get("total") or 0)
    return {"total": total, "pages": max(1, -(-total // per_page)), "page": page, "items": items}


async def check_tron(address: str) -> list[ChainResult]:
    grid, scan, all_tokens = await asyncio.gather(
        safe(get_json(f"https://api.trongrid.io/v1/accounts/{address}", headers=tron_headers(), timeout=10)),
        safe(get_json("https://apilist.tronscanapi.com/api/accountv2", params={"address": address},
                      headers=tronscan_headers(), timeout=10)),
        safe(tron_all_tokens(address)),
    )
    explorer = f"https://tronscan.org/#/address/{address}"
    tokens: dict[str, Token] = {}
    trx = None
    status = None
    txs = None
    created = None
    acc = (grid or {}).get("data") or []
    if acc:
        a = acc[0]
        trx = a.get("balance", 0) / 1e6
        created = int(a["create_time"]) // 1000 if a.get("create_time") else None
        for item in a.get("trc20", []) or []:
            for contract, raw in item.items():
                if contract in TRC20_TOKENS:
                    sym, dec = TRC20_TOKENS[contract]
                    amount = int(raw) / 10 ** dec
                    if amount > 0:
                        tokens[sym] = Token(sym, amount)
    elif grid is not None and grid.get("success") and not acc:
        status = "⚪️ اکانت فعال نشده"
    if isinstance(scan, dict) and scan:
        txs = scan.get("totalTransactionCount") or scan.get("transactions")
        if created is None and scan.get("date_created"):
            created = int(scan["date_created"]) // 1000
        if trx is None and scan.get("balance") is not None:
            trx = scan.get("balance", 0) / 1e6
        for tok in scan.get("withPriceTokens") or []:
            sym = (tok.get("tokenAbbr") or "").upper()
            if not sym or sym == "TRX" or sym in tokens:
                continue
            try:
                amount = float(tok.get("amount") or 0) or int(tok.get("balance") or 0) / 10 ** int(tok.get("tokenDecimal") or 0)
            except (TypeError, ValueError):
                continue
            if amount > 0 and tok.get("tokenType") == "trc20":
                usd = None
                pin_trx = tok.get("tokenPriceInTrx")
                if pin_trx:
                    trx_usd = await prices.crypto_usd("TRX")
                    usd = amount * float(pin_trx) * trx_usd if trx_usd else None
                tokens[sym] = Token(sym, amount, usd)
            if len(tokens) > 12:
                break
        if scan.get("activated") is False:
            status = "⚪️ اکانت فعال نشده"
    for item in (all_tokens or {}).get("token", []):
        sym = str(item.get("Symbol") or "").upper()
        try:
            amount = float(item.get("Amount") or 0)
        except (TypeError, ValueError):
            continue
        if item.get("TokenType") == "COIN" or sym == "TRX":
            if trx is None:
                trx = amount
            continue
        if not sym or sym in tokens or len(tokens) > 15 or amount <= 0:
            continue
        usd = item.get("AmountInUsd")
        tokens[sym] = Token(sym, amount, float(usd) if usd else None)
    if trx is None and not tokens:
        return []
    return [ChainResult("TRON", "TRX", trx or 0, explorer, tokens=list(tokens.values()), txs=txs,
                        status=status, created=created, kind="tron")]


async def check_ton(address: str) -> list[ChainResult]:
    acc, jettons, nfts, center = await asyncio.gather(
        safe(get_json(f"https://tonapi.io/v2/accounts/{address}", headers=tonapi_headers(), timeout=10)),
        safe(get_json(f"https://tonapi.io/v2/accounts/{address}/jettons", params={"currencies": "usd"},
                      headers=tonapi_headers(), timeout=10)),
        safe(get_json(f"https://tonapi.io/v2/accounts/{address}/nfts", params={"limit": 1000},
                      headers=tonapi_headers(), timeout=12)),
        safe(get_json("https://toncenter.com/api/v2/getAddressInformation",
                      params={"address": address, **toncenter_params()}, timeout=10)),
    )
    balance = None
    status = None
    if isinstance(acc, dict) and "balance" in acc:
        balance = int(acc["balance"]) / 1e9
        status = {"active": "🟢 فعال", "uninit": "⚪️ فعال نشده", "frozen": "🧊 فریز"}.get(acc.get("status"))
        if acc.get("name"):
            status = f"{status or ''} • {acc['name']}".strip(" •")
    elif isinstance(center, dict) and center.get("ok"):
        balance = int(center["result"].get("balance", 0)) / 1e9
        state = center["result"].get("state")
        status = {"active": "🟢 فعال", "uninitialized": "⚪️ فعال نشده", "frozen": "🧊 فریز"}.get(state)
    if balance is None:
        return []
    tokens = []
    for item in (jettons or {}).get("balances", []) or []:
        j = item.get("jetton") or {}
        dec = int(j.get("decimals") or 9)
        amount = int(item.get("balance") or 0) / 10 ** dec
        if amount <= 0:
            continue
        usd_price = ((item.get("price") or {}).get("prices") or {}).get("USD")
        tokens.append(Token(j.get("symbol") or "JETTON", amount, amount * usd_price if usd_price else None))
    tokens.sort(key=lambda t: t.usd or 0, reverse=True)
    nft_count = len((nfts or {}).get("nft_items", []) or []) if nfts else None
    return [ChainResult("TON", "TON", balance, f"https://tonviewer.com/{address}", tokens=tokens[:12],
                        nfts=nft_count, status=status)]


async def check_sol(address: str) -> list[ChainResult]:
    bal = await safe(rpc(SOL_RPCS, "getBalance", [address]))
    if bal is None:
        return []
    lamports = bal.get("value", 0) if isinstance(bal, dict) else 0
    tokens: list[Token] = []
    for program in ("TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA", "TokenzQdBNbLqP5VEhdkAS6EPFLC1PHnBqCXEpPxuEb"):
        res = await safe(rpc(SOL_RPCS, "getTokenAccountsByOwner",
                             [address, {"programId": program}, {"encoding": "jsonParsed"}]))
        for acc in (res or {}).get("value", []) or []:
            info = (((acc.get("account") or {}).get("data") or {}).get("parsed") or {}).get("info") or {}
            amount = float((info.get("tokenAmount") or {}).get("uiAmount") or 0)
            mint = info.get("mint")
            if amount > 0 and mint in SOL_TOKENS:
                tokens.append(Token(SOL_TOKENS[mint], amount))
    sigs = await safe(rpc(SOL_RPCS, "getSignaturesForAddress", [address, {"limit": 1000}]))
    txs = None
    if isinstance(sigs, list):
        txs = f"{len(sigs)}+" if len(sigs) >= 1000 else len(sigs)
    return [ChainResult("Solana", "SOL", lamports / 1e9, f"https://solscan.io/account/{address}", tokens=tokens,
                        txs=txs)]


async def check_xrp(address: str) -> list[ChainResult]:
    for url in XRP_RPCS:
        data = await safe(post_json(url, {"method": "account_info",
                                          "params": [{"account": address, "ledger_index": "validated"}]}, timeout=10))
        result = (data or {}).get("result") or {}
        if "account_data" in result:
            ad = result["account_data"]
            return [ChainResult("XRP Ledger", "XRP", int(ad.get("Balance", 0)) / 1e6,
                                f"https://xrpscan.com/account/{address}", txs=ad.get("Sequence"))]
        if result.get("error") == "actNotFound":
            return []
    return []


async def check_sui(address: str) -> list[ChainResult]:
    res = await safe(rpc(SUI_RPCS, "suix_getAllBalances", [address]))
    if not isinstance(res, list) or not res:
        return []
    sui = 0.0
    tokens = []
    for item in res:
        ctype = item.get("coinType", "")
        amount = int(item.get("totalBalance", 0))
        if ctype == "0x2::sui::SUI":
            sui = amount / 1e9
        elif ctype.endswith("::usdc::USDC"):
            tokens.append(Token("USDC", amount / 1e6))
        elif ctype.endswith("::coin::COIN") or ctype.endswith("::usdt::USDT"):
            tokens.append(Token("USDT", amount / 1e6))
    return [ChainResult("Sui", "SUI", sui, f"https://suiscan.xyz/mainnet/account/{address}", tokens=tokens)]


async def check_aptos(address: str) -> list[ChainResult]:
    base = "https://fullnode.mainnet.aptoslabs.com/v1"
    raw = await safe(get_json(f"{base}/accounts/{address}/balance/0x1::aptos_coin::AptosCoin", timeout=10))
    if raw is None:
        res = await safe(get_json(
            f"{base}/accounts/{address}/resource/0x1::coin::CoinStore%3C0x1::aptos_coin::AptosCoin%3E", timeout=10))
        raw = int(((res or {}).get("data") or {}).get("coin", {}).get("value", 0)) if res else None
    if raw is None:
        return []
    acc = await safe(get_json(f"{base}/accounts/{address}", timeout=10))
    return [ChainResult("Aptos", "APT", int(raw) / 1e8, f"https://explorer.aptoslabs.com/account/{address}?network=mainnet",
                        txs=int((acc or {}).get("sequence_number", 0)) if acc else None)]


CHECKERS = {
    "evm": check_evm,
    "btc": check_btc,
    "ltc": check_ltc,
    "doge": check_doge,
    "dash": check_dash,
    "bch": check_bch,
    "tron": check_tron,
    "ton": check_ton,
    "sol": check_sol,
    "xrp": check_xrp,
}


async def check_wallet(kind: str, address: str) -> list[ChainResult]:
    if kind == "hex64x":
        results = (await asyncio.gather(safe(check_sui(address), []), safe(check_aptos(address), [])))
        found = [r for group in results for r in group]
    else:
        fn = CHECKERS.get(kind)
        if not fn:
            return []
        found = await safe(asyncio.wait_for(fn(address), 30), [])
    found = [r for r in found if r and r.active]
    await asyncio.gather(*(safe(_fill_usd(r)) for r in found))
    found.sort(key=lambda r: r.total_usd, reverse=True)
    return found

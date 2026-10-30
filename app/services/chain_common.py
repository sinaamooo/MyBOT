from __future__ import annotations

import hashlib
import re
from dataclasses import dataclass, field

from ..config import config
from ..utils.http import post_json, safe

B58_ALPHABET = "123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz"
TRANSFER_TOPIC = "0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef"


@dataclass
class EvmChain:
    key: str
    name: str
    symbol: str
    rpcs: list[str]
    explorer: str
    tokens: dict[str, tuple[str, int]] = field(default_factory=dict)


EVM_CHAINS: list[EvmChain] = [
    EvmChain(
        "eth", "Ethereum (ERC20)", "ETH",
        ["https://ethereum-rpc.publicnode.com", "https://eth.llamarpc.com", "https://cloudflare-eth.com",
         "https://eth.drpc.org"],
        "https://etherscan.io",
        {
            "USDT": ("0xdAC17F958D2ee523a2206206994597C13D831ec7", 6),
            "USDC": ("0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48", 6),
            "DAI": ("0x6B175474E89094C44Da98b954EedeAC495271d0F", 18),
            "WBTC": ("0x2260FAC5E5542a773Aa44fBCfeDf7C193bc2C599", 8),
            "SHIB": ("0x95aD61b0a150d79219dCF64E1E6Cc01f0B64C4cE", 18),
            "PEPE": ("0x6982508145454Ce325dDbE47a25d4ec3d2311933", 18),
            "LINK": ("0x514910771AF9Ca656af840dff83E8264EcF986CA", 18),
        },
    ),
    EvmChain(
        "bsc", "BNB Smart Chain (BEP20)", "BNB",
        ["https://bsc-rpc.publicnode.com", "https://bsc-dataseed.bnbchain.org", "https://bsc-dataseed1.binance.org",
         "https://bsc.drpc.org"],
        "https://bscscan.com",
        {
            "USDT": ("0x55d398326f99059fF775485246999027B3197955", 18),
            "USDC": ("0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d", 18),
            "BUSD": ("0xe9e7CEA3DedcA5984780Bafc599bD69ADd087D56", 18),
            "ETH": ("0x2170Ed0880ac9A755fd29B2688956BD959F933F8", 18),
            "CAKE": ("0x0E09FaBB73Bd3Ade0a17ECC321fD13a19e81cE82", 18),
        },
    ),
    EvmChain(
        "polygon", "Polygon", "POL",
        ["https://polygon-bor-rpc.publicnode.com", "https://polygon-rpc.com", "https://polygon.drpc.org"],
        "https://polygonscan.com",
        {
            "USDT": ("0xc2132D05D31c914a87C6611C10748AEb04B58e8F", 6),
            "USDC": ("0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359", 6),
            "USDC.e": ("0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174", 6),
        },
    ),
    EvmChain(
        "arbitrum", "Arbitrum One", "ETH",
        ["https://arbitrum-one-rpc.publicnode.com", "https://arb1.arbitrum.io/rpc", "https://arbitrum.drpc.org"],
        "https://arbiscan.io",
        {
            "USDT": ("0xFd086bC7CD5C481DCC9C85ebE478A1C0b69FCbb9", 6),
            "USDC": ("0xaf88d065e77c8cC2239327C5EDb3A432268e5831", 6),
            "ARB": ("0x912CE59144191C1204E64559FE8253a0e49E6548", 18),
        },
    ),
    EvmChain(
        "optimism", "Optimism", "ETH",
        ["https://optimism-rpc.publicnode.com", "https://mainnet.optimism.io", "https://optimism.drpc.org"],
        "https://optimistic.etherscan.io",
        {
            "USDT": ("0x94b008aA00579c1307B0EF2c499aD98a8ce58e58", 6),
            "USDC": ("0x0b2C639c533813f4Aa9D7837CAf62653d097Ff85", 6),
            "OP": ("0x4200000000000000000000000000000000000042", 18),
        },
    ),
    EvmChain(
        "base", "Base", "ETH",
        ["https://base-rpc.publicnode.com", "https://mainnet.base.org", "https://base.drpc.org"],
        "https://basescan.org",
        {
            "USDC": ("0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913", 6),
            "USDT": ("0xfde4C96c8593536E31F229EA8f37b2ADa2699bb2", 6),
        },
    ),
    EvmChain(
        "avax", "Avalanche C-Chain", "AVAX",
        ["https://avalanche-c-chain-rpc.publicnode.com", "https://api.avax.network/ext/bc/C/rpc"],
        "https://snowtrace.io",
        {
            "USDT": ("0x9702230A8Ea53601f5cD2dc00fDBc13d4dF4A8c7", 6),
            "USDC": ("0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E", 6),
        },
    ),
    EvmChain(
        "linea", "Linea", "ETH",
        ["https://linea-rpc.publicnode.com", "https://rpc.linea.build"],
        "https://lineascan.build",
        {
            "USDC": ("0x176211869cA2b568f2A7D4EE941E073a821EE1ff", 6),
            "USDT": ("0xA219439258ca9da29E9Cc4cE5596924745e12B93", 6),
        },
    ),
]

TRC20_TOKENS: dict[str, tuple[str, int]] = {
    "TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t": ("USDT", 6),
    "TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8": ("USDC", 6),
    "TPYmHEhy5n8TCEfYGqW2rPxsghSfzghPDn": ("USDD", 18),
    "TUpMhErZL2fhh4sVNULAbNKLokS4GjC1F4": ("TUSD", 18),
    "TAFjULxiVgT4qWk6UZwjqwZXTSaGaqnVp4": ("BTT", 18),
    "TCFLL5dx5ZJdKnWuesXxi1VPwjLVmWZZy9": ("JST", 18),
    "TSSMHYeV2uE9qYH95DqyoCuNCzEL1NvU3S": ("SUN", 18),
    "TLa2f6VPqDgRE67v1736s7bJ8Ray5wYjU7": ("WIN", 6),
}

SOL_TOKENS: dict[str, str] = {
    "Es9vMFrzaCERmJfrF4H2FYD4KCoNkY11McCe8BenwNYB": "USDT",
    "EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v": "USDC",
    "DezXAZ8z7PnrnRJjz3wXBoRgixCa6xjnB7YaB1pPB263": "BONK",
    "JUPyiwrYJFskUPiHa7hkeR8VUtAeFoSYbKedZNsDvCN": "JUP",
    "EKpQGSJtjMFqKZ9KQanSqYXRcF8fBopzLHYxdM65zcjm": "WIF",
}

SOL_RPCS = ["https://solana-rpc.publicnode.com", "https://api.mainnet-beta.solana.com"]
XRP_RPCS = ["https://xrplcluster.com", "https://s1.ripple.com:51234", "https://s2.ripple.com:51234"]
SUI_RPCS = ["https://fullnode.mainnet.sui.io:443", "https://sui-rpc.publicnode.com"]


def tron_headers() -> dict | None:
    return {"TRON-PRO-API-KEY": config.trongrid_key} if config.trongrid_key else None


def tronscan_headers() -> dict | None:
    key = config.tronscan_key or config.trongrid_key
    return {"TRON-PRO-API-KEY": key} if key else None


def tonapi_headers() -> dict | None:
    return {"Authorization": f"Bearer {config.tonapi_key}"} if config.tonapi_key else None


def toncenter_params() -> dict:
    return {"api_key": config.toncenter_key} if config.toncenter_key else {}


class RpcError(Exception):
    pass


async def rpc(urls: list[str], method: str, params: list | dict, timeout: float = 10):
    last: Exception | None = None
    for url in urls:
        try:
            data = await post_json(url, {"jsonrpc": "2.0", "id": 1, "method": method, "params": params},
                                   timeout=timeout)
        except Exception as exc:
            last = exc
            continue
        if isinstance(data, dict) and "result" in data:
            return data["result"]
        last = RpcError(str(data.get("error") if isinstance(data, dict) else data))
    raise last or RpcError("no rpc")


async def rpc_batch(urls: list[str], calls: list[tuple[str, list]], timeout: float = 12) -> list:
    body = [{"jsonrpc": "2.0", "id": i, "method": m, "params": p} for i, (m, p) in enumerate(calls)]
    for url in urls:
        data = await safe(post_json(url, body, timeout=timeout))
        if isinstance(data, list) and data:
            by_id = {item.get("id"): item for item in data if isinstance(item, dict)}
            if len(by_id) == len(calls):
                return [by_id.get(i, {}).get("result") for i in range(len(calls))]
    out = []
    for m, p in calls:
        out.append(await safe(rpc(urls, m, p, timeout=timeout)))
    return out


def b58encode(data: bytes) -> str:
    n = int.from_bytes(data, "big")
    out = ""
    while n:
        n, r = divmod(n, 58)
        out = B58_ALPHABET[r] + out
    pad = len(data) - len(data.lstrip(b"\0"))
    return "1" * pad + out


def b58decode(text: str) -> bytes:
    n = 0
    for ch in text:
        n = n * 58 + B58_ALPHABET.index(ch)
    raw = n.to_bytes((n.bit_length() + 7) // 8, "big") if n else b""
    pad = len(text) - len(text.lstrip("1"))
    return b"\0" * pad + raw


def b58check_valid(text: str) -> bool:
    try:
        raw = b58decode(text)
    except ValueError:
        return False
    if len(raw) < 5:
        return False
    return hashlib.sha256(hashlib.sha256(raw[:-4]).digest()).digest()[:4] == raw[-4:]


def b58check_encode(payload: bytes) -> str:
    checksum = hashlib.sha256(hashlib.sha256(payload).digest()).digest()[:4]
    return b58encode(payload + checksum)


def tron_hex_to_base58(hex_addr: str) -> str:
    h = (hex_addr or "").lower().removeprefix("0x")
    if len(h) == 40:
        h = "41" + h
    try:
        return b58check_encode(bytes.fromhex(h))
    except ValueError:
        return hex_addr


def hex_int(value) -> int:
    if value in (None, "", "0x"):
        return 0
    if isinstance(value, int):
        return value
    return int(value, 16)


def pad_address(addr: str) -> str:
    return addr.lower().removeprefix("0x").rjust(64, "0")


def balance_of_data(owner: str) -> str:
    return "0x70a08231" + pad_address(owner)


def decode_abi_string(hex_data: str | None) -> str | None:
    if not hex_data or hex_data == "0x":
        return None
    raw = bytes.fromhex(hex_data.removeprefix("0x"))
    try:
        if len(raw) >= 64:
            offset = int.from_bytes(raw[:32], "big")
            if offset == 32 and len(raw) >= 64:
                length = int.from_bytes(raw[32:64], "big")
                return raw[64:64 + length].decode("utf-8", "ignore").strip("\0") or None
        return raw[:32].decode("utf-8", "ignore").strip("\0") or None
    except Exception:
        return None


def topic_to_address(topic: str) -> str:
    return "0x" + topic[-40:]


def wei(value, decimals: int) -> float:
    return hex_int(value) / (10 ** decimals) if isinstance(value, str) else value / (10 ** decimals)


PATTERNS = {
    "evm": re.compile(r"^0x[0-9a-fA-F]{40}$"),
    "hex64x": re.compile(r"^0x[0-9a-fA-F]{64}$"),
    "tron": re.compile(r"^T[1-9A-HJ-NP-Za-km-z]{33}$"),
    "ton": re.compile(r"^(?:[EUk0][Qf][A-Za-z0-9_-]{46}|-?[01]:[0-9a-fA-F]{64})$"),
    "btc": re.compile(r"^(?:bc1[ac-hj-np-z02-9]{25,87}|[13][a-km-zA-HJ-NP-Z1-9]{25,34})$"),
    "ltc": re.compile(r"^(?:ltc1[ac-hj-np-z02-9]{25,87}|[LM][a-km-zA-HJ-NP-Z1-9]{26,33})$"),
    "doge": re.compile(r"^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$"),
    "dash": re.compile(r"^X[1-9A-HJ-NP-Za-km-z]{33}$"),
    "bch": re.compile(r"^(?:bitcoincash:)?[qp][a-z0-9]{41}$"),
    "xrp": re.compile(r"^r[1-9A-HJ-NP-Za-km-z]{24,34}$"),
    "sol": re.compile(r"^[1-9A-HJ-NP-Za-km-z]{32,44}$"),
    "hex64": re.compile(r"^[0-9a-fA-F]{64}$"),
    "b64hash": re.compile(r"^[A-Za-z0-9+/_-]{43}=?$"),
    "solsig": re.compile(r"^[1-9A-HJ-NP-Za-km-z]{86,90}$"),
}

ADDRESS_KINDS = ("evm", "tron", "ton", "btc", "ltc", "doge", "dash", "bch", "xrp", "sol")
HASH_KINDS = ("hex64x", "hex64", "b64hash", "solsig")

_TOKEN = re.compile(r"[A-Za-z0-9:_+/=-]{25,100}")
_EXPLORER = re.compile(
    r"(?:/(?:address|addr|account|tx|transaction|txs|wallet)/|tonviewer\.com/|tonscan\.org/(?:address|tx)/|#/(?:address|transaction)/)"
    r"(?:transaction/)?([A-Za-z0-9:_+/=-]{25,100})"
)


def classify(token: str) -> str | None:
    token = token.strip()
    for kind in ("evm", "hex64x", "tron", "ton", "bch", "btc", "ltc", "doge", "dash", "xrp", "hex64", "solsig",
                 "sol", "b64hash"):
        if PATTERNS[kind].match(token):
            if kind == "tron" and not b58check_valid(token):
                return None
            return kind
    return None


def extract(text: str, strict: bool) -> tuple[str, str] | None:
    text = (text or "").strip()
    if not text or len(text) > 400:
        return None
    m = _EXPLORER.search(text)
    if m:
        token = m.group(1).rstrip("/").split("?")[0]
        kind = classify(token)
        if kind:
            return kind, token
    if strict:
        kind = classify(text)
        return (kind, text) if kind else None
    for token in _TOKEN.findall(text):
        kind = classify(token)
        if kind:
            return kind, token
    return None

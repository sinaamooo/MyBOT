from __future__ import annotations

import asyncio
import time
from typing import Any

import ccxt.async_support as ccxt
import pandas as pd
from tenacity import retry, retry_if_exception_type, stop_after_attempt, wait_exponential

from app.core.logging_config import get_logger
from config import settings

logger = get_logger("exchange")

RETRYABLE = (ccxt.NetworkError, ccxt.ExchangeNotAvailable, ccxt.RequestTimeout, asyncio.TimeoutError)

_retry_policy = retry(
    reraise=True,
    stop=stop_after_attempt(3),
    wait=wait_exponential(multiplier=1, min=1, max=8),
    retry=retry_if_exception_type(RETRYABLE),
)


class ExchangeService:
    """Thin, resilient wrapper around ccxt's Binance USDT-M Futures client.

    Only public market-data endpoints are used for signal generation, so the
    bot works even without API keys. Keys (if provided) are only used for
    endpoints that require them and are never logged or exposed.
    """

    def __init__(self, ohlcv_cache_ttl: float = 15.0, max_concurrency: int = 5) -> None:
        config: dict[str, Any] = {"enableRateLimit": True, "options": {"defaultType": "future"}}
        if settings.binance_api_key and settings.binance_api_secret:
            config["apiKey"] = settings.binance_api_key
            config["secret"] = settings.binance_api_secret

        self._exchange = ccxt.binanceusdm(config)
        self._semaphore = asyncio.Semaphore(max_concurrency)
        self._ohlcv_cache: dict[tuple[str, str], tuple[float, pd.DataFrame]] = {}
        self._ohlcv_cache_ttl = ohlcv_cache_ttl
        self._symbol_map: dict[str, str] = {}
        self._markets_loaded = False
        self._lock = asyncio.Lock()

    async def load_markets(self) -> None:
        if self._markets_loaded:
            return
        async with self._lock:
            if self._markets_loaded:
                return
            await self._exchange.load_markets()
            for unified, market in self._exchange.markets.items():
                if market.get("swap") and market.get("quote") == "USDT" and market.get("linear"):
                    self._symbol_map[market["id"]] = unified
            self._markets_loaded = True
            logger.info("Loaded %d USDT-M futures markets from Binance", len(self._symbol_map))

    def to_unified(self, raw_symbol: str) -> str:
        """'BTCUSDT' -> 'BTC/USDT:USDT' (ccxt unified futures symbol)."""
        if raw_symbol in self._symbol_map:
            return self._symbol_map[raw_symbol]
        base = raw_symbol[:-4] if raw_symbol.endswith("USDT") else raw_symbol
        return f"{base}/USDT:USDT"

    async def close(self) -> None:
        await self._exchange.close()

    @_retry_policy
    async def get_ohlcv(self, symbol: str, timeframe: str, limit: int = 300) -> pd.DataFrame:
        cache_key = (symbol, timeframe)
        now = time.monotonic()
        cached = self._ohlcv_cache.get(cache_key)
        if cached and now - cached[0] < self._ohlcv_cache_ttl:
            return cached[1]

        await self.load_markets()
        unified = self.to_unified(symbol)
        async with self._semaphore:
            raw = await self._exchange.fetch_ohlcv(unified, timeframe=timeframe, limit=limit)

        df = pd.DataFrame(raw, columns=["timestamp", "open", "high", "low", "close", "volume"])
        df["timestamp"] = pd.to_datetime(df["timestamp"], unit="ms", utc=True)
        df.set_index("timestamp", inplace=True)
        self._ohlcv_cache[cache_key] = (now, df)
        return df

    @_retry_policy
    async def get_ticker(self, symbol: str) -> dict[str, Any]:
        await self.load_markets()
        unified = self.to_unified(symbol)
        async with self._semaphore:
            ticker = await self._exchange.fetch_ticker(unified)
        return {
            "symbol": symbol,
            "last": ticker.get("last"),
            "percentage": ticker.get("percentage"),
            "quote_volume": ticker.get("quoteVolume"),
            "high": ticker.get("high"),
            "low": ticker.get("low"),
        }

    async def get_funding_rate(self, symbol: str) -> float | None:
        try:
            await self.load_markets()
            unified = self.to_unified(symbol)
            async with self._semaphore:
                data = await self._exchange.fetch_funding_rate(unified)
            return data.get("fundingRate")
        except Exception as exc:  # pragma: no cover - best effort, non critical field
            logger.warning("funding_rate unavailable for %s: %s", symbol, exc)
            return None

    async def get_open_interest(self, symbol: str) -> float | None:
        try:
            await self.load_markets()
            unified = self.to_unified(symbol)
            async with self._semaphore:
                data = await self._exchange.fetch_open_interest(unified)
            return data.get("openInterestAmount") or data.get("openInterestValue")
        except Exception as exc:  # pragma: no cover - best effort, non critical field
            logger.warning("open_interest unavailable for %s: %s", symbol, exc)
            return None

    async def get_price_precision(self, symbol: str) -> int:
        await self.load_markets()
        unified = self.to_unified(symbol)
        market = self._exchange.markets.get(unified, {})
        precision = market.get("precision", {}).get("price")
        if isinstance(precision, int):
            return precision
        # ccxt sometimes reports precision as a tick size float, derive decimals from it.
        if isinstance(precision, float) and precision > 0:
            return max(0, len(f"{precision:.10f}".rstrip("0").split(".")[-1]))
        return 4

    async def round_price(self, symbol: str, price: float) -> float:
        decimals = await self.get_price_precision(symbol)
        return round(price, decimals)


exchange_service = ExchangeService()

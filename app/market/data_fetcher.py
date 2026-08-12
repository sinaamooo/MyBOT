from __future__ import annotations

import asyncio
from dataclasses import dataclass, field

import pandas as pd

from app.core.logging_config import get_logger
from app.market.exchange import ExchangeService
from config import PIPELINE_TIMEFRAMES

logger = get_logger("data_fetcher")


@dataclass
class MarketSnapshot:
    symbol: str
    price: float
    change_24h: float | None
    quote_volume_24h: float | None
    funding_rate: float | None
    open_interest: float | None
    candles: dict[str, pd.DataFrame] = field(default_factory=dict)  # timeframe -> OHLCV


class MarketDataService:
    """Fetches everything the analysis pipeline needs for one symbol in one go."""

    def __init__(self, exchange: ExchangeService) -> None:
        self._exchange = exchange

    async def get_snapshot(self, symbol: str, timeframes: list[str] | None = None, candles: int = 300) -> MarketSnapshot | None:
        timeframes = timeframes or PIPELINE_TIMEFRAMES
        try:
            ticker_task = self._exchange.get_ticker(symbol)
            funding_task = self._exchange.get_funding_rate(symbol)
            oi_task = self._exchange.get_open_interest(symbol)
            ohlcv_tasks = {tf: self._exchange.get_ohlcv(symbol, tf, limit=candles) for tf in timeframes}

            ticker, funding, open_interest, *ohlcv_results = await asyncio.gather(
                ticker_task, funding_task, oi_task, *ohlcv_tasks.values()
            )
        except Exception as exc:
            logger.error("Failed to build market snapshot for %s: %s", symbol, exc)
            return None

        candle_map = dict(zip(ohlcv_tasks.keys(), ohlcv_results))
        for tf, df in candle_map.items():
            if df is None or len(df) < 50:
                logger.warning("Insufficient candles for %s %s (%s rows)", symbol, tf, 0 if df is None else len(df))
                return None

        return MarketSnapshot(
            symbol=symbol,
            price=ticker["last"],
            change_24h=ticker.get("percentage"),
            quote_volume_24h=ticker.get("quote_volume"),
            funding_rate=funding,
            open_interest=open_interest,
            candles=candle_map,
        )

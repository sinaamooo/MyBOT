from __future__ import annotations

import asyncio
import datetime as dt
from typing import Any

from app.core.enums import SignalStatus
from app.core.logging_config import get_logger
from app.core.models import Signal
from app.market.exchange import ExchangeService
from app.services import log_service, settings_service, signal_service
from app.signals.formatter import format_update_message
from app.signals.publisher import NotificationService

logger = get_logger("trade_monitor")

_TP_SEQUENCE = [("tp1", "TP1"), ("tp2", "TP2"), ("tp3", "TP3")]


class TradeMonitor:
    """Watches every ACTIVE/TP1/TP2/RISK_FREE signal and reacts to price:
    TP hits, Risk-Free (SL -> Entry), Trailing Stop, and Stop-Loss closes.
    """

    def __init__(self, exchange: ExchangeService, notifier: NotificationService) -> None:
        self._exchange = exchange
        self._notifier = notifier
        self._task: asyncio.Task | None = None

    async def start(self) -> None:
        if self._task and not self._task.done():
            return
        self._task = asyncio.create_task(self._run_loop(), name="trade-monitor-loop")

    async def stop(self) -> None:
        if self._task:
            self._task.cancel()
            self._task = None

    async def _run_loop(self) -> None:
        while True:
            try:
                await self.check_once()
            except asyncio.CancelledError:
                raise
            except Exception:
                logger.exception("Monitor cycle failed")
            params = await settings_service.get_all(force_reload=True)
            await asyncio.sleep(params["monitor_interval_seconds"])

    async def check_once(self) -> None:
        params = await settings_service.get_all()
        signals = await signal_service.get_active_signals()
        for signal in signals:
            try:
                price = (await self._exchange.get_ticker(signal.symbol))["last"]
                if price is None:
                    continue
                await self._evaluate(signal, float(price), params)
            except Exception as exc:
                logger.warning("Monitor failed for %s#%s: %s", signal.symbol, signal.id, exc)

    async def _evaluate(self, signal: Signal, price: float, params: dict[str, Any]) -> None:
        is_long = signal.side == "LONG"

        for field_name, label in _TP_SEQUENCE:
            if getattr(signal, f"{field_name}_hit_at") is not None:
                continue
            target = getattr(signal, field_name)
            reached = price >= target if is_long else price <= target
            if not reached:
                break  # targets are monotonic - can't skip ahead

            now = dt.datetime.utcnow()
            if label == "TP3":
                signal = await signal_service.update_signal(signal.id, tp3_hit_at=now)
                signal = await signal_service.close_signal(signal.id, SignalStatus.TP3, price)
                await self._notifier.publish_update(format_update_message("TP3", signal.symbol, signal.side, price))
                await log_service.log("INFO", "signals", f"{signal.symbol} {signal.side} TP3 hit - trade completed")
                return

            status = SignalStatus.TP1 if label == "TP1" else SignalStatus.TP2
            signal = await signal_service.update_signal(signal.id, **{f"{field_name}_hit_at": now}, status=status.value)
            await self._notifier.publish_update(format_update_message(label, signal.symbol, signal.side, price))
            await log_service.log("INFO", "signals", f"{signal.symbol} {signal.side} {label} hit @ {price}")

            if label == "TP1" and (params["risk_free_enabled"] or params["trailing_stop_enabled"]) and signal.risk_free_at is None:
                signal = await signal_service.update_signal(signal.id, current_sl=signal.entry, risk_free_at=now, status=SignalStatus.RISK_FREE.value)
                await self._notifier.publish_update(format_update_message("RISK_FREE", signal.symbol, signal.side))
                await log_service.log("INFO", "signals", f"{signal.symbol} {signal.side} moved to risk-free (SL -> entry)")

            if label == "TP2" and params["trailing_stop_enabled"]:
                signal = await signal_service.update_signal(signal.id, current_sl=signal.tp1)
                await self._notifier.publish_update(format_update_message("TRAILING_STOP", signal.symbol, signal.side, signal.tp1))
                await log_service.log("INFO", "signals", f"{signal.symbol} {signal.side} trailing stop -> TP1")

        # Stop-loss check (current_sl may already have moved via risk-free/trailing above)
        current_sl = signal.current_sl or signal.stop_loss
        sl_hit = price <= current_sl if is_long else price >= current_sl
        if sl_hit:
            await signal_service.close_signal(signal.id, SignalStatus.STOPPED, price)
            await self._notifier.publish_update(format_update_message("STOPPED", signal.symbol, signal.side, price))
            await log_service.log("INFO", "signals", f"{signal.symbol} {signal.side} stop loss hit @ {price}")

    # -- manual admin actions ------------------------------------------------
    async def manual_close(self, signal_id: int) -> Signal | None:
        signal = await signal_service.get_signal(signal_id)
        if signal is None:
            return None
        price = (await self._exchange.get_ticker(signal.symbol))["last"]
        closed = await signal_service.close_signal(signal_id, SignalStatus.COMPLETED, float(price))
        await self._notifier.publish_update(f"⚪️ MANUALLY CLOSED\n\n{signal.symbol} {signal.side}\nClose price: {price}")
        await log_service.log("INFO", "signals", f"{signal.symbol}#{signal_id} manually closed @ {price}")
        return closed

    async def cancel(self, signal_id: int) -> Signal | None:
        signal = await signal_service.update_signal(signal_id, status=SignalStatus.CANCELLED.value, closed_at=dt.datetime.utcnow())
        if signal:
            await self._notifier.publish_update(format_update_message("CANCELLED", signal.symbol, signal.side))
            await log_service.log("INFO", "signals", f"{signal.symbol}#{signal_id} cancelled")
        return signal

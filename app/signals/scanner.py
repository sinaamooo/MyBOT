from __future__ import annotations

import asyncio
import datetime as dt
from typing import Any

from app.core.enums import ScannerState, SignalSide, SignalStatus
from app.core.logging_config import get_logger
from app.services import log_service, settings_service, signal_service, symbol_service
from app.signals.formatter import format_signal_message
from app.signals.generator import SignalCandidate, SignalEngine
from app.signals.publisher import NotificationService, build_signal_keyboard

logger = get_logger("scanner")


class Scanner:
    """Runs the 'scan every ~4 minutes, publish only on quality signals' loop.

    Every tick: Scan Market -> Analyze -> Score -> Filter -> Risk Check ->
    Duplicate Check -> Signal Decision -> Publish (or do nothing).
    """

    def __init__(self, engine: SignalEngine, notifier: NotificationService) -> None:
        self._engine = engine
        self._notifier = notifier
        self.state = ScannerState.STOPPED
        self.last_scan_at: dt.datetime | None = None
        self.next_scan_at: dt.datetime | None = None
        self.last_error: str | None = None
        self._task: asyncio.Task | None = None
        self._pause_event = asyncio.Event()
        self._pause_event.set()

    # -- lifecycle -----------------------------------------------------
    async def start(self) -> None:
        if self._task and not self._task.done():
            return
        self.state = ScannerState.RUNNING
        self._pause_event.set()
        self._task = asyncio.create_task(self._run_loop(), name="scanner-loop")
        await settings_service.set("scanner_running", True)
        await log_service.log("INFO", "scanner", "Scanner started")

    async def stop(self) -> None:
        self.state = ScannerState.STOPPED
        if self._task:
            self._task.cancel()
            self._task = None
        self.next_scan_at = None
        await settings_service.set("scanner_running", False)
        await log_service.log("INFO", "scanner", "Scanner stopped")

    async def pause(self) -> None:
        if self.state != ScannerState.RUNNING:
            return
        self.state = ScannerState.PAUSED
        self._pause_event.clear()
        await log_service.log("INFO", "scanner", "Scanner paused")

    async def resume(self) -> None:
        if self.state != ScannerState.PAUSED:
            return
        self.state = ScannerState.RUNNING
        self._pause_event.set()
        await log_service.log("INFO", "scanner", "Scanner resumed")

    async def restart(self) -> None:
        await self.stop()
        await self.start()

    # -- main loop -------------------------------------------------------
    async def _run_loop(self) -> None:
        while True:
            await self._pause_event.wait()
            try:
                await self.scan_once()
                self.last_error = None
            except asyncio.CancelledError:
                raise
            except Exception as exc:  # never let a bad symbol/API hiccup crash the scanner
                self.last_error = str(exc)
                logger.exception("Scan cycle failed")
                await log_service.log("ERROR", "scanner", f"Scan cycle failed: {exc}")

            params = await settings_service.get_all(force_reload=True)
            interval = params["scan_interval_seconds"]
            self.next_scan_at = dt.datetime.utcnow() + dt.timedelta(seconds=interval)
            await asyncio.sleep(interval)

    # -- one scan pass -----------------------------------------------------
    async def scan_once(self) -> None:
        self.last_scan_at = dt.datetime.utcnow()
        params = await settings_service.get_all(force_reload=True)

        if not params["signals_enabled"]:
            logger.info("Signals disabled by admin - scan skipped")
            return

        symbols = await symbol_service.list_enabled()
        if not symbols:
            return

        for symbol in symbols:
            active_count = await signal_service.count_active_signals()
            if active_count >= params["max_active_signals"]:
                logger.info("Max active signals (%d) reached, stopping scan pass", params["max_active_signals"])
                break

            today_start = dt.datetime.utcnow().replace(hour=0, minute=0, second=0, microsecond=0)
            today_count = await signal_service.count_signals_since(today_start)
            if today_count >= params["max_daily_signals"]:
                logger.info("Max daily signals (%d) reached, stopping scan pass", params["max_daily_signals"])
                break

            if await signal_service.get_open_signal_for_symbol(symbol):
                continue  # duplicate protection: one open trade per symbol at a time

            last_time = await signal_service.get_last_signal_time(symbol)
            if last_time and (dt.datetime.utcnow() - last_time).total_seconds() < params["signal_cooldown_seconds"]:
                continue  # cooldown

            try:
                _scoring, candidate, _snapshot = await self._engine.analyze_symbol(symbol, params)
            except Exception as exc:
                logger.warning("Analysis failed for %s: %s", symbol, exc)
                continue

            if candidate is None:
                continue  # NO_SIGNAL / WATCHLIST / failed a hard gate - do nothing, as designed

            await self.publish_candidate(candidate, params)

    # -- publishing --------------------------------------------------------
    async def publish_candidate(self, candidate: SignalCandidate, params: dict[str, Any], is_manual: bool = False, is_test: bool = False) -> None:
        signal = await signal_service.create_signal(
            symbol=candidate.symbol,
            side=candidate.side.value,
            entry=candidate.entry,
            stop_loss=candidate.stop_loss,
            current_sl=candidate.stop_loss,
            tp1=candidate.tp1,
            tp2=candidate.tp2,
            tp3=candidate.tp3,
            leverage=candidate.leverage,
            score=candidate.score,
            risk_score=candidate.risk_score,
            rr=candidate.rr,
            timeframe=candidate.timeframe,
            trend=candidate.trend,
            regime=candidate.regime,
            reasons=candidate.reasons,
            is_manual=is_manual,
            is_test=is_test,
            status=SignalStatus.PENDING.value,
        )

        text = format_signal_message(
            params["signal_message_template"],
            {
                "symbol": candidate.symbol,
                "side": candidate.side.value,
                "score": round(candidate.score),
                "entry": candidate.entry,
                "stop_loss": candidate.stop_loss,
                "tp1": candidate.tp1,
                "tp2": candidate.tp2,
                "tp3": candidate.tp3,
                "leverage": candidate.leverage,
                "rr": candidate.rr,
                "timeframe": candidate.timeframe,
                "trend": candidate.trend,
                "regime": candidate.regime,
            },
        )
        if is_test:
            text = "🧪 <b>TEST SIGNAL</b> (not a real trade)\n\n" + text
        elif is_manual:
            text = "👤 <b>MANUAL SIGNAL</b> (admin override)\n\n" + text

        keyboard = build_signal_keyboard(candidate.symbol, signal.id)
        message_id = await self._notifier.publish_signal(text, keyboard)

        if message_id is None:
            await log_service.log("ERROR", "scanner", f"Failed to publish signal for {candidate.symbol}, kept PENDING")
            return

        await signal_service.update_signal(signal.id, status=SignalStatus.ACTIVE.value, channel_message_id=message_id)
        await log_service.log(
            "INFO", "signals",
            f"Published {candidate.side.value} {candidate.symbol} score={candidate.score} rr={candidate.rr} lev={candidate.leverage}x",
        )

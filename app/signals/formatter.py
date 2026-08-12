from __future__ import annotations

from typing import Any

SIDE_EMOJI = {"LONG": "🟢", "SHORT": "🔴"}

UPDATE_TEMPLATES: dict[str, str] = {
    "TP1": "✅ {symbol} {side}\n\nTP1 HIT 🎯\n\nProfit Target 1 reached.\nPrice: {price}",
    "TP2": "🔥 TP2 HIT\n\n{symbol} {side}\n\nProfit Target 2 reached.\nPrice: {price}",
    "TP3": "🏆 TP3 HIT\n\n{symbol} {side}\n\nTrade Completed.\nPrice: {price}",
    "STOPPED": "❌ STOP LOSS HIT\n\n{symbol} {side}\n\nTrade Closed.\nPrice: {price}",
    "RISK_FREE": "🛡 RISK FREE\n\n{symbol} {side}\n\nSL → ENTRY\nOriginal SL protected.",
    "TRAILING_STOP": "🔧 TRAILING STOP UPDATED\n\n{symbol} {side}\n\nNew SL: {price}",
    "CANCELLED": "⚪️ SIGNAL CANCELLED\n\n{symbol} {side}",
}


def format_signal_message(template: str, data: dict[str, Any]) -> str:
    payload = dict(data)
    payload.setdefault("side_emoji", SIDE_EMOJI.get(payload.get("side", ""), ""))
    return template.format(**payload)


def format_update_message(update_type: str, symbol: str, side: str, price: float | None = None) -> str:
    template = UPDATE_TEMPLATES.get(update_type, "{symbol} {side}: {update_type}")
    return template.format(symbol=symbol, side=side, price=price, update_type=update_type)

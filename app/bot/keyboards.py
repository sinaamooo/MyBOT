from __future__ import annotations

from aiogram.types import InlineKeyboardButton, InlineKeyboardMarkup
from aiogram.utils.keyboard import InlineKeyboardBuilder

from app.core.enums import ScannerState
from app.core.models import Admin, Log, Signal, Symbol, User


def _kb(rows: list[list[InlineKeyboardButton]]) -> InlineKeyboardMarkup:
    return InlineKeyboardMarkup(inline_keyboard=rows)


def back_row(target: str = "root") -> list[InlineKeyboardButton]:
    return [InlineKeyboardButton(text="⬅️ Back", callback_data=f"menu:{target}")]


def main_menu_keyboard() -> InlineKeyboardMarkup:
    rows = [
        [InlineKeyboardButton(text="🖥 Dashboard", callback_data="menu:dashboard")],
        [InlineKeyboardButton(text="📡 Signal Control", callback_data="menu:signal_control")],
        [
            InlineKeyboardButton(text="🪙 Symbols", callback_data="menu:symbols"),
            InlineKeyboardButton(text="📊 Indicators", callback_data="menu:indicators"),
        ],
        [
            InlineKeyboardButton(text="⚙️ Settings", callback_data="menu:settings"),
            InlineKeyboardButton(text="📈 Statistics", callback_data="menu:statistics"),
        ],
        [
            InlineKeyboardButton(text="📋 Active Signals", callback_data="menu:active"),
            InlineKeyboardButton(text="🗂 Signal History", callback_data="menu:history"),
        ],
        [
            InlineKeyboardButton(text="📝 Message Templates", callback_data="menu:templates"),
            InlineKeyboardButton(text="🧪 Backtest", callback_data="menu:backtest"),
        ],
        [
            InlineKeyboardButton(text="👥 Users", callback_data="menu:users"),
            InlineKeyboardButton(text="🔐 Admins", callback_data="menu:admins"),
        ],
        [InlineKeyboardButton(text="📜 Logs", callback_data="menu:logs")],
        [
            InlineKeyboardButton(text="🔄 Restart Scanner", callback_data="scanner:restart"),
            InlineKeyboardButton(text="⛔ Stop Scanner", callback_data="scanner:stop"),
        ],
    ]
    return _kb(rows)


def dashboard_keyboard(state: ScannerState) -> InlineKeyboardMarkup:
    if state == ScannerState.RUNNING:
        toggle = InlineKeyboardButton(text="⏸ Pause Scanner", callback_data="scanner:pause")
    elif state == ScannerState.PAUSED:
        toggle = InlineKeyboardButton(text="▶️ Resume Scanner", callback_data="scanner:resume")
    else:
        toggle = InlineKeyboardButton(text="▶️ Start Scanner", callback_data="scanner:start")
    rows = [
        [toggle, InlineKeyboardButton(text="🔄 Restart", callback_data="scanner:restart")],
        [InlineKeyboardButton(text="🔃 Refresh", callback_data="menu:dashboard")],
        back_row(),
    ]
    return _kb(rows)


def signal_control_keyboard(signals_enabled: bool) -> InlineKeyboardMarkup:
    toggle = (
        InlineKeyboardButton(text="🔴 Disable Signals", callback_data="signals:disable")
        if signals_enabled
        else InlineKeyboardButton(text="🟢 Enable Signals", callback_data="signals:enable")
    )
    rows = [
        [toggle],
        [
            InlineKeyboardButton(text="🟢 Manual LONG", callback_data="signals:manual:LONG"),
            InlineKeyboardButton(text="🔴 Manual SHORT", callback_data="signals:manual:SHORT"),
        ],
        [InlineKeyboardButton(text="🧪 Send Test Signal", callback_data="signals:test")],
        back_row(),
    ]
    return _kb(rows)


def symbols_keyboard(symbols: list[Symbol]) -> InlineKeyboardMarkup:
    rows = []
    for s in symbols:
        dot = "🟢" if s.enabled else "🔴"
        rows.append(
            [
                InlineKeyboardButton(text=f"{s.symbol} {dot}", callback_data=f"sym:toggle:{s.symbol}"),
                InlineKeyboardButton(text="🗑", callback_data=f"sym:remove:{s.symbol}"),
            ]
        )
    rows.append([InlineKeyboardButton(text="➕ Add Symbol", callback_data="sym:add")])
    rows.append(back_row())
    return _kb(rows)


def indicators_keyboard(params: dict) -> InlineKeyboardMarkup:
    flags = [
        ("EMA", "indicator_ema_enabled"),
        ("RSI", "indicator_rsi_enabled"),
        ("MACD", "indicator_macd_enabled"),
        ("ADX", "indicator_adx_enabled"),
        ("Volume", "indicator_volume_enabled"),
        ("Bollinger", "indicator_bollinger_enabled"),
    ]
    rows = []
    for label, key in flags:
        dot = "🟢" if params.get(key) else "🔴"
        rows.append([InlineKeyboardButton(text=f"{label} {dot}", callback_data=f"ind:toggle:{key}")])
    rows.append(back_row())
    return _kb(rows)


SETTINGS_CATEGORIES: dict[str, list[tuple[str, str]]] = {
    "scoring": [
        ("Min Score (LONG/SHORT)", "min_score"),
        ("Watchlist Min Score", "watchlist_min_score"),
        ("Min ADX", "min_adx"),
        ("Volume Confirm Multiplier", "volume_confirm_multiplier"),
        ("Weight: Trend", "score_weight_trend"),
        ("Weight: EMA", "score_weight_ema"),
        ("Weight: RSI", "score_weight_rsi"),
        ("Weight: MACD", "score_weight_macd"),
        ("Weight: ADX", "score_weight_adx"),
        ("Weight: Volume", "score_weight_volume"),
        ("Weight: Price Action", "score_weight_price_action"),
        ("Weight: HTF Confirm", "score_weight_htf"),
    ],
    "risk": [
        ("ATR SL Multiplier", "atr_sl_multiplier"),
        ("TP1 R Multiple", "tp1_r_multiple"),
        ("TP2 R Multiple", "tp2_r_multiple"),
        ("TP3 R Multiple", "tp3_r_multiple"),
        ("Min Risk/Reward", "min_rr"),
        ("Min Leverage", "min_leverage"),
        ("Max Leverage", "max_leverage"),
    ],
    "scanner": [
        ("Scan Interval (seconds)", "scan_interval_seconds"),
        ("Signal Cooldown (seconds)", "signal_cooldown_seconds"),
        ("Max Active Signals", "max_active_signals"),
        ("Max Daily Signals", "max_daily_signals"),
        ("Max Daily Loss % (0=off)", "max_daily_loss_percent"),
    ],
    "monitoring": [
        ("Monitor Interval (seconds)", "monitor_interval_seconds"),
        ("Risk Free Enabled", "risk_free_enabled"),
        ("Trailing Stop Enabled", "trailing_stop_enabled"),
    ],
}


def settings_menu_keyboard() -> InlineKeyboardMarkup:
    rows = [
        [InlineKeyboardButton(text="🎯 Scoring & Thresholds", callback_data="set:cat:scoring")],
        [InlineKeyboardButton(text="🛡 Risk & Leverage", callback_data="set:cat:risk")],
        [InlineKeyboardButton(text="🔁 Scanner & Cooldown", callback_data="set:cat:scanner")],
        [InlineKeyboardButton(text="👁 Monitoring", callback_data="set:cat:monitoring")],
        back_row(),
    ]
    return _kb(rows)


def settings_category_keyboard(category: str, params: dict) -> InlineKeyboardMarkup:
    rows = []
    for label, key in SETTINGS_CATEGORIES[category]:
        value = params.get(key)
        if isinstance(value, bool):
            display = "🟢 ON" if value else "🔴 OFF"
            rows.append([InlineKeyboardButton(text=f"{label}: {display}", callback_data=f"set:bool:{key}")])
        else:
            rows.append([InlineKeyboardButton(text=f"{label}: {value}", callback_data=f"set:edit:{key}")])
    rows.append([InlineKeyboardButton(text="⬅️ Back", callback_data="menu:settings")])
    return _kb(rows)


def statistics_keyboard() -> InlineKeyboardMarkup:
    return _kb([[InlineKeyboardButton(text="🔃 Refresh", callback_data="menu:statistics")], back_row()])


def active_signals_keyboard(signals: list[Signal]) -> InlineKeyboardMarkup:
    rows = [[InlineKeyboardButton(text=f"{s.symbol} {s.side} ({s.status})", callback_data=f"sig:view:{s.id}")] for s in signals]
    rows.append(back_row())
    return _kb(rows)


def signal_detail_keyboard(signal: Signal) -> InlineKeyboardMarkup:
    rows = [
        [
            InlineKeyboardButton(text="❌ Cancel", callback_data=f"sig:cancel:{signal.id}"),
            InlineKeyboardButton(text="🔒 Close Now", callback_data=f"sig:close:{signal.id}"),
        ],
        [InlineKeyboardButton(text="⬅️ Back", callback_data="menu:active")],
    ]
    return _kb(rows)


def history_keyboard(signals: list[Signal], page: int) -> InlineKeyboardMarkup:
    rows = [[InlineKeyboardButton(text=f"{s.symbol} {s.side} {s.status} ({s.result or '-'})", callback_data=f"sig:view:{s.id}")] for s in signals]
    nav = []
    if page > 0:
        nav.append(InlineKeyboardButton(text="⬅️ Prev", callback_data=f"hist:page:{page-1}"))
    if len(signals) == 10:
        nav.append(InlineKeyboardButton(text="Next ➡️", callback_data=f"hist:page:{page+1}"))
    if nav:
        rows.append(nav)
    rows.append(back_row())
    return _kb(rows)


def templates_keyboard() -> InlineKeyboardMarkup:
    rows = [
        [InlineKeyboardButton(text="✏️ Edit Signal Template", callback_data="tpl:edit")],
        [InlineKeyboardButton(text="♻️ Reset to Default", callback_data="tpl:reset")],
        back_row(),
    ]
    return _kb(rows)


def users_keyboard(page: int, has_more: bool) -> InlineKeyboardMarkup:
    nav = []
    if page > 0:
        nav.append(InlineKeyboardButton(text="⬅️ Prev", callback_data=f"usr:page:{page-1}"))
    if has_more:
        nav.append(InlineKeyboardButton(text="Next ➡️", callback_data=f"usr:page:{page+1}"))
    rows = [nav] if nav else []
    rows.append(back_row())
    return _kb(rows)


def admins_keyboard(admins: list[Admin]) -> InlineKeyboardMarkup:
    rows = [[InlineKeyboardButton(text=f"🔐 {a.label or a.telegram_id}", callback_data=f"adm:remove:{a.telegram_id}")] for a in admins]
    rows.append([InlineKeyboardButton(text="➕ Add Admin", callback_data="adm:add")])
    rows.append(back_row())
    return _kb(rows)


def logs_keyboard() -> InlineKeyboardMarkup:
    return _kb([[InlineKeyboardButton(text="🔃 Refresh", callback_data="menu:logs")], back_row()])


def backtest_keyboard() -> InlineKeyboardMarkup:
    return _kb([[InlineKeyboardButton(text="▶️ Run Backtest", callback_data="bt:start")], back_row()])


def cancel_keyboard(target: str = "root") -> InlineKeyboardMarkup:
    return _kb([back_row(target)])

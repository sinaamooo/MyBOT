from enum import Enum


class SignalSide(str, Enum):
    LONG = "LONG"
    SHORT = "SHORT"


class SignalStatus(str, Enum):
    PENDING = "PENDING"
    ACTIVE = "ACTIVE"
    TP1 = "TP1"
    TP2 = "TP2"
    TP3 = "TP3"
    RISK_FREE = "RISK_FREE"
    STOPPED = "STOPPED"
    COMPLETED = "COMPLETED"
    CANCELLED = "CANCELLED"


OPEN_SIGNAL_STATUSES = {
    SignalStatus.PENDING,
    SignalStatus.ACTIVE,
    SignalStatus.TP1,
    SignalStatus.TP2,
    SignalStatus.RISK_FREE,
}

CLOSED_SIGNAL_STATUSES = {
    SignalStatus.TP3,
    SignalStatus.STOPPED,
    SignalStatus.COMPLETED,
    SignalStatus.CANCELLED,
}


class TrendDirection(str, Enum):
    BULLISH = "BULLISH"
    BEARISH = "BEARISH"
    NEUTRAL = "NEUTRAL"


class RiskLevel(str, Enum):
    LOW = "LOW"
    MEDIUM = "MEDIUM"
    HIGH = "HIGH"


class MarketRegime(str, Enum):
    TRENDING = "TRENDING"
    RANGING = "RANGING"
    HIGH_VOLATILITY = "HIGH_VOLATILITY"
    LOW_VOLATILITY = "LOW_VOLATILITY"


class SignalResult(str, Enum):
    PENDING = "PENDING"
    WIN = "WIN"
    LOSS = "LOSS"
    BREAKEVEN = "BREAKEVEN"


class ScannerState(str, Enum):
    RUNNING = "RUNNING"
    PAUSED = "PAUSED"
    STOPPED = "STOPPED"

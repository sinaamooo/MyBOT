from __future__ import annotations

import datetime as dt

from sqlalchemy import BigInteger, Boolean, DateTime, Float, ForeignKey, Integer, String, Text, UniqueConstraint
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.core.database import Base


def _utcnow() -> dt.datetime:
    return dt.datetime.utcnow()


class User(Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    telegram_id: Mapped[int] = mapped_column(BigInteger, unique=True, index=True)
    username: Mapped[str | None] = mapped_column(String(64), nullable=True)
    first_name: Mapped[str | None] = mapped_column(String(128), nullable=True)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow)
    last_seen_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow, onupdate=_utcnow)


class Admin(Base):
    __tablename__ = "admins"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    telegram_id: Mapped[int] = mapped_column(BigInteger, unique=True, index=True)
    label: Mapped[str | None] = mapped_column(String(128), nullable=True)
    added_by: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow)


class Symbol(Base):
    __tablename__ = "symbols"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    symbol: Mapped[str] = mapped_column(String(32), unique=True, index=True)
    enabled: Mapped[bool] = mapped_column(Boolean, default=True)
    added_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow)


class Setting(Base):
    __tablename__ = "settings"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    key: Mapped[str] = mapped_column(String(128), unique=True, index=True)
    value: Mapped[str] = mapped_column(Text)
    updated_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow, onupdate=_utcnow)


class Log(Base):
    __tablename__ = "logs"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    level: Mapped[str] = mapped_column(String(16))
    source: Mapped[str] = mapped_column(String(64))
    message: Mapped[str] = mapped_column(Text)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow, index=True)


class Signal(Base):
    __tablename__ = "signals"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    symbol: Mapped[str] = mapped_column(String(32), index=True)
    side: Mapped[str] = mapped_column(String(8))

    entry: Mapped[float] = mapped_column(Float)
    stop_loss: Mapped[float] = mapped_column(Float)
    tp1: Mapped[float] = mapped_column(Float)
    tp2: Mapped[float] = mapped_column(Float)
    tp3: Mapped[float] = mapped_column(Float)

    leverage: Mapped[int] = mapped_column(Integer)
    score: Mapped[float] = mapped_column(Float)
    risk_score: Mapped[str] = mapped_column(String(16))
    rr: Mapped[float] = mapped_column(Float)
    timeframe: Mapped[str] = mapped_column(String(32))
    trend: Mapped[str | None] = mapped_column(String(16), nullable=True)
    regime: Mapped[str | None] = mapped_column(String(24), nullable=True)

    reasons: Mapped[str | None] = mapped_column(Text, nullable=True)  # JSON list of strings
    is_manual: Mapped[bool] = mapped_column(Boolean, default=False)
    is_test: Mapped[bool] = mapped_column(Boolean, default=False)

    status: Mapped[str] = mapped_column(String(16), default="PENDING", index=True)

    channel_message_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)

    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow, index=True)
    tp1_hit_at: Mapped[dt.datetime | None] = mapped_column(DateTime, nullable=True)
    tp2_hit_at: Mapped[dt.datetime | None] = mapped_column(DateTime, nullable=True)
    tp3_hit_at: Mapped[dt.datetime | None] = mapped_column(DateTime, nullable=True)
    risk_free_at: Mapped[dt.datetime | None] = mapped_column(DateTime, nullable=True)
    closed_at: Mapped[dt.datetime | None] = mapped_column(DateTime, nullable=True)

    close_price: Mapped[float | None] = mapped_column(Float, nullable=True)
    result: Mapped[str | None] = mapped_column(String(16), nullable=True)  # SignalResult
    profit_percent: Mapped[float | None] = mapped_column(Float, nullable=True)

    current_sl: Mapped[float | None] = mapped_column(Float, nullable=True)  # trailing/risk-free adjusted SL

    updates: Mapped[list["SignalUpdate"]] = relationship(back_populates="signal", cascade="all, delete-orphan")


class SignalUpdate(Base):
    __tablename__ = "signal_updates"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    signal_id: Mapped[int] = mapped_column(ForeignKey("signals.id"), index=True)
    update_type: Mapped[str] = mapped_column(String(32))
    message: Mapped[str] = mapped_column(Text)
    created_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow)

    signal: Mapped["Signal"] = relationship(back_populates="updates")


class SystemStats(Base):
    __tablename__ = "system_stats"
    __table_args__ = (UniqueConstraint("date", name="uq_system_stats_date"),)

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    date: Mapped[str] = mapped_column(String(10), index=True)  # YYYY-MM-DD (UTC)

    total_signals: Mapped[int] = mapped_column(Integer, default=0)
    tp1_count: Mapped[int] = mapped_column(Integer, default=0)
    tp2_count: Mapped[int] = mapped_column(Integer, default=0)
    tp3_count: Mapped[int] = mapped_column(Integer, default=0)
    stopped_count: Mapped[int] = mapped_column(Integer, default=0)
    risk_free_count: Mapped[int] = mapped_column(Integer, default=0)
    cancelled_count: Mapped[int] = mapped_column(Integer, default=0)

    win_rate: Mapped[float] = mapped_column(Float, default=0.0)
    avg_rr: Mapped[float] = mapped_column(Float, default=0.0)
    avg_score: Mapped[float] = mapped_column(Float, default=0.0)

    updated_at: Mapped[dt.datetime] = mapped_column(DateTime, default=_utcnow, onupdate=_utcnow)

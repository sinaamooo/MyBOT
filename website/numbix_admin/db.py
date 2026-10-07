"""SQLite storage. Every query in the admin panel goes through parameter binding."""

from __future__ import annotations

import sqlite3
from pathlib import Path

SCHEMA = """
CREATE TABLE IF NOT EXISTS admin (
    id               INTEGER PRIMARY KEY CHECK (id = 1),
    username         TEXT    NOT NULL,
    password_hash    TEXT    NOT NULL,
    totp_secret      TEXT    NOT NULL,          -- encrypted
    totp_last_step   INTEGER NOT NULL DEFAULT 0,
    session_version  INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS products (
    id           INTEGER PRIMARY KEY,
    name         TEXT    NOT NULL,
    price        INTEGER,
    category     TEXT    NOT NULL DEFAULT '',
    description  TEXT    NOT NULL DEFAULT '',
    icon         TEXT    NOT NULL DEFAULT 'package',
    available    INTEGER NOT NULL DEFAULT 1,
    position     INTEGER NOT NULL DEFAULT 0,
    api_provider TEXT    NOT NULL DEFAULT '',
    api_base_url TEXT    NOT NULL DEFAULT '',
    api_key      TEXT    NOT NULL DEFAULT '',    -- encrypted
    api_service  TEXT    NOT NULL DEFAULT '',
    api_params   TEXT    NOT NULL DEFAULT '{}',
    api_enabled  INTEGER NOT NULL DEFAULT 0,
    updated_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS login_failures (
    ip TEXT NOT NULL,
    at REAL NOT NULL
);
CREATE INDEX IF NOT EXISTS login_failures_at ON login_failures (at);
CREATE TABLE IF NOT EXISTS audit (
    id     INTEGER PRIMARY KEY,
    at     TEXT NOT NULL DEFAULT (datetime('now')),
    ip     TEXT NOT NULL,
    action TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT ''
);
"""


def connect(path: Path) -> sqlite3.Connection:
    conn = sqlite3.connect(path, timeout=10, isolation_level=None)  # autocommit; explicit BEGIN where needed
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA journal_mode = WAL")
    conn.execute("PRAGMA busy_timeout = 10000")
    return conn


def init_db(path: Path) -> None:
    path.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
    conn = connect(path)
    try:
        conn.executescript(SCHEMA)
    finally:
        conn.close()
    path.chmod(0o600)


def audit(conn: sqlite3.Connection, ip: str, action: str, detail: str = "") -> None:
    conn.execute("INSERT INTO audit (ip, action, detail) VALUES (?, ?, ?)", (ip, action, detail[:300]))

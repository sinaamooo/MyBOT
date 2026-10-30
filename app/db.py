"""SQLite storage (aiosqlite) with an in-memory cache for settings and texts."""
from __future__ import annotations

import json
import time
from pathlib import Path
from typing import Any

import aiosqlite

SCHEMA = """
CREATE TABLE IF NOT EXISTS kv (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS texts (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY,
    username TEXT,
    first_name TEXT,
    last_name TEXT,
    is_premium INTEGER DEFAULT 0,
    first_seen INTEGER,
    last_seen INTEGER,
    msg_count INTEGER DEFAULT 0,
    xp INTEGER DEFAULT 0,
    balance REAL DEFAULT 0,
    started INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE TABLE IF NOT EXISTS chats (
    id INTEGER PRIMARY KEY,
    type TEXT,
    title TEXT,
    username TEXT,
    added_at INTEGER,
    active INTEGER DEFAULT 1,
    settings TEXT DEFAULT '{}'
);
CREATE TABLE IF NOT EXISTS scams (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    target_id INTEGER,
    target_username TEXT,
    target_name TEXT,
    reporter_id INTEGER,
    reason TEXT,
    evidence TEXT DEFAULT '[]',
    status TEXT DEFAULT 'draft',
    origin_chat INTEGER,
    created_at INTEGER,
    decided_at INTEGER,
    decided_by INTEGER
);
CREATE INDEX IF NOT EXISTS idx_scams_target ON scams(target_id);
CREATE INDEX IF NOT EXISTS idx_scams_username ON scams(target_username);
CREATE TABLE IF NOT EXISTS strikes (
    chat_id INTEGER,
    user_id INTEGER,
    kind TEXT,
    count INTEGER DEFAULT 0,
    updated_at INTEGER,
    PRIMARY KEY (chat_id, user_id, kind)
);
CREATE TABLE IF NOT EXISTS gift_snapshots (
    owner_id INTEGER,
    value_ton REAL,
    ts INTEGER
);
CREATE TABLE IF NOT EXISTS translations (
    chat_id INTEGER,
    message_id INTEGER,
    requester_id INTEGER,
    text TEXT,
    used INTEGER DEFAULT 0,
    created_at INTEGER,
    PRIMARY KEY (chat_id, message_id)
);
CREATE TABLE IF NOT EXISTS seen_alerts (
    id TEXT PRIMARY KEY,
    ts INTEGER
);
"""


class Database:
    def __init__(self, path: str):
        self.path = path
        self.conn: aiosqlite.Connection | None = None
        self._settings: dict[str, Any] = {}
        self._texts: dict[str, str] = {}

    async def connect(self) -> None:
        Path(self.path).parent.mkdir(parents=True, exist_ok=True)
        self.conn = await aiosqlite.connect(self.path)
        self.conn.row_factory = aiosqlite.Row
        await self.conn.execute("PRAGMA journal_mode=WAL")
        await self.conn.executescript(SCHEMA)
        await self.conn.commit()
        async with self.conn.execute("SELECT key, value FROM kv") as cur:
            for row in await cur.fetchall():
                try:
                    self._settings[row["key"]] = json.loads(row["value"])
                except json.JSONDecodeError:
                    self._settings[row["key"]] = row["value"]
        async with self.conn.execute("SELECT key, value FROM texts") as cur:
            self._texts = {row["key"]: row["value"] for row in await cur.fetchall()}

    async def close(self) -> None:
        if self.conn:
            await self.conn.close()

    # ---------------- settings ----------------
    def get(self, key: str, default: Any = None) -> Any:
        return self._settings.get(key, default)

    async def set(self, key: str, value: Any) -> None:
        self._settings[key] = value
        await self.conn.execute(
            "INSERT INTO kv(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
            (key, json.dumps(value, ensure_ascii=False)),
        )
        await self.conn.commit()

    async def delete(self, key: str) -> None:
        self._settings.pop(key, None)
        await self.conn.execute("DELETE FROM kv WHERE key=?", (key,))
        await self.conn.commit()

    # ---------------- texts ----------------
    def text_override(self, key: str) -> str | None:
        return self._texts.get(key)

    async def set_text(self, key: str, value: str) -> None:
        self._texts[key] = value
        await self.conn.execute(
            "INSERT INTO texts(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value=excluded.value",
            (key, value),
        )
        await self.conn.commit()

    async def reset_text(self, key: str) -> None:
        self._texts.pop(key, None)
        await self.conn.execute("DELETE FROM texts WHERE key=?", (key,))
        await self.conn.commit()

    # ---------------- users ----------------
    async def touch_user(self, user, count_message: bool = False, started: bool = False) -> None:
        now = int(time.time())
        await self.conn.execute(
            """
            INSERT INTO users(id, username, first_name, last_name, is_premium, first_seen, last_seen, msg_count, xp, started)
            VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(id) DO UPDATE SET
                username=excluded.username,
                first_name=excluded.first_name,
                last_name=excluded.last_name,
                is_premium=excluded.is_premium,
                last_seen=excluded.last_seen,
                msg_count=users.msg_count + ?,
                xp=users.xp + ?,
                started=MAX(users.started, excluded.started)
            """,
            (
                user.id,
                (user.username or "").lower() or None,
                user.first_name,
                user.last_name,
                int(bool(getattr(user, "is_premium", False))),
                now,
                now,
                int(count_message),
                int(count_message),
                int(started),
                int(count_message),
                int(count_message),
            ),
        )
        await self.conn.commit()

    async def get_user(self, user_id: int) -> dict | None:
        async with self.conn.execute("SELECT * FROM users WHERE id=?", (user_id,)) as cur:
            row = await cur.fetchone()
        return dict(row) if row else None

    async def find_user_by_username(self, username: str) -> dict | None:
        username = username.lstrip("@").lower()
        async with self.conn.execute(
            "SELECT * FROM users WHERE username=? ORDER BY last_seen DESC LIMIT 1", (username,)
        ) as cur:
            row = await cur.fetchone()
        return dict(row) if row else None

    async def add_balance(self, user_id: int, amount: float) -> float:
        await self.conn.execute(
            "INSERT INTO users(id, balance, first_seen, last_seen) VALUES(?, ?, ?, ?) "
            "ON CONFLICT(id) DO UPDATE SET balance=users.balance + ?",
            (user_id, amount, int(time.time()), int(time.time()), amount),
        )
        await self.conn.commit()
        user = await self.get_user(user_id)
        return float(user["balance"] or 0) if user else amount

    async def user_ids(self, only_started: bool = True) -> list[int]:
        query = "SELECT id FROM users" + (" WHERE started=1" if only_started else "")
        async with self.conn.execute(query) as cur:
            return [row["id"] for row in await cur.fetchall()]

    async def count_users(self) -> int:
        async with self.conn.execute("SELECT COUNT(*) AS c FROM users") as cur:
            return (await cur.fetchone())["c"]

    # ---------------- chats ----------------
    async def upsert_chat(self, chat, active: bool = True) -> None:
        await self.conn.execute(
            """
            INSERT INTO chats(id, type, title, username, added_at, active) VALUES(?, ?, ?, ?, ?, ?)
            ON CONFLICT(id) DO UPDATE SET type=excluded.type, title=excluded.title,
                username=excluded.username, active=excluded.active
            """,
            (chat.id, chat.type, chat.title, chat.username, int(time.time()), int(active)),
        )
        await self.conn.commit()

    async def set_chat_active(self, chat_id: int, active: bool) -> None:
        await self.conn.execute("UPDATE chats SET active=? WHERE id=?", (int(active), chat_id))
        await self.conn.commit()

    async def active_groups(self) -> list[dict]:
        async with self.conn.execute(
            "SELECT * FROM chats WHERE active=1 AND type IN ('group', 'supergroup')"
        ) as cur:
            return [dict(r) for r in await cur.fetchall()]

    async def chat_settings(self, chat_id: int) -> dict:
        async with self.conn.execute("SELECT settings FROM chats WHERE id=?", (chat_id,)) as cur:
            row = await cur.fetchone()
        if not row:
            return {}
        try:
            return json.loads(row["settings"] or "{}")
        except json.JSONDecodeError:
            return {}

    async def set_chat_setting(self, chat_id: int, key: str, value: Any) -> dict:
        settings = await self.chat_settings(chat_id)
        settings[key] = value
        await self.conn.execute(
            "INSERT INTO chats(id, settings, added_at, active) VALUES(?, ?, ?, 1) "
            "ON CONFLICT(id) DO UPDATE SET settings=excluded.settings",
            (chat_id, json.dumps(settings), int(time.time())),
        )
        await self.conn.commit()
        return settings

    async def count_groups(self) -> int:
        async with self.conn.execute(
            "SELECT COUNT(*) AS c FROM chats WHERE active=1 AND type IN ('group','supergroup')"
        ) as cur:
            return (await cur.fetchone())["c"]

    # ---------------- strikes (warnings) ----------------
    async def add_strike(self, chat_id: int, user_id: int, kind: str) -> int:
        now = int(time.time())
        await self.conn.execute(
            """
            INSERT INTO strikes(chat_id, user_id, kind, count, updated_at) VALUES(?, ?, ?, 1, ?)
            ON CONFLICT(chat_id, user_id, kind) DO UPDATE SET
                count=CASE WHEN strikes.updated_at < ? THEN 1 ELSE strikes.count + 1 END,
                updated_at=excluded.updated_at
            """,
            (chat_id, user_id, kind, now, now - 86400),
        )
        await self.conn.commit()
        return await self.get_strikes(chat_id, user_id, kind)

    async def get_strikes(self, chat_id: int, user_id: int, kind: str) -> int:
        async with self.conn.execute(
            "SELECT count FROM strikes WHERE chat_id=? AND user_id=? AND kind=?", (chat_id, user_id, kind)
        ) as cur:
            row = await cur.fetchone()
        return int(row["count"]) if row else 0

    async def reset_strikes(self, chat_id: int, user_id: int, kind: str | None = None) -> None:
        if kind:
            await self.conn.execute(
                "DELETE FROM strikes WHERE chat_id=? AND user_id=? AND kind=?", (chat_id, user_id, kind)
            )
        else:
            await self.conn.execute("DELETE FROM strikes WHERE chat_id=? AND user_id=?", (chat_id, user_id))
        await self.conn.commit()

    # ---------------- scams ----------------
    async def create_scam(self, target_id, target_username, target_name, reporter_id, origin_chat) -> int:
        cur = await self.conn.execute(
            "INSERT INTO scams(target_id, target_username, target_name, reporter_id, origin_chat, created_at, status)"
            " VALUES(?, ?, ?, ?, ?, ?, 'draft')",
            (
                target_id,
                (target_username or "").lstrip("@").lower() or None,
                target_name,
                reporter_id,
                origin_chat,
                int(time.time()),
            ),
        )
        await self.conn.commit()
        return cur.lastrowid

    async def get_scam(self, scam_id: int) -> dict | None:
        async with self.conn.execute("SELECT * FROM scams WHERE id=?", (scam_id,)) as cur:
            row = await cur.fetchone()
        if not row:
            return None
        data = dict(row)
        data["evidence"] = json.loads(data.get("evidence") or "[]")
        return data

    async def update_scam(self, scam_id: int, **fields) -> None:
        if "evidence" in fields:
            fields["evidence"] = json.dumps(fields["evidence"], ensure_ascii=False)
        cols = ", ".join(f"{k}=?" for k in fields)
        await self.conn.execute(f"UPDATE scams SET {cols} WHERE id=?", (*fields.values(), scam_id))
        await self.conn.commit()

    async def find_scammer(self, user_id: int | None = None, username: str | None = None) -> dict | None:
        username = (username or "").lstrip("@").lower() or None
        async with self.conn.execute(
            "SELECT * FROM scams WHERE status='approved' AND ((target_id IS NOT NULL AND target_id=?) "
            "OR (target_username IS NOT NULL AND target_username=?)) ORDER BY decided_at DESC LIMIT 1",
            (user_id or -1, username or "\x00"),
        ) as cur:
            row = await cur.fetchone()
        return dict(row) if row else None

    async def list_scams(self, status: str, limit: int = 20) -> list[dict]:
        async with self.conn.execute(
            "SELECT * FROM scams WHERE status=? ORDER BY id DESC LIMIT ?", (status, limit)
        ) as cur:
            return [dict(r) for r in await cur.fetchall()]

    # ---------------- gift snapshots ----------------
    async def last_gift_snapshot(self, owner_id: int) -> dict | None:
        async with self.conn.execute(
            "SELECT * FROM gift_snapshots WHERE owner_id=? ORDER BY ts DESC LIMIT 1", (owner_id,)
        ) as cur:
            row = await cur.fetchone()
        return dict(row) if row else None

    async def add_gift_snapshot(self, owner_id: int, value_ton: float) -> None:
        await self.conn.execute(
            "INSERT INTO gift_snapshots(owner_id, value_ton, ts) VALUES(?, ?, ?)",
            (owner_id, value_ton, int(time.time())),
        )
        await self.conn.commit()

    # ---------------- translations ----------------
    async def save_translation(self, chat_id: int, message_id: int, requester_id: int, text: str) -> None:
        await self.conn.execute(
            "INSERT OR REPLACE INTO translations(chat_id, message_id, requester_id, text, used, created_at)"
            " VALUES(?, ?, ?, ?, 0, ?)",
            (chat_id, message_id, requester_id, text, int(time.time())),
        )
        await self.conn.commit()

    async def claim_translation(self, chat_id: int, message_id: int) -> dict | None:
        """Atomically mark a pending translation as used. Returns the row only for the first caller."""
        cur = await self.conn.execute(
            "UPDATE translations SET used=1 WHERE chat_id=? AND message_id=? AND used=0", (chat_id, message_id)
        )
        await self.conn.commit()
        if cur.rowcount != 1:
            return None
        async with self.conn.execute(
            "SELECT * FROM translations WHERE chat_id=? AND message_id=?", (chat_id, message_id)
        ) as c:
            row = await c.fetchone()
        return dict(row) if row else None

    async def release_translation(self, chat_id: int, message_id: int) -> None:
        await self.conn.execute(
            "UPDATE translations SET used=0 WHERE chat_id=? AND message_id=?", (chat_id, message_id)
        )
        await self.conn.commit()

    async def get_translation(self, chat_id: int, message_id: int) -> dict | None:
        async with self.conn.execute(
            "SELECT * FROM translations WHERE chat_id=? AND message_id=?", (chat_id, message_id)
        ) as c:
            row = await c.fetchone()
        return dict(row) if row else None

    # ---------------- whale alert dedupe ----------------
    async def mark_alert(self, alert_id: str) -> bool:
        """Returns True if the alert is new."""
        cur = await self.conn.execute(
            "INSERT OR IGNORE INTO seen_alerts(id, ts) VALUES(?, ?)", (alert_id, int(time.time()))
        )
        await self.conn.commit()
        return cur.rowcount == 1

    async def cleanup(self) -> None:
        week = int(time.time()) - 7 * 86400
        await self.conn.execute("DELETE FROM seen_alerts WHERE ts < ?", (week,))
        await self.conn.execute("DELETE FROM translations WHERE created_at < ?", (week,))
        await self.conn.commit()


db: Database | None = None


def get_db() -> Database:
    assert db is not None, "Database is not initialised"
    return db


async def init_db(path: str) -> Database:
    global db
    db = Database(path)
    await db.connect()
    return db

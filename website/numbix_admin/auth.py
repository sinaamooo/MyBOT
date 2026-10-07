"""Authentication: password + TOTP, brute-force limits, sessions and CSRF."""

from __future__ import annotations

import base64
import hashlib
import hmac
import secrets
import struct
import time
from functools import wraps
from urllib.parse import quote, urlsplit

from flask import abort, current_app, g, redirect, request, session, url_for
from werkzeug.security import check_password_hash, generate_password_hash

MIN_PASSWORD = 12
IP_LIMIT, GLOBAL_LIMIT, WINDOW = 5, 50, 15 * 60  # failures allowed per IP / overall, per 15 minutes
IDLE_TIMEOUT, MAX_SESSION = 30 * 60, 12 * 3600
TOTP_STEP = 30

# Verified against when the username is wrong, so both paths cost the same time.
_DUMMY_HASH = generate_password_hash(secrets.token_hex(16), method="scrypt")


# ── Passwords ──────────────────────────────────────────────────────────────
def hash_password(password: str) -> str:
    if len(password) < MIN_PASSWORD:
        raise ValueError(f"Password must be at least {MIN_PASSWORD} characters")
    return generate_password_hash(password, method="scrypt")


def password_ok(stored_hash: str | None, password: str) -> bool:
    return check_password_hash(stored_hash or _DUMMY_HASH, password) and stored_hash is not None


# ── TOTP (RFC 6238, compatible with Google Authenticator) ──────────────────
def new_totp_secret() -> str:
    return base64.b32encode(secrets.token_bytes(20)).decode()


def totp_uri(secret: str, username: str) -> str:
    return f"otpauth://totp/Numbix:{quote(username)}?secret={secret}&issuer=Numbix&digits=6&period={TOTP_STEP}"


def _totp(secret: str, step: int) -> str:
    digest = hmac.new(base64.b32decode(secret), struct.pack(">Q", step), hashlib.sha1).digest()
    offset = digest[-1] & 0x0F
    code = (struct.unpack(">I", digest[offset : offset + 4])[0] & 0x7FFFFFFF) % 1_000_000
    return f"{code:06d}"


def totp_step(secret: str, code: str, last_step: int, now: float | None = None) -> int | None:
    """Return the matching time step (±1 for clock drift), refusing codes already used."""
    if not (len(code) == 6 and code.isdigit()):
        return None
    current = int((time.time() if now is None else now) // TOTP_STEP)
    for step in (current - 1, current, current + 1):
        if step > last_step and hmac.compare_digest(_totp(secret, step), code):
            return step
    return None


# ── Brute-force limits ─────────────────────────────────────────────────────
def login_blocked(db, ip: str) -> bool:
    since = time.time() - WINDOW
    db.execute("DELETE FROM login_failures WHERE at < ?", (since,))
    per_ip = db.execute("SELECT COUNT(*) FROM login_failures WHERE ip = ?", (ip,)).fetchone()[0]
    overall = db.execute("SELECT COUNT(*) FROM login_failures").fetchone()[0]
    return per_ip >= IP_LIMIT or overall >= GLOBAL_LIMIT


def record_failure(db, ip: str) -> None:
    db.execute("INSERT INTO login_failures (ip, at) VALUES (?, ?)", (ip, time.time()))


def clear_failures(db, ip: str) -> None:
    db.execute("DELETE FROM login_failures WHERE ip = ?", (ip,))


# ── Sessions ───────────────────────────────────────────────────────────────
def start_session(session_version: int) -> None:
    session.clear()  # never reuse a pre-login session (fixation)
    now = time.time()
    session.update(uid=1, ver=session_version, iat=now, seen=now, csrf=secrets.token_urlsafe(32))
    session.permanent = True


def load_session(db) -> None:
    """Validate the session on every request; expired or revoked sessions are dropped."""
    g.admin = None
    if session.get("uid") != 1:
        return
    now = time.time()
    row = db.execute("SELECT username, session_version FROM admin WHERE id = 1").fetchone()
    expired = now - session.get("seen", 0) > IDLE_TIMEOUT or now - session.get("iat", 0) > MAX_SESSION
    if row is None or expired or session.get("ver") != row["session_version"]:
        session.clear()
        return
    if now - session["seen"] > 60:
        session["seen"] = now
    g.admin = row["username"]


def login_required(view):
    @wraps(view)
    def wrapper(*args, **kwargs):
        if g.get("admin") is None:
            return redirect(url_for("admin.login"))
        return view(*args, **kwargs)

    return wrapper


# ── CSRF ───────────────────────────────────────────────────────────────────
def csrf_token() -> str:
    if "csrf" not in session:
        session["csrf"] = secrets.token_urlsafe(32)
    return session["csrf"]


def check_csrf() -> None:
    """Every POST needs the session token and, when the browser sends one, a same-origin Origin."""
    sent = request.form.get("csrf", "")
    expected = session.get("csrf", "")
    if not expected or not hmac.compare_digest(sent, expected):
        abort(400)
    origin = request.headers.get("Origin")
    if origin and origin != "null":
        host = urlsplit(request.host_url)
        if urlsplit(origin)[:2] != host[:2]:
            abort(400)
    elif origin == "null":
        abort(400)
    if current_app.config["SESSION_COOKIE_SECURE"] and request.scheme != "https":
        abort(400)


def client_ip() -> str:
    return request.remote_addr or "?"

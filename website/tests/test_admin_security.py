"""Attacks against the admin panel: each test is an attempt that must fail safely."""

from __future__ import annotations

import time

from conftest import PASSWORD, csrf_from, current_code, login, product_data

from numbix_admin import auth, create_app, db
from numbix_admin.integration import product_api


# ── Access control ─────────────────────────────────────────────────────────
def test_every_page_requires_login(client):
    for path in ("/admin/", "/admin/products/new", "/admin/products/1", "/admin/audit"):
        response = client.get(path)
        assert response.status_code == 302 and response.headers["Location"].endswith("/admin/login")


def test_state_changing_routes_reject_get(logged_in):
    for path in ("/admin/publish", "/admin/products/1/delete", "/admin/logout", "/admin/sessions/revoke"):
        assert logged_in.get(path).status_code == 405


def test_forged_session_cookie_is_ignored(app, settings):
    forged = create_app(settings.__class__(**{**settings.__dict__, "secret_key": b"attacker" * 6})).test_client()
    with forged.session_transaction() as sess:  # a cookie signed with the wrong key
        sess.update(uid=1, ver=1, iat=time.time(), seen=time.time(), csrf="x")
    cookie = forged.get_cookie("nxadmin")
    victim = app.test_client()
    victim.set_cookie("nxadmin", cookie.value)
    assert victim.get("/admin/").status_code == 302


# ── CSRF ───────────────────────────────────────────────────────────────────
def test_post_without_csrf_token_is_rejected(client, logged_in):
    assert client.post("/admin/login", data={"username": "admin"}).status_code == 400
    assert logged_in.post("/admin/products/new", data=product_data()).status_code == 400


def test_post_with_wrong_csrf_token_is_rejected(logged_in):
    assert logged_in.post("/admin/products/new", data={**product_data(), "csrf": "guess"}).status_code == 400


def test_cross_origin_post_is_rejected(logged_in):
    token = csrf_from(logged_in.get("/admin/products/new"))
    for origin in ("https://evil.example", "null"):
        response = logged_in.post(
            "/admin/products/new", data={**product_data(), "csrf": token}, headers={"Origin": origin}
        )
        assert response.status_code == 400


# ── Login attacks ──────────────────────────────────────────────────────────
def test_wrong_password_wrong_user_and_wrong_code_fail(client):
    assert login(client, password="nope nope nope").status_code == 401
    assert login(client, username="root").status_code == 401
    assert login(client, code="000000").status_code == 401


def test_sql_injection_in_login_does_not_bypass(client, settings):
    for payload in ("' OR '1'='1", "admin'--", '" OR 1=1 --', "admin'; DROP TABLE admin; --"):
        assert login(client, username=payload, password=payload).status_code == 401
    conn = db.connect(settings.db_path)
    assert conn.execute("SELECT COUNT(*) FROM admin").fetchone()[0] == 1
    conn.close()


def test_totp_code_cannot_be_replayed(app):
    code = current_code()
    first, second = app.test_client(), app.test_client()
    assert login(first, code=code).status_code == 302
    assert login(second, code=code).status_code == 401


def test_brute_force_is_locked_out(client):
    for _ in range(auth.IP_LIMIT):
        assert login(client, password="wrong password!").status_code == 401
    # even the right credentials are refused while locked
    assert login(client).status_code == 429


def test_login_ignores_open_redirect_targets(client):
    token = csrf_from(client.get("/admin/login?next=//evil.example"))
    response = client.post(
        "/admin/login?next=https://evil.example",
        data={"csrf": token, "username": "admin", "password": PASSWORD, "code": current_code()},
    )
    assert response.headers["Location"].endswith("/admin/")


# ── Sessions ───────────────────────────────────────────────────────────────
def test_session_is_regenerated_at_login(client):
    before = csrf_from(client.get("/admin/login"))
    login(client)
    after = csrf_from(client.get("/admin/"))
    assert before != after


def test_idle_and_revoked_sessions_end(logged_in):
    with logged_in.session_transaction() as sess:
        sess["seen"] = time.time() - auth.IDLE_TIMEOUT - 1
    assert logged_in.get("/admin/").status_code == 302


def test_revoke_logs_out_every_device(app):
    phone, laptop = app.test_client(), app.test_client()
    login(phone)
    time.sleep(0)  # same TOTP window: log the laptop in with the next code
    login(laptop, code=current_code(1))
    token = csrf_from(phone.get("/admin/"))
    phone.post("/admin/sessions/revoke", data={"csrf": token})
    assert laptop.get("/admin/").status_code == 302


def test_secure_cookie_flags(settings):
    secure = create_app(settings.__class__(**{**settings.__dict__, "cookie_secure": True}))
    client = secure.test_client()
    response = client.get("/admin/login", base_url="https://admin.test")
    cookie = response.headers["Set-Cookie"]
    for flag in ("__Host-nxadmin=", "Secure", "HttpOnly", "SameSite=Strict", "Path=/"):
        assert flag in cookie
    # a plain-http POST is refused when cookies are Secure
    token = csrf_from(response)
    assert client.post("/admin/login", data={"csrf": token}, base_url="http://admin.test").status_code == 400


# ── Headers and limits ─────────────────────────────────────────────────────
def test_security_headers_everywhere(client, logged_in):
    for response in (client.get("/admin/login"), logged_in.get("/admin/"), client.get("/admin/nope")):
        headers = response.headers
        assert "default-src 'none'" in headers["Content-Security-Policy"]
        assert "frame-ancestors 'none'" in headers["Content-Security-Policy"]
        assert headers["X-Frame-Options"] == "DENY"
        assert headers["X-Content-Type-Options"] == "nosniff"
        assert headers["Referrer-Policy"] == "no-referrer"
        assert headers["Cache-Control"] == "no-store"


def test_oversized_request_is_refused(logged_in):
    token = csrf_from(logged_in.get("/admin/products/new"))
    response = logged_in.post("/admin/products/new", data={"csrf": token, "description": "x" * 200_000})
    assert response.status_code == 413


def test_errors_do_not_leak_internals(client):
    body = client.get("/admin/products/../../etc/passwd").data.decode()
    assert "Traceback" not in body and "root:" not in body


# ── Products and API settings ──────────────────────────────────────────────
def _create(client, **overrides):
    token = csrf_from(client.get("/admin/products/new"))
    return client.post("/admin/products/new", data={**product_data(**overrides), "csrf": token})


def test_invalid_product_input_is_rejected(logged_in):
    bad = [
        {"api_base_url": "http://api.example.com"},
        {"api_base_url": "javascript:alert(1)"},
        {"api_base_url": "https://user:pass@api.example.com"},
        {"api_params": "{not json"},
        {"api_params": '{"nested": {"a": 1}}'},
        {"api_params": "[1, 2]"},
        {"name": ""},
        {"name": "x" * 81},
        {"price": "45abc"},
        {"price": "1" * 20},
        {"api_enabled": "1", "api_base_url": ""},
    ]
    for overrides in bad:
        assert _create(logged_in, **overrides).status_code == 422, overrides


def test_html_in_products_is_escaped_everywhere(logged_in, settings):
    payload = '"><script>alert(1)</script><img src=x onerror=alert(1)>'
    assert _create(logged_in, name=payload[:80], category=payload[:40], description=payload).status_code == 302
    page = logged_in.get("/admin/").data.decode()
    assert "<script>alert(1)" not in page and "<img src=x" not in page

    token = csrf_from(logged_in.get("/admin/"))
    assert logged_in.post("/admin/publish", data={"csrf": token}).status_code == 302
    for html in settings.webroot.rglob("*.html"):
        text = html.read_text(encoding="utf-8")
        assert "<script>alert(1)" not in text and "<img src=x" not in text, html


def test_api_key_is_encrypted_masked_and_never_echoed(logged_in, settings):
    secret = "sk_live_THIS_IS_A_SECRET_1234"
    assert _create(logged_in, api_key=secret, api_enabled="1").status_code == 302
    conn = db.connect(settings.db_path)
    stored = conn.execute("SELECT api_key FROM products").fetchone()[0]
    conn.close()
    assert stored and secret not in stored  # ciphertext at rest

    page = logged_in.get("/admin/products/1").data.decode()
    assert secret not in page and "1234" in page  # only the last four characters

    token = csrf_from(logged_in.get("/admin/products/1"))
    logged_in.post("/admin/products/1", data={**product_data(api_enabled="1"), "csrf": token})
    assert product_api(1, settings)["api_key"] == secret  # empty field keeps the key

    token = csrf_from(logged_in.get("/admin/products/1"))
    logged_in.post("/admin/products/1", data={**product_data(), "api_key_clear": "1", "csrf": token})
    assert product_api(1, settings) is None  # cleared and disabled


def test_unknown_product_is_404(logged_in):
    assert logged_in.get("/admin/products/999").status_code == 404
    token = csrf_from(logged_in.get("/admin/"))
    assert logged_in.post("/admin/products/999/delete", data={"csrf": token, "confirm": "1"}).status_code == 404


def test_delete_needs_explicit_confirmation(logged_in):
    _create(logged_in)
    token = csrf_from(logged_in.get("/admin/products/1"))
    assert logged_in.post("/admin/products/1/delete", data={"csrf": token}).status_code == 400
    assert logged_in.post("/admin/products/1/delete", data={"csrf": token, "confirm": "1"}).status_code == 302
    assert logged_in.get("/admin/products/1").status_code == 404


# ── Publishing ─────────────────────────────────────────────────────────────
def test_publish_refuses_incomplete_site(logged_in, settings):
    (settings.site_root / "site.toml").write_text(
        (settings.site_root / "site.toml").read_text(encoding="utf-8").replace('"علی نمونه"', '""'),
        encoding="utf-8",
    )
    _create(logged_in)
    token = csrf_from(logged_in.get("/admin/"))
    logged_in.post("/admin/publish", data={"csrf": token})
    assert not (settings.webroot / "index.html").exists()


def test_publish_updates_site_and_keeps_foreign_files(logged_in, settings):
    settings.webroot.mkdir(parents=True)
    (settings.webroot / ".user.ini").write_text("open_basedir=/www")
    (settings.webroot / "assets").mkdir()
    (settings.webroot / "assets" / "app.0000000000.css").write_text("old")
    _create(logged_in, name="محصول آزمایشی", price="12345")
    token = csrf_from(logged_in.get("/admin/"))
    assert logged_in.post("/admin/publish", data={"csrf": token}).status_code == 302

    services = (settings.webroot / "services" / "index.html").read_text(encoding="utf-8")
    assert "محصول آزمایشی" in services and "۱۲٬۳۴۵" in services
    assert (settings.webroot / ".user.ini").read_text() == "open_basedir=/www"
    assert not (settings.webroot / "assets" / "app.0000000000.css").exists()


def test_every_action_is_audited(logged_in, settings):
    _create(logged_in)
    conn = db.connect(settings.db_path)
    actions = [r[0] for r in conn.execute("SELECT action FROM audit ORDER BY id")]
    conn.close()
    assert actions[:2] == ["login", "product_created"]

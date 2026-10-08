from __future__ import annotations

import re
import shutil
import sys
import time
from pathlib import Path

import pytest

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from numbix_admin import auth, create_app, db  # noqa: E402
from numbix_admin.crypto import Vault, new_key  # noqa: E402
from numbix_admin.settings import Settings  # noqa: E402

PASSWORD = "correct horse battery staple"
TOTP_SECRET = auth.new_totp_secret()

FILLED = {
    "domain": "numbix.ir",
    "bot_username": "NumbixBot",
    "owner_name": "علی نمونه",
    "mobile": "09121234567",
    "email": "info@numbix.ir",
    "address": "تهران، خیابان نمونه",
    "postal_code": "1234567890",
    "services_line": "فروش خدمات مجازی",
}


def write_config(root: Path, values: dict[str, str], extra: str = "") -> None:
    text = (ROOT / "site.toml").read_text(encoding="utf-8")
    for key, value in values.items():
        line = f"{key} = {toml_str(value)}"
        text = re.sub(rf'^{key} = "[^"\n]*"', lambda _m, line=line: line, text, count=1, flags=re.M)
    (root / "site.toml").write_text(text + extra, encoding="utf-8")


def toml_str(value: str) -> str:
    return '"' + value.replace("\\", "\\\\").replace('"', '\\"') + '"'


@pytest.fixture
def site_root(tmp_path: Path) -> Path:
    root = tmp_path / "site"
    root.mkdir()
    for name in ("templates", "assets", "numbix_admin"):
        shutil.copytree(ROOT / name, root / name, ignore=shutil.ignore_patterns("__pycache__"))
    write_config(root, FILLED)
    return root


@pytest.fixture
def settings(tmp_path: Path, site_root: Path) -> Settings:
    return Settings(
        site_root=site_root,
        data_dir=tmp_path / "data",
        secret_key=b"s" * 48,
        fernet_key=new_key().encode(),
        webroot=tmp_path / "www",
        url_prefix="/admin",
        cookie_secure=False,
    )


@pytest.fixture
def app(settings: Settings):
    application = create_app(settings)
    application.config["TESTING"] = True
    conn = db.connect(settings.db_path)
    conn.execute(
        "INSERT INTO admin (id, username, password_hash, totp_secret) VALUES (1, ?, ?, ?)",
        ("admin", auth.hash_password(PASSWORD), Vault(settings.fernet_key).seal(TOTP_SECRET)),
    )
    conn.close()
    return application


@pytest.fixture
def client(app):
    return app.test_client()


def csrf_from(response) -> str:
    match = re.search(rb'name="csrf" value="([^"]+)"', response.data)
    assert match, "page has no CSRF token"
    return match.group(1).decode()


def current_code(offset: int = 0) -> str:
    return auth._totp(TOTP_SECRET, int(time.time() // auth.TOTP_STEP) + offset)


def login(client, password: str = PASSWORD, code: str | None = None, username: str = "admin"):
    token = csrf_from(client.get("/admin/login"))
    return client.post(
        "/admin/login",
        data={"csrf": token, "username": username, "password": password, "code": code or current_code()},
    )


@pytest.fixture
def logged_in(client):
    response = login(client)
    assert response.status_code == 302 and response.headers["Location"].endswith("/admin/")
    return client


def product_data(**overrides) -> dict:
    data = {
        "name": "شماره‌ی مجازی تلگرام",
        "price": "45000",
        "category": "شماره‌ی مجازی",
        "description": "",
        "icon": "smartphone",
        "position": "0",
        "available": "1",
        "api_provider": "demo",
        "api_base_url": "https://api.example.com/v1",
        "api_service": "tg",
        "api_key": "",
        "api_params": '{"country": "ir"}',
    }
    data.update(overrides)
    return data

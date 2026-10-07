#!/usr/bin/env python3
"""Admin panel management.

python3 manage.py init            # secrets file, database, import products from site.toml
python3 manage.py create-admin    # admin username, password and two-factor (TOTP) secret
python3 manage.py publish         # rebuild the public site from the database into the webroot
python3 manage.py run             # local development server on http://127.0.0.1:8001/admin
"""

from __future__ import annotations

import argparse
import getpass
import os
import re
import secrets
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
DEFAULT_ENV = ROOT / "instance" / "admin.env"


def load_env(path: Path) -> None:
    if not path.exists():
        sys.exit(f"{path} پیدا نشد؛ اول اجرا کنید: python3 manage.py init")
    for line in path.read_text(encoding="utf-8").splitlines():
        key, sep, value = line.partition("=")
        if sep and not line.lstrip().startswith("#"):
            os.environ.setdefault(key.strip(), value.strip())


def cmd_init(args) -> None:
    from numbix_admin import db
    from numbix_admin.crypto import new_key

    env = args.env
    if env.exists():
        print(f"{env} از قبل هست؛ کلیدها دست نخوردند.")
    else:
        data_dir = Path(args.data_dir or env.parent).resolve()
        env.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        env.write_text(
            f"NX_SECRET_KEY={secrets.token_urlsafe(48)}\nNX_FERNET_KEY={new_key()}\nNX_DATA_DIR={data_dir}\n",
            encoding="utf-8",
        )
        env.chmod(0o600)
        print(f"کلیدها ساخته شدند: {env}  (از این فایل نسخه‌ی پشتیبان امن بگیرید)")
    load_env(env)

    from numbix_admin.settings import Settings

    settings = Settings.from_env()
    db.init_db(settings.db_path)
    conn = db.connect(settings.db_path)
    try:
        if conn.execute("SELECT COUNT(*) FROM products").fetchone()[0] == 0:
            imported = _import_products(conn, settings.config_path)
            print(f"{imported} محصول از site.toml وارد پایگاه‌داده شد.")
    finally:
        conn.close()
    print(f"پایگاه‌داده آماده است: {settings.db_path}")


def _import_products(conn, config: Path) -> int:
    from numbix_site.config import to_en, tomllib

    rows = tomllib.loads(config.read_text(encoding="utf-8")).get("services", [])
    count = 0
    for position, row in enumerate(rows):
        name = str(row.get("name", "")).strip()
        price = re.sub(r"\D", "", to_en(row.get("price", "")))
        if not name:
            continue
        conn.execute(
            "INSERT INTO products (name, price, category, description, icon, available, position)"
            " VALUES (?, ?, ?, ?, ?, ?, ?)",
            (
                name[:80],
                int(price) if price else None,
                str(row.get("category", ""))[:40],
                str(row.get("description", ""))[:300],
                str(row.get("icon", "package")),
                1 if row.get("available", True) else 0,
                position,
            ),
        )
        count += 1
    return count


def cmd_create_admin(args) -> None:
    load_env(args.env)
    from numbix_admin import auth, db
    from numbix_admin.crypto import Vault
    from numbix_admin.settings import Settings

    settings = Settings.from_env()
    username = input("نام کاربری مدیر [admin]: ").strip() or "admin"
    while True:
        password = getpass.getpass(f"رمز عبور (حداقل {auth.MIN_PASSWORD} کاراکتر): ")
        if password != getpass.getpass("تکرار رمز عبور: "):
            print("دو رمز یکی نیستند.")
            continue
        try:
            password_hash = auth.hash_password(password)
        except ValueError as error:
            print(error)
            continue
        break
    secret = auth.new_totp_secret()
    db.init_db(settings.db_path)
    conn = db.connect(settings.db_path)
    try:
        version = (conn.execute("SELECT session_version FROM admin WHERE id = 1").fetchone() or [0])[0] + 1
        conn.execute(
            "INSERT OR REPLACE INTO admin (id, username, password_hash, totp_secret, totp_last_step, session_version)"
            " VALUES (1, ?, ?, ?, 0, ?)",
            (username, password_hash, Vault(settings.fernet_key).seal(secret), version),
        )
        db.audit(conn, "cli", "admin_created", username)
    finally:
        conn.close()
    print("\nحساب مدیر ساخته شد. این کلید را در اپ Google Authenticator یا Aegis وارد کنید:")
    print(f"  کلید: {secret}")
    print(f"  یا این لینک: {auth.totp_uri(secret, username)}")
    print("این کلید فقط همین یک بار نمایش داده می‌شود.")


def cmd_publish(args) -> None:
    load_env(args.env)
    from numbix_admin import db
    from numbix_admin.publish import PublishError, publish
    from numbix_admin.settings import Settings

    settings = Settings.from_env()
    conn = db.connect(settings.db_path)
    try:
        count = publish(settings, conn)
        db.audit(conn, "cli", "published", f"{count} files")
    except PublishError as error:
        sys.exit(f"انتشار انجام نشد: {error}")
    finally:
        conn.close()
    print(f"سایت منتشر شد: {settings.webroot} ({count} فایل)")


def cmd_run(args) -> None:
    load_env(args.env)
    os.environ["NX_COOKIE_SECURE"] = "0"  # plain http on localhost only
    from numbix_admin import create_app

    app = create_app()
    print(f"پنل مدیر: http://127.0.0.1:{args.port}{app.extensions['nx_settings'].url_prefix}/login")
    app.run(host="127.0.0.1", port=args.port, debug=False)


def main() -> None:
    parser = argparse.ArgumentParser(description="Numbix admin panel")
    parser.add_argument("--env", type=Path, default=DEFAULT_ENV, help="secrets file (default: instance/admin.env)")
    sub = parser.add_subparsers(dest="command", required=True)
    init = sub.add_parser("init", help="create secrets and database")
    init.add_argument("--data-dir", help="where the database lives (default: next to the env file)")
    sub.add_parser("create-admin", help="create or reset the admin account")
    sub.add_parser("publish", help="rebuild the public site into the webroot")
    run = sub.add_parser("run", help="development server")
    run.add_argument("--port", type=int, default=8001)
    args = parser.parse_args()
    {"init": cmd_init, "create-admin": cmd_create_admin, "publish": cmd_publish, "run": cmd_run}[args.command](args)


if __name__ == "__main__":
    main()

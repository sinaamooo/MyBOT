"""Numbix admin panel: products, provider API settings and publishing the site."""

from __future__ import annotations

import hashlib
from datetime import timedelta

from flask import Flask, Response, g, render_template, request
from markupsafe import Markup
from werkzeug.middleware.proxy_fix import ProxyFix

from numbix_site.assets import css_bundle, load_icons
from numbix_site.config import to_fa

from . import auth, db
from .crypto import Vault
from .settings import Settings
from .views import bp

ADMIN_CSP = (
    "default-src 'none'; style-src 'self'; img-src 'self' data:; font-src 'self'; "
    "form-action 'self'; frame-ancestors 'none'; base-uri 'none'"
)
SECURITY_HEADERS = {
    "Content-Security-Policy": ADMIN_CSP,
    "X-Frame-Options": "DENY",
    "X-Content-Type-Options": "nosniff",
    "Referrer-Policy": "no-referrer",
    "Cross-Origin-Opener-Policy": "same-origin",
    "Cross-Origin-Resource-Policy": "same-origin",
    "Permissions-Policy": "camera=(), microphone=(), geolocation=(), payment=(), usb=()",
}


def create_app(settings: Settings | None = None) -> Flask:
    settings = settings or Settings.from_env()
    db.init_db(settings.db_path)

    app = Flask(__name__)
    app.wsgi_app = ProxyFix(app.wsgi_app, x_for=1, x_proto=1, x_host=0)  # exactly one proxy: nginx
    app.config.update(
        SECRET_KEY=settings.secret_key,
        SESSION_COOKIE_NAME="__Host-nxadmin" if settings.cookie_secure else "nxadmin",
        SESSION_COOKIE_SECURE=settings.cookie_secure,
        SESSION_COOKIE_HTTPONLY=True,
        SESSION_COOKIE_SAMESITE="Strict",
        SESSION_COOKIE_PATH="/",
        PERMANENT_SESSION_LIFETIME=timedelta(seconds=auth.MAX_SESSION),
        MAX_CONTENT_LENGTH=64 * 1024,
        TEMPLATES_AUTO_RELOAD=False,
    )
    app.extensions["nx_settings"] = settings
    app.extensions["nx_vault"] = Vault(settings.fernet_key)

    assets = settings.site_root / "assets"
    stylesheet = css_bundle(assets / "css", settings.site_root / "numbix_admin" / "admin.css")
    stylesheet_tag = hashlib.sha256(stylesheet).hexdigest()[:12]
    icons = load_icons(assets / "icons")

    def icon(name: str) -> Markup:  # inlines our own SVG files, never user input
        return Markup(  # noqa: S704
            '<svg class="i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' + icons[name] + "</svg>"
        )

    app.jinja_env.globals.update(csrf_token=auth.csrf_token, icon=icon, stylesheet_tag=stylesheet_tag)
    app.jinja_env.filters["fa"] = to_fa

    @app.before_request
    def open_db():
        g.db = db.connect(settings.db_path)
        auth.load_session(g.db)
        if request.method == "POST":
            auth.check_csrf()

    @app.teardown_request
    def close_db(_error=None):
        conn = g.pop("db", None)
        if conn is not None:
            conn.close()

    @app.after_request
    def harden(response: Response) -> Response:
        response.headers.update(SECURITY_HEADERS)
        if not request.path.endswith("/static/admin.css"):
            response.headers["Cache-Control"] = "no-store"
        return response

    @app.get(f"{settings.url_prefix}/static/admin.css")
    def stylesheet_view():
        response = Response(stylesheet, mimetype="text/css")
        response.headers["Cache-Control"] = "public, max-age=31536000, immutable"
        return response

    for code, message in ((400, "درخواست نامعتبر است."), (403, "دسترسی مجاز نیست."), (404, "صفحه پیدا نشد."),
                          (405, "این روش مجاز نیست."), (413, "درخواست بیش از حد بزرگ است."),
                          (500, "خطای داخلی؛ دوباره تلاش کنید.")):  # fmt: skip
        app.register_error_handler(code, _error_page(code, message))

    app.register_blueprint(bp, url_prefix=settings.url_prefix)
    return app


def _error_page(code: int, message: str):
    def handler(_error):
        return render_template("admin/error.html", code=code, message=message), code

    return handler

"""Admin routes. Every page except /login requires a valid session."""

from __future__ import annotations

from hmac import compare_digest

from flask import Blueprint, abort, current_app, flash, g, redirect, render_template, request, session, url_for

from . import auth, products
from .db import audit
from .publish import PublishError, publish

bp = Blueprint("admin", __name__, template_folder="templates")


def _vault():
    return current_app.extensions["nx_vault"]


def _settings():
    return current_app.extensions["nx_settings"]


# ── Login / logout ─────────────────────────────────────────────────────────
@bp.route("/login", methods=["GET", "POST"])
def login():
    if g.admin is not None:
        return redirect(url_for("admin.dashboard"))
    if request.method == "GET":
        return render_template("admin/login.html", error=None)

    ip = auth.client_ip()
    if auth.login_blocked(g.db, ip):
        audit(g.db, ip, "login_blocked")
        return render_template(
            "admin/login.html", error="تلاش‌های ناموفق زیاد بود؛ ۱۵ دقیقه‌ی دیگر دوباره امتحان کنید."
        ), 429

    row = g.db.execute("SELECT * FROM admin WHERE id = 1").fetchone()
    username_ok = row is not None and compare_digest(request.form.get("username", ""), row["username"])
    password_ok = auth.password_ok(row["password_hash"] if username_ok else None, request.form.get("password", ""))
    step = None
    if username_ok and password_ok:
        secret = _vault().open(row["totp_secret"])
        step = auth.totp_step(secret, request.form.get("code", "").strip(), row["totp_last_step"])
    if step is None:
        auth.record_failure(g.db, ip)
        audit(g.db, ip, "login_failed")
        return render_template("admin/login.html", error="نام کاربری، رمز یا کد تأیید درست نیست."), 401

    g.db.execute("UPDATE admin SET totp_last_step = ? WHERE id = 1", (step,))
    auth.clear_failures(g.db, ip)
    auth.start_session(row["session_version"])
    audit(g.db, ip, "login")
    return redirect(url_for("admin.dashboard"))


@bp.post("/logout")
def logout():
    if g.admin is not None:
        audit(g.db, auth.client_ip(), "logout")
    session.clear()
    return redirect(url_for("admin.login"))


@bp.post("/sessions/revoke")
@auth.login_required
def revoke_sessions():
    g.db.execute("UPDATE admin SET session_version = session_version + 1 WHERE id = 1")
    audit(g.db, auth.client_ip(), "sessions_revoked")
    return redirect(url_for("admin.login"))


# ── Products ───────────────────────────────────────────────────────────────
@bp.get("/")
@auth.login_required
def dashboard():
    rows = products.all_products(g.db)
    return render_template(
        "admin/dashboard.html",
        rows=rows,
        price_label=products.price_label,
        api_ready=sum(1 for r in rows if r["api_enabled"] and r["api_key"]),
    )


def _form_page(product, form, status=200):
    key_hint = products.masked_key(_vault(), product["api_key"]) if product else ""
    return render_template(
        "admin/product.html", product=product, form=form, icons=products.ICONS, key_hint=key_hint
    ), status


@bp.route("/products/new", methods=["GET", "POST"])
@auth.login_required
def product_new():
    if request.method == "GET":
        return _form_page(None, products.ProductForm(values={"available": True, "icon": "package", "api_params": "{}"}))
    form = products.parse_form(request.form)
    if not form.ok:
        return _form_page(None, form, 422)
    product_id = products.save(g.db, _vault(), form)
    audit(g.db, auth.client_ip(), "product_created", f"#{product_id} {form.values['name']}")
    flash("محصول ساخته شد. برای نمایش در سایت «انتشار» را بزنید.", "success")
    return redirect(url_for("admin.dashboard"))


@bp.route("/products/<int:product_id>", methods=["GET", "POST"])
@auth.login_required
def product_edit(product_id: int):
    product = products.get_product(g.db, product_id) or abort(404)
    if request.method == "GET":
        values = dict(product)
        values.pop("api_key")
        return _form_page(product, products.ProductForm(values=values))
    form = products.parse_form(request.form)
    if not form.ok:
        return _form_page(product, form, 422)
    products.save(g.db, _vault(), form, product_id)
    detail = f"#{product_id} {form.values['name']}" + (" (API key changed)" if form.new_key is not None else "")
    audit(g.db, auth.client_ip(), "product_updated", detail)
    flash("تغییرات ذخیره شد. برای نمایش در سایت «انتشار» را بزنید.", "success")
    return redirect(url_for("admin.dashboard"))


@bp.post("/products/<int:product_id>/delete")
@auth.login_required
def product_delete(product_id: int):
    product = products.get_product(g.db, product_id) or abort(404)
    if request.form.get("confirm") != "1":
        abort(400)
    g.db.execute("DELETE FROM products WHERE id = ?", (product_id,))
    audit(g.db, auth.client_ip(), "product_deleted", f"#{product_id} {product['name']}")
    flash("محصول حذف شد.", "success")
    return redirect(url_for("admin.dashboard"))


# ── Publishing & audit ─────────────────────────────────────────────────────
@bp.post("/publish")
@auth.login_required
def publish_site():
    try:
        count = publish(_settings(), g.db)
    except PublishError as error:
        flash(f"انتشار انجام نشد: {error}", "error")
    else:
        audit(g.db, auth.client_ip(), "published", f"{count} files")
        flash("سایت با اطلاعات جدید منتشر شد.", "success")
    return redirect(url_for("admin.dashboard"))


@bp.get("/audit")
@auth.login_required
def audit_log():
    rows = g.db.execute("SELECT * FROM audit ORDER BY id DESC LIMIT 200").fetchall()
    return render_template("admin/audit.html", rows=rows)

"""Page registry: every page, its URL, navigation label and icon."""

from __future__ import annotations

from dataclasses import dataclass


@dataclass(frozen=True)
class Page:
    slug: str
    title: str
    nav: str
    icon: str
    description: str
    short: str = ""
    sections: tuple[tuple[str, str], ...] = ()  # (anchor, heading) for legal pages

    @property
    def template(self) -> str:
        return f"pages/{self.slug}.html"

    @property
    def url(self) -> str:
        return {"home": "/", "404": "/404.html"}.get(self.slug, f"/{self.slug}/")

    @property
    def output(self) -> str:
        return {"home": "index.html", "404": "404.html"}.get(self.slug, f"{self.slug}/index.html")

    @property
    def label(self) -> str:
        return self.short or self.nav


PAGES = (
    Page(
        "home",
        "نامبیکس | خدمات مجازی از طریق ربات تلگرام",
        "خانه",
        "house",
        "نامبیکس (Numbix): ثبت سفارش، پرداخت و تحویل سریع خدمات مجازی از طریق ربات تلگرام.",
    ),
    Page(
        "panel",
        "پنل کاربری",
        "پنل کاربری",
        "layout-dashboard",
        "پیشخوان نامبیکس: موجودی، سفارش‌ها، خدمات، وضعیت سرویس‌ها و اعلان‌ها.",
        short="پنل",
    ),
    Page(
        "services",
        "خدمات و قیمت‌ها",
        "خدمات و قیمت‌ها",
        "shopping-bag",
        "فهرست خدمات نامبیکس (Numbix) و قیمت به‌روز هر خدمت.",
        short="خدمات",
    ),
    Page("about", "درباره‌ی ما", "درباره‌ی ما", "building-2", "معرفی کسب‌وکار نامبیکس (Numbix) و تعهدات ما به کاربران."),
    Page(
        "contact",
        "تماس با ما",
        "تماس با ما",
        "headphones",
        "راه‌های ارتباط با نامبیکس (Numbix): تلفن همراه، ایمیل، نشانی و ربات تلگرام.",
        short="تماس",
    ),
    Page(
        "terms",
        "قوانین و مقررات",
        "قوانین و مقررات",
        "scale",
        "قوانین و مقررات استفاده از خدمات نامبیکس (Numbix)، شرایط تحویل و بازگشت وجه.",
        sections=(
            ("general", "کلیات"),
            ("definitions", "تعاریف"),
            ("orders", "ثبت سفارش و پرداخت"),
            ("delivery", "تحویل خدمات"),
            ("refund", "لغو سفارش و بازگشت وجه"),
            ("user", "تعهدات کاربر"),
            ("liability", "محدودیت مسئولیت"),
            ("privacy", "حریم خصوصی"),
            ("changes", "تغییر قوانین"),
            ("disputes", "رسیدگی به شکایات و حل اختلاف"),
            ("contact", "ارتباط با ما"),
        ),
    ),
    Page(
        "privacy",
        "حریم خصوصی",
        "حریم خصوصی",
        "shield-check",
        "سیاست حریم خصوصی نامبیکس (Numbix): چه اطلاعاتی جمع‌آوری می‌شود و چگونه از آن محافظت می‌کنیم.",
        sections=(
            ("collect", "اطلاعاتی که جمع‌آوری می‌کنیم"),
            ("purpose", "هدف استفاده از اطلاعات"),
            ("sharing", "اشتراک‌گذاری اطلاعات"),
            ("cookies", "کوکی‌ها"),
            ("security", "نگهداری و امنیت اطلاعات"),
            ("rights", "حقوق کاربران"),
            ("changes", "تغییرات این سیاست"),
            ("contact", "ارتباط با ما"),
        ),
    ),
    Page("404", "صفحه پیدا نشد", "صفحه پیدا نشد", "orbit", "صفحه‌ی موردنظر پیدا نشد."),
)

BY_SLUG = {page.slug: page for page in PAGES}

NAV_GROUPS = (
    ("پیشخوان", ("home", "panel", "services")),
    ("نامبیکس", ("about", "contact")),
    ("اطلاعات حقوقی", ("terms", "privacy")),
)

MOBILE_NAV = ("home", "services", "panel", "contact")

SITEMAP = ("home", "services", "panel", "about", "contact", "terms", "privacy")

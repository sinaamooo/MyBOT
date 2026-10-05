#!/usr/bin/env python3
"""
اطلاعات واقعی کسب‌وکار را در صفحات سایت جای‌گذاری می‌کند.

۱. مقادیر INFO را در همین فایل پر کنید.
۲. اجرا کنید:  python3 fill.py
۳. خروجی آماده‌ی آپلود در پوشه‌ی dist/ ساخته می‌شود (پوشه‌ی public/ دست نمی‌خورد).
"""
import re
import shutil
import sys
from pathlib import Path

INFO = {
    "DOMAIN": "",          # فقط دامنه، مثال: numbix.ir
    "BOT_USERNAME": "",    # نام کاربری ربات بدون @، مثال: NumbixBot
    "OWNER_NAME": "",      # نام و نام خانوادگی صاحب کسب‌وکار (همان که در اینماد ثبت می‌کنید)
    "MOBILE": "",          # مثال: 09121234567
    "EMAIL": "",           # مثال: info@numbix.ir
    "ADDRESS": "",         # نشانی کامل، دقیقاً مطابق نشانی ثبت‌شده در اینماد
    "POSTAL_CODE": "",     # کد پستی ۱۰ رقمی
    "HOURS": "شنبه تا پنجشنبه، ساعت ۹ تا ۱۸",
    "SERVICES_LINE": "",   # یک جمله بدون نقطه‌ی پایانی، مثال: فروش شماره‌ی مجازی برای تأیید حساب پیام‌رسان‌ها
    "SERVICE_1": "", "PRICE_1": "",   # قیمت به تومان، فقط عدد، مثال: 45000
    "SERVICE_2": "", "PRICE_2": "",
    "SERVICE_3": "", "PRICE_3": "",
    "SERVICE_4": "", "PRICE_4": "",
}

FA_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
EN_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")

HERE = Path(__file__).resolve().parent
SRC = HERE / "public"
DST = HERE / "dist"


def prepare(info):
    v = {k: s.strip() for k, s in info.items()}
    v["DOMAIN"] = re.sub(r"^https?://|/+$", "", v["DOMAIN"])
    v["BOT_USERNAME"] = re.sub(r"^(https?://)?(t\.me/)?@?", "", v["BOT_USERNAME"])
    v["EMAIL"] = v["EMAIL"].translate(EN_DIGITS)
    if v["MOBILE"]:
        v["MOBILE_TEL"] = re.sub(r"[^\d+]", "", v["MOBILE"].translate(EN_DIGITS))
        v["MOBILE"] = v["MOBILE"].translate(EN_DIGITS).translate(FA_DIGITS)
    else:
        v["MOBILE_TEL"] = ""
    v["POSTAL_CODE"] = v["POSTAL_CODE"].translate(EN_DIGITS).translate(FA_DIGITS)
    for key in [k for k in v if k.startswith("PRICE_")]:
        raw = re.sub(r"[,٬،\s]", "", v[key].translate(EN_DIGITS))
        if raw.isdigit():
            v[key] = f"{int(raw):,}".replace(",", "٬").translate(FA_DIGITS)
    return v


def main():
    values = prepare(INFO)
    empty = [k for k, s in values.items() if not s]
    if DST.exists():
        shutil.rmtree(DST)
    shutil.copytree(SRC, DST)

    for path in list(DST.glob("*.html")) + [DST / "robots.txt", DST / "sitemap.xml"]:
        text = path.read_text(encoding="utf-8")
        for key, val in values.items():
            if val:
                text = text.replace("{{" + key + "}}", val)
        path.write_text(text, encoding="utf-8")

    left = sorted({m for p in DST.rglob("*") if p.suffix in {".html", ".txt", ".xml"}
                   for m in re.findall(r"\{\{[A-Z0-9_]+\}\}", p.read_text(encoding="utf-8"))})
    print(f"خروجی ساخته شد: {DST}")
    if left:
        print("این موارد هنوز پر نشده‌اند (مقدارشان را در INFO بنویسید):", ", ".join(left))
        sys.exit(1)
    if empty:
        print("هشدار، این کلیدها خالی‌اند:", ", ".join(empty))
    print("همه‌ی جاهای خالی پر شد. محتوای پوشه‌ی dist را روی هاست آپلود کنید.")


if __name__ == "__main__":
    main()

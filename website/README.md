# سایت و پنل مدیر نامبیکس (Numbix)

دو بخش دارد:

- **سایت عمومی** (برای اینماد و مشتری‌ها): HTML/CSS/JS ایستا که با Python + Jinja ساخته می‌شود. روی هاست نه PHP لازم است نه پایتون.
- **پنل مدیر** (`/admin`): برنامه‌ی Flask پشت nginx برای مدیریت محصولات، قیمت‌ها، تنظیمات API هر محصول و انتشار سایت. ورود با رمز و کد دومرحله‌ای انجام می‌شود.

در سایت هیچ دکمه، لینک یا ورودی به تلگرام نیست؛ نام کاربری ربات فقط در متن قوانین و حریم خصوصی آمده است.

---

## ۱. اجرا روی کامپیوتر خودتان (پیش‌نمایش)

پیش‌نیاز: Python 3.10 یا جدیدتر.

```bash
cd website
python3 -m venv .venv
. .venv/bin/activate                 # ویندوز: .venv\Scripts\activate
pip install -r requirements.txt
python build.py --serve              # سایت: http://127.0.0.1:8000
```

پنل مدیر روی کامپیوتر خودتان:

```bash
python manage.py init                # ساخت کلیدها و پایگاه‌داده در instance/
python manage.py create-admin        # نام کاربری، رمز و کلید Google Authenticator
python manage.py run                 # پنل: http://127.0.0.1:8001/admin/login
```

---

## ۲. وصل‌کردن دامنه

1. در پنل جایی که دامنه را خریده‌اید (یا nic.ir)، بخش DNS را باز کنید. اگر DNS ندارد، Nameserverها را روی یک سرویس DNS بگذارید (مثل DNS ابر آروان یا DNS خود فروشنده‌ی دامنه).
2. دو رکورد بسازید:

   | نوع | نام | مقدار |
   |---|---|---|
   | A | `@` | IP سرور |
   | A | `www` | IP سرور |

3. معمولاً چند دقیقه تا چند ساعت طول می‌کشد. با `ping numbix.ir` ببینید IP سرور را برمی‌گرداند یا نه.

---

## ۳. نصب روی سرور (Ubuntu + aaPanel)

**الف) ساخت سایت در aaPanel:** Website ← Add site، دامنه با و بدون www، PHP روی **Static**. ریشه‌ی سایت `/www/wwwroot/numbix.ir` است.

**ب) آماده‌کردن پروژه** (در ترمینال aaPanel یا SSH):

```bash
sudo apt install -y python3-venv unzip
sudo mkdir -p /opt/numbix && cd /opt/numbix
sudo unzip ~/numbix-galaxy-site.zip            # zip را قبلاً در پوشه‌ی خانه آپلود کنید
cd website
sudo python3 -m venv .venv && sudo .venv/bin/pip install -r requirements.txt
sudo nano site.toml                             # اطلاعات واقعی را پر کنید
sudo .venv/bin/python build.py --strict         # اگر چیزی خالی باشد خطا می‌دهد
```

**ج) پنل مدیر:**

```bash
sudo .venv/bin/python manage.py init
sudo .venv/bin/python manage.py create-admin    # کلید نمایش‌داده‌شده را در Google Authenticator وارد کنید
sudo chown -R www:www instance /www/wwwroot/numbix.ir
sudo -u www .venv/bin/python manage.py publish  # سایت را در /www/wwwroot/numbix.ir منتشر می‌کند
sudo cp deploy/numbix-admin.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now numbix-admin
```

**د) nginx:** فایل `dist-nginx.conf` کنار پروژه ساخته شده است. در aaPanel از Website ← numbix.ir ← Conf:
- بخش **PART 1** را بالای فایل (بیرون از `server {`) بچسبانید.
- بخش **PART 2** را داخل `server { }` بعد از خطوط `ssl_` بچسبانید و بلوک‌های `location /`، `error_page` و `location ~ .*\.(js|css)$` خود aaPanel را پاک کنید.

**هـ) SSL:** سایت ← SSL ← Let's Encrypt، بعد **Force HTTPS**.

**و) دیواره‌ی آتش:** در Security فقط پورت‌های 80، 443، SSH و پورت aaPanel باز باشند. پورت 8001 فقط روی 127.0.0.1 گوش می‌دهد و از بیرون در دسترس نیست.

> اگر پنل مدیر نمی‌خواهید، فقط محتوای `dist/` را در ریشه‌ی سایت آپلود کنید.

---

## ۴. پنل مدیر

- **محصولات و API:** نام، قیمت، دسته، آیکن و موجودی هر محصول، به‌اضافه‌ی سرویس‌دهنده، نشانی API (فقط https)، کد محصول، کلید API و پارامترهای JSON.
  - کلید API رمزگذاری‌شده ذخیره می‌شود و بعد از ذخیره فقط چهار کاراکتر آخرش نمایش داده می‌شود.
- **انتشار در سایت:** سایت را از روی محصولات دوباره می‌سازد. اگر اطلاعات کسب‌وکار ناقص باشد، منتشر نمی‌کند.
- **گزارش فعالیت:** همه‌ی ورودها، تلاش‌های ناموفق و تغییرات ثبت می‌شوند.
- **خروج از همه‌ی دستگاه‌ها:** همه‌ی نشست‌های باز را باطل می‌کند.
- **گم‌شدن گوشی یا رمز:** `manage.py create-admin` را دوباره اجرا کنید.
- **برای ربات:** `from numbix_admin.integration import product_api` و `product_api(id)` تنظیمات API محصول را (با کلید رمزگشایی‌شده) برمی‌گرداند. فراخوانی API سرویس‌دهنده بعد از دریافت مستندات اضافه می‌شود.

نسخه‌ی پشتیبان: پوشه‌ی `instance/` (کلیدها و پایگاه‌داده). اگر `admin.env` گم شود، کلیدهای API ذخیره‌شده قابل بازیابی نیستند.

---

## ۵. امنیت

**سایت عمومی:**
- CSP سخت‌گیرانه؛ فقط اسکریپت‌های خود سایت اجرا می‌شوند.
- HSTS، ممنوعیت iframe (clickjacking) و هدرهای امنیتی دیگر.
- محدودیت نرخ درخواست برای جلوگیری از flood.
- بستن فایل‌های مخفی و فایل‌های سمت سرور.
- کد نماد اینماد فقط اگر واقعاً از `trustseal.enamad.ir` باشد پذیرفته می‌شود.

**پنل مدیر:**
- ورود: رمز با scrypt، کد دومرحله‌ای TOTP با جلوگیری از استفاده‌ی دوباره، و قفل پس از ۵ تلاش ناموفق برای هر IP یا ۵۰ تلاش در کل.
- نشست‌ها: کوکی `__Host-` با Secure، HttpOnly و SameSite=Strict؛ پایان نشست پس از ۳۰ دقیقه بی‌کاری یا ۱۲ ساعت در کل.
- درخواست‌ها: توکن CSRF به‌همراه بررسی Origin.
- داده‌ها: کوئری‌های پارامتری (بدون امکان SQL Injection)، اعتبارسنجی همه‌ی ورودی‌ها، رمزگذاری کلیدها، و سرویس systemd محدودشده.

**توصیه‌ی جدی:** در nginx خطوط `allow` و `deny` بخش پنل مدیر را فعال کنید تا فقط IP خودتان پنل را ببیند. مسیر `/admin` را هم در `site.toml` به یک مسیر غیرقابل‌حدس تغییر دهید.

**اجرای تست‌های امنیتی:**

```bash
pip install pytest && pytest
```

این تست‌ها حمله‌های XSS، SQL Injection، CSRF، brute force، جعل نشست، دستکاری کد اینماد، ورودی‌های نامعتبر و انتشار را امتحان می‌کنند.

---

## ۶. اینماد

- **کد تأیید دامنه:** در `[enamad] meta_code`.
- **کد نماد:** در `[enamad] seal_html`.

بعد از تغییر این دو، `build.py` یا «انتشار» را دوباره اجرا کنید.

چک‌لیست:
- دامنه به نام خود متقاضی در ایرنیک ثبت شده باشد.
- نشانی و تلفن دقیقاً مطابق اطلاعات اینماد باشد.
- `build.py --strict` بدون خطا اجرا شود.
- سایت با HTTPS از داخل ایران باز شود.

---

## ساختار

```
website/
├── site.toml, build.py, manage.py, requirements.txt, pyproject.toml
├── numbix_site/     سازنده‌ی سایت (config، pages، assets، render، security، server)
├── numbix_admin/    پنل مدیر (auth، products، publish، crypto، db، views، integration، templates)
├── templates/       قالب‌های سایت + deploy/nginx.conf
├── assets/          CSS (Design System)، JS (کهکشان و کامپوننت‌ها)، آیکن‌ها، فونت
├── deploy/          سرویس systemd پنل مدیر
└── tests/           تست‌های امنیتی
```

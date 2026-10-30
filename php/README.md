# MyBOT (نسخه PHP)

ربات با **وب‌هوک** کار می‌کند: تلگرام هر پیام را به `bot.php` می‌فرستد، پس روی هاست cPanel نیازی به روشن نگه داشتن برنامه نیست.

## نیازمندی‌ها

- PHP نسخه 8.0 یا بالاتر با افزونه‌های `curl`، `gd` (با FreeType)، `pdo_sqlite`، `mbstring`
- دامنه با SSL معتبر (https)

## نصب روی cPanel

1. فایل زیپ را در **File Manager** داخل `public_html` آپلود و Extract کنید. پوشه `MyBOT-PHP` ساخته می‌شود (می‌توانید اسمش را مثلا `bot` بگذارید).
2. فایل `.env` داخل پوشه را باز کنید؛ توکن و آیدی مدیر داخلش هست. در صورت نیاز `CHANNEL_URL` و اگر هاست ایرانی است `PROXY` را پر کنید.
3. در **Terminal** وب‌هوک را ثبت کنید (آدرس را با دامنه و مسیر خودتان عوض کنید):

```bash
php ~/public_html/MyBOT-PHP/setup.php https://yourdomain.com/MyBOT-PHP/bot.php
```

4. در **Cron Jobs** یک کرون هر دقیقه (`* * * * *`) بسازید:

```bash
php /home/USERNAME/public_html/MyBOT-PHP/cron.php
```

5. در BotFather گزینه **Group Privacy** را خاموش کنید و ربات را در گروه‌ها و کانال‌های عضویت اجباری ادمین کنید.
6. برای `/gift @username` مقدارهای `API_ID` و `API_HASH` در `.env` باید پر باشند. ربات بار اول با همین‌ها وارد MTProto می‌شود و کلیدش را در دیتابیس نگه می‌دارد. اگر هاست به تلگرام دسترسی مستقیم ندارد، `MTPROTO_PROXY` را به شکل `socks5://ip:port` پر کنید.

کرون هر دقیقه قیمت‌ها را تازه نگه می‌دارد تا جواب تبدیل‌ها بدون صبر برای API ها برسد.

اگر `php -v` در ترمینال نسخه پایین‌تر از 8 نشان داد، از مسیر نسخه جدید استفاده کنید، مثلا `/opt/cpanel/ea-php82/root/usr/bin/php` (هم در دستور setup هم در کرون).

## دستورات مفید

```bash
php ~/public_html/MyBOT-PHP/setup.php info
```

```bash
php ~/public_html/MyBOT-PHP/tests/run.php
```

## امنیت

فایل `.htaccess` همه فایل‌ها (از جمله `.env` و دیتابیس) را از دسترس عمومی خارج می‌کند و فقط `bot.php` و `setup.php` باز هستند. `bot.php` فقط درخواست‌هایی را قبول می‌کند که توکن مخفی وب‌هوک را داشته باشند. `setup.php` از مرورگر فقط با تنظیم `SETUP_KEY` در `.env` و باز کردن `setup.php?key=...` کار می‌کند.

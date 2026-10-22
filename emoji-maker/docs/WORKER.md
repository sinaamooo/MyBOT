# کارگر (Worker) و صف کارها

ساخت ایموجی (به‌خصوص WEBM و پک‌های بزرگ) سنگین است و هرگز داخل وبهوک انجام نمی‌شود. ربات فقط یک
«کار» در جدول `generation_jobs` ثبت می‌کند و **کارگر** آن را انجام می‌دهد: پیش‌نمایش، ساخت نهایی،
تحویل فایل‌ها، انتشار پک، پاک‌سازی.

ویژگی‌ها: برداشتن اتمی کار (بدون نیاز به `SKIP LOCKED`)، محدودیت تعداد کار هم‌زمان (`WORKER_MAX_PARALLEL`)
و حداکثر یک کار فعال برای هر کاربر، نمایش درصد پیشرفت در همان پیام تلگرام، لغو توسط کاربر یا مدیر،
تلاش مجدد خودکار با تأخیر نمایی برای خطاهای موقت، بازیابی کارهای گیرکرده (بدون ضربان قلب)، و ثبت
وضعیت کارگر در `storage/cache/worker.json` که در داشبورد پنل دیده می‌شود.

## هاست اشتراکی (cPanel) — Cron

در cPanel بخش **Cron Jobs**، یک کار «هر دقیقه» (`* * * * *`) بسازید:

```
/usr/local/bin/php /home/USER/emoji-maker/bin/cron.php >/dev/null 2>&1
```

* مسیر PHP را از بخش «Select PHP Version» یا با پشتیبانی هاست بپرسید (گاهی `/opt/cpanel/ea-php82/root/usr/bin/php`).
* `cron.php` در هر اجرا حدود ۵۵ ثانیه کارها را انجام می‌دهد و ساعتی یک‌بار پاک‌سازی را هم اجرا می‌کند.
* اجرای هم‌زمان چند Cron مشکلی ندارد؛ سقف هم‌زمانی از دیتابیس کنترل می‌شود.

## سرور (Ubuntu / aaPanel) — systemd

```bash
sudo cp deploy/emoji-worker.service /etc/systemd/system/
sudo nano /etc/systemd/system/emoji-worker.service   # مسیر پروژه، کاربر و PHP را تنظیم کنید
sudo systemctl daemon-reload
sudo systemctl enable --now emoji-worker
systemctl status emoji-worker
journalctl -u emoji-worker -f
```

برای چند کارگر موازی می‌توانید از `deploy/emoji-worker@.service` استفاده کنید
(`systemctl enable --now emoji-worker@1 emoji-worker@2`) یا گزینه‌ی `--slots=N`.
روی سرور هم یک Cron برای پاک‌سازی ساعتی کافی است (`deploy/crontab.linux.txt`).

با Supervisor: `deploy/supervisor.conf` را در `/etc/supervisor/conf.d/` کپی و `supervisorctl reread && supervisorctl update` کنید.

## گزینه‌های خط فرمان

```
php bin/worker.php --once [--time-limit=55]     تا تمام شدن کارها یا رسیدن به زمان (مناسب Cron)
php bin/worker.php --daemon [--slots=2]         دائمی؛ با SIGTERM به‌آرامی متوقف می‌شود
php bin/worker.php --daemon --max-jobs=200      بعد از ۲۰۰ کار خودش را از نو اجرا می‌کند (جلوگیری از نشت حافظه)
php bin/cleanup.php                             پاک‌سازی دستی (خروجی‌های منقضی، فایل‌های موقت، رکوردهای قدیمی)
```

## مدیریت کارها

در پنل ← «صف کارها»: مشاهده‌ی وضعیت، پیشرفت، خطا، لغو، تلاش مجدد. کاربران هم در ربات دکمه‌ی لغو دارند.

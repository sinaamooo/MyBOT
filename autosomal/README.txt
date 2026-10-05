ربات تحلیل + بنر + سیگنال (یک ربات، /panel با سه بخش)

نصب یا به‌روزرسانی روی هاست (مثلاً public_html/Dina):
1) bot.php و cron.php و پوشه‌ی modules را آپلود و جایگزین کنید.
2) نصب جدید: config.example.php را به config.php تغییر نام دهید و توکن، آیدی مدیر،
   webhook_secret، webhook_url و کلید Gemini را پر کنید.
   به‌روزرسانی: config.php فعلی هاست را نگه دارید (این پوشه config.php ندارد تا روی آن نوشته نشود).
3) یک بار این آدرس را باز کنید تا وبهوک ثبت شود و وضعیت بخش‌ها را ببینید:
   https://دامنه/مسیر/bot.php?setup=<webhook_secret>
4) کرون هر دقیقه (PHP 8.1 یا بالاتر):
   /usr/local/bin/php /home/USER/public_html/Dina/cron.php

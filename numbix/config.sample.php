<?php
// نامبیکس | Numbix — این فایل را با اطلاعاتِ واقعیِ خود پر کنید و روی گیت‌هاب نگذارید (مجوز 600).
// نسخه‌ی پرشده‌ی این فایل جداگانه و خصوصی برایتان فرستاده شده است.
if (!defined('BOT_TOKEN'))           define('BOT_TOKEN', 'توکنِ ربات');
if (!defined('ADMIN_ID'))            define('ADMIN_ID', 0);
if (!defined('ADMIN_IDS'))           define('ADMIN_IDS', [0]);
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', ''); // php -r "echo password_hash('رمز', PASSWORD_DEFAULT);"
if (!defined('ADMIN_PANEL_PASS'))    define('ADMIN_PANEL_PASS', '');     // یا رمزِ ساده (اگر هش نگذاشتید)
if (!defined('ADMIN_EMAIL'))         define('ADMIN_EMAIL', '');          // ورودِ پنل: ایمیلِ مدیر
if (!defined('ADMIN_PHONE'))         define('ADMIN_PHONE', '');          // ورودِ پنل: شماره‌ی موبایلِ مدیر
if (!defined('WEBHOOK_SECRET'))      define('WEBHOOK_SECRET', '');
if (!defined('CRON_KEY'))            define('CRON_KEY', '');
if (!defined('HEALTH_KEY'))          define('HEALTH_KEY', '');
if (!defined('FIVESIM_TOKEN'))       define('FIVESIM_TOKEN', '');
// اطلاعاتِ دیتابیسِ MySQL را از پنلِ وب ← تنظیمات ← دیتابیس وارد کنید (در DATA_DIR/db.php ذخیره می‌شود).
if (!defined('DATA_DIR'))            define('DATA_DIR', __DIR__ . '/data_master');

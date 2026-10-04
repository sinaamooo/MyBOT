<?php
// نامبیکس | Numbix — نمونه‌ی تنظیمات.
// این فایل را کپی کنید به config.local.php، با اطلاعاتِ واقعی پر کنید و مجوزش را 600 بگذارید.
// config.local.php هرگز نباید روی گیت‌هاب یا داخلِ زیپِ آپدیت برود (در .gitignore هست).
if (!defined('BOT_TOKEN'))           define('BOT_TOKEN', 'توکنِ ربات');
if (!defined('ADMIN_ID'))            define('ADMIN_ID', 0);
if (!defined('ADMIN_IDS'))           define('ADMIN_IDS', [0]);
if (!defined('ADMIN_PASSWORD_HASH')) define('ADMIN_PASSWORD_HASH', ''); // php -r "echo password_hash('رمز', PASSWORD_DEFAULT);"
if (!defined('ADMIN_PANEL_PASS'))    define('ADMIN_PANEL_PASS', '');     // یا رمزِ ساده (اگر هش نگذاشتید) — دست‌کم ۱۲ نویسه
if (!defined('ADMIN_EMAIL'))         define('ADMIN_EMAIL', '');          // ورودِ پنل: ایمیلِ مدیر
if (!defined('ADMIN_PHONE'))         define('ADMIN_PHONE', '');          // ورودِ پنل: شماره‌ی موبایلِ مدیر
if (!defined('WEBHOOK_SECRET'))      define('WEBHOOK_SECRET', '');       // یک رشته‌ی تصادفیِ بلند: php -r "echo bin2hex(random_bytes(24));"
if (!defined('CRON_KEY'))            define('CRON_KEY', '');             // یک رشته‌ی تصادفیِ بلندِ دیگر
if (!defined('HEALTH_KEY'))          define('HEALTH_KEY', '');
if (!defined('FIVESIM_TOKEN'))       define('FIVESIM_TOKEN', '');
// اطلاعاتِ دیتابیسِ MySQL را از پنلِ وب ← تنظیمات ← دیتابیس وارد کنید (در DATA_DIR/db.php ذخیره می‌شود).

// امن‌ترین حالت: پوشه‌ی داده بیرون از ریشه‌ی وب (public_html) باشد.
if (!defined('DATA_DIR'))            define('DATA_DIR', __DIR__ . '/data_master');
// if (!defined('DATA_DIR'))         define('DATA_DIR', '/home/USER/private/data_master');

// ── ایردراپ (کریستال) — جلوگیری از سوءاستفاده ─────────────────────────────
// این‌ها حالا از پنلِ ربات هم قابلِ تغییرند: /panel ← 🎮 بازی‌ها ← 🎁 ایردراپ
// (مقدارِ پنل بر این define‌ها اولویت دارد؛ این‌ها فقط پیش‌فرضِ اولیه‌اند).
// کریستال رایگان جمع می‌شود؛ تبدیلش به پولِ کیف‌پول/کدِ تخفیف این محدودیت‌ها را دارد:
// if (!defined('AD_ON'))                  define('AD_ON', true);          // کلِ بخشِ ایردراپ
// if (!defined('AD_CASHOUT_ON'))          define('AD_CASHOUT_ON', true);  // تبدیلِ کریستال به تومان/کد
// if (!defined('AD_CASHOUT_NEED_BUY'))    define('AD_CASHOUT_NEED_BUY', 1);      // چند خریدِ تحویل‌شده لازم است
// if (!defined('AD_CASHOUT_DAY_MAX'))     define('AD_CASHOUT_DAY_MAX', 10000);   // سقفِ هر کاربر در روز (تومان، ۰ = بی‌سقف)
// if (!defined('AD_CASHOUT_SPEND_PCT'))   define('AD_CASHOUT_SPEND_PCT', 20);    // کلِ تبدیل‌های هر کاربر حداکثر ۲۰٪ِ خریدهای واقعی‌اش
// if (!defined('AD_CASHOUT_ALL_DAY_MAX')) define('AD_CASHOUT_ALL_DAY_MAX', 300000); // سقفِ همه‌ی کاربران در روز

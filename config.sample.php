<?php
/**
 * پیکربندی NIKTO CRYPTO BOT
 *
 * این فایل را به نام config.php کپی کنید و مقادیر را پر کنید:
 *   cp config.sample.php config.php
 */

return [
    // توکن ربات از @BotFather
    'bot_token' => 'PUT-YOUR-BOT-TOKEN-HERE',

    // شناسه‌ی عددی خود ربات (بخش قبل از ":" در توکن)
    'bot_id'    => 0,

    // شناسه‌ی عددی مالک ربات — دسترسی کامل به پنل
    'owner_id'  => 0,

    // مدیران اضافی (می‌توانید از داخل پنل هم اضافه کنید)
    'admins'    => [],

    // مسیر دیتابیس
    'db_path'   => __DIR__ . '/data/bot.sqlite',

    // منطقه‌ی زمانی پیش‌فرض (از پنل هم قابل تغییر است)
    'timezone'  => 'Asia/Tehran',

    // نام نمایشی روی کارت‌ها
    'brand'     => 'NIKTO CRYPTO',

    // مهلت درخواست‌های HTTP (ثانیه)
    'http_timeout' => 25,

    // در صورت نیاز به پروکسی برای دسترسی به تلگرام یا APIها
    // مثال: 'socks5h://127.0.0.1:1080' یا 'http://user:pass@host:port'
    'http_proxy' => '',

    // سطح لاگ: debug | info | warn | error
    'log_level' => 'info',

    // حالت وب‌هوک: آدرس عمومی فایل webhook.php روی دامنه‌ی شما
    'webhook_url'    => 'https://your-domain.tld/webhook.php',

    // یک رشته‌ی تصادفی؛ تلگرام آن را در هدر هر درخواست می‌فرستد
    'webhook_secret' => '',
];

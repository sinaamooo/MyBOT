<?php

/**
 * پیکربندی عمومی NIKTO CRYPTO BOT
 *
 * 🔐 توکن ربات عمداً اینجا نیست تا در مخزن ذخیره نشود.
 *    آن را یک‌بار با دستور زیر ثبت کنید (فایل config.local.php ساخته می‌شود):
 *
 *      php tools/install.php --token=123456789:AAH...
 *
 *    یا به‌صورت متغیر محیطی:  BOT_TOKEN=123456789:AAH... php bot.php
 */

return [
    // توکن از config.local.php یا متغیر محیطی BOT_TOKEN خوانده می‌شود
    'bot_token' => '',

    // شناسه‌ی عددی خود ربات (بخش قبل از ":" در توکن)
    'bot_id'    => 8870139346,

    // مالک ربات — دسترسی کامل به پنل
    'owner_id'  => 6595849261,

    // مدیران ربات
    'admins'    => [6595849261],

    // آدرس وب‌هوک؛ با «php tools/webhook-set.php» روی تلگرام ثبت می‌شود
    'webhook_url' => 'https://nikto.s14.telviprobot.top/webhook.php',

    'db_path'   => __DIR__ . '/data/bot.sqlite',
    'timezone'  => 'Asia/Tehran',
    'brand'     => 'NIKTO CRYPTO',

    'http_timeout' => 25,

    // در صورت نیاز به پروکسی: 'socks5h://127.0.0.1:1080'
    'http_proxy' => '',

    'log_level' => 'info',
];

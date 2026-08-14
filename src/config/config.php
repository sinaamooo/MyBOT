<?php
/**
 * Telegram Member Shop System - Main Configuration
 */

// Bot Configuration
define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('BOT_USERNAME', '@mybot_shop_bot'); // تنظیم بعدی

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'telegram_shop');

// API Configuration
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);
define('WEBHOOK_URL', 'https://yourdomain.com/bot.php'); // تنظیم بعدی

// Shop Settings
define('PROFIT_PERCENTAGE', 10); // درصد سود
define('MIN_MEMBER_PRICE', 100); // حداقل قیمت عضویت
define('MAX_MEMBER_PRICE', 100000); // حداکثر قیمت عضویت

// Payment Methods
define('PAYMENT_METHODS', [
    'tether' => 'تتر (USDT)',
    'tron' => 'ترون (TRX)',
    'tomy' => 'تومی'
]);

// Emoji Premium
define('EMOJI_PREMIUM', [
    'shop' => '🛍️',
    'member' => '👥',
    'money' => '💰',
    'lock' => '🔒',
    'unlock' => '🔓',
    'check' => '✅',
    'error' => '❌',
    'warning' => '⚠️',
    'info' => 'ℹ️',
    'star' => '⭐',
    'fire' => '🔥',
    'rocket' => '🚀'
]);

// Button Colors (Glassy Style)
define('BUTTON_COLORS', [
    'blue' => '#0088cc',
    'red' => '#ff0000',
    'green' => '#00cc00'
]);

// Database Configuration
define('DB_CHARSET', 'utf8mb4');

// Admin Panel Settings
define('ADMIN_PANEL_URL', '/admin/');
define('ADMIN_SESSION_TIMEOUT', 3600 * 24); // 24 ساعت

// Sub-Bots Configuration
define('SUB_BOTS', [
    // نمونه: 'bot_token' => 'bot_id'
]);

// Logging
define('LOG_PATH', __DIR__ . '/../../storage/logs/');
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR

// Timezone
date_default_timezone_set('Asia/Tehran');

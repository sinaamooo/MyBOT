<?php

declare(strict_types=1);

use EmojiMaker\Support\Env;

$root = dirname(__DIR__);

return [
    'name' => Env::get('APP_NAME', 'Emoji Maker'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => Env::bool('APP_DEBUG', false),
    'url' => rtrim((string) Env::get('APP_URL', ''), '/'),
    'key' => (string) Env::get('APP_KEY', ''),
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Tehran'),
    'locale' => Env::get('APP_LOCALE', 'fa'),
    'root' => $root,
    'public_path' => $root . '/public',

    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'emoji_maker'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
        'socket' => Env::get('DB_SOCKET', ''),
        'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    ],

    'telegram' => [
        'token' => (string) Env::get('TELEGRAM_BOT_TOKEN', ''),
        'username' => ltrim((string) Env::get('TELEGRAM_BOT_USERNAME', ''), '@'),
        'webhook_secret' => (string) Env::get('TELEGRAM_WEBHOOK_SECRET', ''),
        'admin_ids' => array_values(array_filter(array_map(
            static fn ($v) => (int) trim($v),
            explode(',', (string) Env::get('TELEGRAM_ADMIN_IDS', ''))
        ))),
        'api_base' => rtrim((string) Env::get('TELEGRAM_API_BASE', 'https://api.telegram.org'), '/'),
        'ip_check' => Env::bool('TELEGRAM_IP_CHECK', false),
        'webhook_mode' => Env::get('WEBHOOK_MODE', 'inline'),
        // Telegram webhook source ranges (https://core.telegram.org/bots/webhooks)
        'ip_ranges' => ['149.154.160.0/20', '91.108.4.0/22'],
        'max_webhook_body' => 1024 * 1024,
    ],

    'storage' => [
        'path' => (string) Env::get('STORAGE_PATH', ''),
    ],

    'tools' => [
        'ffmpeg' => (string) Env::get('FFMPEG_PATH', ''),
        'ffprobe' => (string) Env::get('FFPROBE_PATH', ''),
        'nice' => Env::int('PROCESS_NICE', 10),
        'timeout' => Env::int('PROCESS_TIMEOUT', 120),
    ],

    'worker' => [
        'max_parallel' => Env::int('WORKER_MAX_PARALLEL', 2),
        'memory_limit' => Env::get('WORKER_MEMORY_LIMIT', '512M'),
        'stale_after' => 600, // seconds without heartbeat before a running job is recovered
    ],

    'admin' => [
        'session_secure' => Env::get('SESSION_SECURE', 'auto'),
        'session_lifetime' => Env::int('ADMIN_SESSION_LIFETIME', 7200),
        'login_max_attempts' => 5,
        'login_lock_minutes' => 15,
    ],

    // Official Telegram technical requirements (https://core.telegram.org/stickers).
    'telegram_formats' => [
        'video' => [
            'emoji_size' => 100,          // "For emoji, the video must be exactly 100x100 pixels"
            'sticker_size' => 512,
            'max_duration' => 3.0,         // seconds
            'max_fps' => 30,
            'max_bytes' => 256 * 1024,     // "Video size should not exceed 256 KB"
            'codec' => 'vp9',
        ],
        'animated' => [
            'canvas' => 512,               // "The canvas size must be 512x512 pixels"
            'fps' => 60,                   // "All animations must run at 60 Frames Per Second"
            'max_duration' => 3.0,
            'max_bytes' => 64 * 1024,      // "Final file size must not exceed 64 KB"
        ],
        'static' => [
            'emoji_size' => 100,           // "For emoji, images must be exactly 100x100 pixels"
            'formats' => ['png', 'webp'],
        ],
        'pack' => [
            'max_initial' => 50,           // createNewStickerSet: 1-50 initial stickers
            'max_emoji_set' => 200,        // "Emoji sticker sets can have up to 200 stickers"
            'max_emoji_per_sticker' => 20,
        ],
    ],

    // Defaults for runtime settings. Every key can be overridden from the admin
    // panel (stored in the bot_settings table).
    'defaults' => [
        'bot_enabled' => true,
        'maintenance_message' => 'ربات در حال به‌روزرسانی است. لطفاً کمی بعد دوباره تلاش کنید.',
        'max_upload_bytes' => 10 * 1024 * 1024,
        'max_image_pixels' => 4096 * 4096,
        'max_image_side' => 4096,
        'max_text_length' => 32,
        'max_text_lines' => 3,
        'max_batch_count' => 100,
        'preset_counts' => '1,5,10,20,50,100',
        'daily_limit' => 300,
        'max_queued_jobs_per_user' => 3,
        'rate_limit_per_minute' => 40,
        'default_format' => 'webm',
        'default_duration' => 2.0,
        'webm_fps' => 30,
        'webm_crf' => 30,
        'output_retention_days' => 30,
        'tmp_retention_hours' => 6,
        'download_link_hours' => 48,
        'credits_enabled' => false,
        'default_credits' => 0,
        'allow_tgs' => true,
        'allow_publish' => true,
        'vectorize_max_colors' => 6,
        'support_contact' => '',
        'welcome_text' => '',
        // animated templates
        'templates_enabled' => true,
        'templates_original_colors' => 'admin',   // who may keep a template's original brand colours: admin | all | none
        'templates_demo_color' => '#8B5CF6',      // colour of the gallery previews (never the original brand colour)
        'max_template_batch' => 50,               // templates in one pack generation
        'publish_default_emoji' => '⭐',
        'bot_admin_ids' => '',                    // extra bot admins (comma separated Telegram ids)
    ],
];

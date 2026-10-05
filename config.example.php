<?php
/**
 * Copy this file to config.php and fill in your values.
 * config.php is git-ignored so the bot token never ends up in the repository.
 *
 * One bot runs three sections (analysis, banner, signal); /panel opens each one's own settings.
 * The token, admins and timezone below are shared; everything else is per section.
 */
return [
    // Token from @BotFather
    'bot_token' => 'PUT_YOUR_BOT_TOKEN_HERE',

    // Numeric Telegram IDs of admins (no quota limits, can use admin commands)
    'admin_ids' => [6595849261],

    // Random string; Telegram sends it back in a header so webhook.php can verify requests
    'webhook_secret' => 'change-this-to-a-long-random-string',

    // Public HTTPS address of bot.php (the single webhook for all three bots)
    'webhook_url' => 'https://example.com/bot/bot.php',

    'timezone' => 'Asia/Tehran',

    // Optional proxy for all outgoing requests (Telegram, exchanges, Gemini), e.g. 'socks5h://127.0.0.1:1080'
    'proxy' => '',

    // Days analysis is available, PHP date('w') numbering: 0=Sunday ... 6=Saturday
    'analysis_days' => [6, 0],

    // How many analyses each user gets per period ('week' starts on Saturday, 'day', or 'lifetime')
    'quota_per_user' => 2,
    'quota_period' => 'week',

    // Also answer in a private chat with the bot (not only in the channel's direct messages)
    'allow_private_chat' => true,

    'brand' => [
        'name' => '',
        // Shown at the end of the caption (leave empty to hide), e.g. '@yourchannel'
        'handle' => '',
        'accent' => '#7C5CFF',
    ],

    'market' => [
        'quote' => 'USDT',
        'default_timeframe' => '4h',
        // Tried in order until one answers
        'providers' => ['binance', 'binance_vision', 'bybit', 'okx', 'kucoin'],
        // true = synthetic candles (for testing without internet access)
        'demo' => false,
    ],

    // 🖼 Banner bot (scheduled market banners). Everything else is set in /panel → ربات بنر.
    'banner' => [
        'brand' => 'NIKTO CRYPTO',
        'coinglass_key' => '',
        'coingecko_key' => '',
        // Optional proxy only for market data requests of the banner bot
        'data_proxy' => '',
    ],

    // 📡 Signal bot: optional overrides of modules/signal/env.example.php, e.g. 'CARD_BRAND' => 'MY BRAND'.
    // Most options can also be changed in /panel → ربات سیگنال → اتومات (the panel wins).
    'signal' => [
        'CARD_BRAND' => 'AUTO TRADE MARKET',
    ],

    // Google Gemini reviews each analysis and writes the Persian summary and reasons
    'gemini' => [
        'api_key' => '',
        'model' => 'gemini-3.5-flash',
        // Optional proxy only for Gemini (e.g. if Google says "User location is not supported")
        'proxy' => '',
    ],
];

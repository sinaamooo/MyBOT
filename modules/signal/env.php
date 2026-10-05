<?php
/**
 * Signal module settings.
 *  - Defaults come from env.example.php (every option documented there).
 *  - Shared values (bot token, admins, timezone) come from the main config.php of the hub.
 *  - Optional overrides: add a 'signal' => ['KEY' => 'value', ...] block to the main config.php.
 * Settings changed from the panel (/panel → سیگنال) are stored in the module database and win over these.
 */

$defaults = require __DIR__ . '/env.example.php';

$hubConfigFile = dirname(__DIR__, 2) . '/config.php';
$hub = function_exists('app_config') ? app_config() : (is_file($hubConfigFile) ? (require $hubConfigFile) : []);
$hub = is_array($hub) ? $hub : [];

// Keep the module data inside the hub's protected data folder (same path for webhook and cron).
$hubStorage = rtrim((string) ($hub['storage_dir'] ?? ''), '/') ?: dirname(__DIR__, 2) . '/storage';

$shared = [
    'TELEGRAM_BOT_TOKEN' => (string) ($hub['bot_token'] ?? ''),
    // Same secret as the hub, so modules/signal/bot.php cannot be called directly without it
    'TELEGRAM_WEBHOOK_SECRET' => (string) ($hub['webhook_secret'] ?? '') ?: bin2hex(random_bytes(16)),
    'ADMIN_IDS' => implode(',', array_map('intval', (array) ($hub['admin_ids'] ?? []))),
    'APP_TIMEZONE' => (string) ($hub['timezone'] ?? 'Asia/Tehran'),
    'STORAGE_DIR' => $hubStorage . '/signal',
];

$own = isset($hub['signal']) && is_array($hub['signal']) ? $hub['signal'] : [];

return array_merge($defaults, $shared, array_map('strval', $own));

<?php
/**
 * Telegram webhook endpoint. Point the bot's webhook here (tools/set_webhook.php).
 */
require __DIR__ . '/src/bootstrap.php';

App\Entry::webhook(app_config());

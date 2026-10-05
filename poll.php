<?php
/**
 * Long-polling runner (for a VPS or local testing, instead of the webhook):
 *   php poll.php                   run until stopped (Ctrl+C)
 *   php poll.php --delete-webhook  remove an existing webhook first (polling does not work while one is set)
 */
require __DIR__ . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
App\Entry::poll(app_config(), in_array('--delete-webhook', $argv, true));

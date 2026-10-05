<?php
/**
 * Checks the server setup: php tools/check.php
 */
require dirname(__DIR__) . '/src/bootstrap.php';

// Web access (for hosts without SSH) needs ?key=<webhook_secret>
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals((string) app_config()['webhook_secret'], (string) ($_GET['key'] ?? ''))) {
        http_response_code(403);
        exit('forbidden');
    }
}

echo implode("\n", App\Entry::check(app_config())), "\n";

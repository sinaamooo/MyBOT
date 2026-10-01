<?php
/**
 * Numbix — نامبیکس
 * Front controller: every request (except real files) lands here.
 */

// Used by the installer to detect whether URL rewriting works on this host.
if (str_ends_with(rtrim((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'), '/rewrite-check')) {
    header('Content-Type: text/plain');
    exit('NUMBIX-REWRITE-OK');
}

require __DIR__ . '/app/bootstrap.php';

$router = new Router();
require APP_PATH . '/routes.php';

// Maintenance mode: admins and a few system paths still work.
if (setting('maintenance') === '1' && !is_admin()) {
    $p = Router::currentPath();
    if (!preg_match('#^/(login|admin|api|cron|payment|logout)#', $p)) {
        http_response_code(503);
        echo view('errors/maintenance', ['title' => 'در حال بروزرسانی'], 'blank');
        exit;
    }
}

$router->dispatch();

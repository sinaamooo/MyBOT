<?php

const NBX_VERSION = '1.0.0';
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', __DIR__);
define('STORAGE_PATH', BASE_PATH . '/storage');

spl_autoload_register(static function (string $class): void {
    foreach (['core', 'services', 'controllers', 'controllers/admin'] as $dir) {
        $file = APP_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

require APP_PATH . '/core/helpers.php';
require APP_PATH . '/core/jdate.php';

$configFile = BASE_PATH . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Numbix is not installed yet. Open /install in your browser.\n");
        exit(1);
    }
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/.');
    header('Location: ' . $base . '/install/');
    exit;
}

$GLOBALS['__config'] = require $configFile;
date_default_timezone_set((string)config('app.timezone', 'Asia/Tehran'));
mb_internal_encoding('UTF-8');

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-errors.log');

set_exception_handler(static function (Throwable $e): void {
    log_error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    $msg = config('app.debug') ? $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine() : '';
    if (is_ajax()) {
        json(['ok' => false, 'message' => $msg ?: 'خطای داخلی سرور'], 500);
    }
    try {
        echo view('errors/error', ['code' => 500, 'message' => $msg, 'title' => 'خطای سرور'], 'blank');
    } catch (Throwable) {
        echo '<h1>500</h1><p>' . e($msg) . '</p>';
    }
});

DB::connect(config('db'));

if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('nbx_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() ?: '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    $GLOBALS['__old'] = $_SESSION['_old'] ?? [];
    unset($_SESSION['_old']);

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // "Poor man's cron": keeps automation running even if the host has no cron job.
    register_shutdown_function(static function (): void {
        try {
            Automation::maybeTick();
        } catch (Throwable $e) {
            log_error('auto-cron: ' . $e->getMessage());
        }
    });
}

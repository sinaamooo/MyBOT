<?php
/**
 * Cron entry for the banner scheduler and the signal worker. Run it every minute:
 *
 *   /usr/local/bin/php /home/USER/public_html/Dina/cron.php
 *
 *   php cron.php           banner scheduler, then signal worker (~50 s)
 *   php cron.php banner    only the banner scheduler
 *   php cron.php signal    only the signal worker
 *
 * The analysis bot needs no cron.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("cron.php runs from the command line (cPanel → Cron Jobs).\n");
}

$hub = __DIR__ . '/bot.php';
if (!defined('HUB_LIBRARY')) {
    define('HUB_LIBRARY', true);
}
require is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : $hub;

$config = app_config();
$job = $argv[1] ?? 'all';
@file_put_contents(app_storage() . '/cron.last', date('Y-m-d H:i:s') . ' ' . $job);

if (($job === 'all' || $job === 'banner') && App\Modules::installed(App\Modules::BANNER)) {
    try {
        App\Modules::bannerTick($config);
    } catch (Throwable $e) {
        app_log('cron: banner failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        fwrite(STDERR, 'banner: ' . $e->getMessage() . "\n");
    }
}

if (($job === 'all' || $job === 'signal') && App\Modules::installed(App\Modules::SIGNAL)) {
    try {
        App\Modules::signalWorker();
    } catch (Throwable $e) {
        app_log('cron: signal failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        fwrite(STDERR, 'signal: ' . $e->getMessage() . "\n");
    }
}

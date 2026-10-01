<?php
/**
 * Numbix automation runner.
 *
 * cPanel / DirectAdmin cron job (every 5 minutes):
 *   /usr/local/bin/php /home/USER/public_html/cron.php >/dev/null 2>&1
 *
 * Or, with a web-cron service:  https://your-site.com/cron/<cron_key>
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/app/bootstrap.php';

@set_time_limit(300);
foreach (Automation::run() as $line) {
    echo $line, PHP_EOL;
}

<?php
// The installer (/install) writes config.php automatically.
// Copy this file to config.php only if you want to configure Numbix by hand.
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'numbix',
        'user' => 'root',
        'pass' => '',
    ],
    'app' => [
        'url' => 'https://example.com',   // full site address, no trailing slash
        'key' => 'change-me-to-a-long-random-string',
        'debug' => false,
        'timezone' => 'Asia/Tehran',
        'pretty_urls' => true,             // false if mod_rewrite is not available
    ],
    'cron_key' => 'change-me',
];

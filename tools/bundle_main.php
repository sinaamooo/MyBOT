<?php
// Entry point of the single-file build (appended by tools/build.php).

$config = app_config();

if (PHP_SAPI === 'cli') {
    $cmd = $argv[1] ?? '';
    switch ($cmd) {
        case 'poll':
            App\Entry::poll($config, in_array('--delete-webhook', $argv, true));
            break;
        case 'check':
            echo implode("\n", App\Entry::check($config)), "\n";
            break;
        case 'setwebhook':
            echo App\Entry::setWebhook($config, $argv[2] ?? (string) ($config['webhook_url'] ?? '')), "\n";
            break;
        case 'demo':
            if (in_array('--demo', $argv, true)) {
                $config['market']['demo'] = true;
            }
            $r = (new App\AnalysisService($config))->analyze(strtoupper($argv[2] ?? 'BTC'), $argv[3] ?? '4h');
            echo $r['caption'], "\n\n", $r['extra'] !== '' ? $r['extra'] . "\n\n" : '', 'Image: ', $r['image'], "\n";
            break;
        default:
            echo "php bot.php check | setwebhook <url> | poll [--delete-webhook] | demo BTC 4h [--demo]\n";
    }
    exit;
}

// https://your-domain/path/bot.php?setup=<webhook_secret>  -> checks the server and sets the webhook to this file
if (isset($_GET['setup'])) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals((string) ($config['webhook_secret'] ?? ''), (string) $_GET['setup'])) {
        http_response_code(403);
        exit('forbidden');
    }
    $url = App\Entry::selfUrl();
    echo "Webhook URL: $url\n\n";
    echo implode("\n", App\Entry::check($config)), "\n\n";
    echo App\Entry::setWebhook($config, $url), "\n";
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    App\Entry::webhook($config);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "Bot is running.\n";

<?php
/**
 * Renders a sample analysis without Telegram.
 *   php tools/demo.php BTC 4h          (uses the market providers from config)
 *   php tools/demo.php BTC 4h --demo   (synthetic candles, no internet needed)
 * Output: storage/out/<SYMBOL>_<TF>.png and the caption text.
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

$config = app_config();
$argv = $argv ?? [];
$base = strtoupper($argv[1] ?? ($_GET['symbol'] ?? 'BTC'));
$tf = $argv[2] ?? ($_GET['tf'] ?? $config['market']['default_timeframe']);
if (in_array('--demo', $argv, true) || isset($_GET['demo'])) {
    $config['market']['demo'] = true;
}

$service = new App\AnalysisService($config);
$result = $service->analyze($base, $tf);
echo $result['caption'], "\n\n";
if ($result['extra'] !== '') {
    echo "---- second message ----\n", $result['extra'], "\n\n";
}
echo 'Image: ', $result['image'], "\n";

<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Data\Mock;
use Nikto\Jobs\Registry;

$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $arg, $m)) {
        $args[$m[1]] = $m[2] ?? '1';
    }
}

if (isset($args['mock'])) {
    Mock::enable();
    echo "🧪 حالت داده‌ی نمونه فعال است\n";
}

$outDir = $args['out'] ?? (APP_STORAGE . '/cards');
if (!is_dir($outDir)) {
    @mkdir($outDir, 0775, true);
}

$keys = isset($args['job']) ? [$args['job']] : Registry::keys();

foreach ($keys as $key) {
    $job = Registry::refresh($key);
    if ($job === null) {
        fwrite(STDERR, "❌ کار ناشناخته: {$key}\n");
        continue;
    }
    if (isset($args['theme'])) {
        $job->setTheme($args['theme']);
    }

    $start = microtime(true);
    try {
        $data = $job->fetch();
        if ($data === null) {
            fwrite(STDERR, "❌ {$key}: داده‌ای دریافت نشد (اینترنت یا API)\n");
            continue;
        }
        $path = rtrim($outDir, '/') . '/' . $key . '.png';
        $job->card($data)->save($path);
        printf(
            "✅ %-10s → %s  (%.2f ثانیه، %s کیلوبایت، تم %s)\n",
            $key,
            $path,
            microtime(true) - $start,
            number_format(filesize($path) / 1024, 1),
            $job->theme()
        );
        if (isset($args['caption'])) {
            echo "── کپشن ──\n" . strip_tags($job->renderCaption($data)) . "\n──────────\n";
        }
    } catch (\Throwable $e) {
        fwrite(STDERR, "❌ {$key}: " . $e->getMessage() . "\n");
    }
}

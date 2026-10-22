<?php

declare(strict_types=1);

/*
 * Signed, expiring download links for generated files
 * (download.php?f=<file id>&u=<user id>&e=<expires>&s=<hmac>).
 * The HMAC (APP_KEY) binds file, owner and expiry; the file row must still
 * belong to that user and exist. Files are streamed from private storage,
 * never exposed by path.
 */

use EmojiMaker\App;
use EmojiMaker\Delivery\DownloadLinks;

require dirname(__DIR__) . '/app/autoload.php';

$app = App::boot();
$app->registerErrorHandlers(false);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('Cache-Control: private, no-store');

$fail = static function (int $code, string $message): never {
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>دانلود</title><body style="font-family:Tahoma,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0">'
        . '<div style="max-width:420px;padding:24px;text-align:center">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</div></body></html>';
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    $fail(405, 'درخواست نامعتبر است.');
}
if (!$app->hasKey() || !$app->dbAvailable()) {
    $fail(503, 'سرویس دانلود موقتاً در دسترس نیست.');
}
$links = new DownloadLinks($app->crypto(), $app->url());
$target = $links->verify($_GET);
if ($target === null) {
    $fail($links->verify($_GET, true) !== null ? 410 : 403, $links->isExpired($_GET)
        ? 'مهلت این لینک تمام شده است. از داخل ربات لینک تازه بگیرید.'
        : 'لینک دانلود نامعتبر است.');
}
[$fileId, $userId] = $target;
$row = $app->files()->find($fileId);
if ($row === null || $row['deleted_at'] !== null || (int) $row['user_id'] !== $userId) {
    $fail(404, 'این فایل دیگر موجود نیست (خروجی‌ها پس از مدتی پاک می‌شوند).');
}
try {
    $path = $app->storage()->path((string) $row['path']);
} catch (\InvalidArgumentException) {
    $fail(404, 'این فایل دیگر موجود نیست.');
}
if (!is_file($path) || !is_readable($path)) {
    $fail(404, 'این فایل دیگر موجود نیست.');
}

$name = (string) $row['filename'];
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'emoji';
$mime = preg_match('~^[a-z]+/[a-z0-9.+-]+$~i', (string) $row['mime']) ? (string) $row['mime'] : 'application/octet-stream';
$size = (int) filesize($path);
header('Content-Type: ' . $mime);
header('Content-Length: ' . $size);
header("Content-Disposition: attachment; filename=\"{$ascii}\"; filename*=UTF-8''" . rawurlencode($name));
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
while (ob_get_level() > 0) {
    ob_end_clean();
}
$fh = fopen($path, 'rb');
if ($fh === false) {
    http_response_code(500);
    exit;
}
while (!feof($fh)) {
    echo fread($fh, 65536);
    flush();
}
fclose($fh);

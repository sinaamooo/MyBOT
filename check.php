<?php
/**
 * عیب‌یابی از طریق مرورگر.
 *
 *   https://your-domain/nikto/check.php?key=<webhook_secret>
 *
 * این فایل عمداً با نحو قدیمی PHP نوشته شده تا روی نسخه‌های قدیمی هم
 * اجرا شود و بتواند پیام «نسخه PHP قدیمی است» را نشان دهد.
 */

header('Content-Type: text/plain; charset=utf-8');

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo "NIKTO CRYPTO BOT\n";
    echo str_repeat('=', 46) . "\n\n";
    echo "[ !! ] نسخه PHP این هاست " . PHP_VERSION . " است.\n";
    echo "        این ربات به PHP 8.0 یا بالاتر نیاز دارد.\n\n";
    echo "در پنل هاست (cPanel/DirectAdmin) بخش «Select PHP Version» را باز کنید\n";
    echo "و نسخه را روی 8.1 یا 8.2 بگذارید.\n";
    exit;
}

$bootstrap = __DIR__ . '/src/bootstrap.php';
if (!is_file($bootstrap)) {
    echo "فایل src/bootstrap.php پیدا نشد — به نظر می‌رسد همه‌ی فایل‌ها آپلود نشده‌اند.\n";
    exit;
}
require $bootstrap;

$secret = (string) Nikto\Core\Config::get('webhook_secret', '');
$given = isset($_GET['key']) ? (string) $_GET['key'] : '';

if ($secret === '' || !hash_equals($secret, $given)) {
    http_response_code(403);
    echo "forbidden\n\n";
    echo "آدرس درست:  check.php?key=<webhook_secret>\n";
    echo "مقدار کلید در فایل config.local.php نوشته شده است.\n";
    exit;
}

echo Nikto\Core\Diagnostics::renderText();

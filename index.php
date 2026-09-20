<?php
/**
 * نقطه‌ی ورود ریشه‌ی دامنه.
 *
 * اگر دامنه‌ی شما مستقیماً به پوشه‌ی پروژه اشاره می‌کند، وب‌هوک هم روی
 * https://your-domain/ و هم روی https://your-domain/webhook.php کار می‌کند.
 */
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "NIKTO CRYPTO BOT\n\n";
    echo "PHP " . PHP_VERSION . " — this bot needs PHP 8.0 or newer.\n";
    echo "نسخه PHP هاست را از پنل هاست روی 8.1 یا بالاتر بگذارید.\n";
    exit;
}

require __DIR__ . '/src/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require __DIR__ . '/webhook.php';
    return;
}

header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);
echo \Nikto\Core\PublicUrl::statusPage();

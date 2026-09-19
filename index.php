<?php
/**
 * نقطه‌ی ورود ریشه‌ی دامنه.
 *
 * اگر دامنه‌ی شما مستقیماً به پوشه‌ی پروژه اشاره می‌کند، وب‌هوک هم روی
 * https://your-domain/ و هم روی https://your-domain/webhook.php کار می‌کند.
 */
declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require __DIR__ . '/webhook.php';
    return;
}

header('Content-Type: text/plain; charset=utf-8');
http_response_code(200);
echo "NIKTO CRYPTO BOT is running.\n";

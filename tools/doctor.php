<?php
/**
 * بررسی سلامت نصب:  php tools/doctor.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Settings;
use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Render\Canvas;
use Nikto\Telegram\Api;
use Nikto\Telegram\Channels;

$ok = static fn (string $m) => print("  ✅ $m\n");
$bad = static fn (string $m) => print("  ❌ $m\n");
$warn = static fn (string $m) => print("  ⚠️  $m\n");
$problems = 0;

echo "\n🔎 بررسی سلامت NIKTO CRYPTO BOT\n";
echo str_repeat('─', 52) . "\n";

echo "\n▸ نسخه‌ی PHP و افزونه‌ها\n";
version_compare(PHP_VERSION, '8.1.0', '>=') ? $ok('PHP ' . PHP_VERSION) : ($bad('PHP 8.1+ لازم است، نسخه‌ی فعلی ' . PHP_VERSION) && $problems++);
foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite', 'json'] as $ext) {
    if (extension_loaded($ext)) {
        $ok('افزونه‌ی ' . $ext);
    } else {
        $bad('افزونه‌ی ' . $ext . ' نصب نیست');
        $problems++;
    }
}
if (function_exists('imagettftext')) {
    $ok('پشتیبانی FreeType در GD');
} else {
    $bad('GD بدون FreeType — متن روی تصویر رسم نمی‌شود');
    $problems++;
}

echo "\n▸ فونت‌ها\n";
foreach (['Regular', 'Medium', 'SemiBold', 'Bold', 'ExtraBold', 'Black'] as $weight) {
    $path = APP_ASSETS . '/fonts/Vazirmatn-' . $weight . '.ttf';
    is_file($path) ? $ok('Vazirmatn-' . $weight) : $warn('نبود فونت ' . $weight . ' (از Regular استفاده می‌شود)');
}

echo "\n▸ دسترسی نوشتن\n";
foreach ([APP_DATA, APP_STORAGE . '/cards', APP_STORAGE . '/logs'] as $dir) {
    is_writable($dir) ? $ok($dir) : ($bad('قابل نوشتن نیست: ' . $dir) && $problems++);
}

echo "\n▸ پیکربندی\n";
if (Config::isConfigured()) {
    $ok('توکن ربات تنظیم شده است');
} else {
    $bad('توکن ربات خالی است — config.php را بسازید');
    $problems++;
}
Config::admins() !== [] ? $ok('مدیران: ' . implode(', ', Config::admins())) : $warn('هیچ مدیری تعریف نشده است');

echo "\n▸ دیتابیس\n";
try {
    Db::migrate();
    Registry::all();
    $ok('جداول آماده هستند (' . Settings::get('timezone') . ')');
} catch (\Throwable $e) {
    $bad('خطای دیتابیس: ' . $e->getMessage());
    $problems++;
}

echo "\n▸ رسم تصویر\n";
try {
    $canvas = new Canvas(200, 90, 2, '#101828');
    $canvas->text('آزمایش فارسی ۱۲۳', 190, 30, 18, '#FFFFFF');
    $path = APP_STORAGE . '/cards/doctor-test.png';
    $canvas->savePng($path);
    filesize($path) > 1000 ? $ok('تصویر آزمایشی ساخته شد: ' . $path) : $warn('تصویر خیلی کوچک است');
} catch (\Throwable $e) {
    $bad('خطای رسم: ' . $e->getMessage());
    $problems++;
}

echo "\n▸ اتصال به تلگرام\n";
if (Config::isConfigured()) {
    $me = (new Api())->getMe();
    if ($me['ok'] ?? false) {
        $ok('ربات: @' . ($me['result']['username'] ?? '?') . ' (id: ' . ($me['result']['id'] ?? '?') . ')');
    } else {
        $bad('اتصال ناموفق: ' . ($me['description'] ?? 'نامشخص'));
        $problems++;
    }
}

echo "\n▸ وب‌هوک\n";
$webhookUrl = (string) Config::get('webhook_url', '');
if ($webhookUrl === '') {
    $warn('آدرس وب‌هوک در config.php تنظیم نشده (حالت long polling)');
} else {
    $ok('آدرس تنظیم‌شده: ' . $webhookUrl);
    if (Config::isConfigured()) {
        $info = (new Api())->getWebhookInfo();
        if ($info['ok'] ?? false) {
            $current = (string) ($info['result']['url'] ?? '');
            if ($current === '') {
                $warn('هنوز روی تلگرام ثبت نشده — php tools/webhook-set.php');
            } elseif ($current === $webhookUrl) {
                $ok('روی تلگرام ثبت شده است');
                $pending = (int) ($info['result']['pending_update_count'] ?? 0);
                if ($pending > 0) {
                    $warn($pending . ' آپدیت در صف مانده است');
                }
                if (($info['result']['last_error_message'] ?? '') !== '') {
                    $warn('آخرین خطای تلگرام: ' . $info['result']['last_error_message']);
                }
            } else {
                $warn('آدرس ثبت‌شده فرق دارد: ' . $current);
            }
        }
    }
}

echo "\n▸ کانال‌ها و زمان‌بندی\n";
$channels = Channels::all();
$channels === [] ? $warn('هنوز کانالی ثبت نشده است') : $ok(count($channels) . ' کانال ثبت شده');
foreach (Registry::all() as $key => $job) {
    $next = Scheduler::nextRun($key);
    printf(
        "  %s %-22s %s | کانال: %d | ارسال بعدی: %s\n",
        $job->icon(),
        $job->title(),
        $job->enabled() ? 'فعال  ' : 'خاموش',
        count($job->channels()),
        $next?->format('Y-m-d H:i') ?? '—'
    );
}

echo "\n" . str_repeat('─', 52) . "\n";
echo $problems === 0
    ? "✅ همه‌چیز آماده است.\n\n"
    : "❌ {$problems} مورد نیاز به رسیدگی دارد.\n\n";

exit($problems === 0 ? 0 : 1);

<?php
declare(strict_types=1);

namespace Nikto\Core;

use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Telegram\Api;
use Nikto\Telegram\Channels;

/**
 * عیب‌یابی نصب — هم از خط فرمان و هم از مرورگر قابل اجراست.
 */
final class Diagnostics
{
    /** @return array<int,array{ok:bool|null,label:string,detail:string}> */
    public static function run(): array
    {
        $out = [];
        $add = static function (?bool $ok, string $label, string $detail = '') use (&$out): void {
            $out[] = ['ok' => $ok, 'label' => $label, 'detail' => $detail];
        };

        // ── محیط
        $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
        $add($phpOk, 'نسخه PHP', PHP_VERSION . ($phpOk ? '' : ' — حداقل ۸.۰ لازم است'));

        foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite', 'json', 'zlib'] as $ext) {
            $has = extension_loaded($ext);
            $add($has, 'افزونه ' . $ext, $has ? 'نصب است' : 'نصب نیست');
        }
        $add(function_exists('imagettftext'), 'پشتیبانی FreeType در GD', function_exists('imagettftext') ? 'دارد' : 'ندارد — متن روی تصویر رسم نمی‌شود');

        $limit = (string) ini_get('memory_limit');
        $add(null, 'حافظه PHP', $limit);

        // ── دسترسی نوشتن
        foreach ([APP_DATA => 'پوشه data', APP_STORAGE . '/cards' => 'پوشه storage/cards'] as $dir => $label) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $writable = is_dir($dir) && is_writable($dir);
            $add($writable, $label, $writable ? 'قابل نوشتن' : 'قابل نوشتن نیست — دسترسی 755 یا 775 بدهید');
        }

        // ── پیکربندی
        $token = Config::token();
        $add($token !== '', 'توکن ربات', $token !== '' ? self::mask($token) : 'تنظیم نشده');
        $owner = (int) Config::get('owner_id', 0);
        $add($owner > 0, 'شناسه مدیر', $owner > 0 ? (string) $owner : 'تنظیم نشده');
        $add(null, 'آدرس وب‌هوک در تنظیمات', (string) Config::get('webhook_url', '—'));
        $current = PublicUrl::current();
        if ($current !== '') {
            $add(null, 'آدرس واقعی این فایل', $current);
        }

        // ── دیتابیس
        try {
            Db::migrate();
            Registry::all();
            $channels = count(Channels::all());
            $add(true, 'دیتابیس', 'سالم — ' . $channels . ' کانال ثبت‌شده');
        } catch (\Throwable $e) {
            $add(false, 'دیتابیس', 'خطا: ' . $e->getMessage());
        }

        // ── فونت و لوگو
        $fonts = count(glob(APP_ASSETS . '/fonts/*.ttf') ?: []);
        $logos = count(glob(APP_ASSETS . '/coins/color/*.png') ?: []);
        $add($fonts > 0, 'فونت‌ها', $fonts . ' فایل');
        $add($logos > 0, 'لوگوی ارزها', $logos . ' فایل');

        // ── تلگرام
        if ($token === '') {
            return $out;
        }
        $api = new Api();
        $me = $api->getMe();
        if ($me['ok'] ?? false) {
            $add(true, 'اتصال به تلگرام', '@' . ($me['result']['username'] ?? '?') . ' (id: ' . ($me['result']['id'] ?? '?') . ')');
        } else {
            $add(false, 'اتصال به تلگرام', (string) ($me['description'] ?? 'ناموفق — احتمالاً سرور به api.telegram.org دسترسی ندارد'));
            return $out;
        }

        $info = $api->getWebhookInfo();
        if ($info['ok'] ?? false) {
            $result = $info['result'] ?? [];
            $url = (string) ($result['url'] ?? '');
            $add($url !== '', 'وب‌هوک ثبت‌شده', $url !== '' ? $url : 'ثبت نشده — ربات در حالت پولینگ است');

            if ($url !== '' && $current !== '' && rtrim($url, '/') !== rtrim($current, '/')) {
                $add(false, 'تطابق آدرس', 'آدرس ثبت‌شده با این فایل یکی نیست');
            }

            $pending = (int) ($result['pending_update_count'] ?? 0);
            $add($pending === 0, 'پیام‌های در صف', (string) $pending);

            $lastError = (string) ($result['last_error_message'] ?? '');
            if ($lastError !== '') {
                $when = isset($result['last_error_date']) ? date('Y-m-d H:i', (int) $result['last_error_date']) : '';
                $add(false, 'آخرین خطای تلگرام', $lastError . ($when !== '' ? ' (' . $when . ')' : ''));
            } else {
                $add(true, 'آخرین خطای تلگرام', 'خطایی ثبت نشده');
            }

            $add(null, 'کلید امنیتی وب‌هوک', ($result['has_custom_certificate'] ?? false) ? 'گواهی سفارشی' : 'استاندارد');
        }

        foreach (Registry::all() as $key => $job) {
            $add(null, $job->title(), ($job->enabled() ? 'فعال' : 'خاموش')
                . ' | کانال: ' . count($job->channels())
                . ' | ارسال بعدی: ' . (Scheduler::nextRun($key)?->format('Y-m-d H:i') ?? '—'));
        }

        $heartbeat = Settings::get('scheduler_heartbeat', '');
        $add(
            $heartbeat !== '' && (time() - (int) $heartbeat) < 300,
            'زمان‌بند',
            $heartbeat !== '' ? 'آخرین بررسی ' . date('Y-m-d H:i:s', (int) $heartbeat) : 'هنوز اجرا نشده'
        );

        return $out;
    }

    public static function renderText(): string
    {
        $lines = ['NIKTO CRYPTO BOT — عیب‌یابی', str_repeat('=', 46), ''];
        $problems = 0;

        foreach (self::run() as $row) {
            $mark = match ($row['ok']) {
                true  => '[ OK ]',
                false => '[ !! ]',
                null  => '[ -- ]',
            };
            if ($row['ok'] === false) {
                $problems++;
            }
            $lines[] = sprintf('%s %-28s %s', $mark, $row['label'], $row['detail']);
        }

        $lines[] = '';
        $lines[] = str_repeat('=', 46);
        $lines[] = $problems === 0
            ? 'همه‌چیز سالم است.'
            : $problems . ' مورد نیاز به رسیدگی دارد (خط‌های [ !! ]).';

        return implode("\n", $lines) . "\n";
    }

    private static function mask(string $token): string
    {
        $parts = explode(':', $token, 2);

        return ($parts[0] ?? '') . ':' . substr($parts[1] ?? '', 0, 3) . str_repeat('*', 8);
    }
}

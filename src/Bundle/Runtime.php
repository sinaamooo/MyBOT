<?php
declare(strict_types=1);

namespace Nikto\Bundle;

use Nikto\Core\Config;
use Nikto\Core\Db;
use Nikto\Core\Diagnostics;
use Nikto\Core\Log;
use Nikto\Core\PublicUrl;
use Nikto\Core\Settings;
use Nikto\Jobs\Dispatcher;
use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Telegram\Api;
use Nikto\Telegram\Panel;

/**
 * هسته‌ی نسخه‌ی «دو فایلی».
 *
 * وقتی پروژه با tools/build.php فشرده می‌شود، این کلاس مسیرها را می‌سازد،
 * فونت‌ها و لوگوها را از دل فایل بیرون می‌کشد و ورودی وب/خط فرمان را مدیریت می‌کند.
 */
final class Runtime
{
    public const VERSION = '2.0';

    /** @param array<string,mixed> $config */
    public static function boot(array $config): void
    {
        $token = trim((string) ($config['bot_token'] ?? ''));
        $botId = (int) (strtok($token, ':') ?: 0);
        $root  = (string) ($config['root'] ?? __DIR__);

        // پوشه‌ی داده برای هر ربات جداست تا چند ربات روی یک هاست قاطی نشوند
        $data = rtrim($root, '/') . '/nikto-' . ($botId ?: 'bot') . '-data';

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $root);
            define('APP_SRC', $data);
            define('APP_DATA', $data);
            define('APP_STORAGE', $data . '/storage');
            define('APP_ASSETS', $data . '/assets');
            define('APP_FIXTURES', $data . '/fixtures');
        }

        foreach ([APP_DATA, APP_STORAGE, APP_STORAGE . '/cards', APP_STORAGE . '/logs', APP_ASSETS, APP_FIXTURES] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }
        self::protect($data);
        self::extractAssets();

        mb_internal_encoding('UTF-8');
        date_default_timezone_set('UTC');

        Config::boot();
        foreach ([
            'bot_token'      => $token,
            'bot_id'         => $botId,
            'owner_id'       => (int) ($config['owner_id'] ?? 0),
            'admins'         => (array) ($config['admins'] ?? []),
            'webhook_url'    => (string) ($config['webhook_url'] ?? ''),
            'webhook_secret' => (string) ($config['webhook_secret'] ?? ''),
            'http_proxy'     => (string) ($config['http_proxy'] ?? ''),
            'db_path'        => $data . '/bot.sqlite',
            'timezone'       => (string) ($config['timezone'] ?? 'Asia/Tehran'),
            'brand'          => (string) ($config['brand'] ?? 'NIKTO CRYPTO'),
        ] as $key => $value) {
            Config::set($key, $value);
        }

        $admins = Config::get('admins');
        $admins = is_array($admins) ? $admins : [];
        $owner = (int) Config::get('owner_id', 0);
        if ($owner > 0) {
            $admins[] = $owner;
        }
        Config::set('admins', array_values(array_unique(array_filter(array_map('intval', $admins)))));
    }

    /** جلوگیری از دسترسی وب به پوشه‌ی داده */
    private static function protect(string $dir): void
    {
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
        $index = $dir . '/index.html';
        if (!is_file($index)) {
            @file_put_contents($index, '');
        }
    }

    /** نوشتن فونت‌ها، لوگوها و داده‌های نمونه روی دیسک (فقط بار اول) */
    private static function extractAssets(): void
    {
        if (!class_exists(Assets::class)) {
            return; // اجرای عادی پروژه، نه نسخه‌ی فشرده
        }
        $marker = APP_ASSETS . '/.version';
        if (is_file($marker) && trim((string) file_get_contents($marker)) === Assets::VERSION) {
            return;
        }

        foreach (Assets::files() as $relative => $base64) {
            $path = APP_DATA . '/' . $relative;
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $bytes = base64_decode($base64, true);
            if ($bytes !== false) {
                @file_put_contents($path, $bytes);
            }
        }
        @file_put_contents($marker, Assets::VERSION);
    }

    // ------------------------------------------------------------ ورودی وب

    public static function handleWeb(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if ($method !== 'POST') {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(200);

            // صفحه‌ی عیب‌یابی:  ...nikto-bot.php?check=<webhook_secret>
            $secret = (string) Config::get('webhook_secret', '');
            $given = (string) ($_GET['check'] ?? '');
            if ($given !== '' && $secret !== '' && hash_equals($secret, $given)) {
                echo Diagnostics::renderText();
                return;
            }

            echo PublicUrl::statusPage(
                self::VERSION,
                'php ' . basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'nikto-bot.php')) . ' webhook'
            );
            return;
        }

        $secret = (string) Config::get('webhook_secret', '');
        if ($secret !== '' && ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== $secret) {
            http_response_code(403);
            echo 'forbidden';
            return;
        }

        $update = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($update)) {
            http_response_code(400);
            echo 'bad request';
            return;
        }

        http_response_code(200);
        echo 'ok';
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }

        try {
            Db::migrate();
            Registry::all();
            (new Panel(new Api()))->handleUpdate($update);
        } catch (\Throwable $e) {
            Log::error('Webhook handling failed', ['error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------- ورودی خط فرمان

    /** @param string[] $argv */
    public static function handleCli(array $argv): int
    {
        $command = $argv[1] ?? 'poll';
        $arg = $argv[2] ?? '';

        if (!Config::isConfigured()) {
            fwrite(STDERR, "❌ توکن ربات تنظیم نشده است (بالای همین فایل، مقدار bot_token).\n");
            return 1;
        }

        Db::migrate();
        Registry::all();

        return match ($command) {
            'poll', 'start' => self::poll(),
            'webhook'       => self::webhook(true),
            'webhook:off'   => self::webhook(false),
            'webhook:info'  => self::webhookInfo(),
            'send'          => self::send($arg),
            'preview'       => self::preview($arg),
            'doctor', 'check' => self::doctor(),
            'cron', 'tick'  => self::tick(),
            'cron:loop'     => self::cronLoop(),
            default         => self::usage($command),
        };
    }

    private static function usage(string $command): int
    {
        $file = basename($_SERVER['SCRIPT_NAME'] ?? 'nikto-bot.php');
        echo "دستور ناشناخته: {$command}\n\n";
        echo "روش استفاده:\n";
        echo "  php {$file}                  روشن‌کردن ربات (long polling)\n";
        echo "  php {$file} webhook          ثبت وب‌هوک روی تلگرام\n";
        echo "  php {$file} webhook:info     وضعیت وب‌هوک\n";
        echo "  php {$file} webhook:off      حذف وب‌هوک\n";
        echo "  php {$file} send prices      ارسال فوری یک کارت\n";
        echo "  php {$file} preview          ساخت پیش‌نمایش کارت‌ها روی دیسک\n";
        echo "  php {$file} doctor           بررسی سلامت\n";
        echo "  php {$file} cron             یک بار بررسی زمان‌بندی\n";

        return 1;
    }

    private static function poll(): int
    {
        $api = new Api();
        $me = $api->getMe();
        if (!($me['ok'] ?? false)) {
            fwrite(STDERR, '❌ اتصال به تلگرام ناموفق بود: ' . ($me['description'] ?? '') . "\n");
            return 1;
        }
        $api->deleteWebhook();

        echo '✅ ربات @' . ($me['result']['username'] ?? '?') . " روشن شد. (Ctrl+C برای توقف)\n";
        $panel = new Panel($api);
        $offset = 0;
        $running = true;

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $stop = static function () use (&$running): void { $running = false; };
            pcntl_signal(SIGTERM, $stop);
            pcntl_signal(SIGINT, $stop);
        }

        while ($running) {
            try {
                foreach ($api->getUpdates($offset, 30) as $update) {
                    $offset = max($offset, (int) ($update['update_id'] ?? 0) + 1);
                    try {
                        $panel->handleUpdate($update);
                    } catch (\Throwable $e) {
                        Log::error('Update handling failed', ['error' => $e->getMessage()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Polling loop error', ['error' => $e->getMessage()]);
                sleep(3);
            }
        }

        return 0;
    }

    private static function webhook(bool $enable): int
    {
        $api = new Api();
        if (!$enable) {
            $res = $api->deleteWebhook();
            echo ($res['ok'] ?? false) ? "✅ وب‌هوک حذف شد.\n" : '❌ ' . ($res['description'] ?? '') . "\n";
            return ($res['ok'] ?? false) ? 0 : 1;
        }

        $url = (string) Config::get('webhook_url', '');
        if ($url === '') {
            fwrite(STDERR, "❌ مقدار webhook_url بالای فایل تنظیم نشده است.\n");
            return 1;
        }
        $res = $api->setWebhook($url, (string) Config::get('webhook_secret', ''));
        if ($res['ok'] ?? false) {
            echo "✅ وب‌هوک ثبت شد:\n   {$url}\n";
            return 0;
        }
        echo '❌ ' . ($res['description'] ?? 'خطا') . "\n";

        return 1;
    }

    private static function webhookInfo(): int
    {
        $res = (new Api())->getWebhookInfo();
        echo json_encode($res['result'] ?? $res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        return 0;
    }

    private static function send(string $jobKey): int
    {
        if (!in_array($jobKey, Registry::keys(), true)) {
            echo 'کارهای موجود: ' . implode(', ', Registry::keys()) . "\n";
            return 1;
        }
        $result = Dispatcher::run($jobKey);
        echo ($result['ok'] ? '✅ ' : '❌ ') . $result['message'] . "\n";

        return $result['ok'] ? 0 : 1;
    }

    private static function preview(string $jobKey): int
    {
        $keys = $jobKey !== '' ? [$jobKey] : Registry::keys();
        foreach ($keys as $key) {
            $result = Dispatcher::build($key);
            echo ($result['ok'] ?? false)
                ? sprintf("✅ %-10s → %s\n", $key, $result['path'])
                : sprintf("❌ %-10s → %s\n", $key, $result['message'] ?? '');
        }

        return 0;
    }

    private static function tick(): int
    {
        Settings::set('scheduler_heartbeat', (string) time());
        foreach (Scheduler::tick() as $item) {
            printf("[%s] %s → %s\n", date('H:i:s'), $item['job'], $item['result']['message'] ?? '');
        }

        return 0;
    }

    /** اجرای دائمی زمان‌بند (هر دقیقه یک بار) */
    private static function cronLoop(): int
    {
        $running = true;
        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $stop = static function () use (&$running): void { $running = false; };
            pcntl_signal(SIGTERM, $stop);
            pcntl_signal(SIGINT, $stop);
        }

        echo '⏰ زمان‌بند روشن شد — منطقه‌ی زمانی: ' . Settings::get('timezone') . "\n";
        while ($running) {
            self::tick();
            $sleep = 60 - (int) date('s');
            for ($i = 0; $i < $sleep && $running; $i++) {
                sleep(1);
            }
        }

        return 0;
    }

    /** بررسی سلامت نصب در نسخه‌ی دو فایلی */
    private static function doctor(): int
    {
        echo "\n" . Diagnostics::renderText() . "\n";

        return 0;
    }

    /** نسخه‌ی قدیمی بررسی سلامت (دیگر استفاده نمی‌شود) */
    private static function doctorLegacy(): int
    {
        $problems = 0;
        echo "\n🔎 بررسی سلامت — NIKTO CRYPTO BOT v" . self::VERSION . "\n" . str_repeat('─', 46) . "\n";

        printf("  PHP %s%s\n", PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>=') ? ' ✅' : ' ❌');
        $problems += version_compare(PHP_VERSION, '8.1.0', '>=') ? 0 : 1;

        foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite'] as $ext) {
            $has = extension_loaded($ext);
            printf("  افزونه %-12s %s\n", $ext, $has ? '✅' : '❌');
            $problems += $has ? 0 : 1;
        }
        printf("  FreeType %s\n", function_exists('imagettftext') ? '✅' : '❌');

        printf("  پوشه‌ی داده: %s %s\n", APP_DATA, is_writable(APP_DATA) ? '✅' : '❌');
        $problems += is_writable(APP_DATA) ? 0 : 1;

        $fonts = glob(APP_ASSETS . '/fonts/*.ttf') ?: [];
        $logos = glob(APP_ASSETS . '/coins/color/*.png') ?: [];
        printf("  فونت‌ها: %d  |  لوگوها: %d\n", count($fonts), count($logos));
        $problems += count($fonts) > 0 ? 0 : 1;

        $me = (new Api())->getMe();
        if ($me['ok'] ?? false) {
            printf("  تلگرام: @%s ✅\n", $me['result']['username'] ?? '?');
        } else {
            printf("  تلگرام: ❌ %s\n", $me['description'] ?? '');
            $problems++;
        }

        $url = (string) Config::get('webhook_url', '');
        if ($url !== '') {
            $info = (new Api())->getWebhookInfo();
            $current = (string) ($info['result']['url'] ?? '');
            printf("  وب‌هوک: %s\n", $current === $url ? 'ثبت شده ✅' : ($current === '' ? 'ثبت نشده ⚠️' : 'آدرس متفاوت: ' . $current));
        }

        foreach (Registry::all() as $key => $job) {
            printf(
                "  %s %-22s %s | کانال: %d | بعدی: %s\n",
                $job->icon(),
                $job->title(),
                $job->enabled() ? 'فعال ' : 'خاموش',
                count($job->channels()),
                Scheduler::nextRun($key)?->format('Y-m-d H:i') ?? '—'
            );
        }

        echo str_repeat('─', 46) . "\n";
        echo $problems === 0 ? "✅ همه‌چیز آماده است.\n\n" : "❌ {$problems} مورد نیاز به رسیدگی دارد.\n\n";

        return $problems === 0 ? 0 : 1;
    }

    /** اجرای زمان‌بند از طریق وب (برای هاست‌هایی که فقط کرون اینترنتی دارند) */
    public static function handleWebCron(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $secret = (string) Config::get('webhook_secret', '');
        $given = (string) ($_GET['key'] ?? $_GET['secret'] ?? '');

        if ($secret === '' || !hash_equals($secret, $given)) {
            http_response_code(403);
            echo "forbidden\n";
            return;
        }

        try {
            Db::migrate();
            Registry::all();
            Settings::set('scheduler_heartbeat', (string) time());
            $results = Scheduler::tick();
            echo 'ok ' . count($results) . " job(s)\n";
            foreach ($results as $item) {
                echo $item['job'] . ': ' . ($item['result']['message'] ?? '') . "\n";
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            Log::error('Web cron failed', ['error' => $e->getMessage()]);
            echo "error\n";
        }
    }
}

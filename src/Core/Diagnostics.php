<?php
declare(strict_types=1);

namespace Nikto\Core;

use Nikto\Jobs\Registry;
use Nikto\Jobs\Scheduler;
use Nikto\Telegram\Api;
use Nikto\Telegram\Channels;

final class Diagnostics
{
    private const DATA_SOURCES = [
        'منبع: Binance Futures'   => 'https://fapi.binance.com/fapi/v1/ping',
        'منبع: Binance Spot'      => 'https://api.binance.com/api/v3/ping',
        'منبع: Binance (mirror)'  => 'https://data-api.binance.vision/api/v3/ping',
        'منبع: Bybit'             => 'https://api.bybit.com/v5/market/time',
        'منبع: OKX'               => 'https://www.okx.com/api/v5/public/time',
        'منبع: Gate.io'           => 'https://api.gateio.ws/api/v4/spot/time',
        'منبع: MEXC'              => 'https://api.mexc.com/api/v3/ping',
        'منبع: MEXC Futures'      => 'https://contract.mexc.com/api/v1/contract/ping',
        'منبع: KuCoin'            => 'https://api.kucoin.com/api/v1/timestamp',
        'منبع: KuCoin Futures'    => 'https://api-futures.kucoin.com/api/v1/timestamp',
        'منبع: Nobitex'           => 'https://api.nobitex.ir/market/stats?srcCurrency=btc&dstCurrency=usdt',
        'منبع: Bitget'            => 'https://api.bitget.com/api/v2/public/time',
        'منبع: BingX'             => 'https://open-api.bingx.com/openApi/swap/v2/server/time',
        'منبع: CoinEx'            => 'https://api.coinex.com/v2/time',
        'منبع: HTX'               => 'https://api.hbdm.com/api/v1/timestamp',
        'منبع: Hyperliquid'       => ['https://api.hyperliquid.xyz/info', '{"type":"l2Book","coin":"BTC"}'],
        'منبع: CoinGecko'         => 'https://api.coingecko.com/api/v3/ping',
        'منبع: ترس و طمع'          => 'https://api.alternative.me/fng/?limit=1&format=json',
        'منبع: ForexFactory'      => 'https://nfs.faireconomy.media/ff_calendar_thisweek.json',
    ];

    public static function run(): array
    {
        $out = [];
        $add = static function (?bool $ok, string $label, string $detail = '') use (&$out): void {
            $out[] = ['ok' => $ok, 'label' => $label, 'detail' => $detail];
        };

        $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
        $add($phpOk, 'نسخه PHP', PHP_VERSION . ($phpOk ? '' : ' — حداقل ۸.۰ لازم است'));

        foreach (['curl', 'gd', 'mbstring', 'pdo_sqlite', 'json', 'zlib'] as $ext) {
            $has = extension_loaded($ext);
            $add($has, 'افزونه ' . $ext, $has ? 'نصب است' : 'نصب نیست');
        }
        $add(function_exists('imagettftext'), 'پشتیبانی FreeType در GD', function_exists('imagettftext') ? 'دارد' : 'ندارد — متن روی تصویر رسم نمی‌شود');

        $limit = (string) ini_get('memory_limit');
        $add(null, 'حافظه PHP', $limit);

        foreach ([APP_DATA => 'پوشه data', APP_STORAGE . '/cards' => 'پوشه storage/cards'] as $dir => $label) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $writable = is_dir($dir) && is_writable($dir);
            $add($writable, $label, $writable ? 'قابل نوشتن' : 'قابل نوشتن نیست — دسترسی 755 یا 775 بدهید');
        }

        $token = Config::token();
        $validToken = Config::isConfigured();
        $add($validToken, 'توکن ربات', $validToken ? self::mask($token) : ($token === '' ? 'تنظیم نشده' : 'نامعتبر — توکن را از BotFather کپی کنید'));
        $owner = (int) Config::get('owner_id', 0);
        $add($owner > 0, 'شناسه مدیر', $owner > 0 ? (string) $owner : 'تنظیم نشده');
        $add(null, 'آدرس وب‌هوک در تنظیمات', (string) Config::get('webhook_url', '—'));
        $current = PublicUrl::current();
        if ($current !== '') {
            $add(null, 'آدرس واقعی این فایل', $current);
        }

        try {
            Db::migrate();
            Registry::all();
            $channels = count(Channels::all());
            $add(true, 'دیتابیس', 'سالم — ' . $channels . ' کانال ثبت‌شده');
        } catch (\Throwable $e) {
            $add(false, 'دیتابیس', 'خطا: ' . $e->getMessage());
        }

        $fonts = count(glob(APP_ASSETS . '/fonts/*.ttf') ?: []);
        $logos = count(glob(APP_ASSETS . '/coins/color/*.png') ?: []);
        $add($fonts > 0, 'فونت‌ها', $fonts . ' فایل');
        $add($logos > 0, 'لوگوی ارزها', $logos . ' فایل');

        if (!$validToken) {
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

        $proxy = Http::proxy();
        $add(null, 'پراکسی داده', $proxy === '' ? 'ندارد (اتصال مستقیم)' : (string) preg_replace('#//[^@/]*@#', '//***@', $proxy));

        if (\Nikto\Data\CoinGeckoApi::enabled()) {
            Http::resetFailures();
            $ping = \Nikto\Data\CoinGeckoApi::get('/ping', [], 10);
            $add(
                is_array($ping),
                'کلید CoinGecko',
                is_array($ping) ? 'کلید معتبر است' : (Http::explainFailures(1) !== '' ? ltrim(Http::explainFailures(1), '• ') : 'پاسخی نیامد')
            );
        } else {
            $add(null, 'کلید CoinGecko', 'تنظیم نشده (رایگان؛ coingecko_key بالای فایل)');
        }

        if (\Nikto\Data\CoinGlass::enabled()) {
            Http::resetFailures();
            $coins = \Nikto\Data\CoinGlass::get('/futures/supported-coins');
            $add(
                is_array($coins),
                'منبع: CoinGlass',
                is_array($coins) ? 'کلید معتبر است' : (Http::explainFailures(1) !== '' ? ltrim(Http::explainFailures(1), '• ') : 'پاسخی نیامد')
            );
        } else {
            $add(null, 'منبع: CoinGlass', 'کلید تنظیم نشده (اختیاری؛ coinglass_key بالای فایل)');
        }

        foreach (Http::probe(self::DATA_SOURCES) as $label => $r) {
            $add(
                $r['ok'],
                $label,
                $r['ok'] ? 'در دسترس (' . $r['ms'] . ' ms)' : Http::explain($r['code'], $r['error'], $r['body'])
            );
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

        $recent = Log::recent(8);
        if ($recent !== []) {
            $lines[] = '';
            $lines[] = str_repeat('-', 46);
            $lines[] = 'آخرین خطاهای ثبت‌شده:';
            foreach ($recent as $row) {
                $lines[] = sprintf(
                    '  %s  %s  %s %s',
                    date('m-d H:i:s', $row['created_at']),
                    strtoupper($row['level']),
                    $row['message'],
                    mb_substr($row['context'], 0, 160)
                );
            }
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

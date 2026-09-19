<?php
declare(strict_types=1);

namespace Nikto\Core;

/**
 * کلاینت HTTP ساده روی cURL با کش اختیاری و تلاش مجدد.
 */
final class Http
{
    public static function get(string $url, array $headers = [], int $timeout = 0, int $retries = 2): ?string
    {
        $timeout = $timeout ?: (int) Config::get('http_timeout', 25);
        $lastErr = '';

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if ($attempt > 0) {
                usleep((int) (500000 * (2 ** ($attempt - 1))));
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 4,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(12, $timeout),
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'NiktoCryptoBot/1.0 (+php)',
                CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            ]);
            $proxy = (string) Config::get('http_proxy', '');
            if ($proxy !== '') {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
            }
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body !== false && $code >= 200 && $code < 300) {
                return (string) $body;
            }
            $lastErr = $err !== '' ? $err : ('HTTP ' . $code);
            if ($code >= 400 && $code < 500 && $code !== 429) {
                break; // خطای سمت درخواست؛ تلاش مجدد بی‌فایده است
            }
        }

        Log::warn('HTTP request failed', ['url' => self::maskUrl($url), 'error' => $lastErr]);
        return null;
    }

    /** @return array<mixed>|null */
    public static function getJson(string $url, array $headers = [], int $timeout = 0, int $retries = 2): ?array
    {
        $raw = self::get($url, $headers, $timeout, $retries);
        if ($raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Log::warn('Invalid JSON response', ['url' => self::maskUrl($url)]);
            return null;
        }
        return $data;
    }

    /** دریافت JSON با کش کوتاه‌مدت در دیتابیس */
    public static function cachedJson(string $key, string $url, int $ttl, array $headers = []): ?array
    {
        $row = Db::one('SELECT value, expires_at FROM cache WHERE key = :k', [':k' => $key]);
        if ($row && (int) $row['expires_at'] > time()) {
            $data = json_decode((string) $row['value'], true);
            if (is_array($data)) {
                return $data;
            }
        }
        $data = self::getJson($url, $headers);
        if ($data !== null) {
            Db::exec(
                'INSERT INTO cache(key, value, expires_at) VALUES(:k, :v, :e)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value, expires_at = excluded.expires_at',
                [':k' => $key, ':v' => json_encode($data, JSON_UNESCAPED_UNICODE), ':e' => time() + $ttl]
            );
            return $data;
        }
        // اگر شبکه قطع بود، از کش منقضی‌شده استفاده کن (بهتر از هیچ)
        if ($row) {
            $stale = json_decode((string) $row['value'], true);
            if (is_array($stale)) {
                Log::warn('Using stale cache', ['key' => $key]);
                return $stale;
            }
        }
        return null;
    }

    private static function maskUrl(string $url): string
    {
        return preg_replace('/(token|apikey|api_key|key)=[^&]+/i', '$1=***', $url) ?? $url;
    }
}

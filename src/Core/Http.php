<?php
declare(strict_types=1);

namespace Nikto\Core;

final class Http
{
    private static array $failures = [];

    public static function resetFailures(): void
    {
        self::$failures = [];
    }

    public static function failures(): array
    {
        return self::$failures;
    }

    public static function lastCode(string $url): int
    {
        return (int) (self::$failures[self::host($url)]['code'] ?? 0);
    }

    public static function explainFailures(int $max = 8): string
    {
        $lines = [];
        foreach (array_slice(self::$failures, 0, $max, true) as $host => $f) {
            $lines[] = '• ' . $host . ': ' . self::explain((int) $f['code'], (string) $f['error'], (string) ($f['body'] ?? ''));
        }
        if (count(self::$failures) > $max) {
            $lines[] = '• و ' . (count(self::$failures) - $max) . ' سرویس دیگر';
        }

        return implode("\n", $lines);
    }

    public static function allRegionBlocked(): bool
    {
        if (self::$failures === []) {
            return false;
        }
        foreach (self::$failures as $f) {
            if (!self::isRegionBlock((int) $f['code'], (string) ($f['body'] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    public static function isRegionBlock(int $code, string $body): bool
    {
        return $code === 451
            || ($code === 403 && preg_match('/country|countries|region|restricted location|eligibility|jurisdiction|1009/i', $body) === 1);
    }

    public static function explain(int $code, string $error, string $body = ''): string
    {
        $error = str_replace(['<', '>', '&'], '', $error);

        return match (true) {
            self::isRegionBlock($code, $body) => 'HTTP ' . $code . ' — این سرویس کشورِ سرورِ شما را مسدود کرده است',
            $code === 403 => 'HTTP 403 — دسترسی رد شد (فایروالِ هاست یا محدودیتِ سرویس)',
            $code === 401 => 'HTTP 401 — کلیدِ API نامعتبر است',
            $code === 429, $code === 418 => 'HTTP ' . $code . ' — محدودیت تعداد درخواست؛ چند دقیقه بعد دوباره امتحان کنید',
            $code >= 500 => 'HTTP ' . $code . ' — خطای موقت سمت سرویس',
            $code > 0 => 'HTTP ' . $code,
            stripos($error, 'SSL') !== false || stripos($error, 'certificate') !== false
                => 'خطای SSL/گواهی روی سرور: ' . mb_substr($error, 0, 80),
            stripos($error, 'timed out') !== false || stripos($error, 'timeout') !== false
                => 'مهلت اتصال تمام شد (سرور به این آدرس دسترسی ندارد)',
            stripos($error, 'resolve') !== false => 'نام دامنه پیدا نشد (DNS سرور)',
            stripos($error, 'JSON') !== false => 'پاسخ نامعتبر (JSON نبود)',
            $error !== '' => mb_substr($error, 0, 80),
            default => 'خطای نامشخص شبکه',
        };
    }

    public static function probe(array $urls, int $timeout = 8): array
    {
        $mh = curl_multi_init();
        $handles = [];
        foreach ($urls as $label => $target) {
            [$url, $post] = is_array($target) ? $target : [$target, null];
            $ch = curl_init($url);
            curl_setopt_array($ch, self::options($timeout, $post !== null ? ['Content-Type: application/json'] : []));
            if ($post !== null) {
                curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]);
            }
            $proxy = self::proxy();
            if ($proxy !== '') {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
            }
            curl_multi_add_handle($mh, $ch);
            $handles[$label] = $ch;
        }

        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        $results = [];
        while (($info = curl_multi_info_read($mh)) !== false) {
            $results[spl_object_id($info['handle'])] = (int) $info['result'];
        }

        $out = [];
        foreach ($handles as $label => $ch) {
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $result = $results[spl_object_id($ch)] ?? CURLE_OK;
            $out[$label] = [
                'ok'    => $code >= 200 && $code < 300,
                'code'  => $code,
                'error' => $result !== CURLE_OK ? (string) curl_strerror($result) : '',
                'body'  => self::snippet(curl_multi_getcontent($ch)),
                'ms'    => (int) round((float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000),
            ];
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }
        curl_multi_close($mh);

        return $out;
    }

    private static function options(int $timeout, array $headers): array
    {
        return [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(12, $timeout),
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'NiktoCryptoBot/1.0 (+php)',
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ];
    }

    public static function proxy(): string
    {
        return (string) (Config::get('data_proxy', '') ?: Config::get('http_proxy', ''));
    }

    private static function snippet(mixed $body): string
    {
        return is_string($body) ? mb_substr(trim((string) preg_replace('/\s+/', ' ', strip_tags($body))), 0, 300) : '';
    }

    private static function host(string $url): string
    {
        return strtolower((string) (parse_url($url, PHP_URL_HOST) ?: $url));
    }

    public static function note(string $url, int $code, string $error): void
    {
        self::fail($url, $code, $error);
    }

    private static function fail(string $url, int $code, string $error, string $body = ''): void
    {
        $host = self::host($url);
        unset(self::$failures[$host]);
        self::$failures[$host] = ['code' => $code, 'error' => $error, 'body' => $body];
    }

    public static function get(string $url, array $headers = [], int $timeout = 0, int $retries = 2, ?string $post = null): ?string
    {
        $timeout = $timeout ?: (int) Config::get('http_timeout', 25);
        $lastErr = '';
        $lastCode = 0;
        $lastBody = '';
        $err = '';

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            if ($attempt > 0) {
                usleep((int) (500000 * (2 ** ($attempt - 1))));
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, self::options($timeout, $post !== null ? array_merge($headers, ['Content-Type: application/json']) : $headers));
            if ($post !== null) {
                curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post]);
            }
            $proxy = self::proxy();
            if ($proxy !== '') {
                curl_setopt($ch, CURLOPT_PROXY, $proxy);
            }
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = curl_error($ch);
            curl_close($ch);

            if ($body !== false && $code >= 200 && $code < 300) {
                unset(self::$failures[self::host($url)]);
                return (string) $body;
            }
            $lastErr = $err !== '' ? $err : ('HTTP ' . $code);
            $lastCode = $code;
            $lastBody = self::snippet($body);
            if ($code >= 400 && $code < 500 && $code !== 429) {
                break;
            }
        }

        self::fail($url, $lastCode, $err, $lastBody);
        Log::warn('HTTP request failed', ['url' => self::maskUrl($url), 'error' => $lastErr]);
        return null;
    }

    public static function postJson(string $url, array $body, int $timeout = 0, int $retries = 1): ?array
    {
        return self::getJson($url, [], $timeout, $retries, (string) json_encode($body));
    }

    public static function getJson(string $url, array $headers = [], int $timeout = 0, int $retries = 2, ?string $post = null): ?array
    {
        $raw = self::get($url, $headers, $timeout, $retries, $post);
        if ($raw === null) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::fail($url, 0, 'Invalid JSON');
            Log::warn('Invalid JSON response', ['url' => self::maskUrl($url)]);
            return null;
        }
        return $data;
    }

    public static function remember(string $key, int $ttl, callable $produce): ?array
    {
        $row = Db::one('SELECT value, expires_at FROM cache WHERE key = :k', [':k' => $key]);
        if ($row && (int) $row['expires_at'] > time()) {
            $data = json_decode((string) $row['value'], true);
            if (is_array($data)) {
                return $data;
            }
        }

        $data = $produce();
        if (is_array($data) && $data !== []) {
            Db::exec(
                'INSERT INTO cache(key, value, expires_at) VALUES(:k, :v, :e)
                 ON CONFLICT(key) DO UPDATE SET value = excluded.value, expires_at = excluded.expires_at',
                [':k' => $key, ':v' => json_encode($data, JSON_UNESCAPED_UNICODE), ':e' => time() + $ttl]
            );
            return $data;
        }
        if ($row) {
            $stale = json_decode((string) $row['value'], true);
            if (is_array($stale)) {
                Log::warn('Using stale cache', ['key' => $key]);
                return $stale;
            }
        }

        return null;
    }

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

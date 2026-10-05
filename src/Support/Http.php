<?php

declare(strict_types=1);

namespace App\Support;

final class Http
{
    public static string $proxy = '';

    /**
     * @return array{status:int, body:string, error:string}
     */
    public static function request(string $method, string $url, array $options = []): array
    {
        $ch = curl_init($url);
        $headers = $options['headers'] ?? [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => $options['connect_timeout'] ?? 6,
            CURLOPT_TIMEOUT => $options['timeout'] ?? 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; AnalystBot/1.0)',
            CURLOPT_ENCODING => '',
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if (self::$proxy !== '') {
            curl_setopt($ch, CURLOPT_PROXY, self::$proxy);
        }
        if (array_key_exists('json', $options)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($options['json'], JSON_UNESCAPED_UNICODE));
            $headers[] = 'Content-Type: application/json';
        } elseif (array_key_exists('form', $options)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['form']);
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['status' => $status, 'body' => is_string($body) ? $body : '', 'error' => $error];
    }

    public static function getJson(string $url, array $options = []): ?array
    {
        $res = self::request('GET', $url, $options);
        if ($res['status'] !== 200) {
            return null;
        }
        $data = json_decode($res['body'], true);
        return is_array($data) ? $data : null;
    }
}

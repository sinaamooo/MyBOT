<?php

declare(strict_types=1);

namespace MyBot\Support;

use CurlHandle;

final class TelegramClient
{
    private ?CurlHandle $handle = null;

    public function __construct(
        private readonly string $token,
        private readonly Logger $logger,
        private readonly string $apiBase = 'https://api.telegram.org',
        private readonly int $timeout = 60,
    ) {
    }

    /**
     * Calls a Bot API method and returns its `result` payload.
     *
     * @param array<string, mixed> $params
     * @throws ApiException on any non-ok response (including transport failures)
     */
    public function call(string $method, array $params = []): mixed
    {
        $payload = [];

        foreach ($params as $key => $value) {
            if ($value === null) {
                continue;
            }
            if (\is_bool($value)) {
                $payload[$key] = $value ? 'true' : 'false';
                continue;
            }
            if (\is_array($value)) {
                $encoded = json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
                if ($encoded === false) {
                    continue;
                }
                $payload[$key] = $encoded;
                continue;
            }
            $payload[$key] = (string) $value;
        }

        $url = sprintf('%s/bot%s/%s', rtrim($this->apiBase, '/'), $this->token, $method);

        $this->handle ??= curl_init();
        $curl = $this->handle;

        curl_reset($curl);
        curl_setopt_array($curl, [
            \CURLOPT_URL            => $url,
            \CURLOPT_POST           => true,
            \CURLOPT_POSTFIELDS     => http_build_query($payload, '', '&'),
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_TIMEOUT        => $this->timeout,
            \CURLOPT_CONNECTTIMEOUT => 15,
            \CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],
        ]);

        $body = curl_exec($curl);

        if ($body === false) {
            $error = curl_error($curl);
            $this->logger->warn('transport failure', ['method' => $method, 'error' => $error]);

            throw new ApiException('خطای شبکه: ' . $error, 0, [], $method);
        }

        /** @var mixed $decoded */
        $decoded = json_decode((string) $body, true);

        if (!\is_array($decoded)) {
            throw new ApiException('پاسخ نامعتبر از تلگرام دریافت شد.', 0, [], $method);
        }

        if (($decoded['ok'] ?? false) !== true) {
            $description = \is_string($decoded['description'] ?? null)
                ? $decoded['description']
                : 'خطای ناشناخته از تلگرام';
            $parameters = \is_array($decoded['parameters'] ?? null) ? $decoded['parameters'] : [];
            $code       = \is_int($decoded['error_code'] ?? null) ? $decoded['error_code'] : 0;

            throw new ApiException($description, $code, $parameters, $method);
        }

        return $decoded['result'] ?? null;
    }

    /**
     * Same as call(), but returns null instead of throwing.
     * Use where a failure is genuinely uninteresting (e.g. a stats refresh).
     *
     * @param array<string, mixed> $params
     */
    public function tryCall(string $method, array $params = []): mixed
    {
        try {
            return $this->call($method, $params);
        } catch (ApiException $e) {
            $this->logger->debug('call failed (ignored)', [
                'method' => $method,
                'error'  => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param list<string> $allowedUpdates
     * @return list<array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeout, array $allowedUpdates): array
    {
        $result = $this->call('getUpdates', [
            'offset'          => $offset,
            'timeout'         => $timeout,
            'limit'           => 100,
            'allowed_updates' => $allowedUpdates,
        ]);

        return \is_array($result) ? array_values($result) : [];
    }

    public function __destruct()
    {
        if ($this->handle instanceof CurlHandle) {
            curl_close($this->handle);
            $this->handle = null;
        }
    }
}

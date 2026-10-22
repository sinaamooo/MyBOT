<?php

declare(strict_types=1);

namespace EmojiMaker\Delivery;

use EmojiMaker\Security\Crypto;

/**
 * Signed, expiring download links for generated files:
 *   <base>/download.php?f=<fileId>&u=<userId>&e=<expires>&s=<hmac>
 * The HMAC (APP_KEY) covers file, user and expiry, so links cannot be
 * forged, re-targeted to another file / user or extended. public/download.php
 * additionally checks that the file still belongs to that user.
 */
final class DownloadLinks
{
    private const MAX_TTL = 30 * 86400;

    public function __construct(private Crypto $crypto, private string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** https://…/download.php?f=<fileId>&u=<userId>&e=<expires>&s=<hmac> */
    public function make(int $fileId, int $userId, int $ttlSeconds): string
    {
        if ($fileId <= 0 || $userId < 0) {
            throw new \InvalidArgumentException('Invalid download link target');
        }
        $expires = time() + max(60, min(self::MAX_TTL, $ttlSeconds));
        return $this->baseUrl . '/download.php?' . http_build_query([
            'f' => $fileId,
            'u' => $userId,
            'e' => $expires,
            's' => $this->signature($fileId, $userId, $expires),
        ]);
    }

    /**
     * Returns [fileId, userId] or null (bad signature / expired). The
     * signature is compared in constant time. $allowExpired lets the
     * download page tell an expired link from a forged one.
     * @param array<string,mixed> $query
     * @return array{0:int,1:int}|null
     */
    public function verify(array $query, bool $allowExpired = false): ?array
    {
        $f = $query['f'] ?? null;
        $u = $query['u'] ?? null;
        $e = $query['e'] ?? null;
        $s = $query['s'] ?? null;
        foreach ([$f, $u, $e] as $v) {
            if (!is_string($v) && !is_int($v)) {
                return null;
            }
            if (!preg_match('/^\d{1,19}$/', (string) $v)) {
                return null;
            }
        }
        if (!is_string($s) || !preg_match('/^[A-Za-z0-9_-]{20,128}$/', $s)) {
            return null;
        }
        $fileId = (int) $f;
        $userId = (int) $u;
        $expires = (int) $e;
        if ($fileId <= 0 || !$this->crypto->verify($this->payload($fileId, $userId, $expires), $s)) {
            return null;
        }
        if (!$allowExpired && $expires < time()) {
            return null;
        }
        return [$fileId, $userId];
    }

    public function isExpired(array $query): bool
    {
        return isset($query['e']) && is_scalar($query['e']) && ctype_digit((string) $query['e']) && (int) $query['e'] < time();
    }

    private function signature(int $fileId, int $userId, int $expires): string
    {
        return $this->crypto->sign($this->payload($fileId, $userId, $expires));
    }

    private function payload(int $fileId, int $userId, int $expires): string
    {
        return 'download|' . $fileId . '|' . $userId . '|' . $expires;
    }
}

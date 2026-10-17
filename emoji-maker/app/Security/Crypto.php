<?php

declare(strict_types=1);

namespace EmojiMaker\Security;

/**
 * Symmetric encryption (secrets stored in the database) and HMAC signatures
 * (download links) keyed by APP_KEY. Uses libsodium when available and
 * AES-256-GCM (OpenSSL) otherwise.
 */
final class Crypto
{
    private string $key;

    public function __construct(string $appKey)
    {
        if (str_starts_with($appKey, 'base64:')) {
            $raw = base64_decode(substr($appKey, 7), true);
        } else {
            $raw = $appKey;
        }
        if ($raw === false || strlen($raw) < 16) {
            throw new \RuntimeException('APP_KEY is missing or too short. Run bin/install.php or set a 32 byte base64 key.');
        }
        $this->key = hash('sha256', $raw, true);
    }

    public static function generateKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    public static function randomToken(int $bytes = 24): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function encrypt(string $plain): string
    {
        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
        return 'g1:' . base64_encode($iv . $tag . $cipher);
    }

    public function decrypt(string $payload): ?string
    {
        $prefix = substr($payload, 0, 3);
        $raw = base64_decode(substr($payload, 3), true);
        if ($raw === false) {
            return null;
        }
        if ($prefix === 's1:' && function_exists('sodium_crypto_secretbox_open')) {
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
            return $plain === false ? null : $plain;
        }
        if ($prefix === 'g1:') {
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);
            return $plain === false ? null : $plain;
        }
        return null;
    }

    public function sign(string $data): string
    {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $this->key, true)), '+/', '-_'), '=');
    }

    public function verify(string $data, string $signature): bool
    {
        return hash_equals($this->sign($data), $signature);
    }
}

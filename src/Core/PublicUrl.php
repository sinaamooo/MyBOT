<?php
declare(strict_types=1);

namespace Nikto\Core;

final class PublicUrl
{
    public static function current(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
        if ($host === '') {
            return '';
        }

        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

        $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $path = '/' . ltrim($path, '/');

        return ($https ? 'https' : 'http') . '://' . $host . $path;
    }

    public static function statusPage(string $version = '', string $registerHint = ''): string
    {
        $url = self::current();
        $configured = trim((string) Config::get('webhook_url', ''));

        $lines = ['NIKTO CRYPTO BOT' . ($version !== '' ? ' v' . $version : '') . ' is running.', ''];

        if ($url !== '') {
            $lines[] = 'Webhook URL for this file:';
            $lines[] = '  ' . $url;
            $lines[] = '';
            if ($configured !== '' && rtrim($configured, '/') !== rtrim($url, '/')) {
                $lines[] = 'NOTE: config says ' . $configured;
                $lines[] = 'Update webhook_url to the address above, then register it again.';
                $lines[] = '';
            }
        }
        $lines[] = 'To register this URL with Telegram, run on the server:';
        $lines[] = '  ' . ($registerHint !== '' ? $registerHint : 'php tools/webhook-set.php');

        return implode("\n", $lines) . "\n";
    }
}

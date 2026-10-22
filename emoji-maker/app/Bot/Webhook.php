<?php

declare(strict_types=1);

namespace EmojiMaker\Bot;

use EmojiMaker\App;
use EmojiMaker\Security\Redactor;
use EmojiMaker\Support\Env;
use EmojiMaker\Support\Logger;

/**
 * Telegram webhook endpoint logic.
 *
 * Telegram waits for our HTTP answer and re-sends the update when it is slow
 * or fails, so the request is validated (method, content type, size, secret
 * token, optional source IP), the update is stored for de-duplication and
 * "200 ok" is sent and the connection closed BEFORE the bot works on it.
 * In "queue" mode the stored update is left for the worker instead.
 */
final class Webhook
{
    private const DEFAULT_MAX_BODY = 1048576;
    private const PROCESS_TIME_LIMIT = 300;

    private ?\Closure $processor;

    /**
     * @param callable(array<string,mixed>):void|null $processor replaces UpdateProcessor::process() (tests)
     */
    public function __construct(private App $app, ?callable $processor = null)
    {
        $this->processor = $processor !== null ? \Closure::fromCallable($processor) : null;
    }

    /** Entry point of public/webhook.php: works on the current PHP request. */
    public function handle(): void
    {
        $server = $_SERVER;
        $body = '';
        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) === 'POST' && (int) ($server['CONTENT_LENGTH'] ?? 0) <= $this->maxBody()) {
            $body = self::readInput($this->maxBody() + 1);
        }
        $this->run($server, $body, static function (int $status, string $text, array $headers): void {
            self::emit($status, $text, $headers);
        });
    }

    /**
     * Runs the webhook flow for one request and returns the HTTP status that was sent.
     * $emit(status, body, headers) must send the answer and close the connection; it is
     * called exactly once and always before the update is processed.
     *
     * @param array<string,mixed> $server $_SERVER-like array
     * @param callable(int,string,array<string,string>):void $emit
     */
    public function run(array $server, string $body, callable $emit): int
    {
        $sent = false;
        $respond = static function (int $status, string $text, array $headers = []) use ($emit, &$sent): int {
            if (!$sent) {
                $sent = true;
                $emit($status, $text, $headers);
            }
            return $status;
        };

        if (strtoupper((string) ($server['REQUEST_METHOD'] ?? '')) !== 'POST') {
            return $respond(405, 'method not allowed', ['Allow' => 'POST']);
        }
        if (!self::isJsonRequest($server)) {
            return $respond(415, 'unsupported media type');
        }
        $max = $this->maxBody();
        if ((int) ($server['CONTENT_LENGTH'] ?? 0) > $max || strlen($body) > $max) {
            return $respond(413, 'payload too large');
        }

        try {
            $secret = $this->app->webhookSecret();
        } catch (\Throwable $e) {
            $this->log()->exception($e, 'Webhook secret could not be loaded');
            $secret = '';
        }
        if ($secret === '') {
            $this->log()->error('Webhook secret is not configured; update rejected (set TELEGRAM_WEBHOOK_SECRET and call setWebhook again)');
            return $respond(503, 'service unavailable');
        }
        $given = $server['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
        if (!is_string($given) || $given === '' || !hash_equals($secret, $given)) {
            $this->log()->warning('Webhook request with a missing or wrong secret token', ['ip' => (string) ($server['REMOTE_ADDR'] ?? '')]);
            return $respond(403, 'forbidden');
        }

        if ((bool) $this->app->config()->get('telegram.ip_check', false)) {
            $ip = $this->clientIp($server);
            $ranges = (array) $this->app->config()->get('telegram.ip_ranges', []);
            if ($ip === '' || !self::ipInRanges($ip, $ranges)) {
                $this->log()->warning('Webhook request from an address outside the Telegram ranges', ['ip' => $ip, 'remote' => (string) ($server['REMOTE_ADDR'] ?? '')]);
                return $respond(403, 'forbidden');
            }
        }

        $update = json_decode($body, true, 64);
        if (!is_array($update) || !is_int($update['update_id'] ?? null) || $update['update_id'] < 0) {
            $this->log()->notice('Webhook request without a valid update', ['bytes' => strlen($body)]);
            return $respond(400, 'bad request');
        }
        $updateId = $update['update_id'];
        [$type, $telegramId] = self::describe($update);

        try {
            if (!$this->app->misc()->storeUpdate($updateId, $telegramId, $type, $body)) {
                $this->log()->info('Duplicate update ignored', ['update_id' => $updateId]);
                return $respond(200, 'ok');
            }
        } catch (\Throwable $e) {
            // Telegram re-delivers after a non-2xx answer, so nothing is lost while the database is down
            $this->log()->exception($e, 'Webhook could not store the update', ['update_id' => $updateId]);
            return $respond(503, 'service unavailable');
        }

        // claim before answering so a worker that polls pending updates never takes it as well
        $inline = $this->mode() === 'inline' && $this->claim($updateId);
        $respond(200, 'ok');

        if ($inline) {
            $this->process($updateId, $update);
        }
        return 200;
    }

    /** "inline" (default) or "queue". */
    public function mode(): string
    {
        return strtolower(trim((string) $this->app->config()->get('telegram.webhook_mode', 'inline'))) === 'queue' ? 'queue' : 'inline';
    }

    /**
     * Client address; X-Forwarded-For is honoured only when the direct peer is
     * listed in TRUSTED_PROXIES (comma separated IPs / CIDR ranges).
     * @param array<string,mixed> $server
     */
    public function clientIp(array $server): string
    {
        $remote = self::normalizeIp((string) ($server['REMOTE_ADDR'] ?? ''));
        $forwarded = (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '');
        $trusted = $this->trustedProxies();
        if ($forwarded === '' || $trusted === [] || !self::ipInRanges($remote, $trusted)) {
            return $remote;
        }
        foreach (array_reverse(explode(',', $forwarded)) as $hop) {
            $hop = self::normalizeIp(trim($hop));
            if (filter_var($hop, FILTER_VALIDATE_IP) === false) {
                return ''; // malformed chain: there is no trustworthy client address
            }
            if (!self::ipInRanges($hop, $trusted)) {
                return $hop;
            }
        }
        return $remote;
    }

    /** True when $ip is equal to an address or inside a CIDR range of the list (IPv4 and IPv6). */
    public static function ipInRanges(string $ip, array $ranges): bool
    {
        $ip = self::normalizeIp($ip);
        $bin = @inet_pton($ip);
        if ($ip === '' || $bin === false) {
            return false;
        }
        foreach ($ranges as $range) {
            if (!is_string($range) || trim($range) === '') {
                continue;
            }
            $range = trim($range);
            $bits = null;
            if (str_contains($range, '/')) {
                [$range, $bitsText] = explode('/', $range, 2);
                if (!ctype_digit($bitsText)) {
                    continue;
                }
                $bits = (int) $bitsText;
            }
            $net = @inet_pton(self::normalizeIp($range));
            if ($net === false || strlen($net) !== strlen($bin)) {
                continue;
            }
            $bits ??= strlen($bin) * 8;
            if ($bits > strlen($bin) * 8) {
                continue;
            }
            $bytes = intdiv($bits, 8);
            if (substr($bin, 0, $bytes) !== substr($net, 0, $bytes)) {
                continue;
            }
            $rest = $bits % 8;
            if ($rest === 0) {
                return true;
            }
            $mask = (0xFF << (8 - $rest)) & 0xFF;
            if ((ord($bin[$bytes]) & $mask) === (ord($net[$bytes]) & $mask)) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------ internals

    /** @param array<string,mixed> $update */
    private function process(int $updateId, array $update): void
    {
        ignore_user_abort(true);
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::PROCESS_TIME_LIMIT);
        }
        $finished = false;
        // a fatal error or the time limit must not leave the update in "processing" forever
        register_shutdown_function(function () use (&$finished, $updateId): void {
            if (!$finished) {
                try {
                    $this->app->misc()->markUpdate($updateId, 'failed', 'Processing aborted (fatal error or time limit)');
                } catch (\Throwable) {
                    // the database may be the reason for the abort
                }
            }
        });
        try {
            if ($this->processor !== null) {
                ($this->processor)($update);
            } else {
                (new UpdateProcessor($this->app))->process($update);
            }
            $this->app->misc()->markUpdate($updateId, 'processed');
        } catch (\Throwable $e) {
            $this->log()->exception($e, 'Update processing failed', ['update_id' => $updateId]);
            try {
                $this->app->misc()->markUpdate($updateId, 'failed', Redactor::text(get_class($e) . ': ' . $e->getMessage()));
            } catch (\Throwable $inner) {
                $this->log()->exception($inner, 'Update status could not be saved', ['update_id' => $updateId]);
            }
        } finally {
            $finished = true;
        }
    }

    /** received -> processing, only when nobody else took it (optimistic, rowCount based). */
    private function claim(int $updateId): bool
    {
        try {
            return $this->app->db()->query(
                "UPDATE telegram_updates SET status = 'processing' WHERE update_id = ? AND status = 'received'",
                [$updateId]
            )->rowCount() === 1;
        } catch (\Throwable $e) {
            $this->log()->exception($e, 'Update could not be claimed; left for the worker', ['update_id' => $updateId]);
            return false;
        }
    }

    /**
     * Update type (first payload key) and the Telegram user / chat id it belongs to.
     * @param array<string,mixed> $update
     * @return array{0:string,1:?int}
     */
    private static function describe(array $update): array
    {
        foreach ($update as $key => $value) {
            if ($key === 'update_id' || !is_array($value)) {
                continue;
            }
            $id = $value['from']['id'] ?? $value['user']['id'] ?? $value['chat']['id'] ?? $value['message']['chat']['id'] ?? null;
            return [mb_substr((string) $key, 0, 32), is_int($id) ? $id : null];
        }
        return ['unknown', null];
    }

    /** @return list<string> */
    private function trustedProxies(): array
    {
        $configured = $this->app->config()->get('telegram.trusted_proxies');
        $list = is_array($configured) ? $configured : preg_split('/[\s,]+/', (string) Env::get('TRUSTED_PROXIES', ''));
        return array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $list ?: []), static fn (string $v) => $v !== ''));
    }

    /** "::ffff:1.2.3.4" (dual stack sockets) -> "1.2.3.4". */
    private static function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        if (stripos($ip, '::ffff:') === 0 && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return substr($ip, 7);
        }
        return $ip;
    }

    /** @param array<string,mixed> $server */
    private static function isJsonRequest(array $server): bool
    {
        $type = (string) ($server['CONTENT_TYPE'] ?? $server['HTTP_CONTENT_TYPE'] ?? '');
        return strtolower(trim(explode(';', $type, 2)[0])) === 'application/json';
    }

    private function maxBody(): int
    {
        return max(1024, (int) $this->app->config()->get('telegram.max_webhook_body', self::DEFAULT_MAX_BODY));
    }

    private function log(): Logger
    {
        return $this->app->logger('webhook');
    }

    private static function readInput(int $limit): string
    {
        $in = @fopen('php://input', 'rb');
        if ($in === false) {
            return '';
        }
        $data = stream_get_contents($in, $limit);
        fclose($in);
        return is_string($data) ? $data : '';
    }

    /**
     * Sends the answer and ends the HTTP exchange while PHP keeps running:
     * PHP-FPM / LiteSpeed (cPanel) have finish functions, elsewhere
     * Content-Length + Connection: close + flush let the client go.
     * @param array<string,string> $headers
     */
    private static function emit(int $status, string $body, array $headers): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Length: ' . strlen($body));
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            header('Connection: close');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
        }
        echo $body;
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
            return;
        }
        ignore_user_abort(true);
        while (ob_get_level() > 0) {
            if (!@ob_end_flush()) {
                break;
            }
        }
        flush();
    }
}

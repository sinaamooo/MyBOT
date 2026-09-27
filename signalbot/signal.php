<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/card.php';

enum Direction: string
{
    case LONG = 'LONG';
    case SHORT = 'SHORT';
}

enum ZoneType: string
{
    case SUPPORT = 'support';
    case RESISTANCE = 'resistance';
}

enum OrderBlockType: string
{
    case BULLISH = 'bullish';
    case BEARISH = 'bearish';
}

enum FvgType: string
{
    case BULLISH = 'bullish';
    case BEARISH = 'bearish';
}

final class Candle
{
    public function __construct(
        public readonly int $openTime,
        public readonly float $open,
        public readonly float $high,
        public readonly float $low,
        public readonly float $close,
        public readonly float $volume,
        public readonly string $timeframe = '',
    ) {
    }

    public function isBullish(): bool
    {
        return $this->close >= $this->open;
    }

    public function bodySize(): float
    {
        return abs($this->close - $this->open);
    }

    public function range(): float
    {
        return $this->high - $this->low;
    }

    public static function timeframeSeconds(string $timeframe): int
    {
        if (!preg_match('/^(\d+)([mhdw])$/i', trim($timeframe), $m)) {
            return 300;
        }
        $unit = match (strtolower($m[2])) {
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
            'w' => 604800,
        };
        return max(60, (int) $m[1] * $unit);
    }

    public function isSane(): bool
    {
        foreach ([$this->open, $this->high, $this->low, $this->close] as $price) {
            if (!is_finite($price) || $price <= 0) {
                return false;
            }
        }
        if ($this->volume < 0 || !is_finite($this->volume)) {
            return false;
        }
        if ($this->high < $this->low) {
            return false;
        }
        if ($this->high < max($this->open, $this->close) || $this->low > min($this->open, $this->close)) {
            return false;
        }
        return $this->openTime > 1_262_304_000_000 && $this->openTime < (time() + 86_400) * 1000;
    }
}

final class MarketSnapshot
{
    public function __construct(
        public readonly string $exchange,
        public readonly string $symbol,
        public readonly float $price,
        public readonly float $volume,
        public readonly float $bid,
        public readonly float $ask,
        public readonly float $spreadPct,
        public readonly int $timestamp,
        public readonly array $candles = [],
    ) {
    }

    public function candlesFor(string $timeframe): array
    {
        return $this->candles[$timeframe] ?? [];
    }
}

final class Zone
{
    public function __construct(
        public readonly ZoneType $type,
        public readonly float $high,
        public readonly float $low,
        public readonly string $timeframe,
        public readonly float $strength,
        public readonly float $volume,
        public readonly int $touches,
        public readonly string $source = 'swing',
    ) {
    }

    public function mid(): float
    {
        return ($this->high + $this->low) / 2;
    }
}

final class OrderBlock
{
    public function __construct(
        public readonly OrderBlockType $type,
        public readonly float $high,
        public readonly float $low,
        public readonly string $timeframe,
        public readonly float $strength,
        public readonly float $volume,
        public bool $mitigated,
        public readonly int $createdAt,
    ) {
    }
}

final class Fvg
{
    public function __construct(
        public readonly FvgType $type,
        public readonly float $high,
        public readonly float $low,
        public readonly float $midpoint,
        public readonly string $timeframe,
        public readonly float $size,
        public bool $filled,
        public readonly int $createdAt,
    ) {
    }
}

final class Signal
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $exchange,
        public readonly string $symbol,
        public readonly Direction $direction,
        public readonly string $timeframe,
        public readonly float $entry,
        public readonly float $stopLoss,
        public readonly ?float $tp1,
        public readonly ?float $tp2,
        public readonly ?float $tp3,
        public readonly ?float $tp4,
        public readonly float $riskReward,
        public readonly float $score,
        public readonly string $confidence,
        public readonly string $strategy,
        public readonly array $reasons,
        public readonly string $fingerprint,
        public string $status = 'pending',
        public ?int $id = null,
        public readonly int $createdAt = 0,
        public readonly int $leverage = 1,
        public readonly string $tier = 'alt',
        public ?float $activeStop = null,
    ) {
        $this->activeStop ??= $stopLoss;
    }

    public function leveragedPnlPercent(float $price): float
    {
        if ($this->entry <= 0) {
            return 0.0;
        }
        $move = $this->direction === Direction::LONG
            ? ($price - $this->entry)
            : ($this->entry - $price);
        return ($move / $this->entry) * 100 * $this->leverage;
    }
}

final class RateLimiter
{
    private static array $buckets = [];

    public static function acquire(string $key, int $maxPerMinute): void
    {
        $now = time();
        $bucket = self::$buckets[$key] ?? ['count' => 0, 'windowStart' => $now];

        if ($now - $bucket['windowStart'] >= 60) {
            $bucket = ['count' => 0, 'windowStart' => $now];
        }

        if ($bucket['count'] >= $maxPerMinute) {
            $sleepFor = 60 - ($now - $bucket['windowStart']);
            if ($sleepFor > 0) {
                usleep(min($sleepFor, 5) * 1_000_000);
            }
            $bucket = ['count' => 0, 'windowStart' => time()];
        }

        $bucket['count']++;
        self::$buckets[$key] = $bucket;
    }
}

final class HttpClient
{
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, ?int $retries = null): array
    {
        $retries ??= Config::maxRetries();
        $attempt = 0;
        $lastError = null;

        while ($attempt <= $retries) {
            if (ShutdownFlag::isRequested()) {
                $lastError = 'aborted: shutdown requested';
                break;
            }
            $attempt++;
            $ch = curl_init($url);
            $headerLines = [];
            foreach ($headers as $k => $v) {
                $headerLines[] = "$k: $v";
            }
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => Config::httpTimeoutSeconds(),
                CURLOPT_CONNECTTIMEOUT => Config::httpTimeoutSeconds(),
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'NiktoSignalBot/1.0',
            ]);

            $responseBody = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($errno !== 0 || $responseBody === false) {
                $lastError = "curl error [$errno]: $error";
                self::backoffSleep($attempt);
                continue;
            }

            if ($status === 429 || $status >= 500) {
                $lastError = "HTTP $status";
                self::backoffSleep($attempt);
                continue;
            }

            $json = null;
            if ($responseBody !== '') {
                $decoded = json_decode($responseBody, true);
                if (is_array($decoded)) {
                    $json = $decoded;
                }
            }

            return ['status' => $status, 'body' => (string) $responseBody, 'json' => $json];
        }

        Logger::warning('http', 'request failed after retries', ['url' => self::stripQuery($url), 'error' => $lastError]);
        return ['status' => 0, 'body' => '', 'json' => null];
    }

    private static function backoffSleep(int $attempt): void
    {
        $delayUs = min(1_000_000 * (2 ** $attempt), 8_000_000);

        $sliceUs = 200_000;
        $slept = 0;
        while ($slept < $delayUs) {
            if (ShutdownFlag::isRequested()) {
                return;
            }
            $chunk = min($sliceUs, $delayUs - $slept);
            usleep($chunk);
            $slept += $chunk;
        }
    }

    private static function stripQuery(string $url): string
    {
        return explode('?', $url)[0];
    }
}

final class CoinalyzeClient
{
    public function liquidationHistory(string $symbol, int $fromUnix, int $toUnix, string $interval = 'daily'): ?array
    {
        $apiKey = Config::coinalyzeApiKey();
        if ($apiKey === '') {
            return null;
        }
        $url = Config::coinalyzeRestBase() . '/liquidation-history?' . http_build_query([
            'symbols' => $symbol,
            'interval' => $interval,
            'from' => $fromUnix,
            'to' => $toUnix,
            'convert_to_usd' => 'true',
        ]);
        $res = HttpClient::request('GET', $url, ['api_key' => $apiKey], null, 1);
        if ($res['status'] !== 200 || !is_array($res['json'])) {
            Logger::warning('coinalyze', 'liquidation-history request failed', [
                'status' => $res['status'],
                'body' => mb_strimwidth($res['body'], 0, 300, '…'),
            ]);
            return null;
        }
        $row = $res['json'][0] ?? null;
        if (!is_array($row) || !isset($row['history']) || !is_array($row['history'])) {
            Logger::warning('coinalyze', 'unexpected liquidation-history shape', ['raw' => mb_strimwidth(json_encode($res['json']), 0, 300, '…')]);
            return null;
        }
        return $row['history'];
    }

    public function liquidationTotals(array $history): ?array
    {
        if (empty($history)) {
            return ['long' => 0.0, 'short' => 0.0];
        }
        $keys = $this->detectKeys($history);
        if ($keys === null) {
            return null;
        }
        $long = 0.0;
        $short = 0.0;
        foreach ($history as $row) {
            $long += (float) ($row[$keys['long']] ?? 0);
            $short += (float) ($row[$keys['short']] ?? 0);
        }
        return ['long' => $long, 'short' => $short];
    }

    public function detectKeys(array $history): ?array
    {
        if (empty($history)) {
            return null;
        }
        $keys = array_keys($history[0]);
        $findKey = static function (array $keys, array $candidates): ?string {
            foreach ($candidates as $c) {
                foreach ($keys as $k) {
                    if (strcasecmp((string) $k, $c) === 0) {
                        return $k;
                    }
                }
            }
            return null;
        };

        $longKey = $findKey($keys, ['l', 'long', 'longs', 'buy', 'b']);
        $shortKey = $findKey($keys, ['s', 'short', 'shorts', 'sell']);
        if ($longKey === null || $shortKey === null) {
            return null;
        }
        return ['long' => $longKey, 'short' => $shortKey];
    }
}

final class WebSocketClient
{
    private $socket = null;
    private bool $connected = false;

    public function connect(string $url, int $timeoutSeconds = 10): bool
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            Logger::error('websocket', 'invalid ws url');
            return false;
        }

        $scheme = $parts['scheme'] ?? 'wss';
        $host = $parts['host'];
        $port = $parts['port'] ?? ($scheme === 'wss' ? 443 : 80);
        $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $transport = $scheme === 'wss' ? 'ssl' : 'tcp';

        $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->socket = @stream_socket_client(
            "$transport://$host:$port",
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($this->socket === false) {
            Logger::warning('websocket', 'connect failed', ['host' => $host, 'error' => $errstr]);
            $this->socket = null;
            return false;
        }

        $key = base64_encode(random_bytes(16));
        $handshake = "GET $path HTTP/1.1\r\n"
            . "Host: $host\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: $key\r\n"
            . "Sec-WebSocket-Version: 13\r\n\r\n";

        fwrite($this->socket, $handshake);
        stream_set_timeout($this->socket, $timeoutSeconds);
        $response = '';
        while (!feof($this->socket)) {
            $line = fgets($this->socket, 2048);
            if ($line === false || $line === "\r\n") {
                break;
            }
            $response .= $line;
        }

        if (!str_contains($response, '101')) {
            Logger::warning('websocket', 'handshake failed', ['response' => substr($response, 0, 200)]);
            $this->close();
            return false;
        }

        $expectedAccept = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        if (!str_contains($response, $expectedAccept)) {
            Logger::warning('websocket', 'handshake accept mismatch');
            $this->close();
            return false;
        }

        stream_set_blocking($this->socket, false);
        $this->connected = true;
        return true;
    }

    public function isConnected(): bool
    {
        return $this->connected && is_resource($this->socket) && !feof($this->socket);
    }

    public function send(string $payload, int $opcode = 0x1): bool
    {
        if (!$this->isConnected()) {
            return false;
        }
        $frame = $this->encodeFrame($payload, $opcode);
        return @fwrite($this->socket, $frame) !== false;
    }

    public function receive(float $timeoutSeconds = 0.5): ?string
    {
        if (!is_resource($this->socket)) {
            return null;
        }

        $read = [$this->socket];
        $write = null;
        $except = null;
        $sec = (int) floor($timeoutSeconds);
        $usec = (int) (($timeoutSeconds - $sec) * 1_000_000);

        $changed = @stream_select($read, $write, $except, $sec, $usec);
        if ($changed === false || $changed === 0) {
            return null;
        }

        $header = fread($this->socket, 2);
        if ($header === false || strlen($header) < 2) {
            return null;
        }

        $b1 = ord($header[0]);
        $b2 = ord($header[1]);
        $opcode = $b1 & 0x0F;
        $masked = ($b2 & 0x80) !== 0;
        $len = $b2 & 0x7F;

        if ($len === 126) {
            $ext = fread($this->socket, 2);
            $len = $ext !== false ? unpack('n', $ext)[1] : 0;
        } elseif ($len === 127) {
            $ext = fread($this->socket, 8);
            $len = $ext !== false ? (int) unpack('J', $ext)[1] : 0;
        }

        $maskKey = '';
        if ($masked) {
            $maskKey = (string) fread($this->socket, 4);
        }

        $payload = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $payload .= $chunk;
            $remaining -= strlen($chunk);
        }

        if ($masked && $maskKey !== '') {
            $unmasked = '';
            for ($i = 0, $n = strlen($payload); $i < $n; $i++) {
                $unmasked .= $payload[$i] ^ $maskKey[$i % 4];
            }
            $payload = $unmasked;
        }

        if ($opcode === 0x8) {
            $this->connected = false;
            return null;
        }
        if ($opcode === 0x9) {
            $this->send($payload, 0xA);
            return null;
        }
        if ($opcode === 0xA) {
            return null;
        }

        return $payload;
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
        }
        $this->socket = null;
        $this->connected = false;
    }

    private function encodeFrame(string $payload, int $opcode): string
    {
        $len = strlen($payload);
        $frame = chr(0x80 | $opcode);

        $maskKey = random_bytes(4);
        if ($len <= 125) {
            $frame .= chr(0x80 | $len);
        } elseif ($len <= 65535) {
            $frame .= chr(0x80 | 126) . pack('n', $len);
        } else {
            $frame .= chr(0x80 | 127) . pack('J', $len);
        }

        $masked = '';
        for ($i = 0; $i < $len; $i++) {
            $masked .= $payload[$i] ^ $maskKey[$i % 4];
        }

        return $frame . $maskKey . $masked;
    }
}

interface ExchangeAdapter
{
    public function name(): string;

    public function fetchExchangeSymbols(): array;

    public function fetchTicker24h(): array;

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array;

    public function fetchOrderBook(string $symbol, int $depth): array;

    public function supportsWebSocket(): bool;

    public function connectWebSocket(array $symbols, array $timeframes): ?WebSocketClient;

    public function handleWebSocketMessage(string $raw, MarketDataStore $store): void;
}

abstract class AbstractExchangeAdapter implements ExchangeAdapter
{
    protected array $timeframeMap = [];

    protected function mapTimeframe(string $timeframe): ?string
    {
        if (empty($this->timeframeMap)) {
            return $timeframe;
        }
        return $this->timeframeMap[$timeframe] ?? null;
    }

    public function supportsWebSocket(): bool
    {
        return false;
    }

    public function connectWebSocket(array $symbols, array $timeframes): ?WebSocketClient
    {
        return null;
    }

    public function handleWebSocketMessage(string $raw, MarketDataStore $store): void
    {
    }
}

final class BinanceAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '1h', '2h' => '2h', '4h' => '4h', '1D' => '1d',
    ];

    public function name(): string
    {
        return 'binance';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('binance', 1000);
        $res = HttpClient::request('GET', Config::binanceRestBase() . '/api/v3/exchangeInfo');
        $out = [];
        foreach ($res['json']['symbols'] ?? [] as $s) {
            if (($s['status'] ?? '') !== 'TRADING') {
                continue;
            }
            $out[] = [
                'symbol' => (string) ($s['symbol'] ?? ''),
                'base' => (string) ($s['baseAsset'] ?? ''),
                'quote' => (string) ($s['quoteAsset'] ?? ''),
                'status' => 'TRADING',
            ];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('binance', 1000);
        $res = HttpClient::request('GET', Config::binanceRestBase() . '/api/v3/ticker/24hr');
        $out = [];
        foreach ($res['json'] ?? [] as $t) {
            $symbol = (string) ($t['symbol'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $out[$symbol] = [
                'volume' => (float) ($t['quoteVolume'] ?? 0),
                'lastPrice' => (float) ($t['lastPrice'] ?? 0),
                'bid' => (float) ($t['bidPrice'] ?? 0),
                'ask' => (float) ($t['askPrice'] ?? 0),
                'priceChangePercent' => (float) ($t['priceChangePercent'] ?? 0),
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('binance', 1000);
        $interval = $this->mapTimeframe($timeframe);
        $url = Config::binanceRestBase() . '/api/v3/klines?' . http_build_query([
            'symbol' => $symbol, 'interval' => $interval, 'limit' => $limit,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach ($res['json'] ?? [] as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = new Candle(
                openTime: (int) $row[0],
                open: (float) $row[1],
                high: (float) $row[2],
                low: (float) $row[3],
                close: (float) $row[4],
                volume: (float) $row[5],
                timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('binance', 1000);
        $url = Config::binanceRestBase() . '/api/v3/depth?' . http_build_query(['symbol' => $symbol, 'limit' => $depth]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['asks'] ?? []),
        ];
    }

    public function supportsWebSocket(): bool
    {
        return true;
    }

    public function connectWebSocket(array $symbols, array $timeframes): ?WebSocketClient
    {
        $streams = [];
        foreach ($symbols as $symbol) {
            $lower = strtolower($symbol);
            $streams[] = "$lower@ticker";
            foreach ($timeframes as $tf) {
                $mapped = $this->mapTimeframe($tf);
                if ($mapped === null) {
                    continue;
                }
                $streams[] = "$lower@kline_" . $mapped;
            }
        }
        if (empty($streams)) {
            return null;
        }

        $url = Config::binanceWsBase() . '/stream?streams=' . implode('/', $streams);
        $client = new WebSocketClient();
        return $client->connect($url) ? $client : null;
    }

    public function handleWebSocketMessage(string $raw, MarketDataStore $store): void
    {
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['data']['e'])) {
            return;
        }
        $payload = $data['data'];
        $eventType = $payload['e'] ?? '';

        if ($eventType === 'kline' && isset($payload['k'])) {
            $k = $payload['k'];
            $symbol = (string) ($payload['s'] ?? '');
            $tfNative = (string) ($k['i'] ?? '');
            $timeframe = array_search($tfNative, $this->timeframeMap, true) ?: $tfNative;
            $candle = new Candle(
                openTime: (int) ($k['t'] ?? 0),
                open: (float) ($k['o'] ?? 0),
                high: (float) ($k['h'] ?? 0),
                low: (float) ($k['l'] ?? 0),
                close: (float) ($k['c'] ?? 0),
                volume: (float) ($k['v'] ?? 0),
                timeframe: $timeframe,
            );
            $store->upsertCandle('binance', $symbol, $timeframe, $candle, (bool) ($k['x'] ?? false));
        } elseif ($eventType === '24hrTicker') {
            $symbol = (string) ($payload['s'] ?? '');
            $store->upsertTicker('binance', $symbol, (float) ($payload['c'] ?? 0), (float) ($payload['b'] ?? 0), (float) ($payload['a'] ?? 0), (float) ($payload['q'] ?? 0));
        }
    }
}

final class MexcAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '60m', '4h' => '4h', '1D' => '1d',
    ];

    public function name(): string
    {
        return 'mexc';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('mexc', 500);
        $res = HttpClient::request('GET', Config::mexcRestBase() . '/api/v3/exchangeInfo');
        $out = [];
        foreach ($res['json']['symbols'] ?? [] as $s) {
            if (($s['status'] ?? $s['isSpotTradingAllowed'] ?? '') === false) {
                continue;
            }
            $out[] = [
                'symbol' => (string) ($s['symbol'] ?? ''),
                'base' => (string) ($s['baseAsset'] ?? ''),
                'quote' => (string) ($s['quoteAsset'] ?? ''),
                'status' => 'TRADING',
            ];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('mexc', 500);
        $res = HttpClient::request('GET', Config::mexcRestBase() . '/api/v3/ticker/24hr');
        $out = [];
        $rows = $res['json'] ?? [];
        if (isset($rows['symbol'])) {
            $rows = [$rows];
        }
        foreach ($rows as $t) {
            $symbol = (string) ($t['symbol'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $out[$symbol] = [
                'volume' => (float) ($t['quoteVolume'] ?? 0),
                'lastPrice' => (float) ($t['lastPrice'] ?? 0),
                'bid' => (float) ($t['bidPrice'] ?? 0),
                'ask' => (float) ($t['askPrice'] ?? 0),
                'priceChangePercent' => (float) ($t['priceChangePercent'] ?? 0),
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('mexc', 500);
        $interval = $this->mapTimeframe($timeframe);
        $url = Config::mexcRestBase() . '/api/v3/klines?' . http_build_query([
            'symbol' => $symbol, 'interval' => $interval, 'limit' => $limit,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach ($res['json'] ?? [] as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = new Candle(
                openTime: (int) $row[0],
                open: (float) $row[1],
                high: (float) $row[2],
                low: (float) $row[3],
                close: (float) $row[4],
                volume: (float) $row[5],
                timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('mexc', 500);
        $url = Config::mexcRestBase() . '/api/v3/depth?' . http_build_query(['symbol' => $symbol, 'limit' => $depth]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['asks'] ?? []),
        ];
    }

    public function supportsWebSocket(): bool
    {
        return true;
    }

    public function connectWebSocket(array $symbols, array $timeframes): ?WebSocketClient
    {
        $client = new WebSocketClient();
        if (!$client->connect(Config::mexcWsBase() . '/ws')) {
            return null;
        }

        foreach ($symbols as $symbol) {
            foreach ($timeframes as $tf) {
                $mapped = $this->mapTimeframe($tf);
                if ($mapped === null) {
                    continue;
                }
                $interval = str_replace('m', 'Min', $mapped);
                $client->send(json_encode([
                    'method' => 'SUBSCRIPTION',
                    'params' => ["spot@public.kline.v3.api@{$symbol}@{$interval}"],
                ]) ?: '');
            }
        }
        return $client;
    }

    public function handleWebSocketMessage(string $raw, MarketDataStore $store): void
    {
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['d']['k'])) {
            return;
        }
        $k = $data['d']['k'];
        $symbol = (string) ($data['s'] ?? '');
        $tfNative = (string) ($k['i'] ?? '');
        $timeframe = array_search($tfNative, $this->timeframeMap, true) ?: $tfNative;
        $candle = new Candle(
            openTime: (int) ($k['t'] ?? 0),
            open: (float) ($k['o'] ?? 0),
            high: (float) ($k['h'] ?? 0),
            low: (float) ($k['l'] ?? 0),
            close: (float) ($k['c'] ?? 0),
            volume: (float) ($k['v'] ?? 0),
            timeframe: $timeframe,
        );
        $store->upsertCandle('mexc', $symbol, $timeframe, $candle, true);
    }
}

final class GateAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '1h', '2h' => '2h', '4h' => '4h', '1D' => '1d',
    ];

    public function name(): string
    {
        return 'gate';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('gate', 600);
        $res = HttpClient::request('GET', Config::gateRestBase() . '/api/v4/spot/currency_pairs');
        $out = [];
        foreach ($res['json'] ?? [] as $s) {
            if (($s['trade_status'] ?? '') !== 'tradable') {
                continue;
            }
            $base = strtoupper((string) ($s['base'] ?? ''));
            $quote = strtoupper((string) ($s['quote'] ?? ''));
            $id = (string) ($s['id'] ?? '');
            if ($base === '' || $quote === '' || $id === '') {
                continue;
            }
            $out[] = ['symbol' => $id, 'base' => $base, 'quote' => $quote, 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('gate', 600);
        $res = HttpClient::request('GET', Config::gateRestBase() . '/api/v4/spot/tickers');
        $out = [];
        foreach ($res['json'] ?? [] as $t) {
            $symbol = (string) ($t['currency_pair'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $last = (float) ($t['last'] ?? 0);
            $bid = (float) ($t['highest_bid'] ?? 0);
            $ask = (float) ($t['lowest_ask'] ?? 0);
            $out[$symbol] = [
                'volume' => (float) ($t['quote_volume'] ?? 0),
                'lastPrice' => $last,
                'bid' => $bid > 0 ? $bid : $last,
                'ask' => $ask > 0 ? $ask : $last,
                'priceChangePercent' => (float) ($t['change_percentage'] ?? 0),
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('gate', 600);
        $url = Config::gateRestBase() . '/api/v4/spot/candlesticks?' . http_build_query([
            'currency_pair' => $symbol,
            'interval' => $interval,
            'limit' => min($limit, 1000),
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach ($res['json'] ?? [] as $row) {
            if (!is_array($row) || count($row) < 7) {
                continue;
            }
            $out[] = new Candle(
                openTime: ((int) $row[0]) * 1000,
                open: (float) $row[5],
                high: (float) $row[3],
                low: (float) $row[4],
                close: (float) $row[2],
                volume: (float) $row[6],
                timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('gate', 600);
        $url = Config::gateRestBase() . '/api/v4/spot/order_book?' . http_build_query([
            'currency_pair' => $symbol, 'limit' => min($depth, 100),
        ]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['asks'] ?? []),
        ];
    }
}

final class BitgetAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1min', '5m' => '5min', '15m' => '15min', '30m' => '30min', '1h' => '1h', '2h' => '2h', '4h' => '4h', '1D' => '1day',
    ];

    public function name(): string
    {
        return 'bitget';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('bitget', 600);
        $res = HttpClient::request('GET', Config::bitgetRestBase() . '/api/v2/spot/public/symbols');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $s) {
            if (($s['status'] ?? '') !== 'online') {
                continue;
            }
            $base = strtoupper((string) ($s['baseCoin'] ?? ''));
            $quote = strtoupper((string) ($s['quoteCoin'] ?? ''));
            $symbol = (string) ($s['symbol'] ?? '');
            if ($base === '' || $quote === '' || $symbol === '') {
                continue;
            }
            $out[] = ['symbol' => $symbol, 'base' => $base, 'quote' => $quote, 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('bitget', 600);
        $res = HttpClient::request('GET', Config::bitgetRestBase() . '/api/v2/spot/market/tickers');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $t) {
            $symbol = (string) ($t['symbol'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $last = (float) ($t['lastPr'] ?? 0);
            $bid = (float) ($t['bidPr'] ?? 0);
            $ask = (float) ($t['askPr'] ?? 0);
            $out[$symbol] = [
                'volume' => (float) ($t['quoteVolume'] ?? 0),
                'lastPrice' => $last,
                'bid' => $bid > 0 ? $bid : $last,
                'ask' => $ask > 0 ? $ask : $last,
                'priceChangePercent' => ((float) ($t['change24h'] ?? 0)) * 100,
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('bitget', 600);
        $url = Config::bitgetRestBase() . '/api/v2/spot/market/candles?' . http_build_query([
            'symbol' => $symbol,
            'granularity' => $interval,
            'limit' => min($limit, 1000),
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach ($res['json']['data'] ?? [] as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = new Candle(
                openTime: (int) $row[0],
                open: (float) $row[1], high: (float) $row[2], low: (float) $row[3], close: (float) $row[4],
                volume: (float) $row[5], timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('bitget', 600);
        $url = Config::bitgetRestBase() . '/api/v2/spot/market/orderbook?' . http_build_query([
            'symbol' => $symbol, 'limit' => min($depth, 150),
        ]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['data']['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['data']['asks'] ?? []),
        ];
    }
}

final class HtxAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1min', '5m' => '5min', '15m' => '15min', '30m' => '30min', '1h' => '60min', '4h' => '4hour', '1D' => '1day',
    ];

    public function name(): string
    {
        return 'htx';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('htx', 600);
        $res = HttpClient::request('GET', Config::htxRestBase() . '/v1/common/symbols');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $s) {
            if (($s['state'] ?? '') !== 'online') {
                continue;
            }
            $base = strtoupper((string) ($s['base-currency'] ?? ''));
            $quote = strtoupper((string) ($s['quote-currency'] ?? ''));
            $symbol = (string) ($s['symbol'] ?? '');
            if ($base === '' || $quote === '' || $symbol === '') {
                continue;
            }
            $out[] = ['symbol' => $symbol, 'base' => $base, 'quote' => $quote, 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('htx', 600);
        $res = HttpClient::request('GET', Config::htxRestBase() . '/market/tickers');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $t) {
            $symbol = (string) ($t['symbol'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $last = (float) ($t['close'] ?? 0);
            $open = (float) ($t['open'] ?? 0);
            $bid = (float) ($t['bid'] ?? 0);
            $ask = (float) ($t['ask'] ?? 0);
            $out[$symbol] = [
                'volume' => (float) ($t['vol'] ?? 0),
                'lastPrice' => $last,
                'bid' => $bid > 0 ? $bid : $last,
                'ask' => $ask > 0 ? $ask : $last,
                'priceChangePercent' => $open > 0 ? (($last - $open) / $open) * 100 : 0.0,
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('htx', 600);
        $url = Config::htxRestBase() . '/market/history/kline?' . http_build_query([
            'symbol' => $symbol,
            'period' => $interval,
            'size' => min($limit, 2000),
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach (array_reverse($res['json']['data'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = new Candle(
                openTime: ((int) ($row['id'] ?? 0)) * 1000,
                open: (float) ($row['open'] ?? 0), high: (float) ($row['high'] ?? 0),
                low: (float) ($row['low'] ?? 0), close: (float) ($row['close'] ?? 0),
                volume: (float) ($row['vol'] ?? 0), timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('htx', 600);
        $url = Config::htxRestBase() . '/market/depth?' . http_build_query([
            'symbol' => $symbol, 'type' => 'step0', 'depth' => in_array($depth, [5, 10, 20], true) ? $depth : 20,
        ]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['tick']['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['tick']['asks'] ?? []),
        ];
    }
}

final class CryptoCompareAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => 'minute', '5m' => 'minute', '15m' => 'minute', '30m' => 'minute',
        '1h' => 'hour', '2h' => 'hour', '4h' => 'hour', '1D' => 'day', '1W' => 'day',
    ];

    public function name(): string
    {
        return 'cryptocompare';
    }

    private function headers(): array
    {
        $key = Config::cryptocompareApiKey();
        return $key !== '' ? ['authorization' => 'Apikey ' . $key] : [];
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('cryptocompare', 60);
        $url = Config::cryptocompareRestBase() . '/data/top/totalvolfull?' . http_build_query(['limit' => 100, 'tsym' => 'USDT']);
        $res = HttpClient::request('GET', $url, $this->headers());
        $out = [];
        foreach ($res['json']['Data'] ?? [] as $row) {
            $base = (string) ($row['CoinInfo']['Name'] ?? '');
            if ($base === '') {
                continue;
            }
            $out[] = ['symbol' => $base . 'USDT', 'base' => $base, 'quote' => 'USDT', 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        $symbols = $this->fetchExchangeSymbols();
        if (empty($symbols)) {
            return [];
        }
        $bases = array_column($symbols, 'base');
        RateLimiter::acquire('cryptocompare', 60);
        $url = Config::cryptocompareRestBase() . '/data/pricemultifull?' . http_build_query(['fsyms' => implode(',', $bases), 'tsyms' => 'USDT']);
        $res = HttpClient::request('GET', $url, $this->headers());
        $out = [];
        foreach ($res['json']['RAW'] ?? [] as $base => $quotes) {
            $d = $quotes['USDT'] ?? null;
            if ($d === null) {
                continue;
            }
            $price = (float) ($d['PRICE'] ?? 0);
            $out[$base . 'USDT'] = [
                'volume' => (float) ($d['VOLUME24HOURTO'] ?? 0),
                'lastPrice' => $price,

                'bid' => $price,
                'ask' => $price,
                'priceChangePercent' => (float) ($d['CHANGEPCT24HOUR'] ?? 0),
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        [$base, $quote] = $this->splitSymbol($symbol);
        $endpoint = match ($interval) {
            'minute' => '/data/v2/histominute',
            'day' => '/data/v2/histoday',
            default => '/data/v2/histohour',
        };
        RateLimiter::acquire('cryptocompare', 60);
        $url = Config::cryptocompareRestBase() . $endpoint . '?' . http_build_query([
            'fsym' => $base, 'tsym' => $quote, 'limit' => $limit, 'aggregate' => $this->aggregateFor($timeframe),
        ]);
        $res = HttpClient::request('GET', $url, $this->headers());
        $out = [];
        foreach ($res['json']['Data']['Data'] ?? [] as $row) {
            $out[] = new Candle(
                openTime: ((int) ($row['time'] ?? 0)) * 1000,
                open: (float) ($row['open'] ?? 0),
                high: (float) ($row['high'] ?? 0),
                low: (float) ($row['low'] ?? 0),
                close: (float) ($row['close'] ?? 0),
                volume: (float) ($row['volumeto'] ?? 0),
                timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        return ['bids' => [], 'asks' => []];
    }

    private function aggregateFor(string $timeframe): int
    {
        return match ($timeframe) {
            '5m' => 5, '15m' => 15, '4h' => 4, '1W' => 7,
            default => 1,
        };
    }

    private function splitSymbol(string $symbol): array
    {
        if (str_ends_with($symbol, 'USDT')) {
            return [substr($symbol, 0, -4), 'USDT'];
        }
        return [$symbol, 'USDT'];
    }
}

final class BybitAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1', '5m' => '5', '15m' => '15', '30m' => '30', '1h' => '60', '2h' => '120', '4h' => '240', '1D' => 'D',
    ];

    public function name(): string
    {
        return 'bybit';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('bybit', 600);
        $res = HttpClient::request('GET', Config::bybitRestBase() . '/v5/market/instruments-info?category=spot');
        $out = [];
        foreach ($res['json']['result']['list'] ?? [] as $s) {
            if (($s['status'] ?? '') !== 'Trading') {
                continue;
            }
            $base = (string) ($s['baseCoin'] ?? '');
            $quote = (string) ($s['quoteCoin'] ?? '');
            if ($base === '' || $quote === '') {
                continue;
            }
            $out[] = ['symbol' => (string) ($s['symbol'] ?? ''), 'base' => $base, 'quote' => $quote, 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('bybit', 600);
        $res = HttpClient::request('GET', Config::bybitRestBase() . '/v5/market/tickers?category=spot');
        $out = [];
        foreach ($res['json']['result']['list'] ?? [] as $t) {
            $symbol = (string) ($t['symbol'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $last = (float) ($t['lastPrice'] ?? 0);
            $bid = (float) ($t['bid1Price'] ?? 0);
            $ask = (float) ($t['ask1Price'] ?? 0);
            $out[$symbol] = [
                'volume' => (float) ($t['turnover24h'] ?? 0),
                'lastPrice' => $last,
                'bid' => $bid > 0 ? $bid : $last,
                'ask' => $ask > 0 ? $ask : $last,
                'priceChangePercent' => ((float) ($t['price24hPcnt'] ?? 0)) * 100,
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('bybit', 600);
        $url = Config::bybitRestBase() . '/v5/market/kline?' . http_build_query([
            'category' => 'spot', 'symbol' => $symbol, 'interval' => $interval, 'limit' => $limit,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach (array_reverse($res['json']['result']['list'] ?? []) as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = new Candle(
                openTime: (int) $row[0], open: (float) $row[1], high: (float) $row[2],
                low: (float) $row[3], close: (float) $row[4], volume: (float) $row[5], timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('bybit', 600);
        $url = Config::bybitRestBase() . '/v5/market/orderbook?' . http_build_query([
            'category' => 'spot', 'symbol' => $symbol, 'limit' => min($depth, 50),
        ]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['result']['b'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['result']['a'] ?? []),
        ];
    }
}

final class OkxAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '30m' => '30m', '1h' => '1H', '2h' => '2H', '4h' => '4H', '1D' => '1D',
    ];

    public function name(): string
    {
        return 'okx';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('okx', 600);
        $res = HttpClient::request('GET', Config::okxRestBase() . '/api/v5/public/instruments?instType=SPOT');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $s) {
            if (($s['state'] ?? '') !== 'live') {
                continue;
            }
            $base = (string) ($s['baseCcy'] ?? '');
            $quote = (string) ($s['quoteCcy'] ?? '');
            if ($base === '' || $quote === '') {
                continue;
            }
            $out[] = ['symbol' => (string) ($s['instId'] ?? ''), 'base' => $base, 'quote' => $quote, 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('okx', 600);
        $res = HttpClient::request('GET', Config::okxRestBase() . '/api/v5/market/tickers?instType=SPOT');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $t) {
            $symbol = (string) ($t['instId'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $last = (float) ($t['last'] ?? 0);
            $open24h = (float) ($t['open24h'] ?? 0);
            $bid = (float) ($t['bidPx'] ?? 0);
            $ask = (float) ($t['askPx'] ?? 0);
            $out[$symbol] = [
                'volume' => (float) ($t['volCcy24h'] ?? 0),
                'lastPrice' => $last,
                'bid' => $bid > 0 ? $bid : $last,
                'ask' => $ask > 0 ? $ask : $last,
                'priceChangePercent' => $open24h > 0 ? (($last - $open24h) / $open24h) * 100 : 0.0,
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('okx', 600);
        $url = Config::okxRestBase() . '/api/v5/market/candles?' . http_build_query([
            'instId' => $symbol, 'bar' => $interval, 'limit' => $limit,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach (array_reverse($res['json']['data'] ?? []) as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = new Candle(
                openTime: (int) $row[0], open: (float) $row[1], high: (float) $row[2],
                low: (float) $row[3], close: (float) $row[4], volume: (float) $row[5], timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('okx', 600);
        $url = Config::okxRestBase() . '/api/v5/market/books?' . http_build_query(['instId' => $symbol, 'sz' => min($depth, 400)]);
        $res = HttpClient::request('GET', $url);
        $book = $res['json']['data'][0] ?? [];
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $book['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $book['asks'] ?? []),
        ];
    }
}

final class KucoinAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1min', '5m' => '5min', '15m' => '15min', '30m' => '30min', '1h' => '1hour', '2h' => '2hour', '4h' => '4hour', '1D' => '1day',
    ];

    private const INTERVAL_SECONDS = [
        '1m' => 60, '5m' => 300, '15m' => 900, '1h' => 3600, '4h' => 14400, '1D' => 86400,
    ];

    public function name(): string
    {
        return 'kucoin';
    }

    public function fetchExchangeSymbols(): array
    {
        RateLimiter::acquire('kucoin', 600);
        $res = HttpClient::request('GET', Config::kucoinRestBase() . '/api/v1/symbols');
        $out = [];
        foreach ($res['json']['data'] ?? [] as $s) {
            if (($s['enableTrading'] ?? false) !== true) {
                continue;
            }
            $base = (string) ($s['baseCurrency'] ?? '');
            $quote = (string) ($s['quoteCurrency'] ?? '');
            if ($base === '' || $quote === '') {
                continue;
            }
            $out[] = ['symbol' => (string) ($s['symbol'] ?? ''), 'base' => $base, 'quote' => $quote, 'status' => 'TRADING'];
        }
        return $out;
    }

    public function fetchTicker24h(): array
    {
        RateLimiter::acquire('kucoin', 600);
        $res = HttpClient::request('GET', Config::kucoinRestBase() . '/api/v1/market/allTickers');
        $out = [];
        foreach ($res['json']['data']['ticker'] ?? [] as $t) {
            $symbol = (string) ($t['symbol'] ?? '');
            if ($symbol === '') {
                continue;
            }
            $last = (float) ($t['last'] ?? 0);
            $bid = (float) ($t['buy'] ?? 0);
            $ask = (float) ($t['sell'] ?? 0);
            $out[$symbol] = [
                'volume' => (float) ($t['volValue'] ?? 0),
                'lastPrice' => $last,
                'bid' => $bid > 0 ? $bid : $last,
                'ask' => $ask > 0 ? $ask : $last,
                'priceChangePercent' => ((float) ($t['changeRate'] ?? 0)) * 100,
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return [];
        }
        RateLimiter::acquire('kucoin', 600);
        $intervalSeconds = self::INTERVAL_SECONDS[$timeframe] ?? 3600;
        $endAt = time();
        $startAt = $endAt - ($limit * $intervalSeconds);
        $url = Config::kucoinRestBase() . '/api/v1/market/candles?' . http_build_query([
            'symbol' => $symbol, 'type' => $interval, 'startAt' => $startAt, 'endAt' => $endAt,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach (array_reverse($res['json']['data'] ?? []) as $row) {
            if (!is_array($row) || count($row) < 6) {
                continue;
            }
            $out[] = new Candle(
                openTime: ((int) $row[0]) * 1000, open: (float) $row[1], high: (float) $row[3],
                low: (float) $row[4], close: (float) $row[2], volume: (float) $row[5], timeframe: $timeframe,
            );
        }
        return $out;
    }

    public function fetchOrderBook(string $symbol, int $depth): array
    {
        RateLimiter::acquire('kucoin', 600);
        $url = Config::kucoinRestBase() . '/api/v1/market/orderbook/level2_20?' . http_build_query(['symbol' => $symbol]);
        $res = HttpClient::request('GET', $url);
        return [
            'bids' => array_map(static fn($b) => [(float) $b[0], (float) $b[1]], $res['json']['data']['bids'] ?? []),
            'asks' => array_map(static fn($a) => [(float) $a[0], (float) $a[1]], $res['json']['data']['asks'] ?? []),
        ];
    }
}

final class ExchangeManager
{
    private array $adapters = [];

    private array $health = [];

    private const FAILURE_THRESHOLD = 3;
    private const BASE_BACKOFF_SECONDS = 10;
    private const MAX_BACKOFF_SECONDS = 600;

    public function __construct()
    {
        $enabled = Config::enabledExchanges();
        if (in_array('binance', $enabled, true)) {
            $this->register(new BinanceAdapter());
        }
        if (in_array('mexc', $enabled, true)) {
            $this->register(new MexcAdapter());
        }
        if (in_array('gate', $enabled, true)) {
            $this->register(new GateAdapter());
        }
        if (in_array('bitget', $enabled, true)) {
            $this->register(new BitgetAdapter());
        }
        if (in_array('htx', $enabled, true)) {
            $this->register(new HtxAdapter());
        }
        if (in_array('cryptocompare', $enabled, true)) {
            $this->register(new CryptoCompareAdapter());
        }
        if (in_array('bybit', $enabled, true)) {
            $this->register(new BybitAdapter());
        }
        if (in_array('okx', $enabled, true)) {
            $this->register(new OkxAdapter());
        }
        if (in_array('kucoin', $enabled, true)) {
            $this->register(new KucoinAdapter());
        }
    }

    public function register(ExchangeAdapter $adapter): void
    {
        $this->adapters[$adapter->name()] = $adapter;
        $this->health[$adapter->name()] = ['failures' => 0, 'openUntil' => 0];
    }

    public function adapters(): array
    {
        return $this->adapters;
    }

    public function get(string $name): ?ExchangeAdapter
    {
        return $this->adapters[$name] ?? null;
    }

    public function isHealthy(string $name): bool
    {
        return time() >= ($this->health[$name]['openUntil'] ?? 0);
    }

    public function withIsolation(string $name, callable $fn): mixed
    {
        $adapter = $this->adapters[$name] ?? null;
        if ($adapter === null) {
            return null;
        }

        if (!$this->isHealthy($name)) {
            return null;
        }

        try {
            $result = $fn($adapter);
            $this->health[$name] = ['failures' => 0, 'openUntil' => 0];
            return $result;
        } catch (Throwable $e) {
            $failures = ($this->health[$name]['failures'] ?? 0) + 1;
            $backoff = min(self::BASE_BACKOFF_SECONDS * (2 ** min($failures, 6)), self::MAX_BACKOFF_SECONDS);
            $openUntil = $failures >= self::FAILURE_THRESHOLD ? time() + $backoff : 0;
            $this->health[$name] = ['failures' => $failures, 'openUntil' => $openUntil];
            Logger::error('exchange', "$name call failed", ['error' => $e->getMessage(), 'failures' => $failures, 'openUntil' => $openUntil]);
            return null;
        }
    }

    public function healthSnapshot(): array
    {
        $out = [];
        foreach ($this->adapters as $name => $adapter) {
            $h = $this->health[$name];
            $out[$name] = $h['openUntil'] > time() ? 'قطع' : ($h['failures'] > 0 ? 'ناپایدار' : 'سالم');
        }
        return $out;
    }
}

final class ExchangeRepository
{
    private static array $cache = [];

    public static function idForName(string $name): ?int
    {
        if (isset(self::$cache[$name])) {
            return self::$cache[$name];
        }
        $stmt = Database::pdo()->prepare('SELECT id FROM exchanges WHERE name = :name LIMIT 1');
        $stmt->execute([':name' => $name]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }
        self::$cache[$name] = (int) $id;
        return (int) $id;
    }
}

final class CandleManager
{
    public function upsertMany(string $exchange, string $symbol, string $timeframe, array $candles): void
    {
        $total = count($candles);
        $candles = array_values(array_filter($candles, static fn(Candle $c) => $c->isSane()));
        if ($total > 0 && count($candles) < $total) {
            Logger::warning('market_data', 'discarded malformed candles', [
                'exchange' => $exchange,
                'symbol' => $symbol,
                'timeframe' => $timeframe,
                'discarded' => $total - count($candles),
                'of' => $total,
            ]);
        }
        if (empty($candles)) {
            return;
        }

        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null || empty($candles)) {
            return;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO candles (exchange_id, symbol, timeframe, open_time, open_price, high_price, low_price, close_price, volume)
             VALUES (:eid, :symbol, :tf, :ot, :o, :h, :l, :c, :v)
             ON CONFLICT(exchange_id, symbol, timeframe, open_time) DO UPDATE SET
                open_price = excluded.open_price, high_price = excluded.high_price,
                low_price = excluded.low_price, close_price = excluded.close_price, volume = excluded.volume'
        );
        $pdo->beginTransaction();
        try {
            foreach ($candles as $candle) {
                $stmt->execute([
                    ':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe, ':ot' => $candle->openTime,
                    ':o' => $candle->open, ':h' => $candle->high, ':l' => $candle->low, ':c' => $candle->close, ':v' => $candle->volume,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function recent(string $exchange, string $symbol, string $timeframe, int $limit): array
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM candles WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf
             ORDER BY open_time DESC LIMIT :lim'
        );
        $stmt->bindValue(':eid', $exchangeId, PDO::PARAM_INT);
        $stmt->bindValue(':symbol', $symbol);
        $stmt->bindValue(':tf', $timeframe);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
        return array_map(static fn($r) => new Candle(
            (int) $r['open_time'], (float) $r['open_price'], (float) $r['high_price'],
            (float) $r['low_price'], (float) $r['close_price'], (float) $r['volume'], $timeframe
        ), $rows);
    }

    public function latestOpenTime(string $exchange, string $symbol, string $timeframe): ?int
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT MAX(open_time) FROM candles WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf'
        );
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? null : (int) $v;
    }

    public function pruneSeries(string $exchange, string $symbol, string $timeframe, int $keep): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null || $keep <= 0) {
            return;
        }
        $cutoff = (time() - Candle::timeframeSeconds($timeframe) * $keep) * 1000;
        Database::pdo()->prepare(
            'DELETE FROM candles WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf AND open_time < :cutoff'
        )->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe, ':cutoff' => $cutoff]);
    }
}

final class TickerManager
{
    public function upsert(string $exchange, string $symbol, float $price, float $bid, float $ask, float $volume24h): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return;
        }
        $spread = $price > 0 && $ask > 0 && $bid > 0 ? (($ask - $bid) / $price) * 100 : 0.0;
        $stmt = Database::pdo()->prepare(
            'INSERT INTO market_data (exchange_id, symbol, price, bid, ask, spread_pct, volume_24h, updated_at)
             VALUES (:eid, :symbol, :price, :bid, :ask, :spread, :vol, :now)
             ON CONFLICT(exchange_id, symbol) DO UPDATE SET
                price = excluded.price, bid = excluded.bid, ask = excluded.ask,
                spread_pct = excluded.spread_pct, volume_24h = excluded.volume_24h, updated_at = excluded.updated_at'
        );
        $stmt->execute([
            ':eid' => $exchangeId, ':symbol' => $symbol, ':price' => $price, ':bid' => $bid,
            ':ask' => $ask, ':spread' => $spread, ':vol' => $volume24h, ':now' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get(string $exchange, string $symbol): ?array
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return null;
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM market_data WHERE exchange_id = :eid AND symbol = :symbol LIMIT 1');
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}

final class OrderbookManager
{
    private array $cache = [];

    public function set(string $exchange, string $symbol, array $book): void
    {
        $this->cache["$exchange:$symbol"] = $book;
    }

    public function get(string $exchange, string $symbol): ?array
    {
        return $this->cache["$exchange:$symbol"] ?? null;
    }

    public function spreadPercent(array $book, float $lastPrice): float
    {
        $bestBid = $book['bids'][0][0] ?? 0.0;
        $bestAsk = $book['asks'][0][0] ?? 0.0;
        if ($bestBid <= 0 || $bestAsk <= 0 || $lastPrice <= 0) {
            return 0.0;
        }
        return (($bestAsk - $bestBid) / $lastPrice) * 100;
    }
}

final class MarketDataStore
{
    private CandleManager $candles;
    private TickerManager $tickers;
    private OrderbookManager $orderbooks;

    public function __construct()
    {
        $this->candles = new CandleManager();
        $this->tickers = new TickerManager();
        $this->orderbooks = new OrderbookManager();
    }

    public function candleManager(): CandleManager
    {
        return $this->candles;
    }

    public function tickerManager(): TickerManager
    {
        return $this->tickers;
    }

    public function orderbookManager(): OrderbookManager
    {
        return $this->orderbooks;
    }

    public function upsertCandle(string $exchange, string $symbol, string $timeframe, Candle $candle, bool $closed): void
    {
        if ($closed) {
            $this->candles->upsertMany($exchange, $symbol, $timeframe, [$candle]);
        }
    }

    public function upsertTicker(string $exchange, string $symbol, float $price, float $bid, float $ask, float $volume24h): void
    {
        $this->tickers->upsert($exchange, $symbol, $price, $bid, $ask, $volume24h);
    }

    public function latestPrice(string $exchange, string $symbol): float
    {
        $ticker = $this->tickers->get($exchange, $symbol);
        $price = (float) ($ticker['price'] ?? 0);
        if ($price > 0) {
            return $price;
        }
        return $this->lastCandleClose($exchange, $symbol);
    }

    private function lastCandleClose(string $exchange, string $symbol): float
    {
        $best = 0.0;
        $bestTime = -1;
        foreach (Config::timeframes() as $tf) {
            $candles = $this->candles->recent($exchange, $symbol, $tf, 1);
            $candle = $candles[0] ?? null;
            if ($candle instanceof Candle && $candle->openTime > $bestTime && $candle->close > 0) {
                $best = $candle->close;
                $bestTime = $candle->openTime;
            }
        }
        return $best;
    }

    public function buildSnapshot(string $exchange, string $symbol, array $timeframes, int $candleLimit = 200): MarketSnapshot
    {
        $ticker = $this->tickers->get($exchange, $symbol);
        $now = time();
        $candlesByTf = [];
        foreach ($timeframes as $tf) {
            $series = $this->candles->recent($exchange, $symbol, $tf, $candleLimit + 1);
            $last = end($series);
            if ($last instanceof Candle && intdiv($last->openTime, 1000) + Candle::timeframeSeconds($tf) > $now) {
                array_pop($series);
            }
            $candlesByTf[$tf] = count($series) > $candleLimit ? array_slice($series, -$candleLimit) : $series;
        }
        $price = (float) ($ticker['price'] ?? 0);
        if ($price <= 0) {
            $lastTfCandles = $candlesByTf[array_key_last($candlesByTf)] ?? [];
            $lastClose = empty($lastTfCandles) ? null : $lastTfCandles[count($lastTfCandles) - 1];
            if ($lastClose instanceof Candle) {
                $price = $lastClose->close;
            }
        }
        $bid = (float) ($ticker['bid'] ?? 0);
        $ask = (float) ($ticker['ask'] ?? 0);

        return new MarketSnapshot(
            exchange: $exchange,
            symbol: $symbol,
            price: $price,
            volume: (float) ($ticker['volume_24h'] ?? 0),
            bid: $bid,
            ask: $ask,
            spreadPct: (float) ($ticker['spread_pct'] ?? 0),
            timestamp: time(),
            candles: $candlesByTf,
        );
    }
}

interface Indicator
{
    public function name(): string;

    public function calculate(array $candles): array;
}

final class IndicatorEngine
{
    private array $registry = [];

    public function register(Indicator $indicator): void
    {
        $this->registry[$indicator->name()] = $indicator;
    }

    public function unregister(string $name): void
    {
        unset($this->registry[$name]);
    }

    public function registeredNames(): array
    {
        return array_keys($this->registry);
    }

    public function runAll(array $candles): array
    {
        $out = [];
        foreach ($this->registry as $name => $indicator) {
            try {
                $out[$name] = $indicator->calculate($candles);
            } catch (Throwable $e) {
                Logger::warning('indicator', "indicator '$name' failed", ['error' => $e->getMessage()]);
            }
        }
        return $out;
    }
}

final class SwingPivots
{
    public static function detect(array $candles, int $lookback = 5): array
    {
        $highs = [];
        $lows = [];
        $n = count($candles);
        for ($i = $lookback; $i < $n - $lookback; $i++) {
            $isHigh = true;
            $isLow = true;
            for ($j = $i - $lookback; $j <= $i + $lookback; $j++) {
                if ($j === $i) {
                    continue;
                }
                if ($candles[$j]->high >= $candles[$i]->high) {
                    $isHigh = false;
                }
                if ($candles[$j]->low <= $candles[$i]->low) {
                    $isLow = false;
                }
            }
            if ($isHigh) {
                $highs[] = ['index' => $i, 'candle' => $candles[$i]];
            }
            if ($isLow) {
                $lows[] = ['index' => $i, 'candle' => $candles[$i]];
            }
        }
        return ['highs' => $highs, 'lows' => $lows];
    }

    public static function atr(array $candles, int $period = 14): float
    {
        $n = count($candles);
        if ($n < 2) {
            return 0.0;
        }
        $trs = [];
        for ($i = max(1, $n - $period); $i < $n; $i++) {
            $prevClose = $candles[$i - 1]->close;
            $tr = max(
                $candles[$i]->high - $candles[$i]->low,
                abs($candles[$i]->high - $prevClose),
                abs($candles[$i]->low - $prevClose)
            );
            $trs[] = $tr;
        }
        return empty($trs) ? 0.0 : array_sum($trs) / count($trs);
    }

    public static function structuralTrend(array $candles, int $lookback = 5): string
    {
        $pivots = self::detect($candles, $lookback);
        $highs = $pivots['highs'];
        $lows = $pivots['lows'];
        if (count($highs) < 2 || count($lows) < 2) {
            return 'neutral';
        }
        $lastTwoHighs = array_slice($highs, -2);
        $lastTwoLows = array_slice($lows, -2);
        $higherHigh = $lastTwoHighs[1]['candle']->high > $lastTwoHighs[0]['candle']->high;
        $higherLow = $lastTwoLows[1]['candle']->low > $lastTwoLows[0]['candle']->low;
        $lowerHigh = $lastTwoHighs[1]['candle']->high < $lastTwoHighs[0]['candle']->high;
        $lowerLow = $lastTwoLows[1]['candle']->low < $lastTwoLows[0]['candle']->low;

        if ($higherHigh && $higherLow) {
            return 'bullish';
        }
        if ($lowerHigh && $lowerLow) {
            return 'bearish';
        }
        return 'neutral';
    }
}

final class Ta
{
    public static function sma(array $src, int $len): array
    {
        $out = [];
        $sum = 0.0;
        $nans = 0;
        $n = count($src);
        for ($i = 0; $i < $n; $i++) {
            if (is_nan($src[$i])) {
                $nans++;
            } else {
                $sum += $src[$i];
            }
            if ($i >= $len) {
                $dropped = $src[$i - $len];
                if (is_nan($dropped)) {
                    $nans--;
                } else {
                    $sum -= $dropped;
                }
            }
            $out[$i] = ($i >= $len - 1 && $nans === 0) ? $sum / $len : NAN;
        }
        return $out;
    }

    public static function ema(array $src, int $len): array
    {
        $out = [];
        $k = 2 / ($len + 1);
        $prev = null;
        foreach ($src as $i => $v) {
            if ($prev === null) {
                if ($i < $len - 1) {
                    $out[$i] = NAN;
                    continue;
                }
                $prev = array_sum(array_slice($src, 0, $len)) / $len;
                $out[$i] = $prev;
                continue;
            }
            $prev = ($v - $prev) * $k + $prev;
            $out[$i] = $prev;
        }
        return $out;
    }

    public static function alma(array $src, int $len, float $offset = 0.85, float $sigma = 6.0): array
    {
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        if ($len < 1 || $n < $len) {
            return $out;
        }
        $m = $offset * ($len - 1);
        $sd = $len / $sigma;
        $weights = [];
        $norm = 0.0;
        for ($i = 0; $i < $len; $i++) {
            $w = exp(-1 * (($i - $m) ** 2) / (2 * $sd * $sd));
            $weights[$i] = $w;
            $norm += $w;
        }
        if ($norm <= 0) {
            return $out;
        }
        for ($b = $len - 1; $b < $n; $b++) {
            $sum = 0.0;
            for ($i = 0; $i < $len; $i++) {
                $sum += $src[$b - ($len - 1) + $i] * $weights[$i];
            }
            $out[$b] = $sum / $norm;
        }
        return $out;
    }

    public static function rsi(array $src, int $len = 14): array
    {
        $n = count($src);
        $out = array_fill(0, $n, NAN);
        if ($n <= $len) {
            return $out;
        }
        $gain = 0.0;
        $loss = 0.0;
        for ($i = 1; $i <= $len; $i++) {
            $d = $src[$i] - $src[$i - 1];
            $gain += max(0.0, $d);
            $loss += max(0.0, -$d);
        }
        $gain /= $len;
        $loss /= $len;
        $out[$len] = $loss == 0.0 ? 100.0 : 100 - (100 / (1 + $gain / $loss));
        for ($i = $len + 1; $i < $n; $i++) {
            $d = $src[$i] - $src[$i - 1];
            $gain = ($gain * ($len - 1) + max(0.0, $d)) / $len;
            $loss = ($loss * ($len - 1) + max(0.0, -$d)) / $len;
            $out[$i] = $loss == 0.0 ? 100.0 : 100 - (100 / (1 + $gain / $loss));
        }
        return $out;
    }

    public static function stdev(array $src, int $len): float
    {
        $slice = array_slice($src, -$len);
        $n = count($slice);
        if ($n < 2) {
            return 0.0;
        }
        $mean = array_sum($slice) / $n;
        $sum = 0.0;
        foreach ($slice as $v) {
            $sum += ($v - $mean) ** 2;
        }
        return sqrt($sum / $n);
    }

    public static function vwap(array $candles): array
    {
        $out = [];
        $pv = 0.0;
        $vol = 0.0;
        $day = null;
        foreach ($candles as $i => $c) {
            $d = (int) floor($c->openTime / 86400000);
            if ($day !== $d) {
                $day = $d;
                $pv = 0.0;
                $vol = 0.0;
            }
            $typical = ($c->high + $c->low + $c->close) / 3;
            $pv += $typical * $c->volume;
            $vol += $c->volume;
            $out[$i] = $vol > 0 ? $pv / $vol : NAN;
        }
        return $out;
    }

    public static function slope(array $src, int $len): float
    {
        $slice = array_slice($src, -$len);
        $n = count($slice);
        if ($n < 2) {
            return 0.0;
        }
        $sx = $sy = $sxy = $sxx = 0.0;
        foreach ($slice as $i => $y) {
            $sx += $i;
            $sy += $y;
            $sxy += $i * $y;
            $sxx += $i * $i;
        }
        $den = $n * $sxx - $sx * $sx;
        return $den == 0.0 ? 0.0 : ($n * $sxy - $sx * $sy) / $den;
    }

    public static function percentile(array $src, int $len, float $pct): float
    {
        $slice = array_slice($src, -$len);
        if (empty($slice)) {
            return NAN;
        }
        sort($slice);
        $n = count($slice);
        $rank = ($pct / 100) * ($n - 1);
        $lo = (int) floor($rank);
        $hi = (int) ceil($rank);
        if ($lo === $hi) {
            return $slice[$lo];
        }
        return $slice[$lo] + ($slice[$hi] - $slice[$lo]) * ($rank - $lo);
    }

    public static function percentRank(array $series, float $value): float
    {
        $n = count($series);
        if ($n === 0) {
            return NAN;
        }
        $below = 0;
        foreach ($series as $v) {
            if (!is_nan($v) && $v <= $value) {
                $below++;
            }
        }
        return ($below / $n) * 100;
    }

    public static function highest(array $candles, int $len, int $offset = 0): float
    {
        $slice = array_slice($candles, -($len + $offset), $len);
        $out = -INF;
        foreach ($slice as $c) {
            $out = max($out, $c->high);
        }
        return $out === -INF ? NAN : $out;
    }

    public static function lowest(array $candles, int $len, int $offset = 0): float
    {
        $slice = array_slice($candles, -($len + $offset), $len);
        $out = INF;
        foreach ($slice as $c) {
            $out = min($out, $c->low);
        }
        return $out === INF ? NAN : $out;
    }

    public static function closes(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->close, $candles);
    }

    public static function volumes(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->volume, $candles);
    }

    public static function pivots(array $src, int $left, int $right, bool $high): array
    {
        $out = [];
        $n = count($src);
        for ($i = $left; $i < $n - $right; $i++) {
            $ok = true;
            for ($j = $i - $left; $j <= $i + $right; $j++) {
                if ($j === $i) {
                    continue;
                }
                if ($high ? $src[$j] >= $src[$i] : $src[$j] <= $src[$i]) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $out[] = $i;
            }
        }
        return $out;
    }

    public static function adx(array $candles, int $period = 14): float
    {
        $n = count($candles);
        if ($n < $period * 2 + 1) {
            return NAN;
        }

        $trs = [];
        $plusDms = [];
        $minusDms = [];
        for ($i = 1; $i < $n; $i++) {
            $up = $candles[$i]->high - $candles[$i - 1]->high;
            $down = $candles[$i - 1]->low - $candles[$i]->low;
            $plusDms[] = ($up > $down && $up > 0) ? $up : 0.0;
            $minusDms[] = ($down > $up && $down > 0) ? $down : 0.0;
            $trs[] = max(
                $candles[$i]->high - $candles[$i]->low,
                abs($candles[$i]->high - $candles[$i - 1]->close),
                abs($candles[$i]->low - $candles[$i - 1]->close)
            );
        }

        $smoothedTr = array_sum(array_slice($trs, 0, $period));
        $smoothedPlusDm = array_sum(array_slice($plusDms, 0, $period));
        $smoothedMinusDm = array_sum(array_slice($minusDms, 0, $period));

        $dxs = [];
        $count = count($trs);
        for ($i = $period; $i < $count; $i++) {
            $smoothedTr = $smoothedTr - ($smoothedTr / $period) + $trs[$i];
            $smoothedPlusDm = $smoothedPlusDm - ($smoothedPlusDm / $period) + $plusDms[$i];
            $smoothedMinusDm = $smoothedMinusDm - ($smoothedMinusDm / $period) + $minusDms[$i];

            if ($smoothedTr <= 0) {
                continue;
            }
            $plusDi = 100 * $smoothedPlusDm / $smoothedTr;
            $minusDi = 100 * $smoothedMinusDm / $smoothedTr;
            $diSum = $plusDi + $minusDi;
            $dxs[] = $diSum > 0 ? 100 * abs($plusDi - $minusDi) / $diSum : 0.0;
        }

        if (count($dxs) < $period) {
            return NAN;
        }

        $adx = array_sum(array_slice($dxs, 0, $period)) / $period;
        for ($i = $period; $i < count($dxs); $i++) {
            $adx = ($adx * ($period - 1) + $dxs[$i]) / $period;
        }
        return $adx;
    }

    public static function macd(array $closes, int $fast = 12, int $slow = 26, int $signal = 9): array
    {
        $emaFast = self::ema($closes, $fast);
        $emaSlow = self::ema($closes, $slow);
        $n = count($closes);
        $macdLine = [];
        for ($i = 0; $i < $n; $i++) {
            $f = $emaFast[$i] ?? NAN;
            $s = $emaSlow[$i] ?? NAN;
            $macdLine[$i] = (is_nan($f) || is_nan($s)) ? NAN : $f - $s;
        }

        $signalLine = array_fill(0, $n, NAN);
        $validStart = null;
        foreach ($macdLine as $i => $v) {
            if (!is_nan($v)) {
                $validStart = $i;
                break;
            }
        }
        if ($validStart !== null && $n - $validStart >= $signal) {
            $seedIndex = $validStart + $signal - 1;
            $prev = array_sum(array_slice($macdLine, $validStart, $signal)) / $signal;
            $signalLine[$seedIndex] = $prev;
            $k = 2 / ($signal + 1);
            for ($i = $seedIndex + 1; $i < $n; $i++) {
                $prev = ($macdLine[$i] - $prev) * $k + $prev;
                $signalLine[$i] = $prev;
            }
        }

        $histogram = [];
        for ($i = 0; $i < $n; $i++) {
            $m = $macdLine[$i] ?? NAN;
            $sg = $signalLine[$i] ?? NAN;
            $histogram[$i] = (is_nan($m) || is_nan($sg)) ? NAN : $m - $sg;
        }
        return ['macd' => $macdLine, 'signal' => $signalLine, 'histogram' => $histogram];
    }
}

final class MarketStructure
{
    public static function analyse(array $candles, int $lookback = 3, int $maxAge = 3): array
    {
        $empty = [
            'trend' => 'range', 'event' => null, 'direction' => null, 'level' => null,
            'swing_high' => null, 'swing_low' => null, 'break_index' => null,
            'range_position' => 0.5, 'basing' => false, 'run_pct' => 0.0, 'drop_pct' => 0.0,
            'break_volume_ratio' => 0.0,
        ];

        $n = count($candles);
        if ($n < $lookback * 2 + 12) {
            return $empty;
        }

        $pivots = SwingPivots::detect($candles, $lookback);
        $highs = $pivots['highs'];
        $lows = $pivots['lows'];
        if (count($highs) < 2 || count($lows) < 2) {
            return $empty;
        }

        $hLast = $highs[count($highs) - 1];
        $hPrev = $highs[count($highs) - 2];
        $lLast = $lows[count($lows) - 1];
        $lPrev = $lows[count($lows) - 2];

        $swingHigh = $hLast['candle']->high;
        $swingLow = $lLast['candle']->low;

        $trend = 'range';
        if ($swingHigh > $hPrev['candle']->high && $swingLow > $lPrev['candle']->low) {
            $trend = 'bullish';
        } elseif ($swingHigh < $hPrev['candle']->high && $swingLow < $lPrev['candle']->low) {
            $trend = 'bearish';
        }

        $event = null;
        $direction = null;
        $level = null;
        $breakIndex = null;
        $volumeRatio = 0.0;

        for ($i = $n - 1; $i >= max($lookback, $n - $maxAge); $i--) {
            $c = $candles[$i];
            if ($i > $hLast['index'] && $c->close > $swingHigh) {
                $event = match ($trend) {
                    'bearish' => 'choch',
                    'bullish' => 'bos',
                    default => 'break',
                };
                $direction = 'bullish';
                $level = $swingHigh;
                $breakIndex = $i;
                break;
            }
            if ($i > $lLast['index'] && $c->close < $swingLow) {
                $event = match ($trend) {
                    'bullish' => 'choch',
                    'bearish' => 'bos',
                    default => 'break',
                };
                $direction = 'bearish';
                $level = $swingLow;
                $breakIndex = $i;
                break;
            }
        }

        if ($breakIndex !== null && $level !== null) {
            $floor = ($direction === 'bullish' ? $hLast['index'] : $lLast['index']) + 1;
            while ($breakIndex > $floor) {
                $prev = $candles[$breakIndex - 1];
                $beyond = $direction === 'bullish' ? $prev->close > $level : $prev->close < $level;
                if (!$beyond) {
                    break;
                }
                $breakIndex--;
            }
        }

        if ($breakIndex !== null) {
            $prior = array_slice($candles, max(0, $breakIndex - 20), min(20, $breakIndex));
            $avg = empty($prior) ? 0.0 : array_sum(array_map(static fn(Candle $c) => $c->volume, $prior)) / count($prior);
            $volumeRatio = $avg > 0 ? $candles[$breakIndex]->volume / $avg : 0.0;
        }

        $contextEnd = $breakIndex ?? $n;
        $contextEnd = max(12, $contextEnd);
        $close = $candles[$contextEnd - 1]->close;

        $baseWindow = array_slice($candles, max(0, $contextEnd - 40), min(40, $contextEnd));
        $baseHigh = max(array_map(static fn(Candle $c) => $c->high, $baseWindow));
        $baseLow = min(array_map(static fn(Candle $c) => $c->low, $baseWindow));
        $recentAtr = SwingPivots::atr(array_slice($candles, max(0, $contextEnd - 14), min(14, $contextEnd)), 14);
        $priorAtr = SwingPivots::atr(array_slice($candles, max(0, $contextEnd - 40), min(20, max(0, $contextEnd - 20))), 14);
        $spanPct = $close > 0 ? (($baseHigh - $baseLow) / $close) * 100 : 100.0;
        $basing = $spanPct <= Config::baseRangePercent() && $priorAtr > 0 && $recentAtr <= $priorAtr * 0.9;

        $wide = array_slice($candles, max(0, $contextEnd - 120), min(120, $contextEnd));
        $winHigh = max(array_map(static fn(Candle $c) => $c->high, $wide));
        $winLow = min(array_map(static fn(Candle $c) => $c->low, $wide));
        $span = $winHigh - $winLow;
        $position = $span > 0 ? ($close - $winLow) / $span : 0.5;

        $runPct = $winLow > 0 ? (($close - $winLow) / $winLow) * 100 : 0.0;
        $dropPct = $winHigh > 0 ? (($winHigh - $close) / $winHigh) * 100 : 0.0;

        return [
            'trend' => $trend,
            'event' => $event,
            'direction' => $direction,
            'level' => $level,
            'swing_high' => $swingHigh,
            'swing_low' => $swingLow,
            'break_index' => $breakIndex,
            'range_position' => $position,
            'basing' => $basing,
            'run_pct' => $runPct,
            'drop_pct' => $dropPct,
            'break_volume_ratio' => $volumeRatio,
        ];
    }
}

final class ReversalCandleEngine
{
    private const MAX_BODY_RATIO = 0.35;
    private const MIN_WICK_TO_BODY = 2.0;
    private const MAX_OPPOSITE_WICK_RATIO = 0.25;
    private const CONTEXT_LOOKBACK = 8;
    private const MIN_CONTEXT_ATR = 1.0;

    public static function check(array $candles, bool $isLong): array
    {
        $n = count($candles);
        if ($n < self::CONTEXT_LOOKBACK + 1) {
            return self::result(false, [], 'کندل کافی روی تایم تاییدیه نیست');
        }

        $last = $candles[$n - 1];
        $range = $last->range();
        if ($range <= 0) {
            return self::result(false, [], 'کندل تاییدیه بدون حرکت بود (دوجی صفر)');
        }

        $body = $last->bodySize();
        $upperWick = $last->high - max($last->open, $last->close);
        $lowerWick = min($last->open, $last->close) - $last->low;
        $dominantWick = $isLong ? $lowerWick : $upperWick;
        $oppositeWick = $isLong ? $upperWick : $lowerWick;

        $checklist = [
            'small_body' => ($body / $range) <= self::MAX_BODY_RATIO,
            'long_rejection_wick' => $body > 0 ? ($dominantWick / $body) >= self::MIN_WICK_TO_BODY : $dominantWick > 0,
            'short_opposite_wick' => ($oppositeWick / $range) <= self::MAX_OPPOSITE_WICK_RATIO,
            'prior_move' => self::hadPriorMove($candles, $isLong),
        ];

        $shape = $isLong ? 'همر صعودی' : 'شوتینگ‌استار نزولی';
        if (!in_array(false, $checklist, true)) {
            return self::result(true, $checklist, "کندل تاییدیه ({$shape}) روی تایم پایین تایید شد");
        }

        $missing = implode('، ', array_map(
            self::labelFa(...),
            array_keys(array_filter($checklist, static fn(bool $ok) => !$ok))
        ));
        return self::result(false, $checklist, "کندل {$shape} تایید نشد — رد شد در: {$missing}");
    }

    private static function labelFa(string $key): string
    {
        return match ($key) {
            'small_body' => 'بدنه کوچک',
            'long_rejection_wick' => 'دم بلند رد قیمت',
            'short_opposite_wick' => 'دم مخالف کوتاه',
            'prior_move' => 'حرکت قبلی کافی',
            default => $key,
        };
    }

    private static function hadPriorMove(array $candles, bool $isLong): bool
    {
        $atr = SwingPivots::atr($candles);
        if ($atr <= 0) {
            return false;
        }
        $n = count($candles);
        $window = array_slice($candles, $n - 1 - self::CONTEXT_LOOKBACK, self::CONTEXT_LOOKBACK);
        $from = $window[0]->open;
        $to = $window[count($window) - 1]->close;
        $moved = $isLong ? ($from - $to) : ($to - $from);
        return $moved >= $atr * self::MIN_CONTEXT_ATR;
    }

    private static function result(bool $confirmed, array $checklist, string $reason): array
    {
        return ['confirmed' => $confirmed, 'checklist' => $checklist, 'reason' => $reason];
    }
}

final class BreakerBlockEngine
{
    private const MAX_AGE_CANDLES = 6;

    public static function detect(int $totalCandles, array $liquidity, array $structure): ?array
    {
        $sweep = $liquidity['recent_sweep'] ?? null;
        $breakIndex = $structure['break_index'] ?? null;
        if ($sweep === null || $structure['event'] === null || $structure['direction'] === null || $breakIndex === null) {
            return null;
        }

        $wantsBullish = !$sweep['is_high'];
        $isBullish = $structure['direction'] === 'bullish';
        if ($wantsBullish !== $isBullish) {
            return null;
        }

        if ($breakIndex < $sweep['index']) {
            return null;
        }

        $age = max(0, $totalCandles - 1 - $breakIndex);
        return [
            'direction' => $isBullish ? 'bullish' : 'bearish',
            'fresh' => $age <= self::MAX_AGE_CANDLES,
            'age' => $age,
        ];
    }

    public static function detectFlip(float $price, array $orderBlocks): ?array
    {
        $best = null;
        foreach ($orderBlocks as $ob) {
            if (!$ob->mitigated) {
                continue;
            }
            if ($ob->type === OrderBlockType::BULLISH && $price < $ob->high) {
                if ($best === null || $ob->high < $best['level']) {
                    $best = ['direction' => 'bearish', 'level' => $ob->high];
                }
            } elseif ($ob->type === OrderBlockType::BEARISH && $price > $ob->low) {
                if ($best === null || $ob->low > $best['level']) {
                    $best = ['direction' => 'bullish', 'level' => $ob->low];
                }
            }
        }
        return $best;
    }
}

final class RsiReversalEngine
{
    private const PIVOT_LEN = 21;
    private const RSI_LEN = 14;
    private const BANDWIDTH = 2.71828;
    private const MIN_PIVOTS = 5;
    private const CONFIDENCE_THRESHOLD = 0.65;

    public static function detect(array $candles): array
    {
        $none = ['signal' => null, 'confidence' => 0.0];
        $n = count($candles);
        if ($n < self::PIVOT_LEN * 2 + self::RSI_LEN + 10) {
            return $none;
        }

        $closes = array_map(static fn(Candle $c) => $c->close, $candles);
        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);

        $rsi = Ta::rsi($closes, self::RSI_LEN);
        $currentRsi = end($rsi);
        if ($currentRsi === false || !is_finite($currentRsi)) {
            return $none;
        }

        $highRsis = [];
        foreach (Ta::pivots($highs, self::PIVOT_LEN, self::PIVOT_LEN, true) as $i) {
            if (is_finite($rsi[$i] ?? NAN)) {
                $highRsis[] = $rsi[$i];
            }
        }
        $lowRsis = [];
        foreach (Ta::pivots($lows, self::PIVOT_LEN, self::PIVOT_LEN, false) as $i) {
            if (is_finite($rsi[$i] ?? NAN)) {
                $lowRsis[] = $rsi[$i];
            }
        }

        if (count($highRsis) < self::MIN_PIVOTS || count($lowRsis) < self::MIN_PIVOTS) {
            return $none;
        }

        $highDensity = self::density($currentRsi, $highRsis);
        $lowDensity = self::density($currentRsi, $lowRsis);
        $total = $highDensity + $lowDensity;
        if ($total <= 0) {
            return $none;
        }

        $bullishShare = $lowDensity / $total;
        $bearishShare = $highDensity / $total;

        if ($bullishShare >= self::CONFIDENCE_THRESHOLD) {
            return ['signal' => 'bullish', 'confidence' => round($bullishShare, 3)];
        }
        if ($bearishShare >= self::CONFIDENCE_THRESHOLD) {
            return ['signal' => 'bearish', 'confidence' => round($bearishShare, 3)];
        }
        return $none;
    }

    private static function density(float $x, array $points): float
    {
        $sum = 0.0;
        foreach ($points as $p) {
            $d = ($x - $p) / self::BANDWIDTH;
            $sum += exp(-0.5 * $d * $d);
        }
        return $sum / count($points);
    }
}

final class SuperTrendAiEngine
{
    private const ATR_LEN = 10;
    private const MIN_FACTOR = 1.0;
    private const MAX_FACTOR = 5.0;
    private const STEP = 0.5;
    private const PERF_ALPHA = 10.0;

    public static function detect(array $candles): array
    {
        $none = ['direction' => null, 'confidence' => 0.0];
        $n = count($candles);
        if ($n < self::ATR_LEN + 50) {
            return $none;
        }

        $atr = self::atrSeries($candles, self::ATR_LEN);
        $hl2 = array_map(static fn(Candle $c) => ($c->high + $c->low) / 2, $candles);
        $closes = array_map(static fn(Candle $c) => $c->close, $candles);

        $factors = [];
        for ($f = self::MIN_FACTOR; $f <= self::MAX_FACTOR + 1e-9; $f += self::STEP) {
            $factors[] = $f;
        }

        $scored = [];
        foreach ($factors as $factor) {
            $pass = self::runPass($hl2, $closes, $atr, $factor);
            $scored[] = ['factor' => $factor, 'perf' => $pass['perf']];
        }

        usort($scored, static fn($a, $b) => $b['perf'] <=> $a['perf']);
        $top = array_slice($scored, 0, max(1, (int) ceil(count($scored) / 3)));
        $bestFactor = array_sum(array_column($top, 'factor')) / count($top);

        $final = self::runPass($hl2, $closes, $atr, $bestFactor);
        $confidence = $final['den'] > 0 ? max(0.0, min(1.0, $final['perf'] / $final['den'])) : 0.0;

        return [
            'direction' => $final['trend'] === 1 ? 'bullish' : 'bearish',
            'confidence' => round($confidence, 3),
        ];
    }

    private static function runPass(array $hl2, array $closes, array $atr, float $factor): array
    {
        $n = count($closes);
        $upper = $hl2[0];
        $lower = $hl2[0];
        $trend = 0;
        $output = $hl2[0];
        $perf = 0.0;
        $den = 0.0;
        $k = 2 / (self::PERF_ALPHA + 1);

        for ($i = 1; $i < $n; $i++) {
            $up = $hl2[$i] + $atr[$i] * $factor;
            $dn = $hl2[$i] - $atr[$i] * $factor;
            $upper = $closes[$i - 1] < $upper ? min($up, $upper) : $up;
            $lower = $closes[$i - 1] > $lower ? max($dn, $lower) : $dn;

            if ($closes[$i] > $upper) {
                $trend = 1;
            } elseif ($closes[$i] < $lower) {
                $trend = 0;
            }

            $diff = self::sign($closes[$i - 1] - $output);
            $perf += $k * (($closes[$i] - $closes[$i - 1]) * $diff - $perf);

            $absMove = abs($closes[$i] - $closes[$i - 1]);
            $den = $i === 1 ? $absMove : $den + $k * ($absMove - $den);

            $output = $trend === 1 ? $lower : $upper;
        }

        return ['perf' => $perf, 'den' => $den, 'trend' => $trend, 'output' => $output];
    }

    private static function sign(float $x): float
    {
        return $x > 0 ? 1.0 : ($x < 0 ? -1.0 : 0.0);
    }

    private static function atrSeries(array $candles, int $period): array
    {
        $n = count($candles);
        $out = array_fill(0, $n, 0.0);
        $trs = [];
        for ($i = 1; $i < $n; $i++) {
            $prevClose = $candles[$i - 1]->close;
            $trs[] = max(
                $candles[$i]->high - $candles[$i]->low,
                abs($candles[$i]->high - $prevClose),
                abs($candles[$i]->low - $prevClose)
            );
            $window = array_slice($trs, -$period);
            $out[$i] = array_sum($window) / count($window);
        }
        return $out;
    }
}

final class VolumeImbalanceEngine
{
    public static function detect(array $candles): array
    {
        $none = ['present' => false, 'direction' => null];
        $n = count($candles);
        if ($n < 2) {
            return $none;
        }

        $prev = $candles[$n - 2];
        $last = $candles[$n - 1];
        $lastBodyTop = max($last->open, $last->close);
        $lastBodyBottom = min($last->open, $last->close);

        $bull = $last->open > $prev->close
            && $prev->high > $last->low
            && $last->close > $prev->close
            && $last->open > $prev->open
            && $prev->high < $lastBodyBottom;

        $bear = $last->open < $prev->close
            && $prev->low < $last->high
            && $last->close < $prev->close
            && $last->open < $prev->open
            && $prev->low > $lastBodyTop;

        if ($bull) {
            return ['present' => true, 'direction' => 'bullish'];
        }
        if ($bear) {
            return ['present' => true, 'direction' => 'bearish'];
        }
        return $none;
    }
}

final class DisplacementEngine
{
    private const BODY_AVG_LEN = 5;
    private const MAX_WICK_RATIO = 0.36;

    public static function detect(array $candles): array
    {
        $none = ['present' => false, 'direction' => null];
        $n = count($candles);
        if ($n < self::BODY_AVG_LEN + 2) {
            return $none;
        }

        $bodies = [];
        for ($i = $n - self::BODY_AVG_LEN - 1; $i < $n - 1; $i++) {
            $bodies[] = $candles[$i]->bodySize();
        }
        $meanBody = array_sum($bodies) / count($bodies);

        $last = $candles[$n - 1];
        $body = $last->bodySize();
        if ($body <= $meanBody || $body <= 0) {
            return $none;
        }

        $upperWick = $last->high - max($last->open, $last->close);
        $lowerWick = min($last->open, $last->close) - $last->low;
        if ($upperWick >= $body * self::MAX_WICK_RATIO || $lowerWick >= $body * self::MAX_WICK_RATIO) {
            return $none;
        }

        return ['present' => true, 'direction' => $last->isBullish() ? 'bullish' : 'bearish'];
    }
}

final class FvgMitigation
{
    public static function percent(Fvg $fvg, float $price): float
    {
        $top = max($fvg->high, $fvg->low);
        $bottom = min($fvg->high, $fvg->low);
        $height = max(1e-9, $top - $bottom);

        if ($fvg->type === FvgType::BULLISH) {
            if ($price >= $top) {
                return 0.0;
            }
            if ($price <= $bottom) {
                return 100.0;
            }
            return (($top - $price) / $height) * 100;
        }

        if ($price <= $bottom) {
            return 0.0;
        }
        if ($price >= $top) {
            return 100.0;
        }
        return (($price - $bottom) / $height) * 100;
    }
}

final class KillzoneEngine
{
    private const ZONES = [
        [0, 4],
        [7, 10],
        [12, 15],
        [15, 17],
    ];

    public static function isActive(int $unixTime): bool
    {
        $hour = (int) gmdate('G', $unixTime);
        foreach (self::ZONES as [$start, $end]) {
            if ($hour >= $start && $hour < $end) {
                return true;
            }
        }
        return false;
    }
}

final class SqueezeEngine
{
    private const BAND_LEN = 20;
    private const LOOKBACK = 100;
    private const SQUEEZE_PERCENTILE = 0.20;
    private const EXPANSION_ATR_MULT = 1.5;

    public static function detect(array $candles): array
    {
        $none = ['squeezing' => false, 'expanding' => false, 'direction' => null];
        $n = count($candles);
        if ($n < self::BAND_LEN + self::LOOKBACK + 1) {
            return $none;
        }

        $closes = array_map(static fn(Candle $c) => $c->close, $candles);

        $widths = [];
        for ($i = $n - 1 - self::LOOKBACK; $i < $n - 1; $i++) {
            $slice = array_slice($closes, $i - self::BAND_LEN + 1, self::BAND_LEN);
            $mean = array_sum($slice) / count($slice);
            if ($mean <= 0) {
                continue;
            }
            $widths[] = (Ta::stdev($slice, self::BAND_LEN) * 4) / $mean;
        }
        if (count($widths) < 10) {
            return $none;
        }

        $priorWidth = end($widths);
        $sorted = $widths;
        sort($sorted);
        $rank = array_search($priorWidth, $sorted, true);
        $percentile = $rank === false ? 1.0 : $rank / (count($sorted) - 1);
        $squeezing = $percentile <= self::SQUEEZE_PERCENTILE;

        $atr = SwingPivots::atr(array_slice($candles, 0, $n - 1));
        $last = $candles[$n - 1];
        $expanding = $atr > 0 && $last->range() >= $atr * self::EXPANSION_ATR_MULT;

        return [
            'squeezing' => $squeezing,
            'expanding' => $expanding,
            'direction' => $expanding ? ($last->isBullish() ? 'bullish' : 'bearish') : null,
        ];
    }
}

final class LiquidityLevel
{
    public function __construct(
        public readonly float $price,
        public readonly bool $isHigh,
        public readonly string $origin,
        public readonly int $bornIndex,
        public bool $swept = false,
        public int $sweptIndex = -1,
    ) {
    }
}

final class LiquidityEngine
{
    public function detect(array $candles, int $keepPerSide = 6, int $pivotLen = 3, int $sweepAge = 4): array
    {
        $n = count($candles);
        if ($n < $pivotLen * 2 + 6) {
            return ['levels' => [], 'recent_sweep' => null];
        }

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);

        $levels = [];
        foreach (Ta::pivots($highs, $pivotLen, $pivotLen, true) as $i) {
            $levels[] = new LiquidityLevel($candles[$i]->high, true, 'swing', $i);
        }
        foreach (Ta::pivots($lows, $pivotLen, $pivotLen, false) as $i) {
            $levels[] = new LiquidityLevel($candles[$i]->low, false, 'swing', $i);
        }
        foreach ($this->dailyLevels($candles) as $level) {
            $levels[] = $level;
        }

        $recentSweep = null;
        foreach ($levels as $level) {
            for ($i = $level->bornIndex + $pivotLen + 1; $i < $n; $i++) {
                $through = $level->isHigh ? $candles[$i]->high > $level->price : $candles[$i]->low < $level->price;
                if (!$through) {
                    continue;
                }
                $level->swept = true;
                $level->sweptIndex = $i;
                break;
            }
            if ($level->swept && $level->sweptIndex >= $n - $sweepAge) {
                $c = $candles[$level->sweptIndex];
                $rejected = $level->isHigh ? $c->close < $level->price : $c->close > $level->price;
                if ($rejected && ($recentSweep === null || $level->sweptIndex > $recentSweep['index'])) {
                    $recentSweep = [
                        'price' => $level->price,
                        'is_high' => $level->isHigh,
                        'index' => $level->sweptIndex,
                        'origin' => $level->origin,
                    ];
                }
            }
        }

        $live = array_values(array_filter($levels, static fn(LiquidityLevel $l) => !$l->swept));
        usort($live, static fn(LiquidityLevel $a, LiquidityLevel $b) => $b->bornIndex <=> $a->bornIndex);

        $kept = [];
        $counts = ['high' => 0, 'low' => 0];
        foreach ($live as $level) {
            $key = $level->isHigh ? 'high' : 'low';
            if ($counts[$key] >= $keepPerSide) {
                continue;
            }
            $counts[$key]++;
            $kept[] = $level;
        }

        return ['levels' => $kept, 'recent_sweep' => $recentSweep];
    }

    private function dailyLevels(array $candles): array
    {
        $byDay = [];
        foreach ($candles as $i => $c) {
            $day = (int) floor($c->openTime / 86400000);
            if (!isset($byDay[$day])) {
                $byDay[$day] = ['high' => $c->high, 'low' => $c->low, 'hi' => $i, 'li' => $i];
                continue;
            }
            if ($c->high > $byDay[$day]['high']) {
                $byDay[$day]['high'] = $c->high;
                $byDay[$day]['hi'] = $i;
            }
            if ($c->low < $byDay[$day]['low']) {
                $byDay[$day]['low'] = $c->low;
                $byDay[$day]['li'] = $i;
            }
        }
        $days = array_keys($byDay);
        sort($days);
        if (count($days) < 2) {
            return [];
        }
        $prev = $byDay[$days[count($days) - 2]];
        return [
            new LiquidityLevel($prev['high'], true, 'PDH', $prev['hi']),
            new LiquidityLevel($prev['low'], false, 'PDL', $prev['li']),
        ];
    }
}

final class SupplyDemandZone
{
    public function __construct(
        public readonly float $top,
        public readonly float $bottom,
        public readonly bool $isDemand,
        public readonly int $startIndex,
        public bool $broken = false,
    ) {
    }

    public function contains(float $price, float $pad = 0.0): bool
    {
        return $price <= $this->top + $pad && $price >= $this->bottom - $pad;
    }

    public function mid(): float
    {
        return ($this->top + $this->bottom) / 2;
    }
}

final class SupplyDemandEngine
{
    public function __construct(
        private int $momentumSpan = 4,
        private int $momentumCount = 4,
        private float $bodyMultiplier = 0.5,
        private float $maxZoneAtr = 1.5,
        private int $minDistance = 5,
    ) {
    }

    public function detect(array $candles): array
    {
        $n = count($candles);
        if ($n < $this->momentumSpan + 25) {
            return [];
        }

        $bodies = array_map(static fn(Candle $c) => abs($c->close - $c->open), $candles);
        $avgBody = Ta::sma($bodies, 20);
        $atr = SwingPivots::atr($candles);
        if ($atr <= 0) {
            return [];
        }

        $zones = [];
        $lastDemand = -PHP_INT_MAX;
        $lastSupply = -PHP_INT_MAX;

        for ($i = $this->momentumSpan + 1; $i < $n; $i++) {
            $avg = $avgBody[$i] ?? NAN;
            if (is_nan($avg) || $avg <= 0) {
                continue;
            }
            $bull = 0;
            $bear = 0;
            for ($k = 0; $k < $this->momentumSpan; $k++) {
                $c = $candles[$i - $k];
                if ($bodies[$i - $k] < $avg * $this->bodyMultiplier) {
                    continue;
                }
                if ($c->close > $c->open) {
                    $bull++;
                } elseif ($c->close < $c->open) {
                    $bear++;
                }
            }

            $originIndex = $i - ($this->momentumSpan + 1);
            if ($originIndex < 0) {
                continue;
            }
            $origin = $candles[$originIndex];

            if ($bull >= $this->momentumCount && ($i - $lastDemand) > $this->minDistance) {
                $lastDemand = $i;
                $zones[] = $this->clamp($origin->high, $origin->low, true, $originIndex, $atr);
            }
            if ($bear >= $this->momentumCount && ($i - $lastSupply) > $this->minDistance) {
                $lastSupply = $i;
                $zones[] = $this->clamp($origin->high, $origin->low, false, $originIndex, $atr);
            }
        }

        foreach ($zones as $zone) {
            for ($i = $zone->startIndex + 1; $i < $n; $i++) {
                $c = $candles[$i];
                $dead = $zone->isDemand
                    ? min($c->open, $c->close) < $zone->bottom
                    : max($c->open, $c->close) > $zone->top;
                if ($dead) {
                    $zone->broken = true;
                    break;
                }
            }
        }

        $live = array_values(array_filter($zones, static fn(SupplyDemandZone $z) => !$z->broken));
        usort($live, static fn(SupplyDemandZone $a, SupplyDemandZone $b) => $b->startIndex <=> $a->startIndex);
        return array_slice($live, 0, 12);
    }

    private function clamp(float $top, float $bottom, bool $isDemand, int $index, float $atr): SupplyDemandZone
    {
        $size = $top - $bottom;
        $limit = $atr * $this->maxZoneAtr;
        if ($size > $limit) {
            $trim = ($size - $limit) / 2;
            $top -= $trim;
            $bottom += $trim;
        }
        return new SupplyDemandZone($top, $bottom, $isDemand, $index);
    }
}

final class RangeEngine
{
    public function __construct(
        private int $baseLength = 20,
        private float $bandPercentile = 90.0,
        private float $compressionPercentile = 40.0,
        private float $minRotation = 0.18,
        private int $minTouches = 2,
        private float $touchTolerance = 0.10,
        private float $maxDrift = 0.45,
        private float $minContainment = 0.70,
        private float $breakoutBuffer = 0.15,
    ) {
    }

    public function detect(array $candles): array
    {
        $none = ['qualified' => false, 'top' => null, 'bottom' => null, 'length' => 0, 'break' => null, 'position' => 0.5];
        $n = count($candles);
        $atr = SwingPivots::atr($candles);
        if ($atr <= 0 || $n < $this->baseLength * 3 + 10) {
            return $none;
        }

        $hold = Config::rangeBreakLookback();
        $body = array_slice($candles, 0, $n - $hold);
        if (count($body) < $this->baseLength + 5) {
            return $none;
        }

        foreach ([3, 2, 1] as $scale) {
            $len = $this->baseLength * $scale;
            if (count($body) < $len + 5) {
                continue;
            }
            $window = $this->scan($body, $len, $atr);
            if (!$window['qualified']) {
                continue;
            }

            $top = $window['top'];
            $bottom = $window['bottom'];
            $close = $candles[$n - 1]->close;
            $buffer = $this->breakoutBuffer * $atr;
            $break = null;
            if ($close > $top + $buffer) {
                $break = 'up';
            } elseif ($close < $bottom - $buffer) {
                $break = 'down';
            }

            $span = $top - $bottom;
            return [
                'qualified' => true,
                'top' => $top,
                'bottom' => $bottom,
                'length' => $len,
                'break' => $break,
                'position' => $span > 0 ? ($close - $bottom) / $span : 0.5,
            ];
        }

        return $none;
    }

    private function scan(array $candles, int $len, float $atr): array
    {
        $n = count($candles);
        $slice = array_slice($candles, -$len);
        $highs = array_map(static fn(Candle $c) => $c->high, $slice);
        $lows = array_map(static fn(Candle $c) => $c->low, $slice);

        $top = Ta::percentile($highs, $len, $this->bandPercentile);
        $bottom = Ta::percentile($lows, $len, 100.0 - $this->bandPercentile);
        $height = $top - $bottom;
        if ($height <= 0) {
            return ['qualified' => false, 'top' => 0.0, 'bottom' => 0.0];
        }

        $compression = $height / ($atr * sqrt($len));
        $history = [];
        $step = max(1, (int) floor($len / 4));
        for ($end = $n - $len; $end >= $len; $end -= $step) {
            $past = array_slice($candles, $end - $len, $len);
            $h = max(array_map(static fn(Candle $c) => $c->high, $past));
            $l = min(array_map(static fn(Candle $c) => $c->low, $past));
            if ($h > $l) {
                $history[] = ($h - $l) / ($atr * sqrt($len));
            }
            if (count($history) >= 40) {
                break;
            }
        }

        $rank = empty($history) ? 100.0 : Ta::percentRank($history, $compression);
        if ($rank > $this->compressionPercentile && $compression > Config::rangeAbsoluteCompression()) {
            return ['qualified' => false, 'top' => $top, 'bottom' => $bottom];
        }

        $mid = ($top + $bottom) / 2;
        $tolerance = $this->touchTolerance * $atr;
        $crossings = 0;
        $hitsTop = 0;
        $hitsBottom = 0;
        $contained = 0;
        for ($i = 0; $i < $len; $i++) {
            if ($i > 0 && (($slice[$i]->close > $mid) !== ($slice[$i - 1]->close > $mid))) {
                $crossings++;
            }
            if ($highs[$i] >= $top - $tolerance) {
                $hitsTop++;
            }
            if ($lows[$i] <= $bottom + $tolerance) {
                $hitsBottom++;
            }
            if ($slice[$i]->close <= $top && $slice[$i]->close >= $bottom) {
                $contained++;
            }
        }

        $drift = abs(Ta::slope(Ta::closes($slice), $len)) * $len / $height;
        $qualified = $crossings >= max(3, (int) round($this->minRotation * $len))
            && $hitsTop >= $this->minTouches
            && $hitsBottom >= $this->minTouches
            && $drift <= $this->maxDrift
            && ($contained / $len) >= $this->minContainment;

        return ['qualified' => $qualified, 'top' => $top, 'bottom' => $bottom];
    }
}

final class TrendWave
{
    public static function analyse(array $candles, int $waveLength = 21, int $sensitivity = 7, int $sdLength = 20): array
    {
        $n = count($candles);
        $out = [
            'direction' => 'neutral', 'wave' => null, 'distance_atr' => 0.0,
            'event' => null, 'event_dir' => null, 'event_index' => null,
            'sd_target' => null, 'inside_bar' => false, 'structure_trend' => null,
        ];
        if ($n < max($waveLength, $sensitivity * 2 + 4, $sdLength) + 2) {
            return $out;
        }

        $closes = Ta::closes($candles);
        $wave = Ta::alma($closes, $waveLength);
        $last = $n - 1;
        $waveNow = $wave[$last] ?? NAN;
        $atr = SwingPivots::atr($candles);

        if (!is_nan($waveNow)) {
            $out['wave'] = $waveNow;
            $out['direction'] = $closes[$last] >= $waveNow ? 'bullish' : 'bearish';
            $out['distance_atr'] = $atr > 0 ? abs($closes[$last] - $waveNow) / $atr : 0.0;
        }

        $out['inside_bar'] = $candles[$last]->high <= $candles[$last - 1]->high
            && $candles[$last]->low >= $candles[$last - 1]->low;

        $mean = array_sum(array_slice($closes, -$sdLength)) / $sdLength;
        $out['sd_target'] = $mean - Ta::stdev($closes, $sdLength) * 2.5;

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);
        $ph = Ta::pivots($highs, $sensitivity, $sensitivity, true);
        $pl = Ta::pivots($lows, $sensitivity, $sensitivity, false);

        $lastPh = null;
        $lastPl = null;
        $trend = 0;
        $event = null;
        $eventDir = null;
        $eventIndex = null;
        $phCursor = 0;
        $plCursor = 0;

        for ($i = 1; $i < $n; $i++) {
            while ($phCursor < count($ph) && $ph[$phCursor] + $sensitivity <= $i) {
                $lastPh = $highs[$ph[$phCursor]];
                $phCursor++;
            }
            while ($plCursor < count($pl) && $pl[$plCursor] + $sensitivity <= $i) {
                $lastPl = $lows[$pl[$plCursor]];
                $plCursor++;
            }

            if ($lastPh !== null && $closes[$i] > $lastPh && $closes[$i - 1] <= $lastPh) {
                $event = $trend === -1 ? 'choch' : 'bos';
                $eventDir = 'bullish';
                $eventIndex = $i;
                $trend = 1;
                $lastPh = null;
            } elseif ($lastPl !== null && $closes[$i] < $lastPl && $closes[$i - 1] >= $lastPl) {
                $event = $trend === 1 ? 'choch' : 'bos';
                $eventDir = 'bearish';
                $eventIndex = $i;
                $trend = -1;
                $lastPl = null;
            }
        }

        $out['event'] = $event;
        $out['event_dir'] = $eventDir;
        $out['event_index'] = $eventIndex;
        $out['structure_trend'] = $trend === 1 ? 'bullish' : ($trend === -1 ? 'bearish' : null);
        return $out;
    }
}

final class DeviationChannel
{
    public static function analyse(array $candles, int $length = 20, int $rsiLength = 20): array
    {
        $out = ['mid' => null, 'upper1' => null, 'lower1' => null, 'rsi' => null, 'signal' => null, 'mid_rising' => false, 'mid_falling' => false];
        $n = count($candles);
        if ($n < max($length, $rsiLength, 100) + 8) {
            return $out;
        }

        $closes = Ta::closes($candles);
        $mid = Ta::ema($closes, $length);
        $last = $n - 1;
        if (is_nan($mid[$last] ?? NAN)) {
            return $out;
        }

        $stDev = SwingPivots::atr($candles, 100) * 1.5;
        if ($stDev <= 0) {
            return $out;
        }

        $rsi = Ta::sma(Ta::rsi($closes, $rsiLength), 5);
        $rsiNow = $rsi[$last] ?? NAN;
        if (is_nan($rsiNow)) {
            return $out;
        }

        $upper1 = $mid[$last] + $stDev;
        $lower1 = $mid[$last] - $stDev;
        $upperPrev = $mid[$last - 1] + $stDev;
        $lowerPrev = $mid[$last - 1] - $stDev;

        $out['mid'] = $mid[$last];
        $out['upper1'] = $upper1;
        $out['lower1'] = $lower1;
        $out['rsi'] = $rsiNow;
        $out['mid_rising'] = $mid[$last] > ($mid[$last - 3] ?? $mid[$last]);
        $out['mid_falling'] = $mid[$last] < ($mid[$last - 3] ?? $mid[$last]);

        if ($rsiNow < 50 && $closes[$last] > $lower1 && $closes[$last - 1] <= $lowerPrev) {
            $out['signal'] = 'long';
        } elseif ($rsiNow >= 50 && $closes[$last] < $upper1 && $closes[$last - 1] >= $upperPrev) {
            $out['signal'] = 'short';
        }

        return $out;
    }
}

final class SmcContext
{
    public static function analyse(array $candles, int $swingLen = 8, int $internalLen = 3, int $rangeLookback = 40): array
    {
        $out = [
            'range_high' => null, 'range_low' => null, 'equilibrium' => null,
            'zone' => 'unknown', 'position' => 0.5,
            'ote_high' => null, 'ote_low' => null, 'in_ote' => false, 'leg' => null,
            'eqh' => null, 'eql' => null,
            'internal_event' => null, 'internal_dir' => null,
        ];
        $n = count($candles);
        if ($n < max($swingLen * 2 + 6, $rangeLookback)) {
            return $out;
        }

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);
        $closes = Ta::closes($candles);
        $close = $closes[$n - 1];
        $atr = SwingPivots::atr($candles);

        $window = array_slice($candles, -$rangeLookback);
        $rangeHigh = max(array_map(static fn(Candle $c) => $c->high, $window));
        $rangeLow = min(array_map(static fn(Candle $c) => $c->low, $window));
        $span = $rangeHigh - $rangeLow;
        $out['range_high'] = $rangeHigh;
        $out['range_low'] = $rangeLow;
        $out['equilibrium'] = ($rangeHigh + $rangeLow) / 2;
        $out['position'] = $span > 0 ? ($close - $rangeLow) / $span : 0.5;
        $out['zone'] = $out['position'] > 0.5 ? 'premium' : 'discount';

        $phs = Ta::pivots($highs, $swingLen, $swingLen, true);
        $pls = Ta::pivots($lows, $swingLen, $swingLen, false);
        $lastPh = empty($phs) ? null : $phs[count($phs) - 1];
        $lastPl = empty($pls) ? null : $pls[count($pls) - 1];

        if ($lastPh !== null && $lastPl !== null) {
            if ($lastPl > $lastPh) {
                $legHigh = $highs[$lastPh];
                $legLow = $lows[$lastPl];
                $out['leg'] = 'down';
                $out['ote_low'] = $legLow + ($legHigh - $legLow) * 0.62;
                $out['ote_high'] = $legLow + ($legHigh - $legLow) * 0.79;
            } else {
                $legHigh = $highs[$lastPh];
                $legLow = $lows[$lastPl];
                $out['leg'] = 'up';
                $out['ote_low'] = $legHigh - ($legHigh - $legLow) * 0.79;
                $out['ote_high'] = $legHigh - ($legHigh - $legLow) * 0.62;
            }
            if ($out['ote_low'] !== null && $out['ote_high'] !== null) {
                $out['in_ote'] = $close >= min($out['ote_low'], $out['ote_high'])
                    && $close <= max($out['ote_low'], $out['ote_high']);
            }
        }

        $tolerance = $atr * 0.10;
        if (count($phs) >= 2 && abs($highs[$phs[count($phs) - 1]] - $highs[$phs[count($phs) - 2]]) <= $tolerance) {
            $out['eqh'] = max($highs[$phs[count($phs) - 1]], $highs[$phs[count($phs) - 2]]);
        }
        if (count($pls) >= 2 && abs($lows[$pls[count($pls) - 1]] - $lows[$pls[count($pls) - 2]]) <= $tolerance) {
            $out['eql'] = min($lows[$pls[count($pls) - 1]], $lows[$pls[count($pls) - 2]]);
        }

        $iph = Ta::pivots($highs, $internalLen, $internalLen, true);
        $ipl = Ta::pivots($lows, $internalLen, $internalLen, false);
        $recent = static fn(array $piv, array $src, bool $up): ?array => (function () use ($piv, $src, $up, $closes, $n, $internalLen) {
            for ($k = count($piv) - 1; $k >= 0; $k--) {
                $idx = $piv[$k];
                for ($i = max($idx + $internalLen + 1, $n - 3); $i < $n; $i++) {
                    $crossed = $up
                        ? ($closes[$i] > $src[$idx] && $closes[$i - 1] <= $src[$idx])
                        : ($closes[$i] < $src[$idx] && $closes[$i - 1] >= $src[$idx]);
                    if ($crossed) {
                        return ['index' => $i, 'level' => $src[$idx]];
                    }
                }
            }
            return null;
        })();

        $up = $recent($iph, $highs, true);
        $down = $recent($ipl, $lows, false);
        if ($up !== null && ($down === null || $up['index'] >= $down['index'])) {
            $out['internal_event'] = 'ibos';
            $out['internal_dir'] = 'bullish';
        } elseif ($down !== null) {
            $out['internal_event'] = 'ibos';
            $out['internal_dir'] = 'bearish';
        }

        return $out;
    }
}

final class SetupScanner
{
    public const VWAP = 'vwap_reclaim';
    public const EMA = 'ema_pullback';
    public const BREAK_RETEST = 'break_retest';
    public const SWEEP = 'liquidity_sweep';
    public const DIVERGENCE = 'rsi_divergence';
    public const RANGE_BREAK = 'opening_range';
    public const BIG_MOVE = 'sweep_big_move';
    public const CHANNEL = 'deviation_channel';

    public function scan(array $candles): array
    {
        $n = count($candles);
        $out = ['long' => [], 'short' => [], 'trend' => 'neutral', 'atr' => 0.0, 'rsi' => NAN, 'vwap' => null, 'volume_ok' => false];
        if ($n < 60) {
            return $out;
        }

        $closes = Ta::closes($candles);
        $volumes = Ta::volumes($candles);
        $last = $n - 1;
        $c = $candles[$last];
        $prev = $candles[$last - 1];

        $atr = SwingPivots::atr($candles);
        if ($atr <= 0) {
            return $out;
        }
        $out['atr'] = $atr;

        $ema9 = Ta::ema($closes, 9);
        $ema21 = Ta::ema($closes, 21);
        $ema50 = Ta::ema($closes, 50);
        $rsi = Ta::rsi($closes, 14);
        $vwap = Ta::vwap($candles);
        $volMa = Ta::sma($volumes, 20);

        $out['rsi'] = $rsi[$last] ?? NAN;
        $out['vwap'] = is_nan($vwap[$last] ?? NAN) ? null : $vwap[$last];

        $volumeOk = !is_nan($volMa[$last] ?? NAN) && $volMa[$last] > 0
            && $c->volume >= $volMa[$last] * Config::setupVolumeMultiple();
        $out['volume_ok'] = $volumeOk;

        $bullBar = $c->close > $c->open;
        $bearBar = $c->close < $c->open;

        $trendUp = !is_nan($ema21[$last]) && !is_nan($ema50[$last])
            && $ema21[$last] > $ema50[$last] && $ema50[$last] > ($ema50[$last - 3] ?? $ema50[$last]);
        $trendDown = !is_nan($ema21[$last]) && !is_nan($ema50[$last])
            && $ema21[$last] < $ema50[$last] && $ema50[$last] < ($ema50[$last - 3] ?? $ema50[$last]);
        $out['trend'] = $trendUp ? 'bullish' : ($trendDown ? 'bearish' : 'neutral');

        if ($out['vwap'] !== null && $volumeOk) {
            $away = Config::vwapAwayBars();
            $belowRun = 0;
            $aboveRun = 0;
            for ($i = $last - 1; $i >= max(0, $last - 60); $i--) {
                if (is_nan($vwap[$i])) {
                    break;
                }
                if ($closes[$i] < $vwap[$i]) {
                    if ($aboveRun > 0) {
                        break;
                    }
                    $belowRun++;
                } elseif ($closes[$i] > $vwap[$i]) {
                    if ($belowRun > 0) {
                        break;
                    }
                    $aboveRun++;
                } else {
                    break;
                }
            }
            if ($closes[$last] > $vwap[$last] && $closes[$last - 1] <= $vwap[$last - 1] && $belowRun >= $away) {
                $out['long'][] = self::VWAP;
            }
            if ($closes[$last] < $vwap[$last] && $closes[$last - 1] >= $vwap[$last - 1] && $aboveRun >= $away) {
                $out['short'][] = self::VWAP;
            }
        }

        if ($volumeOk && !is_nan($ema9[$last])) {
            $window = Config::emaTouchWindow();
            $touchedUp = false;
            $touchedDown = false;
            for ($i = $last; $i > max(0, $last - $window); $i--) {
                if (!is_nan($ema21[$i]) && $candles[$i]->low <= $ema21[$i]) {
                    $touchedUp = true;
                }
                if (!is_nan($ema21[$i]) && $candles[$i]->high >= $ema21[$i]) {
                    $touchedDown = true;
                }
            }
            if ($trendUp && $touchedUp && $c->close > $ema9[$last] && $bullBar && $c->close > $prev->high) {
                $out['long'][] = self::EMA;
            }
            if ($trendDown && $touchedDown && $c->close < $ema9[$last] && $bearBar && $c->close < $prev->low) {
                $out['short'][] = self::EMA;
            }
        }

        $retest = $this->breakAndRetest($candles, $atr);
        if ($retest === 'long') {
            $out['long'][] = self::BREAK_RETEST;
        } elseif ($retest === 'short') {
            $out['short'][] = self::BREAK_RETEST;
        }

        if ($volumeOk) {
            $len = Config::sweepLookback();
            $swLow = Ta::lowest($candles, $len, 1);
            $swHigh = Ta::highest($candles, $len, 1);
            if (!is_nan($swLow) && $c->low < $swLow && $c->close > $swLow && $bullBar
                && ($c->close - $c->low) > ($c->high - $c->close)) {
                $out['long'][] = self::SWEEP;
            }
            if (!is_nan($swHigh) && $c->high > $swHigh && $c->close < $swHigh && $bearBar
                && ($c->high - $c->close) > ($c->close - $c->low)) {
                $out['short'][] = self::SWEEP;
            }
        }

        $divergence = $this->divergence($candles, $rsi);
        if ($divergence === 'long') {
            $out['long'][] = self::DIVERGENCE;
        } elseif ($divergence === 'short') {
            $out['short'][] = self::DIVERGENCE;
        }

        $bigMove = $this->sweepBigMove($candles);
        if ($bigMove === 'long') {
            $out['long'][] = self::BIG_MOVE;
        } elseif ($bigMove === 'short') {
            $out['short'][] = self::BIG_MOVE;
        }

        $channel = DeviationChannel::analyse($candles);
        if ($channel['signal'] === 'long') {
            $out['long'][] = self::CHANNEL;
        } elseif ($channel['signal'] === 'short') {
            $out['short'][] = self::CHANNEL;
        }

        return $out;
    }

    private function sweepBigMove(array $candles): ?string
    {
        $n = count($candles);
        $lookback = Config::bigMoveLookback();
        $window = Config::bigMoveConfirmBars();
        $minWick = Config::bigMoveMinWick();
        $bodyStrength = Config::bigMoveBodyStrength();
        if ($n < $lookback + $window + 3) {
            return null;
        }

        $c = $candles[$n - 1];
        $prev = $candles[$n - 2];
        $range = max($c->high - $c->low, 1e-12);
        $bodyRatio = abs($c->close - $c->open) / $range;

        $strongBull = $c->close > $c->open && $bodyRatio >= $bodyStrength && $c->close > $prev->high;
        $strongBear = $c->close < $c->open && $bodyRatio >= $bodyStrength && $c->close < $prev->low;
        if (!$strongBull && !$strongBear) {
            return null;
        }

        for ($i = $n - 2; $i >= max(1, $n - 1 - $window); $i--) {
            $sweep = $candles[$i];
            $priorHigh = -INF;
            $priorLow = INF;
            for ($k = max(0, $i - $lookback); $k < $i; $k++) {
                $priorHigh = max($priorHigh, $candles[$k]->high);
                $priorLow = min($priorLow, $candles[$k]->low);
            }
            if ($priorHigh === -INF || $priorLow === INF) {
                continue;
            }

            $sweepRange = max($sweep->high - $sweep->low, 1e-12);
            if ($strongBull
                && $sweep->low < $priorLow && $sweep->close > $priorLow
                && ((min($sweep->open, $sweep->close) - $sweep->low) / $sweepRange) >= $minWick) {
                return 'long';
            }
            if ($strongBear
                && $sweep->high > $priorHigh && $sweep->close < $priorHigh
                && (($sweep->high - max($sweep->open, $sweep->close)) / $sweepRange) >= $minWick) {
                return 'short';
            }
        }

        return null;
    }

    private function breakAndRetest(array $candles, float $atr): ?string
    {
        $n = count($candles);
        $pivotLen = Config::setupPivotLength();
        $window = Config::retestWindow();
        $tolerance = Config::retestTolerance() * $atr;

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);
        $ph = Ta::pivots($highs, $pivotLen, $pivotLen, true);
        $pl = Ta::pivots($lows, $pivotLen, $pivotLen, false);
        $c = $candles[$n - 1];

        for ($k = count($ph) - 1; $k >= 0; $k--) {
            $idx = $ph[$k];
            $level = $highs[$idx];
            $breakIndex = null;
            for ($i = $idx + $pivotLen + 1; $i < $n; $i++) {
                if ($candles[$i]->close > $level) {
                    $breakIndex = $i;
                    break;
                }
            }
            if ($breakIndex === null || ($n - 1 - $breakIndex) > $window || $breakIndex === $n - 1) {
                continue;
            }
            if ($c->low <= $level + $tolerance && $c->close > $level && $c->close > $c->open) {
                return 'long';
            }
            break;
        }

        for ($k = count($pl) - 1; $k >= 0; $k--) {
            $idx = $pl[$k];
            $level = $lows[$idx];
            $breakIndex = null;
            for ($i = $idx + $pivotLen + 1; $i < $n; $i++) {
                if ($candles[$i]->close < $level) {
                    $breakIndex = $i;
                    break;
                }
            }
            if ($breakIndex === null || ($n - 1 - $breakIndex) > $window || $breakIndex === $n - 1) {
                continue;
            }
            if ($c->high >= $level - $tolerance && $c->close < $level && $c->close < $c->open) {
                return 'short';
            }
            break;
        }

        return null;
    }

    private function divergence(array $candles, array $rsi): ?string
    {
        $pivotLen = Config::setupPivotLength();
        $gap = Config::divergenceGap();
        $n = count($candles);

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);
        $ph = Ta::pivots($highs, $pivotLen, $pivotLen, true);
        $pl = Ta::pivots($lows, $pivotLen, $pivotLen, false);

        $fresh = static fn(int $idx): bool => ($n - 1 - ($idx + $pivotLen)) <= 2;

        if (count($pl) >= 2) {
            $a = $pl[count($pl) - 2];
            $b = $pl[count($pl) - 1];
            if ($fresh($b) && ($b - $a) <= $gap
                && $lows[$b] < $lows[$a]
                && !is_nan($rsi[$b] ?? NAN) && !is_nan($rsi[$a] ?? NAN)
                && $rsi[$b] > $rsi[$a]) {
                return 'long';
            }
        }
        if (count($ph) >= 2) {
            $a = $ph[count($ph) - 2];
            $b = $ph[count($ph) - 1];
            if ($fresh($b) && ($b - $a) <= $gap
                && $highs[$b] > $highs[$a]
                && !is_nan($rsi[$b] ?? NAN) && !is_nan($rsi[$a] ?? NAN)
                && $rsi[$b] < $rsi[$a]) {
                return 'short';
            }
        }
        return null;
    }
}

final class SupportResistanceEngine
{
    public function __construct(
        private int $lookback = 5,
        private float $clusterAtrMultiplier = 0.5,
    ) {
    }

    public function detect(array $candles, string $timeframe): array
    {
        if (count($candles) < $this->lookback * 2 + 5) {
            return [];
        }
        $atr = SwingPivots::atr($candles);
        $tolerance = max($atr * $this->clusterAtrMultiplier, 1e-9);
        $pivots = SwingPivots::detect($candles, $this->lookback);

        $resistanceZones = $this->clusterPivots($pivots['highs'], $tolerance, ZoneType::RESISTANCE, $timeframe, true);
        $supportZones = $this->clusterPivots($pivots['lows'], $tolerance, ZoneType::SUPPORT, $timeframe, false);

        return array_merge($resistanceZones, $supportZones);
    }

    private function clusterPivots(array $pivots, float $tolerance, ZoneType $type, string $timeframe, bool $useHigh): array
    {
        if (empty($pivots)) {
            return [];
        }

        usort($pivots, static fn($a, $b) => ($useHigh ? $a['candle']->high : $a['candle']->low) <=> ($useHigh ? $b['candle']->high : $b['candle']->low));

        $clusters = [];
        $current = [];
        $lastPrice = null;

        foreach ($pivots as $pivot) {
            $price = $useHigh ? $pivot['candle']->high : $pivot['candle']->low;
            if ($lastPrice !== null && abs($price - $lastPrice) > $tolerance) {
                $clusters[] = $current;
                $current = [];
            }
            $current[] = $pivot;
            $lastPrice = $price;
        }
        if (!empty($current)) {
            $clusters[] = $current;
        }

        $zones = [];
        foreach ($clusters as $cluster) {
            $prices = array_map(static fn($p) => $useHigh ? $p['candle']->high : $p['candle']->low, $cluster);
            $volumes = array_map(static fn($p) => $p['candle']->volume, $cluster);
            $high = $useHigh ? max($prices) : max($prices) + $tolerance / 2;
            $low = $useHigh ? min($prices) - $tolerance / 2 : min($prices);
            $touches = count($cluster);
            $totalVolume = array_sum($volumes);
            $strength = min(100.0, ($touches * 15) + (log(1 + $totalVolume) * 2));

            $zones[] = new Zone(
                type: $type,
                high: $high,
                low: $low,
                timeframe: $timeframe,
                strength: round($strength, 2),
                volume: $totalVolume,
                touches: $touches,
                source: 'swing',
            );
        }

        return $zones;
    }
}

final class OrderBlockEngine
{
    public function __construct(
        private int $length = 5,
        private bool $mitigateOnClose = false,
    ) {
    }

    public function detect(array $candles, string $timeframe): array
    {
        $n = count($candles);
        $len = $this->length;
        if ($n < $len * 3 + 5) {
            return [];
        }

        $volumes = Ta::volumes($candles);
        $volumePivots = [];
        foreach (Ta::pivots($volumes, $len, $len, true) as $i) {
            $volumePivots[$i] = true;
        }

        $atr = SwingPivots::atr($candles);
        $blocks = [];
        $state = 0;

        for ($i = $len; $i < $n; $i++) {
            $upper = -INF;
            $lower = INF;
            for ($k = $i - $len + 1; $k <= $i; $k++) {
                $upper = max($upper, $candles[$k]->high);
                $lower = min($lower, $candles[$k]->low);
            }

            $back = $candles[$i - $len];
            if ($back->high > $upper) {
                $state = 0;
            } elseif ($back->low < $lower) {
                $state = 1;
            }

            if (!isset($volumePivots[$i - $len])) {
                continue;
            }

            $origin = $candles[$i - $len];
            $mid = ($origin->high + $origin->low) / 2;
            $strength = $atr > 0 ? round(min(100.0, ($origin->volume / max(1e-9, $this->averageVolume($volumes, $i))) * 25), 2) : 50.0;

            if ($state === 1) {
                $blocks[] = new OrderBlock(
                    type: OrderBlockType::BULLISH,
                    high: $mid,
                    low: $origin->low,
                    timeframe: $timeframe,
                    strength: $strength,
                    volume: $origin->volume,
                    mitigated: false,
                    createdAt: $origin->openTime,
                );
            } else {
                $blocks[] = new OrderBlock(
                    type: OrderBlockType::BEARISH,
                    high: $origin->high,
                    low: $mid,
                    timeframe: $timeframe,
                    strength: $strength,
                    volume: $origin->volume,
                    mitigated: false,
                    createdAt: $origin->openTime,
                );
            }
        }

        $blocks = $this->markMitigated($blocks, $candles);

        usort($blocks, static fn(OrderBlock $a, OrderBlock $b) => $b->createdAt <=> $a->createdAt);
        return array_slice($blocks, 0, 12);
    }

    private function markMitigated(array $blocks, array $candles): array
    {
        foreach ($blocks as $block) {
            foreach ($candles as $c) {
                if ($c->openTime <= $block->createdAt) {
                    continue;
                }
                $low = $this->mitigateOnClose ? min($c->open, $c->close) : $c->low;
                $high = $this->mitigateOnClose ? max($c->open, $c->close) : $c->high;
                if ($block->type === OrderBlockType::BULLISH ? $low < $block->low : $high > $block->high) {
                    $block->mitigated = true;
                    break;
                }
            }
        }
        return $blocks;
    }

    private function averageVolume(array $volumes, int $upTo): float
    {
        $slice = array_slice($volumes, max(0, $upTo - 20), 20);
        return empty($slice) ? 0.0 : array_sum($slice) / count($slice);
    }
}

final class FvgEngine
{
    public function detect(array $candles, string $timeframe): array
    {
        $n = count($candles);
        if ($n < 3) {
            return [];
        }
        $fvgs = [];

        for ($i = 2; $i < $n; $i++) {
            $left = $candles[$i - 2];
            $right = $candles[$i];

            if ($left->high < $right->low) {
                $gapHigh = $right->low;
                $gapLow = $left->high;
                $fvgs[] = new Fvg(
                    type: FvgType::BULLISH,
                    high: $gapHigh,
                    low: $gapLow,
                    midpoint: ($gapHigh + $gapLow) / 2,
                    timeframe: $timeframe,
                    size: $gapHigh - $gapLow,
                    filled: $this->isFilled($candles, $i + 1, $gapLow, $gapHigh),
                    createdAt: $right->openTime,
                );
            } elseif ($left->low > $right->high) {
                $gapHigh = $left->low;
                $gapLow = $right->high;
                $fvgs[] = new Fvg(
                    type: FvgType::BEARISH,
                    high: $gapHigh,
                    low: $gapLow,
                    midpoint: ($gapHigh + $gapLow) / 2,
                    timeframe: $timeframe,
                    size: $gapHigh - $gapLow,
                    filled: $this->isFilled($candles, $i + 1, $gapLow, $gapHigh),
                    createdAt: $right->openTime,
                );
            }
        }

        return array_slice($fvgs, -30);
    }

    private function isFilled(array $candles, int $fromIndex, float $low, float $high): bool
    {
        for ($i = $fromIndex; $i < count($candles); $i++) {
            if ($candles[$i]->low <= $high && $candles[$i]->high >= $low) {
                return true;
            }
        }
        return false;
    }
}

final class ConfluenceEngine
{
    public function score(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $zones,
        array $orderBlocks,
        array $fvgs,
        array $indicatorResults,
        string $trend,
    ): array {
        $weights = Config::confluenceWeights();
        $reasons = [];
        $breakdown = [];
        $price = $snapshot->price;

        $breakdown['trend'] = match ($trend) {
            'bullish' => 100.0,
            'bearish' => 0.0,
            default => 50.0,
        };
        if ($trend !== 'neutral') {
            $reasons[] = 'روند ساختاری: ' . ($trend === 'bullish' ? 'صعودی' : 'نزولی');
        }

        $nearestSupport = $this->nearestZone($zones, ZoneType::SUPPORT, $price);
        $supportPull = $nearestSupport !== null ? $this->proximityScore($price, $nearestSupport->mid(), $nearestSupport->strength) / 100 : 0.0;
        $breakdown['support'] = 50.0 + 50.0 * $supportPull;
        if ($nearestSupport !== null && $supportPull > 0.4) {
            $reasons[] = sprintf('نزدیک به ناحیه حمایت %s (قدرت %.0f)', number_format($nearestSupport->mid(), 6), $nearestSupport->strength);
        }

        $nearestResistance = $this->nearestZone($zones, ZoneType::RESISTANCE, $price);
        $resistancePull = $nearestResistance !== null ? $this->proximityScore($price, $nearestResistance->mid(), $nearestResistance->strength) / 100 : 0.0;
        $breakdown['resistance'] = 50.0 - 50.0 * $resistancePull;
        if ($nearestResistance !== null && $resistancePull > 0.4) {
            $reasons[] = sprintf('نزدیک به ناحیه مقاومت %s (قدرت %.0f)', number_format($nearestResistance->mid(), 6), $nearestResistance->strength);
        }

        $activeObs = array_values(array_filter(
            $orderBlocks,
            static fn(OrderBlock $ob) => !$ob->mitigated && $price >= $ob->low && $price <= $ob->high
        ));
        $bullishObs = count(array_filter($activeObs, static fn(OrderBlock $ob) => $ob->type === OrderBlockType::BULLISH));
        $breakdown['order_block'] = $this->directionalRatio($bullishObs, count($activeObs) - $bullishObs);
        foreach ($activeObs as $ob) {
            $reasons[] = sprintf(
                '%s Order Block فعال [%s - %s]',
                $ob->type === OrderBlockType::BULLISH ? 'صعودی' : 'نزولی',
                number_format($ob->low, 6),
                number_format($ob->high, 6)
            );
        }

        $activeFvgs = array_values(array_filter(
            $fvgs,
            static fn(Fvg $f) => !$f->filled && $price >= $f->low && $price <= $f->high
        ));
        $bullishFvgs = count(array_filter($activeFvgs, static fn(Fvg $f) => $f->type === FvgType::BULLISH));
        $breakdown['fvg'] = $this->directionalRatio($bullishFvgs, count($activeFvgs) - $bullishFvgs);
        foreach ($activeFvgs as $fvg) {
            $reasons[] = sprintf(
                '%s FVG پرنشده [%s - %s]',
                $fvg->type === FvgType::BULLISH ? 'صعودی' : 'نزولی',
                number_format($fvg->low, 6),
                number_format($fvg->high, 6)
            );
        }

        $indicatorScore = 50.0;
        $votes = [];
        foreach ($indicatorResults as $result) {
            if (isset($result['bias']) && is_string($result['bias'])) {
                $votes[] = match (strtolower($result['bias'])) {
                    'bullish' => 100.0,
                    'bearish' => 0.0,
                    default => 50.0,
                };
            }
        }
        if (!empty($votes)) {
            $indicatorScore = array_sum($votes) / count($votes);
        }
        $breakdown['indicators'] = $indicatorScore;

        $volumeScore = $this->volumeScore($snapshot->candlesFor($timeframe));
        $breakdown['volume'] = $volumeScore;
        if ($volumeScore > 60) {
            $reasons[] = 'حجم معاملات بالاتر از میانگین';
        }

        $directionalFactors = ['trend', 'support', 'resistance', 'order_block', 'fvg', 'indicators'];
        $weighted = 0.0;
        $evidenceWeight = 0.0;
        $totalWeight = 0.0;
        foreach ($directionalFactors as $factor) {
            $w = $weights[$factor] ?? 0.0;
            $totalWeight += $w;
            if (abs($breakdown[$factor] - 50.0) < 0.001) {
                continue;
            }
            $weighted += $w * $breakdown[$factor];
            $evidenceWeight += $w;
        }
        $directional = $evidenceWeight > 0 ? round($weighted / $evidenceWeight, 2) : 50.0;
        $coverage = $totalWeight > 0 ? $evidenceWeight / $totalWeight : 0.0;

        $strength = abs($directional - 50.0) * 2;
        $finalScore = round(0.75 * $strength * pow($coverage, 0.75) + 0.25 * $volumeScore, 2);

        $bias = $directional >= 60 ? 'bullish' : ($directional <= 40 ? 'bearish' : 'neutral');

        return [
            'score' => $finalScore,
            'directional' => $directional,
            'coverage' => round($coverage, 3),
            'breakdown' => $breakdown,
            'reasons' => $reasons,
            'bias' => $bias,
        ];
    }

    private function directionalRatio(int $bullish, int $bearish): float
    {
        $total = $bullish + $bearish;
        if ($total <= 0) {
            return 50.0;
        }
        return 50.0 + 50.0 * (($bullish - $bearish) / $total);
    }

    private function nearestZone(array $zones, ZoneType $type, float $price): ?Zone
    {
        $candidates = array_filter($zones, static fn(Zone $z) => $z->type === $type);
        if (empty($candidates)) {
            return null;
        }
        usort($candidates, static fn(Zone $a, Zone $b) => abs($a->mid() - $price) <=> abs($b->mid() - $price));
        return array_values($candidates)[0];
    }

    private function proximityScore(float $price, float $zoneMid, float $strength): float
    {
        if ($zoneMid <= 0) {
            return 0.0;
        }
        $distancePct = abs($price - $zoneMid) / $zoneMid * 100;
        $proximity = max(0.0, 1 - min($distancePct / 2, 1));
        return round($proximity * $strength, 2);
    }

    private function volumeScore(array $candles): float
    {
        $n = count($candles);
        if ($n < 10) {
            return 50.0;
        }
        $recent = array_slice($candles, -10);
        $last = end($recent)->volume;
        $avg = array_sum(array_map(static fn(Candle $c) => $c->volume, $recent)) / count($recent);
        if ($avg <= 0) {
            return 50.0;
        }
        $ratio = $last / $avg;
        return round(min(100.0, max(0.0, $ratio * 50)), 2);
    }
}

interface Strategy
{
    public function name(): string;

    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $zones,
        array $orderBlocks,
        array $fvgs,
        array $confluence,
    ): ?array;

    public function lastRejection(): ?string;
}

final class DefaultStructureStrategy implements Strategy
{
    private ?string $rejection = null;

    public function lastRejection(): ?string
    {
        return $this->rejection;
    }

    public function name(): string
    {
        return 'default_structure';
    }

    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $zones,
        array $orderBlocks,
        array $fvgs,
        array $confluence,
    ): ?array {
        if ($confluence['bias'] === 'neutral') {
            return null;
        }

        $price = $snapshot->price;
        $atr = SwingPivots::atr($snapshot->candlesFor($timeframe));
        if ($atr <= 0 || $price <= 0) {
            return null;
        }

        $direction = $confluence['bias'] === 'bullish' ? Direction::LONG : Direction::SHORT;

        if ($direction === Direction::LONG) {
            $supports = array_values(array_filter($zones, static fn(Zone $z) => $z->type === ZoneType::SUPPORT && $z->mid() <= $price));
            $bullishObs = array_values(array_filter($orderBlocks, static fn(OrderBlock $ob) => $ob->type === OrderBlockType::BULLISH && !$ob->mitigated));

            $stopBasis = $price - ($atr * 1.5);
            if (!empty($supports)) {
                usort($supports, static fn($a, $b) => $b->mid() <=> $a->mid());
                $stopBasis = min($stopBasis, $supports[0]->low);
            }
            if (!empty($bullishObs)) {
                $stopBasis = min($stopBasis, end($bullishObs)->low);
            }

            $entry = $price;
            $stopLoss = $stopBasis;
            $risk = $entry - $stopLoss;
            if ($risk <= 0) {
                return null;
            }

            $resistances = array_values(array_filter($zones, static fn(Zone $z) => $z->type === ZoneType::RESISTANCE && $z->low > $price));
            $bearishObs = array_values(array_filter($orderBlocks, static fn(OrderBlock $ob) => $ob->type === OrderBlockType::BEARISH && !$ob->mitigated && $ob->low > $price));
            $targets = self::structuralTargets($price, $risk, $resistances, $bearishObs, true);

            return [
                'direction' => $direction,
                'entry' => $entry,
                'stop_loss' => $stopLoss,
                'tp1' => $targets[0],
                'tp2' => $targets[1],
                'tp3' => $targets[2],
            ];
        }

        $supportsBelow = array_values(array_filter($zones, static fn(Zone $z) => $z->type === ZoneType::SUPPORT && $z->high < $price));
        $bullishObsBelow = array_values(array_filter($orderBlocks, static fn(OrderBlock $ob) => $ob->type === OrderBlockType::BULLISH && !$ob->mitigated && $ob->high < $price));
        $resistances = array_values(array_filter($zones, static fn(Zone $z) => $z->type === ZoneType::RESISTANCE && $z->mid() >= $price));
        $bearishObs = array_values(array_filter($orderBlocks, static fn(OrderBlock $ob) => $ob->type === OrderBlockType::BEARISH && !$ob->mitigated));

        $stopBasis = $price + ($atr * 1.5);
        if (!empty($resistances)) {
            usort($resistances, static fn($a, $b) => $a->mid() <=> $b->mid());
            $stopBasis = max($stopBasis, $resistances[0]->high);
        }
        if (!empty($bearishObs)) {
            $stopBasis = max($stopBasis, end($bearishObs)->high);
        }

        $entry = $price;
        $stopLoss = $stopBasis;
        $risk = $stopLoss - $entry;
        if ($risk <= 0) {
            return null;
        }

        $targets = self::structuralTargets($price, $risk, $supportsBelow, $bullishObsBelow, false);

        return [
            'direction' => $direction,
            'entry' => $entry,
            'stop_loss' => $stopLoss,
            'tp1' => $targets[0],
            'tp2' => $targets[1],
            'tp3' => $targets[2],
        ];
    }

    private static function structuralTargets(float $price, float $risk, array $zones, array $obs, bool $ascending): array
    {
        $levels = [];
        foreach ($zones as $z) {
            $levels[] = $ascending ? $z->low : $z->high;
        }
        foreach ($obs as $ob) {
            $levels[] = $ascending ? $ob->low : $ob->high;
        }
        if ($ascending) {
            sort($levels);
        } else {
            rsort($levels);
        }

        $minGap = $risk * 0.5;
        $kept = [];
        $last = $price;
        foreach ($levels as $lvl) {
            $dist = $ascending ? $lvl - $last : $last - $lvl;
            if ($dist < $minGap) {
                continue;
            }
            $kept[] = $lvl;
            $last = $lvl;
            if (count($kept) >= 3) {
                break;
            }
        }

        $fallback = [2.0, 3.5, 5.0];
        $sign = $ascending ? 1 : -1;
        for ($i = 0; $i < 3; $i++) {
            if (isset($kept[$i])) {
                continue;
            }
            $candidate = $price + $sign * $risk * $fallback[$i];
            $prevLevel = $i > 0 ? $kept[$i - 1] : $price;

            $kept[$i] = $ascending
                ? max($candidate, $prevLevel + $minGap)
                : min($candidate, $prevLevel - $minGap);
        }

        return [$kept[0], $kept[1], $kept[2]];
    }
}

final class StructureBreakStrategy implements Strategy
{
    private ?string $rejection = null;

    public function name(): string
    {
        return 'structure_break';
    }

    public function lastRejection(): ?string
    {
        return $this->rejection;
    }

    private function reject(string $why): ?array
    {
        $this->rejection = $why;
        return null;
    }

    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $zones,
        array $orderBlocks,
        array $fvgs,
        array $confluence,
    ): ?array {
        $candles = $snapshot->candlesFor($timeframe);
        $price = $snapshot->price;
        $atr = SwingPivots::atr($candles);
        if ($price <= 0 || $atr <= 0) {
            return null;
        }

        $this->rejection = null;
        $ms = MarketStructure::analyse($candles);
        if ($ms['event'] === null || $ms['level'] === null) {
            return $this->reject('هنوز سقف یا کف کوچکی نشکسته (CHoCH/BOS ندارد)');
        }

        $isLong = $ms['direction'] === 'bullish';
        $direction = $isLong ? Direction::LONG : Direction::SHORT;

        $setup = null;
        if ($isLong && ($ms['basing'] || $ms['range_position'] <= 0.55)) {
            $setup = 'breakout';
        } elseif (!$isLong && ($ms['run_pct'] >= Config::reversalRunPercent() || $ms['range_position'] >= 0.55)) {
            $setup = 'reversal';
        }
        if ($setup === null) {
            return $this->reject($isLong
                ? 'شکست رو به بالا ولی قیمت در سقف محدوده است، نه در کف'
                : 'برگشت نزولی ولی ارز رشد قابل‌توجهی نکرده بود');
        }

        if (Config::requireReversalCandle()) {
            $confirmation = ReversalCandleEngine::check($snapshot->candlesFor(Config::reversalConfirmTimeframe()), $isLong);
            if (!$confirmation['confirmed']) {
                return $this->reject($confirmation['reason']);
            }
        }

        if ($ms['break_volume_ratio'] < Config::breakVolumeRatio()) {
            return $this->reject(sprintf(
                'حجم پشت شکست کم بود (%.1f برابر میانگین، حداقل %.1f)',
                $ms['break_volume_ratio'],
                Config::breakVolumeRatio()
            ));
        }

        if (abs($price - $ms['level']) > $atr * Config::maxChaseAtr()) {
            return $this->reject('قیمت از سطح شکست خیلی دور شده');
        }
        $wanted = $isLong ? 'bullish' : 'bearish';
        if ($confluence['bias'] !== 'neutral' && $confluence['bias'] !== $wanted) {
            return $this->reject('جهت شکست با مجموع اندیکاتورها و ساختار هم‌خوان نیست');
        }

        $guard = $this->guardLevel($isLong, $price, $orderBlocks, $fvgs);
        if ($guard === null && Config::requireZoneConfluence()) {
            return $this->reject('اوردر بلاک یا FVG معتبری برای گذاشتن حد ضرر پشت آن نبود');
        }

        $structural = $isLong
            ? min($ms['swing_low'] ?? $price, $ms['level'])
            : max($ms['swing_high'] ?? $price, $ms['level']);
        $stop = $isLong
            ? min($structural, $guard ?? $structural) - $atr * 0.25
            : max($structural, $guard ?? $structural) + $atr * 0.25;

        if (($isLong && $stop >= $price) || (!$isLong && $stop <= $price)) {
            return $this->reject('حد ضرر ساختاری سمت اشتباه قیمت افتاد');
        }

        return [
            'direction' => $direction,
            'entry' => $price,
            'stop_loss' => $stop,
            'tp1' => null,
            'tp2' => null,
            'tp3' => null,
            'setup' => $setup,
            'event' => $ms['event'],
        ];
    }

    private function guardLevel(bool $isLong, float $price, array $orderBlocks, array $fvgs): ?float
    {
        $best = null;
        foreach ($orderBlocks as $ob) {
            if ($ob->mitigated) {
                continue;
            }
            if ($isLong && $ob->type === OrderBlockType::BULLISH && $ob->high <= $price) {
                $best = $best === null ? $ob->low : max($best, $ob->low);
            }
            if (!$isLong && $ob->type === OrderBlockType::BEARISH && $ob->low >= $price) {
                $best = $best === null ? $ob->high : min($best, $ob->high);
            }
        }
        foreach ($fvgs as $fvg) {
            if ($fvg->filled) {
                continue;
            }
            if ($isLong && $fvg->high <= $price) {
                $best = $best === null ? $fvg->low : max($best, $fvg->low);
            }
            if (!$isLong && $fvg->low >= $price) {
                $best = $best === null ? $fvg->high : min($best, $fvg->high);
            }
        }
        return $best;
    }
}

final class ConfluenceProStrategy implements Strategy
{
    private const GROUPS = ['structure', 'liquidity', 'location', 'momentum', 'volume', 'htf'];

    private ?string $rejection = null;
    private LiquidityEngine $liquidity;
    private SupplyDemandEngine $supplyDemand;
    private RangeEngine $ranges;
    private SetupScanner $setups;

    private array $lastVotes = [];

    public function __construct()
    {
        $this->liquidity = new LiquidityEngine();
        $this->supplyDemand = new SupplyDemandEngine();
        $this->ranges = new RangeEngine();
        $this->setups = new SetupScanner();
    }

    public function name(): string
    {
        return 'confluence_pro';
    }

    public function lastRejection(): ?string
    {
        return $this->rejection;
    }

    public function lastVotes(): array
    {
        return $this->lastVotes;
    }

    private function reject(string $why): ?array
    {
        $this->rejection = $why;
        return null;
    }

    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $zones,
        array $orderBlocks,
        array $fvgs,
        array $confluence,
    ): ?array {
        $this->rejection = null;
        $this->lastVotes = [];

        $candles = $snapshot->candlesFor($timeframe);
        $price = $snapshot->price;
        $atr = SwingPivots::atr($candles);
        if ($price <= 0 || $atr <= 0 || count($candles) < 60) {
            return $this->reject('کندل کافی برای تحلیل ترکیبی نیست');
        }

        if (Config::requireKillzone() && !KillzoneEngine::isActive($snapshot->timestamp)) {
            return $this->reject('خارج از بازه‌های زمانی پرحجم (Killzone)');
        }

        if (Config::requireAdxFilter()) {
            $adx = Ta::adx($candles);
            if (!is_nan($adx) && $adx < Config::minAdx()) {
                return $this->reject(sprintf('روند بازار ضعیف است (ADX %.1f کمتر از حداقل %.1f)', $adx, Config::minAdx()));
            }
        }

        $setups = $this->setups->scan($candles);
        $wave = TrendWave::analyse($candles);
        $range = $this->ranges->detect($candles);
        $liquidity = $this->liquidity->detect($candles);
        $sdZones = $this->supplyDemand->detect($candles);
        $structure = MarketStructure::analyse($candles);
        $smc = SmcContext::analyse($candles);
        $htf = $this->higherTimeframeBias($snapshot, $timeframe);

        $reasons = ['long' => [], 'short' => []];
        $votes = ['long' => 0.0, 'short' => 0.0];

        $groups = ['long' => array_fill_keys(self::GROUPS, 0.0), 'short' => array_fill_keys(self::GROUPS, 0.0)];
        $vote = function (string $side, string $group, float $amount) use (&$votes, &$groups): void {
            $votes[$side] += $amount;
            $groups[$side][$group] += $amount;
        };

        foreach ($setups['long'] as $setup) {
            $vote('long', self::groupForSetup($setup), Config::setupWeight());
            $reasons['long'][] = self::setupFa($setup);
        }
        foreach ($setups['short'] as $setup) {
            $vote('short', self::groupForSetup($setup), Config::setupWeight());
            $reasons['short'][] = self::setupFa($setup);
        }

        if ($range['qualified'] && $range['break'] !== null) {
            $side = $range['break'] === 'up' ? 'long' : 'short';
            $vote($side, 'structure', Config::rangeWeight());
            $reasons[$side][] = 'شکست محدوده رنج تاییدشده';
        }

        if ($structure['event'] !== null && $structure['direction'] !== null) {
            $side = $structure['direction'] === 'bullish' ? 'long' : 'short';
            $vote($side, 'structure', Config::structureWeight());
            $reasons[$side][] = $structure['event'] === 'choch'
                ? 'تغییر کاراکتر ساختار (CHoCH)'
                : ($structure['event'] === 'bos' ? 'شکست ساختار (BOS)' : 'شکست محدوده');
        }

        if ($wave['event'] !== null && $wave['event_dir'] !== null && $wave['event_index'] !== null
            && (count($candles) - 1 - $wave['event_index']) <= Config::structureMaxAge()) {
            $side = $wave['event_dir'] === 'bullish' ? 'long' : 'short';
            $vote($side, 'structure', Config::structureWeight());
            $reasons[$side][] = strtoupper($wave['event']) . ' روی موج روند';
        }

        if ($smc['internal_event'] !== null && $smc['internal_dir'] !== null) {
            $sideI = $smc['internal_dir'] === 'bullish' ? 'long' : 'short';
            $vote($sideI, 'structure', Config::internalStructureWeight());
            $reasons[$sideI][] = 'شکست ساختار داخلی (iBOS)';
        }

        $sweep = $liquidity['recent_sweep'];
        if ($sweep !== null) {
            $side = $sweep['is_high'] ? 'short' : 'long';
            $vote($side, 'liquidity', Config::liquidityWeight());
            $reasons[$side][] = 'جاروی نقدینگی روی ' . ($sweep['is_high'] ? 'سقف' : 'کف') . ' و برگشت';
        }

        $breaker = BreakerBlockEngine::detect(count($candles), $liquidity, $structure);
        if ($breaker !== null && $breaker['fresh']) {
            $sideB = $breaker['direction'] === 'bullish' ? 'long' : 'short';
            $vote($sideB, 'liquidity', Config::breakerBlockWeight());
            $reasons[$sideB][] = 'بریکر بلاک تازه تایید شده (جارو + برگشت ساختار)';
        }

        $squeeze = SqueezeEngine::detect($candles);
        if ($squeeze['squeezing'] && $squeeze['expanding'] && $squeeze['direction'] !== null) {
            $sideS = $squeeze['direction'] === 'bullish' ? 'long' : 'short';
            $vote($sideS, 'momentum', Config::squeezeWeight());
            $reasons[$sideS][] = 'فشردگی نوسان و شروع انفجار حرکت';
        }

        $flip = BreakerBlockEngine::detectFlip($price, $orderBlocks);
        if ($flip !== null) {
            $sideF = $flip['direction'] === 'bullish' ? 'long' : 'short';
            $vote($sideF, 'liquidity', Config::breakerBlockWeight());
            $reasons[$sideF][] = 'بریکر بلاک (اوردر بلاک شکسته و برگشت‌نکرده) فعال است';
        }

        $rsiReversal = RsiReversalEngine::detect($candles);
        if ($rsiReversal['signal'] !== null) {
            $sideR = $rsiReversal['signal'] === 'bullish' ? 'long' : 'short';
            $vote($sideR, 'momentum', Config::rsiReversalWeight());
            $reasons[$sideR][] = sprintf('احتمال برگشت RSI (%.0f%% اطمینان آماری)', $rsiReversal['confidence'] * 100);
        }

        $superTrend = SuperTrendAiEngine::detect($candles);
        if ($superTrend['direction'] !== null && $superTrend['confidence'] >= 0.5) {
            $sideST = $superTrend['direction'] === 'bullish' ? 'long' : 'short';
            $vote($sideST, 'momentum', Config::superTrendWeight());
            $reasons[$sideST][] = sprintf('سوپرترند تطبیقی هم‌جهت (%.0f%% عملکرد اخیر)', $superTrend['confidence'] * 100);
        }

        $closes = array_map(static fn(Candle $c) => $c->close, $candles);
        $macd = Ta::macd($closes);
        $macdLast = end($macd['macd']);
        $macdSignalLast = end($macd['signal']);
        $macdHistLast = end($macd['histogram']);
        if (!is_nan($macdLast) && !is_nan($macdSignalLast) && !is_nan($macdHistLast)) {
            if ($macdLast > $macdSignalLast && $macdHistLast > 0) {
                $vote('long', 'momentum', Config::macdWeight());
                $reasons['long'][] = 'مکدی بالای خط سیگنال با هیستوگرام مثبت';
            } elseif ($macdLast < $macdSignalLast && $macdHistLast < 0) {
                $vote('short', 'momentum', Config::macdWeight());
                $reasons['short'][] = 'مکدی زیر خط سیگنال با هیستوگرام منفی';
            }
        }

        $volumeImbalance = VolumeImbalanceEngine::detect($candles);
        if ($volumeImbalance['present']) {
            $sideV = $volumeImbalance['direction'] === 'bullish' ? 'long' : 'short';
            $vote($sideV, 'volume', Config::volumeImbalanceWeight());
            $reasons[$sideV][] = 'عدم تعادل حجمی (Volume Imbalance) در همین کندل';
        }
        $displacement = DisplacementEngine::detect($candles);
        if ($displacement['present']) {
            $sideD = $displacement['direction'] === 'bullish' ? 'long' : 'short';
            $vote($sideD, 'volume', Config::displacementWeight());
            $reasons[$sideD][] = 'کندل جابجایی (Displacement) با بدنه بزرگ';
        }

        $strongZone = $this->nearestStrongZone($price, $atr, $zones);
        if ($strongZone !== null) {
            $vote($strongZone['side'], 'location', Config::strongZoneWeight());
            $reasons[$strongZone['side']][] = sprintf('نزدیک قوی‌ترین سطح حمایت/مقاومت (%d بار لمس شده)', $strongZone['touches']);
        }

        $direction = null;
        if ($votes['long'] > $votes['short'] && $votes['long'] > 0) {
            $direction = Direction::LONG;
        } elseif ($votes['short'] > $votes['long'] && $votes['short'] > 0) {
            $direction = Direction::SHORT;
        }
        if ($direction === null) {
            return $this->reject($votes['long'] > 0 || $votes['short'] > 0
                ? 'سیگنال‌های خرید و فروش هم‌وزن شدند'
                : 'هیچ‌کدام از شش ستاپ فعال نشد');
        }

        $isLong = $direction === Direction::LONG;
        $side = $isLong ? 'long' : 'short';
        $otherSide = $isLong ? 'short' : 'long';
        $score = $votes[$side];
        $why = $reasons[$side];

        $opposingRatio = Config::maxOpposingVoteRatio();
        if ($opposingRatio < 1.0 && $votes[$otherSide] >= $votes[$side] * $opposingRatio) {
            return $this->reject(sprintf(
                'شواهد خلاف جهت خیلی قوی بود (%.0f در برابر %.0f)',
                $votes[$otherSide],
                $votes[$side]
            ));
        }

        if ($strongZone !== null && $strongZone['side'] !== $side
            && $strongZone['touches'] >= Config::strongZoneVetoTouches()) {
            return $this->reject(sprintf(
                'قوی‌ترین سطح حمایت/مقاومت نزدیک قیمت (%d بار لمس‌شده) برخلاف جهت این معامله‌ست',
                $strongZone['touches']
            ));
        }

        $waveAgainst = $wave['direction'] !== 'neutral' && $wave['direction'] !== ($isLong ? 'bullish' : 'bearish');
        $emaAgainst = $setups['trend'] !== 'neutral' && $setups['trend'] !== ($isLong ? 'bullish' : 'bearish');

        $reversal = $sweep !== null && ($sweep['is_high'] !== $isLong);
        if (($waveAgainst || $emaAgainst) && !$reversal) {
            return $this->reject('جهت ستاپ برخلاف روند موج/میانگین‌هاست');
        }
        if (!$waveAgainst && !$emaAgainst) {
            $score += Config::trendWeight();
            $groups[$side]['htf'] += Config::trendWeight();
            $why[] = 'هم‌جهت با روند موج و میانگین‌ها';
        }

        $ema200 = Ta::ema($closes, 200);
        $ema200Last = end($ema200);
        if (!is_nan($ema200Last)) {
            $aboveEma200 = $price > $ema200Last;
            if ($aboveEma200 === $isLong) {
                $score += Config::ema200Weight();
                $groups[$side]['htf'] += Config::ema200Weight();
                $why[] = $isLong ? 'قیمت بالای EMA200 (روند بلندمدت صعودی)' : 'قیمت زیر EMA200 (روند بلندمدت نزولی)';
            }
        }

        if (!$reversal && $wave['direction'] !== 'neutral' && $wave['direction'] === ($isLong ? 'bullish' : 'bearish')
            && $wave['distance_atr'] > Config::maxChaseAtr()) {
            return $this->reject('قیمت خیلی از میانگین حرکت فاصله گرفته');
        }

        $guard = $this->guardLevel($isLong, $price, $atr, $orderBlocks, $fvgs, $sdZones, $candles);
        if ($guard === null) {
            return $this->reject('اوردر بلاک، FVG یا ناحیه عرضه/تقاضایی نزدیک قیمت نبود');
        }
        $score += Config::zoneWeight();
        $groups[$side]['location'] += Config::zoneWeight();
        $why[] = $guard['label'];

        $mitigation = $this->nearestFvgMitigation($isLong, $price, $atr, $fvgs);
        if ($mitigation !== null && $mitigation >= Config::minFvgMitigationPercent()) {
            $score += Config::fvgMitigationWeight();
            $groups[$side]['location'] += Config::fvgMitigationWeight();
            $why[] = sprintf('بازگشت %.0f%% به داخل FVG', $mitigation);
        }

        $target = $this->liquidityTarget($isLong, $price, $liquidity['levels']);

        $equal = $isLong ? $smc['eqh'] : $smc['eql'];
        if ($equal !== null && ($isLong ? $equal > $price : $equal < $price)) {
            $target = $target === null
                ? $equal
                : ($isLong ? min($target, $equal) : max($target, $equal));
            $why[] = $isLong ? 'سقف‌های برابر (EQH) بالای قیمت' : 'کف‌های برابر (EQL) زیر قیمت';
        }
        if ($target !== null) {
            $score += Config::liquidityWeight();
            $groups[$side]['liquidity'] += Config::liquidityWeight();
            $why[] = 'نقدینگی دست‌نخورده در مسیر هدف';
        }

        $continuation = $this->isContinuation($setups[$side], $range, $structure, $smc);

        if ($continuation && Config::requireBreakoutMomentum()) {
            $momentumWith = $groups[$side]['momentum'] + $groups[$side]['volume'];
            $momentumAgainst = $groups[$otherSide]['momentum'] + $groups[$otherSide]['volume'];
            if ($momentumWith <= 0 || $momentumWith < $momentumAgainst) {
                return $this->reject('شکست بدون تایید مومنتوم و حجم');
            }
        }

        if (!$continuation && $sweep !== null && Config::requireFreshReversalZone()) {
            $sweepTime = (float) $candles[$sweep['index']]->openTime;
            if ($guard['origin'] < $sweepTime) {
                return $this->reject('ناحیه‌ای که حد ضرر روی آن است قبل از جاروی نقدینگی اخیر شکل گرفته');
            }
        }

        if (!$continuation && $sweep !== null && Config::requireSweepAtZone()) {
            if (abs($guard['level'] - $sweep['price']) > $atr * Config::zoneReachAtr()) {
                return $this->reject('کندل جاروی نقدینگی (حرکت شارپ) درست روی این ناحیه (اوردر بلاک/حمایت/مقاومت) اتفاق نیفتاده');
            }
        }

        if (!$continuation && Config::requireReversalCandle()) {
            $confirmation = ReversalCandleEngine::check($snapshot->candlesFor(Config::reversalConfirmTimeframe()), $isLong);
            if (!$confirmation['confirmed']) {
                return $this->reject($confirmation['reason']);
            }
            $why[] = $confirmation['reason'];
        }

        $wrongHalf = $isLong ? $smc['zone'] === 'premium' : $smc['zone'] === 'discount';

        if (!$continuation) {
            if ($wrongHalf && Config::requireDiscountPremium() && !$reversal) {
                return $this->reject($isLong
                    ? 'ورود پولبکی در نیمه بالای محدوده (Premium)'
                    : 'ورود پولبکی در نیمه پایین محدوده (Discount)');
            }
            if (!$wrongHalf) {
                $score += Config::premiumDiscountWeight();
                $groups[$side]['location'] += Config::premiumDiscountWeight();
                $why[] = $isLong ? 'ورود از ناحیه ارزان (Discount)' : 'ورود از ناحیه گران (Premium)';
            }
        }

        if ($smc['in_ote'] && !$continuation) {
            $score += Config::oteWeight();
            $groups[$side]['location'] += Config::oteWeight();
            $why[] = 'قیمت داخل ناحیه ورود بهینه (OTE ۶۲-۷۹٪)';
        }

        if ($htf !== null) {
            if ($htf === ($isLong ? 'bullish' : 'bearish')) {
                $score += Config::htfWeight();
                $groups[$side]['htf'] += Config::htfWeight();
                $why[] = 'هم‌جهت با تایم‌فریم بالاتر';
            } elseif (Config::requireHtfAlignment() && !$reversal) {
                return $this->reject('تایم‌فریم بالاتر خلاف جهت این معامله است');
            }
        }

        $groupCaps = self::groupCaps();
        $rawScore = $score;
        $score = 0.0;
        $groupScores = [];
        foreach ($groupCaps as $group => $cap) {
            $groupScores[$group] = round(min($groups[$side][$group], $cap), 1);
            $score += $groupScores[$group];
        }

        if ($score < Config::minConfluenceScore()) {
            return $this->reject(sprintf(
                'امتیاز هم‌گرایی %.0f از حداقل لازم %.0f کمتر بود (%s)',
                $score,
                Config::minConfluenceScore(),
                implode('، ', array_slice($why, 0, 3))
            ));
        }

        $volRatio = $this->volatilityRatio($candles, $atr);
        $stopPad = $volRatio <= Config::volatilityCalmRatio()
            ? Config::stopPadAtrCalm()
            : ($volRatio >= Config::volatilityHotRatio() ? Config::stopPadAtrVolatile() : Config::stopPadAtr());

        $stop = $isLong
            ? min($guard['level'], $price - $atr * 0.5) - $atr * $stopPad
            : max($guard['level'], $price + $atr * 0.5) + $atr * $stopPad;
        if (($isLong && $stop >= $price) || (!$isLong && $stop <= $price)) {
            return $this->reject('حد ضرر ساختاری سمت اشتباه قیمت افتاد');
        }

        $risk = abs($price - $stop);
        if ($target !== null && $risk > 0) {
            $room = abs($target - $price);
            $roomR = $room / $risk;
            if ($roomR < Config::minRoomToTargetR()) {
                return $this->reject(sprintf(
                    'فاصله تا نزدیک‌ترین ساختار مخالف فقط %.2fR است (حداقل لازم %.1fR)',
                    $roomR,
                    Config::minRoomToTargetR()
                ));
            }
        }

        $this->lastVotes = [
            'volatility_ratio' => round($volRatio, 2),
            'stop_pad_atr' => $stopPad,
            'score' => round($score, 1),
            'score_raw' => round($rawScore, 1),
            'groups' => $groupScores,
            'group_caps' => $groupCaps,
            'setups' => $setups[$side],
            'trend' => $setups['trend'],
            'wave' => $wave['direction'],
            'range' => $range['qualified'] ? ($range['break'] ?? 'inside') : 'none',
            'structure' => $structure['event'],
            'structure_dir' => $structure['direction'],
            'range_position' => $structure['range_position'],
            'continuation' => $continuation,
            'internal' => $smc['internal_dir'],
            'sweep' => $sweep !== null,
            'breaker' => $breaker !== null && $breaker['fresh'] ? $breaker['direction'] : null,
            'breaker_flip' => $flip['direction'] ?? null,
            'squeeze' => $squeeze['squeezing'] && $squeeze['expanding'] ? $squeeze['direction'] : null,
            'rsi_reversal' => $rsiReversal['signal'],
            'super_trend' => $superTrend['direction'],
            'volume_imbalance' => $volumeImbalance['direction'],
            'displacement' => $displacement['direction'],
            'strong_zone' => $strongZone['touches'] ?? null,
            'fvg_mitigation_pct' => $mitigation,
            'zone' => $guard['label'],
            'pd' => $smc['zone'],
            'ote' => $smc['in_ote'],
            'htf' => $htf,
            'liquidity_target' => $target,
        ];

        return [
            'direction' => $direction,
            'entry' => $price,
            'stop_loss' => $stop,
            'tp1' => null,
            'tp2' => null,
            'tp3' => null,

            'structural_target' => $target,
            'setup' => $isLong ? 'breakout' : 'reversal',
            'event' => $structure['event'] ?? ($wave['event'] ?? 'confluence'),
            'confluence_score' => round($score, 1),
            'confluence_reasons' => $why,
        ];
    }

    private function isContinuation(array $firedSetups, array $range, array $structure, array $smc): bool
    {
        if ($range['qualified'] && $range['break'] !== null) {
            return true;
        }
        if (in_array($structure['event'] ?? null, ['bos', 'break'], true)) {
            return true;
        }
        foreach ([SetupScanner::BIG_MOVE, SetupScanner::BREAK_RETEST, SetupScanner::RANGE_BREAK] as $momentum) {
            if (in_array($momentum, $firedSetups, true)) {
                return true;
            }
        }
        return false;
    }

    private function higherTimeframeBias(MarketSnapshot $snapshot, string $timeframe): ?string
    {
        $best = Config::htfConfirmTimeframe($timeframe);
        if ($best === null) {
            $order = Config::signalTimeframes();
            $seconds = static function (string $tf): int {
                $unit = strtolower(substr($tf, -1));
                $value = (int) substr($tf, 0, -1);
                return match ($unit) {
                    'm' => $value * 60,
                    'h' => $value * 3600,
                    'd' => $value * 86400,
                    default => 0,
                };
            };
            $current = $seconds($timeframe);
            $bestSeconds = PHP_INT_MAX;
            foreach ($order as $tf) {
                $s = $seconds($tf);
                if ($s > $current && $s < $bestSeconds) {
                    $best = $tf;
                    $bestSeconds = $s;
                }
            }
        }
        if ($best === null) {
            return null;
        }

        $higher = $snapshot->candlesFor($best);
        if (count($higher) < 40) {
            return null;
        }
        $wave = TrendWave::analyse($higher);
        if ($wave['direction'] === 'neutral') {
            return null;
        }
        if ($wave['structure_trend'] !== null && $wave['structure_trend'] !== $wave['direction']) {
            return null;
        }
        return $wave['direction'];
    }

    private function guardLevel(bool $isLong, float $price, float $atr, array $orderBlocks, array $fvgs, array $sdZones, array $candles): ?array
    {
        $reach = $atr * Config::zoneReachAtr();
        $best = null;

        $consider = function (float $level, string $label, float $origin) use (&$best, $isLong, $price, $reach): void {
            if ($isLong && ($level > $price || $level < $price - $reach)) {
                return;
            }
            if (!$isLong && ($level < $price || $level > $price + $reach)) {
                return;
            }
            if ($best === null
                || ($isLong && $level > $best['level'])
                || (!$isLong && $level < $best['level'])) {
                $best = ['level' => $level, 'label' => $label, 'origin' => $origin];
            }
        };

        foreach ($orderBlocks as $ob) {
            if ($ob->mitigated) {
                continue;
            }
            if ($isLong && $ob->type === OrderBlockType::BULLISH) {
                $consider($ob->low, 'اوردر بلاک صعودی زیر قیمت', (float) $ob->createdAt);
            }
            if (!$isLong && $ob->type === OrderBlockType::BEARISH) {
                $consider($ob->high, 'اوردر بلاک نزولی بالای قیمت', (float) $ob->createdAt);
            }
        }
        foreach ($fvgs as $fvg) {
            if ($fvg->filled) {
                continue;
            }
            $consider($isLong ? $fvg->low : $fvg->high, 'گپ قیمتی (FVG) پرنشده', (float) $fvg->createdAt);
        }
        foreach ($sdZones as $zone) {
            $originTime = isset($candles[$zone->startIndex]) ? (float) $candles[$zone->startIndex]->openTime : 0.0;
            if ($isLong && $zone->isDemand) {
                $consider($zone->bottom, 'ناحیه تقاضا', $originTime);
            }
            if (!$isLong && !$zone->isDemand) {
                $consider($zone->top, 'ناحیه عرضه', $originTime);
            }
        }

        return $best;
    }

    private function nearestFvgMitigation(bool $isLong, float $price, float $atr, array $fvgs): ?float
    {
        $reach = $atr * Config::zoneReachAtr();
        $best = null;
        foreach ($fvgs as $fvg) {
            if ($fvg->filled || $fvg->type !== ($isLong ? FvgType::BULLISH : FvgType::BEARISH)) {
                continue;
            }
            $edge = $isLong ? $fvg->low : $fvg->high;
            if ($isLong ? ($edge > $price || $edge < $price - $reach) : ($edge < $price || $edge > $price + $reach)) {
                continue;
            }
            $pct = FvgMitigation::percent($fvg, $price);
            if ($best === null || $pct > $best) {
                $best = $pct;
            }
        }
        return $best;
    }

    private function liquidityTarget(bool $isLong, float $price, array $levels): ?float
    {
        $best = null;
        foreach ($levels as $level) {
            if ($isLong && (!$level->isHigh || $level->price <= $price)) {
                continue;
            }
            if (!$isLong && ($level->isHigh || $level->price >= $price)) {
                continue;
            }
            if ($best === null
                || ($isLong && $level->price < $best)
                || (!$isLong && $level->price > $best)) {
                $best = $level->price;
            }
        }
        return $best;
    }

    private function nearestStrongZone(float $price, float $atr, array $zones): ?array
    {
        if (empty($zones)) {
            return null;
        }
        $reach = $atr * Config::zoneReachAtr();
        $candidates = array_filter(
            $zones,
            static fn(Zone $z) => $z->type === ZoneType::SUPPORT
                ? ($z->mid() <= $price && $z->mid() >= $price - $reach)
                : ($z->mid() >= $price && $z->mid() <= $price + $reach)
        );
        if (empty($candidates)) {
            return null;
        }
        usort($candidates, static fn(Zone $a, Zone $b) => $b->strength <=> $a->strength);
        $best = reset($candidates);
        return [
            'side' => $best->type === ZoneType::SUPPORT ? 'long' : 'short',
            'touches' => $best->touches,
        ];
    }

    private function volatilityRatio(array $candles, float $recentAtr): float
    {
        $n = count($candles);

        $priorEnd = max(0, $n - 14);
        $priorStart = max(0, $priorEnd - 46);
        $prior = array_slice($candles, $priorStart, $priorEnd - $priorStart);
        $priorAtr = count($prior) >= 2 ? SwingPivots::atr($prior, count($prior)) : 0.0;
        return $priorAtr > 0 ? $recentAtr / $priorAtr : 1.0;
    }

    private static function setupFa(string $setup): string
    {
        return match ($setup) {
            SetupScanner::VWAP => 'بازپس‌گیری VWAP',
            SetupScanner::EMA => 'پولبک به میانگین ۹/۲۱',
            SetupScanner::BREAK_RETEST => 'شکست و پولبک به سطح',
            SetupScanner::SWEEP => 'جاروی نقدینگی و برگشت',
            SetupScanner::DIVERGENCE => 'واگرایی RSI',
            SetupScanner::RANGE_BREAK => 'شکست محدوده باز',
            SetupScanner::BIG_MOVE => 'جاروی نقدینگی و شروع حرکت بزرگ',
            SetupScanner::CHANNEL => 'برگشت از کانال انحراف با تایید RSI',
            default => $setup,
        };
    }

    private static function groupForSetup(string $setup): string
    {
        return match ($setup) {
            SetupScanner::BREAK_RETEST, SetupScanner::RANGE_BREAK => 'structure',
            SetupScanner::SWEEP, SetupScanner::BIG_MOVE => 'liquidity',
            SetupScanner::DIVERGENCE, SetupScanner::CHANNEL => 'momentum',
            SetupScanner::VWAP, SetupScanner::EMA => 'location',
            default => 'structure',
        };
    }

    private static function groupCaps(): array
    {
        return [
            'structure' => Config::confluenceStructureCap(),
            'liquidity' => Config::confluenceLiquidityCap(),
            'location' => Config::confluenceLocationCap(),
            'momentum' => Config::confluenceMomentumCap(),
            'volume' => Config::confluenceVolumeCap(),
            'htf' => Config::confluenceHtfCap(),
        ];
    }
}

final class APlusSetupFilter
{
    public static function evaluate(array $lastVotes, bool $isLong): array
    {
        $dir = $isLong ? 'bullish' : 'bearish';
        $setups = $lastVotes['setups'] ?? [];
        $zone = (string) ($lastVotes['zone'] ?? '');

        $checklist = [
            'htf_trend' => ($lastVotes['htf'] ?? null) === $dir,
            'liquidity_sweep' => ($lastVotes['sweep'] ?? false) === true,
            'choch_bos' => ($lastVotes['structure'] ?? null) !== null && ($lastVotes['structure_dir'] ?? null) === $dir,
            'fresh_ob_fvg' => str_contains($zone, 'اوردر بلاک') || str_contains($zone, 'FVG'),
            'retest' => in_array(SetupScanner::BREAK_RETEST, $setups, true),
            'displacement_volume' => ($lastVotes['displacement'] ?? null) === $dir && ($lastVotes['volume_imbalance'] ?? null) === $dir,
            'room_to_target' => ($lastVotes['liquidity_target'] ?? null) !== null,
        ];
        $count = count(array_filter($checklist));
        $total = count($checklist);
        $required = Config::aPlusMinConfirmations();

        $rangePos = $lastVotes['range_position'] ?? null;
        if (($lastVotes['continuation'] ?? false) && $rangePos !== null && $rangePos > 0.42 && $rangePos < 0.58) {
            return [
                'passed' => false, 'count' => $count, 'total' => $total, 'required' => $required, 'checklist' => $checklist,
                'reason' => 'ورود از وسط محدوده قیمتی',
            ];
        }

        if ($count < $required) {
            return [
                'passed' => false, 'count' => $count, 'total' => $total, 'required' => $required, 'checklist' => $checklist,
                'reason' => sprintf('فیلتر A+: فقط %d از %d تاییدیه مستقل موجود بود (حداقل لازم %d)', $count, $total, $required),
            ];
        }

        return ['passed' => true, 'count' => $count, 'total' => $total, 'required' => $required, 'checklist' => $checklist, 'reason' => null];
    }
}

final class StrategyEngine
{
    private array $strategies = [];

    public function __construct()
    {
        $this->register(new DefaultStructureStrategy());
        $this->register(new StructureBreakStrategy());
        $this->register(new ConfluenceProStrategy());
    }

    public function register(Strategy $strategy): void
    {
        $this->strategies[$strategy->name()] = $strategy;
    }

    public function get(string $name): ?Strategy
    {
        return $this->strategies[$name] ?? null;
    }

    public function names(): array
    {
        return array_keys($this->strategies);
    }
}

final class SymbolClassifier
{
    public const TIER_MAJOR = 'major';
    public const TIER_LARGE = 'large';
    public const TIER_ALT   = 'alt';
    public const TIER_MICRO = 'micro';

    public static function baseAsset(string $symbol, ?string $known = null): string
    {
        if ($known !== null && trim($known) !== '') {
            return strtoupper(trim($known));
        }
        $symbol = strtoupper(trim($symbol));
        foreach (['/', '-', '_'] as $sep) {
            if (str_contains($symbol, $sep)) {
                return explode($sep, $symbol)[0];
            }
        }
        foreach (['USDT', 'USDC', 'FDUSD', 'BUSD', 'TUSD', 'DAI', 'USD', 'BTC', 'ETH'] as $quote) {
            if (str_ends_with($symbol, $quote) && strlen($symbol) > strlen($quote)) {
                return substr($symbol, 0, -strlen($quote));
            }
        }
        return $symbol;
    }

    public static function tier(string $baseAsset, float $volume24h): string
    {
        if (in_array(strtoupper($baseAsset), Config::majorAssets(), true)) {
            return self::TIER_MAJOR;
        }
        return match (true) {
            $volume24h >= 100_000_000 => self::TIER_LARGE,
            $volume24h >= 5_000_000 => self::TIER_ALT,
            default => self::TIER_MICRO,
        };
    }
}

final class LeverageEngine
{
    public static function forSymbol(string $baseAsset, float $volume24h, float $volatilityPct): int
    {
        $tier = SymbolClassifier::tier($baseAsset, $volume24h);
        if ($tier === SymbolClassifier::TIER_MAJOR) {
            return Config::leverageMajor();
        }

        $min = Config::leverageAltMin();
        $max = max($min, Config::leverageAltMax());

        $liquidity = self::normalize(log10(max(1.0, $volume24h)), 6.0, 8.7);

        $stability = 1.0 - self::normalize($volatilityPct, 0.5, 6.0);

        $quality = 0.6 * $liquidity + 0.4 * $stability;
        if ($tier === SymbolClassifier::TIER_MICRO) {
            $quality = min($quality, 0.45);
        }

        return (int) max($min, min($max, round($min + ($max - $min) * $quality)));
    }

    private static function normalize(float $value, float $low, float $high): float
    {
        if ($high <= $low) {
            return 0.0;
        }
        return max(0.0, min(1.0, ($value - $low) / ($high - $low)));
    }
}

final class TradePlanner
{
    public function plan(
        Direction $direction,
        float $entry,
        float $structuralStop,
        float $atr,
        string $baseAsset,
        float $volume24h,
        ?float $structuralTarget = null,
    ): ?array {
        if ($entry <= 0) {
            return null;
        }

        $tier = SymbolClassifier::tier($baseAsset, $volume24h);

        $minStopPct = Config::minStopPercent();
        $distance = max(abs($entry - $structuralStop), $entry * ($minStopPct / 100));
        if ($distance <= 0) {
            return null;
        }
        $stopPct = ($distance / $entry) * 100;

        $maxLeverageForStop = min(
            Config::maxStopLeveragedPercent() / $stopPct,
            Config::leverageLiquidationBuffer() * (100.0 / $stopPct)
        );

        $ceiling = $tier === SymbolClassifier::TIER_MAJOR ? Config::leverageMajor() : Config::leverageAltMax();
        $floor = $tier === SymbolClassifier::TIER_MAJOR ? 1 : Config::leverageAltMin();
        if ($maxLeverageForStop < $floor) {
            return null;
        }

        $volatilityPct = $atr > 0 ? ($atr / $entry) * 100 : 0.0;
        $preferred = LeverageEngine::forSymbol($baseAsset, $volume24h, $volatilityPct);
        $leverage = (int) max($floor, min($ceiling, $preferred, floor($maxLeverageForStop)));

        $tp1Distance = $entry * (Config::tp1LeveragedPercent() / $leverage) / 100;
        $tp2Distance = $entry * (Config::tp2LeveragedPercent() / $leverage) / 100;
        $tp3Distance = $entry * (Config::tp3LeveragedPercent() / $leverage) / 100;
        $tp4Distance = $entry * (Config::tp4LeveragedPercent() / $leverage) / 100;

        if ($structuralTarget !== null) {
            $targetDistance = abs($structuralTarget - $entry);
            if ($targetDistance > 0 && $targetDistance < $tp4Distance) {
                $tp4Distance = $targetDistance;
            }
        }

        if ($tp1Distance <= 0 || $tp2Distance <= $tp1Distance || $tp3Distance <= $tp2Distance || $tp4Distance <= $tp3Distance) {
            return null;
        }

        $sign = $direction === Direction::LONG ? 1 : -1;

        return [
            'entry' => $entry,
            'stop_loss' => $entry - $sign * $distance,
            'tp1' => $entry + $sign * $tp1Distance,
            'tp2' => $entry + $sign * $tp2Distance,
            'tp3' => $entry + $sign * $tp3Distance,
            'tp4' => $entry + $sign * $tp4Distance,
            'leverage' => $leverage,
            'tier' => $tier,
            'risk' => $distance,
            'rr' => round($tp1Distance / $distance, 2),
            'rr_final' => round($tp4Distance / $distance, 2),
            'stop_pct' => $stopPct,
        ];
    }
}

final class MoneyManager
{
    public static function plan(float $entry, float $stopLoss, int $leverage): array
    {
        $balance = Config::accountBalance();
        $riskPct = Config::riskPerTradePercent();
        $riskAmount = $balance * ($riskPct / 100);

        $stopPct = $entry > 0 ? (abs($entry - $stopLoss) / $entry) * 100 : 0.0;
        if ($stopPct <= 0) {
            return [
                'balance' => $balance, 'risk_pct' => $riskPct, 'risk_amount' => $riskAmount,
                'position_size' => 0.0, 'margin' => 0.0, 'qty' => 0.0, 'stop_pct' => 0.0,
            ];
        }

        $positionSize = $riskAmount / ($stopPct / 100);
        $margin = $leverage > 0 ? $positionSize / $leverage : $positionSize;

        if ($margin > $balance) {
            $margin = $balance;
            $positionSize = $margin * max(1, $leverage);
        }

        return [
            'balance' => $balance,
            'risk_pct' => $riskPct,
            'risk_amount' => $riskAmount,
            'position_size' => $positionSize,
            'margin' => $margin,
            'qty' => $entry > 0 ? $positionSize / $entry : 0.0,
            'stop_pct' => $stopPct,
        ];
    }

    public static function money(float $value): string
    {
        if ($value >= 1000) {
            return number_format($value, 0);
        }
        return number_format($value, $value >= 10 ? 1 : 2);
    }
}

final class SignalValidator
{
    public function validate(Signal $signal, MarketSnapshot $snapshot): array
    {
        $reasons = [];

        if ($signal->score < Config::minSignalScore()) {
            $reasons[] = sprintf('امتیاز (%.2f) کمتر از حداقل مجاز (%.2f) است', $signal->score, Config::minSignalScore());
        }

        $minRr = min(Config::minRiskReward(), Config::tp1RiskReward());
        if ($signal->riskReward < $minRr) {
            $reasons[] = sprintf('نسبت ریسک به ریوارد (%.2f) کمتر از حداقل مجاز (%.2f) است', $signal->riskReward, $minRr);
        }
        $targetSequence = array_values(array_filter(
            [$signal->tp1, $signal->tp2, $signal->tp3, $signal->tp4],
            static fn(?float $tp) => $tp !== null
        ));
        for ($i = 1; $i < count($targetSequence); $i++) {
            $ordered = $signal->direction === Direction::LONG
                ? $targetSequence[$i] > $targetSequence[$i - 1]
                : $targetSequence[$i] < $targetSequence[$i - 1];
            if (!$ordered) {
                $reasons[] = sprintf('هدف %d باید بعد از هدف %d قرار بگیرد', $i + 1, $i);
                break;
            }
        }
        if ($signal->leverage < 1) {
            $reasons[] = 'اهرم نامعتبر است';
        }
        if ($snapshot->spreadPct > Config::maxSpreadPercent()) {
            $reasons[] = sprintf('اسپرد بازار (%.3f%%) بیش از حد مجاز است', $snapshot->spreadPct);
        }

        if ($signal->direction === Direction::LONG) {
            if (!($signal->stopLoss < $signal->entry)) {
                $reasons[] = 'حد ضرر برای LONG باید کمتر از قیمت ورود باشد';
            }
            if ($signal->tp1 !== null && !($signal->tp1 > $signal->entry)) {
                $reasons[] = 'هدف اول برای LONG باید بالاتر از قیمت ورود باشد';
            }
        } else {
            if (!($signal->stopLoss > $signal->entry)) {
                $reasons[] = 'حد ضرر برای SHORT باید بیشتر از قیمت ورود باشد';
            }
            if ($signal->tp1 !== null && !($signal->tp1 < $signal->entry)) {
                $reasons[] = 'هدف اول برای SHORT باید پایین‌تر از قیمت ورود باشد';
            }
        }

        return ['valid' => empty($reasons), 'reasons' => $reasons];
    }
}

final class SignalDeduplicator
{
    public function fingerprint(string $exchange, string $symbol, Direction $direction, string $timeframe, string $strategy, float $entry): string
    {
        $bucket = $entry > 0 ? (int) round(log($entry) / log(1.005)) : 0;
        return hash('sha256', implode('|', [$exchange, $symbol, $direction->value, $timeframe, $strategy, $bucket]));
    }

    public function isDuplicate(string $fingerprint, int $cooldownSeconds): bool
    {
        $since = date('Y-m-d H:i:s', time() - $cooldownSeconds);
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM signals WHERE fingerprint = :fp AND created_at >= :since AND status NOT IN ('invalidated','failed')"
        );
        $stmt->execute([':fp' => $fingerprint, ':since' => $since]);
        return ((int) $stmt->fetchColumn()) > 0;
    }
}

final class PriceFormatter
{
    public static function format(float $price): string
    {
        $abs = abs($price);
        $decimals = match (true) {
            $abs >= 1000 => 2,
            $abs >= 100 => 3,
            $abs >= 1 => 4,
            $abs >= 0.01 => 6,
            default => 8,
        };
        $formatted = number_format($price, $decimals, '.', '');
        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}

final class SignalFormatter
{
    public function format(Signal $signal, string $templateText, array $templateEntities): array
    {
        $risk = abs($signal->entry - $signal->stopLoss);
        $riskPct = $signal->entry > 0 ? ($risk / $signal->entry) * 100 : 0.0;
        $money = MoneyManager::plan($signal->entry, $signal->stopLoss, $signal->leverage);

        $placeholders = [
            'symbol' => SignalCardFactory::displaySymbol($signal->symbol),
            'symbol_raw' => $signal->symbol,
            'exchange' => ucfirst($signal->exchange),
            'direction' => $signal->direction->value,
            'direction_fa' => self::directionFa($signal->direction->value),
            'entry' => $this->fmt($signal->entry),

            'entry_mode' => 'مارکت (Market)',
            'margin' => MoneyManager::money($money['margin']) . ' USDT',
            'position_size' => MoneyManager::money($money['position_size']) . ' USDT',
            'risk_amount' => MoneyManager::money($money['risk_amount']) . ' USDT',
            'risk_per_trade' => number_format($money['risk_pct'], 1) . '%',
            'balance' => MoneyManager::money($money['balance']) . ' USDT',
            'sl_loss_amount' => MoneyManager::money($money['risk_amount']) . ' USDT',
            'sl' => $this->fmt($signal->stopLoss),
            'tp1' => $signal->tp1 !== null ? $this->fmt($signal->tp1) : '-',
            'tp2' => $signal->tp2 !== null ? $this->fmt($signal->tp2) : '-',
            'tp3' => $signal->tp3 !== null ? $this->fmt($signal->tp3) : '-',
            'tp4' => $signal->tp4 !== null ? $this->fmt($signal->tp4) : '-',
            'tp1_profit' => $signal->tp1 !== null ? number_format($signal->leveragedPnlPercent($signal->tp1), 1) : '-',
            'tp2_profit' => $signal->tp2 !== null ? number_format($signal->leveragedPnlPercent($signal->tp2), 1) : '-',
            'tp3_profit' => $signal->tp3 !== null ? number_format($signal->leveragedPnlPercent($signal->tp3), 1) : '-',
            'tp4_profit' => $signal->tp4 !== null ? number_format($signal->leveragedPnlPercent($signal->tp4), 1) : '-',
            'sl_loss' => number_format(abs($signal->leveragedPnlPercent($signal->stopLoss)), 1),
            'leverage' => $signal->leverage . 'x',
            'tier' => self::tierFa($signal->tier),
            'risk_pct' => number_format($riskPct, 2),
            'rr' => number_format($signal->riskReward, 2),
            'score' => number_format($signal->score, 1),
            'confidence' => $signal->confidence,
            'confidence_fa' => self::confidenceFa($signal->confidence),
            'timeframe' => $signal->timeframe,
            'strategy' => $signal->strategy,
            'time' => date('Y-m-d H:i'),
            'reasons' => empty($signal->reasons) ? '-' : ('• ' . implode("\n• ", $signal->reasons)),
        ];

        return TelegramEntityUtils::renderTemplate($templateText, $templateEntities, $placeholders);
    }

    public function formatResult(array $row, string $kind, float $exitPrice, string $templateText, array $templateEntities): array
    {
        $stats = self::resultStats($row, $exitPrice);

        $placeholders = [
            'symbol' => SignalCardFactory::displaySymbol((string) $row['symbol']),
            'symbol_raw' => (string) $row['symbol'],
            'exchange' => ucfirst((string) ($row['exchange'] ?? '')),
            'direction' => (string) $row['direction'],
            'direction_fa' => self::directionFa((string) $row['direction']),
            'timeframe' => (string) $row['timeframe'],
            'leverage' => $stats['leverage'] . 'x',
            'entry' => $this->fmt((float) $row['entry_price']),
            'exit' => $this->fmt($exitPrice),
            'sl' => $this->fmt((float) $row['stop_loss']),
            'tp1' => $row['tp1'] !== null ? $this->fmt((float) $row['tp1']) : '-',
            'tp2' => $row['tp2'] !== null ? $this->fmt((float) $row['tp2']) : '-',
            'tp3' => isset($row['tp3']) && $row['tp3'] !== null ? $this->fmt((float) $row['tp3']) : '-',
            'tp4' => isset($row['tp4']) && $row['tp4'] !== null ? $this->fmt((float) $row['tp4']) : '-',
            'pnl' => $stats['pnl_signed'],
            'move' => $stats['move_signed'],
            'result' => $kind,
            'time' => date('Y-m-d H:i'),
        ];

        return TelegramEntityUtils::renderTemplate($templateText, $templateEntities, $placeholders);
    }

    public static function resultStats(array $row, float $exitPrice): array
    {
        $entry = (float) $row['entry_price'];
        $leverage = max(1, (int) round((float) ($row['leverage'] ?? 1)));
        $isLong = (string) $row['direction'] === 'LONG';
        $moveTo = static fn(float $price): float => $entry > 0
            ? (($isLong ? $price - $entry : $entry - $price) / $entry) * 100
            : 0.0;

        $tp1Price = ($row['tp1_hit_price'] ?? null) !== null ? (float) $row['tp1_hit_price'] : null;
        if ($tp1Price !== null) {
            $share = Config::tp1ClosePercent() / 100;
            $move = $share * $moveTo($tp1Price) + (1 - $share) * $moveTo($exitPrice);
        } else {
            $move = $moveTo($exitPrice);
        }
        $pnl = $move * $leverage;

        return [
            'leverage' => $leverage,
            'move' => $move,
            'pnl' => $pnl,
            'move_signed' => self::signed($move, 2),
            'pnl_signed' => self::signed($pnl, 2),
        ];
    }

    public static function signed(float $value, int $decimals = 2): string
    {
        return ($value >= 0 ? '+' : '-') . number_format(abs($value), $decimals);
    }

    public static function directionFa(string $direction): string
    {
        return strtoupper($direction) === 'SHORT' ? 'فروش / SHORT' : 'خرید / LONG';
    }

    public static function tierFa(string $tier): string
    {
        return match ($tier) {
            SymbolClassifier::TIER_MAJOR => 'ارز اصلی',
            SymbolClassifier::TIER_LARGE => 'آلت‌کوین پرحجم',
            SymbolClassifier::TIER_MICRO => 'شت‌کوین / کم‌حجم',
            default => 'آلت‌کوین',
        };
    }

    public static function confidenceFa(string $confidence): string
    {
        return match ($confidence) {
            'high' => 'بالا',
            'medium' => 'متوسط',
            default => 'پایین',
        };
    }

    private function fmt(float $n): string
    {
        return PriceFormatter::format($n);
    }
}

final class VenueListings
{
    private const CACHE_KEY = 'venue_listings';

    private static ?array $memo = null;

    public static function current(bool $forceRefresh = false): array
    {
        if (!$forceRefresh && self::$memo !== null) {
            return self::$memo;
        }

        $venues = Config::tradableVenues();
        if (empty($venues)) {
            return self::$memo = ['assets' => [], 'venues' => [], 'active' => false, 'fetched_at' => 0];
        }

        if (!$forceRefresh) {
            $cached = self::readCache();
            if ($cached !== null) {
                return self::$memo = $cached;
            }
        }

        $assets = [];
        $report = [];
        foreach ($venues as $venue) {
            $url = Config::venueListingUrl($venue);
            $found = self::fetchVenue($url);
            $report[$venue] = [
                'ok' => $found !== null,
                'count' => $found === null ? 0 : count($found),
                'error' => $found === null ? 'unreachable or unrecognised response' : null,
                'url' => $url,
            ];
            foreach ($found ?? [] as $asset) {
                $assets[$asset] = true;
            }
        }

        $result = [
            'assets' => $assets,
            'venues' => $report,
            'active' => !empty($assets),
            'fetched_at' => time(),
        ];
        self::writeCache($result);

        if (!$result['active']) {
            Logger::warning('venues', 'no venue listing could be loaded — tradability filter is off', ['venues' => array_keys($report)]);
        }

        return self::$memo = $result;
    }

    public static function allows(string $baseAsset): bool
    {
        $state = self::current();
        if (!$state['active']) {
            return true;
        }
        return isset($state['assets'][strtoupper($baseAsset)]);
    }

    public static function forget(): void
    {
        self::$memo = null;
        try {
            Database::pdo()->prepare('DELETE FROM bot_settings WHERE setting_key = :k')->execute([':k' => self::CACHE_KEY]);
        } catch (Throwable) {
        }
    }

    private static function fetchVenue(string $url): ?array
    {
        if (trim($url) === '') {
            return null;
        }
        try {
            $res = HttpClient::request('GET', $url, ['Accept' => 'application/json'], null, 1);
        } catch (Throwable $e) {
            Logger::warning('venues', 'venue listing fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
        if ($res['status'] < 200 || $res['status'] >= 300 || !is_array($res['json'])) {
            return null;
        }

        return self::assetsFromJson($res['json']);
    }

    public static function assetsFromJson(array $json): ?array
    {
        $lists = self::instrumentLists($json, true);
        if (empty($lists)) {
            $lists = self::instrumentLists($json, false);
        }

        $assets = [];
        foreach ($lists as $list) {
            foreach ($list as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $asset = self::baseAssetOf($row);
                if ($asset !== null) {
                    $assets[$asset] = true;
                }
            }
        }
        return empty($assets) ? null : array_keys($assets);
    }

    private static function instrumentLists(array $json, bool $futuresOnly = false): array
    {
        $keys = $futuresOnly
            ? ['contracts', 'contractList', 'perpetuals']
            : ['symbols', 'contracts', 'data', 'result', 'list'];

        $lists = [];
        foreach ($keys as $key) {
            $candidate = $json[$key] ?? null;
            if (is_array($candidate) && isset($candidate[0]) && is_array($candidate[0])) {
                $lists[] = $candidate;
            }
            if (is_array($candidate)) {
                foreach (['list', 'symbols', 'contracts'] as $inner) {
                    $nested = $candidate[$inner] ?? null;
                    if (is_array($nested) && isset($nested[0]) && is_array($nested[0])) {
                        $lists[] = $nested;
                    }
                }
            }
        }
        if (!$futuresOnly && isset($json[0]) && is_array($json[0])) {
            $lists[] = $json;
        }
        return $lists;
    }

    private static function baseAssetOf(array $row): ?string
    {
        foreach (['status', 'state', 'contractStatus'] as $key) {
            $status = $row[$key] ?? null;
            if (is_string($status) && $status !== '' && !in_array(strtoupper($status), ['TRADING', 'ENABLED', 'ONLINE', 'NORMAL', 'LIVE', '1'], true)) {
                return null;
            }
        }

        foreach (['baseAsset', 'baseCoin', 'baseCurrency', 'baseCoinName', 'base'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return SymbolClassifier::baseAsset(strtoupper(trim($v)));
            }
        }
        foreach (['symbol', 'symbolName', 'name', 'instId', 'contract'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                return SymbolClassifier::baseAsset(strtoupper(trim($v)));
            }
        }
        return null;
    }

    private static function readCache(): ?array
    {
        try {
            $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
            $stmt->execute([':k' => self::CACHE_KEY]);
            $raw = $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }
        if ($raw === false || $raw === null) {
            return null;
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded) || !isset($decoded['fetched_at'], $decoded['assets'])) {
            return null;
        }
        if ((time() - (int) $decoded['fetched_at']) > Config::venueListingTtlSeconds()) {
            return null;
        }
        return [
            'assets' => is_array($decoded['assets']) ? $decoded['assets'] : [],
            'venues' => is_array($decoded['venues'] ?? null) ? $decoded['venues'] : [],
            'active' => (bool) ($decoded['active'] ?? false),
            'fetched_at' => (int) $decoded['fetched_at'],
        ];
    }

    private static function writeCache(array $state): void
    {
        try {
            Database::pdo()->prepare(
                "INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :now)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at"
            )->execute([':k' => self::CACHE_KEY, ':v' => json_encode($state), ':now' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            Logger::warning('venues', 'could not cache venue listings', ['error' => $e->getMessage()]);
        }
    }
}

final class SignalCardFactory
{
    public static function entry(Signal $signal): ?string
    {
        if (!CardConfig::enabled()) {
            return null;
        }
        return SignalCard::render([
            'symbol' => self::displaySymbol($signal->symbol),
            'direction' => $signal->direction->value,
            'leverage' => $signal->leverage . 'X',
            'entry' => self::fmt($signal->entry),
            'sl' => self::fmt($signal->stopLoss),
            'tp1' => $signal->tp1 !== null ? self::fmt($signal->tp1) : '-',
            'tp2' => $signal->tp2 !== null ? self::fmt($signal->tp2) : '-',
            'tp3' => $signal->tp3 !== null ? self::fmt($signal->tp3) : '-',
            'tp4' => $signal->tp4 !== null ? self::fmt($signal->tp4) : '-',
            'time' => date('Y-m-d H:i') . ' ' . date('T'),
        ]);
    }

    public static function result(array $row, string $kind, float $exitPrice): ?string
    {
        if (!CardConfig::enabled()) {
            return null;
        }
        $stats = SignalFormatter::resultStats($row, $exitPrice);

        return ResultCard::render([
            'kind' => $kind,

            'symbol' => strtoupper((string) $row['symbol']),
            'direction' => (string) $row['direction'],
            'leverage' => $stats['leverage'] . 'X',
            'headline' => $stats['pnl_signed'] . '%',
            'move' => $stats['move_signed'] . '%',
            'entry' => self::fmt((float) $row['entry_price']),
            'exit' => self::fmt($exitPrice),
            'duration' => self::holdTime($row),
            'time' => date('Y-m-d H:i') . ' ' . date('T'),
        ]);
    }

    private static function holdTime(array $row): string
    {
        $opened = strtotime((string) ($row['created_at'] ?? ''));
        if ($opened === false) {
            return '00H 00M';
        }
        $closed = strtotime((string) ($row['resolved_at'] ?? '')) ?: time();
        $seconds = max(0, $closed - $opened);
        return sprintf('%02dH %02dM', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    public static function displaySymbol(string $symbol, ?string $base = null): string
    {
        $symbol = strtoupper(trim($symbol));
        foreach (['-', '_'] as $sep) {
            $symbol = str_replace($sep, '/', $symbol);
        }
        if (str_contains($symbol, '/')) {
            return $symbol;
        }
        $baseAsset = SymbolClassifier::baseAsset($symbol, $base);
        if ($baseAsset !== $symbol && str_starts_with($symbol, $baseAsset)) {
            return $baseAsset . '/' . substr($symbol, strlen($baseAsset));
        }
        return $symbol;
    }

    private static function fmt(float $n): string
    {
        return PriceFormatter::format($n);
    }
}

final class SignalRepository
{
    public function save(Signal $signal): int
    {
        $exchangeId = ExchangeRepository::idForName($signal->exchange);
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO signals (uuid, exchange_id, symbol, direction, timeframe, entry_price, stop_loss, tp1, tp2, tp3, tp4,
                risk_reward, score, confidence, strategy, reasons, fingerprint, status, leverage, tier, stage, active_stop, created_at, updated_at)
             VALUES (:uuid, :eid, :symbol, :dir, :tf, :entry, :sl, :tp1, :tp2, :tp3, :tp4, :rr, :score, :conf, :strategy, :reasons, :fp, :status, :lev, :tier, :stage, :astop, :now1, :now2)'
        );
        $stmt->execute([
            ':uuid' => $signal->uuid, ':eid' => $exchangeId, ':symbol' => $signal->symbol, ':dir' => $signal->direction->value,
            ':tf' => $signal->timeframe, ':entry' => $signal->entry, ':sl' => $signal->stopLoss,
            ':tp1' => $signal->tp1, ':tp2' => $signal->tp2, ':tp3' => $signal->tp3, ':tp4' => $signal->tp4, ':rr' => $signal->riskReward,
            ':score' => $signal->score, ':conf' => $signal->confidence, ':strategy' => $signal->strategy,
            ':reasons' => json_encode($signal->reasons, JSON_UNESCAPED_UNICODE), ':fp' => $signal->fingerprint,
            ':status' => $signal->status, ':lev' => $signal->leverage, ':tier' => $signal->tier,
            ':stage' => 'open', ':astop' => $signal->activeStop ?? $signal->stopLoss,
            ':now1' => $now, ':now2' => $now,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare('UPDATE signals SET status = :status, updated_at = :now WHERE id = :id');
        $stmt->execute([':status' => $status, ':now' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function countToday(): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM signals WHERE created_at >= :start AND created_at < :end');
        $stmt->execute([':start' => date('Y-m-d 00:00:00'), ':end' => date('Y-m-d 00:00:00', strtotime('+1 day'))]);
        return (int) $stmt->fetchColumn();
    }

    public function countActive(): int
    {
        $stmt = Database::pdo()->query("SELECT COUNT(*) FROM signals WHERE status IN ('queued','sent')");
        return (int) $stmt->fetchColumn();
    }

    public function recent(int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM signals ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function hasOpenPosition(string $exchange, string $symbol): bool
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM signals
             WHERE exchange_id = :eid AND symbol = :symbol
               AND status IN ('sent','queued') AND resolved_at IS NULL"
        );
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol]);
        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function hasOpenPositionForBaseAsset(string $baseAsset): bool
    {
        $stmt = Database::pdo()->query(
            "SELECT symbol FROM signals WHERE status IN ('sent','queued') AND resolved_at IS NULL"
        );
        foreach ($stmt->fetchAll() as $row) {
            if (SymbolClassifier::baseAsset((string) $row['symbol']) === $baseAsset) {
                return true;
            }
        }
        return false;
    }

    public function recentLossOnBaseAsset(string $baseAsset, int $withinSeconds): bool
    {
        if ($withinSeconds <= 0) {
            return false;
        }
        $since = date('Y-m-d H:i:s', time() - $withinSeconds);
        $stmt = Database::pdo()->prepare(
            "SELECT symbol FROM signals WHERE result = 'sl' AND resolved_at IS NOT NULL AND resolved_at >= :since"
        );
        $stmt->execute([':since' => $since]);
        foreach ($stmt->fetchAll() as $row) {
            if (SymbolClassifier::baseAsset((string) $row['symbol']) === $baseAsset) {
                return true;
            }
        }
        return false;
    }

    public function openPositionSymbols(): array
    {
        $sql = "SELECT DISTINCT e.name AS exchange, s.symbol
                FROM signals s
                JOIN exchanges e ON e.id = s.exchange_id
                WHERE s.status IN ('sent','queued') AND s.resolved_at IS NULL";
        return Database::pdo()->query($sql)->fetchAll();
    }

    public function openPositions(): array
    {
        $sql = "SELECT s.*, e.name AS exchange
                FROM signals s
                JOIN exchanges e ON e.id = s.exchange_id
                WHERE s.status IN ('sent','queued') AND s.resolved_at IS NULL";
        return Database::pdo()->query($sql)->fetchAll();
    }

    public function resolve(int $id, string $outcome, float $price): void
    {
        Database::pdo()->prepare(
            'UPDATE signals SET outcome = :outcome, resolved_at = :now, resolved_price = :price, updated_at = :now WHERE id = :id'
        )->execute([':outcome' => $outcome, ':now' => date('Y-m-d H:i:s'), ':price' => $price, ':id' => $id]);
    }

    public function markRiskFree(int $id, float $price, float $entry): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE signals
                SET stage = 'risk_free', active_stop = :entry, tp1_hit_at = :now, tp1_hit_price = :price,
                    peak_price = :price, updated_at = :now
              WHERE id = :id"
        )->execute([':entry' => $entry, ':now' => $now, ':price' => $price, ':id' => $id]);
    }

    public function markTrailingTp2(int $id, float $price, float $tp1Price): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE signals
                SET stage = 'trailing_tp2', active_stop = :tp1price, tp2_hit_at = :now, tp2_hit_price = :price,
                    peak_price = :price, updated_at = :now
              WHERE id = :id"
        )->execute([':tp1price' => $tp1Price, ':now' => $now, ':price' => $price, ':id' => $id]);
    }

    public function markTrailingTp3(int $id, float $price, float $tp2Price): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE signals
                SET stage = 'trailing_tp3', active_stop = :tp2price, tp3_hit_at = :now, tp3_hit_price = :price,
                    peak_price = :price, updated_at = :now
              WHERE id = :id"
        )->execute([':tp2price' => $tp2Price, ':now' => $now, ':price' => $price, ':id' => $id]);
    }

    public function updatePeak(int $id, float $peak): void
    {
        Database::pdo()->prepare(
            'UPDATE signals SET peak_price = :peak, updated_at = :now WHERE id = :id'
        )->execute([':peak' => $peak, ':now' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function markAdvisorySent(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE signals SET advisory_sent_at = :now, updated_at = :now WHERE id = :id'
        )->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function markStallAdvisorySent(int $id): void
    {
        Database::pdo()->prepare(
            'UPDATE signals SET stall_advisory_sent_at = :now, updated_at = :now WHERE id = :id'
        )->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
    }

    public function close(int $id, string $result, float $price): void
    {
        $legacyOutcome = match ($result) {
            'tp1', 'tp2', 'tp3', 'tp4', 'trail' => 'tp1',
            'sl' => 'sl',
            default => null,
        };
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE signals
                SET stage = 'closed', result = :result, outcome = :outcome,
                    resolved_at = :now, resolved_price = :price, updated_at = :now
              WHERE id = :id"
        )->execute([':result' => $result, ':outcome' => $legacyOutcome, ':now' => $now, ':price' => $price, ':id' => $id]);
    }

    public function countOpen(): int
    {
        $stmt = Database::pdo()->query(
            "SELECT COUNT(*) FROM signals WHERE status IN ('sent','queued') AND resolved_at IS NULL"
        );
        return (int) $stmt->fetchColumn();
    }

    public function lastPublishedAt(): ?int
    {
        $v = Database::pdo()->query(
            "SELECT MAX(created_at) FROM signals WHERE status IN ('sent','queued')"
        )->fetchColumn();
        if ($v === false || $v === null) {
            return null;
        }
        $ts = strtotime((string) $v);
        return $ts === false ? null : $ts;
    }

    public function todayTally(): array
    {
        $midnight = date('Y-m-d 00:00:00');

        $lost = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM signals WHERE result = 'sl' AND resolved_at IS NOT NULL AND resolved_at >= :m"
        );
        $lost->execute([':m' => $midnight]);

        $sent = Database::pdo()->prepare(
            "SELECT COUNT(*) FROM signals WHERE status IN ('sent','queued') AND created_at >= :m"
        );
        $sent->execute([':m' => $midnight]);

        return ['losses' => (int) $lost->fetchColumn(), 'published' => (int) $sent->fetchColumn()];
    }

    public function recentClosed(int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM signals WHERE resolved_at IS NOT NULL ORDER BY resolved_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function performance(): array
    {
        $rows = Database::pdo()->query(
            "SELECT result, COUNT(*) AS n FROM signals WHERE resolved_at IS NOT NULL GROUP BY result"
        )->fetchAll();

        $counts = ['tp1' => 0, 'tp2' => 0, 'tp3' => 0, 'tp4' => 0, 'trail' => 0, 'sl' => 0, 'be' => 0];
        foreach ($rows as $row) {
            $key = (string) ($row['result'] ?? '');
            if (isset($counts[$key])) {
                $counts[$key] = (int) $row['n'];
            }
        }
        $timeouts = Database::pdo()->query(
            "SELECT direction, entry_price, resolved_price FROM signals WHERE resolved_at IS NOT NULL AND result = 'timeout'"
        )->fetchAll();
        foreach ($timeouts as $t) {
            $entry = (float) $t['entry_price'];
            $exit = (float) $t['resolved_price'];
            $gain = (string) $t['direction'] === 'LONG' ? $exit - $entry : $entry - $exit;
            $counts[$gain < 0 ? 'sl' : 'be']++;
        }
        $wins = $counts['tp1'] + $counts['tp2'] + $counts['tp3'] + $counts['tp4'] + $counts['trail'];
        $total = $wins + $counts['sl'] + $counts['be'];
        $decided = $wins + $counts['sl'];

        return [
            'total' => $total,
            'wins' => $wins,
            'losses' => $counts['sl'],
            'breakeven' => $counts['be'],
            'win_rate' => $decided > 0 ? round($wins / $decided * 100, 1) : 0.0,
        ];
    }
}

final class ZoneRepository
{
    public function replaceForSymbol(string $exchange, string $symbol, string $timeframe, array $zones): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM zones WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf')
            ->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);

        if (empty($zones)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare(
            'INSERT INTO zones (exchange_id, symbol, zone_type, high_price, low_price, timeframe, strength, volume, touches, source, created_at, updated_at)
             VALUES (:eid, :symbol, :type, :high, :low, :tf, :strength, :volume, :touches, :source, :now1, :now2)'
        );
        foreach ($zones as $zone) {
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $symbol, ':type' => $zone->type->value, ':high' => $zone->high,
                ':low' => $zone->low, ':tf' => $timeframe, ':strength' => $zone->strength, ':volume' => $zone->volume,
                ':touches' => $zone->touches, ':source' => $zone->source, ':now1' => $now, ':now2' => $now,
            ]);
        }
    }

    public function forSymbol(string $exchange, string $symbol, string $timeframe): array
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return [];
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM zones WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf');
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);
        return array_map(static fn($r) => new Zone(
            ZoneType::from($r['zone_type']), (float) $r['high_price'], (float) $r['low_price'], $r['timeframe'],
            (float) $r['strength'], (float) $r['volume'], (int) $r['touches'], $r['source']
        ), $stmt->fetchAll());
    }
}

final class OrderBlockRepository
{
    public function replaceForSymbol(string $exchange, string $symbol, string $timeframe, array $blocks): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM order_blocks WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf')
            ->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);

        if (empty($blocks)) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO order_blocks (exchange_id, symbol, ob_type, high_price, low_price, timeframe, strength, volume, mitigated, created_at)
             VALUES (:eid, :symbol, :type, :high, :low, :tf, :strength, :volume, :mit, :created)'
        );
        foreach ($blocks as $ob) {
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $symbol, ':type' => $ob->type->value, ':high' => $ob->high,
                ':low' => $ob->low, ':tf' => $timeframe, ':strength' => $ob->strength, ':volume' => $ob->volume,
                ':mit' => $ob->mitigated ? 1 : 0, ':created' => date('Y-m-d H:i:s', (int) ($ob->createdAt / 1000) ?: time()),
            ]);
        }
    }

    public function forSymbol(string $exchange, string $symbol, string $timeframe): array
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return [];
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM order_blocks WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf');
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);
        return array_map(static fn($r) => new OrderBlock(
            OrderBlockType::from($r['ob_type']), (float) $r['high_price'], (float) $r['low_price'], $r['timeframe'],
            (float) $r['strength'], (float) $r['volume'], (bool) $r['mitigated'], strtotime($r['created_at']) * 1000
        ), $stmt->fetchAll());
    }
}

final class FvgRepository
{
    public function replaceForSymbol(string $exchange, string $symbol, string $timeframe, array $fvgs): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM fvgs WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf')
            ->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);

        if (empty($fvgs)) {
            return;
        }
        $stmt = $pdo->prepare(
            'INSERT INTO fvgs (exchange_id, symbol, fvg_type, high_price, low_price, midpoint, timeframe, size, filled, created_at)
             VALUES (:eid, :symbol, :type, :high, :low, :mid, :tf, :size, :filled, :created)'
        );
        foreach ($fvgs as $fvg) {
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $symbol, ':type' => $fvg->type->value, ':high' => $fvg->high,
                ':low' => $fvg->low, ':mid' => $fvg->midpoint, ':tf' => $timeframe, ':size' => $fvg->size,
                ':filled' => $fvg->filled ? 1 : 0, ':created' => date('Y-m-d H:i:s', (int) ($fvg->createdAt / 1000) ?: time()),
            ]);
        }
    }

    public function forSymbol(string $exchange, string $symbol, string $timeframe): array
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return [];
        }
        $stmt = Database::pdo()->prepare('SELECT * FROM fvgs WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf');
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);
        return array_map(static fn($r) => new Fvg(
            FvgType::from($r['fvg_type']), (float) $r['high_price'], (float) $r['low_price'], (float) $r['midpoint'],
            $r['timeframe'], (float) $r['size'], (bool) $r['filled'], strtotime($r['created_at']) * 1000
        ), $stmt->fetchAll());
    }
}

final class SymbolRepository
{
    public function upsertRanked(string $exchange, array $rows): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE symbols SET is_active = 0 WHERE exchange_id = :eid')->execute([':eid' => $exchangeId]);

        $stmt = $pdo->prepare(
            'INSERT INTO symbols (exchange_id, symbol, base_asset, quote_asset, volume_24h, liquidity_score, spread_pct, volatility, change_24h, bucket, rank_position, is_active, last_scanned_at)
             VALUES (:eid, :symbol, :base, :quote, :vol, :liq, :spread, :volat, :chg, :bucket, :rank, 1, :now)
             ON CONFLICT(exchange_id, symbol) DO UPDATE SET
                base_asset = excluded.base_asset, quote_asset = excluded.quote_asset, volume_24h = excluded.volume_24h,
                liquidity_score = excluded.liquidity_score, spread_pct = excluded.spread_pct, volatility = excluded.volatility,
                change_24h = excluded.change_24h, bucket = excluded.bucket,
                rank_position = excluded.rank_position, is_active = 1, last_scanned_at = excluded.last_scanned_at'
        );
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $row['symbol'], ':base' => $row['base'], ':quote' => $row['quote'],
                ':vol' => $row['volume'], ':liq' => $row['liquidity'], ':spread' => $row['spread'], ':volat' => $row['volatility'],
                ':chg' => $row['change'] ?? 0.0, ':bucket' => $row['bucket'] ?? 'liquid',
                ':rank' => $row['rank'], ':now' => $now,
            ]);
        }
    }

    public function listActive(?string $exchange = null, ?int $limit = null): array
    {
        $sql = 'SELECT e.name AS exchange, s.symbol, s.base_asset, s.quote_asset, s.volume_24h, s.volatility,
                       s.change_24h, s.bucket, s.spread_pct, s.rank_position
                FROM symbols s JOIN exchanges e ON e.id = s.exchange_id WHERE s.is_active = 1';
        $params = [];
        if ($exchange !== null) {
            $sql .= ' AND e.name = :ex';
            $params[':ex'] = $exchange;
        }
        $sql .= ' ORDER BY s.rank_position ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countActive(): int
    {
        $stmt = Database::pdo()->query('SELECT COUNT(*) FROM symbols WHERE is_active = 1');
        return (int) $stmt->fetchColumn();
    }

    public function universe(?int $limit = null): array
    {
        $limit = ($limit === null || $limit <= 0) ? null : $limit;

        $rows = $this->listActive(null, $limit === null ? null : $limit * 4);
        $rows = self::preferPrimaryExchange($rows);
        $majors = Config::majorAssets();

        $pinned = [];
        $rest = [];
        foreach ($rows as $row) {
            if (in_array(strtoupper((string) ($row['base_asset'] ?? '')), $majors, true)) {
                $pinned[] = $row;
            } else {
                $rest[] = $row;
            }
        }
        $merged = array_merge($pinned, $rest);
        return $limit === null ? $merged : array_slice($merged, 0, $limit);
    }

    private static function preferPrimaryExchange(array $rows): array
    {
        $primary = Config::primaryExchange();
        $best = [];
        foreach ($rows as $row) {
            $base = strtoupper((string) ($row['base_asset'] ?? ''));
            if ($base === '') {
                $base = SymbolClassifier::baseAsset((string) $row['symbol']);
            }
            $key = $base . '/' . strtoupper((string) ($row['quote_asset'] ?? ''));
            if (!isset($best[$key])) {
                $best[$key] = $row;
                continue;
            }

            if (strtolower((string) $row['exchange']) === $primary
                && strtolower((string) $best[$key]['exchange']) !== $primary) {
                $best[$key] = $row;
            }
        }
        return array_values($best);
    }
}

final class ScannerRunRepository
{
    public function start(?string $exchange): int
    {
        $exchangeId = $exchange !== null ? ExchangeRepository::idForName($exchange) : null;
        $stmt = Database::pdo()->prepare('INSERT INTO scanner_runs (exchange_id, started_at, status) VALUES (:eid, :now, :status)');
        $stmt->execute([':eid' => $exchangeId, ':now' => date('Y-m-d H:i:s'), ':status' => 'running']);
        return (int) Database::pdo()->lastInsertId();
    }

    public function finish(int $id, int $scanned, int $selected, string $status = 'completed', ?string $error = null): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE scanner_runs SET finished_at = :now, symbols_scanned = :scanned, symbols_selected = :selected, status = :status, error = :error WHERE id = :id'
        );
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':scanned' => $scanned, ':selected' => $selected, ':status' => $status, ':error' => $error, ':id' => $id]);
    }

    public function lastRunAt(): ?string
    {
        $stmt = Database::pdo()->query('SELECT finished_at FROM scanner_runs WHERE finished_at IS NOT NULL ORDER BY id DESC LIMIT 1');
        $v = $stmt->fetchColumn();
        return $v === false ? null : (string) $v;
    }
}

final class MarketScanner
{
    private SymbolRepository $symbolRepo;
    private ScannerRunRepository $runRepo;

    public function __construct()
    {
        $this->symbolRepo = new SymbolRepository();
        $this->runRepo = new ScannerRunRepository();
    }

    public function scan(ExchangeManager $exchangeManager): int
    {
        $totalScanned = 0;
        $totalSelected = 0;

        foreach ($exchangeManager->adapters() as $name => $adapter) {
            $runId = $this->runRepo->start($name);
            try {
                $result = $exchangeManager->withIsolation($name, function (ExchangeAdapter $adapter) {
                    $symbols = $adapter->fetchExchangeSymbols();
                    $tickers = $adapter->fetchTicker24h();
                    return [$symbols, $tickers];
                });

                if ($result === null) {
                    $this->runRepo->finish($runId, 0, 0, 'skipped', 'exchange unavailable');
                    continue;
                }

                [$symbols, $tickers] = $result;
                ['candidates' => $ranked] = $this->rank($symbols, $tickers);
                $totalScanned += count($symbols);
                $selected = array_slice($ranked, 0, Config::scannerTopN());
                $totalSelected += count($selected);

                $this->symbolRepo->upsertRanked($name, $selected);
                $this->runRepo->finish($runId, count($symbols), count($selected));
            } catch (Throwable $e) {
                Logger::error('scanner', "scan failed for $name", ['error' => $e->getMessage()]);
                $this->runRepo->finish($runId, 0, 0, 'failed', $e->getMessage());
            }
        }

        return $totalSelected;
    }

    private function rank(array $symbols, array $tickers): array
    {
        $allowedQuotes = array_map('strtoupper', Config::allowedQuoteAssets());
        $blockedQuotes = Config::blockedQuoteAssets();
        $minVolume = Config::minVolumeUsdt();
        $maxSpread = Config::maxSpreadPercent();
        $includeStable = Config::includeStablecoinPairs();
        $stableAssets = ['USDT', 'USDC', 'BUSD', 'TUSD', 'DAI', 'FDUSD'];

        $funnel = [
            'total' => count($symbols), 'quote_ok' => 0, 'stable_ok' => 0,
            'tradable_ok' => 0, 'has_ticker' => 0, 'volume_ok' => 0, 'spread_ok' => 0,
        ];

        $candidates = [];
        foreach ($symbols as $s) {
            $quote = strtoupper($s['quote']);
            $base = strtoupper($s['base']);
            if (in_array($quote, $blockedQuotes, true)) {
                continue;
            }
            if (!empty($allowedQuotes) && !in_array($quote, $allowedQuotes, true)) {
                continue;
            }
            $funnel['quote_ok']++;
            if (!$includeStable && in_array($base, $stableAssets, true)) {
                continue;
            }
            $funnel['stable_ok']++;

            if (!VenueListings::allows($base)) {
                continue;
            }
            $funnel['tradable_ok']++;
            $ticker = $tickers[$s['symbol']] ?? null;
            if ($ticker === null) {
                continue;
            }
            $funnel['has_ticker']++;
            if ($ticker['volume'] < $minVolume) {
                continue;
            }
            $funnel['volume_ok']++;
            $price = $ticker['lastPrice'];
            $spread = ($ticker['bid'] > 0 && $ticker['ask'] > 0 && $price > 0)
                ? (($ticker['ask'] - $ticker['bid']) / $price) * 100
                : 0.0;
            if ($spread > $maxSpread) {
                continue;
            }
            $funnel['spread_ok']++;
            $change = (float) $ticker['priceChangePercent'];
            $liquidity = $spread > 0 ? $ticker['volume'] / $spread : $ticker['volume'];

            $candidates[] = [
                'symbol' => $s['symbol'], 'base' => $s['base'], 'quote' => $s['quote'],
                'volume' => $ticker['volume'], 'liquidity' => $liquidity, 'spread' => $spread,
                'volatility' => abs($change), 'change' => $change, 'bucket' => 'liquid', 'rank' => 0,
            ];
        }

        $candidates = self::bucketRank($candidates, Config::scannerTopN());

        foreach ($candidates as $i => &$c) {
            $c['rank'] = $i + 1;
        }
        unset($c);

        return ['candidates' => $candidates, 'funnel' => $funnel];
    }

    private static function bucketRank(array $candidates, int $topN): array
    {
        if (empty($candidates)) {
            return [];
        }

        $topN = $topN > 0 ? $topN : count($candidates);
        $minMove = Config::scannerMinMovePercent();

        $gainerSlots = max(1, (int) round($topN * Config::scannerGainerShare() / 100));
        $loserSlots = max(1, (int) round($topN * Config::scannerLoserShare() / 100));

        $byGain = $candidates;
        usort($byGain, static fn($a, $b) => $b['change'] <=> $a['change']);
        $gainers = array_slice(
            array_values(array_filter($byGain, static fn($c) => $c['change'] >= $minMove)),
            0,
            $gainerSlots
        );

        $byLoss = $candidates;
        usort($byLoss, static fn($a, $b) => $a['change'] <=> $b['change']);
        $losers = array_slice(
            array_values(array_filter($byLoss, static fn($c) => $c['change'] <= -$minMove)),
            0,
            $loserSlots
        );

        $liquid = $candidates;
        usort($liquid, static function ($a, $b) {
            $scoreA = ($a['volume'] * 0.7) + ($a['liquidity'] * 0.3);
            $scoreB = ($b['volume'] * 0.7) + ($b['liquidity'] * 0.3);
            return $scoreB <=> $scoreA;
        });

        foreach ($gainers as &$c) {
            $c['bucket'] = 'gainer';
        }
        unset($c);
        foreach ($losers as &$c) {
            $c['bucket'] = 'loser';
        }
        unset($c);

        $out = [];
        $seen = [];
        $lists = [$gainers, $losers, $liquid];
        $cursor = [0, 0, 0];
        while (count($out) < $topN) {
            $addedThisRound = false;
            foreach ($lists as $i => $list) {
                while ($cursor[$i] < count($list)) {
                    $row = $list[$cursor[$i]++];
                    if (isset($seen[$row['symbol']])) {
                        continue;
                    }
                    $seen[$row['symbol']] = true;
                    $out[] = $row;
                    $addedThisRound = true;
                    break;
                }
                if (count($out) >= $topN) {
                    break;
                }
            }
            if (!$addedThisRound) {
                break;
            }
        }

        return $out;
    }

    public function diagnoseExchange(string $exchangeName, ExchangeAdapter $adapter): array
    {
        try {
            $symbols = $adapter->fetchExchangeSymbols();
            $tickers = $adapter->fetchTicker24h();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
        if (empty($symbols)) {
            return ['ok' => false, 'error' => 'fetchExchangeSymbols returned 0 symbols'];
        }
        ['candidates' => $candidates, 'funnel' => $funnel] = $this->rank($symbols, $tickers);
        $sample = array_map(
            static fn($s) => ['symbol' => $s['symbol'], 'base' => $s['base'], 'quote' => $s['quote']],
            array_slice($symbols, 0, 3)
        );
        return ['ok' => true, 'funnel' => $funnel, 'sample' => $sample, 'ticker_count' => count($tickers)];
    }
}

final class SignalGenerator
{
    private SupportResistanceEngine $srEngine;
    private OrderBlockEngine $obEngine;
    private FvgEngine $fvgEngine;
    private ConfluenceEngine $confluenceEngine;
    private StrategyEngine $strategyEngine;
    private SignalValidator $validator;
    private SignalDeduplicator $deduplicator;
    private TradePlanner $planner;
    private ZoneRepository $zoneRepo;
    private OrderBlockRepository $obRepo;
    private FvgRepository $fvgRepo;
    private SignalRepository $signalRepo;

    private ?array $bestObservation = null;

    public function resetObservations(): void
    {
        $this->bestObservation = null;
    }

    public function bestObservation(): ?array
    {
        return $this->bestObservation;
    }

    private function observe(MarketSnapshot $snapshot, string $timeframe, float $score, string $bias, string $reason): void
    {
        if ($this->bestObservation !== null && $this->bestObservation['score'] >= $score) {
            return;
        }
        $this->bestObservation = [
            'symbol' => $snapshot->symbol,
            'timeframe' => $timeframe,
            'score' => round($score, 1),
            'bias' => $bias,
            'reason' => $reason,
        ];
    }

    public function __construct(private IndicatorEngine $indicatorEngine)
    {
        $this->srEngine = new SupportResistanceEngine();
        $this->obEngine = new OrderBlockEngine();
        $this->fvgEngine = new FvgEngine();
        $this->confluenceEngine = new ConfluenceEngine();
        $this->strategyEngine = new StrategyEngine();
        $this->validator = new SignalValidator();
        $this->deduplicator = new SignalDeduplicator();
        $this->planner = new TradePlanner();
        $this->zoneRepo = new ZoneRepository();
        $this->obRepo = new OrderBlockRepository();
        $this->fvgRepo = new FvgRepository();
        $this->signalRepo = new SignalRepository();
    }

    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $meta = [],
        ?string $strategyName = null,
    ): ?Signal {
        if (Config::isNewsBlackoutActive()) {
            return null;
        }

        $candles = $snapshot->candlesFor($timeframe);
        if (count($candles) < 30) {
            $this->observe($snapshot, $timeframe, 0.0, 'neutral', sprintf('کندل کافی نیست (%d از ۳۰)', count($candles)));
            return null;
        }

        if ($this->signalRepo->hasOpenPosition($snapshot->exchange, $snapshot->symbol)) {
            return null;
        }

        $baseAsset = SymbolClassifier::baseAsset($snapshot->symbol, $meta['base_asset'] ?? null);
        if ($this->signalRepo->hasOpenPositionForBaseAsset($baseAsset)) {
            return null;
        }

        if ($this->signalRepo->recentLossOnBaseAsset($baseAsset, Config::symbolLossCooldownSeconds())) {
            return null;
        }

        try {
            $zones = $this->srEngine->detect($candles, $timeframe);
            $orderBlocks = $this->obEngine->detect($candles, $timeframe);
            $fvgs = $this->fvgEngine->detect($candles, $timeframe);
            $trend = SwingPivots::structuralTrend($candles);
            $indicatorResults = $this->indicatorEngine->runAll($candles);

            if (Config::persistStructure()) {
                $this->zoneRepo->replaceForSymbol($snapshot->exchange, $snapshot->symbol, $timeframe, $zones);
                $this->obRepo->replaceForSymbol($snapshot->exchange, $snapshot->symbol, $timeframe, $orderBlocks);
                $this->fvgRepo->replaceForSymbol($snapshot->exchange, $snapshot->symbol, $timeframe, $fvgs);
            }

            $confluence = $this->confluenceEngine->score($snapshot, $timeframe, $zones, $orderBlocks, $fvgs, $indicatorResults, $trend);

            $strategy = $this->strategyEngine->get($strategyName ?? Config::strategyName());
            if ($strategy === null) {
                return null;
            }
            $setup = $strategy->evaluate($snapshot, $timeframe, $zones, $orderBlocks, $fvgs, $confluence);
            if ($setup === null) {
                $this->observe(
                    $snapshot,
                    $timeframe,
                    $confluence['score'],
                    $confluence['bias'],
                    $strategy->lastRejection()
                        ?? ($confluence['bias'] === 'neutral' ? 'بازار جهت مشخصی ندارد' : 'ساختار قیمت پلن معامله نداد')
                );
                return null;
            }

            if ($strategy instanceof ConfluenceProStrategy && Config::requireAPlusSetup()) {
                $aplus = APlusSetupFilter::evaluate($strategy->lastVotes(), $setup['direction'] === Direction::LONG);
                if (!$aplus['passed']) {
                    $this->observe($snapshot, $timeframe, $confluence['score'], $confluence['bias'], $aplus['reason'] ?? 'فیلتر A+ رد کرد');
                    return null;
                }
                if (isset($setup['confluence_reasons'])) {
                    $setup['confluence_reasons'][] = sprintf('فیلتر A+ Setup: %d از %d تاییدیه مستقل تایید شد', $aplus['count'], $aplus['total']);
                }
            }

            $plan = $this->planner->plan(
                $setup['direction'],
                (float) $setup['entry'],
                (float) $setup['stop_loss'],
                SwingPivots::atr($candles),
                SymbolClassifier::baseAsset($snapshot->symbol, $meta['base_asset'] ?? null),
                (float) ($meta['volume_24h'] ?? 0.0),
                isset($setup['structural_target']) ? (float) $setup['structural_target'] : null,
            );
            if ($plan === null) {
                $this->observe($snapshot, $timeframe, $confluence['score'], $confluence['bias'], 'اهرم جایی برای حد ضرر نگذاشت');
                return null;
            }

            $fingerprint = $this->deduplicator->fingerprint(
                $snapshot->exchange,
                $snapshot->symbol,
                $setup['direction'],
                $timeframe,
                $strategy->name(),
                $plan['entry']
            );

            $score = isset($setup['confluence_score'])
                ? (float) $setup['confluence_score']
                : $confluence['score'];

            if ($this->deduplicator->isDuplicate($fingerprint, Config::cooldownSeconds())) {
                $this->observe($snapshot, $timeframe, $score, $confluence['bias'], 'تکراری است (Cooldown)');
                return null;
            }

            if (isset($setup['confluence_score'])) {
                $scoreFloor = Config::minConfluenceScore();
                $confidence = $score >= $scoreFloor * 1.20 ? 'high' : ($score >= $scoreFloor * 1.08 ? 'medium' : 'low');
            } else {
                $scoreFloor = Config::minSignalScore();
                $confidence = $score >= $scoreFloor * 1.5 ? 'high' : ($score >= $scoreFloor * 1.2 ? 'medium' : 'low');
            }

            $signal = new Signal(
                uuid: self::uuid4(),
                exchange: $snapshot->exchange,
                symbol: $snapshot->symbol,
                direction: $setup['direction'],
                timeframe: $timeframe,
                entry: $plan['entry'],
                stopLoss: $plan['stop_loss'],
                tp1: $plan['tp1'],
                tp2: $plan['tp2'],
                tp3: $plan['tp3'],
                tp4: $plan['tp4'],
                riskReward: $plan['rr'],
                score: $score,
                confidence: $confidence,
                strategy: $strategy->name(),
                reasons: self::setupReasons($setup, $confluence['reasons']),
                fingerprint: $fingerprint,
                status: 'pending',
                leverage: $plan['leverage'],
                tier: $plan['tier'],
            );

            $validation = $this->validator->validate($signal, $snapshot);
            if (!$validation['valid']) {
                Logger::debug('signal', 'signal rejected by validator', ['symbol' => $snapshot->symbol, 'reasons' => $validation['reasons']]);
                $this->observe($snapshot, $timeframe, $signal->score, $confluence['bias'], $validation['reasons'][0] ?? 'رد شد');
                return null;
            }

            $this->observe($snapshot, $timeframe, $signal->score, $confluence['bias'], 'قابل انتشار');
            return $signal;
        } catch (Throwable $e) {
            Logger::error('signal', 'signal generation failed', ['symbol' => $snapshot->symbol, 'timeframe' => $timeframe, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private static function setupReasons(array $setup, array $reasons): array
    {
        if (!empty($setup['confluence_reasons']) && is_array($setup['confluence_reasons'])) {
            return array_merge($setup['confluence_reasons'], $reasons);
        }

        $event = (string) ($setup['event'] ?? '');
        $kind = (string) ($setup['setup'] ?? '');
        if ($event === '' && $kind === '') {
            return $reasons;
        }

        $eventFa = match ($event) {
            'choch' => 'تغییر کاراکتر ساختار (CHoCH)',
            'bos' => 'شکست ساختار در جهت روند (BOS)',
            'break' => 'شکست محدوده رنج',
            default => '',
        };
        $kindFa = match ($kind) {
            'breakout' => 'ارز در کف بوده و سقف کوچک را شکست',
            'reversal' => 'بعد از رشد، برگشت ساختار',
            default => '',
        };

        $head = array_values(array_filter([$kindFa, $eventFa]));
        return array_merge($head, $reasons);
    }

    public function persist(Signal $signal): Signal
    {
        $signal->id = $this->signalRepo->save($signal);
        return $signal;
    }

    public function generate(MarketSnapshot $snapshot, string $timeframe, ?string $strategyName = null): ?Signal
    {
        $signal = $this->evaluate($snapshot, $timeframe, [], $strategyName);
        return $signal === null ? null : $this->persist($signal);
    }

    public static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

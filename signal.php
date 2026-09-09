<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/card.php';

/**
 * ============================================================================
 * signal.php — Core analysis engine.
 *
 * Exchange Manager / per-exchange Adapters -> MarketSnapshot
 *   -> Candle/Ticker/Orderbook managers
 *   -> Indicator Engine (empty registry, plug-in point)
 *   -> Support/Resistance, Order Block, FVG engines
 *   -> Confluence Engine -> Strategy Engine -> Signal Validator
 *   -> Signal Deduplication -> Signal Formatter / Generator
 *   -> Market Scanner (ranks ~200 symbols)
 *
 * No file here talks Telegram. bot.php/worker.php consume SignalGenerator
 * and MarketScanner; nothing in here knows a channel or a chat_id exists.
 * ============================================================================
 */

// ============================================================================
// SECTION 0 — ENUMS & VALUE OBJECTS
// ============================================================================

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

    /**
     * Whether this candle is internally consistent.
     *
     * Every adapter maps a different response shape onto these six fields,
     * and the shapes are not consistent between exchanges — Gate.io returns
     * [time, quoteVolume, close, high, low, open, baseVolume], which is not
     * OHLCV at all. A mis-mapped feed does not throw; it produces
     * plausible-looking numbers in the wrong slots and quietly poisons every
     * signal derived from them. Cheaper to refuse the candle.
     */
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
        // Milliseconds since 2010, and not implausibly far in the future.
        return $this->openTime > 1_262_304_000_000 && $this->openTime < (time() + 86_400) * 1000;
    }
}

final class MarketSnapshot
{
    /** @param array<string, Candle[]> $candles keyed by timeframe */
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

    /** @return Candle[] */
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
    /** @param string[] $reasons */
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
        public readonly float $riskReward,
        public readonly float $score,
        public readonly string $confidence,
        public readonly string $strategy,
        public readonly array $reasons,
        public readonly string $fingerprint,
        public string $status = 'pending',
        public ?int $id = null,
        public readonly int $createdAt = 0,
        /** Announced leverage for this trade, chosen by LeverageEngine. */
        public readonly int $leverage = 1,
        /** Liquidity/volatility bucket the leverage was derived from. */
        public readonly string $tier = 'alt',
        /**
         * The stop currently in force. Starts equal to $stopLoss and is
         * raised to $entry once TP1 is hit (the risk-free move), while
         * $stopLoss keeps the original level the card was published with.
         */
        public ?float $activeStop = null,
    ) {
        $this->activeStop ??= $stopLoss;
    }

    /** Profit/loss at $price as a percentage of margin, i.e. including leverage. */
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

// ============================================================================
// SECTION 1 — HTTP CLIENT + RATE LIMITER (shared by all adapters)
// ============================================================================

final class RateLimiter
{
    /** @var array<string, array{count:int, windowStart:int}> */
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
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string, json:?array}
     */
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
        // Sleep in short slices so a ShutdownFlag request (worker.php's
        // SIGTERM/SIGINT handler) interrupts a long backoff immediately
        // instead of after the full delay.
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

// ============================================================================
// SECTION 2 — WEBSOCKET CLIENT (raw RFC6455, no external dependencies)
// Used by adapters that expose a raw (non Socket.IO) public WS feed.
// ============================================================================

final class WebSocketClient
{
    /** @var resource|null */
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

    /**
     * Non-blocking-ish poll: waits up to $timeoutSeconds for data, returns
     * the decoded text payload, or null if nothing arrived / connection closed.
     */
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

        if ($opcode === 0x8) { // close
            $this->connected = false;
            return null;
        }
        if ($opcode === 0x9) { // ping -> pong
            $this->send($payload, 0xA);
            return null;
        }
        if ($opcode === 0xA) { // pong
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

// ============================================================================
// SECTION 3 — EXCHANGE ADAPTER INTERFACE
// ============================================================================

interface ExchangeAdapter
{
    public function name(): string;

    /** @return array<int,array{symbol:string,base:string,quote:string,status:string}> */
    public function fetchExchangeSymbols(): array;

    /** @return array<string,array{volume:float,lastPrice:float,bid:float,ask:float,priceChangePercent:float}> keyed by symbol */
    public function fetchTicker24h(): array;

    /** @return Candle[] */
    public function fetchCandles(string $symbol, string $timeframe, int $limit): array;

    /** @return array{bids:array<int,array{0:float,1:float}>, asks:array<int,array{0:float,1:float}>} */
    public function fetchOrderBook(string $symbol, int $depth): array;

    public function supportsWebSocket(): bool;

    /** @param string[] $symbols @param string[] $timeframes */
    public function connectWebSocket(array $symbols, array $timeframes): ?WebSocketClient;

    public function handleWebSocketMessage(string $raw, MarketDataStore $store): void;
}

abstract class AbstractExchangeAdapter implements ExchangeAdapter
{
    /** @var array<string,string> maps internal timeframe -> exchange-native interval */
    protected array $timeframeMap = [];

    protected function mapTimeframe(string $timeframe): string
    {
        return $this->timeframeMap[$timeframe] ?? $timeframe;
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
        // No-op by default; overridden by adapters that support streaming.
    }
}

// ============================================================================
// SECTION 4 — BINANCE ADAPTER
// ============================================================================

final class BinanceAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '1h' => '1h', '4h' => '4h', '1D' => '1d',
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
                $streams[] = "$lower@kline_" . $this->mapTimeframe($tf);
            }
        }
        if (empty($streams)) {
            return null;
        }
        // Binance combined streams are capped; callers are expected to shard
        // symbol batches across multiple WebSocketClient instances.
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

// ============================================================================
// SECTION 5 — MEXC ADAPTER
// ============================================================================

final class MexcAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '1h' => '60m', '4h' => '4h', '1D' => '1d',
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
        // MEXC v3 WS: subscribe via a JSON control message per channel.
        // Channel names below follow MEXC's documented spot v3 WS schema at
        // time of writing; if MEXC revises channel naming this call fails
        // soft (no messages arrive) and the adapter keeps working over REST.
        foreach ($symbols as $symbol) {
            foreach ($timeframes as $tf) {
                $interval = str_replace('m', 'Min', $this->mapTimeframe($tf));
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

// ============================================================================
// SECTION 6 — GATE.IO / BITGET / HTX ADAPTERS
//
// Three large spot venues whose public market-data endpoints need no API
// key. Response shapes were taken from ccxt's parsers rather than from
// memory, which matters most for Gate: its candlestick array is NOT in
// OHLCV order — it is [time, quoteVolume, close, high, low, open,
// baseVolume]. Reading it as OHLCV would have produced plausible-looking
// but completely wrong candles, which is worse than having no exchange.
// ============================================================================

final class GateAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '1h' => '1h', '4h' => '4h', '1D' => '1d',
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
        RateLimiter::acquire('gate', 600);
        $url = Config::gateRestBase() . '/api/v4/spot/candlesticks?' . http_build_query([
            'currency_pair' => $symbol,
            'interval' => $this->mapTimeframe($timeframe),
            'limit' => min($limit, 1000),
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        foreach ($res['json'] ?? [] as $row) {
            if (!is_array($row) || count($row) < 7) {
                continue;
            }
            // [0] seconds, [1] quote vol, [2] close, [3] high, [4] low, [5] open, [6] base vol
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
        '1m' => '1min', '5m' => '5min', '15m' => '15min', '1h' => '1h', '4h' => '4h', '1D' => '1day',
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
        RateLimiter::acquire('bitget', 600);
        $url = Config::bitgetRestBase() . '/api/v2/spot/market/candles?' . http_build_query([
            'symbol' => $symbol,
            'granularity' => $this->mapTimeframe($timeframe),
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
        '1m' => '1min', '5m' => '5min', '15m' => '15min', '1h' => '60min', '4h' => '4hour', '1D' => '1day',
    ];

    public function name(): string
    {
        return 'htx';
    }

    /** HTX symbol ids are lowercase throughout its public API. */
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
                // "vol" is turnover in the quote currency; "amount" is base volume.
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
        RateLimiter::acquire('htx', 600);
        $url = Config::htxRestBase() . '/market/history/kline?' . http_build_query([
            'symbol' => $symbol,
            'period' => $this->mapTimeframe($timeframe),
            'size' => min($limit, 2000),
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        // HTX returns newest-first; reverse to chronological order like every other adapter.
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

// ============================================================================
// SECTION 6.5 — CRYPTOCOMPARE ADAPTER
// A market-data aggregator, not an exchange with its own trading/compliance
// obligations -- a realistic route around a host's IP being blocked by an
// exchange directly (see WallexAdapter above for the same REST-only
// reasoning; CryptoCompare additionally has no per-exchange WS to plug in).
// Symbols are synthesized as "{coin}USDT" since CryptoCompare is coin-
// centric (fsym/tsym pairs against its aggregated feed) rather than
// organized around one exchange's own tradeable pairs.
// ============================================================================

final class CryptoCompareAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => 'minute', '5m' => 'minute', '15m' => 'minute',
        '1h' => 'hour', '4h' => 'hour', '1D' => 'day', '1W' => 'day',
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
                // CryptoCompare's aggregated feed has no real bid/ask
                // (it's not one order book) -- collapsing both to price
                // gives a 0% spread rather than fabricating a number.
                'bid' => $price,
                'ask' => $price,
                'priceChangePercent' => (float) ($d['CHANGEPCT24HOUR'] ?? 0),
            ];
        }
        return $out;
    }

    public function fetchCandles(string $symbol, string $timeframe, int $limit): array
    {
        [$base, $quote] = $this->splitSymbol($symbol);
        $endpoint = match ($this->mapTimeframe($timeframe)) {
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
        // The free aggregation API doesn't expose real order-book depth;
        // an empty book is the honest answer, not a fabricated one.
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

// ============================================================================
// SECTION 6b — BYBIT ADAPTER (public spot market data, no API key needed;
// not one of the exchanges known to geo-block this host)
// ============================================================================

final class BybitAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1', '5m' => '5', '15m' => '15', '1h' => '60', '4h' => '240', '1D' => 'D',
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
        RateLimiter::acquire('bybit', 600);
        $url = Config::bybitRestBase() . '/v5/market/kline?' . http_build_query([
            'category' => 'spot', 'symbol' => $symbol, 'interval' => $this->mapTimeframe($timeframe), 'limit' => $limit,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        // Bybit returns newest-first; reverse to chronological order like every other adapter.
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

// ============================================================================
// SECTION 6c — OKX ADAPTER (public spot market data, no API key needed)
// ============================================================================

final class OkxAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1m', '5m' => '5m', '15m' => '15m', '1h' => '1H', '4h' => '4H', '1D' => '1D',
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
        RateLimiter::acquire('okx', 600);
        $url = Config::okxRestBase() . '/api/v5/market/candles?' . http_build_query([
            'instId' => $symbol, 'bar' => $this->mapTimeframe($timeframe), 'limit' => $limit,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        // OKX returns newest-first; reverse to chronological order like every other adapter.
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

// ============================================================================
// SECTION 6d — KUCOIN ADAPTER (public spot market data, no API key needed)
// ============================================================================

final class KucoinAdapter extends AbstractExchangeAdapter
{
    protected array $timeframeMap = [
        '1m' => '1min', '5m' => '5min', '15m' => '15min', '1h' => '1hour', '4h' => '4hour', '1D' => '1day',
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
        RateLimiter::acquire('kucoin', 600);
        $intervalSeconds = self::INTERVAL_SECONDS[$timeframe] ?? 3600;
        $endAt = time();
        $startAt = $endAt - ($limit * $intervalSeconds);
        $url = Config::kucoinRestBase() . '/api/v1/market/candles?' . http_build_query([
            'symbol' => $symbol, 'type' => $this->mapTimeframe($timeframe), 'startAt' => $startAt, 'endAt' => $endAt,
        ]);
        $res = HttpClient::request('GET', $url);
        $out = [];
        // KuCoin returns newest-first rows shaped [time, open, close, high, low, volume, turnover].
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

// ============================================================================
// SECTION 7 — EXCHANGE MANAGER (isolation + circuit breaker)
// ============================================================================

final class ExchangeManager
{
    /** @var array<string,ExchangeAdapter> */
    private array $adapters = [];

    /** @var array<string,array{failures:int, openUntil:int}> */
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

    /** @return array<string,ExchangeAdapter> */
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

    /**
     * Runs $fn(adapter) with per-exchange failure isolation. A failing
     * exchange opens its own circuit (exponential backoff) without
     * affecting calls against any other exchange.
     */
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

    /** @return array<string,string> exchange => 🟢/🔴/🟡 status for the health panel */
    public function healthSnapshot(): array
    {
        $out = [];
        foreach ($this->adapters as $name => $adapter) {
            $h = $this->health[$name];
            $out[$name] = $h['openUntil'] > time() ? '🔴' : ($h['failures'] > 0 ? '🟡' : '🟢');
        }
        return $out;
    }
}

// ============================================================================
// SECTION 8 — MARKET DATA (Candle / Ticker / Orderbook managers)
// ============================================================================

final class ExchangeRepository
{
    /** @var array<string,int> */
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
        // Single choke point for every adapter's output — see Candle::isSane().
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
                /** @var Candle $candle */
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

    /** @return Candle[] most recent first turned oldest-first */
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

    /**
     * Cheap existence check (no row hydration) — lets a cron-bounded worker
     * invocation skip re-priming a symbol/timeframe that already has history,
     * instead of re-fetching everything on every single invocation.
     */
    public function hasAny(string $exchange, string $symbol, string $timeframe): bool
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM candles WHERE exchange_id = :eid AND symbol = :symbol AND timeframe = :tf LIMIT 1'
        );
        $stmt->execute([':eid' => $exchangeId, ':symbol' => $symbol, ':tf' => $timeframe]);
        return $stmt->fetchColumn() !== false;
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
    /** @var array<string,array> in-process cache only (best-effort, high churn) */
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

/**
 * Aggregation point that WebSocket handlers and REST sync both write
 * through, and that SignalGenerator reads MarketSnapshot from. Backed by
 * DB (candles / market_data tables) so bot.php (a separate process/webhook)
 * sees the same state worker.php produces.
 */
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
        // Only persist closed candles from the stream to avoid writing a
        // half-formed bar into the shared table on every tick.
        if ($closed) {
            $this->candles->upsertMany($exchange, $symbol, $timeframe, [$candle]);
        }
    }

    public function upsertTicker(string $exchange, string $symbol, float $price, float $bid, float $ask, float $volume24h): void
    {
        $this->tickers->upsert($exchange, $symbol, $price, $bid, $ask, $volume24h);
    }

    /**
     * Last known price, preferring the 24h ticker and falling back to the
     * most recent candle close.
     *
     * The fallback matters more than it looks: a price of 0 makes a symbol
     * invisible to the signal pass AND freezes every open position on it
     * (monitorOpenPositions() skips a zero price, so TP/SL would never
     * resolve and the slot would never free). A missing ticker — one
     * exchange keying its ticker map differently, a thin pair absent from
     * the 24h feed, a single failed fetch — should not be able to do that
     * while good candle data is sitting right there.
     */
    public function latestPrice(string $exchange, string $symbol): float
    {
        $ticker = $this->tickers->get($exchange, $symbol);
        $price = (float) ($ticker['price'] ?? 0);
        if ($price > 0) {
            return $price;
        }
        return $this->lastCandleClose($exchange, $symbol);
    }

    /** Most recent close across the configured timeframes, or 0 if there is no candle at all. */
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

    /** @param string[] $timeframes */
    public function buildSnapshot(string $exchange, string $symbol, array $timeframes, int $candleLimit = 200): MarketSnapshot
    {
        $ticker = $this->tickers->get($exchange, $symbol);
        $candlesByTf = [];
        foreach ($timeframes as $tf) {
            $candlesByTf[$tf] = $this->candles->recent($exchange, $symbol, $tf, $candleLimit);
        }
        $price = (float) ($ticker['price'] ?? 0);
        if ($price <= 0) {
            // Same reasoning as latestPrice(): candles present but no ticker
            // must not silently take the symbol out of circulation.
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

// ============================================================================
// SECTION 9 — INDICATOR ENGINE (modular, intentionally empty registry)
// ============================================================================

interface Indicator
{
    public function name(): string;

    /**
     * @param Candle[] $candles oldest-first
     * @return array<string,mixed> indicator-specific output (values, signal, etc.)
     */
    public function calculate(array $candles): array;
}

final class IndicatorEngine
{
    /** @var array<string,Indicator> */
    private array $registry = [];

    public function register(Indicator $indicator): void
    {
        $this->registry[$indicator->name()] = $indicator;
    }

    public function unregister(string $name): void
    {
        unset($this->registry[$name]);
    }

    /** @return string[] */
    public function registeredNames(): array
    {
        return array_keys($this->registry);
    }

    /**
     * @param Candle[] $candles
     * @return array<string,array<string,mixed>> results keyed by indicator name.
     *   Empty array when no indicators are registered yet — this is expected
     *   until concrete indicator rules are supplied; ConfluenceEngine treats
     *   an empty result set as a neutral contribution, never a fabricated one.
     */
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

// ============================================================================
// SECTION 10 — SWING PIVOT DETECTION (shared primitive for S/R, OB, trend)
// ============================================================================

final class SwingPivots
{
    /**
     * @param Candle[] $candles oldest-first
     * @return array{highs: array<int,array{index:int, candle:Candle}>, lows: array<int,array{index:int, candle:Candle}>}
     */
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

    /** @param Candle[] $candles */
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

    /**
     * Structural trend from swing highs/lows: HH+HL -> bullish, LH+LL -> bearish, else neutral.
     * @param Candle[] $candles
     */
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

// ============================================================================
// SECTION 11 — SUPPORT / RESISTANCE ENGINE
// (generic swing-high/low + repeated-rejection clustering; exact scoring
//  rules to be refined later per user spec — architecture is final)
// ============================================================================

final class SupportResistanceEngine
{
    public function __construct(
        private int $lookback = 5,
        private float $clusterAtrMultiplier = 0.5,
    ) {
    }

    /** @param Candle[] $candles oldest-first @return Zone[] */
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

    /**
     * @param array<int,array{index:int,candle:Candle}> $pivots
     * @return Zone[]
     */
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

// ============================================================================
// SECTION 12 — ORDER BLOCK ENGINE
// (generic: last opposite candle before an impulsive/displacement move;
//  exact OB rules to be refined later — architecture is final)
// ============================================================================

final class OrderBlockEngine
{
    public function __construct(private float $displacementAtrMultiplier = 1.5)
    {
    }

    /** @param Candle[] $candles oldest-first @return OrderBlock[] */
    public function detect(array $candles, string $timeframe): array
    {
        $n = count($candles);
        if ($n < 20) {
            return [];
        }
        $atr = SwingPivots::atr($candles);
        $threshold = $atr * $this->displacementAtrMultiplier;
        $blocks = [];

        for ($i = 1; $i < $n; $i++) {
            $impulse = $candles[$i];
            if ($impulse->range() < $threshold || $threshold <= 0) {
                continue;
            }
            $prev = $candles[$i - 1];

            if ($impulse->isBullish() && !$prev->isBullish()) {
                $blocks[] = new OrderBlock(
                    type: OrderBlockType::BULLISH,
                    high: $prev->high,
                    low: $prev->low,
                    timeframe: $timeframe,
                    strength: round(min(100.0, ($impulse->range() / max($atr, 1e-9)) * 20), 2),
                    volume: $prev->volume,
                    mitigated: $this->isMitigated($candles, $i + 1, $prev->low, $prev->high, true),
                    createdAt: $prev->openTime,
                );
            } elseif (!$impulse->isBullish() && $prev->isBullish()) {
                $blocks[] = new OrderBlock(
                    type: OrderBlockType::BEARISH,
                    high: $prev->high,
                    low: $prev->low,
                    timeframe: $timeframe,
                    strength: round(min(100.0, ($impulse->range() / max($atr, 1e-9)) * 20), 2),
                    volume: $prev->volume,
                    mitigated: $this->isMitigated($candles, $i + 1, $prev->low, $prev->high, false),
                    createdAt: $prev->openTime,
                );
            }
        }

        // Keep only the most recent, most relevant blocks.
        $blocks = array_slice($blocks, -20);
        return $blocks;
    }

    /** @param Candle[] $candles */
    private function isMitigated(array $candles, int $fromIndex, float $low, float $high, bool $bullish): bool
    {
        for ($i = $fromIndex; $i < count($candles); $i++) {
            if ($bullish && $candles[$i]->low <= $high) {
                return true;
            }
            if (!$bullish && $candles[$i]->high >= $low) {
                return true;
            }
        }
        return false;
    }
}

// ============================================================================
// SECTION 13 — FVG ENGINE
// (classic 3-candle imbalance; exact FVG rules to be refined later —
//  architecture is final)
// ============================================================================

final class FvgEngine
{
    /** @param Candle[] $candles oldest-first @return Fvg[] */
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

    /** @param Candle[] $candles */
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

// ============================================================================
// SECTION 14 — CONFLUENCE ENGINE
// ============================================================================

final class ConfluenceEngine
{
    /**
     * Produces TWO separate numbers, which the previous version conflated
     * into one — and that conflation made short signals impossible.
     *
     *   directional  0..100, where 0 is maximally bearish, 50 neutral and
     *                100 maximally bullish. This is what picks LONG vs SHORT.
     *   score        0..100 conviction: how strong the setup is, in either
     *                direction. This is what MIN_SIGNAL_SCORE gates on.
     *
     * Before, `bias` was read off the same value that `score` was gated on:
     * bearish meant score <= 40, while publishing required score >= 45, so
     * with any realistic threshold the bot could only ever go long. Now a
     * strong short scores exactly as high as an equally strong long.
     *
     * The directional factors were also made genuinely directional: sitting
     * on support now reads bullish and sitting under resistance reads
     * bearish (both used to push the score the same way, upwards), and
     * order blocks and FVGs are weighted by their own bullish/bearish type.
     * Volume no longer votes on direction at all — a volume spike is
     * conviction, and it says nothing about which way price is about to go.
     *
     * @param Zone[] $zones
     * @param OrderBlock[] $orderBlocks
     * @param Fvg[] $fvgs
     * @param array<string,array<string,mixed>> $indicatorResults
     * @return array{score:float, directional:float, coverage:float, breakdown:array<string,float>, reasons:string[], bias:string}
     */
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

        // -- trend ---------------------------------------------------------
        $breakdown['trend'] = match ($trend) {
            'bullish' => 100.0,
            'bearish' => 0.0,
            default => 50.0,
        };
        if ($trend !== 'neutral') {
            $reasons[] = 'روند ساختاری: ' . ($trend === 'bullish' ? 'صعودی' : 'نزولی');
        }

        // -- support: near support is bullish --------------------------------
        $nearestSupport = $this->nearestZone($zones, ZoneType::SUPPORT, $price);
        $supportPull = $nearestSupport !== null ? $this->proximityScore($price, $nearestSupport->mid(), $nearestSupport->strength) / 100 : 0.0;
        $breakdown['support'] = 50.0 + 50.0 * $supportPull;
        if ($nearestSupport !== null && $supportPull > 0.4) {
            $reasons[] = sprintf('نزدیک به ناحیه حمایت %s (قدرت %.0f)', number_format($nearestSupport->mid(), 6), $nearestSupport->strength);
        }

        // -- resistance: near resistance is bearish --------------------------
        $nearestResistance = $this->nearestZone($zones, ZoneType::RESISTANCE, $price);
        $resistancePull = $nearestResistance !== null ? $this->proximityScore($price, $nearestResistance->mid(), $nearestResistance->strength) / 100 : 0.0;
        $breakdown['resistance'] = 50.0 - 50.0 * $resistancePull;
        if ($nearestResistance !== null && $resistancePull > 0.4) {
            $reasons[] = sprintf('نزدیک به ناحیه مقاومت %s (قدرت %.0f)', number_format($nearestResistance->mid(), 6), $nearestResistance->strength);
        }

        // -- order blocks the price is currently inside ----------------------
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

        // -- unfilled FVGs the price is currently inside ---------------------
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

        // -- registered indicators (neutral while the registry is empty) -----
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

        // -- volume: conviction only, deliberately not part of direction -----
        $volumeScore = $this->volumeScore($snapshot->candlesFor($timeframe));
        $breakdown['volume'] = $volumeScore;
        if ($volumeScore > 60) {
            $reasons[] = 'حجم معاملات بالاتر از میانگین';
        }

        // A factor sitting exactly at 50 has NO evidence — price is not
        // inside any order block, no zone is within range, no indicator is
        // registered. Averaging those in as if they were readings pulls the
        // result toward neutral and caps how strong any setup can look:
        // measured over a sweep of trending markets, a clean trend with no
        // other factor in range could never score above ~42 no matter how
        // clean it was, purely because four silent factors outvoted it.
        //
        // So abstaining factors are excluded from the direction, and the
        // share of weight that DID have something to say becomes a separate
        // conviction multiplier — which is what "confluence" should mean:
        // several independent factors agreeing beats one factor shouting.
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

        // Conviction: how far from neutral the evidence points, discounted
        // by how little of the picture that evidence covers, plus volume as
        // a supporting (never deciding) contribution. The 0.75 exponent is
        // the deliberate part: one lone factor on ordinary volume lands
        // below a sensible threshold, while two or three agreeing factors
        // clear it comfortably.
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

    /** 50 when there is nothing (or it is balanced), 100 all-bullish, 0 all-bearish. */
    private function directionalRatio(int $bullish, int $bearish): float
    {
        $total = $bullish + $bearish;
        if ($total <= 0) {
            return 50.0;
        }
        return 50.0 + 50.0 * (($bullish - $bearish) / $total);
    }

    /** @param Zone[] $zones */
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
        $proximity = max(0.0, 1 - min($distancePct / 2, 1)); // within ~2% fades to 0
        return round($proximity * $strength, 2);
    }

    /** @param Candle[] $candles */
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

// ============================================================================
// SECTION 15 — STRATEGY ENGINE
// ============================================================================

interface Strategy
{
    public function name(): string;

    /**
     * @param Zone[] $zones
     * @param OrderBlock[] $orderBlocks
     * @param Fvg[] $fvgs
     * @param array{score:float, directional:float, coverage:float, breakdown:array<string,float>, reasons:string[], bias:string} $confluence
     */
    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $zones,
        array $orderBlocks,
        array $fvgs,
        array $confluence,
    ): ?array; // returns ['direction'=>Direction,'entry'=>..,'stop_loss'=>..,'tp1'=>..,'tp2'=>..,'tp3'=>..] or null
}

/**
 * Default reference strategy built only from the structural inputs this
 * project explicitly commissions (S/R, Order Blocks, FVG, Confluence).
 * Swappable/disable-able per `strategies.is_enabled` — final strategy
 * rules are pending the user's exact specification.
 */
final class DefaultStructureStrategy implements Strategy
{
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

    /**
     * Targets come from real opposing structure (the next S/R zones and
     * unmitigated order blocks in the trade's direction) when available --
     * the same thing a trader eyeballs on a chart with a manual RR tool --
     * instead of a fixed risk multiple. That's why realised RR now varies
     * signal to signal rather than always landing on the same number.
     * Falls back to fixed multiples (2R/3.5R/5R) only when structure gives
     * nothing usable, so a thin market never blocks a signal outright.
     *
     * @param Zone[] $zones
     * @param OrderBlock[] $obs
     * @return array{0:float,1:float,2:float}
     */
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
            // A structural level already claiming tp1/tp2 can sit closer or
            // farther than the fixed-multiple fallback would -- always push
            // the fallback past whatever came before it so tp1<tp2<tp3 (or
            // the mirror for SHORT) holds even when the two are mixed.
            $kept[$i] = $ascending
                ? max($candidate, $prevLevel + $minGap)
                : min($candidate, $prevLevel - $minGap);
        }

        return [$kept[0], $kept[1], $kept[2]];
    }
}

final class StrategyEngine
{
    /** @var array<string,Strategy> */
    private array $strategies = [];

    public function __construct()
    {
        $this->register(new DefaultStructureStrategy());
    }

    public function register(Strategy $strategy): void
    {
        $this->strategies[$strategy->name()] = $strategy;
    }

    public function get(string $name): ?Strategy
    {
        return $this->strategies[$name] ?? null;
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->strategies);
    }
}

// ============================================================================
// SECTION 15.5 — SYMBOL TIERS, LEVERAGE, TRADE PLANNING
//
// This is the "fully automatic" part of the bot: given a structural setup
// the strategy found, decide how much leverage this particular coin can
// carry, where the stop has to sit for that leverage to survive, and where
// the two targets land.
// ============================================================================

final class SymbolClassifier
{
    public const TIER_MAJOR = 'major';   // BTC / ETH — deep books, the high-leverage tier
    public const TIER_LARGE = 'large';   // top-volume alts
    public const TIER_ALT   = 'alt';     // ordinary altcoins
    public const TIER_MICRO = 'micro';   // thin books: memecoins, low-cap "shitcoins"

    /**
     * Best-effort base asset for a symbol, for the cases where the symbols
     * table has no base_asset stored (a hand-run test, an older row).
     * Exchanges spell the same pair four different ways: BTCUSDT,
     * BTC-USDT, BTC/USDT, BTC_USDT.
     */
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

    /**
     * Liquidity bucket. The thresholds are quote-currency 24h volume, and
     * they are deliberately generous at the bottom: this bot is explicitly
     * meant to trade memecoins and low-caps, so "micro" is a leverage
     * decision, not an exclusion.
     */
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
    /**
     * Majors get the configured high leverage (150x by default). Everything
     * else is placed inside the LEVERAGE_ALT_MIN..MAX band (20..25 by
     * default) by liquidity and volatility: a deep, calm book earns the top
     * of the band, a thin or wildly swinging one gets the bottom.
     *
     * @param float $volatilityPct ATR as a percentage of price on the signal timeframe
     */
    public static function forSymbol(string $baseAsset, float $volume24h, float $volatilityPct): int
    {
        $tier = SymbolClassifier::tier($baseAsset, $volume24h);
        if ($tier === SymbolClassifier::TIER_MAJOR) {
            return Config::leverageMajor();
        }

        $min = Config::leverageAltMin();
        $max = max($min, Config::leverageAltMax());

        // Liquidity: $1M of 24h volume scores 0, $500M scores 1 (log scale,
        // because volume across the whole altcoin universe spans decades).
        $liquidity = self::normalize(log10(max(1.0, $volume24h)), 6.0, 8.7);

        // Stability: 0.5% ATR scores 1, 6% ATR scores 0 — inverted, since
        // more volatility must mean less leverage.
        $stability = 1.0 - self::normalize($volatilityPct, 0.5, 6.0);

        // A micro-cap never reaches the top of the band no matter how calm
        // it looks on a quiet hour: thin books gap, and the ATR of the last
        // 14 candles does not price that in.
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

/**
 * Turns "the strategy likes a LONG here, with structure at X" into the
 * actual publishable trade: leverage, a stop that can survive that
 * leverage, and TP1/TP2 at the configured risk-multiples.
 */
final class TradePlanner
{
    /**
     * @param float $structuralStop where the strategy would put the stop from market structure alone
     * @return array{entry:float, stop_loss:float, tp1:float, tp2:float, leverage:int, tier:string, risk:float, rr:float, stop_pct:float}|null
     */
    public function plan(
        Direction $direction,
        float $entry,
        float $structuralStop,
        float $atr,
        string $baseAsset,
        float $volume24h,
    ): ?array {
        if ($entry <= 0) {
            return null;
        }

        $volatilityPct = $atr > 0 ? ($atr / $entry) * 100 : 0.0;
        $leverage = LeverageEngine::forSymbol($baseAsset, $volume24h, $volatilityPct);
        $tier = SymbolClassifier::tier($baseAsset, $volume24h);

        // How far the stop may sit before the leverage itself would have
        // closed the trade first. At 150x, liquidation is ~0.67% away, so a
        // 2% structural stop is fiction — cap it and let the targets scale
        // down with the (now smaller) risk.
        $maxStopPct = Config::leverageLiquidationBuffer() * (100.0 / $leverage);
        $minStopPct = Config::minStopPercent();
        if ($maxStopPct < $minStopPct) {
            // Leverage so high that no publishable stop fits inside it.
            return null;
        }

        $rawDistance = abs($entry - $structuralStop);
        $distance = max(
            $entry * ($minStopPct / 100),
            min($rawDistance, $entry * ($maxStopPct / 100))
        );
        if ($distance <= 0) {
            return null;
        }

        $sign = $direction === Direction::LONG ? 1 : -1;
        $tp1Rr = Config::tp1RiskReward();
        $tp2Rr = Config::tp2RiskReward();

        return [
            'entry' => $entry,
            'stop_loss' => $entry - $sign * $distance,
            'tp1' => $entry + $sign * $distance * $tp1Rr,
            'tp2' => $entry + $sign * $distance * $tp2Rr,
            'leverage' => $leverage,
            'tier' => $tier,
            'risk' => $distance,
            'rr' => round($tp1Rr, 2),
            'stop_pct' => ($distance / $entry) * 100,
        ];
    }
}

// ============================================================================
// SECTION 16 — SIGNAL VALIDATOR
// ============================================================================

final class SignalValidator
{
    /** @return array{valid:bool, reasons:string[]} */
    public function validate(Signal $signal, MarketSnapshot $snapshot): array
    {
        $reasons = [];

        if ($signal->score < Config::minSignalScore()) {
            $reasons[] = sprintf('امتیاز (%.2f) کمتر از حداقل مجاز (%.2f) است', $signal->score, Config::minSignalScore());
        }
        // Targets are now placed AT a configured risk-multiple (TP1_RR), so
        // every signal's R:R equals that setting by construction. Gating on
        // a separately-configured MIN_RISK_REWARD that happens to be higher
        // — e.g. a 1.5 left over from the structural-target era against a
        // TP1_RR of 1.2 — would reject 100% of signals with no visible
        // reason, so the explicit target multiple wins.
        $minRr = min(Config::minRiskReward(), Config::tp1RiskReward());
        if ($signal->riskReward < $minRr) {
            $reasons[] = sprintf('نسبت ریسک به ریوارد (%.2f) کمتر از حداقل مجاز (%.2f) است', $signal->riskReward, $minRr);
        }
        if ($signal->tp2 !== null && $signal->tp1 !== null) {
            $ordered = $signal->direction === Direction::LONG
                ? $signal->tp2 > $signal->tp1
                : $signal->tp2 < $signal->tp1;
            if (!$ordered) {
                $reasons[] = 'هدف دوم باید بعد از هدف اول قرار بگیرد';
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

// ============================================================================
// SECTION 17 — SIGNAL DEDUPLICATION
// ============================================================================

final class SignalDeduplicator
{
    public function fingerprint(string $exchange, string $symbol, Direction $direction, string $timeframe, string $strategy, float $entry): string
    {
        // Entry is bucketed (0.5% steps) so near-identical re-triggers of the
        // same structural setup collapse to the same fingerprint.
        $bucket = $entry > 0 ? round($entry / ($entry * 0.005)) : 0;
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

// ============================================================================
// SECTION 18 — SIGNAL FORMATTER
// ============================================================================

/**
 * Price display precision, scaled to the magnitude of the number.
 *
 * A fixed 8 decimals is right for a memecoin at 0.00000842 and absurd for
 * BTC at 89739.92258649 — and both go through the same templates and the
 * same card.
 */
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
    /**
     * Placeholder set for the entry message. Everything a reader needs to
     * act is in here — including the leveraged profit each target is worth,
     * which is the number the "profit shot" will later confirm.
     *
     * @param array<int,array<string,mixed>> $templateEntities
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public function format(Signal $signal, string $templateText, array $templateEntities): array
    {
        $risk = abs($signal->entry - $signal->stopLoss);
        $riskPct = $signal->entry > 0 ? ($risk / $signal->entry) * 100 : 0.0;

        $placeholders = [
            'symbol' => $signal->symbol,
            'exchange' => ucfirst($signal->exchange),
            'direction' => $signal->direction->value,
            'direction_fa' => self::directionFa($signal->direction->value),
            'entry' => $this->fmt($signal->entry),
            'sl' => $this->fmt($signal->stopLoss),
            'tp1' => $signal->tp1 !== null ? $this->fmt($signal->tp1) : '-',
            'tp2' => $signal->tp2 !== null ? $this->fmt($signal->tp2) : '-',
            'tp3' => $signal->tp3 !== null ? $this->fmt($signal->tp3) : '-',
            'tp1_profit' => $signal->tp1 !== null ? number_format($signal->leveragedPnlPercent($signal->tp1), 1) : '-',
            'tp2_profit' => $signal->tp2 !== null ? number_format($signal->leveragedPnlPercent($signal->tp2), 1) : '-',
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

    /**
     * Placeholder set for a trade-result announcement (TP1 risk-free, TP2
     * close, stop out, breakeven close).
     *
     * @param array<string,mixed> $row the signals table row
     * @param array<int,array<string,mixed>> $templateEntities
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public function formatResult(array $row, string $kind, float $exitPrice, string $templateText, array $templateEntities): array
    {
        $stats = self::resultStats($row, $exitPrice);

        $placeholders = [
            'symbol' => (string) $row['symbol'],
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
            'pnl' => $stats['pnl_signed'],
            'move' => $stats['move_signed'],
            'result' => $kind,
            'time' => date('Y-m-d H:i'),
        ];

        return TelegramEntityUtils::renderTemplate($templateText, $templateEntities, $placeholders);
    }

    /**
     * Shared PnL maths for a closed/partially-closed row, so the message
     * text and the image card can never disagree about the numbers.
     *
     * @param array<string,mixed> $row
     * @return array{leverage:int, move:float, pnl:float, move_signed:string, pnl_signed:string}
     */
    public static function resultStats(array $row, float $exitPrice): array
    {
        $entry = (float) $row['entry_price'];
        $leverage = max(1, (int) round((float) ($row['leverage'] ?? 1)));
        $move = $entry > 0
            ? (((string) $row['direction'] === 'LONG' ? $exitPrice - $entry : $entry - $exitPrice) / $entry) * 100
            : 0.0;
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

// ============================================================================
// SECTION 18.5 — CARD FACTORY
//
// The one place that maps trading data onto the image renderer's inputs, so
// the picture and the caption are always generated from the same numbers.
// ============================================================================

final class SignalCardFactory
{
    /** PNG bytes for the entry card, or null when cards are off/unavailable. */
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
            'time' => date('Y-m-d H:i') . ' ' . date('T'),
        ]);
    }

    /**
     * PNG bytes for the "profit shot" / trade-result card.
     *
     * @param array<string,mixed> $row the signals table row
     * @param string $kind tp1|tp2|sl|be
     */
    public static function result(array $row, string $kind, float $exitPrice): ?string
    {
        if (!CardConfig::enabled()) {
            return null;
        }
        $stats = SignalFormatter::resultStats($row, $exitPrice);

        return ResultCard::render([
            'kind' => $kind,
            'symbol' => self::displaySymbol((string) $row['symbol']),
            'direction' => (string) $row['direction'],
            'leverage' => $stats['leverage'] . 'X',
            'headline' => $stats['pnl_signed'] . '%',
            'move' => $stats['move_signed'] . '%',
            'entry' => self::fmt((float) $row['entry_price']),
            'exit' => self::fmt($exitPrice),
            'time' => date('Y-m-d H:i') . ' ' . date('T'),
        ]);
    }

    /** BTCUSDT / BTC-USDT / BTC_USDT all display as BTC/USDT. */
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

// ============================================================================
// SECTION 19 — REPOSITORIES
// ============================================================================

final class SignalRepository
{
    public function save(Signal $signal): int
    {
        $exchangeId = ExchangeRepository::idForName($signal->exchange);
        $now = date('Y-m-d H:i:s');
        $stmt = Database::pdo()->prepare(
            'INSERT INTO signals (uuid, exchange_id, symbol, direction, timeframe, entry_price, stop_loss, tp1, tp2, tp3,
                risk_reward, score, confidence, strategy, reasons, fingerprint, status, leverage, tier, stage, active_stop, created_at, updated_at)
             VALUES (:uuid, :eid, :symbol, :dir, :tf, :entry, :sl, :tp1, :tp2, :tp3, :rr, :score, :conf, :strategy, :reasons, :fp, :status, :lev, :tier, :stage, :astop, :now1, :now2)'
        );
        $stmt->execute([
            ':uuid' => $signal->uuid, ':eid' => $exchangeId, ':symbol' => $signal->symbol, ':dir' => $signal->direction->value,
            ':tf' => $signal->timeframe, ':entry' => $signal->entry, ':sl' => $signal->stopLoss,
            ':tp1' => $signal->tp1, ':tp2' => $signal->tp2, ':tp3' => $signal->tp3, ':rr' => $signal->riskReward,
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
        // Bind PHP-computed day bounds instead of relying on SQL date
        // functions, which differ between MySQL and SQLite.
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM signals WHERE created_at >= :start AND created_at < :end');
        $stmt->execute([':start' => date('Y-m-d 00:00:00'), ':end' => date('Y-m-d 00:00:00', strtotime('+1 day'))]);
        return (int) $stmt->fetchColumn();
    }

    public function countActive(): int
    {
        $stmt = Database::pdo()->query("SELECT COUNT(*) FROM signals WHERE status IN ('queued','sent')");
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM signals ORDER BY created_at DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * True while this symbol already has a dispatched (or, in DRY_RUN,
     * queued) signal whose outcome (SL or TP1) hasn't been determined yet
     * -- used to hold off issuing a new signal for the same symbol until
     * the previous one's result is known, instead of firing back-to-back.
     */
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

    /** @return array<int,array<string,mixed>> open positions, joined with their exchange name */
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

    /**
     * TP1 was reached: the trade moves to the risk-free stage and its stop
     * is lifted to the entry price, so from here the worst case is a
     * breakeven exit. The position stays open, running toward TP2.
     */
    public function markRiskFree(int $id, float $price, float $entry): void
    {
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE signals
                SET stage = 'risk_free', active_stop = :entry, tp1_hit_at = :now, tp1_hit_price = :price, updated_at = :now
              WHERE id = :id"
        )->execute([':entry' => $entry, ':now' => $now, ':price' => $price, ':id' => $id]);
    }

    /**
     * Final close. `result` carries the real state (tp1/tp2/sl/be) while
     * `outcome` keeps the legacy win/loss value its CHECK constraint
     * allows, so anything still reading the old column stays correct.
     */
    public function close(int $id, string $result, float $price): void
    {
        $legacyOutcome = match ($result) {
            'tp1', 'tp2' => 'tp1',
            'sl' => 'sl',
            default => null, // a breakeven exit is neither a win nor a loss
        };
        $now = date('Y-m-d H:i:s');
        Database::pdo()->prepare(
            "UPDATE signals
                SET stage = 'closed', result = :result, outcome = :outcome,
                    resolved_at = :now, resolved_price = :price, updated_at = :now
              WHERE id = :id"
        )->execute([':result' => $result, ':outcome' => $legacyOutcome, ':now' => $now, ':price' => $price, ':id' => $id]);
    }

    /** When the most recent signal was published — the input to the 15-minute pacing gate. */
    public function lastDispatchedAt(): ?int
    {
        $stmt = Database::pdo()->query(
            "SELECT created_at FROM signals WHERE status IN ('sent','queued') ORDER BY id DESC LIMIT 1"
        );
        $v = $stmt->fetchColumn();
        return $v === false || $v === null ? null : (int) strtotime((string) $v);
    }

    public function countOpen(): int
    {
        $stmt = Database::pdo()->query(
            "SELECT COUNT(*) FROM signals WHERE status IN ('sent','queued') AND resolved_at IS NULL"
        );
        return (int) $stmt->fetchColumn();
    }

    /** @return array<int,array<string,mixed>> */
    public function recentClosed(int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM signals WHERE resolved_at IS NOT NULL ORDER BY resolved_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Win/loss tally over closed trades, for the panel. TP1 and TP2 both
     * count as wins; a breakeven exit counts as neither.
     *
     * @return array{total:int, wins:int, losses:int, breakeven:int, win_rate:float}
     */
    public function performance(): array
    {
        $rows = Database::pdo()->query(
            "SELECT result, COUNT(*) AS n FROM signals WHERE resolved_at IS NOT NULL GROUP BY result"
        )->fetchAll();

        $counts = ['tp1' => 0, 'tp2' => 0, 'sl' => 0, 'be' => 0];
        foreach ($rows as $row) {
            $key = (string) ($row['result'] ?? '');
            if (isset($counts[$key])) {
                $counts[$key] = (int) $row['n'];
            }
        }
        $wins = $counts['tp1'] + $counts['tp2'];
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
    /** @param Zone[] $zones */
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
            /** @var Zone $zone */
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $symbol, ':type' => $zone->type->value, ':high' => $zone->high,
                ':low' => $zone->low, ':tf' => $timeframe, ':strength' => $zone->strength, ':volume' => $zone->volume,
                ':touches' => $zone->touches, ':source' => $zone->source, ':now1' => $now, ':now2' => $now,
            ]);
        }
    }

    /** @return Zone[] */
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
    /** @param OrderBlock[] $blocks */
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
            /** @var OrderBlock $ob */
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $symbol, ':type' => $ob->type->value, ':high' => $ob->high,
                ':low' => $ob->low, ':tf' => $timeframe, ':strength' => $ob->strength, ':volume' => $ob->volume,
                ':mit' => $ob->mitigated ? 1 : 0, ':created' => date('Y-m-d H:i:s', (int) ($ob->createdAt / 1000) ?: time()),
            ]);
        }
    }

    /** @return OrderBlock[] */
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
    /** @param Fvg[] $fvgs */
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
            /** @var Fvg $fvg */
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $symbol, ':type' => $fvg->type->value, ':high' => $fvg->high,
                ':low' => $fvg->low, ':mid' => $fvg->midpoint, ':tf' => $timeframe, ':size' => $fvg->size,
                ':filled' => $fvg->filled ? 1 : 0, ':created' => date('Y-m-d H:i:s', (int) ($fvg->createdAt / 1000) ?: time()),
            ]);
        }
    }

    /** @return Fvg[] */
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
    /** @param array<int,array{symbol:string,base:string,quote:string,volume:float,liquidity:float,spread:float,volatility:float,rank:int}> $rows */
    public function upsertRanked(string $exchange, array $rows): void
    {
        $exchangeId = ExchangeRepository::idForName($exchange);
        if ($exchangeId === null) {
            return;
        }
        $pdo = Database::pdo();
        $pdo->prepare('UPDATE symbols SET is_active = 0 WHERE exchange_id = :eid')->execute([':eid' => $exchangeId]);

        $stmt = $pdo->prepare(
            'INSERT INTO symbols (exchange_id, symbol, base_asset, quote_asset, volume_24h, liquidity_score, spread_pct, volatility, rank_position, is_active, last_scanned_at)
             VALUES (:eid, :symbol, :base, :quote, :vol, :liq, :spread, :volat, :rank, 1, :now)
             ON CONFLICT(exchange_id, symbol) DO UPDATE SET
                base_asset = excluded.base_asset, quote_asset = excluded.quote_asset, volume_24h = excluded.volume_24h,
                liquidity_score = excluded.liquidity_score, spread_pct = excluded.spread_pct, volatility = excluded.volatility,
                rank_position = excluded.rank_position, is_active = 1, last_scanned_at = excluded.last_scanned_at'
        );
        $now = date('Y-m-d H:i:s');
        foreach ($rows as $row) {
            $stmt->execute([
                ':eid' => $exchangeId, ':symbol' => $row['symbol'], ':base' => $row['base'], ':quote' => $row['quote'],
                ':vol' => $row['volume'], ':liq' => $row['liquidity'], ':spread' => $row['spread'], ':volat' => $row['volatility'],
                ':rank' => $row['rank'], ':now' => $now,
            ]);
        }
    }

    /**
     * @return array<int,array{exchange:string,symbol:string,base_asset:string,quote_asset:string,volume_24h:float,volatility:float,spread_pct:float,rank_position:int}>
     */
    public function listActive(?string $exchange = null, ?int $limit = null): array
    {
        $sql = 'SELECT e.name AS exchange, s.symbol, s.base_asset, s.quote_asset, s.volume_24h, s.volatility, s.spread_pct, s.rank_position
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

    /**
     * The scan universe, with BTC/ETH (whatever LEVERAGE_MAJOR_ASSETS says)
     * pulled to the front regardless of where the volume ranking put them,
     * and the rest following in volume/liquidity order — so the majors are
     * always evaluated even when a cron pass runs out of budget partway
     * through a few hundred alts.
     *
     * @return array<int,array<string,mixed>>
     */
    public function universe(?int $limit = null): array
    {
        $rows = $this->listActive(null, $limit);
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
        return array_merge($pinned, $rest);
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

// ============================================================================
// SECTION 20 — MARKET SCANNER
// ============================================================================

final class MarketScanner
{
    private SymbolRepository $symbolRepo;
    private ScannerRunRepository $runRepo;

    public function __construct()
    {
        $this->symbolRepo = new SymbolRepository();
        $this->runRepo = new ScannerRunRepository();
    }

    /**
     * Ranks candidate symbols across all enabled, healthy exchanges by
     * volume/liquidity/spread/volatility and persists the top N. Never
     * throws — a failing exchange is simply skipped for this run.
     */
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

    /**
     * @param array<int,array{symbol:string,base:string,quote:string,status:string}> $symbols
     * @param array<string,array{volume:float,lastPrice:float,bid:float,ask:float,priceChangePercent:float}> $tickers
     * @return array{candidates:array<int,array{symbol:string,base:string,quote:string,volume:float,liquidity:float,spread:float,volatility:float,rank:int}>, funnel:array<string,int>}
     */
    private function rank(array $symbols, array $tickers): array
    {
        // Compared case-insensitively -- an exchange returning "usdt"
        // while ALLOWED_QUOTE_ASSETS says "USDT" must still match; a
        // strict-case mismatch here silently filters out every symbol.
        $allowedQuotes = array_map('strtoupper', Config::allowedQuoteAssets());
        $minVolume = Config::minVolumeUsdt();
        $maxSpread = Config::maxSpreadPercent();
        $includeStable = Config::includeStablecoinPairs();
        $stableAssets = ['USDT', 'USDC', 'BUSD', 'TUSD', 'DAI', 'FDUSD'];

        $funnel = [
            'total' => count($symbols), 'quote_ok' => 0, 'stable_ok' => 0,
            'has_ticker' => 0, 'volume_ok' => 0, 'spread_ok' => 0,
        ];

        $candidates = [];
        foreach ($symbols as $s) {
            $quote = strtoupper($s['quote']);
            $base = strtoupper($s['base']);
            if (!empty($allowedQuotes) && !in_array($quote, $allowedQuotes, true)) {
                continue;
            }
            $funnel['quote_ok']++;
            if (!$includeStable && in_array($base, $stableAssets, true)) {
                continue;
            }
            $funnel['stable_ok']++;
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
            $volatility = abs($ticker['priceChangePercent']);
            // Liquidity proxy: volume normalized against spread (tighter spread + higher volume = more liquid).
            $liquidity = $spread > 0 ? $ticker['volume'] / $spread : $ticker['volume'];

            $candidates[] = [
                'symbol' => $s['symbol'], 'base' => $s['base'], 'quote' => $s['quote'],
                'volume' => $ticker['volume'], 'liquidity' => $liquidity, 'spread' => $spread,
                'volatility' => $volatility, 'rank' => 0,
            ];
        }

        // Composite rank: volume and liquidity weigh most (as required —
        // priority to higher volume/liquidity), volatility is a tiebreaker.
        usort($candidates, static function ($a, $b) {
            $scoreA = ($a['volume'] * 0.6) + ($a['liquidity'] * 0.3) + ($a['volatility'] * 0.1);
            $scoreB = ($b['volume'] * 0.6) + ($b['liquidity'] * 0.3) + ($b['volatility'] * 0.1);
            return $scoreB <=> $scoreA;
        });

        foreach ($candidates as $i => &$c) {
            $c['rank'] = $i + 1;
        }
        unset($c);

        return ['candidates' => $candidates, 'funnel' => $funnel];
    }

    /**
     * Fresh, direct (circuit-breaker-bypassing) fetch + rank for ONE
     * exchange, returning the funnel counts at every filter stage instead
     * of just the final result — pinpoints exactly which filter
     * (quote asset, stablecoin exclusion, missing ticker match, min
     * volume, max spread) is eliminating every candidate, for the admin
     * panel's scanner diagnostic.
     * @return array{ok:bool, error?:string, funnel?:array<string,int>, sample?:array<int,array<string,mixed>>}
     */
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

// ============================================================================
// SECTION 21 — SIGNAL GENERATOR (orchestrator)
// ============================================================================

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

    /**
     * The strongest setup seen since the last reset, whether or not it was
     * publishable, plus why it was turned down.
     *
     * Without this a quiet bot is indistinguishable from a broken one: the
     * pass just returns null and the operator has no number to act on. With
     * it the panel can say "best of this pass was 28.4 on BTCUSDT, minimum
     * is 45", which is a decision they can actually make.
     *
     * @var array{symbol:string,timeframe:string,score:float,bias:string,reason:string}|null
     */
    private ?array $bestObservation = null;

    public function resetObservations(): void
    {
        $this->bestObservation = null;
    }

    /** @return array{symbol:string,timeframe:string,score:float,bias:string,reason:string}|null */
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

    /**
     * Runs the full structural analysis pipeline for one symbol/timeframe
     * and returns a fully planned but UNSAVED Signal, or null.
     *
     * Nothing is written here on purpose. The worker evaluates every active
     * symbol across every signal timeframe and then publishes only the
     * single best candidate of the pass, so persisting each one as it was
     * produced would fill the signals table with trades that were never
     * sent — and would poison the deduplication window against the
     * candidate that actually won.
     *
     * @param array{base_asset?:string, volume_24h?:float} $meta symbol metadata from the scanner
     */
    public function evaluate(
        MarketSnapshot $snapshot,
        string $timeframe,
        array $meta = [],
        string $strategyName = 'default_structure',
    ): ?Signal {
        $candles = $snapshot->candlesFor($timeframe);
        if (count($candles) < 30) {
            $this->observe($snapshot, $timeframe, 0.0, 'neutral', sprintf('کندل کافی نیست (%d از ۳۰)', count($candles)));
            return null;
        }

        // Don't stack a new signal on a symbol that already has one out
        // whose result isn't known yet -- checked before the detection
        // pipeline runs so a symbol under an open position also skips the
        // wasted computation, not just the dispatch.
        if ($this->signalRepo->hasOpenPosition($snapshot->exchange, $snapshot->symbol)) {
            return null;
        }

        try {
            $zones = $this->srEngine->detect($candles, $timeframe);
            $orderBlocks = $this->obEngine->detect($candles, $timeframe);
            $fvgs = $this->fvgEngine->detect($candles, $timeframe);
            $trend = SwingPivots::structuralTrend($candles);
            $indicatorResults = $this->indicatorEngine->runAll($candles);

            // Write-only tables (nothing in the project reads them back),
            // and a DELETE+INSERT per symbol per timeframe is the single
            // most expensive thing a scan pass can do on SQLite. Opt-in.
            if (Config::persistStructure()) {
                $this->zoneRepo->replaceForSymbol($snapshot->exchange, $snapshot->symbol, $timeframe, $zones);
                $this->obRepo->replaceForSymbol($snapshot->exchange, $snapshot->symbol, $timeframe, $orderBlocks);
                $this->fvgRepo->replaceForSymbol($snapshot->exchange, $snapshot->symbol, $timeframe, $fvgs);
            }

            $confluence = $this->confluenceEngine->score($snapshot, $timeframe, $zones, $orderBlocks, $fvgs, $indicatorResults, $trend);

            $strategy = $this->strategyEngine->get($strategyName);
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
                    $confluence['bias'] === 'neutral' ? 'بازار جهت مشخصی ندارد' : 'ساختار قیمت پلن معامله نداد'
                );
                return null;
            }

            // The strategy supplies direction + structure; the planner turns
            // that into a leveraged, publishable trade (see TradePlanner).
            $plan = $this->planner->plan(
                $setup['direction'],
                (float) $setup['entry'],
                (float) $setup['stop_loss'],
                SwingPivots::atr($candles),
                SymbolClassifier::baseAsset($snapshot->symbol, $meta['base_asset'] ?? null),
                (float) ($meta['volume_24h'] ?? 0.0),
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
            if ($this->deduplicator->isDuplicate($fingerprint, Config::cooldownSeconds())) {
                $this->observe($snapshot, $timeframe, $confluence['score'], $confluence['bias'], 'تکراری است (Cooldown)');
                return null;
            }

            // Calibrated against the conviction scale: 70+ means several factors
            // agree, 55+ means a clear read with partial confluence.
            $confidence = $confluence['score'] >= 70 ? 'high' : ($confluence['score'] >= 55 ? 'medium' : 'low');

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
                tp3: null,
                riskReward: $plan['rr'],
                score: $confluence['score'],
                confidence: $confidence,
                strategy: $strategy->name(),
                reasons: $confluence['reasons'],
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

    /** Writes the chosen candidate and stamps its database id onto it. */
    public function persist(Signal $signal): Signal
    {
        $signal->id = $this->signalRepo->save($signal);
        return $signal;
    }

    /**
     * evaluate() + persist() in one call — the original entry point, kept
     * for the admin panel's "Test Signal" button, which produces exactly
     * one signal and wants it stored.
     */
    public function generate(MarketSnapshot $snapshot, string $timeframe, string $strategyName = 'default_structure'): ?Signal
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

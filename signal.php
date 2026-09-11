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

    /**
     * The exchange's own name for a timeframe, or null when it does not
     * offer one. Not every venue has every interval — HTX has no 2h, for
     * instance — and passing our name through would just spend a request to
     * be told so.
     */
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
        // No-op by default; overridden by adapters that support streaming.
    }
}

// ============================================================================
// SECTION 4 — BINANCE ADAPTER
// ============================================================================

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
            return []; // this exchange has no such interval
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
            return []; // this exchange has no such interval
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
        // MEXC v3 WS: subscribe via a JSON control message per channel.
        // Channel names below follow MEXC's documented spot v3 WS schema at
        // time of writing; if MEXC revises channel naming this call fails
        // soft (no messages arrive) and the adapter keeps working over REST.
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
            return []; // this exchange has no such interval
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
            return []; // this exchange has no such interval
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
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return []; // this exchange has no such interval
        }
        RateLimiter::acquire('htx', 600);
        $url = Config::htxRestBase() . '/market/history/kline?' . http_build_query([
            'symbol' => $symbol,
            'period' => $interval,
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
        $interval = $this->mapTimeframe($timeframe);
        if ($interval === null) {
            return []; // this exchange has no such interval
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
            return []; // this exchange has no such interval
        }
        RateLimiter::acquire('bybit', 600);
        $url = Config::bybitRestBase() . '/v5/market/kline?' . http_build_query([
            'category' => 'spot', 'symbol' => $symbol, 'interval' => $interval, 'limit' => $limit,
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
            return []; // this exchange has no such interval
        }
        RateLimiter::acquire('okx', 600);
        $url = Config::okxRestBase() . '/api/v5/market/candles?' . http_build_query([
            'instId' => $symbol, 'bar' => $interval, 'limit' => $limit,
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
            return []; // this exchange has no such interval
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
// SECTION 10.4 — TA TOOLBOX
//
// The Pine built-ins the ported indicators lean on. Everything returns a full
// series (oldest-first, aligned with the candle array) so a caller can look
// back, but the strategy layer only ever reads the last few values: Pine
// recomputes on every bar because it draws history, while this engine only
// needs to know what is true right now.
// ============================================================================

final class Ta
{
    /** @param float[] $src @return float[] */
    public static function sma(array $src, int $len): array
    {
        $out = [];
        $sum = 0.0;
        $n = count($src);
        for ($i = 0; $i < $n; $i++) {
            $sum += $src[$i];
            if ($i >= $len) {
                $sum -= $src[$i - $len];
            }
            $out[$i] = $i >= $len - 1 ? $sum / $len : NAN;
        }
        return $out;
    }

    /** @param float[] $src @return float[] */
    public static function ema(array $src, int $len): array
    {
        $out = [];
        $k = 2 / ($len + 1);
        $prev = null;
        foreach ($src as $i => $v) {
            if ($prev === null) {
                // Pine seeds the EMA with an SMA of the first `len` values.
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

    /**
     * Arnaud Legoux MA — the "trend wave" of the SMC indicator. Gaussian
     * weights offset toward the recent end, which is what makes it hug price
     * more tightly than an EMA without the lag.
     *
     * @param float[] $src
     * @return float[]
     */
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

    /** Wilder's RSI. @param float[] $src @return float[] */
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

    /** @param float[] $src */
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

    /**
     * Session-anchored VWAP. Crypto has no session, so the anchor is UTC
     * midnight — the same boundary the funding and daily candle use.
     *
     * @param Candle[] $candles
     * @return float[]
     */
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

    /** Slope of a linear regression over the last $len values. @param float[] $src */
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

    /** Linear-interpolated percentile of the last $len values. @param float[] $src */
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

    /** Where $value ranks inside $series, as a percentage. @param float[] $series */
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

    /** @param Candle[] $candles */
    public static function highest(array $candles, int $len, int $offset = 0): float
    {
        $slice = array_slice($candles, -($len + $offset), $len);
        $out = -INF;
        foreach ($slice as $c) {
            $out = max($out, $c->high);
        }
        return $out === -INF ? NAN : $out;
    }

    /** @param Candle[] $candles */
    public static function lowest(array $candles, int $len, int $offset = 0): float
    {
        $slice = array_slice($candles, -($len + $offset), $len);
        $out = INF;
        foreach ($slice as $c) {
            $out = min($out, $c->low);
        }
        return $out === INF ? NAN : $out;
    }

    /** @param Candle[] $candles @return float[] */
    public static function closes(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->close, $candles);
    }

    /** @param Candle[] $candles @return float[] */
    public static function volumes(array $candles): array
    {
        return array_map(static fn(Candle $c) => $c->volume, $candles);
    }

    /**
     * Pine's ta.pivothigh/ta.pivotlow over an arbitrary series: index $i is a
     * pivot when it is the strict extreme of the $left+$right window centred
     * on it.
     *
     * @param float[] $src
     * @return array<int,int> pivot indices, oldest first
     */
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
}

// ============================================================================
// SECTION 10.5 — MARKET STRUCTURE (CHoCH / BOS / range break / basing)
//
// The swing sequence read the way a price-action trader reads it:
//
//   BOS   — a break in the SAME direction as the prevailing swing sequence.
//           Continuation.
//   CHoCH — a break AGAINST it: the first higher high after a run of lower
//           highs, or the first lower low after a run of higher lows. This
//           is the change of character, and it is the earliest structural
//           evidence that the previous move is over.
//   BREAK — the same event out of a sideways range: no trend to continue
//           or reverse, price simply leaves the box.
//
// Pivots are found with a SMALL lookback on purpose: the setups asked for
// here are "a little ceiling breaks" and "a little floor breaks", which a
// 5-bar swing filter would smooth away entirely.
// ============================================================================

final class MarketStructure
{
    /**
     * @param Candle[] $candles oldest-first
     * @return array{
     *   trend:string, event:?string, direction:?string, level:?float,
     *   swing_high:?float, swing_low:?float, break_index:?int,
     *   range_position:float, basing:bool, run_pct:float, drop_pct:float,
     *   break_volume_ratio:float
     * }
     */
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

        // The break itself, in the last few closed candles only — an event
        // from twenty bars ago is history, not a signal.
        $event = null;
        $direction = null;
        $level = null;
        $breakIndex = null;
        $volumeRatio = 0.0;

        // Newest first: when a window contains both a break up and a break
        // back down — which is exactly what a reversal looks like — the
        // later one is the one that is still true.
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

        // Walk back to the candle that actually did the breaking. Follow-
        // through bars also close beyond the level, and taking the newest
        // of them would measure the volume of the follow-through and read
        // the pre-break context one bar too late.
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

        // Everything below describes the market AS IT WAS GOING INTO the
        // break, so the context window stops at the breaking candle. Measured
        // to the last candle instead, a breakout always looks "extended" and
        // a base never looks like one — the break itself moved price to the
        // top of its own range.
        $contextEnd = $breakIndex ?? $n;
        $contextEnd = max(12, $contextEnd);
        $close = $candles[$contextEnd - 1]->close;

        // Tight, quiet range = a base, whatever part of the bigger picture
        // it sits in.
        $baseWindow = array_slice($candles, max(0, $contextEnd - 40), min(40, $contextEnd));
        $baseHigh = max(array_map(static fn(Candle $c) => $c->high, $baseWindow));
        $baseLow = min(array_map(static fn(Candle $c) => $c->low, $baseWindow));
        $recentAtr = SwingPivots::atr(array_slice($candles, max(0, $contextEnd - 14), min(14, $contextEnd)), 14);
        $priorAtr = SwingPivots::atr(array_slice($candles, max(0, $contextEnd - 40), min(20, max(0, $contextEnd - 20))), 14);
        $spanPct = $close > 0 ? (($baseHigh - $baseLow) / $close) * 100 : 100.0;
        $basing = $spanPct <= Config::baseRangePercent() && $priorAtr > 0 && $recentAtr <= $priorAtr * 0.9;

        // Where the break started from, measured against a much longer
        // window: "am I buying the bottom of the picture or the top of it".
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

// ============================================================================
// SECTION 10.7 — LIQUIDITY, SUPPLY/DEMAND, RANGES AND THE TREND WAVE
//
// Ports of the six indicators supplied, reduced to what a signal actually
// needs. Pine recalculates on every bar because it draws a chart; these run
// one pass over the candle array and report the state that is true NOW —
// which is the only state a trade is taken from, and the difference between
// a pass that fits in a cron minute and one that does not.
// ============================================================================

/** A resting pool of stop orders: a swing high/low, session extreme or PDH/PDL. */
final class LiquidityLevel
{
    public function __construct(
        public readonly float $price,
        public readonly bool $isHigh,
        public readonly string $origin,   // '1h' | 'Asia' | 'PDH' | ...
        public readonly int $bornIndex,
        public bool $swept = false,
        public int $sweptIndex = -1,
    ) {
    }
}

/**
 * MTF Liquidity Stack (Zeiierman), ported.
 *
 * Liquidity is where stops sit: under swing lows, over swing highs, at the
 * previous day's extremes and at session highs/lows. Two things matter to a
 * trade — untapped liquidity ahead is where price is drawn (a target), and a
 * level that has just been swept and rejected is a reversal trigger.
 */
final class LiquidityEngine
{
    /**
     * @param Candle[] $candles oldest-first
     * @return array{levels:LiquidityLevel[], recent_sweep:?array{price:float,is_high:bool,index:int,origin:string}}
     */
    public function detect(array $candles, int $keepPerSide = 6, int $pivotLen = 3, int $sweepAge = 4): array
    {
        $n = count($candles);
        if ($n < $pivotLen * 2 + 6) {
            return ['levels' => [], 'recent_sweep' => null];
        }

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);

        /** @var LiquidityLevel[] $levels */
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

        // Sweep detection: a level is taken once price trades through it
        // AFTER it formed. The original marks these "mitigated" and stops
        // drawing them; here the sweep itself is the interesting event.
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
                // A sweep only counts as a reversal cue if the candle CLOSED
                // back on the right side of the level: through and gone is a
                // break, through and rejected is a sweep.
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

        // Only untapped levels are worth keeping as targets, newest first.
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

    /**
     * Previous day's high and low. The original also tracks Asia / London /
     * New York sessions; on a 24h crypto market the daily extremes are the
     * levels that actually hold stops, and they cost one pass to find.
     *
     * @param Candle[] $candles
     * @return LiquidityLevel[]
     */
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

/** A momentum-born supply or demand zone. */
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

/**
 * Supply & Demand (MTF) — Flux Charts, ported.
 *
 * A zone is born where a run of momentum candles left from: several
 * consecutive bodies at least half the average size, all in one direction.
 * The zone is the candle that started the run, clamped to 1.5 ATR so one
 * outsized bar cannot claim half the chart, and it dies when price closes
 * through the far side.
 */
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

    /**
     * @param Candle[] $candles oldest-first
     * @return SupplyDemandZone[] newest first, unbroken only
     */
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

        /** @var SupplyDemandZone[] $zones */
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

        // Invalidation: a demand zone dies when price closes below its
        // bottom, supply when price closes above its top.
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

/**
 * Auto Range Detector (QuantAlgo), ported.
 *
 * A range is not "price went sideways for a bit" — it has to pass five
 * tests: the band is compressed relative to ATR, price rotated across the
 * midpoint often enough, both boundaries were touched, the band has little
 * drift, and price was actually contained inside it. That is what separates
 * a tradable box from a slow trend, and it is why the breakout of one is
 * worth taking.
 *
 * @phpstan-type RangeState array{qualified:bool, top:float, bottom:float, bars:int, broke:?string}
 */
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

    /**
     * @param Candle[] $candles oldest-first
     * @return array{qualified:bool, top:?float, bottom:?float, length:int, break:?string, position:float}
     */
    public function detect(array $candles): array
    {
        $none = ['qualified' => false, 'top' => null, 'bottom' => null, 'length' => 0, 'break' => null, 'position' => 0.5];
        $n = count($candles);
        $atr = SwingPivots::atr($candles);
        if ($atr <= 0 || $n < $this->baseLength * 3 + 10) {
            return $none;
        }

        // The box is measured from a window that STOPS SHORT of the last few
        // candles, and the break is then tested against those.
        //
        // The original carries the range forward as state: it is established
        // first, and price leaves it later. Re-deriving the box from a
        // window that already contains the breakout is not the same thing —
        // the impulse widens the band and ruins the containment, so the one
        // moment the range matters is the one moment it stops qualifying.
        $hold = Config::rangeBreakLookback();
        $body = array_slice($candles, 0, $n - $hold);
        if (count($body) < $this->baseLength + 5) {
            return $none;
        }

        // The original scans three nested windows and prefers the widest that
        // qualifies — a big slow box beats the small one inside it.
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

    /**
     * @param Candle[] $candles
     * @return array{qualified:bool, top:float, bottom:float}
     */
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

        // Compression: band height against what ATR says a random walk of
        // this length would cover. Ranked against the recent past, because
        // "tight" only means anything relative to this symbol's own history.
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
        // Two ways to be compressed, and either will do.
        //
        // The rank is the original's test: tight COMPARED TO this symbol's
        // recent past. On its own it has a blind spot — a coin that has been
        // ranging for the whole lookback ranks every window the same, so
        // nothing ever qualifies and the most obvious box on the chart is
        // invisible. The absolute measure has no such problem: height over
        // ATR*sqrt(len) is ~1.0 for a random walk by construction, so
        // anything well under that is a range whatever came before it.
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

/**
 * SMC Institutional Clean Wave, ported.
 *
 * The ALMA wave gives a trend read that is not a lagging crossover, and the
 * BOS/CHoCH engine is the same idea as MarketStructure's but anchored on the
 * LAST pivot rather than the last two: it fires on the crossover itself,
 * which is earlier, and it tracks its own direction so it can tell a
 * continuation from a change of character.
 */
final class TrendWave
{
    /**
     * @param Candle[] $candles oldest-first
     * @return array{
     *   direction:string, wave:?float, distance_atr:float,
     *   event:?string, event_dir:?string, event_index:?int,
     *   sd_target:?float, inside_bar:bool
     * }
     */
    public static function analyse(array $candles, int $waveLength = 21, int $sensitivity = 7, int $sdLength = 20): array
    {
        $n = count($candles);
        $out = [
            'direction' => 'neutral', 'wave' => null, 'distance_atr' => 0.0,
            'event' => null, 'event_dir' => null, 'event_index' => null,
            'sd_target' => null, 'inside_bar' => false,
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

        // Inside bar / consolidation, the indicator's "smart candle" colour.
        $out['inside_bar'] = $candles[$last]->high <= $candles[$last - 1]->high
            && $candles[$last]->low >= $candles[$last - 1]->low;

        // -2.5 standard deviations below the mean: the original's "next
        // target" line, useful as a downside objective.
        $mean = array_sum(array_slice($closes, -$sdLength)) / $sdLength;
        $out['sd_target'] = $mean - Ta::stdev($closes, $sdLength) * 2.5;

        // BOS / CHoCH by crossover of the most recent confirmed pivot.
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
        return $out;
    }
}

/**
 * Dynamic Deviation Channels (RSI Trigger) [ChartPrime], ported.
 *
 * An EMA midline with ATR-scaled bands around it, and a smoothed RSI that
 * decides which side of the channel is live: above 50 the market is working
 * the upper bands, below 50 the lower ones. The trade is a cross back
 * through the first band — mean reversion with a momentum filter on it,
 * rather than "price touched a band".
 */
final class DeviationChannel
{
    /**
     * @param Candle[] $candles oldest-first
     * @return array{
     *   mid:?float, upper1:?float, lower1:?float, rsi:?float,
     *   signal:?string, mid_rising:bool, mid_falling:bool
     * }
     */
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

        // The original's volatility measure: a long ATR, deliberately slow,
        // so the bands describe the regime rather than the last few bars.
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

        // RSI gates which side is even eligible, exactly as the original
        // only plots one set of bands at a time.
        if ($rsiNow < 50 && $closes[$last] > $lower1 && $closes[$last - 1] <= $lowerPrev) {
            $out['signal'] = 'long';
        } elseif ($rsiNow >= 50 && $closes[$last] < $upper1 && $closes[$last - 1] >= $upperPrev) {
            $out['signal'] = 'short';
        }

        return $out;
    }
}

/**
 * The SMC vocabulary the two Smart-Money suites share: the dealing range and
 * where price sits in it, the optimal-trade-entry pocket, equal highs and
 * lows, and the micro structure inside the macro one.
 *
 * These are not triggers. They are the context that decides whether a
 * trigger is worth taking — buying in premium and selling in discount is
 * the single most common way a good entry signal still loses.
 */
final class SmcContext
{
    /**
     * @param Candle[] $candles oldest-first
     * @return array{
     *   range_high:?float, range_low:?float, equilibrium:?float,
     *   zone:string, position:float,
     *   ote_high:?float, ote_low:?float, in_ote:bool, leg:?string,
     *   eqh:?float, eql:?float,
     *   internal_event:?string, internal_dir:?string
     * }
     */
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

        // -- the dealing range, and which half of it price is in -----------
        $window = array_slice($candles, -$rangeLookback);
        $rangeHigh = max(array_map(static fn(Candle $c) => $c->high, $window));
        $rangeLow = min(array_map(static fn(Candle $c) => $c->low, $window));
        $span = $rangeHigh - $rangeLow;
        $out['range_high'] = $rangeHigh;
        $out['range_low'] = $rangeLow;
        $out['equilibrium'] = ($rangeHigh + $rangeLow) / 2;
        $out['position'] = $span > 0 ? ($close - $rangeLow) / $span : 0.5;
        $out['zone'] = $out['position'] > 0.5 ? 'premium' : 'discount';

        // -- OTE: the 62-79% retracement of the most recent leg -------------
        $phs = Ta::pivots($highs, $swingLen, $swingLen, true);
        $pls = Ta::pivots($lows, $swingLen, $swingLen, false);
        $lastPh = empty($phs) ? null : $phs[count($phs) - 1];
        $lastPl = empty($pls) ? null : $pls[count($pls) - 1];

        if ($lastPh !== null && $lastPl !== null) {
            if ($lastPl > $lastPh) {
                // Down leg: high then low. The retracement to sell is
                // measured back UP from the low.
                $legHigh = $highs[$lastPh];
                $legLow = $lows[$lastPl];
                $out['leg'] = 'down';
                $out['ote_low'] = $legLow + ($legHigh - $legLow) * 0.62;
                $out['ote_high'] = $legLow + ($legHigh - $legLow) * 0.79;
            } else {
                // Up leg: low then high. The retracement to buy is measured
                // back DOWN from the high.
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

        // -- equal highs / lows: stop pools that have been built twice -------
        $tolerance = $atr * 0.10;
        if (count($phs) >= 2 && abs($highs[$phs[count($phs) - 1]] - $highs[$phs[count($phs) - 2]]) <= $tolerance) {
            $out['eqh'] = max($highs[$phs[count($phs) - 1]], $highs[$phs[count($phs) - 2]]);
        }
        if (count($pls) >= 2 && abs($lows[$pls[count($pls) - 1]] - $lows[$pls[count($pls) - 2]]) <= $tolerance) {
            $out['eql'] = min($lows[$pls[count($pls) - 1]], $lows[$pls[count($pls) - 2]]);
        }

        // -- internal (micro) structure -------------------------------------
        // The same crossover engine on a much smaller pivot: an iBOS is the
        // first evidence a macro leg is resuming, and it fires well before
        // the swing structure confirms.
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

/**
 * Setup Scanner [GBB], ported.
 *
 * Six independent entry triggers. Each answers one question — has price
 * reclaimed VWAP, pulled back into the EMA band, retested a broken level,
 * swept a stop pool and rejected, diverged from RSI, or broken the opening
 * range — and each is reported separately so the strategy above can require
 * agreement rather than trusting any single one.
 *
 * The original's volume gate is kept where the original had it: on the
 * setups where participation is part of the thesis (VWAP, EMA, sweep) and
 * deliberately not on the others, where a quiet bar is normal.
 */
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

    /**
     * @param Candle[] $candles oldest-first
     * @return array{
     *   long:array<int,string>, short:array<int,string>,
     *   trend:string, atr:float, rsi:float, vwap:?float, volume_ok:bool
     * }
     */
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

        // -- trend, by the 21/50 relationship -------------------------------
        $trendUp = !is_nan($ema21[$last]) && !is_nan($ema50[$last])
            && $ema21[$last] > $ema50[$last] && $ema50[$last] > ($ema50[$last - 3] ?? $ema50[$last]);
        $trendDown = !is_nan($ema21[$last]) && !is_nan($ema50[$last])
            && $ema21[$last] < $ema50[$last] && $ema50[$last] < ($ema50[$last - 3] ?? $ema50[$last]);
        $out['trend'] = $trendUp ? 'bullish' : ($trendDown ? 'bearish' : 'neutral');

        // -- 1. VWAP reclaim -------------------------------------------------
        // Price spent real time on one side of VWAP, then closed back across.
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

        // -- 2. EMA pullback --------------------------------------------------
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

        // -- 3. Break and retest ----------------------------------------------
        $retest = $this->breakAndRetest($candles, $atr);
        if ($retest === 'long') {
            $out['long'][] = self::BREAK_RETEST;
        } elseif ($retest === 'short') {
            $out['short'][] = self::BREAK_RETEST;
        }

        // -- 4. Liquidity sweep reversal ---------------------------------------
        // A wick takes out a recent extreme and the body closes back inside,
        // with the rejection wick larger than the body's own follow-through.
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

        // -- 5. RSI divergence --------------------------------------------------
        $divergence = $this->divergence($candles, $rsi);
        if ($divergence === 'long') {
            $out['long'][] = self::DIVERGENCE;
        } elseif ($divergence === 'short') {
            $out['short'][] = self::DIVERGENCE;
        }

        // -- 6. Sweep, then the big move ----------------------------------------
        $bigMove = $this->sweepBigMove($candles);
        if ($bigMove === 'long') {
            $out['long'][] = self::BIG_MOVE;
        } elseif ($bigMove === 'short') {
            $out['short'][] = self::BIG_MOVE;
        }

        // -- 7. Deviation channel reversion --------------------------------------
        $channel = DeviationChannel::analyse($candles);
        if ($channel['signal'] === 'long') {
            $out['long'][] = self::CHANNEL;
        } elseif ($channel['signal'] === 'short') {
            $out['short'][] = self::CHANNEL;
        }

        return $out;
    }

    /**
     * Liquidity Sweep Before The Big Move, ported.
     *
     * Two separate events, in order: price takes out the N-bar extreme and
     * closes back inside it leaving a real wick, and THEN, within a few
     * bars, a strong-bodied candle closes beyond the previous bar's range.
     *
     * The sweep alone is where most people enter and get run over again;
     * requiring the second candle is what makes it the start of a move
     * rather than a guess at one.
     *
     * @param Candle[] $candles
     * @return string|null 'long'|'short'|null
     */
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

        // The confirmation candle has to be doing the work itself.
        $strongBull = $c->close > $c->open && $bodyRatio >= $bodyStrength && $c->close > $prev->high;
        $strongBear = $c->close < $c->open && $bodyRatio >= $bodyStrength && $c->close < $prev->low;
        if (!$strongBull && !$strongBear) {
            return null;
        }

        // Look back through the confirmation window for the sweep that set
        // this up, measuring each candidate against the extreme as it stood
        // BEFORE that candle.
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

    /**
     * A confirmed swing is broken, price returns to it within the window,
     * holds, and closes away again.
     *
     * @param Candle[] $candles
     * @return string|null 'long'|'short'|null
     */
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

        // The most recent confirmed swing high that price has already broken.
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

    /**
     * Regular RSI divergence between the last two confirmed pivots. Late by
     * construction — the pivot needs its right-hand bars to confirm — which
     * is exactly why it is one vote and never the whole decision.
     *
     * @param Candle[] $candles
     * @param float[] $rsi
     */
    private function divergence(array $candles, array $rsi): ?string
    {
        $pivotLen = Config::setupPivotLength();
        $gap = Config::divergenceGap();
        $n = count($candles);

        $highs = array_map(static fn(Candle $c) => $c->high, $candles);
        $lows = array_map(static fn(Candle $c) => $c->low, $candles);
        $ph = Ta::pivots($highs, $pivotLen, $pivotLen, true);
        $pl = Ta::pivots($lows, $pivotLen, $pivotLen, false);

        // Only a divergence confirmed in the last few bars is actionable.
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

/**
 * Order Block Detector [LuxAlgo], ported, replacing the earlier
 * displacement-candle rule.
 *
 * The difference that matters: a block is anchored to a pivot in VOLUME, not
 * in price. The candle that printed a local volume peak is where the size
 * actually traded, and the directional state (has the market since made a
 * new extreme, or given one up) decides whether that candle left demand or
 * supply behind. A big candle with no volume behind it is displacement
 * without participation, and that is what the old rule kept finding.
 */
final class OrderBlockEngine
{
    public function __construct(
        private int $length = 5,
        private bool $mitigateOnClose = false,
    ) {
    }

    /** @param Candle[] $candles oldest-first @return OrderBlock[] */
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
        $state = 0;   // 0 = market gave up a high, 1 = market gave up a low

        for ($i = $len; $i < $n; $i++) {
            // Rolling extremes of the window ending at $i.
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

            // A volume pivot confirms $len bars after the fact, so the block
            // it marks is the candle at $i - $len.
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

        // Newest first and capped, the way the original only ever draws its
        // last few: an order block from 300 candles ago that price has not
        // returned to is not what the next trade is taken against.
        usort($blocks, static fn(OrderBlock $a, OrderBlock $b) => $b->createdAt <=> $a->createdAt);
        return array_slice($blocks, 0, 12);
    }

    /**
     * A bullish block dies when price trades (or closes) below its low, a
     * bearish one when price takes out its high.
     *
     * @param OrderBlock[] $blocks
     * @param Candle[] $candles
     * @return OrderBlock[]
     */
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

    /** @param float[] $volumes */
    private function averageVolume(array $volumes, int $upTo): float
    {
        $slice = array_slice($volumes, max(0, $upTo - 20), 20);
        return empty($slice) ? 0.0 : array_sum($slice) / count($slice);
    }
}

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

    /**
     * Why the last evaluate() returned null, in Persian, for the panel's
     * "why is there no signal" report. A strict strategy that never says
     * which of its gates it failed is impossible to tune.
     */
    public function lastRejection(): ?string;
}

/**
 * Default reference strategy built only from the structural inputs this
 * project explicitly commissions (S/R, Order Blocks, FVG, Confluence).
 * Swappable/disable-able per `strategies.is_enabled` — final strategy
 * rules are pending the user's exact specification.
 */
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

/**
 * The operator's own playbook, in two named setups.
 *
 *   BREAKOUT  a coin sitting on its floor, quiet, that takes out a small
 *             ceiling — accumulation ending. Long.
 *   REVERSAL  a coin (memecoins especially) that has already run hard and
 *             then prints a CHoCH down or loses a small floor —
 *             distribution starting. Short.
 *
 * Both are the same structural event read in opposite contexts, which is
 * why they share one detector. What separates a tradable one from a trap
 * is the gate underneath: the break must carry volume, must not already be
 * extended, must have an order block or FVG behind it to put the stop
 * against, and must have clear air to the first target. Every one of those
 * rejections is recorded, so "why no signal" stays answerable.
 */
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

    /** Records why this candidate was dropped and returns null, in one step. */
    private function reject(string $why): null
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

        // -- context: which of the two setups is this? ---------------------
        $setup = null;
        if ($isLong && ($ms['basing'] || $ms['range_position'] <= 0.55)) {
            $setup = 'breakout';
        } elseif (!$isLong && ($ms['run_pct'] >= Config::reversalRunPercent() || $ms['range_position'] >= 0.55)) {
            $setup = 'reversal';
        }
        if ($setup === null) {
            // A long bought at the top of the range, or a short sold at the
            // bottom of one. Both are the wrong end of the move.
            return $this->reject($isLong
                ? 'شکست رو به بالا ولی قیمت در سقف محدوده است، نه در کف'
                : 'برگشت نزولی ولی ارز رشد قابل‌توجهی نکرده بود');
        }

        // -- the gate ------------------------------------------------------
        if ($ms['break_volume_ratio'] < Config::breakVolumeRatio()) {
            return $this->reject(sprintf(
                'حجم پشت شکست کم بود (%.1f برابر میانگین، حداقل %.1f)',
                $ms['break_volume_ratio'],
                Config::breakVolumeRatio()
            ));
        }
        // Chasing: price has already travelled too far past the level for
        // the stop that level implies to still be worth the target.
        if (abs($price - $ms['level']) > $atr * Config::maxChaseAtr()) {
            return $this->reject('قیمت از سطح شکست خیلی دور شده — ورود در این نقطه دنبال‌کردن بازار است');
        }
        // Confluence may abstain, but it may not disagree.
        $wanted = $isLong ? 'bullish' : 'bearish';
        if ($confluence['bias'] !== 'neutral' && $confluence['bias'] !== $wanted) {
            return $this->reject('جهت شکست با مجموع اندیکاتورها و ساختار هم‌خوان نیست');
        }

        // -- where the stop goes -------------------------------------------
        // Behind the structure that produced the break, and behind any
        // order block or FVG guarding it — that zone is the level the
        // market has to give back for the idea to be wrong.
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
            'tp1' => null,   // the planner sets both targets from the risk budget
            'tp2' => null,
            'tp3' => null,
            'setup' => $setup,
            'event' => $ms['event'],
        ];
    }

    /**
     * The nearest unmitigated order block or FVG on the protective side of
     * price — the zone a stop can legitimately hide behind.
     *
     * @param OrderBlock[] $orderBlocks
     * @param Fvg[] $fvgs
     */
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

/**
 * The combination strategy: every indicated module gets a vote, and a trade
 * is only taken when enough independent ones agree.
 *
 * The six ported indicators answer different questions, and that is the
 * point — a break with no volume behind it, a pullback with no zone under
 * it, or a sweep against the higher-timeframe trend are each the kind of
 * setup that looks perfect alone and loses in a series. So:
 *
 *   TRIGGER   at least one of the six entry setups, a qualified range
 *             breakout, or a structure shift (BOS / CHoCH) fires.
 *   ZONE      the entry sits at an order block or a supply/demand zone, so
 *             the stop has something real to hide behind.  [required]
 *   TREND     the ALMA wave and the 21/50 relationship are not against it.
 *   LIQUIDITY a stop pool was just swept in our favour, or there is untapped
 *             liquidity ahead for price to run at.
 *
 * The score is the weighted sum of what agreed. It is reported, so the panel
 * can say exactly which votes a rejected setup was missing.
 */
final class ConfluenceProStrategy implements Strategy
{
    private ?string $rejection = null;
    private LiquidityEngine $liquidity;
    private SupplyDemandEngine $supplyDemand;
    private RangeEngine $ranges;
    private SetupScanner $setups;

    /** @var array<string,mixed> the last evaluation's vote breakdown, for the panel */
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

    /** @return array<string,mixed> */
    public function lastVotes(): array
    {
        return $this->lastVotes;
    }

    private function reject(string $why): null
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

        // -- run every module once ------------------------------------------
        $setups = $this->setups->scan($candles);
        $wave = TrendWave::analyse($candles);
        $range = $this->ranges->detect($candles);
        $liquidity = $this->liquidity->detect($candles);
        $sdZones = $this->supplyDemand->detect($candles);
        $structure = MarketStructure::analyse($candles);
        $smc = SmcContext::analyse($candles);
        $htf = $this->higherTimeframeBias($snapshot, $timeframe);

        // -- who wants to be long, who wants to be short? --------------------
        $reasons = ['long' => [], 'short' => []];
        $votes = ['long' => 0.0, 'short' => 0.0];

        foreach ($setups['long'] as $setup) {
            $votes['long'] += Config::setupWeight();
            $reasons['long'][] = self::setupFa($setup);
        }
        foreach ($setups['short'] as $setup) {
            $votes['short'] += Config::setupWeight();
            $reasons['short'][] = self::setupFa($setup);
        }

        if ($range['qualified'] && $range['break'] !== null) {
            $side = $range['break'] === 'up' ? 'long' : 'short';
            $votes[$side] += Config::rangeWeight();
            $reasons[$side][] = 'شکست محدوده رنج تاییدشده';
        }

        if ($structure['event'] !== null && $structure['direction'] !== null) {
            $side = $structure['direction'] === 'bullish' ? 'long' : 'short';
            $votes[$side] += Config::structureWeight();
            $reasons[$side][] = $structure['event'] === 'choch'
                ? 'تغییر کاراکتر ساختار (CHoCH)'
                : ($structure['event'] === 'bos' ? 'شکست ساختار (BOS)' : 'شکست محدوده');
        }

        if ($wave['event'] !== null && $wave['event_dir'] !== null && $wave['event_index'] !== null
            && (count($candles) - 1 - $wave['event_index']) <= Config::structureMaxAge()) {
            $side = $wave['event_dir'] === 'bullish' ? 'long' : 'short';
            $votes[$side] += Config::structureWeight();
            $reasons[$side][] = strtoupper($wave['event']) . ' روی موج روند';
        }

        if ($smc['internal_event'] !== null && $smc['internal_dir'] !== null) {
            $sideI = $smc['internal_dir'] === 'bullish' ? 'long' : 'short';
            $votes[$sideI] += Config::internalStructureWeight();
            $reasons[$sideI][] = 'شکست ساختار داخلی (iBOS)';
        }

        $sweep = $liquidity['recent_sweep'];
        if ($sweep !== null) {
            // A swept high traps late longs and is a SHORT cue, and vice
            // versa — the pool is gone and price rejected away from it.
            $side = $sweep['is_high'] ? 'short' : 'long';
            $votes[$side] += Config::liquidityWeight();
            $reasons[$side][] = 'جاروی نقدینگی روی ' . ($sweep['is_high'] ? 'سقف' : 'کف') . ' و برگشت';
        }

        // -- pick a side ------------------------------------------------------
        $direction = null;
        if ($votes['long'] > $votes['short'] && $votes['long'] > 0) {
            $direction = Direction::LONG;
        } elseif ($votes['short'] > $votes['long'] && $votes['short'] > 0) {
            $direction = Direction::SHORT;
        }
        if ($direction === null) {
            return $this->reject($votes['long'] > 0 || $votes['short'] > 0
                ? 'سیگنال‌های خرید و فروش هم‌وزن شدند — بازار تصمیم نگرفته'
                : 'هیچ‌کدام از شش ستاپ فعال نشد');
        }

        $isLong = $direction === Direction::LONG;
        $side = $isLong ? 'long' : 'short';
        $score = $votes[$side];
        $why = $reasons[$side];

        // -- the trend must not be against us ---------------------------------
        $waveAgainst = $wave['direction'] !== 'neutral' && $wave['direction'] !== ($isLong ? 'bullish' : 'bearish');
        $emaAgainst = $setups['trend'] !== 'neutral' && $setups['trend'] !== ($isLong ? 'bullish' : 'bearish');
        // A sweep reversal is ALLOWED to trade against the wave — that is
        // what a reversal is. Everything else is not.
        $reversal = $sweep !== null && ($sweep['is_high'] !== $isLong);
        if (($waveAgainst || $emaAgainst) && !$reversal) {
            return $this->reject('جهت ستاپ برخلاف روند موج/میانگین‌هاست');
        }
        if (!$waveAgainst && !$emaAgainst) {
            $score += Config::trendWeight();
            $why[] = 'هم‌جهت با روند موج و میانگین‌ها';
        }

        // -- a zone to put the stop behind [required] --------------------------
        $guard = $this->guardLevel($isLong, $price, $atr, $orderBlocks, $fvgs, $sdZones);
        if ($guard === null) {
            return $this->reject('اوردر بلاک، FVG یا ناحیه عرضه/تقاضایی نزدیک قیمت نبود');
        }
        $score += Config::zoneWeight();
        $why[] = $guard['label'];

        // -- untapped liquidity ahead ------------------------------------------
        $target = $this->liquidityTarget($isLong, $price, $liquidity['levels']);
        // Equal highs and lows are liquidity built twice over — the strongest
        // magnets on the chart, so they outrank an ordinary swing as a target.
        $equal = $isLong ? $smc['eqh'] : $smc['eql'];
        if ($equal !== null && ($isLong ? $equal > $price : $equal < $price)) {
            $target = $target === null
                ? $equal
                : ($isLong ? min($target, $equal) : max($target, $equal));
            $why[] = $isLong ? 'سقف‌های برابر (EQH) بالای قیمت' : 'کف‌های برابر (EQL) زیر قیمت';
        }
        if ($target !== null) {
            $score += Config::liquidityWeight();
            $why[] = 'نقدینگی دست‌نخورده در مسیر هدف';
        }

        // -- premium / discount ---------------------------------------------
        //
        // Buying in the top half of the dealing range is the classic way a
        // good trigger still loses — but only for a PULLBACK entry. A
        // breakout is in the top half by definition: that is what breaking
        // out means. Applying the rule to both trade families would refuse
        // every continuation trade the engine can find.
        //
        // So the trade is classified first, and the rule is applied to the
        // family it actually belongs to.
        $continuation = $this->isContinuation($setups[$side], $range, $structure, $smc);
        $wrongHalf = $isLong ? $smc['zone'] === 'premium' : $smc['zone'] === 'discount';

        if (!$continuation) {
            if ($wrongHalf && Config::requireDiscountPremium() && !$reversal) {
                return $this->reject($isLong
                    ? 'ورود پولبکی در نیمه بالای محدوده (Premium) — خرید اینجا گران است'
                    : 'ورود پولبکی در نیمه پایین محدوده (Discount) — فروش اینجا ارزان است');
            }
            if (!$wrongHalf) {
                $score += Config::premiumDiscountWeight();
                $why[] = $isLong ? 'ورود از ناحیه ارزان (Discount)' : 'ورود از ناحیه گران (Premium)';
            }
        }

        // -- optimal trade entry ----------------------------------------------
        // OTE is a retracement pocket, so it only means anything on a
        // pullback entry either.
        if ($smc['in_ote'] && !$continuation) {
            $score += Config::oteWeight();
            $why[] = 'قیمت داخل ناحیه ورود بهینه (OTE ۶۲-۷۹٪)';
        }

        // -- higher timeframe --------------------------------------------------
        if ($htf !== null) {
            if ($htf === ($isLong ? 'bullish' : 'bearish')) {
                $score += Config::htfWeight();
                $why[] = 'هم‌جهت با تایم‌فریم بالاتر';
            } elseif (Config::requireHtfAlignment() && !$reversal) {
                return $this->reject('تایم‌فریم بالاتر خلاف جهت این معامله است');
            }
        }

        if ($score < Config::minConfluenceScore()) {
            return $this->reject(sprintf(
                'امتیاز هم‌گرایی %.0f از حداقل لازم %.0f کمتر بود (%s)',
                $score,
                Config::minConfluenceScore(),
                implode('، ', array_slice($why, 0, 3))
            ));
        }

        // -- the stop -----------------------------------------------------------
        $stop = $isLong
            ? min($guard['level'], $price - $atr * 0.5) - $atr * Config::stopPadAtr()
            : max($guard['level'], $price + $atr * 0.5) + $atr * Config::stopPadAtr();
        if (($isLong && $stop >= $price) || (!$isLong && $stop <= $price)) {
            return $this->reject('حد ضرر ساختاری سمت اشتباه قیمت افتاد');
        }

        $this->lastVotes = [
            'score' => round($score, 1),
            'setups' => $setups[$side],
            'trend' => $setups['trend'],
            'wave' => $wave['direction'],
            'range' => $range['qualified'] ? ($range['break'] ?? 'inside') : 'none',
            'structure' => $structure['event'],
            'internal' => $smc['internal_dir'],
            'sweep' => $sweep !== null,
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
            'tp1' => null,   // the planner sets both targets from the risk budget
            'tp2' => null,
            'tp3' => null,
            'setup' => $isLong ? 'breakout' : 'reversal',
            'event' => $structure['event'] ?? ($wave['event'] ?? 'confluence'),
            'confluence_score' => round($score, 1),
            'confluence_reasons' => $why,
        ];
    }

    /**
     * Is this a continuation trade or a pullback one?
     *
     * Continuation: something broke — a range, a structure level, a sweep
     * that started a move. Price is meant to be at the edge of its range.
     * Pullback: price came back into a zone and is being bought or sold
     * there, which is where premium/discount and OTE apply.
     *
     * @param array<int,string> $firedSetups
     * @param array<string,mixed> $range
     * @param array<string,mixed> $structure
     * @param array<string,mixed> $smc
     */
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

    /**
     * The trend on the next timeframe up, read from the same ALMA wave.
     *
     * A 15m long into a falling 1h is the trade that keeps almost working:
     * the trigger is real, the context is wrong, and it gets stopped on the
     * higher timeframe's next leg. Returns null when that timeframe has no
     * candles loaded, which is not a veto — just no information.
     */
    private function higherTimeframeBias(MarketSnapshot $snapshot, string $timeframe): ?string
    {
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
        $best = null;
        $bestSeconds = PHP_INT_MAX;
        foreach ($order as $tf) {
            $s = $seconds($tf);
            if ($s > $current && $s < $bestSeconds) {
                $best = $tf;
                $bestSeconds = $s;
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
        return $wave['direction'] === 'neutral' ? null : $wave['direction'];
    }

    /**
     * The nearest protective zone below (long) or above (short) price:
     * an order block, an unfilled FVG, or a supply/demand zone. Only zones
     * within reach count — a demand zone 8 ATR away protects nothing.
     *
     * @param OrderBlock[] $orderBlocks
     * @param Fvg[] $fvgs
     * @param SupplyDemandZone[] $sdZones
     * @return array{level:float, label:string}|null
     */
    private function guardLevel(bool $isLong, float $price, float $atr, array $orderBlocks, array $fvgs, array $sdZones): ?array
    {
        $reach = $atr * Config::zoneReachAtr();
        $best = null;

        $consider = function (float $level, string $label) use (&$best, $isLong, $price, $reach): void {
            if ($isLong && ($level > $price || $level < $price - $reach)) {
                return;
            }
            if (!$isLong && ($level < $price || $level > $price + $reach)) {
                return;
            }
            if ($best === null
                || ($isLong && $level > $best['level'])
                || (!$isLong && $level < $best['level'])) {
                $best = ['level' => $level, 'label' => $label];
            }
        };

        foreach ($orderBlocks as $ob) {
            if ($ob->mitigated) {
                continue;
            }
            if ($isLong && $ob->type === OrderBlockType::BULLISH) {
                $consider($ob->low, 'اوردر بلاک صعودی زیر قیمت');
            }
            if (!$isLong && $ob->type === OrderBlockType::BEARISH) {
                $consider($ob->high, 'اوردر بلاک نزولی بالای قیمت');
            }
        }
        foreach ($fvgs as $fvg) {
            if ($fvg->filled) {
                continue;
            }
            $consider($isLong ? $fvg->low : $fvg->high, 'گپ قیمتی (FVG) پرنشده');
        }
        foreach ($sdZones as $zone) {
            if ($isLong && $zone->isDemand) {
                $consider($zone->bottom, 'ناحیه تقاضا');
            }
            if (!$isLong && !$zone->isDemand) {
                $consider($zone->top, 'ناحیه عرضه');
            }
        }

        return $best;
    }

    /**
     * The nearest untapped liquidity pool in the trade's direction — where
     * price is being drawn, and a sanity check that the target is reachable.
     *
     * @param LiquidityLevel[] $levels
     */
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
}

final class StrategyEngine
{
    /** @var array<string,Strategy> */
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

        // Two separate ceilings on how far the stop may sit:
        //
        //   1. the published risk budget — MAX_STOP_LEVERAGED_PCT is a
        //      LEVERAGED loss (30% by default), so at 20x it is a 1.5%
        //      price move and at 50x a 0.6% one;
        //   2. liquidation — the leverage itself would have closed the
        //      trade first, whatever the strategy wanted.
        //
        // Whichever is tighter wins.
        $riskBudgetPct = Config::maxStopLeveragedPercent() / $leverage;
        $liquidationPct = Config::leverageLiquidationBuffer() * (100.0 / $leverage);
        $maxStopPct = min($riskBudgetPct, $liquidationPct);
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

        // Targets are the leveraged percentages the operator asked for —
        // 60% and 120% — converted at this coin's leverage. They do NOT
        // scale with the stop: a tighter structural stop makes the trade a
        // better R:R rather than a smaller win.
        $tp1Distance = $entry * (Config::tp1LeveragedPercent() / $leverage) / 100;
        $tp2Distance = $entry * (Config::tp2LeveragedPercent() / $leverage) / 100;
        if ($tp1Distance <= 0 || $tp2Distance <= $tp1Distance) {
            return null;
        }

        $sign = $direction === Direction::LONG ? 1 : -1;

        return [
            'entry' => $entry,
            'stop_loss' => $entry - $sign * $distance,
            'tp1' => $entry + $sign * $tp1Distance,
            'tp2' => $entry + $sign * $tp2Distance,
            'leverage' => $leverage,
            'tier' => $tier,
            'risk' => $distance,
            'rr' => round($tp1Distance / $distance, 2),
            'stop_pct' => ($distance / $entry) * 100,
        ];
    }
}

// ============================================================================
// SECTION 15.9 — MONEY MANAGEMENT
//
// The bot publishes signals, it does not place orders, so this turns the
// stop the planner produced into the numbers a reader actually needs to
// size the trade: risk a fixed percent of the account, never a fixed
// margin, so a losing streak shrinks the position instead of compounding
// the damage.
// ============================================================================

final class MoneyManager
{
    /**
     * @return array{
     *   balance:float, risk_pct:float, risk_amount:float,
     *   position_size:float, margin:float, qty:float, stop_pct:float
     * }
     */
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

        // Notional that loses exactly $riskAmount when the stop is hit.
        // Leverage does not change the risk — it only changes how much
        // margin that notional ties up, which is the whole reason sizing is
        // done from the stop distance and not from the leverage.
        $positionSize = $riskAmount / ($stopPct / 100);
        $margin = $leverage > 0 ? $positionSize / $leverage : $positionSize;

        // Never let a single trade tie up more margin than the account has.
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

    /** Formats a quote-currency amount the way the caption prints it. */
    public static function money(float $value): string
    {
        if ($value >= 1000) {
            return number_format($value, 0);
        }
        return number_format($value, $value >= 10 ? 1 : 2);
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
        $money = MoneyManager::plan($signal->entry, $signal->stopLoss, $signal->leverage);

        $placeholders = [
            'symbol' => SignalCardFactory::displaySymbol($signal->symbol),
            'symbol_raw' => $signal->symbol,
            'exchange' => ucfirst($signal->exchange),
            'direction' => $signal->direction->value,
            'direction_fa' => self::directionFa($signal->direction->value),
            'entry' => $this->fmt($signal->entry),
            // Entries are market orders taken at the price the signal was
            // built on — never a resting trigger — so the caption can say so.
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

/**
 * Which coins the reader can actually trade.
 *
 * MEXC is where the analysis happens (deepest small-cap coverage), but the
 * signals are taken on Toobit and Ourbit — so a setup on a coin neither of
 * those lists is a signal nobody can act on. This fetches each venue's
 * listing once every few hours, caches it in bot_settings, and hands back
 * the set of base assets they carry between them.
 *
 * It FAILS OPEN, deliberately and loudly: if a venue cannot be reached, or
 * its response parses to nothing, that venue simply does not contribute a
 * restriction. A tradability filter that failed closed would silence the
 * whole bot the first time an endpoint moved.
 */
final class VenueListings
{
    private const CACHE_KEY = 'venue_listings';

    /** @var array{assets:array<string,bool>, venues:array<string,array<string,mixed>>}|null */
    private static ?array $memo = null;

    /**
     * @return array{
     *   assets:array<string,bool>,
     *   venues:array<string,array{ok:bool,count:int,error:?string,url:string}>,
     *   active:bool,
     *   fetched_at:int
     * }
     */
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

    /** True when $baseAsset is listed on at least one configured venue, or when the filter is off. */
    public static function allows(string $baseAsset): bool
    {
        $state = self::current();
        if (!$state['active']) {
            return true;
        }
        return isset($state['assets'][strtoupper($baseAsset)]);
    }

    /** Drops the cache so the next call refetches. */
    public static function forget(): void
    {
        self::$memo = null;
        try {
            Database::pdo()->prepare('DELETE FROM bot_settings WHERE setting_key = :k')->execute([':k' => self::CACHE_KEY]);
        } catch (Throwable) {
            // A cache that will not clear is not worth failing over.
        }
    }

    /**
     * Pulls one venue's listing and reduces it to a set of base assets.
     *
     * The response shape is deliberately not assumed: exchanges put their
     * instrument list under "symbols", "contracts", "data" or at the root,
     * and name the base asset half a dozen ways. Anything that yields no
     * assets returns null so the caller can treat the venue as unavailable.
     *
     * @return array<int,string>|null
     */
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

    /**
     * Reduces a listing response to its set of base assets.
     *
     * Signals are for PERPETUAL FUTURES, not spot, so when a response
     * carries both — Toobit returns "symbols" (spot) and "contracts"
     * (USDT-M) in one document — only the contracts count. A coin listed on
     * spot with no perp cannot be traded at 20x at all. A response with no
     * derivatives section falls back to whatever instrument list it does
     * have, which is the right call for a futures-only endpoint that simply
     * names its array something else.
     *
     * @param array<mixed> $json
     * @return array<int,string>|null
     */
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

    /**
     * Every list-of-objects in the response that could be an instrument
     * list, in the order exchanges tend to nest them.
     *
     * @param array<mixed> $json
     * @param bool $futuresOnly restrict to the keys an exchange uses for its
     *   derivatives book, so a spot-only listing never satisfies the filter
     * @return array<int,array<int,mixed>>
     */
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
            // One more level down: {"data": {"list": [...]}}
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

    /** @param array<string,mixed> $row */
    private static function baseAssetOf(array $row): ?string
    {
        // A status field, when present, must say the instrument is live.
        foreach (['status', 'state', 'contractStatus'] as $key) {
            $status = $row[$key] ?? null;
            if (is_string($status) && $status !== '' && !in_array(strtoupper($status), ['TRADING', 'ENABLED', 'ONLINE', 'NORMAL', 'LIVE', '1'], true)) {
                return null;
            }
        }

        foreach (['baseAsset', 'baseCoin', 'baseCurrency', 'baseCoinName', 'base'] as $key) {
            $v = $row[$key] ?? null;
            if (is_string($v) && trim($v) !== '') {
                // Toobit spells a contract's base asset as the whole
                // instrument name ("BTC-SWAP-USDT"), so it still goes
                // through the splitter rather than being trusted whole.
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

    /** @return array{assets:array<string,bool>, venues:array<string,mixed>, active:bool, fetched_at:int}|null */
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

    /** @param array<string,mixed> $state */
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
            // The share card speaks the exchanges' own language, so the
            // symbol keeps its raw exchange spelling rather than the
            // prettified BTC/USDT form used in the Persian caption.
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

    /**
     * How long the trade was open, in the exchanges' own "00H 00M" form.
     * Falls back to zero rather than guessing when the row predates the
     * timestamp columns.
     *
     * @param array<string,mixed> $row
     */
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

    /**
     * Exchange/symbol pairs that still have an unresolved trade on them.
     *
     * @return array<int,array{exchange:string,symbol:string}>
     */
    public function openPositionSymbols(): array
    {
        $sql = "SELECT DISTINCT e.name AS exchange, s.symbol
                FROM signals s
                JOIN exchanges e ON e.id = s.exchange_id
                WHERE s.status IN ('sent','queued') AND s.resolved_at IS NULL";
        return Database::pdo()->query($sql)->fetchAll();
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

    /**
     * Trades stopped out since local midnight, and signals published since
     * local midnight — the two counters the daily circuit breaker reads.
     *
     * @return array{losses:int, published:int}
     */
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

    /**
     * @return array<int,array{exchange:string,symbol:string,base_asset:string,quote_asset:string,volume_24h:float,volatility:float,spread_pct:float,rank_position:int}>
     */
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
        // A limit of 0 (or null) means the whole universe — every perp above
        // the volume floor. The rotation in the worker bounds the work by
        // time instead, so there is no reason to throw coins away here.
        $limit = ($limit === null || $limit <= 0) ? null : $limit;

        // Over-fetch, because the same pair listed on four exchanges
        // collapses to one row below and a limit applied before that would
        // return a universe of mostly duplicates.
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

    /**
     * One row per pair, taken from PRIMARY_EXCHANGE wherever that exchange
     * lists it.
     *
     * Without this the same coin is analysed once per exchange that carries
     * it, and since the dedup fingerprint includes the exchange name, the
     * identical setup goes out as three separate signals. It also means a
     * pair MEXC lists is signalled on MEXC — which is the point of having a
     * primary venue at all.
     *
     * @param array<int,array<string,mixed>> $rows ordered best-first
     * @return array<int,array<string,mixed>>
     */
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
            // Rows arrive best-ranked first, so only the primary exchange
            // may displace one that is already held.
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
            // Fiat quotes are refused unconditionally — see Config::blockedQuoteAssets().
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
            // A setup on a coin the reader's exchange does not list is a
            // signal nobody can act on. Inactive (and therefore harmless)
            // whenever the venue listings could not be loaded.
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
            // Liquidity proxy: volume normalized against spread (tighter spread + higher volume = more liquid).
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

    /**
     * Builds the universe out of three baskets rather than one volume
     * ranking: the day's biggest gainers, its biggest losers, and the most
     * liquid names. They are then interleaved, so the top of the list —
     * the part a cron pass is guaranteed to reach before its budget runs
     * out — always contains all three kinds.
     *
     * A coin only counts as a mover if it has actually moved
     * (SCANNER_MIN_MOVE_PCT); in a flat market the mover baskets simply
     * come up short and the liquid basket fills the rest, which is the
     * correct behaviour rather than promoting noise.
     *
     * @param array<int,array<string,mixed>> $candidates
     * @return array<int,array<string,mixed>>
     */
    private static function bucketRank(array $candidates, int $topN): array
    {
        if (empty($candidates)) {
            return [];
        }
        // 0 means no cap: keep every coin that cleared the volume floor.
        // Clamping to 1 here would collapse the whole market to a single
        // symbol, which is what "unlimited" must never be turned into.
        $topN = $topN > 0 ? $topN : count($candidates);
        $minMove = Config::scannerMinMovePercent();

        // With no cap the baskets exist purely to ORDER the list — the
        // movers lead so the rotation reaches them first — and every
        // remaining coin still follows in the liquid basket.
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

        // Volume and liquidity, the original ranking, for everything else.
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

        // Round-robin so the first slots carry one of each kind.
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
                break; // every basket exhausted
            }
        }

        return $out;
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
        ?string $strategyName = null,
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
                $this->observe($snapshot, $timeframe, $score, $confluence['bias'], 'تکراری است (Cooldown)');
                return null;
            }

            // A strategy that scored the setup itself owns that number.
            //
            // The combination strategy weighs the six indicators directly,
            // so judging its output by the older factor engine's score as
            // well would be a second, invisible gate on a different scale —
            // and a setup every module agreed on could be thrown away for
            // failing a test it never took.
            $score = isset($setup['confluence_score'])
                ? (float) $setup['confluence_score']
                : $confluence['score'];

            // Calibrated against the conviction scale: 70+ means several factors
            // agree, 55+ means a clear read with partial confluence.
            $confidence = $score >= 70 ? 'high' : ($score >= 55 ? 'medium' : 'low');

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

    /**
     * Puts the structural event that actually triggered the trade at the
     * top of the reason list. The confluence factors explain why it was
     * worth taking; this explains what happened.
     *
     * @param array<string,mixed> $setup
     * @param string[] $reasons
     * @return string[]
     */
    private static function setupReasons(array $setup, array $reasons): array
    {
        // The combination strategy already explains itself in full — which
        // modules agreed and why — so its reasons lead and the generic
        // confluence factors follow.
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

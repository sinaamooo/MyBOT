<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'worker.php can only be run from the CLI: php worker.php';
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';
require_once __DIR__ . '/bot.php';

/**
 * ============================================================================
 * worker.php — runs the whole Load Symbols -> Market Data -> Scan ->
 * Indicators -> S/R -> OB -> FVG -> Strategy -> Confluence -> Validate ->
 * Queue -> Send Telegram cycle. Two execution modes, chosen by WORKER_MODE:
 *
 *   daemon  php worker.php   loops forever until SIGTERM/SIGINT — the
 *           original "no cron, ever" model. Needs a host that allows a
 *           persistent background process (SSH + nohup/screen, a VPS, ...).
 *
 *   cron    php worker.php   runs ONE bounded pass (up to
 *           WORKER_MAX_RUNTIME_SECONDS, default 50s) and exits cleanly.
 *           A cPanel Cron Job re-invokes it (every 1 minute — cPanel's
 *           minimum granularity) to approximate continuous operation. This
 *           is the default: it's the only mode plain shared hosting with
 *           just File Manager + Cron Jobs (no SSH, no persistent process)
 *           can actually run. A lock file prevents two invocations
 *           overlapping if one runs long; scanner/timeframe due-times are
 *           persisted to the database (not kept in memory) so scheduling
 *           survives across the process restarting every single minute.
 *           In this mode there is no persistent WebSocket connection (a
 *           connection that lives ~50s and dies is pointless) — market
 *           data is REST-polled, same as the fallback path in daemon mode.
 *
 * One exchange failing (rate limit, outage, bad response) never stops the
 * others — every exchange call goes through ExchangeManager::withIsolation
 * (signal.php), which owns per-exchange circuit breaking.
 * ============================================================================
 */

// ============================================================================
// SECTION 0 — LOCK FILE
// Stops two overlapping invocations (a slow cron run still finishing when
// the next minute's cron fires) from touching market data / the signal
// queue at the same time.
// ============================================================================

final class LockFile
{
    /** @var resource|null */
    private $handle = null;

    public function acquire(string $path): bool
    {
        $this->handle = @fopen($path, 'c');
        if ($this->handle === false) {
            $this->handle = null;
            return false;
        }
        return flock($this->handle, LOCK_EX | LOCK_NB);
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }
}

// ============================================================================
// SECTION 1 — BACKOFF HELPER
// ============================================================================

final class Backoff
{
    private int $failures = 0;

    public function __construct(private int $baseSeconds = 2, private int $maxSeconds = 120)
    {
    }

    public function reset(): void
    {
        $this->failures = 0;
    }

    public function fail(): int
    {
        $this->failures++;
        return $this->delaySeconds();
    }

    public function delaySeconds(): int
    {
        return min($this->maxSeconds, $this->baseSeconds * (2 ** min($this->failures, 10)));
    }
}

// ============================================================================
// SECTION 2 — SIGNAL QUEUE (internal, in-process)
// Decouples signal generation from Telegram delivery, so a slow/failing
// Telegram API call never blocks the scanner from moving to the next symbol.
// ============================================================================

final class SignalQueue
{
    /** @var array<int,Signal> */
    private array $items = [];

    public function push(Signal $signal): void
    {
        $this->items[] = $signal;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function drain(): array
    {
        $items = $this->items;
        $this->items = [];
        return $items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}

// ============================================================================
// SECTION 3 — TELEGRAM DISPATCHER
// Fans one Signal out to every eligible channel (per-channel template,
// minimum score, strategy allow-list, enabled flag), with per-send retry.
// ============================================================================

final class TelegramDispatcher
{
    /** Telegram's hard limit on a photo caption, in UTF-16 code units. */
    private const CAPTION_LIMIT = 1024;

    private SignalFormatter $formatter;
    private TextFormatManager $texts;
    private QuoteManager $quotes;

    public function __construct(
        private TelegramClient $telegram,
        private ChannelManager $channels,
        private SignalRepository $signalRepo,
    ) {
        $this->formatter = new SignalFormatter();
        $this->texts = new TextFormatManager();
        $this->quotes = new QuoteManager();
    }

    public function dispatch(Signal $signal): void
    {
        $eligible = $this->eligibleChannels($signal);
        if (empty($eligible)) {
            $this->signalRepo->updateStatus($signal->id, 'expired');
            return;
        }

        $dryRun = $this->currentRunMode() === 'DRY_RUN';
        // Rendered once and reused for every channel — the card costs about
        // a second of CPU, and a broadcast to five channels should not cost
        // five of them.
        $card = $dryRun ? null : SignalCardFactory::entry($signal);
        $anySent = false;

        foreach ($eligible as $channel) {
            $templateKey = $channel['template_key'] ?: 'signal_template';
            $template = $this->texts->get($templateKey);
            if ($template['text'] === '') {
                $template = $this->texts->get('signal_template');
            }
            $rendered = $this->formatter->format($signal, $template['text'], $template['entities']);

            if ($dryRun) {
                Logger::info('dispatcher', 'DRY_RUN — signal not sent', ['symbol' => $signal->symbol, 'channel' => $channel['chat_id']]);
                $this->logEvent($signal->id, (int) $channel['id'], 'queued', null, null, 0);
                continue;
            }

            $opts = [];
            if ((int) $channel['quote_enabled'] === 1) {
                $ref = $this->quotes->latest('channel_pin', (string) $channel['id']);
                if ($ref !== null) {
                    $opts['reply_parameters'] = $this->quotes->buildReplyParameters($ref);
                }
            }

            $sent = $this->deliver((int) $channel['chat_id'], $rendered['text'], $rendered['entities'], $card, $opts, $signal->id, (int) $channel['id']);
            if ($sent !== null) {
                $anySent = true;
                if ((int) $channel['pin_signal'] === 1 && $sent > 0) {
                    $this->telegram->pinChatMessage((int) $channel['chat_id'], $sent);
                }
            }
        }

        $this->signalRepo->updateStatus($signal->id, $dryRun ? 'queued' : ($anySent ? 'sent' : 'failed'));
    }

    /**
     * Announces a trade event as a reply to the original signal message, in
     * every channel that actually received it — TP1 (the risk-free "profit
     * shot"), TP2, a stop out, or a breakeven close. LIVE sends only: a
     * DRY_RUN "queued" row was never really posted, so there is nothing to
     * reply to.
     *
     * @param array<string,mixed> $signalRow
     * @param string $kind tp1|tp2|sl|be
     */
    public function announceResult(array $signalRow, string $kind, float $price): void
    {
        $template = $this->texts->get('result_' . $kind);
        if ($template['text'] === '') {
            $template = ['text' => "{symbol} {direction} — {result}\n{pnl}%", 'entities' => []];
        }
        $rendered = $this->formatter->formatResult($signalRow, $kind, $price, $template['text'], $template['entities']);
        $card = SignalCardFactory::result($signalRow, $kind, $price);

        $targets = $this->announcementTargets((int) $signalRow['id']);
        if (empty($targets)) {
            Logger::error('dispatcher', 'trade result had nowhere to go', [
                'signal_id' => $signalRow['id'] ?? null,
                'symbol' => $signalRow['symbol'] ?? null,
                'kind' => $kind,
            ]);
            return;
        }

        $delivered = 0;
        foreach ($targets as $target) {
            $opts = [];
            if ($target['message_id'] > 0) {
                // Reply to the signal itself. chat_id is deliberately omitted
                // — in reply_parameters it means "the message lives in a
                // DIFFERENT chat", and passing the current chat can make
                // Telegram fail to find it. allow_sending_without_reply
                // keeps a deleted or unreachable original from swallowing the
                // result entirely: the announcement matters more than the
                // thread it hangs off.
                $opts['reply_parameters'] = [
                    'message_id' => $target['message_id'],
                    'allow_sending_without_reply' => true,
                ];
            }
            $sent = $this->deliver($target['chat_id'], $rendered['text'], $rendered['entities'], $card, $opts, (int) $signalRow['id'], $target['channel_id']);
            if ($sent !== null) {
                $delivered++;
            }
        }

        if ($delivered === 0) {
            Logger::error('dispatcher', 'trade result failed to send anywhere', [
                'signal_id' => $signalRow['id'] ?? null,
                'symbol' => $signalRow['symbol'] ?? null,
                'kind' => $kind,
                'targets' => count($targets),
            ]);
        }
    }

    /**
     * Where a trade result should be announced.
     *
     * Normally: as a reply to the message that carried the signal, in each
     * channel that received it. But if those events are missing — an older
     * signal, a wiped database, a send that succeeded while its event row
     * did not — the outcome must still reach the channel. Silence there is
     * the worst possible failure: subscribers are left holding a position
     * with no word on it. So fall back to the active channels with no reply.
     *
     * @return array<int,array{chat_id:int, channel_id:int, message_id:int}>
     */
    private function announcementTargets(int $signalId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT se.channel_id, MAX(se.message_id) AS message_id, c.chat_id
             FROM signal_events se
             JOIN channels c ON c.id = se.channel_id
             WHERE se.signal_id = :sid AND se.event_type = 'sent' AND se.message_id IS NOT NULL
             GROUP BY se.channel_id, c.chat_id"
        );
        $stmt->execute([':sid' => $signalId]);

        $targets = [];
        foreach ($stmt->fetchAll() as $row) {
            $targets[] = [
                'chat_id' => (int) $row['chat_id'],
                'channel_id' => (int) $row['channel_id'],
                'message_id' => (int) $row['message_id'],
            ];
        }
        if (!empty($targets)) {
            return $targets;
        }

        Logger::warning('dispatcher', 'no sent-event for signal, announcing without a reply', ['signal_id' => $signalId]);
        foreach ($this->channels->listActiveWithSettings() as $channel) {
            $targets[] = [
                'chat_id' => (int) $channel['chat_id'],
                'channel_id' => (int) $channel['id'],
                'message_id' => 0,
            ];
        }
        return $targets;
    }

    /**
     * Sends one message, as a captioned photo when a card was rendered and
     * the text fits inside Telegram's caption limit, otherwise as a photo
     * plus a follow-up text message (or plain text when there is no card).
     *
     * Returns the message id the rest of the thread should reply to — the
     * photo's, when there is one, so later TP/SL announcements hang off the
     * card rather than off a trailing text message.
     */
    private function deliver(int $chatId, string $text, array $entities, ?string $card, array $opts, int $signalId, int $channelId): ?int
    {
        if ($card === null) {
            return $this->sendWithRetry($chatId, $text, $entities, $opts, $signalId, $channelId);
        }

        $fitsInCaption = TelegramEntityUtils::utf16Length($text) <= self::CAPTION_LIMIT;
        $caption = $fitsInCaption ? $text : '';
        $captionEntities = $fitsInCaption ? $entities : [];

        $messageId = $this->sendPhotoWithRetry($chatId, $card, $caption, $captionEntities, $opts, $signalId, $channelId);
        if ($messageId === null) {
            // The image failed (upload error, Telegram rejecting the file,
            // ...) — the signal itself still has to reach the channel.
            return $this->sendWithRetry($chatId, $text, $entities, $opts, $signalId, $channelId);
        }

        if (!$fitsInCaption) {
            $this->sendWithRetry($chatId, $text, $entities, [
                'reply_parameters' => ['message_id' => $messageId, 'allow_sending_without_reply' => true],
            ], $signalId, $channelId);
        }
        return $messageId;
    }

    private function sendPhotoWithRetry(int $chatId, string $photo, string $caption, array $captionEntities, array $opts, int $signalId, int $channelId, int $maxAttempts = 3): ?int
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->telegram->sendPhoto($chatId, $photo, $caption, $captionEntities, $opts);
            if ($result['ok'] ?? false) {
                $messageId = (int) ($result['result']['message_id'] ?? 0);
                $this->logEvent($signalId, $channelId, 'sent', $messageId, null, $attempt);
                return $messageId;
            }
            $error = (string) ($result['description'] ?? 'unknown error');
            Logger::warning('dispatcher', 'photo send failed', ['chat_id' => $chatId, 'attempt' => $attempt, 'error' => $error]);
            $captionEntities = $this->degradeEntities($captionEntities, $error, $chatId);
            usleep(500_000 * $attempt);
        }
        return null;
    }

    private function sendWithRetry(int $chatId, string $text, array $entities, array $opts, int $signalId, int $channelId, int $maxAttempts = 3): ?int
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $result = $this->telegram->sendMessage($chatId, $text, $entities, $opts);
            if ($result['ok'] ?? false) {
                $messageId = (int) ($result['result']['message_id'] ?? 0);
                $this->logEvent($signalId, $channelId, 'sent', $messageId, null, $attempt);
                return $messageId;
            }
            $error = (string) ($result['description'] ?? 'unknown error');
            $this->logEvent($signalId, $channelId, 'retry', null, $error, $attempt);
            Logger::warning('dispatcher', 'send failed, retrying', ['chat_id' => $chatId, 'attempt' => $attempt, 'error' => $error]);
            $entities = $this->degradeEntities($entities, $error, $chatId);
            usleep(500_000 * $attempt);
        }
        $this->logEvent($signalId, $channelId, 'failed', null, 'max attempts reached', $maxAttempts);
        return null;
    }

    /**
     * Drops custom emoji from a message Telegram just refused, so the retry
     * goes out as plain text rather than failing again for the same reason.
     *
     * A bot may only use custom emoji if it bought a username on Fragment,
     * or when writing to a private/group/supergroup chat and its owner has
     * Premium — CHANNELS are not covered by the owner-Premium route. Since
     * this bot's whole job is posting to a channel, a premium emoji set is
     * exactly the kind of setting that could silently stop every signal.
     * Losing the fancy emoji is always better than losing the signal.
     *
     * @param array<int,array<string,mixed>> $entities
     * @return array<int,array<string,mixed>>
     */
    private function degradeEntities(array $entities, string $error, int $chatId): array
    {
        if (!TelegramEntityUtils::hasCustomEmoji($entities)) {
            return $entities;
        }
        Logger::warning('dispatcher', 'retrying without custom emoji', ['chat_id' => $chatId, 'error' => $error]);
        return TelegramEntityUtils::stripCustomEmoji($entities);
    }

    private function logEvent(int $signalId, int $channelId, string $type, ?int $messageId, ?string $error, int $attempt): void
    {
        Database::pdo()->prepare(
            'INSERT INTO signal_events (signal_id, channel_id, event_type, message_id, error, attempt, created_at)
             VALUES (:sid, :cid, :type, :mid, :err, :att, :now)'
        )->execute([
            ':sid' => $signalId, ':cid' => $channelId, ':type' => $type, ':mid' => $messageId,
            ':err' => $error, ':att' => $attempt, ':now' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function eligibleChannels(Signal $signal): array
    {
        $channels = $this->channels->listActiveWithSettings();
        return array_values(array_filter($channels, static function ($c) use ($signal) {
            if ((float) $c['min_signal_score'] > $signal->score) {
                return false;
            }
            $allowedStrategies = json_decode((string) ($c['allowed_strategies'] ?? 'null'), true);
            if (is_array($allowedStrategies) && !empty($allowedStrategies) && !in_array($signal->strategy, $allowedStrategies, true)) {
                return false;
            }
            $allowedExchanges = json_decode((string) ($c['allowed_exchanges'] ?? 'null'), true);
            if (is_array($allowedExchanges) && !empty($allowedExchanges) && !in_array($signal->exchange, $allowedExchanges, true)) {
                return false;
            }
            return true;
        }));
    }

    private function currentRunMode(): string
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = "run_mode"');
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return $v !== false && $v !== '' ? (string) $v : Config::runMode()->value;
    }
}

// ============================================================================
// SECTION 4 — WORKER (main loop)
// ============================================================================

final class Worker
{
    private ExchangeManager $exchangeManager;
    private MarketDataStore $marketData;
    private MarketScanner $scanner;
    private SignalGenerator $signalGenerator;
    private SignalQueue $queue;
    private TelegramDispatcher $dispatcher;
    private SymbolRepository $symbolRepo;
    private SignalRepository $signalRepo;

    private bool $running = true;
    private int $lastScanAt = 0;

    /** Unix time this invocation must be finished by (cron mode); PHP_INT_MAX in daemon mode. */
    private int $deadline = PHP_INT_MAX;

    /** Seconds held back from market-data collection so the tick loop always runs. */
    private const TICK_RESERVE_SECONDS = 15;

    /** @var array<string,int> timeframe => unix timestamp of next due scan */
    private array $nextTimeframeRun = [];

    /** Why publishing is paused for the rest of today, if it is: 'losses' | 'quota' | null. */
    private ?string $dailyStop = null;

    /** @var array<string,Backoff> per-exchange backoff state for the outer loop */
    private array $exchangeBackoff = [];

    private ScannerRunRepository $scannerRunRepo;

    public function __construct()
    {
        Database::migrate();

        $this->exchangeManager = new ExchangeManager();
        $this->marketData = new MarketDataStore();
        $this->scanner = new MarketScanner();
        $this->signalGenerator = new SignalGenerator(new IndicatorEngine());
        $this->queue = new SignalQueue();
        $telegram = new TelegramClient();
        $channels = new ChannelManager($telegram);
        $this->signalRepo = new SignalRepository();
        $this->dispatcher = new TelegramDispatcher($telegram, $channels, $this->signalRepo);
        $this->symbolRepo = new SymbolRepository();
        $this->scannerRunRepo = new ScannerRunRepository();

        foreach ($this->exchangeManager->adapters() as $name => $adapter) {
            $this->exchangeBackoff[$name] = new Backoff();
        }

        // Cron mode restarts this whole process every ~1 minute, so "last
        // run" state can't live in memory (it would reset every restart and
        // make every symbol/timeframe look due on every single invocation,
        // hammering exchange APIs). Load it from the database instead;
        // daemon mode reads the same state once and then keeps it in memory
        // for the life of the process, same as before.
        $lastScan = $this->scannerRunRepo->lastRunAt();
        $this->lastScanAt = $lastScan !== null ? (int) strtotime($lastScan) : 0;
        $this->nextTimeframeRun = array_merge(array_fill_keys(Config::timeframes(), 0), $this->loadTimeframeSchedule());

        $this->installSignalHandlers();
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        $handler = function (int $signo): void {
            Logger::info('worker', 'shutdown signal received', ['signal' => $signo]);
            $this->running = false;
            ShutdownFlag::request();
        };
        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
        // SIGALRM drives the cron-mode hard deadline below (pcntl_alarm) —
        // same handler as SIGTERM/SIGINT, so it interrupts an in-flight
        // HTTP retry storm exactly the same way (ShutdownFlag is what
        // HttpClient actually polls; a deadline check in the tick loop
        // alone would NOT catch a retry storm stuck inside the very first,
        // pre-loop scanner/priming calls — confirmed by testing).
        pcntl_signal(SIGALRM, $handler);
    }

    public function run(): void
    {
        $mode = Config::workerMode();
        Logger::info('worker', 'worker starting', ['run_mode' => Config::runMode()->value, 'worker_mode' => $mode]);

        $lock = new LockFile();
        if (!$lock->acquire(Config::storageDir() . '/worker.lock')) {
            Logger::info('worker', 'another worker invocation is still running — skipping this one');
            return;
        }

        if ($mode === 'cron' && function_exists('pcntl_alarm')) {
            pcntl_alarm(Config::workerMaxRuntimeSeconds());
        }

        try {
            $this->runLoop($mode);
        } finally {
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0); // cancel any pending alarm — we're exiting on our own
            }
            $lock->release();
        }
    }

    private function runLoop(string $mode): void
    {
        // daemon: no deadline, runs until SIGTERM/SIGINT flips $this->running.
        // cron: exit on our own before the host's cron/process limits would
        // kill us anyway, so the next minute's invocation starts clean. The
        // real enforcement is the SIGALRM set in run(); this time-based
        // check is just what keeps the *tick loop itself* from starting
        // another full cycle once the budget is spent.
        $deadline = $mode === 'daemon' ? PHP_INT_MAX : (time() + Config::workerMaxRuntimeSeconds());
        $this->deadline = $deadline;

        // Load Symbols (whatever the last scan produced) + Fetch Initial
        // Market Data before entering the steady-state loop. Gated the same
        // way as every later tick, so a cron invocation doesn't re-scan or
        // re-prime data that's already fresh from the previous minute.
        // Written before the expensive work, so the panel can tell "the
        // worker is running" apart from "cron never fired" even while an
        // invocation is still busy collecting data.
        $this->heartbeat();

        $this->maybeRunScanner();
        $this->primeMarketData();

        while ($this->running && time() < $deadline) {
            $tickStart = microtime(true);

            try {
                $this->maybeRunScanner();
                $this->updateMarketData();
                $this->monitorOpenPositions();
                $this->maybeEmitSignal();
                $this->processQueue();
                $this->heartbeat();
            } catch (Throwable $e) {
                // The loop itself must never die — log and continue.
                Logger::critical('worker', 'unhandled error in tick', ['error' => $e->getMessage()]);
            }

            $elapsed = microtime(true) - $tickStart;
            $remaining = $deadline - time();
            if ($remaining <= 0) {
                break;
            }
            $sleepFor = max(1, min(Config::workerTickSeconds(), $remaining) - (int) $elapsed);
            $this->sleepInterruptible($sleepFor);
        }

        Logger::info('worker', $mode === 'daemon' ? 'worker stopped gracefully' : 'worker run finished (cron invocation)');
    }

    private function sleepInterruptible(int $seconds): void
    {
        for ($i = 0; $i < $seconds && $this->running; $i++) {
            sleep(1);
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    private function heartbeat(): void
    {
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (\'worker_heartbeat\', :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
        )->execute([':v' => (string) time(), ':now' => date('Y-m-d H:i:s')]);
    }

    /** @return array<string,int> timeframe => unix timestamp of next due run, as of the last saved state */
    private function loadTimeframeSchedule(): array
    {
        $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = \'tf_schedule\'');
        $stmt->execute();
        $v = $stmt->fetchColumn();
        $data = $v !== false && $v !== '' ? json_decode((string) $v, true) : null;
        return is_array($data) ? array_map('intval', $data) : [];
    }

    /** @param array<string,int> $schedule */
    private function saveTimeframeSchedule(array $schedule): void
    {
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (\'tf_schedule\', :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
        )->execute([':v' => json_encode($schedule), ':now' => date('Y-m-d H:i:s')]);
    }

    private function maybeRunScanner(): void
    {
        if (time() - $this->lastScanAt < Config::scannerIntervalSeconds()) {
            return;
        }
        $this->runScanner();
    }

    private function runScanner(): void
    {
        Logger::info('worker', 'running market scanner');
        $selected = $this->scanner->scan($this->exchangeManager);
        $this->lastScanAt = time();
        Logger::info('worker', 'scanner completed', ['symbols_selected' => $selected]);
    }

    /**
     * Fetches history only for symbol/timeframe pairs that have none yet.
     * In daemon mode this only ever matters once, right after start. In
     * cron mode this method runs at the top of every single invocation
     * (every ~1 minute) — the hasAny() check is what stops that from
     * re-fetching all candles for every symbol on every invocation once
     * the database is actually populated.
     */
    private function primeMarketData(): void
    {
        // Only the symbols holding open trades are primed up front. Every
        // other coin is primed by the rotation at the moment it is visited,
        // because the universe is now the whole perpetual market and a
        // pre-loop sweep of it would consume every invocation forever —
        // which is exactly the failure this bot has already had once.
        $candleManager = $this->marketData->candleManager();
        foreach ($this->signalRepo->openPositionSymbols() as $open) {
            if (!$this->running || $this->outOfDataBudget()) {
                break;
            }
            $missing = array_values(array_filter(
                Config::signalTimeframes(),
                static fn(string $tf) => !$candleManager->hasAny($open['exchange'], $open['symbol'], $tf)
            ));
            if (!empty($missing)) {
                $this->syncSymbolCandles($open['exchange'], $open['symbol'], $missing, 200);
            }
        }
    }

    /**
     * Market-data collection stops early enough to leave the tick loop room
     * to run. Publishing a signal matters more than having one more symbol
     * primed.
     */
    private function outOfDataBudget(): bool
    {
        if ($this->deadline === PHP_INT_MAX) {
            return false;
        }
        return time() >= ($this->deadline - self::TICK_RESERVE_SECONDS);
    }

    /**
     * REST-based incremental update every tick (cheap: only timeframes
     * whose candle would plausibly have closed since the last check), plus
     * best-effort WebSocket polling for exchanges that support it. A given
     * exchange failing here only affects that exchange (per-exchange
     * Backoff); the exchanges are otherwise fully independent.
     */
    private function updateMarketData(): void
    {
        // Candles are fetched by the rotation, at the moment it visits each
        // symbol. What has to happen on EVERY tick regardless is the price
        // feed for coins holding an open trade: TP and SL are decided from
        // the ticker alone, and a target hit between two visits would
        // otherwise go unnoticed.
        $tickerCache = [];
        foreach ($this->signalRepo->openPositionSymbols() as $open) {
            if (!$this->running || $this->outOfDataBudget()) {
                return;
            }
            $this->refreshTicker($tickerCache, (string) $open['exchange'], (string) $open['symbol']);
        }
    }

    /**
     * Stores the latest ticker for one symbol, fetching that exchange's
     * whole ticker list at most once per tick.
     *
     * @param array<string,array<string,array<string,float>>> $cache
     */
    private function refreshTicker(array &$cache, string $exchange, string $symbol): void
    {
        if (!$this->exchangeManager->isHealthy($exchange)) {
            return; // circuit open — skip this tick for this exchange only
        }
        if (!array_key_exists($exchange, $cache)) {
            $fetched = $this->exchangeManager->withIsolation($exchange, fn(ExchangeAdapter $a) => $a->fetchTicker24h());
            $cache[$exchange] = is_array($fetched) ? $fetched : [];
        }
        $ticker = $cache[$exchange][$symbol] ?? null;
        if ($ticker !== null) {
            $this->marketData->upsertTicker($exchange, $symbol, $ticker['lastPrice'], $ticker['bid'], $ticker['ask'], $ticker['volume']);
        }
    }

    private function syncSymbolCandles(string $exchange, string $symbol, array $timeframes, int $limit): void
    {
        foreach ($timeframes as $tf) {
            $candles = $this->exchangeManager->withIsolation($exchange, fn(ExchangeAdapter $a) => $a->fetchCandles($symbol, $tf, $limit));
            if (is_array($candles) && !empty($candles)) {
                $this->marketData->candleManager()->upsertMany($exchange, $symbol, $tf, $candles);
                $this->exchangeBackoff[$exchange]->reset();
            }
        }
    }

    private function timeframeSeconds(string $timeframe): int
    {
        return match ($timeframe) {
            '1m' => 60, '5m' => 300, '15m' => 900, '30m' => 1800,
            '1h' => 3600, '2h' => 7200, '4h' => 14400, '1D' => 86400,
            default => 300,
        };
    }

    /**
     * Drives the lifecycle of every open trade against the latest known
     * price, using whatever updateMarketData() already fetched this tick —
     * no extra API calls.
     *
     *   open      -> stop hit            : closed as a loss
     *   open      -> TP1 hit             : "profit shot" + stop moved to
     *                                      entry (risk free), stays open
     *   risk_free -> TP2 hit             : closed as a win, slot freed
     *   risk_free -> back to entry       : closed at breakeven, no loss
     *
     * Freeing the slot is what lets the next signal go out: both the
     * per-symbol gate and the MAX_OPEN_POSITIONS gate read from here.
     */
    private function monitorOpenPositions(): void
    {
        foreach ($this->signalRepo->openPositions() as $row) {
            $price = $this->marketData->latestPrice((string) $row['exchange'], (string) $row['symbol']);
            if ($price <= 0) {
                continue;
            }

            $isLong = (string) $row['direction'] === 'LONG';
            $entry = (float) $row['entry_price'];
            // active_stop is NULL on rows written before this column existed.
            $stop = $row['active_stop'] !== null ? (float) $row['active_stop'] : (float) $row['stop_loss'];
            $tp1 = $row['tp1'] !== null ? (float) $row['tp1'] : null;
            $tp2 = $row['tp2'] !== null ? (float) $row['tp2'] : null;
            $stage = (string) ($row['stage'] ?? 'open');

            $reached = static fn(?float $target): bool => $target !== null && ($isLong ? $price >= $target : $price <= $target);
            $stopHit = $isLong ? $price <= $stop : $price >= $stop;

            if ($stage === 'risk_free') {
                // The stop is sitting at entry now, so "stop hit" here means
                // a breakeven exit, not a loss. It wins a tie against TP2 for
                // the same reason SL wins one below.
                if ($stopHit) {
                    $this->closeTrade($row, 'be', $price);
                } elseif ($reached($tp2)) {
                    $this->closeTrade($row, 'tp2', $price);
                }
                continue;
            }

            if ($stopHit) {
                $this->closeTrade($row, 'sl', $price);
                continue;
            }

            if ($reached($tp2)) {
                // A gap straight through both targets between two checks:
                // report the further one and close, rather than claiming a
                // risk-free stage the trade never actually spent time in.
                $this->closeTrade($row, 'tp2', $price);
                continue;
            }

            if (!$reached($tp1)) {
                continue;
            }

            if (!Config::riskFreeEnabled() || $tp2 === null) {
                $this->closeTrade($row, 'tp1', $price);
                continue;
            }

            $this->signalRepo->markRiskFree((int) $row['id'], $price, $entry);
            $this->announce($row, 'tp1', $price);
        }
    }

    /** @param array<string,mixed> $row */
    private function closeTrade(array $row, string $result, float $price): void
    {
        $this->signalRepo->close((int) $row['id'], $result, $price);
        $this->announce($row, $result, $price);
    }

    /** @param array<string,mixed> $row */
    private function announce(array $row, string $kind, float $price): void
    {
        try {
            $this->dispatcher->announceResult($row, $kind, $price);
        } catch (Throwable $e) {
            Logger::error('worker', 'result announcement failed', ['symbol' => $row['symbol'] ?? '', 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    /**
     * The pacing gate plus the search for what to publish.
     *
     * Two things hold the bot back, and both are deliberate: at most one
     * signal every SIGNAL_INTERVAL_SECONDS (15 minutes by default), and at
     * most MAX_OPEN_POSITIONS trades running at once. When the gate is
     * open, every active symbol is evaluated on every signal timeframe
     * (15m/1h/4h) and only the single highest-scoring candidate of the
     * whole pass is saved and sent — which is what "one good signal every
     * 15 minutes" means, as opposed to "every setup that passes".
     */
    private function maybeEmitSignal(): void
    {
        if ($this->signalRepo->countOpen() >= Config::maxOpenPositions()) {
            return;
        }
        $lastAt = $this->signalRepo->lastDispatchedAt();
        if ($lastAt !== null && (time() - $lastAt) < Config::signalIntervalSeconds()) {
            return;
        }
        // The daily circuit breaker. Nothing about a bad day makes the next
        // setup better, and a bot that keeps firing through a losing streak
        // is how an account is lost — so it stops until tomorrow instead.
        $today = $this->signalRepo->todayTally();
        if ($today['losses'] >= Config::maxDailyLosses()) {
            $this->dailyStop = 'losses';
            return;
        }
        $maxSignals = Config::maxDailySignals();
        if ($maxSignals > 0 && $today['published'] >= $maxSignals) {
            $this->dailyStop = 'quota';
            return;
        }
        $this->dailyStop = null;

        // How many of this pass's qualifying candidates may go out. Room
        // is left for the open-position and daily caps, which are the real
        // risk limits — this only decides how much of a good pass is used.
        $room = min(
            Config::signalsPerPass(),
            max(0, Config::maxOpenPositions() - $this->signalRepo->countOpen()),
            $maxSignals > 0 ? max(0, $maxSignals - $today['published']) : PHP_INT_MAX
        );
        if ($room < 1) {
            return;
        }

        foreach ($this->findBestCandidates($room) as $candidate) {
            Logger::info('worker', 'signal selected', [
                'symbol' => $candidate->symbol,
                'direction' => $candidate->direction->value,
                'timeframe' => $candidate->timeframe,
                'leverage' => $candidate->leverage,
                'score' => $candidate->score,
            ]);
            $this->queue->push($this->signalGenerator->persist($candidate));
        }
    }

    /**
     * The pass's best candidates, highest score first, at most one per
     * symbol.
     *
     * This walks the universe on a ROTATION rather than from the top every
     * time. The universe is now every perpetual above the volume floor —
     * several hundred coins — and a cron minute cannot sweep that. Starting
     * at the top each invocation would mean the first sixty coins are
     * checked every minute and the rest are never checked at all.
     *
     * So each invocation resumes where the last one stopped, visits as far
     * as its time budget allows, and persists the cursor. Every coin gets
     * looked at in turn, a few minutes apart, which is what "check them all,
     * about one a second" actually requires.
     *
     * Each symbol is synced and evaluated in one visit, so the HTTP call for
     * its candles is paid once and used immediately.
     *
     * @return Signal[]
     */
    private function findBestCandidates(int $limit): array
    {
        $timeframes = Config::signalTimeframes();
        $universe = $this->symbolRepo->universe(Config::signalMaxSymbolsPerPass());
        $total = count($universe);
        if ($total === 0) {
            $this->saveScanReport(0, 0, false, 0);
            return [];
        }

        $this->signalGenerator->resetObservations();
        $dueTimeframes = $this->dueTimeframes();
        $candleManager = $this->marketData->candleManager();
        $tickerCache = [];

        $cursor = $this->loadCursor() % $total;
        $budgetUntil = microtime(true) + Config::rotationBudgetSeconds();
        $maxSymbols = min(Config::rotationMaxSymbols(), $total);

        $evaluated = 0;
        $visited = 0;
        /** @var array<string,Signal> $bySymbol */
        $bySymbol = [];

        while ($visited < $maxSymbols) {
            if (!$this->running || microtime(true) >= $budgetUntil || $this->outOfDataBudget()) {
                break;
            }

            $row = $universe[$cursor];
            $cursor = ($cursor + 1) % $total;
            $visited++;

            $exchange = (string) $row['exchange'];
            $symbol = (string) $row['symbol'];
            if (!$this->exchangeManager->isHealthy($exchange)) {
                continue;
            }

            try {
                // Sync then evaluate, in one visit.
                //
                // The due-schedule is global (one next-run time per
                // timeframe), so it answers "is a refresh owed" — not "does
                // THIS coin have any candles". On a rotation those are very
                // different questions: the first invocation marks 15m as
                // refreshed for the next quarter hour, and every coin the
                // rotation reaches after that would be evaluated against an
                // empty candle table and silently skipped. Most of the
                // market would never get a first fetch at all.
                //
                // So a timeframe is synced when it is due OR when this
                // symbol has none of it yet.
                $sync = $dueTimeframes;
                foreach ($timeframes as $tf) {
                    if (!in_array($tf, $sync, true) && !$candleManager->hasAny($exchange, $symbol, $tf)) {
                        $sync[] = $tf;
                    }
                }
                if (!empty($sync)) {
                    $this->syncSymbolCandles($exchange, $symbol, $sync, 200);
                }
                $this->refreshTicker($tickerCache, $exchange, $symbol);

                $snapshot = $this->marketData->buildSnapshot($exchange, $symbol, $timeframes);
                if ($snapshot->price <= 0) {
                    continue;
                }
                $meta = [
                    'base_asset' => (string) ($row['base_asset'] ?? ''),
                    'volume_24h' => (float) ($row['volume_24h'] ?? 0),
                ];
                foreach ($timeframes as $timeframe) {
                    $evaluated++;
                    $candidate = $this->signalGenerator->evaluate($snapshot, $timeframe, $meta);
                    if ($candidate === null) {
                        continue;
                    }
                    // One per symbol: the same setup seen on 15m and on 1h
                    // is one trade, not two.
                    $key = $exchange . '|' . $candidate->symbol;
                    if (!isset($bySymbol[$key]) || $candidate->score > $bySymbol[$key]->score) {
                        $bySymbol[$key] = $candidate;
                    }
                }
            } catch (Throwable $e) {
                Logger::error('worker', 'signal pipeline failed for symbol', ['symbol' => $symbol, 'exchange' => $exchange, 'error' => $e->getMessage()]);
            }
        }

        $this->saveCursor($cursor);

        $ranked = array_values($bySymbol);
        usort($ranked, static fn(Signal $a, Signal $b) => $b->score <=> $a->score);
        $chosen = array_slice($ranked, 0, max(1, $limit));

        $this->saveScanReport($evaluated, $total, !empty($chosen), count($ranked), $visited, $cursor);
        return $chosen;
    }

    /**
     * Which timeframes are due a candle refresh this invocation. Pulled out
     * of updateMarketData() so the rotation can sync a symbol at the moment
     * it visits it instead of in a separate sweep.
     *
     * @return string[]
     */
    private function dueTimeframes(): array
    {
        $now = time();
        $due = [];
        foreach (Config::signalTimeframes() as $tf) {
            if ($now >= ($this->nextTimeframeRun[$tf] ?? 0)) {
                $due[] = $tf;
                $this->nextTimeframeRun[$tf] = $now + $this->timeframeSeconds($tf);
            }
        }
        if (!empty($due)) {
            $this->saveTimeframeSchedule($this->nextTimeframeRun);
        }
        return $due;
    }

    /** Where the rotation stopped last invocation. */
    private function loadCursor(): int
    {
        try {
            $stmt = Database::pdo()->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'scan_cursor'");
            $stmt->execute();
            $v = $stmt->fetchColumn();
            return $v === false || $v === null ? 0 : max(0, (int) $v);
        } catch (Throwable) {
            return 0;
        }
    }

    private function saveCursor(int $cursor): void
    {
        try {
            Database::pdo()->prepare(
                "INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES ('scan_cursor', :v, :now)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at"
            )->execute([':v' => (string) $cursor, ':now' => date('Y-m-d H:i:s')]);
        } catch (Throwable $e) {
            Logger::warning('worker', 'could not persist the scan cursor', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Records what the pass saw, so a bot that is simply not finding setups
     * can say so with numbers instead of staying silent and looking broken.
     */
    private function saveScanReport(
        int $evaluated,
        int $symbolCount,
        bool $published,
        int $qualified = 0,
        int $visited = 0,
        int $cursor = 0,
    ): void {
        $observation = $this->signalGenerator->bestObservation();
        $report = [
            'at' => time(),
            'symbols' => $symbolCount,
            'evaluated' => $evaluated,
            'published' => $published,
            'qualified' => $qualified,
            'visited' => $visited,
            'cursor' => $cursor,
            'min_score' => Config::minSignalScore(),
            'best' => $observation,
            'daily_stop' => $this->dailyStop,
        ];
        Database::pdo()->prepare(
            'INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES (\'last_scan_report\', :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at'
        )->execute([':v' => json_encode($report, JSON_UNESCAPED_UNICODE), ':now' => date('Y-m-d H:i:s')]);
    }

    private function processQueue(): void
    {
        if ($this->queue->isEmpty()) {
            return;
        }
        foreach ($this->queue->drain() as $signal) {
            try {
                $this->dispatcher->dispatch($signal);
            } catch (Throwable $e) {
                Logger::error('worker', 'dispatch failed', ['symbol' => $signal->symbol, 'error' => $e->getMessage()]);
            }
        }
    }
}

// ============================================================================
// SECTION 5 — ENTRYPOINT
//
// Guarded so the file can also be require()d for inspection (tests, tooling)
// without starting a worker. Running `php worker.php` is unaffected.
// ============================================================================

if (!defined('WORKER_BOOTSTRAP_ONLY')) {
    (new Worker())->run();
}

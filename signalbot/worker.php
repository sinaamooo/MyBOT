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

final class LockFile
{
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

final class SignalQueue
{
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

final class TelegramDispatcher
{
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
        $anySent = false;

        try {
            $card = $dryRun ? null : SignalCardFactory::entry($signal);

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
                        try {
                            $this->telegram->pinChatMessage((int) $channel['chat_id'], $sent);
                        } catch (Throwable $e) {
                            Logger::warning('dispatcher', 'pin failed', ['chat_id' => $channel['chat_id'], 'error' => $e->getMessage()]);
                        }
                    }
                }
            }
        } finally {
            $this->signalRepo->updateStatus($signal->id, $dryRun ? 'queued' : ($anySent ? 'sent' : 'failed'));
        }
    }

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

    public function announceAdvisory(array $signalRow, string $text): void
    {
        $targets = $this->announcementTargets((int) $signalRow['id']);
        if (empty($targets)) {
            Logger::error('dispatcher', 'advisory had nowhere to go', ['signal_id' => $signalRow['id'] ?? null]);
            return;
        }
        foreach ($targets as $target) {
            $opts = [];
            if ($target['message_id'] > 0) {
                $opts['reply_parameters'] = [
                    'message_id' => $target['message_id'],
                    'allow_sending_without_reply' => true,
                ];
            }
            $this->deliver($target['chat_id'], $text, [], null, $opts, (int) $signalRow['id'], $target['channel_id']);
        }
    }

    private function announcementTargets(int $signalId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT se.channel_id, MIN(se.message_id) AS message_id, c.chat_id
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

    private function deliver(int $chatId, string $text, array $entities, ?string $card, array $opts, int $signalId, int $channelId): ?int
    {
        if (!isset($opts['reply_markup'])) {
            $buttons = Config::channelButtonsKeyboard();
            if ($buttons !== null) {
                $opts['reply_markup'] = $buttons;
            }
        }

        if ($card === null) {
            return $this->sendWithRetry($chatId, $text, $entities, $opts, $signalId, $channelId);
        }

        $fitsInCaption = TelegramEntityUtils::utf16Length($text) <= self::CAPTION_LIMIT;
        $caption = $fitsInCaption ? $text : '';
        $captionEntities = $fitsInCaption ? $entities : [];

        $messageId = $this->sendPhotoWithRetry($chatId, $card, $caption, $captionEntities, $opts, $signalId, $channelId);
        if ($messageId === null) {
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
            $opts = $this->degradeButtonEmoji($opts, $error, $chatId);
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
            $opts = $this->degradeButtonEmoji($opts, $error, $chatId);
            usleep(500_000 * $attempt);
        }
        $this->logEvent($signalId, $channelId, 'failed', null, 'max attempts reached', $maxAttempts);
        return null;
    }

    private function degradeEntities(array $entities, string $error, int $chatId): array
    {
        if (!TelegramEntityUtils::hasCustomEmoji($entities)) {
            return $entities;
        }
        Logger::warning('dispatcher', 'retrying without custom emoji', ['chat_id' => $chatId, 'error' => $error]);
        return TelegramEntityUtils::stripCustomEmoji($entities);
    }

    private function degradeButtonEmoji(array $opts, string $error, int $chatId): array
    {
        $keyboard = $opts['reply_markup'] ?? null;
        if (!is_array($keyboard) || !TelegramEntityUtils::hasButtonEmojiIcon($keyboard)) {
            return $opts;
        }
        Logger::warning('dispatcher', 'retrying without button emoji icon', ['chat_id' => $chatId, 'error' => $error]);
        $opts['reply_markup'] = TelegramEntityUtils::stripButtonEmojiIcons($keyboard);
        return $opts;
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
        $stmt = Database::pdo()->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'run_mode'");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        return $v !== false && $v !== '' ? (string) $v : Config::runMode()->value;
    }
}

final class Worker
{
    private ExchangeManager $exchangeManager;
    private MarketDataStore $marketData;
    private MarketScanner $scanner;
    private SignalGenerator $signalGenerator;
    private SignalQueue $queue;
    private TelegramDispatcher $dispatcher;
    private TextFormatManager $texts;
    private SymbolRepository $symbolRepo;
    private SignalRepository $signalRepo;

    private bool $running = true;
    private int $lastScanAt = 0;

    private int $deadline = PHP_INT_MAX;

    private const TICK_RESERVE_SECONDS = 15;

    private const CANDLE_HISTORY = 200;

    private const CANDLE_KEEP = 500;

    private ?string $dailyStop = null;

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
        $this->texts = new TextFormatManager();
        $this->symbolRepo = new SymbolRepository();
        $this->scannerRunRepo = new ScannerRunRepository();

        foreach ($this->exchangeManager->adapters() as $name => $adapter) {
            $this->exchangeBackoff[$name] = new Backoff();
        }

        $lastScan = $this->scannerRunRepo->lastRunAt();
        $this->lastScanAt = $lastScan !== null ? (int) strtotime($lastScan) : 0;

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
                pcntl_alarm(0);
            }
            $lock->release();
        }
    }

    private function runLoop(string $mode): void
    {
        $deadline = $mode === 'daemon' ? PHP_INT_MAX : (time() + Config::workerMaxRuntimeSeconds());
        $this->deadline = $deadline;

        $this->heartbeat();

        try {
            Database::maintain();
        } catch (Throwable $e) {
            Logger::warning('worker', 'database maintenance skipped', ['error' => $e->getMessage()]);
        }

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

    private function primeMarketData(): void
    {
        foreach ($this->signalRepo->openPositionSymbols() as $open) {
            if (!$this->running || $this->outOfDataBudget()) {
                break;
            }
            $this->syncFreshCandles((string) $open['exchange'], (string) $open['symbol'], Config::signalTimeframes());
        }
    }

    private function outOfDataBudget(): bool
    {
        if ($this->deadline === PHP_INT_MAX) {
            return false;
        }
        return time() >= ($this->deadline - self::TICK_RESERVE_SECONDS);
    }

    private function updateMarketData(): void
    {
        $tickerCache = [];
        foreach ($this->signalRepo->openPositionSymbols() as $open) {
            if (!$this->running || $this->outOfDataBudget()) {
                return;
            }
            $this->refreshTicker($tickerCache, (string) $open['exchange'], (string) $open['symbol']);
        }
    }

    private function refreshTicker(array &$cache, string $exchange, string $symbol): void
    {
        if (!$this->exchangeManager->isHealthy($exchange)) {
            return;
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

    private function syncFreshCandles(string $exchange, string $symbol, array $timeframes): void
    {
        $candleManager = $this->marketData->candleManager();
        $now = time();
        foreach (array_unique($timeframes) as $tf) {
            $step = Candle::timeframeSeconds($tf);
            $last = $candleManager->latestOpenTime($exchange, $symbol, $tf);
            if ($last !== null && intdiv($last, 1000) + $step > $now) {
                continue;
            }
            $limit = $last === null
                ? self::CANDLE_HISTORY
                : min(self::CANDLE_HISTORY, intdiv($now - intdiv($last, 1000), $step) + 2);
            $candles = $this->exchangeManager->withIsolation($exchange, fn(ExchangeAdapter $a) => $a->fetchCandles($symbol, $tf, $limit));
            if (is_array($candles) && !empty($candles)) {
                $candleManager->upsertMany($exchange, $symbol, $tf, $candles);
                $candleManager->pruneSeries($exchange, $symbol, $tf, $limit >= self::CANDLE_HISTORY ? self::CANDLE_HISTORY : self::CANDLE_KEEP);
                $this->exchangeBackoff[$exchange]->reset();
            }
        }
    }

    private function monitorOpenPositions(): void
    {
        foreach ($this->signalRepo->openPositions() as $row) {
            $price = $this->marketData->latestPrice((string) $row['exchange'], (string) $row['symbol']);
            $expired = (string) ($row['stage'] ?? 'open') === 'open' && $this->tradeExpired($row);
            if ($price <= 0) {
                if ($expired) {
                    $this->closeTrade($row, 'timeout', (float) $row['entry_price']);
                }
                continue;
            }

            $isLong = (string) $row['direction'] === 'LONG';
            $entry = (float) $row['entry_price'];
            $stop = $row['active_stop'] !== null ? (float) $row['active_stop'] : (float) $row['stop_loss'];
            $tp1 = $row['tp1'] !== null ? (float) $row['tp1'] : null;
            $tp2 = $row['tp2'] !== null ? (float) $row['tp2'] : null;
            $tp3 = $row['tp3'] !== null ? (float) $row['tp3'] : null;
            $tp4 = $row['tp4'] !== null ? (float) $row['tp4'] : null;
            $stage = (string) ($row['stage'] ?? 'open');

            $reached = static fn(?float $target): bool => $target !== null && ($isLong ? $price >= $target : $price <= $target);
            $stopHit = $isLong ? $price <= $stop : $price >= $stop;
            $moreFavourable = static fn(float $a, float $b): float => $isLong ? max($a, $b) : min($a, $b);

            if ($reached($tp4)) {
                $this->closeTrade($row, 'tp4', $price);
                continue;
            }

            if ($stage === 'trailing_tp3') {
                if ($stopHit) {
                    $this->closeTrade($row, 'trail', $price);
                    continue;
                }
                $priorPeak = $row['peak_price'] !== null ? (float) $row['peak_price'] : $price;
                $peak = $moreFavourable($priorPeak, $price);
                if ($peak !== $priorPeak) {
                    $this->signalRepo->updatePeak((int) $row['id'], $peak);
                }
                if (($row['stall_advisory_sent_at'] ?? null) === null && $tp3 !== null && $row['tp2_hit_price'] !== null) {
                    $leg = abs($tp3 - (float) $row['tp2_hit_price']);
                    $retracedPct = $leg > 0 ? (abs($peak - $price) / $leg) * 100 : 0.0;
                    if ($leg > 0 && $retracedPct >= Config::advisoryStallRetracePercent()) {
                        $this->sendAdvisory($row, $this->advisoryText(
                            'advisory_stall_warning',
                            "🎯 {symbol} بعد از تارگت ۳ داره برمی‌گرده و هنوز به تارگت ۴ نرسیده.\nشاید بهتر باشه همینجا سود رو قفل کنی به‌جای ریسک کردن رو ادامه حرکت.",
                            (string) $row['symbol']
                        ), 'stall');
                    }
                }
                continue;
            }

            if ($stage === 'trailing_tp2') {
                if ($stopHit) {
                    $this->closeTrade($row, 'trail', $price);
                    continue;
                }
                if ($reached($tp3)) {
                    if ($tp4 !== null && $row['tp2_hit_price'] !== null) {
                        $this->signalRepo->markTrailingTp3((int) $row['id'], $price, (float) $row['tp2_hit_price']);
                        $this->announce($row, 'tp3', $price);
                    } else {
                        $this->closeTrade($row, 'tp3', $price);
                    }
                }
                continue;
            }

            if ($stage === 'risk_free') {
                if ($stopHit) {
                    $this->closeTrade($row, 'be', $price);
                    continue;
                }
                if ($reached($tp2)) {
                    if ($tp3 !== null && $row['tp1_hit_price'] !== null) {
                        $this->signalRepo->markTrailingTp2((int) $row['id'], $price, (float) $row['tp1_hit_price']);
                        $this->announce($row, 'tp2', $price);
                    } else {
                        $this->closeTrade($row, 'tp2', $price);
                    }
                }
                continue;
            }

            if ($stopHit) {
                $this->closeTrade($row, 'sl', $price);
                continue;
            }

            if ($reached($tp1)) {
                if (Config::riskFreeEnabled() && $tp2 !== null) {
                    $this->signalRepo->markRiskFree((int) $row['id'], $price, $entry);
                    $this->announce($row, 'tp1', $price);
                } else {
                    $this->closeTrade($row, 'tp1', $price);
                }
                continue;
            }

            if ($expired) {
                $this->closeTrade($row, 'timeout', $price);
                continue;
            }

            if ($row['advisory_sent_at'] === null) {
                $adverse = $isLong ? $entry - $price : $price - $entry;
                $riskDistance = abs($entry - (float) $row['stop_loss']);
                if ($adverse > 0 && $riskDistance > 0 && ($adverse / $riskDistance) * 100 >= Config::advisoryStopWarnPercent()) {
                    $this->sendAdvisory($row, $this->advisoryText(
                        'advisory_stop_warning',
                        "⚠️ {symbol} داره به سمت حد ضرر می‌ره و هنوز به تارگت ۱ نرسیده.\nبا احتیاط بیشتر رصدش کن.",
                        (string) $row['symbol']
                    ));
                }
            }
        }
    }

    private function closeTrade(array $row, string $result, float $price): void
    {
        $this->signalRepo->close((int) $row['id'], $result, $price);
        $this->announce($row, $result, $price);
    }

    private function tradeExpired(array $row): bool
    {
        $hours = Config::maxTradeHours();
        if ($hours <= 0) {
            return false;
        }
        $opened = strtotime((string) ($row['created_at'] ?? ''));
        return $opened !== false && time() - $opened >= $hours * 3600;
    }

    private function advisoryText(string $key, string $default, string $symbol): string
    {
        $template = $this->texts->get($key);
        $templateText = $template['text'] !== '' ? $template['text'] : $default;
        $rendered = TelegramEntityUtils::renderTemplate($templateText, $template['entities'], [
            'symbol' => SignalCardFactory::displaySymbol($symbol),
        ]);
        return $rendered['text'];
    }

    private function sendAdvisory(array $row, string $text, string $kind = 'early'): void
    {
        if ($kind === 'stall') {
            $this->signalRepo->markStallAdvisorySent((int) $row['id']);
        } else {
            $this->signalRepo->markAdvisorySent((int) $row['id']);
        }
        try {
            $this->dispatcher->announceAdvisory($row, $text);
        } catch (Throwable $e) {
            Logger::error('worker', 'advisory send failed', ['symbol' => $row['symbol'] ?? '', 'error' => $e->getMessage()]);
        }
    }

    private function announce(array $row, string $kind, float $price): void
    {
        try {
            $this->dispatcher->announceResult($row, $kind, $price);
        } catch (Throwable $e) {
            Logger::error('worker', 'result announcement failed', ['symbol' => $row['symbol'] ?? '', 'kind' => $kind, 'error' => $e->getMessage()]);
        }
    }

    private function maybeEmitSignal(): void
    {
        $today = $this->signalRepo->todayTally();
        if ($today['losses'] >= Config::maxDailyLosses()) {
            $this->dailyStop = 'losses';
            $this->recordGateStatus('daily_stop');
            return;
        }
        $maxSignals = Config::maxDailySignals();
        if ($maxSignals > 0 && $today['published'] >= $maxSignals) {
            $this->dailyStop = 'quota';
            $this->recordGateStatus('daily_stop');
            return;
        }
        $this->dailyStop = null;

        $maxOpen = Config::maxOpenTrades();
        $openNow = $this->signalRepo->countOpen();
        if ($maxOpen > 0 && $openNow >= $maxOpen) {
            $this->recordGateStatus('max_open');
            return;
        }

        $gapMinutes = Config::minSignalGapMinutes();
        if ($gapMinutes > 0) {
            $lastPublished = $this->signalRepo->lastPublishedAt();
            if ($lastPublished !== null && time() - $lastPublished < $gapMinutes * 60) {
                $this->recordGateStatus('pacing');
                return;
            }
        }

        $room = min(
            Config::signalsPerPass(),
            $maxSignals > 0 ? max(0, $maxSignals - $today['published']) : PHP_INT_MAX,
            $maxOpen > 0 ? max(0, $maxOpen - $openNow) : PHP_INT_MAX
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

    private function findBestCandidates(int $limit): array
    {
        $timeframes = Config::signalTimeframes();

        $confirmTf = Config::reversalConfirmTimeframe();
        $snapshotTimeframes = in_array($confirmTf, $timeframes, true) ? $timeframes : [...$timeframes, $confirmTf];

        foreach ($timeframes as $tf) {
            $htfTf = Config::htfConfirmTimeframe($tf);
            if ($htfTf !== null && !in_array($htfTf, $snapshotTimeframes, true)) {
                $snapshotTimeframes[] = $htfTf;
            }
        }
        $universe = $this->symbolRepo->universe(Config::signalMaxSymbolsPerPass());
        $total = count($universe);
        if ($total === 0) {
            $this->saveScanReport(0, 0, false, 0);
            return [];
        }

        $this->signalGenerator->resetObservations();
        $tickerCache = [];

        $cursor = $this->loadCursor() % $total;
        $budgetUntil = microtime(true) + Config::rotationBudgetSeconds();
        $maxSymbols = min(Config::rotationMaxSymbols(), $total);

        $evaluated = 0;
        $visited = 0;
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
                $this->syncFreshCandles($exchange, $symbol, $snapshotTimeframes);
                $this->refreshTicker($tickerCache, $exchange, $symbol);

                $snapshot = $this->marketData->buildSnapshot($exchange, $symbol, $snapshotTimeframes);
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

    private function recordGateStatus(string $gate): void
    {
        $stmt = Database::pdo()->prepare("SELECT setting_value FROM bot_settings WHERE setting_key = 'last_scan_report'");
        $stmt->execute();
        $v = $stmt->fetchColumn();
        $prior = $v !== false && $v !== '' ? json_decode((string) $v, true) : null;
        $prior = is_array($prior) ? $prior : [];

        $report = [
            'at' => time(),
            'symbols' => $prior['symbols'] ?? 0,
            'evaluated' => $prior['evaluated'] ?? 0,
            'published' => false,
            'qualified' => $prior['qualified'] ?? 0,
            'visited' => $prior['visited'] ?? 0,
            'cursor' => $prior['cursor'] ?? 0,
            'min_score' => Config::minSignalScore(),
            'best' => $prior['best'] ?? null,
            'gate' => $gate,
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

if (!defined('WORKER_BOOTSTRAP_ONLY')) {
    (new Worker())->run();
}

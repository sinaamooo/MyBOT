<?php

declare(strict_types=1);

/**
 * ============================================================================
 * config.php — Configuration, Environment, Database bootstrap, Logger and
 * shared low-level Telegram entity/text utilities.
 *
 * No trading/business logic lives here. This file only:
 *   - loads environment variables (env.php, or classic .env text file)
 *   - exposes typed configuration via Config::
 *   - owns the single PDO connection + schema migration via Database::
 *   - provides a minimal structured Logger:: (never logs secrets)
 *   - provides TelegramEntityUtils:: (UTF-16 safe entity math), shared by
 *     bot.php (TextFormatManager) and signal.php (SignalFormatter) so both
 *     can render {placeholder} templates without corrupting Telegram
 *     message entities (premium custom emoji, bold, spoilers, etc).
 * ============================================================================
 */

// ============================================================================
// SECTION 0 — EXCEPTIONS
// ============================================================================

class AppException extends RuntimeException
{
}

final class ConfigException extends AppException
{
}

final class DatabaseException extends AppException
{
}

// ============================================================================
// SECTION 1 — ENV LOADER
// ============================================================================

final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    /**
     * $dir is the project root. Two supported sources, both optional and
     * mergeable (env.php values win on conflict):
     *
     *   env.php  — `<?php return ['KEY' => 'value', ...];`. Preferred: it
     *              has no leading-dot filename (some cPanel File Manager
     *              builds cannot create/rename to a dotfile correctly —
     *              they turn ".env" into "env." instead) and, being a
     *              .php file, the webserver always executes it rather
     *              than ever serving it as a downloadable text file, so
     *              it needs no extra .htaccess protection to stay safe.
     *   .env     — classic KEY=value text file, for hosts where a
     *              leading-dot filename works fine.
     */
    public static function load(string $dir): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        $phpFile = $dir . '/env.php';
        if (is_file($phpFile)) {
            $data = require $phpFile;
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    self::$vars[(string) $key] = (string) $value;
                    if (getenv((string) $key) === false) {
                        putenv($key . '=' . $value);
                    }
                }
            }
        }

        $envFile = $dir . '/.env';
        if (is_file($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (!str_contains($line, '=')) {
                        continue;
                    }
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    if (strlen($value) >= 2) {
                        $first = $value[0];
                        $last = $value[strlen($value) - 1];
                        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                            $value = substr($value, 1, -1);
                        }
                    }
                    self::$vars[$key] = $value;
                    if (getenv($key) === false) {
                        putenv($key . '=' . $value);
                    }
                }
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $fromEnv = getenv($key);
        if ($fromEnv !== false && $fromEnv !== '') {
            return $fromEnv;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        return self::$vars[$key] ?? $default;
    }

    public static function getInt(string $key, int $default): int
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (int) $v;
    }

    public static function getFloat(string $key, float $default): float
    {
        $v = self::get($key);
        return $v === null || $v === '' ? $default : (float) $v;
    }

    public static function getBool(string $key, bool $default): bool
    {
        $v = self::get($key);
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** @return string[] */
    public static function getList(string $key, string $default = ''): array
    {
        $v = self::get($key, $default) ?? '';
        if (trim($v) === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $v)), static fn($x) => $x !== ''));
    }
}

Env::load(__DIR__);

// ============================================================================
// SECTION 2 — APP CONFIG
// ============================================================================

enum RunMode: string
{
    case LIVE = 'LIVE';
    case DRY_RUN = 'DRY_RUN';
}

final class Config
{
    // -- Telegram ------------------------------------------------------
    public static function telegramBotToken(): string
    {
        $t = Env::get('TELEGRAM_BOT_TOKEN', '');
        if ($t === '' || $t === null) {
            throw new ConfigException('TELEGRAM_BOT_TOKEN is not set in environment/.env');
        }
        return $t;
    }

    public static function telegramWebhookSecret(): string
    {
        return Env::get('TELEGRAM_WEBHOOK_SECRET', '') ?? '';
    }

    public static function telegramApiBase(): string
    {
        return 'https://api.telegram.org/bot' . self::telegramBotToken();
    }

    /** @return int[] Telegram numeric user IDs allowed into the admin panel */
    public static function adminIds(): array
    {
        return array_map('intval', Env::getList('ADMIN_IDS', ''));
    }

    // -- Database --------------------------------------------------------
    /**
     * SQLite storage. Everything lives in one file inside a self-created,
     * self-protected "storage/" directory next to the 4 project files —
     * no separate database server/credentials needed (this is what makes
     * the project deployable on plain cPanel shared hosting with no SSH).
     */
    public static function storageDir(): string
    {
        return rtrim(Env::get('STORAGE_DIR', __DIR__ . '/storage') ?? (__DIR__ . '/storage'), '/');
    }

    public static function dbPath(): string
    {
        return self::storageDir() . '/' . (Env::get('DB_FILE', 'database.sqlite') ?? 'database.sqlite');
    }

    // -- Exchanges ---------------------------------------------------------
    /**
     * Admin-panel-set credentials (stored in bot_settings, editable via
     * 📡 صرافی‌ها → 🔑 API without touching env.php) take precedence over
     * whatever env.php/.env has, falling back to it when nothing was set
     * through the panel. Never throws — DB may not be migrated yet the
     * very first time Config is touched.
     */
    private static function dbOverride(string $key): ?string
    {
        try {
            $stmt = Database::pdo()->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
            $stmt->execute([':k' => $key]);
            $v = $stmt->fetchColumn();
            return $v !== false && $v !== '' ? (string) $v : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function binanceApiKey(): string
    {
        return self::dbOverride('BINANCE_API_KEY') ?? (Env::get('BINANCE_API_KEY', '') ?? '');
    }

    public static function binanceApiSecret(): string
    {
        return self::dbOverride('BINANCE_API_SECRET') ?? (Env::get('BINANCE_API_SECRET', '') ?? '');
    }

    public static function binanceRestBase(): string
    {
        return Env::get('BINANCE_REST_BASE', 'https://api.binance.com') ?? 'https://api.binance.com';
    }

    public static function binanceWsBase(): string
    {
        return Env::get('BINANCE_WS_BASE', 'wss://stream.binance.com:9443') ?? 'wss://stream.binance.com:9443';
    }

    public static function mexcApiKey(): string
    {
        return self::dbOverride('MEXC_API_KEY') ?? (Env::get('MEXC_API_KEY', '') ?? '');
    }

    public static function mexcApiSecret(): string
    {
        return self::dbOverride('MEXC_API_SECRET') ?? (Env::get('MEXC_API_SECRET', '') ?? '');
    }

    public static function mexcRestBase(): string
    {
        return Env::get('MEXC_REST_BASE', 'https://api.mexc.com') ?? 'https://api.mexc.com';
    }

    public static function mexcWsBase(): string
    {
        return Env::get('MEXC_WS_BASE', 'wss://wbs.mexc.com') ?? 'wss://wbs.mexc.com';
    }

    public static function gateRestBase(): string
    {
        return Env::get('GATE_REST_BASE', 'https://api.gateio.ws') ?? 'https://api.gateio.ws';
    }

    public static function bitgetRestBase(): string
    {
        return Env::get('BITGET_REST_BASE', 'https://api.bitget.com') ?? 'https://api.bitget.com';
    }

    public static function htxRestBase(): string
    {
        return Env::get('HTX_REST_BASE', 'https://api.huobi.pro') ?? 'https://api.huobi.pro';
    }

    /**
     * CryptoCompare is a market-data aggregator, not an exchange with its
     * own trading/compliance obligations -- unlike Binance/MEXC it's a
     * realistic route around a host IP being geo-blocked by an exchange
     * directly. Free tier works without a key at low volume; set one for
     * higher rate limits.
     */
    public static function cryptocompareApiKey(): string
    {
        return self::dbOverride('CRYPTOCOMPARE_API_KEY') ?? (Env::get('CRYPTOCOMPARE_API_KEY', '') ?? '');
    }

    public static function cryptocompareRestBase(): string
    {
        return Env::get('CRYPTOCOMPARE_REST_BASE', 'https://min-api.cryptocompare.com') ?? 'https://min-api.cryptocompare.com';
    }

    /**
     * Bybit / OKX / KuCoin: large, high-volume exchanges whose public spot
     * market-data endpoints need no API key. Every exchange is isolated via
     * ExchangeManager's circuit breaker, so one being blocked or down just
     * means it contributes 0 symbols instead of breaking anything else.
     */
    public static function bybitRestBase(): string
    {
        return Env::get('BYBIT_REST_BASE', 'https://api.bybit.com') ?? 'https://api.bybit.com';
    }

    public static function okxRestBase(): string
    {
        return Env::get('OKX_REST_BASE', 'https://www.okx.com') ?? 'https://www.okx.com';
    }

    public static function kucoinRestBase(): string
    {
        return Env::get('KUCOIN_REST_BASE', 'https://api.kucoin.com') ?? 'https://api.kucoin.com';
    }

    /** @return string[] enabled exchange names, in priority order */
    public static function enabledExchanges(): array
    {
        return Env::getList('ENABLED_EXCHANGES', 'binance,mexc,bybit,okx,kucoin,gate,bitget,htx,cryptocompare');
    }

    // -- Scanner -------------------------------------------------------
    // Every scanner filter below is admin-panel-editable (📊 Scanner →
    // ⚙️ تنظیمات فیلتر), stored via the same DB-override mechanism as the
    // exchange API keys, so they can be tuned live from Telegram without
    // touching env.php — deliberately, since the "right" threshold depends
    // on which exchange/market is actually reachable and varies a lot
    // (Binance-scale liquidity vs. a smaller regional exchange).
    public static function scannerTopN(): int
    {
        $override = self::dbOverride('SCANNER_TOP_N');
        return $override !== null ? (int) $override : Env::getInt('SCANNER_TOP_N', 200);
    }

    public static function scannerIntervalSeconds(): int
    {
        return Env::getInt('SCANNER_INTERVAL_SECONDS', 3600);
    }

    public static function minVolumeUsdt(): float
    {
        $override = self::dbOverride('MIN_VOLUME_USDT');
        return $override !== null ? (float) $override : Env::getFloat('MIN_VOLUME_USDT', 20_000.0);
    }

    public static function maxSpreadPercent(): float
    {
        $override = self::dbOverride('MAX_SPREAD_PERCENT');
        return $override !== null ? (float) $override : Env::getFloat('MAX_SPREAD_PERCENT', 0.5);
    }

    /**
     * Quote assets a pair may be priced in. Returning [] means no filter at
     * all, which only happens when the value is the explicit '*' sentinel.
     *
     * An EMPTY setting resolves to the dollar-stablecoin set rather than to
     * "allow everything". Allowing everything sounds harmless and is not:
     * regional exchanges list fiat pairs, so an unfiltered scan ranks things
     * like USDTTMN — Tether priced in Iranian toman — as a tradable symbol
     * and happily publishes leveraged "signals" on a currency peg. This bot
     * trades crypto against a dollar stablecoin; that is the default.
     *
     * @return string[]
     */
    public const DEFAULT_QUOTE_ASSETS = ['USDT', 'USDC', 'FDUSD', 'BUSD', 'TUSD', 'DAI'];

    public static function allowedQuoteAssets(): array
    {
        $override = self::dbOverride('ALLOWED_QUOTE_ASSETS');
        $raw = $override ?? (Env::get('ALLOWED_QUOTE_ASSETS', '') ?? '');

        if (trim($raw) === '*') {
            return []; // explicit "no filter"
        }
        $list = array_values(array_filter(array_map(
            static fn($x) => strtoupper(trim($x)),
            explode(',', $raw)
        ), static fn($x) => $x !== ''));

        return empty($list) ? self::DEFAULT_QUOTE_ASSETS : $list;
    }

    /**
     * Quote currencies that are never tradable here, whatever
     * ALLOWED_QUOTE_ASSETS says — including when it says '*'.
     *
     * These are national currencies. A pair quoted in one is a fiat on-ramp
     * rate, not a leveraged crypto instrument: the bot published signals on
     * USDTTMN (toman) and USDTBRL (real), which are currency pegs. The
     * allow-list alone did not stop it, because an operator who wants "all
     * crypto quotes" reaches for '*' and gets fiat with it.
     *
     * @return string[]
     */
    public static function blockedQuoteAssets(): array
    {
        $custom = Env::getList('BLOCKED_QUOTE_ASSETS', '');
        if (!empty($custom)) {
            return array_map('strtoupper', $custom);
        }
        return [
            'TMN', 'IRT', 'IRR', 'TRY', 'BRL', 'RUB', 'UAH', 'ARS', 'NGN', 'ZAR',
            'EUR', 'GBP', 'JPY', 'KRW', 'CNY', 'INR', 'IDR', 'VND', 'THB', 'PHP',
            'MXN', 'COP', 'PLN', 'CZK', 'RON', 'HUF', 'AUD', 'CAD', 'CHF', 'SEK',
            'NOK', 'DKK', 'AED', 'SAR', 'EGP', 'PKR', 'BDT', 'KZT', 'GEL', 'AZN',
        ];
    }

    public static function includeStablecoinPairs(): bool
    {
        return Env::getBool('INCLUDE_STABLECOIN_PAIRS', false);
    }

    // -- Timeframes ------------------------------------------------------
    /** @return string[] */
    public static function timeframes(): array
    {
        return Env::getList('TIMEFRAMES', '15m,30m,1h,2h');
    }

    // -- Signal thresholds -------------------------------------------------
    /**
     * Minimum CONVICTION for a signal to be publishable.
     *
     * Note the scale changed with the confluence rework: this used to be a
     * directional score where >=60 meant bullish, which made 75 a sane-
     * looking (but short-blocking) threshold. It is now symmetric
     * conviction — 0 is "no evidence either way", 100 is "everything
     * aligns" — so a long and an equally strong short are gated identically.
     */
    public static function minSignalScore(): float
    {
        $override = self::dbOverride('MIN_SIGNAL_SCORE');
        return $override !== null ? (float) $override : Env::getFloat('MIN_SIGNAL_SCORE', 45.0);
    }

    public static function cooldownSeconds(): int
    {
        return Env::getInt('SIGNAL_COOLDOWN_SECONDS', 1800);
    }

    public static function minRiskReward(): float
    {
        $override = self::dbOverride('MIN_RISK_REWARD');
        return $override !== null ? (float) $override : Env::getFloat('MIN_RISK_REWARD', 1.2);
    }

    // -- Confluence weights (configurable, sums need not be 100 — normalized at use) --
    /** @return array<string,float> */
    public static function confluenceWeights(): array
    {
        return [
            'trend'      => Env::getFloat('WEIGHT_TREND', 20.0),
            'support'    => Env::getFloat('WEIGHT_SUPPORT', 15.0),
            'resistance' => Env::getFloat('WEIGHT_RESISTANCE', 15.0),
            'order_block'=> Env::getFloat('WEIGHT_ORDER_BLOCK', 20.0),
            'fvg'        => Env::getFloat('WEIGHT_FVG', 15.0),
            'volume'     => Env::getFloat('WEIGHT_VOLUME', 10.0),
            'indicators' => Env::getFloat('WEIGHT_INDICATORS', 5.0),
        ];
    }

    // -- Automatic trading policy -------------------------------------------
    // These are what make the bot run unattended: how often it is allowed
    // to speak, which timeframes it may speak about, how much leverage it
    // announces per coin, and where the targets sit. All of them are
    // panel-editable (🤖 اتومات) through the same DB-override mechanism as
    // the scanner filters, so they can be retuned from Telegram without
    // touching env.php.

    /** Minimum gap between two dispatched signals. One signal per 15 minutes by default. */
    public static function signalIntervalSeconds(): int
    {
        $override = self::dbOverride('SIGNAL_INTERVAL_SECONDS');
        return $override !== null ? max(60, (int) $override) : max(60, Env::getInt('SIGNAL_INTERVAL_SECONDS', 900));
    }

    /**
     * How many trades may be open at once. 1 is the strict reading of
     * "after TP2, move to the next trade" — the bot then runs one position
     * at a time and stays silent until it closes. The default of 3 keeps
     * the 15-minute cadence meaningful when a trade runs for hours, while
     * the per-symbol gate still prevents stacking on the same coin.
     */
    public static function maxOpenPositions(): int
    {
        $override = self::dbOverride('MAX_OPEN_POSITIONS');
        return $override !== null ? max(1, (int) $override) : max(1, Env::getInt('MAX_OPEN_POSITIONS', 3));
    }

    /**
     * Timeframes a signal may be issued on — deliberately narrower than
     * TIMEFRAMES (which also drives candle collection): 1m/5m noise is
     * useful as context but is not something to trade off.
     *
     * @return string[]
     */
    public static function signalTimeframes(): array
    {
        $override = self::dbOverride('SIGNAL_TIMEFRAMES');
        $list = $override !== null
            ? array_values(array_filter(array_map('trim', explode(',', $override))))
            : Env::getList('SIGNAL_TIMEFRAMES', '15m,30m,1h,2h');
        return empty($list) ? ['15m', '30m', '1h', '2h'] : $list;
    }

    /** Risk-multiple of the first target. Realised R:R of every signal equals this. */
    public static function tp1RiskReward(): float
    {
        $override = self::dbOverride('TP1_RR');
        return max(0.1, $override !== null ? (float) $override : Env::getFloat('TP1_RR', 1.2));
    }

    /** Risk-multiple of the second (final) target — the trade closes here. */
    public static function tp2RiskReward(): float
    {
        $override = self::dbOverride('TP2_RR');
        return max(0.1, $override !== null ? (float) $override : Env::getFloat('TP2_RR', 1.3));
    }

    /** Move the stop to entry once TP1 is hit, and announce it. */
    public static function riskFreeEnabled(): bool
    {
        $override = self::dbOverride('RISK_FREE_ENABLED');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('RISK_FREE_ENABLED', true);
    }

    // -- Leverage ----------------------------------------------------------

    /** @return string[] base assets that get the high-leverage treatment */
    public static function majorAssets(): array
    {
        $override = self::dbOverride('LEVERAGE_MAJOR_ASSETS');
        $list = $override !== null
            ? array_values(array_filter(array_map('trim', explode(',', strtoupper($override)))))
            : array_map('strtoupper', Env::getList('LEVERAGE_MAJOR_ASSETS', 'BTC,ETH'));
        return empty($list) ? ['BTC', 'ETH'] : $list;
    }

    public static function leverageMajor(): int
    {
        $override = self::dbOverride('LEVERAGE_MAJOR');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_MAJOR', 150));
    }

    /** Lower bound of the auto-picked range for altcoins / low-cap / high-volatility pairs. */
    public static function leverageAltMin(): int
    {
        $override = self::dbOverride('LEVERAGE_ALT_MIN');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_ALT_MIN', 20));
    }

    /** Upper bound of that range — reserved for the most liquid, least volatile alts. */
    public static function leverageAltMax(): int
    {
        $override = self::dbOverride('LEVERAGE_ALT_MAX');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_ALT_MAX', 25));
    }

    /**
     * Fraction of the liquidation distance the stop loss is allowed to use.
     *
     * This is what keeps high announced leverage honest. At 150x a position
     * liquidates roughly 0.67% against you, so a stop placed 2% away — a
     * perfectly normal structural stop on a 4h chart — would be wiped out
     * long before it was ever reached. The planner therefore caps the stop
     * distance at buffer x (100 / leverage) percent, and the targets (1.2R
     * / 1.3R) are measured from that capped risk.
     */
    public static function leverageLiquidationBuffer(): float
    {
        $override = self::dbOverride('LEVERAGE_LIQUIDATION_BUFFER');
        $v = $override !== null ? (float) $override : Env::getFloat('LEVERAGE_LIQUIDATION_BUFFER', 0.75);
        return max(0.05, min(0.95, $v));
    }

    /**
     * How many symbols one signal pass may evaluate. Each symbol costs an
     * S/R + order-block + FVG + confluence run per timeframe, so on a cron
     * host with a ~50 second budget this is the knob that keeps a pass
     * inside it. Majors are always evaluated first (SymbolRepository::universe).
     */
    public static function signalMaxSymbolsPerPass(): int
    {
        $override = self::dbOverride('SIGNAL_MAX_SYMBOLS_PER_PASS');
        return max(1, $override !== null ? (int) $override : Env::getInt('SIGNAL_MAX_SYMBOLS_PER_PASS', 60));
    }

    /**
     * Whether detected zones / order blocks / FVGs are written to their
     * tables. Nothing in the project reads them back — they are a debugging
     * aid — and a DELETE+INSERT per symbol per timeframe is the most
     * expensive thing a scan pass does on SQLite. Off by default.
     */
    public static function persistStructure(): bool
    {
        return Env::getBool('PERSIST_STRUCTURE', false);
    }

    /** Floor on stop distance (percent of entry), so a stop can never land inside the spread. */
    public static function minStopPercent(): float
    {
        $override = self::dbOverride('MIN_STOP_PERCENT');
        return max(0.01, $override !== null ? (float) $override : Env::getFloat('MIN_STOP_PERCENT', 0.12));
    }

    // -- Worker / runtime ----------------------------------------------------
    public static function runMode(): RunMode
    {
        $v = strtoupper(Env::get('RUN_MODE', 'DRY_RUN') ?? 'DRY_RUN');
        return $v === 'LIVE' ? RunMode::LIVE : RunMode::DRY_RUN;
    }

    public static function workerTickSeconds(): int
    {
        return Env::getInt('WORKER_TICK_SECONDS', 15);
    }

    /**
     * 'daemon' — worker.php loops forever until SIGTERM/SIGINT (needs a
     * host that allows a persistent background process: SSH + nohup/screen,
     * a VPS, "Setup Node.js App"-style always-on process, etc).
     * 'cron'   — worker.php runs one bounded pass (Config::workerMaxRuntimeSeconds())
     * and exits cleanly; a cPanel Cron Job re-invokes it every minute. This
     * is the default because it's the only mode plain shared hosting with
     * just File Manager + Cron Jobs can actually run.
     */
    public static function workerMode(): string
    {
        $v = strtolower(Env::get('WORKER_MODE', 'cron') ?? 'cron');
        return $v === 'daemon' ? 'daemon' : 'cron';
    }

    public static function workerMaxRuntimeSeconds(): int
    {
        return Env::getInt('WORKER_MAX_RUNTIME_SECONDS', 50);
    }

    public static function httpTimeoutSeconds(): int
    {
        return Env::getInt('HTTP_TIMEOUT_SECONDS', 10);
    }

    public static function maxRetries(): int
    {
        return Env::getInt('MAX_RETRIES', 5);
    }

    public static function appTimezone(): string
    {
        return Env::get('APP_TIMEZONE', 'UTC') ?? 'UTC';
    }

    // -- Logging -----------------------------------------------------------
    /**
     * Minimum level that gets written anywhere. Defaults to 'error': the
     * bot is meant to run unattended and silently, and the per-symbol
     * debug/info chatter is what used to fill both the log table and the
     * cron mail. Set LOG_LEVEL=info temporarily when diagnosing something.
     */
    public static function logMinLevel(): string
    {
        $v = strtolower(Env::get('LOG_LEVEL', 'error') ?? 'error');
        return in_array($v, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $v : 'error';
    }

    /** Days of log rows to keep. Old rows are pruned on migrate(); 0 disables pruning. */
    public static function logRetentionDays(): int
    {
        return max(0, Env::getInt('LOG_RETENTION_DAYS', 3));
    }
}

date_default_timezone_set(Config::appTimezone());

// ============================================================================
// SECTION 3 — DATABASE (PDO singleton + schema migration)
// ============================================================================

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::ensureStorageDir();
        $dsn = 'sqlite:' . Config::dbPath();

        try {
            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10, // seconds to wait on a locked db before throwing
            ]);
            // WAL lets worker.php (writer) and bot.php's webhook (occasional
            // reader/writer) touch the database concurrently without
            // "database is locked" errors on every overlap.
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 8000');
        } catch (PDOException $e) {
            throw new DatabaseException('Database connection failed: ' . $e->getCode());
        }

        return self::$pdo = $pdo;
    }

    private static function ensureStorageDir(): void
    {
        $dir = Config::storageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new DatabaseException("storage directory is missing or not writable: $dir");
        }

        // Self-heal: keep the sqlite file (and WAL/journal siblings) and
        // logs out of the public web root even if someone points STORAGE_DIR
        // inside it. Harmless no-op on nginx/php-fpm setups that don't read
        // .htaccess; this is aimed squarely at the Apache/LiteSpeed shared
        // hosting (cPanel) this project is meant to run on.
        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    /**
     * Idempotent schema creation. Safe to call on every worker/bot boot.
     */
    public static function migrate(): void
    {
        $pdo = self::pdo();

        // SQLite DDL: INTEGER PRIMARY KEY is the rowid alias (= AUTO_INCREMENT),
        // ENUM becomes TEXT + CHECK, DATETIME columns are TEXT ('Y-m-d H:i:s',
        // which sorts correctly as a string), JSON columns are TEXT holding
        // json_encode() output (same as how PHP already reads/writes them).
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_user_id INTEGER NOT NULL,
                username TEXT NULL,
                first_name TEXT NULL,
                last_name TEXT NULL,
                language_code TEXT NULL,
                is_bot INTEGER NOT NULL DEFAULT 0,
                first_seen_at TEXT NOT NULL,
                last_seen_at TEXT NOT NULL,
                UNIQUE (telegram_user_id)
            )",

            "CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_user_id INTEGER NOT NULL,
                role TEXT NOT NULL DEFAULT 'admin' CHECK (role IN ('owner','admin')),
                added_by INTEGER NULL,
                created_at TEXT NOT NULL,
                UNIQUE (telegram_user_id)
            )",

            "CREATE TABLE IF NOT EXISTS channels (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chat_id INTEGER NOT NULL,
                title TEXT NULL,
                username TEXT NULL,
                type TEXT NOT NULL DEFAULT 'channel',
                is_active INTEGER NOT NULL DEFAULT 0,
                bot_status TEXT NOT NULL DEFAULT 'unknown' CHECK (bot_status IN ('unknown','member','administrator','left','kicked')),
                can_post INTEGER NOT NULL DEFAULT 0,
                added_by INTEGER NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (chat_id)
            )",

            "CREATE TABLE IF NOT EXISTS channel_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                channel_id INTEGER NOT NULL,
                template_key TEXT NOT NULL DEFAULT 'signal_template',
                min_signal_score REAL NOT NULL DEFAULT 75.00,
                allowed_strategies TEXT NULL,
                allowed_exchanges TEXT NULL,
                enabled INTEGER NOT NULL DEFAULT 1,
                pin_signal INTEGER NOT NULL DEFAULT 0,
                quote_enabled INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (channel_id),
                FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS bot_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                setting_key TEXT NOT NULL,
                setting_value TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (setting_key)
            )",

            "CREATE TABLE IF NOT EXISTS text_formats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                text_key TEXT NOT NULL,
                text_value TEXT NOT NULL,
                entities TEXT NULL,
                updated_at TEXT NOT NULL,
                updated_by INTEGER NULL,
                UNIQUE (text_key)
            )",

            "CREATE TABLE IF NOT EXISTS exchanges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                display_name TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                priority INTEGER NOT NULL DEFAULT 0,
                UNIQUE (name)
            )",

            "CREATE TABLE IF NOT EXISTS exchange_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                rate_limit_per_min INTEGER NOT NULL DEFAULT 1200,
                ws_enabled INTEGER NOT NULL DEFAULT 0,
                extra TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (exchange_id),
                FOREIGN KEY (exchange_id) REFERENCES exchanges(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS symbols (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                base_asset TEXT NOT NULL,
                quote_asset TEXT NOT NULL,
                volume_24h REAL NOT NULL DEFAULT 0,
                liquidity_score REAL NOT NULL DEFAULT 0,
                spread_pct REAL NOT NULL DEFAULT 0,
                volatility REAL NOT NULL DEFAULT 0,
                rank_position INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                last_scanned_at TEXT NULL,
                UNIQUE (exchange_id, symbol),
                FOREIGN KEY (exchange_id) REFERENCES exchanges(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS candles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                timeframe TEXT NOT NULL,
                open_time INTEGER NOT NULL,
                open_price REAL NOT NULL,
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                close_price REAL NOT NULL,
                volume REAL NOT NULL,
                UNIQUE (exchange_id, symbol, timeframe, open_time)
            )",

            "CREATE TABLE IF NOT EXISTS market_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                price REAL NOT NULL,
                bid REAL NOT NULL DEFAULT 0,
                ask REAL NOT NULL DEFAULT 0,
                spread_pct REAL NOT NULL DEFAULT 0,
                volume_24h REAL NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL,
                UNIQUE (exchange_id, symbol)
            )",

            "CREATE TABLE IF NOT EXISTS zones (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                zone_type TEXT NOT NULL CHECK (zone_type IN ('support','resistance')),
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                timeframe TEXT NOT NULL,
                strength REAL NOT NULL DEFAULT 0,
                volume REAL NOT NULL DEFAULT 0,
                touches INTEGER NOT NULL DEFAULT 1,
                source TEXT NOT NULL DEFAULT 'swing',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS order_blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                ob_type TEXT NOT NULL CHECK (ob_type IN ('bullish','bearish')),
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                timeframe TEXT NOT NULL,
                strength REAL NOT NULL DEFAULT 0,
                volume REAL NOT NULL DEFAULT 0,
                mitigated INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS fvgs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                fvg_type TEXT NOT NULL CHECK (fvg_type IN ('bullish','bearish')),
                high_price REAL NOT NULL,
                low_price REAL NOT NULL,
                midpoint REAL NOT NULL,
                timeframe TEXT NOT NULL,
                size REAL NOT NULL DEFAULT 0,
                filled INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS strategies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                display_name TEXT NOT NULL,
                is_enabled INTEGER NOT NULL DEFAULT 1,
                description TEXT NULL,
                UNIQUE (name)
            )",

            "CREATE TABLE IF NOT EXISTS strategy_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                strategy_id INTEGER NOT NULL,
                weights TEXT NULL,
                params TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (strategy_id),
                FOREIGN KEY (strategy_id) REFERENCES strategies(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS signals (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                exchange_id INTEGER NOT NULL,
                symbol TEXT NOT NULL,
                direction TEXT NOT NULL CHECK (direction IN ('LONG','SHORT')),
                timeframe TEXT NOT NULL,
                entry_price REAL NOT NULL,
                stop_loss REAL NOT NULL,
                tp1 REAL NULL,
                tp2 REAL NULL,
                tp3 REAL NULL,
                risk_reward REAL NOT NULL DEFAULT 0,
                score REAL NOT NULL DEFAULT 0,
                confidence TEXT NOT NULL DEFAULT 'medium',
                strategy TEXT NOT NULL,
                reasons TEXT NULL,
                fingerprint TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','queued','sent','failed','expired','invalidated','test')),
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (uuid)
            )",

            "CREATE TABLE IF NOT EXISTS signal_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                signal_id INTEGER NOT NULL,
                channel_id INTEGER NULL,
                event_type TEXT NOT NULL CHECK (event_type IN ('queued','sent','failed','retry','test')),
                message_id INTEGER NULL,
                error TEXT NULL,
                attempt INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                FOREIGN KEY (signal_id) REFERENCES signals(id) ON DELETE CASCADE
            )",

            "CREATE TABLE IF NOT EXISTS scanner_runs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                exchange_id INTEGER NULL,
                started_at TEXT NOT NULL,
                finished_at TEXT NULL,
                symbols_scanned INTEGER NOT NULL DEFAULT 0,
                symbols_selected INTEGER NOT NULL DEFAULT 0,
                status TEXT NOT NULL DEFAULT 'running',
                error TEXT NULL
            )",

            "CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level TEXT NOT NULL DEFAULT 'info' CHECK (level IN ('debug','info','warning','error','critical')),
                channel TEXT NOT NULL DEFAULT 'app',
                message TEXT NOT NULL,
                context TEXT NULL,
                created_at TEXT NOT NULL
            )",

            "CREATE TABLE IF NOT EXISTS admin_states (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                telegram_user_id INTEGER NOT NULL,
                state TEXT NOT NULL,
                payload TEXT NULL,
                updated_at TEXT NOT NULL,
                UNIQUE (telegram_user_id)
            )",

            "CREATE TABLE IF NOT EXISTS message_refs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ref_type TEXT NOT NULL,
                ref_id TEXT NOT NULL,
                chat_id INTEGER NOT NULL,
                message_id INTEGER NOT NULL,
                quote_text TEXT NULL,
                quote_entities TEXT NULL,
                created_at TEXT NOT NULL
            )",
        ];

        $indexes = [
            'CREATE INDEX IF NOT EXISTS idx_symbols_active_rank ON symbols (is_active, rank_position)',
            'CREATE INDEX IF NOT EXISTS idx_candles_lookup ON candles (exchange_id, symbol, timeframe, open_time DESC)',
            'CREATE INDEX IF NOT EXISTS idx_zones_lookup ON zones (exchange_id, symbol, timeframe, zone_type)',
            'CREATE INDEX IF NOT EXISTS idx_ob_lookup ON order_blocks (exchange_id, symbol, timeframe, mitigated)',
            'CREATE INDEX IF NOT EXISTS idx_fvg_lookup ON fvgs (exchange_id, symbol, timeframe, filled)',
            'CREATE INDEX IF NOT EXISTS idx_signals_fingerprint ON signals (fingerprint, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_signals_symbol ON signals (exchange_id, symbol, created_at)',
            'CREATE INDEX IF NOT EXISTS idx_signal_events_signal ON signal_events (signal_id)',
            'CREATE INDEX IF NOT EXISTS idx_logs_created ON logs (created_at)',
            'CREATE INDEX IF NOT EXISTS idx_logs_level ON logs (level)',
            'CREATE INDEX IF NOT EXISTS idx_message_refs_ref ON message_refs (ref_type, ref_id)',
        ];

        foreach ($tables as $sql) {
            $pdo->exec($sql);
        }
        foreach ($indexes as $sql) {
            $pdo->exec($sql);
        }

        // Added after the initial release -- ensureColumn() is idempotent
        // (checks PRAGMA table_info first) so this is safe to run on every
        // request against a database that already has these columns.
        self::ensureColumn($pdo, 'signals', 'outcome', "TEXT NULL CHECK (outcome IS NULL OR outcome IN ('tp1','sl'))");
        self::ensureColumn($pdo, 'signals', 'resolved_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'resolved_price', 'REAL NULL');

        // Automatic-trade lifecycle. `outcome` above carries a CHECK
        // constraint from an earlier release that only allows 'tp1'/'sl',
        // and SQLite cannot drop a column constraint without rebuilding the
        // table (which would cascade-delete signal_events). So the richer
        // states live in `result`, and `outcome` keeps getting the legacy
        // win/loss value so older reads of it stay correct.
        self::ensureColumn($pdo, 'signals', 'leverage', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tier', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'stage', "TEXT NOT NULL DEFAULT 'open'");
        self::ensureColumn($pdo, 'signals', 'result', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'active_stop', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp1_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp1_hit_price', 'REAL NULL');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_signals_open ON signals (status, resolved_at)');

        self::pruneLogs($pdo);
        self::seedDefaults($pdo);
    }

    private static function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
    {
        $stmt = $pdo->query("PRAGMA table_info($table)");
        foreach ($stmt->fetchAll() as $col) {
            if (strcasecmp((string) $col['name'], $column) === 0) {
                return;
            }
        }
        $pdo->exec("ALTER TABLE $table ADD COLUMN $column $definition");
    }

    /**
     * Keeps the log table from growing without bound on a bot that is meant
     * to run forever untouched. Cheap enough to run on every migrate()
     * (i.e. every worker invocation) because the created_at index makes it
     * a range delete.
     */
    private static function pruneLogs(PDO $pdo): void
    {
        $days = Config::logRetentionDays();
        if ($days <= 0) {
            return;
        }
        try {
            $pdo->prepare('DELETE FROM logs WHERE created_at < :cutoff')
                ->execute([':cutoff' => date('Y-m-d H:i:s', time() - $days * 86400)]);
        } catch (Throwable) {
            // Never let housekeeping break a boot.
        }
    }

    /** The pre-automation template, recognised so it can be upgraded in place. */
    private const LEGACY_SIGNAL_TEMPLATE = "🔔 سیگنال جدید\n\nنماد: {symbol}\nصرافی: {exchange}\nجهت: {direction}\nتایم‌فریم: {timeframe}\n\nورود: {entry}\nحد ضرر: {sl}\n\nهدف ۱: {tp1}\nهدف ۲: {tp2}\nهدف ۳: {tp3}\n\nامتیاز: {score}\nاستراتژی: {strategy}\n\nدلایل:\n{reasons}";

    /** Default entry-signal caption for the automatic bot. */
    private const AUTO_SIGNAL_TEMPLATE = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم پیشنهادی: {leverage}\n🏷 نوع ارز: {tier}\n\n📍 نقطه ورود: {entry}\n🛑 حد ضرر: {sl}\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n🔻 فاصله حد ضرر: {risk_pct}%\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    /**
     * Replaces a seeded default text with a newer one ONLY while it is
     * still byte-identical to the old default and carries no entities —
     * i.e. nobody has customised it. Idempotent.
     */
    private static function upgradeUntouchedText(PDO $pdo, string $key, string $oldDefault, string $newValue, string $now): void
    {
        try {
            $stmt = $pdo->prepare('SELECT text_value, entities FROM text_formats WHERE text_key = :k');
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch();
            if ($row === false) {
                return;
            }
            $entities = json_decode((string) ($row['entities'] ?? '[]'), true);
            if ((string) $row['text_value'] !== $oldDefault || !empty($entities)) {
                return;
            }
            $pdo->prepare('UPDATE text_formats SET text_value = :v, updated_at = :u WHERE text_key = :k')
                ->execute([':v' => $newValue, ':u' => $now, ':k' => $key]);
        } catch (Throwable) {
            // A failed cosmetic upgrade must never block a boot.
        }
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');

        // Exchanges
        $exchangeNames = [
            'binance' => 'Binance', 'mexc' => 'MEXC', 'bybit' => 'Bybit', 'okx' => 'OKX',
            'kucoin' => 'KuCoin', 'gate' => 'Gate.io', 'bitget' => 'Bitget', 'htx' => 'HTX',
            'cryptocompare' => 'CryptoCompare',
        ];
        // Wallex was removed; drop any row a previous install left behind so
        // it stops showing in the panel and the health screen.
        $pdo->prepare('DELETE FROM exchanges WHERE name = :n')->execute([':n' => 'wallex']);
        $stmt = $pdo->prepare(
            'INSERT INTO exchanges (name, display_name, is_enabled, priority)
             VALUES (:name, :display_name, :enabled, :priority)
             ON CONFLICT(name) DO UPDATE SET display_name = excluded.display_name'
        );
        $priority = 0;
        $enabled = Config::enabledExchanges();
        foreach ($exchangeNames as $name => $display) {
            $stmt->execute([
                ':name' => $name,
                ':display_name' => $display,
                ':enabled' => in_array($name, $enabled, true) ? 1 : 0,
                ':priority' => $priority++,
            ]);
        }

        // Default text formats (plain, no entities) — admin can edit via panel.
        $defaults = [
            'welcome' => "به ربات سیگنال خوش آمدید.",
            'help' => "برای مشاهده راهنما با ادمین در تماس باشید.",
            'signal_template' => self::AUTO_SIGNAL_TEMPLATE,
            'signal_long' => "🟢 سیگنال LONG برای {symbol}",
            'signal_short' => "🟡 سیگنال SHORT برای {symbol}",
            'error' => "خطایی رخ داد. لطفاً دوباره تلاش کنید.",
            'channel_added' => "✅ کانال با موفقیت اضافه شد.",
            'scanner_status' => "وضعیت اسکنر: {status}",

            // Trade-result announcements. New keys, so an existing install
            // picks them up on its next boot without touching anything the
            // operator may already have customised.
            'result_tp1' => "✅ تارگت ۱ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 معامله ریسک‌فری شد — حد ضرر روی نقطه ورود ({entry}) منتقل شد و از این لحظه این معامله ضرری ندارد.\n🎯 تارگت بعدی: {tp2}",
            'result_tp2' => "🏆 تارگت ۲ فعال شد | {symbol}\n\n💰 سود نهایی با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n✅ معامله با موفقیت بسته شد. ربات به سراغ سیگنال بعدی می‌رود.",
            'result_sl' => "❌ حد ضرر فعال شد | {symbol}\n\n📉 نتیجه با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🛑 خروج: {exit}\n\nمعامله بسته شد. ربات به سراغ سیگنال بعدی می‌رود.",
            'result_be' => "🛡 معامله بدون ضرر بسته شد | {symbol}\n\nقیمت بعد از فعال شدن تارگت ۱ به نقطه ورود برگشت و چون معامله ریسک‌فری شده بود، بدون ضرر بسته شد.\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n💰 نتیجه با اهرم {leverage}: {pnl}%\n\nربات به سراغ سیگنال بعدی می‌رود.",
        ];
        $stmt = $pdo->prepare(
            'INSERT OR IGNORE INTO text_formats (text_key, text_value, entities, updated_at) VALUES (:k, :v, :e, :u)'
        );
        foreach ($defaults as $key => $value) {
            $stmt->execute([':k' => $key, ':v' => $value, ':e' => json_encode([]), ':u' => $now]);
        }

        // The signal template gained {leverage}, {tp1_profit}, {risk_pct}
        // and friends. INSERT OR IGNORE above cannot update an install that
        // already has a row, so an untouched original default is migrated
        // in place here — a template the operator actually edited (or gave
        // premium-emoji entities to) is left exactly as it is.
        self::upgradeUntouchedText($pdo, 'signal_template', self::LEGACY_SIGNAL_TEMPLATE, self::AUTO_SIGNAL_TEMPLATE, $now);

        // Seed admin from env (owner)
        foreach (Config::adminIds() as $adminId) {
            $ins = $pdo->prepare(
                "INSERT INTO admins (telegram_user_id, role, created_at) VALUES (:id, 'owner', :now)
                 ON CONFLICT(telegram_user_id) DO NOTHING"
            );
            $ins->execute([':id' => $adminId, ':now' => $now]);
        }
    }
}

// ============================================================================
// SECTION 3.5 — SHUTDOWN FLAG (shared)
// A process-wide "please stop" signal that long-running retry/backoff loops
// (HttpClient in signal.php) poll between attempts, so a SIGTERM/SIGINT
// caught by worker.php's own handler can interrupt an in-flight retry
// storm instead of waiting for it to exhaust naturally (which, under a
// full network outage with several exchanges, could otherwise take
// minutes before the process actually exits).
// ============================================================================

final class ShutdownFlag
{
    private static bool $requested = false;

    public static function request(): void
    {
        self::$requested = true;
    }

    public static function isRequested(): bool
    {
        return self::$requested;
    }
}

// ============================================================================
// SECTION 4 — LOGGER
// ============================================================================

final class Logger
{
    private const SECRET_PATTERNS = ['token', 'secret', 'password', 'api_key', 'apikey'];

    /** @var resource|null */
    private static $stderr = null;
    /** @var resource|null */
    private static $stdout = null;

    public static function debug(string $channel, string $message, array $context = []): void
    {
        self::write('debug', $channel, $message, $context);
    }

    public static function info(string $channel, string $message, array $context = []): void
    {
        self::write('info', $channel, $message, $context);
    }

    public static function warning(string $channel, string $message, array $context = []): void
    {
        self::write('warning', $channel, $message, $context);
    }

    public static function error(string $channel, string $message, array $context = []): void
    {
        self::write('error', $channel, $message, $context);
    }

    public static function critical(string $channel, string $message, array $context = []): void
    {
        self::write('critical', $channel, $message, $context);
    }

    private const LEVELS = ['debug' => 10, 'info' => 20, 'warning' => 30, 'error' => 40, 'critical' => 50];

    private static ?int $threshold = null;

    /**
     * The bot is designed to run unattended and quiet ("بدون هیچ لاگ خاصی"),
     * so anything below LOG_LEVEL (default: error) is dropped before it
     * reaches either the output streams or the log table.
     */
    private static function enabled(string $level): bool
    {
        if (self::$threshold === null) {
            self::$threshold = self::LEVELS[Config::logMinLevel()] ?? 40;
        }
        return (self::LEVELS[$level] ?? 0) >= self::$threshold;
    }

    private static function write(string $level, string $channel, string $message, array $context): void
    {
        if (!self::enabled($level)) {
            return;
        }
        $context = self::redact($context);
        $message = self::redactString($message);

        $line = sprintf('[%s] [%s] [%s] %s %s', date('Y-m-d H:i:s'), strtoupper($level), $channel, $message, empty($context) ? '' : json_encode($context, JSON_UNESCAPED_UNICODE));

        // STDERR/STDOUT are only predefined under the CLI SAPI (worker.php).
        // bot.php runs as a web request (webhook, via PHP-FPM/mod_php),
        // where referencing those constants directly is a fatal "undefined
        // constant" error -- and since that happened inside this very
        // logger, called from the webhook's own top-level catch block, it
        // was masking whatever the real error actually was. php://stderr
        // and php://stdout streams work identically under both SAPIs.
        self::$stderr ??= @fopen('php://stderr', 'a') ?: null;
        self::$stdout ??= @fopen('php://stdout', 'a') ?: null;
        $stream = ($level === 'error' || $level === 'critical') ? self::$stderr : self::$stdout;
        if ($stream !== null) {
            @fwrite($stream, $line . PHP_EOL);
        }

        try {
            $stmt = Database::pdo()->prepare(
                'INSERT INTO logs (level, channel, message, context, created_at) VALUES (:level, :channel, :message, :context, :now)'
            );
            $stmt->execute([
                ':level' => $level,
                ':channel' => substr($channel, 0, 32),
                ':message' => $message,
                ':context' => empty($context) ? null : json_encode($context, JSON_UNESCAPED_UNICODE),
                ':now' => date('Y-m-d H:i:s'),
            ]);
        } catch (Throwable) {
            // Logging must never crash the caller; DB may not be migrated yet.
        }
    }

    private static function redact(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            $keyLower = strtolower((string) $k);
            $isSecret = false;
            foreach (self::SECRET_PATTERNS as $pattern) {
                if (str_contains($keyLower, $pattern)) {
                    $isSecret = true;
                    break;
                }
            }
            if ($isSecret) {
                $out[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $out[$k] = self::redact($v);
            } else {
                $out[$k] = is_string($v) ? self::redactString($v) : $v;
            }
        }
        return $out;
    }

    private static function redactString(string $s): string
    {
        $s = preg_replace('/\d{6,}:[A-Za-z0-9_-]{30,}/', '[REDACTED_TOKEN]', $s) ?? $s;
        return $s;
    }
}

// ============================================================================
// SECTION 5 — SHARED TELEGRAM ENTITY UTILITIES
// (UTF-16 code-unit safe placeholder rendering — used by bot.php's
//  TextFormatManager and signal.php's SignalFormatter so Premium custom
//  emoji / bold / spoiler / etc entities never shift or break.)
// ============================================================================

final class TelegramEntityUtils
{
    /**
     * Telegram entity offsets/lengths are counted in UTF-16 code units,
     * NOT bytes and NOT PHP string length. This computes that length for
     * an arbitrary UTF-8 string without requiring ext-intl.
     */
    public static function utf16Length(string $text): int
    {
        $len = 0;
        $bytes = strlen($text);
        $i = 0;
        while ($i < $bytes) {
            $ord = ord($text[$i]);
            if ($ord < 0x80) {
                $cp = $ord;
                $i += 1;
            } elseif (($ord & 0xE0) === 0xC0 && $i + 1 < $bytes) {
                $cp = (($ord & 0x1F) << 6) | (ord($text[$i + 1]) & 0x3F);
                $i += 2;
            } elseif (($ord & 0xF0) === 0xE0 && $i + 2 < $bytes) {
                $cp = (($ord & 0x0F) << 12) | ((ord($text[$i + 1]) & 0x3F) << 6) | (ord($text[$i + 2]) & 0x3F);
                $i += 3;
            } elseif (($ord & 0xF8) === 0xF0 && $i + 3 < $bytes) {
                $cp = (($ord & 0x07) << 18) | ((ord($text[$i + 1]) & 0x3F) << 12) | ((ord($text[$i + 2]) & 0x3F) << 6) | (ord($text[$i + 3]) & 0x3F);
                $i += 4;
            } else {
                $cp = 0xFFFD;
                $i += 1;
            }
            $len += ($cp > 0xFFFF) ? 2 : 1;
        }
        return $len;
    }

    /**
     * Renders {placeholder} tokens inside $template, replacing them with
     * $placeholders values while correctly shifting every entity's
     * offset/length (UTF-16 units) so formatting/custom-emoji entities
     * that sit before, after, or wrap around a placeholder stay intact.
     *
     * @param array<int,array<string,mixed>> $entities Telegram entity objects (offset/length in UTF-16 units)
     * @param array<string,string> $placeholders token => replacement text (plain text, no entities of its own)
     * @return array{text:string, entities:array<int,array<string,mixed>>}
     */
    public static function renderTemplate(string $template, array $entities, array $placeholders): array
    {
        // Locate every {token} occurrence (byte offsets are fine here since
        // "{" "}" and placeholder names are ASCII) and compute its UTF-16
        // start/end within the original template.
        $tokens = [];
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $idx => $full) {
                $name = $matches[1][$idx][0];
                if (!array_key_exists($name, $placeholders)) {
                    continue;
                }
                $byteStart = $full[1];
                $utf16Start = self::utf16Length(substr($template, 0, $byteStart));
                $utf16TokenLen = self::utf16Length($full[0]);
                $tokens[] = [
                    'byte_start' => $byteStart,
                    'byte_len' => strlen($full[0]),
                    'utf16_start' => $utf16Start,
                    'utf16_end' => $utf16Start + $utf16TokenLen,
                    'value' => (string) $placeholders[$name],
                    'delta' => self::utf16Length((string) $placeholders[$name]) - $utf16TokenLen,
                ];
            }
        }

        // Remap every entity from template coordinates to rendered
        // coordinates in ONE pass, reading token positions that always stay
        // in template coordinates.
        //
        // The previous version walked the tokens in an outer loop and
        // mutated entity offsets as it went, then compared those already-
        // shifted offsets against the NEXT token's untouched template
        // coordinates. With placeholders that shrink the text — and most of
        // them do, "{leverage}" is ten units and "150x" is four — an entity
        // could slide backwards past a later token's start and get treated
        // as overlapping it, which clamped it onto the wrong text or
        // collapsed it to nothing. On a template where the admin had quoted
        // each line and put a premium emoji on it, that silently moved the
        // quotes off their lines and dropped emoji entities, so the message
        // arrived unformatted.
        $workingEntities = [];
        foreach ($entities as $entity) {
            $start = (int) ($entity['offset'] ?? 0);
            $length = (int) ($entity['length'] ?? 0);
            $newStart = self::mapOffset($tokens, $start, false);
            $newEnd = self::mapOffset($tokens, $start + $length, true);
            $entity['offset'] = $newStart;
            $entity['length'] = max(0, $newEnd - $newStart);
            $workingEntities[] = $entity;
        }

        // Now perform the textual substitution (byte-safe, right to left so
        // earlier byte offsets stay valid while we mutate the string).
        $text = $template;
        foreach (array_reverse($tokens) as $token) {
            $text = substr_replace($text, $token['value'], $token['byte_start'], $token['byte_len']);
        }

        // Drop any zero-length entities left over from a fully-collapsed token.
        $workingEntities = array_values(array_filter($workingEntities, static fn($e) => ((int) ($e['length'] ?? 0)) > 0));

        return ['text' => $text, 'entities' => $workingEntities];
    }

    /**
     * Maps one template offset to its rendered offset.
     *
     * @param array<int,array<string,mixed>> $tokens ordered, in template coordinates
     * @param bool $isEnd treat the position as an entity's end, so a position
     *                    that lands inside a replaced token snaps past the
     *                    replacement rather than in front of it
     */
    private static function mapOffset(array $tokens, int $pos, bool $isEnd): int
    {
        $shift = 0;
        foreach ($tokens as $token) {
            if ($token['utf16_end'] <= $pos) {
                $shift += $token['delta'];
                continue;
            }
            if ($token['utf16_start'] < $pos && $pos < $token['utf16_end']) {
                // Inside a replaced placeholder: snap to an edge of the
                // replacement instead of landing in the middle of a value.
                return $isEnd
                    ? $token['utf16_start'] + $shift + self::utf16Length($token['value'])
                    : $token['utf16_start'] + $shift;
            }
            // Tokens are in ascending order, so everything from here on
            // starts at or after $pos and cannot move it.
            break;
        }
        return $pos + $shift;
    }

    // ------------------------------------------------------------------
    // Delivery fallback
    // ------------------------------------------------------------------

    /**
     * Removes custom_emoji entities, leaving the fallback emoji characters
     * in place. Used to re-send a message that Telegram rejected because
     * the bot is not allowed to use custom emoji in that chat.
     *
     * @param array<int,array<string,mixed>> $entities
     * @return array<int,array<string,mixed>>
     */
    public static function stripCustomEmoji(array $entities): array
    {
        return array_values(array_filter(
            $entities,
            static fn($e) => ($e['type'] ?? '') !== 'custom_emoji'
        ));
    }

    /** @param array<int,array<string,mixed>> $entities */
    public static function hasCustomEmoji(array $entities): bool
    {
        foreach ($entities as $e) {
            if (($e['type'] ?? '') === 'custom_emoji') {
                return true;
            }
        }
        return false;
    }
}

<?php

declare(strict_types=1);

class AppException extends RuntimeException
{
}

final class ConfigException extends AppException
{
}

final class DatabaseException extends AppException
{
}

final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

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

                    if (!array_key_exists($key, self::$vars)) {
                        self::$vars[$key] = $value;
                    }
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

enum RunMode: string
{
    case LIVE = 'LIVE';
    case DRY_RUN = 'DRY_RUN';
}

final class Config
{
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

    public static function adminIds(): array
    {
        return array_map('intval', Env::getList('ADMIN_IDS', ''));
    }

    public static function storageDir(): string
    {
        return rtrim(Env::get('STORAGE_DIR', __DIR__ . '/storage') ?? (__DIR__ . '/storage'), '/');
    }

    public static function dbPath(): string
    {
        return self::storageDir() . '/' . (Env::get('DB_FILE', 'database.sqlite') ?? 'database.sqlite');
    }

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

    public static function cryptocompareApiKey(): string
    {
        return self::dbOverride('CRYPTOCOMPARE_API_KEY') ?? (Env::get('CRYPTOCOMPARE_API_KEY', '') ?? '');
    }

    public static function cryptocompareRestBase(): string
    {
        return Env::get('CRYPTOCOMPARE_REST_BASE', 'https://min-api.cryptocompare.com') ?? 'https://min-api.cryptocompare.com';
    }

    public static function coinalyzeApiKey(): string
    {
        return self::dbOverride('COINALYZE_API_KEY') ?? (Env::get('COINALYZE_API_KEY', '') ?? '');
    }

    public static function coinalyzeRestBase(): string
    {
        return Env::get('COINALYZE_REST_BASE', 'https://api.coinalyze.net/v1') ?? 'https://api.coinalyze.net/v1';
    }

    public static function coinalyzeSymbol(): string
    {
        return self::dbOverride('COINALYZE_SYMBOL') ?? (Env::get('COINALYZE_SYMBOL', 'BTCUSDT_PERP.A') ?? 'BTCUSDT_PERP.A');
    }

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

    public static function enabledExchanges(): array
    {
        return Env::getList('ENABLED_EXCHANGES', 'mexc,binance,bybit,okx,kucoin,gate,bitget,htx,cryptocompare');
    }

    public static function primaryExchange(): string
    {
        $override = self::dbOverride('PRIMARY_EXCHANGE');
        $name = strtolower(trim((string) ($override ?? Env::get('PRIMARY_EXCHANGE', 'mexc') ?? 'mexc')));
        return $name === '' ? 'mexc' : $name;
    }

    public static function scannerTopN(): int
    {
        $override = self::dbOverride('SCANNER_TOP_N');

        return $override !== null ? (int) $override : Env::getInt('SCANNER_TOP_N', 0);
    }

    public static function scannerIntervalSeconds(): int
    {
        return Env::getInt('SCANNER_INTERVAL_SECONDS', 3600);
    }

    public static function minVolumeUsdt(): float
    {
        $override = self::dbOverride('MIN_VOLUME_USDT');

        return $override !== null ? (float) $override : Env::getFloat('MIN_VOLUME_USDT', 5_000_000.0);
    }

    public static function maxSpreadPercent(): float
    {
        $override = self::dbOverride('MAX_SPREAD_PERCENT');
        return $override !== null ? (float) $override : Env::getFloat('MAX_SPREAD_PERCENT', 0.5);
    }

    public const DEFAULT_QUOTE_ASSETS = ['USDT', 'USDC', 'FDUSD', 'BUSD', 'TUSD', 'DAI'];

    public static function allowedQuoteAssets(): array
    {
        $override = self::dbOverride('ALLOWED_QUOTE_ASSETS');
        $raw = $override ?? (Env::get('ALLOWED_QUOTE_ASSETS', '') ?? '');

        if (trim($raw) === '*') {
            return [];
        }
        $list = array_values(array_filter(array_map(
            static fn($x) => strtoupper(trim($x)),
            explode(',', $raw)
        ), static fn($x) => $x !== ''));

        return empty($list) ? self::DEFAULT_QUOTE_ASSETS : $list;
    }

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

    public static function timeframes(): array
    {
        return Env::getList('TIMEFRAMES', '15m,30m,1h,2h');
    }

    public static function minSignalScore(): float
    {
        $override = self::dbOverride('MIN_SIGNAL_SCORE');
        return $override !== null ? (float) $override : Env::getFloat('MIN_SIGNAL_SCORE', 45.0);
    }

    public static function cooldownSeconds(): int
    {
        return Env::getInt('SIGNAL_COOLDOWN_SECONDS', 900);
    }

    public static function symbolLossCooldownSeconds(): int
    {
        $override = self::dbOverride('SYMBOL_LOSS_COOLDOWN_SECONDS');
        return max(0, $override !== null ? (int) $override : Env::getInt('SYMBOL_LOSS_COOLDOWN_SECONDS', 10800));
    }

    public static function htfConfirmTimeframe(string $timeframe): ?string
    {
        $override = self::dbOverride('HTF_CONFIRM_MAP');
        $raw = (string) ($override ?? Env::get('HTF_CONFIRM_MAP', '15m:1h,30m:2h,1h:4h,2h:4h,4h:1d'));
        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', trim($pair), 2);
            if (count($parts) === 2 && trim($parts[0]) === $timeframe) {
                $target = trim($parts[1]);
                return $target === '' ? null : $target;
            }
        }
        return null;
    }

    public static function requireAPlusSetup(): bool
    {
        $override = self::dbOverride('REQUIRE_APLUS_SETUP');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }

        return Env::getBool('REQUIRE_APLUS_SETUP', false);
    }

    public static function aPlusMinConfirmations(): int
    {
        $override = self::dbOverride('APLUS_MIN_CONFIRMATIONS');
        $value = $override !== null ? (int) $override : Env::getInt('APLUS_MIN_CONFIRMATIONS', 3);
        return max(1, min(7, $value));
    }

    public static function newsBlackoutStart(): ?int
    {
        $override = self::dbOverride('NEWS_BLACKOUT_START');
        $raw = trim((string) ($override ?? Env::get('NEWS_BLACKOUT_START', '')));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts !== false ? $ts : null;
    }

    public static function newsBlackoutEnd(): ?int
    {
        $override = self::dbOverride('NEWS_BLACKOUT_END');
        $raw = trim((string) ($override ?? Env::get('NEWS_BLACKOUT_END', '')));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        return $ts !== false ? $ts : null;
    }

    public static function isNewsBlackoutActive(): bool
    {
        $start = self::newsBlackoutStart();
        $end = self::newsBlackoutEnd();
        if ($start === null || $end === null || $start > $end) {
            return false;
        }
        $now = time();
        return $now >= $start && $now <= $end;
    }

    public static function minRiskReward(): float
    {
        $override = self::dbOverride('MIN_RISK_REWARD');
        return $override !== null ? (float) $override : Env::getFloat('MIN_RISK_REWARD', 1.2);
    }

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

    public static function signalTimeframes(): array
    {
        $override = self::dbOverride('SIGNAL_TIMEFRAMES');
        $list = $override !== null
            ? array_values(array_filter(array_map('trim', explode(',', $override))))
            : Env::getList('SIGNAL_TIMEFRAMES', '15m,30m,1h,2h');
        return empty($list) ? ['15m', '30m', '1h', '2h'] : $list;
    }

    public static function tp1LeveragedPercent(): float
    {
        $override = self::dbOverride('TP1_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP1_LEVERAGED_PCT', 28.0));
    }

    public static function tp2LeveragedPercent(): float
    {
        $override = self::dbOverride('TP2_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP2_LEVERAGED_PCT', 50.0));
    }

    public static function tp3LeveragedPercent(): float
    {
        $override = self::dbOverride('TP3_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP3_LEVERAGED_PCT', 90.0));
    }

    public static function tp4LeveragedPercent(): float
    {
        $override = self::dbOverride('TP4_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('TP4_LEVERAGED_PCT', 160.0));
    }

    public static function maxStopLeveragedPercent(): float
    {
        $override = self::dbOverride('MAX_STOP_LEVERAGED_PCT');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('MAX_STOP_LEVERAGED_PCT', 30.0));
    }

    public static function tp1RiskReward(): float
    {
        return round(self::tp1LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    public static function tp2RiskReward(): float
    {
        return round(self::tp2LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    public static function tp3RiskReward(): float
    {
        return round(self::tp3LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    public static function tp4RiskReward(): float
    {
        return round(self::tp4LeveragedPercent() / self::maxStopLeveragedPercent(), 2);
    }

    public static function tradableVenues(): array
    {
        $override = self::dbOverride('TRADABLE_VENUES');
        $raw = $override ?? (Env::get('TRADABLE_VENUES', 'toobit,ourbit') ?? '');
        $list = array_values(array_filter(array_map(
            static fn(string $v): string => strtolower(trim($v)),
            explode(',', $raw)
        )));
        return $list === ['off'] || $list === ['none'] ? [] : $list;
    }

    public static function venueListingUrl(string $venue): string
    {
        $key = strtoupper($venue) . '_LISTINGS_URL';
        $override = self::dbOverride($key);
        if ($override !== null && trim($override) !== '') {
            return trim($override);
        }
        $configured = Env::get($key, '') ?? '';
        if (trim($configured) !== '') {
            return trim($configured);
        }
        return match (strtolower($venue)) {
            'toobit' => 'https://api.toobit.com/api/v1/exchangeInfo',

            'ourbit' => 'https://contract.ourbit.com/api/v1/contract/detail',
            'mexc' => 'https://contract.mexc.com/api/v1/contract/detail',
            'bitunix' => 'https://fapi.bitunix.com/api/v1/futures/market/trading_pairs',
            default => '',
        };
    }

    public static function venueListingTtlSeconds(): int
    {
        $override = self::dbOverride('VENUE_LISTINGS_TTL');
        return max(300, $override !== null ? (int) $override : Env::getInt('VENUE_LISTINGS_TTL', 21600));
    }

    public static function setupWeight(): float
    {
        return self::weight('CONFLUENCE_SETUP_WEIGHT', 18.0);
    }

    public static function rangeWeight(): float
    {
        return self::weight('CONFLUENCE_RANGE_WEIGHT', 16.0);
    }

    public static function structureWeight(): float
    {
        return self::weight('CONFLUENCE_STRUCTURE_WEIGHT', 14.0);
    }

    public static function liquidityWeight(): float
    {
        return self::weight('CONFLUENCE_LIQUIDITY_WEIGHT', 12.0);
    }

    public static function trendWeight(): float
    {
        return self::weight('CONFLUENCE_TREND_WEIGHT', 12.0);
    }

    public static function zoneWeight(): float
    {
        return self::weight('CONFLUENCE_ZONE_WEIGHT', 14.0);
    }

    public static function internalStructureWeight(): float
    {
        return self::weight('CONFLUENCE_INTERNAL_WEIGHT', 8.0);
    }

    public static function premiumDiscountWeight(): float
    {
        return self::weight('CONFLUENCE_PD_WEIGHT', 12.0);
    }

    public static function oteWeight(): float
    {
        return self::weight('CONFLUENCE_OTE_WEIGHT', 10.0);
    }

    public static function htfWeight(): float
    {
        return self::weight('CONFLUENCE_HTF_WEIGHT', 14.0);
    }

    public static function breakerBlockWeight(): float
    {
        return self::weight('CONFLUENCE_BREAKER_WEIGHT', 16.0);
    }

    public static function squeezeWeight(): float
    {
        return self::weight('CONFLUENCE_SQUEEZE_WEIGHT', 10.0);
    }

    public static function rsiReversalWeight(): float
    {
        return self::weight('CONFLUENCE_RSI_REVERSAL_WEIGHT', 10.0);
    }

    public static function superTrendWeight(): float
    {
        return self::weight('CONFLUENCE_SUPERTREND_WEIGHT', 8.0);
    }

    public static function volumeImbalanceWeight(): float
    {
        return self::weight('CONFLUENCE_VOLUME_IMBALANCE_WEIGHT', 6.0);
    }

    public static function displacementWeight(): float
    {
        return self::weight('CONFLUENCE_DISPLACEMENT_WEIGHT', 6.0);
    }

    public static function strongZoneWeight(): float
    {
        return self::weight('CONFLUENCE_STRONG_ZONE_WEIGHT', 8.0);
    }

    public static function strongZoneVetoTouches(): int
    {
        $override = self::dbOverride('STRONG_ZONE_VETO_TOUCHES');
        return max(1, $override !== null ? (int) $override : Env::getInt('STRONG_ZONE_VETO_TOUCHES', 3));
    }

    public static function ema200Weight(): float
    {
        return self::weight('CONFLUENCE_EMA200_WEIGHT', 8.0);
    }

    public static function macdWeight(): float
    {
        return self::weight('CONFLUENCE_MACD_WEIGHT', 8.0);
    }

    public static function requireAdxFilter(): bool
    {
        $override = self::dbOverride('REQUIRE_ADX_FILTER');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_ADX_FILTER', false);
    }

    public static function minAdx(): float
    {
        $override = self::dbOverride('MIN_ADX');
        return $override !== null ? (float) $override : Env::getFloat('MIN_ADX', 20.0);
    }

    public static function maxOpposingVoteRatio(): float
    {
        $override = self::dbOverride('MAX_OPPOSING_VOTE_RATIO');
        $v = $override !== null ? (float) $override : Env::getFloat('MAX_OPPOSING_VOTE_RATIO', 0.6);
        return max(0.1, min(1.0, $v));
    }

    public static function requireBreakoutMomentum(): bool
    {
        $override = self::dbOverride('REQUIRE_BREAKOUT_MOMENTUM');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_BREAKOUT_MOMENTUM', true);
    }

    public static function fvgMitigationWeight(): float
    {
        return self::weight('CONFLUENCE_FVG_MITIGATION_WEIGHT', 8.0);
    }

    public static function minFvgMitigationPercent(): float
    {
        $override = self::dbOverride('MIN_FVG_MITIGATION_PCT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('MIN_FVG_MITIGATION_PCT', 5.0));
    }

    public static function confluenceStructureCap(): float
    {
        return self::weight('CONFLUENCE_STRUCTURE_CAP', 30.0);
    }

    public static function confluenceLiquidityCap(): float
    {
        return self::weight('CONFLUENCE_LIQUIDITY_CAP', 28.0);
    }

    public static function confluenceLocationCap(): float
    {
        return self::weight('CONFLUENCE_LOCATION_CAP', 26.0);
    }

    public static function confluenceMomentumCap(): float
    {
        return self::weight('CONFLUENCE_MOMENTUM_CAP', 22.0);
    }

    public static function confluenceVolumeCap(): float
    {
        return self::weight('CONFLUENCE_VOLUME_CAP', 14.0);
    }

    public static function confluenceHtfCap(): float
    {
        return self::weight('CONFLUENCE_HTF_CAP', 22.0);
    }

    public static function requireKillzone(): bool
    {
        $override = self::dbOverride('REQUIRE_KILLZONE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_KILLZONE', false);
    }

    public static function requireDiscountPremium(): bool
    {
        $override = self::dbOverride('REQUIRE_DISCOUNT_PREMIUM');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_DISCOUNT_PREMIUM', true);
    }

    public static function requireHtfAlignment(): bool
    {
        $override = self::dbOverride('REQUIRE_HTF_ALIGNMENT');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_HTF_ALIGNMENT', true);
    }

    public static function bigMoveLookback(): int
    {
        $override = self::dbOverride('BIG_MOVE_LOOKBACK');
        return max(5, $override !== null ? (int) $override : Env::getInt('BIG_MOVE_LOOKBACK', 20));
    }

    public static function bigMoveConfirmBars(): int
    {
        $override = self::dbOverride('BIG_MOVE_CONFIRM_BARS');
        return max(1, $override !== null ? (int) $override : Env::getInt('BIG_MOVE_CONFIRM_BARS', 3));
    }

    public static function bigMoveMinWick(): float
    {
        $override = self::dbOverride('BIG_MOVE_MIN_WICK');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('BIG_MOVE_MIN_WICK', 0.15));
    }

    public static function bigMoveBodyStrength(): float
    {
        $override = self::dbOverride('BIG_MOVE_BODY_STRENGTH');
        return max(0.05, $override !== null ? (float) $override : Env::getFloat('BIG_MOVE_BODY_STRENGTH', 0.55));
    }

    public static function minConfluenceScore(): float
    {
        $override = self::dbOverride('MIN_CONFLUENCE_SCORE');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('MIN_CONFLUENCE_SCORE', 75.0));
    }

    public static function rangeAbsoluteCompression(): float
    {
        $override = self::dbOverride('RANGE_ABS_COMPRESSION');
        return max(0.05, $override !== null ? (float) $override : Env::getFloat('RANGE_ABS_COMPRESSION', 0.75));
    }

    public static function rangeBreakLookback(): int
    {
        $override = self::dbOverride('RANGE_BREAK_LOOKBACK');
        return max(1, $override !== null ? (int) $override : Env::getInt('RANGE_BREAK_LOOKBACK', 3));
    }

    public static function zoneReachAtr(): float
    {
        $override = self::dbOverride('ZONE_REACH_ATR');
        return max(0.2, $override !== null ? (float) $override : Env::getFloat('ZONE_REACH_ATR', 2.5));
    }

    public static function stopPadAtr(): float
    {
        $override = self::dbOverride('STOP_PAD_ATR');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('STOP_PAD_ATR', 0.25));
    }

    public static function stopPadAtrCalm(): float
    {
        $override = self::dbOverride('STOP_PAD_ATR_CALM');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('STOP_PAD_ATR_CALM', 0.15));
    }

    public static function stopPadAtrVolatile(): float
    {
        $override = self::dbOverride('STOP_PAD_ATR_VOLATILE');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('STOP_PAD_ATR_VOLATILE', 0.35));
    }

    public static function volatilityCalmRatio(): float
    {
        $override = self::dbOverride('VOLATILITY_CALM_RATIO');
        return max(0.05, $override !== null ? (float) $override : Env::getFloat('VOLATILITY_CALM_RATIO', 0.85));
    }

    public static function volatilityHotRatio(): float
    {
        $override = self::dbOverride('VOLATILITY_HOT_RATIO');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('VOLATILITY_HOT_RATIO', 1.3));
    }

    public static function minRoomToTargetR(): float
    {
        $override = self::dbOverride('MIN_ROOM_TO_TARGET_R');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('MIN_ROOM_TO_TARGET_R', 1.5));
    }

    public static function structureMaxAge(): int
    {
        $override = self::dbOverride('STRUCTURE_MAX_AGE');
        return max(1, $override !== null ? (int) $override : Env::getInt('STRUCTURE_MAX_AGE', 3));
    }

    private static function weight(string $key, float $default): float
    {
        $override = self::dbOverride($key);
        return max(0.0, $override !== null ? (float) $override : Env::getFloat($key, $default));
    }

    public static function setupVolumeMultiple(): float
    {
        $override = self::dbOverride('SETUP_VOLUME_MULT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('SETUP_VOLUME_MULT', 1.0));
    }

    public static function vwapAwayBars(): int
    {
        $override = self::dbOverride('VWAP_AWAY_BARS');
        return max(1, $override !== null ? (int) $override : Env::getInt('VWAP_AWAY_BARS', 6));
    }

    public static function emaTouchWindow(): int
    {
        $override = self::dbOverride('EMA_TOUCH_WINDOW');
        return max(1, $override !== null ? (int) $override : Env::getInt('EMA_TOUCH_WINDOW', 3));
    }

    public static function setupPivotLength(): int
    {
        $override = self::dbOverride('SETUP_PIVOT_LENGTH');
        return max(2, $override !== null ? (int) $override : Env::getInt('SETUP_PIVOT_LENGTH', 5));
    }

    public static function retestTolerance(): float
    {
        $override = self::dbOverride('RETEST_TOLERANCE_ATR');
        return max(0.01, $override !== null ? (float) $override : Env::getFloat('RETEST_TOLERANCE_ATR', 0.3));
    }

    public static function retestWindow(): int
    {
        $override = self::dbOverride('RETEST_WINDOW');
        return max(2, $override !== null ? (int) $override : Env::getInt('RETEST_WINDOW', 20));
    }

    public static function sweepLookback(): int
    {
        $override = self::dbOverride('SWEEP_LOOKBACK');
        return max(5, $override !== null ? (int) $override : Env::getInt('SWEEP_LOOKBACK', 20));
    }

    public static function divergenceGap(): int
    {
        $override = self::dbOverride('DIVERGENCE_GAP');
        return max(5, $override !== null ? (int) $override : Env::getInt('DIVERGENCE_GAP', 60));
    }

    public static function strategyName(): string
    {
        $override = self::dbOverride('STRATEGY');
        $name = trim((string) ($override ?? Env::get('STRATEGY', 'confluence_pro') ?? 'confluence_pro'));
        return $name === '' ? 'confluence_pro' : $name;
    }

    public static function breakVolumeRatio(): float
    {
        $override = self::dbOverride('BREAK_VOLUME_RATIO');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('BREAK_VOLUME_RATIO', 1.3));
    }

    public static function maxChaseAtr(): float
    {
        $override = self::dbOverride('MAX_CHASE_ATR');
        return max(0.1, $override !== null ? (float) $override : Env::getFloat('MAX_CHASE_ATR', 1.5));
    }

    public static function reversalRunPercent(): float
    {
        $override = self::dbOverride('REVERSAL_RUN_PCT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('REVERSAL_RUN_PCT', 12.0));
    }

    public static function baseRangePercent(): float
    {
        $override = self::dbOverride('BASE_RANGE_PCT');
        return max(0.5, $override !== null ? (float) $override : Env::getFloat('BASE_RANGE_PCT', 12.0));
    }

    public static function requireZoneConfluence(): bool
    {
        $override = self::dbOverride('REQUIRE_ZONE_CONFLUENCE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_ZONE_CONFLUENCE', true);
    }

    public static function requireReversalCandle(): bool
    {
        $override = self::dbOverride('REQUIRE_REVERSAL_CANDLE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_REVERSAL_CANDLE', true);
    }

    public static function reversalConfirmTimeframe(): string
    {
        $override = self::dbOverride('REVERSAL_CONFIRM_TIMEFRAME');
        $tf = trim((string) ($override ?? Env::get('REVERSAL_CONFIRM_TIMEFRAME', '5m') ?? '5m'));
        return $tf === '' ? '5m' : $tf;
    }

    public static function requireFreshReversalZone(): bool
    {
        $override = self::dbOverride('REQUIRE_FRESH_REVERSAL_ZONE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_FRESH_REVERSAL_ZONE', true);
    }

    public static function requireSweepAtZone(): bool
    {
        $override = self::dbOverride('REQUIRE_SWEEP_AT_ZONE');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('REQUIRE_SWEEP_AT_ZONE', true);
    }

    public static function rotationBudgetSeconds(): int
    {
        $override = self::dbOverride('ROTATION_BUDGET_SECONDS');
        return max(5, $override !== null ? (int) $override : Env::getInt('ROTATION_BUDGET_SECONDS', 32));
    }

    public static function rotationMaxSymbols(): int
    {
        $override = self::dbOverride('ROTATION_MAX_SYMBOLS');
        return max(1, $override !== null ? (int) $override : Env::getInt('ROTATION_MAX_SYMBOLS', 400));
    }

    public static function scannerGainerShare(): float
    {
        $override = self::dbOverride('SCANNER_GAINER_SHARE');
        return min(100.0, max(0.0, $override !== null ? (float) $override : Env::getFloat('SCANNER_GAINER_SHARE', 40.0)));
    }

    public static function scannerLoserShare(): float
    {
        $override = self::dbOverride('SCANNER_LOSER_SHARE');
        return min(100.0, max(0.0, $override !== null ? (float) $override : Env::getFloat('SCANNER_LOSER_SHARE', 25.0)));
    }

    public static function scannerMinMovePercent(): float
    {
        $override = self::dbOverride('SCANNER_MIN_MOVE_PCT');
        return max(0.0, $override !== null ? (float) $override : Env::getFloat('SCANNER_MIN_MOVE_PCT', 4.0));
    }

    public static function accountBalance(): float
    {
        $override = self::dbOverride('ACCOUNT_BALANCE');
        return max(1.0, $override !== null ? (float) $override : Env::getFloat('ACCOUNT_BALANCE', 1000.0));
    }

    public static function riskPerTradePercent(): float
    {
        $override = self::dbOverride('RISK_PER_TRADE_PCT');
        $v = $override !== null ? (float) $override : Env::getFloat('RISK_PER_TRADE_PCT', 2.0);
        return min(100.0, max(0.1, $v));
    }

    public static function maxDailyLosses(): int
    {
        $override = self::dbOverride('MAX_DAILY_LOSSES');
        return max(1, $override !== null ? (int) $override : Env::getInt('MAX_DAILY_LOSSES', 6));
    }

    public static function maxDailySignals(): int
    {
        $override = self::dbOverride('MAX_DAILY_SIGNALS');
        return max(0, $override !== null ? (int) $override : Env::getInt('MAX_DAILY_SIGNALS', 24));
    }

    public static function maxOpenTrades(): int
    {
        $override = self::dbOverride('MAX_OPEN_TRADES');
        return max(0, $override !== null ? (int) $override : Env::getInt('MAX_OPEN_TRADES', 3));
    }

    public static function minSignalGapMinutes(): int
    {
        $override = self::dbOverride('MIN_SIGNAL_GAP_MINUTES');
        return max(0, $override !== null ? (int) $override : Env::getInt('MIN_SIGNAL_GAP_MINUTES', 30));
    }

    public static function maxTradeHours(): int
    {
        $override = self::dbOverride('MAX_TRADE_HOURS');
        return max(0, $override !== null ? (int) $override : Env::getInt('MAX_TRADE_HOURS', 24));
    }

    public static function signalsPerPass(): int
    {
        $override = self::dbOverride('SIGNALS_PER_PASS');
        return max(1, $override !== null ? (int) $override : Env::getInt('SIGNALS_PER_PASS', 1));
    }

    public static function riskFreeEnabled(): bool
    {
        $override = self::dbOverride('RISK_FREE_ENABLED');
        if ($override !== null) {
            return in_array(strtolower($override), ['1', 'true', 'yes', 'on'], true);
        }
        return Env::getBool('RISK_FREE_ENABLED', true);
    }

    public static function tp1ClosePercent(): float
    {
        $override = self::dbOverride('TP1_CLOSE_PERCENT');
        $value = $override !== null ? (float) $override : Env::getFloat('TP1_CLOSE_PERCENT', 50.0);
        return max(0.0, min(100.0, $value));
    }

    public static function advisoryStopWarnPercent(): float
    {
        $override = self::dbOverride('ADVISORY_STOP_WARN_PCT');
        $value = $override !== null ? (float) $override : Env::getFloat('ADVISORY_STOP_WARN_PCT', 70.0);
        return max(1.0, min(100.0, $value));
    }

    public static function advisoryStallRetracePercent(): float
    {
        $override = self::dbOverride('ADVISORY_STALL_RETRACE_PCT');
        $value = $override !== null ? (float) $override : Env::getFloat('ADVISORY_STALL_RETRACE_PCT', 50.0);
        return max(1.0, min(100.0, $value));
    }

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
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_MAJOR', 20));
    }

    public static function leverageAltMin(): int
    {
        $override = self::dbOverride('LEVERAGE_ALT_MIN');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_ALT_MIN', 20));
    }

    public static function leverageAltMax(): int
    {
        $override = self::dbOverride('LEVERAGE_ALT_MAX');
        return max(1, $override !== null ? (int) $override : Env::getInt('LEVERAGE_ALT_MAX', 25));
    }

    public static function leverageLiquidationBuffer(): float
    {
        $override = self::dbOverride('LEVERAGE_LIQUIDATION_BUFFER');
        $v = $override !== null ? (float) $override : Env::getFloat('LEVERAGE_LIQUIDATION_BUFFER', 0.75);
        return max(0.05, min(0.95, $v));
    }

    public static function signalMaxSymbolsPerPass(): int
    {
        $override = self::dbOverride('SIGNAL_MAX_SYMBOLS_PER_PASS');

        return max(0, $override !== null ? (int) $override : Env::getInt('SIGNAL_MAX_SYMBOLS_PER_PASS', 0));
    }

    public static function persistStructure(): bool
    {
        return Env::getBool('PERSIST_STRUCTURE', false);
    }

    public static function minStopPercent(): float
    {
        $override = self::dbOverride('MIN_STOP_PERCENT');
        return max(0.01, $override !== null ? (float) $override : Env::getFloat('MIN_STOP_PERCENT', 0.12));
    }

    public static function runMode(): RunMode
    {
        $v = strtoupper(Env::get('RUN_MODE', 'DRY_RUN') ?? 'DRY_RUN');
        return $v === 'LIVE' ? RunMode::LIVE : RunMode::DRY_RUN;
    }

    public static function workerTickSeconds(): int
    {
        return Env::getInt('WORKER_TICK_SECONDS', 15);
    }

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

    public static function logMinLevel(): string
    {
        $v = strtolower(Env::get('LOG_LEVEL', 'error') ?? 'error');
        return in_array($v, ['debug', 'info', 'warning', 'error', 'critical'], true) ? $v : 'error';
    }

    public static function logRetentionDays(): int
    {
        return max(0, Env::getInt('LOG_RETENTION_DAYS', 3));
    }

    private const CHANNEL_BUTTON_STYLES = ['primary', 'success', 'danger'];
    private const CHANNEL_BUTTON_DEFAULTS = [
        1 => ['text' => '📢 کانال ما', 'url' => 'https://t.me', 'style' => 'success'],
    ];

    public static function channelButtonText(int $slot): string
    {
        $default = self::CHANNEL_BUTTON_DEFAULTS[$slot]['text'] ?? '';
        return TextStore::value("BUTTON{$slot}_TEXT") ?? $default;
    }

    public static function channelButtonUrl(int $slot): string
    {
        $default = self::CHANNEL_BUTTON_DEFAULTS[$slot]['url'] ?? '';
        return TextStore::value("BUTTON{$slot}_URL") ?? $default;
    }

    public static function channelButtonStyle(int $slot): string
    {
        $v = TextStore::value("BUTTON{$slot}_STYLE");
        if ($v !== null && in_array($v, self::CHANNEL_BUTTON_STYLES, true)) {
            return $v;
        }
        return self::CHANNEL_BUTTON_DEFAULTS[$slot]['style'] ?? 'primary';
    }

    public static function channelButtonEmojiId(int $slot): ?string
    {
        return TextStore::value("BUTTON{$slot}_EMOJI_ID");
    }

    public static function channelButtonStyles(): array
    {
        return self::CHANNEL_BUTTON_STYLES;
    }

    public static function channelButtonsKeyboard(): ?array
    {
        $text = trim(self::channelButtonText(1));
        $url = trim(self::channelButtonUrl(1));
        if ($text === '' || $url === '') {
            return null;
        }
        $button = ['text' => $text, 'url' => $url, 'style' => self::channelButtonStyle(1)];
        $emojiId = self::channelButtonEmojiId(1);
        if ($emojiId !== null && $emojiId !== '') {
            $button['icon_custom_emoji_id'] = $emojiId;
        }
        return ['inline_keyboard' => [[$button]]];
    }
}

date_default_timezone_set(Config::appTimezone());

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
                PDO::ATTR_TIMEOUT            => 10,
            ]);

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

        $htaccess = $dir . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\nDeny from all\n");
        }
    }

    public static function migrate(): void
    {
        $pdo = self::pdo();

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

        self::ensureColumn($pdo, 'signals', 'outcome', "TEXT NULL CHECK (outcome IS NULL OR outcome IN ('tp1','sl'))");
        self::ensureColumn($pdo, 'signals', 'resolved_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'resolved_price', 'REAL NULL');

        self::ensureColumn($pdo, 'signals', 'leverage', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tier', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'stage', "TEXT NOT NULL DEFAULT 'open'");
        self::ensureColumn($pdo, 'signals', 'result', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'active_stop', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp1_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp1_hit_price', 'REAL NULL');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_signals_open ON signals (status, resolved_at)');

        self::ensureColumn($pdo, 'signals', 'tp4', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp2_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp2_hit_price', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'tp3_hit_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'tp3_hit_price', 'REAL NULL');
        self::ensureColumn($pdo, 'signals', 'peak_price', 'REAL NULL');

        self::ensureColumn($pdo, 'signals', 'advisory_sent_at', 'TEXT NULL');
        self::ensureColumn($pdo, 'signals', 'stall_advisory_sent_at', 'TEXT NULL');

        self::ensureColumn($pdo, 'symbols', 'change_24h', 'REAL NOT NULL DEFAULT 0');
        self::ensureColumn($pdo, 'symbols', 'bucket', "TEXT NOT NULL DEFAULT 'liquid'");

        self::pruneLogs($pdo);
        self::moveTextsToFile($pdo);
        self::seedDefaults($pdo);
        self::resetLooseScoreOverride($pdo);
    }

    private static function resetLooseScoreOverride(PDO $pdo): void
    {
        $flag = 'migration_score_reset_v1';
        $stmt = $pdo->prepare('SELECT 1 FROM bot_settings WHERE setting_key = :k');
        $stmt->execute([':k' => $flag]);
        if ($stmt->fetchColumn() !== false) {
            return;
        }
        $read = $pdo->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
        $read->execute([':k' => 'MIN_CONFLUENCE_SCORE']);
        $value = $read->fetchColumn();
        if ($value !== false && is_numeric($value) && (float) $value < Env::getFloat('MIN_CONFLUENCE_SCORE', 80.0)) {
            $pdo->prepare('DELETE FROM bot_settings WHERE setting_key = :k')->execute([':k' => 'MIN_CONFLUENCE_SCORE']);
        }
        $pdo->prepare('INSERT OR IGNORE INTO bot_settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :now)')
            ->execute([':k' => $flag, ':v' => '1', ':now' => date('Y-m-d H:i:s')]);
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

    public static function maintain(): void
    {
        $pdo = self::pdo();
        $read = $pdo->prepare('SELECT setting_value FROM bot_settings WHERE setting_key = :k');
        $read->execute([':k' => 'db_maintenance_at']);
        if (time() - (int) ($read->fetchColumn() ?: 0) < 86400) {
            return;
        }
        $pdo->prepare(
            "INSERT INTO bot_settings (setting_key, setting_value, updated_at) VALUES ('db_maintenance_at', :v, :now)
             ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = excluded.updated_at"
        )->execute([':v' => (string) time(), ':now' => date('Y-m-d H:i:s')]);

        $cutoff = time() - 3 * 86400;
        $since = date('Y-m-d H:i:s', $cutoff);
        try {
            $stale = $pdo->prepare('SELECT exchange_id, symbol, timeframe FROM candles GROUP BY exchange_id, symbol, timeframe HAVING MAX(open_time) < :c');
            $stale->bindValue(':c', $cutoff * 1000, PDO::PARAM_INT);
            $stale->execute();
            $drop = $pdo->prepare('DELETE FROM candles WHERE exchange_id = :e AND symbol = :s AND timeframe = :t');
            foreach ($stale->fetchAll() as $row) {
                $drop->execute([':e' => $row['exchange_id'], ':s' => $row['symbol'], ':t' => $row['timeframe']]);
            }
            foreach (['zones', 'order_blocks', 'fvgs'] as $table) {
                $pdo->prepare("DELETE FROM $table WHERE created_at < :c")->execute([':c' => $since]);
            }
            $pdo->prepare('DELETE FROM scanner_runs WHERE started_at < :c')->execute([':c' => $since]);
            $pdo->prepare('DELETE FROM admin_states WHERE updated_at < :c')->execute([':c' => $since]);
        } catch (Throwable $e) {
            Logger::warning('db', 'cleanup failed', ['error' => $e->getMessage()]);
        }
        try {
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
            $pdo->exec('VACUUM');
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        } catch (Throwable $e) {
            Logger::warning('db', 'vacuum failed', ['error' => $e->getMessage()]);
        }
    }

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
        }
    }

    private const LEGACY_SIGNAL_TEMPLATE = "🔔 سیگنال جدید\n\nنماد: {symbol}\nصرافی: {exchange}\nجهت: {direction}\nتایم‌فریم: {timeframe}\n\nورود: {entry}\nحد ضرر: {sl}\n\nهدف ۱: {tp1}\nهدف ۲: {tp2}\nهدف ۳: {tp3}\n\nامتیاز: {score}\nاستراتژی: {strategy}\n\nدلایل:\n{reasons}";

    private const AUTO_SIGNAL_TEMPLATE_V1 = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم پیشنهادی: {leverage}\n🏷 نوع ارز: {tier}\n\n📍 نقطه ورود: {entry}\n🛑 حد ضرر: {sl}\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n🔻 فاصله حد ضرر: {risk_pct}%\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    private const AUTO_SIGNAL_TEMPLATE_V2 = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم پیشنهادی: {leverage}\n🏷 نوع ارز: {tier}\n\n⚡️ نوع ورود: {entry_mode} — همین الان وارد شوید\n📍 قیمت ورود: {entry}\n🛑 حد ضرر: {sl}\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n🔻 فاصله حد ضرر: {risk_pct}%\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    private const AUTO_SIGNAL_TEMPLATE_V3 = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم: {leverage}\n🏷 نوع ارز: {tier}\n\n⚡️ نوع ورود: {entry_mode} — همین الان وارد شوید\n📍 قیمت ورود: {entry}\n🛑 حد ضرر: {sl}  ({sl_loss}% با اهرم)\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n\n💼 مدیریت سرمایه\n• سرمایه مرجع: {balance}\n• ریسک این معامله: {risk_per_trade} یعنی {risk_amount}\n• مارجین پیشنهادی: {margin}\n• حجم پوزیشن: {position_size}\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n\n♻️ بعد از تارگت ۱ حد ضرر روی نقطه ورود منتقل می‌شود (ریسک‌فری) و معامله تا تارگت ۲ ادامه پیدا می‌کند.";

    private const AUTO_SIGNAL_TEMPLATE = "🚨 سیگنال جدید | {direction_fa}\n\n💎 ارز: {symbol}\n🏦 صرافی: {exchange}\n⏱ تایم‌فریم: {timeframe}\n⚡️ اهرم: {leverage}\n🏷 نوع ارز: {tier}\n\n⚡️ نوع ورود: {entry_mode} — همین الان وارد شوید\n📍 قیمت ورود: {entry}\n🛑 حد ضرر: {sl}  ({sl_loss}% با اهرم)\n\n🎯 تارگت ۱: {tp1}  ({tp1_profit}% با اهرم)\n🎯 تارگت ۲: {tp2}  ({tp2_profit}% با اهرم)\n🎯 تارگت ۳: {tp3}  ({tp3_profit}% با اهرم)\n🎯 تارگت ۴: {tp4}  ({tp4_profit}% با اهرم)\n\n💼 مدیریت سرمایه\n• سرمایه مرجع: {balance}\n• ریسک این معامله: {risk_per_trade} یعنی {risk_amount}\n• مارجین پیشنهادی: {margin}\n• حجم پوزیشن: {position_size}\n\n⚖️ ریسک به ریوارد: {rr}\n📊 امتیاز: {score} | اعتبار: {confidence_fa}\n\n♻️ بعد از هر تارگت، حد ضرر به سمت تارگت قبلی منتقل می‌شود (ریسک‌فری و سپس تریلینگ) و معامله تا تارگت ۴ ادامه پیدا می‌کند.";

    private const RESULT_TP2_V1 = "🏆 تارگت ۲ فعال شد | {symbol}\n\n💰 سود نهایی با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n✅ معامله با موفقیت بسته شد. ربات به سراغ سیگنال بعدی می‌رود.";

    private static function moveTextsToFile(PDO $pdo): void
    {
        try {
            $hasTable = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'text_formats'")->fetchColumn() !== false;
            $buttons = $pdo->query("SELECT setting_key, setting_value FROM bot_settings WHERE setting_key LIKE 'BUTTON%'")->fetchAll();
            if (!$hasTable && empty($buttons)) {
                return;
            }
            $rows = $hasTable ? $pdo->query('SELECT text_key, text_value, entities, updated_at, updated_by FROM text_formats')->fetchAll() : [];
            TextStore::update(static function (array $data) use ($rows, $buttons): array {
                foreach ($rows as $row) {
                    $key = (string) $row['text_key'];
                    if (isset($data['texts'][$key])) {
                        continue;
                    }
                    $entities = json_decode((string) ($row['entities'] ?? '[]'), true);
                    $data['texts'][$key] = [
                        'text' => (string) $row['text_value'],
                        'entities' => is_array($entities) ? array_values($entities) : [],
                        'updated_at' => (string) $row['updated_at'],
                        'updated_by' => $row['updated_by'] !== null ? (int) $row['updated_by'] : null,
                    ];
                }
                foreach ($buttons as $row) {
                    $key = (string) $row['setting_key'];
                    if (!isset($data['buttons'][$key]) && (string) $row['setting_value'] !== '') {
                        $data['buttons'][$key] = (string) $row['setting_value'];
                    }
                }
                return $data;
            });
            $saved = TextStore::snapshot();
            foreach ($rows as $row) {
                if (!isset($saved['texts'][(string) $row['text_key']])) {
                    return;
                }
            }
            foreach ($buttons as $row) {
                if ((string) $row['setting_value'] !== '' && !isset($saved['buttons'][(string) $row['setting_key']])) {
                    return;
                }
            }
            if ($hasTable) {
                $pdo->exec('DROP TABLE IF EXISTS text_formats');
            }
            $pdo->exec("DELETE FROM bot_settings WHERE setting_key LIKE 'BUTTON%'");
        } catch (Throwable $e) {
            Logger::error('texts', 'could not move texts to texts.json', ['error' => $e->getMessage()]);
        }
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $now = date('Y-m-d H:i:s');

        $exchangeNames = [
            'binance' => 'Binance', 'mexc' => 'MEXC', 'bybit' => 'Bybit', 'okx' => 'OKX',
            'kucoin' => 'KuCoin', 'gate' => 'Gate.io', 'bitget' => 'Bitget', 'htx' => 'HTX',
            'cryptocompare' => 'CryptoCompare',
        ];

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

        $defaults = [
            'welcome' => "به ربات سیگنال خوش آمدید.",
            'help' => "برای مشاهده راهنما با ادمین در تماس باشید.",
            'signal_template' => self::AUTO_SIGNAL_TEMPLATE,
            'signal_long' => "🟢 سیگنال LONG برای {symbol}",
            'signal_short' => "🟡 سیگنال SHORT برای {symbol}",
            'error' => "خطایی رخ داد. لطفاً دوباره تلاش کنید.",
            'channel_added' => "✅ کانال با موفقیت اضافه شد.",
            'scanner_status' => "وضعیت اسکنر: {status}",

            'result_tp1' => "✅ تارگت ۱ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 معامله ریسک‌فری شد — حد ضرر روی نقطه ورود ({entry}) منتقل شد و از این لحظه این معامله ضرری ندارد.\n🎯 تارگت بعدی: {tp2}",
            'result_tp2' => "🏆 تارگت ۲ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 حد ضرر به سطح تارگت ۱ منتقل شد — از این لحظه سود این معامله قفل شده.\n🎯 تارگت بعدی: {tp3}",
            'result_tp3' => "🏆 تارگت ۳ فعال شد | {symbol}\n\n💰 سود با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n🛡 حد ضرر به سطح تارگت ۲ منتقل شد.\n🎯 تارگت نهایی: {tp4}\n\n👀 اگه حرکت اینجا بایسته و برنگرده به تارگت ۴، ممکنه پیشنهاد بدیم زودتر خارج بشی.",
            'result_tp4' => "🏆🏆 تارگت ۴ (نهایی) فعال شد | {symbol}\n\n💰 سود نهایی با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n\n✅ معامله با موفقیت و کامل بسته شد. ربات به سراغ سیگنال بعدی می‌رود.",
            'result_trail' => "🛡 معامله با سود قفل‌شده بسته شد | {symbol}\n\nقیمت بعد از رسیدن به یکی از تارگت‌ها برگشت و به حد ضرر تریلینگ (بالاتر از نقطه ورود) خورد.\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n💰 نتیجه با اهرم {leverage}: {pnl}%\n\nربات به سراغ سیگنال بعدی می‌رود.",
            'result_sl' => "❌ حد ضرر فعال شد | {symbol}\n\n📉 نتیجه با اهرم {leverage}: {pnl}%\n📈 حرکت قیمت: {move}%\n\n📍 ورود: {entry}\n🛑 خروج: {exit}\n\nمعامله بسته شد. ربات به سراغ سیگنال بعدی می‌رود.",
            'result_be' => "🛡 معامله بدون ضرر بسته شد | {symbol}\n\nقیمت بعد از فعال شدن تارگت ۱ به نقطه ورود برگشت و چون معامله ریسک‌فری شده بود، بدون ضرر بسته شد.\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n💰 نتیجه با اهرم {leverage}: {pnl}%\n\nربات به سراغ سیگنال بعدی می‌رود.",
            'result_timeout' => "⏱ معامله به‌خاطر طول کشیدن بسته شد | {symbol}\n\nقیمت در زمان مجاز به تارگت ۱ نرسید، پس ستاپ اعتبارش رو از دست داد و معامله با قیمت فعلی بسته شد.\n\n📍 ورود: {entry}\n🎯 خروج: {exit}\n💰 نتیجه با اهرم {leverage}: {pnl}%\n\nربات به سراغ سیگنال بعدی می‌رود.",
        ];
        $upgrades = [
            ['signal_template', self::LEGACY_SIGNAL_TEMPLATE, self::AUTO_SIGNAL_TEMPLATE],
            ['signal_template', self::AUTO_SIGNAL_TEMPLATE_V1, self::AUTO_SIGNAL_TEMPLATE],
            ['signal_template', self::AUTO_SIGNAL_TEMPLATE_V2, self::AUTO_SIGNAL_TEMPLATE],
            ['signal_template', self::AUTO_SIGNAL_TEMPLATE_V3, self::AUTO_SIGNAL_TEMPLATE],
            ['result_tp2', self::RESULT_TP2_V1, $defaults['result_tp2']],
        ];
        $apply = static function (array $data) use ($defaults, $upgrades, $now): array {
            foreach ($defaults as $key => $value) {
                if (!isset($data['texts'][$key])) {
                    $data['texts'][$key] = ['text' => $value, 'entities' => [], 'updated_at' => $now, 'updated_by' => null];
                }
            }
            foreach ($upgrades as [$key, $old, $new]) {
                $entry = $data['texts'][$key] ?? null;
                if (is_array($entry) && (string) ($entry['text'] ?? '') === $old && empty($entry['entities'])) {
                    $data['texts'][$key] = ['text' => $new, 'entities' => [], 'updated_at' => $now, 'updated_by' => null];
                }
            }
            return $data;
        };
        try {
            $current = TextStore::snapshot();
            $probe = $apply($current);
            if ($probe !== $current) {
                TextStore::update($apply);
            }
        } catch (Throwable $e) {
            Logger::error('texts', 'could not seed texts.json', ['error' => $e->getMessage()]);
        }

        foreach (Config::adminIds() as $adminId) {
            $ins = $pdo->prepare(
                "INSERT INTO admins (telegram_user_id, role, created_at) VALUES (:id, 'owner', :now)
                 ON CONFLICT(telegram_user_id) DO NOTHING"
            );
            $ins->execute([':id' => $adminId, ':now' => $now]);
        }
    }
}

final class TextStore
{
    private const EMPTY = ['texts' => [], 'buttons' => []];

    public static function path(): string
    {
        return Config::storageDir() . '/texts.json';
    }

    public static function snapshot(): array
    {
        return self::load() ?? self::EMPTY;
    }

    public static function text(string $key): ?array
    {
        $entry = self::snapshot()['texts'][$key] ?? null;
        if (!is_array($entry) || !isset($entry['text'])) {
            return null;
        }
        return [
            'text' => (string) $entry['text'],
            'entities' => is_array($entry['entities'] ?? null) ? $entry['entities'] : [],
        ];
    }

    public static function textKeys(): array
    {
        $keys = array_map('strval', array_keys(self::snapshot()['texts']));
        sort($keys);
        return $keys;
    }

    public static function putText(string $key, string $text, array $entities, ?int $updatedBy = null): void
    {
        self::update(static function (array $data) use ($key, $text, $entities, $updatedBy): array {
            $data['texts'][$key] = [
                'text' => $text,
                'entities' => array_values($entities),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $updatedBy,
            ];
            return $data;
        });
    }

    public static function value(string $key): ?string
    {
        $value = self::snapshot()['buttons'][$key] ?? null;
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    public static function putValue(string $key, ?string $value): void
    {
        self::update(static function (array $data) use ($key, $value): array {
            if ($value === null || $value === '') {
                unset($data['buttons'][$key]);
            } else {
                $data['buttons'][$key] = $value;
            }
            return $data;
        });
    }

    public static function update(callable $change): void
    {
        $dir = Config::storageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $lock = @fopen($dir . '/.texts.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('texts.json lock could not be opened');
        }
        try {
            flock($lock, LOCK_EX);
            $current = self::load();
            if ($current === null) {
                throw new RuntimeException('texts.json is not valid JSON; leaving it untouched');
            }
            $next = $change($current);
            $next = [
                'texts' => is_array($next['texts'] ?? null) ? $next['texts'] : [],
                'buttons' => is_array($next['buttons'] ?? null) ? $next['buttons'] : [],
            ];
            ksort($next['texts']);
            ksort($next['buttons']);
            if ($next === $current && is_file(self::path())) {
                return;
            }
            $json = json_encode($next, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                throw new RuntimeException('texts could not be encoded');
            }
            $tmp = self::path() . '.tmp';
            if (@file_put_contents($tmp, $json . "\n") === false || !@rename($tmp, self::path())) {
                @unlink($tmp);
                throw new RuntimeException('texts.json could not be written');
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function load(): ?array
    {
        $path = self::path();
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return self::EMPTY;
        }
        $raw = @file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return null;
        }
        return [
            'texts' => is_array($data['texts'] ?? null) ? $data['texts'] : [],
            'buttons' => is_array($data['buttons'] ?? null) ? $data['buttons'] : [],
        ];
    }
}

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

final class Logger
{
    private const SECRET_PATTERNS = ['token', 'secret', 'password', 'api_key', 'apikey'];

    private static $stderr = null;
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

final class TelegramEntityUtils
{
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

    public static function renderTemplate(string $template, array $entities, array $placeholders): array
    {
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

        $text = $template;
        foreach (array_reverse($tokens) as $token) {
            $text = substr_replace($text, $token['value'], $token['byte_start'], $token['byte_len']);
        }

        $workingEntities = array_values(array_filter($workingEntities, static fn($e) => ((int) ($e['length'] ?? 0)) > 0));

        return ['text' => $text, 'entities' => $workingEntities];
    }

    private static function mapOffset(array $tokens, int $pos, bool $isEnd): int
    {
        $shift = 0;
        foreach ($tokens as $token) {
            if ($token['utf16_end'] <= $pos) {
                $shift += $token['delta'];
                continue;
            }
            if ($token['utf16_start'] < $pos && $pos < $token['utf16_end']) {
                return $isEnd
                    ? $token['utf16_start'] + $shift + self::utf16Length($token['value'])
                    : $token['utf16_start'] + $shift;
            }

            break;
        }
        return $pos + $shift;
    }

    public static function stripCustomEmoji(array $entities): array
    {
        return array_values(array_filter(
            $entities,
            static fn($e) => ($e['type'] ?? '') !== 'custom_emoji'
        ));
    }

    public static function hasCustomEmoji(array $entities): bool
    {
        foreach ($entities as $e) {
            if (($e['type'] ?? '') === 'custom_emoji') {
                return true;
            }
        }
        return false;
    }

    public static function hasButtonEmojiIcon(array $keyboard): bool
    {
        foreach ($keyboard['inline_keyboard'] ?? [] as $row) {
            foreach ($row as $button) {
                if (!empty($button['icon_custom_emoji_id'])) {
                    return true;
                }
            }
        }
        return false;
    }

    public static function stripButtonEmojiIcons(array $keyboard): array
    {
        $keyboard['inline_keyboard'] = array_map(
            static function (array $row): array {
                return array_map(static function (array $button): array {
                    unset($button['icon_custom_emoji_id']);
                    return $button;
                }, $row);
            },
            $keyboard['inline_keyboard'] ?? []
        );
        return $keyboard;
    }
}

<?php

/**
 * Copy this file to `env.php` on the server and fill in the real values.
 * `env.php` is git-ignored on purpose — a bot token in the repository is a
 * compromised bot token.
 *
 * Values set from the Telegram admin panel are stored in the database and
 * take precedence over the ones here, so anything marked "panel-editable"
 * can be retuned live without touching this file.
 */
return [
    // ---------------------------------------------------------------------
    // Telegram
    // ---------------------------------------------------------------------
    'TELEGRAM_BOT_TOKEN' => 'PUT-YOUR-BOT-TOKEN-HERE',
    // Leave empty to disable the check. If you set one here you must also
    // register the same value with setWebhook, or every update is rejected.
    'TELEGRAM_WEBHOOK_SECRET' => '',
    // Numeric Telegram user IDs allowed into the admin panel, comma separated.
    'ADMIN_IDS' => '',

    // ---------------------------------------------------------------------
    // Exchanges — public market data needs no API key on any of these.
    // Each exchange is isolated by a circuit breaker: one being blocked or
    // down just means it contributes 0 symbols.
    // ---------------------------------------------------------------------
    'ENABLED_EXCHANGES' => 'binance,mexc,wallex,bybit,okx,kucoin,cryptocompare',

    'BINANCE_API_KEY' => '',
    'BINANCE_API_SECRET' => '',
    'BINANCE_REST_BASE' => 'https://api.binance.com',
    'BINANCE_WS_BASE' => 'wss://stream.binance.com:9443',

    'MEXC_API_KEY' => '',
    'MEXC_API_SECRET' => '',
    'MEXC_REST_BASE' => 'https://api.mexc.com',
    'MEXC_WS_BASE' => 'wss://wbs.mexc.com',

    'WALLEX_API_KEY' => '',
    'WALLEX_REST_BASE' => 'https://api.wallex.ir',

    'BYBIT_REST_BASE' => 'https://api.bybit.com',
    'OKX_REST_BASE' => 'https://www.okx.com',
    'KUCOIN_REST_BASE' => 'https://api.kucoin.com',

    'CRYPTOCOMPARE_API_KEY' => '',
    'CRYPTOCOMPARE_REST_BASE' => 'https://min-api.cryptocompare.com',

    // ---------------------------------------------------------------------
    // Scanner — which symbols make it into the tradable universe.
    // The defaults are deliberately wide: this bot is meant to trade
    // altcoins and low-caps, not just majors. All panel-editable.
    // ---------------------------------------------------------------------
    'SCANNER_TOP_N' => '200',
    'SCANNER_INTERVAL_SECONDS' => '900',
    'MIN_VOLUME_USDT' => '0',
    'MAX_SPREAD_PERCENT' => '10',
    // Quote assets a pair may be priced in. Leave empty for the dollar
    // stablecoin set (USDT, USDC, FDUSD, BUSD, TUSD, DAI) — do NOT expect an
    // empty value to mean "everything". Regional exchanges list fiat pairs,
    // and without this filter the scanner ranks things like USDTTMN (Tether
    // priced in Iranian toman) as tradable. Use '*' if you really want no
    // filter at all.
    'ALLOWED_QUOTE_ASSETS' => 'USDT',

    // Whether stablecoin-vs-stablecoin pairs (USDCUSDT and friends) count as
    // tradable. They are pegs; leveraged signals on them are meaningless.
    'INCLUDE_STABLECOIN_PAIRS' => 'false',

    // Timeframes to COLLECT candles for. Must be a superset of
    // SIGNAL_TIMEFRAMES below, or those timeframes have no data to work with.
    'TIMEFRAMES' => '1m,5m,15m,1h,4h,1D',

    // ---------------------------------------------------------------------
    // Automatic mode — the behaviour described in 🤖 اتومات in the panel.
    // ---------------------------------------------------------------------

    // At most one signal every 15 minutes.
    'SIGNAL_INTERVAL_SECONDS' => '900',

    // How many trades may run at once. Set to 1 for strictly one trade at a
    // time (no new signal until the current one closes at TP2/SL). The
    // default of 3 keeps the 15-minute cadence meaningful when a trade runs
    // for hours; a symbol can never have two open trades either way.
    'MAX_OPEN_POSITIONS' => '3',

    // Timeframes a signal may be issued on.
    'SIGNAL_TIMEFRAMES' => '15m,1h,4h',

    // Targets, as multiples of the trade's risk. Every signal's R:R equals
    // TP1_RR by construction.
    'TP1_RR' => '1.2',
    'TP2_RR' => '1.3',

    // On TP1: move the stop to entry and announce the trade as risk free.
    'RISK_FREE_ENABLED' => 'true',

    // Symbols evaluated per signal pass (majors always first). Lower this if
    // your host is slow enough that a cron pass runs out of time.
    'SIGNAL_MAX_SYMBOLS_PER_PASS' => '60',

    'MIN_SIGNAL_SCORE' => '45',
    'SIGNAL_COOLDOWN_SECONDS' => '900',
    'MIN_RISK_REWARD' => '1.2',

    // ---------------------------------------------------------------------
    // Leverage
    // ---------------------------------------------------------------------
    // These coins get the high-leverage tier.
    'LEVERAGE_MAJOR_ASSETS' => 'BTC,ETH',
    'LEVERAGE_MAJOR' => '150',

    // Everything else lands inside this band automatically, by liquidity and
    // volatility: deep + calm books earn the top, thin or wild ones the
    // bottom. Low-cap/meme pairs are additionally capped below the top.
    'LEVERAGE_ALT_MIN' => '20',
    'LEVERAGE_ALT_MAX' => '25',

    // Fraction of the liquidation distance the stop is allowed to use.
    // At 150x liquidation is ~0.67% away, so 0.75 caps the stop at ~0.5% and
    // the targets (1.2R/1.3R) are measured from that. Lower it for more
    // headroom, raise it for wider stops.
    'LEVERAGE_LIQUIDATION_BUFFER' => '0.75',

    // Floor on stop distance as a percent of entry, so a stop can never land
    // inside the spread.
    'MIN_STOP_PERCENT' => '0.12',

    // ---------------------------------------------------------------------
    // Signal cards (the images posted with every signal)
    // ---------------------------------------------------------------------
    'SIGNAL_CARD_ENABLED' => 'true',
    // 2 is the default; 3 is slightly smoother and roughly twice as slow.
    'CARD_RENDER_SCALE' => '2',
    // Wordmark printed in the card footer.
    'CARD_BRAND' => 'AUTO SIGNAL',
    // Fonts. Leave these empty to use the Vazirmatn-*.ttf shipped alongside
    // the PHP files, which is what lets the cards carry Persian labels. Set
    // them to point at any other .ttf you prefer. If neither the configured
    // font nor the bundled one is readable (or FreeType is missing), the
    // cards fall back to the vector font built into card.php and switch
    // their labels to English.
    'CARD_FONT_PATH' => '',
    'CARD_FONT_PATH_BOLD' => '',

    // ---------------------------------------------------------------------
    // Runtime
    // ---------------------------------------------------------------------
    // LIVE actually posts to Telegram; DRY_RUN generates and queues only.
    // Also switchable from the panel (🎯 تنظیمات Signal).
    'RUN_MODE' => 'LIVE',

    // 'cron'   — one bounded pass per invocation, re-run by a cron job every
    //            minute. The only mode plain shared hosting can run.
    // 'daemon' — loops until SIGTERM. Needs a host that allows a persistent
    //            background process.
    'WORKER_MODE' => 'cron',
    'WORKER_MAX_RUNTIME_SECONDS' => '50',
    'WORKER_TICK_SECONDS' => '15',

    'HTTP_TIMEOUT_SECONDS' => '10',
    'MAX_RETRIES' => '5',
    'APP_TIMEZONE' => 'UTC',

    // ---------------------------------------------------------------------
    // Logging — the bot is meant to run silently. 'error' keeps the log
    // table and cron mail empty unless something is actually wrong; set
    // 'info' temporarily when diagnosing.
    // ---------------------------------------------------------------------
    'LOG_LEVEL' => 'error',
    'LOG_RETENTION_DAYS' => '3',

    // Write detected zones/order-blocks/FVGs to their tables. Nothing reads
    // them back; it is a debugging aid and it is the most expensive part of
    // a scan pass on SQLite.
    'PERSIST_STRUCTURE' => 'false',
];

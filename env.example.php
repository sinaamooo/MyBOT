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
    'ENABLED_EXCHANGES' => 'mexc,binance,bybit,okx,kucoin,gate,bitget,htx,cryptocompare',

    // The venue a signal is issued on when several list the same pair. MEXC
    // leads because it lists far more small caps and memecoins than the
    // others; a setup on a coin the reader cannot actually trade is wasted.
    'PRIMARY_EXCHANGE' => 'mexc',

    // Where YOU actually place the trades. Only coins listed on at least
    // one of these are signalled, so a setup never lands on a coin your
    // exchange does not carry. Set to 'off' to disable the filter.
    //
    // It fails OPEN on purpose: if a listing endpoint moves or is blocked,
    // the filter switches itself off rather than silencing the bot. Check
    // 📊 Scanner -> 🏦 صرافی‌های معاملاتی to see whether it actually loaded,
    // and override a URL below if one has changed.
    'TRADABLE_VENUES' => 'toobit,ourbit',
    'TOOBIT_LISTINGS_URL' => '',
    'OURBIT_LISTINGS_URL' => '',
    'VENUE_LISTINGS_TTL' => '21600',

    'BINANCE_API_KEY' => '',
    'BINANCE_API_SECRET' => '',
    'BINANCE_REST_BASE' => 'https://api.binance.com',
    'BINANCE_WS_BASE' => 'wss://stream.binance.com:9443',

    'MEXC_API_KEY' => '',
    'MEXC_API_SECRET' => '',
    'MEXC_REST_BASE' => 'https://api.mexc.com',
    'MEXC_WS_BASE' => 'wss://wbs.mexc.com',

    'BYBIT_REST_BASE' => 'https://api.bybit.com',
    'OKX_REST_BASE' => 'https://www.okx.com',
    'KUCOIN_REST_BASE' => 'https://api.kucoin.com',
    'GATE_REST_BASE' => 'https://api.gateio.ws',
    'BITGET_REST_BASE' => 'https://api.bitget.com',
    'HTX_REST_BASE' => 'https://api.huobi.pro',

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
    'TIMEFRAMES' => '15m,30m,1h,2h',

    // ---------------------------------------------------------------------
    // Automatic mode — the behaviour described in 🤖 اتومات in the panel.
    // ---------------------------------------------------------------------

    // At most one signal every 15 minutes.
    'SIGNAL_INTERVAL_SECONDS' => '900',

    // How many trades may run at once. Set to 1 for strictly one trade at a
    // time (no new signal until the current one closes at TP2/SL). The
    // default of 3 keeps the 15-minute cadence meaningful when a trade runs
    // for hours; a symbol can never have two open trades either way.
    'MAX_OPEN_POSITIONS' => '8',

    // How many of a pass's qualifying candidates may go out at once. This
    // is the throughput lever that costs nothing in quality: each one has
    // already cleared the identical filters and used to be discarded only
    // for not being the single best score of the pass.
    'SIGNALS_PER_PASS' => '3',

    // Timeframes a signal may be issued on.
    'SIGNAL_TIMEFRAMES' => '15m,30m,1h,2h',

    // Targets and the stop as LEVERAGED account percentages, not price
    // moves and not risk multiples: "first target pays 60% of margin,
    // second pays 120%, and the stop never costs more than 30%". At 20x
    // those are price moves of 3% / 6% / 1.5%, and the R:R works out to 2
    // and 4 whenever the stop sits at its cap (better when structure allows
    // a tighter one).
    'TP1_LEVERAGED_PCT' => '60',
    'TP2_LEVERAGED_PCT' => '120',
    'MAX_STOP_LEVERAGED_PCT' => '30',

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
    'LEVERAGE_MAJOR' => '20',

    // Everything else lands inside this band automatically, by liquidity and
    // volatility: deep + calm books earn the top, thin or wild ones the
    // bottom. Low-cap/meme pairs are additionally capped below the top.
    'LEVERAGE_ALT_MIN' => '20',
    'LEVERAGE_ALT_MAX' => '25',

    // Fraction of the liquidation distance the stop is allowed to use — a
    // second ceiling on top of MAX_STOP_LEVERAGED_PCT, whichever is tighter.
    // At 20x liquidation is ~5% away, so 0.75 allows 3.75%; the 30% risk
    // budget caps it at 1.5% first, which is the intended binding limit.
    'LEVERAGE_LIQUIDATION_BUFFER' => '0.75',

    // Floor on stop distance as a percent of entry, so a stop can never land
    // inside the spread.
    'MIN_STOP_PERCENT' => '0.12',

    // ---------------------------------------------------------------------
    // Strategy — the quality gate. Every knob here trades signal COUNT for
    // win rate; loosening them produces more signals and worse ones.
    // ---------------------------------------------------------------------
    // structure_break: a coiled coin taking out a small ceiling (long), or a
    // coin that already ran and then prints a CHoCH down / loses a small
    // floor (short). default_structure is the older, looser strategy.
    'STRATEGY' => 'structure_break',

    // Volume on the breaking candle as a multiple of the previous 20-candle
    // average. A break nobody participated in is a trap.
    'BREAK_VOLUME_RATIO' => '1.3',

    // How far past the broken level price may already be, in ATR, before
    // taking the entry counts as chasing.
    'MAX_CHASE_ATR' => '1.5',

    // How far a coin must have run off its floor before a short counts as
    // fading a pump rather than shorting a base.
    'REVERSAL_RUN_PCT' => '12',

    // How tight the last 40 candles must be, as a percent of price, to call
    // the coin "based" and take its breakout.
    'BASE_RANGE_PCT' => '12',

    // Refuse an entry that has no order block or FVG behind it to put the
    // stop against. This is the single biggest win-rate lever here.
    'REQUIRE_ZONE_CONFLUENCE' => 'true',

    // ---------------------------------------------------------------------
    // Scanner buckets — the universe is built from three baskets rather
    // than one volume ranking, so every pass contains the day's biggest
    // movers in BOTH directions as well as the steady liquid names.
    // ---------------------------------------------------------------------
    'SCANNER_GAINER_SHARE' => '40',
    'SCANNER_LOSER_SHARE' => '25',
    // A coin has to have actually moved this much in 24h to count as a
    // mover; in a flat market the mover baskets simply come up short and
    // the liquid basket fills the rest.
    'SCANNER_MIN_MOVE_PCT' => '4',

    // ---------------------------------------------------------------------
    // Money management — the bot does not place orders, so these turn the
    // stop into the numbers the reader needs to size the trade.
    // ---------------------------------------------------------------------
    // Reference account the suggested position is calculated from.
    'ACCOUNT_BALANCE' => '1000',
    // Percent of it put at risk on one trade. Position size is derived from
    // this and the stop distance, never from the leverage.
    'RISK_PER_TRADE_PCT' => '2',
    // The daily circuit breaker: after this many stop-outs, or this many
    // published signals, the bot goes quiet until tomorrow. 0 = no cap.
    'MAX_DAILY_LOSSES' => '3',
    'MAX_DAILY_SIGNALS' => '8',

    // ---------------------------------------------------------------------
    // Signal cards (the images posted with every signal)
    // ---------------------------------------------------------------------
    'SIGNAL_CARD_ENABLED' => 'true',
    // 2 is the default; 3 is slightly smoother and roughly twice as slow.
    'CARD_RENDER_SCALE' => '2',
    // Wordmark printed in the card footer.
    'CARD_BRAND' => 'AUTO TRADE MARKET',
    // Printed under the trade-result card's footer line, e.g. '@yourchannel'.
    'CARD_HANDLE' => '',
    // Brand logo drawn on the cards. Leave empty and simply drop a PNG named
    // logo.png (transparent background works best) beside the PHP files; set
    // this only to keep it somewhere else. With no logo at all the cards fall
    // back to a drawn mark.
    'CARD_LOGO_PATH' => '',
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

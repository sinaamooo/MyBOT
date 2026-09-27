<?php

return [
    'TELEGRAM_BOT_TOKEN' => 'PUT-YOUR-BOT-TOKEN-HERE',

    'TELEGRAM_WEBHOOK_SECRET' => '',
    'ADMIN_IDS' => '',

    'ENABLED_EXCHANGES' => 'mexc,binance,bybit,okx,kucoin,gate,bitget,htx,cryptocompare',

    'PRIMARY_EXCHANGE' => 'mexc',

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

    'COINALYZE_API_KEY' => '',
    'COINALYZE_REST_BASE' => 'https://api.coinalyze.net/v1',
    'COINALYZE_SYMBOL' => 'BTCUSDT_PERP.A',

    'SCANNER_TOP_N' => '0',
    'SCANNER_INTERVAL_SECONDS' => '900',

    'MIN_VOLUME_USDT' => '5000000',

    'MAX_SPREAD_PERCENT' => '0.3',

    'ALLOWED_QUOTE_ASSETS' => 'USDT',

    'INCLUDE_STABLECOIN_PAIRS' => 'false',

    'TIMEFRAMES' => '15m,30m,1h,2h',

    'SIGNALS_PER_PASS' => '1',

    'SIGNAL_TIMEFRAMES' => '15m,30m,1h,2h',

    'TP1_LEVERAGED_PCT' => '28',
    'TP2_LEVERAGED_PCT' => '50',
    'TP3_LEVERAGED_PCT' => '90',
    'TP4_LEVERAGED_PCT' => '160',
    'MAX_STOP_LEVERAGED_PCT' => '35',

    'RISK_FREE_ENABLED' => 'true',

    'TP1_CLOSE_PERCENT' => '50',

    'ADVISORY_STOP_WARN_PCT' => '70',
    'ADVISORY_STALL_RETRACE_PCT' => '50',

    'SIGNAL_MAX_SYMBOLS_PER_PASS' => '0',
    'ROTATION_BUDGET_SECONDS' => '32',
    'ROTATION_MAX_SYMBOLS' => '400',

    'MIN_SIGNAL_SCORE' => '45',
    'SIGNAL_COOLDOWN_SECONDS' => '900',
    'MIN_RISK_REWARD' => '1.2',

    'SYMBOL_LOSS_COOLDOWN_SECONDS' => '10800',

    'LEVERAGE_MAJOR_ASSETS' => 'BTC,ETH',
    'LEVERAGE_MAJOR' => '15',

    'LEVERAGE_ALT_MIN' => '8',
    'LEVERAGE_ALT_MAX' => '25',

    'LEVERAGE_LIQUIDATION_BUFFER' => '0.75',

    'MIN_STOP_PERCENT' => '0.12',

    'STRATEGY' => 'confluence_pro',

    'MIN_CONFLUENCE_SCORE' => '80',
    'CONFLUENCE_SETUP_WEIGHT' => '18',
    'CONFLUENCE_RANGE_WEIGHT' => '16',
    'CONFLUENCE_STRUCTURE_WEIGHT' => '14',
    'CONFLUENCE_ZONE_WEIGHT' => '14',
    'CONFLUENCE_TREND_WEIGHT' => '12',
    'CONFLUENCE_LIQUIDITY_WEIGHT' => '12',
    'CONFLUENCE_HTF_WEIGHT' => '14',
    'CONFLUENCE_PD_WEIGHT' => '12',
    'CONFLUENCE_OTE_WEIGHT' => '10',
    'CONFLUENCE_INTERNAL_WEIGHT' => '8',

    'CONFLUENCE_STRUCTURE_CAP' => '30',
    'CONFLUENCE_LIQUIDITY_CAP' => '28',
    'CONFLUENCE_LOCATION_CAP' => '26',
    'CONFLUENCE_MOMENTUM_CAP' => '22',
    'CONFLUENCE_VOLUME_CAP' => '14',
    'CONFLUENCE_HTF_CAP' => '22',

    'REQUIRE_HTF_ALIGNMENT' => 'true',
    'REQUIRE_DISCOUNT_PREMIUM' => 'true',

    'HTF_CONFIRM_MAP' => '15m:1h,30m:2h,1h:4h,2h:4h,4h:1d',

    'BIG_MOVE_LOOKBACK' => '20',
    'BIG_MOVE_CONFIRM_BARS' => '3',
    'BIG_MOVE_MIN_WICK' => '0.15',
    'BIG_MOVE_BODY_STRENGTH' => '0.55',

    'ZONE_REACH_ATR' => '2.5',
    'STOP_PAD_ATR' => '0.25',

    'STOP_PAD_ATR_CALM' => '0.15',
    'STOP_PAD_ATR_VOLATILE' => '0.35',
    'VOLATILITY_CALM_RATIO' => '0.85',
    'VOLATILITY_HOT_RATIO' => '1.3',

    'MIN_ROOM_TO_TARGET_R' => '1.5',

    'STRUCTURE_MAX_AGE' => '3',

    'SETUP_VOLUME_MULT' => '1.0',
    'VWAP_AWAY_BARS' => '6',
    'EMA_TOUCH_WINDOW' => '3',
    'SETUP_PIVOT_LENGTH' => '5',
    'RETEST_TOLERANCE_ATR' => '0.3',
    'RETEST_WINDOW' => '20',
    'SWEEP_LOOKBACK' => '20',
    'DIVERGENCE_GAP' => '60',

    'RANGE_ABS_COMPRESSION' => '0.75',
    'RANGE_BREAK_LOOKBACK' => '3',

    'BREAK_VOLUME_RATIO' => '1.3',

    'MAX_CHASE_ATR' => '1.5',

    'REVERSAL_RUN_PCT' => '12',

    'BASE_RANGE_PCT' => '12',

    'REQUIRE_ZONE_CONFLUENCE' => 'true',

    'REQUIRE_REVERSAL_CANDLE' => 'true',
    'REVERSAL_CONFIRM_TIMEFRAME' => '5m',

    'REQUIRE_FRESH_REVERSAL_ZONE' => 'true',

    'REQUIRE_SWEEP_AT_ZONE' => 'true',

    'STRONG_ZONE_VETO_TOUCHES' => '3',

    'REQUIRE_APLUS_SETUP' => 'false',
    'APLUS_MIN_CONFIRMATIONS' => '3',

    'REQUIRE_ADX_FILTER' => 'false',
    'MIN_ADX' => '20',
    'MAX_OPPOSING_VOTE_RATIO' => '0.6',
    'REQUIRE_BREAKOUT_MOMENTUM' => 'true',

    'NEWS_BLACKOUT_START' => '',
    'NEWS_BLACKOUT_END' => '',

    'SCANNER_GAINER_SHARE' => '40',
    'SCANNER_LOSER_SHARE' => '25',

    'SCANNER_MIN_MOVE_PCT' => '4',

    'ACCOUNT_BALANCE' => '1000',

    'RISK_PER_TRADE_PCT' => '2',

    'MAX_DAILY_LOSSES' => '5',
    'MAX_DAILY_SIGNALS' => '10',

    'MAX_OPEN_TRADES' => '3',
    'MIN_SIGNAL_GAP_MINUTES' => '30',
    'MAX_TRADE_HOURS' => '24',

    'SIGNAL_CARD_ENABLED' => 'true',
    'CARD_RENDER_SCALE' => '2',
    'CARD_BRAND' => 'AUTO TRADE MARKET',

    'CARD_LOGO_PATH' => '',

    'CARD_FONT_PATH' => '',
    'CARD_FONT_PATH_BOLD' => '',

    'RUN_MODE' => 'LIVE',

    'WORKER_MODE' => 'cron',
    'WORKER_MAX_RUNTIME_SECONDS' => '50',
    'WORKER_TICK_SECONDS' => '15',

    'HTTP_TIMEOUT_SECONDS' => '10',
    'MAX_RETRIES' => '5',
    'APP_TIMEZONE' => 'UTC',

    'LOG_LEVEL' => 'error',
    'LOG_RETENTION_DAYS' => '3',

    'PERSIST_STRUCTURE' => 'false',
];

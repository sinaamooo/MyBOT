<?php
/**
 * The bot's "brain": weights and parameters of the analysis engine.
 * Positive score = bullish, negative = bearish. Each weight is the maximum
 * number of points that factor can add or subtract.
 *
 * Custom indicators go in brain/indicators/ (see _example.php there).
 */
return [
    'swing_length' => 3,

    'weights' => [
        'htf_trend' => 22,       // trend of the higher timeframe (structure + EMA)
        'main_structure' => 20,  // HH/HL or LH/LL and last BOS/CHoCH on the requested timeframe
        'ema_alignment' => 10,   // close > EMA20 > EMA50 (or the opposite)
        'ema200' => 8,           // price above / below EMA200
        'rsi_momentum' => 8,
        'rsi_divergence' => 12,
        'macd' => 7,
        'volume' => 5,           // volume confirming the last move
        'zone_reaction' => 10,   // price sitting on a support / order block (or resistance)
        'ltf_momentum' => 6,     // lower timeframe agrees
    ],

    // |score| below this = range market (plan is built from the nearest zone)
    'neutral_threshold' => 15,

    // Stop distance limits, in ATR
    'stop_min_atr' => 0.7,
    'stop_max_atr' => 3.0,

    // Minimum reward/risk for the first target
    'min_rr' => 1.0,
];

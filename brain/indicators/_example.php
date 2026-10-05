<?php
/**
 * Template for a custom indicator. Files starting with "_" are ignored;
 * copy this to e.g. brain/indicators/my_indicator.php to enable it.
 *
 * $ctx contains:
 *   'main', 'htf', 'ltf' => App\Market\Series (candles: ->o ->h ->l ->c ->v ->t arrays, oldest first)
 *   'price'  => current price
 *   'atr'    => ATR(14) of the main timeframe
 *   'tf'     => requested timeframe, e.g. '4h'
 *
 * Return null to skip, or:
 *   'score'  => points between -100 and 100 (positive = bullish)
 *   'reason' => Persian sentence shown in the analysis
 */

use App\Analysis\Indicators;

return static function (array $ctx): ?array {
    $closes = $ctx['main']->c;
    $ema9 = Indicators::last(Indicators::ema($closes, 9));
    $ema21 = Indicators::last(Indicators::ema($closes, 21));
    if ($ema9 === null || $ema21 === null) {
        return null;
    }
    return $ema9 > $ema21
        ? ['score' => 5, 'reason' => 'کراس صعودی EMA9 و EMA21']
        : ['score' => -5, 'reason' => 'کراس نزولی EMA9 و EMA21'];
};

<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * Opt-in "Pump Hunter" bonus (see pump_hunter_enabled in Settings): flags a
 * volume surge + breakout + accelerating-momentum setup on the entry (5M)
 * timeframe. This only ever ADDS a bonus on top of the normal weighted
 * score - it never triggers a signal by itself, keeping the rules-based
 * scoring engine as the sole gatekeeper.
 */
final class PumpDetector
{
    /** @return array{detected: bool, reasons: string[]} */
    public static function detect(array $entryIndexed, array $params, bool $bullish): array
    {
        $lookback = max(2, (int) $params['pump_momentum_lookback']);
        $n = count($entryIndexed['close']);
        if ($n < $lookback * 2 + 2) {
            return ['detected' => false, 'reasons' => []];
        }

        $i = $n - 1;
        $close = $entryIndexed['close'];

        $vol = (float) $entryIndexed['volume'][$i];
        $volMa = (float) $entryIndexed['volume_ma'][$i];
        $volRatio = $volMa > 0 ? $vol / $volMa : 0.0;
        $volumeSurge = $volRatio >= (float) $params['pump_volume_multiplier'];

        $pa = PriceAction::detect($entryIndexed);
        $breakout = $bullish ? $pa['breakout'] : $pa['breakdown'];

        $prevAnchor = (float) $close[$i - $lookback];
        $priorAnchor = (float) $close[$i - 2 * $lookback];
        $recentRoc = $prevAnchor != 0.0 ? ((float) $close[$i] - $prevAnchor) / $prevAnchor : 0.0;
        $priorRoc = $priorAnchor != 0.0 ? ($prevAnchor - $priorAnchor) / $priorAnchor : 0.0;
        $accelerating = $bullish
            ? ($recentRoc > 0 && $recentRoc > $priorRoc)
            : ($recentRoc < 0 && $recentRoc < $priorRoc);

        $detected = $volumeSurge && $breakout && $accelerating;
        $reasons = $detected
            ? [sprintf('🚀 Pump: %.1fx volume, breakout, accelerating momentum', $volRatio)]
            : [];

        return ['detected' => $detected, 'reasons' => $reasons];
    }
}

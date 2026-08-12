<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * TRENDING / RANGING / HIGH_VOLATILITY / LOW_VOLATILITY classification, used
 * to soften confidence in ranging markets and raise the bar in volatile ones.
 */
final class MarketRegime
{
    public static function detect(array $indexed, array $params): string
    {
        $i = count($indexed['close']) - 1;
        $price = (float) $indexed['close'][$i];
        $atrPct = $price != 0.0 ? (float) $indexed['atr'][$i] / $price : 0.0;

        if ($atrPct >= (float) $params['high_volatility_atr_pct']) {
            return 'HIGH_VOLATILITY';
        }
        if ($atrPct <= (float) $params['low_volatility_atr_pct']) {
            return 'LOW_VOLATILITY';
        }
        if ((float) $indexed['adx'][$i] >= (float) $params['min_adx']) {
            return 'TRENDING';
        }
        return 'RANGING';
    }

    /**
     * Extension point for a future news/event filter - disabled by default.
     * Always returns false unless `news_filter_enabled` is on AND a real
     * source is wired in (not implemented here).
     */
    public static function newsRiskFlag(array $params): bool
    {
        if (empty($params['news_filter_enabled'])) {
            return false;
        }
        return false;
    }
}

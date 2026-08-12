<?php

declare(strict_types=1);

namespace App\Analysis;

/**
 * ATR-based Stop Loss / Take Profit, Risk:Reward, and a suggested (never
 * auto-executed) leverage + LOW/MEDIUM/HIGH risk score. Leverage changes
 * required margin for a given exposure - it does NOT reduce risk; real
 * risk is governed by stop distance, which is exactly what this computes.
 */
final class Risk
{
    /**
     * @param array{resistances: float[], supports: float[]}|null $srLevels
     * @return array{stop_loss:float, tp1:float, tp2:float, tp3:float, rr:float, leverage:int, risk_score:string, risk_distance:float, atr_percent:float}
     */
    public static function calculate(float $entry, string $side, float $atr, array $params, ?array $srLevels = null): array
    {
        $slDistance = $atr * (float) $params['atr_sl_multiplier'];

        if ($side === 'LONG') {
            $stopLoss = $entry - $slDistance;
            $tp1 = $entry + $slDistance * (float) $params['tp1_r_multiple'];
            $tp2 = $entry + $slDistance * (float) $params['tp2_r_multiple'];
            $tp3 = $entry + $slDistance * (float) $params['tp3_r_multiple'];
            if ($srLevels !== null) {
                $tp1 = SupportResistance::adjustTargetForLevels($tp1, 'LONG', $entry, $srLevels);
                $tp2 = SupportResistance::adjustTargetForLevels($tp2, 'LONG', $entry, $srLevels);
                $tp3 = SupportResistance::adjustTargetForLevels($tp3, 'LONG', $entry, $srLevels);
            }
        } else {
            $stopLoss = $entry + $slDistance;
            $tp1 = $entry - $slDistance * (float) $params['tp1_r_multiple'];
            $tp2 = $entry - $slDistance * (float) $params['tp2_r_multiple'];
            $tp3 = $entry - $slDistance * (float) $params['tp3_r_multiple'];
            if ($srLevels !== null) {
                $tp1 = SupportResistance::adjustTargetForLevels($tp1, 'SHORT', $entry, $srLevels);
                $tp2 = SupportResistance::adjustTargetForLevels($tp2, 'SHORT', $entry, $srLevels);
                $tp3 = SupportResistance::adjustTargetForLevels($tp3, 'SHORT', $entry, $srLevels);
            }
        }

        $riskDistance = abs($entry - $stopLoss);
        $avgReward = (abs($tp1 - $entry) + abs($tp2 - $entry) + abs($tp3 - $entry)) / 3;
        $rr = $riskDistance > 0 ? round($avgReward / $riskDistance, 2) : 0.0;

        $atrPct = $entry != 0.0 ? $atr / $entry : 0.0;
        if ($atrPct <= (float) $params['leverage_low_vol_atr_pct']) {
            $leverage = (int) $params['max_leverage'];
            $riskScore = 'LOW';
        } elseif ($atrPct <= (float) $params['leverage_medium_vol_atr_pct']) {
            $leverage = (int) round(((int) $params['min_leverage'] + (int) $params['max_leverage']) / 2);
            $riskScore = 'MEDIUM';
        } else {
            $leverage = (int) $params['min_leverage'];
            $riskScore = 'HIGH';
        }
        $leverage = max((int) $params['min_leverage'], min((int) $params['max_leverage'], $leverage));

        return [
            'stop_loss' => $stopLoss,
            'tp1' => $tp1,
            'tp2' => $tp2,
            'tp3' => $tp3,
            'rr' => $rr,
            'leverage' => $leverage,
            'risk_score' => $riskScore,
            'risk_distance' => $riskDistance,
            'atr_percent' => round($atrPct * 100, 3),
        ];
    }

    public static function passesMinRr(array $risk, array $params): bool
    {
        return $risk['rr'] >= (float) $params['min_rr'];
    }
}

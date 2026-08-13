<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Config;

/**
 * LONG/SHORT scoring engine - weighted components normalized into a 0-100
 * score per side, so the score always stays comparable to min_score /
 * watchlist_min_score regardless of how the admin panel's weight sliders
 * are tuned. Timeframe roles: 4H = Trend, 1H = Confirmation, 15M = Setup,
 * 5M = Entry.
 */
final class Scoring
{
    /**
     * @param array<string, array<string, array>> $indexedByTf timeframe => Indicators::compute() output
     * @param array<string, mixed> $params
     */
    public static function scoreSymbol(array $indexedByTf, array $params): array
    {
        $entryIdx = $indexedByTf[Config::TIMEFRAME_ENTRY];
        $pa = PriceAction::detect($entryIdx);
        $obZones = !empty($params['indicator_smc_ob_enabled'])
            ? OrderBlocks::detect($indexedByTf[Config::TIMEFRAME_SETUP])
            : ['bullish' => [], 'bearish' => []];
        $fvgZones = !empty($params['indicator_fvg_enabled'])
            ? FairValueGap::detect($indexedByTf[Config::TIMEFRAME_SETUP])
            : ['bullish' => [], 'bearish' => []];
        $liquidityPools = !empty($params['indicator_liquidity_enabled'])
            ? Liquidity::detectPools($entryIdx)
            : ['buyside' => [], 'sellside' => []];
        $structure = !empty($params['indicator_structure_enabled'])
            ? MarketStructure::detect($indexedByTf[Config::TIMEFRAME_SETUP])
            : ['bias' => 'NEUTRAL', 'last_break' => 'NONE', 'last_break_index' => null];

        [$longScore, $longReasons] = self::scoreSide($indexedByTf, $pa, $obZones, $fvgZones, $liquidityPools, $structure, $params, true);
        [$shortScore, $shortReasons] = self::scoreSide($indexedByTf, $pa, $obZones, $fvgZones, $liquidityPools, $structure, $params, false);

        $regime = MarketRegime::detect($indexedByTf[Config::TIMEFRAME_SETUP], $params);
        $trend = Trend::detect($indexedByTf[Config::TIMEFRAME_TREND], $params);

        $displayLong = $longScore;
        $displayShort = $shortScore;
        $effectiveMinScore = (float) $params['min_score'];

        if ($regime === 'RANGING') {
            $displayLong *= (float) $params['ranging_confidence_multiplier'];
            $displayShort *= (float) $params['ranging_confidence_multiplier'];
        } elseif ($regime === 'HIGH_VOLATILITY') {
            $effectiveMinScore = (float) $params['min_score'] + (float) $params['high_volatility_score_buffer'];
        }

        if ($displayLong >= $displayShort) {
            $side = 'LONG';
            $score = $displayLong;
        } else {
            $side = 'SHORT';
            $score = $displayShort;
        }

        $pumpDetected = false;
        if (!empty($params['pump_hunter_enabled'])) {
            $pump = PumpDetector::detect($entryIdx, $params, $side === 'LONG');
            if ($pump['detected']) {
                $pumpDetected = true;
                $score = min(100.0, $score + (float) $params['pump_score_bonus']);
                if ($side === 'LONG') {
                    $longReasons = array_merge($longReasons, $pump['reasons']);
                } else {
                    $shortReasons = array_merge($shortReasons, $pump['reasons']);
                }
            }
        }

        if ($score >= $effectiveMinScore) {
            $decision = 'SIGNAL';
        } elseif ($score >= (float) $params['watchlist_min_score']) {
            $decision = 'WATCHLIST';
        } else {
            $decision = 'NO_SIGNAL';
            $side = null;
        }

        return [
            'decision' => $decision,
            'side' => $side,
            'score' => round($score, 2),
            'raw_long_score' => round($longScore, 2),
            'raw_short_score' => round($shortScore, 2),
            'long_reasons' => $longReasons,
            'short_reasons' => $shortReasons,
            'trend' => $trend,
            'regime' => $regime,
            'price_action' => $pa,
            'pump_detected' => $pumpDetected,
        ];
    }

    private static function clamp(float $value, float $weight): float
    {
        return max(0.0, min($weight, $value));
    }

    /** @return array{0: float, 1: string[]} [score, reasons] */
    private static function scoreSide(
        array $indexedByTf,
        array $pa,
        array $obZones,
        array $fvgZones,
        array $liquidityPools,
        array $structure,
        array $params,
        bool $bullish
    ): array {
        $reasons = [];
        $total = 0.0;

        $trendIdx = $indexedByTf[Config::TIMEFRAME_TREND];
        $setupIdx = $indexedByTf[Config::TIMEFRAME_SETUP];
        $confirmIdx = $indexedByTf[Config::TIMEFRAME_CONFIRM];
        $entryIdx = $indexedByTf[Config::TIMEFRAME_ENTRY];

        $setupI = count($setupIdx['close']) - 1;
        $entryI = count($entryIdx['close']) - 1;

        $wTrend = (float) $params['score_weight_trend'];
        $wEma = (float) $params['score_weight_ema'];
        $wRsi = (float) $params['score_weight_rsi'];
        $wMacd = (float) $params['score_weight_macd'];
        $wAdx = (float) $params['score_weight_adx'];
        $wVolume = (float) $params['score_weight_volume'];
        $wPa = (float) $params['score_weight_price_action'];
        $wHtf = (float) $params['score_weight_htf'];
        $wSmcOb = (float) $params['score_weight_smc_ob'];
        $wFvg = (float) $params['score_weight_fvg'];
        $wLiquidity = (float) $params['score_weight_liquidity'];
        $wStructure = (float) $params['score_weight_structure'];

        // 1) Trend (4H)
        $trend = Trend::detect($trendIdx, $params);
        if (!empty($params['indicator_ema_enabled']) && !empty($params['indicator_adx_enabled'])) {
            if (($bullish && $trend === 'BULLISH') || (!$bullish && $trend === 'BEARISH')) {
                $total += $wTrend;
                $reasons[] = "4H Trend {$trend}";
            }
        }

        // 2) EMA confirmation (15M)
        if (!empty($params['indicator_ema_enabled'])) {
            [$bullPairs, $bearPairs] = Trend::emaAlignment($setupIdx);
            $pairs = $bullish ? $bullPairs : $bearPairs;
            $credit = $wEma * ($pairs / 3);
            if ($credit > 0) {
                $total += $credit;
                $reasons[] = "EMA alignment {$pairs}/3 (15M)";
            }
        }

        // 3) RSI confirmation (15M)
        if (!empty($params['indicator_rsi_enabled'])) {
            $rsiVal = (float) $setupIdx['rsi'][$setupI];
            if ($bullish) {
                if ($rsiVal > 50 && $rsiVal < 75) {
                    $total += $wRsi;
                    $reasons[] = sprintf('RSI bullish (%.1f)', $rsiVal);
                } elseif ($rsiVal >= 45 && $rsiVal <= 50) {
                    $total += $wRsi * 0.4;
                    $reasons[] = sprintf('RSI mildly bullish (%.1f)', $rsiVal);
                }
            } else {
                if ($rsiVal > 25 && $rsiVal < 50) {
                    $total += $wRsi;
                    $reasons[] = sprintf('RSI bearish (%.1f)', $rsiVal);
                } elseif ($rsiVal >= 50 && $rsiVal <= 55) {
                    $total += $wRsi * 0.4;
                    $reasons[] = sprintf('RSI mildly bearish (%.1f)', $rsiVal);
                }
            }
        }

        // 4) MACD (15M)
        if (!empty($params['indicator_macd_enabled'])) {
            $macdVal = (float) $setupIdx['macd'][$setupI];
            $signalVal = (float) $setupIdx['macd_signal'][$setupI];
            $histVal = (float) $setupIdx['macd_hist'][$setupI];
            if ($bullish && $macdVal > $signalVal) {
                $total += $histVal > 0 ? $wMacd : $wMacd * 0.5;
                $reasons[] = 'MACD bullish' . ($histVal > 0 ? ' (histogram+)' : ' (cross)');
            } elseif (!$bullish && $macdVal < $signalVal) {
                $total += $histVal < 0 ? $wMacd : $wMacd * 0.5;
                $reasons[] = 'MACD bearish' . ($histVal < 0 ? ' (histogram-)' : ' (cross)');
            }
        }

        // 5) ADX trend strength (15M)
        if (!empty($params['indicator_adx_enabled'])) {
            $adxVal = (float) $setupIdx['adx'][$setupI];
            $plusDi = (float) $setupIdx['plus_di'][$setupI];
            $minusDi = (float) $setupIdx['minus_di'][$setupI];
            $dominant = $bullish ? ($plusDi > $minusDi) : ($minusDi > $plusDi);
            if ($dominant) {
                if ($adxVal >= (float) $params['min_adx']) {
                    $total += $wAdx;
                    $reasons[] = sprintf('ADX strong (%.1f)', $adxVal);
                } else {
                    $total += $wAdx * min(1.0, $adxVal / (float) $params['min_adx']);
                }
            }
        }

        // 6) Volume confirmation (5M)
        if (!empty($params['indicator_volume_enabled'])) {
            $vol = (float) $entryIdx['volume'][$entryI];
            $volMa = (float) $entryIdx['volume_ma'][$entryI];
            $candleBullish = $entryIdx['close'][$entryI] > $entryIdx['open'][$entryI];
            $directionOk = $bullish ? $candleBullish : !$candleBullish;
            if ($directionOk && $volMa > 0) {
                $ratio = $vol / $volMa;
                if ($ratio >= (float) $params['volume_confirm_multiplier']) {
                    $total += $wVolume;
                    $reasons[] = sprintf('Volume confirmed (%.2fx avg)', $ratio);
                } elseif ($ratio >= 1.0) {
                    $total += $wVolume * 0.5;
                    $reasons[] = sprintf('Volume above average (%.2fx)', $ratio);
                }
            }
        }

        // 7) Price action (5M)
        $hits = $bullish ? PriceAction::bullishHits($pa) : PriceAction::bearishHits($pa);
        if ($hits > 0) {
            $total += min(1.0, $hits / 2) * $wPa;
            $reasons[] = 'Price action: ' . implode(', ', PriceAction::labels($pa));
        }

        // 8) Higher timeframe confirmation (1H)
        $htfTrend = Trend::detect($confirmIdx, $params);
        if (($bullish && $htfTrend === 'BULLISH') || (!$bullish && $htfTrend === 'BEARISH')) {
            $total += $wHtf;
            $reasons[] = "1H HTF confirms {$htfTrend}";
        } else {
            $confirmI = count($confirmIdx['close']) - 1;
            $dominant = $bullish
                ? $confirmIdx['plus_di'][$confirmI] > $confirmIdx['minus_di'][$confirmI]
                : $confirmIdx['minus_di'][$confirmI] > $confirmIdx['plus_di'][$confirmI];
            if ($dominant) {
                $total += $wHtf * 0.4;
            }
        }

        // The 8 core components above are normalized to their own 0-100 scale
        // (unchanged since before the ICT/SMC additions) so min_score/
        // watchlist_min_score keep meaning the same thing regardless of how
        // many extra components exist. ICT/SMC confluence below is added as
        // a bonus on top - confirming signals get a boost, but its absence
        // never dilutes the base score the way folding it into the same
        // denominator would (that silently made 75+ scores far rarer).
        $baseMax = $wTrend + $wEma + $wRsi + $wMacd + $wAdx + $wVolume + $wPa + $wHtf;
        $baseScore = $baseMax > 0 ? (self::clamp($total, $baseMax) / $baseMax) * 100.0 : 0.0;

        $icmBonus = 0.0;

        // 9) ICT/SMC Order Blocks + Breaker Blocks (15M)
        if (!empty($params['indicator_smc_ob_enabled'])) {
            $ob = OrderBlocks::evaluate($obZones, $setupIdx, $bullish);
            if ($ob['hit']) {
                $icmBonus += $wSmcOb;
                $reasons[] = $ob['reason'];
            }
        }

        // 10) ICT/SMC Fair Value Gap retest (15M)
        if (!empty($params['indicator_fvg_enabled'])) {
            $fvg = FairValueGap::evaluate($fvgZones, $setupIdx, $bullish);
            if ($fvg['hit']) {
                $icmBonus += $wFvg;
                $reasons[] = $fvg['reason'];
            }
        }

        // 11) ICT/SMC liquidity sweep (5M)
        if (!empty($params['indicator_liquidity_enabled'])) {
            $sweep = Liquidity::detectSweep($liquidityPools, $entryIdx, $bullish);
            if ($sweep['hit']) {
                $icmBonus += $wLiquidity;
                $reasons[] = $sweep['reason'];
            }
        }

        // 12) ICT/SMC market structure BOS/CHoCH (15M)
        if (!empty($params['indicator_structure_enabled'])) {
            $struct = MarketStructure::evaluate($structure, $setupIdx, $bullish);
            if ($struct['hit']) {
                $icmBonus += $wStructure;
                $reasons[] = $struct['reason'];
            }
        }

        return [min(100.0, $baseScore + $icmBonus), $reasons];
    }
}

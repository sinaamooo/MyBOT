<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Config;

/**
 * LONG/SHORT scoring engine - eight weighted components combined into a
 * 0-100 score per side. Timeframe roles: 4H = Trend, 1H = Confirmation,
 * 15M = Setup, 5M = Entry. Ported 1:1 from the Python scoring.py.
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

        [$longScore, $longReasons] = self::scoreSide($indexedByTf, $pa, $params, true);
        [$shortScore, $shortReasons] = self::scoreSide($indexedByTf, $pa, $params, false);

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
            $reasons = $longReasons;
        } else {
            $side = 'SHORT';
            $score = $displayShort;
            $reasons = $shortReasons;
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
        ];
    }

    private static function clamp(float $value, float $weight): float
    {
        return max(0.0, min($weight, $value));
    }

    /** @return array{0: float, 1: string[]} [score, reasons] */
    private static function scoreSide(array $indexedByTf, array $pa, array $params, bool $bullish): array
    {
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

        $maxScore = $wTrend + $wEma + $wRsi + $wMacd + $wAdx + $wVolume + $wPa + $wHtf;
        return [self::clamp($total, $maxScore), $reasons];
    }
}

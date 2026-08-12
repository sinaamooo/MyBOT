<?php

declare(strict_types=1);

namespace App\Signals;

use App\Analysis\Indicators;
use App\Analysis\Risk;
use App\Analysis\Scoring;
use App\Analysis\SupportResistance;
use App\Analysis\Trend;
use App\Config;
use App\Exchange\MexcClient;
use App\Market\MarketDataService;

/**
 * Per-symbol analysis pipeline: market data -> indicators -> score -> risk ->
 * hard quality gates -> a publishable signal candidate (or nothing).
 * Duplicate-signal/cooldown checks live in Scanner (need DB signal state) -
 * this class is a pure function of market data + settings.
 */
final class SignalGenerator
{
    public function __construct(
        private readonly MarketDataService $market,
        private readonly MexcClient $exchange,
    ) {
    }

    /** Hard AND-gate quality filter: ADX strength, volume confirmation, and HTF agreement must ALL hold. */
    public static function passesHardGates(string $side, array $indexedByTf, array $params): bool
    {
        $setupIdx = $indexedByTf[Config::TIMEFRAME_SETUP];
        $entryIdx = $indexedByTf[Config::TIMEFRAME_ENTRY];
        $setupI = count($setupIdx['close']) - 1;
        $entryI = count($entryIdx['close']) - 1;

        $adxOk = (float) $setupIdx['adx'][$setupI] >= (float) $params['min_adx'];

        $volMa = (float) $entryIdx['volume_ma'][$entryI];
        $volRatio = $volMa > 0 ? (float) $entryIdx['volume'][$entryI] / $volMa : 0.0;
        $candleDirectionOk = $side === 'LONG'
            ? $entryIdx['close'][$entryI] > $entryIdx['open'][$entryI]
            : $entryIdx['close'][$entryI] < $entryIdx['open'][$entryI];
        $volumeOk = $candleDirectionOk && $volRatio >= (float) $params['volume_confirm_multiplier'];

        $htfTrend = Trend::detect($indexedByTf[Config::TIMEFRAME_CONFIRM], $params);
        $htfOk = $htfTrend === ($side === 'LONG' ? 'BULLISH' : 'BEARISH');

        return $adxOk && $volumeOk && $htfOk;
    }

    /** @return array{scoring: ?array, candidate: ?array, snapshot: ?array} */
    public function analyzeSymbol(string $symbol, array $params): array
    {
        $snapshot = $this->market->getSnapshot($symbol, Config::pipelineTimeframes());
        if ($snapshot === null) {
            return ['scoring' => null, 'candidate' => null, 'snapshot' => null];
        }

        $indexedByTf = [];
        foreach ($snapshot['candles'] as $tf => $candles) {
            $indexedByTf[$tf] = Indicators::compute($candles, $params);
        }

        $scoring = Scoring::scoreSymbol($indexedByTf, $params);

        if ($scoring['decision'] !== 'SIGNAL' || $scoring['side'] === null) {
            return ['scoring' => $scoring, 'candidate' => null, 'snapshot' => $snapshot];
        }
        if (!self::passesHardGates($scoring['side'], $indexedByTf, $params)) {
            return ['scoring' => $scoring, 'candidate' => null, 'snapshot' => $snapshot];
        }

        $setupIdx = $indexedByTf[Config::TIMEFRAME_SETUP];
        $atrValue = Indicators::last($setupIdx['atr']);
        $srLevels = SupportResistance::findLevels($setupIdx);
        $risk = Risk::calculate($snapshot['price'], $scoring['side'], $atrValue, $params, $srLevels);

        if (!Risk::passesMinRr($risk, $params)) {
            return ['scoring' => $scoring, 'candidate' => null, 'snapshot' => $snapshot];
        }

        $reasons = $scoring['side'] === 'LONG' ? $scoring['long_reasons'] : $scoring['short_reasons'];
        $candidate = $this->buildCandidate($symbol, $scoring['side'], $snapshot['price'], $risk, $scoring['score'], $reasons, $scoring['trend'], $scoring['regime'], $scoring['pump_detected']);

        return ['scoring' => $scoring, 'candidate' => $candidate, 'snapshot' => $snapshot];
    }

    /** Builds a candidate for an admin-forced Manual LONG/SHORT or Test Signal, bypassing the scoring/quality gates. */
    public function manualCandidate(string $symbol, string $side, array $params): ?array
    {
        $snapshot = $this->market->getSnapshot($symbol, Config::pipelineTimeframes());
        if ($snapshot === null) {
            return null;
        }

        $indexedByTf = [];
        foreach ($snapshot['candles'] as $tf => $candles) {
            $indexedByTf[$tf] = Indicators::compute($candles, $params);
        }
        $scoring = Scoring::scoreSymbol($indexedByTf, $params);

        $setupIdx = $indexedByTf[Config::TIMEFRAME_SETUP];
        $atrValue = Indicators::last($setupIdx['atr']);
        $srLevels = SupportResistance::findLevels($setupIdx);
        $risk = Risk::calculate($snapshot['price'], $side, $atrValue, $params, $srLevels);

        $score = $side === 'LONG' ? $scoring['raw_long_score'] : $scoring['raw_short_score'];
        $reasons = $side === 'LONG' ? $scoring['long_reasons'] : $scoring['short_reasons'];
        $reasons[] = 'Manual override by admin';

        return $this->buildCandidate($symbol, $side, $snapshot['price'], $risk, $score, $reasons, $scoring['trend'], $scoring['regime'], $scoring['pump_detected']);
    }

    /** @return array<string, mixed> */
    private function buildCandidate(string $symbol, string $side, float $price, array $risk, float $score, array $reasons, string $trend, string $regime, bool $pumpDetected = false): array
    {
        $decimals = $this->exchange->getPricePrecision($symbol);
        $round = fn(float $v) => round($v, $decimals);

        return [
            'symbol' => $symbol,
            'side' => $side,
            'entry' => $round($price),
            'stop_loss' => $round($risk['stop_loss']),
            'tp1' => $round($risk['tp1']),
            'tp2' => $round($risk['tp2']),
            'tp3' => $round($risk['tp3']),
            'leverage' => $risk['leverage'],
            'score' => $score,
            'risk_score' => $risk['risk_score'],
            'rr' => $risk['rr'],
            'trend' => $trend,
            'regime' => $regime,
            'timeframe' => strtoupper(Config::TIMEFRAME_ENTRY) . ' / ' . strtoupper(Config::TIMEFRAME_SETUP),
            'reasons' => $reasons,
            'pump_detected' => $pumpDetected,
        ];
    }
}

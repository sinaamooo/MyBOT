<?php

declare(strict_types=1);

namespace App\Signals;

use App\Analysis\Indicators;
use App\Analysis\Risk;
use App\Analysis\Scoring;
use App\Analysis\SupportResistance;
use App\Config;
use App\Exchange\MexcClient;

/**
 * Simplified walk-forward backtester: fetches 5M candles for the requested
 * lookback, resamples them into the 15M/1H/4H roles the live engine uses,
 * then replays the exact same scoring + risk pipeline bar-by-bar (fully
 * causal - only data up to each point in time is used). An approximation
 * for strategy research, not a tick-perfect simulation: TP/SL ordering
 * within a single 5M candle is resolved conservatively (SL before further TPs).
 */
final class Backtester
{
    private const MIN_LOOKBACK = 210; // enough bars for EMA200 etc to be meaningful
    private const BUCKET_SECONDS = ['15m' => 900, '1h' => 3600, '4h' => 14400];
    private const MAX_CANDLES = 1500; // exchange response size guardrail

    public function __construct(private readonly MexcClient $exchange)
    {
    }

    /** @return array<string, mixed> */
    public function run(string $symbol, int $days, array $params): array
    {
        $candlesNeeded = min(self::MAX_CANDLES, $days * 24 * 12 + self::MIN_LOOKBACK);
        $raw5m = $this->exchange->getKlines($symbol, Config::TIMEFRAME_ENTRY, $candlesNeeded);

        $rawByTf = [
            Config::TIMEFRAME_ENTRY => $raw5m,
            Config::TIMEFRAME_SETUP => $this->resample($raw5m, self::BUCKET_SECONDS['15m']),
            Config::TIMEFRAME_CONFIRM => $this->resample($raw5m, self::BUCKET_SECONDS['1h']),
            Config::TIMEFRAME_TREND => $this->resample($raw5m, self::BUCKET_SECONDS['4h']),
        ];

        $indexedByTf = [];
        foreach ($rawByTf as $tf => $candles) {
            $indexedByTf[$tf] = Indicators::compute($candles, $params);
        }

        $setupTs = array_column($rawByTf[Config::TIMEFRAME_SETUP], 'ts');
        $entryTs = array_column($raw5m, 'ts');
        $trades = [];

        for ($i = self::MIN_LOOKBACK, $n = count($setupTs); $i < $n - 1; $i++) {
            $cutoffTs = $setupTs[$i];

            $sliced = [];
            $ok = true;
            foreach ($rawByTf as $tf => $candles) {
                $tsCol = array_column($candles, 'ts');
                $pos = $this->findCutoffIndex($tsCol, $cutoffTs);
                if ($pos < self::MIN_LOOKBACK) {
                    $ok = false;
                    break;
                }
                $sliced[$tf] = $this->sliceIndexed($indexedByTf[$tf], $pos);
            }
            if (!$ok) {
                continue;
            }

            $scoring = Scoring::scoreSymbol($sliced, $params);
            if ($scoring['decision'] !== 'SIGNAL' || $scoring['side'] === null) {
                continue;
            }
            if (!SignalGenerator::passesHardGates($scoring['side'], $sliced, $params)) {
                continue;
            }

            $entryPrice = Indicators::last($sliced[Config::TIMEFRAME_ENTRY]['close']);
            $atrValue = Indicators::last($sliced[Config::TIMEFRAME_SETUP]['atr']);
            $srLevels = SupportResistance::findLevels($sliced[Config::TIMEFRAME_SETUP]);
            $risk = Risk::calculate($entryPrice, $scoring['side'], $atrValue, $params, $srLevels);
            if (!Risk::passesMinRr($risk, $params)) {
                continue;
            }

            $futureStartPos = $this->findCutoffIndex($entryTs, $cutoffTs);
            $future = array_slice($raw5m, $futureStartPos);
            if ($future === []) {
                continue;
            }

            [$result, $exitReason, $profitR] = $this->simulateTrade($future, $scoring['side'], $entryPrice, $risk['stop_loss'], $risk['tp1'], $risk['tp2'], $risk['tp3'], $params);
            if ($result === 'PENDING') {
                continue; // never resolved within available history
            }

            $trades[] = [
                'timestamp' => $cutoffTs,
                'side' => $scoring['side'],
                'entry' => $entryPrice,
                'stop_loss' => $risk['stop_loss'],
                'tp1' => $risk['tp1'],
                'tp2' => $risk['tp2'],
                'tp3' => $risk['tp3'],
                'rr' => $risk['rr'],
                'score' => $scoring['score'],
                'result' => $result,
                'exit_reason' => $exitReason,
                'profit_r' => $profitR,
            ];
        }

        return $this->buildReport($symbol, $days, $trades);
    }

    /** @return array<int, array{ts:int,open:float,high:float,low:float,close:float,volume:float}> */
    private function resample(array $candles, int $bucketSeconds): array
    {
        $buckets = [];
        foreach ($candles as $c) {
            $bucketStart = intdiv((int) $c['ts'], $bucketSeconds) * $bucketSeconds;
            if (!isset($buckets[$bucketStart])) {
                $buckets[$bucketStart] = ['ts' => $bucketStart, 'open' => $c['open'], 'high' => $c['high'], 'low' => $c['low'], 'close' => $c['close'], 'volume' => $c['volume']];
            } else {
                $buckets[$bucketStart]['high'] = max($buckets[$bucketStart]['high'], $c['high']);
                $buckets[$bucketStart]['low'] = min($buckets[$bucketStart]['low'], $c['low']);
                $buckets[$bucketStart]['close'] = $c['close'];
                $buckets[$bucketStart]['volume'] += $c['volume'];
            }
        }
        ksort($buckets);
        return array_values($buckets);
    }

    /** bisect_right: count of elements with ts <= $ts */
    private function findCutoffIndex(array $tsArray, int $ts): int
    {
        $lo = 0;
        $hi = count($tsArray);
        while ($lo < $hi) {
            $mid = intdiv($lo + $hi, 2);
            if ($tsArray[$mid] <= $ts) {
                $lo = $mid + 1;
            } else {
                $hi = $mid;
            }
        }
        return $lo;
    }

    private function sliceIndexed(array $indexed, int $count): array
    {
        $out = [];
        foreach ($indexed as $col => $arr) {
            $out[$col] = array_slice($arr, 0, $count);
        }
        return $out;
    }

    /** @return array{0:string,1:string,2:float} [result, exitReason, profitR] */
    private function simulateTrade(array $future, string $side, float $entry, float $stopLoss, float $tp1, float $tp2, float $tp3, array $params): array
    {
        $isLong = $side === 'LONG';
        $currentSl = $stopLoss;
        $hitTp1 = false;
        $hitTp2 = false;

        foreach ($future as $c) {
            $high = $c['high'];
            $low = $c['low'];
            $slHit = $isLong ? $low <= $currentSl : $high >= $currentSl;
            $tp1Hit = $isLong ? $high >= $tp1 : $low <= $tp1;
            $tp2Hit = $isLong ? $high >= $tp2 : $low <= $tp2;
            $tp3Hit = $isLong ? $high >= $tp3 : $low <= $tp3;

            if ($slHit && !$hitTp1) {
                return ['LOSS', 'SL', -1.0];
            }
            if ($tp1Hit && !$hitTp1) {
                $hitTp1 = true;
                $currentSl = $entry;
            }
            if ($slHit && $hitTp1 && !$hitTp2) {
                return ['BREAKEVEN', 'SL_AFTER_TP1', 0.0];
            }
            if ($tp2Hit && !$hitTp2) {
                $hitTp2 = true;
                $currentSl = $tp1;
            }
            if ($slHit && $hitTp2) {
                return ['WIN', 'SL_AFTER_TP2', (float) $params['tp1_r_multiple']];
            }
            if ($tp3Hit) {
                return ['WIN', 'TP3', (float) $params['tp3_r_multiple']];
            }
        }
        return ['PENDING', 'OPEN_AT_END', 0.0];
    }

    /** @return array<string, mixed> */
    private function buildReport(string $symbol, int $days, array $trades): array
    {
        $total = count($trades);
        if ($total === 0) {
            return [
                'symbol' => $symbol, 'days' => $days, 'total_signals' => 0, 'wins' => 0, 'losses' => 0,
                'win_rate' => 0.0, 'avg_rr' => 0.0, 'avg_score' => 0.0, 'profit_factor' => 0.0,
                'max_drawdown_r' => 0.0, 'trades' => [],
            ];
        }

        $wins = count(array_filter($trades, fn($t) => $t['result'] === 'WIN'));
        $losses = count(array_filter($trades, fn($t) => $t['result'] === 'LOSS'));
        $winRate = round($wins / $total * 100, 2);
        $avgRr = round(array_sum(array_column($trades, 'rr')) / $total, 2);
        $avgScore = round(array_sum(array_column($trades, 'score')) / $total, 2);

        $grossProfit = array_sum(array_map(fn($t) => $t['profit_r'] > 0 ? $t['profit_r'] : 0, $trades));
        $grossLoss = abs(array_sum(array_map(fn($t) => $t['profit_r'] < 0 ? $t['profit_r'] : 0, $trades)));
        $profitFactor = $grossLoss > 0 ? round($grossProfit / $grossLoss, 2) : 0.0;

        $cum = 0.0;
        $peak = 0.0;
        $maxDd = 0.0;
        foreach ($trades as $t) {
            $cum += $t['profit_r'];
            $peak = max($peak, $cum);
            $maxDd = max($maxDd, $peak - $cum);
        }

        return [
            'symbol' => $symbol, 'days' => $days, 'total_signals' => $total, 'wins' => $wins, 'losses' => $losses,
            'win_rate' => $winRate, 'avg_rr' => $avgRr, 'avg_score' => $avgScore, 'profit_factor' => $profitFactor,
            'max_drawdown_r' => round($maxDd, 2), 'trades' => $trades,
        ];
    }
}

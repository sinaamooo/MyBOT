<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Market\Series;

/**
 * Swing points, market structure (HH/HL/LH/LL) and structure breaks (BOS / CHoCH).
 */
final class Structure
{
    /**
     * Pivot highs/lows confirmed by $len candles on each side, compressed so highs and lows alternate.
     * @return array<int, array{type:string, i:int, price:float}>
     */
    public static function swings(Series $s, int $len = 3): array
    {
        $n = $s->count();
        $raw = [];
        for ($i = $len; $i < $n - $len; $i++) {
            $isHigh = true;
            $isLow = true;
            for ($k = 1; $k <= $len; $k++) {
                if ($s->h[$i - $k] > $s->h[$i] || $s->h[$i + $k] >= $s->h[$i]) {
                    $isHigh = false;
                }
                if ($s->l[$i - $k] < $s->l[$i] || $s->l[$i + $k] <= $s->l[$i]) {
                    $isLow = false;
                }
            }
            if ($isHigh) {
                $raw[] = ['type' => 'high', 'i' => $i, 'price' => $s->h[$i]];
            }
            if ($isLow) {
                $raw[] = ['type' => 'low', 'i' => $i, 'price' => $s->l[$i]];
            }
        }

        $out = [];
        foreach ($raw as $p) {
            $last = end($out);
            if ($last !== false && $last['type'] === $p['type']) {
                $better = $p['type'] === 'high' ? $p['price'] > $last['price'] : $p['price'] < $last['price'];
                if ($better) {
                    $out[count($out) - 1] = $p;
                }
                continue;
            }
            $out[] = $p;
        }
        return $out;
    }

    /**
     * Walks the candles and records every break of the last confirmed swing.
     * A break in the trend direction is a BOS, a break against it is a CHoCH.
     *
     * @return array{trend:string, events:array, labels:array}
     */
    public static function analyze(Series $s, array $swings, int $len = 3): array
    {
        $n = $s->count();
        $labels = [];
        $prevHigh = null;
        $prevLow = null;
        foreach ($swings as $sw) {
            if ($sw['type'] === 'high') {
                $labels[] = $sw + ['label' => $prevHigh === null ? 'H' : ($sw['price'] > $prevHigh ? 'HH' : 'LH')];
                $prevHigh = $sw['price'];
            } else {
                $labels[] = $sw + ['label' => $prevLow === null ? 'L' : ($sw['price'] > $prevLow ? 'HL' : 'LL')];
                $prevLow = $sw['price'];
            }
        }

        $events = [];
        $trend = 0;
        $lastHigh = null;
        $lastLow = null;
        $si = 0;
        $swingCount = count($swings);
        for ($i = 0; $i < $n; $i++) {
            // A swing becomes usable once its right-hand confirmation candles have closed.
            while ($si < $swingCount && $swings[$si]['i'] + $len <= $i) {
                $sw = $swings[$si++];
                if ($sw['type'] === 'high') {
                    $lastHigh = $sw + ['broken' => false];
                } else {
                    $lastLow = $sw + ['broken' => false];
                }
            }
            if ($lastHigh !== null && !$lastHigh['broken'] && $s->c[$i] > $lastHigh['price']) {
                $events[] = ['type' => $trend === -1 ? 'CHoCH' : 'BOS', 'dir' => 'up', 'level' => $lastHigh['price'], 'from' => $lastHigh['i'], 'to' => $i];
                $lastHigh['broken'] = true;
                $trend = 1;
            }
            if ($lastLow !== null && !$lastLow['broken'] && $s->c[$i] < $lastLow['price']) {
                $events[] = ['type' => $trend === 1 ? 'CHoCH' : 'BOS', 'dir' => 'down', 'level' => $lastLow['price'], 'from' => $lastLow['i'], 'to' => $i];
                $lastLow['broken'] = true;
                $trend = -1;
            }
        }

        // Combine the last break direction with the latest swing sequence.
        $lastLabels = array_slice($labels, -4);
        $bull = 0;
        $bear = 0;
        foreach ($lastLabels as $lb) {
            if (in_array($lb['label'], ['HH', 'HL'], true)) {
                $bull++;
            } elseif (in_array($lb['label'], ['LH', 'LL'], true)) {
                $bear++;
            }
        }
        $name = 'range';
        if ($trend === 1 && $bull >= $bear) {
            $name = 'bullish';
        } elseif ($trend === -1 && $bear >= $bull) {
            $name = 'bearish';
        } elseif ($bull >= 3) {
            $name = 'bullish';
        } elseif ($bear >= 3) {
            $name = 'bearish';
        }

        return ['trend' => $name, 'break_dir' => $trend, 'events' => $events, 'labels' => $labels];
    }

    /**
     * Regular RSI divergence on the two most recent swing lows/highs.
     * @return array{type:string, a:array, b:array}|null
     */
    public static function rsiDivergence(Series $s, array $swings, array $rsi, int $maxAge = 30): ?array
    {
        $n = $s->count();
        $lows = array_values(array_filter($swings, static fn ($p) => $p['type'] === 'low'));
        $highs = array_values(array_filter($swings, static fn ($p) => $p['type'] === 'high'));
        $check = static function (array $pts, string $kind) use ($rsi, $n, $maxAge): ?array {
            $c = count($pts);
            if ($c < 2) {
                return null;
            }
            [$a, $b] = [$pts[$c - 2], $pts[$c - 1]];
            if ($n - 1 - $b['i'] > $maxAge || $rsi[$a['i']] === null || $rsi[$b['i']] === null) {
                return null;
            }
            if ($kind === 'bullish' && $b['price'] < $a['price'] && $rsi[$b['i']] > $rsi[$a['i']] + 2) {
                return ['type' => 'bullish', 'a' => $a, 'b' => $b];
            }
            if ($kind === 'bearish' && $b['price'] > $a['price'] && $rsi[$b['i']] < $rsi[$a['i']] - 2) {
                return ['type' => 'bearish', 'a' => $a, 'b' => $b];
            }
            return null;
        };
        $bull = $check($lows, 'bullish');
        $bear = $check($highs, 'bearish');
        if ($bull && $bear) {
            return $bull['b']['i'] > $bear['b']['i'] ? $bull : $bear;
        }
        return $bull ?? $bear;
    }
}

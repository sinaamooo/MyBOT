<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Market\Series;

/**
 * Price levels: support/resistance zones, order blocks, fair value gaps and liquidity.
 */
final class Levels
{
    /**
     * Clusters swing points into zones. $extra are higher-timeframe swing prices (weighted more).
     * @return array{support: array, resistance: array}
     */
    public static function zones(Series $s, array $swings, array $extra, float $atr): array
    {
        $price = $s->lastClose();
        $n = $s->count();
        $points = [];
        foreach ($swings as $sw) {
            $points[] = ['price' => $sw['price'], 'i' => $sw['i'], 'htf' => false];
        }
        foreach ($extra as $p) {
            $points[] = ['price' => $p, 'i' => $n - 1, 'htf' => true];
        }
        usort($points, static fn ($a, $b) => $a['price'] <=> $b['price']);

        $tol = 0.55 * $atr;
        $clusters = [];
        foreach ($points as $p) {
            $last = count($clusters) - 1;
            if ($last >= 0 && $p['price'] - $clusters[$last]['mean'] <= $tol) {
                $c = &$clusters[$last];
                $c['pts'][] = $p;
                $c['mean'] = array_sum(array_column($c['pts'], 'price')) / count($c['pts']);
                unset($c);
                continue;
            }
            $clusters[] = ['mean' => $p['price'], 'pts' => [$p]];
        }

        $zones = [];
        foreach ($clusters as $c) {
            $prices = array_column($c['pts'], 'price');
            $htf = count(array_filter($c['pts'], static fn ($p) => $p['htf']));
            $touches = count($c['pts']) - $htf;
            $lastI = max(array_column(array_filter($c['pts'], static fn ($p) => !$p['htf']) ?: [['i' => 0]], 'i'));
            $mid = $c['mean'];
            $lo = max(min($prices), $mid - 0.6 * $atr);
            $hi = min(max($prices), $mid + 0.6 * $atr);
            $lo = min($lo, $mid - 0.18 * $atr);
            $hi = max($hi, $mid + 0.18 * $atr);
            $dist = abs($mid - $price) / $atr;
            if ($dist > 14) {
                continue;
            }
            $zones[] = [
                'mid' => $mid,
                'lo' => $lo,
                'hi' => $hi,
                'touches' => $touches,
                'htf' => $htf > 0,
                'score' => $touches + 2.0 * min($htf, 2) + ($lastI / max($n, 1)) * 1.5,
                'last_i' => $lastI,
            ];
        }

        $support = array_values(array_filter($zones, static fn ($z) => $z['mid'] < $price));
        $resistance = array_values(array_filter($zones, static fn ($z) => $z['mid'] >= $price));
        $pick = static function (array $list, bool $below) use ($price): array {
            usort($list, static fn ($a, $b) => $b['score'] <=> $a['score']);
            $list = array_slice($list, 0, 4);
            usort($list, static fn ($a, $b) => $below ? $b['mid'] <=> $a['mid'] : $a['mid'] <=> $b['mid']);
            return array_slice($list, 0, 3);
        };
        return ['support' => $pick($support, true), 'resistance' => $pick($resistance, false)];
    }

    /**
     * Order blocks: the last opposite candle before the impulsive leg that caused a structure break.
     * Only blocks that were never closed through are kept.
     */
    public static function orderBlocks(Series $s, array $events, array $atrSeries): array
    {
        $n = $s->count();
        $blocks = [];
        foreach ($events as $ev) {
            $from = $ev['from'];
            $to = $ev['to'];
            if ($to - $from < 1) {
                continue;
            }
            $bull = $ev['dir'] === 'up';
            // Origin of the impulsive leg = extreme between the broken swing and the break candle.
            $m = $from;
            for ($i = $from; $i <= $to; $i++) {
                if ($bull ? $s->l[$i] < $s->l[$m] : $s->h[$i] > $s->h[$m]) {
                    $m = $i;
                }
            }
            $idx = null;
            for ($i = $m; $i >= max(0, $m - 6); $i--) {
                $isOpposite = $bull ? $s->c[$i] < $s->o[$i] : $s->c[$i] > $s->o[$i];
                if ($isOpposite) {
                    $idx = $i;
                    break;
                }
            }
            $idx ??= $m;
            $atr = $atrSeries[$idx] ?? $atrSeries[$n - 1];
            $top = $s->h[$idx];
            $bottom = $s->l[$idx];
            if ($atr && $top - $bottom > 1.8 * $atr) {
                // Very large candle: use its body so the zone stays tradable.
                $top = max($s->o[$idx], $s->c[$idx]);
                $bottom = min($s->o[$idx], $s->c[$idx]);
            }

            $valid = true;
            $tested = false;
            for ($j = $to + 1; $j < $n; $j++) {
                if ($bull ? $s->c[$j] < $bottom : $s->c[$j] > $top) {
                    $valid = false;
                    break;
                }
                if ($bull ? $s->l[$j] <= $top : $s->h[$j] >= $bottom) {
                    $tested = true;
                }
            }
            if (!$valid) {
                continue;
            }
            $blocks[] = [
                'dir' => $bull ? 'bull' : 'bear',
                'top' => $top,
                'bottom' => $bottom,
                'i' => $idx,
                'event' => $ev['type'],
                'tested' => $tested,
            ];
        }

        // Drop overlapping blocks, keeping the newest.
        usort($blocks, static fn ($a, $b) => $b['i'] <=> $a['i']);
        $kept = [];
        foreach ($blocks as $b) {
            foreach ($kept as $k) {
                if ($k['dir'] === $b['dir'] && $b['bottom'] <= $k['top'] && $b['top'] >= $k['bottom']) {
                    continue 2;
                }
            }
            $kept[] = $b;
        }

        $price = $s->lastClose();
        $bulls = array_values(array_filter($kept, static fn ($b) => $b['dir'] === 'bull' && $b['bottom'] < $price));
        $bears = array_values(array_filter($kept, static fn ($b) => $b['dir'] === 'bear' && $b['top'] > $price));
        usort($bulls, static fn ($a, $b) => $b['top'] <=> $a['top']);
        usort($bears, static fn ($a, $b) => $a['bottom'] <=> $b['bottom']);
        return array_merge(array_slice($bulls, 0, 2), array_slice($bears, 0, 2));
    }

    /** Unfilled fair value gaps (three-candle imbalances) closest to price. */
    public static function fairValueGaps(Series $s, float $atr, int $lookback = 120): array
    {
        $n = $s->count();
        $gaps = [];
        for ($i = max(2, $n - $lookback); $i < $n; $i++) {
            if ($s->l[$i] > $s->h[$i - 2] && $s->l[$i] - $s->h[$i - 2] > 0.25 * $atr) {
                $gaps[] = ['dir' => 'bull', 'top' => $s->l[$i], 'bottom' => $s->h[$i - 2], 'i' => $i - 1];
            } elseif ($s->h[$i] < $s->l[$i - 2] && $s->l[$i - 2] - $s->h[$i] > 0.25 * $atr) {
                $gaps[] = ['dir' => 'bear', 'top' => $s->l[$i - 2], 'bottom' => $s->h[$i], 'i' => $i - 1];
            }
        }
        $open = [];
        foreach ($gaps as $g) {
            $filled = false;
            for ($j = $g['i'] + 2; $j < $n; $j++) {
                if ($g['dir'] === 'bull' ? $s->l[$j] <= $g['bottom'] : $s->h[$j] >= $g['top']) {
                    $filled = true;
                    break;
                }
            }
            if (!$filled) {
                $open[] = $g;
            }
        }
        $price = $s->lastClose();
        usort($open, static fn ($a, $b) => abs(($a['top'] + $a['bottom']) / 2 - $price) <=> abs(($b['top'] + $b['bottom']) / 2 - $price));
        return array_slice($open, 0, 2);
    }

    /**
     * Resting liquidity: equal highs/lows and untouched range extremes.
     * 'buy' side sits above price (stops of shorts), 'sell' side below.
     */
    public static function liquidity(Series $s, array $swings, float $atr, int $lookback = 160): array
    {
        $n = $s->count();
        $price = $s->lastClose();
        $recent = array_values(array_filter($swings, static fn ($p) => $p['i'] >= $n - $lookback));
        $swept = static function (array $p) use ($s, $n): bool {
            for ($j = $p['i'] + 1; $j < $n; $j++) {
                if ($p['type'] === 'high' ? $s->h[$j] > $p['price'] : $s->l[$j] < $p['price']) {
                    return true;
                }
            }
            return false;
        };

        $out = [];
        foreach (['high', 'low'] as $type) {
            $pts = array_values(array_filter($recent, static fn ($p) => $p['type'] === $type && !$swept($p)));
            $c = count($pts);
            for ($a = 0; $a < $c; $a++) {
                for ($b = $a + 1; $b < $c; $b++) {
                    if (abs($pts[$a]['price'] - $pts[$b]['price']) <= 0.2 * $atr) {
                        $lvl = $type === 'high' ? max($pts[$a]['price'], $pts[$b]['price']) : min($pts[$a]['price'], $pts[$b]['price']);
                        $out[] = ['side' => $type === 'high' ? 'buy' : 'sell', 'price' => $lvl, 'kind' => $type === 'high' ? 'EQH' : 'EQL', 'i' => $pts[$a]['i'], 'i2' => $pts[$b]['i']];
                    }
                }
            }
            if ($pts) {
                usort($pts, static fn ($x, $y) => $type === 'high' ? $y['price'] <=> $x['price'] : $x['price'] <=> $y['price']);
                $ext = $pts[0];
                $out[] = ['side' => $type === 'high' ? 'buy' : 'sell', 'price' => $ext['price'], 'kind' => 'swing', 'i' => $ext['i'], 'i2' => $ext['i']];
            }
        }

        // Dedupe nearby levels
        usort($out, static fn ($a, $b) => ($a['kind'] === 'swing') <=> ($b['kind'] === 'swing'));
        $kept = [];
        foreach ($out as $l) {
            if (($l['side'] === 'buy' && $l['price'] <= $price) || ($l['side'] === 'sell' && $l['price'] >= $price)) {
                continue;
            }
            foreach ($kept as $k) {
                if (abs($k['price'] - $l['price']) < 0.4 * $atr) {
                    continue 2;
                }
            }
            $kept[] = $l;
        }
        usort($kept, static fn ($a, $b) => abs($a['price'] - $price) <=> abs($b['price'] - $price));
        $buy = array_values(array_filter($kept, static fn ($l) => $l['side'] === 'buy'));
        $sell = array_values(array_filter($kept, static fn ($l) => $l['side'] === 'sell'));
        return array_merge(array_slice($buy, 0, 2), array_slice($sell, 0, 2));
    }

    /**
     * Largest order-book walls within ±6% of price, bucketed in 0.25% bins.
     */
    public static function walls(?array $book, float $price): array
    {
        if (!$book) {
            return [];
        }
        $bin = $price * 0.0025;
        $out = [];
        foreach (['bids' => 'bid', 'asks' => 'ask'] as $key => $side) {
            $bins = [];
            foreach ($book[$key] as [$p, $q]) {
                if (abs($p - $price) > $price * 0.06) {
                    continue;
                }
                $k = (int) floor($p / $bin);
                $bins[$k] = ($bins[$k] ?? 0) + $p * $q;
            }
            if (count($bins) < 3) {
                continue;
            }
            $vals = array_values($bins);
            sort($vals);
            $median = $vals[intdiv(count($vals), 2)];
            arsort($bins);
            foreach (array_slice($bins, 0, 2, true) as $k => $notional) {
                if ($notional >= 2.5 * $median) {
                    $out[] = ['side' => $side, 'price' => ($k + 0.5) * $bin, 'notional' => $notional];
                }
            }
        }
        return $out;
    }
}

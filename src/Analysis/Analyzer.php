<?php

declare(strict_types=1);

namespace App\Analysis;

use App\Market\MarketData;
use App\Market\Series;
use App\Support\Fa;

/**
 * The analysis engine: pulls candles on three timeframes, finds structure, zones,
 * order blocks and liquidity, scores the setup and builds a trade plan.
 */
final class Analyzer
{
    private const HIGHER = ['15m' => '1h', '30m' => '4h', '1h' => '4h', '2h' => '1d', '4h' => '1d', '6h' => '1d', '12h' => '1w', '1d' => '1w', '1w' => '1w'];
    private const LOWER = ['15m' => '5m', '30m' => '5m', '1h' => '15m', '2h' => '30m', '4h' => '1h', '6h' => '1h', '12h' => '2h', '1d' => '4h', '1w' => '1d'];

    public function __construct(private MarketData $market, private array $brain, private string $pluginDir = '')
    {
    }

    public function run(string $base, string $tf): array
    {
        $tf = isset(self::HIGHER[$tf]) ? $tf : '4h';
        $main = $this->market->candles($base, $tf, 300);
        $source = $this->market->lastProvider;
        $htf = $this->market->candles($base, self::HIGHER[$tf], 220);
        $ltf = $this->market->candles($base, self::LOWER[$tf], 200);
        $hourly = self::LOWER[$tf] === '1h' ? $ltf : $this->market->candles($base, '1h', 30);

        $len = (int) ($this->brain['swing_length'] ?? 3);
        $w = $this->brain['weights'] ?? [];
        $price = $main->lastClose();
        $n = $main->count();

        $ema20 = Indicators::ema($main->c, 20);
        $ema50 = Indicators::ema($main->c, 50);
        $ema200 = Indicators::ema($main->c, 200);
        $rsi = Indicators::rsi($main->c, 14);
        $atrSeries = Indicators::atr($main->h, $main->l, $main->c, 14);
        $atr = (float) Indicators::last($atrSeries);
        $macd = Indicators::macd($main->c);
        $volSma = Indicators::sma($main->v, 20);

        $swings = Structure::swings($main, $len);
        $structure = Structure::analyze($main, $swings, $len);
        $htfSwings = Structure::swings($htf, 2);
        $htfStructure = Structure::analyze($htf, $htfSwings, 2);
        $ltfStructure = Structure::analyze($ltf, Structure::swings($ltf, $len), $len);

        $htfPrices = array_column(array_slice($htfSwings, -14), 'price');
        $zones = Levels::zones($main, array_values(array_filter($swings, static fn ($s) => $s['i'] >= $n - 220)), $htfPrices, $atr);
        $orderBlocks = Levels::orderBlocks($main, $structure['events'], $atrSeries);
        $fvgs = Levels::fairValueGaps($main, $atr);
        $liquidity = Levels::liquidity($main, $swings, $atr);
        $walls = Levels::walls($this->market->orderBook($base), $price);
        $divergence = Structure::rsiDivergence($main, $swings, $rsi);

        // ---------------------------------------------------------------- scoring
        $score = 0.0;
        $reasons = [];
        $add = static function (float $points, string $text) use (&$score, &$reasons): void {
            if (abs($points) < 0.01) {
                return;
            }
            $score += $points;
            $reasons[] = ['w' => $points, 'fa' => $text];
        };

        $htfEma50 = Indicators::last(Indicators::ema($htf->c, 50));
        $htfEma200 = Indicators::last(Indicators::ema($htf->c, 200)) ?? Indicators::last(Indicators::ema($htf->c, 100));
        $htfName = Fa::tf(self::HIGHER[$tf]);
        $htfTrend = $htfStructure['trend'];
        $htfPts = ($w['htf_trend'] ?? 22);
        $htfDir = ['bullish' => 1, 'bearish' => -1, 'range' => 0][$htfTrend];
        $htfEmaDir = $htfEma50 !== null ? ($htf->lastClose() > $htfEma50 ? 1 : -1) : 0;
        if ($htfDir !== 0) {
            $add($htfDir * $htfPts * 0.6, "روند تایم‌فریم {$htfName} " . Fa::trend($htfTrend) . ' است');
        }
        if ($htfEmaDir !== 0) {
            $add($htfEmaDir * $htfPts * 0.4, 'قیمت ' . ($htfEmaDir > 0 ? 'بالای' : 'زیر') . " EMA50 تایم {$htfName}");
        }

        $tfName = Fa::tf($tf);
        $lastEvent = end($structure['events']) ?: null;
        $mainDir = ['bullish' => 1, 'bearish' => -1, 'range' => 0][$structure['trend']];
        $msPts = $w['main_structure'] ?? 20;
        if ($mainDir !== 0) {
            $txt = $mainDir > 0 ? 'سقف و کف‌های بالاتر' : 'سقف و کف‌های پایین‌تر';
            $add($mainDir * $msPts * 0.6, "ساختار {$tfName} " . Fa::trend($structure['trend']) . " ({$txt})");
        }
        if ($lastEvent && $n - 1 - $lastEvent['to'] <= 40) {
            $d = $lastEvent['dir'] === 'up' ? 1 : -1;
            $kind = $lastEvent['type'] === 'CHoCH' ? 'تغییر ساختار (CHoCH)' : 'شکست ساختار (BOS)';
            $add($d * $msPts * 0.4, $kind . ' ' . ($d > 0 ? 'صعودی' : 'نزولی') . ' در ' . Fa::price($lastEvent['level']));
        }

        $e20 = Indicators::last($ema20);
        $e50 = Indicators::last($ema50);
        $e200 = Indicators::last($ema200);
        if ($e20 !== null && $e50 !== null) {
            if ($price > $e20 && $e20 > $e50) {
                $add($w['ema_alignment'] ?? 10, 'چیدمان صعودی میانگین‌ها (قیمت > EMA20 > EMA50)');
            } elseif ($price < $e20 && $e20 < $e50) {
                $add(-($w['ema_alignment'] ?? 10), 'چیدمان نزولی میانگین‌ها (قیمت < EMA20 < EMA50)');
            }
        }
        if ($e200 !== null) {
            $add(($price > $e200 ? 1 : -1) * ($w['ema200'] ?? 8), 'قیمت ' . ($price > $e200 ? 'بالای' : 'زیر') . ' EMA200');
        }

        $r = (float) Indicators::last($rsi);
        $rsiPts = $w['rsi_momentum'] ?? 8;
        if ($r >= 70) {
            $add(-$rsiPts * 0.5, 'RSI در ناحیه اشباع خرید (' . round($r) . ')');
        } elseif ($r <= 30) {
            $add($rsiPts * 0.5, 'RSI در ناحیه اشباع فروش (' . round($r) . ')');
        } elseif ($r >= 55) {
            $add($rsiPts, 'مومنتوم مثبت RSI (' . round($r) . ')');
        } elseif ($r <= 45) {
            $add(-$rsiPts, 'مومنتوم منفی RSI (' . round($r) . ')');
        }
        if ($divergence) {
            $d = $divergence['type'] === 'bullish' ? 1 : -1;
            $add($d * ($w['rsi_divergence'] ?? 12), 'واگرایی ' . ($d > 0 ? 'مثبت' : 'منفی') . ' RSI');
        }

        $hist = $macd['hist'];
        $h0 = Indicators::last($hist);
        $h1 = Indicators::last($hist, 1);
        if ($h0 !== null && $h1 !== null) {
            $macdPts = $w['macd'] ?? 7;
            if ($h0 > 0 && $h0 >= $h1) {
                $add($macdPts, 'هیستوگرام MACD مثبت و صعودی');
            } elseif ($h0 < 0 && $h0 <= $h1) {
                $add(-$macdPts, 'هیستوگرام MACD منفی و نزولی');
            } elseif ($h0 > 0) {
                $add($macdPts * 0.3, 'MACD بالای خط صفر');
            } else {
                $add(-$macdPts * 0.3, 'MACD زیر خط صفر');
            }
        }

        $lastVol = array_sum(array_slice($main->v, -3)) / 3;
        $avgVol = (float) Indicators::last($volSma);
        if ($avgVol > 0 && $lastVol > 1.3 * $avgVol) {
            $dir = $main->c[$n - 1] >= $main->c[$n - 4] ? 1 : -1;
            $add($dir * ($w['volume'] ?? 5), 'افزایش حجم معاملات همراه با حرکت ' . ($dir > 0 ? 'صعودی' : 'نزولی'));
        }

        $zonePts = $w['zone_reaction'] ?? 10;
        foreach ($orderBlocks as $ob) {
            $near = $ob['dir'] === 'bull'
                ? $price >= $ob['bottom'] - 0.3 * $atr && $price <= $ob['top'] + 0.6 * $atr
                : $price <= $ob['top'] + 0.3 * $atr && $price >= $ob['bottom'] - 0.6 * $atr;
            if ($near) {
                $add(($ob['dir'] === 'bull' ? 1 : -1) * $zonePts, 'قیمت روی اوردر بلاک ' . ($ob['dir'] === 'bull' ? 'صعودی' : 'نزولی') . ' قرار دارد');
                break;
            }
        }
        $sup = $zones['support'][0] ?? null;
        $res = $zones['resistance'][0] ?? null;
        if ($sup && $price - $sup['hi'] < 0.5 * $atr) {
            $add($zonePts * 0.6, 'واکنش به حمایت ' . Fa::price($sup['mid']));
        } elseif ($res && $res['lo'] - $price < 0.5 * $atr) {
            $add(-$zonePts * 0.6, 'برخورد به مقاومت ' . Fa::price($res['mid']));
        }

        $ltfDir = ['bullish' => 1, 'bearish' => -1, 'range' => 0][$ltfStructure['trend']];
        if ($ltfDir !== 0) {
            $add($ltfDir * ($w['ltf_momentum'] ?? 6), 'تایم پایین‌تر (' . Fa::tf(self::LOWER[$tf]) . ') ' . Fa::trend($ltfStructure['trend']) . ' است');
        }

        foreach ($this->plugins() as $name => $plugin) {
            try {
                $res2 = $plugin(['main' => $main, 'htf' => $htf, 'ltf' => $ltf, 'price' => $price, 'atr' => $atr, 'tf' => $tf]);
            } catch (\Throwable $e) {
                app_log("plugin $name failed: " . $e->getMessage());
                continue;
            }
            if (is_array($res2) && isset($res2['score'])) {
                $add(max(-100, min(100, (float) $res2['score'])), (string) ($res2['reason'] ?? $name));
            }
        }

        $score = max(-100.0, min(100.0, $score));
        $neutral = abs($score) < ($this->brain['neutral_threshold'] ?? 15);
        if ($neutral) {
            $distSup = $sup ? $price - $sup['mid'] : INF;
            $distRes = $res ? $res['mid'] - $price : INF;
            $side = $distSup <= $distRes ? 'long' : 'short';
        } else {
            $side = $score > 0 ? 'long' : 'short';
        }

        $plan = $this->plan($side, $main, $price, $atr, $zones, $orderBlocks, $liquidity, $fvgs, $swings);
        $confidence = (int) round(min(92, 38 + abs($score) * 0.62 + min(8, max(0, ($plan['rr'][1] ?? 1) - 1.5) * 3)));
        if ($neutral) {
            $confidence = min($confidence, 52);
        }

        // Keep the reasons that support the chosen side first.
        $sign = $side === 'long' ? 1 : -1;
        usort($reasons, static fn ($a, $b) => ($b['w'] * $sign) <=> ($a['w'] * $sign));

        // 24h statistics from hourly candles
        $last24 = $hourly->tail(24);
        $open24 = $last24->o[0];
        $quoteVolume = 0.0;
        foreach ($last24->v as $i => $v) {
            $quoteVolume += $v * $last24->c[$i];
        }

        return [
            'base' => strtoupper($base),
            'quote' => $this->market->quote(),
            'symbol' => $main->symbol,
            'timeframe' => $tf,
            'htf' => self::HIGHER[$tf],
            'ltf' => self::LOWER[$tf],
            'source' => $source,
            'time' => time(),
            'price' => $price,
            'change24h' => $open24 > 0 ? ($price - $open24) / $open24 * 100 : 0.0,
            'high24h' => max($last24->h),
            'low24h' => min($last24->l),
            'volume24h' => $quoteVolume,
            'atr' => $atr,
            'rsi' => $r,
            'macd_hist' => $h0,
            'ema' => ['20' => $e20, '50' => $e50, '200' => $e200],
            'trend' => [self::HIGHER[$tf] => $htfTrend, $tf => $structure['trend'], self::LOWER[$tf] => $ltfStructure['trend']],
            'last_event' => $lastEvent,
            'events' => array_slice($structure['events'], -6),
            'labels' => array_slice($structure['labels'], -12),
            'zones' => $zones,
            'order_blocks' => $orderBlocks,
            'fvg' => $fvgs,
            'liquidity' => $liquidity,
            'walls' => $walls,
            'divergence' => $divergence,
            'score' => (int) round($score),
            'bias' => $neutral ? 'neutral' : $side,
            'confidence' => $confidence,
            'plan' => $plan,
            'reasons' => $reasons,
            'series' => $main,
            'htf_series' => $htf,
            'ema_series' => ['20' => $ema20, '50' => $ema50, '200' => $ema200],
            'rsi_series' => $rsi,
        ];
    }

    private function plan(string $side, Series $s, float $price, float $atr, array $zones, array $obs, array $liq, array $fvgs, array $swings): array
    {
        $long = $side === 'long';
        $sign = $long ? 1 : -1;

        // Entry: nearest order block / zone on our side, within 3.5 ATR.
        $candidates = [];
        foreach ($obs as $ob) {
            if ($ob['dir'] === ($long ? 'bull' : 'bear')) {
                $candidates[] = ['lo' => $ob['bottom'], 'hi' => $ob['top'], 'kind' => 'ob', 'prio' => 1];
            }
        }
        foreach ($zones[$long ? 'support' : 'resistance'] as $z) {
            $candidates[] = ['lo' => $z['lo'], 'hi' => $z['hi'], 'kind' => 'zone', 'prio' => 2];
        }
        foreach ($fvgs as $g) {
            if ($g['dir'] === ($long ? 'bull' : 'bear')) {
                $candidates[] = ['lo' => $g['bottom'], 'hi' => $g['top'], 'kind' => 'fvg', 'prio' => 3];
            }
        }
        $entry = null;
        $best = INF;
        foreach ($candidates as $c) {
            $edge = $long ? $c['hi'] : $c['lo'];
            $dist = ($price - $edge) * $sign; // >0 = zone is in the pullback direction
            if ($dist < -0.3 * $atr || $dist > 3.5 * $atr) {
                continue;
            }
            $rank = max($dist, 0) / $atr + $c['prio'] * 0.35;
            if ($rank < $best) {
                $best = $rank;
                $entry = $c;
            }
        }
        if ($entry === null || ($long ? $price < $entry['hi'] : $price > $entry['lo'])) {
            // Already inside the zone (or nothing close): enter around the current price.
            $lo = $long ? $price - 0.35 * $atr : $price - 0.1 * $atr;
            $hi = $long ? $price + 0.1 * $atr : $price + 0.35 * $atr;
            $entry = ['lo' => $lo, 'hi' => $hi, 'kind' => $entry['kind'] ?? 'market'];
        }
        $zoneLo = $entry['lo'];
        $zoneHi = $entry['hi'];
        if ($entry['hi'] - $entry['lo'] > 1.2 * $atr) {
            // Trim wide zones to the half closest to price.
            $mid = ($entry['lo'] + $entry['hi']) / 2;
            $long ? $entry['lo'] = $mid : $entry['hi'] = $mid;
        }
        $entryMid = ($entry['lo'] + $entry['hi']) / 2;

        // Stop: beyond the zone and the closest protective swing.
        $stop = $long ? $zoneLo - 0.3 * $atr : $zoneHi + 0.3 * $atr;
        foreach (array_reverse($swings) as $sw) {
            if ($long && $sw['type'] === 'low' && $sw['price'] < $entry['lo'] && $entry['lo'] - $sw['price'] < 1.2 * $atr) {
                $stop = min($stop, $sw['price'] - 0.2 * $atr);
                break;
            }
            if (!$long && $sw['type'] === 'high' && $sw['price'] > $entry['hi'] && $sw['price'] - $entry['hi'] < 1.2 * $atr) {
                $stop = max($stop, $sw['price'] + 0.2 * $atr);
                break;
            }
        }
        $risk = abs($entryMid - $stop);
        $minRisk = ($this->brain['stop_min_atr'] ?? 0.7) * $atr;
        $maxRisk = ($this->brain['stop_max_atr'] ?? 3.0) * $atr;
        $risk = max($minRisk, min($maxRisk, $risk));
        $stop = $entryMid - $sign * $risk;

        // Targets: opposing zones, order blocks, liquidity and gaps beyond 1R.
        $levels = [];
        foreach ($zones[$long ? 'resistance' : 'support'] as $z) {
            $levels[] = $long ? $z['lo'] : $z['hi'];
        }
        foreach ($obs as $ob) {
            if ($ob['dir'] === ($long ? 'bear' : 'bull')) {
                $levels[] = $long ? $ob['bottom'] : $ob['top'];
            }
        }
        foreach ($liq as $l) {
            if ($l['side'] === ($long ? 'buy' : 'sell')) {
                $levels[] = $l['price'];
            }
        }
        foreach ($fvgs as $g) {
            if ($g['dir'] === ($long ? 'bear' : 'bull')) {
                $levels[] = $long ? $g['bottom'] : $g['top'];
            }
        }
        $minRR = $this->brain['min_rr'] ?? 1.0;
        $levels = array_values(array_filter($levels, static fn ($p) => ($p - $entryMid) * $sign >= $minRR * $risk));
        usort($levels, static fn ($a, $b) => ($a <=> $b) * $sign);
        $targets = [];
        foreach ($levels as $p) {
            if ($targets && abs($p - end($targets)) < 0.6 * $atr) {
                continue;
            }
            $targets[] = $p;
            if (count($targets) === 3) {
                break;
            }
        }
        $mult = [1.5, 2.5, 3.5];
        while (count($targets) < 3) {
            $k = count($targets);
            $candidate = $entryMid + $sign * $risk * $mult[$k];
            if ($targets && ($candidate - end($targets)) * $sign < 0.8 * $risk) {
                $candidate = end($targets) + $sign * $risk;
            }
            $targets[] = $candidate;
        }

        return self::finalizePlan($side, $entry['lo'], $entry['hi'], $stop, $targets, $price, $atr) + ['entry_kind' => $entry['kind']];
    }

    /** Builds the plan array (R multiples, risk %, projected path) from raw levels. */
    public static function finalizePlan(string $side, float $entryLo, float $entryHi, float $stop, array $targets, float $price, float $atr): array
    {
        $entryMid = ($entryLo + $entryHi) / 2;
        $risk = max(abs($entryMid - $stop), 1e-12);
        return [
            'side' => $side,
            'entry' => [min($entryLo, $entryHi), max($entryLo, $entryHi)],
            'entry_mid' => $entryMid,
            'stop' => $stop,
            'targets' => array_values($targets),
            'rr' => array_map(static fn ($t) => round(abs($t - $entryMid) / $risk, 2), array_values($targets)),
            'risk_pct' => $risk / $entryMid * 100,
            'path' => self::path($side === 'long', $price, $entryMid, array_values($targets), $atr),
        ];
    }

    /** Projected price path as [bars ahead, price] points for the chart. */
    private static function path(bool $long, float $price, float $entry, array $targets, float $atr): array
    {
        $sign = $long ? 1 : -1;
        $bars = static fn (float $a, float $b) => max(3, abs($a - $b) / $atr * 1.6);
        $pts = [[0, $price]];
        $x = 0.0;
        if (abs($price - $entry) > 0.4 * $atr) {
            $x += $bars($price, $entry);
            $pts[] = [$x, $entry];
            $from = $entry;
        } else {
            $dip = $price - $sign * 0.6 * $atr;
            $x += 3;
            $pts[] = [$x, $dip];
            $from = $dip;
        }
        $x += $bars($from, $targets[0]);
        $pts[] = [$x, $targets[0]];
        $pull = $targets[0] - $sign * abs($targets[0] - $from) * 0.38;
        $x += $bars($targets[0], $pull) * 0.8;
        $pts[] = [$x, $pull];
        $x += $bars($pull, $targets[1]);
        $pts[] = [$x, $targets[1]];
        return $pts;
    }

    /** @return array<string, callable> */
    private function plugins(): array
    {
        if ($this->pluginDir === '' || !is_dir($this->pluginDir)) {
            return [];
        }
        $out = [];
        foreach (glob($this->pluginDir . '/*.php') ?: [] as $file) {
            if (str_starts_with(basename($file), '_')) {
                continue;
            }
            $fn = require $file;
            if (is_callable($fn)) {
                $out[basename($file, '.php')] = $fn;
            }
        }
        return $out;
    }
}

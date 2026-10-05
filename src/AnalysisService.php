<?php

declare(strict_types=1);

namespace App;

use App\Ai\Gemini;
use App\Analysis\Analyzer;
use App\Market\MarketData;
use App\Market\News;
use App\Render\Caption;
use App\Render\Card;

/**
 * Full pipeline for one request:
 * engine analysis -> news -> Gemini review (sees numbers + chart) -> validation -> final chart + caption.
 */
final class AnalysisService
{
    private MarketData $market;
    private Analyzer $analyzer;
    private News $news;
    private Gemini $gemini;
    private Card $card;
    private string $outDir;

    public function __construct(private array $config, private string $brainNotes = '')
    {
        $storage = APP_ROOT . '/storage';
        $this->market = new MarketData($config['market'] ?? [], $storage . '/cache');
        $brain = require APP_ROOT . '/brain/strategy.php';
        $this->analyzer = new Analyzer($this->market, $brain, APP_ROOT . '/brain/indicators');
        $this->news = new News($config['news'] ?? [], $storage . '/cache');
        $this->gemini = new Gemini((string) ($config['gemini']['api_key'] ?? ''), $config['gemini']['model'] ?? 'gemini-3.5-flash');
        $this->card = new Card($config['brand'] ?? []);
        $this->outDir = $storage . '/out';
        if (!is_dir($this->outDir)) {
            @mkdir($this->outDir, 0775, true);
        }
    }

    /**
     * @return array{image: string, caption: string, extra: string, analysis: array, news: array}
     */
    public function analyze(string $base, string $tf): array
    {
        $a = $this->analyzer->run($base, $tf);
        $news = $this->news->forCoin($a['base']);
        $a['ai'] = ['used' => false];

        if ($this->gemini->enabled()) {
            $draft = $this->outDir . '/draft_' . $a['symbol'] . '_' . getmypid() . '.jpg';
            $this->card->render($a, $draft, false);
            $ai = $this->gemini->analyze($this->facts($a), $this->candles($a), $draft, $news, $this->brainNotes);
            @unlink($draft);
            if ($ai !== null) {
                $a = $this->mergeAi($a, $ai);
                foreach ($ai['news'] ?? [] as $k => $item) {
                    if (isset($news[$k])) {
                        $news[$k]['title_fa'] = (string) ($item['title_fa'] ?? '');
                        $news[$k]['sentiment'] = (string) ($item['sentiment'] ?? 'neutral');
                    }
                }
            }
        }

        $image = $this->outDir . '/' . $a['symbol'] . '_' . $a['timeframe'] . '_' . date('Ymd_His') . '.png';
        $this->card->render($a, $image);
        $this->cleanup();
        [$caption, $extra] = Caption::build($a, $news, $this->config['brand'] ?? []);

        return ['image' => $image, 'caption' => $caption, 'extra' => $extra, 'analysis' => $a, 'news' => $news];
    }

    /**
     * Accepts Gemini's plan only if it is internally consistent; otherwise keeps the engine plan.
     */
    private function mergeAi(array $a, array $ai): array
    {
        $atr = $a['atr'];
        $price = $a['price'];
        $side = ($ai['side'] ?? '') === 'short' ? 'short' : 'long';
        $sign = $side === 'long' ? 1 : -1;
        $lo = (float) ($ai['entry_low'] ?? 0);
        $hi = (float) ($ai['entry_high'] ?? 0);
        if ($lo > $hi) {
            [$lo, $hi] = [$hi, $lo];
        }
        $stop = (float) ($ai['stop'] ?? 0);
        $targets = array_values(array_filter(array_map('floatval', (array) ($ai['targets'] ?? [])), static fn ($t) => $t > 0));
        usort($targets, static fn ($x, $y) => ($x <=> $y) * $sign);
        $targets = array_slice($targets, 0, 3);
        if (count($targets) === 2) {
            $targets[] = $targets[1] + ($targets[1] - $targets[0]);
        }

        $mid = ($lo + $hi) / 2;
        $risk = ($mid - $stop) * $sign;
        $problems = [];
        if ($lo <= 0 || $hi <= 0 || count($targets) < 3) {
            $problems[] = 'missing levels';
        } else {
            if (($side === 'long' ? $lo - $stop : $stop - $hi) <= 0) {
                $problems[] = 'stop on wrong side';
            }
            if ($risk < 0.25 * $atr || $risk > 6 * $atr) {
                $problems[] = 'risk size';
            }
            if (($targets[0] - ($side === 'long' ? $hi : $lo)) * $sign <= 0) {
                $problems[] = 'target on wrong side';
            }
            if ($risk > 0 && ($targets[0] - $mid) * $sign / $risk < 0.8) {
                $problems[] = 'first target RR < 0.8';
            }
            if (abs($mid - $price) > 8 * $atr || abs($targets[2] - $price) > 15 * $atr) {
                $problems[] = 'levels too far from price';
            }
        }

        $planFromAi = !$problems;
        if ($planFromAi) {
            // Keep the AI's first target; space the others at least 0.6 ATR apart,
            // borrowing engine targets (or R multiples) when the AI clustered them.
            $pool = array_merge(array_slice($targets, 1), $a['plan']['targets']);
            usort($pool, static fn ($x, $y) => ($x <=> $y) * $sign);
            $final = [$targets[0]];
            foreach ($pool as $t) {
                if (count($final) < 3 && ($t - end($final)) * $sign >= 0.6 * $atr) {
                    $final[] = $t;
                }
            }
            while (count($final) < 3) {
                $final[] = end($final) + $sign * max($risk, 0.8 * $atr);
            }
            $targets = $final;
            $a['plan'] = Analyzer::finalizePlan($side, $lo, $hi, $stop, $targets, $price, $atr) + ['entry_kind' => 'ai'];
            $a['bias'] = $side;
        } else {
            app_log('gemini plan rejected (' . implode(', ', $problems) . ') ' . json_encode(['side' => $side, 'entry' => [$lo, $hi], 'stop' => $stop, 'targets' => $targets]));
        }

        // Use the AI text only when it describes the plan we are actually publishing.
        $textMatches = $side === $a['plan']['side'];
        $conf = (int) ($ai['confidence'] ?? $a['confidence']);
        $a['ai'] = [
            'used' => $textMatches,
            'model' => $ai['model'] ?? '',
            'plan_from_ai' => $planFromAi,
            'summary' => $textMatches ? trim((string) ($ai['summary'] ?? '')) : '',
            'invalidation' => $textMatches ? trim((string) ($ai['invalidation'] ?? '')) : '',
            'reasons' => $textMatches ? array_values(array_filter(array_map('trim', (array) ($ai['reasons'] ?? [])))) : [],
        ];
        if ($textMatches) {
            // Blend the AI confidence with the engine's so a single source cannot dominate.
            $a['confidence'] = (int) round(max(5, min(95, 0.6 * $conf + 0.4 * $a['confidence'])));
        }
        return $a;
    }

    /** Compact engine output for the prompt. */
    private function facts(array $a): array
    {
        $r = static function (?float $p): ?float {
            if ($p === null || $p <= 0) {
                return $p;
            }
            return round($p, max(2, 3 - (int) floor(log10($p))) + 1);
        };
        $plan = $a['plan'];
        return [
            'symbol' => $a['symbol'],
            'timeframe' => $a['timeframe'],
            'higher_tf' => $a['htf'],
            'lower_tf' => $a['ltf'],
            'price' => $r($a['price']),
            'change_24h_pct' => round($a['change24h'], 2),
            'high_24h' => $r($a['high24h']),
            'low_24h' => $r($a['low24h']),
            'atr14' => $r($a['atr']),
            'rsi14' => round($a['rsi'], 1),
            'macd_hist' => $a['macd_hist'] !== null ? $r(abs($a['macd_hist'])) * ($a['macd_hist'] < 0 ? -1 : 1) : null,
            'ema' => array_map($r, $a['ema']),
            'trend' => $a['trend'],
            'structure_breaks' => array_map(static fn ($e) => ['type' => $e['type'], 'dir' => $e['dir'], 'level' => $r($e['level']), 'bars_ago' => $a['series']->count() - 1 - $e['to']], $a['events']),
            'swings' => array_map(static fn ($l) => ['label' => $l['label'], 'price' => $r($l['price']), 'bars_ago' => $a['series']->count() - 1 - $l['i']], array_slice($a['labels'], -8)),
            'support_zones' => array_map(static fn ($z) => ['low' => $r($z['lo']), 'high' => $r($z['hi']), 'touches' => $z['touches'], 'higher_tf' => $z['htf']], $a['zones']['support']),
            'resistance_zones' => array_map(static fn ($z) => ['low' => $r($z['lo']), 'high' => $r($z['hi']), 'touches' => $z['touches'], 'higher_tf' => $z['htf']], $a['zones']['resistance']),
            'order_blocks' => array_map(static fn ($o) => ['dir' => $o['dir'], 'top' => $r($o['top']), 'bottom' => $r($o['bottom']), 'tested' => $o['tested'], 'from' => $o['event']], $a['order_blocks']),
            'fair_value_gaps' => array_map(static fn ($g) => ['dir' => $g['dir'], 'top' => $r($g['top']), 'bottom' => $r($g['bottom'])], $a['fvg']),
            'liquidity' => array_map(static fn ($l) => ['side' => $l['side'] === 'buy' ? 'buy_side_above' : 'sell_side_below', 'price' => $r($l['price']), 'kind' => $l['kind']], $a['liquidity']),
            'orderbook_walls' => array_map(static fn ($w) => ['side' => $w['side'], 'price' => $r($w['price']), 'usd' => (int) $w['notional']], $a['walls']),
            'rsi_divergence' => $a['divergence']['type'] ?? null,
            'engine_score' => $a['score'],
            'engine_reasons' => array_map(static fn ($x) => $x['fa'], array_slice($a['reasons'], 0, 8)),
            'draft_plan' => [
                'side' => $plan['side'],
                'entry_low' => $r($plan['entry'][0]),
                'entry_high' => $r($plan['entry'][1]),
                'stop' => $r($plan['stop']),
                'targets' => array_map($r, $plan['targets']),
            ],
        ];
    }

    private function candles(array $a): array
    {
        $rows = static function ($s, int $n): array {
            $out = [];
            $count = $s->count();
            for ($i = max(0, $count - $n); $i < $count; $i++) {
                $out[] = [$s->o[$i], $s->h[$i], $s->l[$i], $s->c[$i], round($s->v[$i])];
            }
            return $out;
        };
        return [$a['timeframe'] => $rows($a['series'], 80), $a['htf'] => $rows($a['htf_series'], 40)];
    }

    /** Removes images older than a day. */
    private function cleanup(): void
    {
        foreach (glob($this->outDir . '/*.{png,jpg}', GLOB_BRACE) ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}

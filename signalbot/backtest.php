<?php

declare(strict_types=1);

// Offline backtest: replays closed candles through the live signal pipeline
// (SignalGenerator::evaluateStateless) and simulates each trade with the same lifecycle
// as the worker (TP1 → risk-free, TP2 → stop on TP1, TP3 → stop on TP2, TP4, time limit).
//
//   php backtest.php --symbols=BTCUSDT,ETHUSDT,SOLUSDT --timeframes=15m,1h --days=45
//   php backtest.php --exchange=bybit --symbols=BTCUSDT --timeframes=30m --days=30 --fee=0.05
//   php backtest.php --csv=/path/to/csv --symbols=BTCUSDT --timeframes=1h
//
// Options: --exchange=binance|mexc|bybit (default: PRIMARY_EXCHANGE or binance), --days=N,
// --fee=percent per side (default 0.05), --csv=dir with SYMBOL_TF.csv files
// (open_time_ms,open,high,low,close,volume), --save-csv=dir to keep the downloaded candles,
// --trades=file.csv to write every simulated trade.
//
// Nothing is sent to Telegram and nothing is written to the signals table.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';

final class BacktestData
{
    public static function load(array $opts, string $symbol, string $tf, int $fromMs, int $toMs): array
    {
        if (!empty($opts['csv'])) {
            return self::fromCsv((string) $opts['csv'], $symbol, $tf, $fromMs, $toMs);
        }
        $candles = self::fetch((string) $opts['exchange'], $symbol, $tf, $fromMs, $toMs);
        if (!empty($opts['save-csv'])) {
            self::toCsv((string) $opts['save-csv'], $symbol, $tf, $candles);
        }
        return $candles;
    }

    private static function fetch(string $exchange, string $symbol, string $tf, int $fromMs, int $toMs): array
    {
        if ($exchange === 'mexc' && $tf === '2h') {
            return CandleAggregator::merge(self::fetch($exchange, $symbol, '1h', $fromMs, $toMs), '2h');
        }
        $stepMs = Candle::timeframeSeconds($tf) * 1000;
        $byTime = [];
        $end = $toMs;
        $guard = 0;
        while ($end > $fromMs && $guard++ < 400) {
            $batch = match ($exchange) {
                'bybit' => self::bybit($symbol, $tf, $end),
                'mexc' => self::binanceLike(Config::mexcRestBase(), $symbol, $tf === '1h' ? '60m' : ($tf === '1D' ? '1d' : $tf), $end, 1000),
                default => self::binanceLike(Config::binanceRestBase(), $symbol, $tf === '1D' ? '1d' : $tf, $end, 1000),
            };
            if (empty($batch)) {
                break;
            }
            $oldest = PHP_INT_MAX;
            foreach ($batch as $c) {
                if ($c->openTime >= $fromMs && $c->openTime < $toMs) {
                    $byTime[$c->openTime] = $c;
                }
                $oldest = min($oldest, $c->openTime);
            }
            if ($oldest >= $end) {
                break;
            }
            $end = $oldest - 1;
            usleep(150_000);
        }
        ksort($byTime);
        $out = array_values($byTime);
        $now = (int) (microtime(true) * 1000);
        while (!empty($out) && end($out)->openTime + $stepMs > $now) {
            array_pop($out);
        }
        return $out;
    }

    private static function binanceLike(string $base, string $symbol, string $interval, int $endMs, int $limit): array
    {
        $res = HttpClient::request('GET', $base . '/api/v3/klines?' . http_build_query([
            'symbol' => $symbol, 'interval' => $interval, 'endTime' => $endMs, 'limit' => $limit,
        ]));
        $out = [];
        foreach ($res['json'] ?? [] as $row) {
            if (is_array($row) && count($row) >= 6) {
                $out[] = new Candle((int) $row[0], (float) $row[1], (float) $row[2], (float) $row[3], (float) $row[4], (float) $row[5]);
            }
        }
        return $out;
    }

    private static function bybit(string $symbol, string $tf, int $endMs): array
    {
        $map = ['1m' => '1', '5m' => '5', '15m' => '15', '30m' => '30', '1h' => '60', '2h' => '120', '4h' => '240', '1D' => 'D'];
        $res = HttpClient::request('GET', Config::bybitRestBase() . '/v5/market/kline?' . http_build_query([
            'category' => 'spot', 'symbol' => $symbol, 'interval' => $map[$tf] ?? $tf, 'end' => $endMs, 'limit' => 1000,
        ]));
        $out = [];
        foreach ($res['json']['result']['list'] ?? [] as $row) {
            if (is_array($row) && count($row) >= 6) {
                $out[] = new Candle((int) $row[0], (float) $row[1], (float) $row[2], (float) $row[3], (float) $row[4], (float) $row[5]);
            }
        }
        return $out;
    }

    private static function fromCsv(string $dir, string $symbol, string $tf, int $fromMs, int $toMs): array
    {
        $path = rtrim($dir, '/') . "/{$symbol}_{$tf}.csv";
        if (!is_file($path)) {
            return [];
        }
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $row = str_getcsv($line, ",", "\"", "");
            if (count($row) < 6 || !is_numeric($row[0])) {
                continue;
            }
            $t = (int) $row[0];
            if ($t >= $fromMs && $t < $toMs) {
                $out[] = new Candle($t, (float) $row[1], (float) $row[2], (float) $row[3], (float) $row[4], (float) $row[5]);
            }
        }
        usort($out, static fn(Candle $a, Candle $b) => $a->openTime <=> $b->openTime);
        return $out;
    }

    private static function toCsv(string $dir, string $symbol, string $tf, array $candles): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $fh = fopen(rtrim($dir, '/') . "/{$symbol}_{$tf}.csv", 'w');
        if ($fh === false) {
            return;
        }
        foreach ($candles as $c) {
            fputcsv($fh, [$c->openTime, $c->open, $c->high, $c->low, $c->close, $c->volume], ",", "\"", "");
        }
        fclose($fh);
    }
}

final class BacktestTrade
{
    public string $stage = 'open';
    public ?float $tp1HitPrice = null;
    public ?string $result = null;
    public float $exit = 0.0;
    public int $closedAt = 0;

    public function __construct(public readonly Signal $signal, public readonly int $openedAt)
    {
    }

    // Walks one candle. When the stop and a target are both inside the same candle the stop
    // is assumed first (pessimistic), so the reported win rate is a floor, not a ceiling.
    public function step(Candle $c, int $maxHours, int $candleCloseMs): bool
    {
        $s = $this->signal;
        $isLong = $s->direction === Direction::LONG;
        $hit = static fn(?float $level): bool => $level !== null && ($isLong ? $c->high >= $level : $c->low <= $level);
        $through = static fn(float $level): bool => $isLong ? $c->low <= $level : $c->high >= $level;

        $stop = match ($this->stage) {
            'risk_free' => $s->entry,
            'trailing_tp2' => (float) $this->tp1HitPrice,
            'trailing_tp3' => (float) $s->tp2,
            default => $s->stopLoss,
        };

        if ($through($stop)) {
            return $this->close(match ($this->stage) {
                'open' => 'sl',
                'risk_free' => 'be',
                default => 'trail',
            }, $stop, $candleCloseMs);
        }
        if ($hit($s->tp4)) {
            return $this->close('tp4', (float) $s->tp4, $candleCloseMs);
        }
        if ($this->stage === 'trailing_tp2' && $hit($s->tp3)) {
            $this->stage = 'trailing_tp3';
            return false;
        }
        if ($this->stage === 'risk_free' && $hit($s->tp2)) {
            $this->stage = 'trailing_tp2';
            return false;
        }
        if ($this->stage === 'open' && $hit($s->tp1)) {
            $this->tp1HitPrice = (float) $s->tp1;
            $this->stage = Config::riskFreeEnabled() ? 'risk_free' : 'closed';
            if ($this->stage === 'closed') {
                return $this->close('tp1', (float) $s->tp1, $candleCloseMs);
            }
            return false;
        }
        if ($this->stage === 'open' && $maxHours > 0 && $candleCloseMs - $this->openedAt >= $maxHours * 3_600_000) {
            return $this->close('timeout', $c->close, $candleCloseMs);
        }
        return false;
    }

    private function close(string $result, float $price, int $at): bool
    {
        $this->result = $result;
        $this->exit = $price;
        $this->closedAt = $at;
        return true;
    }

    // Realised result in R (multiples of the initial risk), after fees.
    public function resultR(float $feePct): float
    {
        $s = $this->signal;
        $risk = abs($s->entry - $s->stopLoss);
        if ($risk <= 0) {
            return 0.0;
        }
        $isLong = $s->direction === Direction::LONG;
        $r = static fn(float $price): float => ($isLong ? $price - $s->entry : $s->entry - $price) / $risk;
        if ($this->tp1HitPrice !== null && $this->result !== 'tp1') {
            $share = Config::tp1ClosePercent() / 100;
            $gross = $share * $r($this->tp1HitPrice) + (1 - $share) * $r($this->exit);
        } else {
            $gross = $r($this->exit);
        }
        $feeR = ($feePct * 2 / 100) * $s->entry / $risk;
        return $gross - $feeR;
    }

    public function leveragedPnl(float $feePct): float
    {
        $s = $this->signal;
        $riskPct = abs($s->entry - $s->stopLoss) / $s->entry * 100;
        return $this->resultR($feePct) * $riskPct * $s->leverage;
    }
}

final class Backtester
{
    private SignalGenerator $generator;
    private array $rejections = [];

    public function __construct(private array $opts)
    {
        $engine = new IndicatorEngine();
        $this->generator = new SignalGenerator($engine);
    }

    public function run(): int
    {
        $symbols = array_values(array_filter(array_map('trim', explode(',', strtoupper((string) $this->opts['symbols'])))));
        $timeframes = array_values(array_filter(array_map('trim', explode(',', (string) $this->opts['timeframes']))));
        $days = max(1, (int) $this->opts['days']);
        $fee = (float) $this->opts['fee'];
        $toMs = (int) (microtime(true) * 1000);
        $fromMs = $toMs - $days * 86_400_000;

        $need = $timeframes;
        $confirm = Config::reversalConfirmTimeframe();
        $need[] = $confirm;
        foreach ($timeframes as $tf) {
            $htf = Config::htfConfirmTimeframe($tf);
            if ($htf !== null) {
                $need[] = $htf;
            }
        }
        $need = array_values(array_unique($need));

        printf("Backtest %s | %s | %d days | fee %.3f%%/side | leverage floor %dx | targets %s\n",
            implode(',', $symbols), implode(',', $timeframes), $days, $fee, Config::leverageFloor(),
            Config::targetsByRisk() ? sprintf('R %.2f/%.2f/%.2f/%.2f', Config::tpR(1), Config::tpR(2), Config::tpR(3), Config::tpR(4)) : 'leveraged %');

        $all = [];
        foreach ($symbols as $symbol) {
            $series = [];
            foreach ($need as $tf) {
                // Warm-up: 300 bars of the slowest frame before the test window starts.
                $warm = MarketDataStore::SNAPSHOT_CANDLES * Candle::timeframeSeconds($tf) * 1000;
                $series[$tf] = BacktestData::load($this->opts, $symbol, $tf, $fromMs - $warm, $toMs);
                printf("  %s %s: %d candles\n", $symbol, $tf, count($series[$tf]));
            }
            foreach ($timeframes as $tf) {
                $trades = $this->runSeries($symbol, $tf, $series, $fromMs);
                $all = array_merge($all, $trades);
                $this->report("{$symbol} {$tf}", $trades, $fee);
            }
        }
        echo str_repeat('=', 72), "\n";
        $this->report('TOTAL', $all, $fee);
        foreach ($timeframes as $tf) {
            $this->report("  all {$tf}", array_values(array_filter($all, static fn(BacktestTrade $t) => $t->signal->timeframe === $tf)), $fee);
        }
        $this->report('  LONG', array_values(array_filter($all, static fn(BacktestTrade $t) => $t->signal->direction === Direction::LONG)), $fee);
        $this->report('  SHORT', array_values(array_filter($all, static fn(BacktestTrade $t) => $t->signal->direction === Direction::SHORT)), $fee);

        arsort($this->rejections);
        echo "\nMost common rejection reasons:\n";
        foreach (array_slice($this->rejections, 0, 15, true) as $reason => $count) {
            printf("  %6d  %s\n", $count, $reason);
        }
        if (!empty($this->opts['trades'])) {
            $this->writeTrades((string) $this->opts['trades'], $all, $fee);
        }
        return 0;
    }

    private function runSeries(string $symbol, string $tf, array $series, int $fromMs): array
    {
        $main = $series[$tf] ?? [];
        $stepMs = Candle::timeframeSeconds($tf) * 1000;
        $fine = $series[Config::reversalConfirmTimeframe()] ?? [];
        $useFine = !empty($fine) && Candle::timeframeSeconds(Config::reversalConfirmTimeframe()) < Candle::timeframeSeconds($tf);
        $cursor = array_fill_keys(array_keys($series), 0);
        $fineCursor = 0;
        $trades = [];
        $open = null;
        $cooldownUntil = 0;
        $meta = ['base_asset' => SymbolClassifier::baseAsset($symbol), 'volume_24h' => (float) ($this->opts['volume'] ?? 150_000_000)];

        $total = count($main);
        foreach ($main as $i => $bar) {
            if (($i % 100) === 0) {
                fwrite(STDERR, sprintf("\r  %s %s %d/%d", $symbol, $tf, $i, $total));
            }
            $closeMs = $bar->openTime + $stepMs;
            if ($open !== null) {
                $done = false;
                if ($useFine) {
                    $fineStep = Candle::timeframeSeconds(Config::reversalConfirmTimeframe()) * 1000;
                    while ($fineCursor < count($fine) && $fine[$fineCursor]->openTime + $fineStep <= $closeMs) {
                        $f = $fine[$fineCursor++];
                        if ($f->openTime < $open->openedAt) {
                            continue;
                        }
                        if ($open->step($f, Config::maxTradeHours(), $f->openTime + $fineStep)) {
                            $done = true;
                            break;
                        }
                    }
                } elseif ($bar->openTime >= $open->openedAt) {
                    $done = $open->step($bar, Config::maxTradeHours(), $closeMs);
                }
                if ($done) {
                    $trades[] = $open;
                    if ($open->result === 'sl') {
                        $cooldownUntil = $open->closedAt + Config::symbolLossCooldownSeconds() * 1000;
                    }
                    $open = null;
                }
                continue;
            }
            if ($closeMs <= $fromMs || $closeMs < $cooldownUntil) {
                continue;
            }

            $candlesByTf = [];
            foreach ($series as $stf => $list) {
                $sstep = Candle::timeframeSeconds($stf) * 1000;
                while ($cursor[$stf] < count($list) && $list[$cursor[$stf]]->openTime + $sstep <= $closeMs) {
                    $cursor[$stf]++;
                }
                $candlesByTf[$stf] = array_slice($list, max(0, $cursor[$stf] - MarketDataStore::SNAPSHOT_CANDLES), min($cursor[$stf], MarketDataStore::SNAPSHOT_CANDLES));
            }
            if (count($candlesByTf[$tf]) < 60) {
                continue;
            }
            $snapshot = new MarketSnapshot(
                exchange: 'backtest',
                symbol: $symbol,
                price: $bar->close,
                volume: 0.0,
                bid: $bar->close,
                ask: $bar->close,
                spreadPct: 0.0,
                timestamp: intdiv($closeMs, 1000),
                candles: $candlesByTf,
            );
            $signal = $this->generator->evaluateStateless($snapshot, $tf, $meta);
            if ($signal === null) {
                $reason = $this->generator->lastReason();
                if ($reason !== null) {
                    $key = trim((string) preg_replace('/[0-9۰-۹][0-9۰-۹.,٫]*/u', '#', $reason));
                    $this->rejections[$key] = ($this->rejections[$key] ?? 0) + 1;
                }
                continue;
            }
            $open = new BacktestTrade($signal, $closeMs);
            if ($useFine) {
                while ($fineCursor < count($fine) && $fine[$fineCursor]->openTime < $closeMs) {
                    $fineCursor++;
                }
            }
        }
        if ($open !== null) {
            $last = end($main);
            if ($last instanceof Candle) {
                $open->result = $open->tp1HitPrice !== null ? 'open_after_tp1' : 'open';
                $open->exit = $last->close;
                $trades[] = $open;
            }
        }
        fwrite(STDERR, "\r" . str_repeat(' ', 40) . "\r");
        return $trades;
    }

    private function report(string $label, array $trades, float $fee): void
    {
        $closed = array_values(array_filter($trades, static fn(BacktestTrade $t) => !in_array($t->result, ['open', 'open_after_tp1'], true)));
        $n = count($closed);
        if ($n === 0) {
            printf("%-22s no closed trades\n", $label);
            return;
        }
        // Same accounting as the live report: TP1 reached = win, a losing timeout = loss.
        $wins = count(array_filter($closed, static fn(BacktestTrade $t) => $t->tp1HitPrice !== null));
        $losses = count(array_filter($closed, static fn(BacktestTrade $t) => $t->result === 'sl' || ($t->result === 'timeout' && $t->resultR(0.0) < 0)));
        $timeouts = count(array_filter($closed, static fn(BacktestTrade $t) => $t->result === 'timeout'));
        $rs = array_map(static fn(BacktestTrade $t) => $t->resultR($fee), $closed);
        $gain = array_sum(array_filter($rs, static fn(float $r) => $r > 0));
        $loss = -array_sum(array_filter($rs, static fn(float $r) => $r < 0));
        $streak = 0;
        $worst = 0;
        foreach ($closed as $t) {
            $streak = $t->result === 'sl' ? $streak + 1 : 0;
            $worst = max($worst, $streak);
        }
        $lev = array_map(static fn(BacktestTrade $t) => $t->leveragedPnl($fee), $closed);
        printf(
            "%-22s trades %4d | win(TP1) %5.1f%% | SL %3d | timeout %3d | exp %+.3fR | PF %s | avg %+.1f%% (lev) | max SL streak %d\n",
            $label,
            $n,
            ($wins + $losses) > 0 ? $wins / ($wins + $losses) * 100 : 0.0,
            $losses,
            $timeouts,
            array_sum($rs) / $n,
            $loss > 0 ? number_format($gain / $loss, 2) : 'inf',
            array_sum($lev) / $n,
            $worst
        );
    }

    private function writeTrades(string $path, array $trades, float $fee): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            return;
        }
        fputcsv($fh, ['symbol', 'tf', 'dir', 'opened_utc', 'entry', 'sl', 'tp1', 'tp2', 'tp3', 'tp4', 'lev', 'score', 'result', 'exit', 'r', 'lev_pnl_pct'], ',', '"', '');
        foreach ($trades as $t) {
            $s = $t->signal;
            fputcsv($fh, [
                $s->symbol, $s->timeframe, $s->direction->value, gmdate('Y-m-d H:i', intdiv($t->openedAt, 1000)),
                $s->entry, $s->stopLoss, $s->tp1, $s->tp2, $s->tp3, $s->tp4, $s->leverage, round($s->score, 1),
                $t->result, $t->exit, round($t->resultR($fee), 3), round($t->leveragedPnl($fee), 2),
            ], ',', '"', '');
        }
        fclose($fh);
        echo "Trades written to {$path}\n";
    }
}

$opts = getopt('', ['symbols::', 'timeframes::', 'days::', 'exchange::', 'fee::', 'csv::', 'save-csv::', 'trades::', 'volume::']);
$opts += [
    'symbols' => 'BTCUSDT,ETHUSDT,SOLUSDT,XRPUSDT,DOGEUSDT',
    'timeframes' => implode(',', Config::signalTimeframes()),
    'days' => '30',
    'exchange' => in_array(Config::primaryExchange(), ['binance', 'mexc', 'bybit'], true) ? Config::primaryExchange() : 'binance',
    'fee' => '0.05',
];
exit((new Backtester($opts))->run());

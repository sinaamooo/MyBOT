<?php

declare(strict_types=1);

namespace App\Signals;

use App\Services\LogService;
use App\Services\SettingsService;
use App\Services\SignalService;
use App\Services\SymbolService;
use App\Support\Num;

/**
 * Single scan pass: Scan Market -> Analyze -> Score -> Filter -> Risk Check
 * -> Duplicate Check -> Signal Decision -> Publish (or do nothing).
 *
 * Invoked once per cron/scanner.php run (every ~4 minutes) - there is no
 * long-lived loop like the Python version, since PHP processes don't
 * persist between requests. "Start/Pause/Stop Scanner" in the Admin Panel
 * just flips the `signals_enabled` / `scanner_running` DB flags that this
 * class (and the cron job itself) checks on each invocation.
 */
final class Scanner
{
    public function __construct(
        private readonly SignalGenerator $engine,
        private readonly Publisher $publisher,
    ) {
    }

    public function scanOnce(): void
    {
        $params = SettingsService::getAll(true);
        $now = gmdate('Y-m-d H:i:s');
        $nextScan = gmdate('Y-m-d H:i:s', time() + (int) $params['scan_interval_seconds']);

        try {
            if (empty($params['scanner_running'])) {
                LogService::log('INFO', 'scanner', 'Scanner is stopped/paused - scan skipped');
            } elseif (empty($params['signals_enabled'])) {
                LogService::log('INFO', 'scanner', 'Signals disabled by admin - scan skipped');
            } else {
                $this->runScanPass($params);
            }
            SettingsService::set('last_scan_error', '');
        } catch (\Throwable $e) {
            SettingsService::set('last_scan_error', $e->getMessage());
            LogService::log('ERROR', 'scanner', 'Scan cycle failed: ' . $e->getMessage());
        }

        SettingsService::set('last_scan_at', $now);
        SettingsService::set('next_scan_at', $nextScan);
    }

    private function runScanPass(array $params): void
    {
        $symbols = SymbolService::listEnabled();
        $todayStart = gmdate('Y-m-d 00:00:00');

        foreach ($symbols as $symbol) {
            if (SignalService::countActive() >= (int) $params['max_active_signals']) {
                LogService::log('INFO', 'scanner', "Max active signals ({$params['max_active_signals']}) reached, stopping scan pass");
                break;
            }
            if (SignalService::countSince($todayStart) >= (int) $params['max_daily_signals']) {
                LogService::log('INFO', 'scanner', "Max daily signals ({$params['max_daily_signals']}) reached, stopping scan pass");
                break;
            }
            if (SignalService::getOpenForSymbol($symbol) !== null) {
                continue; // duplicate protection: one open trade per symbol at a time
            }

            $lastTime = SignalService::getLastSignalTime($symbol);
            if ($lastTime !== null) {
                $elapsed = time() - strtotime($lastTime . ' UTC');
                if ($elapsed < (int) $params['signal_cooldown_seconds']) {
                    continue; // cooldown
                }
            }

            try {
                $analysis = $this->engine->analyzeSymbol($symbol, $params);
            } catch (\Throwable $e) {
                LogService::log('WARNING', 'scanner', "Analysis failed for {$symbol}: " . $e->getMessage());
                continue;
            }

            if ($analysis['candidate'] === null) {
                continue; // NO_SIGNAL / WATCHLIST / failed a hard gate - do nothing, as designed
            }

            $this->publishCandidate($analysis['candidate'], $params);
        }
    }

    /** @return array{signal_id: int, published: bool} */
    public function publishCandidate(array $candidate, array $params, bool $isManual = false, bool $isTest = false): array
    {
        $signalId = SignalService::create([
            'symbol' => $candidate['symbol'],
            'side' => $candidate['side'],
            'entry' => $candidate['entry'],
            'stop_loss' => $candidate['stop_loss'],
            'current_sl' => $candidate['stop_loss'],
            'tp1' => $candidate['tp1'],
            'tp2' => $candidate['tp2'],
            'tp3' => $candidate['tp3'],
            'leverage' => $candidate['leverage'],
            'score' => $candidate['score'],
            'risk_score' => $candidate['risk_score'],
            'rr' => $candidate['rr'],
            'timeframe' => $candidate['timeframe'],
            'trend' => $candidate['trend'],
            'regime' => $candidate['regime'],
            'reasons' => $candidate['reasons'],
            'is_manual' => $isManual ? 1 : 0,
            'is_test' => $isTest ? 1 : 0,
            'status' => 'PENDING',
        ]);

        $text = Formatter::formatSignalMessage($params['signal_message_template'], [
            'symbol' => $candidate['symbol'],
            'side' => $candidate['side'],
            'score' => (int) round($candidate['score']),
            'entry' => Num::price((float) $candidate['entry']),
            'stop_loss' => Num::price((float) $candidate['stop_loss']),
            'tp1' => Num::price((float) $candidate['tp1']),
            'tp2' => Num::price((float) $candidate['tp2']),
            'tp3' => Num::price((float) $candidate['tp3']),
            'leverage' => $candidate['leverage'],
            'rr' => $candidate['rr'],
            'timeframe' => $candidate['timeframe'],
            'trend' => $candidate['trend'],
            'regime' => $candidate['regime'],
            'quote' => !empty($params['signal_quote_enabled']) ? Formatter::randomQuote() : '',
            'header_emoji' => Formatter::premiumEmoji($params, 'signal', '🚨'),
        ]);

        if ($candidate['leverage'] >= 50) {
            $text .= "\n\n⚠️ اهرم بالا ({$candidate['leverage']}X): یک حرکت کوچک خلاف جهت می‌تواند کل پوزیشن را لیکویید کند. "
                . 'اهرم فقط مارجین لازم را تغییر می‌دهد نه ریسک را - همیشه حجم پوزیشن را بر اساس فاصله حد ضرر تنظیم کن، '
                . 'نه صرفاً بر اساس اهرم.';
        }

        if (!empty($candidate['pump_detected'])) {
            $pumpEmoji = Formatter::premiumEmoji($params, 'pump', '🚀');
            $text = "{$pumpEmoji} <b>شکارچی پامپ</b> (جهش حجم + شکست + شتاب مومنتوم)\n\n" . $text;
        }
        if ($isTest) {
            $text = "🧪 <b>سیگنال تستی</b> (معامله واقعی نیست)\n\n" . $text;
        } elseif ($isManual) {
            $text = "👤 <b>سیگنال دستی</b> (اورراید ادمین)\n\n" . $text;
        }

        $cardPath = !empty($params['signal_card_enabled']) ? SignalCard::generate($candidate) : null;
        $messageId = $this->publisher->publishSignal($text, $cardPath);

        if ($messageId === null) {
            LogService::log('ERROR', 'scanner', "Failed to publish signal for {$candidate['symbol']}, kept PENDING");
            return ['signal_id' => $signalId, 'published' => false];
        }

        SignalService::update($signalId, ['status' => 'ACTIVE', 'channel_message_id' => $messageId]);
        LogService::log('INFO', 'signals', "Published {$candidate['side']} {$candidate['symbol']} score={$candidate['score']} rr={$candidate['rr']} lev={$candidate['leverage']}x");

        return ['signal_id' => $signalId, 'published' => true];
    }
}

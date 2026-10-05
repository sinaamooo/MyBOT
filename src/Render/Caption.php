<?php

declare(strict_types=1);

namespace App\Render;

use App\Support\Fa;

/**
 * Builds the Telegram caption (HTML) for an analysis. Telegram allows 1024 visible
 * characters in a photo caption; whatever does not fit goes into a follow-up message.
 */
final class Caption
{
    private const LIMIT = 1024;
    private const LRM = "\u{200E}";

    /** @return array{0: string, 1: string} [caption, extra message ('' if none)] */
    public static function build(array $a, array $news, array $brand): array
    {
        $plan = $a['plan'];
        $long = $plan['side'] === 'long';
        $neutral = $a['bias'] === 'neutral';
        $e = static fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $p = static fn (float $v) => '<code>' . Fa::price($v) . '</code>';
        $pct = static fn (float $v) => self::LRM . Fa::pct($v, 1);
        $entry = $plan['entry_mid'];

        $icon = $neutral ? '🟡' : ($long ? '🟢' : '🔴');
        $scenario = $neutral
            ? 'بازار رنج؛ ' . ($long ? 'خرید از کف محدوده' : 'فروش از سقف محدوده')
            : ($long ? 'سناریوی صعودی (Long)' : 'سناریوی نزولی (Short)');

        $head = "{$icon} <b>#{$e($a['base'])} / {$e($a['quote'])}</b> | تایم " . $e(Fa::tf($a['timeframe'])) . "\n"
            . '<b>' . $e($scenario) . '</b> | اعتبار: ' . Fa::digits((string) $a['confidence']) . "٪\n"
            . '💵 قیمت فعلی: ' . $p($a['price']) . ' (' . $pct($a['change24h']) . ')';

        $planLines = '🎯 <b>ورود:</b> ' . $p($plan['entry'][0]) . ' تا ' . $p($plan['entry'][1]) . "\n"
            . '⛔️ <b>حد ضرر:</b> ' . $p($plan['stop']) . ' (' . $pct(($plan['stop'] - $entry) / $entry * 100) . ")\n";
        foreach ($plan['targets'] as $k => $t) {
            $planLines .= '✅ <b>تارگت ' . Fa::digits((string) ($k + 1)) . ':</b> ' . $p($t) . ' (' . $pct(($t - $entry) / $entry * 100) . ' | R ' . number_format($plan['rr'][$k], 1) . ")\n";
        }
        $planLines .= '⚖️ ریسک به ریوارد: <b>' . self::LRM . '1:' . number_format($plan['rr'][1] ?? $plan['rr'][0], 1) . '</b>';

        $ai = $a['ai'] ?? [];
        $summary = '';
        if (!empty($ai['used']) && ($ai['summary'] ?? '') !== '') {
            $summary = '🧠 <b>خلاصه:</b> ' . $e($ai['summary']);
            if (($ai['invalidation'] ?? '') !== '') {
                $summary .= "\n❌ <b>ابطال:</b> " . $e($ai['invalidation']);
            }
        }

        $reasonTexts = !empty($ai['used']) && !empty($ai['reasons'])
            ? array_slice($ai['reasons'], 0, 5)
            : array_map(static fn ($r) => $r['fa'], array_slice(array_values(array_filter($a['reasons'], static fn ($r) => ($r['w'] > 0) === $long)), 0, 5));
        $reasons = '';
        if ($reasonTexts) {
            $reasons = "📌 <b>دلایل:</b>\n<blockquote expandable>• " . implode("\n• ", array_map($e, $reasonTexts)) . '</blockquote>';
        }

        $liq = [];
        foreach ($a['liquidity'] as $l) {
            $liq[] = ($l['side'] === 'buy' ? 'BSL ' : 'SSL ') . $p($l['price']);
        }
        $walls = [];
        foreach ($a['walls'] as $w) {
            $walls[] = ($w['side'] === 'bid' ? 'دیوار خرید ' : 'دیوار فروش ') . $p($w['price']) . ' (' . self::LRM . '$' . Fa::compact($w['notional']) . ')';
        }
        $liquidity = '';
        if ($liq || $walls) {
            $liquidity = '💧 <b>نقدینگی:</b> ' . implode(' | ', array_slice($liq, 0, 3));
            if ($walls) {
                $liquidity .= "\n🧱 " . implode(' | ', array_slice($walls, 0, 2));
            }
        }

        $newsBlock = '';
        if ($news) {
            $lines = [];
            foreach (array_slice($news, 0, 3) as $n) {
                $mark = match ($n['sentiment'] ?? '') {
                    'positive' => '🟢',
                    'negative' => '🔴',
                    default => '⚪️',
                };
                $title = ($n['title_fa'] ?? '') !== '' ? $n['title_fa'] : $n['title'];
                $lines[] = $mark . ' <a href="' . $e($n['link']) . '">' . $e($title) . '</a>';
            }
            $newsBlock = "📰 <b>اخبار:</b>\n" . implode("\n", $lines);
        }

        $handle = $brand['handle'] ?? '';
        $footer = '⚠️ <i>تحلیل آموزشی است، نه توصیه مالی. مدیریت سرمایه را رعایت کنید.</i>' . ($handle !== '' ? "\n" . $e($handle) : '');

        // Fill the caption by priority; the rest becomes a second message.
        $sections = [$head, $planLines, $summary, $reasons, $liquidity, $newsBlock];
        $caption = [];
        $extra = [];
        foreach ($sections as $i => $sec) {
            if ($sec === '') {
                continue;
            }
            $try = implode("\n\n", array_merge($caption, [$sec, $footer]));
            if ($i < 2 || (!$extra && self::length($try) <= self::LIMIT)) {
                $caption[] = $sec;
            } else {
                $extra[] = $sec;
            }
        }
        $caption[] = $footer;
        return [implode("\n\n", $caption), implode("\n\n", $extra)];
    }

    /** Visible length in UTF-16 code units, the way Telegram counts it. */
    public static function length(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }
}

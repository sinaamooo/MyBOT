<?php

declare(strict_types=1);

namespace App\Render;

use App\Support\Fa;
use App\Texts;

/**
 * Builds the analysis caption from the editable 'caption' template (see Texts).
 * Telegram allows 1024 visible characters in a photo caption, so optional parts
 * are dropped when needed; if it still does not fit the text goes in a separate message.
 */
final class Caption
{
    private const LIMIT = 1024;
    private const LRM = "\u{200E}";

    /** @return array{0: string, 1: string} [caption, extra message ('' if none)] */
    public static function build(array $a, Texts $texts): array
    {
        $vars = self::vars($a);
        $template = $texts->get('caption');

        $attempts = [
            [],
            ['liquidity' => ''],
            ['liquidity' => '', 'summary' => ''],
            ['liquidity' => '', 'summary' => '', 'reasons' => self::reasons($a, 3)],
            ['liquidity' => '', 'summary' => '', 'reasons' => self::reasons($a, 2), 'summary_text' => '', 'invalidation' => ''],
        ];
        foreach ($attempts as $override) {
            $html = Texts::fill($template, array_merge($vars, $override));
            if (self::length($html) <= self::LIMIT) {
                return [$html, ''];
            }
        }
        // Too long even when trimmed: photo without caption, full text as its own message.
        return ['', Texts::fill($template, $vars)];
    }

    /** @return array<string, string> */
    public static function vars(array $a): array
    {
        $plan = $a['plan'];
        $long = $plan['side'] === 'long';
        $neutral = $a['bias'] === 'neutral';
        $e = static fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $pct = static fn (float $v) => self::LRM . Fa::pct($v, 1);
        $entry = $plan['entry_mid'];
        $ai = $a['ai'] ?? [];

        $vars = [
            'side_icon' => $neutral ? '🟡' : ($long ? '🟢' : '🔴'),
            'base' => $e($a['base']),
            'quote' => $e($a['quote']),
            'tf' => $e(Fa::tf($a['timeframe'])),
            'scenario' => $neutral
                ? 'بازار رنج؛ ' . ($long ? 'خرید از کف محدوده' : 'فروش از سقف محدوده')
                : ($long ? 'سناریوی صعودی (Long)' : 'سناریوی نزولی (Short)'),
            'confidence' => Fa::digits((string) $a['confidence']),
            'price' => Fa::price($a['price']),
            'change' => $pct($a['change24h']),
            'entry_low' => Fa::price($plan['entry'][0]),
            'entry_high' => Fa::price($plan['entry'][1]),
            'stop' => Fa::price($plan['stop']),
            'stop_pct' => $pct(($plan['stop'] - $entry) / $entry * 100),
            'rr' => number_format($plan['rr'][1] ?? $plan['rr'][0], 1),
        ];
        foreach ($plan['targets'] as $k => $t) {
            $n = $k + 1;
            $vars["tp{$n}"] = Fa::price($t);
            $vars["tp{$n}_pct"] = $pct(($t - $entry) / $entry * 100);
            $vars["tp{$n}_rr"] = number_format($plan['rr'][$k], 1);
        }

        $summaryText = !empty($ai['used']) ? trim((string) ($ai['summary'] ?? '')) : '';
        $invalidation = !empty($ai['used']) ? trim((string) ($ai['invalidation'] ?? '')) : '';
        $vars['summary_text'] = $e($summaryText);
        $vars['invalidation'] = $e($invalidation);
        $vars['summary'] = $summaryText !== ''
            ? '🧠 <b>خلاصه:</b> ' . $e($summaryText) . ($invalidation !== '' ? "\n❌ <b>ابطال:</b> " . $e($invalidation) : '')
            : '';
        $vars['reasons'] = self::reasons($a, 5);

        $liq = [];
        foreach ($a['liquidity'] as $l) {
            $liq[] = ($l['side'] === 'buy' ? 'BSL ' : 'SSL ') . '<code>' . Fa::price($l['price']) . '</code>';
        }
        $walls = [];
        foreach ($a['walls'] as $w) {
            $walls[] = ($w['side'] === 'bid' ? 'دیوار خرید ' : 'دیوار فروش ') . '<code>' . Fa::price($w['price']) . '</code> (' . self::LRM . '$' . Fa::compact($w['notional']) . ')';
        }
        $vars['liquidity'] = '';
        if ($liq || $walls) {
            $vars['liquidity'] = '💧 <b>نقدینگی:</b> ' . implode(' | ', array_slice($liq, 0, 3))
                . ($walls ? "\n🧱 " . implode(' | ', array_slice($walls, 0, 2)) : '');
        }
        return $vars;
    }

    private static function reasons(array $a, int $max): string
    {
        $ai = $a['ai'] ?? [];
        $long = $a['plan']['side'] === 'long';
        $list = !empty($ai['used']) && !empty($ai['reasons'])
            ? $ai['reasons']
            : array_map(static fn ($r) => $r['fa'], array_values(array_filter($a['reasons'], static fn ($r) => ($r['w'] > 0) === $long)));
        $list = array_slice($list, 0, $max);
        return $list ? '• ' . implode("\n• ", array_map(static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $list)) : '-';
    }

    /** Visible length in UTF-16 code units, the way Telegram counts it. */
    public static function length(string $html): int
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }
}

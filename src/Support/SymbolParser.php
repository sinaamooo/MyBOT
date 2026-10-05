<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Understands requests like "تحلیل BTC", "btc 1h", "تحلیل بیت کوین روزانه", "ETH/USDT h4".
 */
final class SymbolParser
{
    public const TIMEFRAMES = ['15m', '30m', '1h', '2h', '4h', '6h', '12h', '1d', '1w'];

    private const NAMES = [
        'بیت کوین' => 'BTC', 'بیتکوین' => 'BTC', 'بیت' => 'BTC', 'اتریوم' => 'ETH', 'اتریم' => 'ETH', 'اتر' => 'ETH',
        'سولانا' => 'SOL', 'ریپل' => 'XRP', 'دوج کوین' => 'DOGE', 'دوجکوین' => 'DOGE', 'دوج' => 'DOGE',
        'تون کوین' => 'TON', 'تونکوین' => 'TON', 'تون' => 'TON', 'کاردانو' => 'ADA', 'بایننس کوین' => 'BNB', 'بی ان بی' => 'BNB',
        'شیبا' => 'SHIB', 'پپه' => 'PEPE', 'ترون' => 'TRX', 'لایت کوین' => 'LTC', 'لایتکوین' => 'LTC',
        'آوالانچ' => 'AVAX', 'اوالانچ' => 'AVAX', 'چین لینک' => 'LINK', 'چینلینک' => 'LINK', 'پولکادات' => 'DOT',
        'نات کوین' => 'NOT', 'نات' => 'NOT', 'سویی' => 'SUI', 'سوئی' => 'SUI', 'اپتوس' => 'APT', 'آربیتروم' => 'ARB',
        'اپتیمیزم' => 'OP', 'کازماس' => 'ATOM', 'یونی سواپ' => 'UNI', 'استلار' => 'XLM', 'مونرو' => 'XMR',
        'اتریوم کلاسیک' => 'ETC', 'فایل کوین' => 'FIL', 'بیت کوین کش' => 'BCH', 'ترامپ' => 'TRUMP', 'هایپر لیکوئید' => 'HYPE',
        'طلا' => 'PAXG',
    ];

    private const TRIGGERS = ['تحلیل', 'انالیز', 'آنالیز', 'سیگنال', 'چارت', 'analysis', 'analyze', 'analyse', 'signal', 'chart'];

    private const STOP = ['USDT', 'USD', 'BUSD', 'USDC', 'IRT', 'TMN', 'PLEASE', 'PLS', 'THE', 'AND', 'FOR', 'OK', 'HI', 'TF', 'BOT',
        'ANALYSIS', 'ANALYZE', 'ANALYSE', 'SIGNAL', 'CHART', 'LONG', 'SHORT', 'SPOT', 'FUTURES', 'TP', 'SL'];

    /**
     * @return array{base: string, tf: ?string, explicit: bool}|null
     *   explicit = the message contains a trigger word such as "تحلیل"
     */
    public static function parse(string $text): ?array
    {
        $text = Fa::latinDigits(str_replace(["\u{200C}", "\u{200F}", "\u{200E}"], ' ', $text));
        $text = str_replace(['ي', 'ك'], ['ی', 'ک'], $text);
        $lower = mb_strtolower(trim($text));
        if ($lower === '' || mb_strlen($lower) > 160) {
            return null;
        }
        $explicit = false;
        foreach (self::TRIGGERS as $t) {
            if (str_contains($lower, $t)) {
                $explicit = true;
                break;
            }
        }

        $tf = self::timeframe($lower);
        $clean = preg_replace('/\b(?:\d{1,2}\s*(?:m|min|h|d|w)|[hHdDwWmM]\d{1,2})\b/u', ' ', $text);

        $base = null;
        // Persian names first (longest first so "بیت کوین کش" wins over "بیت کوین")
        $names = self::NAMES;
        uksort($names, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($names as $name => $sym) {
            if (preg_match('/(?<![\p{L}])' . preg_quote($name, '/') . '(?![\p{L}])/u', $clean)) {
                $base = $sym;
                break;
            }
        }
        if ($base === null && preg_match_all('/(?<![A-Za-z0-9])\$?([A-Za-z][A-Za-z0-9]{1,14})(?:\s*[\/\-]?\s*(?:USDT|usdt|USD|usd))?(?![A-Za-z0-9])/u', $clean, $m)) {
            foreach ($m[1] as $tok) {
                $tok = strtoupper($tok);
                $tok = preg_replace('/(USDT|BUSD|USDC)$/', '', $tok) ?: $tok;
                if (strlen($tok) < 2 || in_array($tok, self::STOP, true)) {
                    continue;
                }
                $base = $tok;
                break;
            }
        }
        if ($base === null) {
            return null;
        }
        if (!$explicit) {
            // Without a trigger word only accept short messages that are basically just a symbol.
            $words = preg_split('/\s+/u', trim($lower));
            if (count($words) > 3) {
                return null;
            }
        }
        return ['base' => $base, 'tf' => $tf, 'explicit' => $explicit];
    }

    public static function timeframe(string $lower): ?string
    {
        $map = [
            '/(?<![a-z0-9])(?:15\s*m(?:in)?|m15)(?![a-z])|15\s*دقیقه/u' => '15m',
            '/(?<![a-z0-9])(?:30\s*m(?:in)?|m30)(?![a-z])|30\s*دقیقه|نیم\s*ساعت/u' => '30m',
            '/(?<![a-z0-9])(?:12\s*h|h12)(?![a-z])|12\s*ساعت/u' => '12h',
            '/(?<![a-z0-9])(?:6\s*h|h6)(?![a-z])|6\s*ساعت/u' => '6h',
            '/(?<![a-z0-9])(?:4\s*h|h4)(?![a-z])|4\s*ساعت|چهار\s*ساعت/u' => '4h',
            '/(?<![a-z0-9])(?:2\s*h|h2)(?![a-z])|2\s*ساعت|دو\s*ساعت/u' => '2h',
            '/(?<![a-z0-9])(?:1\s*h|h1)(?![a-z])|1\s*ساعت|یک\s*ساعت|ساعتی/u' => '1h',
            '/(?<![a-z0-9])(?:1\s*d|d1|daily)(?![a-z])|روزانه|دیلی|یک\s*روزه/u' => '1d',
            '/(?<![a-z0-9])(?:1\s*w|w1|weekly)(?![a-z])|هفتگی|ویکلی/u' => '1w',
        ];
        foreach ($map as $re => $tf) {
            if (preg_match($re, $lower)) {
                return $tf;
            }
        }
        return null;
    }
}

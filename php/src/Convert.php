<?php

final class Convert
{
    const PREMIUM = ['3' => 11.99, '6' => 15.99, '12' => 28.99];
    const STAR_PACKS = [50, 100, 250, 500, 1000, 2500];

    public static function run(float $amount, string $kind, string $code): ?array
    {
        if ($amount <= 0 || $amount > 1e15) {
            return null;
        }
        return match ($kind) {
            'crypto', 'crypto_guess' => self::crypto($amount, $code),
            'toman' => self::toman($amount, $amount, 'IRT'),
            'rial' => self::toman($amount / 10, $amount, 'IRR'),
            'stars' => self::stars($amount),
            'fiat' => self::fiat($amount, $code),
            'gold' => self::gold($amount, $code),
            default => null,
        };
    }

    private static function bot(): string
    {
        return TG::username();
    }

    private static function rnd(float $v): string
    {
        return sprintf('%.5g', $v);
    }

    private static function ctx(): array
    {
        $usdt = Prices::usdtToman();
        $ton = Prices::usd('TON');
        $trx = Prices::usd('TRX');
        return [$usdt, $ton, $trx, Prices::starUsd()];
    }

    private static function crypto(float $amount, string $sym): ?array
    {
        $S = strtoupper($sym);
        $q = Prices::quote($S);
        if (!$q) {
            return null;
        }
        [$usdt, $tonUsd, $trxUsd, $starUsd] = self::ctx();
        $stable = in_array($S, Prices::STABLES, true);
        $usd = $amount * $q['price'];
        $toman = $usdt ? $usd * $usdt : null;
        $chg = $stable ? Market::usdtChange() : $q['change'];
        $vals = [
            'trx' => $trxUsd && $S !== 'TRX' ? Fmt::num($usd / $trxUsd) : null,
            'ton' => $tonUsd && $S !== 'TON' ? Fmt::num($usd / $tonUsd) : null,
            'usd' => !$stable ? Fmt::usd($usd) : null,
            'stars' => $starUsd > 0 ? Fmt::int($usd / $starUsd) : null,
        ];
        if (count(array_filter($vals, fn($v) => $v !== null)) > 3) {
            $vals['usd'] = null;
        }
        $name = Currencies::cryptoName($S) ?? ($q['name'] ?? null);
        $text = UI::t('conv_crypto', $vals + [
            'amount' => Fmt::num($amount),
            'symbol' => $S,
            'name' => $name,
            'toman' => $toman !== null ? Fmt::toman($toman) : null,
            'price_usd' => Fmt::usd($q['price']),
            'price_toman' => $usdt ? Fmt::toman($q['price'] * $usdt) : null,
            'change' => $chg === null ? null : Fmt::badge($chg),
            'time' => Fmt::now(),
        ]);
        $one = abs($amount - 1) < 1e-9;
        if ($toman !== null && ($stable || !$one)) {
            $val = $toman;
            $pre = '';
            $unit = 'تومان';
        } else {
            $val = $usd;
            $pre = '$';
            $unit = '';
        }
        $label = $name ?? 'قیمت لحظه‌ای';
        if (!$stable && $pre === '' ) {
            $label .= ' · $' . PriceCard::valueStr($q['price'], '');
        } elseif ($pre === '$' && $usdt) {
            $label .= ' · ' . Fmt::toman($q['price'] * $usdt) . ' تومان';
        }
        $key = 'c|' . $S . '|' . self::rnd($amount) . '|' . self::rnd($val) . '|' . round((float)$chg, 2) . '|' . self::bot();
        $bot = self::bot();
        return ['text' => $text, 'key' => $key, 'card' => fn() => PriceCard::crypto([
            'sym' => $S, 'title' => $one ? $S . ($pre === '$' ? '/USD' : '/IRT') : $S, 'pill' => $one ? '24H' : Fmt::num($amount) . ' ' . $S,
            'value' => $val, 'prefix' => $pre, 'unit' => $unit, 'chg' => (float)$chg, 'label' => $label, 'bot' => $bot,
        ])];
    }

    private static function toman(float $t, float $shown, string $tag): ?array
    {
        [$usdt, $tonUsd, $trxUsd] = self::ctx();
        if (!$usdt) {
            return ['text' => UI::t('price_unavailable'), 'key' => null, 'card' => null];
        }
        $usd = $t / $usdt;
        $chg = Market::usdtChange();
        $inv = $chg !== null && $chg > -100 ? (1 / (1 + $chg / 100) - 1) * 100 : 0.0;
        $text = UI::t('conv_toman', [
            'amount' => Fmt::toman($shown),
            'unit' => $tag === 'IRR' ? 'ریال' : 'تومان',
            'usdt' => Fmt::num($usd),
            'ton' => $tonUsd ? Fmt::num($usd / $tonUsd) : null,
            'trx' => $trxUsd ? Fmt::num($usd / $trxUsd) : null,
            'usdt_rate' => Fmt::toman($usdt),
            'time' => Fmt::now(),
        ]);
        $key = 't|' . $tag . '|' . self::rnd($shown) . '|' . self::rnd($usd) . '|' . round($inv, 2) . '|' . self::bot();
        $bot = self::bot();
        return ['text' => $text, 'key' => $key, 'card' => fn() => PriceCard::crypto([
            'sym' => 'USDT', 'title' => 'USDT', 'pill' => Fmt::toman($shown) . ' ' . $tag, 'value' => $usd,
            'unit' => '', 'prefix' => '', 'chg' => $inv, 'label' => 'تتر · ' . Fmt::toman($usdt) . ' تومان', 'bot' => $bot,
        ])];
    }

    private static function stars(float $amount): array
    {
        [$usdt, $tonUsd, $trxUsd, $starUsd] = self::ctx();
        $usd = $amount * $starUsd;
        $toman = $usdt ? $usd * $usdt : null;
        $text = UI::t('conv_stars', [
            'amount' => Fmt::int($amount),
            'toman' => $toman !== null ? Fmt::toman($toman) : null,
            'usd' => Fmt::usd($usd),
            'ton' => $tonUsd ? Fmt::num($usd / $tonUsd) : null,
            'trx' => $trxUsd ? Fmt::num($usd / $trxUsd) : null,
            'star_usd' => Fmt::num($starUsd),
            'time' => Fmt::now(),
        ]);
        if ($toman === null) {
            return ['text' => $text, 'key' => null, 'card' => null];
        }
        $chg = (float)(Market::usdtChange() ?? 0);
        $key = 's|' . self::rnd($amount) . '|' . self::rnd($toman) . '|' . round($chg, 2) . '|' . self::bot();
        $bot = self::bot();
        return ['text' => $text, 'key' => $key, 'card' => fn() => PriceCard::crypto([
            'sym' => 'STARS', 'title' => 'TELEGRAM STARS', 'pill' => Fmt::int($amount) . ' STARS', 'value' => $toman,
            'unit' => 'تومان', 'chg' => $chg, 'label' => 'استارز تلگرام · $' . Fmt::usd($usd), 'bot' => $bot,
        ])];
    }

    private static function fiat(float $amount, string $code): ?array
    {
        $code = strtoupper($code);
        $px = Market::fiatToman($code);
        $fx = Prices::fiatRates();
        if (!$px && empty($fx[$code])) {
            return null;
        }
        [$usdt, $tonUsd, $trxUsd, $starUsd] = self::ctx();
        $f = Currencies::fiat($code) ?? ['fa' => $code, 'en' => $code, 'cc' => null];
        $usd = !empty($fx[$code]) ? $amount / $fx[$code] : ($px && $usdt ? $amount * $px['toman'] / $usdt : null);
        $toman = $px ? $amount * $px['toman'] : null;
        $isUsd = $code === 'USD';
        $text = UI::t('conv_fiat', [
            'flag' => Currencies::flag($f['cc']),
            'amount' => Fmt::num($amount),
            'name' => $f['fa'],
            'code' => $code,
            'toman' => $toman !== null ? Fmt::toman($toman) : null,
            'unit_toman' => $px ? Fmt::toman($px['toman']) : null,
            'usd' => !$isUsd && $usd !== null ? Fmt::usd($usd) : null,
            'ton' => $tonUsd && $usd !== null ? Fmt::num($usd / $tonUsd) : null,
            'trx' => $trxUsd && $usd !== null ? Fmt::num($usd / $trxUsd) : null,
            'stars' => $isUsd && $usd !== null && $starUsd > 0 ? Fmt::int($usd / $starUsd) : null,
            'time' => Fmt::now(),
        ]);
        if ($toman === null) {
            return ['text' => $text, 'key' => null, 'card' => null];
        }
        $chg = (float)($px['change'] ?? 0);
        $badge = $f['cc'] && is_file(Config::$root . '/assets/flags/' . strtolower($f['cc']) . '.png') ? 'flags/' . strtolower($f['cc']) . '.png' : '';
        $key = 'f|' . $code . '|' . self::rnd($amount) . '|' . self::rnd($toman) . '|' . round($chg, 2) . '|' . self::bot();
        $bot = self::bot();
        $one = abs($amount - 1) < 1e-9;
        return ['text' => $text, 'key' => $key, 'card' => fn() => PriceCard::fiat([
            'badge' => $badge, 'name' => $f['fa'], 'value' => $toman, 'unit' => 'تومان',
            'tag' => $one ? $code . '/IRT' : Fmt::num($amount) . ' ' . $code, 'chg' => $chg,
            'label' => $one ? 'قیمت لحظه‌ای' : 'هر واحد ' . Fmt::toman($px['toman']) . ' تومان', 'bot' => $bot,
        ])];
    }

    private static function gold(float $amount, string $code): ?array
    {
        $code = strtoupper($code);
        $g = Market::gold($code);
        if (!$g) {
            return null;
        }
        [$usdt] = self::ctx();
        $isOunce = $code === 'OUNCE';
        $total = $amount * $g['toman'];
        $name = Market::GOLD[$code][1] ?? 'انس جهانی طلا';
        $text = UI::t('conv_gold', [
            'amount' => Fmt::num($amount),
            'name' => $name,
            'toman' => $isOunce ? ($usdt ? Fmt::toman($total * $usdt) : null) : Fmt::toman($total),
            'usd' => $isOunce ? Fmt::usd($total) : ($usdt ? Fmt::usd($total / $usdt) : null),
            'change' => $g['change'] === null ? null : Fmt::badge($g['change']),
            'time' => Fmt::now(),
        ]);
        $chg = (float)($g['change'] ?? 0);
        $key = 'g|' . $code . '|' . self::rnd($amount) . '|' . self::rnd($total) . '|' . round($chg, 2) . '|' . self::bot();
        $tag = Market::GOLD[$code][2] ?? 'XAU';
        $bot = self::bot();
        $one = abs($amount - 1) < 1e-9;
        return ['text' => $text, 'key' => $key, 'card' => fn() => PriceCard::fiat([
            'badge' => in_array($code, ['GOLD18', 'GOLD24', 'OUNCE', 'MESGHAL'], true) ? 'art/gold.png' : 'art/coin.png',
            'name' => $name, 'value' => $total, 'unit' => $isOunce ? 'دلار' : 'تومان',
            'tag' => $one ? $tag : Fmt::num($amount) . ' × ' . $tag, 'chg' => $chg, 'bot' => $bot,
        ])];
    }

    public static function premiumText(): ?string
    {
        [$usdt, $tonUsd] = self::ctx();
        if (!$usdt || !$tonUsd) {
            return null;
        }
        $lines = [];
        foreach (Settings::get('premium_usd') ?: self::PREMIUM as $months => $usd) {
            $lines[] = UI::fill(UI::template('premium_line'), ['months' => $months, 'toman' => Fmt::toman($usd * $usdt),
                'ton' => Fmt::num($usd / $tonUsd, 2), 'usd' => Fmt::usd($usd)]);
        }
        return UI::t('premium_prices', ['lines' => implode("\n", $lines), 'time' => Fmt::now()]);
    }

    public static function starsText(): ?string
    {
        [$usdt, , , $starUsd] = self::ctx();
        if (!$usdt) {
            return null;
        }
        $lines = [];
        foreach (self::STAR_PACKS as $n) {
            $lines[] = UI::fill(UI::template('stars_line'), ['stars' => Fmt::int($n), 'toman' => Fmt::toman($n * $starUsd * $usdt)]);
        }
        return UI::t('stars_prices', ['lines' => implode("\n", $lines), 'each' => Fmt::toman($starUsd * $usdt), 'time' => Fmt::now()]);
    }
}

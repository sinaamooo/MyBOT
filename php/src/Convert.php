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

    private static function unit(string $kind, string $code): ?array
    {
        $usdt = Prices::usdtToman();
        $vsUsd = fn($c, $base) => $c !== null && $base !== null && $base > -100 ? ((1 + $c / 100) / (1 + $base / 100) - 1) * 100 : null;
        switch ($kind) {
            case 'crypto':
                $q = Prices::quote($code);
                return $q ? [(float)$q['price'], in_array($code, Prices::STABLES, true) ? 0.0 : $q['change'], $code, Currencies::cryptoName($code) ?? $code, UI::badge($code)] : null;
            case 'toman':
            case 'rial':
                if (!$usdt) {
                    return null;
                }
                $c = Market::usdtChange();
                $r = $kind === 'rial' ? 10 : 1;
                return [1 / ($usdt * $r), $c !== null && $c > -100 ? (1 / (1 + $c / 100) - 1) * 100 : null, $kind === 'rial' ? 'IRR' : 'IRT', UI::l($kind === 'rial' ? 'ریال' : 'تومان'), UI::badge($kind === 'rial' ? 'IRR' : 'TOMAN', '💰')];
            case 'stars':
                return [Prices::starUsd(), null, 'STARS', UI::l('استارز'), '⭐️'];
            case 'fiat':
                $fx = Prices::fiatRates();
                $px = Market::fiatToman($code);
                $usd = !empty($fx[$code]) ? 1 / $fx[$code] : ($px && $usdt ? $px['toman'] / $usdt : null);
                if (!$usd) {
                    return null;
                }
                $f = Currencies::fiat($code) ?? ['fa' => $code, 'cc' => null];
                return [$usd, $code === 'USD' ? 0.0 : $vsUsd($px['change'] ?? null, Market::usdtChange()), $code, Currencies::fiatName($code), UI::badge($code, Currencies::flag($f['cc'] ?? null) ?: '💱')];
            case 'gold':
                $g = Market::gold($code);
                if (!$g || ($code !== 'OUNCE' && !$usdt)) {
                    return null;
                }
                $usd = $code === 'OUNCE' ? $g['toman'] : $g['toman'] / $usdt;
                return [$usd, $code === 'OUNCE' ? $g['change'] : $vsUsd($g['change'], Market::usdtChange()), Market::GOLD[$code][2] ?? 'XAU', UI::l(Market::GOLD[$code][1] ?? 'انس جهانی طلا'), UI::badge('GOLD', '🏅')];
        }
        return null;
    }

    public static function pair(float $amount, array $from, array $to): ?array
    {
        if ($amount <= 0 || $amount > 1e15) {
            return null;
        }
        $a = self::unit($from[0], strtoupper($from[1]));
        $b = self::unit($to[0], strtoupper($to[1]));
        if (!$a || !$b || $a[0] <= 0 || $b[0] <= 0) {
            return null;
        }
        [$usdA, $chgA, $codeA, $nameA, $iconA] = $a;
        [$usdB, $chgB, $codeB, $nameB] = $b;
        $rate = $usdA / $usdB;
        $result = $amount * $rate;
        $usd = $amount * $usdA;
        $usdt = Prices::usdtToman();
        $chg = $chgA === null && $chgB === null ? 0.0 : ((1 + ($chgA ?? 0) / 100) / (1 + ($chgB ?? 0) / 100) - 1) * 100;
        $text = UI::t('conv_pair', [
            'icon' => $iconA,
            'amount' => Fmt::num($amount),
            'from' => UI::esc($nameA),
            'to' => UI::esc($nameB),
            'result' => Fmt::num($result),
            'from_code' => $codeA,
            'to_code' => $codeB,
            'rate' => Fmt::num($rate),
            'inverse' => Fmt::num(1 / $rate),
            'usd' => in_array('USD', [$codeA, $codeB], true) ? null : Fmt::usd($usd),
            'toman' => $usdt && !array_intersect([$codeA, $codeB], ['IRT', 'IRR']) ? Fmt::toman($usd * $usdt) : null,
            'time' => Fmt::now(),
        ]);
        $toUnit = match ($codeB) {
            'IRT' => 'تومان',
            'IRR' => 'ریال',
            'USD' => '',
            default => $codeB,
        };
        $bot = self::bot();
        $key = 'p|' . $codeA . '|' . $codeB . '|' . self::rnd($amount) . '|' . self::rnd($result) . '|' . round($chg, 2) . '|' . $bot;
        return ['text' => $text, 'key' => $key, 'card' => fn() => PriceCard::crypto([
            'sym' => $from[0] === 'crypto' ? $codeA : ($to[0] === 'crypto' ? $codeB : $codeA), 'title' => $codeA . '/' . $codeB,
            'pill' => Fmt::num($amount) . ' ' . $codeA, 'value' => $result, 'prefix' => $codeB === 'USD' ? '$' : '', 'unit' => $toUnit,
            'chg' => $chg, 'label' => '1 ' . $codeA . ' = ' . PriceCard::valueStr($rate, '') . ' ' . $codeB, 'bot' => $bot,
        ])];
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
            'title' => UI::esc(Currencies::cryptoName($S) ?? $S),
            'coin_icon' => UI::badge($S),
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
        $label = $name ?? UI::l('قیمت لحظه‌ای');
        if (!$stable && $pre === '' ) {
            $label .= ' · $' . PriceCard::valueStr($q['price'], '');
        } elseif ($pre === '$' && $usdt) {
            $label .= ' · ' . Fmt::toman($q['price'] * $usdt) . ' ' . UI::l('تومان');
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
            'icon' => UI::badge($tag === 'IRR' ? 'IRR' : 'TOMAN', '💰'),
            'usdt_icon' => UI::badge('USDT', '💵'),
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
            'unit' => '', 'prefix' => '', 'chg' => $inv, 'label' => UI::l('تتر') . ' · ' . Fmt::toman($usdt) . ' ' . UI::l('تومان'), 'bot' => $bot,
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
            'unit' => 'تومان', 'chg' => $chg, 'label' => UI::l('استارز تلگرام') . ' · $' . Fmt::usd($usd), 'bot' => $bot,
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
            'icon' => UI::badge($code, Currencies::flag($f['cc']) ?: '💱'),
            'flag' => Currencies::flag($f['cc']),
            'amount' => Fmt::num($amount),
            'name' => Currencies::fiatName($code),
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
            'badge' => $badge, 'name' => Currencies::fiatName($code), 'value' => $toman, 'unit' => 'تومان',
            'tag' => $one ? $code . '/IRT' : Fmt::num($amount) . ' ' . $code, 'chg' => $chg,
            'label' => $one ? UI::l('قیمت لحظه‌ای') : UI::l('هر واحد') . ' ' . Fmt::toman($px['toman']) . ' ' . UI::l('تومان'), 'bot' => $bot,
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
        $name = UI::l(Market::GOLD[$code][1] ?? 'انس جهانی طلا');
        $text = UI::t('conv_gold', [
            'icon' => UI::badge('GOLD', '🏅'),
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
        return UI::t('premium_prices', ['icon' => UI::badge('PREMIUM', '👑'), 'lines' => implode("\n", $lines), 'time' => Fmt::now()]);
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

    const POPULAR = [['crypto', 'USDT'], ['crypto', 'BTC'], ['crypto', 'ETH'], ['crypto', 'TON'], ['crypto', 'TRX'], ['crypto', 'BNB'], ['crypto', 'SOL'],
        ['crypto', 'DOGE'], ['crypto', 'NOT'], ['crypto', 'XRP'], ['fiat', 'USD'], ['fiat', 'EUR'], ['fiat', 'AED'], ['fiat', 'TRY'], ['fiat', 'GBP'],
        ['fiat', 'IQD'], ['fiat', 'CNY'], ['gold', 'GOLD18'], ['gold', 'GOLD24'], ['gold', 'COIN'], ['gold', 'OUNCE']];

    public static function prerender(): int
    {
        if (!PriceCard::ready()) {
            return 0;
        }
        PriceCard::warm(PriceCard::basePairs(array_merge(array_column(array_filter(self::POPULAR, fn($p) => $p[0] === 'crypto'), 1), ['STARS'])));
        $n = 0;
        foreach (self::POPULAR as [$kind, $code]) {
            try {
                $a = self::run(1, $kind, $code);
                if (!empty($a['key']) && !empty($a['card'])) {
                    PriceCard::cached($a['key'], $a['card']);
                    $n++;
                }
            } catch (Throwable $e) {
                error_log('prerender: ' . $e->getMessage());
            }
        }
        PriceCard::prune();
        return $n;
    }
}

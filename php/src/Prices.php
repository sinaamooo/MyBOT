<?php

final class Currencies
{
    private static ?array $fiatIdx = null;
    private static ?array $cryptoIdx = null;
    private static ?array $goldIdx = null;

    public static function normalize(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = str_replace(['ي', 'ك', 'ى', 'ۀ', 'ة', "\u{200C}", "\u{200F}", "\u{200E}", 'ـ'], ['ی', 'ک', 'ی', 'ه', 'ه', ' ', '', '', ''], $t);
        $t = preg_replace('/[\x{064B}-\x{065F}\x{0670}]/u', '', $t);
        return trim(preg_replace('/\s+/u', ' ', $t));
    }

    private static function build(): void
    {
        if (self::$fiatIdx !== null) {
            return;
        }
        self::$fiatIdx = [];
        self::$cryptoIdx = [];
        self::$goldIdx = [];
        foreach (Config::data('units')['gold'] ?? [] as $code => $words) {
            foreach ($words as $w) {
                self::$goldIdx[self::normalize($w)] = $code;
            }
        }
        foreach (Config::data('fiat') as $code => $f) {
            foreach (array_merge([$code, $f['fa'], $f['en']], $f['aliases']) as $a) {
                $n = self::normalize($a);
                if (!isset(self::$fiatIdx[$n])) {
                    self::$fiatIdx[$n] = $code;
                }
            }
        }
        foreach (Config::data('fiat') as $code => $f) {
            $n = self::normalize(str_replace(' ', '', $f['fa']));
            if (!isset(self::$fiatIdx[$n])) {
                self::$fiatIdx[$n] = $code;
            }
        }
        foreach (Config::data('crypto') as $sym => $c) {
            foreach (array_merge([$sym, $c['fa']], $c['aliases']) as $a) {
                $n = self::normalize($a);
                if (!isset(self::$cryptoIdx[$n])) {
                    self::$cryptoIdx[$n] = $sym;
                }
            }
        }
    }

    public static function resolve(string $unit): ?array
    {
        self::build();
        $u = self::normalize($unit);
        if ($u === '') {
            return null;
        }
        $units = Config::data('units');
        if (in_array($u, $units['toman'], true)) {
            return ['toman', 'IRT'];
        }
        if (in_array($u, $units['rial'], true)) {
            return ['rial', 'IRR'];
        }
        if (in_array($u, $units['stars'], true)) {
            return ['stars', 'STARS'];
        }
        if (isset(self::$goldIdx[$u])) {
            return ['gold', self::$goldIdx[$u]];
        }
        if (in_array(strtoupper($u), $units['crypto_priority'], true)) {
            return ['crypto', strtoupper($u)];
        }
        if (isset(self::$fiatIdx[$u])) {
            if (isset(self::$cryptoIdx[$u]) && !preg_match('/^[a-z]{3}$/', $u)) {
                return ['crypto', self::$cryptoIdx[$u]];
            }
            return ['fiat', self::$fiatIdx[$u]];
        }
        if (isset(self::$cryptoIdx[$u])) {
            return ['crypto', self::$cryptoIdx[$u]];
        }
        if (preg_match('/^[a-z0-9]{2,12}$/', $u) && !ctype_digit($u)) {
            return ['crypto_guess', strtoupper($u)];
        }
        return null;
    }

    public static function word(string $text, string $group): bool
    {
        $u = self::normalize($text);
        foreach (Config::data('units')['words'][$group] ?? [] as $w) {
            if (self::normalize($w) === $u) {
                return true;
            }
        }
        return false;
    }

    public static function bareAsset(string $text): ?array
    {
        self::build();
        $u = self::normalize($text);
        foreach (Config::data('units')['bare_gold'] ?? [] as $w) {
            if (self::normalize($w) === $u) {
                return ['gold', self::$goldIdx[$u] ?? 'GOLD18'];
            }
        }
        if (isset(self::$fiatIdx[$u]) && !isset(self::$cryptoIdx[$u]) && mb_strlen($u) > 2 && !preg_match('/^[a-z]{3}$/', $u)) {
            return ['fiat', self::$fiatIdx[$u]];
        }
        return null;
    }

    public static function cryptoName(string $sym): ?string
    {
        return Config::data('crypto')[strtoupper($sym)]['fa'] ?? null;
    }

    public static function knownCrypto(string $t): ?string
    {
        self::build();
        return self::$cryptoIdx[self::normalize($t)] ?? null;
    }

    public static function fiat(string $code): ?array
    {
        return Config::data('fiat')[strtoupper($code)] ?? null;
    }

    public static function flag(?string $cc): string
    {
        if (!$cc || strlen($cc) !== 2) {
            return '🏳';
        }
        $out = '';
        foreach (str_split(strtolower($cc)) as $c) {
            $out .= mb_chr(0x1F1E6 + ord($c) - ord('a'));
        }
        return $out;
    }
}

final class Prices
{
    const STABLES = ['USDT', 'USDC', 'DAI', 'FDUSD', 'USDE', 'PYUSD', 'BUSD', 'TUSD', 'USDD'];

    private static function f($x): ?float
    {
        return is_numeric($x) && (float)$x > 0 ? (float)$x : null;
    }

    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    public static function quote(string $symbol): ?array
    {
        $sym = strtoupper(trim($symbol));
        if (in_array($sym, self::STABLES, true)) {
            return ['symbol' => $sym, 'price' => 1.0, 'change' => 0.0, 'sources' => ['peg']];
        }
        $m = Market::coin($sym);
        if ($m) {
            return ['symbol' => $sym, 'price' => $m['price'], 'change' => $m['change'], 'name' => $m['name'], 'sources' => ['market']];
        }
        return Cache::remember('q:' . $sym, 45, fn() => self::fetchQuote($sym));
    }

    private static function fetchQuote(string $sym): ?array
    {
        $cg = Config::get('COINGECKO_API_KEY');
        $reqs = [
            'Binance' => ['url' => 'https://api.binance.com/api/v3/ticker/24hr', 'params' => ['symbol' => $sym . 'USDT'], 'timeout' => 6],
            'BinanceVision' => ['url' => 'https://data-api.binance.vision/api/v3/ticker/24hr', 'params' => ['symbol' => $sym . 'USDT'], 'timeout' => 6],
            'CoinGecko' => ['url' => 'https://api.coingecko.com/api/v3/simple/price', 'params' => ['symbols' => strtolower($sym), 'vs_currencies' => 'usd', 'include_24hr_change' => 'true'], 'headers' => ['x-cg-demo-api-key' => $cg], 'timeout' => 7],
            'OKX' => ['url' => 'https://www.okx.com/api/v5/market/ticker', 'params' => ['instId' => $sym . '-USDT'], 'timeout' => 6],
            'Bybit' => ['url' => 'https://api.bybit.com/v5/market/tickers', 'params' => ['category' => 'spot', 'symbol' => $sym . 'USDT'], 'timeout' => 6],
            'KuCoin' => ['url' => 'https://api.kucoin.com/api/v1/market/stats', 'params' => ['symbol' => $sym . '-USDT'], 'timeout' => 6],
            'Gate' => ['url' => 'https://api.gateio.ws/api/v4/spot/tickers', 'params' => ['currency_pair' => $sym . '_USDT'], 'timeout' => 6],
            'CryptoCompare' => ['url' => 'https://min-api.cryptocompare.com/data/pricemultifull', 'params' => ['fsyms' => $sym, 'tsyms' => 'USD'], 'timeout' => 7],
            'MEXC' => ['url' => 'https://api.mexc.com/api/v3/ticker/price', 'params' => ['symbol' => $sym . 'USDT'], 'timeout' => 6],
        ];
        $res = Http::multiJson($reqs);
        $found = [];
        $add = function ($name, $price, $change) use (&$found) {
            if ($price) {
                $found[] = [$name, $price, is_numeric($change) ? (float)$change : null];
            }
        };
        foreach (['Binance', 'BinanceVision'] as $b) {
            $d = $res[$b];
            if (is_array($d) && self::f($d['lastPrice'] ?? null) && !in_array('Binance', array_column($found, 0), true)) {
                $add('Binance', self::f($d['lastPrice']), $d['priceChangePercent'] ?? null);
            }
        }
        $d = $res['CoinGecko'][strtolower($sym)] ?? null;
        if ($d) {
            $add('CoinGecko', self::f($d['usd'] ?? null), $d['usd_24h_change'] ?? null);
        }
        $d = $res['OKX']['data'][0] ?? null;
        if ($d && self::f($d['last'] ?? null)) {
            $o = self::f($d['open24h'] ?? null);
            $add('OKX', self::f($d['last']), $o ? (self::f($d['last']) - $o) / $o * 100 : null);
        }
        $d = $res['Bybit']['result']['list'][0] ?? null;
        if ($d && self::f($d['lastPrice'] ?? null)) {
            $add('Bybit', self::f($d['lastPrice']), is_numeric($d['price24hPcnt'] ?? null) ? $d['price24hPcnt'] * 100 : null);
        }
        $d = $res['KuCoin']['data'] ?? null;
        if ($d && self::f($d['last'] ?? null)) {
            $add('KuCoin', self::f($d['last']), is_numeric($d['changeRate'] ?? null) ? $d['changeRate'] * 100 : null);
        }
        $d = $res['Gate'][0] ?? null;
        if (is_array($d) && self::f($d['last'] ?? null)) {
            $add('Gate', self::f($d['last']), $d['change_percentage'] ?? null);
        }
        $d = $res['CryptoCompare']['RAW'][$sym]['USD'] ?? null;
        if ($d && self::f($d['PRICE'] ?? null)) {
            $add('CryptoCompare', self::f($d['PRICE']), $d['CHANGEPCT24HOUR'] ?? null);
        }
        $d = $res['MEXC'] ?? null;
        if (is_array($d) && self::f($d['price'] ?? null)) {
            $add('MEXC', self::f($d['price']), null);
        }
        if (!$found) {
            return null;
        }
        $med = self::median(array_column($found, 1));
        $good = array_values(array_filter($found, fn($f) => abs($f[1] - $med) / $med <= 0.2)) ?: $found;
        $price = count($good) >= 3 ? self::median(array_column($good, 1)) : $good[0][1];
        $change = null;
        foreach ($good as $g) {
            if ($g[2] !== null) {
                $change = $g[2];
                break;
            }
        }
        return ['symbol' => $sym, 'price' => $price, 'change' => $change, 'sources' => array_column($good, 0)];
    }

    public static function usd(string $sym): ?float
    {
        $q = self::quote($sym);
        return $q ? (float)$q['price'] : null;
    }

    public static function usdtToman(): ?float
    {
        $manual = Settings::get('usdt_toman_manual');
        if ($manual) {
            return (float)$manual;
        }
        if ($v = Market::usdtToman()) {
            return $v;
        }
        return Cache::remember('usdt_toman', 60, function () {
            $res = Http::multiJson([
                'nobitex' => ['url' => 'https://api.nobitex.ir/market/stats', 'params' => ['srcCurrency' => 'usdt', 'dstCurrency' => 'rls'], 'timeout' => 7],
                'wallex' => ['url' => 'https://api.wallex.ir/v1/markets', 'timeout' => 7],
                'bitpin' => ['url' => 'https://api.bitpin.org/api/v1/mkt/tickers/', 'timeout' => 7],
                'tabdeal' => ['url' => 'https://api1.tabdeal.org/r/api/v1/ticker/price', 'params' => ['symbol' => 'USDTIRT'], 'timeout' => 7],
                'ramzinex' => ['url' => 'https://publicapi.ramzinex.com/exchange/api/v1.0/exchange/pairs/11', 'timeout' => 7],
            ]);
            $vals = [];
            $s = $res['nobitex']['stats']['usdt-rls'] ?? null;
            if ($s && ($v = self::f($s['latest'] ?? null) ?? self::f($s['bestSell'] ?? null))) {
                $vals[] = $v / 10;
            }
            $s = $res['wallex']['result']['symbols']['USDTTMN']['stats'] ?? null;
            if ($s && ($v = self::f($s['lastPrice'] ?? null) ?? self::f($s['bidPrice'] ?? null))) {
                $vals[] = $v;
            }
            $rows = $res['bitpin'];
            $rows = is_array($rows) ? (array_is_list($rows) ? $rows : ($rows['results'] ?? [])) : [];
            foreach ($rows as $r) {
                if (in_array(strtoupper((string)($r['symbol'] ?? '')), ['USDT_IRT', 'USDTIRT'], true) && ($v = self::f($r['price'] ?? null))) {
                    $vals[] = $v;
                }
            }
            if (is_array($res['tabdeal']) && ($v = self::f($res['tabdeal']['price'] ?? null))) {
                $vals[] = $v;
            }
            $r = $res['ramzinex']['data'] ?? null;
            if ($r && ($v = self::f($r['sell'] ?? null) ?? self::f($r['buy'] ?? null))) {
                $vals[] = $v > 1000000 ? $v / 10 : $v;
            }
            $vals = array_values(array_filter($vals, fn($v) => $v > 1000 && $v < 100000000));
            if (!$vals) {
                $last = Settings::get('usdt_toman_last');
                return $last ? (float)$last : null;
            }
            $med = self::median($vals);
            $vals = array_values(array_filter($vals, fn($v) => abs($v - $med) / $med < 0.1)) ?: $vals;
            $rate = self::median($vals);
            if (abs((float)(Settings::get('usdt_toman_last') ?? 0) - $rate) > 1) {
                Settings::set('usdt_toman_last', $rate);
            }
            return $rate;
        });
    }

    public static function fiatRates(): array
    {
        if ($fx = Market::fx()) {
            return $fx;
        }
        return Cache::remember('fiat_rates', 1800, function () {
            $rates = [];
            $res = Http::multiJson([
                'fawaz' => ['url' => 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json', 'timeout' => 10],
                'fawaz2' => ['url' => 'https://latest.currency-api.pages.dev/v1/currencies/usd.json', 'timeout' => 10],
                'er' => ['url' => 'https://open.er-api.com/v6/latest/USD', 'timeout' => 10],
            ]);
            foreach (['fawaz', 'fawaz2'] as $k) {
                if (!empty($res[$k]['usd']) && is_array($res[$k]['usd'])) {
                    foreach ($res[$k]['usd'] as $c => $v) {
                        if (self::f($v)) {
                            $rates[strtoupper($c)] = (float)$v;
                        }
                    }
                    break;
                }
            }
            foreach ($res['er']['rates'] ?? [] as $c => $v) {
                if (self::f($v)) {
                    $rates[strtoupper($c)] = (float)$v;
                }
            }
            if (!$rates) {
                $d = Http::json('https://api.frankfurter.dev/v1/latest', ['params' => ['base' => 'USD'], 'timeout' => 10]);
                foreach ($d['rates'] ?? [] as $c => $v) {
                    $rates[strtoupper($c)] = (float)$v;
                }
            }
            $rates['USD'] = 1.0;
            return $rates;
        });
    }

    public static function starUsd(): float
    {
        return (float)(Settings::get('star_usd') ?: 0.015);
    }

    public static function snapshot(): array
    {
        $r = self::fiatRates();
        return ['toman' => self::usdtToman(), 'ton' => self::usd('TON'), 'trx' => self::usd('TRX'), 'eur' => $r['EUR'] ?? null];
    }
}

final class Tgju
{
    public static function parse(string $page): array
    {
        $out = [];
        $names = [];
        foreach (Config::data('fiat') as $code => $f) {
            $names[Currencies::normalize($f['fa'])] = $code;
            foreach ($f['aliases'] as $a) {
                if (!preg_match('/^[\x00-\x7F]+$/', $a)) {
                    $names[Currencies::normalize($a)] = $names[Currencies::normalize($a)] ?? $code;
                }
            }
        }
        preg_match_all('/<tr[^>]*id=["\']f-diff_[^"\']*["\'][^>]*>(.*?)<\/tr>/is', $page, $rows);
        foreach ($rows[1] as $row) {
            if (!preg_match('/<th[^>]*>(.*?)<\/th>/is', $row, $th)) {
                continue;
            }
            preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $row, $tds);
            $tds = array_map(fn($x) => trim(html_entity_decode(strip_tags($x), ENT_QUOTES, 'UTF-8')), $tds[1]);
            if (!$tds) {
                continue;
            }
            $title = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($th[1]), ENT_QUOTES, 'UTF-8')));
            $code = preg_match('/\b([A-Z]{3})\b/', $title, $cm) ? $cm[1] : null;
            if (!$code) {
                $n = Currencies::normalize(preg_replace('/[A-Za-z]/', '', $title));
                $code = $names[$n] ?? null;
                if (!$code) {
                    foreach ($names as $nm => $c) {
                        if ($nm !== '' && str_contains($n, $nm)) {
                            $code = $c;
                            break;
                        }
                    }
                }
            }
            $digits = preg_replace('/[^\d.]/', '', str_replace([',', '٬'], '', Fmt::en($tds[0])));
            $price = is_numeric($digits) ? (float)$digits : 0;
            if (!$code || $price <= 0) {
                continue;
            }
            $out[$code] = ['rial' => $price, 'toman' => $price / 10, 'change' => $tds[2] ?? null, 'title' => $title];
        }
        return $out;
    }

    public static function rates(): array
    {
        return Cache::remember('tgju', 600, fn() => self::parse(Http::text('https://www.tgju.org/diff', ['timeout' => 15]) ?? ''));
    }

    public static function toman(string $code, ?float $cross): ?float
    {
        $row = self::rates()[strtoupper($code)] ?? null;
        if (!$row) {
            return null;
        }
        $v = $row['toman'];
        if ($cross) {
            $ratio = $v / $cross;
            if ($ratio > 5 && $ratio < 15) {
                $v /= 10;
                $ratio /= 10;
            }
            if ($ratio <= 0.5 || $ratio >= 2) {
                return null;
            }
        }
        return $v;
    }
}

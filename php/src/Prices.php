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
        $src = array_map(fn($n) => dirname(__FILE__) . '/data/' . $n . '.json', ['units', 'fiat', 'crypto']);
        [self::$fiatIdx, self::$cryptoIdx, self::$goldIdx] = Config::compiled('cur_idx', array_merge($src, [__FILE__]), function () {
            self::index();
            return [self::$fiatIdx, self::$cryptoIdx, self::$goldIdx];
        });
    }

    private static function index(): void
    {
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
        if (preg_match('/^[a-z0-9]{2,15}$/', $u) && !ctype_digit($u)) {
            return [SpotDir::mask($u) ? 'crypto' : 'crypto_guess', strtoupper($u)];
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
        return UI::$lang === 'fa' ? (Config::data('crypto')[strtoupper($sym)]['fa'] ?? null) : null;
    }

    public static function fiatName(string $code): string
    {
        $code = strtoupper($code);
        $f = self::fiat($code) ?? ['fa' => $code, 'en' => $code];
        return match (UI::$lang) {
            'fa' => $f['fa'],
            'ru' => Config::data('phrases_ru')['fiat:' . $code] ?? $f['en'],
            default => $f['en'],
        };
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

final class SpotDir
{
    const TTL = 21600;
    const BITS = ['Binance' => 1, 'OKX' => 2, 'Bybit' => 4, 'KuCoin' => 8, 'Gate' => 16, 'MEXC' => 32];
    const CHAT_WORDS = ['OK', 'OKAY', 'LOL', 'HI', 'HEY', 'HELLO', 'YES', 'NO', 'GO', 'ME', 'YOU', 'THE', 'AND', 'FOR', 'WIN', 'TOP', 'GOOD', 'BAD', 'NICE', 'WOW', 'BRO', 'XD', 'HA', 'HAHA'];

    private static ?array $d = null;

    private static function file(): string
    {
        return Config::$root . '/data/spot.json';
    }

    public static function reset(?array $d = null): void
    {
        self::$d = $d;
    }

    public static function all(): array
    {
        if (self::$d === null) {
            $f = self::file();
            self::$d = is_file($f) ? Config::compiled('spot', [$f], fn() => json_decode((string)@file_get_contents($f), true) ?: []) : [];
        }
        return self::$d;
    }

    public static function mask(string $sym): int
    {
        return (int)(self::all()['s'][strtoupper($sym)] ?? 0);
    }

    public static function count(): int
    {
        return count(self::all()['s'] ?? []);
    }

    public static function due(): bool
    {
        return time() - (int)(self::all()['at'] ?? 0) > self::TTL;
    }

    public static function refresh(): int
    {
        $res = Http::multiJson([
            'Binance' => ['url' => 'https://api.binance.com/api/v3/ticker/price', 'timeout' => 20],
            'BinanceVision' => ['url' => 'https://data-api.binance.vision/api/v3/ticker/price', 'timeout' => 20],
            'OKX' => ['url' => 'https://www.okx.com/api/v5/public/instruments', 'params' => ['instType' => 'SPOT'], 'timeout' => 20],
            'Bybit' => ['url' => 'https://api.bybit.com/v5/market/instruments-info', 'params' => ['category' => 'spot', 'limit' => 1000], 'timeout' => 20],
            'KuCoin' => ['url' => 'https://api.kucoin.com/api/v2/symbols', 'timeout' => 20],
            'Gate' => ['url' => 'https://api.gateio.ws/api/v4/spot/currency_pairs', 'timeout' => 20],
            'MEXC' => ['url' => 'https://api.mexc.com/api/v3/ticker/price', 'timeout' => 20],
        ]);
        $lists = [];
        foreach (['Binance', 'BinanceVision'] as $k) {
            if (!isset($lists['Binance']) && is_array($res[$k] ?? null) && array_is_list($res[$k])) {
                $lists['Binance'] = self::pairs($res[$k], fn($r) => (float)($r['price'] ?? 0) > 0 && str_ends_with((string)($r['symbol'] ?? ''), 'USDT') ? substr($r['symbol'], 0, -4) : null);
            }
        }
        if (is_array($res['OKX']['data'] ?? null)) {
            $lists['OKX'] = self::pairs($res['OKX']['data'], fn($r) => ($r['quoteCcy'] ?? '') === 'USDT' && ($r['state'] ?? 'live') === 'live' ? ($r['baseCcy'] ?? null) : null);
        }
        if (is_array($res['Bybit']['result']['list'] ?? null)) {
            $lists['Bybit'] = self::pairs($res['Bybit']['result']['list'], fn($r) => ($r['quoteCoin'] ?? '') === 'USDT' && ($r['status'] ?? 'Trading') === 'Trading' ? ($r['baseCoin'] ?? null) : null);
        }
        if (is_array($res['KuCoin']['data'] ?? null)) {
            $lists['KuCoin'] = self::pairs($res['KuCoin']['data'], fn($r) => ($r['quoteCurrency'] ?? '') === 'USDT' && !empty($r['enableTrading']) ? ($r['baseCurrency'] ?? null) : null);
        }
        if (is_array($res['Gate'] ?? null) && array_is_list($res['Gate'])) {
            $lists['Gate'] = self::pairs($res['Gate'], fn($r) => ($r['quote'] ?? '') === 'USDT' && ($r['trade_status'] ?? 'tradable') === 'tradable' ? ($r['base'] ?? null) : null);
        }
        if (is_array($res['MEXC'] ?? null) && array_is_list($res['MEXC'])) {
            $lists['MEXC'] = self::pairs($res['MEXC'], fn($r) => (float)($r['price'] ?? 0) > 0 && str_ends_with((string)($r['symbol'] ?? ''), 'USDT') ? substr($r['symbol'], 0, -4) : null);
        }
        if (!$lists) {
            return 0;
        }
        $old = self::all()['s'] ?? [];
        $out = [];
        foreach (self::BITS as $ex => $bit) {
            if (!empty($lists[$ex])) {
                foreach ($lists[$ex] as $sym) {
                    $out[$sym] = ($out[$sym] ?? 0) | $bit;
                }
                continue;
            }
            foreach ($old as $sym => $m) {
                if ($m & $bit) {
                    $out[$sym] = ($out[$sym] ?? 0) | $bit;
                }
            }
        }
        if (count($out) < 50) {
            return 0;
        }
        ksort($out);
        $d = ['at' => time(), 's' => $out];
        $tmp = self::file() . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($d)) !== false && @rename($tmp, self::file())) {
            self::$d = $d;
        } else {
            @unlink($tmp);
        }
        return count($out);
    }

    private static function pairs(array $rows, callable $base): array
    {
        $out = [];
        foreach ($rows as $r) {
            $b = is_array($r) ? $base($r) : null;
            if (is_string($b) && preg_match('/^[A-Z0-9]{2,15}$/', $b = strtoupper($b))) {
                $out[$b] = true;
            }
        }
        return array_keys($out);
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
        $k = 'q:' . $sym;
        $v = Cache::get($k, $hit);
        if ($hit) {
            return $v;
        }
        $lock = @fopen(sys_get_temp_dir() . '/mybot_q_' . md5(Config::$root . $sym) . '.lock', 'c');
        if ($lock && flock($lock, LOCK_EX)) {
            $v = Cache::get($k, $hit);
            if ($hit) {
                flock($lock, LOCK_UN);
                return $v;
            }
        }
        try {
            $v = isset(Market::PIN[$sym]) ? self::pinned($sym) : self::fetchQuote($sym);
            Cache::set($k, $v, $v ? 45 : 600);
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
            }
        }
        return $v;
    }

    private static function pinned(string $sym): ?array
    {
        [$id, $aliases] = Market::PIN[$sym];
        $all = array_filter(self::fetchMany(array_merge([$sym], $aliases)));
        $cg = Http::json('https://api.coingecko.com/api/v3/simple/price', ['params' => ['ids' => $id, 'vs_currencies' => 'usd', 'include_24hr_change' => 'true'],
            'headers' => ['x-cg-demo-api-key' => Config::get('COINGECKO_API_KEY')], 'timeout' => 5]);
        $anchor = self::f($cg[$id]['usd'] ?? null);
        if ($anchor) {
            foreach ($all as $q) {
                if (abs($q['price'] - $anchor) / $anchor <= 0.07) {
                    return ['symbol' => $sym] + $q;
                }
            }
            return ['symbol' => $sym, 'price' => $anchor, 'change' => is_numeric($cg[$id]['usd_24h_change'] ?? null) ? (float)$cg[$id]['usd_24h_change'] : null, 'sources' => ['CoinGecko']];
        }
        return $all ? ['symbol' => $sym] + reset($all) : null;
    }

    private static function fetchQuote(string $sym): ?array
    {
        return self::fetchMany([$sym])[$sym] ?? null;
    }

    public static function fetchMany(array $syms, float $deadline = 2.5): array
    {
        $reqs = [];
        foreach ($syms as $sym) {
            foreach (self::quoteReqs($sym) as $src => $r) {
                $reqs["$sym|$src"] = $r;
            }
        }
        $got = $reqs ? Http::collect($reqs, fn($k) => $k, fn($k, $d) => is_array($d) ? $d : null, $deadline) : [];
        $by = [];
        foreach ($got as $k => $d) {
            [$sym, $src] = explode('|', $k, 2);
            $by[$sym][$src] = $d;
        }
        $out = [];
        foreach ($syms as $sym) {
            $out[$sym] = self::quoteParse($sym, $by[$sym] ?? []);
        }
        return $out;
    }

    public static function prefetch(array $syms): void
    {
        $need = [];
        foreach (array_unique(array_filter(array_map(fn($x) => strtoupper(trim((string)$x)), $syms))) as $sym) {
            if (in_array($sym, self::STABLES, true) || Market::coin($sym)) {
                continue;
            }
            Cache::get('q:' . $sym, $hit);
            if (!$hit) {
                $need[] = $sym;
            }
        }
        if ($need) {
            foreach (self::fetchMany(array_slice($need, 0, 20)) as $sym => $v) {
                Cache::set('q:' . $sym, $v, $v ? 45 : 600);
            }
        }
    }

    private static function quoteReqs(string $sym): array
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
        $mask = SpotDir::mask($sym);
        if ($mask) {
            $reqs = array_filter($reqs, fn($k) => ($mask & (SpotDir::BITS[$k === 'BinanceVision' ? 'Binance' : $k] ?? 0)) !== 0, ARRAY_FILTER_USE_KEY);
        }
        return $reqs;
    }

    private static function quoteParse(string $sym, array $res): ?array
    {
        $res += array_fill_keys(['Binance', 'BinanceVision', 'CoinGecko', 'OKX', 'Bybit', 'KuCoin', 'Gate', 'CryptoCompare', 'MEXC'], null);
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
            ], 3.0);
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

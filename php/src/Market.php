<?php

final class Market
{
    const FRESH = 75;
    const STALE = 900;
    const ALT_TTL = 240;
    const FX_TTL = 3600;
    const GOLD = [
        'GOLD18' => ['geram18', 'طلا ۱۸ عیار', '18K GOLD'],
        'GOLD24' => ['geram24', 'طلا ۲۴ عیار', '24K GOLD'],
        'COIN' => ['sekee', 'سکه امامی', 'COIN'],
        'BAHAR' => ['sekeb', 'سکه بهار آزادی', 'COIN'],
        'NIM' => ['nim', 'نیم سکه', '1/2 COIN'],
        'ROB' => ['rob', 'ربع سکه', '1/4 COIN'],
        'GERAMI' => ['gerami', 'سکه گرمی', 'GERAMI'],
        'MESGHAL' => ['mesghal', 'مثقال طلا', 'MESGHAL'],
    ];

    const PIN = ['TON' => ['the-open-network', ['GRAM']], 'NOT' => ['notcoin', []], 'DOGS' => ['dogs-2', []], 'TRX' => ['tron', []], 'BTC' => ['bitcoin', []],
        'ETH' => ['ethereum', []], 'BNB' => ['binancecoin', []], 'SOL' => ['solana', []], 'DOGE' => ['dogecoin', []], 'XRP' => ['ripple', []]];
    const TOP = ['BTC', 'ETH', 'TON', 'GRAM', 'TRX', 'BNB', 'SOL', 'DOGE', 'NOT', 'XRP', 'ADA', 'LTC', 'DOGS', 'SHIB', 'PEPE', 'AVAX', 'DOT', 'LINK', 'BCH', 'ETC',
        'XLM', 'ATOM', 'UNI', 'SUI', 'APT', 'NEAR', 'FIL', 'ARB', 'OP', 'TRUMP', 'HMSTR', 'CATI', 'MAJOR', 'USDC'];
    const PRIO = ['okx' => 0, 'gecko' => 1, 'cc' => 2, 'wallex' => 3, 'bitpin' => 4, 'nobitex' => 5];
    const COIN_TTL = 600;

    private static ?array $snap = null;

    private static function file(): string
    {
        $d = Config::$root . '/data';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }
        return $d . '/market.json';
    }

    public static function snap(): array
    {
        if (self::$snap !== null) {
            return self::$snap;
        }
        $raw = @file_get_contents(self::file());
        $s = $raw ? json_decode($raw, true) : null;
        $s = is_array($s) ? $s : [];
        $age = time() - (int)($s['at'] ?? 0);
        if ($age > self::FRESH && !Http::$fake) {
            if (!empty($s['irt']) && $age < self::COIN_TTL - 60) {
                register_shutdown_function(function () use ($s) {
                    try {
                        self::refresh($s);
                    } catch (Throwable $e) {
                        error_log('market refresh: ' . $e->getMessage());
                    }
                });
            } else {
                $new = self::refresh($s, true);
                if ($new) {
                    $s = $new;
                }
            }
        }
        return self::$snap = $s;
    }

    public static function reset(?array $s = null): void
    {
        self::$snap = $s;
    }

    public static function refresh(array $old = [], bool $wait = false, bool $force = false): ?array
    {
        $lock = @fopen(self::file() . '.lock', 'c');
        if ($lock && !flock($lock, $wait ? LOCK_EX : LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return null;
        }
        try {
            $raw = @file_get_contents(self::file());
            $cur = $raw ? json_decode($raw, true) : null;
            if (!$force && is_array($cur) && time() - (int)($cur['at'] ?? 0) <= self::FRESH) {
                return $cur;
            }
            $s = self::build(is_array($cur) ? $cur : $old);
            if (!empty($s['irt']) || !empty($s['c'])) {
                $tmp = self::file() . '.' . bin2hex(random_bytes(4));
                if (@file_put_contents($tmp, json_encode($s, JSON_UNESCAPED_UNICODE)) !== false) {
                    @rename($tmp, self::file());
                }
            }
            self::$snap = $s;
            return $s;
        } finally {
            if ($lock) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private static function num($v): ?float
    {
        if (!is_scalar($v)) {
            return null;
        }
        $v = str_replace([',', '،', '٬', ' '], '', Fmt::en((string)$v));
        return is_numeric($v) ? (float)$v : null;
    }

    private static function median(array $v): float
    {
        sort($v);
        $n = count($v);
        return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
    }

    private static function build(array $old): array
    {
        $now = time();
        $gk = Config::get('COINGECKO_API_KEY');
        $geckoDue = $now - (int)($old['gk_at'] ?? 0) >= 55;
        $reqs = [
            'wallex' => ['url' => 'https://api.wallex.ir/v1/markets', 'timeout' => 6],
            'gecko' => ['url' => 'https://api.coingecko.com/api/v3/coins/markets', 'params' => ['vs_currency' => 'usd', 'order' => 'market_cap_desc', 'per_page' => 250, 'page' => 1, 'price_change_percentage' => '24h'], 'headers' => ['x-cg-demo-api-key' => $gk], 'timeout' => 7],
            'okx' => ['url' => 'https://www.okx.com/api/v5/market/tickers', 'params' => ['instType' => 'SPOT'], 'timeout' => 6],
            'nobitex' => ['url' => 'https://api.nobitex.ir/market/stats', 'params' => ['srcCurrency' => 'usdt,btc,eth,ton,trx', 'dstCurrency' => 'rls'], 'timeout' => 6],
            'nobitexu' => ['url' => 'https://api.nobitex.ir/market/stats', 'params' => ['srcCurrency' => strtolower(implode(',', self::TOP)), 'dstCurrency' => 'usdt'], 'timeout' => 6],
            'cc' => ['url' => 'https://min-api.cryptocompare.com/data/pricemultifull', 'params' => ['fsyms' => implode(',', self::TOP), 'tsyms' => 'USD'], 'timeout' => 6],
            'bitpin' => ['url' => 'https://api.bitpin.org/api/v1/mkt/tickers/', 'timeout' => 6],
            'tabdeal' => ['url' => 'https://api1.tabdeal.org/r/api/v1/ticker/price', 'params' => ['symbol' => 'USDTIRT'], 'timeout' => 6],
        ];
        if (!$geckoDue) {
            unset($reqs['gecko']);
        }
        $altDue = $now - (int)($old['alt_at'] ?? 0) > self::ALT_TTL;
        if ($altDue) {
            $reqs['tgju'] = ['url' => 'https://call1.tgju.org/ajax.json', 'timeout' => 7];
            $reqs['tgju3'] = ['url' => 'https://call3.tgju.org/ajax.json', 'timeout' => 7];
        }
        $fxDue = $now - (int)($old['fx_at'] ?? 0) > self::FX_TTL;
        if ($fxDue) {
            $reqs['er'] = ['url' => 'https://open.er-api.com/v6/latest/USD', 'timeout' => 7];
            $reqs['fawaz'] = ['url' => 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json', 'timeout' => 7];
        }
        $res = Http::multiJson($reqs, 5.0);
        $cand = [];
        $add = function (string $src, string $sym, $price, $chg, ?string $name = null, $vol = 0, int $w = 1) use (&$cand) {
            $sym = strtoupper(trim($sym));
            $price = self::num($price);
            if (!$price || $price <= 0 || !preg_match('/^[A-Z0-9]{1,12}$/', $sym)) {
                return;
            }
            $cand[$sym][] = [$src, $price, $chg === null ? null : self::num($chg), $name, (float)(self::num($vol) ?? 0), $w];
        };
        $byId = [];
        $seen = [];
        $gk = [];
        $gid = [];
        $pinIds = array_flip(array_column(self::PIN, 0));
        if (!$geckoDue) {
            foreach ((array)($old['gk'] ?? []) as $sym => $row) {
                $add('gecko', (string)$sym, $row[0], $row[1], $row[2], $row[3]);
            }
            foreach ((array)($old['gid'] ?? []) as $id => $row) {
                $byId[(string)$id] = ['gecko', $row[0], $row[1], $row[2], $row[3], 1];
            }
        }
        foreach ((array)($res['gecko'] ?? []) as $r) {
            $sym = strtoupper(trim((string)($r['symbol'] ?? '')));
            $row = ['gecko', self::num($r['current_price'] ?? null), self::num($r['price_change_percentage_24h'] ?? null), mb_substr((string)($r['name'] ?? $sym), 0, 40), (float)(self::num($r['total_volume'] ?? null) ?? 0), 1];
            if (!$row[1]) {
                continue;
            }
            $byId[(string)($r['id'] ?? '')] = $row;
            if (isset($pinIds[(string)($r['id'] ?? '')])) {
                $gid[(string)$r['id']] = array_slice($row, 1, 4);
            }
            if (!isset($seen[$sym])) {
                $seen[$sym] = true;
                $gk[$sym] = array_slice($row, 1, 4);
                $add('gecko', $sym, $row[1], $row[2], $row[3], $row[4]);
            }
        }
        foreach ((array)($res['okx']['data'] ?? []) as $r) {
            if (!preg_match('/^([A-Z0-9]{1,12})-USDT$/', (string)($r['instId'] ?? ''), $mm)) {
                continue;
            }
            $last = self::num($r['last'] ?? null);
            $open = self::num($r['open24h'] ?? null);
            $ts = (int)(self::num($r['ts'] ?? null) ?? 0);
            if ($last && (!$ts || $ts / 1000 > $now - 3600)) {
                $add('okx', $mm[1], $last, $open ? ($last - $open) / $open * 100 : null, null, $r['volCcy24h'] ?? 0);
            }
        }
        foreach ((array)($res['cc']['RAW'] ?? []) as $sym => $q) {
            $u = $q['USD'] ?? null;
            if (is_array($u) && (int)($u['LASTUPDATE'] ?? $now) > $now - 3600) {
                $add('cc', (string)$sym, $u['PRICE'] ?? null, $u['CHANGEPCT24HOUR'] ?? null, null, $u['VOLUME24HOURTO'] ?? 0);
            }
        }
        foreach ((array)($res['nobitexu']['stats'] ?? []) as $pair => $st) {
            if (preg_match('/^([a-z0-9]+)-usdt$/', (string)$pair, $mm) && is_array($st) && empty($st['isClosed'])) {
                $add('nobitex', $mm[1], $st['latest'] ?? null, $st['dayChange'] ?? null);
            }
        }
        foreach ((array)(is_array($res['bitpin'] ?? null) ? (array_is_list($res['bitpin']) ? $res['bitpin'] : ($res['bitpin']['results'] ?? [])) : []) as $r) {
            if (is_array($r) && preg_match('/^([A-Z0-9]+)_?USDT$/', strtoupper((string)($r['symbol'] ?? '')), $mm)) {
                $add('bitpin', $mm[1], $r['price'] ?? null, $r['daily_change_price'] ?? null);
            }
        }
        $irts = [];
        $irtChg = null;
        $wx = $res['wallex']['result']['symbols'] ?? null;
        if (is_array($wx)) {
            $tmn = [];
            foreach ($wx as $key => $row) {
                $st = is_array($row['stats'] ?? null) ? $row['stats'] : $row;
                $price = null;
                foreach (['lastPrice', 'bidPrice', 'askPrice'] as $pk) {
                    $n = self::num($st[$pk] ?? null);
                    if ($n && $n > 0) {
                        $price = $n;
                        break;
                    }
                }
                if (!$price) {
                    continue;
                }
                $base = strtoupper((string)($row['baseAsset'] ?? ''));
                $quote = strtoupper((string)($row['quoteAsset'] ?? ''));
                $sym = strtoupper((string)($row['symbol'] ?? $key));
                if ($base === '' || $quote === '') {
                    if (str_ends_with($sym, 'USDT')) {
                        [$base, $quote] = [substr($sym, 0, -4), 'USDT'];
                    } elseif (str_ends_with($sym, 'TMN')) {
                        [$base, $quote] = [substr($sym, 0, -3), 'TMN'];
                    } else {
                        continue;
                    }
                }
                $chg = self::num($st['24h_ch'] ?? $st['24h_change'] ?? null);
                if (in_array($quote, ['TMN', 'IRT'], true)) {
                    $tmn[$base] = [$price, $chg];
                } elseif ($quote === 'USDT' && $base !== '') {
                    $add('wallex', $base, $price, $chg);
                }
            }
            if (!empty($tmn['USDT'][0])) {
                $irts[] = $tmn['USDT'][0];
                $irtChg = $tmn['USDT'][1];
            }
        }
        $s = $res['nobitex']['stats']['usdt-rls'] ?? null;
        if ($s && ($v = self::num($s['latest'] ?? null) ?: self::num($s['bestSell'] ?? null))) {
            $irts[] = $v / 10;
            if ($irtChg === null && ($d = self::num($s['dayChange'] ?? null)) !== null) {
                $irtChg = $d;
            }
        }
        $rows = $res['bitpin'] ?? null;
        $rows = is_array($rows) ? (array_is_list($rows) ? $rows : ($rows['results'] ?? [])) : [];
        foreach ($rows as $r) {
            if (in_array(strtoupper((string)($r['symbol'] ?? '')), ['USDT_IRT', 'USDTIRT'], true) && ($v = self::num($r['price'] ?? null))) {
                $irts[] = $v;
            }
        }
        if (is_array($res['tabdeal'] ?? null) && ($v = self::num($res['tabdeal']['price'] ?? null))) {
            $irts[] = $v;
        }
        $irts = array_values(array_filter($irts, fn($v) => $v > 1000 && $v < 100000000));
        $irt = null;
        if ($irts) {
            $med = self::median($irts);
            $irts = array_values(array_filter($irts, fn($v) => abs($v - $med) / $med < 0.1)) ?: $irts;
            $irt = self::median($irts);
        }
        foreach (self::PIN as $sym => [$id, $aliases]) {
            $all = [];
            foreach (array_merge([$sym], $aliases) as $k) {
                array_push($all, ...($cand[$k] ?? []));
            }
            if (isset($byId[$id])) {
                $all[] = array_replace($byId[$id], [5 => 3]);
            }
            if ($all) {
                foreach (array_merge([$sym], $aliases) as $k) {
                    $cand[$k] = $all;
                }
            }
        }
        $coins = [];
        foreach ($cand as $sym => $list) {
            if ($c = self::consensus($list)) {
                $coins[$sym] = [$c[0], $c[1], $c[2] ?? $sym, $now];
            }
        }
        foreach ((array)($old['c'] ?? []) as $sym => $r) {
            if (!isset($coins[$sym]) && isset($r[3]) && $now - (int)$r[3] < 3600) {
                $coins[$sym] = $r;
            }
        }
        $out = [
            'at' => $now,
            'irt' => $irt ?? ($old['irt'] ?? null),
            'irt_chg' => $irtChg ?? ($old['irt_chg'] ?? null),
            'c' => $coins,
            'gk' => $geckoDue ? ($gk ?: ($old['gk'] ?? [])) : ($old['gk'] ?? []),
            'gid' => $geckoDue ? ($gid ?: ($old['gid'] ?? [])) : ($old['gid'] ?? []),
            'gk_at' => $geckoDue && $gk ? $now : (int)($old['gk_at'] ?? 0),
            'a' => $old['a'] ?? [],
            'alt_at' => $old['alt_at'] ?? 0,
            'fx' => $old['fx'] ?? [],
            'fx_at' => $old['fx_at'] ?? 0,
        ];
        if ($altDue) {
            $j = is_array($res['tgju'] ?? null) ? $res['tgju'] : ($res['tgju3'] ?? null);
            $a = self::parseTgju(is_array($j) ? $j : []);
            if ($a) {
                $out['a'] = $a;
                $out['alt_at'] = $now;
            }
        }
        if ($fxDue) {
            $fx = [];
            foreach ((array)($res['fawaz']['usd'] ?? []) as $c => $v) {
                if (($n = self::num($v)) && $n > 0) {
                    $fx[strtoupper($c)] = $n;
                }
            }
            foreach ((array)($res['er']['rates'] ?? []) as $c => $v) {
                if (($n = self::num($v)) && $n > 0) {
                    $fx[strtoupper($c)] = $n;
                }
            }
            if ($fx) {
                $fx['USD'] = 1.0;
                $out['fx'] = $fx;
                $out['fx_at'] = $now;
            }
        }
        return $out;
    }

    public static function consensus(array $list): ?array
    {
        $best = null;
        $score = null;
        foreach ($list as $c) {
            $members = array_values(array_filter($list, fn($d) => abs($d[1] - $c[1]) / $c[1] <= 0.07));
            $sc = [array_sum(array_map(fn($d) => $d[5] ?? 1, $members)), array_sum(array_map(fn($d) => $d[4] ?? 0, $members))];
            if ($score === null || $sc > $score) {
                $score = $sc;
                $best = $members;
            }
        }
        if (!$best) {
            return null;
        }
        usort($best, fn($x, $y) => (self::PRIO[$x[0]] ?? 9) <=> (self::PRIO[$y[0]] ?? 9));
        $chg = null;
        $name = null;
        foreach ($best as $b) {
            $chg ??= $b[2];
            $name ??= $b[3];
        }
        return [$best[0][1], $chg, $name];
    }

    public static function parseTgju(array $j): array
    {
        $cur = $j['current'] ?? null;
        if (!is_array($cur)) {
            return [];
        }
        $out = [];
        $row = function ($k) use ($cur) {
            $r = $cur[$k] ?? null;
            if (!is_array($r)) {
                return null;
            }
            $p = self::num($r['p'] ?? null);
            if (!$p || $p <= 0) {
                return null;
            }
            $dp = self::num($r['dp'] ?? null);
            $dt = (string)($r['dt'] ?? '');
            if ($dp !== null && in_array($dt, ['low', 'down'], true)) {
                $dp = -abs($dp);
            }
            return [$p, $dp];
        };
        if ($r = $row('price_dollar_rl')) {
            $out['USD'] = [$r[0] / 10, $r[1]];
        }
        foreach ($cur as $k => $_) {
            if (preg_match('/^price_([a-z]{3})$/', (string)$k, $m) && ($r = $row($k))) {
                $out[strtoupper($m[1])] = [$r[0] / 10, $r[1]];
            }
        }
        foreach (self::GOLD as $code => [$key]) {
            if ($r = $row($key)) {
                $out[$code] = [$r[0] / 10, $r[1]];
            }
        }
        if ($r = $row('ons')) {
            $out['OUNCE'] = [$r[0], $r[1]];
        }
        return $out;
    }

    public static function usdtToman(): ?float
    {
        $manual = (float)(Settings::get('usdt_toman_manual') ?: 0);
        if ($manual > 0) {
            return $manual;
        }
        $v = self::snap()['irt'] ?? null;
        return $v ? (float)$v : null;
    }

    public static function usdtChange(): ?float
    {
        $v = self::snap()['irt_chg'] ?? null;
        return $v === null ? null : (float)$v;
    }

    public static function coin(string $sym): ?array
    {
        $sym = strtoupper($sym);
        $c = self::snap()['c'][$sym] ?? null;
        if (!$c || !isset($c[3]) || time() - (int)$c[3] > self::COIN_TTL) {
            return null;
        }
        return ['price' => (float)$c[0], 'change' => $c[1] === null ? null : (float)$c[1], 'name' => $c[2] ?? $sym];
    }

    public static function fx(): array
    {
        return self::snap()['fx'] ?? [];
    }

    public static function alt(string $code): ?array
    {
        $a = self::snap()['a'][strtoupper($code)] ?? null;
        return $a ? ['toman' => (float)$a[0], 'change' => $a[1] === null ? null : (float)$a[1]] : null;
    }

    public static function fiatToman(string $code): ?array
    {
        $code = strtoupper($code);
        $usdt = Prices::usdtToman();
        $fx = self::fx() ?: Prices::fiatRates();
        $cross = ($usdt && !empty($fx[$code])) ? $usdt / $fx[$code] : null;
        $alt = self::alt($code);
        if ($alt && $alt['toman'] > 0) {
            $v = $alt['toman'];
            if ($cross) {
                $ratio = $v / $cross;
                if ($ratio > 5 && $ratio < 15) {
                    $v /= 10;
                    $ratio /= 10;
                }
                if ($ratio > 0.5 && $ratio < 2) {
                    return ['toman' => $v, 'change' => $alt['change'], 'source' => 'market'];
                }
            } else {
                return ['toman' => $v, 'change' => $alt['change'], 'source' => 'market'];
            }
        }
        if ($cross) {
            return ['toman' => $cross, 'change' => self::usdtChange(), 'source' => 'cross'];
        }
        return null;
    }

    public static function gold(string $code): ?array
    {
        $code = strtoupper($code);
        $a = self::alt($code);
        if ($a && $a['toman'] > 0) {
            return $a;
        }
        $usdt = Prices::usdtToman();
        $ounce = self::alt('OUNCE')['toman'] ?? null;
        if (!$ounce) {
            foreach (['PAXG', 'XAUT'] as $s) {
                if ($q = self::coin($s)) {
                    $ounce = $q['price'];
                    break;
                }
            }
        }
        if ($code === 'OUNCE') {
            return $ounce ? ['toman' => (float)$ounce, 'change' => self::coin('PAXG')['change'] ?? null] : null;
        }
        if (!$ounce || !$usdt) {
            return null;
        }
        $k24 = $ounce / 31.1034768 * $usdt;
        $v = match ($code) {
            'GOLD24' => $k24,
            'GOLD18' => $k24 * 0.75,
            'MESGHAL' => $k24 * 0.75 * 4.608,
            'COIN', 'BAHAR' => $k24 * 8.133 * 0.9 * 1.12,
            'NIM' => $k24 * 4.066 * 0.9 * 1.12,
            'ROB' => $k24 * 2.033 * 0.9 * 1.12,
            'GERAMI' => $k24 * 1.01 * 0.9 * 1.12,
            default => null,
        };
        return $v ? ['toman' => $v, 'change' => self::coin('PAXG')['change'] ?? null] : null;
    }
}

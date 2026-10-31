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
            if (!empty($s['irt']) && $age < 86400) {
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
        $reqs = [
            'wallex' => ['url' => 'https://api.wallex.ir/v1/markets', 'timeout' => 6],
            'gecko' => ['url' => 'https://api.coingecko.com/api/v3/coins/markets', 'params' => ['vs_currency' => 'usd', 'order' => 'market_cap_desc', 'per_page' => 250, 'page' => 1, 'price_change_percentage' => '24h'], 'headers' => ['x-cg-demo-api-key' => $gk], 'timeout' => 7],
            'okx' => ['url' => 'https://www.okx.com/api/v5/market/tickers', 'params' => ['instType' => 'SPOT'], 'timeout' => 6],
            'nobitex' => ['url' => 'https://api.nobitex.ir/market/stats', 'params' => ['srcCurrency' => 'usdt,btc,eth,ton,trx', 'dstCurrency' => 'rls'], 'timeout' => 6],
            'bitpin' => ['url' => 'https://api.bitpin.org/api/v1/mkt/tickers/', 'timeout' => 6],
            'tabdeal' => ['url' => 'https://api1.tabdeal.org/r/api/v1/ticker/price', 'params' => ['symbol' => 'USDTIRT'], 'timeout' => 6],
        ];
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
        $res = Http::multiJson($reqs);
        $coins = [];
        foreach ((array)($res['gecko'] ?? []) as $r) {
            $sym = strtoupper(trim((string)($r['symbol'] ?? '')));
            $usd = self::num($r['current_price'] ?? null);
            if (!preg_match('/^[A-Z0-9]{1,12}$/', $sym) || !$usd || isset($coins[$sym])) {
                continue;
            }
            $coins[$sym] = [$usd, self::num($r['price_change_percentage_24h'] ?? null), mb_substr((string)($r['name'] ?? $sym), 0, 40)];
        }
        foreach ((array)($res['okx']['data'] ?? []) as $r) {
            if (!preg_match('/^([A-Z0-9]{1,12})-USDT$/', (string)($r['instId'] ?? ''), $mm) || isset($coins[$mm[1]])) {
                continue;
            }
            $last = self::num($r['last'] ?? null);
            $open = self::num($r['open24h'] ?? null);
            if ($last) {
                $coins[$mm[1]] = [$last, $open ? ($last - $open) / $open * 100 : null, $mm[1]];
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
                } elseif ($quote === 'USDT' && $base !== '' && !isset($coins[$base])) {
                    $coins[$base] = [$price, $chg, $base];
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
        $out = [
            'at' => $now,
            'irt' => $irt ?? ($old['irt'] ?? null),
            'irt_chg' => $irtChg ?? ($old['irt_chg'] ?? null),
            'c' => $coins ?: ($old['c'] ?? []),
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
        return $c ? ['price' => (float)$c[0], 'change' => $c[1] === null ? null : (float)$c[1], 'name' => $c[2] ?? $sym] : null;
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

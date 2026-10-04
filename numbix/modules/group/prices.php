<?php
defined('NB_ROOT') || exit;

function pxDefaults() {
    return [
        'on'  => true,

        'wx_on'  => 1,
        'wx_url' => 'https://api.wallex.ir/v1/markets',

        'api' => 'https://api.coingecko.com/api/v3/coins/markets',
        'key' => '',
        'ttl' => 60,

        'alt_url' => "https://call1.tgju.org/ajax.json\nhttps://call3.tgju.org/ajax.json\nhttps://call.tgju.org/ajax.json",
        'alt_ttl' => 300,
        'timeout' => 6,
        'cooldown' => 120,

        'fx_url' => "https://open.er-api.com/v6/latest/USD\nhttps://api.exchangerate-api.com/v4/latest/USD\nhttps://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json",
        'fx_ttl' => 3600,

        'derive' => 1,
        'gold_k' => 1.0,
        'coin_k' => 1.12,

        'emoji' => [
            'date'   => '5413879192267805083',
            'gold'   => '5949707595445968258',
            'usd'    => '5951773156887764244',
            'toman'  => '5965097893491642896',
            'chg'    => '6050900104431278847',
            'conv'   => '4931934645626341248',

            'frag'  => '4902715076873553054',
            'card'  => '5343902037438391058',
            'prem'  => '5899945812296731931',
            'star'  => '4936468614967460670',
            'ton'   => '5899945812296731931',
            'coin'  => '5271878966347601947',
        ],

        'buttons' => [
            ['on' => 1, 'text' => '💎 ربات خدمات مجازی', 'url' => '', 'color' => 'success', 'icon' => ''],
            ['on' => 1, 'text' => '➕ افزودن به گروه',    'url' => '', 'color' => 'primary', 'icon' => ''],
        ],

        'premium_usd' => ['3' => 11.99, '6' => 15.99, '12' => 28.99],
        'premium_off' => ['3' => 20, '6' => 47, '12' => 52],
        'star_usd'    => 0.015,
        'star_packs'  => [50, 100, 150, 250, 500, 1000, 2500],

        'margin' => 0,

        'coins' => ['BTC', 'ETH', 'TON', 'TRX', 'XRP', 'SOL', 'DOGE'],

        'words' => [
            'premium' => 'پریمیوم,پرمیوم,تلگرام پریمیوم,قیمت پریمیوم,اشتراک',
            'stars'   => 'استارز,ستاره,قیمت استارز,stars',
            'rates'   => 'نرخ ارز,قیمت ارز,ارزها,کریپتو,بازار',
        ],

        'card' => [
            'on' => 1,
            'font'      => '',
            'font_bold' => '',
        ],

        'texts' => [
            'prem_head'  => 'Telegram Premium',
            'prem_month' => '{n} months',
            'prem_off'   => '{off}% Sale',
            'star_head'  => '{n} STARS :',
            'rates_head' => 'بازار جهانی',
            'coin_head'  => '{n} {sym} :',
            'foot'       => 'Fragment',
            'toman'      => 'toman',
            'down'       => 'موتور قیمت الان جواب نمی‌دهد. چند لحظه بعد دوباره امتحان کنید.',

            'prem_full'  => '',
            'star_full'  => '',
        ],
    ];
}

function pxCfg($refresh = false) {
    static $out = null;
    if ($out !== null && !$refresh) return $out;

    $c = cfg()['prices'] ?? null;
    if (!is_array($c)) return $out = pxDefaults();

    if (str_contains((string)($c['api'] ?? ''), 'swapwallet.app') || (int)($c['ttl'] ?? 60) < 15) {
        unset($c['api'], $c['key']);
        $c['ttl'] = 60;
        pxSet(function (&$x) { unset($x['api'], $x['key']); $x['ttl'] = 60; });
        maCachePut('px_cool', 0);
    }

    $out = array_replace_recursive(pxDefaults(), $c);
    foreach (['texts', 'emoji', 'words'] as $k) $out[$k] = array_intersect_key((array)$out[$k], pxDefaults()[$k]);
    foreach (['buttons', 'coins', 'star_packs'] as $k) {
        if (isset($c[$k]) && is_array($c[$k])) $out[$k] = array_values($c[$k]);
    }
    $shape = ['on' => 1, 'text' => '', 'url' => '', 'color' => 'primary', 'icon' => ''];
    $def   = pxDefaults()['buttons'];
    $btns  = [];
    foreach (array_values((array)$out['buttons']) as $i => $b) {
        if (!is_array($b)) continue;
        $btns[] = array_replace($shape, $def[$i] ?? [], $b);
    }
    $out['buttons'] = $btns;
    return $out;
}

function pxSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['prices'] ?? null)) $c['prices'] = pxDefaults();
        $fn($c['prices']);
    });
    pxCfg(true);
}

function pxVal($path, $default = null) {
    $v = pxCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function pxT($slug, $vars = []) {
    $t = (string)(pxVal('texts.' . $slug) ?? pxDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function pxEm($slug, $fallback = '💎') {
    $id = trim((string)pxVal('emoji.' . $slug, ''));
    if ($id === '' || !ctype_digit($id)) return $fallback;
    return '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>';
}

function pxNoNet($on = null) {
    static $flag = false;
    if ($on !== null) $flag = (bool)$on;
    return $flag;
}

function pxWarmClaim($secs) {
    $f = DATA_DIR . '/.px_warm';
    $now = time();
    clearstatcache(true, $f);
    if (($mt = @filemtime($f)) !== false && ($now - $mt) < $secs) return false;

    $existed = is_file($f);

    $fp = @fopen($f, 'c');
    if (!$fp) return true;
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return false; }

    $ok = true;
    if ($existed) {
        clearstatcache(true, $f);
        $mt2 = @filemtime($f);
        $ok  = !($mt2 !== false && ($now - $mt2) < $secs);
    }
    if ($ok) @touch($f);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ok;
}

function pxWarm() {
    pxNoNet(false);
    if (!pxWarmClaim(max(10, (int)(pxVal('ttl', 60) / 2)))) return;

    try {
        if (maCacheGet('px_pairs', max(15, (int)pxVal('ttl', 60))) === null) pxFetch(true);

        $alt = trim((string)pxVal('alt_url', ''));
        if ($alt !== '' && maCacheGet('px_alt', max(30, (int)pxVal('alt_ttl', 300))) === null)
            pxAltFetch(true);
        pxLogoSync(4);
    } catch (Throwable $e) {
        error_log('[prices-warm] ' . $e->getMessage());
    }
}

function pxHasAnyCache() {
    return is_array(maCacheGet('px_pairs', 0));
}

function pxStale() {
    if (maCacheGet('px_pairs', max(15, (int)pxVal('ttl', 60))) === null) return true;
    if (trim((string)pxVal('alt_url', '')) !== ''
        && maCacheGet('px_alt', max(30, (int)pxVal('alt_ttl', 300))) === null) return true;
    return false;
}

function pxToNum($v) {
    if (!is_scalar($v)) return null;
    $raw = str_replace([',', '،', '٬', ' ', "\u{200c}"], '', norm_fa_digits((string)$v));
    return is_numeric($raw) ? (float)$raw : null;
}

function pxGetMany(array $jobs, $timeout = 6) {
    if (!$jobs) return [];

    $out = [];
    if (function_exists('__pxHttpHook')) {
        foreach ($jobs as $k => $j) {
            $r = __pxHttpHook($j['url'], $j['head'] ?? '');
            if (is_array($r)) { $out[$k] = $r; unset($jobs[$k]); }
        }
        if (!$jobs) return $out;
    }

    if (!function_exists('curl_multi_init')) {
        foreach ($jobs as $k => $j)
            $out[$k] = maHttp($j['url'], 'GET', $j['head'] ?? '', '', (int)($j['timeout'] ?? $timeout), 'prices');
        return $out;
    }

    $mh = curl_multi_init();
    $hs = [];
    foreach ($jobs as $k => $j) {
        $url = trim((string)($j['url'] ?? ''));
        if ($url === '' || !preg_match('#^https?://#i', $url)) continue;
        $head = [];
        foreach (preg_split('/\r?\n/', (string)($j['head'] ?? '')) as $line) {
            $line = trim($line);
            if ($line !== '' && str_contains($line, ':')) $head[] = $line;
        }
        $t  = max(2, (int)($j['timeout'] ?? $timeout));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $t,
            CURLOPT_CONNECTTIMEOUT => min(5, $t),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
            CURLOPT_HTTPHEADER     => $head ?: ['Accept: application/json'],
        ]);
        curl_multi_add_handle($mh, $ch);
        $hs[$k] = $ch;
    }
    if (!$hs) { curl_multi_close($mh); return $out; }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.3);
    } while ($running > 0);

    foreach ($hs as $k => $ch) {
        $body = curl_multi_getcontent($ch);
        monCurlDone($ch, 'prices');
        $err  = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        if (!is_string($body) || $body === '') { $out[$k] = [null, $err ?: 'پاسخی نیامد']; continue; }
        if ($code < 200 || $code >= 300) {
            $why = trim(preg_replace('/\s+/u', ' ', $body));
            $out[$k] = [null, 'کد پاسخ ' . $code . ($why !== '' ? ' — ' . mb_substr($why, 0, 200) : '')];
            continue;
        }
        $j = json_decode($body, true);
        $out[$k] = is_array($j) ? [$j, ''] : [null, 'پاسخ JSON نبود'];
    }
    curl_multi_close($mh);
    return $out;
}

function pxWallexPairs($j) {
    $syms = pxWallexSymbols($j);
    if (!$syms) return [];

    $tmn = $usd = $chg = [];
    foreach ($syms as $key => $row) {
        if (!is_array($row)) continue;
        $sym = strtoupper(trim((string)($row['symbol'] ?? $key)));
        if ($sym === '') continue;

        $st = is_array($row['stats'] ?? null) ? $row['stats'] : $row;
        $price = null;
        foreach (['lastPrice', 'last_price', 'bidPrice', 'askPrice', 'last', 'price'] as $pk) {
            $n = pxToNum($st[$pk] ?? null);
            if ($n !== null && $n > 0) { $price = $n; break; }
        }
        if ($price === null) continue;

        $base  = strtoupper(trim((string)($row['baseAsset']  ?? '')));
        $quote = strtoupper(trim((string)($row['quoteAsset'] ?? '')));
        if ($base === '' || $quote === '') {
            if (str_ends_with($sym, 'USDT'))     { $quote = 'USDT'; $base = substr($sym, 0, -4); }
            elseif (str_ends_with($sym, 'TMN'))  { $quote = 'TMN';  $base = substr($sym, 0, -3); }
            elseif (str_ends_with($sym, 'IRT'))  { $quote = 'TMN';  $base = substr($sym, 0, -3); }
            else continue;
        }
        if ($quote === 'IRT' || $quote === 'IRR') $quote = 'TMN';
        if ($base === '') continue;

        $c = null;
        foreach (['24h_ch', '24h_change', 'change24h', 'dayChange'] as $ck) {
            $n = pxToNum($st[$ck] ?? null);
            if ($n !== null) { $c = $n; break; }
        }

        if ($quote === 'TMN')  { $tmn[$base] = $price; if ($c !== null && !isset($chg[$base])) $chg[$base] = $c; }
        if ($quote === 'USDT') { $usd[$base] = $price; if ($c !== null) $chg[$base] = $c; }
    }

    $irt = (float)($tmn['USDT'] ?? 0);
    if ($irt <= 0) return [];

    $out = ['USDT/IRT' => $irt];
    if (isset($chg['USDT'])) $out['USDT/CHANGE24'] = $chg['USDT'];

    foreach ($usd as $b => $v) if ($b !== 'USDT' && $v > 0) $out[$b . '/USDT'] = $v;
    foreach ($tmn as $b => $v) {
        if ($b === 'USDT' || $v <= 0) continue;
        if (!isset($out[$b . '/USDT'])) $out[$b . '/USDT'] = $v / $irt;
    }
    foreach ($chg as $b => $v) if ($b !== 'USDT') $out[$b . '/CHANGE24'] = $v;

    return $out;
}

function pxWallexSymbols($j) {
    if (!is_array($j)) return [];
    if (is_array($j['result']['symbols'] ?? null)) return $j['result']['symbols'];
    if (is_array($j['symbols'] ?? null))           return $j['symbols'];
    if (is_array($j['result'] ?? null) && !isset($j['result']['symbols'])) {
        $r = $j['result'];
        $first = is_array($r) ? reset($r) : null;
        if (is_array($first) && (isset($first['stats']) || isset($first['symbol']))) return $r;
    }
    foreach ($j as $v)
        if (is_array($v) && is_array($v['symbols'] ?? null)) return $v['symbols'];
    return [];
}

function pxFetch($fresh = false) {
    static $mem = null;
    if (!$fresh && is_array($mem)) return $mem;

    $c = pxCfg();
    $ck = 'px_pairs';

    if (!$fresh && (pxNoNet() || (function_exists('maNoNet') && maNoNet()))) {
        $any = maCacheGet($ck, 0);
        return $mem = (is_array($any) ? $any : []);
    }

    if (!$fresh) {
        $hit = maCacheGet($ck, (int)$c['ttl']);
        if (is_array($hit)) return $mem = $hit;

        if ((int)(maCacheGet('px_cool', (int)$c['cooldown']) ?: 0) > 0)
            return $mem = (array)(maCacheGet($ck, 0) ?: []);

        $lockFp = @fopen(DATA_DIR . '/.px_fetch.lock', 'c');
        if ($lockFp && flock($lockFp, LOCK_EX)) {
            try {
                $hit2 = maCacheGet($ck, (int)$c['ttl'], true);
                if (is_array($hit2)) return $mem = $hit2;
                if ((int)(maCacheGet('px_cool', (int)$c['cooldown'], true) ?: 0) > 0)
                    return $mem = (array)(maCacheGet($ck, 0, true) ?: []);
                return $mem = pxFetchNetwork($c, $ck);
            } finally {
                flock($lockFp, LOCK_UN);
                fclose($lockFp);
            }
        }
        if ($lockFp) fclose($lockFp);
    }

    return $mem = pxFetchNetwork($c, $ck);
}

function pxFetchNetwork($c, $ck) {
    $to   = max(2, (int)$c['timeout']);
    $jobs = [];

    $wx = trim((string)($c['wx_url'] ?? ''));
    if (!empty($c['wx_on']) && $wx !== '')
        $jobs['wx'] = ['url' => $wx, 'head' => 'Accept: application/json', 'timeout' => $to];

    $jobs += pxSecondJobs((string)$c['api'], (string)$c['key'], $to);

    if (!$jobs) return [];
    $res = pxGetMany($jobs, $to);

    $out = $wxErr = [];
    [$jw, $ew] = $res['wx'] ?? [null, 'گرفته نشد'];
    if (is_array($jw)) {
        $out = pxWallexPairs($jw);
        if (!$out) $wxErr[] = 'والکس: نمادی پیدا نشد';
    } elseif (isset($jobs['wx'])) {
        $wxErr[] = 'والکس: ' . $ew;
    }

    $meta = [];
    foreach (['api', 'api2'] as $jk) {
        if (!isset($jobs[$jk])) continue;
        [$j, $err] = $res[$jk] ?? [null, 'گرفته نشد'];
        if (is_array($j)) {
            foreach (pxSecondPairs($j, $meta) as $k => $v)
                if (!isset($out[$k])) $out[$k] = $v;
        } elseif ($jk === 'api') {
            $wxErr[] = 'منبع دوم: ' . $err;
        }
    }
    pxGeckoKeep($meta);

    if (!$out) {
        maCachePut('px_err', implode(' · ', $wxErr) ?: 'پاسخی نیامد');
        maCachePut('px_cool', time());
        return (array)(maCacheGet($ck, 0) ?: []);
    }

    maCachePut('px_err', $wxErr ? implode(' · ', $wxErr) : '');
    maCachePut('px_cool', 0);
    maCachePut($ck, $out);
    pxHistNote($out);
    return $out;
}

function pxSwapPairs($j) {
    $rows = (isset($j['result']) && is_array($j['result'])) ? $j['result'] : $j;
    if (!is_array($rows)) return [];

    $out = [];
    foreach ($rows as $k => $v) {
        if (!is_string($k) || !str_contains($k, '/')) continue;
        $K = strtoupper($k);

        $n = pxToNum($v);
        if ($n !== null) { $out[$K] = $n; continue; }

        if (!is_array($v)) continue;
        foreach (['price', 'last', 'value', 'p', 'close', 'rate'] as $pk) {
            if (isset($v[$pk]) && ($n = pxToNum($v[$pk])) !== null) { $out[$K] = $n; break; }
        }
        foreach (['change24h', 'change_24h', 'changePercent', 'change_percent',
                  'percentChange', 'percent', 'change', 'chg', 'dp'] as $ck) {
            if (isset($v[$ck]) && ($n = pxToNum($v[$ck])) !== null) {
                $out[explode('/', $K)[0] . '/CHANGE24'] = $n;
                break;
            }
        }
    }
    return $out;
}

if (!defined('PX_HIST_STEP')) define('PX_HIST_STEP', 60);
if (!defined('PX_HIST_KEEP')) define('PX_HIST_KEEP', 180000);
if (!defined('PX_HIST_FINE')) define('PX_HIST_FINE', 7200);
if (!defined('PX_HIST_COARSE')) define('PX_HIST_COARSE', 600);

function pxHistThin($pts, $now) {
    $out = [];
    $lastCoarse = 0;
    foreach ($pts as $p) {
        if (!is_array($p) || count($p) < 2) continue;
        $t = (int)$p[0];
        if ($now - $t > PX_HIST_KEEP) continue;
        if ($now - $t <= PX_HIST_FINE) { $out[] = $p; continue; }
        if ($t - $lastCoarse < PX_HIST_COARSE) continue;
        $lastCoarse = $t;
        $out[] = $p;
    }
    return count($out) > 260 ? array_slice($out, -260) : $out;
}

function pxHistNote($prices) {
    if (!$prices) return;
    $now = time();

    $mark = DATA_DIR . '/.hist_at';
    if ($now - (@filemtime($mark) ?: 0) < PX_HIST_STEP) return;
    @touch($mark);

    mutate('px_hist', function (&$h) use ($prices, $now) {
        foreach (array_keys($h) as $pair)
            if (!preg_match('#^[A-Z0-9]{1,10}/(USDT|IRT)$#', (string)$pair)) unset($h[$pair]);
        foreach ($prices as $pair => $v) {
            $v = (float)$v;
            if ($v <= 0) continue;
            if (!preg_match('#^[A-Z0-9]{1,10}/(USDT|IRT)$#', (string)$pair)) continue;
            $pts = (array)($h[$pair] ?? []);
            $pts[] = [$now, $v];
            $h[$pair] = array_values(pxHistThin($pts, $now));
        }
    });
}

if (!defined('PX_HIST_MIN')) define('PX_HIST_MIN', 1500);

function pxHistAgo($pair, $seconds = 86400) {
    $pts = (array)(load('px_hist')[strtoupper((string)$pair)] ?? []);
    if (!$pts) return null;

    $now = time();
    $target = $now - $seconds;

    $best = null; $bestGap = PHP_INT_MAX;
    $oldest = null; $oldestT = PHP_INT_MAX;
    foreach ($pts as $p) {
        if (!is_array($p) || count($p) < 2) continue;
        $t = (int)$p[0]; $v = (float)$p[1];
        if ($v <= 0) continue;
        $gap = abs($t - $target);
        if ($gap < $bestGap) { $bestGap = $gap; $best = $v; }
        if ($t < $oldestT)   { $oldestT = $t;   $oldest = $v; }
    }

    if ($best !== null && $bestGap <= $seconds * 0.75) return $best;

    if ($oldest !== null && ($now - $oldestT) >= PX_HIST_MIN) return $oldest;
    return null;
}

function pxLastError() { return (string)(maCacheGet('px_err', 0) ?: ''); }

function pxPair($pair, $fresh = false) {
    $p = pxFetch($fresh);
    return (float)($p[strtoupper($pair)] ?? 0);
}

function pxTonUsd($fresh = false) { return pxPair('TON/USDT', $fresh); }

function pxUsdtIrt($fresh = false) { return pxPair('USDT/IRT', $fresh); }

function pxUsdtToman() {
    $r = (float)pxUsdtIrt();
    if ($r > 0) {
        if (!is_numeric(maCacheGet('px_usdt_last', 600))) maCachePut('px_usdt_last', $r);
        return $r;
    }
    $fb = maCacheGet('px_usdt_fb', 600);
    if (is_numeric($fb) && (float)$fb > 0) return (float)$fb;
    if (!(function_exists('maNoNet') && maNoNet()) && !maCacheGet('px_usdt_fb_cool', 120)) {
        $r = pxUsdtNobitex();
        if ($r > 0) { maCachePut('px_usdt_fb', $r); maCachePut('px_usdt_last', $r); return $r; }
        maCachePut('px_usdt_fb_cool', 1);
    }
    $last = maCacheGet('px_usdt_last', 3 * 86400);
    return is_numeric($last) ? (float)$last : 0.0;
}

function pxUsdtNobitex() {
    $url = defined('PX_NOBITEX_URL') ? PX_NOBITEX_URL : 'https://api.nobitex.ir/market/stats?srcCurrency=usdt&dstCurrency=rls';
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 7, CURLOPT_CONNECTTIMEOUT => 5,
                            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; NumbixBot/1.0)', CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    $res = monCurl($ch, 'prices');
    curl_close($ch);
    $j = is_string($res) ? json_decode($res, true) : null;
    $st = is_array($j['stats']['usdt-rls'] ?? null) ? $j['stats']['usdt-rls'] : null;
    if (!$st) return 0.0;
    foreach (['latest', 'bestSell', 'bestBuy'] as $k) {
        $n = pxToNum($st[$k] ?? null);
        if ($n !== null && $n > 10000) return round($n / 10);
    }
    return 0.0;
}

function pxPremiumRows($fresh = false) {
    $ton = pxTonUsd($fresh);
    $irt = pxUsdtIrt($fresh);
    if ($ton <= 0 || $irt <= 0) return [];

    $plans = (array)pxVal('premium_usd', []);
    $offs  = (array)pxVal('premium_off', []);
    $out = [];
    foreach ($plans as $months => $usd) {
        $usd = (float)$usd;
        if ($usd <= 0) continue;
        $out[(int)$months] = [
            'usd' => $usd,
            'ton' => round($usd / $ton, 2),
            'irt' => round($usd * $irt),
            'off' => (float)($offs[(string)$months] ?? 0),
        ];
    }
    krsort($out);
    return $out;
}

function pxStars($n, $fresh = false) {
    $n = max(1, (float)$n);
    $ton = pxTonUsd($fresh);
    $irt = pxUsdtIrt($fresh);
    if ($ton <= 0 || $irt <= 0) return null;
    $usd = $n * (float)pxVal('star_usd', 0.015);
    return ['n' => $n, 'usd' => $usd, 'ton' => $usd / $ton, 'irt' => round($usd * $irt)];
}

function pxJalali($ts = null) {
    $ts = $ts === null ? time() : (int)$ts;
    $gy = (int)date('Y', $ts); $gm = (int)date('n', $ts); $gd = (int)date('j', $ts);

    $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

    $gy2 = ($gm > 2) ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
          + intdiv($gy2 + 399, 400) + $gd;
    for ($i = 0; $i < $gm - 1; $i++) $days += $gDaysInMonth[$i];

    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $jm = 0;
    for ($i = 0; $i < 12 && $days >= $jDaysInMonth[$i]; $i++) {
        $days -= $jDaysInMonth[$i];
        $jm = $i + 1;
    }
    $jm++; $jd = $days + 1;

    return sprintf('%04d/%02d/%02d | %s', $jy, $jm, $jd, date('H:i:s', $ts));
}

function pxNum($v) {
    $v = (float)$v;
    if ($v == 0.0) return '0';
    $a = abs($v);
    if ($a >= 1000)   return number_format($v, ($v == floor($v)) ? 0 : 2);
    if ($a >= 1)      return rtrim(rtrim(number_format($v, 3), '0'), '.');
    if ($a >= 0.0001) return rtrim(rtrim(number_format($v, 6), '0'), '.');
    return rtrim(rtrim(number_format($v, 8), '0'), '.');
}

function pxToman($v) { return number_format(round((float)$v)); }

function pxKeyRound($v, $unit = 'تومان') {
    $v = (float)$v;
    return $unit === 'تومان' ? (string)round($v) : (string)round($v, 2);
}

function pxQuote($irt, $usd, $ton, $expandable = false) {
    $t  = '<blockquote' . ($expandable ? ' expandable' : '') . '>';
    $t .= pxEm('usd', '💵') . ' ' . pxNum($usd) . "\n";
    $t .= pxEm('toman', '💰') . ' ' . pxToman($irt);
    if ($ton !== null) $t .= "\n" . pxEm('ton', '💎') . ' ' . pxNum($ton);
    $t .= '</blockquote>';
    return $t;
}

function pxPremiumText($fresh = false) {
    $full = pxTplPremium($fresh);
    if ($full !== null) return $full;

    $rows = pxPremiumRows($fresh);
    if (!$rows) return null;

    $t = pxEm('prem', '⭐️') . ' <b>' . h(pxT('prem_head')) . "</b>\n\n";
    foreach ($rows as $months => $d) {
        $label = pxT('prem_month', ['n' => $months]);
        if ($d['off'] > 0) $label .= ' — ' . pxT('prem_off', ['off' => $d['off']]);
        $t .= pxEm('prem', '💎') . ' <b>' . h($label) . "</b>\n";
        $t .= pxQuote($d['irt'], $d['usd'], $d['ton'], true) . "\n";
    }
    $t .= pxFootLine();
    $t .= pxDateLine();
    return $t;
}

function pxFootLine() {
    return pxEm('frag', '🔻') . ' <b>' . h(pxT('foot')) . "</b>\n";
}

function pxDateLine() {
    return '<blockquote>' . pxEm('date', '🕓') . ' ' . h(pxJalali()) . '</blockquote>';
}

function pxTplPremium($fresh = false) {
    $tpl = trim((string)pxT('prem_full'));
    if ($tpl === '') return null;
    $rows = pxPremiumRows($fresh);
    if (!$rows) return null;

    $v = ['date' => pxJalali(), 'usdt' => pxToman(pxUsdtIrt()), 'tonusd' => pxNum(pxTonUsd())];
    foreach ($rows as $m => $d) {
        $v[$m . 'irt'] = pxToman($d['irt']);
        $v[$m . 'ton'] = pxNum($d['ton']);
        $v[$m . 'usd'] = pxNum($d['usd']);
        $v[$m . 'off'] = (string)$d['off'];
    }
    return pxFill($tpl, $v);
}

function pxTplStars($n, $fresh = false) {
    $tpl = trim((string)pxT('star_full'));
    if ($tpl === '') return null;
    $one = pxStars(max(1, (int)$n), $fresh);
    if (!$one) return null;

    $v = ['n' => pxNum($n), 'irt' => pxToman($one['irt']), 'ton' => pxNum($one['ton']),
          'usd' => pxNum($one['usd']), 'date' => pxJalali(),
          'usdt' => pxToman(pxUsdtIrt()), 'tonusd' => pxNum(pxTonUsd())];
    $one1 = pxStars(1, $fresh);
    if ($one1) { $v['each'] = pxToman($one1['irt']); $v['eachton'] = pxNum($one1['ton']); }

    $lines = [];
    foreach (array_map('intval', (array)pxVal('star_packs', [])) as $p) {
        if ($p <= 0) continue;
        $d = pxStars($p);
        if (!$d) continue;
        $lines[] = pxEm('star', '✨') . ' <b>' . number_format($p) . '</b> — ' .
                   pxToman($d['irt']) . ' ' . h(pxT('toman'));
    }
    $v['packs'] = implode("\n", $lines);
    return pxFill($tpl, $v);
}

function pxFill($tpl, array $vars) {
    $map = [];
    foreach ($vars as $k => $val) $map['{' . $k . '}'] = (string)$val;
    return strtr((string)$tpl, $map);
}

function pxStarsText($n = 1, $fresh = false) {
    $full = pxTplStars($n, $fresh);
    if ($full !== null) return $full;

    $one = pxStars($n, $fresh);
    if (!$one) return null;

    $t  = pxEm('star', '⭐️') . ' <b>' . h(pxT('star_head', ['n' => pxNum($n)])) . "</b>\n\n";
    $t .= pxQuote($one['irt'], $one['usd'], $one['ton']) . "\n";

    $packs = array_values(array_filter(array_map('intval', (array)pxVal('star_packs', []))));
    if ($packs) {
        $t .= '<blockquote expandable>';
        $lines = [];
        foreach ($packs as $p) {
            $d = pxStars($p);
            if (!$d) continue;
            $lines[] = pxEm('star', '✨') . ' <b>' . number_format($p) . '</b> — ' .
                       pxToman($d['irt']);
        }
        $t .= implode("\n", $lines) . '</blockquote>' . "\n";
    }

    $t .= pxFootLine();
    $t .= pxDateLine();
    return $t;
}

function pxRatesText($fresh = false) {
    $p = pxFetch($fresh);
    $irt = (float)($p['USDT/IRT'] ?? 0);
    if ($irt <= 0) return null;

    $t = pxEm('card', '📊') . ' <b>' . h(pxT('rates_head')) . "</b>\n\n";
    foreach ((array)pxVal('coins', []) as $sym) {
        $sym = strtoupper(trim((string)$sym));
        $usd = ($sym === 'USDT') ? 1.0 : (float)($p[$sym . '/USDT'] ?? 0);
        if ($usd <= 0) continue;
        $t .= pxEm('conv', '💵') . ' <b>' . h($sym) . "</b>\n";
        $t .= '<blockquote>' . pxToman($usd * $irt) . ' ' . h(pxT('toman')) .
              "\n$" . pxNum($usd) . '</blockquote>';
    }
    $t .= "\n" . pxDateLine();
    return $t;
}

function pxCoinCaption($sym, $usd, $irt, $chg) {
    $t  = pxEm('conv', '🪙') . ' <b>' . h(pxT('coin_head', ['n' => '1', 'sym' => $sym])) . "</b>\n\n";
    $t .= '<blockquote>' . pxEm('usd', '💵') . ' ' . pxNum($usd) . '</blockquote>' . "\n";
    $t .= '<blockquote>' . pxEm('toman', '💰') . ' ' . pxToman($irt) . '</blockquote>' . "\n";
    $t .= '<blockquote>' . pxEm('chg', '📈') . ' ' . ($chg >= 0 ? '+' : '−') .
          number_format(abs($chg), 2) . '%</blockquote>' . "\n";
    $t .= pxDateLine();
    return $t;
}

function pxKeyboard() {
    $rows = [];
    foreach ((array)pxVal('buttons', []) as $b) {
        if (empty($b['on'])) continue;
        $txt = trim((string)($b['text'] ?? ''));
        $url = trim((string)($b['url'] ?? ''));
        if ($txt === '' || $url === '') continue;
        $btn = ['text' => $txt, 'url' => $url];
        if (function_exists('isStyle') && isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
        if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
        $rows[] = [$btn];
    }
    return $rows ? inlineKb($rows) : null;
}

function pxFontList($limit = 40) {
    static $memo = null;
    if ($memo !== null) return $memo;

    $dirs = [
        nbAsset('fonts'),
        rtrim(DATA_DIR, '/') . '/fonts',
        '/usr/share/fonts/truetype/dejavu',
        '/usr/share/fonts/truetype/vazir',
        '/usr/share/fonts/TTF',
        '/usr/share/fonts',
    ];
    $out = []; $seen = [];
    foreach ($dirs as $d) {
        if (!is_dir($d)) continue;
        foreach ((array)glob($d . '/*.[tT][tT][fF]') as $p) {
            $bn = strtolower(basename($p));
            if (!is_readable($p) || isset($seen[$bn])) continue;
            $seen[$bn] = 1;
            $out[$p] = ['name' => basename($p), 'fa' => pxFontHasFa($p)];
            if (count($out) >= $limit) break 2;
        }
    }
    return $memo = $out;
}

function pxFontCacheBust() {
    if (function_exists('maCachePut')) {
        maCachePut('px_font_b', null);      maCachePut('px_font_r', null);
        maCachePut('px_font_b_no', null);   maCachePut('px_font_r_no', null);
    }
    pxFont(true, true);
    pxFont(false, true);
    if (function_exists('bcFontBust')) bcFontBust();
}

function pxFont($bold = true, $bust = false) {
    static $cache = [];
    $k = $bold ? 'b' : 'r';
    if ($bust) { unset($cache[$k]); return ''; }
    if (isset($cache[$k])) return $cache[$k];

    $set = trim((string)pxVal('card.font' . ($bold ? '_bold' : ''), ''));
    if ($set !== '' && is_file($set)) return $cache[$k] = $set;
    $any = trim((string)pxVal('card.font_bold', '')) ?: trim((string)pxVal('card.font', ''));
    if ($any !== '' && is_file($any)) return $cache[$k] = $any;

    $own = nbAsset('fonts/' . ($bold ? 'Vazirmatn-Bold.ttf' : 'Vazirmatn-Regular.ttf'));
    if (is_file($own)) return $cache[$k] = $own;

    $diskKey = 'px_font_' . $k;
    if (function_exists('maCacheGet')) {
        $disk = maCacheGet($diskKey, 2592000);
        if (is_string($disk) && $disk !== '' && is_file($disk) && is_readable($disk))
            return $cache[$k] = $disk;
        if (maCacheGet($diskKey . '_no', 600) !== null) return $cache[$k] = '';
    }

    $names = $bold
        ? ['Vazirmatn-Bold.ttf', 'Vazir-Bold.ttf', 'IRANSansBold.ttf', 'IRANSansXBold.ttf',
           'Sahel-Bold.ttf', 'Shabnam-Bold.ttf', 'Yekan-Bold.ttf',
           'DejaVuSans-Bold.ttf', 'NotoNaskhArabic-Bold.ttf', 'NotoSansArabic-Bold.ttf',
           'LiberationSans-Bold.ttf', 'Roboto-Bold.ttf',
           'arialbd.ttf', 'FreeSansBold.ttf', 'NotoSans-Bold.ttf']
        : ['Vazirmatn-Regular.ttf', 'Vazir.ttf', 'IRANSans.ttf', 'IRANSansX-Regular.ttf',
           'Sahel.ttf', 'Shabnam.ttf', 'Yekan.ttf',
           'DejaVuSans.ttf', 'NotoNaskhArabic-Regular.ttf', 'NotoSansArabic-Regular.ttf',
           'LiberationSans-Regular.ttf', 'Roboto-Regular.ttf',
           'arial.ttf', 'FreeSans.ttf', 'NotoSans-Regular.ttf'];

    $dirs = [
        rtrim(DATA_DIR, '/') . '/fonts',
        nbAsset('fonts'),
        '/usr/share/fonts/truetype/dejavu',
        '/usr/share/fonts/truetype/liberation',
        '/usr/share/fonts/truetype/freefont',
        '/usr/share/fonts/dejavu',
        '/usr/share/fonts/TTF',
        '/usr/local/share/fonts',
        'C:\\Windows\\Fonts',
    ];
    $fallback = '';
    foreach ($dirs as $d) foreach ($names as $n) {
        $f = $d . '/' . $n;
        if (!is_file($f) || !is_readable($f)) continue;
        if ($fallback === '') $fallback = $f;
        if (!pxFontHasFa($f)) continue;
        if (function_exists('maCachePut')) maCachePut($diskKey, $f);
        return $cache[$k] = $f;
    }
    if ($fallback !== '') {
        if (function_exists('maCachePut')) maCachePut($diskKey, $fallback);
        return $cache[$k] = $fallback;
    }

    $any = '';
    foreach (['/usr/share/fonts', '/usr/local/share/fonts', rtrim(DATA_DIR, '/')] as $root) {
        if (!is_dir($root)) continue;
        foreach ([$root . '/*.[tT][tT][fF]', $root . '/*/*.[tT][tT][fF]',
                  $root . '/*/*/*.[tT][tT][fF]'] as $pat) {
            foreach ((glob($pat) ?: []) as $f) {
                if ($any === '') $any = $f;
                if (pxFontHasFa($f)) {
                    if (function_exists('maCachePut')) maCachePut($diskKey, $f);
                    return $cache[$k] = $f;
                }
            }
        }
    }
    if (function_exists('maCachePut')) {
        if ($any !== '') maCachePut($diskKey, $any);
        else             maCachePut($diskKey . '_no', time());
    }
    return $cache[$k] = $any;
}

function pxFontHasFa($file) {
    static $memo = [];
    if (isset($memo[$file])) return $memo[$file];
    if (!is_file($file) || !function_exists('imagettftext')) return $memo[$file] = false;

    $shot = function ($ch) use ($file) {
        $im = imagecreatetruecolor(70, 70);
        imagefilledrectangle($im, 0, 0, 69, 69, imagecolorallocate($im, 255, 255, 255));
        $ok = @imagettftext($im, 34, 0, 8, 52, imagecolorallocate($im, 0, 0, 0), $file, $ch);
        if (!$ok) { imagedestroy($im); return null; }
        $sig = '';
        for ($y = 0; $y < 70; $y += 2)
            for ($x = 0; $x < 70; $x += 2)
                $sig .= ((imagecolorat($im, $x, $y) >> 16) & 255) < 128 ? '1' : '0';
        imagedestroy($im);
        return $sig;
    };

    $miss = $shot("\u{E000}");
    $fa   = $shot('ط');
    if ($miss === null || $fa === null) return $memo[$file] = false;
    if (substr_count($fa, '1') < 8)     return $memo[$file] = false;
    return $memo[$file] = ($fa !== $miss);
}

function pxCardReady() {
    return function_exists('imagecreatetruecolor')
        && function_exists('imagettftext')
        && pxFont(true) !== '';
}

function pxCardWhy() {
    if (!function_exists('imagecreatetruecolor'))
        return 'افزونه‌ی GD روی سرور نصب نیست. از پشتیبانی هاست بخواهید gd را روشن کند.';
    if (!function_exists('imagettftext'))
        return 'GD هست ولی بدون FreeType، پس نمی‌تواند متن بنویسد. از هاست بخواهید gd را با freetype بسازد.';
    if (pxFont(true) === '')
        return 'هیچ فونتی روی سرور پیدا نشد. یک فایل .ttf برای ربات بفرستید تا همین‌جا ذخیره‌اش کند.';
    return '';
}

function pxHex($hex) {
    $hex = ltrim((string)$hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function pxPhotoIdKey($cacheKey) { return 'pxfid_' . substr(md5((string)$cacheKey), 0, 16); }

function pxSendPhotoById($chatId, $fileId, $caption, $markup = null, $replyTo = null, $timeout = 20) {
    $post = [
        'chat_id'    => $chatId,
        'photo'      => $fileId,
        'caption'    => mb_substr((string)$caption, 0, 1024),
        'parse_mode' => 'HTML',
    ];
    if ($markup)  $post['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    if ($replyTo) $post['reply_to_message_id'] = $replyTo;
    return tg(BOT_TOKEN, 'sendPhoto', $post, $timeout);
}

function pxPhotoIdOf($resp) {
    $ph = $resp['result']['photo'] ?? null;
    if (!is_array($ph) || !$ph) return '';
    $last = $ph[count($ph) - 1] ?? [];
    return (string)($last['file_id'] ?? '');
}

function pxSendPhoto($chatId, $bytes, $caption, $markup = null, $replyTo = null, $cacheKey = '') {
    if ($cacheKey !== '') {
        $fid = (string)(maCacheGet(pxPhotoIdKey($cacheKey), 86400) ?? '');
        if ($fid !== '') {
            $r = pxSendPhotoById($chatId, $fid, $caption, $markup, $replyTo);
            if (!empty($r['ok'])) return $r;
            maCachePut(pxPhotoIdKey($cacheKey), '');
        }
    }

    if (!is_string($bytes) || strlen($bytes) < 100)
        return ['ok' => false, 'description' => 'تصویر ساخته نشد'];

    if (function_exists('__tgHook')) {
        $out = __tgHook(BOT_TOKEN, 'sendPhoto',
            emOut('sendPhoto', ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML', 'photo_len' => strlen((string)$bytes)]
                + ($markup ? ['reply_markup' => is_string($markup) ? $markup : json_encode($markup)] : [])));
        if ($cacheKey !== '' && !empty($out['ok'])) {
            $fid = pxPhotoIdOf($out);
            if ($fid !== '') maCachePut(pxPhotoIdKey($cacheKey), $fid);
        }
        return $out;
    }

    $dir = rtrim(DATA_DIR, '/') . '/tmp';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $jpg = substr($bytes, 0, 2) === "\xFF\xD8";
    $tmp = $dir . '/px_' . bin2hex(random_bytes(6)) . ($jpg ? '.jpg' : '.png');

    if (@file_put_contents($tmp, $bytes) === false) {
        $tmp2 = @tempnam(sys_get_temp_dir(), 'px');
        if ($tmp2 === false || @file_put_contents($tmp2, $bytes) === false)
            return ['ok' => false, 'description' => 'جایی برای نوشتن فایل موقت نبود'];
        $tmp = $tmp2;
    }

    $post = [
        'chat_id' => $chatId,
        'caption' => mb_substr((string)$caption, 0, 1024),
        'parse_mode' => 'HTML',
        'photo' => new CURLFile($tmp, $jpg ? 'image/jpeg' : 'image/png', $jpg ? 'card.jpg' : 'card.png'),
    ];
    if ($markup)  $post['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    if ($replyTo) $post['reply_to_message_id'] = $replyTo;
    $post = emOut('sendPhoto', $post);

    $base = defined('TG_API_BASE') ? TG_API_BASE : 'https://api.telegram.org';
    $ch = curl_init($base . '/bot' . BOT_TOKEN . '/sendPhoto');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $res = monCurl($ch, 'telegram');
    $err = curl_error($ch);
    curl_close($ch);
    @unlink($tmp);

    if ($res === false) return ['ok' => false, 'description' => 'curl: ' . $err];
    $out = json_decode((string)$res, true);
    if (!is_array($out)) return ['ok' => false, 'description' => 'پاسخ نامعتبر تلگرام'];

    if ($cacheKey !== '' && !empty($out['ok'])) {
        $fid = pxPhotoIdOf($out);
        if ($fid !== '') maCachePut(pxPhotoIdKey($cacheKey), $fid);
    }
    return $out;
}

function pxDeliver($chatId, $png, $caption, $markup = null, $replyTo = null, $cacheKey = '') {
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];

    if ($png !== null || $cacheKey !== '') {
        $r = pxSendPhoto($chatId, $png, $caption, $markup, $replyTo, $cacheKey);
        if (!empty($r['ok'])) return true;

        $why = (string)($r['description'] ?? 'نامشخص');
        if (function_exists('adminAlertOnce')) {
            adminAlertOnce('px_photo', "⚠️ <b>کارت قیمت فرستاده نشد</b>\n\n" .
                "<code>" . h(mb_substr($why, 0, 200)) . "</code>\n\n" .
                "فعلا همان متن فرستاده می‌شود. اگر ادامه داشت، از\n" .
                "/panel ← 💹 قیمت لحظه‌ای ← 🖼 کارت را خاموش کنید.");
        }
    }

    sendMsg(BOT_TOKEN, $chatId, $caption, $markup, $extra);
    return true;
}

function pxCardFresh($file, $ttl) {
    if (!is_file($file)) return null;
    clearstatcache(true, $file);
    if (time() - (@filemtime($file) ?: 0) > $ttl) return null;
    $raw = @file_get_contents($file);
    return (is_string($raw) && strlen($raw) > 100) ? $raw : null;
}

function pxCardCached($key, callable $fn) {
    $ttl  = max(10, (int)pxVal('card_ttl', 90));
    $file = pxCardFile($key);

    if ($hit = pxCardFresh($file, $ttl)) return $hit;

    $render = function () use ($file, $fn) {
        $png = pxTryCard($fn);
        if (is_string($png) && $png !== '' && strlen($png) > 100) {
            $tmp = $file . '.' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, $png) !== false) @rename($tmp, $file);
            else @unlink($tmp);
            pxCardPrune();
        }
        return $png;
    };

    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $lockFp = @fopen($file . '.lock', 'c');
    if (!$lockFp || !flock($lockFp, LOCK_EX)) {
        if ($lockFp) fclose($lockFp);
        return $render();
    }

    $hit = pxCardFresh($file, $ttl);
    $png = $hit ?? $render();
    flock($lockFp, LOCK_UN);
    fclose($lockFp);
    return $png;
}

function pxCardFile($key) {
    return rtrim(DATA_DIR, '/') . '/pxcards/' . substr(md5((string)$key), 0, 16) . '.jpg';
}

function pxDropCardCache() {
    return maCacheDrop('pxcard_');
}

function pxCardPrune() {
    $dir = rtrim(DATA_DIR, '/') . '/pxcards';
    $mark = $dir . '/.swept';
    if (is_file($mark) && time() - (@filemtime($mark) ?: 0) < 600) return;
    @touch($mark);

    $keep = max(600, (int)pxVal('card_ttl', 90) * 20);
    $now  = time();
    foreach (array_merge((array)@glob($dir . '/*.jpg'), (array)@glob($dir . '/*.png')) as $f) {
        if ($now - (@filemtime($f) ?: $now) > $keep || str_ends_with($f, '.png')) @unlink($f);
    }
}

function pxTryCard(callable $fn) {
    if (empty(pxVal('card.on'))) return null;

    if (($why = pxCardWhy()) !== '') {
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('px_nocard',
                "🖼 <b>کارت قیمت ساخته نمی‌شود</b>\n\n" . h($why) .
                "\n\nپنل ← 💹 قیمت ← 🖼 کارت گرافیکی");
        return null;
    }
    try {
        return $fn();
    } catch (Throwable $e) {
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('px_card', "⚠️ <b>ساخت کارت قیمت شکست خورد</b>\n\n<code>" .
                h(mb_substr($e->getMessage(), 0, 200)) . "</code>");
        return null;
    }
}

function pxWordKind($text) {
    $t = trim(mb_strtolower(norm_fa_digits((string)$text)));
    if ($t === '') return null;
    foreach (['premium', 'stars', 'rates'] as $kind) {
        foreach (explode(',', (string)pxVal('words.' . $kind, '')) as $w) {
            $w = trim(mb_strtolower($w));
            if ($w !== '' && $t === $w) return $kind;
        }
    }
    return null;
}

function pxStarsCount($text) {
    $t = norm_fa_digits((string)$text);
    if (preg_match('/(\d{1,7})/', $t, $m)) return max(1, (int)$m[1]);
    return 1;
}

function pxLatinUnit($unit) {
    $unit = (string)$unit;
    return ['دلار' => 'USD', 'ریال' => 'IRR'][$unit] ?? $unit;
}

function pxConvBody($val, $unit, $usdVal = null, $chg = null) {
    $isT = ($unit === 'تومان');
    $t = '';

    if ($usdVal !== null && $usdVal > 0)
        $t .= '<blockquote>' . pxEm('usd', '💵') . ' ' . pxNum($usdVal) . ' ' . pxLatinUnit('دلار') . '</blockquote>' . "\n";

    $em = $isT ? pxEm('toman', '💰') : (preg_match('/^[A-Z0-9]{1,10}$/', (string)$unit) ? pxEm('coin', '🪙') : pxEm('usd', '💵'));
    $t .= '<blockquote>' . $em . ' ' .
          ($isT ? pxToman($val) : pxNum($val)) .
          ($isT ? '' : ' ' . h(pxLatinUnit($unit))) . '</blockquote>' . "\n";

    if ($chg !== null)
        $t .= '<blockquote>' . pxEm('chg', '📈') . ' ' . ($chg >= 0 ? '+' : '−') .
              number_format(abs($chg), 2) . '%</blockquote>' . "\n";

    $t .= '<blockquote>' . pxEm('date', '🕓') . ' ' . h(pxJalali()) . '</blockquote>';
    return $t;
}

function pxWaitReact($chatId, $msgId, $on = true) {
    if (!$msgId) return;
    if (!$on) { tg(BOT_TOKEN, 'setMessageReaction', ['chat_id' => $chatId, 'message_id' => $msgId, 'reaction' => json_encode([])], 4); return; }

    $tryEmoji = function ($emoji) use ($chatId, $msgId) {
        return tg(BOT_TOKEN, 'setMessageReaction', [
            'chat_id' => $chatId, 'message_id' => $msgId,
            'reaction' => json_encode([['type' => 'emoji', 'emoji' => $emoji]]),
        ], 4);
    };

    $r = $tryEmoji('🐳');
    if (empty($r['ok']) && str_contains((string)($r['description'] ?? ''), 'REACTION_INVALID')) {
        $r = $tryEmoji('✅');
    }
    if (function_exists('adminAlertOnce') && empty($r['ok'])) {
        adminAlertOnce('px_react_fail',
            '⚠️ ری‌اکشنِ «در حالِ ساخت» روی هیچ پیامی نمی‌نشیند:' . "\n" .
            '<code>' . h((string)($r['description'] ?? 'پاسخ نامشخص')) . '</code>' . "\n\n" .
            'هم 🐳 هم ✅ رد شدند — معمولا یعنی توی گروه، Reactions کلا خاموش است — ' .
            'از تنظیمات گروه ← Reactions ببینید روشن است یا نه.');
    }
}

function pxHandleText($text, $chatId, $replyTo = null) {
    if (empty(pxVal('on'))) return false;
    $raw = trim((string)$text);
    if ($raw === '' || mb_strlen($raw) > 40) return false;

    $kb = pxKeyboard();
    $kind = pxWordKind($raw);

    if ($kind === 'premium') {
        $t = pxPremiumText();
        sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? $kb : null,
                $replyTo ? ['reply_to_message_id' => $replyTo] : []);
        return true;
    }
    if ($kind === 'stars') {
        $t = pxStarsText(pxStarsCount($raw));
        sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? $kb : null,
                $replyTo ? ['reply_to_message_id' => $replyTo] : []);
        return true;
    }
    if ($kind === 'rates') {
        $t = pxRatesText();
        sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? $kb : null,
                $replyTo ? ['reply_to_message_id' => $replyTo] : []);
        return true;
    }

    if (preg_match('/^\s*[\d۰-۹٠-٩\.,]+\s*(.+)$/u', $raw, $m)) {
        $k2 = pxWordKind(trim($m[1]));
        if ($k2 === 'stars') {
            $t = pxStarsText(pxStarsCount($raw));
            sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? $kb : null,
                    $replyTo ? ['reply_to_message_id' => $replyTo] : []);
            return true;
        }
    }

    $ak = pxAssetOf($raw);
    if ($ak !== null) return pxSendAsset($ak, $chatId, $replyTo, $kb);

    $cv = pxConvert($raw);
    if ($cv !== null && pxSendConv($cv, $chatId, $replyTo, $kb)) return true;

    $sym = pxCoinSym($raw);
    return $sym !== null && pxSendCoin($sym, $chatId, $replyTo, $kb);
}

function pxChangeOf($pair) {
    $p = pxFetch();
    $pair = strtoupper((string)$pair);
    $base = explode('/', $pair)[0];

    foreach ([$pair . '/CHANGE24', $base . '/CHANGE24', $base . '/CHG', $base . '/CHANGE'] as $k) {
        if (isset($p[$k]) && (float)$p[$k] != 0.0) return (float)$p[$k];
    }

    $now = (float)($p[$pair] ?? 0);
    $old = pxHistAgo($pair);
    if ($now > 0 && $old !== null && $old > 0) {
        $pct = (($now - $old) / $old) * 100;
        if (abs($pct) < 200) return round($pct, 2);
    }
    return 0.0;
}

function pxAssetCaption($name, $price, $unit, $chg, $emoji = '', $key = '') {
    $head = pxHeadEmoji($key, $emoji);
    $isT  = ($unit === 'تومان');

    $t  = $head . ' <b>' . h($name) . "</b>\n\n";
    $t .= '<blockquote>';
    $t .= ($isT ? pxEm('toman', '💰') : pxEm('usd', '💵')) . ' ' .
          pxToman($price) . ($isT ? '' : ' ' . h(pxLatinUnit($unit))) . "\n";
    $t .= pxEm('chg', '📈') . ' ' . ($chg >= 0 ? '+' : '−') .
          number_format(abs($chg), 2) . '%';
    $t .= '</blockquote>' . "\n";
    $t .= pxDateLine();
    return $t;
}

function pxHeadEmoji($key, $fallback = '') {
    $key = (string)$key;
    if (in_array($key, ['gold', 'gold24', 'ounce', 'coin', 'nim', 'rob'], true))
        return pxEm('gold', $fallback !== '' ? $fallback : '🥇');
    if ($key === 'usd') return pxEm('usd', $fallback !== '' ? $fallback : '💵');
    return $fallback !== '' ? $fallback : pxEm('conv', '🪙');
}

function pxAdminHome($chatId, $msgId = null) {
    $c = pxCfg();
    $irt = pxUsdtIrt();
    $ton = pxTonUsd();
    $err = pxLastError();

    $t  = "💹 <b>قیمت لحظه‌ای</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'اتصال: ' . ($irt > 0 ? '✅ برقرار' : '🔴 قطع') . "\n";
    if ($irt > 0) {
        $t .= '💵 دلار: <b>' . pxToman($irt) . "</b> تومان\n";
        $t .= '💎 تون: <b>$' . pxNum($ton) . '</b> · <b>' . pxToman($ton * $irt) . "</b> تومان\n";
        $rows = pxPremiumRows();
        if ($rows) {
            $k = array_key_first($rows);
            $t .= '⭐️ پریمیوم ' . $k . ' ماهه: <b>' . pxToman($rows[$k]['irt']) . "</b> تومان\n";
        }
        $s = pxStars(1);
        if ($s) $t .= '✨ هر استارز: <b>' . pxToman($s['irt']) . "</b> تومان\n";
    }
    $t .= "\n🏦 منبع اول (والکس): " . (!empty($c['wx_on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= "🌐 منبع دوم: " . (trim((string)$c['api']) !== '' ? '✅ تنظیم شده' : '❌ ندارد') . "\n";
    if ($err !== '') $t .= "\n⚠️ آخرین خطا:\n<code>" . h(mb_substr($err, 0, 180)) . "</code>\n";
    $t .= "\n🖼 کارت گرافیکی: " . (!empty($c['card']['on'])
            ? (pxCardReady() ? '✅ روشن' : '⚠️ روشن ولی GD/فونت نیست') : '❌ خاموش') . "\n";
    $t .= '📊 سود روی نرخ: <b>' . $c['margin'] . "٪</b>\n";
    $t .= "\nکلمه‌هایی که جواب می‌گیرند:\n";
    $t .= '• پریمیوم: <code>' . h($c['words']['premium']) . "</code>\n";
    $t .= '• استارز: <code>' . h($c['words']['stars']) . "</code>\n";
    $t .= '• نرخ ارز: <code>' . h($c['words']['rates']) . '</code>';

    $rows = [
        [btnCb(!empty($c['on']) ? '✅ روشن' : '❌ خاموش', 'pxx', 'info'),
         btnCb('🔄 تازه‌سازی', 'pxr', 'confirm')],
        [btnCb('🧪 تست اتصال', 'pxtest', 'confirm')],
        [btnCb(!empty($c['wx_on']) ? '🏦 والکس: روشن' : '🏦 والکس: خاموش', 'pxwx', 'info'),
         btnCb('🌐 آدرس والکس', 'pxwu', 'admin')],
        [btnCb('🔑 کلید API', 'pxk', 'admin'), btnCb('🌐 آدرس API', 'pxu', 'admin')],
        [btnCb('📊 درصد سود', 'pxm', 'admin'), btnCb('⏱ ثانیه کش', 'pxttl', 'admin')],
        [btnCb('🗣 کلمه‌ها', 'pxw_home', 'admin'), btnCb('✏️ متن‌ها', 'pxt_home', 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'pxe_home', 'admin'), btnCb('🔘 دکمه‌ها', 'pxb_home', 'admin')],
        [btnCb(!empty($c['card']['on']) ? '🖼 کارت: روشن' : '🖼 کارت: خاموش', 'pxc', 'info'),
         btnCb('🔤 فونت کارت', 'pxcard', 'admin')],
        [btnCb('🥇 طلا، دلار، سکه', 'pxa_home', 'admin'),
         btnCb('🔎 کلیدهای API', 'pxkeys', 'confirm')],
        [btnCb('👀 پیش‌نمایش پریمیوم', 'pxprev_prem', 'confirm'),
         btnCb('👀 استارز', 'pxprev_star', 'confirm')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function pxLabels() {
    return [
        'texts' => [
            'prem_head'  => 'سرتیتر پریمیوم',
            'prem_month' => 'برچسب هر پلن پریمیوم',
            'prem_off'   => 'برچسب تخفیف پریمیوم',
            'star_head'  => 'سرتیتر استارز',
            'rates_head' => 'سرتیتر نرخ ارز',
            'coin_head'  => 'سرتیتر هر ارز',
            'foot'       => 'امضای پایین پیام',
            'toman'      => 'واژه‌ی تومان',
            'down'       => 'پیام وقتی قیمت نمی‌آید',
            'prem_full'  => '📝 قالب کامل پیام پریمیوم',
            'star_full'  => '📝 قالب کامل پیام استارز',
        ],
        'emoji' => [
            'date'  => '🕓 ایموجی تاریخ',
            'gold'  => '🥇 ایموجی سرِ قالب طلا',
            'usd'   => '💵 ایموجی دلار',
            'toman' => '💰 ایموجی تومان',
            'chg'   => '📈 ایموجی درصد تغییرات',
            'conv'  => '💱 ایموجی سرِ قالبِ ارز و کوین',
            'frag'  => '🔻 ایموجی فرگمنت (پایین قالب)',
            'card'  => 'ایموجی سرتیتر',
            'prem'  => 'ایموجی پریمیوم',
            'star'  => 'ایموجی استارز',
            'ton'   => '💎 ایموجی تون',
            'coin'  => '🪙 ایموجی ارز دیجیتال',
        ],
        'words' => [
            'premium' => 'کلمه‌های پریمیوم',
            'stars'   => 'کلمه‌های استارز',
            'rates'   => 'کلمه‌های نرخ ارز',
        ],
    ];
}

function pxLabel($k, $sec) { return pxLabels()[$sec][$k] ?? $k; }

function pxAdminList($chatId, $msgId, $kind) {
    $c = pxCfg();
    $map = [
        'pxt' => ['✏️ <b>متن‌ها</b>', 'texts', 'pxts_'],
        'pxe' => ['✨ <b>ایموجی پریمیوم</b>', 'emoji', 'pxes_'],
        'pxw' => ['🗣 <b>کلمه‌ها</b>', 'words', 'pxws_'],
    ];
    [$title, $sec, $pre] = $map[$kind] ?? $map['pxt'];

    $t = $title . "\n\n";
    if ($kind === 'pxe') $t .= "کد ایموجی پریمیوم را با /emoji می‌گیرید.\n\n";
    if ($kind === 'pxw') $t .= "کلمه‌ها را با ویرگول جدا کنید.\n\n";

    $rows = [];

    if ($kind === 'pxt') {
        $pf = trim((string)($c['texts']['prem_full'] ?? ''));
        $sf = trim((string)($c['texts']['star_full'] ?? ''));
        $t .= "🧩 <b>قالب کامل</b>\n";
        $t .= '   ' . ($pf !== '' ? '✅' : '⚪️') . " پریمیوم: " .
              ($pf !== '' ? '<code>' . h(mb_substr(str_replace("\n", ' ⏎ ', $pf), 0, 40)) . '</code>' : 'خالی (قالب خودکار)') . "\n";
        $t .= '   ' . ($sf !== '' ? '✅' : '⚪️') . " استارز: " .
              ($sf !== '' ? '<code>' . h(mb_substr(str_replace("\n", ' ⏎ ', $sf), 0, 40)) . '</code>' : 'خالی (قالب خودکار)') . "\n\n";
        $t .= "جای‌گذاری پریمیوم: <code>{3irt}</code> <code>{6irt}</code> <code>{12irt}</code> " .
              "<code>{3ton}</code> <code>{3usd}</code> <code>{3off}</code> <code>{usdt}</code> <code>{date}</code>\n";
        $t .= "جای‌گذاری استارز: <code>{n}</code> <code>{irt}</code> <code>{ton}</code> " .
              "<code>{each}</code> <code>{packs}</code> <code>{usdt}</code> <code>{date}</code>\n\n";
        $rows[] = [btnCb('📝 قالب کامل پریمیوم', 'pxts_prem_full', 'admin'),
                   btnCb('📝 قالب کامل استارز', 'pxts_star_full', 'admin')];
        $rows[] = [btnCb('✨ نمونه‌ی آماده بگذار', 'pxtdemo', 'confirm'),
                   btnCb('🧹 برگرد به قالب خودکار', 'pxtclear', 'danger')];
        $t .= "— — —\n<b>تکه‌های ریز</b> (وقتی قالب کامل خالی است کار می‌کنند):\n";
    }

    foreach (array_keys(pxDefaults()[$sec]) as $k) {
        if ($kind === 'pxt' && in_array($k, ['prem_full', 'star_full'], true)) continue;
        $show = mb_substr(str_replace("\n", ' ', (string)($c[$sec][$k] ?? '')), 0, 30);
        $t .= '• <b>' . h(pxLabel($k, $sec)) . '</b>: <code>' . h($show) . "</code>\n";
        $rows[] = [btnCb(pxLabel($k, $sec), $pre . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3900), inlineKb($rows));
}

function pxOldDemoTpl($which) {
    if ($which === 'prem') {
        return "⭐️ <b>تلگرام پریمیوم</b>\n\n" .
               "<blockquote>💎 <b>۳ ماهه</b>\n{3irt} تومان · {3ton} تون</blockquote>\n" .
               "<blockquote>💎 <b>۶ ماهه</b>\n{6irt} تومان · {6ton} تون</blockquote>\n" .
               "<blockquote>💎 <b>۱۲ ماهه</b>\n{12irt} تومان · {12ton} تون</blockquote>\n\n" .
               "💵 دلار: <b>{usdt}</b> تومان\n🕓 <code>{date}</code>";
    }
    return "✨ <b>{n} استارز</b>\n\n" .
           "<blockquote>💵 <b>{irt}</b> تومان\n💎 {ton} تون</blockquote>\n\n" .
           "<blockquote expandable>{packs}</blockquote>\n" .
           "💵 دلار: <b>{usdt}</b> تومان\n🕓 <code>{date}</code>";
}

function pxDemoTpl($which) {
    $usd   = pxEm('usd', '💵');
    $toman = pxEm('toman', '💰');
    $ton   = pxEm('ton', '💎');
    $date  = pxEm('date', '🕓');

    if ($which === 'prem') {
        $t = pxEm('prem', '⭐️') . " <b>Telegram Premium</b>\n\n";
        foreach ([3, 6, 12] as $m) {
            $t .= '<blockquote>' . pxEm('prem', '💎') . " <b>{$m} months</b>\n" .
                  $usd   . ' {' . $m . "usd}\n" .
                  $toman . ' {' . $m . "irt}\n" .
                  $ton   . ' {' . $m . 'ton}</blockquote>' . "\n";
        }
        return $t . '<blockquote>' . $date . " {date}</blockquote>";
    }

    return pxEm('star', '⭐️') . " <b>{n} STARS</b>\n\n" .
           '<blockquote>' . $usd . " {usd}\n" . $toman . " {irt}\n" . $ton . " {ton}</blockquote>\n" .
           "<blockquote expandable>{packs}</blockquote>\n" .
           '<blockquote>' . $date . ' {date}</blockquote>';
}

function pxDropOldDemo() {
    foreach (['prem' => 'prem_full', 'star' => 'star_full'] as $which => $key) {
        $cur = trim((string)pxT($key));
        if ($cur !== '' && $cur === trim(pxOldDemoTpl($which)))
            pxSet(function (&$c) use ($key) { $c['texts'][$key] = ''; });
    }
}

function pxAdminButtons($chatId, $msgId) {
    $bs = (array)pxVal('buttons', []);
    $t = "🔘 <b>دکمه‌های زیر پیام قیمت</b>\n\n";
    $rows = [];
    foreach ($bs as $i => $b) {
        $t .= ($i + 1) . ') ' . (!empty($b['on']) ? '✅' : '❌') . ' <b>' . h($b['text']) . "</b>\n";
        $t .= '   🔗 ' . ($b['url'] !== '' ? '<code>' . h($b['url']) . '</code>' : '—') . "\n";
        $prev = ['text' => trim((string)$b['text']) ?: '—', 'callback_data' => 'pxb_home'];
        if (function_exists('isStyle') && isStyle($b['color'] ?? '')) $prev['style'] = $b['color'];
        if (trim((string)($b['icon'] ?? '')) !== '') $prev['icon_custom_emoji_id'] = (string)$b['icon'];
        $rows[] = [$prev];
        $rows[] = [
            btnCb(!empty($b['on']) ? '✅' : '❌', 'pxbx_' . $i, 'info'),
            btnCb('✏️ متن', 'pxbt_' . $i, 'admin'),
            btnCb('✨', 'pxbi_' . $i, 'admin'),
            btnCb('🔗', 'pxbu_' . $i, 'admin'),
            btnCb('🎨', 'pxbc_' . $i, 'info'),
        ];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function pxAdminAssets($chatId, $msgId) {
    $t  = "🥇 <b>طلا، دلار، سکه و پول کشورها</b>\n\n";
    $t .= "هر قیمت سه راه دارد و اولی که جواب بدهد برنده است:\n";
    $t .= "۱) کلید جفت‌ارز در همان API اصلی\n";
    $t .= "۲) منبع ایرانی (tgju)\n";
    $t .= "۳) محاسبه از روی انس طلا و دلارِ همان API اصلی\n\n";
    $rows = [];
    foreach (pxAssets() as $k => $a) {
        $v = pxAssetPrice($k);
        $t .= ($v > 0 ? '✅ ' : '⚠️ ') . '<b>' . h($a['name']) . '</b> — ' .
              ($v > 0 ? pxToman($v) . ' ' . h($a['unit']) : '<b>هیچ منبعی نداد</b>') . "\n";
        $rows[] = [btnCb('✏️ ' . mb_substr($a['name'], 0, 14), 'pxan_' . $k, 'admin'),
                   btnCb('🔑 کلید', 'pxap_' . $k, 'admin'),
                   btnCb('🗣 کلمه‌ها', 'pxaw_' . $k, 'admin')];
    }
    $rows[] = [btnCb('🩺 هر قیمت از کجا می‌آید؟', 'pxdiag', 'confirm')];
    $rows[] = [btnCb('📡 آدرس منبع ایرانی', 'pxalturl', 'admin'),
               btnCb('🌍 آدرس نرخ ارز', 'pxfxurl', 'admin')];
    $rows[] = [btnCb('🥇 ضریب طلا', 'pxgk', 'admin'),
               btnCb('🪙 حباب سکه', 'pxck', 'admin')];
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function pxAdminCard($chatId, $msgId) {
    $why = pxCardWhy();
    $t  = "🖼 <b>کارت گرافیکی قیمت</b>\n\n";
    $t .= 'وضعیت: ' . (!empty(pxVal('card.on')) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'GD: ' . (function_exists('imagecreatetruecolor') ? '✅ هست' : '🔴 نیست') . "\n";
    $t .= 'نوشتن متن (FreeType): ' . (function_exists('imagettftext') ? '✅ هست' : '🔴 نیست') . "\n";
    $f = pxFont(true);
    $t .= 'فونت: ' . ($f !== '' ? '✅ <code>' . h($f) . '</code>' : '🔴 پیدا نشد') . "\n\n";

    if ($why !== '') $t .= "⚠️ <b>به‌جای کارت، متن فرستاده می‌شود:</b>\n" . h($why);

    $rows = [
        [btnCb(!empty(pxVal('card.on')) ? '✅ کارت روشن است' : '❌ کارت خاموش است', 'pxc', 'info')],
        [btnCb('📚 انتخابِ فونت', 'pxfontpick', 'admin'),
         btnCb('🔤 فرستادن فونت', 'pxfont', 'admin')],
    ];
    if (trim((string)pxVal('card.font_bold', '')) !== '' || trim((string)pxVal('card.font', '')) !== '')
        $rows[] = [btnCb('🧹 پاک کردن فونتِ دستی', 'pxfontclr', 'danger')];
    $rows[] = [btnCb('🔄 گشتنِ دوباره‌ی فونت', 'pxfontbust', 'admin')];
    $rows[] = [btnCb('👀 نمونه‌ی کارت', 'pxprev_card', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function pxAdminFontPick($chatId, $msgId) {
    $cur  = trim((string)pxVal('card.font_bold', '')) ?: trim((string)pxVal('card.font', ''));
    $auto = (string)pxFont(true);
    $list = pxFontList();

    $t  = "📚 <b>انتخابِ فونتِ کارتِ قیمت</b>\n\n";
    $t .= 'الان: <code>' . h($cur !== '' ? basename($cur) : ($auto !== '' ? basename($auto) . ' (خودکار)' : 'پیدا نشد')) . "</code>\n\n";
    $t .= "<b>فا</b> = حرفِ فارسی دارد";

    $rows = []; $i = 0;
    foreach ($list as $p => $m) {
        $on = ($p === $cur) ? '✅ ' : '';
        $rows[] = [btnCb($on . mb_substr($m['name'], 0, 34) . (!empty($m['fa']) ? ' · فا' : ''), 'pxfp_' . $i, 'info')];
        if (++$i >= 20) break;
    }
    if (!$rows) $t .= "\n\n🔴 هیچ فونتی رویِ سرور پیدا نشد.";

    $rows[] = [btnCb('🔤 فرستادنِ فونتِ تازه', 'pxfont', 'confirm')];
    if ($cur !== '') $rows[] = [btnCb('🧹 برگرد به خودکار', 'pxfontclr', 'reject')];
    $rows[] = [btnCb(UT('back'), 'pxcard', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function pxProbeSources() {
    $c   = pxCfg();
    $to  = max(2, (int)$c['timeout']);
    $out = [];

    $wx = trim((string)($c['wx_url'] ?? ''));
    if ($wx !== '') {
        $t0 = microtime(true);
        [$j, $e] = pxGetMany(['wx' => ['url' => $wx, 'head' => 'Accept: application/json',
                                       'timeout' => $to]], $to)['wx'] ?? [null, 'گرفته نشد'];
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $p  = is_array($j) ? pxWallexPairs($j) : [];
        $out[] = ['name' => 'والکس' . (empty($c['wx_on']) ? ' (خاموش)' : ''),
                  'ok' => (bool)$p, 'n' => count($p), 'ms' => $ms,
                  'err' => is_array($j) ? ($p ? '' : 'پاسخ آمد ولی نمادی نداشت') : (string)$e];
    }

    $secondJob = pxSecondJobs((string)$c['api'], (string)$c['key'], $to)['api'] ?? null;
    if ($secondJob) {
        $t0 = microtime(true);
        [$j, $e] = pxGetMany(['api' => $secondJob], $to)['api'] ?? [null, 'گرفته نشد'];
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $p  = is_array($j) ? pxSecondPairs($j) : [];
        $out[] = ['name' => 'منبع دوم', 'ok' => (bool)$p, 'n' => count($p), 'ms' => $ms,
                  'err' => is_array($j) ? ($p ? '' : 'پاسخ آمد ولی جفت‌ارزی نداشت') : (string)$e];
    }
    return $out;
}

function pxAdminDiag($chatId) {
    $main = pxFetch();
    $alt  = pxAltFetch();
    $fx   = pxFxFetch();

    $t  = "🩺 <b>تشخیص منبع قیمت</b>\n\n";

    foreach (pxProbeSources() as $row) {
        $t .= ($row['ok'] ? '✅' : '🔴') . ' <b>' . h($row['name']) . '</b> — ' .
              ($row['ok'] ? $row['n'] . ' جفت‌ارز · ' . $row['ms'] . ' میلی‌ثانیه'
                          : h(mb_substr($row['err'], 0, 110))) . "\n";
    }
    $t .= "\n" . ($main ? '✅' : '🔴') . ' <b>روی هم</b> — ' . count($main) . " جفت‌ارز\n";
    if (!$main) $t .= '   <code>' . h(mb_substr(pxLastError() ?: 'بی‌پاسخ', 0, 120)) . "</code>\n";
    $t .= ($alt ? '✅' : '🔴') . ' <b>منبع ایرانی</b> — ' .
          ($alt ? h((string)(maCacheGet('px_altsrc', 0) ?: 'زنده')) : 'در دسترس نیست') . "\n";
    if (!$alt) $t .= '   <code>' . h(mb_substr(pxAltError() ?: 'بی‌پاسخ', 0, 120)) . "</code>\n";
    $t .= ($fx ? '✅' : '🔴') . ' <b>نرخ برابری ارز</b> — ' . count($fx) . " ارز\n";
    if (!$fx) $t .= '   <code>' . h(mb_substr(pxFxError() ?: 'بی‌پاسخ', 0, 120)) . "</code>\n";

    $oz = 0.0;
    foreach (['PAXG/USDT', 'XAUT/USDT'] as $pp) { $oz = pxPair($pp); if ($oz > 0) break; }
    $t .= "\n🥇 انس طلا از API اصلی: " . ($oz > 0 ? '<b>$' . pxNum($oz) . '</b>' : '❌ نیست') . "\n";
    if ($oz <= 0)
        $t .= "   بدون انس، طلا فقط از منبع ایرانی می‌آید. اگر API شما\n" .
              "   <code>PAXG/USDT</code> یا <code>XAUT/USDT</code> دارد، چیزی لازم نیست.\n";

    $t .= "\n<b>هر قیمت از کجا:</b>\n";
    foreach (pxAssets() as $k => $a) {
        $v = pxAssetPrice($k);
        $t .= ($v > 0 ? '✅' : '⚠️') . ' ' . h($a['name']) . ': ' .
              ($v > 0 ? pxToman($v) . ' ' . h($a['unit']) . ' — ' . pxAssetSource($k) : 'هیچ‌کدام') . "\n";
    }
    sendMsg(BOT_TOKEN, $chatId, mb_substr($t, 0, 4000));
}

function pxAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'px')) return false;

    if ($data === 'px_home') { answerCb(BOT_TOKEN, $cbId); pxAdminHome($chatId, $msgId); return true; }

    if ($data === 'pxx') {
        pxSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); pxAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'pxc') {
        pxSet(function (&$c) { $c['card']['on'] = empty($c['card']['on']) ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, '✅'); pxAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'pxr') {
        pxFetch(true);
        answerCb(BOT_TOKEN, $cbId, '🔄'); pxAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'pxwx') {
        pxSet(function (&$c) { $c['wx_on'] = empty($c['wx_on']) ? 1 : 0; });
        maCachePut('px_cool', 0);
        pxFetch(true);
        answerCb(BOT_TOKEN, $cbId, '✅'); pxAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'pxtest') {
        answerCb(BOT_TOKEN, $cbId);
        $p = pxFetch(true);
        if (!$p) {
            sendMsg(BOT_TOKEN, $chatId, "🔴 <b>اتصال برقرار نشد</b>\n\n<code>" .
                h(pxLastError() ?: 'بی‌پاسخ') . "</code>\n\nآدرس و کلید API را بررسی کنید.");
        } else {
            $t = "✅ <b>اتصال برقرار است</b>\n\n" . count($p) . " جفت‌ارز آمد:\n\n";
            $i = 0;
            foreach ($p as $k => $v) { if ($i++ >= 12) break; $t .= '• ' . h($k) . ': <code>' . pxNum($v) . "</code>\n"; }
            sendMsg(BOT_TOKEN, $chatId, $t);
        }
        return true;
    }
    if ($data === 'pxprev_card') {
        answerCb(BOT_TOKEN, $cbId);
        if (($why = pxCardWhy()) !== '') {
            sendMsg(BOT_TOKEN, $chatId, "🔴 <b>نمی‌شود ساخت</b>\n\n" . h($why));
            return true;
        }
        $png = pxTryCard(fn() => pxSampleCard());
        if ($png === null) sendMsg(BOT_TOKEN, $chatId, "🔴 ساخت کارت شکست خورد.");
        else pxSendPhoto($chatId, $png, "👆 کارت‌ها همین شکلی می‌روند.");
        return true;
    }
    if ($data === 'pxprev_prem' || $data === 'pxprev_star') {
        answerCb(BOT_TOKEN, $cbId);
        $t = $data === 'pxprev_prem' ? pxPremiumText(true) : pxStarsText(1, true);
        sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? pxKeyboard() : null);
        return true;
    }

    if ($data === 'pxdiag') { answerCb(BOT_TOKEN, $cbId, '🩺'); pxAdminDiag($chatId); return true; }

    if ($data === 'pxcard') { answerCb(BOT_TOKEN, $cbId); pxAdminCard($chatId, $msgId); return true; }
    if ($data === 'pxfontpick') { answerCb(BOT_TOKEN, $cbId); pxAdminFontPick($chatId, $msgId); return true; }
    if (preg_match('/^pxfp_(\d+)$/', $data, $mfp)) {
        $paths = array_keys(pxFontList());
        $p = $paths[(int)$mfp[1]] ?? '';
        if ($p === '') { answerCb(BOT_TOKEN, $cbId, '❌ پیدا نشد', true); return true; }
        $bold = $p;
        foreach (['-Bold', 'Bold', '-bold'] as $suf) {
            $cand = preg_replace('/(-?(Regular|regular))?\.ttf$/i', $suf . '.ttf', $p);
            if ($cand !== $p && is_file($cand)) { $bold = $cand; break; }
        }
        pxSet(function (&$c) use ($p, $bold) { $c['card']['font'] = $p; $c['card']['font_bold'] = $bold; });
        pxFontCacheBust();
        answerCb(BOT_TOKEN, $cbId, '✅ ' . basename($p));
        pxAdminFontPick($chatId, $msgId);
        return true;
    }

    if ($data === 'pxfont') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'px_font', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🔤 <b>فایلِ فونت</b> · <code>.ttf</code> <code>.otf</code>",
            inlineKb([[btnCb('انصراف', 'pxcard', 'cancel')]]));
        return true;
    }
    if ($data === 'pxfontbust') {
        pxFontCacheBust();
        $f = pxFont(true);
        answerCb(BOT_TOKEN, $cbId, $f !== '' ? '✅ ' . basename($f) : '🔴 هنوز پیدا نشد', true);
        pxAdminCard($chatId, $msgId);
        return true;
    }
    if ($data === 'pxfontclr') {
        pxSet(function (&$c) { $c['card']['font'] = ''; $c['card']['font_bold'] = ''; });
        pxFontCacheBust();
        answerCb(BOT_TOKEN, $cbId, '🧹');
        pxAdminCard($chatId, $msgId);
        return true;
    }

    if ($data === 'pxtdemo') {
        pxSet(function (&$c) {
            $c['texts']['prem_full'] = pxDemoTpl('prem');
            $c['texts']['star_full'] = pxDemoTpl('star');
        });
        answerCb(BOT_TOKEN, $cbId, '✨ نمونه گذاشته شد');
        pxAdminList($chatId, $msgId, 'pxt');
        return true;
    }
    if ($data === 'pxtclear') {
        pxSet(function (&$c) { $c['texts']['prem_full'] = ''; $c['texts']['star_full'] = ''; });
        answerCb(BOT_TOKEN, $cbId, '🧹 برگشت به قالب خودکار');
        pxAdminList($chatId, $msgId, 'pxt');
        return true;
    }

    foreach (['pxt_home' => 'pxt', 'pxe_home' => 'pxe', 'pxw_home' => 'pxw'] as $d => $kind) {
        if ($data === $d) { answerCb(BOT_TOKEN, $cbId); pxAdminList($chatId, $msgId, $kind); return true; }
    }
    if ($data === 'pxb_home') { answerCb(BOT_TOKEN, $cbId); pxAdminButtons($chatId, $msgId); return true; }
    if ($data === 'pxa_home') { answerCb(BOT_TOKEN, $cbId); pxAdminAssets($chatId, $msgId); return true; }

    if ($data === 'pxkeys') {
        answerCb(BOT_TOKEN, $cbId);
        $p = pxFetch(true);
        if (!$p) {
            sendMsg(BOT_TOKEN, $chatId, "🔴 چیزی از API نیامد.\n\n<code>" .
                h(pxLastError() ?: 'بی‌پاسخ') . '</code>');
            return true;
        }
        $keys = array_keys($p);
        sort($keys);
        $t = "🔎 <b>کلیدهای API</b> — " . count($keys) . " مورد\n\n";
        $t .= "برای طلا و سکه، کلید درست را از این فهرست بردارید و در\n" .
              "💹 قیمت ← 🥇 طلا، دلار، سکه بگذارید.\n\n<blockquote expandable>";
        foreach (array_slice($keys, 0, 220) as $k)
            $t .= '<code>' . h($k) . '</code> = ' . pxNum($p[$k]) . "\n";
        $t .= '</blockquote>';
        sendMsg(BOT_TOKEN, $chatId, mb_substr($t, 0, 4000));
        return true;
    }

    foreach (['pxan_' => ['px_asname', 'نام فارسی'], 'pxap_' => ['px_aspair', 'کلید جفت‌ارز در API'],
              'pxaw_' => ['px_aswords', 'کلمه‌ها (با ویرگول)'],
              'pxau_' => ['px_asunit', 'واحد']] as $pre => [$act, $label]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        $a = pxAssets()[$k] ?? null;
        if (!$a) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['k' => $k]);
        $cur = ['px_asname' => $a['name'], 'px_aspair' => $a['pair'],
                'px_aswords' => $a['words'], 'px_asunit' => $a['unit']][$act] ?? '';
        sendMsg(BOT_TOKEN, $chatId,
            '✏️ <b>' . $label . "</b>\n\nالان: <code>" . h((string)$cur) . '</code>',
            inlineKb([[btnUI('cancel', 'pxa_home', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'pxbx_')) {
        $i = (int)substr($data, 5);
        pxSet(function (&$c) use ($i) {
            if (isset($c['buttons'][$i])) $c['buttons'][$i]['on'] = empty($c['buttons'][$i]['on']) ? 1 : 0;
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); pxAdminButtons($chatId, $msgId); return true;
    }
    if (str_starts_with($data, 'pxbc_')) {
        $i = (int)substr($data, 5);
        pxSet(function (&$c) use ($i) {
            $seq = ['primary', 'success', 'danger', 'info', 'nav'];
            $cur = $c['buttons'][$i]['color'] ?? 'primary';
            $k = array_search($cur, $seq, true);
            $c['buttons'][$i]['color'] = $seq[(($k === false ? 0 : $k) + 1) % count($seq)];
        });
        answerCb(BOT_TOKEN, $cbId, '🎨'); pxAdminButtons($chatId, $msgId); return true;
    }

    $asks = [
        'pxk'   => ['px_key',  "🔑 <b>کلید API</b>"],
        'pxu'   => ['px_url',  "🌐 <b>آدرس API قیمت</b>"],
        'pxwu'  => ['px_wxurl', "🏦 <b>منبع والکس</b>\n\nپیش‌فرض:\n<code>https://api.wallex.ir/v1/markets</code>"],
        'pxm'   => ['px_marg', "📊 <b>درصد سود روی نرخ بازار</b>"],
        'pxttl' => ['px_ttl',  "⏱ <b>کشِ قیمت (ثانیه)</b>"],
        'pxalturl' => ['px_alturl', "📡 <b>منبع ایرانی (طلا و سکه)</b> · هر خط یک آدرس"],
        'pxfxurl'  => ['px_fxurl',  "🌍 <b>نرخ برابری ارز</b> · هر خط یک آدرس · <code>rates</code>"],
        'pxgk' => ['px_goldk', "🥇 <b>ضریب طلا</b> · <code>1.03</code>"],
        'pxck' => ['px_coink', "🪙 <b>حباب سکه</b> · <code>1.12</code>"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'px_home', 'cancel')]]));
        return true;
    }
    foreach (['pxts_' => ['px_text', 'texts'], 'pxes_' => ['px_emoji', 'emoji'],
              'pxws_' => ['px_word', 'words']] as $pre => [$act, $sec]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!array_key_exists($k, pxDefaults()[$sec])) { answerCb(BOT_TOKEN, $cbId); return true; }
        $cur = (string)(pxVal($sec . '.' . $k) ?? '');
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['k' => $k]);
        $hint = $sec === 'words' ? ' · با ویرگول' : '';
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ <b>" . h(pxLabel($k, $sec)) . "</b>" . $hint . "\n\nالان:\n<code>" .
            h(mb_substr($cur, 0, 500)) . '</code>',
            inlineKb([[btnUI('cancel', 'px_home', 'cancel')]]));
        return true;
    }
    foreach (['pxbt_' => ['px_btntext', '✏️ متن'], 'pxbu_' => ['px_btnurl', '🔗 لینک'], 'pxbi_' => ['px_btnicon', '✨ ایموجی پریمیوم']] as $pre => [$act, $ask]) {
        if (!str_starts_with($data, $pre)) continue;
        $i = (int)substr($data, strlen($pre));
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['i' => $i]);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'pxb_home', 'cancel')]]));
        return true;
    }
    return false;
}

function pxStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'px_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('💹 قیمت لحظه‌ای', 'px_home', 'admin')]]);
    $blank = ($text === '-' || $text === '—');

    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'px_key')  { pxSet(function (&$c) use ($text) { $c['key'] = $text; }); return $done(); }
    if ($action === 'px_url') {
        if ($text !== '' && !preg_match('#^https?://#i', $text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس باید با http شروع شود."); return true;
        }
        pxSet(function (&$c) use ($text) { $c['api'] = $text; });
        maCachePut('px_cool', 0);
        return $done();
    }
    if ($action === 'px_wxurl') {
        if ($text !== '' && !preg_match('#^https?://#i', $text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس باید با http شروع شود."); return true;
        }
        pxSet(function (&$c) use ($text) { $c['wx_url'] = $text; });
        maCachePut('px_cool', 0);
        return $done();
    }
    if ($action === 'px_font') {
        $doc = $msg['document'] ?? null;
        if (!$doc) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایلِ <code>.ttf</code> لازم است");
            return true;
        }
        $name = (string)($doc['file_name'] ?? 'font.ttf');
        if (!preg_match('/\.(ttf|otf)$/i', $name)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط <code>.ttf</code> یا <code>.otf</code>.");
            return true;
        }
        if ((int)($doc['file_size'] ?? 0) > 12 * 1024 * 1024) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل خیلی بزرگ است (بیشتر از ۱۲ مگابایت).");
            return true;
        }
        $r = tg(BOT_TOKEN, 'getFile', ['file_id' => (string)$doc['file_id']]);
        $path = (string)($r['result']['file_path'] ?? '');
        if (empty($r['ok']) || $path === '') {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل از تلگرام گرفته نشد");
            return true;
        }
        [$bytes, $err] = maHttpRaw(TG_API_BASE . '/file/bot' . BOT_TOKEN . '/' . $path, 30, 'telegram');
        if (!is_string($bytes) || strlen($bytes) < 1000) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ دانلود فونت نشد: <code>" . h((string)$err) . '</code>');
            return true;
        }
        $dir = rtrim(DATA_DIR, '/') . '/fonts';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $dst = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        if (@file_put_contents($dst, $bytes) === false) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ نوشتن فایل نشد. پوشه‌ی داده اجازه‌ی نوشتن ندارد.");
            return true;
        }
        pxSet(function (&$c) use ($dst) { $c['card']['font'] = $dst; $c['card']['font_bold'] = $dst; });

        clearState($uid);
        if (($why = pxCardWhy()) !== '') {
            sendMsg(BOT_TOKEN, $chatId, "فونت ذخیره شد ولی هنوز کارت ساخته نمی‌شود:\n" . h($why), $back);
            return true;
        }
        $png = pxTryCard(fn() => pxSampleCard());
        if ($png !== null) {
            pxSendPhoto($chatId, $png, "✅ فونت نشست. کارت‌ها از حالا همین شکلی می‌روند.");
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId, "فونت ذخیره شد ولی ساخت نمونه شکست خورد.", $back);
        }
        return true;
    }

    if ($action === 'px_alturl' || $action === 'px_fxurl') {
        $urls = pxUrlList($text);
        if ($text !== '' && !$blank && !$urls) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ حداقل یک آدرس با http لازم است."); return true;
        }
        $val = $blank ? '' : implode("\n", $urls);
        $key = ($action === 'px_alturl') ? 'alt_url' : 'fx_url';
        pxSet(function (&$c) use ($key, $val) { $c[$key] = $val; });
        foreach (['px_altcool', 'px_alt', 'px_fxcool', 'px_fx'] as $ck) maCachePut($ck, 0);
        $n = ($action === 'px_alturl') ? count(pxAltFetch(true)) : count(pxFxFetch(true));
        return $done($n > 0 ? "✅ ذخیره شد — منبع جواب داد." :
                              "✅ ذخیره شد، ولی هنوز جوابی نیامد:\n<code>" .
                              h(mb_substr(($action === 'px_alturl' ? pxAltError() : pxFxError()), 0, 200)) . '</code>');
    }
    if ($action === 'px_goldk' || $action === 'px_coink') {
        $v = (float)norm_fa_digits(str_replace('٫', '.', $text));
        if ($v < 0.2 || $v > 5) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۰٫۲ تا ۵ باشد."); return true; }
        $key = ($action === 'px_goldk') ? 'gold_k' : 'coin_k';
        pxSet(function (&$c) use ($key, $v) { $c[$key] = $v; });
        return $done();
    }
    if ($action === 'px_marg') {
        $v = (float)norm_fa_digits($text);
        if ($v < -90 || $v > 900) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۹۰- تا ۹۰۰ باشد."); return true; }
        pxSet(function (&$c) use ($v) { $c['margin'] = $v; });
        return $done('✅ درصد سود روی ' . $v . '٪ تنظیم شد.');
    }
    if ($action === 'px_ttl') {
        $v = (int)norm_fa_digits($text);
        if ($v < 1 || $v > 3600) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا ۳۶۰۰ ثانیه."); return true; }
        pxSet(function (&$c) use ($v) { $c['ttl'] = $v; });
        return $done();
    }
    if ($action === 'px_emoji') {
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $v = $blank ? '' : ($ids ? (string)$ids[0] : preg_replace('/\D/', '', norm_fa_digits($text)));
        if (!$blank && $v === '') {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ایموجی پیدا نشد.\n" .
                "<code>-</code>");
            return true;
        }
        $k = (string)($sd['k'] ?? '');
        pxSet(function (&$c) use ($k, $v) { if ($k !== '') $c['emoji'][$k] = $v; });
        return $done();
    }
    if ($action === 'px_text' || $action === 'px_word') {
        $sec = $action === 'px_text' ? 'texts' : 'words';
        $k   = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ چیزی برای ذخیره نیست."); return true; }

        $isTpl = ($sec === 'texts' && in_array($k, ['prem_full', 'star_full'], true));
        if ($isTpl && $blank) {
            pxSet(function (&$c) use ($k) { $c['texts'][$k] = ''; });
            return $done("🧹 پاک شد — دوباره از قالب خودکار استفاده می‌شود.");
        }
        $val = $isTpl ? msgHtml($msg) : $text;
        if (trim($val) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        pxSet(function (&$c) use ($sec, $k, $val) { $c[$sec][$k] = $val; });

        if ($isTpl) {
            clearState($uid);
            $prev = ($k === 'prem_full') ? pxPremiumText(true) : pxStarsText(50, true);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد. پیش‌نمایش:");
            sendMsg(BOT_TOKEN, $chatId, $prev ?? pxT('down'), $prev ? pxKeyboard() : $back);
            if ($prev) sendMsg(BOT_TOKEN, $chatId, '👆 همین می‌رود داخل گروه.', $back);
            return true;
        }
        return $done();
    }
    if ($action === 'px_btntext') {
        $i = (int)($sd['i'] ?? -1);
        [$bt, $icon] = btnTextIn($msg);
        if ($bt === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        pxSet(function (&$c) use ($i, $bt, $icon) {
            if (!isset($c['buttons'][$i])) return;
            $c['buttons'][$i]['text'] = $bt;
            if ($icon !== '') $c['buttons'][$i]['icon'] = $icon;
        });
        return $done('✅');
    }
    if ($action === 'px_btnicon') {
        $i = (int)($sd['i'] ?? -1);
        $ids = customEmojiIds($msg);
        $icon = $blank ? '' : (string)($ids[0] ?? '');
        if (!$blank && $icon === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم"); return true; }
        pxSet(function (&$c) use ($i, $icon) {
            if (isset($c['buttons'][$i])) $c['buttons'][$i]['icon'] = $icon;
        });
        return $done('✅');
    }

    if (str_starts_with($action, 'px_as')) {
        $k = (string)($sd['k'] ?? '');
        $f = ['px_asname' => 'name', 'px_aspair' => 'pair',
              'px_aswords' => 'words', 'px_asunit' => 'unit'][$action] ?? '';
        if ($k === '' || $f === '' || $text === '') {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ مقدار خالی نمی‌شود."); return true;
        }
        if ($f === 'pair') $text = strtoupper(str_replace(' ', '', $text));
        pxSet(function (&$c) use ($k, $f, $text) {
            if (!is_array($c['assets'] ?? null)) $c['assets'] = pxAssetsDefault();
            if (!isset($c['assets'][$k])) $c['assets'][$k] = pxAssetsDefault()[$k] ?? [];
            $c['assets'][$k][$f] = $text;
        });
        $note = '';
        if ($f === 'pair') {
            $v = pxAssetPrice($k, true);
            $note = $v > 0 ? "\n\n✅ با این کلید قیمت آمد: <b>" . pxToman($v) . '</b>'
                           : "\n\n⚠️ با این کلید چیزی نیامد. 🔎 کلیدهای API را ببینید.";
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد." . $note,
                inlineKb([[btnCb('🥇 طلا، دلار، سکه', 'pxa_home', 'admin')]]));
        return true;
    }

    if ($action === 'px_btnurl') {
        $i = (int)($sd['i'] ?? -1);
        $v = $blank ? '' : $text;
        if ($v !== '' && !preg_match('#^https?://#i', $v)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک باید با https شروع شود."); return true;
        }
        pxSet(function (&$c) use ($i, $v) { if (isset($c['buttons'][$i])) $c['buttons'][$i]['url'] = $v; });
        return $done();
    }

    clearState($uid);
    return true;
}

function pxArabicTable() {
    static $t = null;
    if ($t !== null) return $t;
    $t = [
        'ء' => [0xFE80, 0, 0, 0],
        'آ' => [0xFE81, 0xFE82, 0, 0],
        'أ' => [0xFE83, 0xFE84, 0, 0],
        'ؤ' => [0xFE85, 0xFE86, 0, 0],
        'إ' => [0xFE87, 0xFE88, 0, 0],
        'ئ' => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C],
        'ا' => [0xFE8D, 0xFE8E, 0, 0],
        'ب' => [0xFE8F, 0xFE90, 0xFE91, 0xFE92],
        'ة' => [0xFE93, 0xFE94, 0, 0],
        'ت' => [0xFE95, 0xFE96, 0xFE97, 0xFE98],
        'ث' => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C],
        'ج' => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0],
        'ح' => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4],
        'خ' => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8],
        'د' => [0xFEA9, 0xFEAA, 0, 0],
        'ذ' => [0xFEAB, 0xFEAC, 0, 0],
        'ر' => [0xFEAD, 0xFEAE, 0, 0],
        'ز' => [0xFEAF, 0xFEB0, 0, 0],
        'س' => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4],
        'ش' => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8],
        'ص' => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC],
        'ض' => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0],
        'ط' => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4],
        'ظ' => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8],
        'ع' => [0xFEC9, 0xFECA, 0xFECB, 0xFECC],
        'غ' => [0xFECD, 0xFECE, 0xFECF, 0xFED0],
        'ف' => [0xFED1, 0xFED2, 0xFED3, 0xFED4],
        'ق' => [0xFED5, 0xFED6, 0xFED7, 0xFED8],
        'ك' => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC],
        'ل' => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0],
        'م' => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4],
        'ن' => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8],
        'ه' => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC],
        'و' => [0xFEED, 0xFEEE, 0, 0],
        'ي' => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4],
        'پ' => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        'چ' => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        'ژ' => [0xFB8A, 0xFB8B, 0, 0],
        'ک' => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        'گ' => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        'ی' => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
    ];
    return $t;
}

function pxLamAlef() {
    return ['آ' => [0xFEF5, 0xFEF6], 'أ' => [0xFEF7, 0xFEF8],
            'إ' => [0xFEF9, 0xFEFA], 'ا' => [0xFEFB, 0xFEFC]];
}

function pxIsArabic($ch) {
    $c = mb_ord($ch, 'UTF-8');
    return $c !== false && (($c >= 0x0600 && $c <= 0x06FF) || ($c >= 0xFB50 && $c <= 0xFEFF));
}

function pxIsDigitCh($ch) {
    $c = mb_ord($ch, 'UTF-8');
    return $c !== false && (($c >= 0x30 && $c <= 0x39) ||
                            ($c >= 0x0660 && $c <= 0x0669) || ($c >= 0x06F0 && $c <= 0x06F9));
}

function pxShape($text) {
    $text = (string)$text;
    if ($text === '') return '';

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!$chars) return $text;

    $tbl = pxArabicTable();
    $lam = pxLamAlef();

    $out = [];
    $n = count($chars);
    for ($i = 0; $i < $n; $i++) {
        $ch = $chars[$i];
        if (!isset($tbl[$ch])) { $out[] = $ch; continue; }

        $prev = null;
        for ($k = $i - 1; $k >= 0; $k--) { if ($chars[$k] !== "\u{200C}") { $prev = $chars[$k]; break; } }
        $next = null;
        for ($k = $i + 1; $k < $n; $k++) { if ($chars[$k] !== "\u{200C}") { $next = $chars[$k]; break; } }

        $joinBefore = $prev !== null && isset($tbl[$prev]) && $tbl[$prev][2] !== 0;
        $joinAfter  = $next !== null && isset($tbl[$next]);

        if ($ch === 'ل' && $next !== null && isset($lam[$next])) {
            $pair = $lam[$next];
            $out[] = mb_chr($joinBefore ? $pair[1] : $pair[0], 'UTF-8');
            $i++;
            continue;
        }

        $f = $tbl[$ch];
        if ($joinBefore && $joinAfter && $f[3] !== 0)      $g = $f[3];
        elseif ($joinBefore && $f[1] !== 0)                $g = $f[1];
        elseif ($joinAfter && $f[2] !== 0)                 $g = $f[2];
        else                                               $g = $f[0];
        $out[] = mb_chr($g, 'UTF-8');
    }

    $runs = [];
    $cur  = null;
    foreach ($out as $ch) {
        if ($ch === ' ')                       $kind = 'sp';
        elseif (pxIsDigitCh($ch) ||
                in_array($ch, ['.', ',', '٬', '/', '%', '$', '+', '-'], true)) $kind = 'ltr';
        elseif (pxIsArabic($ch))               $kind = 'rtl';
        else                                   $kind = 'ltr';

        if ($cur === null || $cur['k'] !== $kind) {
            if ($cur !== null) $runs[] = $cur;
            $cur = ['k' => $kind, 'buf' => []];
        }
        $cur['buf'][] = $ch;
    }
    if ($cur !== null) $runs[] = $cur;

    $res = '';
    foreach (array_reverse($runs) as $r) {
        $res .= ($r['k'] === 'rtl') ? implode('', array_reverse($r['buf'])) : implode('', $r['buf']);
    }
    return $res;
}

function pxAssetsDefault() {
    $g = ['F5A524', 'C2410C'];
    $c = ['EAB308', '92400E'];
    $out = [
        'usd'  => ['name' => 'دلار آمریکا', 'emoji' => '🇺🇸', 'pair' => 'USDT/IRT', 'code' => 'USD', 'flag' => 'us',
                   'path' => 'current.price_dollar_rl.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['B22234', '3C3B6E'], 'words' => 'دلار,دلار آمریکا,دلار امریکا,usd'],
        'gold' => ['name' => 'طلا ۱۸ عیار', 'emoji' => '🥇', 'pair' => '', 'art' => 'gold',
                   'path' => 'current.geram18.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $g, 'words' => 'طلا,طلا ۱۸ عیار,طلای ۱۸,gold'],
        'gold24'=> ['name' => 'طلا ۲۴ عیار', 'emoji' => '🥇', 'pair' => '', 'art' => 'gold',
                   'path' => 'current.geram24.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $g, 'words' => 'طلا ۲۴,طلای ۲۴,gold24'],
        'ounce'=> ['name' => 'انس جهانی طلا', 'emoji' => '🥇', 'pair' => '', 'art' => 'gold',
                   'path' => 'current.ons.p', 'div' => 1,
                   'unit' => 'دلار', 'bg' => $g, 'words' => 'انس,انس طلا,ounce'],
        'coin' => ['name' => 'سکه امامی', 'emoji' => '🪙', 'pair' => '', 'art' => 'coin',
                   'path' => 'current.sekee.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $c, 'words' => 'سکه,سکه امامی,coin'],
        'nim'  => ['name' => 'نیم سکه', 'emoji' => '🪙', 'pair' => '', 'art' => 'coin',
                   'path' => 'current.nim.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $c, 'words' => 'نیم سکه,نیم'],
        'rob'  => ['name' => 'ربع سکه', 'emoji' => '🪙', 'pair' => '', 'art' => 'coin',
                   'path' => 'current.rob.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $c, 'words' => 'ربع سکه,ربع'],
    ];
    $fx = [
        'eur' => ['یورو', '🇪🇺', 'eu', 'current.price_eur.p', ['003399', '001A4D'], 'یورو,eur'],
        'gbp' => ['پوند انگلیس', '🇬🇧', 'gb', 'current.price_gbp.p', ['012169', '000B26'], 'پوند,پوند انگلیس,gbp'],
        'aed' => ['درهم امارات', '🇦🇪', 'ae', 'current.price_aed.p', ['00732F', '111111'], 'درهم,درهم امارات,aed'],
        'try' => ['لیر ترکیه', '🇹🇷', 'tr', 'current.price_try.p', ['E30A17', '7A0509'], 'لیر,لیر ترکیه,ترکیه,try'],
        'cny' => ['یوان چین', '🇨🇳', 'cn', '', ['DE2910', '7A1408'], 'یوان,یوان چین,cny'],
        'rub' => ['روبل روسیه', '🇷🇺', 'ru', '', ['0039A6', '1C2A5A'], 'روبل,روبل روسیه,rub'],
        'iqd' => ['دینار عراق', '🇮🇶', 'iq', 'current.price_iqd.p', ['CE1126', '5C0810'], 'دینار,دینار عراق,عراق,iqd'],
        'afn' => ['افغانی', '🇦🇫', 'af', 'current.price_afn.p', ['000000', '4A0E0E'], 'افغانی,افغانستان,afn'],
        'sar' => ['ریال عربستان', '🇸🇦', 'sa', 'current.price_sar.p', ['006C35', '00381B'], 'ریال عربستان,عربستان,ریال سعودی,sar'],
        'pkr' => ['روپیه پاکستان', '🇵🇰', 'pk', 'current.price_pkr.p', ['01411C', '00250F'], 'روپیه,روپیه پاکستان,پاکستان,pkr'],
        'inr' => ['روپیه هند', '🇮🇳', 'in', '', ['FF9933', '138808'], 'روپیه هند,هند,inr'],
        'jpy' => ['ین ژاپن', '🇯🇵', 'jp', '', ['BC002D', '5E0016'], 'ین,ین ژاپن,jpy'],
        'krw' => ['وون کره جنوبی', '🇰🇷', 'kr', '', ['003478', 'C60C30'], 'وون,وون کره,وون کره جنوبی,krw'],
        'cad' => ['دلار کانادا', '🇨🇦', 'ca', '', ['D52B1E', '6A150F'], 'دلار کانادا,cad'],
        'aud' => ['دلار استرالیا', '🇦🇺', 'au', '', ['012169', '000B26'], 'دلار استرالیا,aud'],
        'nzd' => ['دلار نیوزیلند', '🇳🇿', 'nz', '', ['012169', '000B26'], 'دلار نیوزیلند,nzd'],
        'chf' => ['فرانک سوئیس', '🇨🇭', 'ch', '', ['D52B1E', '6A150F'], 'فرانک,فرانک سوئیس,chf'],
        'sek' => ['کرون سوئد', '🇸🇪', 'se', '', ['006AA7', '003554'], 'کرون سوئد,sek'],
        'nok' => ['کرون نروژ', '🇳🇴', 'no', '', ['BA0C2F', '00205B'], 'کرون نروژ,nok'],
        'dkk' => ['کرون دانمارک', '🇩🇰', 'dk', '', ['C8102E', '64081A'], 'کرون دانمارک,dkk'],
        'hkd' => ['دلار هنگ کنگ', '🇭🇰', 'hk', '', ['DE2910', '7A1408'], 'دلار هنگ کنگ,hkd'],
        'sgd' => ['دلار سنگاپور', '🇸🇬', 'sg', '', ['EF3340', '771A20'], 'دلار سنگاپور,sgd'],
        'myr' => ['رینگیت مالزی', '🇲🇾', 'my', '', ['010066', 'CC0001'], 'رینگیت,رینگیت مالزی,myr'],
        'thb' => ['بات تایلند', '🇹🇭', 'th', '', ['2D2A4A', 'A51931'], 'بات,بات تایلند,thb'],
        'kwd' => ['دینار کویت', '🇰🇼', 'kw', '', ['007A3D', '003D1E'], 'دینار کویت,kwd'],
        'qar' => ['ریال قطر', '🇶🇦', 'qa', '', ['8A1538', '450A1C'], 'ریال قطر,qar'],
        'omr' => ['ریال عمان', '🇴🇲', 'om', '', ['DB161B', '008000'], 'ریال عمان,omr'],
        'bhd' => ['دینار بحرین', '🇧🇭', 'bh', '', ['CE1126', '67081A'], 'دینار بحرین,bhd'],
        'amd' => ['درام ارمنستان', '🇦🇲', 'am', '', ['D90012', '0033A0'], 'درام,درام ارمنستان,amd'],
        'azn' => ['منات آذربایجان', '🇦🇿', 'az', '', ['0092BC', '00AF66'], 'منات,منات آذربایجان,azn'],
        'gel' => ['لاری گرجستان', '🇬🇪', 'ge', '', ['E8112D', '740816'], 'لاری,لاری گرجستان,gel'],
    ];
    foreach ($fx as $k => [$name, $emoji, $flag, $path, $bg, $words]) {
        $out[$k] = ['name' => $name, 'emoji' => $emoji, 'pair' => '', 'code' => strtoupper($k), 'flag' => $flag,
                    'path' => $path, 'div' => 10, 'unit' => 'تومان', 'bg' => $bg, 'words' => $words];
    }
    return $out;
}

function pxAssets() {
    $def   = pxAssetsDefault();
    $saved = pxVal('assets', null);
    if (!is_array($saved) || !$saved) return $def;

    $out = $def;
    foreach ($saved as $k => $a) {
        if (!is_array($a)) continue;
        $base = $def[$k] ?? ['name' => $k, 'emoji' => '💠', 'pair' => '', 'code' => '', 'path' => '',
                             'div' => 1, 'unit' => 'تومان', 'bg' => ['334155', '0F172A'], 'words' => ''];
        $out[$k] = array_replace($base, $a);
    }
    return $out;
}

function pxCoinColors($sym) {
    $known = [
        'BTC' => ['F7931A', '7C4A03'], 'ETH' => ['627EEA', '2B3A78'],
        'USDT'=> ['26A17B', '0E5C43'], 'TON' => ['0098EA', '00457A'],
        'TRX' => ['EF0027', '7A0015'], 'BNB' => ['F3BA2F', '8A6512'],
        'SOL' => ['9945FF', '4B1F8C'], 'XRP' => ['23292F', '000000'],
        'DOGE'=> ['C2A633', '6B5A18'], 'ADA' => ['0033AD', '001B5C'],
        'NOT' => ['000000', '333333'], 'SHIB'=> ['FFA409', '8A5600'],
        'AVAX'=> ['E84142', '7C1F20'], 'LINK'=> ['2A5ADA', '152E77'],
        'DOT' => ['E6007A', '7A0041'], 'MATIC'=> ['8247E5', '43227A'],
        'LTC' => ['345D9D', '1B3054'], 'PEPE'=> ['3D8130', '1F4318'],
        'ATOM'=> ['2E3148', '16182A'], 'NEAR'=> ['00C08B', '006146'],
        'XLM' => ['14B6E7', '0A5F79'], 'UNI' => ['FF007A', '85003F'],
    ];
    $sym = strtoupper((string)$sym);
    if (isset($known[$sym])) return $known[$sym];

    $h = crc32($sym);
    $hue = $h % 360;
    return [pxHsl($hue, 62, 48), pxHsl($hue, 66, 24)];
}

function pxHsl($h, $s, $l) {
    $h = ($h % 360) / 360; $s /= 100; $l /= 100;
    $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
    $p = 2 * $l - $q;
    $f = function ($t) use ($p, $q) {
        if ($t < 0) $t += 1;
        if ($t > 1) $t -= 1;
        if ($t < 1 / 6) return $p + ($q - $p) * 6 * $t;
        if ($t < 1 / 2) return $q;
        if ($t < 2 / 3) return $p + ($q - $p) * (2 / 3 - $t) * 6;
        return $p;
    };
    return sprintf('%02X%02X%02X',
        (int)round($f($h + 1 / 3) * 255), (int)round($f($h) * 255), (int)round($f($h - 1 / 3) * 255));
}

function pxAssetOf($text) {
    $t = trim(mb_strtolower(norm_fa_digits((string)$text)));
    if ($t === '') return null;
    foreach (pxAssets() as $k => $a) {
        foreach (explode(',', (string)($a['words'] ?? '')) as $w) {
            $w = trim(mb_strtolower(norm_fa_digits($w)));
            if ($w !== '' && $t === $w) return $k;
        }
    }
    return null;
}

function pxAltFetch($fresh = false) {
    static $mem = null;
    if (!$fresh && is_array($mem)) return $mem;

    $urls = pxUrlList(pxVal('alt_url', ''));
    if (!$urls) return $mem = [];

    if (!$fresh && (pxNoNet() || (function_exists('maNoNet') && maNoNet()))) {
        $any = maCacheGet('px_alt', 0);
        return $mem = (is_array($any) ? $any : []);
    }

    $ttl = max(30, (int)pxVal('alt_ttl', 300));
    if (!$fresh) {
        $hit = maCacheGet('px_alt', $ttl);
        if (is_array($hit)) return $mem = $hit;
        if (maCacheGet('px_altcool', 600) !== null)
            return $mem = (array)(maCacheGet('px_alt', 0) ?: []);
    }

    $errs = [];
    foreach ($urls as $u) {
        [$j, $err] = maHttp($u, 'GET',
            "Accept: application/json\nUser-Agent: Mozilla/5.0 (compatible; PriceBot/1.0)",
            '', (int)pxVal('timeout', 6), 'prices');
        if (is_array($j) && $j) {
            maCachePut('px_alterr', '');
            maCachePut('px_altcool', 0);
            maCachePut('px_altsrc', $u);
            maCachePut('px_alt', $j);
            return $mem = $j;
        }
        $errs[] = pxHost($u) . ': ' . ($err ?: 'پاسخی نیامد');
    }
    maCachePut('px_alterr', implode(' · ', $errs));
    maCachePut('px_altcool', time());

    $stale = (array)(maCacheGet('px_alt', 0) ?: []);
    if ($stale && function_exists('adminAlertOnce')) {
        $rawAt = maCacheAt('px_alt');
        $age = $rawAt > 0 ? time() - $rawAt : 0;
        if ($age > 3600) {
            adminAlertOnce('px_alt_stale',
                "⚠️ <b>منبعِ دوم (طلا/سکه/پولِ کشورها) بیش از " . intdiv($age, 3600) . " ساعته جواب نمی‌دهد</b>\n\n" .
                "قیمت‌هایی که الان نشان داده می‌شوند، منجمد و کهنه‌اند.\n" .
                "خطا: <code>" . h(implode(' · ', $errs) ?: '—') . "</code>\n\n" .
                "پنل ← 💹 قیمت ← 🥇 طلا، دلار، سکه — آدرسِ منبعِ دوم را چک کنید.");
        }
    }
    return $mem = $stale;
}

function pxUrlList($raw) {
    $out = [];
    foreach (preg_split('/[\r\n,|]+/', (string)$raw) as $u) {
        $u = trim($u);
        if ($u !== '' && preg_match('#^https?://#i', $u)) $out[] = $u;
    }
    return $out;
}

function pxHost($u) { return (string)(parse_url($u, PHP_URL_HOST) ?: $u); }

function pxFxFetch($fresh = false) {
    static $mem = null;
    if (!$fresh && is_array($mem)) return $mem;

    $urls = pxUrlList(pxVal('fx_url', ''));
    if (!$urls) return $mem = [];

    $ttl = max(300, (int)pxVal('fx_ttl', 3600));
    if (!$fresh) {
        $hit = maCacheGet('px_fx', $ttl);
        if (is_array($hit)) return $mem = $hit;
        if (maCacheGet('px_fxcool', 900) !== null)
            return $mem = (array)(maCacheGet('px_fx', 0) ?: []);
    }

    $to = (int)pxVal('timeout', 6);
    $jobs = [];
    foreach ($urls as $i => $u) $jobs['fx' . $i] = ['url' => $u, 'head' => 'Accept: application/json', 'timeout' => $to];
    $res = pxGetMany($jobs, $to);

    $errs = [];
    foreach ($urls as $i => $u) {
        [$j, $err] = $res['fx' . $i] ?? [null, 'گرفته نشد'];
        $rows = null;
        if (is_array($j)) {
            foreach (['rates', 'conversion_rates', 'usd'] as $k)
                if (isset($j[$k]) && is_array($j[$k])) { $rows = $j[$k]; break; }
        }
        if (is_array($rows) && $rows) {
            $out = [];
            foreach ($rows as $k => $v) if (is_scalar($v) && is_numeric($v) && $v > 0)
                $out[strtoupper((string)$k)] = (float)$v;
            if ($out) {
                maCachePut('px_fxerr', '');
                maCachePut('px_fxcool', 0);
                maCachePut('px_fxsrc', $u);
                maCachePut('px_fx', $out);
                return $mem = $out;
            }
        }
        $errs[] = pxHost($u) . ': ' . ($err ?: 'نرخی نداشت');
    }
    maCachePut('px_fxerr', implode(' · ', $errs));
    maCachePut('px_fxcool', time());
    return $mem = (array)(maCacheGet('px_fx', 0) ?: []);
}

function pxAltError() { return (string)(maCacheGet('px_alterr', 0) ?: ''); }
function pxFxError()  { return (string)(maCacheGet('px_fxerr', 0) ?: ''); }

const PX_OUNCE_G = 31.1034768;

function pxDerive($key, $fresh = false) {
    if (empty(pxVal('derive', 1))) return 0.0;

    $usd = pxUsdtIrt($fresh);
    if ($key === 'usd') return $usd;

    $ounce = 0.0;
    foreach (['PAXG/USDT', 'XAUT/USDT'] as $pair) {
        $v = pxPair($pair, $fresh);
        if ($v > 0) { $ounce = $v; break; }
    }
    if ($key === 'ounce') return $ounce;

    if ($usd <= 0) return 0.0;

    $k24 = ($ounce > 0) ? ($ounce / PX_OUNCE_G * $usd) : 0.0;
    $gk  = (float)pxVal('gold_k', 1.0); if ($gk <= 0) $gk = 1.0;

    switch ($key) {
        case 'gold24': return $k24 * $gk;
        case 'gold':   return $k24 * 0.750 * $gk;
        case 'coin':   return $k24 * 8.133 * 0.900 * $gk * max(0.5, (float)pxVal('coin_k', 1.12));
        case 'nim':    return $k24 * 4.066 * 0.900 * $gk * max(0.5, (float)pxVal('coin_k', 1.12));
        case 'rob':    return $k24 * 2.033 * 0.900 * $gk * max(0.5, (float)pxVal('coin_k', 1.12));
    }

    $a    = pxAssets()[$key] ?? null;
    $code = strtoupper(trim((string)($a['code'] ?? '')));
    if ($code === '' || $code === 'USD') return 0.0;
    $fx = pxFxFetch($fresh);
    $per = (float)($fx[$code] ?? 0);
    return $per > 0 ? $usd / $per : 0.0;
}

function pxAssetPrice($key, $fresh = false) {
    $a = pxAssets()[$key] ?? null;
    if (!$a) return 0.0;

    $pair = strtoupper(trim((string)($a['pair'] ?? '')));
    if ($pair !== '') {
        $p = pxFetch($fresh);
        if (isset($p[$pair]) && $p[$pair] > 0) return (float)$p[$pair];
        if (str_ends_with($pair, '/IRT')) {
            $usdPair = substr($pair, 0, -4) . '/USDT';
            $irt = (float)($p['USDT/IRT'] ?? 0);
            if (isset($p[$usdPair]) && $irt > 0) return (float)$p[$usdPair] * $irt;
        }
    }

    $path = trim((string)($a['path'] ?? ''));
    if ($path !== '') {
        $v = maJsonPath(pxAltFetch($fresh), $path);
        $n = maNum($v);
        if ($n > 0) {
            $div = max(1e-9, (float)($a['div'] ?? 1));
            return $n / $div;
        }
    }

    return max(0.0, pxDerive($key, $fresh));
}

function pxAssetSource($key) {
    $a = pxAssets()[$key] ?? null;
    if (!$a) return '—';
    $pair = strtoupper(trim((string)($a['pair'] ?? '')));
    if ($pair !== '') {
        $p = pxFetch();
        if (!empty($p[$pair])) return 'API ارز دیجیتال';
    }
    $path = trim((string)($a['path'] ?? ''));
    if ($path !== '' && maNum(maJsonPath(pxAltFetch(), $path)) > 0)
        return 'منبع ایرانی (' . h((string)(maCacheGet('px_altsrc', 0) ?: '—')) . ')';
    if (pxDerive($key) > 0) return 'محاسبه‌شده از انس و دلار';
    return '—';
}

function pxAssetChange($key) {
    $a = pxAssets()[$key] ?? null;
    if (!$a) return 0.0;
    $pair = strtoupper(trim((string)($a['pair'] ?? '')));
    if ($pair !== '') {
        $c = pxChangeOf($pair);
        if ($c != 0.0) return $c;
    }
    $path = trim((string)($a['path'] ?? ''));
    if ($path !== '' && str_ends_with($path, '.p')) {
        $dp = maJsonPath(pxAltFetch(), substr($path, 0, -2) . '.dp');
        if ($dp !== null) {
            $n = maNum($dp);
            $dt = (string)maJsonPath(pxAltFetch(), substr($path, 0, -2) . '.dt');
            return ($dt === 'low' || $dt === 'down') ? -abs($n) : $n;
        }
    }

    if (in_array($key, ['gold', 'gold24', 'ounce', 'coin', 'nim', 'rob'], true)) {
        foreach (['PAXG', 'XAUT'] as $sym) {
            $c = pxChangeOf($sym . '/USDT');
            if ($c != 0.0) return $c;
        }
        return 0.0;
    }
    return pxChangeOf('USDT/IRT');
}

const PX_SS = 2;
const PX_W  = 1200;
const PX_H  = 675;

function pxArt($rel) {
    $rel = (string)$rel;
    if ($rel === '' || str_contains($rel, '..')) return '';
    $f = nbAsset('img/') . $rel;
    return is_file($f) ? $f : '';
}

function pxLat($w = 600) {
    static $m = [];
    if (isset($m[$w])) return $m[$w];
    $n = [500 => 'Onest-Medium.ttf', 600 => 'Onest-SemiBold.ttf', 700 => 'Onest-Bold.ttf'][$w] ?? 'Onest-SemiBold.ttf';
    $f = nbAsset('fonts/' . $n);
    return $m[$w] = is_file($f) ? $f : pxFont($w >= 600);
}

function pxCol($im, $hex, $op = 1.0) {
    [$r, $g, $b] = pxHex($hex);
    return imagecolorallocatealpha($im, $r, $g, $b, (int)round(127 * (1 - max(0.0, min(1.0, (float)$op)))));
}

function pxTint($hex, $pct, $onto = 'FFFFFF') {
    [$r, $g, $b]    = pxHex($hex);
    [$r2, $g2, $b2] = pxHex($onto);
    $t = max(0.0, min(1.0, (float)$pct));
    return sprintf('%02X%02X%02X',
        (int)round($r2 + ($r - $r2) * $t),
        (int)round($g2 + ($g - $g2) * $t),
        (int)round($b2 + ($b - $b2) * $t));
}

function pxRowSpan($x1, $x2, $y1, $y2, $r, $y) {
    $dy = 0.0;
    if ($y < $y1 + $r)     $dy = $y1 + $r - $y - 0.5;
    elseif ($y > $y2 - $r) $dy = $y - ($y2 - $r) + 0.5;
    $dx = $dy > 0 ? $r - sqrt(max(0.0, $r * $r - $dy * $dy)) : 0.0;
    return [(int)round($x1 + $dx), (int)round($x2 - $dx)];
}

function pxRRect($im, $x1, $y1, $x2, $y2, $r, $col) {
    $r = max(0, min($r, ($x2 - $x1) / 2, ($y2 - $y1) / 2));
    for ($y = (int)$y1; $y <= $y2; $y++) {
        [$a, $b] = pxRowSpan($x1, $x2, $y1, $y2, $r, $y);
        if ($b >= $a) imageline($im, $a, $y, $b, $y, $col);
    }
}

function pxRRing($im, $x1, $y1, $x2, $y2, $r, $t, $col) {
    for ($y = (int)$y1; $y <= $y2; $y++) {
        [$a, $b] = pxRowSpan($x1, $x2, $y1, $y2, $r, $y);
        if ($y < $y1 + $t || $y > $y2 - $t) { imageline($im, $a, $y, $b, $y, $col); continue; }
        [$c, $d] = pxRowSpan($x1 + $t, $x2 - $t, $y1 + $t, $y2 - $t, max(0, $r - $t), $y);
        if ($c - 1 >= $a) imageline($im, $a, $y, $c - 1, $y, $col);
        if ($b >= $d + 1) imageline($im, $d + 1, $y, $b, $y, $col);
    }
}

function pxInk($font, $size, $text) {
    if ($font === '' || (string)$text === '') return [0.0, 0.0];
    $b = @imagettfbbox($size * PX_SS, 0, $font, (string)$text);
    return $b ? [$b[0] / PX_SS, $b[2] / PX_SS] : [0.0, 0.0];
}

function pxInkW($font, $size, $text) {
    [$l, $r] = pxInk($font, $size, $text);
    return $r - $l;
}

function pxPut($im, $font, $size, $x, $y, $col, $text, $align = 'l') {
    if ($font === '' || (string)$text === '') return 0.0;
    [$l, $r] = pxInk($font, $size, $text);
    $ox = $align === 'r' ? $x - $r : ($align === 'c' ? $x - ($l + $r) / 2 : $x - $l);
    imagettftext($im, $size * PX_SS, 0, (int)round($ox * PX_SS), (int)round($y * PX_SS), $col, $font, (string)$text);
    return $r - $l;
}

function pxFit($font, $size, $text, $room, $min) {
    while ($size > $min && pxInkW($font, $size, $text) > $room) $size -= 1;
    return $size;
}

function pxLoadImg($file) {
    if ($file === '' || !is_file($file)) return null;
    $raw = @file_get_contents($file);
    if (!is_string($raw) || strlen($raw) < 32) return null;
    $dim = @getimagesizefromstring($raw);
    if (!$dim || $dim[0] * $dim[1] > 4000000) return null;
    $im = @imagecreatefromstring($raw);
    if (!$im) return null;
    if (!imageistruecolor($im)) imagepalettetotruecolor($im);
    imagealphablending($im, true);
    imagesavealpha($im, true);
    return $im;
}

function pxDisc($src, $d, $pad = false) {
    $t = imagecreatetruecolor($d, $d);
    imagealphablending($t, false);
    imagesavealpha($t, true);
    $clear = imagecolorallocatealpha($t, 0, 0, 0, 127);
    imagefilledrectangle($t, 0, 0, $d, $d, $clear);
    imagealphablending($t, true);
    if ($pad) {
        imagefilledellipse($t, (int)($d / 2), (int)($d / 2), $d, $d, imagecolorallocate($t, 255, 255, 255));
        $in = (int)round($d * 0.72);
        $o  = (int)round(($d - $in) / 2);
        imagecopyresampled($t, $src, $o, $o, 0, 0, $in, $in, imagesx($src), imagesy($src));
    } else {
        imagecopyresampled($t, $src, 0, 0, 0, 0, $d, $d, imagesx($src), imagesy($src));
    }
    imagealphablending($t, false);
    $r = $d / 2;
    for ($y = 0; $y < $d; $y++) {
        $dy = $y + 0.5 - $r;
        $w  = $r * $r - $dy * $dy;
        $half = $w > 0 ? sqrt($w) : 0;
        $a = (int)floor($r - $half);
        $b = (int)ceil($r + $half) - 1;
        if ($a > 0)      imageline($t, 0, $y, $a - 1, $y, $clear);
        if ($b < $d - 1) imageline($t, $b + 1, $y, $d - 1, $y, $clear);
        if ($half <= 0)  imageline($t, 0, $y, $d - 1, $y, $clear);
    }
    return $t;
}

function pxLogoShape($logo) {
    $s = 24;
    $t = imagecreatetruecolor($s, $s);
    imagealphablending($t, false);
    imagesavealpha($t, true);
    imagefilledrectangle($t, 0, 0, $s, $s, imagecolorallocatealpha($t, 0, 0, 0, 127));
    imagecopyresampled($t, $logo, 0, 0, 0, 0, $s, $s, imagesx($logo), imagesy($logo));
    $in = 0; $holes = 0; $corner = 0;
    $hue = array_fill(0, 24, 0.0); $sum = [];
    $dark = 0; $opaque = 0;
    for ($y = 0; $y < $s; $y++) for ($x = 0; $x < $s; $x++) {
        $c = imagecolorat($t, $x, $y);
        $a = ($c >> 24) & 127;
        $dx = $x + 0.5 - $s / 2; $dy = $y + 0.5 - $s / 2;
        $inside = ($dx * $dx + $dy * $dy) <= ($s / 2 - 1) ** 2;
        if ($inside) { $in++; if ($a > 90) $holes++; }
        elseif (($x < 2 || $x > $s - 3) && ($y < 2 || $y > $s - 3) && $a < 40) $corner++;
        if ($a > 50) continue;
        $opaque++;
        $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
        $mx = max($r, $g, $b); $mn = min($r, $g, $b);
        if ($mx < 70) $dark++;
        $sat = $mx > 0 ? ($mx - $mn) / $mx : 0;
        if ($sat < 0.22 || $mx < 45 || $mn > 235) continue;
        if ($mx == $mn) continue;
        if ($mx == $r)     $h = fmod((($g - $b) / ($mx - $mn)), 6);
        elseif ($mx == $g) $h = (($b - $r) / ($mx - $mn)) + 2;
        else               $h = (($r - $g) / ($mx - $mn)) + 4;
        $bk = ((int)floor(($h < 0 ? $h + 6 : $h) * 4)) % 24;
        $wgt = $sat * ($mx / 255);
        $hue[$bk] += $wgt;
        $sum[$bk] = [($sum[$bk][0] ?? 0) + $r * $wgt, ($sum[$bk][1] ?? 0) + $g * $wgt, ($sum[$bk][2] ?? 0) + $b * $wgt];
    }
    imagedestroy($t);
    arsort($hue);
    $top = (int)array_key_first($hue);
    $brand = '';
    if (($hue[$top] ?? 0) > 1.2 && isset($sum[$top])) {
        $w = $hue[$top];
        $brand = sprintf('%02X%02X%02X', (int)min(255, $sum[$top][0] / $w), (int)min(255, $sum[$top][1] / $w), (int)min(255, $sum[$top][2] / $w));
    } elseif ($opaque > 0) {
        $brand = $dark > $opaque * 0.35 ? '1C1F26' : '5B6475';
    }
    return ['pad' => $in > 0 && $holes / $in > 0.18 && $corner < 3, 'brand' => $brand];
}

function pxCapH($font, $size) {
    static $m = [];
    $k = $font . '|' . $size;
    if (isset($m[$k])) return $m[$k];
    $b = @imagettfbbox($size * PX_SS, 0, $font, '0');
    return $m[$k] = $b ? abs($b[7] - $b[1]) / PX_SS : $size;
}

function pxJpg($im) {
    $out = imagecreatetruecolor(PX_W, PX_H);
    imagecopyresampled($out, $im, 0, 0, 0, 0, PX_W, PX_H, imagesx($im), imagesy($im));
    imagedestroy($im);
    ob_start();
    if (function_exists('imagejpeg')) imagejpeg($out, null, 90);
    else imagepng($out, null, 6);
    $bytes = ob_get_clean();
    imagedestroy($out);
    return $bytes;
}

function pxValueStr($v, $unit) {
    $v = (float)$v;
    if (in_array($unit, ['تومان', 'ریال'], true)) return number_format(round($v));
    return pxNum($v);
}

function pxStable($sym) {
    return in_array(strtoupper((string)$sym), ['USDT', 'USDC', 'DAI', 'USDE', 'FDUSD', 'TUSD', 'PYUSD', 'USDD', 'USD1'], true);
}

function pxAssetLook($key) {
    $a = pxAssets()[$key] ?? [];
    $flag = $key === 'irt' ? 'ir' : strtolower(trim((string)($a['flag'] ?? '')));
    if ($flag !== '' && preg_match('/^[a-z]{2}$/', $flag)) return ['flags/' . $flag . '.jpg', 'flags/' . $flag . '.png'];
    $art = (string)($a['art'] ?? '');
    if ($art === 'gold') return ['gold.jpg', 'gold.png'];
    if ($art === 'coin') return ['gold.jpg', 'coin.png'];
    return ['', ''];
}

function pxTop() {
    static $m = null;
    if ($m !== null) return $m;
    return $m = (array)(load('px_top')['c'] ?? []);
}

function pxGeckoBase($url) {
    return preg_match('#^(https://[a-z0-9.-]*coingecko\.com/api/v3)#i', trim((string)$url), $m) ? $m[1] : '';
}

function pxGeckoExtra() {
    return ['notcoin', 'dogs-2', 'hamster-kombat', 'catizen'];
}

function pxSecondJobs($url, $key, $to) {
    $url = trim((string)$url);
    if ($url === '') return [];
    $base = pxGeckoBase($url);
    if ($base === '') {
        return ['api' => ['url' => $url, 'timeout' => $to,
                          'head' => 'x-api-key: ' . trim((string)$key) . "\nAccept: application/json"]];
    }
    $head = 'Accept: application/json';
    $key  = trim((string)$key);
    if ($key !== '') $head .= "\n" . (str_contains($base, '://pro-api.') ? 'x-cg-pro-api-key: ' : 'x-cg-demo-api-key: ') . $key;
    $q = '/coins/markets?vs_currency=usd&price_change_percentage=24h';
    return [
        'api'  => ['url' => $base . $q . '&order=market_cap_desc&per_page=100&page=1&sparkline=false',
                   'timeout' => max($to, 8), 'head' => $head],
        'api2' => ['url' => $base . $q . '&ids=' . implode(',', pxGeckoExtra()), 'timeout' => $to, 'head' => $head],
    ];
}

function pxGeckoRows($j, array &$meta) {
    $out = [];
    foreach ($j as $r) {
        if (!is_array($r)) continue;
        $sym = strtoupper(trim((string)($r['symbol'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{1,10}$/', $sym) || isset($meta[$sym])) continue;
        $usd = pxToNum($r['current_price'] ?? null);
        if ($usd === null || $usd <= 0) continue;
        if ($sym !== 'USDT') $out[$sym . '/USDT'] = $usd;
        $chg = pxToNum($r['price_change_percentage_24h'] ?? null);
        if ($chg !== null) $out[$sym . '/CHANGE24'] = $chg;
        $img = (string)($r['image'] ?? '');
        $meta[$sym] = [
            'n' => mb_substr(trim((string)($r['name'] ?? $sym)), 0, 40),
            'i' => preg_match('#^https://[a-z0-9.-]*coingecko\.com/#i', $img) ? $img : '',
            'r' => (int)($r['market_cap_rank'] ?? 0),
        ];
    }
    return $out;
}

function pxSecondPairs($j, array &$meta = []) {
    if (!is_array($j)) return [];
    if (isset($j[0]) && is_array($j[0])) return pxGeckoRows($j, $meta);
    return pxSwapPairs($j);
}

function pxGeckoKeep(array $meta) {
    if (count($meta) >= 20) {
        $old = load('px_top');
        if (array_keys((array)($old['c'] ?? [])) !== array_keys($meta) || time() - (int)($old['at'] ?? 0) > 21600)
            save('px_top', ['at' => time(), 'c' => $meta]);
    }
}

function pxLogoKey($sym) {
    return strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string)$sym));
}

function pxLogoFile($sym) {
    $s = pxLogoKey($sym);
    if ($s === '') return '';
    $own = pxArt('coins/' . $s . '.png');
    if ($own !== '') return $own;
    $f = rtrim(DATA_DIR, '/') . '/pxlogos/' . $s . '.png';
    return is_file($f) ? $f : '';
}

function pxLogoSync($max = 4) {
    if (pxNoNet() || (function_exists('maNoNet') && maNoNet()) || !function_exists('curl_multi_init')) return;
    $dir  = rtrim(DATA_DIR, '/') . '/pxlogos';
    $miss = [];
    foreach (pxTop() as $sym => $m) {
        $s   = pxLogoKey($sym);
        $url = (string)($m['i'] ?? '');
        if ($s === '' || $url === '' || pxLogoFile($sym) !== '') continue;
        $no = $dir . '/' . $s . '.no';
        if (is_file($no) && time() - (int)@filemtime($no) < 86400) continue;
        $miss[$s] = $url;
        if (count($miss) >= $max) break;
    }
    if (!$miss) return;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $mh  = curl_multi_init();
    $hs  = [];
    $buf = [];
    foreach ($miss as $s => $u) {
        $buf[$s] = '';
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$buf, $s) {
                if (strlen($buf[$s]) + strlen($data) > 800000) return 0;
                $buf[$s] .= $data;
                return strlen($data);
            },
        ]);
        curl_multi_add_handle($mh, $ch);
        $hs[$s] = $ch;
    }
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.3);
    } while ($running > 0);

    foreach ($hs as $s => $ch) {
        $raw  = $buf[$s];
        monCurlDone($ch, 'prices');
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $dim = strlen($raw) > 100 ? @getimagesizefromstring($raw) : false;
        $im  = ($code === 200 && $dim && $dim[0] > 0 && $dim[1] > 0 && $dim[0] * $dim[1] <= 4000000)
             ? @imagecreatefromstring($raw) : false;
        if (!$im) { @touch($dir . '/' . $s . '.no'); continue; }
        if (!imageistruecolor($im)) imagepalettetotruecolor($im);
        $t = imagecreatetruecolor(192, 192);
        imagealphablending($t, false);
        imagesavealpha($t, true);
        imagefilledrectangle($t, 0, 0, 192, 192, imagecolorallocatealpha($t, 0, 0, 0, 127));
        imagealphablending($t, true);
        imagecopyresampled($t, $im, 0, 0, 0, 0, 192, 192, imagesx($im), imagesy($im));
        imagedestroy($im);
        imagealphablending($t, false);
        $tmp = $dir . '/' . $s . '.' . bin2hex(random_bytes(4));
        if (@imagepng($t, $tmp, 9)) @rename($tmp, $dir . '/' . $s . '.png');
        else @unlink($tmp);
        imagedestroy($t);
    }
    curl_multi_close($mh);
}

function pxUnitOf($word) {
    $w = trim(preg_replace('/\s+/u', ' ', mb_strtolower(norm_fa_digits((string)$word))));
    if ($w === '') return null;
    if (in_array($w, ['تومان', 'تومن', 'تومنی', 'toman', 'irt', 'tmn'], true)) return ['t' => 'irt', 'k' => 'IRT'];
    if (in_array($w, ['ریال', 'rial', 'irr'], true))                            return ['t' => 'irr', 'k' => 'IRR'];
    if (($ak = pxAssetOf($w)) !== null)                                          return ['t' => 'asset', 'k' => $ak];
    if (($sym = pxCoinSym($w)) !== null)                                         return ['t' => 'coin', 'k' => $sym];
    return null;
}

function pxIsToman(array $u) {
    return $u['t'] === 'irt' || $u['t'] === 'irr';
}

function pxUnitDefault(array $u) {
    if (pxIsToman($u)) return ['t' => 'asset', 'k' => 'usd'];
    if ($u['t'] === 'asset' && (pxAssets()[$u['k']]['unit'] ?? '') === 'دلار') return ['t' => 'asset', 'k' => 'usd'];
    return ['t' => 'irt', 'k' => 'IRT'];
}

function pxUnitToman(array $u) {
    if ($u['t'] === 'irt') return 1.0;
    if ($u['t'] === 'irr') return 0.1;
    $irt = pxUsdtIrt();
    if ($u['t'] === 'asset') {
        $a = pxAssets()[$u['k']] ?? null;
        $p = $a ? pxAssetPrice($u['k']) : 0.0;
        if ($p <= 0) return 0.0;
        if (($a['unit'] ?? 'تومان') !== 'دلار') return $p;
        return $irt > 0 ? $p * $irt : 0.0;
    }
    $usd = pxCoinUsd($u['k']);
    return ($usd > 0 && $irt > 0) ? $usd * $irt : 0.0;
}

function pxUnitName(array $u) {
    if ($u['t'] === 'irt') return 'تومان';
    if ($u['t'] === 'irr') return 'ریال';
    if ($u['t'] === 'asset') return $u['k'] === 'usd' ? 'دلار' : (string)(pxAssets()[$u['k']]['name'] ?? $u['k']);
    return (string)$u['k'];
}

function pxUnitTitle(array $u) {
    if ($u['t'] === 'asset') return (string)(pxAssets()[$u['k']]['name'] ?? $u['k']);
    if ($u['t'] === 'coin')  return pxCoinName($u['k']);
    return pxUnitName($u);
}

function pxUnitCode(array $u) {
    if ($u['t'] !== 'asset') return (string)$u['k'];
    $k = (string)$u['k'];
    $g = ['gold' => '18K GOLD', 'gold24' => '24K GOLD', 'ounce' => 'XAU', 'coin' => 'COIN', 'nim' => '1/2 COIN', 'rob' => '1/4 COIN'];
    if (isset($g[$k])) return $g[$k];
    $code = strtoupper(trim((string)(pxAssets()[$k]['code'] ?? '')));
    return $code !== '' ? $code : strtoupper($k);
}

function pxCoinSym($word) {
    static $idx = null;
    $w = trim(preg_replace('/\s+/u', ' ', mb_strtolower(norm_fa_digits((string)$word))));
    $w = str_replace(['ي', 'ك'], ['ی', 'ک'], $w);
    if ($w === '' || mb_strlen($w) > 32) return null;
    if ($idx === null) {
        $idx = [];
        foreach (pxCoinBook() as $sym => [$fa, $al]) {
            foreach (array_merge([$fa], explode(',', $al)) as $x) {
                $x = trim(mb_strtolower($x));
                if ($x !== '' && !isset($idx[$x])) $idx[$x] = $sym;
            }
        }
    }
    if (isset($idx[$w])) return $idx[$w];
    $up = strtoupper($w);
    if (preg_match('/^[A-Z0-9]{2,10}$/', $up)) {
        if ($up === 'USDT' || isset(pxCoinBook()[$up]) || isset(pxTop()[$up])) return $up;
        return isset(pxFetch()[$up . '/USDT']) ? $up : null;
    }
    if (!preg_match('/^[a-z0-9 .-]{3,32}$/', $w)) return null;
    foreach (pxTop() as $sym => $m)
        if (mb_strtolower((string)($m['n'] ?? '')) === $w) return (string)$sym;
    return null;
}

function pxCoinName($sym) {
    $sym = strtoupper((string)$sym);
    return pxCoinBook()[$sym][0] ?? ((string)(pxTop()[$sym]['n'] ?? '') ?: $sym);
}

function pxCoinUsd($sym) {
    $sym = strtoupper((string)$sym);
    if ($sym === 'USDT') return 1.0;
    return (float)(pxFetch()[$sym . '/USDT'] ?? 0);
}

function pxUnitChange(array $u) {
    if ($u['t'] === 'asset') return pxAssetChange($u['k']);
    if ($u['t'] === 'coin')  return pxStable($u['k']) ? pxChangeOf('USDT/IRT') : pxChangeOf($u['k'] . '/USDT');
    return 0.0;
}

function pxConvert($text) {
    $t = trim(mb_strtolower(norm_fa_digits((string)$text)));
    $t = str_replace(['،', ',', '٬'], '', $t);
    if (!preg_match('/^(\d+(?:\.\d+)?)\s*([^\d]+?)(?:\s+(?:به|to|in)\s+([^\d]+))?$/u', $t, $m)) return null;
    $n = (float)$m[1];
    if ($n <= 0 || $n > 1e15) return null;
    $from = pxUnitOf($m[2]);
    if ($from === null) return null;
    $dst = trim((string)($m[3] ?? ''));
    $to  = $dst !== '' ? pxUnitOf($dst) : pxUnitDefault($from);
    if ($to === null || ($from['t'] === $to['t'] && $from['k'] === $to['k'])) return null;
    return ['n' => $n, 'from' => $from, 'to' => $to];
}

function pxOpts($replyTo) {
    return $replyTo ? ['reply_to_message_id' => $replyTo] : [];
}

function pxSendAsset($ak, $chatId, $replyTo, $kb) {
    $a = pxAssets()[$ak];
    $price = pxAssetPrice($ak);
    if ($price <= 0) {
        sendMsg(BOT_TOKEN, $chatId,
            '⚠️ قیمت «' . h($a['name']) . '» در منبع فعلی نیست.' . "\n\n" .
            'کلید <code>' . h($a['pair']) . '</code> در پاسخ API پیدا نشد. ' .
            'از پنل ← 💹 قیمت ← 🔎 کلیدهای API ببینید چه کلیدهایی می‌آید و درستش را ست کنید.',
            null, pxOpts($replyTo));
        return true;
    }
    $chg  = pxAssetChange($ak);
    $unit = (string)$a['unit'];
    $ck   = 'a|' . $ak . '|' . pxKeyRound($price, $unit) . '|' . round($chg, 2) . '|' . botUsername();
    $needsRender = (maCacheGet(pxPhotoIdKey($ck), 86400) ?? '') === '';
    if ($needsRender) pxWaitReact($chatId, $replyTo, true);
    $png = $needsRender
         ? pxCardCached($ck, fn() => pxAssetCardOf($ak, $a['name'], $price, $unit, $unit === 'دلار' ? 'USD' : 'IRT', $chg))
         : null;
    pxDeliver($chatId, $png, pxAssetCaption($a['name'], $price, $unit, $chg, $a['emoji'] ?? '', $ak), $kb, $replyTo, $ck);
    if ($needsRender) pxWaitReact($chatId, $replyTo, false);
    return true;
}

function pxSendCoin($sym, $chatId, $replyTo, $kb) {
    $usd = pxCoinUsd($sym);
    $irt = pxUsdtIrt();
    if ($usd <= 0 || $irt <= 0) return false;

    $stable = pxStable($sym);
    $chg = pxUnitChange(['t' => 'coin', 'k' => $sym]);
    $cap = pxCoinCaption($sym, $usd, $usd * $irt, $chg);
    $val = $stable ? $usd * $irt : $usd;
    $ck  = 'c|' . $sym . '|' . sprintf('%.5g', $val) . '|' . round($chg, 2) . '|' . botUsername();

    $needsRender = (maCacheGet(pxPhotoIdKey($ck), 86400) ?? '') === '';
    if ($needsRender) pxWaitReact($chatId, $replyTo, true);
    $png = $needsRender
         ? pxCardCached($ck, fn() => pxCryptoCard([
               'sym' => $sym, 'title' => $sym . ($stable ? '/IRT' : '/USD'), 'pill' => '24H',
               'value' => $val, 'prefix' => $stable ? '' : '$', 'unit' => $stable ? 'تومان' : '',
               'chg' => $chg,
           ]))
         : null;
    pxDeliver($chatId, $png, $cap, $kb, $replyTo, $ck);
    if ($needsRender) pxWaitReact($chatId, $replyTo, false);
    return true;
}

function pxSendConv(array $cv, $chatId, $replyTo, $kb) {
    $n = (float)$cv['n']; $from = $cv['from']; $to = $cv['to'];
    $a = pxUnitToman($from);
    $b = pxUnitToman($to);
    if ($a <= 0 || $b <= 0) return false;
    $val  = $n * $a / $b;
    $base = !pxIsToman($from);
    $subj = $base ? $from : $to;
    $chg  = pxUnitChange($subj);
    if (!$base) $chg = (1 / (1 + $chg / 100) - 1) * 100;

    $unit  = pxUnitName($to);
    $label = (pxIsToman($from) ? pxToman($n) : pxNum($n)) . ' ' . pxUnitCode($from);
    $usd   = pxUnitToman(['t' => 'asset', 'k' => 'usd']);
    $usdEq = null;
    $isUsd = fn($u) => ($u['t'] === 'asset' && $u['k'] === 'usd') || ($u['t'] === 'coin' && pxStable($u['k']));
    if ($usd > 0 && !$isUsd($from) && !$isUsd($to)) $usdEq = $n * $a / $usd;

    if ($subj['t'] === 'coin') {
        $sym  = (string)$subj['k'];
        $toUsd = $to['t'] === 'asset' && $to['k'] === 'usd';
        $make = fn() => pxCryptoCard([
            'sym' => $sym, 'title' => $sym, 'pill' => $label,
            'value' => $val, 'prefix' => $toUsd ? '$' : '', 'unit' => $toUsd ? '' : $unit,
            'chg' => $chg,
        ]);
    } else {
        $key  = $subj['t'] === 'asset' ? (string)$subj['k'] : 'irt';
        $name = $key === 'irt' ? pxUnitName($subj) : (string)(pxAssets()[$key]['name'] ?? $key);
        $make = fn() => pxAssetCardOf($key, $name, $val, $unit, $label, $chg);
    }

    $ck = 'cv|' . $from['k'] . '|' . $to['k'] . '|' . pxKeyRound($n, '') . '|' . sprintf('%.6g', $val) . '|' .
          round($chg, 2) . '|' . botUsername();
    pxWaitReact($chatId, $replyTo, true);
    $png = pxCardCached($ck, $make);
    $title = (pxIsToman($from) ? pxToman($n) : pxNum($n)) . ' ' . pxUnitTitle($from);
    $cap   = pxEm('conv', '💱') . ' <b>' . h($title) . "</b>\n\n" .
             pxConvBody($val, $unit, $usdEq, $chg);
    pxDeliver($chatId, $png, $cap, $kb, $replyTo);
    pxWaitReact($chatId, $replyTo, false);
    return true;
}

function pxCoinBook() {
    return [
        'BTC'   => ['بیت کوین', 'بیتکوین,بیت,bitcoin'],
        'ETH'   => ['اتریوم', 'اتر,اتریم,ethereum'],
        'USDT'  => ['تتر', 'tether'],
        'XRP'   => ['ریپل', 'ripple'],
        'BNB'   => ['بی ان بی', 'بایننس,بایننس کوین,binance'],
        'SOL'   => ['سولانا', 'سول,solana'],
        'USDC'  => ['یو اس دی سی', 'usd coin'],
        'DOGE'  => ['دوج کوین', 'دوج,دوجکوین,dogecoin'],
        'TRX'   => ['ترون', 'ترکس,tron'],
        'ADA'   => ['کاردانو', 'آدا,cardano'],
        'HYPE'  => ['هایپرلیکوئید', 'هایپر لیکوئید,هایپ,hyperliquid'],
        'LINK'  => ['چین لینک', 'چینلینک,chainlink'],
        'SUI'   => ['سویی', 'سوئی,sui'],
        'XLM'   => ['استلار', 'stellar'],
        'BCH'   => ['بیت کوین کش', 'بیتکوین کش,bitcoin cash'],
        'AVAX'  => ['آوالانچ', 'آواکس,avalanche'],
        'HBAR'  => ['هدرا', 'hedera'],
        'LEO'   => ['لئو', 'unus sed leo'],
        'TON'   => ['تون کوین', 'تون,تونکوین,toncoin'],
        'LTC'   => ['لایت کوین', 'لایتکوین,litecoin'],
        'SHIB'  => ['شیبا', 'شیبا اینو,shiba inu'],
        'DOT'   => ['پولکادات', 'دات,polkadot'],
        'XMR'   => ['مونرو', 'monero'],
        'USDE'  => ['یو اس دی ای', 'ethena usde'],
        'DAI'   => ['دای', 'dai'],
        'BGB'   => ['بیت گت توکن', 'bitget token'],
        'UNI'   => ['یونی سواپ', 'یونی,uniswap'],
        'PEPE'  => ['پپه', 'pepe'],
        'AAVE'  => ['آوه', 'aave'],
        'OKB'   => ['او کی بی', 'okb'],
        'TAO'   => ['بیتنسور', 'bittensor'],
        'NEAR'  => ['نیر', 'نیر پروتکل,near protocol'],
        'APT'   => ['آپتوس', 'aptos'],
        'ICP'   => ['اینترنت کامپیوتر', 'internet computer'],
        'ETC'   => ['اتریوم کلاسیک', 'ethereum classic'],
        'ONDO'  => ['اوندو', 'ondo'],
        'CRO'   => ['کرونوس', 'cronos'],
        'MNT'   => ['منتل', 'mantle'],
        'POL'   => ['پالیگان', 'پولیگان,ماتیک,polygon'],
        'KAS'   => ['کسپا', 'kaspa'],
        'TRUMP' => ['ترامپ', 'آفیشال ترامپ,official trump'],
        'VET'   => ['وی چین', 'vechain'],
        'ENA'   => ['اتنا', 'ethena'],
        'RENDER'=> ['رندر', 'render'],
        'FIL'   => ['فایل کوین', 'فایلکوین,filecoin'],
        'ALGO'  => ['الگوراند', 'algorand'],
        'ATOM'  => ['کازماس', 'اتم,cosmos'],
        'ARB'   => ['آربیتروم', 'arbitrum'],
        'WLD'   => ['ورلد کوین', 'ورلدکوین,worldcoin'],
        'FET'   => ['فچ', 'fetch.ai,artificial superintelligence alliance'],
        'OP'    => ['اپتیمیزم', 'optimism'],
        'JUP'   => ['ژوپیتر', 'jupiter'],
        'INJ'   => ['اینجکتیو', 'injective'],
        'SEI'   => ['سی نتورک', 'sei'],
        'TIA'   => ['سلستیا', 'celestia'],
        'BONK'  => ['بونک', 'bonk'],
        'STX'   => ['استکس', 'stacks'],
        'IMX'   => ['ایمیوتبل', 'immutable'],
        'GRT'   => ['گراف', 'the graph'],
        'FLR'   => ['فلر', 'flare'],
        'QNT'   => ['کوانت', 'quant'],
        'XDC'   => ['ایکس دی سی', 'xdc network'],
        'KCS'   => ['کوکوین توکن', 'kucoin token'],
        'THETA' => ['تتا', 'theta network'],
        'SAND'  => ['سندباکس', 'the sandbox'],
        'MANA'  => ['دسنترالند', 'decentraland'],
        'AXS'   => ['اکسی اینفینیتی', 'axie infinity'],
        'GALA'  => ['گالا', 'gala'],
        'LDO'   => ['لیدو', 'lido dao'],
        'JASMY' => ['جسمی', 'jasmycoin'],
        'FLOKI' => ['فلوکی', 'floki'],
        'WIF'   => ['داگ ویف هت', 'dogwifhat'],
        'NEXO'  => ['نکسو', 'nexo'],
        'CAKE'  => ['پنکیک سواپ', 'pancakeswap'],
        'PENGU' => ['پادجی پنگوئن', 'پنگو,pudgy penguins'],
        'VIRTUAL' => ['ویرچوال', 'virtuals protocol'],
        'EOS'   => ['ایاس', 'eos'],
        'XTZ'   => ['تزوس', 'tezos'],
        'FLOW'  => ['فلو', 'flow'],
        'NEO'   => ['نئو', 'neo'],
        'IOTA'  => ['آیوتا', 'iota'],
        'MKR'   => ['میکر', 'maker'],
        'SKY'   => ['اسکای', 'sky'],
        'CRV'   => ['کرو', 'curve dao token'],
        'RAY'   => ['ریدیوم', 'raydium'],
        'PYTH'  => ['پایت', 'pyth network'],
        'ZEC'   => ['زی کش', 'zcash'],
        'DASH'  => ['دش', 'dash'],
        'BTT'   => ['بیت تورنت', 'bittorrent'],
        'CFX'   => ['کانفلاکس', 'conflux'],
        'EGLD'  => ['مولتی ورس ایکس', 'الروند,multiversx'],
        'KAIA'  => ['کایا', 'kaia'],
        'AR'    => ['آرویو', 'arweave'],
        'PAXG'  => ['پکس گلد', 'pax gold'],
        'XAUT'  => ['تتر گلد', 'tether gold'],
        'GT'    => ['گیت توکن', 'gatetoken'],
        '1INCH' => ['وان اینچ', '1inch'],
        'COMP'  => ['کامپاند', 'compound'],
        'SNX'   => ['سینتتیکس', 'synthetix'],
        'ENS'   => ['ای ان اس', 'ethereum name service'],
        'CHZ'   => ['چیلیز', 'chiliz'],
        'APE'   => ['ایپ کوین', 'apecoin'],
        'RUNE'  => ['تورچین', 'thorchain'],
        'BERA'  => ['براچین', 'berachain'],
        'S'     => ['سونیک', 'sonic'],
        'MOVE'  => ['موومنت', 'movement'],
        'IP'    => ['استوری پروتکل', 'story protocol'],
        'W'     => ['ورم هول', 'wormhole'],
        'STRK'  => ['استارک نت', 'starknet'],
        'ZK'    => ['زی کی سینک', 'zksync'],
        'PNUT'  => ['پینات', 'peanut the squirrel'],
        'BSV'   => ['بیت کوین اس وی', 'bitcoin sv'],
        'XEC'   => ['ای کش', 'ecash'],
        'MATIC' => ['ماتیک', 'matic'],
        'NOT'   => ['نات کوین', 'نات,ناتکوین,notcoin'],
        'DOGS'  => ['داگز', 'dogs'],
        'HMSTR' => ['همستر', 'همستر کامبت,hamster kombat'],
        'CATI'  => ['کتیزن', 'catizen'],
        'GRAM'  => ['گرام', 'gram'],
        'MEME'  => ['میم کوین', 'memecoin'],
        'SSV'   => ['اس اس وی', 'ssv network'],
        'GMX'   => ['جی ام ایکس', 'gmx'],
        'DYDX'  => ['دی وای دی ایکس', 'dydx'],
        'SUSHI' => ['سوشی', 'sushi'],
        'BAT'   => ['بیسیک اتنشن توکن', 'basic attention token'],
        'ZIL'   => ['زیلیکا', 'zilliqa'],
        'ONE'   => ['هارمونی', 'harmony'],
        'CELO'  => ['سلو', 'celo'],
        'KSM'   => ['کوزاما', 'kusama'],
        'ROSE'  => ['اوسیس', 'oasis'],
        'MINA'  => ['مینا', 'mina'],
        'AGIX'  => ['سینگولاریتی نت', 'singularitynet'],
    ];
}

function pxGem($im, $cx, $cy, $w, $hex, $op = 1.0) {
    $S = PX_SS;
    $h = $w * 0.86;
    $T = $cy - $h * 0.42; $G = $cy - $h * 0.10; $B = $cy + $h * 0.52;
    $TL = [$cx - $w * 0.30, $T]; $TC = [$cx, $T]; $TR = [$cx + $w * 0.30, $T];
    $GL = [$cx - $w * 0.5, $G]; $G1 = [$cx - $w * 0.17, $G]; $G2 = [$cx + $w * 0.17, $G]; $GR = [$cx + $w * 0.5, $G];
    $P  = [$cx, $B];
    $faces = [
        [[$GL, $TL, $G1], 0.80], [[$TL, $TC, $G1], 0.92], [[$TC, $G2, $G1], 1.00],
        [[$TC, $TR, $G2], 0.86], [[$TR, $GR, $G2], 0.66],
        [[$GL, $G1, $P], 0.56],  [[$G1, $G2, $P], 0.74],  [[$G2, $GR, $P], 0.42],
    ];
    foreach ($faces as [$pts, $k]) {
        $flat = [];
        foreach ($pts as [$x, $y]) { $flat[] = (int)round($x * $S); $flat[] = (int)round($y * $S); }
        $c = $k >= 0.75 ? pxTint($hex, 1 - ($k - 0.75) * 2, 'FFFFFF') : pxTint($hex, 0.4 + $k * 0.8, '000000');
        imagefilledpolygon($im, $flat, pxCol($im, $c, $op));
    }
    $edge = pxCol($im, 'FFFFFF', 0.55 * $op);
    imagesetthickness($im, max(1, (int)round($w * 0.018 * $S)));
    foreach ([[$GL, $GR], [$TL, $TR]] as [$a, $b])
        imageline($im, (int)round($a[0] * $S), (int)round($a[1] * $S), (int)round($b[0] * $S), (int)round($b[1] * $S), $edge);
    imagesetthickness($im, 1);
    if ($op >= 0.5) {
        $sx = $cx - $w * 0.13; $sy = $T + $h * 0.10; $r = $w * 0.10;
        $star = pxCol($im, 'FFFFFF', 0.9);
        imagefilledpolygon($im, [
            (int)round($sx * $S), (int)round(($sy - $r) * $S), (int)round(($sx + $r * 0.22) * $S), (int)round(($sy - $r * 0.22) * $S),
            (int)round(($sx + $r) * $S), (int)round($sy * $S), (int)round(($sx + $r * 0.22) * $S), (int)round(($sy + $r * 0.22) * $S),
            (int)round($sx * $S), (int)round(($sy + $r) * $S), (int)round(($sx - $r * 0.22) * $S), (int)round(($sy + $r * 0.22) * $S),
            (int)round(($sx - $r) * $S), (int)round($sy * $S), (int)round(($sx - $r * 0.22) * $S), (int)round(($sy - $r * 0.22) * $S),
        ], $star);
    }
}

function pxIsFa($text) {
    return (bool)preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', (string)$text);
}

function pxSayFont($text, $fa = '') {
    return pxIsFa($text) ? ($fa !== '' ? $fa : pxFont(true)) : pxLat(700);
}

function pxSayW($size, $text, $fa = '') {
    $t = (string)$text;
    return pxInkW(pxSayFont($t, $fa), $size, pxIsFa($t) ? pxShape($t) : $t);
}

function pxSayFit($size, $text, $room, $min, $fa = '') {
    while ($size > $min && pxSayW($size, $text, $fa) > $room) $size -= 1;
    return $size;
}

function pxSay($im, $size, $x, $y, $col, $text, $align = 'l', $fa = '') {
    $t = (string)$text;
    return pxPut($im, pxSayFont($t, $fa), $size, $x, $y, $col, pxIsFa($t) ? pxShape($t) : $t, $align);
}

function pxAvatar($im, $file, $cx, $cy, $d, $ringHex = '', $ring = 0, $label = '', $fillHex = '2563EB', $fa = '') {
    $S = PX_SS;
    if ($ringHex !== '' && $ring > 0)
        imagefilledellipse($im, (int)round($cx * $S), (int)round($cy * $S), (int)round(($d + 2 * $ring) * $S), (int)round(($d + 2 * $ring) * $S), pxCol($im, $ringHex));
    $D   = (int)round($d * $S);
    $src = $file ? pxLoadImg($file) : null;
    if ($src) {
        $sw = imagesx($src); $sh = imagesy($src); $side = min($sw, $sh);
        $sq = imagecreatetruecolor($side, $side);
        imagecopy($sq, $src, 0, 0, (int)(($sw - $side) / 2), (int)(($sh - $side) / 2), $side, $side);
        imagedestroy($src);
        $disc = pxDisc($sq, $D);
        imagedestroy($sq);
        imagecopy($im, $disc, (int)round(($cx - $d / 2) * $S), (int)round(($cy - $d / 2) * $S), 0, 0, $D, $D);
        imagedestroy($disc);
        return;
    }
    imagefilledellipse($im, (int)round($cx * $S), (int)round($cy * $S), $D, $D, pxCol($im, $fillHex));
    $ini = mb_substr(trim((string)$label), 0, 1, 'UTF-8');
    if ($ini === '' || !preg_match('/[\p{L}\p{N}]/u', $ini)) { pxGem($im, $cx, $cy + $d * 0.02, $d * 0.5, 'FFFFFF', 0.95); return; }
    $f  = pxSayFont($ini, $fa);
    $sz = $d * 0.40;
    pxPut($im, $f, $sz, $cx, $cy + pxCapH($f, $sz) / 2, pxCol($im, 'FFFFFF'), pxIsFa($ini) ? pxShape($ini) : mb_strtoupper($ini, 'UTF-8'), 'c');
}

const PX_PANEL = [180, 98, 1020, 577, 36];

function pxBlobs(array $blobs, $soft, $gain) {
    $gw = 300; $gh = 169;
    $t = imagecreatetruecolor($gw, $gh);
    $kx = PX_W / $gw; $ky = PX_H / $gh;
    $cols = [];
    foreach ($blobs as $b) $cols[] = pxHex($b[3]);
    for ($y = 0; $y < $gh; $y++) {
        $uy = ($y + 0.5) * $ky;
        for ($x = 0; $x < $gw; $x++) {
            $ux = ($x + 0.5) * $kx;
            $r = 7.0; $g = 8.0; $b = 10.0;
            foreach ($blobs as $i => [$cx, $cy, $rad]) {
                $d = hypot($ux - $cx, $uy - $cy);
                $e = ($rad + $soft - $d) / (2 * $soft);
                if ($e <= 0) continue;
                $e = $e >= 1 ? 1.0 : $e * $e * (3 - 2 * $e);
                $a = $e * $gain;
                $r += ($cols[$i][0] - $r) * $a; $g += ($cols[$i][1] - $g) * $a; $b += ($cols[$i][2] - $b) * $a;
            }
            imagesetpixel($t, $x, $y, imagecolorallocate($t, (int)$r, (int)$g, (int)$b));
        }
    }
    $S = PX_SS;
    $out = function_exists('imagescale') ? imagescale($t, PX_W * $S, PX_H * $S, IMG_BILINEAR_FIXED) : false;
    if (!$out) {
        $out = imagecreatetruecolor(PX_W * $S, PX_H * $S);
        imagecopyresampled($out, $t, 0, 0, 0, 0, PX_W * $S, PX_H * $S, $gw, $gh);
    }
    imagedestroy($t);
    return $out;
}

function pxBase($hexA = '12C488', $hexB = '2F72DF') {
    $dir = DATA_DIR . '/cards';
    $f   = $dir . '/_base_v1_' . md5((string)$hexA . '|' . (string)$hexB) . '.png';
    if (is_file($f) && ($im = @imagecreatefrompng($f)) && imagesx($im) === PX_W * PX_SS && imagesy($im) === PX_H * PX_SS) {
        imagealphablending($im, true);
        return $im;
    }
    $im = pxBaseDraw($hexA, $hexB);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $tmp = $f . '.' . bin2hex(random_bytes(4));
    if (@imagepng($im, $tmp, 1)) @rename($tmp, $f);
    else @unlink($tmp);
    return $im;
}

function pxBaseDraw($hexA, $hexB) {
    $S = PX_SS;
    [$x1, $y1, $x2, $y2, $rad] = PX_PANEL;
    $blobs = [[292, 202, 182, $hexA], [967, 520, 182, $hexB]];
    $im = pxBlobs($blobs, 7, 1.0);
    $bl = pxBlobs($blobs, 95, 0.9);
    imagealphablending($im, true);
    for ($y = $y1 * $S; $y <= $y2 * $S; $y++) {
        [$a, $b] = pxRowSpan($x1 * $S, $x2 * $S, $y1 * $S, $y2 * $S, $rad * $S, $y);
        if ($b >= $a) imagecopy($im, $bl, $a, $y, $a, $y, $b - $a + 1, 1);
    }
    imagedestroy($bl);
    pxRRect($im, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, $rad * $S, pxCol($im, '141518', 0.52));
    pxRRing($im, $x1 * $S, $y1 * $S, $x2 * $S, $y2 * $S, $rad * $S, 3, pxCol($im, 'FFFFFF', 0.14));
    return $im;
}

function pxTag($im, $x, $yc, $text, $tone = 'gray', $align = 'r', $fa = '') {
    $S = PX_SS;
    [$ink, $line, $fill] = [
        'green' => ['9CF0CF', '2E9F78', '10352A'],
        'red'   => ['FFB3C1', 'B8465F', '3A1720'],
        'blue'  => ['B9D3FF', '3F6FC7', '14254A'],
        'gold'  => ['FFE3A1', 'B8913A', '3A2E12'],
    ][$tone] ?? ['F2F2F4', '5A5B60', '1E1F22'];
    $t    = (string)$text;
    $isFa = pxIsFa($t);
    $font = $isFa ? ($fa !== '' ? $fa : pxFont(true)) : pxLat(600);
    $str  = $isFa ? pxShape($t) : $t;
    $h    = 34; $size = $isFa ? 15 : 14.5;
    $w    = pxInkW($font, $size, $str) + 36;
    $a    = $align === 'r' ? $x - $w : $x;
    pxRRect($im, $a * $S, ($yc - $h / 2) * $S, ($a + $w) * $S, ($yc + $h / 2) * $S, ($h / 2) * $S, pxCol($im, $line));
    pxRRect($im, ($a + 1.5) * $S, ($yc - $h / 2 + 1.5) * $S, ($a + $w - 1.5) * $S, ($yc + $h / 2 - 1.5) * $S, ($h / 2 - 1.5) * $S, pxCol($im, $fill));
    pxPut($im, $font, $size, $a + $w / 2, $yc + pxCapH($font, $size) / 2, pxCol($im, $ink), $str, 'c');
    return [$a, $a + $w];
}

function pxSpaced($im, $font, $size, $cx, $y, $col, $text, $track) {
    $chars = preg_split('//u', (string)$text, -1, PREG_SPLIT_NO_EMPTY);
    $ws = [];
    foreach ($chars as $c) $ws[] = $c === ' ' ? $size * 0.32 : pxInkW($font, $size, $c);
    $x = $cx - (array_sum($ws) + $track * (count($chars) - 1)) / 2;
    foreach ($chars as $i => $c) {
        if ($c !== ' ') pxPut($im, $font, $size, $x, $y, $col, $c);
        $x += $ws[$i] + $track;
    }
}

function pxFoot($im, $tail) {
    $bot = trim((string)botUsername());
    $txt = mb_strtoupper(($bot !== '' ? '@' . $bot . ' · ' : '') . $tail, 'UTF-8');
    pxSpaced($im, pxLat(600), 13.5, 600, 636, pxCol($im, '8C8C92'), $txt, 2.6);
}

function pxQrMul($x, $y) {
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = ($z << 1) ^ (($z >> 7) * 0x11D);
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z;
}

function pxQrEcc(array $data, $n) {
    $div = array_fill(0, $n, 0);
    $div[$n - 1] = 1;
    $root = 1;
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j < $n; $j++) {
            $div[$j] = pxQrMul($div[$j], $root);
            if ($j + 1 < $n) $div[$j] ^= $div[$j + 1];
        }
        $root = pxQrMul($root, 2);
    }
    $res = array_fill(0, $n, 0);
    foreach ($data as $b) {
        $f = $b ^ $res[0];
        array_shift($res);
        $res[] = 0;
        for ($i = 0; $i < $n; $i++) $res[$i] ^= pxQrMul($div[$i], $f);
    }
    return $res;
}

function pxQrFormat($mask, $n) {
    $data = $mask;
    $rem  = $data;
    for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
    $bits = (($data << 10) | $rem) ^ 0x5412;
    $g = fn($i) => ($bits >> $i) & 1;
    $out = [];
    for ($i = 0; $i <= 5; $i++) $out[] = [8, $i, $g($i)];
    $out[] = [8, 7, $g(6)]; $out[] = [8, 8, $g(7)]; $out[] = [7, 8, $g(8)];
    for ($i = 9; $i < 15; $i++) $out[] = [14 - $i, 8, $g($i)];
    for ($i = 0; $i < 8; $i++)  $out[] = [$n - 1 - $i, 8, $g($i)];
    for ($i = 8; $i < 15; $i++) $out[] = [8, $n - 15 + $i, $g($i)];
    $out[] = [8, $n - 8, 1];
    return $out;
}

function pxQrPenalty(array $t, $n) {
    $p = 0;
    $lines = [];
    for ($y = 0; $y < $n; $y++) $lines[] = $t[$y];
    for ($x = 0; $x < $n; $x++) $lines[] = array_column($t, $x);
    $dark = 0;
    foreach ($lines as $li => $row) {
        $run = 1;
        for ($i = 1; $i <= $n; $i++) {
            if ($i < $n && $row[$i] === $row[$i - 1]) { $run++; continue; }
            if ($run >= 5) $p += $run - 2;
            $run = 1;
        }
        $s = implode('', $row);
        $p += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
        if ($li < $n) $dark += array_sum($row);
    }
    for ($y = 0; $y < $n - 1; $y++) for ($x = 0; $x < $n - 1; $x++) {
        $c = $t[$y][$x];
        if ($c === $t[$y][$x + 1] && $c === $t[$y + 1][$x] && $c === $t[$y + 1][$x + 1]) $p += 3;
    }
    $total = $n * $n;
    $p += 10 * (intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1);
    return $p;
}

function pxQrMatrix($text) {
    $bytes = array_values(unpack('C*', (string)$text) ?: []);
    $len = count($bytes);
    $raw = [1 => 26, 44, 70, 100, 134, 172, 196, 242, 292, 346];
    $ecl = [1 => 10, 16, 26, 18, 24, 16, 18, 22, 22, 26];
    $blk = [1 => 1, 1, 1, 2, 2, 4, 4, 4, 5, 5];
    $ver = 0;
    for ($v = 1; $v <= 10; $v++)
        if (4 + ($v < 10 ? 8 : 16) + 8 * $len <= ($raw[$v] - $ecl[$v] * $blk[$v]) * 8) { $ver = $v; break; }
    if (!$ver || !$len) return null;

    $dataCw = $raw[$ver] - $ecl[$ver] * $blk[$ver];
    $bits = [];
    $push = function ($val, $k) use (&$bits) { for ($i = $k - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1; };
    $push(4, 4);
    $push($len, $ver < 10 ? 8 : 16);
    foreach ($bytes as $b) $push($b, 8);
    $push(0, min(4, $dataCw * 8 - count($bits)));
    $push(0, (8 - count($bits) % 8) % 8);
    $cw = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $b = 0;
        for ($j = 0; $j < 8; $j++) $b = ($b << 1) | $bits[$i + $j];
        $cw[] = $b;
    }
    for ($p = 0; count($cw) < $dataCw; $p++) $cw[] = $p % 2 ? 0x11 : 0xEC;

    $nb = $blk[$ver]; $el = $ecl[$ver];
    $short = $nb - $raw[$ver] % $nb;
    $shortLen = intdiv($raw[$ver], $nb);
    $blocks = []; $k = 0;
    for ($i = 0; $i < $nb; $i++) {
        $dl = $shortLen - $el + ($i < $short ? 0 : 1);
        $d  = array_slice($cw, $k, $dl);
        $k += $dl;
        $e  = pxQrEcc($d, $el);
        if ($i < $short) $d[] = -1;
        $blocks[] = array_merge($d, $e);
    }
    $all = [];
    for ($i = 0, $L = count($blocks[0]); $i < $L; $i++)
        foreach ($blocks as $b) if ($b[$i] >= 0) $all[] = $b[$i];

    $n  = 17 + 4 * $ver;
    $m  = array_fill(0, $n, array_fill(0, $n, 0));
    $fn = array_fill(0, $n, array_fill(0, $n, false));
    $set = function ($x, $y, $dark) use (&$m, &$fn) { $m[$y][$x] = $dark ? 1 : 0; $fn[$y][$x] = true; };
    for ($i = 0; $i < $n; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    foreach ([[3, 3], [$n - 4, 3], [3, $n - 4]] as [$cx, $cy])
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
            $x = $cx + $dx; $y = $cy + $dy;
            if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) continue;
            $d = max(abs($dx), abs($dy));
            $set($x, $y, $d !== 2 && $d !== 4);
        }
    if ($ver > 1) {
        $na   = intdiv($ver, 7) + 2;
        $step = intdiv($ver * 4 + $na * 2 + 1, $na * 2 - 2) * 2;
        $al   = [0 => 6];
        for ($i = $na - 1, $pos = $n - 7; $i >= 1; $i--, $pos -= $step) $al[$i] = $pos;
        ksort($al);
        $al   = array_values($al);
        $last = count($al) - 1;
        foreach ($al as $i => $ax) foreach ($al as $j => $ay) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++)
                $set($ax + $dx, $ay + $dy, max(abs($dx), abs($dy)) !== 1);
        }
    }
    foreach (pxQrFormat(0, $n) as [$x, $y, $b]) $set($x, $y, $b);
    if ($ver >= 7) {
        $rem = $ver;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $vb = ($ver << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = ($vb >> $i) & 1;
            $a = $n - 11 + $i % 3; $b = intdiv($i, 3);
            $set($a, $b, $bit); $set($b, $a, $bit);
        }
    }

    $i = 0; $total = count($all) * 8;
    for ($right = $n - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        $up = (($right + 1) & 2) === 0;
        for ($vert = 0; $vert < $n; $vert++) for ($j = 0; $j < 2; $j++) {
            $x = $right - $j;
            $y = $up ? $n - 1 - $vert : $vert;
            if ($fn[$y][$x] || $i >= $total) continue;
            $m[$y][$x] = ($all[$i >> 3] >> (7 - ($i & 7))) & 1;
            $i++;
        }
    }

    $masks = [
        fn($x, $y) => ($x + $y) % 2 === 0,
        fn($x, $y) => $y % 2 === 0,
        fn($x, $y) => $x % 3 === 0,
        fn($x, $y) => ($x + $y) % 3 === 0,
        fn($x, $y) => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
        fn($x, $y) => $x * $y % 2 + $x * $y % 3 === 0,
        fn($x, $y) => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
        fn($x, $y) => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
    ];
    $best = null; $bestP = PHP_INT_MAX;
    foreach ($masks as $mk => $f) {
        $t = $m;
        for ($y = 0; $y < $n; $y++) for ($x = 0; $x < $n; $x++)
            if (!$fn[$y][$x] && $f($x, $y)) $t[$y][$x] ^= 1;
        foreach (pxQrFormat($mk, $n) as [$x, $y, $b]) $t[$y][$x] = $b;
        $pen = pxQrPenalty($t, $n);
        if ($pen < $bestP) { $bestP = $pen; $best = $t; }
    }
    return $best;
}

function pxQrDraw($im, array $q, $x, $y, $size, $hex) {
    $S = PX_SS;
    $n = count($q);
    $u = $size / $n;
    $col = pxCol($im, $hex);
    $at = fn($v) => (int)round($v * $S);
    $inFinder = fn($c, $r) => ($c < 7 && $r < 7) || ($c >= $n - 7 && $r < 7) || ($c < 7 && $r >= $n - 7);
    for ($r = 0; $r < $n; $r++) for ($c = 0; $c < $n; $c++) {
        if (!$q[$r][$c] || $inFinder($c, $r)) continue;
        imagefilledrectangle($im, $at($x + $c * $u), $at($y + $r * $u), $at($x + ($c + 1) * $u) - 1, $at($y + ($r + 1) * $u) - 1, $col);
    }
    foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fc, $fr]) {
        $x1 = $x + $fc * $u; $y1 = $y + $fr * $u;
        pxRRing($im, $at($x1), $at($y1), $at($x1 + 7 * $u) - 1, $at($y1 + 7 * $u) - 1, $at(1.9 * $u), $at($u), $col);
        pxRRect($im, $at($x1 + 2 * $u), $at($y1 + 2 * $u), $at($x1 + 5 * $u) - 1, $at($y1 + 5 * $u) - 1, $at(0.9 * $u), $col);
    }
}

function pxGlowHex($hex) {
    [$r, $g, $b] = pxHex($hex);
    $l = (max($r, $g, $b) + min($r, $g, $b)) / 510;
    if ($l < 0.42) return pxTint($hex, 0.62, 'FFFFFF');
    if ($l > 0.78) return pxTint($hex, 0.75, '000000');
    return strtoupper(ltrim((string)$hex, '#'));
}

function pxLike($x, $ref) {
    $p = strpos((string)$ref, '.');
    return number_format((float)$x, $p === false ? 0 : strlen((string)$ref) - $p - 1);
}

function pxSigned($s, $neg) {
    return ($neg ? '−' : '+') . ltrim((string)$s, '-');
}

function pxPriceCard(array $o) {
    $S     = PX_SS;
    $up    = (float)($o['chg'] ?? 0) >= 0;
    $im    = pxBase((string)$o['a'], (string)$o['b']);
    $white = pxCol($im, 'F5F5F7');
    $gray  = pxCol($im, '9A9AA0');
    $lat   = pxLat(600);
    $rtl   = !empty($o['rtl']);
    $disc  = $o['disc'] ?? null;
    $title = (string)($o['title'] ?? '');

    if ($rtl) {
        [, $e] = pxTag($im, 232, 172, 'LIVE', 'green', 'l');
        [, $e] = pxTag($im, $e + 10, 172, (string)$o['tag'], 'gray', 'l');
        $tx = $disc ? 896 : 966;
        pxSay($im, pxSayFit(31, $title, $tx - $e - 30, 16), $tx, 184, $white, $title, 'r');
        $dx = 938;
    } else {
        [$e] = pxTag($im, 968, 172, (string)$o['tag'], 'gray', 'r');
        [$e] = pxTag($im, $e - 10, 172, 'LIVE', 'green', 'r');
        $tx = $disc ? 304 : 232;
        pxSay($im, pxSayFit(31, $title, $e - 30 - $tx, 16), $tx, 184, $white, $title, 'l');
        $dx = 262;
    }
    if ($disc instanceof GdImage || is_resource($disc)) {
        imagefilledellipse($im, $dx * $S, 172 * $S, 54 * $S, 54 * $S, pxCol($im, 'FFFFFF', 0.16));
        imagecopy($im, $disc, ($dx - 24) * $S, (172 - 24) * $S, 0, 0, 48 * $S, 48 * $S);
        imagedestroy($disc);
    } elseif (is_array($disc)) {
        imagefilledellipse($im, $dx * $S, 172 * $S, 48 * $S, 48 * $S, pxCol($im, (string)$disc[0]));
        $ini = mb_substr((string)$disc[1], 0, 3);
        $f   = pxLat(700);
        pxPut($im, $f, pxFit($f, 14, $ini, 36, 8), $dx, 172 + pxCapH($f, 14) / 2, $white, $ini, 'c');
    }

    pxSay($im, 18, 600, 256, $gray, (string)($o['label'] ?? 'قیمت لحظه‌ای'), 'c');
    $val  = (string)$o['value'];
    $pre  = (string)($o['prefix'] ?? '');
    $unit = (string)($o['unit'] ?? '');
    $size = pxFit($lat, 58, $pre . $val, 520 - ($unit !== '' ? pxSayW(22, $unit) + 18 : 0), 28);
    $base = 350 - (58 - $size) * 0.3;
    $pw   = $pre !== '' ? pxInkW($lat, $size, $pre) + 4 : 0;
    $w    = pxInkW($lat, $size, $val);
    $us   = max(15, 22 * $size / 58);
    $uw   = $unit !== '' ? pxSayW($us, $unit) + 16 : 0;
    $x0   = 600 - ($uw + $pw + $w) / 2;
    if ($unit !== '') pxSay($im, $us, $x0, $base, $gray, $unit, 'l');
    if ($pre !== '')  pxPut($im, $lat, $size, $x0 + $uw, $base, $gray, $pre);
    pxPut($im, $lat, $size, $x0 + $uw + $pw, $base, $white, $val);

    imagefilledrectangle($im, 232 * $S, 412 * $S, 968 * $S, 412 * $S + 1, pxCol($im, 'FFFFFF', 0.08));
    $tone = $up ? '86EBC9' : 'F7A1B4';
    $pct  = pxSigned(number_format(abs((float)$o['chg']), 2), !$up) . '%';
    foreach ([[844, '۲۴ ساعت پیش', (string)$o['prev'], 'E4E6EB'], [600, 'تغییر', (string)$o['diff'], $tone], [356, 'درصد تغییر', $pct, $tone]] as [$cx, $lab, $v, $hex]) {
        pxSay($im, 15.5, $cx, 462, $gray, $lab, 'c');
        pxPut($im, $lat, pxFit($lat, 22, $v, 220, 12), $cx, 510, pxCol($im, $hex), $v, 'c');
    }

    pxFoot($im, 'LIVE MARKET · ' . gmdate('Y-m-d H:i', time() + 12600) . ' TEHRAN');
    return pxJpg($im);
}

function pxFiatCard(array $o) {
    if (!pxCardReady()) return null;
    $v    = (float)($o['value'] ?? 0);
    $chg  = (float)($o['chg'] ?? 0);
    $unit = (string)($o['unit'] ?? 'تومان');
    $prev = $chg > -100 ? $v / (1 + $chg / 100) : $v;
    $vs   = pxValueStr($v, $unit);
    $disc = null;
    $src  = pxLoadImg(pxArt((string)($o['badge'] ?? '')));
    if ($src) {
        $sw = imagesx($src); $sh = imagesy($src); $side = min($sw, $sh);
        $sq = imagecreatetruecolor($side, $side);
        imagecopy($sq, $src, 0, 0, (int)(($sw - $side) / 2), (int)(($sh - $side) / 2), $side, $side);
        imagedestroy($src);
        $disc = pxDisc($sq, 48 * PX_SS);
        imagedestroy($sq);
    }
    return pxPriceCard([
        'a' => $chg >= 0 ? '12C488' : 'E5486A', 'b' => '2F72DF', 'rtl' => true, 'disc' => $disc,
        'title' => (string)($o['name'] ?? ''), 'tag' => (string)($o['tag'] ?? 'IRT'),
        'value' => $vs, 'unit' => $unit, 'chg' => $chg,
        'prev'  => pxLike($prev, $vs), 'diff' => pxSigned(pxLike(abs($v - $prev), $vs), $v < $prev),
    ]);
}

function pxCryptoCard(array $o) {
    if (!pxCardReady()) return null;
    $sym   = strtoupper((string)($o['sym'] ?? ''));
    $v     = (float)($o['value'] ?? 0);
    $chg   = (float)($o['chg'] ?? 0);
    $unit  = (string)($o['unit'] ?? '');
    $pre   = (string)($o['prefix'] ?? '');
    $prev  = $chg > -100 ? $v / (1 + $chg / 100) : $v;
    $logo  = pxLoadImg(pxLogoFile($sym));
    $shape = $logo ? pxLogoShape($logo) : ['pad' => false, 'brand' => ''];
    $brand = $shape['brand'] !== '' ? $shape['brand'] : pxCoinColors($sym)[0];
    $disc  = [$brand, $sym];
    if ($logo) { $disc = pxDisc($logo, 48 * PX_SS, $shape['pad']); imagedestroy($logo); }
    $book  = pxCoinBook()[$sym][0] ?? '';
    $vs    = pxValueStr($v, $unit);
    return pxPriceCard([
        'a' => pxGlowHex($brand), 'b' => $chg >= 0 ? '12C488' : 'E5486A', 'rtl' => false, 'disc' => $disc,
        'title' => (string)($o['title'] ?? $sym), 'tag' => (string)($o['pill'] ?? '24H'),
        'label' => $book !== '' ? $book : 'قیمت لحظه‌ای',
        'value' => $vs, 'prefix' => $pre, 'unit' => $unit, 'chg' => $chg,
        'prev'  => $pre . pxLike($prev, $vs),
        'diff'  => pxSigned($pre . pxLike(abs($v - $prev), $vs), $v < $prev),
    ]);
}

function pxAssetCardOf($key, $name, $value, $unit, $tag, $chg) {
    [, $badge] = pxAssetLook($key);
    return pxFiatCard(['badge' => $badge, 'name' => $name, 'value' => $value, 'unit' => $unit, 'tag' => $tag, 'chg' => $chg]);
}

function pxSampleCard() {
    $v = pxAssetPrice('usd');
    return pxAssetCardOf('usd', 'دلار آمریکا', $v > 0 ? $v : 97450, 'تومان', 'IRT', 0.85);
}

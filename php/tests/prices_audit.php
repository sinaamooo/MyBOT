<?php

$prevHttpFake = Http::$fake;
foreach (['q:TON', 'q:BTC', 'q:ETH', 'usdt_toman', 'fiat_rates', 'tgju'] as $ck) {
    Cache::forget($ck);
}
$now = time();
$P = ['BTC' => 75562.0, 'ETH' => 2221.5, 'TRX' => 0.31797, 'BNB' => 690.0, 'SOL' => 89.29, 'DOGE' => 0.07141, 'XRP' => 1.1977, 'GRAM' => 1.108, 'NOT' => 0.00181, 'LTC' => 81.2, 'ADA' => 0.512];
$CHG = ['BTC' => 1.2, 'ETH' => -0.8, 'TRX' => 0.4, 'BNB' => 0.1, 'SOL' => 2.5, 'DOGE' => -1.1, 'XRP' => 0.9, 'GRAM' => -5.78, 'NOT' => 3.0, 'LTC' => 0.2, 'ADA' => -0.3];
$USDT = 271200.0;
$FX = ['eur' => 0.89309043, 'aed' => 3.6725, 'iqd' => 1518.05962775, 'try' => 50.12164311, 'gbp' => 0.7448442, 'cny' => 6.66931112, 'xau' => 0.0002473476, 'usd' => 1.0];
$OUNCE = 1 / $FX['xau'];
$gecko = [];
foreach ($P as $s => $p) {
    $gecko[] = ['id' => $s === 'GRAM' ? 'the-open-network' : strtolower($s) . '-id', 'symbol' => strtolower($s), 'name' => $s === 'GRAM' ? 'Toncoin' : $s, 'current_price' => $p * 1.0004, 'price_change_percentage_24h' => $CHG[$s], 'total_volume' => 1e8];
}
$gecko[] = ['id' => 'tokamak-network', 'symbol' => 'ton', 'name' => 'Tokamak', 'current_price' => 1.6, 'price_change_percentage_24h' => 0.95, 'total_volume' => 3e6];
$ids = ['BTC' => 'bitcoin', 'ETH' => 'ethereum', 'TRX' => 'tron', 'BNB' => 'binancecoin', 'SOL' => 'solana', 'DOGE' => 'dogecoin', 'XRP' => 'ripple', 'NOT' => 'notcoin'];
foreach ($gecko as &$g) { $s = strtoupper($g['symbol']); if (isset($ids[$s])) $g['id'] = $ids[$s]; } unset($g);
$okx = [];
foreach ($P as $s => $p) {
    $okx[] = ['instId' => "$s-USDT", 'last' => (string)$p, 'open24h' => (string)($p / (1 + $CHG[$s] / 100)), 'ts' => (string)($now * 1000), 'volCcy24h' => '5e7'];
}
$okx[] = ['instId' => 'TON-USDT', 'last' => '1.6', 'open24h' => '1.59', 'ts' => (string)(($now - 86400 * 30) * 1000), 'volCcy24h' => '10'];
$cc = ['RAW' => []];
foreach ($P as $s => $p) { if ($s !== 'GRAM') $cc['RAW'][$s] = ['USD' => ['PRICE' => $p * 0.9997, 'CHANGEPCT24HOUR' => $CHG[$s], 'LASTUPDATE' => $now, 'VOLUME24HOURTO' => 1e8]]; }
$cc['RAW']['TON'] = ['USD' => ['PRICE' => 1.1075, 'CHANGEPCT24HOUR' => -5.7, 'LASTUPDATE' => $now, 'VOLUME24HOURTO' => 1e8]];
$wallexSyms = ['USDTTMN' => ['baseAsset' => 'USDT', 'quoteAsset' => 'TMN', 'stats' => ['lastPrice' => (string)$USDT, '24h_ch' => '0.4']]];
foreach ($P as $s => $p) { $b = $s === 'GRAM' ? 'TON' : $s; $wallexSyms[$b . 'USDT'] = ['baseAsset' => $b, 'quoteAsset' => 'USDT', 'stats' => ['lastPrice' => (string)($p * 1.0008), '24h_ch' => (string)$CHG[$s]]]; }
$nobU = ['status' => 'ok', 'stats' => []];
foreach ($P as $s => $p) { $b = strtolower($s === 'GRAM' ? 'TON' : $s); $nobU['stats']["$b-usdt"] = ['latest' => (string)($p * 0.9995), 'dayChange' => (string)$CHG[$s], 'isClosed' => false]; }
$bitpin = [['symbol' => 'USDT_IRT', 'price' => (string)($USDT + 100)], ['symbol' => 'BTC_USDT', 'price' => (string)($P['BTC'] * 1.0002), 'daily_change_price' => '1.2']];
$k24 = $OUNCE / 31.1034768 * $USDT;
$tgju = ['current' => [
    'price_dollar_rl' => ['p' => number_format($USDT * 10 * 0.998), 'dp' => '0.3', 'dt' => 'high'],
    'price_eur' => ['p' => number_format($USDT / $FX['eur'] * 10), 'dp' => '0.2', 'dt' => 'high'],
    'price_aed' => ['p' => number_format($USDT / $FX['aed'] * 10), 'dp' => '0.3', 'dt' => 'high'],
    'price_try' => ['p' => number_format($USDT / $FX['try'] * 10), 'dp' => '0.1', 'dt' => 'low'],
    'price_iqd' => ['p' => number_format($USDT / $FX['iqd'] * 10 * 100), 'dp' => '0.5', 'dt' => 'high'],
    'price_gbp' => ['p' => number_format($USDT / $FX['gbp'] * 10), 'dp' => '0.2', 'dt' => 'high'],
    'price_cny' => ['p' => number_format($USDT / $FX['cny'] * 10), 'dp' => '0.2', 'dt' => 'high'],
    'geram18' => ['p' => number_format($k24 * 0.75 * 10), 'dp' => '0.7', 'dt' => 'high'],
    'geram24' => ['p' => number_format($k24 * 10), 'dp' => '0.7', 'dt' => 'high'],
    'sekee' => ['p' => number_format(2900000000), 'dp' => '1.1', 'dt' => 'high'],
    'ons' => ['p' => number_format($OUNCE, 2), 'dp' => '0.4', 'dt' => 'high'],
]];
$gd = ['upgraded' => []];
$HTTP = [
    'api.coingecko.com/api/v3/coins/markets' => $gecko, 'okx.com/api/v5/market/tickers' => ['data' => $okx], 'min-api.cryptocompare.com/data/pricemultifull' => $cc,
    'api.wallex.ir/v1/markets' => ['result' => ['symbols' => $wallexSyms]], 'api.bitpin.org/api/v1/mkt/tickers/' => $bitpin,
    'api1.tabdeal.org' => ['price' => (string)($USDT - 50)], 'call1.tgju.org/ajax.json' => $tgju,
    'open.er-api.com/v6/latest/USD' => ['rates' => array_change_key_case(array_map(fn($v) => $v, array_filter($FX, fn($k) => strlen($k) === 3, ARRAY_FILTER_USE_KEY)), CASE_UPPER)],
    'Gifts_Details.json' => $gd,
];
Http::$fake = function (array $r) use ($HTTP, $nobU, $USDT) {
    $u = $r['url'];
    if (str_contains($u, 'api.nobitex.ir/market/stats')) {
        return ['status' => 200, 'body' => json_encode(($r['params']['dstCurrency'] ?? '') === 'usdt' ? $nobU : ['status' => 'ok', 'stats' => ['usdt-rls' => ['latest' => (string)($USDT * 10 + 500), 'dayChange' => '0.4']]])];
    }
    foreach ($HTTP as $k => $v) { if (str_contains($u, $k)) return ['status' => 200, 'body' => json_encode($v)]; }
    return ['status' => 0, 'body' => ''];
};
Market::reset(null);
@unlink(Config::$root . '/data/market.json');
Market::refresh([], true, true);
$ask = function (string $q, int $chat = 6100) {
    $mid = ++$GLOBALS['mid'];
    $GLOBALS['calls'] = [];
    Bot::handle(['update_id' => $mid, 'message' => ['message_id' => $mid, 'date' => time(), 'chat' => ['id' => $chat, 'type' => $chat > 0 ? 'private' : 'supergroup', 'title' => 'G'],
        'from' => ['id' => 777, 'is_bot' => false, 'first_name' => 'A'], 'text' => $q]]);
    $out = [];
    foreach ($GLOBALS['calls'] as [$m, $p]) { if (in_array($m, ['sendMessage', 'sendPhoto', 'editMessageText'], true)) $out[] = $p['text'] ?? $p['caption'] ?? ''; }
    return trim(preg_replace(['/<tg-emoji[^>]*>(.*?)<\/tg-emoji>/u', '/<[^>]+>/', '/[\x{200E}\x{200F}]/u'], ['$1', '', ''], implode("\n----\n", $out)));
};
$fmt = fn($v, $d = 0) => number_format($v, $d);
$ton = 1.108; $tx = 0.31797; $star = 0.015;
$cases = [
    ['btc', ['$' . $fmt($P['BTC'])]],
    ['1 btc', [$fmt($P['BTC'] * $USDT)]],
    ['0.5 eth', [$fmt(0.5 * $P['ETH'] * $USDT)]],
    ['قیمت تون', [$fmt($ton * $USDT)]],
    ['10 تون', [$fmt(10 * $ton * $USDT)]],
    ['100 تتر', [$fmt(100 * $USDT)]],
    ['1000000 تومان', [$fmt(1000000 / $USDT, 2)]],
    ['10m ریال', [$fmt(1000000 / $USDT, 2)]],
    ['500 درهم', [$fmt(500 * $USDT / $FX['aed'])]],
    ['100 یورو', [$fmt(100 * $USDT / $FX['eur'])]],
    ['1 دلار', [$fmt($USDT * 0.998)]],
    ['1000 دینار', [$fmt(1000 * $USDT / $FX['iqd'])]],
    ['100 لیر', [$fmt(100 * $USDT / $FX['try'])]],
    ['2 یورو به لیر', [$fmt(2 / $FX['eur'] * $FX['try'], 2)]],
    ['500 لیر دلار', [$fmt(500 / $FX['try'], 2)]],
    ['500 تتر تون', [$fmt(500 / $ton, 2)]],
    ['طلا', [$fmt($k24 * 0.75)]],
    ['2 سکه', [$fmt(2 * 290000000)]],
    ['استارز', [$fmt(50 * $star * $USDT)]],
    ['1000 استارز', [$fmt(1000 * $star * $USDT)]],
    ['پریمیوم', [$fmt(11.99 * $USDT)]],
    ['نرخ ارز', ['$' . $fmt($P['BTC']), $fmt($P['BTC'] * $USDT)]],
];
foreach ($cases as [$q, $want]) {
    $flat = str_replace(['٬', '٫'], [',', '.'], Fmt::en($ask($q)));
    preg_match_all('/[\d,]+(?:\.\d+)?/', $flat, $nums);
    $vals = array_map(fn($n) => (float)str_replace(',', '', $n), $nums[0]);
    $miss = array_filter($want, function ($w) use ($vals) {
        $wn = (float)str_replace([',', '$'], '', $w);
        foreach ($vals as $v) {
            if ($wn > 0 && abs($v - $wn) / $wn < 0.004) {
                return false;
            }
        }
        return true;
    });
    check(!$miss, "price audit: $q gives " . implode(', ', $want));
}
check(!str_contains(Fmt::en($ask('قیمت تون')), '433,'), 'price audit: TON is never priced as the other coin called TON');
check(str_contains(Fmt::en($ask('btc')), '75,562 $'), 'price audit: one coin shows its dollar price');
@unlink(Config::$root . '/data/market.json');
Market::reset([]);
Http::$fake = $prevHttpFake;

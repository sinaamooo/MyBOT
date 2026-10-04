<?php
defined('NB_ROOT') || exit;

if (!defined('NUM_LIB')) define('NUM_LIB', 1);


if (!defined('NUM5_BASE'))    define('NUM5_BASE', 'https://5sim.net/v1');
if (!defined('NUM5_PRODUCT')) define('NUM5_PRODUCT', 'telegram');
if (!defined('NUMNL_BASE'))   define('NUMNL_BASE', 'https://api.numberland.ir');

function numProviders() {
    return [
        '5sim' => [
            'label' => '🌍 ۵سیم (5sim.net)',
            'name'  => '۵سیم',
            'base'  => NUM5_BASE,
            'hosts' => ['5sim.net'],
            'key'   => 'توکن',
            'cur'   => 'دلار',
            'help'  => '5sim.net ← Settings ← API key (کلیدِ بالایی، همان که با eyJ شروع می‌شود)',
        ],
        'numberland' => [
            'label' => '🇮🇷 نامبرلند (numberland.ir)',
            'name'  => 'نامبرلند',
            'base'  => NUMNL_BASE,
            'hosts' => ['numberland.ir'],
            'key'   => 'کلید API',
            'cur'   => 'تومان',
            'help'  => 'numberland.ir ← پنل کاربری ← API',
        ],
    ];
}

function numProv() {
    $p = (string)numVal('provider', '5sim');
    return isset(numProviders()[$p]) ? $p : '5sim';
}
function numProvInfo($k = null) { return numProviders()[$k ?? numProv()]; }
function numProvName() { return numProvInfo()['name']; }

function numNeedsRate() { return numProv() === '5sim'; }

function numKey() {
    return trim((string)numVal(numProv() === 'numberland' ? 'api.nl_key' : 'api.token', ''));
}

function numBase() {
    $own = numBaseNorm((string)numVal('api.base', ''), numProv());
    if ($own !== '' && numBaseForeign($own, numProv())) $own = '';
    return $own !== '' ? $own : rtrim(numProvInfo()['base'], '/');
}

function numBaseNorm($url, $prov) {
    $u = rtrim(trim((string)$url), '/');
    if ($u === '') return '';
    $h = strtolower((string)parse_url($u, PHP_URL_HOST));
    if ($h === '') return '';
    if ($prov === '5sim') {
        if ($h === '5sim.net' || str_ends_with($h, '.5sim.net')) return 'https://5sim.net/v1';
        return preg_replace('#(/v1)(/.*)?$#i', '$1', $u);
    }
    if ($prov === 'numberland') return rtrim(preg_replace('#/v2\.php.*$#i', '', $u), '/');
    return $u;
}

function numProducts() {
    return [
        'telegram'  => ['fa' => 'تلگرام',     'e' => '✈️', 'nl' => 'nl_svc',    'rx' => '/telegram|تلگرام/iu'],
        'instagram' => ['fa' => 'اینستاگرام', 'e' => '📸', 'nl' => 'nl_svc_ig', 'rx' => '/instagram|insta|اینستا/iu'],
        'whatsapp'  => ['fa' => 'واتساپ',     'e' => '💬', 'nl' => 'nl_svc_wa', 'rx' => '/whats\s*app|واتس/iu'],
    ];
}

function numProdsOn() {
    $on = (array)numVal('prods', array_keys(numProducts()));
    $out = [];
    foreach (array_keys(numProducts()) as $k) if (in_array($k, $on, true)) $out[] = $k;
    return $out ?: ['telegram'];
}

function numProdOfSid($sid) {
    $sid = (string)$sid;
    if (str_contains($sid, '~')) { $p = strstr($sid, '~', true); if (isset(numProducts()[$p])) return $p; }
    return 'telegram';
}

function numBaseForeign($url, $prov) {
    $h = strtolower((string)parse_url((string)$url, PHP_URL_HOST));
    if ($h === '') return false;
    foreach (numProviders() as $k => $p) {
        if ($k === $prov) continue;
        $own = array_merge([strtolower((string)parse_url($p['base'], PHP_URL_HOST))], $p['hosts'] ?? []);
        foreach ($own as $o)
            if ($o !== '' && ($h === $o || str_ends_with($h, '.' . $o))) return true;
    }
    return false;
}

function numLooks5sim($tok) {
    $t = preg_replace('/\s+/', '', (string)$tok);
    return str_starts_with($t, 'eyJ') && substr_count($t, '.') === 2 && numTokenShape($t)['ok'];
}

function numUse5sim($tok) {
    $t = preg_replace('/\s+/', '', (string)$tok);
    numSet(function (&$c) use ($t) {
        $c['provider'] = '5sim';
        if (!is_array($c['api'] ?? null)) $c['api'] = [];
        $c['api']['token'] = $t;
        if (numBaseForeign((string)($c['api']['base'] ?? ''), '5sim')) $c['api']['base'] = '';
    });
}

function numDefaults() {
    return [
        'provider' => '5sim',
        'wait'   => 900,
        'poll'   => 6,
        'markup' => 0,
        'sync_price' => true,
        'prods'  => ['telegram', 'instagram', 'whatsapp'],
        'pick'   => true,
        'pick_n' => 12,
        'pick_ops' => 2,
        'all'    => true,
        'api'   => [
            'on'      => false,
            'token'   => '',
            'nl_key'  => '',
            'nl_svc'  => '1',
            'nl_svc_ig' => '',
            'nl_svc_wa' => '',
            'base'    => '',
            'timeout' => 15,
            'rate'    => 0,
            'max'     => 0,
        ],
    ];
}

function numCfg() {
    $c = cfg()['numbers'] ?? null;
    $d = numDefaults();
    if (!is_array($c)) return $d;

    $out = array_replace($d, array_intersect_key($c,
        ['wait' => 1, 'poll' => 1, 'markup' => 1, 'sync_price' => 1, 'provider' => 1,
         'prods' => 1, 'pick' => 1, 'pick_n' => 1, 'pick_ops' => 1, 'all' => 1]));
    $out['api'] = array_replace($d['api'],
        array_intersect_key(is_array($c['api'] ?? null) ? $c['api'] : [],
                            ['on'=>1,'token'=>1,'nl_key'=>1,'nl_svc'=>1,'nl_svc_ig'=>1,'nl_svc_wa'=>1,
                             'base'=>1,'timeout'=>1,'rate'=>1,'max'=>1]));
    return $out;
}

function numVal($path, $default = null) {
    $cur = numCfg();
    foreach (explode('.', (string)$path) as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
        $cur = $cur[$p];
    }
    return $cur;
}

function numSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!isset($c['numbers']) || !is_array($c['numbers'])) $c['numbers'] = [];
        $fn($c['numbers']);
    });
}

function numReady() {
    return !empty(numVal('api.on')) && numKey() !== '';
}

function numRate() {
    if (!numNeedsRate()) return 1.0;
    $own = (float)numVal('api.rate', 0);
    if ($own > 0) return $own;
    if (function_exists('pxUsdtToman')) {
        $r = (float)pxUsdtToman();
        if ($r > 0) return $r;
    }
    if (function_exists('svCfg')) {
        $c = svCfg();
        foreach (['fx_live', 'fx'] as $k) if ((float)($c[$k] ?? 0) > 0) return (float)$c[$k];
    }
    return 0.0;
}

function numToman($usd, $markup = 0, $round = true) {
    $rate = numRate();
    if ($rate <= 0) return 0.0;
    $t = (float)$usd * $rate * (1 + max(0, (float)$markup) / 100);
    return $round ? numRound100($t) : $t;
}

function numRound100($t) {
    $t = (float)$t;
    return $t > 0 ? round($t / 100) * 100 : 0.0;
}


function num5Get($path, $timeout = null) {
    $base = numBase();
    if ($base === '') return [null, 'آدرس فروشنده ثبت نشده'];
    $key = numKey();
    if ($key === '') return [null, 'کلید ' . numProvName() . ' ثبت نشده'];
    if ($timeout === null) $timeout = (int)numVal('api.timeout', 15);

    $url = $base . '/' . ltrim((string)$path, '/');
    $hdr = ['Accept: application/json'];

    if (numProv() === 'numberland')
        $url .= (str_contains($url, '?') ? '&' : '?') . 'apikey=' . rawurlencode($key);
    else
        $hdr[] = 'Authorization: Bearer ' . $key;

    if ((!defined('SV_ALLOW_PRIVATE') || !SV_ALLOW_PRIVATE) && function_exists('ssrfSafeUrl') && !ssrfSafeUrl($url, $ssrfWhy)) return [null, 'آدرسِ فروشنده رد شد: ' . $ssrfWhy];
    if (function_exists('__num5Hook')) return __num5Hook($url, $key, $timeout);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max(3, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => 6,
CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
        CURLOPT_HTTPHEADER     => $hdr,
    ]);
    $res  = monCurl($ch, 'numprov');
    $cerr = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return num5Parse($res, $cerr, $code);
}

function num5Parse($res, $cerr, $code) {
    if ($res === false || $res === null) return [null, 'اتصال برقرار نشد: ' . $cerr];

    if (numLooksIntercepted((string)$res, (int)$code))
        return [null, 'این پاسخ از ' . numProvName() . ' نیامده — یک واسط جوابش را داده. ' .
                      'یعنی درخواستِ سرور به مقصد نمی‌رسد.'];

    $txt = trim((string)$res);
    $j   = json_decode($txt, true);

    if ($code < 200 || $code >= 300) {
        $why = is_array($j) ? (string)($j['message'] ?? $j['error'] ?? '') : $txt;
        $why = trim(preg_replace('/\s+/u', ' ', $why));
        return [null, num5Why($why, $code)];
    }

    if (!is_array($j)) {
        if ($txt === '' ) return [null, 'پاسخی نیامد'];
        return [null, num5Why($txt, 200)];
    }
    return [$j, ''];
}

function numLooksIntercepted($body, $code) {
    if ($code !== 404 && $code !== 403 && $code !== 502) return false;
    $j = json_decode(trim((string)$body), true);
    if (!is_array($j)) return false;
    $blob = strtolower(json_encode($j));
    foreach (['invalid api endpoint', 'access denied', 'forbidden by', 'blocked'] as $sig)
        if (str_contains($blob, $sig)) return true;
    return isset($j['status'], $j['message'], $j['data']) && !isset($j['RESULT']);
}

function num5Why($raw, $code = 0) {
    $k = strtolower(trim(preg_replace('/\s+/u', ' ', (string)$raw)));
    static $map = [
        'server offline'        => 'سرورِ ۵سیم موقتا خاموش است — چند دقیقه بعد',
        'internal error'        => 'خطای داخلیِ ۵سیم — کمی بعد دوباره',
        'country is incorrect'  => 'کشور نامعتبر است',
        'bad country'           => 'کشور نامعتبر است',
        'bad operator'          => 'اپراتور نامعتبر است',
        'product is incorrect'  => 'این محصول برای این کشور نیست',
        'order no sms'          => 'برای این سفارش پیامکی نیامده',
        'hosting order'         => 'این سفارشِ اجاره‌ای است، نه فعال‌سازی',
        'you need to wait time' => 'هنوز نمی‌شود لغو کرد — ۵سیم کمی زمان می‌خواهد؛ خودکار دوباره امتحان می‌شود',
        'record not found'      => 'پیدا نشد',
        'api limit is 100 requests per second' => 'درخواست‌ها به ۵سیم از سقفِ ۱۰۰ در ثانیه گذشت',
        'ip address limit is 100 requests per second' => 'درخواست‌های این سرور به ۵سیم از سقفِ ۱۰۰ در ثانیه گذشت',
        'no free phones'        => 'الان شماره‌ی آزاد برای این کشور و اپراتور نیست',
        'not enough rating'     => 'امتیازِ حسابِ ۵سیم برای این خرید کافی نیست',
        'not enough user balance'=> 'موجودی حسابِ ۵سیم کافی نیست',
        'no product'            => 'این محصول روی ۵سیم نیست',
        'select operator'       => 'اپراتور انتخاب نشده',
        'select country'        => 'کشور انتخاب نشده',
        'order not found'       => 'این سفارش روی ۵سیم پیدا نشد',
        'order expired'         => 'مهلتِ این سفارش تمام شده',
        'order has sms'         => 'برای این شماره پیامک آمده — دیگر لغو نمی‌شود',
        'hosting order not found'=> 'این سفارش روی ۵سیم پیدا نشد',
        'unauthorized'          => 'توکن ۵سیم پذیرفته نشد',
        'token expired'         => 'توکن ۵سیم منقضی شده',
        'token invalid'         => 'توکن ۵سیم نامعتبر است',
        'bad request'           => 'درخواست نادرست بود',
    ];
    if ($k === 'not enough user balance') monFunds('numprov');
    if (isset($map[$k])) return $map[$k];
    if ($code === 401 || $code === 403) return 'کلیدِ ' . numProvName() . ' پذیرفته نشد — دوباره ثبتش کنید';
    if ($code === 429) return 'درخواست‌ها به ' . numProvName() . ' زیاد شد — چند ثانیه بعد';
    if ($code === 503) return numProvName() . ' موقتا در دسترس نیست یا سقفِ درخواستِ این سرور پر شده';
    if ($k === '') return 'کد پاسخ ' . $code;
    return mb_substr($raw, 0, 200);
}

function numCall($op, array $vars = []) {
    $id = trim((string)($vars['id'] ?? ''));
    $nl = numProv() === 'numberland';

    switch ($op) {
        case 'buy':
            if ($nl) {
                $sid = trim((string)($vars['sid'] ?? ''));
                if ($sid === '') return [null, 'شناسه‌ی شماره مشخص نیست'];
                return num5Get('/v2.php/?method=getnum&sid=' . rawurlencode($sid));
            }
            $co  = trim((string)($vars['country'] ?? ''));
            $opr = trim((string)($vars['operator'] ?? '')) ?: 'any';
            if ($co === '') return [null, 'کشور مشخص نیست'];
            $prod = trim((string)($vars['product'] ?? '')) ?: NUM5_PRODUCT;
            $p = '/user/buy/activation/' . rawurlencode($co) . '/' . rawurlencode($opr) .
                 '/' . rawurlencode($prod);
            $max = (float)numVal('api.max', 0);
            if ($max > 0) $p .= '?maxPrice=' . rawurlencode((string)$max);
            return num5Get($p);

        case 'status':
            if ($id === '') return [null, 'شناسه ندارد'];
            $t = min(6, (int)numVal('api.timeout', 15));
            return $nl ? num5Get('/v2.php/?method=checkstatus&id=' . rawurlencode($id), $t)
                       : num5Get('/user/check/' . rawurlencode($id), $t);

        case 'cancel':
            if ($id === '') return [null, 'شناسه ندارد'];
            $t = min(8, (int)numVal('api.timeout', 15));
            return $nl ? num5Get('/v2.php/?method=cancelnumber&id=' . rawurlencode($id), $t)
                       : num5Get('/user/cancel/' . rawurlencode($id), $t);

        case 'close':
            if ($id === '') return [null, 'شناسه ندارد'];
            $t = min(8, (int)numVal('api.timeout', 15));
            return $nl ? num5Get('/v2.php/?method=closenumber&id=' . rawurlencode($id), $t)
                       : num5Get('/user/finish/' . rawurlencode($id), $t);

        case 'repeat':
            if ($id === '') return [null, 'شناسه ندارد'];
            $t = min(8, (int)numVal('api.timeout', 15));
            return $nl ? num5Get('/v2.php/?method=repeat&id=' . rawurlencode($id), $t)
                       : [[], ''];

        case 'ban':
            if ($id === '') return [null, 'شناسه ندارد'];
            $t = min(8, (int)numVal('api.timeout', 15));
            return $nl ? num5Get('/v2.php/?method=bannumber&id=' . rawurlencode($id), $t)
                       : num5Get('/user/ban/' . rawurlencode($id), $t);

        case 'balance':
            return $nl ? num5Get('/v2.php/?method=getbalance')
                       : num5Get('/user/profile');
    }
    return [null, 'عملیات «' . $op . '» را نمی‌شناسم'];
}


function numNlErr($r) {
    if (!is_array($r)) return 'پاسخ نامعتبر';
    if (!isset($r['RESULT'])) return '';
    if ((string)$r['RESULT'] === '1') return '';
    $d = trim((string)($r['DESCRIPTION'] ?? ''));
    if (preg_match('/موجودی|اعتبار|balance|credit/iu', $d)) monFunds('numprov');
    return $d !== '' ? $d : ('نامبرلند کدِ ' . $r['RESULT'] . ' داد');
}

function numBuyDo(array $meta) {
    $nl = numProv() === 'numberland';
    [$r, $e] = numCall('buy', $nl
        ? ['sid' => $meta['sid'] ?? '']
        : ['country' => $meta['country'] ?? '', 'operator' => ($meta['operator'] ?? '') ?: 'any',
           'product' => $meta['product'] ?? NUM5_PRODUCT]);

    if (!is_array($r)) return ['err' => $e ?: 'پاسخی نیامد'];

    if ($nl) {
        if (($x = numNlErr($r)) !== '') return ['err' => $x];
        $aid   = (string)($r['ID'] ?? '');
        $phone = (string)($r['NUMBER'] ?? '');
        if ($aid === '')   return ['err' => 'شناسه‌ی فعال‌سازی در پاسخ نامبرلند نبود'];
        if ($phone === '') return ['err' => 'شماره در پاسخ نامبرلند نبود'];
        return ['aid' => $aid, 'phone' => $phone,
                'ttl' => numParseTtl($r['TIME'] ?? ''), 'cost' => 0.0,
                'operator' => '', 'country' => (string)($meta['country'] ?? ''), 'err' => ''];
    }

    $aid   = (string)($r['id'] ?? '');
    $phone = (string)($r['phone'] ?? '');
    if ($aid === '')   return ['err' => 'شناسه‌ی سفارش در پاسخ ۵سیم نبود'];
    if ($phone === '') return ['err' => 'شماره در پاسخ ۵سیم نبود'];
    return ['aid' => $aid, 'phone' => $phone,
            'ttl' => numExpiresIn((string)($r['expires'] ?? '')),
            'cost' => (float)($r['price'] ?? 0),
            'operator' => (string)($r['operator'] ?? ($meta['operator'] ?? '')),
            'country'  => (string)($r['country']  ?? ($meta['country']  ?? '')),
            'err' => ''];
}

function numCheckDo($aid, $seen = 0) {
    [$r, $e] = numCall('status', ['id' => $aid]);
    if (!is_array($r)) return ['state' => 'waiting', 'code' => '', 'n' => $seen, 'err' => $e];

    if (numProv() === 'numberland') {
        $st = (string)($r['RESULT'] ?? '');
        if (in_array($st, ['3', '4'], true))
            return ['state' => 'dead', 'code' => '', 'n' => $seen, 'err' => ''];
        if (!in_array($st, ['2', '6'], true))
            return ['state' => 'waiting', 'code' => '', 'n' => $seen, 'err' => ''];
        $code = numDigits((string)($r['CODE'] ?? ''));
        if ($code === '' || $code === '0')
            return ['state' => 'waiting', 'code' => '', 'n' => $seen, 'err' => ''];
        return ['state' => 'done', 'code' => $code, 'n' => $seen + 1, 'err' => ''];
    }

    $st = strtoupper(trim((string)($r['status'] ?? '')));
    if (in_array($st, ['CANCELED', 'CANCELLED', 'TIMEOUT', 'BANNED'], true))
        return ['state' => 'dead', 'code' => '', 'n' => $seen, 'err' => ''];

    $sms = is_array($r['sms'] ?? null) ? array_values($r['sms']) : [];
    if (count($sms) <= $seen)
        return ['state' => 'waiting', 'code' => '', 'n' => $seen, 'err' => ''];

    $code = numExtractCode($sms[count($sms) - 1]);
    if ($code === '') return ['state' => 'waiting', 'code' => '', 'n' => $seen, 'err' => ''];
    return ['state' => 'done', 'code' => $code, 'n' => count($sms), 'err' => ''];
}

function numOpDo($op, $aid) {
    [$r, $e] = numCall($op, ['id' => $aid]);
    if (!is_array($r)) return [false, $e ?: 'پاسخی نیامد'];
    if (numProv() === 'numberland' && ($x = numNlErr($r)) !== '') return [false, $x];
    return [true, ''];
}

function numBalanceDo() {
    [$r, $e] = numCall('balance');
    if (!is_array($r)) return [0.0, '', $e ?: 'پاسخی نیامد'];

    if (numProv() === 'numberland') {
        if (($x = numNlErr($r)) !== '') return [0.0, '', $x];
        foreach (['AMOUNT', 'BALANCE', 'CREDIT', 'amount', 'balance'] as $k)
            if (isset($r[$k]) && is_numeric($r[$k])) return [(float)$r[$k], 'تومان', ''];
        return [0.0, '', 'موجودی در پاسخ نامبرلند نبود'];
    }

    if (!isset($r['balance']) || !is_numeric($r['balance']))
        return [0.0, '', 'موجودی در پاسخ ۵سیم نبود'];
    return [(float)$r['balance'], (string)($r['currency'] ?? ''), ''];
}

function num5User($what, $limit = 8) {
    if (numProv() !== '5sim') return [null, 'این بخش فقط برای ۵سیم است'];
    $l = max(1, min(50, (int)$limit));
    $p = [
        'profile'  => '/user/profile',
        'orders'   => '/user/orders?category=activation&limit=' . $l . '&offset=0&order=id&reverse=true',
        'payments' => '/user/payments?limit=' . $l . '&offset=0&order=id&reverse=true',
        'flash'    => '/guest/flash/en',
    ][$what] ?? '';
    if ($p === '') return [null, 'نامعلوم'];
    return num5Get($p, min(10, (int)numVal('api.timeout', 15)));
}

function num5StatusFa($st) {
    return [
        'PENDING' => '⏳ منتظرِ کد', 'RECEIVED' => '📩 کد رسید', 'FINISHED' => '✅ تمام‌شده',
        'CANCELED' => '↩️ لغو', 'CANCELLED' => '↩️ لغو', 'TIMEOUT' => '⌛️ بی‌کد تمام شد', 'BANNED' => '⛔️ مسدود',
    ][strtoupper((string)$st)] ?? h((string)$st);
}

function num5Time($iso) {
    $t = strtotime((string)$iso);
    return $t ? date('m/d H:i', $t) : '—';
}

function numAdm5Acct($chatId, $msgId) {
    $back = [[btnCb('🔄 تازه کن', 'num5acct', 'confirm')], [btnCb('☎️ شماره مجازی', 'num_home', 'nav')]];
    if (numProv() !== '5sim' || numKey() === '') {
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "👤 <b>حسابِ ۵سیم</b>\n\n⚠️ فروشنده ۵سیم نیست یا توکنش ثبت نشده.\n" . numWebHint('فروشنده و توکن'),
            inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'nav')]]));
        return;
    }
    [$pr, $e] = num5User('profile');
    if (!is_array($pr)) {
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "👤 <b>حسابِ ۵سیم</b>\n\n❌ <code>" . h(mb_substr($e, 0, 300)) . "</code>",
            inlineKb(array_merge([[btnCb('🩺 عیب‌یابی اتصال', 'numdiag', 'confirm')]], $back)));
        return;
    }
    $rate = numRate();
    $bal  = (float)($pr['balance'] ?? 0);
    $frz  = (float)($pr['frozen_balance'] ?? 0);
    $rt   = (float)($pr['rating'] ?? 0);
    $t  = "👤 <b>حسابِ ۵سیم</b>\n\n";
    $t .= '🆔 <code>' . h((string)($pr['id'] ?? '—')) . '</code>' .
          (!empty($pr['email']) ? ' — ' . h((string)$pr['email']) : '') . "\n";
    $t .= '💰 موجودی: <b>' . fmtNum($bal) . '</b>' . ($rate > 0 ? ' ≈ ' . fmtNum(numRound100($bal * $rate)) . ' تومان' : '') . "\n";
    $t .= '🧊 بلوکه‌شده (شماره‌های باز): <b>' . fmtNum($frz) . "</b>\n";
    $t .= '⭐️ امتیاز: <b>' . fmtNum($rt) . "</b> از ۹۶\n";
    $dc = is_array($pr['default_country'] ?? null) ? (string)($pr['default_country']['name'] ?? '') : '';
    $do = is_array($pr['default_operator'] ?? null) ? (string)($pr['default_operator']['name'] ?? '') : '';
    if ($dc !== '' || $do !== '') $t .= '📍 پیش‌فرضِ حساب: ' . h(trim(($dc !== '' ? numCountryFa($dc) : '') . ' / ' . $do, ' /')) . "\n";
    if ($rt < 30)
        $t .= "\n⚠️ امتیاز پایین است. در ۵سیم هر لغو و هر شماره‌ی بی‌کد امتیاز کم می‌کند و هر کدِ " .
              "دریافت‌شده امتیاز می‌دهد. اگر امتیاز صفر شود، ۲۴ ساعت خرید بسته است.\n";
    if ($bal <= 0) $t .= "\n⚠️ موجودیِ ۵سیم صفر است؛ تا شارژ نشود خریدی انجام نمی‌شود.\n";

    [$od] = num5User('orders', 8);
    $list = is_array($od['Data'] ?? null) ? $od['Data'] : [];
    $t .= "\n📦 <b>آخرین سفارش‌ها روی ۵سیم</b>" . (isset($od['Total']) ? ' (' . fmtNum((int)$od['Total']) . ')' : '') . "\n";
    if (!$list) $t .= "—\n";
    foreach (array_slice($list, 0, 8) as $o) {
        $t .= '• <code>' . h((string)($o['phone'] ?? '')) . '</code> ' . num5StatusFa($o['status'] ?? '') .
              ' — ' . fmtNum((float)($o['price'] ?? 0)) . ' — ' . h(numCountryFa((string)($o['country'] ?? ''))) .
              ' — ' . num5Time($o['created_at'] ?? '') . "\n";
    }

    [$py] = num5User('payments', 5);
    $pl = is_array($py['Data'] ?? null) ? $py['Data'] : [];
    if ($pl) {
        $t .= "\n💳 <b>آخرین تراکنش‌های حساب</b>\n";
        foreach (array_slice($pl, 0, 5) as $x)
            $t .= '• ' . h((string)($x['TypeName'] ?? '')) . (!empty($x['ProviderName']) ? ' (' . h((string)$x['ProviderName']) . ')' : '') .
                  ': <b>' . fmtNum((float)($x['Amount'] ?? 0)) . '</b> — مانده ' . fmtNum((float)($x['Balance'] ?? 0)) .
                  ' — ' . num5Time($x['CreatedAt'] ?? '') . "\n";
    }

    [$fl] = num5User('flash');
    $ft = is_array($fl) ? trim(strip_tags((string)($fl['text'] ?? ''))) : '';
    if ($ft !== '') $t .= "\n📢 <b>اطلاعیه‌ی ۵سیم</b>\n<i>" . h(mb_substr($ft, 0, 300)) . "</i>\n";

    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3900), inlineKb($back));
}

function numParseTtl($v) {
    if (!is_scalar($v)) return 0;
    $v = trim((string)$v);
    if ($v === '') return 0;
    if (preg_match('/^\d+$/', $v)) return (int)$v;
    if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $v, $m))
        return (int)($m[1] ?? 0) * 3600 + (int)$m[2] * 60 + (int)$m[3];
    return 0;
}

function num5Probe($path, $withToken = true, $timeout = 12) {
    $url = numBase() . '/' . ltrim((string)$path, '/');
    if (function_exists('__num5ProbeHook')) return __num5ProbeHook($url, $withToken);

    $hdr = ['Accept: application/json'];
    if ($withToken) {
        $key = numKey();
        if ($key !== '') {
            if (numProv() === 'numberland')
                $url .= (str_contains($url, '?') ? '&' : '?') . 'apikey=' . rawurlencode($key);
            else
                $hdr[] = 'Authorization: Bearer ' . $key;
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max(4, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => 8,
CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
        CURLOPT_HTTPHEADER     => $hdr,
    ]);
    $body = monCurl($ch, 'numprov');
    $out  = [
        'code'  => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'err'   => (string)curl_error($ch),
        'errno' => (int)curl_errno($ch),
        'body'  => $body === false ? '' : (string)$body,
        'dns'   => (float)curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
        'conn'  => (float)curl_getinfo($ch, CURLINFO_CONNECT_TIME),
        'total' => (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        'ip'    => (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP),
    ];
    curl_close($ch);
    return $out;
}

function numTokenShape($tok = null) {
    $t = $tok === null ? trim((string)numVal('api.token', '')) : trim((string)$tok);

    if ($t === '')
        return ['ok' => false, 'kind' => 'empty', 'why' => 'هنوز چیزی ثبت نشده'];

    if (preg_match('/^[0-9a-f]{32,64}$/i', $t))
        return ['ok' => false, 'kind' => 'old',
                'why' => 'این کلیدِ «API قدیمی» است (رشته‌ی هگز). این ربات با API نسخه‌ی ۱ کار می‌کند — ' .
                         'کلیدِ بالاییِ صفحه را بفرستید، همان که با <code>eyJ</code> شروع می‌شود.'];

    if (!str_starts_with($t, 'eyJ'))
        return ['ok' => false, 'kind' => 'weird',
                'why' => 'توکنِ ۵سیم همیشه با <code>eyJ</code> شروع می‌شود. شاید ناقص کپی شده باشد.'];

    if (substr_count($t, '.') !== 2)
        return ['ok' => false, 'kind' => 'cut',
                'why' => 'توکن ناقص است — باید سه تکه باشد که با نقطه از هم جدا شده‌اند. ' .
                         'روی دکمه‌ی کپیِ کنارِ کادر بزنید، نه اینکه دستی انتخابش کنید.'];

    $exp = 0;
    $mid = explode('.', $t)[1] ?? '';
    $pad = strtr($mid, '-_', '+/');
    $j   = json_decode((string)base64_decode($pad . str_repeat('=', (4 - strlen($pad) % 4) % 4)), true);
    if (is_array($j) && !empty($j['exp'])) $exp = (int)$j['exp'];
    if ($exp > 0 && $exp < time())
        return ['ok' => false, 'kind' => 'expired',
                'why' => 'این توکن در ' . date('Y/m/d', $exp) . ' منقضی شده. ' .
                         'در ۵سیم «Refresh key» را بزنید و کلیدِ تازه را بفرستید.'];

    return ['ok' => true, 'kind' => 'jwt',
            'why' => $exp > 0 ? 'معتبر تا ' . date('Y/m/d', $exp) : 'شکلش درست است'];
}

function numBalance() { return numBalanceDo(); }


function numActsDbPath() { return DATA_DIR . '/num_acts.sqlite'; }

function numActsDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = numActsDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[numbers] num_acts.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS num_acts (
        id TEXT PRIMARY KEY, uid INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT \'\', created INTEGER NOT NULL DEFAULT 0,
        data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_num_acts_uid ON num_acts(uid)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_num_acts_status ON num_acts(status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_num_acts_created ON num_acts(created)');

    if ($fresh) numActsImportFromJson($db);
    return $db;
}

function numActsImportFromJson($db) {
    $old = dataPath('num_acts');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO num_acts (id, uid, status, created, data) VALUES (:id, :uid, :status, :created, :data)');
        foreach ($arr as $k => $v) {
            $id = (string)$k;
            if ($id === '' || !is_array($v)) continue;
            $stmt->bindValue(':id', $id, SQLITE3_TEXT);
            $stmt->bindValue(':uid', (int)($v['uid'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':status', (string)($v['status'] ?? ''), SQLITE3_TEXT);
            $stmt->bindValue(':created', (int)($v['created'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function numAll() {
    $db = numActsDb();
    if (!$db) return [];
    $out = [];
    $res = $db->query('SELECT id, data FROM num_acts');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[(string)$row['id']] = $d;
    }
    return $out;
}

function numGet($orderId) {
    $db = numActsDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM num_acts WHERE id = :id');
    $stmt->bindValue(':id', (string)$orderId, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function numSetAct($orderId, callable $fn) {
    $db = numActsDb();
    if (!$db) return false;
    $id = (string)$orderId;

    if (!@$db->exec('BEGIN IMMEDIATE')) return false;
    try {
        $stmt = $db->prepare('SELECT data FROM num_acts WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $x = $row ? json_decode($row['data'], true) : null;
        if (!is_array($x)) { $db->exec('ROLLBACK'); return false; }

        $result = $fn($x);

        $up = $db->prepare('UPDATE num_acts SET uid = :uid, status = :status, created = :created, data = :data WHERE id = :id');
        $up->bindValue(':uid', (int)($x['uid'] ?? 0), SQLITE3_INTEGER);
        $up->bindValue(':status', (string)($x['status'] ?? ''), SQLITE3_TEXT);
        $up->bindValue(':created', (int)($x['created'] ?? 0), SQLITE3_INTEGER);
        $up->bindValue(':data', json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->bindValue(':id', $id, SQLITE3_TEXT);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
        return $result;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[numbers] numSetAct خطا: ' . $e->getMessage());
        return false;
    }
}

function numActsClaim($orderId) {
    $db = numActsDb();
    if (!$db) return false;
    $id = (string)$orderId;
    try {
        $stmt = $db->prepare('INSERT OR IGNORE INTO num_acts (id, uid, status, created, data) VALUES (:id, 0, \'buying\', :created, :data)');
        $now = time();
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':created', $now, SQLITE3_INTEGER);
        $stmt->bindValue(':data', json_encode(['order' => $id, 'status' => 'buying', 'created' => $now],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $stmt->execute();
        return $db->changes() > 0;
    } catch (Throwable $e) {
        error_log('[numbers] numActsClaim خطا: ' . $e->getMessage());
        return false;
    }
}

function numActsDelete($orderId) {
    $db = numActsDb();
    if (!$db) return;
    $stmt = $db->prepare('DELETE FROM num_acts WHERE id = :id');
    $stmt->bindValue(':id', (string)$orderId, SQLITE3_TEXT);
    $stmt->execute();
}

function numPut(array $act) {
    $db = numActsDb();
    if (!$db) return;
    try {
        $stmt = $db->prepare('INSERT OR REPLACE INTO num_acts (id, uid, status, created, data) VALUES (:id, :uid, :status, :created, :data)');
        $stmt->bindValue(':id', (string)$act['order'], SQLITE3_TEXT);
        $stmt->bindValue(':uid', (int)($act['uid'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(':status', (string)($act['status'] ?? ''), SQLITE3_TEXT);
        $stmt->bindValue(':created', (int)($act['created'] ?? 0), SQLITE3_INTEGER);
        $stmt->bindValue(':data', json_encode($act, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('[numbers] numPut خطا: ' . $e->getMessage());
        return;
    }

    $n = (int)$db->querySingle('SELECT COUNT(*) FROM num_acts');
    if ($n > 500) {
        $stmt = $db->prepare("DELETE FROM num_acts WHERE status != 'waiting' AND created < :cut");
        $stmt->bindValue(':cut', time() - 172800, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

function numCanRepeat($act, $left = null) {
    if (!is_array($act)) return false;
    if (($act['status'] ?? '') !== 'done') return false;
    if (!empty($act['closed'])) return false;
    if ($left === null)
        $left = numWaitFor($act) - (time() - (int)($act['created'] ?? 0));
    return $left > 0;
}

function numItemMeta($itemId) {
    $i = function_exists('maFindItem') ? maFindItem($itemId) : null;
    if (!$i) return null;

    $sid = (string)($i['svc'] ?? '');
    $prod = (string)($i['pr'] ?? '') ?: numProdOfSid($sid);
    $raw = str_contains($sid, '~') ? substr($sid, strpos($sid, '~') + 1) : $sid;
    $co  = $op = '';
    if (str_contains($raw, '|')) [$co, $op] = explode('|', $raw, 2);
    else                          $op = $raw;

    $c = maFindCat((string)($i['cat'] ?? ''));
    if ($c && trim((string)($c['code'] ?? '')) !== '') $co = trim((string)$c['code']);
    return ['operator' => $op, 'country' => $co, 'sid' => $sid, 'product' => $prod,
            'prov' => (string)($i['prov'] ?? ''), 'item' => $i];
}


if (!defined('NUM_BUY_TTL')) define('NUM_BUY_TTL', 120);

function numBuy($order) {
    if (!is_array($order)) return [false, 'سفارش پیدا نشد'];
    $oid = (string)$order['id'];

    $existing = numGet($oid);
    if ($existing) {
        if (($existing['status'] ?? '') === 'buying' &&
            time() - (int)($existing['created'] ?? 0) >= NUM_BUY_TTL) {
            return [false, 'خریدِ قبلیِ این سفارش کامل نشد — به‌زودی هزینه برمی‌گردد'];
        }
        return [true, ''];
    }
    if (!numReady())  return [false, 'اتصال به ' . numProvName() . ' تنظیم نشده است'];

    $meta = numItemMeta((string)($order['item_id'] ?? ''));
    if (!$meta) return [false, 'این شماره دیگر تعریف نشده است'];

    if ($meta['prov'] !== '' && $meta['prov'] !== numProv())
        return [false, 'این ردیف مالِ فروشنده‌ی قبلی است — یک بار «وارد کردن» را بزنید'];

    if (numProv() === 'numberland') {
        if (trim((string)$meta['sid']) === '') return [false, 'شناسه‌ی این ردیف تنظیم نشده است'];
    } elseif ($meta['country'] === '') {
        return [false, 'کشور این ردیف تنظیم نشده است'];
    }

    if (!numActsClaim($oid)) return [true, ''];

    $r = numBuyDo($meta);
    if (($r['err'] ?? '') !== '') {
        numActsDelete($oid);
        return [false, $r['err']];
    }

    $ttl = (int)($r['ttl'] ?? 0);

    numPut([
        'order'    => $oid,
        'uid'      => (int)($order['user_id'] ?? 0),
        'item'     => (string)($order['item_id'] ?? ''),
        'name'     => (string)($order['item_name'] ?? ''),
        'emoji'    => (string)($order['item_emoji'] ?? '☎️'),
        'prov'     => numProv(),
        'operator' => (string)($r['operator'] ?? ''),
        'country'  => (string)($r['country'] ?? ''),
        'aid'      => (string)$r['aid'],
        'phone'    => numPhone((string)$r['phone']),
        'code'     => '',
        'status'   => 'waiting',
        'price'    => (float)($order['total'] ?? 0),
        'cost'     => (float)($r['cost'] ?? 0),
        'created'  => time(),
        'checked'  => 0,
        'wait'     => $ttl >= 60 ? $ttl : 0,
        'sms_seen' => 0,
        'repeats'  => 0,
    ]);
    return [true, ''];
}

function numExpiresIn($iso) {
    $iso = trim((string)$iso);
    if ($iso === '') return 0;
    $t = strtotime($iso);
    if ($t === false) return 0;
    return max(0, $t - time());
}

function numPhone($s) {
    $d = preg_replace('/\D+/', '', (string)$s);
    return $d === '' ? '' : '+' . $d;
}


function numState($orderId, $force = false) {
    $act = numGet($orderId);
    if (!$act) return null;

    $wait = numWaitFor($act);
    $left = max(0, $wait - (time() - (int)$act['created']));

    if ($act['status'] === 'waiting') {
        if ($left <= 0) {
            numFinish($orderId, 'expired');
            $act = numGet($orderId) ?: $act;
        } else {
            $gap = max(3, (int)numVal('poll', 6));
            if ($force || (time() - (int)($act['checked'] ?? 0)) >= $gap) {
                numPoll($orderId);
                $act = numGet($orderId) ?: $act;
                $left = max(0, $wait - (time() - (int)$act['created']));
            }
        }
    }

    return [
        'order'  => (string)$act['order'],
        'phone'  => (string)($act['phone'] ?? ''),
        'code'   => (string)($act['code'] ?? ''),
        'status' => (string)$act['status'],
        'left'   => (int)$left,
        'wait'   => $wait,
        'name'   => (string)($act['name'] ?? ''),
        'repeat' => numCanRepeat($act, $left) ? 1 : 0,
        'repeats'=> (int)($act['repeats'] ?? 0),
    ];
}

function numPoll($orderId) {
    $act = numGet($orderId);
    if (!$act || $act['status'] !== 'waiting') return;

    numSetAct($orderId, function (&$x) { $x['checked'] = time(); return true; });

    $r = numCheckDo((string)$act['aid'], (int)($act['sms_seen'] ?? 0));

    if ($r['state'] === 'dead') { numFinish($orderId, 'expired', false); return; }
    if ($r['state'] !== 'done' || $r['code'] === '') return;

    $code = $r['code'];
    $n    = (int)$r['n'];
    $got  = numSetAct($orderId, function (&$x) use ($code, $n) {
        if ($x['status'] !== 'waiting') return false;
        $x['code'] = $code; $x['status'] = 'done'; $x['sms_seen'] = $n;
        return true;
    });
    if ($got) numOrderDone($orderId);
}

function numExtractCode($sms) {
    if (!is_array($sms)) return '';
    $c = trim((string)($sms['code'] ?? ''));
    if ($c !== '' && strtolower($c) !== 'null') return $c;

    $txt = (string)($sms['text'] ?? '');
    if (preg_match_all('/\d{3,8}/', $txt, $m)) {
        usort($m[0], fn($a, $b) => strlen($b) <=> strlen($a));
        return $m[0][0];
    }
    return '';
}

function numWaitFor($act) {
    $own = (int)($act['wait'] ?? 0);
    if ($own >= 60) return $own;
    return max(60, (int)numVal('wait', 900));
}

function numOrderDone($orderId) {
    if (!class_exists('MaOrder')) return;
    $first = (bool)MaOrder::set($orderId, function (&$x) {
        if (($x['status'] ?? '') === MaOrder::DONE) return false;
        $x['status']       = MaOrder::DONE;
        $x['delivered_at'] = nowStr();
        $x['last_error']   = '';
        return true;
    });
    if (function_exists('maOrderDelivered')) maOrderDelivered($orderId, $first);
}

function numClose($orderId) {
    $act = numGet($orderId);
    if (!$act || empty($act['aid']) || !empty($act['closed'])) return;

    numTellPanelCancel($orderId);
}


function numFinish($orderId, $why = 'cancel', $tellPanel = true) {
    $done = false;
    numSetAct($orderId, function (&$x) use ($why, &$done) {
        if ($x['status'] !== 'waiting' && $x['status'] !== 'buying') return false;
        $x['status'] = $why;
        $x['ended']  = time();
        $done = true;
        return true;
    });
    if (!$done) return [false, 'این شماره قبلا بسته شده'];

    if ($tellPanel) numTellPanelCancel($orderId);

    if (function_exists('maRefundOrder'))
        maRefundOrder($orderId, $why === 'expired' ? 'کدی تا پایانِ مهلت نرسید' : 'شماره لغو شد');
    return [true, ''];
}

function numRepeat($orderId) {
    $act = numGet($orderId);
    if (!$act)               return [false, 'این شماره پیدا نشد'];
    if (!numCanRepeat($act)) return [false, 'الان نمی‌شود کدِ دوباره گرفت'];

    $claimed = false;
    numSetAct($orderId, function (&$x) use (&$claimed) {
        if (($x['status'] ?? '') !== 'done') return false;
        $x['status']  = 'waiting';
        $x['code']    = '';
        $x['checked'] = 0;
        $x['repeats'] = (int)($x['repeats'] ?? 0) + 1;
        $claimed = true;
        return true;
    });
    if (!$claimed) return [false, 'همین الان درخواست داده شد'];

    if (numProv() === 'numberland') {
        [$ok, $e] = numOpDo('repeat', (string)$act['aid']);
        if (!$ok) {
            numSetAct($orderId, function (&$x) use ($act) {
                if (($x['status'] ?? '') !== 'waiting') return false;
                $x['status']  = 'done';
                $x['code']    = (string)($act['code'] ?? '');
                $x['repeats'] = max(0, (int)($x['repeats'] ?? 1) - 1);
                return true;
            });
            return [false, $e];
        }
    }
    return [true, ''];
}

function numForUid($uid, $limit = 20) {
    $db = numActsDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT data FROM num_acts WHERE uid = :u ORDER BY created DESC LIMIT :n');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':n', max(1, (int)$limit), SQLITE3_INTEGER);
    $out = [];
    $res = $st->execute();
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[] = $d;
    }
    return $out;
}

function numOpenFor($uid) {
    $out = [];
    foreach (numForUid($uid, 15) as $act) {
        $st = (string)($act['status'] ?? '');
        if ($st === 'waiting' || $st === 'buying' || ($st === 'done' && numCanRepeat($act))) $out[] = $act;
    }
    return $out;
}

function numActiveCount($uid) {
    $db = numActsDb();
    if (!$db) return 0;
    $st = $db->prepare("SELECT COUNT(*) AS n FROM num_acts WHERE uid = :u AND status IN ('waiting','buying')");
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    return (int)(($st->execute()->fetchArray(SQLITE3_ASSOC))['n'] ?? 0);
}

function numTellPanelCancel($orderId) {
    $act = numGet($orderId);
    if (!$act || empty($act['aid'])) return [true, ''];
    if (!empty($act['panel_closed'])) return [true, ''];

    $aid = (string)$act['aid'];

    $state = numPanelState($aid);
    if ($state === 'closed') {
        numSetAct($orderId, function (&$x) {
            $x['panel_err'] = '';
            $x['panel_closed'] = time();
            $x['closed'] = time();
            unset($x['panel_pending']);
            return true;
        });
        return [true, ''];
    }

    $ops  = ($state === 'sms') ? ['close', 'cancel'] : ['cancel', 'close'];
    $fail = '';
    foreach ($ops as $op) {
        [$ok, $e] = numOpDo($op, $aid);
        if ($ok) { $fail = ''; break; }
        if ($fail === '') $fail = $e ?: 'پاسخی نیامد';
    }

    if ($fail !== '' && numPanelState($aid) === 'closed') $fail = '';

    numSetAct($orderId, function (&$x) use ($fail) {
        $x['panel_err']   = $fail;
        $x['panel_tries'] = (int)($x['panel_tries'] ?? 0) + 1;
        if ($fail === '') {
            $x['panel_closed'] = time();
            $x['closed'] = time();
            unset($x['panel_pending']);
        } else {
            $x['panel_pending'] = time();
        }
        return true;
    });

    return [$fail === '', $fail];
}

function numPanelState($aid) {
    $aid = trim((string)$aid);
    if ($aid === '') return '';

    [$r, ] = numCall('status', ['id' => $aid]);
    if (!is_array($r)) return '';

    if (numProv() === 'numberland') {
        $st = $r['RESULT'] ?? null;
        if (!is_numeric($st)) return '';
        $st = (int)$st;

        if ($st < 0) {
            $d = trim((string)($r['DESCRIPTION'] ?? ''));
            foreach (['پیدا نشد', 'یافت نشد', 'وجود ندارد', 'not found', 'invalid id'] as $needle)
                if (stripos($d, $needle) !== false) return 'closed';
            return '';
        }

        if (in_array($st, [3, 4, 6], true)) return 'closed';
        if (in_array($st, [2, 5], true))    return 'sms';
        if ($st === 1)                      return 'open';
        return '';
    }

    $st = strtoupper(trim((string)($r['status'] ?? '')));
    if (in_array($st, ['CANCELED', 'CANCELLED', 'TIMEOUT', 'FINISHED', 'BANNED'], true)) return 'closed';
    if (is_array($r['sms'] ?? null) && count($r['sms']) > 0) return 'sms';
    if ($st === 'RECEIVED') return 'sms';
    if ($st === 'PENDING')  return 'open';
    return '';
}

function numPanelBackoff($tries) {
    static $steps = [15, 45, 120, 300, 600, 1800, 3600];
    $i = max(0, (int)$tries - 1);
    return $steps[min($i, count($steps) - 1)];
}

if (!defined('NUM_PANEL_TRIES')) define('NUM_PANEL_TRIES', 14);

function numRetryPanel($limit = 5) {
    $n = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        if (empty($act['panel_pending'])) continue;
        if (in_array(($act['status'] ?? ''), ['waiting', 'buying'], true)) continue;

        $tries = (int)($act['panel_tries'] ?? 0);
        if ($tries >= NUM_PANEL_TRIES) continue;
        if (time() - (int)$act['panel_pending'] < numPanelBackoff($tries)) continue;

        $oid = (string)$act['order'];
        [$ok, $err] = numTellPanelCancel($oid);
        $n++;

        if (!$ok && $tries + 1 === 3 && function_exists('chTechAlert')) {
            chTechAlert(
                "⏳ <b>لغو روی " . h(numProvName()) . " هنوز نگرفته</b>\n\n" .
                '☎️ <code>' . h((string)($act['phone'] ?? '')) . "</code>\n" .
                '🧾 <code>' . h($oid) . "</code>\n" .
                '❌ <code>' . h(mb_substr((string)$err, 0, 200)) . "</code>\n\n" .
                "پول کاربر برگشته؛ ربات همچنان با فاصله‌ی بیشتر تلاش می‌کند. " .
                "اگر عجله دارید، دستی ببندیدش.",
                inlineKb([[btnCb('📋 شماره‌های باز', 'numopen', 'admin')]]));
        }

        if (!$ok && $tries + 1 >= NUM_PANEL_TRIES && function_exists('chTechAlert')) {
            chTechAlert(
                "⚠️ <b>لغو روی " . h(numProvName()) . " نگرفت</b>\n\n" .
                '☎️ <code>' . h((string)($act['phone'] ?? '')) . "</code>\n" .
                '🧾 <code>' . h($oid) . "</code>\n" .
                '❌ <code>' . h(mb_substr((string)$err, 0, 200)) . "</code>\n\n" .
                "پول کاربر برگشته، ولی این سفارش روی " . h(numProvName()) . " باز مانده. " .
                "دستی ببندیدش، وگرنه هزینه‌اش برنمی‌گردد.",
                inlineKb([[btnCb('📋 شماره‌های باز', 'numopen', 'admin')]]));
        }
    }
    return $n;
}

function numTick($limit = 10) {
    numRetryPanel(max(5, (int)($limit / 2)));
    $now = time();
    $n   = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        $st = (string)($act['status'] ?? '');
        $over = $now - (int)($act['created'] ?? 0) >= numWaitFor($act);

        if ($st === 'waiting') {
            if (!$over) continue;
            // One last look before refunding: a code that arrived in the final seconds
            // goes to the buyer instead of being paid to the provider while refunded here.
            numPoll((string)$act['order']);
            if ((string)((numGet((string)$act['order']) ?: [])['status'] ?? '') === 'waiting')
                numFinish((string)$act['order'], 'expired');
            $n++;
            continue;
        }
        if ($st === 'buying' && time() - (int)($act['created'] ?? 0) >= NUM_BUY_TTL) {
            $oid = (string)$act['order'];
            numFinish($oid, 'expired', false);
            $n++;
            if (function_exists('chTechAlert')) {
                chTechAlert(
                    "⏳ <b>خریدِ شماره کامل نشد</b>\n\n" .
                    '🧾 <code>' . h($oid) . "</code>\n\n" .
                    'درخواستِ خرید روی ' . h(numProvName()) . ' جواب نداد یا پردازش وسطِ کار متوقف شد. ' .
                    "هزینه به کیف پولِ کاربر برگشت. اگر این تکرار شد، اتصال به فروشنده را بررسی کنید.");
            }
            continue;
        }
        if ($st === 'done' && $over && empty($act['closed'])) {
            numClose((string)$act['order']);
            $n++;
        }
    }
    return $n;
}


function numRank($slug) {
    static $top = ['russia','ukraine','kazakhstan','england','usa','germany','france',
                   'netherlands','poland','romania','indonesia','philippines','vietnam',
                   'india','malaysia','thailand','turkey','spain','italy','portugal',
                   'sweden','finland','norway','denmark','austria','switzerland','belgium',
                   'ireland','czech','hungary','slovakia','bulgaria','serbia','croatia',
                   'greece','latvia','lithuania','estonia','moldova','georgia','armenia',
                   'azerbaijan','uzbekistan','kyrgyzstan','tajikistan','canada','brazil',
                   'mexico','argentina','colombia','southafrica','nigeria','kenya','egypt',
                   'morocco','israel','australia','newzealand','japan','hongkong',
                   'singapore','china','taiwan'];
    $k = strtolower(preg_replace('/[^a-z]/i', '', (string)$slug));
    $i = array_search($k, $top, true);
    return $i === false ? 500 : $i;
}

function numFlagIso($iso) {
    $iso = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$iso));
    if (strlen($iso) !== 2) return '🌍';
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $c = ord($iso[$i]) - 65;
        if ($c < 0 || $c > 25) return '🌍';
        $out .= mb_chr(0x1F1E6 + $c, 'UTF-8');
    }
    return $out;
}

function numCountryFa($slug, $en = '') {
    static $fa = [
        'russia'=>'روسیه','ukraine'=>'اوکراین','kazakhstan'=>'قزاقستان','england'=>'انگلیس',
        'usa'=>'آمریکا','germany'=>'آلمان','france'=>'فرانسه','netherlands'=>'هلند',
        'poland'=>'لهستان','romania'=>'رومانی','indonesia'=>'اندونزی','philippines'=>'فیلیپین',
        'vietnam'=>'ویتنام','india'=>'هند','malaysia'=>'مالزی','thailand'=>'تایلند',
        'turkey'=>'ترکیه','spain'=>'اسپانیا','italy'=>'ایتالیا','portugal'=>'پرتغال',
        'sweden'=>'سوئد','finland'=>'فنلاند','norway'=>'نروژ','denmark'=>'دانمارک',
        'austria'=>'اتریش','switzerland'=>'سوئیس','belgium'=>'بلژیک','ireland'=>'ایرلند',
        'czech'=>'چک','hungary'=>'مجارستان','slovakia'=>'اسلواکی','slovenia'=>'اسلوونی',
        'bulgaria'=>'بلغارستان','serbia'=>'صربستان','croatia'=>'کرواسی','greece'=>'یونان',
        'latvia'=>'لتونی','lithuania'=>'لیتوانی','estonia'=>'استونی','moldova'=>'مولداوی',
        'georgia'=>'گرجستان','armenia'=>'ارمنستان','azerbaijan'=>'آذربایجان',
        'uzbekistan'=>'ازبکستان','kyrgyzstan'=>'قرقیزستان','tajikistan'=>'تاجیکستان',
        'turkmenistan'=>'ترکمنستان','mongolia'=>'مغولستان','canada'=>'کانادا','brazil'=>'برزیل',
        'mexico'=>'مکزیک','argentina'=>'آرژانتین','colombia'=>'کلمبیا','chile'=>'شیلی',
        'peru'=>'پرو','venezuela'=>'ونزوئلا','ecuador'=>'اکوادور','bolivia'=>'بولیوی',
        'paraguay'=>'پاراگوئه','uruguay'=>'اروگوئه','southafrica'=>'آفریقای جنوبی',
        'nigeria'=>'نیجریه','kenya'=>'کنیا','egypt'=>'مصر','morocco'=>'مراکش',
        'tunisia'=>'تونس','algeria'=>'الجزایر','ghana'=>'غنا','ethiopia'=>'اتیوپی',
        'tanzania'=>'تانزانیا','uganda'=>'اوگاندا','senegal'=>'سنگال','cameroon'=>'کامرون',
        'israel'=>'اسرائیل','saudiarabia'=>'عربستان','uae'=>'امارات','qatar'=>'قطر',
        'kuwait'=>'کویت','oman'=>'عمان','bahrain'=>'بحرین','jordan'=>'اردن',
        'iraq'=>'عراق','lebanon'=>'لبنان','afghanistan'=>'افغانستان','pakistan'=>'پاکستان',
        'bangladesh'=>'بنگلادش','srilanka'=>'سریلانکا','nepal'=>'نپال','myanmar'=>'میانمار',
        'cambodia'=>'کامبوج','laos'=>'لائوس','australia'=>'استرالیا','newzealand'=>'نیوزیلند',
        'japan'=>'ژاپن','china'=>'چین','hongkong'=>'هنگ‌کنگ','macau'=>'ماکائو',
        'taiwan'=>'تایوان','singapore'=>'سنگاپور','southkorea'=>'کره جنوبی',
        'belarus'=>'بلاروس','cyprus'=>'قبرس','malta'=>'مالت','iceland'=>'ایسلند',
        'luxembourg'=>'لوکزامبورگ','albania'=>'آلبانی','montenegro'=>'مونته‌نگرو',
        'northmacedonia'=>'مقدونیه','bih'=>'بوسنی','kosovo'=>'کوزوو',
        'puertorico'=>'پورتوریکو','dominicana'=>'دومینیکن','panama'=>'پاناما',
        'costarica'=>'کاستاریکا','guatemala'=>'گواتمالا','honduras'=>'هندوراس',
        'nicaragua'=>'نیکاراگوئه','elsalvador'=>'السالوادور','jamaica'=>'جامائیکا',
        'haiti'=>'هائیتی','cuba'=>'کوبا','gibraltar'=>'جبل‌الطارق',
    ];
    $k = strtolower(preg_replace('/[^a-z]/i', '', (string)$slug));
    if (isset($fa[$k])) return $fa[$k];
    $en = trim((string)$en);
    return $en !== '' ? $en : ucfirst((string)$slug);
}

function numOperFa($op) {
    $op = strtolower(trim((string)$op));
    if ($op === '' || $op === 'any' || $op === '0') return 'خودکار';
    if (preg_match('/^virtual(\d+)$/', $op, $m)) return 'مجازی ' . strtr($m[1], ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    if (preg_match('/^\d+$/', $op))
        return 'اپراتور ' . strtr($op, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴',
                                        '5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    static $map = ['beeline'=>'Beeline','mts'=>'MTS','megafon'=>'MegaFon','tele2'=>'Tele2',
                   'rostelecom'=>'Rostelecom','yota'=>'Yota','tinkoff'=>'Tinkoff',
                   'lifecell'=>'Lifecell','kyivstar'=>'Kyivstar','vodafone'=>'Vodafone',
                   'altel'=>'Altel','activ'=>'Activ','tele'=>'Tele'];
    return $map[$op] ?? ucfirst($op);
}

function numCatalog() {
    return numProv() === 'numberland' ? numCatalogNl() : numCatalog5();
}

function numCatalog5() {
    $rate = numRate();
    if ($rate <= 0)
        return [[], [], 'نرخِ تبدیل معلوم نیست — یا در همین صفحه نرخ را بگذارید، یا بخش قیمت‌گیری را روشن کنید'];

    $meta = [];
    [$co] = num5Get('/guest/countries', 30);
    foreach ((array)$co as $slug => $x) {
        if (!is_array($x)) continue;
        $iso = '';
        if (is_array($x['iso'] ?? null)) { $k = array_keys($x['iso']); $iso = (string)($k[0] ?? ''); }
        $fl = numFlagIso($iso);
        if ($fl === '🌍') $fl = numFlagFa((string)($x['text_en'] ?? ''), (string)$slug);
        $meta[(string)$slug] = [
            'fa'   => numCountryFa($slug, (string)($x['text_en'] ?? '')),
            'flag' => $fl,
        ];
    }

    $countries = $out = $errs = [];
    foreach (numProdsOn() as $prod) {
        $pi = numProducts()[$prod];
        [$px, $err] = num5Get('/guest/prices?product=' . rawurlencode($prod), 30);
        if (!is_array($px)) { $errs[] = $pi['fa'] . ': ' . ($err ?: 'فهرست قیمت‌ها نیامد'); continue; }
        $byCountry = (isset($px[$prod]) && is_array($px[$prod])) ? $px[$prod] : $px;
        $rows = [];
        foreach ($byCountry as $slug => $x) {
            $slug = (string)$slug;
            if (!is_array($x)) continue;
            $ops = (isset($x[$prod]) && is_array($x[$prod])) ? $x[$prod] : $x;
            $cName = $meta[$slug]['fa']   ?? numCountryFa($slug);
            $flag  = $meta[$slug]['flag'] ?? '🌍';
            if ($flag === '🌍') $flag = numFlagFa($slug, $cName);
            foreach ($ops as $op => $info) {
                if (!is_array($info) || !isset($info['cost'])) continue;
                $usd = (float)$info['cost'];
                $cnt = (int)($info['count'] ?? 0);
                if ($usd <= 0) continue;
                $rows[] = [
                    'sid'      => ($prod === 'telegram' ? '' : $prod . '~') . $slug . '|' . $op,
                    'prod'     => $prod,
                    'country'  => $slug,
                    'operator' => (string)$op,
                    'flag'     => $flag,
                    'cname'    => $cName,
                    'rank'     => numRank($slug),
                    'name'     => ($prod === 'telegram' ? '' : $pi['fa'] . ' · ') . numOperFa($op),
                    'usd'      => $usd,
                    'price'    => numToman($usd, 0, false),
                    'count'    => $cnt,
                    'rate'     => (float)($info['rate'] ?? 0),
                    'on'       => $cnt > 0,
                ];
            }
        }
        if (!$rows) { $errs[] = $pi['fa'] . ': شماره‌ای در پاسخ نبود'; continue; }
        if (!empty(numVal('pick', true))) $rows = numPick($rows, (int)numVal('pick_n', 12), (int)numVal('pick_ops', 1));
        foreach ($rows as $r) {
            $countries[$r['country']] = ['name' => $r['cname'], 'flag' => $r['flag']];
            $out[] = $r;
        }
    }

    if (!$out) return [[], [], $errs ? implode(' · ', $errs) : 'هیچ شماره‌ای در پاسخِ ۵سیم نبود'];

    usort($out, fn($x, $y) => [$x['rank'], $x['cname'], $x['prod'], $x['usd']]
                          <=> [$y['rank'], $y['cname'], $y['prod'], $y['usd']]);
    return [$countries, $out, ''];
}

function numPick(array $rows, $n = 12, $perCountry = 1) {
    $n = max(1, min(60, (int)$n));
    $perCountry = max(1, min(5, (int)$perCountry));
    $by = [];
    foreach ($rows as $r) if (!empty($r['on'])) $by[$r['country']][] = $r;
    $best = [];
    foreach ($by as $co => $list) {
        $maxRate = max(array_map(fn($r) => (float)$r['rate'], $list));
        $good = array_values(array_filter($list, fn($r) => (float)$r['rate'] >= $maxRate - 10));
        usort($good, fn($x, $y) => [(float)$x['usd'], -(float)$x['rate']] <=> [(float)$y['usd'], -(float)$y['rate']]);
        $pick = array_slice($good, 0, $perCountry);
        $top = $pick[0];
        $best[$co] = ['rows' => $pick, 'rate' => $maxRate, 'usd' => (float)$top['usd'], 'rank' => (int)$top['rank'], 'cnt' => (int)$top['count']];
    }
    uasort($best, function ($x, $y) {
        $sx = ($x['rank'] < 500 ? 30 : 0) + min(100, $x['rate']) + min(20, $x['cnt'] / 50);
        $sy = ($y['rank'] < 500 ? 30 : 0) + min(100, $y['rate']) + min(20, $y['cnt'] / 50);
        return [$sy, $x['usd']] <=> [$sx, $y['usd']];
    });
    $out = [];
    $k = 0;
    $all = !empty(numVal('all', true));
    foreach ($best as $b) {
        $feat = $k++ < $n;
        if (!$feat && !$all) break;
        foreach ($b['rows'] as $r) { $r['feat'] = $feat; $out[] = $r; }
    }
    return $out;
}

function numNlServices() {
    [$l] = num5Get('/v2.php/?method=getservice', 20);
    $out = [];
    foreach ((array)$l as $x) {
        if (!is_array($x) || !isset($x['id'])) continue;
        $out[(string)$x['id']] = trim((string)($x['name'] ?? '') . ' ' . (string)($x['name_en'] ?? '') . ' ' . (string)($x['title'] ?? ''));
    }
    return $out;
}

function numCatalogNl() {
    $svcs = [];
    $list = null;
    foreach (numProdsOn() as $prod) {
        $pi = numProducts()[$prod];
        $id = trim((string)numVal('api.' . $pi['nl'], $prod === 'telegram' ? '1' : ''));
        if ($id === '' ) {
            if ($list === null) $list = numNlServices();
            foreach ($list as $sid => $nm) if (preg_match($pi['rx'], $nm)) { $id = (string)$sid; break; }
        }
        if ($id !== '') $svcs[$prod] = $id;
    }
    if (!$svcs) return [[], [], 'شناسه‌ی سرویس‌های نامبرلند معلوم نیست'];

    $fa = $en = [];
    [$ct] = num5Get('/v2.php/?method=getcountry', 30);
    foreach ((array)$ct as $x) {
        if (!is_array($x) || !isset($x['id'])) continue;
        $fa[(string)$x['id']] = trim((string)($x['name'] ?? $x['name_en'] ?? $x['id']));
        $en[(string)$x['id']] = trim((string)($x['name_en'] ?? ''));
    }

    $countries = $out = $errs = [];
    foreach ($svcs as $prod => $svc) {
        $pi = numProducts()[$prod];
        [$rows, $err] = num5Get('/v2.php/?method=getinfo&operator=any&service=' . rawurlencode($svc), 30);
        if (!is_array($rows)) { $errs[] = $pi['fa'] . ': ' . ($err ?: 'فهرست نیامد'); continue; }
        if ($rows !== array_values($rows)) {
            $d = trim((string)($rows['DESCRIPTION'] ?? ''));
            $errs[] = $pi['fa'] . ': ' . ($d !== '' ? $d : 'پاسخ فهرست نبود');
            continue;
        }
        $mine = [];
        foreach ($rows as $r) {
            if (!is_array($r) || !isset($r['id'])) continue;
            $cc = (string)($r['country'] ?? '');
            $ss = (string)($r['service'] ?? '');
            if ($cc === '' || $ss !== $svc) continue;
            $cName = $fa[$cc] ?? ('کشور ' . $cc);
            $flag  = numFlagFa($en[$cc] ?? '', $cName);
            $op    = trim((string)($r['operator'] ?? ''));
            $price = (float)($r['amount'] ?? 0);
            $cnt   = (int)($r['count'] ?? 0);
            $mine[] = [
                'sid'      => (string)$r['id'],
                'prod'     => $prod,
                'country'  => $cc,
                'operator' => $op,
                'flag'     => $flag,
                'cname'    => $cName,
                'rank'     => numRank($en[$cc] ?? ''),
                'name'     => ($prod === 'telegram' ? '' : $pi['fa'] . ' · ') . numOperFa($op),
                'usd'      => $price,
                'price'    => $price,
                'count'    => $cnt,
                'rate'     => 0.0,
                'ttl'      => numParseTtl($r['time'] ?? ''),
                'on'       => (string)($r['active'] ?? '1') !== '0' && $cnt > 0,
            ];
        }
        if (!empty(numVal('pick', true))) $mine = numPick($mine, (int)numVal('pick_n', 12), (int)numVal('pick_ops', 1));
        foreach ($mine as $r) { $countries[$r['country']] = ['name' => $r['cname'], 'flag' => $r['flag']]; $out[] = $r; }
    }

    if (!$out) return [[], [], $errs ? implode(' · ', $errs) : 'هیچ شماره‌ای در پاسخِ نامبرلند نبود'];

    usort($out, fn($x, $y) => [$x['rank'], $x['cname'], $x['prod'], $x['price']]
                          <=> [$y['rank'], $y['cname'], $y['prod'], $y['price']]);
    return [$countries, $out, ''];
}

function numFlagFa($name, $alt = '') {
    foreach ([$name, $alt] as $cand) {
        $f = numFlagOne($cand);
        if ($f !== '🌍') return $f;
    }
    return '🌍';
}

function numFlagOne($name) {
    $raw = trim((string)$name);
    if ($raw === '') return '🌍';

    if (preg_match('/[\x{1F1E6}-\x{1F1FF}]{2}/u', $raw, $m)) return $m[0];

    $s = str_replace(['(', ')', '-', '_', '.', '،'], ' ', $raw);
    if (strpos($s, ',') !== false) {
        $parts = array_map('trim', explode(',', $s));
        $s = implode(' ', array_reverse($parts));
    }

    $k = numFlagKey($s);
    if ($k === '') return '🌍';

    $map = numFlagMap();
    if (isset($map[$k])) return numFlagIso($map[$k]);

    if (strlen($k) === 2 && ctype_alpha($k)) {
        $f = numFlagIso($k);
        if ($f !== '🌍') return $f;
    }

    $trim = preg_replace('/^(the|republicof|kingdomof|stateof|unitedstateof|کشور)/', '', $k);
    if ($trim !== $k && isset($map[$trim])) return numFlagIso($map[$trim]);

    $w = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY);
    $n = count($w);
    if ($n > 1)
        for ($len = $n; $len >= 1; $len--)
            for ($i = 0; $i + $len <= $n; $i++) {
                $kk = numFlagKey(implode('', array_slice($w, $i, $len)));
                if ($kk !== '' && isset($map[$kk])) return numFlagIso($map[$kk]);
            }

    return '🌍';
}

function numFlagKey($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    $s = strtr($s, ['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','أ'=>'ا','إ'=>'ا','آ'=>'ا','ؤ'=>'و','ئ'=>'ی','\u{200c}'=>'']);
    return preg_replace('/[^a-z\x{0600}-\x{06FF}]/u', '', $s);
}

function numFlagMap() {
    static $map = null;
    if ($map !== null) return $map;

    $en = [
        'RU'=>['russia','russianfederation','rusia'],
        'GW'=>['guineabissau'], 'CV'=>['capeverde','caboverde'], 'GQ'=>['equatorialguinea'],
        'LC'=>['saintlucia','stlucia'], 'KN'=>['saintkittsandnevis','saintkitts'], 'VC'=>['saintvincent','saintvincentandthegrenadines'],
        'ST'=>['saotomeandprincipe','saotome'], 'CF'=>['centralafricanrepublic','car'], 'SS'=>['southsudan'],
        'UA'=>['ukraine'],
        'KZ'=>['kazakhstan','kazakstan'],
        'GB'=>['england','unitedkingdom','uk','greatbritain','britain','scotland','wales'],
        'US'=>['usa','unitedstates','unitedstatesofamerica','america','us'],
        'DE'=>['germany','deutschland'],
        'FR'=>['france'],
        'NL'=>['netherlands','holland'],
        'PL'=>['poland'],
        'RO'=>['romania'],
        'ID'=>['indonesia'],
        'PH'=>['philippines','phillipines'],
        'VN'=>['vietnam','vietnam','vietnamese'],
        'IN'=>['india'],
        'MY'=>['malaysia'],
        'TH'=>['thailand'],
        'TR'=>['turkey','turkiye','türkiye'],
        'ES'=>['spain','espana'],
        'IT'=>['italy','italia'],
        'PT'=>['portugal'],
        'SE'=>['sweden'],
        'FI'=>['finland'],
        'NO'=>['norway'],
        'DK'=>['denmark'],
        'AT'=>['austria'],
        'CH'=>['switzerland'],
        'BE'=>['belgium'],
        'IE'=>['ireland'],
        'CZ'=>['czechia','czechrepublic','czech'],
        'HU'=>['hungary'],
        'SK'=>['slovakia'],
        'SI'=>['slovenia'],
        'BG'=>['bulgaria'],
        'RS'=>['serbia'],
        'HR'=>['croatia'],
        'GR'=>['greece'],
        'LV'=>['latvia'],
        'LT'=>['lithuania'],
        'EE'=>['estonia'],
        'MD'=>['moldova'],
        'GE'=>['georgia'],
        'AM'=>['armenia'],
        'AZ'=>['azerbaijan'],
        'UZ'=>['uzbekistan'],
        'KG'=>['kyrgyzstan','kirgizstan'],
        'TJ'=>['tajikistan'],
        'TM'=>['turkmenistan'],
        'MN'=>['mongolia'],
        'BY'=>['belarus'],
        'CY'=>['cyprus'],
        'MT'=>['malta'],
        'IS'=>['iceland'],
        'LU'=>['luxembourg'],
        'AL'=>['albania'],
        'ME'=>['montenegro'],
        'MK'=>['northmacedonia','macedonia'],
        'BA'=>['bosnia','bosniaandherzegovina','bih'],
        'XK'=>['kosovo'],
        'CA'=>['canada'],
        'BR'=>['brazil','brasil'],
        'MX'=>['mexico'],
        'AR'=>['argentina'],
        'CO'=>['colombia'],
        'CL'=>['chile'],
        'PE'=>['peru'],
        'VE'=>['venezuela'],
        'EC'=>['ecuador'],
        'BO'=>['bolivia'],
        'PY'=>['paraguay'],
        'UY'=>['uruguay'],
        'GT'=>['guatemala'],
        'HN'=>['honduras'],
        'NI'=>['nicaragua'],
        'SV'=>['elsalvador','salvador'],
        'CR'=>['costarica'],
        'PA'=>['panama'],
        'CU'=>['cuba'],
        'DO'=>['dominicanrepublic','dominicana','dominican'],
        'HT'=>['haiti'],
        'JM'=>['jamaica'],
        'TT'=>['trinidadandtobago','trinidad'],
        'PR'=>['puertorico'],
        'BZ'=>['belize'],
        'GY'=>['guyana'],
        'SR'=>['suriname'],
        'ZA'=>['southafrica'],
        'NG'=>['nigeria'],
        'KE'=>['kenya'],
        'EG'=>['egypt'],
        'MA'=>['morocco'],
        'TN'=>['tunisia'],
        'DZ'=>['algeria'],
        'LY'=>['libya'],
        'SD'=>['sudan'],
        'GH'=>['ghana'],
        'ET'=>['ethiopia'],
        'TZ'=>['tanzania'],
        'UG'=>['uganda'],
        'SN'=>['senegal'],
        'CM'=>['cameroon'],
        'CI'=>['ivorycoast','cotedivoire'],
        'ML'=>['mali'],
        'BF'=>['burkinafaso','burkina'],
        'NE'=>['niger'],
        'TD'=>['chad'],
        'GN'=>['guinea'],
        'BJ'=>['benin'],
        'TG'=>['togo'],
        'GA'=>['gabon'],
        'CG'=>['congo','republicofthecongo'],
        'CD'=>['drcongo','democraticrepublicofthecongo','congokinshasa'],
        'AO'=>['angola'],
        'ZM'=>['zambia'],
        'ZW'=>['zimbabwe'],
        'MZ'=>['mozambique'],
        'MW'=>['malawi'],
        'RW'=>['rwanda'],
        'BI'=>['burundi'],
        'SO'=>['somalia'],
        'MG'=>['madagascar'],
        'MU'=>['mauritius'],
        'NA'=>['namibia'],
        'BW'=>['botswana'],
        'SL'=>['sierraleone'],
        'LR'=>['liberia'],
        'GM'=>['gambia'],
        'MR'=>['mauritania'],
        'IL'=>['israel'],
        'SA'=>['saudiarabia','saudi'],
        'AE'=>['uae','unitedarabemirates','emirates'],
        'QA'=>['qatar'],
        'KW'=>['kuwait'],
        'OM'=>['oman'],
        'BH'=>['bahrain'],
        'JO'=>['jordan'],
        'IQ'=>['iraq'],
        'LB'=>['lebanon'],
        'SY'=>['syria'],
        'YE'=>['yemen'],
        'PS'=>['palestine'],
        'IR'=>['iran'],
        'AF'=>['afghanistan'],
        'PK'=>['pakistan'],
        'BD'=>['bangladesh'],
        'LK'=>['srilanka'],
        'NP'=>['nepal'],
        'BT'=>['bhutan'],
        'MV'=>['maldives'],
        'MM'=>['myanmar','burma'],
        'KH'=>['cambodia'],
        'LA'=>['laos'],
        'BN'=>['brunei'],
        'TL'=>['timorleste','easttimor'],
        'AU'=>['australia'],
        'NZ'=>['newzealand'],
        'PG'=>['papuanewguinea'],
        'FJ'=>['fiji'],
        'JP'=>['japan'],
        'CN'=>['china'],
        'HK'=>['hongkong'],
        'MO'=>['macau','macao'],
        'TW'=>['taiwan'],
        'SG'=>['singapore'],
        'KR'=>['southkorea','korea','republicofkorea','koreasouth'],
        'KP'=>['northkorea','koreanorth'],
        'GI'=>['gibraltar'],
        'MC'=>['monaco'],
        'AD'=>['andorra'],
        'SM'=>['sanmarino'],
        'LI'=>['liechtenstein'],
        'RE'=>['reunion'],
        'GP'=>['guadeloupe'],
        'MQ'=>['martinique'],
        'GF'=>['frenchguiana'],
        'NC'=>['newcaledonia'],
        'PF'=>['frenchpolynesia'],
        'VG'=>['britishvirginislands'],
        'KY'=>['caymanislands'],
        'BB'=>['barbados'],
        'BS'=>['bahamas'],
        'AW'=>['aruba'],
        'CW'=>['curacao'],
    ];

    $fa = [
        'RU'=>['روسیه'],            'UA'=>['اوکراین'],        'KZ'=>['قزاقستان'],
        'GB'=>['انگلیس','انگلستان','بریتانیا'],               'US'=>['امریکا','ایالاتمتحده'],
        'DE'=>['المان'],            'FR'=>['فرانسه'],         'NL'=>['هلند'],
        'PL'=>['لهستان'],           'RO'=>['رومانی'],         'ID'=>['اندونزی'],
        'PH'=>['فیلیپین'],          'VN'=>['ویتنام'],         'IN'=>['هند'],
        'MY'=>['مالزی'],            'TH'=>['تایلند'],         'TR'=>['ترکیه'],
        'ES'=>['اسپانیا'],          'IT'=>['ایتالیا'],        'PT'=>['پرتغال'],
        'SE'=>['سوئد'],             'FI'=>['فنلاند'],         'NO'=>['نروژ'],
        'DK'=>['دانمارک'],          'AT'=>['اتریش'],          'CH'=>['سوئیس'],
        'BE'=>['بلژیک'],            'IE'=>['ایرلند'],         'CZ'=>['چک','جمهوریچک'],
        'HU'=>['مجارستان'],         'SK'=>['اسلواکی'],        'SI'=>['اسلوونی'],
        'BG'=>['بلغارستان'],        'RS'=>['صربستان'],        'HR'=>['کرواسی'],
        'GR'=>['یونان'],            'LV'=>['لتونی'],          'LT'=>['لیتوانی'],
        'EE'=>['استونی'],           'MD'=>['مولداوی'],        'GE'=>['گرجستان'],
        'AM'=>['ارمنستان'],         'AZ'=>['اذربایجان'],      'UZ'=>['ازبکستان'],
        'KG'=>['قرقیزستان'],        'TJ'=>['تاجیکستان'],      'TM'=>['ترکمنستان'],
        'MN'=>['مغولستان'],         'BY'=>['بلاروس'],         'CY'=>['قبرس'],
        'MT'=>['مالت'],             'IS'=>['ایسلند'],         'LU'=>['لوکزامبورگ'],
        'AL'=>['البانی'],           'ME'=>['مونتهنگرو'],      'MK'=>['مقدونیه'],
        'BA'=>['بوسنی'],            'XK'=>['کوزوو'],          'CA'=>['کانادا'],
        'BR'=>['برزیل'],            'MX'=>['مکزیک'],          'AR'=>['ارژانتین'],
        'CO'=>['کلمبیا'],           'CL'=>['شیلی'],           'PE'=>['پرو'],
        'VE'=>['ونزوئلا'],          'EC'=>['اکوادور'],        'BO'=>['بولیوی'],
        'PY'=>['پاراگوئه'],         'UY'=>['اروگوئه'],        'GT'=>['گواتمالا'],
        'HN'=>['هندوراس'],          'NI'=>['نیکاراگوئه'],     'SV'=>['السالوادور'],
        'CR'=>['کاستاریکا'],        'PA'=>['پاناما'],         'CU'=>['کوبا'],
        'DO'=>['دومینیکن'],         'HT'=>['هائیتی'],         'JM'=>['جامائیکا'],
        'PR'=>['پورتوریکو'],        'ZA'=>['افریقایجنوبی'],   'NG'=>['نیجریه'],
        'KE'=>['کنیا'],             'EG'=>['مصر'],            'MA'=>['مراکش'],
        'TN'=>['تونس'],             'DZ'=>['الجزایر'],        'LY'=>['لیبی'],
        'SD'=>['سودان'],            'GH'=>['غنا'],            'ET'=>['اتیوپی'],
        'TZ'=>['تانزانیا'],         'UG'=>['اوگاندا'],        'SN'=>['سنگال'],
        'CM'=>['کامرون'],           'CI'=>['ساحلعاج'],        'ML'=>['مالی'],
        'AO'=>['انگولا'],           'ZM'=>['زامبیا'],         'ZW'=>['زیمبابوه'],
        'MZ'=>['موزامبیک'],         'RW'=>['رواندا'],         'SO'=>['سومالی'],
        'MG'=>['ماداگاسکار'],       'MU'=>['موریس'],          'NA'=>['نامیبیا'],
        'BW'=>['بوتسوانا'],         'IL'=>['اسرائیل'],        'SA'=>['عربستان'],
        'AE'=>['امارات'],           'QA'=>['قطر'],            'KW'=>['کویت'],
        'OM'=>['عمان'],             'BH'=>['بحرین'],          'JO'=>['اردن'],
        'IQ'=>['عراق'],             'LB'=>['لبنان'],          'SY'=>['سوریه'],
        'YE'=>['یمن'],              'PS'=>['فلسطین'],         'IR'=>['ایران'],
        'AF'=>['افغانستان'],        'PK'=>['پاکستان'],        'BD'=>['بنگلادش'],
        'LK'=>['سریلانکا'],         'NP'=>['نپال'],           'MV'=>['مالدیو'],
        'MM'=>['میانمار'],          'KH'=>['کامبوج'],         'LA'=>['لائوس'],
        'BN'=>['برونئی'],           'AU'=>['استرالیا'],       'NZ'=>['نیوزیلند'],
        'FJ'=>['فیجی'],             'JP'=>['ژاپن'],           'CN'=>['چین'],
        'HK'=>['هنگکنگ'],           'MO'=>['ماکائو'],         'TW'=>['تایوان'],
        'SG'=>['سنگاپور'],          'KR'=>['کرهجنوبی','کره'], 'KP'=>['کرهشمالی'],
        'GI'=>['جبلالطارق'],        'MC'=>['موناکو'],         'AD'=>['اندورا'],
        'SM'=>['سنمارینو'],         'LI'=>['لیختناشتاین'],
    ];

    $map = [];
    foreach ([$en, $fa] as $tbl)
        foreach ($tbl as $iso => $names)
            foreach ($names as $n) {
                $k = numFlagKey($n);
                if ($k !== '' && !isset($map[$k])) $map[$k] = $iso;
            }
    return $map;
}

function numImport(array $countries, array $rows, $markup = 0, $syncPrice = null) {
    $newC = $newI = $upd = $off = 0;
    $mul  = 1 + (max(0, (float)$markup) / 100);
    if ($syncPrice === null) $syncPrice = !empty(numVal('sync_price', true));

    maSetRoot(function (&$a) use ($countries, $rows, $mul, $syncPrice,
                                     &$newC, &$newI, &$upd, &$off) {
        if (!is_array($a['cats'] ?? null))  $a['cats']  = [];
        if (!is_array($a['items'] ?? null)) $a['items'] = [];

        $byCode = [];
        foreach ($a['cats'] as $i => $c) {
            $code = trim((string)($c['code'] ?? ''));
            if ($code !== '') $byCode[$code] = $i;
        }
        $order = count($a['cats']);
        foreach ($countries as $code => $info) {
            $code = (string)$code;
            if (isset($byCode[$code])) {
                $k   = $byCode[$code];
                $now = trim((string)($a['cats'][$k]['emoji'] ?? ''));
                if (($now === '' || $now === '🌍') && ($info['flag'] ?? '🌍') !== '🌍')
                    $a['cats'][$k]['emoji'] = $info['flag'];
                continue;
            }
            $a['cats'][] = [
                'id' => 'c' . bin2hex(random_bytes(3)), 'emoji' => $info['flag'],
                'name' => $info['name'], 'code' => $code, 'on' => true, 'order' => ++$order,
            ];
            $byCode[$code] = count($a['cats']) - 1;
            $newC++;
        }

        $bySid = [];
        foreach ($a['items'] as $i => $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            if ($svc !== '') $bySid[$svc] = $i;
        }
        $seen = [];
        $iOrder = 0;
        foreach ($rows as $r) {
            $iOrder++;
            $seen[$r['sid']] = true;
            $catIdx = $byCode[$r['country']] ?? null;
            $catId  = $catIdx !== null ? (string)$a['cats'][$catIdx]['id'] : '';

            if (isset($bySid[$r['sid']])) {
                $k = $bySid[$r['sid']];
                $was = !empty($a['items'][$k]['on']);
                $a['items'][$k]['on'] = (bool)$r['on'];
                if ($catId !== '') $a['items'][$k]['cat'] = $catId;
                $a['items'][$k]['prov'] = numProv();
                $a['items'][$k]['pr'] = (string)($r['prod'] ?? 'telegram');
                $a['items'][$k]['hot'] = !empty($r['feat']);
                $ne = trim((string)($a['items'][$k]['emoji'] ?? ''));
                if (($ne === '' || $ne === '🌍' || $ne === '☎️') && (string)($r['flag'] ?? '🌍') !== '🌍')
                    $a['items'][$k]['emoji'] = (string)$r['flag'];
                if ($syncPrice) $a['items'][$k]['price'] = numRound100($r['price'] * $mul);
                $a['items'][$k]['order'] = $iOrder;
                if ($was && !$r['on']) $off++; else $upd++;
                continue;
            }

            $a['items'][] = [
                'id' => 'i' . bin2hex(random_bytes(3)), 'cat' => $catId, 'svc' => $r['sid'],
                'prov' => numProv(), 'pr' => (string)($r['prod'] ?? 'telegram'), 'hot' => !empty($r['feat']),
                'emoji' => (string)($r['flag'] ?? '☎️'),
                'name' => $r['name'],
                'price' => numRound100($r['price'] * $mul), 'badge' => '',
                'on' => (bool)$r['on'], 'order' => $iOrder,
            ];
            $newI++;
        }

        foreach ($a['items'] as $k => $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            if ($svc === '' || isset($seen[$svc]) || empty($it['on'])) continue;
            $a['items'][$k]['on'] = false;
            $off++;
        }
    });

    return [$newC, $newI, $upd, $off];
}

function numPruneCatalog(array $keepSids) {
    $keep = array_flip(array_map('strval', $keepSids));
    $delI = $delC = 0;

    maSetRoot(function (&$a) use ($keep, &$delI, &$delC) {
        $items = [];
        foreach ((array)($a['items'] ?? []) as $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            if ($svc === '' || isset($keep[$svc])) { $items[] = $it; continue; }
            $delI++;
        }
        $a['items'] = array_values($items);

        $used = [];
        foreach ($a['items'] as $it) $used[(string)($it['cat'] ?? '')] = 1;
        $cats = [];
        foreach ((array)($a['cats'] ?? []) as $c) {
            $id = (string)($c['id'] ?? '');
            if (trim((string)($c['code'] ?? '')) !== '' && !isset($used[$id])) { $delC++; continue; }
            $cats[] = $c;
        }
        $a['cats'] = array_values($cats);
    });
    return [$delI, $delC];
}

function numForceTelegramOnly() {
    numSet(function (&$c) {
        foreach (['svc_only', 'preset'] as $k) unset($c[$k]);
        if (is_array($c['api'] ?? null))
            foreach (['ops','auth_type','auth_key','auth_value','spec_url','name','preset','preset_key'] as $k)
                unset($c['api'][$k]);
    });

    $delI = $delC = 0;
    maSetRoot(function (&$a) use (&$delI, &$delC) {
        $keep = [];
        foreach ((array)($a['items'] ?? []) as $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            $pv  = trim((string)($it['prov'] ?? ''));
            $mine = $pv !== '' ? ($pv === numProv())
                               : (numProv() === '5sim' ? str_contains($svc, '|')
                                                       : ctype_digit($svc));
            if ($svc === '' || $mine) { $keep[] = $it; continue; }
            $delI++;
        }
        $a['items'] = array_values($keep);

        $used = [];
        foreach ($a['items'] as $it) $used[(string)($it['cat'] ?? '')] = 1;
        $cats = [];
        foreach ((array)($a['cats'] ?? []) as $c) {
            if (trim((string)($c['code'] ?? '')) !== '' && !isset($used[(string)($c['id'] ?? '')])) { $delC++; continue; }
            $cats[] = $c;
        }
        $a['cats'] = array_values($cats);
    });

    if ($delI > 0 && function_exists('notifyAdmins')) {
        notifyAdmins(
            "☎️ <b>ربات به ۵سیم وصل شد</b>\n\n" .
            "🗑 <b>" . fmtNum($delI) . "</b> شماره و <b>" . fmtNum($delC) . "</b> پوشه‌ی پنلِ قبلی حذف شد.\n\n" .
            "🔑 توکن ۵سیم · 📥 وارد کردن",
            inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
    }
    return [$delI, $delC];
}


function numAdmHome($chatId, $msgId) {
    $api  = numVal('api', []);
    $open = $stuck = 0;
    foreach (numAll() as $a) {
        if (($a['status'] ?? '') === 'waiting') $open++;
        if (!empty($a['panel_pending']) && !in_array(($a['status'] ?? ''), ['waiting','buying'], true)) $stuck++;
    }

    $tok  = numKey();
    $rate = numRate();
    $mk   = (float)numVal('markup', 0);
    $pi   = numProvInfo();

    $t  = "☎️ <b>شماره مجازی تلگرام</b>\n\n";
    $t .= "🏪 فروشنده: <b>" . h($pi['label']) . "</b>\n";
    $t .= "وضعیت: " . (!empty($api['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $sh = numTokenShape();
    $t .= "🔑 " . h($pi['key']) . ": " . ($tok !== ''
          ? ($sh['ok'] ? '✅ ثبت شده (' . fmtNum(strlen($tok)) . ' حرف)' : '⚠️ مشکل دارد')
          : '<b>خالی</b>') . "\n";
    if ($tok !== '' && !$sh['ok']) $t .= '<i>' . $sh['why'] . "</i>\n";
    $t .= "🎯 محصول: <b>تلگرام</b> — همین و بس\n\n";

    $t .= numNeedsRate()
        ? ("💵 نرخ تبدیل: " . ($rate > 0
            ? '<b>' . fmtNum($rate) . '</b> تومان' .
              ((float)($api['rate'] ?? 0) > 0 ? ' (دستی)' : ' (از بخش قیمت‌گیری)')
            : '<b>معلوم نیست</b>') . "\n")
        : "💵 قیمت‌ها تومانی‌اند — نرخی لازم نیست\n";
    $t .= "📈 سود: <b>" . rtrim(rtrim(number_format($mk, 1), '0'), '.') . "٪</b>\n";
    $t .= "💰 قیمت از فروشنده: " . (!empty(numVal('sync_price', true)) ? '✅ هر بار تازه' : '🔒 دستی') . "\n\n";

    $t .= "⏳ مهلت انتظار کد: <b>" . (int)numVal('wait', 900) . "</b> ثانیه\n";
    $t .= "🔁 فاصله‌ی پیگیری: <b>" . (int)numVal('poll', 6) . "</b> ثانیه\n";
    $t .= "⏱ مهلت تماس: <b>" . (int)($api['timeout'] ?? 15) . "</b> ثانیه\n";

    $nc = count(array_filter(maCats(),  fn($x) => !empty($x['on'])));
    $ni = count(array_filter(maItems(), fn($x) => !empty($x['on'])));
    $t .= "\n🌍 کشور: <b>{$nc}</b> · ☎️ شماره: <b>{$ni}</b>\n";
    $t .= "مینی‌اپ: " . (!empty(maCfg()['on']) ? '✅ باز' : '❌ بسته') . "\n";
    if ($ni === 0) $t .= "⚠️ هنوز چیزی برای فروش نیست — پنلِ وب ← کشورها و قیمت‌ها ← 📥 وارد کردن.\n";

    if (numNeedsRate() && $rate <= 0)
        $t .= "\n⚠️ بدون نرخ تبدیل، قیمتی وارد نمی‌شود. نرخ را در پنلِ وب بگذارید یا بخش قیمت‌گیری را روشن کنید.\n";
    if ($tok === '')
        $t .= "\n⚠️ تا " . h($pi['key']) . " ثبت نشود هیچ خریدی انجام نمی‌شود.\n";
    $t .= "\n⚙️ فروشنده، کلید، سود، نرخ، مهلت‌ها، کشورها و قیمت‌ها فقط در <b>پنلِ وب</b> تنظیم می‌شوند.";
    if ($stuck) $t .= "\n⚠️ <b>{$stuck}</b> سفارش روی فروشنده بسته نشده — «📋 شماره‌های باز» را ببینید.";
    if ($open)  $t .= "\n⏳ <b>{$open}</b> شماره‌ی باز، در انتظار کد.";

    $rows = [
        [btnCb('🩺 عیب‌یابی اتصال', 'numdiag', 'confirm')],
        numProv() === '5sim' ? [btnCb('👤 حساب ۵سیم: موجودی، امتیاز، سفارش‌ها', 'num5acct', 'confirm')]
                             : [btnCb('💰 موجودی حساب ' . $pi['name'], 'numtest', 'confirm')],
        [btnCb('📋 شماره‌های باز', 'numopen', 'reject'),
         btnCb('🧹 پاک‌سازی', 'numclean', 'reject')],
        [btnCb('📱 مینی‌اپ', 'maadm_home', 'admin'), btnCb('🔗 آدرس مینی‌اپ', 'numlink', 'info')],
        [btnCb('🔙 بازگشت', 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function numAdmTest($chatId) {
    $back = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    if (numKey() === '') {
        sendMsg(BOT_TOKEN, $chatId, "⚠️ اول کلیدِ " . h(numProvName()) . " را ثبت کنید.", $back);
        return;
    }
    [$bal, $cur, $err] = numBalance();
    if ($err !== '') {
        sendMsg(BOT_TOKEN, $chatId,
            "❌ <b>نگرفت</b>\n<code>" . h(mb_substr($err, 0, 300)) . "</code>\n" .
            "🌐 مقصد: <code>" . h(numBase()) . "</code>\n\n" .
            "🩺 «عیب‌یابی اتصال» می‌گوید دقیقا کجا می‌شکند — کلید، شبکه، یا نرخ.",
            inlineKb([[btnCb('🩺 عیب‌یابی اتصال', 'numdiag', 'confirm')],
                      [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
        return;
    }
    $rate = numRate();
    $t  = "✅ <b>وصل است</b>\n\n";
    $t .= "💰 موجودی: <b>" . fmtNum($bal) . "</b> " . h($cur ?: '') . "\n";
    if ($rate > 0) $t .= "≈ <b>" . fmtNum(numRound100($bal * $rate)) . "</b> تومان\n";
    sendMsg(BOT_TOKEN, $chatId, $t, $back);
}

function numAdmDiag($chatId, $msgId = 0) {
    $pi = numProvInfo();
    $t = "🩺 <b>عیب‌یابی اتصال — " . h($pi['name']) . "</b>\n\n";
    $rows = [];
    $stop = false;

    $tok = numKey();
    $sh  = numProv() === '5sim'
        ? numTokenShape($tok)
        : ($tok === '' ? ['ok' => false, 'kind' => 'empty', 'why' => 'هنوز چیزی ثبت نشده']
                       : (strlen($tok) < 16
                          ? ['ok' => false, 'kind' => 'cut', 'why' => 'کلید خیلی کوتاه است — کلش را بفرستید']
                          : ['ok' => true, 'kind' => 'nl', 'why' => 'شکلش درست است']));
    $t .= "<b>۱. " . h($pi['key']) . "</b>\n";
    if ($sh['ok']) {
        $t .= "✅ ثبت شده · " . strlen($tok) . " حرف · " . h($sh['why']) . "\n";
    } else {
        $t .= "❌ " . $sh['why'] . "\n";
        if ($sh['kind'] === 'old')
            $t .= "\n📌 در همان صفحه‌ی ۵سیم <b>دو</b> کلید هست:\n" .
                  "• بالایی — <b>API key for 5SIM protocol</b> ← <b>این را بفرستید</b>\n" .
                  "• پایینی — <b>(Deprecated API)</b> ← این به درد ما نمی‌خورد\n";
        $t .= numWebHint('توکن') . "\n";
        $stop = true;
    }

    if (!$stop) {
        $t .= "\n<b>۲. رسیدن به " . h($pi['name']) . "</b>\n";
        $probePath = numProv() === 'numberland' ? '/v2.php/?method=getcountry' : '/guest/countries';
        $p = num5Probe($probePath, numProv() === 'numberland', 12);
        if ($p['errno'] !== 0) {
            $t .= "❌ <code>" . h(mb_substr($p['err'], 0, 120)) . "</code>\n";
            $why = match (true) {
                $p['errno'] === 6  => "نامِ <code>5sim.net</code> پیدا نشد — DNS سرور مشکل دارد.",
                $p['errno'] === 7  => "اتصال برقرار نشد. یا سرور اینترنتِ خروجی ندارد، یا " .
                                      "۵سیم آی‌پیِ سرورتان را بسته است.",
                $p['errno'] === 28 => "مهلت تمام شد — اتصال هست ولی خیلی کند یا فیلتر است.",
                in_array($p['errno'], [35, 60, 77], true) =>
                                      "دستِ TLS نگرفت. گواهی‌های سرور کهنه‌اند (بسته‌ی ca-certificates).",
                default            => "اتصال به ۵سیم برقرار نشد.",
            };
            $t .= "🔴 " . $why . "\n\n";
            $t .= "<b>این مشکلِ کلید نیست، مشکلِ خودِ سرور است.</b>\n" .
                  "خیلی از هاست‌های ایرانی جلوی اتصالِ خروجی را می‌گیرند. " .
                  "از پشتیبانیِ هاست بپرسید «اتصال خروجی باز است؟»\n";
            if (numProv() === '5sim')
                $t .= "\n🇮🇷 یا فروشنده را روی <b>نامبرلند</b> بگذارید — ایرانی است و می‌رسد.\n";
            $t .= numWebHint('فروشنده') . "\n";
            $stop = true;
        } elseif (numLooksIntercepted($p['body'], $p['code'])) {
            $t .= "❌ جواب آمد ولی از " . h($pi['name']) . " نیست\n";
            $t .= ($p['ip'] !== '' ? "آی‌پی: <code>" . h($p['ip']) . "</code>\n" : '');
            $t .= "<code>" . h(mb_substr(trim($p['body']), 0, 120)) . "</code>\n\n";
            $t .= "🔴 <b>درخواستِ سرور به مقصد نمی‌رسد.</b>\n" .
                  "یک واسط — فیلترینگ یا DNSِ هاست — جوابِ خودش را داده. " .
                  "هیچ کلیدی این را درست نمی‌کند.\n\n";
            if (numProv() === '5sim')
                $t .= "🇮🇷 <b>راهِ حل:</b> فروشنده را روی <b>نامبرلند</b> بگذارید. " .
                      "ایرانی است و از داخل ایران می‌رسد.\n";
            else
                $t .= "🌐 یا در «آدرس پایه» یک واسطه بگذارید.\n";
            $t .= numWebHint('فروشنده') . "\n";
            $stop = true;
        } else {
            $t .= "✅ رسید · " . ($p['ip'] !== '' ? '<code>' . h($p['ip']) . '</code> · ' : '') .
                  "کد <b>" . $p['code'] . "</b> · " . number_format($p['total'], 2) . " ثانیه\n";
            if ($p['total'] > 4) $t .= "⚠️ کند است — ممکن است سرِ خرید هم دیر جواب بدهد.\n";
        }
    }

    if (!$stop) {
        $t .= "\n<b>۳. پذیرشِ کلید</b>\n";
        [$bal, $cur, $bErr] = numBalanceDo();
        if ($bErr === '') {
            $t .= "✅ قبول شد · موجودی: <b>" . fmtNum($bal) . "</b> " . h($cur) . "\n";
            if ($bal <= 0)
                $t .= "⚠️ موجودیِ حسابِ " . h($pi['name']) . " صفر است — خرید انجام نمی‌شود.\n";
        } else {
            $t .= "❌ " . h(mb_substr($bErr, 0, 200)) . "\n\n";
            $t .= "شکلش درست است ولی خودش قبول نشد. یعنی یکی از این‌ها:\n" .
                  "• کلید عوض شده و کلیدِ قدیمی را فرستاده‌اید\n" .
                  (numProv() === '5sim'
                   ? "• کلیدِ پایینیِ صفحه (Deprecated) را کپی کرده‌اید\n" : '') .
                  "• موقعِ کپی، تکه‌ای جا افتاده\n\n" .
                  "🔧 روی دکمه‌ی <b>کپی</b>ِ کنارِ کادر بزنید (نه انتخاب با دست) " .
                  "و همان را اینجا بفرستید.\n";
            $t .= numWebHint('کلید') . "\n";
            $stop = true;
        }
    }

    if (!$stop && !numNeedsRate()) {
        $t .= "\n<b>۴. نرخ تبدیل</b>\n✅ لازم نیست — قیمت‌های " . h($pi['name']) . " تومانی‌اند\n";
    } elseif (!$stop) {
        $t .= "\n<b>۴. نرخ تبدیل</b>\n";
        $r = numRate();
        if ($r > 0) {
            $t .= "✅ هر دلار <b>" . fmtNum($r) . "</b> تومان" .
                  ((float)numVal('api.rate', 0) > 0 ? ' (دستی)' : ' (از بخش قیمت‌گیری)') . "\n";
        } else {
            $t .= "❌ معلوم نیست\n\n";
            $t .= "<b>بدون نرخ، «وارد کردن» کار نمی‌کند</b> — و پیامش شبیهِ خرابیِ کلید است، " .
                  "در حالی که کلید سالم است.\n" .
                  "یا نرخ را دستی بگذارید (" . strip_tags(numWebHint('نرخ دلار')) . ")، یا بخش قیمت‌گیری را روشن کنید.\n";
            $rows[] = [btnCb('💹 قیمت‌گیری', 'px_home', 'nav')];
            $stop = true;
        }
    }

    if (!$stop) {
        $t .= "\n<b>۵. شماره‌های تلگرام</b>\n";
        [$co, $rowsC, $err] = numCatalog();
        if ($err !== '') {
            $t .= "❌ " . h(mb_substr($err, 0, 200)) . "\n";
            $stop = true;
        } else {
            $t .= "✅ <b>" . fmtNum(count($rowsC)) . "</b> شماره از <b>" .
                  fmtNum(count($co)) . "</b> کشور\n";
            $on = 0;
            foreach ($rowsC as $r) if (!empty($r['on'])) $on++;
            $t .= "موجود: <b>" . fmtNum($on) . "</b>\n";
        }
    }

    if (!$stop) {
        $t .= "\n<b>۶. وضعیت بخش</b>\n";
        if (!empty(numVal('api.on'))) {
            $t .= "✅ روشن\n";
        } else {
            $t .= "❌ خاموش — همه‌چیز سالم است ولی فروش انجام نمی‌شود.\n" . numWebHint('روشن کردنِ فروش') . "\n";
        }
        $ni = count(array_filter(maItems(), fn($x) => !empty($x['on'])));
        if ($ni === 0) {
            $t .= "⚠️ هنوز چیزی وارد نشده — پنلِ وب ← کشورها و قیمت‌ها ← 📥 وارد کردن.\n";
        } else {
            $t .= "☎️ <b>" . fmtNum($ni) . "</b> شماره آماده‌ی فروش\n";
        }
    }

    if (!$stop && !empty(numVal('api.on')))
        $t .= "\n🎉 <b>همه‌چیز سرِ جایش است.</b>";

    $rows[] = [btnCb('🔄 دوباره امتحان کن', 'numdiag', 'confirm')];
    $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];

    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function numAdmClean($chatId, $msgId) {
    $n  = count(maItems());
    $nc = count(maCats());
    $on = count(array_filter(maItems(), fn($x) => !empty($x['on'])));
    $pn = h(numProvName());
    $sz = @filesize(DATA_DIR . '/config.json') ?: 0;

    $t  = "🧹 <b>پاک‌سازی کاتالوگ</b>\n\n";
    $t .= "الان: <b>" . fmtNum($n) . "</b> شماره (<b>" . fmtNum($on) . "</b> روشن) · <b>" .
          fmtNum($nc) . "</b> پوشه\n";
    $t .= "اندازه‌ی تنظیمات: <b>" . number_format($sz / 1024, 1) . "</b> کیلوبایت\n\n";
    if ($n > 500)
        $t .= "⚠️ این تعداد هم مینی‌اپ را سنگین می‌کند هم کلِ ربات را: هر تغییرِ " .
              "تنظیمات، این فایل را از نو می‌نویسد.\n\n";

    $t .= "۱️⃣ <b>هرچه روی {$pn} نیست برود</b> · قیمت و نامِ دستی می‌ماند\n";
    $t .= "۲️⃣ <b>از صفر</b> · همه پاک می‌شوند\n\n";
    $t .= "شماره‌های دستی (بدون شناسه) دست نمی‌خورند";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('🧹 هرچه روی ' . numProvName() . ' نیست برود', 'numclean1', 'confirm')],
        [btnCb('🗑 از صفر شروع کن', 'numclean2', 'reject')],
        [btnCb('🔙 بازگشت', 'num_home', 'nav')],
    ]));
}

function numAdmCleanKeep($chatId, $msgId) {
    [$co, $rows, $err] = numCatalog();
    if ($err !== '') {
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "❌ <b>فهرست از " . h(numProvName()) . " نیامد</b>\n<code>" . h(mb_substr($err, 0, 240)) . "</code>",
            inlineKb([[btnCb('🗑 از صفر شروع کن', 'numclean2', 'reject')],
                      [btnCb('🔙 بازگشت', 'numclean', 'nav')]]));
        return;
    }

    [$delI, $delC] = numPruneCatalog(array_column($rows, 'sid'));
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "✅ <b>پاک شد</b>\n\n" .
        "🗑 <b>" . fmtNum($delI) . "</b> شماره و <b>" . fmtNum($delC) . "</b> پوشه حذف شد.\n" .
        "الان: <b>" . fmtNum(count(maItems())) . "</b> شماره",
        inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
}

function numAdmCleanAll($chatId, $msgId) {
    $delI = $delC = 0;
    maSetRoot(function (&$a) use (&$delI, &$delC) {
        $keep = [];
        foreach ((array)($a['items'] ?? []) as $it) {
            if (trim((string)($it['svc'] ?? '')) === '') { $keep[] = $it; continue; }
            $delI++;
        }
        $a['items'] = array_values($keep);

        $used = [];
        foreach ($a['items'] as $it) $used[(string)($it['cat'] ?? '')] = 1;
        $cats = [];
        foreach ((array)($a['cats'] ?? []) as $c) {
            if (trim((string)($c['code'] ?? '')) !== '' && !isset($used[(string)($c['id'] ?? '')])) { $delC++; continue; }
            $cats[] = $c;
        }
        $a['cats'] = array_values($cats);
    });

    $sz = @filesize(DATA_DIR . '/config.json') ?: 0;
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "✅ <b>کاتالوگ خالی شد</b>\n\n" .
        "🗑 <b>" . fmtNum($delI) . "</b> شماره و <b>" . fmtNum($delC) . "</b> پوشه حذف شد.\n" .
        "اندازه‌ی تنظیمات: <b>" . number_format($sz / 1024, 1) . "</b> کیلوبایت",
        inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
}

function numAdmOpen($chatId, $msgId) {
    $rows = [];
    $t = "📋 <b>شماره‌های باز</b>\n\n";
    $n = 0;
    foreach (numAll() as $a) {
        if (($a['status'] ?? '') !== 'waiting') continue;
        if (++$n > 20) break;
        $left = max(0, numWaitFor($a) - (time() - (int)($a['created'] ?? 0)));
        $t .= "• <code>" . h((string)($a['phone'] ?? '')) . "</code> — " . h((string)($a['name'] ?? '')) .
              " · " . intdiv($left, 60) . ":" . str_pad((string)($left % 60), 2, '0', STR_PAD_LEFT) . "\n";
        $rows[] = [btnCb('🔴 لغو ' . mb_substr((string)($a['phone'] ?? ''), 0, 16), 'numkill_' . $a['order'], 'reject')];
    }
    if (!$n) $t .= "<i>الان هیچ شماره‌ای باز نیست.</i>\n";

    $stuck = [];
    foreach (numAll() as $a) {
        if (empty($a['panel_pending'])) continue;
        if (in_array(($a['status'] ?? ''), ['waiting', 'buying'], true)) continue;
        $stuck[] = $a;
    }
    if ($stuck) {
        $t .= "\n⚠️ <b>روی " . h(numProvName()) . " بسته نشدند</b> (" . count($stuck) . ")\n";
        $t .= "<i>پول کاربر برگشته، ولی هزینه‌ی این‌ها روی فروشنده برنگشته.</i>\n";
        foreach (array_slice($stuck, 0, 8) as $a) {
            $t .= "• <code>" . h((string)($a['phone'] ?? '')) . "</code> — " .
                  h(mb_substr((string)($a['panel_err'] ?? '—'), 0, 60)) .
                  " (" . (int)($a['panel_tries'] ?? 0) . " تلاش)\n";
            $rows[] = [btnCb('🔁 دوباره ' . mb_substr((string)($a['phone'] ?? ''), 0, 16),
                             'numretry_' . $a['order'], 'confirm')];
        }
    }

    $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}


function numCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (!str_starts_with((string)$data, 'num')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    $ack = function ($m = '') use ($cbId) { answerCb(BOT_TOKEN, $cbId, $m); };

    if ($data === 'num_home')  { $ack(); numAdmHome($chatId, $msgId);  return true; }
    if ($data === 'numtest')   { $ack('⏳'); numAdmTest($chatId); return true; }
    if ($data === 'num5acct')  { $ack('⏳'); numAdm5Acct($chatId, $msgId); return true; }
    if ($data === 'numdiag')   { $ack('🩺 در حال بررسی…'); numAdmDiag($chatId, $msgId); return true; }
    if ($data === 'numopen')   { $ack(); numAdmOpen($chatId, $msgId); return true; }
    if ($data === 'numclean')  { $ack(); numAdmClean($chatId, $msgId); return true; }
    if ($data === 'numclean1') { $ack('⏳'); numAdmCleanKeep($chatId, $msgId); return true; }
    if ($data === 'numclean2') { $ack('⏳'); numAdmCleanAll($chatId, $msgId); return true; }

    if ($data === 'numlink') {
        $u = maUrl();
        $ack();
        sendMsg(BOT_TOKEN, $chatId,
            $u !== '' ? "🔗 <b>آدرس مینی‌اپ شماره</b>\n<code>" . h($u) . '</code>'
                      : "⚠️ اول آدرس عمومی را ثبت کنید: پنلِ وب ← API و اتصال‌ها ← آدرسِ عمومی",
            inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
        return true;
    }

    if (str_starts_with($data, 'numkill_')) {
        [$ok, $err] = numFinish(substr($data, 8), 'cancel');
        $ack($ok ? '✅ لغو شد' : '⚠️ ' . mb_substr($err, 0, 40));
        numAdmOpen($chatId, $msgId);
        return true;
    }

    if (str_starts_with($data, 'numretry_')) {
        [$ok, $err] = numTellPanelCancel(substr($data, 9));
        $ack($ok ? '✅ بسته شد' : '❌ ' . mb_substr($err, 0, 60));
        numAdmOpen($chatId, $msgId);
        return true;
    }

    $ack();
    return true;
}


function numWebHint($what) {
    return '⚙️ ' . h($what) . ': <b>پنلِ وب ← API و اتصال‌ها ← فروشنده‌ی شماره</b>';
}

function numDigits($s) {
    $s = strtr((string)$s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    return preg_replace('/\D+/', '', $s);
}

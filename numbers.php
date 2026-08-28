<?php
/**
 * ☎️ numbers.php — موتور فروش شماره مجازی تلگرام (۵سیم)
 * ============================================================
 *
 * فروش شماره با بقیه‌ی فروش‌های ربات یک فرق بنیادی دارد: تحویل
 * یک‌مرحله‌ای نیست. بعد از پرداخت باید:
 *
 *   ۱. از ۵سیم یک شماره گرفت
 *   ۲. شماره را به خریدار داد
 *   ۳. منتظر پیامک ماند و کد را نشان داد
 *   ۴. اگر کد نیامد، لغو کرد و پول را برگرداند
 *
 * پس هر سفارش یک «فعال‌سازی» دارد که بین این حالت‌ها می‌چرخد:
 *
 *   waiting  → منتظر پیامک
 *   done     → کد آمد
 *   cancel   → لغو شد و پول برگشت
 *   expired  → مهلت تمام شد و پول برگشت
 *
 * 🔌 اتصال دیگر «عمومی» نیست و مسیر و بدنه تنظیم نمی‌شود. قبلا بود و
 *    نتیجه‌اش این شد که ادمین باید ده‌تا مسیر را دستی می‌نوشت و یک
 *    اشتباه تایپی همه‌چیز را می‌خواباند. حالا فقط یک چیز لازم است:
 *    توکن ۵سیم. بقیه‌اش سرِ جایش نشسته و تغییر نمی‌کند.
 *
 * 🎯 و فقط یک چیز فروخته می‌شود: شماره‌ی تلگرام. نه به این دلیل که
 *    بقیه‌اش سخت است، به این دلیل که فروشگاه همین است.
 *
 * ⚠️ هیچ‌جای این فایل پول را دو بار برنمی‌گرداند و هیچ شماره‌ای را دو بار
 *    نمی‌خرد: هر دو کار پشت یک ادعای اتمی روی خودِ رکورد انجام می‌شوند.
 *
 * 📖 API که این فایل حرف می‌زند (5sim.net/v1، همه GET، سرآیندِ
 *    Authorization: Bearer …):
 *
 *      /user/profile                                    موجودی حساب
 *      /guest/countries                                 نام کشورها
 *      /guest/prices?product=telegram                   قیمت‌ها
 *      /user/buy/activation/{country}/{operator}/telegram   خرید
 *      /user/check/{id}                                 پیامک‌ها
 *      /user/finish/{id}                                بستنِ سفارش
 *      /user/cancel/{id}                                لغو (پیش از پیامک)
 *      /user/ban/{id}                                   شماره‌ی خراب
 */

if (!defined('NUM_LIB')) define('NUM_LIB', 1);

// ============================================================
// ⚙️ پیکربندی
// ============================================================

if (!defined('NUM5_BASE'))    define('NUM5_BASE', 'https://5sim.net/v1');
if (!defined('NUM5_PRODUCT')) define('NUM5_PRODUCT', 'telegram');

function numDefaults() {
    return [
        'wait'   => 900,       // مهلت انتظار کد — ثانیه
        'poll'   => 6,         // فاصله‌ی دو پرسش از ۵سیم — ثانیه
        'markup' => 0,         // درصد سود روی قیمتِ ۵سیم
        'sync_price' => true,  // قیمتِ ۵سیم منبعِ حقیقت باشد و هر بار به‌روز شود
        'api'   => [
            'on'      => false,
            'token'   => '',            // توکن ۵سیم (JWT)
            'base'    => NUM5_BASE,
            'timeout' => 15,
            // 💵 نرخِ هر واحدِ پولِ حسابِ ۵سیم به تومان.
            //    صفر یعنی «از بخش قیمت‌گیریِ خودِ ربات بگیر» — یعنی نرخِ
            //    زنده‌ی دلار. اگر حسابتان روبلی است، اینجا نرخ روبل را
            //    دستی بگذارید.
            'rate'    => 0,
            // 🧢 سقفِ قیمتِ هر خرید به واحدِ ۵سیم. صفر یعنی بی‌سقف.
            'max'     => 0,
        ],
    ];
}

function numCfg() {
    $c = cfg()['numbers'] ?? null;
    $d = numDefaults();
    if (!is_array($c)) return $d;

    $out = array_replace($d, array_intersect_key($c,
        ['wait' => 1, 'poll' => 1, 'markup' => 1, 'sync_price' => 1]));
    $out['api'] = array_replace($d['api'],
        array_intersect_key(is_array($c['api'] ?? null) ? $c['api'] : [],
                            ['on'=>1,'token'=>1,'base'=>1,'timeout'=>1,'rate'=>1,'max'=>1]));
    // آدرس همیشه همان است — دستِ کسی نمی‌رود
    if (trim((string)$out['api']['base']) === '') $out['api']['base'] = NUM5_BASE;
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
    return !empty(numVal('api.on')) && trim((string)numVal('api.token', '')) !== '';
}

/**
 * 💵 نرخِ تبدیل: هر ۱ واحدِ پولِ ۵سیم چند تومان است.
 *
 * اگر ادمین عددی گذاشته باشد همان؛ وگرنه نرخِ زنده‌ی دلار از بخش
 * قیمت‌گیریِ خودِ ربات. صفر یعنی «نمی‌دانم» — و آن‌وقت قیمتی هم وارد
 * نمی‌شود، چون قیمتِ غلط بدتر از نبودِ قیمت است.
 */
function numRate() {
    $own = (float)numVal('api.rate', 0);
    if ($own > 0) return $own;
    if (function_exists('pxRawToman')) {
        $r = (float)pxRawToman('USDT');
        if ($r > 0) return $r;
    }
    return 0.0;
}

/**
 * قیمتِ ارزیِ ۵سیم → تومان.
 *
 * سود عمداً اینجا سوار نمی‌شود مگر بخواهیم: صاحبِ سود numImport است و
 * اگر دو جا حساب شود، سودِ ۲۰٪ می‌شود ۴۴٪ و هیچ‌کس هم نمی‌فهمد چرا.
 */
function numToman($usd, $markup = 0, $round = true) {
    $rate = numRate();
    if ($rate <= 0) return 0.0;
    $t = (float)$usd * $rate * (1 + max(0, (float)$markup) / 100);
    return $round ? numRound100($t) : $t;
}

/** به نزدیک‌ترین ۱۰۰ تومان — قیمتِ «۱۲٬۳۴۷ تومان» به درد فروشگاه نمی‌خورد */
function numRound100($t) {
    $t = (float)$t;
    return $t > 0 ? round($t / 100) * 100 : 0.0;
}

// ============================================================
// 📡 تماس با ۵سیم
// ============================================================

/**
 * یک GET به ۵سیم.
 *
 * ۵سیم دو جور جواب می‌دهد و هر دو باید فهمیده شود:
 *   • موفق → JSON
 *   • ناموفق → کدِ ۴۰۰/۴۰۱ با یک متنِ ساده مثل «no free phones»
 *
 * پس متنِ خطا را هم برمی‌گردانیم، نه فقط «کد پاسخ ۴۰۰».
 *
 * برگشت: [آرایه‌ی پاسخ یا null, خطا]
 */
function num5Get($path, $timeout = null) {
    $base = rtrim(trim((string)numVal('api.base', NUM5_BASE)), '/');
    if ($base === '') return [null, 'آدرس ۵سیم ثبت نشده'];
    $tok = trim((string)numVal('api.token', ''));
    if ($tok === '') return [null, 'توکن ۵سیم ثبت نشده'];
    if ($timeout === null) $timeout = (int)numVal('api.timeout', 15);

    $url = $base . '/' . ltrim((string)$path, '/');
    if (function_exists('__num5Hook')) return __num5Hook($url, $tok, $timeout);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max(3, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',          // gzip — فهرست قیمت‌ها بزرگ است
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $tok,
            'Accept: application/json',
        ],
    ]);
    $res  = curl_exec($ch);
    $cerr = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return num5Parse($res, $cerr, $code);
}

/** پاسخِ خام ۵سیم → [آرایه, خطا]. جدا نوشته شده تا آزمون‌پذیر باشد. */
function num5Parse($res, $cerr, $code) {
    if ($res === false || $res === null) return [null, 'اتصال به ۵سیم برقرار نشد: ' . $cerr];

    $txt = trim((string)$res);
    $j   = json_decode($txt, true);

    if ($code < 200 || $code >= 300) {
        // متنِ ۵سیم را همان‌طور که هست بده — گویاتر از هر ترجمه‌ای است
        $why = is_array($j) ? (string)($j['message'] ?? $j['error'] ?? '') : $txt;
        $why = trim(preg_replace('/\s+/u', ' ', $why));
        return [null, num5Why($why, $code)];
    }

    // ۵سیم گاهی با کدِ ۲۰۰ هم یک کلمه‌ی خطا می‌فرستد
    if (!is_array($j)) {
        if ($txt === '' ) return [null, 'پاسخی نیامد'];
        return [null, num5Why($txt, 200)];
    }
    return [$j, ''];
}

/** متنِ خطای ۵سیم → جمله‌ی فارسی. آنچه نشناسیم خودش را می‌گوید. */
function num5Why($raw, $code = 0) {
    $k = strtolower(trim(preg_replace('/\s+/u', ' ', (string)$raw)));
    static $map = [
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
    if (isset($map[$k])) return $map[$k];
    if ($code === 401 || $code === 403) return 'توکن ۵سیم پذیرفته نشد — کلید را دوباره ثبت کنید';
    if ($k === '') return 'کد پاسخ ' . $code;
    return mb_substr($raw, 0, 200);
}

/**
 * یک عملیات روی ۵سیم.
 *
 * نام‌ها همان‌هایی‌اند که بقیه‌ی فایل صدا می‌زند، پس چرخه‌ی سفارش
 * دست‌نخورده ماند وقتی پنلِ فروشنده عوض شد.
 *
 * برگشت: [پاسخ, خطا]
 */
function numCall($op, array $vars = []) {
    $id = rawurlencode(trim((string)($vars['id'] ?? '')));
    switch ($op) {
        case 'buy':
            $co = trim((string)($vars['country'] ?? ''));
            $opr= trim((string)($vars['operator'] ?? '')) ?: 'any';
            if ($co === '') return [null, 'کشور مشخص نیست'];
            $p = '/user/buy/activation/' . rawurlencode($co) . '/' . rawurlencode($opr) .
                 '/' . rawurlencode(NUM5_PRODUCT);
            $max = (float)numVal('api.max', 0);
            if ($max > 0) $p .= '?maxPrice=' . rawurlencode((string)$max);
            return num5Get($p);

        case 'status':  return $id === '' ? [null, 'شناسه ندارد'] : num5Get('/user/check/'  . $id);
        case 'cancel':  return $id === '' ? [null, 'شناسه ندارد'] : num5Get('/user/cancel/' . $id);
        case 'close':   return $id === '' ? [null, 'شناسه ندارد'] : num5Get('/user/finish/' . $id);
        case 'ban':     return $id === '' ? [null, 'شناسه ندارد'] : num5Get('/user/ban/'    . $id);
        case 'balance': return num5Get('/user/profile');
    }
    return [null, 'عملیات «' . $op . '» را نمی‌شناسم'];
}

/** موجودی حسابِ ۵سیم — [عدد, واحد, خطا] */
function numBalance() {
    [$j, $err] = numCall('balance');
    if (!is_array($j)) return [0.0, '', $err ?: 'پاسخی نیامد'];
    $b = $j['balance'] ?? null;
    if (!is_numeric($b)) return [0.0, '', 'موجودی در پاسخ نبود'];
    return [(float)$b, (string)($j['currency'] ?? ''), ''];
}

// ============================================================
// 🗂 انبار فعال‌سازی‌ها
// ============================================================

function numAll() { return load('num_acts'); }
function numGet($orderId) { $a = numAll(); return $a[(string)$orderId] ?? null; }

function numSetAct($orderId, callable $fn) {
    return mutate('num_acts', function (&$a) use ($orderId, $fn) {
        $k = (string)$orderId;
        if (!isset($a[$k])) return false;
        return $fn($a[$k]);
    });
}

function numPut(array $act) {
    mutate('num_acts', function (&$a) use ($act) {
        $a[(string)$act['order']] = $act;
        // خانه‌تکانی — فعال‌سازی‌های تمام‌شده‌ی کهنه جا اشغال نکنند
        if (count($a) > 500) {
            $now = time();
            foreach ($a as $k => $v)
                if (($v['status'] ?? '') !== 'waiting' && ($now - (int)($v['created'] ?? 0)) > 172800)
                    unset($a[$k]);
        }
    });
}

/**
 * فعال‌سازیِ بازِ یک کاربر — تازه‌ترینش.
 *
 * وقتی کاربر مینی‌اپ را می‌بندد و دوباره باز می‌کند، شناسه‌ی سفارش دستش
 * نیست؛ اپ همین را صدا می‌زند تا مستقیم برگردد سر صفحه‌ی انتظار.
 */
function numActiveFor($uid) {
    $uid  = (int)$uid;
    $best = null;
    foreach (numAll() as $act) {
        if ((int)($act['uid'] ?? 0) !== $uid) continue;
        if (($act['status'] ?? '') !== 'waiting') continue;
        if (!$best || (int)$act['created'] > (int)$best['created']) $best = $act;
    }
    return $best;
}

/** فهرست کوتاهِ فعال‌سازی‌های اخیرِ یک کاربر — برای صفحه‌ی «سفارش‌ها» */
function numHistory($uid, $limit = 10) {
    $uid = (int)$uid;
    $now = time();
    $out = [];
    foreach (numAll() as $act) {
        if ((int)($act['uid'] ?? 0) !== $uid) continue;
        $st   = (string)($act['status'] ?? '');
        $wait = numWaitFor($act);
        $left = max(0, $wait - ($now - (int)($act['created'] ?? 0)));
        $out[] = [
            'order'  => (string)$act['order'],
            'name'   => (string)($act['name'] ?? ''),
            'phone'  => (string)($act['phone'] ?? ''),
            'code'   => (string)($act['code'] ?? ''),
            'status' => $st,
            'at'     => (int)($act['created'] ?? 0),
            // 🕒 صفحه‌ی «سفارش‌های من» باید بتواند شمارش معکوس نشان دهد و
            //    بداند کدام دکمه را بگذارد — بدون این، برای هر ردیف یک
            //    درخواستِ جدا لازم می‌شد.
            'left'   => $st === 'waiting' ? (int)$left : 0,
            'wait'   => (int)$wait,
            'can_get'=> $st === 'waiting' && $left > 0 ? 1 : 0,
            'repeat' => numCanRepeat($act, $left) ? 1 : 0,
            'price'  => (float)($act['price'] ?? 0),
        ];
    }
    usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
    return array_slice($out, 0, max(1, (int)$limit));
}

/**
 * 🔁 «کد مجدد» روی ۵سیم یعنی چه؟
 *
 * ۵سیم تا وقتی سفارش را نبسته باشیم، پیامک‌های بعدی را هم روی همان
 * شماره تحویل می‌دهد. پس «کد مجدد» یعنی: منتظرِ پیامکِ بعدی بمان.
 * شرطش این است که سفارش هنوز بسته نشده و مهلتش تمام نشده باشد.
 */
function numCanRepeat($act, $left = null) {
    if (!is_array($act)) return false;
    if (($act['status'] ?? '') !== 'done') return false;
    if (!empty($act['closed'])) return false;
    if ($left === null)
        $left = numWaitFor($act) - (time() - (int)($act['created'] ?? 0));
    return $left > 0;
}

/**
 * کشور و اپراتورِ یک آیتم — از روی خودِ پیکربندی مینی‌اپ.
 *
 * شناسه‌ی ردیف «کشور|اپراتور» است، چون روی ۵سیم هیچ‌کدام به‌تنهایی یک
 * محصول نیستند. کشور را ترجیحا از پوشه‌اش می‌گیریم (ادمین ممکن است
 * دستی عوضش کرده باشد) و اگر پوشه‌ای نبود، از خودِ شناسه.
 */
function numItemMeta($itemId) {
    $a = function_exists('maGet') ? maGet('num') : [];
    foreach ((array)($a['items'] ?? []) as $i) {
        if ((string)($i['id'] ?? '') !== (string)$itemId) continue;

        $sid = (string)($i['svc'] ?? '');
        $co  = $op = '';
        if (str_contains($sid, '|')) [$co, $op] = explode('|', $sid, 2);
        else                          $op = $sid;

        foreach ((array)($a['cats'] ?? []) as $c)
            if ((string)($c['id'] ?? '') === (string)($i['cat'] ?? '')) {
                $cc = trim((string)($c['code'] ?? ''));
                if ($cc !== '') $co = $cc;
                break;
            }
        return ['operator' => $op, 'country' => $co, 'item' => $i];
    }
    return null;
}

// ============================================================
// 🛒 گرفتن شماره
// ============================================================

/**
 * بعد از پرداخت صدا زده می‌شود. یک شماره می‌گیرد و فعال‌سازی می‌سازد.
 * برگشت: [موفق؟, پیام]
 */
function numBuy($order) {
    if (!is_array($order)) return [false, 'سفارش پیدا نشد'];
    $oid = (string)$order['id'];

    if (numGet($oid)) return [true, ''];            // قبلا گرفته شده
    if (!numReady())  return [false, 'اتصال به ۵سیم تنظیم نشده است'];

    $meta = numItemMeta((string)($order['item_id'] ?? ''));
    if (!$meta) return [false, 'این شماره دیگر تعریف نشده است'];
    if ($meta['country'] === '') return [false, 'کشور این ردیف تنظیم نشده است'];

    // 🔒 ادعای اتمی: فقط یک اجرا شماره می‌خرد، حتی اگر دو درخواست هم‌زمان بیاید
    $claimed = false;
    mutate('num_acts', function (&$a) use ($oid, &$claimed) {
        if (isset($a[$oid])) return;
        $a[$oid] = ['order' => $oid, 'status' => 'buying', 'created' => time()];
        $claimed = true;
    });
    if (!$claimed) return [true, ''];

    [$resp, $err] = numCall('buy', [
        'country'  => $meta['country'],
        'operator' => $meta['operator'] ?: 'any',
    ]);

    $fail = '';
    if (!is_array($resp)) $fail = $err ?: 'پاسخی از ۵سیم نیامد';
    else {
        $aid   = $resp['id']    ?? null;
        $phone = $resp['phone'] ?? null;
        if (!is_scalar($aid) || trim((string)$aid) === '')       $fail = 'شناسه‌ی سفارش در پاسخ ۵سیم نبود';
        elseif (!is_scalar($phone) || trim((string)$phone) === '') $fail = 'شماره در پاسخ ۵سیم نبود';
    }

    if ($fail !== '') {
        mutate('num_acts', function (&$a) use ($oid) { unset($a[$oid]); });   // تا بشود دوباره تلاش کرد
        return [false, $fail];
    }

    // ⏳ مهلتِ خودِ ۵سیم دقیق‌تر از عددِ ماست
    $ttl = numExpiresIn((string)($resp['expires'] ?? ''));

    numPut([
        'order'    => $oid,
        'uid'      => (int)($order['user_id'] ?? 0),
        'item'     => (string)($order['item_id'] ?? ''),
        'name'     => (string)($order['item_name'] ?? ''),
        'operator' => (string)($resp['operator'] ?? $meta['operator']),
        'country'  => (string)($resp['country'] ?? $meta['country']),
        'aid'      => (string)$resp['id'],
        'phone'    => numPhone((string)$resp['phone']),
        'code'     => '',
        'status'   => 'waiting',
        'price'    => (float)($order['total'] ?? 0),
        'cost'     => (float)($resp['price'] ?? 0),   // آنچه به ۵سیم دادیم
        'created'  => time(),
        'checked'  => 0,
        'wait'     => $ttl >= 60 ? $ttl : 0,
        'sms_seen' => 0,
        'repeats'  => 0,
    ]);
    return [true, ''];
}

/** «2020-06-28T16:32:43.307041Z» → چند ثانیه مانده. ۰ یعنی نفهمیدم. */
function numExpiresIn($iso) {
    $iso = trim((string)$iso);
    if ($iso === '') return 0;
    $t = strtotime($iso);
    if ($t === false) return 0;
    return max(0, $t - time());
}

/** شماره را تمیز می‌کند: فقط رقم، با + اول */
function numPhone($s) {
    $d = preg_replace('/\D+/', '', (string)$s);
    return $d === '' ? '' : '+' . $d;
}

// ============================================================
// 📩 پیگیری کد
// ============================================================

/**
 * وضعیت تازه‌ی یک فعال‌سازی.
 *
 * از ۵سیم زیاد نمی‌پرسد: بین دو پرسش دست‌کم `poll` ثانیه فاصله می‌گذارد،
 * وگرنه صد نفر که مینی‌اپ باز دارند سهمیه‌ی درخواست را تمام می‌کنند.
 *
 * برگشت: آرایه‌ی وضعیت برای مینی‌اپ، یا null اگر فعال‌سازی نبود.
 */
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

/** یک بار از ۵سیم می‌پرسد پیامک آمده یا نه */
function numPoll($orderId) {
    $act = numGet($orderId);
    if (!$act || $act['status'] !== 'waiting') return;

    numSetAct($orderId, function (&$x) { $x['checked'] = time(); return true; });

    [$resp, $err] = numCall('status', ['id' => (string)$act['aid']]);
    if (!is_array($resp)) return;                         // شبکه نبود — دفعه‌ی بعد

    $st = strtoupper(trim((string)($resp['status'] ?? '')));

    // ⛔️ مرده؟ پول همان‌جا برگردد، منتظرِ مهلت نمانیم.
    //    ۵سیم خودش بسته، پس دوباره «لغو کن» گفتن بی‌معنی است.
    if (in_array($st, ['CANCELED', 'CANCELLED', 'TIMEOUT', 'BANNED'], true)) {
        numFinish($orderId, 'expired', false);
        return;
    }

    // 📨 پیامک‌ها. ۵سیم آنها را به ترتیبِ رسیدن می‌دهد و تا سفارش بسته
    //    نشده، تازه‌ها را هم اضافه می‌کند. پس «کدِ مجدد» یعنی همان
    //    پیامکِ بعدی — و برای همین می‌شماریم کدام‌ها را قبلا داده‌ایم.
    $sms  = is_array($resp['sms'] ?? null) ? array_values($resp['sms']) : [];
    $seen = (int)($act['sms_seen'] ?? 0);
    if (count($sms) <= $seen) return;                     // چیزِ تازه‌ای نیست

    $code = numExtractCode($sms[count($sms) - 1]);
    if ($code === '') return;

    $n = count($sms);
    numSetAct($orderId, function (&$x) use ($code, $n) {
        if ($x['status'] !== 'waiting') return false;
        $x['code'] = $code; $x['status'] = 'done'; $x['sms_seen'] = $n;
        return true;
    });
    numOrderDone($orderId);
}

/**
 * 🔑 کد را از یک پیامکِ ۵سیم بیرون می‌کشد.
 *
 * ۵سیم معمولا خودش فیلد code را جدا می‌دهد. ولی همیشه نه — و آن‌وقت
 * کاربر یک جمله‌ی روسی می‌گیرد به‌جای کد. پس اگر code نبود، از متنِ
 * پیامک بلندترین رشته‌ی رقمی را درمی‌آوریم.
 */
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

/**
 * ⏳ مهلتِ این فعال‌سازی.
 *
 * اگر ۵سیم موقعِ فروش خودش مهلت داده باشد همان معتبر است، نه عددِ
 * پنلِ ما — وگرنه یا زودتر پول برمی‌گردانیم و شماره‌ی پول‌داده را دور
 * می‌ریزیم، یا دیرتر و کاربر الکی منتظر می‌ماند.
 */
function numWaitFor($act) {
    $own = (int)($act['wait'] ?? 0);
    if ($own >= 60) return $own;
    return max(60, (int)numVal('wait', 900));
}

/** کد رسید — سفارش بسته می‌شود و به خریدار خبر می‌رود */
function numOrderDone($orderId) {
    if (!class_exists('MaOrder')) return;
    $o = MaOrder::get($orderId);
    if (!$o) return;

    // 🔁 کدِ مجدد روی همان شماره یعنی سفارش از قبل DONE است. پس «قبلا
    //    تمام شده» دلیلِ ساکت ماندن نیست — کدِ تازه هم باید برسد. فقط
    //    گذارِ وضعیت و گزارش یک بار انجام می‌شوند.
    $first = ($o['status'] ?? '') !== MaOrder::DONE;
    if ($first) {
        MaOrder::set($orderId, function (&$x) {
            $x['status'] = MaOrder::DONE;
            $x['delivered_at'] = nowStr();
            $x['sending'] = 0;
            $x['last_error'] = '';
        });
        $o = MaOrder::get($orderId);
    }

    $act = numGet($orderId);
    if (function_exists('maTellUser') && $act) {
        maTellUser($o,
            ($first ? "✅ <b>کد شما رسید</b>\n\n" : "🔁 <b>کد مجدد رسید</b>\n\n") .
            '📦 ' . h((string)($act['name'] ?? '')) . "\n" .
            '☎️ <code>' . h((string)$act['phone']) . "</code>\n" .
            '🔑 <code>' . h((string)$act['code']) . "</code>\n" .
            '🧾 <code>' . h((string)$orderId) . '</code>');
    }
    if ($first && function_exists('axReportOrder')) axReportOrder($o, 'done');

    // ⚠️ اینجا عمداً سفارش را روی ۵سیم نمی‌بندیم.
    //
    //    ۵سیم تا وقتی finish نزده‌ایم، پیامک‌های بعدی را هم می‌دهد و
    //    هزینه‌اش هم همان هزینه‌ی اول است. اگر همین‌جا ببندیم، «کد
    //    مجدد» را با دستِ خودمان دور ریخته‌ایم. numTick سرِ مهلت
    //    خودش می‌بنددش.
}

/** سفارش را روی ۵سیم می‌بندد — بی‌صدا، چون به کاربر ربطی ندارد */
function numClose($orderId) {
    $act = numGet($orderId);
    if (!$act || empty($act['aid']) || !empty($act['closed'])) return;

    [$r, $e] = numCall('close', ['id' => (string)$act['aid']]);
    if (is_array($r)) numSetAct($orderId, function (&$x) { $x['closed'] = time(); return true; });
    else              error_log('[numbers] بستن سفارش روی ۵سیم نگرفت: ' . $e);
}

/**
 * 🔁 منتظرِ پیامکِ بعدی روی همان شماره.
 *
 * برگشت: [موفق؟, پیام]
 */
function numRepeat($orderId) {
    $act = numGet($orderId);
    if (!$act)                  return [false, 'این شماره پیدا نشد'];
    if (!numCanRepeat($act))    return [false, 'الان نمی‌شود کد مجدد گرفت'];

    // 🔒 ادعای اتمی: دو تقه‌ی پشت‌سرهم دو بار وضعیت را عوض نکند
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
    return [true, ''];
}

// ============================================================
// 🔴 لغو و برگشت پول
// ============================================================

/**
 * فعال‌سازی را می‌بندد و پول را برمی‌گرداند.
 * $why: 'cancel' (خواستِ کاربر) یا 'expired' (مهلت تمام شد)
 *
 * ⚠️ برگشت پول فقط یک بار — با ادعای اتمی روی خودِ رکورد.
 */
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

    $act = numGet($orderId);

    // به ۵سیم هم بگو، ولی اگر نگرفت جلوی برگشت پول را نگیر.
    if ($tellPanel) numTellPanelCancel($orderId);

    // 💰 پول برگردد
    $amount = (float)($act['price'] ?? 0);
    if ($amount > 0 && class_exists('MaOrder')) {
        $o = MaOrder::get($orderId);
        if ($o && empty($o['refunded'])) {
            MaOrder::set($orderId, function (&$x) {
                $x['refunded'] = true;
                $x['status']   = MaOrder::REJECT;
            });
            if (function_exists('maRefund'))
                maRefund((int)$act['uid'], $amount,
                         $why === 'expired' ? 'کد شماره نرسید' : 'لغو شماره');
        }
    }
    return [true, ''];
}

/**
 * 🔴 به ۵سیم بگو این سفارش را ببندد.
 *
 * ⚠️ دو باگ که قبلا اینجا بود و پول می‌سوزاند:
 *
 *   ۱. فقط شکستِ شبکه دیده می‌شد. پاسخِ معتبر ولی منفی «موفق» خوانده
 *      می‌شد، شماره روی پنل باز می‌ماند و هیچ‌کس خبردار نمی‌شد.
 *   ۲. حتی همان شکستِ شبکه فقط در error_log می‌نشست. کسی لاگِ سرور را
 *      نمی‌خواند.
 *
 * حالا: پاسخ واقعا بررسی می‌شود، شکست روی خودِ فعال‌سازی ثبت می‌شود تا
 * numTick دوباره تلاش کند، و بعد از چند تلاشِ ناموفق ادمین خبردار
 * می‌شود.
 *
 * و یک نکته‌ی خودِ ۵سیم: cancel فقط تا وقتی کار می‌کند که پیامکی نیامده
 * باشد. اگر کد بین درخواستِ کاربر و تماسِ ما رسیده باشد، لغو رد می‌شود
 * («order has sms») — ولی finish می‌گیرد. پس آن را هم امتحان می‌کنیم،
 * وگرنه سفارش تا آخرِ تایمر روی ۵سیم باز می‌ماند.
 *
 * برگشت: [موفق؟, پیام]
 */
function numTellPanelCancel($orderId) {
    $act = numGet($orderId);
    if (!$act || empty($act['aid'])) return [true, ''];

    $vars = ['id' => (string)$act['aid']];

    [$resp, $err] = numCall('cancel', $vars);
    $fail = is_array($resp) ? '' : ($err ?: 'پاسخی نیامد');

    // ⛳️ رد شد چون پیامک رسیده؟ پس «بستن» جواب می‌دهد
    if ($fail !== '') {
        [$r2, $e2] = numCall('close', $vars);
        if (is_array($r2)) $fail = '';
    }

    numSetAct($orderId, function (&$x) use ($fail) {
        $x['panel_err']   = $fail;
        $x['panel_tries'] = (int)($x['panel_tries'] ?? 0) + 1;
        if ($fail === '') { $x['panel_closed'] = time(); $x['closed'] = time(); unset($x['panel_pending']); }
        else              { $x['panel_pending'] = time(); }
        return true;
    });

    return [$fail === '', $fail];
}

/**
 * 🔁 لغوهایی که به ۵سیم نرسیدند را دوباره امتحان کن.
 *
 * یک تلاشِ ناموفق ممکن است فقط قطعیِ لحظه‌ای باشد. ولی اگر چند بار هم
 * نگرفت، دیگر خودش خوب نمی‌شود و ادمین باید بداند — هر شماره‌ای که روی
 * ۵سیم باز بماند، پولِ رفته است.
 */
function numRetryPanel($limit = 5) {
    $n = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        if (empty($act['panel_pending'])) continue;
        if (in_array(($act['status'] ?? ''), ['waiting', 'buying'], true)) continue;

        $tries = (int)($act['panel_tries'] ?? 0);
        if ($tries >= 6) continue;                       // بس است، دیگر خودش درست نمی‌شود
        if (time() - (int)$act['panel_pending'] < 60) continue;

        $oid = (string)$act['order'];
        [$ok, $err] = numTellPanelCancel($oid);
        $n++;

        // درست سرِ تلاشِ آخر، یک بار خبر بده
        if (!$ok && $tries + 1 >= 6 && defined('ADMIN_ID') && ADMIN_ID) {
            sendMsg(BOT_TOKEN, ADMIN_ID,
                "⚠️ <b>لغو روی ۵سیم نگرفت</b>\n\n" .
                '☎️ <code>' . h((string)($act['phone'] ?? '')) . "</code>\n" .
                '🧾 <code>' . h($oid) . "</code>\n" .
                '❌ <code>' . h(mb_substr((string)$err, 0, 200)) . "</code>\n\n" .
                "پول کاربر برگشته، ولی این سفارش روی ۵سیم باز مانده. " .
                "دستی ببندیدش، وگرنه هزینه‌اش برنمی‌گردد.",
                inlineKb([[btnCb('📋 شماره‌های باز', 'numopen', 'admin')]]));
        }
    }
    return $n;
}

/**
 * ⏰ کارِ پس‌زمینه — از همان تیک عمومی ربات صدا زده می‌شود.
 *
 * سه کار:
 *   ۱. لغوهایی که به ۵سیم نرسیدند
 *   ۲. شماره‌هایی که مهلتشان تمام شد → پول برگردد
 *   ۳. سفارش‌هایی که کدشان رسید و مهلتشان تمام شد → روی ۵سیم بسته شوند
 */
function numTick($limit = 10) {
    numRetryPanel(5);
    $now = time();
    $n   = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        $st = (string)($act['status'] ?? '');
        $over = $now - (int)($act['created'] ?? 0) >= numWaitFor($act);

        if ($st === 'waiting') {
            if (!$over) continue;
            numFinish((string)$act['order'], 'expired');
            $n++;
            continue;
        }
        // کد گرفته، مهلت هم تمام شده — دیگر «کد مجدد» معنی ندارد، ببندش
        if ($st === 'done' && $over && empty($act['closed'])) {
            numClose((string)$act['order']);
            $n++;
        }
    }
    return $n;
}

// ============================================================
// 🌍 کاتالوگ — کشورها و اپراتورها از ۵سیم
// ============================================================

/**
 * 🏆 رتبه‌ی یک کشور — کوچک‌تر یعنی بالاتر در مینی‌اپ.
 *
 * صفحه فقط چهل‌تای اول را نشان می‌دهد، پس «کدام چهل‌تا» مهم است: اگر
 * ترتیب همانی باشد که ۵سیم داده، ممکن است چهل کشوری بیاید که کسی
 * نمی‌خرد و روسیه‌ی پرفروش صفحه‌ی دوم بماند. پس کشورهای پرتقاضا اول
 * می‌آیند و بقیه پشتشان، به ترتیبِ الفبا.
 */
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

/**
 * 🚩 پرچم از روی کدِ دو حرفیِ ISO.
 *
 * ۵سیم برای هر کشور iso را می‌دهد، و دو حرفِ ISO دقیقا همان دو
 * «نشانگرِ منطقه‌ای» یونیکد است. پس به‌جای نگه داشتنِ یک جدولِ ۲۵۰
 * تایی که همیشه ناقص می‌ماند، پرچم را حساب می‌کنیم.
 */
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

/** نامِ فارسیِ کشور. آنچه را نداریم، انگلیسیِ خودِ ۵سیم می‌ماند. */
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

/**
 * 🏷 نامِ خواندنیِ یک اپراتور.
 *
 * ۵سیم اسم‌ها را با حروف کوچک و بی‌فاصله می‌دهد («tele2»، «virtual21»).
 * توی مینی‌اپ که کنارِ پرچم می‌نشیند، باید آدمیزادی باشد.
 */
function numOperFa($op) {
    $op = strtolower(trim((string)$op));
    if ($op === '' || $op === 'any') return 'خودکار';
    if (preg_match('/^virtual(\d+)$/', $op, $m)) return 'مجازی ' . $m[1];
    static $map = ['beeline'=>'Beeline','mts'=>'MTS','megafon'=>'MegaFon','tele2'=>'Tele2',
                   'rostelecom'=>'Rostelecom','yota'=>'Yota','tinkoff'=>'Tinkoff',
                   'lifecell'=>'Lifecell','kyivstar'=>'Kyivstar','vodafone'=>'Vodafone',
                   'altel'=>'Altel','activ'=>'Activ','tele'=>'Tele'];
    return $map[$op] ?? ucfirst($op);
}

/**
 * کاتالوگِ تلگرام را از ۵سیم می‌خواند.
 *
 * برگشت: [کشورها, ردیف‌ها, خطا]
 *   کشورها: slug => ['name' => …, 'flag' => …]
 *   ردیف‌ها: هرکدام ['sid','country','operator','name','price','usd','count','on', …]
 */
function numCatalog() {
    $rate = numRate();
    if ($rate <= 0)
        return [[], [], 'نرخِ تبدیل معلوم نیست — یا در همین صفحه نرخ را بگذارید، یا بخش قیمت‌گیری را روشن کنید'];

    [$px, $err] = num5Get('/guest/prices?product=' . rawurlencode(NUM5_PRODUCT), 30);
    if (!is_array($px)) return [[], [], $err ?: 'فهرست قیمت‌ها نیامد'];

    // نام و پرچمِ کشورها. اگر نیامد، از خودِ slug می‌سازیم.
    $meta = [];
    [$co] = num5Get('/guest/countries', 30);
    foreach ((array)$co as $slug => $x) {
        if (!is_array($x)) continue;
        $iso = '';
        if (is_array($x['iso'] ?? null)) { $k = array_keys($x['iso']); $iso = (string)($k[0] ?? ''); }
        $meta[(string)$slug] = [
            'fa'   => numCountryFa($slug, (string)($x['text_en'] ?? '')),
            'flag' => numFlagIso($iso),
        ];
    }

    $countries = $out = [];

    foreach ($px as $slug => $byProduct) {
        $slug = (string)$slug;
        if (!is_array($byProduct)) continue;
        $ops = $byProduct[NUM5_PRODUCT] ?? null;
        if (!is_array($ops)) continue;

        $cName = $meta[$slug]['fa']   ?? numCountryFa($slug);
        $flag  = $meta[$slug]['flag'] ?? '🌍';
        $rows  = [];

        foreach ($ops as $op => $info) {
            if (!is_array($info)) continue;
            $usd = (float)($info['cost']  ?? 0);
            $cnt = (int)  ($info['count'] ?? 0);
            if ($usd <= 0) continue;
            $rows[] = [
                'sid'      => $slug . '|' . $op,
                'country'  => $slug,
                'operator' => (string)$op,
                'flag'     => $flag,
                'cname'    => $cName,
                'rank'     => numRank($slug),
                'name'     => numOperFa($op),
                'usd'      => $usd,
                'price'    => numToman($usd, 0, false),   // خام — سود را numImport می‌کشد
                'count'    => $cnt,
                'rate'     => (float)($info['rate'] ?? 0),
                'on'       => $cnt > 0,
            ];
        }
        if (!$rows) continue;
        $countries[$slug] = ['name' => $cName, 'flag' => $flag];
        foreach ($rows as $r) $out[] = $r;
    }

    if (!$out) return [[], [], 'هیچ شماره‌ی تلگرامی در پاسخِ ۵سیم نبود'];

    // کشورهای پرتقاضا اول؛ داخلِ هر کشور، ارزان‌ترین بالا
    usort($out, fn($x, $y) => [$x['rank'], $x['cname'], $x['usd']]
                          <=> [$y['rank'], $y['cname'], $y['usd']]);
    return [$countries, $out, ''];
}

/**
 * کاتالوگ را در پیکربندی مینی‌اپ می‌نشاند.
 *
 * $markup: درصد سودی که روی قیمتِ ۵سیم کشیده می‌شود.
 * برگشت: [کشورِ تازه, ردیفِ تازه, به‌روزشده, خاموش‌شده]
 */
function numImport(array $countries, array $rows, $markup = 0, $syncPrice = null) {
    $newC = $newI = $upd = $off = 0;
    $mul  = 1 + (max(0, (float)$markup) / 100);
    // قیمتِ ۵سیم، منبعِ حقیقت است یا قیمتی که ادمین دستی گذاشته؟
    if ($syncPrice === null) $syncPrice = !empty(numVal('sync_price', true));

    maSet('num', function (&$a) use ($countries, $rows, $mul, $syncPrice,
                                     &$newC, &$newI, &$upd, &$off) {
        if (!is_array($a['cats'] ?? null))  $a['cats']  = [];
        if (!is_array($a['items'] ?? null)) $a['items'] = [];

        // ── 📁 هر کشور یک پوشه ──
        $byCode = [];
        foreach ($a['cats'] as $i => $c) {
            $code = trim((string)($c['code'] ?? ''));
            if ($code !== '') $byCode[$code] = $i;
        }
        $order = count($a['cats']);
        foreach ($countries as $code => $info) {
            $code = (string)$code;
            if (isset($byCode[$code])) continue;     // هست — دست نمی‌زنیم
            $a['cats'][] = [
                'id' => 'c' . bin2hex(random_bytes(3)), 'emoji' => $info['flag'],
                'name' => $info['name'], 'code' => $code, 'on' => true, 'order' => ++$order,
            ];
            $byCode[$code] = count($a['cats']) - 1;
            $newC++;
        }

        // ── ☎️ شماره‌ها ──
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
                // 🔒 نام و ایموجی و برچسب همیشه مالِ ادمین‌اند — دست نمی‌خورند.
                //    قیمت ولی بستگی دارد: اگر «قیمت از ۵سیم» روشن باشد،
                //    قیمتِ فروشنده منبعِ حقیقت است و هر بار به‌روز می‌شود؛
                //    وگرنه قیمتی که ادمین گذاشته سرِ جایش می‌ماند.
                $k = $bySid[$r['sid']];
                $was = !empty($a['items'][$k]['on']);
                $a['items'][$k]['on'] = (bool)$r['on'];
                if ($catId !== '') $a['items'][$k]['cat'] = $catId;
                if ($syncPrice) $a['items'][$k]['price'] = numRound100($r['price'] * $mul);
                // ترتیبِ نمایش هم از ۵سیم می‌آید — وگرنه کشورِ پرفروشی که
                // بار اول آخر افتاده، برای همیشه آخر می‌ماند
                $a['items'][$k]['order'] = $iOrder;
                if ($was && !$r['on']) $off++; else $upd++;
                continue;
            }

            $a['items'][] = [
                'id' => 'i' . bin2hex(random_bytes(3)), 'cat' => $catId, 'svc' => $r['sid'],
                'emoji' => (string)($r['flag'] ?? '☎️'),
                'name' => $r['name'], 'desc' => '',
                'price' => numRound100($r['price'] * $mul), 'unit' => '', 'badge' => '',
                'ask' => 'none', 'min' => 1, 'max' => 1,
                'on' => (bool)$r['on'], 'order' => $iOrder,
            ];
            $newI++;
        }

        // ردیفی که دیگر روی ۵سیم نیست: خاموش، نه پاک — شاید موقتا رفته باشد
        foreach ($a['items'] as $k => $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            if ($svc === '' || isset($seen[$svc]) || empty($it['on'])) continue;
            $a['items'][$k]['on'] = false;
            $off++;
        }
    });

    return [$newC, $newI, $upd, $off];
}

/** ردیف‌هایی که دیگر در کاتالوگ نیستند را پاک می‌کند */
function numPruneCatalog(array $keepSids) {
    $keep = array_flip(array_map('strval', $keepSids));
    $delI = $delC = 0;

    maSet('num', function (&$a) use ($keep, &$delI, &$delC) {
        $items = [];
        foreach ((array)($a['items'] ?? []) as $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            // ردیفِ دستیِ ادمین (بدون شناسه) هیچ‌وقت حذف نمی‌شود
            if ($svc === '' || isset($keep[$svc])) { $items[] = $it; continue; }
            $delI++;
        }
        $a['items'] = array_values($items);

        // پوشه‌ای که دیگر هیچ شماره‌ای ندارد و کدِ کشور دارد (یعنی خودمان
        // ساخته‌ایمش) جا اشغال می‌کند
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

/**
 * 🧹 پاک کردنِ ته‌مانده‌ی پنلِ قبلی.
 *
 * ربات قبلا به نامبرلند وصل بود و کدِ محصول‌هایش عددی بود («۱»، «۸»).
 * ۵سیم اسمی است («russia|beeline»). پس هیچ‌کدام از آن ردیف‌ها دیگر
 * قابلِ خرید نیستند — می‌مانند و فقط مینی‌اپ را شلوغ و ربات را کند
 * می‌کنند.
 *
 * ⚠️ محصول‌هایی که ادمین دستی ساخته (بدون شناسه) دست نمی‌خورند.
 */
function numForceTelegramOnly() {
    // تنظیماتِ پنلِ قبلی هم برود — وگرنه در config.json می‌ماند و
    // هر نوشتنِ تنظیمات، حملش می‌کند
    numSet(function (&$c) {
        foreach (['svc_only', 'preset'] as $k) unset($c[$k]);
        if (is_array($c['api'] ?? null))
            foreach (['ops','auth_type','auth_key','auth_value','spec_url','name','preset','preset_key'] as $k)
                unset($c['api'][$k]);
    });

    $delI = $delC = 0;
    maSet('num', function (&$a) use (&$delI, &$delC) {
        $keep = [];
        foreach ((array)($a['items'] ?? []) as $it) {
            $svc = trim((string)($it['svc'] ?? ''));
            // شناسه‌ی ۵سیم همیشه «کشور|اپراتور» است. هرچه این شکلی نیست،
            // مالِ پنلِ قبلی است.
            if ($svc === '' || str_contains($svc, '|')) { $keep[] = $it; continue; }
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

    // خبر بده — وگرنه ادمین مینی‌اپِ خالی می‌بیند و فکر می‌کند خراب شده
    if ($delI > 0 && defined('ADMIN_ID') && ADMIN_ID) {
        sendMsg(BOT_TOKEN, ADMIN_ID,
            "☎️ <b>ربات به ۵سیم وصل شد</b>\n\n" .
            "🗑 <b>" . fmtNum($delI) . "</b> شماره و <b>" . fmtNum($delC) . "</b> پوشه‌ی پنلِ قبلی حذف شد.\n\n" .
            "آن‌ها کدِ نامبرلند داشتند و روی ۵سیم قابل خرید نیستند.\n\n" .
            "🔑 توکن ۵سیم را ثبت کنید و یک بار «📥 وارد کردن» را بزنید " .
            "تا کشورها با قیمتِ روز بیایند.",
            inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
    }
    return [$delI, $delC];
}

// ============================================================
// 🛠 پنل مدیریت — اتصال به ۵سیم
// ============================================================
//
// این بخش درِ خودش را دارد و جدا از بقیه‌ی پنل زندگی می‌کند: هیچ
// صفحه‌ای از جاهای دیگر داخلش ساخته نمی‌شود و خودش هم چیزی به
// صفحه‌های دیگر اضافه نمی‌کند.
//
// و عمداً کوچک است. نسخه‌ی قبلی بیست‌وچند دکمه داشت — مسیر، بدنه،
// نوعِ احراز، نامِ هدر — و هیچ‌کدامشان کاری نمی‌کرد جز اینکه یک جای
// دیگر برای اشتباه تایپی باشد. ۵سیم یک شکل دارد و همان یک شکل اینجا
// نوشته شده. چیزی که تنظیم می‌شود همان‌هایی است که واقعا انتخابِ
// فروشنده‌اند: توکن، سود، نرخ، مهلت‌ها.

function numAdmHome($chatId, $msgId) {
    $api  = numVal('api', []);
    $open = $stuck = 0;
    foreach (numAll() as $a) {
        if (($a['status'] ?? '') === 'waiting') $open++;
        if (!empty($a['panel_pending']) && !in_array(($a['status'] ?? ''), ['waiting','buying'], true)) $stuck++;
    }

    $tok  = trim((string)($api['token'] ?? ''));
    $rate = numRate();
    $mk   = (float)numVal('markup', 0);

    $t  = "☎️ <b>شماره مجازی تلگرام — ۵سیم</b>\n\n";
    $t .= "وضعیت: " . (!empty($api['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    // 🔒 هیچ تکه‌ای از توکن نشان داده نمی‌شود — نه اولش، نه آخرش.
    //    عکسِ صفحه‌ی پنل زیاد دست‌به‌دست می‌شود.
    $t .= "🔑 توکن: " . ($tok !== ''
          ? '✅ ثبت شده (' . fmtNum(strlen($tok)) . ' حرف)' : '<b>خالی</b>') . "\n";
    $t .= "🎯 محصول: <b>تلگرام</b> — همین و بس\n\n";

    $t .= "💵 نرخ تبدیل: " . ($rate > 0
          ? '<b>' . fmtNum($rate) . '</b> تومان' .
            ((float)($api['rate'] ?? 0) > 0 ? ' (دستی)' : ' (از بخش قیمت‌گیری)')
          : '<b>معلوم نیست</b>') . "\n";
    $t .= "📈 سود: <b>" . rtrim(rtrim(number_format($mk, 1), '0'), '.') . "٪</b>\n";
    $t .= "💰 قیمت از ۵سیم: " . (!empty(numVal('sync_price', true)) ? '✅ هر بار تازه' : '🔒 دستی') . "\n\n";

    $t .= "⏳ مهلت انتظار کد: <b>" . (int)numVal('wait', 900) . "</b> ثانیه\n";
    $t .= "🔁 فاصله‌ی پیگیری: <b>" . (int)numVal('poll', 6) . "</b> ثانیه\n";
    $t .= "⏱ مهلت تماس: <b>" . (int)($api['timeout'] ?? 15) . "</b> ثانیه\n";

    // 📊 یک نگاه به کاتالوگ، تا معلوم باشد چیزی برای فروش هست یا نه
    if (function_exists('maGet')) {
        $a  = maGet('num');
        $nc = count(array_filter((array)($a['cats'] ?? []),  fn($x) => !empty($x['on'])));
        $ni = count(array_filter((array)($a['items'] ?? []), fn($x) => !empty($x['on'])));
        $t .= "\n🌍 کشور: <b>{$nc}</b> · ☎️ شماره: <b>{$ni}</b>\n";
        $t .= "مینی‌اپ: " . (!empty($a['on']) ? '✅ باز' : '❌ بسته') . "\n";
        if ($ni === 0) $t .= "⚠️ هنوز چیزی برای فروش نیست — «📥 وارد کردن» را بزنید.\n";
    }

    if ($rate <= 0)
        $t .= "\n⚠️ بدون نرخ تبدیل، قیمتی وارد نمی‌شود. یا «💵 نرخ دلار» را دستی بگذارید، " .
              "یا بخش قیمت‌گیری را روشن کنید.\n";
    if ($tok === '')
        $t .= "\n⚠️ تا توکن ثبت نشود هیچ خریدی انجام نمی‌شود.\n";
    if ($stuck) $t .= "\n⚠️ <b>{$stuck}</b> سفارش روی ۵سیم بسته نشده — «📋 شماره‌های باز» را ببینید.";
    if ($open)  $t .= "\n⏳ <b>{$open}</b> شماره‌ی باز، در انتظار کد.";

    $rows = [
        [btnCb(!empty($api['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'numtog', 'info')],
        [btnCb('🔑 توکن ۵سیم', 'nums_token', 'admin')],
        [btnCb('📥 وارد کردن کشورها و قیمت‌ها', 'numimp', 'buy')],
        [btnCb('📈 درصد سود', 'nums_markup', 'admin'),
         btnCb('💵 نرخ دلار', 'nums_rate', 'admin')],
        [btnCb(!empty(numVal('sync_price', true)) ? '💰 قیمت از ۵سیم: روشن' : '💰 قیمت از ۵سیم: خاموش',
               'numsync', 'info')],
        [btnCb('💰 موجودی حساب ۵سیم', 'numtest', 'confirm')],
        [btnCb('⏳ مهلت انتظار کد', 'nums_wait', 'admin'),
         btnCb('🔁 فاصله‌ی پیگیری', 'nums_poll', 'admin')],
        [btnCb('⏱ مهلت تماس', 'nums_timeout', 'admin'),
         btnCb('🧢 سقف قیمت خرید', 'nums_max', 'admin')],
        [btnCb('📋 شماره‌های باز', 'numopen', 'reject'),
         btnCb('🧹 پاک‌سازی', 'numclean', 'reject')],
        [btnCb('🌍 کشورها', 'maadm_cats_num', 'admin'),
         btnCb('☎️ شماره‌ها', 'maadm_items_num', 'admin')],
        [btnCb('🎨 ظاهر و متن‌های مینی‌اپ', 'maadm_app_num', 'admin')],
        [btnCb('🔗 آدرس مینی‌اپ', 'numlink', 'info')],
        [btnCb('🔙 بازگشت', 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

/**
 * 📥 پیش‌نمایشِ وارد کردن.
 *
 * اول نشان می‌دهیم چه چیزی قرار است بیاید و چه چیزی دست‌نخورده می‌ماند،
 * بعد ادمین تایید می‌کند. نوشتنِ بی‌خبر روی کاتالوگی که ادمین ساخته،
 * حتی اگر ادغامی باشد، ترسناک است.
 */
function numAdmImport($chatId, $msgId) {
    $back = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    if (!numReady()) {
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "⚠️ اول توکن ۵سیم را ثبت کنید و بخش را روشن کنید.", $back);
        return;
    }

    [$countries, $rows, $err] = numCatalog();
    if ($err !== '') {
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "❌ <b>فهرست نیامد</b>\n<code>" . h(mb_substr($err, 0, 300)) . "</code>",
            inlineKb([[btnCb('💰 موجودی حساب ۵سیم', 'numtest', 'confirm')],
                      [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
        return;
    }

    // چقدرش تازه است؟ — تا ادمین بداند این دکمه چه می‌کند
    $a = maGet('num');
    $haveC = $haveI = [];
    foreach ((array)($a['cats'] ?? []) as $c)
        if (trim((string)($c['code'] ?? '')) !== '') $haveC[(string)$c['code']] = 1;
    foreach ((array)($a['items'] ?? []) as $i)
        if (trim((string)($i['svc'] ?? '')) !== '') $haveI[(string)$i['svc']] = 1;

    $newC = $newI = 0;
    foreach ($countries as $code => $_) if (!isset($haveC[(string)$code])) $newC++;
    foreach ($rows as $r)               if (!isset($haveI[$r['sid']])) $newI++;

    $mk = (float)numVal('markup', 0);

    // چندتا از چیزهایی که الان در مینی‌اپ هستند، دیگر روی ۵سیم نیستند؟
    $keep = array_flip(array_map('strval', array_column($rows, 'sid')));
    $willDel = 0;
    foreach ((array)($a['items'] ?? []) as $it) {
        $svc = trim((string)($it['svc'] ?? ''));
        if ($svc !== '' && !isset($keep[$svc])) $willDel++;
    }

    $t  = "📥 <b>وارد کردن از ۵سیم</b>\n\n";
    $t .= "🎯 فقط <b>تلگرام</b>\n";
    $t .= "<b>" . fmtNum(count($rows)) . "</b> شماره از <b>" . fmtNum(count($countries)) . "</b> کشور\n";
    $t .= "💵 نرخ: <b>" . fmtNum(numRate()) . "</b> تومان به ازای هر دلار\n\n";

    $t .= "اگر تایید کنید:\n";
    $t .= "➕ <b>{$newC}</b> کشور و <b>{$newI}</b> شماره‌ی تازه ساخته می‌شود\n";
    $sync = !empty(numVal('sync_price', true));
    $t .= $sync
        ? "💰 قیمتِ ردیف‌های موجود هم <b>از ۵سیم به‌روز می‌شود</b>\n"
        : "🔒 قیمتِ ردیف‌های موجود <b>دست نمی‌خورد</b>\n";
    $t .= "🔒 نام و ایموجی و برچسب همیشه دست‌نخورده می‌مانند\n";
    $t .= $willDel
        ? "🧹 <b>{$willDel}</b> ردیفی که دیگر روی ۵سیم نیست <b>حذف</b> می‌شود\n"
        : "🗑 ردیفی که موقتا ناموجود شود فقط خاموش می‌شود، پاک نه\n";
    $t .= "\n💵 سود روی قیمتِ ۵سیم: <b>" . rtrim(rtrim(number_format($mk, 1), '0'), '.') . "٪</b>";
    if ($mk <= 0) $t .= "\n<i>یعنی به قیمتِ خرید می‌فروشید — سود را قبل از وارد کردن ست کنید.</i>";

    // چند نمونه، تا ادمین ببیند چه چیزی می‌آید
    $t .= "\n\n<b>نمونه:</b>\n";
    foreach (array_slice($rows, 0, 5) as $r) {
        $t .= '• ' . h($r['flag']) . ' ' . h(mb_substr($r['cname'], 0, 18)) . ' · ' .
              h(mb_substr($r['name'], 0, 16)) . ' — ' .
              fmtNum(numRound100($r['price'] * (1 + max(0, $mk) / 100))) . ' ت' .
              ' <i>($' . rtrim(rtrim(number_format($r['usd'], 3), '0'), '.') . ')</i>' .
              ($r['count'] > 0 ? ' · ' . fmtNum($r['count']) . ' موجود' : ' · ناموجود') . "\n";
    }
    if (count($rows) > 5) $t .= '<i>… و ' . fmtNum(count($rows) - 5) . " تای دیگر</i>\n";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('✅ تایید و وارد کن', 'numimpgo', 'buy')],
        [btnCb('📈 درصد سود', 'nums_markup', 'admin'),
         btnCb($sync ? '💰 قیمت از ۵سیم: روشن' : '💰 قیمت از ۵سیم: خاموش', 'numsync', 'info')],
        [btnCb('🔙 بازگشت', 'num_home', 'nav')],
    ]));
}

/** انجامِ واقعیِ وارد کردن */
function numAdmImportGo($chatId, $msgId) {
    [$countries, $rows, $err] = numCatalog();
    if ($err !== '') {
        editMsg(BOT_TOKEN, $chatId, $msgId, "❌ " . h(mb_substr($err, 0, 300)),
                inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
        return;
    }

    [$nc, $ni, $up, $off] = numImport($countries, $rows, (float)numVal('markup', 0));
    [$delI, $delC] = numPruneCatalog(array_column($rows, 'sid'));

    $t  = "✅ <b>وارد شد</b>\n\n";
    $t .= "🌍 کشور تازه: <b>{$nc}</b>\n";
    $t .= "➕ شماره‌ی تازه: <b>{$ni}</b>\n";
    $t .= "🔄 به‌روزشده: <b>{$up}</b>\n";
    $t .= "⚪️ خاموش‌شده: <b>{$off}</b>\n";
    if ($delI || $delC) $t .= "🧹 حذف‌شده: <b>{$delI}</b> شماره" .
                              ($delC ? " · <b>{$delC}</b> پوشه" : '') . "\n";
    $t .= "\nهر کشور یک پوشه است و شماره‌هایش تویش.";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('☎️ مینی‌اپ شماره مجازی', 'maadm_app_num', 'admin')],
        [btnCb('🔙 بازگشت', 'num_home', 'nav')],
    ]));
}

/** 💰 موجودی حساب ۵سیم — همان تستِ اتصال هم هست */
function numAdmTest($chatId) {
    $back = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    if (trim((string)numVal('api.token', '')) === '') {
        sendMsg(BOT_TOKEN, $chatId, "⚠️ اول توکن ۵سیم را ثبت کنید.", $back);
        return;
    }
    [$bal, $cur, $err] = numBalance();
    if ($err !== '') {
        sendMsg(BOT_TOKEN, $chatId, "❌ <b>نگرفت</b>\n<code>" . h(mb_substr($err, 0, 300)) . '</code>', $back);
        return;
    }
    $rate = numRate();
    $t  = "✅ <b>وصل است</b>\n\n";
    $t .= "💰 موجودی: <b>" . fmtNum($bal) . "</b> " . h($cur ?: '') . "\n";
    if ($rate > 0) $t .= "≈ <b>" . fmtNum(numRound100($bal * $rate)) . "</b> تومان\n";
    sendMsg(BOT_TOKEN, $chatId, $t, $back);
}

/** 🧹 صفحه‌ی پاک‌سازی */
function numAdmClean($chatId, $msgId) {
    $a  = maGet('num');
    $n  = count((array)($a['items'] ?? []));
    $nc = count((array)($a['cats'] ?? []));
    $on = function_exists('maCountOn') ? maCountOn($a) : 0;
    $sz = @filesize(DATA_DIR . '/config.json') ?: 0;

    $t  = "🧹 <b>پاک‌سازی کاتالوگ</b>\n\n";
    $t .= "الان: <b>" . fmtNum($n) . "</b> شماره (<b>" . fmtNum($on) . "</b> روشن) · <b>" .
          fmtNum($nc) . "</b> پوشه\n";
    $t .= "اندازه‌ی تنظیمات: <b>" . number_format($sz / 1024, 1) . "</b> کیلوبایت\n\n";
    if ($n > 500)
        $t .= "⚠️ این تعداد هم مینی‌اپ را سنگین می‌کند هم کلِ ربات را: هر تغییرِ " .
              "تنظیمات، این فایل را از نو می‌نویسد.\n\n";

    $t .= "<b>دو راه:</b>\n";
    $t .= "۱️⃣ <b>هرچه روی ۵سیم نیست برود</b> — فهرست را تازه می‌گیرد و بقیه را حذف می‌کند. " .
          "قیمت و نامی که خودتان ست کرده‌اید سرِ جایش می‌ماند.\n";
    $t .= "۲️⃣ <b>از صفر</b> — همه پاک می‌شوند و بعد دوباره وارد می‌کنید. " .
          "وقتی کاتالوگ به‌هم‌ریخته است، این تمیزتر است.\n\n";
    $t .= "<i>در هر دو حالت، شماره‌های دستیِ خودتان (بدون شناسه) دست نمی‌خورند.</i>";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
        [btnCb('🧹 هرچه روی ۵سیم نیست برود', 'numclean1', 'confirm')],
        [btnCb('🗑 از صفر شروع کن', 'numclean2', 'reject')],
        [btnCb('🔙 بازگشت', 'num_home', 'nav')],
    ]));
}

/** ۱️⃣ فهرستِ تازه را بگیر و بقیه را حذف کن */
function numAdmCleanKeep($chatId, $msgId) {
    [$co, $rows, $err] = numCatalog();
    if ($err !== '') {
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "❌ <b>فهرست از ۵سیم نیامد</b>\n<code>" . h(mb_substr($err, 0, 240)) . "</code>\n\n" .
            "بدون آن نمی‌دانیم کدام ردیف هنوز هست. اگر ۵سیم در دسترس نیست، " .
            "«🗑 از صفر» را بزنید و بعد دوباره وارد کنید.",
            inlineKb([[btnCb('🗑 از صفر شروع کن', 'numclean2', 'reject')],
                      [btnCb('🔙 بازگشت', 'numclean', 'nav')]]));
        return;
    }

    [$delI, $delC] = numPruneCatalog(array_column($rows, 'sid'));
    $a = maGet('num');
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "✅ <b>پاک شد</b>\n\n" .
        "🗑 <b>" . fmtNum($delI) . "</b> شماره و <b>" . fmtNum($delC) . "</b> پوشه حذف شد.\n" .
        "الان: <b>" . fmtNum(count((array)($a['items'] ?? []))) . "</b> شماره.\n\n" .
        "حالا «📥 وارد کردن» را بزنید تا قیمت‌ها هم تازه شوند.",
        inlineKb([[btnCb('📥 وارد کردن', 'numimp', 'buy')],
                  [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
}

/** ۲️⃣ کاتالوگ را خالی کن — جز ردیف‌های دستی */
function numAdmCleanAll($chatId, $msgId) {
    $delI = $delC = 0;
    maSet('num', function (&$a) use (&$delI, &$delC) {
        $keep = [];
        foreach ((array)($a['items'] ?? []) as $it) {
            // بدونِ شناسه یعنی ادمین خودش ساخته — مالِ ما نیست که پاکش کنیم
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
        "اندازه‌ی تنظیمات: <b>" . number_format($sz / 1024, 1) . "</b> کیلوبایت\n\n" .
        "حالا «📥 وارد کردن» را بزنید.",
        inlineKb([[btnCb('📥 وارد کردن', 'numimp', 'buy')],
                  [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
}

/** 📋 شماره‌های باز */
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

    // ⚠️ سفارش‌هایی که در ربات بسته شده‌اند ولی روی ۵سیم نه — هرکدام پولِ رفته
    $stuck = [];
    foreach (numAll() as $a) {
        if (empty($a['panel_pending'])) continue;
        if (in_array(($a['status'] ?? ''), ['waiting', 'buying'], true)) continue;
        $stuck[] = $a;
    }
    if ($stuck) {
        $t .= "\n⚠️ <b>روی ۵سیم بسته نشدند</b> (" . count($stuck) . ")\n";
        $t .= "<i>پول کاربر برگشته، ولی هزینه‌ی این‌ها در ۵سیم برنگشته.</i>\n";
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

// ---------- 🎬 دکمه‌ها ----------

/** برگشت true یعنی این callback مالِ بخش شماره بود و رسیدگی شد */
function numCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (!str_starts_with((string)$data, 'num')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    $ack = function ($m = '') use ($cbId) { answerCb(BOT_TOKEN, $cbId, $m); };

    if ($data === 'num_home')  { $ack(); numAdmHome($chatId, $msgId);  return true; }
    if ($data === 'numimp')    { $ack('⏳ در حال خواندن…'); numAdmImport($chatId, $msgId); return true; }
    if ($data === 'numimpgo')  { $ack('⏳'); numAdmImportGo($chatId, $msgId); return true; }
    if ($data === 'numtest')   { $ack('⏳'); numAdmTest($chatId); return true; }
    if ($data === 'numopen')   { $ack(); numAdmOpen($chatId, $msgId); return true; }
    if ($data === 'numclean')  { $ack(); numAdmClean($chatId, $msgId); return true; }
    if ($data === 'numclean1') { $ack('⏳'); numAdmCleanKeep($chatId, $msgId); return true; }
    if ($data === 'numclean2') { $ack('⏳'); numAdmCleanAll($chatId, $msgId); return true; }

    if ($data === 'numtog') {
        numSet(function (&$c) { $c['api']['on'] = empty($c['api']['on']); });
        $ack(!empty(numVal('api.on')) ? '✅ روشن' : '❌ خاموش');
        numAdmHome($chatId, $msgId);
        return true;
    }

    if ($data === 'numsync') {
        numSet(function (&$c) { $c['sync_price'] = empty($c['sync_price']); });
        $ack(!empty(numVal('sync_price')) ? '💰 قیمت از ۵سیم' : '🔒 قیمت دستی');
        numAdmHome($chatId, $msgId);
        return true;
    }

    if ($data === 'numlink') {
        $u = function_exists('maAppUrl') ? maAppUrl('num') : '';
        $ack();
        sendMsg(BOT_TOKEN, $chatId,
            $u !== '' ? "🔗 <b>آدرس مینی‌اپ شماره</b>\n<code>" . h($u) . '</code>'
                      : "⚠️ اول در بخش مینی‌اپ‌ها دامنه را ثبت کنید.",
            inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
        return true;
    }

    // 🔴 لغو دستیِ یک شماره
    if (str_starts_with($data, 'numkill_')) {
        [$ok, $err] = numFinish(substr($data, 8), 'cancel');
        $ack($ok ? '✅ لغو شد' : '⚠️ ' . mb_substr($err, 0, 40));
        numAdmOpen($chatId, $msgId);
        return true;
    }

    // 🔁 دوباره تلاش کن این سفارش را روی ۵سیم ببندی
    if (str_starts_with($data, 'numretry_')) {
        [$ok, $err] = numTellPanelCancel(substr($data, 9));
        $ack($ok ? '✅ بسته شد' : '❌ ' . mb_substr($err, 0, 60));
        numAdmOpen($chatId, $msgId);
        return true;
    }

    // ✍️ ورودی‌های متنی
    if (str_starts_with($data, 'nums_')) {
        $f = substr($data, 5);
        static $ask = [
            'token'   => ["🔑 <b>توکن ۵سیم</b>\n\nاز 5sim.net → Settings → API key بردارید و همین‌جا بفرستید.\n\n<i>پیامتان بلافاصله پاک می‌شود.</i>", 'num_token'],
            'markup'  => ["📈 <b>درصد سود</b>\n\nچند درصد روی قیمتِ ۵سیم کشیده شود؟ مثلا <code>25</code>", 'num_markup'],
            'rate'    => ["💵 <b>نرخ دلار</b>\n\nهر ۱ دلار چند تومان؟ عدد بفرستید.\n\n<code>-</code> بفرستید تا خودکار از بخش قیمت‌گیری بگیرد.", 'num_rate'],
            'wait'    => ["⏳ <b>مهلت انتظار کد</b> (ثانیه)\n\nمثلا <code>900</code>", 'num_wait'],
            'poll'    => ["🔁 <b>فاصله‌ی پیگیری</b> (ثانیه)\n\nمثلا <code>6</code>", 'num_poll'],
            'timeout' => ["⏱ <b>مهلت تماس با ۵سیم</b> (ثانیه)\n\nمثلا <code>15</code>", 'num_timeout'],
            'max'     => ["🧢 <b>سقف قیمت خرید</b> (دلار)\n\nاگر قیمتِ لحظه‌ای از این بیشتر شد، خرید انجام نشود.\n\n<code>-</code> یعنی بی‌سقف.", 'num_max'],
        ];
        if (!isset($ask[$f])) { $ack(); return true; }
        [$txt, $state] = $ask[$f];
        setState($uid, $state, []);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, $txt,
                inlineKb([[btnCb('🔙 بی‌خیال', 'num_home', 'nav')]]));
        return true;
    }

    $ack();
    return true;
}

// ---------- ✍️ ورودی‌های متنی ----------

function numStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'num_')) return false;
    $plain = trim((string)($msg['text'] ?? ''));
    $blank = ($plain === '-' || $plain === '—');
    $back  = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    $done  = function ($t, $kb = null) use ($chatId, $back, $uid) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $t, $kb ?: $back);
    };

    switch ($action) {
        case 'num_token':
            // 🔒 توکن در تاریخچه‌ی گفتگو نمی‌ماند
            $v = $blank ? '' : preg_replace('/\s+/', '', $plain);
            if ($v !== '' && strlen($v) < 20) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ این توکن خیلی کوتاه است. کلِ کلید را بفرستید.", $back);
                return true;
            }
            numSet(function (&$c) use ($v) { $c['api']['token'] = $v; });
            if (!empty($msg['message_id'])) delMsg(BOT_TOKEN, $chatId, (int)$msg['message_id']);
            $done($v === '' ? '✅ توکن پاک شد.' : "✅ توکن ثبت شد و پیامتان پاک شد.",
                  inlineKb([[btnCb('💰 تست اتصال', 'numtest', 'confirm')],
                            [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
            return true;

        case 'num_markup':
            $v = min(1000.0, max(0.0, (float)str_replace([',', '،'], '', numDigitsF($plain))));
            numSet(function (&$c) use ($v) { $c['markup'] = $v; });
            $done('✅ سود: <b>' . rtrim(rtrim(number_format($v, 1), '0'), '.') . '٪</b>',
                  inlineKb([[btnCb('📥 وارد کردن', 'numimp', 'buy')],
                            [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
            return true;

        case 'num_rate':
            $v = $blank ? 0.0 : max(0.0, (float)str_replace([',', '،'], '', numDigitsF($plain)));
            numSet(function (&$c) use ($v) { $c['api']['rate'] = $v; });
            $done($v > 0
                ? '✅ نرخ دستی: <b>' . fmtNum($v) . '</b> تومان'
                : '✅ نرخ از بخش قیمت‌گیری گرفته می‌شود' .
                  (numRate() > 0 ? ' — الان <b>' . fmtNum(numRate()) . '</b> تومان' : ' — که الان در دسترس نیست'));
            return true;

        case 'num_max':
            $v = $blank ? 0.0 : max(0.0, (float)str_replace([',', '،'], '', numDigitsF($plain)));
            numSet(function (&$c) use ($v) { $c['api']['max'] = $v; });
            $done($v > 0 ? '✅ سقف: <b>$' . rtrim(rtrim(number_format($v, 2), '0'), '.') . '</b>'
                         : '✅ بی‌سقف.');
            return true;

        case 'num_wait':
            $v = max(60, min(86400, (int)numDigits($plain)));
            numSet(function (&$c) use ($v) { $c['wait'] = $v; });
            $done('✅ مهلت انتظار: <b>' . $v . '</b> ثانیه');
            return true;

        case 'num_poll':
            $v = max(3, min(120, (int)numDigits($plain)));
            numSet(function (&$c) use ($v) { $c['poll'] = $v; });
            $done('✅ فاصله‌ی پیگیری: <b>' . $v . '</b> ثانیه');
            return true;

        case 'num_timeout':
            $v = max(3, min(60, (int)numDigits($plain)));
            numSet(function (&$c) use ($v) { $c['api']['timeout'] = $v; });
            $done('✅ مهلت تماس: <b>' . $v . '</b> ثانیه');
            return true;
    }
    return false;
}

/** مثل numDigits ولی نقطه‌ی اعشار را نگه می‌دارد */
function numDigitsF($s) {
    $s = strtr((string)$s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
                            '٫'=>'.', '،'=>'']);
    return preg_replace('/[^0-9.]/', '', $s);
}

/** عددِ فارسی یا عربی هم عدد است */
function numDigits($s) {
    $s = strtr((string)$s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    return preg_replace('/\D+/', '', $s);
}

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
if (!defined('NUMNL_BASE'))   define('NUMNL_BASE', 'https://api.numberland.ir');

/**
 * 🏪 دو فروشنده، هر دو داخلِ کد.
 *
 * چرا هر دو: ۵سیم ارزان‌تر و بزرگ‌تر است، ولی از داخلِ ایران اغلب
 * اصلا در دسترس نیست — DNS جوابِ دیگری می‌دهد و درخواست به مقصد
 * نمی‌رسد. نامبرلند ایرانی است و می‌رسد. پس انتخابش با فروشنده
 * است، نه با ما.
 *
 * ⚠️ باز هم هیچ مسیری دستی تنظیم نمی‌شود. شکلِ هر دو اینجا نوشته
 *    شده و تنها چیزی که ادمین می‌دهد کلیدِ همان فروشنده است.
 */
function numProviders() {
    return [
        '5sim' => [
            'label' => '🌍 ۵سیم (5sim.net)',
            'name'  => '۵سیم',
            'base'  => NUM5_BASE,
            'key'   => 'توکن',
            'cur'   => 'دلار',      // قیمت‌ها ارزی‌اند → نرخ لازم است
            'help'  => '5sim.net ← Settings ← API key (کلیدِ بالایی، همان که با eyJ شروع می‌شود)',
        ],
        'numberland' => [
            'label' => '🇮🇷 نامبرلند (numberland.ir)',
            'name'  => 'نامبرلند',
            'base'  => NUMNL_BASE,
            'key'   => 'کلید API',
            'cur'   => 'تومان',     // قیمت‌ها تومانی‌اند → نرخ لازم نیست
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

/** آیا قیمتِ این فروشنده ارزی است و باید تبدیل شود؟ */
function numNeedsRate() { return numProv() === '5sim'; }

/** کلیدِ فروشنده‌ی فعال */
function numKey() {
    return trim((string)numVal(numProv() === 'numberland' ? 'api.nl_key' : 'api.token', ''));
}

/** آدرسِ پایه — خالی یعنی پیش‌فرضِ همان فروشنده */
function numBase() {
    $own = rtrim(trim((string)numVal('api.base', '')), '/');
    return $own !== '' ? $own : rtrim(numProvInfo()['base'], '/');
}

function numDefaults() {
    return [
        'provider' => '5sim',
        'wait'   => 900,       // مهلت انتظار کد — ثانیه
        'poll'   => 6,         // فاصله‌ی دو پرسش از فروشنده — ثانیه
        'markup' => 0,         // درصد سود روی قیمتِ فروشنده
        'sync_price' => true,  // قیمتِ فروشنده منبعِ حقیقت باشد و هر بار به‌روز شود
        'api'   => [
            'on'      => false,
            'token'   => '',            // توکن ۵سیم (JWT)
            'nl_key'  => '',            // کلید نامبرلند
            'nl_svc'  => '1',           // کد سرویسِ تلگرام نزد نامبرلند
            // 🌐 آدرسِ پایه. خالی یعنی آدرسِ رسمیِ همان فروشنده.
            //    فقط برای وقتی که سرور مستقیم نمی‌رسد و باید از یک
            //    واسطه رد شود.
            'base'    => '',
            'timeout' => 15,
            // 💵 نرخِ هر واحدِ پولِ ارزی به تومان — فقط برای ۵سیم.
            //    صفر یعنی «از بخش قیمت‌گیریِ خودِ ربات بگیر».
            'rate'    => 0,
            // 🧢 سقفِ قیمتِ هر خرید به واحدِ فروشنده. صفر یعنی بی‌سقف.
            'max'     => 0,
        ],
    ];
}

function numCfg() {
    $c = cfg()['numbers'] ?? null;
    $d = numDefaults();
    if (!is_array($c)) return $d;

    $out = array_replace($d, array_intersect_key($c,
        ['wait' => 1, 'poll' => 1, 'markup' => 1, 'sync_price' => 1, 'provider' => 1]));
    $out['api'] = array_replace($d['api'],
        array_intersect_key(is_array($c['api'] ?? null) ? $c['api'] : [],
                            ['on'=>1,'token'=>1,'nl_key'=>1,'nl_svc'=>1,
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

/**
 * 💵 نرخِ تبدیل: هر ۱ واحدِ پولِ ۵سیم چند تومان است.
 *
 * اگر ادمین عددی گذاشته باشد همان؛ وگرنه نرخِ زنده‌ی دلار از بخش
 * قیمت‌گیریِ خودِ ربات. صفر یعنی «نمی‌دانم» — و آن‌وقت قیمتی هم وارد
 * نمی‌شود، چون قیمتِ غلط بدتر از نبودِ قیمت است.
 */
function numRate() {
    // نامبرلند تومانی می‌فروشد — نرخی در کار نیست
    if (!numNeedsRate()) return 1.0;
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
    $base = numBase();
    if ($base === '') return [null, 'آدرس فروشنده ثبت نشده'];
    $key = numKey();
    if ($key === '') return [null, 'کلید ' . numProvName() . ' ثبت نشده'];
    if ($timeout === null) $timeout = (int)numVal('api.timeout', 15);

    $url = $base . '/' . ltrim((string)$path, '/');
    $hdr = ['Accept: application/json'];

    // 🔑 هر فروشنده کلید را جای خودش می‌خواهد: ۵سیم در سرآیند، نامبرلند
    //    داخلِ خودِ آدرس. همین یک تفاوت است و همین‌جا تمام می‌شود.
    if (numProv() === 'numberland')
        $url .= (str_contains($url, '?') ? '&' : '?') . 'apikey=' . rawurlencode($key);
    else
        $hdr[] = 'Authorization: Bearer ' . $key;

    if (function_exists('__num5Hook')) return __num5Hook($url, $key, $timeout);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => max(3, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',          // gzip — فهرست قیمت‌ها بزرگ است
        // بعضی دیوارهای آتش، درخواستِ بی‌شناسه را رد می‌کنند
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
        CURLOPT_HTTPHEADER     => $hdr,
    ]);
    $res  = curl_exec($ch);
    $cerr = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return num5Parse($res, $cerr, $code);
}

/** آدرس بدون کلید — برای نشان دادن به ادمین */
function numSafeUrl($url) {
    return preg_replace('/([?&]apikey=)[^&]*/i', '$1***', (string)$url);
}

/** پاسخِ خام → [آرایه, خطا]. جدا نوشته شده تا آزمون‌پذیر باشد. */
function num5Parse($res, $cerr, $code) {
    if ($res === false || $res === null) return [null, 'اتصال برقرار نشد: ' . $cerr];

    // 🕵️ جوابی که از فروشنده نیامده.
    //
    //    روی خیلی از سرورهای ایرانی، درخواست به ۵سیم اصلا به مقصد
    //    نمی‌رسد: یک واسط جوابِ خودش را می‌دهد. شکلش هم لو می‌رود —
    //    ۵سیم خطا را متنِ خام می‌دهد، نه JSONِ {"status":404,…}.
    //    بدون این تشخیص، ادمین ساعت‌ها دنبالِ کلیدِ سالم می‌گردد.
    if (numLooksIntercepted((string)$res, (int)$code))
        return [null, 'این پاسخ از ' . numProvName() . ' نیامده — یک واسط جوابش را داده. ' .
                      'یعنی درخواستِ سرور به مقصد نمی‌رسد.'];

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

/**
 * 🕵️ آیا این پاسخ را یک واسط ساخته، نه خودِ فروشنده؟
 *
 * نشانه‌اش شکلِ پاسخ است: هیچ‌کدام از این دو فروشنده خطایشان را
 * به شکلِ {"status":404,"message":…,"data":{"error":"Invalid API
 * endpoint"}} نمی‌دهند. ۵سیم متنِ خام می‌دهد و نامبرلند
 * {"RESULT":…,"DESCRIPTION":…}.
 */
function numLooksIntercepted($body, $code) {
    if ($code !== 404 && $code !== 403 && $code !== 502) return false;
    $j = json_decode(trim((string)$body), true);
    if (!is_array($j)) return false;
    $blob = strtolower(json_encode($j));
    foreach (['invalid api endpoint', 'access denied', 'forbidden by', 'blocked'] as $sig)
        if (str_contains($blob, $sig)) return true;
    // شکلِ عمومیِ یک دروازه‌ی واسط
    return isset($j['status'], $j['message'], $j['data']) && !isset($j['RESULT']);
}

/** متنِ خطای فروشنده → جمله‌ی فارسی. آنچه نشناسیم خودش را می‌گوید. */
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
    if ($code === 401 || $code === 403) return 'کلیدِ ' . numProvName() . ' پذیرفته نشد — دوباره ثبتش کنید';
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
    $id = trim((string)($vars['id'] ?? ''));
    $nl = numProv() === 'numberland';

    switch ($op) {
        case 'buy':
            if ($nl) {
                // نامبرلند: کشور و سرویس با هم یک شناسه‌اند
                $sid = trim((string)($vars['sid'] ?? ''));
                if ($sid === '') return [null, 'شناسه‌ی شماره مشخص نیست'];
                return num5Get('/v2.php/?method=getnum&sid=' . rawurlencode($sid));
            }
            $co  = trim((string)($vars['country'] ?? ''));
            $opr = trim((string)($vars['operator'] ?? '')) ?: 'any';
            if ($co === '') return [null, 'کشور مشخص نیست'];
            $p = '/user/buy/activation/' . rawurlencode($co) . '/' . rawurlencode($opr) .
                 '/' . rawurlencode(NUM5_PRODUCT);
            $max = (float)numVal('api.max', 0);
            if ($max > 0) $p .= '?maxPrice=' . rawurlencode((string)$max);
            return num5Get($p);

        case 'status':
            if ($id === '') return [null, 'شناسه ندارد'];
            return $nl ? num5Get('/v2.php/?method=checkstatus&id=' . rawurlencode($id))
                       : num5Get('/user/check/' . rawurlencode($id));

        case 'cancel':
            if ($id === '') return [null, 'شناسه ندارد'];
            return $nl ? num5Get('/v2.php/?method=cancelnumber&id=' . rawurlencode($id))
                       : num5Get('/user/cancel/' . rawurlencode($id));

        case 'close':
            if ($id === '') return [null, 'شناسه ندارد'];
            return $nl ? num5Get('/v2.php/?method=closenumber&id=' . rawurlencode($id))
                       : num5Get('/user/finish/' . rawurlencode($id));

        case 'repeat':
            if ($id === '') return [null, 'شناسه ندارد'];
            // ۵سیم «کد مجدد» جدا ندارد؛ تا finish نزنیم پیامکِ بعدی خودش می‌آید
            return $nl ? num5Get('/v2.php/?method=repeat&id=' . rawurlencode($id))
                       : [[], ''];

        case 'ban':
            if ($id === '') return [null, 'شناسه ندارد'];
            return $nl ? num5Get('/v2.php/?method=bannumber&id=' . rawurlencode($id))
                       : num5Get('/user/ban/' . rawurlencode($id));

        case 'balance':
            return $nl ? num5Get('/v2.php/?method=getbalance')
                       : num5Get('/user/profile');
    }
    return [null, 'عملیات «' . $op . '» را نمی‌شناسم'];
}

// ------------------------------------------------------------
// 🔌 لایه‌ی یکسان‌کننده
// ------------------------------------------------------------
//
// بالاتر از اینجا، هیچ‌جای فایل نمی‌داند کدام فروشنده وصل است. هر
// عملیات یک شکلِ واحد برمی‌گرداند و تفاوتِ دو پنل همین‌جا تمام
// می‌شود — وگرنه هر «اگر نامبرلند بود» در ده جای چرخه‌ی سفارش
// تکرار می‌شد و یکی‌شان بالاخره جا می‌ماند.

/** خطای نامبرلند از داخلِ پاسخ — نامبرلند با کدِ ۲۰۰ هم «نه» می‌گوید */
function numNlErr($r) {
    if (!is_array($r)) return 'پاسخ نامعتبر';
    if (!isset($r['RESULT'])) return '';
    if ((string)$r['RESULT'] === '1') return '';
    $d = trim((string)($r['DESCRIPTION'] ?? ''));
    return $d !== '' ? $d : ('نامبرلند کدِ ' . $r['RESULT'] . ' داد');
}

/** 🛒 خرید → ['aid','phone','ttl','cost','err'] */
function numBuyDo(array $meta) {
    $nl = numProv() === 'numberland';
    [$r, $e] = numCall('buy', $nl
        ? ['sid' => $meta['sid'] ?? '']
        : ['country' => $meta['country'] ?? '', 'operator' => ($meta['operator'] ?? '') ?: 'any']);

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

/**
 * 📩 پیگیری → ['state','code','n','err']
 *   state: waiting | done | dead
 *   n: چندمین پیامک است (برای «کد مجدد»)
 */
function numCheckDo($aid, $seen = 0) {
    [$r, $e] = numCall('status', ['id' => $aid]);
    if (!is_array($r)) return ['state' => 'waiting', 'code' => '', 'n' => $seen, 'err' => $e];

    if (numProv() === 'numberland') {
        $st = (string)($r['RESULT'] ?? '');
        // ۱ منتظر · ۲ کد رسید · ۳ لغو · ۴ مسدود · ۵ منتظرِ کد مجدد · ۶ تمام
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

/** یک عملیاتِ ساده‌ی بله/نه → [موفق؟, خطا] */
function numOpDo($op, $aid) {
    [$r, $e] = numCall($op, ['id' => $aid]);
    if (!is_array($r)) return [false, $e ?: 'پاسخی نیامد'];
    if (numProv() === 'numberland' && ($x = numNlErr($r)) !== '') return [false, $x];
    return [true, ''];
}

/** 💰 موجودی → [عدد, واحد, خطا] */
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

/** «00:20:00» یا «1200» → ثانیه. ۰ یعنی نفهمیدم. */
function numParseTtl($v) {
    if (!is_scalar($v)) return 0;
    $v = trim((string)$v);
    if ($v === '') return 0;
    if (preg_match('/^\d+$/', $v)) return (int)$v;
    if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $v, $m))
        return (int)($m[1] ?? 0) * 3600 + (int)$m[2] * 60 + (int)$m[3];
    return 0;
}

/**
 * 🔬 یک تماسِ خام با ۵سیم — با همه‌ی جزئیاتی که برای عیب‌یابی لازم است.
 *
 * maHttp و num5Get فقط می‌گویند «نشد». برای پیدا کردنِ علت باید دانست
 * که کجا نشد: اسمِ دامنه پیدا نشد؟ اتصال برقرار نشد؟ TLS؟ یا سرور
 * جواب داد ولی جوابش ۴۰۱ بود؟ هرکدام راهِ حلِ کاملا متفاوتی دارند.
 *
 * برگشت: ['code'=>, 'err'=>, 'body'=>, 'dns'=>, 'conn'=>, 'total'=>]
 */
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
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
        CURLOPT_HTTPHEADER     => $hdr,
    ]);
    $body = curl_exec($ch);
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

/**
 * 🔎 شکلِ توکن.
 *
 * ۵سیم دو کلید می‌دهد و این تفاوت کسی را که اولین بار وصل می‌شود
 * گیج می‌کند: کلیدِ بالا (JWT، با eyJ شروع می‌شود) مالِ API نسخه‌ی
 * ۱ است — همانی که این ربات حرف می‌زند. کلیدِ پایین یک رشته‌ی ۳۲
 * حرفیِ هگزادسیمال است و مالِ API قدیمی است که ما اصلا صدایش
 * نمی‌زنیم. اگر آن یکی ثبت شود، ۵سیم درست و حسابی «۴۰۱» می‌دهد و
 * آدم فکر می‌کند کلیدش خراب است.
 *
 * برگشت: ['ok'=>, 'kind'=>, 'why'=>]
 */
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

    // 📅 JWT تاریخِ انقضا را داخل خودش دارد — بی‌آنکه لازم باشد جایی برویم
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

/** موجودی حسابِ فروشنده — [عدد, واحد, خطا] */
function numBalance() { return numBalanceDo(); }

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
        // نامبرلند کشور و سرویس را در یک شناسه می‌دهد؛ ۵سیم دوتا.
        // هر دو را برمی‌گردانیم و لایه‌ی خرید هرکدام را که لازم دارد برمی‌دارد.
        return ['operator' => $op, 'country' => $co, 'sid' => $sid,
                'prov' => (string)($i['prov'] ?? ''), 'item' => $i];
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
    if (!numReady())  return [false, 'اتصال به ' . numProvName() . ' تنظیم نشده است'];

    $meta = numItemMeta((string)($order['item_id'] ?? ''));
    if (!$meta) return [false, 'این شماره دیگر تعریف نشده است'];

    // ⚠️ ردیفی که مالِ فروشنده‌ی دیگری است، شناسه‌اش اینجا معنی ندارد.
    //    بدون این بررسی، خرید با یک خطای گنگِ سمتِ پنل رد می‌شد.
    if ($meta['prov'] !== '' && $meta['prov'] !== numProv())
        return [false, 'این ردیف مالِ فروشنده‌ی قبلی است — یک بار «وارد کردن» را بزنید'];

    if (numProv() === 'numberland') {
        if (trim((string)$meta['sid']) === '') return [false, 'شناسه‌ی این ردیف تنظیم نشده است'];
    } elseif ($meta['country'] === '') {
        return [false, 'کشور این ردیف تنظیم نشده است'];
    }

    // 🔒 ادعای اتمی: فقط یک اجرا شماره می‌خرد، حتی اگر دو درخواست هم‌زمان بیاید
    $claimed = false;
    mutate('num_acts', function (&$a) use ($oid, &$claimed) {
        if (isset($a[$oid])) return;
        $a[$oid] = ['order' => $oid, 'status' => 'buying', 'created' => time()];
        $claimed = true;
    });
    if (!$claimed) return [true, ''];

    $r = numBuyDo($meta);
    if (($r['err'] ?? '') !== '') {
        mutate('num_acts', function (&$a) use ($oid) { unset($a[$oid]); });   // تا بشود دوباره تلاش کرد
        return [false, $r['err']];
    }

    // ⏳ مهلتِ خودِ فروشنده دقیق‌تر از عددِ ماست
    $ttl = (int)($r['ttl'] ?? 0);

    numPut([
        'order'    => $oid,
        'uid'      => (int)($order['user_id'] ?? 0),
        'item'     => (string)($order['item_id'] ?? ''),
        'name'     => (string)($order['item_name'] ?? ''),
        'prov'     => numProv(),
        'operator' => (string)($r['operator'] ?? ''),
        'country'  => (string)($r['country'] ?? ''),
        'aid'      => (string)$r['aid'],
        'phone'    => numPhone((string)$r['phone']),
        'code'     => '',
        'status'   => 'waiting',
        'price'    => (float)($order['total'] ?? 0),
        'cost'     => (float)($r['cost'] ?? 0),   // آنچه به فروشنده دادیم
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

/** یک بار از فروشنده می‌پرسد پیامک آمده یا نه */
function numPoll($orderId) {
    $act = numGet($orderId);
    if (!$act || $act['status'] !== 'waiting') return;

    numSetAct($orderId, function (&$x) { $x['checked'] = time(); return true; });

    $r = numCheckDo((string)$act['aid'], (int)($act['sms_seen'] ?? 0));

    // ⛔️ مرده؟ پول همان‌جا برگردد، منتظرِ مهلت نمانیم.
    //    فروشنده خودش بسته، پس دوباره «لغو کن» گفتن بی‌معنی است.
    if ($r['state'] === 'dead') { numFinish($orderId, 'expired', false); return; }
    if ($r['state'] !== 'done' || $r['code'] === '') return;

    $code = $r['code'];
    $n    = (int)$r['n'];
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

    // ⚠️ قبلا شکست فقط در error_log می‌نشست و هیچ‌کس دوباره تلاش
    //    نمی‌کرد — شماره روی پنل باز می‌ماند و پولش می‌سوخت. حالا از
    //    همان مسیری می‌رود که لغو می‌رود: اگر نگرفت، صف‌بندی می‌شود و
    //    numRetryPanel با فاصله‌ی فزاینده دوباره امتحان می‌کند.
    numTellPanelCancel($orderId);
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

    // 🔁 نامبرلند برای کدِ مجدد یک عملیاتِ واقعی دارد و بدونِ صدا زدنش
    //    پیامکِ تازه‌ای نمی‌آید. ۵سیم چیزی لازم ندارد — تا نبسته‌ایم
    //    خودش می‌فرستد.
    if (numProv() === 'numberland') {
        [$ok, $e] = numOpDo('repeat', (string)$act['aid']);
        if (!$ok) {
            numSetAct($orderId, function (&$x) {
                $x['status'] = 'done'; $x['repeats'] = max(0, (int)($x['repeats'] ?? 1) - 1);
                return true;
            });
            return [false, $e];
        }
    }
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
                         $why === 'expired' ? 'کد شماره نرسید' : 'لغو شماره',
                         (string)($o['app'] ?? 'num'));
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
    if (!empty($act['panel_closed'])) return [true, ''];    // قبلا بسته شده

    $aid = (string)$act['aid'];

    // 1️⃣ اول بپرس پنل خودش این شماره را در چه حالی می‌بیند.
    //
    // ⚠️ همین یک پرسش، بزرگ‌ترین باگِ این بخش را می‌بندد. قبلا کورکورانه
    //    «لغو» فرستاده می‌شد و بعد «بستن». ولی هر دو فروشنده قاعده‌ی
    //    سفت‌وسختی دارند: لغو فقط روی شماره‌ی بی‌پیامک می‌گیرد و بستن
    //    فقط روی شماره‌ای که پیامک گرفته. یعنی نصفِ حالت‌ها اول یک
    //    درخواستِ محکوم‌به‌شکست می‌رفت، و اگر پنل همان لحظه سخت‌گیری
    //    می‌کرد (مثلا «تا دو دقیقه بعدِ خرید لغو نکن»)، هر دو رد می‌شدند
    //    و شماره روی پنل باز می‌ماند — یعنی پولِ رفته.
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

    // 2️⃣ عملیاتِ درست را اول امتحان کن، آن یکی را دومی.
    //    (اگر حالِ پنل معلوم نشد، ترتیبِ قدیمی: لغو بعد بستن)
    $ops  = ($state === 'sms') ? ['close', 'cancel'] : ['cancel', 'close'];
    $fail = '';
    foreach ($ops as $op) {
        [$ok, $e] = numOpDo($op, $aid);
        if ($ok) { $fail = ''; break; }
        if ($fail === '') $fail = $e ?: 'پاسخی نیامد';
    }

    // 3️⃣ هر دو رد شدند؟ شاید بینِ این دو درخواست، خودِ پنل بسته‌اش کرده
    //    (کد رسید و مهلت تمام شد). یک بار دیگر بپرس تا بی‌خود «باز» نماند.
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

/**
 * 🔎 پنل این شماره را در چه حالی می‌بیند؟
 *
 *   'open'   — هنوز پیامکی نیامده  → «لغو» می‌گیرد
 *   'sms'    — پیامک آمده           → «بستن» می‌گیرد
 *   'closed' — لغو/مسدود/تمام‌شده   → کاری لازم نیست
 *   ''       — معلوم نشد (شبکه یا پاسخِ ناشناخته)
 */
function numPanelState($aid) {
    $aid = trim((string)$aid);
    if ($aid === '') return '';

    [$r, ] = numCall('status', ['id' => $aid]);
    if (!is_array($r)) return '';

    if (numProv() === 'numberland') {
        // ⚠️ اینجا numNlErr به درد نمی‌خورد: در checkstatus خودِ RESULT
        //    «وضعیت» است نه «موفق/ناموفق»، پس RESULT=2 (کد رسید) را
        //    خطا می‌خواند. قاعده‌ی درست: منفی یعنی خطا، ۱ تا ۶ یعنی وضعیت.
        $st = $r['RESULT'] ?? null;
        if (!is_numeric($st)) return '';
        $st = (int)$st;

        if ($st < 0) {
            // شناسه‌ی ناشناخته یعنی روی پنل نیست — بسته حساب می‌شود،
            // وگرنه تا ابد برای چیزی که وجود ندارد دوباره تلاش می‌کنیم.
            $d = trim((string)($r['DESCRIPTION'] ?? ''));
            foreach (['پیدا نشد', 'یافت نشد', 'وجود ندارد', 'not found', 'invalid id'] as $needle)
                if (stripos($d, $needle) !== false) return 'closed';
            return '';
        }

        // ۱ منتظر · ۲ کد رسید · ۳ لغو · ۴ مسدود · ۵ منتظرِ کد مجدد · ۶ تمام
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

/**
 * ⏳ فاصله‌ی تلاشِ بعدی — فزاینده.
 *
 * ثابتِ ۶۰ ثانیه دو جور بد بود: برای «تا دو دقیقه بعدِ خرید لغو نکن»
 * زیادی زود، و برای قطعیِ طولانی زیادی مکرر. حالا اولین تلاش‌ها سریع‌اند
 * (شاید فقط یک لرزشِ لحظه‌ای بوده) و بعد فاصله باز می‌شود.
 */
function numPanelBackoff($tries) {
    static $steps = [15, 45, 120, 300, 600, 1800, 3600];
    $i = max(0, (int)$tries - 1);
    return $steps[min($i, count($steps) - 1)];
}

/**
 * 🔁 لغوهایی که به ۵سیم نرسیدند را دوباره امتحان کن.
 *
 * یک تلاشِ ناموفق ممکن است فقط قطعیِ لحظه‌ای باشد. ولی اگر چند بار هم
 * نگرفت، دیگر خودش خوب نمی‌شود و ادمین باید بداند — هر شماره‌ای که روی
 * ۵سیم باز بماند، پولِ رفته است.
 */
if (!defined('NUM_PANEL_TRIES')) define('NUM_PANEL_TRIES', 14);

function numRetryPanel($limit = 5) {
    $n = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        if (empty($act['panel_pending'])) continue;
        if (in_array(($act['status'] ?? ''), ['waiting', 'buying'], true)) continue;

        $tries = (int)($act['panel_tries'] ?? 0);
        if ($tries >= NUM_PANEL_TRIES) continue;         // بس است، دیگر خودش درست نمی‌شود
        // فاصله‌ی فزاینده: اول سریع، بعد آرام‌تر
        if (time() - (int)$act['panel_pending'] < numPanelBackoff($tries)) continue;

        $oid = (string)$act['order'];
        [$ok, $err] = numTellPanelCancel($oid);
        $n++;

        // درست سرِ تلاشِ آخر، یک بار خبر بده
        if (!$ok && $tries + 1 >= NUM_PANEL_TRIES && defined('ADMIN_ID') && ADMIN_ID) {
            sendMsg(BOT_TOKEN, ADMIN_ID,
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

/**
 * ⏰ کارِ پس‌زمینه — از همان تیک عمومی ربات صدا زده می‌شود.
 *
 * سه کار:
 *   ۱. لغوهایی که به ۵سیم نرسیدند
 *   ۲. شماره‌هایی که مهلتشان تمام شد → پول برگردد
 *   ۳. سفارش‌هایی که کدشان رسید و مهلتشان تمام شد → روی ۵سیم بسته شوند
 */
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
    return numProv() === 'numberland' ? numCatalogNl() : numCatalog5();
}

/** 🌍 ۵سیم — {کشور: {telegram: {اپراتور: {cost,count,rate}}}} */
function numCatalog5() {
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

    usort($out, fn($x, $y) => [$x['rank'], $x['cname'], $x['usd']]
                          <=> [$y['rank'], $y['cname'], $y['usd']]);
    return [$countries, $out, ''];
}

/**
 * 🇮🇷 نامبرلند — فهرستِ صافِ ردیف‌ها.
 *
 * getinfo هر ترکیبِ «کشور × سرویس × اپراتور» را یک ردیف با شناسه‌ی
 * خودش می‌دهد. قیمت‌ها تومانی‌اند، پس هیچ تبدیلی لازم نیست.
 */
function numCatalogNl() {
    $svc = trim((string)numVal('api.nl_svc', '1')) ?: '1';

    [$rows, $err] = num5Get('/v2.php/?method=getinfo&operator=any&service=' . rawurlencode($svc), 30);
    if (!is_array($rows)) return [[], [], $err ?: 'فهرست شماره‌ها نیامد'];
    if (!array_is_list($rows)) {
        $d = trim((string)($rows['DESCRIPTION'] ?? ''));
        return [[], [], $d !== '' ? $d : 'پاسخ نامبرلند فهرست نبود'];
    }

    // نامِ کشورها
    $fa = $en = [];
    [$ct] = num5Get('/v2.php/?method=getcountry', 30);
    foreach ((array)$ct as $x) {
        if (!is_array($x) || !isset($x['id'])) continue;
        $fa[(string)$x['id']] = trim((string)($x['name'] ?? $x['name_en'] ?? $x['id']));
        $en[(string)$x['id']] = trim((string)($x['name_en'] ?? ''));
    }

    $countries = $out = [];
    foreach ($rows as $r) {
        if (!is_array($r) || !isset($r['id'])) continue;
        $cc = (string)($r['country'] ?? '');
        $ss = (string)($r['service'] ?? '');
        if ($cc === '' || $ss !== $svc) continue;

        $cName = $fa[$cc] ?? ('کشور ' . $cc);
        $flag  = numFlagFa($en[$cc] ?? '', $cName);
        $countries[$cc] = ['name' => $cName, 'flag' => $flag];

        $op    = trim((string)($r['operator'] ?? ''));
        $price = (float)($r['amount'] ?? 0);
        $cnt   = (int)($r['count'] ?? 0);
        $out[] = [
            'sid'      => (string)$r['id'],
            'country'  => $cc,
            'operator' => $op,
            'flag'     => $flag,
            'cname'    => $cName,
            'rank'     => numRank($en[$cc] ?? ''),
            'name'     => ($op !== '' && $op !== '0') ? 'اپراتور ' . $op : 'شماره',
            'usd'      => 0.0,
            'price'    => $price,                 // خودش تومان است
            'count'    => $cnt,
            'rate'     => 0.0,
            'ttl'      => numParseTtl($r['time'] ?? ''),
            'on'       => (string)($r['active'] ?? '1') !== '0' && $cnt > 0,
        ];
    }

    if (!$out) return [[], [], 'هیچ شماره‌ای در پاسخِ نامبرلند نبود'];

    usort($out, fn($x, $y) => [$x['rank'], $x['cname'], $x['price']]
                          <=> [$y['rank'], $y['cname'], $y['price']]);
    return [$countries, $out, ''];
}

/**
 * 🚩 پرچم از روی نام — برای نامبرلند که ISO نمی‌دهد.
 *
 * نامبرلند برای هر کشور فقط یک اسم می‌دهد؛ گاهی انگلیسی، گاهی فارسی،
 * گاهی با پسوند و پرانتز («Korea, South»، «کره جنوبی»، «UK (England)»).
 * جدولِ قبلی فقط انگلیسیِ تمیز را می‌شناخت، برای همین بیشترِ ردیف‌ها
 * 🌍 می‌شدند. حالا سه راه امتحان می‌شود و هر کدام جواب داد بس است:
 *
 *   ۱. خودِ متن اگر کدِ دو حرفیِ ISO باشد
 *   ۲. جدولِ انگلیسی (با نام‌های جایگزین)
 *   ۳. جدولِ فارسی
 *
 * و اگر هیچ‌کدام نشد، آخرین تیر: نامِ فارسیِ کشور را از numCountryFa
 * می‌گیریم و ببینیم همان توی جدولِ فارسی هست یا نه.
 */
function numFlagFa($name, $alt = '') {
    foreach ([$name, $alt] as $cand) {
        $f = numFlagOne($cand);
        if ($f !== '🌍') return $f;
    }
    return '🌍';
}

/** یک اسم را به پرچم برساند، یا 🌍 بدهد. */
function numFlagOne($name) {
    $raw = trim((string)$name);
    if ($raw === '') return '🌍';

    // خودِ متن پرچم است؟ (بعضی منبع‌ها پرچم را قاطیِ اسم می‌دهند)
    if (preg_match('/[\x{1F1E6}-\x{1F1FF}]{2}/u', $raw, $m)) return $m[0];

    // «Korea, South» → «South Korea» ، «UK (England)» → «UK England»
    $s = str_replace(['(', ')', '-', '_', '.', '،'], ' ', $raw);
    if (strpos($s, ',') !== false) {
        $parts = array_map('trim', explode(',', $s));
        $s = implode(' ', array_reverse($parts));
    }

    $k = numFlagKey($s);
    if ($k === '') return '🌍';

    $map = numFlagMap();
    if (isset($map[$k])) return numFlagIso($map[$k]);

    // کدِ دو حرفیِ خالص
    if (strlen($k) === 2 && ctype_alpha($k)) {
        $f = numFlagIso($k);
        if ($f !== '🌍') return $f;
    }

    // واژه‌های زائد را بریز دور و دوباره امتحان کن
    $trim = preg_replace('/^(the|republicof|kingdomof|stateof|unitedstateof|کشور)/', '', $k);
    if ($trim !== $k && isset($map[$trim])) return numFlagIso($map[$trim]);

    // «UK (England)» یا «Telegram Indonesia» — اسمِ کشور یکی از واژه‌هاست.
    // از بلندترین ترکیب به کوتاه‌ترین می‌گردیم تا «South Korea» زودتر از
    // «Korea» و «North Macedonia» زودتر از «Macedonia» گیر بیفتد.
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

/** کلیدِ یکدست: حروفِ لاتین کوچک یا حروفِ فارسی، بی‌فاصله. */
function numFlagKey($s) {
    $s = mb_strtolower(trim((string)$s), 'UTF-8');
    // یکدست‌سازیِ عربی/فارسی
    $s = strtr($s, ['ي'=>'ی','ك'=>'ک','ۀ'=>'ه','ة'=>'ه','أ'=>'ا','إ'=>'ا','آ'=>'ا','ؤ'=>'و','ئ'=>'ی','\u{200c}'=>'']);
    return preg_replace('/[^a-z\x{0600}-\x{06FF}]/u', '', $s);
}

/** جدولِ نام → ISO. هم انگلیسی هم فارسی، در یک جا. */
function numFlagMap() {
    static $map = null;
    if ($map !== null) return $map;

    // انگلیسی (با نام‌های جایگزینِ رایج در پنل‌های شماره)
    $en = [
        'RU'=>['russia','russianfederation','rusia'],
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

    // فارسی — همان کشورها، به اسمی که کاربر می‌بیند
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
            if (isset($byCode[$code])) {
                // 🩹 پوشه‌ای که بارِ اول پرچمش پیدا نشده بود و 🌍 خورده،
                //    حالا که جدولِ نام‌ها کامل‌تر است پرچمِ درستش را می‌گیرد.
                //    ایموجیِ دلخواهِ ادمین دست نمی‌خورد؛ فقط 🌍 و خالی.
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
                $a['items'][$k]['prov'] = numProv();
                // همان وصله‌ی پرچم، این بار روی خودِ ردیف
                $ne = trim((string)($a['items'][$k]['emoji'] ?? ''));
                if (($ne === '' || $ne === '🌍' || $ne === '☎️') && (string)($r['flag'] ?? '🌍') !== '🌍')
                    $a['items'][$k]['emoji'] = (string)$r['flag'];
                if ($syncPrice) $a['items'][$k]['price'] = numRound100($r['price'] * $mul);
                // ترتیبِ نمایش هم از ۵سیم می‌آید — وگرنه کشورِ پرفروشی که
                // بار اول آخر افتاده، برای همیشه آخر می‌ماند
                $a['items'][$k]['order'] = $iOrder;
                if ($was && !$r['on']) $off++; else $upd++;
                continue;
            }

            $a['items'][] = [
                'id' => 'i' . bin2hex(random_bytes(3)), 'cat' => $catId, 'svc' => $r['sid'],
                'prov' => numProv(),
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
            $pv  = trim((string)($it['prov'] ?? ''));
            // ردیفِ دستیِ ادمین، یا ردیفی که مالِ فروشنده‌ی فعلی است، می‌ماند.
            // ردیفِ بی‌نشان را از روی شکلِ شناسه‌اش حدس می‌زنیم: «کشور|اپراتور»
            // مالِ ۵سیم است و عددِ تنها مالِ نامبرلند.
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

    $tok  = numKey();
    $rate = numRate();
    $mk   = (float)numVal('markup', 0);
    $pi   = numProvInfo();

    $t  = "☎️ <b>شماره مجازی تلگرام</b>\n\n";
    $t .= "🏪 فروشنده: <b>" . h($pi['label']) . "</b>\n";
    $t .= "وضعیت: " . (!empty($api['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    // 🔒 هیچ تکه‌ای از توکن نشان داده نمی‌شود — نه اولش، نه آخرش.
    //    عکسِ صفحه‌ی پنل زیاد دست‌به‌دست می‌شود.
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

    // 📊 یک نگاه به کاتالوگ، تا معلوم باشد چیزی برای فروش هست یا نه
    if (function_exists('maGet')) {
        $a  = maGet('num');
        $nc = count(array_filter((array)($a['cats'] ?? []),  fn($x) => !empty($x['on'])));
        $ni = count(array_filter((array)($a['items'] ?? []), fn($x) => !empty($x['on'])));
        $t .= "\n🌍 کشور: <b>{$nc}</b> · ☎️ شماره: <b>{$ni}</b>\n";
        $t .= "مینی‌اپ: " . (!empty($a['on']) ? '✅ باز' : '❌ بسته') . "\n";
        if ($ni === 0) $t .= "⚠️ هنوز چیزی برای فروش نیست — «📥 وارد کردن» را بزنید.\n";
    }

    if (numNeedsRate() && $rate <= 0)
        $t .= "\n⚠️ بدون نرخ تبدیل، قیمتی وارد نمی‌شود. یا «💵 نرخ دلار» را دستی بگذارید، " .
              "یا بخش قیمت‌گیری را روشن کنید.\n";
    if ($tok === '')
        $t .= "\n⚠️ تا " . h($pi['key']) . " ثبت نشود هیچ خریدی انجام نمی‌شود.\n";
    if ($stuck) $t .= "\n⚠️ <b>{$stuck}</b> سفارش روی فروشنده بسته نشده — «📋 شماره‌های باز» را ببینید.";
    if ($open)  $t .= "\n⏳ <b>{$open}</b> شماره‌ی باز، در انتظار کد.";

    $rows = [
        [btnCb(!empty($api['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'numtog', 'info')],
        [btnCb('🏪 فروشنده: ' . $pi['name'], 'numprov', 'confirm')],
        [btnCb('🔑 ' . $pi['key'] . ' ' . $pi['name'], 'nums_token', 'admin')],
        [btnCb('📥 وارد کردن کشورها و قیمت‌ها', 'numimp', 'buy')],
        [btnCb('📈 درصد سود', 'nums_markup', 'admin'),
         btnCb('💵 نرخ دلار', 'nums_rate', 'admin')],
        [btnCb(!empty(numVal('sync_price', true)) ? '💰 قیمت از ۵سیم: روشن' : '💰 قیمت از ۵سیم: خاموش',
               'numsync', 'info')],
        [btnCb('🩺 عیب‌یابی اتصال', 'numdiag', 'confirm')],
        [btnCb('💰 موجودی حساب ' . $pi['name'], 'numtest', 'confirm')],
        [btnCb('⏳ مهلت انتظار کد', 'nums_wait', 'admin'),
         btnCb('🔁 فاصله‌ی پیگیری', 'nums_poll', 'admin')],
        [btnCb('⏱ مهلت تماس', 'nums_timeout', 'admin'),
         btnCb('🧢 سقف قیمت خرید', 'nums_max', 'admin')],
        [btnCb('🌍 تعداد کشورِ صفحه‌ی اول', 'nums_cats', 'admin')],
        // ⚠️ array_merge نه «+»: جمعِ آرایه کلیدهای تکراری را دور می‌ریزد
        //    و دکمه‌ی دوم بی‌صدا گم می‌شد.
        array_merge([btnCb('🌐 آدرس پایه', 'nums_base', 'admin')],
                    numProv() === 'numberland'
                        ? [btnCb('🎯 کد سرویس تلگرام', 'nums_nl_svc', 'admin')] : []),
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
        sendMsg(BOT_TOKEN, $chatId,
            "❌ <b>نگرفت</b>\n<code>" . h(mb_substr($err, 0, 300)) . "</code>\n\n" .
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

/**
 * 🩺 عیب‌یابیِ اتصال — قدم به قدم.
 *
 * «کار نمی‌کند» چهار علتِ کاملا متفاوت دارد و هرکدام راهِ حلِ خودش
 * را: توکنِ اشتباه، سروری که به ۵سیم نمی‌رسد، نرخِ تبدیلِ نداشته، و
 * بخشی که روشن نشده. این صفحه هر چهار را جدا امتحان می‌کند و سرِ
 * اولین شکست می‌ایستد و می‌گوید دقیقا چه کار کنید.
 */
function numAdmDiag($chatId, $msgId = 0) {
    $pi = numProvInfo();
    $t = "🩺 <b>عیب‌یابی اتصال — " . h($pi['name']) . "</b>\n\n";
    $rows = [];
    $stop = false;

    // ── ۱) شکلِ کلید ──
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
        $rows[] = [btnCb('🔑 توکن ۵سیم', 'nums_token', 'admin')];
        $stop = true;
    }

    // ── ۲) آیا سرور اصلا به ۵سیم می‌رسد؟ ──
    if (!$stop) {
        $t .= "\n<b>۲. رسیدن به " . h($pi['name']) . "</b>\n";
        $probePath = numProv() === 'numberland' ? '/v2.php/?method=getcountry' : '/guest/countries';
        $p = num5Probe($probePath, numProv() === 'numberland', 12);
        if ($p['errno'] !== 0) {
            $t .= "❌ <code>" . h(mb_substr($p['err'], 0, 120)) . "</code>\n";
            // 🎯 هر خطای curl راهِ حلِ خودش را دارد
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
            $rows[] = [btnCb('🏪 عوض کردن فروشنده', 'numprov', 'confirm')];
            $stop = true;
        } elseif (numLooksIntercepted($p['body'], $p['code'])) {
            // 🕵️ جواب آمد، ولی از فروشنده نیامد
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
            $rows[] = [btnCb('🏪 عوض کردن فروشنده', 'numprov', 'confirm')];
            $stop = true;
        } else {
            $t .= "✅ رسید · " . ($p['ip'] !== '' ? '<code>' . h($p['ip']) . '</code> · ' : '') .
                  "کد <b>" . $p['code'] . "</b> · " . number_format($p['total'], 2) . " ثانیه\n";
            if ($p['total'] > 4) $t .= "⚠️ کند است — ممکن است سرِ خرید هم دیر جواب بدهد.\n";
        }
    }

    // ── ۳) خودِ توکن را قبول می‌کند؟ ──
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
            $rows[] = [btnCb('🔑 کلید را دوباره بفرست', 'nums_token', 'admin')];
            $stop = true;
        }
    }

    // ── ۴) نرخ تبدیل ──
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
                  "یا «💵 نرخ دلار» را دستی بگذارید، یا بخش قیمت‌گیری را روشن کنید.\n";
            $rows[] = [btnCb('💵 نرخ دلار', 'nums_rate', 'admin'),
                       btnCb('💹 قیمت‌گیری', 'px_home', 'nav')];
            $stop = true;
        }
    }

    // ── ۵) کاتالوگ تلگرام ──
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

    // ── ۶) بخش روشن است؟ ──
    if (!$stop) {
        $t .= "\n<b>۶. وضعیت بخش</b>\n";
        if (!empty(numVal('api.on'))) {
            $t .= "✅ روشن\n";
        } else {
            $t .= "❌ خاموش — همه‌چیز سالم است ولی فروش انجام نمی‌شود.\n";
            $rows[] = [btnCb('✅ روشنش کن', 'numtog', 'info')];
        }
        $a  = function_exists('maGet') ? maGet('num') : [];
        $ni = count(array_filter((array)($a['items'] ?? []), fn($x) => !empty($x['on'])));
        if ($ni === 0) {
            $t .= "⚠️ هنوز چیزی وارد نشده — «📥 وارد کردن» را بزنید.\n";
            $rows[] = [btnCb('📥 وارد کردن', 'numimp', 'buy')];
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
    if ($data === 'numdiag')   { $ack('🩺 در حال بررسی…'); numAdmDiag($chatId, $msgId); return true; }
    if ($data === 'numopen')   { $ack(); numAdmOpen($chatId, $msgId); return true; }
    if ($data === 'numclean')  { $ack(); numAdmClean($chatId, $msgId); return true; }
    if ($data === 'numclean1') { $ack('⏳'); numAdmCleanKeep($chatId, $msgId); return true; }
    if ($data === 'numclean2') { $ack('⏳'); numAdmCleanAll($chatId, $msgId); return true; }

    // 🏪 انتخاب فروشنده
    if ($data === 'numprov') {
        $ack();
        $t = "🏪 <b>فروشنده‌ی شماره</b>\n\nالان: <b>" . h(numProvInfo()['label']) . "</b>\n\n";
        $t .= "🌍 <b>۵سیم</b> — ارزان‌تر و بزرگ‌تر، ولی از داخل ایران اغلب در دسترس نیست: " .
              "درخواست به مقصد نمی‌رسد و یک واسط جوابش را می‌دهد.\n\n";
        $t .= "🇮🇷 <b>نامبرلند</b> — ایرانی، پس می‌رسد. قیمت‌ها تومانی‌اند و نرخ تبدیل لازم ندارد.\n\n";
        $t .= "<i>بعد از عوض کردن، یک بار «📥 وارد کردن» را بزنید — شناسه‌ی محصول‌ها بین دو " .
              "فروشنده فرق دارد و ردیف‌های قبلی دیگر قابل خرید نیستند.</i>";
        $rows = [];
        foreach (numProviders() as $k => $pv)
            $rows[] = [btnCb(($k === numProv() ? '✅ ' : '⚪️ ') . $pv['label'], 'numprov_' . $k,
                             $k === numProv() ? 'confirm' : 'admin')];
        $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];
        editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
        return true;
    }
    if (str_starts_with($data, 'numprov_')) {
        $k = substr($data, 8);
        if (!isset(numProviders()[$k])) { $ack(); return true; }
        numSet(function (&$c) use ($k) { $c['provider'] = $k; });
        $ack('✅ ' . numProvName());
        numAdmHome($chatId, $msgId);
        return true;
    }

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
        $pi  = numProvInfo();
        $ask = [
            'token'   => ["🔑 <b>" . h($pi['key']) . ' ' . h($pi['name']) . "</b>\n\n" .
                          h($pi['help']) . "\n\n<i>پیامتان بلافاصله پاک می‌شود.</i>", 'num_token'],
            'nl_svc'  => ["🎯 <b>کد سرویس تلگرام نزد نامبرلند</b>\n\nمعمولا <code>1</code> است.", 'num_nl_svc'],
            'base'    => ["🌐 <b>آدرس پایه</b>\n\nخالی بگذارید تا آدرسِ رسمیِ فروشنده استفاده شود.\n" .
                          "فقط وقتی لازم است که سرور مستقیم نمی‌رسد و باید از یک واسطه رد شود.\n\n" .
                          "<code>-</code> یعنی برگرد به پیش‌فرض.", 'num_base'],
            'markup'  => ["📈 <b>درصد سود</b>\n\nچند درصد روی قیمتِ ۵سیم کشیده شود؟ مثلا <code>25</code>", 'num_markup'],
            'rate'    => ["💵 <b>نرخ دلار</b>\n\nهر ۱ دلار چند تومان؟ عدد بفرستید.\n\n<code>-</code> بفرستید تا خودکار از بخش قیمت‌گیری بگیرد.", 'num_rate'],
            'wait'    => ["⏳ <b>مهلت انتظار کد</b> (ثانیه)\n\nمثلا <code>900</code>", 'num_wait'],
            'poll'    => ["🔁 <b>فاصله‌ی پیگیری</b> (ثانیه)\n\nمثلا <code>6</code>", 'num_poll'],
            'timeout' => ["⏱ <b>مهلت تماس با ۵سیم</b> (ثانیه)\n\nمثلا <code>15</code>", 'num_timeout'],
            'max'     => ["🧢 <b>سقف قیمت خرید</b> (دلار)\n\nاگر قیمتِ لحظه‌ای از این بیشتر شد، خرید انجام نشود.\n\n<code>-</code> یعنی بی‌سقف.", 'num_max'],
            'cats'    => ["🌍 <b>چند کشور روی صفحه‌ی اولِ مینی‌اپ؟</b>\n\n" .
                          "فروشنده بالای صد کشور دارد؛ ریختنِ همه روی صفحه فقط اسکرول است.\n" .
                          "پیشنهاد: <code>24</code>\n\n" .
                          "بقیه‌ی کشورها گم نمی‌شوند — با جستجو پیدا می‌شوند.\n" .
                          "<code>-</code> یعنی همه را نشان بده.", 'num_cats'],
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
            // 🔒 توکن در تاریخچه‌ی گفتگو نمی‌ماند — چه قبولش کنیم چه نه
            $v = $blank ? '' : preg_replace('/\s+/', '', $plain);
            if (!empty($msg['message_id'])) delMsg(BOT_TOKEN, $chatId, (int)$msg['message_id']);

            // 🚦 کلیدِ اشتباه را همین‌جا بگیر، نه سرِ اولین خرید.
            //
            //    ۵سیم دو کلید می‌دهد و کلیدِ پایینی (API قدیمی) هم شکلِ
            //    یک کلیدِ درست را دارد — ۳۲ حرف. اگر بی‌صدا ذخیره‌اش
            //    کنیم، همه‌چیز ۴۰۱ می‌گیرد و آدم فکر می‌کند کلیدش
            //    خراب است، در حالی که فقط اشتباهی را کپی کرده.
            // نامبرلند کلیدِ هگزِ ۳۲ حرفی می‌دهد؛ ۵سیم JWT. شکل را
            // بر اساس همان فروشنده‌ای می‌سنجیم که الان انتخاب است.
            if ($v !== '' && numProv() === '5sim') {
                $sh = numTokenShape($v);
                if (!$sh['ok']) {
                    clearState($uid);
                    sendMsg(BOT_TOKEN, $chatId,
                        "⚠️ <b>این کلید به درد نمی‌خورد</b>\n\n" . $sh['why'],
                        inlineKb([[btnCb('🔑 دوباره بفرست', 'nums_token', 'admin')],
                                  [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
                    return true;
                }
            }

            if ($v !== '' && numProv() === 'numberland' && strlen($v) < 16) {
                clearState($uid);
                sendMsg(BOT_TOKEN, $chatId,
                    "⚠️ کلیدِ نامبرلند خیلی کوتاه است. کلِ کلید را بفرستید.",
                    inlineKb([[btnCb('🔑 دوباره بفرست', 'nums_token', 'admin')],
                              [btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
                return true;
            }

            $fld = numProv() === 'numberland' ? 'nl_key' : 'token';
            numSet(function (&$c) use ($v, $fld) { $c['api'][$fld] = $v; });
            $done($v === '' ? '✅ کلید پاک شد.' : "✅ کلید ثبت شد و پیامتان پاک شد.",
                  inlineKb([[btnCb('🩺 عیب‌یابی اتصال', 'numdiag', 'confirm')],
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

        case 'num_cats':
            $v = $blank ? 0 : max(0, (int)preg_replace('/[^0-9]/', '', numDigits($plain)));
            maSet('num', function (&$a) use ($v) { $a['cats_max'] = $v; });
            $done($v > 0
                ? '✅ صفحه‌ی اول: <b>' . fmtNum($v) . '</b> کشور. بقیه با جستجو.'
                : '✅ همه‌ی کشورها روی صفحه‌ی اول می‌آیند.');
            return true;

        case 'num_nl_svc':
            $v = preg_replace('/[^0-9]/', '', numDigits($plain)) ?: '1';
            numSet(function (&$c) use ($v) { $c['api']['nl_svc'] = $v; });
            $done('✅ کد سرویس: <code>' . h($v) . '</code>');
            return true;

        case 'num_base':
            $v = $blank ? '' : rtrim($plain, '/');
            if ($v !== '' && !preg_match('#^https?://[^\s]+$#i', $v)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس باید با <code>http://</code> یا <code>https://</code> شروع شود.", $back);
                return true;
            }
            numSet(function (&$c) use ($v) { $c['api']['base'] = $v; });
            $done($v === '' ? '✅ آدرسِ رسمیِ فروشنده استفاده می‌شود: <code>' . h(numBase()) . '</code>'
                            : '✅ آدرس: <code>' . h($v) . '</code>');
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

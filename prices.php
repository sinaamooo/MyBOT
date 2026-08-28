<?php
/**
 * 💹 موتور قیمت لحظه‌ای — ارز دیجیتال، پریمیوم و استارز فرگمنت
 *
 * یک منبع قیمت برای کل ربات: هم پیام‌های داخل گروه، هم نرخ ارزِ مینی‌اپ‌ها.
 * تا حالا هر جا نرخ خودش را از جای دیگری می‌گرفت و همین باعث می‌شد قیمتی که
 * در گروه نشان داده می‌شود با قیمتی که در مینی‌اپ فروخته می‌شود یکی نباشد.
 *
 * قیمت پریمیوم و استارز حدس زده نمی‌شود: قیمت دلاری‌شان روی فرگمنت ثابت است
 * (۳ ماهه ۱۱٫۹۹ دلار، هر استارز ۰٫۰۱۵ دلار) و فقط نرخ TON و دلار لحظه‌ای است.
 * پس عددی که بیرون می‌آید همان چیزی است که خریدار روی فرگمنت می‌بیند.
 *
 * همه‌ی متن‌ها، ایموجی‌های پریمیوم و دکمه‌ها از پنل قابل ویرایش‌اند.
 */

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function pxDefaults() {
    return [
        'on'  => true,

        // 🏦 منبع اول: والکس — ایرانی، بی‌کلید، و تومان را مستقیم می‌دهد.
        //
        // منبع قبلی از بیرونِ ایران جواب می‌داد و روی هر تازه‌سازی چند
        // ثانیه پشتِ تایم‌اوت می‌ماند؛ همان «گیر کردنِ» قیمت‌گیری. والکس
        // از داخل نزدیک است و همان یک درخواست هم USDT/IRT می‌دهد هم
        // جفت‌های دلاری. هر دو منبع حالا هم‌زمان (نه پشت‌سرهم) گرفته
        // می‌شوند و والکس اولویت دارد؛ منبع دوم فقط جاهای خالی را پر
        // می‌کند. اگر یکی از دسترس خارج شد، آن یکی تنهایی کافی است.
        'wx_on'  => 1,
        'wx_url' => 'https://api.wallex.ir/v1/markets',

        // منبع قیمت
        'api' => 'https://swapwallet.app/api/v1/market/prices',
        'key' => 'apikey-h8T5ufE73fILlDudXnPJp6CRYV9PSMKviBB0SxCXCAOzSFneGcBHaUa19am2kTIU',
        // ⏱ عمر کشِ قیمت.
        //
        // ۱۵ ثانیه بود و همین کندش می‌کرد: کارتِ قیمت با خودِ عدد کلید
        // می‌خورد، پس هر بار که عدد تکان می‌خورد کارت از نو ساخته و از
        // نو آپلود می‌شد — نزدیک یک ثانیه برای هر نفر. با یک دقیقه، در
        // هر دقیقه فقط نفر اول هزینه می‌دهد و بقیه همان تصویر را با
        // شناسه‌ی فایل می‌گیرند. قیمت تومانی هم در یک دقیقه کهنه نمی‌شود.
        'ttl' => 60,

        // 📡 منبع دوم: طلا، سکه و پول کشورها (API اصلی فقط ارز دیجیتال دارد)
        // چند آدرس، با خط جدا. هرکدام جواب داد، همان استفاده می‌شود — پس
        // اگر یکی از دسترس خارج شد، بقیه سرِ پا نگهش می‌دارند.
        'alt_url' => "https://call1.tgju.org/ajax.json\nhttps://call3.tgju.org/ajax.json\nhttps://call.tgju.org/ajax.json",
        'alt_ttl' => 300,
        'timeout' => 6,
        'cooldown' => 120,    // بعد از شکست، این مدت سراغ شبکه نرو

        // 🌍 نرخ برابری پول کشورها نسبت به دلار — رایگان و بی‌کلید
        'fx_url' => "https://open.er-api.com/v6/latest/USD\nhttps://api.exchangerate-api.com/v4/latest/USD\nhttps://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@latest/v1/currencies/usd.json",
        'fx_ttl' => 3600,

        // 🧮 وقتی هیچ منبع ایرانی جواب نداد، قیمت طلا و ارز از روی همین
        // API ارز دیجیتال ساخته می‌شود. PAXG و XAUT هرکدام دقیقا یک انس
        // طلای واقعی‌اند، پس انس از آن‌ها درمی‌آید و بقیه از انس و دلار.
        'derive' => 1,
        'gold_k' => 1.0,      // ضریب تنظیم گرم طلا (اگر با بازار چند درصد فرق داشت)
        'coin_k' => 1.12,     // حباب سکه — نسبت قیمت سکه به ارزش طلای داخلش

        // ایموجی پریمیوم — با /emoji در ربات کدشان را می‌گیرید
        'emoji' => [
            // ── جاهایی که خودتان مشخص کردید ──
            'date'   => '5413879192267805083',   // 🕓 جلوی تاریخ
            'gold'   => '5949707595445968258',   // 🥇 سرِ قالب طلا
            'usd'    => '5951773156887764244',   // 💵 سرِ دلار و جلوی قیمت دلاری
            'toman'  => '5965097893491642896',   // 💰 جلوی قیمت تومانی
            'chg'    => '6050900104431278847',   // 📈 جلوی درصد تغییرات
            'conv'   => '4931934645626341248',   // 💱 سرِ قالب تبدیل

            'frag'  => '4902715076873553054',   // 🔻 چسبیده به «Fragment» پایین قالب
            'card'  => '5343902037438391058',
            'price' => '5841359408952513916',
            'prem'  => '5899945812296731931',
            'star'  => '4936468614967460670',
            'ton'   => '5899945812296731931',   // 💎 جلوی قیمت تونی
            'coin'  => '5271878966347601947',
            'chart' => '5902331842723319210',
        ],

        // دکمه‌های زیر هر پیام قیمت — متن، لینک و رنگ قابل ویرایش
        'buttons' => [
            ['on' => 1, 'text' => '💎 ربات خدمات مجازی', 'url' => '', 'color' => 'success', 'icon' => ''],
            ['on' => 1, 'text' => '➕ افزودن به گروه',    'url' => '', 'color' => 'primary', 'icon' => ''],
        ],

        // قیمت دلاری فرگمنت — ثابت است، فقط اگر فرگمنت عوضش کرد اینجا را عوض کنید
        'premium_usd' => ['3' => 11.99, '6' => 15.99, '12' => 28.99],
        'premium_off' => ['3' => 20, '6' => 47, '12' => 52],   // درصد تخفیف روی جلد
        'star_usd'    => 0.015,
        'star_packs'  => [50, 100, 150, 250, 500, 1000, 2500],

        // درصدی که روی نرخ خام بازار سوار می‌شود (۰ = دقیقا نرخ بازار)
        'margin' => 0,

        // ارزهایی که در «نرخ ارز» فهرست می‌شوند
        'coins' => ['BTC', 'ETH', 'TON', 'SOL', 'BNB', 'XRP', 'DOGE', 'NOT', 'TRX', 'ADA', 'LINK', 'AVAX'],

        // کلماتی که پیام را می‌سازند (با ویرگول جدا)
        'words' => [
            'premium' => 'پریمیوم,پرمیوم,تلگرام پریمیوم,قیمت پریمیوم,اشتراک',
            'stars'   => 'استارز,ستاره,قیمت استارز,stars',
            'rates'   => 'نرخ ارز,قیمت ارز,ارزها,کریپتو,بازار',
        ],

        // 🖼 کارت گرافیکی ارز
        'card' => [
            'on' => 1,
            'w'  => 1080,
            'h'  => 620,
            // مسیر فونت — خالی یعنی خودش بگردد و پیدا کند
            'font'      => '',
            'font_bold' => '',
        ],

        // ✏️ متن‌ها — همه قابل ویرایش
        'texts' => [
            'prem_head'  => 'Telegram Premium',
            'prem_month' => '{n} months',
            'prem_off'   => '{off}% Sale',
            'star_head'  => '{n} STARS :',
            'rates_head' => 'بازار جهانی',
            'coin_head'  => '{n} {sym} :',
            'hl_head'    => 'High & Low',
            'foot'       => 'Fragment',
            'toman'      => 'toman',
            'dollar'     => 'dollar',
            'ton'        => 'ton',
            'down'       => 'موتور قیمت الان جواب نمی‌دهد. چند لحظه بعد دوباره امتحان کنید.',
            'nocoin'     => 'این نماد در بازار پیدا نشد.',

            // 📝 قالب کاملِ پیام — خالی یعنی «خودت بساز».
            // پرش کنید و کل پیام دقیقا همان می‌شود که نوشته‌اید.
            'prem_full'  => '',
            'star_full'  => '',
        ],
    ];
}

function pxCfg() {
    $c = cfg()['prices'] ?? null;
    if (!is_array($c)) return pxDefaults();
    $out = array_replace_recursive(pxDefaults(), $c);
    // فهرست‌ها باید عینا همان چیزی باشند که ادمین ذخیره کرده — نه ادغام عمقی،
    // وگرنه حذف یک ردیف هیچ‌وقت اثر نمی‌کند.
    foreach (['buttons', 'coins', 'star_packs'] as $k) {
        if (isset($c[$k]) && is_array($c[$k])) $out[$k] = array_values($c[$k]);
    }
    // ولی هر دکمه باید همه‌ی کلیدهایش را داشته باشد. اگر جایی فقط یک کلید
    // نوشته شده باشد (مثلا فقط لینک)، بقیه از همان ردیفِ پیش‌فرض پر می‌شود —
    // وگرنه دکمه بی‌متن می‌ماند و بی‌صدا ناپدید می‌شود.
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
}

function pxVal($path, $default = null) {
    $v = pxCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

/** متن قابل ویرایش، با جای‌گذاری */
function pxT($slug, $vars = []) {
    $t = (string)(pxVal('texts.' . $slug) ?? pxDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

/** ایموجی پریمیوم دور یک نشانه */
function pxEm($slug, $fallback = '💎') {
    $id = trim((string)pxVal('emoji.' . $slug, ''));
    if ($id === '' || !ctype_digit($id)) return $fallback;
    return '<tg-emoji emoji-id="' . $id . '">' . $fallback . '</tg-emoji>';
}

// ============================================================
// 📡 گرفتن قیمت
// ============================================================

/**
 * همه‌ی جفت‌ارزها را یک بار می‌گیرد و کوتاه کش می‌کند.
 * برگشت: ['BTC/USDT' => 78931.0, 'USDT/IRT' => 197529.0, …] یا [] اگر نشد.
 */
/**
 * 🐇 حالت «اول جواب بده، بعد تازه کن».
 *
 * وقتی روشن باشد هیچ قیمتی از اینترنت گرفته نمی‌شود و هرچه در کش هست —
 * حتی کهنه — همان استفاده می‌شود.
 *
 * دلیلش: هر بار که کش تمام می‌شد، اولین کسی که «طلا» می‌نوشت پشت یک
 * تماس شبکه‌ی ۲۱۶ میلی‌ثانیه‌ای می‌ماند و تاخیر را حس می‌کرد. حالا
 * جواب با قیمت کش‌شده فوری می‌رود و تازه‌سازی بعدش انجام می‌شود، پس
 * نفر بعدی قیمت تازه دارد و هیچ‌کس معطل نمی‌ماند.
 */
function pxNoNet($on = null) {
    static $flag = false;
    if ($on !== null) $flag = (bool)$on;
    return $flag;
}

/**
 * 🔒 فقط یک تازه‌سازی در هر بازه.
 *
 * بدون این، لحظه‌ای که کش تمام می‌شود چند پیامِ هم‌زمانِ گروه هرکدام یک
 * تازه‌سازیِ کامل راه می‌اندازند: چند تماس به منبع اصلی و تا سه تماس به
 * منبع دوم، هرکدام تا ۶ ثانیه. همه‌ی این‌ها روی هم می‌نشیند و کارگرهای
 * PHP را می‌بندد — از بیرون یعنی «ربات سخت جواب می‌دهد».
 *
 * ادعا اتمی است: فقط پروسه‌ای که قفل را گرفت ادامه می‌دهد.
 */
function pxWarmClaim($secs) {
    $f = DATA_DIR . '/.px_warm';
    $now = time();
    clearstatcache(true, $f);
    if (($mt = @filemtime($f)) !== false && ($now - $mt) < $secs) return false;

    // ⚠️ خودِ ساختنِ فایل، زمانش را روی «الان» می‌گذارد. پس باید بدانیم
    //    قبل از ما بوده یا نه؛ وگرنه هر بار زمانِ خودمان را می‌بینیم و
    //    فکر می‌کنیم یکی دیگر همین حالا ادعا کرده.
    $existed = is_file($f);

    $fp = @fopen($f, 'c');
    if (!$fp) return true;                       // قفل نشد؛ لااقل کار را انجام بده
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

/**
 * بعد از فرستادن جواب: هر منبعی که کهنه بود تازه شود.
 *
 * ⚠️ فقط همانی که واقعا کهنه است. قبلا هر بار هر دو منبع را با
 * $fresh=true می‌گرفت، یعنی منبع دومِ ۵ دقیقه‌ای هم هر دقیقه از نو
 * گرفته می‌شد — سه تماس شبکه‌ی بی‌خود روی هر تازه‌سازی.
 */
function pxWarm() {
    pxNoNet(false);
    if (!pxWarmClaim(max(10, (int)(pxVal('ttl', 60) / 2)))) return;

    try {
        if (maCacheGet('px_pairs', max(15, (int)pxVal('ttl', 60))) === null) pxFetch(true);

        $alt = trim((string)pxVal('alt_url', ''));
        if ($alt !== '' && maCacheGet('px_alt', max(30, (int)pxVal('alt_ttl', 300))) === null)
            pxAltFetch(true);
    } catch (Throwable $e) {
        error_log('[prices-warm] ' . $e->getMessage());
    }
}

/**
 * آیا اصلا کشی داریم؟ (هر چقدر هم کهنه)
 *
 * فرقش با pxStale مهم است: «کهنه» یعنی می‌شود جواب داد ولی بعدش باید نو
 * کرد؛ «نداریم» یعنی چاره‌ای جز انتظار نیست.
 */
function pxHasAnyCache() {
    return is_array(maCacheGet('px_pairs', 0));
}

/** آیا الان کشِ قیمت کهنه است؟ (تا بدانیم بعدا تازه‌سازی لازم است) */
function pxStale() {
    if (maCacheGet('px_pairs', max(15, (int)pxVal('ttl', 60))) === null) return true;
    if (trim((string)pxVal('alt_url', '')) !== ''
        && maCacheGet('px_alt', max(30, (int)pxVal('alt_ttl', 300))) === null) return true;
    return false;
}

/**
 * 🔢 عددِ داخلِ هر چیزی — با کاما، فاصله، رقمِ فارسی، یا رشته.
 * null یعنی عدد نبود (نه صفر).
 */
function pxToNum($v) {
    if (!is_scalar($v)) return null;
    $raw = str_replace([',', '،', '٬', ' ', "\u{200c}"], '', norm_fa_digits((string)$v));
    return is_numeric($raw) ? (float)$raw : null;
}

/**
 * 🌐 چند آدرس، هم‌زمان.
 *
 * قبلا منبع‌ها پشت‌سرهم گرفته می‌شدند: تایم‌اوتِ اولی به‌علاوه‌ی دومی.
 * با curl_multi هر دو در یک رفت‌وبرگشت تمام می‌شوند، پس کندیِ کل برابرِ
 * کندترین منبع است نه مجموعشان.
 *
 * ورودی: [کلید => ['url'=>…, 'head'=>"…\n…", 'timeout'=>…]]
 * خروجی: [همان کلید => [آرایه‌ی JSON یا null, خطا]]
 */
function pxGetMany(array $jobs, $timeout = 6) {
    if (!$jobs) return [];

    // 🧪 قلابِ آزمون — هر آدرسی که خودش جواب داد، همان؛ هرچه null داد
    //    از مسیرِ واقعی می‌رود. پس آزمون می‌تواند یک منبع را جعل کند و
    //    همان موقع منبعِ دیگر را واقعا از شبکه بگیرد.
    $out = [];
    if (function_exists('__pxHttpHook')) {
        foreach ($jobs as $k => $j) {
            $r = __pxHttpHook($j['url'], $j['head'] ?? '');
            if (is_array($r)) { $out[$k] = $r; unset($jobs[$k]); }
        }
        if (!$jobs) return $out;
    }

    // بدونِ curl_multi (بعضی هاست‌های قدیمی) همان مسیرِ پشت‌سرهم
    if (!function_exists('curl_multi_init')) {
        foreach ($jobs as $k => $j)
            $out[$k] = maHttp($j['url'], 'GET', $j['head'] ?? '', '', (int)($j['timeout'] ?? $timeout));
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
            CURLOPT_FOLLOWLOCATION => true,
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

/**
 * 🏦 پاسخِ والکس → جفت‌ارزهای ما.
 *
 * والکس نمادها را چسبیده می‌دهد («USDTTMN»، «BTCUSDT») و قیمت را داخل
 * stats. ما دو چیز می‌خواهیم: USDT/IRT و X/USDT.
 *
 *   • هر نمادِ …TMN  → X/IRT  (و USDTTMN همان USDT/IRT است)
 *   • هر نمادِ …USDT → X/USDT
 *   • هر X که فقط تومانی‌اش را داریم → X/USDT = X_TMN ÷ USDT_TMN
 *
 * ⚠️ نامِ دقیقِ فیلدها را حدس نمی‌زنیم: بینِ lastPrice، bidPrice و
 *    askPrice هرکدام عددِ معتبری داشت برداشته می‌شود، و اگر ساختارِ
 *    پاسخ عوض شد، هر جای دیگری از درخت که «symbols» باشد پیدا می‌شود.
 *    پس یک تغییرِ کوچک در API، قیمت‌ها را صفر نمی‌کند.
 */
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

        // پایه و مقصد: اگر خودِ والکس گفته، همان؛ وگرنه از تهِ نماد
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
    if ($irt <= 0) return [];              // بدونِ نرخِ دلار، بقیه به درد نمی‌خورد

    $out = ['USDT/IRT' => $irt];
    if (isset($chg['USDT'])) $out['USDT/CHANGE24'] = $chg['USDT'];

    foreach ($usd as $b => $v) if ($b !== 'USDT' && $v > 0) $out[$b . '/USDT'] = $v;
    // آنچه فقط تومانی داشت، از روی نرخِ دلار به دلار می‌آید
    foreach ($tmn as $b => $v) {
        if ($b === 'USDT' || $v <= 0) continue;
        if (!isset($out[$b . '/USDT'])) $out[$b . '/USDT'] = $v / $irt;
    }
    foreach ($chg as $b => $v) if ($b !== 'USDT') $out[$b . '/CHANGE24'] = $v;

    return $out;
}

/** فهرستِ نمادها را در پاسخِ والکس پیدا کن — هر جای درخت که باشد. */
function pxWallexSymbols($j) {
    if (!is_array($j)) return [];
    if (is_array($j['result']['symbols'] ?? null)) return $j['result']['symbols'];
    if (is_array($j['symbols'] ?? null))           return $j['symbols'];
    if (is_array($j['result'] ?? null) && !isset($j['result']['symbols'])) {
        // شاید خودِ result همان فهرست باشد
        $r = $j['result'];
        $first = is_array($r) ? reset($r) : null;
        if (is_array($first) && (isset($first['stats']) || isset($first['symbol']))) return $r;
    }
    // آخرین تیر: هر کلیدِ «symbols» در دو سطحِ اول
    foreach ($j as $v)
        if (is_array($v) && is_array($v['symbols'] ?? null)) return $v['symbols'];
    return [];
}

function pxFetch($fresh = false) {
    static $mem = null;
    if (!$fresh && is_array($mem)) return $mem;        // در همین درخواست، یک بار

    $c = pxCfg();
    $ck = 'px_pairs';

    // 🐇 حالت بی‌شبکه: کشِ کهنه هم قبول، تماس شبکه نه
    if (pxNoNet() || (function_exists('maNoNet') && maNoNet())) {
        $any = maCacheGet($ck, 0);
        if (is_array($any) && $any) return $mem = $any;
    }

    if (!$fresh) {
        $hit = maCacheGet($ck, (int)$c['ttl']);
        if (is_array($hit)) return $mem = $hit;

        // 🐢 همین چند لحظه پیش شکست خورده؟ دوباره پشت تایم‌اوت نایست
        //
        // ⚠️ «صفر» یعنی شکستی در کار نیست. قبلا موقعِ موفقیت هم صفر
        //    نوشته می‌شد و این شرط چون فقط null را استثنا می‌کرد، بعد از
        //    هر موفقیت هم تا مدتِ cooldown سراغ شبکه نمی‌رفت — یعنی
        //    درست برعکسِ چیزی که می‌خواستیم.
        if ((int)(maCacheGet('px_cool', (int)$c['cooldown']) ?: 0) > 0)
            return $mem = (array)(maCacheGet($ck, 0) ?: []);
    }

    // 🌐 هر دو منبع، هم‌زمان — نه پشت‌سرهم.
    //
    // قبلا فقط یک منبع بود و اگر کند می‌شد، کلِ قیمت‌گیری پشتش می‌ماند.
    // حالا والکس (ایرانی، بی‌کلید، تومانِ مستقیم) و منبعِ دلاری با هم
    // گرفته می‌شوند و کندیِ کل برابرِ کندترینشان است، نه مجموعشان.
    $to   = max(2, (int)$c['timeout']);
    $jobs = [];

    $wx = trim((string)($c['wx_url'] ?? ''));
    if (!empty($c['wx_on']) && $wx !== '')
        $jobs['wx'] = ['url' => $wx, 'head' => 'Accept: application/json', 'timeout' => $to];

    $url = trim((string)$c['api']);
    if ($url !== '')
        $jobs['api'] = ['url' => $url, 'timeout' => $to,
                        'head' => 'x-api-key: ' . trim((string)$c['key']) . "\nAccept: application/json"];

    if (!$jobs) return $mem = [];
    $res = pxGetMany($jobs, $to);

    // 1️⃣ والکس پایه است — USDT/IRT و جفت‌های دلاری از همین یک تماس
    $out = $wxErr = [];
    [$jw, $ew] = $res['wx'] ?? [null, 'گرفته نشد'];
    if (is_array($jw)) {
        $out = pxWallexPairs($jw);
        if (!$out) $wxErr[] = 'والکس: نمادی پیدا نشد';
    } elseif (isset($jobs['wx'])) {
        $wxErr[] = 'والکس: ' . $ew;
    }

    // 2️⃣ منبعِ دوم فقط جاهای خالی را پر می‌کند
    [$j, $err] = $res['api'] ?? [null, 'گرفته نشد'];
    if (is_array($j)) {
        foreach (pxSwapPairs($j) as $k => $v)
            if (!isset($out[$k])) $out[$k] = $v;
    } elseif (isset($jobs['api'])) {
        $wxErr[] = 'منبع دوم: ' . $err;
    }

    // هیچ‌کدام نشد؟ کشِ قدیمی بهتر از هیچ.
    if (!$out) {
        maCachePut('px_err', implode(' · ', $wxErr) ?: 'پاسخی نیامد');
        maCachePut('px_cool', time());
        return $mem = (array)(maCacheGet($ck, 0) ?: []);
    }

    // یکی‌شان نشد ولی آن یکی جواب داد: کار می‌کند، فقط خطا را نگه دار
    maCachePut('px_err', $wxErr ? implode(' · ', $wxErr) : '');
    maCachePut('px_cool', 0);
    maCachePut($ck, $out);
    pxHistNote($out);
    return $mem = $out;
}

/**
 * پاسخِ منبعِ دومِ دلاری → جفت‌ارزها.
 * کلیدهایش خودشان «BTC/USDT» شکل‌اند؛ مقدار یا عدد است یا یک شیء.
 */
function pxSwapPairs($j) {
    // بعضی پاسخ‌ها داخل result بسته‌بندی می‌شوند
    $rows = (isset($j['result']) && is_array($j['result'])) ? $j['result'] : $j;
    if (!is_array($rows)) return [];

    $out = [];
    foreach ($rows as $k => $v) {
        if (!is_string($k) || !str_contains($k, '/')) continue;
        $K = strtoupper($k);

        // حالت ساده: مقدار خودِ قیمت است
        $n = pxToNum($v);
        if ($n !== null) { $out[$K] = $n; continue; }

        // 🔎 حالت تودرتو: بعضی APIها به‌جای یک عدد، یک شیء می‌دهند
        // {"price":…, "change24h":…}. قبلا این‌ها کامل دور ریخته می‌شدند
        // و هم قیمت و هم درصد گم می‌شد.
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

/**
 * 📈 تاریخچه‌ی قیمت — برای درصد تغییر واقعی.
 *
 * API فقط قیمتِ همین لحظه می‌دهد و درصد تغییر ندارد، برای همین همه‌ی
 * کارت‌ها «۰٪» نشان می‌دادند. حالا خودمان هر نیم‌ساعت یک نقطه ذخیره
 * می‌کنیم و درصد را نسبت به ۲۴ ساعت پیش حساب می‌کنیم.
 *
 * فایل کوچک می‌ماند: دو ساعت اخیر دقیقه‌به‌دقیقه، کهنه‌ترها ۱۰ دقیقه‌ای.
 */
if (!defined('PX_HIST_STEP')) define('PX_HIST_STEP', 60);      // هر دقیقه یک نقطه
if (!defined('PX_HIST_KEEP')) define('PX_HIST_KEEP', 180000);  // ۵۰ ساعت
if (!defined('PX_HIST_FINE')) define('PX_HIST_FINE', 7200);    // ۲ ساعتِ اخیر، دقیقه‌به‌دقیقه
if (!defined('PX_HIST_COARSE')) define('PX_HIST_COARSE', 600); // کهنه‌ترها، ۱۰ دقیقه‌ای

/**
 * تاریخچه را کوچک نگه می‌دارد.
 *
 * دقیقه‌به‌دقیقه ثبت کردن یعنی ۵۰ ساعت می‌شود ۳۰۰۰ نقطه برای هر جفت —
 * فایل بی‌خود بزرگ می‌شود. پس دو ساعت اخیر کامل می‌ماند (که درصد هر
 * دقیقه تازه شود) و قدیمی‌ترها به یکی در هر ۱۰ دقیقه نازک می‌شوند.
 */
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

    // اگر همین دقیقه ثبت شده، دوباره ننویس — نوشتن روی دیسک ارزان نیست
    $mark = DATA_DIR . '/.hist_at';
    if ($now - (@filemtime($mark) ?: 0) < PX_HIST_STEP) return;
    @touch($mark);

    mutate('px_hist', function (&$h) use ($prices, $now) {
        foreach ($prices as $pair => $v) {
            $v = (float)$v;
            if ($v <= 0) continue;                       // درصدها و صفرها به درد تاریخچه نمی‌خورند
            if (!str_contains((string)$pair, '/')) continue;
            $pts = (array)($h[$pair] ?? []);
            $pts[] = [$now, $v];
            $h[$pair] = array_values(pxHistThin($pts, $now));
        }
    });
}

/**
 * قیمتِ گذشته برای حساب کردن درصد.
 *
 * ایده‌آل ۲۴ ساعت پیش است، ولی ربات که تازه راه افتاده هنوز آن‌قدر
 * تاریخچه ندارد و درصدها روی صفر می‌ماندند. پس نزدیک‌ترین نقطه به ۲۴
 * ساعت را برمی‌داریم و اگر نبود، قدیمی‌ترین نقطه‌ای که دست‌کم
 * PX_HIST_MIN ثانیه عمر دارد — یعنی درصدِ واقعیِ همان بازه.
 */
if (!defined('PX_HIST_MIN')) define('PX_HIST_MIN', 1500);   // ۲۵ دقیقه

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

    // نقطه‌ای نزدیک ۲۴ ساعت داریم؟ همان بهترین است
    if ($best !== null && $bestGap <= $seconds * 0.75) return $best;

    // وگرنه قدیمی‌ترین نقطه، به شرطی که واقعا کهنه باشد
    if ($oldest !== null && ($now - $oldestT) >= PX_HIST_MIN) return $oldest;
    return null;
}

/** آخرین خطای موتور قیمت — برای صفحه‌ی وضعیت */
function pxLastError() { return (string)(maCacheGet('px_err', 0) ?: ''); }

function pxPair($pair, $fresh = false) {
    $p = pxFetch($fresh);
    return (float)($p[strtoupper($pair)] ?? 0);
}

/** نرخ TON به دلار */
function pxTonUsd($fresh = false) { return pxPair('TON/USDT', $fresh); }

/** نرخ دلار (تتر) به تومان */
function pxUsdtIrt($fresh = false) { return pxPair('USDT/IRT', $fresh); }

/**
 * نرخ یک ارز به تومان، با درصد سود پیکربندی‌شده.
 * همان چیزی که مینی‌اپ برای فروش استفاده می‌کند.
 */
function pxRate($sym, $fresh = false) {
    $sym = strtoupper(trim($sym));
    $irt = pxUsdtIrt($fresh);
    if ($irt <= 0) return 0.0;
    $usd = ($sym === 'USDT') ? 1.0 : pxPair($sym . '/USDT', $fresh);
    if ($usd <= 0) return 0.0;
    $m = (float)pxVal('margin', 0);
    return $usd * $irt * (1 + $m / 100);
}

/**
 * نرخ خام یک ارز به تومان — بدون هیچ سودی.
 * مینی‌اپ سود خودش را جدا سوار می‌کند، پس اینجا نباید دوبار حساب شود.
 */
function pxRawToman($sym, $fresh = false) {
    $sym = strtoupper(trim((string)$sym));
    if ($sym === '') return 0.0;
    if (empty(pxVal('on'))) return 0.0;
    $irt = pxUsdtIrt($fresh);
    if ($irt <= 0) return 0.0;
    if ($sym === 'USDT') return $irt;
    $usd = pxPair($sym . '/USDT', $fresh);
    return $usd > 0 ? $usd * $irt : 0.0;
}

function pxReady() { return !empty(pxVal('on')) && pxUsdtIrt() > 0; }

// ============================================================
// 🧮 قیمت فرگمنت — پریمیوم و استارز
// ============================================================

/** [ماه => ['usd'=>, 'ton'=>, 'irt'=>, 'off'=>]] */
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
    krsort($out);        // ۱۲ ماهه اول
    return $out;
}

/** قیمت n استارز */
function pxStars($n, $fresh = false) {
    $n = max(1, (float)$n);
    $ton = pxTonUsd($fresh);
    $irt = pxUsdtIrt($fresh);
    if ($ton <= 0 || $irt <= 0) return null;
    $usd = $n * (float)pxVal('star_usd', 0.015);
    return ['n' => $n, 'usd' => $usd, 'ton' => $usd / $ton, 'irt' => round($usd * $irt)];
}

// ============================================================
// 🗓 تاریخ شمسی
// ============================================================

/** میلادی → شمسی، بدون هیچ افزونه‌ای */
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

// ============================================================
// 🎨 قالب‌بندی عدد
// ============================================================

/** عدد بازار: بزرگ‌ها بدون اعشار، کوچک‌ها با اعشار کافی */
function pxNum($v) {
    $v = (float)$v;
    if ($v == 0.0) return '0';
    $a = abs($v);
    if ($a >= 1000)   return number_format($v, ($v == floor($v)) ? 0 : 2);
    if ($a >= 1)      return rtrim(rtrim(number_format($v, 3), '0'), '.');
    if ($a >= 0.0001) return rtrim(rtrim(number_format($v, 6), '0'), '.');
    return rtrim(rtrim(number_format($v, 8), '0'), '.');
}

/** عدد فشرده برای محور نمودار: 15,161,977,525 → 15.16B */
function pxCompact($v) {
    $v = (float)$v;
    $a = abs($v);
    if ($a >= 1e9) return rtrim(rtrim(number_format($v / 1e9, 2), '0'), '.') . 'B';
    if ($a >= 1e6) return rtrim(rtrim(number_format($v / 1e6, 2), '0'), '.') . 'M';
    if ($a >= 1e4) return rtrim(rtrim(number_format($v / 1e3, 1), '0'), '.') . 'K';
    if ($a >= 1)   return number_format($v);
    return pxNum($v);
}

/** تومان همیشه رند و با جداکننده */
function pxToman($v) { return number_format(round((float)$v)); }

// ============================================================
// 💬 متن پیام‌ها
// ============================================================

/** سه‌خطی تومان/دلار/تون داخل نقل‌قول */
/**
 * یک ردیف قیمت — دقیقا به همان شکلی که در قالب تبدیل پسندیده شد:
 * هر مقدار ایموجی پریمیوم خودش را دارد و هیچ واژه‌ی فارسی
 * («تومان»، «دلار»، «تون») کنارش نوشته نمی‌شود — ایموجی خودش می‌گوید
 * عدد مالِ چیست.
 */
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

/**
 * امضای پایین قالب — ایموجی فرگمنت، بعدش خودِ کلمه.
 *
 * هیچ چیز جلوتر از ایموجی نمی‌نشیند: خطِ خودش است و از همان ایموجی
 * شروع می‌شود.
 */
function pxFootLine() {
    return pxEm('frag', '🔻') . ' <b>' . h(pxT('foot')) . "</b>\n";
}

/** تاریخ — با ایموجی پرمیوم، داخل نقل‌قول خودش */
function pxDateLine() {
    return '<blockquote>' . pxEm('date', '🕓') . ' ' . h(pxJalali()) . '</blockquote>';
}

/**
 * قالب کاملِ پیام، دستِ خود ادمین.
 * اگر «قالب کامل پریمیوم» در پنل پر شده باشد، تمام پیام از همان ساخته
 * می‌شود و هیچ‌چیز دیگری قاطی‌اش نمی‌شود — پس واقعا هر شکلی بخواهید
 * می‌شود، با ایموجی پرمیوم و quote خودتان.
 */
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

    // {packs} — همان فهرست بسته‌ها، آماده برای چسباندن هرجای قالب
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

/** {کلید} را با مقدارش عوض می‌کند و بقیه‌ی متن را دست نمی‌زند */
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

    // بسته‌های آماده، جمع‌وجور داخل یک نقل‌قول بازشونده
    $packs = array_values(array_filter(array_map('intval', (array)pxVal('star_packs', []))));
    if ($packs) {
        $t .= '<blockquote expandable>';
        $lines = [];
        foreach ($packs as $p) {
            $d = pxStars($p);
            if (!$d) continue;
            // فهرست بسته‌ها فقط ستاره و عدد — ایموجی تومان و تون اینجا
            // شلوغش می‌کرد، در حالی که بالاتر یک بار گفته شده عددها چیستند
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

/** متن زیر کارت یک ارز */
function pxCoinCaption($sym, $usd, $irt, $chg, $hi, $lo, $n = 1) {
    $t  = pxEm('conv', '🪙') . ' <b>' . h(pxT('coin_head', ['n' => pxNum($n), 'sym' => $sym])) . "</b>\n\n";
    $t .= '<blockquote>' . pxEm('usd', '💵') . ' ' . pxNum($usd) . '</blockquote>' . "\n";
    $t .= '<blockquote>' . pxEm('toman', '💰') . ' ' . pxToman($irt) . '</blockquote>' . "\n";
    $t .= '<blockquote>' . pxEm('chg', '📈') . ' ' . ($chg >= 0 ? '+' : '−') .
          number_format(abs($chg), 2) . '%</blockquote>' . "\n";

    if ($hi > 0 && $lo > 0) {
        $t .= pxEm('chart', '📊') . ' <b>' . h(pxT('hl_head')) . "</b>\n";
        $t .= '<blockquote expandable>' .
              pxToman($hi * ($usd > 0 ? $irt / $usd : 0)) . ' / ' .
              pxToman($lo * ($usd > 0 ? $irt / $usd : 0)) . ' ' . h(pxT('toman')) . "\n" .
              pxNum($hi) . ' / ' . pxNum($lo) . ' ' . h(pxT('dollar')) . '</blockquote>' . "\n";
    }
    $t .= pxDateLine();
    return $t;
}

/** دکمه‌های شیشه‌ای زیر پیام‌های قیمت */
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

// ============================================================
// 🖼 کارت گرافیکی ارز
// ============================================================

/** فونتی که حتما روی سرور هست */
/**
 * فونتِ کارت.
 *
 * قبلا فقط چهار مسیرِ ثابت را نگاه می‌کرد؛ روی هاست اشتراکی هیچ‌کدام
 * نبود و کارت بی‌صدا تبدیل می‌شد به متنِ خالی. حالا:
 *
 *   ۱) فونتی که خودتان در پنل داده‌اید (یا به ربات فرستاده‌اید)
 *   ۲) کنار همین فایل، در پوشه‌ی fonts/
 *   ۳) مسیرهای معمولِ لینوکس و ویندوز
 *   ۴) جست‌وجوی واقعی در پوشه‌های فونتِ سیستم — هر ttf که پیدا شد
 */
function pxFont($bold = true) {
    static $cache = [];
    $k = $bold ? 'b' : 'r';
    if (isset($cache[$k])) return $cache[$k];

    $set = trim((string)pxVal('card.font' . ($bold ? '_bold' : ''), ''));
    if ($set !== '' && is_file($set)) return $cache[$k] = $set;
    // یک فونت هم بهتر از هیچ: اگر فقط یکی داده شده، برای هر دو حالت
    $any = trim((string)pxVal('card.font_bold', '')) ?: trim((string)pxVal('card.font', ''));
    if ($any !== '' && is_file($any)) return $cache[$k] = $any;

    $names = $bold
        ? ['DejaVuSans-Bold.ttf', 'LiberationSans-Bold.ttf', 'Roboto-Bold.ttf',
           'Vazirmatn-Bold.ttf', 'arialbd.ttf', 'FreeSansBold.ttf', 'NotoSans-Bold.ttf']
        : ['DejaVuSans.ttf', 'LiberationSans-Regular.ttf', 'Roboto-Regular.ttf',
           'Vazirmatn-Regular.ttf', 'arial.ttf', 'FreeSans.ttf', 'NotoSans-Regular.ttf'];

    $dirs = [
        rtrim(DATA_DIR, '/') . '/fonts',          // فونتی که به ربات فرستاده‌اید
        __DIR__ . '/fonts',
        '/usr/share/fonts/truetype/dejavu',
        '/usr/share/fonts/truetype/liberation',
        '/usr/share/fonts/truetype/freefont',
        '/usr/share/fonts/dejavu',
        '/usr/share/fonts/TTF',
        '/usr/local/share/fonts',
        'C:\\Windows\\Fonts',
    ];
    foreach ($dirs as $d) foreach ($names as $n)
        if (is_file($d . '/' . $n)) return $cache[$k] = $d . '/' . $n;

    // هنوز نه؟ هر ttf ای که پیدا شود — ولی فقط آن‌هایی که واقعا حرف
    // فارسی دارند. فونتِ فقط‌لاتین کارت را با مربع پر می‌کند، که از
    // نساختنِ کارت هم بدتر است.
    $any = '';
    foreach (['/usr/share/fonts', '/usr/local/share/fonts', rtrim(DATA_DIR, '/')] as $root) {
        if (!is_dir($root)) continue;
        foreach ([$root . '/*.[tT][tT][fF]', $root . '/*/*.[tT][tT][fF]',
                  $root . '/*/*/*.[tT][tT][fF]'] as $pat) {
            foreach ((glob($pat) ?: []) as $f) {
                if ($any === '') $any = $f;
                if (pxFontHasFa($f)) return $cache[$k] = $f;
            }
        }
    }
    return $cache[$k] = $any;      // هیچ‌کدام فارسی نداشت — لااقل یکی
}

/**
 * این فونت حرف فارسی دارد یا مربع می‌کشد؟
 *
 * شمردن پیکسل جواب نمی‌دهد: مربعِ «حرف را ندارم» هم پیکسل دارد و
 * حتی پررنگ‌تر از خودِ حرف است. پس یک نویسه‌ی «قطعا موجود نیست»
 * (ناحیه‌ی خصوصی یونیکد) را هم می‌کشیم؛ اگر شکلشان یکی درآمد،
 * یعنی هر دو مربع‌اند و این فونت فارسی ندارد.
 */
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

    $miss = $shot("\u{E000}");          // ناحیه‌ی خصوصی — هیچ فونتی ندارد
    $fa   = $shot('ط');
    if ($miss === null || $fa === null) return $memo[$file] = false;
    if (substr_count($fa, '1') < 8)     return $memo[$file] = false;   // چیزی نکشید
    return $memo[$file] = ($fa !== $miss);
}

function pxCardReady() {
    return function_exists('imagecreatetruecolor')
        && function_exists('imagettftext')
        && pxFont(true) !== '';
}

/** چرا کارت ساخته نمی‌شود؟ رشته‌ی خالی یعنی مشکلی نیست. */
function pxCardWhy() {
    if (!function_exists('imagecreatetruecolor'))
        return 'افزونه‌ی GD روی سرور نصب نیست. از پشتیبانی هاست بخواهید gd را روشن کند.';
    if (!function_exists('imagettftext'))
        return 'GD هست ولی بدون FreeType، پس نمی‌تواند متن بنویسد. از هاست بخواهید gd را با freetype بسازد.';
    if (pxFont(true) === '')
        return 'هیچ فونتی روی سرور پیدا نشد. یک فایل .ttf برای ربات بفرستید تا همین‌جا ذخیره‌اش کند.';
    return '';
}

/** #RRGGBB → [r,g,b] */
function pxHex($hex) {
    $hex = ltrim((string)$hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * سایه‌ی نرمِ زیر کارت.
 *
 * قبلا چهارده مستطیلِ روی‌هم بود و هرکدام تقریبا تمام بوم را می‌پوشاند
 * — ۱۵۰ میلی‌ثانیه از ۱۸۰ میلی‌ثانیه‌ی کل کارت، فقط برای همین سایه.
 * حالا روی یک بوم کوچک (یک‌ششم) کشیده و بلور می‌شود و بعد بزرگ
 * می‌شود: هم چند برابر سریع‌تر، هم نرم‌تر از قبل.
 */
function pxSoftShadow($im, $x1, $y1, $x2, $y2, $r, $layers = 14, $alpha = 126) {
    // فقط حلقه‌ی بیرونی کشیده می‌شود، نه تمام سطح.
    //
    // شکلِ نهایی مو‌به‌مو همان قبلی است — چون کارتِ سفید بلافاصله بعد
    // رویش می‌نشیند و داخلِ سایه هیچ‌وقت دیده نمی‌شد. ولی به‌جای
    // چهارده بار پر کردنِ یک سطح ۱۰۵۰×۵۰۰، هر لایه فقط چند نوار باریک
    // و چهار گوشه است: همان ظاهر، کسری از هزینه.
    for ($i = $layers; $i >= 1; $i--)
        pxRoundRing($im, $x1 - $i, $y1 - $i + 5, $x2 + $i, $y2 + $i + 5, $r + $i, $i, $alpha);
}

/**
 * حلقه‌ی بیرونیِ یک مستطیلِ گردگوشه، به ضخامت $t.
 * داخلش دست‌نخورده می‌ماند.
 */
function pxRoundRing($im, $x1, $y1, $x2, $y2, $r, $t, $alpha = 126) {
    $t = max(1, (int)$t);
    $c = imagecolorallocatealpha($im, 0, 0, 0, (int)$alpha);

    // چهار نوارِ لبه
    imagefilledrectangle($im, $x1 + $r, $y1,          $x2 - $r, $y1 + $t, $c);   // بالا
    imagefilledrectangle($im, $x1 + $r, $y2 - $t,     $x2 - $r, $y2,      $c);   // پایین
    imagefilledrectangle($im, $x1,      $y1 + $r,     $x1 + $t, $y2 - $r, $c);   // چپ
    imagefilledrectangle($im, $x2 - $t, $y1 + $r,     $x2,      $y2 - $r, $c);   // راست

    // چهار گوشه — کمانِ کامل، ولی روی سطحِ کوچکِ خودِ گوشه
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r],
              [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as $p)
        imagefilledellipse($im, $p[0], $p[1], $r * 2, $r * 2, $c);
}

function pxRoundRect($im, $x1, $y1, $x2, $y2, $r, $color) {
    imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $color);
    imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $color);
    foreach ([[$x1 + $r, $y1 + $r], [$x2 - $r, $y1 + $r], [$x1 + $r, $y2 - $r], [$x2 - $r, $y2 - $r]] as $c)
        imagefilledellipse($im, $c[0], $c[1], $r * 2, $r * 2, $color);
}

function pxText($im, $size, $x, $y, $color, $text, $bold = true) {
    $f = pxFont($bold);
    if ($f === '') return;
    imagettftext($im, $size, 0, (int)$x, (int)$y, $color, $f, (string)$text);
}

function pxTextW($size, $text, $bold = true) {
    $f = pxFont($bold);
    if ($f === '') return 0;
    $b = imagettfbbox($size, 0, $f, (string)$text);
    return abs($b[2] - $b[0]);
}

/**
 * سری قیمت ساختگی برای شکلِ نمودار.
 * قیمت آخر همان قیمت واقعی است؛ بقیه فقط برای این است که کارت خالی نباشد.
 * هیچ‌جا به‌عنوان «تاریخچه واقعی» ادعا نمی‌شود.
 */
function pxSeries($last, $chgPct, $n = 90) {
    $last = max((float)$last, 1e-9);
    $n    = max(8, (int)$n);
    $chg  = (float)$chgPct;

    // نمودار باید با همان درصدی که روی کارت نوشته شده جور دربیاید:
    // اگر نشان «+۰٫۸۴٪» می‌دهیم، خط هم باید بالا رفته باشد. قبلا نمودار
    // یک قدم‌زنیِ کاملا تصادفی بود و گاهی خلافِ برچسب را نشان می‌داد.
    $first = $last / (1 + $chg / 100);
    if (!is_finite($first) || $first <= 0) $first = $last;

    $amp = abs($last - $first);
    if ($amp <= 0) $amp = $last * 0.006;     // روز آرام هم صاف نباشد
    $amp *= 0.42;                            // نوسان، کوچک‌تر از خودِ روند

    $out = [];
    $noise = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $t    = $i / ($n - 1);
        $base = $first + ($last - $first) * $t;
        // نوسان نرم: هر قدم کمی از قدم قبل ارث می‌برد، پس خط دندانه‌ای نمی‌شود
        $noise = $noise * 0.72 + (mt_rand(-1000, 1000) / 1000) * $amp * 0.5;
        // دو سرِ خط به عددهای واقعی چفت می‌شوند
        $edge  = sin(M_PI * $t);
        $out[] = max(1e-9, $base + $noise * $edge);
    }
    $out[0]      = $first;
    $out[$n - 1] = $last;
    return $out;
}

/**
 * کارت نئونی یک ارز. برگشت: بایت‌های PNG یا null.
 * همه‌ی متن‌ها لاتین‌اند تا هیچ فونت فارسی‌ای لازم نباشد.
 */
function pxCard($symbol, $usd, $chgPct, $series = null) {
    if (!pxCardReady()) return null;

    $W = max(600, (int)pxVal('card.w', 1080));
    $H = max(400, (int)pxVal('card.h', 620));
    $up = $chgPct >= 0;

    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imageantialias($im, true);

    $C = function ($hex, $a = 0) use ($im) {
        [$r, $g, $b] = pxHex($hex);
        return imagecolorallocatealpha($im, $r, $g, $b, $a);
    };
    $bgBase  = $C('080A10');
    $bgPanel = $C('0D111A');
    $grid    = $C('1A2133');
    $white   = $C('FFFFFF');
    $muted   = $C('64748B');
    $accHex  = $up ? '00F0FF' : 'FF003C';
    $acc     = $C($accHex);

    imagefilledrectangle($im, 0, 0, $W, $H, $bgBase);

    // پنل داخلی
    $m = 30;
    $rad = 22;
    pxRoundRect($im, $m, $m, $W - $m, $H - $m, $rad, $bgPanel);

    // درخشش بالای پنل — خط‌به‌خط، تا از گوشه‌های گرد بیرون نزند.
    // نسخه‌ی قبلی بیضی بود و بیرونِ کارت یک لکه‌ی قرمزِ بریده می‌ساخت.
    $band = 210;
    for ($dy = 0; $dy < $band; $dy++) {
        $t = $dy / $band;                       // ۰ بالا، ۱ پایینِ نوار
        $a = (int)round(104 + 23 * $t);         // ۱۰۴ کم‌رنگ … ۱۲۷ نامرئی
        if ($a > 127) break;
        $inset = ($dy < $rad) ? (int)round($rad - sqrt(max(0, $rad * $rad - ($rad - $dy) * ($rad - $dy)))) : 0;
        imageline($im, $m + $inset, $m + $dy, $W - $m - $inset, $m + $dy, $C($accHex, $a));
    }

    $x = $m + 38;
    $y = $m + 78;
    $sym = strtoupper((string)$symbol);

    pxText($im, 42, $x, $y, $white, $sym);
    $pairX = $x + pxTextW(42, $sym) + 22;
    $pair  = $sym . ' / USDT';
    $pw    = pxTextW(17, $pair);
    pxRoundRect($im, $pairX, $y - 26, $pairX + $pw + 26, $y + 6, 8, $grid);
    pxText($im, 17, $pairX + 13, $y - 4, $muted, $pair);

    $priceStr = '$' . pxNum($usd);
    pxText($im, 56, $x, $y + 86, $white, $priceStr);

    $pct = ($up ? '+' : '-') . number_format(abs((float)$chgPct), 2) . '%';
    $pcw = pxTextW(20, $pct);
    pxRoundRect($im, $x, $y + 112, $x + $pcw + 34, $y + 158, 9, $C('141B29'));
    pxText($im, 20, $x + 17, $y + 143, $acc, $pct);

    // ── نمودار ──
    $cx1 = $m + 30;  $cx2 = $W - $m - 44;   // جا برای هاله‌ی نقطه‌ی آخر
    $cy1 = $y + 190; $cy2 = $H - $m - 60;
    $data = is_array($series) && count($series) > 3 ? array_values($series) : pxSeries($usd, $chgPct);
    $n = count($data);
    $min = min($data); $max = max($data);
    if ($max - $min < 1e-12) { $max = $min + 1e-9; }

    for ($i = 0; $i <= 4; $i++) {
        $gy = (int)($cy1 + ($cy2 - $cy1) * $i / 4);
        imageline($im, $cx1, $gy, $cx2, $gy, $grid);
    }

    $px = function ($i) use ($cx1, $cx2, $n) { return $cx1 + ($cx2 - $cx1) * $i / max(1, $n - 1); };
    $py = function ($v) use ($cy1, $cy2, $min, $max) {
        return $cy2 - ($cy2 - $cy1) * (($v - $min) / ($max - $min));
    };

    // سایه‌ی زیر خط
    $fill = $C($accHex, 108);
    $poly = [];
    for ($i = 0; $i < $n; $i++) { $poly[] = (int)$px($i); $poly[] = (int)$py($data[$i]); }
    $poly[] = (int)$cx2; $poly[] = (int)$cy2;
    $poly[] = (int)$cx1; $poly[] = (int)$cy2;
    imagefilledpolygon($im, $poly, $fill);

    imagesetthickness($im, 3);
    for ($i = 1; $i < $n; $i++)
        imageline($im, (int)$px($i - 1), (int)$py($data[$i - 1]), (int)$px($i), (int)$py($data[$i]), $acc);
    imagesetthickness($im, 1);

    // نقطه‌ی آخر با هاله
    $lx = (int)$px($n - 1); $ly = (int)$py($data[$n - 1]);
    imagefilledellipse($im, $lx, $ly, 26, 26, $C($accHex, 100));
    imagefilledellipse($im, $lx, $ly, 12, 12, $acc);
    imagefilledellipse($im, $lx, $ly, 5, 5, $white);

    $wm = trim((string)(cfg()['bot_username'] ?? ''));
    $wm = $wm !== '' ? '@' . $wm : 'Live Market';
    pxText($im, 15, (int)(($W - pxTextW(15, $wm)) / 2), $H - $m - 22, $muted, $wm, false);

    return pxPngOut($im);
}

/** فرستادن عکس با کپشن — tg() فقط فرم ساده می‌فرستد، پس اینجا خودمان */
/**
 * 🚀 فرستادن کارت با شناسه‌ی فایلِ تلگرام.
 *
 * هر کارت حدود ۴۳ کیلوبایت است و تا حالا هر بار از نو آپلود می‌شد —
 * روی هاستی که خط خوبی به تلگرام ندارد، همین آپلود چند ثانیه می‌شد و
 * کاربر «کندی» حس می‌کرد.
 *
 * تلگرام بعد از اولین آپلود یک file_id می‌دهد. دفعه‌ی بعد همان تصویر
 * را فقط با همان شناسه می‌فرستیم: هیچ بایتی آپلود نمی‌شود، فقط یک
 * درخواست کوچک. تا وقتی قیمت عوض نشده، همه‌ی کاربرها همان کارت را
 * می‌گیرند.
 */
function pxPhotoIdKey($cacheKey) { return 'pxfid_' . substr(md5((string)$cacheKey), 0, 16); }

function pxSendPhotoById($chatId, $fileId, $caption, $markup = null, $replyTo = null) {
    $post = [
        'chat_id'    => $chatId,
        'photo'      => $fileId,
        'caption'    => mb_substr((string)$caption, 0, 1024),
        'parse_mode' => 'HTML',
    ];
    if ($markup)  $post['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    if ($replyTo) $post['reply_to_message_id'] = $replyTo;
    return tg(BOT_TOKEN, 'sendPhoto', $post, 20);
}

/** شناسه‌ی فایل را از پاسخ تلگرام درمی‌آورد */
function pxPhotoIdOf($resp) {
    $ph = $resp['result']['photo'] ?? null;
    if (!is_array($ph) || !$ph) return '';
    $last = $ph[count($ph) - 1] ?? [];
    return (string)($last['file_id'] ?? '');
}

function pxSendPhoto($chatId, $bytes, $caption, $markup = null, $replyTo = null, $cacheKey = '') {
    // ⚡ اگر همین کارت قبلا آپلود شده، فقط شناسه‌اش را می‌فرستیم
    if ($cacheKey !== '') {
        $fid = (string)(maCacheGet(pxPhotoIdKey($cacheKey), 86400) ?? '');
        if ($fid !== '') {
            $r = pxSendPhotoById($chatId, $fid, $caption, $markup, $replyTo);
            if (!empty($r['ok'])) return $r;
            maCachePut(pxPhotoIdKey($cacheKey), '');   // منقضی شده — از نو آپلود کن
        }
    }

    if (!is_string($bytes) || strlen($bytes) < 100)
        return ['ok' => false, 'description' => 'تصویر ساخته نشد'];

    // 🧪 در تست، آپلود واقعی انجام نمی‌شود — ولی همه‌ی مسیرِ بالا، یعنی
    //    همان جایی که شناسه‌ی فایل دوباره استفاده می‌شود، واقعا اجرا
    //    شده است. قبلا این قلاب اول تابع بود و آن مسیر هیچ‌وقت تست
    //    نمی‌شد.
    if (function_exists('__tgHook')) {
        $out = __tgHook(BOT_TOKEN, 'sendPhoto',
            ['chat_id' => $chatId, 'caption' => $caption, 'photo_len' => strlen((string)$bytes)]);
        if ($cacheKey !== '' && !empty($out['ok'])) {
            $fid = pxPhotoIdOf($out);
            if ($fid !== '') maCachePut(pxPhotoIdKey($cacheKey), $fid);
        }
        return $out;
    }

    // فایل موقت را کنار داده‌های خود ربات می‌سازیم، نه در /tmp سیستم.
    // روی هاست‌های اشتراکی /tmp اغلب قابل نوشتن نیست و آن‌وقت عکس
    // بی‌صدا ناپدید می‌شد — نه خطایی، نه پیامی.
    $dir = rtrim(DATA_DIR, '/') . '/tmp';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $tmp = $dir . '/px_' . bin2hex(random_bytes(6)) . '.png';

    if (@file_put_contents($tmp, $bytes) === false) {
        $tmp2 = @tempnam(sys_get_temp_dir(), 'px');       // آخرین تلاش
        if ($tmp2 === false || @file_put_contents($tmp2, $bytes) === false)
            return ['ok' => false, 'description' => 'جایی برای نوشتن فایل موقت نبود'];
        $tmp = $tmp2;
    }

    $post = [
        'chat_id' => $chatId,
        'caption' => mb_substr((string)$caption, 0, 1024),
        'parse_mode' => 'HTML',
        'photo' => new CURLFile($tmp, 'image/png', 'chart.png'),
    ];
    if ($markup)  $post['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    if ($replyTo) $post['reply_to_message_id'] = $replyTo;

    $base = defined('TG_API_BASE') ? TG_API_BASE : 'https://api.telegram.org';
    $ch = curl_init($base . '/bot' . BOT_TOKEN . '/sendPhoto');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    @unlink($tmp);

    if ($res === false) return ['ok' => false, 'description' => 'curl: ' . $err];
    $out = json_decode((string)$res, true);
    if (!is_array($out)) return ['ok' => false, 'description' => 'پاسخ نامعتبر تلگرام'];

    // شناسه را نگه دار تا دفعه‌ی بعد آپلود لازم نباشد
    if ($cacheKey !== '' && !empty($out['ok'])) {
        $fid = pxPhotoIdOf($out);
        if ($fid !== '') maCachePut(pxPhotoIdKey($cacheKey), $fid);
    }
    return $out;
}

/**
 * کارت را می‌فرستد؛ اگر به هر دلیلی نشد، همان متن را می‌فرستد.
 *
 * قانون: کاربر نباید هیچ‌وقت دست خالی بماند. عکس نرفت؟ متن می‌رود.
 * و علتش یک بار در ساعت به مدیر گفته می‌شود تا خرابی ساکت نماند.
 */
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

/** کارت بساز، ولی اگر GD سرِ راه مرد، کل پیام را نکش */
/**
 * همان pxTryCard، ولی نتیجه را کوتاه‌مدت نگه می‌دارد.
 *
 * ساختن هر کارت حدود ۷۰ میلی‌ثانیه است. وقتی چند نفر پشت سر هم در گروه
 * «طلا» می‌نویسند، قیمت که عوض نشده — پس همان تصویر دوباره استفاده
 * می‌شود و فقط نفر اول هزینه‌ی ساختن را می‌دهد.
 */
/**
 * 🗜 خروجیِ PNG — نصفِ حجم، بدون فرقِ دیدنی.
 *
 * کارت را کاربرِ ایرانی روی موبایل می‌بیند و هر کیلوبایت، وقتِ آپلود
 * است: اول از سرورِ ما به تلگرام، بعد از تلگرام به گوشیِ او. کارت
 * تخت است — چند رنگِ ثابت و یک گرادیانِ ملایم — پس ۲۵۵ رنگ با
 * دیترینگ عینِ همان می‌شود و حجم نصف.
 *
 * اندازه‌گیری روی کارتِ دلار: ۴۰.۵ → ۲۱.۰ کیلوبایت، و حتی کمی
 * سریع‌تر از فشرده‌سازیِ سطح ۶ روی تصویرِ کاملِ رنگی.
 */
function pxPngOut($im) {
    $w = imagesx($im); $h = imagesy($im);
    $p = imagecreatetruecolor($w, $h);
    imagecopy($p, $im, 0, 0, 0, 0, $w, $h);
    imagetruecolortopalette($p, true, 255);

    ob_start();
    imagepng($p, null, 9);
    $small = ob_get_clean();
    imagedestroy($p);

    // اگر پالت به هر دلیلی بزرگ‌تر درآمد، همان اصلی را بده
    ob_start();
    imagepng($im, null, 6);
    $full = ob_get_clean();
    imagedestroy($im);

    return (strlen($small) > 0 && strlen($small) < strlen($full)) ? $small : $full;
}

function pxCardCached($key, callable $fn) {
    $ttl  = max(10, (int)pxVal('card_ttl', 90));
    $file = pxCardFile($key);

    if (is_file($file)) {
        clearstatcache(true, $file);
        if (time() - (@filemtime($file) ?: 0) <= $ttl) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && strlen($raw) > 100) return $raw;
        }
    }

    $png = pxTryCard($fn);
    if (is_string($png) && $png !== '' && strlen($png) > 100) {
        $dir = dirname($file);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        // اول موقت، بعد جابه‌جا — تا درخواستِ همزمان نصفه‌ی فایل را نخواند
        $tmp = $file . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $png) !== false) @rename($tmp, $file);
        else @unlink($tmp);
        pxCardPrune();
    }
    return $png;
}

/**
 * 🖼 کارت‌ها روی دیسک، نه داخل کشِ JSON.
 *
 * ⚠️ چرا این مهم است: قبلا هر کارت را base64 کرده و داخل ma_cache.json
 *    می‌گذاشتیم. هر کارت حدود ۴۰ کیلوبایت است و base64 بزرگ‌ترش هم
 *    می‌کند؛ با ۱۴ ارز، آن فایل ۹۰۰ کیلوبایت شده بود.
 *
 *    و آن فایل، فایلِ کشِ کلِ ربات است: هر maCachePut — از هر بخشی، نه
 *    فقط قیمت — کلش را می‌خواند، JSON را باز می‌کند، دوباره می‌سازد و
 *    زیر قفل می‌نویسد. یعنی یک تصویر، کلِ ربات را کند می‌کرد و هرچه
 *    ارزهای بیشتری کش می‌شد، کندتر.
 *
 *    حالا تصویر یک فایلِ جداست: خواندنش یک read است، نوشتنش هیچ قفلی
 *    نمی‌گیرد، و کشِ JSON دوباره چند کیلوبایت می‌شود.
 */
function pxCardFile($key) {
    return rtrim(DATA_DIR, '/') . '/pxcards/' . substr(md5((string)$key), 0, 16) . '.png';
}

/**
 * 🧹 کارت‌هایی که در نسخه‌های قبل داخل کشِ JSON نشسته‌اند را بیرون بریز.
 *
 * روی نصبی که مدتی کار کرده، این فایل صدها کیلوبایت شده و هر نوشتنِ کش
 * در کلِ ربات را کند می‌کند. یک بار تمیزش می‌کنیم و تمام.
 */
function pxDropCardCache() {
    $n = 0;
    mutate('ma_cache', function (&$c) use (&$n) {
        foreach (array_keys($c) as $k)
            if (str_starts_with((string)$k, 'pxcard_')) { unset($c[$k]); $n++; }
    });
    return $n;
}

/** کارت‌های کهنه را جمع کن — پوشه بی‌نهایت بزرگ نشود */
function pxCardPrune() {
    $dir = rtrim(DATA_DIR, '/') . '/pxcards';
    // گران است، پس نه هر بار: حداکثر هر ۱۰ دقیقه یک بار
    $mark = $dir . '/.swept';
    if (is_file($mark) && time() - (@filemtime($mark) ?: 0) < 600) return;
    @touch($mark);

    $keep = max(600, (int)pxVal('card_ttl', 90) * 20);
    $now  = time();
    foreach ((array)@glob($dir . '/*.png') as $f) {
        if ($now - (@filemtime($f) ?: $now) > $keep) @unlink($f);
    }
}

function pxTryCard(callable $fn) {
    if (empty(pxVal('card.on'))) return null;

    // کارت روشن است ولی سرور نمی‌تواند بسازدش؟ یک‌بار به ادمین بگو چرا،
    // وگرنه فقط متنِ خالی می‌رود و کسی نمی‌فهمد چه شده.
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

// ============================================================
// 🗣 تشخیص پیام
// ============================================================

/** نام فارسی ارزها → نماد */
function pxCoinMap() {
    return [
        'بیتکوین' => 'BTC', 'بیت کوین' => 'BTC', 'بیت' => 'BTC',
        'اتریوم' => 'ETH', 'اتر' => 'ETH', 'اتریم' => 'ETH',
        'تتر' => 'USDT', 'دلار' => 'USDT',
        'سولانا' => 'SOL', 'سول' => 'SOL',
        'تون' => 'TON', 'تون کوین' => 'TON', 'تونکوین' => 'TON',
        'ترون' => 'TRX', 'ترکس' => 'TRX',
        'ریپل' => 'XRP',
        'بایننس' => 'BNB', 'بی ان بی' => 'BNB',
        'دوج' => 'DOGE', 'دوجکوین' => 'DOGE', 'دوج کوین' => 'DOGE',
        'شیبا' => 'SHIB',
        'نات' => 'NOT', 'نات کوین' => 'NOT',
        'کاردانو' => 'ADA', 'آدا' => 'ADA',
        'پپه' => 'PEPE',
        'آواکس' => 'AVAX',
        'ماتیک' => 'MATIC',
        'دات' => 'DOT',
        'لینک' => 'LINK',
        'لایت کوین' => 'LTC',
        'اتم' => 'ATOM',
        'نیر' => 'NEAR',
        'استلار' => 'XLM',
    ];
}

/** «پریمیوم» یا «استارز» یا … — کدام دسته؟ */
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

/** «۵۰۰ استارز» → 500 */
function pxStarsCount($text) {
    $t = norm_fa_digits((string)$text);
    if (preg_match('/(\d{1,7})/', $t, $m)) return max(1, (int)$m[1]);
    return 1;
}

/**
 * «۵۰ تتر به تومان» یا «2 sol به usdt» یا فقط «۵۰ تتر»
 * برگشت: [مقدار, نماد مبدا, نماد مقصد] یا null
 */
function pxConvert($text) {
    $t = trim(mb_strtolower(norm_fa_digits((string)$text)));
    $t = str_replace(['،', ',', '٬'], '', $t);
    if (!preg_match('/^([\d\.]+)\s*([^\d]+?)(?:\s+(?:به|to|in)\s+(.+))?$/u', $t, $m)) return null;

    $amount = (float)$m[1];
    if ($amount <= 0 || !is_finite($amount)) return null;

    $map = pxCoinMap();
    $src = trim($m[2]);
    $from = $map[$src] ?? strtoupper($src);

    $dst = isset($m[3]) ? trim($m[3]) : '';
    if ($dst === '' || in_array($dst, ['تومان', 'تومن', 'ریال', 'irt', 'toman'], true)) $to = 'IRT';
    elseif (in_array($dst, ['دلار', 'تتر', 'usd', 'usdt'], true))                        $to = 'USDT';
    else                                                                                 $to = $map[$dst] ?? strtoupper($dst);

    // کلمه‌ی خام مبدا را هم برمی‌گردانیم تا اگر ارز دیجیتال نبود،
    // بشود بین دارایی‌ها (افغانی، لیر، طلا…) دنبالش گشت.
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $from) && pxAssetOfWord($src) === null) return null;
    if ($to !== 'IRT' && !preg_match('/^[A-Z0-9]{2,10}$/', $to)) return null;
    return [$amount, $from, $to, $src];
}

/** یک کلمه‌ی خام → کلید دارایی (بدون گیر دادن به فاصله‌های اضافه) */
function pxAssetOfWord($word) {
    $w = trim(mb_strtolower(norm_fa_digits((string)$word)));
    if ($w === '') return null;
    foreach (pxAssets() as $k => $a) {
        foreach (explode(',', (string)($a['words'] ?? '')) as $x) {
            $x = trim(mb_strtolower(norm_fa_digits($x)));
            if ($x !== '' && $x === $w) return $k;
        }
    }
    return null;
}

/** کپشن تبدیلِ دارایی */
/**
 * کپشن تبدیل — هر تکه داخل نقل‌قول خودش.
 *
 * سه سطر جدا: قیمت دلاری، قیمت تومانی، و تاریخ. واژه‌ی فارسی «تومان»
 * نوشته نمی‌شود؛ خودِ ایموجی می‌گوید کدام است.
 */
function pxConvAssetCaption($title, $val, $unit, $usdVal = null, $chg = null) {
    $t  = pxEm('conv', '💱') . ' <b>' . h($title) . "</b>\n\n";
    $t .= pxConvBody($val, $unit, $usdVal, $chg);
    return $t;
}

/** بدنه‌ی مشترکِ تبدیل — هر عدد در نقل‌قولِ خودش */
function pxConvBody($val, $unit, $usdVal = null, $chg = null) {
    $isT = ($unit === 'تومان');
    $t = '';

    if ($usdVal !== null && $usdVal > 0)
        $t .= '<blockquote>' . pxEm('usd', '💵') . ' ' . pxNum($usdVal) . '</blockquote>' . "\n";

    $t .= '<blockquote>' . ($isT ? pxEm('toman', '💰') : pxEm('usd', '💵')) . ' ' .
          ($isT ? pxToman($val) : pxNum($val)) . ($isT ? '' : ' ' . h($unit)) . '</blockquote>' . "\n";

    if ($chg !== null)
        $t .= '<blockquote>' . pxEm('chg', '📈') . ' ' . ($chg >= 0 ? '+' : '−') .
              number_format(abs($chg), 2) . '%</blockquote>' . "\n";

    $t .= '<blockquote>' . pxEm('date', '🕓') . ' ' . h(pxJalali()) . '</blockquote>';
    return $t;
}

/** کارت یک ارز — همان قالب روشن، با رنگ اختصاصی خودش */
function pxCoinCard($sym, $priceShown, $unit, $chg, $seriesBase = null) {
    $bg = pxCoinColors($sym);
    $name = pxCoinName($sym);
    return pxAssetCard($name, '●', $priceShown, $unit, $chg, $bg,
                       pxSeries($seriesBase ?? $priceShown, $chg, 110));
}

/** نام نمایشی یک نماد — اگر فارسی‌اش را بلد باشیم */
function pxCoinName($sym) {
    $sym = strtoupper((string)$sym);
    $fa = array_flip(array_map('strtoupper', pxCoinMap()));
    $names = [
        'BTC' => 'بیت کوین', 'ETH' => 'اتریوم', 'USDT' => 'تتر', 'TON' => 'تون کوین',
        'TRX' => 'ترون', 'BNB' => 'بایننس کوین', 'SOL' => 'سولانا', 'XRP' => 'ریپل',
        'DOGE' => 'دوج کوین', 'ADA' => 'کاردانو', 'NOT' => 'نات کوین', 'SHIB' => 'شیبا',
        'AVAX' => 'آواکس', 'LINK' => 'چین لینک', 'DOT' => 'پولکادات', 'MATIC' => 'ماتیک',
        'LTC' => 'لایت کوین', 'PEPE' => 'پپه', 'ATOM' => 'کازماس', 'NEAR' => 'نیر',
        'XLM' => 'استلار', 'UNI' => 'یونی سواپ',
    ];
    return $names[$sym] ?? $sym;
}

/**
 * پیام را می‌بیند و اگر مربوط به قیمت بود، جواب می‌دهد.
 * برگشت true یعنی رسیدگی شد و بقیه‌ی ربات نباید دستش بزند.
 */
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

    // «۵۰۰ استارز» — عدد + کلمه
    if (preg_match('/^\s*[\d۰-۹٠-٩\.,]+\s*(.+)$/u', $raw, $m)) {
        $k2 = pxWordKind(trim($m[1]));
        if ($k2 === 'stars') {
            $t = pxStarsText(pxStarsCount($raw));
            sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? $kb : null,
                    $replyTo ? ['reply_to_message_id' => $replyTo] : []);
            return true;
        }
    }

    // 🥇 دارایی‌های ایرانی — طلا، دلار، سکه
    $ak = pxAssetOf($raw);
    if ($ak !== null) {
        $a = pxAssets()[$ak];
        $price = pxAssetPrice($ak);
        if ($price <= 0) {
            sendMsg(BOT_TOKEN, $chatId,
                '⚠️ قیمت «' . h($a['name']) . '» در منبع فعلی نیست.' . "\n\n" .
                'کلید <code>' . h($a['pair']) . '</code> در پاسخ API پیدا نشد. ' .
                'از پنل ← 💹 قیمت ← 🔎 کلیدهای API ببینید چه کلیدهایی می‌آید و درستش را ست کنید.',
                null, $replyTo ? ['reply_to_message_id' => $replyTo] : []);
            return true;
        }
        $chg = pxAssetChange($ak);
        $ck  = 'a|' . $ak . '|' . $price . '|' . $chg;
        // کارت فقط وقتی ساخته می‌شود که شناسه‌ی فایلش را نداشته باشیم —
        // وگرنه اصلا رندر هم لازم نیست
        $png = (maCacheGet(pxPhotoIdKey($ck), 86400) ?? '') !== ''
             ? null
             : pxCardCached($ck, fn() => pxAssetCard($a['name'], $a['emoji'], $price, $a['unit'], $chg, $a['bg']));
        pxDeliver($chatId, $png,
            pxAssetCaption($a['name'], $price, $a['unit'], $chg, $a['emoji'] ?? '', $ak),
            $kb, $replyTo, $ck);
        return true;
    }

    // 💱 تبدیل — «۵۰ تتر به تومان»، «۱۰۰ افغانی»، «2 sol به usdt»
    $cv = pxConvert($raw);
    if ($cv !== null) {
        [$amount, $from, $to] = $cv;

        // مبدا یکی از دارایی‌هاست؟ («۱۰۰ افغانی»)
        $srcAsset = pxAssetOfWord($cv[3] ?? '');
        if ($srcAsset !== null) {
            $unitPrice = pxAssetPrice($srcAsset);
            if ($unitPrice > 0) {
                $as  = pxAssets()[$srcAsset];
                $val = $amount * $unitPrice;
                $chg = pxAssetChange($srcAsset);
                $ttl = pxNum($amount) . ' ' . $as['name'];
                // معادل دلاری، وقتی مبلغ تومانی است
                $usdEq = null;
                if ($as['unit'] === 'تومان') {
                    $d = pxUsdtIrt();
                    if ($d > 0) $usdEq = $val / $d;
                }
                $png = pxTryCard(fn() => pxAssetCard($ttl, $as['emoji'], $val, $as['unit'], $chg,
                                                     $as['bg'], pxSeries($val, $chg, 110)));
                pxDeliver($chatId, $png,
                    pxConvAssetCaption($ttl, $val, $as['unit'], $usdEq, $chg), $kb, $replyTo);
                return true;
            }
        }
        $p = pxFetch();
        $irt = (float)($p['USDT/IRT'] ?? 0);
        $fromUsd = ($from === 'USDT') ? 1.0 : (float)($p[$from . '/USDT'] ?? 0);
        if ($fromUsd > 0 && $irt > 0) {
            if ($to === 'IRT')          { $val = $amount * $fromUsd * $irt; $unit = 'تومان'; }
            elseif ($to === 'USDT')     { $val = $amount * $fromUsd;        $unit = 'دلار'; }
            else {
                $toUsd = (float)($p[$to . '/USDT'] ?? 0);
                if ($toUsd <= 0) return false;
                $val = $amount * $fromUsd / $toUsd; $unit = $to;
            }
            $chg = pxChangeOf($from . '/USDT');
            // معادل دلاری همیشه نوشته می‌شود — حتی وقتی خروجی تومان است
            $usdEq = ($unit === 'دلار') ? null : $amount * $fromUsd;
            // رنگِ قالب از خودِ ارزِ مبدا می‌آید، پس هر ارز رنگ خودش را دارد
            $png = pxTryCard(fn() => pxAssetCard(pxNum($amount) . ' ' . pxCoinName($from), '●',
                                                 $val, $unit, $chg, pxCoinColors($from),
                                                 pxSeries($val, $chg, 110)));
            pxDeliver($chatId, $png,
                pxConvCaption($amount, $from, $val, $unit, $usdEq, $chg), $kb, $replyTo);
            return true;
        }
    }

    // نماد یک ارز
    $key = mb_strtolower($raw);
    $sym = pxCoinMap()[$key] ?? strtoupper($raw);
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $sym)) return false;

    $p = pxFetch();
    $irtRate = (float)($p['USDT/IRT'] ?? 0);
    $usd = ($sym === 'USDT') ? 1.0 : (float)($p[$sym . '/USDT'] ?? 0);
    if ($usd <= 0 || $irtRate <= 0) return false;      // نماد ناشناخته — بگذار بقیه رسیدگی کنند

    $chg = pxChangeOf($sym . '/USDT');
    $hi  = (float)($p[$sym . '/HIGH24'] ?? 0);
    $lo  = (float)($p[$sym . '/LOW24'] ?? 0);
    $cap = pxCoinCaption($sym, $usd, $usd * $irtRate, $chg, $hi, $lo);

    // قیمت تومانی روی کارت — چون مخاطب ایرانی است
    $ck  = 'c|' . $sym . '|' . round($usd * $irtRate) . '|' . $chg;
    $png = (maCacheGet(pxPhotoIdKey($ck), 86400) ?? '') !== ''
         ? null
         : pxCardCached($ck, fn() => pxCoinCard($sym, $usd * $irtRate, 'تومان', $chg, $usd * $irtRate));
    pxDeliver($chatId, $png, $cap, $kb, $replyTo, $ck);
    return true;
}

/** درصد تغییر ۲۴ ساعت، اگر API بدهد */
function pxChangeOf($pair) {
    $p = pxFetch();
    $pair = strtoupper((string)$pair);
    $base = explode('/', $pair)[0];

    // ۱) اگر خود API درصد داد، همان معتبرترین است
    foreach ([$pair . '/CHANGE24', $base . '/CHANGE24', $base . '/CHG', $base . '/CHANGE'] as $k) {
        if (isset($p[$k]) && (float)$p[$k] != 0.0) return (float)$p[$k];
    }

    // ۲) وگرنه از تاریخچه‌ی خودمان حساب کن — این همان چیزی است که
    //    نمی‌گذارد همه‌ی کارت‌ها همیشه «۰٪» بمانند
    $now = (float)($p[$pair] ?? 0);
    $old = pxHistAgo($pair);
    if ($now > 0 && $old !== null && $old > 0) {
        $pct = (($now - $old) / $old) * 100;
        if (abs($pct) < 200) return round($pct, 2);      // جهش‌های بی‌معنی را نپذیر
    }
    return 0.0;
}

/** کپشن کارت دارایی */
/**
 * کپشن طلا، دلار، سکه و پول کشورها.
 * تاریخ داخل همان نقل‌قول می‌نشیند، نه بیرونش.
 */
function pxAssetCaption($name, $price, $unit, $chg, $emoji = '', $key = '') {
    $head = pxHeadEmoji($key, $emoji);
    $isT  = ($unit === 'تومان');

    $t  = $head . ' <b>' . h($name) . "</b>\n\n";
    // قیمت و درصد با هم، ولی تاریخ در نقل‌قولِ خودش
    $t .= '<blockquote>';
    // واحد نوشته نمی‌شود — ایموجی خودش می‌گوید تومان است یا دلار
    $t .= ($isT ? pxEm('toman', '💰') : pxEm('usd', '💵')) . ' ' .
          pxToman($price) . ($isT ? '' : ' ' . h($unit)) . "\n";
    $t .= pxEm('chg', '📈') . ' ' . ($chg >= 0 ? '+' : '−') .
          number_format(abs($chg), 2) . '%';
    $t .= '</blockquote>' . "\n";
    $t .= pxDateLine();
    return $t;
}

/**
 * ایموجی سرِ قالب.
 * طلا و سکه ایموجی خودشان را دارند، دلار و ارزهای تومانی مالِ خودشان،
 * و بقیه همان ایموجیِ خودِ دارایی.
 */
function pxHeadEmoji($key, $fallback = '') {
    $key = (string)$key;
    if (in_array($key, ['gold', 'gold24', 'ounce', 'coin', 'nim', 'rob'], true))
        return pxEm('gold', $fallback !== '' ? $fallback : '🥇');
    if ($key === 'usd') return pxEm('usd', $fallback !== '' ? $fallback : '💵');
    return $fallback !== '' ? $fallback : pxEm('conv', '🪙');
}

/** کپشن تبدیل */
function pxConvCaption($amount, $from, $val, $unit, $usdVal = null, $chg = null) {
    $t  = pxEm('conv', '💱') . ' <b>' . h(pxNum($amount) . ' ' . pxCoinName($from)) . "</b>\n\n";
    $t .= pxConvBody($val, $unit, $usdVal, $chg);
    return $t;
}

// ============================================================
// 👑 پنل مدیریت — قیمت لحظه‌ای
// ============================================================

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

/** نام فارسی کلیدها — تا در پنل معلوم باشد هرکدام چیست */
function pxLabels() {
    return [
        // متن‌ها
        'prem_head'  => 'سرتیتر پریمیوم',
        'prem_month' => 'برچسب هر پلن پریمیوم',
        'prem_off'   => 'برچسب تخفیف پریمیوم',
        'star_head'  => 'سرتیتر استارز',
        'rates_head' => 'سرتیتر نرخ ارز',
        'coin_head'  => 'سرتیتر هر ارز',
        'hl_head'    => 'سرتیتر بیشترین و کمترین',
        'foot'       => 'امضای پایین پیام',
        'toman'      => 'واژه‌ی تومان',
        'dollar'     => 'واژه‌ی دلار',
        'ton'        => 'واژه‌ی تون',
        'down'       => 'پیام وقتی قیمت نمی‌آید',
        'nocoin'     => 'پیام نماد ناشناخته',
        'prem_full'  => '📝 قالب کامل پیام پریمیوم',
        'star_full'  => '📝 قالب کامل پیام استارز',
        // ایموجی‌ها
        'frag'  => '🔻 ایموجی فرگمنت (پایین قالب)',
        'card'  => 'ایموجی سرتیتر',
        'price' => 'ایموجی قیمت',
        'prem'  => 'ایموجی پریمیوم',
        'star'  => 'ایموجی استارز',
        'coin'  => 'ایموجی ارز و ساعت',
        'chart' => 'ایموجی نمودار',
        'date'  => '🕓 ایموجی تاریخ',
        'gold'  => '🥇 ایموجی سرِ قالب طلا',
        'usd'   => '💵 ایموجی دلار',
        'toman' => '💰 ایموجی تومان',
        'chg'   => '📈 ایموجی درصد تغییرات',
        'conv'  => '💱 ایموجی سرِ قالب تبدیل',
        // کلمه‌ها
        'premium' => 'کلمه‌های پریمیوم',
        'stars'   => 'کلمه‌های استارز',
        'rates'   => 'کلمه‌های نرخ ارز',
    ];
}

function pxLabel($k) { return pxLabels()[$k] ?? $k; }

/** فهرست ویرایش یک بخش (متن‌ها، ایموجی‌ها، کلمه‌ها) */
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

    // 📝 دو قالبِ کامل، جدا و بالا — چون همین دو تا هستند که کل پیام را
    //    عوض می‌کنند و بقیه فقط تکه‌های ریزند.
    if ($kind === 'pxt') {
        $pf = trim((string)($c['texts']['prem_full'] ?? ''));
        $sf = trim((string)($c['texts']['star_full'] ?? ''));
        $t .= "🧩 <b>قالب کامل</b> — پرش کنید و کل پیام دقیقا همان می‌شود:\n";
        $t .= '   ' . ($pf !== '' ? '✅' : '⚪️') . " پریمیوم: " .
              ($pf !== '' ? '<code>' . h(mb_substr(str_replace("\n", ' ⏎ ', $pf), 0, 40)) . '</code>' : 'خالی (قالب خودکار)') . "\n";
        $t .= '   ' . ($sf !== '' ? '✅' : '⚪️') . " استارز: " .
              ($sf !== '' ? '<code>' . h(mb_substr(str_replace("\n", ' ⏎ ', $sf), 0, 40)) . '</code>' : 'خالی (قالب خودکار)') . "\n\n";
        $t .= "داخل قالب می‌توانید ایموجی پرمیوم و <code>&lt;blockquote&gt;</code> بگذارید.\n";
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

    foreach ((array)$c[$sec] as $k => $v) {
        if ($kind === 'pxt' && in_array($k, ['prem_full', 'star_full'], true)) continue;
        $show = mb_substr(str_replace("\n", ' ', (string)$v), 0, 30);
        $t .= '• <b>' . h(pxLabel($k)) . '</b>: <code>' . h($show) . "</code>\n";
        $rows[] = [btnCb(pxLabel($k), $pre . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3900), inlineKb($rows));
}

/**
 * قالب‌های قدیمیِ نمونه — با واژه‌های فارسی کنار عددها.
 * اگر ادمین دقیقا همین‌ها را ذخیره کرده باشد (یعنی دست‌نخورده از دکمه‌ی
 * «نمونه‌ی آماده» آمده)، خودمان کنارشان می‌گذاریم تا قالب خودکارِ تازه
 * — که ایموجی پریمیوم دارد و واژه‌ی فارسی ندارد — کار کند.
 * قالبی که خود ادمین نوشته باشد هیچ‌وقت دست نمی‌خورد.
 */
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

/**
 * نمونه‌ی آماده‌ی قالب کامل — همان شکلی که پسندیده شد:
 * هر مقدار داخل quote با ایموجی پریمیوم خودش، بدون واژه‌ی فارسی.
 */
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

/**
 * قالب کاملی که دقیقا برابر نمونه‌ی قدیمی است را پاک می‌کند.
 * یک بار اجرا می‌شود و اثرش می‌ماند.
 */
function pxDropOldDemo() {
    foreach (['prem' => 'prem_full', 'star' => 'star_full'] as $which => $key) {
        $cur = trim((string)pxT($key));
        if ($cur !== '' && $cur === trim(pxOldDemoTpl($which)))
            pxSet(function (&$c) use ($key) { $c['texts'][$key] = ''; });
    }
}

/** دکمه‌های زیر پیام قیمت */
function pxAdminButtons($chatId, $msgId) {
    $bs = (array)pxVal('buttons', []);
    $t = "🔘 <b>دکمه‌های زیر پیام قیمت</b>\n\n";
    $t .= "دکمه بدون لینک نشان داده نمی‌شود.\n";
    $t .= "✨ برای ایموجی پریمیوم کار جدایی لازم نیست: موقع نوشتن متن دکمه، " .
          "همان ایموجی را جلوی متن بگذارید — خودش برداشته می‌شود.\n\n";
    $rows = [];
    foreach ($bs as $i => $b) {
        $t .= ($i + 1) . ') ' . (!empty($b['on']) ? '✅' : '❌') . ' <b>' . h($b['text']) . "</b>\n";
        $t .= '   🔗 ' . ($b['url'] !== '' ? '<code>' . h($b['url']) . '</code>' : '—') . "\n";
        if (trim((string)($b['icon'] ?? '')) !== '')
            $t .= '   ✨ ایموجی پریمیوم: <code>' . h($b['icon']) . "</code>\n";
        $rows[] = [
            btnCb(!empty($b['on']) ? '✅' : '❌', 'pxbx_' . $i, 'info'),
            btnCb('✏️ متن', 'pxbt_' . $i, 'admin'),
            btnCb('🔗 لینک', 'pxbu_' . $i, 'admin'),
            btnCb('🎨 رنگ', 'pxbc_' . $i, 'info'),
        ];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

/** 🥇 طلا، دلار، سکه — نام، کلید API، کلمه‌ها */
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

/** 🖼 کارت گرافیکی — چرا می‌سازد یا نمی‌سازد، و فونتش از کجاست */
function pxAdminCard($chatId, $msgId) {
    $why = pxCardWhy();
    $t  = "🖼 <b>کارت گرافیکی قیمت</b>\n\n";
    $t .= 'وضعیت: ' . (!empty(pxVal('card.on')) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'GD: ' . (function_exists('imagecreatetruecolor') ? '✅ هست' : '🔴 نیست') . "\n";
    $t .= 'نوشتن متن (FreeType): ' . (function_exists('imagettftext') ? '✅ هست' : '🔴 نیست') . "\n";
    $f = pxFont(true);
    $t .= 'فونت: ' . ($f !== '' ? '✅ <code>' . h($f) . '</code>' : '🔴 پیدا نشد') . "\n\n";

    if ($why !== '') {
        $t .= "⚠️ <b>به همین دلیل به‌جای کارت، متن فرستاده می‌شود:</b>\n" . h($why) . "\n\n";
    } else {
        $t .= "همه‌چیز آماده است. با دکمه‌ی پایین یک نمونه ببینید.\n\n";
    }
    $t .= "💡 اگر فونت پیدا نشد، کافی است یک فایل <code>.ttf</code> برای ربات بفرستید.";

    $rows = [
        [btnCb(!empty(pxVal('card.on')) ? '✅ کارت روشن است' : '❌ کارت خاموش است', 'pxc', 'info')],
        [btnCb('🔤 فرستادن فونت', 'pxfont', 'admin')],
    ];
    if (trim((string)pxVal('card.font_bold', '')) !== '' || trim((string)pxVal('card.font', '')) !== '')
        $rows[] = [btnCb('🧹 پاک کردن فونتِ دستی', 'pxfontclr', 'danger')];
    $rows[] = [btnCb('👀 نمونه‌ی کارت', 'pxprev_card', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

/** 🩺 تشخیص — دقیقا بگو کدام منبع زنده است و هر قیمت از کجا آمده */
/**
 * 🩺 هر منبعِ قیمت را جداگانه بسنج.
 *
 * برگشت: [['name','ok','n','ms','err'], …]
 * زمان‌ها واقعی‌اند — همان چیزی که کاربر پشتش منتظر می‌ماند.
 */
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

    $api = trim((string)$c['api']);
    if ($api !== '') {
        $t0 = microtime(true);
        [$j, $e] = pxGetMany(['api' => ['url' => $api, 'timeout' => $to,
            'head' => 'x-api-key: ' . trim((string)$c['key']) . "\nAccept: application/json"]], $to)['api']
            ?? [null, 'گرفته نشد'];
        $ms = (int)round((microtime(true) - $t0) * 1000);
        $p  = is_array($j) ? pxSwapPairs($j) : [];
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

    // 🏦 هر منبع را جدا بسنج — وگرنه «کار می‌کند» چیزی نمی‌گوید:
    //    شاید فقط یکی‌شان زنده باشد و آن یکی هر بار پشتِ تایم‌اوت بماند.
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

/** برگشت true یعنی این callback مال بخش قیمت بود */
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
    // 🏦 منبعِ والکس: روشن/خاموش
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
        $png = pxTryCard(fn() => pxAssetCard('نمونه', '💠', 1234567, 'تومان', 2.5,
                                             ['3B82F6', '1E3A8A'], pxSeries(1234567, 2.5, 110)));
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
    if ($data === 'pxfont') {
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'px_font', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🔤 <b>فرستادن فونت</b>\n\n" .
            "یک فایل <code>.ttf</code> همین‌جا بفرستید (به‌شکل فایل، نه عکس).\n" .
            "همان‌جا ذخیره می‌شود و کارت‌ها از همان لحظه ساخته می‌شوند.\n\n" .
            "💡 فونت DejaVu Sans یا Roboto یا Vazirmatn هرکدام خوب است.",
            inlineKb([[btnCb('انصراف', 'pxcard', 'cancel')]]));
        return true;
    }
    if ($data === 'pxfontclr') {
        pxSet(function (&$c) { $c['card']['font'] = ''; $c['card']['font_bold'] = ''; });
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

    // 🔎 دقیقا چه کلیدهایی از API می‌آید — تا طلا و سکه را درست ست کنند
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

    // ویرایش یک دارایی
    foreach (['pxan_' => ['px_asname', 'نام فارسی'], 'pxap_' => ['px_aspair', 'کلید جفت‌ارز در API'],
              'pxaw_' => ['px_aswords', 'کلمه‌هایی که این دارایی را صدا می‌زنند (با ویرگول)'],
              'pxau_' => ['px_asunit', 'واحد (مثلا تومان)']] as $pre => [$act, $label]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        $a = pxAssets()[$k] ?? null;
        if (!$a) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['k' => $k]);
        $cur = ['px_asname' => $a['name'], 'px_aspair' => $a['pair'],
                'px_aswords' => $a['words'], 'px_asunit' => $a['unit']][$act] ?? '';
        sendMsg(BOT_TOKEN, $chatId,
            '✏️ ' . $label . " را بفرستید.\n\nالان: <code>" . h((string)$cur) . '</code>',
            inlineKb([[btnUI('cancel', 'pxa_home', 'cancel')]]));
        return true;
    }

    // روشن/خاموش یک دکمه
    if (str_starts_with($data, 'pxbx_')) {
        $i = (int)substr($data, 5);
        pxSet(function (&$c) use ($i) {
            if (isset($c['buttons'][$i])) $c['buttons'][$i]['on'] = empty($c['buttons'][$i]['on']) ? 1 : 0;
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); pxAdminButtons($chatId, $msgId); return true;
    }
    // رنگ دکمه
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

    // ورودی متنی
    $asks = [
        'pxk'   => ['px_key',  "🔑 کلید API را بفرستید:"],
        'pxu'   => ['px_url',  "🌐 آدرس API قیمت را بفرستید:"],
        'pxwu'  => ['px_wxurl',
            "🏦 آدرس منبع والکس را بفرستید.\n\nپیش‌فرض:\n<code>https://api.wallex.ir/v1/markets</code>\n\n" .
            "کلید نمی‌خواهد و تومان را مستقیم می‌دهد."],
        'pxm'   => ['px_marg', "📊 درصد سود روی نرخ بازار (۰ = دقیقا نرخ بازار):"],
        'pxttl' => ['px_ttl',  "⏱ چند ثانیه قیمت کش شود؟ (پیشنهاد ۱۵)"],
        'pxalturl' => ['px_alturl',
            "📡 آدرس منبع ایرانی (طلا و سکه).\n\nمی‌توانید چند آدرس بدهید، هرکدام در یک خط — " .
            "اولی که جواب بدهد استفاده می‌شود."],
        'pxfxurl'  => ['px_fxurl',
            "🌍 آدرس نرخ برابری ارز.\n\nچند آدرس، هرکدام در یک خط. باید JSON با کلید " .
            "<code>rates</code> بدهد، مثل <code>open.er-api.com</code>."],
        'pxgk' => ['px_goldk',
            "🥇 ضریب طلا.\n\nاگر گرم طلای محاسبه‌شده با بازار چند درصد فرق داشت، اینجا " .
            "تنظیمش کنید. ۱ یعنی بدون تغییر، ۱٫۰۳ یعنی ۳٪ بالاتر."],
        'pxck' => ['px_coink',
            "🪙 حباب سکه.\n\nنسبت قیمت سکه به ارزش طلای داخلش. ۱٫۱۲ یعنی ۱۲٪ حباب."],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'px_home', 'cancel')]]));
        return true;
    }
    foreach (['pxts_' => ['px_text', 'texts'], 'pxes_' => ['px_emoji', 'emoji'],
              'pxws_' => ['px_word', 'words']] as $pre => [$act, $sec]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        $cur = (string)(pxVal($sec . '.' . $k) ?? '');
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['k' => $k]);
        $hint = ($sec === 'emoji')
            ? "\n\nیک پیام با همان ایموجی پریمیوم بفرستید، یا کد عددی‌اش را."
            : (($sec === 'words') ? "\n\nچند کلمه را با ویرگول جدا کنید." : '');
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ <b>" . h(pxLabel($k)) . "</b> را بفرستید." . $hint . "\n\nالان:\n<code>" .
            h(mb_substr($cur, 0, 500)) . '</code>',
            inlineKb([[btnUI('cancel', 'px_home', 'cancel')]]));
        return true;
    }
    foreach (['pxbt_' => ['px_btntext', 'text'], 'pxbu_' => ['px_btnurl', 'url']] as $pre => [$act, $f]) {
        if (!str_starts_with($data, $pre)) continue;
        $i = (int)substr($data, strlen($pre));
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['i' => $i]);
        $ask = ($f === 'url')
            ? "🔗 لینک دکمه را بفرستید (خط تیره = پاک کردن):"
            : "✏️ متن دکمه را بفرستید.\n\n" .
              "✨ اگر ایموجی پریمیوم می‌خواهید، همان را جلوی متن بگذارید — خودش برداشته می‌شود.";
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'px_home', 'cancel')]]));
        return true;
    }
    return false;
}

/** گرفتن مقدار متنی — برگشت true یعنی رسیدگی شد */
function pxStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'px_')) return false;
    if ($uid !== ADMIN_ID) return false;

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
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل فونت را به‌شکل <b>فایل</b> بفرستید، نه عکس.");
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
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل از تلگرام گرفته نشد. دوباره بفرستید.");
            return true;
        }
        [$bytes, $err] = maHttpRaw(TG_API_BASE . '/file/bot' . BOT_TOKEN . '/' . $path, 30);
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
        $png = pxTryCard(fn() => pxAssetCard('نمونه', '💠', 1234567, 'تومان', 2.5,
                                             ['3B82F6', '1E3A8A'], pxSeries(1234567, 2.5, 110)));
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
        // کش و قفلِ «تازه شکست خورد» را باز کن تا همین حالا امتحان شود
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
                "⚠️ ایموجی پیدا نشد. یک پیام با همان ایموجی بفرستید، یا کد عددی‌اش را.\n" .
                "برای برداشتنش خط تیره بفرستید.");
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

        // قالب کامل را «همان‌طور که تایپ شد» نگه می‌داریم: ایموجی پریمیوم،
        // بولد، نقل‌قول و quote بازشونده به HTML تبدیل می‌شوند، نه اینکه
        // به متن خشک تبدیل شوند و از دست بروند.
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
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        // ✨ ایموجی پریمیومی که جلوی متن گذاشته، خودکار برداشته می‌شود
        $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $icon = $ids ? (string)$ids[0] : '';
        pxSet(function (&$c) use ($i, $text, $icon) {
            if (!isset($c['buttons'][$i])) return;
            $c['buttons'][$i]['text'] = $text;
            $c['buttons'][$i]['icon'] = $icon;      // نبود؟ یعنی برداشته شود
        });
        return $done($icon !== ''
            ? '✅ متن ذخیره شد و ایموجی پریمیوم هم خودکار برداشته شد.'
            : '✅ متن ذخیره شد.');
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

// ============================================================
// 🔤 نوشتن فارسی روی تصویر
//
// GD حروف فارسی را نه به هم می‌چسباند و نه راست‌به‌چپ می‌نویسد؛
// «طلا» می‌شود «ا ل ط» جدا از هم. پس خودمان هر حرف را به شکل
// درستش (آغازی/میانی/پایانی/تنها) تبدیل می‌کنیم و ترتیب را
// برمی‌گردانیم. عددها و لاتین سرِ جای خودشان می‌مانند.
// ============================================================

/** ch => [تنها, پایانی, آغازی, میانی] — صفر یعنی آن شکل وجود ندارد */
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
        // ویژه‌ی فارسی
        'پ' => [0xFB56, 0xFB57, 0xFB58, 0xFB59],
        'چ' => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D],
        'ژ' => [0xFB8A, 0xFB8B, 0, 0],
        'ک' => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91],
        'گ' => [0xFB92, 0xFB93, 0xFB94, 0xFB95],
        'ی' => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF],
    ];
    return $t;
}

/** لام + الف یک نشانه‌ی واحد می‌شود */
function pxLamAlef() {
    return ['آ' => [0xFEF5, 0xFEF6], 'أ' => [0xFEF7, 0xFEF8],
            'إ' => [0xFEF9, 0xFEFA], 'ا' => [0xFEFB, 0xFEFC]];
}

function pxIsArabic($ch) {
    $c = mb_ord($ch, 'UTF-8');
    return $c !== false && (($c >= 0x0600 && $c <= 0x06FF) || ($c >= 0xFB50 && $c <= 0xFEFF));
}

/** رقم فارسی/عربی هم «عدد» است و نباید برعکس شود */
function pxIsDigitCh($ch) {
    $c = mb_ord($ch, 'UTF-8');
    return $c !== false && (($c >= 0x30 && $c <= 0x39) ||
                            ($c >= 0x0660 && $c <= 0x0669) || ($c >= 0x06F0 && $c <= 0x06F9));
}

/**
 * متن فارسی را به شکلی درمی‌آورد که GD درست بکشد:
 * حروف چسبیده، و ترتیب برعکس‌شده برای راست‌به‌چپ.
 */
function pxShape($text) {
    $text = (string)$text;
    if ($text === '') return '';

    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (!$chars) return $text;

    $tbl = pxArabicTable();
    $lam = pxLamAlef();

    // ── ۱) هر حرف را به شکل درستش ──
    $out = [];
    $n = count($chars);
    for ($i = 0; $i < $n; $i++) {
        $ch = $chars[$i];
        if (!isset($tbl[$ch])) { $out[] = $ch; continue; }

        $prev = null;
        for ($k = $i - 1; $k >= 0; $k--) { if ($chars[$k] !== "\u{200C}") { $prev = $chars[$k]; break; } }
        $next = null;
        for ($k = $i + 1; $k < $n; $k++) { if ($chars[$k] !== "\u{200C}") { $next = $chars[$k]; break; } }

        // حرف قبلی می‌تواند به جلو بچسبد؟ (یعنی شکل آغازی/میانی دارد)
        $joinBefore = $prev !== null && isset($tbl[$prev]) && $tbl[$prev][2] !== 0;
        $joinAfter  = $next !== null && isset($tbl[$next]);

        // لام + الف
        if ($ch === 'ل' && $next !== null && isset($lam[$next])) {
            $pair = $lam[$next];
            $out[] = mb_chr($joinBefore ? $pair[1] : $pair[0], 'UTF-8');
            $i++;                      // الف را مصرف کردیم
            continue;
        }

        $f = $tbl[$ch];
        if ($joinBefore && $joinAfter && $f[3] !== 0)      $g = $f[3];   // میانی
        elseif ($joinBefore && $f[1] !== 0)                $g = $f[1];   // پایانی
        elseif ($joinAfter && $f[2] !== 0)                 $g = $f[2];   // آغازی
        else                                               $g = $f[0];   // تنها
        $out[] = mb_chr($g, 'UTF-8');
    }

    // ── ۲) ترتیب: راست‌به‌چپ، ولی عدد و لاتین سرِ جایشان ──
    // فاصله دسته‌ی خودش است؛ اگر به یکی از دو طرف بچسبد، موقع برعکس
    // کردن یک طرف دو فاصله می‌گیرد و طرف دیگر هیچ.
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

/** نوشتن متن فارسی — همان pxText ولی با شکل‌دهی */
function pxTextFa($im, $size, $x, $y, $color, $text, $bold = true) {
    pxText($im, $size, $x, $y, $color, pxShape($text), $bold);
}

function pxTextFaW($size, $text, $bold = true) {
    return pxTextW($size, pxShape($text), $bold);
}

// ============================================================
// 🖼 کارت روشن — سبک طلا و دلار
// ============================================================

/**
 * دارایی‌هایی که کارت اختصاصی دارند.
 * هرکدام: نام فارسی، ایموجی، کلید جفت‌ارز در API، واحد، و رنگ پس‌زمینه.
 * از پنل هم می‌شود کم و زیادشان کرد.
 */
/**
 * دارایی‌هایی که کارت اختصاصی دارند.
 *
 * هرکدام دو راه برای قیمت دارند:
 *   pair — کلیدی در همان API اصلی (اگر داشته باشدش)
 *   path — مسیری در «منبع دوم» (tgju)، وقتی API اصلی طلا و ارز کشورها را نمی‌دهد
 * اولی مقدم است؛ نبود، دومی. div برای ریال→تومان است.
 */
function pxAssetsDefault() {
    $g = ['F5A524', 'C2410C'];
    return [
        'usd'  => ['name' => 'دلار آمریکا', 'emoji' => '🇺🇸', 'pair' => 'USDT/IRT', 'code' => 'USD',
                   'path' => 'current.price_dollar_rl.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['B22234', '3C3B6E'], 'words' => 'دلار,دلار آمریکا,usd'],
        'gold' => ['name' => 'طلا ۱۸ عیار', 'emoji' => '🥇', 'pair' => '',
                   'path' => 'current.geram18.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $g, 'words' => 'طلا,طلا ۱۸ عیار,طلای ۱۸,gold'],
        'gold24'=> ['name' => 'طلا ۲۴ عیار', 'emoji' => '🥇', 'pair' => '',
                   'path' => 'current.geram24.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => $g, 'words' => 'طلا ۲۴,طلای ۲۴,gold24'],
        'ounce'=> ['name' => 'انس جهانی طلا', 'emoji' => '🥇', 'pair' => '',
                   'path' => 'current.ons.p', 'div' => 1,
                   'unit' => 'دلار', 'bg' => $g, 'words' => 'انس,انس طلا,ounce'],
        'coin' => ['name' => 'سکه امامی', 'emoji' => '🪙', 'pair' => '',
                   'path' => 'current.sekee.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['EAB308', '92400E'], 'words' => 'سکه,سکه امامی,coin'],
        'nim'  => ['name' => 'نیم سکه', 'emoji' => '🪙', 'pair' => '',
                   'path' => 'current.nim.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['EAB308', '92400E'], 'words' => 'نیم سکه,نیم'],
        'rob'  => ['name' => 'ربع سکه', 'emoji' => '🪙', 'pair' => '',
                   'path' => 'current.rob.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['EAB308', '92400E'], 'words' => 'ربع سکه,ربع'],

        // ── پول کشورهای دیگر ──
        'eur'  => ['name' => 'یورو', 'emoji' => '🇪🇺', 'pair' => '', 'code' => 'EUR',
                   'path' => 'current.price_eur.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['003399', '001A4D'], 'words' => 'یورو,eur'],
        'aed'  => ['name' => 'درهم امارات', 'emoji' => '🇦🇪', 'pair' => '', 'code' => 'AED',
                   'path' => 'current.price_aed.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['00732F', '111111'], 'words' => 'درهم,درهم امارات,aed'],
        'try'  => ['name' => 'لیر ترکیه', 'emoji' => '🇹🇷', 'pair' => '', 'code' => 'TRY',
                   'path' => 'current.price_try.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['E30A17', '7A0509'], 'words' => 'لیر,لیر ترکیه,ترکیه,try'],
        'sar'  => ['name' => 'ریال عربستان', 'emoji' => '🇸🇦', 'pair' => '', 'code' => 'SAR',
                   'path' => 'current.price_sar.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['006C35', '00381B'], 'words' => 'ریال عربستان,عربستان,ریال سعودی,sar'],
        'pkr'  => ['name' => 'روپیه پاکستان', 'emoji' => '🇵🇰', 'pair' => '', 'code' => 'PKR',
                   'path' => 'current.price_pkr.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['01411C', '00250F'], 'words' => 'روپیه,روپیه پاکستان,پاکستان,pkr'],
        'afn'  => ['name' => 'افغانی', 'emoji' => '🇦🇫', 'pair' => '', 'code' => 'AFN',
                   'path' => 'current.price_afn.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['000000', '4A0E0E'], 'words' => 'افغانی,افغانستان,afn'],
        'gbp'  => ['name' => 'پوند انگلیس', 'emoji' => '🇬🇧', 'pair' => '', 'code' => 'GBP',
                   'path' => 'current.price_gbp.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['012169', '000B26'], 'words' => 'پوند,پوند انگلیس,gbp'],
        'iqd'  => ['name' => 'دینار عراق', 'emoji' => '🇮🇶', 'pair' => '', 'code' => 'IQD',
                   'path' => 'current.price_iqd.p', 'div' => 10,
                   'unit' => 'تومان', 'bg' => ['CE1126', '5C0810'], 'words' => 'دینار,دینار عراق,عراق,iqd'],
    ];
}

function pxAssets() {
    $def   = pxAssetsDefault();
    $saved = pxVal('assets', null);
    if (!is_array($saved) || !$saved) return $def;

    // دارایی‌های تازه‌ی نسخه‌های بعدی هم باید ظاهر شوند، نه اینکه
    // پیکربندی قدیمی برای همیشه فهرست را قفل کند.
    $out = $def;
    foreach ($saved as $k => $a) {
        if (!is_array($a)) continue;
        $base = $def[$k] ?? ['name' => $k, 'emoji' => '💠', 'pair' => '', 'code' => '', 'path' => '',
                             'div' => 1, 'unit' => 'تومان', 'bg' => ['334155', '0F172A'], 'words' => ''];
        $out[$k] = array_replace($base, $a);
    }
    return $out;
}

/**
 * رنگ اختصاصی هر ارز.
 * چند نماد شناخته‌شده رنگ خودشان را دارند؛ بقیه از روی خودِ نماد
 * یک رنگ ثابت می‌گیرند — پس هیچ ارزی بی‌رنگ نمی‌ماند و فهرست
 * هیچ‌وقت تمام نمی‌شود.
 */
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

    // رنگ ثابت از روی نماد — همیشه یک ارز، یک رنگ
    $h = crc32($sym);
    $hue = $h % 360;
    return [pxHsl($hue, 62, 48), pxHsl($hue, 66, 24)];
}

/** HSL → RRGGBB */
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

/** «طلا» → کلید دارایی، یا null */
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

/** رنگ را با سفید رقیق می‌کند — تینتِ توپر، بدون آلفا */
function pxTint($hex, $pct, $onto = 'FFFFFF') {
    [$r, $g, $b]    = pxHex($hex);
    [$r2, $g2, $b2] = pxHex($onto);
    $t = max(0.0, min(1.0, (float)$pct));
    return sprintf('%02X%02X%02X',
        (int)round($r2 + ($r - $r2) * $t),
        (int)round($g2 + ($g - $g2) * $t),
        (int)round($b2 + ($b - $b2) * $t));
}

/**
 * 📡 منبع دوم — طلا، سکه و پول کشورها.
 * API اصلی فقط ارز دیجیتال می‌دهد؛ این‌ها از جای دیگری می‌آیند.
 * آدرس و مسیرها از پنل قابل عوض کردن‌اند تا اگر منبع عوض شد، کد دست‌نخورده بماند.
 */
/**
 * منبع دوم (طلا، سکه، ارز کشورها).
 * چند آدرس پشت سر هم امتحان می‌شود؛ اولی که جواب داد برنده است و
 * تا پایان TTL همان می‌ماند. یک آدرس از کار افتاده کل بخش را نمی‌خواباند.
 */
function pxAltFetch($fresh = false) {
    static $mem = null;
    if (!$fresh && is_array($mem)) return $mem;

    $urls = pxUrlList(pxVal('alt_url', ''));
    if (!$urls) return $mem = [];

    if (!$fresh && (pxNoNet() || (function_exists('maNoNet') && maNoNet()))) {
        $any = maCacheGet('px_alt', 0);
        if (is_array($any) && $any) return $mem = $any;
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
            '', (int)pxVal('timeout', 6));
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
    return $mem = (array)(maCacheGet('px_alt', 0) ?: []);
}

/** یک رشته‌ی چندخطی/کاما‌دار را به فهرست آدرس تمیز تبدیل می‌کند */
function pxUrlList($raw) {
    $out = [];
    foreach (preg_split('/[\r\n,|]+/', (string)$raw) as $u) {
        $u = trim($u);
        if ($u !== '' && preg_match('#^https?://#i', $u)) $out[] = $u;
    }
    return $out;
}

function pxHost($u) { return (string)(parse_url($u, PHP_URL_HOST) ?: $u); }

/**
 * 🌍 نرخ برابری ارزها نسبت به دلار — «چند واحد از این پول، برابر ۱ دلار».
 * سه آدرس رایگان و بی‌کلید، پشت سر هم. خروجی: ['EUR'=>0.92, 'TRY'=>34.1, …]
 */
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

    $errs = [];
    foreach ($urls as $u) {
        [$j, $err] = maHttp($u, 'GET', "Accept: application/json", '', (int)pxVal('timeout', 6));
        // هر سه API شکل خروجی خودشان را دارند؛ هرکدام که بود بردار
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

/** یک اونس تروی، به گرم */
const PX_OUNCE_G = 31.1034768;

/**
 * 🧮 ساختن قیمت از روی چیزی که همیشه در دسترس است.
 *
 * API ارز دیجیتال روی سرور شما کار می‌کند (چون پریمیوم و استارز درست
 * درمی‌آیند)، پس طلا و ارز هم باید از همان بیرون بیایند و منتظر منبع
 * ایرانی نمانند:
 *
 *   انس طلا      = PAXG یا XAUT  (هرکدام دقیقا یک انس طلای واقعی است)
 *   دلار تومانی  = USDT/IRT
 *   طلای ۲۴      = انس ÷ ۳۱٫۱ × دلار
 *   طلای ۱۸      = طلای ۲۴ × ۰٫۷۵
 *   سکه          = طلای ۲۴ × ۸٫۱۳۳ گرم × ۰٫۹۰۰ عیار × حباب
 *   پول کشورها   = دلار تومانی ÷ نرخ آن پول به ازای ۱ دلار
 *
 * ۰ یعنی حتی این هم نشد.
 */
function pxDerive($key, $fresh = false) {
    if (empty(pxVal('derive', 1))) return 0.0;

    $usd = pxUsdtIrt($fresh);                    // تومان به ازای ۱ دلار
    if ($key === 'usd') return $usd;

    $ounce = 0.0;
    foreach (['PAXG/USDT', 'XAUT/USDT'] as $pair) {
        $v = pxPair($pair, $fresh);
        if ($v > 0) { $ounce = $v; break; }
    }
    if ($key === 'ounce') return $ounce;

    if ($usd <= 0) return 0.0;

    $k24 = ($ounce > 0) ? ($ounce / PX_OUNCE_G * $usd) : 0.0;   // تومان، هر گرم طلای ۲۴
    $gk  = (float)pxVal('gold_k', 1.0); if ($gk <= 0) $gk = 1.0;

    switch ($key) {
        case 'gold24': return $k24 * $gk;
        case 'gold':   return $k24 * 0.750 * $gk;
        case 'coin':   return $k24 * 8.133 * 0.900 * $gk * max(0.5, (float)pxVal('coin_k', 1.12));
        case 'nim':    return $k24 * 4.066 * 0.900 * $gk * max(0.5, (float)pxVal('coin_k', 1.12));
        case 'rob':    return $k24 * 2.033 * 0.900 * $gk * max(0.5, (float)pxVal('coin_k', 1.12));
    }

    // پول کشورها
    $a    = pxAssets()[$key] ?? null;
    $code = strtoupper(trim((string)($a['code'] ?? '')));
    if ($code === '' || $code === 'USD') return 0.0;
    $fx = pxFxFetch($fresh);
    $per = (float)($fx[$code] ?? 0);              // چند واحد از این پول = ۱ دلار
    return $per > 0 ? $usd / $per : 0.0;
}

/** قیمت یک دارایی — اول API اصلی، بعد منبع دوم. ۰ یعنی هیچ‌کدام نداشتند. */
function pxAssetPrice($key, $fresh = false) {
    $a = pxAssets()[$key] ?? null;
    if (!$a) return 0.0;

    // ۱) API اصلی
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

    // ۲) منبع دوم
    $path = trim((string)($a['path'] ?? ''));
    if ($path !== '') {
        $v = maJsonPath(pxAltFetch($fresh), $path);
        $n = maNum($v);
        if ($n > 0) {
            $div = max(1e-9, (float)($a['div'] ?? 1));
            return $n / $div;
        }
    }

    // ۳) هیچ‌کدام نبود؟ از روی انس و دلارِ همان API ارز دیجیتال بساز.
    //    این همان چیزی است که نمی‌گذارد «طلا» و «دلار» بی‌جواب بمانند.
    return max(0.0, pxDerive($key, $fresh));
}

/** قیمت آمد، ولی از کجا؟ — برای صفحه‌ی تشخیص پنل */
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

/** درصد تغییر یک دارایی از منبع دوم (اگر داشت) */
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
        // tgju درصد را در همان گره، کلید dp می‌گذارد
        $dp = maJsonPath(pxAltFetch(), substr($path, 0, -2) . '.dp');
        if ($dp !== null) {
            $n = maNum($dp);
            $dt = (string)maJsonPath(pxAltFetch(), substr($path, 0, -2) . '.dt');
            return ($dt === 'low' || $dt === 'down') ? -abs($n) : $n;
        }
    }

    // درصدِ قیمت‌های محاسبه‌شده: طلا از انس، پول کشورها از دلار.
    // بدون این، همه‌ی کارت‌های ساخته‌شده همیشه «۰٪» نشان می‌دادند.
    if (in_array($key, ['gold', 'gold24', 'ounce', 'coin', 'nim', 'rob'], true)) {
        foreach (['PAXG', 'XAUT'] as $sym) {
            $c = pxChangeOf($sym . '/USDT');
            if ($c != 0.0) return $c;
        }
        return 0.0;
    }
    return pxChangeOf('USDT/IRT');
}

/** گرادیان عمودی ساده */
function pxGradient($im, $x1, $y1, $x2, $y2, $hexA, $hexB) {
    [$r1, $g1, $b1] = pxHex($hexA);
    [$r2, $g2, $b2] = pxHex($hexB);
    $h = max(1, $y2 - $y1);
    for ($y = 0; $y <= $h; $y++) {
        $t = $y / $h;
        $c = imagecolorallocate($im,
            (int)round($r1 + ($r2 - $r1) * $t),
            (int)round($g1 + ($g2 - $g1) * $t),
            (int)round($b1 + ($b2 - $b1) * $t));
        imageline($im, $x1, $y1 + $y, $x2, $y1 + $y, $c);
    }
}

/** خط نرم از میان نقطه‌ها — گوشه‌های تیز نمودار را گرد می‌کند */
function pxSmooth($data, $passes = 2) {
    $d = array_values(array_map('floatval', $data));
    $n = count($d);
    if ($n < 3) return $d;
    for ($p = 0; $p < $passes; $p++) {
        $o = $d;
        for ($i = 1; $i < $n - 1; $i++) $d[$i] = ($o[$i - 1] + 2 * $o[$i] + $o[$i + 1]) / 4;
    }
    return $d;
}

/**
 * کارت روشن یک دارایی — همان چیدمانی که فرستادید.
 * برگشت: بایت‌های PNG یا null.
 */
function pxAssetCard($name, $emoji, $price, $unit, $chgPct, $bg = ['334155', '0F172A'], $series = null) {
    if (!pxCardReady()) return null;

    $W = 1200; $H = 675;
    $up = $chgPct >= 0;

    $im = imagecreatetruecolor($W, $H);
    imagealphablending($im, true);
    imageantialias($im, true);

    $C = function ($hex, $a = 0) use ($im) {
        [$r, $g, $b] = pxHex($hex);
        return imagecolorallocatealpha($im, $r, $g, $b, $a);
    };

    // پس‌زمینه‌ی رنگی دارایی
    pxGradient($im, 0, 0, $W, $H, $bg[0] ?? '334155', $bg[1] ?? '0F172A');

    // کارت سفید با سایه‌ی نرم
    $cx1 = 78; $cy1 = 62; $cx2 = $W - 78; $cy2 = $H - 96;
    pxSoftShadow($im, $cx1, $cy1, $cx2, $cy2, 34);
    pxRoundRect($im, $cx1, $cy1, $cx2, $cy2, 34, $C('F7F7F8'));

    $ink   = $C('101114');
    $muted = $C('9AA0A6');
    $line  = $C('E6E7EA');
    $accHex = $up ? '16A34A' : 'DC2626';
    $acc    = $C($accHex);

    // ── نشان IRT، بالا-چپ ──
    $tagW = pxTextW(21, 'IRT') + 46;
    pxRoundRect($im, $cx1 + 34, $cy1 + 40, $cx1 + 34 + $tagW, $cy1 + 92, 24, $C('EBECEF'));
    pxText($im, 21, $cx1 + 34 + 23, $cy1 + 76, $C('6B7280'), 'IRT');

    // ── نام دارایی، بالا-راست ──
    $pad = 40;
    $right = $cx2 - $pad;
    $emo = trim((string)$emoji);
    $nameW = pxTextFaW(32, $name);
    pxTextFa($im, 32, $right - $nameW, $cy1 + 82, $ink, $name);
    if ($emo !== '') {
        // ایموجی را فونت نمی‌کشد؛ یک دایره‌ی رنگی به‌جایش
        $ec = $C($bg[0] ?? '334155');
        $ex = $right - $nameW - 36; $ey = $cy1 + 70;
        imagefilledellipse($im, $ex, $ey, 38, 38, $C(pxTint($bg[0] ?? '334155', 0.18, 'F7F7F8')));
        imagefilledellipse($im, $ex, $ey, 22, 22, $ec);
    }

    // ── قیمت بزرگ ──
    // عددهای بزرگ رند، عددهای کوچک با اعشار — «۲٫۸۱ دلار» نباید «۳» شود
    $ps = ((float)$price >= 1000) ? number_format((float)$price) : pxNum($price);

    // اگر عدد بلند باشد (مثلا بیت‌کوین به تومان) فونت کوچک‌تر می‌شود
    // تا از کارت بیرون نزند.
    $uw   = pxTextFaW(26, $unit);
    $room = ($right - ($cx1 + 34 + $tagW + 24)) - $uw - 26;
    $fs = 64;
    while ($fs > 30 && pxTextW($fs, $ps) > $room) $fs -= 2;
    $pw = pxTextW($fs, $ps);
    pxText($im, $fs, $right - $pw, $cy1 + 176, $ink, $ps);
    pxTextFa($im, 26, $right - $pw - 26 - $uw, $cy1 + 172, $muted, $unit);

    // ── درصد و فلش ──
    $pct = ($up ? '+' : '-') . number_format(abs((float)$chgPct), 2) . '%';
    $pcw = pxTextW(23, $pct);
    $px2 = $right;
    $px1 = $px2 - $pcw - 46;
    $soft = $C(pxTint($accHex, 0.13, 'F7F7F8'));
    pxRoundRect($im, $px1, $cy1 + 206, $px2, $cy1 + 258, 24, $soft);
    pxText($im, 23, $px1 + 23, $cy1 + 243, $acc, $pct);
    // دایره‌ی فلش
    $ax = $px1 - 42;
    imagefilledellipse($im, $ax, $cy1 + 232, 46, 46, $soft);
    $dir = $up ? -1 : 1;
    imagesetthickness($im, 4);
    foreach ([-7, 3] as $off) {
        imageline($im, $ax - 10, $cy1 + 232 + $off * $dir, $ax, $cy1 + 232 + ($off + 7) * $dir, $acc);
        imageline($im, $ax, $cy1 + 232 + ($off + 7) * $dir, $ax + 10, $cy1 + 232 + $off * $dir, $acc);
    }
    imagesetthickness($im, 1);

    // ── نمودار ──
    $gx1 = $cx1 + 150; $gx2 = $cx2 - 34;
    $gy1 = $cy1 + 300; $gy2 = $cy2 - 34;
    $data = pxSmooth(is_array($series) && count($series) > 5 ? $series
                                                            : pxSeries($price, $chgPct, 110), 3);
    $n = count($data);
    $min = min($data); $max = max($data);
    if ($max - $min < 1e-9) $max = $min + 1;
    $lo = $min - ($max - $min) * 0.35;
    $hi = $max + ($max - $min) * 0.18;

    // خط‌های افقی و عددهای محور
    for ($i = 0; $i <= 4; $i++) {
        $yy = (int)($gy2 - ($gy2 - $gy1) * $i / 4);
        imageline($im, $gx1, $yy, $gx2, $yy, $line);
        $v = $lo + ($hi - $lo) * $i / 4;
        pxText($im, 15, $cx1 + 34, $yy + 6, $muted, pxCompact($v), false);
    }

    $fx = function ($i) use ($gx1, $gx2, $n) { return $gx1 + ($gx2 - $gx1) * $i / max(1, $n - 1); };
    $fy = function ($v) use ($gy1, $gy2, $lo, $hi) {
        return $gy2 - ($gy2 - $gy1) * (($v - $lo) / max(1e-9, $hi - $lo));
    };

    // سایه‌ی زیر خط — نوار‌به‌نوار، هر نوار رنگ خودش را دارد.
    // نسخه‌ی قبلی چند چندضلعی روی هم می‌کشید و راه‌راه می‌افتاد.
    $bands = 56;
    for ($bnd = 0; $bnd < $bands; $bnd++) {
        $yTop = $gy1 + ($gy2 - $gy1) * $bnd / $bands;
        $yBot = $gy1 + ($gy2 - $gy1) * ($bnd + 1) / $bands;
        // بالا پررنگ‌تر، پایین محوتر
        $col = $C(pxTint($accHex, 0.30 * (1 - $bnd / $bands) + 0.05, 'F7F7F8'));
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = (int)$fx($i); $x2 = (int)$fx($i + 1);
            $yc = min($fy($data[$i]), $fy($data[$i + 1]));
            $top = max($yTop, $yc);
            if ($top < $yBot) imagefilledrectangle($im, $x1, (int)$top, $x2 + 1, (int)$yBot, $col);
        }
    }

    imagesetthickness($im, 5);
    for ($i = 1; $i < $n; $i++)
        imageline($im, (int)$fx($i - 1), (int)$fy($data[$i - 1]), (int)$fx($i), (int)$fy($data[$i]), $acc);
    imagesetthickness($im, 1);

    // نقطه‌ی آخر
    $lx = (int)$fx($n - 1); $ly = (int)$fy($data[$n - 1]);
    imagefilledellipse($im, $lx, $ly, 20, 20, $acc);
    imagefilledellipse($im, $lx, $ly, 9, 9, $C('FFFFFF'));

    // ── امضا ──
    $wm = trim((string)(cfg()['bot_username'] ?? ''));
    $wm = $wm !== '' ? '@' . $wm : 'Live Market';
    pxText($im, 20, (int)(($W - pxTextW(20, $wm)) / 2), $H - 34, $C('FFFFFF', 40), $wm);

    return pxPngOut($im);
}

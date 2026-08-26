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

        // منبع قیمت
        'api' => 'https://swapwallet.app/api/v1/market/prices',
        'key' => 'apikey-h8T5ufE73fILlDudXnPJp6CRYV9PSMKviBB0SxCXCAOzSFneGcBHaUa19am2kTIU',
        'ttl' => 15,          // ثانیه — قیمت لحظه‌ای یعنی کش کوتاه
        'timeout' => 6,
        'cooldown' => 120,    // بعد از شکست، این مدت سراغ شبکه نرو

        // ایموجی پریمیوم — با /emoji در ربات کدشان را می‌گیرید
        'emoji' => [
            'card'  => '5343902037438391058',
            'price' => '5841359408952513916',
            'prem'  => '5899945812296731931',
            'star'  => '4936468614967460670',
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
function pxFetch($fresh = false) {
    static $mem = null;
    if (!$fresh && is_array($mem)) return $mem;        // در همین درخواست، یک بار

    $c = pxCfg();
    $ck = 'px_pairs';
    if (!$fresh) {
        $hit = maCacheGet($ck, (int)$c['ttl']);
        if (is_array($hit)) return $mem = $hit;

        // 🐢 همین چند لحظه پیش شکست خورده؟ دوباره پشت تایم‌اوت نایست
        if (maCacheGet('px_cool', (int)$c['cooldown']) !== null)
            return $mem = (array)(maCacheGet($ck, 0) ?: []);
    }

    $url = trim((string)$c['api']);
    if ($url === '') return $mem = [];

    [$j, $err] = maHttp($url, 'GET', 'x-api-key: ' . trim((string)$c['key']) .
                        "\nAccept: application/json", '', (int)$c['timeout']);
    if (!is_array($j)) {
        maCachePut('px_err', $err ?: 'پاسخی نیامد');
        maCachePut('px_cool', time());
        return $mem = (array)(maCacheGet($ck, 0) ?: []);   // کش قدیمی بهتر از هیچ
    }

    // بعضی پاسخ‌ها داخل result بسته‌بندی می‌شوند
    $rows = (isset($j['result']) && is_array($j['result'])) ? $j['result'] : $j;

    $out = [];
    foreach ($rows as $k => $v) {
        if (!is_string($k) || !str_contains($k, '/')) continue;
        if (!is_scalar($v)) continue;
        // درصد تغییر می‌تواند منفی باشد؛ فیلترِ «بزرگ‌تر از صفر» همه‌ی
        // ریزش‌ها را دور می‌ریخت و کارت‌ها همیشه ۰٪ نشان می‌دادند.
        $raw = norm_fa_digits((string)$v);
        $raw = str_replace([',', '،', '٬', ' '], '', $raw);
        if (!is_numeric($raw)) continue;
        $out[strtoupper($k)] = (float)$raw;
    }
    if (!$out) {
        maCachePut('px_err', 'پاسخ آمد ولی هیچ جفت‌ارزی نداشت');
        maCachePut('px_cool', time());
        return $mem = (array)(maCacheGet($ck, 0) ?: []);
    }

    maCachePut('px_err', '');
    maCachePut('px_cool', 0);
    maCachePut($ck, $out);
    return $mem = $out;
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
function pxQuote($irt, $usd, $ton, $expandable = false) {
    $e = pxEm('price', '💵');
    $t  = '<blockquote' . ($expandable ? ' expandable' : '') . '>';
    $t .= $e . ' ' . pxToman($irt) . ' ' . h(pxT('toman')) . "\n";
    $t .= $e . ' ' . pxNum($usd) . ' ' . h(pxT('dollar'));
    if ($ton !== null) $t .= "\n" . $e . ' ' . pxNum($ton) . ' ' . h(pxT('ton'));
    $t .= '</blockquote>';
    return $t;
}

function pxPremiumText($fresh = false) {
    $rows = pxPremiumRows($fresh);
    if (!$rows) return null;

    $t = pxEm('prem', '⭐️') . ' <b>' . h(pxT('prem_head')) . "</b>\n\n";
    foreach ($rows as $months => $d) {
        $label = pxT('prem_month', ['n' => $months]);
        if ($d['off'] > 0) $label .= ' — ' . pxT('prem_off', ['off' => $d['off']]);
        $t .= pxEm('prem', '💎') . ' <b>' . h($label) . "</b>\n";
        $t .= pxQuote($d['irt'], $d['usd'], $d['ton'], true) . "\n";
    }
    $t .= pxEm('card', '🔻') . ' <b>' . h(pxT('foot')) . "</b>\n";
    $t .= pxEm('coin', '🕓') . ' <code>' . h(pxJalali()) . '</code>';
    return $t;
}

function pxStarsText($n = 1, $fresh = false) {
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
            $lines[] = pxEm('star', '✨') . ' <b>' . number_format($p) . '</b> — ' .
                       pxToman($d['irt']) . ' ' . h(pxT('toman')) . ' · ' .
                       pxNum($d['ton']) . ' ' . h(pxT('ton'));
        }
        $t .= implode("\n", $lines) . '</blockquote>' . "\n";
    }

    $t .= pxEm('card', '🔻') . ' <b>' . h(pxT('foot')) . "</b>\n";
    $t .= pxEm('coin', '🕓') . ' <code>' . h(pxJalali()) . '</code>';
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
        $t .= pxEm('price', '💵') . ' <b>' . h($sym) . "</b>\n";
        $t .= '<blockquote>' . pxToman($usd * $irt) . ' ' . h(pxT('toman')) .
              "\n$" . pxNum($usd) . '</blockquote>';
    }
    $t .= "\n" . pxEm('coin', '🕓') . ' <code>' . h(pxJalali()) . '</code>';
    return $t;
}

/** متن زیر کارت یک ارز */
function pxCoinCaption($sym, $usd, $irt, $chg, $hi, $lo, $n = 1) {
    $t  = pxEm('coin', '🪙') . ' <b>' . h(pxT('coin_head', ['n' => pxNum($n), 'sym' => $sym])) . "</b>\n\n";
    $t .= pxEm('price', '💵') . ' ' . pxToman($irt) . ' ' . h(pxT('toman')) . "\n";
    $t .= pxEm('price', '💲') . ' $' . pxNum($usd) . "\n";
    $t .= ($chg >= 0 ? '🟢' : '🔴') . ' ' . number_format(abs($chg), 2) . "%\n\n";

    if ($hi > 0 && $lo > 0) {
        $t .= pxEm('chart', '📊') . ' <b>' . h(pxT('hl_head')) . "</b>\n";
        $t .= '<blockquote expandable>' .
              pxToman($hi * ($usd > 0 ? $irt / $usd : 0)) . ' / ' .
              pxToman($lo * ($usd > 0 ? $irt / $usd : 0)) . ' ' . h(pxT('toman')) . "\n" .
              pxNum($hi) . ' / ' . pxNum($lo) . ' ' . h(pxT('dollar')) . '</blockquote>' . "\n";
    }
    $t .= pxEm('coin', '🕓') . ' <code>' . h(pxJalali()) . '</code>';
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
function pxFont($bold = true) {
    static $cache = [];
    $k = $bold ? 'b' : 'r';
    if (isset($cache[$k])) return $cache[$k];
    $try = $bold
        ? ['/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
           '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
           __DIR__ . '/fonts/Roboto-Bold.ttf', 'C:\\Windows\\Fonts\\arialbd.ttf']
        : ['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
           '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
           __DIR__ . '/fonts/Roboto-Regular.ttf', 'C:\\Windows\\Fonts\\arial.ttf'];
    foreach ($try as $f) if (is_file($f)) return $cache[$k] = $f;
    return $cache[$k] = '';
}

function pxCardReady() {
    return function_exists('imagecreatetruecolor')
        && function_exists('imagettftext')
        && pxFont(true) !== '';
}

/** #RRGGBB → [r,g,b] */
function pxHex($hex) {
    $hex = ltrim((string)$hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
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
    $sigma = max(abs((float)$chgPct) / 100, 0.012) / sqrt($n);
    $out = array_fill(0, $n, $last);
    for ($i = $n - 2; $i >= 0; $i--) {
        $u1 = max(1e-9, mt_rand(1, 1000000) / 1000000);
        $u2 = mt_rand(1, 1000000) / 1000000;
        $z  = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
        $out[$i] = $out[$i + 1] / exp($sigma * $z);
    }
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

    ob_start();
    imagepng($im, null, 6);
    $bytes = ob_get_clean();
    imagedestroy($im);
    return $bytes;
}

/** فرستادن عکس با کپشن — tg() فقط فرم ساده می‌فرستد، پس اینجا خودمان */
function pxSendPhoto($chatId, $bytes, $caption, $markup = null, $replyTo = null) {
    if (function_exists('__tgHook'))
        return __tgHook(BOT_TOKEN, 'sendPhoto',
            ['chat_id' => $chatId, 'caption' => $caption, 'photo_len' => strlen((string)$bytes)]);

    $tmp = tempnam(sys_get_temp_dir(), 'pxc') . '.png';
    file_put_contents($tmp, $bytes);

    $post = [
        'chat_id' => $chatId,
        'caption' => $caption,
        'parse_mode' => 'HTML',
        'photo' => new CURLFile($tmp, 'image/png', 'chart.png'),
    ];
    if ($markup) $post['reply_markup'] = json_encode($markup);
    if ($replyTo) $post['reply_to_message_id'] = $replyTo;

    $ch = curl_init(TG_API_BASE . '/bot' . BOT_TOKEN . '/sendPhoto');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    @unlink($tmp);
    $out = json_decode((string)$res, true);
    return is_array($out) ? $out : ['ok' => false, 'description' => 'bad response'];
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

    if (!preg_match('/^[A-Z0-9]{2,10}$/', $from)) return null;
    if ($to !== 'IRT' && !preg_match('/^[A-Z0-9]{2,10}$/', $to)) return null;
    return [$amount, $from, $to];
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
        $chg = pxChangeOf($a['pair']);
        $png = !empty(pxVal('card.on'))
             ? pxAssetCard($a['name'], $a['emoji'], $price, $a['unit'], $chg, $a['bg'])
             : null;
        $cap = pxAssetCaption($a['name'], $price, $a['unit'], $chg);
        if ($png !== null) pxSendPhoto($chatId, $png, $cap, $kb, $replyTo);
        else sendMsg(BOT_TOKEN, $chatId, $cap, $kb, $replyTo ? ['reply_to_message_id' => $replyTo] : []);
        return true;
    }

    // 💱 تبدیل — «۵۰ تتر به تومان» یا فقط «۵۰ تتر»
    $cv = pxConvert($raw);
    if ($cv !== null) {
        [$amount, $from, $to] = $cv;
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
            $png = !empty(pxVal('card.on'))
                 ? pxAssetCard(pxNum($amount) . ' ' . pxCoinName($from), '●', $val, $unit, $chg,
                               pxCoinColors($from), pxSeries($val, $chg, 110))
                 : null;
            $cap = pxConvCaption($amount, $from, $val, $unit);
            if ($png !== null) pxSendPhoto($chatId, $png, $cap, $kb, $replyTo);
            else sendMsg(BOT_TOKEN, $chatId, $cap, $kb, $replyTo ? ['reply_to_message_id' => $replyTo] : []);
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

    if (!empty(pxVal('card.on'))) {
        // قیمت تومانی روی کارت — چون مخاطب ایرانی است
        $png = pxCoinCard($sym, $usd * $irtRate, 'تومان', $chg, $usd * $irtRate);
        if ($png !== null) {
            pxSendPhoto($chatId, $png, $cap, $kb, $replyTo);
            return true;
        }
    }
    sendMsg(BOT_TOKEN, $chatId, $cap, $kb, $replyTo ? ['reply_to_message_id' => $replyTo] : []);
    return true;
}

/** درصد تغییر ۲۴ ساعت، اگر API بدهد */
function pxChangeOf($pair) {
    $p = pxFetch();
    $base = explode('/', strtoupper((string)$pair))[0];
    foreach ([$pair . '/CHANGE24', $base . '/CHANGE24', $base . '/CHG', $base . '/CHANGE'] as $k) {
        if (isset($p[$k])) return (float)$p[$k];
    }
    return 0.0;
}

/** کپشن کارت دارایی */
function pxAssetCaption($name, $price, $unit, $chg) {
    $t  = pxEm('coin', '🪙') . ' <b>' . h($name) . "</b>\n\n";
    $t .= '<blockquote>' . pxEm('price', '💵') . ' ' . pxToman($price) . ' ' . h($unit) . "\n" .
          ($chg >= 0 ? '🟢' : '🔴') . ' ' . number_format(abs($chg), 2) . '%</blockquote>' . "\n";
    $t .= pxEm('coin', '🕓') . ' <code>' . h(pxJalali()) . '</code>';
    return $t;
}

/** کپشن تبدیل */
function pxConvCaption($amount, $from, $val, $unit) {
    // تومان رند می‌شود، ولی «۲٫۸۱ دلار» نباید بشود «۳ دلار»
    $shown = ($unit === 'تومان') ? pxToman($val) : pxNum($val);
    $t  = pxEm('card', '💱') . ' <b>' . h(pxNum($amount) . ' ' . pxCoinName($from)) . "</b>\n\n";
    $t .= '<blockquote>' . pxEm('price', '💵') . ' ' . $shown . ' ' . h($unit) . '</blockquote>' . "\n";
    $t .= pxEm('coin', '🕓') . ' <code>' . h(pxJalali()) . '</code>';
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
        [btnCb('🔑 کلید API', 'pxk', 'admin'), btnCb('🌐 آدرس API', 'pxu', 'admin')],
        [btnCb('📊 درصد سود', 'pxm', 'admin'), btnCb('⏱ ثانیه کش', 'pxttl', 'admin')],
        [btnCb('🗣 کلمه‌ها', 'pxw_home', 'admin'), btnCb('✏️ متن‌ها', 'pxt_home', 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'pxe_home', 'admin'), btnCb('🔘 دکمه‌ها', 'pxb_home', 'admin')],
        [btnCb(!empty($c['card']['on']) ? '🖼 کارت: روشن' : '🖼 کارت: خاموش', 'pxc', 'info')],
        [btnCb('🥇 طلا، دلار، سکه', 'pxa_home', 'admin'),
         btnCb('🔎 کلیدهای API', 'pxkeys', 'confirm')],
        [btnCb('👀 پیش‌نمایش پریمیوم', 'pxprev_prem', 'confirm'),
         btnCb('👀 استارز', 'pxprev_star', 'confirm')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

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
    if ($kind === 'pxt') $t .= "جای‌گذاری‌ها: <code>{n}</code> <code>{sym}</code> <code>{off}</code>\n\n";

    $rows = [];
    foreach ((array)$c[$sec] as $k => $v) {
        $show = mb_substr((string)$v, 0, 34);
        $t .= '• <b>' . h($k) . '</b>: <code>' . h($show) . "</code>\n";
        $rows[] = [btnCb($k, $pre . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

/** دکمه‌های زیر پیام قیمت */
function pxAdminButtons($chatId, $msgId) {
    $bs = (array)pxVal('buttons', []);
    $t = "🔘 <b>دکمه‌های زیر پیام قیمت</b>\n\n";
    $t .= "دکمه بدون لینک نشان داده نمی‌شود.\n";
    $t .= "برای ایموجی پریمیوم، با /emoji کدش را بگیرید یا همان‌جا یک پیام حاوی آن ایموجی بفرستید.\n\n";
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
        ];
        $rows[] = [
            btnCb('🎨 رنگ: ' . (string)($b['color'] ?? '—'), 'pxbc_' . $i, 'info'),
            btnCb('✨ ایموجی پریمیوم', 'pxbi_' . $i, 'admin'),
        ];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

/** 🥇 طلا، دلار، سکه — نام، کلید API، کلمه‌ها */
function pxAdminAssets($chatId, $msgId) {
    $t = "🥇 <b>طلا، دلار، سکه</b>\n\n";
    $t .= "هرکدام یک «کلید جفت‌ارز» دارد که باید در پاسخ API وجود داشته باشد.\n";
    $t .= "با 🔎 کلیدهای API ببینید چه چیزی می‌آید.\n\n";
    $rows = [];
    foreach (pxAssets() as $k => $a) {
        $v = pxAssetPrice($k);
        $t .= ($v > 0 ? '✅ ' : '⚠️ ') . '<b>' . h($a['name']) . "</b>\n";
        $t .= '   کلید: <code>' . h($a['pair']) . '</code>' .
              ($v > 0 ? ' — ' . pxToman($v) . ' ' . h($a['unit']) : ' — <b>پیدا نشد</b>') . "\n";
        $t .= '   کلمه‌ها: <code>' . h($a['words']) . "</code>\n\n";
        $rows[] = [btnCb('✏️ ' . mb_substr($a['name'], 0, 14), 'pxan_' . $k, 'admin'),
                   btnCb('🔑 کلید', 'pxap_' . $k, 'admin'),
                   btnCb('🗣 کلمه‌ها', 'pxaw_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'px_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
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
    if ($data === 'pxprev_prem' || $data === 'pxprev_star') {
        answerCb(BOT_TOKEN, $cbId);
        $t = $data === 'pxprev_prem' ? pxPremiumText(true) : pxStarsText(1, true);
        sendMsg(BOT_TOKEN, $chatId, $t ?? pxT('down'), $t ? pxKeyboard() : null);
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
        'pxm'   => ['px_marg', "📊 درصد سود روی نرخ بازار (۰ = دقیقا نرخ بازار):"],
        'pxttl' => ['px_ttl',  "⏱ چند ثانیه قیمت کش شود؟ (پیشنهاد ۱۵)"],
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
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ مقدار تازه‌ی <b>" . h($k) . "</b> را بفرستید.\n\nالان:\n<code>" .
            h(mb_substr($cur, 0, 500)) . '</code>',
            inlineKb([[btnUI('cancel', 'px_home', 'cancel')]]));
        return true;
    }
    foreach (['pxbt_' => ['px_btntext', 'text'], 'pxbu_' => ['px_btnurl', 'url'],
              'pxbi_' => ['px_btnicon', 'icon']] as $pre => [$act, $f]) {
        if (!str_starts_with($data, $pre)) continue;
        $i = (int)substr($data, strlen($pre));
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['i' => $i]);
        $ask = [
            'url'  => "🔗 لینک دکمه را بفرستید (خط تیره = پاک کردن):",
            'text' => "✏️ متن دکمه را بفرستید:",
            'icon' => "✨ یک پیام با همان ایموجی پریمیوم بفرستید، یا کد عددی‌اش را.\n\n" .
                      "خط تیره = برداشتن ایموجی",
        ][$f];
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
        if ($k === '' || $text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        pxSet(function (&$c) use ($sec, $k, $text) { $c[$sec][$k] = $text; });
        return $done();
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

    if ($action === 'px_btnicon') {
        $i = (int)($sd['i'] ?? -1);
        // یا از خودِ پیام برش می‌داریم، یا کد عددی‌ای که تایپ کرده
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $v = $blank ? '' : ($ids ? (string)$ids[0] : preg_replace('/\D/', '', norm_fa_digits($text)));
        // متنی فرستاده که نه ایموجی بود نه عدد؟ نباید بی‌صدا پاکش کنیم —
        // «خط تیره» تنها راه پاک کردن است.
        if (!$blank && $v === '') {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ایموجی پریمیوم پیدا نشد.\n\n" .
                "یا یک پیام بفرستید که همان ایموجی داخلش باشد، یا کد عددی‌اش را.\n" .
                "برای برداشتن ایموجی، خط تیره بفرستید.");
            return true;
        }
        pxSet(function (&$c) use ($i, $v) { if (isset($c['buttons'][$i])) $c['buttons'][$i]['icon'] = $v; });
        return $done($v !== '' ? '✅ ایموجی پریمیوم دکمه ثبت شد: <code>' . h($v) . '</code>'
                               : '✅ ایموجی دکمه برداشته شد.');
    }
    if ($action === 'px_btntext' || $action === 'px_btnurl') {
        $i = (int)($sd['i'] ?? -1);
        $f = $action === 'px_btnurl' ? 'url' : 'text';
        $v = $blank ? '' : $text;
        if ($f === 'url' && $v !== '' && !preg_match('#^https?://#i', $v)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک باید با https شروع شود."); return true;
        }
        if ($f === 'text' && $v === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        pxSet(function (&$c) use ($i, $f, $v) { if (isset($c['buttons'][$i])) $c['buttons'][$i][$f] = $v; });
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
function pxAssetsDefault() {
    return [
        'usd'  => ['name' => 'دلار آمریکا', 'emoji' => '🇺🇸', 'pair' => 'USDT/IRT',
                   'unit' => 'تومان', 'bg' => ['B22234', '3C3B6E'], 'words' => 'دلار,دلار آمریکا,usd'],
        'gold' => ['name' => 'طلا ۱۸ عیار', 'emoji' => '🥇', 'pair' => 'GOLD18/IRT',
                   'unit' => 'تومان', 'bg' => ['F5A524', 'C2410C'], 'words' => 'طلا,طلا ۱۸ عیار,gold,طلای ۱۸'],
        'coin' => ['name' => 'سکه امامی', 'emoji' => '🪙', 'pair' => 'COIN/IRT',
                   'unit' => 'تومان', 'bg' => ['EAB308', '92400E'], 'words' => 'سکه,سکه امامی,coin'],
    ];
}

function pxAssets() {
    $saved = pxVal('assets', null);
    if (!is_array($saved)) return pxAssetsDefault();
    $out = [];
    foreach ($saved as $k => $a) {
        if (!is_array($a)) continue;
        $out[$k] = array_replace(pxAssetsDefault()[$k] ?? [
            'name' => $k, 'emoji' => '💠', 'pair' => '', 'unit' => 'تومان',
            'bg' => ['334155', '0F172A'], 'words' => '',
        ], $a);
    }
    return $out ?: pxAssetsDefault();
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

/** قیمت یک دارایی (طلا، دلار، سکه) — ۰ یعنی کلیدش در API نیست */
function pxAssetPrice($key, $fresh = false) {
    $a = pxAssets()[$key] ?? null;
    if (!$a) return 0.0;
    $pair = strtoupper(trim((string)($a['pair'] ?? '')));
    if ($pair === '') return 0.0;
    $p = pxFetch($fresh);
    if (isset($p[$pair])) return (float)$p[$pair];
    // شاید فقط به دلار داده شده — به تومان بیاورش
    if (str_ends_with($pair, '/IRT')) {
        $usdPair = substr($pair, 0, -4) . '/USDT';
        $irt = (float)($p['USDT/IRT'] ?? 0);
        if (isset($p[$usdPair]) && $irt > 0) return (float)$p[$usdPair] * $irt;
    }
    return 0.0;
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
    for ($i = 14; $i >= 1; $i--)
        pxRoundRect($im, $cx1 - $i, $cy1 - $i + 5, $cx2 + $i, $cy2 + $i + 5, 34 + $i, $C('000000', 126));
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

    ob_start();
    imagepng($im, null, 6);
    $bytes = ob_get_clean();
    imagedestroy($im);
    return $bytes;
}

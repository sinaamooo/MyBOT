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
        $n = maNum($v);
        if ($n > 0) $out[strtoupper($k)] = $n;
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

    // نماد یک ارز
    $key = mb_strtolower($raw);
    $sym = pxCoinMap()[$key] ?? strtoupper($raw);
    if (!preg_match('/^[A-Z0-9]{2,10}$/', $sym)) return false;

    $p = pxFetch();
    $irtRate = (float)($p['USDT/IRT'] ?? 0);
    $usd = ($sym === 'USDT') ? 1.0 : (float)($p[$sym . '/USDT'] ?? 0);
    if ($usd <= 0 || $irtRate <= 0) return false;      // نماد ناشناخته — بگذار بقیه رسیدگی کنند

    $chg = (float)($p[$sym . '/CHANGE24'] ?? $p[$sym . '/CHG'] ?? 0);
    $hi  = (float)($p[$sym . '/HIGH24'] ?? 0);
    $lo  = (float)($p[$sym . '/LOW24'] ?? 0);
    $cap = pxCoinCaption($sym, $usd, $usd * $irtRate, $chg, $hi, $lo);

    if (!empty(pxVal('card.on'))) {
        $png = pxCard($sym, $usd, $chg);
        if ($png !== null) {
            pxSendPhoto($chatId, $png, $cap, $kb, $replyTo);
            return true;
        }
    }
    sendMsg(BOT_TOKEN, $chatId, $cap, $kb, $replyTo ? ['reply_to_message_id' => $replyTo] : []);
    return true;
}

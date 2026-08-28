<?php
/**
 * ☎️ numbers.php — موتور فروش شماره مجازی
 * ============================================================
 *
 * فروش شماره با بقیه‌ی فروش‌های ربات یک فرق بنیادی دارد: تحویل
 * یک‌مرحله‌ای نیست. بعد از پرداخت باید:
 *
 *   ۱. از پنل فروشنده یک شماره گرفت
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
 * اتصال به پنل فروشنده عمداً عمومی است — مثل «تحویل خودکار» مینی‌اپ‌ها.
 * آدرس، کلید و مسیر هر عملیات از پنل تنظیم می‌شود، پس با هر فروشنده‌ای
 * کار می‌کند و به هیچ سرویس خاصی گره نخورده.
 *
 * ⚠️ هیچ‌جای این فایل پول را دو بار برنمی‌گرداند و هیچ شماره‌ای را دو بار
 *    نمی‌خرد: هر دو کار پشت یک ادعای اتمی روی خودِ رکورد انجام می‌شوند.
 */

if (!defined('NUM_LIB')) define('NUM_LIB', 1);

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function numDefaults() {
    return [
        'wait'  => 900,        // مهلت انتظار کد — ثانیه
        'poll'  => 6,          // فاصله‌ی دو پرسش از پنل — ثانیه
        'api'   => [
            'on'         => false,
            'name'       => 'پنل شماره',
            'base'       => '',
            'auth_type'  => 'header',      // header | query | body | none
            'auth_key'   => 'Authorization',
            'auth_value' => '',
            'spec_url'   => '',
            'timeout'    => 15,
            'ops' => [
                // 🛒 گرفتن شماره: باید شناسه‌ی فعال‌سازی و خودِ شماره را برگرداند
                'buy' => [
                    'method' => 'POST', 'path' => '',
                    'body'   => '{"country":"{country}","service":"{service}"}',
                    'id_path' => 'id', 'phone_path' => 'phone', 'err_path' => 'message',
                ],
                // 📩 پیگیری کد
                'status' => [
                    'method' => 'GET', 'path' => '',
                    'body'   => '',
                    'code_path' => 'code', 'state_path' => 'status', 'err_path' => 'message',
                ],
                // 🔴 لغو
                'cancel' => [
                    'method' => 'POST', 'path' => '',
                    'body'   => '{"id":"{id}"}',
                    'err_path' => 'message',
                ],
                // 💰 موجودی پنل — اختیاری، فقط برای دکمه‌ی تست
                'balance' => [
                    'method' => 'GET', 'path' => '', 'body' => '',
                    'val_path' => 'balance', 'err_path' => 'message',
                ],
            ],
        ],
    ];
}

function numCfg() {
    $c = cfg()['numbers'] ?? null;
    $d = numDefaults();
    if (!is_array($c)) return $d;

    $out = array_replace($d, array_intersect_key($c, ['wait' => 1, 'poll' => 1]));
    $out['api'] = array_replace($d['api'], is_array($c['api'] ?? null) ? $c['api'] : []);
    $out['api']['ops'] = $d['api']['ops'];
    foreach ((array)($c['api']['ops'] ?? []) as $k => $v) {
        if (isset($out['api']['ops'][$k]) && is_array($v))
            $out['api']['ops'][$k] = array_replace($out['api']['ops'][$k], $v);
    }
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
    $api = numVal('api', []);
    return !empty($api['on']) && trim((string)($api['base'] ?? '')) !== '';
}

// ============================================================
// 📡 تماس با پنل فروشنده
// ============================================================

/**
 * یک عملیات را روی پنل اجرا می‌کند.
 * برگشت: [پاسخ آرایه‌ای, خطا]
 */
function numCall($op, array $vars = []) {
    $api = numVal('api', []);
    $cfgOp = $api['ops'][$op] ?? null;
    if (!is_array($cfgOp)) return [null, 'عملیات «' . $op . '» تعریف نشده'];

    [$url, $method, $headers, $body, $err] = numRequest(
        (string)($cfgOp['path'] ?? ''),
        strtoupper((string)($cfgOp['method'] ?? 'POST')),
        (string)($cfgOp['body'] ?? ''),
        $vars
    );
    if ($err !== '') return [null, $err];

    return maHttp($url, $method, $headers, $body, (int)($api['timeout'] ?? 15));
}

/**
 * آدرس و سرآیندِ یک درخواست را می‌سازد — با کلید، هرجا که پنل می‌خواهدش.
 *
 * جدا از numCall نوشته شده چون «تست خام» هم دقیقا همین را لازم دارد:
 * همان آدرس، همان کلید، ولی یک مسیر دلخواه که ادمین تایپ می‌کند.
 *
 * برگشت: [آدرس, متد, سرآیند, بدنه, خطا]
 */
function numRequest($path, $method, $body, array $vars = []) {
    $api  = numVal('api', []);
    $base = rtrim(trim((string)($api['base'] ?? '')), '/');
    if ($base === '') return ['', '', '', '', 'آدرس پنل ثبت نشده'];

    $path   = numFill((string)$path, $vars);
    $body   = numFill((string)$body, $vars);
    $url    = $base . '/' . ltrim($path, '/');
    $method = strtoupper((string)$method) ?: 'GET';

    $headers = '';
    $ak = trim((string)($api['auth_key'] ?? ''));
    $av = (string)($api['auth_value'] ?? '');
    switch ((string)($api['auth_type'] ?? 'header')) {
        case 'header':
            if ($ak !== '') $headers = $ak . ': ' . $av;
            break;
        case 'query':
            if ($ak !== '') $url .= (str_contains($url, '?') ? '&' : '?') .
                                    rawurlencode($ak) . '=' . rawurlencode($av);
            break;
        case 'body':
            if ($ak !== '' && $method === 'POST') {
                $b = json_decode($body ?: '{}', true);
                if (is_array($b)) { $b[$ak] = $av; $body = json_encode($b, JSON_UNESCAPED_UNICODE); }
            }
            break;
    }

    // متغیری که پر نشده، خام نرود — وگرنه پنل جواب گنگ می‌دهد
    if (preg_match('/\{([a-z_][a-z0-9_]*)\}/i', $url . ' ' . $body, $m))
        return ['', '', '', '', 'متغیر {' . $m[1] . '} پر نشد — تنظیمات این عملیات را ببینید'];

    return [$url, $method, $headers, $body, ''];
}

/** آدرس بدون کلید — برای نشان دادن به ادمین، تا کلید در گفتگو نیفتد */
function numSafeUrl($url) {
    $ak = trim((string)numVal('api.auth_key', ''));
    if ($ak === '' || (string)numVal('api.auth_type', '') !== 'query') return $url;
    return preg_replace('/([?&]' . preg_quote(rawurlencode($ak), '/') . '=)[^&]*/i', '$1***', $url);
}

function numFill($tpl, array $vars) {
    // پنل‌هایی که سرویس و کشور را یکی می‌کنند، اسمش را sid می‌گذارند —
    // هم‌معنی می‌گیریمش تا ادمین لازم نباشد اسم ما را حفظ کند.
    if (isset($vars['service']) && !isset($vars['sid'])) $vars['sid'] = $vars['service'];
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
    return strtr((string)$tpl, $map);
}

/** آیا پاسخ خطا دارد؟ همان منطق مینی‌اپ‌ها — آرایه هم خطاست، نه فقط رشته */
function numErr($resp, $cfgOp) {
    if (!is_array($resp)) return 'پاسخ نامعتبر';

    // 🚦 بعضی پنل‌ها اصلا فیلد «خطا» ندارند و موفقیت را با یک کد اعلام
    //    می‌کنند — مثلا RESULT=1 یعنی شد، هر چیز دیگری یعنی نشد. بدون
    //    این، پاسخِ ناموفق «بی‌خطا» خوانده می‌شد و بعد سر شماره‌ی نیامده
    //    یک پیام گنگ می‌گرفتیم.
    $okP = trim((string)($cfgOp['ok_path'] ?? ''));
    $okV = trim((string)($cfgOp['ok_val'] ?? ''));
    if ($okP !== '' && $okV !== '') {
        $got = maJsonPath($resp, $okP);
        if (!is_scalar($got) || trim((string)$got) !== $okV) {
            $seen = is_scalar($got) ? trim((string)$got) : 'نبود';
            // اگر پنل توضیحی هم داده، همان را بگو — گویاتر از یک عدد است
            $why = '';
            $ep = (string)($cfgOp['err_path'] ?? '');
            if ($ep !== '' && function_exists('maErrLooksReal')) {
                $ev = maJsonPath($resp, $ep);
                if (maErrLooksReal($ev)) $why = ' — ' . maErrText($ev);
            }
            return 'پنل ' . $okP . '=' . $seen . ' داد (انتظار ' . $okV . ')' . $why;
        }
    }

    $p = (string)($cfgOp['err_path'] ?? '');
    if ($p !== '' && function_exists('maErrLooksReal')) {
        $v = maJsonPath($resp, $p);
        if (maErrLooksReal($v)) return maErrText($v);
    }
    return '';
}

/**
 * 🔑 کد را از پاسخ بیرون می‌کشد.
 *
 * سه لایه، چون پنل‌ها سه جور جواب می‌دهند:
 *   ۱. مسیری که ادمین داده
 *   ۲. کلیدهای متداول، اگر مسیر خالی بود یا چیزی نداشت
 *   ۳. اگر آنچه آمد خودِ متنِ پیامک بود («کد شما 12345 است»)، رقم‌ها را
 *      از دلش درمی‌آوریم — چون کاربر کدِ خالی می‌خواهد نه یک جمله.
 */
function numExtractCode($resp, $cfgOp) {
    if (!is_array($resp)) return '';

    $cands = [];
    $p = trim((string)($cfgOp['code_path'] ?? ''));
    if ($p !== '') $cands[] = maJsonPath($resp, $p);
    foreach (['SMS', 'sms', 'code', 'CODE', 'Code', 'pin', 'PIN',
              'data.code', 'data.sms', 'text', 'TEXT'] as $k)
        $cands[] = maJsonPath($resp, $k);

    foreach ($cands as $v) {
        if (!is_scalar($v)) continue;
        $v = trim((string)$v);
        if ($v === '' || strtolower($v) === 'null') continue;

        // خودش کد است؟ (فقط رقم، ۳ تا ۸ رقمی)
        if (preg_match('/^\d{3,8}$/', $v)) return $v;

        // وگرنه متنِ پیامک است — بلندترین رشته‌ی رقمیِ ۳ تا ۸ رقمی را بردار
        if (preg_match_all('/\d{3,8}/', $v, $m)) {
            usort($m[0], fn($a, $b) => strlen($b) <=> strlen($a));
            return $m[0][0];
        }
    }
    return '';
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
    $out = [];
    foreach (numAll() as $act) {
        if ((int)($act['uid'] ?? 0) !== $uid) continue;
        $out[] = [
            'order'  => (string)$act['order'],
            'name'   => (string)($act['name'] ?? ''),
            'phone'  => (string)($act['phone'] ?? ''),
            'code'   => (string)($act['code'] ?? ''),
            'status' => (string)($act['status'] ?? ''),
            'at'     => (int)($act['created'] ?? 0),
        ];
    }
    usort($out, fn($a, $b) => $b['at'] <=> $a['at']);
    return array_slice($out, 0, max(1, (int)$limit));
}

/** کشور و سرویسِ یک آیتم — از روی خودِ پیکربندی مینی‌اپ */
function numItemMeta($itemId) {
    $a = function_exists('maGet') ? maGet('num') : [];
    foreach ((array)($a['items'] ?? []) as $i) {
        if ((string)($i['id'] ?? '') !== (string)$itemId) continue;
        $country = '';
        foreach ((array)($a['cats'] ?? []) as $c)
            if ((string)($c['id'] ?? '') === (string)($i['cat'] ?? '')) { $country = (string)($c['code'] ?? ''); break; }
        return ['service' => (string)($i['svc'] ?? ''), 'country' => $country, 'item' => $i];
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
    if (!numReady())  return [false, 'اتصال به پنل شماره تنظیم نشده است'];

    $meta = numItemMeta((string)($order['item_id'] ?? ''));
    if (!$meta) return [false, 'این سرویس دیگر تعریف نشده است'];

    // کشور اختیاری است: بعضی پنل‌ها (مثل نامبرلند) سرویس و کشور را با هم
    // در یک شناسه می‌دهند، پس فقط همان شناسه لازم است.
    if ($meta['service'] === '')
        return [false, 'کد سرویس برای این ردیف تنظیم نشده است'];

    // 🔒 ادعای اتمی: فقط یک اجرا شماره می‌خرد، حتی اگر دو درخواست هم‌زمان بیاید
    $claimed = false;
    mutate('num_acts', function (&$a) use ($oid, &$claimed) {
        if (isset($a[$oid])) return;
        $a[$oid] = ['order' => $oid, 'status' => 'buying', 'created' => time()];
        $claimed = true;
    });
    if (!$claimed) return [true, ''];

    [$resp, $err] = numCall('buy', [
        'country' => $meta['country'],
        'service' => $meta['service'],
        'order'   => $oid,
        'user_id' => (string)($order['user_id'] ?? ''),
    ]);

    $ops = numVal('api.ops.buy', []);
    if (!$resp)                          $fail = $err;
    elseif (($e = numErr($resp, $ops)))  $fail = $e;
    else                                 $fail = '';

    if ($fail === '') {
        $aid   = maJsonPath($resp, (string)($ops['id_path'] ?? 'id'));
        $phone = maJsonPath($resp, (string)($ops['phone_path'] ?? 'phone'));
        if (!is_scalar($aid) || trim((string)$aid) === '')   $fail = 'شناسه‌ی فعال‌سازی در پاسخ پنل نبود';
        elseif (!is_scalar($phone) || trim((string)$phone) === '') $fail = 'شماره در پاسخ پنل نبود';
    }

    if ($fail !== '') {
        mutate('num_acts', function (&$a) use ($oid) { unset($a[$oid]); });   // تا بشود دوباره تلاش کرد
        return [false, $fail];
    }

    // ⏳ اگر پنل خودش مهلت داده، همان را نگه دار — دقیق‌تر از عددِ ماست
    $ttl = numParseTtl(maJsonPath($resp, (string)($ops['ttl_path'] ?? '')));

    numPut([
        'order'   => $oid,
        'uid'     => (int)($order['user_id'] ?? 0),
        'item'    => (string)($order['item_id'] ?? ''),
        'name'    => (string)($order['item_name'] ?? ''),
        'service' => $meta['service'],
        'country' => $meta['country'],
        'aid'     => (string)$aid,
        'phone'   => numPhone((string)$phone),
        'code'    => '',
        'status'  => 'waiting',
        'price'   => (float)($order['total'] ?? 0),
        'created' => time(),
        'checked' => 0,
        'wait'    => $ttl >= 60 ? $ttl : 0,
    ]);
    return [true, ''];
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
 * از پنل زیاد نمی‌پرسد: بین دو پرسش دست‌کم `poll` ثانیه فاصله می‌گذارد،
 * وگرنه صد نفر که مینی‌اپ باز دارند پنل را می‌بندند.
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
    ];
}

/** یک بار از پنل می‌پرسد کد آمده یا نه */
function numPoll($orderId) {
    $act = numGet($orderId);
    if (!$act || $act['status'] !== 'waiting') return;

    numSetAct($orderId, function (&$x) { $x['checked'] = time(); return true; });

    [$resp, $err] = numCall('status', [
        'id'      => (string)$act['aid'],
        'order'   => (string)$act['order'],
        'country' => (string)($act['country'] ?? ''),
        'service' => (string)($act['service'] ?? ''),
    ]);
    if (!$resp) return;                                   // شبکه نبود — دفعه‌ی بعد

    $ops = numVal('api.ops.status', []);
    if (numErr($resp, $ops) !== '') return;

    // 📊 وضعیتی که پنل داد — بعضی پنل‌ها عدد می‌دهند، بعضی کلمه
    $st = maJsonPath($resp, (string)($ops['state_path'] ?? 'status'));
    $st = is_scalar($st) ? strtolower(trim((string)$st)) : '';

    // ⛔️ مرده؟ (لغو یا مسدود) — پول همان‌جا برگردد، منتظرِ مهلت نمانیم
    if ($st !== '' && numStateIn($st, (string)($ops['dead_val'] ?? ''))) {
        numFinish($orderId, 'expired');
        return;
    }

    // ✅ کد آمده؟
    //
    // دو جور پنل داریم و هر دو باید کار کنند:
    //   • آنکه تا کد نیامده اصلا فیلد کد را نمی‌فرستد → خودِ وجودِ کد نشانه است
    //   • آنکه همیشه فیلد کد را می‌فرستد و تا وقتی نیامده تویش "0" می‌گذارد
    //     → آنجا باید به وضعیت نگاه کرد، نه به فیلد کد
    // پس اگر «مقدارِ رسیدن» تنظیم شده باشد، همان حرفِ آخر را می‌زند.
    $wantSt = trim((string)($ops['done_val'] ?? ''));
    if ($wantSt !== '' && !numStateIn($st, $wantSt)) return;   // هنوز نه

    $code = numExtractCode($resp, $ops);
    if ($code !== '') {
        numSetAct($orderId, function (&$x) use ($code) {
            if ($x['status'] !== 'waiting') return false;
            $x['code'] = $code; $x['status'] = 'done';
            return true;
        });
        numOrderDone($orderId);
        return;
    }

    // وضعیتِ کلمه‌ای، برای پنل‌هایی که چیزی تنظیم نشده
    if ($wantSt === '' && $st !== '' &&
        in_array($st, ['cancel', 'cancelled', 'canceled', 'refunded', 'expired'], true))
        numFinish($orderId, 'expired');
}

/**
 * آیا این وضعیت در فهرستِ داده‌شده هست؟
 *
 * فهرست با ویرگول جدا می‌شود («2,6») چون یک حالت همیشه یک عدد نیست:
 * نامبرلند هم «کد رسید» دارد هم «تکمیل شد»، و هر دو یعنی کد دستمان است.
 */
function numStateIn($state, $list) {
    $state = strtolower(trim((string)$state));
    if ($state === '') return false;
    foreach (preg_split('/[,\s|]+/', (string)$list) as $one) {
        $one = strtolower(trim($one));
        if ($one !== '' && $one === $state) return true;
    }
    return false;
}

/**
 * ⏳ مهلتِ این فعال‌سازی.
 *
 * اگر پنل موقعِ فروش خودش مهلت داده باشد («TIME»:«00:20:00») همان معتبر
 * است، نه عددِ پنلِ ما — وگرنه یا زودتر پول برمی‌گردانیم و شماره‌ی
 * پول‌داده را دور می‌ریزیم، یا دیرتر و کاربر الکی منتظر می‌ماند.
 */
function numWaitFor($act) {
    $own = (int)($act['wait'] ?? 0);
    if ($own >= 60) return $own;
    return max(60, (int)numVal('wait', 900));
}

/** «00:20:00» یا «20:00» یا «1200» → ثانیه. ۰ یعنی نفهمیدم. */
function numParseTtl($v) {
    if (!is_scalar($v)) return 0;
    $v = trim((string)$v);
    if ($v === '') return 0;
    if (preg_match('/^\d+$/', $v)) return (int)$v;
    if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $v, $m))
        return (int)($m[1] ?? 0) * 3600 + (int)$m[2] * 60 + (int)$m[3];
    return 0;
}

/** کد رسید — سفارش بسته می‌شود و به خریدار خبر می‌رود */
function numOrderDone($orderId) {
    if (!class_exists('MaOrder')) return;
    $o = MaOrder::get($orderId);
    if (!$o || ($o['status'] ?? '') === MaOrder::DONE) return;

    MaOrder::set($orderId, function (&$x) {
        $x['status'] = MaOrder::DONE;
        $x['delivered_at'] = nowStr();
        $x['sending'] = 0;
        $x['last_error'] = '';
    });

    $act = numGet($orderId);
    $o   = MaOrder::get($orderId);
    if (function_exists('maTellUser') && $act) {
        maTellUser($o,
            "✅ <b>کد شما رسید</b>\n\n" .
            '📦 ' . h((string)($act['name'] ?? '')) . "\n" .
            '☎️ <code>' . h((string)$act['phone']) . "</code>\n" .
            '🔑 <code>' . h((string)$act['code']) . "</code>\n" .
            '🧾 <code>' . h((string)$orderId) . '</code>');
    }
    if (function_exists('axReportOrder')) axReportOrder($o, 'done');
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
function numFinish($orderId, $why = 'cancel') {
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

    // به پنل هم بگو، ولی اگر نگرفت جلوی برگشت پول را نگیر
    if (!empty($act['aid'])) {
        [$r, $e] = numCall('cancel', [
            'id'      => (string)$act['aid'],
            'order'   => (string)$orderId,
            'country' => (string)($act['country'] ?? ''),
            'service' => (string)($act['service'] ?? ''),
        ]);
        if (!$r) error_log('[numbers] لغو روی پنل نگرفت: ' . $e);
    }

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

/** ⏰ فعال‌سازی‌های از مهلت گذشته — از همان تیک عمومی ربات صدا زده می‌شود */
function numTick($limit = 10) {
    $now = time();
    $n   = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        if (($act['status'] ?? '') !== 'waiting') continue;
        if ($now - (int)($act['created'] ?? 0) < numWaitFor($act)) continue;
        numFinish((string)$act['order'], 'expired');
        $n++;
    }
    return $n;
}

// ============================================================
// 🛠 پنل مدیریت — اتصال به پنل فروشنده
// ============================================================
//
// این بخش سرِ جای بخشِ برداشته‌شده‌ی قبلی نشسته است و مثل آن، جدا از
// بقیه‌ی پنل زندگی می‌کند: هیچ صفحه‌ای از جاهای دیگر داخلش ساخته نمی‌شود
// و خودش هم چیزی به صفحه‌های دیگر اضافه نمی‌کند.

/**
 * ⚡️ آماده‌سازی یک‌ضربه‌ای برای پنل‌های شناخته‌شده.
 *
 * پر کردن دستیِ چهار عملیات، ده‌تا ورودی است و یک اشتباه تایپی کافی است
 * تا هیچ‌چیز کار نکند. برای پنل‌هایی که ساختارشان را می‌دانیم، همه‌اش با
 * یک دکمه ست می‌شود و ادمین فقط کلید API را می‌دهد.
 *
 * نامبرلند با بقیه دو فرق دارد و هر دو اینجا لحاظ شده:
 *   • کلید در خودِ آدرس می‌آید (apikey=…)، نه در سرآیند
 *   • سرویس و کشور یک شناسه‌ی واحدند (sid)، نه دوتا
 */
function numPresets() {
    return [
        'numberland' => [
            'label' => '🇮🇷 نامبرلند (numberland.ir)',
            'name'  => 'نامبرلند',
            'base'  => 'https://api.numberland.ir',
            'auth_type'  => 'query',
            'auth_key'   => 'apikey',
            'sid_only'   => true,
            'ops' => [
                'buy' => [
                    'method' => 'GET', 'path' => '/v2.php/?method=getnum&sid={service}', 'body' => '',
                    'id_path' => 'ID', 'phone_path' => 'NUMBER', 'ttl_path' => 'TIME',
                    'ok_path' => 'RESULT', 'ok_val' => '1', 'err_path' => 'DESCRIPTION',
                ],
                // 📌 وضعیت‌های نامبرلند: ۱ منتظر کد · ۲ کد رسید · ۳ لغو شده
                //    ۴ مسدود شده · ۵ منتظر کد مجدد · ۶ تکمیل شد
                //
                //    اینجا فیلد CODE همیشه فرستاده می‌شود و تا وقتی کد نیامده
                //    تویش "0" است. پس نمی‌شود به وجودِ CODE تکیه کرد — حرفِ
                //    آخر را RESULT می‌زند.
                'status' => [
                    'method' => 'GET', 'path' => '/v2.php/?method=checkstatus&id={id}', 'body' => '',
                    'code_path' => 'CODE', 'state_path' => 'RESULT',
                    'done_val' => '2,6', 'dead_val' => '3,4',
                    'err_path' => '',
                ],
                'cancel' => [
                    'method' => 'GET', 'path' => '', 'body' => '',
                    'err_path' => 'DESCRIPTION', 'ok_path' => '', 'ok_val' => '',
                ],
                'balance' => [
                    'method' => 'GET', 'path' => '', 'body' => '',
                    'val_path' => 'AMOUNT', 'err_path' => 'DESCRIPTION',
                ],
            ],
            // ⚠️ این دو مسیر عمدا خالی مانده‌اند، نه از قلم افتاده:
            //    «تغییر وضعیت شماره» و «موجودی» در مستندات نامبرلند بخش
            //    جدا دارند و اسم متدشان را حدس نمی‌زنیم. با «🧪 تست خام»
            //    پیدایشان کنید و در همان عملیات بگذارید.
            'todo' => ['cancel' => 'لغو شماره', 'balance' => 'موجودی پنل'],
            // متدهایی که در «🧪 تست خام» به درد می‌خورند
            'probe' => ['getinfo', 'getservice', 'getcountry'],
        ],
    ];
}

/** پیکربندی را از روی یک قالب پر می‌کند — کلید API دست‌نخورده می‌ماند */
function numApplyPreset($key) {
    $p = numPresets()[$key] ?? null;
    if (!$p) return false;
    numSet(function (&$c) use ($p) {
        $c['api']['on']        = true;
        $c['api']['name']      = $p['name'];
        $c['api']['base']      = $p['base'];
        $c['api']['auth_type'] = $p['auth_type'];
        $c['api']['auth_key']  = $p['auth_key'];
        foreach ($p['ops'] as $op => $fields)
            $c['api']['ops'][$op] = array_replace((array)($c['api']['ops'][$op] ?? []), $fields);
        $c['api']['preset'] = $p['label'];
    });
    return true;
}

/** برچسب فارسی هر عملیات */
function numOpLabels() {
    return [
        'buy'     => '🛒 گرفتن شماره',
        'status'  => '📩 پیگیری کد',
        'cancel'  => '🔴 لغو شماره',
        'balance' => '💰 موجودی پنل',
    ];
}

/** یک نگاه کوتاه: این عملیات تنظیم شده یا نه */
function numOpLine($op) {
    $o = numVal('api.ops.' . $op, []);
    $p = trim((string)($o['path'] ?? ''));
    return $p !== ''
        ? '<code>' . h((string)($o['method'] ?? 'POST')) . ' ' . h($p) . '</code>'
        : '<b>تنظیم نشده</b>';
}

function numAdmHome($chatId, $msgId) {
    $api  = numVal('api', []);
    $open = 0;
    foreach (numAll() as $a) if (($a['status'] ?? '') === 'waiting') $open++;

    $t  = "☎️ <b>شماره مجازی — اتصال به پنل</b>\n\n";
    $t .= "وضعیت: " . (!empty($api['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= "نام پنل: " . h((string)($api['name'] ?? '—')) . "\n";
    if (trim((string)($api['preset'] ?? '')) !== '')
        $t .= "قالب: " . h((string)$api['preset']) . "\n";
    $t .= "آدرس: " . (trim((string)($api['base'] ?? '')) !== ''
          ? '<code>' . h((string)$api['base']) . '</code>' : '<b>ثبت نشده</b>') . "\n";
    $t .= "احراز هویت: <b>" . h((string)($api['auth_type'] ?? '—')) . "</b>";
    $t .= trim((string)($api['auth_key'] ?? '')) !== '' ? ' · <code>' . h((string)$api['auth_key']) . '</code>' : '';
    $t .= "\nکلید: " . (trim((string)($api['auth_value'] ?? '')) !== ''
          ? '✅ ثبت شده (' . h(mb_substr((string)$api['auth_value'], 0, 6)) . '…)' : '<b>خالی</b>') . "\n";
    $t .= "مهلت تماس: <b>" . (int)($api['timeout'] ?? 15) . "</b> ثانیه\n\n";

    $t .= "⏳ مهلت انتظار کد: <b>" . (int)numVal('wait', 900) . "</b> ثانیه\n";
    $t .= "🔁 فاصله‌ی پیگیری: <b>" . (int)numVal('poll', 6) . "</b> ثانیه\n\n";

    $t .= "<b>عملیات‌ها:</b>\n";
    foreach (numOpLabels() as $k => $lbl) $t .= $lbl . ': ' . numOpLine($k) . "\n";

    // 🩺 عملیاتِ بی‌مسیر را همان بالا بگو، نه اینکه سرِ اولین خرید معلوم شود
    $miss = [];
    foreach (['buy' => 'گرفتن شماره', 'status' => 'پیگیری کد', 'cancel' => 'لغو'] as $k => $lbl)
        if (trim((string)numVal('api.ops.' . $k . '.path', '')) === '') $miss[] = $lbl;
    if ($miss) $t .= "\n⚠️ <b>بدون مسیر:</b> " . h(implode('، ', $miss)) .
                     ($miss === ['لغو'] ? " — فروش کار می‌کند ولی لغو روی پنل انجام نمی‌شود.\n"
                                        : " — تا اینها ست نشوند فروش انجام نمی‌شود.\n");

    if ($open) $t .= "\n⏳ <b>{$open}</b> شماره‌ی باز، در انتظار کد.";

    $t .= "\n\n💡 اول آدرس و کلید را بدهید، بعد «📖 خواندن مستندات» را بزنید تا " .
          "مسیرهای واقعی پنل را ببینید و برای هر عملیات انتخاب کنید.\n" .
          "کد کشور در «دسته‌ها» و کد سرویس در «محصول‌ها»ی همین مینی‌اپ ثبت می‌شود.";

    $rows = [
        [btnCb(!empty($api['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'numtog', 'info')],
        [btnCb('⚡️ آماده‌سازی خودکار پنل', 'numpre', 'confirm')],
        [btnCb('🧪 تست خام (هر متدی)', 'numraw', 'confirm')],
        [btnCb('📖 خواندن مستندات API', 'numspec', 'confirm')],
        [btnCb('💰 تست موجودی پنل', 'numtest', 'confirm')],
        [btnCb('🔗 آدرس پنل', 'nums_base', 'admin'), btnCb('🏷 نام', 'nums_name', 'admin')],
        [btnCb('🔐 نوع احراز', 'numauth', 'admin'), btnCb('🔑 کلید API', 'nums_auth_value', 'admin')],
        [btnCb('📛 نام هدر/پارامتر', 'nums_auth_key', 'admin'),
         btnCb('📄 آدرس مستندات', 'nums_spec_url', 'admin')],
        [btnCb('🛒 عملیات گرفتن شماره', 'numop_buy', 'admin')],
        [btnCb('📩 عملیات پیگیری کد', 'numop_status', 'admin')],
        [btnCb('🔴 عملیات لغو', 'numop_cancel', 'admin')],
        [btnCb('💰 عملیات موجودی', 'numop_balance', 'admin')],
        [btnCb('⏳ مهلت انتظار کد', 'nums_wait', 'admin'),
         btnCb('🔁 فاصله‌ی پیگیری', 'nums_poll', 'admin')],
        [btnCb('⏱ مهلت تماس', 'nums_timeout', 'admin')],
        [btnCb('📋 شماره‌های باز', 'numopen', 'reject')],
        [btnCb('🔙 بازگشت', 'maadm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

/** ⚙️ تنظیم یک عملیات */
function numAdmOp($chatId, $msgId, $op) {
    $labels = numOpLabels();
    if (!isset($labels[$op])) return;
    $o = numVal('api.ops.' . $op, []);

    $t  = $labels[$op] . " <b>— تنظیم عملیات</b>\n\n";
    $t .= "متد: <b>" . h((string)($o['method'] ?? 'POST')) . "</b>\n";
    $t .= "مسیر: <code>" . h(($o['path'] ?? '') !== '' ? (string)$o['path'] : '(خالی)') . "</code>\n";
    $t .= "بدنه:\n<code>" . h(($o['body'] ?? '') !== '' ? (string)$o['body'] : '(خالی)') . "</code>\n\n";

    // هر عملیات فقط مسیرهای مربوط به خودش را نشان می‌دهد — نه یک فهرست
    // بلندِ گنگ که ادمین نداند کدامش به کارش می‌آید.
    $t .= "<b>مسیرهای پاسخ:</b>\n";
    foreach (numOpFields($op) as $k => $lbl)
        $t .= $lbl . ': <code>' . h(($o[$k] ?? '') !== '' ? (string)$o[$k] : '—') . "</code>\n";

    $t .= "\n<b>متغیرهای قابل استفاده:</b>\n";
    $t .= "<code>{country}</code> کد کشور (از دسته)\n";
    $t .= "<code>{service}</code> کد سرویس (از محصول)\n";
    $t .= "<code>{id}</code> شناسه‌ی فعال‌سازی (پیگیری و لغو)\n";
    $t .= "<code>{order}</code> کد سفارش ما\n";
    $t .= "<code>{user_id}</code> آیدی عددی کاربر\n\n";
    $t .= "مثال بدنه:\n<code>{\"country\":\"{country}\",\"service\":\"{service}\"}</code>";

    $rows = [
        [btnCb('📮 متد', 'numo_' . $op . '|method', 'admin'),
         btnCb('📂 مسیر', 'numo_' . $op . '|path', 'admin')],
        [btnCb('📦 بدنه', 'numo_' . $op . '|body', 'admin')],
    ];
    $line = [];
    foreach (numOpFields($op) as $k => $lbl) {
        $line[] = btnCb($lbl, 'numo_' . $op . '|' . $k, 'admin');
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

/** کدام «مسیر پاسخ» برای کدام عملیات معنی دارد */
function numOpFields($op) {
    switch ($op) {
        case 'buy':     return ['id_path' => '🧾 مسیر شناسه', 'phone_path' => '☎️ مسیر شماره',
                                'ttl_path' => '⏳ مسیر مهلت', 'err_path' => '⚠️ مسیر خطا',
                                'ok_path' => '🚦 مسیر کد موفقیت', 'ok_val' => '🚦 مقدار موفقیت'];
        case 'status':  return ['code_path' => '🔑 مسیر کد', 'state_path' => '📊 مسیر وضعیت',
                                'done_val' => '✅ وضعیتِ «کد رسید»', 'dead_val' => '⛔️ وضعیتِ «لغو/مسدود»',
                                'err_path' => '⚠️ مسیر خطا'];
        case 'balance': return ['val_path' => '💠 مسیر مقدار', 'err_path' => '⚠️ مسیر خطا'];
        default:        return ['err_path' => '⚠️ مسیر خطا',
                                'ok_path' => '🚦 مسیر کد موفقیت', 'ok_val' => '🚦 مقدار موفقیت'];
    }
}

/** 📖 مستندات — همان جست‌وجوی مینی‌اپ‌ها، روی آدرسِ این پنل */
function numAdmSpec($chatId) {
    $back = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    $base = trim((string)numVal('api.base', ''));
    if ($base === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ اول «🔗 آدرس پنل» را ثبت کنید.", $back); return; }
    if (!function_exists('maSpecDiscover')) { sendMsg(BOT_TOKEN, $chatId, "⚠️ در دسترس نیست.", $back); return; }

    [$url, $j, $log] = maSpecDiscover(trim((string)numVal('api.spec_url', '')), $base);

    if (!$j) {
        // 📌 خیلی از پنل‌ها اصلا OpenAPI ندارند — مستنداتشان یک صفحه‌ی
        //    فارسیِ ساده است. ریختنِ ۱۲ خط ۴۰۴ جلوی ادمین فقط می‌ترساندش؛
        //    راهِ درست را می‌گوییم و فهرست تلاش‌ها را کوتاه نگه می‌داریم.
        $t  = "ℹ️ <b>این پنل مستندات ماشین‌خوان (OpenAPI) ندارد</b>\n\n";
        $t .= "اشکالی ندارد — بیشتر پنل‌های شماره همین‌طورند. دو راه دارید:\n\n";
        $t .= "۱️⃣ <b>⚡️ آماده‌سازی خودکار</b> — اگر پنلتان در فهرست هست، همه‌چیز یک‌جا ست می‌شود.\n\n";
        $t .= "۲️⃣ <b>🧪 تست خام</b> — مسیر را از مستنداتِ سایتِ پنل بردارید، اینجا صدایش بزنید، " .
              "و کلیدهای پاسخ را در «مسیر»های همان عملیات بنویسید.\n\n";
        $t .= "<i>امتحان شد: " . h(implode('، ', array_slice(array_map(
                fn($l) => trim(explode('→', $l)[0]), $log), 0, 5))) . " …</i>";
        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb([
            [btnCb('⚡️ آماده‌سازی خودکار', 'numpre', 'confirm')],
            [btnCb('🧪 تست خام', 'numraw', 'confirm')],
            [btnCb('☎️ شماره مجازی', 'num_home', 'admin')],
        ]));
        return;
    }

    $t = "📖 <b>مسیرهای پنل</b>\n<code>" . h(mb_substr($url, 0, 90)) . "</code>\n\n";
    $n = 0;
    foreach ((array)$j['paths'] as $path => $methods) {
        if (!is_array($methods)) continue;
        foreach ($methods as $m => $spec) {
            if (!in_array(strtolower((string)$m), ['get', 'post', 'put', 'patch', 'delete'], true)) continue;
            if (++$n > 60) break 2;
            $sum = is_array($spec) ? trim((string)($spec['summary'] ?? '')) : '';
            $t .= '<code>' . h(strtoupper((string)$m) . ' ' . $path) . '</code>' .
                  ($sum !== '' ? ' — ' . h(mb_substr($sum, 0, 40)) : '') . "\n";
        }
    }
    if ($n > 60) $t .= "\n<i>… و بقیه</i>";
    $t .= "\n\n💡 مسیر هرکدام را در «عملیات» مربوطه بگذارید — بدون آدرس پایه.";

    foreach (str_split($t, 3500) as $chunk) sendMsg(BOT_TOKEN, $chatId, $chunk, $back);
}

/** 💰 تست موجودی — بی‌خطرترین تماسی که می‌شود با پنل گرفت */
function numAdmTest($chatId) {
    $back = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    if (trim((string)numVal('api.ops.balance.path', '')) === '') {
        sendMsg(BOT_TOKEN, $chatId, "⚠️ اول مسیر «💰 عملیات موجودی» را ثبت کنید.", $back);
        return;
    }
    [$resp, $err] = numCall('balance');
    if (!$resp) { sendMsg(BOT_TOKEN, $chatId, "❌ <b>تماس نگرفت</b>\n<code>" . h((string)$err) . '</code>', $back); return; }

    $ops = numVal('api.ops.balance', []);
    if (($e = numErr($resp, $ops)) !== '') {
        sendMsg(BOT_TOKEN, $chatId, "❌ <b>پنل خطا داد</b>\n<code>" . h(mb_substr($e, 0, 300)) . '</code>', $back);
        return;
    }
    $v = maJsonPath($resp, (string)($ops['val_path'] ?? 'balance'));
    $t = "✅ <b>اتصال برقرار است</b>\n\n";
    $t .= is_scalar($v)
        ? "💰 موجودی پنل: <b>" . h((string)$v) . "</b>"
        : "⚠️ تماس گرفت ولی «مسیر مقدار» درست نیست.\n<code>" .
          h(mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 500)) . '</code>';
    sendMsg(BOT_TOKEN, $chatId, $t, $back);
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
    if (!$n) $t .= "<i>الان هیچ شماره‌ای باز نیست.</i>";
    $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

// ---------- 🎬 دکمه‌ها ----------

/** برگشت true یعنی این callback مالِ بخش شماره بود و رسیدگی شد */
function numCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (!str_starts_with($data, 'num')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
    $ack = function ($m = '') use ($cbId) { answerCb(BOT_TOKEN, $cbId, $m); };

    if ($data === 'num_home') { $ack(); numAdmHome($chatId, $msgId); return true; }
    if ($data === 'numspec')  { $ack('⏳ در حال خواندن…'); numAdmSpec($chatId); return true; }

    if ($data === 'numpre') {
        $rows = [];
        foreach (numPresets() as $k => $p) $rows[] = [btnCb($p['label'], 'numpre_' . $k, 'admin')];
        $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];
        $ack();
        editMsg(BOT_TOKEN, $chatId, $msgId,
            "⚡️ <b>آماده‌سازی خودکار</b>\n\n" .
            "پنلتان را انتخاب کنید تا آدرس، نوع احراز و هر چهار عملیات یک‌جا ست شود.\n" .
            "بعدش فقط «🔑 کلید API» را بدهید.\n\n" .
            "⚠️ کلید API دست‌نخورده می‌ماند — این دکمه پاکش نمی‌کند.", inlineKb($rows));
        return true;
    }
    if (str_starts_with($data, 'numpre_')) {
        $k = substr($data, 7);
        if (!numApplyPreset($k)) { $ack('⚠️ پیدا نشد'); return true; }
        $p = numPresets()[$k];
        $ack('✅ ست شد');
        sendMsg(BOT_TOKEN, $chatId,
            "✅ <b>" . h($p['label']) . " ست شد</b>\n\n" .
            (trim((string)numVal('api.auth_value', '')) === ''
                ? "حالا «🔑 کلید API» را بدهید.\n\n"
                : "کلید API از قبل ثبت است.\n\n") .
            (!empty($p['sid_only'])
                ? "📌 این پنل سرویس و کشور را با هم در یک شناسه می‌دهد. پس:\n" .
                  "• «کد کشور» در دسته‌ها را <b>خالی بگذارید</b>\n" .
                  "• همان <code>sid</code> را در «📱 کد سرویس» هر محصول بنویسید\n" .
                  "• <code>sid</code>ها را با «🧪 تست خام» و <code>getinfo</code> ببینید\n\n"
                : '') .
            (!empty($p['todo'])
                ? "⚠️ <b>دو مسیر خالی ماند</b> — اسمشان در مستندات هست ولی حدس نزدیم:\n" .
                  implode('', array_map(fn($lbl) => '• ' . $lbl . "\n", $p['todo'])) .
                  "با «🧪 تست خام» پیدا کنید و در همان عملیات بگذارید.\n" .
                  "<i>بدون «لغو»، پول کاربر برمی‌گردد ولی شماره روی پنل باز می‌ماند تا خودش منقضی شود.</i>\n\n"
                : "بعد با «🧪 تست خام» مطمئن شوید متدها درست‌اند."),
            inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]));
        return true;
    }

    if ($data === 'numraw') {
        setState($uid, 'num_raw', []);
        $ack();
        $hint = '';
        foreach (numPresets() as $p) {
            if (rtrim((string)numVal('api.base', ''), '/') !== rtrim($p['base'], '/')) continue;
            $hint = "\n\nبرای این پنل اینها را امتحان کنید:\n";
            foreach ((array)($p['probe'] ?? []) as $m)
                $hint .= '<code>/v2.php/?method=' . h($m) . "</code>\n";
        }
        sendMsg(BOT_TOKEN, $chatId,
            "🧪 <b>تست خام</b>\n\n" .
            "یک مسیر بفرستید تا همان‌طور که هست صدا زده شود و پاسخِ خام را ببینید — " .
            "کلید API خودکار اضافه می‌شود.\n\n" .
            "مثال:\n<code>/v2.php/?method=getservice</code>" . $hint,
            inlineKb([[btnCb('🔙 بازگشت', 'num_home', 'nav')]]));
        return true;
    }
    if ($data === 'numtest')  { $ack('⏳ تماس با پنل…'); numAdmTest($chatId); return true; }
    if ($data === 'numopen')  { $ack(); numAdmOpen($chatId, $msgId); return true; }

    if ($data === 'numtog') {
        numSet(function (&$c) { $c['api']['on'] = empty($c['api']['on']); });
        $ack(!empty(numVal('api.on')) ? '✅ روشن شد' : '❌ خاموش شد');
        numAdmHome($chatId, $msgId);
        return true;
    }

    if ($data === 'numauth') {
        $cur = (string)numVal('api.auth_type', 'header');
        $rows = [];
        foreach (['header' => 'هدر (Authorization)', 'query' => 'پارامتر آدرس',
                  'body' => 'داخل بدنه', 'none' => 'بدون احراز'] as $k => $lbl) {
            $rows[] = [btnCb(($cur === $k ? '✅ ' : '') . $lbl, 'numauths_' . $k, 'admin')];
        }
        $rows[] = [btnCb('🔙 بازگشت', 'num_home', 'nav')];
        $ack();
        editMsg(BOT_TOKEN, $chatId, $msgId, "🔐 <b>نوع احراز هویت پنل</b>\n\n" .
            "پنل کلید را کجا می‌خواهد؟ اگر مطمئن نیستید، «هدر» درست‌ترین حدس است.", inlineKb($rows));
        return true;
    }
    if (str_starts_with($data, 'numauths_')) {
        $v = substr($data, 9);
        if (in_array($v, ['header', 'query', 'body', 'none'], true)) {
            numSet(function (&$c) use ($v) { $c['api']['auth_type'] = $v; });
            $ack('✅');
        } else $ack();
        numAdmHome($chatId, $msgId);
        return true;
    }

    if (str_starts_with($data, 'numop_')) {
        $ack(); numAdmOp($chatId, $msgId, substr($data, 6)); return true;
    }

    if (str_starts_with($data, 'numkill_')) {
        [$ok, $err] = numFinish(substr($data, 8), 'cancel');
        $ack($ok ? '✅ لغو شد و پول برگشت' : ('⚠️ ' . $err));
        numAdmOpen($chatId, $msgId);
        return true;
    }

    // ✍️ ورودی‌های متنی — تنظیمات ساده
    $simple = [
        'nums_base'       => ['num_base',      "🔗 آدرس پنل را بفرستید — کامل و با https، بدون اسلش آخر.\nمثال: <code>https://api.example.com</code>"],
        'nums_name'       => ['num_name',      "🏷 نام پنل را بفرستید — فقط برای خودتان است."],
        'nums_auth_key'   => ['num_auth_key',  "📛 نام هدر یا پارامتر کلید را بفرستید.\nمعمولا <code>Authorization</code> یا <code>X-API-Key</code>."],
        'nums_auth_value' => ['num_auth_value',"🔑 کلید API را بفرستید.\n\n⚠️ اگر پنل «Bearer» می‌خواهد، خودِ کلمه را هم بنویسید: <code>Bearer abc123</code>\n\nبعد از ثبت، پیامتان پاک می‌شود."],
        'nums_spec_url'   => ['num_spec_url',  "📄 آدرس فایل مستندات (معمولا به <code>.json</code> ختم می‌شود) را بفرستید."],
        'nums_wait'       => ['num_wait',      "⏳ مهلت انتظار کد را به ثانیه بفرستید.\nمثال: <code>900</code> یعنی ۱۵ دقیقه. کمتر از ۶۰ پذیرفته نمی‌شود."],
        'nums_poll'       => ['num_poll',      "🔁 فاصله‌ی دو پرسش از پنل را به ثانیه بفرستید.\nکمتر از ۳ ثانیه پنل را خسته می‌کند."],
        'nums_timeout'    => ['num_timeout',   "⏱ مهلت هر تماس با پنل را به ثانیه بفرستید. بین ۳ تا ۶۰."],
    ];
    if (isset($simple[$data])) {
        [$act, $msg] = $simple[$data];
        setState($uid, $act, []);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, $msg, inlineKb([[btnCb('🔙 بازگشت', 'num_home', 'nav')]]));
        return true;
    }

    // ✍️ ورودی‌های متنی — فیلدهای یک عملیات
    if (str_starts_with($data, 'numo_')) {
        $rest = substr($data, 5);
        $bar  = strpos($rest, '|');
        if ($bar === false) { $ack(); return true; }
        $op    = substr($rest, 0, $bar);
        $field = substr($rest, $bar + 1);
        if (!isset(numOpLabels()[$op]) || !numFieldOk($op, $field)) { $ack(); return true; }

        setState($uid, 'num_op', ['op' => $op, 'field' => $field]);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, numFieldAsk($op, $field),
                inlineKb([[btnCb('🔙 بازگشت', 'numop_' . $op, 'nav')]]));
        return true;
    }

    return false;
}

/** آیا این فیلد برای این عملیات مجاز است؟ ورودیِ callback همیشه مشکوک است */
function numFieldOk($op, $field) {
    if (in_array($field, ['method', 'path', 'body'], true)) return true;
    return array_key_exists($field, numOpFields($op));
}

function numFieldAsk($op, $field) {
    switch ($field) {
        case 'method': return "📮 متد را بفرستید: <code>GET</code> یا <code>POST</code> یا <code>PUT</code>.";
        case 'path':   return "📂 مسیر این عملیات را بفرستید — بدون آدرس پایه.\n" .
                              "مثال: <code>/api/v1/number</code>\n\n" .
                              "پارامتر هم می‌شود گذاشت:\n<code>/v2.php/?method=getnum&sid={service}</code>\n\n" .
                              "متغیرها: <code>{service}</code> <code>{sid}</code> <code>{country}</code> <code>{id}</code>";
        case 'body':   return "📦 بدنه‌ی JSON را بفرستید.\nمثال: <code>{\"country\":\"{country}\",\"service\":\"{service}\"}</code>\n\nبرای خالی کردن، یک خط تیره بفرستید: <code>-</code>";
        case 'ok_path': return "🚦 اگر پنل موفقیت را با یک کد اعلام می‌کند، نامِ آن فیلد را بفرستید.\n" .
                               "مثال نامبرلند: <code>RESULT</code>\n\nبرای خالی کردن: <code>-</code>";
        case 'ok_val':  return "🚦 مقداری که یعنی «موفق» را بفرستید.\n" .
                               "مثال نامبرلند: <code>1</code>\n\nبرای خالی کردن: <code>-</code>";
        case 'ttl_path': return "⏳ اگر پنل موقعِ فروش مهلتِ شماره را می‌دهد، نامِ آن فیلد را بفرستید.\n" .
                                "مثال نامبرلند: <code>TIME</code> (که <code>00:20:00</code> می‌دهد)\n\n" .
                                "با این، شمارشِ معکوسِ ربات دقیقا همان مهلتِ واقعی می‌شود.\n" .
                                "برای خالی کردن: <code>-</code>";
        case 'done_val': return "✅ وضعیت‌هایی که یعنی «کد رسید» را بفرستید — با ویرگول.\n" .
                                "مثال نامبرلند: <code>2,6</code> (کد رسید، تکمیل شد)\n\n" .
                                "این وقتی لازم است که پنل فیلدِ کد را همیشه می‌فرستد و تا نیامده تویش " .
                                "<code>0</code> می‌گذارد.\n\nبرای خالی کردن: <code>-</code>";
        case 'dead_val': return "⛔️ وضعیت‌هایی که یعنی «لغو یا مسدود» را بفرستید — با ویرگول.\n" .
                                "مثال نامبرلند: <code>3,4</code> (کنسل شده، مسدود شده)\n\n" .
                                "با این، پول بی‌درنگ برمی‌گردد و کاربر تا آخرِ مهلت الکی منتظر نمی‌ماند.\n" .
                                "برای خالی کردن: <code>-</code>";
        default:
            return "🧭 مسیر این مقدار داخل پاسخِ پنل را بفرستید.\n" .
                   "برای فیلدهای تودرتو با نقطه: <code>data.activation.id</code>\n" .
                   "برای عضوِ آرایه با عدد: <code>items.0.phone</code>\n\n" .
                   "برای خالی کردن، یک خط تیره بفرستید: <code>-</code>";
    }
}

// ---------- ✍️ پاسخ‌های متنی ----------

function numStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'num_')) return false;
    $st    = getState($uid);
    $sd    = is_array($st['data'] ?? null) ? $st['data'] : [];
    $plain = trim((string)($msg['text'] ?? ''));
    $blank = ($plain === '-' || $plain === '—');
    $back  = inlineKb([[btnCb('☎️ شماره مجازی', 'num_home', 'admin')]]);
    $done  = function ($t) use ($chatId, $back, $uid) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $t, $back);
    };

    switch ($action) {
        case 'num_base':
            if (!preg_match('#^https://[^\s]+$#i', $plain)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس باید با <code>https://</code> شروع شود.", $back);
                return true;
            }
            $v = rtrim($plain, '/');
            numSet(function (&$c) use ($v) { $c['api']['base'] = $v; });
            $done("✅ آدرس پنل ثبت شد.\n<code>" . h($v) . '</code>');
            return true;

        case 'num_name':
            $v = mb_substr($plain, 0, 40);
            numSet(function (&$c) use ($v) { $c['api']['name'] = $v; });
            $done('✅ ثبت شد.');
            return true;

        case 'num_auth_key':
            $v = $blank ? '' : mb_substr($plain, 0, 60);
            numSet(function (&$c) use ($v) { $c['api']['auth_key'] = $v; });
            $done('✅ ثبت شد.');
            return true;

        case 'num_auth_value':
            // 🔒 کلید در تاریخچه‌ی گفتگو نمی‌ماند
            $v = $blank ? '' : $plain;
            numSet(function (&$c) use ($v) { $c['api']['auth_value'] = $v; });
            if (!empty($msg['message_id'])) delMsg(BOT_TOKEN, $chatId, (int)$msg['message_id']);
            $done($v === '' ? '✅ کلید پاک شد.' : '✅ کلید ثبت شد و پیامتان پاک شد.');
            return true;

        case 'num_spec_url':
            $v = $blank ? '' : $plain;
            if ($v !== '' && !preg_match('#^https?://[^\s]+$#i', $v)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس معتبر نیست.", $back);
                return true;
            }
            numSet(function (&$c) use ($v) { $c['api']['spec_url'] = $v; });
            $done('✅ ثبت شد.');
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

        case 'num_raw':
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک مسیر بفرستید.", $back); return true; }
            [$url, $m, $hd, $bd, $er] = numRequest($plain, 'GET', '');
            if ($er !== '') { sendMsg(BOT_TOKEN, $chatId, "❌ " . h($er), $back); return true; }
            [$resp, $err] = maHttp($url, 'GET', $hd, '', (int)numVal('api.timeout', 15));

            $t  = "🧪 <b>پاسخ خام</b>\n<code>" . h(mb_substr(numSafeUrl($url), 0, 120)) . "</code>\n\n";
            if (!$resp) {
                $t .= "❌ " . h(mb_substr((string)$err, 0, 600));
            } else {
                $j = json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $t .= "<code>" . h(mb_substr((string)$j, 0, 2800)) . "</code>";
                // کلیدهای سطح اول را جدا بگو — همان‌هایی که باید در «مسیر»ها بنویسد
                if (is_array($resp) && !array_is_list($resp)) {
                    $keys = array_slice(array_keys($resp), 0, 20);
                    $t .= "\n\n🔑 کلیدها: <code>" . h(implode('</code> <code>', $keys)) . "</code>";
                }
            }
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $t, inlineKb([
                [btnCb('🧪 یکی دیگر', 'numraw', 'confirm')],
                [btnCb('☎️ شماره مجازی', 'num_home', 'admin')],
            ]));
            return true;

        case 'num_op':
            $op    = (string)($sd['op'] ?? '');
            $field = (string)($sd['field'] ?? '');
            if (!isset(numOpLabels()[$op]) || !numFieldOk($op, $field)) { clearState($uid); return true; }

            $v = $blank ? '' : $plain;
            if ($field === 'method') {
                $v = strtoupper($v);
                if (!in_array($v, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    sendMsg(BOT_TOKEN, $chatId, "⚠️ متد باید GET یا POST یا PUT یا PATCH یا DELETE باشد.", $back);
                    return true;
                }
            }
            if ($field === 'path' && $v !== '' && !str_starts_with($v, '/')) $v = '/' . $v;
            if ($field === 'body' && $v !== '') {
                // ⚠️ بدنه‌ای که JSON نیست، بعدا وسط یک سفارش خطا می‌دهد —
                //    بهتر است همین‌جا معلوم شود، نه سرِ خرید مشتری.
                //
                //    متغیرها دو جور می‌آیند: داخل گیومه ("{country}") که رشته
                //    می‌شوند، و بی‌گیومه ({qty}) که عدد. اگر هر دو را یک‌جور
                //    جایگزین کنیم، همان بدنه‌ی درست هم نامعتبر خوانده می‌شود.
                $probe = preg_replace('/"\{[a-z_][a-z0-9_]*\}"/i', '"x"', $v);
                $probe = preg_replace('/\{[a-z_][a-z0-9_]*\}/i', '0', (string)$probe);
                if (json_decode($probe, true) === null && strtolower(trim($probe)) !== 'null') {
                    sendMsg(BOT_TOKEN, $chatId, "⚠️ این بدنه JSON معتبر نیست. دوباره بفرستید.", $back);
                    return true;
                }
            }
            numSet(function (&$c) use ($op, $field, $v) { $c['api']['ops'][$op][$field] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $v === '' ? '✅ پاک شد.' : '✅ ثبت شد.',
                    inlineKb([[btnCb(numOpLabels()[$op], 'numop_' . $op, 'admin')]]));
            return true;
    }
    return false;
}

/** عددِ فارسی یا عربی هم عدد است */
function numDigits($s) {
    $s = strtr((string)$s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    return preg_replace('/\D+/', '', $s);
}

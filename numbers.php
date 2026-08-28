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
    $base = rtrim(trim((string)($api['base'] ?? '')), '/');
    if ($base === '') return [null, 'آدرس پنل ثبت نشده'];

    $cfgOp = $api['ops'][$op] ?? null;
    if (!is_array($cfgOp)) return [null, 'عملیات «' . $op . '» تعریف نشده'];

    $path = numFill((string)($cfgOp['path'] ?? ''), $vars);
    $body = numFill((string)($cfgOp['body'] ?? ''), $vars);
    $url  = $base . '/' . ltrim($path, '/');
    $method = strtoupper((string)($cfgOp['method'] ?? 'POST'));

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
        return [null, 'متغیر {' . $m[1] . '} پر نشد — تنظیمات این عملیات را ببینید'];

    return maHttp($url, $method, $headers, $body, (int)($api['timeout'] ?? 15));
}

function numFill($tpl, array $vars) {
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
    return strtr((string)$tpl, $map);
}

/** آیا پاسخ خطا دارد؟ همان منطق مینی‌اپ‌ها — آرایه هم خطاست، نه فقط رشته */
function numErr($resp, $cfgOp) {
    if (!is_array($resp)) return 'پاسخ نامعتبر';
    $p = (string)($cfgOp['err_path'] ?? '');
    if ($p !== '' && function_exists('maErrLooksReal')) {
        $v = maJsonPath($resp, $p);
        if (maErrLooksReal($v)) return maErrText($v);
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
    if ($meta['service'] === '' || $meta['country'] === '')
        return [false, 'کد سرویس یا کشور برای این ردیف تنظیم نشده است'];

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

    $wait = max(60, (int)numVal('wait', 900));
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

    $code = maJsonPath($resp, (string)($ops['code_path'] ?? 'code'));
    if (is_scalar($code)) {
        $code = trim((string)$code);
        if ($code !== '' && strtolower($code) !== 'null') {
            numSetAct($orderId, function (&$x) use ($code) {
                if ($x['status'] !== 'waiting') return false;
                $x['code'] = $code; $x['status'] = 'done';
                return true;
            });
            numOrderDone($orderId);
            return;
        }
    }

    // بعضی پنل‌ها به‌جای کد، وضعیت می‌دهند — «لغو شده» را هم بفهمیم
    $st = maJsonPath($resp, (string)($ops['state_path'] ?? 'status'));
    if (is_scalar($st)) {
        $st = strtolower(trim((string)$st));
        if (in_array($st, ['cancel', 'cancelled', 'canceled', 'refunded', 'expired'], true))
            numFinish($orderId, 'expired');
    }
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
    $wait = max(60, (int)numVal('wait', 900));
    $now  = time();
    $n    = 0;
    foreach (numAll() as $act) {
        if ($n >= $limit) break;
        if (($act['status'] ?? '') !== 'waiting') continue;
        if ($now - (int)($act['created'] ?? 0) < $wait) continue;
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

    if ($open) $t .= "\n⏳ <b>{$open}</b> شماره‌ی باز، در انتظار کد.";

    $t .= "\n\n💡 اول آدرس و کلید را بدهید، بعد «📖 خواندن مستندات» را بزنید تا " .
          "مسیرهای واقعی پنل را ببینید و برای هر عملیات انتخاب کنید.\n" .
          "کد کشور در «دسته‌ها» و کد سرویس در «محصول‌ها»ی همین مینی‌اپ ثبت می‌شود.";

    $rows = [
        [btnCb(!empty($api['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'numtog', 'info')],
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
                                'err_path' => '⚠️ مسیر خطا'];
        case 'status':  return ['code_path' => '🔑 مسیر کد', 'state_path' => '📊 مسیر وضعیت',
                                'err_path' => '⚠️ مسیر خطا'];
        case 'balance': return ['val_path' => '💠 مسیر مقدار', 'err_path' => '⚠️ مسیر خطا'];
        default:        return ['err_path' => '⚠️ مسیر خطا'];
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
        $t = "❌ <b>مستندات پیدا نشد</b>\n\nاین آدرس‌ها امتحان شد:\n";
        foreach (array_slice($log, 0, 14) as $l) $t .= "• <code>" . h(mb_substr($l, 0, 76)) . "</code>\n";
        $t .= "\nصفحه‌ی مستندات پنل را در مرورگر باز کنید و آدرسی که به <code>.json</code> " .
              "ختم می‌شود در «📄 آدرس مستندات» بگذارید.";
        sendMsg(BOT_TOKEN, $chatId, $t, $back);
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
        $left = max(0, (int)numVal('wait', 900) - (time() - (int)($a['created'] ?? 0)));
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
        case 'path':   return "📂 مسیر این عملیات را بفرستید — بدون آدرس پایه.\nمثال: <code>/api/v1/number</code>\n\nمتغیرها هم مجازند: <code>/status/{id}</code>";
        case 'body':   return "📦 بدنه‌ی JSON را بفرستید.\nمثال: <code>{\"country\":\"{country}\",\"service\":\"{service}\"}</code>\n\nبرای خالی کردن، یک خط تیره بفرستید: <code>-</code>";
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

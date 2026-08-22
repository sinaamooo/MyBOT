<?php
/**
 * ============================================================
 * 🧩 admin_ext.php — افزونه‌ی مدیریت
 * ============================================================
 *
 * همه‌ی قابلیت‌های تازه اینجا زندگی می‌کنند تا فایل‌های اصلی سنگین نشوند:
 *
 *   📦 مخزن کانفیگ        — کانفیگ‌ها را می‌ریزید، ربات خودکار می‌فروشد
 *   🎁 سفارش دستی گیفت    — فرم سفارش در کانال، دکمه «انجام شد»
 *   📊 گزارش مینی‌اپ‌ها    — گزارش جدا برای هر مینی‌اپ
 *   💱 نرخ خودکار ارز     — نوبیتکس / والکس برای کل ربات
 *   💵 سود و قیمت         — درصد سود و قیمت دستی هر محصول
 *   ✍️ متن‌های قابل ویرایش — همه‌ی متن‌ها و برچسب دکمه‌ها
 *
 * ذخیره‌سازی: data_master/ext.json  (جدا از config.json)
 * وابستگی: توابع پایه‌ی bot_master_membership.php (load/mutate/sendMsg/…)
 */

if (!defined('AX_VERSION')) define('AX_VERSION', '1.0.0');

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function axDefaults() {
    return [
        // ---------- 📦 مخزن کانفیگ ----------
        'stock' => [
            'on'   => true,
            // متن تحویل — {config} جای خود کانفیگ می‌نشیند
            'text' => "✅ <b>سفارش شما آماده است</b>\n\n" .
                      "📦 محصول: <b>{item}</b>\n" .
                      "📅 تاریخ: {date}\n" .
                      "🧾 کد پیگیری: <code>{code}</code>\n\n" .
                      "<blockquote>{config}</blockquote>\n\n" .
                      "🙏 از خرید شما سپاسگزاریم.",
            'empty_text' => "⚠️ <b>موجودی این محصول تمام شد</b>\n\n" .
                            "سفارش شما ثبت شد و به‌محض شارژ مخزن، خودکار برایتان می‌آید.\n" .
                            "کد پیگیری: <code>{code}</code>",
            'low_at'  => 3,        // زیر این تعداد به ادمین هشدار بده
            'items'   => [],       // sku => ['name'=>..,'lines'=>[..]]
        ],

        // ---------- 🎁 سفارش دستی (گیفت / تون) ----------
        'manual' => [
            'on'      => true,
            'chat_id' => '',       // کانال فرم سفارش — @name یا -100…
            'thread_id' => 0,
            'items'   => [],       // شناسه محصول‌هایی که دستی‌اند
            'form'    => "🎁 <b>سفارش تازه — نیاز به انجام دستی</b>\n\n" .
                         "📦 محصول: <b>{item}</b>\n" .
                         "🔢 تعداد: <b>{qty}</b>\n" .
                         "🎯 گیرنده: <code>{field}</code>\n" .
                         "👤 خریدار: {user} (<code>{user_id}</code>)\n" .
                         "💰 مبلغ: <b>{amount} {currency}</b>\n" .
                         "🧾 کد: <code>{code}</code>\n" .
                         "📅 {date}",
            'btn'     => '✅ سفارش انجام شد',
            'done_text' => "🎉 <b>سفارش شما انجام شد</b>\n\n" .
                           "📦 محصول: <b>{item}</b>\n" .
                           "🔢 تعداد: <b>{qty}</b>\n" .
                           "🎯 گیرنده: <code>{field}</code>\n" .
                           "🧾 کد پیگیری: <code>{code}</code>\n\n" .
                           "🙏 از خرید شما سپاسگزاریم.",
            'done_mark' => "✅ <b>انجام شد</b> — {admin} · {date}",
        ],

        // ---------- 📊 گزارش مینی‌اپ‌ها ----------
        'report' => [
            'tg'  => axDefaultReport('🛰 <b>فروش خدمات تلگرام</b>'),
            'cfg' => axDefaultReport('🛡 <b>فروش کانفیگ</b>'),
        ],

        // ---------- 💵 سود و قیمت ----------
        'pricing' => [
            'on'      => true,
            'margin'  => [],       // category => درصد سود
            'fixed'   => [],       // item_id  => قیمت دستی تومان (۰ = خودکار)
            'auto'    => ['ton', 'trx', 'usdt'],   // اینها همیشه از صرافی
        ],

        // ---------- ✍️ متن‌ها و برچسب دکمه‌ها ----------
        'labels' => [
            'members_btn'  => '👥 پیوی‌ها و گروه‌ها',
            'members_head' => "👥 <b>منابع عضوگیری این سفارش</b>\n",
            'members_pv'   => '💬 پیوی‌ها',
            'members_gp'   => '👥 گروه‌ها و کانال‌ها',
            'members_none' => 'هنوز منبعی برای این سفارش ثبت نشده است.',
            'sup_stack'    => true,      // دکمه‌های پشتیبانی زیر هم
        ],

        // ---------- 🧾 متن تایید سفارش مینی‌اپ‌ها ----------
        // همان شکل ربات مادر: ایموجی پریمیوم و بخش‌های نقل‌قول‌شده
        'texts' => [
            'invoice_on' => true,
            'invoice'    => "📋 کاربر گرامی، جزئیات سفارش شما به شرح زیر است:\n\n" .
                            "<blockquote>📦 محصول: <b>{item}</b>\n" .
                            "🔢 تعداد: <b>{qty}</b> {unit}\n" .
                            "🎯 گیرنده: <code>{field}</code>\n" .
                            "💵 قیمت واحد: <b>{unit_price}</b> {currency}</blockquote>\n\n" .
                            "💳 مبلغ قابل پرداخت: <b>{amount} {currency}</b>\n" .
                            "👛 موجودی شما: <b>{balance}</b> {currency}\n" .
                            "🧾 کد پیگیری: <code>{code}</code>\n\n" .
                            "<blockquote expandable>❗️ تمامی سفارش‌ها بصورت آنی ثبت شده و بصورت سیستمی انجام می‌گیرند.\n" .
                            "پس از پرداخت، سفارش خودکار وارد صف تحویل می‌شود.</blockquote>\n\n" .
                            "👇 روش پرداخت را انتخاب کنید:",
        ],

        // ---------- ⚙️ عمومی ----------
        'rates_auto' => true,
        'log'        => [],        // ۵۰ رویداد آخر
    ];
}

function axDefaultReport($head) {
    return [
        'on'        => false,
        'chat_id'   => '',
        'thread_id' => 0,
        'text'      => $head . "\n\n" .
                       "📦 محصول: <b>{item}</b>\n" .
                       "🔢 تعداد: <b>{qty}</b>\n" .
                       "🎯 گیرنده: <code>{field}</code>\n" .
                       "👤 خریدار: {user}\n" .
                       "💰 مبلغ: <b>{amount} {currency}</b>\n" .
                       "💳 پرداخت: {pay}\n" .
                       "🧾 کد: <code>{code}</code>\n" .
                       "📅 {date}",
        'buttons'   => [
            ['text' => 'ثبت سفارش', 'url' => '', 'color' => 'success', 'icon' => '', 'on' => true],
            ['text' => 'پشتیبانی',  'url' => '', 'color' => 'primary', 'icon' => '', 'on' => true],
        ],
        'btn_row'   => true,
        'on_paid'   => true,       // لحظه‌ی پرداخت
        'on_done'   => false,      // لحظه‌ی تحویل
    ];
}

/** ادغام بازگشتی تنظیمات ذخیره‌شده روی پیش‌فرض */
function axMerge($def, $saved) {
    if (!is_array($saved)) return $def;
    foreach ($saved as $k => $v) {
        if (isset($def[$k]) && is_array($def[$k]) && is_array($v) && !array_is_list($def[$k])) {
            $def[$k] = axMerge($def[$k], $v);
        } else {
            $def[$k] = $v;
        }
    }
    return $def;
}

function axCfg() {
    static $c = null, $gen = -1;
    $now = (int)($GLOBALS['__ax_gen'] ?? 0);
    if ($c === null || $gen !== $now) { $c = axMerge(axDefaults(), load('ext')); $gen = $now; }
    return $c;
}

/** تغییر تنظیمات — کال‌بک آرایه‌ی کامل را با ارجاع می‌گیرد */
function axSet(callable $fn) {
    $r = mutate('ext', function (&$a) use ($fn) {
        $full = axMerge(axDefaults(), $a);
        $out  = $fn($full);
        $a    = $full;
        return $out === null ? true : $out;
    });
    $GLOBALS['__ax_gen'] = (int)($GLOBALS['__ax_gen'] ?? 0) + 1;   // کش را باطل کن
    return $r;
}

/** یک کلید تو در تو: axVal('stock.low_at', 3) */
function axVal($path, $default = null) {
    $cur = axCfg();
    foreach (explode('.', $path) as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
        $cur = $cur[$k];
    }
    return $cur;
}

function axLog($what, $detail = '') {
    axSet(function (&$c) use ($what, $detail) {
        $c['log'][] = ['at' => nowStr(), 'what' => $what, 'detail' => mb_substr((string)$detail, 0, 300)];
        if (count($c['log']) > 50) $c['log'] = array_slice($c['log'], -50);
    });
}

// ============================================================
// 📦 مخزن کانفیگ — فروش خودکار از موجودی
// ============================================================

/**
 * حجم‌های پذیرفته‌شده. کاربر باید عدد «رند» بدهد:
 * ۵۰۰ مگابایت یا مضرب‌های ۱ گیگابایت. ۵۵۰ یا ۵۴۸ قبول نیست.
 */
function axVolumeOk($mb) {
    $mb = (float)$mb;
    if ($mb <= 0 || $mb > 1024 * 1024) return false;         // سقف ۱ ترابایت
    if (abs($mb - round($mb)) > 0.0001) return false;         // اعشاری نه
    $mb = (int)round($mb);
    if ($mb < 1024) return $mb % 500 === 0;                   // زیر ۱ گیگ: مضرب ۵۰۰ مگ
    return $mb % 1024 === 0;                                  // بالای آن: گیگ کامل
}

/** ۵۰۰ → «۵۰۰ مگابایت» ، ۳۰۷۲ → «۳ گیگابایت» */
function axVolumeLabel($mb) {
    $mb = (int)$mb;
    if ($mb % 1024 === 0 && $mb >= 1024) return ($mb / 1024) . ' گیگابایت';
    return $mb . ' مگابایت';
}

/** فهرست حجم‌های رند تا سقف داده‌شده — برای دکمه‌های مینی‌اپ */
function axVolumeChoices($maxGb = 100) {
    $out = [500];
    for ($g = 1; $g <= (int)$maxGb; $g++) $out[] = $g * 1024;
    return $out;
}

/** شناسه‌ی مخزن یک محصول */
function axSku($itemId) { return preg_replace('/[^a-zA-Z0-9_.-]/', '_', (string)$itemId); }

/** چند تا در مخزن مانده */
function axStockCount($itemId) {
    $it = axVal('stock.items.' . axSku($itemId));
    return is_array($it['lines'] ?? null) ? count($it['lines']) : 0;
}

/** موجودی همه‌ی محصول‌ها: sku => تعداد */
function axStockAll() {
    $out = [];
    foreach ((array)axVal('stock.items', []) as $sku => $it) {
        $out[$sku] = ['name' => (string)($it['name'] ?? $sku), 'n' => count((array)($it['lines'] ?? []))];
    }
    return $out;
}

/** افزودن کانفیگ — هر خط یک کانفیگ، تکراری‌ها کنار گذاشته می‌شوند */
function axStockAdd($itemId, $name, $raw) {
    $sku   = axSku($itemId);
    $lines = preg_split('/\r\n|\r|\n/u', (string)$raw);
    $lines = array_values(array_filter(array_map('trim', $lines), fn($l) => $l !== ''));
    if (!$lines) return [0, 0];

    return axSet(function (&$c) use ($sku, $name, $lines) {
        if (!is_array($c['stock']['items'][$sku] ?? null))
            $c['stock']['items'][$sku] = ['name' => $name, 'lines' => [], 'sold' => 0];
        if ($name !== '') $c['stock']['items'][$sku]['name'] = $name;

        $have = array_flip($c['stock']['items'][$sku]['lines']);
        $add  = 0; $dup = 0;
        foreach ($lines as $l) {
            if (isset($have[$l])) { $dup++; continue; }
            $have[$l] = true;
            $c['stock']['items'][$sku]['lines'][] = $l;
            $add++;
        }
        return [$add, $dup];
    });
}

/**
 * یک کانفیگ از مخزن بردار — اتمیک.
 * همان قفلی که مصرف می‌کند، حذف هم می‌کند؛ پس دو سفارش همزمان
 * هرگز یک کانفیگ نمی‌گیرند.
 */
function axStockTake($itemId, $orderId = '') {
    $sku  = axSku($itemId);
    // axSet مقدار null را «موفق» می‌خواند، پس برای «چیزی نبود» از false استفاده می‌کنیم
    $line = axSet(function (&$c) use ($sku, $orderId) {
        $lines = $c['stock']['items'][$sku]['lines'] ?? null;
        if (!is_array($lines) || !$lines) return false;
        $line = array_shift($c['stock']['items'][$sku]['lines']);
        $c['stock']['items'][$sku]['sold'] = ((int)($c['stock']['items'][$sku]['sold'] ?? 0)) + 1;
        if ($orderId !== '') {
            $c['stock']['items'][$sku]['taken'][$orderId] = ['line' => $line, 'at' => nowStr()];
            if (count($c['stock']['items'][$sku]['taken']) > 200)
                $c['stock']['items'][$sku]['taken'] = array_slice($c['stock']['items'][$sku]['taken'], -200, null, true);
        }
        return $line;
    });
    return $line === false ? null : $line;
}

/** برگرداندن کانفیگ به مخزن — اگر تحویل شکست خورد */
function axStockReturn($itemId, $line) {
    $sku = axSku($itemId);
    axSet(function (&$c) use ($sku, $line) {
        if (!is_array($c['stock']['items'][$sku]['lines'] ?? null)) return;
        array_unshift($c['stock']['items'][$sku]['lines'], $line);
    });
}

function axStockClear($itemId) {
    $sku = axSku($itemId);
    return axSet(function (&$c) use ($sku) {
        $n = count((array)($c['stock']['items'][$sku]['lines'] ?? []));
        $c['stock']['items'][$sku]['lines'] = [];
        return $n;
    });
}

/**
 * تحویل خودکار از مخزن.
 * برگشت: [true, ''] یا [false, 'دلیل']
 */
function axStockDeliver($order) {
    if (empty(axVal('stock.on'))) return [false, 'مخزن خاموش است'];

    $itemId = (string)($order['item_id'] ?? '');
    $qty    = max(1, (int)($order['qty'] ?? 1));
    $uid    = (int)($order['user_id'] ?? 0);
    if ($itemId === '' || $uid === 0) return [false, 'سفارش ناقص'];

    if (axStockCount($itemId) < $qty) {
        sendMsg(BOT_TOKEN, $uid, strtr((string)axVal('stock.empty_text'), [
            '{code}' => h((string)($order['id'] ?? '')),
            '{item}' => h((string)($order['item_name'] ?? '')),
        ]));
        axNotifyAdmin("📦 <b>مخزن خالی شد</b>\n\nمحصول: <b>" . h((string)($order['item_name'] ?? $itemId)) . "</b>\n" .
                      "سفارش <code>" . h((string)($order['id'] ?? '')) . "</code> منتظر شارژ مخزن ماند.");
        return [false, 'موجودی کافی نیست'];
    }

    $taken = [];
    for ($i = 0; $i < $qty; $i++) {
        $line = axStockTake($itemId, (string)($order['id'] ?? ''));
        if ($line === null) break;
        $taken[] = $line;
    }
    if (!$taken) return [false, 'برداشت از مخزن ناموفق'];

    $text = strtr((string)axVal('stock.text'), [
        '{config}'   => h(implode("\n", $taken)),
        '{item}'     => h((string)($order['item_name'] ?? '')),
        '{qty}'      => (string)$qty,
        '{code}'     => h((string)($order['id'] ?? '')),
        '{amount}'   => fmtNum($order['total'] ?? 0),
        '{currency}' => h((string)($order['currency'] ?? 'تومان')),
        '{date}'     => h(nowStr()),
        '{field}'    => h((string)($order['field'] ?? '')),
    ]);

    $res = sendMsg(BOT_TOKEN, $uid, $text);
    if (empty($res['ok'])) {
        foreach (array_reverse($taken) as $l) axStockReturn($itemId, $l);
        return [false, 'ارسال به کاربر ناموفق: ' . ($res['description'] ?? '—')];
    }

    $left = axStockCount($itemId);
    if ($left <= (int)axVal('stock.low_at', 3)) {
        axNotifyAdmin("⚠️ <b>موجودی مخزن رو به اتمام</b>\n\n" .
                      "محصول: <b>" . h((string)($order['item_name'] ?? $itemId)) . "</b>\n" .
                      "باقی‌مانده: <b>" . $left . "</b> عدد");
    }
    axLog('stock_deliver', $itemId . ' × ' . $qty . ' → ' . $uid);
    return [true, ''];
}

function axNotifyAdmin($text, $kb = null) {
    if (defined('ADMIN_ID')) @sendMsg(BOT_TOKEN, ADMIN_ID, $text, $kb);
}

// ============================================================
// 🎁 سفارش دستی — فرم در کانال، دکمه‌ی «انجام شد»
// ============================================================

/** آیا این محصول دستی انجام می‌شود؟ */
function axIsManual($itemId) {
    $ids = (array)axVal('manual.items', []);
    return in_array((string)$itemId, array_map('strval', $ids), true);
}

function axManualToggle($itemId) {
    return axSet(function (&$c) use ($itemId) {
        $ids = array_map('strval', (array)($c['manual']['items'] ?? []));
        $i   = array_search((string)$itemId, $ids, true);
        if ($i === false) { $ids[] = (string)$itemId; $on = true; }
        else              { unset($ids[$i]); $on = false; }
        $c['manual']['items'] = array_values($ids);
        return $on;
    });
}

/** جای‌گذاری مقدارها در قالب‌ها */
function axFill($tpl, $order, $more = []) {
    $u = function_exists('getUser') ? (getUser((int)($order['user_id'] ?? 0)) ?: []) : [];
    $uname = !empty($order['username']) ? '@' . ltrim((string)$order['username'], '@')
           : (!empty($u['username']) ? '@' . $u['username'] : ($u['first_name'] ?? (string)($order['user_id'] ?? '—')));

    return strtr((string)$tpl, array_merge([
        '{item}'     => h((string)($order['item_name'] ?? '—')),
        '{emoji}'    => (string)($order['item_emoji'] ?? ''),
        '{qty}'      => fmtNum($order['qty'] ?? 1),
        '{unit}'     => h((string)($order['unit'] ?? '')),
        '{field}'    => h((string)($order['field'] ?? '—')),
        '{user}'     => h($uname),
        '{user_id}'  => (string)(int)($order['user_id'] ?? 0),
        '{amount}'   => fmtNum($order['total'] ?? 0),
        '{currency}' => h((string)($order['currency'] ?? 'تومان')),
        '{code}'     => h((string)($order['id'] ?? '—')),
        '{pay}'      => h(axPayLabel($order['pay'] ?? '')),
        '{app}'      => h(axAppName($order['app'] ?? '')),
        '{date}'     => h(nowStr()),
        '{status}'   => h(class_exists('MaOrder') ? MaOrder::statusLabel($order['status'] ?? '') : ''),
    ], $more));
}

function axPayLabel($p) {
    return ['wallet' => 'کیف پول', 'card' => 'کارت به کارت', 'crypto' => 'رمزارز',
            'trx' => 'ترون', 'usdt' => 'تتر'][$p] ?? ($p === '' ? '—' : $p);
}

function axAppName($app) {
    return ['tg' => 'خدمات تلگرام', 'cfg' => 'فروش کانفیگ'][$app] ?? '—';
}

/** فرم سفارش را در کانال بگذار، با دکمه‌ی «انجام شد» */
function axManualPost($order) {
    $m = axCfg()['manual'];
    if (empty($m['on'])) return [false, 'بخش دستی خاموش است'];

    $chat = trim((string)$m['chat_id']);
    if ($chat === '') {
        axNotifyAdmin("🔴 <b>کانال سفارش دستی تنظیم نشده!</b>\n\n" .
                      "سفارش <code>" . h((string)$order['id']) . "</code> جایی ارسال نشد.\n" .
                      "پنل ← 🧩 افزونه ← 🎁 سفارش دستی");
        return [false, 'کانال تنظیم نشده'];
    }

    $rows  = [[['text' => (string)$m['btn'], 'callback_data' => 'axdone_' . $order['id'], 'style' => 'success']]];
    $extra = [];
    if ((int)$m['thread_id'] > 0) $extra['message_thread_id'] = (int)$m['thread_id'];

    $res = sendMsg(BOT_TOKEN, $chat, axFill($m['form'], $order), inlineKb($rows), $extra);
    if (empty($res['ok'])) {
        axNotifyAdmin("⚠️ <b>فرم سفارش ارسال نشد</b>\n\n" .
                      "کانال: <code>" . h($chat) . "</code>\n" .
                      "خطا: <code>" . h($res['description'] ?? '—') . "</code>\n\n" .
                      "ربات را در کانال ادمین کنید.");
        return [false, (string)($res['description'] ?? 'خطا')];
    }

    // شماره‌ی پیام را نگه می‌داریم تا بعد از انجام، همان را ویرایش کنیم
    if (class_exists('MaOrder')) {
        MaOrder::set($order['id'], function (&$x) use ($res, $chat) {
            $x['manual_chat'] = $chat;
            $x['manual_msg']  = (int)($res['result']['message_id'] ?? 0);
            $x['manual_at']   = nowStr();
        });
    }
    axLog('manual_post', $order['id']);
    return [true, ''];
}

/**
 * ادمین دکمه‌ی «سفارش انجام شد» را زد.
 * برگشت: [true,''] یا [false,'دلیل']
 */
function axManualDone($orderId, $adminId, $adminName = '') {
    if (!class_exists('MaOrder')) return [false, 'مینی‌اپ در دسترس نیست'];
    $o = MaOrder::get($orderId);
    if (!$o) return [false, 'سفارش پیدا نشد'];
    if (($o['status'] ?? '') === MaOrder::DONE) return [false, 'قبلا انجام شده'];

    // قفل: فقط یک بار
    $claimed = MaOrder::set($orderId, function (&$x) {
        if (($x['status'] ?? '') === MaOrder::DONE) return false;
        $x['status'] = MaOrder::DONE;
        $x['delivered_at'] = nowStr();
        $x['delivered_by'] = 'manual';
        return true;
    });
    if (!$claimed) return [false, 'قبلا انجام شده'];

    $o = MaOrder::get($orderId);
    $m = axCfg()['manual'];

    sendMsg(BOT_TOKEN, (int)$o['user_id'], axFill($m['done_text'], $o));

    // فرم را در کانال علامت بزن
    if (!empty($o['manual_msg']) && !empty($o['manual_chat'])) {
        $mark = axFill($m['form'], $o) . "\n\n" .
                strtr((string)$m['done_mark'], [
                    '{admin}' => h($adminName !== '' ? $adminName : (string)$adminId),
                    '{date}'  => h(nowStr()),
                ]);
        @editMsg(BOT_TOKEN, $o['manual_chat'], (int)$o['manual_msg'], $mark, null);
    }

    axReportOrder($o, 'done');
    axLog('manual_done', $orderId . ' by ' . $adminId);
    return [true, ''];
}

// ============================================================
// 📊 گزارش سفارش مینی‌اپ‌ها — هر مینی‌اپ جدا
// ============================================================

/** $when: 'paid' یا 'done' */
function axReportOrder($order, $when = 'paid') {
    $app = (string)($order['app'] ?? '');
    $r   = axVal('report.' . $app);
    if (!is_array($r)) return;
    if (empty($r['on'])) return;
    if ($when === 'paid' && empty($r['on_paid'])) return;
    if ($when === 'done' && empty($r['on_done'])) return;

    $chat = trim((string)$r['chat_id']);
    if ($chat === '') return;

    // یک سفارش دو بار در یک حالت گزارش نشود
    if (class_exists('MaOrder')) {
        $fresh = MaOrder::set($order['id'], function (&$x) use ($when) {
            if (!empty($x['reported'][$when])) return false;
            $x['reported'][$when] = nowStr();
            return true;
        });
        if (!$fresh) return;
    }

    $rows = axButtonRows($r['buttons'] ?? [], !empty($r['btn_row']));
    $extra = [];
    if ((int)$r['thread_id'] > 0) $extra['message_thread_id'] = (int)$r['thread_id'];

    $res = sendMsg(BOT_TOKEN, $chat, axFill($r['text'], $order), $rows ? inlineKb($rows) : null, $extra);
    if (empty($res['ok'])) {
        if (class_exists('MaOrder')) MaOrder::set($order['id'], function (&$x) use ($when) { unset($x['reported'][$when]); });
        axNotifyAdmin("⚠️ <b>گزارش مینی‌اپ ارسال نشد</b>\n\n" .
                      "مینی‌اپ: <b>" . h(axAppName($app)) . "</b>\n" .
                      "گروه: <code>" . h($chat) . "</code>\n" .
                      "خطا: <code>" . h($res['description'] ?? '—') . "</code>");
    }
}

/** دکمه‌های لینک‌دار یک گزارش → ردیف‌های اینلاین */
function axButtonRows($buttons, $sameRow = true) {
    $line = [];
    foreach ((array)$buttons as $b) {
        if (empty($b['on'])) continue;
        $t = trim((string)($b['text'] ?? ''));
        $u = trim((string)($b['url'] ?? ''));
        if ($t === '' || $u === '') continue;
        $btn = ['text' => $t, 'url' => $u];
        if (function_exists('isStyle') && isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
        if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
        $line[] = $btn;
    }
    if (!$line) return [];
    if ($sameRow) return [$line];
    $rows = [];
    foreach ($line as $b) $rows[] = [$b];
    return $rows;
}

// ============================================================
// 💵 سود و قیمت — بخش ویژه‌ی مدیر
// ============================================================

/** آیا قیمت این محصول همیشه از صرافی می‌آید؟ (تون، ترون، تتر) */
function axIsAutoPriced($itemId, $category = '') {
    $auto = array_map('strval', (array)axVal('pricing.auto', []));
    $id   = strtolower((string)$itemId);
    $cat  = strtolower((string)$category);
    foreach ($auto as $a) {
        $a = strtolower($a);
        if ($a === '') continue;
        if ($id === $a || $cat === $a || str_contains($id, $a)) return true;
    }
    return false;
}

/**
 * قیمت نهایی یک محصول.
 * ترتیب: قیمت دستی مدیر → قیمت پایه + درصد سود دسته.
 * تون/ترون/تتر همیشه از نرخ زنده می‌آیند و قیمت دستی نمی‌پذیرند.
 */
function axPrice($itemId, $basePrice, $category = '') {
    $base = (float)$basePrice;
    if (empty(axVal('pricing.on'))) return $base;

    if (!axIsAutoPriced($itemId, $category)) {
        $fixed = axVal('pricing.fixed.' . axSku($itemId));
        if ($fixed !== null && (float)$fixed > 0) return (float)$fixed;
    }

    $m = axVal('pricing.margin.' . axSku($category));
    if ($m === null) $m = axVal('pricing.margin._all');
    if ($m !== null && (float)$m != 0.0) $base = $base * (1 + ((float)$m / 100));

    return $base;
}

function axSetFixed($itemId, $price) {
    axSet(function (&$c) use ($itemId, $price) {
        $sku = axSku($itemId);
        if ((float)$price <= 0) unset($c['pricing']['fixed'][$sku]);
        else $c['pricing']['fixed'][$sku] = (float)$price;
    });
}

function axSetMargin($category, $percent) {
    axSet(function (&$c) use ($category, $percent) {
        $c['pricing']['margin'][axSku($category)] = (float)$percent;
    });
}

// ============================================================
// 💱 نرخ ارز برای کل ربات — نوبیتکس / والکس
// ============================================================

/**
 * نرخ تومانی یک ارز برای هرجای ربات.
 * از موتور مینی‌اپ استفاده می‌کند تا یک منبع حقیقت داشته باشیم.
 */
function axRate($which, $fresh = false) {
    if (function_exists('maRate')) {
        $v = (float)maRate($which, $fresh);
        if ($v > 0) return $v;
    }
    return 0.0;
}

/** نرخ‌ها را یکجا تازه کن — از کران صدا زده می‌شود */
function axRatesRefresh() {
    if (empty(axVal('rates_auto'))) return [];
    $out = [];
    foreach (['usdt', 'ton', 'trx'] as $c) $out[$c] = axRate($c, true);
    return $out;
}

/** خط خوانا برای نمایش نرخ‌ها */
function axRatesText() {
    $n = ['usdt' => '💵 تتر', 'ton' => '💎 تون', 'trx' => '🔺 ترون'];
    $out = [];
    foreach ($n as $k => $label) {
        $v = axRate($k);
        $out[] = $label . ': ' . ($v > 0 ? '<b>' . fmtNum($v) . '</b> تومان' : '—');
    }
    return implode("\n", $out);
}

// ============================================================
// 👥 دکمه‌ی «پیوی‌ها و گروه‌ها» روی متن عضوگیری
// ============================================================

/** دکمه‌ای که زیر متن عضوگیری می‌نشیند */
function axMembersButton($orderId) {
    $t = trim((string)axVal('labels.members_btn'));
    if ($t === '') return null;
    return btnCb($t, 'axsrc_' . $orderId, 'nav');
}

/**
 * منابع عضوگیری یک سفارش را نشان بده:
 * پیوی‌ها (ربات‌ها/حساب‌ها) و گروه‌ها/کانال‌های همکار.
 */
function axMembersText($orderId) {
    $cmp = function_exists('Campaign::forOrder') || class_exists('Campaign')
         ? Campaign::forOrder($orderId) : null;

    $head = (string)axVal('labels.members_head');
    if (!$cmp) return $head . "\n" . (string)axVal('labels.members_none');

    $pv = []; $gp = [];
    foreach ((array)($cmp['bots'] ?? []) as $b) {
        $s = is_array($b) ? (string)($b['username'] ?? $b['title'] ?? $b['id'] ?? '') : (string)$b;
        if (trim($s) !== '') $pv[] = $s;
    }
    foreach ((array)($cmp['partners'] ?? []) as $p) {
        $s = is_array($p) ? (string)($p['title'] ?? $p['username'] ?? $p['id'] ?? '') : (string)$p;
        if (trim($s) !== '') $gp[] = $s;
    }

    $t  = $head . "\n";
    $t .= "📣 کانال: <b>" . h((string)($cmp['title'] ?? '—')) . "</b>\n";
    $t .= "🎯 هدف: <b>" . number_format((int)($cmp['target'] ?? 0)) . "</b> نفر · " .
          "تحویل‌شده: <b>" . number_format(count((array)($cmp['joined'] ?? []))) . "</b>\n\n";

    $t .= "<b>" . h((string)axVal('labels.members_pv')) . "</b> (" . count($pv) . ")\n";
    $t .= $pv ? axList($pv) : "—\n";
    $t .= "\n<b>" . h((string)axVal('labels.members_gp')) . "</b> (" . count($gp) . ")\n";
    $t .= $gp ? axList($gp) : "—\n";

    if (!$pv && !$gp) $t .= "\n" . (string)axVal('labels.members_none');
    return $t;
}

function axList($items) {
    $out = '';
    foreach (array_slice($items, 0, 40) as $i => $x) {
        $s = trim((string)$x);
        $out .= '  ' . ($i + 1) . '. ' . (str_starts_with($s, '@') ? h($s) : '<code>' . h($s) . '</code>') . "\n";
    }
    if (count($items) > 40) $out .= '  … و ' . (count($items) - 40) . " مورد دیگر\n";
    return $out;
}

// ============================================================
// 👑 پنل مدیریت افزونه — بار پنل اصلی را سبک می‌کند
// ============================================================

function axShow($chatId, $msgId, $text, $rows) {
    $kb = inlineKb($rows);
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb);
    else        sendMsg(BOT_TOKEN, $chatId, $text, $kb);
}

function axHome($chatId, $msgId = null) {
    $c  = axCfg();
    $st = axStockAll();
    $tot = 0; foreach ($st as $s) $tot += $s['n'];

    $t  = "🧩 <b>افزونه‌ی مدیریت</b>\n";
    $t .= "<i>نسخه " . AX_VERSION . "</i>\n\n";
    $t .= "📦 مخزن کانفیگ: <b>" . count($st) . "</b> محصول · <b>" . number_format($tot) . "</b> عدد\n";
    $t .= "🎁 سفارش دستی: " . (!empty($c['manual']['on']) && trim((string)$c['manual']['chat_id']) !== '' ? '✅ فعال' : '⚪️ خاموش') . "\n";
    $t .= "📊 گزارش تلگرام: " . (!empty($c['report']['tg']['on']) ? '✅' : '⚪️') .
          " · کانفیگ: " . (!empty($c['report']['cfg']['on']) ? '✅' : '⚪️') . "\n";
    $t .= "💵 سود و قیمت: " . (!empty($c['pricing']['on']) ? '✅ فعال' : '⚪️ خاموش') . "\n\n";
    $t .= "💱 <b>نرخ زنده</b>\n" . axRatesText() . "\n";

    axShow($chatId, $msgId, $t, [
        [btnCb('📦 مخزن کانفیگ', 'ax_stock', 'admin')],
        [btnCb('🎁 سفارش دستی گیفت/تون', 'ax_manual', 'admin')],
        [btnCb('📊 گزارش خدمات تلگرام', 'ax_rep_tg', 'admin'),
         btnCb('📊 گزارش کانفیگ', 'ax_rep_cfg', 'admin')],
        [btnCb('💵 سود و قیمت', 'ax_price', 'admin')],
        [btnCb('💱 نرخ ارز', 'ax_rates', 'admin'),
         btnCb('✍️ متن‌ها', 'ax_texts', 'admin')],
        [btnCb('📜 رویدادها', 'ax_log', 'admin')],
        [btnCb('🔙 بازگشت', 'admin', 'nav')],
    ]);
}

// ---------- 📦 مخزن ----------

function axStockHome($chatId, $msgId) {
    $st = axStockAll();
    $t  = "📦 <b>مخزن کانفیگ</b>\n\n";
    $t .= "کانفیگ‌ها را اینجا می‌ریزید، ربات بعد از هر خرید خودکار یکی می‌فرستد.\n\n";
    if (!$st) $t .= "<i>هنوز محصولی در مخزن نیست.</i>\n";
    else foreach ($st as $sku => $s) {
        $warn = $s['n'] <= (int)axVal('stock.low_at', 3) ? ' ⚠️' : '';
        $t .= "• <b>" . h($s['name']) . "</b> — <code>" . number_format($s['n']) . "</code> عدد" . $warn . "\n";
    }
    $t .= "\n🔔 هشدار کمبود زیر <b>" . (int)axVal('stock.low_at', 3) . "</b> عدد";

    $rows = [];
    foreach ($st as $sku => $s)
        $rows[] = [btnCb('🗂 ' . mb_substr($s['name'], 0, 22) . ' (' . $s['n'] . ')', 'axsk_' . $sku, 'admin')];
    $rows[] = [btnCb('➕ افزودن کانفیگ', 'axsadd', 'admin')];
    $rows[] = [btnCb((!empty(axVal('stock.on')) ? '🟢 روشن' : '🔴 خاموش'), 'axstog', 'admin'),
               btnCb('🔔 حد هشدار', 'axslow', 'admin')];
    $rows[] = [btnCb('✍️ متن تحویل', 'axstxt', 'admin'),
               btnCb('✍️ متن ناموجود', 'axsemp', 'admin')];
    $rows[] = [btnCb('🔙 بازگشت', 'ax_home', 'nav')];
    axShow($chatId, $msgId, $t, $rows);
}

function axStockItem($chatId, $msgId, $sku) {
    $it = axVal('stock.items.' . $sku);
    if (!is_array($it)) { axStockHome($chatId, $msgId); return; }
    $lines = (array)($it['lines'] ?? []);

    $t  = "🗂 <b>" . h((string)($it['name'] ?? $sku)) . "</b>\n\n";
    $t .= "شناسه: <code>" . h($sku) . "</code>\n";
    $t .= "موجودی: <b>" . number_format(count($lines)) . "</b> عدد\n";
    $t .= "فروخته‌شده: <b>" . number_format((int)($it['sold'] ?? 0)) . "</b>\n\n";
    if ($lines) {
        $t .= "<b>سه مورد اول:</b>\n";
        foreach (array_slice($lines, 0, 3) as $l)
            $t .= "<code>" . h(mb_substr($l, 0, 70)) . (mb_strlen($l) > 70 ? '…' : '') . "</code>\n";
    }

    axShow($chatId, $msgId, $t, [
        [btnCb('➕ افزودن به این محصول', 'axsadd_' . $sku, 'admin')],
        [btnCb('🗑 خالی کردن مخزن', 'axsclr_' . $sku, 'danger')],
        [btnCb('🔙 بازگشت', 'ax_stock', 'nav')],
    ]);
}

// ---------- 🎁 سفارش دستی ----------

function axManualHome($chatId, $msgId) {
    $m = axCfg()['manual'];
    $t  = "🎁 <b>سفارش دستی</b>\n\n";
    $t .= "برای گیفت‌هایی مثل تدی یا خرید تون که خودکار نیستند:\n";
    $t .= "بعد از پرداخت مشتری، فرم سفارش در کانال شما می‌افتد.\n";
    $t .= "شما سفارش را انجام می‌دهید و دکمه‌ی زیر فرم را می‌زنید؛\n";
    $t .= "همان لحظه پیام «سفارش انجام شد» برای مشتری می‌رود.\n\n";
    $t .= "وضعیت: " . (!empty($m['on']) ? '🟢 روشن' : '🔴 خاموش') . "\n";
    $t .= "کانال: " . (trim((string)$m['chat_id']) !== ''
          ? '<code>' . h((string)$m['chat_id']) . '</code>' . ((int)$m['thread_id'] > 0 ? ' · تاپیک ' . (int)$m['thread_id'] : '')
          : '<i>تنظیم نشده</i>') . "\n";
    $t .= "محصول‌های دستی: <b>" . count((array)$m['items']) . "</b>\n";
    if ($m['items']) $t .= "<code>" . h(implode('، ', array_slice((array)$m['items'], 0, 12))) . "</code>\n";

    axShow($chatId, $msgId, $t, [
        [btnCb((!empty($m['on']) ? '🟢 روشن' : '🔴 خاموش'), 'axmtog', 'admin')],
        [btnCb('📢 کانال سفارش', 'axmchat', 'admin')],
        [btnCb('🎯 محصول‌های دستی', 'axmitems', 'admin')],
        [btnCb('✍️ متن فرم', 'axmform', 'admin'),
         btnCb('✍️ متن انجام شد', 'axmdone', 'admin')],
        [btnCb('🔘 متن دکمه', 'axmbtn', 'admin')],
        [btnCb('🔙 بازگشت', 'ax_home', 'nav')],
    ]);
}

function axManualItems($chatId, $msgId) {
    $ids  = array_map('strval', (array)axVal('manual.items', []));
    $t  = "🎯 <b>محصول‌های دستی</b>\n\n";
    $t .= "هر محصولی که اینجا روشن باشد، خودکار انجام نمی‌شود؛\n";
    $t .= "فرمش به کانال می‌رود تا خودتان انجامش دهید.\n\n";

    $rows = [];
    foreach (axCatalogItems() as $it) {
        $on = in_array((string)$it['id'], $ids, true);
        $rows[] = [btnCb(($on ? '✅ ' : '⚪️ ') . mb_substr($it['name'], 0, 26), 'axmi_' . $it['id'], 'admin')];
    }
    if (!$rows) $t .= "<i>محصولی پیدا نشد. اول در مینی‌اپ محصول بسازید.</i>\n";
    $rows[] = [btnCb('🔙 بازگشت', 'ax_manual', 'nav')];
    axShow($chatId, $msgId, $t, array_slice($rows, 0, 60));
}

/** همه‌ی محصول‌های دو مینی‌اپ — [id، name، category، app] */
function axCatalogItems() {
    $out = [];
    if (!function_exists('maCfg')) return $out;
    $c = maCfg();
    foreach (['tg', 'cfg'] as $app) {
        foreach ((array)($c[$app]['cats'] ?? []) as $cat) {
            foreach ((array)($cat['items'] ?? []) as $it) {
                if (!is_array($it) || empty($it['id'])) continue;
                $out[] = [
                    'id'       => (string)$it['id'],
                    'name'     => (string)($it['name'] ?? $it['id']),
                    'category' => (string)($cat['id'] ?? ''),
                    'app'      => $app,
                    'price'    => (float)($it['price'] ?? 0),
                ];
            }
        }
    }
    return $out;
}

// ---------- 📊 گزارش ----------

function axReportHome($chatId, $msgId, $app) {
    $r = axVal('report.' . $app);
    $t  = "📊 <b>گزارش " . h(axAppName($app)) . "</b>\n\n";
    $t .= "هر سفارش این مینی‌اپ در کانال/گروه شما گزارش می‌شود.\n\n";
    $t .= "وضعیت: " . (!empty($r['on']) ? '🟢 روشن' : '🔴 خاموش') . "\n";
    $t .= "مقصد: " . (trim((string)$r['chat_id']) !== ''
          ? '<code>' . h((string)$r['chat_id']) . '</code>' . ((int)$r['thread_id'] > 0 ? ' · تاپیک ' . (int)$r['thread_id'] : '')
          : '<i>تنظیم نشده</i>') . "\n";
    $t .= "زمان: " . (!empty($r['on_paid']) ? 'پرداخت ✅ ' : '') . (!empty($r['on_done']) ? 'تحویل ✅' : '') . "\n\n";
    $t .= "<b>متن فعلی:</b>\n" . $r['text'] . "\n\n";
    $t .= "<i>کلیدها: {item} {qty} {field} {user} {user_id} {amount} {currency} {pay} {code} {app} {date} {status}</i>";

    axShow($chatId, $msgId, $t, [
        [btnCb((!empty($r['on']) ? '🟢 روشن' : '🔴 خاموش'), 'axrtog_' . $app, 'admin')],
        [btnCb('📢 مقصد گزارش', 'axrchat_' . $app, 'admin')],
        [btnCb('✍️ متن گزارش', 'axrtxt_' . $app, 'admin')],
        [btnCb((!empty($r['on_paid']) ? '✅' : '⚪️') . ' هنگام پرداخت', 'axrpaid_' . $app, 'admin'),
         btnCb((!empty($r['on_done']) ? '✅' : '⚪️') . ' هنگام تحویل', 'axrdone_' . $app, 'admin')],
        [btnCb('🧪 ارسال آزمایشی', 'axrtest_' . $app, 'admin')],
        [btnCb('🔙 بازگشت', 'ax_home', 'nav')],
    ]);
}

// ---------- 💵 قیمت ----------

function axPriceHome($chatId, $msgId) {
    $p = axCfg()['pricing'];
    $t  = "💵 <b>سود و قیمت</b>\n\n";
    $t .= "درصد سود روی قیمت پایه اعمال می‌شود.\n";
    $t .= "تون، ترون و تتر همیشه نرخ زنده‌ی صرافی را می‌گیرند و قیمت دستی نمی‌پذیرند.\n\n";
    $t .= "وضعیت: " . (!empty($p['on']) ? '🟢 روشن' : '🔴 خاموش') . "\n";
    $t .= "سود عمومی: <b>" . fmtNum($p['margin']['_all'] ?? 0) . "%</b>\n";
    $n = count(array_filter((array)$p['fixed'], fn($x) => (float)$x > 0));
    $t .= "قیمت دستی: <b>" . $n . "</b> محصول\n\n";
    $t .= "💱 <b>نرخ زنده</b>\n" . axRatesText();

    $rows = [
        [btnCb((!empty($p['on']) ? '🟢 روشن' : '🔴 خاموش'), 'axptog', 'admin')],
        [btnCb('📈 سود عمومی', 'axpall', 'admin')],
        [btnCb('🏷 قیمت دستی محصول‌ها', 'axpfix', 'admin')],
        [btnCb('🔙 بازگشت', 'ax_home', 'nav')],
    ];
    axShow($chatId, $msgId, $t, $rows);
}

function axPriceList($chatId, $msgId) {
    $fixed = (array)axVal('pricing.fixed', []);
    $t  = "🏷 <b>قیمت دستی محصول‌ها</b>\n\n";
    $t .= "روی هر محصول بزنید و قیمت تومانی بدهید. <code>0</code> یعنی خودکار.\n\n";
    $rows = [];
    foreach (axCatalogItems() as $it) {
        if (axIsAutoPriced($it['id'], $it['category'])) continue;
        $f = (float)($fixed[axSku($it['id'])] ?? 0);
        $rows[] = [btnCb(($f > 0 ? '🏷 ' : '⚪️ ') . mb_substr($it['name'], 0, 20) .
                         ($f > 0 ? ' — ' . fmtNum($f) : ''), 'axpf_' . $it['id'], 'admin')];
    }
    if (!$rows) $t .= "<i>محصولی پیدا نشد.</i>\n";
    $rows[] = [btnCb('🔙 بازگشت', 'ax_price', 'nav')];
    axShow($chatId, $msgId, $t, array_slice($rows, 0, 60));
}

// ---------- ✍️ متن‌ها ----------

function axTextsHome($chatId, $msgId) {
    $l = axCfg()['labels'];
    $t  = "✍️ <b>متن‌ها و برچسب دکمه‌ها</b>\n\n";
    $t .= "دکمه‌ی عضوگیری: <b>" . h((string)$l['members_btn']) . "</b>\n";
    $t .= "برچسب پیوی: <b>" . h((string)$l['members_pv']) . "</b>\n";
    $t .= "برچسب گروه: <b>" . h((string)$l['members_gp']) . "</b>\n";
    $t .= "دکمه‌های پشتیبانی: <b>" . (!empty($l['sup_stack']) ? 'زیر هم' : 'کنار هم') . "</b>\n";

    axShow($chatId, $msgId, $t, [
        [btnCb('🧾 متن تایید سفارش مینی‌اپ', 'axinv', 'admin')],
        [btnCb((!empty(axVal('texts.invoice_on')) ? '🟢 متن سفارشی روشن' : '⚪️ متن پیش‌فرض'), 'axinvtog', 'admin')],
        [btnCb('👥 متن دکمه عضوگیری', 'axtx_members_btn', 'admin')],
        [btnCb('📝 سربرگ منابع', 'axtx_members_head', 'admin')],
        [btnCb('💬 برچسب پیوی', 'axtx_members_pv', 'admin'),
         btnCb('👥 برچسب گروه', 'axtx_members_gp', 'admin')],
        [btnCb((!empty($l['sup_stack']) ? '⬇️ پشتیبانی: زیر هم' : '↔️ پشتیبانی: کنار هم'), 'axsup', 'admin')],
        [btnCb('🔙 بازگشت', 'ax_home', 'nav')],
    ]);
}

// ============================================================
// 🎛 مسیریاب callback افزونه
// ============================================================

/** برگشت true یعنی این callback را ما جواب دادیم */
function axCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {

    // --- دکمه‌ی «سفارش انجام شد» زیر فرم کانال ---
    if (str_starts_with($data, 'axdone_')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒 فقط مدیر', true); return true; }
        [$ok, $err] = axManualDone(substr($data, 7), $uid, '');
        answerCb(BOT_TOKEN, $cbId, $ok ? '✅ به مشتری اطلاع داده شد' : '⚠️ ' . $err, true);
        return true;
    }

    // --- دکمه‌ی «پیوی‌ها و گروه‌ها» زیر متن عضوگیری (برای همه) ---
    if (str_starts_with($data, 'axsrc_')) {
        answerCb(BOT_TOKEN, $cbId);
        $oid = substr($data, 6);
        $o   = function_exists('Order::get') || class_exists('Order') ? Order::get($oid) : null;
        if ($o && (int)$o['user_id'] !== (int)$uid && !$isAdmin) return true;   // سفارش دیگران نه
        sendMsg(BOT_TOKEN, $chatId, axMembersText($oid));
        return true;
    }

    if (!str_starts_with($data, 'ax')) return false;
    if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒 فقط مدیر', true); return true; }

    $ack = fn($m = '') => answerCb(BOT_TOKEN, $cbId, $m, $m !== '');

    // ---------- صفحه‌ها ----------
    if ($data === 'ax_home')   { $ack(); axHome($chatId, $msgId);        return true; }
    if ($data === 'ax_stock')  { $ack(); axStockHome($chatId, $msgId);   return true; }
    if ($data === 'ax_manual') { $ack(); axManualHome($chatId, $msgId);  return true; }
    if ($data === 'ax_price')  { $ack(); axPriceHome($chatId, $msgId);   return true; }
    if ($data === 'ax_texts')  { $ack(); axTextsHome($chatId, $msgId);   return true; }
    if ($data === 'axmitems')  { $ack(); axManualItems($chatId, $msgId); return true; }
    if ($data === 'axpfix')    { $ack(); axPriceList($chatId, $msgId);   return true; }
    if ($data === 'ax_rep_tg') { $ack(); axReportHome($chatId, $msgId, 'tg');  return true; }
    if ($data === 'ax_rep_cfg'){ $ack(); axReportHome($chatId, $msgId, 'cfg'); return true; }

    if ($data === 'ax_rates') {
        $ack('⏳ در حال گرفتن نرخ…');
        axRatesRefresh();
        $t = "💱 <b>نرخ ارز</b>\n\n" . axRatesText() . "\n\n" .
             "منبع و تنظیمات دقیق در: پنل ← 🚀 مینی‌اپ‌ها ← 💱 نرخ ارز\n" .
             "نرخ‌ها هر چند دقیقه یک‌بار خودکار تازه می‌شوند.";
        axShow($chatId, $msgId, $t, [
            [btnCb('🔄 تازه‌سازی', 'ax_rates', 'admin')],
            [btnCb('🔙 بازگشت', 'ax_home', 'nav')],
        ]);
        return true;
    }

    if ($data === 'ax_log') {
        $ack();
        $log = array_reverse((array)axVal('log', []));
        $t = "📜 <b>رویدادهای اخیر</b>\n\n";
        if (!$log) $t .= "<i>هنوز رویدادی ثبت نشده.</i>";
        foreach (array_slice($log, 0, 25) as $e)
            $t .= "• <code>" . h((string)$e['at']) . "</code> — " . h((string)$e['what']) .
                  (($e['detail'] ?? '') !== '' ? ' · ' . h((string)$e['detail']) : '') . "\n";
        axShow($chatId, $msgId, $t, [[btnCb('🔙 بازگشت', 'ax_home', 'nav')]]);
        return true;
    }

    // ---------- کلیدهای روشن/خاموش ----------
    $toggles = [
        'axstog' => ['stock', 'on',       fn($c, $v) => axStockHome($c, $v)],
        'axmtog' => ['manual', 'on',      fn($c, $v) => axManualHome($c, $v)],
        'axptog' => ['pricing', 'on',     fn($c, $v) => axPriceHome($c, $v)],
    ];
    if (isset($toggles[$data])) {
        [$sec, $key, $back] = $toggles[$data];
        $now = axSet(function (&$c) use ($sec, $key) {
            $c[$sec][$key] = empty($c[$sec][$key]);
            return !empty($c[$sec][$key]);
        });
        $ack($now ? '🟢 روشن شد' : '🔴 خاموش شد');
        $back($chatId, $msgId);
        return true;
    }

    if ($data === 'axinvtog') {
        $now = axSet(function (&$c) { $c['texts']['invoice_on'] = empty($c['texts']['invoice_on']); return !empty($c['texts']['invoice_on']); });
        $ack($now ? '🟢 متن سفارشی' : '⚪️ متن پیش‌فرض');
        axTextsHome($chatId, $msgId);
        return true;
    }

    if ($data === 'axsup') {
        $now = axSet(function (&$c) { $c['labels']['sup_stack'] = empty($c['labels']['sup_stack']); return !empty($c['labels']['sup_stack']); });
        $ack($now ? '⬇️ زیر هم' : '↔️ کنار هم');
        axTextsHome($chatId, $msgId);
        return true;
    }

    foreach (['axrtog' => 'on', 'axrpaid' => 'on_paid', 'axrdone' => 'on_done'] as $pre => $key) {
        if (str_starts_with($data, $pre . '_')) {
            $app = substr($data, strlen($pre) + 1);
            if (!in_array($app, ['tg', 'cfg'], true)) { $ack(); return true; }
            axSet(function (&$c) use ($app, $key) { $c['report'][$app][$key] = empty($c['report'][$app][$key]); });
            $ack('✅');
            axReportHome($chatId, $msgId, $app);
            return true;
        }
    }

    if (str_starts_with($data, 'axmi_')) {
        $on = axManualToggle(substr($data, 5));
        $ack($on ? '✅ دستی شد' : '⚪️ خودکار شد');
        axManualItems($chatId, $msgId);
        return true;
    }

    if (str_starts_with($data, 'axsk_'))  { $ack(); axStockItem($chatId, $msgId, substr($data, 5)); return true; }
    if (str_starts_with($data, 'axsclr_')) {
        $n = axStockClear(substr($data, 7));
        $ack('🗑 ' . (int)$n . ' کانفیگ پاک شد');
        axStockHome($chatId, $msgId);
        return true;
    }

    if (str_starts_with($data, 'axrtest_')) {
        $app = substr($data, 8);
        $ack('⏳');
        $fake = ['id' => 'ma_test', 'app' => $app, 'user_id' => $uid, 'username' => '',
                 'item_name' => 'محصول آزمایشی', 'item_emoji' => '🧪', 'qty' => 1,
                 'field' => '@example', 'total' => 100000, 'currency' => 'تومان',
                 'pay' => 'wallet', 'status' => 'done'];
        $r = axVal('report.' . $app);
        $chat = trim((string)$r['chat_id']);
        if ($chat === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ اول مقصد گزارش را تنظیم کنید."); return true; }
        $rows = axButtonRows($r['buttons'] ?? [], !empty($r['btn_row']));
        $extra = (int)$r['thread_id'] > 0 ? ['message_thread_id' => (int)$r['thread_id']] : [];
        $res = sendMsg(BOT_TOKEN, $chat, axFill($r['text'], $fake), $rows ? inlineKb($rows) : null, $extra);
        sendMsg(BOT_TOKEN, $chatId, !empty($res['ok'])
            ? "✅ گزارش آزمایشی ارسال شد."
            : "❌ ارسال نشد:\n<code>" . h($res['description'] ?? '—') . "</code>\n\nربات را در آن گروه ادمین کنید.");
        return true;
    }

    // ---------- ورودی‌های متنی ----------
    $asks = [
        'axsadd'  => ['ax_stock_add',  "📦 کانفیگ‌ها را بفرستید — <b>هر خط یک کانفیگ</b>.\n\nاول یک خط بنویسید:\n<code>نام محصول</code>\nبعد از خط بعد، کانفیگ‌ها.\n\nتکراری‌ها خودکار حذف می‌شوند."],
        'axslow'  => ['ax_stock_low',  "🔔 زیر چند عدد هشدار کمبود بیاید؟ (فقط عدد)"],
        'axstxt'  => ['ax_stock_text', "✍️ متن تحویل کانفیگ را بفرستید.\n\nکلیدها: <code>{config}</code> <code>{item}</code> <code>{qty}</code> <code>{code}</code> <code>{amount}</code> <code>{date}</code>\n\nمی‌توانید ایموجی پریمیوم و نقل‌قول بگذارید."],
        'axsemp'  => ['ax_stock_empty',"✍️ متن «موجودی تمام شد» را بفرستید.\n\nکلیدها: <code>{code}</code> <code>{item}</code>"],
        'axmchat' => ['ax_manual_chat',"📢 لینک یا آیدی کانال سفارش دستی را بفرستید.\n\nنمونه:\n<code>https://t.me/c/1234567890/5</code>\n<code>@mychannel</code>\n<code>-1001234567890</code>\n\n⚠️ ربات باید در آن کانال ادمین باشد."],
        'axmform' => ['ax_manual_form',"✍️ متن فرم سفارش را بفرستید.\n\nکلیدها: <code>{item} {qty} {field} {user} {user_id} {amount} {currency} {code} {pay} {app} {date}</code>"],
        'axmdone' => ['ax_manual_done',"✍️ متن «سفارش انجام شد» را بفرستید.\n\nکلیدها: <code>{item} {qty} {field} {code} {amount} {date}</code>"],
        'axmbtn'  => ['ax_manual_btn', "🔘 متن دکمه‌ی زیر فرم را بفرستید.\n\nالان: <code>" . h((string)axVal('manual.btn')) . "</code>"],
        'axpall'  => ['ax_price_all',  "📈 درصد سود عمومی را بفرستید (فقط عدد).\n\nمثلا <code>12</code> یعنی ۱۲٪ روی قیمت پایه.\n<code>0</code> یعنی بدون سود."],
        'axinv'   => ['ax_invoice',    "🧾 متن تایید سفارش مینی‌اپ‌ها را بفرستید.\n\nهمین‌جا می‌توانید ایموجی پریمیوم بگذارید و بخش‌ها را نقل‌قول (quote) کنید — عینا حفظ می‌شود.\n\nکلیدها: <code>{item} {qty} {unit} {field} {unit_price} {amount} {currency} {balance} {code} {app} {date}</code>"],
    ];
    if (isset($asks[$data])) {
        [$st, $msg] = $asks[$data];
        setState($uid, $st);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, $msg, inlineKb([[btnCb('❌ انصراف', 'ax_home', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'axsadd_')) {
        setState($uid, 'ax_stock_add', ['sku' => substr($data, 7)]);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, "📦 کانفیگ‌ها را بفرستید — هر خط یک کانفیگ.",
            inlineKb([[btnCb('❌ انصراف', 'ax_stock', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'axrchat_') || str_starts_with($data, 'axrtxt_')) {
        $isTxt = str_starts_with($data, 'axrtxt_');
        $app   = substr($data, $isTxt ? 7 : 8);
        if (!in_array($app, ['tg', 'cfg'], true)) { $ack(); return true; }
        setState($uid, $isTxt ? 'ax_rep_text' : 'ax_rep_chat', ['app' => $app]);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, $isTxt
            ? "✍️ متن گزارش <b>" . h(axAppName($app)) . "</b> را بفرستید.\n\nکلیدها: <code>{item} {qty} {field} {user} {user_id} {amount} {currency} {pay} {code} {app} {date} {status}</code>"
            : "📢 لینک یا آیدی مقصد گزارش <b>" . h(axAppName($app)) . "</b> را بفرستید.",
            inlineKb([[btnCb('❌ انصراف', 'ax_rep_' . $app, 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'axpf_')) {
        setState($uid, 'ax_price_fix', ['item' => substr($data, 5)]);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, "🏷 قیمت تومانی این محصول را بفرستید.\n<code>0</code> یعنی برگردد به حالت خودکار.",
            inlineKb([[btnCb('❌ انصراف', 'axpfix', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'axtx_')) {
        $key = substr($data, 5);
        if (!array_key_exists($key, axDefaults()['labels'])) { $ack(); return true; }
        setState($uid, 'ax_label', ['key' => $key]);
        $ack();
        sendMsg(BOT_TOKEN, $chatId, "✍️ متن تازه را بفرستید.\n\nالان:\n<code>" . h((string)axVal('labels.' . $key)) . "</code>",
            inlineKb([[btnCb('❌ انصراف', 'ax_texts', 'cancel')]]));
        return true;
    }

    $ack();
    return true;
}

// ============================================================
// ✍️ مسیریاب ورودی متنی افزونه
// ============================================================

/** برگشت true یعنی این پیام را ما مصرف کردیم */
function axStateHandle($action, $sd, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'ax_')) return false;

    $plain = trim((string)($msg['text'] ?? ''));
    // متن با ایموجی پریمیوم و نقل‌قول — دقیقا مثل ربات مادر
    $rich  = function_exists('msgHtml') ? msgHtml($msg) : $plain;

    $done = function ($text, $back = 'ax_home') use ($uid, $chatId) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[btnCb('🔙 بازگشت', $back, 'nav')]]));
    };

    switch ($action) {

        case 'ax_stock_add': {
            $sku = (string)($sd['sku'] ?? '');
            $raw = $plain;
            if ($raw === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متنی نیامد."); return true; }

            if ($sku === '') {
                // خط اول = نام محصول
                $lines = preg_split('/\r\n|\r|\n/u', $raw);
                $name  = trim(array_shift($lines));
                if ($name === '' || !$lines) {
                    sendMsg(BOT_TOKEN, $chatId, "⚠️ اول یک خط نام محصول، بعد کانفیگ‌ها.");
                    return true;
                }
                $sku = axSku($name);
                $raw = implode("\n", $lines);
            } else {
                $name = (string)(axVal('stock.items.' . $sku . '.name') ?? $sku);
            }

            [$add, $dup] = axStockAdd($sku, $name, $raw);
            $done("📦 <b>" . h($name) . "</b>\n\n" .
                  "➕ افزوده شد: <b>" . $add . "</b>\n" .
                  ($dup ? "♻️ تکراری (رد شد): <b>" . $dup . "</b>\n" : '') .
                  "📊 موجودی کل: <b>" . axStockCount($sku) . "</b>", 'ax_stock');
            axLog('stock_add', $sku . ' +' . $add);
            return true;
        }

        case 'ax_stock_low':
            axSet(function (&$c) use ($plain) { $c['stock']['low_at'] = max(0, (int)axDigits($plain)); });
            $done("🔔 حد هشدار روی <b>" . (int)axVal('stock.low_at') . "</b> تنظیم شد.", 'ax_stock');
            return true;

        case 'ax_stock_text':
            if ($rich === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return true; }
            if (!str_contains($rich, '{config}')) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ متن باید کلید <code>{config}</code> داشته باشد، وگرنه کانفیگ به مشتری نمی‌رسد.");
                return true;
            }
            axSet(function (&$c) use ($rich) { $c['stock']['text'] = $rich; });
            $done("✅ متن تحویل ذخیره شد.", 'ax_stock');
            return true;

        case 'ax_stock_empty':
            axSet(function (&$c) use ($rich) { $c['stock']['empty_text'] = $rich; });
            $done("✅ متن ناموجودی ذخیره شد.", 'ax_stock');
            return true;

        case 'ax_manual_chat': {
            [$chat, $th] = function_exists('parseChatLink') ? parseChatLink($plain) : [null, 0];
            if (!$chat) { sendMsg(BOT_TOKEN, $chatId, "❌ لینک یا آیدی معتبر نبود. دوباره بفرستید."); return true; }
            axSet(function (&$c) use ($chat, $th) { $c['manual']['chat_id'] = $chat; $c['manual']['thread_id'] = (int)$th; });
            $probe = sendMsg(BOT_TOKEN, $chat, "✅ اتصال کانال سفارش دستی برقرار شد.",
                null, $th > 0 ? ['message_thread_id' => (int)$th] : []);
            $done(!empty($probe['ok'])
                ? "✅ کانال تنظیم شد: <code>" . h($chat) . "</code>" . ($th > 0 ? " · تاپیک " . (int)$th : '')
                : "⚠️ ذخیره شد ولی پیام آزمایشی نرفت:\n<code>" . h($probe['description'] ?? '—') . "</code>\n\nربات را در کانال ادمین کنید.",
                'ax_manual');
            return true;
        }

        case 'ax_manual_form':
            axSet(function (&$c) use ($rich) { $c['manual']['form'] = $rich; });
            $done("✅ متن فرم ذخیره شد.", 'ax_manual');
            return true;

        case 'ax_manual_done':
            axSet(function (&$c) use ($rich) { $c['manual']['done_text'] = $rich; });
            $done("✅ متن «انجام شد» ذخیره شد.", 'ax_manual');
            return true;

        case 'ax_manual_btn':
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن دکمه خالی است."); return true; }
            axSet(function (&$c) use ($plain) { $c['manual']['btn'] = mb_substr($plain, 0, 40); });
            $done("✅ متن دکمه ذخیره شد.", 'ax_manual');
            return true;

        case 'ax_rep_chat': {
            $app = (string)($sd['app'] ?? 'tg');
            [$chat, $th] = function_exists('parseChatLink') ? parseChatLink($plain) : [null, 0];
            if (!$chat) { sendMsg(BOT_TOKEN, $chatId, "❌ لینک یا آیدی معتبر نبود."); return true; }
            axSet(function (&$c) use ($app, $chat, $th) {
                $c['report'][$app]['chat_id'] = $chat;
                $c['report'][$app]['thread_id'] = (int)$th;
                $c['report'][$app]['on'] = true;
            });
            $done("✅ مقصد گزارش تنظیم و روشن شد:\n<code>" . h($chat) . "</code>", 'ax_rep_' . $app);
            return true;
        }

        case 'ax_rep_text': {
            $app = (string)($sd['app'] ?? 'tg');
            if ($rich === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return true; }
            axSet(function (&$c) use ($app, $rich) { $c['report'][$app]['text'] = $rich; });
            $done("✅ متن گزارش ذخیره شد.", 'ax_rep_' . $app);
            return true;
        }

        case 'ax_price_all':
            axSet(function (&$c) use ($plain) { $c['pricing']['margin']['_all'] = (float)axDigits($plain); });
            $done("📈 سود عمومی روی <b>" . fmtNum(axVal('pricing.margin._all')) . "%</b> تنظیم شد.", 'ax_price');
            return true;

        case 'ax_price_fix': {
            $item = (string)($sd['item'] ?? '');
            $v    = (float)axDigits($plain);
            axSetFixed($item, $v);
            $done($v > 0
                ? "🏷 قیمت دستی: <b>" . fmtNum($v) . "</b> تومان"
                : "⚪️ برگشت به قیمت خودکار.", 'axpfix');
            return true;
        }

        case 'ax_invoice':
            if ($rich === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return true; }
            axSet(function (&$c) use ($rich) { $c['texts']['invoice'] = $rich; $c['texts']['invoice_on'] = true; });
            $done("✅ متن تایید سفارش ذخیره و روشن شد.", 'ax_texts');
            return true;

        case 'ax_label': {
            $key = (string)($sd['key'] ?? '');
            if (!array_key_exists($key, axDefaults()['labels'])) { clearState($uid); return true; }
            axSet(function (&$c) use ($key, $rich) { $c['labels'][$key] = $rich; });
            $done("✅ ذخیره شد.", 'ax_texts');
            return true;
        }
    }

    return false;
}

/** ارقام فارسی/عربی و جداکننده‌ها را به عدد انگلیسی تبدیل می‌کند */
function axDigits($s) {
    $s = (string)$s;
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    for ($i = 0; $i < 10; $i++) $s = str_replace([$fa[$i], $ar[$i]], (string)$i, $s);
    $s = str_replace([',', '٬', '،', ' ', '_', "\u{200c}", "\u{200f}"], '', $s);
    return preg_match('/-?\d+(\.\d+)?/', $s, $m) ? $m[0] : '0';
}

<?php
/**
 * 🚀 ماژول مینی‌اپ‌ها — دو مینی‌اپ کاملا جدا روی ربات فروش ممبر
 *
 *   1) 🌟 خدمات تلگرام  (app=tg)   — استارز، پریمیوم، گیفت، تون، ترون
 *   2) 🛡 فروش کانفیگ   (app=cfg)  — سرویس‌های اینترنت / کانفیگ
 *
 * هر دو مینی‌اپ:
 *   • ظاهر و رنگ و متن‌هایشان کاملا از داخل پنل ربات قابل ویرایش است
 *   • دکمه‌شان زیر دکمه‌های «ثبت سفارش محصولات» می‌نشیند
 *   • سفارش را با initData امضاشده تلگرام می‌گیرند (جعل‌ناپذیر)
 *   • پرداخت: کیف پول ربات یا کارت به کارت با رسید و تایید ادمین
 *
 * آدرس‌ها (DOMAIN = آدرس عمومی همین فایلِ ربات):
 *   مینی‌اپ خدمات : https://DOMAIN/bot_master_membership.php?app=tg
 *   مینی‌اپ کانفیگ: https://DOMAIN/bot_master_membership.php?app=cfg
 *   API           : https://DOMAIN/bot_master_membership.php?mapi=<action>
 */

require_once __DIR__ . '/miniapp_view_tg.php';
require_once __DIR__ . '/miniapp_view_cfg.php';

// ============================================================
// ⚙️ پیکربندی پیش‌فرض
// ============================================================

/** کلیدهای دو مینی‌اپ — همیشه همین دوتا، جدا از هم */
function maKeys() { return ['tg', 'cfg']; }

function maAppLabels() {
    return ['tg' => '🌟 خدمات تلگرام', 'cfg' => '🛡 فروش کانفیگ'];
}

/** نوع سوالی که هر آیتم از کاربر می‌پرسد */
function maAskLabels() {
    return [
        'none'     => '— بدون سوال',
        'username' => '📎 آیدی تلگرام (@user)',
        'qty'      => '🔢 تعداد (قیمت × تعداد)',
        'wallet'   => '💼 آدرس ولت',
        'text'     => '✍️ توضیح دلخواه',
    ];
}

function maDefaultConfig() {
    return [
        // آدرس عمومی همین فایل — بدون این، دکمه مینی‌اپ ساخته نمی‌شود
        'base_url' => '',
        'apps' => [
            'tg'  => maDefaultTg(),
            'cfg' => maDefaultCfg(),
        ],
    ];
}

/** 🌟 مینی‌اپ خدمات تلگرام — تم «شفق قطبی» بنفش/فیروزه‌ای */
function maDefaultTg() {
    return [
        'on'    => true,
        'title' => 'خدمات تلگرام',
        'sub'   => 'استارز · پریمیوم · گیفت · تون',
        'hero'  => 'سریع‌ترین تحویل، بهترین قیمت، پشتیبانی ۲۴ ساعته',
        'note'  => 'سفارش بعد از تایید پرداخت، حداکثر تا ۱۵ دقیقه تحویل می‌شود.',
        'currency' => 'تومان',

        // دکمه‌ای که زیر محصولات نشان داده می‌شود
        'btn' => [
            'emoji' => '🌟', 'text' => 'خدمات تلگرام',
            'color' => 'primary', 'icon' => '', 'order' => 1,
        ],

        // 🎨 تم گرافیکی
        'theme' => [
            'preset' => 'aurora',
            'c1'  => '#7C4DFF',   // رنگ اصلی
            'c2'  => '#00E5FF',   // رنگ دوم
            'c3'  => '#FF3D9A',   // رنگ تاکید
            'bg'  => '#080512',   // پس‌زمینه
            'glow' => 1,          // درخشش
            'grain' => 1,         // بافت
        ],

        // ✏️ متن دکمه‌های داخل مینی‌اپ
        'ui' => [
            'balance'  => 'موجودی شما',
            'all'      => 'همه',
            'buy'      => 'ثبت سفارش',
            'submit'   => 'تایید و ادامه',
            'close'    => 'بستن',
            'sending'  => 'در حال ثبت…',
            'done'     => 'سفارش ثبت شد',
            'done_sub' => 'فاکتور پرداخت داخل ربات برایتان فرستاده شد.',
            'search'   => 'جستجو در سرویس‌ها…',
            'empty'    => 'فعلا سرویسی در این بخش نیست.',
        ],

        // 💠 دکمه‌های شیشه‌ای فاکتور داخل ربات — متن و رنگ هردو قابل ویرایش
        'glass' => [
            'wallet'  => ['emoji' => '💰', 'text' => 'پرداخت از کیف پول', 'color' => 'success', 'icon' => ''],
            'card'    => ['emoji' => '💳', 'text' => 'کارت به کارت',      'color' => 'primary', 'icon' => ''],
            'receipt' => ['emoji' => '🧾', 'text' => 'ارسال رسید',        'color' => 'success', 'icon' => ''],
            'cancel'  => ['emoji' => '🔴', 'text' => 'انصراف',            'color' => 'danger',  'icon' => ''],
            'open'    => ['emoji' => '🚀', 'text' => 'باز کردن مینی‌اپ',   'color' => 'primary', 'icon' => ''],
        ],

        'cats' => [
            ['id' => 'c_star', 'emoji' => '⭐️', 'name' => 'استارز',  'on' => true, 'order' => 1],
            ['id' => 'c_prem', 'emoji' => '💎', 'name' => 'پریمیوم', 'on' => true, 'order' => 2],
            ['id' => 'c_gift', 'emoji' => '🎁', 'name' => 'گیفت',    'on' => true, 'order' => 3],
            ['id' => 'c_coin', 'emoji' => '💱', 'name' => 'ارز',     'on' => true, 'order' => 4],
        ],

        'items' => [
            ['id' => 'i_star1', 'cat' => 'c_star', 'emoji' => '⭐️', 'name' => 'استارز تلگرام',
             'desc' => 'قیمت هر ۱ استارز — حداقل ۵۰ عدد', 'price' => 1900, 'unit' => 'استارز',
             'badge' => 'پرفروش', 'ask' => 'qty', 'min' => 50, 'max' => 100000, 'on' => true, 'order' => 1],

            ['id' => 'i_prem3', 'cat' => 'c_prem', 'emoji' => '💎', 'name' => 'پریمیوم ۳ ماهه',
             'desc' => 'فعال‌سازی روی آیدی شما — بدون نیاز به رمز', 'price' => 690000, 'unit' => '',
             'badge' => '', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1],
            ['id' => 'i_prem6', 'cat' => 'c_prem', 'emoji' => '💎', 'name' => 'پریمیوم ۶ ماهه',
             'desc' => 'فعال‌سازی روی آیدی شما — بدون نیاز به رمز', 'price' => 990000, 'unit' => '',
             'badge' => 'اقتصادی', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2],
            ['id' => 'i_prem12', 'cat' => 'c_prem', 'emoji' => '👑', 'name' => 'پریمیوم ۱۲ ماهه',
             'desc' => 'یک سال کامل — بهترین قیمت', 'price' => 1690000, 'unit' => '',
             'badge' => 'ویژه', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 3],

            ['id' => 'i_gift_teddy', 'cat' => 'c_gift', 'emoji' => '🧸', 'name' => 'گیفت تدی',
             'desc' => '۱۵ استارز — قابل نمایش روی پروفایل', 'price' => 33000, 'unit' => '',
             'badge' => '', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1],
            ['id' => 'i_gift_heart', 'cat' => 'c_gift', 'emoji' => '💗', 'name' => 'گیفت قلب',
             'desc' => '۱۵ استارز — هدیه‌ای برای سوپرایز کردن', 'price' => 33000, 'unit' => '',
             'badge' => '', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2],
            ['id' => 'i_gift_rose', 'cat' => 'c_gift', 'emoji' => '🌹', 'name' => 'گیفت گل رز',
             'desc' => '۲۵ استارز', 'price' => 54000, 'unit' => '',
             'badge' => '', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 3],

            ['id' => 'i_ton', 'cat' => 'c_coin', 'emoji' => '💎', 'name' => 'تون (TON)',
             'desc' => 'قیمت هر ۱ TON — تحویل مستقیم به ولت', 'price' => 210000, 'unit' => 'TON',
             'badge' => '', 'ask' => 'wallet', 'min' => 1, 'max' => 5000, 'on' => true, 'order' => 1],
            ['id' => 'i_trx', 'cat' => 'c_coin', 'emoji' => '🚀', 'name' => 'ترون (TRX)',
             'desc' => 'قیمت هر ۱ TRX — شبکه TRC20', 'price' => 21000, 'unit' => 'TRX',
             'badge' => '', 'ask' => 'wallet', 'min' => 10, 'max' => 100000, 'on' => true, 'order' => 2],
        ],
    ];
}

/** 🛡 مینی‌اپ فروش کانفیگ — تم «سایبر گرید» مشکی/سبز نئون */
function maDefaultCfg() {
    return [
        'on'    => true,
        'title' => 'فروش کانفیگ',
        'sub'   => 'پرسرعت · بدون قطعی · تحویل آنی',
        'hero'  => 'سرورهای اختصاصی، پینگ پایین، پشتیبانی کامل',
        'note'  => 'کانفیگ بعد از تایید پرداخت، داخل همین ربات برایتان ارسال می‌شود.',
        'currency' => 'تومان',

        'btn' => [
            'emoji' => '🛡', 'text' => 'خرید کانفیگ',
            'color' => 'success', 'icon' => '', 'order' => 2,
        ],

        'theme' => [
            'preset' => 'cyber',
            'c1'  => '#00FF9C',
            'c2'  => '#00B3FF',
            'c3'  => '#FF2E97',
            'bg'  => '#04070A',
            'glow' => 1,
            'grain' => 1,
        ],

        'ui' => [
            'balance'  => 'اعتبار شما',
            'all'      => 'همه پلن‌ها',
            'buy'      => 'خرید سرویس',
            'submit'   => 'ثبت سفارش',
            'close'    => 'بستن',
            'sending'  => 'در حال ثبت…',
            'done'     => 'سفارش ثبت شد',
            'done_sub' => 'فاکتور پرداخت داخل ربات برایتان فرستاده شد.',
            'search'   => 'جستجو در پلن‌ها…',
            'empty'    => 'فعلا پلنی در این بخش نیست.',
        ],

        'glass' => [
            'wallet'  => ['emoji' => '💰', 'text' => 'پرداخت از کیف پول', 'color' => 'success', 'icon' => ''],
            'card'    => ['emoji' => '💳', 'text' => 'کارت به کارت',      'color' => 'primary', 'icon' => ''],
            'receipt' => ['emoji' => '🧾', 'text' => 'ارسال رسید',        'color' => 'success', 'icon' => ''],
            'cancel'  => ['emoji' => '🔴', 'text' => 'انصراف',            'color' => 'danger',  'icon' => ''],
            'open'    => ['emoji' => '🛡', 'text' => 'باز کردن مینی‌اپ',   'color' => 'success', 'icon' => ''],
        ],

        'cats' => [
            ['id' => 'k_vol',  'emoji' => '📦', 'name' => 'حجمی',      'on' => true, 'order' => 1],
            ['id' => 'k_unl',  'emoji' => '♾', 'name' => 'نامحدود',   'on' => true, 'order' => 2],
            ['id' => 'k_ded',  'emoji' => '🔒', 'name' => 'اختصاصی',   'on' => true, 'order' => 3],
        ],

        'items' => [
            ['id' => 'k_v30', 'cat' => 'k_vol', 'emoji' => '📦', 'name' => '۳۰ گیگ — ۳۰ روزه',
             'desc' => 'مولتی‌یوزر ۲ کاربره — مناسب موبایل', 'price' => 145000, 'unit' => '',
             'badge' => 'پرفروش', 'ask' => 'none', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1],
            ['id' => 'k_v60', 'cat' => 'k_vol', 'emoji' => '📦', 'name' => '۶۰ گیگ — ۳۰ روزه',
             'desc' => 'مولتی‌یوزر ۳ کاربره — مناسب خانواده', 'price' => 235000, 'unit' => '',
             'badge' => '', 'ask' => 'none', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2],
            ['id' => 'k_v120', 'cat' => 'k_vol', 'emoji' => '🎯', 'name' => '۱۲۰ گیگ — ۶۰ روزه',
             'desc' => 'حجم بالا با قیمت مناسب', 'price' => 420000, 'unit' => '',
             'badge' => 'اقتصادی', 'ask' => 'none', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 3],

            ['id' => 'k_u1', 'cat' => 'k_unl', 'emoji' => '♾', 'name' => 'نامحدود — ۳۰ روزه',
             'desc' => 'بدون محدودیت حجم — ۱ کاربر همزمان', 'price' => 320000, 'unit' => '',
             'badge' => '', 'ask' => 'none', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1],
            ['id' => 'k_u3', 'cat' => 'k_unl', 'emoji' => '♾', 'name' => 'نامحدود — ۹۰ روزه',
             'desc' => 'بدون محدودیت حجم — ۲ کاربر همزمان', 'price' => 850000, 'unit' => '',
             'badge' => 'ویژه', 'ask' => 'none', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2],

            ['id' => 'k_d1', 'cat' => 'k_ded', 'emoji' => '🔒', 'name' => 'آی‌پی اختصاصی — ۳۰ روزه',
             'desc' => 'سرور اختصاصی، پینگ پایین، مناسب کار', 'price' => 690000, 'unit' => '',
             'badge' => 'حرفه‌ای', 'ask' => 'text', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1],
        ],
    ];
}

// ============================================================
// 🧩 خواندن و نوشتن پیکربندی
// ============================================================

/** ادغام امن — فهرست‌ها (آیتم‌ها/دسته‌ها) جایگزین می‌شوند نه ادغام عمقی */
function maMergeConfig($def, $saved) {
    if (!is_array($saved)) return $def;
    $out = $def;
    if (isset($saved['base_url'])) $out['base_url'] = (string)$saved['base_url'];

    foreach (maKeys() as $k) {
        $d = $def['apps'][$k] ?? [];
        $s = $saved['apps'][$k] ?? null;
        if (!is_array($s)) { $out['apps'][$k] = $d; continue; }

        $m = array_replace_recursive($d, $s);
        // این‌ها فهرست‌اند — باید عینا همان چیزی باشند که ادمین ذخیره کرده
        foreach (['cats', 'items'] as $listKey) {
            if (array_key_exists($listKey, $s)) $m[$listKey] = is_array($s[$listKey]) ? array_values($s[$listKey]) : [];
        }
        $out['apps'][$k] = $m;
    }
    return $out;
}

function maCfg() {
    $c = cfg()['miniapps'] ?? null;
    return is_array($c) ? $c : maDefaultConfig();
}

/** پیکربندی یک مینی‌اپ */
function maGet($key) {
    $a = maCfg()['apps'][$key] ?? null;
    if (!is_array($a)) $a = maDefaultConfig()['apps'][$key] ?? [];
    return $a;
}

/** ویرایش پیکربندی یک مینی‌اپ */
function maSet($key, callable $fn) {
    cfgSet(function (&$c) use ($key, $fn) {
        if (!is_array($c['miniapps'] ?? null)) $c['miniapps'] = maDefaultConfig();
        if (!is_array($c['miniapps']['apps'][$key] ?? null))
            $c['miniapps']['apps'][$key] = maDefaultConfig()['apps'][$key] ?? [];
        $fn($c['miniapps']['apps'][$key]);
    });
}

function maSetRoot(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['miniapps'] ?? null)) $c['miniapps'] = maDefaultConfig();
        $fn($c['miniapps']);
    });
}

/** متن دکمه‌های داخل مینی‌اپ */
function maUT($key, $slug) {
    $a = maGet($key);
    $d = ($key === 'tg' ? maDefaultTg() : maDefaultCfg())['ui'];
    $v = trim((string)($a['ui'][$slug] ?? ''));
    return $v !== '' ? $v : ($d[$slug] ?? $slug);
}

/** یک دکمه شیشه‌ای قابل ویرایش (متن + ایموجی + رنگ + ایموجی پریمیوم) */
function maGlassBtn($key, $slug, $callbackData) {
    $a = maGet($key);
    $d = ($key === 'tg' ? maDefaultTg() : maDefaultCfg())['glass'][$slug] ?? [];
    $g = $a['glass'][$slug] ?? $d;

    $label = trim((string)($g['emoji'] ?? '') . ' ' . (string)($g['text'] ?? ($d['text'] ?? $slug)));
    if (trim($label) === '') $label = $d['text'] ?? $slug;

    $b = ['text' => $label, 'callback_data' => $callbackData];
    if (isStyle($g['color'] ?? '')) $b['style'] = $g['color'];
    if (!empty($g['icon'])) $b['icon_custom_emoji_id'] = (string)$g['icon'];
    return $b;
}

// ============================================================
// 🔗 آدرس‌ها
// ============================================================

/** آدرس عمومی همین فایل — از تنظیمات مینی‌اپ یا از درگاه پرداخت */
function maBaseUrl() {
    $u = trim((string)(maCfg()['base_url'] ?? ''));
    if ($u === '') $u = trim((string)(cfg()['gateway']['base_url'] ?? ''));
    return rtrim($u, '/');
}

/** آدرس مینی‌اپ — خالی یعنی هنوز آدرس ثبت نشده */
function maUrl($key) {
    $b = maBaseUrl();
    if ($b === '' || !preg_match('#^https://#i', $b)) return '';
    return $b . (str_contains($b, '?') ? '&' : '?') . 'app=' . urlencode($key);
}

/** آیا مینی‌اپ آماده نمایش است؟ */
function maReady($key) {
    $a = maGet($key);
    return !empty($a['on']) && maUrl($key) !== '';
}

// ============================================================
// 🎛 دکمه‌های مینی‌اپ زیر محصولات
// ============================================================

/**
 * ردیف‌های دکمه مینی‌اپ — دقیقا زیر دکمه‌های ثبت سفارش محصولات می‌نشیند.
 * هر مینی‌اپ دکمه خودش را دارد و کاملا جدا از دیگری است.
 */
function maRows() {
    $rows = [];
    $list = [];
    foreach (maKeys() as $k) {
        if (!maReady($k)) continue;
        $a = maGet($k);
        $list[] = ['key' => $k, 'order' => (int)($a['btn']['order'] ?? 99), 'app' => $a];
    }
    usort($list, fn($x, $y) => $x['order'] <=> $y['order']);

    foreach ($list as $x) {
        $a = $x['app'];
        $label = trim((string)($a['btn']['emoji'] ?? '') . ' ' . (string)($a['btn']['text'] ?? ''));
        if ($label === '') $label = maAppLabels()[$x['key']] ?? $x['key'];

        $b = ['text' => $label, 'web_app' => ['url' => maUrl($x['key'])]];
        if (isStyle($a['btn']['color'] ?? '')) $b['style'] = $a['btn']['color'];
        if (!empty($a['btn']['icon'])) $b['icon_custom_emoji_id'] = (string)$a['btn']['icon'];
        $rows[] = [$b];
    }
    return $rows;
}

// ============================================================
// 📦 سفارش‌های مینی‌اپ — انبار جدا از سفارش‌های ممبر
// ============================================================

class MaOrder
{
    const PENDING = 'pending';     // منتظر انتخاب روش پرداخت / رسید
    const REVIEW  = 'review';      // رسید آمده، منتظر ادمین
    const PAID    = 'paid';        // پرداخت‌شده (کیف پول یا تایید ادمین)
    const DONE    = 'done';        // تحویل شد
    const REJECT  = 'rejected';

    public static function all() { return load('ma_orders'); }
    public static function get($id) { $a = load('ma_orders'); return $a[$id] ?? null; }

    public static function create($app, $uid, $uname, $item, $qty, $total, $field) {
        $id = 'ma_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3));
        mutate('ma_orders', function (&$a) use ($id, $app, $uid, $uname, $item, $qty, $total, $field) {
            $a[$id] = [
                'id' => $id, 'app' => $app,
                'user_id' => (int)$uid, 'username' => (string)$uname,
                'item_id' => $item['id'], 'item_name' => $item['name'],
                'item_emoji' => $item['emoji'] ?? '', 'unit' => $item['unit'] ?? '',
                'unit_price' => (float)$item['price'],
                'qty' => (float)$qty, 'total' => (float)$total,
                'field' => (string)$field,          // آیدی / ولت / توضیح کاربر
                'currency' => (string)($item['currency'] ?? 'تومان'),
                'status' => self::PENDING, 'pay' => '',
                'receipt_type' => null, 'receipt' => null,
                'created_at' => nowStr(), 'decided_at' => null, 'delivered_at' => null,
            ];
        });
        return $id;
    }

    public static function set($id, callable $fn) {
        return mutate('ma_orders', function (&$a) use ($id, $fn) {
            if (!isset($a[$id])) return false;
            $fn($a[$id]);
            return true;
        });
    }

    public static function forUser($uid, $limit = 10) {
        $out = [];
        foreach (self::all() as $o) if ((int)$o['user_id'] === (int)$uid) $out[] = $o;
        usort($out, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return array_slice($out, 0, $limit);
    }

    public static function countBy($status) {
        $n = 0;
        foreach (self::all() as $o) if ($o['status'] === $status) $n++;
        return $n;
    }

    public static function statusLabel($s) {
        return [
            self::PENDING => '⏳ منتظر پرداخت',
            self::REVIEW  => '🧾 در حال بررسی',
            self::PAID    => '✅ پرداخت شد — در حال انجام',
            self::DONE    => '📦 تحویل شد',
            self::REJECT  => '❌ رد شده',
        ][$s] ?? '—';
    }
}

// ============================================================
// 🔐 اعتبارسنجی initData تلگرام
// ============================================================

/**
 * امضای initData را با توکن ربات بررسی می‌کند.
 * برگشت: آرایه کاربر یا null. بدون این، هرکسی می‌توانست به جای دیگری سفارش بدهد.
 */
function maVerifyInitData($initData, $maxAge = 86400) {
    $initData = (string)$initData;
    if ($initData === '') return null;

    parse_str($initData, $q);
    if (empty($q['hash']) || empty($q['user'])) return null;

    $hash = (string)$q['hash'];
    unset($q['hash'], $q['signature']);
    ksort($q);

    $pairs = [];
    foreach ($q as $k => $v) $pairs[] = $k . '=' . $v;
    $check = implode("\n", $pairs);

    $secret = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $calc   = hash_hmac('sha256', $check, $secret);
    if (!hash_equals($calc, $hash)) return null;

    if ($maxAge > 0 && !empty($q['auth_date']) && (time() - (int)$q['auth_date']) > $maxAge) return null;

    $user = json_decode((string)$q['user'], true);
    return (is_array($user) && !empty($user['id'])) ? $user : null;
}

// ============================================================
// 🌐 سرو کردن مینی‌اپ
// ============================================================

/** صفحه HTML مینی‌اپ */
function maServe($key) {
    if (!in_array($key, maKeys(), true)) { http_response_code(404); echo 'not found'; exit; }

    $a = maGet($key);
    if (empty($a['on'])) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        echo maClosedPage($a);
        exit;
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Frame-Options: ALLOWALL');
    echo $key === 'tg' ? maViewTg($a, maBoot($key, $a)) : maViewCfg($a, maBoot($key, $a));
    exit;
}

/** داده‌ای که همان اول داخل صفحه تزریق می‌شود */
function maBoot($key, $a) {
    return [
        'app'      => $key,
        'title'    => (string)$a['title'],
        'sub'      => (string)$a['sub'],
        'hero'     => (string)$a['hero'],
        'note'     => (string)$a['note'],
        'currency' => (string)($a['currency'] ?? 'تومان'),
        'ui'       => maUiAll($key),
        'cats'     => maCatsPublic($a),
        'items'    => maItemsPublic($a),
        'api'      => maApiUrl(),
    ];
}

function maApiUrl() {
    $b = maBaseUrl();
    if ($b === '') return '';
    return $b . (str_contains($b, '?') ? '&' : '?') . 'mapi=1';
}

function maUiAll($key) {
    $d = ($key === 'tg' ? maDefaultTg() : maDefaultCfg())['ui'];
    $out = [];
    foreach ($d as $slug => $_) $out[$slug] = maUT($key, $slug);
    return $out;
}

function maCatsPublic($a) {
    $out = [];
    foreach ($a['cats'] ?? [] as $c) {
        if (empty($c['on'])) continue;
        $out[] = ['id' => (string)$c['id'], 'name' => (string)$c['name'], 'emoji' => (string)($c['emoji'] ?? '')];
    }
    usort($out, fn($x, $y) => 0);
    return $out;
}

function maItemsPublic($a) {
    // ترتیب دسته‌ها، تا در تب «همه» سرویس‌های یک دسته کنار هم بمانند
    $catPos = []; $n = 0;
    foreach ($a['cats'] ?? [] as $c) $catPos[(string)$c['id']] = $n++;

    $items = [];
    foreach ($a['items'] ?? [] as $i) {
        if (empty($i['on'])) continue;
        $items[] = [
            'id'    => (string)$i['id'],
            'cat'   => (string)($i['cat'] ?? ''),
            'emoji' => (string)($i['emoji'] ?? '💠'),
            'name'  => (string)$i['name'],
            'desc'  => (string)($i['desc'] ?? ''),
            'badge' => (string)($i['badge'] ?? ''),
            'price' => (float)$i['price'],
            'unit'  => (string)($i['unit'] ?? ''),
            'ask'   => (string)($i['ask'] ?? 'none'),
            'min'   => (float)($i['min'] ?? 1),
            'max'   => (float)($i['max'] ?? 1),
            'order' => (int)($i['order'] ?? 99),
            'cpos'  => $catPos[(string)($i['cat'] ?? '')] ?? 999,
        ];
    }
    usort($items, fn($x, $y) => [$x['cpos'], $x['order']] <=> [$y['cpos'], $y['order']]);
    return $items;
}

/** صفحه «موقتا بسته است» */
function maClosedPage($a) {
    $t = h($a['title'] ?? 'مینی‌اپ');
    return "<!doctype html><html lang=\"fa\" dir=\"rtl\"><head><meta charset=\"utf-8\">" .
           "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><title>{$t}</title>" .
           "<style>body{background:#08060f;color:#fff;font-family:system-ui,Tahoma;display:grid;" .
           "place-items:center;height:100vh;margin:0;text-align:center}</style></head><body><div>" .
           "<div style=\"font-size:56px\">🔒</div><h2>{$t}</h2><p style=\"opacity:.7\">این بخش موقتا بسته است.</p>" .
           "</div></body></html>";
}

// ============================================================
// 🔌 API مینی‌اپ
// ============================================================

function maApiOut($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function maApi() {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) $body = $_POST;

    $action = (string)($body['action'] ?? $_GET['action'] ?? '');
    $key    = (string)($body['app'] ?? $_GET['app'] ?? '');
    if (!in_array($key, maKeys(), true)) maApiOut(['ok' => false, 'error' => 'bad_app'], 400);

    $user = maVerifyInitData($body['initData'] ?? '');
    if (!$user) maApiOut(['ok' => false, 'error' => 'unauthorized', 'message' => 'اعتبارسنجی تلگرام ناموفق بود.'], 401);

    $uid   = (int)$user['id'];
    $uname = (string)($user['username'] ?? '');
    touchUser($uid, $uname, (string)($user['first_name'] ?? ''));

    $u = getUser($uid);
    if ($u && !empty($u['banned'])) maApiOut(['ok' => false, 'error' => 'banned', 'message' => 'دسترسی شما مسدود است.'], 403);

    $a = maGet($key);
    if (empty($a['on'])) maApiOut(['ok' => false, 'error' => 'closed', 'message' => 'این بخش موقتا بسته است.'], 403);

    // ---- وضعیت کاربر: موجودی + سفارش‌های اخیر ----
    if ($action === 'me') {
        maApiOut([
            'ok' => true,
            'balance' => (float)($u['balance'] ?? 0),
            'name' => (string)($user['first_name'] ?? ''),
            'orders' => array_map(fn($o) => [
                'id' => $o['id'], 'name' => $o['item_name'], 'emoji' => $o['item_emoji'],
                'total' => $o['total'], 'status' => MaOrder::statusLabel($o['status']),
                'date' => $o['created_at'],
            ], MaOrder::forUser($uid, 8)),
        ]);
    }

    // ---- ثبت سفارش ----
    if ($action === 'order') {
        // 🔒 عضویت اجباری ربات مادر هم اینجا اعمال می‌شود
        if ($uid !== ADMIN_ID && function_exists('masterJoinMissing')) {
            $miss = masterJoinMissing($uid);
            if ($miss) {
                $names = [];
                foreach ($miss as $m) $names[] = (string)($m['title'] ?? '');
                maApiOut(['ok' => false, 'error' => 'join_required',
                          'message' => "برای ثبت سفارش، اول در کانال‌های زیر عضو شوید:\n" . implode('، ', $names)], 403);
            }
        }

        $itemId = (string)($body['item'] ?? '');
        $item = null;
        foreach ($a['items'] ?? [] as $i) {
            if ((string)$i['id'] === $itemId && !empty($i['on'])) { $item = $i; break; }
        }
        if (!$item) maApiOut(['ok' => false, 'error' => 'bad_item', 'message' => 'این سرویس دیگر موجود نیست.'], 400);

        $ask = (string)($item['ask'] ?? 'none');
        $qty = 1.0;
        if ($ask === 'qty') {
            $qty = (float)str_replace([',', '،', ' '], '', (string)($body['qty'] ?? 0));
            $min = (float)($item['min'] ?? 1);
            $max = (float)($item['max'] ?? 0);
            if ($qty <= 0) maApiOut(['ok' => false, 'error' => 'bad_qty', 'message' => 'تعداد را درست وارد کنید.'], 400);
            if ($min > 0 && $qty < $min) maApiOut(['ok' => false, 'error' => 'min', 'message' => 'حداقل تعداد ' . fmtNum($min) . ' است.'], 400);
            if ($max > 0 && $qty > $max) maApiOut(['ok' => false, 'error' => 'max', 'message' => 'حداکثر تعداد ' . fmtNum($max) . ' است.'], 400);
        }

        $field = trim((string)($body['field'] ?? ''));
        if (in_array($ask, ['username', 'wallet', 'text'], true) && $field === '') {
            maApiOut(['ok' => false, 'error' => 'need_field', 'message' => 'لطفا فیلد خواسته‌شده را پر کنید.'], 400);
        }
        if ($ask === 'username') {
            $field = ltrim($field, '@');
            if (!preg_match('/^[A-Za-z0-9_]{4,64}$/', $field))
                maApiOut(['ok' => false, 'error' => 'bad_username', 'message' => 'آیدی تلگرام معتبر نیست.'], 400);
            $field = '@' . $field;
        }
        if ($ask === 'wallet' && mb_strlen($field) < 8) {
            maApiOut(['ok' => false, 'error' => 'bad_wallet', 'message' => 'آدرس ولت معتبر نیست.'], 400);
        }
        if (mb_strlen($field) > 300) $field = mb_substr($field, 0, 300);

        $item['currency'] = (string)($a['currency'] ?? 'تومان');
        $total = round((float)$item['price'] * ($ask === 'qty' ? $qty : 1), 2);
        if ($total <= 0 && empty(cfg()['test_mode']))
            maApiOut(['ok' => false, 'error' => 'bad_price', 'message' => 'قیمت این سرویس تنظیم نشده است.'], 400);

        $oid = MaOrder::create($key, $uid, $uname, $item, $qty, $total, $field);

        // فاکتور را داخل خود ربات می‌فرستیم — پرداخت آنجا انجام می‌شود
        $o = MaOrder::get($oid);
        sendMsg(BOT_TOKEN, $uid, maInvoiceText($o), maInvoiceKb($o));

        maApiOut(['ok' => true, 'order' => $oid, 'total' => $total,
                  'message' => maUT($key, 'done_sub')]);
    }

    maApiOut(['ok' => false, 'error' => 'unknown_action'], 400);
}

// ============================================================
// 🧾 فاکتور داخل ربات
// ============================================================

function maOrderTitle($o) {
    return trim((string)($o['item_emoji'] ?? '') . ' ' . (string)$o['item_name']);
}

function maFieldLabel($o) {
    $a = maGet($o['app']);
    foreach ($a['items'] ?? [] as $i) {
        if ((string)$i['id'] !== (string)$o['item_id']) continue;
        return ['username' => '📎 آیدی', 'wallet' => '💼 ولت', 'text' => '📝 توضیح'][$i['ask'] ?? ''] ?? '';
    }
    return '';
}

function maInvoiceText($o) {
    $a = maGet($o['app']);
    $bal = (float)(getUser($o['user_id'])['balance'] ?? 0);

    $t  = '🧾 <b>فاکتور ' . h($a['title']) . "</b>\n\n";
    $t .= '📦 ' . h(maOrderTitle($o)) . "\n";
    if ((float)$o['qty'] > 1 || trim((string)$o['unit']) !== '') {
        $t .= '🔢 تعداد: <b>' . fmtNum($o['qty']) . '</b> ' . h($o['unit']) . "\n";
        $t .= '💵 قیمت واحد: ' . fmtNum($o['unit_price']) . ' ' . h($o['currency']) . "\n";
    }
    if (trim((string)$o['field']) !== '') {
        $lbl = maFieldLabel($o);
        $t .= ($lbl !== '' ? $lbl : '📝') . ': <code>' . h($o['field']) . "</code>\n";
    }
    $t .= "\n💰 مبلغ کل: <b>" . fmtNum($o['total']) . ' ' . h($o['currency']) . "</b>\n";
    $t .= '👛 موجودی شما: ' . fmtNum($bal) . ' ' . h($o['currency']) . "\n";
    $t .= '🔑 کد پیگیری: <code>' . h($o['id']) . "</code>\n\n";
    if (trim((string)$a['note']) !== '') $t .= '💡 ' . h($a['note']) . "\n\n";
    $t .= '👇 روش پرداخت را انتخاب کنید:';
    return $t;
}

function maInvoiceKb($o) {
    $rows = [];
    $bal = (float)(getUser($o['user_id'])['balance'] ?? 0);
    if ($bal >= (float)$o['total']) $rows[] = [maGlassBtn($o['app'], 'wallet', 'mapay_' . $o['id'])];
    $rows[] = [maGlassBtn($o['app'], 'card', 'macard_' . $o['id'])];
    $rows[] = [maGlassBtn($o['app'], 'cancel', 'macan_' . $o['id'])];
    return inlineKb($rows);
}

/** ادمین را از سفارش تازه خبر می‌کند */
function maNotifyAdmin($o, $head = '🆕 <b>سفارش تازه مینی‌اپ</b>') {
    $a = maGet($o['app']);
    $t  = $head . "\n\n";
    $t .= '🚀 مینی‌اپ: <b>' . h($a['title']) . "</b>\n";
    $t .= '👤 کاربر: ' . ($o['username'] ? '@' . h($o['username']) : '—') . " (<code>{$o['user_id']}</code>)\n";
    $t .= '📦 ' . h(maOrderTitle($o)) . "\n";
    if ((float)$o['qty'] > 1) $t .= '🔢 تعداد: <b>' . fmtNum($o['qty']) . '</b> ' . h($o['unit']) . "\n";
    if (trim((string)$o['field']) !== '') $t .= '📝 ' . h($o['field']) . "\n";
    $t .= '💰 ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n";
    $t .= '💳 پرداخت: ' . h($o['pay'] === 'wallet' ? 'کیف پول' : 'کارت به کارت') . "\n";
    $t .= '🔑 <code>' . h($o['id']) . "</code>\n";
    $t .= '📅 ' . h($o['created_at']);

    $rows = [];
    if ($o['status'] === MaOrder::REVIEW) {
        $rows[] = [btnCb(UT('confirm'), 'maok_' . $o['id'], 'confirm'),
                   btnCb(UT('reject'),  'mano_' . $o['id'], 'reject')];
    }
    if (in_array($o['status'], [MaOrder::PAID, MaOrder::REVIEW], true)) {
        $rows[] = [btnCb('📤 تحویل سفارش', 'madlv_' . $o['id'], 'link')];
    }

    if ($o['receipt_type'] === 'photo') {
        tg(BOT_TOKEN, 'sendPhoto', [
            'chat_id' => ADMIN_ID, 'photo' => $o['receipt'],
            'caption' => $t, 'parse_mode' => 'HTML',
            'reply_markup' => json_encode(inlineKb($rows)),
        ]);
        return;
    }
    if ($o['receipt_type'] === 'text') $t .= "\n\nرسید:\n<code>" . h($o['receipt']) . '</code>';
    sendMsg(BOT_TOKEN, ADMIN_ID, $t, inlineKb($rows));
}

/** پرداخت‌شده → به ادمین خبر بده و به کاربر رسید بده */
function maMarkPaid($id, $payMethod) {
    MaOrder::set($id, function (&$o) use ($payMethod) {
        $o['status'] = MaOrder::PAID;
        $o['pay'] = $payMethod;
        $o['decided_at'] = nowStr();
    });
    $o = MaOrder::get($id);
    if (!$o) return null;

    $a = maGet($o['app']);
    sendMsg(BOT_TOKEN, $o['user_id'],
        "✅ <b>پرداخت شما تایید شد</b>\n\n" .
        '📦 ' . h(maOrderTitle($o)) . "\n" .
        '💰 ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n" .
        '🔑 <code>' . h($o['id']) . "</code>\n\n" .
        (trim((string)$a['note']) !== '' ? '💡 ' . h($a['note']) : '⏳ سفارش شما در حال انجام است.'));

    maNotifyAdmin($o, '💸 <b>سفارش پرداخت‌شده — آماده تحویل</b>');
    return $o;
}

// ============================================================
// 🎬 دکمه‌های شیشه‌ای کاربر (callback)
// ============================================================

/** برگشت true یعنی این callback مربوط به مینی‌اپ بود و رسیدگی شد */
function maCallback($data, $uid, $chatId, $msgId, $cbId, $isAdmin) {
    if (!str_starts_with($data, 'ma')) return false;

    // ---------- پنل ادمین ----------
    if (str_starts_with($data, 'maadm')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
        return maAdminCallback($data, $uid, $chatId, $msgId, $cbId);
    }

    // ---------- پرداخت از کیف پول ----------
    if (str_starts_with($data, 'mapay_')) {
        $id = substr($data, 6);
        $o  = MaOrder::get($id);
        if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if ($o['status'] !== MaOrder::PENDING) { answerCb(BOT_TOKEN, $cbId, 'این فاکتور قبلا بررسی شده.', true); return true; }

        $bal = (float)(getUser($uid)['balance'] ?? 0);
        if ($bal < (float)$o['total']) {
            answerCb(BOT_TOKEN, $cbId, '❌ موجودی کافی نیست.', true);
            return true;
        }
        addBalance($uid, -1 * (float)$o['total']);
        payReferralCommission($uid, (float)$o['total']);
        answerCb(BOT_TOKEN, $cbId, '✅ پرداخت شد');
        if ($msgId) delMsg(BOT_TOKEN, $chatId, $msgId);
        maMarkPaid($id, 'wallet');
        return true;
    }

    // ---------- کارت به کارت ----------
    if (str_starts_with($data, 'macard_')) {
        $id = substr($data, 7);
        $o  = MaOrder::get($id);
        if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if ($o['status'] !== MaOrder::PENDING) { answerCb(BOT_TOKEN, $cbId, 'این فاکتور قبلا بررسی شده.', true); return true; }

        [$method, $wallet] = walletFor($o['currency']);
        if (str_contains($wallet, 'تنظیم نشده')) {
            answerCb(BOT_TOKEN, $cbId, '⚠️ روش پرداخت تنظیم نشده — با پشتیبانی تماس بگیرید.', true);
            sendMsg(BOT_TOKEN, ADMIN_ID, "🔴 <b>مقصد پرداخت خالی است!</b>\n\nکاربر <code>{$uid}</code> نتوانست فاکتور مینی‌اپ را بپردازد.");
            return true;
        }
        answerCb(BOT_TOKEN, $cbId);
        $t  = "💳 <b>پرداخت کارت به کارت</b>\n\n";
        $t .= '💰 مبلغ: <b>' . fmtNum($o['total']) . ' ' . h($o['currency']) . "</b>\n";
        $t .= '🏦 روش: ' . h($method) . "\n\n";
        $t .= "💠 مقصد پرداخت:\n<code>" . h($wallet) . "</code>\n\n";
        $t .= '🔑 کد پیگیری: <code>' . h($o['id']) . "</code>\n\n";
        $t .= '👇 بعد از واریز، دکمه زیر را بزنید و رسید را بفرستید.';
        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb([
            [maGlassBtn($o['app'], 'receipt', 'marcpt_' . $o['id'])],
            [maGlassBtn($o['app'], 'cancel',  'macan_'  . $o['id'])],
        ]));
        return true;
    }

    // ---------- ارسال رسید ----------
    if (str_starts_with($data, 'marcpt_')) {
        $id = substr($data, 7);
        $o  = MaOrder::get($id);
        if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if ($o['status'] !== MaOrder::PENDING) { answerCb(BOT_TOKEN, $cbId, 'این فاکتور قبلا بررسی شده.', true); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState($uid, 'ma_receipt', ['id' => $id]);
        sendMsg(BOT_TOKEN, $chatId,
            "🧾 <b>رسید پرداخت</b>\n\nعکس رسید یا کد پیگیری بانکی را همین‌جا بفرستید:",
            inlineKb([[maGlassBtn($o['app'], 'cancel', 'macan_' . $id)]]));
        return true;
    }

    // ---------- انصراف ----------
    if (str_starts_with($data, 'macan_')) {
        $id = substr($data, 6);
        $o  = MaOrder::get($id);
        if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if (in_array($o['status'], [MaOrder::PAID, MaOrder::DONE], true)) {
            answerCb(BOT_TOKEN, $cbId, 'این سفارش پرداخت شده و لغو نمی‌شود.', true);
            return true;
        }
        MaOrder::set($id, function (&$x) { $x['status'] = MaOrder::REJECT; $x['decided_at'] = nowStr(); });
        clearState($uid);
        answerCb(BOT_TOKEN, $cbId, '❌ لغو شد');
        if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش لغو شد.");
        return true;
    }

    // ---------- تایید ادمین ----------
    if (str_starts_with($data, 'maok_')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
        $id = substr($data, 5);
        $o  = MaOrder::get($id);
        if (!$o) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if ($o['status'] !== MaOrder::REVIEW) { answerCb(BOT_TOKEN, $cbId, 'قبلا بررسی شده.', true); return true; }
        answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
        maMarkPaid($id, 'card');
        return true;
    }

    if (str_starts_with($data, 'mano_')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
        $id = substr($data, 5);
        $o  = MaOrder::get($id);
        if (!$o) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if (in_array($o['status'], [MaOrder::PAID, MaOrder::DONE, MaOrder::REJECT], true)) {
            answerCb(BOT_TOKEN, $cbId, 'قبلا بررسی شده.', true); return true;
        }
        MaOrder::set($id, function (&$x) { $x['status'] = MaOrder::REJECT; $x['decided_at'] = nowStr(); });
        answerCb(BOT_TOKEN, $cbId, '❌ رد شد');
        sendMsg(BOT_TOKEN, $o['user_id'],
            "❌ <b>رسید شما تایید نشد</b>\n\n" .
            '📦 ' . h(maOrderTitle($o)) . "\n" .
            '🔑 <code>' . h($o['id']) . "</code>\n\n" .
            'در صورت اشتباه، با پشتیبانی تماس بگیرید.');
        return true;
    }

    // ---------- تحویل سفارش توسط ادمین ----------
    if (str_starts_with($data, 'madlv_')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
        $id = substr($data, 6);
        $o  = MaOrder::get($id);
        if (!$o) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'ma_deliver', ['id' => $id]);
        sendMsg(BOT_TOKEN, $chatId,
            "📤 <b>تحویل سفارش</b>\n\n" .
            '📦 ' . h(maOrderTitle($o)) . "\n" .
            '👤 <code>' . $o['user_id'] . "</code>\n\n" .
            'محتوای تحویل (کانفیگ، لینک، کد یا متن) را بفرستید — همان‌طور که هست برای کاربر ارسال می‌شود.',
            inlineKb([[btnCb(UT('cancel'), 'cancel', 'cancel')]]));
        return true;
    }

    return false;
}

// ============================================================
// ✍️ حالت‌های گفتگو (متن/عکس)
// ============================================================

/** برگشت true یعنی پیام مصرف شد */
function maStateHandle($action, $sd, $msg, $uid, $chatId) {
    if (!str_starts_with($action, 'ma_')) return false;

    $plain = trim((string)($msg['text'] ?? ''));
    $ids   = customEmojiIds($msg);

    // ---------- رسید کاربر ----------
    if ($action === 'ma_receipt') {
        $id = (string)($sd['id'] ?? '');
        $o  = MaOrder::get($id);
        if (!$o || (int)$o['user_id'] !== $uid) { clearState($uid); return true; }
        if ($o['status'] !== MaOrder::PENDING) { clearState($uid); sendMsg(BOT_TOKEN, $chatId, 'این فاکتور قبلا بررسی شده.'); return true; }

        $type = null; $val = null;
        if (!empty($msg['photo'])) { $p = $msg['photo']; $type = 'photo'; $val = $p[count($p) - 1]['file_id']; }
        elseif ($plain !== '')     { $type = 'text';  $val = $plain; }
        else { sendMsg(BOT_TOKEN, $chatId, '⚠️ عکس رسید یا کد پیگیری را بفرستید.'); return true; }

        MaOrder::set($id, function (&$x) use ($type, $val) {
            $x['receipt_type'] = $type; $x['receipt'] = $val; $x['status'] = MaOrder::REVIEW;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ <b>رسید ثبت شد</b>\n\nسفارش شما در صف بررسی است و نتیجه همین‌جا اعلام می‌شود.\n" .
            '🔑 <code>' . h($id) . '</code>');
        maNotifyAdmin(MaOrder::get($id), '🧾 <b>رسید تازه مینی‌اپ — منتظر تایید</b>');
        return true;
    }

    // ---------- تحویل توسط ادمین ----------
    if ($action === 'ma_deliver') {
        if ($uid !== ADMIN_ID) { clearState($uid); return true; }
        $id = (string)($sd['id'] ?? '');
        $o  = MaOrder::get($id);
        if (!$o) { clearState($uid); return true; }

        $head = "📦 <b>سفارش شما آماده است</b>\n\n" . '📦 ' . h(maOrderTitle($o)) . "\n" .
                '🔑 <code>' . h($o['id']) . '</code>';
        $sent = false;
        $f    = extractFile($msg);
        $html = msgHtml($msg);   // متن یا کپشن پیام ادمین، با قالب‌بندی

        if ($f) {
            // فایل با سربرگ می‌رود؛ متن بلند جدا می‌آید تا به سقف ۱۰۲۴ کاراکتری کپشن نخورد
            [$type, $fileId] = $f;
            sendFile(BOT_TOKEN, $o['user_id'], $type, $fileId, $head);
            $sent = true;
            if (trim($html) !== '') sendMsg(BOT_TOKEN, $o['user_id'], $html);
        } elseif (trim($html) !== '') {
            sendMsg(BOT_TOKEN, $o['user_id'], $head . "\n\n" . $html);
            $sent = true;
        }
        if (!$sent) { sendMsg(BOT_TOKEN, $chatId, '⚠️ محتوایی نفرستادید.'); return true; }

        MaOrder::set($id, function (&$x) { $x['status'] = MaOrder::DONE; $x['delivered_at'] = nowStr(); });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ تحویل شد و سفارش بسته شد.',
            inlineKb([[btnCb('🚀 مینی‌اپ‌ها', 'maadm_home', 'admin')]]));
        return true;
    }

    // ---------- ورودی‌های ادمین ----------
    if ($uid !== ADMIN_ID) { clearState($uid); return true; }
    return maAdminState($action, $sd, $msg, $uid, $chatId, $plain, $ids);
}

// ============================================================
// 👑 پنل مدیریت مینی‌اپ‌ها
// ============================================================

function maAdmHome($chatId, $msgId = null) {
    $base = maBaseUrl();
    $text  = "🚀 <b>مینی‌اپ‌ها</b>\n\n";
    $text .= "دو مینی‌اپ جدا از هم — دکمه‌شان زیر دکمه‌های ثبت سفارش محصولات می‌نشیند.\n\n";

    foreach (maKeys() as $k) {
        $a = maGet($k);
        $ok = maReady($k);
        $text .= ($ok ? '✅' : (!empty($a['on']) ? '⚠️' : '❌')) . ' <b>' . h($a['title']) . "</b>\n";
        $text .= '   دکمه: ' . h(trim(($a['btn']['emoji'] ?? '') . ' ' . ($a['btn']['text'] ?? ''))) .
                 ' · ' . (styleMap()[$a['btn']['color'] ?? 'none'] ?? '') . "\n";
        $text .= '   سرویس‌ها: ' . count(array_filter($a['items'] ?? [], fn($i) => !empty($i['on']))) .
                 ' فعال از ' . count($a['items'] ?? []) . "\n";
    }

    $text .= "\n🔗 آدرس عمومی: " . ($base !== '' ? '<code>' . h($base) . '</code>' : '<b>ثبت نشده</b>') . "\n";
    if ($base === '') {
        $text .= "\n⚠️ تا وقتی آدرس ثبت نشود دکمه مینی‌اپ نمایش داده نمی‌شود.\n" .
                 "آدرس باید <b>https</b> و دقیقا آدرس عمومی همین فایل ربات باشد.";
    } else {
        $text .= "\n🌟 " . h(maUrl('tg')) . "\n🛡 " . h(maUrl('cfg'));
    }

    $pend = MaOrder::countBy(MaOrder::REVIEW);
    $paid = MaOrder::countBy(MaOrder::PAID);

    $rows = [
        [btnCb('🌟 مینی‌اپ خدمات تلگرام', 'maadm_app_tg', 'info')],
        [btnCb('🛡 مینی‌اپ فروش کانفیگ',  'maadm_app_cfg', 'info')],
        [btnCb('🔗 آدرس عمومی', 'maadm_base', 'admin')],
        [btnCb("🧾 سفارش‌ها ({$pend} منتظر · {$paid} آماده تحویل)", 'maadm_orders', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function maAdmApp($chatId, $msgId, $key) {
    $a = maGet($key);
    $url = maUrl($key);

    $text  = '🚀 <b>' . h($a['title']) . "</b>\n\n";
    $text .= 'وضعیت: ' . (!empty($a['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= 'دکمه: ' . h(trim(($a['btn']['emoji'] ?? '') . ' ' . ($a['btn']['text'] ?? ''))) . "\n";
    $text .= 'رنگ دکمه: ' . (styleMap()[$a['btn']['color'] ?? 'none'] ?? '—') . "\n";
    $text .= '✨ پریمیوم: ' . (!empty($a['btn']['icon']) ? '<code>' . h($a['btn']['icon']) . '</code>' : '—') . "\n";
    $text .= 'ترتیب نمایش: ' . (int)($a['btn']['order'] ?? 1) . "\n\n";
    $text .= '🏷 عنوان: ' . h($a['title']) . "\n";
    $text .= '📝 زیرعنوان: ' . h($a['sub'] ?: '—') . "\n";
    $text .= '🎯 شعار: ' . h($a['hero'] ?: '—') . "\n";
    $text .= '💡 یادداشت فاکتور: ' . h(mb_substr((string)$a['note'], 0, 60) ?: '—') . "\n";
    $text .= '💱 واحد پول: ' . h($a['currency'] ?? 'تومان') . "\n\n";
    $th = $a['theme'] ?? [];
    $text .= '🎨 رنگ‌ها: <code>' . h($th['c1'] ?? '') . '</code> · <code>' . h($th['c2'] ?? '') .
             '</code> · <code>' . h($th['c3'] ?? '') . "</code>\n";
    $text .= '🖼 پس‌زمینه: <code>' . h($th['bg'] ?? '') . "</code>\n";
    $text .= '✨ درخشش: ' . (!empty($th['glow']) ? 'روشن' : 'خاموش') .
             ' · بافت: ' . (!empty($th['grain']) ? 'روشن' : 'خاموش') . "\n\n";
    $text .= '📂 دسته‌ها: ' . count($a['cats'] ?? []) . ' · 🛒 سرویس‌ها: ' . count($a['items'] ?? []) . "\n";
    $text .= "\n🔗 " . ($url !== '' ? '<code>' . h($url) . '</code>' : '⚠️ اول آدرس عمومی را ثبت کنید');

    $rows = [
        [btnCb(!empty($a['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_tog_' . $key, 'info')],
        [btnCb('🎨 دکمه زیر محصولات', 'maadm_btn_' . $key, 'admin')],
        [btnCb('🖌 تم و رنگ گرافیکی', 'maadm_theme_' . $key, 'admin')],
        [btnCb('✏️ متن‌های مینی‌اپ', 'maadm_txt_' . $key, 'admin')],
        [btnCb('💠 متن و رنگ دکمه‌های شیشه‌ای', 'maadm_gl_' . $key, 'admin')],
        [btnCb('📂 دسته‌ها (' . count($a['cats'] ?? []) . ')', 'maadm_cats_' . $key, 'admin'),
         btnCb('🛒 سرویس‌ها (' . count($a['items'] ?? []) . ')', 'maadm_items_' . $key, 'admin')],
        [btnCb('🧪 پیش‌نمایش', 'maadm_prev_' . $key, 'confirm')],
        [btnCb(UT('back'), 'maadm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🎨 دکمه‌ای که زیر محصولات نشان داده می‌شود */
function maAdmBtn($chatId, $msgId, $key) {
    $a = maGet($key);
    $b = $a['btn'];

    $text  = "🎨 <b>دکمه زیر محصولات</b>\n\n";
    $text .= 'نمایش: ' . h(trim(($b['emoji'] ?? '') . ' ' . ($b['text'] ?? ''))) . "\n";
    $text .= 'متن: <code>' . h($b['text'] ?? '') . "</code>\n";
    $text .= 'ایموجی: ' . h($b['emoji'] ?: '—') . "\n";
    $text .= 'رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? '—') . "\n";
    $text .= '✨ پریمیوم: ' . (!empty($b['icon']) ? '<code>' . h($b['icon']) . '</code>' : '—') . "\n";
    $text .= 'ترتیب: ' . (int)($b['order'] ?? 1) . "\n\n";
    $text .= '💡 رنگ دکمه با Bot API 9.4 روی خود دکمه اعمال می‌شود.';

    $rows = [
        [btnCb('✏️ متن', 'maadm_bt_' . $key, 'admin'), btnCb('😀 ایموجی', 'maadm_be_' . $key, 'admin')],
        [btnCb('🎨 رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? ''), 'maadm_bc_' . $key, 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'maadm_bi_' . $key, 'admin'), btnCb('🔢 ترتیب', 'maadm_bo_' . $key, 'admin')],
        [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🖌 تم گرافیکی */
function maAdmTheme($chatId, $msgId, $key) {
    $a  = maGet($key);
    $th = $a['theme'] ?? [];

    $text  = "🖌 <b>تم گرافیکی — " . h($a['title']) . "</b>\n\n";
    $text .= 'استایل: <b>' . h(maPresetLabel($th['preset'] ?? '')) . "</b>\n\n";
    $text .= '🎨 رنگ اصلی: <code>' . h($th['c1'] ?? '') . "</code>\n";
    $text .= '🎨 رنگ دوم: <code>' . h($th['c2'] ?? '') . "</code>\n";
    $text .= '🎨 رنگ تاکید: <code>' . h($th['c3'] ?? '') . "</code>\n";
    $text .= '🖼 پس‌زمینه: <code>' . h($th['bg'] ?? '') . "</code>\n\n";
    $text .= '✨ درخشش: ' . (!empty($th['glow']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= '🌫 بافت دانه‌ای: ' . (!empty($th['grain']) ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $text .= 'رنگ را به شکل <code>#RRGGBB</code> بفرستید.';

    $rows = [
        [btnCb('🎨 رنگ اصلی', 'maadm_c1_' . $key, 'admin'), btnCb('🎨 رنگ دوم', 'maadm_c2_' . $key, 'admin')],
        [btnCb('🎨 رنگ تاکید', 'maadm_c3_' . $key, 'admin'), btnCb('🖼 پس‌زمینه', 'maadm_bg_' . $key, 'admin')],
        [btnCb(!empty($th['glow']) ? '✨ درخشش: روشن' : '✨ درخشش: خاموش', 'maadm_glow_' . $key, 'info'),
         btnCb(!empty($th['grain']) ? '🌫 بافت: روشن' : '🌫 بافت: خاموش', 'maadm_grain_' . $key, 'info')],
        [btnCb('🎭 پالت‌های آماده', 'maadm_pal_' . $key, 'confirm')],
        [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function maPresetLabel($p) {
    return ['aurora' => '🌌 شفق قطبی', 'cyber' => '🟢 سایبر گرید'][$p] ?? ($p ?: '—');
}

/** 🎭 پالت‌های آماده */
function maPalettes() {
    return [
        'violet'  => ['name' => '🟣 بنفش کهکشانی', 'c1' => '#7C4DFF', 'c2' => '#00E5FF', 'c3' => '#FF3D9A', 'bg' => '#080512'],
        'neon'    => ['name' => '🟢 نئون سایبری',  'c1' => '#00FF9C', 'c2' => '#00B3FF', 'c3' => '#FF2E97', 'bg' => '#04070A'],
        'sunset'  => ['name' => '🟠 غروب آتشین',   'c1' => '#FF7A18', 'c2' => '#FFC24B', 'c3' => '#FF2D55', 'bg' => '#120703'],
        'ocean'   => ['name' => '🔵 اقیانوس عمیق', 'c1' => '#2E7DFF', 'c2' => '#00E0C6', 'c3' => '#7C4DFF', 'bg' => '#050B18'],
        'gold'    => ['name' => '🟡 طلایی رویال',  'c1' => '#FFC933', 'c2' => '#FF8A3D', 'c3' => '#FF4D6D', 'bg' => '#0D0A04'],
        'blood'   => ['name' => '🔴 قرمز خونی',    'c1' => '#FF3355', 'c2' => '#FF7A45', 'c3' => '#B14DFF', 'bg' => '#0E0409'],
        'mint'    => ['name' => '🩵 مینت یخی',      'c1' => '#3DE0D0', 'c2' => '#7CE0FF', 'c3' => '#B14DFF', 'bg' => '#04100F'],
        'mono'    => ['name' => '⚪️ سفید مینیمال',  'c1' => '#E8E8F0', 'c2' => '#9AA0B5', 'c3' => '#5C6BFF', 'bg' => '#0B0B0F'],
    ];
}

function maAdmPalettes($chatId, $msgId, $key) {
    $rows = [];
    foreach (maPalettes() as $id => $p) $rows[] = [btnCb($p['name'], 'maadm_setpal_' . $key . '|' . $id, 'info')];
    $rows[] = [btnCb(UT('back'), 'maadm_theme_' . $key, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🎭 <b>پالت‌های آماده</b>\n\nیکی را بزنید تا هر سه رنگ و پس‌زمینه با هم عوض شود:", inlineKb($rows));
}

/** ✏️ متن‌های داخل مینی‌اپ */
function maAdmTexts($chatId, $msgId, $key) {
    $a = maGet($key);
    $text = "✏️ <b>متن‌های مینی‌اپ</b>\n\nهر کدام را بزنید تا عوضش کنید:";

    $rows = [
        [btnCb('🏷 عنوان — ' . mb_substr($a['title'], 0, 18), 'maadm_ttl_' . $key, 'admin')],
        [btnCb('📝 زیرعنوان', 'maadm_sub_' . $key, 'admin'), btnCb('🎯 شعار', 'maadm_hero_' . $key, 'admin')],
        [btnCb('💡 یادداشت فاکتور', 'maadm_note_' . $key, 'admin'), btnCb('💱 واحد پول', 'maadm_cur_' . $key, 'admin')],
    ];
    foreach (maUiLabels() as $slug => $lbl) {
        $rows[] = [btnCb($lbl . ' — ' . mb_substr(maUT($key, $slug), 0, 20), 'maadm_ui_' . $key . '|' . $slug, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function maUiLabels() {
    return [
        'balance'  => '👛 برچسب موجودی',
        'all'      => '📂 دکمه «همه»',
        'buy'      => '🛒 دکمه خرید روی کارت',
        'submit'   => '✅ دکمه تایید نهایی',
        'close'    => '❌ دکمه بستن',
        'sending'  => '⏳ متن حال ثبت',
        'done'     => '🎉 متن موفقیت',
        'done_sub' => '💬 زیرمتن موفقیت',
        'search'   => '🔎 متن جستجو',
        'empty'    => '🕳 متن خالی بودن',
    ];
}

/** 💠 متن و رنگ دکمه‌های شیشه‌ای */
function maAdmGlass($chatId, $msgId, $key) {
    $a = maGet($key);
    $text = "💠 <b>دکمه‌های شیشه‌ای فاکتور</b>\n\nمتن، ایموجی و رنگ هر دکمه جداگانه قابل تنظیم است:\n\n";
    foreach (maGlassLabels() as $slug => $lbl) {
        $g = $a['glass'][$slug] ?? [];
        $text .= $lbl . ' — ' . h(trim(($g['emoji'] ?? '') . ' ' . ($g['text'] ?? ''))) .
                 ' · ' . (styleMap()[$g['color'] ?? 'none'] ?? '') . "\n";
    }

    $rows = [];
    foreach (maGlassLabels() as $slug => $lbl) {
        $g = $a['glass'][$slug] ?? [];
        $rows[] = [
            btnCb('✏️ ' . $lbl, 'maadm_gt_' . $key . '|' . $slug, 'admin'),
            btnCb(styleMap()[$g['color'] ?? 'none'] ?? '🎨', 'maadm_gc_' . $key . '|' . $slug, 'info'),
            btnCb('✨', 'maadm_gi_' . $key . '|' . $slug, 'admin'),
        ];
    }
    $rows[] = [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function maGlassLabels() {
    return [
        'wallet'  => '💰 پرداخت کیف پول',
        'card'    => '💳 کارت به کارت',
        'receipt' => '🧾 ارسال رسید',
        'cancel'  => '🔴 انصراف',
        'open'    => '🚀 باز کردن مینی‌اپ',
    ];
}

/** 📂 دسته‌ها */
function maAdmCats($chatId, $msgId, $key) {
    $a = maGet($key);
    $rows = [];
    foreach ($a['cats'] ?? [] as $c) {
        $rows[] = [
            btnCb((!empty($c['on']) ? '✅ ' : '❌ ') . trim(($c['emoji'] ?? '') . ' ' . $c['name']),
                  'maadm_cat_' . $key . '|' . $c['id'], 'info'),
        ];
    }
    $rows[] = [btnCb('➕ دسته جدید', 'maadm_catnew_' . $key, 'confirm')];
    $rows[] = [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "📂 <b>دسته‌ها</b>\n\nدسته‌ها همان تب‌های بالای مینی‌اپ هستند:", inlineKb($rows));
}

function maAdmCat($chatId, $msgId, $key, $cid) {
    $c = maFindCat($key, $cid);
    if (!$c) { maAdmCats($chatId, $msgId, $key); return; }
    $n = 0;
    foreach (maGet($key)['items'] ?? [] as $i) if ((string)($i['cat'] ?? '') === $cid) $n++;

    $text  = "📂 <b>" . h(trim(($c['emoji'] ?? '') . ' ' . $c['name'])) . "</b>\n\n";
    $text .= 'نام: <code>' . h($c['name']) . "</code>\n";
    $text .= 'ایموجی: ' . h($c['emoji'] ?: '—') . "\n";
    $text .= 'سرویس‌های داخلش: <b>' . $n . "</b>\n";
    $text .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش');

    $rows = [
        [btnCb('✏️ نام', 'maadm_catn_' . $key . '|' . $cid, 'admin'),
         btnCb('😀 ایموجی', 'maadm_cate_' . $key . '|' . $cid, 'admin')],
        [btnCb(!empty($c['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_catx_' . $key . '|' . $cid, 'info')],
        [btnCb('🗑 حذف دسته', 'maadm_catd_' . $key . '|' . $cid, 'reject')],
        [btnCb(UT('back'), 'maadm_cats_' . $key, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🛒 سرویس‌ها */
function maAdmItems($chatId, $msgId, $key, $catFilter = '') {
    $a = maGet($key);
    $rows = [];
    $n = 0;
    foreach (maItemsSorted($a) as $i) {
        if ($catFilter !== '' && (string)($i['cat'] ?? '') !== $catFilter) continue;
        $rows[] = [btnCb((!empty($i['on']) ? '✅ ' : '❌ ') .
                         trim(($i['emoji'] ?? '') . ' ' . $i['name']) . ' — ' . fmtNum($i['price']),
                         'maadm_item_' . $key . '|' . $i['id'], 'info')];
        $n++;
        if ($n >= 40) break;
    }
    $rows[] = [btnCb('➕ سرویس جدید', 'maadm_itemnew_' . $key, 'confirm')];
    $rows[] = [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🛒 <b>سرویس‌های " . h($a['title']) . "</b>\n\nروی هرکدام بزنید تا ویرایش شود:", inlineKb($rows));
}

function maItemsSorted($a) {
    $items = $a['items'] ?? [];
    usort($items, fn($x, $y) => [(string)($x['cat'] ?? ''), (int)($x['order'] ?? 99)]
                            <=> [(string)($y['cat'] ?? ''), (int)($y['order'] ?? 99)]);
    return $items;
}

function maAdmItem($chatId, $msgId, $key, $iid) {
    $i = maFindItem($key, $iid);
    if (!$i) { maAdmItems($chatId, $msgId, $key); return; }
    $a = maGet($key);
    $cat = maFindCat($key, (string)($i['cat'] ?? ''));

    $text  = "🛒 <b>" . h(trim(($i['emoji'] ?? '') . ' ' . $i['name'])) . "</b>\n\n";
    $text .= '💰 قیمت: <b>' . fmtNum($i['price']) . ' ' . h($a['currency'] ?? 'تومان') . "</b>\n";
    $text .= '📂 دسته: ' . h($cat ? trim(($cat['emoji'] ?? '') . ' ' . $cat['name']) : '—') . "\n";
    $text .= '📝 توضیح: ' . h($i['desc'] ?: '—') . "\n";
    $text .= '🏷 برچسب: ' . h($i['badge'] ?: '—') . "\n";
    $text .= '❓ سوال از کاربر: ' . h(maAskLabels()[$i['ask'] ?? 'none'] ?? '—') . "\n";
    if (($i['ask'] ?? '') === 'qty') {
        $text .= '🔢 حداقل: ' . fmtNum($i['min'] ?? 1) . ' · حداکثر: ' . fmtNum($i['max'] ?? 0) . "\n";
        $text .= '📐 واحد: ' . h($i['unit'] ?: '—') . "\n";
    }
    $text .= '🔢 ترتیب: ' . (int)($i['order'] ?? 99) . "\n";
    $text .= 'وضعیت: ' . (!empty($i['on']) ? '✅ روشن' : '❌ خاموش');

    $p = $key . '|' . $iid;
    $rows = [
        [btnCb('✏️ نام', 'maadm_in_' . $p, 'admin'), btnCb('😀 ایموجی', 'maadm_ie_' . $p, 'admin')],
        [btnCb('💰 قیمت', 'maadm_ip_' . $p, 'admin'), btnCb('📝 توضیح', 'maadm_id_' . $p, 'admin')],
        [btnCb('🏷 برچسب', 'maadm_ib_' . $p, 'admin'), btnCb('📂 دسته', 'maadm_ic_' . $p, 'admin')],
        [btnCb('❓ نوع سوال', 'maadm_ia_' . $p, 'admin'), btnCb('📐 واحد', 'maadm_iu_' . $p, 'admin')],
        [btnCb('🔽 حداقل', 'maadm_imin_' . $p, 'admin'), btnCb('🔼 حداکثر', 'maadm_imax_' . $p, 'admin')],
        [btnCb('🔢 ترتیب', 'maadm_io_' . $p, 'admin'),
         btnCb(!empty($i['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_ix_' . $p, 'info')],
        [btnCb('🗑 حذف سرویس', 'maadm_idel_' . $p, 'reject')],
        [btnCb(UT('back'), 'maadm_items_' . $key, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🧾 سفارش‌های مینی‌اپ */
function maAdmOrders($chatId, $msgId, $filter = 'open') {
    $all = MaOrder::all();
    usort($all, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

    $text = "🧾 <b>سفارش‌های مینی‌اپ</b>\n\n";
    $rows = [];
    $n = 0;
    foreach ($all as $o) {
        $open = in_array($o['status'], [MaOrder::REVIEW, MaOrder::PAID], true);
        if ($filter === 'open' && !$open) continue;
        $a = maGet($o['app']);
        $text .= MaOrder::statusLabel($o['status']) . ' · ' . h($a['title']) . "\n";
        $text .= '   ' . h(maOrderTitle($o)) . ' — ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n";
        $text .= '   👤 <code>' . $o['user_id'] . '</code> · 🔑 <code>' . h($o['id']) . "</code>\n";
        $rows[] = [btnCb(mb_substr(maOrderTitle($o), 0, 24) . ' — ' . MaOrder::statusLabel($o['status']),
                         'maadm_ord_' . $o['id'], 'info')];
        if (++$n >= 12) break;
    }
    if (!$n) $text .= 'سفارشی نیست.';

    $rows[] = [btnCb($filter === 'open' ? '📚 نمایش همه' : '⏳ فقط بازها',
                     'maadm_ordf_' . ($filter === 'open' ? 'all' : 'open'), 'admin')];
    $rows[] = [btnCb(UT('back'), 'maadm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function maAdmOrder($chatId, $msgId, $id) {
    $o = MaOrder::get($id);
    if (!$o) { maAdmOrders($chatId, $msgId); return; }
    $a = maGet($o['app']);

    $text  = "🧾 <b>سفارش مینی‌اپ</b>\n\n";
    $text .= '🚀 ' . h($a['title']) . "\n";
    $text .= '📦 ' . h(maOrderTitle($o)) . "\n";
    if ((float)$o['qty'] > 1) $text .= '🔢 ' . fmtNum($o['qty']) . ' ' . h($o['unit']) . "\n";
    if (trim((string)$o['field']) !== '') $text .= '📝 <code>' . h($o['field']) . "</code>\n";
    $text .= '💰 ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n";
    $text .= '👤 <code>' . $o['user_id'] . '</code> ' . ($o['username'] ? '@' . h($o['username']) : '') . "\n";
    $text .= '📊 ' . MaOrder::statusLabel($o['status']) . "\n";
    $text .= '📅 ' . h($o['created_at']) . "\n";
    $text .= '🔑 <code>' . h($o['id']) . '</code>';

    $rows = [];
    if ($o['status'] === MaOrder::REVIEW) {
        $rows[] = [btnCb(UT('confirm'), 'maok_' . $o['id'], 'confirm'),
                   btnCb(UT('reject'),  'mano_' . $o['id'], 'reject')];
    }
    if (in_array($o['status'], [MaOrder::PAID, MaOrder::REVIEW], true)) {
        $rows[] = [btnCb('📤 تحویل سفارش', 'madlv_' . $o['id'], 'link')];
    }
    if ($o['receipt_type'] === 'photo') {
        $rows[] = [btnCb('🖼 دیدن رسید', 'maadm_rcp_' . $o['id'], 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'maadm_orders', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

// ---------- کمکی‌ها ----------

function maFindCat($key, $cid) {
    foreach (maGet($key)['cats'] ?? [] as $c) if ((string)$c['id'] === (string)$cid) return $c;
    return null;
}

function maFindItem($key, $iid) {
    foreach (maGet($key)['items'] ?? [] as $i) if ((string)$i['id'] === (string)$iid) return $i;
    return null;
}

function maCatMutate($key, $cid, callable $fn) {
    maSet($key, function (&$a) use ($cid, $fn) {
        foreach ($a['cats'] as $k => $c) {
            if ((string)$c['id'] === (string)$cid) { $fn($a['cats'][$k]); return; }
        }
    });
}

function maItemMutate($key, $iid, callable $fn) {
    maSet($key, function (&$a) use ($iid, $fn) {
        foreach ($a['items'] as $k => $i) {
            if ((string)$i['id'] === (string)$iid) { $fn($a['items'][$k]); return; }
        }
    });
}

/** «tg|xyz» → ['tg', 'xyz'] */
function maSplit($rest) {
    $pos = strpos($rest, '|');
    if ($pos === false) return [$rest, ''];
    return [substr($rest, 0, $pos), substr($rest, $pos + 1)];
}

function maAskState($uid, $chatId, $action, $data, $title, $hint = '', $back = null) {
    setState($uid, $action, $data);
    $rows = [];
    if ($back) $rows[] = [btnCb(UT('cancel'), $back, 'cancel')];
    else       $rows[] = [btnCb(UT('cancel'), 'cancel', 'cancel')];
    sendMsg(BOT_TOKEN, $chatId, $title . ($hint !== '' ? "\n\n" . $hint : ''), inlineKb($rows));
}

function maColorNext($cur) { return nextStyle($cur ?: 'none'); }

function maNormHex($s) {
    $s = strtoupper(trim($s));
    if ($s !== '' && $s[0] !== '#') $s = '#' . $s;
    return preg_match('/^#[0-9A-F]{6}$/', $s) ? $s : '';
}

// ============================================================
// 👑 callbackهای پنل مدیریت
// ============================================================

function maAdminCallback($data, $uid, $chatId, $msgId, $cbId) {
    // ---- صفحه‌ها ----
    if ($data === 'maadm_home')   { answerCb(BOT_TOKEN, $cbId); maAdmHome($chatId, $msgId); return true; }
    if ($data === 'maadm_orders') { answerCb(BOT_TOKEN, $cbId); maAdmOrders($chatId, $msgId); return true; }

    if (str_starts_with($data, 'maadm_ordf_')) {
        answerCb(BOT_TOKEN, $cbId);
        maAdmOrders($chatId, $msgId, substr($data, 11));
        return true;
    }
    if (str_starts_with($data, 'maadm_ord_')) {
        answerCb(BOT_TOKEN, $cbId);
        maAdmOrder($chatId, $msgId, substr($data, 10));
        return true;
    }
    if (str_starts_with($data, 'maadm_rcp_')) {
        $o = MaOrder::get(substr($data, 10));
        answerCb(BOT_TOKEN, $cbId);
        if ($o && $o['receipt_type'] === 'photo') {
            sendFile(BOT_TOKEN, $chatId, 'photo', $o['receipt'], '🧾 رسید سفارش <code>' . h($o['id']) . '</code>');
        }
        return true;
    }

    if ($data === 'maadm_base') {
        answerCb(BOT_TOKEN, $cbId);
        maAskState($uid, $chatId, 'ma_base', [],
            "🔗 <b>آدرس عمومی ربات</b>",
            "آدرس کامل همین فایل را بفرستید — باید با <code>https://</code> باشد.\n\n" .
            "مثال:\n<code>https://example.com/bot_master_membership.php</code>\n\n" .
            "برای پاک کردن، <code>-</code> بفرستید.");
        return true;
    }

    // ---- عملیات روی یک اپ: maadm_<op>_<key>[|arg] ----
    if (!preg_match('/^maadm_([a-z0-9]+)_(.+)$/', $data, $m)) { answerCb(BOT_TOKEN, $cbId); return true; }
    $op   = $m[1];
    $rest = $m[2];
    [$key, $arg] = maSplit($rest);

    if (!in_array($key, maKeys(), true)) { answerCb(BOT_TOKEN, $cbId); return true; }
    $a = maGet($key);

    switch ($op) {
        // ---------- صفحه‌ها ----------
        case 'app':   answerCb(BOT_TOKEN, $cbId); maAdmApp($chatId, $msgId, $key); return true;
        case 'btn':   answerCb(BOT_TOKEN, $cbId); maAdmBtn($chatId, $msgId, $key); return true;
        case 'theme': answerCb(BOT_TOKEN, $cbId); maAdmTheme($chatId, $msgId, $key); return true;
        case 'txt':   answerCb(BOT_TOKEN, $cbId); maAdmTexts($chatId, $msgId, $key); return true;
        case 'gl':    answerCb(BOT_TOKEN, $cbId); maAdmGlass($chatId, $msgId, $key); return true;
        case 'cats':  answerCb(BOT_TOKEN, $cbId); maAdmCats($chatId, $msgId, $key); return true;
        case 'items': answerCb(BOT_TOKEN, $cbId); maAdmItems($chatId, $msgId, $key); return true;
        case 'pal':   answerCb(BOT_TOKEN, $cbId); maAdmPalettes($chatId, $msgId, $key); return true;

        case 'prev':
            answerCb(BOT_TOKEN, $cbId);
            $url = maUrl($key);
            if ($url === '') {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ اول آدرس عمومی را ثبت کنید.\n\n🚀 مینی‌اپ‌ها ← 🔗 آدرس عمومی");
                return true;
            }
            $b = maGlassBtn($key, 'open', 'noop');
            unset($b['callback_data']);
            $b['web_app'] = ['url' => $url];
            sendMsg(BOT_TOKEN, $chatId,
                "🧪 <b>پیش‌نمایش " . h($a['title']) . "</b>\n\nدکمه زیر همان چیزی است که کاربر می‌بیند:",
                inlineKb([[$b]]));
            return true;

        case 'tog':
            maSet($key, function (&$x) { $x['on'] = empty($x['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmApp($chatId, $msgId, $key);
            return true;

        // ---------- دکمه زیر محصولات ----------
        case 'bc':
            maSet($key, function (&$x) { $x['btn']['color'] = maColorNext($x['btn']['color'] ?? 'none'); });
            answerCb(BOT_TOKEN, $cbId, styleMap()[maGet($key)['btn']['color']] ?? '');
            maAdmBtn($chatId, $msgId, $key);
            return true;
        case 'bt':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_btn_text', ['k' => $key], '✏️ متن جدید دکمه را بفرستید:',
                'الان: <code>' . h($a['btn']['text']) . '</code>');
            return true;
        case 'be':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_btn_emoji', ['k' => $key], '😀 ایموجی دکمه را بفرستید:',
                'برای حذف <code>-</code> بفرستید.');
            return true;
        case 'bi':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_btn_icon', ['k' => $key],
                '✨ یک ایموجی پریمیوم بفرستید (یا کدش را):', 'برای حذف <code>-</code> بفرستید.');
            return true;
        case 'bo':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_btn_order', ['k' => $key], '🔢 ترتیب نمایش (عدد):');
            return true;

        // ---------- تم ----------
        case 'c1': case 'c2': case 'c3': case 'bg':
            answerCb(BOT_TOKEN, $cbId);
            $names = ['c1' => 'رنگ اصلی', 'c2' => 'رنگ دوم', 'c3' => 'رنگ تاکید', 'bg' => 'پس‌زمینه'];
            maAskState($uid, $chatId, 'ma_theme', ['k' => $key, 'f' => $op],
                '🎨 ' . $names[$op] . ' را بفرستید:',
                "به شکل <code>#RRGGBB</code>\nالان: <code>" . h($a['theme'][$op] ?? '') . '</code>');
            return true;
        case 'glow':
            maSet($key, function (&$x) { $x['theme']['glow'] = empty($x['theme']['glow']) ? 1 : 0; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmTheme($chatId, $msgId, $key);
            return true;
        case 'grain':
            maSet($key, function (&$x) { $x['theme']['grain'] = empty($x['theme']['grain']) ? 1 : 0; });
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmTheme($chatId, $msgId, $key);
            return true;
        case 'setpal':
            $p = maPalettes()[$arg] ?? null;
            if ($p) {
                maSet($key, function (&$x) use ($p) {
                    $x['theme']['c1'] = $p['c1']; $x['theme']['c2'] = $p['c2'];
                    $x['theme']['c3'] = $p['c3']; $x['theme']['bg'] = $p['bg'];
                });
            }
            answerCb(BOT_TOKEN, $cbId, '🎨 اعمال شد');
            maAdmTheme($chatId, $msgId, $key);
            return true;

        // ---------- متن‌ها ----------
        case 'ttl': case 'sub': case 'hero': case 'note': case 'cur':
            answerCb(BOT_TOKEN, $cbId);
            $map = ['ttl' => ['title', '🏷 عنوان'], 'sub' => ['sub', '📝 زیرعنوان'],
                    'hero' => ['hero', '🎯 شعار'], 'note' => ['note', '💡 یادداشت فاکتور'],
                    'cur' => ['currency', '💱 واحد پول']];
            [$f, $lbl] = $map[$op];
            maAskState($uid, $chatId, 'ma_field', ['k' => $key, 'f' => $f],
                $lbl . ' جدید را بفرستید:', 'الان: <code>' . h((string)$a[$f]) . '</code>');
            return true;
        case 'ui':
            answerCb(BOT_TOKEN, $cbId);
            if (!isset(maUiLabels()[$arg])) return true;
            maAskState($uid, $chatId, 'ma_ui', ['k' => $key, 's' => $arg],
                '✏️ ' . maUiLabels()[$arg] . ' — متن جدید:',
                'الان: <code>' . h(maUT($key, $arg)) . '</code>');
            return true;

        // ---------- دکمه‌های شیشه‌ای ----------
        case 'gt':
            answerCb(BOT_TOKEN, $cbId);
            if (!isset(maGlassLabels()[$arg])) return true;
            $g = $a['glass'][$arg] ?? [];
            maAskState($uid, $chatId, 'ma_glass_text', ['k' => $key, 's' => $arg],
                '✏️ متن دکمه «' . maGlassLabels()[$arg] . '»:',
                "الان: <code>" . h(trim(($g['emoji'] ?? '') . ' ' . ($g['text'] ?? ''))) . "</code>\n" .
                'می‌توانید ایموجی را هم اول متن بنویسید.');
            return true;
        case 'gc':
            if (isset(maGlassLabels()[$arg])) {
                maSet($key, function (&$x) use ($arg) {
                    $x['glass'][$arg]['color'] = maColorNext($x['glass'][$arg]['color'] ?? 'none');
                });
            }
            answerCb(BOT_TOKEN, $cbId, styleMap()[maGet($key)['glass'][$arg]['color'] ?? 'none'] ?? '');
            maAdmGlass($chatId, $msgId, $key);
            return true;
        case 'gi':
            answerCb(BOT_TOKEN, $cbId);
            if (!isset(maGlassLabels()[$arg])) return true;
            maAskState($uid, $chatId, 'ma_glass_icon', ['k' => $key, 's' => $arg],
                '✨ ایموجی پریمیوم دکمه «' . maGlassLabels()[$arg] . '»:',
                'یک پیام با ایموجی پریمیوم بفرستید. برای حذف <code>-</code>.');
            return true;

        // ---------- دسته‌ها ----------
        case 'cat':  answerCb(BOT_TOKEN, $cbId); maAdmCat($chatId, $msgId, $key, $arg); return true;
        case 'catnew':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_cat_new', ['k' => $key],
                '📂 نام دسته جدید را بفرستید:', 'می‌توانید ایموجی را اول نام بنویسید.');
            return true;
        case 'catn':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_cat_name', ['k' => $key, 'c' => $arg], '✏️ نام جدید دسته:');
            return true;
        case 'cate':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_cat_emoji', ['k' => $key, 'c' => $arg], '😀 ایموجی دسته:',
                'برای حذف <code>-</code> بفرستید.');
            return true;
        case 'catx':
            maCatMutate($key, $arg, function (&$c) { $c['on'] = empty($c['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmCat($chatId, $msgId, $key, $arg);
            return true;
        case 'catd':
            maSet($key, function (&$x) use ($arg) {
                $x['cats'] = array_values(array_filter($x['cats'], fn($c) => (string)$c['id'] !== (string)$arg));
                foreach ($x['items'] as $k2 => $i) {
                    if ((string)($i['cat'] ?? '') === (string)$arg) $x['items'][$k2]['cat'] = '';
                }
            });
            answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد');
            maAdmCats($chatId, $msgId, $key);
            return true;

        // ---------- سرویس‌ها ----------
        case 'item': answerCb(BOT_TOKEN, $cbId); maAdmItem($chatId, $msgId, $key, $arg); return true;
        case 'itemnew':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_item_new', ['k' => $key],
                '🛒 نام سرویس جدید را بفرستید:', 'بعدش قیمت و بقیه تنظیماتش را می‌پرسم.');
            return true;
        case 'in': case 'ie': case 'ip': case 'id': case 'ib': case 'iu':
        case 'io': case 'imin': case 'imax':
            answerCb(BOT_TOKEN, $cbId);
            $map = [
                'in'   => ['name',  '✏️ نام سرویس:', ''],
                'ie'   => ['emoji', '😀 ایموجی سرویس:', 'برای حذف <code>-</code>.'],
                'ip'   => ['price', '💰 قیمت (فقط عدد):', ''],
                'id'   => ['desc',  '📝 توضیح کوتاه:', 'برای حذف <code>-</code>.'],
                'ib'   => ['badge', '🏷 برچسب روی کارت:', 'مثلا «پرفروش» — برای حذف <code>-</code>.'],
                'iu'   => ['unit',  '📐 واحد شمارش:', 'مثلا «استارز» یا «TON» — برای حذف <code>-</code>.'],
                'io'   => ['order', '🔢 ترتیب نمایش (عدد):', ''],
                'imin' => ['min',   '🔽 حداقل تعداد:', ''],
                'imax' => ['max',   '🔼 حداکثر تعداد:', '۰ یعنی بی‌نهایت.'],
            ];
            [$f, $title, $hint] = $map[$op];
            maAskState($uid, $chatId, 'ma_item_field', ['k' => $key, 'i' => $arg, 'f' => $f], $title, $hint);
            return true;
        case 'ic':
            answerCb(BOT_TOKEN, $cbId);
            $rows = [];
            foreach ($a['cats'] ?? [] as $c) {
                $rows[] = [btnCb(trim(($c['emoji'] ?? '') . ' ' . $c['name']),
                                 'maadm_icset_' . $key . '|' . $arg . '~' . $c['id'], 'info')];
            }
            $rows[] = [btnCb(UT('back'), 'maadm_item_' . $key . '|' . $arg, 'nav')];
            editMsg(BOT_TOKEN, $chatId, $msgId, "📂 <b>دسته این سرویس</b>\n\nکدام دسته؟", inlineKb($rows));
            return true;
        case 'icset':
            $parts = explode('~', $arg, 2);
            if (count($parts) === 2) {
                maItemMutate($key, $parts[0], function (&$i) use ($parts) { $i['cat'] = $parts[1]; });
            }
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmItem($chatId, $msgId, $key, $parts[0] ?? '');
            return true;
        case 'ia':
            answerCb(BOT_TOKEN, $cbId);
            $rows = [];
            foreach (maAskLabels() as $ak => $al) {
                $rows[] = [btnCb($al, 'maadm_iaset_' . $key . '|' . $arg . '~' . $ak, 'info')];
            }
            $rows[] = [btnCb(UT('back'), 'maadm_item_' . $key . '|' . $arg, 'nav')];
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "❓ <b>سوال از کاربر</b>\n\nموقع خرید این سرویس، مینی‌اپ چه چیزی بپرسد؟", inlineKb($rows));
            return true;
        case 'iaset':
            $parts = explode('~', $arg, 2);
            if (count($parts) === 2 && isset(maAskLabels()[$parts[1]])) {
                maItemMutate($key, $parts[0], function (&$i) use ($parts) { $i['ask'] = $parts[1]; });
            }
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmItem($chatId, $msgId, $key, $parts[0] ?? '');
            return true;
        case 'ix':
            maItemMutate($key, $arg, function (&$i) { $i['on'] = empty($i['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdmItem($chatId, $msgId, $key, $arg);
            return true;
        case 'idel':
            maSet($key, function (&$x) use ($arg) {
                $x['items'] = array_values(array_filter($x['items'], fn($i) => (string)$i['id'] !== (string)$arg));
            });
            answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد');
            maAdmItems($chatId, $msgId, $key);
            return true;
    }

    answerCb(BOT_TOKEN, $cbId);
    return true;
}

// ============================================================
// 👑 ورودی‌های متنی پنل مدیریت
// ============================================================

function maAdminState($action, $sd, $msg, $uid, $chatId, $plain, $ids) {
    $key = (string)($sd['k'] ?? '');
    $backApp = $key !== '' ? inlineKb([[btnCb('🚀 ' . (maGet($key)['title'] ?? 'مینی‌اپ'), 'maadm_app_' . $key, 'admin')]]) : null;
    $dash = ($plain === '-' || $plain === '—');

    // ---- آدرس عمومی ----
    if ($action === 'ma_base') {
        $v = $dash ? '' : $plain;
        if ($v !== '' && !preg_match('#^https://#i', $v)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ آدرس باید با <code>https://</code> شروع شود.");
            return true;
        }
        maSetRoot(function (&$m) use ($v) { $m['base_url'] = rtrim($v, '/'); });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            $v === '' ? '✅ آدرس پاک شد.' :
            "✅ آدرس ثبت شد.\n\n🌟 <code>" . h(maUrl('tg')) . "</code>\n🛡 <code>" . h(maUrl('cfg')) . '</code>',
            inlineKb([[btnCb('🚀 مینی‌اپ‌ها', 'maadm_home', 'admin')]]));
        return true;
    }

    if ($key !== '' && !in_array($key, maKeys(), true)) { clearState($uid); return true; }

    // ---- دکمه زیر محصولات ----
    if ($action === 'ma_btn_text') {
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی است.'); return true; }
        maSet($key, function (&$x) use ($plain, $ids) {
            $x['btn']['text'] = $plain;
            if ($ids) $x['btn']['icon'] = $ids[0];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.' . ($ids ? "\n✨ ایموجی پریمیوم هم نشست." : ''), $backApp);
        return true;
    }
    if ($action === 'ma_btn_emoji') {
        maSet($key, function (&$x) use ($plain, $dash) { $x['btn']['emoji'] = $dash ? '' : $plain; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $backApp);
        return true;
    }
    if ($action === 'ma_btn_icon') {
        $v = $ids ? $ids[0] : ($dash ? '' : $plain);
        maSet($key, function (&$x) use ($v) { $x['btn']['icon'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $v === '' ? '✅ حذف شد.' : "✅ ثبت شد: <code>" . h($v) . '</code>', $backApp);
        return true;
    }
    if ($action === 'ma_btn_order') {
        $n = (int)preg_replace('/\D/', '', norm_fa_digits($plain));
        maSet($key, function (&$x) use ($n) { $x['btn']['order'] = max(1, $n); });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $backApp);
        return true;
    }

    // ---- تم ----
    if ($action === 'ma_theme') {
        $f = (string)($sd['f'] ?? '');
        $hex = maNormHex(norm_fa_digits($plain));
        if ($hex === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ رنگ را به شکل <code>#7C4DFF</code> بفرستید."); return true; }
        maSet($key, function (&$x) use ($f, $hex) { $x['theme'][$f] = $hex; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ رنگ <code>" . h($hex) . '</code> ذخیره شد.',
            inlineKb([[btnCb('🖌 تم', 'maadm_theme_' . $key, 'admin')]]));
        return true;
    }

    // ---- متن‌های اپ ----
    if ($action === 'ma_field') {
        $f = (string)($sd['f'] ?? '');
        $v = $dash ? '' : $plain;
        if (in_array($f, ['title', 'currency'], true) && $v === '') {
            sendMsg(BOT_TOKEN, $chatId, '⚠️ این مورد نمی‌تواند خالی باشد.'); return true;
        }
        maSet($key, function (&$x) use ($f, $v) { $x[$f] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $backApp);
        return true;
    }
    if ($action === 'ma_ui') {
        $s = (string)($sd['s'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی است.'); return true; }
        maSet($key, function (&$x) use ($s, $plain) { $x['ui'][$s] = $plain; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('✏️ متن‌ها', 'maadm_txt_' . $key, 'admin')]]));
        return true;
    }

    // ---- دکمه‌های شیشه‌ای ----
    if ($action === 'ma_glass_text') {
        $s = (string)($sd['s'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی است.'); return true; }
        // ایموجی ابتدای متن جدا می‌شود تا هم ایموجی و هم متن ذخیره شود
        [$emoji, $label] = maSplitEmoji($plain);
        maSet($key, function (&$x) use ($s, $emoji, $label, $ids) {
            if ($emoji !== '') $x['glass'][$s]['emoji'] = $emoji;
            $x['glass'][$s]['text'] = $label;
            if ($ids) $x['glass'][$s]['icon'] = $ids[0];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('💠 دکمه‌های شیشه‌ای', 'maadm_gl_' . $key, 'admin')]]));
        return true;
    }
    if ($action === 'ma_glass_icon') {
        $s = (string)($sd['s'] ?? '');
        $v = $ids ? $ids[0] : ($dash ? '' : $plain);
        maSet($key, function (&$x) use ($s, $v) { $x['glass'][$s]['icon'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $v === '' ? '✅ حذف شد.' : '✅ ثبت شد.',
            inlineKb([[btnCb('💠 دکمه‌های شیشه‌ای', 'maadm_gl_' . $key, 'admin')]]));
        return true;
    }

    // ---- دسته‌ها ----
    if ($action === 'ma_cat_new') {
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ نام خالی است.'); return true; }
        [$emoji, $name] = maSplitEmoji($plain);
        $cid = 'c' . bin2hex(random_bytes(3));
        maSet($key, function (&$x) use ($cid, $emoji, $name) {
            $x['cats'][] = ['id' => $cid, 'emoji' => $emoji, 'name' => $name !== '' ? $name : 'دسته',
                            'on' => true, 'order' => count($x['cats']) + 1];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ دسته ساخته شد.',
            inlineKb([[btnCb('📂 دسته‌ها', 'maadm_cats_' . $key, 'admin')]]));
        return true;
    }
    if ($action === 'ma_cat_name') {
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ نام خالی است.'); return true; }
        maCatMutate($key, (string)($sd['c'] ?? ''), function (&$c) use ($plain) { $c['name'] = $plain; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('📂 دسته‌ها', 'maadm_cats_' . $key, 'admin')]]));
        return true;
    }
    if ($action === 'ma_cat_emoji') {
        maCatMutate($key, (string)($sd['c'] ?? ''), function (&$c) use ($plain, $dash) { $c['emoji'] = $dash ? '' : $plain; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('📂 دسته‌ها', 'maadm_cats_' . $key, 'admin')]]));
        return true;
    }

    // ---- سرویس‌ها ----
    if ($action === 'ma_item_new') {
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ نام خالی است.'); return true; }
        [$emoji, $name] = maSplitEmoji($plain);
        $iid = 'i' . bin2hex(random_bytes(3));
        $firstCat = '';
        foreach (maGet($key)['cats'] ?? [] as $c) { $firstCat = (string)$c['id']; break; }
        maSet($key, function (&$x) use ($iid, $emoji, $name, $firstCat) {
            $x['items'][] = [
                'id' => $iid, 'cat' => $firstCat, 'emoji' => $emoji !== '' ? $emoji : '💠',
                'name' => $name !== '' ? $name : 'سرویس', 'desc' => '', 'badge' => '',
                'price' => 0, 'unit' => '', 'ask' => 'none', 'min' => 1, 'max' => 1,
                'on' => true, 'order' => count($x['items']) + 1,
            ];
        });
        clearState($uid);
        setState($uid, 'ma_item_field', ['k' => $key, 'i' => $iid, 'f' => 'price']);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ سرویس ساخته شد.\n\n💰 حالا قیمتش را بفرستید (فقط عدد):",
            inlineKb([[btnCb(UT('cancel'), 'maadm_item_' . $key . '|' . $iid, 'cancel')]]));
        return true;
    }
    if ($action === 'ma_item_field') {
        $iid = (string)($sd['i'] ?? '');
        $f   = (string)($sd['f'] ?? '');
        if (!maFindItem($key, $iid)) { clearState($uid); return true; }

        $numeric = in_array($f, ['price', 'order', 'min', 'max'], true);
        if ($numeric) {
            $v = (float)str_replace([',', '،', ' '], '', norm_fa_digits($plain));
            if ($v < 0) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عدد معتبر بفرستید.'); return true; }
            maItemMutate($key, $iid, function (&$i) use ($f, $v) { $i[$f] = ($f === 'order') ? (int)$v : $v; });
        } else {
            $v = $dash ? '' : $plain;
            if ($f === 'name' && $v === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ نام خالی است.'); return true; }
            maItemMutate($key, $iid, function (&$i) use ($f, $v) { $i[$f] = $v; });
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('🛒 سرویس', 'maadm_item_' . $key . '|' . $iid, 'admin')]]));
        return true;
    }

    clearState($uid);
    return true;
}

/** ایموجی ابتدای متن را جدا می‌کند: «🎁 خرید» → ['🎁', 'خرید'] */
function maSplitEmoji($s) {
    $s = trim($s);
    if ($s === '') return ['', ''];
    if (preg_match('/^(\X)\s+(.+)$/u', $s, $m)) {
        $first = $m[1];
        // اگر کاراکتر اول حرف یا رقم نیست، ایموجی حساب می‌شود
        if (!preg_match('/^[\p{L}\p{N}]/u', $first)) return [$first, trim($m[2])];
    }
    return ['', $s];
}

/** ارقام فارسی/عربی → انگلیسی */
function norm_fa_digits($s) {
    return strtr((string)$s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

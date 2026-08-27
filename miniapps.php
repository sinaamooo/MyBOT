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

/**
 * ⚡️ سطح افکت گرافیکی: ۲ کامل · ۱ سبک · ۰ خاموش.
 * روی گوشی ضعیف، ادمین می‌تواند بیاوردش پایین تا اسکرول روان بماند.
 */
function maFxLevel($th) {
    if (isset($th['fx'])) return max(0, min(2, (int)$th['fx']));
    return !empty($th['glow']) ? 2 : 1;
}

function maAppLabels() {
    return ['tg' => '🌟 خدمات تلگرام', 'cfg' => '🛡 فروش کانفیگ'];
}

/** عملیات تحویل خودکار روی پنل فروش */
function maAutoLabels() {
    return [
        ''        => '— دستی (بدون تحویل خودکار)',
        'stars'   => '⭐️ خرید استارز',
        'premium' => '💎 خرید پریمیوم',
        'gift'    => '🎁 خرید گیفت',
    ];
}

/** نوع سوالی که هر آیتم از کاربر می‌پرسد */
function maAskLabels() {
    return [
        'none'     => '— بدون سوال',
        'username' => '📎 آیدی تلگرام (@user)',
        'qty'      => '🔢 تعداد (قیمت × تعداد)',
        'wallet'   => '💼 آدرس ولت',
        'qty_wallet' => '🔢 مقدار + 💼 آدرس ولت (برای ارز)',
        'qty_username' => '🔢 تعداد + 📎 آیدی تلگرام',
        'text'     => '✍️ توضیح دلخواه',
    ];
}

function maDefaultConfig() {
    return [
        // آدرس عمومی همین فایل — بدون این، دکمه مینی‌اپ ساخته نمی‌شود
        'base_url' => '',

        // 📐 چیدمان دکمه‌های مینی‌اپ وقتی جدا نمایش داده می‌شوند
        'row_layout' => '1,1',

        // 🔗 ادغام با دکمه‌های ثبت سفارش: وقتی روشن باشد، دکمه‌های مینی‌اپ
        // عضو همان لیست زیردکمه‌ها می‌شوند و با همان «ترتیب» و همان «چیدمان»
        // بین بقیه جا می‌گیرند — یعنی می‌شود کانفیگ را بالا و خدمات تلگرام
        // را بین ممبرها گذاشت.
        'merge' => true,

        // ⏰ سقف عمر داده ورود تلگرام (ثانیه).
        // تلگرام هر بار که مینی‌اپ باز می‌شود این داده را از نو می‌سازد،
        // پس ۶ ساعت برای استفاده‌ی عادی زیاد هم هست و پنجره‌ی سوءاستفاده
        // از یک داده‌ی شنودشده را کوتاه می‌کند.
        'init_max_age' => 21600,

        // 🚦 سقف درخواست در دقیقه.
        // روی IP بالا (پشت کلادفلر همه یک IP دارند) و روی کاربر سخت‌گیر.
        'rate_ip'   => 600,
        'rate_user' => 40,

        // 🌐 پشت کلادفلر/پروکسی هستید؟ فقط آن موقع روشن کنید —
        // وگرنه هرکس می‌تواند با جعل هدر، سقف IP را دور بزند.
        'trust_proxy' => false,

        // 🔌 قیمت‌گیری زنده از مارکت گیفت (Tonnel / Portals / هر API دیگر)
        // چون هر مارکت ساختار پاسخ خودش را دارد، آدرس و مسیر فیلدها اینجا تنظیم می‌شود
        // و با دکمه «تست اتصال» در پنل بررسی می‌شود.
        'market' => [
            'on'          => false,
            'name'        => 'مارکت گیفت',
            'url'         => '',
            'method'      => 'GET',
            'headers'     => '',        // هر خط: Key: Value
            'body'        => '',        // برای POST — JSON
            'list_path'   => '',        // مسیر آرایه نتایج، مثل: data.results
            'key_field'   => 'name',    // فیلدی که اسم/شناسه گیفت در آن است
            'price_field' => 'price',   // فیلدی که قیمت در آن است
            'price_cur'   => 'TON',     // TON | USDT | IRT
            'margin'      => 10,        // درصد سود روی قیمت مارکت
            'round'       => 1000,      // گرد کردن نهایی به تومان
            'ttl'         => 600,       // ثانیه کش
        ],

        // 💱 نرخ ارز — نوبیتکس یا والکس (از پنل قابل تعویض)
        'rates' => [
            'on'       => true,
            'source'   => 'nobitex',    // nobitex | wallex | custom
            'ton_url'  => 'https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=rls',
            'ton_path' => 'stats.ton-rls.latest',
            'trx_url'  => 'https://api.nobitex.ir/market/stats?srcCurrency=trx&dstCurrency=rls',
            'trx_path' => 'stats.trx-rls.latest',
            'usdt_url' => 'https://api.nobitex.ir/market/stats?srcCurrency=usdt&dstCurrency=rls',
            'usdt_path'=> 'stats.usdt-rls.latest',
            'div'      => 10,           // ریال → تومان
            'margin'   => 5,            // درصد سود روی نرخ ارز
            'round'    => 100,
            'ttl'      => 300,
            'timeout'  => 4,       // ثانیه — بیشتر از این یعنی کاربر منتظر می‌ماند
            'cooldown' => 600,     // بعد از شکست، تا این مدت سراغ شبکه نرو
        ],

        // 🤖 تحویل خودکار — به هر پنل/API فروشی وصل می‌شود
        // (marketapp و مشابهش). چون هر سرویس قرارداد خودش را دارد،
        // مسیر، احراز هویت، قالب بدنه و مسیر فیلدهای پاسخ اینجا تنظیم می‌شود.
        'fulfill' => [
            'on'         => false,
            'name'       => 'پنل فروش',
            'base'       => '',                 // مثل https://api.marketapp.org
            'auth_type'  => 'header',           // header | query | body | none
            'auth_key'   => 'Authorization',
            'auth_value' => '',                 // توکن یا کلید API
            'spec_url'   => '',                 // آدرس openapi.json پنل — برای خواندن خودکار مسیرها
            'timeout'    => 20,
            'retry'      => 3,                  // چند بار تلاش دوباره
            'auto_pay'   => true,               // بلافاصله بعد از پرداخت اجرا شود؟
            'ops' => [
                'balance' => ['path' => '/balance', 'method' => 'GET',  'body' => '',
                              'val_path' => 'balance', 'err_path' => 'message'],
                'stars'   => ['path' => '', 'method' => 'POST',
                              'body' => '{"username":"{username}","quantity":{qty}}',
                              'id_path' => 'order_id', 'err_path' => 'message'],
                'premium' => ['path' => '', 'method' => 'POST',
                              'body' => '{"username":"{username}","months":{qty}}',
                              'id_path' => 'order_id', 'err_path' => 'message'],
                'gift'    => ['path' => '', 'method' => 'POST',
                              'body' => '{"username":"{username}","gift_id":"{gift}"}',
                              'id_path' => 'order_id', 'err_path' => 'message'],
                'status'  => ['path' => '', 'method' => 'GET', 'body' => '',
                              'val_path' => 'status', 'err_path' => 'message'],
            ],
        ],

        // ⭐️ نرخ استارز — قیمت هر ۱ استارز به تومان
        'stars' => [
            'on'    => false,
            'price' => 1900,
            'round' => 1000,
        ],

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
            'color' => 'primary', 'icon' => '', 'order' => 1, 'row' => 0,
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
            'fx'    => 2,         // سطح افکت: ۲ کامل · ۱ سبک · ۰ خاموش
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
            'pay_wallet' => 'پرداخت از کیف پول',
            'pay_other'  => 'روش‌های دیگر پرداخت',
            'low_bal'    => 'موجودی کافی نیست',
            'paid_ok'    => 'پرداخت شد',
            'topup_hint' => 'برای شارژ، دکمه‌ی «شارژ حساب» را بزنید — همین‌جا انجام می‌شود.',
            // 🆕 صفحه‌های تازه
            'nav_home'   => 'خانه',
            'nav_shop'   => 'فروشگاه',
            'nav_orders' => 'سفارش‌ها',
            'nav_me'     => 'حساب من',
            'hot'        => 'پیشنهاد ویژه',
            'cats_ttl'   => 'دسته‌بندی‌ها',
            'rates_ttl'  => 'نرخ لحظه‌ای',
            'orders_ttl' => 'سفارش‌های اخیر',
            'no_orders'  => 'هنوز سفارشی ثبت نکرده‌اید.',
            'me_ttl'     => 'حساب کاربری',
            'topup'      => 'شارژ کیف پول',
            'topup_do'   => 'ثبت درخواست شارژ',
            'topup_amt'  => 'مبلغ شارژ (تومان)',
            'card_ttl'   => 'کارت به کارت',
            'copy'       => 'کپی شماره کارت',
            'copied'     => 'کپی شد ✓',
            'see_all'    => 'همه',
            'hi'         => 'سلام {name} 👋',
            'plans'      => 'انتخاب بسته',
            'custom'     => 'یا مقدار دلخواه (حداقل {min})',
            'buy_now'    => 'خرید',
            'topup_btn'  => '＋ شارژ',
        ],

        // 💠 دکمه‌های شیشه‌ای فاکتور داخل ربات — متن و رنگ هردو قابل ویرایش
        'glass' => [
            'wallet'  => ['emoji' => '💰', 'text' => 'پرداخت از کیف پول', 'color' => 'success', 'icon' => ''],
            'card'    => ['emoji' => '💳', 'text' => 'کارت به کارت',      'color' => 'primary', 'icon' => ''],
            'receipt' => ['emoji' => '🧾', 'text' => 'ارسال رسید',        'color' => 'success', 'icon' => ''],
            'cancel'  => ['emoji' => '🔴', 'text' => 'انصراف',            'color' => 'danger',  'icon' => ''],
            'open'    => ['emoji' => '🚀', 'text' => 'باز کردن مینی‌اپ',   'color' => 'primary', 'icon' => ''],
        ],
        // چیدمان دکمه‌های فاکتور: «1,2» یعنی ردیف اول ۱ دکمه، ردیف دوم ۲ دکمه
        'glass_layout' => '1,1,1',

        'cats' => [
            ['id' => 'c_star', 'emoji' => '⭐️', 'name' => 'استارز',  'on' => true, 'order' => 1],
            ['id' => 'c_prem', 'emoji' => '💎', 'name' => 'پریمیوم', 'on' => true, 'order' => 2],
            ['id' => 'c_gift', 'emoji' => '🎁', 'name' => 'گیفت',    'on' => true, 'order' => 3],
            ['id' => 'c_coin', 'emoji' => '💱', 'name' => 'ارز',     'on' => true, 'order' => 4],
        ],

        'items' => [
            // ── ⭐️ استارز: بسته‌های آماده + مقدار دلخواه ──
            ['id' => 'i_star_free', 'cat' => 'c_star', 'emoji' => '⭐️', 'name' => 'استارز — مقدار دلخواه',
             'desc' => 'هر تعداد که بخواهید — قیمت هر ۱ استارز', 'price' => 1900, 'unit' => 'استارز',
             'badge' => 'دلخواه', 'ask' => 'qty_username', 'min' => 50, 'max' => 1000000, 'on' => true, 'order' => 1,
             // قیمت «هر ۱ استارز» هم باید زنده باشد، وگرنه خریدار همان ۵۰ استارز
             // را از این در ارزان‌تر می‌خرد تا از بسته‌ی آماده.
             'stars' => 1, 'auto' => 'stars'],

            ['id' => 'i_star_50',    'cat' => 'c_star', 'emoji' => '⭐️', 'name' => '۵۰ استارز',
             'desc' => 'کمترین مقدار قابل خرید', 'price' => 95000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2, 'stars' => 50, 'auto' => 'stars', 'auto_qty' => 50],
            ['id' => 'i_star_75',    'cat' => 'c_star', 'emoji' => '⭐️', 'name' => '۷۵ استارز',
             'desc' => '', 'price' => 142500, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 3, 'stars' => 75, 'auto' => 'stars', 'auto_qty' => 75],
            ['id' => 'i_star_100',   'cat' => 'c_star', 'emoji' => '🌟', 'name' => '۱۰۰ استارز',
             'desc' => 'مناسب گیفت و ری‌اکشن', 'price' => 190000, 'unit' => '', 'badge' => 'پرفروش',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 4, 'stars' => 100, 'auto' => 'stars', 'auto_qty' => 100],
            ['id' => 'i_star_150',   'cat' => 'c_star', 'emoji' => '🌟', 'name' => '۱۵۰ استارز',
             'desc' => '', 'price' => 285000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 5, 'stars' => 150, 'auto' => 'stars', 'auto_qty' => 150],
            ['id' => 'i_star_250',   'cat' => 'c_star', 'emoji' => '🌟', 'name' => '۲۵۰ استارز',
             'desc' => '', 'price' => 475000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 6, 'stars' => 250, 'auto' => 'stars', 'auto_qty' => 250],
            ['id' => 'i_star_350',   'cat' => 'c_star', 'emoji' => '✨', 'name' => '۳۵۰ استارز',
             'desc' => '', 'price' => 665000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 7, 'stars' => 350, 'auto' => 'stars', 'auto_qty' => 350],
            ['id' => 'i_star_500',   'cat' => 'c_star', 'emoji' => '✨', 'name' => '۵۰۰ استارز',
             'desc' => 'مناسب خرید پریمیوم با استارز', 'price' => 950000, 'unit' => '', 'badge' => 'اقتصادی',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 8, 'stars' => 500, 'auto' => 'stars', 'auto_qty' => 500],
            ['id' => 'i_star_750',   'cat' => 'c_star', 'emoji' => '✨', 'name' => '۷۵۰ استارز',
             'desc' => '', 'price' => 1425000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 9, 'stars' => 750, 'auto' => 'stars', 'auto_qty' => 750],
            ['id' => 'i_star_1000',  'cat' => 'c_star', 'emoji' => '💫', 'name' => '۱۰۰۰ استارز',
             'desc' => 'بسته حرفه‌ای', 'price' => 1900000, 'unit' => '', 'badge' => 'ویژه',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 10, 'stars' => 1000, 'auto' => 'stars', 'auto_qty' => 1000],
            ['id' => 'i_star_1500',  'cat' => 'c_star', 'emoji' => '💫', 'name' => '۱۵۰۰ استارز',
             'desc' => '', 'price' => 2850000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 11, 'stars' => 1500, 'auto' => 'stars', 'auto_qty' => 1500],
            ['id' => 'i_star_2500',  'cat' => 'c_star', 'emoji' => '💫', 'name' => '۲۵۰۰ استارز',
             'desc' => '', 'price' => 4750000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 12, 'stars' => 2500, 'auto' => 'stars', 'auto_qty' => 2500],
            ['id' => 'i_star_5000',  'cat' => 'c_star', 'emoji' => '🌠', 'name' => '۵۰۰۰ استارز',
             'desc' => 'بسته عمده', 'price' => 9500000, 'unit' => '', 'badge' => 'عمده',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 13, 'stars' => 5000, 'auto' => 'stars', 'auto_qty' => 5000],
            ['id' => 'i_star_10000', 'cat' => 'c_star', 'emoji' => '🌠', 'name' => '۱۰۰۰۰ استارز',
             'desc' => 'بسته عمده — بهترین قیمت', 'price' => 19000000, 'unit' => '', 'badge' => 'عمده',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 14, 'stars' => 10000, 'auto' => 'stars', 'auto_qty' => 10000],

            // ── 💎 پریمیوم ──
            ['id' => 'i_prem3', 'cat' => 'c_prem', 'emoji' => '💎', 'name' => 'پریمیوم ۳ ماهه',
             'desc' => 'فعال‌سازی روی آیدی شما — بدون نیاز به رمز', 'price' => 690000, 'unit' => '',
             'badge' => '', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1, 'auto' => 'premium', 'auto_qty' => 3, 'premium' => 3],
            ['id' => 'i_prem6', 'cat' => 'c_prem', 'emoji' => '💎', 'name' => 'پریمیوم ۶ ماهه',
             'desc' => 'فعال‌سازی روی آیدی شما — بدون نیاز به رمز', 'price' => 990000, 'unit' => '',
             'badge' => 'اقتصادی', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2, 'auto' => 'premium', 'auto_qty' => 6, 'premium' => 6],
            ['id' => 'i_prem12', 'cat' => 'c_prem', 'emoji' => '👑', 'name' => 'پریمیوم ۱۲ ماهه',
             'desc' => 'یک سال کامل — بهترین قیمت', 'price' => 1690000, 'unit' => '',
             'badge' => 'ویژه', 'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 3, 'auto' => 'premium', 'auto_qty' => 12, 'premium' => 12],

            // ── 🎁 گیفت‌های استارزی (قیمت بر پایه استارز، قابل اتصال به مارکت) ──
            ['id' => 'g_teddy',     'cat' => 'c_gift', 'emoji' => '🧸', 'name' => 'گیفت تدی',
             'desc' => '۱۵ استارز', 'price' => 33000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 1,
             'stars' => 15, 'market_key' => 'teddy'],
            ['id' => 'g_heart',     'cat' => 'c_gift', 'emoji' => '💗', 'name' => 'گیفت قلب',
             'desc' => '۱۵ استارز', 'price' => 33000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 2,
             'stars' => 15, 'market_key' => 'heart'],
            ['id' => 'g_rose',      'cat' => 'c_gift', 'emoji' => '🌹', 'name' => 'گیفت گل رز',
             'desc' => '۲۵ استارز', 'price' => 54000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 3,
             'stars' => 25, 'market_key' => 'rose'],
            ['id' => 'g_gift',      'cat' => 'c_gift', 'emoji' => '🎁', 'name' => 'گیفت کادو',
             'desc' => '۲۵ استارز', 'price' => 54000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 4,
             'stars' => 25, 'market_key' => 'gift_box'],
            ['id' => 'g_cake',      'cat' => 'c_gift', 'emoji' => '🎂', 'name' => 'گیفت کیک تولد',
             'desc' => '۵۰ استارز', 'price' => 105000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 5,
             'stars' => 50, 'market_key' => 'birthday_cake'],
            ['id' => 'g_flowers',   'cat' => 'c_gift', 'emoji' => '💐', 'name' => 'گیفت دسته گل',
             'desc' => '۵۰ استارز', 'price' => 105000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 6,
             'stars' => 50, 'market_key' => 'bouquet'],
            ['id' => 'g_rocket',    'cat' => 'c_gift', 'emoji' => '🚀', 'name' => 'گیفت موشک',
             'desc' => '۵۰ استارز', 'price' => 105000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 7,
             'stars' => 50, 'market_key' => 'rocket'],
            ['id' => 'g_champagne', 'cat' => 'c_gift', 'emoji' => '🍾', 'name' => 'گیفت شامپاین',
             'desc' => '۵۰ استارز', 'price' => 105000, 'unit' => '', 'badge' => '',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 8,
             'stars' => 50, 'market_key' => 'champagne'],
            ['id' => 'g_trophy',    'cat' => 'c_gift', 'emoji' => '🏆', 'name' => 'گیفت جام قهرمانی',
             'desc' => '۱۰۰ استارز', 'price' => 205000, 'unit' => '', 'badge' => 'لاکچری',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 9,
             'stars' => 100, 'market_key' => 'trophy'],
            ['id' => 'g_ring',      'cat' => 'c_gift', 'emoji' => '💍', 'name' => 'گیفت حلقه',
             'desc' => '۱۰۰ استارز', 'price' => 205000, 'unit' => '', 'badge' => 'لاکچری',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 10,
             'stars' => 100, 'market_key' => 'ring'],
            ['id' => 'g_diamond',   'cat' => 'c_gift', 'emoji' => '💎', 'name' => 'گیفت الماس',
             'desc' => '۱۰۰ استارز', 'price' => 205000, 'unit' => '', 'badge' => 'لاکچری',
             'ask' => 'username', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 11,
             'stars' => 100, 'market_key' => 'diamond'],
            ['id' => 'g_custom',    'cat' => 'c_gift', 'emoji' => '🎀', 'name' => 'گیفت دلخواه از مارکت',
             'desc' => 'اسم گیفت موردنظرت را بنویس تا قیمت بدهیم', 'price' => 0, 'unit' => '',
             'badge' => 'سفارشی', 'ask' => 'text', 'min' => 1, 'max' => 1, 'on' => true, 'order' => 12],

            // ── 💱 ارز ──
            ['id' => 'i_ton', 'cat' => 'c_coin', 'emoji' => '💎', 'name' => 'تون (TON)',
             'desc' => 'قیمت هر ۱ TON — تحویل مستقیم به ولت', 'price' => 210000, 'unit' => 'TON',
             'badge' => '', 'ask' => 'qty_wallet', 'min' => 1, 'max' => 5000, 'on' => true, 'order' => 1,
             'rate_key' => 'ton'],
            ['id' => 'i_trx', 'cat' => 'c_coin', 'emoji' => '🚀', 'name' => 'ترون (TRX)',
             'desc' => 'قیمت هر ۱ TRX — شبکه TRC20', 'price' => 21000, 'unit' => 'TRX',
             'badge' => '', 'ask' => 'qty_wallet', 'min' => 10, 'max' => 100000, 'on' => true, 'order' => 2,
             'rate_key' => 'trx'],
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
            'color' => 'success', 'icon' => '', 'order' => 2, 'row' => 0,
        ],

        'theme' => [
            'preset' => 'aurora',
            'c1'  => '#8B5CF6',
            'c2'  => '#6366F1',
            'c3'  => '#22D3EE',
            'bg'  => '#08090D',
            'glow' => 1,
            'grain' => 0,
            'fx'    => 1,
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
            'pay_wallet' => 'پرداخت از کیف پول',
            'pay_other'  => 'روش‌های دیگر پرداخت',
            'low_bal'    => 'موجودی کافی نیست',
            'paid_ok'    => 'پرداخت شد',
            'topup_hint' => 'برای شارژ، دکمه‌ی «شارژ حساب» را بزنید — همین‌جا انجام می‌شود.',
            // 🆕 صفحه‌های تازه
            'nav_home'   => 'خانه',
            'nav_shop'   => 'پلن‌ها',
            'nav_orders' => 'سفارش‌ها',
            'nav_me'     => 'حساب من',
            'hot'        => 'پرفروش‌ترین‌ها',
            'cats_ttl'   => 'نوع سرویس',
            'rates_ttl'  => 'نرخ لحظه‌ای',
            'orders_ttl' => 'سفارش‌های اخیر',
            'no_orders'  => 'هنوز سفارشی ثبت نکرده‌اید.',
            'me_ttl'     => 'پروفایل من',
            'topup'      => 'افزایش اعتبار',
            'topup_do'   => 'ثبت درخواست شارژ',
            'topup_amt'  => 'مبلغ شارژ (تومان)',
            'card_ttl'   => 'کارت به کارت',
            'copy'       => 'کپی شماره کارت',
            'copied'     => 'کپی شد ✓',
            'see_all'    => 'همه',
            'hi'         => 'سلام {name} 👋',
            'plans'      => 'انتخاب بسته',
            'custom'     => 'یا مقدار دلخواه (حداقل {min})',
            'buy_now'    => 'خرید',
            'topup_btn'  => '＋ شارژ',
        ],

        'glass' => [
            'wallet'  => ['emoji' => '💰', 'text' => 'پرداخت از کیف پول', 'color' => 'success', 'icon' => ''],
            'card'    => ['emoji' => '💳', 'text' => 'کارت به کارت',      'color' => 'primary', 'icon' => ''],
            'receipt' => ['emoji' => '🧾', 'text' => 'ارسال رسید',        'color' => 'success', 'icon' => ''],
            'cancel'  => ['emoji' => '🔴', 'text' => 'انصراف',            'color' => 'danger',  'icon' => ''],
            'open'    => ['emoji' => '🛡', 'text' => 'باز کردن مینی‌اپ',   'color' => 'success', 'icon' => ''],
        ],
        'glass_layout' => '1,1,1',

        'cats' => [
            ['id' => 'k_vol',  'emoji' => '📦', 'name' => 'حجمی',      'on' => true, 'order' => 1],
            ['id' => 'k_unl',  'emoji' => '♾', 'name' => 'نامحدود',   'on' => true, 'order' => 2],
            ['id' => 'k_ded',  'emoji' => '🔒', 'name' => 'اختصاصی',   'on' => true, 'order' => 3],
        ],

        'items' => [
            // 📦 حجم دلخواه — کاربر خودش حجم رند انتخاب می‌کند، قیمت به‌ازای هر گیگ
            ['id' => 'k_vfree', 'cat' => 'k_vol', 'emoji' => '🎚', 'name' => 'حجم دلخواه',
             'desc' => 'هر حجمی که بخواهید — فقط عددهای رند: ۵۰۰ مگ یا گیگ کامل',
             'price' => 5500, 'unit' => 'گیگابایت', 'badge' => 'دلخواه',
             'ask' => 'volume', 'min' => 500, 'max' => 102400, 'on' => true, 'order' => 0],
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
    if (isset($saved['base_url']))     $out['base_url']     = (string)$saved['base_url'];
    if (isset($saved['row_layout']))   $out['row_layout']   = (string)$saved['row_layout'];
    if (isset($saved['merge']))        $out['merge']        = (bool)$saved['merge'];
    if (isset($saved['init_max_age'])) $out['init_max_age'] = (int)$saved['init_max_age'];
    if (isset($saved['rate_ip']))      $out['rate_ip']      = max(60,  (int)$saved['rate_ip']);
    if (isset($saved['rate_user']))    $out['rate_user']    = max(10,  (int)$saved['rate_user']);
    if (isset($saved['trust_proxy']))  $out['trust_proxy']  = (bool)$saved['trust_proxy'];
    foreach (['market', 'rates', 'stars', 'fulfill'] as $sec) {
        if (isset($saved[$sec]) && is_array($saved[$sec]))
            $out[$sec] = array_replace($def[$sec] ?? [], $saved[$sec]);
    }

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
/**
 * پیکربندی یک مینی‌اپ.
 *
 * محصول‌های ذخیره‌شده با محصول‌های پیش‌فرضِ هم‌نام ادغام می‌شوند.
 * چرا مهم است: نسخه‌های بعدی به محصول‌ها فیلد اضافه می‌کنند —
 * مثل stars و premium که می‌گویند «قیمت این را زنده حساب کن» یا auto
 * که می‌گوید «خودکار تحویلش بده». نصب‌های قدیمی این فیلدها را ذخیره
 * نکرده‌اند و بدون ادغام برای همیشه بی‌نصیب می‌مانند: پریمیوم با
 * قیمت ثابتِ ۶۹۰٬۰۰۰ می‌ماند و استارزِ دلخواه روی ۱٬۹۰۰ گیر می‌کند،
 * هرچه هم که نرخ زنده روشن باشد.
 *
 * هرچه ادمین خودش ست کرده سرِ جایش می‌ماند، و محصولی که حذف کرده
 * برنمی‌گردد.
 */
function maGet($key) {
    $a = maCfg()['apps'][$key] ?? null;
    if (!is_array($a)) return maDefaultConfig()['apps'][$key] ?? [];

    $def = maDefaultConfig()['apps'][$key]['items'] ?? [];
    if (!is_array($a['items'] ?? null) || !$def) return $a;

    $byId = [];
    foreach ($def as $d) if (isset($d['id'])) $byId[(string)$d['id']] = $d;

    foreach ($a['items'] as $i => $it) {
        $id = (string)($it['id'] ?? '');
        if ($id === '' || !isset($byId[$id])) continue;
        // پیش‌فرض پایه، ذخیره‌شده رویش — پس فیلدهای تازه می‌آیند و
        // چیزی که ادمین دست‌کاری کرده دست‌نخورده می‌ماند.
        $a['items'][$i] = array_replace($byId[$id], is_array($it) ? $it : []);
    }
    return $a;
}

/**
 * 🔄 متن‌های راهنمای قدیمی که دیگر درست نیستند.
 *
 * «برای شارژ داخل ربات …» وقتی نوشته شده بود که خرید داخل ربات تمام
 * می‌شد. حالا همه‌چیز داخل مینی‌اپ است، پس آن راهنما آدرس غلط می‌دهد.
 * فقط متنی که دقیقا برابر همان پیش‌فرضِ قدیمی است پاک می‌شود تا
 * پیش‌فرضِ تازه جایش بنشیند؛ متنی که ادمین خودش نوشته دست نمی‌خورد.
 */
function maDropOldTexts() {
    $old = 'برای شارژ کیف پول، داخل ربات «افزایش موجودی» را بزنید.';
    foreach (maKeys() as $k) {
        $cur = trim((string)(maGet($k)['ui']['topup_hint'] ?? ''));
        if ($cur === $old)
            maSet($k, function (&$a) { $a['ui']['topup_hint'] = ''; });
    }
}

/**
 * 🔄 تمِ سبزِ قدیمیِ مینی‌اپ کانفیگ را کنار می‌گذارد تا تمِ تازه‌ی
 * بنفش بنشیند. فقط وقتی که رنگ‌ها دقیقا همان پیش‌فرضِ قدیمی باشند —
 * یعنی ادمین دستشان نزده. هر رنگی که خودتان انتخاب کرده باشید
 * دست‌نخورده می‌ماند.
 */
function maDropOldTheme() {
    $old = ['c1' => '#00FF9C', 'c2' => '#00B3FF', 'c3' => '#FF2E97', 'bg' => '#04070A'];
    $th  = (array)(maGet('cfg')['theme'] ?? []);
    foreach ($old as $k => $v)
        if (strtoupper(trim((string)($th[$k] ?? ''))) !== $v) return false;

    maSet('cfg', function (&$a) {
        $d = maDefaultCfg()['theme'];
        $a['theme'] = array_replace((array)($a['theme'] ?? []), [
            'preset' => $d['preset'], 'c1' => $d['c1'], 'c2' => $d['c2'],
            'c3' => $d['c3'], 'bg' => $d['bg'], 'grain' => $d['grain'], 'fx' => $d['fx'],
        ]);
    });
    return true;
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
// 🔌 قیمت‌گیری زنده — مارکت گیفت، نرخ ارز، نرخ استارز
// ============================================================

/** «stats.ton-rls.latest» را داخل آرایه دنبال می‌کند */
function maJsonPath($data, $path) {
    $path = trim((string)$path);
    if ($path === '') return $data;
    foreach (explode('.', $path) as $seg) {
        if ($seg === '') continue;
        if (is_array($data) && array_key_exists($seg, $data)) { $data = $data[$seg]; continue; }
        if (is_array($data) && ctype_digit($seg) && array_key_exists((int)$seg, $data)) { $data = $data[(int)$seg]; continue; }
        return null;
    }
    return $data;
}

/** «1,234.5» یا «۱۲۳۴» → 1234.5 */
function maNum($v) {
    if (is_int($v) || is_float($v)) return (float)$v;
    $v = norm_fa_digits((string)$v);
    // جداکننده‌های هزارگان لاتین/عربی/فارسی و فاصله‌های نامرئی
    $v = str_replace([',', '،', '٬', '_', ' ', "\u{00A0}", "\u{200C}", "\u{200F}"], '', $v);
    $v = trim($v);
    return is_numeric($v) ? (float)$v : 0.0;
}

/** درخواست HTTP ساده با تایم‌اوت کوتاه — هیچ‌وقت نباید مینی‌اپ را معطل کند */
function maHttp($url, $method = 'GET', $headersRaw = '', $body = '', $timeout = 8) {
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return [null, 'آدرس نامعتبر'];

    $headers = [];
    foreach (preg_split('/\r?\n/', (string)$headersRaw) as $line) {
        $line = trim($line);
        if ($line !== '' && str_contains($line, ':')) $headers[] = $line;
    }

    $ch = curl_init($url);
    $opt = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
    ];
    if (strtoupper($method) === 'POST') {
        $opt[CURLOPT_POST] = true;
        $opt[CURLOPT_POSTFIELDS] = (string)$body;
        if (!$headers) $headers[] = 'Content-Type: application/json';
    }
    if ($headers) $opt[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opt);

    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) return [null, 'اتصال برقرار نشد: ' . $err];
    if ($code < 200 || $code >= 300) return [null, 'کد پاسخ ' . $code];

    $j = json_decode((string)$res, true);
    if (!is_array($j)) return [null, 'پاسخ JSON نبود: ' . mb_substr((string)$res, 0, 120)];
    return [$j, ''];
}

/** مثل maHttp ولی متن خام برمی‌گرداند — برای خواندن HTML صفحه Swagger */
function maHttpRaw($url, $timeout = 12) {
    $url = trim((string)$url);
    if ($url === '' || !preg_match('#^https?://#i', $url)) return [null, 'آدرس نامعتبر'];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; ShopBot/1.0)',
    ]);
    $res  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($res === false) return [null, 'اتصال برقرار نشد: ' . $err];
    if ($code < 200 || $code >= 300) return [null, 'کد پاسخ ' . $code];
    return [(string)$res, ''];
}

// ---------- کش ----------

function maCacheGet($key, $ttl) {
    $c = load('ma_cache');
    $x = $c[$key] ?? null;
    if (!is_array($x)) return null;
    if ($ttl > 0 && (time() - (int)($x['at'] ?? 0)) > $ttl) return null;
    return $x['v'] ?? null;
}

function maCachePut($key, $value) {
    mutate('ma_cache', function (&$c) use ($key, $value) {
        $c[$key] = ['at' => time(), 'v' => $value];
    });
}

// ---------- نرخ ارز ----------

/** نرخ یک ارز به تومان (با سود و گرد کردن) — 0 یعنی در دسترس نیست */
/**
 * 🐇 حالت «بی‌شبکه».
 *
 * وقتی روشن باشد، هیچ قیمتی از اینترنت گرفته نمی‌شود و هرچه در کش
 * هست — حتی کهنه — همان برگردانده می‌شود.
 *
 * برای صفحه‌ی مینی‌اپ است: کاربری که مینی‌اپ را باز می‌کند نباید پشت
 * پنج تماس شبکه (۱.۳ ثانیه) منتظر بماند. صفحه با قیمت کش‌شده فوری
 * می‌آید و تازه‌سازی بعد از بسته شدن جواب انجام می‌شود.
 */
function maNoNet($on = null) {
    static $flag = false;
    if ($on !== null) $flag = (bool)$on;
    return $flag;
}

function maRate($which, $fresh = false) {
    $r = maCfg()['rates'] ?? [];
    if (empty($r['on'])) return 0.0;
    $which = strtolower($which);

    // در حالت بی‌شبکه، هرچه در کش هست کافی است
    if (maNoNet()) {
        $c = maCacheGet('rate_' . $which, 0);
        if ($c !== null && (float)$c > 0) return (float)$c;
    }
    $url  = (string)($r[$which . '_url'] ?? '');
    $path = (string)($r[$which . '_path'] ?? '');
    if ($url === '') return 0.0;

    // 💹 موتور قیمت مرکزی، اگر روشن باشد، حرف اول را می‌زند — تا قیمتی که
    // در گروه نشان داده می‌شود با قیمتی که در مینی‌اپ فروخته می‌شود یکی باشد.
    if (function_exists('pxRawToman')) {
        $raw = pxRawToman($which, $fresh);
        if ($raw > 0) {
            $v = $raw * (1 + ((float)($r['margin'] ?? 0) / 100));
            $v = maRound($v, (float)($r['round'] ?? 0));
            maCachePut('rate_' . $which, $v);
            maCachePut('ratesrc_' . $which, 'swap');
            maCachePut('rateerr_' . $which, '');
            return $v;
        }
    }

    $ck = 'rate_' . $which;
    if (!$fresh) {
        $hit = maCacheGet($ck, (int)($r['ttl'] ?? 300));
        if ($hit !== null) return (float)$hit;

        // 🐢 اگر همین چند دقیقه پیش شکست خورده، دوباره امتحان نکن.
        // بدون این، هر صفحه‌ای که نرخ می‌خواهد پشت تایم‌اوت شبکه گیر
        // می‌کرد — سه ارز × دو صرافی × چند ثانیه = ربات کند.
        if (maCacheGet('ratecool_' . $which, (int)($r['cooldown'] ?? 600)) !== null)
            return (float)(maCacheGet($ck, 0) ?? 0);
    }

    // صرافی انتخاب‌شده اول، بعد بقیه — یک صرافی که از هاست شما در دسترس
    // نباشد نباید کل قیمت‌ها را بخواباند.
    $tries = [[$url, $path, (float)($r['div'] ?? 1), (string)($r['source'] ?? 'custom')]];
    foreach (maRateSources() as $sid => $src) {
        if ($sid === (string)($r['source'] ?? '')) continue;
        if (empty($src[$which . '_url'])) continue;
        $tries[] = [$src[$which . '_url'], $src[$which . '_path'], (float)$src['div'], $sid];
    }

    $j = null; $raw = 0; $div = 1; $errs = [];
    foreach ($tries as [$u, $pth, $dv, $sid]) {
        [$jj, $err] = maHttp($u, 'GET', '', '', (int)($r['timeout'] ?? 4));
        if (!$jj) { $errs[] = $sid . ': ' . ($err ?: 'پاسخی نیامد'); continue; }
        $v = maNum(maJsonPath($jj, $pth));
        if ($v <= 0) {
            $errs[] = $sid . ': مسیر «' . $pth . '» پیدا نشد یا صفر بود (کلیدها: ' .
                      implode(', ', array_slice(array_keys($jj), 0, 6)) . ')';
            continue;
        }
        $j = $jj; $raw = $v; $div = max(1, $dv);
        if ($sid !== (string)($r['source'] ?? '')) maCachePut('ratesrc_' . $which, $sid);
        break;
    }

    if ($raw <= 0) {
        maCachePut('rateerr_' . $which, implode(' | ', $errs) ?: 'هیچ صرافی‌ای جواب نداد');
        maCachePut('ratecool_' . $which, time());   // تا مدتی دیگر سراغ شبکه نرو
        return (float)(maCacheGet($ck, 0) ?? 0);    // کش قدیمی بهتر از هیچ
    }
    maCachePut('rateerr_' . $which, '');
    maCachePut('ratecool_' . $which, 0);            // موفق شد، دوره‌ی استراحت لغو
    $val = ($raw / $div) * (1 + ((float)($r['margin'] ?? 0) / 100));
    $val = maRound($val, (float)($r['round'] ?? 0));

    maCachePut($ck, $val);
    return $val;
}

/** تنظیمات آماده هر صرافی — کاربر فقط اسم صرافی را انتخاب می‌کند */
function maRateSources() {
    return [
        'nobitex' => [
            'name'     => 'نوبیتکس',
            'ton_url'  => 'https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=rls',
            'ton_path' => 'stats.ton-rls.latest',
            'trx_url'  => 'https://api.nobitex.ir/market/stats?srcCurrency=trx&dstCurrency=rls',
            'trx_path' => 'stats.trx-rls.latest',
            'usdt_url' => 'https://api.nobitex.ir/market/stats?srcCurrency=usdt&dstCurrency=rls',
            'usdt_path'=> 'stats.usdt-rls.latest',
            'div'      => 10,           // نوبیتکس ریال می‌دهد
        ],
        'wallex' => [
            'name'     => 'والکس',
            'ton_url'  => 'https://api.wallex.ir/v1/markets',
            'ton_path' => 'result.symbols.TONTMN.stats.lastPrice',
            'trx_url'  => 'https://api.wallex.ir/v1/markets',
            'trx_path' => 'result.symbols.TRXTMN.stats.lastPrice',
            'usdt_url' => 'https://api.wallex.ir/v1/markets',
            'usdt_path'=> 'result.symbols.USDTTMN.stats.lastPrice',
            'div'      => 1,            // والکس تومان می‌دهد
        ],
    ];
}

/** اعمال تنظیمات آماده یک صرافی */
function maApplyRateSource($id) {
    $src = maRateSources()[$id] ?? null;
    if (!$src) return false;
    maSetRoot(function (&$m) use ($id, $src) {
        $m['rates']['source'] = $id;
        foreach (['ton_url','ton_path','trx_url','trx_path','usdt_url','usdt_path','div'] as $k)
            $m['rates'][$k] = $src[$k];
    });
    save('ma_cache', []);
    return true;
}

/**
 * گِرد کردن مبلغ تومانی.
 *
 * تومان جزء ندارد. وقتی قیمت اعشار داشت، کاربر روی صفحه «۲۹۵٬۹۰۰» را
 * می‌دید ولی از کیف پولش ۲۹۵٬۹۰۰٫۴۷ خواسته می‌شد و پرداخت با پیامِ
 * «موجودی کافی نیست» رد می‌شد — همان «۱ تومن این‌ور آن‌ور». حالا هر
 * مبلغ به تومانِ کامل بالا گِرد می‌شود، پس عددی که دیده می‌شود دقیقا
 * همان عددی است که کسر می‌شود.
 */
function maRound($v, $step) {
    $v = (float)$v;
    if ($step <= 0) return (float)ceil($v - 1e-9);
    return (float)(ceil($v / $step - 1e-9) * $step);
}

/** هر مبلغ تومانی که قرار است نمایش داده یا کسر شود */
function maMoney($v) { return (float)ceil((float)$v - 1e-9); }

// ---------- مارکت گیفت ----------

/** [شناسه گیفت => قیمت به ارز مبدا] — از کش یا از API */
function maMarketMap($fresh = false) {
    $m = maCfg()['market'] ?? [];
    if (empty($m['on'])) return [];

    if (!$fresh) {
        $hit = maCacheGet('market_map', (int)($m['ttl'] ?? 600));
        if (is_array($hit)) return $hit;
    }

    [$list, $err] = maMarketFetch($m);
    if (!is_array($list)) {
        $old = maCacheGet('market_map', 0);
        return is_array($old) ? $old : [];
    }

    maCachePut('market_map', $list);
    return $list;
}

/** خواندن و تجزیه پاسخ مارکت — برگشت: [آرایه شناسه=>قیمت, خطا] */
function maMarketFetch($m) {
    [$j, $err] = maHttp($m['url'] ?? '', $m['method'] ?? 'GET',
                        $m['headers'] ?? '', $m['body'] ?? '', 10);
    if (!$j) return [null, $err];

    $rows = maJsonPath($j, (string)($m['list_path'] ?? ''));
    if (!is_array($rows)) return [null, 'مسیر فهرست پیدا نشد: ' . ($m['list_path'] ?: '(خالی)')];

    $kf = (string)($m['key_field'] ?? 'name');
    $pf = (string)($m['price_field'] ?? 'price');

    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $k = maJsonPath($row, $kf);
        $p = maNum(maJsonPath($row, $pf));
        if (!is_scalar($k) || $p <= 0) continue;
        $key = maMarketKey((string)$k);
        // ارزان‌ترین آگهی هر گیفت ملاک است
        if (!isset($out[$key]) || $p < $out[$key]) $out[$key] = $p;
    }
    if (!$out) return [null, 'هیچ ردیف معتبری پیدا نشد — فیلد نام یا قیمت درست نیست'];
    return [$out, ''];
}

/** «Birthday Cake» و «birthday_cake» یکی حساب می‌شوند */
function maMarketKey($s) {
    $s = mb_strtolower(trim((string)$s));
    return preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '_', $s);
}

// ---------- قیمت نهایی هر سرویس ----------

/**
 * قیمت زنده یک سرویس به تومان — یا null اگر منبع زنده‌ای ندارد.
 * هم موقع نمایش و هم موقع ثبت سفارش از همین تابع استفاده می‌شود
 * تا قیمت نمایش‌داده‌شده و قیمت فاکتور هیچ‌وقت از هم جدا نشوند.
 */
function maLivePrice($item) {
    $c = maCfg();

    // ۱) مارکت گیفت
    $mk = trim((string)($item['market_key'] ?? ''));
    if ($mk !== '' && !empty($c['market']['on'])) {
        $map = maMarketMap();
        $raw = (float)($map[maMarketKey($mk)] ?? 0);
        if ($raw > 0) {
            $cur = strtolower((string)($c['market']['price_cur'] ?? 'ton'));
            $unit = in_array($cur, ['ton', 'trx', 'usdt'], true) ? maRate($cur) : 1.0;
            if ($unit > 0) {
                $v = $raw * $unit * (1 + ((float)($c['market']['margin'] ?? 0) / 100));
                return maRound($v, (float)($c['market']['round'] ?? 0));
            }
        }
    }

    // ۲) ⭐️ استارز
    //
    // عددی که اینجا درمی‌آید باید مو‌به‌مو همان چیزی باشد که در گروه
    // نوشته می‌شود. پس نه گِردِ پله‌ای (که ۱۴۸٬۱۴۷ را می‌کرد ۱۴۹٬۰۰۰)
    // و نه نرخِ دستیِ کهنه — که ۲٬۹۶۳ تومانی را ۱٬۹۰۰ می‌فروخت و
    // هر سفارش یعنی ضرر. نرخ که نیامد، این سرویس فعلا فروختنی نیست.
    $st = (float)($item['stars'] ?? 0);
    if ($st > 0) {
        if (function_exists('pxStars') && !empty(pxVal('on'))) {
            $d = pxStars($st);
            if ($d && $d['irt'] > 0) return maMoney($d['irt']);
        }
        // نرخ دستی فقط وقتی که خودتان عمدا روشنش کرده باشید. اگر روشن
        // نباشد، نرخ که نیامد این سرویس فروختنی نیست — بهتر از فروختن
        // با عددِ کهنه و ضرر کردن.
        if (!empty($c['stars']['on'])) {
            $p = (float)($c['stars']['price'] ?? 0);
            if ($p > 0) return maRound($st * $p, (float)($c['stars']['round'] ?? 0));
        }
        return null;
    }

    // ۲.۵) 💎 پریمیوم — قیمت دلاری‌اش روی فرگمنت ثابت است، فقط نرخ ارز عوض می‌شود
    $pm = (int)($item['premium'] ?? 0);
    if ($pm > 0 && function_exists('pxPremiumRows') && !empty(pxVal('on'))) {
        $rows = pxPremiumRows();
        if (isset($rows[$pm]) && $rows[$pm]['irt'] > 0) return maMoney($rows[$pm]['irt']);
        return null;
    }

    // ۳) نرخ ارز (تون/ترون) — قیمت هر واحد
    $rk = trim((string)($item['rate_key'] ?? ''));
    if ($rk !== '' && !empty($c['rates']['on'])) {
        $v = maRate($rk);
        if ($v > 0) return $v;
    }

    return null;
}

/** قیمت قابل استفاده: زنده اگر بود، وگرنه قیمت دستی */
/**
 * قیمتی که مشتری واقعا می‌پردازد.
 *
 * تا حالا سود و «قیمت دستی» که در پنل تنظیم می‌شد فقط در خودِ پنل
 * نشان داده می‌شد و هیچ‌وقت روی فاکتور نمی‌نشست — یعنی هر عددی هم
 * می‌گذاشتید، مشتری قیمت پایه را می‌داد.
 */
function maItemPrice($item) {
    $live = maLivePrice($item);
    $base = $live !== null ? (float)$live : (float)($item['price'] ?? 0);

    if (function_exists('axPrice')) {
        // 🔒 سرویسی که نرخش زنده است، «قیمت دستی» نمی‌پذیرد.
        //
        // وگرنه یک عددِ قدیمی در پنل، قیمت لحظه‌ای فرگمنت را کنار
        // می‌زد و مینی‌اپ ۹۵٬۰۰۰ نشان می‌داد در حالی که گروه
        // ۱۴۸٬۱۴۷ می‌گفت. سود دسته‌ای سرِ جایش می‌ماند.
        $base = ($live !== null && maNeedsLive($item))
            ? (float)axPriceMargin($base, (string)($item['cat'] ?? ''), (string)($item['id'] ?? ''))
            : (float)axPrice((string)($item['id'] ?? ''), $base, (string)($item['cat'] ?? ''));
    }
    // تومانِ کامل — تا قیمتِ روی صفحه و مبلغِ کسرشده یکی باشند
    return maMoney($base);
}

/**
 * آیا این سرویس قیمتش باید زنده باشد؟ (تون، ترون، تتر و گیفت مارکت)
 * برای اینها عدد ثابتِ داخل تنظیمات فقط یک پیش‌فرض قدیمی است و
 * فروختن با آن یعنی ضرر — پس وقتی نرخ نمی‌آید اصلا نباید فروخته شوند.
 */
function maNeedsLive($item) {
    // وقتی موتور قیمت روشن است، استارز و پریمیوم هم نرخ لحظه‌ای می‌خواهند:
    // قیمت دلاری‌شان روی فرگمنت ثابت است ولی نرخ ارز هر لحظه عوض می‌شود،
    // و فروختن با عدد دیروز یعنی ضرر.
    if (function_exists('pxVal') && !empty(pxVal('on'))) {
        if ((float)($item['stars'] ?? 0) > 0)   return true;
        if ((int)($item['premium'] ?? 0) > 0)   return true;
    }
    return trim((string)($item['rate_key'] ?? '')) !== ''
        || trim((string)($item['market_key'] ?? '')) !== '';
}

/** سرویسی که نرخ زنده می‌خواهد ولی نرخش نیامده — یعنی فعلا قابل فروش نیست */
function maPriceStale($item) {
    return maNeedsLive($item) && maLivePrice($item) === null;
}

/** آیا این سرویس قیمتش زنده است؟ (برای نشان دادن نشانه در مینی‌اپ) */
function maIsLive($item) {
    return maLivePrice($item) !== null;
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

/** آیا دکمه‌های مینی‌اپ داخل لیست زیردکمه‌های «ثبت سفارش» ادغام شوند؟ */
function maMergeOn() {
    $c = maCfg();
    return !isset($c['merge']) || !empty($c['merge']);
}

/**
 * دکمه‌های مینی‌اپ به شکل «زیردکمه» — تا در همان لیست ثبت سفارش،
 * با همان ترتیب و چیدمان، کنار بقیه بنشینند.
 */
function maSubItems() {
    $out = [];
    foreach (maKeys() as $k) {
        if (!maReady($k)) continue;
        $a = maGet($k);
        $b = $a['btn'] ?? [];
        $out[] = [
            'id'      => '__ma_' . $k,
            'emoji'   => (string)($b['emoji'] ?? ''),
            'text'    => (string)($b['text'] ?? (maAppLabels()[$k] ?? $k)),
            'color'   => (string)($b['color'] ?? 'none'),
            'icon'    => (string)($b['icon'] ?? ''),
            'order'   => (int)($b['order'] ?? 99),
            'row'     => (int)($b['row'] ?? 0),
            'on'      => true,
            'action'  => '',
            '_webapp' => maUrl($k),
        ];
    }
    return $out;
}

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

    $items = [];
    foreach ($list as $x) {
        $a = $x['app'];
        $label = trim((string)($a['btn']['emoji'] ?? '') . ' ' . (string)($a['btn']['text'] ?? ''));
        if ($label === '') $label = maAppLabels()[$x['key']] ?? $x['key'];

        $b = ['text' => $label, 'web_app' => ['url' => maUrl($x['key'])]];
        if (isStyle($a['btn']['color'] ?? '')) $b['style'] = $a['btn']['color'];
        if (!empty($a['btn']['icon'])) $b['icon_custom_emoji_id'] = (string)$a['btn']['icon'];
        $items[] = $b;
    }
    if (!$items) return [];

    // چیدمان دلخواه ادمین — مثل چیدمان زیردکمه‌های ثبت سفارش
    $layout = trim((string)(maCfg()['row_layout'] ?? ''));
    return $layout !== '' ? layoutRows($items, $layout) : array_map(fn($b) => [$b], $items);
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

    /** فایل داغ، و اگر نبود بایگانی — تا سفارش قدیمی هم پیدا شود */
    public static function get($id) {
        $a = load('ma_orders');
        if (isset($a[$id])) return $a[$id];
        $b = load('ma_orders_old');
        return $b[$id] ?? null;
    }

    public static function allWithArchive() {
        return load('ma_orders') + load('ma_orders_old');
    }

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

    /**
     * تغییر یک سفارش زیر قفل.
     * اگر کال‌بک چیزی برگرداند، همان برمی‌گردد — این برای «قفل گرفتن» لازم است:
     * تابع می‌تواند false برگرداند تا بگوید شرط برقرار نبود.
     */
    /** سفارشی که هیچ‌وقت پرداخت نشد و نگه داشتنش فایده‌ای ندارد */
    public static function remove($id) {
        mutate('ma_orders', function (&$a) use ($id) { unset($a[(string)$id]); });
    }

    public static function set($id, callable $fn) {
        return mutate('ma_orders', function (&$a) use ($id, $fn) {
            if (!isset($a[$id])) return false;
            $r = $fn($a[$id]);
            return $r === null ? true : $r;
        });
    }

    /**
     * سفارش‌های یک کاربر. با $app، فقط سفارش‌های همان مینی‌اپ.
     * بدون این، کسی که داخل «فروش کانفیگ» بود سفارش استارزش را هم
     * آنجا می‌دید — دو فروشگاه جدا که فهرست سفارششان قاطی بود.
     */
    public static function forUser($uid, $limit = 10, $app = null) {
        $out = [];
        foreach (self::all() as $o) {
            if ((int)$o['user_id'] !== (int)$uid) continue;
            if ($app !== null && (string)($o['app'] ?? '') !== (string)$app) continue;
            $out[] = $o;
        }
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
function maVerifyInitData($initData, &$reason = null, $maxAge = 0) {
    $reason = '';
    $initData = (string)$initData;

    if ($initData === '')            { $reason = 'empty';    return null; }
    if (strlen($initData) > 8192)    { $reason = 'too_big';  return null; }

    // parse_str نام کلیدها را دستکاری می‌کند (نقطه و فاصله را زیرخط می‌کند و
    // براکت را آرایه می‌خواند). برای رشته‌ای که قرار است بایت‌به‌بایت هش شود
    // این خطرناک است، پس خودمان می‌شکافیم.
    $q = [];
    foreach (explode('&', $initData) as $part) {
        if ($part === '') continue;
        $eq = strpos($part, '=');
        if ($eq === false) continue;
        $q[urldecode(substr($part, 0, $eq))] = urldecode(substr($part, $eq + 1));
    }

    if (empty($q['hash']))           { $reason = 'no_hash';  return null; }
    if (empty($q['user']))           { $reason = 'no_user';  return null; }

    $hash = (string)$q['hash'];
    unset($q['hash']);

    // فیلد signature را تلگرام برای اعتبارسنجی شخص ثالث اضافه کرده و
    // مستنداتش روشن نمی‌گوید داخل رشته‌ی هش می‌آید یا نه. به‌جای حدس زدن،
    // هر دو حالت را می‌سنجیم — یکی‌شان حتما درست است، و اگر تلگرام روزی
    // قاعده را عوض کند باز هم کار می‌کند.
    $secret = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $mkCheck = function ($fields) {
        ksort($fields);
        $pairs = [];
        foreach ($fields as $k => $v) $pairs[] = $k . '=' . $v;
        return implode("\n", $pairs);
    };

    $withSig = $q;
    $noSig   = $q; unset($noSig['signature']);

    $matched = '';
    foreach (['بدون signature' => $noSig, 'با signature' => $withSig] as $how => $fields) {
        if (hash_equals(hash_hmac('sha256', $mkCheck($fields), $secret), $hash)) { $matched = $how; break; }
        if ($noSig === $withSig) break;      // signature اصلا نبود، دو بار سنجیدن بی‌معنی است
    }
    if ($matched === '') { $reason = 'bad_hash'; return null; }

    // ⏰ عمر داده — با تحمل اختلاف ساعت سرور.
    // سقف کوتاه (یک ساعت) روی هاست‌هایی که ساعتشان تنظیم نیست همه را بیرون می‌انداخت،
    // در حالی که محدودیت نرخ و سقف سفارش هر نشست کار امنیتی را انجام می‌دهند.
    if ($maxAge <= 0) $maxAge = (int)(maCfg()['init_max_age'] ?? 86400);
    if ($maxAge > 0 && !empty($q['auth_date'])) {
        $age = time() - (int)$q['auth_date'];
        if ($age > $maxAge)   { $reason = 'expired:' . $age;  return null; }
        if ($age < -86400)    { $reason = 'clock_skew:' . $age; return null; }   // ساعت سرور خیلی عقب است
    }

    $user = json_decode((string)$q['user'], true);
    if (!is_array($user) || empty($user['id'])) { $reason = 'bad_user'; return null; }

    return $user;
}

/** پیام فارسی برای هر دلیل شکست — به ادمین کمک می‌کند بفهمد مشکل کجاست */
function maAuthReasonText($reason) {
    $r = (string)$reason;
    if (str_starts_with($r, 'expired:')) {
        $sec = (int)substr($r, 8);
        return 'داده ورود منقضی شده (' . round($sec / 60) . ' دقیقه قدیمی‌تر). ' .
               'اگر تازه باز کردید، یعنی ساعت سرور جلو است.';
    }
    if (str_starts_with($r, 'clock_skew:')) {
        $sec = (int)substr($r, 11);
        return 'ساعت سرور ' . round(abs($sec) / 60) . ' دقیقه عقب است — آن را درست کنید.';
    }
    return [
        'empty'    => 'مینی‌اپ بدون اطلاعات ورود باز شده. آن را از دکمه داخل ربات باز کنید، نه از مرورگر.',
        'no_hash'  => 'امضای تلگرام در داده ورود نبود.',
        'no_user'  => 'اطلاعات کاربر در داده ورود نبود. مینی‌اپ را از چت خصوصی ربات باز کنید.',
        'bad_hash' => maBadHashText(),
        'bad_user' => 'اطلاعات کاربر خوانده نشد.',
        'too_big'  => 'داده ورود بیش از حد بزرگ بود.',
    ][$r] ?? 'اعتبارسنجی ناموفق بود.';
}

/**
 * وقتی امضا نمی‌خواند، مفیدترین چیزی که می‌شود گفت این است که
 * توکنِ داخل فایل مال کدام ربات است — کاربر همان‌جا با ربات خودش می‌سنجد.
 */
function maBadHashText() {
    $un = maCacheGet('selfbot', 3600);
    if ($un === null) {
        $me = tg(BOT_TOKEN, 'getMe', []);
        $un = !empty($me['result']['username']) ? '@' . $me['result']['username'] : '';
        maCachePut('selfbot', $un);
    }
    $t = 'امضای تلگرام نخواند.' . "\n\n" .
         'یعنی توکنی که در فایل ربات گذاشته‌اید، مال رباتی نیست که این دکمه را ساخته.';
    if ($un !== '') {
        $t .= "\n\n" . 'توکن داخل فایل مال ربات ' . $un . ' است.' . "\n" .
              'اگر این همان رباتی نیست که الان داخلش هستید، توکن را عوض کنید:' . "\n" .
              'خط ۲۰ فایل bot_master_membership.php';
    }
    $t .= "\n\n" . 'اگر تازه از @BotFather توکن را Revoke کرده‌اید، توکن تازه را در فایل بگذارید و وبهوک را دوباره ست کنید.';
    return $t;
}

// ============================================================
// 🤖 تحویل خودکار — اتصال به پنل فروش بیرونی
// ============================================================

/** جای‌گذاری {username} و {qty} و … در قالب مسیر یا بدنه */
function maFillTpl($tpl, $vars) {
    $out = (string)$tpl;
    foreach ($vars as $k => $v) {
        // داخل JSON، رشته باید امن escape شود
        $json = trim(json_encode((string)$v, JSON_UNESCAPED_UNICODE), '"');
        $out  = str_replace('{' . $k . '}', $json, $out);
    }
    return $out;
}

/** یک عملیات روی پنل فروش — برگشت: [پاسخ, خطا] */
function maFulfillCall($op, $vars = []) {
    $f = maCfg()['fulfill'] ?? [];
    if (trim((string)($f['base'] ?? '')) === '') return [null, 'آدرس پنل ثبت نشده'];

    $cfgOp = $f['ops'][$op] ?? null;
    if (!is_array($cfgOp)) return [null, 'عملیات «' . $op . '» تعریف نشده'];

    $path = maFillTpl($cfgOp['path'] ?? '', $vars);
    $url  = rtrim((string)$f['base'], '/') . '/' . ltrim($path, '/');
    $body = maFillTpl($cfgOp['body'] ?? '', $vars);
    $method = strtoupper((string)($cfgOp['method'] ?? 'POST'));

    // احراز هویت به شکلی که پنل می‌خواهد
    $headers = '';
    $ak = trim((string)($f['auth_key'] ?? ''));
    $av = (string)($f['auth_value'] ?? '');
    switch ((string)($f['auth_type'] ?? 'header')) {
        case 'header':
            if ($ak !== '') $headers = $ak . ': ' . $av;
            break;
        case 'query':
            if ($ak !== '') $url .= (str_contains($url, '?') ? '&' : '?') . rawurlencode($ak) . '=' . rawurlencode($av);
            break;
        case 'body':
            if ($ak !== '' && $method === 'POST') {
                $b = json_decode($body ?: '{}', true);
                if (is_array($b)) { $b[$ak] = $av; $body = json_encode($b, JSON_UNESCAPED_UNICODE); }
            }
            break;
    }

    return maHttp($url, $method, $headers, $body, (int)($f['timeout'] ?? 20));
}

/** آیا این پاسخ یعنی موفقیت؟ */
function maFulfillOk($resp, $cfgOp) {
    if (!is_array($resp)) return false;
    // اگر پنل فیلد خطا پر کرده، شکست است
    $errPath = (string)($cfgOp['err_path'] ?? '');
    if ($errPath !== '') {
        $e = maJsonPath($resp, $errPath);
        if (is_string($e) && trim($e) !== '' && strtolower(trim($e)) !== 'ok' && strtolower(trim($e)) !== 'success')
            return false;
    }
    // فیلدهای رایج «موفق بود؟»
    foreach (['ok', 'success', 'status'] as $k) {
        if (!array_key_exists($k, $resp)) continue;
        $v = $resp[$k];
        if (is_bool($v)) return $v;
        if (is_numeric($v)) return (int)$v > 0;
        if (is_string($v)) {
            $v = strtolower(trim($v));
            if (in_array($v, ['ok', 'true', 'success', 'done', 'completed', 'processing', 'pending'], true)) return true;
            if (in_array($v, ['error', 'false', 'failed', 'fail', 'rejected'], true)) return false;
        }
    }
    return true;   // پاسخ JSON آمد و خطایی اعلام نشد
}

/** متن خطای پنل */
function maFulfillErr($resp, $cfgOp) {
    if (!is_array($resp)) return 'پاسخ نامعتبر';
    foreach ([(string)($cfgOp['err_path'] ?? ''), 'message', 'error', 'detail', 'msg'] as $pth) {
        if ($pth === '') continue;
        $v = maJsonPath($resp, $pth);
        if (is_string($v) && trim($v) !== '') return mb_substr($v, 0, 200);
    }
    return 'خطای نامشخص';
}

/** سرویسِ یک سفارش، کدام عملیات خودکار را می‌خواهد؟ */
function maAutoOp($o) {
    $i = maFindItem($o['app'], $o['item_id']);
    if (!$i) return [null, []];
    $op = trim((string)($i['auto'] ?? ''));
    if ($op === '' || $op === 'none') return [null, []];

    $uname = ltrim(trim((string)$o['field']), '@');
    $qty   = (float)($i['auto_qty'] ?? 0);
    if ($qty <= 0) $qty = (float)($o['qty'] ?? 1);

    return [$op, [
        'username' => $uname,
        'user'     => $uname,
        'qty'      => (int)$qty,
        'amount'   => (int)$qty,
        'gift'     => (string)($i['auto_id'] ?? $i['market_key'] ?? ''),
        'order'    => (string)$o['id'],
        'user_id'  => (string)$o['user_id'],
        'field'    => (string)$o['field'],
    ]];
}

/**
 * تحویل خودکار یک سفارش پرداخت‌شده.
 * هیچ‌وقت پول را برنمی‌گرداند و هیچ‌وقت دوبار سفارش نمی‌دهد —
 * قبل از تماس، سفارش را «در حال ارسال» علامت می‌زند تا اجرای همزمان رخ ندهد.
 */
function maAutoFulfill($orderId, $manual = false) {
    $f = maCfg()['fulfill'] ?? [];
    $o = MaOrder::get($orderId);
    if (!$o) return [false, 'سفارش پیدا نشد'];
    if ($o['status'] !== MaOrder::PAID) return [false, 'سفارش در وضعیت پرداخت‌شده نیست'];

    [$op, $vars] = maAutoOp($o);
    if (!$op) return [false, 'این سرویس تحویل خودکار ندارد'];
    if (empty($f['on'])) return [false, 'تحویل خودکار خاموش است'];

    // 🔒 قفل: فقط یک اجرا در لحظه — جلوی سفارش دوباره روی پنل را می‌گیرد
    $claimed = MaOrder::set($orderId, function (&$x) {
        if (!empty($x['sending'])) return false;
        $x['sending'] = time();
        $x['tries']   = (int)($x['tries'] ?? 0) + 1;
        return true;
    });
    if (!$claimed) return [false, 'همین حالا در حال ارسال است'];

    $cfgOp = $f['ops'][$op] ?? [];
    [$resp, $err] = maFulfillCall($op, $vars);

    if (!$resp) {
        MaOrder::set($orderId, function (&$x) use ($err) {
            $x['sending'] = 0; $x['last_error'] = $err;
        });
        maAutoFailNotice($orderId, $err);
        return [false, $err];
    }

    if (!maFulfillOk($resp, $cfgOp)) {
        $msg = maFulfillErr($resp, $cfgOp);
        MaOrder::set($orderId, function (&$x) use ($msg) {
            $x['sending'] = 0; $x['last_error'] = $msg;
        });
        maAutoFailNotice($orderId, $msg);
        return [false, $msg];
    }

    // 👛 اگر پنل تراکنش امضانشده داده، همین‌جا امضا و ارسالش می‌کنیم
    if (function_exists('axWalletHandle')) {
        [$wok, $winfo] = axWalletHandle($resp, $orderId);
        if (!$wok) {
            MaOrder::set($orderId, function (&$x) use ($winfo) {
                $x['sending'] = 0; $x['last_error'] = $winfo;
            });
            maAutoFailNotice($orderId, 'تراکنش ولت انجام نشد: ' . $winfo);
            return [false, $winfo];
        }
        if ($winfo !== '') MaOrder::set($orderId, function (&$x) use ($winfo) { $x['ton_tx'] = $winfo; });
    }

    $ref = '';
    if (!empty($cfgOp['id_path'])) {
        $v = maJsonPath($resp, (string)$cfgOp['id_path']);
        if (is_scalar($v)) $ref = (string)$v;
    }

    MaOrder::set($orderId, function (&$x) use ($ref) {
        $x['status'] = MaOrder::DONE;
        $x['sending'] = 0;
        $x['last_error'] = '';
        $x['provider_ref'] = $ref;
        $x['delivered_at'] = nowStr();
        $x['auto'] = true;
    });

    $o = MaOrder::get($orderId);
    if (function_exists('axReportOrder')) axReportOrder($o, 'done');
    maTellUser($o,
        "✅ <b>سفارش شما انجام شد</b>\n\n" .
        '📦 ' . h(maOrderTitle($o)) . "\n" .
        ((float)$o['qty'] > 1 ? '🔢 ' . fmtNum($o['qty']) . ' ' . h($o['unit']) . "\n" : '') .
        (trim((string)$o['field']) !== '' ? '📎 ' . h($o['field']) . "\n" : '') .
        '🔑 <code>' . h($o['id']) . "</code>\n" .
        ($ref !== '' ? '🧾 کد پنل: <code>' . h($ref) . "</code>\n" : '') .
        "\n🙏 از خرید شما سپاسگزاریم.");

    sendMsg(BOT_TOKEN, ADMIN_ID,
        "🤖 <b>تحویل خودکار انجام شد</b>\n\n" .
        '📦 ' . h(maOrderTitle($o)) . "\n" .
        '👤 <code>' . $o['user_id'] . "</code>\n" .
        '💰 ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n" .
        '🔑 <code>' . h($o['id']) . '</code>' .
        ($ref !== '' ? "\n🧾 " . h($ref) : ''));

    return [true, $ref];
}

/** به ادمین خبر بده که تحویل خودکار نشد و راه‌های ادامه را بده */
function maAutoFailNotice($orderId, $err) {
    $o = MaOrder::get($orderId);
    if (!$o) return;
    $max = (int)(maCfg()['fulfill']['retry'] ?? 3);
    $n   = (int)($o['tries'] ?? 1);

    $t  = "⚠️ <b>تحویل خودکار ناموفق</b>\n\n";
    $t .= '📦 ' . h(maOrderTitle($o)) . "\n";
    $t .= '👤 <code>' . $o['user_id'] . '</code> ' . ($o['username'] ? '@' . h($o['username']) : '') . "\n";
    if (trim((string)$o['field']) !== '') $t .= '📎 ' . h($o['field']) . "\n";
    $t .= '💰 ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n";
    $t .= '🔑 <code>' . h($o['id']) . "</code>\n";
    $t .= '🔁 تلاش ' . $n . ' از ' . $max . "\n\n";
    $t .= '❌ ' . h($err) . "\n\n";
    $t .= ($n < $max
        ? 'خودکار دوباره تلاش می‌شود. می‌توانید همین حالا هم دستی اقدام کنید:'
        : '<b>تلاش خودکار تمام شد</b> — پول کاربر گرفته شده و سفارش تحویل نشده. یکی را انتخاب کنید:');

    sendMsg(BOT_TOKEN, ADMIN_ID, $t, inlineKb([
        [btnCb('🔁 تلاش دوباره', 'maretry_' . $o['id'], 'confirm')],
        [btnCb('📤 تحویل دستی', 'madlv_' . $o['id'], 'link')],
        [btnCb('💰 برگشت پول به کاربر', 'marefund_' . $o['id'], 'reject')],
    ]));
}

/**
 * صف تلاش دوباره — از همان cron موجود ربات صدا زده می‌شود.
 * سفارش‌های پرداخت‌شده‌ای که تحویل نشده‌اند و هنوز تلاش باقی دارند.
 */
function maAutoQueue($limit = 5) {
    $f = maCfg()['fulfill'] ?? [];
    if (empty($f['on'])) return 0;

    $max  = (int)($f['retry'] ?? 3);
    $done = 0;
    $now  = time();

    foreach (MaOrder::all() as $o) {
        if ($done >= $limit) break;
        if ($o['status'] !== MaOrder::PAID) continue;

        [$op] = maAutoOp($o);
        if (!$op) continue;

        $tries = (int)($o['tries'] ?? 0);
        if ($tries >= $max) continue;

        // قفل رهاشده (اجرای قبلی وسط کار مرد) بعد از ۵ دقیقه آزاد می‌شود
        $sending = (int)($o['sending'] ?? 0);
        if ($sending > 0 && ($now - $sending) < 300) continue;
        if ($sending > 0) MaOrder::set($o['id'], function (&$x) { $x['sending'] = 0; });

        // فاصله فزاینده بین تلاش‌ها: ۱، ۴، ۹ دقیقه
        $last = strtotime((string)($o['decided_at'] ?? $o['created_at'])) ?: 0;
        if ($tries > 0 && ($now - $last) < (60 * $tries * $tries)) continue;

        maAutoFulfill($o['id']);
        $done++;
    }
    return $done;
}

/**
 * سفارش‌هایی که موقع خرید مخزنشان خالی بود.
 * قولی که به مشتری داده‌ایم همین است: «به‌محض شارژ مخزن خودکار می‌آید» —
 * پس هر بار که ربات بیدار می‌شود، صف را دوباره نگاه می‌کند.
 */
function maStockQueue($limit = 5) {
    if (!function_exists('axStockCount') || empty(axVal('stock.on'))) return 0;

    $done = 0;
    $now  = time();
    foreach (MaOrder::all() as $o) {
        if ($done >= $limit) break;
        if (($o['status'] ?? '') !== MaOrder::PAID) continue;
        if (!empty($o['manual_msg'])) continue;                 // دست ادمین است
        if (function_exists('axIsManual') && axIsManual($o['item_id'])) continue;

        // قفل رهاشده (اجرای قبلی وسط کار مرد) بعد از ۵ دقیقه آزاد می‌شود
        $sending = (int)($o['sending'] ?? 0);
        if ($sending > 0 && ($now - $sending) < 300) continue;

        if (axStockCount($o['item_id']) < max(1, (int)$o['qty'])) continue;

        $claimed = MaOrder::set($o['id'], function (&$x) {
            if (($x['status'] ?? '') !== MaOrder::PAID) return false;
            $x['sending'] = time();
            return true;
        });
        if (!$claimed) continue;

        [$ok, $err] = axStockDeliver($o);
        MaOrder::set($o['id'], function (&$x) use ($ok, $err) {
            $x['sending'] = 0;
            if ($ok) {
                $x['status'] = MaOrder::DONE;
                $x['delivered_at'] = nowStr();
                $x['delivered_by'] = 'stock';
            } else {
                $x['last_error'] = $err;
            }
        });
        if ($ok) {
            if (function_exists('axReportOrder')) axReportOrder(MaOrder::get($o['id']), 'done');
            $done++;
        }
    }
    return $done;
}

// ============================================================
// 👛 کیف پول — کسر اتمیک
// ============================================================

/**
 * برداشت از کیف پول در یک قفل واحد.
 *
 * مهم: بررسی موجودی و کسر باید داخل یک mutate باشند. اگر جدا باشند،
 * دو درخواست همزمان هر دو موجودی را کافی می‌بینند و هر دو کم می‌کنند —
 * یعنی کاربر با یک موجودی دو خرید می‌کند و حساب منفی می‌شود.
 *
 * برگشت: true اگر واقعا کسر شد.
 */
function maDebit($userId, $amount) {
    $amount = round((float)$amount, 2);
    if ($amount <= 0) return false;

    return (bool)mutate('users', function (&$users) use ($userId, $amount) {
        $k = (string)$userId;
        if (!isset($users[$k])) return false;
        $bal = round((float)($users[$k]['balance'] ?? 0), 2);
        if ($bal + 0.001 < $amount) return false;          // موجودی کافی نیست
        $users[$k]['balance'] = round($bal - $amount, 2);
        return true;
    });
}

/** برگرداندن پول به کیف پول (وقتی تحویل خودکار شکست بخورد و ادمین لغو کند) */
function maRefund($userId, $amount, $note = '') {
    $amount = round((float)$amount, 2);
    if ($amount <= 0) return;
    addBalance($userId, $amount);
    sendMsg(BOT_TOKEN, $userId,
        "💰 <b>مبلغ به کیف پول شما برگشت</b>\n\n" .
        '➕ ' . fmtNum($amount) . " تومان\n" .
        ($note !== '' ? '📝 ' . h($note) : ''));
}

/** پرداخت یک سفارش مینی‌اپ از کیف پول — برگشت: [موفق, پیام] */
function maPayFromWallet($orderId, $uid) {
    $o = MaOrder::get($orderId);
    if (!$o || (int)$o['user_id'] !== (int)$uid) return [false, 'سفارش پیدا نشد.'];
    if ($o['status'] !== MaOrder::PENDING)       return [false, 'این فاکتور قبلا بررسی شده.'];

    if (!maDebit($uid, (float)$o['total']))
        return [false, 'موجودی کیف پول کافی نیست.'];

    payReferralCommission($uid, (float)$o['total']);
    maMarkPaid($orderId, 'wallet');
    return [true, ''];
}

/**
 * 🗄 بایگانی سفارش‌های تمام‌شده‌ی مینی‌اپ.
 *
 * همان دلیلِ سفارش‌های ربات: هر ثبت سفارش کل فایل را بازنویسی می‌کند،
 * پس فایلِ بزرگ یعنی سفارشِ کند. تحویل‌شده‌ها و ردشده‌های قدیمی
 * می‌روند کنار و فایل داغ کوچک می‌ماند.
 */
function maOrdersArchive($days = 0, $limit = 4000) {
    $days = $days > 0 ? $days : (int)(cfg()['orders_keep_days'] ?? 14);
    if ($days <= 0) return 0;
    $cut = time() - $days * 86400;

    $moved = [];
    mutate('ma_orders', function (&$a) use ($cut, $limit, &$moved) {
        foreach ($a as $id => $o) {
            if (count($moved) >= $limit) break;
            $st = (string)($o['status'] ?? '');
            // فقط تمام‌شده‌ها؛ هرچه هنوز در جریان است می‌ماند
            if ($st !== MaOrder::DONE && $st !== MaOrder::REJECT) continue;
            $when = strtotime((string)($o['delivered_at'] ?: $o['decided_at'] ?: $o['created_at'] ?? '')) ?: 0;
            if ($when === 0 || $when > $cut) continue;
            $moved[$id] = $o;
            unset($a[$id]);
        }
    });
    if (!$moved) return 0;

    mutate('ma_orders_old', function (&$b) use ($moved) {
        foreach ($moved as $id => $o) $b[$id] = $o;
    });
    return count($moved);
}

// ============================================================
// 🛡 لایه امنیتی — محدودیت نرخ، ضد تکرار، ضد بازپخش
// ============================================================

/**
 * پنجره لغزان ساده: بیش از $limit بار در $win ثانیه = رد.
 * جلوی سیل درخواست، اسکریپت خودکار و آزمون‌وخطای مهاجم را می‌گیرد.
 */
/** تعداد تکه‌های فایل محدودیت نرخ */
if (!defined('MA_RATE_SHARDS')) define('MA_RATE_SHARDS', 16);

/**
 * فایل محدودیت نرخ به تکه تقسیم می‌شود.
 *
 * قبلا همه‌ی کاربران در یک فایل بودند: با هزار کاربر فعال آن فایل
 * ۲۲۸ کیلوبایت می‌شد و هر درخواست کلِ آن را با قفل انحصاری بازنویسی
 * می‌کرد — یعنی همه‌ی درخواست‌های مینی‌اپ پشت یک قفل صف می‌کشیدند.
 * حالا هر کلید در یکی از ۱۶ تکه می‌نشیند: هم فایل کوچک می‌ماند، هم
 * قفل‌ها روی هم نمی‌افتند.
 */
/**
 * IP واقعی کاربر.
 *
 * پشت کلادفلر/پروکسی، REMOTE_ADDR آدرسِ خود پروکسی است و همه‌ی
 * کاربران یکی می‌شوند. ولی هدرها را هم نمی‌شود همین‌طوری باور کرد —
 * هرکس می‌تواند X-Forwarded-For جعل کند. پس فقط وقتی به هدر اعتماد
 * می‌کنیم که ادمین صریحا گفته باشد پشت پروکسی است.
 */
function maClientIp() {
    $real = (string)($_SERVER['REMOTE_ADDR'] ?? '0');
    if (empty(maCfg()['trust_proxy'])) return $real;

    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR'] as $h) {
        $v = trim((string)($_SERVER[$h] ?? ''));
        if ($v === '') continue;
        $v = trim(explode(',', $v)[0]);          // اولی = خودِ کاربر
        if (filter_var($v, FILTER_VALIDATE_IP)) return $v;
    }
    return $real;
}

function maRateFile($k) {
    return 'ma_rate_' . (hexdec(substr(md5($k), 0, 4)) % MA_RATE_SHARDS);
}

function maRateOk($bucket, $id, $limit, $win) {
    $ok = true;
    $k  = $bucket . ':' . $id;
    mutate(maRateFile($k), function (&$a) use ($k, $limit, $win, &$ok) {
        $now = time();
        $hits = array_values(array_filter((array)($a[$k] ?? []), fn($t) => ($now - (int)$t) < $win));
        if (count($hits) >= $limit) { $ok = false; }
        else { $hits[] = $now; }
        $a[$k] = $hits;

        // خانه‌تکانی تا تکه بی‌نهایت بزرگ نشود
        if (count($a) > 200) {
            foreach ($a as $kk => $vv) {
                $last = is_array($vv) && $vv ? (int)max($vv) : 0;
                if (($now - $last) > 3600) unset($a[$kk]);
            }
        }
    });
    return $ok;
}

/**
 * سقف تعداد سفارش با یک initData.
 *
 * یک‌بارمصرف کردنش غلط بود: کاربر عادی در یک بار باز کردن مینی‌اپ ممکن است
 * چند چیز بخرد و initData در تمام آن نشست ثابت می‌ماند. پس به‌جای «فقط یک بار»،
 * سقف معقولی می‌گذاریم که خرید عادی آزاد باشد ولی کسی نتواند با یک initData
 * شنودشده صدها سفارش بسازد.
 */
function maNonceOk($initData, $max = 15) {
    $sig = substr(hash('sha256', (string)$initData), 0, 32);
    $ok  = true;
    mutate('ma_nonce', function (&$a) use ($sig, $max, &$ok) {
        $now = time();
        foreach ($a as $k => $v) {
            $last = is_array($v) ? (int)($v['at'] ?? 0) : (int)$v;
            if (($now - $last) > 7200) unset($a[$k]);
        }
        $cur = is_array($a[$sig] ?? null) ? $a[$sig] : ['n' => 0, 'at' => $now];
        if ((int)$cur['n'] >= $max) { $ok = false; return; }
        $a[$sig] = ['n' => (int)$cur['n'] + 1, 'at' => $now];
    });
    return $ok;
}

/** همان سفارش، دوبار پشت سر هم (دابل‌کلیک یا اسکریپت) */
function maDuplicateOrder($uid, $app, $itemId, $qty, $field, $win = 45) {
    $sig = hash('sha256', $uid . '|' . $app . '|' . $itemId . '|' . $qty . '|' . $field);
    $dup = false;
    mutate('ma_dup', function (&$a) use ($sig, $win, &$dup) {
        $now = time();
        foreach ($a as $k => $t) if (($now - (int)$t) > 600) unset($a[$k]);
        if (isset($a[$sig]) && ($now - (int)$a[$sig]) < $win) { $dup = true; return; }
        $a[$sig] = $now;
    });
    return $dup;
}

/** هدرهای امنیتی — مینی‌اپ فقط داخل تلگرام باز می‌شود، نه داخل سایت دیگران */
function maSecurityHeaders() {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header(
        "Content-Security-Policy: " .
        "default-src 'none'; " .
        "script-src 'self' 'unsafe-inline' https://telegram.org https://*.telegram.org; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src https://fonts.gstatic.com data:; " .
        // عکس پروفایل کاربر از سرور خود تلگرام می‌آید و جای دیگری مجاز نیست
        "img-src 'self' data: https://t.me https://*.telegram.org https://*.telesco.pe; " .
        "connect-src 'self'; " .
        "base-uri 'none'; form-action 'none'; " .
        "frame-ancestors https://web.telegram.org https://*.telegram.org; " .
        "object-src 'none'"
    );
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

    maSecurityHeaders();
    echo $key === 'tg' ? maViewTg($a, maBoot($key, $a)) : maViewCfg($a, maBoot($key, $a));
    exit;
}

/**
 * 💳 اطلاعات شارژ کارت‌به‌کارت — همان شماره‌ای که در پنل وب ست می‌شود.
 * شماره کارت محرمانه نیست (خریدار برای واریز می‌بیندش) ولی اگر خالی باشد
 * اصلا دکمه شارژ در مینی‌اپ نشان داده نمی‌شود تا کاربر سرگردان نشود.
 */
function maTopupInfo() {
    $w = cfg()['wallets'] ?? [];
    $g = cfg()['gateway'] ?? [];
    $card = trim((string)($w['card'] ?? ''));
    return [
        'on'   => $card !== '' ? 1 : 0,
        'card' => $card,
        'name' => trim((string)($w['card_name'] ?? '')),
        'min'  => (float)(cfg()['topup_min'] ?? 10000),
        'gw'   => (function_exists('gwOn') && gwOn()) ? 1 : 0,
        'gwmin'=> (float)($g['min'] ?? 0),
    ];
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
        'topup'    => maTopupInfo(),
        'api'      => maApiUrl(),
    ];
}

/** ثبت در دفترچه‌ی افزونه، اگر بود */
function axLogIf($what, $detail = '') {
    if (function_exists('axLog')) axLog($what, $detail);
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
            'price' => maItemPrice($i),
            'live'  => maIsLive($i) ? 1 : 0,
            'stale' => maPriceStale($i) ? 1 : 0,
            'unit'  => (string)($i['unit'] ?? ''),
            'ask'   => (string)($i['ask'] ?? 'none'),
            'min'   => (float)($i['min'] ?? 1),
            'max'   => (float)($i['max'] ?? 1),
            'order' => (int)($i['order'] ?? 99),
            'cpos'  => $catPos[(string)($i['cat'] ?? '')] ?? 999,
        ];
        // 📦 حجم دلخواه: فقط حجم‌هایی که واقعا در مخزن هستند به کاربر نشان داده شود
        if ((string)($i['ask'] ?? '') === 'volume') {
            $vols = [];
            $min = (int)($i['min'] ?? 500);
            $max = (int)($i['max'] ?? 102400);
            if (function_exists('axVolumeChoices')) {
                foreach (axVolumeChoices((int)floor($max / 1024)) as $mb) {
                    if ($mb < $min || $mb > $max) continue;
                    $have = function_exists('axStockCount') ? axStockCount($i['id'] . '_' . $mb) : 0;
                    if ($have < 1) continue;
                    $vols[] = ['mb' => $mb, 'label' => axVolumeLabel($mb), 'n' => $have,
                               'price' => round(maItemPrice($i) * ($mb / 1024), 0)];
                }
            }
            $items[count($items) - 1]['vols'] = $vols;
        }
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
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');

    // فقط POST — و بدنه بزرگ‌تر از ۳۲ کیلوبایت اصلا خوانده نمی‌شود
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST')
        maApiOut(['ok' => false, 'error' => 'bad_method'], 405);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768)
        maApiOut(['ok' => false, 'error' => 'too_large'], 413);

    // 🛡 سد اول: محدودیت نرخ روی IP، قبل از هر کار سنگینی.
    // سقف اینجا عمدا بالاست چون پشت کلادفلر یا اینترنت همراه، صدها
    // کاربر یک IP دارند؛ سدِ اصلی، محدودیت روی خودِ کاربر است که
    // بعد از بررسی امضای تلگرام اعمال می‌شود و جعل‌ناپذیر است.
    $ip = maClientIp();
    if (!maRateOk('ip', $ip, (int)(maCfg()['rate_ip'] ?? 600), 60))
        maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

    $raw  = file_get_contents('php://input', false, null, 0, 32768);
    $body = json_decode((string)$raw, true);
    if (!is_array($body)) $body = $_POST;

    $action = (string)($body['action'] ?? $_GET['action'] ?? '');
    $key    = (string)($body['app'] ?? $_GET['app'] ?? '');
    if (!in_array($key, maKeys(), true)) maApiOut(['ok' => false, 'error' => 'bad_app'], 400);

    $initData = (string)($body['initData'] ?? '');
    $reason = '';
    $user = maVerifyInitData($initData, $reason);
    if (!$user) {
        maApiOut(['ok' => false, 'error' => 'unauthorized', 'reason' => $reason,
                  'message' => maAuthReasonText($reason)], 401);
    }

    $uid   = (int)$user['id'];

    // 🛡 سد دوم: محدودیت نرخ روی خود کاربر — این همان سدی است که واقعا می‌گیرد
    if (!maRateOk('u', $uid, (int)(maCfg()['rate_user'] ?? 40), 60))
        maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'درخواست‌ها زیاد است، کمی صبر کنید.'], 429);

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
            'uid'  => $uid,
            'admin'=> ($uid === ADMIN_ID) ? 1 : 0,
            'name' => trim((string)($user['first_name'] ?? '') . ' ' . (string)($user['last_name'] ?? '')),
            'uname'=> $uname,
            'photo'=> (string)($user['photo_url'] ?? ''),
            'orders' => array_map(fn($o) => [
                'id' => $o['id'], 'name' => $o['item_name'], 'emoji' => $o['item_emoji'],
                'total' => $o['total'], 'status' => MaOrder::statusLabel($o['status']),
                'date' => $o['created_at'],
            ], MaOrder::forUser($uid, 8, $key)),
        ]);
    }

    // ---- 👑 بخش مدیر: سود و قیمت — فقط برای ادمین ----
    if ($action === 'adm_get' || $action === 'adm_set') {
        // سد سخت: هرکس جز ادمین، انگار این مسیر اصلا وجود ندارد
        if ($uid !== ADMIN_ID) maApiOut(['ok' => false, 'error' => 'not_found'], 404);
        if (!function_exists('axPrice')) maApiOut(['ok' => false, 'error' => 'ext_missing'], 500);

        if ($action === 'adm_set') {
            if (!maRateOk('admset', $uid, 30, 60))
                maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی آرام‌تر.'], 429);
            $what = (string)($body['what'] ?? '');
            $id   = (string)($body['id'] ?? '');
            $val  = maNum($body['value'] ?? 0);
            if ($what === 'fixed') {
                if ($id === '' || axIsAutoPriced($id)) maApiOut(['ok' => false, 'error' => 'bad_item',
                    'message' => 'قیمت تون، ترون و تتر از صرافی می‌آید و دستی نمی‌شود.'], 400);
                if ($val < 0 || $val > 1e12) maApiOut(['ok' => false, 'error' => 'bad_value'], 400);
                axSetFixed($id, $val);
            } elseif ($what === 'margin') {
                if ($val < -90 || $val > 900) maApiOut(['ok' => false, 'error' => 'bad_value',
                    'message' => 'درصد سود باید بین ۹۰- تا ۹۰۰ باشد.'], 400);
                axSetMargin($id !== '' ? $id : '_all', $val);
            } else {
                maApiOut(['ok' => false, 'error' => 'bad_what'], 400);
            }
            axLog('adm_price', $what . ' ' . $id . ' = ' . $val);
        }

        $items = [];
        foreach ((array)($a['items'] ?? []) as $i) {
            if (!is_array($i) || empty($i['id'])) continue;
            $auto = axIsAutoPriced((string)$i['id'], (string)($i['cat'] ?? ''));
            $items[] = [
                'id'    => (string)$i['id'],
                'name'  => (string)($i['name'] ?? $i['id']),
                'emoji' => (string)($i['emoji'] ?? ''),
                'cat'   => (string)($i['cat'] ?? ''),
                'base'  => (float)($i['price'] ?? 0),
                'final' => (float)axPrice((string)$i['id'], (float)($i['price'] ?? 0), (string)($i['cat'] ?? '')),
                'fixed' => (float)((axCfg()['pricing']['fixed'][axSku((string)$i['id'])] ?? 0)),
                'auto'  => $auto,
            ];
        }
        maApiOut([
            'ok'     => true,
            'margin' => (float)(axVal('pricing.margin._all') ?? 0),
            'on'     => !empty(axVal('pricing.on')),
            'rates'  => ['usdt' => axRate('usdt'), 'ton' => axRate('ton'), 'trx' => axRate('trx')],
            'items'  => $items,
        ]);
    }

    // ---- 💳 شارژ کیف پول، کارت به کارت ----
    // مینی‌اپ فقط مبلغ را می‌گیرد؛ ساختن سفارش، متن کارت و دکمه «ارسال رسید»
    // همان مسیر آزموده‌ی ربات است تا دو جای جدا برای یک کار نداشته باشیم.
    if ($action === 'topup') {
        if (!maRateOk('top', $uid, 5, 300))
            maApiOut(['ok' => false, 'error' => 'rate_limited',
                      'message' => 'درخواست شارژ زیاد شد. چند دقیقه بعد دوباره.'], 429);

        $t = maTopupInfo();
        if (empty($t['on']) && empty($t['gw']))
            maApiOut(['ok' => false, 'error' => 'no_card',
                      'message' => 'روش پرداخت هنوز تنظیم نشده است. با پشتیبانی تماس بگیرید.'], 503);

        $amt = round(maNum($body['amount'] ?? 0));
        $min = max(1000.0, (float)$t['min']);
        if ($amt < $min)
            maApiOut(['ok' => false, 'error' => 'min',
                      'message' => 'حداقل مبلغ شارژ ' . fmtNum($min) . ' تومان است.'], 400);
        if ($amt > 500000000)
            maApiOut(['ok' => false, 'error' => 'max',
                      'message' => 'مبلغ خیلی بزرگ است.'], 400);

        $oid = createOrderAndAsk($uid, $uid, $uname, 'topup', null, $amt, 'تومان', '➕ شارژ کیف پول');
        if (!$oid)
            maApiOut(['ok' => false, 'error' => 'failed',
                      'message' => 'ثبت درخواست شارژ انجام نشد. با پشتیبانی تماس بگیرید.'], 500);

        maApiOut(['ok' => true, 'order' => $oid, 'amount' => $amt,
                  'card' => (string)$t['card'], 'holder' => (string)$t['name'],
                  'message' => 'درخواست شارژ ثبت شد. فاکتور و شماره کارت داخل ربات برایتان فرستاده شد؛ ' .
                               'بعد از واریز، دکمه «ارسال رسید» را بزنید.']);
    }

    // ---- 👑 مدیریت محصول‌ها، از داخل خودِ مینی‌اپ ----
    // برای هرکس جز مدیر، این مسیرها انگار اصلا وجود ندارند.
    if (str_starts_with($action, 'adm_item') || $action === 'adm_cats') {
        if ($uid !== ADMIN_ID) maApiOut(['ok' => false, 'error' => 'not_found'], 404);

        if ($action === 'adm_cats') {
            maApiOut(['ok' => true, 'cats' => array_map(fn($c) => [
                'id' => (string)$c['id'], 'name' => (string)$c['name'],
                'emoji' => (string)($c['emoji'] ?? ''), 'on' => !empty($c['on']) ? 1 : 0,
            ], (array)($a['cats'] ?? []))]);
        }

        if ($action === 'adm_items') {
            $out = [];
            foreach ((array)($a['items'] ?? []) as $i) {
                if (!is_array($i) || empty($i['id'])) continue;
                $out[] = [
                    'id' => (string)$i['id'], 'cat' => (string)($i['cat'] ?? ''),
                    'emoji' => (string)($i['emoji'] ?? ''), 'name' => (string)($i['name'] ?? ''),
                    'desc' => (string)($i['desc'] ?? ''), 'badge' => (string)($i['badge'] ?? ''),
                    'price' => (float)($i['price'] ?? 0), 'unit' => (string)($i['unit'] ?? ''),
                    'ask' => (string)($i['ask'] ?? 'none'),
                    'min' => (float)($i['min'] ?? 1), 'max' => (float)($i['max'] ?? 1),
                    'order' => (int)($i['order'] ?? 99), 'on' => !empty($i['on']) ? 1 : 0,
                    'live' => maIsLive($i) ? 1 : 0,
                    'final' => maItemPrice($i),
                ];
            }
            maApiOut(['ok' => true, 'items' => $out, 'asks' => maAskLabels()]);
        }

        if ($action === 'adm_item_del') {
            $id = (string)($body['id'] ?? '');
            if ($id === '') maApiOut(['ok' => false, 'error' => 'bad_id'], 400);
            $found = false;
            maSet($key, function (&$app) use ($id, &$found) {
                $out = [];
                foreach ((array)($app['items'] ?? []) as $i) {
                    if ((string)($i['id'] ?? '') === $id) { $found = true; continue; }
                    $out[] = $i;
                }
                $app['items'] = array_values($out);
            });
            if (!$found) maApiOut(['ok' => false, 'error' => 'not_found'], 404);
            axLogIf('miniapp_item_del', $key . ' ' . $id);
            maApiOut(['ok' => true]);
        }

        if ($action === 'adm_item_save') {
            $in = is_array($body['item'] ?? null) ? $body['item'] : [];

            $name = trim((string)($in['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80)
                maApiOut(['ok' => false, 'error' => 'bad_name', 'message' => 'نام باید بین ۱ تا ۸۰ نویسه باشد.'], 400);

            $ask = (string)($in['ask'] ?? 'none');
            if (!array_key_exists($ask, maAskLabels()))
                maApiOut(['ok' => false, 'error' => 'bad_ask', 'message' => 'نوع سوال معتبر نیست.'], 400);

            $price = maNum($in['price'] ?? 0);
            if ($price < 0 || $price > 1e12)
                maApiOut(['ok' => false, 'error' => 'bad_price', 'message' => 'قیمت معتبر نیست.'], 400);

            $min = maNum($in['min'] ?? 1);
            $max = maNum($in['max'] ?? 1);
            if ($min < 0 || $max < 0 || ($max > 0 && $max < $min))
                maApiOut(['ok' => false, 'error' => 'bad_range', 'message' => 'حداقل نباید از حداکثر بیشتر باشد.'], 400);

            $cat = (string)($in['cat'] ?? '');
            $catOk = $cat === '';
            foreach ((array)($a['cats'] ?? []) as $c) if ((string)$c['id'] === $cat) $catOk = true;
            if (!$catOk) maApiOut(['ok' => false, 'error' => 'bad_cat', 'message' => 'دسته پیدا نشد.'], 400);

            $id = trim((string)($in['id'] ?? ''));
            $isNew = ($id === '');
            if ($isNew) {
                $id = 'x_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(2));
            } elseif (!preg_match('/^[A-Za-z0-9_]{2,40}$/', $id)) {
                maApiOut(['ok' => false, 'error' => 'bad_id'], 400);
            }

            $row = [
                'id'    => $id,
                'cat'   => $cat,
                'emoji' => mb_substr(trim((string)($in['emoji'] ?? '')), 0, 8),
                'name'  => $name,
                'desc'  => mb_substr(trim((string)($in['desc'] ?? '')), 0, 300),
                'badge' => mb_substr(trim((string)($in['badge'] ?? '')), 0, 20),
                'price' => $price,
                'unit'  => mb_substr(trim((string)($in['unit'] ?? '')), 0, 20),
                'ask'   => $ask,
                'min'   => $min,
                'max'   => $max,
                'order' => max(0, min(999, (int)maNum($in['order'] ?? 99))),
                'on'    => !empty($in['on']),
            ];

            maSet($key, function (&$app) use ($row, $isNew) {
                $items = (array)($app['items'] ?? []);
                if (!$isNew) {
                    foreach ($items as $k2 => $i) {
                        if ((string)($i['id'] ?? '') !== $row['id']) continue;
                        // فیلدهای فنی (auto، stars، rate_key…) دست‌نخورده می‌مانند —
                        // این صفحه فقط ظاهر و قیمت را عوض می‌کند.
                        $items[$k2] = array_replace($i, $row);
                        $app['items'] = array_values($items);
                        return;
                    }
                }
                $items[] = $row;
                $app['items'] = array_values($items);
            });
            axLogIf('miniapp_item_save', $key . ' ' . $id . ' ' . $name);
            maApiOut(['ok' => true, 'id' => $id, 'created' => $isNew]);
        }
    }

    // ---- ثبت سفارش ----
    if ($action === 'order') {
        // 🛡 سقف تعداد سفارش در دقیقه
        if (!maRateOk('ord', $uid, 6, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited',
                      'message' => 'تعداد سفارش‌های پشت‌سرهم زیاد است. یک دقیقه صبر کنید.'], 429);

        // 🛡 هر initData فقط یک سفارش — جلوی بازپخش (replay) را می‌گیرد
        if (!maNonceOk($initData))
            maApiOut(['ok' => false, 'error' => 'replay',
                      'message' => 'سقف سفارش این نشست پر شد. مینی‌اپ را ببندید و دوباره باز کنید.'], 409);

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
        // «qty» تعداد صحیح می‌خواهد (۵۰ استارز)، ولی «qty_wallet» برای ارز است
        // و مقدار اعشاری هم می‌گیرد (۲٫۵ تون) — چون کسی مجبور نیست عدد رند بخرد.
        if ($ask === 'qty' || $ask === 'qty_wallet' || $ask === 'qty_username') {
            $frac = ($ask === 'qty_wallet');
            $qty = maNum($body['qty'] ?? 0);
            if (!is_finite($qty) || $qty < 0) $qty = 0;
            $qty = $frac ? round($qty, 4) : floor($qty);
            $min = (float)($item['min'] ?? 1);
            $max = (float)($item['max'] ?? 0);
            if ($qty <= 0) maApiOut(['ok' => false, 'error' => 'bad_qty', 'message' => 'مقدار را درست وارد کنید.'], 400);
            if ($min > 0 && $qty < $min) maApiOut(['ok' => false, 'error' => 'min', 'message' => 'حداقل مقدار ' . fmtNum($min) . ' است.'], 400);
            if ($max > 0 && $qty > $max) maApiOut(['ok' => false, 'error' => 'max', 'message' => 'حداکثر مقدار ' . fmtNum($max) . ' است.'], 400);
        }

        // 📦 حجم دلخواه — فقط عددهای رند: ۵۰۰ مگ، یا گیگ کامل
        $volMb = 0;
        if ($ask === 'volume') {
            $volMb = (int)maNum($body['volume'] ?? 0);
            if (!function_exists('axVolumeOk') || !axVolumeOk($volMb)) {
                maApiOut(['ok' => false, 'error' => 'bad_volume',
                          'message' => 'حجم باید رند باشد: ۵۰۰ مگابایت، یا گیگابایت کامل (۱، ۲، ۳ …).'], 400);
            }
            $max = (int)($item['max'] ?? 0);
            if ($max > 0 && $volMb > $max)
                maApiOut(['ok' => false, 'error' => 'max',
                          'message' => 'حداکثر حجم ' . axVolumeLabel($max) . ' است.'], 400);
            $min = (int)($item['min'] ?? 0);
            if ($min > 0 && $volMb < $min)
                maApiOut(['ok' => false, 'error' => 'min',
                          'message' => 'حداقل حجم ' . axVolumeLabel($min) . ' است.'], 400);

            // مخزن جدا برای هر حجم — «۱ گیگ» و «۳ گیگ» موجودی خودشان را دارند
            $volSku = $itemId . '_' . $volMb;
            if (function_exists('axStockCount') && axStockCount($volSku) < 1) {
                maApiOut(['ok' => false, 'error' => 'out_of_stock',
                          'message' => axVolumeLabel($volMb) . ' الان در مخزن موجود نیست. حجم دیگری را امتحان کنید.'], 409);
            }
            $qty = 1.0;
        }

        $field = trim((string)($body['field'] ?? ''));
        if (in_array($ask, ['username', 'wallet', 'qty_wallet', 'qty_username', 'text'], true) && $field === '') {
            maApiOut(['ok' => false, 'error' => 'need_field', 'message' => 'لطفا فیلد خواسته‌شده را پر کنید.'], 400);
        }
        if ($ask === 'username' || $ask === 'qty_username') {
            $field = ltrim($field, '@');
            if (!preg_match('/^[A-Za-z0-9_]{4,64}$/', $field))
                maApiOut(['ok' => false, 'error' => 'bad_username', 'message' => 'آیدی تلگرام معتبر نیست.'], 400);
            $field = '@' . $field;
        }
        if (($ask === 'wallet' || $ask === 'qty_wallet') && mb_strlen($field) < 8) {
            maApiOut(['ok' => false, 'error' => 'bad_wallet', 'message' => 'آدرس ولت معتبر نیست.'], 400);
        }
        if (mb_strlen($field) > 300) $field = mb_substr($field, 0, 300);

        $item['currency'] = (string)($a['currency'] ?? 'تومان');

        // 🔒 قیمت همیشه اینجا و از نو حساب می‌شود — هرچه کاربر بفرستد نادیده گرفته می‌شود
        $unitPrice = maItemPrice($item);

        if ($ask === 'volume') {
            // قیمت پایه به‌ازای هر گیگابایت است
            $unitPrice = round($unitPrice * ($volMb / 1024), 0);
            $item['unit'] = '';
            $item['name'] = $item['name'] . ' — ' . axVolumeLabel($volMb);
            $item['id']   = $itemId . '_' . $volMb;      // مخزن همین حجم
            $field = $field !== '' ? $field : axVolumeLabel($volMb);
        }

        $item['price'] = $unitPrice;
        $total = maMoney($unitPrice * (in_array($ask, ['qty', 'qty_wallet', 'qty_username'], true) ? $qty : 1));

        // 🛑 نرخ زنده نیامده؟ نفروش. قیمت قدیمی یعنی ضرر.
        if (maPriceStale($item)) {
            maApiOut(['ok' => false, 'error' => 'rate_down',
                      'message' => 'نرخ لحظه‌ای این ارز الان در دسترس نیست، برای همین موقتا فروش آن بسته است. ' .
                                   'چند دقیقه دیگر دوباره امتحان کنید.'], 503);
        }

        if ($total <= 0 && empty(cfg()['test_mode']))
            maApiOut(['ok' => false, 'error' => 'bad_price', 'message' => 'قیمت این سرویس تنظیم نشده است.'], 400);

        // اگر بین باز شدن مینی‌اپ و زدن دکمه، نرخ زنده عوض شده باشد،
        // به‌جای فاکتور کردن قیمت قدیمی، از کاربر می‌خواهیم صفحه را تازه کند.
        $seen = (float)($body['seen_price'] ?? 0);
        if ($seen > 0 && abs($seen - $unitPrice) > max(1.0, $unitPrice * 0.005)) {
            maApiOut(['ok' => false, 'error' => 'price_changed', 'price' => $unitPrice,
                      'message' => 'قیمت این سرویس به‌روز شد. لطفا دوباره تلاش کنید.'], 409);
        }

        // 🛡 همان سفارش دوبار پشت سر هم (دابل‌کلیک یا اسکریپت)
        if (maDuplicateOrder($uid, $key, $itemId, $qty, $field))
            maApiOut(['ok' => false, 'error' => 'duplicate',
                      'message' => 'همین سفارش چند لحظه پیش ثبت شد — فاکتورش داخل ربات است.'], 409);

        $oid = MaOrder::create($key, $uid, $uname, $item, $qty, $total, $field);

        // 👛 پرداخت همیشه از کیف پول و همیشه داخل خود مینی‌اپ.
        //
        // قبلا فاکتور داخل ربات فرستاده می‌شد و کاربر باید از مینی‌اپ
        // بیرون می‌آمد. حالا خرید همان‌جا تمام می‌شود: پول کم می‌شود،
        // رسید همان‌جا نشان داده می‌شود، و هیچ پیامی به ربات نمی‌رود.
        [$paid, $perr] = maPayFromWallet($oid, $uid);

        if (!$paid) {
            // موجودی کم بود — سفارشِ نیمه‌کاره را نگه نمی‌داریم
            $need = max(0.0, (float)$total - (float)(getUser($uid)['balance'] ?? 0));
            MaOrder::remove($oid);
            maApiOut([
                'ok' => false, 'error' => 'no_balance',
                'balance' => (float)(getUser($uid)['balance'] ?? 0),
                'need'    => $need,
                'total'   => (float)$total,
                'message' => trim($perr) !== ''
                    ? $perr
                    : 'موجودی کافی نیست. ' . maMoney($need) . ' تومان کم دارید.',
            ], 402);
        }

        $o   = MaOrder::get($oid);
        $bal = (float)(getUser($uid)['balance'] ?? 0);
        maApiOut([
            'ok' => true, 'order' => $oid, 'total' => $total, 'paid' => true,
            'balance' => $bal,
            'done' => ($o['status'] === MaOrder::DONE),
            'message' => ($o['status'] === MaOrder::DONE)
                ? maUT($key, 'done_sub')
                : 'پرداخت انجام شد. سفارش در حال پردازش است.',
        ]);
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
        return ['username' => '📎 آیدی', 'wallet' => '💼 ولت', 'qty_wallet' => '💼 ولت',
                'qty_username' => '📎 آیدی', 'text' => '📝 توضیح'][$i['ask'] ?? ''] ?? '';
    }
    return '';
}

function maInvoiceText($o) {
    $a = maGet($o['app']);
    $bal = (float)(getUser($o['user_id'])['balance'] ?? 0);

    // 🧾 متن سفارشی ادمین — با ایموجی پریمیوم و نقل‌قول، عینا مثل ربات مادر
    if (function_exists('axVal') && !empty(axVal('texts.invoice_on'))) {
        $tpl = (string)axVal('texts.invoice');
        if (trim($tpl) !== '') {
            return axFill($tpl, $o, [
                '{unit_price}' => fmtNum($o['unit_price']),
                '{balance}'    => fmtNum($bal),
                '{title}'      => h($a['title']),
                '{note}'       => h((string)($a['note'] ?? '')),
            ]);
        }
    }

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
    $bal   = (float)(getUser($o['user_id'])['balance'] ?? 0);
    $items = [];
    if ($bal >= (float)$o['total']) $items[] = maGlassBtn($o['app'], 'wallet', 'mapay_' . $o['id']);
    $items[] = maGlassBtn($o['app'], 'card',   'macard_' . $o['id']);
    $items[] = maGlassBtn($o['app'], 'cancel', 'macan_'  . $o['id']);

    // چیدمان دلخواه ادمین — «2,1» یعنی دو دکمه بالا، یکی پایین
    $layout = trim((string)(maGet($o['app'])['glass_layout'] ?? ''));
    return inlineKb($layout !== '' ? layoutRows($items, $layout) : array_map(fn($b) => [$b], $items));
}

/** ادمین را از سفارش تازه خبر می‌کند */
/**
 * 📮 خبر دادن به مشتری — همیشه روی یک پیام.
 *
 * قبلا هر مرحله یک پیام تازه می‌فرستاد: «پرداخت تایید شد»، بعد
 * «سفارش انجام شد»… و چت شلوغ می‌شد. حالا هر سفارش یک پیام دارد که
 * همان‌جا به‌روز می‌شود، پس همیشه فقط آخرین وضعیت دیده می‌شود.
 */
function maTellUser($o, $text, $markup = null) {
    $id  = (string)($o['id'] ?? '');
    $uid = (int)($o['user_id'] ?? 0);
    if (!$uid) return;

    $mid = (int)($o['msg_id'] ?? 0);
    if ($mid) {
        $d = ['chat_id' => $uid, 'message_id' => $mid, 'text' => $text,
              'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true'];
        if ($markup) $d['reply_markup'] = json_encode($markup);
        $r = tg(BOT_TOKEN, 'editMessageText', $d);
        if (!empty($r['ok'])) return;
        // «تغییری نکرده» یعنی همان متن سرِ جایش هست — کاری لازم نیست
        if (str_contains(strtolower((string)($r['description'] ?? '')), 'not modified')) return;
        // پیام پاک شده یا خیلی کهنه است → تازه بفرست
    }
    $r = sendMsg(BOT_TOKEN, $uid, $text, $markup);
    $new = (int)($r['result']['message_id'] ?? 0);
    if ($new && $id !== '') MaOrder::set($id, function (&$x) use ($new) { $x['msg_id'] = $new; });
}

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
    maTellUser($o,
        "✅ <b>پرداخت شما تایید شد</b>\n\n" .
        '📦 ' . h(maOrderTitle($o)) . "\n" .
        '💰 ' . fmtNum($o['total']) . ' ' . h($o['currency']) . "\n" .
        '🔑 <code>' . h($o['id']) . "</code>\n\n" .
        (trim((string)$a['note']) !== '' ? '💡 ' . h($a['note']) : '⏳ سفارش شما در حال انجام است.'));

    // 📊 گزارش این مینی‌اپ در کانال مدیر
    if (function_exists('axReportOrder')) axReportOrder($o, 'paid');

    // 📡 و روی کانالِ «گزارش خرید»، اگر تنظیم شده باشد
    if (function_exists('chBuy')) {
        // دسته‌ی سرویس را هم می‌دهیم تا گزارش در تاپیکِ همان دسته بیفتد
        $it  = maFindItem($o['app'], $o['item_id']);
        $cat = (string)($it['cat'] ?? '');
        chBuy($o['user_id'], $o['username'] ?? '', maOrderTitle($o),
              (float)($o['qty'] ?? 1), (float)$o['total'], $o['id'], [], (string)$o['app'], $cat);
    }

    // 🚚 زنجیره‌ی تحویل: مخزن → دستی → پنل خودکار → دست ادمین
    return maDeliver($o);
}

/**
 * تصمیم می‌گیرد این سفارش چطور تحویل شود.
 * ترتیب عمدی است: مخزن سریع‌ترین است، دستی صریح‌ترین،
 * و پنل خودکار آخرین گزینه‌ی ماشینی. هیچ‌کدام دو بار اجرا نمی‌شود.
 */
function maDeliver($o) {
    $id = $o['id'];

    // 1️⃣ مخزن کانفیگ — اگر برای این محصول موجودی گذاشته‌ایم
    if (function_exists('axStockCount') && axStockCount($o['item_id']) > 0
        && !empty(axVal('stock.on'))) {
        $claimed = MaOrder::set($id, function (&$x) {
            if (!empty($x['sending']) || ($x['status'] ?? '') === MaOrder::DONE) return false;
            $x['sending'] = time();
            return true;
        });
        if ($claimed) {
            [$ok, $err] = axStockDeliver($o);
            MaOrder::set($id, function (&$x) use ($ok, $err) {
                $x['sending'] = 0;
                if ($ok) {
                    $x['status'] = MaOrder::DONE;
                    $x['delivered_at'] = nowStr();
                    $x['delivered_by'] = 'stock';
                } else {
                    $x['last_error'] = $err;
                }
            });
            if ($ok) {
                $o = MaOrder::get($id);
                if (function_exists('axReportOrder')) axReportOrder($o, 'done');
                return $o;
            }
        }
    }

    // 2️⃣ سفارش دستی — گیفت‌هایی مثل تدی، یا خرید تون
    if (function_exists('axIsManual') && axIsManual($o['item_id'])) {
        [$ok, $err] = axManualPost($o);
        if (!$ok) maNotifyAdmin($o, '🎁 <b>سفارش دستی — فرم به کانال نرفت</b>' .
                                    ($err !== '' ? "\n<code>" . h($err) . '</code>' : ''));
        return MaOrder::get($id);
    }

    // 3️⃣ پنل فروش خودکار
    [$op] = maAutoOp($o);
    $f = maCfg()['fulfill'] ?? [];
    if ($op && !empty($f['on']) && !empty($f['auto_pay'])) {
        maAutoFulfill($id);                     // موفق یا ناموفق، ادمین خبردار می‌شود
        return MaOrder::get($id);
    }

    // 4️⃣ هیچ‌کدام — دست ادمین
    //
    // ولی نه بی‌توضیح: تا حالا فقط «آماده تحویل» می‌آمد و معلوم نبود
    // چرا خودکار نشد. حالا دقیقا همان‌جا می‌گوید کدام تکه کم است.
    $why = maAutoWhy($o);
    maNotifyAdmin($o, '💸 <b>سفارش پرداخت‌شده — آماده تحویل</b>' .
        ($why !== '' ? "\n\n⚠️ <b>چرا خودکار نشد:</b> " . h($why) : ''));
    return $o;
}

/**
 * چرا این سفارش خودکار تحویل نشد؟ رشته‌ی خالی یعنی باید می‌شد.
 *
 * زنجیره‌ی تحویل چهار حلقه دارد و اگر یکی‌شان نباشد کار می‌افتد دست
 * ادمین. تا حالا معلوم نبود کدام حلقه؛ حالا هست.
 */
function maAutoWhy($o) {
    $f = maCfg()['fulfill'] ?? [];
    [$op] = maAutoOp($o);

    if (!$op) {
        $i = maFindItem($o['app'], $o['item_id']);
        return $i
            ? 'این محصول «عملیات خودکار» ندارد. پنل ← 🚀 مینی‌اپ‌ها ← محصول ← عملیات خودکار.'
            : 'محصول پیدا نشد.';
    }
    if (empty($f['on']))
        return 'پنل فروش خاموش است. پنل ← 🚀 مینی‌اپ‌ها ← 🤖 تحویل خودکار.';
    if (trim((string)($f['base'] ?? '')) === '')
        return 'آدرس پنل فروش خالی است.';
    if (($f['auth_type'] ?? '') !== 'none' && trim((string)($f['auth_value'] ?? '')) === '')
        return 'کلید API پنل فروش خالی است.';
    if (trim((string)($f['ops'][$op]['path'] ?? '')) === '')
        return 'مسیر «' . h($op) . '» در پنل فروش تنظیم نشده است.';
    if (empty($f['auto_pay']))
        return 'تحویلِ بلافاصله بعد از پرداخت خاموش است.';
    return '';
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

        [$ok, $err] = maPayFromWallet($id, $uid);
        if (!$ok) { answerCb(BOT_TOKEN, $cbId, '❌ ' . $err, true); return true; }
        answerCb(BOT_TOKEN, $cbId, '✅ پرداخت شد');
        if ($msgId) delMsg(BOT_TOKEN, $chatId, $msgId);
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
        maTellUser($o,
            "❌ <b>رسید شما تایید نشد</b>\n\n" .
            '📦 ' . h(maOrderTitle($o)) . "\n" .
            '🔑 <code>' . h($o['id']) . "</code>\n\n" .
            'در صورت اشتباه، با پشتیبانی تماس بگیرید.');
        return true;
    }

    // ---------- 🔁 تلاش دوباره تحویل خودکار ----------
    if (str_starts_with($data, 'maretry_')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
        $id = substr($data, 8);
        answerCb(BOT_TOKEN, $cbId, '⏳ در حال تلاش…');
        [$ok, $msg] = maAutoFulfill($id, true);
        if (!$ok) sendMsg(BOT_TOKEN, $chatId, "❌ باز هم نشد:\n<code>" . h($msg) . '</code>',
            inlineKb([[btnCb('📤 تحویل دستی', 'madlv_' . $id, 'link')],
                      [btnCb('💰 برگشت پول', 'marefund_' . $id, 'reject')]]));
        return true;
    }

    // ---------- 💰 برگشت پول به کاربر ----------
    if (str_starts_with($data, 'marefund_')) {
        if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒', true); return true; }
        $id = substr($data, 9);
        $o  = MaOrder::get($id);
        if (!$o) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return true; }
        if (!empty($o['refunded'])) { answerCb(BOT_TOKEN, $cbId, 'قبلا برگشت خورده.', true); return true; }
        if ($o['status'] === MaOrder::DONE) { answerCb(BOT_TOKEN, $cbId, 'این سفارش تحویل شده.', true); return true; }

        MaOrder::set($id, function (&$x) { $x['refunded'] = true; $x['status'] = MaOrder::REJECT; });
        maRefund($o['user_id'], (float)$o['total'], 'سفارش ' . maOrderTitle($o) . ' انجام نشد.');
        answerCb(BOT_TOKEN, $cbId, '✅ برگشت خورد');
        sendMsg(BOT_TOKEN, $chatId, '💰 مبلغ <b>' . fmtNum($o['total']) . '</b> تومان به کیف پول کاربر برگشت.');
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

/**
 * 🩺 تشخیص خودکارسازی.
 *
 * «چرا سفارش خودکار انجام نشد» جوابِ کوتاه ندارد: چهار حلقه باید با هم
 * جور باشند. این صفحه هر حلقه را جدا می‌سنجد و بعد تک‌تک محصول‌ها را
 * می‌گوید کدام خودکار می‌شود و کدام دستِ ادمین می‌ماند.
 */
function maAdmAutoDiag($chatId) {
    $f = maCfg()['fulfill'] ?? [];
    $t  = "🩺 <b>چرا سفارش‌ها خودکار انجام نمی‌شوند؟</b>\n\n";

    $on   = !empty($f['on']);
    $base = trim((string)($f['base'] ?? ''));
    $key  = trim((string)($f['auth_value'] ?? ''));
    $needKey = ($f['auth_type'] ?? '') !== 'none';

    $t .= "<b>پنل فروش</b>\n";
    $t .= ($on ? '✅' : '🔴') . ' روشن بودن' . ($on ? '' : ' — خاموش است') . "\n";
    $t .= ($base !== '' ? '✅' : '🔴') . ' آدرس: ' . ($base !== '' ? '<code>' . h($base) . '</code>' : 'خالی') . "\n";
    $t .= (!$needKey || $key !== '' ? '✅' : '🔴') . ' کلید API' .
          ($needKey && $key === '' ? ' — خالی' : '') . "\n";
    $t .= (!empty($f['auto_pay']) ? '✅' : '🔴') . " تحویل بلافاصله بعد از پرداخت\n";

    $t .= "\n<b>مسیرها</b>\n";
    foreach (['stars' => '⭐️ استارز', 'premium' => '💎 پریمیوم', 'gift' => '🎁 گیفت'] as $k => $lbl) {
        $pth = trim((string)($f['ops'][$k]['path'] ?? ''));
        $t .= ($pth !== '' ? '✅' : '⚪️') . ' ' . $lbl . ': ' .
              ($pth !== '' ? '<code>' . h($pth) . '</code>' : 'تنظیم نشده') . "\n";
    }

    // اتصال واقعی، همین حالا
    if ($on && $base !== '') {
        [$resp, $err] = maFulfillCall('balance');
        $t .= "\n<b>اتصال</b>\n" . (is_array($resp)
            ? '✅ برقرار — پاسخ پنل: <code>' .
              h(mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE), 0, 120)) . "</code>\n"
            : '🔴 <code>' . h(mb_substr((string)$err, 0, 140)) . "</code>\n");
    }

    // ⚠️ نرخ دستیِ کمتر از نرخ واقعی = ضررِ هر سفارش
    if (!empty(maCfg()['stars']['on']) && function_exists('pxStars')) {
        $manual = (float)(maCfg()['stars']['price'] ?? 0);
        $live   = pxStars(1);
        if ($manual > 0 && $live && $live['irt'] > 0 && $manual < $live['irt'] * 0.95) {
            $t .= "\n🔴 <b>هشدار ضرر</b>\n";
            $t .= 'نرخ دستی استارز <b>' . fmtNum($manual) . '</b> تومان است ولی نرخ واقعی <b>' .
                  fmtNum($live['irt']) . "</b> تومان.\n";
            $t .= "هر استارز که با نرخ دستی فروخته شود یعنی ضرر. یا نرخ را درست کنید،\n" .
                  "یا «نرخ دستی استارز» را خاموش کنید تا فقط با نرخ زنده بفروشد.\n";
        }
    }

    $t .= "\n<b>محصول‌ها</b>\n";
    $auto = 0; $manual = 0;
    foreach (maKeys() as $appKey) {
        foreach ((array)(maGet($appKey)['items'] ?? []) as $i) {
            if (empty($i['on'])) continue;
            $fake = ['app' => $appKey, 'item_id' => $i['id'], 'qty' => 1,
                     'field' => '@x', 'user_id' => 0, 'id' => 'diag'];
            $why = maAutoWhy($fake);
            if ($why === '') { $auto++; continue; }
            $manual++;
            if ($manual <= 12)
                $t .= '⚠️ ' . h(mb_substr((string)$i['name'], 0, 24)) . ' — ' . h(mb_substr($why, 0, 70)) . "\n";
        }
    }
    $t .= "\n✅ خودکار: <b>{$auto}</b> · ⚠️ دستی: <b>{$manual}</b>";
    if ($manual > 12) $t .= "\n(بقیه هم همین‌طور)";

    sendMsg(BOT_TOKEN, $chatId, mb_substr($t, 0, 4000),
        inlineKb([[btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'admin')],
                  [btnCb(UT('back'), 'maadm_home', 'nav')]]));
}

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

    $text .= "\n📐 چیدمان دکمه‌ها زیر محصولات: <code>" . h(maCfg()['row_layout'] ?: '۱ در هر ردیف') . "</code>\n";
    $text .= "🔗 آدرس عمومی: " . ($base !== '' ? '<code>' . h($base) . '</code>' : '<b>ثبت نشده</b>') . "\n";
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
        [btnCb('🔗 آدرس عمومی', 'maadm_base', 'admin'),
         btnCb('📐 چیدمان دکمه‌ها', 'maadm_rowlay', 'admin')],
        [btnCb('🔌 قیمت‌گذاری زنده', 'maadm_pricing', 'confirm')],
        [btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'confirm'),
         btnCb('🩺 چرا خودکار نیست؟', 'maadm_autodiag', 'confirm')],
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
    $text .= 'ترتیب: ' . (int)($b['order'] ?? 1) . "\n";
    $text .= 'شماره ردیف: ' . ((int)($b['row'] ?? 0) > 0 ? (int)$b['row'] : 'خودکار') . "\n\n";
    $text .= maMergeOn()
        ? "🔗 <b>این دکمه با لیست «ثبت سفارش» ادغام شده.</b>\n" .
          "یعنی با همین «ترتیب»، جایش را بین ممبر اخلاقی و فیک و … عوض می‌کنید، " .
          "و چیدمانش هم همان چیدمان همان لیست است.\n\n"
        : "این دکمه جدا از لیست ثبت سفارش، زیر همه نمایش داده می‌شود.\n\n";
    $text .= '💡 رنگ دکمه با Bot API 9.4 روی خود دکمه اعمال می‌شود.';

    $rows = [
        [btnCb('✏️ متن', 'maadm_bt_' . $key, 'admin'), btnCb('😀 ایموجی', 'maadm_be_' . $key, 'admin')],
        [btnCb('🎨 رنگ: ' . (styleMap()[$b['color'] ?? 'none'] ?? ''), 'maadm_bc_' . $key, 'admin')],
        [btnCb('✨ ایموجی پریمیوم', 'maadm_bi_' . $key, 'admin'), btnCb('🔢 ترتیب', 'maadm_bo_' . $key, 'admin')],
        [btnCb('📍 شماره ردیف', 'maadm_br_' . $key, 'admin')],
    ];
    // اگر ادغام روشن است، راه برگشت طبیعی همان صفحه‌ی چیدمان است
    if (maMergeOn()) $rows[] = [btnCb('📐 چیدمان دکمه‌های ثبت سفارش', 'sbs_buy', 'nav')];
    $rows[] = [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')];
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
    $text .= '🌫 بافت دانه‌ای: ' . (!empty($th['grain']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= '⚡️ سطح افکت: <b>' . maFxLabel(maFxLevel($th)) . "</b>\n\n";
    $text .= "💡 اگر مینی‌اپ روی گوشی‌های ضعیف کند است، سطح افکت را «سبک» یا «خاموش» کنید — " .
             "سنگین‌ترین جلوه‌ها (بلور شیشه‌ای و انیمیشن پس‌زمینه) کنار می‌روند و بقیه ظاهر می‌ماند.\n\n";
    $text .= 'رنگ را به شکل <code>#RRGGBB</code> بفرستید.';

    $rows = [
        [btnCb('🎨 رنگ اصلی', 'maadm_c1_' . $key, 'admin'), btnCb('🎨 رنگ دوم', 'maadm_c2_' . $key, 'admin')],
        [btnCb('🎨 رنگ تاکید', 'maadm_c3_' . $key, 'admin'), btnCb('🖼 پس‌زمینه', 'maadm_bg_' . $key, 'admin')],
        [btnCb(!empty($th['glow']) ? '✨ درخشش: روشن' : '✨ درخشش: خاموش', 'maadm_glow_' . $key, 'info'),
         btnCb(!empty($th['grain']) ? '🌫 بافت: روشن' : '🌫 بافت: خاموش', 'maadm_grain_' . $key, 'info')],
        [btnCb('⚡️ سطح افکت: ' . maFxLabel(maFxLevel($th)), 'maadm_fx_' . $key, 'confirm')],
        [btnCb('🎭 پالت‌های آماده', 'maadm_pal_' . $key, 'confirm')],
        [btnCb(UT('back'), 'maadm_app_' . $key, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function maFxLabel($n) {
    return [0 => '❌ خاموش (سریع‌ترین)', 1 => '🔸 سبک', 2 => '✨ کامل'][(int)$n] ?? '—';
}

function maPresetLabel($p) {
    return ['aurora' => '🌌 شفق قطبی', 'cyber' => '🟢 سایبر گرید'][$p] ?? ($p ?: '—');
}

/** 🎭 پالت‌های آماده */
function maPalettes() {
    return [
        'aurora'  => ['name' => '💜 بنفش پریمیوم', 'c1' => '#8B5CF6', 'c2' => '#6366F1', 'c3' => '#22D3EE', 'bg' => '#08090D'],
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
        'pay_wallet' => '👛 دکمه پرداخت از کیف پول',
        'pay_other'  => '💳 دکمه روش دیگر پرداخت',
        'low_bal'    => '⚠️ متن موجودی کم',
        'paid_ok'    => '✅ متن پرداخت موفق',
        'topup_hint' => '💡 راهنمای شارژ',
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

    $text .= "\n📐 چیدمان فاکتور: <code>" . h($a['glass_layout'] ?: '۱ در هر ردیف') . "</code>\n";
    $text .= "\n💡 «✏️» متن و ایموجی · «🎨» رنگ · «✨» ایموجی پریمیوم";

    $rows = [];
    foreach (maGlassLabels() as $slug => $lbl) {
        $g = $a['glass'][$slug] ?? [];
        $rows[] = [
            btnCb('✏️ ' . $lbl, 'maadm_gt_' . $key . '|' . $slug, 'admin'),
            btnCb(styleMap()[$g['color'] ?? 'none'] ?? '🎨', 'maadm_gc_' . $key . '|' . $slug, 'info'),
            btnCb('✨', 'maadm_gi_' . $key . '|' . $slug, 'admin'),
        ];
    }
    $rows[] = [btnCb('📐 چیدمان دکمه‌های فاکتور', 'maadm_glay_' . $key, 'confirm')];
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

    $live = maLivePrice($i);

    $text  = "🛒 <b>" . h(trim(($i['emoji'] ?? '') . ' ' . $i['name'])) . "</b>\n\n";
    $text .= '💰 قیمت دستی: <b>' . fmtNum($i['price']) . ' ' . h($a['currency'] ?? 'تومان') . "</b>\n";
    if ($live !== null) {
        $text .= '🔌 قیمت زنده: <b>' . fmtNum($live) . ' ' . h($a['currency'] ?? 'تومان') . "</b> ← همین فروخته می‌شود\n";
    }
    $autoOp = trim((string)($i['auto'] ?? ''));
    $text .= '🤖 تحویل: <b>' . h(maAutoLabels()[$autoOp] ?? 'دستی') . "</b>\n";
    if ($autoOp !== '') {
        $text .= '   مقدار ارسالی به پنل: <b>' . fmtNum($i['auto_qty'] ?? 0) . "</b>" .
                 ((float)($i['auto_qty'] ?? 0) <= 0 ? ' (از تعداد سفارش)' : '') . "\n";
        if (trim((string)($i['auto_id'] ?? '')) !== '')
            $text .= '   شناسه در پنل: <code>' . h($i['auto_id']) . "</code>\n";
    }
    if (trim((string)($i['market_key'] ?? '')) !== '') $text .= '🔗 کلید مارکت: <code>' . h($i['market_key']) . "</code>\n";
    if ((float)($i['stars'] ?? 0) > 0)                 $text .= '⭐️ ارزش استارز: <b>' . fmtNum($i['stars']) . "</b>\n";
    $text .= '📂 دسته: ' . h($cat ? trim(($cat['emoji'] ?? '') . ' ' . $cat['name']) : '—') . "\n";
    $text .= '📝 توضیح: ' . h($i['desc'] ?: '—') . "\n";
    $text .= '🏷 برچسب: ' . h($i['badge'] ?: '—') . "\n";
    $text .= '❓ سوال از کاربر: ' . h(maAskLabels()[$i['ask'] ?? 'none'] ?? '—') . "\n";
    if (in_array($i['ask'] ?? '', ['qty', 'qty_wallet', 'qty_username'], true)) {
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
        [btnCb('🔗 کلید مارکت', 'maadm_imk_' . $p, 'admin'), btnCb('⭐️ ارزش استارز', 'maadm_ist_' . $p, 'admin')],
        [btnCb('🤖 تحویل خودکار', 'maadm_iau_' . $p, 'confirm')],
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

/** 🔌 صفحه اصلی قیمت‌گذاری زنده */
function maAdmPricing($chatId, $msgId) {
    $c  = maCfg();
    $mk = $c['market'] ?? []; $rt = $c['rates'] ?? []; $st = $c['stars'] ?? [];

    $text  = "🔌 <b>قیمت‌گذاری زنده</b>\n\n";
    $text .= "قیمت‌ها به‌جای عدد ثابت، از منبع زنده گرفته و با درصد سود شما حساب می‌شوند.\n\n";

    $text .= "🎁 <b>مارکت گیفت</b>: " . (!empty($mk['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    if (trim((string)($mk['url'] ?? '')) !== '') {
        $text .= "   آدرس: <code>" . h(mb_substr((string)$mk['url'], 0, 48)) . "</code>\n";
        $map = maMarketMap();
        $text .= "   گیفت‌های خوانده‌شده: <b>" . count($map) . "</b>\n";
    } else {
        $text .= "   ⚠️ آدرس API ثبت نشده\n";
    }
    $text .= "   سود: " . (float)($mk['margin'] ?? 0) . "% · ارز مبدا: " . h($mk['price_cur'] ?? '—') . "\n\n";

    $text .= "💱 <b>نرخ ارز</b>: " . (!empty($rt['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    if (!empty($rt['on'])) {
        foreach (['ton' => 'TON', 'trx' => 'TRX', 'usdt' => 'USDT'] as $k => $lbl) {
            $v = maRate($k);
            $text .= "   {$lbl}: " . ($v > 0 ? '<b>' . fmtNum($v) . '</b> تومان' : '—') . "\n";
        }
        $text .= "   سود: " . (float)($rt['margin'] ?? 0) . "%\n";
    }
    $text .= "\n⭐️ <b>نرخ استارز</b>: " . (!empty($st['on']) ? '✅ روشن' : '❌ خاموش') .
             " · هر استارز: <b>" . fmtNum($st['price'] ?? 0) . "</b> تومان\n\n";
    $text .= "💡 هر سرویسی که منبع زنده نداشته باشد، با همان قیمت دستی خودش فروخته می‌شود.";

    $rows = [
        [btnCb('🎁 مارکت گیفت', 'maadm_market', 'admin')],
        [btnCb('💱 نرخ ارز', 'maadm_rates', 'admin'), btnCb('⭐️ نرخ استارز', 'maadm_starp', 'admin')],
        [btnCb('♻️ تازه‌سازی قیمت‌ها', 'maadm_refresh', 'confirm')],
        [btnCb(UT('back'), 'maadm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🎁 تنظیم مارکت گیفت */
function maAdmMarket($chatId, $msgId) {
    $m = maCfg()['market'] ?? [];

    $text  = "🎁 <b>مارکت گیفت</b>\n\n";
    $text .= "وضعیت: " . (!empty($m['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= "نام: " . h($m['name'] ?? '—') . "\n";
    $text .= "آدرس: " . (trim((string)$m['url']) !== '' ? '<code>' . h($m['url']) . '</code>' : '<b>ثبت نشده</b>') . "\n";
    $text .= "متد: <b>" . h($m['method'] ?? 'GET') . "</b>\n";
    $text .= "هدرها: " . (trim((string)$m['headers']) !== '' ? '✅ ثبت شده' : '—') . "\n";
    $text .= "مسیر فهرست: <code>" . h($m['list_path'] ?: '(ریشه)') . "</code>\n";
    $text .= "فیلد نام: <code>" . h($m['key_field']) . "</code>\n";
    $text .= "فیلد قیمت: <code>" . h($m['price_field']) . "</code>\n";
    $text .= "ارز مبدا: <b>" . h($m['price_cur']) . "</b>\n";
    $text .= "سود: <b>" . (float)$m['margin'] . "%</b> · گرد کردن: " . fmtNum($m['round']) . " تومان\n";
    $text .= "کش: " . (int)$m['ttl'] . " ثانیه\n\n";
    $text .= "💡 <b>راهنما:</b> اول آدرس API را بدهید، بعد «🔌 تست اتصال» را بزنید. " .
             "خروجی خام نشان داده می‌شود تا اسم فیلدها را از داخلش بردارید.";

    $rows = [
        [btnCb(!empty($m['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_mktog', 'info')],
        [btnCb('🔌 تست اتصال', 'maadm_mktest', 'confirm')],
        [btnCb('🔗 آدرس API', 'maadm_mk_url', 'admin'), btnCb('📮 متد', 'maadm_mk_method', 'admin')],
        [btnCb('📋 هدرها', 'maadm_mk_headers', 'admin'), btnCb('📦 بدنه POST', 'maadm_mk_body', 'admin')],
        [btnCb('📂 مسیر فهرست', 'maadm_mk_list_path', 'admin')],
        [btnCb('🏷 فیلد نام', 'maadm_mk_key_field', 'admin'), btnCb('💰 فیلد قیمت', 'maadm_mk_price_field', 'admin')],
        [btnCb('💱 ارز مبدا', 'maadm_mk_price_cur', 'admin'), btnCb('📈 سود %', 'maadm_mk_margin', 'admin')],
        [btnCb('🔢 گرد کردن', 'maadm_mk_round', 'admin'), btnCb('⏱ کش', 'maadm_mk_ttl', 'admin')],
        [btnCb(UT('back'), 'maadm_pricing', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🔌 تست اتصال — پاسخ خام را نشان می‌دهد تا ادمین فیلدها را پیدا کند */
function maAdmMarketTest($chatId) {
    $m = maCfg()['market'] ?? [];
    if (trim((string)$m['url']) === '') {
        sendMsg(BOT_TOKEN, $chatId, "⚠️ اول آدرس API را ثبت کنید.");
        return;
    }

    [$j, $err] = maHttp($m['url'], $m['method'] ?? 'GET', $m['headers'] ?? '', $m['body'] ?? '', 12);
    if (!$j) {
        sendMsg(BOT_TOKEN, $chatId, "❌ <b>اتصال ناموفق</b>\n\n" . h($err),
            inlineKb([[btnCb('🎁 مارکت', 'maadm_market', 'admin')]]));
        return;
    }

    $text = "✅ <b>پاسخ گرفته شد</b>\n\n";
    $keys = array_slice(array_keys($j), 0, 12);
    $text .= "کلیدهای سطح اول: <code>" . h(implode(', ', $keys)) . "</code>\n\n";

    [$map, $perr] = maMarketFetch($m);
    if (is_array($map)) {
        $text .= "🎯 <b>" . count($map) . " گیفت خوانده شد</b>\n\n<b>نمونه:</b>\n";
        $n = 0;
        foreach ($map as $k => $v) {
            $text .= "• <code>" . h($k) . "</code> → " . $v . ' ' . h($m['price_cur']) . "\n";
            if (++$n >= 8) break;
        }
        $text .= "\n💡 همین <code>شناسه</code>ها را در «🔗 کلید مارکت» هر گیفت بگذارید.";
    } else {
        $text .= "⚠️ <b>تجزیه نشد:</b> " . h($perr) . "\n\n";
        $text .= "<b>نمونه پاسخ خام:</b>\n<code>" .
                 h(mb_substr(json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 900)) . "</code>\n\n";
        $text .= "از روی همین، «مسیر فهرست» و «فیلد نام/قیمت» را درست کنید.";
    }

    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[btnCb('🎁 مارکت', 'maadm_market', 'admin')]]));
}

/** 💱 تنظیم نرخ ارز */
function maAdmRates($chatId, $msgId) {
    $r = maCfg()['rates'] ?? [];
    $srcName = maRateSources()[$r['source'] ?? '']['name'] ?? 'دلخواه';
    $text  = "💱 <b>نرخ ارز</b>\n\n";
    $text .= "وضعیت: " . (!empty($r['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= "منبع: <b>" . h($srcName) . "</b>\n\n";
    foreach (['ton' => 'TON', 'trx' => 'TRX', 'usdt' => 'USDT'] as $k => $lbl) {
        $v = maRate($k);
        $text .= "<b>{$lbl}</b>: " . ($v > 0 ? '<b>' . fmtNum($v) . '</b> تومان' : '❌ خوانده نشد') . "\n";
        $err = (string)(maCacheGet('rateerr_' . $k, 0) ?? '');
        if ($err !== '') $text .= "   ⚠️ " . h(mb_substr($err, 0, 110)) . "\n";
    }
    $text .= "\nتقسیم بر: <b>" . (float)$r['div'] . "</b> (ریال→تومان)\n";
    $text .= "سود: <b>" . (float)$r['margin'] . "%</b> · گرد کردن: " . fmtNum($r['round']) . "\n";
    $text .= "کش: " . (int)$r['ttl'] . " ثانیه";

    $rows = [
        [btnCb(!empty($r['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_rttog', 'info')],
        [btnCb('🏦 نوبیتکس', 'maadm_rtsrc_nobitex', 'confirm'),
         btnCb('🏦 والکس', 'maadm_rtsrc_wallex', 'confirm')],
        [btnCb('🔗 آدرس TON', 'maadm_rt_ton_url', 'admin'), btnCb('📂 مسیر TON', 'maadm_rt_ton_path', 'admin')],
        [btnCb('🔗 آدرس TRX', 'maadm_rt_trx_url', 'admin'), btnCb('📂 مسیر TRX', 'maadm_rt_trx_path', 'admin')],
        [btnCb('🔗 آدرس USDT', 'maadm_rt_usdt_url', 'admin'), btnCb('📂 مسیر USDT', 'maadm_rt_usdt_path', 'admin')],
        [btnCb('🔌 تست نرخ‌ها', 'maadm_rttest', 'confirm')],
        [btnCb('➗ تقسیم بر', 'maadm_rt_div', 'admin'), btnCb('📈 سود %', 'maadm_rt_margin', 'admin')],
        [btnCb('🔢 گرد کردن', 'maadm_rt_round', 'admin'), btnCb('⏱ کش', 'maadm_rt_ttl', 'admin')],
        [btnCb(UT('back'), 'maadm_pricing', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🔌 تست نرخ ارز — پاسخ خام هر صرافی را نشان می‌دهد */
function maAdmRateTest($chatId) {
    $r = maCfg()['rates'] ?? [];
    $back = inlineKb([[btnCb('💱 نرخ ارز', 'maadm_rates', 'admin')]]);
    $out = "🔌 <b>تست نرخ ارز</b>\n\n";

    foreach (['ton' => 'TON', 'trx' => 'TRX', 'usdt' => 'USDT'] as $k => $lbl) {
        $url  = (string)($r[$k . '_url'] ?? '');
        $path = (string)($r[$k . '_path'] ?? '');
        $out .= "<b>{$lbl}</b>\n";
        if ($url === '') { $out .= "   آدرس ندارد\n\n"; continue; }

        [$j, $err] = maHttp($url, 'GET', '', '', 10);
        if (!$j) { $out .= "   ❌ " . h($err) . "\n\n"; continue; }

        $raw = maJsonPath($j, $path);
        if (is_scalar($raw)) {
            $num  = maNum($raw);
            $div  = max(1, (float)($r['div'] ?? 1));
            $fin  = maRound(($num / $div) * (1 + ((float)($r['margin'] ?? 0) / 100)), (float)($r['round'] ?? 0));
            $out .= "   خام: <code>" . h((string)$raw) . "</code>\n";
            $out .= "   ÷ " . (float)$div . " و +" . (float)($r['margin'] ?? 0) . "% → <b>" . fmtNum($fin) . "</b> تومان\n\n";
        } else {
            $out .= "   ⚠️ مسیر <code>" . h($path) . "</code> پیدا نشد\n";
            $out .= "   کلیدها: <code>" . h(implode(', ', array_slice(array_keys($j), 0, 8))) . "</code>\n";
            $out .= "   نمونه: <code>" . h(mb_substr(json_encode($j, JSON_UNESCAPED_UNICODE), 0, 260)) . "</code>\n\n";
        }
    }
    $out .= "💡 اگر عدد نهایی با قیمت واقعی بازار نمی‌خواند، «➗ تقسیم بر» را چک کنید — " .
            "نوبیتکس <b>ریال</b> می‌دهد (÷۱۰) ولی والکس <b>تومان</b> (÷۱).";

    sendMsg(BOT_TOKEN, $chatId, $out, $back);
}

/** ⭐️ نرخ استارز */
function maAdmStarPrice($chatId, $msgId) {
    $st = maCfg()['stars'] ?? [];
    $text  = "⭐️ <b>نرخ استارز</b>\n\n";
    $text .= "وضعیت: " . (!empty($st['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= "قیمت هر ۱ استارز: <b>" . fmtNum($st['price'] ?? 0) . "</b> تومان\n";
    $text .= "گرد کردن: " . fmtNum($st['round'] ?? 0) . " تومان\n\n";
    $text .= "با روشن بودن این گزینه، قیمت همه بسته‌های استارز و گیفت‌های استارزی " .
             "خودکار از همین نرخ حساب می‌شود — دیگر لازم نیست تک‌تک را دستی عوض کنید.";

    $rows = [
        [btnCb(!empty($st['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_sttog', 'info')],
        [btnCb('💰 قیمت هر استارز', 'maadm_st_price', 'admin'),
         btnCb('🔢 گرد کردن', 'maadm_st_round', 'admin')],
        [btnCb(UT('back'), 'maadm_pricing', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🤖 پنل تحویل خودکار */
function maAdmFulfill($chatId, $msgId) {
    $f = maCfg()['fulfill'] ?? [];

    $text  = "🤖 <b>تحویل خودکار</b>\n\n";
    $text .= "وضعیت: " . (!empty($f['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= "نام پنل: " . h($f['name'] ?? '—') . "\n";
    $text .= "آدرس: " . (trim((string)$f['base']) !== '' ? '<code>' . h($f['base']) . '</code>' : '<b>ثبت نشده</b>') . "\n";
    $text .= "احراز هویت: <b>" . h($f['auth_type'] ?? '—') . "</b>";
    $text .= trim((string)$f['auth_key']) !== '' ? ' · <code>' . h($f['auth_key']) . '</code>' : '';
    $text .= "\nکلید: " . (trim((string)$f['auth_value']) !== ''
             ? '✅ ثبت شده (' . h(mb_substr((string)$f['auth_value'], 0, 6)) . '…)' : '<b>خالی</b>') . "\n";
    $text .= "تلاش دوباره: <b>" . (int)$f['retry'] . "</b> بار · مهلت: " . (int)$f['timeout'] . " ثانیه\n";
    $text .= "اجرای فوری بعد از پرداخت: " . (!empty($f['auto_pay']) ? '✅' : '❌') . "\n\n";

    $text .= "<b>عملیات‌ها:</b>\n";
    foreach (['balance' => '💰 موجودی', 'stars' => '⭐️ استارز', 'premium' => '💎 پریمیوم', 'gift' => '🎁 گیفت'] as $k => $lbl) {
        $o = $f['ops'][$k] ?? [];
        $p = trim((string)($o['path'] ?? ''));
        $text .= $lbl . ': ' . ($p !== '' ? '<code>' . h($o['method'] ?? 'POST') . ' ' . h($p) . '</code>' : '<b>تنظیم نشده</b>') . "\n";
    }

    $pend = 0;
    foreach (MaOrder::all() as $o) {
        if ($o['status'] === MaOrder::PAID && !empty($o['tries'])) $pend++;
    }
    if ($pend) $text .= "\n⚠️ <b>{$pend}</b> سفارش پرداخت‌شده تحویل نشده است.";

    $text .= "\n\n💡 اول آدرس و کلید را بدهید، بعد «🔌 تست موجودی» را بزنید.";

    $rows = [
        [btnCb(!empty($f['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'maadm_fftog', 'info')],
        [btnCb('📖 خواندن مستندات API', 'maadm_spec', 'confirm')],
        [btnCb('🔌 تست موجودی پنل', 'maadm_fftest', 'confirm')],
        [btnCb('🔗 آدرس پنل', 'maadm_ff_base', 'admin'), btnCb('🏷 نام', 'maadm_ff_name', 'admin')],
        [btnCb('🔐 نوع احراز', 'maadm_ffauth', 'admin'), btnCb('🔑 کلید API', 'maadm_ff_auth_value', 'admin')],
        [btnCb('📛 نام هدر/پارامتر', 'maadm_ff_auth_key', 'admin'),
         btnCb('📄 آدرس مستندات', 'maadm_ff_spec_url', 'admin')],
        [btnCb('⭐️ عملیات استارز', 'maadm_ffop_stars', 'admin')],
        [btnCb('💎 عملیات پریمیوم', 'maadm_ffop_premium', 'admin')],
        [btnCb('🎁 عملیات گیفت', 'maadm_ffop_gift', 'admin')],
        [btnCb('💰 عملیات موجودی', 'maadm_ffop_balance', 'admin')],
        [btnCb('🔁 تعداد تلاش', 'maadm_ff_retry', 'admin'), btnCb('⏱ مهلت', 'maadm_ff_timeout', 'admin')],
        [btnCb(!empty($f['auto_pay']) ? '⚡️ اجرای فوری: روشن' : '⚡️ اجرای فوری: خاموش', 'maadm_ffauto', 'info')],
        [btnCb('🧾 سفارش‌های تحویل‌نشده', 'maadm_ffstuck', 'reject')],
        [btnCb(UT('back'), 'maadm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** ⚙️ تنظیم یک عملیات */
function maAdmFulfillOp($chatId, $msgId, $op) {
    $labels = ['balance' => '💰 موجودی', 'stars' => '⭐️ استارز', 'premium' => '💎 پریمیوم', 'gift' => '🎁 گیفت'];
    $o = maCfg()['fulfill']['ops'][$op] ?? [];

    $text  = (($labels[$op] ?? $op)) . " <b>— تنظیم عملیات</b>\n\n";
    $text .= "متد: <b>" . h($o['method'] ?? 'POST') . "</b>\n";
    $text .= "مسیر: <code>" . h($o['path'] ?: '(خالی)') . "</code>\n";
    $text .= "بدنه:\n<code>" . h($o['body'] ?: '(خالی)') . "</code>\n";
    $text .= "مسیر شناسه سفارش: <code>" . h($o['id_path'] ?? '—') . "</code>\n";
    $text .= "مسیر مقدار: <code>" . h($o['val_path'] ?? '—') . "</code>\n";
    $text .= "مسیر خطا: <code>" . h($o['err_path'] ?? '—') . "</code>\n\n";
    $text .= "<b>متغیرهای قابل استفاده در مسیر و بدنه:</b>\n";
    $text .= "<code>{username}</code> آیدی گیرنده (بدون @)\n";
    $text .= "<code>{qty}</code> مقدار (تعداد استارز یا ماه پریمیوم)\n";
    $text .= "<code>{gift}</code> شناسه گیفت در پنل\n";
    $text .= "<code>{order}</code> کد سفارش ما\n";
    $text .= "<code>{user_id}</code> آیدی عددی کاربر\n\n";
    $text .= "مثال بدنه:\n<code>{\"username\":\"{username}\",\"quantity\":{qty}}</code>";

    $rows = [
        [btnCb('📮 متد', 'maadm_ffo_' . $op . '|method', 'admin'),
         btnCb('📂 مسیر', 'maadm_ffo_' . $op . '|path', 'admin')],
        [btnCb('📦 بدنه', 'maadm_ffo_' . $op . '|body', 'admin')],
        [btnCb('🧾 مسیر شناسه', 'maadm_ffo_' . $op . '|id_path', 'admin'),
         btnCb('💠 مسیر مقدار', 'maadm_ffo_' . $op . '|val_path', 'admin')],
        [btnCb('⚠️ مسیر خطا', 'maadm_ffo_' . $op . '|err_path', 'admin')],
        [btnCb(UT('back'), 'maadm_fulfill', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/**
 * 📖 خواندن مستندات OpenAPI پنل.
 *
 * صفحه Swagger هر API یک فایل openapi.json دارد که همه مسیرها، متدها و
 * فیلدها را دقیق توصیف می‌کند. به‌جای حدس زدن یا اسکرین‌شات گرفتن،
 * همان فایل را می‌خوانیم و فهرست واقعی را نشان می‌دهیم.
 */
function maSpecUrlGuess() {
    $f = maCfg()['fulfill'] ?? [];
    $u = trim((string)($f['spec_url'] ?? ''));
    if ($u !== '') return $u;
    $base = rtrim((string)($f['base'] ?? ''), '/');
    return $base !== '' ? $base . '/openapi.json' : '';
}

/** کلیدواژه‌های هر عملیات — برای پیشنهاد خودکار مسیر */
function maSpecKeywords() {
    return [
        'balance' => ['balance', 'deposit', 'wallet', 'account', 'credit', 'me', 'profile', 'funds'],
        'stars'   => ['star'],
        'premium' => ['premium'],
        'gift'    => ['gift'],
    ];
}

/**
 * پیدا کردن آدرس openapi.json.
 *
 * مسیر پیش‌فرض همیشه درست نیست (اگر برنامه زیر یک پیشوند سوار شده باشد).
 * پس اول مسیرهای رایج را امتحان می‌کنیم، و اگر نشد، HTML صفحه Swagger را
 * می‌خوانیم — آدرس واقعی spec همیشه داخل خود آن صفحه نوشته شده است.
 *
 * برگشت: [آدرس, داده, گزارش تلاش‌ها]
 */
function maSpecDiscover($explicit = '') {
    $f    = maCfg()['fulfill'] ?? [];
    $base = rtrim((string)($f['base'] ?? ''), '/');
    $log  = [];

    $tries = [];
    if ($explicit !== '') $tries[] = $explicit;
    if ($base !== '') {
        foreach (['/openapi.json', '/v1/openapi.json', '/api/openapi.json',
                  '/docs/openapi.json', '/openapi', '/swagger.json',
                  '/v1/swagger.json', '/api/v1/openapi.json'] as $p) {
            $tries[] = $base . $p;
        }
    }

    foreach ($tries as $u) {
        [$j, $err] = maHttp($u, 'GET', '', '', 12);
        if (is_array($j) && !empty($j['paths'])) return [$u, $j, $log];
        $log[] = substr($u, strlen($base)) . ' → ' . ($err ?: 'بدون paths');
    }

    // آخرین راه: آدرس spec را از داخل صفحه مستندات بیرون بکش
    foreach ([$base, $base . '/docs', $base . '/redoc'] as $page) {
        if ($base === '') break;
        [$html, $err] = maHttpRaw($page, 12);
        if (!$html) { $log[] = 'صفحه ' . ($page === $base ? '/' : substr($page, strlen($base))) . ' → ' . $err; continue; }

        $found = [];
        // url: "…openapi.json"  یا  "url":"…/openapi.json"  یا  spec-url="…"
        if (preg_match_all('#["\']([^"\'\s]*(?:openapi|swagger)[^"\'\s]*\.json)["\']#i', $html, $m)) {
            foreach ($m[1] as $cand) $found[] = $cand;
        }
        if (preg_match_all('#(?:url|spec-url|data-url)\s*[:=]\s*["\']([^"\']+)["\']#i', $html, $m2)) {
            foreach ($m2[1] as $cand) if (str_contains(strtolower($cand), 'openapi') || str_contains(strtolower($cand), 'swagger')) $found[] = $cand;
        }

        foreach (array_unique($found) as $cand) {
            $u = $cand;
            if (str_starts_with($u, '//'))      $u = 'https:' . $u;
            elseif (str_starts_with($u, '/'))   $u = $base . $u;
            elseif (!preg_match('#^https?://#i', $u)) $u = $base . '/' . ltrim($u, '/');

            [$j, $e2] = maHttp($u, 'GET', '', '', 12);
            if (is_array($j) && !empty($j['paths'])) return [$u, $j, $log];
            $log[] = 'از صفحه: ' . $cand . ' → ' . ($e2 ?: 'بدون paths');
        }
        if (!$found) $log[] = 'صفحه ' . ($page === $base ? '/' : substr($page, strlen($base))) . ' → آدرس spec داخلش نبود';
    }

    return ['', null, $log];
}

function maAdmSpecRead($chatId) {
    $back = inlineKb([[btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'admin')]]);
    $f    = maCfg()['fulfill'] ?? [];
    if (trim((string)($f['base'] ?? '')) === '') {
        sendMsg(BOT_TOKEN, $chatId, "⚠️ اول «🔗 آدرس پنل» را ثبت کنید.", $back);
        return;
    }

    [$url, $j, $log] = maSpecDiscover(trim((string)($f['spec_url'] ?? '')));

    if (!$j) {
        $t  = "❌ <b>مستندات پیدا نشد</b>\n\n";
        $t .= "این آدرس‌ها امتحان شد:\n";
        foreach (array_slice($log, 0, 14) as $l) $t .= "• <code>" . h(mb_substr($l, 0, 76)) . "</code>\n";
        $t .= "\nصفحه مستندات را در مرورگر باز کنید و آدرسی که با <code>.json</code> " .
              "تمام می‌شود پیدا کنید، بعد آن را در «📄 آدرس مستندات» ثبت کنید.\n\n" .
              "راه ساده‌تر: در مرورگر روی صفحه مستندات، <b>View Source</b> بزنید و " .
              "کلمه <code>openapi</code> را جستجو کنید.";
        sendMsg(BOT_TOKEN, $chatId, $t, $back);
        return;
    }

    // آدرسی که جواب داد را ذخیره کن تا دفعه بعد مستقیم برود
    maSetRoot(function (&$m) use ($url) { $m['fulfill']['spec_url'] = $url; });

    $paths = $j['paths'] ?? null;
    if (!is_array($paths)) {
        sendMsg(BOT_TOKEN, $chatId,
            "⚠️ فایل خوانده شد ولی بخش <code>paths</code> نداشت.\nکلیدها: <code>" .
            h(implode(', ', array_slice(array_keys($j), 0, 10))) . '</code>', $back);
        return;
    }

    // همه مسیرها را صاف می‌کنیم
    $all = [];
    foreach ($paths as $path => $methods) {
        if (!is_array($methods)) continue;
        foreach ($methods as $m => $def) {
            $m = strtolower($m);
            if (!in_array($m, ['get', 'post', 'put', 'patch', 'delete'], true)) continue;
            $all[] = [
                'method'  => strtoupper($m),
                'path'    => (string)$path,
                'summary' => (string)($def['summary'] ?? ''),
            ];
        }
    }
    if (!$all) { sendMsg(BOT_TOKEN, $chatId, "⚠️ هیچ مسیری در مستندات نبود.", $back); return; }

    save('ma_spec', $all);

    // 🎯 مهم‌ترین سوال: آیا حساب اعتباری دارد یا نه؟
    $hits = [];
    foreach (maSpecKeywords() as $op => $words) {
        foreach ($all as $i => $e) {
            $hay = mb_strtolower($e['path'] . ' ' . $e['summary']);
            foreach ($words as $w) {
                if (str_contains($hay, $w)) { $hits[$op][] = $i; break; }
            }
        }
    }

    $t  = "📖 <b>مستندات پنل خوانده شد</b>\n\n";
    $t .= "<code>" . h(mb_substr($url, 0, 70)) . "</code>\n";
    $t .= "مجموع مسیرها: <b>" . count($all) . "</b>\n\n";

    $labels = ['balance' => '💰 موجودی / اعتبار', 'stars' => '⭐️ استارز',
               'premium' => '💎 پریمیوم', 'gift' => '🎁 گیفت'];
    $rows = [];
    foreach ($labels as $op => $lbl) {
        $list = $hits[$op] ?? [];
        $t .= $lbl . ': ' . (count($list) ? '<b>' . count($list) . '</b> مسیر' : '❌ پیدا نشد') . "\n";
        foreach (array_slice($list, 0, 5) as $i) {
            $e = $all[$i];
            $t .= "   <code>" . h($e['method'] . ' ' . $e['path']) . "</code>\n";
            if ($e['summary'] !== '') $t .= "      " . h(mb_substr($e['summary'], 0, 46)) . "\n";
        }
        if (count($list)) {
            $rows[] = [btnCb($lbl . ' — انتخاب مسیر', 'maadm_spick_' . $op, 'info')];
        }
        $t .= "\n";
    }

    $hasBalance = !empty($hits['balance']);
    $t .= $hasBalance
        ? "✅ <b>مسیر موجودی/اعتبار پیدا شد</b> — یعنی احتمالا می‌شود بدون کلید ولت، از حساب اعتباری خرید کرد. مسیرها را ببینید.\n"
        : "⚠️ <b>هیچ مسیر موجودی/اعتباری پیدا نشد.</b> یعنی این پنل با تراکنش ولت کار می‌کند، نه حساب شارژشده.\n";

    $rows[] = [btnCb('📜 دیدن همه مسیرها', 'maadm_sall_0', 'admin')];
    $rows[] = [btnCb(UT('back'), 'maadm_fulfill', 'nav')];

    sendMsg(BOT_TOKEN, $chatId, mb_substr($t, 0, 3800), inlineKb($rows));
}

/** 📜 فهرست همه مسیرها، صفحه‌به‌صفحه */
function maAdmSpecAll($chatId, $msgId, $page = 0) {
    $all = load('ma_spec');
    if (!is_array($all) || !$all) {
        editMsg(BOT_TOKEN, $chatId, $msgId, "اول «📖 خواندن مستندات» را بزنید.",
            inlineKb([[btnCb(UT('back'), 'maadm_fulfill', 'nav')]]));
        return;
    }
    $per   = 18;
    $pages = (int)ceil(count($all) / $per);
    $page  = max(0, min($pages - 1, (int)$page));

    $t = "📜 <b>مسیرهای پنل</b> — صفحه " . ($page + 1) . " از {$pages}\n\n";
    foreach (array_slice($all, $page * $per, $per) as $e) {
        $t .= "<code>" . h($e['method'] . ' ' . $e['path']) . "</code>\n";
        if ($e['summary'] !== '') $t .= "   " . h(mb_substr($e['summary'], 0, 44)) . "\n";
    }

    $nav = [];
    if ($page > 0)          $nav[] = btnCb('◀️ قبلی', 'maadm_sall_' . ($page - 1), 'nav');
    if ($page < $pages - 1) $nav[] = btnCb('بعدی ▶️', 'maadm_sall_' . ($page + 1), 'nav');
    $rows = $nav ? [$nav] : [];
    $rows[] = [btnCb(UT('back'), 'maadm_fulfill', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

/** 🎯 انتخاب مسیر برای یک عملیات، از روی مستندات */
function maAdmSpecPick($chatId, $msgId, $op) {
    $all = load('ma_spec');
    if (!is_array($all) || !$all) { maAdmFulfill($chatId, $msgId); return; }

    $words = maSpecKeywords()[$op] ?? [];
    $rows  = [];
    $n     = 0;
    foreach ($all as $i => $e) {
        $hay = mb_strtolower($e['path'] . ' ' . $e['summary']);
        $ok  = false;
        foreach ($words as $w) if (str_contains($hay, $w)) { $ok = true; break; }
        if (!$ok) continue;
        $rows[] = [btnCb($e['method'] . ' ' . mb_substr($e['path'], 0, 40), 'maadm_sset_' . $op . '|' . $i, 'info')];
        if (++$n >= 14) break;
    }
    $rows[] = [btnCb(UT('back'), 'maadm_fulfill', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🎯 <b>مسیر عملیات را انتخاب کنید</b>\n\n" .
        "با زدن هرکدام، مسیر و متدش روی این عملیات می‌نشیند. " .
        "بعدش فقط «📦 بدنه» را با متغیرهای <code>{username}</code> و <code>{qty}</code> پر کنید.",
        inlineKb($rows));
}

/** همان صفحه انتخاب مسیر، ولی به‌صورت پیام تازه */
function maAdmSpecPickSend($chatId, $op) {
    $r = sendMsg(BOT_TOKEN, $chatId, '⏳');
    $mid = $r['result']['message_id'] ?? null;
    if ($mid) maAdmSpecPick($chatId, $mid, $op);
}

/** 🔌 تست اتصال به پنل */
function maAdmFulfillTest($chatId) {
    $f = maCfg()['fulfill'] ?? [];
    if (trim((string)$f['base']) === '') {
        sendMsg(BOT_TOKEN, $chatId, "⚠️ اول آدرس پنل را ثبت کنید.");
        return;
    }
    [$resp, $err] = maFulfillCall('balance', []);
    $back = inlineKb([[btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'admin')]]);

    if (!$resp) {
        $is404 = str_contains($err, '404');
        $path  = trim((string)($f['ops']['balance']['path'] ?? ''));

        $t  = "❌ <b>اتصال ناموفق</b>\n\n" . h($err) . "\n\n";
        if ($is404) {
            $t .= "مسیر فعلی: <code>" . h(($f['ops']['balance']['method'] ?? 'GET') . ' ' . $path) . "</code>\n\n";
            $t .= "۴۰۴ یعنی این مسیر روی پنل وجود ندارد — کلید API مشکلی ندارد.\n" .
                  "مسیر درست را از مستندات انتخاب کنید 👇";
        } else {
            $t .= "اگر «۴۰۱» یا «۴۰۳» است، کلید API یا نوع احراز هویت درست نیست.";
        }

        $rows = [];
        if ($is404) {
            $spec = load('ma_spec');
            if (is_array($spec) && $spec) $rows[] = [btnCb('🎯 انتخاب مسیر موجودی از مستندات', 'maadm_spick_balance', 'confirm')];
            else                          $rows[] = [btnCb('📖 اول مستندات را بخوان', 'maadm_spec', 'confirm')];
        }
        $rows[] = [btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'nav')];
        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
        return;
    }

    $cfgOp = $f['ops']['balance'] ?? [];
    $val   = maJsonPath($resp, (string)($cfgOp['val_path'] ?? ''));

    $t  = "✅ <b>پنل جواب داد</b>\n\n";
    $t .= 'کلیدهای پاسخ: <code>' . h(implode(', ', array_slice(array_keys($resp), 0, 12))) . "</code>\n\n";
    $t .= is_scalar($val)
        ? "💰 موجودی خوانده‌شده: <b>" . h((string)$val) . "</b>\n\n"
        : "⚠️ «مسیر مقدار» درست نیست — از پاسخ زیر مسیر موجودی را پیدا کنید.\n\n";
    $t .= "<b>پاسخ خام:</b>\n<code>" .
          h(mb_substr(json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 900)) . '</code>';

    sendMsg(BOT_TOKEN, $chatId, $t, $back);
}

/** 🧾 سفارش‌های پرداخت‌شده که تحویل نشده‌اند */
function maAdmStuck($chatId, $msgId) {
    $rows = []; $n = 0;
    $text = "🧾 <b>سفارش‌های تحویل‌نشده</b>\n\nپول گرفته شده ولی سرویس تحویل نشده:\n\n";

    foreach (MaOrder::all() as $o) {
        if ($o['status'] !== MaOrder::PAID) continue;
        $text .= '• ' . h(maOrderTitle($o)) . ' — ' . fmtNum($o['total']) . " تومان\n";
        $text .= '  👤 <code>' . $o['user_id'] . '</code> · 🔁 ' . (int)($o['tries'] ?? 0) . " تلاش\n";
        if (!empty($o['last_error'])) $text .= '  ❌ ' . h(mb_substr((string)$o['last_error'], 0, 80)) . "\n";
        $rows[] = [btnCb(mb_substr(maOrderTitle($o), 0, 22), 'maadm_ord_' . $o['id'], 'info'),
                   btnCb('🔁', 'maretry_' . $o['id'], 'confirm'),
                   btnCb('💰', 'marefund_' . $o['id'], 'reject')];
        if (++$n >= 10) break;
    }
    if (!$n) $text .= '✅ هیچ سفارش معطلی نیست.';

    $rows[] = [btnCb(UT('back'), 'maadm_fulfill', 'nav')];
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

    // ---- 🔌 قیمت‌گذاری زنده (سراسری، نه مخصوص یک اپ) ----
    if ($data === 'maadm_pricing') { answerCb(BOT_TOKEN, $cbId); maAdmPricing($chatId, $msgId); return true; }
    if ($data === 'maadm_market')  { answerCb(BOT_TOKEN, $cbId); maAdmMarket($chatId, $msgId); return true; }
    if ($data === 'maadm_rates')   { answerCb(BOT_TOKEN, $cbId); maAdmRates($chatId, $msgId); return true; }
    if ($data === 'maadm_starp')   { answerCb(BOT_TOKEN, $cbId); maAdmStarPrice($chatId, $msgId); return true; }

    if ($data === 'maadm_mktest')  { answerCb(BOT_TOKEN, $cbId, '⏳ در حال تست…'); maAdmMarketTest($chatId); return true; }

    if ($data === 'maadm_mktog') {
        maSetRoot(function (&$m) { $m['market']['on'] = empty($m['market']['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); maAdmMarket($chatId, $msgId); return true;
    }
    if ($data === 'maadm_rttog') {
        maSetRoot(function (&$m) { $m['rates']['on'] = empty($m['rates']['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); maAdmRates($chatId, $msgId); return true;
    }
    if ($data === 'maadm_sttog') {
        maSetRoot(function (&$m) { $m['stars']['on'] = empty($m['stars']['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); maAdmStarPrice($chatId, $msgId); return true;
    }
    if (preg_match('/^maadm_rtsrc_([a-z]+)$/', $data, $rs)) {
        if (maApplyRateSource($rs[1])) {
            $v = maRate('ton', true);
            answerCb(BOT_TOKEN, $cbId, $v > 0 ? '✅ ' . fmtNum($v) . ' تومان' : '⚠️ نرخ خوانده نشد', true);
        } else {
            answerCb(BOT_TOKEN, $cbId, '❌');
        }
        maAdmRates($chatId, $msgId);
        return true;
    }

    if ($data === 'maadm_rttest') { answerCb(BOT_TOKEN, $cbId, '⏳ تست…'); maAdmRateTest($chatId); return true; }

    if ($data === 'maadm_refresh') {
        save('ma_cache', []);
        maMarketMap(true);
        foreach (['ton', 'trx', 'usdt'] as $k) maRate($k, true);
        answerCb(BOT_TOKEN, $cbId, '♻️ تازه شد');
        maAdmPricing($chatId, $msgId);
        return true;
    }

    // maadm_mk_<field> / maadm_rt_<field> / maadm_st_<field>
    if (preg_match('/^maadm_(mk|rt|st)_([a-z_]+)$/', $data, $mm)) {
        $sec   = ['mk' => 'market', 'rt' => 'rates', 'st' => 'stars'][$mm[1]];
        $field = $mm[2];
        $cur   = maCfg()[$sec][$field] ?? '';
        answerCb(BOT_TOKEN, $cbId);
        $hints = [
            'url'         => 'آدرس کامل API را بفرستید (با https).',
            'method'      => "<code>GET</code> یا <code>POST</code>",
            'headers'     => "هر خط یک هدر:\n<code>Authorization: Bearer xxx</code>\nبرای پاک کردن <code>-</code>",
            'body'        => "بدنه JSON برای POST — برای پاک کردن <code>-</code>",
            'list_path'   => "مسیر آرایه نتایج، مثل <code>data.results</code>\nاگر خودِ پاسخ آرایه است، <code>-</code> بفرستید.",
            'key_field'   => "اسم فیلدی که نام گیفت در آن است، مثل <code>name</code>",
            'price_field' => "اسم فیلدی که قیمت در آن است، مثل <code>price</code>",
            'price_cur'   => "<code>TON</code> یا <code>USDT</code> یا <code>IRT</code>",
            'margin'      => 'درصد سود روی قیمت پایه — فقط عدد.',
            'round'       => 'قیمت نهایی به این عدد گرد می‌شود (بالا). مثلا 1000',
            'ttl'         => 'چند ثانیه قیمت‌ها کش شوند؟ مثلا 600',
            'div'         => 'نرخ بر این عدد تقسیم می‌شود. نوبیتکس ریال می‌دهد پس 10',
            'price'       => 'قیمت هر ۱ استارز به تومان — فقط عدد.',
        ];
        $hint = $hints[$field] ?? '';
        if (str_ends_with($field, '_url'))  $hint = $hints['url'];
        if (str_ends_with($field, '_path')) $hint = "مسیر مقدار داخل JSON، مثل <code>stats.ton-rls.latest</code>";
        maAskState($uid, $chatId, 'ma_pcfg', ['sec' => $sec, 'f' => $field],
            '✏️ مقدار جدید را بفرستید:',
            ($hint !== '' ? $hint . "\n\n" : '') . 'الان: <code>' . h(mb_substr((string)$cur, 0, 120)) . '</code>');
        return true;
    }

    // ---- 🤖 تحویل خودکار ----
    if ($data === 'maadm_fulfill') { answerCb(BOT_TOKEN, $cbId); maAdmFulfill($chatId, $msgId); return true; }
    if ($data === 'maadm_autodiag') { answerCb(BOT_TOKEN, $cbId, '🩺'); maAdmAutoDiag($chatId); return true; }
    if ($data === 'maadm_ffstuck') { answerCb(BOT_TOKEN, $cbId); maAdmStuck($chatId, $msgId); return true; }
    if ($data === 'maadm_fftest')  { answerCb(BOT_TOKEN, $cbId, '⏳ تست…'); maAdmFulfillTest($chatId); return true; }
    if ($data === 'maadm_spec')    { answerCb(BOT_TOKEN, $cbId, '⏳ خواندن…'); maAdmSpecRead($chatId); return true; }

    if (preg_match('/^maadm_sall_(\d+)$/', $data, $sm)) {
        answerCb(BOT_TOKEN, $cbId); maAdmSpecAll($chatId, $msgId, (int)$sm[1]); return true;
    }
    if (preg_match('/^maadm_spick_([a-z]+)$/', $data, $sm)) {
        answerCb(BOT_TOKEN, $cbId);
        if ($msgId) maAdmSpecPick($chatId, $msgId, $sm[1]);
        else        maAdmSpecPickSend($chatId, $sm[1]);
        return true;
    }
    if (preg_match('/^maadm_sset_([a-z]+)\|(\d+)$/', $data, $sm)) {
        [$all2, $op2, $idx] = [load('ma_spec'), $sm[1], (int)$sm[2]];
        $e = is_array($all2) ? ($all2[$idx] ?? null) : null;
        if ($e) {
            maSetRoot(function (&$m) use ($op2, $e) {
                $m['fulfill']['ops'][$op2]['path']   = $e['path'];
                $m['fulfill']['ops'][$op2]['method'] = $e['method'];
            });
            answerCb(BOT_TOKEN, $cbId, '✅ ' . $e['method'] . ' ' . mb_substr($e['path'], 0, 28), true);
            maAdmFulfillOp($chatId, $msgId, $op2);
        } else {
            answerCb(BOT_TOKEN, $cbId, '❌');
        }
        return true;
    }

    if ($data === 'maadm_fftog') {
        maSetRoot(function (&$m) { $m['fulfill']['on'] = empty($m['fulfill']['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); maAdmFulfill($chatId, $msgId); return true;
    }
    if ($data === 'maadm_ffauto') {
        maSetRoot(function (&$m) { $m['fulfill']['auto_pay'] = empty($m['fulfill']['auto_pay']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); maAdmFulfill($chatId, $msgId); return true;
    }
    if ($data === 'maadm_ffauth') {
        $cur = maCfg()['fulfill']['auth_type'] ?? 'header';
        $seq = ['header' => 'query', 'query' => 'body', 'body' => 'none', 'none' => 'header'];
        maSetRoot(function (&$m) use ($seq, $cur) { $m['fulfill']['auth_type'] = $seq[$cur] ?? 'header'; });
        answerCb(BOT_TOKEN, $cbId, maCfg()['fulfill']['auth_type']);
        maAdmFulfill($chatId, $msgId);
        return true;
    }
    if (preg_match('/^maadm_ffop_([a-z]+)$/', $data, $fm)) {
        answerCb(BOT_TOKEN, $cbId); maAdmFulfillOp($chatId, $msgId, $fm[1]); return true;
    }
    if (preg_match('/^maadm_ff_([a-z_]+)$/', $data, $fm)) {
        $field = $fm[1];
        $cur = maCfg()['fulfill'][$field] ?? '';
        answerCb(BOT_TOKEN, $cbId);
        $hints = [
            'base'       => "آدرس پایه پنل، بدون اسلش آخر:\n<code>https://api.marketapp.org</code>",
            'spec_url'   => "آدرس فایل openapi.json پنل. خالی بگذارید تا خودش حدس بزند:\n<code>{آدرس پنل}/openapi.json</code>",
            'name'       => 'نامی که در پیام‌ها نشان داده می‌شود.',
            'auth_key'   => "اسم هدر یا پارامتر کلید — مثلا <code>Authorization</code> یا <code>api_key</code>",
            'auth_value' => "مقدار کلید. اگر پنل <code>Bearer</code> می‌خواهد، کاملش را بنویسید:\n<code>Bearer xxxxx</code>",
            'retry'      => 'چند بار تلاش دوباره؟ مثلا 3',
            'timeout'    => 'مهلت هر تماس به ثانیه — مثلا 20',
        ];
        maAskState($uid, $chatId, 'ma_ffcfg', ['f' => $field], '✏️ مقدار جدید:',
            ($hints[$field] ?? '') . "\n\nالان: <code>" . h(mb_substr((string)$cur, 0, 120)) . '</code>');
        return true;
    }
    if (preg_match('/^maadm_ffo_([a-z]+)\|([a-z_]+)$/', $data, $fm)) {
        [$all, $op2, $field] = $fm;
        $cur = maCfg()['fulfill']['ops'][$op2][$field] ?? '';
        answerCb(BOT_TOKEN, $cbId);
        $hints = [
            'method'   => "<code>GET</code> یا <code>POST</code>",
            'path'     => "مسیر بعد از آدرس پایه، مثل <code>/order/stars</code>\nمتغیرها مجازند: <code>{username}</code> <code>{qty}</code>",
            'body'     => "بدنه JSON. مثال:\n<code>{\"username\":\"{username}\",\"quantity\":{qty}}</code>\nبرای خالی کردن <code>-</code>",
            'id_path'  => "مسیر شناسه سفارش در پاسخ، مثل <code>data.order_id</code>",
            'val_path' => "مسیر مقدار در پاسخ، مثل <code>balance</code>",
            'err_path' => "مسیر پیام خطا، مثل <code>message</code>",
        ];
        maAskState($uid, $chatId, 'ma_ffop', ['op' => $op2, 'f' => $field], '✏️ مقدار جدید:',
            ($hints[$field] ?? '') . "\n\nالان: <code>" . h(mb_substr((string)$cur, 0, 200)) . '</code>');
        return true;
    }

    if ($data === 'maadm_rowlay') {
        answerCb(BOT_TOKEN, $cbId);
        maAskState($uid, $chatId, 'ma_rowlay', [],
            '📐 <b>چیدمان دکمه‌های مینی‌اپ</b>',
            "این دکمه‌ها زیر دکمه‌های ثبت سفارش می‌نشینند. با کاما بگویید هر ردیف چند تا:\n\n" .
            "<code>1,1</code> هرکدام در یک ردیف کامل\n" .
            "<code>2</code> هر دو کنار هم در یک ردیف\n\n" .
            'الان: <code>' . h(maCfg()['row_layout'] ?: '1,1') . '</code>');
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
            maAskState($uid, $chatId, 'ma_btn_order', ['k' => $key],
                '🔢 ترتیب نمایش (عدد):',
                "عدد کوچک‌تر یعنی بالاتر. زیردکمه‌های ثبت سفارش هم همین «ترتیب» را دارند، " .
                "پس با عدد می‌توانید این دکمه را بین آن‌ها جابه‌جا کنید.");
            return true;
        case 'br':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_btn_row', ['k' => $key],
                '📍 شماره ردیف (عدد):',
                "اگر عدد بدهید، این دکمه حتما در همان ردیف می‌نشیند.\n۰ یعنی خودکار (طبق چیدمان).");
            return true;

        // ---------- تم ----------
        case 'c1': case 'c2': case 'c3': case 'bg':
            answerCb(BOT_TOKEN, $cbId);
            $names = ['c1' => 'رنگ اصلی', 'c2' => 'رنگ دوم', 'c3' => 'رنگ تاکید', 'bg' => 'پس‌زمینه'];
            maAskState($uid, $chatId, 'ma_theme', ['k' => $key, 'f' => $op],
                '🎨 ' . $names[$op] . ' را بفرستید:',
                "به شکل <code>#RRGGBB</code>\nالان: <code>" . h($a['theme'][$op] ?? '') . '</code>');
            return true;
        case 'fx':
            maSet($key, function (&$x) {
                $cur = isset($x['theme']['fx']) ? (int)$x['theme']['fx'] : 2;
                $x['theme']['fx'] = ($cur + 2) % 3;   // ۲ → ۱ → ۰ → ۲
            });
            answerCb(BOT_TOKEN, $cbId, maFxLabel(maGet($key)['theme']['fx']));
            maAdmTheme($chatId, $msgId, $key);
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
        case 'glay':
            answerCb(BOT_TOKEN, $cbId);
            maAskState($uid, $chatId, 'ma_glay', ['k' => $key],
                '📐 چیدمان دکمه‌های فاکتور را بفرستید:',
                "با کاما جدا کنید — هر عدد یعنی تعداد دکمه آن ردیف.\n\n" .
                "<code>1,1,1</code> هر کدام در یک ردیف\n" .
                "<code>2,1</code> دو تا بالا، یکی پایین\n" .
                "<code>3</code> هر سه کنار هم\n\n" .
                'الان: <code>' . h($a['glass_layout'] ?: '1,1,1') . '</code>');
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
        case 'io': case 'imin': case 'imax': case 'imk': case 'ist':
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
                'imk'  => ['market_key', '🔗 کلید این گیفت در مارکت:',
                           "همان شناسه‌ای که در «🔌 تست اتصال» دیدید، مثل <code>birthday_cake</code>.\nبرای حذف <code>-</code>."],
                'ist'  => ['stars', '⭐️ ارزش این سرویس به استارز:',
                           "مثلا برای گیفت تدی <code>15</code>.\nبا روشن بودن «نرخ استارز»، قیمت خودکار حساب می‌شود. ۰ = غیرفعال."],
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
        case 'iau':
            answerCb(BOT_TOKEN, $cbId);
            $it = maFindItem($key, $arg);
            $rows = [];
            foreach (maAutoLabels() as $ak => $al) {
                $mark = (trim((string)($it['auto'] ?? '')) === $ak) ? '✅ ' : '';
                $rows[] = [btnCb($mark . $al, 'maadm_iauset_' . $key . '|' . $arg . '~' . $ak, 'info')];
            }
            $rows[] = [btnCb('🔢 مقدار ارسالی به پنل', 'maadm_iaq_' . $key . '|' . $arg, 'admin'),
                       btnCb('🏷 شناسه در پنل', 'maadm_iai_' . $key . '|' . $arg, 'admin')];
            $rows[] = [btnCb(UT('back'), 'maadm_item_' . $key . '|' . $arg, 'nav')];
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "🤖 <b>تحویل خودکار این سرویس</b>\n\n" .
                "بعد از پرداخت، ربات خودش سفارش را روی پنل فروش ثبت می‌کند.\n\n" .
                "• <b>مقدار ارسالی</b>: مثلا برای «۱۰۰ استارز» عدد <code>100</code>، " .
                "برای «پریمیوم ۳ ماهه» عدد <code>3</code>.\n" .
                "  اگر <code>0</code> بگذارید، همان تعداد سفارش کاربر فرستاده می‌شود.\n" .
                "• <b>شناسه در پنل</b>: برای گیفت‌ها، همان شناسه‌ای که پنل می‌شناسد.",
                inlineKb($rows));
            return true;
        case 'iauset':
            $parts = explode('~', $arg, 2);
            if (count($parts) === 2 && isset(maAutoLabels()[$parts[1]])) {
                maItemMutate($key, $parts[0], function (&$i) use ($parts) { $i['auto'] = $parts[1]; });
            }
            answerCb(BOT_TOKEN, $cbId, '✅');
            maAdminCallback('maadm_iau_' . $key . '|' . ($parts[0] ?? ''), $uid, $chatId, $msgId, $cbId);
            return true;
        case 'iaq': case 'iai':
            answerCb(BOT_TOKEN, $cbId);
            $mp = ['iaq' => ['auto_qty', '🔢 مقدار ارسالی به پنل (عدد):', '۰ = همان تعداد سفارش کاربر'],
                   'iai' => ['auto_id', '🏷 شناسه این سرویس در پنل:', 'برای حذف <code>-</code>']];
            [$f2, $t2, $h2] = $mp[$op];
            maAskState($uid, $chatId, 'ma_item_field', ['k' => $key, 'i' => $arg, 'f' => $f2], $t2, $h2);
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

    // ---- 🤖 تنظیمات تحویل خودکار ----
    if ($action === 'ma_ffcfg') {
        $f = (string)($sd['f'] ?? '');
        if ($f === 'base' || $f === 'spec_url') {
            $v = $dash ? '' : rtrim($plain, '/');
            if ($v !== '' && !preg_match('#^https://#i', $v)) {
                sendMsg(BOT_TOKEN, $chatId, '⚠️ آدرس باید با https:// شروع شود.'); return true;
            }
        } elseif (in_array($f, ['retry', 'timeout'], true)) {
            $v = (int)preg_replace('/\D/', '', norm_fa_digits($plain));
            if ($v < 1) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عدد معتبر بفرستید.'); return true; }
            if ($f === 'timeout') $v = min(60, $v);
            if ($f === 'retry')   $v = min(10, $v);
        } else {
            $v = $dash ? '' : $plain;
        }
        maSetRoot(function (&$m) use ($f, $v) { $m['fulfill'][$f] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'admin')]]));
        return true;
    }
    if ($action === 'ma_ffop') {
        $op2 = (string)($sd['op'] ?? '');
        $f   = (string)($sd['f'] ?? '');
        if ($f === 'method') {
            $v = strtoupper(trim($plain));
            if (!in_array($v, ['GET', 'POST'], true)) {
                sendMsg(BOT_TOKEN, $chatId, '⚠️ فقط GET یا POST.'); return true;
            }
        } else {
            $v = $dash ? '' : $plain;
            // قالب خودش JSON معتبر نیست ({qty} جای عدد است)؛ پس با مقدار نمونه پرش می‌کنیم
            if ($f === 'body' && $v !== '') {
                $probe = maFillTpl($v, ['username' => 'test', 'user' => 'test', 'qty' => 1,
                                        'amount' => 1, 'gift' => 'g1', 'order' => 'ma_1',
                                        'user_id' => '1', 'field' => 'test']);
                if (json_decode($probe, true) === null && strtolower(trim($probe)) !== 'null') {
                    sendMsg(BOT_TOKEN, $chatId,
                        "⚠️ بدنه بعد از جای‌گذاری، JSON معتبر نشد:\n<code>" . h(mb_substr($probe, 0, 200)) . "</code>\n\n" .
                        "متغیرهای عددی مثل <code>{qty}</code> نباید داخل کوتیشن باشند، " .
                        "و متغیرهای متنی مثل <code>{username}</code> باید داخل کوتیشن باشند.");
                    return true;
                }
            }
        }
        maSetRoot(function (&$m) use ($op2, $f, $v) { $m['fulfill']['ops'][$op2][$f] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.',
            inlineKb([[btnCb('⚙️ همین عملیات', 'maadm_ffop_' . $op2, 'admin')],
                      [btnCb('🤖 تحویل خودکار', 'maadm_fulfill', 'nav')]]));
        return true;
    }

    // ---- 🔌 تنظیمات قیمت‌گذاری ----
    if ($action === 'ma_pcfg') {
        $sec = (string)($sd['sec'] ?? '');
        $f   = (string)($sd['f'] ?? '');
        if (!in_array($sec, ['market', 'rates', 'stars'], true)) { clearState($uid); return true; }

        $numeric = in_array($f, ['margin', 'round', 'ttl', 'div', 'price'], true);
        if ($numeric) {
            $v = (float)str_replace([',', '،', ' '], '', norm_fa_digits($plain));
            if (!is_finite($v) || $v < 0) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عدد معتبر بفرستید.'); return true; }
        } elseif (str_ends_with($f, '_url') || $f === 'url') {
            $v = $dash ? '' : $plain;
            if ($v !== '' && !preg_match('#^https?://#i', $v)) {
                sendMsg(BOT_TOKEN, $chatId, '⚠️ آدرس باید با http:// یا https:// شروع شود.'); return true;
            }
        } elseif ($f === 'method') {
            $v = strtoupper(trim($plain));
            if (!in_array($v, ['GET', 'POST'], true)) {
                sendMsg(BOT_TOKEN, $chatId, '⚠️ فقط <code>GET</code> یا <code>POST</code>.'); return true;
            }
        } elseif ($f === 'price_cur') {
            $v = strtoupper(trim($plain));
            if (!in_array($v, ['TON', 'TRX', 'USDT', 'IRT'], true)) {
                sendMsg(BOT_TOKEN, $chatId, '⚠️ فقط TON یا TRX یا USDT یا IRT.'); return true;
            }
        } else {
            $v = $dash ? '' : $plain;
        }

        maSetRoot(function (&$m) use ($sec, $f, $v) { $m[$sec][$f] = $v; });
        save('ma_cache', []);          // تنظیمات عوض شد، کش قیمت باید دور ریخته شود
        clearState($uid);

        $back = ['market' => 'maadm_market', 'rates' => 'maadm_rates', 'stars' => 'maadm_starp'][$sec];
        $extra = '';
        if ($sec === 'rates' && str_ends_with($f, '_url')) {
            $w = str_replace('_url', '', $f);
            $rv = maRate($w, true);
            $extra = "\n\n" . ($rv > 0 ? "✅ نرخ خوانده شد: <b>" . fmtNum($rv) . "</b> تومان"
                                        : "⚠️ نرخ خوانده نشد — مسیر مقدار را بررسی کنید.");
        }
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.' . $extra,
            inlineKb([[btnCb('🔌 قیمت‌گذاری', $back, 'admin')]]));
        return true;
    }

    if ($action === 'ma_rowlay') {
        $v = trim(norm_fa_digits($plain));
        if (!parseLayout($v)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ چیدمان معتبر نیست. مثال: <code>2</code> یا <code>1,1</code>");
            return true;
        }
        maSetRoot(function (&$m) use ($v) { $m['row_layout'] = $v; });
        clearState($uid);
        $preview = [];
        foreach (maRows() as $r) {
            $line = [];
            foreach ($r as $btn) $line[] = trim((string)($btn['text'] ?? ''));
            $preview[] = implode('  |  ', $line);
        }
        sendMsg(BOT_TOKEN, $chatId,
            "✅ چیدمان ذخیره شد.\n\n<b>پیش‌نمایش:</b>\n" . h(implode("\n", $preview)),
            inlineKb([
                [btnCb('💠 دکمه‌های شیشه‌ای', 'sb_buy', 'admin')],
                [btnCb('🚀 مینی اپ‌ها', 'maadm_home', 'nav')],
            ]));
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
    if ($action === 'ma_btn_row') {
        $n = (int)preg_replace('/\D/', '', norm_fa_digits($plain));
        maSet($key, function (&$x) use ($n) { $x['btn']['row'] = max(0, $n); });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد.', $backApp);
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

    if ($action === 'ma_glay') {
        $v = trim(norm_fa_digits($plain));
        if (!parseLayout($v)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ چیدمان معتبر نیست. مثال: <code>2,1</code>");
            return true;
        }
        maSet($key, function (&$x) use ($v) { $x['glass_layout'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ چیدمان ذخیره شد.',
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

        $numeric = in_array($f, ['price', 'order', 'min', 'max', 'stars', 'auto_qty'], true);
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

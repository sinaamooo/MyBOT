<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  🐕  ربات هاپی  —  Happy Dog Bot  (PHP Edition)
 * ═══════════════════════════════════════════════════════════════════════════
 *  بازنویسی کامل ربات پایتونی با تمام قابلیت‌ها + قابلیت‌های جدید:
 *    • دکمه‌های شیشه‌ای رنگی (Glass Inline Buttons)
 *    • Quote / ریپلای روی پیام کاربر برای همه پاسخ‌ها
 *    • چالش دوز (XO) — «چالش ۵۰۰ میو»
 *
 *  اجرا:
 *    حالت پولینگ :  php bot.php
 *    حالت وبهوک  :  فایل را روی هاست بگذارید و setWebhook کنید
 *                   php bot.php --set-webhook=https://example.com/bot.php
 * ═══════════════════════════════════════════════════════════════════════════
 */

declare(strict_types=1);

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Tehran');
error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
set_time_limit(0);

// ═══════════════════════════════════════════════════════════════════════════
//  ⚙️  پیکربندی
// ═══════════════════════════════════════════════════════════════════════════

if (!defined('BOT_TOKEN'))    { define('BOT_TOKEN',    'توکن بات'); }
if (!defined('BOT_USERNAME')) { define('BOT_USERNAME', 'یوزرنیم بات'); }

/** آیدی عددی ادمین‌های اصلی */
if (!defined('ADMIN_IDS')) { define('ADMIN_IDS', [123456789]); }

if (!defined('DB_FILE'))  { define('DB_FILE',  __DIR__ . '/happy_bot.db'); }
if (!defined('LOG_FILE')) { define('LOG_FILE', __DIR__ . '/bot.log'); }

/** کانال اجباری ثابت */
const FORCE_JOIN_CHANNEL      = 'یوزرنیم کانال';
const FORCE_JOIN_CHANNEL_LINK = 'https://t.me/کانال';
const FORCE_JOIN_CHANNEL_NAME = 'اسم کانال';
/** اگر false باشد جوین اجباری کلاً غیرفعال است */
const FORCE_JOIN_ENABLED = false;

/** مکث قبل از بازگشت خودکار به پنل (ثانیه) — 0 یعنی بدون مکث */
if (!defined('PANEL_RETURN_DELAY')) { define('PANEL_RETURN_DELAY', 2); }

// ── دعوت دوستان ──────────────────────────────────────────────────────────
const REFERRAL_ENABLED       = true;
const REFERRAL_REWARD_SENDER = 5000;
const REFERRAL_REWARD_JOINER = 1000;

// ── هاپ ──────────────────────────────────────────────────────────────────
const HOP_COOLDOWN    = 300;
const HOP_BASE_POINTS = 50;
const HOP_LEVEL_BONUS = 0.20;

// ── شهر ──────────────────────────────────────────────────────────────────
/** سطح => [خزانه, کل‌هاپ, کل‌سگ, کل‌استخوان, کل‌ماهی] */
const CITY_LEVELS = [
    1  => [0,          0,     0,    0,     0],
    2  => [5000,       50,    5,    10,    5],
    3  => [20000,      150,   15,   30,    15],
    4  => [60000,      400,   35,   80,    40],
    5  => [150000,     900,   80,   200,   100],
    6  => [400000,     2000,  180,  500,   250],
    7  => [1000000,    5000,  400,  1200,  600],
    8  => [3000000,    12000, 900,  3000,  1500],
    9  => [8000000,    30000, 2000, 8000,  4000],
    10 => [20000000,   80000, 5000, 20000, 10000],
];
const CITY_MAX_LEVEL   = 10;
const CITY_HOP_BUFF    = 5;
const CITY_FISH_BUFF   = 30;
const CITY_STRAY_BUFF  = 0.05;

// ── سگ ───────────────────────────────────────────────────────────────────
const DOG_MIN_LEVEL = 3;
const DOG_COST      = 1000;
const DOG_MAX_LEVEL = 20;
/** سطح => [نرخ تولید بر ثانیه, ظرفیت جعبه, هزینه ارتقا] */
const DOG_LEVEL_DATA = [
    1  => [0.10,  5000,    1000],
    2  => [0.20,  10000,   2400],
    3  => [0.35,  18000,   5000],
    4  => [0.55,  28000,   9000],
    5  => [0.80,  40000,   16000],
    6  => [1.10,  55000,   26000],
    7  => [1.50,  75000,   40000],
    8  => [2.00,  100000,  60000],
    9  => [2.60,  130000,  90000],
    10 => [3.30,  170000,  130000],
    11 => [4.10,  215000,  180000],
    12 => [5.00,  265000,  240000],
    13 => [6.00,  320000,  316000],
    14 => [7.20,  380000,  410000],
    15 => [8.50,  450000,  520000],
    16 => [10.00, 530000,  650000],
    17 => [11.80, 620000,  800000],
    18 => [13.80, 720000,  980000],
    19 => [16.00, 830000,  1180000],
    20 => [18.50, 950000,  1400000],
];
/** مقام => [نام, ضریب] */
const DOG_RANKS = [
    1  => ['سگ خیابونی 🐾',    1.0],
    2  => ['سگ تازه‌کار 🐶',    1.3],
    3  => ['سگ باتجربه 🐕',     1.6],
    4  => ['سگ آموزش‌دیده 🦮',  2.0],
    5  => ['سگ ماهر 🐕‍🦺',       2.5],
    6  => ['سگ نخبه 🌟🐕',      3.2],
    7  => ['سگ قهرمان 🏆🐕',    4.0],
    8  => ['سگ اسطوره 💎🐕',    5.0],
    9  => ['سگ افسانه‌ای 👑🐕',  6.5],
    10 => ['سگ جاودان ✨👑🐕',  8.5],
];
const DOG_MAX_RANK  = 10;
const BONE_COST     = 14000;
const FEED_DURATION = 7200;

/** مدت سیری پایه بر اساس کمیابی (ثانیه) */
const FEED_BASE_BY_RARITY = [
    '⚪' => 600,  '🟢' => 1500, '🔵' => 2700, '🟣' => 3900,
    '🟠' => 5100, '🔴' => 6300, '🔥' => 7200,
];
const FEED_WEIGHT_BONUS = 900;

// ── قلاب و استخوان ───────────────────────────────────────────────────────
const HOOK_MIN_LEVEL = 2;
const HOOK_MAX_LEVEL = 15;
/** سطح => [هزینه خرید, کولداون, هزینه ارتقا به سطح بعد] */
const HOOK_DATA = [
    1  => [600, 3600, 0],
    2  => [0,   3300, 1600],
    3  => [0,   2700, 3600],
    4  => [0,   2400, 7000],
    5  => [0,   2100, 12000],
    6  => [0,   1800, 20000],
    7  => [0,   1500, 32000],
    8  => [0,   1200, 50000],
    9  => [0,   1050, 76000],
    10 => [0,   900,  110000],
    11 => [0,   750,  160000],
    12 => [0,   600,  230000],
    13 => [0,   480,  320000],
    14 => [0,   360,  440000],
    15 => [0,   300,  600000],
];
const BONE_SELL_TIME = 120;

const RARITY_ORDER    = ['⚪', '🟢', '🔵', '🟣', '🟠', '🔴', '🔥'];
const RARITY_ICON_MAP = [
    '⚪' => 'عادی',      '🟢' => 'غیرمعمول', '🔵' => 'نادر',
    '🟣' => 'حماسی',     '🟠' => 'اسطوره‌ای', '🔴' => 'کمیاب',
    '🔥' => 'آتشی',
];
/** سطح قلاب => وزن شانس هر کمیابی به ترتیب RARITY_ORDER */
const RARITY_WEIGHTS_BY_LEVEL = [
    1  => [100, 0,  0,  0,  0,  0,  0],
    2  => [90,  10, 0,  0,  0,  0,  0],
    3  => [75,  20, 5,  0,  0,  0,  0],
    4  => [65,  22, 10, 3,  0,  0,  0],
    5  => [55,  25, 13, 5,  2,  0,  0],
    6  => [48,  25, 15, 8,  4,  0,  0],
    7  => [40,  24, 18, 11, 5,  2,  0],
    8  => [35,  22, 18, 13, 7,  4,  1],
    9  => [30,  21, 18, 14, 9,  5,  3],
    10 => [28,  20, 17, 14, 10, 7,  4],
    11 => [25,  20, 16, 14, 11, 8,  6],
    12 => [22,  19, 16, 14, 12, 10, 7],
    13 => [20,  18, 15, 14, 13, 11, 9],
    14 => [17,  17, 15, 14, 13, 13, 11],
    15 => [15,  15, 14, 13, 13, 13, 17],
];
/** [نام, حداقل وزن, حداکثر وزن, حداقل سطح قلاب, قیمت پایه, کمیابی] */
const BONES_TABLE = [
    ['استخوان کوچیک 🦴',       0.1,  0.3,  1, 50,  '⚪'],
    ['استخوان مرغ 🍗',         0.2,  0.5,  1, 80,  '⚪'],
    ['استخوان گربه 🐱',        0.2,  0.6,  1, 110, '⚪'],
    ['استخوان سگ 🐩',          0.3,  0.8,  1, 150, '⚪'],
    ['استخوان گاو 🐄',         0.4,  1.2,  1, 200, '⚪'],
    ['استخوان خوک 🐷',         0.5,  1.5,  1, 250, '⚪'],
    ['استخوان بز 🐐',          0.5,  1.6,  1, 280, '⚪'],
    ['استخوان گوسفند 🐑',      0.6,  1.8,  2, 320, '⚪'],
    ['استخوان دنده 🦷',        0.7,  2.0,  2, 370, '⚪'],
    ['استخوان ران 🦵',         0.8,  2.3,  2, 430, '⚪'],
    ['استخوان آهو 🦌',         1.0,  2.8,  2, 500, '⚪'],
    ['استخوان گراز 🐗',        1.1,  3.0,  2, 560, '⚪'],
    ['استخوان اسب 🐴',         0.9,  2.5,  2, 520, '⚪'],
    ['استخوان خرگوش 🐇',       0.1,  0.3,  1, 70,  '⚪'],
    ['استخوان روباه 🦊',       0.4,  1.0,  1, 170, '⚪'],
    ['استخوان خرس 🐻',          1.3,  3.5,  3, 750,   '🟢'],
    ['استخوان پلنگ 🐆',         1.5,  4.0,  3, 950,   '🟢'],
    ['استخوان کروکودیل 🐊',     1.8,  4.5,  3, 1200,  '🟢'],
    ['استخوان شیر 🦁',          2.0,  5.0,  3, 1500,  '🟢'],
    ['استخوان کرگدن 🦏',        2.2,  5.5,  3, 1800,  '🟢'],
    ['استخوان فیل 🐘',          2.5,  6.0,  4, 2200,  '🟢'],
    ['استخوان دایناسور 🦕',     2.8,  6.5,  4, 2700,  '🟢'],
    ['استخوان ماستادون 🦣',     3.2,  7.5,  4, 3300,  '🟢'],
    ['استخوان گوریل غول‌پیکر 🦍', 3.5, 8.0,  4, 4000,  '🟢'],
    ['استخوان هیپوپوتاموس 🦛',  2.8,  7.0,  4, 3600,  '🟢'],
    ['استخوان عقاب طلایی 🦅',   1.8,  4.8,  3, 1600,  '🟢'],
    ['استخوان نهنگ 🐋',          4.0,  9.0,  5, 5000,  '🔵'],
    ['استخوان کوسه 🦈',          4.5,  10.0, 5, 6200,  '🔵'],
    ['استخوان مارماهی غول‌آسا 🐍', 5.0, 11.0, 5, 7500, '🔵'],
    ['استخوان کراکن 🐙',         5.5,  12.0, 5, 9000,  '🔵'],
    ['استخوان ماموت 🦣',         6.0,  13.0, 6, 11000, '🔵'],
    ['استخوان گرگ عظیم 🐺',      6.5,  14.0, 6, 13500, '🔵'],
    ['استخوان ببر دندان‌شمشیری 🐯', 7.0, 15.0, 6, 16000, '🔵'],
    ['استخوان مگالودون 🦷',      7.5,  16.0, 6, 19000, '🔵'],
    ['استخوان اژدمار دریایی 🌊', 6.8,  14.5, 6, 17000, '🔵'],
    ['استخوان غول غار 🗿',       5.8,  12.5, 5, 8200,  '🔵'],
    ['استخوان غول 👾',           8.0,  17.0, 7, 23000,  '🟣'],
    ['استخوان سیمرغ 🦅',         9.0,  19.0, 7, 28000,  '🟣'],
    ['استخوان گریفین 🦁',        10.0, 21.0, 7, 34000,  '🟣'],
    ['استخوان هیدرا 🐲',         11.0, 23.0, 7, 41000,  '🟣'],
    ['استخوان اژدها 🐉',         12.0, 25.0, 8, 50000,  '🟣'],
    ['استخوان ققنوس 🔥',         13.5, 28.0, 8, 60000,  '🟣'],
    ['استخوان لویاتان 🌊',       15.0, 31.0, 8, 72000,  '🟣'],
    ['استخوان فضایی 🛸',         16.5, 34.0, 9, 86000,  '🟣'],
    ['استخوان موجود فضایی 👽',   18.0, 37.0, 9, 102000, '🟣'],
    ['استخوان بهموت 🔱',         20.0, 40.0, 9, 120000, '🟣'],
    ['استخوان اژدهای یخ 🧊',     14.0, 29.0, 8, 65000,  '🟣'],
    ['استخوان ققنوس تاریک 🖤',   17.0, 35.0, 9, 95000,  '🟣'],
    ['استخوان افسانه‌ای ✨',      23.0, 46.0,  10, 145000, '🟠'],
    ['استخوان تایتان ⚙️',        26.0, 52.0,  10, 175000, '🟠'],
    ['استخوان خدایان 👑',        30.0, 60.0,  10, 210000, '🟠'],
    ['استخوان دیو باستانی 🏺',   34.0, 68.0,  11, 250000, '🟠'],
    ['استخوان کیهانی 🌌',        38.0, 76.0,  11, 300000, '🟠'],
    ['استخوان سیاهچاله 🕳️',      43.0, 86.0,  11, 360000, '🟠'],
    ['استخوان اهریمن 😈',        48.0, 95.0,  12, 430000, '🟠'],
    ['استخوان شیطان بزرگ 🩸',    54.0, 105.0, 12, 510000, '🟠'],
    ['استخوان نمرود 🏛️',         60.0, 115.0, 12, 600000, '🟠'],
    ['استخوان جن عظیم 🧿',       42.0, 82.0,  11, 340000, '🟠'],
    ['استخوان ستاره مرده ⭐',     56.0, 108.0, 12, 570000, '🟠'],
    ['استخوان فرشته 😇',         68.0,  128.0, 13, 700000,  '🔴'],
    ['استخوان سرافیم 🌠',        76.0,  144.0, 13, 850000,  '🔴'],
    ['استخوان آغازین ⚡️',        85.0,  160.0, 13, 1020000, '🔴'],
    ['استخوان ازلی 🌀',          95.0,  180.0, 14, 1220000, '🔴'],
    ['استخوان خالق 🌟',          108.0, 200.0, 14, 1450000, '🔴'],
    ['استخوان عرش 🕊️',           122.0, 225.0, 14, 1720000, '🔴'],
    ['استخوان ملک‌الموت ☠️',      72.0,  136.0, 13, 790000,  '🔴'],
    ['استخوان نور ابدی 💫',      100.0, 190.0, 14, 1350000, '🔴'],
    ['استخوان آتش ازلی 🔥',      140.0, 260.0, 15, 2100000, '🔥'],
    ['استخوان فنا 💀',           162.0, 300.0, 15, 2600000, '🔥'],
    ['استخوان هستی ☀️',          188.0, 350.0, 15, 3200000, '🔥'],
    ['استخوان خدای خدایان 🌋',   220.0, 420.0, 15, 4200000, '🔥'],
    ['استخوان اژدهای کیهانی 🌠', 250.0, 480.0, 15, 5500000, '🔥'],
];

// ── بانک ─────────────────────────────────────────────────────────────────
const BANK_MIN_LEVEL       = 4;
const BANK_OPEN_COST       = 10000;
const BANK_INTEREST_RATE   = 0.03;
const BANK_MAX_INTEREST    = 325000;
const BANK_NUM_CHANGE_COST = 1250;
const BANK_NUM_CHANGE_CD   = 259200; // 72h

// ── انتقال ───────────────────────────────────────────────────────────────
const TRANSFER_MIN       = 50;
const TRANSFER_MAX       = 500000;
const TRANSFER_MIN_LEVEL = 2;
const TRANSFER_COOLDOWN  = 30;

// ── سگ خیابونی ───────────────────────────────────────────────────────────
const STRAY_CHANCE       = 0.30;
const STRAY_MAX_TRIES    = 3;
const STRAY_BASE_COST    = 600;
const STRAY_COST_MULT    = 2.0;
const STRAY_TRIGGER_HOPS = 20;

// ── زندان ────────────────────────────────────────────────────────────────
const JAIL_DURATION       = 1800;
const BAIL_COST_PER_MIN   = 100;
const JAIL_WORK_INTERVAL  = 300;
const JAIL_WORK_EARN      = 50;
const JAIL_ESCAPE_CHANCE  = 0.25;
const JAIL_ESCAPE_PENALTY = 900;

// ── اسپم ─────────────────────────────────────────────────────────────────
const SPAM_WINDOW    = 3;
const SPAM_THRESHOLD = 3;
const SPAM_JAIL_BASE = 300;

// ── قاچاق ────────────────────────────────────────────────────────────────
const SMUGGLE_MIN_LEVEL   = 4;
const SMUGGLE_MIN_STRAYS  = 3;
const SMUGGLE_BASE_CATCH  = 0.10;
const SMUGGLE_CATCH_PER   = 0.10;
const SMUGGLE_REWARD_EACH = 800;
const SMUGGLE_JAIL_MINS   = 30;

// ── کارخانه ──────────────────────────────────────────────────────────────
const FACTORY_MIN_LEVEL   = 7;
const FACTORY_BUILD_COST  = 20000;
const FACTORY_WORKER_COST = 5000;
const FACTORY_MAX_LEVEL   = 10;
const FACTORY_DAILY_CAP   = 5000000;
const FACTORY_SELL_TAX    = 0.10;

/** سطح => [ظرفیت, هزینه ارتقا] */
const WAREHOUSE_LEVELS = [
    1 => [20, 0],     2 => [40, 6000],     3 => [80, 16000],
    4 => [150, 36000], 5 => [280, 80000],  6 => [500, 180000],
    7 => [900, 400000], 8 => [1600, 900000], 9 => [2800, 2000000],
    10 => [5000, 5000000],
];
const WAREHOUSE_MAX_LEVEL = 10;

/** سطح => [ثانیه تولید, هزینه ارتقا] */
const MACHINE_LEVELS = [
    1 => [120, 0],    2 => [90, 10000],   3 => [70, 24000],
    4 => [55, 50000], 5 => [42, 110000],  6 => [32, 240000],
    7 => [24, 520000], 8 => [18, 1160000], 9 => [13, 2600000],
    10 => [9, 6000000],
];
const MACHINE_MAX_LEVEL = 10;

const FACTORY_LEVEL_EXP = [
    1 => 100, 2 => 300, 3 => 700, 4 => 1500, 5 => 3000,
    6 => 6000, 7 => 12000, 8 => 25000, 9 => 50000, 10 => 0,
];

/** [نام, هزینه تولید, قیمت پایه فروش, حداقل سطح کارخانه, تجربه] */
const FACTORY_PRODUCTS = [
    ['نخ میویی 🧵',            200,    400,    1,  5],
    ['پشمک پیشی 🍭',           350,    700,    1,  8],
    ['چرم گربه 🐾',            500,    950,    1,  10],
    ['جوراب پیشی 🧦',          800,    1600,   2,  16],
    ['کلاه میویی 🎩',          1200,   2400,   2,  22],
    ['عطر پیشی 🌸',            2000,   4200,   3,  38],
    ['صابون میویی 🧼',         1500,   3100,   3,  28],
    ['کنسرو ماهی 🐟',          3000,   6500,   4,  58],
    ['شامپو پشمالو 🛁',        2500,   5200,   4,  46],
    ['ابزار چنگول 🔧',         5000,   11000,  5,  95],
    ['دستکش میویی 🧤',         4000,   8500,   5,  76],
    ['باتری پیشی ⚡',          8000,   17500,  6,  150],
    ['چیپ میویی 💾',           12000,  26000,  6,  220],
    ['ربات پیشی 🤖',           20000,  45000,  7,  380],
    ['موشک میویی 🚀',          35000,  78000,  7,  650],
    ['فضاپیمای پشمالو 🛸',     60000,  45000,  8,  1100],
    ['کریستال میویی 💎',       80000,  60000,  8,  1500],
    ['پورتال پیشی 🌀',         150000, 113000, 9,  2800],
    ['قلب میویی افسانه‌ای ✨',  300000, 230000, 10, 6000],
];
const MARKET_PRICE_MIN = 0.6;
const MARKET_PRICE_MAX = 2.2;

// ── بحران شهری ───────────────────────────────────────────────────────────
const CRISIS_TRIGGER_CHANCE = 0.08;
const CRISIS_MIN_TREASURY   = 2000;
const CRISIS_TYPES = [
    'fire' => [
        'name' => '🔥 آتش‌سوزی',
        'desc' => 'بخشی از شهر آتش گرفته! باید سریع اقدام کنی.',
        'penalty' => 'کاهش ۲۰٪ خزانه و ضعف ۳۰ دقیقه کولداون هاپ',
        'treasury_cost' => 0.15, 'fix_cooldown' => 1800,
        'penalty_type' => 'hop_cooldown', 'penalty_value' => 60,
        'reward' => 8000, 'timeout' => 600,
    ],
    'blackout' => [
        'name' => '⚡ قطعی برق',
        'desc' => 'برق کل شهر قطع شده! کارخانه‌ها متوقف شدن.',
        'penalty' => 'توقف تولید کارخانه‌ها به مدت ۴۵ دقیقه',
        'treasury_cost' => 0.10, 'fix_cooldown' => 2700,
        'penalty_type' => 'factory_freeze', 'penalty_value' => 2700,
        'reward' => 6000, 'timeout' => 600,
    ],
    'pollution' => [
        'name' => '☣️ آلودگی شهر',
        'desc' => 'آلودگی شدید! سگ‌ها نمی‌تونن کار کنن.',
        'penalty' => 'توقف درآمد سگ‌ها به مدت ۱ ساعت',
        'treasury_cost' => 0.08, 'fix_cooldown' => 3600,
        'penalty_type' => 'dog_freeze', 'penalty_value' => 3600,
        'reward' => 5000, 'timeout' => 600,
    ],
    'factory_breakdown' => [
        'name' => '🏭 خرابی کارخانه‌ها',
        'desc' => 'ماشین‌آلات کارخانه‌ها خراب شدن!',
        'penalty' => '۵۰٪ کاهش سرعت تولید برای ۱ ساعت',
        'treasury_cost' => 0.12, 'fix_cooldown' => 3600,
        'penalty_type' => 'factory_slow', 'penalty_value' => 3600,
        'reward' => 7000, 'timeout' => 600,
    ],
    'dog_disease' => [
        'name' => '🤒 بیماری سگ‌ها',
        'desc' => 'یه بیماری واگیر بین سگ‌های شهر پخش شده!',
        'penalty' => '۳۰٪ کاهش درآمد سگ‌ها برای ۴۵ دقیقه',
        'treasury_cost' => 0.06, 'fix_cooldown' => 2700,
        'penalty_type' => 'dog_slow', 'penalty_value' => 2700,
        'reward' => 4000, 'timeout' => 600,
    ],
];

// ── بازی‌ها و کازینو ─────────────────────────────────────────────────────
const GAME_MIN_LEVEL     = 2;
const TABLE_MIN_LEVEL    = 3;
const CASINO_MIN_LEVEL   = 4;
const CASINO_TABLE_LEVEL = 5;
const XO_COOLDOWN        = 600;
const DICE_COOLDOWN      = 600;
const WHEEL_COOLDOWN     = 600;
const GAMBLE_COOLDOWN    = 600;
const XO_TIMEOUT         = 60;
const TABLE_WAIT_TIMEOUT = 60;

// ── 🆕 چالش دوز ──────────────────────────────────────────────────────────
const CHALLENGE_MIN_LEVEL   = 2;      // حداقل سطح برای ساخت/شرکت در چالش
const CHALLENGE_MIN_BET     = 100;    // حداقل مبلغ چالش
const CHALLENGE_MAX_BET     = 5000000;// حداکثر مبلغ چالش
const CHALLENGE_JOIN_WINDOW = 180;    // مهلت پیوستن حریف (ثانیه)
const CHALLENGE_TURN_TIME   = 90;     // مهلت هر نوبت (ثانیه)
const CHALLENGE_COOLDOWN    = 60;     // فاصله بین دو چالش هر کاربر
const CHALLENGE_WIN_BONUS   = 0.05;   // ۵٪ پاداش اضافی برای برنده

// ── شهردار ───────────────────────────────────────────────────────────────
const MAYOR_TERM_DAYS = 7;
const MAYOR_DECREES_DEF = [
    'hop_festival'  => ['name' => '🎉 جشنواره هاپ',           'desc' => '+۳۰٪ پوینت هاپ برای کل گروه',       'effect' => 'hop_boost',     'value' => 0.30],
    'factory_boost' => ['name' => '🏭 افزایش تولید کارخانه',  'desc' => '+۴۰٪ سرعت تولید کارخانه',           'effect' => 'factory_boost', 'value' => 0.40],
    'dog_income'    => ['name' => '🐕 افزایش درآمد سگ‌ها',    'desc' => '+۳۵٪ درآمد سگ‌های گروه',            'effect' => 'dog_boost',     'value' => 0.35],
    'tax_cut'       => ['name' => '💰 کاهش مالیات شهر',       'desc' => 'اهدای ۵٪ خزانه به همه کاربران',     'effect' => 'tax_cut',       'value' => 0.05],
    'bank_profit'   => ['name' => '🏦 افزایش سود بانک',       'desc' => '+۵۰٪ سود بانکی امروز',              'effect' => 'bank_boost',    'value' => 0.50],
    'daily_prize'   => ['name' => '🎁 رویداد جایزه روزانه',   'desc' => 'جایزه تصادفی ۵۰۰-۵۰۰۰ برای هر هاپ', 'effect' => 'daily_prize',   'value' => 0],
];
const MAYOR_DECREE_DURATION = 21600; // 6h
const MAYOR_PLEDGES_DEF = [
    'tax_cut'       => 'کاهش مالیات (اجرای فرمان کاهش مالیات)',
    'build_project' => 'ساخت پروژه جدید (ساخت حداقل یک پروژه)',
    'bank_boost'    => 'افزایش سود بانک (اجرای فرمان سود بانک)',
    'hop_festival'  => 'برگزاری جشنواره (اجرای فرمان جشنواره هاپ)',
    'factory_up'    => 'افزایش تولید (اجرای فرمان تولید کارخانه)',
];
const CITY_PROJECTS = [
    'bank'    => ['name' => '🏦 توسعه بانک',   'desc' => '+۱۵٪ سود بانک',            'effect' => 'bank_boost',    'build_hours' => 24, 'max_level' => 3, 'value' => 0.15],
    'factory' => ['name' => '🏭 منطقه صنعتی',  'desc' => '+۲۰٪ تولید کارخانه',       'effect' => 'factory_boost', 'build_hours' => 24, 'max_level' => 3, 'value' => 0.20],
    'dog'     => ['name' => '🐕 پناهگاه سگ‌ها', 'desc' => '+۲۰٪ درآمد سگ',           'effect' => 'dog_boost',     'build_hours' => 12, 'max_level' => 3, 'value' => 0.20],
    'park'    => ['name' => '🌳 پارک شهر',      'desc' => '+۱۰٪ پاداش فعالیت روزانه', 'effect' => 'daily_boost',   'build_hours' => 18, 'max_level' => 2, 'value' => 0.10],
    'power'   => ['name' => '⚡ نیروگاه',       'desc' => '-۲۰٪ زمان انتظار',         'effect' => 'cooldown_cut',  'build_hours' => 36, 'max_level' => 2, 'value' => 0.20],
];
const CITY_CONTRACTS = [
    'industrial' => ['name' => '🏭 قرارداد صنعتی', 'pros' => '+۲۰٪ تولید کارخانه', 'cons' => '-۱۰٪ سود بانک',     'pro_effect' => 'factory_boost', 'con_effect' => 'bank_cut',    'pro_value' => 0.20, 'con_value' => 0.10],
    'banking'    => ['name' => '🏦 قرارداد بانکی',  'pros' => '+۱۵٪ سود بانک',      'cons' => '-۵٪ درآمد هاپ',     'pro_effect' => 'bank_boost',    'con_effect' => 'hop_cut',     'pro_value' => 0.15, 'con_value' => 0.05],
    'welfare'    => ['name' => '🤝 قرارداد رفاهی',  'pros' => '+۱۰٪ درآمد سگ',      'cons' => '-۵٪ تولید کارخانه', 'pro_effect' => 'dog_boost',     'con_effect' => 'factory_cut', 'pro_value' => 0.10, 'con_value' => 0.05],
];
const PROJECT_DEFAULT_COST = 50000;
const CONTRACT_DEFAULT_MIN_DAYS = 3;

// ── رهبر کشور ────────────────────────────────────────────────────────────
const LEADER_COMMAND_COOLDOWN = 86400;
const LEADER_COMMAND_DURATION = 43200;
const LEADER_TERM_DAYS        = 30;
const LEADER_ELECTION_DAYS    = 3;
const LEADER_MIN_CANDIDATES   = 1;

const NATIONAL_COMMANDS = [
    'hop_boost'     => ['name' => '🦴 افزایش درآمد هاپ',          'key' => 'hop_mult',     'val' => 1.5,  'desc' => 'درآمد هاپ همه بازیکنان ۵۰٪ بیشتر میشه'],
    'factory_boost' => ['name' => '🏭 افزایش تولید کارخانه‌ها',   'key' => 'factory_mult', 'val' => 1.4,  'desc' => 'سرعت تولید کارخانه‌ها ۴۰٪ بیشتر میشه'],
    'dog_boost'     => ['name' => '🐕 افزایش درآمد سگ‌ها',         'key' => 'dog_mult',     'val' => 1.5,  'desc' => 'درآمد سگ‌ها ۵۰٪ بیشتر میشه'],
    'bank_boost'    => ['name' => '🏦 افزایش سود بانک',            'key' => 'bank_mult',    'val' => 1.3,  'desc' => 'سود بانک ۳۰٪ بیشتر میشه'],
    'tax_cut'       => ['name' => '💸 کاهش مالیات',                'key' => 'tax_mult',     'val' => 0.5,  'desc' => 'مالیات کشور ۵۰٪ کمتر میشه'],
    'daily_bonus'   => ['name' => '🎁 افزایش جایزه مأموریت روزانه','key' => 'daily_bonus',  'val' => 500,  'desc' => '+۵۰۰ جایزه اضافه به هاپ روزانه'],
    'cd_cut'        => ['name' => '⚡ کاهش کولداون',               'key' => 'cd_mult',      'val' => 0.8,  'desc' => 'زمان انتظار ۲۰٪ کمتر میشه'],
    'all_boost'     => ['name' => '✨ افزایش درآمد همه مشاغل',     'key' => 'all_mult',     'val' => 1.25, 'desc' => 'کل درآمدها ۲۵٪ بیشتر میشه'],
];
const NATIONAL_LAWS = [
    'tax_5'         => ['name' => '⚖️ مالیات کشور ۵٪',      'key' => 'tax_rate',        'val' => 0.05],
    'tax_2'         => ['name' => '⚖️ مالیات کشور ۲٪',      'key' => 'tax_rate',        'val' => 0.02],
    'bank_bonus'    => ['name' => '🏦 افزایش سود بانک',      'key' => 'bank_rate_bonus', 'val' => 0.01],
    'factory_bonus' => ['name' => '🏭 درآمد کارخانه بیشتر', 'key' => 'factory_rate',    'val' => 1.15],
    'upgrade_disc'  => ['name' => '🔧 کاهش هزینه ارتقا',     'key' => 'upgrade_disc',    'val' => 0.85],
    'item_disc'     => ['name' => '🛒 کاهش هزینه آیتم',      'key' => 'item_disc',       'val' => 0.90],
];
const NATIONAL_PROJECTS = [
    'central_bank'    => ['name' => '🏦 بانک مرکزی',   'cost' => 5000000,  'build_hours' => 24, 'ekey' => 'bank_mult',    'eval' => 1.2,  'desc' => 'سود بانک ۲۰٪ بیشتر میشه'],
    'industrial_zone' => ['name' => '🏭 شهرک صنعتی',   'cost' => 8000000,  'build_hours' => 36, 'ekey' => 'factory_mult', 'eval' => 1.3,  'desc' => 'تولید کارخانه ۳۰٪ بیشتر میشه'],
    'airport'         => ['name' => '🛫 فرودگاه',       'cost' => 12000000, 'build_hours' => 48, 'ekey' => 'hop_mult',     'eval' => 1.15, 'desc' => 'درآمد هاپ ۱۵٪ بیشتر میشه'],
    'seaport'         => ['name' => '🚢 بندر',          'cost' => 10000000, 'build_hours' => 42, 'ekey' => 'trade_mult',   'eval' => 1.25, 'desc' => 'درآمد تجارت ۲۵٪ بیشتر میشه'],
    'powerplant'      => ['name' => '⚡ نیروگاه ملی',  'cost' => 6000000,  'build_hours' => 30, 'ekey' => 'cd_mult',      'eval' => 0.85, 'desc' => 'کولداون همه ۱۵٪ کمتر میشه'],
    'tech_center'     => ['name' => '📡 مرکز فناوری',  'cost' => 15000000, 'build_hours' => 72, 'ekey' => 'all_mult',     'eval' => 1.1,  'desc' => 'درآمد همه مشاغل ۱۰٪ بیشتر میشه'],
];
const MINISTER_ROLES = [
    'economy'     => ['name' => 'وزیر اقتصاد 💹',   'ekey' => 'all_mult',     'eval' => 1.05, 'desc' => '+۵٪ درآمد کل کشور'],
    'industry'    => ['name' => 'وزیر صنعت 🏭',     'ekey' => 'factory_mult', 'eval' => 1.10, 'desc' => '+۱۰٪ تولید کارخانه'],
    'bank'        => ['name' => 'وزیر بانک 🏦',     'ekey' => 'bank_mult',    'eval' => 1.03, 'desc' => '+۳٪ سود بانک'],
    'agriculture' => ['name' => 'وزیر کشاورزی 🌾',  'ekey' => 'dog_mult',     'eval' => 1.08, 'desc' => '+۸٪ درآمد سگ‌ها'],
    'livestock'   => ['name' => 'وزیر دامداری 🐄',  'ekey' => 'dog_mult',     'eval' => 1.06, 'desc' => '+۶٪ درآمد سگ‌ها'],
    'trade'       => ['name' => 'وزیر تجارت 🤝',    'ekey' => 'hop_mult',     'eval' => 1.08, 'desc' => '+۸٪ درآمد هاپ'],
];
const NATIONAL_EVENTS = [
    'hop_festival'  => ['name' => '🎉 جشنواره هاپ',   'hours' => 6,  'ekey' => 'hop_mult',     'eval' => 2.0, 'cost' => 500000],
    'industry_week' => ['name' => '🏭 هفته صنعت',      'hours' => 24, 'ekey' => 'factory_mult', 'eval' => 1.5, 'cost' => 800000],
    'dog_party'     => ['name' => '🐕 جشن سگ‌ها',      'hours' => 6,  'ekey' => 'dog_mult',     'eval' => 2.0, 'cost' => 400000],
    'login_bonus'   => ['name' => '🎁 پاداش ورود',     'hours' => 12, 'ekey' => 'daily_bonus',  'eval' => 1000,'cost' => 300000],
    'golden_friday' => ['name' => '🌟 جمعه طلایی',     'hours' => 8,  'ekey' => 'all_mult',     'eval' => 1.5, 'cost' => 1000000],
    'double_income' => ['name' => '💰 دو برابر درآمد', 'hours' => 4,  'ekey' => 'all_mult',     'eval' => 2.0, 'cost' => 1500000],
];
const EMERGENCY_TYPES = [
    'war'       => ['name' => '⚔️ جنگ',             'hours' => 12, 'tax_mult' => 0.3, 'all_mult' => 0.8],
    'recession' => ['name' => '📉 رکود اقتصادی',    'hours' => 24, 'tax_mult' => 0.5, 'factory_mult' => 0.7],
    'crisis'    => ['name' => '💥 بحران مالی',       'hours' => 12, 'bank_mult' => 0.5, 'all_mult' => 0.9],
    'special'   => ['name' => '🚨 وضعیت ویژه',      'hours' => 6,  'cd_mult' => 0.7,  'all_mult' => 1.1],
];

// ── جواب‌های «میو» ───────────────────────────────────────────────────────
const MEOW_REPLIES = [
    'اینجا قلمرو هاپوهاست، صدای گربه میاد بار آخرت باشه! 🐕☠️',
    'اشتباهی اومدی داداش، گربه‌ها رو اینجا سگ‌خور می‌کنیم! 🐕',
    'میو؟ مگه تو هاپو نیستی؟ داری جاسوسی گربه‌ها رو می‌کنی؟ 🤨',
    'یک بار دیگه صدا گربه در بیاری بچه‌ها می‌فرستنت انفرادی! 😾',
];
const MEOW_VOTE_NEEDED  = 3;
const MEOW_JAIL_MINUTES = 15;
const MEOW_VOTE_WINDOW  = 60;

// ── مالیات ثروتمندان ─────────────────────────────────────────────────────
const RICH_TAX_THRESHOLD = 600000000;
const RICH_TAX_RATE      = 0.01;

// ═══════════════════════════════════════════════════════════════════════════
//  📊  آستانه‌های سطح (۱۰۰۰ سطح)
// ═══════════════════════════════════════════════════════════════════════════

function levelThresholds(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    $hops = 10;
    for ($i = 0; $i < 1000; $i++) {
        $cache[] = $hops;
        if ($i < 9)       { $hops += 20; }
        elseif ($i < 49)  { $hops += 50; }
        elseif ($i < 199) { $hops += 100; }
        else              { $hops += 200; }
    }
    return $cache;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🗄  لایه دیتابیس
// ═══════════════════════════════════════════════════════════════════════════

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 15,
    ]);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA synchronous=NORMAL');
    $pdo->exec('PRAGMA busy_timeout=15000');
    $pdo->exec('PRAGMA foreign_keys=ON');
    return $pdo;
}

/** اجرای یک کوئری با پارامتر */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** یک ردیف یا null */
function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** همه ردیف‌ها */
function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** اولین ستون از اولین ردیف */
function val(string $sql, array $params = [], $default = null)
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? $default : $v;
}

/** تراکنش امن */
function transact(callable $fn)
{
    $pdo = db();
    $own = !$pdo->inTransaction();
    if ($own) {
        $pdo->beginTransaction();
    }
    try {
        $res = $fn();
        if ($own) {
            $pdo->commit();
        }
        return $res;
    } catch (Throwable $e) {
        if ($own && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function logMsg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] $msg\n";
    if (PHP_SAPI === 'cli') {
        echo $line;
    }
    @file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// ═══════════════════════════════════════════════════════════════════════════
//  📡  کلاینت تلگرام
// ═══════════════════════════════════════════════════════════════════════════

function tg(string $method, array $params = [], int $timeout = 30)
{
    // حالت تست: بدون تماس شبکه
    if (defined('HAPPY_BOT_DRY_RUN')) {
        return dryRunTg($method, $params);
    }

    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;

    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_bool($v)) {
            $params[$k] = $v ? 'true' : 'false';
        } elseif ($v === null) {
            unset($params[$k]);
        }
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        logMsg("⚠️ cURL error on $method: $err");
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        logMsg("⚠️ Bad JSON from $method: " . substr((string) $raw, 0, 300));
        return null;
    }
    if (empty($json['ok'])) {
        $desc = $json['description'] ?? 'unknown';
        // خطاهای رایج و بی‌ضرر را لاگ نکن
        if (!preg_match('/message is not modified|message to (edit|delete) not found|query is too old|QUERY_ID_INVALID/i', $desc)) {
            logMsg("⚠️ TG $method failed: $desc");
        }
        return null;
    }
    return $json['result'];
}

// ═══════════════════════════════════════════════════════════════════════════
//  🎨  دکمه‌های شیشه‌ای  (Glass Buttons)
// ═══════════════════════════════════════════════════════════════════════════

/** نشانگر رنگی هر استایل — شبیه‌سازی دکمه رنگی در تلگرام */
const BTN_STYLE_DOT = [
    'success' => '🟢',
    'danger'  => '🔴',
    'primary' => '🔵',
    'warning' => '🟡',
    'gold'    => '🟠',
    'plain'   => '',
];

/**
 * ساخت دکمه شیشه‌ای.
 * استایل بر اساس callback_data به‌صورت خودکار حدس زده می‌شود مگر صریح داده شود.
 */
function btn(string $text, ?string $data = null, ?string $style = null, ?string $url = null): array
{
    if ($style === null) {
        $style = guessBtnStyle($data);
    }
    $dot = BTN_STYLE_DOT[$style] ?? '';
    $label = $dot === '' ? $text : $dot . ' ' . $text;

    $b = ['text' => $label];
    if ($url !== null) {
        $b['url'] = $url;
    } else {
        $b['callback_data'] = $data ?? 'noop';
    }
    return $b;
}

function guessBtnStyle(?string $data): string
{
    if ($data === null) {
        return 'primary';
    }
    $d = mb_strtolower($data);
    $danger = ['cancel', 'reject', 'delete', 'remove', 'deactivate', 'ignore', 'no_', 'reset_cancel'];
    foreach ($danger as $k) {
        if (str_contains($d, $k)) {
            return 'danger';
        }
    }
    $success = [
        'buy', 'collect', 'interest', 'sell', 'upgrade', 'rankup', 'feed', 'donate',
        'check_join', 'confirm', 'join', 'draw', 'produce', 'stray_', 'bail',
        'deposit', 'approve', 'start', 'build', 'hire', 'open', 'chal_join',
    ];
    foreach ($success as $k) {
        if (str_contains($d, $k)) {
            return 'success';
        }
    }
    return 'primary';
}

function urlBtn(string $text, string $url, string $style = 'primary'): array
{
    return btn($text, null, $style, $url);
}

/** ساخت inline keyboard */
function kb(array $rows): array
{
    $clean = [];
    foreach ($rows as $row) {
        if (!is_array($row) || $row === []) {
            continue;
        }
        // اگر تک‌دکمه بدون آرایه‌ی سطر داده شده باشد
        $clean[] = isset($row['text']) ? [$row] : array_values(array_filter($row));
    }
    return ['inline_keyboard' => $clean];
}

/** ساخت reply keyboard */
function rkb(array $rows, bool $resize = true): array
{
    return [
        'keyboard'        => $rows,
        'resize_keyboard' => $resize,
        'is_persistent'   => true,
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
//  ✉️  ارسال پیام + Quote
// ═══════════════════════════════════════════════════════════════════════════

/** escape برای parse_mode=HTML */
function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** متن را داخل بلوک نقل‌قول تلگرام می‌گذارد */
function quote(string $text, bool $expandable = false): string
{
    return $expandable
        ? "<blockquote expandable>$text</blockquote>"
        : "<blockquote>$text</blockquote>";
}

/** نقل‌قول از متن پیام کاربر (کوتاه‌شده) */
function quoteOf(array $msg, int $max = 120): string
{
    $t = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
    if ($t === '') {
        return '';
    }
    if (mb_strlen($t) > $max) {
        $t = mb_substr($t, 0, $max) . '…';
    }
    return quote(h($t)) . "\n";
}

/**
 * ارسال پیام با پشتیبانی از quote/ریپلای.
 * @param array|null $replyTo پیام مبدأ برای ریپلای‌کردن
 */
/** حداکثر طول متن پیام تلگرام */
const TG_MAX_TEXT = 4096;
/** حداکثر طول متن پاسخ کال‌بک تلگرام */
const TG_MAX_CB_TEXT = 200;

/** کوتاه‌سازی امن متن بدون شکستن تگ‌های HTML باز */
function clampText(string $text, int $max = TG_MAX_TEXT): string
{
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max - 20);
    // اگر وسط یک تگ بریده شد، تا قبل از '<' عقب برو
    $lastOpen = mb_strrpos($cut, '<');
    $lastClose = mb_strrpos($cut, '>');
    if ($lastOpen !== false && ($lastClose === false || $lastOpen > $lastClose)) {
        $cut = mb_substr($cut, 0, $lastOpen);
    }
    // بستن blockquote باز
    $opens = mb_substr_count($cut, '<blockquote');
    $closes = mb_substr_count($cut, '</blockquote>');
    $cut .= str_repeat('</blockquote>', max(0, $opens - $closes));
    return $cut . "\n…";
}

function sendMsg($chatId, string $text, ?array $keyboard = null, ?array $replyTo = null, array $extra = [])
{
    $p = [
        'chat_id'    => $chatId,
        'text'       => clampText($text),
        'parse_mode' => 'HTML',
        'link_preview_options' => ['is_disabled' => true],
    ];
    if ($keyboard !== null) {
        $p['reply_markup'] = $keyboard;
    }
    if ($replyTo !== null && isset($replyTo['message_id'])) {
        $p['reply_parameters'] = [
            'message_id'                => $replyTo['message_id'],
            'allow_sending_without_reply' => true,
        ];
    }
    return tg('sendMessage', array_merge($p, $extra));
}

/** ریپلای مستقیم به پیام کاربر (Quote واقعی تلگرام) */
function reply(array $msg, string $text, ?array $keyboard = null, array $extra = [])
{
    return sendMsg($msg['chat']['id'], $text, $keyboard, $msg, $extra);
}

/** ریپلای همراه با نقل‌قول متن پیام کاربر داخل حباب پاسخ */
function replyQuoted(array $msg, string $text, ?array $keyboard = null)
{
    return reply($msg, quoteOf($msg) . $text, $keyboard);
}

function editMsg($chatId, int $messageId, string $text, ?array $keyboard = null)
{
    $p = [
        'chat_id'    => $chatId,
        'message_id' => $messageId,
        'text'       => clampText($text),
        'parse_mode' => 'HTML',
        'link_preview_options' => ['is_disabled' => true],
    ];
    if ($keyboard !== null) {
        $p['reply_markup'] = $keyboard;
    }
    return tg('editMessageText', $p);
}

function editMarkup($chatId, int $messageId, ?array $keyboard)
{
    return tg('editMessageReplyMarkup', [
        'chat_id'      => $chatId,
        'message_id'   => $messageId,
        'reply_markup' => $keyboard,
    ]);
}

function deleteMsg($chatId, int $messageId)
{
    return tg('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
}

function answerCb(string $callbackId, string $text = '', bool $alert = false)
{
    // تلگرام متن پاسخ کال‌بک را به ۲۰۰ کاراکتر محدود می‌کند
    if (mb_strlen($text) > TG_MAX_CB_TEXT) {
        $text = mb_substr($text, 0, TG_MAX_CB_TEXT - 1) . '…';
    }
    return tg('answerCallbackQuery', [
        'callback_query_id' => $callbackId,
        'text'              => $text,
        'show_alert'        => $alert,
    ]);
}

function sendDice($chatId, string $emoji = '🎲')
{
    return tg('sendDice', ['chat_id' => $chatId, 'emoji' => $emoji]);
}

/** ارسال پیام خصوصی امن (بدون کرش اگر کاربر بلاک کرده) */
function sendPrivate(int $userId, string $text, ?array $keyboard = null): bool
{
    return sendMsg($userId, $text, $keyboard) !== null;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🧰  ابزارهای عمومی
// ═══════════════════════════════════════════════════════════════════════════

/** فرمت عدد با جداکننده هزارگان */
function nf($n, int $dec = 0): string
{
    return number_format((float) $n, $dec, '.', ',');
}

/** تبدیل ارقام فارسی/عربی به انگلیسی */
function faToEn(string $s): string
{
    return strtr($s, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

/** نرمال‌سازی متن ورودی کاربر (نیم‌فاصله، ی/ک عربی، فاصله اضافه) */
function normalizeText(string $s): string
{
    $s = str_replace(["\xE2\x80\x8C", "\xE2\x80\x8B", "\xEF\xBB\xBF"], [' ', '', ''], $s);
    $s = strtr($s, ['ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه', '‌' => ' ']);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim((string) $s);
}

/**
 * تبدیل متن به عدد. پشتیبانی: 1,000 / 5k / 2m / ۳کا / 1.5میل
 * در صورت نامعتبر بودن -1 برمی‌گرداند.
 */
function parseAmount(string $text): int
{
    $t = trim(faToEn(normalizeText($text)));
    $t = str_replace([',', '،', '_', ' '], '', $t);
    if ($t === '') {
        return -1;
    }

    $mults = ['کیلو' => 1000, 'میل' => 1000000, 'کی' => 1000, 'کا' => 1000, 'k' => 1000, 'm' => 1000000];
    foreach ($mults as $suffix => $mult) {
        $len = mb_strlen($suffix);
        if (mb_strtolower(mb_substr($t, -$len)) === $suffix) {
            $head = mb_substr($t, 0, mb_strlen($t) - $len);
            if ($head === '' || !is_numeric($head)) {
                return -1;
            }
            return (int) round((float) $head * $mult);
        }
    }
    if (!is_numeric($t)) {
        return -1;
    }
    $n = (float) $t;
    if ($n > PHP_INT_MAX || $n < PHP_INT_MIN) {
        return -1;
    }
    return (int) $n;
}

function nowIso(): string
{
    return date('Y-m-d H:i:s');
}

function isoPlus(int $seconds): string
{
    return date('Y-m-d H:i:s', time() + $seconds);
}

/** تبدیل رشته زمان دیتابیس به timestamp (سازگار با ISO پایتون) */
function ts(?string $iso): int
{
    if ($iso === null || $iso === '') {
        return 0;
    }
    $iso = str_replace('T', ' ', $iso);
    $t = strtotime($iso);
    return $t === false ? 0 : $t;
}

/** آیا زمان داده‌شده هنوز در آینده است */
function isFuture(?string $iso): bool
{
    return $iso !== null && $iso !== '' && ts($iso) > time();
}

/** ثانیه باقی‌مانده تا زمان مقصد */
function secsLeft(?string $iso): int
{
    return max(0, ts($iso) - time());
}

/** فرمت مدت زمان به فارسی */
function fmtDur(int $secs): string
{
    $secs = max(0, $secs);
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    $s = $secs % 60;
    if ($h > 0) {
        return "{$h} ساعت و {$m} دقیقه";
    }
    if ($m > 0) {
        return "{$m} دقیقه و {$s} ثانیه";
    }
    return "{$s} ثانیه";
}

/** فرمت ساعت:دقیقه:ثانیه */
function fmtClock(int $secs): string
{
    $secs = max(0, $secs);
    return sprintf('%d:%02d:%02d', intdiv($secs, 3600), intdiv($secs % 3600, 60), $secs % 60);
}

/** نوار پیشرفت */
function bar($val, $need, int $size = 5): string
{
    if ($need <= 0) {
        return str_repeat('▰', $size);
    }
    $filled = (int) min($size, floor(($val / $need) * $size));
    $filled = max(0, $filled);
    return str_repeat('▰', $filled) . str_repeat('▱', $size - $filled);
}

/** انتخاب وزن‌دار از یک آرایه */
function weightedPick(array $items, array $weights)
{
    $total = array_sum($weights);
    if ($total <= 0) {
        return $items[array_key_first($items)] ?? null;
    }
    $r = mt_rand(1, (int) round($total * 1000)) / 1000;
    $acc = 0.0;
    foreach ($items as $i => $item) {
        $acc += $weights[$i] ?? 0;
        if ($r <= $acc) {
            return $item;
        }
    }
    return $items[count($items) - 1];
}

/** عدد اعشاری تصادفی بین دو مقدار */
function randFloat(float $min, float $max): float
{
    if ($max <= $min) {
        return $min;
    }
    return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
}

/** آیا رویداد با احتمال داده شده رخ می‌دهد */
function chance(float $p): bool
{
    return (mt_rand() / mt_getrandmax()) < $p;
}

/** مکث قبل از بازگشت به پنل */
function panelDelay(): void
{
    if (PANEL_RETURN_DELAY > 0) {
        sleep(PANEL_RETURN_DELAY);
    }
}

/** نام قابل نمایش کاربر */
function displayName(array $from): string
{
    $n = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    return $n !== '' ? $n : ('کاربر ' . ($from['id'] ?? '?'));
}

/** منشن HTML کاربر */
function mention(array $from): string
{
    return '<a href="tg://user?id=' . (int) $from['id'] . '">' . h(displayName($from)) . '</a>';
}

function mentionId(int $userId, string $name): string
{
    return '<a href="tg://user?id=' . $userId . '">' . h($name) . '</a>';
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏗  ساخت جداول دیتابیس
// ═══════════════════════════════════════════════════════════════════════════

function initDb(): void
{
    $pdo = db();
    $schema = [
        // ── هسته ──────────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS users (
            user_id    INTEGER PRIMARY KEY,
            username   TEXT    DEFAULT '',
            first_name TEXT    DEFAULT '',
            hop_points REAL    DEFAULT 0,
            total_hops INTEGER DEFAULT 0,
            level      INTEGER DEFAULT 1,
            last_hop   TEXT    DEFAULT NULL,
            joined_at  TEXT    DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS groups (
            group_id    INTEGER PRIMARY KEY,
            title       TEXT    DEFAULT '',
            level       INTEGER DEFAULT 1,
            treasury    REAL    DEFAULT 0,
            total_hops  INTEGER DEFAULT 0,
            total_dogs  INTEGER DEFAULT 0,
            total_bones INTEGER DEFAULT 0,
            total_fish  INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS dogs (
            user_id      INTEGER PRIMARY KEY,
            name         TEXT    DEFAULT 'سگولو',
            level        INTEGER DEFAULT 1,
            rank         INTEGER DEFAULT 1,
            points_box   REAL    DEFAULT 0,
            fed_until    TEXT    DEFAULT NULL,
            last_collect TEXT    DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS hooks (
            user_id   INTEGER PRIMARY KEY,
            level     INTEGER DEFAULT 1,
            last_cast TEXT    DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS pending_bones (
            user_id   INTEGER PRIMARY KEY,
            bone_name TEXT,
            weight    REAL,
            price     INTEGER,
            caught_at TEXT
        )",
        "CREATE TABLE IF NOT EXISTS bank (
            user_id         INTEGER PRIMARY KEY,
            balance         REAL DEFAULT 0,
            account_number  TEXT UNIQUE,
            opened_at       TEXT DEFAULT CURRENT_TIMESTAMP,
            last_interest   TEXT DEFAULT NULL,
            last_num_change TEXT DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS transfers (
            user_id       INTEGER PRIMARY KEY,
            last_transfer TEXT DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS transactions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER,
            type       TEXT,
            amount     REAL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_tx_user_type ON transactions(user_id, type, created_at)",

        // ── سگ خیابونی / زندان / اسپم ─────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS stray_dogs (
            group_id     INTEGER PRIMARY KEY,
            tries_left   INTEGER DEFAULT 3,
            current_cost INTEGER DEFAULT 600,
            appeared_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
            rescuer_ids  TEXT    DEFAULT ''
        )",
        "CREATE TABLE IF NOT EXISTS user_strays (
            user_id INTEGER PRIMARY KEY,
            count   INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS jail (
            user_id     INTEGER PRIMARY KEY,
            jailed_at   TEXT,
            release_at  TEXT,
            reason      TEXT    DEFAULT 'قاچاق',
            spam_count  INTEGER DEFAULT 0,
            work_points INTEGER DEFAULT 0,
            last_work   TEXT    DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS spam_tracker (
            user_id    INTEGER,
            group_id   INTEGER,
            msg_times  TEXT    DEFAULT '[]',
            jail_count INTEGER DEFAULT 0,
            PRIMARY KEY (user_id, group_id)
        )",

        // ── کارخانه ───────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS factories (
            user_id         INTEGER PRIMARY KEY,
            level           INTEGER DEFAULT 1,
            exp             INTEGER DEFAULT 0,
            warehouse_level INTEGER DEFAULT 1,
            machine_level   INTEGER DEFAULT 1,
            stock           INTEGER DEFAULT 0,
            last_produced   TEXT    DEFAULT NULL,
            producing       INTEGER DEFAULT 0,
            product_idx     INTEGER DEFAULT 0,
            production_end  TEXT    DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS market_prices (
            product_idx INTEGER PRIMARY KEY,
            multiplier  REAL    DEFAULT 1.0,
            updated_at  TEXT    DEFAULT CURRENT_TIMESTAMP
        )",

        // ── ادمین / تنظیمات ───────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS sub_admins (
            user_id    INTEGER PRIMARY KEY,
            username   TEXT,
            first_name TEXT,
            added_by   INTEGER,
            added_at   TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS bot_settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        )",
        "CREATE TABLE IF NOT EXISTS force_join_channels (
            username  TEXT PRIMARY KEY,
            name      TEXT,
            link      TEXT,
            added_at  TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        // ── دعوت دوستان ───────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS referrals (
            user_id    INTEGER PRIMARY KEY,
            inviter_id INTEGER,
            rewarded   INTEGER DEFAULT 0,
            joined_at  TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE INDEX IF NOT EXISTS idx_ref_inviter ON referrals(inviter_id)",

        // ── قرعه‌کشی ──────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS lotteries (
            lottery_id   TEXT PRIMARY KEY,
            title        TEXT,
            prize        INTEGER DEFAULT 0,
            winner_count INTEGER DEFAULT 1,
            state        TEXT    DEFAULT 'open',
            created_by   INTEGER,
            created_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
            end_at       TEXT    DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS lottery_entries (
            lottery_id TEXT,
            user_id    INTEGER,
            username   TEXT,
            first_name TEXT,
            joined_at  TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (lottery_id, user_id)
        )",

        // ── مارکت کاربران ─────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS user_market (
            listing_id  TEXT PRIMARY KEY,
            seller_id   INTEGER,
            seller_name TEXT,
            title       TEXT,
            description TEXT,
            content     TEXT,
            price       INTEGER,
            max_buyers  INTEGER DEFAULT 1,
            buyer_count INTEGER DEFAULT 0,
            status      TEXT    DEFAULT 'pending',
            created_at  TEXT    DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS user_market_buyers (
            listing_id TEXT,
            buyer_id   INTEGER,
            bought_at  TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (listing_id, buyer_id)
        )",

        // ── بحران شهری ────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS city_crises (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id    INTEGER,
            crisis_type TEXT,
            status      TEXT    DEFAULT 'active',
            started_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
            expires_at  TEXT,
            resolved_by INTEGER DEFAULT NULL,
            decision    TEXT    DEFAULT NULL,
            resolved_at TEXT    DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS city_crisis_penalties (
            group_id      INTEGER PRIMARY KEY,
            penalty_type  TEXT,
            penalty_value INTEGER,
            expires_at    TEXT
        )",

        // ── شهردار ────────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS mayor (
            group_id   INTEGER PRIMARY KEY,
            user_id    INTEGER,
            username   TEXT,
            first_name TEXT,
            elected_at TEXT    DEFAULT CURRENT_TIMESTAMP,
            term_end   TEXT,
            popularity INTEGER DEFAULT 100
        )",
        "CREATE TABLE IF NOT EXISTS mayor_decrees (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id    INTEGER,
            user_id     INTEGER,
            decree_type TEXT,
            decree_name TEXT,
            issued_at   TEXT DEFAULT CURRENT_TIMESTAMP,
            expires_at  TEXT
        )",
        "CREATE TABLE IF NOT EXISTS mayor_pledges (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id    INTEGER,
            user_id     INTEGER,
            pledge_key  TEXT,
            pledge_text TEXT,
            fulfilled   INTEGER DEFAULT 0,
            created_at  TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS mayor_elections (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id   INTEGER,
            status     TEXT DEFAULT 'candidacy',
            started_by INTEGER,
            started_at TEXT DEFAULT CURRENT_TIMESTAMP,
            ended_at   TEXT DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS mayor_candidates (
            election_id INTEGER,
            user_id     INTEGER,
            username    TEXT,
            first_name  TEXT,
            pledges     TEXT DEFAULT '',
            joined_at   TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (election_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS mayor_election_votes (
            election_id  INTEGER,
            voter_id     INTEGER,
            candidate_id INTEGER,
            voted_at     TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (election_id, voter_id)
        )",
        "CREATE TABLE IF NOT EXISTS mayor_protests (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id   INTEGER,
            user_id    INTEGER,
            username   TEXT,
            first_name TEXT,
            reason     TEXT,
            status     TEXT DEFAULT 'pending',
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS mayor_protest_votes (
            protest_id INTEGER,
            user_id    INTEGER,
            vote       TEXT,
            voted_at   TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (protest_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS mayor_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id    INTEGER,
            user_id     INTEGER,
            action_type TEXT,
            action_desc TEXT,
            created_at  TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS mayor_projects (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id    INTEGER,
            project_key TEXT,
            level       INTEGER DEFAULT 1,
            started_at  TEXT    DEFAULT CURRENT_TIMESTAMP,
            done_at     TEXT,
            status      TEXT    DEFAULT 'building'
        )",
        "CREATE TABLE IF NOT EXISTS mayor_contracts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id     INTEGER,
            contract_key TEXT,
            started_at   TEXT DEFAULT CURRENT_TIMESTAMP,
            expires_at   TEXT,
            status       TEXT DEFAULT 'active'
        )",
        "CREATE TABLE IF NOT EXISTS mayor_project_settings  (key TEXT PRIMARY KEY, value TEXT)",
        "CREATE TABLE IF NOT EXISTS mayor_contract_settings (key TEXT PRIMARY KEY, value TEXT)",

        // ── رهبر کشور ─────────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS leader (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER UNIQUE,
            username   TEXT,
            first_name TEXT,
            elected_at TEXT    DEFAULT CURRENT_TIMESTAMP,
            term_end   TEXT,
            popularity INTEGER DEFAULT 100
        )",
        "CREATE TABLE IF NOT EXISTS leader_elections (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            status     TEXT DEFAULT 'candidacy',
            started_by INTEGER,
            started_at TEXT DEFAULT CURRENT_TIMESTAMP,
            vote_start TEXT DEFAULT NULL,
            ended_at   TEXT DEFAULT NULL
        )",
        "CREATE TABLE IF NOT EXISTS leader_candidates (
            election_id INTEGER,
            user_id     INTEGER,
            username    TEXT,
            first_name  TEXT,
            pledges     TEXT DEFAULT '',
            joined_at   TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (election_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS leader_votes (
            election_id  INTEGER,
            voter_id     INTEGER,
            candidate_id INTEGER,
            voted_at     TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (election_id, voter_id)
        )",
        "CREATE TABLE IF NOT EXISTS leader_commands (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            command_key TEXT,
            issued_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
            expires_at  TEXT,
            is_active   INTEGER DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS leader_laws (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            law_key    TEXT,
            law_name   TEXT,
            enacted_at TEXT    DEFAULT CURRENT_TIMESTAMP,
            expires_at TEXT,
            is_active  INTEGER DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS national_treasury (
            id      INTEGER PRIMARY KEY CHECK(id=1),
            balance REAL DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS treasury_log (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            amount     REAL,
            reason     TEXT,
            user_id    INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS city_budgets (
            group_id    INTEGER PRIMARY KEY,
            amount      REAL DEFAULT 0,
            assigned_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS national_projects (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            project_key TEXT UNIQUE,
            status      TEXT DEFAULT 'building',
            started_at  TEXT DEFAULT CURRENT_TIMESTAMP,
            done_at     TEXT
        )",
        "CREATE TABLE IF NOT EXISTS ministers (
            role         TEXT PRIMARY KEY,
            user_id      INTEGER,
            username     TEXT,
            first_name   TEXT,
            appointed_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS national_events (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            event_key  TEXT,
            started_at TEXT    DEFAULT CURRENT_TIMESTAMP,
            expires_at TEXT,
            is_active  INTEGER DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS emergency_state (
            id         INTEGER PRIMARY KEY CHECK(id=1),
            etype      TEXT    DEFAULT NULL,
            started_at TEXT    DEFAULT NULL,
            expires_at TEXT    DEFAULT NULL,
            is_active  INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS leader_ratings (
            user_id  INTEGER PRIMARY KEY,
            rating   TEXT,
            rated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS leader_log (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER,
            action_type TEXT,
            action_desc TEXT,
            created_at  TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        // ── بازی و کازینو ─────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS game_tables (
            table_id     TEXT PRIMARY KEY,
            group_id     INTEGER,
            game_type    TEXT,
            creator_id   INTEGER,
            player2_id   INTEGER DEFAULT NULL,
            bet          INTEGER,
            state        TEXT    DEFAULT 'waiting',
            board        TEXT    DEFAULT NULL,
            current_turn INTEGER DEFAULT NULL,
            message_id   INTEGER DEFAULT NULL,
            created_at   TEXT    DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS game_cooldowns (
            user_id   INTEGER,
            game_type TEXT,
            last_play TEXT,
            PRIMARY KEY (user_id, game_type)
        )",

        // ── 🆕 چالش دوز ───────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS challenges (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            group_id     INTEGER NOT NULL,
            message_id   INTEGER DEFAULT NULL,
            creator_id   INTEGER NOT NULL,
            creator_name TEXT    DEFAULT '',
            rival_id     INTEGER DEFAULT NULL,
            rival_name   TEXT    DEFAULT '',
            bet          INTEGER NOT NULL,
            pot          INTEGER DEFAULT 0,
            board        TEXT    DEFAULT '000000000',
            turn_id      INTEGER DEFAULT NULL,
            status       TEXT    DEFAULT 'open',
            winner_id    INTEGER DEFAULT NULL,
            created_at   TEXT    DEFAULT CURRENT_TIMESTAMP,
            expires_at   TEXT,
            turn_expires TEXT    DEFAULT NULL,
            moves        INTEGER DEFAULT 0
        )",
        "CREATE INDEX IF NOT EXISTS idx_chal_status ON challenges(status, group_id)",
        "CREATE TABLE IF NOT EXISTS challenge_stats (
            user_id INTEGER PRIMARY KEY,
            wins    INTEGER DEFAULT 0,
            losses  INTEGER DEFAULT 0,
            draws   INTEGER DEFAULT 0,
            earned  REAL    DEFAULT 0
        )",

        // ── وضعیت مکالمه‌ها (جایگزین user_data پایتون) ────────────────────
        "CREATE TABLE IF NOT EXISTS user_states (
            user_id    INTEGER PRIMARY KEY,
            state      TEXT,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        // ── رأی‌گیری «میو» ────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS meow_votes (
            chat_id    INTEGER,
            message_id INTEGER,
            target_id  INTEGER,
            voters     TEXT DEFAULT '',
            expires_at TEXT,
            PRIMARY KEY (chat_id, message_id)
        )",

        // ── آفست پولینگ ───────────────────────────────────────────────────
        "CREATE TABLE IF NOT EXISTS bot_meta (key TEXT PRIMARY KEY, value TEXT)",
    ];

    foreach ($schema as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            logMsg('⚠️ schema: ' . $e->getMessage() . ' | ' . substr($sql, 0, 80));
        }
    }

    // ردیف‌های تک‌نمونه
    $pdo->exec('INSERT OR IGNORE INTO national_treasury (id, balance) VALUES (1, 0)');
    $pdo->exec('INSERT OR IGNORE INTO emergency_state (id) VALUES (1)');

    migrateDb();
    logMsg('✅ دیتابیس آماده شد');
}

/** افزودن ستون‌های جاافتاده به دیتابیس‌های قدیمی */
function migrateDb(): void
{
    $required = [
        'users'         => ['hop_points' => 'REAL DEFAULT 0', 'total_hops' => 'INTEGER DEFAULT 0', 'level' => 'INTEGER DEFAULT 1', 'last_hop' => 'TEXT', 'joined_at' => 'TEXT', 'username' => 'TEXT', 'first_name' => 'TEXT'],
        'groups'        => ['title' => 'TEXT', 'level' => 'INTEGER DEFAULT 1', 'treasury' => 'REAL DEFAULT 0', 'total_hops' => 'INTEGER DEFAULT 0', 'total_dogs' => 'INTEGER DEFAULT 0', 'total_bones' => 'INTEGER DEFAULT 0', 'total_fish' => 'INTEGER DEFAULT 0'],
        'dogs'          => ['name' => "TEXT DEFAULT 'سگولو'", 'level' => 'INTEGER DEFAULT 1', 'rank' => 'INTEGER DEFAULT 1', 'points_box' => 'REAL DEFAULT 0', 'fed_until' => 'TEXT', 'last_collect' => 'TEXT'],
        'hooks'         => ['level' => 'INTEGER DEFAULT 1', 'last_cast' => 'TEXT'],
        'bank'          => ['balance' => 'REAL DEFAULT 0', 'opened_at' => 'TEXT', 'last_interest' => 'TEXT', 'last_num_change' => 'TEXT'],
        'factories'     => ['level' => 'INTEGER DEFAULT 1', 'exp' => 'INTEGER DEFAULT 0', 'warehouse_level' => 'INTEGER DEFAULT 1', 'machine_level' => 'INTEGER DEFAULT 1', 'stock' => 'INTEGER DEFAULT 0', 'last_produced' => 'TEXT', 'producing' => 'INTEGER DEFAULT 0', 'product_idx' => 'INTEGER DEFAULT 0', 'production_end' => 'TEXT'],
        'jail'          => ['jailed_at' => 'TEXT', 'release_at' => 'TEXT', 'reason' => "TEXT DEFAULT 'قاچاق'", 'spam_count' => 'INTEGER DEFAULT 0', 'work_points' => 'INTEGER DEFAULT 0', 'last_work' => 'TEXT'],
        'user_strays'   => ['count' => 'INTEGER DEFAULT 0'],
        'spam_tracker'  => ['msg_times' => "TEXT DEFAULT '[]'", 'jail_count' => 'INTEGER DEFAULT 0'],
        'market_prices' => ['multiplier' => 'REAL DEFAULT 1.0', 'updated_at' => 'TEXT'],
        'mayor'         => ['popularity' => 'INTEGER DEFAULT 100'],
        'mayor_candidates' => ['pledges' => "TEXT DEFAULT ''"],
        'game_tables'   => ['message_id' => 'INTEGER DEFAULT NULL'],
        'challenges'    => ['moves' => 'INTEGER DEFAULT 0', 'turn_expires' => 'TEXT DEFAULT NULL', 'pot' => 'INTEGER DEFAULT 0'],
    ];

    foreach ($required as $table => $cols) {
        $exists = val("SELECT name FROM sqlite_master WHERE type='table' AND name=?", [$table]);
        if (!$exists) {
            continue;
        }
        $current = [];
        foreach (all("PRAGMA table_info($table)") as $c) {
            $current[$c['name']] = true;
        }
        foreach ($cols as $col => $def) {
            if (!isset($current[$col])) {
                try {
                    db()->exec("ALTER TABLE $table ADD COLUMN $col $def");
                    logMsg("✅ ستون '$col' به جدول '$table' اضافه شد");
                } catch (Throwable $e) {
                    // بی‌خطر
                }
            }
        }
    }

    // بازسازی کارخانه برای کاربران واجد شرایط
    try {
        q('INSERT OR IGNORE INTO factories (user_id)
           SELECT user_id FROM users
           WHERE level >= ? AND user_id NOT IN (SELECT user_id FROM factories)', [FACTORY_MIN_LEVEL]);
    } catch (Throwable $e) {
        // بی‌خطر
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  💾  تنظیمات و وضعیت مکالمه
// ═══════════════════════════════════════════════════════════════════════════

function setting(string $key, $default = null)
{
    $v = val('SELECT value FROM bot_settings WHERE key=?', [$key]);
    return $v === null ? $default : $v;
}

function setSetting(string $key, $value): void
{
    q('INSERT INTO bot_settings (key, value) VALUES (?, ?)
       ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, (string) $value]);
}

function meta(string $key, $default = null)
{
    $v = val('SELECT value FROM bot_meta WHERE key=?', [$key]);
    return $v === null ? $default : $v;
}

function setMeta(string $key, $value): void
{
    q('INSERT INTO bot_meta (key, value) VALUES (?, ?)
       ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, (string) $value]);
}

/** خواندن وضعیت مکالمه کاربر */
function getState(int $userId): array
{
    $raw = val('SELECT state FROM user_states WHERE user_id=?', [$userId]);
    if (!$raw) {
        return [];
    }
    $d = json_decode((string) $raw, true);
    return is_array($d) ? $d : [];
}

/** ذخیره وضعیت مکالمه کاربر */
function setState(int $userId, array $data): void
{
    q('INSERT INTO user_states (user_id, state, updated_at) VALUES (?, ?, ?)
       ON CONFLICT(user_id) DO UPDATE SET state=excluded.state, updated_at=excluded.updated_at',
        [$userId, json_encode($data, JSON_UNESCAPED_UNICODE), nowIso()]);
}

function clearState(int $userId): void
{
    q('DELETE FROM user_states WHERE user_id=?', [$userId]);
}

// ═══════════════════════════════════════════════════════════════════════════
//  👤  کاربران، گروه‌ها و سطح
// ═══════════════════════════════════════════════════════════════════════════

function getUser(int $userId): ?array
{
    return one('SELECT * FROM users WHERE user_id=?', [$userId]);
}

function ensureUser(array $from): array
{
    $id = (int) $from['id'];
    $username = (string) ($from['username'] ?? '');
    $name = displayName($from);
    q('INSERT INTO users (user_id, username, first_name) VALUES (?, ?, ?)
       ON CONFLICT(user_id) DO UPDATE SET username=excluded.username, first_name=excluded.first_name',
        [$id, $username, $name]);
    return getUser($id) ?? ['user_id' => $id, 'username' => $username, 'first_name' => $name,
        'hop_points' => 0, 'total_hops' => 0, 'level' => 1, 'last_hop' => null];
}

function ensureGroup(array $chat): array
{
    $id = (int) $chat['id'];
    $title = (string) ($chat['title'] ?? 'گروه');
    q('INSERT INTO groups (group_id, title) VALUES (?, ?)
       ON CONFLICT(group_id) DO UPDATE SET title=excluded.title', [$id, $title]);
    return getGroup($id);
}

function getGroup(int $groupId): array
{
    $g = one('SELECT * FROM groups WHERE group_id=?', [$groupId]);
    return $g ?? ['group_id' => $groupId, 'title' => '', 'level' => 1, 'treasury' => 0,
        'total_hops' => 0, 'total_dogs' => 0, 'total_bones' => 0, 'total_fish' => 0];
}

function addPoints(int $userId, $amount): void
{
    q('UPDATE users SET hop_points = hop_points + ? WHERE user_id=?', [(float) $amount, $userId]);
}

function deductPoints(int $userId, $amount): void
{
    q('UPDATE users SET hop_points = MAX(0, hop_points - ?) WHERE user_id=?', [(float) $amount, $userId]);
}

function userPoints(int $userId): float
{
    return (float) val('SELECT hop_points FROM users WHERE user_id=?', [$userId], 0);
}

/** سطح بر اساس تعداد کل هاپ */
function levelOf(int $totalHops): int
{
    $level = 1;
    foreach (levelThresholds() as $i => $threshold) {
        if ($totalHops >= $threshold) {
            $level = $i + 2;
        } else {
            break;
        }
    }
    return min($level, 1000);
}

/** تعداد هاپ لازم برای سطح بعد */
function hopsForNextLevel(int $level): int
{
    if ($level >= 1000) {
        return 0;
    }
    $t = levelThresholds();
    return $t[$level - 1] ?? 0;
}

/** پاداش تصادفی هر هاپ بر اساس سطح */
function calcHopReward(int $level): int
{
    $base = 56.74;
    $ratio = 1.2336;
    $maxReward = (int) ($base * ($ratio ** ($level - 1)));
    $maxReward = max(25, min($maxReward, PHP_INT_MAX / 4));
    $minReward = 20;
    if ($level >= 43) {
        $mid = (int) ($maxReward * 0.60);
        return chance(0.60)
            ? mt_rand($minReward, max($minReward, $mid))
            : mt_rand(max($minReward, $mid), $maxReward);
    }
    return mt_rand($minReward, $maxReward);
}

// ═══════════════════════════════════════════════════════════════════════════
//  ⛓  زندان
// ═══════════════════════════════════════════════════════════════════════════

/** @return array{0:bool,1:?array} */
function jailStatus(int $userId): array
{
    $row = one('SELECT * FROM jail WHERE user_id=?', [$userId]);
    if (!$row) {
        return [false, null];
    }
    if (isFuture($row['release_at'])) {
        return [true, $row];
    }
    q('DELETE FROM jail WHERE user_id=?', [$userId]);
    return [false, null];
}

function isJailed(int $userId): bool
{
    return jailStatus($userId)[0];
}

function jailUser(int $userId, string $reason = 'قاچاق', ?int $seconds = null): void
{
    $secs = $seconds ?? JAIL_DURATION;
    q('INSERT INTO jail (user_id, jailed_at, release_at, reason, spam_count, work_points, last_work)
       VALUES (?, ?, ?, ?, 0, 0, NULL)
       ON CONFLICT(user_id) DO UPDATE SET
         jailed_at=excluded.jailed_at, release_at=excluded.release_at,
         reason=excluded.reason, work_points=0, last_work=NULL',
        [$userId, nowIso(), isoPlus($secs), $reason]);
}

/** بررسی اسپم؛ در صورت زندانی‌شدن مدت را برمی‌گرداند */
function checkSpam(int $userId, int $groupId): int
{
    $row = one('SELECT * FROM spam_tracker WHERE user_id=? AND group_id=?', [$userId, $groupId]);
    $times = $row ? (json_decode((string) $row['msg_times'], true) ?: []) : [];
    $jailCount = $row ? (int) $row['jail_count'] : 0;
    $now = microtime(true);

    $times = array_values(array_filter($times, static fn($t) => $now - (float) $t < SPAM_WINDOW));
    $times[] = $now;

    if (count($times) >= SPAM_THRESHOLD) {
        $duration = SPAM_JAIL_BASE * (2 ** $jailCount);
        $jailCount++;
        $times = [];
        q('INSERT INTO spam_tracker (user_id, group_id, msg_times, jail_count) VALUES (?,?,?,?)
           ON CONFLICT(user_id, group_id) DO UPDATE SET msg_times=excluded.msg_times, jail_count=excluded.jail_count',
            [$userId, $groupId, json_encode($times), $jailCount]);
        jailUser($userId, "اسپم (دفعه $jailCount)", (int) $duration);
        return (int) $duration;
    }

    q('INSERT INTO spam_tracker (user_id, group_id, msg_times, jail_count) VALUES (?,?,?,?)
       ON CONFLICT(user_id, group_id) DO UPDATE SET msg_times=excluded.msg_times, jail_count=excluded.jail_count',
        [$userId, $groupId, json_encode($times), $jailCount]);
    return 0;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏰  شهر و باف‌ها
// ═══════════════════════════════════════════════════════════════════════════

function cityLevel(array $grp): int
{
    $lvl = 1;
    for ($i = 2; $i <= CITY_MAX_LEVEL; $i++) {
        $r = CITY_LEVELS[$i];
        if ((float) $grp['treasury'] >= $r[0]
            && (int) $grp['total_hops'] >= $r[1]
            && (int) $grp['total_dogs'] >= $r[2]
            && (int) $grp['total_bones'] >= $r[3]
            && (int) $grp['total_fish'] >= $r[4]) {
            $lvl = $i;
        }
    }
    return $lvl;
}

function cityHopCooldown(int $cityLevel): int
{
    return max(30, HOP_COOLDOWN - ($cityLevel - 1) * CITY_HOP_BUFF);
}

function cityFishCooldown(int $cityLevel, int $baseCd): int
{
    return max(60, $baseCd - ($cityLevel - 1) * CITY_FISH_BUFF);
}

// ── بحران‌ها ─────────────────────────────────────────────────────────────

function activeCrisis(int $groupId): ?array
{
    return one("SELECT * FROM city_crises WHERE group_id=? AND status='active' ORDER BY id DESC LIMIT 1", [$groupId]);
}

function activePenalty(int $groupId): ?array
{
    $row = one('SELECT * FROM city_crisis_penalties WHERE group_id=?', [$groupId]);
    if (!$row) {
        return null;
    }
    if (!isFuture($row['expires_at'])) {
        q('DELETE FROM city_crisis_penalties WHERE group_id=?', [$groupId]);
        return null;
    }
    return $row;
}

function applyCrisisPenalty(int $groupId, string $crisisKey, bool $half = false): void
{
    $c = CRISIS_TYPES[$crisisKey] ?? null;
    if (!$c) {
        return;
    }
    $dur = $half ? intdiv($c['fix_cooldown'], 2) : $c['fix_cooldown'];
    $valp = $half ? intdiv($c['penalty_value'], 2) : $c['penalty_value'];
    q('INSERT INTO city_crisis_penalties (group_id, penalty_type, penalty_value, expires_at) VALUES (?,?,?,?)
       ON CONFLICT(group_id) DO UPDATE SET penalty_type=excluded.penalty_type,
         penalty_value=excluded.penalty_value, expires_at=excluded.expires_at',
        [$groupId, $c['penalty_type'], $valp, isoPlus($dur)]);
}

function resolveCrisis(int $crisisId, string $decision, ?int $resolverId = null): void
{
    q('UPDATE city_crises SET status=?, decision=?, resolved_by=?, resolved_at=? WHERE id=?', [
        $decision === 'ignore' ? 'ignored' : 'resolved',
        $decision, $resolverId, nowIso(), $crisisId,
    ]);
}

function crisisHopPenalty(int $groupId): int
{
    $p = activePenalty($groupId);
    return ($p && $p['penalty_type'] === 'hop_cooldown') ? (int) $p['penalty_value'] : 0;
}

function crisisDogMultiplier(int $groupId): float
{
    $p = activePenalty($groupId);
    if (!$p) {
        return 1.0;
    }
    if ($p['penalty_type'] === 'dog_freeze') { return 0.0; }
    if ($p['penalty_type'] === 'dog_slow')   { return 0.70; }
    return 1.0;
}

function crisisFactoryMultiplier(int $groupId): float
{
    $p = activePenalty($groupId);
    if (!$p) {
        return 1.0;
    }
    if ($p['penalty_type'] === 'factory_freeze') { return 0.0; }
    if ($p['penalty_type'] === 'factory_slow')   { return 0.50; }
    return 1.0;
}

// ── فرمان‌های شهردار ─────────────────────────────────────────────────────

function activeDecree(int $groupId): ?array
{
    return one("SELECT * FROM mayor_decrees WHERE group_id=? AND expires_at > ? ORDER BY id DESC LIMIT 1",
        [$groupId, nowIso()]);
}

function decreeMultiplier(int $groupId, string $effect): float
{
    $d = activeDecree($groupId);
    if (!$d) {
        return 1.0;
    }
    $def = MAYOR_DECREES_DEF[$d['decree_type']] ?? null;
    if (!$def || $def['effect'] !== $effect) {
        return 1.0;
    }
    return 1.0 + (float) $def['value'];
}

function decreeDailyPrize(int $groupId): int
{
    $d = activeDecree($groupId);
    if (!$d) {
        return 0;
    }
    $def = MAYOR_DECREES_DEF[$d['decree_type']] ?? null;
    return ($def && $def['effect'] === 'daily_prize') ? mt_rand(500, 5000) : 0;
}

// ── پروژه‌ها و قراردادهای شهری ───────────────────────────────────────────

function projectSetting(string $key, $default = null)
{
    $v = val('SELECT value FROM mayor_project_settings WHERE key=?', [$key]);
    return $v === null ? $default : $v;
}

function setProjectSetting(string $key, $value): void
{
    q('INSERT INTO mayor_project_settings (key, value) VALUES (?,?)
       ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, (string) $value]);
}

function contractSetting(string $key, $default = null)
{
    $v = val('SELECT value FROM mayor_contract_settings WHERE key=?', [$key]);
    return $v === null ? $default : $v;
}

function setContractSetting(string $key, $value): void
{
    q('INSERT INTO mayor_contract_settings (key, value) VALUES (?,?)
       ON CONFLICT(key) DO UPDATE SET value=excluded.value', [$key, (string) $value]);
}

function projectCost(string $key, int $level = 1): int
{
    return (int) projectSetting("cost_{$key}_lv{$level}", PROJECT_DEFAULT_COST * $level);
}

function contractMinDays(): int
{
    return (int) contractSetting('min_days', CONTRACT_DEFAULT_MIN_DAYS);
}

function getProject(int $groupId, string $key): ?array
{
    return one('SELECT * FROM mayor_projects WHERE group_id=? AND project_key=? ORDER BY id DESC LIMIT 1',
        [$groupId, $key]);
}

function activeContract(int $groupId): ?array
{
    return one("SELECT * FROM mayor_contracts WHERE group_id=? AND status='active' AND expires_at > ? ORDER BY id DESC LIMIT 1",
        [$groupId, nowIso()]);
}

function projectMultiplier(int $groupId, string $effect): float
{
    $mult = 1.0;
    foreach (CITY_PROJECTS as $key => $proj) {
        if ($proj['effect'] !== $effect) {
            continue;
        }
        $p = getProject($groupId, $key);
        if ($p && $p['status'] === 'done') {
            $mult += (float) $proj['value'] * (int) $p['level'];
        }
    }
    return $mult;
}

/** دامنه اثر یک کلید قرارداد: factory_boost/factory_cut → factory */
function effectDomain(string $effect): string
{
    foreach (['factory', 'bank', 'dog', 'hop', 'daily', 'cooldown'] as $d) {
        if (str_starts_with($effect, $d)) {
            return $d;
        }
    }
    return $effect;
}

/**
 * ضریب قرارداد فعال شهر برای یک دامنه.
 * $domain یکی از: factory | bank | dog | hop
 */
function contractMultiplier(int $groupId, string $domain): float
{
    $c = activeContract($groupId);
    if (!$c) {
        return 1.0;
    }
    $def = CITY_CONTRACTS[$c['contract_key']] ?? null;
    if (!$def) {
        return 1.0;
    }
    $domain = effectDomain($domain);
    $mult = 1.0;
    if (effectDomain((string) $def['pro_effect']) === $domain) {
        $mult *= 1.0 + (float) $def['pro_value'];
    }
    if (effectDomain((string) $def['con_effect']) === $domain) {
        $mult *= 1.0 - (float) $def['con_value'];
    }
    return $mult;
}

// ── اثرات ملی (رهبر) ─────────────────────────────────────────────────────

function activeNationalCommand(): ?array
{
    return one("SELECT * FROM leader_commands WHERE is_active=1 AND expires_at > ? ORDER BY id DESC LIMIT 1", [nowIso()]);
}

function activeNationalLaws(): array
{
    return all("SELECT * FROM leader_laws WHERE is_active=1 AND (expires_at IS NULL OR expires_at > ?)", [nowIso()]);
}

function activeNationalEvents(): array
{
    return all("SELECT * FROM national_events WHERE is_active=1 AND expires_at > ?", [nowIso()]);
}

function activeEmergency(): ?array
{
    $row = one('SELECT * FROM emergency_state WHERE id=1');
    if (!$row || !(int) $row['is_active']) {
        return null;
    }
    if (!isFuture($row['expires_at'])) {
        q("UPDATE emergency_state SET is_active=0, etype=NULL WHERE id=1");
        return null;
    }
    return $row;
}

function doneNationalProjects(): array
{
    return all("SELECT * FROM national_projects WHERE status='done'");
}

/**
 * ضریب ملی برای یک اثر مشخص:
 * hop_mult | factory_mult | dog_mult | bank_mult | cd_mult | all_mult
 */
function nationalMultiplier(string $key): float
{
    $mult = 1.0;

    // فرمان فعال رهبر
    $cmd = activeNationalCommand();
    if ($cmd) {
        $def = NATIONAL_COMMANDS[$cmd['command_key']] ?? null;
        if ($def) {
            if ($def['key'] === $key) {
                $mult *= (float) $def['val'];
            } elseif ($def['key'] === 'all_mult' && in_array($key, ['hop_mult', 'factory_mult', 'dog_mult', 'bank_mult'], true)) {
                $mult *= (float) $def['val'];
            }
        }
    }

    // پروژه‌های ملی تکمیل‌شده
    foreach (doneNationalProjects() as $p) {
        $def = NATIONAL_PROJECTS[$p['project_key']] ?? null;
        if (!$def) {
            continue;
        }
        if ($def['ekey'] === $key) {
            $mult *= (float) $def['eval'];
        } elseif ($def['ekey'] === 'all_mult' && in_array($key, ['hop_mult', 'factory_mult', 'dog_mult', 'bank_mult'], true)) {
            $mult *= (float) $def['eval'];
        }
    }

    // وزرا
    foreach (all('SELECT role FROM ministers WHERE user_id IS NOT NULL') as $m) {
        $def = MINISTER_ROLES[$m['role']] ?? null;
        if (!$def) {
            continue;
        }
        if ($def['ekey'] === $key) {
            $mult *= (float) $def['eval'];
        } elseif ($def['ekey'] === 'all_mult' && in_array($key, ['hop_mult', 'factory_mult', 'dog_mult', 'bank_mult'], true)) {
            $mult *= (float) $def['eval'];
        }
    }

    // رویدادهای ملی
    foreach (activeNationalEvents() as $e) {
        $def = NATIONAL_EVENTS[$e['event_key']] ?? null;
        if (!$def) {
            continue;
        }
        if ($def['ekey'] === $key) {
            $mult *= (float) $def['eval'];
        } elseif ($def['ekey'] === 'all_mult' && in_array($key, ['hop_mult', 'factory_mult', 'dog_mult', 'bank_mult'], true)) {
            $mult *= (float) $def['eval'];
        }
    }

    // وضعیت اضطراری
    $em = activeEmergency();
    if ($em) {
        $def = EMERGENCY_TYPES[$em['etype']] ?? null;
        if ($def) {
            if (isset($def[$key])) {
                $mult *= (float) $def[$key];
            } elseif (isset($def['all_mult']) && in_array($key, ['hop_mult', 'factory_mult', 'dog_mult', 'bank_mult'], true)) {
                $mult *= (float) $def['all_mult'];
            }
        }
    }

    // قوانین ملی
    foreach (activeNationalLaws() as $law) {
        $def = NATIONAL_LAWS[$law['law_key']] ?? null;
        if ($def && $def['key'] === 'factory_rate' && $key === 'factory_mult') {
            $mult *= (float) $def['val'];
        }
    }

    return $mult > 0 ? $mult : 0.0;
}

/** جایزه ثابت اضافه ملی روی هر هاپ */
function nationalDailyBonus(): int
{
    $bonus = 0;
    $cmd = activeNationalCommand();
    if ($cmd) {
        $def = NATIONAL_COMMANDS[$cmd['command_key']] ?? null;
        if ($def && $def['key'] === 'daily_bonus') {
            $bonus += (int) $def['val'];
        }
    }
    foreach (activeNationalEvents() as $e) {
        $def = NATIONAL_EVENTS[$e['event_key']] ?? null;
        if ($def && $def['ekey'] === 'daily_bonus') {
            $bonus += (int) $def['eval'];
        }
    }
    return $bonus;
}

/** ضریب نهایی کولداون (کمتر = بهتر) */
function cooldownMultiplier(int $groupId): float
{
    $m = nationalMultiplier('cd_mult');
    $p = projectMultiplier($groupId, 'cooldown_cut');
    if ($p > 1.0) {
        $m *= (2.0 - $p); // هر ۲۰٪ پروژه یعنی ۲۰٪ کاهش کولداون
    }
    return max(0.3, min(2.0, $m));
}

/** ضریب کل درآمد هاپ */
function hopIncomeMultiplier(int $groupId): float
{
    return decreeMultiplier($groupId, 'hop_boost')
        * contractMultiplier($groupId, 'hop')
        * nationalMultiplier('hop_mult');
}

/** ضریب کل درآمد سگ */
function dogIncomeMultiplier(int $groupId): float
{
    return crisisDogMultiplier($groupId)
        * decreeMultiplier($groupId, 'dog_boost')
        * projectMultiplier($groupId, 'dog_boost')
        * contractMultiplier($groupId, 'dog')
        * nationalMultiplier('dog_mult');
}

/** ضریب کل تولید کارخانه */
function factorySpeedMultiplier(int $groupId): float
{
    return crisisFactoryMultiplier($groupId)
        * decreeMultiplier($groupId, 'factory_boost')
        * projectMultiplier($groupId, 'factory_boost')
        * contractMultiplier($groupId, 'factory')
        * nationalMultiplier('factory_mult');
}

/** ضریب کل سود بانک */
function bankInterestMultiplier(int $groupId = 0): float
{
    $m = nationalMultiplier('bank_mult');
    if ($groupId !== 0) {
        $m *= decreeMultiplier($groupId, 'bank_boost')
            * projectMultiplier($groupId, 'bank_boost')
            * contractMultiplier($groupId, 'bank');
    }
    foreach (activeNationalLaws() as $law) {
        $def = NATIONAL_LAWS[$law['law_key']] ?? null;
        if ($def && $def['key'] === 'bank_rate_bonus') {
            $m *= 1 + ((float) $def['val'] / BANK_INTEREST_RATE) * 0.1;
        }
    }
    return $m;
}

/** تخفیف هزینه ارتقا از قوانین ملی */
function upgradeDiscount(): float
{
    $d = 1.0;
    foreach (activeNationalLaws() as $law) {
        $def = NATIONAL_LAWS[$law['law_key']] ?? null;
        if ($def && $def['key'] === 'upgrade_disc') {
            $d *= (float) $def['val'];
        }
    }
    return $d;
}

/** تخفیف هزینه آیتم از قوانین ملی */
function itemDiscount(): float
{
    $d = 1.0;
    foreach (activeNationalLaws() as $law) {
        $def = NATIONAL_LAWS[$law['law_key']] ?? null;
        if ($def && $def['key'] === 'item_disc') {
            $d *= (float) $def['val'];
        }
    }
    return $d;
}

/** اعمال تخفیف روی هزینه */
function withDiscount(int $cost, float $discount): int
{
    return max(1, (int) round($cost * $discount));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🐕  سگ
// ═══════════════════════════════════════════════════════════════════════════

function getDog(int $userId): ?array
{
    return one('SELECT * FROM dogs WHERE user_id=?', [$userId]);
}

function dogData(int $level): array
{
    return DOG_LEVEL_DATA[$level] ?? DOG_LEVEL_DATA[1];
}

function dogRank(int $rank): array
{
    return DOG_RANKS[$rank] ?? ['نامشخص', 1.0];
}

/** پوینت انباشته در جعبه سگ */
function calcDogPoints(array $dog, int $groupId = 0): float
{
    if (empty($dog['last_collect']) || !isFuture($dog['fed_until'])) {
        return 0.0;
    }
    $seconds = max(0, time() - ts($dog['last_collect']));
    [$rate, $capacity] = dogData((int) $dog['level']);
    $rankMult = dogRank((int) $dog['rank'])[1];
    $buff = $groupId !== 0 ? dogIncomeMultiplier($groupId) : 1.0;
    return min($seconds * $rate * $rankMult * $buff, (float) $capacity);
}

function boneRarity(string $boneName): string
{
    foreach (BONES_TABLE as $b) {
        if ($b[0] === $boneName) {
            return $b[5];
        }
    }
    return '⚪';
}

/** مدت سیری استخوان بر اساس کمیابی و وزن */
function calcFeedDuration(string $boneName, float $weight): int
{
    $icon = boneRarity($boneName);
    $base = FEED_BASE_BY_RARITY[$icon] ?? FEED_BASE_BY_RARITY['⚪'];
    return $base + (int) ($weight * FEED_WEIGHT_BONUS);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🦴  قلاب و صید استخوان
// ═══════════════════════════════════════════════════════════════════════════

function getHook(int $userId): ?array
{
    return one('SELECT * FROM hooks WHERE user_id=?', [$userId]);
}

function hookData(int $level): array
{
    return HOOK_DATA[$level] ?? HOOK_DATA[HOOK_MAX_LEVEL];
}

/**
 * صید یک استخوان تصادفی.
 * @return array{0:string,1:float,2:int,3:string,4:string}
 */
function catchBone(int $hookLevel): array
{
    $weights = RARITY_WEIGHTS_BY_LEVEL[$hookLevel] ?? RARITY_WEIGHTS_BY_LEVEL[HOOK_MAX_LEVEL];

    $icons = [];
    $iconWeights = [];
    foreach (RARITY_ORDER as $i => $icon) {
        if (($weights[$i] ?? 0) <= 0) {
            continue;
        }
        foreach (BONES_TABLE as $b) {
            if ($b[5] === $icon && $b[3] <= $hookLevel) {
                $icons[] = $icon;
                $iconWeights[] = (float) $weights[$i];
                break;
            }
        }
    }
    if ($icons === []) {
        $icons = ['⚪'];
        $iconWeights = [1.0];
    }

    $chosenRarity = weightedPick($icons, $iconWeights);

    $pool = [];
    foreach (BONES_TABLE as $b) {
        if ($b[5] === $chosenRarity && $b[3] <= $hookLevel) {
            $pool[] = $b;
        }
    }
    if ($pool === []) {
        $pool[] = BONES_TABLE[0];
    }

    $maxPrice = 0;
    foreach ($pool as $b) {
        $maxPrice = max($maxPrice, (int) $b[4]);
    }
    $inner = [];
    foreach ($pool as $b) {
        $inner[] = max(1.0, ($maxPrice / max(1, (int) $b[4])) ** 1.5);
    }

    $bone = weightedPick($pool, $inner);
    [$name, $wMin, $wMax, , $basePrice, $rarityIcon] = $bone;

    $wMin = max(0.01, (float) $wMin);
    $weight = round(randFloat($wMin, (float) $wMax), 2);
    $price = (int) ($basePrice * ($weight / $wMin));

    return [$name, $weight, $price, $rarityIcon, RARITY_ICON_MAP[$rarityIcon] ?? ''];
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏭  کارخانه
// ═══════════════════════════════════════════════════════════════════════════

function getFactory(int $userId): ?array
{
    return one('SELECT * FROM factories WHERE user_id=?', [$userId]);
}

function strayCount(int $userId): int
{
    return (int) val('SELECT count FROM user_strays WHERE user_id=?', [$userId], 0);
}

function addStrays(int $userId, int $n): void
{
    q('INSERT INTO user_strays (user_id, count) VALUES (?, ?)
       ON CONFLICT(user_id) DO UPDATE SET count = count + ?', [$userId, max(0, $n), $n]);
}

/** به‌روزرسانی ضرایب بازار هر یک ساعت */
function refreshMarketPrices(): void
{
    $count = count(FACTORY_PRODUCTS);
    for ($idx = 0; $idx < $count; $idx++) {
        $row = one('SELECT updated_at FROM market_prices WHERE product_idx=?', [$idx]);
        if ($row && $row['updated_at'] && (time() - ts($row['updated_at'])) < 3600) {
            continue;
        }
        $mult = round(randFloat(MARKET_PRICE_MIN, MARKET_PRICE_MAX), 2);
        q('INSERT INTO market_prices (product_idx, multiplier, updated_at) VALUES (?,?,?)
           ON CONFLICT(product_idx) DO UPDATE SET multiplier=excluded.multiplier, updated_at=excluded.updated_at',
            [$idx, $mult, nowIso()]);
    }
}

/** @return array{0:int,1:float} قیمت لحظه‌ای و ضریب بازار */
function marketPrice(int $productIdx): array
{
    refreshMarketPrices();
    $mult = (float) val('SELECT multiplier FROM market_prices WHERE product_idx=?', [$productIdx], 1.0);
    $base = FACTORY_PRODUCTS[$productIdx][2] ?? 0;
    return [(int) ($base * $mult), $mult];
}

function availableProducts(int $factoryLevel): array
{
    $out = [];
    foreach (FACTORY_PRODUCTS as $idx => $p) {
        if ($p[3] <= $factoryLevel) {
            $out[$idx] = $p;
        }
    }
    return $out;
}

function productionDone(?array $factory): bool
{
    if (!$factory || !(int) $factory['producing'] || empty($factory['production_end'])) {
        return false;
    }
    return time() >= ts($factory['production_end']);
}

/** جمع‌آوری محصولات آماده — تعداد اضافه‌شده را برمی‌گرداند */
function collectProduction(int $userId): int
{
    $factory = getFactory($userId);
    if (!productionDone($factory)) {
        return 0;
    }
    $workers = max(1, strayCount($userId));
    $cap = WAREHOUSE_LEVELS[(int) $factory['warehouse_level']][0] ?? 20;
    $added = min($workers, max(0, $cap - (int) $factory['stock']));
    if ($added <= 0) {
        return 0;
    }

    $product = FACTORY_PRODUCTS[(int) $factory['product_idx']] ?? FACTORY_PRODUCTS[0];
    $newExp = (int) $factory['exp'] + ($product[4] * $added);
    $newLevel = (int) $factory['level'];
    while ($newLevel < FACTORY_MAX_LEVEL
        && (FACTORY_LEVEL_EXP[$newLevel] ?? 0) > 0
        && $newExp >= FACTORY_LEVEL_EXP[$newLevel]) {
        $newExp -= FACTORY_LEVEL_EXP[$newLevel];
        $newLevel++;
    }

    q('UPDATE factories SET stock = stock + ?, exp = ?, level = ?, producing = 0,
         production_end = NULL, last_produced = ? WHERE user_id = ?',
        [$added, $newExp, $newLevel, nowIso(), $userId]);

    return $added;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🔐  ادمین
// ═══════════════════════════════════════════════════════════════════════════

function isAdmin(int $userId): bool
{
    return in_array($userId, ADMIN_IDS, true);
}

function isSubAdmin(int $userId): bool
{
    return val('SELECT 1 FROM sub_admins WHERE user_id=?', [$userId]) !== null;
}

function isAnyAdmin(int $userId): bool
{
    return isAdmin($userId) || isSubAdmin($userId);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🔔  جوین اجباری
// ═══════════════════════════════════════════════════════════════════════════

function forceJoinChannels(): array
{
    $out = [];
    if (FORCE_JOIN_CHANNEL !== '' && FORCE_JOIN_CHANNEL !== 'یوزرنیم کانال') {
        $out[] = ['username' => FORCE_JOIN_CHANNEL, 'name' => FORCE_JOIN_CHANNEL_NAME, 'link' => FORCE_JOIN_CHANNEL_LINK];
    }
    foreach (all('SELECT username, name, link FROM force_join_channels ORDER BY added_at') as $r) {
        $out[] = $r;
    }
    return $out;
}

/** @return array{0:bool,1:array} */
function checkMembership(int $userId): array
{
    if (!FORCE_JOIN_ENABLED) {
        return [true, []];
    }
    $missing = [];
    foreach (forceJoinChannels() as $ch) {
        $res = tg('getChatMember', ['chat_id' => '@' . $ch['username'], 'user_id' => $userId], 10);
        if ($res === null) {
            continue; // ربات ادمین کانال نیست — نادیده بگیر
        }
        $status = $res['status'] ?? '';
        if (!in_array($status, ['member', 'administrator', 'creator'], true)) {
            $missing[] = $ch;
        }
    }
    return [$missing === [], $missing];
}

function joinKeyboard(array $missing): array
{
    $rows = [];
    foreach ($missing as $ch) {
        $rows[] = [urlBtn('عضویت در ' . $ch['name'] . ' 🔔', $ch['link'], 'primary')];
    }
    $rows[] = [btn('عضو شدم، بررسی کن!', 'check_join', 'success')];
    return kb($rows);
}

/** true یعنی کاربر عضو نیست و باید متوقف شود */
function forceJoinBlocked(array $msg, array $from): bool
{
    if (!FORCE_JOIN_ENABLED) {
        return false;
    }
    $chatType = $msg['chat']['type'] ?? 'private';
    if (!in_array($chatType, ['group', 'supergroup'], true)) {
        return false;
    }
    [$ok, $missing] = checkMembership((int) $from['id']);
    if ($ok) {
        return false;
    }
    $list = '';
    foreach ($missing as $ch) {
        $list .= '• ' . h($ch['name']) . "\n";
    }
    reply($msg,
        '⛔️ ' . mention($from) . " عزیز!\n\n" .
        "برای استفاده از ربات هاپ‌داگ، ابتدا باید عضو این کانال‌ها بشی:\n\n" .
        quote($list) .
        "\n👇 روی دکمه‌ها بزن، عضو شو، بعد «عضو شدم» رو بزن:",
        joinKeyboard($missing));
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🎁  دعوت دوستان
// ═══════════════════════════════════════════════════════════════════════════

function referralEnabled(): bool
{
    return setting('referral_enabled', REFERRAL_ENABLED ? '1' : '0') === '1';
}

function referralRewardSender(): int
{
    return (int) setting('referral_reward_sender', (string) REFERRAL_REWARD_SENDER);
}

function referralRewardJoiner(): int
{
    return (int) setting('referral_reward_joiner', (string) REFERRAL_REWARD_JOINER);
}

function referralCount(int $userId): int
{
    return (int) val('SELECT COUNT(*) FROM referrals WHERE inviter_id=? AND rewarded=1', [$userId], 0);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🕹  کولداون بازی‌ها
// ═══════════════════════════════════════════════════════════════════════════

function gameCooldownLeft(int $userId, string $gameType, int $seconds): int
{
    $last = val('SELECT last_play FROM game_cooldowns WHERE user_id=? AND game_type=?', [$userId, $gameType]);
    if (!$last) {
        return 0;
    }
    return max(0, $seconds - (time() - ts((string) $last)));
}

function setGameCooldown(int $userId, string $gameType): void
{
    q('INSERT INTO game_cooldowns (user_id, game_type, last_play) VALUES (?,?,?)
       ON CONFLICT(user_id, game_type) DO UPDATE SET last_play=excluded.last_play',
        [$userId, $gameType, nowIso()]);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🆕🎯  چالش دوز  (XO Challenge)
// ═══════════════════════════════════════════════════════════════════════════
//  کاربر می‌نویسد: «چالش 500 میو»  →  یک چالش باز می‌شود.
//  هر کسی که ۵۰۰ میو پوینت داشته باشد می‌تواند با دکمه سبز شرکت کند.
// ═══════════════════════════════════════════════════════════════════════════

const XO_SYMBOLS   = [0 => '⬜', 1 => '❌', 2 => '⭕'];
const XO_WIN_LINES = [
    [0, 1, 2], [3, 4, 5], [6, 7, 8],
    [0, 3, 6], [1, 4, 7], [2, 5, 8],
    [0, 4, 8], [2, 4, 6],
];

/** آرایه تخته از رشته ۹ کاراکتری */
function xoBoard(string $s): array
{
    $b = [];
    for ($i = 0; $i < 9; $i++) {
        $b[$i] = (int) ($s[$i] ?? '0');
    }
    return $b;
}

function xoBoardStr(array $b): string
{
    $s = '';
    for ($i = 0; $i < 9; $i++) {
        $s .= (string) ((int) ($b[$i] ?? 0));
    }
    return $s;
}

/** نمایش متنی تخته */
function xoRender(array $b): string
{
    $rows = [];
    for ($i = 0; $i < 9; $i += 3) {
        $rows[] = XO_SYMBOLS[$b[$i]] . ' ' . XO_SYMBOLS[$b[$i + 1]] . ' ' . XO_SYMBOLS[$b[$i + 2]];
    }
    return implode("\n", $rows);
}

/**
 * بررسی برنده.
 * @return array{0:int,1:array} 0=ادامه، -1=مساوی، 1|2=برنده  +  خانه‌های برنده
 */
function xoWinner(array $b): array
{
    foreach (XO_WIN_LINES as $line) {
        [$x, $y, $z] = $line;
        if ($b[$x] !== 0 && $b[$x] === $b[$y] && $b[$y] === $b[$z]) {
            return [$b[$x], $line];
        }
    }
    foreach ($b as $c) {
        if ($c === 0) {
            return [0, []];
        }
    }
    return [-1, []];
}

/** صفحه‌کلید شیشه‌ای تخته دوز */
function xoKeyboard(array $b, int $challengeId, array $highlight = []): array
{
    $rows = [];
    for ($i = 0; $i < 9; $i += 3) {
        $row = [];
        for ($j = 0; $j < 3; $j++) {
            $idx = $i + $j;
            $sym = XO_SYMBOLS[$b[$idx]];
            if (in_array($idx, $highlight, true)) {
                $sym = '🏆';
            }
            $row[] = [
                'text' => $sym,
                'callback_data' => $b[$idx] === 0 ? "chal_mv_{$challengeId}_{$idx}" : 'chal_noop',
            ];
        }
        $rows[] = $row;
    }
    return ['inline_keyboard' => $rows];
}

function getChallenge(int $id): ?array
{
    return one('SELECT * FROM challenges WHERE id=?', [$id]);
}

function challengeStats(int $userId): array
{
    $s = one('SELECT * FROM challenge_stats WHERE user_id=?', [$userId]);
    return $s ?? ['user_id' => $userId, 'wins' => 0, 'losses' => 0, 'draws' => 0, 'earned' => 0];
}

function bumpChallengeStats(int $userId, string $field, float $earned = 0): void
{
    if (!in_array($field, ['wins', 'losses', 'draws'], true)) {
        return;
    }
    q('INSERT INTO challenge_stats (user_id, wins, losses, draws, earned) VALUES (?,0,0,0,0)
       ON CONFLICT(user_id) DO NOTHING', [$userId]);
    q("UPDATE challenge_stats SET $field = $field + 1, earned = earned + ? WHERE user_id = ?", [$earned, $userId]);
}

/** متن اعلان چالش باز */
function challengeOpenText(array $c): string
{
    $left = secsLeft($c['expires_at']);
    return "🎯 <b>چالش دوز باز شد!</b>\n\n"
        . quote(
            "🎮 سازنده : " . h($c['creator_name']) . "\n"
            . "💰 مبلغ چالش : <b>" . nf($c['bet']) . "</b> میو پوینت\n"
            . "🏆 جایزه برنده : <b>" . nf(challengePrize((int) $c['bet'])) . "</b> میو پوینت\n"
            . "⏳ مهلت پیوستن : " . fmtDur($left)
        )
        . "\n📌 هر کسی <b>" . nf($c['bet']) . "</b> میو پوینت داشته باشه می‌تونه شرکت کنه!\n"
        . "🟢 روی دکمه سبز بزن و بازی رو شروع کن 👇";
}

/** جایزه برنده = دو برابر شرط + پاداش */
function challengePrize(int $bet): int
{
    return (int) round($bet * 2 * (1 + CHALLENGE_WIN_BONUS));
}

/** دکمه‌های چالش باز — سبز برای شرکت + دکمه دوم */
function challengeOpenKeyboard(array $c): array
{
    $id = (int) $c['id'];
    return kb([
        [btn('شرکت در چالش دوز (' . nf($c['bet']) . ' میو)', "chal_join_{$id}", 'success')],
        [
            btn('وضعیت چالش', "chal_info_{$id}", 'primary'),
            btn('لغو چالش', "chal_cancel_{$id}", 'danger'),
        ],
    ]);
}

/** متن تخته در حال بازی */
function challengePlayText(array $c, string $note = ''): string
{
    $b = xoBoard((string) $c['board']);
    $turnName = ((int) $c['turn_id'] === (int) $c['creator_id']) ? $c['creator_name'] : $c['rival_name'];
    $turnSym  = ((int) $c['turn_id'] === (int) $c['creator_id']) ? '❌' : '⭕';
    $left = secsLeft($c['turn_expires']);

    return "🎯 <b>چالش دوز</b>\n\n"
        . quote(
            "❌ " . h($c['creator_name']) . "\n"
            . "⭕ " . h($c['rival_name']) . "\n"
            . "💰 شرط : " . nf($c['bet']) . " میو  |  🏆 جایزه : " . nf(challengePrize((int) $c['bet']))
        )
        . "\n" . xoRender($b) . "\n\n"
        . "🎲 نوبت : {$turnSym} <b>" . h($turnName) . "</b>\n"
        . "⏳ مهلت نوبت : " . fmtDur($left)
        . ($note !== '' ? "\n\n{$note}" : '');
}

/**
 * ساخت چالش جدید.
 * فراخوانی از روتر متن گروه با متن «چالش 500 میو»
 */
function cmdCreateChallenge(array $msg, array $from, int $bet): void
{
    $chatId = (int) $msg['chat']['id'];
    $uid = (int) $from['id'];

    if (!in_array($msg['chat']['type'] ?? '', ['group', 'supergroup'], true)) {
        reply($msg, '🎯 چالش دوز فقط توی گروه کار می‌کنه!');
        return;
    }

    $u = ensureUser($from);
    ensureGroup($msg['chat']);

    if (isJailed($uid)) {
        reply($msg, '⛓ تو زندانی هستی! نمی‌تونی چالش بذاری.');
        return;
    }
    if ((int) $u['level'] < CHALLENGE_MIN_LEVEL) {
        reply($msg, "🔒 برای چالش دوز باید حداقل سطح " . CHALLENGE_MIN_LEVEL . " باشی!\n⭐️ سطح فعلیت: " . (int) $u['level']);
        return;
    }
    if ($bet < CHALLENGE_MIN_BET) {
        reply($msg, '❌ حداقل مبلغ چالش ' . nf(CHALLENGE_MIN_BET) . " میو پوینته!\n\n📝 مثال: <code>چالش " . nf(CHALLENGE_MIN_BET) . ' میو</code>');
        return;
    }
    if ($bet > CHALLENGE_MAX_BET) {
        reply($msg, '❌ حداکثر مبلغ چالش ' . nf(CHALLENGE_MAX_BET) . ' میو پوینته!');
        return;
    }
    if ((float) $u['hop_points'] < $bet) {
        reply($msg, "❌ موجودی کافی نداری!\n💰 موجودی تو: " . nf($u['hop_points']) . "\n🎯 لازم: " . nf($bet));
        return;
    }

    // چالش باز/در حال بازیِ قبلی همین کاربر — قبل از کولداون بررسی می‌شود
    $existing = one("SELECT * FROM challenges WHERE creator_id=? AND status IN ('open','playing')", [$uid]);
    if ($existing) {
        reply($msg, "⚠️ تو الان یه چالش فعال داری!\nاول اون رو تموم کن یا لغوش کن.",
            kb([[btn('نمایش چالش فعلی', 'chal_info_' . (int) $existing['id'], 'primary')]]));
        return;
    }

    // همینطور اگر حریفِ یک بازی در جریان است
    $asRival = one("SELECT * FROM challenges WHERE rival_id=? AND status='playing'", [$uid]);
    if ($asRival) {
        reply($msg, "⚠️ تو الان وسط یه چالش دیگه‌ای!\nاول اون رو تموم کن.",
            kb([[btn('نمایش چالش فعلی', 'chal_info_' . (int) $asRival['id'], 'primary')]]));
        return;
    }

    $cdLeft = gameCooldownLeft($uid, 'challenge', CHALLENGE_COOLDOWN);
    if ($cdLeft > 0) {
        reply($msg, '⌛️ ' . fmtDur($cdLeft) . ' تا چالش بعدی صبر کن!');
        return;
    }

    $name = displayName($from);

    transact(static function () use ($chatId, $uid, $name, $bet) {
        deductPoints($uid, $bet);
        q('INSERT INTO challenges (group_id, creator_id, creator_name, bet, pot, board, status, created_at, expires_at)
           VALUES (?,?,?,?,?,?,?,?,?)',
            [$chatId, $uid, $name, $bet, $bet, '000000000', 'open', nowIso(), isoPlus(CHALLENGE_JOIN_WINDOW)]);
    });

    $id = (int) db()->lastInsertId();
    $c = getChallenge($id);
    if (!$c) {
        addPoints($uid, $bet);
        reply($msg, '❌ خطا در ساخت چالش! پوینتت برگشت داده شد.');
        return;
    }

    setGameCooldown($uid, 'challenge');

    $sent = reply($msg, challengeOpenText($c), challengeOpenKeyboard($c));
    if (is_array($sent) && isset($sent['message_id'])) {
        q('UPDATE challenges SET message_id=? WHERE id=?', [(int) $sent['message_id'], $id]);
    }
}

/** پیوستن به چالش */
function challengeJoin(array $cbq, int $id): void
{
    $from = $cbq['from'];
    $uid = (int) $from['id'];
    $cbId = $cbq['id'];

    $c = getChallenge($id);
    if (!$c) {
        answerCb($cbId, '❌ این چالش پیدا نشد!', true);
        return;
    }
    if ($c['status'] !== 'open') {
        answerCb($cbId, '❌ این چالش دیگه باز نیست!', true);
        return;
    }
    if (!isFuture($c['expires_at'])) {
        challengeExpire($c);
        answerCb($cbId, '⏰ مهلت این چالش تموم شده!', true);
        return;
    }
    if ((int) $c['creator_id'] === $uid) {
        answerCb($cbId, '❌ نمی‌تونی با خودت بازی کنی!', true);
        return;
    }
    if (isJailed($uid)) {
        answerCb($cbId, '⛓ تو زندانی هستی! نمی‌تونی شرکت کنی.', true);
        return;
    }

    $u = ensureUser($from);
    if ((int) $u['level'] < CHALLENGE_MIN_LEVEL) {
        answerCb($cbId, '🔒 برای شرکت باید حداقل سطح ' . CHALLENGE_MIN_LEVEL . ' باشی!', true);
        return;
    }

    $bet = (int) $c['bet'];
    if ((float) $u['hop_points'] < $bet) {
        answerCb($cbId, '❌ برای شرکت به ' . nf($bet) . " میو پوینت نیاز داری!\n💰 موجودی تو: " . nf($u['hop_points']), true);
        return;
    }

    $name = displayName($from);
    $ok = transact(static function () use ($id, $uid, $name, $bet, $c) {
        // قفل خوش‌بینانه: فقط اگر هنوز open است
        $st = q("UPDATE challenges SET rival_id=?, rival_name=?, status='playing', turn_id=?,
                   pot = pot + ?, turn_expires=?, board='000000000'
                 WHERE id=? AND status='open'",
            [$uid, $name, (int) $c['creator_id'], $bet, isoPlus(CHALLENGE_TURN_TIME), $id]);
        if ($st->rowCount() === 0) {
            return false;
        }
        deductPoints($uid, $bet);
        return true;
    });

    if (!$ok) {
        answerCb($cbId, '❌ یکی زودتر از تو وارد چالش شد!', true);
        return;
    }

    setGameCooldown($uid, 'challenge');
    answerCb($cbId, '✅ وارد چالش شدی! نوبت حریفه.');

    $c = getChallenge($id);
    $chatId = (int) $c['group_id'];
    $mid = (int) ($c['message_id'] ?? 0);
    $text = challengePlayText($c, '🔥 بازی شروع شد! موفق باشی 🐾');
    $board = xoBoard((string) $c['board']);

    if ($mid > 0) {
        editMsg($chatId, $mid, $text, xoKeyboard($board, $id));
    } else {
        $sent = sendMsg($chatId, $text, xoKeyboard($board, $id));
        if (is_array($sent) && isset($sent['message_id'])) {
            q('UPDATE challenges SET message_id=? WHERE id=?', [(int) $sent['message_id'], $id]);
        }
    }
}

/** حرکت در تخته دوز */
function challengeMove(array $cbq, int $id, int $idx): void
{
    $from = $cbq['from'];
    $uid = (int) $from['id'];
    $cbId = $cbq['id'];

    if ($idx < 0 || $idx > 8) {
        answerCb($cbId, '❌ خونه نامعتبر!', true);
        return;
    }

    $c = getChallenge($id);
    if (!$c || $c['status'] !== 'playing') {
        answerCb($cbId, '❌ این بازی تموم شده!', true);
        return;
    }
    if ((int) $c['turn_id'] !== $uid) {
        $isPlayer = in_array($uid, [(int) $c['creator_id'], (int) $c['rival_id']], true);
        answerCb($cbId, $isPlayer ? '⏳ نوبت تو نیست!' : '❌ تو توی این چالش نیستی!', true);
        return;
    }
    if (!isFuture($c['turn_expires'])) {
        challengeTimeoutTurn($c);
        answerCb($cbId, '⏰ مهلت نوبتت تموم شد!', true);
        return;
    }

    $board = xoBoard((string) $c['board']);
    if ($board[$idx] !== 0) {
        answerCb($cbId, '❌ این خونه پره!', true);
        return;
    }

    $isCreator = ($uid === (int) $c['creator_id']);
    $board[$idx] = $isCreator ? 1 : 2;
    $nextTurn = $isCreator ? (int) $c['rival_id'] : (int) $c['creator_id'];

    [$winner, $line] = xoWinner($board);

    if ($winner === 0) {
        q("UPDATE challenges SET board=?, turn_id=?, turn_expires=?, moves = moves + 1 WHERE id=?",
            [xoBoardStr($board), $nextTurn, isoPlus(CHALLENGE_TURN_TIME), $id]);
        answerCb($cbId, '✅ حرکتت ثبت شد!');
        $c = getChallenge($id);
        editMsg((int) $c['group_id'], (int) $c['message_id'], challengePlayText($c), xoKeyboard($board, $id));
        return;
    }

    if ($winner === -1) {
        challengeFinishDraw($c, $board);
        answerCb($cbId, '🤝 مساوی شد! پوینت‌ها برگشت.');
        return;
    }

    $winnerId = $winner === 1 ? (int) $c['creator_id'] : (int) $c['rival_id'];
    challengeFinishWin($c, $board, $winnerId, $line);
    answerCb($cbId, $winnerId === $uid ? '🏆 بردی! تبریک 🎉' : '😿 حریف برد!');
}

/** پایان با برنده */
function challengeFinishWin(array $c, array $board, int $winnerId, array $line = []): void
{
    $id = (int) $c['id'];
    $bet = (int) $c['bet'];
    $prize = challengePrize($bet);
    $loserId = $winnerId === (int) $c['creator_id'] ? (int) $c['rival_id'] : (int) $c['creator_id'];
    $winnerName = $winnerId === (int) $c['creator_id'] ? $c['creator_name'] : $c['rival_name'];
    $loserName  = $winnerId === (int) $c['creator_id'] ? $c['rival_name'] : $c['creator_name'];

    $done = transact(static function () use ($id, $winnerId, $prize, $board) {
        $st = q("UPDATE challenges SET status='done', winner_id=?, board=?, turn_id=NULL, turn_expires=NULL
                 WHERE id=? AND status='playing'", [$winnerId, xoBoardStr($board), $id]);
        if ($st->rowCount() === 0) {
            return false;
        }
        addPoints($winnerId, $prize);
        return true;
    });
    if (!$done) {
        return;
    }

    bumpChallengeStats($winnerId, 'wins', $prize - $bet);
    bumpChallengeStats($loserId, 'losses', -$bet);

    $wStats = challengeStats($winnerId);

    $text = "🏆 <b>" . h($winnerName) . " برنده چالش دوز شد!</b>\n\n"
        . xoRender($board) . "\n\n"
        . quote(
            "🥇 برنده : " . h($winnerName) . "\n"
            . "🥈 بازنده : " . h($loserName) . "\n"
            . "💰 جایزه : <b>+" . nf($prize) . "</b> میو پوینت\n"
            . "✨ پاداش برد : " . (int) (CHALLENGE_WIN_BONUS * 100) . "٪"
        )
        . "\n📊 کارنامه برنده: " . (int) $wStats['wins'] . " برد | " . (int) $wStats['losses'] . " باخت | " . (int) $wStats['draws'] . " مساوی\n\n"
        . "🎯 برای چالش جدید بنویس: <code>چالش " . nf($bet) . " میو</code>";

    editMsg((int) $c['group_id'], (int) $c['message_id'], $text, xoKeyboard($board, $id, $line));
}

/** پایان مساوی */
function challengeFinishDraw(array $c, array $board): void
{
    $id = (int) $c['id'];
    $bet = (int) $c['bet'];

    $done = transact(static function () use ($id, $c, $bet, $board) {
        $st = q("UPDATE challenges SET status='draw', board=?, turn_id=NULL, turn_expires=NULL
                 WHERE id=? AND status='playing'", [xoBoardStr($board), $id]);
        if ($st->rowCount() === 0) {
            return false;
        }
        addPoints((int) $c['creator_id'], $bet);
        addPoints((int) $c['rival_id'], $bet);
        return true;
    });
    if (!$done) {
        return;
    }

    bumpChallengeStats((int) $c['creator_id'], 'draws');
    bumpChallengeStats((int) $c['rival_id'], 'draws');

    $text = "🤝 <b>چالش دوز مساوی شد!</b>\n\n"
        . xoRender($board) . "\n\n"
        . quote(
            "❌ " . h($c['creator_name']) . "\n"
            . "⭕ " . h($c['rival_name']) . "\n"
            . "💰 " . nf($bet) . " میو پوینت به هر دو برگشت داده شد ✅"
        )
        . "\n🎯 برای چالش جدید بنویس: <code>چالش " . nf($bet) . " میو</code>";

    editMsg((int) $c['group_id'], (int) $c['message_id'], $text, null);
}

/** منقضی شدن چالش بدون حریف — بازگشت پوینت */
function challengeExpire(array $c): void
{
    $id = (int) $c['id'];
    $bet = (int) $c['bet'];

    $done = transact(static function () use ($id, $c, $bet) {
        $st = q("UPDATE challenges SET status='expired' WHERE id=? AND status='open'", [$id]);
        if ($st->rowCount() === 0) {
            return false;
        }
        addPoints((int) $c['creator_id'], $bet);
        return true;
    });
    if (!$done) {
        return;
    }

    $text = "⏰ <b>چالش دوز منقضی شد!</b>\n\n"
        . quote(
            "🎮 سازنده : " . h($c['creator_name']) . "\n"
            . "💰 " . nf($bet) . " میو پوینت برگشت داده شد ✅"
        )
        . "\n😿 کسی حاضر نشد وارد چالش بشه...\n"
        . "🎯 دوباره امتحان کن: <code>چالش " . nf($bet) . " میو</code>";

    if ((int) ($c['message_id'] ?? 0) > 0) {
        editMsg((int) $c['group_id'], (int) $c['message_id'], $text, null);
    }
}

/** پایان نوبت به دلیل اتمام زمان — حریف برنده می‌شود */
function challengeTimeoutTurn(array $c): void
{
    $id = (int) $c['id'];
    $loserId = (int) $c['turn_id'];
    $winnerId = $loserId === (int) $c['creator_id'] ? (int) $c['rival_id'] : (int) $c['creator_id'];
    $bet = (int) $c['bet'];
    $prize = challengePrize($bet);
    $winnerName = $winnerId === (int) $c['creator_id'] ? $c['creator_name'] : $c['rival_name'];
    $loserName  = $winnerId === (int) $c['creator_id'] ? $c['rival_name'] : $c['creator_name'];

    $done = transact(static function () use ($id, $winnerId, $prize) {
        $st = q("UPDATE challenges SET status='timeout', winner_id=?, turn_id=NULL, turn_expires=NULL
                 WHERE id=? AND status='playing'", [$winnerId, $id]);
        if ($st->rowCount() === 0) {
            return false;
        }
        addPoints($winnerId, $prize);
        return true;
    });
    if (!$done) {
        return;
    }

    bumpChallengeStats($winnerId, 'wins', $prize - $bet);
    bumpChallengeStats($loserId, 'losses', -$bet);

    $board = xoBoard((string) $c['board']);
    $text = "⏰ <b>وقت تموم شد!</b>\n\n"
        . xoRender($board) . "\n\n"
        . quote(
            "😴 " . h($loserName) . " توی مهلتش بازی نکرد.\n"
            . "🏆 برنده : " . h($winnerName) . "\n"
            . "💰 جایزه : <b>+" . nf($prize) . "</b> میو پوینت"
        );

    editMsg((int) $c['group_id'], (int) $c['message_id'], $text, null);
}

/** لغو چالش توسط سازنده یا ادمین */
function challengeCancel(array $cbq, int $id): void
{
    $uid = (int) $cbq['from']['id'];
    $cbId = $cbq['id'];

    $c = getChallenge($id);
    if (!$c) {
        answerCb($cbId, '❌ چالش پیدا نشد!', true);
        return;
    }
    if ($c['status'] !== 'open') {
        answerCb($cbId, '❌ فقط چالش بازِ بدون حریف رو میشه لغو کرد!', true);
        return;
    }
    if ((int) $c['creator_id'] !== $uid && !isAnyAdmin($uid)) {
        answerCb($cbId, '❌ فقط سازنده چالش می‌تونه لغوش کنه!', true);
        return;
    }

    $bet = (int) $c['bet'];
    $done = transact(static function () use ($id, $c, $bet) {
        $st = q("UPDATE challenges SET status='cancelled' WHERE id=? AND status='open'", [$id]);
        if ($st->rowCount() === 0) {
            return false;
        }
        addPoints((int) $c['creator_id'], $bet);
        return true;
    });
    if (!$done) {
        answerCb($cbId, '❌ این چالش قبلاً بسته شده!', true);
        return;
    }

    answerCb($cbId, '❌ چالش لغو شد. پوینتت برگشت داده شد.');
    editMsg((int) $c['group_id'], (int) $c['message_id'],
        "❌ <b>چالش دوز لغو شد</b>\n\n" . quote('💰 ' . nf($bet) . ' میو پوینت به ' . h($c['creator_name']) . ' برگشت داده شد ✅'),
        null);
}

/** نمایش وضعیت چالش (دکمه دوم) */
function challengeInfo(array $cbq, int $id): void
{
    $c = getChallenge($id);
    $cbId = $cbq['id'];
    if (!$c) {
        answerCb($cbId, '❌ چالش پیدا نشد!', true);
        return;
    }

    $statusMap = [
        'open' => '🟢 باز — منتظر حریف',
        'playing' => '🎮 در حال بازی',
        'done' => '✅ تموم شده',
        'draw' => '🤝 مساوی',
        'expired' => '⏰ منقضی',
        'timeout' => '⏰ پایان با تایم‌اوت',
        'cancelled' => '❌ لغو شده',
    ];

    $me = challengeStats((int) $cbq['from']['id']);
    $lines = "🎯 وضعیت: " . ($statusMap[$c['status']] ?? $c['status']) . "\n"
        . "💰 شرط: " . nf($c['bet']) . " میو\n"
        . "🏆 جایزه: " . nf(challengePrize((int) $c['bet'])) . " میو\n"
        . "❌ " . $c['creator_name'] . "\n";

    if ($c['rival_id']) {
        $lines .= "⭕ " . $c['rival_name'] . "\n";
    } else {
        $lines .= "⭕ (خالی)\n";
    }
    if ($c['status'] === 'open') {
        $lines .= "⏳ مهلت پیوستن: " . fmtDur(secsLeft($c['expires_at'])) . "\n";
    } elseif ($c['status'] === 'playing') {
        $lines .= "⏳ مهلت نوبت: " . fmtDur(secsLeft($c['turn_expires'])) . "\n";
    }
    $lines .= "\n📊 کارنامه تو:\n🏆 " . (int) $me['wins'] . " برد | 💀 " . (int) $me['losses'] . " باخت | 🤝 " . (int) $me['draws'] . ' مساوی';

    answerCb($cbId, $lines, true);
}

/** پاک‌سازی دوره‌ای چالش‌های منقضی */
function challengeCron(): void
{
    foreach (all("SELECT * FROM challenges WHERE status='open' AND expires_at <= ?", [nowIso()]) as $c) {
        challengeExpire($c);
    }
    foreach (all("SELECT * FROM challenges WHERE status='playing' AND turn_expires IS NOT NULL AND turn_expires <= ?", [nowIso()]) as $c) {
        challengeTimeoutTurn($c);
    }
}

/** جدول برترین‌های چالش دوز */
function cmdChallengeTop(array $msg): void
{
    $rows = all('SELECT s.*, u.first_name FROM challenge_stats s
                 LEFT JOIN users u ON u.user_id = s.user_id
                 ORDER BY s.wins DESC, s.earned DESC LIMIT 10');
    if ($rows === []) {
        reply($msg, '🎯 هنوز هیچ چالش دوزی برگزار نشده!\n\n📝 اولین نفر باش: <code>چالش 500 میو</code>');
        return;
    }
    $medals = ['🥇', '🥈', '🥉'];
    $body = '';
    foreach ($rows as $i => $r) {
        $medal = $medals[$i] ?? ($i + 1) . '.';
        $name = $r['first_name'] ?: 'ناشناس';
        $body .= "$medal " . h($name) . ' — 🏆 ' . (int) $r['wins'] . ' برد | 💀 ' . (int) $r['losses'] . " باخت\n";
    }
    reply($msg, "🎯 <b>برترین‌های چالش دوز</b>\n\n" . quote($body));
}

/** راهنمای چالش */
function cmdChallengeHelp(array $msg): void
{
    reply($msg,
        "🎯 <b>راهنمای چالش دوز</b>\n\n"
        . quote(
            "برای ساخت چالش بنویس:\n"
            . "<code>چالش 500 میو</code>\n"
            . "<code>چالش 10k میو</code>\n\n"
            . "هر کسی اون مقدار میو پوینت داشته باشه\n"
            . "می‌تونه با دکمه سبز 🟢 وارد چالش بشه."
        )
        . "\n📋 قوانین:\n"
        . "┐─ 💰 حداقل شرط: " . nf(CHALLENGE_MIN_BET) . " میو\n"
        . "┐─ ⏳ مهلت پیوستن: " . fmtDur(CHALLENGE_JOIN_WINDOW) . "\n"
        . "┐─ ⏱ مهلت هر نوبت: " . fmtDur(CHALLENGE_TURN_TIME) . "\n"
        . "┐─ 🏆 برنده: " . (int) (CHALLENGE_WIN_BONUS * 100) . "٪ پاداش اضافه می‌گیره\n"
        . "└─ 🤝 مساوی: پوینت‌ها برگردونده میشه",
        kb([[btn('برترین‌های چالش', 'chal_top', 'primary')]]));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🐾  هاپ
// ═══════════════════════════════════════════════════════════════════════════

function cmdHop(array $msg, array $from): void
{
    $chat = $msg['chat'];
    $chatId = (int) $chat['id'];
    $uid = (int) $from['id'];

    if (($chat['type'] ?? '') === 'private') {
        reply($msg, "🐕 هاپ فقط توی گروه کار می‌کنه!\nمنو به گروهت اضافه کن 👉 @" . BOT_USERNAME);
        return;
    }

    $u = ensureUser($from);
    ensureGroup($chat);

    [$jailed, $jailRow] = jailStatus($uid);
    if ($jailed) {
        reply($msg,
            '⛓️ <b>' . h(displayName($from)) . "، تو زندانی هستی!</b>\n\n"
            . quote("📌 دلیل : " . h($jailRow['reason']) . "\n⌛️ " . fmtDur(secsLeft($jailRow['release_at'])) . ' تا آزادی')
            . "\nبنویس <b>زندان</b> تا گزینه‌های آزادی رو ببینی.");
        return;
    }

    $grp = getGroup($chatId);
    $cityLvl = cityLevel($grp);

    $effectiveCd = (int) round(cityHopCooldown($cityLvl) * cooldownMultiplier($chatId));
    $effectiveCd += crisisHopPenalty($chatId);
    $effectiveCd = max(15, $effectiveCd);

    if (!empty($u['last_hop'])) {
        $diff = time() - ts($u['last_hop']);
        if ($diff < $effectiveCd) {
            reply($msg, "⏳ " . h(displayName($from)) . "، هنوز باید صبر کنی!\n⌛️ "
                . fmtDur($effectiveCd - $diff) . ' دیگه می‌تونی هاپ کنی 🐾');
            return;
        }
    }

    $currentLevel = (int) $u['level'];
    $reward = calcHopReward($currentLevel);

    $mult = hopIncomeMultiplier($chatId);
    if (abs($mult - 1.0) > 0.0001) {
        $reward = (int) round($reward * $mult);
    }
    $dailyPrize = decreeDailyPrize($chatId) + nationalDailyBonus();
    $reward = max(1, $reward + $dailyPrize);

    $newHops = (int) $u['total_hops'] + 1;
    $newPoints = (float) $u['hop_points'] + $reward;
    $newLevel = levelOf($newHops);
    $leveledUp = $newLevel > $currentLevel;

    q('UPDATE users SET hop_points=?, total_hops=?, level=?, last_hop=? WHERE user_id=?',
        [$newPoints, $newHops, $newLevel, nowIso(), $uid]);
    q('UPDATE groups SET total_hops = total_hops + 1 WHERE group_id=?', [$chatId]);

    $nextLvlHops = hopsForNextLevel($newLevel);
    $progress = $newLevel < 1000 ? "$newHops/$nextLvlHops" : 'MAX';
    $boostTag = abs($mult - 1.0) > 0.0001 ? ' 🎉' : '';

    $body = "🦴 +" . nf($reward) . " هاپ پوینت{$boostTag}\n"
        . "💰 موجودی : " . nf($newPoints) . "\n"
        . "⭐️ سطح : {$newLevel}  |  🐾 هاپ : {$progress}";
    if ($dailyPrize > 0) {
        $body .= "\n🎁 جایزه روزانه : +" . nf($dailyPrize);
    }

    $text = '🐕 <b>هاپ هاپ!</b> ' . h(displayName($from)) . "\n\n" . quote($body);
    if ($leveledUp) {
        $text .= "\n\n🎉 <b>لِول آپ!</b> به سطح {$newLevel} رسیدی!";
    }
    reply($msg, $text);

    // پاداش دعوت پس از اولین هاپ
    if ($newHops === 1 && referralEnabled()) {
        grantReferralReward($msg, $from);
    }

    // بحران و سگ خیابونی
    expireOldCrises();
    maybeTriggerCrisis($msg, $chatId);
    maybeSpawnStray($msg, $chatId);
}

function grantReferralReward(array $msg, array $from): void
{
    $uid = (int) $from['id'];
    $ref = one('SELECT * FROM referrals WHERE user_id=? AND rewarded=0', [$uid]);
    if (!$ref) {
        return;
    }
    $inviterId = (int) $ref['inviter_id'];
    $rewardS = referralRewardSender();
    $rewardJ = referralRewardJoiner();

    addPoints($uid, $rewardJ);
    $inviterExists = val('SELECT 1 FROM users WHERE user_id=?', [$inviterId]) !== null;
    if ($inviterExists) {
        addPoints($inviterId, $rewardS);
    }
    q('UPDATE referrals SET rewarded=1 WHERE user_id=?', [$uid]);

    reply($msg, "🎁 <b>جایزه دعوت!</b>\n\n" . quote('+' . nf($rewardJ) . ' هاپ پوینت به خاطر ورود با لینک دعوت دریافت کردی!'));

    if ($inviterExists) {
        sendPrivate($inviterId,
            "🎉 <b>دعوت موفق!</b>\n\n"
            . quote(h(displayName($from)) . " با لینک دعوت تو وارد شد و اولین هاپش رو زد!\n💰 +" . nf($rewardS) . ' هاپ پوینت به حسابت واریز شد 🦴'));
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  🐕‍🦺  سگ خیابونی
// ═══════════════════════════════════════════════════════════════════════════

function maybeSpawnStray(array $msg, int $chatId): void
{
    $grp = getGroup($chatId);
    if (one('SELECT group_id FROM stray_dogs WHERE group_id=?', [$chatId])) {
        return;
    }
    $cityLvl = cityLevel($grp);
    $threshold = max(5, (int) round(STRAY_TRIGGER_HOPS * (1 - ($cityLvl - 1) * CITY_STRAY_BUFF)));
    if ((int) $grp['total_hops'] % $threshold !== 0) {
        return;
    }

    q("INSERT INTO stray_dogs (group_id, tries_left, current_cost, appeared_at, rescuer_ids)
       VALUES (?,?,?,?,'')
       ON CONFLICT(group_id) DO UPDATE SET tries_left=excluded.tries_left,
         current_cost=excluded.current_cost, appeared_at=excluded.appeared_at, rescuer_ids=''",
        [$chatId, STRAY_MAX_TRIES, STRAY_BASE_COST, nowIso()]);

    reply($msg,
        "🐕 <b>یه سگ خیابونی ظاهر شد!</b>\n\n"
        . quote(
            "😿 این سگ بیچاره کنار خیابونه و به کمک نیاز داره!\n"
            . "🍀 شانس نجات : " . (int) (STRAY_CHANCE * 100) . "٪\n"
            . "💰 هزینه تلاش : " . nf(STRAY_BASE_COST) . " هاپ پوینت\n"
            . "🔁 تلاش باقی‌مونده : " . STRAY_MAX_TRIES
        )
        . "\nکی اول نجاتش میده؟",
        kb([[btn('نجات (' . nf(STRAY_BASE_COST) . ' پوینت)', "rescue_{$chatId}", 'success')]]));
}

function cbRescue(array $cbq, int $groupId): void
{
    $from = $cbq['from'];
    $uid = (int) $from['id'];
    $cbId = $cbq['id'];
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];

    if (isJailed($uid)) {
        answerCb($cbId, '⛓️ تو زندانی! نمی‌تونی کمک کنی!', true);
        return;
    }

    $stray = one('SELECT * FROM stray_dogs WHERE group_id=?', [$groupId]);
    if (!$stray) {
        answerCb($cbId, '❌ این سگ دیگه اینجا نیست!', true);
        return;
    }

    $rescuers = array_filter(explode(',', (string) $stray['rescuer_ids']));
    if (in_array((string) $uid, $rescuers, true)) {
        answerCb($cbId, '❌ تو قبلاً تلاش کردی!', true);
        return;
    }

    ensureUser($from);
    $cost = (int) $stray['current_cost'];
    if (userPoints($uid) < $cost) {
        answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
        return;
    }

    deductPoints($uid, $cost);

    $success = chance(STRAY_CHANCE);
    $triesLeft = (int) $stray['tries_left'] - 1;
    $rescuers[] = (string) $uid;
    $newCost = (int) ($cost * STRAY_COST_MULT);
    $name = h(displayName($from));

    if ($success) {
        q('DELETE FROM stray_dogs WHERE group_id=?', [$groupId]);
        addStrays($uid, 1);
        answerCb($cbId, '🎉 نجاتش دادی!');
        editMsg($chatId, $mid,
            "🎉 <b>{$name} سگ رو نجات داد!</b>\n\n"
            . quote("🐕 سگ خیابونی الان در امنیته!\n🏅 +۱ به آمار نجات سگت اضافه شد!"),
            null);
        return;
    }

    if ($triesLeft <= 0) {
        q('DELETE FROM stray_dogs WHERE group_id=?', [$groupId]);
        answerCb($cbId, '😢 سگ فرار کرد!');
        editMsg($chatId, $mid,
            "😢 <b>سگ خیابونی فرار کرد!</b>\n\n"
            . quote("متأسفانه هیچ‌کس نتونست این سگ رو نجات بده...\nشاید دفعه بعد شانس بیشتری داشته باشید 🐾"),
            null);
        return;
    }

    q('UPDATE stray_dogs SET tries_left=?, current_cost=?, rescuer_ids=? WHERE group_id=?',
        [$triesLeft, $newCost, implode(',', $rescuers), $groupId]);

    answerCb($cbId, '😿 موفق نشدی!');
    editMsg($chatId, $mid,
        "😿 <b>{$name} موفق نشد!</b>\n\n"
        . quote(
            "سگ هنوز اینجاست ولی ترسیده‌تره...\n"
            . "🔁 تلاش باقی‌مونده : {$triesLeft}\n"
            . "💰 هزینه بعدی : " . nf($newCost) . ' هاپ پوینت'
        ),
        kb([[btn('تلاش دوباره (' . nf($newCost) . ' پوینت)', "rescue_{$groupId}", 'success')]]));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🚨  بحران شهری
// ═══════════════════════════════════════════════════════════════════════════

function maybeTriggerCrisis(array $msg, int $groupId): void
{
    if (activeCrisis($groupId)) {
        return;
    }
    $grp = getGroup($groupId);
    if ((float) $grp['treasury'] < CRISIS_MIN_TREASURY) {
        return;
    }
    if (!chance(CRISIS_TRIGGER_CHANCE)) {
        return;
    }

    $keys = array_keys(CRISIS_TYPES);
    $crisisKey = $keys[array_rand($keys)];
    $c = CRISIS_TYPES[$crisisKey];
    $maxCost = (int) ((float) $grp['treasury'] * $c['treasury_cost']);

    q("INSERT INTO city_crises (group_id, crisis_type, status, started_at, expires_at)
       VALUES (?,?,'active',?,?)", [$groupId, $crisisKey, nowIso(), isoPlus($c['timeout'])]);
    $crisisId = (int) db()->lastInsertId();

    $mayor = one('SELECT * FROM mayor WHERE group_id=?', [$groupId]);
    $mayorMention = $mayor
        ? '📣 شهردار ' . mentionId((int) $mayor['user_id'], (string) $mayor['first_name']) . " باید تصمیم بگیره!\n\n"
        : '';

    reply($msg,
        "🚨 <b>بحران شهری!</b>\n\n"
        . "<b>" . h($c['name']) . "</b>\n\n"
        . quote(h($c['desc']))
        . "\n" . $mayorMention
        . '⏳ <b>' . fmtDur($c['timeout']) . "</b> فرصت تصمیم‌گیری دارید!\n\n"
        . quote(
            "✅ پرداخت از خزانه : حل کامل + " . nf($c['reward']) . " پاداش\n"
            . "🔧 استفاده از منابع : حل جزئی، بدون پاداش\n"
            . "❌ نادیده گرفتن : " . h($c['penalty'])
        ),
        kb([
            [btn('پرداخت از خزانه (' . nf($maxCost) . ' هاپ)', "crisis_pay_{$crisisId}_{$groupId}", 'success')],
            [btn('استفاده از منابع شهر', "crisis_resource_{$crisisId}_{$groupId}", 'warning')],
            [btn('نادیده گرفتن (خطرناک!)', "crisis_ignore_{$crisisId}_{$groupId}", 'danger')],
        ]));
}

/** @return array لیست بحران‌های منقضی‌شده */
function expireOldCrises(): array
{
    $old = all("SELECT * FROM city_crises WHERE status='active' AND expires_at <= ?", [nowIso()]);
    foreach ($old as $row) {
        q("UPDATE city_crises SET status='ignored', decision='timeout' WHERE id=?", [(int) $row['id']]);
        applyCrisisPenalty((int) $row['group_id'], (string) $row['crisis_type']);
    }
    return $old;
}

function cbCrisis(array $cbq, string $action, int $crisisId, int $groupId): void
{
    $from = $cbq['from'];
    $uid = (int) $from['id'];
    $cbId = $cbq['id'];
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];

    $crisis = one('SELECT * FROM city_crises WHERE id=?', [$crisisId]);
    if (!$crisis || $crisis['status'] !== 'active') {
        answerCb($cbId, '❌ این بحران دیگه فعال نیست!', true);
        return;
    }

    if (!isFuture($crisis['expires_at'])) {
        resolveCrisis($crisisId, 'timeout');
        applyCrisisPenalty($groupId, (string) $crisis['crisis_type']);
        editMsg($chatId, $mid, "⏰ <b>وقت تموم شد!</b>\n\n" . quote('بحران مدیریت نشد و شهر جریمه گرفت.'), null);
        answerCb($cbId, '⏰ وقت تموم شد!', true);
        return;
    }

    $mayor = one('SELECT * FROM mayor WHERE group_id=?', [$groupId]);
    if (!$mayor || (int) $mayor['user_id'] !== $uid) {
        answerCb($cbId, '❌ فقط شهردار می‌تونه تصمیم بگیره!', true);
        return;
    }

    $c = CRISIS_TYPES[$crisis['crisis_type']] ?? null;
    if (!$c) {
        answerCb($cbId, '❌ نوع بحران نامعتبره!', true);
        return;
    }

    if ($action === 'pay') {
        $grp = getGroup($groupId);
        $cost = (int) ((float) $grp['treasury'] * $c['treasury_cost']);
        if ((float) $grp['treasury'] < $cost || $cost <= 0) {
            answerCb($cbId, '❌ خزانه کافی نیست! لازم: ' . nf($cost), true);
            return;
        }
        q('UPDATE groups SET treasury = treasury - ? WHERE group_id=?', [$cost, $groupId]);
        addPoints($uid, (int) $c['reward']);
        resolveCrisis($crisisId, 'pay', $uid);
        changeMayorPopularity($groupId, +5);
        logMayorAction($groupId, $uid, 'crisis', 'مدیریت بحران ' . $c['name']);

        answerCb($cbId, '✅ بحران حل شد!');
        editMsg($chatId, $mid,
            "✅ <b>بحران مدیریت شد!</b>\n\n<b>" . h($c['name']) . "</b>\n\n"
            . quote(
                "💰 " . nf($cost) . " هاپ از خزانه پرداخت شد.\n"
                . "🏆 شهردار " . nf($c['reward']) . " پاداش گرفت!\n"
                . "📈 محبوبیت شهردار +۵"
            )
            . "\n🏙 شهر دوباره آروم شد 🐾", null);
        return;
    }

    if ($action === 'resource') {
        applyCrisisPenalty($groupId, (string) $crisis['crisis_type'], true);
        resolveCrisis($crisisId, 'resource', $uid);
        $mins = intdiv($c['fix_cooldown'], 120);

        answerCb($cbId, '🔧 بحران تا حدی کنترل شد.');
        editMsg($chatId, $mid,
            "🔧 <b>بحران تا حدی کنترل شد!</b>\n\n<b>" . h($c['name']) . "</b>\n\n"
            . quote("📉 جریمه نصف شد: {$mins} دقیقه اثر می‌ذاره.\n💡 دفعه بعد از خزانه پرداخت کن تا کامل حل بشه!"), null);
        return;
    }

    if ($action === 'ignore') {
        resolveCrisis($crisisId, 'ignore', $uid);
        applyCrisisPenalty($groupId, (string) $crisis['crisis_type']);
        changeMayorPopularity($groupId, -10);
        $mins = intdiv($c['fix_cooldown'], 60);

        answerCb($cbId, '😤 بحران نادیده گرفته شد!');
        editMsg($chatId, $mid,
            "😤 <b>بحران نادیده گرفته شد!</b>\n\n<b>" . h($c['name']) . "</b>\n\n"
            . quote(
                "⚠️ جریمه فعال شد: " . h($c['penalty']) . "\n"
                . "⏳ مدت: {$mins} دقیقه\n"
                . "📉 محبوبیت شهردار -۱۰"
            )
            . "\nدفعه بعد بهتر تصمیم بگیر! 🏛", null);
    }
}

function cmdCrisisStatus(array $msg): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🚨 این دستور فقط توی گروه کار می‌کنه!');
        return;
    }
    $chatId = (int) $chat['id'];
    $crisis = activeCrisis($chatId);
    $penalty = activePenalty($chatId);

    if (!$crisis && !$penalty) {
        reply($msg, '✅ <b>شهر در آرامشه!</b> هیچ بحران یا جریمه‌ای فعال نیست 🏙');
        return;
    }

    $body = '';
    if ($crisis) {
        $c = CRISIS_TYPES[$crisis['crisis_type']] ?? ['name' => $crisis['crisis_type']];
        $body .= "🔴 بحران فعال :\n" . h($c['name']) . "\n⏳ " . fmtDur(secsLeft($crisis['expires_at'])) . " فرصت باقیه\n\n";
    }
    if ($penalty) {
        $labels = [
            'hop_cooldown'   => '🐾 کولداون هاپ بیشتر',
            'factory_freeze' => '🏭 کارخانه متوقف',
            'factory_slow'   => '🏭 کارخانه کند',
            'dog_freeze'     => '🐕 سگ‌ها متوقف',
            'dog_slow'       => '🐕 سگ‌ها کند',
        ];
        $label = $labels[$penalty['penalty_type']] ?? $penalty['penalty_type'];
        $body .= "⚠️ جریمه فعال :\n" . h($label) . "\n⏳ " . fmtDur(secsLeft($penalty['expires_at'])) . ' مونده';
    }

    reply($msg, "🚨 <b>وضعیت بحران شهر</b>\n\n" . quote(trim($body)));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🐕  پنل سگ
// ═══════════════════════════════════════════════════════════════════════════

/** ساخت متن + دکمه پنل سگ. @return array{0:string,1:array} */
function dogPanel(int $uid, int $groupId): array
{
    $u = getUser($uid);
    $dog = getDog($uid);
    if (!$dog) {
        return ["🐕 سگی نداری! بنویس <b>سگ</b> تا بخری.", kb([])];
    }

    $isFed = isFuture($dog['fed_until']);
    $pending = calcDogPoints($dog, $groupId) + (float) $dog['points_box'];
    [$rate, $capacity, $upgradeCost] = dogData((int) $dog['level']);
    $upgradeCost = withDiscount((int) $upgradeCost, upgradeDiscount());
    [$rankName, $rankMult] = dogRank((int) $dog['rank']);
    $buff = dogIncomeMultiplier($groupId);
    $effectiveRate = $rate * $rankMult * $buff;

    $fedStr = $isFed
        ? '✅ سیر (' . fmtClock(secsLeft($dog['fed_until'])) . ' مونده)'
        : '😋 گشنه — تولید متوقفه!';

    $body = "🏷️ اسم : " . h($dog['name']) . "\n"
        . "🎖 مقام : " . h($rankName) . "\n"
        . "⭐️ سطح : " . (int) $dog['level'] . '/' . DOG_MAX_LEVEL . "\n"
        . "⚡️ تولید : " . number_format($effectiveRate, 2) . " پوینت/ثانیه\n"
        . "📦 جعبه : " . nf($pending) . ' / ' . nf($capacity) . "\n"
        . "🍖 شکم : " . $fedStr;
    if ($buff > 1.0001) {
        $body .= "\n✨ باف فعال : ×" . number_format($buff, 2);
    } elseif ($buff < 0.9999) {
        $body .= "\n⚠️ جریمه فعال : ×" . number_format($buff, 2);
    }

    $text = "🐕 <b>سگت: " . h($dog['name']) . "</b>\n\n" . quote($body)
        . "\n👛 موجودی تو : " . nf($u['hop_points'] ?? 0);

    $rows = [];
    if ((int) $pending >= 1) {
        $rows[] = [btn('برداشت پوینت‌ها (' . nf((int) $pending) . ')', "collect_{$uid}", 'success')];
    }
    $rows[] = [btn('خرید استخوان — غذا (' . nf(withDiscount(BONE_COST, itemDiscount())) . ')', "feed_{$uid}", 'success')];
    if ((int) $dog['level'] < DOG_MAX_LEVEL) {
        $rows[] = [btn('ارتقا سطح (' . nf($upgradeCost) . ')', "upgradedog_{$uid}", 'success')];
    }
    if ((int) $dog['rank'] < DOG_MAX_RANK && (int) $dog['level'] >= (int) $dog['rank'] * 2) {
        $rows[] = [btn('ارتقا مقام 🏅', "rankup_{$uid}", 'gold')];
    }
    $rows[] = [btn('تغییر اسم ✏️', "rename_{$uid}", 'primary')];

    return [$text, kb($rows)];
}

function cmdDog(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🐕 این دستور فقط توی گروه کار می‌کنه!');
        return;
    }
    $uid = (int) $from['id'];
    $u = ensureUser($from);
    ensureGroup($chat);

    if (getDog($uid)) {
        [$text, $keyboard] = dogPanel($uid, (int) $chat['id']);
        reply($msg, $text, $keyboard);
        return;
    }

    if ((int) $u['level'] < DOG_MIN_LEVEL) {
        reply($msg, "🔒 برای خرید سگ باید حداقل سطح " . DOG_MIN_LEVEL . " باشی!\n⭐️ سطح فعلیت: " . (int) $u['level']);
        return;
    }
    $cost = withDiscount(DOG_COST, itemDiscount());
    if ((float) $u['hop_points'] < $cost) {
        reply($msg, "💸 برای خرید سگ به " . nf($cost) . " هاپ پوینت نیاز داری!\n💰 موجودیت: " . nf($u['hop_points']) . ' 🦴');
        return;
    }

    reply($msg,
        "🐕 <b>خرید سگ</b>\n\n"
        . quote(
            "💰 هزینه : " . nf($cost) . " هاپ پوینت\n"
            . "📦 نرخ تولید : 0.1 پوینت/ثانیه\n"
            . "🍖 هر ۲ ساعت باید بهش غذا (استخوان) بدی!"
        )
        . "\nمطمئنی می‌خوای سگ بخری؟",
        kb([[
            btn('خرید سگ', "buydog_{$uid}", 'success'),
            btn('انصراف', 'cancel', 'danger'),
        ]]));
}

/** ویرایش پیام به پنل سگ */
function showDogPanelEdit(array $cbq, int $uid): void
{
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];
    [$text, $keyboard] = dogPanel($uid, $chatId);
    editMsg($chatId, $mid, $text, $keyboard);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🦴  پنل قلاب
// ═══════════════════════════════════════════════════════════════════════════

function hookPanel(int $uid, int $groupId): array
{
    $u = getUser($uid);
    $hook = getHook($uid);
    if (!$hook) {
        return ["🦴 قلاب نداری! بنویس <b>قلاب</b> تا بخری.", kb([])];
    }

    $lvl = (int) $hook['level'];
    [, $cd, $upgradeCost] = hookData($lvl);
    $upgradeCost = withDiscount((int) $upgradeCost, upgradeDiscount());
    $grp = getGroup($groupId);
    $realCd = (int) round(cityFishCooldown(cityLevel($grp), (int) $cd) * cooldownMultiplier($groupId));

    $status = '✅ آماده صید!';
    if (!empty($hook['last_cast'])) {
        $left = $realCd - (time() - ts($hook['last_cast']));
        if ($left > 0) {
            $status = '⌛️ ' . fmtDur($left) . ' مونده';
        }
    }

    $text = "🦴 <b>قلاب استخوان‌گیری</b>\n\n"
        . quote(
            "⭐️ سطح قلاب : {$lvl}/" . HOOK_MAX_LEVEL . "\n"
            . "⌛️ کولداون : {$realCd} ثانیه\n"
            . "🎯 وضعیت : {$status}"
        )
        . "\n👛 موجودی : " . nf($u['hop_points'] ?? 0);

    $rows = [];
    if ($lvl < HOOK_MAX_LEVEL) {
        $text .= "\n💰 هزینه ارتقا : " . nf($upgradeCost);
        $rows[] = [btn('ارتقا قلاب (' . nf($upgradeCost) . ')', "upgradehook_{$uid}", 'success')];
    }
    return [$text, kb($rows)];
}

function cmdHook(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🦴 این دستور فقط توی گروه کار می‌کنه!');
        return;
    }
    $uid = (int) $from['id'];
    $u = ensureUser($from);
    ensureGroup($chat);

    if (getHook($uid)) {
        [$text, $keyboard] = hookPanel($uid, (int) $chat['id']);
        reply($msg, $text, $keyboard);
        return;
    }

    if ((int) $u['level'] < HOOK_MIN_LEVEL) {
        reply($msg, "🔒 برای خرید قلاب باید حداقل سطح " . HOOK_MIN_LEVEL . " باشی!\n⭐️ سطح فعلیت: " . (int) $u['level']);
        return;
    }

    [$costBuy, $cooldown] = hookData(1);
    $costBuy = withDiscount((int) $costBuy, itemDiscount());
    reply($msg,
        "🦴 <b>قلاب استخوان‌گیری</b>\n\n"
        . quote(
            "💰 هزینه : " . nf($costBuy) . " هاپ پوینت\n"
            . "⌛️ کولداون اولیه : {$cooldown} ثانیه\n"
            . "📦 موجودی تو : " . nf($u['hop_points'])
        )
        . "\nبعد از خرید بنویس <b>استخوان</b> تا بندازی!",
        kb([[
            btn('خرید قلاب (' . nf($costBuy) . ' پوینت)', "buyhook_{$uid}", 'success'),
            btn('انصراف', 'cancel', 'danger'),
        ]]));
}

function showHookPanelEdit(array $cbq, int $uid): void
{
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];
    [$text, $keyboard] = hookPanel($uid, $chatId);
    editMsg($chatId, $mid, $text, $keyboard);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🎣  انداختن قلاب (صید)
// ═══════════════════════════════════════════════════════════════════════════

function cmdCast(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🦴 این دستور فقط توی گروه کار می‌کنه!');
        return;
    }
    $uid = (int) $from['id'];
    $chatId = (int) $chat['id'];
    ensureUser($from);
    ensureGroup($chat);

    $hook = getHook($uid);
    if (!$hook) {
        reply($msg, "❌ هنوز قلاب نداری!\n📌 بنویس <b>قلاب</b> تا بخری 🦴");
        return;
    }

    // صید تصمیم‌نگرفته قبلی
    $pending = one('SELECT * FROM pending_bones WHERE user_id=?', [$uid]);
    if ($pending) {
        $age = time() - ts($pending['caught_at']);
        if ($age < BONE_SELL_TIME) {
            $icon = boneRarity((string) $pending['bone_name']);
            reply($msg,
                "⚠️ هنوز روی صید قبلیت تصمیم نگرفتی!\n\n"
                . quote(
                    "🦴 <b>" . h($pending['bone_name']) . "</b>\n"
                    . "🏷️ دسته : {$icon} " . (RARITY_ICON_MAP[$icon] ?? '') . "\n"
                    . "⚖️ وزن : " . $pending['weight'] . " kg\n"
                    . "💰 ارزش : " . nf($pending['price']) . ' هاپ پوینت'
                )
                . "\n⌛️ " . (BONE_SELL_TIME - $age) . ' ثانیه فرصت داری!',
                kb([[
                    btn('فروش 💰', "sellbone_{$uid}", 'success'),
                    btn('غذای سگ 🍖', "feeddog_{$uid}", 'warning'),
                ]]));
            return;
        }
        q('DELETE FROM pending_bones WHERE user_id=?', [$uid]);
    }

    $lvl = (int) $hook['level'];
    [, $baseCd] = hookData($lvl);
    $grp = getGroup($chatId);
    $cd = (int) round(cityFishCooldown(cityLevel($grp), (int) $baseCd) * cooldownMultiplier($chatId));

    if (!empty($hook['last_cast'])) {
        $left = $cd - (time() - ts($hook['last_cast']));
        if ($left > 0) {
            reply($msg, "⌛️ قلابت هنوز توی آبه!\n🕐 " . fmtDur($left) . ' دیگه می‌تونی دوباره بندازی 🦴');
            return;
        }
    }

    [$boneName, $weight, $price, $rarityIcon, $rarityName] = catchBone($lvl);
    $caughtAt = nowIso();

    q('UPDATE hooks SET last_cast=? WHERE user_id=?', [$caughtAt, $uid]);
    q('INSERT INTO pending_bones (user_id, bone_name, weight, price, caught_at) VALUES (?,?,?,?,?)
       ON CONFLICT(user_id) DO UPDATE SET bone_name=excluded.bone_name, weight=excluded.weight,
         price=excluded.price, caught_at=excluded.caught_at',
        [$uid, $boneName, $weight, $price, $caughtAt]);
    q('UPDATE groups SET total_bones = total_bones + 1, total_fish = total_fish + 1 WHERE group_id=?', [$chatId]);

    $name = h(displayName($from));
    $header = match ($rarityIcon) {
        '🔥' => "🔥🔥🔥 <b>{$name} یه استخوان آتشی صید کرد!</b> 🔥🔥🔥",
        '🔴' => "💥 <b>{$name} یه استخوان کمیاب صید کرد!</b> 💥",
        '🟠' => "✨ <b>{$name} یه استخوان اسطوره‌ای صید کرد!</b> ✨",
        default => "🎣 <b>{$name} یه استخوان صید کرد!</b>",
    };

    reply($msg,
        $header . "\n\n"
        . quote(
            "🦴 " . h($boneName) . "\n"
            . "🏷️ دسته : {$rarityIcon} {$rarityName}\n"
            . "⚖️ وزن : {$weight} kg\n"
            . "💰 ارزش فروش : " . nf($price) . ' هاپ پوینت'
        )
        . "\n⌛️ " . BONE_SELL_TIME . ' ثانیه فرصت داری تصمیم بگیری!',
        kb([[
            btn('فروش 💰', "sellbone_{$uid}", 'success'),
            btn('غذای سگ 🍖', "feeddog_{$uid}", 'warning'),
        ]]));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏦  بانک
// ═══════════════════════════════════════════════════════════════════════════

function getBank(int $userId): ?array
{
    return one('SELECT * FROM bank WHERE user_id=?', [$userId]);
}

function genAccountNumber(): string
{
    do {
        $num = '';
        for ($i = 0; $i < 12; $i++) {
            $num .= (string) mt_rand(0, 9);
        }
    } while (val('SELECT 1 FROM bank WHERE account_number=?', [$num]) !== null);
    return $num;
}

function bankPanel(int $uid, int $groupId): array
{
    $u = getUser($uid);
    $bank = getBank($uid);
    if (!$bank) {
        return ["🏦 حساب بانکی نداری! بنویس <b>بانک</b> تا بسازی.", kb([])];
    }

    $interestReady = empty($bank['last_interest']) || (time() - ts($bank['last_interest'])) >= 86400;
    $rate = BANK_INTEREST_RATE * bankInterestMultiplier($groupId);
    $interestAmount = min((float) $bank['balance'] * $rate, BANK_MAX_INTEREST);

    $body = "💳 شماره حساب : <code>" . h($bank['account_number']) . "</code>\n"
        . "💰 موجودی بانک : " . nf($bank['balance']) . "\n"
        . "👛 موجودی کیف : " . nf($u['hop_points'] ?? 0) . "\n"
        . "📈 سود روزانه (" . number_format($rate * 100, 1) . "٪) : " . nf($interestAmount) . "\n"
        . ($interestReady
            ? '✅ سود آماده دریافته!'
            : '⌛️ ' . fmtClock(86400 - (time() - ts($bank['last_interest']))) . ' تا سود بعدی');

    $text = "🏦 <b>بانک هاپی</b>\n\n" . quote($body);

    $rows = [[
        btn('واریز ➕', "bank_deposit_{$uid}", 'success'),
        btn('برداشت ➖', "bank_withdraw_{$uid}", 'warning'),
    ]];
    if ($interestReady && (float) $bank['balance'] > 0) {
        $rows[] = [btn('دریافت سود 💸', "bank_interest_{$uid}", 'success')];
    }
    $rows[] = [btn('تغییر شماره حساب 🔄', "bank_changenum_{$uid}", 'primary')];

    return [$text, kb($rows)];
}

function cmdBank(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🏦 بانک فقط توی گروه کار می‌کنه!');
        return;
    }
    $uid = (int) $from['id'];
    [$jailed, $jailRow] = jailStatus($uid);
    if ($jailed) {
        reply($msg, "⛓️ تو زندانی! نمی‌تونی به بانک دسترسی داشته باشی!\n⌛️ " . fmtDur(secsLeft($jailRow['release_at'])) . ' تا آزادی');
        return;
    }

    $u = ensureUser($from);
    ensureGroup($chat);

    if ((int) $u['level'] < BANK_MIN_LEVEL) {
        reply($msg, "🔒 برای باز کردن حساب بانکی باید حداقل سطح " . BANK_MIN_LEVEL . " باشی!\n⭐️ سطح فعلیت: " . (int) $u['level']);
        return;
    }

    if (getBank($uid)) {
        [$text, $keyboard] = bankPanel($uid, (int) $chat['id']);
        reply($msg, $text, $keyboard);
        return;
    }

    $cost = withDiscount(BANK_OPEN_COST, itemDiscount());
    reply($msg,
        "🏦 <b>بانک هاپی</b>\n\n"
        . quote(
            "💰 هزینه افتتاح : " . nf($cost) . " هاپ پوینت\n"
            . "📈 سود روزانه : ۳٪ (حداکثر " . nf(BANK_MAX_INTEREST) . ")\n"
            . "💳 شماره حساب ۱۲ رقمی اختصاصی\n"
            . "🔄 امکان کارت به کارت"
        )
        . "\n👛 موجودی تو : " . nf($u['hop_points']) . ' 🦴',
        kb([[
            btn('افتتاح حساب (' . nf($cost) . ' پوینت)', "openbank_{$uid}", 'success'),
            btn('انصراف', 'cancel', 'danger'),
        ]]));
}

function showBankPanelEdit(array $cbq, int $uid): void
{
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];
    [$text, $keyboard] = bankPanel($uid, $chatId);
    editMsg($chatId, $mid, $text, $keyboard);
}

// ═══════════════════════════════════════════════════════════════════════════
//  💸  انتقال پوینت
// ═══════════════════════════════════════════════════════════════════════════

function cmdTransfer(array $msg, array $from, string $text): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🧲 انتقال فقط توی گروه کار می‌کنه!');
        return;
    }
    $uid = (int) $from['id'];
    if (isJailed($uid)) {
        reply($msg, '⛓️ تو زندانی! نمی‌تونی انتقال بدی!');
        return;
    }

    $u = ensureUser($from);
    if ((int) $u['level'] < TRANSFER_MIN_LEVEL) {
        reply($msg, '🔒 برای انتقال باید حداقل سطح ' . TRANSFER_MIN_LEVEL . ' باشی!');
        return;
    }

    $tr = one('SELECT * FROM transfers WHERE user_id=?', [$uid]);
    if ($tr && !empty($tr['last_transfer'])) {
        $left = TRANSFER_COOLDOWN - (time() - ts($tr['last_transfer']));
        if ($left > 0) {
            reply($msg, "⌛️ {$left} ثانیه تا انتقال بعدی صبر کن!");
            return;
        }
    }

    $args = preg_split('/\s+/u', trim($text)) ?: [];
    $targetId = 0;
    $targetName = '';
    $amount = -1;

    if (isset($msg['reply_to_message']['from'])) {
        $t = $msg['reply_to_message']['from'];
        $targetId = (int) $t['id'];
        $targetName = displayName($t);
        if (count($args) >= 2) {
            $amount = parseAmount($args[1]);
        }
    } elseif (count($args) >= 3) {
        $amount = parseAmount($args[1]);
        $username = ltrim($args[2], '@');
        $row = one('SELECT * FROM users WHERE username=? COLLATE NOCASE OR CAST(user_id AS TEXT)=?', [$username, $username]);
        if (!$row) {
            reply($msg, '❌ کاربری با این یوزرنیم پیدا نشد!');
            return;
        }
        $targetId = (int) $row['user_id'];
        $targetName = (string) $row['first_name'];
    } else {
        reply($msg,
            "📌 <b>فرمت انتقال</b>\n\n"
            . quote("<code>انتقال {مبلغ} @یوزرنیم</code>\n\nیا روی پیام کاربر ریپلای کن و بنویس:\n<code>انتقال {مبلغ}</code>"));
        return;
    }

    if ($targetId === 0 || $targetId === $uid) {
        reply($msg, '❌ نمی‌تونی به خودت انتقال بدی!');
        return;
    }
    if ($amount <= 0) {
        reply($msg, '❌ مبلغ نامعتبره!');
        return;
    }
    if ($amount < TRANSFER_MIN) {
        reply($msg, '❌ حداقل مبلغ انتقال: ' . nf(TRANSFER_MIN) . ' هاپ پوینت');
        return;
    }
    if ($amount > TRANSFER_MAX) {
        reply($msg, '❌ حداکثر مبلغ انتقال: ' . nf(TRANSFER_MAX) . ' هاپ پوینت');
        return;
    }

    $target = getUser($targetId);
    if (!$target || (int) $target['level'] < TRANSFER_MIN_LEVEL) {
        reply($msg, '❌ کاربر مقصد باید حداقل سطح ' . TRANSFER_MIN_LEVEL . ' باشه!');
        return;
    }
    if ((float) $u['hop_points'] < $amount) {
        reply($msg, "❌ موجودی کافی نداری!\n💰 موجودی: " . nf($u['hop_points']));
        return;
    }

    transact(static function () use ($uid, $targetId, $amount) {
        deductPoints($uid, $amount);
        addPoints($targetId, $amount);
        q('INSERT INTO transfers (user_id, last_transfer) VALUES (?,?)
           ON CONFLICT(user_id) DO UPDATE SET last_transfer=excluded.last_transfer', [$uid, nowIso()]);
        q('INSERT INTO transactions (user_id, type, amount, created_at) VALUES (?,?,?,?)',
            [$uid, 'transfer_out', $amount, nowIso()]);
    });

    reply($msg,
        "✅ <b>انتقال موفق!</b>\n\n"
        . quote(
            "💸 " . nf($amount) . " هاپ پوینت به " . h($targetName ?: $target['first_name']) . " منتقل شد!\n"
            . "👛 موجودی جدیدت : " . nf((float) $u['hop_points'] - $amount)
        ));
}

// ═══════════════════════════════════════════════════════════════════════════
//  ⛓  زندان (پنل)
// ═══════════════════════════════════════════════════════════════════════════

function cmdJail(array $msg, array $from, string $text): void
{
    $uid = (int) $from['id'];

    // ── حالت ادمین: زندانی کردن دیگران ────────────────────────────────────
    if (isAdmin($uid)) {
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        $target = null;
        $mins = 30;

        if (isset($msg['reply_to_message']['from'])) {
            $t = $msg['reply_to_message']['from'];
            if (isset($parts[1]) && ctype_digit(faToEn($parts[1]))) {
                $mins = max(1, (int) faToEn($parts[1]));
            }
            jailUser((int) $t['id'], 'زندان توسط ادمین', $mins * 60);
            reply($msg, '⛓️ <b>' . h(displayName($t)) . " به زندان فرستاده شد!</b>\n⌛️ مدت: {$mins} دقیقه");
            return;
        }

        if (count($parts) >= 2) {
            $needle = ltrim($parts[1], '@');
            $mins = (isset($parts[2]) && ctype_digit(faToEn($parts[2]))) ? max(1, (int) faToEn($parts[2])) : 30;
            $target = one('SELECT * FROM users WHERE username=? COLLATE NOCASE OR CAST(user_id AS TEXT)=?', [$needle, $needle]);
            if (!$target) {
                reply($msg, '❌ کاربر پیدا نشد!');
                return;
            }
            jailUser((int) $target['user_id'], 'زندان توسط ادمین', $mins * 60);
            reply($msg, '⛓️ <b>' . h($target['first_name']) . " به زندان فرستاده شد!</b>\n⌛️ مدت: {$mins} دقیقه");
            return;
        }
    }

    // ── پنل زندانی ─────────────────────────────────────────────────────────
    [$jailed, $jailRow] = jailStatus($uid);
    if (!$jailed) {
        reply($msg, '✅ تو آزادی! توی زندان نیستی 🐕');
        return;
    }

    ensureUser($from);
    $leftSec = secsLeft($jailRow['release_at']);
    $bailCost = (int) (($leftSec / 60) * BAIL_COST_PER_MIN);
    $workPts = (int) ($jailRow['work_points'] ?? 0);
    $u = getUser($uid);

    reply($msg,
        "⛓️ <b>تو زندانی!</b>\n\n"
        . quote(
            "📌 دلیل : " . h($jailRow['reason']) . "\n"
            . "⌛️ زمان آزادی : " . fmtDur($leftSec) . " دیگه\n"
            . "💸 آزادی با پوینت : " . nf($bailCost) . "\n"
            . "⛏ پوینت جمع‌شده از کار : " . nf($workPts) . "\n"
            . "👛 موجودی : " . nf($u['hop_points'] ?? 0)
        )
        . "\n🗝 فرار: " . (int) (JAIL_ESCAPE_CHANCE * 100) . '٪ شانس — اگه گرفتن، +' . intdiv(JAIL_ESCAPE_PENALTY, 60) . ' دقیقه اضافه میشه!',
        kb([
            [
                btn('آزادی (' . nf($bailCost) . ' پوینت)', "bail_{$uid}", 'success'),
                btn('فرار! 🗝', "jail_escape_{$uid}", 'danger'),
            ],
            [btn('کار در زندان (+' . JAIL_WORK_EARN . ' پوینت) ⛏', "jail_work_{$uid}", 'warning')],
        ]));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🥷  قاچاق
// ═══════════════════════════════════════════════════════════════════════════

function cmdContraband(array $msg, array $from): void
{
    $uid = (int) $from['id'];
    $u = ensureUser($from);

    if ((int) $u['level'] < SMUGGLE_MIN_LEVEL) {
        reply($msg, '❌ داداش برای ورود به دنیای سیاه و پرخطر قاچاق، باید حداقل به <b>سطح ' . SMUGGLE_MIN_LEVEL . '</b> رسیده باشی! 🥷');
        return;
    }

    [$jailed, $jailRow] = jailStatus($uid);
    if ($jailed) {
        reply($msg, "⛓️ تو خودت الان زندانی هستی! نمی‌تونی باند قاچاق راه بندازی!\n⌛️ " . fmtDur(secsLeft($jailRow['release_at'])) . ' تا آزادی');
        return;
    }

    $strays = strayCount($uid);
    if ($strays < SMUGGLE_MIN_STRAYS) {
        reply($msg, '❌ برای راهی کردن محموله، باید حداقل <b>' . SMUGGLE_MIN_STRAYS . ' تا سگ خیابانی</b> نجات داده باشی! 🐕');
        return;
    }

    $maxSmuggle = min($strays, 15);
    $rows = [];
    $rowBtns = [];
    for ($n = SMUGGLE_MIN_STRAYS; $n <= $maxSmuggle; $n++) {
        $risk = (int) round((SMUGGLE_BASE_CATCH + $n * SMUGGLE_CATCH_PER) * 100);
        $rowBtns[] = btn("{$n} سگ 🐕 ({$risk}٪ خطر)", "smuggle_{$n}_{$uid}", 'warning');
        if (count($rowBtns) === 2) {
            $rows[] = $rowBtns;
            $rowBtns = [];
        }
    }
    if ($rowBtns !== []) {
        $rows[] = $rowBtns;
    }
    $rows[] = [btn('انصراف', 'cancel', 'danger')];

    reply($msg,
        "🥷 <b>به بخش قاچاق زیرزمینی هاپویی خوش آمدی!</b>\n\n"
        . quote(
            "🐕 موجودی سگ خیابونی تو : {$strays}\n"
            . "💰 پاداش هر سگ : " . nf(SMUGGLE_REWARD_EACH) . "\n"
            . "⛓ مجازات لو رفتن : " . SMUGGLE_JAIL_MINS . ' دقیقه زندان'
        )
        . "\n⚠️ <i>هر چی تعداد بالاتر بره، شانس لو رفتن بیشتر میشه!</i>",
        kb($rows));
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏭  پنل کارخانه
// ═══════════════════════════════════════════════════════════════════════════

function factoryPanel(int $uid, int $groupId): array
{
    $factory = getFactory($uid);
    if (!$factory) {
        return ["🏭 کارخونه نداری! بنویس <b>کارخونه</b> تا بسازی.", kb([])];
    }

    $workers = strayCount($uid);
    $cap = WAREHOUSE_LEVELS[(int) $factory['warehouse_level']][0] ?? 20;
    $machineCd = MACHINE_LEVELS[(int) $factory['machine_level']][0] ?? 120;
    $speed = factorySpeedMultiplier($groupId);
    $realCd = $speed > 0 ? max(3, (int) round($machineCd / $speed)) : $machineCd;

    $status = '💤 بیکار';
    $timeLeft = '';
    if (productionDone($factory)) {
        $status = '✅ محصول آماده جمع‌آوری!';
    } elseif ((int) $factory['producing'] && !empty($factory['production_end'])) {
        $status = '⚙️ در حال تولید';
        $timeLeft = "\n⌛️ " . fmtDur(secsLeft($factory['production_end'])) . ' تا آماده شدن';
    }

    $expNeeded = FACTORY_LEVEL_EXP[(int) $factory['level']] ?? 0;
    $expStr = $expNeeded > 0
        ? (int) $factory['exp'] . "/{$expNeeded} " . bar((int) $factory['exp'], $expNeeded)
        : 'MAX 🏆';

    $productName = (int) $factory['producing']
        ? (FACTORY_PRODUCTS[(int) $factory['product_idx']][0] ?? '—')
        : '—';

    $body = "⭐️ سطح کارخونه : " . (int) $factory['level'] . '/' . FACTORY_MAX_LEVEL . "\n"
        . "📊 تجربه : {$expStr}\n\n"
        . "🏗️ دستگاه : سطح " . (int) $factory['machine_level'] . " | ⌛️ {$realCd} ثانیه\n"
        . "🧳 انبار : سطح " . (int) $factory['warehouse_level'] . ' | ' . (int) $factory['stock'] . "/{$cap} محصول\n"
        . "🐕 کارگرها : {$workers} هاپو\n\n"
        . "🔄 وضعیت : {$status}\n"
        . "📦 محصول فعلی : " . h($productName) . $timeLeft;
    if ($speed > 1.0001) {
        $body .= "\n✨ باف سرعت : ×" . number_format($speed, 2);
    } elseif ($speed < 0.9999) {
        $body .= "\n⚠️ جریمه سرعت : ×" . number_format($speed, 2);
    }

    $text = "🏭 <b>کارخونه هاپویی 🐾</b>\n\n" . quote($body);

    $rows = [];
    if (productionDone($factory)) {
        $rows[] = [btn('جمع‌آوری محصولات 📦', "factory_collect_{$uid}", 'success')];
    }
    $rows[] = [
        btn('تولید محصول 🛒', "factory_produce_{$uid}", 'primary'),
        btn('فروش در بازار 💹', "factory_sell_{$uid}", 'success'),
    ];
    $rows[] = [
        btn('ارتقا دستگاه ⬆️', "factory_upmachine_{$uid}", 'success'),
        btn('ارتقا انبار ⬆️', "factory_upwarehouse_{$uid}", 'success'),
    ];
    $rows[] = [
        btn('استخدام هاپو 🐕', "factory_hire_{$uid}", 'primary'),
        btn('خرید کارگر (' . nf(FACTORY_WORKER_COST) . ')', "factory_buyworker_{$uid}", 'success'),
    ];

    return [$text, kb($rows)];
}

function cmdFactory(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🏭 کارخونه فقط توی گروه کار می‌کنه!');
        return;
    }
    $uid = (int) $from['id'];
    $u = ensureUser($from);
    ensureGroup($chat);

    if ((int) $u['level'] < FACTORY_MIN_LEVEL) {
        reply($msg,
            "🔒 <b>کارخونه میویی</b>\n\n"
            . quote("برای ساخت کارخونه باید حداقل <b>سطح " . FACTORY_MIN_LEVEL . "</b> باشی!\n⭐️ سطح فعلیت : " . (int) $u['level'])
            . "\n💪 بیشتر هاپ بزن تا سطحت بالا بره!");
        return;
    }

    if (getFactory($uid)) {
        [$text, $keyboard] = factoryPanel($uid, (int) $chat['id']);
        reply($msg, $text, $keyboard);
        return;
    }

    $cost = withDiscount(FACTORY_BUILD_COST, itemDiscount());
    reply($msg,
        "🏭 <b>کارخونه میویی</b>\n\n"
        . quote(
            "هنوز کارخونه نداری! 😿\n\n"
            . "💰 هزینه ساخت : " . nf($cost) . " پوینت\n"
            . "👛 موجودی تو : " . nf($u['hop_points']) . "\n"
            . "🐕 کارگرهات : " . strayCount($uid) . ' هاپو'
        )
        . "\n✨ با کارخونه می‌تونی محصول تولید کنی و بفروشی!",
        kb([[
            btn('ساخت کارخونه (' . nf($cost) . ' پوینت)', "factory_build_{$uid}", 'success'),
            btn('انصراف', 'cancel', 'danger'),
        ]]));
}

function showFactoryPanelEdit(array $cbq, int $uid): void
{
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];
    [$text, $keyboard] = factoryPanel($uid, $chatId);
    editMsg($chatId, $mid, $text, $keyboard);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏰  شهر، اهدا، پروفایل، لیدربرد، راهنما
// ═══════════════════════════════════════════════════════════════════════════

function cmdCity(array $msg): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🏰 دستور شهر فقط توی گروه کار می‌کنه!');
        return;
    }
    $chatId = (int) $chat['id'];
    ensureGroup($chat);
    $grp = getGroup($chatId);
    $cityLvl = cityLevel($grp);

    // رتبه جهانی شهر
    $allGroups = all('SELECT group_id, treasury, total_hops, total_dogs, total_bones, total_fish FROM groups');
    usort($allGroups, static function ($a, $b) {
        $la = cityLevel($a);
        $lb = cityLevel($b);
        if ($la !== $lb) {
            return $lb <=> $la;
        }
        return (float) $b['treasury'] <=> (float) $a['treasury'];
    });
    $rank = 1;
    foreach ($allGroups as $i => $g) {
        if ((int) $g['group_id'] === $chatId) {
            $rank = $i + 1;
            break;
        }
    }

    $title = $grp['title'] ?: 'شهر هاپو';

    if ($cityLvl < CITY_MAX_LEVEL) {
        $nxt = CITY_LEVELS[$cityLvl + 1];
        $progress = "\n📊 <b>پیشرفت به سطح " . ($cityLvl + 1) . ":</b>\n"
            . quote(
                "🏦 خزانه : " . nf($grp['treasury']) . ' / ' . nf($nxt[0]) . '  ' . bar((float) $grp['treasury'], $nxt[0]) . "\n"
                . "🐾 هاپ کل : " . nf($grp['total_hops']) . ' / ' . nf($nxt[1]) . '  ' . bar((int) $grp['total_hops'], $nxt[1]) . "\n"
                . "🐕 سگ‌ها : " . nf($grp['total_dogs']) . ' / ' . nf($nxt[2]) . '  ' . bar((int) $grp['total_dogs'], $nxt[2]) . "\n"
                . "🦴 استخوان‌ها : " . nf($grp['total_bones']) . ' / ' . nf($nxt[3]) . '  ' . bar((int) $grp['total_bones'], $nxt[3]) . "\n"
                . "🎣 ماهی‌ها : " . nf($grp['total_fish']) . ' / ' . nf($nxt[4]) . '  ' . bar((int) $grp['total_fish'], $nxt[4])
            );
    } else {
        $progress = "\n🏆 <b>شهر به سطح ماکس رسیده!</b>";
    }

    $hopCd = cityHopCooldown($cityLvl);
    $fishRed = ($cityLvl - 1) * CITY_FISH_BUFF;
    $strayRed = (int) (($cityLvl - 1) * CITY_STRAY_BUFF * 100);
    $stars = $cityLvl <= 5 ? str_repeat('⭐️', $cityLvl) : str_repeat('⭐️', 5) . ' +' . ($cityLvl - 5);

    $mayor = one('SELECT * FROM mayor WHERE group_id=?', [$chatId]);
    $mayorLine = $mayor
        ? "\n🏛 شهردار : " . h($mayor['first_name']) . ' (محبوبیت ' . (int) $mayor['popularity'] . "٪)"
        : "\n🏛 شهردار : ندارد — بنویس <b>شهرداری</b>";

    reply($msg,
        "🏰 <b>شهر هاپو 🐾</b>\n\n"
        . quote(
            "🏙️ نام : " . h($title) . "\n"
            . "🎖️ رتبه جهانی : #{$rank}\n"
            . $stars
        )
        . "\n📈 <b>آمار شهر:</b>\n"
        . quote(
            "⭐️ سطح : {$cityLvl} / " . CITY_MAX_LEVEL . "\n"
            . "🏦 خزانه : " . nf($grp['treasury']) . " 🦴\n"
            . "🐾 کل هاپ : " . nf($grp['total_hops']) . "\n"
            . "🐕 کل سگ : " . nf($grp['total_dogs']) . "\n"
            . "🦴 کل استخوان : " . nf($grp['total_bones']) . "\n"
            . "🎣 کل ماهی : " . nf($grp['total_fish'])
            . $mayorLine
        )
        . "\n✨ <b>باف‌های فعال (سطح {$cityLvl}):</b>\n"
        . quote(
            "🐾 کولداون هاپ : {$hopCd}s (اصلی " . HOP_COOLDOWN . "s)\n"
            . "🎣 کاهش کولداون ماهیگیری : {$fishRed}s\n"
            . "🐈 کاهش آستانه سگ خیابونی : {$strayRed}٪"
        )
        . $progress
        . "\n\n💡 برای کمک به خزانه بنویس: <b>اهدا [مقدار]</b>");
}

function cmdDonate(array $msg, array $from, string $text): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🏦 این دستور فقط توی گروه کار می‌کنه!');
        return;
    }
    $parts = preg_split('/\s+/u', trim($text)) ?: [];
    if (count($parts) < 2) {
        reply($msg, '✍️ فرمت درست: <b>اهدا 500</b>');
        return;
    }
    $amount = parseAmount($parts[1]);
    if ($amount <= 0) {
        reply($msg, '❌ مقدار نامعتبره! یه عدد مثبت بنویس.');
        return;
    }

    $uid = (int) $from['id'];
    $chatId = (int) $chat['id'];
    $u = ensureUser($from);
    if ((float) $u['hop_points'] < $amount) {
        reply($msg, "❌ پوینت کافی نداری!\n💰 موجودی: " . nf($u['hop_points']) . ' هاپ پوینت');
        return;
    }

    ensureGroup($chat);
    $oldLvl = cityLevel(getGroup($chatId));

    transact(static function () use ($uid, $chatId, $amount) {
        deductPoints($uid, $amount);
        q('UPDATE groups SET treasury = treasury + ? WHERE group_id=?', [$amount, $chatId]);
    });

    $newGrp = getGroup($chatId);
    $newLvl = cityLevel($newGrp);

    $text2 = "🏦 <b>" . h(displayName($from)) . "</b> " . nf($amount) . " هاپ پوینت به خزانه شهر اهدا کرد! 🎉\n\n"
        . quote('💰 خزانه فعلی : ' . nf($newGrp['treasury']));
    if ($newLvl > $oldLvl) {
        $text2 .= "\n\n🎊 <b>شهر به سطح {$newLvl} ارتقا پیدا کرد!</b> 🏰";
    }
    reply($msg, $text2);
}

/** پروفایل کاربر (خود یا ریپلای‌شده) */
function sendProfile(array $msg, array $targetFrom): void
{
    $tid = (int) $targetFrom['id'];
    $u = getUser($tid);
    if (!$u) {
        $isSelf = (int) ($msg['from']['id'] ?? 0) === $tid;
        reply($msg, $isSelf
            ? '🐾 هنوز هاپ نزدی! اول بنویس <b>هاپ</b> تا ثبت بشی!'
            : '🐾 <b>' . h(displayName($targetFrom)) . '</b> هنوز هاپ نزده و ثبت نشده!');
        return;
    }

    $dog = getDog($tid);
    $hook = getHook($tid);
    $strayCount = strayCount($tid);
    [$jailed, $jailRow] = jailStatus($tid);

    $rPts = (int) val('SELECT COUNT(*) + 1 FROM users WHERE hop_points > ?', [(float) $u['hop_points']], 1);
    $rHops = (int) val('SELECT COUNT(*) + 1 FROM users WHERE total_hops > ?', [(int) $u['total_hops']], 1);
    $rStray = (int) val('SELECT COUNT(*) + 1 FROM user_strays WHERE count > ?', [$strayCount], 1);

    $lvl = (int) $u['level'];
    $hops = (int) $u['total_hops'];
    $nextHops = hopsForNextLevel($lvl);
    if ($nextHops > 0 && $lvl > 1) {
        $prevHops = hopsForNextLevel($lvl - 1);
        $progress = $hops - $prevHops;
        $needed = $nextHops - $prevHops;
    } else {
        $progress = $hops;
        $needed = $nextHops > 0 ? $nextHops : 0;
    }
    $progressBar = bar($progress, $needed);

    $body = "👤 کاربر : " . (!empty($targetFrom['username']) ? '@' . h($targetFrom['username']) : h(displayName($targetFrom))) . "\n"
        . "🪪 آیدی : <code>{$tid}</code>\n\n"
        . "💰 هاپ پوینت : " . nf($u['hop_points']) . " 🦴\n"
        . "🎖️ رتبه پوینت : #" . nf($rPts) . "\n"
        . "🐾 هاپ‌های کل : " . nf($hops) . "\n"
        . "🎖️ رتبه هاپ : #" . nf($rHops) . "\n\n"
        . "🐈 سگ‌های خیابونی : {$strayCount}\n"
        . "🎖️ رتبه نجات : #" . nf($rStray);

    if ($dog) {
        $body .= "\n\n🐕 سگ : " . h($dog['name']) . "\n"
            . "🎖️ سطح " . (int) $dog['level'] . ' | مقام ' . h(dogRank((int) $dog['rank'])[0]);
    }
    if ($hook) {
        $body .= "\n🎣 قلاب : سطح " . (int) $hook['level'];
    }

    $st = challengeStats($tid);
    if ((int) $st['wins'] + (int) $st['losses'] + (int) $st['draws'] > 0) {
        $body .= "\n\n🎯 چالش دوز : 🏆 " . (int) $st['wins'] . ' برد | 💀 ' . (int) $st['losses'] . ' باخت | 🤝 ' . (int) $st['draws'] . ' مساوی';
    }

    $body .= "\n\n⭐️ سطح : {$lvl} | " . nf($progress) . ' / ' . ($needed > 0 ? nf($needed) : '∞') . ' ' . $progressBar;

    $text = "🐾 <b>پروفایل هاپو</b> 🐾\n\n" . quote($body, true);
    if ($jailed) {
        $text .= "\n\n⛓️ <b>در زندان!</b> — " . fmtDur(secsLeft($jailRow['release_at'])) . ' تا آزادی';
    }

    // تلاش برای ارسال با عکس پروفایل
    $photos = tg('getUserProfilePhotos', ['user_id' => $tid, 'limit' => 1], 15);
    if (is_array($photos) && (int) ($photos['total_count'] ?? 0) > 0) {
        $sizes = $photos['photos'][0] ?? [];
        $fileId = $sizes ? ($sizes[count($sizes) - 1]['file_id'] ?? null) : null;
        if ($fileId) {
            $ok = tg('sendPhoto', [
                'chat_id'    => $msg['chat']['id'],
                'photo'      => $fileId,
                'caption'    => clampText($text, 1024),
                'parse_mode' => 'HTML',
                'reply_parameters' => ['message_id' => $msg['message_id'], 'allow_sending_without_reply' => true],
            ]);
            if ($ok !== null) {
                return;
            }
        }
    }
    reply($msg, $text);
}

function cmdProfileOther(array $msg): void
{
    if (!isset($msg['reply_to_message']['from'])) {
        reply($msg, '❌ باید روی پیام کسی ریپلای کنی!');
        return;
    }
    $t = $msg['reply_to_message']['from'];
    if (!empty($t['is_bot'])) {
        reply($msg, '🤖 پروفایل ربات؟ نه بابا!');
        return;
    }
    sendProfile($msg, $t);
}

function cmdTop(array $msg): void
{
    $rows = all('SELECT first_name, hop_points, level FROM users ORDER BY hop_points DESC LIMIT 10');
    if ($rows === []) {
        reply($msg, 'هنوز کسی هاپ نزده!');
        return;
    }
    $medals = ['🥇', '🥈', '🥉'];
    $body = '';
    foreach ($rows as $i => $r) {
        $medal = $medals[$i] ?? ($i + 1) . '.';
        $body .= "$medal " . h($r['first_name'] ?: 'ناشناس') . ' — ' . nf($r['hop_points']) . ' 🦴 | سطح ' . (int) $r['level'] . "\n";
    }
    reply($msg, "🏆 <b>برترین هاپوها</b> 🐾\n\n" . quote(trim($body)));
}

function cmdHelp(array $msg): void
{
    reply($msg,
        "🐾 <b>راهنمای هاپو</b> 🐾\n\n"
        . "<b>💼 اقتصاد و رشد</b>\n"
        . quote(
            "🐾 <b>هاپ</b> — جمع پوینت (کولداون ۵ دقیقه)\n"
            . "🐕 <b>سگ</b> — خرید و مدیریت سگ\n"
            . "🎣 <b>قلاب</b> — خرید قلاب ماهیگیری\n"
            . "🦴 <b>استخوان</b> — صید استخوان\n"
            . "🏦 <b>بانک</b> — حساب بانکی و سود\n"
            . "🏭 <b>کارخونه</b> — تولید و فروش محصول\n"
            . "💳 <b>انتقال [عدد] @یوزر</b> — انتقال پوینت\n"
            . "🎁 <b>اهدا [عدد]</b> — کمک به خزانه شهر"
        )
        . "\n<b>🎮 بازی و سرگرمی</b>\n"
        . quote(
            "🎯 <b>چالش [عدد] میو</b> — چالش دوز! 🆕\n"
            . "🏅 <b>برترین چالش</b> — جدول چالش دوز\n"
            . "🎲 <b>بازی</b> — منوی بازی‌ها\n"
            . "🃏 <b>کازینو</b> — قمار، تاس، گردونه\n"
            . "🥷 <b>قاچاق</b> — قاچاق سگ خیابونی"
        )
        . "\n<b>🏛 شهر و سیاست</b>\n"
        . quote(
            "🏰 <b>شهر</b> — وضعیت شهر گروه\n"
            . "🏛 <b>شهرداری</b> — پنل شهردار و انتخابات\n"
            . "🚨 <b>بحران</b> — وضعیت بحران شهر\n"
            . "👑 <b>رهبر</b> — پنل رهبری کشور"
        )
        . "\n<b>👤 حساب کاربری</b>\n"
        . quote(
            "🐾 <b>هاپوهام</b> — پروفایل خودت\n"
            . "🐾 <b>هاپ هاش</b> — پروفایل نفر ریپلای‌شده\n"
            . "📊 <b>برترین</b> — لیدربرد\n"
            . "⛓ <b>زندان</b> — وضعیت زندان\n"
            . "🛒 <b>مارکت</b> — بازار کاربران"
        ),
        kb([
            [btn('راهنمای چالش دوز 🎯', 'chal_help', 'success')],
            [btn('برترین‌ها 📊', 'show_top', 'primary')],
        ]));
}

// ═══════════════════════════════════════════════════════════════════════════
//  😾  منطق «میو»
// ═══════════════════════════════════════════════════════════════════════════

function handleMeow(array $msg, array $from): bool
{
    $text = normalizeText((string) ($msg['text'] ?? ''));
    if ($text !== 'میو') {
        return false;
    }

    $targetId = (int) $from['id'];
    $reply = MEOW_REPLIES[array_rand(MEOW_REPLIES)];

    $sent = reply($msg,
        h($reply) . "\n\n"
        . "🚨 <b>رای‌گیری برای زندانی کردن " . mention($from) . " شروع شد!</b>\n"
        . quote('اگر تا ' . fmtDur(MEOW_VOTE_WINDOW) . ' دیگر ' . MEOW_VOTE_NEEDED . ' رای جمع بشه، میره زندان!'),
        kb([[btn('رای به زندانی شدن (0/' . MEOW_VOTE_NEEDED . ') ⚖️', "vmeow_{$targetId}", 'danger')]]));

    if (is_array($sent) && isset($sent['message_id'])) {
        q('INSERT INTO meow_votes (chat_id, message_id, target_id, voters, expires_at) VALUES (?,?,?,?,?)
           ON CONFLICT(chat_id, message_id) DO UPDATE SET voters=excluded.voters, expires_at=excluded.expires_at',
            [(int) $msg['chat']['id'], (int) $sent['message_id'], $targetId, '', isoPlus(MEOW_VOTE_WINDOW)]);
    }
    return true;
}

function cbMeowVote(array $cbq, int $targetId): void
{
    $voterId = (int) $cbq['from']['id'];
    $cbId = $cbq['id'];
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];

    if ($voterId === $targetId) {
        answerCb($cbId, '❌ داداش نمی‌تونی به خودت رای بدی!', true);
        return;
    }

    $row = one('SELECT * FROM meow_votes WHERE chat_id=? AND message_id=?', [$chatId, $mid]);
    if (!$row) {
        answerCb($cbId, '❌ این رای‌گیری تموم شده!', true);
        return;
    }
    if (!isFuture($row['expires_at'])) {
        q('DELETE FROM meow_votes WHERE chat_id=? AND message_id=?', [$chatId, $mid]);
        editMarkup($chatId, $mid, null);
        answerCb($cbId, '⏰ مهلت رای‌گیری تموم شده!', true);
        return;
    }

    $voters = array_filter(explode(',', (string) $row['voters']));
    if (in_array((string) $voterId, $voters, true)) {
        answerCb($cbId, '❌ شما قبلاً رای داده‌اید!', true);
        return;
    }
    $voters[] = (string) $voterId;
    $count = count($voters);

    if ($count >= MEOW_VOTE_NEEDED) {
        q('INSERT INTO users (user_id, username, first_name) VALUES (?, "", "کاربر") ON CONFLICT(user_id) DO NOTHING', [$targetId]);
        jailUser($targetId, 'گفتن کلمه ممنوعه میو', MEOW_JAIL_MINUTES * 60);
        q('DELETE FROM meow_votes WHERE chat_id=? AND message_id=?', [$chatId, $mid]);

        answerCb($cbId, '🔒 کاربر به زندان فرستاده شد!', true);
        editMsg($chatId, $mid,
            "🔒 <b>حکم صادر شد!</b>\n\n"
            . quote('کاربر به دلیل گفتن «میو» با ' . MEOW_VOTE_NEEDED . ' رای موافق به مدت ' . MEOW_JAIL_MINUTES . ' دقیقه زندانی شد! 😾'),
            null);
        return;
    }

    q('UPDATE meow_votes SET voters=? WHERE chat_id=? AND message_id=?', [implode(',', $voters), $chatId, $mid]);
    answerCb($cbId, '✅ رای شما ثبت شد.');
    editMarkup($chatId, $mid,
        kb([[btn("رای به زندانی شدن ({$count}/" . MEOW_VOTE_NEEDED . ') ⚖️', "vmeow_{$targetId}", 'danger')]]));
}

/** پاک‌سازی رای‌گیری‌های منقضی میو */
function meowCron(): void
{
    foreach (all('SELECT * FROM meow_votes WHERE expires_at <= ?', [nowIso()]) as $row) {
        editMarkup((int) $row['chat_id'], (int) $row['message_id'], null);
        deleteMsg((int) $row['chat_id'], (int) $row['message_id']);
        q('DELETE FROM meow_votes WHERE chat_id=? AND message_id=?', [(int) $row['chat_id'], (int) $row['message_id']]);
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  🕹  منوی بازی‌ها و کازینو
// ═══════════════════════════════════════════════════════════════════════════

function cmdGamesMenu(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🕹 بازی‌ها فقط توی گروه کار می‌کنن!');
        return;
    }
    $u = ensureUser($from);
    if ((int) $u['level'] < GAME_MIN_LEVEL) {
        reply($msg, '🔒 برای بازی باید حداقل سطح ' . GAME_MIN_LEVEL . ' باشی!');
        return;
    }
    $uid = (int) $from['id'];

    reply($msg,
        "🕹 <b>بازی‌های هاپی</b>\n\n"
        . quote(
            "🎯 چالش دوز — شرط‌بندی دوز با میو پوینت 🆕\n"
            . "🧩 XO — نبرد استراتژیک ۳x۳\n"
            . "🃏 کازینو — قمار، تاس، گردونه"
        )
        . "\n👛 موجودی تو : " . nf($u['hop_points']) . ' 🦴',
        kb([
            [btn('چالش دوز 🎯', 'chal_help', 'success')],
            [btn('میز XO 🧩', "game_xo_menu_{$uid}", 'primary')],
            [btn('کازینو 🃏', "casino_menu_{$uid}", 'primary')],
        ]));
}

function casinoMenuData(array $u): array
{
    $uid = (int) $u['user_id'];
    $text = "🃏 <b>کازینو هاپی</b>\n\n"
        . quote(
            "🍷 قمار گروهی — ۲ تا ۵ نفره، برنده همه رو می‌بره\n"
            . "🎰 گردونه شانس — تکی یا چندنفره\n"
            . "🎲 تاس — زوج/فرد یا عدد دقیق"
        )
        . "\n👛 موجودی تو : " . nf($u['hop_points']) . ' 🦴';
    $keyboard = kb([
        [
            btn('قمار گروهی 🍷', "casino_gamble_{$uid}", 'primary'),
            btn('گردونه شانس 🎰', "casino_wheel_{$uid}", 'primary'),
        ],
        [btn('تاس 🎲', "casino_dice_{$uid}", 'primary')],
    ]);
    return [$text, $keyboard];
}

function cmdCasino(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🃏 کازینو فقط توی گروه کار می‌کنه!');
        return;
    }
    $u = ensureUser($from);
    if ((int) $u['level'] < CASINO_MIN_LEVEL) {
        reply($msg, '🔒 سطح ' . CASINO_MIN_LEVEL . ' لازمه!');
        return;
    }
    [$text, $keyboard] = casinoMenuData($u);
    reply($msg, $text, $keyboard);
}

// ── میز XO شرط‌بندی (کازینو) ─────────────────────────────────────────────

function xoTableKeyboard(array $b, string $tableId): array
{
    $rows = [];
    for ($i = 0; $i < 9; $i += 3) {
        $row = [];
        for ($j = 0; $j < 3; $j++) {
            $idx = $i + $j;
            $row[] = [
                'text' => XO_SYMBOLS[$b[$idx]],
                'callback_data' => $b[$idx] === 0 ? "xo_move_{$tableId}_{$idx}" : 'xo_noop',
            ];
        }
        $rows[] = $row;
    }
    return ['inline_keyboard' => $rows];
}

function xoCreateTable(array $msg, array $from, int $bet): void
{
    $uid = (int) $from['id'];
    $chatId = (int) $msg['chat']['id'];

    $left = gameCooldownLeft($uid, 'xo', XO_COOLDOWN);
    if ($left > 0) {
        reply($msg, '⌛️ ' . fmtDur($left) . ' تا بازی بعدی صبر کن!');
        return;
    }
    $u = getUser($uid);
    if (!$u || (float) $u['hop_points'] < $bet) {
        reply($msg, '❌ موجودی کافی نداری! موجودی: ' . nf($u['hop_points'] ?? 0));
        return;
    }

    $tableId = 'xo' . $uid . dechex(time());
    deductPoints($uid, $bet);
    q("INSERT INTO game_tables (table_id, group_id, game_type, creator_id, bet, state, board, current_turn, created_at)
       VALUES (?,?,'xo',?,?,'waiting',?,?,?)",
        [$tableId, $chatId, $uid, $bet, '0,0,0,0,0,0,0,0,0', $uid, nowIso()]);

    $sent = reply($msg,
        "🧩 <b>" . h(displayName($from)) . " میز XO ساخت!</b>\n\n"
        . quote("💰 شرط : " . nf($bet) . " هاپ پوینت\n⌛️ " . TABLE_WAIT_TIMEOUT . ' ثانیه فرصت پیوستن'),
        kb([[
            btn('پیوستن (' . nf($bet) . ' پوینت) ✋', "xo_join_{$tableId}", 'success'),
            btn('لغو', "xo_cancel_{$tableId}", 'danger'),
        ]]));

    if (is_array($sent) && isset($sent['message_id'])) {
        q('UPDATE game_tables SET message_id=? WHERE table_id=?', [(int) $sent['message_id'], $tableId]);
    }
}

// ── تاس / گردونه / قمار ──────────────────────────────────────────────────

const WHEEL_JACKPOT_MULT = 15.0;

function wheelResult(int $slotValue): array
{
    if ($slotValue === 64) {
        return [WHEEL_JACKPOT_MULT, '🎰 جکپات! 7️⃣7️⃣7️⃣'];
    }
    if ($slotValue >= 49) {
        return [2.5, '🎉 برنده!'];
    }
    if ($slotValue >= 29) {
        return [0.5, '😬 نصفه...'];
    }
    return [0.0, '💀 باختی!'];
}

function cmdDice(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🎲 تاس فقط توی گروه کار می‌کنه!');
        return;
    }
    $u = ensureUser($from);
    $uid = (int) $from['id'];
    if ((int) $u['level'] < CASINO_MIN_LEVEL) {
        reply($msg, '🔒 برای تاس باید سطح ' . CASINO_MIN_LEVEL . ' باشی!');
        return;
    }
    $left = gameCooldownLeft($uid, 'dice', DICE_COOLDOWN);
    if ($left > 0) {
        reply($msg, '⌛️ ' . fmtDur($left) . ' تا تاس بعدی!');
        return;
    }
    setState($uid, ['flow' => 'dice', 'step' => 'bet']);
    reply($msg, "🎲 <b>تاس هاپی</b>\n\n" . quote('💰 موجودی : ' . nf($u['hop_points'])) . "\nشرط چقدر؟ مبلغ رو بنویس:");
}

function cmdWheel(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🎰 گردونه فقط توی گروه!');
        return;
    }
    $u = ensureUser($from);
    $uid = (int) $from['id'];
    if ((int) $u['level'] < CASINO_MIN_LEVEL) {
        reply($msg, '🔒 سطح ' . CASINO_MIN_LEVEL . ' لازمه!');
        return;
    }
    $left = gameCooldownLeft($uid, 'wheel', WHEEL_COOLDOWN);
    if ($left > 0) {
        reply($msg, '⌛️ ' . fmtDur($left) . ' تا گردونه بعدی!');
        return;
    }
    setState($uid, ['flow' => 'wheel', 'step' => 'bet']);
    reply($msg,
        "🎰 <b>گردونه شانس</b>\n\n"
        . quote("ضرایب: 0x ❌ | 0.5x | 2.5x | 15x 🌟\n👛 موجودی : " . nf($u['hop_points']))
        . "\nشرط چقدر؟ مبلغ رو بنویس:");
}

function cmdGamble(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🍷 قمار فقط توی گروه!');
        return;
    }
    $u = ensureUser($from);
    $uid = (int) $from['id'];
    if ((int) $u['level'] < CASINO_MIN_LEVEL) {
        reply($msg, '🔒 سطح ' . CASINO_MIN_LEVEL . ' لازمه!');
        return;
    }
    $left = gameCooldownLeft($uid, 'gamble', GAMBLE_COOLDOWN);
    if ($left > 0) {
        reply($msg, '⌛️ ' . fmtDur($left) . ' تا قمار بعدی!');
        return;
    }
    setState($uid, ['flow' => 'gamble', 'step' => 'bet', 'group_id' => (int) $chat['id']]);
    reply($msg, "🍷 <b>قمار گروهی</b>\n\n" . quote('💰 موجودی : ' . nf($u['hop_points'])) . "\nشرط چقدر؟ مبلغ رو بنویس:");
}

/** پرتاب تاس واقعی و برگرداندن مقدار */
function rollDice($chatId, string $emoji = '🎲'): int
{
    $res = sendDice($chatId, $emoji);
    $v = is_array($res) ? (int) ($res['dice']['value'] ?? 0) : 0;
    if ($v > 0) {
        sleep($emoji === '🎰' ? 3 : 4);
    }
    return $v > 0 ? $v : mt_rand(1, 6);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🛒  مارکت کاربران
// ═══════════════════════════════════════════════════════════════════════════

function marketOpen(): bool
{
    return setting('market_open', '1') === '1';
}

function setMarketOpen(bool $v): void
{
    setSetting('market_open', $v ? '1' : '0');
}

function newId(int $len = 10): string
{
    return substr(bin2hex(random_bytes($len)), 0, $len);
}

function getListing(string $id): ?array
{
    return one('SELECT * FROM user_market WHERE listing_id=?', [$id]);
}

function activeListings(): array
{
    return all("SELECT * FROM user_market WHERE status='active' ORDER BY created_at DESC");
}

function pendingListings(): array
{
    return all("SELECT * FROM user_market WHERE status='pending' ORDER BY created_at ASC");
}

function sellerListings(int $sellerId): array
{
    return all('SELECT * FROM user_market WHERE seller_id=? ORDER BY created_at DESC', [$sellerId]);
}

function cmdMarket(array $msg, array $from): void
{
    $isPrivate = ($msg['chat']['type'] ?? '') === 'private';
    $uid = (int) $from['id'];
    ensureUser($from);

    if ($isPrivate) {
        marketPrivatePanel($msg, $uid);
        return;
    }

    if (!marketOpen()) {
        reply($msg, '🔴 مارکت در حال حاضر تعطیله!');
        return;
    }
    $listings = activeListings();
    if ($listings === []) {
        reply($msg, '🛒 مارکت خالیه! هنوز آگهی فعالی ثبت نشده.');
        return;
    }

    $body = '';
    $rows = [];
    foreach ($listings as $l) {
        $remaining = (int) $l['max_buyers'] - (int) $l['buyer_count'];
        $body .= "🏷 <b>" . h($l['title']) . "</b>\n"
            . "👤 فروشنده : " . h($l['seller_name']) . "\n"
            . "💰 قیمت : " . nf($l['price']) . " هاپ پوینت\n"
            . "📦 ظرفیت باقی‌مانده : {$remaining}\n"
            . "───────────────\n";
        $rows[] = [btn('خرید «' . mb_substr($l['title'], 0, 20) . '» 🛍', 'mkt_buy_' . $l['listing_id'], 'success')];
    }
    reply($msg, "🛒 <b>مارکت هاپ‌داگ</b>\n\n" . quote(trim($body), true), kb($rows));
}

function marketPrivatePanel(array $msg, int $uid): void
{
    $mine = sellerListings($uid);
    $active = $pending = $cancelled = 0;
    foreach ($mine as $l) {
        if ($l['status'] === 'active') { $active++; }
        elseif ($l['status'] === 'pending') { $pending++; }
        elseif (in_array($l['status'], ['cancelled', 'rejected'], true)) { $cancelled++; }
    }
    $rows = [[btn('ثبت آگهی جدید ➕', 'mkt_new', 'success')]];
    if ($mine !== []) {
        $rows[] = [btn('آگهی‌های من 📋', 'mkt_my_listings', 'primary')];
    }
    reply($msg,
        "🛒 <b>پنل مارکت شخصی</b>\n\n"
        . quote("✅ فعال : {$active} آگهی\n⏳ در انتظار تأیید : {$pending} آگهی\n❌ لغو/رد شده : {$cancelled} آگهی")
        . "\nیه گزینه انتخاب کن:",
        kb($rows));
}

function marketAdminPanelData(): array
{
    $pendings = count(pendingListings());
    $actives = count(activeListings());
    $status = marketOpen() ? '🟢 باز' : '🔴 تعطیل';
    $toggleLabel = marketOpen() ? 'تعطیل کردن مارکت' : 'باز کردن مارکت';

    $text = "🛒 <b>پنل مدیریت مارکت</b>\n\n"
        . quote("وضعیت مارکت : {$status}\n⏳ در انتظار تأیید : {$pendings} آگهی\n✅ فعال : {$actives} آگهی")
        . "\nیه گزینه انتخاب کن:";

    $keyboard = kb([
        [btn("بررسی آگهی‌های در انتظار ({$pendings}) ⏳", 'mkt_admin_pending', 'primary')],
        [btn('لیست آگهی‌های فعال 📋', 'mkt_admin_active', 'primary')],
        [btn($toggleLabel, 'mkt_admin_toggle', marketOpen() ? 'danger' : 'success')],
    ]);
    return [$text, $keyboard];
}

// ═══════════════════════════════════════════════════════════════════════════
//  🎰  قرعه‌کشی
// ═══════════════════════════════════════════════════════════════════════════

function getLottery(string $id): ?array
{
    return one('SELECT * FROM lotteries WHERE lottery_id=?', [$id]);
}

function allLotteries(): array
{
    return all('SELECT * FROM lotteries ORDER BY created_at DESC');
}

function lotteryEntries(string $id): array
{
    return all('SELECT * FROM lottery_entries WHERE lottery_id=? ORDER BY joined_at ASC', [$id]);
}

function lotteryParticipantText(array $entries): string
{
    if ($entries === []) {
        return 'هنوز کسی شرکت نکرده 😿';
    }
    $lines = [];
    foreach ($entries as $i => $e) {
        $uname = !empty($e['username']) ? '@' . h($e['username']) : '—';
        $lines[] = ($i + 1) . '. ' . h($e['first_name']) . " ({$uname})";
    }
    return implode("\n", $lines);
}

function lotteryAnnounceText(array $lot, array $entries): string
{
    return "🎰 <b>قرعه‌کشی: " . h($lot['title']) . "</b>\n\n"
        . quote(
            "🎁 جایزه : " . nf($lot['prize']) . " هاپ پوینت\n"
            . "🏆 تعداد برنده : " . (int) $lot['winner_count'] . " نفر\n"
            . "👥 شرکت‌کنندگان : " . count($entries) . ' نفر'
        )
        . "\n" . quote(lotteryParticipantText($entries), true);
}

function lotteryAdminPanelData(): array
{
    $lots = allLotteries();
    $open = $done = $cancelled = 0;
    foreach ($lots as $l) {
        if ($l['state'] === 'open') { $open++; }
        elseif ($l['state'] === 'done') { $done++; }
        else { $cancelled++; }
    }
    $text = "🎰 <b>پنل مدیریت قرعه‌کشی</b>\n\n"
        . quote("🟢 باز : {$open}\n✅ انجام‌شده : {$done}\n❌ لغو‌شده : {$cancelled}")
        . "\nیه گزینه انتخاب کن:";
    $keyboard = kb([
        [btn('ساخت قرعه‌کشی جدید ➕', 'lot_new', 'success')],
        [btn('لیست همه قرعه‌کشی‌ها 📋', 'lot_list_0', 'primary')],
        [btn('قرعه‌کشی‌های باز 🟢', 'lot_open', 'primary')],
    ]);
    return [$text, $keyboard];
}

function doLotteryDraw(string $lotteryId): bool
{
    $lot = getLottery($lotteryId);
    if (!$lot || $lot['state'] !== 'open') {
        return false;
    }
    $entries = lotteryEntries($lotteryId);
    if ($entries === []) {
        q("UPDATE lotteries SET state='cancelled' WHERE lottery_id=?", [$lotteryId]);
        return false;
    }

    $winnerCount = min((int) $lot['winner_count'], count($entries));
    $keys = array_rand($entries, $winnerCount);
    if (!is_array($keys)) {
        $keys = [$keys];
    }
    $prizeEach = intdiv((int) $lot['prize'], max(1, $winnerCount));

    $winnerLines = [];
    transact(static function () use ($keys, $entries, $prizeEach, $lotteryId, &$winnerLines) {
        foreach ($keys as $k) {
            $w = $entries[$k];
            addPoints((int) $w['user_id'], $prizeEach);
            $uname = !empty($w['username']) ? '@' . h($w['username']) : '—';
            $winnerLines[] = "🏆 <b>" . h($w['first_name']) . "</b>\n"
                . "   🆔 <code>" . (int) $w['user_id'] . "</code>\n"
                . "   👤 {$uname}\n"
                . "   💰 " . nf($prizeEach) . ' هاپ پوینت';
        }
        q("UPDATE lotteries SET state='done' WHERE lottery_id=?", [$lotteryId]);
    });

    $announce = "🎉 <b>نتیجه قرعه‌کشی: " . h($lot['title']) . "</b>\n\n"
        . "از بین " . count($entries) . " نفر شرکت‌کننده، {$winnerCount} نفر انتخاب شدن:\n\n"
        . quote(implode("\n\n", $winnerLines), true)
        . "\n✨ تبریک به برندگان!";

    foreach (all('SELECT group_id FROM groups') as $g) {
        sendMsg((int) $g['group_id'], $announce);
    }
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏛  شهرداری
// ═══════════════════════════════════════════════════════════════════════════

function getMayor(int $groupId): ?array
{
    $m = one('SELECT * FROM mayor WHERE group_id=?', [$groupId]);
    if ($m && !isFuture($m['term_end'])) {
        q('DELETE FROM mayor WHERE group_id=?', [$groupId]);
        return null;
    }
    return $m;
}

function setMayor(int $groupId, int $userId, string $username, string $firstName, int $termDays = MAYOR_TERM_DAYS): void
{
    q('INSERT INTO mayor (group_id, user_id, username, first_name, elected_at, term_end, popularity)
       VALUES (?,?,?,?,?,?,100)
       ON CONFLICT(group_id) DO UPDATE SET user_id=excluded.user_id, username=excluded.username,
         first_name=excluded.first_name, elected_at=excluded.elected_at,
         term_end=excluded.term_end, popularity=100',
        [$groupId, $userId, $username, $firstName, nowIso(), isoPlus($termDays * 86400)]);
}

function changeMayorPopularity(int $groupId, int $delta): void
{
    q('UPDATE mayor SET popularity = MAX(0, MIN(100, popularity + ?)) WHERE group_id=?', [$delta, $groupId]);
}

function logMayorAction(int $groupId, int $userId, string $type, string $desc): void
{
    q('INSERT INTO mayor_log (group_id, user_id, action_type, action_desc, created_at) VALUES (?,?,?,?,?)',
        [$groupId, $userId, $type, $desc, nowIso()]);
}

function activeMayorElection(int $groupId): ?array
{
    return one("SELECT * FROM mayor_elections WHERE group_id=? AND status IN ('candidacy','voting') ORDER BY id DESC LIMIT 1", [$groupId]);
}

function activeProtest(int $groupId): ?array
{
    return one("SELECT * FROM mayor_protests WHERE group_id=? AND status='pending' ORDER BY id DESC LIMIT 1", [$groupId]);
}

function mayorPanelData(int $groupId, int $viewerId): array
{
    $mayor = getMayor($groupId);
    $election = activeMayorElection($groupId);
    $decree = activeDecree($groupId);
    $contract = activeContract($groupId);
    $grp = getGroup($groupId);

    $body = "🏙 شهر : " . h($grp['title'] ?: 'شهر هاپو') . "\n"
        . "⭐️ سطح شهر : " . cityLevel($grp) . "\n"
        . "🏦 خزانه : " . nf($grp['treasury']) . "\n\n";

    if ($mayor) {
        $body .= "🏛 شهردار : " . h($mayor['first_name']) . "\n"
            . "📈 محبوبیت : " . (int) $mayor['popularity'] . "٪ " . bar((int) $mayor['popularity'], 100) . "\n"
            . "⏳ پایان دوره : " . fmtDur(secsLeft($mayor['term_end']));
    } else {
        $body .= "🏛 شهردار : <b>ندارد</b>\n💡 می‌تونی انتخابات راه بندازی!";
    }

    if ($decree) {
        $def = MAYOR_DECREES_DEF[$decree['decree_type']] ?? null;
        if ($def) {
            $body .= "\n\n📜 فرمان فعال : " . h($def['name']) . "\n"
                . "   " . h($def['desc']) . "\n"
                . "   ⏳ " . fmtDur(secsLeft($decree['expires_at']));
        }
    }
    if ($contract) {
        $def = CITY_CONTRACTS[$contract['contract_key']] ?? null;
        if ($def) {
            $body .= "\n\n🤝 قرارداد فعال : " . h($def['name']) . "\n"
                . "   ✅ " . h($def['pros']) . "  |  ⚠️ " . h($def['cons']);
        }
    }
    if ($election) {
        $phase = $election['status'] === 'candidacy' ? '📝 ثبت‌نام کاندیداها' : '🗳 رأی‌گیری';
        $n = (int) val('SELECT COUNT(*) FROM mayor_candidates WHERE election_id=?', [(int) $election['id']], 0);
        $body .= "\n\n🗳 انتخابات فعال : {$phase}\n   👥 {$n} کاندیدا";
    }

    $text = "🏛 <b>شهرداری</b>\n\n" . quote($body, true);

    $isMayor = $mayor && (int) $mayor['user_id'] === $viewerId;
    $rows = [];

    if ($isMayor) {
        $rows[] = [btn('صدور فرمان 📜', "mayor_decrees_{$groupId}", 'success')];
        $rows[] = [
            btn('پروژه‌های شهر 🏗', "mproj_list_{$groupId}", 'primary'),
            btn('قراردادها 🤝', "mproj_contracts_{$groupId}", 'primary'),
        ];
        $rows[] = [btn('گزارش عملکرد 📊', "mayor_report_{$groupId}", 'primary')];
        $rows[] = [btn('استعفا 🚪', "mayor_resign_{$groupId}", 'danger')];
    } else {
        if (!$mayor && !$election) {
            $rows[] = [btn('شروع انتخابات 🗳', "melec_start_{$groupId}", 'success')];
        }
        if ($mayor) {
            $rows[] = [btn('ثبت اعتراض ✊', "mayor_protest_{$groupId}", 'warning')];
        }
    }

    if ($election) {
        if ($election['status'] === 'candidacy') {
            $rows[] = [btn('ثبت‌نام کاندیدا ✋', 'melec_join_' . (int) $election['id'], 'success')];
            if ($mayor === null || $isMayor || isAnyAdmin($viewerId)) {
                $rows[] = [btn('شروع رأی‌گیری ▶️', 'melec_vote_' . (int) $election['id'], 'success')];
            }
        } else {
            $rows[] = [btn('مشاهده کاندیداها و رأی 🗳', 'melec_list_' . (int) $election['id'], 'primary')];
            $rows[] = [btn('پایان انتخابات و شمارش 🏁', 'melec_finish_' . (int) $election['id'], 'warning')];
        }
    }

    $protest = activeProtest($groupId);
    if ($protest) {
        $rows[] = [btn('رأی به اعتراض ⚖️', 'mayor_protestview_' . (int) $protest['id'], 'warning')];
    }

    $rows[] = [btn('وضعیت بحران 🚨', "crisis_status_{$groupId}", 'primary')];

    return [$text, kb($rows)];
}

function cmdMayor(array $msg, array $from): void
{
    $chat = $msg['chat'];
    if (($chat['type'] ?? '') === 'private') {
        reply($msg, '🏛 شهرداری فقط توی گروه کار می‌کنه!');
        return;
    }
    ensureUser($from);
    ensureGroup($chat);
    [$text, $keyboard] = mayorPanelData((int) $chat['id'], (int) $from['id']);
    reply($msg, $text, $keyboard);
}

function editMayorPanel(array $cbq, int $groupId): void
{
    [$text, $keyboard] = mayorPanelData($groupId, (int) $cbq['from']['id']);
    editMsg((int) $cbq['message']['chat']['id'], (int) $cbq['message']['message_id'], $text, $keyboard);
}

/** شمارش آرا و اعلام شهردار */
function finishMayorElection(int $electionId, int $groupId): ?array
{
    $election = one('SELECT * FROM mayor_elections WHERE id=?', [$electionId]);
    if (!$election || $election['status'] === 'done') {
        return null;
    }
    $candidates = all('SELECT * FROM mayor_candidates WHERE election_id=?', [$electionId]);
    if ($candidates === []) {
        q("UPDATE mayor_elections SET status='done', ended_at=? WHERE id=?", [nowIso(), $electionId]);
        return null;
    }

    $counts = [];
    foreach (all('SELECT candidate_id, COUNT(*) AS n FROM mayor_election_votes WHERE election_id=? GROUP BY candidate_id', [$electionId]) as $r) {
        $counts[(int) $r['candidate_id']] = (int) $r['n'];
    }

    $best = null;
    $bestVotes = -1;
    foreach ($candidates as $c) {
        $v = $counts[(int) $c['user_id']] ?? 0;
        if ($v > $bestVotes) {
            $bestVotes = $v;
            $best = $c;
        }
    }
    if (!$best) {
        return null;
    }

    setMayor($groupId, (int) $best['user_id'], (string) $best['username'], (string) $best['first_name']);
    q("UPDATE mayor_elections SET status='done', ended_at=? WHERE id=?", [nowIso(), $electionId]);
    logMayorAction($groupId, (int) $best['user_id'], 'elected', 'انتخاب به عنوان شهردار');

    return ['winner' => $best, 'votes' => $bestVotes, 'candidates' => $candidates, 'counts' => $counts];
}

// ═══════════════════════════════════════════════════════════════════════════
//  👑  رهبر کشور
// ═══════════════════════════════════════════════════════════════════════════

function getLeader(): ?array
{
    $l = one('SELECT * FROM leader LIMIT 1');
    if ($l && !isFuture($l['term_end'])) {
        q('DELETE FROM leader WHERE user_id=?', [(int) $l['user_id']]);
        return null;
    }
    return $l;
}

function setLeader(int $userId, string $username, string $firstName, int $termDays = LEADER_TERM_DAYS): void
{
    q('DELETE FROM leader');
    q('INSERT INTO leader (user_id, username, first_name, elected_at, term_end, popularity)
       VALUES (?,?,?,?,?,100)', [$userId, $username, $firstName, nowIso(), isoPlus($termDays * 86400)]);
}

function isLeader(int $userId): bool
{
    $l = getLeader();
    return $l !== null && (int) $l['user_id'] === $userId;
}

function logLeader(int $userId, string $type, string $desc): void
{
    q('INSERT INTO leader_log (user_id, action_type, action_desc, created_at) VALUES (?,?,?,?)',
        [$userId, $type, $desc, nowIso()]);
}

function treasury(): float
{
    return (float) val('SELECT balance FROM national_treasury WHERE id=1', [], 0);
}

function changeTreasury(float $amount, string $reason, int $userId = 0): void
{
    q('UPDATE national_treasury SET balance = MAX(0, balance + ?) WHERE id=1', [$amount]);
    q('INSERT INTO treasury_log (amount, reason, user_id, created_at) VALUES (?,?,?,?)',
        [$amount, $reason, $userId, nowIso()]);
}

function canIssueCommand(): array
{
    $last = one('SELECT * FROM leader_commands ORDER BY id DESC LIMIT 1');
    if (!$last) {
        return [true, 0];
    }
    $elapsed = time() - ts($last['issued_at']);
    if ($elapsed >= LEADER_COMMAND_COOLDOWN) {
        return [true, 0];
    }
    return [false, LEADER_COMMAND_COOLDOWN - $elapsed];
}

function activeLeaderElection(): ?array
{
    return one("SELECT * FROM leader_elections WHERE status IN ('candidacy','voting') ORDER BY id DESC LIMIT 1");
}

function leaderPanelData(int $viewerId): array
{
    $ld = getLeader();
    $election = activeLeaderElection();
    $cmd = activeNationalCommand();
    $em = activeEmergency();

    $body = "🏦 خزانه ملی : " . nf(treasury()) . " 🦴\n";
    if ($ld) {
        $body .= "👑 رهبر : " . h($ld['first_name']) . "\n"
            . "📈 محبوبیت : " . (int) $ld['popularity'] . "٪ " . bar((int) $ld['popularity'], 100) . "\n"
            . "⏳ پایان دوره : " . fmtDur(secsLeft($ld['term_end']));
    } else {
        $body .= "👑 رهبر : <b>ندارد</b>\n💡 انتخابات سراسری راه بنداز!";
    }

    if ($cmd) {
        $def = NATIONAL_COMMANDS[$cmd['command_key']] ?? null;
        if ($def) {
            $body .= "\n\n⚡ فرمان فعال : " . h($def['name']) . "\n   " . h($def['desc'])
                . "\n   ⏳ " . fmtDur(secsLeft($cmd['expires_at']));
        }
    }

    $laws = activeNationalLaws();
    if ($laws !== []) {
        $body .= "\n\n⚖️ قوانین فعال :";
        foreach ($laws as $law) {
            $def = NATIONAL_LAWS[$law['law_key']] ?? null;
            $body .= "\n   • " . h($def['name'] ?? $law['law_name']);
        }
    }

    $events = activeNationalEvents();
    if ($events !== []) {
        $body .= "\n\n🎉 رویدادهای فعال :";
        foreach ($events as $e) {
            $def = NATIONAL_EVENTS[$e['event_key']] ?? null;
            $body .= "\n   • " . h($def['name'] ?? $e['event_key']) . ' (' . fmtDur(secsLeft($e['expires_at'])) . ')';
        }
    }

    if ($em) {
        $def = EMERGENCY_TYPES[$em['etype']] ?? null;
        $body .= "\n\n🚨 وضعیت اضطراری : " . h($def['name'] ?? $em['etype'])
            . "\n   ⏳ " . fmtDur(secsLeft($em['expires_at']));
    }

    $projects = all('SELECT * FROM national_projects');
    if ($projects !== []) {
        $body .= "\n\n🏗 پروژه‌های ملی :";
        foreach ($projects as $p) {
            $def = NATIONAL_PROJECTS[$p['project_key']] ?? null;
            $icon = $p['status'] === 'done' ? '✅' : '⚙️';
            $body .= "\n   {$icon} " . h($def['name'] ?? $p['project_key']);
        }
    }

    $ministers = all('SELECT * FROM ministers WHERE user_id IS NOT NULL');
    if ($ministers !== []) {
        $body .= "\n\n👔 کابینه :";
        foreach ($ministers as $m) {
            $def = MINISTER_ROLES[$m['role']] ?? null;
            $body .= "\n   • " . h($def['name'] ?? $m['role']) . ' — ' . h($m['first_name']);
        }
    }

    if ($election) {
        $phase = $election['status'] === 'candidacy' ? '📝 ثبت‌نام' : '🗳 رأی‌گیری';
        $n = (int) val('SELECT COUNT(*) FROM leader_candidates WHERE election_id=?', [(int) $election['id']], 0);
        $body .= "\n\n🗳 انتخابات سراسری : {$phase}\n   👥 {$n} کاندیدا";
    }

    $text = "👑 <b>پنل رهبری کشور</b>\n\n" . quote($body, true);

    $isLeader = $ld && (int) $ld['user_id'] === $viewerId;
    $rows = [];

    if ($isLeader) {
        $rows[] = [btn('صدور فرمان ملی ⚡', 'lead_cmds', 'success')];
        $rows[] = [
            btn('وضع قانون ⚖️', 'lead_laws', 'primary'),
            btn('رویداد ملی 🎉', 'lead_events', 'primary'),
        ];
        $rows[] = [
            btn('پروژه ملی 🏗', 'lead_projects', 'primary'),
            btn('کابینه 👔', 'lead_ministers', 'primary'),
        ];
        $rows[] = [
            btn('بودجه شهرها 💰', 'lead_budget', 'success'),
            btn('وضعیت اضطراری 🚨', 'lead_emergency', 'danger'),
        ];
        $rows[] = [btn('استعفا 🚪', 'lead_resign', 'danger')];
    }

    if (isAdmin($viewerId)) {
        $rows[] = [btn('پنل ادمین رهبری 🛠', 'lead_admin', 'warning')];
    }

    if (!$election && !$ld) {
        $rows[] = [btn('شروع انتخابات سراسری 🗳', 'lead_elec_start', 'success')];
    }
    if ($election) {
        if ($election['status'] === 'candidacy') {
            $rows[] = [btn('ثبت‌نام کاندیدا ✋', 'lead_elec_join_' . (int) $election['id'], 'success')];
            $rows[] = [btn('شروع رأی‌گیری ▶️', 'lead_elec_vote_' . (int) $election['id'], 'success')];
        } else {
            $rows[] = [btn('کاندیداها و رأی 🗳', 'lead_elec_list_' . (int) $election['id'], 'primary')];
            $rows[] = [btn('شمارش آرا 🏁', 'lead_elec_finish_' . (int) $election['id'], 'warning')];
        }
    }

    if ($ld && !$isLeader) {
        $rows[] = [
            btn('امتیاز مثبت 👍', 'lead_rate_up', 'success'),
            btn('امتیاز منفی 👎', 'lead_rate_down', 'danger'),
        ];
    }

    return [$text, kb($rows)];
}

function cmdLeader(array $msg, array $from): void
{
    ensureUser($from);
    [$text, $keyboard] = leaderPanelData((int) $from['id']);
    reply($msg, $text, $keyboard);
}

function editLeaderPanel(array $cbq): void
{
    [$text, $keyboard] = leaderPanelData((int) $cbq['from']['id']);
    editMsg((int) $cbq['message']['chat']['id'], (int) $cbq['message']['message_id'], $text, $keyboard);
}

/** شمارش آرا و اعلام رهبر */
function finishLeaderElection(int $electionId): ?array
{
    $election = one('SELECT * FROM leader_elections WHERE id=?', [$electionId]);
    if (!$election || $election['status'] === 'done') {
        return null;
    }
    $candidates = all('SELECT * FROM leader_candidates WHERE election_id=?', [$electionId]);
    if (count($candidates) < LEADER_MIN_CANDIDATES) {
        q("UPDATE leader_elections SET status='done', ended_at=? WHERE id=?", [nowIso(), $electionId]);
        return null;
    }

    $counts = [];
    foreach (all('SELECT candidate_id, COUNT(*) AS n FROM leader_votes WHERE election_id=? GROUP BY candidate_id', [$electionId]) as $r) {
        $counts[(int) $r['candidate_id']] = (int) $r['n'];
    }

    $best = null;
    $bestVotes = -1;
    foreach ($candidates as $c) {
        $v = $counts[(int) $c['user_id']] ?? 0;
        if ($v > $bestVotes) {
            $bestVotes = $v;
            $best = $c;
        }
    }
    if (!$best) {
        return null;
    }

    setLeader((int) $best['user_id'], (string) $best['username'], (string) $best['first_name']);
    q("UPDATE leader_elections SET status='done', ended_at=? WHERE id=?", [nowIso(), $electionId]);
    logLeader((int) $best['user_id'], 'elected', 'انتخاب به عنوان رهبر کشور');

    return ['winner' => $best, 'votes' => $bestVotes, 'candidates' => $candidates, 'counts' => $counts];
}

/** اعلام سراسری به همه گروه‌ها */
function broadcastGroups(string $text, ?array $keyboard = null): int
{
    $sent = 0;
    foreach (all('SELECT group_id FROM groups') as $g) {
        if (sendMsg((int) $g['group_id'], $text, $keyboard) !== null) {
            $sent++;
        }
    }
    return $sent;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🎛  روتر کال‌بک‌ها
// ═══════════════════════════════════════════════════════════════════════════

/** بررسی مالکیت دکمه */
function ownsButton(array $cbq, int $ownerId): bool
{
    if ((int) $cbq['from']['id'] === $ownerId) {
        return true;
    }
    answerCb($cbq['id'], '❌ این دکمه برای تو نیست!', true);
    return false;
}

/** استخراج آخرین بخش عددی از callback_data */
function cbInt(string $data, int $index): int
{
    $parts = explode('_', $data);
    return (int) ($parts[$index] ?? 0);
}

function handleCallback(array $cbq): void
{
    $data = (string) ($cbq['data'] ?? '');
    $from = $cbq['from'];
    $uid = (int) $from['id'];
    $cbId = $cbq['id'];
    $message = $cbq['message'] ?? null;

    if ($message === null) {
        answerCb($cbId, '❌ پیام قدیمیه!', true);
        return;
    }
    $chatId = (int) $message['chat']['id'];
    $mid = (int) $message['message_id'];

    ensureUser($from);

    // ── عمومی ─────────────────────────────────────────────────────────────
    if ($data === 'noop' || $data === 'xo_noop' || $data === 'chal_noop') {
        answerCb($cbId);
        return;
    }
    if ($data === 'cancel') {
        answerCb($cbId, '❌ لغو شد');
        clearState($uid);
        editMsg($chatId, $mid, '❌ عملیات لغو شد.', null);
        return;
    }
    if ($data === 'check_join') {
        [$ok, $missing] = checkMembership($uid);
        if ($ok) {
            answerCb($cbId, '✅ عضویتت تأیید شد! حالا می‌تونی از ربات استفاده کنی 🐾', true);
            editMsg($chatId, $mid, '✅ <b>عضویت تأیید شد!</b>\n\nحالا بنویس <b>هاپ</b> تا شروع کنی 🐾', null);
        } else {
            answerCb($cbId, '❌ هنوز عضو همه کانال‌ها نشدی!', true);
        }
        return;
    }
    if ($data === 'show_top') {
        answerCb($cbId);
        cmdTop($message);
        return;
    }

    // ── 🆕 چالش دوز ───────────────────────────────────────────────────────
    if ($data === 'chal_help') {
        answerCb($cbId);
        cmdChallengeHelp($message);
        return;
    }
    if ($data === 'chal_top') {
        answerCb($cbId);
        cmdChallengeTop($message);
        return;
    }
    if (str_starts_with($data, 'chal_join_')) {
        challengeJoin($cbq, cbInt($data, 2));
        return;
    }
    if (str_starts_with($data, 'chal_cancel_')) {
        challengeCancel($cbq, cbInt($data, 2));
        return;
    }
    if (str_starts_with($data, 'chal_info_')) {
        challengeInfo($cbq, cbInt($data, 2));
        return;
    }
    if (str_starts_with($data, 'chal_mv_')) {
        $parts = explode('_', $data);
        challengeMove($cbq, (int) ($parts[2] ?? 0), (int) ($parts[3] ?? -1));
        return;
    }

    // ── رأی «میو» ─────────────────────────────────────────────────────────
    if (str_starts_with($data, 'vmeow_')) {
        cbMeowVote($cbq, cbInt($data, 1));
        return;
    }

    // ── سگ خیابونی / بحران ────────────────────────────────────────────────
    if (str_starts_with($data, 'rescue_')) {
        cbRescue($cbq, (int) substr($data, 7));
        return;
    }
    if (str_starts_with($data, 'crisis_status_')) {
        answerCb($cbId);
        cmdCrisisStatus($message);
        return;
    }
    if (preg_match('/^crisis_(pay|resource|ignore)_(\d+)_(-?\d+)$/', $data, $m)) {
        cbCrisis($cbq, $m[1], (int) $m[2], (int) $m[3]);
        return;
    }

    // ── قلاب ──────────────────────────────────────────────────────────────
    if (str_starts_with($data, 'buyhook_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $u = getUser($uid);
        $cost = withDiscount((int) hookData(1)[0], itemDiscount());
        if ((float) $u['hop_points'] < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        if (getHook($uid)) {
            answerCb($cbId, '⚠️ قبلاً قلاب خریدی!', true);
            return;
        }
        transact(static function () use ($uid, $cost) {
            deductPoints($uid, $cost);
            q('INSERT INTO hooks (user_id, level) VALUES (?, 1) ON CONFLICT(user_id) DO NOTHING', [$uid]);
        });
        answerCb($cbId, '🎉 قلاب خریدی!');
        editMsg($chatId, $mid, "🎉 <b>قلاب استخوان‌گیری خریدی!</b>\n\n"
            . quote("🦴 حالا توی گروه بنویس <b>استخوان</b> تا بندازی!") . "\n⏳ در حال بازگشت به پنل قلاب...", null);
        panelDelay();
        showHookPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'upgradehook_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $hook = getHook($uid);
        if (!$hook || (int) $hook['level'] >= HOOK_MAX_LEVEL) {
            answerCb($cbId, '🏆 قلابت ماکسه!', true);
            return;
        }
        $cost = withDiscount((int) hookData((int) $hook['level'])[2], upgradeDiscount());
        $u = getUser($uid);
        if ((float) $u['hop_points'] < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        $newLvl = (int) $hook['level'] + 1;
        transact(static function () use ($uid, $cost, $newLvl) {
            deductPoints($uid, $cost);
            q('UPDATE hooks SET level=? WHERE user_id=?', [$newLvl, $uid]);
        });
        $newCd = hookData($newLvl)[1];
        answerCb($cbId, "⬆️ قلاب به سطح {$newLvl} ارتقا یافت!");
        editMsg($chatId, $mid, "⬆️ <b>قلاب ارتقا پیدا کرد!</b>\n\n"
            . quote("⭐️ سطح جدید : {$newLvl}\n⌛️ کولداون جدید : {$newCd} ثانیه") . "\n⏳ در حال بازگشت به پنل قلاب...", null);
        panelDelay();
        showHookPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'sellbone_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $pending = one('SELECT * FROM pending_bones WHERE user_id=?', [$uid]);
        if (!$pending) {
            answerCb($cbId, '❌ استخوانی برای فروش نیست!', true);
            return;
        }
        if ((time() - ts($pending['caught_at'])) > BONE_SELL_TIME) {
            q('DELETE FROM pending_bones WHERE user_id=?', [$uid]);
            answerCb($cbId, '⌛️ وقتت تموم شد! استخوان از دست رفت.', true);
            editMsg($chatId, $mid, '⌛️ <b>وقت تموم شد!</b>\n\n' . quote('استخوان از دست رفت 😿'), null);
            return;
        }
        $price = (int) $pending['price'];
        transact(static function () use ($uid, $price) {
            addPoints($uid, $price);
            q('DELETE FROM pending_bones WHERE user_id=?', [$uid]);
        });
        answerCb($cbId, '💰 +' . nf($price) . ' پوینت!');
        editMsg($chatId, $mid,
            "💰 <b>" . h($pending['bone_name']) . "</b> رو فروختی!\n\n"
            . quote("⚖️ وزن : " . $pending['weight'] . " kg\n🦴 +" . nf($price) . ' هاپ پوینت دریافت کردی!'), null);
        return;
    }

    if (str_starts_with($data, 'feeddog_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $pending = one('SELECT * FROM pending_bones WHERE user_id=?', [$uid]);
        $dog = getDog($uid);
        if (!$pending) {
            answerCb($cbId, '❌ استخوانی نداری!', true);
            return;
        }
        if (!$dog) {
            answerCb($cbId, '❌ سگی نداری! اول سگ بخر 🐕', true);
            return;
        }
        if ((time() - ts($pending['caught_at'])) > BONE_SELL_TIME) {
            q('DELETE FROM pending_bones WHERE user_id=?', [$uid]);
            answerCb($cbId, '⌛️ وقتت تموم شد! استخوان از دست رفت.', true);
            return;
        }
        $duration = calcFeedDuration((string) $pending['bone_name'], (float) $pending['weight']);
        $base = isFuture($dog['fed_until']) ? ts($dog['fed_until']) : time();
        $newFed = date('Y-m-d H:i:s', $base + $duration);
        $icon = boneRarity((string) $pending['bone_name']);

        transact(static function () use ($uid, $newFed) {
            q('UPDATE dogs SET fed_until=? WHERE user_id=?', [$newFed, $uid]);
            q('DELETE FROM pending_bones WHERE user_id=?', [$uid]);
        });

        answerCb($cbId, '🍖 سگت سیر شد!');
        editMsg($chatId, $mid,
            "🍖 <b>" . h($pending['bone_name']) . "</b> رو به سگت دادی!\n\n"
            . quote(
                "⚖️ وزن : " . $pending['weight'] . " kg\n"
                . "🏷️ دسته : {$icon} " . (RARITY_ICON_MAP[$icon] ?? '') . "\n"
                . "⏱️ مدت سیری : " . intdiv($duration, 60) . " دقیقه\n"
                . "😋 سگت تا " . date('H:i', ts($newFed)) . ' سیره! 🐕'
            ), null);
        return;
    }

    // ── سگ ────────────────────────────────────────────────────────────────
    if (str_starts_with($data, 'buydog_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        if (getDog($uid)) {
            answerCb($cbId, '⚠️ قبلاً سگ خریدی!', true);
            return;
        }
        $cost = withDiscount(DOG_COST, itemDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری!', true);
            return;
        }
        transact(static function () use ($uid, $cost, $chatId) {
            deductPoints($uid, $cost);
            q("INSERT INTO dogs (user_id, name, level, rank, points_box, last_collect)
               VALUES (?, 'سگولو', 1, 1, 0, ?) ON CONFLICT(user_id) DO NOTHING", [$uid, nowIso()]);
            q('UPDATE groups SET total_dogs = total_dogs + 1 WHERE group_id=?', [$chatId]);
        });
        answerCb($cbId, '🎉 سگت رو خریدی!');
        editMsg($chatId, $mid, "🎉 <b>تبریک! سگت رو خریدی!</b>\n\n"
            . quote("🐕 اسمش <b>سگولو</b> ه — می‌تونی عوضش کنی!\n🍖 یادت باشه بهش استخوان بدی!")
            . "\n⏳ در حال بازگشت به پنل سگ...", null);
        panelDelay();
        showDogPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'collect_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $dog = getDog($uid);
        if (!$dog) {
            answerCb($cbId, '❌ سگی نداری!', true);
            return;
        }
        $pending = calcDogPoints($dog, $chatId) + (float) $dog['points_box'];
        if ($pending < 1) {
            answerCb($cbId, '📦 جعبه خالیه!', true);
            return;
        }
        $amount = (int) $pending;
        transact(static function () use ($uid, $amount) {
            q('UPDATE dogs SET points_box=0, last_collect=? WHERE user_id=?', [nowIso(), $uid]);
            addPoints($uid, $amount);
        });
        answerCb($cbId, '✅ +' . nf($amount) . ' پوینت برداشت شد!');
        editMsg($chatId, $mid, "✅ <b>" . nf($amount) . "</b> هاپ پوینت از جعبه سگت برداشت شد! 🦴\n\n⏳ در حال بازگشت به پنل سگ...", null);
        panelDelay();
        showDogPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'feed_') && !str_starts_with($data, 'feeddog_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $dog = getDog($uid);
        if (!$dog) {
            answerCb($cbId, '❌ سگی نداری!', true);
            return;
        }
        $cost = withDiscount(BONE_COST, itemDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        $duration = calcFeedDuration('استخوان کوچیک 🦴', 0.2);
        $base = isFuture($dog['fed_until']) ? ts($dog['fed_until']) : time();
        $newFed = date('Y-m-d H:i:s', $base + $duration);

        transact(static function () use ($uid, $cost, $newFed) {
            deductPoints($uid, $cost);
            q('UPDATE dogs SET fed_until=? WHERE user_id=?', [$newFed, $uid]);
        });

        answerCb($cbId, '🍖 استخوان خریدی!');
        editMsg($chatId, $mid, "🍖 <b>استخوان کوچیک خریدی!</b>\n\n"
            . quote(
                "💰 هزینه : " . nf($cost) . " هاپ پوینت\n"
                . "⏱️ مدت سیری : " . intdiv($duration, 60) . " دقیقه\n"
                . "⏰ سگت تا " . date('H:i', ts($newFed)) . ' سیره! 🐕'
            ) . "\n⏳ در حال بازگشت به پنل سگ...", null);
        panelDelay();
        showDogPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'upgradedog_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $dog = getDog($uid);
        if (!$dog || (int) $dog['level'] >= DOG_MAX_LEVEL) {
            answerCb($cbId, '🏆 سگت ماکسه!', true);
            return;
        }
        $cost = withDiscount((int) dogData((int) $dog['level'])[2], upgradeDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        $newLevel = (int) $dog['level'] + 1;
        transact(static function () use ($uid, $cost, $newLevel) {
            deductPoints($uid, $cost);
            q('UPDATE dogs SET level=? WHERE user_id=?', [$newLevel, $uid]);
        });
        [$newRate, $newCap] = dogData($newLevel);
        answerCb($cbId, "⬆️ سگت به سطح {$newLevel} رسید!");
        editMsg($chatId, $mid, "⬆️ <b>سگت ارتقا پیدا کرد!</b>\n\n"
            . quote(
                "⭐️ سطح جدید : {$newLevel}\n"
                . "⚡️ تولید جدید : " . number_format($newRate, 2) . " پوینت/ثانیه\n"
                . "📦 ظرفیت جدید : " . nf($newCap)
            ) . "\n⏳ در حال بازگشت به پنل سگ...", null);
        panelDelay();
        showDogPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'rankup_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        $dog = getDog($uid);
        if (!$dog || (int) $dog['rank'] >= DOG_MAX_RANK) {
            answerCb($cbId, '🏆 مقام سگت ماکسه!', true);
            return;
        }
        $needLevel = (int) $dog['rank'] * 2;
        if ((int) $dog['level'] < $needLevel) {
            answerCb($cbId, "❌ سگت باید حداقل سطح {$needLevel} باشه!", true);
            return;
        }
        $newRank = (int) $dog['rank'] + 1;
        q('UPDATE dogs SET rank=?, level=1, points_box=0, last_collect=? WHERE user_id=?', [$newRank, nowIso(), $uid]);
        [$rankName, $rankMult] = dogRank($newRank);
        answerCb($cbId, "🏅 مقام جدید: {$rankName}");
        editMsg($chatId, $mid, "🏅 <b>مقام ارتقا پیدا کرد!</b>\n\n"
            . quote("🎖 مقام جدید : " . h($rankName) . "\n✨ ضریب تولید : {$rankMult}x\n\n⚠️ سطح سگ به ۱ برگشت اما قوی‌تر از قبله!")
            . "\n⏳ در حال بازگشت به پنل سگ...", null);
        panelDelay();
        showDogPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'rename_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        if (!getDog($uid)) {
            answerCb($cbId, '❌ سگی نداری!', true);
            return;
        }
        setState($uid, ['flow' => 'rename_dog']);
        answerCb($cbId);
        editMsg($chatId, $mid, "✏️ <b>اسم جدید سگت رو بنویس:</b>\n" . quote('حداکثر ۱۵ کاراکتر — برای لغو بنویس: لغو'), null);
        return;
    }

    handleCallback2($cbq, $data, $uid, $cbId, $chatId, $mid, $message);
}

/** بخش دوم روتر کال‌بک: بانک، زندان، قاچاق، کارخانه */
function handleCallback2(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid, array $message): void
{
    // ── بانک ──────────────────────────────────────────────────────────────
    if (str_starts_with($data, 'openbank_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        if (getBank($uid)) {
            answerCb($cbId, '⚠️ قبلاً حساب باز کردی!', true);
            return;
        }
        $cost = withDiscount(BANK_OPEN_COST, itemDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        $acc = genAccountNumber();
        transact(static function () use ($uid, $cost, $acc) {
            deductPoints($uid, $cost);
            q('INSERT INTO bank (user_id, balance, account_number, opened_at, last_interest)
               VALUES (?, 0, ?, ?, NULL) ON CONFLICT(user_id) DO NOTHING', [$uid, $acc, nowIso()]);
        });
        answerCb($cbId, '🎉 حساب باز شد!');
        editMsg($chatId, $mid, "🎉 <b>حساب بانکی افتتاح شد!</b>\n\n"
            . quote("💳 شماره حساب : <code>{$acc}</code>\n📈 سود روزانه ۳٪ از موجودی")
            . "\n⏳ در حال بازگشت به پنل بانک...", null);
        panelDelay();
        showBankPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'bank_deposit_') || str_starts_with($data, 'bank_withdraw_')) {
        $target = cbInt($data, 2);
        if (!ownsButton($cbq, $target)) { return; }
        if (!getBank($uid)) {
            answerCb($cbId, '❌ حساب بانکی نداری!', true);
            return;
        }
        $isDeposit = str_starts_with($data, 'bank_deposit_');
        setState($uid, ['flow' => 'bank', 'action' => $isDeposit ? 'deposit' : 'withdraw']);
        answerCb($cbId);
        editMsg($chatId, $mid,
            ($isDeposit ? "➕ <b>چقدر می‌خوای واریز کنی؟</b>" : "➖ <b>چقدر می‌خوای برداشت کنی؟</b>") . "\n"
            . quote('مبلغ رو بنویس (مثلاً: 1000 یا 5k)  —  برای لغو بنویس: لغو'), null);
        return;
    }

    if (str_starts_with($data, 'bank_interest_')) {
        $target = cbInt($data, 2);
        if (!ownsButton($cbq, $target)) { return; }
        $bank = getBank($uid);
        if (!$bank) {
            answerCb($cbId, '❌ حساب بانکی نداری!', true);
            return;
        }
        if (!empty($bank['last_interest'])) {
            $elapsed = time() - ts($bank['last_interest']);
            if ($elapsed < 86400) {
                answerCb($cbId, '⌛️ ' . fmtClock(86400 - $elapsed) . ' تا سود بعدی', true);
                return;
            }
        }
        $rate = BANK_INTEREST_RATE * bankInterestMultiplier($chatId);
        $interest = (int) min((float) $bank['balance'] * $rate, BANK_MAX_INTEREST);
        if ($interest < 1) {
            answerCb($cbId, '❌ موجودی خیلی کمه!', true);
            return;
        }
        q('UPDATE bank SET balance = balance + ?, last_interest = ? WHERE user_id=?', [$interest, nowIso(), $uid]);
        answerCb($cbId, '💸 +' . nf($interest) . ' سود گرفتی!');
        editMsg($chatId, $mid, "💸 <b>سود دریافت شد!</b>\n\n"
            . quote("📈 +" . nf($interest) . " هاپ پوینت به بانکت اضافه شد!\n📅 سود بعدی : ۲۴ ساعت دیگه")
            . "\n⏳ در حال بازگشت به پنل بانک...", null);
        panelDelay();
        showBankPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'bank_changenum_')) {
        $target = cbInt($data, 2);
        if (!ownsButton($cbq, $target)) { return; }
        $bank = getBank($uid);
        if (!$bank) {
            answerCb($cbId, '❌ حساب بانکی نداری!', true);
            return;
        }
        if (!empty($bank['last_num_change'])) {
            $elapsed = time() - ts($bank['last_num_change']);
            if ($elapsed < BANK_NUM_CHANGE_CD) {
                answerCb($cbId, '⌛️ ' . fmtDur(BANK_NUM_CHANGE_CD - $elapsed) . ' دیگه می‌تونی عوض کنی', true);
                return;
            }
        }
        $cost = withDiscount(BANK_NUM_CHANGE_COST, itemDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        $newNum = genAccountNumber();
        transact(static function () use ($uid, $cost, $newNum) {
            deductPoints($uid, $cost);
            q('UPDATE bank SET account_number=?, last_num_change=? WHERE user_id=?', [$newNum, nowIso(), $uid]);
        });
        answerCb($cbId, '🔄 شماره حساب عوض شد!');
        editMsg($chatId, $mid, "🔄 <b>شماره حساب تغییر کرد!</b>\n\n"
            . quote("💳 شماره جدید : <code>{$newNum}</code>") . "\n⏳ در حال بازگشت به پنل بانک...", null);
        panelDelay();
        showBankPanelEdit($cbq, $uid);
        return;
    }

    // ── زندان ─────────────────────────────────────────────────────────────
    if (str_starts_with($data, 'bail_')) {
        $target = cbInt($data, 1);
        if (!ownsButton($cbq, $target)) { return; }
        [$jailed, $jailRow] = jailStatus($uid);
        if (!$jailed) {
            answerCb($cbId, '✅ تو آزادی!', true);
            return;
        }
        $leftSec = secsLeft($jailRow['release_at']);
        $bailCost = (int) (($leftSec / 60) * BAIL_COST_PER_MIN);
        if (userPoints($uid) < $bailCost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($bailCost), true);
            return;
        }
        $workPts = (int) ($jailRow['work_points'] ?? 0);
        transact(static function () use ($uid, $bailCost, $workPts) {
            deductPoints($uid, $bailCost);
            if ($workPts > 0) {
                addPoints($uid, $workPts);
            }
            q('DELETE FROM jail WHERE user_id=?', [$uid]);
        });
        $body = "💸 " . nf($bailCost) . " هاپ پوینت پرداخت کردی";
        if ($workPts > 0) {
            $body .= "\n⛏ +" . nf($workPts) . ' پوینت از کار در زندان هم گرفتی!';
        }
        answerCb($cbId, '🎉 آزاد شدی!');
        editMsg($chatId, $mid, "🎉 <b>آزاد شدی!</b>\n\n" . quote($body) . "\n🐕 حالا می‌تونی دوباره هاپ کنی!", null);
        return;
    }

    if (str_starts_with($data, 'jail_escape_')) {
        $target = cbInt($data, 2);
        if (!ownsButton($cbq, $target)) { return; }
        [$jailed, $jailRow] = jailStatus($uid);
        if (!$jailed) {
            answerCb($cbId, '✅ تو آزادی!', true);
            return;
        }
        if (chance(JAIL_ESCAPE_CHANCE)) {
            q('DELETE FROM jail WHERE user_id=?', [$uid]);
            answerCb($cbId, '🏃 فرار موفق!');
            editMsg($chatId, $mid, "🏃 <b>فرار موفق!</b>\n\n" . quote('تونستی از زندان فرار کنی! 🎉'), null);
            return;
        }
        $newRel = date('Y-m-d H:i:s', ts($jailRow['release_at']) + JAIL_ESCAPE_PENALTY);
        q('UPDATE jail SET release_at=? WHERE user_id=?', [$newRel, $uid]);
        answerCb($cbId, '❌ گرفتنت! +' . intdiv(JAIL_ESCAPE_PENALTY, 60) . ' دقیقه اضافه شد. (' . fmtClock(secsLeft($newRel)) . ' مونده)', true);
        return;
    }

    if (str_starts_with($data, 'jail_work_')) {
        $target = cbInt($data, 2);
        if (!ownsButton($cbq, $target)) { return; }
        [$jailed, $jailRow] = jailStatus($uid);
        if (!$jailed) {
            answerCb($cbId, '✅ تو آزادی!', true);
            return;
        }
        if (!empty($jailRow['last_work'])) {
            $elapsed = time() - ts($jailRow['last_work']);
            if ($elapsed < JAIL_WORK_INTERVAL) {
                answerCb($cbId, '⌛️ ' . fmtClock(JAIL_WORK_INTERVAL - $elapsed) . ' تا کار بعدی!', true);
                return;
            }
        }
        q('UPDATE jail SET work_points = work_points + ?, last_work = ? WHERE user_id=?',
            [JAIL_WORK_EARN, nowIso(), $uid]);
        $newWp = (int) val('SELECT work_points FROM jail WHERE user_id=?', [$uid], 0);
        answerCb($cbId, '⛏ +' . JAIL_WORK_EARN . ' پوینت جمع کردی! (جمع: ' . nf($newWp) . ')', true);
        return;
    }

    // ── قاچاق ─────────────────────────────────────────────────────────────
    if (preg_match('/^smuggle_(\d+)_(\d+)$/', $data, $m)) {
        $count = (int) $m[1];
        $target = (int) $m[2];
        if (!ownsButton($cbq, $target)) { return; }
        if (isJailed($uid)) {
            answerCb($cbId, '⛓️ الان زندانی هستی!', true);
            return;
        }
        $strays = strayCount($uid);
        if ($strays < $count) {
            answerCb($cbId, '❌ سگ خیابونی کافی نداری!', true);
            return;
        }
        $catchChance = SMUGGLE_BASE_CATCH + ($count * SMUGGLE_CATCH_PER);

        if (chance($catchChance)) {
            transact(static function () use ($uid, $count) {
                addStrays($uid, -$count);
                jailUser($uid, "قاچاق {$count} سگ خیابونی", SMUGGLE_JAIL_MINS * 60);
            });
            answerCb($cbId, '🚔 لو رفتی!');
            editMsg($chatId, $mid, "🚔 <b>لو رفتی!</b>\n\n"
                . quote("پلیس هاپو {$count} تا از سگ‌هات رو مصادره کرد!\n⛓️ " . SMUGGLE_JAIL_MINS . ' دقیقه زندان منتظرته...'), null);
            return;
        }

        $reward = $count * SMUGGLE_REWARD_EACH;
        addPoints($uid, $reward);
        answerCb($cbId, '🥷 +' . nf($reward) . ' پوینت!');
        editMsg($chatId, $mid, "🥷 <b>محموله سالم رسید!</b>\n\n"
            . quote(
                "🐕 {$count} تا سگ قاچاق شدن!\n"
                . "💰 +" . nf($reward) . " هاپ پوینت به جیبت رفت!\n"
                . "⚠️ شانس لو رفتن بود : " . (int) round($catchChance * 100) . '٪'
            ), null);
        return;
    }

    // ── کارخانه ───────────────────────────────────────────────────────────
    if (str_starts_with($data, 'factory_')) {
        handleFactoryCallback($cbq, $data, $uid, $cbId, $chatId, $mid);
        return;
    }

    handleCallback3($cbq, $data, $uid, $cbId, $chatId, $mid, $message);
}

/** کال‌بک‌های کارخانه */
function handleFactoryCallback(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid): void
{
    if (str_starts_with($data, 'factory_build_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        if (getFactory($uid)) {
            answerCb($cbId, '❌ قبلاً کارخونه ساختی!', true);
            return;
        }
        $cost = withDiscount(FACTORY_BUILD_COST, itemDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        transact(static function () use ($uid, $cost) {
            deductPoints($uid, $cost);
            q('INSERT INTO factories (user_id, level, exp, warehouse_level, machine_level, stock, producing, product_idx)
               VALUES (?,1,0,1,1,0,0,0) ON CONFLICT(user_id) DO NOTHING', [$uid]);
        });
        answerCb($cbId, '✅ کارخونه ساخته شد!');
        editMsg($chatId, $mid, "🎉 <b>کارخونه‌ات ساخته شد!</b>\n\n"
            . quote(
                "🏭 حالا می‌تونی محصول تولید کنی!\n"
                . "🐕 هر سگ خیابونی که نجات بدی = یه کارگر بیشتر\n"
                . "💰 یا با پوینت (" . nf(FACTORY_WORKER_COST) . ' تا) کارگر بخر!'
            ) . "\n⏳ در حال بازگشت به پنل کارخونه...", null);
        panelDelay();
        showFactoryPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'factory_produce_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $factory = getFactory($uid);
        if (!$factory) {
            answerCb($cbId, '❌ کارخونه نداری!', true);
            return;
        }
        if ((int) $factory['producing'] && isFuture($factory['production_end'])) {
            answerCb($cbId, '⚙️ کارخونه داره کار می‌کنه! ' . fmtDur(secsLeft($factory['production_end'])) . ' مونده.', true);
            return;
        }
        if (productionDone($factory)) {
            answerCb($cbId, '📦 اول محصولات آماده رو جمع‌آوری کن!', true);
            return;
        }
        $workers = strayCount($uid);
        if ($workers === 0) {
            answerCb($cbId, '❌ هیچ کارگری نداری! از دکمه «استخدام» استفاده کن یا کارگر بخر 🐕', true);
            return;
        }
        $speed = factorySpeedMultiplier($chatId);
        if ($speed <= 0) {
            answerCb($cbId, '⚠️ تولید کارخونه‌ها به دلیل بحران متوقفه!', true);
            return;
        }
        $machineCd = MACHINE_LEVELS[(int) $factory['machine_level']][0] ?? 120;
        $realCd = max(3, (int) round($machineCd / $speed));

        $rows = [];
        foreach (availableProducts((int) $factory['level']) as $idx => $p) {
            [$marketP, $mult] = marketPrice($idx);
            $trend = $mult >= 1.2 ? '📈' : ($mult <= 0.75 ? '📉' : '➡️');
            $rows[] = [btn($p[0] . ' | ' . nf($p[1]) . '🪙 | ' . nf($marketP) . $trend, "factory_start_{$uid}_{$idx}", 'primary')];
        }
        $rows[] = [btn('انصراف', 'cancel', 'danger')];

        answerCb($cbId);
        editMsg($chatId, $mid, "🛒 <b>انتخاب محصول برای تولید</b>\n\n"
            . quote(
                "🐈 کارگران : {$workers} | هر دوره {$workers} محصول\n"
                . "⌛️ زمان هر دوره : {$realCd} ثانیه\n"
                . "📊 قیمت‌های بازار هر ساعت عوض میشن!"
            ), kb($rows));
        return;
    }

    if (preg_match('/^factory_start_(\d+)_(\d+)$/', $data, $m)) {
        if (!ownsButton($cbq, (int) $m[1])) { return; }
        $productIdx = (int) $m[2];
        if (!isset(FACTORY_PRODUCTS[$productIdx])) {
            answerCb($cbId, '❌ محصول نامعتبر!', true);
            return;
        }
        $factory = getFactory($uid);
        if (!$factory) {
            answerCb($cbId, '❌ کارخونه نداری!', true);
            return;
        }
        if ((int) $factory['producing'] && isFuture($factory['production_end'])) {
            answerCb($cbId, '⚙️ کارخونه مشغوله!', true);
            return;
        }
        [$name, $cost, , $minLvl] = FACTORY_PRODUCTS[$productIdx];
        if ((int) $factory['level'] < $minLvl) {
            answerCb($cbId, "❌ برای این محصول سطح {$minLvl} کارخونه لازمه!", true);
            return;
        }
        $cost = withDiscount((int) $cost, itemDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! هزینه: ' . nf($cost), true);
            return;
        }
        $cap = WAREHOUSE_LEVELS[(int) $factory['warehouse_level']][0] ?? 20;
        if ((int) $factory['stock'] >= $cap) {
            answerCb($cbId, '❌ انبارت پره! اول بفروش!', true);
            return;
        }

        $speed = factorySpeedMultiplier($chatId);
        $machineCd = MACHINE_LEVELS[(int) $factory['machine_level']][0] ?? 120;
        $realCd = $speed > 0 ? max(3, (int) round($machineCd / $speed)) : $machineCd;
        $end = isoPlus($realCd);

        transact(static function () use ($uid, $cost, $productIdx, $end) {
            deductPoints($uid, $cost);
            q('UPDATE factories SET producing=1, product_idx=?, production_end=?, last_produced=? WHERE user_id=?',
                [$productIdx, $end, nowIso(), $uid]);
        });

        answerCb($cbId, '⚙️ تولید شروع شد!');
        editMsg($chatId, $mid, "⚙️ <b>تولید شروع شد!</b>\n\n"
            . quote(
                "📦 محصول : " . h($name) . "\n"
                . "💰 هزینه : " . nf($cost) . " پوینت\n"
                . "🐈 کارگران : " . strayCount($uid) . "\n"
                . "⌛️ زمان دوره : " . fmtDur($realCd)
            ) . "\nبنویس <b>کارخونه</b> تا محصولات رو جمع‌آوری کنی!", null);
        return;
    }

    if (str_starts_with($data, 'factory_collect_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $factory = getFactory($uid);
        if (!productionDone($factory)) {
            answerCb($cbId, '⌛️ هنوز تولید تموم نشده!', true);
            return;
        }
        $added = collectProduction($uid);
        if ($added <= 0) {
            answerCb($cbId, '❌ انبارت پره! اول بفروش.', true);
            return;
        }
        $factory = getFactory($uid);
        $productName = FACTORY_PRODUCTS[(int) $factory['product_idx']][0] ?? '—';
        $cap = WAREHOUSE_LEVELS[(int) $factory['warehouse_level']][0] ?? 20;

        answerCb($cbId, "📦 {$added} محصول جمع‌آوری شد!");
        editMsg($chatId, $mid, "📦 <b>{$added} عدد " . h($productName) . " به انبار اضافه شد!</b>\n\n"
            . quote("🧳 موجودی انبار : " . (int) $factory['stock'] . "/{$cap}\n⭐️ سطح کارخونه : " . (int) $factory['level'])
            . "\n⏳ در حال بازگشت به پنل کارخونه...", null);
        panelDelay();
        showFactoryPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'factory_sell_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $factory = getFactory($uid);
        if (!$factory) {
            answerCb($cbId, '❌ کارخونه نداری!', true);
            return;
        }
        if ((int) $factory['stock'] <= 0) {
            answerCb($cbId, '❌ انبارت خالیه! اول محصول تولید کن.', true);
            return;
        }
        [$price, $mult] = marketPrice((int) $factory['product_idx']);
        $totalEarn = $price * (int) $factory['stock'];
        $name = FACTORY_PRODUCTS[(int) $factory['product_idx']][0] ?? '—';
        $trend = $mult >= 1.5 ? '📈 بازار داغه!' : ($mult <= 0.7 ? '📉 بازار سرده...' : '➡️ بازار معمولیه');

        $mp = one('SELECT updated_at FROM market_prices WHERE product_idx=?', [(int) $factory['product_idx']]);
        $timeLeft = '';
        if ($mp && $mp['updated_at']) {
            $left = max(0, 3600 - (time() - ts($mp['updated_at'])));
            $timeLeft = "\n🕐 قیمت تا " . fmtDur($left) . ' دیگه ثابته';
        }

        answerCb($cbId);
        editMsg($chatId, $mid, "💹 <b>بازار میویی</b>\n\n"
            . quote(
                "📦 محصول : " . h($name) . "\n"
                . "🧳 موجودی : " . (int) $factory['stock'] . " عدد\n"
                . "💰 قیمت هر عدد : " . nf($price) . " (×{$mult})\n"
                . "🤑 درآمد کل : " . nf($totalEarn) . " پوینت\n"
                . $trend . $timeLeft
            ) . "\n⚠️ قیمت‌ها هر ساعت عوض میشن!",
            kb([[
                btn('فروش همه (' . nf($totalEarn) . ' پوینت)', "factory_confirmsell_{$uid}", 'success'),
                btn('صبر می‌کنم', 'cancel', 'danger'),
            ]]));
        return;
    }

    if (str_starts_with($data, 'factory_confirmsell_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $factory = getFactory($uid);
        if (!$factory || (int) $factory['stock'] <= 0) {
            answerCb($cbId, '❌ انباری برای فروش نیست!', true);
            return;
        }
        [$price, $mult] = marketPrice((int) $factory['product_idx']);
        $productName = FACTORY_PRODUCTS[(int) $factory['product_idx']][0] ?? '—';
        $soldCount = (int) $factory['stock'];
        $totalEarn = $price * $soldCount;

        $today = date('Y-m-d');
        $soldToday = (float) val(
            "SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='factory_sell' AND DATE(created_at)=?",
            [$uid, $today], 0);
        $remainingCap = max(0, FACTORY_DAILY_CAP - $soldToday);

        if ($remainingCap <= 0) {
            answerCb($cbId, '❌ سقف فروش روزانه (' . nf(FACTORY_DAILY_CAP) . ') تموم شده! فردا بیا.', true);
            return;
        }
        if ($totalEarn > $remainingCap && $totalEarn > 0) {
            $ratio = $remainingCap / $totalEarn;
            $soldCount = max(1, (int) ((int) $factory['stock'] * $ratio));
            $totalEarn = $price * $soldCount;
        }

        $tax = (int) ($totalEarn * FACTORY_SELL_TAX);
        $netEarn = $totalEarn - $tax;

        transact(static function () use ($uid, $netEarn, $soldCount, $totalEarn, $tax) {
            addPoints($uid, $netEarn);
            q('UPDATE factories SET stock = MAX(0, stock - ?) WHERE user_id=?', [$soldCount, $uid]);
            q('INSERT INTO transactions (user_id, type, amount, created_at) VALUES (?,?,?,?)',
                [$uid, 'factory_sell', $totalEarn, nowIso()]);
            changeTreasury($tax, 'مالیات فروش کارخانه', $uid);
        });

        $remainingAfter = $remainingCap - $totalEarn;
        $capMsg = $remainingAfter > 0
            ? "\n📊 باقی‌مونده سقف امروز : " . nf($remainingAfter)
            : "\n🔴 سقف فروش امروز تموم شد!";
        $profitMsg = $mult >= 1.2 ? '🤑 سود خوبی کردی!' : ($mult < 1.0 ? '😿 ضرر کردی...' : '😊 معامله منصفانه‌ای بود!');

        answerCb($cbId, "✅ {$soldCount} عدد فروخته شد!");
        editMsg($chatId, $mid, "✅ <b>فروش انجام شد!</b>\n\n"
            . quote(
                "📦 {$soldCount} عدد " . h($productName) . " فروخته شد\n"
                . "💰 درآمد کل : " . nf($totalEarn) . "\n"
                . "🏛 مالیات " . (int) (FACTORY_SELL_TAX * 100) . "٪ : -" . nf($tax) . "\n"
                . "💵 خالص دریافتی : +" . nf($netEarn) . "\n"
                . "📊 ضریب بازار : ×{$mult}"
            ) . "\n{$profitMsg}{$capMsg}", null);
        return;
    }

    if (str_starts_with($data, 'factory_upmachine_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $factory = getFactory($uid);
        if (!$factory) {
            answerCb($cbId, '❌ کارخونه نداری!', true);
            return;
        }
        if ((int) $factory['machine_level'] >= MACHINE_MAX_LEVEL) {
            answerCb($cbId, '🏆 دستگاهت ماکسه!', true);
            return;
        }
        $nextLvl = (int) $factory['machine_level'] + 1;
        $cost = withDiscount((int) MACHINE_LEVELS[$nextLvl][1], upgradeDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        transact(static function () use ($uid, $cost, $nextLvl) {
            deductPoints($uid, $cost);
            q('UPDATE factories SET machine_level=? WHERE user_id=?', [$nextLvl, $uid]);
        });
        answerCb($cbId, "⬆️ دستگاه به سطح {$nextLvl} رسید!");
        editMsg($chatId, $mid, "⬆️ <b>دستگاه ارتقا پیدا کرد!</b>\n\n"
            . quote("⭐️ سطح جدید : {$nextLvl}/" . MACHINE_MAX_LEVEL . "\n⌛️ زمان تولید جدید : " . MACHINE_LEVELS[$nextLvl][0] . ' ثانیه')
            . "\n⏳ در حال بازگشت به پنل کارخونه...", null);
        panelDelay();
        showFactoryPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'factory_upwarehouse_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $factory = getFactory($uid);
        if (!$factory) {
            answerCb($cbId, '❌ کارخونه نداری!', true);
            return;
        }
        if ((int) $factory['warehouse_level'] >= WAREHOUSE_MAX_LEVEL) {
            answerCb($cbId, '🏆 انبارت ماکسه!', true);
            return;
        }
        $nextLvl = (int) $factory['warehouse_level'] + 1;
        $cost = withDiscount((int) WAREHOUSE_LEVELS[$nextLvl][1], upgradeDiscount());
        if (userPoints($uid) < $cost) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($cost), true);
            return;
        }
        transact(static function () use ($uid, $cost, $nextLvl) {
            deductPoints($uid, $cost);
            q('UPDATE factories SET warehouse_level=? WHERE user_id=?', [$nextLvl, $uid]);
        });
        answerCb($cbId, "⬆️ انبار به سطح {$nextLvl} رسید!");
        editMsg($chatId, $mid, "⬆️ <b>انبار ارتقا پیدا کرد!</b>\n\n"
            . quote("⭐️ سطح جدید : {$nextLvl}/" . WAREHOUSE_MAX_LEVEL . "\n🧳 ظرفیت جدید : " . nf(WAREHOUSE_LEVELS[$nextLvl][0]) . ' محصول')
            . "\n⏳ در حال بازگشت به پنل کارخونه...", null);
        panelDelay();
        showFactoryPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'factory_hire_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $strays = strayCount($uid);
        $factory = getFactory($uid);
        $cap = $factory ? (WAREHOUSE_LEVELS[(int) $factory['warehouse_level']][0] ?? 20) : 20;
        answerCb($cbId);
        editMsg($chatId, $mid, "🐕 <b>کارگرهای کارخونه</b>\n\n"
            . quote(
                "👷 کارگرهای فعلی : <b>{$strays}</b> سگ\n"
                . "⚙️ هر دوره تولید : {$strays} محصول\n"
                . "🧳 ظرفیت انبار : {$cap} محصول\n\n"
                . "💡 هر سگ خیابونی که نجات بدی خودکار کارگر کارخونه میشه.\n"
                . "🐾 برای دیدن سگ خیابونی توی گروه <b>هاپ</b> بزن!"
            ),
            kb([
                [btn('خرید کارگر (' . nf(withDiscount(FACTORY_WORKER_COST, itemDiscount())) . ')', "factory_buyworker_{$uid}", 'success')],
                [btn('برگشت به کارخونه 🔙', "factory_panel_{$uid}", 'primary')],
            ]));
        return;
    }

    if (str_starts_with($data, 'factory_panel_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        answerCb($cbId);
        showFactoryPanelEdit($cbq, $uid);
        return;
    }

    if (preg_match('/^factory_buyworkerconf_(\d+)_(\d+)$/', $data, $m)) {
        if (!ownsButton($cbq, (int) $m[1])) { return; }
        $n = (int) $m[2];
        if ($n < 1 || $n > 50) {
            answerCb($cbId, '❌ تعداد نامعتبر!', true);
            return;
        }
        $total = withDiscount($n * FACTORY_WORKER_COST, itemDiscount());
        if (userPoints($uid) < $total) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($total), true);
            return;
        }
        transact(static function () use ($uid, $total, $n) {
            deductPoints($uid, $total);
            addStrays($uid, $n);
        });
        answerCb($cbId, "✅ {$n} کارگر خریداری شد!");
        editMsg($chatId, $mid, "✅ <b>{$n} کارگر جدید به کارخونه‌ات اضافه شد!</b>\n\n"
            . quote("💰 هزینه : " . nf($total) . " هاپ پوینت\n⚙️ هر دوره تولید، {$n} محصول بیشتر می‌گیری!")
            . "\n⏳ در حال بازگشت به پنل کارخونه...", null);
        panelDelay();
        showFactoryPanelEdit($cbq, $uid);
        return;
    }

    if (str_starts_with($data, 'factory_buyworker_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $points = userPoints($uid);
        $rows = [];
        $rowBtns = [];
        foreach ([1, 2, 3, 5, 10] as $n) {
            $total = withDiscount($n * FACTORY_WORKER_COST, itemDiscount());
            if ($points >= $total) {
                $rowBtns[] = btn("{$n} نفر (" . nf($total) . ')', "factory_buyworkerconf_{$uid}_{$n}", 'success');
            }
            if (count($rowBtns) === 2) {
                $rows[] = $rowBtns;
                $rowBtns = [];
            }
        }
        if ($rowBtns !== []) {
            $rows[] = $rowBtns;
        }
        if ($rows === []) {
            answerCb($cbId, '❌ پوینت کافی نداری! حداقل ' . nf(FACTORY_WORKER_COST) . ' لازمه.', true);
            return;
        }
        $rows[] = [btn('انصراف', 'cancel', 'danger')];

        answerCb($cbId);
        editMsg($chatId, $mid, "💰 <b>خرید کارگر با هاپ پوینت</b>\n\n"
            . quote("قیمت هر کارگر : " . nf(withDiscount(FACTORY_WORKER_COST, itemDiscount())) . "\n👛 موجودی : " . nf($points))
            . "\nچند تا کارگر می‌خوای بخری؟", kb($rows));
        return;
    }

    answerCb($cbId);
}

/** بخش سوم روتر کال‌بک: کازینو، مارکت، قرعه‌کشی، دعوت */
function handleCallback3(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid, array $message): void
{
    $from = $cbq['from'];

    // ── منوی بازی و کازینو ────────────────────────────────────────────────
    if (str_starts_with($data, 'game_xo_menu_')) {
        if (!ownsButton($cbq, cbInt($data, 3))) { return; }
        $u = getUser($uid);
        if (!$u || (int) $u['level'] < TABLE_MIN_LEVEL) {
            answerCb($cbId, '🔒 سطح ' . TABLE_MIN_LEVEL . ' لازمه!', true);
            return;
        }
        setState($uid, ['flow' => 'xo_create']);
        answerCb($cbId);
        editMsg($chatId, $mid, "🧩 <b>میز XO</b>\n\n" . quote('شرط چقدر؟ مبلغ رو بنویس  —  برای لغو بنویس: لغو'), null);
        return;
    }

    if (str_starts_with($data, 'casino_menu_')) {
        if (!ownsButton($cbq, cbInt($data, 2))) { return; }
        $u = getUser($uid);
        if (!$u || (int) $u['level'] < CASINO_MIN_LEVEL) {
            answerCb($cbId, '🔒 سطح ' . CASINO_MIN_LEVEL . ' لازمه!', true);
            return;
        }
        answerCb($cbId);
        [$text, $keyboard] = casinoMenuData($u);
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }

    if (preg_match('/^casino_(dice|wheel|gamble)_(\d+)$/', $data, $m)) {
        if (!ownsButton($cbq, (int) $m[2])) { return; }
        $game = $m[1];
        $cdMap = ['dice' => DICE_COOLDOWN, 'wheel' => WHEEL_COOLDOWN, 'gamble' => GAMBLE_COOLDOWN];
        $left = gameCooldownLeft($uid, $game, $cdMap[$game]);
        if ($left > 0) {
            answerCb($cbId, '⌛️ ' . fmtDur($left) . ' تا بازی بعدی!', true);
            return;
        }
        $u = getUser($uid);
        $titles = ['dice' => '🎲 <b>تاس</b>', 'wheel' => '🎰 <b>گردونه شانس</b>', 'gamble' => '🍷 <b>قمار گروهی</b>'];
        $st = ['flow' => $game, 'step' => 'bet'];
        if ($game === 'gamble') {
            $st['group_id'] = $chatId;
        }
        setState($uid, $st);
        answerCb($cbId);
        editMsg($chatId, $mid, $titles[$game] . "\n\n" . quote('💰 موجودی : ' . nf($u['hop_points'] ?? 0))
            . "\nشرط چقدر؟ مبلغ رو بنویس  —  برای لغو بنویس: لغو", null);
        return;
    }

    if (preg_match('/^dice_mode_(evenodd|exact)_(\d+)$/', $data, $m)) {
        if (!ownsButton($cbq, (int) $m[2])) { return; }
        $state = getState($uid);
        if (($state['flow'] ?? '') !== 'dice') {
            answerCb($cbId, '❌ این بازی منقضی شده!', true);
            return;
        }
        $bet = (int) ($state['bet'] ?? 0);
        if ($bet <= 0) {
            answerCb($cbId, '❌ شرط نامعتبر!', true);
            return;
        }
        answerCb($cbId);
        if ($m[1] === 'evenodd') {
            clearState($uid);
            editMsg($chatId, $mid, "🎲 <b>زوج یا فرد؟</b>\n\n" . quote('شرط : ' . nf($bet) . ' — ضریب 1.7x'),
                kb([[
                    btn('زوج', "dice_guess_even_{$uid}_{$bet}", 'primary'),
                    btn('فرد', "dice_guess_odd_{$uid}_{$bet}", 'primary'),
                ]]));
        } else {
            $state['step'] = 'guess_exact';
            setState($uid, $state);
            editMsg($chatId, $mid, "🎲 <b>عدد دقیق (۱ تا ۶) رو بنویس:</b>\n\n" . quote('شرط : ' . nf($bet) . ' — ضریب 4x'), null);
        }
        return;
    }

    if (preg_match('/^dice_guess_(even|odd)_(\d+)_(\d+)$/', $data, $m)) {
        if (!ownsButton($cbq, (int) $m[2])) { return; }
        $guessType = $m[1];
        $bet = (int) $m[3];
        if (userPoints($uid) < $bet) {
            answerCb($cbId, '❌ پوینت کافی نداری!', true);
            return;
        }
        if (gameCooldownLeft($uid, 'dice', DICE_COOLDOWN) > 0) {
            answerCb($cbId, '⌛️ هنوز کولداون داری!', true);
            return;
        }
        answerCb($cbId, '🎲 در حال پرتاب...');
        clearState($uid);
        deductPoints($uid, $bet);
        setGameCooldown($uid, 'dice');

        editMsg($chatId, $mid, "🎲 انتخابت : <b>" . ($guessType === 'even' ? 'زوج' : 'فرد') . '</b> | شرط : ' . nf($bet) . "\n\n⏳ در حال پرتاب تاس...", null);

        $roll = rollDice($chatId);
        $isEven = $roll % 2 === 0;
        $won = ($guessType === 'even' && $isEven) || ($guessType === 'odd' && !$isEven);

        if ($won) {
            $win = (int) ($bet * 1.7);
            addPoints($uid, $win);
            sendMsg($chatId, "✅ عدد <b>{$roll}</b> (" . ($isEven ? 'زوج' : 'فرد') . ")!\n\n"
                . quote('🎉 بردی! +' . nf($win) . ' هاپ پوینت'));
        } else {
            sendMsg($chatId, "💀 عدد <b>{$roll}</b> (" . ($isEven ? 'زوج' : 'فرد') . ")!\n\n"
                . quote('😢 باختی! -' . nf($bet) . ' هاپ پوینت'));
        }
        return;
    }

    if (preg_match('/^wheel_(solo|multi)_(\d+)$/', $data, $m)) {
        if (!ownsButton($cbq, (int) $m[2])) { return; }
        if ($m[1] === 'multi') {
            $u = getUser($uid);
            if ((int) $u['level'] < CASINO_TABLE_LEVEL) {
                answerCb($cbId, '🔒 سطح ' . CASINO_TABLE_LEVEL . ' لازمه!', true);
                return;
            }
        }
        setState($uid, ['flow' => 'wheel', 'step' => 'bet', 'mode' => $m[1]]);
        answerCb($cbId);
        editMsg($chatId, $mid, "🎰 شرط چقدر؟ مبلغ رو بنویس:", null);
        return;
    }

    // ── میز XO ────────────────────────────────────────────────────────────
    if (str_starts_with($data, 'xo_join_')) {
        $tableId = substr($data, 8);
        $table = one('SELECT * FROM game_tables WHERE table_id=?', [$tableId]);
        if (!$table || $table['state'] !== 'waiting') {
            answerCb($cbId, '❌ این میز دیگه موجود نیست!', true);
            return;
        }
        if ((int) $table['creator_id'] === $uid) {
            answerCb($cbId, '❌ نمی‌تونی با خودت بازی کنی!', true);
            return;
        }
        $bet = (int) $table['bet'];
        if (userPoints($uid) < $bet) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($bet), true);
            return;
        }
        $ok = transact(static function () use ($tableId, $uid, $table, $bet) {
            $st = q("UPDATE game_tables SET player2_id=?, state='playing', board=?, current_turn=?
                     WHERE table_id=? AND state='waiting'",
                [$uid, '0,0,0,0,0,0,0,0,0', (int) $table['creator_id'], $tableId]);
            if ($st->rowCount() === 0) {
                return false;
            }
            deductPoints($uid, $bet);
            return true;
        });
        if (!$ok) {
            answerCb($cbId, '❌ یکی زودتر پیوست!', true);
            return;
        }
        $creator = getUser((int) $table['creator_id']);
        $board = array_fill(0, 9, 0);
        answerCb($cbId, '✅ وارد بازی شدی!');
        editMsg($chatId, $mid,
            "🧩 <b>بازی XO شروع شد!</b>\n\n"
            . quote("❌ " . h($creator['first_name'] ?? '?') . " vs ⭕ " . h(displayName($from)) . "\n💰 جایزه : " . nf($bet * 2) . ' هاپ پوینت')
            . "\n" . xoRender($board) . "\n\n🎲 نوبت : ❌ <b>" . h($creator['first_name'] ?? '?') . '</b>',
            xoTableKeyboard($board, $tableId));
        return;
    }

    if (preg_match('/^xo_move_(.+)_(\d+)$/', $data, $m)) {
        $tableId = $m[1];
        $idx = (int) $m[2];
        $table = one('SELECT * FROM game_tables WHERE table_id=?', [$tableId]);
        if (!$table || $table['state'] !== 'playing') {
            answerCb($cbId, '❌ بازی تموم شده!', true);
            return;
        }
        if ((int) $table['current_turn'] !== $uid) {
            answerCb($cbId, '⏳ نوبت تو نیست!', true);
            return;
        }
        $board = array_map('intval', explode(',', (string) $table['board']));
        if (count($board) !== 9 || $idx < 0 || $idx > 8) {
            answerCb($cbId, '❌ خطای تخته!', true);
            return;
        }
        if ($board[$idx] !== 0) {
            answerCb($cbId, '❌ این خونه پره!', true);
            return;
        }

        $isCreator = $uid === (int) $table['creator_id'];
        $board[$idx] = $isCreator ? 1 : 2;
        $nextTurn = $isCreator ? (int) $table['player2_id'] : (int) $table['creator_id'];
        [$winner] = xoWinner($board);

        $p1 = getUser((int) $table['creator_id']);
        $p2 = getUser((int) $table['player2_id']);
        $n1 = h($p1['first_name'] ?? '?');
        $n2 = h($p2['first_name'] ?? '?');
        $bet = (int) $table['bet'];

        if ($winner === 0) {
            q('UPDATE game_tables SET board=?, current_turn=? WHERE table_id=?',
                [implode(',', $board), $nextTurn, $tableId]);
            answerCb($cbId, '✅ حرکتت ثبت شد!');
            $nextName = $nextTurn === (int) $table['creator_id'] ? $n1 : $n2;
            $nextSym = $nextTurn === (int) $table['creator_id'] ? '❌' : '⭕';
            editMsg($chatId, $mid,
                "🧩 <b>بازی XO</b>\n\n" . quote("❌ {$n1} vs ⭕ {$n2}")
                . "\n" . xoRender($board) . "\n\n🎲 نوبت : {$nextSym} <b>{$nextName}</b>",
                xoTableKeyboard($board, $tableId));
            return;
        }

        if ($winner === -1) {
            transact(static function () use ($tableId, $table, $bet) {
                q("UPDATE game_tables SET state='done' WHERE table_id=?", [$tableId]);
                addPoints((int) $table['creator_id'], $bet);
                addPoints((int) $table['player2_id'], $bet);
            });
            setGameCooldown((int) $table['creator_id'], 'xo');
            setGameCooldown((int) $table['player2_id'], 'xo');
            answerCb($cbId, '🤝 مساوی!');
            editMsg($chatId, $mid, "🤝 <b>مساوی!</b>\n\n" . xoRender($board) . "\n\n" . quote('پوینت‌ها برگشت داده شد!'), null);
            return;
        }

        $winnerId = $winner === 1 ? (int) $table['creator_id'] : (int) $table['player2_id'];
        $prize = $bet * 2;
        transact(static function () use ($tableId, $winnerId, $prize) {
            q("UPDATE game_tables SET state='done' WHERE table_id=?", [$tableId]);
            addPoints($winnerId, $prize);
        });
        setGameCooldown((int) $table['creator_id'], 'xo');
        setGameCooldown((int) $table['player2_id'], 'xo');
        $winnerName = $winnerId === (int) $table['creator_id'] ? $n1 : $n2;
        answerCb($cbId, $winnerId === $uid ? '🏆 بردی!' : '😿 باختی!');
        editMsg($chatId, $mid, "🏆 <b>{$winnerName} برنده شد!</b>\n\n" . xoRender($board) . "\n\n"
            . quote('🎉 +' . nf($prize) . ' هاپ پوینت'), null);
        return;
    }

    if (str_starts_with($data, 'xo_cancel_')) {
        $tableId = substr($data, 10);
        $table = one('SELECT * FROM game_tables WHERE table_id=?', [$tableId]);
        if (!$table) {
            answerCb($cbId, '❌ میز پیدا نشد!', true);
            return;
        }
        if ((int) $table['creator_id'] !== $uid) {
            answerCb($cbId, '❌ فقط سازنده میز می‌تونه لغو کنه!', true);
            return;
        }
        if ($table['state'] !== 'waiting') {
            answerCb($cbId, '❌ بازی شروع شده!', true);
            return;
        }
        transact(static function () use ($uid, $table, $tableId) {
            addPoints($uid, (int) $table['bet']);
            q('DELETE FROM game_tables WHERE table_id=?', [$tableId]);
        });
        answerCb($cbId, '❌ میز لغو شد.');
        editMsg($chatId, $mid, '❌ <b>میز لغو شد.</b>' . "\n\n" . quote('پوینتت برگشت داده شد.'), null);
        return;
    }

    // ── قمار گروهی ────────────────────────────────────────────────────────
    if (str_starts_with($data, 'gamble_join_')) {
        $tableId = substr($data, 12);
        $table = one('SELECT * FROM game_tables WHERE table_id=?', [$tableId]);
        if (!$table || $table['state'] !== 'waiting') {
            answerCb($cbId, '❌ این میز دیگه موجود نیست!', true);
            return;
        }
        $players = array_filter(explode(',', (string) $table['board']));
        $playerIds = array_map(static fn($p) => (int) explode(':', $p)[0], $players);
        if (in_array($uid, $playerIds, true)) {
            answerCb($cbId, '❌ قبلاً پیوستی!', true);
            return;
        }
        if (count($players) >= 5) {
            answerCb($cbId, '❌ میز پره! (حداکثر ۵ نفر)', true);
            return;
        }
        $bet = (int) $table['bet'];
        if (userPoints($uid) < $bet) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($bet), true);
            return;
        }
        $players[] = "{$uid}:{$bet}";
        transact(static function () use ($uid, $bet, $players, $tableId) {
            deductPoints($uid, $bet);
            q('UPDATE game_tables SET board=? WHERE table_id=?', [implode(',', $players), $tableId]);
        });
        answerCb($cbId, '🍷 وارد میز شدی!');
        editMsg($chatId, $mid, "🍷 <b>میز قمار</b>\n\n"
            . quote('💰 شرط : ' . nf($bet) . " هاپ پوینت\n👥 بازیکن‌ها : " . count($players) . ' نفر')
            . "\n(سازنده دکمه شروع رو بزنه)",
            kb([[
                btn('پیوستن (' . nf($bet) . ' پوینت) 🍷', "gamble_join_{$tableId}", 'success'),
                btn('شروع! 🎯', "gamble_start_{$tableId}", 'warning'),
            ]]));
        return;
    }

    if (str_starts_with($data, 'gamble_start_')) {
        $tableId = substr($data, 13);
        $table = one('SELECT * FROM game_tables WHERE table_id=?', [$tableId]);
        if (!$table) {
            answerCb($cbId, '❌ میز پیدا نشد!', true);
            return;
        }
        if ((int) $table['creator_id'] !== $uid) {
            answerCb($cbId, '❌ فقط سازنده می‌تونه شروع کنه!', true);
            return;
        }
        if ($table['state'] !== 'waiting') {
            answerCb($cbId, '❌ این میز قبلاً اجرا شده!', true);
            return;
        }
        $players = array_values(array_filter(explode(',', (string) $table['board'])));
        if (count($players) < 2) {
            answerCb($cbId, '❌ حداقل ۲ نفر لازمه!', true);
            return;
        }
        $winnerEntry = $players[array_rand($players)];
        $winnerId = (int) explode(':', $winnerEntry)[0];
        $totalPot = 0;
        foreach ($players as $p) {
            $totalPot += (int) (explode(':', $p)[1] ?? 0);
        }
        $ok = transact(static function () use ($tableId, $winnerId, $totalPot) {
            $st = q("UPDATE game_tables SET state='done' WHERE table_id=? AND state='waiting'", [$tableId]);
            if ($st->rowCount() === 0) {
                return false;
            }
            addPoints($winnerId, $totalPot);
            return true;
        });
        if (!$ok) {
            answerCb($cbId, '❌ این میز قبلاً اجرا شده!', true);
            return;
        }
        foreach ($players as $p) {
            setGameCooldown((int) explode(':', $p)[0], 'gamble');
        }
        $winner = getUser($winnerId);
        answerCb($cbId, '🍀 قرعه کشیده شد!');
        editMsg($chatId, $mid, "🍀 <b>نتیجه قمار!</b>\n\n"
            . quote("🏆 <b>" . h($winner['first_name'] ?? '?') . "</b> برنده شد!\n💰 +" . nf($totalPot) . ' هاپ پوینت'), null);
        return;
    }

    // ── دعوت دوستان ───────────────────────────────────────────────────────
    if ($data === 'ref_mystats') {
        $count = referralCount($uid);
        $rewardS = referralRewardSender();
        answerCb($cbId);
        editMsg($chatId, $mid,
            "👥 <b>دعوت‌های موفق تو: {$count} نفر</b>\n\n"
            . quote("💰 جمع جایزه دریافتی : " . nf($count * $rewardS) . " هاپ پوینت\n\nهر دوستی که با لینک تو بیاد و اولین هاپش رو بزنه = +" . nf($rewardS) . ' برای تو!'),
            kb([[btn('برگشت 🔙', 'ref_back', 'primary')]]));
        return;
    }
    if ($data === 'ref_back') {
        answerCb($cbId);
        [$text, $keyboard] = referralPanelData($uid);
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }
    if ($data === 'ref_admin_toggle') {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        setSetting('referral_enabled', referralEnabled() ? '0' : '1');
        answerCb($cbId, '✅ وضعیت تغییر کرد!');
        [$text, $keyboard] = referralAdminPanelData();
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }
    if ($data === 'ref_admin_set_sender' || $data === 'ref_admin_set_joiner') {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        $field = $data === 'ref_admin_set_sender' ? 'sender' : 'joiner';
        setState($uid, ['flow' => 'ref_admin', 'field' => $field]);
        answerCb($cbId);
        editMsg($chatId, $mid, "✏️ مقدار جدید جایزه " . ($field === 'sender' ? 'دعوت‌کننده' : 'دعوت‌شده') . " رو بنویس:\n\n"
            . quote('برای لغو بنویس: لغو'), null);
        return;
    }

    if (str_starts_with($data, 'mkt_')) {
        handleMarketCallback($cbq, $data, $uid, $cbId, $chatId, $mid, $message);
        return;
    }
    if (str_starts_with($data, 'lot_')) {
        handleLotteryCallback($cbq, $data, $uid, $cbId, $chatId, $mid);
        return;
    }
    if (str_starts_with($data, 'mayor_') || str_starts_with($data, 'melec_') || str_starts_with($data, 'mproj_')) {
        handleMayorCallback($cbq, $data, $uid, $cbId, $chatId, $mid, $message);
        return;
    }
    if (str_starts_with($data, 'lead_')) {
        handleLeaderCallback($cbq, $data, $uid, $cbId, $chatId, $mid, $message);
        return;
    }
    if (str_starts_with($data, 'adm_') || str_starts_with($data, 'confirm_delete_user_')) {
        handleAdminCallback($cbq, $data, $uid, $cbId, $chatId, $mid, $message);
        return;
    }

    answerCb($cbId);
}

function referralPanelData(int $uid): array
{
    $link = 'https://t.me/' . BOT_USERNAME . '?start=ref_' . $uid;
    $count = referralCount($uid);
    $rs = referralRewardSender();
    $rj = referralRewardJoiner();
    $text = "🎁 <b>دعوت دوستان</b>\n\n"
        . "لینک اختصاصی تو:\n<code>" . h($link) . "</code>\n\n"
        . quote(
            "👥 تعداد دعوت‌های موفق : <b>{$count} نفر</b>\n\n"
            . "💰 جوایز:\n"
            . "دعوت‌کننده (تو) : <b>" . nf($rs) . " هاپ پوینت</b>\n"
            . "دعوت‌شده (دوستت) : <b>" . nf($rj) . ' هاپ پوینت</b>'
        )
        . "\n⚡️ جایزه بعد از اولین هاپ دوستت واریز میشه!";
    $keyboard = kb([
        [urlBtn('اشتراک‌گذاری لینک 📤', 'https://t.me/share/url?url=' . rawurlencode($link) . '&text=' . rawurlencode('بیا هاپ داگ بازی کن!'), 'success')],
        [btn('تعداد دعوت‌هام 👥', 'ref_mystats', 'primary')],
    ]);
    return [$text, $keyboard];
}

function referralAdminPanelData(): array
{
    $enabled = referralEnabled();
    $total = (int) val('SELECT COUNT(*) FROM referrals WHERE rewarded=1', [], 0);
    $text = "🎁 <b>پنل مدیریت دعوت دوستان</b>\n\n"
        . quote(
            "وضعیت : " . ($enabled ? '🟢 فعال' : '🔴 غیرفعال') . "\n"
            . "💰 جایزه دعوت‌کننده : " . nf(referralRewardSender()) . "\n"
            . "💰 جایزه دعوت‌شده : " . nf(referralRewardJoiner()) . "\n"
            . "👥 کل دعوت‌های موفق : {$total}"
        )
        . "\nیه گزینه انتخاب کن:";
    $keyboard = kb([
        [btn($enabled ? 'غیرفعال کردن دعوت' : 'فعال کردن دعوت', 'ref_admin_toggle', $enabled ? 'danger' : 'success')],
        [btn('تغییر جایزه دعوت‌کننده ✏️', 'ref_admin_set_sender', 'primary')],
        [btn('تغییر جایزه دعوت‌شده ✏️', 'ref_admin_set_joiner', 'primary')],
    ]);
    return [$text, $keyboard];
}

// ═══════════════════════════════════════════════════════════════════════════
//  🛒  کال‌بک‌های مارکت
// ═══════════════════════════════════════════════════════════════════════════

function handleMarketCallback(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid, array $message): void
{
    $from = $cbq['from'];

    if ($data === 'mkt_admin_toggle') {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین اصلی!', true); return; }
        setMarketOpen(!marketOpen());
        answerCb($cbId, '✅ وضعیت مارکت تغییر کرد!');
        [$text, $keyboard] = marketAdminPanelData();
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }
    if ($data === 'mkt_admin_back') {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        answerCb($cbId);
        [$text, $keyboard] = marketAdminPanelData();
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }
    if ($data === 'mkt_admin_pending') {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        answerCb($cbId);
        $pendings = pendingListings();
        if ($pendings === []) {
            editMsg($chatId, $mid, '✅ هیچ آگهی در انتظاری نیست!', kb([[btn('برگشت 🔙', 'mkt_admin_back', 'primary')]]));
            return;
        }
        $rows = [];
        foreach ($pendings as $l) {
            $rows[] = [btn('👁 ' . mb_substr($l['title'], 0, 25) . ' — ' . mb_substr($l['seller_name'], 0, 15), 'mkt_admin_review_' . $l['listing_id'], 'primary')];
        }
        $rows[] = [btn('برگشت 🔙', 'mkt_admin_back', 'primary')];
        editMsg($chatId, $mid, '⏳ <b>آگهی‌های در انتظار تأیید (' . count($pendings) . ' عدد):</b>', kb($rows));
        return;
    }
    if (str_starts_with($data, 'mkt_admin_review_')) {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        $lid = substr($data, 17);
        $l = getListing($lid);
        if (!$l) { answerCb($cbId, '❌ آگهی پیدا نشد!', true); return; }
        answerCb($cbId);
        editMsg($chatId, $mid,
            "📋 <b>بررسی آگهی</b>\n\n"
            . quote(
                "🏷 نام : " . h($l['title']) . "\n"
                . "👤 فروشنده : " . h($l['seller_name']) . " (<code>" . (int) $l['seller_id'] . "</code>)\n"
                . "📝 توضیح : " . h($l['description']) . "\n"
                . "💰 قیمت : " . nf($l['price']) . " هاپ پوینت\n"
                . "👥 حداکثر خریدار : " . (int) $l['max_buyers'] . "\n"
                . "📦 محتوا : " . h($l['content']) . "\n"
                . "📅 ثبت : " . h(substr((string) $l['created_at'], 0, 16)), true),
            kb([
                [btn('تأیید ✅', 'mkt_admin_approve_' . $lid, 'success'), btn('رد ❌', 'mkt_admin_reject_' . $lid, 'danger')],
                [btn('برگشت 🔙', 'mkt_admin_pending', 'primary')],
            ]));
        return;
    }
    if (str_starts_with($data, 'mkt_admin_approve_') || str_starts_with($data, 'mkt_admin_reject_')) {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        $approve = str_starts_with($data, 'mkt_admin_approve_');
        $lid = substr($data, $approve ? 18 : 17);
        $l = getListing($lid);
        if (!$l) { answerCb($cbId, '❌ آگهی پیدا نشد!', true); return; }
        q('UPDATE user_market SET status=? WHERE listing_id=?', [$approve ? 'active' : 'rejected', $lid]);
        answerCb($cbId, $approve ? '✅ تأیید شد!' : '❌ رد شد!');
        editMsg($chatId, $mid,
            $approve
                ? '✅ آگهی <b>' . h($l['title']) . '</b> تأیید و در مارکت منتشر شد!'
                : '❌ آگهی <b>' . h($l['title']) . '</b> رد شد.',
            kb([[btn('برگشت 🔙', 'mkt_admin_pending', 'primary')]]));
        sendPrivate((int) $l['seller_id'], $approve
            ? "✅ <b>آگهیت تأیید شد!</b>\n\n" . quote('🏷 «' . h($l['title']) . '» الان توی مارکت فعاله و قابل خریده.')
            : "❌ <b>آگهیت رد شد!</b>\n\n" . quote('🏷 «' . h($l['title']) . '» توسط ادمین تأیید نشد.'));
        return;
    }
    if ($data === 'mkt_admin_active') {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        answerCb($cbId);
        $actives = activeListings();
        if ($actives === []) {
            editMsg($chatId, $mid, '📋 هیچ آگهی فعالی وجود نداره!', kb([[btn('برگشت 🔙', 'mkt_admin_back', 'primary')]]));
            return;
        }
        $rows = [];
        foreach ($actives as $l) {
            $remaining = (int) $l['max_buyers'] - (int) $l['buyer_count'];
            $rows[] = [btn('🏷 ' . mb_substr($l['title'], 0, 22) . " | {$remaining} باقی", 'mkt_admin_deact_' . $l['listing_id'], 'danger')];
        }
        $rows[] = [btn('برگشت 🔙', 'mkt_admin_back', 'primary')];
        editMsg($chatId, $mid, '📋 <b>آگهی‌های فعال</b> (برای لغو انتخاب کن):', kb($rows));
        return;
    }
    if (str_starts_with($data, 'mkt_admin_deact_')) {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین!', true); return; }
        $lid = substr($data, 16);
        $l = getListing($lid);
        if (!$l) { answerCb($cbId, '❌ آگهی پیدا نشد!', true); return; }
        q("UPDATE user_market SET status='cancelled' WHERE listing_id=?", [$lid]);
        answerCb($cbId, '❌ آگهی لغو شد!');
        editMsg($chatId, $mid, '❌ آگهی <b>' . h($l['title']) . '</b> لغو شد.',
            kb([[btn('برگشت 🔙', 'mkt_admin_active', 'primary')]]));
        sendPrivate((int) $l['seller_id'], '⚠️ آگهیت «' . h($l['title']) . '» توسط ادمین لغو شد.');
        return;
    }

    if ($data === 'mkt_new') {
        if (!marketOpen()) { answerCb($cbId, '🔴 مارکت الان تعطیله!', true); return; }
        setState($uid, ['flow' => 'market_new', 'step' => 'title']);
        answerCb($cbId);
        editMsg($chatId, $mid, "🏷 <b>اسم محصول:</b>\n" . quote('یه اسم کوتاه و واضح برای آگهیت بنویس.  —  برای لغو بنویس: لغو'), null);
        return;
    }
    if ($data === 'mkt_submit_confirm') {
        $state = getState($uid);
        if (($state['flow'] ?? '') !== 'market_new' || ($state['step'] ?? '') !== 'confirm') {
            answerCb($cbId, '❌ اطلاعات پیدا نشد!', true);
            return;
        }
        $lid = newId();
        q('INSERT INTO user_market (listing_id, seller_id, seller_name, title, description, content, price, max_buyers, status, created_at)
           VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$lid, $uid, displayName($from), $state['title'], $state['description'], $state['content'],
             (int) $state['price'], (int) $state['max_buyers'], 'pending', nowIso()]);
        clearState($uid);
        answerCb($cbId, '✅ آگهی ثبت شد!');
        editMsg($chatId, $mid, "✅ <b>آگهیت ثبت شد!</b>\n\n"
            . quote('«' . h($state['title']) . "» برای تأیید ادمین فرستاده شد.\nبعد از تأیید توی مارکت منتشر میشه و بهت خبر میدم."), null);
        foreach (ADMIN_IDS as $adminId) {
            sendPrivate($adminId, "🔔 <b>آگهی جدید در انتظار تأیید</b>\n\n"
                . quote("🏷 " . h($state['title']) . "\n👤 " . h(displayName($from)) . "\n💰 " . nf($state['price']) . ' هاپ پوینت')
                . "\nبرای بررسی بنویس: <b>مارکت ادمین</b>");
        }
        return;
    }
    if ($data === 'mkt_submit_cancel') {
        clearState($uid);
        answerCb($cbId, '❌ لغو شد');
        editMsg($chatId, $mid, '❌ ثبت آگهی لغو شد.', null);
        return;
    }
    if ($data === 'mkt_my_listings') {
        answerCb($cbId);
        $listings = sellerListings($uid);
        if ($listings === []) {
            editMsg($chatId, $mid, '📋 هنوز هیچ آگهی‌ای نداری!', kb([[btn('ثبت آگهی جدید ➕', 'mkt_new', 'success')]]));
            return;
        }
        $emoji = ['active' => '✅', 'pending' => '⏳', 'cancelled' => '❌', 'rejected' => '🚫', 'done' => '🏁'];
        $rows = [];
        foreach ($listings as $l) {
            $rows[] = [btn(($emoji[$l['status']] ?? '❓') . ' ' . mb_substr($l['title'], 0, 20) . ' | ' . (int) $l['buyer_count'] . '/' . (int) $l['max_buyers'],
                'mkt_my_detail_' . $l['listing_id'], 'primary')];
        }
        $rows[] = [btn('ثبت آگهی جدید ➕', 'mkt_new', 'success')];
        editMsg($chatId, $mid, '📋 <b>آگهی‌های من:</b>', kb($rows));
        return;
    }
    if (str_starts_with($data, 'mkt_my_detail_')) {
        $lid = substr($data, 14);
        $l = getListing($lid);
        if (!$l || (int) $l['seller_id'] !== $uid) { answerCb($cbId, '❌ آگهی پیدا نشد!', true); return; }
        answerCb($cbId);
        $statusText = ['active' => '✅ فعال', 'pending' => '⏳ در انتظار تأیید', 'cancelled' => '❌ لغو شده', 'rejected' => '🚫 رد شده', 'done' => '🏁 تکمیل شده'];
        $rows = [];
        if ($l['status'] === 'active') {
            $rows[] = [btn('لغو آگهی ❌', 'mkt_my_cancel_' . $lid, 'danger')];
        }
        $rows[] = [btn('برگشت 🔙', 'mkt_my_listings', 'primary')];
        editMsg($chatId, $mid,
            "🏷 <b>" . h($l['title']) . "</b>\n\n"
            . quote(
                "📊 وضعیت : " . ($statusText[$l['status']] ?? '؟') . "\n"
                . "💰 قیمت : " . nf($l['price']) . " هاپ پوینت\n"
                . "👥 خریداران : " . (int) $l['buyer_count'] . ' از ' . (int) $l['max_buyers'] . " نفر\n"
                . "📝 توضیح : " . h($l['description']) . "\n"
                . "📅 ثبت : " . h(substr((string) $l['created_at'], 0, 16))),
            kb($rows));
        return;
    }
    if (str_starts_with($data, 'mkt_my_cancel_')) {
        $lid = substr($data, 14);
        $l = getListing($lid);
        if (!$l || (int) $l['seller_id'] !== $uid) { answerCb($cbId, '❌ مجاز نیستی!', true); return; }
        q("UPDATE user_market SET status='cancelled' WHERE listing_id=?", [$lid]);
        answerCb($cbId, '❌ آگهی لغو شد!');
        editMsg($chatId, $mid, '❌ آگهی <b>' . h($l['title']) . '</b> لغو شد.',
            kb([[btn('برگشت 🔙', 'mkt_my_listings', 'primary')]]));
        return;
    }
    if (str_starts_with($data, 'mkt_buyconf_')) {
        $lid = substr($data, 12);
        $l = getListing($lid);
        if (!$l || $l['status'] !== 'active') { answerCb($cbId, '❌ آگهی دیگه فعال نیست!', true); return; }
        if ((int) $l['seller_id'] === $uid) { answerCb($cbId, '❌ نمی‌تونی محصول خودت رو بخری!', true); return; }
        if (val('SELECT 1 FROM user_market_buyers WHERE listing_id=? AND buyer_id=?', [$lid, $uid]) !== null) {
            answerCb($cbId, '❌ قبلاً خریدی!', true);
            return;
        }
        $price = (int) $l['price'];
        if (userPoints($uid) < $price) { answerCb($cbId, '❌ پوینت کافی نداری!', true); return; }

        $newCount = (int) $l['buyer_count'] + 1;
        $ok = transact(static function () use ($lid, $uid, $l, $price, $newCount) {
            $st = q('INSERT INTO user_market_buyers (listing_id, buyer_id, bought_at) VALUES (?,?,?)
                     ON CONFLICT(listing_id, buyer_id) DO NOTHING', [$lid, $uid, nowIso()]);
            if ($st->rowCount() === 0) {
                return false;
            }
            deductPoints($uid, $price);
            addPoints((int) $l['seller_id'], $price);
            if ($newCount >= (int) $l['max_buyers']) {
                q("UPDATE user_market SET buyer_count=?, status='done' WHERE listing_id=?", [$newCount, $lid]);
            } else {
                q('UPDATE user_market SET buyer_count=? WHERE listing_id=?', [$newCount, $lid]);
            }
            return true;
        });
        if (!$ok) { answerCb($cbId, '❌ قبلاً خریدی!', true); return; }

        answerCb($cbId, '✅ خرید موفق!');
        editMsg($chatId, $mid, "✅ <b>خرید موفق!</b>\n\n"
            . quote('💰 ' . nf($price) . " هاپ پوینت کسر شد.\nمحتوای آگهی توی پیوی برات فرستاده شد 📩"), null);
        sendPrivate($uid, "📦 <b>محتوای خریدت:</b>\n\n🏷 " . h($l['title']) . "\n\n" . quote(h($l['content']), true));
        sendPrivate((int) $l['seller_id'], "🛍 <b>یه نفر آگهیت رو خرید!</b>\n\n"
            . quote("🏷 " . h($l['title']) . "\n💰 +" . nf($price) . " هاپ پوینت\n👥 خریداران : {$newCount}/" . (int) $l['max_buyers']));
        return;
    }
    if (str_starts_with($data, 'mkt_buy_')) {
        if (!marketOpen()) { answerCb($cbId, '🔴 مارکت الان تعطیله!', true); return; }
        $lid = substr($data, 8);
        $l = getListing($lid);
        if (!$l || $l['status'] !== 'active') { answerCb($cbId, '❌ این آگهی دیگه فعال نیست!', true); return; }
        if ((int) $l['seller_id'] === $uid) { answerCb($cbId, '❌ نمی‌تونی محصول خودت رو بخری!', true); return; }
        if (val('SELECT 1 FROM user_market_buyers WHERE listing_id=? AND buyer_id=?', [$lid, $uid]) !== null) {
            answerCb($cbId, '❌ قبلاً این رو خریدی!', true);
            return;
        }
        if (userPoints($uid) < (int) $l['price']) {
            answerCb($cbId, '❌ پوینت کافی نداری! لازم: ' . nf($l['price']), true);
            return;
        }
        answerCb($cbId);
        sendMsg($chatId, "🛍 <b>تأیید خرید</b>\n\n"
            . quote("🏷 " . h($l['title']) . "\n💰 قیمت : " . nf($l['price']) . " هاپ پوینت\n📝 " . h($l['description']))
            . "\nمطمئنی؟",
            kb([[
                btn('بله، خریدم! ✅', 'mkt_buyconf_' . $lid, 'success'),
                btn('انصراف', 'cancel', 'danger'),
            ]]), $message);
        return;
    }

    answerCb($cbId);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🎰  کال‌بک‌های قرعه‌کشی
// ═══════════════════════════════════════════════════════════════════════════

function handleLotteryCallback(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid): void
{
    $from = $cbq['from'];

    // پیوستن — برای همه آزاد است
    if (str_starts_with($data, 'lot_join_')) {
        $lotId = substr($data, 9);
        $lot = getLottery($lotId);
        if (!$lot || $lot['state'] !== 'open') {
            answerCb($cbId, '❌ این قرعه‌کشی دیگه باز نیست!', true);
            return;
        }
        if (val('SELECT 1 FROM lottery_entries WHERE lottery_id=? AND user_id=?', [$lotId, $uid]) !== null) {
            answerCb($cbId, '✅ قبلاً ثبت‌نام کردی!', true);
            return;
        }
        q('INSERT INTO lottery_entries (lottery_id, user_id, username, first_name, joined_at) VALUES (?,?,?,?,?)
           ON CONFLICT(lottery_id, user_id) DO NOTHING',
            [$lotId, $uid, (string) ($from['username'] ?? ''), displayName($from), nowIso()]);

        $entries = lotteryEntries($lotId);
        answerCb($cbId, '✅ ثبت‌نام شدی! (' . count($entries) . ' نفر تا الان)');
        editMsg($chatId, $mid, lotteryAnnounceText($lot, $entries),
            kb([[btn('شرکت در قرعه‌کشی (' . count($entries) . ' نفر) ✋', "lot_join_{$lotId}", 'success')]]));
        return;
    }

    if (!isAdmin($uid)) {
        answerCb($cbId, '❌ فقط ادمین!', true);
        return;
    }

    if ($data === 'lot_panel') {
        answerCb($cbId);
        [$text, $keyboard] = lotteryAdminPanelData();
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }
    if ($data === 'lot_new') {
        setState($uid, ['flow' => 'lottery_new', 'step' => 'title']);
        answerCb($cbId);
        editMsg($chatId, $mid, "🎰 <b>ساخت قرعه‌کشی جدید</b>\n\n" . quote('📝 اسم قرعه‌کشی رو بنویس:  —  برای لغو بنویس: لغو'),
            kb([[btn('انصراف', 'lot_panel', 'danger')]]));
        return;
    }
    if (str_starts_with($data, 'lot_list_')) {
        answerCb($cbId);
        $page = max(0, cbInt($data, 2));
        $lots = allLotteries();
        $perPage = 5;
        $total = count($lots);
        $pageLots = array_slice($lots, $page * $perPage, $perPage);
        $stateEmoji = ['open' => '🟢', 'done' => '✅', 'cancelled' => '❌'];
        $body = '';
        $rows = [];
        foreach ($pageLots as $l) {
            $e = $stateEmoji[$l['state']] ?? '❓';
            $n = count(lotteryEntries($l['lottery_id']));
            $body .= "{$e} <b>" . h($l['title']) . "</b> — {$n} نفر — جایزه: " . nf($l['prize']) . "\n";
            $rows[] = [btn($e . ' ' . mb_substr($l['title'], 0, 25), 'lot_view_' . $l['lottery_id'], 'primary')];
        }
        $nav = [];
        if ($page > 0) {
            $nav[] = btn('قبلی ◀️', 'lot_list_' . ($page - 1), 'primary');
        }
        if (($page + 1) * $perPage < $total) {
            $nav[] = btn('بعدی ▶️', 'lot_list_' . ($page + 1), 'primary');
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = [btn('پنل 🔙', 'lot_panel', 'primary')];
        editMsg($chatId, $mid, "📋 <b>همه قرعه‌کشی‌ها</b> ({$total} تا)\n\n"
            . ($body !== '' ? quote(trim($body), true) : 'هیچ قرعه‌کشی‌ای نیست!'), kb($rows));
        return;
    }
    if ($data === 'lot_open') {
        answerCb($cbId);
        $lots = array_filter(allLotteries(), static fn($l) => $l['state'] === 'open');
        $rows = [];
        foreach ($lots as $l) {
            $n = count(lotteryEntries($l['lottery_id']));
            $rows[] = [btn('🟢 ' . mb_substr($l['title'], 0, 22) . " — {$n} نفر", 'lot_view_' . $l['lottery_id'], 'success')];
        }
        $rows[] = [btn('پنل 🔙', 'lot_panel', 'primary')];
        editMsg($chatId, $mid, '🟢 <b>قرعه‌کشی‌های باز</b> (' . count($lots) . ' تا)'
            . ($lots === [] ? "\n\nهیچ قرعه‌کشی بازی نیست!" : ''), kb($rows));
        return;
    }
    if (str_starts_with($data, 'lot_view_')) {
        answerCb($cbId);
        $lotId = substr($data, 9);
        $lot = getLottery($lotId);
        if (!$lot) { editMsg($chatId, $mid, '❌ قرعه‌کشی پیدا نشد!', null); return; }
        $entries = lotteryEntries($lotId);
        $stateMap = ['open' => '🟢 باز', 'done' => '✅ انجام‌شده', 'cancelled' => '❌ لغو'];
        $rows = [];
        if ($lot['state'] === 'open') {
            $rows[] = [
                btn('قرعه‌کشی همین الان! 🎲', 'lot_draw_' . $lotId, 'success'),
                btn('لغو', 'lot_cancel_' . $lotId, 'danger'),
            ];
        }
        $rows[] = [btn('برگشت 🔙', 'lot_list_0', 'primary')];
        editMsg($chatId, $mid,
            "🎰 <b>" . h($lot['title']) . "</b>\n\n"
            . quote(
                "📌 وضعیت : " . ($stateMap[$lot['state']] ?? $lot['state']) . "\n"
                . "🎁 جایزه کل : " . nf($lot['prize']) . " هاپ پوینت\n"
                . "🏆 تعداد برنده : " . (int) $lot['winner_count'] . " نفر\n"
                . "👥 شرکت‌کنندگان : " . count($entries) . ' نفر')
            . "\n" . quote(lotteryParticipantText($entries), true), kb($rows));
        return;
    }
    if (str_starts_with($data, 'lot_cancelconf_')) {
        $lotId = substr($data, 15);
        q("UPDATE lotteries SET state='cancelled' WHERE lottery_id=?", [$lotId]);
        answerCb($cbId, '❌ قرعه‌کشی لغو شد!');
        editMsg($chatId, $mid, '❌ <b>قرعه‌کشی لغو شد.</b>', kb([[btn('پنل 🔙', 'lot_panel', 'primary')]]));
        return;
    }
    if (str_starts_with($data, 'lot_cancel_')) {
        $lotId = substr($data, 11);
        $lot = getLottery($lotId);
        if (!$lot || $lot['state'] !== 'open') { answerCb($cbId, '❌ نمیشه لغو کرد!', true); return; }
        answerCb($cbId);
        editMsg($chatId, $mid, '⚠️ مطمئنی می‌خوای قرعه‌کشی <b>' . h($lot['title']) . '</b> رو لغو کنی؟',
            kb([[
                btn('بله، لغو کن ✅', 'lot_cancelconf_' . $lotId, 'danger'),
                btn('نه', 'lot_view_' . $lotId, 'primary'),
            ]]));
        return;
    }
    if (str_starts_with($data, 'lot_draw_')) {
        $lotId = substr($data, 9);
        $lot = getLottery($lotId);
        if (!$lot || $lot['state'] !== 'open') { answerCb($cbId, '❌ قرعه‌کشی باز نیست!', true); return; }
        if (lotteryEntries($lotId) === []) { answerCb($cbId, '❌ هیچ شرکت‌کننده‌ای نیست!', true); return; }
        answerCb($cbId, '🎲 در حال قرعه‌کشی...');
        editMsg($chatId, $mid, '⏳ <b>در حال قرعه‌کشی...</b>', null);
        doLotteryDraw($lotId);
        editMsg($chatId, $mid, '✅ <b>قرعه‌کشی انجام شد! نتایج به همه گروه‌ها ارسال شد.</b>',
            kb([[btn('پنل 🔙', 'lot_panel', 'primary')]]));
        return;
    }

    answerCb($cbId);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🏛  کال‌بک‌های شهرداری
// ═══════════════════════════════════════════════════════════════════════════

function handleMayorCallback(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid, array $message): void
{
    $from = $cbq['from'];

    // ── انتخابات ──────────────────────────────────────────────────────────
    if (str_starts_with($data, 'melec_start_')) {
        $groupId = (int) substr($data, 12);
        if (getMayor($groupId)) { answerCb($cbId, '❌ این شهر همین الان شهردار داره!', true); return; }
        if (activeMayorElection($groupId)) { answerCb($cbId, '❌ یه انتخابات فعال هست!', true); return; }
        q("INSERT INTO mayor_elections (group_id, status, started_by, started_at) VALUES (?,'candidacy',?,?)",
            [$groupId, $uid, nowIso()]);
        answerCb($cbId, '🗳 انتخابات شروع شد!');
        editMayorPanel($cbq, $groupId);
        sendMsg($chatId, "🗳 <b>انتخابات شهرداری شروع شد!</b>\n\n"
            . quote('هر کسی می‌تونه کاندیدا بشه. بنویس <b>شهرداری</b> و دکمه ثبت‌نام رو بزن.'));
        return;
    }

    if (str_starts_with($data, 'melec_join_')) {
        $electionId = (int) substr($data, 11);
        $election = one('SELECT * FROM mayor_elections WHERE id=?', [$electionId]);
        if (!$election || $election['status'] !== 'candidacy') {
            answerCb($cbId, '❌ مهلت ثبت‌نام تموم شده!', true);
            return;
        }
        $u = getUser($uid);
        if (!$u || (int) $u['level'] < 3) {
            answerCb($cbId, '🔒 برای کاندیداتوری باید حداقل سطح ۳ باشی!', true);
            return;
        }
        if (val('SELECT 1 FROM mayor_candidates WHERE election_id=? AND user_id=?', [$electionId, $uid]) !== null) {
            answerCb($cbId, '✅ قبلاً ثبت‌نام کردی!', true);
            return;
        }
        q('INSERT INTO mayor_candidates (election_id, user_id, username, first_name, joined_at) VALUES (?,?,?,?,?)
           ON CONFLICT(election_id, user_id) DO NOTHING',
            [$electionId, $uid, (string) ($from['username'] ?? ''), displayName($from), nowIso()]);
        $n = (int) val('SELECT COUNT(*) FROM mayor_candidates WHERE election_id=?', [$electionId], 0);
        answerCb($cbId, "✅ کاندیدا شدی! ({$n} کاندیدا)");
        editMayorPanel($cbq, (int) $election['group_id']);
        return;
    }

    if (str_starts_with($data, 'melec_vote_')) {
        $electionId = (int) substr($data, 11);
        $election = one('SELECT * FROM mayor_elections WHERE id=?', [$electionId]);
        if (!$election || $election['status'] !== 'candidacy') {
            answerCb($cbId, '❌ وضعیت انتخابات نامعتبره!', true);
            return;
        }
        $n = (int) val('SELECT COUNT(*) FROM mayor_candidates WHERE election_id=?', [$electionId], 0);
        if ($n < 1) { answerCb($cbId, '❌ هیچ کاندیدایی ثبت‌نام نکرده!', true); return; }
        q("UPDATE mayor_elections SET status='voting' WHERE id=?", [$electionId]);
        answerCb($cbId, '🗳 رأی‌گیری شروع شد!');
        editMayorPanel($cbq, (int) $election['group_id']);
        sendMsg($chatId, "🗳 <b>رأی‌گیری شهرداری شروع شد!</b>\n\n" . quote("👥 {$n} کاندیدا\nبنویس <b>شهرداری</b> و رأی بده."));
        return;
    }

    if (str_starts_with($data, 'melec_list_')) {
        $electionId = (int) substr($data, 11);
        $election = one('SELECT * FROM mayor_elections WHERE id=?', [$electionId]);
        if (!$election || $election['status'] !== 'voting') {
            answerCb($cbId, '❌ رأی‌گیری فعال نیست!', true);
            return;
        }
        $cands = all('SELECT * FROM mayor_candidates WHERE election_id=?', [$electionId]);
        $counts = [];
        foreach (all('SELECT candidate_id, COUNT(*) AS n FROM mayor_election_votes WHERE election_id=? GROUP BY candidate_id', [$electionId]) as $r) {
            $counts[(int) $r['candidate_id']] = (int) $r['n'];
        }
        $rows = [];
        $body = '';
        foreach ($cands as $c) {
            $v = $counts[(int) $c['user_id']] ?? 0;
            $body .= "👤 " . h($c['first_name']) . " — {$v} رأی\n";
            $rows[] = [btn('رأی به ' . mb_substr($c['first_name'], 0, 20) . " ({$v})", 'melec_cast_' . $electionId . '_' . (int) $c['user_id'], 'success')];
        }
        $rows[] = [btn('پایان و شمارش 🏁', 'melec_finish_' . $electionId, 'warning')];
        answerCb($cbId);
        editMsg($chatId, $mid, "🗳 <b>کاندیداهای شهرداری</b>\n\n" . quote(trim($body) ?: 'کاندیدایی نیست'), kb($rows));
        return;
    }

    if (preg_match('/^melec_cast_(\d+)_(\d+)$/', $data, $m)) {
        $electionId = (int) $m[1];
        $candidateId = (int) $m[2];
        $election = one('SELECT * FROM mayor_elections WHERE id=?', [$electionId]);
        if (!$election || $election['status'] !== 'voting') {
            answerCb($cbId, '❌ رأی‌گیری فعال نیست!', true);
            return;
        }
        if (val('SELECT 1 FROM mayor_election_votes WHERE election_id=? AND voter_id=?', [$electionId, $uid]) !== null) {
            answerCb($cbId, '❌ قبلاً رأی دادی!', true);
            return;
        }
        q('INSERT INTO mayor_election_votes (election_id, voter_id, candidate_id, voted_at) VALUES (?,?,?,?)
           ON CONFLICT(election_id, voter_id) DO NOTHING', [$electionId, $uid, $candidateId, nowIso()]);
        answerCb($cbId, '✅ رأیت ثبت شد!');
        handleMayorCallback($cbq, 'melec_list_' . $electionId, $uid, $cbId, $chatId, $mid, $message);
        return;
    }

    if (str_starts_with($data, 'melec_finish_')) {
        $electionId = (int) substr($data, 13);
        $election = one('SELECT * FROM mayor_elections WHERE id=?', [$electionId]);
        if (!$election) { answerCb($cbId, '❌ انتخابات پیدا نشد!', true); return; }
        $groupId = (int) $election['group_id'];
        $res = finishMayorElection($electionId, $groupId);
        if (!$res) {
            answerCb($cbId, '❌ کاندیدایی نبود — انتخابات بسته شد.', true);
            editMayorPanel($cbq, $groupId);
            return;
        }
        $body = '';
        foreach ($res['candidates'] as $c) {
            $v = $res['counts'][(int) $c['user_id']] ?? 0;
            $body .= "👤 " . h($c['first_name']) . " — {$v} رأی\n";
        }
        answerCb($cbId, '🏁 شمارش انجام شد!');
        editMsg($chatId, $mid,
            "🏛 <b>" . h($res['winner']['first_name']) . " شهردار شد!</b>\n\n"
            . quote("🏆 آرا : " . $res['votes'] . "\n⏳ مدت دوره : " . MAYOR_TERM_DAYS . " روز\n\n" . trim($body), true)
            . "\n✨ تبریک! حالا می‌تونی فرمان صادر کنی و پروژه بسازی.", null);
        return;
    }

    // ── فرمان‌ها ──────────────────────────────────────────────────────────
    if (str_starts_with($data, 'mayor_decrees_')) {
        $groupId = (int) substr($data, 14);
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        if (activeDecree($groupId)) { answerCb($cbId, '⚠️ یه فرمان فعال داری! صبر کن تموم بشه.', true); return; }
        $rows = [];
        $body = '';
        foreach (MAYOR_DECREES_DEF as $key => $def) {
            $body .= h($def['name']) . " — " . h($def['desc']) . "\n";
            $rows[] = [btn($def['name'], "mayor_decree_{$groupId}_{$key}", 'success')];
        }
        $rows[] = [btn('برگشت 🔙', "mayor_back_{$groupId}", 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "📜 <b>صدور فرمان شهرداری</b>\n\n" . quote(trim($body), true)
            . "\n⏳ مدت اثر : " . fmtDur(MAYOR_DECREE_DURATION), kb($rows));
        return;
    }

    if (preg_match('/^mayor_decree_(-?\d+)_(\w+)$/', $data, $m)) {
        $groupId = (int) $m[1];
        $key = $m[2];
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        $def = MAYOR_DECREES_DEF[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ فرمان نامعتبر!', true); return; }
        if (activeDecree($groupId)) { answerCb($cbId, '⚠️ یه فرمان فعال داری!', true); return; }

        // فرمان کاهش مالیات: توزیع ۵٪ خزانه بین کاربران
        $extra = '';
        if ($def['effect'] === 'tax_cut') {
            $grp = getGroup($groupId);
            $pool = (int) ((float) $grp['treasury'] * (float) $def['value']);
            $users = all('SELECT user_id FROM users WHERE total_hops > 0');
            if ($pool > 0 && $users !== []) {
                $each = intdiv($pool, count($users));
                if ($each > 0) {
                    transact(static function () use ($users, $each, $groupId, $pool) {
                        foreach ($users as $usr) {
                            addPoints((int) $usr['user_id'], $each);
                        }
                        q('UPDATE groups SET treasury = MAX(0, treasury - ?) WHERE group_id=?', [$pool, $groupId]);
                    });
                    $extra = "\n💸 " . nf($each) . ' پوینت به هر کاربر پرداخت شد!';
                }
            }
        }

        q('INSERT INTO mayor_decrees (group_id, user_id, decree_type, decree_name, issued_at, expires_at) VALUES (?,?,?,?,?,?)',
            [$groupId, $uid, $key, $def['name'], nowIso(), isoPlus(MAYOR_DECREE_DURATION)]);
        changeMayorPopularity($groupId, +3);
        logMayorAction($groupId, $uid, 'decree', 'صدور فرمان ' . $def['name']);
        fulfillMayorPledge($groupId, $uid, $key);

        answerCb($cbId, '📜 فرمان صادر شد!');
        editMsg($chatId, $mid, "📜 <b>فرمان صادر شد!</b>\n\n"
            . quote(h($def['name']) . "\n" . h($def['desc']) . "\n⏳ مدت : " . fmtDur(MAYOR_DECREE_DURATION) . $extra), null);
        sendMsg($groupId, "📣 <b>فرمان جدید شهرداری!</b>\n\n"
            . quote("🏛 شهردار : " . h($mayor['first_name']) . "\n📜 " . h($def['name']) . "\n✨ " . h($def['desc'])));
        return;
    }

    if (str_starts_with($data, 'mayor_back_')) {
        $groupId = (int) substr($data, 11);
        answerCb($cbId);
        editMayorPanel($cbq, $groupId);
        return;
    }

    if (str_starts_with($data, 'mayor_report_')) {
        $groupId = (int) substr($data, 13);
        $mayor = getMayor($groupId);
        if (!$mayor) { answerCb($cbId, '❌ شهرداری نداره!', true); return; }
        $logs = all('SELECT * FROM mayor_log WHERE group_id=? AND user_id=? ORDER BY id DESC LIMIT 10',
            [$groupId, (int) $mayor['user_id']]);
        $body = '';
        foreach ($logs as $l) {
            $body .= '• ' . h($l['action_desc']) . ' — ' . h(substr((string) $l['created_at'], 0, 16)) . "\n";
        }
        answerCb($cbId);
        editMsg($chatId, $mid, "📊 <b>گزارش عملکرد شهردار</b>\n\n"
            . quote("🏛 " . h($mayor['first_name']) . "\n📈 محبوبیت : " . (int) $mayor['popularity'] . "٪\n⏳ " . fmtDur(secsLeft($mayor['term_end'])) . ' تا پایان دوره')
            . "\n📋 آخرین اقدامات:\n" . ($body !== '' ? quote(trim($body), true) : quote('هنوز اقدامی ثبت نشده')),
            kb([[btn('برگشت 🔙', "mayor_back_{$groupId}", 'primary')]]));
        return;
    }

    if (str_starts_with($data, 'mayor_resign_')) {
        $groupId = (int) substr($data, 13);
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        q('DELETE FROM mayor WHERE group_id=?', [$groupId]);
        logMayorAction($groupId, $uid, 'resign', 'استعفا از شهرداری');
        answerCb($cbId, '🚪 استعفا دادی!');
        editMsg($chatId, $mid, "🚪 <b>" . h($mayor['first_name']) . " از شهرداری استعفا داد!</b>\n\n"
            . quote('حالا می‌تونید انتخابات جدید راه بندازید.'), null);
        return;
    }

    // ── اعتراض ────────────────────────────────────────────────────────────
    if (str_starts_with($data, 'mayor_protest_') && !str_starts_with($data, 'mayor_protestview_')) {
        $groupId = (int) substr($data, 14);
        if (!getMayor($groupId)) { answerCb($cbId, '❌ این شهر شهردار نداره!', true); return; }
        if (activeProtest($groupId)) { answerCb($cbId, '⚠️ یه اعتراض فعال هست!', true); return; }
        setState($uid, ['flow' => 'mayor_protest', 'group_id' => $groupId]);
        answerCb($cbId);
        editMsg($chatId, $mid, "✊ <b>ثبت اعتراض به شهردار</b>\n\n" . quote('دلیل اعتراضت رو بنویس  —  برای لغو بنویس: لغو'), null);
        return;
    }

    if (str_starts_with($data, 'mayor_protestview_')) {
        $protestId = (int) substr($data, 18);
        $p = one('SELECT * FROM mayor_protests WHERE id=?', [$protestId]);
        if (!$p || $p['status'] !== 'pending') { answerCb($cbId, '❌ این اعتراض بسته شده!', true); return; }
        $yes = (int) val("SELECT COUNT(*) FROM mayor_protest_votes WHERE protest_id=? AND vote='yes'", [$protestId], 0);
        $no = (int) val("SELECT COUNT(*) FROM mayor_protest_votes WHERE protest_id=? AND vote='no'", [$protestId], 0);
        answerCb($cbId);
        editMsg($chatId, $mid,
            "✊ <b>اعتراض به شهردار</b>\n\n"
            . quote("👤 معترض : " . h($p['first_name']) . "\n📝 دلیل : " . h($p['reason']) . "\n\n✅ موافق : {$yes}  |  ❌ مخالف : {$no}")
            . "\n⚠️ اگر ۵ رأی موافق بیشتر از مخالف باشه، شهردار برکنار میشه!",
            kb([[
                btn("موافق ({$yes}) ✅", 'mayor_pvote_' . $protestId . '_yes', 'success'),
                btn("مخالف ({$no}) ❌", 'mayor_pvote_' . $protestId . '_no', 'danger'),
            ]]));
        return;
    }

    if (preg_match('/^mayor_pvote_(\d+)_(yes|no)$/', $data, $m)) {
        $protestId = (int) $m[1];
        $vote = $m[2];
        $p = one('SELECT * FROM mayor_protests WHERE id=?', [$protestId]);
        if (!$p || $p['status'] !== 'pending') { answerCb($cbId, '❌ این اعتراض بسته شده!', true); return; }
        if (val('SELECT 1 FROM mayor_protest_votes WHERE protest_id=? AND user_id=?', [$protestId, $uid]) !== null) {
            answerCb($cbId, '❌ قبلاً رأی دادی!', true);
            return;
        }
        q('INSERT INTO mayor_protest_votes (protest_id, user_id, vote, voted_at) VALUES (?,?,?,?)
           ON CONFLICT(protest_id, user_id) DO NOTHING', [$protestId, $uid, $vote, nowIso()]);

        $yes = (int) val("SELECT COUNT(*) FROM mayor_protest_votes WHERE protest_id=? AND vote='yes'", [$protestId], 0);
        $no = (int) val("SELECT COUNT(*) FROM mayor_protest_votes WHERE protest_id=? AND vote='no'", [$protestId], 0);
        $groupId = (int) $p['group_id'];

        if ($yes - $no >= 5) {
            $mayor = getMayor($groupId);
            q("UPDATE mayor_protests SET status='accepted' WHERE id=?", [$protestId]);
            q('DELETE FROM mayor WHERE group_id=?', [$groupId]);
            answerCb($cbId, '⚖️ شهردار برکنار شد!');
            editMsg($chatId, $mid, "⚖️ <b>شهردار برکنار شد!</b>\n\n"
                . quote(($mayor ? h($mayor['first_name']) . ' ' : '') . "با {$yes} رأی موافق در برابر {$no} رأی مخالف برکنار شد.")
                . "\n🗳 حالا می‌تونید انتخابات جدید راه بندازید.", null);
            return;
        }

        answerCb($cbId, '✅ رأیت ثبت شد!');
        editMarkup($chatId, $mid, kb([[
            btn("موافق ({$yes}) ✅", 'mayor_pvote_' . $protestId . '_yes', 'success'),
            btn("مخالف ({$no}) ❌", 'mayor_pvote_' . $protestId . '_no', 'danger'),
        ]]));
        return;
    }

    // ── پروژه‌ها و قراردادها ──────────────────────────────────────────────
    if (str_starts_with($data, 'mproj_list_')) {
        $groupId = (int) substr($data, 11);
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        $grp = getGroup($groupId);
        $rows = [];
        $body = '';
        foreach (CITY_PROJECTS as $key => $proj) {
            $p = getProject($groupId, $key);
            if ($p && $p['status'] === 'building') {
                $body .= "⚙️ " . h($proj['name']) . " — در حال ساخت (" . fmtDur(secsLeft($p['done_at'])) . ")\n";
                continue;
            }
            $lvl = $p ? (int) $p['level'] : 0;
            if ($lvl >= (int) $proj['max_level']) {
                $body .= "🏆 " . h($proj['name']) . " — ماکس (سطح {$lvl})\n";
                continue;
            }
            $cost = projectCost($key, $lvl + 1);
            $body .= "🏗 " . h($proj['name']) . " — سطح {$lvl}/" . (int) $proj['max_level'] . ' — ' . nf($cost) . "\n";
            $rows[] = [btn($proj['name'] . ' (' . nf($cost) . ')', "mproj_build_{$groupId}_{$key}", 'success')];
        }
        $rows[] = [btn('برگشت 🔙', "mayor_back_{$groupId}", 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "🏗 <b>پروژه‌های شهر</b>\n\n" . quote(trim($body), true)
            . "\n🏦 خزانه : " . nf($grp['treasury']), kb($rows));
        return;
    }

    if (preg_match('/^mproj_build_(-?\d+)_(\w+)$/', $data, $m)) {
        $groupId = (int) $m[1];
        $key = $m[2];
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        $proj = CITY_PROJECTS[$key] ?? null;
        if (!$proj) { answerCb($cbId, '❌ پروژه نامعتبر!', true); return; }
        $p = getProject($groupId, $key);
        if ($p && $p['status'] === 'building') { answerCb($cbId, '⚙️ این پروژه در حال ساخته!', true); return; }
        $lvl = $p ? (int) $p['level'] : 0;
        if ($lvl >= (int) $proj['max_level']) { answerCb($cbId, '🏆 این پروژه ماکسه!', true); return; }

        $cost = projectCost($key, $lvl + 1);
        $grp = getGroup($groupId);
        if ((float) $grp['treasury'] < $cost) {
            answerCb($cbId, '❌ خزانه کافی نیست! لازم: ' . nf($cost), true);
            return;
        }

        $doneAt = isoPlus((int) $proj['build_hours'] * 3600);
        transact(static function () use ($groupId, $cost, $key, $lvl, $doneAt, $p) {
            q('UPDATE groups SET treasury = treasury - ? WHERE group_id=?', [$cost, $groupId]);
            if ($p) {
                q("UPDATE mayor_projects SET level=?, status='building', started_at=?, done_at=? WHERE id=?",
                    [$lvl + 1, nowIso(), $doneAt, (int) $p['id']]);
            } else {
                q("INSERT INTO mayor_projects (group_id, project_key, level, started_at, done_at, status)
                   VALUES (?,?,?,?,?,'building')", [$groupId, $key, 1, nowIso(), $doneAt]);
            }
        });
        changeMayorPopularity($groupId, +4);
        logMayorAction($groupId, $uid, 'project', 'ساخت پروژه ' . $proj['name']);
        fulfillMayorPledge($groupId, $uid, 'build_project');

        answerCb($cbId, '🏗 ساخت شروع شد!');
        editMsg($chatId, $mid, "🏗 <b>ساخت پروژه شروع شد!</b>\n\n"
            . quote(h($proj['name']) . " سطح " . ($lvl + 1) . "\n💰 هزینه : " . nf($cost) . "\n⏳ زمان ساخت : " . (int) $proj['build_hours'] . " ساعت\n✨ اثر : " . h($proj['desc'])), null);
        return;
    }

    if (str_starts_with($data, 'mproj_contracts_')) {
        $groupId = (int) substr($data, 16);
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        $active = activeContract($groupId);
        $rows = [];
        $body = '';
        foreach (CITY_CONTRACTS as $key => $def) {
            $body .= h($def['name']) . "\n   ✅ " . h($def['pros']) . "\n   ⚠️ " . h($def['cons']) . "\n";
            if (!$active) {
                $rows[] = [btn($def['name'], "mproj_signc_{$groupId}_{$key}", 'success')];
            }
        }
        $rows[] = [btn('برگشت 🔙', "mayor_back_{$groupId}", 'primary')];
        $head = $active
            ? "🤝 قرارداد فعال : " . h(CITY_CONTRACTS[$active['contract_key']]['name'] ?? '?') . "\n⏳ " . fmtDur(secsLeft($active['expires_at'])) . " تا پایان\n\n"
            : '';
        answerCb($cbId);
        editMsg($chatId, $mid, "🤝 <b>قراردادهای شهری</b>\n\n" . quote($head . trim($body), true)
            . "\n⏳ حداقل مدت قرارداد : " . contractMinDays() . ' روز', kb($rows));
        return;
    }

    if (preg_match('/^mproj_signc_(-?\d+)_(\w+)$/', $data, $m)) {
        $groupId = (int) $m[1];
        $key = $m[2];
        $mayor = getMayor($groupId);
        if (!$mayor || (int) $mayor['user_id'] !== $uid) { answerCb($cbId, '❌ فقط شهردار!', true); return; }
        if (activeContract($groupId)) { answerCb($cbId, '⚠️ یه قرارداد فعال داری!', true); return; }
        $def = CITY_CONTRACTS[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ قرارداد نامعتبر!', true); return; }

        q("INSERT INTO mayor_contracts (group_id, contract_key, started_at, expires_at, status)
           VALUES (?,?,?,?,'active')", [$groupId, $key, nowIso(), isoPlus(contractMinDays() * 86400)]);
        logMayorAction($groupId, $uid, 'contract', 'امضای قرارداد ' . $def['name']);

        answerCb($cbId, '🤝 قرارداد امضا شد!');
        editMsg($chatId, $mid, "🤝 <b>قرارداد امضا شد!</b>\n\n"
            . quote(h($def['name']) . "\n✅ " . h($def['pros']) . "\n⚠️ " . h($def['cons']) . "\n⏳ مدت : " . contractMinDays() . ' روز'), null);
        sendMsg($groupId, "📣 <b>قرارداد جدید شهری!</b>\n\n" . quote(h($def['name']) . "\n✅ " . h($def['pros']) . "\n⚠️ " . h($def['cons'])));
        return;
    }

    answerCb($cbId);
}

/** ثبت انجام وعده انتخاباتی */
function fulfillMayorPledge(int $groupId, int $userId, string $pledgeKey): void
{
    q('UPDATE mayor_pledges SET fulfilled=1 WHERE group_id=? AND user_id=? AND pledge_key=? AND fulfilled=0',
        [$groupId, $userId, $pledgeKey]);
}

/** تکمیل پروژه‌های آماده */
function completeProjectsCron(): void
{
    foreach (all("SELECT * FROM mayor_projects WHERE status='building' AND done_at <= ?", [nowIso()]) as $p) {
        q("UPDATE mayor_projects SET status='done' WHERE id=?", [(int) $p['id']]);
        $proj = CITY_PROJECTS[$p['project_key']] ?? null;
        if ($proj) {
            sendMsg((int) $p['group_id'], "🎉 <b>پروژه تکمیل شد!</b>\n\n"
                . quote(h($proj['name']) . " سطح " . (int) $p['level'] . "\n✅ " . h($proj['desc']) . ' فعال شد!'));
        }
    }
    q("UPDATE mayor_contracts SET status='expired' WHERE status='active' AND expires_at <= ?", [nowIso()]);
}

// ═══════════════════════════════════════════════════════════════════════════
//  👑  کال‌بک‌های رهبری
// ═══════════════════════════════════════════════════════════════════════════

function handleLeaderCallback(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid, array $message): void
{
    $from = $cbq['from'];
    $ld = getLeader();
    $isLeaderNow = $ld && (int) $ld['user_id'] === $uid;

    // ── انتخابات سراسری ───────────────────────────────────────────────────
    if ($data === 'lead_elec_start') {
        if ($ld) { answerCb($cbId, '❌ کشور همین الان رهبر داره!', true); return; }
        if (activeLeaderElection()) { answerCb($cbId, '❌ یه انتخابات فعال هست!', true); return; }
        q("INSERT INTO leader_elections (status, started_by, started_at) VALUES ('candidacy',?,?)", [$uid, nowIso()]);
        answerCb($cbId, '🗳 انتخابات سراسری شروع شد!');
        editLeaderPanel($cbq);
        broadcastGroups("🗳 <b>انتخابات سراسری رهبری شروع شد!</b>\n\n"
            . quote('هر کسی می‌تونه کاندیدا بشه. بنویس <b>نامزد رهبری</b> یا از پنل <b>رهبر</b> ثبت‌نام کن.'));
        return;
    }

    if (str_starts_with($data, 'lead_elec_join_')) {
        $eid = (int) substr($data, 15);
        leaderJoinCandidate($cbq, $eid);
        return;
    }

    if (str_starts_with($data, 'lead_elec_vote_')) {
        $eid = (int) substr($data, 15);
        $e = one('SELECT * FROM leader_elections WHERE id=?', [$eid]);
        if (!$e || $e['status'] !== 'candidacy') { answerCb($cbId, '❌ وضعیت انتخابات نامعتبره!', true); return; }
        $n = (int) val('SELECT COUNT(*) FROM leader_candidates WHERE election_id=?', [$eid], 0);
        if ($n < LEADER_MIN_CANDIDATES) { answerCb($cbId, '❌ کاندیدای کافی نیست!', true); return; }
        q("UPDATE leader_elections SET status='voting', vote_start=? WHERE id=?", [nowIso(), $eid]);
        answerCb($cbId, '🗳 رأی‌گیری شروع شد!');
        editLeaderPanel($cbq);
        broadcastGroups("🗳 <b>رأی‌گیری سراسری رهبری شروع شد!</b>\n\n" . quote("👥 {$n} کاندیدا\nبنویس <b>رای رهبر</b> تا رأی بدی."));
        return;
    }

    if (str_starts_with($data, 'lead_elec_list_')) {
        $eid = (int) substr($data, 15);
        leaderShowCandidates($cbq, $eid);
        return;
    }

    if (preg_match('/^lead_elec_cast_(\d+)_(\d+)$/', $data, $m)) {
        $eid = (int) $m[1];
        $cid = (int) $m[2];
        $e = one('SELECT * FROM leader_elections WHERE id=?', [$eid]);
        if (!$e || $e['status'] !== 'voting') { answerCb($cbId, '❌ رأی‌گیری فعال نیست!', true); return; }
        if (val('SELECT 1 FROM leader_votes WHERE election_id=? AND voter_id=?', [$eid, $uid]) !== null) {
            answerCb($cbId, '❌ قبلاً رأی دادی!', true);
            return;
        }
        q('INSERT INTO leader_votes (election_id, voter_id, candidate_id, voted_at) VALUES (?,?,?,?)
           ON CONFLICT(election_id, voter_id) DO NOTHING', [$eid, $uid, $cid, nowIso()]);
        answerCb($cbId, '✅ رأیت ثبت شد!');
        leaderShowCandidates($cbq, $eid);
        return;
    }

    if (str_starts_with($data, 'lead_elec_finish_')) {
        $eid = (int) substr($data, 17);
        $res = finishLeaderElection($eid);
        if (!$res) {
            answerCb($cbId, '❌ کاندیدای کافی نبود — انتخابات بسته شد.', true);
            editLeaderPanel($cbq);
            return;
        }
        $body = '';
        foreach ($res['candidates'] as $c) {
            $v = $res['counts'][(int) $c['user_id']] ?? 0;
            $body .= "👤 " . h($c['first_name']) . " — {$v} رأی\n";
        }
        answerCb($cbId, '🏁 شمارش انجام شد!');
        $announce = "👑 <b>" . h($res['winner']['first_name']) . " رهبر کشور شد!</b>\n\n"
            . quote("🏆 آرا : " . $res['votes'] . "\n⏳ مدت رهبری : " . LEADER_TERM_DAYS . " روز\n\n" . trim($body), true)
            . "\n✨ تبریک! حالا می‌تونی فرمان ملی صادر کنی.";
        editMsg($chatId, $mid, $announce, null);
        broadcastGroups($announce);
        return;
    }

    // ── امتیازدهی به رهبر ─────────────────────────────────────────────────
    if ($data === 'lead_rate_up' || $data === 'lead_rate_down') {
        if (!$ld) { answerCb($cbId, '❌ رهبری وجود نداره!', true); return; }
        if ($isLeaderNow) { answerCb($cbId, '❌ به خودت نمی‌تونی امتیاز بدی!', true); return; }
        $rating = $data === 'lead_rate_up' ? 'up' : 'down';
        $prev = val('SELECT rating FROM leader_ratings WHERE user_id=?', [$uid]);
        if ($prev === $rating) { answerCb($cbId, '❌ قبلاً همین امتیاز رو دادی!', true); return; }
        q('INSERT INTO leader_ratings (user_id, rating, rated_at) VALUES (?,?,?)
           ON CONFLICT(user_id) DO UPDATE SET rating=excluded.rating, rated_at=excluded.rated_at',
            [$uid, $rating, nowIso()]);
        $delta = $rating === 'up' ? 1 : -1;
        if ($prev !== null) {
            $delta *= 2; // خنثی کردن رأی قبلی
        }
        q('UPDATE leader SET popularity = MAX(0, MIN(100, popularity + ?)) WHERE user_id=?', [$delta, (int) $ld['user_id']]);
        answerCb($cbId, $rating === 'up' ? '👍 امتیاز مثبت ثبت شد!' : '👎 امتیاز منفی ثبت شد!');
        editLeaderPanel($cbq);
        return;
    }

    if ($data === 'lead_back') {
        answerCb($cbId);
        editLeaderPanel($cbq);
        return;
    }

    // ── از اینجا به بعد فقط رهبر ──────────────────────────────────────────
    $adminPanels = ['lead_admin', 'lead_admin_setleader', 'lead_admin_removeleader', 'lead_admin_addtreasury'];
    if (in_array($data, $adminPanels, true) || str_starts_with($data, 'lead_admin_')) {
        if (!isAdmin($uid)) { answerCb($cbId, '❌ فقط ادمین اصلی!', true); return; }
        if ($data === 'lead_admin') {
            answerCb($cbId);
            editMsg($chatId, $mid, "🛠 <b>پنل ادمین رهبری</b>\n\n"
                . quote("👑 رهبر فعلی : " . ($ld ? h($ld['first_name']) : 'ندارد') . "\n🏦 خزانه ملی : " . nf(treasury())),
                kb([
                    [btn('تعیین رهبر (ریپلای) 👑', 'lead_admin_setleader', 'success')],
                    [btn('برکناری رهبر 🚫', 'lead_admin_removeleader', 'danger')],
                    [btn('افزودن به خزانه ملی 💰', 'lead_admin_addtreasury', 'success')],
                    [btn('برگشت 🔙', 'lead_back', 'primary')],
                ]));
            return;
        }
        if ($data === 'lead_admin_removeleader') {
            if (!$ld) { answerCb($cbId, '❌ رهبری وجود نداره!', true); return; }
            q('DELETE FROM leader');
            answerCb($cbId, '🚫 رهبر برکنار شد!');
            editLeaderPanel($cbq);
            broadcastGroups("🚫 <b>رهبر کشور توسط ادمین برکنار شد!</b>\n\n" . quote('انتخابات جدید می‌تونه شروع بشه.'));
            return;
        }
        if ($data === 'lead_admin_addtreasury') {
            setState($uid, ['flow' => 'lead_treasury']);
            answerCb($cbId);
            editMsg($chatId, $mid, "💰 مبلغی که می‌خوای به خزانه ملی اضافه بشه رو بنویس:\n\n" . quote('برای لغو بنویس: لغو'), null);
            return;
        }
        if ($data === 'lead_admin_setleader') {
            setState($uid, ['flow' => 'lead_setleader']);
            answerCb($cbId);
            editMsg($chatId, $mid, "👑 آیدی عددی یا یوزرنیم کاربر موردنظر رو بنویس:\n\n" . quote('برای لغو بنویس: لغو'), null);
            return;
        }
    }

    if (!$isLeaderNow) {
        answerCb($cbId, '❌ فقط رهبر کشور می‌تونه این کار رو بکنه!', true);
        return;
    }

    if ($data === 'lead_cmds') {
        [$can, $left] = canIssueCommand();
        if (!$can) { answerCb($cbId, '⌛️ ' . fmtDur($left) . ' تا فرمان بعدی!', true); return; }
        $rows = [];
        $body = '';
        foreach (NATIONAL_COMMANDS as $key => $def) {
            $body .= h($def['name']) . " — " . h($def['desc']) . "\n";
            $rows[] = [btn($def['name'], "lead_cmd_{$key}", 'success')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "⚡ <b>صدور فرمان ملی</b>\n\n" . quote(trim($body), true)
            . "\n⏳ مدت اثر : " . fmtDur(LEADER_COMMAND_DURATION) . "\n🔄 کولداون : " . fmtDur(LEADER_COMMAND_COOLDOWN), kb($rows));
        return;
    }

    if (str_starts_with($data, 'lead_cmd_')) {
        $key = substr($data, 9);
        $def = NATIONAL_COMMANDS[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ فرمان نامعتبر!', true); return; }
        [$can, $left] = canIssueCommand();
        if (!$can) { answerCb($cbId, '⌛️ ' . fmtDur($left) . ' تا فرمان بعدی!', true); return; }
        q('UPDATE leader_commands SET is_active=0 WHERE is_active=1');
        q('INSERT INTO leader_commands (command_key, issued_at, expires_at, is_active) VALUES (?,?,?,1)',
            [$key, nowIso(), isoPlus(LEADER_COMMAND_DURATION)]);
        logLeader($uid, 'command', 'صدور فرمان ' . $def['name']);
        answerCb($cbId, '⚡ فرمان صادر شد!');
        $announce = "⚡ <b>فرمان ملی جدید!</b>\n\n"
            . quote("👑 رهبر : " . h($ld['first_name']) . "\n📜 " . h($def['name']) . "\n✨ " . h($def['desc']) . "\n⏳ مدت : " . fmtDur(LEADER_COMMAND_DURATION));
        editMsg($chatId, $mid, $announce, null);
        broadcastGroups($announce);
        return;
    }

    if ($data === 'lead_laws') {
        $active = activeNationalLaws();
        $activeKeys = array_column($active, 'law_key');
        $rows = [];
        $body = '';
        foreach (NATIONAL_LAWS as $key => $def) {
            $on = in_array($key, $activeKeys, true);
            $body .= ($on ? '✅ ' : '⬜ ') . h($def['name']) . "\n";
            $rows[] = [btn(($on ? 'لغو ' : 'وضع ') . $def['name'], "lead_law_{$key}", $on ? 'danger' : 'success')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "⚖️ <b>قوانین ملی</b>\n\n" . quote(trim($body), true), kb($rows));
        return;
    }

    if (str_starts_with($data, 'lead_law_')) {
        $key = substr($data, 9);
        $def = NATIONAL_LAWS[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ قانون نامعتبر!', true); return; }
        $existing = one("SELECT * FROM leader_laws WHERE law_key=? AND is_active=1", [$key]);
        if ($existing) {
            q('UPDATE leader_laws SET is_active=0 WHERE id=?', [(int) $existing['id']]);
            logLeader($uid, 'law', 'لغو قانون ' . $def['name']);
            answerCb($cbId, '🚫 قانون لغو شد!');
        } else {
            q('INSERT INTO leader_laws (law_key, law_name, enacted_at, expires_at, is_active) VALUES (?,?,?,NULL,1)',
                [$key, $def['name'], nowIso()]);
            logLeader($uid, 'law', 'وضع قانون ' . $def['name']);
            answerCb($cbId, '⚖️ قانون وضع شد!');
            broadcastGroups("⚖️ <b>قانون ملی جدید!</b>\n\n" . quote(h($def['name'])));
        }
        handleLeaderCallback($cbq, 'lead_laws', $uid, $cbId, $chatId, $mid, $message);
        return;
    }

    if ($data === 'lead_events') {
        $rows = [];
        $body = '';
        foreach (NATIONAL_EVENTS as $key => $def) {
            $body .= h($def['name']) . " — " . nf($def['cost']) . " 🦴 — " . (int) $def['hours'] . " ساعت\n";
            $rows[] = [btn($def['name'] . ' (' . nf($def['cost']) . ')', "lead_event_{$key}", 'success')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "🎉 <b>رویدادهای ملی</b>\n\n" . quote(trim($body), true)
            . "\n🏦 خزانه ملی : " . nf(treasury()), kb($rows));
        return;
    }

    if (str_starts_with($data, 'lead_event_')) {
        $key = substr($data, 11);
        $def = NATIONAL_EVENTS[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ رویداد نامعتبر!', true); return; }
        if (one("SELECT * FROM national_events WHERE event_key=? AND is_active=1 AND expires_at > ?", [$key, nowIso()])) {
            answerCb($cbId, '⚠️ این رویداد همین الان فعاله!', true);
            return;
        }
        if (treasury() < (float) $def['cost']) {
            answerCb($cbId, '❌ خزانه ملی کافی نیست! لازم: ' . nf($def['cost']), true);
            return;
        }
        transact(static function () use ($def, $key, $uid) {
            changeTreasury(-(float) $def['cost'], 'برگزاری رویداد ' . $def['name'], $uid);
            q('INSERT INTO national_events (event_key, started_at, expires_at, is_active) VALUES (?,?,?,1)',
                [$key, nowIso(), isoPlus((int) $def['hours'] * 3600)]);
        });
        logLeader($uid, 'event', 'برگزاری رویداد ' . $def['name']);
        answerCb($cbId, '🎉 رویداد شروع شد!');
        $announce = "🎉 <b>رویداد ملی شروع شد!</b>\n\n"
            . quote(h($def['name']) . "\n⏳ مدت : " . (int) $def['hours'] . " ساعت\n💰 هزینه : " . nf($def['cost']) . ' از خزانه ملی');
        editMsg($chatId, $mid, $announce, null);
        broadcastGroups($announce);
        return;
    }

    if ($data === 'lead_projects') {
        $rows = [];
        $body = '';
        foreach (NATIONAL_PROJECTS as $key => $def) {
            $p = one('SELECT * FROM national_projects WHERE project_key=?', [$key]);
            if ($p) {
                $icon = $p['status'] === 'done' ? '✅' : '⚙️';
                $extra = $p['status'] === 'done' ? 'تکمیل شده' : ('در حال ساخت — ' . fmtDur(secsLeft($p['done_at'])));
                $body .= "{$icon} " . h($def['name']) . " — {$extra}\n";
                continue;
            }
            $body .= "🏗 " . h($def['name']) . " — " . nf($def['cost']) . " 🦴 — " . (int) $def['build_hours'] . " ساعت\n";
            $rows[] = [btn($def['name'] . ' (' . nf($def['cost']) . ')', "lead_proj_{$key}", 'success')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "🏗 <b>پروژه‌های ملی</b>\n\n" . quote(trim($body), true)
            . "\n🏦 خزانه ملی : " . nf(treasury()), kb($rows));
        return;
    }

    if (str_starts_with($data, 'lead_proj_')) {
        $key = substr($data, 10);
        $def = NATIONAL_PROJECTS[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ پروژه نامعتبر!', true); return; }
        if (one('SELECT 1 FROM national_projects WHERE project_key=?', [$key])) {
            answerCb($cbId, '⚠️ این پروژه قبلاً شروع شده!', true);
            return;
        }
        if (treasury() < (float) $def['cost']) {
            answerCb($cbId, '❌ خزانه ملی کافی نیست! لازم: ' . nf($def['cost']), true);
            return;
        }
        transact(static function () use ($def, $key, $uid) {
            changeTreasury(-(float) $def['cost'], 'ساخت پروژه ملی ' . $def['name'], $uid);
            q("INSERT INTO national_projects (project_key, status, started_at, done_at) VALUES (?,'building',?,?)",
                [$key, nowIso(), isoPlus((int) $def['build_hours'] * 3600)]);
        });
        logLeader($uid, 'project', 'ساخت پروژه ملی ' . $def['name']);
        answerCb($cbId, '🏗 ساخت شروع شد!');
        $announce = "🏗 <b>ساخت پروژه ملی شروع شد!</b>\n\n"
            . quote(h($def['name']) . "\n💰 هزینه : " . nf($def['cost']) . "\n⏳ زمان : " . (int) $def['build_hours'] . " ساعت\n✨ " . h($def['desc']));
        editMsg($chatId, $mid, $announce, null);
        broadcastGroups($announce);
        return;
    }

    if ($data === 'lead_ministers') {
        $rows = [];
        $body = '';
        foreach (MINISTER_ROLES as $role => $def) {
            $m = one('SELECT * FROM ministers WHERE role=?', [$role]);
            $body .= h($def['name']) . ' — ' . ($m ? h($m['first_name']) : '<i>خالی</i>') . ' — ' . h($def['desc']) . "\n";
            $rows[] = [btn(($m ? 'تغییر ' : 'انتصاب ') . $def['name'], "lead_min_{$role}", $m ? 'warning' : 'success')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "👔 <b>کابینه دولت</b>\n\n" . quote(trim($body), true)
            . "\n💡 برای انتصاب، آیدی عددی یا یوزرنیم فرد رو می‌فرستی.", kb($rows));
        return;
    }

    if (str_starts_with($data, 'lead_min_')) {
        $role = substr($data, 9);
        if (!isset(MINISTER_ROLES[$role])) { answerCb($cbId, '❌ سمت نامعتبر!', true); return; }
        setState($uid, ['flow' => 'lead_minister', 'role' => $role]);
        answerCb($cbId);
        editMsg($chatId, $mid, "👔 <b>انتصاب " . h(MINISTER_ROLES[$role]['name']) . "</b>\n\n"
            . quote('آیدی عددی یا یوزرنیم فرد رو بنویس  —  برای لغو بنویس: لغو'), null);
        return;
    }

    if ($data === 'lead_budget') {
        setState($uid, ['flow' => 'lead_budget', 'step' => 'group']);
        answerCb($cbId);
        $groups = all('SELECT group_id, title FROM groups ORDER BY total_hops DESC LIMIT 20');
        $rows = [];
        foreach ($groups as $g) {
            $rows[] = [btn(mb_substr((string) ($g['title'] ?: 'گروه'), 0, 28), 'lead_budgetg_' . (int) $g['group_id'], 'primary')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        editMsg($chatId, $mid, "💰 <b>تخصیص بودجه به شهرها</b>\n\n"
            . quote('🏦 خزانه ملی : ' . nf(treasury()) . "\n\nشهر مقصد رو انتخاب کن:"), kb($rows));
        return;
    }

    if (str_starts_with($data, 'lead_budgetg_')) {
        $gid = (int) substr($data, 13);
        setState($uid, ['flow' => 'lead_budget', 'step' => 'amount', 'group_id' => $gid]);
        answerCb($cbId);
        $g = getGroup($gid);
        editMsg($chatId, $mid, "💰 <b>بودجه برای " . h($g['title'] ?: 'گروه') . "</b>\n\n"
            . quote("🏦 خزانه ملی : " . nf(treasury()) . "\n\nمبلغ رو بنویس  —  برای لغو بنویس: لغو"), null);
        return;
    }

    if ($data === 'lead_emergency') {
        $em = activeEmergency();
        if ($em) {
            answerCb($cbId);
            editMsg($chatId, $mid, "🚨 <b>وضعیت اضطراری فعال</b>\n\n"
                . quote(h(EMERGENCY_TYPES[$em['etype']]['name'] ?? $em['etype']) . "\n⏳ " . fmtDur(secsLeft($em['expires_at']))),
                kb([
                    [btn('لغو وضعیت اضطراری 🚫', 'lead_em_cancel', 'danger')],
                    [btn('برگشت 🔙', 'lead_back', 'primary')],
                ]));
            return;
        }
        $rows = [];
        $body = '';
        foreach (EMERGENCY_TYPES as $key => $def) {
            $body .= h($def['name']) . ' — ' . (int) $def['hours'] . " ساعت\n";
            $rows[] = [btn($def['name'], "lead_em_{$key}", 'danger')];
        }
        $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
        answerCb($cbId);
        editMsg($chatId, $mid, "🚨 <b>اعلام وضعیت اضطراری</b>\n\n" . quote(trim($body), true)
            . "\n⚠️ وضعیت اضطراری روی کل اقتصاد کشور اثر می‌ذاره!", kb($rows));
        return;
    }

    if ($data === 'lead_em_cancel') {
        q('UPDATE emergency_state SET is_active=0, etype=NULL WHERE id=1');
        logLeader($uid, 'emergency', 'لغو وضعیت اضطراری');
        answerCb($cbId, '🚫 وضعیت اضطراری لغو شد!');
        editLeaderPanel($cbq);
        broadcastGroups("✅ <b>وضعیت اضطراری لغو شد!</b>\n\n" . quote('کشور به حالت عادی برگشت 🕊'));
        return;
    }

    if (str_starts_with($data, 'lead_em_')) {
        $key = substr($data, 8);
        $def = EMERGENCY_TYPES[$key] ?? null;
        if (!$def) { answerCb($cbId, '❌ نوع نامعتبر!', true); return; }
        q('UPDATE emergency_state SET etype=?, started_at=?, expires_at=?, is_active=1 WHERE id=1',
            [$key, nowIso(), isoPlus((int) $def['hours'] * 3600)]);
        logLeader($uid, 'emergency', 'اعلام ' . $def['name']);
        answerCb($cbId, '🚨 وضعیت اضطراری اعلام شد!');
        $announce = "🚨 <b>وضعیت اضطراری اعلام شد!</b>\n\n"
            . quote(h($def['name']) . "\n⏳ مدت : " . (int) $def['hours'] . " ساعت\n\n⚠️ اقتصاد کشور تحت تأثیر قرار گرفت!");
        editMsg($chatId, $mid, $announce, null);
        broadcastGroups($announce);
        return;
    }

    if ($data === 'lead_resign') {
        q('DELETE FROM leader');
        logLeader($uid, 'resign', 'استعفا از رهبری');
        answerCb($cbId, '🚪 استعفا دادی!');
        $announce = "🚪 <b>" . h($ld['first_name']) . " از رهبری کشور استعفا داد!</b>\n\n" . quote('انتخابات جدید می‌تونه شروع بشه.');
        editMsg($chatId, $mid, $announce, null);
        broadcastGroups($announce);
        return;
    }

    answerCb($cbId);
}

function leaderJoinCandidate(array $cbq, int $electionId): void
{
    $uid = (int) $cbq['from']['id'];
    $cbId = $cbq['id'];
    $e = one('SELECT * FROM leader_elections WHERE id=?', [$electionId]);
    if (!$e || $e['status'] !== 'candidacy') {
        answerCb($cbId, '❌ مهلت ثبت‌نام تموم شده!', true);
        return;
    }
    $u = getUser($uid);
    if (!$u || (int) $u['level'] < 5) {
        answerCb($cbId, '🔒 برای کاندیداتوری رهبری باید حداقل سطح ۵ باشی!', true);
        return;
    }
    if (val('SELECT 1 FROM leader_candidates WHERE election_id=? AND user_id=?', [$electionId, $uid]) !== null) {
        answerCb($cbId, '✅ قبلاً ثبت‌نام کردی!', true);
        return;
    }
    q('INSERT INTO leader_candidates (election_id, user_id, username, first_name, joined_at) VALUES (?,?,?,?,?)
       ON CONFLICT(election_id, user_id) DO NOTHING',
        [$electionId, $uid, (string) ($cbq['from']['username'] ?? ''), displayName($cbq['from']), nowIso()]);
    $n = (int) val('SELECT COUNT(*) FROM leader_candidates WHERE election_id=?', [$electionId], 0);
    answerCb($cbId, "✅ کاندیدای رهبری شدی! ({$n} کاندیدا)");
    editLeaderPanel($cbq);
}

function leaderShowCandidates(array $cbq, int $electionId): void
{
    $cbId = $cbq['id'];
    $chatId = (int) $cbq['message']['chat']['id'];
    $mid = (int) $cbq['message']['message_id'];

    $e = one('SELECT * FROM leader_elections WHERE id=?', [$electionId]);
    if (!$e || $e['status'] !== 'voting') {
        answerCb($cbId, '❌ رأی‌گیری فعال نیست!', true);
        return;
    }
    $cands = all('SELECT * FROM leader_candidates WHERE election_id=?', [$electionId]);
    $counts = [];
    foreach (all('SELECT candidate_id, COUNT(*) AS n FROM leader_votes WHERE election_id=? GROUP BY candidate_id', [$electionId]) as $r) {
        $counts[(int) $r['candidate_id']] = (int) $r['n'];
    }
    $rows = [];
    $body = '';
    foreach ($cands as $c) {
        $v = $counts[(int) $c['user_id']] ?? 0;
        $body .= "👤 " . h($c['first_name']) . " — {$v} رأی\n";
        $rows[] = [btn('رأی به ' . mb_substr($c['first_name'], 0, 20) . " ({$v})", 'lead_elec_cast_' . $electionId . '_' . (int) $c['user_id'], 'success')];
    }
    $rows[] = [btn('شمارش نهایی 🏁', 'lead_elec_finish_' . $electionId, 'warning')];
    $rows[] = [btn('برگشت 🔙', 'lead_back', 'primary')];
    answerCb($cbId);
    editMsg($chatId, $mid, "🗳 <b>کاندیداهای رهبری کشور</b>\n\n" . quote(trim($body) ?: 'کاندیدایی نیست', true), kb($rows));
}

/** کارهای دوره‌ای رهبری */
function leaderCron(): void
{
    q("UPDATE leader_commands SET is_active=0 WHERE is_active=1 AND expires_at <= ?", [nowIso()]);
    q("UPDATE national_events SET is_active=0 WHERE is_active=1 AND expires_at <= ?", [nowIso()]);
    q("UPDATE emergency_state SET is_active=0, etype=NULL WHERE is_active=1 AND expires_at <= ?", [nowIso()]);

    foreach (all("SELECT * FROM national_projects WHERE status='building' AND done_at <= ?", [nowIso()]) as $p) {
        q("UPDATE national_projects SET status='done' WHERE id=?", [(int) $p['id']]);
        $def = NATIONAL_PROJECTS[$p['project_key']] ?? null;
        if ($def) {
            broadcastGroups("🎉 <b>پروژه ملی تکمیل شد!</b>\n\n" . quote(h($def['name']) . "\n✅ " . h($def['desc'])));
        }
    }

    // پایان دوره رهبر
    $ld = one('SELECT * FROM leader LIMIT 1');
    if ($ld && !isFuture($ld['term_end'])) {
        q('DELETE FROM leader WHERE user_id=?', [(int) $ld['user_id']]);
        broadcastGroups("⏳ <b>دوره رهبری " . h($ld['first_name']) . " به پایان رسید!</b>\n\n" . quote('انتخابات جدید می‌تونه شروع بشه.'));
    }

    // پایان دوره شهردارها
    foreach (all('SELECT * FROM mayor WHERE term_end <= ?', [nowIso()]) as $m) {
        q('DELETE FROM mayor WHERE group_id=?', [(int) $m['group_id']]);
        sendMsg((int) $m['group_id'], "⏳ <b>دوره شهرداری " . h($m['first_name']) . " تموم شد!</b>\n\n" . quote('انتخابات جدید می‌تونه شروع بشه.'));
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  🛠  پنل‌های ادمین
// ═══════════════════════════════════════════════════════════════════════════

function resetPanelData(): array
{
    $total = (int) val('SELECT COUNT(*) FROM users', [], 0);
    $text = "🔴 <b>پنل ریست</b>\n\n"
        . quote("👥 کل کاربران : {$total}\n\n⚠️ ریست، موجودی و سطح کاربران رو صفر می‌کنه!")
        . "\nیه گزینه انتخاب کن:";
    $keyboard = kb([
        [btn('ریست بر اساس موجودی 💰', 'adm_reset_by_balance', 'warning')],
        [btn('انصراف', 'adm_reset_cancel', 'danger')],
    ]);
    return [$text, $keyboard];
}

function forceJoinPanelData(): array
{
    $channels = all('SELECT * FROM force_join_channels ORDER BY added_at');
    $body = FORCE_JOIN_ENABLED ? "وضعیت : 🟢 فعال\n\n" : "وضعیت : 🔴 غیرفعال (در تنظیمات فایل)\n\n";
    if (FORCE_JOIN_CHANNEL !== '' && FORCE_JOIN_CHANNEL !== 'یوزرنیم کانال') {
        $body .= "📌 کانال ثابت : @" . h(FORCE_JOIN_CHANNEL) . "\n";
    }
    foreach ($channels as $c) {
        $body .= "• @" . h($c['username']) . ' — ' . h($c['name']) . "\n";
    }
    if ($channels === []) {
        $body .= "\nکانال اضافه‌ای ثبت نشده.";
    }
    $rows = [[btn('افزودن کانال ➕', 'adm_fj_add', 'success')]];
    foreach ($channels as $c) {
        $rows[] = [btn('حذف @' . mb_substr((string) $c['username'], 0, 20), 'adm_fj_del_' . $c['username'], 'danger')];
    }
    return ["🔔 <b>مدیریت جوین اجباری</b>\n\n" . quote(trim($body), true), kb($rows)];
}

function handleAdminCallback(array $cbq, string $data, int $uid, string $cbId, int $chatId, int $mid, array $message): void
{
    if (!isAdmin($uid)) {
        answerCb($cbId, '❌ فقط ادمین اصلی!', true);
        return;
    }

    if (str_starts_with($data, 'confirm_delete_user_')) {
        $targetId = (int) substr($data, 20);
        if (in_array($targetId, ADMIN_IDS, true)) {
            answerCb($cbId, '❌ نمیشه ادمین اصلی رو حذف کرد!', true);
            return;
        }
        $tu = getUser($targetId);
        if (!$tu) {
            answerCb($cbId, '❌ کاربر پیدا نشد!', true);
            editMsg($chatId, $mid, '❌ کاربر پیدا نشد!', null);
            return;
        }
        $name = (string) $tu['first_name'];
        $tables = ['users', 'dogs', 'hooks', 'pending_bones', 'bank', 'transfers',
                   'user_strays', 'jail', 'factories', 'sub_admins', 'user_states',
                   'challenge_stats', 'referrals', 'leader_ratings'];
        transact(static function () use ($tables, $targetId) {
            foreach ($tables as $t) {
                try {
                    q("DELETE FROM $t WHERE user_id=?", [$targetId]);
                } catch (Throwable $e) {
                    // بی‌خطر
                }
            }
        });
        answerCb($cbId, '🗑 کاربر حذف شد!');
        editMsg($chatId, $mid, "🗑 <b>کاربر " . h($name) . " از دیتابیس حذف شد!</b>\n\n"
            . quote("🪪 آیدی : <code>{$targetId}</code>\nتمام اطلاعاتش پاک شد."), null);
        return;
    }

    if ($data === 'adm_reset_by_balance') {
        setState($uid, ['flow' => 'reset', 'step' => 'threshold']);
        answerCb($cbId);
        editMsg($chatId, $mid, "💰 <b>ریست بر اساس موجودی</b>\n\n"
            . quote("کاربرانی که موجودی‌شون <b>کمتر یا مساوی</b> این عدد باشه ریست میشن.\n\nعدد آستانه رو بنویس  —  برای لغو بنویس: لغو"), null);
        return;
    }

    if (str_starts_with($data, 'adm_reset_confirm_')) {
        $threshold = (int) substr($data, 18);
        setState($uid, ['flow' => 'reset', 'step' => 'init_points', 'threshold' => $threshold]);
        answerCb($cbId);
        editMsg($chatId, $mid, "✅ آستانه تأیید شد: <code>" . nf($threshold) . "</code>\n\n"
            . quote('حالا <b>موجودی اولیه</b> که به همه داده بشه رو بنویس (مثلاً 0 یا 1000):'), null);
        return;
    }

    if ($data === 'adm_reset_cancel') {
        clearState($uid);
        answerCb($cbId, '❌ ریست لغو شد.');
        editMsg($chatId, $mid, '❌ ریست لغو شد.', null);
        return;
    }

    if ($data === 'adm_fj_add') {
        setState($uid, ['flow' => 'fj_add']);
        answerCb($cbId);
        editMsg($chatId, $mid, "➕ <b>افزودن کانال جوین اجباری</b>\n\n"
            . quote("اطلاعات کانال رو در یک خط بفرست:\n<code>یوزرنیم | نام نمایشی | لینک</code>\n\nمثال:\n<code>mychannel | کانال ما | https://t.me/mychannel</code>\n\nبرای لغو بنویس: لغو"), null);
        return;
    }

    if (str_starts_with($data, 'adm_fj_del_')) {
        $username = substr($data, 11);
        q('DELETE FROM force_join_channels WHERE username=?', [$username]);
        answerCb($cbId, '🗑 کانال حذف شد!');
        [$text, $keyboard] = forceJoinPanelData();
        editMsg($chatId, $mid, $text, $keyboard);
        return;
    }

    answerCb($cbId);
}

/** اجرای ریست کاربران */
function doResetUsers(int $threshold, int $initPoints, int $initLevel = 1): int
{
    $adminList = implode(',', array_map('intval', ADMIN_IDS));
    $where = "hop_points <= :th" . ($adminList !== '' ? " AND user_id NOT IN ($adminList)" : '');
    $affected = (int) val("SELECT COUNT(*) FROM users WHERE $where", [':th' => $threshold], 0);

    $initHops = $initLevel >= 2 ? (levelThresholds()[$initLevel - 2] ?? 0) : 0;

    transact(static function () use ($where, $threshold, $initPoints, $initLevel, $initHops) {
        q("UPDATE users SET hop_points=:p, level=:l, total_hops=:hp WHERE $where",
            [':p' => $initPoints, ':l' => $initLevel, ':hp' => $initHops, ':th' => $threshold]);
    });

    return $affected;
}

// ═══════════════════════════════════════════════════════════════════════════
//  ⌨️  پردازش ورودی متنی (وضعیت مکالمه)
// ═══════════════════════════════════════════════════════════════════════════

/**
 * true اگر پیام به عنوان ورودی یک مکالمه مصرف شد.
 * $text متن خام کاربر است (بدون نرمال‌سازی) تا محتوایی مثل اسم آگهی
 * یا عنوان قرعه‌کشی دقیقاً همان‌طور که نوشته شده ذخیره شود.
 */
function handleStateInput(array $msg, array $from, string $text): bool
{
    $uid = (int) $from['id'];
    $state = getState($uid);
    if ($state === []) {
        return false;
    }
    $flow = (string) ($state['flow'] ?? '');
    $chatId = (int) $msg['chat']['id'];
    $text = trim($text);

    if (in_array(normalizeText($text), ['لغو', 'انصراف', 'کنسل', 'cancel'], true)) {
        clearState($uid);
        reply($msg, '❌ عملیات لغو شد.');
        return true;
    }

    switch ($flow) {
        // ── تغییر نام سگ ───────────────────────────────────────────────────
        case 'rename_dog':
            if (mb_strlen($text) > 15) {
                reply($msg, '❌ اسم نباید بیشتر از ۱۵ کاراکتر باشه!');
                return true;
            }
            if (mb_strlen($text) < 1) {
                reply($msg, '❌ اسم نمی‌تونه خالی باشه!');
                return true;
            }
            q('UPDATE dogs SET name=? WHERE user_id=?', [$text, $uid]);
            clearState($uid);
            [$panelText, $panelKb] = dogPanel($uid, $chatId);
            reply($msg, '✅ اسم سگت به <b>' . h($text) . "</b> تغییر پیدا کرد! 🐕\n\n" . $panelText, $panelKb);
            return true;

        // ── بانک ───────────────────────────────────────────────────────────
        case 'bank':
            $amount = parseAmount($text);
            if ($amount <= 0) {
                reply($msg, '❌ مبلغ نامعتبره! دوباره بنویس یا «لغو» بزن.');
                return true;
            }
            $bank = getBank($uid);
            if (!$bank) {
                clearState($uid);
                reply($msg, '❌ حساب بانکی نداری!');
                return true;
            }
            $action = (string) ($state['action'] ?? 'deposit');
            if ($action === 'deposit') {
                if (userPoints($uid) < $amount) {
                    reply($msg, '❌ موجودی کافی نداری! موجودی: ' . nf(userPoints($uid)));
                    return true;
                }
                transact(static function () use ($uid, $amount) {
                    deductPoints($uid, $amount);
                    q('UPDATE bank SET balance = balance + ? WHERE user_id=?', [$amount, $uid]);
                });
                clearState($uid);
                reply($msg, "✅ <b>" . nf($amount) . " هاپ پوینت واریز شد!</b>\n\n"
                    . quote('🏦 موجودی بانک : ' . nf((float) $bank['balance'] + $amount)));
            } else {
                if ((float) $bank['balance'] < $amount) {
                    reply($msg, '❌ موجودی بانک کافی نیست! موجودی: ' . nf($bank['balance']));
                    return true;
                }
                transact(static function () use ($uid, $amount) {
                    q('UPDATE bank SET balance = balance - ? WHERE user_id=?', [$amount, $uid]);
                    addPoints($uid, $amount);
                });
                clearState($uid);
                reply($msg, "✅ <b>" . nf($amount) . " هاپ پوینت برداشت شد!</b>\n\n"
                    . quote('👛 موجودی کیف : ' . nf(userPoints($uid))));
            }
            return true;

        // ── ساخت میز XO ────────────────────────────────────────────────────
        case 'xo_create':
            $bet = parseAmount($text);
            if ($bet <= 0) {
                reply($msg, '❌ عدد معتبر وارد کن!');
                return true;
            }
            clearState($uid);
            xoCreateTable($msg, $from, $bet);
            return true;

        // ── تاس ────────────────────────────────────────────────────────────
        case 'dice':
            if (($state['step'] ?? '') === 'bet') {
                $bet = parseAmount($text);
                if ($bet <= 0 || userPoints($uid) < $bet) {
                    reply($msg, '❌ مبلغ نامعتبر یا موجودی کافی نیست!');
                    return true;
                }
                $state['bet'] = $bet;
                $state['step'] = 'mode';
                setState($uid, $state);
                reply($msg, "🎲 <b>چه نوع شرطی؟</b>\n\n" . quote('شرط : ' . nf($bet)),
                    kb([[
                        btn('زوج یا فرد (1.7x)', "dice_mode_evenodd_{$uid}", 'primary'),
                        btn('عدد دقیق (4x)', "dice_mode_exact_{$uid}", 'primary'),
                    ]]));
                return true;
            }
            if (($state['step'] ?? '') === 'guess_exact') {
                $guess = (int) faToEn($text);
                if ($guess < 1 || $guess > 6) {
                    reply($msg, '❌ عدد ۱ تا ۶ وارد کن!');
                    return true;
                }
                $bet = (int) ($state['bet'] ?? 0);
                if (userPoints($uid) < $bet) {
                    clearState($uid);
                    reply($msg, '❌ موجودی کافی نداری!');
                    return true;
                }
                clearState($uid);
                deductPoints($uid, $bet);
                setGameCooldown($uid, 'dice');
                reply($msg, "🎲 حدست : <b>{$guess}</b> | شرط : " . nf($bet) . "\n\n⏳ در حال پرتاب تاس...");
                $roll = rollDice($chatId);
                if ($roll === $guess) {
                    $win = $bet * 4;
                    addPoints($uid, $win);
                    sendMsg($chatId, "🎯 عدد <b>{$roll}</b>!\n\n" . quote('🎉 درست حدس زدی! +' . nf($win) . ' هاپ پوینت'));
                } else {
                    sendMsg($chatId, "💀 عدد <b>{$roll}</b> (تو گفتی {$guess})!\n\n" . quote('😢 اشتباه بود. -' . nf($bet) . ' هاپ پوینت'));
                }
                return true;
            }
            return true;

        // ── گردونه ─────────────────────────────────────────────────────────
        case 'wheel':
            $bet = parseAmount($text);
            if ($bet <= 0) {
                reply($msg, '❌ عدد معتبر وارد کن!');
                return true;
            }
            if (userPoints($uid) < $bet) {
                clearState($uid);
                reply($msg, '❌ موجودی کافی نداری!');
                return true;
            }
            if (gameCooldownLeft($uid, 'wheel', WHEEL_COOLDOWN) > 0) {
                clearState($uid);
                reply($msg, '⌛️ هنوز کولداون گردونه داری!');
                return true;
            }
            clearState($uid);
            deductPoints($uid, $bet);
            setGameCooldown($uid, 'wheel');
            reply($msg, "🎰 شرط : <b>" . nf($bet) . "</b>\n\n⏳ در حال چرخوندن گردونه...");
            $slot = rollDice($chatId, '🎰');
            [$mult, $label] = wheelResult($slot);
            $win = (int) ($bet * $mult);
            if ($mult == 0.0) {
                $result = "💀 " . $label . "\n-" . nf($bet) . ' هاپ پوینت';
            } else {
                addPoints($uid, $win);
                $result = $mult < 1.0
                    ? "😬 {$label} (×{$mult})\n+" . nf($win) . ' برگشت (باختی ' . nf($bet - $win) . ')'
                    : ($mult >= 15 ? '🎰' : '🎉') . " {$label} (×{$mult})\n+" . nf($win) . ' هاپ پوینت';
            }
            sendMsg($chatId, "🎰 <b>نتیجه گردونه!</b>\n\n" . quote($result));
            return true;

        // ── قمار گروهی ─────────────────────────────────────────────────────
        case 'gamble':
            $bet = parseAmount($text);
            if ($bet <= 0 || userPoints($uid) < $bet) {
                reply($msg, '❌ مبلغ نامعتبر یا موجودی کافی نیست!');
                return true;
            }
            clearState($uid);
            $groupId = (int) ($state['group_id'] ?? $chatId);
            $tableId = 'gm' . $uid . dechex(time());
            transact(static function () use ($uid, $bet, $tableId, $groupId) {
                deductPoints($uid, $bet);
                q("INSERT INTO game_tables (table_id, group_id, game_type, creator_id, bet, state, board, created_at)
                   VALUES (?,?,'gamble',?,?,'waiting',?,?)", [$tableId, $groupId, $uid, $bet, "{$uid}:{$bet}", nowIso()]);
            });
            reply($msg, "🍷 <b>" . h(displayName($from)) . " میز قمار ساخت!</b>\n\n"
                . quote("💰 شرط : " . nf($bet) . " هاپ پوینت\n👥 بازیکن‌ها : 1 نفر\n⌛️ منتظر بقیه...")
                . "\n(بعد از جمع شدن ۲ تا ۵ نفر، سازنده دکمه شروع رو بزنه)",
                kb([[
                    btn('پیوستن (' . nf($bet) . ' پوینت) 🍷', "gamble_join_{$tableId}", 'success'),
                    btn('شروع بازی! 🎯', "gamble_start_{$tableId}", 'warning'),
                ]]));
            return true;

        // ── ثبت آگهی مارکت ─────────────────────────────────────────────────
        case 'market_new':
            return marketNewStep($msg, $from, $text, $state);

        // ── ساخت قرعه‌کشی ──────────────────────────────────────────────────
        case 'lottery_new':
            return lotteryNewStep($msg, $from, $text, $state);

        // ── تنظیم جوایز دعوت ───────────────────────────────────────────────
        case 'ref_admin':
            if (!isAdmin($uid)) {
                clearState($uid);
                return false;
            }
            $amount = parseAmount($text);
            if ($amount < 0) {
                reply($msg, '❌ عدد معتبر وارد کن!');
                return true;
            }
            $field = (string) ($state['field'] ?? 'sender');
            setSetting($field === 'sender' ? 'referral_reward_sender' : 'referral_reward_joiner', $amount);
            clearState($uid);
            reply($msg, '✅ جایزه ' . ($field === 'sender' ? 'دعوت‌کننده' : 'دعوت‌شده') . ' به <b>' . nf($amount) . '</b> تغییر کرد!');
            return true;

        // ── اعتراض به شهردار ───────────────────────────────────────────────
        case 'mayor_protest':
            $groupId = (int) ($state['group_id'] ?? $chatId);
            if (mb_strlen($text) < 5) {
                reply($msg, '❌ دلیل اعتراض خیلی کوتاهه! حداقل ۵ کاراکتر.');
                return true;
            }
            if (mb_strlen($text) > 300) {
                reply($msg, '❌ دلیل اعتراض حداکثر ۳۰۰ کاراکتر!');
                return true;
            }
            clearState($uid);
            q('INSERT INTO mayor_protests (group_id, user_id, username, first_name, reason, status, created_at)
               VALUES (?,?,?,?,?,?,?)',
                [$groupId, $uid, (string) ($from['username'] ?? ''), displayName($from), $text, 'pending', nowIso()]);
            $pid = (int) db()->lastInsertId();
            reply($msg, '✅ اعتراضت ثبت شد و به گروه اعلام شد!');
            sendMsg($groupId, "✊ <b>اعتراض به شهردار!</b>\n\n"
                . quote("👤 معترض : " . h(displayName($from)) . "\n📝 دلیل : " . h($text))
                . "\n⚠️ اگر ۵ رأی موافق بیشتر از مخالف جمع بشه، شهردار برکنار میشه!",
                kb([[
                    btn('موافق (0) ✅', "mayor_pvote_{$pid}_yes", 'success'),
                    btn('مخالف (0) ❌', "mayor_pvote_{$pid}_no", 'danger'),
                ]]));
            return true;

        // ── انتصاب وزیر ────────────────────────────────────────────────────
        case 'lead_minister':
            if (!isLeader($uid)) {
                clearState($uid);
                return false;
            }
            $role = (string) ($state['role'] ?? '');
            if (!isset(MINISTER_ROLES[$role])) {
                clearState($uid);
                reply($msg, '❌ سمت نامعتبر!');
                return true;
            }
            $needle = ltrim(faToEn($text), '@');
            $target = one('SELECT * FROM users WHERE username=? COLLATE NOCASE OR CAST(user_id AS TEXT)=?', [$needle, $needle]);
            if (!$target) {
                reply($msg, '❌ کاربر پیدا نشد! آیدی عددی یا یوزرنیم درست بنویس.');
                return true;
            }
            clearState($uid);
            q('INSERT INTO ministers (role, user_id, username, first_name, appointed_at) VALUES (?,?,?,?,?)
               ON CONFLICT(role) DO UPDATE SET user_id=excluded.user_id, username=excluded.username,
                 first_name=excluded.first_name, appointed_at=excluded.appointed_at',
                [$role, (int) $target['user_id'], (string) $target['username'], (string) $target['first_name'], nowIso()]);
            logLeader($uid, 'minister', 'انتصاب ' . MINISTER_ROLES[$role]['name'] . ': ' . $target['first_name']);
            reply($msg, "👔 <b>" . h($target['first_name']) . " به عنوان " . h(MINISTER_ROLES[$role]['name']) . " منصوب شد!</b>\n\n"
                . quote('✨ ' . h(MINISTER_ROLES[$role]['desc'])));
            sendPrivate((int) $target['user_id'], "👔 <b>تبریک!</b>\n\n"
                . quote('شما به عنوان ' . h(MINISTER_ROLES[$role]['name']) . ' در کابینه دولت منصوب شدید!'));
            return true;

        // ── تخصیص بودجه ────────────────────────────────────────────────────
        case 'lead_budget':
            if (!isLeader($uid)) {
                clearState($uid);
                return false;
            }
            $amount = parseAmount($text);
            if ($amount <= 0) {
                reply($msg, '❌ مبلغ نامعتبره!');
                return true;
            }
            if (treasury() < $amount) {
                reply($msg, '❌ خزانه ملی کافی نیست! موجودی: ' . nf(treasury()));
                return true;
            }
            $gid = (int) ($state['group_id'] ?? 0);
            if ($gid === 0) {
                clearState($uid);
                reply($msg, '❌ شهر مقصد مشخص نیست!');
                return true;
            }
            clearState($uid);
            transact(static function () use ($amount, $gid, $uid) {
                changeTreasury(-$amount, 'تخصیص بودجه به شهر', $uid);
                q('UPDATE groups SET treasury = treasury + ? WHERE group_id=?', [$amount, $gid]);
                q('INSERT INTO city_budgets (group_id, amount, assigned_at) VALUES (?,?,?)
                   ON CONFLICT(group_id) DO UPDATE SET amount = amount + ?, assigned_at=excluded.assigned_at',
                    [$gid, $amount, nowIso(), $amount]);
            });
            logLeader($uid, 'budget', 'تخصیص ' . nf($amount) . ' بودجه');
            reply($msg, "💰 <b>بودجه تخصیص یافت!</b>\n\n" . quote(nf($amount) . ' هاپ پوینت به خزانه شهر واریز شد.'));
            sendMsg($gid, "💰 <b>بودجه ملی دریافت شد!</b>\n\n"
                . quote('👑 رهبر کشور ' . nf($amount) . ' هاپ پوینت به خزانه شهر شما اختصاص داد! 🎉'));
            return true;

        // ── افزودن به خزانه ملی ────────────────────────────────────────────
        case 'lead_treasury':
            if (!isAdmin($uid)) {
                clearState($uid);
                return false;
            }
            $amount = parseAmount($text);
            if ($amount === -1) {
                reply($msg, '❌ عدد معتبر وارد کن!');
                return true;
            }
            clearState($uid);
            changeTreasury((float) $amount, 'افزودن توسط ادمین', $uid);
            reply($msg, "💰 <b>خزانه ملی به‌روز شد!</b>\n\n" . quote('موجودی جدید : ' . nf(treasury())));
            return true;

        // ── تعیین رهبر توسط ادمین ──────────────────────────────────────────
        case 'lead_setleader':
            if (!isAdmin($uid)) {
                clearState($uid);
                return false;
            }
            $needle = ltrim(faToEn($text), '@');
            $target = one('SELECT * FROM users WHERE username=? COLLATE NOCASE OR CAST(user_id AS TEXT)=?', [$needle, $needle]);
            if (!$target) {
                reply($msg, '❌ کاربر پیدا نشد!');
                return true;
            }
            clearState($uid);
            setLeader((int) $target['user_id'], (string) $target['username'], (string) $target['first_name']);
            logLeader((int) $target['user_id'], 'appointed', 'انتصاب توسط ادمین');
            reply($msg, "👑 <b>" . h($target['first_name']) . " به عنوان رهبر کشور تعیین شد!</b>");
            broadcastGroups("👑 <b>رهبر جدید کشور!</b>\n\n"
                . quote(h($target['first_name']) . "\n⏳ مدت رهبری : " . LEADER_TERM_DAYS . ' روز'));
            return true;

        // ── ریست ───────────────────────────────────────────────────────────
        case 'reset':
            if (!isAdmin($uid)) {
                clearState($uid);
                return false;
            }
            if (($state['step'] ?? '') === 'threshold') {
                $threshold = parseAmount($text);
                if ($threshold < 0) {
                    reply($msg, '❌ عدد معتبر وارد کن!');
                    return true;
                }
                $adminList = implode(',', array_map('intval', ADMIN_IDS));
                $where = 'hop_points <= ?' . ($adminList !== '' ? " AND user_id NOT IN ($adminList)" : '');
                $count = (int) val("SELECT COUNT(*) FROM users WHERE $where", [$threshold], 0);
                $rows = all("SELECT first_name, hop_points, level FROM users WHERE $where ORDER BY hop_points DESC LIMIT 15", [$threshold]);
                $body = '';
                foreach ($rows as $i => $r) {
                    $body .= ($i + 1) . '. ' . h($r['first_name'] ?: 'ناشناس') . ' — ' . nf($r['hop_points']) . ' | سطح ' . (int) $r['level'] . "\n";
                }
                setState($uid, ['flow' => 'reset', 'step' => 'threshold_done', 'threshold' => $threshold]);
                reply($msg, "⚠️ <b>پیش‌نمایش ریست</b>\n\n"
                    . quote("💰 آستانه : " . nf($threshold) . "\n👥 تعداد کاربران : {$count}")
                    . "\n" . ($body !== '' ? quote(trim($body), true) : quote('هیچ کاربری در این محدوده نیست')),
                    kb([
                        [btn('تأیید و ادامه ✅', 'adm_reset_confirm_' . $threshold, 'danger')],
                        [btn('انصراف', 'adm_reset_cancel', 'primary')],
                    ]));
                return true;
            }
            if (($state['step'] ?? '') === 'init_points') {
                $initPoints = parseAmount($text);
                if ($initPoints < 0) {
                    reply($msg, '❌ عدد معتبر وارد کن!');
                    return true;
                }
                $threshold = (int) ($state['threshold'] ?? 0);
                clearState($uid);
                $affected = doResetUsers($threshold, $initPoints);
                reply($msg, "🔴 <b>ریست انجام شد!</b>\n\n"
                    . quote("👥 {$affected} کاربر ریست شدن\n💰 موجودی اولیه : " . nf($initPoints) . "\n⭐️ سطح اولیه : 1"));
                return true;
            }
            clearState($uid);
            return true;

        // ── افزودن کانال جوین اجباری ───────────────────────────────────────
        case 'fj_add':
            if (!isAdmin($uid)) {
                clearState($uid);
                return false;
            }
            $parts = array_map('trim', explode('|', $text));
            if (count($parts) < 3) {
                reply($msg, "❌ فرمت اشتباهه!\n\n" . quote('<code>یوزرنیم | نام نمایشی | لینک</code>'));
                return true;
            }
            $username = ltrim($parts[0], '@');
            if ($username === '' || !preg_match('/^[A-Za-z0-9_]{4,64}$/', $username)) {
                reply($msg, '❌ یوزرنیم کانال نامعتبره!');
                return true;
            }
            clearState($uid);
            q('INSERT INTO force_join_channels (username, name, link, added_at) VALUES (?,?,?,?)
               ON CONFLICT(username) DO UPDATE SET name=excluded.name, link=excluded.link',
                [$username, $parts[1], $parts[2], nowIso()]);
            reply($msg, '✅ کانال <b>@' . h($username) . '</b> اضافه شد!');
            return true;
    }

    clearState($uid);
    return false;
}

/** مراحل ثبت آگهی مارکت */
function marketNewStep(array $msg, array $from, string $text, array $state): bool
{
    $uid = (int) $from['id'];
    $step = (string) ($state['step'] ?? 'title');

    if ($step === 'title') {
        if (mb_strlen($text) > 50) {
            reply($msg, '❌ اسم محصول حداکثر ۵۰ کاراکتر!');
            return true;
        }
        $state['title'] = $text;
        $state['step'] = 'description';
        setState($uid, $state);
        reply($msg, "📝 <b>توضیح محصول:</b>\n" . quote("یه توضیح کوتاه بنویس که خریدار قبل از خرید بخونه.\n<i>(محتوای اصلی بعداً پرسیده میشه)</i>\n\nبرای لغو بنویس: لغو"));
        return true;
    }
    if ($step === 'description') {
        if (mb_strlen($text) > 200) {
            reply($msg, '❌ توضیح حداکثر ۲۰۰ کاراکتر!');
            return true;
        }
        $state['description'] = $text;
        $state['step'] = 'price';
        setState($uid, $state);
        reply($msg, "💰 <b>قیمت (هاپ پوینت):</b>\n" . quote("چند هاپ پوینت می‌خوای بفروشی؟\n\nبرای لغو بنویس: لغو"));
        return true;
    }
    if ($step === 'price') {
        $price = parseAmount($text);
        if ($price < 1) {
            reply($msg, '❌ قیمت باید یه عدد مثبت باشه!');
            return true;
        }
        $state['price'] = $price;
        $state['step'] = 'max_buyers';
        setState($uid, $state);
        reply($msg, "👥 <b>حداکثر تعداد خریدار:</b>\n" . quote("چند نفر می‌تونن این آگهی رو بخرن؟\n<i>(بعد از رسیدن به این تعداد، آگهی خودکار بسته میشه)</i>\n\nبرای لغو بنویس: لغو"));
        return true;
    }
    if ($step === 'max_buyers') {
        $maxB = (int) faToEn($text);
        if ($maxB < 1 || $maxB > 1000) {
            reply($msg, '❌ عدد بین ۱ تا ۱۰۰۰ وارد کن!');
            return true;
        }
        $state['max_buyers'] = $maxB;
        $state['step'] = 'content';
        setState($uid, $state);
        reply($msg, "📦 <b>محتوای آگهی:</b>\n" . quote("این چیزیه که بعد از خرید توی پیوی خریدار فرستاده میشه.\nمی‌تونه لینک، آیدی، متن یا هر چیزی باشه.\n\n⚠️ این محتوا فقط برای ادمین و خریدار قابل دیدنه.\n\nبرای لغو بنویس: لغو"));
        return true;
    }
    if ($step === 'content') {
        if (mb_strlen($text) > 2000) {
            reply($msg, '❌ محتوا حداکثر ۲۰۰۰ کاراکتر!');
            return true;
        }
        $state['content'] = $text;
        $state['step'] = 'confirm';
        setState($uid, $state);
        reply($msg, "✅ <b>بررسی آگهی:</b>\n\n"
            . quote(
                "🏷 نام : " . h($state['title']) . "\n"
                . "📝 توضیح : " . h($state['description']) . "\n"
                . "💰 قیمت : " . nf($state['price']) . " هاپ پوینت\n"
                . "👥 حداکثر خریدار : " . (int) $state['max_buyers'] . " نفر\n"
                . "📦 محتوا : " . h($state['content']), true)
            . "\nآیا تأیید می‌کنی؟",
            kb([
                [btn('ارسال برای تأیید ادمین ✅', 'mkt_submit_confirm', 'success')],
                [btn('لغو', 'mkt_submit_cancel', 'danger')],
            ]));
        return true;
    }
    return true;
}

/** مراحل ساخت قرعه‌کشی */
function lotteryNewStep(array $msg, array $from, string $text, array $state): bool
{
    $uid = (int) $from['id'];
    if (!isAdmin($uid)) {
        clearState($uid);
        return false;
    }
    $step = (string) ($state['step'] ?? 'title');

    if ($step === 'title') {
        if (mb_strlen($text) > 60) {
            reply($msg, '❌ اسم حداکثر ۶۰ کاراکتر!');
            return true;
        }
        $state['title'] = $text;
        $state['step'] = 'prize';
        setState($uid, $state);
        reply($msg, '✅ اسم : <b>' . h($text) . "</b>\n\n💰 حالا مبلغ جایزه رو بنویس (عدد، هاپ پوینت):");
        return true;
    }
    if ($step === 'prize') {
        $prize = parseAmount($text);
        if ($prize <= 0) {
            reply($msg, '❌ عدد معتبر وارد کن!');
            return true;
        }
        $state['prize'] = $prize;
        $state['step'] = 'winner_count';
        setState($uid, $state);
        reply($msg, '✅ جایزه : <b>' . nf($prize) . "</b> هاپ پوینت\n\n🏆 چند نفر برنده بشن؟ (عدد بنویس):");
        return true;
    }
    if ($step === 'winner_count') {
        $wc = (int) faToEn($text);
        if ($wc <= 0 || $wc > 1000) {
            reply($msg, '❌ عدد معتبر (۱ تا ۱۰۰۰) وارد کن!');
            return true;
        }
        clearState($uid);
        $lotId = newId();
        q('INSERT INTO lotteries (lottery_id, title, prize, winner_count, state, created_by, created_at)
           VALUES (?,?,?,?,?,?,?)',
            [$lotId, $state['title'], (int) $state['prize'], $wc, 'open', $uid, nowIso()]);

        $lot = getLottery($lotId);
        $announceText = lotteryAnnounceText($lot, []);
        $announceKb = kb([[btn('شرکت در قرعه‌کشی (0 نفر) ✋', "lot_join_{$lotId}", 'success')]]);
        $sent = broadcastGroups($announceText, $announceKb);

        reply($msg, "🎉 <b>قرعه‌کشی ساخته شد!</b>\n\n"
            . quote(
                "🎰 اسم : " . h($state['title']) . "\n"
                . "🎁 جایزه : " . nf($state['prize']) . " هاپ پوینت\n"
                . "🏆 تعداد برنده : {$wc} نفر\n"
                . "📢 ارسال به {$sent} گروه\n"
                . "🔑 کد : <code>{$lotId}</code>"));
        return true;
    }
    return true;
}

// ═══════════════════════════════════════════════════════════════════════════
//  🧭  روتر پیام‌ها
// ═══════════════════════════════════════════════════════════════════════════

/** دستور /start */
function cmdStart(array $msg, array $from, string $text): void
{
    $chat = $msg['chat'];
    $uid = (int) $from['id'];

    if (($chat['type'] ?? '') === 'private') {
        ensureUser($from);

        // ثبت دعوت‌کننده
        $parts = preg_split('/\s+/u', trim($text)) ?: [];
        if (isset($parts[1]) && str_starts_with($parts[1], 'ref_')) {
            $inviterId = (int) substr($parts[1], 4);
            if ($inviterId > 0 && $inviterId !== $uid) {
                q('INSERT INTO referrals (user_id, inviter_id, rewarded, joined_at) VALUES (?,?,0,?)
                   ON CONFLICT(user_id) DO NOTHING', [$uid, $inviterId, nowIso()]);
            }
        }

        sendMsg($chat['id'],
            "🐕 سلام " . h(displayName($from)) . " عزیز!\n\n"
            . quote(
                "من ربات هاپی هستم 🦴\n\n"
                . "📌 دستورات اصلی فقط <b>توی گروه</b> کار می‌کنن — منو به گروهت اضافه کن!\n\n"
                . "🆕 قابلیت جدید: <b>چالش دوز</b>\n"
                . "توی گروه بنویس <code>چالش 500 میو</code> تا یه چالش باز بشه!"
            )
            . "\n👇 از دکمه‌های زیر استفاده کن:",
            rkb([
                ['🎁 دعوت دوستان', '🐾 هاپوهام'],
                ['🛒 مارکت', '📖 راهنما'],
                ['📊 لیدربرد'],
            ]), $msg);
        return;
    }

    ensureUser($from);
    ensureGroup($chat);
    reply($msg,
        "🐕 <b>ربات هاپی</b> اینجاست!\n\n"
        . quote(
            "🦴 توی گروه بنویس <b>هاپ</b> تا هاپ پوینت بگیری!\n"
            . "هر ۵ دقیقه یه بار می‌تونی هاپ کنی 🐾\n\n"
            . "🎯 <b>چالش دوز</b> — بنویس <code>چالش 500 میو</code>"
        )
        . "\n📖 برای دیدن همه دستورات بنویس <b>راهنما</b>",
        kb([[btn('راهنمای کامل 📖', 'chal_help', 'primary')]]));
}

/** تشخیص و اجرای دستورات ادمین متنی */
function tryAdminTextCommand(array $msg, array $from, string $text): bool
{
    $uid = (int) $from['id'];

    // افزودن/حذف ادمین و حذف کاربر — فقط ادمین اصلی
    if (in_array($text, ['افزودن ادمین', 'اضافه کردن ادمین'], true)) {
        if (!isAdmin($uid)) { return false; }
        if (!isset($msg['reply_to_message']['from'])) {
            reply($msg, '❌ باید روی پیام فرد موردنظر ریپلای کنی!');
            return true;
        }
        $t = $msg['reply_to_message']['from'];
        $tid = (int) $t['id'];
        if (in_array($tid, ADMIN_IDS, true)) {
            reply($msg, '⚠️ این کاربر ادمین اصلیه!');
            return true;
        }
        if (isSubAdmin($tid)) {
            reply($msg, '⚠️ ' . h(displayName($t)) . ' قبلاً ادمین شده!');
            return true;
        }
        ensureUser($t);
        q('INSERT INTO sub_admins (user_id, username, first_name, added_by, added_at) VALUES (?,?,?,?,?)
           ON CONFLICT(user_id) DO NOTHING',
            [$tid, (string) ($t['username'] ?? ''), displayName($t), $uid, nowIso()]);
        reply($msg, '✅ <b>' . h(displayName($t)) . "</b> به عنوان ادمین اضافه شد!\n\n"
            . quote("🔑 دسترسی‌ها:\n• افزایش/کاهش پوینت\n• افزایش/کاهش لول"));
        return true;
    }

    if ($text === 'حذف ادمین') {
        if (!isAdmin($uid)) { return false; }
        if (!isset($msg['reply_to_message']['from'])) {
            reply($msg, '❌ باید روی پیام فرد موردنظر ریپلای کنی!');
            return true;
        }
        $t = $msg['reply_to_message']['from'];
        $tid = (int) $t['id'];
        if (in_array($tid, ADMIN_IDS, true)) {
            reply($msg, '❌ نمیشه ادمین اصلی رو حذف کرد!');
            return true;
        }
        if (!isSubAdmin($tid)) {
            reply($msg, '⚠️ ' . h(displayName($t)) . ' ادمین نیست!');
            return true;
        }
        q('DELETE FROM sub_admins WHERE user_id=?', [$tid]);
        reply($msg, '✅ دسترسی ادمین <b>' . h(displayName($t)) . '</b> حذف شد!');
        return true;
    }

    if ($text === 'حذف کاربر') {
        if (!isAdmin($uid)) { return false; }
        if (!isset($msg['reply_to_message']['from'])) {
            reply($msg, '❌ باید روی پیام کاربر موردنظر ریپلای کنی!');
            return true;
        }
        $t = $msg['reply_to_message']['from'];
        $tid = (int) $t['id'];
        if (in_array($tid, ADMIN_IDS, true)) {
            reply($msg, '❌ نمیشه ادمین اصلی رو حذف کرد!');
            return true;
        }
        $tu = getUser($tid);
        if (!$tu) {
            reply($msg, '❌ این کاربر در دیتابیس نیست!');
            return true;
        }
        reply($msg, "⚠️ <b>آیا مطمئنی؟</b>\n\n"
            . quote(
                "👤 کاربر : " . h($tu['first_name']) . "\n"
                . "🪪 آیدی : <code>{$tid}</code>\n"
                . "💰 موجودی : " . nf($tu['hop_points']) . "\n"
                . "⭐️ سطح : " . (int) $tu['level'])
            . "\nتمام اطلاعات این کاربر (پوینت، سگ، قلاب، بانک و ...) حذف میشه!",
            kb([[
                btn('بله، حذف کن ✅', "confirm_delete_user_{$tid}", 'danger'),
                btn('انصراف', 'cancel', 'primary'),
            ]]));
        return true;
    }

    // افزایش/کاهش پوینت و لول
    if (preg_match('/^(افزایش|کاهش)\s+(پوینت|لول|سطح)\s*(.*)$/u', $text, $m)) {
        if (!isAnyAdmin($uid)) { return false; }
        if (!isset($msg['reply_to_message']['from'])) {
            reply($msg, "❌ باید روی پیام فرد موردنظر ریپلای کنی!\n\n"
                . quote("📋 دستورات:\n• افزایش پوینت [عدد]\n• کاهش پوینت [عدد]\n• افزایش لول [عدد]\n• کاهش لول [عدد]"));
            return true;
        }
        $t = $msg['reply_to_message']['from'];
        $tid = (int) $t['id'];
        $tu = getUser($tid);
        if (!$tu) {
            reply($msg, '❌ این کاربر هنوز ثبت‌نام نکرده!');
            return true;
        }
        $amount = parseAmount($m[3]);
        if ($amount <= 0) {
            reply($msg, '❌ عدد رو هم بنویس! مثلاً: افزایش پوینت 500');
            return true;
        }

        $isIncrease = $m[1] === 'افزایش';
        $isPoints = $m[2] === 'پوینت';

        if ($isPoints) {
            if ($isIncrease) {
                addPoints($tid, $amount);
                $newVal = (float) $tu['hop_points'] + $amount;
                reply($msg, '✅ <b>' . nf($amount) . '</b> هاپ پوینت به <b>' . h($tu['first_name']) . "</b> اضافه شد!\n"
                    . quote('💰 موجودی جدید : ' . nf($newVal)));
            } else {
                deductPoints($tid, $amount);
                $newVal = max(0, (float) $tu['hop_points'] - $amount);
                reply($msg, '✅ <b>' . nf($amount) . '</b> هاپ پوینت از <b>' . h($tu['first_name']) . "</b> کم شد!\n"
                    . quote('💰 موجودی جدید : ' . nf($newVal)));
            }
            return true;
        }

        $oldLvl = (int) $tu['level'];
        $newLvl = $isIncrease ? min(1000, $oldLvl + $amount) : max(1, $oldLvl - $amount);
        $newHops = $newLvl >= 2 ? (levelThresholds()[$newLvl - 2] ?? 0) : 0;
        q('UPDATE users SET level=?, total_hops=? WHERE user_id=?', [$newLvl, $newHops, $tid]);
        reply($msg, ($isIncrease ? '⭐️' : '⬇️') . ' لول <b>' . h($tu['first_name']) . "</b> از {$oldLvl} به <b>{$newLvl}</b> رسید!");
        return true;
    }

    return false;
}

/** پردازش پیام گروه */
function handleGroupMessage(array $msg, array $from): void
{
    $uid = (int) $from['id'];
    $chatId = (int) $msg['chat']['id'];
    $raw = (string) ($msg['text'] ?? '');
    $text = normalizeText($raw);

    if ($text === '') {
        return;
    }

    // جوین اجباری
    if (forceJoinBlocked($msg, $from)) {
        return;
    }

    // منطق «میو»
    if (handleMeow($msg, $from)) {
        return;
    }

    // ضد اسپم (ادمین‌ها مستثنا)
    if (!isAdmin($uid) && !isJailed($uid)) {
        $dur = checkSpam($uid, $chatId);
        if ($dur > 0) {
            reply($msg, '🚔 <b>' . h(displayName($from)) . ' به خاطر اسپم ' . intdiv($dur, 60) . ' دقیقه زندانی شد!</b>');
            return;
        }
    }

    // ورودی‌های مکالمه‌ای — متن خام پاس داده می‌شود
    if (handleStateInput($msg, $from, $raw)) {
        return;
    }

    // دستورات ادمین متنی
    if (tryAdminTextCommand($msg, $from, $text)) {
        return;
    }

    // 🆕 چالش دوز — «چالش 500 میو»
    if (preg_match('/^چالش\s*(دوز)?\s*([0-9۰-۹٠-٩][0-9۰-۹٠-٩,،_.]*\s*(?:k|K|m|M|کی|کا|میل)?)\s*(میو|میو پوینت|پوینت|هاپ|هاپ پوینت)?$/u', $text, $m)) {
        $bet = parseAmount($m[2]);
        if ($bet <= 0) {
            cmdChallengeHelp($msg);
            return;
        }
        cmdCreateChallenge($msg, $from, $bet);
        return;
    }
    if (in_array($text, ['چالش', 'چالش دوز', 'دوز', 'راهنمای چالش'], true)) {
        cmdChallengeHelp($msg);
        return;
    }
    if (in_array($text, ['برترین چالش', 'برترین چالش دوز', 'تاپ چالش'], true)) {
        cmdChallengeTop($msg);
        return;
    }

    // دستورات ممنوع در زندان
    $jailBlocked = ['هاپ', 'هاپو', 'hop', 'سگ', 'dog', 'قلاب', 'hook', 'استخوان', 'bone',
                    'بانک', 'bank', 'قاچاق', 'کازینو', 'casino', 'تاس', 'گردونه', 'قمار',
                    'کارخونه', 'کارخانه', 'factory', 'بازی', 'games'];
    if (in_array(mb_strtolower($text), array_map('mb_strtolower', $jailBlocked), true) && !isAdmin($uid)) {
        [$jailed, $jailRow] = jailStatus($uid);
        if ($jailed) {
            reply($msg, "⛓️ تو زندانی هستی! نمی‌تونی این کار رو بکنی.\n"
                . quote('⌛️ ' . fmtDur(secsLeft($jailRow['release_at'])) . " تا آزادی\nبنویس <b>زندان</b> برای گزینه‌های آزادی."));
            return;
        }
    }

    // دستورات با پارامتر
    if (str_starts_with($text, 'اهدا')) {
        cmdDonate($msg, $from, $text);
        return;
    }
    if (str_starts_with($text, 'انتقال')) {
        cmdTransfer($msg, $from, $text);
        return;
    }
    if (str_starts_with($text, 'زندان') || str_starts_with($text, 'jail')) {
        cmdJail($msg, $from, $text);
        return;
    }

    // دستورات ساده
    $lower = mb_strtolower($text);
    switch (true) {
        case in_array($lower, ['هاپ', 'هاپو', 'hop'], true):
            cmdHop($msg, $from); return;
        case in_array($lower, ['سگ', 'dog'], true):
            cmdDog($msg, $from); return;
        case in_array($lower, ['قلاب', 'hook'], true):
            cmdHook($msg, $from); return;
        case in_array($lower, ['استخوان', 'bone', 'صید', 'ماهیگیری'], true):
            cmdCast($msg, $from); return;
        case in_array($lower, ['بانک', 'bank'], true):
            cmdBank($msg, $from); return;
        case in_array($lower, ['بازی', 'games', 'بازی ها', 'بازی‌ها'], true):
            cmdGamesMenu($msg, $from); return;
        case $lower === 'قاچاق':
            cmdContraband($msg, $from); return;
        case in_array($lower, ['کازینو', 'casino'], true):
            cmdCasino($msg, $from); return;
        case $lower === 'تاس':
            cmdDice($msg, $from); return;
        case $lower === 'گردونه':
            cmdWheel($msg, $from); return;
        case $lower === 'قمار':
            cmdGamble($msg, $from); return;
        case in_array($lower, ['شهر', 'city'], true):
            cmdCity($msg); return;
        case in_array($lower, ['کارخونه', 'کارخانه', 'factory'], true):
            cmdFactory($msg, $from); return;
        case in_array($lower, ['مارکت', 'market', 'بازار'], true):
            cmdMarket($msg, $from); return;
        case in_array($lower, ['شهرداری', 'شهردار'], true):
            cmdMayor($msg, $from); return;
        case in_array($lower, ['بحران', 'وضعیت بحران', 'crisis'], true):
            cmdCrisisStatus($msg); return;
        case in_array($lower, ['رهبر', 'رهبری', 'leader'], true):
            cmdLeader($msg, $from); return;
        case in_array($lower, ['نامزد رهبر', 'نامزد رهبری'], true):
            cmdLeaderJoin($msg, $from); return;
        case in_array($lower, ['رای رهبر', 'رأی رهبر', 'vote رهبر'], true):
            cmdLeaderVote($msg, $from); return;
        case in_array($lower, ['برترین', 'لیدربرد', 'top', 'رتبه بندی'], true):
            cmdTop($msg); return;
        case in_array($lower, ['راهنما', 'help', 'دستورات'], true):
            cmdHelp($msg); return;
        case in_array($lower, ['هاپوهام', 'هاپو هام', 'هاپ هام', 'پروفایل', 'profile', 'hapoham'], true):
            sendProfile($msg, $from); return;
        case in_array($lower, ['هاپ هاش', 'هاپو هاش', 'هاپوهاش', 'پروفایلش', 'hapohash'], true):
            cmdProfileOther($msg); return;
        case in_array($lower, ['پنل', 'قرعه کشی', 'قرعه‌کشی'], true):
            if (isAdmin($uid)) {
                [$t2, $k2] = lotteryAdminPanelData();
                reply($msg, $t2, $k2);
            }
            return;
        case $lower === 'مارکت ادمین':
            if (isAdmin($uid)) {
                [$t2, $k2] = marketAdminPanelData();
                reply($msg, $t2, $k2);
            }
            return;
        case in_array($lower, ['پنل دعوت', 'دعوت ادمین'], true):
            if (isAdmin($uid)) {
                [$t2, $k2] = referralAdminPanelData();
                reply($msg, $t2, $k2);
            }
            return;
        case in_array($lower, ['دعوت', 'دعوت دوستان', 'invite'], true):
            reply($msg, '🔗 لینک دعوت رو توی پیوی بهت میدم! ربات رو توی پیوی باز کن.',
                kb([[urlBtn('باز کردن پیوی 📩', 'https://t.me/' . BOT_USERNAME . '?start=ref_' . $uid, 'success')]]));
            return;
    }
}

/** پردازش پیام خصوصی */
function handlePrivateMessage(array $msg, array $from): void
{
    $uid = (int) $from['id'];
    $text = normalizeText((string) ($msg['text'] ?? ''));
    if ($text === '') {
        return;
    }

    ensureUser($from);

    if (handleStateInput($msg, $from, (string) ($msg['text'] ?? ''))) {
        return;
    }

    $lower = mb_strtolower($text);

    if (in_array($lower, ['ریست', 'reset', '🔴 ریست'], true) && isAdmin($uid)) {
        [$t2, $k2] = resetPanelData();
        reply($msg, $t2, $k2);
        return;
    }
    if (in_array($lower, ['جوین ادمین', 'مدیریت جوین', 'force join'], true) && isAdmin($uid)) {
        [$t2, $k2] = forceJoinPanelData();
        reply($msg, $t2, $k2);
        return;
    }
    if ($lower === 'مارکت ادمین' && isAdmin($uid)) {
        [$t2, $k2] = marketAdminPanelData();
        reply($msg, $t2, $k2);
        return;
    }
    if (in_array($lower, ['پنل دعوت', 'دعوت ادمین'], true) && isAdmin($uid)) {
        [$t2, $k2] = referralAdminPanelData();
        reply($msg, $t2, $k2);
        return;
    }
    if (in_array($lower, ['پنل', 'قرعه کشی', 'قرعه‌کشی'], true) && isAdmin($uid)) {
        [$t2, $k2] = lotteryAdminPanelData();
        reply($msg, $t2, $k2);
        return;
    }
    if (in_array($lower, ['مارکت', 'market', '🛒 مارکت', 'بازار'], true)) {
        cmdMarket($msg, $from);
        return;
    }
    if (in_array($lower, ['دعوت دوستان', '🎁 دعوت دوستان', 'دعوت', 'invite'], true)) {
        if (!referralEnabled()) {
            reply($msg, '❌ سیستم دعوت دوستان فعلاً غیرفعاله.');
            return;
        }
        [$t2, $k2] = referralPanelData($uid);
        reply($msg, $t2, $k2);
        return;
    }
    if (in_array($lower, ['هاپوهام', 'هاپو هام', '🐾 هاپوهام', 'پروفایل'], true)) {
        sendProfile($msg, $from);
        return;
    }
    if (in_array($lower, ['راهنما', '📖 راهنما', 'help'], true)) {
        cmdHelp($msg);
        return;
    }
    if (in_array($lower, ['لیدربرد', '📊 لیدربرد', 'برترین', 'top'], true)) {
        cmdTop($msg);
        return;
    }
    if (in_array($lower, ['رهبر', 'رهبری', 'leader'], true)) {
        cmdLeader($msg, $from);
        return;
    }
    if (in_array($lower, ['چالش', 'چالش دوز', 'دوز'], true)) {
        cmdChallengeHelp($msg);
        return;
    }

    reply($msg, "🐾 دستورات اصلی فقط توی گروه کار می‌کنن!\n\n"
        . quote("🎯 قابلیت جدید: توی گروه بنویس <code>چالش 500 میو</code>")
        . "\nاز دکمه‌های زیر استفاده کن 👇");
}

/** ثبت‌نام کاندیدای رهبری با دستور متنی */
function cmdLeaderJoin(array $msg, array $from): void
{
    $election = activeLeaderElection();
    if (!$election || $election['status'] !== 'candidacy') {
        reply($msg, '❌ الان انتخابات رهبری در مرحله ثبت‌نام نیست!');
        return;
    }
    $uid = (int) $from['id'];
    $u = ensureUser($from);
    if ((int) $u['level'] < 5) {
        reply($msg, '🔒 برای کاندیداتوری رهبری باید حداقل سطح ۵ باشی!');
        return;
    }
    if (val('SELECT 1 FROM leader_candidates WHERE election_id=? AND user_id=?', [(int) $election['id'], $uid]) !== null) {
        reply($msg, '✅ قبلاً ثبت‌نام کردی!');
        return;
    }
    q('INSERT INTO leader_candidates (election_id, user_id, username, first_name, joined_at) VALUES (?,?,?,?,?)
       ON CONFLICT(election_id, user_id) DO NOTHING',
        [(int) $election['id'], $uid, (string) ($from['username'] ?? ''), displayName($from), nowIso()]);
    $n = (int) val('SELECT COUNT(*) FROM leader_candidates WHERE election_id=?', [(int) $election['id']], 0);
    reply($msg, "✋ <b>" . h(displayName($from)) . " کاندیدای رهبری شد!</b>\n\n" . quote("👥 تعداد کاندیداها : {$n}"));
}

/** رأی‌دهی رهبری با دستور متنی */
function cmdLeaderVote(array $msg, array $from): void
{
    $election = activeLeaderElection();
    if (!$election || $election['status'] !== 'voting') {
        reply($msg, '❌ الان رأی‌گیری رهبری فعال نیست!');
        return;
    }
    $eid = (int) $election['id'];
    ensureUser($from);
    $cands = all('SELECT * FROM leader_candidates WHERE election_id=?', [$eid]);
    if ($cands === []) {
        reply($msg, '❌ هیچ کاندیدایی ثبت‌نام نکرده!');
        return;
    }
    $counts = [];
    foreach (all('SELECT candidate_id, COUNT(*) AS n FROM leader_votes WHERE election_id=? GROUP BY candidate_id', [$eid]) as $r) {
        $counts[(int) $r['candidate_id']] = (int) $r['n'];
    }
    $rows = [];
    $body = '';
    foreach ($cands as $c) {
        $v = $counts[(int) $c['user_id']] ?? 0;
        $body .= "👤 " . h($c['first_name']) . " — {$v} رأی\n";
        $rows[] = [btn('رأی به ' . mb_substr($c['first_name'], 0, 20) . " ({$v})", 'lead_elec_cast_' . $eid . '_' . (int) $c['user_id'], 'success')];
    }
    reply($msg, "🗳 <b>رأی‌گیری رهبری کشور</b>\n\n" . quote(trim($body), true), kb($rows));
}

// ═══════════════════════════════════════════════════════════════════════════
//  ⏰  کارهای دوره‌ای
// ═══════════════════════════════════════════════════════════════════════════

/** اجرای کارهای دوره‌ای با محدودیت زمانی */
function runCronJobs(bool $force = false): void
{
    $now = time();

    // هر ۲۰ ثانیه — چالش‌ها و رأی‌گیری میو
    if ($force || $now - (int) meta('cron_fast', '0') >= 20) {
        setMeta('cron_fast', $now);
        try {
            challengeCron();
            meowCron();
            expireOldCrises();
        } catch (Throwable $e) {
            logMsg('⚠️ cron_fast: ' . $e->getMessage());
        }
    }

    // هر ۵ دقیقه — پروژه‌ها، رهبری، بحران‌ها
    if ($force || $now - (int) meta('cron_slow', '0') >= 300) {
        setMeta('cron_slow', $now);
        try {
            completeProjectsCron();
            leaderCron();
            notifyExpiredCrises();
            cleanupStaleData();
        } catch (Throwable $e) {
            logMsg('⚠️ cron_slow: ' . $e->getMessage());
        }
    }

    // روزانه — مالیات ثروتمندان
    $today = date('Y-m-d');
    if (meta('cron_daily', '') !== $today && (int) date('G') >= 3) {
        setMeta('cron_daily', $today);
        try {
            dailyEconomyJobs();
        } catch (Throwable $e) {
            logMsg('⚠️ cron_daily: ' . $e->getMessage());
        }
    }
}

/** اطلاع‌رسانی بحران‌های بی‌توجه‌مانده */
function notifyExpiredCrises(): void
{
    $rows = all("SELECT * FROM city_crises WHERE status='ignored' AND decision='timeout' AND resolved_at IS NULL");
    foreach ($rows as $row) {
        q('UPDATE city_crises SET resolved_at=? WHERE id=?', [nowIso(), (int) $row['id']]);
        $c = CRISIS_TYPES[$row['crisis_type']] ?? null;
        sendMsg((int) $row['group_id'], "⚠️ <b>بحران بدون مدیریت ماند!</b>\n\n"
            . quote(
                h($c['name'] ?? $row['crisis_type']) . "\n\n"
                . "شهردار تصمیم نگرفت و شهر جریمه شد:\n❌ " . h($c['penalty'] ?? '—')));
        changeMayorPopularity((int) $row['group_id'], -5);
    }
}

/** مالیات روزانه از ثروتمندان به خزانه ملی */
function dailyEconomyJobs(): void
{
    $rich = all('SELECT user_id, hop_points FROM users WHERE hop_points > ?', [RICH_TAX_THRESHOLD]);
    if ($rich === []) {
        return;
    }
    $totalTax = 0.0;
    transact(static function () use ($rich, &$totalTax) {
        foreach ($rich as $u) {
            $tax = (float) $u['hop_points'] * RICH_TAX_RATE;
            q('UPDATE users SET hop_points = MAX(0, hop_points - ?) WHERE user_id=?', [$tax, (int) $u['user_id']]);
            $totalTax += $tax;
        }
    });
    if ($totalTax > 0) {
        changeTreasury($totalTax, 'مالیات روزانه ثروتمندان');
        logMsg('💰 مالیات روزانه: ' . nf($totalTax) . ' از ' . count($rich) . ' کاربر');
    }
}

/** پاک‌سازی داده‌های قدیمی */
function cleanupStaleData(): void
{
    // وضعیت‌های مکالمه‌ای فراموش‌شده (بیش از ۱ ساعت)
    q('DELETE FROM user_states WHERE updated_at < ?', [date('Y-m-d H:i:s', time() - 3600)]);
    // میزهای بازی رهاشده (بیش از ۱ روز)
    q("DELETE FROM game_tables WHERE state='done' AND created_at < ?", [date('Y-m-d H:i:s', time() - 86400)]);
    // میزهای منتظرِ خیلی قدیمی — بازگشت پوینت
    foreach (all("SELECT * FROM game_tables WHERE state='waiting' AND created_at < ?",
                 [date('Y-m-d H:i:s', time() - 3600)]) as $t) {
        addPoints((int) $t['creator_id'], (int) $t['bet']);
        if ($t['game_type'] === 'gamble') {
            foreach (array_filter(explode(',', (string) $t['board'])) as $p) {
                $pid = (int) explode(':', $p)[0];
                if ($pid !== (int) $t['creator_id']) {
                    addPoints($pid, (int) explode(':', $p)[1]);
                }
            }
        }
        q('DELETE FROM game_tables WHERE table_id=?', [$t['table_id']]);
    }
    // استخوان‌های تصمیم‌نگرفته منقضی
    q('DELETE FROM pending_bones WHERE caught_at < ?', [date('Y-m-d H:i:s', time() - BONE_SELL_TIME - 60)]);
    // سگ‌های خیابونی رهاشده (بیش از ۲ ساعت)
    q('DELETE FROM stray_dogs WHERE appeared_at < ?', [date('Y-m-d H:i:s', time() - 7200)]);
}

// ═══════════════════════════════════════════════════════════════════════════
//  🚦  پردازش آپدیت
// ═══════════════════════════════════════════════════════════════════════════

function processUpdate(array $update): void
{
    try {
        if (isset($update['callback_query'])) {
            handleCallback($update['callback_query']);
            return;
        }

        $msg = $update['message'] ?? $update['edited_message'] ?? null;
        if ($msg === null || !isset($msg['from'], $msg['chat'])) {
            return;
        }
        if (!empty($msg['from']['is_bot'])) {
            return;
        }

        $from = $msg['from'];
        $chatType = (string) ($msg['chat']['type'] ?? '');
        $text = normalizeText((string) ($msg['text'] ?? ''));

        // دستورات اسلش
        if ($text !== '' && str_starts_with($text, '/')) {
            $cmd = mb_strtolower(preg_replace('/@\S+/', '', explode(' ', $text)[0]) ?? '');
            switch ($cmd) {
                case '/start':
                    cmdStart($msg, $from, $text);
                    return;
                case '/help':
                    ensureUser($from);
                    cmdHelp($msg);
                    return;
                case '/profile':
                    ensureUser($from);
                    sendProfile($msg, $from);
                    return;
                case '/top':
                    ensureUser($from);
                    cmdTop($msg);
                    return;
                case '/challenge':
                case '/doz':
                    ensureUser($from);
                    cmdChallengeHelp($msg);
                    return;
                case '/invite':
                case '/ref':
                    ensureUser($from);
                    if (!referralEnabled()) {
                        reply($msg, '❌ سیستم دعوت دوستان فعلاً غیرفعاله.');
                        return;
                    }
                    if ($chatType !== 'private') {
                        reply($msg, '🔗 لینک دعوت رو توی پیوی بهت میدم!');
                        return;
                    }
                    [$t2, $k2] = referralPanelData((int) $from['id']);
                    reply($msg, $t2, $k2);
                    return;
                case '/lottery':
                case '/panel':
                    ensureUser($from);
                    if (isAdmin((int) $from['id'])) {
                        [$t2, $k2] = lotteryAdminPanelData();
                        reply($msg, $t2, $k2);
                    }
                    return;
                case '/leaderjoin':
                    ensureUser($from);
                    cmdLeaderJoin($msg, $from);
                    return;
                case '/leadervote':
                    ensureUser($from);
                    cmdLeaderVote($msg, $from);
                    return;
                case '/leader':
                    ensureUser($from);
                    cmdLeader($msg, $from);
                    return;
                default:
                    return;
            }
        }

        if ($chatType === 'private') {
            handlePrivateMessage($msg, $from);
        } elseif ($chatType === 'group' || $chatType === 'supergroup') {
            handleGroupMessage($msg, $from);
        }
    } catch (Throwable $e) {
        logMsg('❌ خطا در پردازش آپدیت: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  ⚙️  راه‌اندازی
// ═══════════════════════════════════════════════════════════════════════════

function setBotCommands(): void
{
    tg('setMyCommands', ['commands' => [
        ['command' => 'start',     'description' => 'شروع کار با ربات'],
        ['command' => 'help',      'description' => 'راهنمای دستورات'],
        ['command' => 'challenge', 'description' => 'راهنمای چالش دوز 🎯'],
        ['command' => 'profile',   'description' => 'پروفایل من'],
        ['command' => 'top',       'description' => 'برترین‌ها'],
        ['command' => 'invite',    'description' => 'دعوت دوستان'],
        ['command' => 'leader',    'description' => 'پنل رهبری کشور'],
    ]]);
}

/** حالت وبهوک */
function runWebhook(): void
{
    initDb();
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        http_response_code(200);
        echo 'ok';
        return;
    }
    $update = json_decode($raw, true);
    if (is_array($update)) {
        processUpdate($update);
    }
    runCronJobs();
    http_response_code(200);
    echo 'ok';
}

/** حالت پولینگ */
function runPolling(): void
{
    initDb();
    setBotCommands();

    $me = tg('getMe');
    if ($me === null) {
        logMsg('❌ اتصال به تلگرام برقرار نشد! توکن را بررسی کنید.');
        exit(1);
    }
    logMsg('🐕 ربات هاپی شروع به کار کرد! (@' . ($me['username'] ?? '?') . ')');

    $offset = (int) meta('poll_offset', '0');

    while (true) {
        $updates = tg('getUpdates', [
            'offset'          => $offset,
            'timeout'         => 25,
            'allowed_updates' => ['message', 'edited_message', 'callback_query'],
        ], 40);

        if (is_array($updates)) {
            foreach ($updates as $update) {
                $offset = (int) $update['update_id'] + 1;
                setMeta('poll_offset', $offset);
                processUpdate($update);
            }
        } else {
            usleep(500000);
        }

        runCronJobs();
    }
}

/** تنظیم وبهوک */
function setWebhookUrl(string $url): void
{
    $res = tg('setWebhook', [
        'url'             => $url,
        'allowed_updates' => ['message', 'edited_message', 'callback_query'],
        'drop_pending_updates' => true,
    ]);
    if ($res !== null) {
        logMsg("✅ وبهوک تنظیم شد: $url");
        setBotCommands();
    } else {
        logMsg('❌ تنظیم وبهوک ناموفق بود.');
    }
}

// ═══════════════════════════════════════════════════════════════════════════
//  🧪  حالت تست (Dry Run)
// ═══════════════════════════════════════════════════════════════════════════

/** شبیه‌ساز پاسخ تلگرام بدون تماس شبکه — فقط وقتی HAPPY_BOT_DRY_RUN تعریف شده باشد */
function dryRunTg(string $method, array $params)
{
    global $DRY_CALLS;
    if (!is_array($DRY_CALLS)) {
        $DRY_CALLS = [];
    }
    $DRY_CALLS[] = ['method' => $method, 'params' => $params];

    static $msgId = 1000;
    switch ($method) {
        case 'sendMessage':
        case 'sendPhoto':
            return [
                'message_id' => ++$msgId,
                'chat'       => ['id' => $params['chat_id'] ?? 0, 'type' => 'supergroup'],
                'text'       => $params['text'] ?? ($params['caption'] ?? ''),
            ];
        case 'sendDice':
            $emoji = $params['emoji'] ?? '🎲';
            return [
                'message_id' => ++$msgId,
                'dice' => ['emoji' => $emoji, 'value' => $emoji === '🎰' ? mt_rand(1, 64) : mt_rand(1, 6)],
            ];
        case 'getMe':
            return ['id' => 1, 'is_bot' => true, 'username' => 'test_bot'];
        case 'getUserProfilePhotos':
            return ['total_count' => 0, 'photos' => []];
        case 'getChatMember':
            return ['status' => 'member'];
        default:
            return true;
    }
}

/** آخرین متن ارسال‌شده در حالت تست */
function dryLastText(): string
{
    global $DRY_CALLS;
    if (!is_array($DRY_CALLS)) {
        return '';
    }
    for ($i = count($DRY_CALLS) - 1; $i >= 0; $i--) {
        $c = $DRY_CALLS[$i];
        if (in_array($c['method'], ['sendMessage', 'editMessageText', 'answerCallbackQuery'], true)) {
            return (string) ($c['params']['text'] ?? '');
        }
    }
    return '';
}

function dryReset(): void
{
    global $DRY_CALLS;
    $DRY_CALLS = [];
}

// ═══════════════════════════════════════════════════════════════════════════
//  ▶️  نقطه شروع
// ═══════════════════════════════════════════════════════════════════════════

if (defined('HAPPY_BOT_NO_RUN')) {
    return;
}

$tokenMissing = (BOT_TOKEN === 'توکن بات' || BOT_TOKEN === '');

if (PHP_SAPI === 'cli') {
    $args = $argv ?? [];

    // فرمان‌هایی که به شبکه نیاز ندارند — حتی بدون توکن کار می‌کنند
    foreach ($args as $arg) {
        if ($arg === '--init-db') {
            initDb();
            logMsg('✅ دیتابیس ساخته شد: ' . DB_FILE);
            exit(0);
        }
        if ($arg === '--help' || $arg === '-h') {
            echo <<<TXT
🐕 ربات هاپی — نسخه PHP

  php bot.php                       اجرا در حالت پولینگ
  php bot.php --set-webhook=<URL>   تنظیم وبهوک
  php bot.php --delete-webhook      حذف وبهوک
  php bot.php --init-db             فقط ساخت دیتابیس
  php bot.php --cron                اجرای یک‌باره کارهای دوره‌ای
  php bot.php --help                نمایش همین راهنما

TXT;
            exit(0);
        }
    }

    if ($tokenMissing) {
        fwrite(STDERR, "⚠️  توکن ربات تنظیم نشده! مقدار BOT_TOKEN را در بالای فایل ویرایش کنید." . PHP_EOL);
        exit(1);
    }

    foreach ($args as $arg) {
        if (str_starts_with((string) $arg, '--set-webhook=')) {
            initDb();
            setWebhookUrl(substr((string) $arg, 14));
            exit(0);
        }
        if ($arg === '--delete-webhook') {
            tg('deleteWebhook', ['drop_pending_updates' => false]);
            logMsg('✅ وبهوک حذف شد.');
            exit(0);
        }
        if ($arg === '--cron') {
            initDb();
            runCronJobs(true);
            logMsg('✅ کارهای دوره‌ای اجرا شد.');
            exit(0);
        }
    }

    // حذف وبهوک قبلی تا پولینگ کار کند
    tg('deleteWebhook', ['drop_pending_updates' => false]);
    runPolling();
} else {
    if ($tokenMissing) {
        http_response_code(500);
        exit('⚠️  توکن ربات تنظیم نشده!');
    }
    runWebhook();
}

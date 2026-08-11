<?php
/**
 * ربات همه‌کاره تلگرام (تک‌فایل، Webhook) — مخصوص cPanel / هاست اشتراکی PHP
 * قابلیت‌ها:
 *   - پرایس‌چکر متصل به Binance (بدون محدودیت ارز) + قیمت تومانی Nobitex
 *   - عکس چارت شمعی (GD) + دکمه‌های شیشه‌ای رنگی تغییر تایم‌فریم
 *   - پنل ادمین کامل (آمار، تعداد گروه‌ها، متن استارت، همگانی، جوین اجباری، مدیریت ادمین)
 *   - مدیریت کامل گروه (قفل‌ها، خوش‌آمد/خداحافظی، ضدفلاد، فیلتر، اخطار/سکوت/بن/اخراج)
 *   - ابزارهای ترون (اطلاعات تراکنش، موجودی ولت، انتقال‌های TRC20) + ولت چندشبکه‌ای تون/بی‌ان‌بی
 *     با ایموجی اختصاصی هر شبکه و اعتبارسنجی محلی آدرس ترون (Base58Check، بدون وابستگی به API خارجی)
 *   - ایموجی و دکمه‌های رنگی + نقل‌قول (blockquote) با تاریخ شمسی
 *   - قالب‌های خفن قیمت دلار/طلای بازار آزاد (tgju) با نشان تغییرات و دامنهٔ سقف/کف
 *   - اخبار روز ارز دیجیتال با /News (RSS)، شاخص ترس و طمع (CoinMarketCap/alternative.me) با کلمهٔ «شاخص»
 *   - نقشهٔ لیکویدیتی کوین‌گلس با «لیکویدی <ارز>» یا /liquidy
 *   - تحلیل — پست‌های تحلیلی کامیونیتی TradingView با «تحلیل <ارز>» (دکمه‌های تحلیل بعدی/قبلی)
 *   - پنل ادمین: ویرایش کامل متن‌های ربات/برچسب دکمه‌ها/APIها/رنگ چارت بدون نیاز به ویرایش سورس
 *
 * راه‌اندازی: کل پوشه (شامل bot.php و پوشهٔ fonts/) را روی هاست بگذارید و یک‌بار این آدرس را باز کنید:
 *   https://YOURDOMAIN/bot.php?setup=1&key=WEBHOOK_SECRET
 * نیازمندی‌ها: PHP 7.4+ با اکستنشن‌های cURL, GD, PDO_SQLite, mbstring.
 * پوشهٔ fonts/ شامل فونت فارسی وزیرمتن — Vazirmatn (مجوز آزاد SIL OFL 1.1) است که برای تایپ‌شدن متن
 * فارسی داخل تصاویر تولیدی (مثل کارت اخبار) لازم است؛ اگر آپلود نشود، آن قابلیت به‌طور خودکار
 * و بدون خطا به حالت متنی ساده برمی‌گردد.
 * برای قابلیت‌های جدید (شاخص ترس‌وطمع پولی، لیکویدیتی دقیق‌تر) کلیدهای CMC_API_KEY / COINGLASS_API_KEY
 * را در بالای همین فایل تنظیم کنید (شاخص ترس‌وطمع و لیکویدیتی حتی بدون این کلیدها هم رایگان کار می‌کنند).
 */

// ==========================================================================
// 1) کانفیگ — این مقادیر را ویرایش کنید
// ==========================================================================
const BOT_TOKEN       = '8668882500:AAEZ19exWTImgkZL9cUwVbIQiLqq3ca-Wqc';
const SUPER_ADMIN_IDS = [8213021584];              // آیدی عددی مالک‌ها
const WEBHOOK_SECRET  = 'kv_9f3a71c2e8b04d56';     // رمز وبهوک (دلخواه، برای ست‌کردن و امنیت)
const BASE_URL        = '';                          // خالی = تشخیص خودکار آدرس فایل
const TZ              = 'Asia/Tehran';

// مسیر دیتابیس و داده‌ها (کنار همین فایل ساخته می‌شود)
const DATA_DIR = __DIR__ . '/bot_data';
const DB_PATH  = __DIR__ . '/bot_data/bot.sqlite';

// اگر مالک ربات Premium داشته باشد یا ربات یوزرنیم Fragment داشته باشد true کنید
// تا ایموجی پریمیوم (tg-emoji) در متن و روی دکمه‌ها فعال شود.
// نکته: اگر ربات اجازهٔ ارسال ایموجی پریمیوم نداشته باشد، ارسال به‌صورت خودکار
// با ایموجی معمولی (fallback) دوباره تلاش می‌شود؛ پس ربات هرگز خراب نمی‌شود.
const BOT_HAS_PREMIUM = true;

// نگاشت ایموجی‌ها. اگر id پر شود و شرایط پریمیوم باشد، پریمیوم استفاده می‌شود؛
// در غیر این صورت ایموجی یونیکد (fb) نمایش داده می‌شود.
const PREMIUM_EMOJI = [
    'coin'   => ['id' => '', 'fb' => '🪙'],
    'money'  => ['id' => '', 'fb' => '💰'],
    'up'     => ['id' => '', 'fb' => '📈'],
    'down'   => ['id' => '', 'fb' => '📉'],
    'chart'  => ['id' => '', 'fb' => '📊'],
    'clock'  => ['id' => '', 'fb' => '🕐'],
    'cal'    => ['id' => '', 'fb' => '📅'],
    'fire'   => ['id' => '', 'fb' => '🔥'],
    'star'   => ['id' => '', 'fb' => '⭐'],
    'lock'   => ['id' => '', 'fb' => '🔒'],
    'unlock' => ['id' => '', 'fb' => '🔓'],
    'shield' => ['id' => '', 'fb' => '🛡'],
    'admin'  => ['id' => '', 'fb' => '👮'],
    'bell'   => ['id' => '', 'fb' => '🔔'],
    'ok'     => ['id' => '', 'fb' => '✅'],
    'no'     => ['id' => '', 'fb' => '❌'],
    'gift'   => ['id' => '', 'fb' => '🎁'],
    'tron'   => ['id' => '', 'fb' => '🧰'],
    'wallet' => ['id' => '', 'fb' => '💼'],
    'wave'   => ['id' => '', 'fb' => '👋'],
    'people' => ['id' => '', 'fb' => '👥'],
    'rocket' => ['id' => '', 'fb' => '🚀'],
    'back'   => ['id' => '5875082500023258804', 'fb' => '◀️'],
    'refresh'=> ['id' => '', 'fb' => '🔄'],
    // ایموجی روی دکمه‌های شیشه‌ای اطلاعات ارز؛ تا وقتی چارت تغییر نکرده نشان داده می‌شود،
    // بعد از هر تعویض تایم‌فریم (تغییر چارت) دیگر نمایش داده نمی‌شود (محو می‌شود).
    'tfinfo' => ['id' => '5386367538735104399', 'fb' => '✨'],
];

// شناسه‌های ایموجی پریمیوم برای قالب‌ها (custom_emoji_id از تلگرام).
// کلید = برچسب معنایی، مقدار = [شناسه، ایموجی جایگزین معمولی].
// اگر ربات اجازهٔ نمایش نداشته باشد، به‌صورت خودکار جایگزین معمولی استفاده می‌شود.
const PE_IDS = [
    // قالب اطلاعات ارز
    'coin'    => ['5332455502917949981', '💎'], // پشت اسم ارز
    'usd'     => ['5951773156887764244', '💵'], // پشت دلار
    'toman'   => ['5965097893491642896', '💵'], // پشت تومان
    'date'    => ['5274055917766202507', '🗓'], // تاریخ
    'change'  => ['5190806721286657692', '🕯'], // تغییرات
    'volume'  => ['5197503331215361533', '📊'], // حجم
    'high'    => ['5877540355187937244', '📈'], // سقف
    'low'     => ['5877307202888273539', '📉'], // کف
    'info24'  => ['5900006938271288826', '📈'], // اطلاعات ۲۴ ساعته
    // قالب تبدیل ارز
    'cv_coin' => ['5778505852520501441', '💎'], // اسم ارز
    'cv_usd'  => ['5951773156887764244', '💵'], // به دلار
    'cv_toman'=> ['5965097893491642896', '💵'], // تومان
    'cv_star' => ['5438496463044752972', '⭐'], // استارز
    // آیدی ربات
    'botid'   => ['5879585266426973039', '🤖'], // جلوی آیدی ربات
    // قالب دلار / طلا (منبع: tgju)
    'r_usd'   => ['5951773156887764244', '💵'], // پشت دلار
    'r_toman' => ['5965097893491642896', '💵'], // پشت تومان
    'r_date'  => ['5413879192267805083', '🪙'], // پشت تاریخ (دلار)
    'g_qty'   => ['5949707595445968258', '✨'], // پشت عددی که ممبر گفته (طلا)
    // سقف/کف دامنهٔ امروز (هم برای قالب دلار و هم طلا)
    'rg_high'   => ['5445355530111437729', '📈'], // پشت سقف دامنهٔ امروز
    'rg_low'    => ['5443127283898405358', '📉'], // پشت کف دامنهٔ امروز
    'rg_label'  => ['5994378914636500516', '🎯'], // پشت عنوان «سقف کف امروز»
    'rg_change' => ['5451882707875276247', '📊'], // پشت درصد تغییرات امروز
    // قالب ولت (ترون / تون / بی‌ان‌بی)
    'w_info'  => ['5296369303661067030', '🪙'], // عنوان: اطلاعات ولت
    'w_usd'   => ['5951773156887764244', '💵'], // پشت دلار
    'w_toman' => ['5965097893491642896', '💵'], // پشت تومان
    'w_date'  => ['5413879192267805083', '🪙'], // پشت تاریخ
    'w_bal'   => ['5767258028956978383', '💰'], // موجودی ولت
    'w_ton'   => ['5843606192244398823', '💎'], // پشت اسم شبکهٔ تون
    'w_bnb'   => ['5845933235590142297', '🟡'], // پشت اسم شبکهٔ بی‌ان‌بی
    'w_tron'  => ['5846143156411703699', '🔴'], // پشت اسم شبکهٔ ترون
    'w_addr'  => ['5282843764451195532', '📍'], // پشت آدرس ولت
    // ایموجی عمومی جدید (به‌جای ایموجی‌های ساده/پیش‌فرض): عنوان طلا، آیکون دلاری طلا، شروع خط تغییر ۲۴س
    'mark'    => ['5282843764451195532', '🔹'],
];
/** ایموجی پریمیوم داخل متن (parse_mode=HTML). اگر پریمیوم فعال نباشد، جایگزین معمولی. */
function pe(string $key): string {
    $e = PE_IDS[$key] ?? null;
    if (!$e) { return ''; }
    if (BOT_HAS_PREMIUM && $e[0] !== '') {
        return '<tg-emoji emoji-id="' . $e[0] . '">' . $e[1] . '</tg-emoji>';
    }
    return $e[1];
}
/** حذف همهٔ برچسب‌های tg-emoji و جایگزینی با ایموجی معمولی (برای تلاش دوبارهٔ ارسال) */
function stripPremiumEmoji(string $text): string {
    return preg_replace('/<tg-emoji[^>]*>(.*?)<\/tg-emoji>/us', '$1', $text);
}
/** آیا خطای پاسخ تلگرام مربوط به ایموجی پریمیوم/سفارشی است؟ */
function isCustomEmojiError($r): bool {
    if (!is_array($r) || !empty($r['ok'])) { return false; }
    $d = mb_strtolower((string)($r['description'] ?? ''), 'UTF-8');
    return strpos($d, 'custom_emoji') !== false || strpos($d, 'custom emoji') !== false
        || strpos($d, 'emoji') !== false && strpos($d, 'invalid') !== false;
}

// یوزرنیم ربات (برای دکمه «افزودن به گروه» و آیدی داخل قالب)
const BOT_USERNAME = 'PriceNik_BOT';
// نرخ تقریبی هر استارز تلگرام به دلار (برای تبدیل مقدار). قابل تغییر.
const STAR_USD = 0.013;

// تایم‌فریم‌های چارت (interval بایننس). برچسب نمایش در TF_LABELS.
const TIMEFRAMES = ['15m', '30m', '1h', '3h', '1d'];
const TF_LABELS  = ['15m' => '15m', '30m' => '30m', '1h' => '1H', '3h' => '3h', '1d' => '1D'];

// ==========================================================================
// 1‑ب) کلیدهای API سرویس‌های جدید
// ==========================================================================
// کوین‌مارکت‌کپ (Pro API، پولی) — کاملاً اختیاری. شاخص ترس‌وطمع همین الان و بدون هیچ
// کلیدی از منبع رایگان alternative.me کار می‌کند؛ این کلید فقط اگر خودتان اشتراک
// CoinMarketCap دارید و می‌خواهید دادهٔ آن‌ها را جایگزین کنید لازم است.
const CMC_API_KEY = '';
// کوین‌گلس (Coinglass، پولی) — کاملاً اختیاری. نقشهٔ لیکویدیتی همین الان و بدون هیچ کلیدی
// با دادهٔ عمومی فیوچرز بایننس (سقف/کف نقدینگی از پرایس‌اکشن + فاندینگ‌ریت + نسبت لانگ/شورت)
// کار می‌کند. اگر این کلید را پر کنید، به‌جای برآورد رایگان از دادهٔ دقیق‌تر Coinglass استفاده می‌شود.
const COINGLASS_API_KEY = '';
// فید RSS اخبار ارز دیجیتال (منبع: ارز دیجیتال / ArzDigital)
const NEWS_RSS_URL = 'https://arzdigital.com/feed/';

// ایموجی اختصاصی هر شبکه در چکر ولت
const CHAIN_EMOJI = [
    'bnb'  => '🟡',
    'ton'  => '💎',
    'tron' => '🔴',
];

// نام کامل ارزها (نماد → نام). اگر نبود، خود نماد نمایش داده می‌شود.
const COIN_NAMES = [
    'BTC'=>'Bitcoin','ETH'=>'Ethereum','USDT'=>'Tether','BNB'=>'BNB','SOL'=>'Solana',
    'XRP'=>'XRP','ADA'=>'Cardano','DOGE'=>'Dogecoin','TRX'=>'TRON','TON'=>'Toncoin',
    'DOT'=>'Polkadot','MATIC'=>'Polygon','LTC'=>'Litecoin','SHIB'=>'Shiba Inu','AVAX'=>'Avalanche',
    'LINK'=>'Chainlink','XMR'=>'Monero','ATOM'=>'Cosmos','UNI'=>'Uniswap','ETC'=>'Ethereum Classic',
    'XLM'=>'Stellar','BCH'=>'Bitcoin Cash','NEAR'=>'NEAR','APT'=>'Aptos','FIL'=>'Filecoin',
    'ARB'=>'Arbitrum','OP'=>'Optimism','PEPE'=>'Pepe','NOT'=>'Notcoin','WIF'=>'dogwifhat',
    'FLOKI'=>'Floki','ICP'=>'Internet Computer','IMX'=>'Immutable','INJ'=>'Injective','SUI'=>'Sui',
    'SEI'=>'Sei','RUNE'=>'THORChain','AAVE'=>'Aave','GRT'=>'The Graph','SAND'=>'The Sandbox',
    'MANA'=>'Decentraland','AXS'=>'Axie Infinity','FTM'=>'Fantom','ALGO'=>'Algorand','VET'=>'VeChain',
    'HBAR'=>'Hedera','EGLD'=>'MultiversX','THETA'=>'Theta','EOS'=>'EOS','XTZ'=>'Tezos',
    'CHZ'=>'Chiliz','GALA'=>'Gala','ZEC'=>'Zcash','DASH'=>'Dash','CRV'=>'Curve',
    'GMT'=>'STEPN','WLD'=>'Worldcoin','JASMY'=>'JasmyCoin','KAVA'=>'Kava','ROSE'=>'Oasis',
];

// نگاشت نام فارسی چند ارز رایج → نماد
const SYMBOL_ALIASES = [
    'بیتکوین' => 'BTC', 'بیت‌کوین' => 'BTC', 'بیت کوین' => 'BTC',
    'اتریوم' => 'ETH', 'اتر' => 'ETH',
    'تتر' => 'USDT', 'ترون' => 'TRX', 'ترون‌' => 'TRX',
    'ریپل' => 'XRP', 'دوج' => 'DOGE', 'شیبا' => 'SHIB',
    'کاردانو' => 'ADA', 'سولانا' => 'SOL', 'سول' => 'SOL',
    'بایننس' => 'BNB', 'تون' => 'TON', 'پولکادات' => 'DOT',
    'لایت‌کوین' => 'LTC', 'لایت کوین' => 'LTC', 'مونرو' => 'XMR',
    'چین‌لینک' => 'LINK', 'اولانچ' => 'AVAX', 'نات' => 'NOT',
    'گرام' => 'TON',
];

// ==========================================================================
// بوت‌استرپ
// ==========================================================================
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
date_default_timezone_set(TZ);
if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }

// ==========================================================================
// 2) دیتابیس (SQLite / PDO)
// ==========================================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0775, true); }
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        try { $pdo->exec('PRAGMA journal_mode=WAL'); } catch (Throwable $e) {}
        $pdo->exec('PRAGMA foreign_keys=ON');
    }
    return $pdo;
}

function initDatabase(): void {
    $sql = [
        "CREATE TABLE IF NOT EXISTS users(
            chat_id INTEGER PRIMARY KEY,
            username TEXT, first_name TEXT,
            is_banned INTEGER DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS groups(
            chat_id INTEGER PRIMARY KEY,
            title TEXT, added_by INTEGER,
            is_active INTEGER DEFAULT 1,
            joined_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS group_settings(
            chat_id INTEGER PRIMARY KEY,
            welcome_text TEXT, goodbye_text TEXT,
            welcome_on INTEGER DEFAULT 0, goodbye_on INTEGER DEFAULT 0,
            antiflood_on INTEGER DEFAULT 0, flood_limit INTEGER DEFAULT 6, flood_secs INTEGER DEFAULT 5,
            warn_limit INTEGER DEFAULT 3, warn_action TEXT DEFAULT 'mute',
            price_on INTEGER DEFAULT 1, rules TEXT, welcome_reply INTEGER DEFAULT 0
        )",
        "CREATE TABLE IF NOT EXISTS locks(
            chat_id INTEGER, lock_type TEXT,
            PRIMARY KEY(chat_id, lock_type)
        )",
        "CREATE TABLE IF NOT EXISTS warns(
            chat_id INTEGER, user_id INTEGER, count INTEGER DEFAULT 0,
            updated_at TEXT DEFAULT (datetime('now')),
            PRIMARY KEY(chat_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS filters(
            chat_id INTEGER, word TEXT,
            PRIMARY KEY(chat_id, word)
        )",
        "CREATE TABLE IF NOT EXISTS flood(
            chat_id INTEGER, user_id INTEGER, cnt INTEGER DEFAULT 0, ts INTEGER DEFAULT 0,
            PRIMARY KEY(chat_id, user_id)
        )",
        "CREATE TABLE IF NOT EXISTS force_join(
            channel TEXT PRIMARY KEY, title TEXT, is_active INTEGER DEFAULT 1
        )",
        "CREATE TABLE IF NOT EXISTS admins(
            chat_id INTEGER PRIMARY KEY, role TEXT DEFAULT 'admin',
            created_at TEXT DEFAULT (datetime('now'))
        )",
        "CREATE TABLE IF NOT EXISTS texts(
            text_key TEXT PRIMARY KEY, text_value TEXT
        )",
        "CREATE TABLE IF NOT EXISTS settings(
            setting_key TEXT PRIMARY KEY, setting_value TEXT
        )",
        "CREATE TABLE IF NOT EXISTS states(
            chat_id INTEGER PRIMARY KEY, step TEXT, data TEXT,
            updated_at TEXT DEFAULT (datetime('now'))
        )",
    ];
    foreach ($sql as $q) { db()->exec($q); }
    // مهاجرت سبک برای دیتابیس‌های ساخته‌شده با نسخهٔ قدیمی‌تر (ستون‌های جدید)
    try { db()->exec("ALTER TABLE group_settings ADD COLUMN welcome_reply INTEGER DEFAULT 0"); } catch (Throwable $e) {}

    // ادمین‌های اولیه
    foreach (SUPER_ADMIN_IDS as $sid) {
        $st = db()->prepare("INSERT OR IGNORE INTO admins(chat_id, role) VALUES(?, 'owner')");
        $st->execute([$sid]);
    }
    // متن استارت پیش‌فرض
    if (getBotText('start') === null) {
        setBotText('start',
            emo('rocket') . " <b>به ربات پرایس‌چکر خوش آمدید!</b>\n\n" .
            "کافیست نماد ارز را بنویسید (مثل <code>btc</code> یا <code>eth</code>) تا قیمت لحظه‌ای، " .
            "اطلاعات کامل و <b>چارت شمعی</b> را دریافت کنید.\n\n" .
            emo('chart') . " متصل به <b>Binance</b> + قیمت تومانی <b>Nobitex</b>\n" .
            emo('tron') . " ابزارهای ترون هم در دسترس است."
        );
    }
}

// ---- تنظیمات کلید/مقدار و متن‌ها ----
function getSetting(string $k, $default = null) {
    $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $st->execute([$k]);
    $r = $st->fetch();
    return $r ? $r['setting_value'] : $default;
}
function setSetting(string $k, $v): void {
    $st = db()->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(?,?)
                         ON CONFLICT(setting_key) DO UPDATE SET setting_value=excluded.setting_value");
    $st->execute([$k, (string)$v]);
}
function getBotText(string $k): ?string {
    $st = db()->prepare("SELECT text_value FROM texts WHERE text_key=?");
    $st->execute([$k]);
    $r = $st->fetch();
    return $r ? $r['text_value'] : null;
}
function setBotText(string $k, string $v): void {
    $st = db()->prepare("INSERT INTO texts(text_key,text_value) VALUES(?,?)
                         ON CONFLICT(text_key) DO UPDATE SET text_value=excluded.text_value");
    $st->execute([$k, $v]);
}
/** حذف یک متن ذخیره‌شده (بازگشت به مقدار پیش‌فرض کد) */
function resetBotText(string $k): void {
    db()->prepare("DELETE FROM texts WHERE text_key=?")->execute([$k]);
}

// ==========================================================================
// 1‑پ) موتور قالب‌های متنی قابل‌ویرایش از پنل ادمین (بدون نیاز به ویرایش سورس)
// ==========================================================================
/**
 * از دو نشانهٔ اختصاصی پشتیبانی می‌کند تا ادمین بتواند از پنل، بدون لمس کد، از ایموجی
 * پریمیوم و نقل‌قول (blockquote) استفاده کند:
 *   {{quote}} متن دلخواه {{/quote}}   → داخل <blockquote> نمایش داده می‌شود
 *   {{pe:key}}                        → ایموجی پریمیوم (کلیدها را با «راهنما» در ویرایشگر ببینید)
 * پس از آن، جای‌گذاری‌های {var} با مقادیر واقعی جایگزین می‌شوند.
 */
function renderTemplate(string $tpl, array $vars): string {
    $tpl = preg_replace_callback('/\{\{quote\}\}(.*?)\{\{\/quote\}\}/us', function ($m) {
        return quoteBlock(trim($m[1]));
    }, $tpl);
    $tpl = preg_replace_callback('/\{\{equote\}\}(.*?)\{\{\/equote\}\}/us', function ($m) {
        return quoteExpandable(trim($m[1]));
    }, $tpl);
    $tpl = preg_replace_callback('/\{\{pe:([a-zA-Z0-9_]+)\}\}/u', function ($m) {
        $v = pe($m[1]);
        return $v !== '' ? $v : pemo($m[1]);
    }, $tpl);
    return strtr($tpl, $vars);
}
/** فهرست کلیدهای مجاز ایموجی پریمیوم برای نمایش در راهنمای ویرایشگر قالب */
function templateEmojiKeys(): array {
    return array_values(array_unique(array_merge(array_keys(PE_IDS), array_keys(PREMIUM_EMOJI))));
}
/** فهرست قالب‌های متنی قابل‌ویرایش: کلید ذخیره‌سازی => عنوان فارسی */
function templateRegistry(): array {
    return [
        'tpl_price'        => 'کارت اطلاعات ارز',
        'tpl_price_simple' => 'کارت سادهٔ ارز (بدون چارت)',
        'tpl_usdt'         => 'کارت تتر',
        'tpl_conversion'   => 'کارت تبدیل مقدار ارز',
        'tpl_wallet'       => 'کارت ولت (ترون/تون/بی‌ان‌بی)',
        'tpl_dollar'       => 'کارت دلار بازار آزاد',
        'tpl_gold'         => 'کارت طلای ۱۸ عیار',
        'tpl_date'         => 'کارت تاریخ و ساعت',
        'tpl_news'         => 'کارت اخبار',
        'tpl_feargreed'    => 'کپشن شاخص ترس و طمع',
        'tpl_liquidity'    => 'کپشن لیکویدیتی',
        'tpl_analysis'     => 'کپشن تحلیل تریدینگ‌ویو',
        'tpl_welcome'      => 'پیام خوش‌آمدگویی گروه (پیش‌فرض سراسری)',
    ];
}
/** مقدار فعلی یک قالب: ذخیره‌شده در دیتابیس، وگرنه پیش‌فرض کد */
function getTemplate(string $key, string $default): string {
    $v = getBotText($key);
    return ($v !== null && trim($v) !== '') ? $v : $default;
}

// ==========================================================================
// 1‑ت) آدرس پایهٔ APIها — قابل‌تنظیم از پنل ادمین بدون نیاز به ویرایش سورس
// ==========================================================================
/** آدرس پایهٔ هر API را از تنظیمات ذخیره‌شده می‌خواند؛ اگر ادمین چیزی تنظیم نکرده باشد مقدار پیش‌فرض استفاده می‌شود. */
function apiBase(string $key, string $default): string {
    $v = trim((string) getSetting('api_' . $key, ''));
    return $v !== '' ? rtrim($v, '/') : rtrim($default, '/');
}
/** فهرست APIهای قابل‌تنظیم از پنل: کلید => [عنوان فارسی, مقدار پیش‌فرض] */
function apiRegistry(): array {
    return [
        'binance'       => ['بایننس (قیمت/کندل)', 'https://api.binance.com'],
        'binance_fapi'  => ['بایننس فیوچرز (لیکویدیتی)', 'https://fapi.binance.com'],
        'mexc'          => ['MEXC', 'https://api.mexc.com'],
        'wallex'        => ['والکس', 'https://api.wallex.ir'],
        'cryptocompare' => ['CryptoCompare', 'https://min-api.cryptocompare.com'],
        'nobitex'       => ['نوبیتکس', 'https://apiv2.nobitex.ir'],
        'tgju'          => ['tgju (دلار/طلا)', 'https://call1.tgju.org'],
        'tronscan'      => ['Tronscan', 'https://apilist.tronscanapi.com'],
        'toncenter'     => ['TON Center', 'https://toncenter.com'],
        'tradingview'   => ['TradingView (تحلیل)', 'https://www.tradingview.com'],
        'news_rss'      => ['فید اخبار RSS', NEWS_RSS_URL],
    ];
}

// ---- ماشین حالت (فرم‌های چندمرحله‌ای) ----
function setState($chatId, string $step, $data = ''): void {
    if (is_array($data)) { $data = json_encode($data, JSON_UNESCAPED_UNICODE); }
    $st = db()->prepare("INSERT INTO states(chat_id,step,data,updated_at) VALUES(?,?,?,datetime('now'))
                         ON CONFLICT(chat_id) DO UPDATE SET step=excluded.step, data=excluded.data, updated_at=datetime('now')");
    $st->execute([$chatId, $step, $data]);
}
function getState($chatId): ?array {
    $st = db()->prepare("SELECT step,data FROM states WHERE chat_id=?");
    $st->execute([$chatId]);
    $r = $st->fetch();
    return $r ?: null;
}
function clearState($chatId): void {
    $st = db()->prepare("DELETE FROM states WHERE chat_id=?");
    $st->execute([$chatId]);
}

// ==========================================================================
// 3) هلپرهای تلگرام
// ==========================================================================
/**
 * اگر هاست به api.telegram.org مستقیم دسترسی نداشته باشد (فیلترینگ تلگرام — خیلی از هاست‌های
 * ایرانی این مشکل را دارند: اتصال TCP برقرار می‌شود ولی هندشیک TLS معلق می‌ماند تا تایم‌اوت)،
 * از پنل ادمین می‌توانید یک پروکسی HTTP/SOCKS5 ست کنید (مثل <code>socks5://ip:port</code> یا
 * <code>http://user:pass@ip:port</code>) تا همهٔ درخواست‌های تلگرام از آن رد شوند.
 */
function tgProxy(): string { return trim((string) getSetting('tg_proxy', '')); }
function tgApi(string $method, array $params = []) {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $hasFile = false;
    foreach ($params as $v) { if ($v instanceof CURLFile) { $hasFile = true; break; } }
    foreach ($params as $k => $v) {
        if (is_array($v)) { $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE); }
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $params,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 40,
    ]);
    $proxy = tgProxy();
    if ($proxy !== '') { curl_setopt($ch, CURLOPT_PROXY, $proxy); }
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) { error_log("tgApi $method curl error: $err"); return null; }
    return json_decode($res, true);
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function emo(string $name): string {
    $e = PREMIUM_EMOJI[$name] ?? null;
    return $e ? $e['fb'] : '';
}
// ایموجی برای داخل متن (parse_mode=HTML). اگر پریمیوم فعال و id موجود بود از tg-emoji استفاده می‌کند.
function pemo(string $name): string {
    $e = PREMIUM_EMOJI[$name] ?? null;
    if (!$e) { return ''; }
    if (BOT_HAS_PREMIUM && $e['id'] !== '') {
        return '<tg-emoji emoji-id="' . $e['id'] . '">' . $e['fb'] . '</tg-emoji>';
    }
    return $e['fb'];
}

function sendMessage($chatId, string $text, $keyboard = null, $replyTo = null, string $parseMode = 'HTML') {
    $p = [
        'chat_id' => $chatId,
        'text'    => $text,
        'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    if ($keyboard !== null) { $p['reply_markup'] = $keyboard; }
    if ($replyTo !== null)  { $p['reply_to_message_id'] = $replyTo; $p['allow_sending_without_reply'] = true; }
    $r = tgApi('sendMessage', $p);
    // اگر ایموجی پریمیوم پذیرفته نشد، بدون آن دوباره بفرست.
    if (isCustomEmojiError($r) && $text !== stripPremiumEmoji($text)) {
        $p['text'] = stripPremiumEmoji($text);
        $r = tgApi('sendMessage', $p);
    }
    return $r;
}
function editMessageText($chatId, $messageId, string $text, $keyboard = null, string $parseMode = 'HTML') {
    $p = [
        'chat_id' => $chatId, 'message_id' => $messageId,
        'text' => $text, 'parse_mode' => $parseMode,
        'disable_web_page_preview' => true,
    ];
    if ($keyboard !== null) { $p['reply_markup'] = $keyboard; }
    $r = tgApi('editMessageText', $p);
    if (isCustomEmojiError($r) && $text !== stripPremiumEmoji($text)) {
        $p['text'] = stripPremiumEmoji($text);
        $r = tgApi('editMessageText', $p);
    }
    if (!$r || empty($r['ok'])) { return sendMessage($chatId, $text, $keyboard, null, $parseMode); }
    return $r;
}
function editMessageReplyMarkup($chatId, $messageId, $keyboard) {
    return tgApi('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId, 'reply_markup' => $keyboard]);
}
function answerCallback($cbId, string $text = '', bool $alert = false) {
    return tgApi('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => $text, 'show_alert' => $alert]);
}
function deleteMessage($chatId, $messageId) {
    return tgApi('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
}
/**
 * ارسال عکس؛ اگر ارسال عکس ناموفق بود (مثلاً به‌خاطر تایم‌اوت شبکه/فیلترینگ)، به‌جای سکوت کامل
 * کپشن را به‌صورت پیام متنی می‌فرستد — تا کاربر حداقل خود اطلاعات را دریافت کند.
 */
function sendPhotoFile($chatId, string $filePath, string $caption = '', $keyboard = null, $replyTo = null) {
    $p = [
        'chat_id' => $chatId,
        'photo'   => new CURLFile($filePath, 'image/png', 'chart.png'),
        'caption' => $caption,
        'parse_mode' => 'HTML',
    ];
    if ($keyboard !== null) { $p['reply_markup'] = $keyboard; }
    if ($replyTo !== null)  { $p['reply_to_message_id'] = $replyTo; $p['allow_sending_without_reply'] = true; }
    $r = tgApi('sendPhoto', $p);
    if (isCustomEmojiError($r) && $caption !== stripPremiumEmoji($caption)) {
        $p['photo']   = new CURLFile($filePath, 'image/png', 'chart.png');
        $p['caption'] = stripPremiumEmoji($caption);
        $r = tgApi('sendPhoto', $p);
    }
    if (!$r || empty($r['ok'])) { return sendMessage($chatId, $caption, $keyboard, $replyTo); }
    return $r;
}
/** ویرایش عکس پیام؛ اگر ناموفق بود (تایم‌اوت/فیلترینگ)، حداقل متن پیام را ویرایش می‌کند. */
function editPhotoMedia($chatId, $messageId, string $filePath, string $caption, $keyboard) {
    $send = function (string $cap) use ($chatId, $messageId, $filePath, $keyboard) {
        $media = ['type' => 'photo', 'media' => 'attach://chart', 'caption' => $cap, 'parse_mode' => 'HTML'];
        return tgApi('editMessageMedia', [
            'chat_id'      => $chatId,
            'message_id'   => $messageId,
            'media'        => json_encode($media, JSON_UNESCAPED_UNICODE),
            'reply_markup' => json_encode($keyboard, JSON_UNESCAPED_UNICODE),
            'chart'        => new CURLFile($filePath, 'image/png', 'chart.png'),
        ]);
    };
    $r = $send($caption);
    if (isCustomEmojiError($r) && $caption !== stripPremiumEmoji($caption)) {
        $r = $send(stripPremiumEmoji($caption));
    }
    if (!$r || empty($r['ok'])) { return editMessageText($chatId, $messageId, $caption, $keyboard); }
    return $r;
}
function copyMessage($toChat, $fromChat, $messageId, $keyboard = null) {
    $p = ['chat_id' => $toChat, 'from_chat_id' => $fromChat, 'message_id' => $messageId];
    if ($keyboard !== null) { $p['reply_markup'] = $keyboard; }
    return tgApi('copyMessage', $p);
}

// مدیریت اعضا
function restrictMember($chatId, $userId, array $perms, $until = 0) {
    return tgApi('restrictChatMember', [
        'chat_id' => $chatId, 'user_id' => $userId,
        'permissions' => $perms, 'until_date' => $until,
    ]);
}
function banMember($chatId, $userId, $until = 0) {
    return tgApi('banChatMember', ['chat_id' => $chatId, 'user_id' => $userId, 'until_date' => $until]);
}
function unbanMember($chatId, $userId) {
    return tgApi('unbanChatMember', ['chat_id' => $chatId, 'user_id' => $userId, 'only_if_banned' => true]);
}
function promoteMember($chatId, $userId, bool $on = true) {
    return tgApi('promoteChatMember', [
        'chat_id' => $chatId, 'user_id' => $userId,
        'can_manage_chat' => $on, 'can_delete_messages' => $on, 'can_restrict_members' => $on,
        'can_pin_messages' => $on, 'can_invite_users' => $on,
    ]);
}
function getChatMember($chatId, $userId) {
    return tgApi('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
}
function getChatMemberCount($chatId) {
    $r = tgApi('getChatMemberCount', ['chat_id' => $chatId]);
    return ($r && !empty($r['ok'])) ? (int)$r['result'] : 0;
}
function pinMessage($chatId, $messageId) {
    return tgApi('pinChatMessage', ['chat_id' => $chatId, 'message_id' => $messageId, 'disable_notification' => true]);
}

// سازنده کیبورد اینلاین
function ikb(array $rows): array { return ['inline_keyboard' => $rows]; }
/** دکمه callback با استایل رنگی اختیاری و ایموجی پریمیوم اختیاری */
function btn(string $text, string $cb, ?string $style = null, ?string $icon = null): array {
    $b = ['text' => $text, 'callback_data' => $cb];
    if ($style) { $b['style'] = $style; }               // primary | success | danger
    if (BOT_HAS_PREMIUM && $icon) {
        $e = PREMIUM_EMOJI[$icon] ?? null;
        if ($e && $e['id'] !== '') { $b['icon_custom_emoji_id'] = $e['id']; }
    }
    return $b;
}
function btnUrl(string $text, string $url, ?string $style = null): array {
    $b = ['text' => $text, 'url' => $url];
    if ($style) { $b['style'] = $style; }
    return $b;
}

// ==========================================================================
// برچسب دکمه‌های شیشه‌ای قابل‌ویرایش از پنل ادمین (بدون نیاز به ویرایش سورس)
// ==========================================================================
/** فهرست برچسب‌های قابل‌ویرایش دکمه‌ها: کلید ذخیره‌سازی => [عنوان فارسی در پنل, مقدار پیش‌فرض] */
function btnLabelRegistry(): array {
    return [
        'btn_back'      => ['دکمهٔ بازگشت', 'بازگشت'],
        'btn_addgroup'  => ['دکمهٔ افزودن به گروه', '➕ افزودن ربات به گروه'],
        'btn_tf_15m'    => ['دکمهٔ تایم‌فریم 15m', '15m'],
        'btn_tf_30m'    => ['دکمهٔ تایم‌فریم 30m', '30m'],
        'btn_tf_1h'     => ['دکمهٔ تایم‌فریم 1H', '1H'],
        'btn_tf_3h'     => ['دکمهٔ تایم‌فریم 3h', '3h'],
        'btn_tf_1d'     => ['دکمهٔ تایم‌فریم 1D', '1D'],
    ];
}
/** برچسب فعلی یک دکمه: ذخیره‌شده در دیتابیس، وگرنه پیش‌فرض */
function btnLabel(string $key): string {
    $def = btnLabelRegistry()[$key][1] ?? $key;
    $v = getBotText($key);
    return ($v !== null && trim($v) !== '') ? $v : $def;
}
/** دکمهٔ بازگشت با برچسب قابل‌ویرایش + ایموجی پریمیوم اختصاصی بازگشت */
function backBtn(string $cb, string $style = 'primary'): array {
    return btn(emo('back') . ' ' . btnLabel('btn_back'), $cb, $style, 'back');
}

// نقل‌قول گسترش‌پذیر
function quoteExpandable(string $inner): string { return "<blockquote expandable>$inner</blockquote>"; }
function quoteBlock(string $inner): string { return "<blockquote>$inner</blockquote>"; }
// اگر حالت نقل‌قول روشن باشد، متن را داخل blockquote می‌گذارد
function maybeQuote(string $text): string {
    if (getSetting('quote_mode', '0') === '1') { return quoteBlock($text); }
    return $text;
}

// ==========================================================================
// دسترسی‌ها
// ==========================================================================
function isGlobalAdmin($userId): bool {
    if (in_array((int)$userId, SUPER_ADMIN_IDS, true)) { return true; }
    $st = db()->prepare("SELECT 1 FROM admins WHERE chat_id=?");
    $st->execute([$userId]);
    return (bool)$st->fetch();
}
function isGroupAdmin($chatId, $userId): bool {
    static $cache = [];
    if (in_array((int)$userId, SUPER_ADMIN_IDS, true)) { return true; }
    $key = $chatId . ':' . $userId;
    if (isset($cache[$key])) { return $cache[$key]; }
    $r = getChatMember($chatId, $userId);
    $ok = false;
    if ($r && !empty($r['ok'])) {
        $status = $r['result']['status'] ?? '';
        $ok = in_array($status, ['administrator', 'creator'], true);
    }
    return $cache[$key] = $ok;
}

// ==========================================================================
// ثبت کاربر/گروه
// ==========================================================================
function registerUser(array $from): void {
    $st = db()->prepare("INSERT INTO users(chat_id,username,first_name) VALUES(?,?,?)
                         ON CONFLICT(chat_id) DO UPDATE SET username=excluded.username, first_name=excluded.first_name");
    $st->execute([$from['id'], $from['username'] ?? null, $from['first_name'] ?? null]);
}
function registerGroup(array $chat, $addedBy = null): void {
    $st = db()->prepare("INSERT INTO groups(chat_id,title,added_by,is_active) VALUES(?,?,?,1)
                         ON CONFLICT(chat_id) DO UPDATE SET title=excluded.title, is_active=1");
    $st->execute([$chat['id'], $chat['title'] ?? '', $addedBy]);
}
function setGroupActive($chatId, int $active): void {
    $st = db()->prepare("UPDATE groups SET is_active=? WHERE chat_id=?");
    $st->execute([$active, $chatId]);
}
function ensureGroupSettings($chatId): array {
    $st = db()->prepare("SELECT * FROM group_settings WHERE chat_id=?");
    $st->execute([$chatId]);
    $r = $st->fetch();
    if (!$r) {
        db()->prepare("INSERT INTO group_settings(chat_id) VALUES(?)")->execute([$chatId]);
        $st->execute([$chatId]);
        $r = $st->fetch();
    }
    return $r ?: [];
}
function updateGroupSetting($chatId, string $col, $val): void {
    $allowed = ['welcome_text','goodbye_text','welcome_on','goodbye_on','antiflood_on',
                'flood_limit','flood_secs','warn_limit','warn_action','price_on','rules','welcome_reply'];
    if (!in_array($col, $allowed, true)) { return; }
    ensureGroupSettings($chatId);
    $st = db()->prepare("UPDATE group_settings SET $col=? WHERE chat_id=?");
    $st->execute([$val, $chatId]);
}
function groupPriceOn($chatId): bool {
    $s = ensureGroupSettings($chatId);
    return (int)($s['price_on'] ?? 1) === 1;
}

// ==========================================================================
// 4) ماژول قیمت (Binance + Nobitex + چارت)
// ==========================================================================
function httpGet(string $url, int $timeout = 12): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PriceBot/1.0)',
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r === false ? null : $r;
}

/** درخواست POST با بدنهٔ JSON (برای RPC شبکه‌ها). */
function httpPostJson(string $url, string $json, int $timeout = 12): ?string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; PriceBot/1.0)',
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return $r === false ? null : $r;
}

/** تبدیل رشتهٔ هگز (با/بدون 0x) به عدد اعشاری بزرگ بدون سرریز. */
function hexToFloat(string $hex): float {
    $hex = ltrim(strtolower(trim($hex)), '0');
    if (strncmp($hex, 'x', 1) === 0) { $hex = substr($hex, 1); }
    $hex = preg_replace('/[^0-9a-f]/', '', $hex);
    if ($hex === '') { return 0.0; }
    $val = 0.0;
    foreach (str_split($hex) as $c) { $val = $val * 16 + hexdec($c); }
    return $val;
}

/** مجموعه نمادهای معتبر USDT بایننس (کش روزانه) — برای جلوگیری از پاسخ اشتباه در گروه */
/** نمادهای USDT بایننس */
function binanceUsdtSymbols(): array {
    $set = [];
    $j = httpGet(apiBase('binance', 'https://api.binance.com') . '/api/v3/exchangeInfo', 20);
    if ($j) {
        $d = json_decode($j, true);
        foreach (($d['symbols'] ?? []) as $s) {
            if (($s['quoteAsset'] ?? '') === 'USDT' && ($s['status'] ?? '') === 'TRADING') { $set[$s['baseAsset']] = 1; }
        }
    }
    return $set;
}
/** نمادهای USDT صرافی MEXC */
function mexcUsdtSymbols(): array {
    $set = [];
    $j = httpGet(apiBase('mexc', 'https://api.mexc.com') . '/api/v3/exchangeInfo', 20);
    if ($j) {
        $d = json_decode($j, true);
        foreach (($d['symbols'] ?? []) as $s) {
            $status = strtoupper((string)($s['status'] ?? ''));
            if (($s['quoteAsset'] ?? '') === 'USDT' && in_array($status, ['ENABLED', 'TRADING', '1'], true)) {
                $set[$s['baseAsset']] = 1;
            }
        }
    }
    return $set;
}
/** نمادهای پایهٔ بازارهای USDT/تومان والکس (صرافی ایرانی) */
function wallexUsdtSymbols(): array {
    $set = [];
    $syms = wallexMarkets();
    if (!$syms) { return $set; }
    foreach (array_keys($syms) as $key) {
        if (substr($key, -4) === 'USDT') { $set[substr($key, 0, -4)] = 1; }
        elseif (substr($key, -3) === 'TMN') { $set[substr($key, 0, -3)] = 1; }
    }
    return $set;
}
/**
 * مجموع نمادهای همهٔ صرافی‌های متصل (بایننس ∪ MEXC ∪ والکس) — تا هیچ محدودیت ارزی واقعی
 * نداشته باشیم. نتیجه ۲۴ ساعت کش می‌شود. حتی اگر این مجموعه در دسترس نباشد، isValidBase
 * به‌صورت مجاز (permissive) عمل می‌کند — یعنی هر نمادی را به زنجیرهٔ قیمت می‌سپارد.
 */
function validUsdtSymbols(): array {
    static $set = null;
    if ($set !== null) { return $set; }
    $file = DATA_DIR . '/symbols.json';
    if (is_file($file) && (time() - filemtime($file) < 86400)) {
        $set = json_decode(@file_get_contents($file), true) ?: [];
        if ($set) { return $set; }
    }
    $set = binanceUsdtSymbols() + mexcUsdtSymbols() + wallexUsdtSymbols();
    if ($set) { @file_put_contents($file, json_encode($set)); }
    return $set;
}
function isValidBase(string $base): bool {
    $set = validUsdtSymbols();
    if (!$set) { return true; } // اگر لیست در دسترس نبود، اجازه بده (زنجیرهٔ قیمت خودش تشخیص می‌دهد)
    return isset($set[strtoupper($base)]);
}
/** دستور /list: نمایش نمادهای پشتیبانی‌شده (بایننس + MEXC + والکس). محدودیت واقعی وجود ندارد —
 *  حتی نمادهایی که در این لیست نباشند هم از طریق زنجیرهٔ کامل قیمت امتحان می‌شوند. */
function sendSupportedList($chatId, $replyTo = null): void {
    $set = validUsdtSymbols();
    if (!$set) {
        sendMessage($chatId, emo('no') . " لیست ارزها موقتاً در دسترس نیست؛ اما محدودیتی وجود ندارد — هر نماد ارزی را مستقیم بفرستید تا قیمتش بررسی شود.", null, $replyTo);
        return;
    }
    $symbols = array_keys($set);
    sort($symbols);
    $count = count($symbols);
    $shown = array_slice($symbols, 0, 250);
    $t = pemo('chart') . " <b>نمادهای پشتیبانی‌شده</b> (مجموع: {$count} — بایننس + MEXC + والکس)\n";
    $t .= quoteExpandable(h(implode('، ', $shown)) . ($count > count($shown) ? '، …' : ''));
    $t .= "\nمحدودیتی در کار نیست؛ حتی نمادهایی که در این لیست نباشند هم از طریق MEXC/والکس/CryptoCompare امتحان می‌شوند — کافیست نمادش را مستقیم بفرستید.";
    sendMessage($chatId, $t, addGroupKeyboard(), $replyTo);
}

/** نرمال‌سازی ورودی کاربر به نماد پایه (یا null) */
function normalizeSymbol(string $text): ?string {
    $t = trim($text);
    $t = ltrim($t, '$');
    // دستور «قیمت btc»
    if (preg_match('/^(?:قیمت|price|\/p|\/price)\s+([A-Za-z0-9]{2,20})$/u', $t, $m)) {
        $t = $m[1];
    }
    if (isset(SYMBOL_ALIASES[$t])) { return SYMBOL_ALIASES[$t]; }
    if (!preg_match('/^[A-Za-z0-9]{2,20}$/', $t)) { return null; }
    $u = strtoupper($t);
    if (strlen($u) > 5 && substr($u, -4) === 'USDT') { $u = substr($u, 0, -4); }
    return $u;
}

function binance24h(string $symbol): ?array {
    $j = httpGet(apiBase('binance', 'https://api.binance.com') . '/api/v3/ticker/24hr?symbol=' . urlencode($symbol));
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d['lastPrice']) ? $d : null;
}
/** قیمت لحظه‌ای بایننس (بدون آمار ۲۴ساعته) — سبک‌تر از ticker/24hr، برای پشتیبان/کاربردهای ساده */
function binancePriceOnly(string $symbol): ?float {
    $j = httpGet(apiBase('binance', 'https://api.binance.com') . '/api/v3/ticker/price?symbol=' . urlencode($symbol));
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d['price']) && is_numeric($d['price']) ? (float)$d['price'] : null;
}
/** قیمت از CryptoCompare (پشتیبان، وقتی صرافی‌ها در دسترس نباشند) */
function cryptoComparePrice(string $base, string $quote = 'USD'): ?float {
    $j = httpGet(apiBase('cryptocompare', 'https://min-api.cryptocompare.com') . '/data/price?fsym=' . urlencode(strtoupper($base)) . '&tsym=' . urlencode($quote), 10);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d[$quote]) && is_numeric($d[$quote]) ? (float)$d[$quote] : null;
}
/** قیمت لحظه‌ای از MEXC — API این صرافی هم‌فرمت با بایننس است (همان مسیر/فیلدها) */
function mexcPriceOnly(string $symbol): ?float {
    $j = httpGet(apiBase('mexc', 'https://api.mexc.com') . '/api/v3/ticker/price?symbol=' . urlencode($symbol), 10);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d['price']) && is_numeric($d['price']) ? (float)$d['price'] : null;
}
/** لیست بازارهای صرافی ایرانی والکس (کش در حافظهٔ همان اجرا) — چون صرافی داخلی است، معمولاً
 *  حتی وقتی بایننس/MEXC از هاست شما در دسترس نباشند، والکس در دسترس می‌ماند. */
function wallexMarkets(): ?array {
    static $cache = null;
    if ($cache !== null) { return $cache ?: null; }
    $j = httpGet(apiBase('wallex', 'https://api.wallex.ir') . '/v1/markets', 12);
    if (!$j) { $cache = false; return null; }
    $d = json_decode($j, true);
    $syms = $d['result']['symbols'] ?? null;
    $cache = is_array($syms) ? $syms : false;
    return $cache ?: null;
}
/** قیمت دلاری یک ارز از والکس (بازار USDT مستقیم، وگرنه از بازار TMN تقسیم‌بر نرخ تتر/تومان) */
function wallexPrice(string $base): ?float {
    $syms = wallexMarkets();
    if (!$syms) { return null; }
    $base = strtoupper($base);
    foreach ([$base . 'USDT', $base . 'TMN'] as $key) {
        if (!isset($syms[$key])) { continue; }
        $stats = $syms[$key]['stats'] ?? $syms[$key];
        $price = $stats['lastPrice'] ?? $stats['last_price'] ?? $stats['price'] ?? null;
        if ($price === null || !is_numeric($price)) { continue; }
        $price = (float)$price;
        if (substr($key, -3) === 'TMN') {
            $usdt = $syms['USDTTMN']['stats']['lastPrice'] ?? $syms['USDTTMN']['lastPrice'] ?? null;
            if ($usdt === null || !is_numeric($usdt) || (float)$usdt <= 0) { continue; }
            $price = $price / (float)$usdt;
        }
        return $price;
    }
    return null;
}
/**
 * دریافت قیمت با زنجیرهٔ پشتیبان تا ربات به‌خاطر قطعی/مسدودبودن یک سرویس از کار نیفتد و
 * محدودیت ارزی نداشته باشد:
 * ۱) بایننس ticker/24hr (کامل: قیمت+تغییرات+سقف/کف/حجم → کارت با چارت)
 * ۲) بایننس ticker/price (فقط قیمت لحظه‌ای)
 * ۳) MEXC (فقط قیمت لحظه‌ای)
 * ۴) والکس — صرافی ایرانی (فقط قیمت لحظه‌ای)
 * ۵) CryptoCompare (فقط قیمت لحظه‌ای)
 * اگر فقط قیمت لحظه‌ای در دسترس باشد (حالت ۲ تا ۵)، چون داده‌ای برای آمار ۲۴ساعته نیست،
 * کارت سادهٔ بدون عکس چارت نمایش داده می‌شود (full=false) — چارت جدا واکشی می‌شود.
 */
function fetchPriceChain(string $symbol, string $base): array {
    $d = binance24h($symbol);
    if ($d) { return ['full' => true, 'data' => $d, 'price' => (float)$d['lastPrice']]; }
    $p = binancePriceOnly($symbol);
    if ($p !== null) { return ['full' => false, 'data' => null, 'price' => $p]; }
    $p = mexcPriceOnly($symbol);
    if ($p !== null) { return ['full' => false, 'data' => null, 'price' => $p]; }
    $p = wallexPrice($base);
    if ($p !== null) { return ['full' => false, 'data' => null, 'price' => $p]; }
    $p = cryptoComparePrice($base);
    if ($p !== null) { return ['full' => false, 'data' => null, 'price' => $p]; }
    return ['full' => false, 'data' => null, 'price' => null];
}
function binanceKlines(string $symbol, string $interval, int $limit = 70): ?array {
    // بایننس تایم‌فریم 3h ندارد → از کندل‌های 1h (سه‌تایی) می‌سازیم.
    if ($interval === '3h') {
        $raw = binanceKlinesRaw($symbol, '1h', $limit * 3 + 3);
        return $raw ? aggregateKlines($raw, 3) : null;
    }
    return binanceKlinesRaw($symbol, $interval, $limit);
}
/**
 * بررسی می‌کند پاسخ JSON صرافی واقعاً «لیستی از کندل‌ها» است، نه یک شیء خطا مثل
 * {"code":-1121,"msg":"Invalid symbol."} (که بایننس/MEXC برای نمادهای نامعتبر برمی‌گردانند).
 * بدون این بررسی، آن شیء خطا به‌اشتباه به‌عنوان کندل معتبر پردازش می‌شد و باعث می‌شد چارت
 * برخی نمادها به‌صورت یک خط صاف و خراب (روی محدودهٔ ‑1..1) رسم شود.
 */
function isValidKlineList($d): bool {
    if (!is_array($d) || !$d) { return false; }
    if (array_keys($d) !== range(0, count($d) - 1)) { return false; } // باید لیست باشد نه شیء (assoc) خطا
    return is_array($d[0]) && count($d[0]) >= 6;
}
function binanceKlinesRaw(string $symbol, string $interval, int $limit): ?array {
    $j = httpGet(apiBase('binance', 'https://api.binance.com') . '/api/v3/klines?symbol=' . urlencode($symbol) . '&interval=' . urlencode($interval) . '&limit=' . $limit);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isValidKlineList($d) ? $d : null;
}
/** کندل از CryptoCompare (پشتیبان چارت وقتی بایننس در دسترس نباشد) — خروجی هم‌فرمت با کندل بایننس */
function cryptoCompareKlines(string $base, string $interval, int $limit = 70): ?array {
    $map = ['15m' => ['histominute', 15], '30m' => ['histominute', 30], '1h' => ['histohour', 1], '3h' => ['histohour', 3], '1d' => ['histoday', 1]];
    if (!isset($map[$interval])) { return null; }
    [$endpoint, $agg] = $map[$interval];
    $url = 'https://min-api.cryptocompare.com/data/v2/' . $endpoint
         . '?fsym=' . urlencode(strtoupper($base)) . '&tsym=USD&limit=' . $limit . '&aggregate=' . $agg;
    $j = httpGet($url, 12);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    $rows = $d['Data']['Data'] ?? null;
    if (!is_array($rows) || !$rows) { return null; }
    $out = [];
    foreach ($rows as $r) {
        if (!isset($r['time'])) { continue; }
        $high = (float)($r['high'] ?? 0); $low = (float)($r['low'] ?? 0);
        if ($high <= 0 && $low <= 0) { continue; } // نمادهای بدون داده در CryptoCompare همه‌چیز صفر برمی‌گردانند
        $out[] = [
            ((int)$r['time']) * 1000,
            (string)($r['open'] ?? 0), (string)$high, (string)$low,
            (string)($r['close'] ?? 0), (string)($r['volumefrom'] ?? 0),
        ];
    }
    return $out ?: null;
}
/** دریافت کندل با زنجیرهٔ پشتیبان (بایننس → CryptoCompare) تا چارت تقریباً همیشه در دسترس باشد */
/** کندل از MEXC — API این صرافی هم‌فرمت با بایننس است (همان مسیر/فیلدها/تایم‌فریم‌ها) */
function mexcKlinesRaw(string $symbol, string $interval, int $limit): ?array {
    $j = httpGet(apiBase('mexc', 'https://api.mexc.com') . '/api/v3/klines?symbol=' . urlencode($symbol) . '&interval=' . urlencode($interval) . '&limit=' . $limit, 12);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isValidKlineList($d) ? $d : null;
}
function mexcKlines(string $symbol, string $interval, int $limit = 70): ?array {
    if ($interval === '3h') {
        $raw = mexcKlinesRaw($symbol, '1h', $limit * 3 + 3);
        return $raw ? aggregateKlines($raw, 3) : null;
    }
    return mexcKlinesRaw($symbol, $interval, $limit);
}
function fetchKlinesChain(string $symbol, string $base, string $interval, int $limit = 70): ?array {
    $k = binanceKlines($symbol, $interval, $limit);
    if ($k) { return $k; }
    $k = mexcKlines($symbol, $interval, $limit);
    if ($k) { return $k; }
    return cryptoCompareKlines($base, $interval, $limit);
}
/** ادغام هر $group کندل به یک کندل (open اول، close آخر، high بیشینه، low کمینه، حجم جمع) */
function aggregateKlines(array $rows, int $group): array {
    $out = [];
    for ($i = 0; $i < count($rows); $i += $group) {
        $slice = array_slice($rows, $i, $group);
        if (count($slice) < $group) { break; }
        $first = $slice[0]; $last = $slice[count($slice) - 1];
        $high = -INF; $low = INF; $vol = 0.0;
        foreach ($slice as $r) {
            $high = max($high, (float)$r[2]);
            $low  = min($low,  (float)$r[3]);
            $vol += (float)$r[5];
        }
        $out[] = [$first[0], $first[1], $high, $low, $last[4], $vol, $last[6] ?? $first[0]];
    }
    return $out;
}
/** قیمت ریالی نوبیتکس (RLS) — برای تبدیل به تومان تقسیم بر ۱۰ */
function nobitexRls(string $base): ?float {
    $sym = strtolower($base);
    $j = httpGet(apiBase('nobitex', 'https://apiv2.nobitex.ir') . '/market/stats?srcCurrency=' . $sym . '&dstCurrency=rls');
    if (!$j) { return null; }
    $d = json_decode($j, true);
    $k = $sym . '-rls';
    if (($d['status'] ?? '') === 'ok' && isset($d['stats'][$k]['latest'])) {
        return (float)$d['stats'][$k]['latest'];
    }
    return null;
}

function fmtPrice(float $p): string {
    if ($p >= 1)     { return number_format($p, 2); }
    if ($p >= 0.01)  { return number_format($p, 4); }
    return rtrim(rtrim(sprintf('%.8f', $p), '0'), '.');
}
function fmtBig(float $n): string { return number_format($n, ($n >= 1 ? 2 : 6)); }
function fmtToman(float $rls): string { return number_format($rls / 10); }

/** تاریخ شمسی از تاریخ میلادی (الگوریتم استاندارد jdf) */
function gregorianToJalali(int $gy, int $gm, int $gd): array {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
          + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}
function persianDateLine(): string {
    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $shamsi = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $miladi = date('Y-m-d', $ts);
    $time    = date('H:i:s', $ts);
    return pemo('cal') . " تاریخ: <b>$shamsi</b> شمسی | $miladi\n" . pemo('clock') . " ساعت: <b>$time</b> (تهران)";
}

function coinName(string $base): string { return COIN_NAMES[strtoupper($base)] ?? $base; }

/** تومان هر واحد ارز: مستقیم از نوبیتکس، وگرنه از نرخ تتر */
function tomanFor(string $base, float $usdPrice): ?float {
    $rls = nobitexRls(strtolower($base));
    if ($rls !== null) { return $rls / 10; }
    $u = nobitexRls('usdt');
    if ($u !== null) { return $usdPrice * ($u / 10); }
    return null;
}

// ---- منبع دلار/طلای بازار آزاد ایران: tgju.org ----
/** عدد رشته‌ای با کاما (ریال) → float */
function tgjuNum($v): float { return (float)str_replace([',', ' '], '', (string)$v); }

/** داده‌ی زندهٔ دلار و طلای ۱۸ عیار از tgju (قیمت‌ها به تومان).
 *  خروجی: ['usd'=>['toman'=>..,'dp'=>..,'dt'=>'high|low'], 'gold'=>[...]] یا null */
function tgjuData(): ?array {
    static $cache = null;
    if ($cache !== null) { return $cache ?: null; }
    // اگر ادمین از پنل آدرس سفارشی tgju ست کرده باشد، اول آن امتحان می‌شود؛ در غیر این‌صورت
    // به آینه‌های پیش‌فرض (برای مقاومت در برابر قطعی هرکدام) برمی‌گردد.
    $custom = apiBase('tgju', '');
    $urls = [];
    if ($custom !== '') { $urls[] = $custom . '/ajax.json'; }
    $urls = array_merge($urls, [
        'https://call1.tgju.org/ajax.json',
        'http://call1.tgju.org/ajax.json',
        'https://call2.tgju.org/ajax.json',
        'http://call3.tgju.org/ajax.json',
    ]);
    $d = null;
    foreach ($urls as $u) {
        $j = httpGet($u, 10);
        if ($j) {
            $tmp = json_decode($j, true);
            if (isset($tmp['current']['price_dollar_rl']) || isset($tmp['current']['geram18'])) { $d = $tmp; break; }
        }
    }
    if (!$d) { $cache = false; return null; }
    $pick = function (string $key) use ($d): ?array {
        $o = $d['current'][$key] ?? null;
        if (!$o || !isset($o['p'])) { return null; }
        return [
            'toman' => tgjuNum($o['p']) / 10,               // ریال → تومان
            'dp'    => (float)($o['dp'] ?? 0),               // درصد تغییر
            'dt'    => ($o['dt'] ?? 'high') === 'low' ? 'low' : 'high',
            'high'  => isset($o['h']) ? tgjuNum($o['h']) / 10 : null,
            'low'   => isset($o['l']) ? tgjuNum($o['l']) / 10 : null,
        ];
    };
    $usd  = $pick('price_dollar_rl');
    $gold = $pick('geram18');
    if (!$usd && !$gold) { $cache = false; return null; }
    $cache = ['usd' => $usd, 'gold' => $gold];
    return $cache;
}

/** سری تاریخی قیمت بستهٔ یک شاخص tgju (به تومان، از قدیم به جدید) برای نمودار کوچک.
 *  $key: 'price_dollar_rl' یا 'geram18'. $limit آخرین نقاط را برمی‌گرداند. */
function tgjuHistory(string $key, int $limit = 30): array {
    static $memo = [];
    if (isset($memo[$key])) { $rows = $memo[$key]; }
    else {
        $rows = [];
        $urls = [
            'https://api.tgju.org/v1/market/indicator/summary-table-data/' . $key,
            'http://api.tgju.org/v1/market/indicator/summary-table-data/' . $key,
        ];
        foreach ($urls as $u) {
            $j = httpGet($u, 12);
            if (!$j) { continue; }
            $d = json_decode($j, true);
            if (!empty($d['data']) && is_array($d['data'])) { $rows = $d['data']; break; }
        }
        $memo[$key] = $rows;
    }
    if (!$rows) { return []; }
    // ستون ۳ = قیمت بسته‌شدن (ریال، کامادار). داده‌ها از جدید به قدیم‌اند → معکوس.
    $series = [];
    foreach ($rows as $r) {
        if (!isset($r[3])) { continue; }
        $v = tgjuNum($r[3]) / 10; // ریال → تومان
        if ($v > 0) { $series[] = $v; }
    }
    $series = array_reverse($series);
    if ($limit > 0 && count($series) > $limit) { $series = array_slice($series, -$limit); }
    return $series;
}

/** دو بلوک نقل‌قول جدا: ۱) تاریخ/ساعت  ۲) آیدی ربات — هرکدام در quote مستقل.
 *  تلگرام دو <blockquote> چسبیده را در یک نقل‌قول ادغام می‌کند؛ برای جداکردن،
 *  یک خط جداکنندهٔ نامرئی (کاراکتر ثابت‌عرض U+2063) بین دو بلوک قرار می‌دهیم. */
function priceQuote(): string {
    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $shamsi = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $time = date('H:i:s', $ts);
    $sep = "\n\xE2\x81\xA3\n"; // U+2063 (invisible separator) → می‌شکند ادغام دو نقل‌قول
    $dateQuote = quoteBlock(pe('date') . " {$time} | {$shamsi}");
    $botQuote  = quoteBlock(pe('botid') . " @" . BOT_USERNAME);
    return "\n" . $dateQuote . $sep . $botQuote;
}

/** دکمه قرمز افزودن ربات به گروه */
function addToGroupBtn(): array {
    return btnUrl(btnLabel('btn_addgroup'), 'https://t.me/' . BOT_USERNAME . '?startgroup=true', 'danger');
}
function addGroupKeyboard(): array { return ikb([[addToGroupBtn()]]); }
/** دکمهٔ شیشه‌ای سبز افزودن ربات به گروه (برای قالب‌های اخبار/شاخص/لیکویدیتی/تحلیل/تاریخ) */
function addToGroupBtnGreen(): array {
    return btnUrl(btnLabel('btn_addgroup'), 'https://t.me/' . BOT_USERNAME . '?startgroup=true', 'success');
}
function addGroupKeyboardGreen(): array { return ikb([[addToGroupBtnGreen()]]); }

/** تبدیل ارقام فارسی/عربی به لاتین */
function faDigits(string $s): string {
    return strtr($s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
                       '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
}

/** تشخیص «مقدار نماد» مثل «10 تون» → [qty, base] */
function parseQuantity(string $text): ?array {
    if (!preg_match('/^([0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]+(?:[.,][0-9\x{06F0}-\x{06F9}\x{0660}-\x{0669}]+)?)\s+(.+)$/u', trim($text), $m)) { return null; }
    $qty = (float)str_replace(',', '.', faDigits($m[1]));
    if ($qty <= 0) { return null; }
    $base = normalizeSymbol(trim($m[2]));
    if ($base === null) { return null; }
    return [$qty, $base];
}

/** تشخیص درخواست دلار/طلای بازار آزاد: «۱۰ دلار»، «دلار»، «۵ گرم طلا»، «طلا»، «10 gold».
 *  خروجی: ['usd'|'gold', qty]. مقدار پیش‌فرض ۱. کمیت متغیر است. */
function parseRialAsset(string $text): ?array {
    $t = mb_strtolower(faDigits(trim($text)), 'UTF-8');
    if ($t === '') { return null; }
    // اولین عدد داخل متن (اگر باشد) = مقدار
    $qty = 1.0;
    if (preg_match('/(\d+(?:[.,]\d+)?)/', $t, $m)) {
        $q = (float)str_replace(',', '.', $m[1]);
        if ($q > 0) { $qty = $q; }
    }
    // طلا (۱۸ عیار) — واژهٔ طلا یا gold
    if (mb_strpos($t, 'طلا', 0, 'UTF-8') !== false || preg_match('/\bgold\b/', $t)) {
        return ['gold', $qty];
    }
    // دلار — واژهٔ دلار، dollar/usd، یا علامت $ کنار عدد
    if (mb_strpos($t, 'دلار', 0, 'UTF-8') !== false
        || preg_match('/\b(dollar|usd)\b/', $t)
        || preg_match('/\$\s*\d/', $t) || preg_match('/\d\s*\$/', $t)) {
        return ['usd', $qty];
    }
    return null;
}

function looksLikeTronAddress(string $t): bool { return (bool)preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $t); }
function looksLikeTxHash(string $t): bool { return (bool)preg_match('/^(0x)?[0-9a-fA-F]{64}$/', $t); }
function isDateQuery(string $t): bool {
    $t = mb_strtolower(trim($t), 'UTF-8');
    return in_array($t, ['تاریخ', 'ساعت', 'زمان', 'تایم', 'date', 'time', '/date', '/time'], true);
}

/** تاریخ قمری (تقویم هجری قمری حسابی/جدولی) از میلادی */
function gregorianToHijri(int $gy, int $gm, int $gd): array {
    $jd = intdiv(1461 * ($gy + 4800 + intdiv($gm - 14, 12)), 4)
        + intdiv(367 * ($gm - 2 - 12 * intdiv($gm - 14, 12)), 12)
        - intdiv(3 * intdiv($gy + 4900 + intdiv($gm - 14, 12), 100), 4)
        + $gd - 32075;
    $l = $jd - 1948440 + 10632;
    $n = intdiv($l - 1, 10631);
    $l = $l - 10631 * $n + 354;
    $j = intdiv(10985 - $l, 5316) * intdiv(50 * $l, 17719) + intdiv($l, 5670) * intdiv(43 * $l, 15238);
    $l = $l - intdiv(30 - $j, 15) * intdiv(17719 * $j, 50) - intdiv($j, 16) * intdiv(15238 * $j, 43) + 29;
    $m = intdiv(24 * $l, 709);
    $d = $l - intdiv(709 * $m, 24);
    $y = 30 * $n + $j - 30;
    return [$y, $m, $d];
}

/** کارت تاریخ/ساعت کامل (شمسی + قمری + میلادی) */
const DEFAULT_TPL_DATE = "{{pe:cal}} <b>ساعت و تاریخ :</b>\n\n▪️ ساعت : \n └─  <b>{time}</b>\n\n▪️ تاریخ امروز : \n └─  <b>{weekday} {jd} {jmonth} {jyear}</b>\n\n▪️ تاریخ قمری : \n └─  <b>{hd} {hmonth} {hyear}</b>\n\n▪️ تاریخ میلادی : \n └─  <b>{gdate}</b>";
function sendDateCard($chatId, $replyTo = null): void {
    $ts = time();
    $wd  = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'][(int)date('w', $ts)];
    $jmn = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $hmn = ['','محرم','صفر','ربیع‌الاول','ربیع‌الثانی','جمادی‌الاول','جمادی‌الثانی','رجب','شعبان','رمضان','شوال','ذیقعده','ذیحجه'];
    [$jy,$jm,$jd] = gregorianToJalali((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));
    [$hy,$hm,$hd] = gregorianToHijri((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));

    $tpl = getTemplate('tpl_date', DEFAULT_TPL_DATE);
    $t = renderTemplate($tpl, [
        '{time}' => date('H:i:s', $ts), '{weekday}' => $wd, '{jd}' => (string)$jd,
        '{jmonth}' => $jmn[$jm], '{jyear}' => (string)$jy,
        '{hd}' => (string)$hd, '{hmonth}' => $hmn[$hm], '{hyear}' => (string)$hy,
        '{gdate}' => date('d F Y', $ts),
    ]);
    sendMessage($chatId, $t, addGroupKeyboard(), $replyTo);
}

const DEFAULT_TPL_CONVERSION = "{{pe:cv_coin}} ارز {name} | {base}\n\n┓━━❲ تعداد {qty} ❳\n┨≡{{pe:cv_usd}} دلار: {usd} $\n┨≡{{pe:cv_star}} استارز: {stars}\n┚≡{{pe:cv_toman}} تومن: {toman}\n";
/** کارت تبدیل مقدار ارز → دلار/استارز/تومان */
function sendConversionCard($chatId, float $qty, string $base, $replyTo = null): void {
    $name = coinName($base);
    if ($base === 'USDT') {
        $usdPrice = 1.0;
    } else {
        $d = binance24h($base . 'USDT');
        if (!$d) { sendMessage($chatId, emo('no') . " نماد <b>" . h($base) . "</b> در بایننس یافت نشد.", null, $replyTo); return; }
        $usdPrice = (float)$d['lastPrice'];
    }
    $usd   = $qty * $usdPrice;
    $tmU   = tomanFor($base, $usdPrice);
    $toman = $tmU !== null ? $tmU * $qty : null;
    $stars = STAR_USD > 0 ? $usd / STAR_USD : 0;
    $qStr  = rtrim(rtrim(number_format($qty, 6, '.', ','), '0'), '.');

    $tpl = getTemplate('tpl_conversion', DEFAULT_TPL_CONVERSION);
    $t = renderTemplate($tpl, [
        '{name}' => $name, '{base}' => $base, '{qty}' => $qStr,
        '{usd}' => fmtBig($usd), '{stars}' => number_format($stars),
        '{toman}' => ($toman !== null ? number_format(round($toman)) . ' تومان' : '—'),
    ]) . priceQuote();
    sendMessage($chatId, $t, addGroupKeyboard(), $replyTo);
}

/**
 * کیبورد تایم‌فریم چارت — فقط متن، بدون ایموجی روی دکمه‌ها (روی برخی کلاینت‌ها آیکون ایموجی
 * پریمیوم دکمه‌های شیشه‌ای درست نمایش داده نمی‌شد، پس حذف شد). آرگومان $showIcon نگه داشته
 * شده تا فراخوانی‌های موجود بدون تغییر کار کنند ولی دیگر اثری ندارد.
 */
function timeframeKeyboard(string $base, string $active, bool $showIcon = false): array {
    $mk = function (string $tf) use ($base, $active) {
        $label = btnLabel('btn_tf_' . $tf);
        $style = ($tf === $active) ? 'success' : 'primary';
        if ($tf === $active) { $label = "• $label •"; }
        return btn($label, "tf:$base:$tf", $style);
    };
    $rows = [
        [$mk('15m'), $mk('30m'), $mk('1h')],
        [$mk('3h'), $mk('1d')],
        [addToGroupBtn()],
    ];
    return ikb($rows);
}

function buildPriceCaption(string $base, array $d): string {
    $price = (float)$d['lastPrice'];
    $chg   = (float)($d['priceChangePercent'] ?? 0);
    $high  = (float)($d['highPrice'] ?? 0);
    $low   = (float)($d['lowPrice'] ?? 0);
    $vol   = (float)($d['quoteVolume'] ?? ($d['volume'] ?? 0));
    $sign  = $chg >= 0 ? '+' : '';
    $name  = coinName($base);

    $tmU   = tomanFor($base, $price);
    $toman = $tmU !== null ? number_format(round($tmU)) . ' تومان' : '—';

    $tpl = getTemplate('tpl_price', DEFAULT_TPL_PRICE);
    return renderTemplate($tpl, [
        '{name}' => $name, '{base}' => $base, '{price}' => fmtPrice($price), '{toman}' => $toman,
        '{sign}' => $sign, '{change}' => number_format($chg, 2),
        '{high}' => fmtPrice($high), '{low}' => fmtPrice($low), '{volume}' => fmtBig($vol),
    ]) . priceQuote();
}
const DEFAULT_TPL_PRICE = "💎 ارز {name} - {base} {{pe:coin}}\n\n┓━━❲ قیمت لحظه ای ❳\n┨≡{{pe:usd}} دلار: {price} $\n┚≡{{pe:toman}} تومن: {toman}\n\n┓━━❲ اطلاعات 𝟐𝟒𝐡 ❳ {{pe:info24}}\n┨≡{{pe:change}} تغییرات: ❲ {sign}{change}% ❳\n┨≡{{pe:high}} سقف: {high} $\n┨≡{{pe:low}} کف: {low} $\n┚≡{{pe:volume}} حجم: {volume} $\n";

/** رندر چارت شمعی و برگرداندن مسیر فایل PNG موقت (اقتباس از Chart.php) */
/** تبدیل کد هگز ۶ کاراکتری (بدون #) به [r,g,b] */
function hexRgb(string $hex): array {
    $hex = str_pad(ltrim($hex, '#'), 6, '0');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}
function renderCandlestickChart(array $candles, string $symbol, string $interval): ?string {
    // فیلتر دفاعی: هر ردیف کندل نامعتبر (کمتر از ۶ فیلد، مثلاً باقیماندهٔ یک پاسخ خطای صرافی) کنار گذاشته می‌شود.
    $candles = array_values(array_filter($candles, fn($c) => is_array($c) && count($c) >= 6));
    if (!function_exists('imagecreatetruecolor') || count($candles) < 2) { return null; }
    $width = 850; $height = 520;
    $pl = 90; $pr = 40; $pt = 50; $pb = 80;
    $cw = $width - $pl - $pr; $chh = $height - $pt - $pb;

    $img = imagecreatetruecolor($width, $height);
    // رنگ چارت از پنل ادمین قابل‌تنظیم است؛ پیش‌فرض: پس‌زمینهٔ سیاه، صعودی آبی پررنگ، نزولی سفید.
    [$bgR, $bgG, $bgB] = hexRgb(chartColor('bg', '000000'));
    [$upR, $upG, $upB] = hexRgb(chartColor('up', '2169ED'));
    [$dnR, $dnG, $dnB] = hexRgb(chartColor('down', 'FFFFFF'));
    $bg     = imagecolorallocate($img, $bgR, $bgG, $bgB);
    $border = imagecolorallocate($img, 55, 58, 66);
    $grid   = imagecolorallocate($img, 32, 34, 40);
    $up     = imagecolorallocate($img, $upR, $upG, $upB);
    $down   = imagecolorallocate($img, $dnR, $dnG, $dnB);
    $dark   = imagecolorallocate($img, 235, 237, 242);
    $muted  = imagecolorallocate($img, 150, 155, 168);

    imagefill($img, 0, 0, $bg);
    imagerectangle($img, $pl - 10, $pt - 10, $width - $pr + 10, $height - $pb + 10, $border);

    $highs = array_map(fn($c) => (float)$c[2], $candles);
    $lows  = array_map(fn($c) => (float)$c[3], $candles);
    $max = max($highs); $min = min($lows);
    if ($max == $min) { $max += 1; $min -= 1; }
    $dec = ($max < 1) ? 6 : 2;

    for ($i = 0; $i <= 10; $i++) {
        $y = (int)($pt + $i * ($chh / 10));
        imageline($img, $pl, $y, $width - $pr, $y, $grid);
        $price = $max - $i * (($max - $min) / 10);
        imagestring($img, 2, 6, $y - 7, number_format($price, $dec), $muted);
    }

    $n = count($candles);
    $bw = $cw / max(1, $n);
    for ($i = 0; $i < $n; $i++) {
        $o = (float)$candles[$i][1]; $c = (float)$candles[$i][4];
        $high = (float)$candles[$i][2]; $low = (float)$candles[$i][3];
        $x = $pl + $i * $bw;
        $yo = $pt + $chh - (($o - $min) / ($max - $min) * $chh);
        $yc = $pt + $chh - (($c - $min) / ($max - $min) * $chh);
        $yh = $pt + $chh - (($high - $min) / ($max - $min) * $chh);
        $yl = $pt + $chh - (($low - $min) / ($max - $min) * $chh);
        $col = ($c >= $o) ? $up : $down;
        $cx = (int)($x + $bw / 2);
        imageline($img, $cx, (int)$yh, $cx, (int)$yl, $col);
        $top = (int)min($yo, $yc); $bot = (int)max($yo, $yc);
        if ($bot - $top < 1) { $bot = $top + 1; }
        imagefilledrectangle($img, (int)($x + 2), $top, (int)($x + $bw - 2), $bot, $col);
    }
    for ($i = 0; $i < $n; $i += 10) {
        $t = date('H:i', (int)($candles[$i][0] / 1000));
        $x = (int)($pl + $i * $bw);
        imagestring($img, 2, $x, $height - $pb + 12, $t, $muted);
    }
    imagestring($img, 5, $pl, 14, "$symbol  |  $interval", $dark);
    $foot = "@" . BOT_USERNAME;
    imagestring($img, 3, $width - $pr - imagefontwidth(3) * strlen($foot), $height - 22, $foot, $muted);

    $tmp = tempnam(sys_get_temp_dir(), 'chart') . '.png';
    imagepng($img, $tmp);
    imagedestroy($img);
    return is_file($tmp) ? $tmp : null;
}

// ---- کارت مدرن دلار/طلا (قیمت بزرگ + نمودار سطحی کوچک) ----
/** یافتن یک فونت TTF لاتین برای متن واضح (اعداد/برچسب‌ها). اگر نبود null. */
function findTtf(bool $bold = false): ?string {
    static $cache = [];
    $k = $bold ? 'b' : 'r';
    if (array_key_exists($k, $cache)) { return $cache[$k]; }
    $list = $bold ? [
        __DIR__ . '/fonts/bold.ttf', __DIR__ . '/fonts/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
    ] : [
        __DIR__ . '/fonts/regular.ttf', __DIR__ . '/fonts/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/liberation/LiberationSans-Regular.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ];
    foreach ($list as $f) { if (@is_file($f)) { return $cache[$k] = $f; } }
    // هیچ فونت لاتین سیستمی (DejaVu/Liberation) پیدا نشد — روی بسیاری از هاست‌های اشتراکی این
    // فونت‌ها اصلاً نصب نیستند. به‌جای خالی‌ماندن همهٔ کارت‌های تصویری، از فونت فارسی وزیرمتن
    // (که پوشش کامل اعداد/لاتین هم دارد) به‌عنوان آخرین راه‌حل استفاده می‌شود.
    return $cache[$k] = findFaTtf($bold);
}

/**
 * یافتن فونت فارسی اصلی — وزیرمتن (Vazirmatn) — برای نوشتن متن فارسی داخل تصاویر GD.
 * برای اینکه این قابلیت کار کند، فایل‌های Vazirmatn-Regular.ttf و Vazirmatn-Bold.ttf باید در
 * پوشهٔ fonts/ کنار همین فایل آپلود شوند (فونت آزاد/رایگان با مجوز SIL OFL 1.1).
 * اگر فونت پیدا نشود، رندر متن فارسی داخل تصویر به‌آرامی غیرفعال می‌شود (بدون خطا/کرش).
 */
function findFaTtf(bool $bold = false): ?string {
    static $cache = [];
    $k = $bold ? 'b' : 'r';
    if (array_key_exists($k, $cache)) { return $cache[$k]; }
    $list = $bold ? [
        __DIR__ . '/fonts/persian-bold.ttf', __DIR__ . '/fonts/Vazirmatn-Bold.ttf',
        '/usr/share/fonts/truetype/vazirmatn/Vazirmatn-Bold.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansArabic-Bold.ttf', // آخرین راه‌حل اگر وزیرمتن موجود نبود
    ] : [
        __DIR__ . '/fonts/persian.ttf', __DIR__ . '/fonts/Vazirmatn-Regular.ttf',
        '/usr/share/fonts/truetype/vazirmatn/Vazirmatn-Regular.ttf',
        '/usr/share/fonts/truetype/noto/NotoSansArabic-Regular.ttf', // آخرین راه‌حل اگر وزیرمتن موجود نبود
    ];
    foreach ($list as $f) { if (@is_file($f)) { return $cache[$k] = $f; } }
    return $cache[$k] = null;
}
/** آیا این کلمه شامل حرف عربی/فارسی است؟ (برای انتخاب فونت مناسب هر توکن) */
function faIsArabicWord(string $w): bool {
    return (bool)preg_match('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u', $w);
}
/** عرض پیکسلی یک رشته با فونت/سایز مشخص */
function faTextWidth(string $font, float $pt, string $text): float {
    $bb = imagettfbbox($pt, 0, $font, $text);
    return abs($bb[2] - $bb[0]);
}
/**
 * رسم یک خط ترکیبی فارسی/لاتین به‌صورت راست‌به‌چپ.
 * نکتهٔ کلیدی: GD/FreeType حروف فارسی/عربی مجاور هم را در یک تک‌کلمه به‌درستی می‌چسباند
 * (shaping خودکار)، اما ترتیب کلمات را معکوس نمی‌کند. پس اینجا فقط «ترتیب کلمات» معکوس
 * می‌شود (نه حروف داخل هر کلمه) تا هم اتصال حروف درست بماند و هم ترتیب خواندن راست‌به‌چپ شود.
 * هر توکن با فونت خودش (فارسی یا لاتین) رسم می‌شود تا اعداد/نمادهای لاتین هم درست دربیایند.
 * $rightX لبهٔ راست خط (نقطهٔ شروع خواندن) است. عرض کل خط را برمی‌گرداند.
 */
/** آرایهٔ کلمات معکوس‌شده (برای رسم راست‌به‌چپ) + عرض هرکدام + عرض کل خط، بدون رسم (برای اندازه‌گیری/میان‌چین‌کردن) */
function faMeasureLine(string $text, string $faFont, string $latFont, float $pt): array {
    $words = preg_split('/\s+/u', trim($text));
    $words = array_values(array_filter($words, fn($w) => $w !== ''));
    if (!$words) { return ['words' => [], 'widths' => [], 'total' => 0.0]; }
    $rev = array_reverse($words);
    $spaceW = faTextWidth($latFont, $pt, ' ');
    $widths = [];
    $total = 0.0;
    foreach ($rev as $w) {
        $font = faIsArabicWord($w) ? $faFont : $latFont;
        $ww = faTextWidth($font, $pt, $w);
        $widths[] = $ww;
        $total += $ww;
    }
    $total += $spaceW * (count($rev) - 1);
    return ['words' => $rev, 'widths' => $widths, 'total' => $total, 'spaceW' => $spaceW];
}
/** رسم یک خط ترکیبی فارسی/لاتین راست‌به‌چپ، با لبهٔ راست ثابت در $rightX. عرض کل را برمی‌گرداند. */
function faDrawLine($img, string $text, string $faFont, string $latFont, int $rightX, int $baselineY, float $pt, int $color): float {
    $m = faMeasureLine($text, $faFont, $latFont, $pt);
    if (!$m['words']) { return 0.0; }
    $x = $rightX - $m['total'];
    foreach ($m['words'] as $i => $w) {
        $font = faIsArabicWord($w) ? $faFont : $latFont;
        imagettftext($img, $pt, 0, (int)round($x), $baselineY, $color, $font, $w);
        $x += $m['widths'][$i] + $m['spaceW'];
    }
    return $m['total'];
}
/** رسم یک خط ترکیبی فارسی/لاتین، میان‌چین حول $centerX */
function faDrawLineCentered($img, string $text, string $faFont, string $latFont, int $centerX, int $baselineY, float $pt, int $color): float {
    $m = faMeasureLine($text, $faFont, $latFont, $pt);
    return faDrawLine($img, $text, $faFont, $latFont, (int)round($centerX + $m['total'] / 2), $baselineY, $pt, $color);
}
/** شکستن متن ترکیبی فارسی/لاتین به چند خط بر اساس حداکثر عرض پیکسلی (بر مبنای ترتیب منطقی کلمات) */
function faWrapMixed(string $text, string $faFont, string $latFont, float $pt, float $maxWidth): array {
    $words = preg_split('/\s+/u', trim($text));
    $words = array_values(array_filter($words, fn($w) => $w !== ''));
    if (!$words) { return []; }
    $spaceW = faTextWidth($latFont, $pt, ' ');
    $lines = []; $cur = []; $curWidth = 0.0;
    foreach ($words as $w) {
        $font = faIsArabicWord($w) ? $faFont : $latFont;
        $ww = faTextWidth($font, $pt, $w);
        $addWidth = $cur ? ($curWidth + $spaceW + $ww) : $ww;
        if ($cur && $addWidth > $maxWidth) {
            $lines[] = implode(' ', $cur);
            $cur = [$w]; $curWidth = $ww;
        } else {
            $cur[] = $w; $curWidth = $addWidth;
        }
    }
    if ($cur) { $lines[] = implode(' ', $cur); }
    return $lines;
}
/** کوتاه‌کردن رشته به حداکثر N نویسهٔ چندبایتی، با سه‌نقطه در صورت بریدن */
function mbTruncate(string $s, int $max): string {
    return mb_strlen($s, 'UTF-8') > $max ? (mb_substr($s, 0, $max, 'UTF-8') . '…') : $s;
}

/** نوشتن متن با مبدأ گوشهٔ بالا-چپ و ارتفاع تقریبی $px پیکسل.
 *  اگر TTF موجود بود از FreeType (واضح) وگرنه از فونت بیت‌مپ بزرگ‌شده استفاده می‌کند. */
function cardText($img, string $text, int $x, int $y, int $px, int $color, bool $bold = false, string $align = 'left'): void {
    $font = function_exists('imagettftext') ? findTtf($bold) : null;
    if ($font) {
        $pt = $px * 0.70;
        $bb = imagettfbbox($pt, 0, $font, $text);
        $w  = abs($bb[2] - $bb[0]);
        $asc = abs($bb[7]);
        if ($align === 'right') { $x -= $w; } elseif ($align === 'center') { $x -= intdiv($w, 2); }
        imagettftext($img, $pt, 0, $x, $y + $asc, $color, $font, $text);
        return;
    }
    $gf = 5; $gw = imagefontwidth($gf); $gh = imagefontheight($gf);
    $scale = max(1, (int)round($px / $gh));
    $tw = $gw * strlen($text) * $scale;
    if ($align === 'right') { $x -= $tw; } elseif ($align === 'center') { $x -= intdiv($tw, 2); }
    if ($scale <= 1) { imagestring($img, $gf, $x, $y, $text, $color); return; }
    $sw = max(1, $gw * strlen($text)); $sh = $gh;
    $scr = imagecreatetruecolor($sw, $sh);
    imagealphablending($scr, false); imagesavealpha($scr, true);
    imagefill($scr, 0, 0, imagecolorallocatealpha($scr, 0, 0, 0, 127));
    imagealphablending($scr, true);
    imagestring($scr, $gf, 0, 0, $text, $color); // شناسهٔ رنگ truecolor بین تصاویر مشترک است
    imagecopyresampled($img, $scr, $x, $y, 0, 0, $tw, $sh * $scale, $sw, $sh);
    imagedestroy($scr);
}

/** مستطیل با گوشه‌های گرد (پرشده) */
function roundedRect($img, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void {
    $r = max(0, min($r, intdiv(min($x1 - $x0, $y1 - $y0), 2)));
    if ($r <= 0) { imagefilledrectangle($img, $x0, $y0, $x1, $y1, $color); return; }
    imagefilledrectangle($img, $x0 + $r, $y0, $x1 - $r, $y1, $color);
    imagefilledrectangle($img, $x0, $y0 + $r, $x1, $y1 - $r, $color);
    $d = $r * 2;
    imagefilledellipse($img, $x0 + $r, $y0 + $r, $d, $d, $color);
    imagefilledellipse($img, $x1 - $r, $y0 + $r, $d, $d, $color);
    imagefilledellipse($img, $x0 + $r, $y1 - $r, $d, $d, $color);
    imagefilledellipse($img, $x1 - $r, $y1 - $r, $d, $d, $color);
}
/** خط دور مستطیل گردگوشه (فقط قوس‌ها + اضلاع) */
function roundedRectOutline($img, int $x0, int $y0, int $x1, int $y1, int $r, int $color): void {
    $r = max(0, min($r, intdiv(min($x1 - $x0, $y1 - $y0), 2)));
    imageline($img, $x0 + $r, $y0, $x1 - $r, $y0, $color);
    imageline($img, $x0 + $r, $y1, $x1 - $r, $y1, $color);
    imageline($img, $x0, $y0 + $r, $x0, $y1 - $r, $color);
    imageline($img, $x1, $y0 + $r, $x1, $y1 - $r, $color);
    if ($r > 0) {
        $d = $r * 2;
        imagearc($img, $x0 + $r, $y0 + $r, $d, $d, 180, 270, $color);
        imagearc($img, $x1 - $r, $y0 + $r, $d, $d, 270, 360, $color);
        imagearc($img, $x0 + $r, $y1 - $r, $d, $d, 90, 180, $color);
        imagearc($img, $x1 - $r, $y1 - $r, $d, $d, 0, 90, $color);
    }
}
/** مثلث کوچک صعود/نزول رسم‌شده مستقیم با GD (مستقل از فونت) — چون ▲/▼ در خیلی از فونت‌های
 *  فارسی (از‌جمله وزیرمتن) گلیف ندارند و روی برخی هاست‌ها اصلاً نمایش داده نمی‌شوند. */
function drawTrendTriangle($img, int $cx, int $cy, int $size, bool $up, int $color): void {
    $pts = $up
        ? [$cx, $cy - $size, $cx - $size, $cy + $size, $cx + $size, $cy + $size]
        : [$cx, $cy + $size, $cx - $size, $cy - $size, $cx + $size, $cy - $size];
    if (PHP_VERSION_ID >= 80100) { imagefilledpolygon($img, $pts, $color); }
    else { imagefilledpolygon($img, $pts, 3, $color); }
}

/** نمودار خطی سادهٔ بدون پرشدگی (مثل polyline در SVG) — برای کارت‌های سبک با پس‌زمینهٔ روشن */
function drawSparkline($img, array $series, int $x0, int $y0, int $x1, int $y1, int $line, int $thickness = 3): void {
    $series = array_values(array_filter($series, fn($v) => is_numeric($v) && $v > 0));
    $n = count($series);
    if ($n < 2) { return; }
    $min = min($series); $max = max($series);
    $pad = ($max - $min) * 0.14; if ($pad <= 0) { $pad = max(1, $max * 0.02); }
    $min -= $pad; $max += $pad;
    $w = $x1 - $x0; $h = $y1 - $y0;
    imagesetthickness($img, $thickness);
    $prev = null;
    for ($i = 0; $i < $n; $i++) {
        $px = $x0 + (int)round($w * ($n === 1 ? 0 : $i / ($n - 1)));
        $py = $y1 - (int)round($h * ($series[$i] - $min) / ($max - $min));
        if ($prev !== null) { imageline($img, $prev[0], $prev[1], $px, $py, $line); }
        $prev = [$px, $py];
    }
    imagesetthickness($img, 1);
}

/** نمودار سطحی (filled area) با پرشدگی نیم‌شفاف زیر خط — برای کارت‌های تیرهٔ دلار/طلا */
function drawAreaChart($img, array $series, int $x0, int $y0, int $x1, int $y1, array $rgb, int $thickness = 4): void {
    $series = array_values(array_filter($series, fn($v) => is_numeric($v) && $v > 0));
    $n = count($series);
    if ($n < 2) { return; }
    $min = min($series); $max = max($series);
    $pad = ($max - $min) * 0.16; if ($pad <= 0) { $pad = max(1, $max * 0.02); }
    $min -= $pad; $max += $pad;
    $w = $x1 - $x0; $h = $y1 - $y0;
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $px = $x0 + (int)round($w * ($n === 1 ? 0 : $i / ($n - 1)));
        $py = $y1 - (int)round($h * ($series[$i] - $min) / ($max - $min));
        $pts[] = [$px, $py];
    }
    // پرشدگی: چند نوار افقی نیم‌شفاف با آلفای کاهنده به سمت پایین (تقریب گرادیان بدون نیاز به پردازش پیکسلی)
    $bands = 36;
    for ($b = 0; $b < $bands; $b++) {
        $alpha = 30 + (int)round(($b / max(1, $bands - 1)) * 87); // 30 (کدر، بالا) → 117 (شفاف، پایین)
        $bandCol = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
        $by0 = $y0 + (int)round(($h / $bands) * $b);
        $by1 = $y0 + (int)round(($h / $bands) * ($b + 1));
        $poly = [];
        foreach ($pts as $p) { $poly[] = $p[0]; $poly[] = max($p[1], $by0); }
        $poly[] = $x1; $poly[] = $by1; $poly[] = $x0; $poly[] = $by1;
        if (PHP_VERSION_ID >= 80100) { imagefilledpolygon($img, $poly, $bandCol); }
        else { imagefilledpolygon($img, $poly, (int)(count($poly) / 2), $bandCol); }
    }
    $lineCol = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    imagesetthickness($img, $thickness);
    for ($i = 1; $i < $n; $i++) { imageline($img, $pts[$i - 1][0], $pts[$i - 1][1], $pts[$i][0], $pts[$i][1], $lineCol); }
    imagesetthickness($img, 1);
    // نقطهٔ آخر (مثل تصویر مرجع)
    imagefilledellipse($img, $pts[$n - 1][0], $pts[$n - 1][1], 7 * max(1, intdiv($thickness, 2)), 7 * max(1, intdiv($thickness, 2)), $lineCol);
}

/**
 * کارت تیرهٔ دلار/طلا با فونت انگلیسی — سبک «رترو دیجیتال»: پس‌زمینهٔ تقریباً سیاه، نوار رنگی
 * باریک بالا، برچسب‌های گوشه، عدد بزرگ، برچسب TOMAN، پیل درصد تغییر و نمودار سطحی رنگی پایین.
 * $asset='usd'|'gold' ، $priceToman قیمت کل ، $series سری تاریخی تومان.
 */
function renderRialCard(string $asset, float $priceToman, array $series, array $meta = []): ?string {
    if (!function_exists('imagecreatetruecolor')) { return null; }
    $latFont = findTtf(false); $latFontB = findTtf(true);
    if (!$latFont || !$latFontB) { return null; }

    $isGold = ($asset === 'gold');
    $SS = 2; // سوپرسمپل
    $W = 900; $H = 520;
    $Wp = $W * $SS; $Hp = $H * $SS;
    $img = imagecreatetruecolor($Wp, $Hp);
    imagealphablending($img, true); imagesavealpha($img, true);

    if ($isGold) {
        $bg = [16, 13, 8]; $accent = [230, 176, 47]; $tagTop = 'GRAM'; $tagRight1 = 'GOLD 18K';
    } else {
        $bg = [6, 15, 13]; $accent = [23, 191, 138]; $tagTop = 'USD'; $tagRight1 = 'USD';
    }
    $white  = imagecolorallocate($img, 240, 242, 246);
    $muted  = imagecolorallocate($img, 140, 150, 162);
    $accentCol = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);

    $bgCol = imagecolorallocate($img, $bg[0], $bg[1], $bg[2]);
    imagefilledrectangle($img, 0, 0, $Wp, $Hp, $bgCol);
    imagefilledrectangle($img, 0, 0, $Wp, 7 * $SS, $accentCol); // نوار رنگی باریک بالای تصویر

    $padX = 42 * $SS;

    // برچسب بالا-چپ: مقدار (مثل GRAM 50) / بالا-راست: نوع دارایی + IRT
    $qtyStr = rtrim(rtrim(number_format((float)($meta['qty'] ?? 1), 2, '.', ''), '0'), '.');
    cardText($img, $tagTop . ' ' . $qtyStr, $padX, 34 * $SS, 20 * $SS, $muted, true, 'left');
    cardText($img, $tagRight1, $Wp - $padX, 30 * $SS, 22 * $SS, $accentCol, true, 'right');
    cardText($img, 'IRT', $Wp - $padX, 58 * $SS, 15 * $SS, $muted, false, 'right');

    // عدد بزرگ (چپ‌چین، فونت انگلیسی)
    $priceStr = number_format((int)round($priceToman));
    $priceSizePx = 78 * $SS;
    cardText($img, $priceStr, $padX, 128 * $SS, $priceSizePx, $white, true, 'left');
    $priceSizePt = $priceSizePx * 0.70;
    $priceAsc = abs(imagettfbbox($priceSizePt, 0, $latFontB, $priceStr)[7]);

    // برچسب واحد TOMAN زیر عدد
    $unitY = 128 * $SS + $priceAsc + 14 * $SS;
    cardText($img, 'TOMAN', $padX, $unitY, 22 * $SS, $muted, true, 'left');

    // پیل درصد تغییر (مثلث + درصد) زیر TOMAN
    $up = (($meta['dt'] ?? 'high') !== 'low');
    $dp = (float)($meta['dp'] ?? 0);
    $chgCol = $up ? imagecolorallocate($img, 46, 204, 113) : imagecolorallocate($img, 235, 87, 87);
    $chgY = $unitY + 34 * $SS;
    $triSize = 9 * $SS;
    drawTrendTriangle($img, $padX + $triSize, $chgY + $triSize, $triSize, $up, $chgCol);
    $chgText = number_format(abs($dp), 2) . '%';
    cardText($img, $chgText, $padX + $triSize * 2 + 8 * $SS, $chgY, 22 * $SS, $chgCol, true, 'left');

    // نمودار سطحی رنگی پایین کارت
    $chartX0 = $padX; $chartX1 = $Wp - $padX;
    $chartY1 = $Hp - 60 * $SS;
    $chartY0 = $chartY1 - 165 * $SS;
    drawAreaChart($img, $series, $chartX0, $chartY0, $chartX1, $chartY1, $accent, 4 * $SS);

    // فوتر: یوزرنیم ربات چپ، تاریخ/ساعت راست
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y'), (int)date('n'), (int)date('j'));
    cardText($img, '@' . BOT_USERNAME, $padX, $Hp - 40 * $SS, 17 * $SS, $muted, false, 'left');
    cardText($img, sprintf('%04d/%02d/%02d  %s', $jy, $jm, $jd, date('H:i')), $Wp - $padX, $Hp - 40 * $SS, 17 * $SS, $muted, false, 'right');

    $out = imagecreatetruecolor($W, $H);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $W, $H, $Wp, $Hp);
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'rial') . '.png';
    imagepng($out, $tmp);
    imagedestroy($out);
    return is_file($tmp) ? $tmp : null;
}

/** برچسب داخل پیل گردگوشه. $x,$y گوشهٔ بالا (چپ/راست بر اساس $align). */
function pill($img, int $x, int $y, string $text, int $bg, int $fg, int $px, string $align = 'left'): void {
    $font = function_exists('imagettftext') ? findTtf(true) : null;
    if ($font) {
        $pt = $px * 0.70;
        $bb = imagettfbbox($pt, 0, $font, $text);
        $tw = abs($bb[2] - $bb[0]); $asc = abs($bb[7]);
    } else {
        $tw = imagefontwidth(5) * strlen($text); $asc = $px;
    }
    // پدینگ متناسب با اندازهٔ فونت (نه پیکسل ثابت) تا زیر سوپرسمپل (رسم در ابعاد چندبرابر و
    // کوچک‌سازی نهایی) هم نسبت درست حفظ شود و پیل بیش‌ازحد فشرده به‌نظر نرسد.
    $padH = (int)round($px * 0.62); $padV = (int)round($px * 0.36);
    $w = $tw + $padH * 2; $h = $asc + $padV * 2;
    $x0 = ($align === 'right') ? $x - $w : $x;
    roundedRect($img, $x0, $y, $x0 + $w, $y + $h, intdiv($h, 2), $bg);
    cardText($img, $text, $x0 + $padH, $y + $padV, $px, $fg, true, 'left');
}

/** کارت قیمت + چارت. اگر editMsgId داده شود، پیام موجود را ویرایش می‌کند. */
function sendPriceCard($chatId, string $base, string $interval = '30m', $editMsgId = null, $cbId = null, $replyTo = null): bool {
    if ($base === 'USDT') { return sendUsdtCard($chatId, $cbId, $replyTo); }
    $symbol = $base . 'USDT';
    $pc = fetchPriceChain($symbol, $base);
    if ($pc['price'] === null) {
        if ($cbId) { answerCallback($cbId, 'دریافت داده ناموفق بود.', true); }
        elseif ($editMsgId === null) { sendMessage($chatId, emo('no') . " نماد <b>" . h($base) . "</b> یافت نشد.", null, $replyTo); }
        return false;
    }
    // چارت مستقل از منبع قیمت واکشی می‌شود: حتی اگر آمار ۲۴ساعتهٔ بایننس در دسترس نبود،
    // ممکن است خود endpoint کندل بایننس یا CryptoCompare جواب بدهد — پس تقریباً همیشه چارت داریم.
    $caption = $pc['full'] ? buildPriceCaption($base, $pc['data']) : buildSimplePriceCaption($base, $pc['price']);
    $candles = fetchKlinesChain($symbol, $base, $interval);
    $chart = $candles ? renderCandlestickChart($candles, $symbol, $interval) : null;
    // بدون چارت، دکمه‌های تایم‌فریم بی‌فایده‌اند (هیچ منبعی کندل نداشت)؛ فقط دکمهٔ افزودن به گروه.
    // ایموجی «tfinfo» فقط روی ارسال اول (پیش از هرگونه تعویض تایم‌فریم) نشان داده می‌شود.
    $kb = $chart ? timeframeKeyboard($base, $interval, $editMsgId === null) : addGroupKeyboard();

    if ($chart) {
        if ($editMsgId !== null) { editPhotoMedia($chatId, $editMsgId, $chart, $caption, $kb); }
        else { sendPhotoFile($chatId, $chart, $caption, $kb, $replyTo); }
        @unlink($chart);
    } else {
        if ($editMsgId !== null) { editMessageText($chatId, $editMsgId, $caption, $kb); }
        else { sendMessage($chatId, $caption, $kb, $replyTo); }
    }
    if ($cbId) { answerCallback($cbId); }
    return true;
}
const DEFAULT_TPL_PRICE_SIMPLE = "💎 ارز {name} - {base} {{pe:coin}}\n\n┓━━❲ قیمت لحظه‌ای ❳\n┨≡{{pe:usd}} دلار: {price} $\n┚≡{{pe:toman}} تومن: {toman}\n";
/** کارت سادهٔ قیمت (بدون عکس چارت) — برای وقتی فقط قیمت لحظه‌ای از منبع پشتیبان در دسترس است */
function buildSimplePriceCaption(string $base, float $price): string {
    $name = coinName($base);
    $tmU = tomanFor($base, $price);
    $toman = $tmU !== null ? number_format(round($tmU)) . ' تومان' : '—';
    $tpl = getTemplate('tpl_price_simple', DEFAULT_TPL_PRICE_SIMPLE);
    return renderTemplate($tpl, ['{name}' => $name, '{base}' => $base, '{price}' => fmtPrice($price), '{toman}' => $toman]) . priceQuote();
}
const DEFAULT_TPL_USDT = "💎 ارز Tether - USDT {{pe:coin}}\n\n┓━━❲ قیمت لحظه ای ❳\n┨≡{{pe:usd}} دلار: 1.00 $\n┚≡{{pe:toman}} تومن: {toman}\n";
/** تتر: فقط قیمت تومانی (بدون چارت) */
function sendUsdtCard($chatId, $cbId = null, $replyTo = null): bool {
    $rls   = nobitexRls('usdt');
    $toman = $rls !== null ? number_format(round($rls / 10)) . ' تومان' : '—';
    $tpl = getTemplate('tpl_usdt', DEFAULT_TPL_USDT);
    $t = renderTemplate($tpl, ['{toman}' => $toman]) . priceQuote();
    sendMessage($chatId, $t, addGroupKeyboard(), $replyTo);
    if ($cbId) { answerCallback($cbId); }
    return true;
}

// ---- کارت‌های دلار و طلای بازار آزاد (منبع tgju) ----
const DEFAULT_TPL_DOLLAR = "💵 <b>نرخ دلار آمریکا</b> {{pe:r_usd}}\n\n┓━━❲ نرخ لحظه‌ای ❳\n┨≡ {{pe:r_toman}} تومان: <b>{toman}</b> تومان\n┨≡ {{pe:r_usd}} مقدار: <b>{qty}</b> $\n┚≡ {arrow} تغییر ۲۴س: {{pe:rg_change}} <b>{change}%</b>\n{hilo}";
/** قالب متن دلار — نسخهٔ خفن با کادر، نشان تغییرات ۲۴ساعته و سقف/کف (ایموجی پریمیوم: r_usd / r_toman / r_date) */
function buildDollarCaption(float $qty, float $priceToman, array $meta = []): string {
    $qtyStr = rtrim(rtrim(number_format($qty, 4, '.', ','), '0'), '.');
    $dp    = (float)($meta['dp'] ?? 0);
    $up    = (($meta['dt'] ?? 'high') !== 'low');
    $arrow = pe('mark') . ($up ? ' ▲' : ' ▼');
    $hilo = '';
    if (isset($meta['high']) && isset($meta['low'])) {
        $hilo = "\n┓━━❲ " . pe('rg_label') . " سقف کف امروز ❳\n" .
                "┨≡ " . pe('rg_high') . " سقف: <b>" . number_format(round($meta['high'])) . "</b> تومان\n" .
                "┚≡ " . pe('rg_low') . " کف: <b>" . number_format(round($meta['low'])) . "</b> تومان\n";
    }
    $tpl = getTemplate('tpl_dollar', DEFAULT_TPL_DOLLAR);
    $t = renderTemplate($tpl, [
        '{toman}' => number_format(round($priceToman)), '{qty}' => $qtyStr, '{arrow}' => $arrow,
        '{change}' => number_format(abs($dp), 2), '{hilo}' => $hilo,
    ]);
    return $t . "\n" . quoteBlock(pe('r_date') . " " . jalaliDateLine());
}

const DEFAULT_TPL_GOLD = "🥇 <b>طلای ۱۸ عیار</b> {{pe:mark}}\n\n┓━━❲ نرخ لحظه‌ای ❳\n┨≡ {{pe:g_qty}} وزن: <b>{qty}</b> گرم\n┨≡ {{pe:r_toman}} تومان: <b>{toman}</b> تومان\n┨≡ {{pe:mark}} دلاری: <b>\${usd}</b>\n┚≡ {arrow} تغییر ۲۴س: {{pe:rg_change}} <b>{change}%</b>\n{hilo}";
/** قالب متن طلای ۱۸ عیار — نسخهٔ خفن با کادر، نشان تغییرات و سقف/کف (ایموجی: g_qty / r_toman / r_usd) */
function buildGoldCaption(float $qty, float $priceToman, float $usdValue, array $meta = []): string {
    $qtyStr = rtrim(rtrim(number_format($qty, 4, '.', ','), '0'), '.');
    $dp    = (float)($meta['dp'] ?? 0);
    $up    = (($meta['dt'] ?? 'high') !== 'low');
    $arrow = pe('mark') . ($up ? ' ▲' : ' ▼');
    $hilo = '';
    if (isset($meta['high']) && isset($meta['low'])) {
        $hilo = "\n┓━━❲ " . pe('rg_label') . " سقف کف امروز ❳\n" .
                "┨≡ " . pe('rg_high') . " سقف: <b>" . number_format(round($meta['high'])) . "</b> تومان\n" .
                "┚≡ " . pe('rg_low') . " کف: <b>" . number_format(round($meta['low'])) . "</b> تومان\n";
    }
    $tpl = getTemplate('tpl_gold', DEFAULT_TPL_GOLD);
    $t = renderTemplate($tpl, [
        '{qty}' => $qtyStr, '{toman}' => number_format(round($priceToman)), '{usd}' => number_format($usdValue, 2),
        '{arrow}' => $arrow, '{change}' => number_format(abs($dp), 2), '{hilo}' => $hilo,
    ]);
    return $t . "\n" . quoteBlock(pe('r_date') . " " . jalaliDateLine());
}
/** خط تاریخ‌شمسی/ساعت مشترک برای قالب‌های دلار و طلا */
function jalaliDateLine(): string {
    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    return sprintf('%04d/%02d/%02d', $jy, $jm, $jd) . " | " . date('H:i:s', $ts);
}

/** ارسال کارت دلار: عکس مدرن (قیمت + نمودار) + کپشن قالب دلار */
function sendDollarCard($chatId, float $qty = 1.0, $replyTo = null): void {
    $data = tgjuData();
    if (!$data || empty($data['usd'])) {
        sendMessage($chatId, emo('no') . " دریافت قیمت دلار در حال حاضر ممکن نشد؛ کمی بعد دوباره تلاش کنید.", null, $replyTo);
        return;
    }
    if ($qty <= 0) { $qty = 1.0; }
    $total   = $data['usd']['toman'] * $qty;
    $meta    = ['dp' => $data['usd']['dp'], 'dt' => $data['usd']['dt']];
    if ($data['usd']['high'] !== null) { $meta['high'] = $data['usd']['high'] * $qty; }
    if ($data['usd']['low']  !== null) { $meta['low']  = $data['usd']['low']  * $qty; }
    $caption = buildDollarCaption($qty, $total, $meta);
    $series  = tgjuHistory('price_dollar_rl', 30);
    $img = renderRialCard('usd', $total, $series, [
        'qty' => $qty, 'dp' => $data['usd']['dp'], 'dt' => $data['usd']['dt'],
    ]);
    if ($img) { sendPhotoFile($chatId, $img, $caption, addGroupKeyboard(), $replyTo); @unlink($img); }
    else       { sendMessage($chatId, $caption, addGroupKeyboard(), $replyTo); }
}

/** ارسال کارت طلا: عکس مدرن (قیمت + نمودار) + کپشن قالب طلا */
function sendGoldCard($chatId, float $qty = 1.0, $replyTo = null): void {
    $data = tgjuData();
    if (!$data || empty($data['gold'])) {
        sendMessage($chatId, emo('no') . " دریافت قیمت طلا در حال حاضر ممکن نشد؛ کمی بعد دوباره تلاش کنید.", null, $replyTo);
        return;
    }
    if ($qty <= 0) { $qty = 1.0; }
    $total   = $data['gold']['toman'] * $qty;
    $usdRate = !empty($data['usd']) ? $data['usd']['toman'] : null;
    $usdVal  = ($usdRate && $usdRate > 0) ? $total / $usdRate : 0.0;
    $meta    = ['dp' => $data['gold']['dp'], 'dt' => $data['gold']['dt']];
    if ($data['gold']['high'] !== null) { $meta['high'] = $data['gold']['high'] * $qty; }
    if ($data['gold']['low']  !== null) { $meta['low']  = $data['gold']['low']  * $qty; }
    $caption = buildGoldCaption($qty, $total, $usdVal, $meta);
    $series  = tgjuHistory('geram18', 30);
    $img = renderRialCard('gold', $total, $series, [
        'qty' => $qty, 'dp' => $data['gold']['dp'], 'dt' => $data['gold']['dt'],
    ]);
    if ($img) { sendPhotoFile($chatId, $img, $caption, addGroupKeyboard(), $replyTo); @unlink($img); }
    else       { sendMessage($chatId, $caption, addGroupKeyboard(), $replyTo); }
}

// ==========================================================================
// 5) مدیریت گروه
// ==========================================================================
function lockLabels(): array {
    return [
        'link'     => '🔗 لینک',
        'forward'  => '↪️ فوروارد',
        'sticker'  => '🎯 استیکر',
        'photo'    => '🖼 عکس',
        'video'    => '🎬 ویدیو',
        'gif'      => '🎞 گیف',
        'voice'    => '🎙 ویس',
        'audio'    => '🎵 موزیک',
        'document' => '📎 فایل',
        'mention'  => '👤 منشن',
        'username' => '🆔 یوزرنیم',
        'viabot'   => '🤖 ربات اینلاین',
        'poll'     => '📊 نظرسنجی',
        'emoji'    => '😀 ایموجی پریمیوم',
        'contact'  => '📇 مخاطب',
        'location' => '📍 موقعیت',
    ];
}
function lockPersianNames(): array {
    return [
        'لینک'=>'link','فوروارد'=>'forward','استیکر'=>'sticker','عکس'=>'photo','ویدیو'=>'video','فیلم'=>'video',
        'گیف'=>'gif','ویس'=>'voice','موزیک'=>'audio','فایل'=>'document','منشن'=>'mention','یوزرنیم'=>'username',
        'ربات'=>'viabot','نظرسنجی'=>'poll','ایموجی'=>'emoji','مخاطب'=>'contact','موقعیت'=>'location','لوکیشن'=>'location',
    ];
}
function lockList($chatId): array {
    static $cache = [];
    if (isset($cache[$chatId])) { return $cache[$chatId]; }
    $st = db()->prepare("SELECT lock_type FROM locks WHERE chat_id=?");
    $st->execute([$chatId]);
    $out = [];
    foreach ($st->fetchAll() as $r) { $out[$r['lock_type']] = 1; }
    return $cache[$chatId] = $out;
}
function isLocked($chatId, string $type): bool {
    $l = lockList($chatId);
    return isset($l[$type]);
}
function setLock($chatId, string $type, bool $on): void {
    if ($on) {
        db()->prepare("INSERT OR IGNORE INTO locks(chat_id,lock_type) VALUES(?,?)")->execute([$chatId, $type]);
    } else {
        db()->prepare("DELETE FROM locks WHERE chat_id=? AND lock_type=?")->execute([$chatId, $type]);
    }
}

function msgHasEntityType(array $msg, string $type): bool {
    foreach (['entities', 'caption_entities'] as $ek) {
        if (!empty($msg[$ek])) {
            foreach ($msg[$ek] as $e) { if (($e['type'] ?? '') === $type) { return true; } }
        }
    }
    return false;
}
function msgViolatesLock(array $msg, string $type): bool {
    $text = $msg['text'] ?? ($msg['caption'] ?? '');
    switch ($type) {
        case 'link':
            if (msgHasEntityType($msg, 'url') || msgHasEntityType($msg, 'text_link')) { return true; }
            return (bool)preg_match('~(https?://|www\.|t\.me/|telegram\.me/|@[A-Za-z0-9_]{4,})~i', $text);
        case 'forward':
            return isset($msg['forward_origin']) || isset($msg['forward_from']) ||
                   isset($msg['forward_from_chat']) || isset($msg['forward_sender_name']);
        case 'sticker':  return isset($msg['sticker']);
        case 'photo':    return isset($msg['photo']);
        case 'video':    return isset($msg['video']) || isset($msg['video_note']);
        case 'gif':      return isset($msg['animation']);
        case 'voice':    return isset($msg['voice']);
        case 'audio':    return isset($msg['audio']);
        case 'document': return isset($msg['document']) && !isset($msg['animation']);
        case 'mention':  return msgHasEntityType($msg, 'mention') || msgHasEntityType($msg, 'text_mention');
        case 'username': return (bool)preg_match('/@[A-Za-z0-9_]{4,}/', $text);
        case 'viabot':   return isset($msg['via_bot']);
        case 'poll':     return isset($msg['poll']);
        case 'emoji':    return msgHasEntityType($msg, 'custom_emoji');
        case 'contact':  return isset($msg['contact']);
        case 'location': return isset($msg['location']) || isset($msg['venue']);
    }
    return false;
}
function enforceLocks($chatId, array $msg): bool {
    $locks = lockList($chatId);
    if (!$locks) { return false; }
    foreach (array_keys($locks) as $t) {
        if (msgViolatesLock($msg, $t)) {
            deleteMessage($chatId, $msg['message_id']);
            return true;
        }
    }
    return false;
}
function enforceFlood($chatId, array $msg): bool {
    $s = ensureGroupSettings($chatId);
    if ((int)($s['antiflood_on'] ?? 0) !== 1) { return false; }
    $uid = $msg['from']['id'];
    $limit = (int)($s['flood_limit'] ?? 6);
    $secs  = (int)($s['flood_secs'] ?? 5);
    $now = time();
    $st = db()->prepare("SELECT cnt, ts FROM flood WHERE chat_id=? AND user_id=?");
    $st->execute([$chatId, $uid]);
    $row = $st->fetch();
    if (!$row || ($now - (int)$row['ts']) > $secs) {
        db()->prepare("INSERT INTO flood(chat_id,user_id,cnt,ts) VALUES(?,?,1,?)
                       ON CONFLICT(chat_id,user_id) DO UPDATE SET cnt=1, ts=?")->execute([$chatId, $uid, $now, $now]);
        return false;
    }
    $cnt = (int)$row['cnt'] + 1;
    db()->prepare("UPDATE flood SET cnt=? WHERE chat_id=? AND user_id=?")->execute([$cnt, $chatId, $uid]);
    if ($cnt > $limit) {
        // سکوت ۲ دقیقه‌ای + حذف
        restrictMember($chatId, $uid, ['can_send_messages' => false], $now + 120);
        deleteMessage($chatId, $msg['message_id']);
        db()->prepare("UPDATE flood SET cnt=0, ts=? WHERE chat_id=? AND user_id=?")->execute([$now, $chatId, $uid]);
        sendMessage($chatId, pemo('shield') . " کاربر به دلیل فلاد ۲ دقیقه سکوت شد.");
        return true;
    }
    return false;
}
function enforceFilters($chatId, array $msg): bool {
    $text = $msg['text'] ?? ($msg['caption'] ?? '');
    if ($text === '') { return false; }
    $st = db()->prepare("SELECT word FROM filters WHERE chat_id=?");
    $st->execute([$chatId]);
    $rows = $st->fetchAll();
    if (!$rows) { return false; }
    $low = mb_strtolower($text);
    foreach ($rows as $r) {
        if (mb_strpos($low, mb_strtolower($r['word'])) !== false) {
            deleteMessage($chatId, $msg['message_id']);
            return true;
        }
    }
    return false;
}

function mentionUser(array $u): string {
    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    if ($name === '') { $name = 'کاربر'; }
    return '<a href="tg://user?id=' . $u['id'] . '">' . h($name) . '</a>';
}

/** پردازش دستورات مدیریتی گروه. اگر true برگرداند یعنی پیام مصرف شد. */
function handleGroupCommand($chatId, array $msg, string $text, bool $isAdmin): bool {
    $reply = $msg['reply_to_message'] ?? null;
    $target = $reply['from'] ?? null;
    $lower = mb_strtolower($text);
    $first = preg_split('/\s+/u', $text)[0] ?? $text;

    // اخبار / شاخص ترس‌وطمع / لیست ارزها / لیکویدیتی
    if ($lower === '/news' || strpos($lower, '/news@') === 0) { sendNewsCard($chatId); return true; }
    if ($lower === '/feargreed' || $lower === '/fng') { sendFearGreedCard($chatId); return true; }
    if ($first === '/list' || strpos(mb_strtolower($first), '/list@') === 0) { sendSupportedList($chatId); return true; }
    if ($first === '/liquidy' || $first === '/liquidity' || strpos(mb_strtolower($first), '/liquidy@') === 0) {
        $rest = trim(mb_substr($text, mb_strlen($first)));
        $liqBase = $rest !== '' ? (normalizeSymbol($rest) ?: 'BTC') : 'BTC';
        sendLiquidityCard($chatId, $liqBase);
        return true;
    }

    // پنل تنظیمات گروه
    if (in_array($lower, ['پنل', 'تنظیمات', 'settings', '/settings', '/panel'], true)) {
        if (!$isAdmin) { return true; }
        sendMessage($chatId, pemo('shield') . " <b>پنل مدیریت گروه</b>\nبرای روشن/خاموش کردن هر گزینه ضربه بزنید:", groupSettingsKeyboard($chatId));
        return true;
    }
    // قوانین
    if (in_array($lower, ['قوانین', 'rules', '/rules'], true)) {
        $s = ensureGroupSettings($chatId);
        $r = trim((string)($s['rules'] ?? ''));
        sendMessage($chatId, $r !== '' ? (pemo('star') . " <b>قوانین گروه</b>\n" . quoteExpandable(h($r))) : "قوانینی ثبت نشده است.");
        return true;
    }
    // ثبت قوانین: «تنظیم قوانین متن...»
    if (mb_strpos($text, 'تنظیم قوانین') === 0) {
        if (!$isAdmin) { return true; }
        $r = trim(mb_substr($text, mb_strlen('تنظیم قوانین')));
        updateGroupSetting($chatId, 'rules', $r);
        sendMessage($chatId, pemo('ok') . " قوانین ثبت شد.");
        return true;
    }
    // قفل/بازکردن با نام فارسی: «قفل لینک» / «باز کردن لینک»
    if (preg_match('/^(قفل|باز کردن|بازکردن|باز)\s+(\S+)/u', $text, $m)) {
        if (!$isAdmin) { return true; }
        $names = lockPersianNames();
        $key = $names[$m[2]] ?? null;
        if ($key) {
            $on = ($m[1] === 'قفل');
            setLock($chatId, $key, $on);
            sendMessage($chatId, ($on ? pemo('lock') . " قفل شد: " : pemo('unlock') . " باز شد: ") . lockLabels()[$key]);
            return true;
        }
    }

    // دستورات نیازمند ریپلای (بن/اخراج/سکوت/آزاد/اخطار/سنجاق/ترفیع/تنزل)
    if (in_array($lower, ['اخطار','warn','/warn','بن','ban','/ban','اخراج','kick','/kick','سکوت','mute','/mute',
                          'آزاد','unmute','/unmute','حذف اخطار','سنجاق','pin','/pin','ترفیع','promote','تنزل','demote'], true)
        || in_array($first, ['اخطار','warn','بن','ban','اخراج','kick','سکوت','mute','آزاد','unmute','سنجاق','pin','ترفیع','promote','تنزل','demote'], true)) {

        // آیدی بدون نیاز به ادمین
        if (!$isAdmin) { return true; }
        if (!$reply || !$target) {
            sendMessage($chatId, emo('no') . " روی پیام کاربر موردنظر ریپلای کنید.");
            return true;
        }
        $tid = $target['id'];
        if (isGroupAdmin($chatId, $tid)) {
            sendMessage($chatId, emo('no') . " این کاربر ادمین است.");
            return true;
        }
        // بن
        if (in_array($first, ['بن','ban'], true) || in_array($lower, ['بن','ban','/ban'], true)) {
            banMember($chatId, $tid);
            sendMessage($chatId, pemo('shield') . " " . mentionUser($target) . " از گروه بن شد.");
            return true;
        }
        // اخراج (بن و آنبن)
        if (in_array($first, ['اخراج','kick'], true)) {
            banMember($chatId, $tid); unbanMember($chatId, $tid);
            sendMessage($chatId, pemo('shield') . " " . mentionUser($target) . " اخراج شد.");
            return true;
        }
        // سکوت [دقیقه]
        if (in_array($first, ['سکوت','mute'], true)) {
            $parts = preg_split('/\s+/u', $text);
            $min = isset($parts[1]) && is_numeric($parts[1]) ? (int)$parts[1] : 0;
            $until = $min > 0 ? time() + $min * 60 : 0;
            restrictMember($chatId, $tid, [
                'can_send_messages' => false, 'can_send_audios' => false, 'can_send_documents' => false,
                'can_send_photos' => false, 'can_send_videos' => false, 'can_send_other_messages' => false,
            ], $until);
            $suffix = $min > 0 ? " به مدت $min دقیقه" : "";
            sendMessage($chatId, pemo('lock') . " " . mentionUser($target) . " سکوت شد$suffix.");
            return true;
        }
        // آزاد
        if (in_array($first, ['آزاد','unmute'], true)) {
            restrictMember($chatId, $tid, [
                'can_send_messages' => true, 'can_send_audios' => true, 'can_send_documents' => true,
                'can_send_photos' => true, 'can_send_videos' => true, 'can_send_other_messages' => true,
                'can_add_web_page_previews' => true, 'can_send_polls' => true,
            ]);
            sendMessage($chatId, pemo('unlock') . " " . mentionUser($target) . " آزاد شد.");
            return true;
        }
        // اخطار
        if (in_array($first, ['اخطار','warn'], true)) {
            return doWarn($chatId, $target, $isAdmin);
        }
        // سنجاق
        if (in_array($first, ['سنجاق','pin'], true)) {
            pinMessage($chatId, $reply['message_id']);
            sendMessage($chatId, pemo('star') . " پیام سنجاق شد.");
            return true;
        }
        // ترفیع
        if (in_array($first, ['ترفیع','promote'], true)) {
            promoteMember($chatId, $tid, true);
            sendMessage($chatId, pemo('admin') . " " . mentionUser($target) . " ادمین شد.");
            return true;
        }
        // تنزل
        if (in_array($first, ['تنزل','demote'], true)) {
            promoteMember($chatId, $tid, false);
            sendMessage($chatId, pemo('ok') . " " . mentionUser($target) . " تنزل یافت.");
            return true;
        }
    }

    // حذف اخطار
    if (in_array($lower, ['حذف اخطار','unwarn','رفع اخطار'], true)) {
        if (!$isAdmin) { return true; }
        if (!$reply || !$target) { sendMessage($chatId, emo('no') . " روی پیام کاربر ریپلای کنید."); return true; }
        db()->prepare("UPDATE warns SET count=MAX(0,count-1) WHERE chat_id=? AND user_id=?")->execute([$chatId, $target['id']]);
        sendMessage($chatId, pemo('ok') . " یک اخطار حذف شد.");
        return true;
    }
    // حذف پیام ریپلای‌شده
    if (in_array($lower, ['حذف','del','/del'], true)) {
        if (!$isAdmin) { return true; }
        if ($reply) { deleteMessage($chatId, $reply['message_id']); }
        deleteMessage($chatId, $msg['message_id']);
        return true;
    }
    // آیدی
    if (in_array($lower, ['ایدی','آیدی','id','/id'], true)) {
        $u = $target ?: $msg['from'];
        sendMessage($chatId, pemo('people') . " آیدی " . mentionUser($u) . ": <code>" . $u['id'] . "</code>");
        return true;
    }
    return false;
}

function doWarn($chatId, array $target, bool $isAdmin): bool {
    $tid = $target['id'];
    $s = ensureGroupSettings($chatId);
    $limit = (int)($s['warn_limit'] ?? 3);
    $action = $s['warn_action'] ?? 'mute';
    db()->prepare("INSERT INTO warns(chat_id,user_id,count) VALUES(?,?,1)
                   ON CONFLICT(chat_id,user_id) DO UPDATE SET count=count+1, updated_at=datetime('now')")->execute([$chatId, $tid]);
    $st = db()->prepare("SELECT count FROM warns WHERE chat_id=? AND user_id=?");
    $st->execute([$chatId, $tid]);
    $cnt = (int)($st->fetch()['count'] ?? 1);
    if ($cnt >= $limit) {
        if ($action === 'ban') {
            banMember($chatId, $tid);
            sendMessage($chatId, pemo('shield') . " " . mentionUser($target) . " به دلیل $limit اخطار بن شد.");
        } else {
            restrictMember($chatId, $tid, ['can_send_messages' => false]);
            sendMessage($chatId, pemo('lock') . " " . mentionUser($target) . " به دلیل $limit اخطار سکوت شد.");
        }
        db()->prepare("UPDATE warns SET count=0 WHERE chat_id=? AND user_id=?")->execute([$chatId, $tid]);
    } else {
        sendMessage($chatId, pemo('bell') . " اخطار به " . mentionUser($target) . " ($cnt از $limit)");
    }
    return true;
}

/** نام فارسیِ بدون ایموجی هر قفل (برای پنل مدیریت گروه که فقط باید متنی باشد) */
function lockLabelsPlain(): array {
    return [
        'link' => 'لینک', 'forward' => 'فوروارد', 'sticker' => 'استیکر', 'photo' => 'عکس',
        'video' => 'ویدیو', 'gif' => 'گیف', 'voice' => 'ویس', 'audio' => 'موزیک',
        'document' => 'فایل', 'mention' => 'منشن', 'username' => 'یوزرنیم', 'viabot' => 'ربات اینلاین',
        'poll' => 'نظرسنجی', 'emoji' => 'ایموجی پریمیوم', 'contact' => 'مخاطب', 'location' => 'موقعیت',
    ];
}
/** پنل مدیریت گروه — فقط متن، بدون هیچ ایموجی (نه یونیکد و نه پریمیوم) روی دکمه‌ها.
 *  همیشه مستقیم از دیتابیس می‌خواند (نه از کش استاتیک lockList) تا بعد از هر تغییر تازه باشد. */
function groupSettingsKeyboard($chatId): array {
    $st = db()->prepare("SELECT lock_type FROM locks WHERE chat_id=?");
    $st->execute([$chatId]);
    $locks = [];
    foreach ($st->fetchAll() as $r) { $locks[$r['lock_type']] = 1; }
    $s = ensureGroupSettings($chatId);
    $rows = [];
    $tmp = [];
    foreach (lockLabelsPlain() as $key => $lbl) {
        $on = isset($locks[$key]);
        $tmp[] = btn(($on ? 'قفل: ' : 'باز: ') . $lbl, "gs:lock:$key", $on ? 'success' : 'danger');
        if (count($tmp) === 2) { $rows[] = $tmp; $tmp = []; }
    }
    if ($tmp) { $rows[] = $tmp; }
    $wc = (int)($s['welcome_on'] ?? 0);
    $wr = (int)($s['welcome_reply'] ?? 0);
    $af = (int)($s['antiflood_on'] ?? 0);
    $pr = (int)($s['price_on'] ?? 1);
    $rows[] = [
        btn('خوش‌آمد: ' . ($wc ? 'روشن' : 'خاموش'), 'gs:welcome', $wc ? 'success' : 'danger'),
        btn('ضدفلاد: ' . ($af ? 'روشن' : 'خاموش'), 'gs:flood', $af ? 'success' : 'danger'),
    ];
    $rows[] = [
        btn('قیمت‌گیری: ' . ($pr ? 'روشن' : 'خاموش'), 'gs:price', $pr ? 'success' : 'danger'),
        btn('پاک‌سازی قفل‌ها', 'gs:clearlocks', 'primary'),
    ];
    $rows[] = [
        btn('ریپلای خوش‌آمد: ' . ($wr ? 'روشن' : 'خاموش'), 'gs:welcomereply', $wr ? 'success' : 'danger'),
        btn('ویرایش پیام خوش‌آمد', 'gs:welcometext', 'primary'),
    ];
    $rows[] = [btn('بستن', 'x', 'danger')];
    return ikb($rows);
}
/** نگهداشته‌شده برای سازگاری با فراخوانی‌های قبلی؛ اکنون معادل groupSettingsKeyboard است. */
function freshGroupSettingsKeyboard($chatId): array { return groupSettingsKeyboard($chatId); }

const DEFAULT_TPL_WELCOME = "{{pe:wave}} خوش آمدی {name} عزیز به {group}!";
// اعضای جدید / خروج
/**
 * پیام خوش‌آمدگویی: از قالب اختصاصی همان گروه (اگر تنظیم شده) وگرنه قالب سراسری پنل ادمین
 * (tpl_welcome) وگرنه پیش‌فرض کد استفاده می‌کند — پس هم از پنل ادمین (سراسری) و هم از پنل
 * مدیریت گروه (اختصاصی) قابل‌ویرایش است و از {{pe:key}} و {{quote}} پشتیبانی می‌کند.
 * اگر «ریپلای روی پیام کاربر» در تنظیمات گروه روشن باشد، روی پیام سرویسی ورود عضو ریپلای می‌شود.
 */
function handleNewMembers($chatId, array $chat, array $members, $serviceMsgId = null): void {
    $meBot = false;
    foreach ($members as $m) {
        if (!empty($m['is_bot']) && ($m['username'] ?? '') !== '' && isBotSelf($m)) { $meBot = true; }
    }
    registerGroup($chat);
    ensureGroupSettings($chatId);
    if ($meBot) {
        sendMessage($chatId, pemo('wave') . " سلام! من اضافه شدم.\nبرای مدیریت، مرا <b>ادمین</b> کنید و <code>پنل</code> را بفرستید.\nاعضا می‌توانند نماد ارز (مثل <code>btc</code>) بفرستند تا قیمت بگیرند.");
        return;
    }
    $s = ensureGroupSettings($chatId);
    if ((int)($s['welcome_on'] ?? 0) !== 1) { return; }
    $tpl = trim((string)($s['welcome_text'] ?? ''));
    if ($tpl === '') { $tpl = getTemplate('tpl_welcome', DEFAULT_TPL_WELCOME); }
    $count = getChatMemberCount($chatId);
    $replyTo = ((int)($s['welcome_reply'] ?? 0) === 1) ? $serviceMsgId : null;
    foreach ($members as $m) {
        if (isBotSelf($m)) { continue; }
        $txt = renderTemplate($tpl, [
            '{name}'  => mentionUser($m),
            '{group}' => h($chat['title'] ?? ''),
            '{count}' => (string)$count,
        ]);
        $txt .= "\n" . quoteBlock(pe('date') . ' ' . jalaliDateLine());
        sendMessage($chatId, $txt, null, $replyTo);
    }
}
function handleLeftMember($chatId, array $member): void {
    $s = ensureGroupSettings($chatId);
    if ((int)($s['goodbye_on'] ?? 0) !== 1) { return; }
    if (isBotSelf($member)) { return; }
    $tpl = trim((string)($s['goodbye_text'] ?? ''));
    if ($tpl === '') { $tpl = pemo('wave') . " {name} گروه را ترک کرد."; }
    sendMessage($chatId, strtr($tpl, ['{name}' => mentionUser($member)]));
}
function isBotSelf(array $u): bool {
    static $selfId = null;
    if ($selfId === null) {
        $me = tgApi('getMe');
        $selfId = ($me && !empty($me['ok'])) ? (int)$me['result']['id'] : 0;
    }
    return (int)($u['id'] ?? 0) === $selfId;
}

// ==========================================================================
// 6) پنل ادمین سراسری
// ==========================================================================
function adminHomeKeyboard(): array {
    $q = getSetting('quote_mode', '0') === '1';
    return ikb([
        [btn(emo('chart') . ' آمار', 'ap:stats', 'primary', 'chart')],
        [btn(emo('star') . ' متن استارت', 'ap:start', 'primary', 'star'),
         btn(($q ? '✅' : '❌') . ' حالت نقل‌قول', 'ap:quote', $q ? 'success' : 'danger')],
        [btn('✏️ ویرایش متن‌های ربات', 'ap:tpl', 'primary')],
        [btn('🔘 برچسب دکمه‌ها', 'ap:btn', 'primary')],
        [btn('🔌 مدیریت APIها', 'ap:api', 'primary'), btn('🎨 رنگ چارت', 'ap:chartcolor', 'primary')],
        [btn('🌐 پروکسی تلگرام', 'ap:tgproxy', 'primary')],
        [btn(emo('bell') . ' پیام همگانی', 'ap:bc', 'primary', 'bell')],
        [btn(emo('admin') . ' ادمین‌ها', 'ap:admins', 'primary', 'admin'),
         btn('🔗 جوین اجباری', 'ap:fj', 'primary')],
        [btn(emo('no') . ' بستن', 'x', 'danger', 'no')],
    ]);
}
function showAdminPanel($chatId, $editMsgId = null): void {
    $txt = pemo('shield') . " <b>پنل مدیریت ربات</b>\nیکی از گزینه‌ها را انتخاب کنید:";
    if ($editMsgId) { editMessageText($chatId, $editMsgId, $txt, adminHomeKeyboard()); }
    else { sendMessage($chatId, $txt, adminHomeKeyboard()); }
}
function getStats(): array {
    $u  = (int)db()->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
    $ga = (int)db()->query("SELECT COUNT(*) c FROM groups WHERE is_active=1")->fetch()['c'];
    $gt = (int)db()->query("SELECT COUNT(*) c FROM groups")->fetch()['c'];
    $ad = (int)db()->query("SELECT COUNT(*) c FROM admins")->fetch()['c'];
    $today = date('Y-m-d');
    $st = db()->prepare("SELECT COUNT(*) c FROM users WHERE substr(created_at,1,10)=?");
    $st->execute([$today]);
    $nu = (int)$st->fetch()['c'];
    return ['users' => $u, 'groups_active' => $ga, 'groups_total' => $gt, 'admins' => $ad, 'today' => $nu];
}
/**
 * مجموع تعداد اعضای همهٔ گروه‌های فعال ربات (برای هر گروه یک فراخوانی getChatMemberCount).
 * چون این کار می‌تواند برای تعداد زیاد گروه کند باشد، نتیجه ۱۰ دقیقه کش می‌شود؛ $force=true
 * کش را نادیده می‌گیرد و دوباره محاسبه می‌کند (دکمهٔ «بروزرسانی» در پنل آمار).
 */
function getTotalMembers(bool $force = false): int {
    if (!$force) {
        $cached = getSetting('total_members_cache');
        $ts = (int) getSetting('total_members_cache_ts', '0');
        if ($cached !== null && (time() - $ts) < 600) { return (int)$cached; }
    }
    $rows = db()->query("SELECT chat_id FROM groups WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
    $total = 0;
    foreach ($rows as $gid) { $total += getChatMemberCount($gid); }
    setSetting('total_members_cache', (string)$total);
    setSetting('total_members_cache_ts', (string)time());
    return $total;
}
function showStats($chatId, $editMsgId, bool $forceMembers = false): void {
    $s = getStats();
    $members = getTotalMembers($forceMembers);
    $txt  = pemo('chart') . " <b>آمار ربات</b>\n";
    $txt .= quoteBlock(
        pemo('people') . " کاربران: <b>{$s['users']}</b>\n" .
        "➕ کاربران امروز: <b>{$s['today']}</b>\n" .
        emo('people') . " گروه‌های فعال: <b>{$s['groups_active']}</b> (کل: {$s['groups_total']})\n" .
        pemo('people') . " مجموع اعضای گروه‌ها: <b>" . number_format($members) . "</b>\n" .
        pemo('admin') . " ادمین‌ها: <b>{$s['admins']}</b>"
    );
    editMessageText($chatId, $editMsgId, $txt, ikb([
        [btn('بروزرسانی آمار اعضا', 'ap:statsrefresh', 'primary')],
        [backBtn('ap:home', 'primary')],
    ]));
}
function showAdminsList($chatId, $editMsgId): void {
    $rows = db()->query("SELECT chat_id, role FROM admins ORDER BY role")->fetchAll();
    $txt = pemo('admin') . " <b>ادمین‌های ربات</b>\n";
    $kbRows = [];
    foreach ($rows as $r) {
        $isOwner = ($r['role'] === 'owner');
        $txt .= "• <code>{$r['chat_id']}</code> — " . ($isOwner ? 'مالک' : 'ادمین') . "\n";
        if (!$isOwner) {
            $kbRows[] = [btn(emo('no') . " حذف {$r['chat_id']}", "ap:admdel:{$r['chat_id']}", 'danger', 'no')];
        }
    }
    $kbRows[] = [btn('➕ افزودن ادمین', 'ap:admadd', 'success')];
    $kbRows[] = [backBtn('ap:home', 'primary')];
    editMessageText($chatId, $editMsgId, $txt, ikb($kbRows));
}
function showForceJoin($chatId, $editMsgId): void {
    $rows = db()->query("SELECT channel, is_active FROM force_join")->fetchAll();
    $txt = "🔗 <b>جوین اجباری</b>\n";
    $kbRows = [];
    if (!$rows) { $txt .= "کانالی ثبت نشده است.\n"; }
    foreach ($rows as $r) {
        $on = (int)$r['is_active'] === 1;
        $txt .= "• " . h($r['channel']) . " — " . ($on ? 'فعال' : 'غیرفعال') . "\n";
        $kbRows[] = [
            btn(($on ? '✅' : '❌') . ' ' . $r['channel'], "ap:fjtoggle:{$r['channel']}", $on ? 'success' : 'danger'),
            btn(emo('no'), "ap:fjdel:{$r['channel']}", 'danger'),
        ];
    }
    $kbRows[] = [btn('➕ افزودن کانال', 'ap:fjadd', 'success')];
    $kbRows[] = [backBtn('ap:home', 'primary')];
    editMessageText($chatId, $editMsgId, $txt, ikb($kbRows));
}

// ==========================================================================
// 6‑ب) پنل ادمین: ویرایش قالب‌های متنی / برچسب دکمه‌ها / APIها / رنگ چارت
// ==========================================================================
function showTemplateList($chatId, $editMsgId): void {
    $rows = [];
    foreach (templateRegistry() as $key => $label) {
        $customized = getBotText($key) !== null ? ' ✓' : '';
        $rows[] = [btn($label . $customized, "ap:tpl:$key", 'primary')];
    }
    $rows[] = [backBtn('ap:home', 'primary')];
    $txt = "✏️ <b>ویرایش متن‌های ربات</b>\n" .
        "یکی از قالب‌ها را انتخاب کنید. علامت ✓ یعنی از پیش‌فرض کد سفارشی‌سازی شده.\n" .
        quoteExpandable(
            "می‌توانید از این نشانه‌ها داخل متن استفاده کنید:\n" .
            "• <code>{{quote}}متن{{/quote}}</code> برای نقل‌قول (blockquote)\n" .
            "• <code>{{equote}}متن{{/equote}}</code> برای نقل‌قول قابل‌گسترش\n" .
            "• <code>{{pe:coin}}</code> ، <code>{{pe:date}}</code> و... برای ایموجی پریمیوم\n" .
            "کلیدهای ایموجی مجاز:\n<code>" . h(implode('، ', templateEmojiKeys())) . "</code>"
        );
    editMessageText($chatId, $editMsgId, $txt, ikb($rows));
}
function showTemplateEdit($chatId, $editMsgId, string $key): void {
    if (!isset(templateRegistry()[$key])) { showTemplateList($chatId, $editMsgId); return; }
    setState($chatId, 'set_tpl', $key);
    $current = getBotText($key);
    $txt = "✏️ <b>" . h(templateRegistry()[$key]) . "</b>\n\n";
    $txt .= "متن جدید را ارسال کنید (HTML، <code>{{pe:key}}</code> و <code>{{quote}}...{{/quote}}</code> مجاز است).\n";
    $txt .= $current !== null
        ? "متن فعلی (سفارشی):\n" . quoteExpandable(h($current))
        : "در حال حاضر از پیش‌فرض کد استفاده می‌شود.";
    $rows = [];
    if ($current !== null) { $rows[] = [btn('بازگشت به پیش‌فرض', "ap:tplreset:$key", 'danger')]; }
    $rows[] = [btn('انصراف', 'ap:tpl', 'danger')];
    editMessageText($chatId, $editMsgId, $txt, ikb($rows));
}

function showBtnLabelList($chatId, $editMsgId): void {
    $rows = [];
    foreach (btnLabelRegistry() as $key => [$label, $def]) {
        $customized = getBotText($key) !== null ? ' ✓' : '';
        $rows[] = [btn($label . $customized, "ap:btn:$key", 'primary')];
    }
    $rows[] = [backBtn('ap:home', 'primary')];
    editMessageText($chatId, $editMsgId, "🔘 <b>برچسب دکمه‌های شیشه‌ای</b>\nیکی را برای ویرایش انتخاب کنید:", ikb($rows));
}
function showBtnLabelEdit($chatId, $editMsgId, string $key): void {
    if (!isset(btnLabelRegistry()[$key])) { showBtnLabelList($chatId, $editMsgId); return; }
    setState($chatId, 'set_btn', $key);
    [$label, $def] = btnLabelRegistry()[$key];
    $current = btnLabel($key);
    $txt = "🔘 <b>" . h($label) . "</b>\nمتن جدید دکمه را بفرستید (حداکثر ۶۴ کاراکتر).\nمقدار فعلی: <code>" . h($current) . "</code>\nپیش‌فرض: <code>" . h($def) . "</code>";
    $rows = [];
    if (getBotText($key) !== null) { $rows[] = [btn('بازگشت به پیش‌فرض', "ap:btnreset:$key", 'danger')]; }
    $rows[] = [btn('انصراف', 'ap:btn', 'danger')];
    editMessageText($chatId, $editMsgId, $txt, ikb($rows));
}

function showApiList($chatId, $editMsgId): void {
    $rows = [];
    foreach (apiRegistry() as $key => [$label, $def]) {
        $customized = trim((string) getSetting('api_' . $key, '')) !== '' ? ' ✓' : '';
        $rows[] = [btn($label . $customized, "ap:api:$key", 'primary')];
    }
    $rows[] = [backBtn('ap:home', 'primary')];
    editMessageText($chatId, $editMsgId, "🔌 <b>مدیریت APIها</b>\nهر API را جداگانه می‌توانید تغییر دهید. علامت ✓ یعنی سفارشی شده.", ikb($rows));
}
function showApiEdit($chatId, $editMsgId, string $key): void {
    if (!isset(apiRegistry()[$key])) { showApiList($chatId, $editMsgId); return; }
    setState($chatId, 'set_api', $key);
    [$label, $def] = apiRegistry()[$key];
    $current = apiBase($key, $def);
    $txt = "🔌 <b>" . h($label) . "</b>\nآدرس پایهٔ جدید را بفرستید (با http:// یا https://):\nمقدار فعلی: <code>" . h($current) . "</code>";
    $rows = [];
    if (trim((string) getSetting('api_' . $key, '')) !== '') { $rows[] = [btn('بازگشت به پیش‌فرض', "ap:apireset:$key", 'danger')]; }
    $rows[] = [btn('انصراف', 'ap:api', 'danger')];
    editMessageText($chatId, $editMsgId, $txt, ikb($rows));
}

/** رنگ فعلی چارت: کلید bg/up/down → کد هگز (بدون #) */
function chartColor(string $field, string $default): string {
    $v = trim((string) getSetting('chart_color_' . $field, ''));
    return $v !== '' ? $v : $default;
}
/** اعتبارسنجی/نرمال‌سازی ورودی رنگ هگز کاربر → رشتهٔ ۶ کاراکتری بدون # ، یا null اگر نامعتبر */
function normalizeHexColor(string $s): ?string {
    $s = ltrim(trim($s), '#');
    if (preg_match('/^[0-9a-fA-F]{6}$/', $s)) { return strtoupper($s); }
    if (preg_match('/^[0-9a-fA-F]{3}$/', $s)) {
        return strtoupper($s[0] . $s[0] . $s[1] . $s[1] . $s[2] . $s[2]);
    }
    return null;
}
function applyChartColorPreset(string $preset): void {
    $presets = [
        'default' => ['000000', '2169ED', 'FFFFFF'], // پیش‌فرض: سیاه، صعودی آبی پررنگ، نزولی سفید
        'classic' => ['131722', '26A69A', 'EF5350'],  // سبک کلاسیک: سبز/قرمز روی زمینهٔ تیرهٔ آبی
        'purple'  => ['0A0A14', '9D7BFF', 'FF7BD5'],
    ];
    if (!isset($presets[$preset])) { return; }
    [$bg, $up, $down] = $presets[$preset];
    setSetting('chart_color_bg', $bg); setSetting('chart_color_up', $up); setSetting('chart_color_down', $down);
}
function showChartColorMenu($chatId, $editMsgId): void {
    $bg = chartColor('bg', '000000'); $up = chartColor('up', '2169ED'); $down = chartColor('down', 'FFFFFF');
    $txt = "🎨 <b>رنگ چارت</b>\n" . quoteBlock(
        "پس‌زمینه: <code>#$bg</code>\n" .
        "کندل صعودی: <code>#$up</code>\n" .
        "کندل نزولی: <code>#$down</code>"
    ) . "\nیک پیش‌فرض انتخاب کنید یا هرکدام را جداگانه با کد هگز تنظیم کنید:";
    $rows = [
        [btn('پیش‌فرض (سیاه/آبی/سفید)', 'ap:cc:preset:default', 'primary')],
        [btn('کلاسیک (سبز/قرمز)', 'ap:cc:preset:classic', 'primary')],
        [btn('بنفش/صورتی', 'ap:cc:preset:purple', 'primary')],
        [btn('پس‌زمینه سفارشی', 'ap:cc:set:bg', 'success'),
         btn('صعودی سفارشی', 'ap:cc:set:up', 'success'),
         btn('نزولی سفارشی', 'ap:cc:set:down', 'success')],
        [backBtn('ap:home', 'primary')],
    ];
    editMessageText($chatId, $editMsgId, $txt, ikb($rows));
}

function doBroadcast($fromChat, $messageId): array {
    $rows = db()->query("SELECT chat_id FROM users WHERE is_banned=0")->fetchAll();
    $ok = 0; $fail = 0;
    foreach ($rows as $r) {
        $res = copyMessage($r['chat_id'], $fromChat, $messageId);
        if ($res && !empty($res['ok'])) { $ok++; } else { $fail++; }
        usleep(40000); // ~25 پیام در ثانیه
    }
    return ['ok' => $ok, 'fail' => $fail];
}

// جوین اجباری (PV)
function forceJoinChannels(): array {
    return db()->query("SELECT channel FROM force_join WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
}
function userJoinedAll($userId): array {
    $missing = [];
    foreach (forceJoinChannels() as $ch) {
        $r = tgApi('getChatMember', ['chat_id' => $ch, 'user_id' => $userId]);
        $status = ($r && !empty($r['ok'])) ? ($r['result']['status'] ?? '') : 'left';
        if (!in_array($status, ['member', 'administrator', 'creator'], true)) { $missing[] = $ch; }
    }
    return $missing;
}
function forceJoinKeyboard(array $missing): array {
    $rows = [];
    foreach ($missing as $ch) {
        $u = ltrim($ch, '@');
        $rows[] = [btnUrl('📢 عضویت در ' . $ch, 'https://t.me/' . $u, 'primary')];
    }
    $rows[] = [btn(emo('ok') . ' عضو شدم', 'fj:check', 'success', 'ok')];
    return ikb($rows);
}
/** دروازه جوین اجباری در PV. true = مجاز، false = باید عضو شود */
function pvGate($userId, $chatId, $cbId = null): bool {
    if (isGlobalAdmin($userId)) { return true; }
    $missing = userJoinedAll($userId);
    if (!$missing) { return true; }
    $txt = pemo('lock') . " برای استفاده از ربات ابتدا در کانال‌های زیر عضو شوید:";
    if ($cbId) { answerCallback($cbId, 'هنوز عضو همه کانال‌ها نیستید.', true); }
    sendMessage($chatId, $txt, forceJoinKeyboard($missing));
    return false;
}

// ==========================================================================
// 7) ابزارهای ترون
// ==========================================================================
function tronConSci($value): string {
    $v = (string)$value;
    if (stripos($v, 'E') !== false) {
        $p = explode('E-', $v);
        if (count($p) === 2) {
            $dec = (int)$p[1] + 1;
            $value = sprintf('%.' . $dec . 'f', (float)$value);
        }
    }
    return rtrim(rtrim((string)$value, '0'), '.') ?: '0';
}
/** دیکد Base58 به رشتهٔ بایت خام (بدون وابستگی به bcmath/gmp — با اریتمتیک آرایه‌ای پایه‌٢٥٦) */
function base58Decode(string $s): ?string {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $bytes = [0];
    for ($i = 0; $i < strlen($s); $i++) {
        $c = strpos($alphabet, $s[$i]);
        if ($c === false) { return null; }
        $carry = $c;
        for ($j = count($bytes) - 1; $j >= 0; $j--) {
            $x = $bytes[$j] * 58 + $carry;
            $bytes[$j] = $x & 0xFF;
            $carry = $x >> 8;
        }
        while ($carry > 0) {
            array_unshift($bytes, $carry & 0xFF);
            $carry >>= 8;
        }
    }
    $leading = 0;
    for ($i = 0; $i < strlen($s) && $s[$i] === '1'; $i++) { $leading++; }
    return str_repeat("\x00", $leading) . implode('', array_map('chr', $bytes));
}
/**
 * اعتبارسنجی آدرس ترون به‌صورت محلی (Base58Check: پیشوند 0x41 + هش‌۲۰بایتی + چک‌سام ۴بایتی
 * از دو بار SHA256). این روش به هیچ API خارجی وابسته نیست، بنابراین دیگر در صورت کندی/خطای
 * سرویس trongrid، آدرس‌های معتبر به‌اشتباه رد نمی‌شوند (رفع باگ «چک نمی‌کند»).
 */
function tronValidate(string $address): bool {
    $address = trim($address);
    if (!preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address)) { return false; }
    $raw = base58Decode($address);
    if ($raw === null || strlen($raw) !== 25) { return false; }
    $payload  = substr($raw, 0, 21);
    $checksum = substr($raw, 21, 4);
    if (ord($payload[0]) !== 0x41) { return false; }
    $hash = hash('sha256', hash('sha256', $payload, true), true);
    return substr($hash, 0, 4) === $checksum;
}
function tronTxInfo(string $hash): string {
    $hash = str_replace(
        ['https://www.tronscan.org/#/transaction/', 'https://tronscan.org/#/transaction/', 'tronscan.org/#/transaction/', '?lang=en'],
        '', trim($hash)
    );
    if (!preg_match('/^[A-Fa-f0-9]{64}$/', $hash)) { return emo('no') . " هش تراکنش نامعتبر است."; }
    $j = httpGet(apiBase('tronscan', 'https://apilist.tronscanapi.com') . '/api/transaction-info?hash=' . $hash);
    if (!$j) { return emo('no') . " دریافت اطلاعات ناموفق بود."; }
    $t = json_decode($j, true);
    if (empty($t['contractRet'])) { return emo('no') . " هش تراکنش اشتباه است."; }
    $ts = (int)(($t['timestamp'] ?? 0) / 1000);
    $ret = $t['contractRet'];
    $cd = $t['contractData'] ?? [];

    if (($cd['to_address'] ?? null) === null && !empty($t['trc20TransferInfo'][0])) {
        $tr = $t['trc20TransferInfo'][0];
        $amount = tronConSci(($tr['amount_str'] ?? 0) / pow(10, (int)($tr['decimals'] ?? 6)));
        return tronTxCard($ret, $amount, $tr['symbol'] ?? '?', $cd['owner_address'] ?? '', $tr['to_address'] ?? '', $ts, $hash, 'TRC20');
    }
    if (($cd['asset_name'] ?? null) === null) {
        $amount = tronConSci(($cd['amount'] ?? 0) / 1000000);
        return tronTxCard($ret, $amount, 'TRX', $cd['owner_address'] ?? '', $cd['to_address'] ?? '', $ts, $hash, 'COIN');
    }
    $ti = $cd['tokenInfo'] ?? [];
    $amount = tronConSci(($cd['amount'] ?? 0) / pow(10, (int)($ti['tokenDecimal'] ?? 6)));
    return tronTxCard($ret, $amount, $ti['tokenAbbr'] ?? '?', $cd['owner_address'] ?? '', $cd['to_address'] ?? '', $ts, $hash, 'TRC10');
}
function tronTxCard($ret, $amount, $sym, $from, $to, $ts, $hash, $type): string {
    $ok = ($ret === 'SUCCESS');
    $txt  = pemo('tron') . " <b>اطلاعات تراکنش ترون</b>\n";
    $txt .= quoteBlock(
        "وضعیت: " . ($ok ? pemo('ok') . " موفق" : emo('no') . " $ret") . "\n" .
        "نوع: <b>$type</b>\n" .
        pemo('money') . " مقدار: <b>" . h($amount) . " " . h($sym) . "</b>\n" .
        "از: <code>" . h($from) . "</code>\n" .
        "به: <code>" . h($to) . "</code>\n" .
        pemo('cal') . " " . date('Y/m/d H:i:s', $ts)
    );
    $txt .= "\n🔗 <code>" . h($hash) . "</code>";
    return $txt;
}
function tronWalletTokens(string $address): string {
    $address = trim($address);
    if (!tronValidate($address)) { return emo('no') . " آدرس ولت نامعتبر است."; }
    $j = httpGet(apiBase('tronscan', 'https://apilist.tronscanapi.com') . '/api/account/tokens?address=' . urlencode($address) . '&start=0&limit=50');
    if (!$j) { return emo('no') . " دریافت اطلاعات ناموفق بود."; }
    $d = json_decode($j, true);
    $data = $d['data'] ?? [];
    if (!$data) { return emo('no') . " توکنی برای این آدرس یافت نشد."; }
    $lines = pemo('wallet') . " <b>موجودی ولت</b>\n<code>" . h($address) . "</code>\n";
    $body = '';
    $c = 0;
    foreach ($data as $it) {
        $dec = (int)($it['tokenDecimal'] ?? 0);
        $raw = (string)($it['balance'] ?? '0');
        $amt = $dec > 0 ? (float)$raw / pow(10, $dec) : (float)$raw;
        if ($amt <= 0) { continue; }
        $sym = $it['tokenAbbr'] ?? ($it['tokenName'] ?? '?');
        $usd = isset($it['tokenPriceInUsd']) ? (' ≈ $' . number_format($amt * (float)$it['tokenPriceInUsd'], 2)) : '';
        $body .= "• <b>" . h($sym) . "</b>: " . h(rtrim(rtrim(number_format($amt, 6, '.', ''), '0'), '.')) . h($usd) . "\n";
        if (++$c >= 20) { break; }
    }
    if ($body === '') { return emo('no') . " موجودی قابل‌نمایشی یافت نشد."; }
    return $lines . quoteExpandable($body);
}
function tronTrc20Transfers(string $address, int $page = 1): array {
    $address = trim($address);
    if (!tronValidate($address)) { return ['text' => emo('no') . " آدرس نامعتبر است.", 'kb' => null]; }
    if ($page < 1) { $page = 1; }
    $per = 10;
    $start = ($page - 1) * $per;
    $j = httpGet(apiBase('tronscan', 'https://apilist.tronscanapi.com') . '/api/new/token_trc20/transfers?limit=' . $per . '&start=' . $start . '&relatedAddress=' . urlencode($address));
    if (!$j) { return ['text' => emo('no') . " دریافت اطلاعات ناموفق بود.", 'kb' => null]; }
    $d = json_decode($j, true);
    $total = (int)($d['total'] ?? 0);
    $items = $d['token_transfers'] ?? [];
    if (!$items) { return ['text' => emo('no') . " انتقال TRC20 یافت نشد.", 'kb' => null]; }
    $pages = max(1, (int)ceil($total / $per));
    $body = '';
    foreach ($items as $it) {
        $dec = (int)($it['tokenInfo']['tokenDecimal'] ?? 6);
        $amt = (float)($it['quant'] ?? 0) / pow(10, $dec);
        $sym = $it['tokenInfo']['tokenAbbr'] ?? '?';
        $dir = (strcasecmp($it['from_address'] ?? '', $address) === 0) ? '🔴 خروج' : '🟢 ورود';
        $body .= "$dir <b>" . h(rtrim(rtrim(number_format($amt, 6, '.', ''), '0'), '.')) . " " . h($sym) . "</b>\n" .
                 "  " . date('Y/m/d H:i', (int)(($it['block_ts'] ?? 0) / 1000)) . "\n";
    }
    $txt = pemo('tron') . " <b>انتقال‌های TRC20</b> (صفحه $page از $pages)\n<code>" . h($address) . "</code>\n" . quoteExpandable($body);
    $nav = [];
    if ($page > 1)      { $nav[] = btn(emo('back') . ' قبلی', "trp:$address:" . ($page - 1), 'primary', 'back'); }
    if ($page < $pages) { $nav[] = btn('بعدی ▶️', "trp:$address:" . ($page + 1), 'primary'); }
    $kbRows = [];
    if ($nav) { $kbRows[] = $nav; }
    $kbRows[] = [btn(emo('no') . ' بستن', 'x', 'danger', 'no')];
    return ['text' => $txt, 'kb' => ikb($kbRows)];
}

// --------------------------------------------------------------------------
// ولت چند‌شبکه‌ای: ترون / تون / بی‌ان‌بی
// --------------------------------------------------------------------------
function looksLikeEvmAddress(string $t): bool { return (bool)preg_match('/^0x[0-9a-fA-F]{40}$/', trim($t)); }
function looksLikeTonAddress(string $t): bool {
    $t = trim($t);
    return (bool)preg_match('/^(EQ|UQ|kQ|0Q)[A-Za-z0-9_-]{46}$/', $t)
        || (bool)preg_match('/^-?0:[0-9a-fA-F]{64}$/', $t);
}
/** تشخیص شبکهٔ آدرس ولت → [کلید, نام فارسی] یا null */
function detectWalletChain(string $t): ?array {
    $t = trim($t);
    if (looksLikeTronAddress($t)) { return ['tron', 'ترون']; }
    if (looksLikeTonAddress($t))  { return ['ton',  'تون']; }
    if (looksLikeEvmAddress($t))  { return ['bnb',  'بی‌ان‌بی']; }
    return null;
}
/** تومان به‌ازای هر دلار (نوبیتکس تتر، وگرنه بازار آزاد tgju) */
function tomanPerUsd(): ?float {
    $u = nobitexRls('usdt');
    if ($u !== null && $u > 0) { return $u / 10; }
    $g = tgjuData();
    if (!empty($g['usd']['toman'])) { return (float)$g['usd']['toman']; }
    return null;
}
/** ارزش دلاری کل ولت ترون (مجموع همهٔ توکن‌ها) */
function tronWalletUsd(string $address): ?array {
    $j = httpGet(apiBase('tronscan', 'https://apilist.tronscanapi.com') . '/api/account/tokens?address=' . urlencode($address) . '&start=0&limit=50');
    if (!$j) { return null; }
    $d = json_decode($j, true);
    $data = $d['data'] ?? null;
    if (!is_array($data)) { return null; }
    $usd = 0.0; $native = 0.0;
    foreach ($data as $it) {
        $dec = (int)($it['tokenDecimal'] ?? 0);
        $raw = (string)($it['balance'] ?? '0');
        $amt = $dec > 0 ? (float)$raw / pow(10, $dec) : (float)$raw;
        if ($amt <= 0) { continue; }
        $price = isset($it['tokenPriceInUsd']) ? (float)$it['tokenPriceInUsd'] : 0.0;
        $usd += $amt * $price;
        $sym = strtoupper($it['tokenAbbr'] ?? ($it['tokenName'] ?? ''));
        if ($sym === 'TRX' || ($it['tokenId'] ?? '') === '_') { $native += $amt; }
    }
    return ['usd' => $usd, 'native' => $native, 'sym' => 'TRX'];
}
/** ارزش دلاری ولت تون (موجودی نیتیو × قیمت TON) */
function tonWalletUsd(string $address): ?array {
    $j = httpGet(apiBase('toncenter', 'https://toncenter.com') . '/api/v2/getAddressBalance?address=' . urlencode($address));
    if (!$j) { return null; }
    $d = json_decode($j, true);
    if (empty($d['ok'])) { return null; }
    $ton = (float)$d['result'] / 1e9;
    $tk = binance24h('TONUSDT');
    $price = $tk ? (float)$tk['lastPrice'] : 0.0;
    return ['usd' => $ton * $price, 'native' => $ton, 'sym' => 'TON'];
}
/** ارزش دلاری ولت بی‌ان‌بی (موجودی نیتیو BNB × قیمت BNB) */
function bnbWalletUsd(string $address): ?array {
    $payload = json_encode([
        'jsonrpc' => '2.0', 'id' => 1, 'method' => 'eth_getBalance',
        'params' => [$address, 'latest'],
    ]);
    $endpoints = [
        'https://bsc-dataseed.binance.org/',
        'https://bsc-dataseed1.defibit.io/',
        'https://rpc.ankr.com/bsc',
    ];
    $weiHex = null;
    foreach ($endpoints as $url) {
        $r = httpPostJson($url, $payload);
        if (!$r) { continue; }
        $d = json_decode($r, true);
        if (isset($d['result']) && is_string($d['result'])) { $weiHex = $d['result']; break; }
    }
    if ($weiHex === null) { return null; }
    $bnb = hexToFloat($weiHex) / 1e18;
    $tk = binance24h('BNBUSDT');
    $price = $tk ? (float)$tk['lastPrice'] : 0.0;
    return ['usd' => $bnb * $price, 'native' => $bnb, 'sym' => 'BNB'];
}
/** کپشن ولت طبق قالب دقیق کاربر (با ایموجی پریمیوم + ایموجی اختصاصی هر شبکه) */
const DEFAULT_TPL_WALLET = "{{pe:w_info}} اطلاعات ولت {chain_icon} {chain_fa}\n\n┓━━❲ {{pe:w_bal}}موجودی ولت ❳\n┨≡ {{pe:w_usd}} دلار: <b>{usd}</b> $\n┨≡ {{pe:w_toman}} تومان: <b>{toman}</b>\n\n┚≡ {{pe:w_addr}} آدرس ولت :\n{{pe:w_addr}} <code>{address}</code>\n";
function buildWalletCaption(string $chain, string $chainFa, string $address, float $usd, ?float $toman): string {
    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $shamsi = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $clock  = date('H:i:s', $ts);
    $usdStr = number_format($usd, 2, '.', '');
    $tmnStr = $toman !== null ? number_format((int)round($toman)) : '—';
    $chainPeKey = ['tron' => 'w_tron', 'ton' => 'w_ton', 'bnb' => 'w_bnb'][$chain] ?? null;
    $chainIcon = $chainPeKey ? pe($chainPeKey) : (CHAIN_EMOJI[$chain] ?? '🔷');

    $tpl = getTemplate('tpl_wallet', DEFAULT_TPL_WALLET);
    $out = renderTemplate($tpl, [
        '{chain_icon}' => $chainIcon, '{chain_fa}' => h($chainFa), '{usd}' => $usdStr,
        '{toman}' => $tmnStr, '{address}' => h($address),
    ]);
    $out .= "\n" . quoteBlock(pe('w_date') . " $clock | $shamsi");
    return $out;
}
/** کارت ولت را برای شبکهٔ مشخص می‌سازد و می‌فرستد (پیام متنی) */
function sendWalletCard($chatId, string $chain, string $address, $replyTo = null): void {
    $chain = strtolower($chain);
    $map = ['tron' => 'ترون', 'ton' => 'تون', 'bnb' => 'بی‌ان‌بی'];
    $chainFa = $map[$chain] ?? $chain;

    if ($chain === 'tron') {
        if (!tronValidate($address)) { sendMessage($chatId, emo('no') . " آدرس ولت ترون نامعتبر است.", null, $replyTo); return; }
        $bal = tronWalletUsd($address);
    } elseif ($chain === 'ton') {
        $bal = tonWalletUsd($address);
    } else {
        $bal = bnbWalletUsd($address);
    }

    if ($bal === null) { sendMessage($chatId, emo('no') . " دریافت موجودی ولت ناموفق بود. بعداً دوباره تلاش کنید.", null, $replyTo); return; }

    $usd   = (float)($bal['usd'] ?? 0);
    $tpu   = tomanPerUsd();
    $toman = $tpu !== null ? $usd * $tpu : null;

    $cap = buildWalletCaption($chain, $chainFa, $address, $usd, $toman);
    sendMessage($chatId, $cap, addGroupKeyboard(), $replyTo);
}

// ==========================================================================
// 7‑ب) قابلیت‌های جدید: اخبار / شاخص ترس‌وطمع / لیکویدیتی / گیفت پورتال / تحلیل AI
// ==========================================================================

// --------------------------------------------------------------------------
// اخبار روز ارز دیجیتال (RSS)
// --------------------------------------------------------------------------
/** پارسر سبک RSS با ریجکس (بدون وابستگی به افزونهٔ SimpleXML/DOM، برای سازگاری با هاست‌های اشتراکی) */
function rssTag(string $block, string $tag): string {
    if (!preg_match('/<' . $tag . '\b[^>]*>(.*?)<\/' . $tag . '>/is', $block, $m)) { return ''; }
    $v = trim($m[1]);
    if (preg_match('/^<!\[CDATA\[(.*)\]\]>$/is', $v, $c)) { $v = trim($c[1]); }
    return trim(html_entity_decode(strip_tags($v), ENT_QUOTES, 'UTF-8'));
}
function parseRssItems(string $xml, int $limit = 5): array {
    $items = [];
    if (!preg_match_all('/<item\b[^>]*>(.*?)<\/item>/is', $xml, $m)) { return []; }
    foreach ($m[1] as $block) {
        if (count($items) >= $limit) { break; }
        $title = rssTag($block, 'title');
        $link  = rssTag($block, 'link');
        if ($title === '' || $link === '') { continue; }
        $items[] = ['title' => $title, 'link' => $link, 'pubDate' => rssTag($block, 'pubDate'), 'desc' => rssTag($block, 'description')];
    }
    return $items;
}
function fetchNews(int $limit = 5): array {
    $xml = httpGet(apiBase('news_rss', NEWS_RSS_URL), 15);
    return $xml ? parseRssItems($xml, $limit) : [];
}
/** تخمین میزان تأثیرگذاری خبر بر بازار بر اساس کلیدواژه‌های حساس (بدون نیاز به API پولی) */
function newsImpact(string $title, string $desc): string {
    $text = mb_strtolower($title . ' ' . $desc, 'UTF-8');
    $high = ['sec','etf','فدرال رزرو','نرخ بهره','هک','ممنوعیت','قانون','رگولات','هالوینگ','halving',
             'حمله','بحران','ورشکست','سقوط','ریزش','رکورد','بلک‌راک','blackrock','cme'];
    foreach ($high as $k) { if (mb_strpos($text, $k) !== false) { return '🔥 تأثیر بالا'; } }
    $mid = ['صرافی','exchange','آپدیت','بروزرسانی','آپگرید','همکاری','سرمایه‌گذاری','لیست شدن','لیستینگ'];
    foreach ($mid as $k) { if (mb_strpos($text, $k) !== false) { return '📊 تأثیر متوسط'; } }
    return '📰 خبری';
}
/** تبدیل pubDate خبر (RFC2822) به ساعت/تاریخ تهران؛ در نبود تاریخ معتبر، اکنون را برمی‌گرداند */
function newsTehranTime(string $pubDate): string {
    $ts = $pubDate !== '' ? strtotime($pubDate) : false;
    if ($ts === false) { $ts = time(); }
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    return date('H:i', $ts) . ' — ' . sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
}
const DEFAULT_TPL_NEWS = "🔸 <b>{title}</b>\n┨≡ {impact}\n┚≡ {{pe:clock}} {time} (تهران)\n🔗 <a href=\"{link}\">مشاهده خبر کامل</a>\n";
function buildNewsCard(array $items): string {
    $t = "📰 ✨ <b>اخبار داغ ارز دیجیتال</b> ✨ 📰\n\n";
    if (!$items) {
        $t .= emo('no') . " در حال حاضر خبری دریافت نشد؛ کمی بعد دوباره تلاش کنید.";
        return $t;
    }
    $tpl = getTemplate('tpl_news', DEFAULT_TPL_NEWS);
    foreach ($items as $it) {
        $t .= renderTemplate($tpl, [
            '{title}' => h($it['title']), '{impact}' => newsImpact($it['title'], $it['desc']),
            '{time}' => newsTehranTime($it['pubDate']), '{link}' => h($it['link']),
        ]) . "\n";
    }
    $t .= priceQuote();
    return $t;
}
/** نسخهٔ رنگی برچسب تأثیر خبر (بدون ایموجی، برای رسم داخل تصویر GD) + رنگ پس‌زمینه */
function newsImpactColored(string $title, string $desc): array {
    $label = trim(preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', newsImpact($title, $desc)));
    if (mb_strpos($label, 'بالا') !== false)      { $color = [235, 87, 87]; }
    elseif (mb_strpos($label, 'متوسط') !== false) { $color = [242, 190, 66]; }
    else                                          { $color = [110, 150, 220]; }
    return [$label, $color];
}
/**
 * تصویر خفن اخبار: برخلاف نسخهٔ قبلی، عناوین واقعی خبر با فونت فارسی داخل خود تصویر تایپ
 * می‌شوند (نه فقط در کپشن). این کار با یافتن الگوی «معکوس‌کردن ترتیب کلمات» ممکن شده: GD به
 * کمک فونت فارسی وزیرمتن حروف فارسی مجاور را خودش به‌درستی می‌چسباند، فقط ترتیب کلمات را
 * معکوس نمی‌کند؛ پس فقط ترتیب کلمات (نه حروف داخل هر کلمه) معکوس می‌شود [faDrawLine].
 * اگر فونت فارسی در fonts/ موجود نباشد null برمی‌گرداند و caller باید به کارت متنی برگردد.
 */
function renderNewsImage(array $items): ?string {
    if (!function_exists('imagecreatetruecolor')) { return null; }
    $faFont = findFaTtf(false); $faFontB = findFaTtf(true);
    $latFont = findTtf(false); $latFontB = findTtf(true);
    if (!$faFont || !$faFontB || !$latFont || !$latFontB) { return null; }

    $items = array_slice($items, 0, 4);
    $n = max(1, count($items));
    $SS = 3; // سوپرسمپل برای لبه‌های صاف و بدون‌پیکسل
    $W = 1000; $rowH = 190; $headerH = 150; $footerH = 70;
    $H = $headerH + $n * $rowH + $footerH;
    $Wp = $W * $SS; $Hp = $H * $SS;

    $img = imagecreatetruecolor($Wp, $Hp);
    imagealphablending($img, true); imagesavealpha($img, true);

    $bgTop = [40, 14, 62]; $bgBot = [7, 5, 16];
    for ($y = 0; $y < $Hp; $y++) {
        $t = $y / ($Hp - 1);
        $c = imagecolorallocate($img,
            (int)round($bgTop[0] + ($bgBot[0] - $bgTop[0]) * $t),
            (int)round($bgTop[1] + ($bgBot[1] - $bgTop[1]) * $t),
            (int)round($bgTop[2] + ($bgBot[2] - $bgTop[2]) * $t));
        imageline($img, 0, $y, $Wp, $y, $c);
    }
    $accent = imagecolorallocate($img, 255, 111, 97);
    $white  = imagecolorallocate($img, 245, 248, 251);
    $muted  = imagecolorallocate($img, 190, 180, 210);
    $faint  = imagecolorallocatealpha($img, 255, 255, 255, 120);
    $panel  = imagecolorallocatealpha($img, 255, 255, 255, 123);
    $sepCol = imagecolorallocatealpha($img, 255, 255, 255, 105);
    $liveBg = imagecolorallocatealpha($img, 255, 60, 60, 60);

    roundedRect($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $panel);
    roundedRectOutline($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $faint);
    imagefilledrectangle($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, 28 * $SS, $accent);

    $padX = 52 * $SS;
    pill($img, $padX, 50 * $SS, '● LIVE', $liveBg, $white, 18 * $SS, 'left');
    faDrawLine($img, 'اخبار داغ ارز دیجیتال', $faFontB, $latFontB, $Wp - $padX, 100 * $SS, 40 * $SS * 0.7, $white);
    cardText($img, 'CRYPTO NEWS UPDATE', $padX, 116 * $SS, 18 * $SS, $muted, false, 'left');

    $y = $headerH * $SS;
    foreach ($items as $idx => $it) {
        if ($idx > 0) { imageline($img, $padX, $y, $Wp - $padX, $y, $sepCol); }

        // ابتدا شکستن خط عنوان محاسبه می‌شود تا ارتفاع واقعی محتوا بدانیم و بلوک را در وسط
        // فضای ردیف (rowH) عمودی وسط‌چین کنیم (ریتم یکنواخت برای تیترهای کوتاه/بلند).
        $lines = faWrapMixed($it['title'], $faFontB, $latFontB, 30 * $SS * 0.7, $Wp - 2 * $padX);
        $lines = array_slice($lines, 0, 2);
        $badgeH = 34 * $SS;
        $contentH = $badgeH + 16 * $SS + count($lines) * 44 * $SS + 14 * $SS + 24 * $SS;
        $rowTop = $y + max(20 * $SS, (int)(($rowH * $SS - $contentH) / 2));

        [$impactLabel, $impactColor] = newsImpactColored($it['title'], $it['desc']);
        $badgeBg = imagecolorallocatealpha($img, $impactColor[0], $impactColor[1], $impactColor[2], 70);
        $bw = faTextWidth($faFont, 16 * $SS * 0.7, $impactLabel) + 28 * $SS;
        roundedRect($img, (int)($Wp - $padX - $bw), $rowTop, $Wp - $padX, $rowTop + $badgeH, (int)($badgeH / 2), $badgeBg);
        faDrawLine($img, $impactLabel, $faFont, $latFont, $Wp - $padX - 14 * $SS, $rowTop + $badgeH - 10 * $SS, 16 * $SS * 0.7, $white);

        $rowTop += $badgeH + 16 * $SS;
        foreach ($lines as $li => $line) {
            faDrawLine($img, $line, $faFontB, $latFontB, $Wp - $padX, $rowTop + $li * 44 * $SS, 30 * $SS * 0.7, $white);
        }
        $rowTop += count($lines) * 44 * $SS + 14 * $SS;

        faDrawLine($img, newsTehranTime($it['pubDate']), $faFont, $latFont, $Wp - $padX, $rowTop, 18 * $SS * 0.7, $muted);

        $y += $rowH * $SS;
    }

    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    cardText($img, sprintf('%04d/%02d/%02d %s (Tehran)', $jy, $jm, $jd, date('H:i', $ts)), $padX, $Hp - 50 * $SS, 18 * $SS, $muted, false, 'left');
    cardText($img, '@' . BOT_USERNAME, $Wp - $padX, $Hp - 50 * $SS, 18 * $SS, $muted, false, 'right');

    $out = imagecreatetruecolor($W, $H);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $W, $H, $Wp, $Hp);
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'newsimg') . '.png';
    imagepng($out, $tmp);
    imagedestroy($out);
    return is_file($tmp) ? $tmp : null;
}
/** کیبورد لینک هر خبر (یک دکمه به‌ازای هر تیتر) + دکمهٔ سبز افزودن به گروه */
function newsKeyboard(array $items): array {
    $rows = [];
    foreach ($items as $i => $it) {
        $rows[] = [btnUrl('🔗 ' . mbTruncate($it['title'], 38), $it['link'], 'primary')];
    }
    $rows[] = [addToGroupBtnGreen()];
    return ikb($rows);
}
function sendNewsCard($chatId, $replyTo = null): void {
    $items = fetchNews(5);
    $img = renderNewsImage($items);
    if ($img) {
        sendPhotoFile($chatId, $img, '', $items ? newsKeyboard($items) : addGroupKeyboardGreen(), $replyTo);
        @unlink($img);
        return;
    }
    // نبود فونت فارسی (fonts/persian.ttf) → برگشت به کارت متنی معمولی، بدون کرش
    sendMessage($chatId, buildNewsCard($items), addGroupKeyboardGreen(), $replyTo);
}

// --------------------------------------------------------------------------
// شاخص ترس و طمع (Fear & Greed Index)
// --------------------------------------------------------------------------
function isFearGreedQuery(string $t): bool {
    $t = mb_strtolower(trim($t), 'UTF-8');
    return in_array($t, ['شاخص', 'شاخص ترس و طمع', 'شاخص ترس وطمع', 'ترس و طمع', 'ترس وطمع',
                          'fear', 'greed', 'fear and greed', 'fear&greed', '/feargreed', '/fng'], true);
}
/** دریافت شاخص ترس و طمع: اولویت با CoinMarketCap Pro (در صورت تنظیم CMC_API_KEY)،
 *  در غیر این صورت منبع رایگان alternative.me — تا این قابلیت بدون کلید هم همیشه کار کند.
 *  نتیجه به‌صورت روزانه در دیتابیس کش می‌شود (به‌روزرسانی روزانه). */
function fetchFearGreed(): ?array {
    $cacheKey = 'fng_' . date('Y-m-d');
    $cached = getSetting($cacheKey);
    if ($cached) {
        $d = json_decode($cached, true);
        if (is_array($d) && isset($d['value'])) { return $d; }
    }

    $value = null; $label = null; $source = '';
    if (CMC_API_KEY !== '') {
        $ch = curl_init('https://pro-api.coinmarketcap.com/v3/fear-and-greed/latest');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['X-CMC_PRO_API_KEY: ' . CMC_API_KEY, 'Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $res = curl_exec($ch); curl_close($ch);
        if ($res) {
            $d = json_decode($res, true);
            $v = $d['data']['value'] ?? null;
            if ($v !== null) { $value = (int)$v; $label = $d['data']['value_classification'] ?? null; $source = 'CoinMarketCap'; }
        }
    }
    if ($value === null) {
        $j = httpGet('https://api.alternative.me/fng/?limit=1', 12);
        if ($j) {
            $d = json_decode($j, true);
            $row = $d['data'][0] ?? null;
            if ($row && isset($row['value'])) { $value = (int)$row['value']; $label = $row['value_classification'] ?? null; $source = 'Alternative.me'; }
        }
    }
    if ($value === null) { return null; }

    $out = ['value' => $value, 'label' => $label, 'source' => $source];
    setSetting($cacheKey, json_encode($out, JSON_UNESCAPED_UNICODE));
    return $out;
}
function fngEmoji(int $v): string {
    if ($v <= 24) { return '🥶'; }
    if ($v <= 44) { return '😨'; }
    if ($v <= 55) { return '😐'; }
    if ($v <= 75) { return '😃'; }
    return '🤑';
}
function fngLabelFa(int $v): string {
    if ($v <= 24) { return 'ترس شدید'; }
    if ($v <= 44) { return 'ترس'; }
    if ($v <= 55) { return 'خنثی'; }
    if ($v <= 75) { return 'طمع'; }
    return 'طمع شدید';
}
function fngLabelEn(int $v): string {
    if ($v <= 24) { return 'Extreme Fear'; }
    if ($v <= 44) { return 'Fear'; }
    if ($v <= 55) { return 'Neutral'; }
    if ($v <= 75) { return 'Greed'; }
    return 'Extreme Greed';
}
function fngBar(int $v): string {
    $filled = (int)round(max(0, min(100, $v)) / 10);
    return str_repeat('🟩', $filled) . str_repeat('⬜️', 10 - $filled);
}
/** میانگین‌های تاریخی شاخص (دیروز/هفتهٔ گذشته/ماه گذشته) از alternative.me — رایگان، تا ۳۰ روز اخیر */
function fetchFearGreedHistory(): ?array {
    $j = httpGet('https://api.alternative.me/fng/?limit=30', 12);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    $rows = $d['data'] ?? null;
    if (!is_array($rows) || !$rows) { return null; }
    $vals = array_map(fn($r) => (int)$r['value'], $rows); // rows[0] = امروز (جدیدترین)
    $week  = array_slice($vals, 0, min(7, count($vals)));
    $month = array_slice($vals, 0, min(30, count($vals)));
    return [
        'yesterday' => $vals[1] ?? null,
        'week_avg'  => $week  ? (int)round(array_sum($week) / count($week))   : null,
        'month_avg' => $month ? (int)round(array_sum($month) / count($month)) : null,
    ];
}
/** رنگ گرادیانی گِیج بین قرمز (ترس) و سبز (طمع) برای نسبت ۰..۱ */
function fngGaugeColor(float $frac): array {
    if ($frac <= 0.5) {
        $t = $frac / 0.5;
        return [(int)round(224 + (250 - 224) * $t), (int)round(60 + (204 - 60) * $t), (int)round(60 + (21 - 60) * $t)];
    }
    $t = ($frac - 0.5) / 0.5;
    return [(int)round(250 + (34 - 250) * $t), (int)round(204 + (197 - 204) * $t), (int)round(21 + (94 - 21) * $t)];
}
/** رندر تصویر گِیج (سرعت‌سنج) شاخص ترس‌وطمع، سبک مدرن تیره با حاشیهٔ درخشان هم‌رنگ مقدار،
 *  برچسب FEAR/GREED در دو سر قوس و مینی‌بج‌های روند (دیروز/هفته/ماه). متن داخل تصویر عمداً
 *  لاتین است (محدودیت shaping فارسی در GD)؛ جزئیات فارسی در کپشن پیام می‌آید. */
function renderFearGreedGauge(int $value, string $labelEn, ?array $hist = null): ?string {
    if (!function_exists('imagecreatetruecolor')) { return null; }
    $SS = 3; // سوپرسمپل: رسم در ابعاد بزرگ‌تر و کوچک‌سازی نرم در پایان برای لبه‌های صاف (رفع پیکسلی‌بودن)
    $W = 900; $H = 760;
    $Wp = $W * $SS; $Hp = $H * $SS;
    $img = imagecreatetruecolor($Wp, $Hp);
    imagealphablending($img, true); imagesavealpha($img, true);

    $bgTop = [12, 16, 26]; $bgBot = [4, 6, 12];
    for ($y = 0; $y < $Hp; $y++) {
        $t = $y / ($Hp - 1);
        $c = imagecolorallocate($img,
            (int)round($bgTop[0] + ($bgBot[0] - $bgTop[0]) * $t),
            (int)round($bgTop[1] + ($bgBot[1] - $bgTop[1]) * $t),
            (int)round($bgTop[2] + ($bgBot[2] - $bgTop[2]) * $t));
        imageline($img, 0, $y, $Wp, $y, $c);
    }
    $white = imagecolorallocate($img, 245, 248, 251);
    $muted = imagecolorallocate($img, 150, 161, 176);
    $faint = imagecolorallocatealpha($img, 255, 255, 255, 120);
    $panel = imagecolorallocatealpha($img, 255, 255, 255, 123);
    $valueFrac = max(0, min(100, $value)) / 100;
    [$vr, $vg, $vb] = fngGaugeColor($valueFrac);
    $glow = imagecolorallocatealpha($img, $vr, $vg, $vb, 70);
    $glowSharp = imagecolorallocate($img, $vr, $vg, $vb);

    // حاشیهٔ درخشان هم‌رنگ مقدار فعلی (چند لایهٔ محو دور پنل، مثل یک ring-glow)
    for ($i = 5; $i >= 1; $i--) {
        roundedRectOutline($img, 22 * $SS - $i * $SS, 22 * $SS - $i * $SS, $Wp - 22 * $SS + $i * $SS, $Hp - 22 * $SS + $i * $SS, 30 * $SS, $glow);
    }
    roundedRect($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $panel);
    roundedRectOutline($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $faint);
    roundedRectOutline($img, 24 * $SS, 24 * $SS, $Wp - 24 * $SS, $Hp - 24 * $SS, 28 * $SS, $glowSharp);

    cardText($img, 'Fear & Greed Index', (int)($Wp / 2), 44 * $SS, 36 * $SS, $white, true, 'center');
    cardText($img, strtoupper($labelEn), (int)($Wp / 2), 92 * $SS, 22 * $SS, $glowSharp, true, 'center');

    $cx = (int)($Wp / 2); $cy = 430 * $SS; $rOuter = 260 * $SS; $rInner = 200 * $SS;
    $segments = 160;
    for ($i = 0; $i < $segments; $i++) {
        $f0 = $i / $segments; $f1 = ($i + 1) / $segments;
        $a0 = 180 + $f0 * 180; $a1 = 180 + $f1 * 180;
        [$r, $g, $b] = fngGaugeColor(($f0 + $f1) / 2);
        $col = imagecolorallocate($img, $r, $g, $b);
        imagefilledarc($img, $cx, $cy, $rOuter * 2, $rOuter * 2, (int)round($a0), (int)round($a1) + 1, $col, IMG_ARC_PIE);
    }
    // برش حلقه: پانچ نیم‌دایرهٔ داخلی با رنگ نزدیک به پس‌زمینهٔ همان ارتفاع تا حلقه (نه دیسک توپر) دیده شود
    $holeT = $cy / ($Hp - 1);
    $holeCol = imagecolorallocate($img,
        (int)round($bgTop[0] + ($bgBot[0] - $bgTop[0]) * $holeT),
        (int)round($bgTop[1] + ($bgBot[1] - $bgTop[1]) * $holeT),
        (int)round($bgTop[2] + ($bgBot[2] - $bgTop[2]) * $holeT));
    imagefilledarc($img, $cx, $cy, $rInner * 2, $rInner * 2, 180, 361, $holeCol, IMG_ARC_PIE);

    // برچسب دو سر قوس: FEAR (چپ) / GREED (راست) + اعداد ۰ و ۱۰۰
    cardText($img, 'FEAR', $cx - $rOuter + 6 * $SS, $cy + 14 * $SS, 20 * $SS, imagecolorallocate($img, 224, 60, 60), true, 'left');
    cardText($img, '0', $cx - $rOuter + 6 * $SS, $cy + 42 * $SS, 16 * $SS, $muted, false, 'left');
    cardText($img, 'GREED', $cx + $rOuter - 6 * $SS, $cy + 14 * $SS, 20 * $SS, imagecolorallocate($img, 34, 197, 94), true, 'right');
    cardText($img, '100', $cx + $rOuter - 6 * $SS, $cy + 42 * $SS, 16 * $SS, $muted, false, 'right');

    // عقربه
    $angleDeg = 180 + $valueFrac * 180;
    $angleRad = deg2rad($angleDeg);
    $needleLen = $rInner - 20 * $SS;
    $nx = $cx + (int)round(cos($angleRad) * $needleLen);
    $ny = $cy + (int)round(sin($angleRad) * $needleLen);
    imagesetthickness($img, 6 * $SS);
    imageline($img, $cx, $cy, $nx, $ny, $white);
    imagesetthickness($img, 1);
    imagefilledellipse($img, $cx, $cy, 26 * $SS, 26 * $SS, $glowSharp);
    imagefilledellipse($img, $cx, $cy, 16 * $SS, 16 * $SS, $white);

    cardText($img, (string)$value, $cx, $cy + 36 * $SS, 76 * $SS, $white, true, 'center');

    // مینی‌بج‌های روند: دیروز / هفته / ماه (اگر داده باشد)
    $badgeY = $cy + 145 * $SS;
    $badgeH = 70 * $SS;
    $badges = [];
    if ($hist) {
        if ($hist['yesterday'] !== null) { $badges[] = ['YESTERDAY', $hist['yesterday']]; }
        if ($hist['week_avg']  !== null) { $badges[] = ['WEEK AVG', $hist['week_avg']]; }
        if ($hist['month_avg'] !== null) { $badges[] = ['MONTH AVG', $hist['month_avg']]; }
    }
    if ($badges) {
        $bw = (int)(($Wp - 80 * $SS) / count($badges));
        $bx = 40 * $SS;
        foreach ($badges as [$label, $val]) {
            [$br, $bg2, $bb] = fngGaugeColor(max(0, min(100, $val)) / 100);
            $bCol = imagecolorallocate($img, $br, $bg2, $bb);
            $cxb = $bx + intdiv($bw, 2);
            roundedRect($img, $bx + 10 * $SS, $badgeY, $bx + $bw - 10 * $SS, $badgeY + $badgeH, 16 * $SS, imagecolorallocatealpha($img, $br, $bg2, $bb, 105));
            cardText($img, (string)$val, $cxb, $badgeY + 8 * $SS, 28 * $SS, $bCol, true, 'center');
            cardText($img, $label, $cxb, $badgeY + 46 * $SS, 13 * $SS, $muted, false, 'center');
            $bx += $bw;
        }
    }

    $ts = time();
    cardText($img, date('d F Y', $ts), $cx, $Hp - 42 * $SS, 20 * $SS, $muted, false, 'center');
    cardText($img, '@' . BOT_USERNAME, $Wp - 52 * $SS, $Hp - 42 * $SS, 18 * $SS, $muted, false, 'right');

    $out = imagecreatetruecolor($W, $H);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $W, $H, $Wp, $Hp);
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'fng') . '.png';
    imagepng($out, $tmp);
    imagedestroy($out);
    return is_file($tmp) ? $tmp : null;
}
const DEFAULT_TPL_FEARGREED = "🧭 ✨ <b>شاخص ترس و طمع بازار کریپتو</b> ✨ 🧭\n\n┓━━❲ وضعیت امروز ❳\n┨≡ {emoji} عدد شاخص: <b>{value} / 100</b>\n┚≡ 🏷 وضعیت: <b>{label}</b>\n{trend}\n{bar}\n";
function buildFearGreedCaption(array $d, ?array $hist = null): string {
    $v = (int)$d['value'];
    $trend = '';
    if ($hist) {
        $trend = "\n┓━━❲ روند اخیر ❳\n";
        if ($hist['yesterday'] !== null) { $trend .= "┨≡ " . fngEmoji($hist['yesterday']) . " دیروز: <b>{$hist['yesterday']}</b> " . fngLabelFa($hist['yesterday']) . "\n"; }
        if ($hist['week_avg']  !== null) { $trend .= "┨≡ " . fngEmoji($hist['week_avg'])  . " میانگین هفتهٔ گذشته: <b>{$hist['week_avg']}</b>\n"; }
        if ($hist['month_avg'] !== null) { $trend .= "┚≡ " . fngEmoji($hist['month_avg']) . " میانگین ماه گذشته: <b>{$hist['month_avg']}</b>\n"; }
    }
    $tpl = getTemplate('tpl_feargreed', DEFAULT_TPL_FEARGREED);
    $t = renderTemplate($tpl, [
        '{emoji}' => fngEmoji($v), '{value}' => (string)$v, '{label}' => fngLabelFa($v),
        '{trend}' => $trend, '{bar}' => fngBar($v),
    ]);
    return $t . "\n" . priceQuote();
}
function sendFearGreedCard($chatId, $replyTo = null): void {
    $d = fetchFearGreed();
    if ($d === null) { sendMessage($chatId, emo('no') . " دریافت شاخص ترس و طمع در حال حاضر ممکن نشد؛ کمی بعد دوباره تلاش کنید.", null, $replyTo); return; }
    $hist = fetchFearGreedHistory();
    $cap = buildFearGreedCaption($d, $hist);
    $img = renderFearGreedGauge($d['value'], fngLabelEn((int)$d['value']), $hist);
    if ($img) { sendPhotoFile($chatId, $img, $cap, addGroupKeyboardGreen(), $replyTo); @unlink($img); }
    else { sendMessage($chatId, $cap, addGroupKeyboardGreen(), $replyTo); }
}

// --------------------------------------------------------------------------
// لیکویدیتی — پیش‌فرض کاملاً رایگان (دادهٔ عمومی فیوچرز بایننس)، با ارتقای اختیاری به Coinglass
// --------------------------------------------------------------------------
function parseLiquidityQuery(string $t): ?string {
    $t = trim($t);
    if (!preg_match('/^لیکویدی(?:تی)?\s*(.*)$/u', $t, $m)) { return null; }
    $rest = trim($m[1]);
    if ($rest === '') { return 'BTC'; }
    return normalizeSymbol($rest) ?: 'BTC';
}
function binanceFuturesGet(string $path): ?array {
    $j = httpGet(apiBase('binance_fapi', 'https://fapi.binance.com') . $path, 12);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return is_array($d) ? $d : null;
}
/**
 * برآورد رایگان نواحی نقدینگی از دادهٔ عمومی فیوچرز بایننس (بدون نیاز به هیچ کلید API):
 * سقف/کف اخیر قیمت به‌عنوان استخر نقدینگی (جایی که استاپ‌لاس/لیکویدیشن معامله‌گران معمولاً
 * خوشه می‌شود) + نرخ فاندینگ و نسبت لانگ/شورت برای نشان‌دادن سمت پرریسک‌تر بازار.
 */
function freeLiquidityEstimate(string $base): ?array {
    $symbol = strtoupper($base) . 'USDT';
    $tk = binance24h($symbol);
    if (!$tk) { return null; }
    $current = (float)$tk['lastPrice'];

    $candles = binanceKlinesRaw($symbol, '1h', 100);
    if (!$candles) { return null; }
    $ceil  = max(array_map(fn($c) => (float)$c[2], $candles));
    $floor = min(array_map(fn($c) => (float)$c[3], $candles));

    $funding = null; $lsRatio = null;
    $prem = binanceFuturesGet('/fapi/v1/premiumIndex?symbol=' . urlencode($symbol));
    if ($prem && isset($prem['lastFundingRate'])) { $funding = (float)$prem['lastFundingRate'] * 100; }
    $ls = binanceFuturesGet('/futures/data/globalLongShortAccountRatio?symbol=' . urlencode($symbol) . '&period=1h&limit=1');
    if (is_array($ls) && isset($ls[0]['longShortRatio'])) { $lsRatio = (float)$ls[0]['longShortRatio']; }

    return ['current' => $current, 'ceil' => $ceil, 'floor' => $floor, 'funding' => $funding, 'ls_ratio' => $lsRatio, 'symbol' => strtoupper($base), 'source' => 'estimate'];
}
/** دریافت نقشهٔ لیکویدیتی از Coinglass (پولی، فقط اگر COINGLASS_API_KEY تنظیم شده باشد) و استخراج
 *  نزدیک‌ترین ناحیهٔ سقف/کف نقدینگی به قیمت فعلی. توجه: ساختار دقیق پاسخ ممکن است بسته به پلن
 *  اشتراک شما متفاوت باشد؛ در صورت لزوم مسیر/فیلدهای زیر را مطابق مستندات پلن خودتان تنظیم کنید. */
function coinglassLiquidity(string $base): ?array {
    if (COINGLASS_API_KEY === '') { return null; }
    $symbol = strtoupper($base);
    $ch = curl_init('https://open-api-v4.coinglass.com/api/futures/liquidation/heatmap?symbol=' . urlencode($symbol) . '&range=1d');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['CG-API-KEY: ' . COINGLASS_API_KEY, 'Accept: application/json'],
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    if (!$res) { return null; }
    $d = json_decode($res, true);
    $rows = $d['data'] ?? null;
    if (!is_array($rows) || !$rows) { return null; }

    $tk = binance24h($symbol . 'USDT');
    $current = $tk ? (float)$tk['lastPrice'] : null;
    if ($current === null) { return null; }

    $levels = [];
    foreach ($rows as $r) {
        $price = (float)($r['price'] ?? $r[0] ?? 0);
        $vol   = (float)($r['liq'] ?? $r['volume'] ?? $r[1] ?? 0);
        if ($price > 0 && $vol > 0) { $levels[] = ['price' => $price, 'vol' => $vol]; }
    }
    if (!$levels) { return null; }
    usort($levels, fn($a, $b) => $b['vol'] <=> $a['vol']);

    $ceil = null; $floor = null;
    foreach ($levels as $lv) {
        if ($lv['price'] > $current && $ceil === null) { $ceil = $lv['price']; }
        if ($lv['price'] < $current && $floor === null) { $floor = $lv['price']; }
        if ($ceil !== null && $floor !== null) { break; }
    }
    return ['current' => $current, 'ceil' => $ceil, 'floor' => $floor, 'funding' => null, 'ls_ratio' => null, 'symbol' => $symbol, 'source' => 'coinglass'];
}
/** مسیر اصلی: اگر COINGLASS_API_KEY تنظیم شده از آن (دقیق‌تر) استفاده می‌کند، وگرنه از برآورد رایگان بایننس. */
function fetchLiquidityData(string $base): ?array {
    if (COINGLASS_API_KEY !== '') {
        $d = coinglassLiquidity($base);
        if ($d !== null) { return $d; }
    }
    return freeLiquidityEstimate($base);
}
const DEFAULT_TPL_LIQUIDITY = "🌊 ✨ <b>نقشهٔ لیکویدیتی {name}</b> ({symbol}) ✨ 🌊\n\n{{quote}}💰 قیمت فعلی: <b>{current}</b> $\n\n{ceil}{floor}{extra}{{/quote}}\n📡 منبع: {source}";
function buildLiquidityCaption(array $d): string {
    $name = coinName($d['symbol']);
    $ceil = ''; $floor = ''; $extra = '';
    if ($d['ceil'] !== null) {
        $ceil = "📈 <b>سقف نقدینگی (ناحیهٔ احتمالی لیکویید شورت‌ها):</b>\n" .
                "حدود <b>" . fmtPrice($d['ceil']) . "</b> $ — احتمال جاروب نقدینگی و برخورد با مقاومت.\n\n";
    }
    if ($d['floor'] !== null) {
        $floor = "📉 <b>کف نقدینگی (ناحیهٔ احتمالی لیکویید لانگ‌ها):</b>\n" .
                 "حدود <b>" . fmtPrice($d['floor']) . "</b> $ — احتمال واکنش قیمتی و برگشت روند.\n";
    }
    if (($d['funding'] ?? null) !== null || ($d['ls_ratio'] ?? null) !== null) {
        $extra = "\n";
        if (($d['funding'] ?? null) !== null) { $extra .= "💸 نرخ فاندینگ: <b>" . number_format($d['funding'], 4) . "%</b>\n"; }
        if (($d['ls_ratio'] ?? null) !== null) { $extra .= "⚖️ نسبت لانگ/شورت: <b>" . number_format($d['ls_ratio'], 2) . "</b>\n"; }
    }
    $tpl = getTemplate('tpl_liquidity', DEFAULT_TPL_LIQUIDITY);
    $t = renderTemplate($tpl, [
        '{name}' => $name, '{symbol}' => $d['symbol'], '{current}' => fmtPrice($d['current']),
        '{ceil}' => $ceil, '{floor}' => $floor, '{extra}' => $extra,
        '{source}' => (($d['source'] ?? '') === 'coinglass' ? 'Coinglass' : 'برآورد رایگان از دادهٔ عمومی فیوچرز بایننس'),
    ]);
    return $t . "\n" . pe('date') . ' ' . jalaliDateLine();
}
function sendLiquidityCard($chatId, string $base, $replyTo = null): void {
    $d = fetchLiquidityData($base);
    if ($d === null) {
        sendMessage($chatId, emo('no') . " دریافت اطلاعات لیکویدیتی برای <b>" . h(strtoupper($base)) . "</b> ممکن نشد؛ کمی بعد دوباره تلاش کنید.", null, $replyTo);
        return;
    }
    sendMessage($chatId, buildLiquidityCaption($d), addGroupKeyboardGreen(), $replyTo);
}

// --------------------------------------------------------------------------
// تحلیل — پست‌های تحلیلی کامیونیتی TradingView (بدون محدودیت ارزی)
// --------------------------------------------------------------------------
/** تشخیص درخواست «تحلیل ...» و استخراج نماد ارز (بدون محدودیت) */
function parseAnalysisRequest(string $t): ?string {
    $t = trim($t);
    if (!preg_match('/^(?:تحلیل|آنالیز|analysis)(?:\s+(.+))?$/iu', $t, $m)) { return null; }
    $rest = trim($m[1] ?? '');
    if ($rest === '') { return 'BTC'; }
    return normalizeSymbol($rest);
}
/**
 * جست‌وجوی بازگشتی داخل یک ساختار JSON دلخواه برای یافتن اولین آرایه‌ای که شبیه لیست
 * «ایده‌های» تریدینگ‌ویو باشد: هر آیتم باید حداقل یک عنوان و یک توضیح داشته باشد.
 * این روش چون به مسیر دقیق کلیدها وابسته نیست، در برابر تغییرات جزئی ساختار صفحهٔ
 * تریدینگ‌ویو مقاوم‌تر از پارس‌کردن مسیر ثابت JSON است.
 */
function findIdeaArrayInJson($node, int $depth = 0): ?array {
    if ($depth > 6 || !is_array($node)) { return null; }
    $isList = array_keys($node) === range(0, count($node) - 1);
    if ($isList && count($node) >= 1) {
        $okCount = 0;
        foreach (array_slice($node, 0, 5) as $item) {
            if (!is_array($item)) { continue; }
            $hasTitle = isset($item['title']) && is_string($item['title']);
            $hasDesc  = isset($item['description']) || isset($item['text']) || isset($item['content']) || isset($item['summary']);
            if ($hasTitle && $hasDesc) { $okCount++; }
        }
        if ($okCount >= 1 && $okCount >= count(array_slice($node, 0, 5)) / 2) { return $node; }
    }
    foreach ($node as $v) {
        if (is_array($v)) {
            $found = findIdeaArrayInJson($v, $depth + 1);
            if ($found !== null) { return $found; }
        }
    }
    return null;
}
/** استخراج متن ساده از HTML (برای توضیحات ایده که ممکن است شامل تگ باشد) */
function stripHtmlToText(string $html): string {
    $t = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $html);
    $t = preg_replace('/<br\s*\/?>/i', "\n", $t);
    $t = preg_replace('/<\/p>/i', "\n\n", $t);
    $t = trim(html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8'));
    return preg_replace("/\n{3,}/", "\n\n", $t);
}
/**
 * دریافت پست‌های تحلیلی کامیونیتی TradingView برای یک نماد (best-effort، بدون کلید API —
 * تریدینگ‌ویو API عمومی رسمی ندارد، پس اینجا صفحهٔ عمومی «Ideas» آن نماد واکشی و JSON
 * تعبیه‌شده در آن (برای SEO/رندر اولیه) تجزیه می‌شود. اگر ساختار صفحه تغییر کند و تجزیه
 * ناموفق بماند، فراخوان (sendAnalysisCard) به‌جای کرش، لینک مستقیم صفحهٔ تریدینگ‌ویو را
 * پیشنهاد می‌دهد — پس هیچ‌وقت ربات را خراب نمی‌کند.
 */
function fetchTradingViewIdeas(string $base): array {
    $base = strtoupper($base);
    $cacheKey = 'tv_ideas_' . $base;
    $cached = getSetting($cacheKey);
    if ($cached) {
        $d = json_decode($cached, true);
        if (is_array($d) && !empty($d['items']) && (time() - (int)($d['ts'] ?? 0) < 900)) { return $d['items']; }
    }
    $tvBase = apiBase('tradingview', 'https://www.tradingview.com');
    $items = [];
    foreach ([$base . 'USD', $base . 'USDT'] as $sym) {
        $html = httpGet($tvBase . '/symbols/' . urlencode($sym) . '/ideas/', 15);
        if (!$html) { continue; }
        // همهٔ بلوک‌های <script type="application/json" ...>...</script> را پیدا و به دنبال
        // آرایهٔ ایده‌ها می‌گردیم (نام دقیق متغیر/کلید مستند نیست، پس جست‌وجوی بازگشتی انجام می‌شود).
        if (preg_match_all('/<script[^>]+type="application\/(?:json|prs\.init-data\+json)"[^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $blob) {
                $d = json_decode($blob, true);
                if (!is_array($d)) { continue; }
                $arr = findIdeaArrayInJson($d);
                if ($arr) {
                    foreach ($arr as $it) {
                        $title = trim((string)($it['title'] ?? ''));
                        $desc  = (string)($it['description'] ?? $it['text'] ?? $it['content'] ?? $it['summary'] ?? '');
                        $desc  = stripHtmlToText($desc);
                        $slug  = $it['published_url'] ?? $it['url'] ?? $it['urlSlug'] ?? $it['id'] ?? null;
                        $image = $it['image_url'] ?? $it['chart_image_url'] ?? $it['imageUrl'] ?? null;
                        $author = $it['author']['username'] ?? $it['user']['username'] ?? '';
                        if ($title === '') { continue; }
                        $items[] = [
                            'title' => $title, 'desc' => mbTruncate($desc, 700),
                            'url' => $slug ? (is_string($slug) && strpos($slug, 'http') === 0 ? $slug : $tvBase . '/chart/' . ltrim((string)$slug, '/')) : ($tvBase . '/symbols/' . $sym . '/ideas/'),
                            'image' => $image, 'author' => $author,
                        ];
                    }
                }
                if ($items) { break; }
            }
        }
        if ($items) { break; }
    }
    if ($items) { setSetting($cacheKey, json_encode(['ts' => time(), 'items' => $items], JSON_UNESCAPED_UNICODE)); }
    return $items;
}
const DEFAULT_TPL_ANALYSIS = "📊 ✨ <b>تحلیل کامیونیتی {name} ({base})</b> ✨\n<i>نویسنده: {author}</i>\n\n{{quote}}<b>{title}</b>\n\n{desc}{{/quote}}\n\nتحلیل {idx} از {total}";
function buildAnalysisCaption(string $base, array $item, int $idx, int $total): string {
    $tpl = getTemplate('tpl_analysis', DEFAULT_TPL_ANALYSIS);
    $t = renderTemplate($tpl, [
        '{name}' => coinName($base), '{base}' => $base, '{author}' => h($item['author'] ?: '—'),
        '{title}' => h($item['title']), '{desc}' => h($item['desc']),
        '{idx}' => (string)($idx + 1), '{total}' => (string)$total,
    ]);
    return $t . "\n" . priceQuote();
}
/** کیبورد پیمایش بین تحلیل‌ها: تحلیل قبلی (آبی) / تحلیل بعدی (سبز) */
function analysisKeyboard(string $base, int $idx, int $total, ?string $url): array {
    $rows = [];
    if ($total > 1) {
        $rows[] = [
            btn('◀️ تحلیل قبلی', "tv:$base:" . (($idx - 1 + $total) % $total), 'primary'),
            btn('تحلیل بعدی ▶️', "tv:$base:" . (($idx + 1) % $total), 'success'),
        ];
    }
    if ($url) { $rows[] = [btnUrl('🔗 مشاهده در TradingView', $url, 'primary')]; }
    $rows[] = [addToGroupBtnGreen()];
    return ikb($rows);
}
/** ارسال/ویرایش کارت تحلیل شمارهٔ $idx از لیست ایده‌های نماد $base */
function sendAnalysisCard($chatId, string $base, int $idx = 0, $msgId = null, $cbId = null, $replyTo = null): void {
    $items = fetchTradingViewIdeas($base);
    if (!$items) {
        $tvBase = apiBase('tradingview', 'https://www.tradingview.com');
        $url = $tvBase . '/symbols/' . urlencode(strtoupper($base)) . 'USD/ideas/';
        $err = emo('no') . " فعلاً تحلیلی برای <b>" . h(strtoupper($base)) . "</b> از تریدینگ‌ویو دریافت نشد؛ می‌توانید مستقیم در سایت ببینید.";
        $kb = ikb([[btnUrl('🔗 مشاهده در TradingView', $url, 'primary')], [addToGroupBtnGreen()]]);
        if ($msgId !== null) { editMessageText($chatId, $msgId, $err, $kb); } else { sendMessage($chatId, $err, $kb, $replyTo); }
        if ($cbId) { answerCallback($cbId); }
        return;
    }
    $total = count($items);
    $idx = (($idx % $total) + $total) % $total;
    $item = $items[$idx];
    $cap = buildAnalysisCaption($base, $item, $idx, $total);
    $kb  = analysisKeyboard($base, $idx, $total, $item['url'] ?? null);

    if ($item['image'] ?? null) {
        if ($msgId !== null) { tgApi('editMessageMedia', [
            'chat_id' => $chatId, 'message_id' => $msgId,
            'media' => json_encode(['type' => 'photo', 'media' => $item['image'], 'caption' => $cap, 'parse_mode' => 'HTML'], JSON_UNESCAPED_UNICODE),
            'reply_markup' => json_encode($kb, JSON_UNESCAPED_UNICODE),
        ]); }
        else { tgApi('sendPhoto', ['chat_id' => $chatId, 'photo' => $item['image'], 'caption' => $cap, 'parse_mode' => 'HTML', 'reply_markup' => $kb, 'reply_to_message_id' => $replyTo]); }
    } else {
        if ($msgId !== null) { editMessageText($chatId, $msgId, $cap, $kb); }
        else { sendMessage($chatId, $cap, $kb, $replyTo); }
    }
    if ($cbId) { answerCallback($cbId); }
}

// ==========================================================================
// 8) روتر
// ==========================================================================
function mainMenuKeyboard($userId): array {
    $rows = [
        [btn(emo('chart') . ' راهنمای قیمت', 'm:help', 'primary', 'chart')],
        [addToGroupBtn()],
    ];
    if (isGlobalAdmin($userId)) {
        $rows[] = [btn(emo('shield') . ' پنل مدیریت', 'ap:home', 'success', 'shield')];
    }
    return ikb($rows);
}
function tronMenuKeyboard(): array {
    return ikb([
        [btn('🔎 اطلاعات تراکنش', 'tron:tx', 'primary')],
        [btn(emo('wallet') . ' موجودی ولت (ترون/تون/BNB)', 'tron:wallet', 'primary', 'wallet')],
        [btn('📜 انتقال‌های TRC20', 'tron:tr', 'primary')],
        [backBtn('m:back', 'danger')],
    ]);
}
function sendStart($chatId, $userId): void {
    $txt = maybeQuote(getBotText('start') ?? 'سلام!');
    sendMessage($chatId, $txt, mainMenuKeyboard($userId));
}

function handleMessage(array $msg): void {
    $chat = $msg['chat'] ?? null;
    if (!$chat) { return; }
    $chatId = $chat['id'];
    $type = $chat['type'] ?? 'private';
    $from = $msg['from'] ?? null;

    if ($type === 'private') {
        if ($from) { registerUser($from); }
        handlePrivate($msg, $chatId, $from);
    } else {
        handleGroup($msg, $chat, $chatId);
    }
}

function handlePrivate(array $msg, $chatId, ?array $from): void {
    $userId = $from['id'] ?? $chatId;
    $text = trim($msg['text'] ?? '');

    // دستورات
    if ($text !== '' && $text[0] === '/') {
        clearState($chatId);
        $cmd = strtolower(preg_split('/[\s@]+/', $text)[0]);
        if ($cmd === '/start') {
            if (!pvGate($userId, $chatId)) { return; }
            sendStart($chatId, $userId);
            return;
        }
        if ($cmd === '/panel' || $cmd === '/admin') {
            if (isGlobalAdmin($userId)) { showAdminPanel($chatId); }
            else { sendMessage($chatId, emo('no') . " شما ادمین نیستید."); }
            return;
        }
        if ($cmd === '/id') {
            sendMessage($chatId, pemo('people') . " آیدی شما: <code>$userId</code>");
            return;
        }
        if ($cmd === '/help') {
            sendMessage($chatId, helpText());
            return;
        }
        if ($cmd === '/date' || $cmd === '/time') {
            sendDateCard($chatId);
            return;
        }
        if ($cmd === '/news') {
            sendNewsCard($chatId);
            return;
        }
        if ($cmd === '/feargreed' || $cmd === '/fng') {
            sendFearGreedCard($chatId);
            return;
        }
        if ($cmd === '/list') {
            sendSupportedList($chatId);
            return;
        }
        if ($cmd === '/liquidy' || $cmd === '/liquidity') {
            $rest = trim(mb_substr($text, mb_strlen(explode(' ', $text)[0])));
            $liqBase = $rest !== '' ? (normalizeSymbol($rest) ?: 'BTC') : 'BTC';
            sendLiquidityCard($chatId, $liqBase);
            return;
        }
        // ناشناخته → استارت
        sendStart($chatId, $userId);
        return;
    }

    // حالت‌های فرم
    $state = getState($chatId);
    if ($state && ($state['step'] ?? '') !== '') {
        if (routeState($chatId, $userId, $state, $msg, $text)) { return; }
    }

    // ابزارهای خودکار: تاریخ / ولت ترون / هش تراکنش / تبدیل مقدار
    $replyTo = $msg['message_id'] ?? null;
    if ($text !== '' && tryAutoTools($chatId, $text, $replyTo)) { return; }

    // نماد ارز؟
    if ($text !== '') {
        $base = normalizeSymbol($text);
        if ($base !== null) {
            if (!pvGate($userId, $chatId)) { return; }
            if ($base === 'USDT') { sendUsdtCard($chatId, null, $replyTo); return; }
            if (isValidBase($base)) { sendPriceCard($chatId, $base, '30m', null, null, $replyTo); return; }
            sendMessage($chatId, emo('no') . " نماد <b>" . h($base) . "</b> پیدا نشد. مثال: <code>btc</code>، <code>eth</code>، <code>sol</code>", null, $replyTo);
            return;
        }
    }
    // پیش‌فرض
    sendMessage($chatId, "برای دریافت قیمت، نماد ارز را بفرستید (مثل <code>btc</code>).\nمنوی اصلی: /start", null);
}

/**
 * ابزارهای خودکار مشترک بین PV و گروه.
 * ترتیب: تاریخ → آدرس ولت ترون → هش تراکنش → تبدیل «مقدار نماد».
 * خروجی true یعنی پیام مصرف شد و نباید به‌عنوان نماد قیمت پردازش شود.
 */
function tryAutoTools($chatId, string $text, $replyTo = null): bool {
    $t = trim($text);

    if (isDateQuery($t)) { sendDateCard($chatId, $replyTo); return true; }

    // «تحلیل <ارز>» → پست‌های تحلیلی کامیونیتی TradingView (بدون محدودیت ارزی)
    $aiBase = parseAnalysisRequest($t);
    if ($aiBase !== null) { sendAnalysisCard($chatId, $aiBase, 0, null, null, $replyTo); return true; }

    // «شاخص» → شاخص ترس و طمع
    if (isFearGreedQuery($t)) { sendFearGreedCard($chatId, $replyTo); return true; }

    // «لیکویدی [ارز]» → نقشهٔ لیکویدیتی کوین‌گلس
    $liqBase = parseLiquidityQuery($t);
    if ($liqBase !== null) { sendLiquidityCard($chatId, $liqBase, $replyTo); return true; }

    // آدرس ولت (ترون / تون / بی‌ان‌بی) → کارت موجودی ولت
    // نکته: قبل از هش تراکنش بررسی می‌شود؛ آدرس EVM با ۴۰ رقم هگز کوتاه‌تر از هش ۶۴ رقمی است و تداخلی ندارد.
    $chain = detectWalletChain($t);
    if ($chain !== null) { sendWalletCard($chatId, $chain[0], $t, $replyTo); return true; }

    // هش تراکنش ترون (۶۴ کاراکتر هگز) → اطلاعات تراکنش
    if (looksLikeTxHash($t)) { sendMessage($chatId, tronTxInfo($t), null, $replyTo); return true; }

    // «۱۰ دلار» / «۵ گرم طلا» → کارت دلار/طلای بازار آزاد (قبل از مسیر ارز کریپتو)
    $ra = parseRialAsset($t);
    if ($ra !== null) {
        if ($ra[0] === 'usd') { sendDollarCard($chatId, $ra[1], $replyTo); }
        else                  { sendGoldCard($chatId, $ra[1], $replyTo); }
        return true;
    }

    // «۱۰ تون» → کارت تبدیل به تومان
    $q = parseQuantity($t);
    if ($q !== null) { sendConversionCard($chatId, $q[0], $q[1], $replyTo); return true; }

    return false;
}

function helpText(): string {
    return pemo('chart') . " <b>راهنما</b>\n" . quoteBlock(
        "• نماد ارز را بفرستید: <code>btc</code>, <code>eth</code>, <code>sol</code>\n" .
        "• تبدیل به تومان: <code>10 تون</code> یا <code>2.5 eth</code>\n" .
        "• قیمت دلار و طلا: <code>دلار</code> ، <code>10 دلار</code> ، <code>طلا</code> ، <code>5 گرم طلا</code>\n" .
        "• زیر چارت، دکمه‌های تغییر تایم‌فریم است.\n" .
        "• آدرس ولت (ترون / تون / بی‌ان‌بی) یا هش تراکنش را بفرستید تا خودکار بررسی شود.\n" .
        "• تاریخ و ساعت: کلمه <code>تاریخ</code> را بفرستید.\n" .
        "• اخبار روز ارز دیجیتال: <code>/News</code>\n" .
        "• شاخص ترس و طمع بازار: کلمهٔ <code>شاخص</code> را بفرستید.\n" .
        "• نقشهٔ لیکویدیتی: <code>لیکویدی بیت کوین</code> یا <code>/liquidy btc</code>\n" .
        "• تحلیل کامیونیتی TradingView: <code>تحلیل بیت کوین</code>\n" .
        "• لیست نمادهای پشتیبانی‌شده: <code>/list</code>"
    );
}

/** مسیر ماشین حالت. true = مصرف شد */
function routeState($chatId, $userId, array $state, array $msg, string $text): bool {
    $step = $state['step'];
    switch ($step) {
        case 'set_start':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            setBotText('start', $text);
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " متن استارت بروزرسانی شد.");
            sendStart($chatId, $userId);
            return true;

        case 'broadcast':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            clearState($chatId);
            sendMessage($chatId, "⏳ در حال ارسال همگانی...");
            $res = doBroadcast($chatId, $msg['message_id']);
            sendMessage($chatId, pemo('ok') . " ارسال شد.\nموفق: <b>{$res['ok']}</b> | ناموفق: <b>{$res['fail']}</b>");
            return true;

        case 'add_admin':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $id = (int)preg_replace('/\D/', '', $text);
            if ($id > 0) {
                db()->prepare("INSERT OR IGNORE INTO admins(chat_id,role) VALUES(?, 'admin')")->execute([$id]);
                sendMessage($chatId, pemo('ok') . " ادمین <code>$id</code> اضافه شد.");
            } else {
                sendMessage($chatId, emo('no') . " آیدی عددی نامعتبر است.");
            }
            clearState($chatId);
            return true;

        case 'add_fj':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $ch = trim($text);
            if ($ch !== '' && $ch[0] !== '@') { $ch = '@' . ltrim($ch, '@'); }
            if (preg_match('/^@[A-Za-z0-9_]{4,}$/', $ch)) {
                db()->prepare("INSERT OR IGNORE INTO force_join(channel,title,is_active) VALUES(?,?,1)")->execute([$ch, $ch]);
                sendMessage($chatId, pemo('ok') . " کانال $ch اضافه شد.\n<b>توجه:</b> ربات باید در آن کانال ادمین باشد.");
            } else {
                sendMessage($chatId, emo('no') . " یوزرنیم کانال نامعتبر است. مثل: <code>@channel</code>");
            }
            clearState($chatId);
            return true;

        case 'tron_tx':
            clearState($chatId);
            sendMessage($chatId, tronTxInfo($text));
            return true;

        case 'tron_wallet':
            clearState($chatId);
            $ch = detectWalletChain($text);
            if ($ch !== null) { sendWalletCard($chatId, $ch[0], trim($text)); }
            else { sendMessage($chatId, emo('no') . " آدرس ولت نامعتبر است. آدرس ترون (T...)، تون (EQ/UQ...) یا بی‌ان‌بی (0x...) را بفرستید."); }
            return true;

        case 'tron_tr':
            clearState($chatId);
            $r = tronTrc20Transfers($text, 1);
            sendMessage($chatId, $r['text'], $r['kb']);
            return true;

        case 'group_welcome_text':
            if (!isGroupAdmin($chatId, $userId)) { clearState($chatId); return true; }
            updateGroupSetting($chatId, 'welcome_text', $text);
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " پیام خوش‌آمدگویی بروزرسانی شد.", freshGroupSettingsKeyboard($chatId));
            return true;

        case 'set_tpl':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $key = (string)$state['data'];
            if (!isset(templateRegistry()[$key])) { clearState($chatId); return true; }
            setBotText($key, $text);
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " قالب «" . h(templateRegistry()[$key]) . "» بروزرسانی شد.", ikb([[backBtn('ap:tpl', 'primary')]]));
            return true;

        case 'set_btn':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $key = (string)$state['data'];
            if (!isset(btnLabelRegistry()[$key])) { clearState($chatId); return true; }
            setBotText($key, mb_substr(trim($text), 0, 64));
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " برچسب «" . h(btnLabelRegistry()[$key][0]) . "» بروزرسانی شد.", ikb([[backBtn('ap:btn', 'primary')]]));
            return true;

        case 'set_api':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $key = (string)$state['data'];
            if (!isset(apiRegistry()[$key])) { clearState($chatId); return true; }
            $url = trim($text);
            if ($url !== '' && !preg_match('~^https?://~i', $url)) {
                sendMessage($chatId, emo('no') . " آدرس باید با http:// یا https:// شروع شود. دوباره بفرستید یا انصراف دهید.");
                return true;
            }
            setSetting('api_' . $key, $url);
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " آدرس «" . h(apiRegistry()[$key][0]) . "» بروزرسانی شد.", ikb([[backBtn('ap:api', 'primary')]]));
            return true;

        case 'set_tgproxy':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $val = trim($text);
            if ($val !== '' && !preg_match('~^(https?|socks5h?)://~i', $val)) {
                sendMessage($chatId, emo('no') . " فرمت پروکسی نامعتبر است. با <code>http://</code>, <code>https://</code> یا <code>socks5://</code> شروع کنید. برای حذف پروکسی، فقط یک خط خالی یا «-» بفرستید.");
                return true;
            }
            if ($val === '-') { $val = ''; }
            setSetting('tg_proxy', $val);
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " پروکسی تلگرام " . ($val !== '' ? 'ذخیره شد.' : 'حذف شد.'), ikb([[backBtn('ap:home', 'primary')]]));
            return true;

        case 'set_chartcolor':
            if (!isGlobalAdmin($userId)) { clearState($chatId); return true; }
            $field = (string)$state['data'];
            $hex = normalizeHexColor($text);
            if ($hex === null) {
                sendMessage($chatId, emo('no') . " رنگ نامعتبر است. مثال درست: <code>#000000</code> یا <code>000000</code>");
                return true;
            }
            setSetting('chart_color_' . $field, $hex);
            clearState($chatId);
            sendMessage($chatId, pemo('ok') . " رنگ بروزرسانی شد.", ikb([[backBtn('ap:chartcolor', 'primary')]]));
            return true;
    }
    clearState($chatId);
    return false;
}

function handleGroup(array $msg, array $chat, $chatId): void {
    // پیام‌های سرویسی
    if (isset($msg['new_chat_members'])) { handleNewMembers($chatId, $chat, $msg['new_chat_members'], $msg['message_id'] ?? null); return; }
    if (isset($msg['left_chat_member']))  { handleLeftMember($chatId, $msg['left_chat_member']); return; }

    $from = $msg['from'] ?? null;
    if (!$from) { return; }
    registerGroup($chat, $from['id']);
    ensureGroupSettings($chatId);
    $userId = $from['id'];
    $isAdmin = isGroupAdmin($chatId, $userId);

    // فرم چندمرحله‌ای پنل مدیریت گروه (مثل ویرایش پیام خوش‌آمدگویی) در حال تکمیل توسط ادمین
    if ($isAdmin) {
        $state = getState($chatId);
        $pendingText = trim($msg['text'] ?? '');
        if ($state && ($state['step'] ?? '') === 'group_welcome_text' && $pendingText !== '') {
            if (routeState($chatId, $userId, $state, $msg, $pendingText)) { return; }
        }
    }

    // اعمال قوانین (فقط برای غیرادمین‌ها)
    if (!$isAdmin) {
        if (enforceLocks($chatId, $msg)) { return; }
        if (enforceFlood($chatId, $msg)) { return; }
        if (enforceFilters($chatId, $msg)) { return; }
    }

    $text = trim($msg['text'] ?? ($msg['caption'] ?? ''));
    if ($text === '') { return; }

    // دستورات مدیریتی
    if (handleGroupCommand($chatId, $msg, $text, $isAdmin)) { return; }

    // ابزارهای خودکار: تاریخ / ولت ترون / هش تراکنش / تبدیل مقدار
    $replyTo = $msg['message_id'] ?? null;
    if (tryAutoTools($chatId, $text, $replyTo)) { return; }

    // قیمت‌گیری با نماد
    $base = normalizeSymbol($text);
    if ($base !== null && groupPriceOn($chatId)) {
        if ($base === 'USDT') { sendUsdtCard($chatId, null, $replyTo); return; }
        if (isValidBase($base)) { sendPriceCard($chatId, $base, '30m', null, null, $replyTo); }
    }
}

function handleCallback(array $cb): void {
    $cbId = $cb['id'];
    $from = $cb['from'] ?? [];
    $userId = $from['id'] ?? 0;
    $msg = $cb['message'] ?? null;
    if (!$msg) { answerCallback($cbId); return; }
    $chatId = $msg['chat']['id'];
    $msgId = $msg['message_id'];
    $data = $cb['data'] ?? '';
    $isPrivate = ($msg['chat']['type'] ?? '') === 'private';

    // بستن
    if ($data === 'x') { deleteMessage($chatId, $msgId); answerCallback($cbId); return; }
    if ($data === 'noop') { answerCallback($cbId); return; }

    // جوین اجباری
    if ($data === 'fj:check') {
        $missing = userJoinedAll($userId);
        if (!$missing) {
            deleteMessage($chatId, $msgId);
            answerCallback($cbId, 'عضویت تایید شد ✅');
            sendStart($chatId, $userId);
        } else {
            answerCallback($cbId, 'هنوز عضو همه کانال‌ها نیستید.', true);
        }
        return;
    }

    // تغییر تایم‌فریم چارت
    if (strpos($data, 'tf:') === 0) {
        [, $base, $tf] = array_pad(explode(':', $data, 3), 3, '');
        if ($base && $tf) { sendPriceCard($chatId, $base, $tf, $msgId, $cbId); }
        else { answerCallback($cbId); }
        return;
    }
    // پیمایش تحلیل‌های کامیونیتی TradingView (قبلی/بعدی)
    if (strpos($data, 'tv:') === 0) {
        $parts = explode(':', $data, 3);
        $base = $parts[1] ?? ''; $idx = (int)($parts[2] ?? 0);
        if ($base !== '') { sendAnalysisCard($chatId, $base, $idx, $msgId, $cbId); }
        else { answerCallback($cbId); }
        return;
    }
    // صفحه‌بندی انتقال‌های ترون
    if (strpos($data, 'trp:') === 0) {
        $parts = explode(':', $data, 3);
        $addr = $parts[1] ?? ''; $pg = (int)($parts[2] ?? 1);
        $r = tronTrc20Transfers($addr, $pg);
        editMessageText($chatId, $msgId, $r['text'], $r['kb']);
        answerCallback($cbId);
        return;
    }

    // منوی اصلی
    if ($data === 'm:back') { editMessageText($chatId, $msgId, maybeQuote(getBotText('start') ?? 'منوی اصلی'), mainMenuKeyboard($userId)); answerCallback($cbId); return; }
    if ($data === 'm:help') { editMessageText($chatId, $msgId, helpText(), ikb([[backBtn('m:back', 'primary')]])); answerCallback($cbId); return; }
    if ($data === 'm:tron') { editMessageText($chatId, $msgId, pemo('tron') . " <b>ابزارهای ترون</b>\nیک گزینه را انتخاب کنید:", tronMenuKeyboard()); answerCallback($cbId); return; }

    // ترون: شروع فرم
    if ($data === 'tron:tx')     { setState($chatId, 'tron_tx');     editMessageText($chatId, $msgId, "🔎 <b>هش تراکنش</b> را ارسال کنید:", ikb([[backBtn('m:tron', 'primary')]])); answerCallback($cbId); return; }
    if ($data === 'tron:wallet') { setState($chatId, 'tron_wallet'); editMessageText($chatId, $msgId, pemo('wallet') . " <b>آدرس ولت</b> را ارسال کنید:\n<code>ترون (T...)</code> ، <code>تون (EQ/UQ...)</code> یا <code>بی‌ان‌بی (0x...)</code>", ikb([[backBtn('m:tron', 'primary')]])); answerCallback($cbId); return; }
    if ($data === 'tron:tr')     { setState($chatId, 'tron_tr');     editMessageText($chatId, $msgId, "📜 <b>آدرس ولت</b> را برای مشاهده انتقال‌های TRC20 ارسال کنید:", ikb([[backBtn('m:tron', 'primary')]])); answerCallback($cbId); return; }

    // پنل ادمین
    if (strpos($data, 'ap:') === 0) {
        if (!isGlobalAdmin($userId)) { answerCallback($cbId, 'دسترسی ندارید.', true); return; }
        handleAdminCallback($chatId, $msgId, $userId, $data, $cbId);
        return;
    }
    // تنظیمات گروه
    if (strpos($data, 'gs:') === 0) {
        if (!isGroupAdmin($chatId, $userId)) { answerCallback($cbId, 'فقط ادمین گروه.', true); return; }
        handleGroupSettingsCallback($chatId, $msgId, $data, $cbId);
        return;
    }

    answerCallback($cbId);
}

function handleAdminCallback($chatId, $msgId, $userId, string $data, $cbId): void {
    if ($data === 'ap:home')   { showAdminPanel($chatId, $msgId); answerCallback($cbId); return; }
    if ($data === 'ap:stats')  { showStats($chatId, $msgId); answerCallback($cbId); return; }
    if ($data === 'ap:statsrefresh') { showStats($chatId, $msgId, true); answerCallback($cbId, 'بروزرسانی شد.'); return; }
    if ($data === 'ap:admins') { showAdminsList($chatId, $msgId); answerCallback($cbId); return; }
    if ($data === 'ap:fj')     { showForceJoin($chatId, $msgId); answerCallback($cbId); return; }

    // ویرایش متن‌های ربات (قالب‌ها)
    if ($data === 'ap:tpl') { showTemplateList($chatId, $msgId); answerCallback($cbId); return; }
    if (strpos($data, 'ap:tpl:') === 0) { showTemplateEdit($chatId, $msgId, substr($data, strlen('ap:tpl:'))); answerCallback($cbId); return; }
    if (strpos($data, 'ap:tplreset:') === 0) {
        $key = substr($data, strlen('ap:tplreset:'));
        resetBotText($key);
        answerCallback($cbId, 'به پیش‌فرض بازگشت.');
        showTemplateEdit($chatId, $msgId, $key);
        return;
    }

    // ویرایش برچسب دکمه‌ها
    if ($data === 'ap:btn') { showBtnLabelList($chatId, $msgId); answerCallback($cbId); return; }
    if (strpos($data, 'ap:btn:') === 0) { showBtnLabelEdit($chatId, $msgId, substr($data, strlen('ap:btn:'))); answerCallback($cbId); return; }
    if (strpos($data, 'ap:btnreset:') === 0) {
        $key = substr($data, strlen('ap:btnreset:'));
        resetBotText($key);
        answerCallback($cbId, 'به پیش‌فرض بازگشت.');
        showBtnLabelEdit($chatId, $msgId, $key);
        return;
    }

    // مدیریت APIها
    if ($data === 'ap:api') { showApiList($chatId, $msgId); answerCallback($cbId); return; }
    if (strpos($data, 'ap:api:') === 0) { showApiEdit($chatId, $msgId, substr($data, strlen('ap:api:'))); answerCallback($cbId); return; }
    if (strpos($data, 'ap:apireset:') === 0) {
        $key = substr($data, strlen('ap:apireset:'));
        setSetting('api_' . $key, '');
        answerCallback($cbId, 'به پیش‌فرض بازگشت.');
        showApiEdit($chatId, $msgId, $key);
        return;
    }

    // رنگ چارت
    if ($data === 'ap:chartcolor') { showChartColorMenu($chatId, $msgId); answerCallback($cbId); return; }
    if (strpos($data, 'ap:cc:preset:') === 0) {
        applyChartColorPreset(substr($data, strlen('ap:cc:preset:')));
        showChartColorMenu($chatId, $msgId);
        answerCallback($cbId, 'اعمال شد.');
        return;
    }
    if (strpos($data, 'ap:cc:set:') === 0) {
        $field = substr($data, strlen('ap:cc:set:'));
        setState($chatId, 'set_chartcolor', $field);
        $labels = ['bg' => 'پس‌زمینه', 'up' => 'کندل صعودی', 'down' => 'کندل نزولی'];
        editMessageText($chatId, $msgId,
            "🎨 رنگ «" . ($labels[$field] ?? $field) . "» را به‌صورت هگز بفرستید (مثل <code>#000000</code> یا <code>2169ED</code>):",
            ikb([[btn('انصراف', 'ap:chartcolor', 'danger')]]));
        answerCallback($cbId);
        return;
    }

    // پروکسی تلگرام (برای هاست‌هایی که به api.telegram.org مستقیم دسترسی ندارند)
    if ($data === 'ap:tgproxy') {
        $cur = tgProxy();
        setState($chatId, 'set_tgproxy');
        $txt = "🌐 <b>پروکسی تلگرام</b>\n" .
            "اگر ربات روی هاستی است که اتصال مستقیمش به api.telegram.org قطع/فیلتر است " .
            "(علامتش: خطای «Operation timed out» در لاگ برای setWebhook/getMe/sendPhoto)، " .
            "یک پروکسی HTTP یا SOCKS5 اینجا بفرستید تا همهٔ درخواست‌های تلگرام از آن رد شود.\n\n" .
            "فرمت‌های مجاز:\n<code>http://ip:port</code>\n<code>http://user:pass@ip:port</code>\n<code>socks5://ip:port</code>\n\n" .
            "مقدار فعلی: <code>" . h($cur !== '' ? $cur : '— (خالی، بدون پروکسی)') . "</code>";
        $rows = [];
        if ($cur !== '') { $rows[] = [btn('حذف پروکسی', 'ap:tgproxyclear', 'danger')]; }
        $rows[] = [btn('انصراف', 'ap:home', 'danger')];
        editMessageText($chatId, $msgId, $txt, ikb($rows));
        answerCallback($cbId);
        return;
    }
    if ($data === 'ap:tgproxyclear') {
        setSetting('tg_proxy', '');
        showAdminPanel($chatId, $msgId);
        answerCallback($cbId, 'پروکسی حذف شد.');
        return;
    }

    if ($data === 'ap:quote') {
        $new = getSetting('quote_mode', '0') === '1' ? '0' : '1';
        setSetting('quote_mode', $new);
        showAdminPanel($chatId, $msgId);
        answerCallback($cbId, 'حالت نقل‌قول: ' . ($new === '1' ? 'روشن' : 'خاموش'));
        return;
    }
    if ($data === 'ap:start') {
        setState($chatId, 'set_start');
        editMessageText($chatId, $msgId, pemo('star') . " متن جدید استارت را ارسال کنید (HTML مجاز است):", ikb([[btn(emo('back') . ' انصراف', 'ap:home', 'danger', 'back')]]));
        answerCallback($cbId);
        return;
    }
    if ($data === 'ap:bc') {
        setState($chatId, 'broadcast');
        editMessageText($chatId, $msgId, pemo('bell') . " پیام همگانی را ارسال کنید (متن/عکس/هرچیز):", ikb([[btn(emo('back') . ' انصراف', 'ap:home', 'danger', 'back')]]));
        answerCallback($cbId);
        return;
    }
    if ($data === 'ap:admadd') {
        setState($chatId, 'add_admin');
        editMessageText($chatId, $msgId, pemo('admin') . " آیدی عددی ادمین جدید را ارسال کنید:", ikb([[btn(emo('back') . ' انصراف', 'ap:admins', 'danger', 'back')]]));
        answerCallback($cbId);
        return;
    }
    if ($data === 'ap:fjadd') {
        setState($chatId, 'add_fj');
        editMessageText($chatId, $msgId, "🔗 یوزرنیم کانال را ارسال کنید (مثل <code>@channel</code>):", ikb([[btn(emo('back') . ' انصراف', 'ap:fj', 'danger', 'back')]]));
        answerCallback($cbId);
        return;
    }
    if (strpos($data, 'ap:admdel:') === 0) {
        $id = (int)substr($data, strlen('ap:admdel:'));
        db()->prepare("DELETE FROM admins WHERE chat_id=? AND role<>'owner'")->execute([$id]);
        showAdminsList($chatId, $msgId);
        answerCallback($cbId, 'حذف شد.');
        return;
    }
    if (strpos($data, 'ap:fjtoggle:') === 0) {
        $ch = substr($data, strlen('ap:fjtoggle:'));
        db()->prepare("UPDATE force_join SET is_active=1-is_active WHERE channel=?")->execute([$ch]);
        showForceJoin($chatId, $msgId);
        answerCallback($cbId);
        return;
    }
    if (strpos($data, 'ap:fjdel:') === 0) {
        $ch = substr($data, strlen('ap:fjdel:'));
        db()->prepare("DELETE FROM force_join WHERE channel=?")->execute([$ch]);
        showForceJoin($chatId, $msgId);
        answerCallback($cbId, 'حذف شد.');
        return;
    }
    answerCallback($cbId);
}

function handleGroupSettingsCallback($chatId, $msgId, string $data, $cbId): void {
    if (strpos($data, 'gs:lock:') === 0) {
        $key = substr($data, strlen('gs:lock:'));
        $now = isLocked($chatId, $key);
        setLock($chatId, $key, !$now);
        // بازخوانی کش
        editMessageReplyMarkup($chatId, $msgId, freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId, ($now ? 'باز شد' : 'قفل شد'));
        return;
    }
    if ($data === 'gs:welcome') {
        $s = ensureGroupSettings($chatId);
        updateGroupSetting($chatId, 'welcome_on', (int)($s['welcome_on'] ?? 0) ? 0 : 1);
        editMessageReplyMarkup($chatId, $msgId, freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId);
        return;
    }
    if ($data === 'gs:flood') {
        $s = ensureGroupSettings($chatId);
        updateGroupSetting($chatId, 'antiflood_on', (int)($s['antiflood_on'] ?? 0) ? 0 : 1);
        editMessageReplyMarkup($chatId, $msgId, freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId);
        return;
    }
    if ($data === 'gs:price') {
        $s = ensureGroupSettings($chatId);
        updateGroupSetting($chatId, 'price_on', (int)($s['price_on'] ?? 1) ? 0 : 1);
        editMessageReplyMarkup($chatId, $msgId, freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId);
        return;
    }
    if ($data === 'gs:clearlocks') {
        db()->prepare("DELETE FROM locks WHERE chat_id=?")->execute([$chatId]);
        editMessageReplyMarkup($chatId, $msgId, freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId, 'همه قفل‌ها باز شد.');
        return;
    }
    if ($data === 'gs:welcomereply') {
        $s = ensureGroupSettings($chatId);
        updateGroupSetting($chatId, 'welcome_reply', (int)($s['welcome_reply'] ?? 0) ? 0 : 1);
        editMessageReplyMarkup($chatId, $msgId, freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId);
        return;
    }
    if ($data === 'gs:welcometext') {
        setState($chatId, 'group_welcome_text');
        $help = "متن جدید پیام خوش‌آمدگویی را بفرستید.\n" .
            "جای‌گذاری‌های مجاز: <code>{name}</code> عضو، <code>{group}</code> نام گروه، <code>{count}</code> تعداد اعضا.\n" .
            "برای ایموجی پریمیوم: <code>{{pe:wave}}</code> (کلیدهای بیشتر در پنل ادمین → راهنمای ایموجی).\n" .
            "برای نقل‌قول: <code>{{quote}}متن{{/quote}}</code>";
        sendMessage($chatId, pemo('wave') . " " . $help, ikb([[btn('انصراف', 'gs:home', 'danger')]]));
        answerCallback($cbId);
        return;
    }
    if ($data === 'gs:home') {
        clearState($chatId);
        editMessageText($chatId, $msgId, 'پنل مدیریت گروه:', freshGroupSettingsKeyboard($chatId));
        answerCallback($cbId);
        return;
    }
    answerCallback($cbId);
}

// بروزرسانی وضعیت عضویت خود ربات در گروه‌ها
function handleMyChatMember(array $upd): void {
    $chat = $upd['chat'] ?? null;
    $newStatus = $upd['new_chat_member']['status'] ?? '';
    if (!$chat) { return; }
    if (in_array($chat['type'] ?? '', ['group', 'supergroup'], true)) {
        if (in_array($newStatus, ['member', 'administrator'], true)) {
            registerGroup($chat, $upd['from']['id'] ?? null);
        } elseif (in_array($newStatus, ['left', 'kicked'], true)) {
            setGroupActive($chat['id'], 0);
        }
    }
}

// ==========================================================================
// 9) ورودی وبهوک و حالت راه‌اندازی
// ==========================================================================
function selfUrl(): string {
    if (BASE_URL !== '') { return BASE_URL; }
    $https = (($_SERVER['HTTPS'] ?? '') === 'on')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $scheme = $https ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['SCRIPT_NAME'] ?? '');
}

function runSetup(): void {
    header('Content-Type: text/html; charset=utf-8');
    if (($_GET['key'] ?? '') !== WEBHOOK_SECRET) {
        http_response_code(403);
        echo "دسترسی غیرمجاز.";
        return;
    }
    initDatabase();
    // محافظت از پوشه داده‌ها
    @file_put_contents(DATA_DIR . '/.htaccess', "Require all denied\nDeny from all\n");

    $mode = $_GET['setup'];
    if ($mode === 'delete') {
        $r = tgApi('deleteWebhook', ['drop_pending_updates' => true]);
        echo "<pre>" . h(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        return;
    }
    if ($mode === 'info') {
        $r = tgApi('getWebhookInfo');
        echo "<pre>" . h(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        return;
    }
    // ست‌کردن/حذف پروکسی تلگرام از طریق مرورگر (بدون نیاز به وبهوک فعال — برای وقتی که هاست
    // اصلاً به api.telegram.org دسترسی مستقیم ندارد و ادمین نمی‌تواند پیام به ربات بدهد).
    if ($mode === 'proxy') {
        $proxy = trim((string)($_GET['proxy'] ?? ''));
        if ($proxy !== '' && $proxy !== '-' && !preg_match('~^(https?|socks5h?)://~i', $proxy)) {
            echo "پروکسی نامعتبر است. باید با http:// ، https:// یا socks5:// شروع شود.";
            return;
        }
        setSetting('tg_proxy', $proxy === '-' ? '' : $proxy);
        echo "پروکسی تلگرام " . ($proxy !== '' && $proxy !== '-' ? "ذخیره شد: " . h($proxy) : "حذف شد") . "\nحالا دوباره <code>?setup=1&key=...</code> را باز کنید.";
        return;
    }
    // ست‌کردن وبهوک
    $url = selfUrl();
    $r = tgApi('setWebhook', [
        'url' => $url,
        'secret_token' => WEBHOOK_SECRET,
        'allowed_updates' => ['message', 'edited_message', 'callback_query', 'my_chat_member', 'chat_member'],
        'drop_pending_updates' => true,
        'max_connections' => 40,
    ]);
    $me = tgApi('getMe');
    echo "<div style='font-family:sans-serif;direction:rtl;padding:20px'>";
    echo "<h2>راه‌اندازی ربات</h2>";
    echo "<p><b>آدرس وبهوک:</b> " . h($url) . "</p>";
    echo "<p><b>setWebhook:</b> <pre>" . h(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></p>";
    echo "<p><b>getMe:</b> <pre>" . h(json_encode($me, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre></p>";
    if (empty($r['ok']) || empty($me['ok'])) {
        echo "<h3 style='color:red'>❌ خطا در ست‌کردن وبهوک</h3>";
        echo "<p>اگر پاسخ‌ها خالی/null هستند یا در لاگ خطای «Operation timed out» می‌بینید، یعنی این هاست " .
             "به api.telegram.org دسترسی مستقیم ندارد (فیلترینگ). یک پروکسی HTTP/SOCKS5 تهیه کنید و از این آدرس ست کنید:</p>" .
             "<pre>" . h($url) . "?setup=proxy&key=" . h(WEBHOOK_SECRET) . "&proxy=socks5://IP:PORT</pre>" .
             "<p>بعد دوباره همین صفحهٔ setup=1 را باز کنید.</p>";
    } else {
        echo "<h3 style='color:green'>✅ آماده است!</h3>";
    }
    echo "</div>";
}

function mainWebhookEntry(): void {
    // حالت راه‌اندازی از مرورگر
    if (isset($_GET['setup'])) { runSetup(); return; }

    // اعتبارسنجی هدر امنیتی وبهوک — فقط اگر وبهوک با secret_token ست شده باشد.
    // اگر وبهوک را دستی و بدون secret_token ست کرده باشید، تلگرام این هدر را نمی‌فرستد و ربات بازهم کار می‌کند.
    $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($secret !== '' && !hash_equals(WEBHOOK_SECRET, $secret)) {
        http_response_code(403);
        echo 'forbidden';
        return;
    }

    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') { http_response_code(200); echo 'ok'; return; }
    $update = json_decode($raw, true);
    if (!is_array($update)) { http_response_code(200); echo 'ok'; return; }

    // پاسخ سریع به تلگرام، پردازش در پس‌زمینه
    http_response_code(200);
    header('Content-Type: application/json');
    if (function_exists('fastcgi_finish_request')) {
        echo json_encode(['ok' => true]);
        @fastcgi_finish_request();
    }

    try {
        initDatabase();
        if (isset($update['callback_query'])) {
            handleCallback($update['callback_query']);
        } elseif (isset($update['my_chat_member'])) {
            handleMyChatMember($update['my_chat_member']);
        } elseif (isset($update['message'])) {
            handleMessage($update['message']);
        } elseif (isset($update['edited_message'])) {
            handleMessage($update['edited_message']);
        }
    } catch (Throwable $e) {
        error_log('bot error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

// اجرا
mainWebhookEntry();
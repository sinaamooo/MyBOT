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
 *   - نقشهٔ لیکویدیتی کوین‌گلس با «لیکویدی <ارز>»
 *   - تحلیل هوشمند تکنیکال (Smart Money Concepts) با هوش مصنوعی — «تحلیل <ارز>» با انتخاب تایم‌فریم
 *
 * راه‌اندازی: کل پوشه (شامل bot.php و پوشهٔ fonts/) را روی هاست بگذارید و یک‌بار این آدرس را باز کنید:
 *   https://YOURDOMAIN/bot.php?setup=1&key=WEBHOOK_SECRET
 * نیازمندی‌ها: PHP 7.4+ با اکستنشن‌های cURL, GD, PDO_SQLite, mbstring.
 * پوشهٔ fonts/ شامل فونت فارسی وزیرمتن — Vazirmatn (مجوز آزاد SIL OFL 1.1) است که برای تایپ‌شدن متن
 * فارسی داخل تصاویر تولیدی (مثل کارت اخبار) لازم است؛ اگر آپلود نشود، آن قابلیت به‌طور خودکار
 * و بدون خطا به حالت متنی ساده برمی‌گردد.
 * برای قابلیت‌های جدید (شاخص ترس‌وطمع پولی، لیکویدیتی دقیق‌تر، تحلیل هوش مصنوعی) کلیدهای
 * CMC_API_KEY / COINGLASS_API_KEY / AI_API_KEY را در بالای همین فایل تنظیم کنید
 * (شاخص ترس‌وطمع و لیکویدیتی حتی بدون این کلیدها هم به‌صورت رایگان کار می‌کنند).
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
// تایم‌فریم‌های تحلیل هوشمند AI (برچسب نمایش روی دکمه‌ها = کلید Binance interval)
const AI_TF_LABELS  = ['5m' => '5m', '30m' => '30m', '1h' => '1H', '4h' => '4H', '1d' => '1D'];
const AI_TF_BINANCE = ['5m' => '5m', '30m' => '30m', '1h' => '1h', '4h' => '4h', '1d' => '1d'];

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
// هوش مصنوعی (سازگار با فرمت OpenAI Chat Completions). پیش‌فرض روی Groq تنظیم شده چون یک
// کلید کاملاً رایگان (بدون کارت بانکی، فقط ثبت‌نام با ایمیل) از https://console.groq.com/keys
// می‌دهد و سرعت پاسخ‌دهی بالایی دارد. اگر بعداً خواستید از OpenAI/OpenRouter/... استفاده کنید،
// کافیست AI_API_URL و AI_MODEL را عوض کنید — فقط AI_API_KEY همیشه باید توسط خودتان پر شود،
// چون ساخت اکانت/کلید نیاز به تأیید ایمیل شماست و از سمت کد قابل انجام نیست.
const AI_API_KEY   = '';
const AI_API_URL   = 'https://api.groq.com/openai/v1/chat/completions';
const AI_MODEL     = 'llama-3.3-70b-versatile';
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
            price_on INTEGER DEFAULT 1, rules TEXT
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
    return $r;
}
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
                'flood_limit','flood_secs','warn_limit','warn_action','price_on','rules'];
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
function validUsdtSymbols(): array {
    static $set = null;
    if ($set !== null) { return $set; }
    $file = DATA_DIR . '/symbols.json';
    if (is_file($file) && (time() - filemtime($file) < 86400)) {
        $set = json_decode(@file_get_contents($file), true) ?: [];
        if ($set) { return $set; }
    }
    $set = [];
    $j = httpGet('https://api.binance.com/api/v3/exchangeInfo', 20);
    if ($j) {
        $d = json_decode($j, true);
        if (!empty($d['symbols'])) {
            foreach ($d['symbols'] as $s) {
                if (($s['quoteAsset'] ?? '') === 'USDT' && ($s['status'] ?? '') === 'TRADING') {
                    $set[$s['baseAsset']] = 1;
                }
            }
            if ($set) { @file_put_contents($file, json_encode($set)); }
        }
    }
    return $set;
}
function isValidBase(string $base): bool {
    $set = validUsdtSymbols();
    if (!$set) { return true; } // اگر لیست در دسترس نبود، اجازه بده (بایننس خودش خطا می‌دهد)
    return isset($set[strtoupper($base)]);
}
/** دستور /list: نمایش نمادهای پشتیبانی‌شده. محدودیت واقعی وجود ندارد — این فقط لیست
 *  بایننس (برای مرجع سریع) است؛ هر نماد دیگری هم از طریق زنجیرهٔ MEXC/والکس/CryptoCompare
 *  امتحان می‌شود، پس اگر نمادی اینجا نبود باز هم می‌توان مستقیماً امتحانش کرد. */
function sendSupportedList($chatId, $replyTo = null): void {
    $set = validUsdtSymbols();
    if (!$set) {
        sendMessage($chatId, emo('no') . " لیست ارزها موقتاً در دسترس نیست؛ اما محدودیتی وجود ندارد — هر نماد ارزی را مستقیم بفرستید تا قیمتش بررسی شود.", null, $replyTo);
        return;
    }
    $symbols = array_keys($set);
    sort($symbols);
    $count = count($symbols);
    $shown = array_slice($symbols, 0, 200);
    $t = pemo('chart') . " <b>نمادهای پشتیبانی‌شده</b> (مجموع: {$count})\n";
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
    $j = httpGet('https://api.binance.com/api/v3/ticker/24hr?symbol=' . urlencode($symbol));
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d['lastPrice']) ? $d : null;
}
/** قیمت لحظه‌ای بایننس (بدون آمار ۲۴ساعته) — سبک‌تر از ticker/24hr، برای پشتیبان/کاربردهای ساده */
function binancePriceOnly(string $symbol): ?float {
    $j = httpGet('https://api.binance.com/api/v3/ticker/price?symbol=' . urlencode($symbol));
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d['price']) && is_numeric($d['price']) ? (float)$d['price'] : null;
}
/** قیمت از CryptoCompare (پشتیبان، وقتی صرافی‌ها در دسترس نباشند) */
function cryptoComparePrice(string $base, string $quote = 'USD'): ?float {
    $j = httpGet('https://min-api.cryptocompare.com/data/price?fsym=' . urlencode(strtoupper($base)) . '&tsym=' . urlencode($quote), 10);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d[$quote]) && is_numeric($d[$quote]) ? (float)$d[$quote] : null;
}
/** قیمت لحظه‌ای از MEXC — API این صرافی هم‌فرمت با بایننس است (همان مسیر/فیلدها) */
function mexcPriceOnly(string $symbol): ?float {
    $j = httpGet('https://api.mexc.com/api/v3/ticker/price?symbol=' . urlencode($symbol), 10);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return isset($d['price']) && is_numeric($d['price']) ? (float)$d['price'] : null;
}
/** قیمت لحظه‌ای از دامنهٔ قدیمی MEXC (open/api/v2) — دامنهٔ جدا (www.mexc.com به‌جای api.mexc.com)
 *  که گاهی حتی وقتی دامنهٔ API جدید از هاست شما مسدود/در دسترس نیست، همچنان پاسخ می‌دهد. */
function mexcV2PriceOnly(string $base): ?float {
    $j = httpGet('https://www.mexc.com/open/api/v2/market/ticker?symbol=' . urlencode(strtoupper($base)) . '_USDT', 10);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    $last = $d['data'][0]['last'] ?? null;
    return ($last !== null && is_numeric($last)) ? (float)$last : null;
}
/** لیست بازارهای صرافی ایرانی والکس (کش در حافظهٔ همان اجرا) — چون صرافی داخلی است، معمولاً
 *  حتی وقتی بایننس/MEXC از هاست شما در دسترس نباشند، والکس در دسترس می‌ماند. */
function wallexMarkets(): ?array {
    static $cache = null;
    if ($cache !== null) { return $cache ?: null; }
    $j = httpGet('https://api.wallex.ir/v1/markets', 12);
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
 * ۳) MEXC — دامنهٔ جدید api.mexc.com (فقط قیمت لحظه‌ای)
 * ۴) MEXC — دامنهٔ قدیمی www.mexc.com/open/api/v2 (فقط قیمت لحظه‌ای، پشتیبان دامنهٔ جدید)
 * ۵) والکس — صرافی ایرانی (فقط قیمت لحظه‌ای)
 * ۶) CryptoCompare (فقط قیمت لحظه‌ای)
 * اگر فقط قیمت لحظه‌ای در دسترس باشد (حالت ۲ تا ۶)، چون داده‌ای برای آمار ۲۴ساعته نیست،
 * کارت سادهٔ بدون عکس چارت نمایش داده می‌شود (full=false) — چارت جدا واکشی می‌شود.
 */
function fetchPriceChain(string $symbol, string $base): array {
    $d = binance24h($symbol);
    if ($d) { return ['full' => true, 'data' => $d, 'price' => (float)$d['lastPrice']]; }
    $p = binancePriceOnly($symbol);
    if ($p !== null) { return ['full' => false, 'data' => null, 'price' => $p]; }
    $p = mexcPriceOnly($symbol);
    if ($p !== null) { return ['full' => false, 'data' => null, 'price' => $p]; }
    $p = mexcV2PriceOnly($base);
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
function binanceKlinesRaw(string $symbol, string $interval, int $limit): ?array {
    $j = httpGet('https://api.binance.com/api/v3/klines?symbol=' . urlencode($symbol) . '&interval=' . urlencode($interval) . '&limit=' . $limit);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return is_array($d) && $d ? $d : null;
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
    $j = httpGet('https://api.mexc.com/api/v3/klines?symbol=' . urlencode($symbol) . '&interval=' . urlencode($interval) . '&limit=' . $limit, 12);
    if (!$j) { return null; }
    $d = json_decode($j, true);
    return is_array($d) && $d ? $d : null;
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
    $j = httpGet('https://apiv2.nobitex.ir/market/stats?srcCurrency=' . $sym . '&dstCurrency=rls');
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
    $urls = [
        'https://call1.tgju.org/ajax.json',
        'http://call1.tgju.org/ajax.json',
        'https://call2.tgju.org/ajax.json',
        'http://call3.tgju.org/ajax.json',
    ];
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
    return btnUrl('➕ افزودن ربات به گروه', 'https://t.me/' . BOT_USERNAME . '?startgroup=true', 'danger');
}
function addGroupKeyboard(): array { return ikb([[addToGroupBtn()]]); }
/** دکمهٔ شیشه‌ای سبز افزودن ربات به گروه (برای قالب‌های اخبار/شاخص/لیکویدیتی/گیفت/تحلیل) */
function addToGroupBtnGreen(): array {
    return btnUrl('➕ افزودن ربات به گروه', 'https://t.me/' . BOT_USERNAME . '?startgroup=true', 'success');
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
function sendDateCard($chatId, $replyTo = null): void {
    $ts = time();
    $wd  = ['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'][(int)date('w', $ts)];
    $jmn = ['','فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    $hmn = ['','محرم','صفر','ربیع‌الاول','ربیع‌الثانی','جمادی‌الاول','جمادی‌الثانی','رجب','شعبان','رمضان','شوال','ذیقعده','ذیحجه'];
    [$jy,$jm,$jd] = gregorianToJalali((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));
    [$hy,$hm,$hd] = gregorianToHijri((int)date('Y',$ts),(int)date('n',$ts),(int)date('j',$ts));

    $t  = "🗓 <b>ساعت و تاریخ :</b>\n\n";
    $t .= "▪️ ساعت : \n └─  <b>" . date('H:i:s', $ts) . "</b>\n\n";
    $t .= "▪️ تاریخ امروز : \n └─  <b>{$wd} {$jd} {$jmn[$jm]} {$jy}</b>\n\n";
    $t .= "▪️ تاریخ قمری : \n └─  <b>{$hd} {$hmn[$hm]} {$hy}</b>\n\n";
    $t .= "▪️ تاریخ میلادی : \n └─  <b>" . date('d F Y', $ts) . "</b>";
    sendMessage($chatId, $t, null, $replyTo);
}

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

    $t  = pe('cv_coin') . " ارز {$name} | {$base}\n\n";
    $t .= "┓━━❲ تعداد {$qStr} ❳\n";
    $t .= "┨≡" . pe('cv_usd') . " دلار: " . fmtBig($usd) . " $\n";
    $t .= "┨≡" . pe('cv_star') . " استارز: " . number_format($stars) . "\n";
    $t .= "┚≡" . pe('cv_toman') . " تومن: " . ($toman !== null ? number_format(round($toman)) . " تومان" : "—") . "\n";
    $t .= priceQuote();
    sendMessage($chatId, $t, addGroupKeyboard(), $replyTo);
}

function timeframeKeyboard(string $base, string $active): array {
    $mk = function (string $tf) use ($base, $active) {
        $label = TF_LABELS[$tf] ?? $tf;
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

    $t  = "💎 ارز {$name} - {$base} " . pe('coin') . "\n\n";
    $t .= "┓━━❲ قیمت لحظه ای ❳\n";
    $t .= "┨≡" . pe('usd') . " دلار: " . fmtPrice($price) . " $\n";
    $t .= "┚≡" . pe('toman') . " تومن: {$toman}\n\n";
    $t .= "┓━━❲ اطلاعات 𝟐𝟒𝐡 ❳ " . pe('info24') . "\n";
    $t .= "┨≡" . pe('change') . " تغییرات: ❲ {$sign}" . number_format($chg, 2) . "% ❳\n";
    $t .= "┨≡" . pe('high') . " سقف: " . fmtPrice($high) . " $\n";
    $t .= "┨≡" . pe('low') . " کف: " . fmtPrice($low) . " $\n";
    $t .= "┚≡" . pe('volume') . " حجم: " . fmtBig($vol) . " $\n";
    $t .= priceQuote();
    return $t;
}

/** رندر چارت شمعی و برگرداندن مسیر فایل PNG موقت (اقتباس از Chart.php) */
function renderCandlestickChart(array $candles, string $symbol, string $interval): ?string {
    if (!function_exists('imagecreatetruecolor') || !$candles) { return null; }
    $width = 850; $height = 520;
    $pl = 90; $pr = 40; $pt = 50; $pb = 80;
    $cw = $width - $pl - $pr; $chh = $height - $pt - $pb;

    $img = imagecreatetruecolor($width, $height);
    // چارت تیره: پس‌زمینهٔ سیاه، کندل صعودی آبی پررنگ، کندل نزولی سفید
    $bg     = imagecolorallocate($img, 0, 0, 0);
    $border = imagecolorallocate($img, 55, 58, 66);
    $grid   = imagecolorallocate($img, 32, 34, 40);
    $up     = imagecolorallocate($img, 33, 111, 237);
    $down   = imagecolorallocate($img, 255, 255, 255);
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
/** چندضلعی پرشده سازگار با PHP 7.4 و 8.x */
function filledPoly($img, array $points, int $color): void {
    $n = intdiv(count($points), 2);
    if ($n < 3) { return; }
    if (PHP_VERSION_ID >= 80100) { imagefilledpolygon($img, $points, $color); }
    else { imagefilledpolygon($img, $points, $n, $color); }
}

/** نمودار سطحی (خط + سایهٔ گرادیانی زیر آن + هالهٔ نقطهٔ آخر) در مستطیل مشخص.
 *  $rgb سه‌تایی رنگ لهجه برای ساخت گرادیان محو. */
function drawAreaChart($img, array $series, int $x0, int $y0, int $x1, int $y1, int $line, int $fill, array $rgb = null, int $scale = 1): void {
    $series = array_values(array_filter($series, fn($v) => is_numeric($v) && $v > 0));
    $n = count($series);
    if ($n < 2) { return; }
    $min = min($series); $max = max($series);
    $pad = ($max - $min) * 0.14; if ($pad <= 0) { $pad = max(1, $max * 0.02); }
    $min -= $pad; $max += $pad;
    $w = $x1 - $x0; $h = $y1 - $y0;
    $pts = [];
    for ($i = 0; $i < $n; $i++) {
        $px = $x0 + (int)round($w * ($n === 1 ? 0 : $i / ($n - 1)));
        $py = $y1 - (int)round($h * ($series[$i] - $min) / ($max - $min));
        $pts[] = [$px, $py];
    }
    // پر کردن گرادیانی: چند نوار افقی با آلفای کاهنده از خط به سمت پایین
    if ($rgb) {
        $bands = 26;
        for ($b = 0; $b < $bands; $b++) {
            $ya = (int)round($y0 + ($y1 - $y0) * ($b / $bands));
            $yb = (int)round($y0 + ($y1 - $y0) * (($b + 1) / $bands));
            if ($yb <= $ya) { $yb = $ya + 1; }
            $alpha = (int)round(78 + ($b / $bands) * 49); // 78→127 (کم‌رنگ‌تر پایین)
            $bandCol = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], min(127, $alpha));
            $band = [];
            foreach ($pts as $p) { $band[] = $p[0]; $band[] = min(max($p[1], $ya), $yb); }
            $band[] = $x1; $band[] = $yb; $band[] = $x0; $band[] = $yb;
            filledPoly($img, $band, $bandCol);
        }
    } else {
        $poly = [];
        foreach ($pts as $p) { $poly[] = $p[0]; $poly[] = $p[1]; }
        $poly[] = $x1; $poly[] = $y1; $poly[] = $x0; $poly[] = $y1;
        filledPoly($img, $poly, $fill);
    }
    // خط اصلی
    imagesetthickness($img, 3 * $scale);
    for ($i = 1; $i < $n; $i++) { imageline($img, $pts[$i-1][0], $pts[$i-1][1], $pts[$i][0], $pts[$i][1], $line); }
    imagesetthickness($img, 1);
    // هالهٔ نقطهٔ آخر
    $last = $pts[$n - 1];
    if ($rgb) {
        $halo = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 96);
        imagefilledellipse($img, $last[0], $last[1], 22 * $scale, 22 * $scale, $halo);
    }
    imagefilledellipse($img, $last[0], $last[1], 12 * $scale, 12 * $scale, $line);
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

/**
 * کارت مدرن دلار/طلا — طرح شیشه‌ای تیره با نمودار سطحی گرادیانی (نسخه‌ای که کاربر ترجیح داد).
 * متن‌های داخل تصویر عمداً انگلیسی‌اند (خواستهٔ کاربر) — فارسی فقط در کپشن HTML زیر عکس می‌آید.
 */
function renderRialCard(string $asset, float $priceToman, array $series, array $meta = []): ?string {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) { return null; }
    $SS = 3; // سوپرسمپل: رسم در ابعاد بزرگ‌تر و کوچک‌سازی نرم در پایان برای لبه‌های صاف
    $W = 900; $H = 520;
    $Wp = $W * $SS; $Hp = $H * $SS;
    $img = imagecreatetruecolor($Wp, $Hp);
    imagealphablending($img, true); imagesavealpha($img, true);

    $isGold = ($asset === 'gold');
    // رنگ لهجه (زمردی برای دلار، طلایی برای طلا)
    $acc = $isGold ? [246, 199, 63] : [46, 213, 158];
    // پس‌زمینهٔ گرادیانی عمودی با ته‌رنگ لهجه
    $bgTop = $isGold ? [46, 35, 13] : [9, 26, 38];
    $bgBot = $isGold ? [17, 13, 5]  : [5, 10, 16];
    for ($y = 0; $y < $Hp; $y++) {
        $t = $y / ($Hp - 1);
        $c = imagecolorallocate($img,
            (int)round($bgTop[0] + ($bgBot[0] - $bgTop[0]) * $t),
            (int)round($bgTop[1] + ($bgBot[1] - $bgTop[1]) * $t),
            (int)round($bgTop[2] + ($bgBot[2] - $bgTop[2]) * $t));
        imageline($img, 0, $y, $Wp, $y, $c);
    }

    $accent     = imagecolorallocate($img, $acc[0], $acc[1], $acc[2]);
    $accentSoft = imagecolorallocatealpha($img, $acc[0], $acc[1], $acc[2], 102);
    $white  = imagecolorallocate($img, 245, 248, 251);
    $muted  = imagecolorallocate($img, 150, 161, 176);
    $faint  = imagecolorallocatealpha($img, 255, 255, 255, 118);
    $panel  = imagecolorallocatealpha($img, 255, 255, 255, 121); // پنل شیشه‌ای بسیار محو
    $chipBg = imagecolorallocatealpha($img, $acc[0], $acc[1], $acc[2], 112);

    // پنل داخلی گردگوشه + قاب لهجه‌ای نازک
    roundedRect($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $panel);
    roundedRectOutline($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $faint);
    // نوار لهجه‌ای بالای پنل
    imagefilledrectangle($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, 28 * $SS, $accent);

    $padX = 52 * $SS;

    // واترمارک بزرگ و محو نماد دارایی
    $wm = imagecolorallocatealpha($img, $acc[0], $acc[1], $acc[2], 118);
    cardText($img, $isGold ? 'AU' : '$', $Wp - 150 * $SS, 250 * $SS, 240 * $SS, $wm, true, 'center');

    // نمودار سطحی نیمهٔ پایین (با گرادیان و هاله)
    $chartTop = 300 * $SS; $chartBot = $Hp - 70 * $SS;
    for ($i = 1; $i <= 3; $i++) {
        $gy = (int)round($chartTop + ($chartBot - $chartTop) * $i / 4);
        imageline($img, $padX, $gy, $Wp - $padX, $gy, $faint);
    }
    drawAreaChart($img, $series, $padX, $chartTop, $Wp - $padX, $chartBot, $accent, $accentSoft, $acc, $SS);

    // چیپ برچسب دارایی (بالا-راست) داخل پیل
    $label = $isGold ? 'GOLD · 18K' : 'USD';
    pill($img, $Wp - $padX, 52 * $SS, $label, $chipBg, $white, 28 * $SS, 'right');
    cardText($img, 'IRT', $Wp - $padX, 100 * $SS, 20 * $SS, $muted, false, 'right');

    // مقدار (بالا-چپ)
    $qty = (float)($meta['qty'] ?? 1);
    $qtyStr = rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.');
    cardText($img, ($isGold ? $qtyStr . ' g' : 'x' . $qtyStr), $padX, 52 * $SS, 26 * $SS, $accent, true, 'left');

    // قیمت بزرگ (تومان)
    cardText($img, number_format((int)round($priceToman)), $padX, 122 * $SS, 88 * $SS, $white, true, 'left');
    cardText($img, 'TOMAN', $padX + 4 * $SS, 224 * $SS, 22 * $SS, $muted, false, 'left');

    // نشان تغییر داخل پیل رنگی — مثلث را خودمان با GD می‌کشیم (نه با کاراکتر ▲/▼) چون این
    // نویسه در خیلی از فونت‌ها گلیف ندارد و ممکن است روی برخی هاست‌ها اصلاً نمایش داده نشود.
    $dp = (float)($meta['dp'] ?? 0);
    $up = (($meta['dt'] ?? 'high') !== 'low');
    $badgeBg = imagecolorallocatealpha($img, $up ? 55 : 235, $up ? 214 : 92, $up ? 122 : 78, 96);
    $chgPx = 26 * $SS; $chgPt = $chgPx * 0.70;
    $chgText = ($up ? '+' : '-') . number_format(abs($dp), 2) . '%';
    $latFontB = findTtf(true) ?? findFaTtf(true);
    $chgBB = imagettfbbox($chgPt, 0, $latFontB, $chgText);
    $chgTw = abs($chgBB[2] - $chgBB[0]); $chgAsc = abs($chgBB[7]);
    $triSize = (int)round($chgPx * 0.24);
    $padH = (int)round($chgPx * 0.62); $padV = (int)round($chgPx * 0.36);
    $chgH = $chgAsc + $padV * 2;
    $chgW = $chgTw + $padH * 2 + $triSize * 3;
    $chgY = 260 * $SS;
    roundedRect($img, $padX, $chgY, $padX + $chgW, $chgY + $chgH, (int)($chgH / 2), $badgeBg);
    drawTrendTriangle($img, $padX + $padH + $triSize, (int)($chgY + $chgH / 2), $triSize, $up, $white);
    cardText($img, $chgText, $padX + $padH + $triSize * 3, $chgY + $padV, $chgPx, $white, true, 'left');

    // فوتر
    cardText($img, '@' . BOT_USERNAME, $padX, $Hp - 46 * $SS, 22 * $SS, $muted, false, 'left');
    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    cardText($img, sprintf('%04d/%02d/%02d  %s', $jy, $jm, $jd, date('H:i', $ts)), $Wp - $padX, $Hp - 46 * $SS, 22 * $SS, $muted, false, 'right');

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
    $kb = $chart ? timeframeKeyboard($base, $interval) : addGroupKeyboard();

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
/** کارت سادهٔ قیمت (بدون عکس چارت) — برای وقتی فقط قیمت لحظه‌ای از منبع پشتیبان در دسترس است */
function buildSimplePriceCaption(string $base, float $price): string {
    $name = coinName($base);
    $tmU = tomanFor($base, $price);
    $toman = $tmU !== null ? number_format(round($tmU)) . ' تومان' : '—';
    $t  = "💎 ارز {$name} - {$base} " . pe('coin') . "\n\n";
    $t .= "┓━━❲ قیمت لحظه‌ای ❳\n";
    $t .= "┨≡" . pe('usd') . " دلار: " . fmtPrice($price) . " $\n";
    $t .= "┚≡" . pe('toman') . " تومن: {$toman}\n";
    $t .= priceQuote();
    return $t;
}
/** تتر: فقط قیمت تومانی (بدون چارت) */
function sendUsdtCard($chatId, $cbId = null, $replyTo = null): bool {
    $rls   = nobitexRls('usdt');
    $toman = $rls !== null ? number_format(round($rls / 10)) . ' تومان' : '—';
    $t  = "💎 ارز Tether - USDT " . pe('coin') . "\n\n";
    $t .= "┓━━❲ قیمت لحظه ای ❳\n";
    $t .= "┨≡" . pe('usd') . " دلار: 1.00 $\n";
    $t .= "┚≡" . pe('toman') . " تومن: {$toman}\n";
    $t .= priceQuote();
    sendMessage($chatId, $t, addGroupKeyboard(), $replyTo);
    if ($cbId) { answerCallback($cbId); }
    return true;
}

// ---- کارت‌های دلار و طلای بازار آزاد (منبع tgju) ----
/** قالب متن دلار — نسخهٔ خفن با کادر، نشان تغییرات ۲۴ساعته و سقف/کف (ایموجی پریمیوم: r_usd / r_toman / r_date) */
function buildDollarCaption(float $qty, float $priceToman, array $meta = []): string {
    $qtyStr = rtrim(rtrim(number_format($qty, 4, '.', ','), '0'), '.');
    $dp    = (float)($meta['dp'] ?? 0);
    $up    = (($meta['dt'] ?? 'high') !== 'low');
    $arrow = pe('mark') . ($up ? ' ▲' : ' ▼');

    $t  = "💵 <b>نرخ دلار آمریکا</b> " . pe('r_usd') . "\n\n";
    $t .= "┓━━❲ نرخ لحظه‌ای ❳\n";
    $t .= "┨≡ " . pe('r_toman') . " تومان: <b>" . number_format(round($priceToman)) . "</b> تومان\n";
    $t .= "┨≡ " . pe('r_usd') . " مقدار: <b>{$qtyStr}</b> $\n";
    $t .= "┚≡ {$arrow} تغییر ۲۴س: " . pe('rg_change') . " <b>" . number_format(abs($dp), 2) . "%</b>\n";
    if (isset($meta['high']) && isset($meta['low'])) {
        $t .= "\n┓━━❲ " . pe('rg_label') . " سقف کف امروز ❳\n";
        $t .= "┨≡ " . pe('rg_high') . " سقف: <b>" . number_format(round($meta['high'])) . "</b> تومان\n";
        $t .= "┚≡ " . pe('rg_low') . " کف: <b>" . number_format(round($meta['low'])) . "</b> تومان\n";
    }
    $t .= "\n" . quoteBlock(pe('r_date') . " " . jalaliDateLine());
    return $t;
}

/** قالب متن طلای ۱۸ عیار — نسخهٔ خفن با کادر، نشان تغییرات و سقف/کف (ایموجی: g_qty / r_toman / r_usd) */
function buildGoldCaption(float $qty, float $priceToman, float $usdValue, array $meta = []): string {
    $qtyStr = rtrim(rtrim(number_format($qty, 4, '.', ','), '0'), '.');
    $dp    = (float)($meta['dp'] ?? 0);
    $up    = (($meta['dt'] ?? 'high') !== 'low');
    $arrow = pe('mark') . ($up ? ' ▲' : ' ▼');

    $t  = "🥇 <b>طلای ۱۸ عیار</b> " . pe('mark') . "\n\n";
    $t .= "┓━━❲ نرخ لحظه‌ای ❳\n";
    $t .= "┨≡ " . pe('g_qty') . " وزن: <b>{$qtyStr}</b> گرم\n";
    $t .= "┨≡ " . pe('r_toman') . " تومان: <b>" . number_format(round($priceToman)) . "</b> تومان\n";
    $t .= "┨≡ " . pe('mark') . " دلاری: <b>$" . number_format($usdValue, 2) . "</b>\n";
    $t .= "┚≡ {$arrow} تغییر ۲۴س: " . pe('rg_change') . " <b>" . number_format(abs($dp), 2) . "%</b>\n";
    if (isset($meta['high']) && isset($meta['low'])) {
        $t .= "\n┓━━❲ " . pe('rg_label') . " سقف کف امروز ❳\n";
        $t .= "┨≡ " . pe('rg_high') . " سقف: <b>" . number_format(round($meta['high'])) . "</b> تومان\n";
        $t .= "┚≡ " . pe('rg_low') . " کف: <b>" . number_format(round($meta['low'])) . "</b> تومان\n";
    }
    $t .= "\n" . quoteBlock(pe('r_date') . " " . jalaliDateLine());
    return $t;
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

function groupSettingsKeyboard($chatId): array {
    $s = ensureGroupSettings($chatId);
    $rows = [];
    // قفل‌ها (۲ در هر ردیف)
    $labels = lockLabels();
    $tmp = [];
    foreach ($labels as $key => $lbl) {
        $on = isLocked($chatId, $key);
        $mark = $on ? '🔒' : '🔓';
        $tmp[] = btn("$mark $lbl", "gs:lock:$key", $on ? 'success' : 'danger');
        if (count($tmp) === 2) { $rows[] = $tmp; $tmp = []; }
    }
    if ($tmp) { $rows[] = $tmp; }
    // قابلیت‌ها
    $wc = (int)($s['welcome_on'] ?? 0);
    $af = (int)($s['antiflood_on'] ?? 0);
    $pr = (int)($s['price_on'] ?? 1);
    $rows[] = [
        btn(($wc ? '✅' : '❌') . ' خوش‌آمد', 'gs:welcome', $wc ? 'success' : 'danger'),
        btn(($af ? '✅' : '❌') . ' ضدفلاد', 'gs:flood', $af ? 'success' : 'danger'),
    ];
    $rows[] = [
        btn(($pr ? '✅' : '❌') . ' قیمت‌گیری', 'gs:price', $pr ? 'success' : 'danger'),
        btn('🚫 پاک‌سازی قفل‌ها', 'gs:clearlocks', 'primary'),
    ];
    $rows[] = [btn(emo('no') . ' بستن', 'x', 'danger', 'no')];
    return ikb($rows);
}

// اعضای جدید / خروج
function handleNewMembers($chatId, array $chat, array $members): void {
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
    if ($tpl === '') { $tpl = pemo('wave') . " خوش آمدی {name} عزیز به {group}!"; }
    $count = getChatMemberCount($chatId);
    foreach ($members as $m) {
        if (isBotSelf($m)) { continue; }
        $txt = strtr($tpl, [
            '{name}'  => mentionUser($m),
            '{group}' => h($chat['title'] ?? ''),
            '{count}' => (string)$count,
        ]);
        sendMessage($chatId, $txt);
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
function showStats($chatId, $editMsgId): void {
    $s = getStats();
    $txt  = pemo('chart') . " <b>آمار ربات</b>\n";
    $txt .= quoteBlock(
        pemo('people') . " کاربران: <b>{$s['users']}</b>\n" .
        "➕ کاربران امروز: <b>{$s['today']}</b>\n" .
        emo('people') . " گروه‌های فعال: <b>{$s['groups_active']}</b> (کل: {$s['groups_total']})\n" .
        pemo('admin') . " ادمین‌ها: <b>{$s['admins']}</b>"
    );
    editMessageText($chatId, $editMsgId, $txt, ikb([[btn(emo('back') . ' بازگشت', 'ap:home', 'primary', 'back')]]));
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
    $kbRows[] = [btn(emo('back') . ' بازگشت', 'ap:home', 'primary', 'back')];
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
    $kbRows[] = [btn(emo('back') . ' بازگشت', 'ap:home', 'primary', 'back')];
    editMessageText($chatId, $editMsgId, $txt, ikb($kbRows));
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
    $j = httpGet('https://apilist.tronscanapi.com/api/transaction-info?hash=' . $hash);
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
    $j = httpGet('https://apilist.tronscanapi.com/api/account/tokens?address=' . urlencode($address) . '&start=0&limit=50');
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
    $j = httpGet('https://apilist.tronscanapi.com/api/new/token_trc20/transfers?limit=' . $per . '&start=' . $start . '&relatedAddress=' . urlencode($address));
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
    if ($page > 1)      { $nav[] = btn('◀️ قبلی', "trp:$address:" . ($page - 1), 'primary'); }
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
    $j = httpGet('https://apilist.tronscanapi.com/api/account/tokens?address=' . urlencode($address) . '&start=0&limit=50');
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
    $j = httpGet('https://toncenter.com/api/v2/getAddressBalance?address=' . urlencode($address));
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
function buildWalletCaption(string $chain, string $chainFa, string $address, float $usd, ?float $toman): string {
    $ts = time();
    [$jy, $jm, $jd] = gregorianToJalali((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $shamsi = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    $clock  = date('H:i:s', $ts);
    $usdStr = number_format($usd, 2, '.', '');
    $tmnStr = $toman !== null ? number_format((int)round($toman)) : '—';
    $chainPeKey = ['tron' => 'w_tron', 'ton' => 'w_ton', 'bnb' => 'w_bnb'][$chain] ?? null;
    $chainIcon = $chainPeKey ? pe($chainPeKey) : (CHAIN_EMOJI[$chain] ?? '🔷');

    $out  = pe('w_info') . " اطلاعات ولت " . $chainIcon . " " . h($chainFa) . "\n\n";
    $out .= "┓━━❲ " . pe('w_bal') . "موجودی ولت ❳\n";
    $out .= "┨≡ " . pe('w_usd') . " دلار: <b>$usdStr</b> $\n";
    $out .= "┨≡ " . pe('w_toman') . " تومان: <b>$tmnStr</b>\n\n";
    $out .= "┚≡ " . pe('w_addr') . " آدرس ولت :\n";
    $out .= pe('w_addr') . " <code>" . h($address) . "</code>\n\n";
    $out .= quoteBlock(pe('w_date') . " $clock | $shamsi");
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
    $xml = httpGet(NEWS_RSS_URL, 15);
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
function buildNewsCard(array $items): string {
    $t = "📰 ✨ <b>اخبار داغ ارز دیجیتال</b> ✨ 📰\n\n";
    if (!$items) {
        $t .= emo('no') . " در حال حاضر خبری دریافت نشد؛ کمی بعد دوباره تلاش کنید.";
        return $t;
    }
    foreach ($items as $it) {
        $t .= "🔸 <b>" . h($it['title']) . "</b>\n";
        $t .= "┨≡ " . newsImpact($it['title'], $it['desc']) . "\n";
        $t .= "┚≡ 🕐 " . newsTehranTime($it['pubDate']) . " (تهران)\n";
        $t .= "🔗 <a href=\"" . h($it['link']) . "\">مشاهده خبر کامل</a>\n\n";
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
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) { return null; }
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
/** رندر تصویر گِیج (سرعت‌سنج) شاخص ترس‌وطمع، سبک مدرن تیره با هالهٔ نوری هم‌رنگ وضعیت، خط‌کش
 *  درجه‌بندی‌شده و ردیف آمار روند (اختیاری). متن داخل تصویر عمداً لاتین است (محدودیت shaping
 *  فارسی در GD)؛ جزئیات فارسی در کپشن پیام می‌آید. */
function renderFearGreedGauge(int $value, string $labelEn, ?array $hist = null): ?string {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) { return null; }
    $SS = 3; // سوپرسمپل: رسم در ابعاد بزرگ‌تر و کوچک‌سازی نرم در پایان برای لبه‌های صاف (رفع پیکسلی‌بودن)
    $value = max(0, min(100, $value));
    $frac  = $value / 100;
    [$zr, $zg, $zb] = fngGaugeColor($frac); // رنگ لهجهٔ وضعیت فعلی، برای هاله/پیل/نوک عقربه

    $hasHist = $hist && (($hist['yesterday'] ?? null) !== null || ($hist['week_avg'] ?? null) !== null || ($hist['month_avg'] ?? null) !== null);
    $W = 900; $H = $hasHist ? 800 : 660;
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
    $white  = imagecolorallocate($img, 245, 248, 251);
    $muted  = imagecolorallocate($img, 150, 161, 176);
    $faint  = imagecolorallocatealpha($img, 255, 255, 255, 120);
    $panel  = imagecolorallocatealpha($img, 255, 255, 255, 123);
    $zoneCol  = imagecolorallocate($img, $zr, $zg, $zb);
    $zoneChip = imagecolorallocatealpha($img, $zr, $zg, $zb, 108);

    roundedRect($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $panel);
    roundedRectOutline($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, $Hp - 22 * $SS, 30 * $SS, $faint);
    // نوار لهجه‌ای بالای پنل، هم‌رنگ وضعیت فعلی (هماهنگ با سبک کارت‌های طلا/دلار)
    imagefilledrectangle($img, 22 * $SS, 22 * $SS, $Wp - 22 * $SS, 28 * $SS, $zoneCol);

    cardText($img, 'FEAR & GREED INDEX', (int)($Wp / 2), 50 * $SS, 34 * $SS, $white, true, 'center');

    $cx = (int)($Wp / 2); $cy = 410 * $SS; $rOuter = 230 * $SS; $rInner = 175 * $SS;

    // هالهٔ نوری ملایم هم‌رنگ وضعیت، فقط چسبیده به لبهٔ بیرونی کمان (نه سرتاسر کارت) —
    // آلفای بالا (نزدیک ۱۲۷ یعنی تقریباً شفاف) تا هاله محو بماند و رنگ پس‌زمینه را نبلعد.
    for ($i = 4; $i >= 1; $i--) {
        $alpha = 116 + $i; // 117..120 از ۱۲۷ (خیلی محو)
        $glow = imagecolorallocatealpha($img, $zr, $zg, $zb, min(127, $alpha));
        $rad = ($rOuter + $i * 12 * $SS) * 2;
        imagefilledellipse($img, $cx, $cy, $rad, $rad, $glow);
    }

    $segments = 200;
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

    // خط‌کش درجه‌بندی: ۵ نشانه (۰/۲۵/۵۰/۷۵/۱۰۰) دور کمان + سه برچسب راهنما
    imagesetthickness($img, 3 * $SS);
    foreach ([0, 25, 50, 75, 100] as $mark) {
        $ang = deg2rad(180 + ($mark / 100) * 180);
        $x0 = $cx + (int)round(cos($ang) * ($rOuter + 6 * $SS));
        $y0 = $cy + (int)round(sin($ang) * ($rOuter + 6 * $SS));
        $x1 = $cx + (int)round(cos($ang) * ($rOuter + 20 * $SS));
        $y1 = $cy + (int)round(sin($ang) * ($rOuter + 20 * $SS));
        imageline($img, $x0, $y0, $x1, $y1, $faint);
    }
    imagesetthickness($img, 1);
    cardText($img, 'FEAR', $cx - $rOuter, $cy + 26 * $SS, 18 * $SS, $muted, true, 'center');
    cardText($img, 'NEUTRAL', $cx, $cy - $rOuter - 40 * $SS, 18 * $SS, $muted, true, 'center');
    cardText($img, 'GREED', $cx + $rOuter, $cy + 26 * $SS, 18 * $SS, $muted, true, 'center');

    // عقربه با سایهٔ نرم + نوک رنگی هم‌رنگ وضعیت داخل هاب سفید
    $angleDeg = 180 + $frac * 180;
    $angleRad = deg2rad($angleDeg);
    $needleLen = $rInner - 18 * $SS;
    $nx = $cx + (int)round(cos($angleRad) * $needleLen);
    $ny = $cy + (int)round(sin($angleRad) * $needleLen);
    $shadow = imagecolorallocatealpha($img, 0, 0, 0, 90);
    imagesetthickness($img, 9 * $SS);
    imageline($img, $cx + 2 * $SS, $cy + 3 * $SS, $nx + 2 * $SS, $ny + 3 * $SS, $shadow);
    imagesetthickness($img, 7 * $SS);
    imageline($img, $cx, $cy, $nx, $ny, $white);
    imagesetthickness($img, 1);
    imagefilledellipse($img, $cx, $cy, 30 * $SS, 30 * $SS, $white);
    imagefilledellipse($img, $cx, $cy, 16 * $SS, 16 * $SS, $zoneCol);
    imagefilledellipse($img, $nx, $ny, 14 * $SS, 14 * $SS, $zoneCol);
    imagefilledellipse($img, $nx, $ny, 7 * $SS, 7 * $SS, $white);

    cardText($img, (string)$value, $cx, $cy + 40 * $SS, 78 * $SS, $white, true, 'center');
    // پیل رنگی وضعیت (خنثی/ترس/طمع...) زیر عدد بزرگ
    $moodText = strtoupper($labelEn);
    $moodPt = 20 * $SS * 0.70;
    $moodFont = findTtf(true) ?? findFaTtf(true);
    $mbb = imagettfbbox($moodPt, 0, $moodFont, $moodText);
    $mtw = abs($mbb[2] - $mbb[0]); $masc = abs($mbb[7]);
    $mPadH = 22 * $SS; $mPadV = 12 * $SS;
    $mw = $mtw + $mPadH * 2; $mh = $masc + $mPadV * 2;
    $mY = $cy + 126 * $SS;
    roundedRect($img, $cx - intdiv($mw, 2), $mY, $cx + intdiv($mw, 2), $mY + $mh, intdiv($mh, 2), $zoneChip);
    cardText($img, $moodText, $cx, $mY + $mPadV, 20 * $SS, $white, true, 'center');

    $footerY = $Hp - 44 * $SS;
    if ($hasHist) {
        // ردیف آمار روند: دیروز / میانگین هفته / میانگین ماه، هرکدام با رنگ هم‌رنگ مقدارش
        $sepY = $mY + $mh + 34 * $SS;
        imageline($img, 60 * $SS, $sepY, $Wp - 60 * $SS, $sepY, $faint);
        $stats = [
            ['label' => 'YESTERDAY',  'val' => $hist['yesterday'] ?? null],
            ['label' => 'WEEK AVG',   'val' => $hist['week_avg']  ?? null],
            ['label' => 'MONTH AVG',  'val' => $hist['month_avg'] ?? null],
        ];
        $cols = count($stats);
        $colW = ($Wp - 100 * $SS) / $cols;
        $rowY = $sepY + 32 * $SS;
        foreach ($stats as $i => $s) {
            $scx = (int)round(60 * $SS + $colW * ($i + 0.5));
            if ($s['val'] === null) { continue; }
            [$sr, $sg, $sb] = fngGaugeColor(max(0, min(100, $s['val'])) / 100);
            $sCol = imagecolorallocate($img, $sr, $sg, $sb);
            cardText($img, (string)$s['val'], $scx, $rowY, 36 * $SS, $sCol, true, 'center');
            cardText($img, $s['label'], $scx, $rowY + 50 * $SS, 16 * $SS, $muted, false, 'center');
        }
    }

    $ts = time();
    cardText($img, date('d F Y', $ts), 52 * $SS, $footerY, 20 * $SS, $muted, false, 'left');
    cardText($img, '@' . BOT_USERNAME, $Wp - 52 * $SS, $footerY, 20 * $SS, $muted, false, 'right');

    $out = imagecreatetruecolor($W, $H);
    imagecopyresampled($out, $img, 0, 0, 0, 0, $W, $H, $Wp, $Hp);
    imagedestroy($img);

    $tmp = tempnam(sys_get_temp_dir(), 'fng') . '.png';
    imagepng($out, $tmp);
    imagedestroy($out);
    return is_file($tmp) ? $tmp : null;
}
function buildFearGreedCaption(array $d, ?array $hist = null): string {
    $v = (int)$d['value'];
    $t  = "🧭 ✨ <b>شاخص ترس و طمع بازار کریپتو</b> ✨ 🧭\n\n";
    $t .= "┓━━❲ وضعیت امروز ❳\n";
    $t .= "┨≡ " . fngEmoji($v) . " عدد شاخص: <b>{$v} / 100</b>\n";
    $t .= "┚≡ 🏷 وضعیت: <b>" . fngLabelFa($v) . "</b>\n";
    if ($hist) {
        $t .= "\n┓━━❲ روند اخیر ❳\n";
        if ($hist['yesterday'] !== null) { $t .= "┨≡ " . fngEmoji($hist['yesterday']) . " دیروز: <b>{$hist['yesterday']}</b> " . fngLabelFa($hist['yesterday']) . "\n"; }
        if ($hist['week_avg']  !== null) { $t .= "┨≡ " . fngEmoji($hist['week_avg'])  . " میانگین هفتهٔ گذشته: <b>{$hist['week_avg']}</b>\n"; }
        if ($hist['month_avg'] !== null) { $t .= "┚≡ " . fngEmoji($hist['month_avg']) . " میانگین ماه گذشته: <b>{$hist['month_avg']}</b>\n"; }
    }
    $t .= "\n" . fngBar($v) . "\n\n";
    $t .= priceQuote();
    return $t;
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
    $j = httpGet('https://fapi.binance.com' . $path, 12);
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
function buildLiquidityCaption(array $d): string {
    $name = coinName($d['symbol']);
    $t  = "🌊 ✨ <b>نقشهٔ لیکویدیتی {$name}</b> ({$d['symbol']}) ✨ 🌊\n\n";
    $body = "💰 قیمت فعلی: <b>" . fmtPrice($d['current']) . "</b> $\n\n";
    if ($d['ceil'] !== null) {
        $body .= "📈 <b>سقف نقدینگی (ناحیهٔ احتمالی لیکویید شورت‌ها):</b>\n";
        $body .= "حدود <b>" . fmtPrice($d['ceil']) . "</b> $ — احتمال جاروب نقدینگی و برخورد با مقاومت.\n\n";
    }
    if ($d['floor'] !== null) {
        $body .= "📉 <b>کف نقدینگی (ناحیهٔ احتمالی لیکویید لانگ‌ها):</b>\n";
        $body .= "حدود <b>" . fmtPrice($d['floor']) . "</b> $ — احتمال واکنش قیمتی و برگشت روند.\n";
    }
    if (($d['funding'] ?? null) !== null || ($d['ls_ratio'] ?? null) !== null) {
        $body .= "\n";
        if (($d['funding'] ?? null) !== null) { $body .= "💸 نرخ فاندینگ: <b>" . number_format($d['funding'], 4) . "%</b>\n"; }
        if (($d['ls_ratio'] ?? null) !== null) { $body .= "⚖️ نسبت لانگ/شورت: <b>" . number_format($d['ls_ratio'], 2) . "</b>\n"; }
    }
    $t .= quoteExpandable($body);
    $t .= "\n📡 منبع: " . (($d['source'] ?? '') === 'coinglass' ? 'Coinglass' : 'برآورد رایگان از دادهٔ عمومی فیوچرز بایننس');
    $t .= "\n" . pe('date') . ' ' . jalaliDateLine();
    return $t;
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
// تحلیل هوشمند تکنیکال (Smart Money Concepts) با هوش مصنوعی — بدون محدودیت ارزی
// --------------------------------------------------------------------------
/** تشخیص درخواست «تحلیل ...» و استخراج نماد ارز (هر نمادی که در بایننس معتبر باشد، بدون محدودیت) */
function parseAnalysisRequest(string $t): ?string {
    $t = trim($t);
    if (!preg_match('/^(?:تحلیل|آنالیز|analysis)(?:\s+(.+))?$/iu', $t, $m)) { return null; }
    $rest = trim($m[1] ?? '');
    if ($rest === '') { return 'BTC'; }
    return normalizeSymbol($rest);
}
function aiTimeframeKeyboard(string $base): array {
    $mk = fn(string $tf, string $style) => btn(AI_TF_LABELS[$tf], "ai:tf:$base:$tf", $style);
    return ikb([
        [$mk('5m', 'danger'), $mk('30m', 'danger'), $mk('1h', 'danger')],
        [$mk('4h', 'primary'), $mk('1d', 'primary')],
        [addToGroupBtnGreen()],
    ]);
}
function sendAnalysisPrompt($chatId, string $base, $replyTo = null): void {
    if (!isValidBase($base)) { sendMessage($chatId, emo('no') . " نماد <b>" . h($base) . "</b> در بایننس پیدا نشد.", null, $replyTo); return; }
    $t = "🧠 ✨ <b>تحلیل هوشمند " . h(coinName($base)) . " ({$base})</b> ✨ 📊\n\n";
    $t .= "لـطفاً تـایم فـریـم مـورد نـظر خـود را مـشخـص کنـیـد";
    sendMessage($chatId, $t, aiTimeframeKeyboard($base), $replyTo);
}
function fetchOhlcForAnalysis(string $base, string $tf, int $limit = 120): ?array {
    $interval = AI_TF_BINANCE[$tf] ?? null;
    return $interval ? binanceKlinesRaw($base . 'USDT', $interval, $limit) : null;
}
function buildSmcPrompt(string $base, string $tf, array $candles): string {
    $lines = [];
    foreach ($candles as $c) {
        $lines[] = date('Y-m-d H:i', (int)($c[0] / 1000)) . ',' . $c[1] . ',' . $c[2] . ',' . $c[3] . ',' . $c[4] . ',' . $c[5];
    }
    $data = implode("\n", $lines);
    $tfLabel = AI_TF_LABELS[$tf] ?? $tf;
    $last = end($candles);
    $lastClose = $last ? (float)$last[4] : 0.0;

    return "شما یک تحلیل‌گر حرفه‌ای بازار ارزهای دیجیتال به سبک Smart Money Concepts (SMC) و Price Action هستید.\n" .
        "نماد: {$base}/USDT — تایم‌فریم: {$tfLabel} — آخرین قیمت بسته‌شدن: {$lastClose}\n" .
        "داده‌های کندل (از قدیم به جدید، فرمت هر خط: زمان,باز,سقف,کف,بسته,حجم):\n{$data}\n\n" .
        "بر اساس دقیقاً همین داده‌ها، یک تحلیل تکنیکال کوتاه و ساختاریافته به زبان فارسی بنویس که شامل موارد زیر باشد " .
        "(هرکدام حداکثر ۱-۲ خط، بدون مقدمهٔ اضافه و بدون دیسکلیمر مالی طولانی):\n" .
        "۱) روند کلی: صعودی/نزولی/رنج + دلیل کوتاه بر اساس پرایس‌اکشن\n" .
        "۲) نواحی Order Block مهم (محدودهٔ قیمتی تقریبی)\n" .
        "۳) ناحیهٔ FVG (Fair Value Gap) در صورت وجود\n" .
        "۴) نواحی نقدینگی (Liquidity) بالا و پایین\n" .
        "۵) آخرین CHOCH یا BOS مشاهده‌شده و در چه محدودهٔ قیمتی\n" .
        "۶) بهترین ناحیهٔ ورود/خروج پیشنهادی و جهت روند غالب\n" .
        "خروجی را دقیقاً با همین شماره‌گذاری و بدون هیچ متن اضافه یا Markdown بنویس.";
}
/** فراخوانی API هوش مصنوعی (سازگار با فرمت OpenAI Chat Completions) */
function callAiApi(string $prompt): ?string {
    if (AI_API_KEY === '') { return null; }
    $body = json_encode([
        'model' => AI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a professional crypto market analyst specializing in Smart Money Concepts and price action. Always answer in Persian (Farsi).'],
            ['role' => 'user', 'content' => $prompt],
        ],
        'temperature' => 0.4,
    ], JSON_UNESCAPED_UNICODE);
    $ch = curl_init(AI_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY],
        CURLOPT_TIMEOUT => 55, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res = curl_exec($ch); curl_close($ch);
    if (!$res) { return null; }
    $d = json_decode($res, true);
    $content = $d['choices'][0]['message']['content'] ?? null;
    return $content !== null ? trim($content) : null;
}
function buildAiAnalysisCaption(string $base, string $tf, string $analysis): string {
    $tfLabel = AI_TF_LABELS[$tf] ?? $tf;
    $t  = "🧠 ✨ <b>تحلیل هوشمند " . h(coinName($base)) . " ({$base})</b> ✨\n";
    $t .= "⏱ تایم‌فریم: <b>{$tfLabel}</b>\n\n";
    $t .= quoteExpandable(h($analysis));
    $t .= "\n" . priceQuote();
    return $t;
}
/** پس از انتخاب تایم‌فریم: دریافت کندل از بایننس (هر نمادی، بدون محدودیت) + تحلیل هوش مصنوعی + ارسال/ویرایش کارت */
function sendAiAnalysis($chatId, string $base, string $tf, $msgId = null, $cbId = null, $replyTo = null): void {
    if (!isValidBase($base)) {
        if ($cbId) { answerCallback($cbId, 'نماد نامعتبر است.', true); } else { sendMessage($chatId, emo('no') . " نماد نامعتبر است.", null, $replyTo); }
        return;
    }
    $candles = fetchOhlcForAnalysis($base, $tf, 120);
    if (!$candles) {
        $err = emo('no') . " دریافت داده‌های قیمتی ناموفق بود؛ کمی بعد دوباره تلاش کنید.";
        if ($msgId !== null) { editMessageText($chatId, $msgId, $err); } else { sendMessage($chatId, $err, null, $replyTo); }
        if ($cbId) { answerCallback($cbId); }
        return;
    }
    $analysis = callAiApi(buildSmcPrompt($base, $tf, $candles));
    if ($analysis === null) {
        $analysis = "برای فعال‌سازی تحلیل هوشمند، کلید AI_API_KEY را در تنظیمات ربات وارد کنید (سازگار با فرمت OpenAI Chat Completions).";
    }
    $cap = buildAiAnalysisCaption($base, $tf, $analysis);
    $kb  = ikb([[addToGroupBtnGreen()]]);
    if ($msgId !== null) { editMessageText($chatId, $msgId, $cap, $kb); } else { sendMessage($chatId, $cap, $kb, $replyTo); }
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
        [btn(emo('back') . ' بازگشت', 'm:back', 'danger', 'back')],
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

    // «تحلیل <ارز>» → منوی انتخاب تایم‌فریم تحلیل هوشمند (بدون محدودیت ارزی)
    $aiBase = parseAnalysisRequest($t);
    if ($aiBase !== null) { sendAnalysisPrompt($chatId, $aiBase, $replyTo); return true; }

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
        "• نقشهٔ لیکویدیتی: <code>لیکویدی بیت کوین</code>\n" .
        "• تحلیل هوشمند SMC: <code>تحلیل بیت کوین</code> (هر نمادی که در بایننس باشد)"
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
    }
    clearState($chatId);
    return false;
}

function handleGroup(array $msg, array $chat, $chatId): void {
    // پیام‌های سرویسی
    if (isset($msg['new_chat_members'])) { handleNewMembers($chatId, $chat, $msg['new_chat_members']); return; }
    if (isset($msg['left_chat_member']))  { handleLeftMember($chatId, $msg['left_chat_member']); return; }

    $from = $msg['from'] ?? null;
    if (!$from) { return; }
    registerGroup($chat, $from['id']);
    ensureGroupSettings($chatId);
    $userId = $from['id'];
    $isAdmin = isGroupAdmin($chatId, $userId);

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
    // انتخاب تایم‌فریم تحلیل هوشمند AI
    if (strpos($data, 'ai:tf:') === 0) {
        $parts = explode(':', $data, 4);
        $base = $parts[2] ?? ''; $tf = $parts[3] ?? '';
        if ($base && $tf) { sendAiAnalysis($chatId, $base, $tf, $msgId, $cbId); }
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
    if ($data === 'm:help') { editMessageText($chatId, $msgId, helpText(), ikb([[btn(emo('back') . ' بازگشت', 'm:back', 'primary', 'back')]])); answerCallback($cbId); return; }
    if ($data === 'm:tron') { editMessageText($chatId, $msgId, pemo('tron') . " <b>ابزارهای ترون</b>\nیک گزینه را انتخاب کنید:", tronMenuKeyboard()); answerCallback($cbId); return; }

    // ترون: شروع فرم
    if ($data === 'tron:tx')     { setState($chatId, 'tron_tx');     editMessageText($chatId, $msgId, "🔎 <b>هش تراکنش</b> را ارسال کنید:", ikb([[btn(emo('back') . ' بازگشت', 'm:tron', 'primary', 'back')]])); answerCallback($cbId); return; }
    if ($data === 'tron:wallet') { setState($chatId, 'tron_wallet'); editMessageText($chatId, $msgId, pemo('wallet') . " <b>آدرس ولت</b> را ارسال کنید:\n<code>ترون (T...)</code> ، <code>تون (EQ/UQ...)</code> یا <code>بی‌ان‌بی (0x...)</code>", ikb([[btn(emo('back') . ' بازگشت', 'm:tron', 'primary', 'back')]])); answerCallback($cbId); return; }
    if ($data === 'tron:tr')     { setState($chatId, 'tron_tr');     editMessageText($chatId, $msgId, "📜 <b>آدرس ولت</b> را برای مشاهده انتقال‌های TRC20 ارسال کنید:", ikb([[btn(emo('back') . ' بازگشت', 'm:tron', 'primary', 'back')]])); answerCallback($cbId); return; }

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
    if ($data === 'ap:admins') { showAdminsList($chatId, $msgId); answerCallback($cbId); return; }
    if ($data === 'ap:fj')     { showForceJoin($chatId, $msgId); answerCallback($cbId); return; }

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
    answerCallback($cbId);
}
/** ساخت کیبورد تنظیمات با کش تازه (بعد از تغییر) */
function freshGroupSettingsKeyboard($chatId): array {
    // ریست کش استاتیک lockList با کوئری مستقیم
    $st = db()->prepare("SELECT lock_type FROM locks WHERE chat_id=?");
    $st->execute([$chatId]);
    $locks = [];
    foreach ($st->fetchAll() as $r) { $locks[$r['lock_type']] = 1; }
    $s = ensureGroupSettings($chatId);
    $rows = [];
    $tmp = [];
    foreach (lockLabels() as $key => $lbl) {
        $on = isset($locks[$key]);
        $mark = $on ? '🔒' : '🔓';
        $tmp[] = btn("$mark $lbl", "gs:lock:$key", $on ? 'success' : 'danger');
        if (count($tmp) === 2) { $rows[] = $tmp; $tmp = []; }
    }
    if ($tmp) { $rows[] = $tmp; }
    $wc = (int)($s['welcome_on'] ?? 0);
    $af = (int)($s['antiflood_on'] ?? 0);
    $pr = (int)($s['price_on'] ?? 1);
    $rows[] = [
        btn(($wc ? '✅' : '❌') . ' خوش‌آمد', 'gs:welcome', $wc ? 'success' : 'danger'),
        btn(($af ? '✅' : '❌') . ' ضدفلاد', 'gs:flood', $af ? 'success' : 'danger'),
    ];
    $rows[] = [
        btn(($pr ? '✅' : '❌') . ' قیمت‌گیری', 'gs:price', $pr ? 'success' : 'danger'),
        btn('🚫 پاک‌سازی قفل‌ها', 'gs:clearlocks', 'primary'),
    ];
    $rows[] = [btn(emo('no') . ' بستن', 'x', 'danger', 'no')];
    return ikb($rows);
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
    echo (!empty($r['ok']) ? "<h3 style='color:green'>✅ آماده است!</h3>" : "<h3 style='color:red'>❌ خطا در ست‌کردن وبهوک</h3>");
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
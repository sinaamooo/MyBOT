<?php
/**
 * GiftIx Telegram Store Bot - single-file PHP webhook port of the original
 * python-telegram-bot (polling) implementation.
 *
 * SETUP
 * 1. Upload this file to your PHP host (needs PHP 7.4+ with the curl
 *    extension; no composer / no external libraries).
 * 2. Make sure the web server user can write to this file's directory
 *    (giftix_data.json / giftix_data.lock are created next to this file).
 * 3. Point Telegram's webhook at this file's public URL, either by:
 *      - visiting: https://yourdomain.com/bot.php?setup_webhook=1&url=https://yourdomain.com/bot.php
 *      - or calling Telegram's setWebhook API yourself.
 * 4. EDIT THE PLACEHOLDER CONSTANTS BELOW before going live:
 *      REQUIRED_CHANNEL, REPORTS_CHANNEL, BOT_USERNAME, SUPPORT_USERNAME,
 *      CARD_NUMBER, CARD_HOLDER.
 *    The bot token / admin id were supplied by the owner and are already
 *    filled in.
 *
 * ARCHITECTURE NOTES
 * - Telegram webhook requests can arrive concurrently, so the entire
 *   request is processed under an exclusive flock() on a side-lock-file,
 *   the JSON data store is loaded, mutated in place, and saved once at
 *   the end (atomic tmp-file + rename) before the lock is released.
 * - python-telegram-bot's per-user context.user_data (conversation state)
 *   has no PHP equivalent between stateless requests, so it is persisted
 *   inside the same JSON store under data.user_state[user_id].
 * - The daily top-up limit reset is lazy (checked/reset on read), exactly
 *   like the original get_daily_remaining() - no cron/scheduler needed.
 * - The original's periodic TON-price external API refresh and autosave
 *   background jobs have no equivalent in a webhook (no long-running
 *   process); every request already saves the store, and TON price stays
 *   at whatever the admin panel sets it to.
 */

declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
date_default_timezone_set('UTC');

/* ===================== CONFIG ===================== */

const BOT_TOKEN = '8759780599:AAEsFbf8h_oFR4ms4KFNqhqHNjTV0FxD3Lo';
const ADMIN_CHAT_ID = 8213021584;

// --- placeholders: owner must edit these before going live ---
const REQUIRED_CHANNEL = 'GiftIx_1';
const REPORTS_CHANNEL = 'GiftIx1_Reports';
const BOT_USERNAME = 'GiftIx1bot';
const SUPPORT_USERNAME = 'Eli_as13';
const CARD_NUMBER = '00000000000';
const CARD_HOLDER = 'name';
const TRUST_CHANNEL = 'GiftIx_1';
// --- end placeholders ---

const DATA_FILE = __DIR__ . '/giftix_data.json';
const LOCK_FILE = __DIR__ . '/giftix_data.lock';

const MIN_TON = 0.1;
const STARS_MIN = 50;

const REFERRAL_COMMISSION_PERCENT = 5;
const REFERRAL_SIGNUP_BONUS = 1000;
const REFERRAL_POINTS_REQUIRED = 7;
const REFERRAL_REWARD_GIFTS = ['teddy', 'heart'];

const TEHRAN_TZ = 'Asia/Tehran';

const GIFTS_META = [
    'teddy'        => ['name' => 'تدی',          'emoji' => '🧸', 'stars' => 15],
    'heart'        => ['name' => 'قلب',          'emoji' => '💗', 'stars' => 15],
    'rose'         => ['name' => 'گل رز',        'emoji' => '🌹', 'stars' => 25],
    'kado'         => ['name' => 'کادو',         'emoji' => '🎁', 'stars' => 25],
    'rocket'       => ['name' => 'سفینه',        'emoji' => '🚀', 'stars' => 50],
    'trophy'       => ['name' => 'جام',          'emoji' => '🏆', 'stars' => 100],
    'champagne'    => ['name' => 'شامپاین',      'emoji' => '🍾', 'stars' => 50],
    'flowers'      => ['name' => 'گل',           'emoji' => '💐', 'stars' => 50],
    'cake'         => ['name' => 'کیک',          'emoji' => '🎂', 'stars' => 50],
    'ring'         => ['name' => 'حلقه',         'emoji' => '💍', 'stars' => 100],
    'diamond'      => ['name' => 'الماس',        'emoji' => '💎', 'stars' => 100],
    'heart_val'    => ['name' => 'قلب ولن',      'emoji' => '💝', 'stars' => 52],
    'bear_val'     => ['name' => 'خرس ولن',      'emoji' => '🐻', 'stars' => 52],
    'bear_wday'    => ['name' => 'خرس روز زن',   'emoji' => '🧸', 'stars' => 52],
    'bear_april'   => ['name' => 'خرس 1 اوریل',  'emoji' => '🎪', 'stars' => 52],
    'bear_xmas'    => ['name' => 'خرس کریسمسی',  'emoji' => '🎅', 'stars' => 52],
    'bear_patrick' => ['name' => 'خرس پاتریک',   'emoji' => '🍀', 'stars' => 52],
];

const PRODUCT_PLACEHOLDER_NFT_TEXT = '🖼 بخش گیفت NFT (در حال تکمیل...)';

/* ===================== STATIC TEXT ===================== */

const WELCOME_TEXT = "🌟 ب ربات فروشگاه GiftIx خوش آمدید !\n\n" .
    "✨با ما بهترین خدمات تلگرام را با کمترین قیمت و بالاترین سرعت دریافت کنید\n\n" .
    "🎁 خدمات بات GiftIx\n\n" .
    "• ⭐️استارز تلگرام\n" .
    "• 💎پرمیوم تلگرام\n" .
    "• 🎁گیفت‌های استارزی و NFT\n" .
    "• ⚡️فروش ارز تون ( GRAM )\n\n" .
    "🚀 برای شروع ، یکی از گزینه‌های منو پایین را انتخاب کنید ☝️";

const PRODUCT_TEXT = "🛒 محصول مورد نظر خودتو انتخاب کن !\n\n" .
    "🚀 تمامی سفارشات با بالاترین سرعت و همراه با پشتیبانی کامل انجام می‌شن " .
    "تا تجربه‌ای مطمئن و بی‌دغدغه داشته باشی !\n\n" .
    "🫶 از لیست زیر ، گزینه مورد نظرت رو انتخاب کن :";

const PREMIUM_TEXT = "🟪 Telegram Premium\n\n" .
    "⭐️ سطح فوق جدیدی از امکانات تلگرام را تجربه کنید.\n\n" .
    "✅فعال شدن تیک آبی \n" .
    "💎 دسترسی به قابلیت‌های اختصاصی پریمیوم\n" .
    "📂 افزایش محدودیت‌های آپلود\n" .
    "⚡️ سرعت بیشتر در دانلود و استفاده از تلگرام\n" .
    "👨‍🎨 استیکرها، ایموجی‌ها و قابلیت‌های ویژه\n" .
    "📈 ابزارهای حرفه‌ای برای مدیریت بهتر\n\n" .
    "🛡 فعال‌سازی رسمی، \n" .
    "امن و بدون نیاز به دسترسی به اکانت\n\n" .
    "🚀 انجام سفارش در کوتاه‌ترین زمان ممکن\n\n" .
    "✍️ کدام گزینه را انتخاب می‌کنید؟";

const ASK_USERNAME_TEXT = "📎 یوزرنیم اکانت مورد نظر \n\n" .
    "✅اگر قصد خرید برای اکانت خودتان را دارید ، روی دکمه «برای خودم» کلیک کنید.\n\n" .
    "✅اگر قصد خرید برای شخص دیگری را دارید یوزرنیم تلگرام او را با علامت @ ارسال کنید\n\n" .
    "نمونه:\n" .
    "✅@Eli_as13\n" .
    "😭 Eli_as13";

const CANCELLED_TEXT = "👎 سفارش شما لغو شد\n\n" .
    "برای خرید مجدد، از منوی اصلی گزینه ' خـریـد مـحـصـول🛒' را انتخاب کنید.";

const TON_WALLET_TEXT = "💼 آدرس ولت تون ( TON )\n\n" .
    "⚠️ نکات مهم قبل از ارسال آدرس :\n\n" .
    "• آدرس باید مخصوص شبکه TON باشد.\n" .
    "• آدرس را کامل و بدون فاصله یا تغییر ارسال نمایید.\n" .
    "• مسئولیت صحت آدرس واردشده بر عهده کاربر است.\n\n" .
    "📎 نمونه فرمت آدرس ولت تون :\n" .
    "UQ….................…xY\n" .
    "یا\n" .
    "EQ….................…9K\n\n" .
    "🔓 لطفاً آدرس ولت تون ( TON ) خود را ارسال کنید :";

const TON_MEMO_QUESTION_TEXT = "💼 آدرس ولت ثبت شد!\n\n" .
    "💬 یک سوال مهم:\n\n" .
    "بعضی ولت‌های TON برای دریافت درستِ ارز، نیاز به کامنت (ممو) دارن " .
    "تا واریز به همون ولت بره و گم نشه!\n\n" .
    "🌟 ولت شما کامنت/ممو داره؟\n" .
    "• اگه داره، روی «بله، کامنت دارم» بزن و متن کامنت رو بفرست\n" .
    "• اگه نداره یا مطمئن نیستی، روی «رد کردن» بزن";

const TON_MEMO_INPUT_TEXT = "💬 ارسال کامنت/ممو ولت\n\n" .
    "لطفاً متن کامنت (ممو) ولت تون رو همینجا بفرستید " .
    "تا دقیقاً همون برای واریز استفاده بشه.";

const WALLET_INCREASE_TEXT = "💰 افـزایـش مـوجـودی حساب شما\n\n" .
    "شما می‌توانید موجودی خود را به دو روش امن و سریع افزایش دهید :\n\n" .
    "💳 پرداخت تومانی – پس از واریز ، موجودی شما به سرعت با تایید پشتیبانی شارژ میشود.\n\n" .
    "🚀 فرآیند افزایش موجودی ساده ، سریع و امن است و پس از پرداخت می‌توانید " .
    "به راحتی از تمامی خدمات ربات استفاده کنید ✔️";

const RECEIPT_PROMPT_TEXT = '💳 رسید خود را در قالب عکس ارسال کنید';

const RECEIPT_SENT_TEXT = "✅ درخواست افزایش موجودی شما با موفقیت ارسال شد\n\n" .
    "پس از تایید نهایی توسط ادمین، موجودی حساب شما به مبلغی که تعیین کرده اید شارژ میشود";

const STARS_BUY_TEXT_TPL = "🌟 خرید استارز تلگرام\n\n" .
    "🚀 تحویل آنی، پشتیبانی لحظه‌ای و ثبت سفارش بدون تأخیر\n\n" .
    "✨ مناسب برای:\n" .
    "💎  خرید تلگرام پریمیوم \n" .
    "⭐️ ری‌اکشن‌های استارزی\n" .
    "📣 تبلیغات تلگرامی\n" .
    "🛍 مینی‌اپ‌ها \n" .
    "🎁خرید گیفت\n\n" .
    "📈 حداقل خرید:  %d عدد\n\n" .
    "👇 تعداد استارز موردنیاز خود را وارد کنید:";

const GIFT_LIST_TEXT = "💰 هدیه دادن گیفت ، تجربه‌ای خاص در تلگرام!\n\n" .
    "⭐️ با گیفت‌های استارزی می‌تونی دوستان، خانواده یا معشوقت رو شگفت‌زده کنی !\n\n" .
    "💫 مزایای گیفت‌های استارزی :\n\n" .
    "• 🎁 ارسال هدیه به دوستان و آشنایان برای سوپرایز کردن\n" .
    "• 👾 قابل نمایش روی پروفایل تلگرام\n\n" .
    "💱 لطفاً گیفت مورد نظر خود را از لیست زیر انتخاب کنید :";

const GIFT_COMMENT_TYPE_TEXT = "💬 تنظیم کامنت گیفت\n\nنوع کامنت را انتخاب کنید:";
const GIFT_COMMENT_INPUT_TEXT = 'کامنت خود را ارسال کنید:';

const TRACK_TEXT = "📦 پیگیری سفارش\n\n\n" .
    "🔢 در صورتی که کد پیگیری محصول خود را دارید دکمه زیر را فشار دهید\n" .
    "‼️ برای اطلاع از وضعیت سفارش خود باید کد پیگیری داشته باشید";

const TRACK_ASK_CODE_TEXT = '🔢 کد پیگیری سفارشتان را ارسال کنید 🔍';
const TRACK_NOT_FOUND_TEXT = "❌ کد پیگیری یافت نشد.\n\nلطفا کد پیگیری صحیح رو دوباره ارسال کن.";

const SUPPORT_TEXT = 'لطفاً یکی از گزینه‌های زیر را انتخاب کنید تا با پشتیبانی ارتباط بگیرید 👇';
const SUPPORT_INDIRECT_TEXT = '💬 پیام خود را برای پشتیبانی ارسال کنید 📞';
const SUPPORT_SENT_CONFIRM_TEXT = '💬 پیام شما به پشتیبانی ارسال شد  و  در اسرع وقت به آن پاسخ داده خواهد شد✅';

const JOIN_CONFIRMED_TEXT = '✅ عضویتت تایید شد، حالا میتونی از ربات استفاده کنی.';
const BOT_OFF_TEXT = "🔴 ربات در حال حاضر خاموش است.\n\nبه زودی دوباره روشن خواهد شد، لطفا کمی صبر کنید 🙏";

const ADMIN_BROADCAST_ASK_TEXT = '📢 متن پیامی که می‌خوای برای تمام کاربران ربات ارسال بشه رو بفرست:';
const ADMIN_VIEW_USERS_EMPTY_TEXT = '👥 هنوز هیچ کاربری ربات رو استارت نکرده.';
const ADMIN_PRICE_MENU_TEXT = "💰 تنظیم قیمت محصولات\n\nمحصول مورد نظر برای تغییر قیمت رو انتخاب کن:";
const ADMIN_PREMIUM_PLAN_SELECT_TEXT = '✏️ قیمت کدوم پلن پرمیوم رو می‌خوای تغییر بدی؟';
const ADMIN_GIFT_PRICE_LIST_TEXT = '🎀 گیفت مورد نظر برای مشاهده/تغییر قیمت رو انتخاب کن:';

/* ===================== DYNAMIC TEXT HELPERS ===================== */

function fmt($n): string { return number_format((float) $n, 0, '.', ','); }

function stars_buy_text(): string { return sprintf(STARS_BUY_TEXT_TPL, STARS_MIN); }
function stars_min_error_text(): string { return "‼️ حداقل تعداد استارز برای خرید " . STARS_MIN . " عدد میباشد ..."; }

function ton_buy_text(array &$DATA): string {
    return "💰 خرید ارز تون (GRAM)\n\n" .
        "✨ ارز تون را با بهترین قیمت و تحویل مستقیم به کیف پول خود دریافت کنید.\n\n" .
        "💎 انتقال مستقیم به ولت \n" .
        "⚡️ انجام سریع سفارش\n" .
        "🔒 تراکنش امن و قابل‌اعتماد\n" .
        "🙏 پشتیبانی کامل در تمامی مراحل\n\n" .
        "📊 قیمت هر TON:  " . fmt($DATA['ton_price']) . " تومان\n" .
        "📌 حداقل سفارش: " . MIN_TON . " TON\n\n" .
        "☝️ مقدار TON موردنیاز خود را وارد کنید.\n\n" .
        "💡 مثال: 0.5 یا 2.5";
}

function join_text(): string { return "برای استفاده از ربات، باید در کانال‌های زیر عضو شوید\n\n@" . REQUIRED_CHANNEL; }

function trust_text(): string {
    return "⭐ چرا می‌توانید با خیال راحت به ما اعتماد کنید؟\n\n" .
        "ما یک ساله در زمینه‌ی خرید و فروش استارز و پرمیوم فعالیت داریم و در این مدت " .
        "با صداقت، سرعت و پشتیبانی قوی تونستیم اعتماد صدها کاربر رو جلب کنیم ❤️\n\n" .
        "✅ دلایل اعتماد :\n" .
        "• 💳 پرداخت سریع، مطمئن و شفاف\n" .
        "• 🌟 بیش از 200 رضایت واقعی مشتری\n" .
        "• 📞 پشتیبانی فعال 24 ساعته، قبل و بعد از خرید\n" .
        "• 🧾 دارای نماد اعتبار و سابقه‌ی چندساله فعالیت بدون هیچ گزارش منفی\n\n" .
        "📣 برای مشاهده‌ی نظرات و رضایت کاربران، می‌تونید در کانال @" . TRUST_CHANNEL . " ما\n" .
        "هشتگ زیر رو جست‌وجو کنید ☝️\n\n" .
        "☝️ #رضایت_GiftIx\n" .
        "اعتماد شما باعث افتخار ماست 💙";
}

function confirm_username_text(string $username): string { return "{$username}\n\n✅ آیا یوزرنیم بالا را تایید می‌کنید؟"; }

function premium_invoice_text($plan, $price, $username, $disc, $final): string {
    return "⏰ فاکتور خرید پرمیوم\n\n" .
        "💫 نوع پلن: {$plan} ماهه\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ فاکتور:  " . fmt($price) . " تومان\n" .
        "🎁 کل موجودی تخفیف:  " . fmt($disc) . " تومان\n\n" .
        "💡 حداکثر تخفیف قابل اعمال:  " . fmt(min($disc, $price)) . " تومان\n\n" .
        "💳 مبلغ نهایی:  " . fmt($final) . " تومان\n\n" .
        "🍾 در صورتی که جزئیات بالا مورد تأیید شماست \n\n" .
        "روی دکمه «تأیید ✅» کلیک کنید.";
}

function premium_success_text($plan, $username, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت ثبت شد!\n\n" .
        "💫 پلن: {$plan} ماهه\n" .
        "📎 یوزر: {$username}\n" .
        "💰 مبلغ پرداخت‌شده: " . fmt($price) . " تومان\n\n" .
        "🔑 کد پیگیری سفارش : {$order_id}\n\n" .
        "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
        "🙏 از اعتماد شما سپاسگزاریم.";
}

function premium_done_text($plan, $username, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
        "☝️اطلاعات سفارش:\n\n" .
        "ℹ️ نوع خدمات: پرمیوم تلگرام\n" .
        "💫 پلن: {$plan} ماهه\n" .
        "📎 یوزر: {$username}\n" .
        "💳 مبلغ نهایی پرداخت شده:  " . fmt($price) . " تومان\n" .
        "🔓 کد پیگیری سفارش: {$order_id}\n\n" .
        "😊 از اعتماد و انتخاب شما سپاسگزاریم.";
}

function admin_premium_text($user, $plan, $username, $price, $order_id): string {
    return "🛒 سفارش جدید - پرمیوم تلگرام\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n" .
        "💫 پلن: {$plan} ماهه\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ پرداخت‌شده: " . fmt($price) . " تومان\n" .
        "🔢 کد پیگیری: {$order_id}";
}

function ton_invoice_text($amount, $wallet, $memo, $price, $disc, $final): string {
    return "📑 فاکتور خرید ارز تون\n\n" .
        "💫 مقدار خرید: {$amount} تون\n" .
        "🏦 ولت آدرس دریافتی: {$wallet}\n" .
        "💬 ممو/کامنت ولت: {$memo}\n" .
        "💰 مبلغ فاکتور:  " . fmt($price) . " تومان\n" .
        "🎁 کل موجودی تخفیف:  " . fmt($disc) . " تومان\n\n" .
        "💡 حداکثر تخفیف قابل اعمال:  " . fmt(min($disc, $price)) . " تومان\n\n" .
        "💳 مبلغ نهایی:  " . fmt($final) . " تومان\n\n" .
        "🔮 در صورتی که جزئیات بالا مورد تأیید شماست ✓ \n" .
        "روی دکمه «تأیید ✅» کلیک کنید.";
}

function ton_success_text($amount, $wallet, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت ثبت شد.\n\n" .
        "⛏️نوع خدمات : خرید ارز تون\n" .
        "🔢 تعداد : {$amount}\n" .
        "📎 آدرس ولت : {$wallet}\n" .
        "💰 مبلغ پرداختی :  " . fmt($price) . " تومان\n" .
        "🔑 کد پیگیری سفارش : {$order_id}\n\n" .
        "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
        "🙏 از اعتماد شما سپاسگزاریم.";
}

function ton_done_text($amount, $wallet, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
        "☝️اطلاعات سفارش:\n\n" .
        "ℹ️ نوع خدمات: خرید ارز تون\n" .
        "👀 تعداد: {$amount} TON\n" .
        "🔗 ولت تون: {$wallet}\n" .
        "💳 مبلغ نهایی پرداخت شده:  " . fmt($price) . " تومان\n" .
        "🔓 کد پیگیری سفارش: {$order_id}\n\n" .
        "😊 از اعتماد و انتخاب شما سپاسگزاریم.";
}

function stars_invoice_text($count, $username, $price, $disc, $final): string {
    return "⏰ فاکتور خرید استارز\n\n" .
        "💫 مقدار خرید: {$count}\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ فاکتور:  " . fmt($price) . " تومان\n" .
        "🎁 کل موجودی تخفیف:  " . fmt($disc) . " تومان\n\n" .
        "💡 حداکثر تخفیف قابل اعمال:  " . fmt(min($disc, $price)) . " تومان\n\n" .
        "💳 مبلغ نهایی:  " . fmt($final) . " تومان\n\n" .
        "🍾 در صورتی که جزئیات بالا مورد تأیید شماست \n\n" .
        "روی دکمه «تأیید ✅» کلیک کنید.";
}

function stars_success_text($count, $username, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت ثبت شد!\n\n" .
        "⛏️ نوع خدمات: خرید استارز تلگرام\n" .
        "⭐️ تعداد: {$count} استارز\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ پرداختی:  " . fmt($price) . " تومان\n" .
        "🔑 کد پیگیری سفارش : {$order_id}\n\n" .
        "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
        "🙏 از اعتماد شما سپاسگزاریم.";
}

function stars_done_text($count, $username, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
        "☝️اطلاعات سفارش:\n\n" .
        "ℹ️ نوع خدمات: خرید استارز تلگرام\n" .
        "⭐️ تعداد: {$count} استارز\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💳 مبلغ نهایی پرداخت شده:  " . fmt($price) . " تومان\n" .
        "🔓 کد پیگیری سفارش: {$order_id}\n\n" .
        "😊 از اعتماد و انتخاب شما سپاسگزاریم.";
}

function admin_stars_text($user, $count, $username, $price, $order_id): string {
    return "🛒 سفارش جدید - استارز تلگرام\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n" .
        "⭐️ تعداد استارز: {$count}\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ پرداخت‌شده: " . fmt($price) . " تومان\n" .
        "🔢 کد پیگیری: {$order_id}";
}

function gift_label(string $key): string {
    $g = GIFTS_META[$key];
    return "گیفت {$g['name']} ({$g['stars']} ⭐️)";
}

function gift_invoice_text($key, $username, $hidden, $comment, $price, $disc, $final): string {
    $hide_str = $hidden ? '✅ بله' : '❌ خیر';
    $comment_str = $comment ? $comment : 'ندارد';
    return "📑 فاکتور خرید گیفت\n\n" .
        "💫 مقدار خرید: " . gift_label($key) . "\n" .
        "🔗 یوزر دریافت‌کننده: {$username}\n" .
        "🔒 گیفت هاید: {$hide_str}\n" .
        "کامنت : {$comment_str}\n\n" .
        "💰 مبلغ فاکتور:  " . fmt($price) . " تومان\n" .
        "🎁 کل موجودی تخفیف:  " . fmt($disc) . " تومان\n\n" .
        "💡 حداکثر تخفیف قابل اعمال:  " . fmt(min($disc, $price)) . " تومان\n\n" .
        "💳 مبلغ نهایی:  " . fmt($final) . " تومان\n\n" .
        "🔮 در صورتی که جزئیات بالا مورد تأیید شماست ✓ \n" .
        "روی دکمه «تأیید ✅» کلیک کنید.";
}

function gift_success_text($key, $username, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت ثبت شد!\n\n" .
        "⛏️ نوع خدمات: خرید گیفت استارز\n" .
        "🎁 گیفت: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ پرداختی:  " . fmt($price) . " تومان\n" .
        "🔑 کد پیگیری سفارش : {$order_id}\n\n" .
        "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
        "🙏 از اعتماد شما سپاسگزاریم.";
}

function gift_done_text($key, $username, $price, $order_id): string {
    return "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
        "☝️اطلاعات سفارش:\n\n" .
        "ℹ️ نوع خدمات: خرید گیفت استارز\n" .
        "🎁 گیفت: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💳 مبلغ نهایی پرداخت شده:  " . fmt($price) . " تومان\n" .
        "🔓 کد پیگیری سفارش: {$order_id}\n\n" .
        "😊 از اعتماد و انتخاب شما سپاسگزاریم.";
}

function admin_gift_text($user, $key, $username, $hidden, $comment, $price, $order_id): string {
    $hide_str = $hidden ? '✅ بله' : '❌ خیر';
    $comment_display = $comment ? ('<code>' . htmlspecialchars((string) $comment, ENT_QUOTES) . '</code>') : 'ندارد';
    return "🛒 سفارش جدید - گیفت استارز\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n" .
        "🎁 گیفت: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "🔒 هاید: {$hide_str}\n" .
        "💬 کامنت: {$comment_display}\n" .
        "💰 مبلغ پرداخت‌شده: " . fmt($price) . " تومان\n" .
        "🔢 کد پیگیری: {$order_id}";
}

function admin_support_text($user, string $message_text): string {
    return "📨 پیام جدید پشتیبانی\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n\n" .
        "💬 متن پیام:\n{$message_text}";
}

function order_report_fields(array $order): array {
    if ($order['type'] === 'premium') return ['پرمیوم تلگرام', '💎', "{$order['plan']} ماهه"];
    if ($order['type'] === 'ton') return ['خرید ارز تون', '💱', "{$order['amount']} TON"];
    if ($order['type'] === 'stars') return ['استارز تلگرام', '⭐️', "{$order['count']} 🌟"];
    if ($order['type'] === 'referral_reward') return ['جایزه رفرال', '🎁', gift_label($order['key'])];
    return ['گیفت استارز', '🎁', gift_label($order['key'])];
}

function price_display(array $order): string {
    if (($order['price'] ?? 0) <= 0) return 'رایگان 🎁';
    return fmt($order['price']) . ' تومان';
}

function report_text(string $buyer_name, array $order): string {
    [$label, $emoji, $qty] = order_report_fields($order);
    return "🛍 خرید موفق ✅\n\n" .
        "👤 خریدار: {$buyer_name}\n" .
        "🛒 سفارش : {$label} {$emoji}\n" .
        "⛏️ تعداد: {$qty}\n" .
        "💰 مبلغ پرداخت شده :  " . price_display($order) . " \n\n" .
        "⏰ " . persian_now_str() . " \n" .
        "📱 @" . BOT_USERNAME;
}

function order_status_text(array $order): string {
    $type_ = $order['type'];
    $status_label = (($order['status'] ?? '') === 'done') ? 'تکمیل شده ✅' : 'در حال انجام ⏳';

    if ($type_ === 'premium') {
        $label = 'پرمیوم تلگرام'; $emoji = '💎';
        $qty = "{$order['plan']} ماهه";
        $extra = "📎 یوزر دریافت‌کننده: {$order['username']}";
    } elseif ($type_ === 'ton') {
        $label = 'خرید ارز تون'; $emoji = '⚡️';
        $qty = "{$order['amount']} تون";
        $extra = "🔓 آدرس ولت کاربر دریافت کننده: {$order['wallet']}";
    } elseif ($type_ === 'stars') {
        $label = 'استارز تلگرام'; $emoji = '⭐️';
        $qty = "{$order['count']} عدد";
        $extra = "📎 یوزر دریافت‌کننده: {$order['username']}";
    } elseif ($type_ === 'referral_reward') {
        $label = 'جایزه رفرال'; $emoji = '🎁';
        $qty = gift_label($order['key']);
        $extra = "📎 یوزر دریافت‌کننده: {$order['username']}";
    } else {
        $label = 'گیفت استارز'; $emoji = '🎁';
        $qty = gift_label($order['key']);
        $extra = "📎 یوزر دریافت‌کننده: {$order['username']}";
    }

    $date_raw = $order['created_at'] ?? persian_now_str();
    $parts = preg_split('/\s+/', trim($date_raw));
    $date_display = (count($parts) >= 2) ? ($parts[0] . ' ساعت ' . end($parts)) : $date_raw;

    return "🔢 مشخصات سفارش \n\n" .
        "📦 نوع سفارش: {$label} {$emoji}\n" .
        "📊 تعداد: {$qty}\n" .
        "💼 قیمت:  " . price_display($order) . "\n" .
        "📱 وضعیت: {$status_label}\n\n\n" .
        "{$extra}\n" .
        "🗓️ تاریخ و ساعت: {$date_display}";
}

function admin_panel_text(array &$DATA): string {
    $status = $DATA['bot_enabled'] ? '🟢 روشن' : '🔴 خاموش';
    $ref_status = $DATA['referral_points_enabled'] ? '🟢 فعال' : '🔴 غیرفعال';
    return "🛠 پنل مدیریت ربات\n\n" .
        "وضعیت فعلی ربات: {$status}\n" .
        "وضعیت جایزه دعوت: {$ref_status}\n\n" .
        "از گزینه‌های زیر یکی رو انتخاب کن:";
}

function broadcast_confirm_text(string $text): string { return "📢 متن زیر برای تمام کاربران ربات ارسال خواهد شد:\n\n{$text}\n\nآیا مطمئن هستید؟"; }
function broadcast_message_text(string $text): string { return "👤 پیام مدیریت به تمام کاربران\n\n{$text}"; }
function broadcast_done_text(int $success, int $fail): string { return "✅ پیام به {$success} کاربر ارسال شد.\n❌ ارسال به {$fail} کاربر ناموفق بود."; }

function view_users_text(array &$DATA): string {
    if (empty($DATA['users'])) return ADMIN_VIEW_USERS_EMPTY_TEXT;
    $lines = [];
    foreach ($DATA['users'] as $uid => $udata) {
        $name = $DATA['user_names'][$uid] ?? 'ناشناس';
        $lines[] = "👤 {$name} | 🆔 {$uid} | 💰 " . fmt($udata['balance']) . ' تومان';
    }
    $header = '👥 کاربران ربات (' . count($DATA['users']) . " نفر)\n\n";
    $full = $header . implode("\n", $lines);
    if (mb_strlen($full) > 4000) $full = mb_substr($full, 0, 4000) . "\n\n...و بقیه (متن بریده شد)";
    return $full;
}

function admin_stars_price_text(array &$DATA): string { return "⭐️ قیمت فعلی هر ۱ استارز:\n\n💰 " . fmt($DATA['stars_price']) . ' تومان'; }
function admin_ask_stars_price_text(): string { return '✏️ قیمت جدید هر ۱ استارز رو به تومان وارد کن:'; }

function admin_ton_price_text(array &$DATA): string {
    $price_per_tenth = (int) round($DATA['ton_price'] * MIN_TON);
    return "💱 قیمت فعلی هر " . MIN_TON . " TON:\n\n💰 " . fmt($price_per_tenth) . ' تومان';
}
function admin_ask_ton_price_text(): string { return '✏️ قیمت جدید هر ' . MIN_TON . ' TON رو به تومان وارد کن:'; }

function admin_premium_price_text(array &$DATA): string {
    $p = $DATA['premium_prices'];
    return "💎 قیمت‌های فعلی پرمیوم تلگرام:\n\n" .
        "🔹 3 ماهه:  " . fmt($p['3']) . " تومان\n" .
        "🔹 6 ماهه:  " . fmt($p['6']) . " تومان\n" .
        "🔹 12 ماهه:  " . fmt($p['12']) . ' تومان';
}
function admin_ask_premium_price_text(string $plan): string { return "✏️ قیمت جدید پلن {$plan} ماهه رو به تومان وارد کن:"; }

function admin_gift_price_detail_text(array &$DATA, string $key): string {
    return gift_label($key) . "\n\n💰 قیمت فعلی: " . fmt($DATA['gifts_prices'][$key]) . ' تومان';
}
function admin_ask_gift_price_text(string $key): string { return '✏️ قیمت جدید ' . gift_label($key) . ' رو به تومان وارد کن:'; }

function admin_daily_limit_text(array &$DATA): string {
    return "⚙️ تنظیم سقف شارژ روزانه\n\n" .
        "💳 سقف مجاز فعلی: " . fmt($DATA['daily_limit']) . " تومان\n\n" .
        'این مقدار حداکثر شارژ هر کاربر در روز است.';
}
function admin_ask_daily_limit_text(array &$DATA): string {
    return "✏️ سقف مجاز شارژ روزانه جدید رو به تومان وارد کن:\n\n(سقف فعلی: " . fmt($DATA['daily_limit']) . ' تومان)';
}

function admin_profit_menu_text(): string { return "📈 تغییر درصد سود محصولات\n\nمحصول مورد نظر رو انتخاب کن:"; }

function admin_profit_stars_text(array &$DATA): string {
    $pct = $DATA['profit_percent']['stars'];
    $base = $DATA['base_stars_price'] * 50;
    $profit_amt = (int) round($base * $pct / 100);
    $sell = $DATA['stars_price'] * 50;
    return "⭐️ درصد سود استارز تلگرام\n\n" .
        "📦 مقدار نمایشی: " . STARS_MIN . " استارز\n" .
        "💵 قیمت پایه " . STARS_MIN . " استارز:  " . fmt($base) . " تومان\n" .
        "📈 درصد سود فعلی: {$pct}٪\n" .
        "💰 مقدار سود:  " . fmt($profit_amt) . " تومان\n" .
        "🏷 قیمت فروش " . STARS_MIN . " استارز:  " . fmt($sell) . ' تومان';
}

function admin_profit_ton_text(array &$DATA): string {
    $pct = $DATA['profit_percent']['ton'];
    $base = $DATA['base_ton_price'];
    $profit_amt = (int) round($base * $pct / 100);
    $sell = $DATA['ton_price'];
    return "💱 درصد سود ارز تون\n\n" .
        "📦 مقدار نمایشی: ۱ TON\n" .
        "💵 قیمت پایه ۱ TON:  " . fmt($base) . " تومان\n" .
        "📈 درصد سود فعلی: {$pct}٪\n" .
        "💰 مقدار سود:  " . fmt($profit_amt) . " تومان\n" .
        "🏷 قیمت فروش ۱ TON:  " . fmt($sell) . ' تومان';
}

function admin_profit_gift_text(array &$DATA): string {
    $pct = $DATA['profit_percent']['gift'];
    $base_per_star = $DATA['base_stars_price'];
    $sell_per_star = $DATA['gift_star_price'];
    $profit_amt = $sell_per_star - $base_per_star;
    return "🎀 درصد سود گیفت استارز\n\n" .
        "📦 مقدار نمایشی: هر استارز گیفت\n" .
        "💵 قیمت پایه هر استارز:  " . fmt($base_per_star) . " تومان\n" .
        "📈 درصد سود فعلی: {$pct}٪\n" .
        "💰 مقدار سود هر استارز:  " . fmt($profit_amt) . " تومان\n" .
        "🏷 قیمت فروش هر استارز گیفت:  " . fmt($sell_per_star) . " تومان\n\n" .
        '⚙️ این درصد روی قیمت همه گیفت‌ها اعمال می‌شود.';
}

function admin_profit_premium_text(array &$DATA): string {
    $pct = $DATA['profit_percent']['premium'];
    $lines = [];
    foreach ($DATA['base_premium_prices'] as $plan => $base) {
        $sell = $DATA['premium_prices'][$plan];
        $profit_amt = $sell - $base;
        $lines[] = "🔹 {$plan} ماهه: " . fmt($base) . ' ← ' . fmt($sell) . ' تومان (+' . fmt($profit_amt) . ')';
    }
    return "💎 درصد سود پرمیوم تلگرام\n\n📈 درصد سود فعلی: {$pct}٪\n\n" . implode("\n", $lines);
}

function admin_ask_profit_text(string $product_fa): string { return "✏️ درصد سود جدید {$product_fa} رو وارد کن (فقط عدد، بدون %):"; }

function admin_ton_text($user, $amount, $wallet, $memo, $price, $order_id): string {
    $walletSafe = htmlspecialchars((string) $wallet, ENT_QUOTES);
    return "🛒 سفارش جدید - خرید ارز تون\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n" .
        "💫 مقدار: {$amount} TON\n" .
        "🏦 آدرس ولت:\n<code>{$walletSafe}</code>\n" .
        "💬 ممو: {$memo}\n" .
        "💰 مبلغ پرداخت‌شده: " . fmt($price) . " تومان\n" .
        "🔢 کد پیگیری: {$order_id}";
}

function insufficient_text(int $shortfall): string {
    return "برای ادامه خرید مبلغ کمبود شما " . fmt($shortfall) . ' تومان است. یکی از روش های زیر را برای شارژ سریع انتخاب کنید:';
}

function wallet_text(int $balance, int $remaining): string {
    return "کیف پول 💰\n\n" .
        "موجودی شما:  " . fmt($balance) . " تومان 💸\n" .
        "باقی مانده سقف مجاز افزایش موجودی تومانی امروز:  " . fmt($remaining) . " 🚀\n\n" .
        'برای افزایش موجودی خود روی دکمه زیر کلیک کنید 👇';
}

function toman_payment_text(int $remaining): string {
    return "💳 پرداخت تومانی \n\n" .
        "ابتدا مبلغی که قصد دارید به موجودی خود اضافه کنید را وارد کنید.\n\n\n" .
        "📱 باقیمانده سقف مجاز پرداخت تومانی امروز :  " . fmt($remaining) . " تومان\n\n\n" .
        '‼️ توجه کنید که مبلغ واریزی نباید بیشتر از مبلغ سقف مجاز باشد ، ' .
        'در غیر اینصورت حساب شما فقط در حد سقف مجاز شارژ خواهد شد ' .
        'و مسئولیت آن به عهده خودتان است ❌';
}

function daily_limit_exceeded_text(int $remaining): string {
    if ($remaining <= 0) {
        return "❌ سقف مجاز شارژ تومانی امروز شما تموم شده.\n\nلطفا فردا دوباره تلاش کن یا با پشتیبانی در ارتباط باش.";
    }
    return "❌ مبلغی که وارد کردی بیشتر از سقف مجاز باقی‌مونده امروزته.\n\n" .
        "📱 سقف مجاز باقی‌مونده امروز شما:  " . fmt($remaining) . " تومان\n\n" .
        'لطفا مبلغ کمتر یا مساوی همین مقدار رو وارد کن.';
}

function card_details_text(int $amount): string {
    return "💸 مبلغ واریزی:   " . fmt($amount) . " تومان\n\n" .
        "💼 شماره کارت جهت واریز :\n\n" .
        '<code>' . htmlspecialchars(CARD_NUMBER, ENT_QUOTES) . "</code>\n\n" .
        '👤 به نام: ' . CARD_HOLDER . "\n\n" .
        "⚡️ لطفا پس از واریز پول،  «دکمه ارسال رسید» را بزنید و سپس " .
        "رسید پرداخت را بصورت عکس ارسال کنید\n\n" .
        "❗️توجه: چنانچه رسید بصورت متن بود از آن اسکرین شات بگیرید و اسکرین شات را ارسال کنید.\n\n" .
        '❗️در صورت مغایرت مبلغ تعیین شده با مبلغ واریز شده به کارت مسئولیت آن به عهده خودتان است.';
}

function admin_topup_caption($user, int $amount, string $req_id): string {
    return "🧾 درخواست افزایش موجودی جدید\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n" .
        "💸 مبلغ: " . fmt($amount) . " تومان\n" .
        "🔢 شماره درخواست: {$req_id}";
}

function approved_text(int $balance): string { return "🔥 مشتری عزیز\n\n✔️ رسید افزایش موجودی شما با موفقیت تایید شد\nموجودی شما:  " . fmt($balance) . ' تومان'; }
function rejected_text(string $reason): string { return "❌ متاسفانه درخواست افزایش موجودی شما رد شد.\n\nدلیل: {$reason}"; }

function account_text(array &$DATA, $user): string {
    $u = get_user($DATA, $user['id']);
    $name = display_name($user);
    $orders = $u['orders'] ?? [];
    $last5 = array_reverse(array_slice($orders, -5));
    $orders_block = $last5 ? implode("\n", $last5) : 'هنوز سفارشی ثبت نکرده‌اید';
    return "👤 حساب کاربری شما\n\n" .
        "🔴 نام : {$name}\n" .
        "🔴 آیدی عددی : {$user['id']}\n\n\n" .
        "⭐️ امتیاز شما : {$u['score']} امتیاز\n" .
        "⭐️ زیر مجموعه های شما : {$u['referrals']} نفر\n\n" .
        "📊 آخرین 5 سفارش شما:\n" .
        "<blockquote>{$orders_block}</blockquote>\n\n" .
        "💰 موجودی فعلی:  " . fmt($u['balance']) . " تومان\n" .
        "🎁 موجودی تخفیف:  " . fmt($u['discount_balance']) . ' تومان';
}

function referral_text(array &$DATA, $user): string {
    $u = get_user($DATA, $user['id']);
    $link = 'https://t.me/' . BOT_USERNAME . '?start=ref_' . $user['id'];
    return "👥 زیرمجموعه‌گیری ( Referral System )\n\n" .
        "🔗 لینک اختصاصی شما :\n{$link}\n\n" .
        "👥 زیرمجموعه‌ها : {$u['referrals']} نفر\n" .
        "💰 درآمد شما :  " . fmt($u['discount_balance']) . " تومان\n" .
        "🏅 امتیاز رفرال : {$u['score']}\n\n" .
        "❗️ توضیحات سیستم رفرال :\n\n" .
        "• با ارسال لینک اختصاصی خود ، دوستانتان را به ربات دعوت کنید.\n\n" .
        "• هر زمان زیرمجموعه‌ی شما خریدی انجام دهد، " . REFERRAL_COMMISSION_PERCENT . "٪ از مبلغ " .
        "آن خرید به موجودی تخفیف شما اضافه می‌شود.\n\n" .
        "• اگر در تایم‌های اعلام‌شده توسط ادمین زیرمجموعه بگیرید ، به ازای هر دعوت " .
        "1 امتیاز به امتیاز رفرال شما اضافه می‌شود.\n\n" .
        "• تایم‌ها از طریق اطلاع‌رسانی ربات و کانال تلگرام @" . TRUST_CHANNEL . " اعلام می‌شوند\n\n" .
        "• درصورت داشتن هر " . REFERRAL_POINTS_REQUIRED . " امتیاز " .
        "شما قادر به دریافت یک جایزه هستید\n\n" .
        '(جایزه : گیفت تدی یا قلب 15 استارزی به انتخاب خودتان)';
}

function referral_notification_text(string $invited_name, bool $point_awarded, int $total_referrals = 0, int $total_discount = 0, int $total_score = 0): string {
    $text = "🎉 تبریک! یک زیرمجموعه جدید داری!\n\n" .
        "👤 کاربر «{$invited_name}» با لینک دعوت شما عضو ربات شد.\n\n";
    if ($point_awarded) $text .= "🏅 1 امتیاز رفرال به امتیازهای شما اضافه شد.\n";
    $text .= "\n━━━━━━━━━━━━━━━━━\n" .
        "👥 کل زیرمجموعه‌های شما: {$total_referrals} نفر\n" .
        "💸 موجودی تخفیف شما: " . fmt($total_discount) . " تومان\n" .
        "🏅 امتیاز رفرال شما: {$total_score}\n\n" .
        "💡 هر خریدی که زیرمجموعه‌تون انجام بده، " . REFERRAL_COMMISSION_PERCENT . "٪ " .
        'از مبلغ خرید به موجودی تخفیف شما اضافه میشه.';
    return $text;
}

function referral_reward_select_text(): string { return '🎁 یکی از گیفت‌های زیر رو به عنوان جایزه رفرال انتخاب کن:'; }

function referral_reward_invoice_text(string $key, string $username): string {
    return "🎁 فاکتور جایزه رفرال\n\n" .
        "💫 جایزه: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "🏅 امتیاز مصرفی: " . REFERRAL_POINTS_REQUIRED . "\n" .
        "💰 مبلغ قابل پرداخت: رایگان 🎁\n\n" .
        "🍾 در صورتی که جزئیات بالا مورد تأیید شماست \n\n" .
        "روی دکمه «تأیید ✅» کلیک کنید.";
}

function referral_reward_success_text(string $key, string $username, string $order_id): string {
    return "✅ جایزه رفرال شما با موفقیت ثبت شد!\n\n" .
        "🎁 جایزه: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💰 مبلغ پرداختی: رایگان 🎁\n" .
        "🔑 کد پیگیری سفارش : {$order_id}\n\n" .
        "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
        "🙏 از همراهی شما سپاسگزاریم.";
}

function admin_referral_reward_text($user, string $key, string $username, string $order_id): string {
    return "🎁 سفارش جدید - جایزه رفرال (رایگان)\n\n" .
        "👤 کاربر: " . display_name($user) . ' (' . username_at($user) . ")\n" .
        "🆔 آیدی عددی: {$user['id']}\n" .
        "🎁 گیفت: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "🔢 کد پیگیری: {$order_id}";
}

function referral_reward_done_text(string $key, string $username, string $order_id): string {
    return "✅ جایزه رفرال شما با موفقیت انجام شد 🎉\n\n" .
        "☝️اطلاعات سفارش:\n\n" .
        "ℹ️ نوع خدمات: جایزه رفرال\n" .
        "🎁 گیفت: " . gift_label($key) . "\n" .
        "📎 یوزر دریافت‌کننده: {$username}\n" .
        "💳 مبلغ نهایی پرداخت شده: رایگان 🎁\n" .
        "🔓 کد پیگیری سفارش: {$order_id}\n\n" .
        "😊 از همراهی شما سپاسگزاریم.";
}

/* ===================== JALALI DATE (no external deps) ===================== */

function gregorian_to_jalali(int $gy, int $gm, int $gd): array {
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
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

function persian_now_str(): string {
    $now = new DateTime('now', new DateTimeZone(TEHRAN_TZ));
    [$jy, $jm, $jd] = gregorian_to_jalali((int) $now->format('Y'), (int) $now->format('n'), (int) $now->format('j'));
    return sprintf('%04d-%02d-%02d     %s', $jy, $jm, $jd, $now->format('H:i:s'));
}

function today_str(): string {
    $now = new DateTime('now', new DateTimeZone(TEHRAN_TZ));
    return $now->format('Y-m-d');
}

/* ===================== TELEGRAM API ===================== */

function tg_api(string $method, array $params = []): ?array {
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        error_log("tg_api curl error ({$method}): " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $decoded = json_decode($res, true);
    if (!is_array($decoded) || empty($decoded['ok'])) {
        error_log("tg_api failed ({$method}): " . substr((string) $res, 0, 500));
    }
    return is_array($decoded) ? $decoded : null;
}

function send_message($chat_id, string $text, ?array $reply_markup = null, ?string $parse_mode = null, array $extra = []): ?array {
    $params = array_merge(['chat_id' => $chat_id, 'text' => $text], $extra);
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    if ($parse_mode !== null) $params['parse_mode'] = $parse_mode;
    return tg_api('sendMessage', $params);
}

function edit_message_text($chat_id, $message_id, string $text, ?array $reply_markup = null, ?string $parse_mode = null): ?array {
    if ($chat_id === null || $message_id === null) return null;
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text];
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    if ($parse_mode !== null) $params['parse_mode'] = $parse_mode;
    return tg_api('editMessageText', $params);
}

function edit_message_caption($chat_id, $message_id, string $caption, ?array $reply_markup = null): ?array {
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'caption' => $caption];
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    return tg_api('editMessageCaption', $params);
}

function answer_callback_query(string $id, ?string $text = null, bool $show_alert = false): ?array {
    $params = ['callback_query_id' => $id];
    if ($text !== null) $params['text'] = $text;
    if ($show_alert) $params['show_alert'] = true;
    return tg_api('answerCallbackQuery', $params);
}

function send_photo($chat_id, string $file_id, ?string $caption = null, ?array $reply_markup = null, ?string $parse_mode = null): ?array {
    $params = ['chat_id' => $chat_id, 'photo' => $file_id];
    if ($caption !== null) $params['caption'] = $caption;
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    if ($parse_mode !== null) $params['parse_mode'] = $parse_mode;
    return tg_api('sendPhoto', $params);
}

function get_chat_member(string $chat, $user_id): ?array {
    return tg_api('getChatMember', ['chat_id' => $chat, 'user_id' => $user_id]);
}

function is_member($user_id): bool {
    $res = get_chat_member('@' . REQUIRED_CHANNEL, $user_id);
    if (!$res || empty($res['ok'])) {
        error_log("is_member check failed for user {$user_id}, defaulting to true (bot likely not admin in @" . REQUIRED_CHANNEL . ')');
        return true;
    }
    $status = $res['result']['status'] ?? '';
    return in_array($status, ['member', 'administrator', 'creator'], true);
}

/* ===================== KEYBOARDS ===================== */

function ikb(array $rows): array { return ['inline_keyboard' => $rows]; }
function btn(string $text, ?string $cb = null, ?string $url = null): array {
    return $url !== null ? ['text' => $text, 'url' => $url] : ['text' => $text, 'callback_data' => $cb];
}
function rkb(array $rows): array { return ['keyboard' => $rows, 'resize_keyboard' => true]; }
function rbtn(string $text): array { return ['text' => $text]; }

function main_kb(bool $is_admin = false): array {
    $rows = [
        [rbtn('🛒 خرید محصول')],
        [rbtn('👛 شارژ موجودی'), rbtn('🔗 لینک دعوت من')],
        [rbtn('👤 حساب کاربری')],
        [rbtn('📋 پیگیری سفارش'), rbtn('📞 پشتیبانی')],
        [rbtn('🔽 چطور میتوانم اعتماد کنم؟')],
    ];
    if ($is_admin) $rows[] = [rbtn('🛠 پنل ادمین')];
    return rkb($rows);
}

function product_kb(): array {
    return ikb([
        [btn('💎 پرمیوم تلگرام', 'product_premium'), btn('⭐️ استارز تلگرام', 'product_stars')],
        [btn('🎀 گیفت استارز', 'product_gift_stars'), btn('💱 خرید ارز تون', 'product_buy_ton')],
        [btn('🖼 گیفت NFT', 'product_gift_nft')],
        [btn('🔙 بازگشت', 'back_to_welcome')],
    ]);
}

function premium_kb(): array {
    return ikb([
        [btn('3 ماه', 'premium_3'), btn('6 ماه', 'premium_6'), btn('12 ماه', 'premium_12')],
        [btn('🔙 بازگشت', 'back_to_products')],
    ]);
}

function ask_username_kb(): array {
    return ikb([[btn('👤 برای خودم', 'username_self')], [btn('🔙 بازگشت', 'back_to_premium')]]);
}

function confirm_username_kb(): array { return ikb([[btn('✅ بله', 'confirm_yes'), btn('❌ خیر', 'confirm_no')]]); }

function invoice_kb(bool $discount_applied = false): array {
    $label = $discount_applied ? '❌ لغو تخفیف' : '💸 اعمال تخفیف';
    return ikb([
        [btn('تأیید ✅', 'invoice_confirm')],
        [btn($label, 'invoice_discount'), btn('❌ کنسل', 'invoice_cancel')],
    ]);
}

function insufficient_kb(): array {
    return ikb([[btn('💳 کارت به کارت', 'topup_from_invoice')], [btn('🔙 بازگشت به منو', 'back_to_products')]]);
}

function wallet_kb(): array { return ikb([[btn('افزایش موجودی', 'wallet_increase')], [btn('🔙 بازگشت', 'back_to_welcome')]]); }
function wallet_increase_kb(): array { return ikb([[btn('💳 پرداخت تومانی', 'topup_from_wallet')], [btn('🔙 بازگشت', 'wallet_back')]]); }
function toman_kb(): array { return ikb([[btn('🔙 بازگشت', 'topup_back')]]); }
function card_details_kb(): array { return ikb([[btn('🔙 بازگشت', 'card_back'), btn('📨 ارسال رسید', 'send_receipt')]]); }
function receipt_prompt_kb(): array { return ikb([[btn('🔙 بازگشت', 'receipt_back')]]); }
function admin_topup_kb(string $req_id): array { return ikb([[btn('✅ تایید', "topup_approve_{$req_id}"), btn('❌ رد', "topup_reject_{$req_id}")]]); }
function admin_order_kb(string $order_id): array { return ikb([[btn('✅ انجام شد', "order_done_{$order_id}")]]); }
function buy_product_kb(): array { return ikb([[btn('🛒 خرید محصول', 'back_to_products')]]); }
function ton_back_kb(): array { return ikb([[btn('🔙 بازگشت', 'back_to_products')]]); }
function ton_wallet_back_kb(): array { return ikb([[btn('🔙 بازگشت', 'ton_back_to_amount')]]); }

function ton_memo_kb(): array {
    return ikb([
        [btn('✅ بله، کامنت دارم', 'ton_memo_yes')],
        [btn('❌ رد کردن', 'ton_memo_skip')],
        [btn('🔙 بازگشت', 'ton_memo_back')],
    ]);
}
function ton_memo_input_kb(): array { return ikb([[btn('🔙 بازگشت', 'ton_memo_input_back')]]); }

function ton_invoice_kb(bool $discount_applied = false): array {
    $label = $discount_applied ? '❌ لغو تخفیف' : '💸 اعمال تخفیف';
    return ikb([
        [btn('تأیید ✅', 'ton_invoice_confirm')],
        [btn($label, 'ton_invoice_discount'), btn('❌ کنسل', 'ton_invoice_cancel')],
    ]);
}

function stars_back_kb(): array { return ikb([[btn('🔙 بازگشت', 'back_to_products')]]); }
function stars_min_error_kb(): array { return ikb([[btn('🔙 بازگشت', 'back_to_products')]]); }
function ask_stars_username_kb(): array { return ikb([[btn('👤 برای خودم', 'stars_username_self')], [btn('🔙 بازگشت', 'stars_back_to_amount')]]); }
function confirm_stars_username_kb(): array { return ikb([[btn('✅ بله', 'stars_confirm_yes'), btn('❌ خیر', 'stars_confirm_no')]]); }

function stars_invoice_kb(bool $discount_applied = false): array {
    $label = $discount_applied ? '❌ لغو تخفیف' : '💸 اعمال تخفیف';
    return ikb([
        [btn('تأیید ✅', 'stars_invoice_confirm')],
        [btn($label, 'stars_invoice_discount'), btn('❌ کنسل', 'stars_invoice_cancel')],
    ]);
}

function gift_list_kb(): array {
    $rows = [];
    $keys = array_keys(GIFTS_META);
    $i = 0;
    $n = count($keys);
    while ($i < $n) {
        $g = GIFTS_META[$keys[$i]];
        $label_a = "{$g['emoji']} {$g['name']} (⭐️ {$g['stars']})";
        if ($i + 1 < $n) {
            $g2 = GIFTS_META[$keys[$i + 1]];
            $label_b = "{$g2['emoji']} {$g2['name']} (⭐️ {$g2['stars']})";
            $rows[] = [btn($label_a, "gift_select_{$keys[$i]}"), btn($label_b, "gift_select_{$keys[$i + 1]}")];
            $i += 2;
        } else {
            $rows[] = [btn($label_a, "gift_select_{$keys[$i]}")];
            $i += 1;
        }
    }
    $rows[] = [btn('◀️ بازگشت', 'back_to_products')];
    return ikb($rows);
}

function ask_gift_username_kb(): array { return ikb([[btn('👤 برای خودم', 'gift_username_self')], [btn('🔙 بازگشت', 'gift_back_to_list')]]); }
function confirm_gift_username_kb(): array { return ikb([[btn('✅ بله', 'gift_confirm_yes'), btn('❌ خیر', 'gift_confirm_no')]]); }

function gift_invoice_kb(bool $hidden, bool $has_comment, bool $discount_applied = false): array {
    $hide_label = !$hidden ? '🔓 هاید کردن' : '🔒 لغو هاید بودن';
    $comment_label = $has_comment ? '🗑 حذف کامنت' : '💬 تنظیم کامنت';
    $comment_cb = $has_comment ? 'gift_del_comment' : 'gift_comment_type';
    $discount_label = $discount_applied ? '❌ لغو تخفیف' : '💸 اعمال تخفیف';
    return ikb([
        [btn('تأیید ✅', 'gift_invoice_confirm')],
        [btn($discount_label, 'gift_invoice_discount'), btn('❌ کنسل', 'gift_invoice_cancel')],
        [btn($comment_label, $comment_cb), btn($hide_label, 'gift_toggle_hide')],
    ]);
}

function gift_comment_type_kb(): array { return ikb([[btn('✍️ عادی (رایگان)', 'gift_comment_free')], [btn('🔙 بازگشت', 'gift_comment_back_to_invoice')]]); }
function gift_comment_input_kb(): array { return ikb([[btn('🔙 بازگشت', 'gift_comment_input_back')]]); }
function support_kb(): array { return ikb([[btn('☎️ ارتباط مستقیم', null, 'https://t.me/' . SUPPORT_USERNAME), btn('💬 ارتباط غیر مستقیم', 'support_indirect')]]); }
function support_indirect_kb(): array { return ikb([[btn('🔙 بازگشت', 'support_back')]]); }
function admin_support_kb(string $ticket_id): array { return ikb([[btn('✍️ پاسخ دادن', "support_reply_{$ticket_id}")]]); }
function join_kb(): array { return ikb([[btn('📢 عضویت در کانال', null, 'https://t.me/' . REQUIRED_CHANNEL)], [btn('✅ عضو شدم', 'check_membership')]]); }
function report_kb(): array { return ikb([[btn('🛍 حالا اقدام به خرید کن', null, 'https://t.me/' . BOT_USERNAME)]]); }
function track_kb(): array { return ikb([[btn('🔢 کد پیگیری محصول دارم', 'track_have_code')]]); }
function track_ask_code_kb(): array { return ikb([[btn('🔙 بازگشت', 'track_back')]]); }
function invite_kb(): array { return ikb([[btn('🎁 دریافت جایزه', 'referral_claim_reward')]]); }

function referral_reward_select_kb(): array {
    $rows = [];
    foreach (REFERRAL_REWARD_GIFTS as $k) {
        $g = GIFTS_META[$k];
        $rows[] = [btn("{$g['emoji']} گیفت {$g['name']}", "referral_reward_{$k}")];
    }
    $rows[] = [btn('🔙 بازگشت', 'referral_back')];
    return ikb($rows);
}

function ask_referral_username_kb(): array { return ikb([[btn('👤 برای خودم', 'referral_username_self')], [btn('🔙 بازگشت', 'referral_reward_select_back')]]); }
function confirm_referral_username_kb(): array { return ikb([[btn('✅ بله', 'referral_confirm_yes'), btn('❌ خیر', 'referral_confirm_no')]]); }
function referral_reward_invoice_kb(): array { return ikb([[btn('تأیید ✅', 'referral_invoice_confirm')], [btn('❌ کنسل', 'referral_invoice_cancel')]]); }

function admin_panel_kb(array &$DATA): array {
    $toggle_label = $DATA['bot_enabled'] ? '🔴 خاموش کردن ربات' : '🟢 روشن کردن ربات';
    $ref_toggle_label = $DATA['referral_points_enabled'] ? '🔴 غیرفعال‌سازی جایزه دعوت' : '🟢 فعال‌سازی جایزه دعوت';
    return ikb([
        [btn($toggle_label, 'admin_toggle_bot')],
        [btn($ref_toggle_label, 'admin_toggle_referral_points')],
        [btn('📢 ارسال پیام به تمام کاربران', 'admin_broadcast')],
        [btn('👥 دیدن کاربران ربات', 'admin_view_users')],
        [btn('💰 تنظیم قیمت', 'admin_price_menu')],
        [btn('📈 تغییر درصد سود', 'admin_profit_menu')],
        [btn('📊 تنظیم سقف شارژ روزانه', 'admin_daily_limit')],
        [btn('🗑 ریست کامل داده‌های ربات', 'admin_reset_confirm')],
    ]);
}

function admin_reset_confirm_kb(): array { return ikb([[btn('✅ بله، همه چیز پاک بشه', 'admin_reset_yes'), btn('❌ نه، برگرد', 'admin_panel_back')]]); }
function admin_back_kb(): array { return ikb([[btn('🔙 بازگشت', 'admin_panel_back')]]); }
function admin_broadcast_ask_kb(): array { return ikb([[btn('🔙 بازگشت', 'admin_panel_back')]]); }
function admin_broadcast_confirm_kb(): array { return ikb([[btn('❌ نه', 'admin_broadcast_no'), btn('✅ بله', 'admin_broadcast_yes')]]); }

function admin_price_menu_kb(): array {
    return ikb([
        [btn('⭐️ استارز تلگرام', 'admin_price_stars')],
        [btn('💎 پرمیوم تلگرام', 'admin_price_premium')],
        [btn('💱 ارز تون', 'admin_price_ton')],
        [btn('🎀 گیفت استارز', 'admin_price_giftstars')],
        [btn('🖼 گیفت NFT', 'admin_price_giftnft')],
        [btn('🔙 بازگشت', 'admin_panel_back')],
    ]);
}

function admin_stars_price_kb(): array { return ikb([[btn('✏️ تغییر قیمت', 'admin_change_stars_price')], [btn('🔙 بازگشت', 'admin_price_menu_back')]]); }
function admin_stars_price_ask_kb(): array { return ikb([[btn('🔙 بازگشت', 'admin_price_stars_back')]]); }
function admin_ton_price_kb(): array { return ikb([[btn('✏️ تغییر قیمت', 'admin_change_ton_price')], [btn('🔙 بازگشت', 'admin_price_menu_back')]]); }
function admin_ton_price_ask_kb(): array { return ikb([[btn('🔙 بازگشت', 'admin_price_ton_back')]]); }
function admin_premium_price_kb(): array { return ikb([[btn('✏️ تغییر قیمت', 'admin_change_premium_price')], [btn('🔙 بازگشت', 'admin_price_menu_back')]]); }

function admin_premium_plan_select_kb(): array {
    return ikb([
        [btn('3 ماه', 'admin_premium_plan_3'), btn('6 ماه', 'admin_premium_plan_6'), btn('12 ماه', 'admin_premium_plan_12')],
        [btn('🔙 بازگشت', 'admin_price_premium_back')],
    ]);
}
function admin_premium_price_ask_kb(): array { return ikb([[btn('🔙 بازگشت', 'admin_premium_plan_select_back')]]); }

function admin_gift_price_list_kb(): array {
    $rows = [];
    $keys = array_keys(GIFTS_META);
    $i = 0;
    $n = count($keys);
    while ($i < $n) {
        $g = GIFTS_META[$keys[$i]];
        $label_a = "{$g['emoji']} {$g['name']}";
        if ($i + 1 < $n) {
            $g2 = GIFTS_META[$keys[$i + 1]];
            $label_b = "{$g2['emoji']} {$g2['name']}";
            $rows[] = [btn($label_a, "admin_gift_price_{$keys[$i]}"), btn($label_b, "admin_gift_price_{$keys[$i + 1]}")];
            $i += 2;
        } else {
            $rows[] = [btn($label_a, "admin_gift_price_{$keys[$i]}")];
            $i += 1;
        }
    }
    $rows[] = [btn('🔙 بازگشت', 'admin_price_menu_back')];
    return ikb($rows);
}

function admin_gift_price_detail_kb(string $key): array { return ikb([[btn('✏️ تغییر قیمت', "admin_change_gift_price_{$key}")], [btn('🔙 بازگشت', 'admin_gift_price_list_back')]]); }
function admin_gift_price_ask_kb(string $key): array { return ikb([[btn('🔙 بازگشت', "admin_gift_price_detail_back_{$key}")]]); }
function admin_daily_limit_kb(): array { return ikb([[btn('✏️ تنظیم سقف روزانه', 'admin_change_daily_limit')], [btn('🔙 بازگشت', 'admin_panel_back')]]); }
function admin_daily_limit_ask_kb(): array { return ikb([[btn('🔙 بازگشت', 'admin_daily_limit_back')]]); }

function admin_profit_menu_kb(): array {
    return ikb([
        [btn('⭐️ استارز تلگرام', 'admin_profit_stars')],
        [btn('💱 ارز تون', 'admin_profit_ton')],
        [btn('🎀 گیفت استارز', 'admin_profit_gift')],
        [btn('💎 پرمیوم تلگرام', 'admin_profit_premium')],
        [btn('🔙 بازگشت', 'admin_panel_back')],
    ]);
}
function admin_profit_detail_kb(string $product): array { return ikb([[btn('✏️ تنظیم سود', "admin_set_profit_{$product}")], [btn('🔙 بازگشت', 'admin_profit_menu_back')]]); }
function admin_profit_ask_kb(string $product): array { return ikb([[btn('🔙 بازگشت', "admin_profit_{$product}")]]); }

/* ===================== DATA STORE ===================== */

function default_data(): array {
    $data = [
        'users' => [],
        'user_names' => [],
        'orders' => [],
        'topup_requests' => [],
        'pending_referrals' => [],
        'support_tickets' => [],
        'admin_waiting_reject' => [],
        'admin_waiting_support_reply' => [],
        'user_state' => [],
        'bot_enabled' => true,
        'referral_points_enabled' => false,
        'daily_limit' => 900000,
        'profit_percent' => ['ton' => 10, 'stars' => 8, 'gift' => 21, 'premium' => 11],
        'base_ton_price' => 298225,
        'base_stars_price' => 2637,
        'base_premium_prices' => ['3' => 1577000, '6' => 2560000, '12' => 4460000],
        'premium_prices' => ['3' => 1750000, '6' => 2841000, '12' => 4950000],
        'stars_price' => 2847,
        'gift_star_price' => 3514,
        'ton_price' => 298225,
        'gifts_prices' => [],
        'req_counter' => 0,
    ];
    recalc_prices($data);
    return $data;
}

function recalc_prices(array &$DATA): void {
    $pp = $DATA['profit_percent'];
    $DATA['stars_price'] = (int) round($DATA['base_stars_price'] * (1 + $pp['stars'] / 100));
    $DATA['gift_star_price'] = (int) round($DATA['base_stars_price'] * (1 + $pp['gift'] / 100));
    $DATA['ton_price'] = (int) round($DATA['base_ton_price'] * (1 + $pp['ton'] / 100));
    foreach ($DATA['base_premium_prices'] as $plan => $base) {
        $DATA['premium_prices'][$plan] = (int) round($base * (1 + $pp['premium'] / 100));
    }
    foreach (GIFTS_META as $key => $g) {
        $DATA['gifts_prices'][$key] = (int) round($g['stars'] * $DATA['gift_star_price']);
    }
}

function load_data(): array {
    if (!file_exists(DATA_FILE)) return default_data();
    $raw = @file_get_contents(DATA_FILE);
    if ($raw === false || $raw === '') return default_data();
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return default_data();
    $defaults = default_data();
    $data = array_replace($defaults, $decoded);
    foreach (['users', 'user_names', 'orders', 'topup_requests', 'pending_referrals', 'support_tickets',
              'admin_waiting_reject', 'admin_waiting_support_reply', 'user_state', 'gifts_prices',
              'premium_prices', 'base_premium_prices', 'profit_percent'] as $k) {
        if (!is_array($data[$k] ?? null)) $data[$k] = $defaults[$k];
    }
    recalc_prices($data);
    if (isset($decoded['gifts_prices']) && is_array($decoded['gifts_prices'])) {
        foreach ($decoded['gifts_prices'] as $key => $price) {
            if (isset(GIFTS_META[$key])) $data['gifts_prices'][$key] = $price;
        }
    }
    if (isset($decoded['stars_price'])) $data['stars_price'] = $decoded['stars_price'];
    if (isset($decoded['ton_price'])) $data['ton_price'] = $decoded['ton_price'];
    if (isset($decoded['premium_prices'])) $data['premium_prices'] = array_replace($data['premium_prices'], $decoded['premium_prices']);
    return $data;
}

function save_data(array $DATA): void {
    $tmp = DATA_FILE . '.tmp.' . getmypid();
    $ok = @file_put_contents($tmp, json_encode($DATA, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    if ($ok === false) { error_log('save_data: failed to write tmp file'); return; }
    if (!@rename($tmp, DATA_FILE)) { error_log('save_data: rename failed'); @unlink($tmp); }
}

/* ===================== DOMAIN HELPERS ===================== */

function &get_user(array &$DATA, $uid): array {
    if (!isset($DATA['users'][$uid])) {
        $DATA['users'][$uid] = [
            'balance' => 0, 'discount_balance' => 0, 'score' => 0, 'referrals' => 0,
            'referrer_id' => null, 'orders' => [], 'daily_topup_used' => 0,
            'daily_topup_date' => today_str(),
        ];
    }
    return $DATA['users'][$uid];
}

function &get_ustate(array &$DATA, $uid): array {
    if (!isset($DATA['user_state'][$uid])) $DATA['user_state'][$uid] = [];
    return $DATA['user_state'][$uid];
}

function display_name(array $user): string {
    $full = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    if ($full !== '') return $full;
    if (!empty($user['username'])) return '@' . $user['username'];
    return 'بدون نام';
}

function username_at(array $user): string { return !empty($user['username']) ? '@' . $user['username'] : '---'; }

function get_daily_remaining(array &$DATA, $uid): int {
    $u = &get_user($DATA, $uid);
    if (($u['daily_topup_date'] ?? null) !== today_str()) {
        $u['daily_topup_date'] = today_str();
        $u['daily_topup_used'] = 0;
    }
    return max($DATA['daily_limit'] - $u['daily_topup_used'], 0);
}

function use_daily_limit(array &$DATA, $uid, int $amount): void {
    get_daily_remaining($DATA, $uid);
    $DATA['users'][$uid]['daily_topup_used'] += $amount;
}

function refund_daily_limit(array &$DATA, $uid, int $amount): void {
    $u = &get_user($DATA, $uid);
    if (($u['daily_topup_date'] ?? null) === today_str()) {
        $u['daily_topup_used'] = max($u['daily_topup_used'] - $amount, 0);
    }
}

function apply_referral_commission(array &$DATA, $buyer_id, $price): void {
    if ($price <= 0) return;
    $buyer = &get_user($DATA, $buyer_id);
    $referrer_id = $buyer['referrer_id'] ?? null;
    if (!$referrer_id) return;
    $commission = (int) round($price * REFERRAL_COMMISSION_PERCENT / 100);
    if ($commission <= 0) return;
    $referrer = &get_user($DATA, $referrer_id);
    $referrer['discount_balance'] += $commission;
}

function compute_final_price(array &$DATA, array $order, $user_id): int {
    $price = $order['price'] ?? 0;
    if (!empty($order['discount_applied'])) {
        $disc_balance = get_user($DATA, $user_id)['discount_balance'];
        return max($price - min($disc_balance, $price), 0);
    }
    return (int) $price;
}

function consume_discount_if_applied(array &$DATA, array $order, $user_id): void {
    if (!empty($order['discount_applied'])) {
        $price = $order['price'] ?? 0;
        $u = &get_user($DATA, $user_id);
        $used = min($u['discount_balance'], $price);
        $u['discount_balance'] -= $used;
    }
}

function new_id(): string { return (string) random_int(1000000, 9999999); }

function new_req_id(array &$DATA): string {
    $DATA['req_counter'] = ($DATA['req_counter'] ?? 0) + 1;
    return (string) $DATA['req_counter'];
}

function push_order_history(array &$DATA, $uid, string $line): void {
    $u = &get_user($DATA, $uid);
    $u['orders'][] = $line;
    $u['orders'] = array_slice($u['orders'], -50);
}

function register_user_and_referral(array &$DATA, $uid): void {
    get_user($DATA, $uid);
    $ref_id = $DATA['pending_referrals'][$uid] ?? null;
    if (array_key_exists($uid, $DATA['pending_referrals'])) unset($DATA['pending_referrals'][$uid]);
    if ($ref_id && $ref_id != $uid) {
        $u = &get_user($DATA, $uid);
        if (empty($u['referrer_id'])) {
            $u['referrer_id'] = $ref_id;
            $referrer = &get_user($DATA, $ref_id);
            $referrer['referrals'] = ($referrer['referrals'] ?? 0) + 1;
            $point_awarded = false;
            if ($DATA['referral_points_enabled']) {
                $referrer['score'] = ($referrer['score'] ?? 0) + 1;
                $point_awarded = true;
            }
            $invited_name = $DATA['user_names'][$uid] ?? 'یک کاربر جدید';
            send_message($ref_id, referral_notification_text($invited_name, $point_awarded, $referrer['referrals'], $referrer['discount_balance'], $referrer['score']));
        }
    }
}

/* ===================== UPDATE HANDLERS ===================== */

function handle_start(array $msg, array &$DATA): void {
    $user = $msg['from'];
    $uid = $user['id'];
    $chat_id = $msg['chat']['id'];
    $DATA['user_state'][$uid] = [];

    $textParts = preg_split('/\s+/', trim($msg['text'] ?? ''), 2);
    if (isset($textParts[1]) && $textParts[1] !== '') {
        $payload = $textParts[1];
        if (str_starts_with($payload, 'ref_')) {
            $ref_id_raw = substr($payload, 4);
            if (ctype_digit($ref_id_raw)) {
                $ref_id = (int) $ref_id_raw;
                if ($ref_id !== $uid) $DATA['pending_referrals'][$uid] = $ref_id;
            }
        }
    }

    if ($chat_id != ADMIN_CHAT_ID) {
        if (!$DATA['bot_enabled']) { send_message($chat_id, BOT_OFF_TEXT); return; }
        if (!is_member($uid)) { send_message($chat_id, join_text(), join_kb()); return; }
    }
    $DATA['user_names'][$uid] = display_name($user);
    register_user_and_referral($DATA, $uid);
    send_message($chat_id, WELCOME_TEXT, main_kb($chat_id == ADMIN_CHAT_ID));
}

function handle_photo(array $msg, array &$DATA): void {
    $user = $msg['from'];
    $uid = $user['id'];
    $chat_id = $msg['chat']['id'];
    $ust = &get_ustate($DATA, $uid);
    if (($ust['state'] ?? null) !== 'awaiting_receipt_photo') return;

    $pending = $ust['pending_topup'] ?? null;
    if (!$pending) {
        send_message($chat_id, 'درخواست شارژ پیدا نشد، لطفا دوباره شروع کن.');
        $ust['state'] = null;
        return;
    }
    $amount = (int) $pending['amount'];
    $req_id = new_req_id($DATA);
    $photos = $msg['photo'] ?? [];
    $largest = end($photos);
    if ($largest === false) return;
    $file_id = $largest['file_id'];

    $DATA['topup_requests'][$req_id] = ['user_id' => $uid, 'chat_id' => $chat_id, 'amount' => $amount];
    if (ADMIN_CHAT_ID) {
        send_photo(ADMIN_CHAT_ID, $file_id, admin_topup_caption($user, $amount, $req_id), admin_topup_kb($req_id));
    }
    $ust['state'] = null;
    $ust['pending_topup'] = null;
    send_message($chat_id, RECEIPT_SENT_TEXT);
}

function handle_text(array $msg, array &$DATA): void {
    $text = $msg['text'] ?? '';
    $user = $msg['from'];
    $uid = $user['id'];
    $chat_id = $msg['chat']['id'];
    $ust = &get_ustate($DATA, $uid);
    $state = $ust['state'] ?? null;

    if ($chat_id == ADMIN_CHAT_ID && isset($DATA['admin_waiting_reject'][$chat_id])) {
        $req_id = $DATA['admin_waiting_reject'][$chat_id];
        unset($DATA['admin_waiting_reject'][$chat_id]);
        $req = $DATA['topup_requests'][$req_id] ?? null;
        if ($req) {
            unset($DATA['topup_requests'][$req_id]);
            refund_daily_limit($DATA, $req['user_id'], (int) $req['amount']);
            send_message($req['chat_id'], rejected_text($text));
        }
        send_message($chat_id, 'دلیل رد برای کاربر ارسال شد.');
        return;
    }

    if ($chat_id == ADMIN_CHAT_ID && isset($DATA['admin_waiting_support_reply'][$chat_id])) {
        $ticket_id = $DATA['admin_waiting_support_reply'][$chat_id];
        unset($DATA['admin_waiting_support_reply'][$chat_id]);
        $ticket = $DATA['support_tickets'][$ticket_id] ?? null;
        if ($ticket) {
            unset($DATA['support_tickets'][$ticket_id]);
            send_message($ticket['chat_id'], "📩 پاسخ پشتیبانی:\n\n{$text}", null, null, ['reply_to_message_id' => $ticket['message_id']]);
        }
        send_message($chat_id, 'پاسخ شما برای کاربر ارسال شد.');
        return;
    }

    if ($chat_id != ADMIN_CHAT_ID && !$DATA['bot_enabled']) { send_message($chat_id, BOT_OFF_TEXT); return; }
    if ($chat_id != ADMIN_CHAT_ID && !is_member($uid)) {
        $DATA['user_state'][$uid] = [];
        send_message($chat_id, join_text(), join_kb());
        return;
    }

    $DATA['user_names'][$uid] = display_name($user);
    register_user_and_referral($DATA, $uid);
    $ust = &get_ustate($DATA, $uid);

    if ($chat_id == ADMIN_CHAT_ID) {
        if ($state === 'admin_awaiting_broadcast_text') {
            $ust['broadcast_text'] = $text;
            $ust['state'] = null;
            send_message($chat_id, broadcast_confirm_text($text), admin_broadcast_confirm_kb());
            return;
        }
        if ($state === 'admin_awaiting_stars_price') {
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد وارد کن.', admin_stars_price_ask_kb()); return; }
            $DATA['stars_price'] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_stars_price_text($DATA), admin_stars_price_kb());
            return;
        }
        if ($state === 'admin_awaiting_ton_price') {
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد وارد کن.', admin_ton_price_ask_kb()); return; }
            $DATA['ton_price'] = (int) round(((int) $raw) / MIN_TON);
            $ust['state'] = null;
            send_message($chat_id, admin_ton_price_text($DATA), admin_ton_price_kb());
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_premium_price_')) {
            $plan = substr($state, strlen('admin_awaiting_premium_price_'));
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد وارد کن.', admin_premium_price_ask_kb()); return; }
            $DATA['premium_prices'][$plan] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_premium_price_text($DATA), admin_premium_price_kb());
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_gift_price_')) {
            $key = substr($state, strlen('admin_awaiting_gift_price_'));
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد وارد کن.', admin_gift_price_ask_kb($key)); return; }
            if (isset(GIFTS_META[$key])) $DATA['gifts_prices'][$key] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_gift_price_detail_text($DATA, $key), admin_gift_price_detail_kb($key));
            return;
        }
        if ($state === 'admin_awaiting_daily_limit') {
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw) || (int) $raw <= 0) { send_message($chat_id, 'لطفا فقط یک عدد مثبت وارد کن.', admin_daily_limit_ask_kb()); return; }
            $DATA['daily_limit'] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_daily_limit_text($DATA), admin_daily_limit_kb());
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_profit_')) {
            $product = substr($state, strlen('admin_awaiting_profit_'));
            $raw = trim(str_replace([',', '%'], '', $text));
            if (!preg_match('/^\d+(\.\d+)?$/', $raw) || (float) $raw < 0) {
                send_message($chat_id, 'لطفا فقط یک عدد مثبت وارد کن (مثال: 10).', admin_profit_ask_kb($product));
                return;
            }
            $DATA['profit_percent'][$product] = round((float) $raw, 2);
            recalc_prices($DATA);
            $ust['state'] = null;
            $textFns = ['stars' => 'admin_profit_stars_text', 'ton' => 'admin_profit_ton_text', 'gift' => 'admin_profit_gift_text', 'premium' => 'admin_profit_premium_text'];
            $fn = $textFns[$product] ?? 'admin_profit_stars_text';
            send_message($chat_id, $fn($DATA), admin_profit_detail_kb($product));
            return;
        }
    }

    if ($state === 'awaiting_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_premium'] = $ust['pending_premium'] ?? [];
        $ust['pending_premium']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_username_kb());
        return;
    }

    if ($state === 'awaiting_topup_amount') {
        $raw = str_replace([',', ' '], '', $text);
        if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد مبلغ رو بفرست. مثال: 40000'); return; }
        $amount = (int) $raw;
        $remaining = get_daily_remaining($DATA, $uid);
        if ($amount > $remaining) { send_message($chat_id, daily_limit_exceeded_text($remaining), toman_kb()); return; }
        use_daily_limit($DATA, $uid, $amount);
        $ust['pending_topup'] = ['amount' => $amount];
        $ust['state'] = null;
        send_message($chat_id, card_details_text($amount), card_details_kb(), 'HTML');
        return;
    }

    if ($state === 'awaiting_receipt_photo') {
        send_message($chat_id, 'لطفا رسید پرداخت رو به صورت عکس ارسال کن 📷');
        return;
    }

    if ($state === 'awaiting_stars_amount') {
        $raw = str_replace(',', '', trim($text));
        if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد وارد کن. مثال: 100', stars_min_error_kb()); return; }
        $count = (int) $raw;
        if ($count < STARS_MIN) { send_message($chat_id, stars_min_error_text(), stars_min_error_kb()); return; }
        $ust['pending_stars'] = ['count' => $count];
        $ust['state'] = 'awaiting_stars_username';
        send_message($chat_id, ASK_USERNAME_TEXT, ask_stars_username_kb());
        return;
    }

    if ($state === 'awaiting_stars_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_stars'] = $ust['pending_stars'] ?? [];
        $ust['pending_stars']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_stars_username_kb());
        return;
    }

    if ($state === 'awaiting_gift_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_gift'] = $ust['pending_gift'] ?? [];
        $ust['pending_gift']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_gift_username_kb());
        return;
    }

    if ($state === 'awaiting_gift_comment') {
        $comment = trim($text);
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $g['comment'] = $comment;
        $ust['state'] = null;
        $key = $g['key'];
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        send_message($chat_id, gift_invoice_text($key, $g['username'], $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], true, $g['discount_applied'] ?? false));
        return;
    }

    if ($state === 'awaiting_referral_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_referral_reward'] = $ust['pending_referral_reward'] ?? [];
        $ust['pending_referral_reward']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_referral_username_kb());
        return;
    }

    if ($state === 'awaiting_ton_amount') {
        $raw = str_replace(',', '', trim($text));
        if (!is_numeric($raw)) { send_message($chat_id, 'لطفا عدد وارد کن. مثال: 0.5 یا 2'); return; }
        $amount = (float) $raw;
        if ($amount < MIN_TON) { send_message($chat_id, 'حداقل سفارش ' . MIN_TON . ' TON است.'); return; }
        $ust['pending_ton'] = ['amount' => $amount];
        $ust['state'] = 'awaiting_ton_wallet';
        send_message($chat_id, TON_WALLET_TEXT, ton_wallet_back_kb());
        return;
    }

    if ($state === 'awaiting_ton_wallet') {
        $wallet = trim($text);
        if (!(str_starts_with($wallet, 'UQ') || str_starts_with($wallet, 'EQ')) || mb_strlen($wallet) < 40) {
            send_message($chat_id, "آدرس ولت نامعتبر به نظر می‌رسد.\nآدرس باید با UQ یا EQ شروع شود و کامل باشد.");
            return;
        }
        $ust['pending_ton']['wallet'] = $wallet;
        $ust['state'] = null;
        send_message($chat_id, TON_MEMO_QUESTION_TEXT, ton_memo_kb());
        return;
    }

    if ($state === 'awaiting_ton_memo') {
        $memo = trim($text);
        $ton = &$ust['pending_ton'];
        $ton['memo'] = $memo;
        $ton['price'] = (int) ($ton['amount'] * $DATA['ton_price']);
        $ton['discount_applied'] = false;
        $ust['state'] = null;
        $price = $ton['price'];
        $disc = get_user($DATA, $uid)['discount_balance'];
        send_message($chat_id, ton_invoice_text($ton['amount'], $ton['wallet'], $memo, $price, $disc, $price), ton_invoice_kb(false));
        return;
    }

    if ($state === 'awaiting_support_message') {
        $ticket_id = new_req_id($DATA);
        $DATA['support_tickets'][$ticket_id] = ['user_id' => $uid, 'chat_id' => $chat_id, 'message_id' => $msg['message_id']];
        send_message($chat_id, SUPPORT_SENT_CONFIRM_TEXT, main_kb($chat_id == ADMIN_CHAT_ID));
        $ust['state'] = null;
        if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_support_text($user, $text), admin_support_kb($ticket_id));
        return;
    }

    if ($state === 'awaiting_tracking_code') {
        $code = trim($text);
        $order = $DATA['orders'][$code] ?? null;
        if (!$order) { send_message($chat_id, TRACK_NOT_FOUND_TEXT, track_ask_code_kb()); return; }
        $ust['state'] = null;
        send_message($chat_id, order_status_text($order));
        return;
    }

    if ($text === '🛒 خرید محصول') { send_message($chat_id, PRODUCT_TEXT, product_kb()); return; }

    if ($text === '👛 شارژ موجودی') {
        $balance = get_user($DATA, $uid)['balance'];
        $remaining = get_daily_remaining($DATA, $uid);
        send_message($chat_id, wallet_text($balance, $remaining), wallet_kb());
        return;
    }

    if ($text === '👤 حساب کاربری') { send_message($chat_id, account_text($DATA, $user), null, 'HTML'); return; }
    if ($text === '🔗 لینک دعوت من') { send_message($chat_id, referral_text($DATA, $user), invite_kb()); return; }
    if ($text === '📞 پشتیبانی') { send_message($chat_id, SUPPORT_TEXT, support_kb()); return; }
    if ($text === '📋 پیگیری سفارش') { send_message($chat_id, TRACK_TEXT, track_kb()); return; }
    if ($text === '🔽 چطور میتوانم اعتماد کنم؟') { send_message($chat_id, trust_text()); return; }
    if ($text === '🛠 پنل ادمین' && $chat_id == ADMIN_CHAT_ID) { send_message($chat_id, admin_panel_text($DATA), admin_panel_kb($DATA)); return; }

    send_message($chat_id, 'این بخش هنوز تکمیل نشده.');
}

function handle_callback(array $cq, array &$DATA): void {
    $data = $cq['data'] ?? '';
    $user = $cq['from'];
    $uid = $user['id'];
    $message = $cq['message'] ?? null;
    $chat_id = $message['chat']['id'] ?? $uid;
    $message_id = $message['message_id'] ?? null;

    answer_callback_query($cq['id']);

    if ($data === 'check_membership') {
        if (is_member($uid)) {
            $DATA['user_names'][$uid] = display_name($user);
            register_user_and_referral($DATA, $uid);
            edit_message_text($chat_id, $message_id, JOIN_CONFIRMED_TEXT);
            send_message($chat_id, WELCOME_TEXT, main_kb($chat_id == ADMIN_CHAT_ID));
        } else {
            answer_callback_query($cq['id'], 'هنوز در کانال عضو نشدید! لطفا ابتدا عضو شوید سپس دوباره امتحان کنید.', true);
        }
        return;
    }

    if ($chat_id != ADMIN_CHAT_ID) {
        if (!$DATA['bot_enabled']) { answer_callback_query($cq['id'], BOT_OFF_TEXT, true); return; }
        if (!is_member($uid)) {
            $DATA['user_state'][$uid] = [];
            edit_message_text($chat_id, $message_id, join_text(), join_kb());
            return;
        }
    }

    $DATA['user_names'][$uid] = display_name($user);
    register_user_and_referral($DATA, $uid);
    $ust = &get_ustate($DATA, $uid);

    if (str_starts_with($data, 'admin_') && $chat_id != ADMIN_CHAT_ID) return;

    if ($data === 'back_to_welcome') { $DATA['user_state'][$uid] = []; edit_message_text($chat_id, $message_id, WELCOME_TEXT); return; }
    if ($data === 'back_to_products') { $ust['state'] = null; edit_message_text($chat_id, $message_id, PRODUCT_TEXT, product_kb()); return; }

    /* ---- gift ---- */
    if ($data === 'product_gift_stars') { edit_message_text($chat_id, $message_id, GIFT_LIST_TEXT, gift_list_kb()); return; }
    if ($data === 'gift_back_to_list') { $ust['state'] = null; edit_message_text($chat_id, $message_id, GIFT_LIST_TEXT, gift_list_kb()); return; }

    if (str_starts_with($data, 'gift_select_')) {
        $key = substr($data, strlen('gift_select_'));
        if (!isset(GIFTS_META[$key])) return;
        $ust['pending_gift'] = ['key' => $key, 'username' => null, 'hidden' => true, 'comment' => null];
        $ust['state'] = 'awaiting_gift_username';
        edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_gift_username_kb());
        return;
    }

    if ($data === 'gift_username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_gift'] = $ust['pending_gift'] ?? [];
            $ust['pending_gift']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_gift_username_kb());
        } else {
            $ust['state'] = 'awaiting_gift_username';
            edit_message_text($chat_id, $message_id, "شما در تلگرام یوزرنیم ندارید.\nلطفا یوزرنیم شخص مورد نظر را با @ ارسال کنید.", ikb([[btn('🔙 بازگشت', 'gift_back_to_list')]]));
        }
        return;
    }

    if ($data === 'gift_confirm_no') { $ust['state'] = 'awaiting_gift_username'; edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_gift_username_kb()); return; }

    if ($data === 'gift_confirm_yes') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $key = $g['key'] ?? '';
        $username = $g['username'] ?? '-';
        $g['price'] = $DATA['gifts_prices'][$key] ?? 0;
        $g['discount_applied'] = false;
        $price = $g['price'];
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, true, null, $price, $disc, $price), gift_invoice_kb(true, false, false));
        return;
    }

    if ($data === 'gift_toggle_hide') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $g['hidden'] = !($g['hidden'] ?? true);
        $key = $g['key'];
        $username = $g['username'];
        $comment = $g['comment'] ?? null;
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], (bool) $comment, $g['discount_applied'] ?? false));
        return;
    }

    if ($data === 'gift_comment_type') { edit_message_text($chat_id, $message_id, GIFT_COMMENT_TYPE_TEXT, gift_comment_type_kb()); return; }

    if ($data === 'gift_comment_back_to_invoice') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $key = $g['key'];
        $username = $g['username'];
        $comment = $g['comment'] ?? null;
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], (bool) $comment, $g['discount_applied'] ?? false));
        return;
    }

    if ($data === 'gift_comment_free') { $ust['state'] = 'awaiting_gift_comment'; edit_message_text($chat_id, $message_id, GIFT_COMMENT_INPUT_TEXT, gift_comment_input_kb()); return; }
    if ($data === 'gift_comment_input_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, GIFT_COMMENT_TYPE_TEXT, gift_comment_type_kb()); return; }

    if ($data === 'gift_del_comment') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $g['comment'] = null;
        $key = $g['key'];
        $username = $g['username'];
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], null, $price, $disc, $final), gift_invoice_kb($g['hidden'], false, $g['discount_applied'] ?? false));
        return;
    }

    if ($data === 'gift_invoice_discount') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $g['discount_applied'] = empty($g['discount_applied']);
        $key = $g['key'];
        $username = $g['username'];
        $comment = $g['comment'] ?? null;
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], (bool) $comment, $g['discount_applied']));
        return;
    }

    if ($data === 'gift_invoice_cancel') { $ust['pending_gift'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, CANCELLED_TEXT); return; }

    if ($data === 'gift_invoice_confirm') {
        $g = $ust['pending_gift'] ?? [];
        $key = $g['key'] ?? '';
        $username = $g['username'] ?? '-';
        $hidden = $g['hidden'] ?? true;
        $comment = $g['comment'] ?? null;
        $price = $g['price'] ?? ($DATA['gifts_prices'][$key] ?? 0);
        $final = compute_final_price($DATA, $g, $uid);
        $u = &get_user($DATA, $uid);
        if ($u['balance'] >= $final) {
            $u['balance'] -= $final;
            consume_discount_if_applied($DATA, $g, $uid);
            apply_referral_commission($DATA, $uid, $final);
            $order_id = new_id();
            push_order_history($DATA, $uid, '🎁 خرید ' . gift_label($key) . ' - به مبلغ ' . fmt($final) . ' تومان');
            $ust['pending_gift'] = null;
            $DATA['orders'][$order_id] = [
                'type' => 'gift', 'chat_id' => $chat_id, 'user_id' => $uid, 'buyer_name' => display_name($user),
                'key' => $key, 'username' => $username, 'price' => $final, 'status' => 'pending', 'created_at' => persian_now_str(),
            ];
            edit_message_text($chat_id, $message_id, gift_success_text($key, $username, $final, $order_id));
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_gift_text($user, $key, $username, $hidden, $comment, $final, $order_id), admin_order_kb($order_id), 'HTML');
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb());
        }
        return;
    }

    /* ---- premium ---- */
    if ($data === 'product_premium') { edit_message_text($chat_id, $message_id, PREMIUM_TEXT, premium_kb()); return; }

    if (in_array($data, ['premium_3', 'premium_6', 'premium_12'], true)) {
        $plan = explode('_', $data)[1];
        $ust['pending_premium'] = ['plan' => $plan, 'price' => $DATA['premium_prices'][$plan]];
        $ust['state'] = 'awaiting_username';
        edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_username_kb());
        return;
    }

    if ($data === 'back_to_premium') { $ust['state'] = null; edit_message_text($chat_id, $message_id, PREMIUM_TEXT, premium_kb()); return; }

    if ($data === 'username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_premium'] = $ust['pending_premium'] ?? [];
            $ust['pending_premium']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_username_kb());
        } else {
            $ust['state'] = 'awaiting_username';
            edit_message_text($chat_id, $message_id, "شما در تلگرام یوزرنیم ندارید.\nلطفا یوزرنیم شخص مورد نظر را با @ ارسال کنید.", ikb([[btn('🔙 بازگشت', 'back_to_ask_username')]]));
        }
        return;
    }

    if ($data === 'back_to_ask_username') { $ust['state'] = 'awaiting_username'; edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_username_kb()); return; }
    if ($data === 'confirm_no') { $ust['state'] = 'awaiting_username'; edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_username_kb()); return; }

    if ($data === 'confirm_yes') {
        $order = &$ust['pending_premium'];
        $order = $order ?? [];
        $order['discount_applied'] = false;
        $plan = $order['plan'] ?? '?';
        $price = $order['price'] ?? 0;
        $username = $order['username'] ?? '-';
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, premium_invoice_text($plan, $price, $username, $disc, $price), invoice_kb(false));
        return;
    }

    if ($data === 'invoice_discount') {
        $order = &$ust['pending_premium'];
        $order = $order ?? [];
        $order['discount_applied'] = empty($order['discount_applied']);
        $plan = $order['plan'] ?? '?';
        $price = $order['price'] ?? 0;
        $username = $order['username'] ?? '-';
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $order, $uid);
        edit_message_text($chat_id, $message_id, premium_invoice_text($plan, $price, $username, $disc, $final), invoice_kb($order['discount_applied']));
        return;
    }

    if ($data === 'invoice_cancel') { $ust['pending_premium'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, CANCELLED_TEXT); return; }

    if ($data === 'invoice_confirm') {
        $order = $ust['pending_premium'] ?? [];
        $plan = $order['plan'] ?? '?';
        $username = $order['username'] ?? '-';
        $final = compute_final_price($DATA, $order, $uid);
        $u = &get_user($DATA, $uid);
        if ($u['balance'] >= $final) {
            $u['balance'] -= $final;
            consume_discount_if_applied($DATA, $order, $uid);
            apply_referral_commission($DATA, $uid, $final);
            $order_id = new_id();
            push_order_history($DATA, $uid, "💎 پرمیوم {$plan} ماهه - به مبلغ " . fmt($final) . ' تومان');
            $ust['pending_premium'] = null;
            $DATA['orders'][$order_id] = [
                'type' => 'premium', 'chat_id' => $chat_id, 'user_id' => $uid, 'buyer_name' => display_name($user),
                'plan' => $plan, 'username' => $username, 'price' => $final, 'status' => 'pending', 'created_at' => persian_now_str(),
            ];
            edit_message_text($chat_id, $message_id, premium_success_text($plan, $username, $final, $order_id));
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_premium_text($user, $plan, $username, $final, $order_id), admin_order_kb($order_id));
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb());
        }
        return;
    }

    /* ---- wallet / top-up ---- */
    if ($data === 'wallet_increase') { edit_message_text($chat_id, $message_id, WALLET_INCREASE_TEXT, wallet_increase_kb()); return; }

    if ($data === 'wallet_back') {
        $balance = get_user($DATA, $uid)['balance'];
        $remaining = get_daily_remaining($DATA, $uid);
        edit_message_text($chat_id, $message_id, wallet_text($balance, $remaining), wallet_kb());
        return;
    }

    if ($data === 'topup_from_invoice' || $data === 'topup_from_wallet') {
        $ust['topup_origin'] = $data === 'topup_from_invoice' ? 'invoice' : 'wallet';
        $ust['state'] = 'awaiting_topup_amount';
        $remaining = get_daily_remaining($DATA, $uid);
        edit_message_text($chat_id, $message_id, toman_payment_text($remaining), toman_kb());
        return;
    }

    if ($data === 'topup_back') {
        $ust['state'] = null;
        $origin = $ust['topup_origin'] ?? null;
        if ($origin === 'invoice') {
            $price = $ust['last_invoice_shortfall_price'] ?? 0;
            $shortfall = max($price - get_user($DATA, $uid)['balance'], 0);
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb());
        } else {
            edit_message_text($chat_id, $message_id, WALLET_INCREASE_TEXT, wallet_increase_kb());
        }
        return;
    }

    if ($data === 'card_back') {
        $ust['state'] = 'awaiting_topup_amount';
        $remaining = get_daily_remaining($DATA, $uid);
        edit_message_text($chat_id, $message_id, toman_payment_text($remaining), toman_kb());
        return;
    }

    if ($data === 'send_receipt') { $ust['state'] = 'awaiting_receipt_photo'; edit_message_text($chat_id, $message_id, RECEIPT_PROMPT_TEXT, receipt_prompt_kb()); return; }

    if ($data === 'receipt_back') {
        $pending = $ust['pending_topup'] ?? [];
        $amount = $pending['amount'] ?? 0;
        $ust['state'] = null;
        edit_message_text($chat_id, $message_id, card_details_text($amount), card_details_kb(), 'HTML');
        return;
    }

    if (str_starts_with($data, 'topup_approve_') || str_starts_with($data, 'topup_reject_')) {
        $req_id = explode('_', $data, 3)[2];
        $req = $DATA['topup_requests'][$req_id] ?? null;
        if (!$req) { edit_message_caption($chat_id, $message_id, 'این درخواست قبلا پردازش شده.'); return; }
        if (str_starts_with($data, 'topup_approve_')) {
            $u = &get_user($DATA, $req['user_id']);
            $u['balance'] += $req['amount'];
            unset($DATA['topup_requests'][$req_id]);
            $old_cap = $message['caption'] ?? '';
            edit_message_caption($chat_id, $message_id, $old_cap . "\n\n✅ تایید شد");
            send_message($req['chat_id'], approved_text($u['balance']), buy_product_kb());
        } else {
            $DATA['admin_waiting_reject'][$chat_id] = $req_id;
            $old_cap = $message['caption'] ?? '';
            edit_message_caption($chat_id, $message_id, $old_cap . "\n\n⏳ در انتظار دلیل رد...");
            send_message($chat_id, 'لطفا دلیل رد را در یک پیام بفرست تا برای کاربر ارسال شود.');
        }
        return;
    }

    if (str_starts_with($data, 'order_done_')) {
        $order_id = substr($data, strlen('order_done_'));
        if (!isset($DATA['orders'][$order_id]) || ($DATA['orders'][$order_id]['status'] ?? '') === 'done') {
            edit_message_text($chat_id, $message_id, ($message['text'] ?? '') . "\n\n✅ قبلاً پردازش شده");
            return;
        }
        $order = &$DATA['orders'][$order_id];
        $order['status'] = 'done';
        edit_message_text($chat_id, $message_id, ($message['text'] ?? '') . "\n\n✅ انجام شد");

        switch ($order['type']) {
            case 'premium': $msgText = premium_done_text($order['plan'], $order['username'], $order['price'], $order_id); break;
            case 'ton': $msgText = ton_done_text($order['amount'], $order['wallet'], $order['price'], $order_id); break;
            case 'stars': $msgText = stars_done_text($order['count'], $order['username'], $order['price'], $order_id); break;
            case 'referral_reward': $msgText = referral_reward_done_text($order['key'], $order['username'], $order_id); break;
            default: $msgText = gift_done_text($order['key'], $order['username'], $order['price'], $order_id);
        }
        send_message($order['chat_id'], $msgText, buy_product_kb());

        $buyer_name = $order['buyer_name'] ?? 'کاربر';
        send_message('@' . REPORTS_CHANNEL, report_text($buyer_name, $order), report_kb());
        return;
    }

    /* ---- tracking ---- */
    if ($data === 'track_have_code') { $ust['state'] = 'awaiting_tracking_code'; edit_message_text($chat_id, $message_id, TRACK_ASK_CODE_TEXT, track_ask_code_kb()); return; }
    if ($data === 'track_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, TRACK_TEXT, track_kb()); return; }

    /* ---- referral ---- */
    if ($data === 'referral_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, referral_text($DATA, $user), invite_kb()); return; }

    if ($data === 'referral_claim_reward') {
        if (!$DATA['referral_points_enabled']) {
            answer_callback_query($cq['id'], '❌ سیستم جایزه دعوت در حال حاضر توسط مدیریت غیرفعال شده است.', true);
            return;
        }
        if (get_user($DATA, $uid)['score'] < REFERRAL_POINTS_REQUIRED) {
            answer_callback_query($cq['id'], 'هنوز امتیاز کافی نداری! برای دریافت جایزه به ' . REFERRAL_POINTS_REQUIRED . ' امتیاز رفرال نیاز داری.', true);
            return;
        }
        edit_message_text($chat_id, $message_id, referral_reward_select_text(), referral_reward_select_kb());
        return;
    }

    if ($data === 'referral_reward_select_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, referral_reward_select_text(), referral_reward_select_kb()); return; }

    if (str_starts_with($data, 'referral_reward_') && in_array(substr($data, strlen('referral_reward_')), REFERRAL_REWARD_GIFTS, true)) {
        $key = substr($data, strlen('referral_reward_'));
        $ust['pending_referral_reward'] = ['key' => $key, 'username' => null];
        $ust['state'] = 'awaiting_referral_username';
        edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_referral_username_kb());
        return;
    }

    if ($data === 'referral_username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_referral_reward'] = $ust['pending_referral_reward'] ?? [];
            $ust['pending_referral_reward']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_referral_username_kb());
        } else {
            $ust['state'] = 'awaiting_referral_username';
            edit_message_text($chat_id, $message_id, "شما در تلگرام یوزرنیم ندارید.\nلطفا یوزرنیم شخص مورد نظر را با @ ارسال کنید.", ikb([[btn('🔙 بازگشت', 'referral_reward_select_back')]]));
        }
        return;
    }

    if ($data === 'referral_confirm_no') { $ust['state'] = 'awaiting_referral_username'; edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_referral_username_kb()); return; }

    if ($data === 'referral_confirm_yes') {
        $r = $ust['pending_referral_reward'] ?? [];
        $key = $r['key'] ?? '';
        $username = $r['username'] ?? '-';
        edit_message_text($chat_id, $message_id, referral_reward_invoice_text($key, $username), referral_reward_invoice_kb());
        return;
    }

    if ($data === 'referral_invoice_cancel') { $ust['pending_referral_reward'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, CANCELLED_TEXT); return; }

    if ($data === 'referral_invoice_confirm') {
        $r = $ust['pending_referral_reward'] ?? [];
        $key = $r['key'] ?? '';
        $username = $r['username'] ?? '-';
        $u = &get_user($DATA, $uid);
        if (!$DATA['referral_points_enabled']) {
            $ust['pending_referral_reward'] = null;
            $ust['state'] = null;
            answer_callback_query($cq['id'], '❌ سیستم جایزه دعوت در حال حاضر توسط مدیریت غیرفعال شده است.', true);
            edit_message_text($chat_id, $message_id, referral_text($DATA, $user), invite_kb());
            return;
        }
        if ($u['score'] < REFERRAL_POINTS_REQUIRED) {
            $ust['pending_referral_reward'] = null;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, '❌ امتیاز رفرال شما کافی نیست.', invite_kb());
            return;
        }
        $u['score'] -= REFERRAL_POINTS_REQUIRED;
        $order_id = new_id();
        push_order_history($DATA, $uid, '🎁 جایزه رفرال - ' . gift_label($key) . ' - رایگان');
        $ust['pending_referral_reward'] = null;
        $DATA['orders'][$order_id] = [
            'type' => 'referral_reward', 'chat_id' => $chat_id, 'user_id' => $uid, 'buyer_name' => display_name($user),
            'key' => $key, 'username' => $username, 'price' => 0, 'status' => 'pending', 'created_at' => persian_now_str(),
        ];
        edit_message_text($chat_id, $message_id, referral_reward_success_text($key, $username, $order_id));
        if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_referral_reward_text($user, $key, $username, $order_id), admin_order_kb($order_id));
        return;
    }

    /* ---- support ---- */
    if ($data === 'support_indirect') { $ust['state'] = 'awaiting_support_message'; edit_message_text($chat_id, $message_id, SUPPORT_INDIRECT_TEXT, support_indirect_kb()); return; }
    if ($data === 'support_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, SUPPORT_TEXT, support_kb()); return; }

    if (str_starts_with($data, 'support_reply_')) {
        $ticket_id = substr($data, strlen('support_reply_'));
        if (!isset($DATA['support_tickets'][$ticket_id])) { answer_callback_query($cq['id'], 'این تیکت قبلا پاسخ داده شده یا پیدا نشد.', true); return; }
        $DATA['admin_waiting_support_reply'][$chat_id] = $ticket_id;
        edit_message_text($chat_id, $message_id, ($message['text'] ?? '') . "\n\nپاسخ خود را بگید");
        return;
    }

    /* ---- stars ---- */
    if ($data === 'product_stars') { $ust['state'] = 'awaiting_stars_amount'; $ust['pending_stars'] = []; edit_message_text($chat_id, $message_id, stars_buy_text(), stars_back_kb()); return; }
    if ($data === 'stars_back_to_amount') { $ust['state'] = 'awaiting_stars_amount'; $ust['pending_stars'] = []; edit_message_text($chat_id, $message_id, stars_buy_text(), stars_back_kb()); return; }

    if ($data === 'stars_username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_stars'] = $ust['pending_stars'] ?? [];
            $ust['pending_stars']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_stars_username_kb());
        } else {
            $ust['state'] = 'awaiting_stars_username';
            edit_message_text($chat_id, $message_id, "شما در تلگرام یوزرنیم ندارید.\nلطفا یوزرنیم شخص مورد نظر را با @ ارسال کنید.", ikb([[btn('🔙 بازگشت', 'stars_back_to_amount')]]));
        }
        return;
    }

    if ($data === 'stars_confirm_no') { $ust['state'] = 'awaiting_stars_username'; edit_message_text($chat_id, $message_id, ASK_USERNAME_TEXT, ask_stars_username_kb()); return; }

    if ($data === 'stars_confirm_yes') {
        $s = &$ust['pending_stars'];
        $s = $s ?? [];
        $count = $s['count'] ?? 0;
        $username = $s['username'] ?? '-';
        $s['price'] = $count * $DATA['stars_price'];
        $s['discount_applied'] = false;
        $price = $s['price'];
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, stars_invoice_text($count, $username, $price, $disc, $price), stars_invoice_kb(false));
        return;
    }

    if ($data === 'stars_invoice_discount') {
        $s = &$ust['pending_stars'];
        $s = $s ?? [];
        $s['discount_applied'] = empty($s['discount_applied']);
        $count = $s['count'] ?? 0;
        $username = $s['username'] ?? '-';
        $price = $s['price'] ?? ($count * $DATA['stars_price']);
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $s, $uid);
        edit_message_text($chat_id, $message_id, stars_invoice_text($count, $username, $price, $disc, $final), stars_invoice_kb($s['discount_applied']));
        return;
    }

    if ($data === 'stars_invoice_cancel') { $ust['pending_stars'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, CANCELLED_TEXT); return; }

    if ($data === 'stars_invoice_confirm') {
        $s = $ust['pending_stars'] ?? [];
        $count = $s['count'] ?? 0;
        $username = $s['username'] ?? '-';
        $price = $s['price'] ?? ($count * $DATA['stars_price']);
        $final = compute_final_price($DATA, $s, $uid);
        $u = &get_user($DATA, $uid);
        if ($u['balance'] >= $final) {
            $u['balance'] -= $final;
            consume_discount_if_applied($DATA, $s, $uid);
            apply_referral_commission($DATA, $uid, $final);
            $order_id = new_id();
            push_order_history($DATA, $uid, "⭐️ خرید {$count} استارز - به مبلغ " . fmt($final) . ' تومان');
            $ust['pending_stars'] = null;
            $DATA['orders'][$order_id] = [
                'type' => 'stars', 'chat_id' => $chat_id, 'user_id' => $uid, 'buyer_name' => display_name($user),
                'count' => $count, 'username' => $username, 'price' => $final, 'status' => 'pending', 'created_at' => persian_now_str(),
            ];
            edit_message_text($chat_id, $message_id, stars_success_text($count, $username, $final, $order_id));
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_stars_text($user, $count, $username, $final, $order_id), admin_order_kb($order_id));
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb());
        }
        return;
    }

    /* ---- ton ---- */
    if ($data === 'product_buy_ton') { $ust['state'] = 'awaiting_ton_amount'; $ust['pending_ton'] = []; edit_message_text($chat_id, $message_id, ton_buy_text($DATA), ton_back_kb()); return; }
    if ($data === 'ton_back_to_amount') { $ust['state'] = 'awaiting_ton_amount'; $ust['pending_ton'] = []; edit_message_text($chat_id, $message_id, ton_buy_text($DATA), ton_back_kb()); return; }
    if ($data === 'ton_memo_yes') { $ust['state'] = 'awaiting_ton_memo'; edit_message_text($chat_id, $message_id, TON_MEMO_INPUT_TEXT, ton_memo_input_kb()); return; }
    if ($data === 'ton_memo_input_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, TON_MEMO_QUESTION_TEXT, ton_memo_kb()); return; }

    if ($data === 'ton_memo_skip') {
        $ton = &$ust['pending_ton'];
        $ton = $ton ?? [];
        $amount = $ton['amount'] ?? 0;
        $wallet = $ton['wallet'] ?? '-';
        $ton['memo'] = 'ندارد';
        $ton['price'] = (int) ($amount * $DATA['ton_price']);
        $ton['discount_applied'] = false;
        $price = $ton['price'];
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, ton_invoice_text($amount, $wallet, 'ندارد', $price, $disc, $price), ton_invoice_kb(false));
        return;
    }

    if ($data === 'ton_memo_back') { $ust['state'] = 'awaiting_ton_wallet'; edit_message_text($chat_id, $message_id, TON_WALLET_TEXT, ton_wallet_back_kb()); return; }

    if ($data === 'ton_invoice_discount') {
        $ton = &$ust['pending_ton'];
        $ton = $ton ?? [];
        $ton['discount_applied'] = empty($ton['discount_applied']);
        $amount = $ton['amount'] ?? 0;
        $wallet = $ton['wallet'] ?? '-';
        $memo = $ton['memo'] ?? 'ندارد';
        $price = $ton['price'] ?? (int) ($amount * $DATA['ton_price']);
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $ton, $uid);
        edit_message_text($chat_id, $message_id, ton_invoice_text($amount, $wallet, $memo, $price, $disc, $final), ton_invoice_kb($ton['discount_applied']));
        return;
    }

    if ($data === 'ton_invoice_cancel') { $ust['pending_ton'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, CANCELLED_TEXT); return; }

    if ($data === 'ton_invoice_confirm') {
        $ton = $ust['pending_ton'] ?? [];
        $amount = $ton['amount'] ?? 0;
        $wallet = $ton['wallet'] ?? '-';
        $memo = $ton['memo'] ?? 'ندارد';
        $price = $ton['price'] ?? (int) ($amount * $DATA['ton_price']);
        $final = compute_final_price($DATA, $ton, $uid);
        $u = &get_user($DATA, $uid);
        if ($u['balance'] >= $final) {
            $u['balance'] -= $final;
            consume_discount_if_applied($DATA, $ton, $uid);
            apply_referral_commission($DATA, $uid, $final);
            $order_id = new_id();
            push_order_history($DATA, $uid, '💱 خرید ارز تون - به مبلغ ' . fmt($final) . ' تومان');
            $ust['pending_ton'] = null;
            $DATA['orders'][$order_id] = [
                'type' => 'ton', 'chat_id' => $chat_id, 'user_id' => $uid, 'buyer_name' => display_name($user),
                'amount' => $amount, 'wallet' => $wallet, 'memo' => $memo, 'price' => $final, 'status' => 'pending', 'created_at' => persian_now_str(),
            ];
            edit_message_text($chat_id, $message_id, ton_success_text($amount, $wallet, $final, $order_id));
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_ton_text($user, $amount, $wallet, $memo, $final, $order_id), admin_order_kb($order_id), 'HTML');
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb());
        }
        return;
    }

    /* ---- NFT gift placeholder (declared in keyboard, unimplemented upstream; wired to a "coming soon" alert like admin_price_giftnft) ---- */
    if ($data === 'product_gift_nft') { answer_callback_query($cq['id'], PRODUCT_PLACEHOLDER_NFT_TEXT, true); return; }

    /* ---- admin panel ---- */
    if ($data === 'admin_panel_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_panel_text($DATA), admin_panel_kb($DATA)); return; }
    if ($data === 'admin_daily_limit') { edit_message_text($chat_id, $message_id, admin_daily_limit_text($DATA), admin_daily_limit_kb()); return; }
    if ($data === 'admin_daily_limit_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_daily_limit_text($DATA), admin_daily_limit_kb()); return; }
    if ($data === 'admin_change_daily_limit') { $ust['state'] = 'admin_awaiting_daily_limit'; edit_message_text($chat_id, $message_id, admin_ask_daily_limit_text($DATA), admin_daily_limit_ask_kb()); return; }

    if ($data === 'admin_profit_menu') { edit_message_text($chat_id, $message_id, admin_profit_menu_text(), admin_profit_menu_kb()); return; }
    if ($data === 'admin_profit_menu_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_profit_menu_text(), admin_profit_menu_kb()); return; }
    if ($data === 'admin_profit_stars') { edit_message_text($chat_id, $message_id, admin_profit_stars_text($DATA), admin_profit_detail_kb('stars')); return; }
    if ($data === 'admin_profit_ton') { edit_message_text($chat_id, $message_id, admin_profit_ton_text($DATA), admin_profit_detail_kb('ton')); return; }
    if ($data === 'admin_profit_gift') { edit_message_text($chat_id, $message_id, admin_profit_gift_text($DATA), admin_profit_detail_kb('gift')); return; }
    if ($data === 'admin_profit_premium') { edit_message_text($chat_id, $message_id, admin_profit_premium_text($DATA), admin_profit_detail_kb('premium')); return; }

    if (str_starts_with($data, 'admin_set_profit_')) {
        $product = substr($data, strlen('admin_set_profit_'));
        $fa_names = ['stars' => 'استارز', 'ton' => 'تون', 'gift' => 'گیفت استارز', 'premium' => 'پرمیوم'];
        $ust['state'] = "admin_awaiting_profit_{$product}";
        edit_message_text($chat_id, $message_id, admin_ask_profit_text($fa_names[$product] ?? $product), admin_profit_ask_kb($product));
        return;
    }

    if ($data === 'admin_reset_confirm') {
        edit_message_text($chat_id, $message_id,
            "⚠️ هشدار!\n\nاین عمل برگشت‌ناپذیر است.\nتمام کاربران، موجودی‌ها، سفارش‌ها و داده‌های ذخیره‌شده کاملاً پاک می‌شوند.\n\nآیا مطمئن هستید؟",
            admin_reset_confirm_kb());
        return;
    }

    if ($data === 'admin_reset_yes') {
        $DATA['users'] = [];
        $DATA['user_names'] = [];
        $DATA['orders'] = [];
        $DATA['topup_requests'] = [];
        $DATA['pending_referrals'] = [];
        $DATA['support_tickets'] = [];
        $DATA['admin_waiting_reject'] = [];
        $DATA['admin_waiting_support_reply'] = [];
        edit_message_text($chat_id, $message_id, "✅ تمام داده‌های ربات پاک شد.\n\nکاربران باید دوباره /start بزنند.", admin_back_kb());
        return;
    }

    if ($data === 'admin_toggle_bot') { $DATA['bot_enabled'] = !$DATA['bot_enabled']; edit_message_text($chat_id, $message_id, admin_panel_text($DATA), admin_panel_kb($DATA)); return; }
    if ($data === 'admin_toggle_referral_points') { $DATA['referral_points_enabled'] = !$DATA['referral_points_enabled']; edit_message_text($chat_id, $message_id, admin_panel_text($DATA), admin_panel_kb($DATA)); return; }

    if ($data === 'admin_broadcast') { $ust['state'] = 'admin_awaiting_broadcast_text'; edit_message_text($chat_id, $message_id, ADMIN_BROADCAST_ASK_TEXT, admin_broadcast_ask_kb()); return; }
    if ($data === 'admin_broadcast_no') { $ust['state'] = 'admin_awaiting_broadcast_text'; edit_message_text($chat_id, $message_id, ADMIN_BROADCAST_ASK_TEXT, admin_broadcast_ask_kb()); return; }

    if ($data === 'admin_broadcast_yes') {
        $broadcast_text = $ust['broadcast_text'] ?? '';
        $success = 0; $fail = 0;
        foreach (array_keys($DATA['users']) as $buid) {
            $res = send_message($buid, broadcast_message_text($broadcast_text));
            if ($res && !empty($res['ok'])) $success++; else $fail++;
        }
        $ust['broadcast_text'] = null;
        edit_message_text($chat_id, $message_id, broadcast_done_text($success, $fail), admin_back_kb());
        return;
    }

    if ($data === 'admin_view_users') { edit_message_text($chat_id, $message_id, view_users_text($DATA), admin_back_kb()); return; }

    if ($data === 'admin_price_menu') { edit_message_text($chat_id, $message_id, ADMIN_PRICE_MENU_TEXT, admin_price_menu_kb()); return; }
    if ($data === 'admin_price_menu_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, ADMIN_PRICE_MENU_TEXT, admin_price_menu_kb()); return; }
    if ($data === 'admin_price_giftnft') { answer_callback_query($cq['id'], 'این بخش بزودی فعال می‌شود ⏳', true); return; }

    if ($data === 'admin_price_stars') { edit_message_text($chat_id, $message_id, admin_stars_price_text($DATA), admin_stars_price_kb()); return; }
    if ($data === 'admin_change_stars_price') { $ust['state'] = 'admin_awaiting_stars_price'; edit_message_text($chat_id, $message_id, admin_ask_stars_price_text(), admin_stars_price_ask_kb()); return; }
    if ($data === 'admin_price_stars_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_stars_price_text($DATA), admin_stars_price_kb()); return; }

    if ($data === 'admin_price_ton') { edit_message_text($chat_id, $message_id, admin_ton_price_text($DATA), admin_ton_price_kb()); return; }
    if ($data === 'admin_change_ton_price') { $ust['state'] = 'admin_awaiting_ton_price'; edit_message_text($chat_id, $message_id, admin_ask_ton_price_text(), admin_ton_price_ask_kb()); return; }
    if ($data === 'admin_price_ton_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_ton_price_text($DATA), admin_ton_price_kb()); return; }

    if ($data === 'admin_price_premium') { edit_message_text($chat_id, $message_id, admin_premium_price_text($DATA), admin_premium_price_kb()); return; }
    if ($data === 'admin_change_premium_price') { edit_message_text($chat_id, $message_id, ADMIN_PREMIUM_PLAN_SELECT_TEXT, admin_premium_plan_select_kb()); return; }
    if ($data === 'admin_price_premium_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_premium_price_text($DATA), admin_premium_price_kb()); return; }

    if (in_array($data, ['admin_premium_plan_3', 'admin_premium_plan_6', 'admin_premium_plan_12'], true)) {
        $parts = explode('_', $data);
        $plan = end($parts);
        $ust['state'] = "admin_awaiting_premium_price_{$plan}";
        edit_message_text($chat_id, $message_id, admin_ask_premium_price_text($plan), admin_premium_price_ask_kb());
        return;
    }

    if ($data === 'admin_premium_plan_select_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, ADMIN_PREMIUM_PLAN_SELECT_TEXT, admin_premium_plan_select_kb()); return; }

    if ($data === 'admin_price_giftstars') { edit_message_text($chat_id, $message_id, ADMIN_GIFT_PRICE_LIST_TEXT, admin_gift_price_list_kb()); return; }
    if ($data === 'admin_gift_price_list_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, ADMIN_GIFT_PRICE_LIST_TEXT, admin_gift_price_list_kb()); return; }

    if (str_starts_with($data, 'admin_gift_price_detail_back_')) {
        $key = substr($data, strlen('admin_gift_price_detail_back_'));
        $ust['state'] = null;
        edit_message_text($chat_id, $message_id, admin_gift_price_detail_text($DATA, $key), admin_gift_price_detail_kb($key));
        return;
    }

    if (str_starts_with($data, 'admin_change_gift_price_')) {
        $key = substr($data, strlen('admin_change_gift_price_'));
        $ust['state'] = "admin_awaiting_gift_price_{$key}";
        edit_message_text($chat_id, $message_id, admin_ask_gift_price_text($key), admin_gift_price_ask_kb($key));
        return;
    }

    if (str_starts_with($data, 'admin_gift_price_')) {
        $key = substr($data, strlen('admin_gift_price_'));
        if (isset(GIFTS_META[$key])) {
            edit_message_text($chat_id, $message_id, admin_gift_price_detail_text($DATA, $key), admin_gift_price_detail_kb($key));
        }
        return;
    }
}

/* ===================== ENTRY POINT ===================== */

function handle_setup_webhook(): void {
    $url = $_GET['url'] ?? '';
    header('Content-Type: application/json; charset=utf-8');
    if ($url === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing url parameter. Usage: ?setup_webhook=1&url=https://yourdomain.com/bot.php']);
        return;
    }
    $res = tg_api('setWebhook', ['url' => $url, 'allowed_updates' => ['message', 'callback_query']]);
    echo json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function dispatch_update(array $update, array &$DATA): void {
    if (isset($update['message'])) {
        $msg = $update['message'];
        if (!isset($msg['from'], $msg['chat'])) return;
        if (isset($msg['photo'])) { handle_photo($msg, $DATA); return; }
        if (isset($msg['text'])) {
            if (str_starts_with(ltrim($msg['text']), '/start')) { handle_start($msg, $DATA); return; }
            if (str_starts_with(ltrim($msg['text']), '/')) return;
            handle_text($msg, $DATA);
        }
        return;
    }
    if (isset($update['callback_query'])) {
        handle_callback($update['callback_query'], $DATA);
    }
}

function main_entry(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        if (isset($_GET['setup_webhook'])) { handle_setup_webhook(); return; }
        header('Content-Type: text/plain; charset=utf-8');
        echo 'GiftIx bot webhook endpoint is alive.';
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(200); return; }

    $input = file_get_contents('php://input');
    if ($input === false || $input === '') { http_response_code(200); return; }
    $update = json_decode($input, true);
    if (!is_array($update)) { http_response_code(200); return; }

    // webhook requests can race, hence the exclusive lock for the whole read-modify-write cycle
    $lockFh = fopen(LOCK_FILE, 'c');
    if ($lockFh === false) { error_log('Cannot open lock file'); http_response_code(200); return; }
    flock($lockFh, LOCK_EX);

    $DATA = load_data();
    try {
        dispatch_update($update, $DATA);
    } catch (Throwable $e) {
        error_log('Unhandled exception: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
    save_data($DATA);

    flock($lockFh, LOCK_UN);
    fclose($lockFh);
    http_response_code(200);
}

main_entry();

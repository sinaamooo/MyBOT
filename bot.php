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
 * - TON base price is fetched from Nobitex (market/stats, ton/rls) with the
 *   same lazy-refresh trick: cached with a timestamp in the JSON store and
 *   re-fetched on read once stale (TON_PRICE_CACHE_SECONDS). Optionally hit
 *   bot.php?cron=refresh_prices from a real server cron for fresher pricing.
 * - Every user-facing/admin-notification text lives in data.texts[slug] and
 *   every button label in data.buttons[slug], both seeded from the
 *   DEFAULT_TEXTS / DEFAULT_BUTTONS constants below and editable at runtime
 *   from the admin panel ("✏️ ویرایش متن‌های ربات" / "✏️ ویرایش نام دکمه‌ها").
 *   Lookups always fall back to the hardcoded default if a slug is missing,
 *   and load_data() only ever fills in *missing* slugs - it never
 *   overwrites an admin's saved edit on a later request.
 * - Text edits (including bold/italic/.../custom_emoji formatting) are
 *   converted to HTML once at save time via tg_entities_to_html() and stored
 *   as that HTML string; T() then only needs to substitute {token}s (each
 *   HTML-escaped) and every send of a stored text uses parse_mode=HTML, so
 *   admin-picked premium emoji reach real users, not just the admin preview.
 * - Reply-keyboard taps are resolved by reverse-looking-up the incoming
 *   text against the *current* buttons store (not hardcoded literals), so
 *   renaming a button never breaks routing. Inline buttons route by
 *   callback_data (unaffected by label edits); only their displayed text/
 *   style/icon come from the store.
 * - Bot API 9.4 "style" (primary/success/danger) and
 *   icon_custom_emoji_id/custom_emoji entities only actually render for a
 *   bot owner with Telegram Premium; every send that uses them retries once
 *   with the fields stripped if Telegram rejects the call, so the bot never
 *   gets stuck failing to send.
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

const NOBITEX_TON_STATS_URL = 'https://api.nobitex.ir/market/stats?srcCurrency=ton&dstCurrency=rls';
const TON_PRICE_CACHE_SECONDS = 300;

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

/* ===================== EDITABLE TEXTS / BUTTONS METADATA ===================== */

const CATEGORY_LABELS = [
    'welcome'    => '👋 خوش‌آمدگویی / محصولات',
    'premium'    => '💎 پرمیوم',
    'stars'      => '⭐️ استارز',
    'gift'       => '🎁 گیفت',
    'ton'        => '💱 تون',
    'wallet'     => '👛 شارژ کیف‌پول',
    'track'      => '📦 پیگیری سفارش',
    'support'    => '📞 پشتیبانی',
    'referral'   => '👥 رفرال',
    'admin_msgs' => '🛠 پیام‌های ادمین',
    'admin_ops'  => '⚙️ عملیات ادمین (قیمت/سود/سقف)',
];

const TEXT_CATEGORIES = [
    'welcome' => ['welcome', 'product_menu', 'join', 'join_confirmed', 'bot_off', 'trust', 'cancelled', 'ask_username', 'confirm_username', 'insufficient', 'no_username_error'],
    'premium' => ['premium_menu', 'premium_invoice', 'premium_success', 'premium_done'],
    'stars' => ['stars_buy', 'stars_min_error', 'stars_invoice', 'stars_success', 'stars_done'],
    'gift' => ['gift_list', 'gift_comment_type', 'gift_comment_input', 'gift_invoice', 'gift_success', 'gift_done'],
    'ton' => ['ton_buy', 'ton_wallet_ask', 'ton_memo_question', 'ton_memo_input', 'ton_invoice', 'ton_success', 'ton_done'],
    'wallet' => ['wallet_increase', 'wallet', 'toman_payment', 'receipt_prompt', 'receipt_sent', 'card_details', 'approved', 'rejected', 'daily_limit_exceeded', 'daily_limit_exceeded_zero'],
    'track' => ['track', 'track_ask_code', 'track_not_found', 'order_status'],
    'support' => ['support', 'support_indirect', 'support_sent_confirm'],
    'referral' => ['referral', 'referral_notification', 'referral_reward_select', 'referral_reward_invoice', 'referral_reward_success', 'referral_reward_done'],
    'admin_msgs' => ['admin_premium_order', 'admin_stars_order', 'admin_gift_order', 'admin_ton_order', 'admin_support_notify', 'admin_referral_reward_order', 'admin_topup_caption', 'report', 'broadcast_message'],
    'admin_ops' => ['admin_ask_stars_price', 'admin_ask_ton_price', 'admin_ask_premium_price', 'admin_ask_gift_price', 'admin_ask_daily_limit', 'admin_ask_profit',
        'admin_numeric_error', 'admin_positive_number_error', 'admin_positive_percent_error'],
];

const TEXT_PLACEHOLDERS = [
    'welcome' => [], 'product_menu' => [], 'join' => ['{channel}'], 'join_confirmed' => [], 'bot_off' => [],
    'trust' => ['{channel}'], 'cancelled' => [], 'ask_username' => [], 'confirm_username' => ['{username}'],
    'insufficient' => ['{shortfall}'], 'no_username_error' => [],
    'premium_menu' => [], 'premium_invoice' => ['{plan}', '{price}', '{discount}', '{max_discount}', '{final}'],
    'premium_success' => ['{plan}', '{username}', '{price}', '{order_id}'], 'premium_done' => ['{plan}', '{username}', '{price}', '{order_id}'],
    'stars_buy' => ['{min}'], 'stars_min_error' => ['{min}'],
    'stars_invoice' => ['{count}', '{username}', '{price}', '{discount}', '{max_discount}', '{final}'],
    'stars_success' => ['{count}', '{username}', '{price}', '{order_id}'], 'stars_done' => ['{count}', '{username}', '{price}', '{order_id}'],
    'gift_list' => [], 'gift_comment_type' => [], 'gift_comment_input' => [],
    'gift_invoice' => ['{gift}', '{username}', '{hide}', '{comment}', '{price}', '{discount}', '{max_discount}', '{final}'],
    'gift_success' => ['{gift}', '{username}', '{price}', '{order_id}'], 'gift_done' => ['{gift}', '{username}', '{price}', '{order_id}'],
    'ton_buy' => ['{price}', '{min}'], 'ton_wallet_ask' => [], 'ton_memo_question' => [], 'ton_memo_input' => [],
    'ton_invoice' => ['{amount}', '{wallet}', '{memo}', '{price}', '{discount}', '{max_discount}', '{final}'],
    'ton_success' => ['{amount}', '{wallet}', '{price}', '{order_id}'], 'ton_done' => ['{amount}', '{wallet}', '{price}', '{order_id}'],
    'wallet_increase' => [], 'wallet' => ['{balance}', '{remaining}'], 'toman_payment' => ['{remaining}'],
    'receipt_prompt' => [], 'receipt_sent' => [], 'card_details' => ['{amount}', '{card_number}', '{card_holder}'],
    'approved' => ['{balance}'], 'rejected' => ['{reason}'], 'daily_limit_exceeded' => ['{remaining}'], 'daily_limit_exceeded_zero' => [],
    'track' => [], 'track_ask_code' => [], 'track_not_found' => [],
    'order_status' => ['{label}', '{emoji}', '{qty}', '{price}', '{status}', '{extra}', '{date}'],
    'support' => [], 'support_indirect' => [], 'support_sent_confirm' => [],
    'referral' => ['{link}', '{referrals}', '{discount}', '{score}', '{commission_percent}', '{points_required}', '{trust_channel}'],
    'referral_notification' => ['{invited_name}', '{point_line}', '{referrals}', '{discount}', '{score}', '{commission_percent}'],
    'referral_reward_select' => [], 'referral_reward_invoice' => ['{gift}', '{username}', '{points_required}'],
    'referral_reward_success' => ['{gift}', '{username}', '{order_id}'], 'referral_reward_done' => ['{gift}', '{username}', '{order_id}'],
    'admin_premium_order' => ['{user_name}', '{username_at}', '{user_id}', '{plan}', '{buy_username}', '{price}', '{order_id}'],
    'admin_stars_order' => ['{user_name}', '{username_at}', '{user_id}', '{count}', '{buy_username}', '{price}', '{order_id}'],
    'admin_gift_order' => ['{user_name}', '{username_at}', '{user_id}', '{gift}', '{buy_username}', '{hide}', '{comment}', '{price}', '{order_id}'],
    'admin_ton_order' => ['{user_name}', '{username_at}', '{user_id}', '{amount}', '{wallet}', '{memo}', '{price}', '{order_id}'],
    'admin_support_notify' => ['{user_name}', '{username_at}', '{user_id}', '{message}'],
    'admin_referral_reward_order' => ['{user_name}', '{username_at}', '{user_id}', '{gift}', '{buy_username}', '{order_id}'],
    'admin_topup_caption' => ['{user_name}', '{username_at}', '{user_id}', '{amount}', '{req_id}'],
    'report' => ['{buyer_name}', '{label}', '{emoji}', '{qty}', '{price}', '{date}', '{bot_username}'],
    'broadcast_message' => ['{text}'],
    'admin_ask_stars_price' => [], 'admin_ask_ton_price' => ['{min_ton}'], 'admin_ask_premium_price' => ['{plan}'],
    'admin_ask_gift_price' => ['{gift}'], 'admin_ask_daily_limit' => ['{limit}'], 'admin_ask_profit' => ['{product}'],
    'admin_numeric_error' => [], 'admin_positive_number_error' => [], 'admin_positive_percent_error' => [],
];

const TEXT_LABELS = [
    'welcome' => 'پیام خوش‌آمدگویی',
    'product_menu' => 'منوی محصولات',
    'join' => 'درخواست عضویت در کانال',
    'join_confirmed' => 'تایید عضویت در کانال',
    'bot_off' => 'پیام خاموش بودن ربات',
    'trust' => 'متن اعتمادسازی',
    'cancelled' => 'پیام لغو سفارش',
    'ask_username' => 'درخواست یوزرنیم گیرنده',
    'confirm_username' => 'تایید یوزرنیم',
    'insufficient' => 'پیام کمبود موجودی',
    'no_username_error' => 'خطای نبود یوزرنیم تلگرام',
    'premium_menu' => 'منوی پرمیوم',
    'premium_invoice' => 'فاکتور خرید پرمیوم',
    'premium_success' => 'ثبت موفق سفارش پرمیوم',
    'premium_done' => 'تکمیل سفارش پرمیوم',
    'stars_buy' => 'متن خرید استارز',
    'stars_min_error' => 'خطای حداقل خرید استارز',
    'stars_invoice' => 'فاکتور خرید استارز',
    'stars_success' => 'ثبت موفق سفارش استارز',
    'stars_done' => 'تکمیل سفارش استارز',
    'gift_list' => 'متن لیست گیفت‌ها',
    'gift_comment_type' => 'انتخاب نوع کامنت گیفت',
    'gift_comment_input' => 'دریافت متن کامنت گیفت',
    'gift_invoice' => 'فاکتور خرید گیفت',
    'gift_success' => 'ثبت موفق سفارش گیفت',
    'gift_done' => 'تکمیل سفارش گیفت',
    'ton_buy' => 'متن خرید ارز تون',
    'ton_wallet_ask' => 'درخواست آدرس ولت تون',
    'ton_memo_question' => 'سوال ممو/کامنت ولت',
    'ton_memo_input' => 'دریافت ممو/کامنت ولت',
    'ton_invoice' => 'فاکتور خرید تون',
    'ton_success' => 'ثبت موفق سفارش تون',
    'ton_done' => 'تکمیل سفارش تون',
    'wallet_increase' => 'منوی افزایش موجودی',
    'wallet' => 'متن کیف پول',
    'toman_payment' => 'متن پرداخت تومانی',
    'receipt_prompt' => 'درخواست ارسال رسید',
    'receipt_sent' => 'تایید ارسال رسید',
    'card_details' => 'اطلاعات شماره کارت',
    'approved' => 'تایید افزایش موجودی',
    'rejected' => 'رد افزایش موجودی',
    'daily_limit_exceeded' => 'اتمام سقف روزانه (مبلغ باقیمانده)',
    'daily_limit_exceeded_zero' => 'اتمام کامل سقف روزانه',
    'track' => 'منوی پیگیری سفارش',
    'track_ask_code' => 'درخواست کد پیگیری',
    'track_not_found' => 'پیام نبود کد پیگیری',
    'order_status' => 'نمایش وضعیت سفارش',
    'support' => 'منوی پشتیبانی',
    'support_indirect' => 'درخواست پیام برای پشتیبانی',
    'support_sent_confirm' => 'تایید ارسال پیام پشتیبانی',
    'referral' => 'منوی رفرال',
    'referral_notification' => 'اطلاع‌رسانی رفرال جدید',
    'referral_reward_select' => 'انتخاب جایزه رفرال',
    'referral_reward_invoice' => 'فاکتور جایزه رفرال',
    'referral_reward_success' => 'ثبت موفق جایزه رفرال',
    'referral_reward_done' => 'تکمیل جایزه رفرال',
    'admin_premium_order' => 'اعلان سفارش پرمیوم به ادمین',
    'admin_stars_order' => 'اعلان سفارش استارز به ادمین',
    'admin_gift_order' => 'اعلان سفارش گیفت به ادمین',
    'admin_ton_order' => 'اعلان سفارش تون به ادمین',
    'admin_support_notify' => 'اعلان تیکت پشتیبانی به ادمین',
    'admin_referral_reward_order' => 'اعلان جایزه رفرال به ادمین',
    'admin_topup_caption' => 'اعلان درخواست شارژ به ادمین',
    'report' => 'گزارش خرید در کانال ریپورت',
    'broadcast_message' => 'قالب پیام همگانی',
    'admin_ask_stars_price' => 'درخواست قیمت جدید استارز',
    'admin_ask_ton_price' => 'درخواست قیمت جدید تون',
    'admin_ask_premium_price' => 'درخواست قیمت جدید پرمیوم',
    'admin_ask_gift_price' => 'درخواست قیمت جدید گیفت',
    'admin_ask_daily_limit' => 'درخواست سقف روزانه جدید',
    'admin_ask_profit' => 'درخواست درصد سود جدید',
    'admin_numeric_error' => 'خطای ورودی غیرعددی',
    'admin_positive_number_error' => 'خطای ورودی غیر مثبت',
    'admin_positive_percent_error' => 'خطای درصد سود نامعتبر',
];

const BUTTON_CATEGORIES = [
    'welcome' => ['menu_buy', 'menu_topup', 'menu_referral', 'menu_account', 'menu_track', 'menu_support', 'menu_trust', 'menu_admin',
        'btn_back', 'btn_back_to_menu', 'btn_self', 'btn_yes_short', 'btn_no_short', 'btn_confirm', 'btn_cancel',
        'btn_card_to_card', 'btn_discount_apply', 'btn_discount_remove', 'btn_join_channel', 'btn_check_membership'],
    'premium' => ['btn_premium', 'btn_plan_3', 'btn_plan_6', 'btn_plan_12'],
    'stars' => ['btn_stars'],
    'gift' => ['btn_gift_stars', 'btn_gift_nft', 'btn_gift_comment_free', 'btn_gift_hide_on', 'btn_gift_hide_off', 'btn_gift_comment_set', 'btn_gift_comment_del'],
    'ton' => ['btn_buy_ton', 'btn_ton_short', 'btn_ton_memo_yes', 'btn_ton_memo_skip'],
    'wallet' => ['btn_wallet_increase', 'btn_toman_payment', 'btn_send_receipt', 'btn_admin_approve', 'btn_admin_reject'],
    'track' => ['btn_track_have_code', 'btn_admin_done', 'btn_report_buy'],
    'support' => ['btn_support_direct', 'btn_support_indirect', 'btn_admin_reply'],
    'referral' => ['btn_referral_claim'],
    'admin_msgs' => ['btn_admin_broadcast', 'btn_admin_view_users', 'btn_admin_price_menu', 'btn_admin_profit_menu',
        'btn_admin_daily_limit', 'btn_admin_reset', 'btn_admin_reset_yes', 'btn_admin_reset_no', 'btn_admin_edit_price',
        'btn_admin_edit_daily_limit', 'btn_admin_edit_profit', 'btn_no_plain', 'btn_admin_edit_texts', 'btn_admin_edit_buttons'],
];

// Inline-keyboard groups the admin can re-chunk into 1/2/3 columns per row
// (data.layout[group] => column count). Scoped to columns-per-row, not
// free-form per-button drag reordering.
const LAYOUT_GROUPS = [
    'products'      => ['label' => '🛒 منوی محصولات', 'default' => 2],
    'premium_plans' => ['label' => '💎 انتخاب پلن پرمیوم', 'default' => 3],
    'gift_list'     => ['label' => '🎁 لیست گیفت‌ها', 'default' => 2],
    'admin_panel'   => ['label' => '🛠 منوی اصلی پنل ادمین', 'default' => 1],
];

function default_texts(): array {
    return [
        'welcome' => "🌟 ب ربات فروشگاه GiftIx خوش آمدید !\n\n" .
            "✨با ما بهترین خدمات تلگرام را با کمترین قیمت و بالاترین سرعت دریافت کنید\n\n" .
            "🎁 خدمات بات GiftIx\n\n" .
            "• ⭐️استارز تلگرام\n" .
            "• 💎پرمیوم تلگرام\n" .
            "• 🎁گیفت‌های استارزی و NFT\n" .
            "• ⚡️فروش ارز تون ( GRAM )\n\n" .
            "🚀 برای شروع ، یکی از گزینه‌های منو پایین را انتخاب کنید ☝️",

        'product_menu' => "🛒 محصول مورد نظر خودتو انتخاب کن !\n\n" .
            "🚀 تمامی سفارشات با بالاترین سرعت و همراه با پشتیبانی کامل انجام می‌شن " .
            "تا تجربه‌ای مطمئن و بی‌دغدغه داشته باشی !\n\n" .
            "🫶 از لیست زیر ، گزینه مورد نظرت رو انتخاب کن :",

        'join' => "برای استفاده از ربات، باید در کانال‌های زیر عضو شوید\n\n@{channel}",
        'join_confirmed' => '✅ عضویتت تایید شد، حالا میتونی از ربات استفاده کنی.',
        'bot_off' => "🔴 ربات در حال حاضر خاموش است.\n\nبه زودی دوباره روشن خواهد شد، لطفا کمی صبر کنید 🙏",

        'trust' => "⭐ چرا می‌توانید با خیال راحت به ما اعتماد کنید؟\n\n" .
            "ما یک ساله در زمینه‌ی خرید و فروش استارز و پرمیوم فعالیت داریم و در این مدت " .
            "با صداقت، سرعت و پشتیبانی قوی تونستیم اعتماد صدها کاربر رو جلب کنیم ❤️\n\n" .
            "✅ دلایل اعتماد :\n" .
            "• 💳 پرداخت سریع، مطمئن و شفاف\n" .
            "• 🌟 بیش از 200 رضایت واقعی مشتری\n" .
            "• 📞 پشتیبانی فعال 24 ساعته، قبل و بعد از خرید\n" .
            "• 🧾 دارای نماد اعتبار و سابقه‌ی چندساله فعالیت بدون هیچ گزارش منفی\n\n" .
            "📣 برای مشاهده‌ی نظرات و رضایت کاربران، می‌تونید در کانال @{channel} ما\n" .
            "هشتگ زیر رو جست‌وجو کنید ☝️\n\n" .
            "☝️ #رضایت_GiftIx\n" .
            "اعتماد شما باعث افتخار ماست 💙",

        'cancelled' => "👎 سفارش شما لغو شد\n\nبرای خرید مجدد، از منوی اصلی گزینه ' خـریـد مـحـصـول🛒' را انتخاب کنید.",

        'ask_username' => "📎 یوزرنیم اکانت مورد نظر \n\n" .
            "✅اگر قصد خرید برای اکانت خودتان را دارید ، روی دکمه «برای خودم» کلیک کنید.\n\n" .
            "✅اگر قصد خرید برای شخص دیگری را دارید یوزرنیم تلگرام او را با علامت @ ارسال کنید\n\n" .
            "نمونه:\n" .
            "✅@Eli_as13\n" .
            "😭 Eli_as13",

        'confirm_username' => "{username}\n\n✅ آیا یوزرنیم بالا را تایید می‌کنید؟",
        'insufficient' => 'برای ادامه خرید مبلغ کمبود شما {shortfall} تومان است. یکی از روش های زیر را برای شارژ سریع انتخاب کنید:',
        'no_username_error' => "شما در تلگرام یوزرنیم ندارید.\nلطفا یوزرنیم شخص مورد نظر را با @ ارسال کنید.",

        'premium_menu' => "🟪 Telegram Premium\n\n" .
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
            "✍️ کدام گزینه را انتخاب می‌کنید؟",

        'premium_invoice' => "⏰ فاکتور خرید پرمیوم\n\n" .
            "💫 نوع پلن: {plan} ماهه\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💰 مبلغ فاکتور:  {price} تومان\n" .
            "🎁 کل موجودی تخفیف:  {discount} تومان\n\n" .
            "💡 حداکثر تخفیف قابل اعمال:  {max_discount} تومان\n\n" .
            "💳 مبلغ نهایی:  {final} تومان\n\n" .
            "🍾 در صورتی که جزئیات بالا مورد تأیید شماست \n\n" .
            "روی دکمه «تأیید ✅» کلیک کنید.",

        'premium_success' => "✅ سفارش شما با موفقیت ثبت شد!\n\n" .
            "💫 پلن: {plan} ماهه\n" .
            "📎 یوزر: {username}\n" .
            "💰 مبلغ پرداخت‌شده: {price} تومان\n\n" .
            "🔑 کد پیگیری سفارش : {order_id}\n\n" .
            "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
            "🙏 از اعتماد شما سپاسگزاریم.",

        'premium_done' => "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
            "☝️اطلاعات سفارش:\n\n" .
            "ℹ️ نوع خدمات: پرمیوم تلگرام\n" .
            "💫 پلن: {plan} ماهه\n" .
            "📎 یوزر: {username}\n" .
            "💳 مبلغ نهایی پرداخت شده:  {price} تومان\n" .
            "🔓 کد پیگیری سفارش: {order_id}\n\n" .
            "😊 از اعتماد و انتخاب شما سپاسگزاریم.",

        'stars_buy' => "🌟 خرید استارز تلگرام\n\n" .
            "🚀 تحویل آنی، پشتیبانی لحظه‌ای و ثبت سفارش بدون تأخیر\n\n" .
            "✨ مناسب برای:\n" .
            "💎  خرید تلگرام پریمیوم \n" .
            "⭐️ ری‌اکشن‌های استارزی\n" .
            "📣 تبلیغات تلگرامی\n" .
            "🛍 مینی‌اپ‌ها \n" .
            "🎁خرید گیفت\n\n" .
            "📈 حداقل خرید:  {min} عدد\n\n" .
            "👇 تعداد استارز موردنیاز خود را وارد کنید:",

        'stars_min_error' => '‼️ حداقل تعداد استارز برای خرید {min} عدد میباشد ...',

        'stars_invoice' => "⏰ فاکتور خرید استارز\n\n" .
            "💫 مقدار خرید: {count}\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💰 مبلغ فاکتور:  {price} تومان\n" .
            "🎁 کل موجودی تخفیف:  {discount} تومان\n\n" .
            "💡 حداکثر تخفیف قابل اعمال:  {max_discount} تومان\n\n" .
            "💳 مبلغ نهایی:  {final} تومان\n\n" .
            "🍾 در صورتی که جزئیات بالا مورد تأیید شماست \n\n" .
            "روی دکمه «تأیید ✅» کلیک کنید.",

        'stars_success' => "✅ سفارش شما با موفقیت ثبت شد!\n\n" .
            "⛏️ نوع خدمات: خرید استارز تلگرام\n" .
            "⭐️ تعداد: {count} استارز\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💰 مبلغ پرداختی:  {price} تومان\n" .
            "🔑 کد پیگیری سفارش : {order_id}\n\n" .
            "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
            "🙏 از اعتماد شما سپاسگزاریم.",

        'stars_done' => "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
            "☝️اطلاعات سفارش:\n\n" .
            "ℹ️ نوع خدمات: خرید استارز تلگرام\n" .
            "⭐️ تعداد: {count} استارز\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💳 مبلغ نهایی پرداخت شده:  {price} تومان\n" .
            "🔓 کد پیگیری سفارش: {order_id}\n\n" .
            "😊 از اعتماد و انتخاب شما سپاسگزاریم.",

        'gift_list' => "💰 هدیه دادن گیفت ، تجربه‌ای خاص در تلگرام!\n\n" .
            "⭐️ با گیفت‌های استارزی می‌تونی دوستان، خانواده یا معشوقت رو شگفت‌زده کنی !\n\n" .
            "💫 مزایای گیفت‌های استارزی :\n\n" .
            "• 🎁 ارسال هدیه به دوستان و آشنایان برای سوپرایز کردن\n" .
            "• 👾 قابل نمایش روی پروفایل تلگرام\n\n" .
            "💱 لطفاً گیفت مورد نظر خود را از لیست زیر انتخاب کنید :",

        'gift_comment_type' => "💬 تنظیم کامنت گیفت\n\nنوع کامنت را انتخاب کنید:",
        'gift_comment_input' => 'کامنت خود را ارسال کنید:',

        'gift_invoice' => "📑 فاکتور خرید گیفت\n\n" .
            "💫 مقدار خرید: {gift}\n" .
            "🔗 یوزر دریافت‌کننده: {username}\n" .
            "🔒 گیفت هاید: {hide}\n" .
            "کامنت : {comment}\n\n" .
            "💰 مبلغ فاکتور:  {price} تومان\n" .
            "🎁 کل موجودی تخفیف:  {discount} تومان\n\n" .
            "💡 حداکثر تخفیف قابل اعمال:  {max_discount} تومان\n\n" .
            "💳 مبلغ نهایی:  {final} تومان\n\n" .
            "🔮 در صورتی که جزئیات بالا مورد تأیید شماست ✓ \n" .
            "روی دکمه «تأیید ✅» کلیک کنید.",

        'gift_success' => "✅ سفارش شما با موفقیت ثبت شد!\n\n" .
            "⛏️ نوع خدمات: خرید گیفت استارز\n" .
            "🎁 گیفت: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💰 مبلغ پرداختی:  {price} تومان\n" .
            "🔑 کد پیگیری سفارش : {order_id}\n\n" .
            "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
            "🙏 از اعتماد شما سپاسگزاریم.",

        'gift_done' => "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
            "☝️اطلاعات سفارش:\n\n" .
            "ℹ️ نوع خدمات: خرید گیفت استارز\n" .
            "🎁 گیفت: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💳 مبلغ نهایی پرداخت شده:  {price} تومان\n" .
            "🔓 کد پیگیری سفارش: {order_id}\n\n" .
            "😊 از اعتماد و انتخاب شما سپاسگزاریم.",

        'ton_buy' => "💰 خرید ارز تون (GRAM)\n\n" .
            "✨ ارز تون را با بهترین قیمت و تحویل مستقیم به کیف پول خود دریافت کنید.\n\n" .
            "💎 انتقال مستقیم به ولت \n" .
            "⚡️ انجام سریع سفارش\n" .
            "🔒 تراکنش امن و قابل‌اعتماد\n" .
            "🙏 پشتیبانی کامل در تمامی مراحل\n\n" .
            "📊 قیمت هر TON:  {price} تومان\n" .
            "📌 حداقل سفارش: {min} TON\n\n" .
            "☝️ مقدار TON موردنیاز خود را وارد کنید.\n\n" .
            "💡 مثال: 0.5 یا 2.5",

        'ton_wallet_ask' => "💼 آدرس ولت تون ( TON )\n\n" .
            "⚠️ نکات مهم قبل از ارسال آدرس :\n\n" .
            "• آدرس باید مخصوص شبکه TON باشد.\n" .
            "• آدرس را کامل و بدون فاصله یا تغییر ارسال نمایید.\n" .
            "• مسئولیت صحت آدرس واردشده بر عهده کاربر است.\n\n" .
            "📎 نمونه فرمت آدرس ولت تون :\n" .
            "UQ….................…xY\n" .
            "یا\n" .
            "EQ….................…9K\n\n" .
            "🔓 لطفاً آدرس ولت تون ( TON ) خود را ارسال کنید :",

        'ton_memo_question' => "💼 آدرس ولت ثبت شد!\n\n" .
            "💬 یک سوال مهم:\n\n" .
            "بعضی ولت‌های TON برای دریافت درستِ ارز، نیاز به کامنت (ممو) دارن " .
            "تا واریز به همون ولت بره و گم نشه!\n\n" .
            "🌟 ولت شما کامنت/ممو داره؟\n" .
            "• اگه داره، روی «بله، کامنت دارم» بزن و متن کامنت رو بفرست\n" .
            "• اگه نداره یا مطمئن نیستی، روی «رد کردن» بزن",

        'ton_memo_input' => "💬 ارسال کامنت/ممو ولت\n\n" .
            "لطفاً متن کامنت (ممو) ولت تون رو همینجا بفرستید " .
            "تا دقیقاً همون برای واریز استفاده بشه.",

        'ton_invoice' => "📑 فاکتور خرید ارز تون\n\n" .
            "💫 مقدار خرید: {amount} تون\n" .
            "🏦 ولت آدرس دریافتی: {wallet}\n" .
            "💬 ممو/کامنت ولت: {memo}\n" .
            "💰 مبلغ فاکتور:  {price} تومان\n" .
            "🎁 کل موجودی تخفیف:  {discount} تومان\n\n" .
            "💡 حداکثر تخفیف قابل اعمال:  {max_discount} تومان\n\n" .
            "💳 مبلغ نهایی:  {final} تومان\n\n" .
            "🔮 در صورتی که جزئیات بالا مورد تأیید شماست ✓ \n" .
            "روی دکمه «تأیید ✅» کلیک کنید.",

        'ton_success' => "✅ سفارش شما با موفقیت ثبت شد.\n\n" .
            "⛏️نوع خدمات : خرید ارز تون\n" .
            "🔢 تعداد : {amount}\n" .
            "📎 آدرس ولت : {wallet}\n" .
            "💰 مبلغ پرداختی :  {price} تومان\n" .
            "🔑 کد پیگیری سفارش : {order_id}\n\n" .
            "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
            "🙏 از اعتماد شما سپاسگزاریم.",

        'ton_done' => "✅ سفارش شما با موفقیت انجام شد 🎉\n\n" .
            "☝️اطلاعات سفارش:\n\n" .
            "ℹ️ نوع خدمات: خرید ارز تون\n" .
            "👀 تعداد: {amount} TON\n" .
            "🔗 ولت تون: {wallet}\n" .
            "💳 مبلغ نهایی پرداخت شده:  {price} تومان\n" .
            "🔓 کد پیگیری سفارش: {order_id}\n\n" .
            "😊 از اعتماد و انتخاب شما سپاسگزاریم.",

        'wallet_increase' => "💰 افـزایـش مـوجـودی حساب شما\n\n" .
            "شما می‌توانید موجودی خود را به دو روش امن و سریع افزایش دهید :\n\n" .
            "💳 پرداخت تومانی – پس از واریز ، موجودی شما به سرعت با تایید پشتیبانی شارژ میشود.\n\n" .
            "🚀 فرآیند افزایش موجودی ساده ، سریع و امن است و پس از پرداخت می‌توانید " .
            "به راحتی از تمامی خدمات ربات استفاده کنید ✔️",

        'wallet' => "کیف پول 💰\n\n" .
            "موجودی شما:  {balance} تومان 💸\n" .
            "باقی مانده سقف مجاز افزایش موجودی تومانی امروز:  {remaining} 🚀\n\n" .
            'برای افزایش موجودی خود روی دکمه زیر کلیک کنید 👇',

        'toman_payment' => "💳 پرداخت تومانی \n\n" .
            "ابتدا مبلغی که قصد دارید به موجودی خود اضافه کنید را وارد کنید.\n\n\n" .
            "📱 باقیمانده سقف مجاز پرداخت تومانی امروز :  {remaining} تومان\n\n\n" .
            '‼️ توجه کنید که مبلغ واریزی نباید بیشتر از مبلغ سقف مجاز باشد ، ' .
            'در غیر اینصورت حساب شما فقط در حد سقف مجاز شارژ خواهد شد ' .
            'و مسئولیت آن به عهده خودتان است ❌',

        'receipt_prompt' => '💳 رسید خود را در قالب عکس ارسال کنید',
        'receipt_sent' => "✅ درخواست افزایش موجودی شما با موفقیت ارسال شد\n\n" .
            "پس از تایید نهایی توسط ادمین، موجودی حساب شما به مبلغی که تعیین کرده اید شارژ میشود",

        'card_details' => "💸 مبلغ واریزی:   {amount} تومان\n\n" .
            "💼 شماره کارت جهت واریز :\n\n" .
            "<code>{card_number}</code>\n\n" .
            "👤 به نام: {card_holder}\n\n" .
            "⚡️ لطفا پس از واریز پول،  «دکمه ارسال رسید» را بزنید و سپس " .
            "رسید پرداخت را بصورت عکس ارسال کنید\n\n" .
            "❗️توجه: چنانچه رسید بصورت متن بود از آن اسکرین شات بگیرید و اسکرین شات را ارسال کنید.\n\n" .
            '❗️در صورت مغایرت مبلغ تعیین شده با مبلغ واریز شده به کارت مسئولیت آن به عهده خودتان است.',

        'approved' => "🔥 مشتری عزیز\n\n✔️ رسید افزایش موجودی شما با موفقیت تایید شد\nموجودی شما:  {balance} تومان",
        'rejected' => "❌ متاسفانه درخواست افزایش موجودی شما رد شد.\n\nدلیل: {reason}",
        'daily_limit_exceeded' => "❌ مبلغی که وارد کردی بیشتر از سقف مجاز باقی‌مونده امروزته.\n\n" .
            "📱 سقف مجاز باقی‌مونده امروز شما:  {remaining} تومان\n\n" .
            'لطفا مبلغ کمتر یا مساوی همین مقدار رو وارد کن.',
        'daily_limit_exceeded_zero' => "❌ سقف مجاز شارژ تومانی امروز شما تموم شده.\n\nلطفا فردا دوباره تلاش کن یا با پشتیبانی در ارتباط باش.",

        'track' => "📦 پیگیری سفارش\n\n\n" .
            "🔢 در صورتی که کد پیگیری محصول خود را دارید دکمه زیر را فشار دهید\n" .
            "‼️ برای اطلاع از وضعیت سفارش خود باید کد پیگیری داشته باشید",
        'track_ask_code' => '🔢 کد پیگیری سفارشتان را ارسال کنید 🔍',
        'track_not_found' => "❌ کد پیگیری یافت نشد.\n\nلطفا کد پیگیری صحیح رو دوباره ارسال کن.",

        'order_status' => "🔢 مشخصات سفارش \n\n" .
            "📦 نوع سفارش: {label} {emoji}\n" .
            "📊 تعداد: {qty}\n" .
            "💼 قیمت:  {price}\n" .
            "📱 وضعیت: {status}\n\n\n" .
            "{extra}\n" .
            "🗓️ تاریخ و ساعت: {date}",

        'support' => 'لطفاً یکی از گزینه‌های زیر را انتخاب کنید تا با پشتیبانی ارتباط بگیرید 👇',
        'support_indirect' => '💬 پیام خود را برای پشتیبانی ارسال کنید 📞',
        'support_sent_confirm' => '💬 پیام شما به پشتیبانی ارسال شد  و  در اسرع وقت به آن پاسخ داده خواهد شد✅',

        'referral' => "👥 زیرمجموعه‌گیری ( Referral System )\n\n" .
            "🔗 لینک اختصاصی شما :\n{link}\n\n" .
            "👥 زیرمجموعه‌ها : {referrals} نفر\n" .
            "💰 درآمد شما :  {discount} تومان\n" .
            "🏅 امتیاز رفرال : {score}\n\n" .
            "❗️ توضیحات سیستم رفرال :\n\n" .
            "• با ارسال لینک اختصاصی خود ، دوستانتان را به ربات دعوت کنید.\n\n" .
            "• هر زمان زیرمجموعه‌ی شما خریدی انجام دهد، {commission_percent}٪ از مبلغ " .
            "آن خرید به موجودی تخفیف شما اضافه می‌شود.\n\n" .
            "• اگر در تایم‌های اعلام‌شده توسط ادمین زیرمجموعه بگیرید ، به ازای هر دعوت " .
            "1 امتیاز به امتیاز رفرال شما اضافه می‌شود.\n\n" .
            "• تایم‌ها از طریق اطلاع‌رسانی ربات و کانال تلگرام @{trust_channel} اعلام می‌شوند\n\n" .
            "• درصورت داشتن هر {points_required} امتیاز " .
            "شما قادر به دریافت یک جایزه هستید\n\n" .
            '(جایزه : گیفت تدی یا قلب 15 استارزی به انتخاب خودتان)',

        'referral_notification' => "🎉 تبریک! یک زیرمجموعه جدید داری!\n\n" .
            "👤 کاربر «{invited_name}» با لینک دعوت شما عضو ربات شد.\n\n{point_line}" .
            "\n━━━━━━━━━━━━━━━━━\n" .
            "👥 کل زیرمجموعه‌های شما: {referrals} نفر\n" .
            "💸 موجودی تخفیف شما: {discount} تومان\n" .
            "🏅 امتیاز رفرال شما: {score}\n\n" .
            "💡 هر خریدی که زیرمجموعه‌تون انجام بده، {commission_percent}٪ " .
            'از مبلغ خرید به موجودی تخفیف شما اضافه میشه.',

        'referral_reward_select' => '🎁 یکی از گیفت‌های زیر رو به عنوان جایزه رفرال انتخاب کن:',
        'referral_reward_invoice' => "🎁 فاکتور جایزه رفرال\n\n" .
            "💫 جایزه: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "🏅 امتیاز مصرفی: {points_required}\n" .
            "💰 مبلغ قابل پرداخت: رایگان 🎁\n\n" .
            "🍾 در صورتی که جزئیات بالا مورد تأیید شماست \n\n" .
            "روی دکمه «تأیید ✅» کلیک کنید.",

        'referral_reward_success' => "✅ جایزه رفرال شما با موفقیت ثبت شد!\n\n" .
            "🎁 جایزه: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💰 مبلغ پرداختی: رایگان 🎁\n" .
            "🔑 کد پیگیری سفارش : {order_id}\n\n" .
            "⏳ سفارش شما در حال پردازش است و به‌زودی وضعیت آن از طریق ربات اطلاع‌رسانی خواهد شد.\n\n" .
            "🙏 از همراهی شما سپاسگزاریم.",

        'referral_reward_done' => "✅ جایزه رفرال شما با موفقیت انجام شد 🎉\n\n" .
            "☝️اطلاعات سفارش:\n\n" .
            "ℹ️ نوع خدمات: جایزه رفرال\n" .
            "🎁 گیفت: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {username}\n" .
            "💳 مبلغ نهایی پرداخت شده: رایگان 🎁\n" .
            "🔓 کد پیگیری سفارش: {order_id}\n\n" .
            "😊 از همراهی شما سپاسگزاریم.",

        'admin_premium_order' => "🛒 سفارش جدید - پرمیوم تلگرام\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n" .
            "💫 پلن: {plan} ماهه\n" .
            "📎 یوزر دریافت‌کننده: {buy_username}\n" .
            "💰 مبلغ پرداخت‌شده: {price} تومان\n" .
            "🔢 کد پیگیری: {order_id}",

        'admin_stars_order' => "🛒 سفارش جدید - استارز تلگرام\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n" .
            "⭐️ تعداد استارز: {count}\n" .
            "📎 یوزر دریافت‌کننده: {buy_username}\n" .
            "💰 مبلغ پرداخت‌شده: {price} تومان\n" .
            "🔢 کد پیگیری: {order_id}",

        'admin_gift_order' => "🛒 سفارش جدید - گیفت استارز\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n" .
            "🎁 گیفت: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {buy_username}\n" .
            "🔒 هاید: {hide}\n" .
            "💬 کامنت: <code>{comment}</code>\n" .
            "💰 مبلغ پرداخت‌شده: {price} تومان\n" .
            "🔢 کد پیگیری: {order_id}",

        'admin_ton_order' => "🛒 سفارش جدید - خرید ارز تون\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n" .
            "💫 مقدار: {amount} TON\n" .
            "🏦 آدرس ولت:\n<code>{wallet}</code>\n" .
            "💬 ممو: {memo}\n" .
            "💰 مبلغ پرداخت‌شده: {price} تومان\n" .
            "🔢 کد پیگیری: {order_id}",

        'admin_support_notify' => "📨 پیام جدید پشتیبانی\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n\n" .
            "💬 متن پیام:\n{message}",

        'admin_referral_reward_order' => "🎁 سفارش جدید - جایزه رفرال (رایگان)\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n" .
            "🎁 گیفت: {gift}\n" .
            "📎 یوزر دریافت‌کننده: {buy_username}\n" .
            "🔢 کد پیگیری: {order_id}",

        'admin_topup_caption' => "🧾 درخواست افزایش موجودی جدید\n\n" .
            "👤 کاربر: {user_name} ({username_at})\n" .
            "🆔 آیدی عددی: {user_id}\n" .
            "💸 مبلغ: {amount} تومان\n" .
            "🔢 شماره درخواست: {req_id}",

        'report' => "🛍 خرید موفق ✅\n\n" .
            "👤 خریدار: {buyer_name}\n" .
            "🛒 سفارش : {label} {emoji}\n" .
            "⛏️ تعداد: {qty}\n" .
            "💰 مبلغ پرداخت شده :  {price} \n\n" .
            "⏰ {date} \n" .
            "📱 @{bot_username}",

        'broadcast_message' => "👤 پیام مدیریت به تمام کاربران\n\n{text}",

        'admin_ask_stars_price' => '✏️ قیمت جدید هر ۱ استارز رو به تومان وارد کن:',
        'admin_ask_ton_price' => '✏️ قیمت جدید هر {min_ton} TON رو به تومان وارد کن:',
        'admin_ask_premium_price' => '✏️ قیمت جدید پلن {plan} ماهه رو به تومان وارد کن:',
        'admin_ask_gift_price' => '✏️ قیمت جدید {gift} رو به تومان وارد کن:',
        'admin_ask_daily_limit' => "✏️ سقف مجاز شارژ روزانه جدید رو به تومان وارد کن:\n\n(سقف فعلی: {limit} تومان)",
        'admin_ask_profit' => '✏️ درصد سود جدید {product} رو وارد کن (فقط عدد، بدون %):',
        'admin_numeric_error' => 'لطفا فقط عدد وارد کن.',
        'admin_positive_number_error' => 'لطفا فقط یک عدد مثبت وارد کن.',
        'admin_positive_percent_error' => 'لطفا فقط یک عدد مثبت وارد کن (مثال: 10).',
    ];
}

function default_buttons(): array {
    return [
        'menu_buy' => '🛒 خرید محصول', 'menu_topup' => '👛 شارژ موجودی', 'menu_referral' => '🔗 لینک دعوت من',
        'menu_account' => '👤 حساب کاربری', 'menu_track' => '📋 پیگیری سفارش', 'menu_support' => '📞 پشتیبانی',
        'menu_trust' => '🔽 چطور میتوانم اعتماد کنم؟', 'menu_admin' => '🛠 پنل ادمین',
        'btn_back' => '🔙 بازگشت', 'btn_back_to_menu' => '🔙 بازگشت به منو', 'btn_self' => '👤 برای خودم',
        'btn_yes_short' => '✅ بله', 'btn_no_short' => '❌ خیر', 'btn_confirm' => 'تأیید ✅', 'btn_cancel' => '❌ کنسل',
        'btn_card_to_card' => '💳 کارت به کارت', 'btn_discount_apply' => '💸 اعمال تخفیف', 'btn_discount_remove' => '❌ لغو تخفیف',
        'btn_join_channel' => '📢 عضویت در کانال', 'btn_check_membership' => '✅ عضو شدم',

        'btn_premium' => '💎 پرمیوم تلگرام', 'btn_plan_3' => '3 ماه', 'btn_plan_6' => '6 ماه', 'btn_plan_12' => '12 ماه',
        'btn_stars' => '⭐️ استارز تلگرام',
        'btn_gift_stars' => '🎀 گیفت استارز', 'btn_gift_nft' => '🖼 گیفت NFT', 'btn_gift_comment_free' => '✍️ عادی (رایگان)',
        'btn_gift_hide_on' => '🔓 هاید کردن', 'btn_gift_hide_off' => '🔒 لغو هاید بودن',
        'btn_gift_comment_set' => '💬 تنظیم کامنت', 'btn_gift_comment_del' => '🗑 حذف کامنت',
        'btn_buy_ton' => '💱 خرید ارز تون', 'btn_ton_short' => '💱 ارز تون', 'btn_ton_memo_yes' => '✅ بله، کامنت دارم', 'btn_ton_memo_skip' => '❌ رد کردن',
        'btn_wallet_increase' => 'افزایش موجودی', 'btn_toman_payment' => '💳 پرداخت تومانی', 'btn_send_receipt' => '📨 ارسال رسید',
        'btn_admin_approve' => '✅ تایید', 'btn_admin_reject' => '❌ رد',
        'btn_track_have_code' => '🔢 کد پیگیری محصول دارم', 'btn_admin_done' => '✅ انجام شد', 'btn_report_buy' => '🛍 حالا اقدام به خرید کن',
        'btn_support_direct' => '☎️ ارتباط مستقیم', 'btn_support_indirect' => '💬 ارتباط غیر مستقیم', 'btn_admin_reply' => '✍️ پاسخ دادن',
        'btn_referral_claim' => '🎁 دریافت جایزه',

        'btn_admin_broadcast' => '📢 ارسال پیام به تمام کاربران', 'btn_admin_view_users' => '👥 دیدن کاربران ربات',
        'btn_admin_price_menu' => '💰 تنظیم قیمت', 'btn_admin_profit_menu' => '📈 تغییر درصد سود',
        'btn_admin_daily_limit' => '📊 تنظیم سقف شارژ روزانه', 'btn_admin_reset' => '🗑 ریست کامل داده‌های ربات',
        'btn_admin_reset_yes' => '✅ بله، همه چیز پاک بشه', 'btn_admin_reset_no' => '❌ نه، برگرد',
        'btn_admin_edit_price' => '✏️ تغییر قیمت', 'btn_admin_edit_daily_limit' => '✏️ تنظیم سقف روزانه',
        'btn_admin_edit_profit' => '✏️ تنظیم سود', 'btn_no_plain' => '❌ نه',
        'btn_admin_edit_texts' => '✏️ ویرایش متن‌های ربات', 'btn_admin_edit_buttons' => '✏️ ویرایش نام دکمه‌ها',
        'btn_style_primary' => '🔵 آبی', 'btn_style_success' => '🟢 سبز', 'btn_style_danger' => '🔴 قرمز', 'btn_default' => '⚪️ پیش‌فرض',
    ];
}

/* ===================== TEXT / BUTTON STORE HELPERS ===================== */

function esc_html(string $text): string {
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Converts a message's raw text + Bot API entities (bold/italic/.../
// custom_emoji) into an HTML string safe for parse_mode=HTML, encoding
// custom_emoji as Telegram's non-standard <tg-emoji emoji-id="..."> tag -
// the only way to keep premium emoji working, since `entities` and
// parse_mode can't be sent together. Once converted, the result is a plain
// HTML string; no entities array needs to travel alongside it again.
function tg_entities_to_html(string $text, array $entities): string {
    if ($entities === []) return esc_html($text);

    $utf16 = @mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    if ($utf16 === false) return esc_html($text);
    $unitLen = (int) (strlen($utf16) / 2);

    $ents = [];
    foreach ($entities as $e) {
        $off = (int) ($e['offset'] ?? -1);
        $len = (int) ($e['length'] ?? 0);
        if ($off < 0 || $len <= 0 || $off + $len > $unitLen) continue;
        $ents[] = [
            'type' => (string) ($e['type'] ?? ''),
            'start' => $off,
            'end' => $off + $len,
            'url' => (string) ($e['url'] ?? ''),
            'emoji_id' => (string) ($e['custom_emoji_id'] ?? ''),
            'language' => (string) ($e['language'] ?? ''),
        ];
    }
    if (!$ents) return esc_html($text);

    usort($ents, function (array $a, array $b): int {
        if ($a['start'] !== $b['start']) return $a['start'] <=> $b['start'];
        return $b['end'] <=> $a['end'];
    });

    $nodes = [];
    foreach ($ents as $e) $nodes[] = ['e' => $e, 'children' => []];

    $roots = [];
    $stack = [];
    foreach ($nodes as $i => $node) {
        $start = $node['e']['start'];
        while ($stack && $nodes[end($stack)]['e']['end'] <= $start) array_pop($stack);
        if ($stack) {
            $nodes[end($stack)]['children'][] = $i;
        } else {
            $roots[] = $i;
        }
        $stack[] = $i;
    }

    $slice = function (int $start, int $end) use ($utf16): string {
        $start = max(0, $start);
        $end = max($start, $end);
        $bytes = substr($utf16, $start * 2, ($end - $start) * 2);
        $out = @mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
        return $out === false ? '' : $out;
    };

    $tagFor = function (array $e): array {
        switch ($e['type']) {
            case 'bold': return ['<b>', '</b>'];
            case 'italic': return ['<i>', '</i>'];
            case 'underline': return ['<u>', '</u>'];
            case 'strikethrough': return ['<s>', '</s>'];
            case 'spoiler': return ['<tg-spoiler>', '</tg-spoiler>'];
            case 'code': return ['<code>', '</code>'];
            case 'pre':
                $lang = trim($e['language']);
                if ($lang !== '') return ['<pre><code class="language-' . esc_html($lang) . '">', '</code></pre>'];
                return ['<pre>', '</pre>'];
            case 'blockquote': return ['<blockquote>', '</blockquote>'];
            case 'expandable_blockquote': return ['<blockquote expandable>', '</blockquote>'];
            case 'text_link': return ['<a href="' . esc_html($e['url']) . '">', '</a>'];
            case 'custom_emoji': return ['<tg-emoji emoji-id="' . esc_html($e['emoji_id']) . '">', '</tg-emoji>'];
            default: return ['', ''];
        }
    };

    $render = function (array $indices, int $rangeStart, int $rangeEnd) use (&$render, $nodes, $slice, $tagFor): string {
        $out = '';
        $cursor = $rangeStart;
        foreach ($indices as $idx) {
            $node = $nodes[$idx];
            $s = $node['e']['start'];
            $en = $node['e']['end'];
            if ($s > $cursor) $out .= esc_html($slice($cursor, $s));
            [$open, $close] = $tagFor($node['e']);
            $out .= $open . $render($node['children'], $s, $en) . $close;
            $cursor = max($cursor, $en);
        }
        if ($cursor < $rangeEnd) $out .= esc_html($slice($cursor, $rangeEnd));
        return $out;
    };

    return $render($roots, 0, $unitLen);
}

function text_default(string $slug): string {
    static $defaults = null;
    if ($defaults === null) $defaults = default_texts();
    return $defaults[$slug] ?? '';
}

// Stored/default text templates are always already-valid HTML (admin edits
// are baked into HTML via tg_entities_to_html() at save time; hardcoded
// defaults are plain strings with no special HTML chars, or the handful
// that intentionally embed literal <code>/<b> tags).
function text_raw(string $slug): string {
    $store = $GLOBALS['STORE']['texts'] ?? [];
    return $store[$slug]['value'] ?? text_default($slug);
}

// Renders a stored/default HTML template with {token} substitution. Every
// dynamic value is HTML-escaped here so user-controlled input (wallet memo,
// comment, username, ...) can never break the surrounding markup or inject
// tags; the template itself is trusted HTML and is left untouched. Always
// send the result with parse_mode => 'HTML'.
function T(string $slug, array $vars = []): string {
    $result = text_raw($slug);
    foreach ($vars as $token => $value) {
        $result = str_replace($token, esc_html((string) $value), $result);
    }
    return $result;
}

function button_default(string $slug): string {
    static $defaults = null;
    if ($defaults === null) $defaults = default_buttons();
    if (isset($defaults[$slug])) return $defaults[$slug];
    if (str_starts_with($slug, 'gift_item_')) {
        $key = substr($slug, strlen('gift_item_'));
        if (isset(GIFTS_META[$key])) return gift_button_default($key);
    }
    if (str_starts_with($slug, 'admin_gift_price_btn_')) {
        $key = substr($slug, strlen('admin_gift_price_btn_'));
        if (isset(GIFTS_META[$key])) return admin_gift_price_button_default($key);
    }
    return $slug;
}

function B(string $slug): string {
    $store = $GLOBALS['STORE']['buttons'] ?? [];
    return $store[$slug]['label'] ?? button_default($slug);
}

function BS(string $slug): ?string {
    $store = $GLOBALS['STORE']['buttons'] ?? [];
    return $store[$slug]['style'] ?? null;
}

function BI(string $slug): ?string {
    $store = $GLOBALS['STORE']['buttons'] ?? [];
    return $store[$slug]['icon_custom_emoji_id'] ?? null;
}

function ibtn(string $slug, ?string $cb = null, ?string $url = null): array {
    return btn(B($slug), $cb, $url, BS($slug), BI($slug));
}

function rbtn_s(string $slug): array {
    return rbtn(B($slug), BS($slug));
}

function resolve_menu_button(string $text): ?string {
    foreach (BUTTON_CATEGORIES['welcome'] as $slug) {
        if (str_starts_with($slug, 'menu_') && B($slug) === $text) return $slug;
    }
    return null;
}

// Extracts entities from an admin's edit message verbatim (offsets already
// correct for that exact text) for storage alongside a text/button edit.
function capture_entities(array $msg): ?array {
    $entities = $msg['entities'] ?? null;
    return (is_array($entities) && $entities) ? $entities : null;
}

function first_custom_emoji_id(?array $entities): ?string {
    if (!$entities) return null;
    foreach ($entities as $e) {
        if (($e['type'] ?? '') === 'custom_emoji' && !empty($e['custom_emoji_id'])) return (string) $e['custom_emoji_id'];
    }
    return null;
}

function all_text_slugs(): array {
    $out = [];
    foreach (TEXT_CATEGORIES as $slugs) $out = array_merge($out, $slugs);
    return $out;
}

function all_button_slugs(): array {
    $out = [];
    foreach (BUTTON_CATEGORIES as $cat => $slugs) $out = array_merge($out, category_button_slugs($cat));
    return $out;
}

function text_category_of(string $slug): ?string {
    foreach (TEXT_CATEGORIES as $cat => $slugs) if (in_array($slug, $slugs, true)) return $cat;
    return null;
}

function button_category_of(string $slug): ?string {
    foreach (BUTTON_CATEGORIES as $cat => $slugs) if (in_array($slug, category_button_slugs($cat), true)) return $cat;
    return null;
}

function apply_text_edit(array &$DATA, string $slug, string $html): void {
    $DATA['texts'][$slug] = ['value' => $html];
}

function reset_text(array &$DATA, string $slug): void {
    unset($DATA['texts'][$slug]);
}

function apply_button_edit(array &$DATA, string $slug, string $label, ?array $entities): void {
    // Telegram always renders icon_custom_emoji_id BEFORE the button text, with
    // no way to move it after - so a label edit must never auto-set it (that
    // silently duplicated whatever emoji was typed and forced it to the front).
    // The label text itself already carries any emoji exactly where it was
    // typed - front, back, or anywhere - with no separate icon involved.
    $existing = $DATA['buttons'][$slug] ?? [];
    $DATA['buttons'][$slug] = [
        'label' => $label,
        'style' => $existing['style'] ?? null,
        'icon_custom_emoji_id' => $existing['icon_custom_emoji_id'] ?? null,
    ];
}

function set_button_style(array &$DATA, string $slug, ?string $style): void {
    $existing = $DATA['buttons'][$slug] ?? ['label' => button_default($slug), 'icon_custom_emoji_id' => null];
    $existing['style'] = $style;
    $DATA['buttons'][$slug] = $existing;
}

function set_button_icon(array &$DATA, string $slug, ?string $icon_custom_emoji_id): void {
    $existing = $DATA['buttons'][$slug] ?? ['label' => button_default($slug), 'style' => null];
    $existing['icon_custom_emoji_id'] = $icon_custom_emoji_id;
    $DATA['buttons'][$slug] = $existing;
}

function reset_button(array &$DATA, string $slug): void {
    unset($DATA['buttons'][$slug]);
}

function get_layout_columns(array &$DATA, string $group, int $default): int {
    $val = (int) ($DATA['layout'][$group] ?? $default);
    return in_array($val, [1, 2, 3], true) ? $val : $default;
}

/* ===================== DYNAMIC TEXT HELPERS ===================== */

const ADMIN_BROADCAST_ASK_TEXT = '📢 متن پیامی که می‌خوای برای تمام کاربران ربات ارسال بشه رو بفرست:';
const ADMIN_VIEW_USERS_EMPTY_TEXT = '👥 هنوز هیچ کاربری ربات رو استارت نکرده.';
const ADMIN_PRICE_MENU_TEXT = "💰 تنظیم قیمت محصولات\n\nمحصول مورد نظر برای تغییر قیمت رو انتخاب کن:";
const ADMIN_PREMIUM_PLAN_SELECT_TEXT = '✏️ قیمت کدوم پلن پرمیوم رو می‌خوای تغییر بدی؟';
const ADMIN_GIFT_PRICE_LIST_TEXT = '🎀 گیفت مورد نظر برای مشاهده/تغییر قیمت رو انتخاب کن:';

function fmt($n): string { return number_format((float) $n, 0, '.', ','); }

function stars_buy_text(): string { return T('stars_buy', ['{min}' => STARS_MIN]); }
function stars_min_error_text(): string { return T('stars_min_error', ['{min}' => STARS_MIN]); }

function ton_buy_text(array &$DATA): string {
    refresh_ton_price_if_stale($DATA);
    return T('ton_buy', ['{price}' => fmt($DATA['ton_price']), '{min}' => MIN_TON]);
}

function join_text(): string { return T('join', ['{channel}' => REQUIRED_CHANNEL]); }
function trust_text(): string { return T('trust', ['{channel}' => TRUST_CHANNEL]); }
function confirm_username_text(string $username): string { return T('confirm_username', ['{username}' => $username]); }

function premium_invoice_text($plan, $price, $username, $disc, $final): string {
    return T('premium_invoice', ['{plan}' => $plan, '{price}' => fmt($price), '{username}' => $username,
        '{discount}' => fmt($disc), '{max_discount}' => fmt(min($disc, $price)), '{final}' => fmt($final)]);
}

function premium_success_text($plan, $username, $price, $order_id): string {
    return T('premium_success', ['{plan}' => $plan, '{username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function premium_done_text($plan, $username, $price, $order_id): string {
    return T('premium_done', ['{plan}' => $plan, '{username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function admin_premium_text($user, $plan, $username, $price, $order_id): string {
    return T('admin_premium_order', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{plan}' => $plan, '{buy_username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function ton_invoice_text($amount, $wallet, $memo, $price, $disc, $final): string {
    return T('ton_invoice', ['{amount}' => $amount, '{wallet}' => $wallet, '{memo}' => $memo,
        '{price}' => fmt($price), '{discount}' => fmt($disc), '{max_discount}' => fmt(min($disc, $price)), '{final}' => fmt($final)]);
}

function ton_success_text($amount, $wallet, $price, $order_id): string {
    return T('ton_success', ['{amount}' => $amount, '{wallet}' => $wallet, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function ton_done_text($amount, $wallet, $price, $order_id): string {
    return T('ton_done', ['{amount}' => $amount, '{wallet}' => $wallet, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function stars_invoice_text($count, $username, $price, $disc, $final): string {
    return T('stars_invoice', ['{count}' => $count, '{username}' => $username,
        '{price}' => fmt($price), '{discount}' => fmt($disc), '{max_discount}' => fmt(min($disc, $price)), '{final}' => fmt($final)]);
}

function stars_success_text($count, $username, $price, $order_id): string {
    return T('stars_success', ['{count}' => $count, '{username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function stars_done_text($count, $username, $price, $order_id): string {
    return T('stars_done', ['{count}' => $count, '{username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function admin_stars_text($user, $count, $username, $price, $order_id): string {
    return T('admin_stars_order', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{count}' => $count, '{buy_username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function gift_label(string $key): string {
    $g = GIFTS_META[$key];
    return "گیفت {$g['name']} ({$g['stars']} ⭐️)";
}

function gift_invoice_text($key, $username, $hidden, $comment, $price, $disc, $final): string {
    return T('gift_invoice', ['{gift}' => gift_label($key), '{username}' => $username,
        '{hide}' => $hidden ? '✅ بله' : '❌ خیر', '{comment}' => $comment ? $comment : 'ندارد',
        '{price}' => fmt($price), '{discount}' => fmt($disc), '{max_discount}' => fmt(min($disc, $price)), '{final}' => fmt($final)]);
}

function gift_success_text($key, $username, $price, $order_id): string {
    return T('gift_success', ['{gift}' => gift_label($key), '{username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function gift_done_text($key, $username, $price, $order_id): string {
    return T('gift_done', ['{gift}' => gift_label($key), '{username}' => $username, '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function admin_gift_text($user, $key, $username, $hidden, $comment, $price, $order_id): string {
    return T('admin_gift_order', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{gift}' => gift_label($key), '{buy_username}' => $username,
        '{hide}' => $hidden ? '✅ بله' : '❌ خیر', '{comment}' => $comment ? $comment : 'ندارد', '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function admin_support_text($user, string $message_text): string {
    return T('admin_support_notify', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{message}' => $message_text]);
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
    return T('report', ['{buyer_name}' => $buyer_name, '{label}' => $label, '{emoji}' => $emoji, '{qty}' => $qty,
        '{price}' => price_display($order), '{date}' => persian_now_str(), '{bot_username}' => BOT_USERNAME]);
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

    return T('order_status', ['{label}' => $label, '{emoji}' => $emoji, '{qty}' => $qty,
        '{price}' => price_display($order), '{status}' => $status_label, '{extra}' => $extra, '{date}' => $date_display]);
}

function admin_panel_text(array &$DATA): string {
    $status = $DATA['bot_enabled'] ? '🟢 روشن' : '🔴 خاموش';
    $ref_status = $DATA['referral_points_enabled'] ? '🟢 فعال' : '🔴 غیرفعال';
    return "🛠 پنل مدیریت ربات\n\n" .
        "وضعیت فعلی ربات: {$status}\n" .
        "وضعیت جایزه دعوت: {$ref_status}\n\n" .
        "از گزینه‌های زیر یکی رو انتخاب کن:";
}

function admin_layout_menu_text(): string { return "🧩 چیدمان دکمه‌های شیشه‌ای\n\nگروه مورد نظر رو انتخاب کن تا تعداد ستون‌هاش رو تغییر بدی:"; }

function admin_layout_detail_text(array &$DATA, string $key): string {
    $meta = LAYOUT_GROUPS[$key];
    $cols = get_layout_columns($DATA, $key, $meta['default']);
    return "{$meta['label']}\n\n📐 چیدمان فعلی: {$cols} دکمه در هر ردیف\n\nتعداد ستون جدید رو انتخاب کن:";
}

function broadcast_confirm_text(string $text): string { return "📢 متن زیر برای تمام کاربران ربات ارسال خواهد شد:\n\n{$text}\n\nآیا مطمئن هستید؟"; }
function broadcast_message_text(string $text): string { return T('broadcast_message', ['{text}' => $text]); }
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
function admin_ask_stars_price_text(): string { return T('admin_ask_stars_price'); }

function admin_ton_price_text(array &$DATA): string {
    $price_per_tenth = (int) round($DATA['ton_price'] * MIN_TON);
    return "💱 قیمت فعلی هر " . MIN_TON . " TON:\n\n💰 " . fmt($price_per_tenth) . ' تومان';
}
function admin_ask_ton_price_text(): string { return T('admin_ask_ton_price', ['{min_ton}' => MIN_TON]); }

function admin_premium_price_text(array &$DATA): string {
    $p = $DATA['premium_prices'];
    return "💎 قیمت‌های فعلی پرمیوم تلگرام:\n\n" .
        "🔹 3 ماهه:  " . fmt($p['3']) . " تومان\n" .
        "🔹 6 ماهه:  " . fmt($p['6']) . " تومان\n" .
        "🔹 12 ماهه:  " . fmt($p['12']) . ' تومان';
}
function admin_ask_premium_price_text(string $plan): string { return T('admin_ask_premium_price', ['{plan}' => $plan]); }

function admin_gift_price_detail_text(array &$DATA, string $key): string {
    return gift_label($key) . "\n\n💰 قیمت فعلی: " . fmt($DATA['gifts_prices'][$key]) . ' تومان';
}
function admin_ask_gift_price_text(string $key): string { return T('admin_ask_gift_price', ['{gift}' => gift_label($key)]); }

function admin_daily_limit_text(array &$DATA): string {
    return "⚙️ تنظیم سقف شارژ روزانه\n\n" .
        "💳 سقف مجاز فعلی: " . fmt($DATA['daily_limit']) . " تومان\n\n" .
        'این مقدار حداکثر شارژ هر کاربر در روز است.';
}
function admin_ask_daily_limit_text(array &$DATA): string {
    return T('admin_ask_daily_limit', ['{limit}' => fmt($DATA['daily_limit'])]);
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

function admin_ask_profit_text(string $product_fa): string { return T('admin_ask_profit', ['{product}' => $product_fa]); }

function admin_ton_text($user, $amount, $wallet, $memo, $price, $order_id): string {
    return T('admin_ton_order', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{amount}' => $amount, '{wallet}' => $wallet, '{memo}' => $memo,
        '{price}' => fmt($price), '{order_id}' => $order_id]);
}

function insufficient_text(int $shortfall): string { return T('insufficient', ['{shortfall}' => fmt($shortfall)]); }

function wallet_text(int $balance, int $remaining): string {
    return T('wallet', ['{balance}' => fmt($balance), '{remaining}' => fmt($remaining)]);
}

function toman_payment_text(int $remaining): string { return T('toman_payment', ['{remaining}' => fmt($remaining)]); }

function daily_limit_exceeded_text(int $remaining): string {
    if ($remaining <= 0) return T('daily_limit_exceeded_zero');
    return T('daily_limit_exceeded', ['{remaining}' => fmt($remaining)]);
}

function card_details_text(int $amount): string {
    return T('card_details', ['{amount}' => fmt($amount), '{card_number}' => CARD_NUMBER, '{card_holder}' => CARD_HOLDER]);
}

function admin_topup_caption($user, int $amount, string $req_id): string {
    return T('admin_topup_caption', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{amount}' => fmt($amount), '{req_id}' => $req_id]);
}

function approved_text(int $balance): string { return T('approved', ['{balance}' => fmt($balance)]); }
function rejected_text(string $reason): string { return T('rejected', ['{reason}' => $reason]); }

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
    return T('referral', ['{link}' => $link, '{referrals}' => $u['referrals'], '{discount}' => fmt($u['discount_balance']),
        '{score}' => $u['score'], '{commission_percent}' => REFERRAL_COMMISSION_PERCENT,
        '{points_required}' => REFERRAL_POINTS_REQUIRED, '{trust_channel}' => TRUST_CHANNEL]);
}

function referral_notification_text(string $invited_name, bool $point_awarded, int $total_referrals = 0, int $total_discount = 0, int $total_score = 0): string {
    $point_line = $point_awarded ? "🏅 1 امتیاز رفرال به امتیازهای شما اضافه شد.\n" : '';
    return T('referral_notification', ['{invited_name}' => $invited_name, '{point_line}' => $point_line,
        '{referrals}' => $total_referrals, '{discount}' => fmt($total_discount), '{score}' => $total_score,
        '{commission_percent}' => REFERRAL_COMMISSION_PERCENT]);
}

function referral_reward_select_text(): string { return T('referral_reward_select'); }

function referral_reward_invoice_text(string $key, string $username): string {
    return T('referral_reward_invoice', ['{gift}' => gift_label($key), '{username}' => $username, '{points_required}' => REFERRAL_POINTS_REQUIRED]);
}

function referral_reward_success_text(string $key, string $username, string $order_id): string {
    return T('referral_reward_success', ['{gift}' => gift_label($key), '{username}' => $username, '{order_id}' => $order_id]);
}

function admin_referral_reward_text($user, string $key, string $username, string $order_id): string {
    return T('admin_referral_reward_order', ['{user_name}' => display_name($user), '{username_at}' => username_at($user),
        '{user_id}' => $user['id'], '{gift}' => gift_label($key), '{buy_username}' => $username, '{order_id}' => $order_id]);
}

function referral_reward_done_text(string $key, string $username, string $order_id): string {
    return T('referral_reward_done', ['{gift}' => gift_label($key), '{username}' => $username, '{order_id}' => $order_id]);
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

// Bot API 9.4 style/icon_custom_emoji_id/custom_emoji only actually render
// for a bot owner with Telegram Premium; Telegram rejects the whole call
// otherwise, so every send that may carry them retries once with the
// fields stripped, logging just once per request.
function reply_markup_has_style(?array $markup): bool {
    if (!$markup) return false;
    $rows = $markup['inline_keyboard'] ?? $markup['keyboard'] ?? null;
    if (!$rows) return false;
    foreach ($rows as $row) {
        foreach ($row as $b) {
            if (isset($b['style']) || isset($b['icon_custom_emoji_id'])) return true;
        }
    }
    return false;
}

function strip_style_fields(array $markup): array {
    $key = isset($markup['inline_keyboard']) ? 'inline_keyboard' : (isset($markup['keyboard']) ? 'keyboard' : null);
    if ($key === null) return $markup;
    foreach ($markup[$key] as &$row) {
        foreach ($row as &$b) unset($b['style'], $b['icon_custom_emoji_id']);
        unset($b);
    }
    unset($row);
    return $markup;
}

function log_premium_fallback_once(): void {
    static $logged = false;
    if ($logged) return;
    $logged = true;
    error_log('Telegram rejected style/icon_custom_emoji_id/custom_emoji entities on this send - the bot owner likely does not have Telegram Premium (Bot API 9.4 restriction). Falling back to plain rendering.');
}

function send_message($chat_id, string $text, ?array $reply_markup = null, ?string $parse_mode = null, array $extra = []): ?array {
    $params = array_merge(['chat_id' => $chat_id, 'text' => $text], $extra);
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    if ($parse_mode !== null) $params['parse_mode'] = $parse_mode;
    $res = tg_api('sendMessage', $params);
    if ((!$res || empty($res['ok'])) && reply_markup_has_style($reply_markup)) {
        log_premium_fallback_once();
        if ($reply_markup !== null) $params['reply_markup'] = strip_style_fields($reply_markup);
        $res = tg_api('sendMessage', $params);
    }
    return $res;
}

function edit_message_text($chat_id, $message_id, string $text, ?array $reply_markup = null, ?string $parse_mode = null): ?array {
    if ($chat_id === null || $message_id === null) return null;
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'text' => $text];
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    if ($parse_mode !== null) $params['parse_mode'] = $parse_mode;
    $res = tg_api('editMessageText', $params);
    if ((!$res || empty($res['ok'])) && reply_markup_has_style($reply_markup)) {
        log_premium_fallback_once();
        $params['reply_markup'] = strip_style_fields($reply_markup);
        $res = tg_api('editMessageText', $params);
    }
    return $res;
}

function edit_message_caption($chat_id, $message_id, string $caption, ?array $reply_markup = null): ?array {
    $params = ['chat_id' => $chat_id, 'message_id' => $message_id, 'caption' => $caption];
    if ($reply_markup !== null) $params['reply_markup'] = $reply_markup;
    $res = tg_api('editMessageCaption', $params);
    if ((!$res || empty($res['ok'])) && reply_markup_has_style($reply_markup)) {
        log_premium_fallback_once();
        $params['reply_markup'] = strip_style_fields($reply_markup);
        $res = tg_api('editMessageCaption', $params);
    }
    return $res;
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
    $res = tg_api('sendPhoto', $params);
    if ((!$res || empty($res['ok'])) && reply_markup_has_style($reply_markup)) {
        log_premium_fallback_once();
        $params['reply_markup'] = strip_style_fields($reply_markup);
        $res = tg_api('sendPhoto', $params);
    }
    return $res;
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

/* ===================== TON PRICE (Nobitex) ===================== */

// Fetches the TON/Rial spot price from Nobitex and returns the base Toman
// price (Rial / 10, rounded to the nearest 100 Toman), or null on failure.
function fetch_ton_price_from_nobitex(): ?int {
    $ch = curl_init(NOBITEX_TON_STATS_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $res = curl_exec($ch);
    if ($res === false) {
        error_log('fetch_ton_price_from_nobitex curl error: ' . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $decoded = json_decode($res, true);
    $rial = $decoded['stats']['ton-rls']['latest'] ?? null;
    if (!is_numeric($rial)) {
        error_log('fetch_ton_price_from_nobitex: unexpected response: ' . substr((string) $res, 0, 300));
        return null;
    }
    return (int) round(((float) $rial) / 10 / 100) * 100;
}

// Lazy on-demand refresh: re-fetches only when the cache is stale (or
// $force). Never throws - a failed fetch just keeps serving the last
// cached base price so the purchase flow never breaks.
function refresh_ton_price_if_stale(array &$DATA, bool $force = false): void {
    $cache = $DATA['ton_price_cache'] ?? ['base' => $DATA['base_ton_price'], 'fetched_at' => 0];
    if (!$force && (time() - (int) ($cache['fetched_at'] ?? 0)) < TON_PRICE_CACHE_SECONDS) return;
    $fresh = fetch_ton_price_from_nobitex();
    if ($fresh === null) {
        $DATA['ton_price_cache'] = $cache; // keep last known value, just don't retry every request
        return;
    }
    $DATA['ton_price_cache'] = ['base' => $fresh, 'fetched_at' => time()];
    $DATA['base_ton_price'] = $fresh;
    recalc_prices($DATA);
}

/* ===================== KEYBOARDS ===================== */

function ikb(array $rows): array { return ['inline_keyboard' => $rows]; }
function btn(string $text, ?string $cb = null, ?string $url = null, ?string $style = null, ?string $icon_custom_emoji_id = null): array {
    $b = $url !== null ? ['text' => $text, 'url' => $url] : ['text' => $text, 'callback_data' => $cb];
    if ($style !== null) $b['style'] = $style;
    if ($icon_custom_emoji_id !== null) $b['icon_custom_emoji_id'] = $icon_custom_emoji_id;
    return $b;
}
function rkb(array $rows): array { return ['keyboard' => $rows, 'resize_keyboard' => true]; }
function rbtn(string $text, ?string $style = null): array {
    $b = ['text' => $text];
    if ($style !== null) $b['style'] = $style;
    return $b;
}

function gift_button_slug(string $key): string { return "gift_item_{$key}"; }
function gift_button_default(string $key): string { $g = GIFTS_META[$key]; return "{$g['emoji']} {$g['name']} (⭐️ {$g['stars']})"; }
function admin_gift_price_button_slug(string $key): string { return "admin_gift_price_btn_{$key}"; }
function admin_gift_price_button_default(string $key): string { $g = GIFTS_META[$key]; return "{$g['emoji']} {$g['name']}"; }

function main_kb(bool $is_admin = false): array {
    $rows = [
        [rbtn_s('menu_buy')],
        [rbtn_s('menu_topup'), rbtn_s('menu_referral')],
        [rbtn_s('menu_account')],
        [rbtn_s('menu_track'), rbtn_s('menu_support')],
        [rbtn_s('menu_trust')],
    ];
    if ($is_admin) $rows[] = [rbtn_s('menu_admin')];
    return rkb($rows);
}

function product_kb(array &$DATA): array {
    $buttons = [
        ibtn('btn_premium', 'product_premium'), ibtn('btn_stars', 'product_stars'),
        ibtn('btn_gift_stars', 'product_gift_stars'), ibtn('btn_buy_ton', 'product_buy_ton'),
        ibtn('btn_gift_nft', 'product_gift_nft'),
    ];
    $cols = get_layout_columns($DATA, 'products', LAYOUT_GROUPS['products']['default']);
    $rows = array_chunk($buttons, $cols);
    $rows[] = [ibtn('btn_back', 'back_to_welcome')];
    return ikb($rows);
}

function premium_kb(array &$DATA): array {
    $buttons = [ibtn('btn_plan_3', 'premium_3'), ibtn('btn_plan_6', 'premium_6'), ibtn('btn_plan_12', 'premium_12')];
    $cols = get_layout_columns($DATA, 'premium_plans', LAYOUT_GROUPS['premium_plans']['default']);
    $rows = array_chunk($buttons, $cols);
    $rows[] = [ibtn('btn_back', 'back_to_products')];
    return ikb($rows);
}

function ask_username_kb(): array {
    return ikb([[ibtn('btn_self', 'username_self')], [ibtn('btn_back', 'back_to_premium')]]);
}

function confirm_username_kb(): array { return ikb([[ibtn('btn_yes_short', 'confirm_yes'), ibtn('btn_no_short', 'confirm_no')]]); }

function invoice_kb(bool $discount_applied = false): array {
    $slug = $discount_applied ? 'btn_discount_remove' : 'btn_discount_apply';
    return ikb([
        [ibtn('btn_confirm', 'invoice_confirm')],
        [ibtn($slug, 'invoice_discount'), ibtn('btn_cancel', 'invoice_cancel')],
    ]);
}

function insufficient_kb(): array {
    return ikb([[ibtn('btn_card_to_card', 'topup_from_invoice')], [ibtn('btn_back_to_menu', 'back_to_products')]]);
}

function wallet_kb(): array { return ikb([[ibtn('btn_wallet_increase', 'wallet_increase')], [ibtn('btn_back', 'back_to_welcome')]]); }
function wallet_increase_kb(): array { return ikb([[ibtn('btn_toman_payment', 'topup_from_wallet')], [ibtn('btn_back', 'wallet_back')]]); }
function toman_kb(): array { return ikb([[ibtn('btn_back', 'topup_back')]]); }
function card_details_kb(): array { return ikb([[ibtn('btn_back', 'card_back'), ibtn('btn_send_receipt', 'send_receipt')]]); }
function receipt_prompt_kb(): array { return ikb([[ibtn('btn_back', 'receipt_back')]]); }
function admin_topup_kb(string $req_id): array { return ikb([[ibtn('btn_admin_approve', "topup_approve_{$req_id}"), ibtn('btn_admin_reject', "topup_reject_{$req_id}")]]); }
function admin_order_kb(string $order_id): array { return ikb([[ibtn('btn_admin_done', "order_done_{$order_id}")]]); }
function buy_product_kb(): array { return ikb([[ibtn('menu_buy', 'back_to_products')]]); }
function ton_back_kb(): array { return ikb([[ibtn('btn_back', 'back_to_products')]]); }
function ton_wallet_back_kb(): array { return ikb([[ibtn('btn_back', 'ton_back_to_amount')]]); }

function ton_memo_kb(): array {
    return ikb([
        [ibtn('btn_ton_memo_yes', 'ton_memo_yes')],
        [ibtn('btn_ton_memo_skip', 'ton_memo_skip')],
        [ibtn('btn_back', 'ton_memo_back')],
    ]);
}
function ton_memo_input_kb(): array { return ikb([[ibtn('btn_back', 'ton_memo_input_back')]]); }

function ton_invoice_kb(bool $discount_applied = false): array {
    $slug = $discount_applied ? 'btn_discount_remove' : 'btn_discount_apply';
    return ikb([
        [ibtn('btn_confirm', 'ton_invoice_confirm')],
        [ibtn($slug, 'ton_invoice_discount'), ibtn('btn_cancel', 'ton_invoice_cancel')],
    ]);
}

function stars_back_kb(): array { return ikb([[ibtn('btn_back', 'back_to_products')]]); }
function stars_min_error_kb(): array { return ikb([[ibtn('btn_back', 'back_to_products')]]); }
function ask_stars_username_kb(): array { return ikb([[ibtn('btn_self', 'stars_username_self')], [ibtn('btn_back', 'stars_back_to_amount')]]); }
function confirm_stars_username_kb(): array { return ikb([[ibtn('btn_yes_short', 'stars_confirm_yes'), ibtn('btn_no_short', 'stars_confirm_no')]]); }

function stars_invoice_kb(bool $discount_applied = false): array {
    $slug = $discount_applied ? 'btn_discount_remove' : 'btn_discount_apply';
    return ikb([
        [ibtn('btn_confirm', 'stars_invoice_confirm')],
        [ibtn($slug, 'stars_invoice_discount'), ibtn('btn_cancel', 'stars_invoice_cancel')],
    ]);
}

function gift_list_kb(array &$DATA): array {
    $buttons = [];
    foreach (array_keys(GIFTS_META) as $key) $buttons[] = ibtn(gift_button_slug($key), "gift_select_{$key}");
    $cols = get_layout_columns($DATA, 'gift_list', LAYOUT_GROUPS['gift_list']['default']);
    $rows = array_chunk($buttons, $cols);
    $rows[] = [ibtn('btn_back', 'back_to_products')];
    return ikb($rows);
}

function ask_gift_username_kb(): array { return ikb([[ibtn('btn_self', 'gift_username_self')], [ibtn('btn_back', 'gift_back_to_list')]]); }
function confirm_gift_username_kb(): array { return ikb([[ibtn('btn_yes_short', 'gift_confirm_yes'), ibtn('btn_no_short', 'gift_confirm_no')]]); }

function gift_invoice_kb(bool $hidden, bool $has_comment, bool $discount_applied = false): array {
    $hide_slug = !$hidden ? 'btn_gift_hide_on' : 'btn_gift_hide_off';
    $comment_slug = $has_comment ? 'btn_gift_comment_del' : 'btn_gift_comment_set';
    $comment_cb = $has_comment ? 'gift_del_comment' : 'gift_comment_type';
    $discount_slug = $discount_applied ? 'btn_discount_remove' : 'btn_discount_apply';
    return ikb([
        [ibtn('btn_confirm', 'gift_invoice_confirm')],
        [ibtn($discount_slug, 'gift_invoice_discount'), ibtn('btn_cancel', 'gift_invoice_cancel')],
        [ibtn($comment_slug, $comment_cb), ibtn($hide_slug, 'gift_toggle_hide')],
    ]);
}

function gift_comment_type_kb(): array { return ikb([[ibtn('btn_gift_comment_free', 'gift_comment_free')], [ibtn('btn_back', 'gift_comment_back_to_invoice')]]); }
function gift_comment_input_kb(): array { return ikb([[ibtn('btn_back', 'gift_comment_input_back')]]); }
function support_kb(): array { return ikb([[ibtn('btn_support_direct', null, 'https://t.me/' . SUPPORT_USERNAME), ibtn('btn_support_indirect', 'support_indirect')]]); }
function support_indirect_kb(): array { return ikb([[ibtn('btn_back', 'support_back')]]); }
function admin_support_kb(string $ticket_id): array { return ikb([[ibtn('btn_admin_reply', "support_reply_{$ticket_id}")]]); }
function join_kb(): array { return ikb([[ibtn('btn_join_channel', null, 'https://t.me/' . REQUIRED_CHANNEL)], [ibtn('btn_check_membership', 'check_membership')]]); }
function report_kb(): array { return ikb([[ibtn('btn_report_buy', null, 'https://t.me/' . BOT_USERNAME)]]); }
function track_kb(): array { return ikb([[ibtn('btn_track_have_code', 'track_have_code')]]); }
function track_ask_code_kb(): array { return ikb([[ibtn('btn_back', 'track_back')]]); }
function invite_kb(): array { return ikb([[ibtn('btn_referral_claim', 'referral_claim_reward')]]); }

function referral_reward_select_kb(): array {
    $rows = [];
    foreach (REFERRAL_REWARD_GIFTS as $k) {
        $rows[] = [ibtn(gift_button_slug($k), "referral_reward_{$k}")];
    }
    $rows[] = [ibtn('btn_back', 'referral_back')];
    return ikb($rows);
}

function ask_referral_username_kb(): array { return ikb([[ibtn('btn_self', 'referral_username_self')], [ibtn('btn_back', 'referral_reward_select_back')]]); }
function confirm_referral_username_kb(): array { return ikb([[ibtn('btn_yes_short', 'referral_confirm_yes'), ibtn('btn_no_short', 'referral_confirm_no')]]); }
function referral_reward_invoice_kb(): array { return ikb([[ibtn('btn_confirm', 'referral_invoice_confirm')], [ibtn('btn_cancel', 'referral_invoice_cancel')]]); }

function admin_panel_kb(array &$DATA): array {
    $toggle_label = $DATA['bot_enabled'] ? '🔴 خاموش کردن ربات' : '🟢 روشن کردن ربات';
    $ref_toggle_label = $DATA['referral_points_enabled'] ? '🔴 غیرفعال‌سازی جایزه دعوت' : '🟢 فعال‌سازی جایزه دعوت';
    $buttons = [
        btn($toggle_label, 'admin_toggle_bot'),
        btn($ref_toggle_label, 'admin_toggle_referral_points'),
        ibtn('btn_admin_broadcast', 'admin_broadcast'),
        ibtn('btn_admin_view_users', 'admin_view_users'),
        ibtn('btn_admin_price_menu', 'admin_price_menu'),
        ibtn('btn_admin_profit_menu', 'admin_profit_menu'),
        ibtn('btn_admin_daily_limit', 'admin_daily_limit'),
        ibtn('btn_admin_edit_texts', 'admin_edit_texts'),
        ibtn('btn_admin_edit_buttons', 'admin_edit_buttons'),
        btn('🧩 چیدمان دکمه‌های شیشه‌ای', 'admin_layout_menu'),
    ];
    $cols = get_layout_columns($DATA, 'admin_panel', LAYOUT_GROUPS['admin_panel']['default']);
    $rows = array_chunk($buttons, $cols);
    // Destructive action always stays isolated on its own row, unaffected by layout.
    $rows[] = [ibtn('btn_admin_reset', 'admin_reset_confirm')];
    return ikb($rows);
}

function admin_layout_menu_kb(array &$DATA): array {
    $rows = [];
    foreach (LAYOUT_GROUPS as $key => $meta) {
        $cols = get_layout_columns($DATA, $key, $meta['default']);
        $rows[] = [btn("{$meta['label']} ({$cols} ستونه)", "admin_layout_pick_{$key}")];
    }
    $rows[] = [ibtn('btn_back', 'admin_panel_back')];
    return ikb($rows);
}

function admin_layout_detail_kb(string $key): array {
    return ikb([
        [btn('1 ستونه', "admin_layout_set_{$key}_1"), btn('2 ستونه', "admin_layout_set_{$key}_2"), btn('3 ستونه', "admin_layout_set_{$key}_3")],
        [ibtn('btn_back', 'admin_layout_menu_back')],
    ]);
}

function admin_reset_confirm_kb(): array { return ikb([[ibtn('btn_admin_reset_yes', 'admin_reset_yes'), ibtn('btn_admin_reset_no', 'admin_panel_back')]]); }
function admin_back_kb(): array { return ikb([[ibtn('btn_back', 'admin_panel_back')]]); }
function admin_broadcast_ask_kb(): array { return ikb([[ibtn('btn_back', 'admin_panel_back')]]); }
function admin_broadcast_confirm_kb(): array { return ikb([[ibtn('btn_no_plain', 'admin_broadcast_no'), ibtn('btn_yes_short', 'admin_broadcast_yes')]]); }

function admin_price_menu_kb(): array {
    return ikb([
        [ibtn('btn_stars', 'admin_price_stars')],
        [ibtn('btn_premium', 'admin_price_premium')],
        [ibtn('btn_ton_short', 'admin_price_ton')],
        [ibtn('btn_gift_stars', 'admin_price_giftstars')],
        [ibtn('btn_gift_nft', 'admin_price_giftnft')],
        [ibtn('btn_back', 'admin_panel_back')],
    ]);
}

function admin_stars_price_kb(): array { return ikb([[ibtn('btn_admin_edit_price', 'admin_change_stars_price')], [ibtn('btn_back', 'admin_price_menu_back')]]); }
function admin_stars_price_ask_kb(): array { return ikb([[ibtn('btn_back', 'admin_price_stars_back')]]); }
function admin_ton_price_kb(): array { return ikb([[ibtn('btn_admin_edit_price', 'admin_change_ton_price')], [ibtn('btn_back', 'admin_price_menu_back')]]); }
function admin_ton_price_ask_kb(): array { return ikb([[ibtn('btn_back', 'admin_price_ton_back')]]); }
function admin_premium_price_kb(): array { return ikb([[ibtn('btn_admin_edit_price', 'admin_change_premium_price')], [ibtn('btn_back', 'admin_price_menu_back')]]); }

function admin_premium_plan_select_kb(): array {
    return ikb([
        [ibtn('btn_plan_3', 'admin_premium_plan_3'), ibtn('btn_plan_6', 'admin_premium_plan_6'), ibtn('btn_plan_12', 'admin_premium_plan_12')],
        [ibtn('btn_back', 'admin_price_premium_back')],
    ]);
}
function admin_premium_price_ask_kb(): array { return ikb([[ibtn('btn_back', 'admin_premium_plan_select_back')]]); }

function admin_gift_price_list_kb(): array {
    $rows = [];
    $keys = array_keys(GIFTS_META);
    $i = 0;
    $n = count($keys);
    while ($i < $n) {
        $a = ibtn(admin_gift_price_button_slug($keys[$i]), "admin_gift_price_{$keys[$i]}");
        if ($i + 1 < $n) {
            $b = ibtn(admin_gift_price_button_slug($keys[$i + 1]), "admin_gift_price_{$keys[$i + 1]}");
            $rows[] = [$a, $b];
            $i += 2;
        } else {
            $rows[] = [$a];
            $i += 1;
        }
    }
    $rows[] = [ibtn('btn_back', 'admin_price_menu_back')];
    return ikb($rows);
}

function admin_gift_price_detail_kb(string $key): array { return ikb([[ibtn('btn_admin_edit_price', "admin_change_gift_price_{$key}")], [ibtn('btn_back', 'admin_gift_price_list_back')]]); }
function admin_gift_price_ask_kb(string $key): array { return ikb([[ibtn('btn_back', "admin_gift_price_detail_back_{$key}")]]); }
function admin_daily_limit_kb(): array { return ikb([[ibtn('btn_admin_edit_daily_limit', 'admin_change_daily_limit')], [ibtn('btn_back', 'admin_panel_back')]]); }
function admin_daily_limit_ask_kb(): array { return ikb([[ibtn('btn_back', 'admin_daily_limit_back')]]); }

function admin_profit_menu_kb(): array {
    return ikb([
        [ibtn('btn_stars', 'admin_profit_stars')],
        [ibtn('btn_ton_short', 'admin_profit_ton')],
        [ibtn('btn_gift_stars', 'admin_profit_gift')],
        [ibtn('btn_premium', 'admin_profit_premium')],
        [ibtn('btn_back', 'admin_panel_back')],
    ]);
}
function admin_profit_detail_kb(string $product): array { return ikb([[ibtn('btn_admin_edit_profit', "admin_set_profit_{$product}")], [ibtn('btn_back', 'admin_profit_menu_back')]]); }
function admin_profit_ask_kb(string $product): array { return ikb([[ibtn('btn_back', "admin_profit_{$product}")]]); }

/* ---- text/button editor admin panel ---- */

function admin_text_categories_kb(): array {
    $rows = [];
    foreach (TEXT_CATEGORIES as $cat => $slugs) $rows[] = [btn(CATEGORY_LABELS[$cat], "admin_text_cat_{$cat}")];
    $rows[] = [ibtn('btn_back', 'admin_panel_back')];
    return ikb($rows);
}

function admin_button_categories_kb(): array {
    $rows = [];
    foreach (BUTTON_CATEGORIES as $cat => $slugs) $rows[] = [btn(CATEGORY_LABELS[$cat], "admin_btn_cat_{$cat}")];
    $rows[] = [ibtn('btn_back', 'admin_panel_back')];
    return ikb($rows);
}

function category_button_slugs(string $cat): array {
    $slugs = BUTTON_CATEGORIES[$cat] ?? [];
    if ($cat === 'gift') {
        foreach (array_keys(GIFTS_META) as $k) $slugs[] = gift_button_slug($k);
    }
    if ($cat === 'admin_msgs') {
        foreach (array_keys(GIFTS_META) as $k) $slugs[] = admin_gift_price_button_slug($k);
    }
    return $slugs;
}

function admin_text_list_kb(string $cat): array {
    $rows = [];
    foreach (TEXT_CATEGORIES[$cat] ?? [] as $slug) $rows[] = [btn(TEXT_LABELS[$slug] ?? $slug, "admin_text_pick_{$slug}")];
    $rows[] = [ibtn('btn_back', 'admin_edit_texts')];
    return ikb($rows);
}

function admin_button_list_kb(string $cat): array {
    $rows = [];
    foreach (category_button_slugs($cat) as $slug) $rows[] = [btn(B($slug), "admin_btn_pick_{$slug}")];
    $rows[] = [ibtn('btn_back', 'admin_edit_buttons')];
    return ikb($rows);
}

function admin_text_detail_kb(string $slug): array {
    return ikb([[btn('🔄 بازگردانی پیش‌فرض', "admin_text_reset_{$slug}")], [ibtn('btn_back', 'admin_text_list_back')]]);
}

// Style picker + icon controls are merged into the detail keyboard itself
// (not just shown transiently after a label edit) so they stay reachable
// from every entry point: pick/reset/style-change/icon-change all redraw
// via this same function.
function admin_button_detail_kb(string $slug): array {
    $rows = admin_button_style_kb($slug)['inline_keyboard'];
    $icon_row = [btn('🖼 ست کردن ایموجی پریمیوم', "admin_btn_icon_set_{$slug}")];
    if (BI($slug) !== null) $icon_row[] = btn('❌ حذف ایموجی', "admin_btn_icon_clear_{$slug}");
    $rows[] = $icon_row;
    $rows[] = [btn('🔄 بازگردانی پیش‌فرض', "admin_btn_reset_{$slug}")];
    $rows[] = [ibtn('btn_back', 'admin_btn_list_back')];
    return ikb($rows);
}

function admin_text_detail_body(string $slug): string {
    $placeholders = TEXT_PLACEHOLDERS[$slug] ?? [];
    $ph = $placeholders ? implode(' ', $placeholders) : 'ندارد';
    $title = TEXT_LABELS[$slug] ?? $slug;
    return "📝 عنوان: {$title}\n📌 پلیس‌هولدرهای مجاز: {$ph}\n\n📄 متن فعلی:\n" . text_raw($slug) .
        "\n\n✍️ برای تغییر، متن جدید رو همینجا (به صورت پیام معمولی) بفرست.";
}

function admin_button_detail_body(string $slug): string {
    $style_names = ['primary' => 'آبی 🔵', 'success' => 'سبز 🟢', 'danger' => 'قرمز 🔴'];
    $style = $style_names[BS($slug) ?? ''] ?? 'پیش‌فرض ⚪️';
    $icon_status = BI($slug) !== null ? 'تنظیم شده ✅' : 'تنظیم نشده ❌';
    return "🏷 برچسب فعلی: " . B($slug) . "\n🎨 رنگ فعلی: {$style}\n🖼 ایموجی پریمیوم: {$icon_status}\n\n" .
        '✍️ برای تغییر نام، برچسب جدید رو همینجا (به صورت پیام معمولی) بفرست.';
}

function admin_button_icon_ask_body(string $slug): string {
    return '🖼 یک ایموجی پریمیوم واقعی بفرست تا به عنوان آیکون دکمه «' . B($slug) . '» تنظیم بشه.' .
        "\n\n📌 روش درست ارسال (حتماً همین‌طوری، وگرنه ایموجی معمولی حساب می‌شه و کار نمی‌کنه):\n" .
        "1️⃣ کیبورد ایموجی تلگرام رو باز کن (آیکون 😀 کنار جعبه پیام)\n" .
        "2️⃣ از ردیف تب‌های بالا، تب مخصوص «Premium» (آیکون ⭐) رو انتخاب کن\n" .
        "3️⃣ یک ایموجی از همون تب انتخاب و بفرست — نه از کیبورد معمولی گوشی، نه با تایپ مستقیم\n\n" .
        "⚠️ توجه: تلگرام همیشه این آیکون رو قبل از متن دکمه نشون می‌ده و امکان جابه‌جایی به بعد از متن وجود نداره. " .
        'اگه می‌خوای ایموجی دقیقاً وسط یا انتهای متن دکمه باشه، به‌جای این گزینه، همون ایموجی رو مستقیم داخل متن دکمه (وقتی نام دکمه رو ویرایش می‌کنی) در همون‌جایی که می‌خوای تایپ کن — اونجا هم باید از همین تب Premium انتخابش کنی تا واقعاً پریمیوم باشه، وگرنه یه ایموجی معمولی می‌مونه.';
}

function admin_button_icon_ask_kb(string $slug): array {
    return ikb([[ibtn('btn_back', "admin_btn_icon_ask_back_{$slug}")]]);
}

function admin_button_icon_invalid_text(): string {
    return "❌ پیام شما هیچ ایموجی پریمیومی نداشت — یعنی یا متنی بدون ایموجی فرستادی، یا ایموجی‌ای که فرستادی از تب معمولی کیبورد بوده نه تب مخصوص «Premium» (⭐).\n\n" .
        "لطفاً کیبورد ایموجی تلگرام رو باز کن، از تب Premium (⭐) یه ایموجی انتخاب کن و همون‌طور بفرست.";
}

function admin_button_style_kb(string $slug): array {
    return ikb([[
        btn(B('btn_style_primary'), "admin_btn_style_{$slug}_primary"),
        btn(B('btn_style_success'), "admin_btn_style_{$slug}_success"),
        btn(B('btn_style_danger'), "admin_btn_style_{$slug}_danger"),
        btn(B('btn_default'), "admin_btn_style_{$slug}_none"),
    ]]);
}

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
        // Only ever holds admin *overrides* (slug => ['value'/'label' => ..., ...]);
        // T()/B() fall back to DEFAULT_TEXTS/DEFAULT_BUTTONS for any missing slug,
        // so load_data() never needs to (and must never) pre-fill these with defaults.
        'texts' => [],
        'buttons' => [],
        // group => column count (1-3); missing group falls back to LAYOUT_GROUPS[group]['default']
        'layout' => [],
        'ton_price_cache' => ['base' => 298225, 'fetched_at' => 0],
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
              'premium_prices', 'base_premium_prices', 'profit_percent', 'texts', 'buttons', 'layout'] as $k) {
        if (!is_array($data[$k] ?? null)) $data[$k] = $defaults[$k];
    }
    if (!is_array($data['ton_price_cache'] ?? null)) $data['ton_price_cache'] = $defaults['ton_price_cache'];
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
            send_message($ref_id, referral_notification_text($invited_name, $point_awarded, $referrer['referrals'], $referrer['discount_balance'], $referrer['score']), null, 'HTML');
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
        if (!$DATA['bot_enabled']) { send_message($chat_id, T('bot_off'), null, 'HTML'); return; }
        if (!is_member($uid)) { send_message($chat_id, join_text(), join_kb(), 'HTML'); return; }
    }
    $DATA['user_names'][$uid] = display_name($user);
    register_user_and_referral($DATA, $uid);
    send_message($chat_id, T('welcome'), main_kb($chat_id == ADMIN_CHAT_ID), 'HTML');
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
        send_photo(ADMIN_CHAT_ID, $file_id, admin_topup_caption($user, $amount, $req_id), admin_topup_kb($req_id), 'HTML');
    }
    $ust['state'] = null;
    $ust['pending_topup'] = null;
    send_message($chat_id, T('receipt_sent'), null, 'HTML');
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
            send_message($req['chat_id'], rejected_text($text), null, 'HTML');
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

    if ($chat_id != ADMIN_CHAT_ID && !$DATA['bot_enabled']) { send_message($chat_id, T('bot_off'), null, 'HTML'); return; }
    if ($chat_id != ADMIN_CHAT_ID && !is_member($uid)) {
        $DATA['user_state'][$uid] = [];
        send_message($chat_id, join_text(), join_kb(), 'HTML');
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
            if (!ctype_digit($raw)) { send_message($chat_id, T('admin_numeric_error'), admin_stars_price_ask_kb(), 'HTML'); return; }
            $DATA['stars_price'] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_stars_price_text($DATA), admin_stars_price_kb());
            return;
        }
        if ($state === 'admin_awaiting_ton_price') {
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, T('admin_numeric_error'), admin_ton_price_ask_kb(), 'HTML'); return; }
            $DATA['ton_price'] = (int) round(((int) $raw) / MIN_TON);
            $ust['state'] = null;
            send_message($chat_id, admin_ton_price_text($DATA), admin_ton_price_kb());
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_premium_price_')) {
            $plan = substr($state, strlen('admin_awaiting_premium_price_'));
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, T('admin_numeric_error'), admin_premium_price_ask_kb(), 'HTML'); return; }
            $DATA['premium_prices'][$plan] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_premium_price_text($DATA), admin_premium_price_kb());
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_gift_price_')) {
            $key = substr($state, strlen('admin_awaiting_gift_price_'));
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw)) { send_message($chat_id, T('admin_numeric_error'), admin_gift_price_ask_kb($key), 'HTML'); return; }
            if (isset(GIFTS_META[$key])) $DATA['gifts_prices'][$key] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_gift_price_detail_text($DATA, $key), admin_gift_price_detail_kb($key));
            return;
        }
        if ($state === 'admin_awaiting_daily_limit') {
            $raw = str_replace(',', '', trim($text));
            if (!ctype_digit($raw) || (int) $raw <= 0) { send_message($chat_id, T('admin_positive_number_error'), admin_daily_limit_ask_kb(), 'HTML'); return; }
            $DATA['daily_limit'] = (int) $raw;
            $ust['state'] = null;
            send_message($chat_id, admin_daily_limit_text($DATA), admin_daily_limit_kb());
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_profit_')) {
            $product = substr($state, strlen('admin_awaiting_profit_'));
            $raw = trim(str_replace([',', '%'], '', $text));
            if (!preg_match('/^\d+(\.\d+)?$/', $raw) || (float) $raw < 0) {
                send_message($chat_id, T('admin_positive_percent_error'), admin_profit_ask_kb($product), 'HTML');
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
        if ($state !== null && str_starts_with($state, 'admin_awaiting_text_')) {
            $slug = substr($state, strlen('admin_awaiting_text_'));
            $ust['state'] = null;
            if (!in_array($slug, all_text_slugs(), true)) { send_message($chat_id, 'این متن پیدا نشد.', admin_back_kb()); return; }
            $html = tg_entities_to_html($text, capture_entities($msg) ?? []);
            apply_text_edit($DATA, $slug, $html);
            send_message($chat_id, "✅ متن «{$slug}» ذخیره شد. پیش‌نمایش 👇");
            send_message($chat_id, T($slug), admin_text_detail_kb($slug), 'HTML');
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_button_')) {
            $slug = substr($state, strlen('admin_awaiting_button_'));
            $ust['state'] = null;
            if (!in_array($slug, all_button_slugs(), true)) { send_message($chat_id, 'این دکمه پیدا نشد.', admin_back_kb()); return; }
            $entities = capture_entities($msg);
            apply_button_edit($DATA, $slug, $text, $entities);
            send_message($chat_id, "✅ نام دکمه «{$slug}» به «" . B($slug) . "» تغییر کرد.");
            send_message($chat_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
            return;
        }
        if ($state !== null && str_starts_with($state, 'admin_awaiting_icon_')) {
            $slug = substr($state, strlen('admin_awaiting_icon_'));
            if (!in_array($slug, all_button_slugs(), true)) { $ust['state'] = null; send_message($chat_id, 'این دکمه پیدا نشد.', admin_back_kb()); return; }
            $emoji_id = first_custom_emoji_id(capture_entities($msg));
            if ($emoji_id === null) {
                send_message($chat_id, admin_button_icon_invalid_text(), admin_button_icon_ask_kb($slug));
                return;
            }
            set_button_icon($DATA, $slug, $emoji_id);
            $ust['state'] = null;
            send_message($chat_id, "✅ ایموجی پریمیوم دکمه «" . B($slug) . "» تنظیم شد.");
            send_message($chat_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
            return;
        }
    }

    if ($state === 'awaiting_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_premium'] = $ust['pending_premium'] ?? [];
        $ust['pending_premium']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_username_kb(), 'HTML');
        return;
    }

    if ($state === 'awaiting_topup_amount') {
        $raw = str_replace([',', ' '], '', $text);
        if (!ctype_digit($raw)) { send_message($chat_id, 'لطفا فقط عدد مبلغ رو بفرست. مثال: 40000'); return; }
        $amount = (int) $raw;
        $remaining = get_daily_remaining($DATA, $uid);
        if ($amount > $remaining) { send_message($chat_id, daily_limit_exceeded_text($remaining), toman_kb(), 'HTML'); return; }
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
        if ($count < STARS_MIN) { send_message($chat_id, stars_min_error_text(), stars_min_error_kb(), 'HTML'); return; }
        $ust['pending_stars'] = ['count' => $count];
        $ust['state'] = 'awaiting_stars_username';
        send_message($chat_id, T('ask_username'), ask_stars_username_kb(), 'HTML');
        return;
    }

    if ($state === 'awaiting_stars_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_stars'] = $ust['pending_stars'] ?? [];
        $ust['pending_stars']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_stars_username_kb(), 'HTML');
        return;
    }

    if ($state === 'awaiting_gift_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_gift'] = $ust['pending_gift'] ?? [];
        $ust['pending_gift']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_gift_username_kb(), 'HTML');
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
        send_message($chat_id, gift_invoice_text($key, $g['username'], $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], true, $g['discount_applied'] ?? false), 'HTML');
        return;
    }

    if ($state === 'awaiting_referral_username') {
        $username = trim($text);
        if (!str_starts_with($username, '@')) $username = '@' . $username;
        $ust['pending_referral_reward'] = $ust['pending_referral_reward'] ?? [];
        $ust['pending_referral_reward']['username'] = $username;
        $ust['state'] = null;
        send_message($chat_id, confirm_username_text($username), confirm_referral_username_kb(), 'HTML');
        return;
    }

    if ($state === 'awaiting_ton_amount') {
        $raw = str_replace(',', '', trim($text));
        if (!is_numeric($raw)) { send_message($chat_id, 'لطفا عدد وارد کن. مثال: 0.5 یا 2'); return; }
        $amount = (float) $raw;
        if ($amount < MIN_TON) { send_message($chat_id, 'حداقل سفارش ' . MIN_TON . ' TON است.'); return; }
        $ust['pending_ton'] = ['amount' => $amount];
        $ust['state'] = 'awaiting_ton_wallet';
        send_message($chat_id, T('ton_wallet_ask'), ton_wallet_back_kb(), 'HTML');
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
        send_message($chat_id, T('ton_memo_question'), ton_memo_kb(), 'HTML');
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
        send_message($chat_id, ton_invoice_text($ton['amount'], $ton['wallet'], $memo, $price, $disc, $price), ton_invoice_kb(false), 'HTML');
        return;
    }

    if ($state === 'awaiting_support_message') {
        $ticket_id = new_req_id($DATA);
        $DATA['support_tickets'][$ticket_id] = ['user_id' => $uid, 'chat_id' => $chat_id, 'message_id' => $msg['message_id']];
        send_message($chat_id, T('support_sent_confirm'), main_kb($chat_id == ADMIN_CHAT_ID), 'HTML');
        $ust['state'] = null;
        if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_support_text($user, $text), admin_support_kb($ticket_id), 'HTML');
        return;
    }

    if ($state === 'awaiting_tracking_code') {
        $code = trim($text);
        $order = $DATA['orders'][$code] ?? null;
        if (!$order) { send_message($chat_id, T('track_not_found'), track_ask_code_kb(), 'HTML'); return; }
        $ust['state'] = null;
        send_message($chat_id, order_status_text($order), null, 'HTML');
        return;
    }

    // Reply-keyboard taps are matched against the *current* buttons store (not
    // hardcoded literals), so renaming a menu button never breaks routing.
    $menu_slug = resolve_menu_button($text);

    if ($menu_slug === 'menu_buy') { send_message($chat_id, T('product_menu'), product_kb($DATA), 'HTML'); return; }

    if ($menu_slug === 'menu_topup') {
        $balance = get_user($DATA, $uid)['balance'];
        $remaining = get_daily_remaining($DATA, $uid);
        send_message($chat_id, wallet_text($balance, $remaining), wallet_kb(), 'HTML');
        return;
    }

    if ($menu_slug === 'menu_account') { send_message($chat_id, account_text($DATA, $user), null, 'HTML'); return; }
    if ($menu_slug === 'menu_referral') { send_message($chat_id, referral_text($DATA, $user), invite_kb(), 'HTML'); return; }
    if ($menu_slug === 'menu_support') { send_message($chat_id, T('support'), support_kb(), 'HTML'); return; }
    if ($menu_slug === 'menu_track') { send_message($chat_id, T('track'), track_kb(), 'HTML'); return; }
    if ($menu_slug === 'menu_trust') { send_message($chat_id, trust_text(), null, 'HTML'); return; }
    if ($menu_slug === 'menu_admin' && $chat_id == ADMIN_CHAT_ID) { send_message($chat_id, admin_panel_text($DATA), admin_panel_kb($DATA)); return; }

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
            edit_message_text($chat_id, $message_id, T('join_confirmed'), null, 'HTML');
            send_message($chat_id, T('welcome'), main_kb($chat_id == ADMIN_CHAT_ID), 'HTML');
        } else {
            answer_callback_query($cq['id'], 'هنوز در کانال عضو نشدید! لطفا ابتدا عضو شوید سپس دوباره امتحان کنید.', true);
        }
        return;
    }

    if ($chat_id != ADMIN_CHAT_ID) {
        if (!$DATA['bot_enabled']) { answer_callback_query($cq['id'], T('bot_off'), true); return; }
        if (!is_member($uid)) {
            $DATA['user_state'][$uid] = [];
            edit_message_text($chat_id, $message_id, join_text(), join_kb(), 'HTML');
            return;
        }
    }

    $DATA['user_names'][$uid] = display_name($user);
    register_user_and_referral($DATA, $uid);
    $ust = &get_ustate($DATA, $uid);

    if (str_starts_with($data, 'admin_') && $chat_id != ADMIN_CHAT_ID) return;

    if ($data === 'back_to_welcome') { $DATA['user_state'][$uid] = []; edit_message_text($chat_id, $message_id, T('welcome'), null, 'HTML'); return; }
    if ($data === 'back_to_products') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('product_menu'), product_kb($DATA), 'HTML'); return; }

    /* ---- gift ---- */
    if ($data === 'product_gift_stars') { edit_message_text($chat_id, $message_id, T('gift_list'), gift_list_kb($DATA), 'HTML'); return; }
    if ($data === 'gift_back_to_list') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('gift_list'), gift_list_kb($DATA), 'HTML'); return; }

    if (str_starts_with($data, 'gift_select_')) {
        $key = substr($data, strlen('gift_select_'));
        if (!isset(GIFTS_META[$key])) return;
        $ust['pending_gift'] = ['key' => $key, 'username' => null, 'hidden' => true, 'comment' => null];
        $ust['state'] = 'awaiting_gift_username';
        edit_message_text($chat_id, $message_id, T('ask_username'), ask_gift_username_kb(), 'HTML');
        return;
    }

    if ($data === 'gift_username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_gift'] = $ust['pending_gift'] ?? [];
            $ust['pending_gift']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_gift_username_kb(), 'HTML');
        } else {
            $ust['state'] = 'awaiting_gift_username';
            edit_message_text($chat_id, $message_id, T('no_username_error'), ikb([[ibtn('btn_back', 'gift_back_to_list')]]), 'HTML');
        }
        return;
    }

    if ($data === 'gift_confirm_no') { $ust['state'] = 'awaiting_gift_username'; edit_message_text($chat_id, $message_id, T('ask_username'), ask_gift_username_kb(), 'HTML'); return; }

    if ($data === 'gift_confirm_yes') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $key = $g['key'] ?? '';
        $username = $g['username'] ?? '-';
        $g['price'] = $DATA['gifts_prices'][$key] ?? 0;
        $g['discount_applied'] = false;
        $price = $g['price'];
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, true, null, $price, $disc, $price), gift_invoice_kb(true, false, false), 'HTML');
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
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], (bool) $comment, $g['discount_applied'] ?? false), 'HTML');
        return;
    }

    if ($data === 'gift_comment_type') { edit_message_text($chat_id, $message_id, T('gift_comment_type'), gift_comment_type_kb(), 'HTML'); return; }

    if ($data === 'gift_comment_back_to_invoice') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $key = $g['key'];
        $username = $g['username'];
        $comment = $g['comment'] ?? null;
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], (bool) $comment, $g['discount_applied'] ?? false), 'HTML');
        return;
    }

    if ($data === 'gift_comment_free') { $ust['state'] = 'awaiting_gift_comment'; edit_message_text($chat_id, $message_id, T('gift_comment_input'), gift_comment_input_kb(), 'HTML'); return; }
    if ($data === 'gift_comment_input_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('gift_comment_type'), gift_comment_type_kb(), 'HTML'); return; }

    if ($data === 'gift_del_comment') {
        $g = &$ust['pending_gift'];
        $g = $g ?? [];
        $g['comment'] = null;
        $key = $g['key'];
        $username = $g['username'];
        $price = $g['price'] ?? $DATA['gifts_prices'][$key];
        $disc = get_user($DATA, $uid)['discount_balance'];
        $final = compute_final_price($DATA, $g, $uid);
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], null, $price, $disc, $final), gift_invoice_kb($g['hidden'], false, $g['discount_applied'] ?? false), 'HTML');
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
        edit_message_text($chat_id, $message_id, gift_invoice_text($key, $username, $g['hidden'], $comment, $price, $disc, $final), gift_invoice_kb($g['hidden'], (bool) $comment, $g['discount_applied']), 'HTML');
        return;
    }

    if ($data === 'gift_invoice_cancel') { $ust['pending_gift'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, T('cancelled'), null, 'HTML'); return; }

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
            edit_message_text($chat_id, $message_id, gift_success_text($key, $username, $final, $order_id), null, 'HTML');
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_gift_text($user, $key, $username, $hidden, $comment, $final, $order_id), admin_order_kb($order_id), 'HTML');
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb(), 'HTML');
        }
        return;
    }

    /* ---- premium ---- */
    if ($data === 'product_premium') { edit_message_text($chat_id, $message_id, T('premium_menu'), premium_kb($DATA), 'HTML'); return; }

    if (in_array($data, ['premium_3', 'premium_6', 'premium_12'], true)) {
        $plan = explode('_', $data)[1];
        $ust['pending_premium'] = ['plan' => $plan, 'price' => $DATA['premium_prices'][$plan]];
        $ust['state'] = 'awaiting_username';
        edit_message_text($chat_id, $message_id, T('ask_username'), ask_username_kb(), 'HTML');
        return;
    }

    if ($data === 'back_to_premium') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('premium_menu'), premium_kb($DATA), 'HTML'); return; }

    if ($data === 'username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_premium'] = $ust['pending_premium'] ?? [];
            $ust['pending_premium']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_username_kb(), 'HTML');
        } else {
            $ust['state'] = 'awaiting_username';
            edit_message_text($chat_id, $message_id, T('no_username_error'), ikb([[ibtn('btn_back', 'back_to_ask_username')]]), 'HTML');
        }
        return;
    }

    if ($data === 'back_to_ask_username') { $ust['state'] = 'awaiting_username'; edit_message_text($chat_id, $message_id, T('ask_username'), ask_username_kb(), 'HTML'); return; }
    if ($data === 'confirm_no') { $ust['state'] = 'awaiting_username'; edit_message_text($chat_id, $message_id, T('ask_username'), ask_username_kb(), 'HTML'); return; }

    if ($data === 'confirm_yes') {
        $order = &$ust['pending_premium'];
        $order = $order ?? [];
        $order['discount_applied'] = false;
        $plan = $order['plan'] ?? '?';
        $price = $order['price'] ?? 0;
        $username = $order['username'] ?? '-';
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, premium_invoice_text($plan, $price, $username, $disc, $price), invoice_kb(false), 'HTML');
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
        edit_message_text($chat_id, $message_id, premium_invoice_text($plan, $price, $username, $disc, $final), invoice_kb($order['discount_applied']), 'HTML');
        return;
    }

    if ($data === 'invoice_cancel') { $ust['pending_premium'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, T('cancelled'), null, 'HTML'); return; }

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
            edit_message_text($chat_id, $message_id, premium_success_text($plan, $username, $final, $order_id), null, 'HTML');
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_premium_text($user, $plan, $username, $final, $order_id), admin_order_kb($order_id), 'HTML');
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb(), 'HTML');
        }
        return;
    }

    /* ---- wallet / top-up ---- */
    if ($data === 'wallet_increase') { edit_message_text($chat_id, $message_id, T('wallet_increase'), wallet_increase_kb(), 'HTML'); return; }

    if ($data === 'wallet_back') {
        $balance = get_user($DATA, $uid)['balance'];
        $remaining = get_daily_remaining($DATA, $uid);
        edit_message_text($chat_id, $message_id, wallet_text($balance, $remaining), wallet_kb(), 'HTML');
        return;
    }

    if ($data === 'topup_from_invoice' || $data === 'topup_from_wallet') {
        $ust['topup_origin'] = $data === 'topup_from_invoice' ? 'invoice' : 'wallet';
        $ust['state'] = 'awaiting_topup_amount';
        $remaining = get_daily_remaining($DATA, $uid);
        edit_message_text($chat_id, $message_id, toman_payment_text($remaining), toman_kb(), 'HTML');
        return;
    }

    if ($data === 'topup_back') {
        $ust['state'] = null;
        $origin = $ust['topup_origin'] ?? null;
        if ($origin === 'invoice') {
            $price = $ust['last_invoice_shortfall_price'] ?? 0;
            $shortfall = max($price - get_user($DATA, $uid)['balance'], 0);
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb(), 'HTML');
        } else {
            edit_message_text($chat_id, $message_id, T('wallet_increase'), wallet_increase_kb(), 'HTML');
        }
        return;
    }

    if ($data === 'card_back') {
        $ust['state'] = 'awaiting_topup_amount';
        $remaining = get_daily_remaining($DATA, $uid);
        edit_message_text($chat_id, $message_id, toman_payment_text($remaining), toman_kb(), 'HTML');
        return;
    }

    if ($data === 'send_receipt') { $ust['state'] = 'awaiting_receipt_photo'; edit_message_text($chat_id, $message_id, T('receipt_prompt'), receipt_prompt_kb(), 'HTML'); return; }

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
            send_message($req['chat_id'], approved_text($u['balance']), buy_product_kb(), 'HTML');
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
        send_message($order['chat_id'], $msgText, buy_product_kb(), 'HTML');

        $buyer_name = $order['buyer_name'] ?? 'کاربر';
        send_message('@' . REPORTS_CHANNEL, report_text($buyer_name, $order), report_kb(), 'HTML');
        return;
    }

    /* ---- tracking ---- */
    if ($data === 'track_have_code') { $ust['state'] = 'awaiting_tracking_code'; edit_message_text($chat_id, $message_id, T('track_ask_code'), track_ask_code_kb(), 'HTML'); return; }
    if ($data === 'track_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('track'), track_kb(), 'HTML'); return; }

    /* ---- referral ---- */
    if ($data === 'referral_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, referral_text($DATA, $user), invite_kb(), 'HTML'); return; }

    if ($data === 'referral_claim_reward') {
        if (!$DATA['referral_points_enabled']) {
            answer_callback_query($cq['id'], '❌ سیستم جایزه دعوت در حال حاضر توسط مدیریت غیرفعال شده است.', true);
            return;
        }
        if (get_user($DATA, $uid)['score'] < REFERRAL_POINTS_REQUIRED) {
            answer_callback_query($cq['id'], 'هنوز امتیاز کافی نداری! برای دریافت جایزه به ' . REFERRAL_POINTS_REQUIRED . ' امتیاز رفرال نیاز داری.', true);
            return;
        }
        edit_message_text($chat_id, $message_id, referral_reward_select_text(), referral_reward_select_kb(), 'HTML');
        return;
    }

    if ($data === 'referral_reward_select_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, referral_reward_select_text(), referral_reward_select_kb(), 'HTML'); return; }

    if (str_starts_with($data, 'referral_reward_') && in_array(substr($data, strlen('referral_reward_')), REFERRAL_REWARD_GIFTS, true)) {
        $key = substr($data, strlen('referral_reward_'));
        $ust['pending_referral_reward'] = ['key' => $key, 'username' => null];
        $ust['state'] = 'awaiting_referral_username';
        edit_message_text($chat_id, $message_id, T('ask_username'), ask_referral_username_kb(), 'HTML');
        return;
    }

    if ($data === 'referral_username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_referral_reward'] = $ust['pending_referral_reward'] ?? [];
            $ust['pending_referral_reward']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_referral_username_kb(), 'HTML');
        } else {
            $ust['state'] = 'awaiting_referral_username';
            edit_message_text($chat_id, $message_id, T('no_username_error'), ikb([[ibtn('btn_back', 'referral_reward_select_back')]]), 'HTML');
        }
        return;
    }

    if ($data === 'referral_confirm_no') { $ust['state'] = 'awaiting_referral_username'; edit_message_text($chat_id, $message_id, T('ask_username'), ask_referral_username_kb(), 'HTML'); return; }

    if ($data === 'referral_confirm_yes') {
        $r = $ust['pending_referral_reward'] ?? [];
        $key = $r['key'] ?? '';
        $username = $r['username'] ?? '-';
        edit_message_text($chat_id, $message_id, referral_reward_invoice_text($key, $username), referral_reward_invoice_kb(), 'HTML');
        return;
    }

    if ($data === 'referral_invoice_cancel') { $ust['pending_referral_reward'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, T('cancelled'), null, 'HTML'); return; }

    if ($data === 'referral_invoice_confirm') {
        $r = $ust['pending_referral_reward'] ?? [];
        $key = $r['key'] ?? '';
        $username = $r['username'] ?? '-';
        $u = &get_user($DATA, $uid);
        if (!$DATA['referral_points_enabled']) {
            $ust['pending_referral_reward'] = null;
            $ust['state'] = null;
            answer_callback_query($cq['id'], '❌ سیستم جایزه دعوت در حال حاضر توسط مدیریت غیرفعال شده است.', true);
            edit_message_text($chat_id, $message_id, referral_text($DATA, $user), invite_kb(), 'HTML');
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
        edit_message_text($chat_id, $message_id, referral_reward_success_text($key, $username, $order_id), null, 'HTML');
        if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_referral_reward_text($user, $key, $username, $order_id), admin_order_kb($order_id), 'HTML');
        return;
    }

    /* ---- support ---- */
    if ($data === 'support_indirect') { $ust['state'] = 'awaiting_support_message'; edit_message_text($chat_id, $message_id, T('support_indirect'), support_indirect_kb(), 'HTML'); return; }
    if ($data === 'support_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('support'), support_kb(), 'HTML'); return; }

    if (str_starts_with($data, 'support_reply_')) {
        $ticket_id = substr($data, strlen('support_reply_'));
        if (!isset($DATA['support_tickets'][$ticket_id])) { answer_callback_query($cq['id'], 'این تیکت قبلا پاسخ داده شده یا پیدا نشد.', true); return; }
        $DATA['admin_waiting_support_reply'][$chat_id] = $ticket_id;
        edit_message_text($chat_id, $message_id, ($message['text'] ?? '') . "\n\nپاسخ خود را بگید");
        return;
    }

    /* ---- stars ---- */
    if ($data === 'product_stars') { $ust['state'] = 'awaiting_stars_amount'; $ust['pending_stars'] = []; edit_message_text($chat_id, $message_id, stars_buy_text(), stars_back_kb(), 'HTML'); return; }
    if ($data === 'stars_back_to_amount') { $ust['state'] = 'awaiting_stars_amount'; $ust['pending_stars'] = []; edit_message_text($chat_id, $message_id, stars_buy_text(), stars_back_kb(), 'HTML'); return; }

    if ($data === 'stars_username_self') {
        if (!empty($user['username'])) {
            $username = '@' . $user['username'];
            $ust['pending_stars'] = $ust['pending_stars'] ?? [];
            $ust['pending_stars']['username'] = $username;
            $ust['state'] = null;
            edit_message_text($chat_id, $message_id, confirm_username_text($username), confirm_stars_username_kb(), 'HTML');
        } else {
            $ust['state'] = 'awaiting_stars_username';
            edit_message_text($chat_id, $message_id, T('no_username_error'), ikb([[ibtn('btn_back', 'stars_back_to_amount')]]), 'HTML');
        }
        return;
    }

    if ($data === 'stars_confirm_no') { $ust['state'] = 'awaiting_stars_username'; edit_message_text($chat_id, $message_id, T('ask_username'), ask_stars_username_kb(), 'HTML'); return; }

    if ($data === 'stars_confirm_yes') {
        $s = &$ust['pending_stars'];
        $s = $s ?? [];
        $count = $s['count'] ?? 0;
        $username = $s['username'] ?? '-';
        $s['price'] = $count * $DATA['stars_price'];
        $s['discount_applied'] = false;
        $price = $s['price'];
        $disc = get_user($DATA, $uid)['discount_balance'];
        edit_message_text($chat_id, $message_id, stars_invoice_text($count, $username, $price, $disc, $price), stars_invoice_kb(false), 'HTML');
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
        edit_message_text($chat_id, $message_id, stars_invoice_text($count, $username, $price, $disc, $final), stars_invoice_kb($s['discount_applied']), 'HTML');
        return;
    }

    if ($data === 'stars_invoice_cancel') { $ust['pending_stars'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, T('cancelled'), null, 'HTML'); return; }

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
            edit_message_text($chat_id, $message_id, stars_success_text($count, $username, $final, $order_id), null, 'HTML');
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_stars_text($user, $count, $username, $final, $order_id), admin_order_kb($order_id), 'HTML');
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb(), 'HTML');
        }
        return;
    }

    /* ---- ton ---- */
    if ($data === 'product_buy_ton') { $ust['state'] = 'awaiting_ton_amount'; $ust['pending_ton'] = []; edit_message_text($chat_id, $message_id, ton_buy_text($DATA), ton_back_kb(), 'HTML'); return; }
    if ($data === 'ton_back_to_amount') { $ust['state'] = 'awaiting_ton_amount'; $ust['pending_ton'] = []; edit_message_text($chat_id, $message_id, ton_buy_text($DATA), ton_back_kb(), 'HTML'); return; }
    if ($data === 'ton_memo_yes') { $ust['state'] = 'awaiting_ton_memo'; edit_message_text($chat_id, $message_id, T('ton_memo_input'), ton_memo_input_kb(), 'HTML'); return; }
    if ($data === 'ton_memo_input_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, T('ton_memo_question'), ton_memo_kb(), 'HTML'); return; }

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
        edit_message_text($chat_id, $message_id, ton_invoice_text($amount, $wallet, 'ندارد', $price, $disc, $price), ton_invoice_kb(false), 'HTML');
        return;
    }

    if ($data === 'ton_memo_back') { $ust['state'] = 'awaiting_ton_wallet'; edit_message_text($chat_id, $message_id, T('ton_wallet_ask'), ton_wallet_back_kb(), 'HTML'); return; }

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
        edit_message_text($chat_id, $message_id, ton_invoice_text($amount, $wallet, $memo, $price, $disc, $final), ton_invoice_kb($ton['discount_applied']), 'HTML');
        return;
    }

    if ($data === 'ton_invoice_cancel') { $ust['pending_ton'] = null; $ust['state'] = null; edit_message_text($chat_id, $message_id, T('cancelled'), null, 'HTML'); return; }

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
            edit_message_text($chat_id, $message_id, ton_success_text($amount, $wallet, $final, $order_id), null, 'HTML');
            if (ADMIN_CHAT_ID) send_message(ADMIN_CHAT_ID, admin_ton_text($user, $amount, $wallet, $memo, $final, $order_id), admin_order_kb($order_id), 'HTML');
        } else {
            $shortfall = $final - $u['balance'];
            $ust['topup_origin'] = 'invoice';
            $ust['last_invoice_shortfall_price'] = $final;
            edit_message_text($chat_id, $message_id, insufficient_text($shortfall), insufficient_kb(), 'HTML');
        }
        return;
    }

    /* ---- NFT gift placeholder (declared in keyboard, unimplemented upstream; wired to a "coming soon" alert like admin_price_giftnft) ---- */
    if ($data === 'product_gift_nft') { answer_callback_query($cq['id'], PRODUCT_PLACEHOLDER_NFT_TEXT, true); return; }

    /* ---- admin panel ---- */
    if ($data === 'admin_panel_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_panel_text($DATA), admin_panel_kb($DATA)); return; }
    if ($data === 'admin_daily_limit') { edit_message_text($chat_id, $message_id, admin_daily_limit_text($DATA), admin_daily_limit_kb()); return; }
    if ($data === 'admin_daily_limit_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_daily_limit_text($DATA), admin_daily_limit_kb()); return; }
    if ($data === 'admin_change_daily_limit') { $ust['state'] = 'admin_awaiting_daily_limit'; edit_message_text($chat_id, $message_id, admin_ask_daily_limit_text($DATA), admin_daily_limit_ask_kb(), 'HTML'); return; }

    /* ---- inline-keyboard layout (columns per row) ---- */
    if ($data === 'admin_layout_menu') { edit_message_text($chat_id, $message_id, admin_layout_menu_text(), admin_layout_menu_kb($DATA)); return; }
    if ($data === 'admin_layout_menu_back') { edit_message_text($chat_id, $message_id, admin_layout_menu_text(), admin_layout_menu_kb($DATA)); return; }

    if (str_starts_with($data, 'admin_layout_pick_')) {
        $key = substr($data, strlen('admin_layout_pick_'));
        if (!isset(LAYOUT_GROUPS[$key])) return;
        edit_message_text($chat_id, $message_id, admin_layout_detail_text($DATA, $key), admin_layout_detail_kb($key));
        return;
    }

    if (str_starts_with($data, 'admin_layout_set_')) {
        $rest = substr($data, strlen('admin_layout_set_'));
        $pos = strrpos($rest, '_');
        if ($pos === false) return;
        $key = substr($rest, 0, $pos);
        $cols = (int) substr($rest, $pos + 1);
        if (!isset(LAYOUT_GROUPS[$key]) || !in_array($cols, [1, 2, 3], true)) return;
        $DATA['layout'][$key] = $cols;
        edit_message_text($chat_id, $message_id, admin_layout_detail_text($DATA, $key), admin_layout_detail_kb($key));
        return;
    }

    if ($data === 'admin_profit_menu') { edit_message_text($chat_id, $message_id, admin_profit_menu_text(), admin_profit_menu_kb()); return; }
    if ($data === 'admin_profit_menu_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_profit_menu_text(), admin_profit_menu_kb()); return; }
    if ($data === 'admin_profit_stars') { edit_message_text($chat_id, $message_id, admin_profit_stars_text($DATA), admin_profit_detail_kb('stars')); return; }
    if ($data === 'admin_profit_ton') { refresh_ton_price_if_stale($DATA); edit_message_text($chat_id, $message_id, admin_profit_ton_text($DATA), admin_profit_detail_kb('ton')); return; }
    if ($data === 'admin_profit_gift') { edit_message_text($chat_id, $message_id, admin_profit_gift_text($DATA), admin_profit_detail_kb('gift')); return; }
    if ($data === 'admin_profit_premium') { edit_message_text($chat_id, $message_id, admin_profit_premium_text($DATA), admin_profit_detail_kb('premium')); return; }

    if (str_starts_with($data, 'admin_set_profit_')) {
        $product = substr($data, strlen('admin_set_profit_'));
        $fa_names = ['stars' => 'استارز', 'ton' => 'تون', 'gift' => 'گیفت استارز', 'premium' => 'پرمیوم'];
        $ust['state'] = "admin_awaiting_profit_{$product}";
        edit_message_text($chat_id, $message_id, admin_ask_profit_text($fa_names[$product] ?? $product), admin_profit_ask_kb($product), 'HTML');
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
            $res = send_message($buid, broadcast_message_text($broadcast_text), null, 'HTML');
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
    if ($data === 'admin_change_stars_price') { $ust['state'] = 'admin_awaiting_stars_price'; edit_message_text($chat_id, $message_id, admin_ask_stars_price_text(), admin_stars_price_ask_kb(), 'HTML'); return; }
    if ($data === 'admin_price_stars_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_stars_price_text($DATA), admin_stars_price_kb()); return; }

    if ($data === 'admin_price_ton') { refresh_ton_price_if_stale($DATA); edit_message_text($chat_id, $message_id, admin_ton_price_text($DATA), admin_ton_price_kb()); return; }
    if ($data === 'admin_change_ton_price') { $ust['state'] = 'admin_awaiting_ton_price'; edit_message_text($chat_id, $message_id, admin_ask_ton_price_text(), admin_ton_price_ask_kb(), 'HTML'); return; }
    if ($data === 'admin_price_ton_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_ton_price_text($DATA), admin_ton_price_kb()); return; }

    if ($data === 'admin_price_premium') { edit_message_text($chat_id, $message_id, admin_premium_price_text($DATA), admin_premium_price_kb()); return; }
    if ($data === 'admin_change_premium_price') { edit_message_text($chat_id, $message_id, ADMIN_PREMIUM_PLAN_SELECT_TEXT, admin_premium_plan_select_kb()); return; }
    if ($data === 'admin_price_premium_back') { $ust['state'] = null; edit_message_text($chat_id, $message_id, admin_premium_price_text($DATA), admin_premium_price_kb()); return; }

    if (in_array($data, ['admin_premium_plan_3', 'admin_premium_plan_6', 'admin_premium_plan_12'], true)) {
        $parts = explode('_', $data);
        $plan = end($parts);
        $ust['state'] = "admin_awaiting_premium_price_{$plan}";
        edit_message_text($chat_id, $message_id, admin_ask_premium_price_text($plan), admin_premium_price_ask_kb(), 'HTML');
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
        edit_message_text($chat_id, $message_id, admin_ask_gift_price_text($key), admin_gift_price_ask_kb($key), 'HTML');
        return;
    }

    if (str_starts_with($data, 'admin_gift_price_')) {
        $key = substr($data, strlen('admin_gift_price_'));
        if (isset(GIFTS_META[$key])) {
            edit_message_text($chat_id, $message_id, admin_gift_price_detail_text($DATA, $key), admin_gift_price_detail_kb($key));
        }
        return;
    }

    /* ---- text editor ---- */
    if ($data === 'admin_edit_texts') { $ust['state'] = null; edit_message_text($chat_id, $message_id, '✏️ ویرایش متن‌های ربات\n\nدسته مورد نظر رو انتخاب کن:', admin_text_categories_kb()); return; }

    if (str_starts_with($data, 'admin_text_cat_')) {
        $cat = substr($data, strlen('admin_text_cat_'));
        if (!isset(TEXT_CATEGORIES[$cat])) return;
        $ust['admin_text_cat'] = $cat;
        edit_message_text($chat_id, $message_id, CATEGORY_LABELS[$cat] . "\n\nمتن مورد نظر رو انتخاب کن:", admin_text_list_kb($cat));
        return;
    }

    if ($data === 'admin_text_list_back') {
        $cat = $ust['admin_text_cat'] ?? null;
        if ($cat === null || !isset(TEXT_CATEGORIES[$cat])) { edit_message_text($chat_id, $message_id, '✏️ ویرایش متن‌های ربات', admin_text_categories_kb()); return; }
        edit_message_text($chat_id, $message_id, CATEGORY_LABELS[$cat] . "\n\nمتن مورد نظر رو انتخاب کن:", admin_text_list_kb($cat));
        return;
    }

    if (str_starts_with($data, 'admin_text_reset_')) {
        $slug = substr($data, strlen('admin_text_reset_'));
        if (!in_array($slug, all_text_slugs(), true)) return;
        reset_text($DATA, $slug);
        edit_message_text($chat_id, $message_id, admin_text_detail_body($slug), admin_text_detail_kb($slug));
        return;
    }

    if (str_starts_with($data, 'admin_text_pick_')) {
        $slug = substr($data, strlen('admin_text_pick_'));
        if (!in_array($slug, all_text_slugs(), true)) return;
        $ust['state'] = "admin_awaiting_text_{$slug}";
        edit_message_text($chat_id, $message_id, admin_text_detail_body($slug), admin_text_detail_kb($slug));
        return;
    }

    /* ---- button label editor ---- */
    if ($data === 'admin_edit_buttons') { $ust['state'] = null; edit_message_text($chat_id, $message_id, '✏️ ویرایش نام دکمه‌ها\n\nدسته مورد نظر رو انتخاب کن:', admin_button_categories_kb()); return; }

    if (str_starts_with($data, 'admin_btn_cat_')) {
        $cat = substr($data, strlen('admin_btn_cat_'));
        if (!isset(BUTTON_CATEGORIES[$cat])) return;
        $ust['admin_btn_cat'] = $cat;
        edit_message_text($chat_id, $message_id, CATEGORY_LABELS[$cat] . "\n\nدکمه مورد نظر رو انتخاب کن:", admin_button_list_kb($cat));
        return;
    }

    if ($data === 'admin_btn_list_back') {
        $cat = $ust['admin_btn_cat'] ?? null;
        if ($cat === null || !isset(BUTTON_CATEGORIES[$cat])) { edit_message_text($chat_id, $message_id, '✏️ ویرایش نام دکمه‌ها', admin_button_categories_kb()); return; }
        edit_message_text($chat_id, $message_id, CATEGORY_LABELS[$cat] . "\n\nدکمه مورد نظر رو انتخاب کن:", admin_button_list_kb($cat));
        return;
    }

    if (str_starts_with($data, 'admin_btn_reset_')) {
        $slug = substr($data, strlen('admin_btn_reset_'));
        if (!in_array($slug, all_button_slugs(), true)) return;
        reset_button($DATA, $slug);
        edit_message_text($chat_id, $message_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
        return;
    }

    if (str_starts_with($data, 'admin_btn_style_')) {
        $rest = substr($data, strlen('admin_btn_style_'));
        $pos = strrpos($rest, '_');
        if ($pos === false) return;
        $slug = substr($rest, 0, $pos);
        $style = substr($rest, $pos + 1);
        if (!in_array($slug, all_button_slugs(), true) || !in_array($style, ['primary', 'success', 'danger', 'none'], true)) return;
        set_button_style($DATA, $slug, $style === 'none' ? null : $style);
        edit_message_text($chat_id, $message_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
        return;
    }

    if (str_starts_with($data, 'admin_btn_icon_set_')) {
        $slug = substr($data, strlen('admin_btn_icon_set_'));
        if (!in_array($slug, all_button_slugs(), true)) return;
        $ust['state'] = "admin_awaiting_icon_{$slug}";
        edit_message_text($chat_id, $message_id, admin_button_icon_ask_body($slug), admin_button_icon_ask_kb($slug));
        return;
    }

    if (str_starts_with($data, 'admin_btn_icon_clear_')) {
        $slug = substr($data, strlen('admin_btn_icon_clear_'));
        if (!in_array($slug, all_button_slugs(), true)) return;
        set_button_icon($DATA, $slug, null);
        edit_message_text($chat_id, $message_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
        return;
    }

    if (str_starts_with($data, 'admin_btn_icon_ask_back_')) {
        $slug = substr($data, strlen('admin_btn_icon_ask_back_'));
        if (!in_array($slug, all_button_slugs(), true)) return;
        $ust['state'] = null;
        edit_message_text($chat_id, $message_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
        return;
    }

    if (str_starts_with($data, 'admin_btn_pick_')) {
        $slug = substr($data, strlen('admin_btn_pick_'));
        if (!in_array($slug, all_button_slugs(), true)) return;
        $ust['state'] = "admin_awaiting_button_{$slug}";
        edit_message_text($chat_id, $message_id, admin_button_detail_body($slug), admin_button_detail_kb($slug));
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

// Optional GET-triggered refresh for a real server cron, e.g.:
//   */5 * * * * curl -s "https://yourdomain.com/bot.php?cron=refresh_prices"
// Forces a fresh Nobitex fetch regardless of the lazy cache TTL.
function handle_cron_refresh_prices(): void {
    header('Content-Type: application/json; charset=utf-8');
    $lockFh = fopen(LOCK_FILE, 'c');
    if ($lockFh === false) { http_response_code(500); echo json_encode(['ok' => false, 'error' => 'lock']); return; }
    flock($lockFh, LOCK_EX);
    $DATA = load_data();
    $before = $DATA['ton_price'];
    refresh_ton_price_if_stale($DATA, true);
    save_data($DATA);
    flock($lockFh, LOCK_UN);
    fclose($lockFh);
    echo json_encode(['ok' => true, 'ton_price_before' => $before, 'ton_price_after' => $DATA['ton_price']], JSON_UNESCAPED_UNICODE);
}

function main_entry(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        if (isset($_GET['setup_webhook'])) { handle_setup_webhook(); return; }
        if (isset($_GET['cron']) && $_GET['cron'] === 'refresh_prices') { handle_cron_refresh_prices(); return; }
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
    // Text/button lookup helpers (T/B/BS/BI) read this reference instead of
    // threading $DATA through every keyboard/label-building function.
    $GLOBALS['STORE'] = &$DATA;
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

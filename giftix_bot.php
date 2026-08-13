<?php
/**
 * GiftIx Store Bot — PHP port of the original Python (python-telegram-bot + SQLAlchemy) source.
 *
 * Two run modes, same file:
 *   1) Long polling (CLI):  php giftix_bot.php run
 *   2) Webhook (web server): deploy this file at a public HTTPS URL and register it with
 *      `php giftix_bot.php webhook:set https://your-domain/path/giftix_bot.php`
 *      `php giftix_bot.php webhook:delete` switches back to polling mode.
 *      `php giftix_bot.php webhook:info` shows Telegram's current webhook status.
 * A mode change takes effect immediately server-side (setWebhook disables getUpdates and
 * vice versa) — never run polling while a webhook is registered, or run two pollers/webhooks
 * against the same token at once.
 *
 * Requires:  php-cli, php-curl, php-sqlite3, php-mbstring (php >= 8.1)
 *
 * Notes on premium (custom) emoji:
 *   Telegram's Bot API renders custom/premium emoji ONLY inside message text/captions via
 *   parse_mode=HTML using the <tg-emoji emoji-id="..."> tag. InlineKeyboardButton labels are
 *   plain strings — the Bot API does not accept entities/HTML inside button text, so a button
 *   can never literally show an animated premium emoji; that is a Telegram platform limit, not
 *   a PHP one. To still make buttons feel "premium" and colorful, every glass/menu button is
 *   prefixed with a colored circle matching its semantic color (see COLOR_EMOJI below), while
 *   real premium emoji (via CUSTOM_EMOJI + pe()) are used inside the message bodies that sit
 *   above those buttons. Fill in real emoji-id values (obtained from a Premium account, e.g.
 *   by forwarding a custom-emoji message to @userinfobot-like tools or via getCustomEmojiStickers)
 *   in $CUSTOM_EMOJI below to activate real premium emoji; until then, the unicode fallback is used.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

// ============================================================
// Config (token / admin set as requested)
// ============================================================
const BOT_TOKEN = '8759780599:AAEsFbf8h_oFR4ms4KFNqhqHNjTV0FxD3Lo';
const BOT_USERNAME = 'GiftIx1bot';
const REPORTS_CHANNEL = 'GiftIx1_Reports';
const DB_PATH = __DIR__ . '/giftix.db';
const OFFSET_FILE = __DIR__ . '/.giftix_update_offset';
const DAILY_DEPOSIT_LIMIT = 900_000;
const REFERRAL_COMMISSION = 5;
const REFERRAL_BONUS = 1_000;
const DEFAULT_LANG = 'fa';
const USE_PREMIUM_EMOJI = true;
const BACKUP_INTERVAL_SECONDS = 24 * 3600;

$ADMINS = [
    8213021584 => 'owner',
];

// Empty = join-gate disabled. To require membership again, list channel usernames here
// (without @) AND make sure the bot itself is an ADMIN of each channel first — Telegram's
// getChatMember call fails for a bot that isn't a member/admin of the channel, and this bot
// treats that failure as "not joined", which is what caused users to get stuck even after
// actually joining.
$REQUIRED_CHANNELS = [];

$BASE_PRICES = [
    'ton' => 298_225,
    'stars' => 2_637,
    'gift_star' => 3_514,
    'premium' => ['3m' => 1_577_000, '6m' => 2_560_000, '12m' => 4_460_000],
];

$PROFIT_PERCENT = ['ton' => 10, 'stars' => 8, 'gift' => 21, 'premium' => 11];

// Premium (custom) emoji registry — fill the 'id' values with real custom-emoji document ids
// that BOT_TOKEN's owner account has access to. Fallback unicode is used automatically otherwise.
$CUSTOM_EMOJI = [
    'gift' => ['id' => '', 'fallback' => '🎁'],
    'star' => ['id' => '', 'fallback' => '⭐'],
    'diamond' => ['id' => '', 'fallback' => '💎'],
    'fire' => ['id' => '', 'fallback' => '🔥'],
    'check' => ['id' => '', 'fallback' => '✅'],
    'wallet' => ['id' => '', 'fallback' => '👛'],
    'crown' => ['id' => '', 'fallback' => '👑'],
    'rocket' => ['id' => '', 'fallback' => '🚀'],
    'bell' => ['id' => '', 'fallback' => '🔔'],
    'admin' => ['id' => '', 'fallback' => '🛠'],
];

$COLOR_EMOJI = [
    'blue' => '🔵', 'green' => '🟢', 'red' => '🔴', 'purple' => '🟣',
    'orange' => '🟠', 'yellow' => '🟡', 'gray' => '⚪',
];

$GIFTS = [
    'teddy' => ['label' => 'خرس عروسکی', 'mult' => 15],
    'heart' => ['label' => 'قلب', 'mult' => 15],
    'rose' => ['label' => 'دسته گل رز', 'mult' => 25],
    'kado' => ['label' => 'جعبه هدیه', 'mult' => 25],
    'rocket' => ['label' => 'موشک', 'mult' => 25],
    'trophy' => ['label' => 'جام قهرمانی', 'mult' => 25],
    'diamond' => ['label' => 'الماس', 'mult' => 25],
];

// ============================================================
// Logging
// ============================================================
function logMsg(string $msg): void
{
    $line = '[' . date('Y-m-d H:i:s') . "] {$msg}\n";
    if (PHP_SAPI === 'cli') {
        fwrite(STDOUT, $line);
    }
    @file_put_contents(__DIR__ . '/giftix_bot.log', $line, FILE_APPEND);
}

// ============================================================
// Database
// ============================================================
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}

function initDb(): void
{
    $d = db();
    $d->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        telegram_id INTEGER UNIQUE NOT NULL,
        username TEXT,
        full_name TEXT,
        language TEXT DEFAULT 'fa',
        balance INTEGER NOT NULL DEFAULT 0,
        discount_balance INTEGER NOT NULL DEFAULT 0,
        referral_code TEXT UNIQUE,
        referrer_id INTEGER,
        level INTEGER NOT NULL DEFAULT 0,
        registered_at TEXT,
        is_active INTEGER NOT NULL DEFAULT 1,
        is_banned INTEGER NOT NULL DEFAULT 0,
        daily_deposit_used INTEGER NOT NULL DEFAULT 0,
        daily_deposit_date TEXT DEFAULT ''
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id TEXT UNIQUE NOT NULL,
        user_id INTEGER NOT NULL,
        product_type TEXT NOT NULL,
        product_detail TEXT,
        price INTEGER NOT NULL,
        final_price INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        wallet_address TEXT,
        memo TEXT,
        created_at TEXT,
        updated_at TEXT,
        admin_note TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        amount INTEGER NOT NULL,
        type TEXT NOT NULL,
        description TEXT,
        reference_id TEXT,
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        subject TEXT,
        body TEXT,
        status TEXT NOT NULL DEFAULT 'open',
        priority TEXT NOT NULL DEFAULT 'normal',
        admin_response TEXT,
        created_at TEXT,
        updated_at TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS admin_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        admin_id INTEGER NOT NULL,
        action TEXT NOT NULL,
        target_id INTEGER,
        details TEXT,
        created_at TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS backups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT,
        size INTEGER,
        created_at TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS menu_buttons (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        section TEXT NOT NULL,
        label TEXT NOT NULL,
        color TEXT NOT NULL DEFAULT 'blue',
        action_type TEXT NOT NULL,
        action_value TEXT,
        price INTEGER,
        description TEXT,
        position INTEGER NOT NULL DEFAULT 0,
        new_row INTEGER NOT NULL DEFAULT 1,
        created_at TEXT
    )");
    $d->exec("CREATE TABLE IF NOT EXISTS user_state (
        telegram_id INTEGER PRIMARY KEY,
        step TEXT,
        data TEXT,
        broadcast INTEGER NOT NULL DEFAULT 0,
        ticket_reply_id INTEGER,
        price_edit INTEGER NOT NULL DEFAULT 0,
        updated_at TEXT
    )");
}

function settingGet(string $key): ?string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    return $row ? $row['value'] : null;
}

function settingSet(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([$key, $value]);
}

// ============================================================
// Time / formatting helpers
// ============================================================
function nowTehran(): string
{
    return date('Y-m-d H:i:s');
}

function todayStr(): string
{
    return date('Y-m-d');
}

function fmtN(int $n): string
{
    return number_format($n);
}

function randCode(int $len): string
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $s;
}

function genOrderId(): string
{
    return randCode(10);
}

// ============================================================
// Pricing
// ============================================================
function getBasePrices(): array
{
    global $BASE_PRICES;
    $override = settingGet('base_prices');
    if ($override) {
        $decoded = json_decode($override, true);
        if (is_array($decoded)) {
            return array_replace_recursive($BASE_PRICES, $decoded);
        }
    }
    return $BASE_PRICES;
}

function getProfitPercents(): array
{
    global $PROFIT_PERCENT;
    $override = settingGet('profit_percent');
    if ($override) {
        $decoded = json_decode($override, true);
        if (is_array($decoded)) {
            return array_replace($PROFIT_PERCENT, $decoded);
        }
    }
    return $PROFIT_PERCENT;
}

function getPrice(string $productType, ?string $detail = null): int
{
    $prices = getBasePrices();
    $base = $prices[$productType] ?? 0;
    if ($productType === 'premium' && $detail !== null) {
        $base = $prices['premium'][$detail] ?? 0;
    }
    $profitKey = $productType === 'gift_star' ? 'gift' : $productType;
    $profit = getProfitPercents()[$profitKey] ?? 0;
    return (int) round($base * (1 + $profit / 100));
}

function setBasePriceOverride(string $key, int $value): void
{
    $prices = getBasePrices();
    $prices[$key] = $value;
    settingSet('base_prices', json_encode($prices, JSON_UNESCAPED_UNICODE));
}

// ============================================================
// HTML formatting helpers (bold / quote / premium emoji)
// ============================================================
function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function b(string $htmlSafe): string
{
    return '<b>' . $htmlSafe . '</b>';
}

function quoteBlock(string $htmlSafe, bool $expandable = false): string
{
    $attr = $expandable ? ' expandable' : '';
    return "<blockquote{$attr}>{$htmlSafe}</blockquote>";
}

function pe(string $key): string
{
    global $CUSTOM_EMOJI;
    if (!isset($CUSTOM_EMOJI[$key])) {
        return '';
    }
    $conf = $CUSTOM_EMOJI[$key];
    $fallback = h($conf['fallback']);
    if (USE_PREMIUM_EMOJI && !empty($conf['id'])) {
        return '<tg-emoji emoji-id="' . h($conf['id']) . '">' . $fallback . '</tg-emoji>';
    }
    return $fallback;
}

// ============================================================
// i18n
// ============================================================
$TEXTS = [
    'fa' => [
        'welcome' => 'به فروشگاه GiftIx خوش آمدید!',
        'welcome_sub' => 'استارز، پرمیوم تلگرام، هدیه و تون‌کوین — همه‌جا یک‌جا.',
        'products_title' => 'یکی از خدمات زیر را انتخاب کنید:',
        'stars_buy_title' => 'خرید استارز تلگرام',
        'premium_title' => 'خرید اشتراک Telegram Premium',
        'gift_list_title' => 'یک هدیه برای دوستان خود انتخاب کنید:',
        'ton_buy_title' => 'خرید تون‌کوین (TON)',
        'order_success' => 'سفارش شما با موفقیت ثبت شد!',
        'order_done' => 'سفارش شما با موفقیت تکمیل شد.',
        'admin_panel' => 'پنل مدیریت',
        'backup_done' => 'پشتیبان‌گیری در ساعت {time} با حجم {size} انجام شد.',
        'maintenance' => 'ربات موقتاً در حال به‌روزرسانی است. کمی بعد دوباره تلاش کنید.',
        'banned' => 'شما از استفاده از این ربات محروم شده‌اید.',
        'not_admin' => 'شما به این بخش دسترسی ندارید.',
    ],
    'en' => [
        'welcome' => 'Welcome to GiftIx Store Bot!',
        'welcome_sub' => 'Stars, Telegram Premium, gifts and TON — all in one place.',
        'products_title' => 'Choose your product:',
        'stars_buy_title' => 'Buy Telegram Stars',
        'premium_title' => 'Telegram Premium',
        'gift_list_title' => 'Send a gift on Telegram!',
        'ton_buy_title' => 'Buy TON coin',
        'order_success' => 'Order registered successfully!',
        'order_done' => 'Order completed.',
        'admin_panel' => 'Admin Panel',
        'backup_done' => 'Backup at {time} — size {size}.',
        'maintenance' => 'The bot is under maintenance, please try again later.',
        'banned' => 'You are banned from using this bot.',
        'not_admin' => 'You do not have access to this section.',
    ],
    'ar' => [
        'welcome' => 'مرحبًا بك في متجر GiftIx!',
        'welcome_sub' => 'نجوم، بريميوم تيليجرام، هدايا وعملة TON، كل ذلك في مكان واحد.',
        'products_title' => 'اختر أحد المنتجات:',
        'stars_buy_title' => 'شراء نجوم تيليجرام',
        'premium_title' => 'اشتراك Telegram Premium',
        'gift_list_title' => 'أرسل هدية على تيليجرام!',
        'ton_buy_title' => 'شراء عملة TON',
        'order_success' => 'تم تسجيل الطلب بنجاح!',
        'order_done' => 'تم إكمال الطلب.',
        'admin_panel' => 'لوحة الإدارة',
        'backup_done' => 'تم النسخ الاحتياطي في {time} — الحجم {size}.',
        'maintenance' => 'البوت قيد الصيانة، حاول لاحقًا.',
        'banned' => 'تم حظرك من استخدام هذا البوت.',
        'not_admin' => 'ليس لديك صلاحية الوصول لهذا القسم.',
    ],
];

function txt(string $key, string $lang = DEFAULT_LANG, array $params = []): string
{
    global $TEXTS;
    $lang = isset($TEXTS[$lang]) ? $lang : DEFAULT_LANG;
    $text = $TEXTS[$lang][$key] ?? $TEXTS[DEFAULT_LANG][$key] ?? $key;
    foreach ($params as $k => $v) {
        $text = str_replace('{' . $k . '}', (string) $v, $text);
    }
    return $text;
}

// ============================================================
// Telegram Bot API
// ============================================================
function apiRequest(string $method, array $params = []): ?array
{
    foreach ($params as $k => $v) {
        if (is_array($v)) {
            $params[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
        }
    }
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/' . $method;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 50,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        logMsg("cURL error on {$method}: " . curl_error($ch));
        curl_close($ch);
        return null;
    }
    curl_close($ch);
    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['ok'])) {
        logMsg("API error on {$method}: " . substr((string) $response, 0, 500));
        return null;
    }
    return is_array($data['result']) ? $data['result'] : [];
}

function sendMessage(int|string $chatId, string $html, ?array $keyboard = null): ?array
{
    $params = ['chat_id' => $chatId, 'text' => $html, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) {
        $params['reply_markup'] = $keyboard;
    }
    return apiRequest('sendMessage', $params);
}

function editMessageText(int|string $chatId, int $messageId, string $html, ?array $keyboard = null): ?array
{
    $params = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $html, 'parse_mode' => 'HTML'];
    if ($keyboard !== null) {
        $params['reply_markup'] = $keyboard;
    }
    $res = apiRequest('editMessageText', $params);
    if ($res === null) {
        // message may be unchanged/unreachable — fall back to a fresh message so the user isn't stuck
        return sendMessage($chatId, $html, $keyboard);
    }
    return $res;
}

function answerCallback(string $callbackId, ?string $text = null, bool $alert = false): void
{
    $params = ['callback_query_id' => $callbackId];
    if ($text !== null) {
        $params['text'] = $text;
        $params['show_alert'] = $alert;
    }
    apiRequest('answerCallbackQuery', $params);
}

function sendPhotoByFileId(int|string $chatId, string $fileId, string $captionHtml): ?array
{
    return apiRequest('sendPhoto', [
        'chat_id' => $chatId, 'photo' => $fileId, 'caption' => $captionHtml, 'parse_mode' => 'HTML',
    ]);
}

function sendDocumentFile(int|string $chatId, string $localPath, string $captionHtml): ?array
{
    $params = [
        'chat_id' => $chatId,
        'document' => new CURLFile($localPath),
        'caption' => $captionHtml,
        'parse_mode' => 'HTML',
    ];
    $url = 'https://api.telegram.org/bot' . BOT_TOKEN . '/sendDocument';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $response, true);
    return (is_array($data) && !empty($data['ok'])) ? $data['result'] : null;
}

function getChatMemberStatus(string $channel, int $tgId): ?string
{
    $res = apiRequest('getChatMember', ['chat_id' => '@' . $channel, 'user_id' => $tgId]);
    return $res['status'] ?? null;
}

// ============================================================
// Keyboards (colored glass buttons)
// ============================================================
function glassButton(string $text, string $callbackData, string $color = 'blue'): array
{
    global $COLOR_EMOJI;
    $emoji = $COLOR_EMOJI[$color] ?? '⚪';
    return ['text' => "{$emoji} {$text}", 'callback_data' => $callbackData];
}

function urlButton(string $text, string $url): array
{
    return ['text' => $text, 'url' => $url];
}

function kb(array $rows): array
{
    return ['inline_keyboard' => $rows];
}

function productKeyboard(): array
{
    return kbWithSection('main_menu', [
        [glassButton('خرید استارز', 'buy_stars', 'blue')],
        [glassButton('خرید پرمیوم', 'buy_premium', 'purple')],
        [glassButton('ارسال هدیه', 'buy_gift', 'green')],
        [glassButton('خرید تون‌کوین', 'buy_ton', 'orange')],
        [glassButton('کیف پول من', 'wallet', 'yellow')],
        [glassButton('پیگیری سفارش', 'track_order', 'red')],
        [glassButton('پشتیبانی', 'support', 'green')],
        [glassButton('تنظیمات', 'settings', 'gray')],
    ]);
}

function adminKeyboard(): array
{
    return kb([
        [glassButton('آمار ربات', 'admin_stats', 'blue')],
        [glassButton('کاربران', 'admin_users', 'green')],
        [glassButton('سفارش‌ها', 'admin_orders', 'orange')],
        [glassButton('تراکنش‌ها', 'admin_transactions', 'purple')],
        [glassButton('تیکت‌ها', 'admin_tickets', 'red')],
        [glassButton('مدیریت قیمت‌ها', 'admin_price', 'yellow')],
        [glassButton('چیدمان دکمه‌ها', 'admin_layout', 'purple')],
        [glassButton('ارسال همگانی', 'admin_broadcast', 'blue')],
        [glassButton('پشتیبان‌گیری', 'admin_backup', 'green')],
        [glassButton('فعال/غیرفعال حالت تعمیر', 'admin_toggle', 'red')],
    ]);
}

function backRow(string $callbackData, string $label = 'بازگشت'): array
{
    return [glassButton($label, $callbackData, 'gray')];
}

// ============================================================
// Admin-editable menu buttons — lets an admin add extra glass buttons (links, other
// internal sections, or brand-new priced products) to any of these sections, and choose
// the exact row/column layout by sequentially picking "new row" or "same row" per button
// (so e.g. "2 on top, 1 in the middle, 2 more" is just five adds in that order).
// ============================================================
const MENU_SECTIONS = [
    'main_menu' => 'منوی اصلی',
    'products' => 'لیست محصولات',
    'buy_stars' => 'خرید استارز',
    'buy_premium' => 'خرید پرمیوم',
    'buy_gift' => 'ارسال هدیه',
    'buy_ton' => 'خرید تون‌کوین',
    'wallet' => 'کیف پول',
];

const MENU_INTERNAL_ACTIONS = [
    'main_menu' => 'منوی اصلی',
    'products' => 'لیست محصولات',
    'buy_stars' => 'خرید استارز',
    'buy_premium' => 'خرید پرمیوم',
    'buy_gift' => 'ارسال هدیه',
    'buy_ton' => 'خرید تون‌کوین',
    'wallet' => 'کیف پول',
    'deposit' => 'افزایش موجودی',
    'track_order' => 'پیگیری سفارش',
    'support' => 'پشتیبانی',
    'settings' => 'تنظیمات',
];

function getMenuButtons(string $section): array
{
    $stmt = db()->prepare('SELECT * FROM menu_buttons WHERE section = ? ORDER BY position ASC, id ASC');
    $stmt->execute([$section]);
    return $stmt->fetchAll();
}

function sectionRows(string $section): array
{
    $rows = [];
    foreach (getMenuButtons($section) as $btn) {
        if ($btn['action_type'] === 'url') {
            $button = urlButton($btn['label'], (string) $btn['action_value']);
        } elseif ($btn['action_type'] === 'product') {
            $button = glassButton($btn['label'] . ' - ' . fmtN((int) $btn['price']) . ' تومان', 'mb_' . $btn['id'], $btn['color']);
        } else {
            $button = glassButton($btn['label'], (string) $btn['action_value'], $btn['color']);
        }
        if ((int) $btn['new_row'] === 1 || empty($rows)) {
            $rows[] = [$button];
        } else {
            $rows[count($rows) - 1][] = $button;
        }
    }
    return $rows;
}

function kbWithSection(string $section, array $staticRows, array $trailingRows = []): array
{
    return kb(array_merge($staticRows, sectionRows($section), $trailingRows));
}

function addMenuButton(string $section, string $label, string $color, string $actionType, ?string $actionValue, ?int $price, ?string $description, bool $newRow): int
{
    $stmt = db()->prepare('SELECT COALESCE(MAX(position), -1) mx FROM menu_buttons WHERE section = ?');
    $stmt->execute([$section]);
    $nextPos = (int) $stmt->fetch()['mx'] + 1;

    $stmt = db()->prepare('INSERT INTO menu_buttons (section, label, color, action_type, action_value, price, description, position, new_row, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$section, $label, $color, $actionType, $actionValue, $price, $description, $nextPos, $newRow ? 1 : 0, nowTehran()]);
    return (int) db()->lastInsertId();
}

function getMenuButton(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM menu_buttons WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function deleteMenuButton(int $id): void
{
    $stmt = db()->prepare('DELETE FROM menu_buttons WHERE id = ?');
    $stmt->execute([$id]);
}

// ============================================================
// User helpers
// ============================================================
function getUserByTelegramId(int $tgId): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE telegram_id = ?');
    $stmt->execute([$tgId]);
    return $stmt->fetch() ?: null;
}

function getUserByReferralCode(string $code): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE referral_code = ?');
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

function getUserById(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function createUser(int $tgId, ?string $username, string $fullName, ?int $referrerDbId = null): array
{
    do {
        $code = randCode(8);
    } while (getUserByReferralCode($code));

    $stmt = db()->prepare('INSERT INTO users (telegram_id, username, full_name, referral_code, referrer_id, registered_at)
        VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$tgId, $username, $fullName, $code, $referrerDbId, nowTehran()]);
    return getUserByTelegramId($tgId);
}

function getOrCreateUser(int $tgId, ?string $username, string $fullName, ?string $referralPayload = null): array
{
    $user = getUserByTelegramId($tgId);
    if ($user) {
        return $user;
    }
    $referrerDbId = null;
    if ($referralPayload) {
        $referrer = getUserByReferralCode($referralPayload);
        if ($referrer && (int) $referrer['telegram_id'] !== $tgId) {
            $referrerDbId = (int) $referrer['id'];
        }
    }
    return createUser($tgId, $username, $fullName, $referrerDbId);
}

function updateUserFields(int $userId, array $fields): void
{
    if (!$fields) {
        return;
    }
    $set = implode(', ', array_map(fn($k) => "{$k} = :{$k}", array_keys($fields)));
    $fields['id'] = $userId;
    $stmt = db()->prepare("UPDATE users SET {$set} WHERE id = :id");
    $stmt->execute($fields);
}

function adjustBalance(int $userId, int $delta): void
{
    $stmt = db()->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
    $stmt->execute([$delta, $userId]);
}

function isAdmin(int $tgId): bool
{
    global $ADMINS;
    return array_key_exists($tgId, $ADMINS);
}

function adminRole(int $tgId): string
{
    global $ADMINS;
    return $ADMINS[$tgId] ?? 'admin';
}

// ============================================================
// Orders / Transactions / Tickets helpers
// ============================================================
function createOrder(int $userId, string $productType, array $detail, int $price): array
{
    $orderId = genOrderId();
    $now = nowTehran();
    $stmt = db()->prepare('INSERT INTO orders (order_id, user_id, product_type, product_detail, price, final_price, status, wallet_address, memo, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $orderId, $userId, $productType, json_encode($detail, JSON_UNESCAPED_UNICODE),
        $price, $price, 'pending', $detail['wallet'] ?? null, $detail['memo'] ?? null, $now, $now,
    ]);
    return getOrderByOrderId($orderId);
}

function getOrderByOrderId(string $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}

function updateOrderStatus(string $orderId, string $status, ?string $note = null): void
{
    $stmt = db()->prepare('UPDATE orders SET status = ?, admin_note = COALESCE(?, admin_note), updated_at = ? WHERE order_id = ?');
    $stmt->execute([$status, $note, nowTehran(), $orderId]);
}

function addTransaction(int $userId, int $amount, string $type, ?string $description = null, ?string $status = 'pending'): int
{
    $stmt = db()->prepare('INSERT INTO transactions (user_id, amount, type, description, status, created_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $amount, $type, $description, $status, nowTehran()]);
    return (int) db()->lastInsertId();
}

function getTransaction(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM transactions WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function setTransactionStatus(int $id, string $status): void
{
    $stmt = db()->prepare('UPDATE transactions SET status = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

function createTicket(int $userId, string $subject, string $body): int
{
    $stmt = db()->prepare('INSERT INTO tickets (user_id, subject, body, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$userId, $subject, $body, 'open', nowTehran(), nowTehran()]);
    return (int) db()->lastInsertId();
}

function getTicket(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM tickets WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function logAdminAction(int $adminId, string $action, ?int $targetId = null, array $details = []): void
{
    $stmt = db()->prepare('INSERT INTO admin_logs (admin_id, action, target_id, details, created_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$adminId, $action, $targetId, json_encode($details, JSON_UNESCAPED_UNICODE), nowTehran()]);
}

// ============================================================
// In-memory per-user conversation state (mirrors context.user_data)
// ============================================================
$STATE = [];

function &state(int $tgId): array
{
    global $STATE;
    if (!isset($STATE[$tgId])) {
        $STATE[$tgId] = ['step' => null, 'data' => [], 'broadcast' => false, 'ticket_reply_id' => null, 'price_edit' => false];
    }
    return $STATE[$tgId];
}

function resetStep(int $tgId): void
{
    $st = &state($tgId);
    $st['step'] = null;
}

// Webhook mode serves one update per fresh PHP-FPM process, so conversation state cannot
// live only in the $STATE array — it is loaded from and saved back to SQLite around every
// single update (loadState/saveState below), keeping polling and webhook mode identical.
function loadState(int $tgId): void
{
    global $STATE;
    $stmt = db()->prepare('SELECT * FROM user_state WHERE telegram_id = ?');
    $stmt->execute([$tgId]);
    $row = $stmt->fetch();
    if ($row) {
        $STATE[$tgId] = [
            'step' => $row['step'],
            'data' => $row['data'] ? (json_decode((string) $row['data'], true) ?: []) : [],
            'broadcast' => (bool) $row['broadcast'],
            'ticket_reply_id' => $row['ticket_reply_id'] !== null ? (int) $row['ticket_reply_id'] : null,
            'price_edit' => (bool) $row['price_edit'],
        ];
    } else {
        $STATE[$tgId] = ['step' => null, 'data' => [], 'broadcast' => false, 'ticket_reply_id' => null, 'price_edit' => false];
    }
}

function saveState(int $tgId): void
{
    $st = state($tgId);
    $stmt = db()->prepare('INSERT INTO user_state (telegram_id, step, data, broadcast, ticket_reply_id, price_edit, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(telegram_id) DO UPDATE SET step = excluded.step, data = excluded.data,
            broadcast = excluded.broadcast, ticket_reply_id = excluded.ticket_reply_id,
            price_edit = excluded.price_edit, updated_at = excluded.updated_at');
    $stmt->execute([
        $tgId, $st['step'], json_encode($st['data'] ?? [], JSON_UNESCAPED_UNICODE),
        !empty($st['broadcast']) ? 1 : 0, $st['ticket_reply_id'] ?? null, !empty($st['price_edit']) ? 1 : 0, nowTehran(),
    ]);
}

// ============================================================
// Join-gate
// ============================================================
function getNotMemberChannels(int $tgId): array
{
    global $REQUIRED_CHANNELS;
    $notMember = [];
    foreach ($REQUIRED_CHANNELS as $ch) {
        $status = getChatMemberStatus($ch, $tgId);
        if (!in_array($status, ['member', 'administrator', 'creator'], true)) {
            $notMember[] = $ch;
        }
    }
    return $notMember;
}

function sendJoinPrompt(int|string $chatId, array $channels, bool $edit = false, ?int $messageId = null): void
{
    $rows = [];
    foreach ($channels as $ch) {
        $rows[] = [urlButton('عضویت در ' . pe('bell') . ' @' . $ch, 'https://t.me/' . $ch)];
    }
    $rows[] = [glassButton('عضو شدم، بررسی کن', 'check_join', 'green')];
    $text = b(h('برای استفاده از ربات ابتدا در کانال(های) زیر عضو شوید:'));
    if ($edit && $messageId !== null) {
        editMessageText($chatId, $messageId, $text, kb($rows));
    } else {
        sendMessage($chatId, $text, kb($rows));
    }
}

// ============================================================
// /start
// ============================================================
function handleStartCommand(array $message): void
{
    $from = $message['from'];
    $tgId = (int) $from['id'];
    $chatId = $message['chat']['id'];
    $username = $from['username'] ?? null;
    $fullName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    if ($fullName === '') {
        $fullName = $username ?? (string) $tgId;
    }

    $text = (string) ($message['text'] ?? '/start');
    $parts = explode(' ', $text, 2);
    $refCode = null;
    if (isset($parts[1]) && str_starts_with(trim($parts[1]), 'ref_')) {
        $refCode = substr(trim($parts[1]), 4);
    }

    $user = getUserByTelegramId($tgId) ?? getOrCreateUser($tgId, $username, $fullName, $refCode);

    if ((int) $user['is_banned'] === 1) {
        sendMessage($chatId, h(txt('banned', $user['language'] ?: DEFAULT_LANG)));
        return;
    }

    $lang = $user['language'] ?: DEFAULT_LANG;

    if (settingGet('maintenance') === '1' && !isAdmin($tgId)) {
        sendMessage($chatId, h(txt('maintenance', $lang)));
        return;
    }

    $notMember = getNotMemberChannels($tgId);
    if (!empty($notMember)) {
        sendJoinPrompt($chatId, $notMember);
        return;
    }

    $welcome = b(pe('gift') . ' ' . h(txt('welcome', $lang))) . "\n\n" . h(txt('welcome_sub', $lang));
    sendMessage($chatId, $welcome, productKeyboard());
}

// ============================================================
// Callback: navigation
// ============================================================
function cbCheckJoin(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $notMember = getNotMemberChannels($tgId);
    if (!empty($notMember)) {
        sendJoinPrompt($ctx['chatId'], $notMember, true, $ctx['messageId']);
        return;
    }
    $lang = $ctx['lang'];
    $welcome = b(pe('check') . ' ' . h('عضویت شما تایید شد!')) . "\n\n" . h(txt('welcome_sub', $lang));
    editMessageText($ctx['chatId'], $ctx['messageId'], $welcome, productKeyboard());
}

function cbMainMenu(array $ctx): void
{
    resetStep($ctx['tgId']);
    $lang = $ctx['lang'];
    $welcome = b(pe('gift') . ' ' . h(txt('welcome', $lang))) . "\n\n" . h(txt('welcome_sub', $lang));
    editMessageText($ctx['chatId'], $ctx['messageId'], $welcome, productKeyboard());
}

function cbProducts(array $ctx): void
{
    $lang = $ctx['lang'];
    editMessageText($ctx['chatId'], $ctx['messageId'], b(h(txt('products_title', $lang))), kbWithSection('products', [
        [glassButton('خرید استارز', 'buy_stars', 'blue')],
        [glassButton('خرید پرمیوم', 'buy_premium', 'purple')],
        [glassButton('ارسال هدیه', 'buy_gift', 'green')],
        [glassButton('خرید تون‌کوین', 'buy_ton', 'orange')],
    ], [backRow('main_menu')]));
}

// ============================================================
// Callback: buy stars
// ============================================================
function cbBuyStars(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $lang = $ctx['lang'];
    $price = getPrice('stars');
    $st = &state($tgId);
    $st['step'] = 'stars_count';
    $st['data']['price_per_star'] = $price;

    $text = b(pe('star') . ' ' . h(txt('stars_buy_title', $lang))) . "\n\n"
        . h('قیمت هر استارز: ') . b(fmtN($price) . ' تومان') . "\n"
        . h('حداقل خرید: 50 عدد') . "\n\n"
        . quoteBlock(h('تعداد استارز موردنظر خود را ارسال کنید:'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kbWithSection('buy_stars', [], [backRow('products')]));
}

// ============================================================
// Callback: buy premium
// ============================================================
function cbBuyPremium(array $ctx): void
{
    $lang = $ctx['lang'];
    $p3 = getPrice('premium', '3m');
    $p6 = getPrice('premium', '6m');
    $p12 = getPrice('premium', '12m');
    $text = b(pe('crown') . ' ' . h(txt('premium_title', $lang)));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kbWithSection('buy_premium', [
        [glassButton("3 ماهه - " . fmtN($p3) . ' تومان', 'premium_3m', 'blue')],
        [glassButton("6 ماهه - " . fmtN($p6) . ' تومان', 'premium_6m', 'green')],
        [glassButton("12 ماهه - " . fmtN($p12) . ' تومان', 'premium_12m', 'purple')],
    ], [backRow('products')]));
}

function cbPremiumSelect(array $ctx, string $data): void
{
    $plan = substr($data, strlen('premium_'));
    if (!in_array($plan, ['3m', '6m', '12m'], true)) {
        return;
    }
    $tgId = $ctx['tgId'];
    $st = &state($tgId);
    $st['data']['premium_plan'] = $plan;
    $st['step'] = 'premium_username';

    $text = quoteBlock(h('لطفاً یوزرنیم تلگرام حساب موردنظر برای فعال‌سازی پرمیوم را ارسال کنید (بدون @):'))
        . "\n\n" . h('نمونه: Eli_as13');
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('products')]));
}

function cbPremiumConfirm(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $st = &state($tgId);
    $plan = $st['data']['premium_plan'] ?? '3m';
    $username = $st['data']['premium_username'] ?? '';
    $price = getPrice('premium', $plan);

    $user = getUserByTelegramId($tgId);
    if ((int) $user['balance'] < $price) {
        editMessageText($ctx['chatId'], $ctx['messageId'], b(h('موجودی کیف پول کافی نیست!')) . "\n" . h('موجودی: ' . fmtN((int) $user['balance']) . ' تومان'));
        return;
    }

    $order = createOrder((int) $user['id'], 'premium', ['plan' => $plan, 'username' => $username], $price);
    adjustBalance((int) $user['id'], -$price);

    $text = b(pe('check') . ' ' . h(txt('order_success', $ctx['lang']))) . "\n\n"
        . h('پلن: ') . b(h($plan)) . "\n"
        . h('یوزرنیم: @' . $username) . "\n"
        . h('مبلغ: ' . fmtN($price) . ' تومان') . "\n"
        . quoteBlock(h('کد پیگیری: ') . b(h($order['order_id'])));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, productKeyboard());
    $st['step'] = null;

    notifyAdmins(b(pe('crown') . ' ' . h('سفارش جدید پرمیوم')) . "\n\n" . orderAdminSummary($order, $user));
}

// ============================================================
// Callback: buy gift
// ============================================================
function giftKeyboardRows(): array
{
    global $GIFTS;
    $rows = [];
    foreach ($GIFTS as $key => $info) {
        $price = getPrice('gift_star') * $info['mult'];
        $rows[] = [glassButton($info['label'] . ' - ' . fmtN($price) . ' تومان', 'gift_' . $key, 'green')];
    }
    return $rows;
}

function cbBuyGift(array $ctx): void
{
    $lang = $ctx['lang'];
    editMessageText($ctx['chatId'], $ctx['messageId'], b(pe('gift') . ' ' . h(txt('gift_list_title', $lang))), kbWithSection('buy_gift', giftKeyboardRows(), [backRow('products')]));
}

function cbGiftSelect(array $ctx, string $data): void
{
    global $GIFTS;
    $key = substr($data, strlen('gift_'));
    if (!isset($GIFTS[$key])) {
        return;
    }
    $tgId = $ctx['tgId'];
    $st = &state($tgId);
    $st['data']['gift_key'] = $key;
    $st['step'] = 'gift_username';

    $text = quoteBlock(h('یوزرنیم تلگرام گیرنده هدیه را ارسال کنید (بدون @):'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('products')]));
}

function cbGiftConfirm(array $ctx): void
{
    global $GIFTS;
    $tgId = $ctx['tgId'];
    $st = &state($tgId);
    $key = $st['data']['gift_key'] ?? 'teddy';
    $username = $st['data']['gift_username'] ?? '';
    $info = $GIFTS[$key] ?? $GIFTS['teddy'];
    $price = getPrice('gift_star') * $info['mult'];

    $user = getUserByTelegramId($tgId);
    if ((int) $user['balance'] < $price) {
        editMessageText($ctx['chatId'], $ctx['messageId'], b(h('موجودی کیف پول کافی نیست!')));
        return;
    }

    $order = createOrder((int) $user['id'], 'gift', ['gift' => $key, 'username' => $username], $price);
    adjustBalance((int) $user['id'], -$price);

    $text = b(pe('check') . ' ' . h('سفارش هدیه ثبت شد!')) . "\n\n"
        . h('هدیه: ' . $info['label']) . "\n"
        . h('گیرنده: @' . $username) . "\n"
        . h('مبلغ: ' . fmtN($price) . ' تومان') . "\n"
        . quoteBlock(h('کد پیگیری: ') . b(h($order['order_id'])));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, productKeyboard());
    $st['step'] = null;

    notifyAdmins(b(pe('gift') . ' ' . h('سفارش جدید هدیه')) . "\n\n" . orderAdminSummary($order, $user));
}

// ============================================================
// Callback: buy TON
// ============================================================
function cbBuyTon(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $lang = $ctx['lang'];
    $price = getPrice('ton');
    $st = &state($tgId);
    $st['step'] = 'ton_amount';
    $st['data']['price_per_ton'] = $price;

    $text = b(pe('rocket') . ' ' . h(txt('ton_buy_title', $lang))) . "\n\n"
        . h('قیمت هر TON: ') . b(fmtN($price) . ' تومان') . "\n"
        . h('حداقل خرید: 0.1 TON') . "\n\n"
        . quoteBlock(h('مقدار TON موردنظر خود را ارسال کنید:'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kbWithSection('buy_ton', [], [backRow('products')]));
}

// ============================================================
// Callback: wallet
// ============================================================
function cbWallet(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $user = getUserByTelegramId($tgId);
    if (!$user) {
        return;
    }
    $refCountStmt = db()->prepare('SELECT COUNT(*) c FROM users WHERE referrer_id = ?');
    $refCountStmt->execute([$user['id']]);
    $refCount = (int) $refCountStmt->fetch()['c'];

    $text = b(pe('wallet') . ' ' . h('کیف پول من')) . "\n\n"
        . h('موجودی: ') . b(fmtN((int) $user['balance']) . ' تومان') . "\n"
        . h('موجودی تخفیف: ') . b(fmtN((int) $user['discount_balance']) . ' تومان') . "\n"
        . h('سطح: ' . $user['level']) . "\n"
        . h('تعداد زیرمجموعه: ' . $refCount) . "\n\n"
        . quoteBlock(h('کد معرف شما: ') . b(h($user['referral_code'])) . "\n"
            . h('لینک دعوت: https://t.me/' . BOT_USERNAME . '?start=ref_' . $user['referral_code']));

    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kbWithSection('wallet', [
        [glassButton('افزایش موجودی', 'deposit', 'green')],
        [glassButton('تراکنش‌های من', 'transactions', 'blue')],
    ], [backRow('main_menu')]));
}

function cbDeposit(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $user = getUserByTelegramId($tgId);
    $today = todayStr();
    $used = ($user['daily_deposit_date'] === $today) ? (int) $user['daily_deposit_used'] : 0;
    $remaining = DAILY_DEPOSIT_LIMIT - $used;

    $st = &state($tgId);
    $st['step'] = 'deposit_amount';

    $text = b(pe('wallet') . ' ' . h('افزایش موجودی')) . "\n\n"
        . h('سقف واریز روزانه: ' . fmtN(DAILY_DEPOSIT_LIMIT) . ' تومان') . "\n"
        . h('باقیمانده امروز: ' . fmtN(max(0, $remaining)) . ' تومان') . "\n\n"
        . quoteBlock(h('مبلغ موردنظر برای واریز را به تومان ارسال کنید:'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('wallet')]));
}

function cbTransactions(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $user = getUserByTelegramId($tgId);
    $stmt = db()->prepare('SELECT * FROM transactions WHERE user_id = ? ORDER BY id DESC LIMIT 10');
    $stmt->execute([$user['id']]);
    $rows = $stmt->fetchAll();

    $statusMap = ['pending' => 'در انتظار بررسی', 'confirmed' => 'تایید شده', 'rejected' => 'رد شده'];
    $lines = [b(pe('wallet') . ' ' . h('۱۰ تراکنش اخیر شما'))];
    if (!$rows) {
        $lines[] = h('هنوز تراکنشی ثبت نشده است.');
    }
    foreach ($rows as $r) {
        $lines[] = quoteBlock(
            h(fmtN((int) $r['amount']) . ' تومان — ' . ($statusMap[$r['status']] ?? $r['status'])) . "\n"
            . h((string) $r['created_at'])
        );
    }
    editMessageText($ctx['chatId'], $ctx['messageId'], implode("\n", $lines), kb([backRow('wallet')]));
}

// ============================================================
// Callback: track order / support / settings
// ============================================================
function cbTrackOrder(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $st = &state($tgId);
    $st['step'] = 'track_order';
    $text = quoteBlock(h('کد پیگیری سفارش خود را ارسال کنید:'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('main_menu')]));
}

function cbSupport(array $ctx): void
{
    $tgId = $ctx['tgId'];
    $st = &state($tgId);
    $st['step'] = 'support_message';
    $text = b(h('پشتیبانی')) . "\n\n" . quoteBlock(h('پیام خود را برای تیم پشتیبانی ارسال کنید، در اسرع وقت پاسخ داده می‌شود.'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('main_menu')]));
}

function cbSettings(array $ctx): void
{
    $text = b(h('تنظیمات زبان')) . "\n\n" . h('زبان موردنظر خود را انتخاب کنید:');
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([
        [glassButton('فارسی', 'set_lang_fa', 'blue')],
        [glassButton('English', 'set_lang_en', 'green')],
        [glassButton('العربية', 'set_lang_ar', 'purple')],
        backRow('main_menu'),
    ]));
}

function cbSetLang(array $ctx, string $data): void
{
    $lang = substr($data, strlen('set_lang_'));
    global $TEXTS;
    if (!isset($TEXTS[$lang])) {
        return;
    }
    updateUserFields((int) $ctx['user']['id'], ['language' => $lang]);
    $welcome = b(pe('gift') . ' ' . h(txt('welcome', $lang))) . "\n\n" . h(txt('welcome_sub', $lang));
    editMessageText($ctx['chatId'], $ctx['messageId'], $welcome, productKeyboard());
}

// ============================================================
// Admin: panel / stats
// ============================================================
function cbAdminPanel(array $ctx): void
{
    $adminName = h($ctx['cq']['from']['first_name'] ?? adminRole($ctx['tgId']));
    $text = b(pe('admin') . ' ' . h(txt('admin_panel', $ctx['lang']))) . "\n\n" . h('خوش آمدید ') . $adminName;
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, adminKeyboard());
}

function cbAdminStats(array $ctx): void
{
    $d = db();
    $totalUsers = (int) $d->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
    $totalOrders = (int) $d->query('SELECT COUNT(*) c FROM orders')->fetch()['c'];
    $ordersDone = (int) $d->query("SELECT COUNT(*) c FROM orders WHERE status = 'done'")->fetch()['c'];
    $revenue = (int) ($d->query("SELECT COALESCE(SUM(final_price),0) s FROM orders WHERE status = 'done'")->fetch()['s'] ?? 0);
    $pendingTx = (int) $d->query("SELECT COUNT(*) c FROM transactions WHERE status = 'pending'")->fetch()['c'];
    $openTickets = (int) $d->query("SELECT COUNT(*) c FROM tickets WHERE status = 'open'")->fetch()['c'];

    $text = b(pe('diamond') . ' ' . h('آمار ربات')) . "\n\n"
        . h('کاربران: ') . b((string) $totalUsers) . "\n"
        . h('کل سفارش‌ها: ') . b((string) $totalOrders) . "\n"
        . h('سفارش‌های تکمیل‌شده: ') . b((string) $ordersDone) . "\n"
        . h('درآمد کل: ') . b(fmtN($revenue) . ' تومان') . "\n"
        . h('تراکنش‌های در انتظار: ') . b((string) $pendingTx) . "\n"
        . h('تیکت‌های باز: ') . b((string) $openTickets);
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('admin_panel')]));
}

// ============================================================
// Admin: users
// ============================================================
function cbAdminUsers(array $ctx): void
{
    $d = db();
    $total = (int) $d->query('SELECT COUNT(*) c FROM users')->fetch()['c'];
    $rows = $d->query('SELECT * FROM users ORDER BY id DESC LIMIT 10')->fetchAll();

    $lines = [b(h('کاربران')) . ' — ' . h('مجموع: ' . $total)];
    $btnRows = [];
    foreach ($rows as $u) {
        $status = (int) $u['is_banned'] === 1 ? 'مسدود' : 'فعال';
        $lines[] = quoteBlock(h('#' . $u['telegram_id'] . ' — ' . ($u['full_name'] ?: '-') . ' — ' . $status));
        if ((int) $u['is_banned'] === 1) {
            $btnRows[] = [glassButton('رفع مسدودیت #' . $u['telegram_id'], 'admin_unban_' . $u['telegram_id'], 'green')];
        } else {
            $btnRows[] = [glassButton('مسدود کردن #' . $u['telegram_id'], 'admin_ban_' . $u['telegram_id'], 'red')];
        }
    }
    $btnRows[] = backRow('admin_panel');
    editMessageText($ctx['chatId'], $ctx['messageId'], implode("\n", $lines), kb($btnRows));
}

function cbAdminBanToggle(array $ctx, string $data, bool $ban): void
{
    $prefix = $ban ? 'admin_ban_' : 'admin_unban_';
    $targetTgId = (int) substr($data, strlen($prefix));
    $target = getUserByTelegramId($targetTgId);
    if (!$target) {
        return;
    }
    updateUserFields((int) $target['id'], ['is_banned' => $ban ? 1 : 0]);
    logAdminAction($ctx['tgId'], $ban ? 'ban_user' : 'unban_user', $targetTgId);
    answerCallback($ctx['cq']['id'], $ban ? 'کاربر مسدود شد' : 'کاربر آزاد شد');
    cbAdminUsers($ctx);
}

// ============================================================
// Admin: orders
// ============================================================
function cbAdminOrders(array $ctx): void
{
    $rows = db()->query('SELECT * FROM orders ORDER BY id DESC LIMIT 10')->fetchAll();
    $statusMap = ['pending' => 'در انتظار', 'paid' => 'پرداخت‌شده', 'processing' => 'در حال پردازش', 'done' => 'تکمیل‌شده', 'cancelled' => 'لغو شده'];

    $lines = [b(h('۱۰ سفارش اخیر'))];
    $btnRows = [];
    if (!$rows) {
        $lines[] = h('سفارشی ثبت نشده است.');
    }
    foreach ($rows as $o) {
        $lines[] = quoteBlock(
            h($o['order_id'] . ' — ' . $o['product_type'] . ' — ' . fmtN((int) $o['final_price']) . ' تومان — ' . ($statusMap[$o['status']] ?? $o['status']))
        );
        if (!in_array($o['status'], ['done', 'cancelled'], true)) {
            $btnRows[] = [
                glassButton('تکمیل ' . $o['order_id'], 'admin_order_done_' . $o['order_id'], 'green'),
                glassButton('لغو ' . $o['order_id'], 'admin_order_cancel_' . $o['order_id'], 'red'),
            ];
        }
    }
    $btnRows[] = backRow('admin_panel');
    editMessageText($ctx['chatId'], $ctx['messageId'], implode("\n", $lines), kb($btnRows));
}

function cbAdminOrderDone(array $ctx, string $data): void
{
    $orderId = substr($data, strlen('admin_order_done_'));
    $order = getOrderByOrderId($orderId);
    if (!$order || in_array($order['status'], ['done', 'cancelled'], true)) {
        return;
    }
    updateOrderStatus($orderId, 'done');
    logAdminAction($ctx['tgId'], 'order_done', null, ['order_id' => $orderId]);
    $owner = getUserById((int) $order['user_id']);
    if ($owner) {
        sendMessage((int) $owner['telegram_id'], b(pe('check') . ' ' . h(txt('order_done', $owner['language'] ?: DEFAULT_LANG))) . "\n" . quoteBlock(h('کد پیگیری: ') . b(h($orderId))));
    }
    answerCallback($ctx['cq']['id'], 'سفارش تکمیل شد');
    cbAdminOrders($ctx);
}

function cbAdminOrderCancel(array $ctx, string $data): void
{
    $orderId = substr($data, strlen('admin_order_cancel_'));
    $order = getOrderByOrderId($orderId);
    if (!$order || in_array($order['status'], ['done', 'cancelled'], true)) {
        return;
    }
    updateOrderStatus($orderId, 'cancelled');
    adjustBalance((int) $order['user_id'], (int) $order['final_price']);
    logAdminAction($ctx['tgId'], 'order_cancel', null, ['order_id' => $orderId]);
    $owner = getUserById((int) $order['user_id']);
    if ($owner) {
        sendMessage((int) $owner['telegram_id'], b(h('سفارش لغو شد و مبلغ آن به کیف پول شما بازگشت.')) . "\n" . quoteBlock(h('کد پیگیری: ') . b(h($orderId))));
    }
    answerCallback($ctx['cq']['id'], 'سفارش لغو و مبلغ بازگردانده شد');
    cbAdminOrders($ctx);
}

// ============================================================
// Admin: transactions (deposit approvals)
// ============================================================
function cbAdminTransactions(array $ctx): void
{
    $rows = db()->query("SELECT t.*, u.telegram_id AS user_tg_id, u.full_name AS user_name FROM transactions t
        JOIN users u ON u.id = t.user_id WHERE t.status = 'pending' ORDER BY t.id DESC LIMIT 10")->fetchAll();

    $lines = [b(h('تراکنش‌های در انتظار تایید'))];
    $btnRows = [];
    if (!$rows) {
        $lines[] = h('تراکنش در انتظاری وجود ندارد.');
    }
    foreach ($rows as $t) {
        $lines[] = quoteBlock(h('#' . $t['id'] . ' — ' . $t['user_name'] . ' — ' . fmtN((int) $t['amount']) . ' تومان'));
        $btnRows[] = [
            glassButton('تایید #' . $t['id'], 'admin_tx_confirm_' . $t['id'], 'green'),
            glassButton('رد #' . $t['id'], 'admin_tx_reject_' . $t['id'], 'red'),
        ];
    }
    $btnRows[] = backRow('admin_panel');
    editMessageText($ctx['chatId'], $ctx['messageId'], implode("\n", $lines), kb($btnRows));
}

function confirmDepositTransaction(int $txId, int $adminTgId): ?string
{
    $tx = getTransaction($txId);
    if (!$tx || $tx['status'] !== 'pending' || $tx['type'] !== 'deposit') {
        return null;
    }
    $user = getUserById((int) $tx['user_id']);
    if (!$user) {
        return null;
    }
    setTransactionStatus($txId, 'confirmed');
    adjustBalance((int) $user['id'], (int) $tx['amount']);
    logAdminAction($adminTgId, 'confirm_deposit', (int) $user['telegram_id'], ['transaction_id' => $txId, 'amount' => (int) $tx['amount']]);
    sendMessage((int) $user['telegram_id'], b(pe('check') . ' ' . h('واریز شما تایید شد.')) . "\n" . h('مبلغ: ' . fmtN((int) $tx['amount']) . ' تومان'));
    return (string) $user['telegram_id'];
}

function rejectDepositTransaction(int $txId, int $adminTgId): ?string
{
    $tx = getTransaction($txId);
    if (!$tx || $tx['status'] !== 'pending' || $tx['type'] !== 'deposit') {
        return null;
    }
    $user = getUserById((int) $tx['user_id']);
    if (!$user) {
        return null;
    }
    setTransactionStatus($txId, 'rejected');
    logAdminAction($adminTgId, 'reject_deposit', (int) $user['telegram_id'], ['transaction_id' => $txId]);
    sendMessage((int) $user['telegram_id'], b(h('واریز شما تایید نشد.')) . "\n" . h('برای اطلاعات بیشتر با پشتیبانی در تماس باشید.'));
    return (string) $user['telegram_id'];
}

function cbAdminTxConfirm(array $ctx, string $data): void
{
    $txId = (int) substr($data, strlen('admin_tx_confirm_'));
    $ok = confirmDepositTransaction($txId, $ctx['tgId']);
    answerCallback($ctx['cq']['id'], $ok ? 'تایید شد' : 'قابل تایید نیست');
    cbAdminTransactions($ctx);
}

function cbAdminTxReject(array $ctx, string $data): void
{
    $txId = (int) substr($data, strlen('admin_tx_reject_'));
    $ok = rejectDepositTransaction($txId, $ctx['tgId']);
    answerCallback($ctx['cq']['id'], $ok ? 'رد شد' : 'قابل رد کردن نیست');
    cbAdminTransactions($ctx);
}

// ============================================================
// Admin: tickets
// ============================================================
function cbAdminTickets(array $ctx): void
{
    $rows = db()->query("SELECT t.*, u.telegram_id AS user_tg_id, u.full_name AS user_name FROM tickets t
        JOIN users u ON u.id = t.user_id WHERE t.status = 'open' ORDER BY t.id DESC LIMIT 10")->fetchAll();

    $lines = [b(h('تیکت‌های باز'))];
    $btnRows = [];
    if (!$rows) {
        $lines[] = h('تیکت بازی وجود ندارد.');
    }
    foreach ($rows as $t) {
        $lines[] = quoteBlock(h('#' . $t['id'] . ' — ' . $t['user_name'] . "\n" . mb_substr((string) $t['body'], 0, 200)));
        $btnRows[] = [
            glassButton('پاسخ #' . $t['id'], 'admin_ticket_reply_' . $t['id'], 'blue'),
            glassButton('بستن #' . $t['id'], 'admin_ticket_close_' . $t['id'], 'gray'),
        ];
    }
    $btnRows[] = backRow('admin_panel');
    editMessageText($ctx['chatId'], $ctx['messageId'], implode("\n", $lines), kb($btnRows));
}

function cbAdminTicketReplyStart(array $ctx, string $data): void
{
    $ticketId = (int) substr($data, strlen('admin_ticket_reply_'));
    $st = &state($ctx['tgId']);
    $st['ticket_reply_id'] = $ticketId;
    editMessageText($ctx['chatId'], $ctx['messageId'], quoteBlock(h('متن پاسخ برای تیکت #' . $ticketId . ' را ارسال کنید:')), kb([backRow('admin_tickets')]));
}

function cbAdminTicketClose(array $ctx, string $data): void
{
    $ticketId = (int) substr($data, strlen('admin_ticket_close_'));
    $ticket = getTicket($ticketId);
    if ($ticket) {
        $stmt = db()->prepare("UPDATE tickets SET status = 'closed', updated_at = ? WHERE id = ?");
        $stmt->execute([nowTehran(), $ticketId]);
        $owner = getUserById((int) $ticket['user_id']);
        if ($owner) {
            sendMessage((int) $owner['telegram_id'], h('تیکت #' . $ticketId . ' توسط پشتیبانی بسته شد.'));
        }
    }
    answerCallback($ctx['cq']['id'], 'تیکت بسته شد');
    cbAdminTickets($ctx);
}

function handleAdminTicketReplySend(array $message, int $adminTgId): void
{
    $st = &state($adminTgId);
    $ticketId = (int) $st['ticket_reply_id'];
    $st['ticket_reply_id'] = null;
    $text = trim((string) ($message['text'] ?? ''));
    $ticket = getTicket($ticketId);
    if (!$ticket || $text === '') {
        sendMessage($message['chat']['id'], h('عملیات ناموفق بود.'));
        return;
    }
    $stmt = db()->prepare("UPDATE tickets SET admin_response = ?, status = 'answered', updated_at = ? WHERE id = ?");
    $stmt->execute([$text, nowTehran(), $ticketId]);
    $owner = getUserById((int) $ticket['user_id']);
    if ($owner) {
        sendMessage((int) $owner['telegram_id'], b(h('پاسخ پشتیبانی به تیکت #' . $ticketId)) . "\n\n" . quoteBlock(h($text)));
    }
    sendMessage($message['chat']['id'], h('پاسخ ارسال شد.'), adminKeyboard());
}

// ============================================================
// Admin: price management
// ============================================================
function cbAdminPrice(array $ctx): void
{
    $prices = getBasePrices();
    $text = b(h('مدیریت قیمت‌ها')) . "\n\n"
        . h('استارز: ' . fmtN((int) $prices['stars']) . ' (قیمت نهایی: ' . fmtN(getPrice('stars')) . ' تومان)') . "\n"
        . h('تون‌کوین: ' . fmtN((int) $prices['ton']) . ' (قیمت نهایی: ' . fmtN(getPrice('ton')) . ' تومان)') . "\n"
        . h('هدیه (پایه): ' . fmtN((int) $prices['gift_star']) . ' (قیمت نهایی: ' . fmtN(getPrice('gift_star')) . ' تومان)') . "\n"
        . h('پرمیوم ۳ ماهه: ' . fmtN(getPrice('premium', '3m')) . ' تومان') . "\n"
        . h('پرمیوم ۶ ماهه: ' . fmtN(getPrice('premium', '6m')) . ' تومان') . "\n"
        . h('پرمیوم ۱۲ ماهه: ' . fmtN(getPrice('premium', '12m')) . ' تومان') . "\n\n"
        . quoteBlock(h('برای تغییر، پیام «کلید مبلغ» ارسال کنید. مثال: stars 2700 یا premium_3m 1600000'));

    $st = &state($ctx['tgId']);
    $st['price_edit'] = true;
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('admin_panel')]));
}

function handleAdminPriceEditSend(array $message, int $adminTgId): void
{
    $st = &state($adminTgId);
    $st['price_edit'] = false;
    $text = trim((string) ($message['text'] ?? ''));
    $parts = preg_split('/\s+/', $text);
    if (count($parts) !== 2 || !is_numeric($parts[1])) {
        sendMessage($message['chat']['id'], h('فرمت نامعتبر است. مثال: stars 2700'), adminKeyboard());
        return;
    }
    [$key, $valueStr] = $parts;
    $value = (int) $valueStr;
    $prices = getBasePrices();

    if (in_array($key, ['stars', 'ton', 'gift_star'], true)) {
        setBasePriceOverride($key, $value);
    } elseif (in_array($key, ['premium_3m', 'premium_6m', 'premium_12m'], true)) {
        $plan = substr($key, strlen('premium_'));
        $prices['premium'][$plan] = $value;
        settingSet('base_prices', json_encode($prices, JSON_UNESCAPED_UNICODE));
    } else {
        sendMessage($message['chat']['id'], h('کلید ناشناخته: ' . $key), adminKeyboard());
        return;
    }
    logAdminAction($adminTgId, 'price_update', null, ['key' => $key, 'value' => $value]);
    sendMessage($message['chat']['id'], h('قیمت بروزرسانی شد.'), adminKeyboard());
}

// ============================================================
// Admin: menu/button layout builder
// ============================================================
function cbAdminLayout(array $ctx): void
{
    $rows = [];
    foreach (MENU_SECTIONS as $key => $label) {
        $rows[] = [glassButton($label, 'admin_layout_sec_' . $key, 'blue')];
    }
    $rows[] = backRow('admin_panel');
    editMessageText($ctx['chatId'], $ctx['messageId'], b(h('چیدمان دکمه‌ها')) . "\n\n" . h('بخشی که می‌خواید دکمه بهش اضافه کنید یا مدیریت کنید رو انتخاب کنید:'), kb($rows));
}

function renderLayoutSection(array $ctx, string $section): void
{
    $label = MENU_SECTIONS[$section] ?? $section;
    $buttons = getMenuButtons($section);

    $lines = [b(h('دکمه‌های بخش: ' . $label))];
    $rows = [];
    if (!$buttons) {
        $lines[] = h('هنوز دکمه‌ی سفارشی‌ای اضافه نشده.');
    }
    foreach ($buttons as $btn) {
        $typeLabel = ['url' => 'لینک', 'product' => 'محصول ' . fmtN((int) $btn['price']) . ' تومان', 'callback' => 'داخلی'][$btn['action_type']] ?? $btn['action_type'];
        $rowMark = (int) $btn['new_row'] === 1 ? 'ردیف جدید' : 'همون ردیف قبلی';
        $lines[] = quoteBlock(h($btn['label'] . ' (' . $typeLabel . ' — ' . $rowMark . ')'));
        $rows[] = [glassButton('حذف: ' . $btn['label'], 'admin_layout_del_' . $btn['id'], 'red')];
    }
    $rows[] = [glassButton('افزودن دکمه جدید', 'admin_layout_add_' . $section, 'green')];
    $rows[] = backRow('admin_layout');

    editMessageText($ctx['chatId'], $ctx['messageId'], implode("\n", $lines), kb($rows));
}

function cbAdminLayoutSection(array $ctx, string $data): void
{
    $section = substr($data, strlen('admin_layout_sec_'));
    if (!isset(MENU_SECTIONS[$section])) {
        return;
    }
    renderLayoutSection($ctx, $section);
}

function cbAdminLayoutDelete(array $ctx, string $data): void
{
    $id = (int) substr($data, strlen('admin_layout_del_'));
    $btn = getMenuButton($id);
    if (!$btn) {
        return;
    }
    deleteMenuButton($id);
    logAdminAction($ctx['tgId'], 'menu_button_delete', null, ['id' => $id, 'section' => $btn['section']]);
    renderLayoutSection($ctx, $btn['section']);
}

function cbAdminLayoutAddStart(array $ctx, string $data): void
{
    $section = substr($data, strlen('admin_layout_add_'));
    if (!isset(MENU_SECTIONS[$section])) {
        return;
    }
    $st = &state($ctx['tgId']);
    $st['data']['layout'] = ['section' => $section];
    $st['step'] = 'layout_label';

    editMessageText($ctx['chatId'], $ctx['messageId'], quoteBlock(h('متن (لیبل) دکمه جدید را ارسال کنید:')), kb([backRow('admin_layout_sec_' . $section)]));
}

function sendLayoutTypePrompt(int|string $chatId): void
{
    sendMessage($chatId, h('نوع این دکمه چیه؟'), kb([
        [glassButton('اتصال به یکی از بخش‌های ربات', 'layout_type_callback', 'blue')],
        [glassButton('لینک بیرونی (URL)', 'layout_type_url', 'green')],
        [glassButton('محصول جدید با قیمت', 'layout_type_product', 'purple')],
    ]));
}

function sendLayoutRowPrompt(int|string $chatId): void
{
    sendMessage($chatId, h('این دکمه کجای چیدمان قرار بگیره؟'), kb([
        [glassButton('ردیف جدید (زیر همه)', 'layout_row_new', 'blue')],
        [glassButton('کنار آخرین دکمه، همون ردیف', 'layout_row_same', 'green')],
    ]));
}

function cbLayoutTypeCallback(array $ctx): void
{
    $rows = [];
    foreach (MENU_INTERNAL_ACTIONS as $key => $label) {
        $rows[] = [glassButton($label, 'layout_cb_' . $key, 'blue')];
    }
    editMessageText($ctx['chatId'], $ctx['messageId'], h('دکمه به کدوم بخش وصل بشه؟'), kb($rows));
}

function cbLayoutPickCb(array $ctx, string $data): void
{
    $action = substr($data, strlen('layout_cb_'));
    if (!isset(MENU_INTERNAL_ACTIONS[$action])) {
        return;
    }
    $st = &state($ctx['tgId']);
    $st['data']['layout']['type'] = 'callback';
    $st['data']['layout']['value'] = $action;
    sendLayoutRowPrompt($ctx['chatId']);
}

function cbLayoutTypeUrl(array $ctx): void
{
    $st = &state($ctx['tgId']);
    $st['step'] = 'layout_url';
    editMessageText($ctx['chatId'], $ctx['messageId'], quoteBlock(h('آدرس (URL) کامل رو ارسال کنید (با https:// شروع بشه):')));
}

function cbLayoutTypeProduct(array $ctx): void
{
    $st = &state($ctx['tgId']);
    $st['step'] = 'layout_price';
    editMessageText($ctx['chatId'], $ctx['messageId'], quoteBlock(h('قیمت به تومان و در صورت نیاز توضیح رو ارسال کنید.')) . "\n" . h('مثال: 150000 پک ویژه تولد'));
}

function cbLayoutRow(array $ctx, string $data): void
{
    $st = &state($ctx['tgId']);
    if (!isset($st['data']['layout']['section'])) {
        return;
    }
    $st['data']['layout']['new_row'] = ($data === 'layout_row_new');

    $colorRows = [
        [glassButton('آبی', 'layout_color_blue', 'blue'), glassButton('سبز', 'layout_color_green', 'green')],
        [glassButton('قرمز', 'layout_color_red', 'red'), glassButton('بنفش', 'layout_color_purple', 'purple')],
        [glassButton('نارنجی', 'layout_color_orange', 'orange'), glassButton('زرد', 'layout_color_yellow', 'yellow')],
        [glassButton('طوسی', 'layout_color_gray', 'gray')],
    ];
    sendMessage($ctx['chatId'], h('رنگ دکمه رو انتخاب کنید:'), kb($colorRows));
}

function cbLayoutColor(array $ctx, string $data): void
{
    $color = substr($data, strlen('layout_color_'));
    global $COLOR_EMOJI;
    if (!isset($COLOR_EMOJI[$color])) {
        return;
    }
    $st = &state($ctx['tgId']);
    $l = $st['data']['layout'] ?? null;
    if (!$l || empty($l['section']) || empty($l['label']) || empty($l['type'])) {
        editMessageText($ctx['chatId'], $ctx['messageId'], h('اطلاعات ناقص است، دوباره تلاش کنید.'));
        return;
    }

    $actionValue = $l['value'] ?? null;
    $price = $l['type'] === 'product' ? (int) ($l['price'] ?? 0) : null;
    $description = $l['type'] === 'product' ? ($l['description'] ?? null) : null;

    addMenuButton($l['section'], $l['label'], $color, $l['type'], $actionValue, $price, $description, !empty($l['new_row']));
    logAdminAction($ctx['tgId'], 'menu_button_add', null, ['section' => $l['section'], 'label' => $l['label'], 'type' => $l['type']]);

    $st['data']['layout'] = null;
    editMessageText($ctx['chatId'], $ctx['messageId'], b(pe('check') . ' ' . h('دکمه اضافه شد.')));
    renderLayoutSection($ctx, $l['section']);
}

// ============================================================
// Custom-product purchase flow (from admin-added "product" menu buttons)
// ============================================================
function cbCustomProductInfo(array $ctx, string $data): void
{
    $id = (int) substr($data, strlen('mb_'));
    $btn = getMenuButton($id);
    if (!$btn || $btn['action_type'] !== 'product') {
        return;
    }
    $text = b(h($btn['label'])) . "\n\n"
        . ($btn['description'] ? h((string) $btn['description']) . "\n\n" : '')
        . h('قیمت: ') . b(fmtN((int) $btn['price']) . ' تومان') . "\n\n"
        . quoteBlock(h('برای نهایی‌کردن خرید تایید کنید.'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([
        [glassButton('تایید و پرداخت', 'mbc_' . $id, 'green')],
        backRow('products'),
    ]));
}

function cbCustomProductConfirm(array $ctx, string $data): void
{
    $id = (int) substr($data, strlen('mbc_'));
    $btn = getMenuButton($id);
    if (!$btn || $btn['action_type'] !== 'product') {
        return;
    }
    $price = (int) $btn['price'];
    $user = getUserByTelegramId($ctx['tgId']);
    if ((int) $user['balance'] < $price) {
        editMessageText($ctx['chatId'], $ctx['messageId'], b(h('موجودی کیف پول کافی نیست!')) . "\n" . h('موجودی: ' . fmtN((int) $user['balance']) . ' تومان'));
        return;
    }

    $order = createOrder((int) $user['id'], 'custom', ['menu_button_id' => $id, 'name' => $btn['label']], $price);
    adjustBalance((int) $user['id'], -$price);

    $text = b(pe('check') . ' ' . h(txt('order_success', $ctx['lang']))) . "\n\n"
        . h('محصول: ' . $btn['label']) . "\n"
        . h('مبلغ: ' . fmtN($price) . ' تومان') . "\n"
        . quoteBlock(h('کد پیگیری: ') . b(h($order['order_id'])));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, productKeyboard());

    notifyAdmins(b(h('سفارش جدید (محصول سفارشی: ' . $btn['label'] . ')')) . "\n\n" . orderAdminSummary($order, $user));
}

// ============================================================
// Admin: broadcast
// ============================================================
function cbAdminBroadcastStart(array $ctx): void
{
    $st = &state($ctx['tgId']);
    $st['broadcast'] = true;
    editMessageText($ctx['chatId'], $ctx['messageId'], quoteBlock(h('متن پیام همگانی را ارسال کنید:')), kb([backRow('admin_panel')]));
}

function handleAdminBroadcastSend(array $message, int $adminTgId): void
{
    $st = &state($adminTgId);
    $st['broadcast'] = false;
    $text = (string) ($message['text'] ?? '');
    if (trim($text) === '') {
        return;
    }
    $rows = db()->query('SELECT telegram_id FROM users')->fetchAll();
    $success = 0;
    foreach ($rows as $r) {
        if (sendMessage((int) $r['telegram_id'], h($text)) !== null) {
            $success++;
        }
    }
    logAdminAction($adminTgId, 'broadcast', null, ['recipients' => count($rows), 'success' => $success]);
    sendMessage($message['chat']['id'], h('ارسال همگانی انجام شد.') . "\n" . h('موفق: ' . $success . ' از ' . count($rows)), adminKeyboard());
}

// ============================================================
// Admin: backup / maintenance toggle
// ============================================================
function runBackup(): ?string
{
    $dir = __DIR__ . '/backups';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $filename = $dir . '/backup_' . date('Ymd_His') . '.db';
    if (!copy(DB_PATH, $filename)) {
        return null;
    }
    $size = filesize($filename) ?: 0;
    $stmt = db()->prepare('INSERT INTO backups (filename, size, created_at) VALUES (?, ?, ?)');
    $stmt->execute([basename($filename), $size, nowTehran()]);

    $caption = b(h('پشتیبان‌گیری')) . "\n" . h(nowTehran());
    sendDocumentFile('@' . REPORTS_CHANNEL, $filename, $caption);
    return $filename;
}

function cbAdminBackupNow(array $ctx): void
{
    editMessageText($ctx['chatId'], $ctx['messageId'], h('در حال تهیه نسخه پشتیبان...'));
    $file = runBackup();
    $text = $file
        ? b(pe('check') . ' ' . h('نسخه پشتیبان با موفقیت تهیه و ارسال شد.'))
        : b(h('خطا در تهیه نسخه پشتیبان.'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('admin_panel')]));
}

function cbAdminToggle(array $ctx): void
{
    $current = settingGet('maintenance') === '1';
    settingSet('maintenance', $current ? '0' : '1');
    logAdminAction($ctx['tgId'], 'toggle_maintenance', null, ['enabled' => !$current]);
    $text = $current
        ? b(pe('check') . ' ' . h('ربات از حالت تعمیر خارج شد.'))
        : b(h('ربات در حالت تعمیر قرار گرفت.'));
    editMessageText($ctx['chatId'], $ctx['messageId'], $text, kb([backRow('admin_panel')]));
}

// ============================================================
// Notify admins helper
// ============================================================
function notifyAdmins(string $html): void
{
    global $ADMINS;
    foreach (array_keys($ADMINS) as $adminId) {
        sendMessage($adminId, $html);
    }
}

function orderAdminSummary(array $order, array $user): string
{
    return h('کاربر: ' . $user['full_name'] . ' (@' . ($user['username'] ?? '-') . ')') . "\n"
        . h('آیدی: ' . $user['telegram_id']) . "\n"
        . h('مبلغ: ' . fmtN((int) $order['final_price']) . ' تومان') . "\n"
        . quoteBlock(h('کد پیگیری: ') . b(h($order['order_id'])));
}

// ============================================================
// Text-step handler (multi-step conversations)
// ============================================================
function handleTextInput(array $message, array $user, ?string $step): void
{
    $tgId = (int) $user['telegram_id'];
    $chatId = $message['chat']['id'];
    $text = trim((string) ($message['text'] ?? ''));
    $lang = $user['language'] ?: DEFAULT_LANG;
    $st = &state($tgId);

    if ($step === 'layout_label' && isAdmin($tgId)) {
        if ($text === '') {
            sendMessage($chatId, h('متن دکمه نمی‌تواند خالی باشد.'));
            return;
        }
        $st['data']['layout']['label'] = $text;
        $st['step'] = null;
        sendLayoutTypePrompt($chatId);
        return;
    }

    if ($step === 'layout_url' && isAdmin($tgId)) {
        if (!preg_match('#^https?://#i', $text)) {
            sendMessage($chatId, h('آدرس باید با http:// یا https:// شروع بشه.'));
            return;
        }
        $st['data']['layout']['type'] = 'url';
        $st['data']['layout']['value'] = $text;
        $st['step'] = null;
        sendLayoutRowPrompt($chatId);
        return;
    }

    if ($step === 'layout_price' && isAdmin($tgId)) {
        $parts = preg_split('/\s+/', $text, 2);
        if (!isset($parts[0]) || !ctype_digit($parts[0]) || (int) $parts[0] <= 0) {
            sendMessage($chatId, h('فرمت نامعتبر است. مثال: 150000 پک ویژه تولد'));
            return;
        }
        $st['data']['layout']['type'] = 'product';
        $st['data']['layout']['price'] = (int) $parts[0];
        $st['data']['layout']['description'] = $parts[1] ?? null;
        $st['step'] = null;
        sendLayoutRowPrompt($chatId);
        return;
    }

    if ($step === 'stars_count') {
        if (!ctype_digit($text) || (int) $text < 50) {
            sendMessage($chatId, h('لطفاً عدد معتبر (حداقل 50) وارد کنید.'));
            return;
        }
        $count = (int) $text;
        $price = $st['data']['price_per_star'] ?? getPrice('stars');
        $total = $count * $price;

        if ((int) $user['balance'] < $total) {
            sendMessage($chatId, b(h('موجودی کیف پول کافی نیست!')) . "\n" . h('موجودی: ' . fmtN((int) $user['balance']) . ' تومان') . "\n" . h('مبلغ لازم: ' . fmtN($total) . ' تومان'));
            return;
        }

        $order = createOrder((int) $user['id'], 'stars', ['count' => $count, 'price_per' => $price], $total);
        adjustBalance((int) $user['id'], -$total);

        sendMessage($chatId, b(pe('star') . ' ' . h(txt('order_success', $lang))) . "\n\n"
            . h('تعداد: ' . $count) . "\n"
            . h('مبلغ: ' . fmtN($total) . ' تومان') . "\n"
            . quoteBlock(h('کد پیگیری: ') . b(h($order['order_id']))), productKeyboard());
        $st['step'] = null;
        notifyAdmins(b(pe('star') . ' ' . h('سفارش جدید استارز')) . "\n\n" . orderAdminSummary($order, $user));
        return;
    }

    if ($step === 'deposit_amount') {
        if (!ctype_digit($text) || (int) $text <= 0) {
            sendMessage($chatId, h('لطفاً یک عدد معتبر ارسال کنید.'));
            return;
        }
        $amount = (int) $text;
        $today = todayStr();
        $used = ($user['daily_deposit_date'] === $today) ? (int) $user['daily_deposit_used'] : 0;
        if ($used + $amount > DAILY_DEPOSIT_LIMIT) {
            $remaining = DAILY_DEPOSIT_LIMIT - $used;
            sendMessage($chatId, h('سقف واریز روزانه ' . fmtN(DAILY_DEPOSIT_LIMIT) . ' تومان است.') . "\n" . h('باقیمانده: ' . fmtN(max(0, $remaining)) . ' تومان'));
            return;
        }
        $st['data']['deposit_amount'] = $amount;
        $st['step'] = 'deposit_receipt';
        sendMessage($chatId, h('مبلغ ' . fmtN($amount) . ' تومان ثبت شد.') . "\n\n"
            . quoteBlock(h('لطفاً تصویر فیش واریزی را ارسال کنید.') . "\n"
                . h('شماره کارت: ') . b('6037-9918-1234-5678') . "\n"
                . h('به نام: ') . b(h('فروشگاه GiftIx'))));
        return;
    }

    if ($step === 'track_order') {
        $order = getOrderByOrderId($text);
        if (!$order) {
            sendMessage($chatId, h('سفارشی با این کد پیدا نشد.'));
            return;
        }
        $statusMap = ['pending' => 'در انتظار', 'paid' => 'پرداخت‌شده', 'processing' => 'در حال پردازش', 'done' => 'تکمیل‌شده', 'cancelled' => 'لغو شده'];
        sendMessage($chatId, b(h('وضعیت سفارش')) . "\n\n"
            . h('کد: ' . $order['order_id']) . "\n"
            . h('نوع: ' . $order['product_type']) . "\n"
            . h('مبلغ: ' . fmtN((int) $order['price']) . ' تومان') . "\n"
            . h('وضعیت: ' . ($statusMap[$order['status']] ?? $order['status'])) . "\n"
            . h('تاریخ ثبت: ' . $order['created_at']), productKeyboard());
        $st['step'] = null;
        return;
    }

    if ($step === 'support_message') {
        $ticketId = createTicket((int) $user['id'], 'پشتیبانی', $text);
        notifyAdmins(b(h('تیکت جدید پشتیبانی')) . "\n\n"
            . h('کاربر: ' . $user['full_name'] . ' (@' . ($user['username'] ?? '-') . ')') . "\n"
            . h('آیدی: ' . $tgId) . "\n"
            . quoteBlock(h($text)) . "\n"
            . h('شماره تیکت: ' . $ticketId));
        sendMessage($chatId, h('پیام شما ثبت شد و در اسرع وقت پاسخ داده می‌شود.'), productKeyboard());
        $st['step'] = null;
        return;
    }

    if ($step === 'premium_username') {
        $username = ltrim($text, '@');
        if ($username === '') {
            sendMessage($chatId, h('یوزرنیم معتبر ارسال کنید.'));
            return;
        }
        $st['data']['premium_username'] = $username;
        $st['step'] = 'premium_confirm';
        $plan = $st['data']['premium_plan'] ?? '3m';
        $price = getPrice('premium', $plan);
        sendMessage($chatId, h('یوزرنیم: @' . $username) . "\n"
            . h('پلن: ' . $plan) . "\n"
            . h('مبلغ: ' . fmtN($price) . ' تومان') . "\n\n"
            . quoteBlock(h('برای نهایی‌کردن خرید تایید کنید.')), kb([
                [glassButton('تایید و پرداخت', 'premium_confirm_yes', 'green')],
                backRow('products'),
            ]));
        return;
    }

    if ($step === 'ton_amount') {
        if (!is_numeric($text) || (float) $text < 0.1) {
            sendMessage($chatId, h('مقدار وارد شده معتبر نیست. حداقل 0.1 TON.'));
            return;
        }
        $amount = (float) $text;
        $price = $st['data']['price_per_ton'] ?? getPrice('ton');
        $total = (int) round($amount * $price);
        $st['data']['ton_amount'] = $amount;
        $st['data']['ton_total'] = $total;
        $st['step'] = 'ton_wallet';
        sendMessage($chatId, h('مقدار: ' . $amount . ' TON') . "\n" . h('مبلغ: ' . fmtN($total) . ' تومان') . "\n\n"
            . quoteBlock(h('آدرس کیف‌پول TON خود را برای واریز ارسال کنید:')));
        return;
    }

    if ($step === 'ton_wallet') {
        if (mb_strlen($text) < 20) {
            sendMessage($chatId, h('آدرس کیف‌پول معتبر نیست.'));
            return;
        }
        $st['data']['ton_wallet'] = $text;
        $st['step'] = 'ton_memo';
        sendMessage($chatId, h('در صورت نیاز، ممو (Memo) کیف‌پول را ارسال کنید، در غیر این صورت "-" ارسال کنید.'));
        return;
    }

    if ($step === 'ton_memo') {
        $memo = ($text !== '' && $text !== '-') ? $text : null;
        $amount = $st['data']['ton_amount'] ?? 0;
        $total = (int) ($st['data']['ton_total'] ?? 0);
        $wallet = $st['data']['ton_wallet'] ?? '';

        if ((int) $user['balance'] < $total) {
            sendMessage($chatId, b(h('موجودی کیف پول کافی نیست!')) . "\n" . h('موجودی: ' . fmtN((int) $user['balance']) . ' تومان'));
            return;
        }

        $order = createOrder((int) $user['id'], 'ton', ['amount' => $amount, 'wallet' => $wallet, 'memo' => $memo], $total);
        adjustBalance((int) $user['id'], -$total);

        sendMessage($chatId, b(pe('rocket') . ' ' . h(txt('order_success', $lang))) . "\n\n"
            . h('مقدار: ' . $amount . ' TON') . "\n"
            . h('مبلغ: ' . fmtN($total) . ' تومان') . "\n"
            . quoteBlock(h('کد پیگیری: ') . b(h($order['order_id']))), productKeyboard());
        $st['step'] = null;
        notifyAdmins(b(pe('rocket') . ' ' . h('سفارش جدید تون‌کوین')) . "\n\n" . orderAdminSummary($order, $user));
        return;
    }

    if ($step === 'gift_username') {
        $username = ltrim($text, '@');
        if ($username === '') {
            sendMessage($chatId, h('یوزرنیم معتبر ارسال کنید.'));
            return;
        }
        $st['data']['gift_username'] = $username;
        $st['step'] = 'gift_confirm';
        global $GIFTS;
        $key = $st['data']['gift_key'] ?? 'teddy';
        $info = $GIFTS[$key] ?? $GIFTS['teddy'];
        $price = getPrice('gift_star') * $info['mult'];
        sendMessage($chatId, h('هدیه: ' . $info['label']) . "\n"
            . h('گیرنده: @' . $username) . "\n"
            . h('مبلغ: ' . fmtN($price) . ' تومان') . "\n\n"
            . quoteBlock(h('برای نهایی‌کردن خرید تایید کنید.')), kb([
                [glassButton('تایید و پرداخت', 'gift_confirm_yes', 'green')],
                backRow('products'),
            ]));
        return;
    }
}

// ============================================================
// Deposit receipt (photo)
// ============================================================
function handleDepositReceipt(array $message, array $user): void
{
    $tgId = (int) $user['telegram_id'];
    $chatId = $message['chat']['id'];
    $st = &state($tgId);
    $photos = $message['photo'];
    $fileId = end($photos)['file_id'];
    $amount = (int) ($st['data']['deposit_amount'] ?? 0);

    $txId = addTransaction((int) $user['id'], $amount, 'deposit', 'واریز ' . fmtN($amount) . ' تومان', 'pending');

    $today = todayStr();
    $newUsed = ($user['daily_deposit_date'] === $today ? (int) $user['daily_deposit_used'] : 0) + $amount;
    updateUserFields((int) $user['id'], ['daily_deposit_used' => $newUsed, 'daily_deposit_date' => $today]);

    $caption = b(h('درخواست واریز جدید')) . "\n\n"
        . h('کاربر: ' . $user['full_name'] . ' (@' . ($user['username'] ?? '-') . ')') . "\n"
        . h('آیدی: ' . $tgId) . "\n"
        . h('مبلغ: ' . fmtN($amount) . ' تومان') . "\n"
        . h('شماره تراکنش: ' . $txId) . "\n\n"
        . quoteBlock(h('برای تایید: /confirm_' . $txId) . "\n" . h('برای رد: /reject_' . $txId));

    global $ADMINS;
    foreach (array_keys($ADMINS) as $adminId) {
        sendPhotoByFileId($adminId, $fileId, $caption);
    }

    sendMessage($chatId, h('رسید شما ثبت شد و پس از بررسی توسط پشتیبانی، موجودی شما بروزرسانی خواهد شد.'), productKeyboard());
    $st['step'] = null;
}

// ============================================================
// Admin commands: /confirm_<id> /reject_<id>
// ============================================================
function handleConfirmDepositCommand(array $message, int $adminTgId): void
{
    $text = trim((string) ($message['text'] ?? ''));
    $txId = (int) substr($text, strlen('/confirm_'));
    $result = confirmDepositTransaction($txId, $adminTgId);
    sendMessage($message['chat']['id'], $result ? h('تراکنش #' . $txId . ' تایید شد.') : h('تراکنش قابل تایید نیست.'));
}

function handleRejectDepositCommand(array $message, int $adminTgId): void
{
    $text = trim((string) ($message['text'] ?? ''));
    $txId = (int) substr($text, strlen('/reject_'));
    $result = rejectDepositTransaction($txId, $adminTgId);
    sendMessage($message['chat']['id'], $result ? h('تراکنش #' . $txId . ' رد شد.') : h('تراکنش قابل رد کردن نیست.'));
}

// ============================================================
// Callback query dispatcher
// ============================================================
function handleCallbackQuery(array $cq): void
{
    $data = (string) ($cq['data'] ?? '');
    $from = $cq['from'];
    $tgId = (int) $from['id'];
    $msg = $cq['message'] ?? null;
    if ($msg === null) {
        answerCallback($cq['id']);
        return;
    }
    $chatId = $msg['chat']['id'];
    $messageId = (int) $msg['message_id'];

    $username = $from['username'] ?? null;
    $fullName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: ($username ?? (string) $tgId);
    $user = getUserByTelegramId($tgId) ?? getOrCreateUser($tgId, $username, $fullName, null);

    if ((int) $user['is_banned'] === 1) {
        answerCallback($cq['id'], txt('banned', $user['language'] ?: DEFAULT_LANG), true);
        return;
    }

    $lang = $user['language'] ?: DEFAULT_LANG;
    answerCallback($cq['id']);

    if (settingGet('maintenance') === '1' && !isAdmin($tgId) && $data !== 'check_join') {
        editMessageText($chatId, $messageId, h(txt('maintenance', $lang)));
        return;
    }

    $ctx = ['cq' => $cq, 'user' => $user, 'lang' => $lang, 'chatId' => $chatId, 'messageId' => $messageId, 'tgId' => $tgId];

    switch (true) {
        case $data === 'check_join': cbCheckJoin($ctx); return;
        case $data === 'main_menu': cbMainMenu($ctx); return;
        case $data === 'products': cbProducts($ctx); return;
        case $data === 'buy_stars': cbBuyStars($ctx); return;
        case $data === 'buy_premium': cbBuyPremium($ctx); return;
        case $data === 'premium_confirm_yes': cbPremiumConfirm($ctx); return;
        case str_starts_with($data, 'premium_'): cbPremiumSelect($ctx, $data); return;
        case $data === 'buy_gift': cbBuyGift($ctx); return;
        case $data === 'gift_confirm_yes': cbGiftConfirm($ctx); return;
        case str_starts_with($data, 'gift_'): cbGiftSelect($ctx, $data); return;
        case $data === 'buy_ton': cbBuyTon($ctx); return;
        case $data === 'wallet': cbWallet($ctx); return;
        case $data === 'deposit': cbDeposit($ctx); return;
        case $data === 'transactions': cbTransactions($ctx); return;
        case $data === 'track_order': cbTrackOrder($ctx); return;
        case $data === 'support': cbSupport($ctx); return;
        case $data === 'settings': cbSettings($ctx); return;
        case str_starts_with($data, 'set_lang_'): cbSetLang($ctx, $data); return;
        case str_starts_with($data, 'mbc_'): cbCustomProductConfirm($ctx, $data); return;
        case str_starts_with($data, 'mb_'): cbCustomProductInfo($ctx, $data); return;
    }

    if (!isAdmin($tgId)) {
        editMessageText($chatId, $messageId, h(txt('not_admin', $lang)));
        return;
    }

    switch (true) {
        case $data === 'admin_panel': cbAdminPanel($ctx); return;
        case $data === 'admin_stats': cbAdminStats($ctx); return;
        case $data === 'admin_users': cbAdminUsers($ctx); return;
        case str_starts_with($data, 'admin_unban_'): cbAdminBanToggle($ctx, $data, false); return;
        case str_starts_with($data, 'admin_ban_'): cbAdminBanToggle($ctx, $data, true); return;
        case $data === 'admin_orders': cbAdminOrders($ctx); return;
        case str_starts_with($data, 'admin_order_done_'): cbAdminOrderDone($ctx, $data); return;
        case str_starts_with($data, 'admin_order_cancel_'): cbAdminOrderCancel($ctx, $data); return;
        case $data === 'admin_transactions': cbAdminTransactions($ctx); return;
        case str_starts_with($data, 'admin_tx_confirm_'): cbAdminTxConfirm($ctx, $data); return;
        case str_starts_with($data, 'admin_tx_reject_'): cbAdminTxReject($ctx, $data); return;
        case $data === 'admin_tickets': cbAdminTickets($ctx); return;
        case str_starts_with($data, 'admin_ticket_reply_'): cbAdminTicketReplyStart($ctx, $data); return;
        case str_starts_with($data, 'admin_ticket_close_'): cbAdminTicketClose($ctx, $data); return;
        case $data === 'admin_price': cbAdminPrice($ctx); return;
        case $data === 'admin_layout': cbAdminLayout($ctx); return;
        case str_starts_with($data, 'admin_layout_sec_'): cbAdminLayoutSection($ctx, $data); return;
        case str_starts_with($data, 'admin_layout_add_'): cbAdminLayoutAddStart($ctx, $data); return;
        case str_starts_with($data, 'admin_layout_del_'): cbAdminLayoutDelete($ctx, $data); return;
        case $data === 'layout_type_callback': cbLayoutTypeCallback($ctx); return;
        case $data === 'layout_type_url': cbLayoutTypeUrl($ctx); return;
        case $data === 'layout_type_product': cbLayoutTypeProduct($ctx); return;
        case str_starts_with($data, 'layout_cb_'): cbLayoutPickCb($ctx, $data); return;
        case $data === 'layout_row_new' || $data === 'layout_row_same': cbLayoutRow($ctx, $data); return;
        case str_starts_with($data, 'layout_color_'): cbLayoutColor($ctx, $data); return;
        case $data === 'admin_broadcast': cbAdminBroadcastStart($ctx); return;
        case $data === 'admin_backup': cbAdminBackupNow($ctx); return;
        case $data === 'admin_toggle': cbAdminToggle($ctx); return;
    }
}

// ============================================================
// Message dispatcher
// ============================================================
function handleMessage(array $message): void
{
    $from = $message['from'] ?? null;
    if ($from === null || !empty($from['is_bot'])) {
        return;
    }
    $tgId = (int) $from['id'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? null;

    if ($text !== null && str_starts_with($text, '/start')) {
        handleStartCommand($message);
        return;
    }

    $username = $from['username'] ?? null;
    $fullName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: ($username ?? (string) $tgId);
    $user = getUserByTelegramId($tgId) ?? getOrCreateUser($tgId, $username, $fullName, null);

    if ((int) $user['is_banned'] === 1) {
        sendMessage($chatId, h(txt('banned', $user['language'] ?: DEFAULT_LANG)));
        return;
    }

    if ($text !== null && str_starts_with($text, '/confirm_') && isAdmin($tgId)) {
        handleConfirmDepositCommand($message, $tgId);
        return;
    }
    if ($text !== null && str_starts_with($text, '/reject_') && isAdmin($tgId)) {
        handleRejectDepositCommand($message, $tgId);
        return;
    }
    if ($text !== null && $text === '/admin' && isAdmin($tgId)) {
        sendMessage($chatId, b(pe('admin') . ' ' . h(txt('admin_panel', $user['language'] ?: DEFAULT_LANG))), adminKeyboard());
        return;
    }

    $st = &state($tgId);

    if (!empty($st['broadcast']) && isAdmin($tgId)) {
        handleAdminBroadcastSend($message, $tgId);
        return;
    }
    if (!empty($st['ticket_reply_id']) && isAdmin($tgId)) {
        handleAdminTicketReplySend($message, $tgId);
        return;
    }
    if (!empty($st['price_edit']) && isAdmin($tgId)) {
        handleAdminPriceEditSend($message, $tgId);
        return;
    }

    if (($st['step'] ?? null) === 'deposit_receipt') {
        if (!empty($message['photo'])) {
            handleDepositReceipt($message, $user);
        } else {
            sendMessage($chatId, h('لطفاً تصویر فیش واریزی را ارسال کنید.'));
        }
        return;
    }

    if ($text === null) {
        return;
    }

    handleTextInput($message, $user, $st['step'] ?? null);
}

function processUpdate(array $update): void
{
    $tgId = null;
    if (isset($update['callback_query']['from']['id'])) {
        $tgId = (int) $update['callback_query']['from']['id'];
    } elseif (isset($update['message']['from']['id'])) {
        $tgId = (int) $update['message']['from']['id'];
    }
    if ($tgId !== null) {
        loadState($tgId);
    }

    if (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query']);
    } elseif (isset($update['message'])) {
        handleMessage($update['message']);
    }

    if ($tgId !== null) {
        saveState($tgId);
    }
}

// ============================================================
// Main loop
// ============================================================
function readOffset(): int
{
    if (is_file(OFFSET_FILE)) {
        return (int) trim((string) file_get_contents(OFFSET_FILE));
    }
    return 0;
}

function writeOffset(int $offset): void
{
    file_put_contents(OFFSET_FILE, (string) $offset);
}

function main(): void
{
    initDb();
    logMsg('GiftIx PHP bot started.');

    $offset = readOffset();
    $lastBackup = time();

    while (true) {
        $updates = apiRequest('getUpdates', [
            'offset' => $offset,
            'timeout' => 30,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        if ($updates === null) {
            sleep(3);
            continue;
        }

        foreach ($updates as $update) {
            $offset = (int) $update['update_id'] + 1;
            writeOffset($offset);
            try {
                processUpdate($update);
            } catch (Throwable $e) {
                logMsg('Error processing update: ' . $e->getMessage());
            }
        }

        if (time() - $lastBackup >= BACKUP_INTERVAL_SECONDS) {
            try {
                runBackup();
            } catch (Throwable $e) {
                logMsg('Backup failed: ' . $e->getMessage());
            }
            $lastBackup = time();
        }
    }
}

// ============================================================
// Webhook management (setWebhook/deleteWebhook) + secret-token verification
// ============================================================
const WEBHOOK_SECRET_FILE = __DIR__ . '/.giftix_webhook_secret';

function getOrCreateWebhookSecret(): string
{
    if (is_file(WEBHOOK_SECRET_FILE)) {
        $existing = trim((string) file_get_contents(WEBHOOK_SECRET_FILE));
        if ($existing !== '') {
            return $existing;
        }
    }
    $secret = bin2hex(random_bytes(32));
    file_put_contents(WEBHOOK_SECRET_FILE, $secret);
    chmod(WEBHOOK_SECRET_FILE, 0600);
    return $secret;
}

function cliWebhookSet(string $url): void
{
    $secret = getOrCreateWebhookSecret();
    $res = apiRequest('setWebhook', [
        'url' => $url,
        'secret_token' => $secret,
        'allowed_updates' => ['message', 'callback_query'],
    ]);
    if ($res === null) {
        fwrite(STDERR, "Failed to set webhook. Check the URL is a public HTTPS address with a trusted certificate.\n");
        exit(1);
    }
    echo "Webhook registered: {$url}\n";
    echo "Telegram will now push updates instead of long polling — do not also run 'php giftix_bot.php run'.\n";
}

function cliWebhookDelete(): void
{
    $res = apiRequest('deleteWebhook', ['drop_pending_updates' => false]);
    echo $res !== null
        ? "Webhook removed. You can go back to polling with: php giftix_bot.php run\n"
        : "Failed to delete webhook.\n";
}

function cliWebhookInfo(): void
{
    $res = apiRequest('getWebhookInfo', []);
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

// ============================================================
// Entry point — CLI (polling / webhook management) or web (webhook receiver)
// ============================================================
if (PHP_SAPI === 'cli') {
    initDb();
    $cmd = $argv[1] ?? 'run';
    switch ($cmd) {
        case 'webhook:set':
            if (empty($argv[2])) {
                fwrite(STDERR, "Usage: php giftix_bot.php webhook:set https://your-domain/path/giftix_bot.php\n");
                exit(1);
            }
            cliWebhookSet($argv[2]);
            break;
        case 'webhook:delete':
            cliWebhookDelete();
            break;
        case 'webhook:info':
            cliWebhookInfo();
            break;
        case 'run':
            main();
            break;
        default:
            fwrite(STDERR, "Unknown command '{$cmd}'. Use: run | webhook:set <url> | webhook:delete | webhook:info\n");
            exit(1);
    }
    exit(0);
}

// --- Web server (webhook) request ---
initDb();
header('Content-Type: application/json');

// Secret-token check is only enforced if a secret was actually issued (i.e. the deployer
// ran `webhook:set` from a terminal at least once). Webhooks registered the simple way —
// just opening https://api.telegram.org/bot<TOKEN>/setWebhook?url=... in a browser, with no
// secret_token param — skip this check entirely, since there is nothing to compare against.
if (is_file(WEBHOOK_SECRET_FILE)) {
    $expectedSecret = trim((string) file_get_contents(WEBHOOK_SECRET_FILE));
    $incomingSecret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($expectedSecret !== '' && !hash_equals($expectedSecret, $incomingSecret)) {
        logMsg('Webhook request rejected: secret token mismatch (got ' . ($incomingSecret === '' ? '<empty>' : strlen($incomingSecret) . ' chars') . ', remote ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ')');
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'invalid secret token']);
        exit;
    }
}

$raw = file_get_contents('php://input');
$update = json_decode((string) $raw, true);
if (is_array($update)) {
    try {
        processUpdate($update);
    } catch (Throwable $e) {
        logMsg('Webhook error: ' . $e->getMessage());
    }
}

http_response_code(200);
echo json_encode(['ok' => true]);

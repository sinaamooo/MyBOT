<?php
/**
 * سیستم خرید و فروش اعضای تلگرام - فایل واحد
 * ساده و شامل - بدون دیتابیس
 */

// ⚙️ تنظیمات
define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');

// ایجاد پوشه داده اگر وجود نداشت
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// 📚 توابع ذخیره و بارگیری داده
function saveData($name, $data) {
    file_put_contents(DATA_DIR . "/$name.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadData($name) {
    $file = DATA_DIR . "/$name.json";
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function getUser($userId) {
    $users = loadData('users');
    return $users[$userId] ?? null;
}

function saveUser($userId, $data) {
    $users = loadData('users');
    $users[$userId] = array_merge($users[$userId] ?? [], $data);
    $users[$userId]['telegram_id'] = $userId;
    saveData('users', $users);
    return $users[$userId];
}

// 🔌 API تلگرام
function sendMessage($chatId, $text, $buttons = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($buttons) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($ch);
    curl_close($ch);
}

function editMessage($chatId, $msgId, $text, $buttons = null) {
    $data = [
        'chat_id' => $chatId,
        'message_id' => $msgId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];

    if ($buttons) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/editMessageText");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_exec($ch);
    curl_close($ch);
}

// 🎨 ایجاد دکمه‌ها
function btn($text, $data, $emoji = '') {
    return [['text' => $emoji . ' ' . $text, 'callback_data' => $data]];
}

// 📋 منوی اصلی
function mainMenu($userId) {
    $user = getUser($userId);
    $balance = $user['balance'] ?? 0;

    $text = "👋 <b>خوش آمدید!</b>\n\n";
    $text .= "💰 <b>موجودی:</b> " . number_format($balance) . "\n";
    $text .= "🎯 <b>لطفاً انتخاب کنید:</b>\n";

    $buttons = [
        btn('خرید اعضا', 'buy', '🛍️'),
        btn('فروش اعضا', 'sell', '💰'),
        btn('تاریخچه', 'history', '📋'),
        btn('تنظیمات', 'settings', '⚙️'),
    ];

    if ($userId == ADMIN_ID) {
        $buttons[] = btn('پنل مدیریت', 'admin', '🔐');
    }

    return [$text, $buttons];
}

// ⚙️ منوی تنظیمات
function settingsMenu($userId) {
    $user = getUser($userId);
    $phone = $user['phone'] ?? '❌ تنظیم نشده';
    $payment = $user['payment_method'] ?? 'انتخاب نشده';

    $text = "⚙️ <b>تنظیمات</b>\n\n";
    $text .= "📱 <b>شماره:</b> $phone\n";
    $text .= "💳 <b>روش پرداخت:</b> $payment\n";

    $buttons = [
        btn('شماره تلفن', 'set_phone', '📱'),
        btn('روش پرداخت', 'set_payment', '💳'),
        btn('بازگشت', 'menu', '◀️'),
    ];

    return [$text, $buttons];
}

// 🔐 پنل مدیریت
function adminPanel() {
    $text = "🔐 <b>پنل مدیریت</b>\n\n";
    $text .= "انتخاب کنید:\n";

    $buttons = [
        btn('کاربران', 'admin_users', '👥'),
        btn('خریدها', 'admin_purchases', '🛍️'),
        btn('فروش‌ها', 'admin_sales', '📊'),
        btn('بازگشت', 'menu', '◀️'),
    ];

    return [$text, $buttons];
}

// 🌐 پردازش webhook
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    die;
}

// پیام متنی
if (isset($input['message'])) {
    $msg = $input['message'];
    $userId = $msg['from']['id'];
    $chatId = $msg['chat']['id'];
    $text = $msg['text'] ?? '';

    // ایجاد کاربر اگر نبود
    if (!getUser($userId)) {
        saveUser($userId, [
            'first_name' => $msg['from']['first_name'],
            'balance' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // دستورات
    if ($text == '/start') {
        list($msg_text, $buttons) = mainMenu($userId);
        sendMessage($chatId, $msg_text, $buttons);
    } elseif ($text == '/balance') {
        $user = getUser($userId);
        $balance = $user['balance'] ?? 0;
        sendMessage($chatId, "💰 <b>موجودی شما:</b>\n" . number_format($balance));
    } elseif ($text == '/help') {
        sendMessage($chatId, "<b>دستورات:</b>\n/start - منوی اصلی\n/balance - موجودی\n/help - راهنما");
    }
}

// callback query
if (isset($input['callback_query'])) {
    $query = $input['callback_query'];
    $userId = $query['from']['id'];
    $chatId = $query['from']['id'];
    $data = $query['data'];
    $msgId = $query['message']['message_id'];

    // مسیریابی
    if ($data == 'menu') {
        list($msg_text, $buttons) = mainMenu($userId);
        editMessage($chatId, $msgId, $msg_text, $buttons);
    }
    elseif ($data == 'settings') {
        list($msg_text, $buttons) = settingsMenu($userId);
        editMessage($chatId, $msgId, $msg_text, $buttons);
    }
    elseif ($data == 'buy') {
        editMessage($chatId, $msgId, "🛍️ <b>خرید اعضا</b>\n\nبزودی فعال می‌شود");
    }
    elseif ($data == 'sell') {
        editMessage($chatId, $msgId, "💰 <b>فروش اعضا</b>\n\nبزودی فعال می‌شود");
    }
    elseif ($data == 'history') {
        $purchases = loadData('purchases');
        $text = "📋 <b>تاریخچه</b>\n\n";
        if (empty($purchases)) {
            $text .= "هنوز تراکنشی انجام نشده";
        } else {
            $text .= "تعداد خریدها: " . count($purchases);
        }
        editMessage($chatId, $msgId, $text, [[btn('بازگشت', 'menu', '◀️')]]);
    }
    elseif ($data == 'set_phone') {
        sendMessage($chatId, "📱 شماره تلفن خود را بفرستید:");
    }
    elseif ($data == 'set_payment') {
        $buttons = [
            btn('تتر USDT', 'payment_usdt', '💵'),
            btn('ترون TRX', 'payment_trx', '🪙'),
            btn('تومی', 'payment_tomy', '💳'),
            btn('بازگشت', 'settings', '◀️'),
        ];
        sendMessage($chatId, "💳 روش پرداخت را انتخاب کنید:", $buttons);
    }
    elseif (strpos($data, 'payment_') === 0) {
        $method = str_replace('payment_', '', $data);
        saveUser($userId, ['payment_method' => $method]);
        sendMessage($chatId, "✅ روش پرداخت تغییر یافت");
    }
    elseif ($data == 'admin') {
        if ($userId == ADMIN_ID) {
            list($msg_text, $buttons) = adminPanel();
            editMessage($chatId, $msgId, $msg_text, $buttons);
        }
    }
    elseif ($data == 'admin_users') {
        $users = loadData('users');
        $text = "👥 <b>کاربران</b>\n\n";
        $text .= "تعداد: " . count($users);
        editMessage($chatId, $msgId, $text, [[btn('بازگشت', 'admin', '◀️')]]);
    }
    elseif ($data == 'admin_purchases') {
        $purchases = loadData('purchases');
        $text = "🛍️ <b>خریدها</b>\n\n";
        $text .= "تعداد: " . count($purchases);
        editMessage($chatId, $msgId, $text, [[btn('بازگشت', 'admin', '◀️')]]);
    }
    elseif ($data == 'admin_sales') {
        $sales = loadData('sales');
        $text = "📊 <b>فروش‌ها</b>\n\n";
        $text .= "تعداد: " . count($sales);
        editMessage($chatId, $msgId, $text, [[btn('بازگشت', 'admin', '◀️')]]);
    }
}

http_response_code(200);
?>

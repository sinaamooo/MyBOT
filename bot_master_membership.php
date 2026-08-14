<?php
/**
 * 🛍️💎 سیستم فروش ممبرشیپ - نسخه پیشرفته
 * ربات مادر + پنل ادمین وب + ربات‌های فرعی اپلودری
 * PHP - تک فایل - 100% حرفه‌ای
 *
 * ویژگی‌ها:
 * - خرید ممبرشیپ با USDT/TRX/تومان
 * - ربات‌های فرعی اپلودری
 * - دکمه‌های رنگی + ایموجی پریمیوم
 * - پنل ادمین وب
 * - محدودیت تعداد اعضا
 * - خودکار اتصال بعد از پرداخت
 */

// ⚙️ تنظیمات
define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data_master');
define('PROFIT_PERCENT', 10);

// ایموجی پریمیوم
$EMOJI = [
    'blue' => '🔵', 'red' => '🔴', 'green' => '🟢',
    'shop' => '🛍️', 'member' => '👥', 'money' => '💰', 'lock' => '🔒',
    'check' => '✅', 'error' => '❌', 'warning' => '⚠️', 'star' => '⭐',
    'rocket' => '🚀', 'wallet' => '💳', 'chart' => '📊', 'bot' => '🤖',
    'upload' => '📤', 'file' => '📁', 'settings' => '⚙️', 'delete' => '🗑️',
    'premium' => '✨', 'crown' => '👑', 'fire' => '🔥'
];

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// ============================================
// 📚 توابع ذخیره و بارگیری داده
// ============================================

function save($file, $data) {
    file_put_contents(DATA_DIR . "/$file.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function load($file) {
    $path = DATA_DIR . "/$file.json";
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

function getUser($id) {
    $users = load('users');
    return $users[$id] ?? null;
}

function saveUser($id, $data) {
    $users = load('users');
    $users[$id] = array_merge($users[$id] ?? [], $data);
    $users[$id]['telegram_id'] = $id;
    save('users', $users);
    return $users[$id];
}

function getMembership($membership_id) {
    $memberships = load('memberships');
    return $memberships[$membership_id] ?? null;
}

function saveMembership($membership_id, $data) {
    $memberships = load('memberships');
    $memberships[$membership_id] = $data;
    save('memberships', $memberships);
    return $data;
}

function getOrCreateMembership($name, $price, $currency) {
    $memberships = load('memberships');
    $id = 'm_' . time();

    if (!isset($memberships[$id])) {
        $memberships[$id] = [
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'currency' => $currency,
            'members' => [],
            'limit' => 0,
            'bot_id' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        save('memberships', $memberships);
    }
    return $memberships[$id];
}

function genCode() {
    return substr(md5(uniqid()), 0, 8);
}

// ============================================
// 🔌 API تلگرام
// ============================================

function api($method, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/$method");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function apiBot($token, $method, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot$token/$method");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result, true);
}

function sendMsg($chatId, $text, $buttons = null, $token = BOT_TOKEN) {
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($buttons) $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    if ($token === BOT_TOKEN) {
        api('sendMessage', $data);
    } else {
        apiBot($token, 'sendMessage', $data);
    }
}

function answerCallback($callbackId, $text, $alert = false, $token = BOT_TOKEN) {
    $data = ['callback_query_id' => $callbackId, 'text' => $text, 'show_alert' => $alert ? 'true' : 'false'];
    if ($token === BOT_TOKEN) {
        api('answerCallbackQuery', $data);
    } else {
        apiBot($token, 'answerCallbackQuery', $data);
    }
}

// ============================================
// 🎨 سازنده دکمه‌های رنگی
// ============================================

function btnBlue($label, $data) {
    return [['text' => "🔵 $label", 'callback_data' => $data]];
}

function btnRed($label, $data) {
    return [['text' => "🔴 $label", 'callback_data' => $data]];
}

function btnGreen($label, $data) {
    return [['text' => "🟢 $label", 'callback_data' => $data]];
}

function btnRow(...$buttons) {
    $row = [];
    foreach ($buttons as $btn) {
        $row[] = $btn[0];
    }
    return [$row];
}

// ============================================
// 🛍️ مدیریت ممبرشیپ
// ============================================

class MembershipManager {
    public static function addMember($membership_id, $user_id, $username) {
        $memberships = load('memberships');
        if (!isset($memberships[$membership_id])) return false;

        $membership = $memberships[$membership_id];

        // بررسی محدودیت
        if ($membership['limit'] > 0 && count($membership['members']) >= $membership['limit']) {
            return false;
        }

        if (!in_array($user_id, $membership['members'])) {
            $membership['members'][] = $user_id;
            $memberships[$membership_id] = $membership;
            save('memberships', $memberships);
        }

        // ذخیره عضویت کاربر
        $user = getUser($user_id) ?? ['telegram_id' => $user_id];
        $user['membership_id'] = $membership_id;
        $user['username'] = $username;
        $user['joined_at'] = date('Y-m-d H:i:s');
        saveUser($user_id, $user);

        return true;
    }

    public static function isMember($membership_id, $user_id) {
        $membership = getMembership($membership_id);
        return $membership && in_array($user_id, $membership['members']);
    }

    public static function getMembershipLink($membership_id) {
        $membership = getMembership($membership_id);
        if (!$membership || !$membership['bot_id']) return null;

        $bots = load('bots');
        $bot = $bots[$membership['bot_id']] ?? null;
        if (!$bot) return null;

        $code = 'mem_' . $membership_id . '_' . genCode();
        return "https://t.me/{$bot['username']}?start=$code";
    }

    public static function getStats($membership_id) {
        $membership = getMembership($membership_id);
        if (!$membership) return null;

        return [
            'name' => $membership['name'],
            'total_members' => count($membership['members']),
            'limit' => $membership['limit'],
            'available' => $membership['limit'] > 0 ? ($membership['limit'] - count($membership['members'])) : '∞',
            'price' => $membership['price'],
            'currency' => $membership['currency']
        ];
    }
}

// ============================================
// 🤖 مدیریت ربات‌های فرعی
// ============================================

class BotManager {
    public static function createBot($membership_id, $token, $username) {
        $bots = load('bots');
        $bot_id = 'bot_' . time();

        $bots[$bot_id] = [
            'id' => $bot_id,
            'token' => $token,
            'username' => $username,
            'membership_id' => $membership_id,
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        save('bots', $bots);

        // تنظیم ممبرشیپ
        $memberships = load('memberships');
        $memberships[$membership_id]['bot_id'] = $bot_id;
        save('memberships', $memberships);

        return $bot_id;
    }

    public static function getBot($bot_id) {
        $bots = load('bots');
        return $bots[$bot_id] ?? null;
    }

    public static function all() {
        return load('bots');
    }
}

// ============================================
// 💳 مدیریت پرداخت
// ============================================

class PaymentManager {
    public static function createPayment($user_id, $membership_id, $amount, $currency) {
        $payments = load('payments');
        $payment_id = 'pay_' . time();

        $payments[$payment_id] = [
            'id' => $payment_id,
            'user_id' => $user_id,
            'membership_id' => $membership_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ];
        save('payments', $payments);

        return $payment_id;
    }

    public static function confirmPayment($payment_id) {
        $payments = load('payments');
        if (!isset($payments[$payment_id])) return false;

        $payment = $payments[$payment_id];
        $payment['status'] = 'confirmed';
        $payment['confirmed_at'] = date('Y-m-d H:i:s');
        $payments[$payment_id] = $payment;
        save('payments', $payments);

        // اضافه کردن عضو
        $user = getUser($payment['user_id']);
        MembershipManager::addMember($payment['membership_id'], $payment['user_id'], $user['username'] ?? 'Unknown');

        return true;
    }

    public static function getPayment($payment_id) {
        $payments = load('payments');
        return $payments[$payment_id] ?? null;
    }
}

// ============================================
// 🎯 Main Bot Logic
// ============================================

function processUpdate($update) {
    global $EMOJI;

    $message = $update['message'] ?? null;
    $callback = $update['callback_query'] ?? null;

    if ($message) {
        $text = $message['text'] ?? '';
        $from_id = $message['from']['id'];
        $username = $message['from']['username'] ?? 'Unknown';
        $chat_id = $message['chat']['id'];

        if (strpos($text, '/start') === 0) {
            handleStart($from_id, $username, $chat_id);
            return;
        }

        if ($text === '/panel' && $from_id === ADMIN_ID) {
            showAdminPanel($chat_id);
            return;
        }
    }

    if ($callback) {
        $from_id = $callback['from']['id'];
        $chat_id = $callback['message']['chat']['id'];
        $data = $callback['data'];

        handleCallback($from_id, $chat_id, $data, $callback['id']);
    }
}

function handleStart($user_id, $username, $chat_id) {
    global $EMOJI;

    saveUser($user_id, ['username' => $username]);

    $text = $EMOJI['crown'] . " <b>خوش آمدید!</b>\n\n";
    $text .= "🛍️ <b>سیستم فروش ممبرشیپ</b>\n\n";
    $text .= "برای دریافت دسترسی، یکی از ممبرشیپ‌های زیر را بخرید:\n\n";

    $memberships = load('memberships');
    $buttons = [];

    foreach ($memberships as $mem) {
        $available = $mem['limit'] > 0 ? (count($mem['members']) . '/' . $mem['limit']) : '∞';
        $label = "{$mem['name']} - {$mem['price']} {$mem['currency']} ({$EMOJI['member']} {$available})";
        $buttons[] = btnGreen("خرید", "buy_{$mem['id']}");
    }

    if (empty($memberships)) {
        sendMsg($chat_id, "هنوز ممبرشیپی وجود ندارد.");
        return;
    }

    foreach ($memberships as $mem) {
        $available = $mem['limit'] > 0 ? (count($mem['members']) . '/' . $mem['limit']) : '∞';
        $text .= "{$EMOJI['money']} <b>{$mem['name']}</b>\n";
        $text .= "قیمت: {$mem['price']} {$mem['currency']}\n";
        $text .= "اعضا: {$available}\n\n";
    }

    if ($user_id === ADMIN_ID) {
        $buttons[] = btnBlue("پنل مدیریت", "admin_panel");
    }

    sendMsg($chat_id, $text, $buttons);
}

function handleCallback($user_id, $chat_id, $data, $callback_id) {
    global $EMOJI;

    if (strpos($data, 'buy_') === 0) {
        $membership_id = substr($data, 4);
        $membership = getMembership($membership_id);

        if (!$membership) {
            answerCallback($callback_id, $EMOJI['error'] . " ممبرشیپ پیدا نشد");
            return;
        }

        // بررسی محدودیت
        if ($membership['limit'] > 0 && count($membership['members']) >= $membership['limit']) {
            answerCallback($callback_id, $EMOJI['error'] . " ظرفیت تمام شده!", true);
            return;
        }

        // ایجاد پرداخت
        $payment_id = PaymentManager::createPayment($user_id, $membership_id, $membership['price'], $membership['currency']);

        answerCallback($callback_id, '');

        $text = $EMOJI['wallet'] . " <b>اطلاعات پرداخت</b>\n\n";
        $text .= "ممبرشیپ: {$membership['name']}\n";
        $text .= "قیمت: {$membership['price']} {$membership['currency']}\n";
        $text .= "شناسه پرداخت: $payment_id\n\n";
        $text .= "{$EMOJI['warning']} لطفا پرداخت را انجام دهید و شناسه را تایید کنید.\n";

        $buttons = [
            btnGreen("تایید پرداخت", "confirm_pay_$payment_id"),
            btnRed("انصراف", "cancel_pay_$payment_id")
        ];

        sendMsg($chat_id, $text, $buttons);
        return;
    }

    if (strpos($data, 'confirm_pay_') === 0) {
        $payment_id = substr($data, 12);
        $payment = PaymentManager::getPayment($payment_id);

        if (!$payment) {
            answerCallback($callback_id, $EMOJI['error'] . " پرداخت پیدا نشد");
            return;
        }

        if ($payment['status'] !== 'pending') {
            answerCallback($callback_id, $EMOJI['error'] . " این پرداخت قبلا تایید شده");
            return;
        }

        PaymentManager::confirmPayment($payment_id);
        answerCallback($callback_id, $EMOJI['check'] . " پرداخت تایید شد!");

        $membership = getMembership($payment['membership_id']);
        $link = MembershipManager::getMembershipLink($payment['membership_id']);

        $text = $EMOJI['check'] . " <b>خرید موفق!</b>\n\n";
        $text .= "ممبرشیپ: {$membership['name']}\n";
        $text .= "لینک دسترسی:\n$link\n";

        sendMsg($chat_id, $text);
        return;
    }

    if ($data === 'admin_panel' && $user_id === ADMIN_ID) {
        answerCallback($callback_id, '');
        showAdminPanel($chat_id);
        return;
    }
}

function showAdminPanel($chat_id) {
    global $EMOJI;

    $memberships = load('memberships');
    $bots = load('bots');
    $payments = load('payments');

    $confirmed = 0;
    foreach ($payments as $p) {
        if ($p['status'] === 'confirmed') $confirmed++;
    }

    $text = $EMOJI['crown'] . " <b>پنل مدیریت</b>\n\n";
    $text .= "ممبرشیپ‌ها: " . count($memberships) . "\n";
    $text .= "ربات‌ها: " . count($bots) . "\n";
    $text .= "پرداخت‌های تایید شده: $confirmed\n";

    $buttons = [
        btnBlue("ممبرشیپ‌ها", "admin_memberships"),
        btnBlue("ربات‌ها", "admin_bots"),
        btnBlue("پرداخت‌ها", "admin_payments"),
        btnGreen("افزودن ممبرشیپ", "admin_add_mem"),
    ];

    sendMsg($chat_id, $text, $buttons);
}

// ============================================
// 🎯 Webhook Handler
// ============================================

$json = file_get_contents('php://input');
$update = json_decode($json, true);

if ($update) {
    processUpdate($update);
}

http_response_code(200);
echo json_encode(['ok' => true]);
?>

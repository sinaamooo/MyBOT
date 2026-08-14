<?php
/**
 * 🛍️ سیستم خرید و فروش اعضای تلگرام - نسخه حرفه‌ای
 * ربات اصلی + مدیریت ربات‌های فرعی + پنل کامل
 * PHP - فایل واحد شامل - 100% عملکردی
 */

// ⚙️ تنظیمات
define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');
define('PROFIT_PERCENT', 10);

// ایموجی‌های premium
$EMOJI = [
    'shop' => '🛍️', 'member' => '👥', 'money' => '💰', 'lock' => '🔒', 'unlock' => '🔓',
    'check' => '✅', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️', 'star' => '⭐',
    'fire' => '🔥', 'rocket' => '🚀', 'wallet' => '💳', 'history' => '📋', 'settings' => '⚙️',
    'admin' => '🔐', 'users' => '👤', 'chart' => '📊', 'channel' => '📢', 'bot' => '🤖'
];

// رنگ‌های دکمه‌های شیشه‌ای
$COLORS = ['blue' => '#0088cc', 'red' => '#ff0000', 'green' => '#00cc00'];

// ایجاد پوشه اگر نبود
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

function sendMsg($chatId, $text, $buttons = null) {
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($buttons) $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    api('sendMessage', $data);
}

function editMsg($chatId, $msgId, $text, $buttons = null) {
    $data = ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($buttons) $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    api('editMessageText', $data);
}

// ============================================
// 🎨 سازنده رابط (Builders)
// ============================================

function btn($text, $data, $emoji = '', $color = 'blue') {
    $text = ($emoji ? "$emoji " : "") . $text;
    return [['text' => $text, 'callback_data' => $data]];
}

function btnRow(...$buttons) {
    return [$buttons];
}

// ============================================
// 🤖 مدیریت ربات‌های فرعی
// ============================================

function apiSubBot($token, $method, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot$token/$method");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $result = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    return json_decode($result, true) ?: ['ok' => false, 'error' => $err];
}

function restrictUser($token, $chatId, $userId) {
    return apiSubBot($token, 'restrictChatMember', [
        'chat_id' => $chatId,
        'user_id' => $userId,
        'permissions' => json_encode([
            'can_send_messages' => false,
            'can_send_media_messages' => false,
            'can_send_other_messages' => false,
            'can_add_web_page_previews' => false
        ])
    ]);
}

function unrestrictUser($token, $chatId, $userId) {
    return apiSubBot($token, 'restrictChatMember', [
        'chat_id' => $chatId,
        'user_id' => $userId,
        'permissions' => json_encode([
            'can_send_messages' => true,
            'can_send_media_messages' => true,
            'can_send_polls' => true,
            'can_add_web_page_previews' => true,
            'can_change_info' => false,
            'can_invite_users' => true,
            'can_pin_messages' => false
        ])
    ]);
}

function getSubBotInfo($token) {
    return apiSubBot($token, 'getMe', []);
}

function getSubBots() {
    return load('subbots');
}

function getSubBot($id) {
    $subbots = getSubBots();
    return $subbots[$id] ?? null;
}

function saveSubBot($id, $data) {
    $subbots = getSubBots();
    $subbots[$id] = array_merge($subbots[$id] ?? [], $data);
    save('subbots', $subbots);
}

function deleteSubBot($id) {
    $subbots = getSubBots();
    unset($subbots[$id]);
    save('subbots', $subbots);
}

// کانال‌های اجباری
function getChannels() {
    return load('channels');
}

function getChannel($id) {
    $channels = getChannels();
    return $channels[$id] ?? null;
}

function saveChannel($id, $data) {
    $channels = getChannels();
    $channels[$id] = array_merge($channels[$id] ?? [], $data);
    save('channels', $channels);
}

function deleteChannel($id) {
    $channels = getChannels();
    unset($channels[$id]);
    save('channels', $channels);
}

function getChannelsByBot($botId) {
    $channels = getChannels();
    return array_filter($channels, fn($c) => $c['bot_id'] == $botId);
}

// منوی اصلی
function mainMenu($userId, $EMOJI) {
    $user = getUser($userId);
    $balance = $user['balance'] ?? 0;

    $text = "<b>{$EMOJI['rocket']} سیستم خرید و فروش اعضا</b>\n\n";
    $text .= "<i>خوش آمدید!</i>\n\n";
    $text .= "<b>{$EMOJI['wallet']} موجودی:</b> <code>" . number_format($balance) . "</code>\n";
    $text .= "<b>{$EMOJI['member']} عضو:</b> <code>" . ($user['is_seller'] ? 'فروشنده' : 'خریدار') . "</code>\n\n";
    $text .= "لطفاً یک گزینه انتخاب کنید:";

    $buttons = [
        btn('خرید اعضا', 'buy', $EMOJI['shop']),
        btn('فروش اعضا', 'sell', $EMOJI['money']),
        btn('تاریخچه', 'history', $EMOJI['history']),
        btn('تنظیمات', 'settings', $EMOJI['settings']),
    ];

    if ($userId == ADMIN_ID) {
        $buttons[] = btn('🔐 پنل مدیریت', 'admin_panel', $EMOJI['admin']);
    }

    return [$text, $buttons];
}

// منوی تنظیمات
function settingsMenu($userId, $EMOJI) {
    $user = getUser($userId);
    $phone = $user['phone'] ?? '❌ تنظیم نشده';
    $payment = $user['payment_method'] ?? 'انتخاب نشده';
    $walletAddr = $user['wallet_address'] ?? 'ثبت نشده';

    $text = "<b>{$EMOJI['settings']} تنظیمات حساب</b>\n\n";
    $text .= "<b>📱 شماره:</b> $phone\n";
    $text .= "<b>💳 روش پرداخت:</b> $payment\n";
    $text .= "<b>🏦 آدرس کیف پول:</b> $walletAddr\n";

    $buttons = [
        btn('📱 شماره', 'set_phone', ''),
        btn('💳 روش پرداخت', 'payment_methods', ''),
        btn('🏦 آدرس کیف پول', 'set_wallet', ''),
        btn('👤 حالت فروشنده', 'toggle_seller', ''),
        btn('◀️ بازگشت', 'menu', ''),
    ];

    return [$text, $buttons];
}

// منوی روش‌های پرداخت
function paymentMenu($EMOJI) {
    $text = "<b>{$EMOJI['money']} روش پرداخت</b>\n\n";
    $text .= "یکی از روش‌های زیر را انتخاب کنید:\n";

    $buttons = [
        btn('تتر (USDT)', 'pay_usdt', '💵'),
        btn('ترون (TRX)', 'pay_trx', '🪙'),
        btn('تومی', 'pay_tomy', '💳'),
        btn('◀️ بازگشت', 'settings', ''),
    ];

    return [$text, $buttons];
}

// منوی خرید
function buyMenu($EMOJI) {
    $text = "<b>{$EMOJI['shop']} خرید اعضا</b>\n\n";
    $text .= "تعداد اعضایی که می‌خواهید خریداری کنید را وارد کنید:\n";
    $text .= "<i>مثال: 100</i>";

    return $text;
}

// منوی فروش
function sellMenu($EMOJI) {
    $text = "<b>{$EMOJI['money']} فروش اعضا</b>\n\n";
    $text .= "تعداد اعضایی برای فروش + هزینه هر عضو را وارد کنید:\n";
    $text .= "<i>مثال: 100 50</i>\n";
    $text .= "<small>(قیمت نهایی = تعداد × هزینه + سود {PROFIT_PERCENT}%)</small>";

    return $text;
}

// پنل مدیریت
function adminPanel($EMOJI) {
    $text = "<b>{$EMOJI['admin']} پنل مدیریت</b>\n\n";
    $text .= "انتخاب کنید:\n";

    $buttons = [
        btn('👥 کاربران', 'admin_users', $EMOJI['users']),
        btn('🤖 ربات‌های فرعی', 'admin_subbots', $EMOJI['bot']),
        btn('📢 مدیریت کانال‌ها', 'admin_channels', $EMOJI['channel']),
        btn('🛍️ خریدها', 'admin_purchases', $EMOJI['shop']),
        btn('💰 فروش‌ها', 'admin_sales', $EMOJI['money']),
        btn('📊 گزارش', 'admin_report', $EMOJI['chart']),
        btn('◀️ بازگشت', 'menu', ''),
    ];

    return [$text, $buttons];
}

// منوی مدیریت ربات‌های فرعی
function subBotsMenu($EMOJI) {
    $subbots = getSubBots();
    $text = "<b>{$EMOJI['bot']} ربات‌های فرعی</b>\n\n";
    $text .= "تعداد: " . count($subbots) . "\n\n";

    foreach ($subbots as $bot) {
        $status = $bot['status'] == 'active' ? '✅' : '❌';
        $text .= "$status <code>{$bot['name']}</code> (@{$bot['username']})\n";
    }

    $buttons = [
        btn('➕ افزودن ربات جدید', 'add_subbot', ''),
        btn('◀️ بازگشت', 'admin_panel', ''),
    ];

    return [$text, $buttons];
}

// منوی مدیریت کانال‌ها
function channelsMenu($EMOJI) {
    $channels = getChannels();
    $text = "<b>{$EMOJI['channel']} کانال‌های اجباری</b>\n\n";
    $text .= "تعداد: " . count($channels) . "\n\n";

    foreach ($channels as $ch) {
        $status = $ch['is_active'] ? '✅' : '❌';
        $text .= "$status <i>{$ch['channel_name']}</i>\n";
        $text .= "   🤖 {$ch['bot_id']}\n";
    }

    $buttons = [
        btn('➕ افزودن کانال', 'add_channel', ''),
        btn('◀️ بازگشت', 'admin_panel', ''),
    ];

    return [$text, $buttons];
}

// ============================================
// 🔄 پردازش webhook
// ============================================

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(200);
    exit;
}

// 📨 پیام متنی
if (isset($input['message'])) {
    $msg = $input['message'];
    $userId = $msg['from']['id'];
    $chatId = $msg['chat']['id'];
    $text = trim($msg['text'] ?? '');

    // ایجاد کاربر
    if (!getUser($userId)) {
        saveUser($userId, [
            'first_name' => $msg['from']['first_name'],
            'balance' => 0,
            'is_seller' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    // دستورات
    if ($text == '/start') {
        list($txt, $btns) = mainMenu($userId, $EMOJI);
        sendMsg($chatId, $txt, $btns);
    }
    elseif ($text == '/balance') {
        $user = getUser($userId);
        $bal = $user['balance'] ?? 0;
        sendMsg($chatId, "<b>{$EMOJI['wallet']} موجودی</b>\n\n💰 " . number_format($bal));
    }
    elseif ($text == '/help') {
        sendMsg($chatId,
            "<b>دستورات:</b>\n" .
            "/start - منوی اصلی\n" .
            "/balance - موجودی\n" .
            "/help - راهنما"
        );
    }
    // دریافت شماره تلفن
    elseif (preg_match('/^\d{10,}$/', $text)) {
        saveUser($userId, ['phone' => $text]);
        sendMsg($chatId, "{$EMOJI['check']} شماره شما ذخیره شد!");
    }
    // دریافت آدرس کیف پول
    elseif (preg_match('/^[a-zA-Z0-9]{26,}$/', $text)) {
        saveUser($userId, ['wallet_address' => $text]);
        sendMsg($chatId, "{$EMOJI['check']} کیف پول ذخیره شد!");
    }
    // خرید اعضا
    elseif (preg_match('/^\d+$/', $text) && strpos($text, ' ') === false) {
        $count = intval($text);
        if ($count > 0 && $count <= 10000) {
            $purchases = load('purchases');
            $id = time() . '_' . $userId;
            $purchases[$id] = [
                'id' => $id,
                'buyer_id' => $userId,
                'count' => $count,
                'status' => 'pending',
                'payment_method' => getUser($userId)['payment_method'] ?? 'نامشخص',
                'created_at' => date('Y-m-d H:i:s')
            ];
            save('purchases', $purchases);
            sendMsg($chatId, "{$EMOJI['check']} سفارش شما ثبت شد!\n\n📦 تعداد: $count عضو\n💰 منتظر تأیید مدیر...");
        }
    }
    // فروش اعضا
    elseif (preg_match('/^(\d+)\s+(\d+)$/', $text, $m)) {
        $count = intval($m[1]);
        $price = intval($m[2]);
        if ($count > 0 && $price > 0) {
            $profit = ($count * $price * PROFIT_PERCENT) / 100;
            $total = ($count * $price) + $profit;
            saveUser($userId, ['is_seller' => true]);

            $sales = load('sales');
            $id = time() . '_' . $userId;
            $sales[$id] = [
                'id' => $id,
                'seller_id' => $userId,
                'count' => $count,
                'price_per' => $price,
                'profit' => $profit,
                'total' => $total,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            save('sales', $sales);

            $txt = "{$EMOJI['check']} فروش شما ثبت شد!\n\n";
            $txt .= "📦 تعداد: $count\n";
            $txt .= "💵 قیمت: " . number_format($price) . "\n";
            $txt .= "📈 سود: " . number_format($profit) . "\n";
            $txt .= "<b>💰 کل: " . number_format($total) . "</b>";
            sendMsg($chatId, $txt);
        }
    }
    // اقدامات خاص ادمین
    elseif ($userId == ADMIN_ID) {
        $action = getUser($userId)['action'] ?? null;

        // افزودن ربات فرعی
        if ($action == 'adding_subbot') {
            $token = trim($text);
            if (preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
                $info = getSubBotInfo($token);
                if ($info['ok'] ?? false) {
                    $botId = 'bot_' . time();
                    saveSubBot($botId, [
                        'id' => $botId,
                        'name' => $info['result']['first_name'] ?? 'ربات',
                        'username' => $info['result']['username'] ?? 'unknown',
                        'token' => $token,
                        'owner_id' => $userId,
                        'status' => 'active',
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    saveUser($userId, ['action' => null]);
                    sendMsg($chatId, "{$EMOJI['check']} ربات {$info['result']['first_name']} اضافه شد!\n\n🆔 شناسه: <code>$botId</code>");
                } else {
                    sendMsg($chatId, "{$EMOJI['error']} توکن نامعتبر!");
                }
            } else {
                sendMsg($chatId, "{$EMOJI['warning']} فرمت توکن نادرست!");
            }
        }

        // افزودن کانال - مرحله 1: شناسه ربات
        elseif ($action == 'adding_channel_1') {
            $botId = trim($text);
            if (getSubBot($botId)) {
                saveUser($userId, ['action' => 'adding_channel_2', 'selected_bot' => $botId]);
                sendMsg($chatId, "{$EMOJI['channel']} شناسه کانال را بفرستید:\n\n<i>مثال:</i>\n<code>-1001234567890</code>");
            } else {
                sendMsg($chatId, "{$EMOJI['error']} ربات فرعی یافت نشد!");
            }
        }

        // افزودن کانال - مرحله 2: شناسه کانال
        elseif ($action == 'adding_channel_2') {
            $channelId = trim($text);
            if (preg_match('/^-?\d+$/', $channelId)) {
                $userData = getUser($userId);
                $botId = $userData['selected_bot'] ?? null;
                $chId = 'ch_' . time();

                saveChannel($chId, [
                    'id' => $chId,
                    'bot_id' => $botId,
                    'channel_id' => intval($channelId),
                    'channel_name' => 'کانال ' . $channelId,
                    'is_mandatory' => true,
                    'is_active' => true,
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                saveUser($userId, ['action' => null, 'selected_bot' => null]);
                sendMsg($chatId, "{$EMOJI['check']} کانال اضافه شد!");
            } else {
                sendMsg($chatId, "{$EMOJI['error']} شناسه کانال نامعتبر!");
            }
        }
    }
}

// 🔘 Callback Query
if (isset($input['callback_query'])) {
    $query = $input['callback_query'];
    $userId = $query['from']['id'];
    $chatId = $query['from']['id'];
    $data = $query['data'];
    $msgId = $query['message']['message_id'];

    $user = getUser($userId);

    // مسیریابی
    if ($data == 'menu') {
        list($txt, $btns) = mainMenu($userId, $EMOJI);
        editMsg($chatId, $msgId, $txt, $btns);
    }
    elseif ($data == 'settings') {
        list($txt, $btns) = settingsMenu($userId, $EMOJI);
        editMsg($chatId, $msgId, $txt, $btns);
    }
    elseif ($data == 'payment_methods') {
        list($txt, $btns) = paymentMenu($EMOJI);
        editMsg($chatId, $msgId, $txt, $btns);
    }
    elseif (strpos($data, 'pay_') === 0) {
        $method = str_replace('pay_', '', $data);
        $methods = ['usdt' => 'تتر (USDT)', 'trx' => 'ترون (TRX)', 'tomy' => 'تومی'];
        saveUser($userId, ['payment_method' => $methods[$method] ?? '']);
        editMsg($chatId, $msgId, "{$EMOJI['check']} روش پرداخت تغییر یافت!");
    }
    elseif ($data == 'set_phone') {
        sendMsg($chatId, "📱 شماره تلفن خود را بفرستید:");
    }
    elseif ($data == 'set_wallet') {
        sendMsg($chatId, "🏦 آدرس کیف پول خود را بفرستید:");
    }
    elseif ($data == 'toggle_seller') {
        $isSeller = !($user['is_seller'] ?? false);
        saveUser($userId, ['is_seller' => $isSeller]);
        $status = $isSeller ? '✅ فروشنده' : '❌ خریدار';
        editMsg($chatId, $msgId, "{$EMOJI['check']} حالت شما: $status");
    }
    elseif ($data == 'buy') {
        sendMsg($chatId, buyMenu($EMOJI));
    }
    elseif ($data == 'sell') {
        sendMsg($chatId, sellMenu($EMOJI));
    }
    elseif ($data == 'history') {
        $purchases = load('purchases');
        $txt = "<b>{$EMOJI['history']} تاریخچه</b>\n\n";
        $count = 0;
        foreach ($purchases as $p) {
            if ($p['buyer_id'] == $userId) {
                $txt .= "🆔 #" . substr($p['id'], -6) . " - {$p['count']} عضو\n";
                $count++;
            }
        }
        if ($count == 0) $txt .= "هنوز سفارشی نداشته‌اید";
        editMsg($chatId, $msgId, $txt, [[btn('◀️ بازگشت', 'menu', '')]]);
    }
    elseif ($data == 'admin_panel') {
        if ($userId == ADMIN_ID) {
            list($txt, $btns) = adminPanel($EMOJI);
            editMsg($chatId, $msgId, $txt, $btns);
        }
    }
    elseif ($data == 'admin_users') {
        if ($userId == ADMIN_ID) {
            $users = load('users');
            $txt = "<b>{$EMOJI['users']} کاربران</b>\n\n";
            $txt .= "تعداد: " . count($users) . "\n\n";
            foreach ($users as $u) {
                $txt .= "👤 " . htmlspecialchars($u['first_name'] ?? 'نامشناس') . " - 💰 " . number_format($u['balance'] ?? 0) . "\n";
            }
            editMsg($chatId, $msgId, $txt, [[btn('◀️ بازگشت', 'admin_panel', '')]]);
        }
    }
    elseif ($data == 'admin_purchases') {
        if ($userId == ADMIN_ID) {
            $purchases = load('purchases');
            $txt = "<b>{$EMOJI['shop']} خریدها</b>\n\n";
            $txt .= "تعداد: " . count($purchases) . "\n";
            $txt .= "در انتظار: " . count(array_filter($purchases, fn($p) => $p['status'] == 'pending')) . "\n";
            editMsg($chatId, $msgId, $txt, [[btn('◀️ بازگشت', 'admin_panel', '')]]);
        }
    }
    elseif ($data == 'admin_sales') {
        if ($userId == ADMIN_ID) {
            $sales = load('sales');
            $txt = "<b>{$EMOJI['money']} فروش‌ها</b>\n\n";
            $txt .= "تعداد: " . count($sales) . "\n";
            $total = 0;
            foreach ($sales as $s) $total += $s['total'] ?? 0;
            $txt .= "💰 کل: " . number_format($total) . "\n";
            editMsg($chatId, $msgId, $txt, [[btn('◀️ بازگشت', 'admin_panel', '')]]);
        }
    }
    elseif ($data == 'admin_report') {
        if ($userId == ADMIN_ID) {
            $users = load('users');
            $purchases = load('purchases');
            $sales = load('sales');

            $txt = "<b>{$EMOJI['chart']} گزارش</b>\n\n";
            $txt .= "👥 کاربران: " . count($users) . "\n";
            $txt .= "{$EMOJI['shop']} خریدها: " . count($purchases) . "\n";
            $txt .= "{$EMOJI['money']} فروش‌ها: " . count($sales) . "\n";
            $txt .= "📈 درصد سود: " . PROFIT_PERCENT . "%\n";
            editMsg($chatId, $msgId, $txt, [[btn('◀️ بازگشت', 'admin_panel', '')]]);
        }
    }
    elseif ($data == 'admin_subbots') {
        if ($userId == ADMIN_ID) {
            list($txt, $btns) = subBotsMenu($EMOJI);
            editMsg($chatId, $msgId, $txt, $btns);
        }
    }
    elseif ($data == 'admin_channels') {
        if ($userId == ADMIN_ID) {
            list($txt, $btns) = channelsMenu($EMOJI);
            editMsg($chatId, $msgId, $txt, $btns);
        }
    }
    elseif ($data == 'add_subbot') {
        if ($userId == ADMIN_ID) {
            sendMsg($chatId, "{$EMOJI['bot']} توکن ربات فرعی را بفرستید:\n\n<i>مثال:</i>\n<code>8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g</code>");
            saveUser($userId, ['action' => 'adding_subbot']);
        }
    }
    elseif ($data == 'add_channel') {
        if ($userId == ADMIN_ID) {
            sendMsg($chatId, "{$EMOJI['channel']} شناسه ربات فرعی را بفرستید:\n\n<i>مثال:</i>\n<code>bot1</code>");
            saveUser($userId, ['action' => 'adding_channel_1']);
        }
    }
}

http_response_code(200);
?>

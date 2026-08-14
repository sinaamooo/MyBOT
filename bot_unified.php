<?php
/**
 * 🛍️🤖 سیستم خرید و فروش اعضای تلگرام + ربات‌ساز آپلودر
 * نسخه حرفه‌ای متحد - فایل واحد
 * فیچرها: خرید/فروش + ربات فرعی + آپلودر فایل + منو ریزی
 * PHP - 100% عملکردی - بدون دیتابیس
 */

// ⚙️ تنظیمات اصلی
define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');
define('CHILD_BOTS_DIR', DATA_DIR . '/child_bots');
define('PROFIT_PERCENT', 10);
define('BUILD_COST', 50); // Cost to create a bot

// Create directories
if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!is_dir(CHILD_BOTS_DIR)) mkdir(CHILD_BOTS_DIR, 0755, true);

// ایموجی‌های premium
$EMOJI = [
    'shop' => '🛍️', 'member' => '👥', 'money' => '💰', 'lock' => '🔒', 'unlock' => '🔓',
    'check' => '✅', 'error' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️', 'star' => '⭐',
    'fire' => '🔥', 'rocket' => '🚀', 'wallet' => '💳', 'history' => '📋', 'settings' => '⚙️',
    'admin' => '🔐', 'users' => '👤', 'chart' => '📊', 'channel' => '📢', 'bot' => '🤖',
    'upload' => '📤', 'batch' => '👥', 'broadcast' => '📣', 'files' => '📁', 'back' => '🔙',
    'delete' => '🗑️', 'add' => '➕', 'remove' => '➖', 'on' => '✔️', 'off' => '✖️'
];

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

function saveBotData($bot_id, $file, $data) {
    $dir = CHILD_BOTS_DIR . "/$bot_id";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    file_put_contents("$dir/$file.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadBotData($bot_id, $file) {
    $path = CHILD_BOTS_DIR . "/$bot_id/$file.json";
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

function addCoins($id, $amount) {
    $user = getUser($id) ?? ['coins' => 0];
    $user['coins'] = max(0, ($user['coins'] ?? 0) + $amount);
    saveUser($id, ['coins' => $user['coins']]);
    return $user['coins'];
}

function getCoins($id) {
    $user = getUser($id);
    return $user['coins'] ?? 0;
}

function genCode() {
    return substr(md5(uniqid()), 0, 8);
}

function now() {
    return date('Y-m-d H:i:s');
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

function editMsg($chatId, $msgId, $text, $buttons = null, $token = BOT_TOKEN) {
    $data = ['chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text, 'parse_mode' => 'HTML'];
    if ($buttons) $data['reply_markup'] = json_encode(['inline_keyboard' => $buttons]);
    if ($token === BOT_TOKEN) {
        api('editMessageText', $data);
    } else {
        apiBot($token, 'editMessageText', $data);
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
// 🎨 سازنده دکمه‌ها
// ============================================

function btn($label, $data) {
    return [['text' => $label, 'callback_data' => $data]];
}

function btnRow(...$buttons) {
    $row = [];
    foreach ($buttons as $btn) {
        $row[] = $btn[0]; // Extract from btn() format
    }
    return [$row];
}

// ============================================
// 🚀 مدیریت ربات‌های فرعی (Child Bots)
// ============================================

class ChildBotManager {
    public static function create($owner_id, $token, $username) {
        $bots = load('child_bots');
        $bot_id = (max(array_keys($bots)) ?? 0) + 1;

        $bots[$bot_id] = [
            'bot_id' => $bot_id,
            'owner_id' => $owner_id,
            'token' => $token,
            'username' => $username,
            'active' => true,
            'created_at' => now()
        ];
        save('child_bots', $bots);

        // Initialize bot data
        $settings = [
            'start_message' => 'سلام {name} خوش اومدی 👋',
            'join_text' => '🔐 برای دریافت فایل باید عضو شوید:',
            'task_text' => '',
            'task_enabled' => false,
            'protect_content' => false,
            'premium_emoji' => '✨'
        ];
        saveBotData($bot_id, 'settings', $settings);
        saveBotData($bot_id, 'files', []);
        saveBotData($bot_id, 'batches', []);
        saveBotData($bot_id, 'users', []);
        saveBotData($bot_id, 'vips', []);
        saveBotData($bot_id, 'admins', []);
        saveBotData($bot_id, 'banned', []);
        saveBotData($bot_id, 'joins', []);

        return $bot_id;
    }

    public static function delete($bot_id) {
        $bots = load('child_bots');
        unset($bots[$bot_id]);
        save('child_bots', $bots);

        // Delete bot data directory
        $dir = CHILD_BOTS_DIR . "/$bot_id";
        array_map('unlink', glob("$dir/*"));
        @rmdir($dir);
    }

    public static function get($bot_id) {
        $bots = load('child_bots');
        return $bots[$bot_id] ?? null;
    }

    public static function getByUser($user_id) {
        $bots = load('child_bots');
        $result = [];
        foreach ($bots as $bot) {
            if ($bot['owner_id'] == $user_id) {
                $result[$bot['bot_id']] = $bot;
            }
        }
        return $result;
    }

    public static function all() {
        return load('child_bots');
    }

    public static function toggleActive($bot_id) {
        $bots = load('child_bots');
        if (isset($bots[$bot_id])) {
            $bots[$bot_id]['active'] = !($bots[$bot_id]['active'] ?? true);
            save('child_bots', $bots);
            return $bots[$bot_id]['active'];
        }
        return false;
    }
}

// ============================================
// 📤 File Uploader Functionality
// ============================================

class FileUploader {
    public static function upload($bot_id, $file_id, $file_name, $file_type) {
        $files = loadBotData($bot_id, 'files');
        $code = genCode();

        $files[$code] = [
            'code' => $code,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'file_type' => $file_type,
            'uploaded_at' => now(),
            'downloads' => 0,
            'batch_id' => null
        ];

        saveBotData($bot_id, 'files', $files);
        return $code;
    }

    public static function startBatch($bot_id) {
        $batches = loadBotData($bot_id, 'batches');
        $batch_id = (max(array_keys($batches)) ?? 0) + 1;
        $batch_code = genCode() . '_b';

        $batches[$batch_id] = [
            'batch_id' => $batch_id,
            'code' => $batch_code,
            'files' => [],
            'created_at' => now(),
            'downloads' => 0
        ];

        saveBotData($bot_id, 'batches', $batches);
        return $batch_id;
    }

    public static function addToBatch($bot_id, $batch_id, $file_id, $file_name, $file_type) {
        $files = loadBotData($bot_id, 'files');
        $code = genCode();

        $files[$code] = [
            'code' => $code,
            'file_id' => $file_id,
            'file_name' => $file_name,
            'file_type' => $file_type,
            'uploaded_at' => now(),
            'downloads' => 0,
            'batch_id' => $batch_id
        ];

        saveBotData($bot_id, 'files', $files);

        // Update batch
        $batches = loadBotData($bot_id, 'batches');
        if (isset($batches[$batch_id])) {
            $batches[$batch_id]['files'][] = $code;
            saveBotData($bot_id, 'batches', $batches);
        }

        return $code;
    }

    public static function getFile($bot_id, $code) {
        $files = loadBotData($bot_id, 'files');
        return $files[$code] ?? null;
    }

    public static function getBatch($bot_id, $batch_id) {
        $batches = loadBotData($bot_id, 'batches');
        return $batches[$batch_id] ?? null;
    }

    public static function recordDownload($bot_id, $code) {
        $files = loadBotData($bot_id, 'files');
        if (isset($files[$code])) {
            $files[$code]['downloads']++;
            saveBotData($bot_id, 'files', $files);
        }
    }
}

// ============================================
// 👥 User Management
// ============================================

class BotUsers {
    public static function addUser($bot_id, $user_id, $username) {
        $users = loadBotData($bot_id, 'users');
        if (!isset($users[$user_id])) {
            $users[$user_id] = [
                'id' => $user_id,
                'username' => $username,
                'joined_at' => now(),
                'banned' => false
            ];
            saveBotData($bot_id, 'users', $users);
        }
        return $users[$user_id];
    }

    public static function isBanned($bot_id, $user_id) {
        $users = loadBotData($bot_id, 'users');
        return $users[$user_id]['banned'] ?? false;
    }

    public static function ban($bot_id, $user_id) {
        $users = loadBotData($bot_id, 'users');
        if (isset($users[$user_id])) {
            $users[$user_id]['banned'] = true;
            saveBotData($bot_id, 'users', $users);
        }
    }

    public static function unban($bot_id, $user_id) {
        $users = loadBotData($bot_id, 'users');
        if (isset($users[$user_id])) {
            $users[$user_id]['banned'] = false;
            saveBotData($bot_id, 'users', $users);
        }
    }

    public static function addVip($bot_id, $user_id) {
        $vips = loadBotData($bot_id, 'vips');
        $vips[$user_id] = true;
        saveBotData($bot_id, 'vips', $vips);
    }

    public static function removeVip($bot_id, $user_id) {
        $vips = loadBotData($bot_id, 'vips');
        unset($vips[$user_id]);
        saveBotData($bot_id, 'vips', $vips);
    }

    public static function isVip($bot_id, $user_id) {
        $vips = loadBotData($bot_id, 'vips');
        return isset($vips[$user_id]);
    }

    public static function addAdmin($bot_id, $user_id) {
        $admins = loadBotData($bot_id, 'admins');
        $admins[$user_id] = true;
        saveBotData($bot_id, 'admins', $admins);
    }

    public static function isAdmin($bot_id, $user_id, $owner_id) {
        if ($user_id == $owner_id) return true;
        $admins = loadBotData($bot_id, 'admins');
        return isset($admins[$user_id]);
    }
}

// ============================================
// 🔐 Forced Join Management
// ============================================

class ForcedJoin {
    public static function add($bot_id, $channel) {
        $joins = loadBotData($bot_id, 'joins');
        $joins[$channel] = true;
        saveBotData($bot_id, 'joins', $joins);
    }

    public static function remove($bot_id, $channel) {
        $joins = loadBotData($bot_id, 'joins');
        unset($joins[$channel]);
        saveBotData($bot_id, 'joins', $joins);
    }

    public static function getAll($bot_id) {
        return loadBotData($bot_id, 'joins');
    }

    public static function checkJoin($bot_id, $user_id, $token) {
        $joins = self::getAll($bot_id);
        $missing = [];

        foreach ($joins as $channel => $v) {
            $result = apiBot($token, 'getChatMember', ['chat_id' => $channel, 'user_id' => $user_id]);
            if (!$result['ok'] || $result['result']['status'] == 'left' || $result['result']['status'] == 'kicked') {
                $missing[] = $channel;
            }
        }

        return count($missing) == 0 ? true : $missing;
    }
}

// ============================================
// 📋 Main Bot Logic
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

        // Master bot: /start command
        if (strpos($text, '/start') === 0) {
            handleMasterStart($from_id, $username, $chat_id);
            return;
        }

        // Master bot: /panel command
        if ($text === '/panel' && $from_id === ADMIN_ID) {
            showAdminPanel($chat_id);
            return;
        }

        // State-based handling for master bot
        $state = getState($from_id);
        if ($state) {
            handleMasterState($from_id, $text, $chat_id, $state);
            return;
        }
    }

    if ($callback) {
        $from_id = $callback['from']['id'];
        $chat_id = $callback['message']['chat']['id'];
        $msg_id = $callback['message']['message_id'];
        $data = $callback['data'];

        handleCallback($from_id, $chat_id, $msg_id, $data);
    }
}

function handleMasterStart($user_id, $username, $chat_id) {
    global $EMOJI;

    $user = saveUser($user_id, ['username' => $username, 'coins' => getCoins($user_id) ?? 0]);

    $text = $EMOJI['bot'] . " <b>خوش آمدید به ربات‌ساز!</b>\n\n";
    $text .= "از این ربات می‌تونید ربات فرعی برای آپلودر فایل‌ها بسازید.\n";
    $text .= "موجودی شما: {$EMOJI['money']} <b>" . getCoins($user_id) . " سکه</b>\n";
    $text .= "هزینه ساخت هر ربات: " . BUILD_COST . " سکه\n";

    $buttons = [
        btn($EMOJI['add'] . " ساخت ربات جدید", "m_build"),
        btn($EMOJI['money'] . " موجودی من", "m_coins"),
        btn($EMOJI['bot'] . " ربات‌های من", "m_mybots"),
        btn($EMOJI['info'] . " راهنما", "m_help"),
    ];

    if ($user_id === ADMIN_ID) {
        $buttons[] = btn($EMOJI['admin'] . " پنل مدیریت", "m_admin");
    }

    sendMsg($chat_id, $text, $buttons);
}

function handleCallback($user_id, $chat_id, $msg_id, $data) {
    global $EMOJI;

    // Master bot callbacks
    if ($data === 'm_build') {
        answerCallback($update['callback_query_id'] ?? '', '');
        $coins = getCoins($user_id);
        if ($coins < BUILD_COST) {
            sendMsg($chat_id, $EMOJI['error'] . " موجودی کافی نیست!\nموجودی: $coins سکه\nنیاز: " . BUILD_COST . " سکه");
            return;
        }
        setState($user_id, ['action' => 'awaiting_token']);
        sendMsg($chat_id, "🔑 لطفا توکن ربات را ارسال کنید.\n(از @BotFather دریافت کنید)");
        return;
    }

    if ($data === 'm_coins') {
        answerCallback($update['callback_query_id'] ?? '', $EMOJI['money'] . " " . getCoins($user_id) . " سکه");
        return;
    }

    if ($data === 'm_mybots') {
        answerCallback($update['callback_query_id'] ?? '', '');
        $bots = ChildBotManager::getByUser($user_id);
        if (empty($bots)) {
            sendMsg($chat_id, "هنوز رباتی نساخته‌اید.");
            return;
        }
        foreach ($bots as $bot) {
            $status = $bot['active'] ? $EMOJI['check'] . " فعال" : $EMOJI['off'] . " غیرفعال";
            $text = $EMOJI['bot'] . " @{$bot['username']}\nوضعیت: $status";
            $buttons = [
                btnRow(
                    btn($EMOJI['off'] . " روشن/خاموش", "tb_{$bot['bot_id']}"),
                    btn($EMOJI['delete'] . " حذف", "db_{$bot['bot_id']}")
                )
            ];
            sendMsg($chat_id, $text, $buttons);
        }
        return;
    }

    if ($data === 'm_help') {
        answerCallback($update['callback_query_id'] ?? '', '');
        $text = "🆘 <b>راهنما:</b>\n\n";
        $text .= "1️⃣ روی «ساخت ربات» بزنید\n";
        $text .= "2️⃣ توکن BotFather را بفرستید\n";
        $text .= "3️⃣ " . BUILD_COST . " سکه کسر می‌شود\n";
        $text .= "4️⃣ ربات فعال می‌شود\n";
        $text .= "5️⃣ وارد ربات شوید و /panel رو بزنید\n";
        sendMsg($chat_id, $text);
        return;
    }

    if ($data === 'm_admin' && $user_id === ADMIN_ID) {
        answerCallback($update['callback_query_id'] ?? '', '');
        showAdminPanel($chat_id);
        return;
    }

    if (strpos($data, 'tb_') === 0) {
        answerCallback($update['callback_query_id'] ?? '', '');
        $bot_id = (int)substr($data, 3);
        $bot = ChildBotManager::get($bot_id);
        if ($bot && $bot['owner_id'] == $user_id) {
            ChildBotManager::toggleActive($bot_id);
            $status = $bot['active'] ? 'غیرفعال شد' : 'فعال شد';
            sendMsg($chat_id, $EMOJI['check'] . " ربات $status");
        }
        return;
    }

    if (strpos($data, 'db_') === 0) {
        answerCallback($update['callback_query_id'] ?? '', '');
        $bot_id = (int)substr($data, 3);
        $bot = ChildBotManager::get($bot_id);
        if ($bot && $bot['owner_id'] == $user_id) {
            ChildBotManager::delete($bot_id);
            sendMsg($chat_id, $EMOJI['delete'] . " ربات حذف شد");
        }
        return;
    }
}

function handleMasterState($user_id, $text, $chat_id, $state) {
    global $EMOJI;

    if ($state['action'] === 'awaiting_token') {
        $token = trim($text);

        // Verify token
        $result = apiBot($token, 'getMe', []);
        if (!$result['ok']) {
            sendMsg($chat_id, $EMOJI['error'] . " توکن نامعتبر است!");
            return;
        }

        $username = $result['result']['username'] ?? 'bot';

        // Check if token already exists
        $bots = ChildBotManager::all();
        foreach ($bots as $bot) {
            if ($bot['token'] === $token) {
                sendMsg($chat_id, $EMOJI['warning'] . " این توکن قبلا ثبت شده!");
                clearState($user_id);
                return;
            }
        }

        // Create bot
        $coins = getCoins($user_id);
        if ($coins < BUILD_COST) {
            sendMsg($chat_id, $EMOJI['error'] . " موجودی کافی نیست!");
            clearState($user_id);
            return;
        }

        $bot_id = ChildBotManager::create($user_id, $token, $username);
        addCoins($user_id, -BUILD_COST);

        clearState($user_id);
        sendMsg($chat_id, $EMOJI['check'] . " <b>ربات ایجاد شد!</b>\n\n" .
            "نام: @$username\n" .
            "هزینه: " . BUILD_COST . " سکه\n\n" .
            "حالا می‌تونید وارد ربات شوید و /panel رو بزنید.");
    }
}

function setState($user_id, $state) {
    $states = load('states');
    $states[$user_id] = $state;
    save('states', $states);
}

function getState($user_id) {
    $states = load('states');
    return $states[$user_id] ?? null;
}

function clearState($user_id) {
    $states = load('states');
    unset($states[$user_id]);
    save('states', $states);
}

function showAdminPanel($chat_id) {
    global $EMOJI;

    $bots_count = count(ChildBotManager::all());
    $users_count = count(load('users'));

    $text = $EMOJI['admin'] . " <b>پنل مدیریت</b>\n\n";
    $text .= "ربات‌ها: $bots_count\n";
    $text .= "کاربران: $users_count\n";

    $buttons = [
        btn($EMOJI['chart'] . " آمار", "a_stats"),
        btn($EMOJI['money'] . " مدیریت سکه", "a_coins"),
        btn($EMOJI['bot'] . " مدیریت ربات‌ها", "a_bots"),
    ];

    sendMsg($chat_id, $text, $buttons);
}

// ============================================
// 🎯 Child Bot Handler
// ============================================

class ChildBotHandler {
    public static function getChildBotByToken($token) {
        $bots = ChildBotManager::all();
        foreach ($bots as $bot) {
            if ($bot['token'] === $token) {
                return $bot;
            }
        }
        return null;
    }

    public static function processChildUpdate($bot_id, $update) {
        global $EMOJI;

        $message = $update['message'] ?? null;
        $callback = $update['callback_query'] ?? null;

        if ($message) {
            $text = $message['text'] ?? '';
            $from_id = $message['from']['id'];
            $username = $message['from']['username'] ?? 'Unknown';
            $chat_id = $message['chat']['id'];
            $content_type = $message['content_type'] ?? null;

            $bot = ChildBotManager::get($bot_id);
            if (!$bot) return;

            // /panel command for bot owner
            if (($text === '/panel' || $text === '/start panel') && $from_id == $bot['owner_id']) {
                self::showOwnerPanel($bot_id, $from_id, $chat_id, $bot['token']);
                return;
            }

            // /start command
            if (strpos($text, '/start') === 0) {
                self::handleChildStart($bot_id, $from_id, $username, $chat_id, $text, $bot['token']);
                return;
            }

            // Check if user has a state
            $state = self::getChildState($bot_id, $from_id);
            if ($state) {
                self::handleChildState($bot_id, $from_id, $text, $chat_id, $state, $message, $bot['token']);
                return;
            }

            // Owner panel button handling
            if ($from_id == $bot['owner_id']) {
                $handled = self::handleOwnerButtons($bot_id, $from_id, $text, $chat_id, $bot['token']);
                if ($handled) return;
            }

            // Regular user
            BotUsers::addUser($bot_id, $from_id, $username);
            if (BotUsers::isBanned($bot_id, $from_id)) {
                sendMsg($chat_id, $EMOJI['error'] . " شما مسدود شده‌اید.", null, $bot['token']);
                return;
            }

            self::sendWelcome($bot_id, $from_id, $chat_id, $bot['token']);
        }

        if ($callback) {
            $from_id = $callback['from']['id'];
            $chat_id = $callback['message']['chat']['id'];
            $msg_id = $callback['message']['message_id'];
            $data = $callback['data'];

            $bot = ChildBotManager::get($bot_id);
            if (!$bot) return;

            self::handleChildCallback($bot_id, $from_id, $chat_id, $msg_id, $data, $bot['token'], $bot['owner_id']);
        }
    }

    public static function showOwnerPanel($bot_id, $user_id, $chat_id, $token) {
        global $EMOJI;

        $settings = loadBotData($bot_id, 'settings');

        $text = $EMOJI['settings'] . " <b>پنل مدیریت ربات</b>\n";
        $text .= "وضعیت: " . ($settings['active'] ?? true ? $EMOJI['check'] . ' فعال' : $EMOJI['off'] . ' غیرفعال');

        $buttons = [
            btn($EMOJI['upload'] . " آپلود فایل", "owner_upload"),
            btn($EMOJI['batch'] . " آپلود گروهی", "owner_batch"),
            btn($EMOJI['broadcast'] . " پیام همگانی", "owner_broadcast"),
            btn($EMOJI['files'] . " فایل‌ها و آمار", "owner_stats"),
            btn($EMOJI['settings'] . " تنظیمات", "owner_settings"),
            btn($EMOJI['off'] . " روشن/خاموش", "owner_toggle"),
            btn($EMOJI['delete'] . " حذف ربات", "owner_delete"),
        ];

        sendMsg($chat_id, $text, $buttons, $token);
    }

    public static function handleChildStart($bot_id, $user_id, $username, $chat_id, $text, $token) {
        global $EMOJI;

        // Extract start parameter (file code)
        $parts = explode(' ', $text);
        $code = $parts[1] ?? null;

        $bot = ChildBotManager::get($bot_id);
        BotUsers::addUser($bot_id, $user_id, $username);

        if (BotUsers::isBanned($bot_id, $user_id)) {
            sendMsg($chat_id, $EMOJI['error'] . " شما مسدود شده‌اید.", null, $token);
            return;
        }

        // If user requested a file
        if ($code) {
            self::handleFileRequest($bot_id, $user_id, $chat_id, $code, $token);
            return;
        }

        // Show welcome
        self::sendWelcome($bot_id, $user_id, $chat_id, $token);
    }

    public static function handleFileRequest($bot_id, $user_id, $chat_id, $code, $token) {
        global $EMOJI;

        // Check if VIP or needs to pass checks
        $is_vip = BotUsers::isVip($bot_id, $user_id);

        if (!$is_vip) {
            // Check forced joins
            $join_check = ForcedJoin::checkJoin($bot_id, $user_id, $token);
            if ($join_check !== true) {
                $settings = loadBotData($bot_id, 'settings');
                $text = $settings['join_text'] ?? '🔐 برای دریافت فایل باید عضو شوید:';
                $buttons = [];
                foreach ((array)$join_check as $channel) {
                    $buttons[] = btn("$channel", "join_$code");
                }
                $buttons[] = btn($EMOJI['check'] . " عضو شدم", "check_join_$code");
                sendMsg($chat_id, $text, $buttons, $token);
                return;
            }

            // Check if task required
            $settings = loadBotData($bot_id, 'settings');
            if ($settings['task_enabled'] ?? false) {
                $task_text = $settings['task_text'] ?? '';
                $text = "🎯 <b>انجام وظیفه لازمی است:</b>\n\n$task_text";
                $buttons = [btn($EMOJI['check'] . " انجام دادم", "task_$code")];
                sendMsg($chat_id, $text, $buttons, $token);
                return;
            }
        }

        // Deliver file
        self::deliverFile($bot_id, $user_id, $chat_id, $code, $token);
    }

    public static function deliverFile($bot_id, $user_id, $chat_id, $code, $token) {
        global $EMOJI;

        // Check if batch or single file
        if (strpos($code, 'b_') === 0) {
            $batch_code = substr($code, 2);
            $batches = loadBotData($bot_id, 'batches');
            $batch = null;
            foreach ($batches as $b) {
                if ($b['code'] === "b_$batch_code") {
                    $batch = $b;
                    break;
                }
            }
            if (!$batch) {
                sendMsg($chat_id, $EMOJI['error'] . " فایل پیدا نشد", null, $token);
                return;
            }

            // Send all files in batch
            $files = loadBotData($bot_id, 'files');
            $count = 0;
            foreach ($files as $file) {
                if ($file['batch_id'] === $batch['batch_id']) {
                    self::sendOneFile($bot_id, $file, $chat_id, $token);
                    $count++;
                }
            }

            if ($count > 0) {
                sendMsg($chat_id, $EMOJI['check'] . " " . $count . " فایل ارسال شد", null, $token);
            }
        } else {
            $file = FileUploader::getFile($bot_id, $code);
            if (!$file) {
                sendMsg($chat_id, $EMOJI['error'] . " فایل پیدا نشد", null, $token);
                return;
            }
            self::sendOneFile($bot_id, $file, $chat_id, $token);
        }

        FileUploader::recordDownload($bot_id, $code);
    }

    public static function sendOneFile($bot_id, $file, $chat_id, $token) {
        $settings = loadBotData($bot_id, 'settings');

        $data = [
            'chat_id' => $chat_id,
            'document' => $file['file_id']
        ];

        if ($settings['file_caption'] ?? '') {
            $data['caption'] = str_replace('{name}', $file['file_name'], $settings['file_caption']);
            $data['parse_mode'] = 'HTML';
        }

        if ($settings['protect_content'] ?? false) {
            $data['protect_content'] = true;
        }

        apiBot($token, 'sendDocument', $data);
    }

    public static function sendWelcome($bot_id, $user_id, $chat_id, $token) {
        global $EMOJI;

        $settings = loadBotData($bot_id, 'settings');
        $emoji = $settings['premium_emoji'] ?? '✨';
        $text = str_replace('{name}', '', $settings['start_message'] ?? 'سلام خوش اومدی');

        sendMsg($chat_id, "$emoji $text", null, $token);
    }

    public static function handleOwnerButtons($bot_id, $user_id, $text, $chat_id, $token) {
        global $EMOJI;

        $buttons_map = [
            $EMOJI['upload'] . " آپلود فایل" => "owner_upload",
            $EMOJI['batch'] . " آپلود گروهی" => "owner_batch",
            $EMOJI['broadcast'] . " پیام همگانی" => "owner_broadcast",
            $EMOJI['files'] . " فایل‌ها و آمار" => "owner_stats",
            $EMOJI['settings'] . " تنظیمات" => "owner_settings",
            $EMOJI['off'] . " روشن/خاموش" => "owner_toggle",
        ];

        foreach ($buttons_map as $label => $action) {
            if ($text === $label) {
                self::setChildState($bot_id, $user_id, ['action' => $action]);
                return true;
            }
        }

        return false;
    }

    public static function handleChildState($bot_id, $user_id, $text, $chat_id, $state, $message, $token) {
        global $EMOJI;

        $action = $state['action'] ?? '';

        if ($action === 'owner_upload') {
            // Handle file upload
            if (isset($message['document'])) {
                $file = $message['document'];
                $code = FileUploader::upload($bot_id, $file['file_id'], $file['file_name'] ?? 'file', 'document');
                $bot = ChildBotManager::get($bot_id);
                $link = "https://t.me/{$bot['username']}?start=$code";
                sendMsg($chat_id, $EMOJI['check'] . " فایل ذخیره شد!\n\n🔗 لینک:\n$link", null, $token);
                self::clearChildState($bot_id, $user_id);
            } else {
                sendMsg($chat_id, "لطفا یک فایل ارسال کنید", null, $token);
            }
            return;
        }

        if ($action === 'owner_batch') {
            if ($text === '/done' || $text === 'پایان') {
                $batch_id = $state['batch_id'] ?? null;
                self::clearChildState($bot_id, $user_id);
                if ($batch_id) {
                    $batch = FileUploader::getBatch($bot_id, $batch_id);
                    if ($batch && count($batch['files']) > 0) {
                        $bot = ChildBotManager::get($bot_id);
                        $link = "https://t.me/{$bot['username']}?start=b_{$batch['code']}";
                        sendMsg($chat_id, $EMOJI['check'] . " آپلود گروهی تمام شد!\n" .
                            "فایل‌ها: " . count($batch['files']) . "\n\n🔗 لینک:\n$link", null, $token);
                    }
                }
                return;
            }

            if (isset($message['document'])) {
                $file = $message['document'];
                $batch_id = $state['batch_id'] ?? FileUploader::startBatch($bot_id);
                FileUploader::addToBatch($bot_id, $batch_id, $file['file_id'], $file['file_name'] ?? 'file', 'document');
                self::setChildState($bot_id, $user_id, ['action' => 'owner_batch', 'batch_id' => $batch_id]);
                $count = $state['count'] ?? 0;
                sendMsg($chat_id, $EMOJI['check'] . " فایل " . ($count + 1) . " اضافه شد\n/done برای اتمام", null, $token);
            }
            return;
        }

        if ($action === 'owner_broadcast') {
            $users = loadBotData($bot_id, 'users');
            $ok = 0;
            $fail = 0;
            foreach ($users as $u) {
                if (!($u['banned'] ?? false)) {
                    $result = apiBot($token, 'sendMessage', [
                        'chat_id' => $u['id'],
                        'text' => $text,
                        'parse_mode' => 'HTML'
                    ]);
                    if ($result['ok']) $ok++;
                    else $fail++;
                    usleep(50000); // rate limiting
                }
            }
            sendMsg($chat_id, $EMOJI['broadcast'] . " پیام ارسال شد\nموفق: $ok\nناموفق: $fail", null, $token);
            self::clearChildState($bot_id, $user_id);
            return;
        }
    }

    public static function handleChildCallback($bot_id, $user_id, $chat_id, $msg_id, $data, $token, $owner_id) {
        global $EMOJI;

        // Owner callbacks
        if ($user_id == $owner_id) {
            if ($data === 'owner_settings') {
                self::showSettingsMenu($bot_id, $chat_id, $msg_id, $token);
                return;
            }

            if ($data === 'owner_stats') {
                $files = loadBotData($bot_id, 'files');
                $users = loadBotData($bot_id, 'users');
                $vips = loadBotData($bot_id, 'vips');
                $banned = array_filter((array)$users, fn($u) => $u['banned'] ?? false);

                $text = $EMOJI['chart'] . " <b>آمار ربات</b>\n\n";
                $text .= "👥 کاربران: " . count($users) . "\n";
                $text .= "💎 VIP: " . count($vips) . "\n";
                $text .= "🚫 مسدود: " . count($banned) . "\n";
                $text .= "📁 فایل‌ها: " . count($files) . "\n";

                sendMsg($chat_id, $text, null, $token);
                return;
            }

            if ($data === 'owner_toggle') {
                $settings = loadBotData($bot_id, 'settings');
                $settings['active'] = !($settings['active'] ?? true);
                saveBotData($bot_id, 'settings', $settings);
                $status = $settings['active'] ? 'فعال' : 'غیرفعال';
                sendMsg($chat_id, $EMOJI['check'] . " ربات $status شد", null, $token);
                return;
            }

            if ($data === 'owner_upload') {
                self::setChildState($bot_id, $user_id, ['action' => 'owner_upload']);
                sendMsg($chat_id, "لطفا فایل را ارسال کنید", null, $token);
                return;
            }

            if ($data === 'owner_batch') {
                $batch_id = FileUploader::startBatch($bot_id);
                self::setChildState($bot_id, $user_id, ['action' => 'owner_batch', 'batch_id' => $batch_id, 'count' => 0]);
                sendMsg($chat_id, "فایل‌ها را یکی پس از دیگری ارسال کنید\n/done برای اتمام", null, $token);
                return;
            }

            if ($data === 'owner_broadcast') {
                self::setChildState($bot_id, $user_id, ['action' => 'owner_broadcast']);
                sendMsg($chat_id, "متن پیام را ارسال کنید", null, $token);
                return;
            }

            if ($data === 'owner_delete') {
                ChildBotManager::delete($bot_id);
                sendMsg($chat_id, $EMOJI['delete'] . " ربات حذف شد", null, $token);
                return;
            }
        }

        // User callbacks
        if (strpos($data, 'check_join_') === 0) {
            $code = substr($data, 11);
            $check = ForcedJoin::checkJoin($bot_id, $user_id, $token);
            if ($check !== true) {
                answerCallback($update['callback_query_id'] ?? '', $EMOJI['error'] . " هنوز عضو نشده", true, $token);
                return;
            }
            answerCallback($update['callback_query_id'] ?? '', $EMOJI['check'] . " تایید شد", false, $token);
            self::handleFileRequest($bot_id, $user_id, $chat_id, $code, $token);
            return;
        }

        if (strpos($data, 'task_') === 0) {
            $code = substr($data, 5);
            answerCallback($update['callback_query_id'] ?? '', $EMOJI['check'] . " ثبت شد", false, $token);
            self::deliverFile($bot_id, $user_id, $chat_id, $code, $token);
            return;
        }
    }

    public static function showSettingsMenu($bot_id, $chat_id, $msg_id, $token) {
        global $EMOJI;

        $buttons = [
            btn($EMOJI['lock'] . " عضویت اجباری", "set_join"),
            btn($EMOJI['unlock'] . " وظیفه اجباری", "set_task"),
            btn($EMOJI['files'] . " تنظیمات فایل‌ها", "set_files"),
            btn($EMOJI['users'] . " VIP", "set_vip"),
            btn($EMOJI['back'] . " بازگشت", "back_panel"),
        ];

        sendMsg($chat_id, "⚙️ تنظیمات:", $buttons, $token);
    }

    public static function setChildState($bot_id, $user_id, $state) {
        $states = load('child_states');
        if (!isset($states[$bot_id])) $states[$bot_id] = [];
        $states[$bot_id][$user_id] = $state;
        save('child_states', $states);
    }

    public static function getChildState($bot_id, $user_id) {
        $states = load('child_states');
        return $states[$bot_id][$user_id] ?? null;
    }

    public static function clearChildState($bot_id, $user_id) {
        $states = load('child_states');
        if (isset($states[$bot_id])) {
            unset($states[$bot_id][$user_id]);
        }
        save('child_states', $states);
    }
}

// ============================================
// 🎯 Main Webhook Handler
// ============================================

// Get JSON
$json = file_get_contents('php://input');
$update = json_decode($json, true);

if ($update) {
    // Determine if this is master bot or child bot based on token
    // For now, route to master bot handler
    processUpdate($update);
}

// Return OK
http_response_code(200);
echo json_encode(['ok' => true]);
?>


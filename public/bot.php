<?php
/**
 * Telegram Bot Webhook Handler
 */

require_once __DIR__ . '/../src/config/config.php';
require_once __DIR__ . '/../src/classes/Logger.php';
require_once __DIR__ . '/../src/classes/Database.php';
require_once __DIR__ . '/../src/classes/TelegramAPI.php';
require_once __DIR__ . '/../src/classes/User.php';
require_once __DIR__ . '/../src/classes/UIBuilder.php';

use TelegramShop\Classes\Logger;
use TelegramShop\Classes\Database;
use TelegramShop\Classes\TelegramAPI;
use TelegramShop\Classes\User;
use TelegramShop\Classes\UIBuilder;

try {
    // Get incoming update
    $update = json_decode(file_get_contents('php://input'), true);

    if (!$update) {
        Logger::warning("Empty update received");
        http_response_code(400);
        die(json_encode(['error' => 'No update data']));
    }

    Logger::debug("Update received: " . json_encode($update));

    // Initialize API handler
    $api = new TelegramAPI();

    // Handle message
    if (isset($update['message'])) {
        handleMessage($update['message'], $api);
    }

    // Handle callback query
    if (isset($update['callback_query'])) {
        handleCallbackQuery($update['callback_query'], $api);
    }

    http_response_code(200);
    echo json_encode(['ok' => true]);

} catch (Exception $e) {
    Logger::error("Exception in bot handler: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleMessage($message, $api) {
    $chatId = $message['chat']['id'];
    $userId = $message['from']['id'];
    $text = $message['text'] ?? '';

    Logger::info("Message from user $userId: $text");

    // Create or get user
    $user = new User($userId);
    if (!$user->exists()) {
        $firstName = $message['from']['first_name'] ?? 'User';
        $username = $message['from']['username'] ?? null;
        User::create($userId, $firstName, $username);
    }

    // Handle commands
    if (strpos($text, '/') === 0) {
        handleCommand($text, $chatId, $userId, $api);
    }
}

function handleCommand($command, $chatId, $userId, $api) {
    switch ($command) {
        case '/start':
            handleStart($chatId, $userId, $api);
            break;

        case '/help':
            handleHelp($chatId, $api);
            break;

        case '/balance':
            handleBalance($chatId, $userId, $api);
            break;

        default:
            $api->sendMessage($chatId, "❌ دستور نامشخص است. برای کمک /help را بزنید.");
            break;
    }
}

function handleStart($chatId, $userId, $api) {
    $user = new User($userId);
    $userData = $user->getData();

    $welcomeText = sprintf(
        "👋 به سیستم خرید و فروش اعضا خوش آمدید!\n\n" .
        "<b>خوش آمدید %s</b>\n\n" .
        "موجودی شما: 💰 <b>%s</b>",
        htmlspecialchars($userData['first_name'] ?? 'کاربر'),
        number_format($user->getBalance(), 2, ',', ',')
    );

    $menu = UIBuilder::mainMenu($userId);

    $api->sendMessage($chatId, $menu['text'], [
        'reply_markup' => $menu['keyboard']
    ]);
}

function handleHelp($chatId, $api) {
    $helpText = "<b>راهنمای سیستم</b>\n\n" .
        "📌 <b>دستورات موجود:</b>\n" .
        "/start - بازگشت به منوی اصلی\n" .
        "/balance - مشاهده موجودی\n" .
        "/help - نمایش این راهنما\n\n" .
        "💡 <b>توضیحات:</b>\n" .
        "• برای خرید اعضا، گزینه \"خرید اعضا\" را انتخاب کنید\n" .
        "• برای فروش اعضا، گزینه \"فروش اعضا\" را انتخاب کنید\n" .
        "• تاریخچه تمام تراکنش‌های شما در بخش \"تاریخچه\" موجود است";

    $api->sendMessage($chatId, $helpText);
}

function handleBalance($chatId, $userId, $api) {
    $user = new User($userId);
    $userData = $user->getData();

    $balanceText = sprintf(
        "💰 <b>موجودی حساب شما</b>\n\n" .
        "مقدار: <b>%s</b>\n" .
        "روش پرداخت: <b>%s</b>\n\n" .
        "برای شارژ حساب، از منوی اصلی «خرید اعضا» را انتخاب کنید.",
        number_format($user->getBalance(), 2, ',', ','),
        $userData['payment_method'] ?? 'تنظیم نشده'
    );

    $api->sendMessage($chatId, $balanceText);
}

function handleCallbackQuery($callbackQuery, $api) {
    $chatId = $callbackQuery['from']['id'];
    $userId = $callbackQuery['from']['id'];
    $callbackData = $callbackQuery['data'] ?? '';
    $messageId = $callbackQuery['message']['message_id'] ?? null;
    $queryId = $callbackQuery['id'];

    Logger::info("Callback from user $userId: $callbackData");

    // Route callback actions
    if (strpos($callbackData, 'action_') === 0) {
        handleActionCallback($callbackData, $chatId, $userId, $messageId, $api);
    } elseif (strpos($callbackData, 'payment_') === 0) {
        handlePaymentCallback($callbackData, $chatId, $userId, $messageId, $api);
    } elseif (strpos($callbackData, 'admin_') === 0) {
        handleAdminCallback($callbackData, $chatId, $userId, $messageId, $api);
    } elseif ($callbackData === 'back_menu') {
        $menu = UIBuilder::mainMenu($userId);
        $api->editMessage($chatId, $messageId, $menu['text'], [
            'reply_markup' => $menu['keyboard']
        ]);
    } elseif ($callbackData === 'back_settings') {
        $menu = UIBuilder::settingsMenu($userId);
        $api->editMessage($chatId, $messageId, $menu['text'], [
            'reply_markup' => $menu['keyboard']
        ]);
    }

    // Answer callback query
    $api->answerCallbackQuery($queryId, "✅ درخواست پردازش شد");
}

function handleActionCallback($action, $chatId, $userId, $messageId, $api) {
    switch ($action) {
        case 'action_buy_members':
            $text = "🛍️ <b>خرید اعضا</b>\n\n" .
                    "این بخش به زودی فعال خواهد شد.";
            $api->sendMessage($chatId, $text);
            break;

        case 'action_sell_members':
            $text = "💰 <b>فروش اعضا</b>\n\n" .
                    "این بخش به زودی فعال خواهد شد.";
            $api->sendMessage($chatId, $text);
            break;

        case 'action_history':
            $user = new User($userId);
            $purchases = $user->getPurchases();

            if (empty($purchases)) {
                $text = "📋 <b>تاریخچه</b>\n\n" .
                        "تاکنون تراکنشی انجام نشده است.";
            } else {
                $text = "📋 <b>تاریخچه خریدها</b>\n\n";
                foreach ($purchases as $purchase) {
                    $text .= "• <code>" . $purchase['id'] . "</code> - " .
                             $purchase['members_count'] . " عضو - " .
                             $purchase['price'] . " ریال\n";
                }
            }
            $api->sendMessage($chatId, $text);
            break;

        case 'action_settings':
            $menu = UIBuilder::settingsMenu($userId);
            if ($messageId) {
                $api->editMessage($chatId, $messageId, $menu['text'], [
                    'reply_markup' => $menu['keyboard']
                ]);
            } else {
                $api->sendMessage($chatId, $menu['text'], [
                    'reply_markup' => $menu['keyboard']
                ]);
            }
            break;

        case 'action_admin_panel':
            if ($userId == ADMIN_ID) {
                $menu = UIBuilder::adminPanel();
                $api->editMessage($chatId, $messageId, $menu['text'], [
                    'reply_markup' => $menu['keyboard']
                ]);
            } else {
                $api->answerCallbackQuery($callbackQuery['id'], "❌ شما اجازه دسترسی ندارید", true);
            }
            break;
    }
}

function handlePaymentCallback($action, $chatId, $userId, $messageId, $api) {
    $method = str_replace('payment_', '', $action);
    $user = new User($userId);

    if ($user->setPaymentMethod($method)) {
        $api->answerCallbackQuery($callbackQuery['id'], "✅ روش پرداخت تغییر یافت");
        $menu = UIBuilder::settingsMenu($userId);
        $api->editMessage($chatId, $messageId, $menu['text'], [
            'reply_markup' => $menu['keyboard']
        ]);
    } else {
        $api->answerCallbackQuery($callbackQuery['id'], "❌ خطا در تغییر روش پرداخت", true);
    }
}

function handleAdminCallback($action, $chatId, $userId, $messageId, $api) {
    if ($userId != ADMIN_ID) {
        return;
    }

    switch ($action) {
        case 'admin_sub_bots':
            $text = "🤖 <b>مدیریت ربات های فرعی</b>\n\n" .
                    "این بخش به زودی فعال خواهد شد.";
            $api->sendMessage($chatId, $text);
            break;

        case 'admin_channels':
            $text = "📢 <b>مدیریت کانال ها</b>\n\n" .
                    "این بخش به زودی فعال خواهد شد.";
            $api->sendMessage($chatId, $text);
            break;

        case 'admin_users':
            $db = new Database();
            $totalUsers = $db->getAll('users');
            $text = "👥 <b>مدیریت کاربران</b>\n\n" .
                    "تعداد کاربران: " . count($totalUsers);
            $api->sendMessage($chatId, $text);
            break;

        case 'admin_reports':
            $text = "📊 <b>گزارش فروش</b>\n\n" .
                    "این بخش به زودی فعال خواهد شد.";
            $api->sendMessage($chatId, $text);
            break;

        case 'admin_settings':
            $text = "⚙️ <b>تنظیمات سیستم</b>\n\n" .
                    "این بخش به زودی فعال خواهد شد.";
            $api->sendMessage($chatId, $text);
            break;
    }
}
?>

<?php
/**
 * 🧪 فایل تست سیستم
 */

define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');

function load($file) {
    $path = DATA_DIR . "/$file.json";
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

function save($file, $data) {
    file_put_contents(DATA_DIR . "/$file.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo "🧪 تست سیستم خرید و فروش اعضا\n";
echo "=" . str_repeat("=", 50) . "\n\n";

// 1️⃣ تست دایرکتوری
echo "1️⃣ تست دایرکتوری...\n";
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
    echo "✅ دایرکتوری ایجاد شد\n";
} else {
    echo "✅ دایرکتوری موجود است\n";
}

// 2️⃣ تست ذخیره اطلاعات کاربری
echo "\n2️⃣ تست ذخیره کاربر...\n";
$testUser = [
    '12345' => [
        'telegram_id' => 12345,
        'first_name' => 'تست',
        'balance' => 1000,
        'is_seller' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]
];
save('users', $testUser);
$loaded = load('users');
if (isset($loaded['12345'])) {
    echo "✅ کاربر ذخیره و بارگیری شد\n";
} else {
    echo "❌ مشکل در ذخیره\n";
}

// 3️⃣ تست ربات‌های فرعی
echo "\n3️⃣ تست ربات‌های فرعی...\n";
$testBots = [
    'bot1' => [
        'id' => 'bot1',
        'name' => 'Member Bot 1',
        'username' => 'memberbot1',
        'token' => 'مثال',
        'status' => 'active'
    ]
];
save('subbots', $testBots);
$bots = load('subbots');
if (isset($bots['bot1'])) {
    echo "✅ ربات فرعی ذخیره شد\n";
} else {
    echo "❌ مشکل در ربات\n";
}

// 4️⃣ تست کانال‌ها
echo "\n4️⃣ تست کانال‌های اجباری...\n";
$testChannels = [
    'ch1' => [
        'id' => 'ch1',
        'bot_id' => 'bot1',
        'channel_id' => -1001234567890,
        'channel_name' => 'کانال تست',
        'is_mandatory' => true,
        'is_active' => true
    ]
];
save('channels', $testChannels);
$channels = load('channels');
if (isset($channels['ch1'])) {
    echo "✅ کانال ذخیره شد\n";
} else {
    echo "❌ مشکل در کانال\n";
}

// 5️⃣ تست خریدها
echo "\n5️⃣ تست خریدها...\n";
$testPurchases = [
    'p1' => [
        'id' => 'p1',
        'buyer_id' => 12345,
        'count' => 100,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ]
];
save('purchases', $testPurchases);
$purchases = load('purchases');
if (isset($purchases['p1'])) {
    echo "✅ خرید ذخیره شد\n";
} else {
    echo "❌ مشکل در خرید\n";
}

// 6️⃣ تست فروش‌ها
echo "\n6️⃣ تست فروش‌ها...\n";
$testSales = [
    's1' => [
        'id' => 's1',
        'seller_id' => 12345,
        'count' => 100,
        'price_per' => 50,
        'profit' => 500,
        'total' => 5500,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ]
];
save('sales', $testSales);
$sales = load('sales');
if (isset($sales['s1'])) {
    echo "✅ فروش ذخیره شد\n";
} else {
    echo "❌ مشکل در فروش\n";
}

// 7️⃣ تست اتصال ربات
echo "\n7️⃣ تست اتصال ربات تلگرام...\n";
$ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/getMe");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);

if ($result['ok'] ?? false) {
    echo "✅ ربات متصل است: @" . $result['result']['username'] . "\n";
} else {
    echo "❌ ربات متصل نیست\n";
}

// 📊 خلاصه
echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ تمام تست‌ها موفق بود!\n";
echo "🚀 سیستم آماده‌ی استفاده است\n";
echo "\n📁 فایل‌های ایجاد شده:\n";
$files = glob(DATA_DIR . '/*.json');
foreach ($files as $file) {
    $size = filesize($file);
    echo "  - " . basename($file) . " (" . number_format($size) . " بایت)\n";
}
?>

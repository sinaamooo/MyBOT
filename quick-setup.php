<?php
/**
 * Quick Setup - إعداد سریع بدون دیتابیس
 */

require_once __DIR__ . '/src/config/config.php';

echo "===============================================\n";
echo "سیستم خرید و فروش اعضا - اعداد سریع\n";
echo "===============================================\n\n";

// Step 1: Create directories
echo "[Step 1] Creating directories...\n";

$directories = [
    __DIR__ . '/storage/data',
    __DIR__ . '/storage/logs',
    __DIR__ . '/storage/uploads',
    __DIR__ . '/public/assets/uploads'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created: $dir\n";
        } else {
            echo "❌ Failed to create: $dir\n";
        }
    } else {
        echo "✅ Exists: $dir\n";
    }
}

echo "\n";

// Step 2: Initialize data files
echo "[Step 2] Initializing data files...\n";

$dataFiles = [
    'users' => [],
    'purchases' => [],
    'sales' => [],
    'transactions' => [],
    'sub_bots' => [],
    'channels' => [],
    'menu_buttons' => []
];

foreach ($dataFiles as $filename => $content) {
    $file = __DIR__ . '/storage/data/' . $filename . '.json';
    if (!file_exists($file)) {
        file_put_contents($file, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "✅ Created: $filename.json\n";
    } else {
        echo "✅ Exists: $filename.json\n";
    }
}

echo "\n";

// Step 3: Create admin user
echo "[Step 3] Creating admin user...\n";

$adminFile = __DIR__ . '/storage/data/users.json';
$users = json_decode(file_get_contents($adminFile), true) ?: [];

$adminExists = false;
foreach ($users as $user) {
    if ($user['telegram_id'] == ADMIN_ID) {
        $adminExists = true;
        break;
    }
}

if (!$adminExists) {
    $adminUser = [
        'id' => time() . '_' . ADMIN_ID,
        'telegram_id' => ADMIN_ID,
        'first_name' => 'Admin',
        'username' => 'admin',
        'phone' => null,
        'balance' => 0,
        'status' => 'active',
        'payment_method' => null,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $users[$adminUser['id']] = $adminUser;
    file_put_contents($adminFile, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "✅ Admin user created\n";
} else {
    echo "✅ Admin user already exists\n";
}

echo "\n";

// Step 4: Test bot connection
echo "[Step 4] Testing bot connection...\n";

$botToken = BOT_TOKEN;
$apiUrl = "https://api.telegram.org/bot$botToken/getMe";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['ok'] && isset($data['result']['username'])) {
        echo "✅ Bot connected successfully!\n";
        echo "   Bot Name: @" . $data['result']['username'] . "\n";
        echo "   Bot ID: " . $data['result']['id'] . "\n\n";
    }
} else {
    echo "⚠️ Could not verify bot connection (HTTP $httpCode)\n";
    echo "   Please check your bot token\n\n";
}

// Final Summary
echo "===============================================\n";
echo "✅ Setup Complete!\n";
echo "===============================================\n\n";

echo "📝 معلومات:\n";
echo "   Admin ID: " . ADMIN_ID . "\n";
echo "   Bot Token: " . substr(BOT_TOKEN, 0, 10) . "***\n";
echo "   Data Storage: " . __DIR__ . "/storage/data/\n\n";

echo "📌 الخطوات التالیة:\n";
echo "1. اذهب إلى: https://yourdomain.com/setup.php\n";
echo "2. اختبر الاتصال بالروبوت\n";
echo "3. اضبط عنوان Webhook\n";
echo "4. أرسل /start إلى الروبوت\n";
echo "5. أنت مستعد للبدء!\n\n";

echo "📊 عرض البيانات:\n";
echo "   جميع البيانات مخزنة في: storage/data/*.json\n";
echo "   السجلات في: storage/logs/*.log\n\n";

echo "🌐 الدخول إلى لوحة التحكم:\n";
echo "   URL: https://yourdomain.com/admin/\n";
echo "   Admin ID: " . ADMIN_ID . "\n\n";
?>

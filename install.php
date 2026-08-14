<?php
/**
 * Telegram Member Shop System - Installation Script
 * این اسکریپت برای ایجاد و تنظیم دیتابیس استفاده می‌شود
 */

require_once __DIR__ . '/src/config/config.php';

echo "===============================================\n";
echo "Telegram Member Shop System - Installation\n";
echo "===============================================\n\n";

// Step 1: Database Connection
echo "[Step 1] Connecting to database...\n";

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($mysqli->connect_error) {
        throw new Exception("Connection failed: " . $mysqli->connect_error);
    }

    echo "✅ Database connection successful!\n\n";

} catch (Exception $e) {
    echo "❌ Connection error: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Create Database
echo "[Step 2] Creating database if not exists...\n";

$dbName = DB_NAME;
$charset = DB_CHARSET;

$sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci";

if ($mysqli->query($sql)) {
    echo "✅ Database created or already exists!\n\n";
} else {
    echo "❌ Error creating database: " . $mysqli->error . "\n";
    exit(1);
}

// Step 3: Select Database
echo "[Step 3] Selecting database...\n";

if ($mysqli->select_db($dbName)) {
    echo "✅ Database selected!\n\n";
} else {
    echo "❌ Error selecting database: " . $mysqli->error . "\n";
    exit(1);
}

// Step 4: Create Tables
echo "[Step 4] Creating tables...\n";

$schema = file_get_contents(__DIR__ . '/src/database/schema.sql');

if (!$schema) {
    echo "❌ Cannot read schema file!\n";
    exit(1);
}

// Execute each query separately
$queries = array_filter(array_map('trim', explode(';', $schema)));

$tableCount = 0;
foreach ($queries as $query) {
    if (empty($query)) continue;

    if ($mysqli->query($query)) {
        $tableCount++;
        echo "✅ Table created/updated\n";
    } else {
        echo "⚠️ Query execution issue: " . $mysqli->error . "\n";
    }
}

echo "\n✅ $tableCount tables created/updated!\n\n";

// Step 5: Create Admin User
echo "[Step 5] Setting up admin user...\n";

$adminId = ADMIN_ID;
$adminFirstName = "Admin";

$checkAdmin = $mysqli->query("SELECT id FROM users WHERE telegram_id = $adminId LIMIT 1");

if ($checkAdmin && $checkAdmin->num_rows > 0) {
    echo "✅ Admin user already exists!\n\n";
} else {
    $now = date('Y-m-d H:i:s');
    $insertAdmin = "INSERT INTO users (telegram_id, first_name, status, created_at, updated_at)
                   VALUES ($adminId, '$adminFirstName', 'active', '$now', '$now')";

    if ($mysqli->query($insertAdmin)) {
        echo "✅ Admin user created successfully!\n\n";
    } else {
        echo "❌ Error creating admin user: " . $mysqli->error . "\n";
    }
}

// Step 6: Create directories
echo "[Step 6] Creating required directories...\n";

$directories = [
    __DIR__ . '/storage/logs',
    __DIR__ . '/storage/uploads',
    __DIR__ . '/public/assets/uploads'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Created: $dir\n";
        } else {
            echo "⚠️ Could not create: $dir\n";
        }
    }
}

echo "\n";

// Step 7: Test Bot Connection
echo "[Step 7] Testing bot connection...\n";

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
echo "✅ Installation Complete!\n";
echo "===============================================\n\n";

echo "📝 Configuration:\n";
echo "   Database: " . DB_NAME . "\n";
echo "   Admin ID: " . ADMIN_ID . "\n";
echo "   Profit %: " . PROFIT_PERCENTAGE . "%\n\n";

echo "📌 Next Steps:\n";
echo "1. Set webhook URL in your bot:\n";
echo "   GET https://api.telegram.org/bot" . substr(BOT_TOKEN, 0, 10) . "**.../setWebhook?url=YOUR_DOMAIN/bot.php\n\n";

echo "2. Copy admin panel files (when ready)\n\n";

echo "3. Verify database tables:\n";
echo "   mysql -h " . DB_HOST . " -u " . DB_USER . " " . DB_NAME . "\n\n";

$mysqli->close();
echo "✅ Database connection closed.\n";
?>

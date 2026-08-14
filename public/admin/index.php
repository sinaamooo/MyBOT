<?php
/**
 * Admin Panel - Dashboard
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/classes/Logger.php';
require_once __DIR__ . '/../../src/classes/FileStorage.php';

use TelegramShop\Classes\Logger;
use TelegramShop\Classes\FileStorage;

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/login.php');
    exit;
}

// Verify admin
$adminId = $_SESSION['admin_id'];
if ($adminId != ADMIN_ID) {
    header('Location: /admin/login.php');
    exit;
}

$storage = new FileStorage();

// Get statistics
$totalUsers = $storage->getAll('users');
$totalPurchases = $storage->getAll('purchases');
$totalSales = $storage->getAll('sales');

$userCount = count($totalUsers);
$purchaseCount = count($totalPurchases);
$salesCount = count($totalSales);

// Calculate totals
$totalRevenue = 0;
foreach ($totalPurchases as $purchase) {
    $totalRevenue += $purchase['price'];
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت - سیستم خرید و فروش اعضا</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header h1 {
            color: #333;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 1.1em;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
        }

        .stat-card .icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .stat-card .label {
            color: #666;
            font-size: 0.95em;
            margin-bottom: 10px;
        }

        .stat-card .value {
            color: #333;
            font-size: 2em;
            font-weight: bold;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .menu-btn {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 1.1em;
            font-weight: 500;
        }

        .menu-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.2);
            background: rgba(255, 255, 255, 1);
        }

        .menu-btn .icon {
            font-size: 2em;
            display: block;
            margin-bottom: 10px;
        }

        .logout-btn {
            background: #ff6b6b;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            float: left;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #ff5252;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }

            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header clearfix">
            <h1>🎛️ پنل مدیریت</h1>
            <p>سیستم خرید و فروش اعضای تلگرام</p>
            <a href="/admin/logout.php" class="logout-btn">🚪 خروج</a>
        </div>

        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="icon">👥</div>
                <div class="label">کل کاربران</div>
                <div class="value"><?php echo $userCount; ?></div>
            </div>

            <div class="stat-card">
                <div class="icon">🛍️</div>
                <div class="label">کل خریدها</div>
                <div class="value"><?php echo $purchaseCount; ?></div>
            </div>

            <div class="stat-card">
                <div class="icon">💰</div>
                <div class="label">کل درآمد</div>
                <div class="value"><?php echo number_format($totalRevenue, 0, '', ','); ?></div>
            </div>

            <div class="stat-card">
                <div class="icon">📤</div>
                <div class="label">فروش فعال</div>
                <div class="value"><?php echo $salesCount; ?></div>
            </div>
        </div>

        <div class="menu-grid">
            <a href="/admin/users.php" class="menu-btn">
                <span class="icon">👥</span>
                مدیریت کاربران
            </a>

            <a href="/admin/sub-bots.php" class="menu-btn">
                <span class="icon">🤖</span>
                ربات های فرعی
            </a>

            <a href="/admin/channels.php" class="menu-btn">
                <span class="icon">📢</span>
                مدیریت کانال ها
            </a>

            <a href="/admin/purchases.php" class="menu-btn">
                <span class="icon">📋</span>
                خریدها
            </a>

            <a href="/admin/sales.php" class="menu-btn">
                <span class="icon">📊</span>
                فروش ها
            </a>

            <a href="/admin/settings.php" class="menu-btn">
                <span class="icon">⚙️</span>
                تنظیمات
            </a>
        </div>
    </div>
</body>
</html>

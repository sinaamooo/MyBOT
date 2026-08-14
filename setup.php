<?php
/**
 * تنظیم Webhook و ربات - صفحه کامل
 */

define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

$status = '';
$message = '';
$botInfo = null;

// تست ربات
if (isset($_POST['test_bot'])) {
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/getMe");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = json_decode(curl_exec($ch), true);

    if ($result['ok']) {
        $status = 'success';
        $botInfo = $result['result'];
        $message = "✅ ربات متصل است!\n" .
                   "👤 نام: @" . $result['result']['username'] . "\n" .
                   "🆔 ID: " . $result['result']['id'];
    } else {
        $status = 'error';
        $message = "❌ توکن نادرست است";
    }
    curl_close($ch);
}

// تنظیم Webhook
if (isset($_POST['set_webhook'])) {
    $url = $_POST['webhook_url'] ?? '';
    if (!$url) {
        $status = 'error';
        $message = "❌ URL را وارد کنید";
    } else {
        $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/setWebhook");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $url]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = json_decode(curl_exec($ch), true);

        if ($result['ok']) {
            $status = 'success';
            $message = "✅ Webhook تنظیم شد!\n🌐 " . $url;
        } else {
            $status = 'error';
            $message = "❌ خطا: " . ($result['description'] ?? 'نامشخص');
        }
        curl_close($ch);
    }
}

// دریافت اطلاعات Webhook فعلی
$ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/getWebhookInfo");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$webhookInfo = json_decode(curl_exec($ch), true)['result'] ?? [];
curl_close($ch);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛍️ تنظیم سیستم خرید و فروش اعضا</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        .header h1 { color: #333; font-size: 2.2em; margin-bottom: 10px; }
        .header p { color: #666; }
        .box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .box h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
            font-size: 0.95em;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: 'Courier New', monospace;
        }
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102,126,234,0.3);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            white-space: pre-wrap;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #e3f2fd;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            color: #333;
            line-height: 1.6;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .stat-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-label { color: #666; font-size: 0.9em; }
        .stat-value { color: #667eea; font-size: 1.5em; font-weight: bold; margin-top: 5px; }
        small {
            display: block;
            margin-top: 8px;
            color: #999;
            font-size: 0.85em;
        }
        .danger { background: #fff3cd; border-left: 4px solid #ff6b6b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🛍️ سیستم خرید و فروش اعضا</h1>
            <p>تنظیم ربات و Webhook</p>
        </div>

        <?php if ($message): ?>
            <div class="<?php echo $status; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- تست ربات -->
        <div class="box">
            <h2>🧪 مرحله 1: تست ربات</h2>
            <form method="POST">
                <div class="form-group">
                    <label>🔑 توکن:</label>
                    <input type="text" value="<?php echo BOT_TOKEN; ?>" readonly>
                    <small>توکن از @BotFather دریافت شده‌است</small>
                </div>
                <button type="submit" name="test_bot">✅ تست اتصال</button>
            </form>

            <?php if ($botInfo): ?>
                <div class="info" style="margin-top: 15px;">
                    <strong>ℹ️ اطلاعات ربات:</strong><br>
                    👤 نام: <code><?php echo $botInfo['first_name']; ?></code><br>
                    @ نام‌کاربری: <code><?php echo $botInfo['username']; ?></code><br>
                    🆔 ID: <code><?php echo $botInfo['id']; ?></code>
                </div>
            <?php endif; ?>
        </div>

        <!-- تنظیم Webhook -->
        <div class="box">
            <h2>🔗 مرحله 2: تنظیم Webhook</h2>
            <form method="POST">
                <div class="form-group">
                    <label>🌐 آدرس Webhook:</label>
                    <input
                        type="url"
                        name="webhook_url"
                        placeholder="https://yourdomain.com/bot.php"
                        required
                    >
                    <small>مثال: https://example.com/bot.php</small>
                </div>
                <button type="submit" name="set_webhook">🚀 تنظیم Webhook</button>
            </form>

            <?php if ($webhookInfo && $webhookInfo['url']): ?>
                <div class="info" style="margin-top: 15px;">
                    <strong>✅ Webhook تنظیم شده:</strong><br>
                    🔗 <code><?php echo htmlspecialchars($webhookInfo['url']); ?></code><br>
                    🆔 آخرین خطا: <?php echo htmlspecialchars($webhookInfo['last_error_message'] ?? 'ندارد'); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- اطلاعات کامل -->
        <div class="box">
            <h2>📋 اطلاعات سیستم</h2>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-label">👤 Admin ID</div>
                    <div class="stat-value"><?php echo ADMIN_ID; ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">🔑 توکن</div>
                    <div class="stat-value">✅</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">💾 داده</div>
                    <div class="stat-value"><?php echo count(glob(DATA_DIR . '/*.json')); ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">🌐 Webhook</div>
                    <div class="stat-value"><?php echo ($webhookInfo['url'] ? '✅' : '❌'); ?></div>
                </div>
            </div>
        </div>

        <!-- راهنمای نصب -->
        <div class="box">
            <h2>📝 راهنما</h2>
            <div class="info">
                <strong>1️⃣ آپلود فایل‌ها:</strong><br>
                تمام فایل‌ها (bot.php, setup.php, admin.php) را به سرور بفرستید.
            </div>
            <div class="info">
                <strong>2️⃣ تست ربات:</strong><br>
                بالا، دکمه "تست اتصال" را کلیک کنید.
            </div>
            <div class="info">
                <strong>3️⃣ تنظیم Webhook:</strong><br>
                آدرس bot.php را در Webhook وارد کنید (مثل https://yourdomain.com/bot.php)
            </div>
            <div class="info">
                <strong>4️⃣ شروع کنید:</strong><br>
                پیام /start را به ربات بفرستید.
            </div>
            <div class="info danger">
                <strong>⚠️ توجه:</strong><br>
                - از HTTPS استفاده کنید (HTTP کار نمی‌کند)<br>
                - پوشه data/ باید قابل نوشتن باشد<br>
                - توکن را عمومی نکنید
            </div>
        </div>

        <!-- لینک‌های مفید -->
        <div class="box">
            <h2>🔗 لینک‌های مفید</h2>
            <div class="info">
                <strong>📌 دستورات:</strong><br>
                /start - منوی اصلی<br>
                /balance - موجودی<br>
                /help - راهنما
            </div>
            <div class="info">
                <strong>🔐 پنل مدیریت:</strong><br>
                <code>https://yourdomain.com/admin.php</code>
            </div>
        </div>
    </div>
</body>
</html>

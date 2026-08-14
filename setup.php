<?php
/**
 * تنظیم Webhook - ساده و بدون پیچیدگی
 */

define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
define('ADMIN_ID', 8213021584);

$status = '';
$message = '';

if ($_POST) {
    if (isset($_POST['test_bot'])) {
        $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/getMe");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = json_decode(curl_exec($ch), true);

        if ($result['ok']) {
            $status = 'success';
            $message = "✅ ربات متصل است!\n👤 نام: @" . $result['result']['username'];
        } else {
            $status = 'error';
            $message = "❌ توکن نادرست است";
        }
    }
    elseif (isset($_POST['set_webhook'])) {
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
                $message = "✅ Webhook تنظیم شد!\n🌐 آدرس: " . $url;
            } else {
                $status = 'error';
                $message = "❌ خطا: " . ($result['description'] ?? 'نامشخص');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیم ربات</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2em;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .box {
            background: #f9f9f9;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
        }
        .box h2 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.3em;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        input[type="text"],
        input[type="url"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
            font-family: monospace;
        }
        input[type="text"]:focus,
        input[type="url"]:focus {
            outline: none;
            border-color: #667eea;
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
            margin-top: 10px;
            transition: transform 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .info {
            background: #e3f2fd;
            border-right: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            color: #333;
        }
        small {
            display: block;
            margin-top: 8px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 تنظیم ربات</h1>
        <p class="subtitle">تنظیم آسان و سریع</p>

        <?php if ($message): ?>
            <div class="<?php echo $status; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- تست ربات -->
        <div class="box">
            <h2>🧪 مرحله 1: تست ربات</h2>
            <form method="POST">
                <label>توکن ربات:</label>
                <input type="text" value="<?php echo BOT_TOKEN; ?>" readonly>
                <small>توکن شما از @BotFather گرفته شده است</small>
                <button type="submit" name="test_bot">✅ تست کن</button>
            </form>
        </div>

        <!-- تنظیم Webhook -->
        <div class="box">
            <h2>🔗 مرحله 2: تنظیم Webhook</h2>
            <form method="POST">
                <label>آدرس Webhook:</label>
                <input
                    type="url"
                    name="webhook_url"
                    placeholder="https://yourdomain.com/bot.php"
                    required
                >
                <small>مثال: https://example.com/bot.php</small>
                <button type="submit" name="set_webhook">🚀 تنظیم کن</button>
            </form>
        </div>

        <!-- اطلاعات -->
        <div class="info">
            <strong>📝 اطلاعات:</strong><br>
            🆔 Admin ID: <?php echo ADMIN_ID; ?><br>
            🌐 پنل: https://yourdomain.com/admin.php<br>
            💾 داده‌ها: /data/*.json
        </div>
    </div>
</body>
</html>

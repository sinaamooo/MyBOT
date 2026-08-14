<?php
/**
 * Setup Wizard - تنظیم ربات و Webhook
 */

require_once __DIR__ . '/../src/config/config.php';

$setupMessage = '';
$setupStatus = '';
$webhookSet = false;

// بررسی اطلاعات قابل پیکربندی
$config = [
    'BOT_TOKEN' => BOT_TOKEN,
    'ADMIN_ID' => ADMIN_ID,
    'WEBHOOK_URL' => WEBHOOK_URL ?? ''
];

// تنظیم Webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'set_webhook') {
        $webhookUrl = $_POST['webhook_url'] ?? '';
        $botToken = $_POST['bot_token'] ?? BOT_TOKEN;

        if (!$webhookUrl) {
            $setupStatus = 'error';
            $setupMessage = '❌ لطفاً URL webhook را وارد کنید';
        } else {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot$botToken/setWebhook");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $webhookUrl]), false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if ($data['ok']) {
                    $setupStatus = 'success';
                    $setupMessage = '✅ Webhook با موفقیت تنظیم شد!';
                    $webhookSet = true;
                } else {
                    $setupStatus = 'error';
                    $setupMessage = '❌ خطا: ' . ($data['description'] ?? 'نامشخص');
                }
            } else {
                $setupStatus = 'error';
                $setupMessage = "❌ خطا در اتصال: HTTP $httpCode";
            }
        }
    }

    if ($action === 'test_bot') {
        $botToken = $_POST['bot_token'] ?? BOT_TOKEN;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot$botToken/getMe");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data['ok']) {
                $setupStatus = 'success';
                $botName = $data['result']['username'] ?? 'نامشخص';
                $botId = $data['result']['id'] ?? 'نامشخص';
                $setupMessage = "✅ ربات متصل است!\n👤 نام: @$botName\n🆔 ID: $botId";
            } else {
                $setupStatus = 'error';
                $setupMessage = '❌ توکن ربات نامعتبر است';
            }
        } else {
            $setupStatus = 'error';
            $setupMessage = "❌ خطا در اتصال: HTTP $httpCode";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیم ربات و Webhook</title>
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
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-align: center;
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

        .setup-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .setup-box h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            font-family: 'Courier New', monospace;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            white-space: pre-wrap;
        }

        .message.show {
            display: block;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f5c6cb;
        }

        .info-box {
            background: #e3f2fd;
            border-right: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #333;
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
            color: #667eea;
        }

        .code-block {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 12px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
            overflow-x: auto;
            word-break: break-all;
        }

        .step {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-right: 4px solid #667eea;
        }

        .step h3 {
            color: #667eea;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {
            .button-group {
                grid-template-columns: 1fr;
            }

            .header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 تنظیم ربات تلگرام</h1>
            <p>راه‌اندازی و تنظیم Webhook</p>
        </div>

        <!-- Message -->
        <?php if ($setupMessage): ?>
        <div class="message show <?php echo $setupStatus; ?>">
            <?php echo htmlspecialchars($setupMessage); ?>
        </div>
        <?php endif; ?>

        <!-- Step 1: Test Bot -->
        <div class="setup-box">
            <h2>📋 مرحله 1: تست ربات</h2>

            <div class="step">
                <h3>✓ بررسی اتصال ربات</h3>
                <p style="margin-bottom: 15px; color: #666;">برای اطمینان از صحت توکن ربات، روی دکمه زیر کلیک کنید:</p>

                <form method="POST">
                    <input type="hidden" name="action" value="test_bot">

                    <div class="form-group">
                        <label>🔑 توکن ربات:</label>
                        <input type="text" name="bot_token" value="<?php echo htmlspecialchars(BOT_TOKEN); ?>" readonly>
                    </div>

                    <button type="submit" class="btn btn-secondary">🧪 تست اتصال</button>
                </form>
            </div>
        </div>

        <!-- Step 2: Set Webhook -->
        <div class="setup-box">
            <h2>🔗 مرحله 2: تنظیم Webhook</h2>

            <div class="step">
                <h3>✓ تنظیم آدرس Webhook</h3>
                <p style="margin-bottom: 15px; color: #666;">Webhook URL را وارد کنید تا ربات بتواند پیام‌ها را دریافت کند:</p>

                <form method="POST">
                    <input type="hidden" name="action" value="set_webhook">

                    <div class="form-group">
                        <label>🌐 Webhook URL:</label>
                        <input
                            type="text"
                            name="webhook_url"
                            placeholder="https://yourdomain.com/bot.php"
                            value="<?php echo htmlspecialchars(WEBHOOK_URL); ?>"
                            required
                        >
                        <small style="color: #666; display: block; margin-top: 8px;">
                            مثال: <code>https://example.com/bot.php</code>
                        </small>
                    </div>

                    <div class="form-group">
                        <label>🔑 توکن ربات:</label>
                        <input type="text" name="bot_token" value="<?php echo htmlspecialchars(BOT_TOKEN); ?>" readonly>
                    </div>

                    <button type="submit" class="btn btn-primary">✅ تنظیم Webhook</button>
                </form>
            </div>
        </div>

        <!-- Step 3: Information -->
        <div class="setup-box">
            <h2>📝 اطلاعات مهم</h2>

            <div class="info-box">
                <strong>🆔 شناسه مدیر:</strong>
                <div class="code-block"><?php echo ADMIN_ID; ?></div>
            </div>

            <div class="info-box">
                <strong>🔑 توکن ربات:</strong>
                <div class="code-block"><?php echo BOT_TOKEN; ?></div>
            </div>

            <div class="info-box">
                <strong>🌐 Webhook URL:</strong>
                <div class="code-block"><?php echo WEBHOOK_URL ?? 'https://yourdomain.com/bot.php'; ?></div>
            </div>

            <div class="info-box">
                <strong>📊 پنل مدیریت:</strong>
                <div class="code-block">https://yourdomain.com/admin/</div>
            </div>
        </div>

        <!-- Next Steps -->
        <div class="setup-box">
            <h2>🚀 مراحل بعدی</h2>

            <div class="step">
                <h3>✓ بعد از تنظیم موفق:</h3>
                <ol style="margin-right: 20px; color: #666; line-height: 2;">
                    <li>پیام <code>/start</code> را به ربات بفرستید</li>
                    <li>بررسی کنید که منوی اصلی نمایش داده شود</li>
                    <li>برای دسترسی پنل مدیریت از <code>https://yourdomain.com/admin/</code> استفاده کنید</li>
                    <li>فایل‌های داده شما در پوشه <code>storage/data/</code> ذخیره می‌شوند</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>

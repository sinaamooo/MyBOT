<?php
/**
 * Admin Panel - Login
 */

session_start();

require_once __DIR__ . '/../../src/config/config.php';
require_once __DIR__ . '/../../src/classes/Logger.php';

use TelegramShop\Classes\Logger;

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == ADMIN_ID) {
    header('Location: /admin/');
    exit;
}

$error = '';
$adminIdInput = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminIdInput = isset($_POST['admin_id']) ? intval($_POST['admin_id']) : '';
    $pin = isset($_POST['pin']) ? $_POST['pin'] : '';

    // Simple PIN verification (in real scenario, use secure auth)
    // For now, admin_id must match ADMIN_ID
    if ($adminIdInput == ADMIN_ID) {
        $_SESSION['admin_id'] = $adminIdInput;
        $_SESSION['login_time'] = time();

        Logger::info("Admin $adminIdInput logged in");
        header('Location: /admin/');
        exit;
    } else {
        $error = 'کد مدیر نادرست است';
        Logger::warning("Failed login attempt with ID: $adminIdInput");
    }
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود - پنل مدیریت</title>
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header .icon {
            font-size: 3em;
            margin-bottom: 15px;
            display: block;
        }

        .login-header h1 {
            color: #333;
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .login-header p {
            color: #666;
            font-size: 0.95em;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .error-message {
            background: #ff6b6b;
            color: white;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            text-align: center;
        }

        .error-message.show {
            display: block;
        }

        .submit-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .info-box {
            background: #e3f2fd;
            border-right: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-top: 30px;
            color: #333;
            font-size: 0.9em;
            line-height: 1.6;
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <span class="icon">🔐</span>
            <h1>پنل مدیریت</h1>
            <p>سیستم خرید و فروش اعضا</p>
        </div>

        <div class="error-message <?php echo $error ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($error); ?>
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="admin_id">کد مدیر (Admin ID)</label>
                <input
                    type="text"
                    id="admin_id"
                    name="admin_id"
                    placeholder="<?php echo ADMIN_ID; ?>"
                    value="<?php echo htmlspecialchars($adminIdInput); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="pin">رمز عبور</label>
                <input
                    type="password"
                    id="pin"
                    name="pin"
                    placeholder="••••••"
                    required
                >
            </div>

            <button type="submit" class="submit-btn">ورود 🚀</button>
        </form>

        <div class="info-box">
            <strong>💡 توجه:</strong>
            برای دسترسی به پنل مدیریت، کد مدیر خود را وارد کنید.
        </div>
    </div>
</body>
</html>

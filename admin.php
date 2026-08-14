<?php
/**
 * پنل مدیریت - یک صفحه ساده
 */

session_start();

define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');

function loadData($name) {
    $file = DATA_DIR . "/$name.json";
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

// بررسی ورود
if ($_GET['action'] == 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_POST) {
    $adminId = intval($_POST['admin_id'] ?? 0);
    $pin = $_POST['pin'] ?? '';

    if ($adminId == ADMIN_ID) {
        $_SESSION['admin_id'] = $adminId;
        $_SESSION['login_time'] = time();
    } else {
        $error = '❌ کد مدیر نادرست';
    }
}

// چک ورود
$logged_in = isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == ADMIN_ID;

if (!$logged_in && $_POST) {
    $error = '❌ کد مدیر نادرست';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #333; }
        .logout-btn {
            background: #ff6b6b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
        }
        .logout-btn:hover { background: #ff5252; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
        }
        .stat-card .icon {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        .stat-card .label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 8px;
        }
        .stat-card .value {
            color: #667eea;
            font-size: 2em;
            font-weight: bold;
        }
        .login-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            max-width: 400px;
            margin: 50px auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-container h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1em;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102,126,234,0.3); }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .data-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .data-section h2 {
            color: #667eea;
            margin-bottom: 15px;
        }
        .data-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .data-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 0.9em;
            color: #666;
        }
        .data-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($logged_in): ?>
            <!-- داشبورد -->
            <div class="header">
                <h1>🔐 پنل مدیریت</h1>
                <a href="?action=logout" class="logout-btn">🚪 خروج</a>
            </div>

            <?php
            $users = loadData('users');
            $purchases = loadData('purchases');
            $sales = loadData('sales');
            ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">👥</div>
                    <div class="label">کاربران</div>
                    <div class="value"><?php echo count($users); ?></div>
                </div>
                <div class="stat-card">
                    <div class="icon">🛍️</div>
                    <div class="label">خریدها</div>
                    <div class="value"><?php echo count($purchases); ?></div>
                </div>
                <div class="stat-card">
                    <div class="icon">📊</div>
                    <div class="label">فروش‌ها</div>
                    <div class="value"><?php echo count($sales); ?></div>
                </div>
                <div class="stat-card">
                    <div class="icon">💰</div>
                    <div class="label">درآمد</div>
                    <div class="value">0</div>
                </div>
            </div>

            <!-- کاربران -->
            <div class="data-section">
                <h2>👥 کاربران</h2>
                <div class="data-list">
                    <?php if ($users): ?>
                        <?php foreach ($users as $user): ?>
                            <div class="data-item">
                                🆔 <?php echo $user['telegram_id']; ?> -
                                <?php echo htmlspecialchars($user['first_name'] ?? 'نامشناس'); ?>
                                (💰 <?php echo number_format($user['balance'] ?? 0); ?>)
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="data-item">هنوز کاربری ثبت نشده</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- خریدها -->
            <div class="data-section">
                <h2>🛍️ خریدها</h2>
                <div class="data-list">
                    <?php if ($purchases): ?>
                        <?php foreach ($purchases as $p): ?>
                            <div class="data-item">
                                🆔 <?php echo $p['id']; ?> -
                                <?php echo $p['members_count']; ?> عضو
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="data-item">هنوز خریدی انجام نشده</div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- صفحه ورود -->
            <div class="login-container">
                <h1>🔐 ورود</h1>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>کد مدیر</label>
                        <input type="text" name="admin_id" placeholder="8213021584" required>
                    </div>
                    <div class="form-group">
                        <label>رمز عبور</label>
                        <input type="password" name="pin" placeholder="••••••" required>
                    </div>
                    <button type="submit">ورود 🚀</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
/**
 * 🛞 پنل ادمین وب - سیستم فروش ممبرشیپ
 * رابط کاربری حرفه‌ای برای مدیریت ممبرشیپ‌ها و ربات‌ها
 */

session_start();

define('DATA_DIR', __DIR__ . '/data_master');
define('ADMIN_PASSWORD', 'admin123456');
define('ADMIN_ID', 8213021584);

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// توابع ذخیره و بارگیری
function save($file, $data) {
    file_put_contents(DATA_DIR . "/$file.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function load($file) {
    $path = DATA_DIR . "/$file.json";
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

// بررسی ورود
if (!isset($_SESSION['logged_in'])) {
    if ($_POST['password'] ?? '' === ADMIN_PASSWORD) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// خروج
if ($_GET['logout'] ?? false) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// عملیات‌های POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['logged_in'] ?? false) {
    if ($_POST['action'] === 'add_membership') {
        $memberships = load('memberships');
        $id = 'm_' . time();
        $memberships[$id] = [
            'id' => $id,
            'name' => $_POST['name'],
            'price' => (int)$_POST['price'],
            'currency' => $_POST['currency'],
            'members' => [],
            'limit' => (int)($_POST['limit'] ?? 0),
            'bot_id' => null,
            'created_at' => date('Y-m-d H:i:s')
        ];
        save('memberships', $memberships);
        $message = '✅ ممبرشیپ اضافه شد';
    }

    if ($_POST['action'] === 'add_bot') {
        $bots = load('bots');
        $bot_id = 'bot_' . time();
        $bots[$bot_id] = [
            'id' => $bot_id,
            'token' => $_POST['token'],
            'username' => $_POST['username'],
            'membership_id' => $_POST['membership_id'],
            'active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];
        save('bots', $bots);

        // تنظیم ممبرشیپ
        $memberships = load('memberships');
        $memberships[$_POST['membership_id']]['bot_id'] = $bot_id;
        save('memberships', $memberships);
        $message = '✅ ربات اضافه شد';
    }

    if ($_POST['action'] === 'delete_membership') {
        $memberships = load('memberships');
        unset($memberships[$_POST['id']]);
        save('memberships', $memberships);
        $message = '✅ ممبرشیپ حذف شد';
    }

    if ($_POST['action'] === 'delete_bot') {
        $bots = load('bots');
        unset($bots[$_POST['id']]);
        save('bots', $bots);
        $message = '✅ ربات حذف شد';
    }
}

// بارگذاری داده
$memberships = load('memberships');
$bots = load('bots');
$users = load('users');
$payments = load('payments');

$confirmed_payments = array_filter($payments, fn($p) => $p['status'] === 'confirmed');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛞 پنل ادمین - فروش ممبرشیپ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Vazir', 'Tahoma', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .login-box h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .login-box p {
            color: #666;
            margin-bottom: 30px;
        }

        .login-box input {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .login-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .login-box button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .login-box button:hover {
            transform: translateY(-2px);
        }

        .dashboard {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
        }

        .header a {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-box .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }

        .stat-box .label {
            color: #666;
            font-size: 14px;
        }

        .content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 24px;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }

        .card h3 {
            color: #555;
            font-size: 16px;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: transform 0.2s;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-danger {
            background: #e74c3c;
        }

        .list-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-item-info {
            flex: 1;
        }

        .list-item-info strong {
            display: block;
            color: #333;
            margin-bottom: 5px;
        }

        .list-item-info small {
            color: #999;
        }

        .list-item-actions {
            display: flex;
            gap: 10px;
        }

        .list-item-actions button {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .message {
            background: #27ae60;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .message.error {
            background: #e74c3c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: right;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .emoji {
            font-size: 20px;
        }

        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if (!($_SESSION['logged_in'] ?? false)): ?>
        <div class="login-container">
            <div class="login-box">
                <h1>🛞 پنل ادمین</h1>
                <p>فروش ممبرشیپ</p>
                <form method="POST">
                    <input type="password" name="password" placeholder="رمز عبور" required>
                    <button type="submit">ورود 🚀</button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard">
            <div class="header">
                <h1>🛞 پنل ادمین</h1>
                <a href="?logout=1">خروج ❌</a>
            </div>

            <?php if ($message ?? false): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="stats">
                <div class="stat-box">
                    <div class="emoji">👥</div>
                    <div class="number"><?php echo count($users); ?></div>
                    <div class="label">کاربران</div>
                </div>
                <div class="stat-box">
                    <div class="emoji">🛍️</div>
                    <div class="number"><?php echo count($memberships); ?></div>
                    <div class="label">ممبرشیپ‌ها</div>
                </div>
                <div class="stat-box">
                    <div class="emoji">🤖</div>
                    <div class="number"><?php echo count($bots); ?></div>
                    <div class="label">ربات‌ها</div>
                </div>
                <div class="stat-box">
                    <div class="emoji">💰</div>
                    <div class="number"><?php echo count($confirmed_payments); ?></div>
                    <div class="label">پرداخت‌های تایید شده</div>
                </div>
            </div>

            <div class="content">
                <!-- افزودن ممبرشیپ -->
                <div class="card">
                    <h2>🛍️ افزودن ممبرشیپ</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_membership">
                        <div class="form-group">
                            <label>نام ممبرشیپ:</label>
                            <input type="text" name="name" placeholder="مثال: پریمیوم" required>
                        </div>
                        <div class="form-group">
                            <label>قیمت:</label>
                            <input type="number" name="price" placeholder="مثال: 50" required>
                        </div>
                        <div class="form-group">
                            <label>واحد پول:</label>
                            <select name="currency" required>
                                <option value="">انتخاب کنید</option>
                                <option value="USDT">USDT</option>
                                <option value="TRX">TRX</option>
                                <option value="تومان">تومان</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>محدودیت اعضا (0 = نامحدود):</label>
                            <input type="number" name="limit" placeholder="مثال: 100" value="0">
                        </div>
                        <button class="btn" type="submit">افزودن ✅</button>
                    </form>

                    <h3>📋 ممبرشیپ‌های موجود:</h3>
                    <?php foreach ($memberships as $mem): ?>
                        <div class="list-item">
                            <div class="list-item-info">
                                <strong><?php echo $mem['name']; ?></strong>
                                <small><?php echo $mem['price'] . ' ' . $mem['currency']; ?></small>
                                <small style="display: block; margin-top: 5px;">
                                    اعضا: <?php echo count($mem['members']);
                                    if ($mem['limit'] > 0) echo '/' . $mem['limit']; ?>
                                </small>
                            </div>
                            <div class="list-item-actions">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_membership">
                                    <input type="hidden" name="id" value="<?php echo $mem['id']; ?>">
                                    <button class="btn-delete" type="submit" onclick="return confirm('مطمئن؟')">حذف</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- افزودن ربات -->
                <div class="card">
                    <h2>🤖 افزودن ربات</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="add_bot">
                        <div class="form-group">
                            <label>توکن ربات:</label>
                            <input type="text" name="token" placeholder="توکن از @BotFather" required>
                        </div>
                        <div class="form-group">
                            <label>نام کاربری:</label>
                            <input type="text" name="username" placeholder="@botusername" required>
                        </div>
                        <div class="form-group">
                            <label>ممبرشیپ:</label>
                            <select name="membership_id" required>
                                <option value="">انتخاب کنید</option>
                                <?php foreach ($memberships as $mem): ?>
                                    <option value="<?php echo $mem['id']; ?>"><?php echo $mem['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn" type="submit">اضافه کردن ✅</button>
                    </form>

                    <h3>📋 ربات‌های موجود:</h3>
                    <?php foreach ($bots as $bot):
                        $mem = $memberships[$bot['membership_id']] ?? null;
                    ?>
                        <div class="list-item">
                            <div class="list-item-info">
                                <strong>@<?php echo $bot['username']; ?></strong>
                                <small>ممبرشیپ: <?php echo $mem['name'] ?? '-'; ?></small>
                            </div>
                            <div class="list-item-actions">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_bot">
                                    <input type="hidden" name="id" value="<?php echo $bot['id']; ?>">
                                    <button class="btn-delete" type="submit" onclick="return confirm('مطمئن؟')">حذف</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- جدول پرداخت‌ها -->
            <div class="card">
                <h2>💳 پرداخت‌های اخیر</h2>
                <table>
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>ممبرشیپ</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent = array_slice(array_reverse($payments), 0, 10);
                        foreach ($recent as $pay):
                            $user = $users[$pay['user_id']] ?? ['username' => 'Unknown'];
                            $mem = $memberships[$pay['membership_id']] ?? ['name' => '-'];
                        ?>
                            <tr>
                                <td>@<?php echo $user['username']; ?></td>
                                <td><?php echo $mem['name']; ?></td>
                                <td><?php echo $pay['amount'] . ' ' . $pay['currency']; ?></td>
                                <td style="color: <?php echo $pay['status'] === 'confirmed' ? 'green' : 'orange'; ?>;">
                                    <?php echo $pay['status'] === 'confirmed' ? '✅ تایید' : '⏳ در انتظار'; ?>
                                </td>
                                <td><?php echo $pay['created_at']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>

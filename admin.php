<?php
/**
 * 🔐 پنل مدیریت - کامل و جامع
 */

session_start();

define('ADMIN_ID', 8213021584);
define('DATA_DIR', __DIR__ . '/data');

function load($file) {
    $path = DATA_DIR . "/$file.json";
    return file_exists($path) ? json_decode(file_get_contents($path), true) : [];
}

function save($file, $data) {
    file_put_contents(DATA_DIR . "/$file.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// login
if ($_POST) {
    $id = intval($_POST['admin_id'] ?? 0);
    if ($id == ADMIN_ID) {
        $_SESSION['admin_id'] = $id;
        $_SESSION['login_time'] = time();
    } else {
        $error = '❌ کد مدیر نادرست';
    }
}

$logged_in = isset($_SESSION['admin_id']) && $_SESSION['admin_id'] == ADMIN_ID;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔐 پنل مدیریت</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { color: #333; font-size: 1.8em; }
        .logout-btn {
            background: #ff6b6b;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
        }
        .logout-btn:hover { background: #ff5252; }

        /* Dashboard */
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
        .stat-card .icon { font-size: 2.5em; margin-bottom: 10px; }
        .stat-card .label { color: #666; font-size: 0.9em; }
        .stat-card .value { color: #667eea; font-size: 2em; font-weight: bold; margin-top: 8px; }

        /* Login */
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 400px;
            margin: 50px auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-container h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 1.8em;
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
        button:hover { transform: translateY(-2px); }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        /* Data Sections */
        .section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .section h2 {
            color: #667eea;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            background: #f9f9f9;
            padding: 12px;
            text-align: right;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }
        .data-table tr:hover { background: #f9f9f9; }

        .stat-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .stat-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .label { color: #999; font-size: 0.85em; }
        .stat-box .value { color: #667eea; font-size: 1.5em; font-weight: bold; margin-top: 8px; }

        .no-data {
            text-align: center;
            color: #999;
            padding: 20px;
        }
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
            $users = load('users');
            $purchases = load('purchases');
            $sales = load('sales');
            $subbots = load('subbots');
            $channels = load('channels');

            $totalRevenue = 0;
            foreach ($sales as $s) $totalRevenue += $s['total'] ?? 0;
            ?>

            <!-- آمار کلی -->
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
                    <div class="icon">💰</div>
                    <div class="label">درآمد</div>
                    <div class="value"><?php echo number_format($totalRevenue); ?></div>
                </div>
                <div class="stat-card">
                    <div class="icon">📊</div>
                    <div class="label">فروش‌ها</div>
                    <div class="value"><?php echo count($sales); ?></div>
                </div>
                <div class="stat-card">
                    <div class="icon">🤖</div>
                    <div class="label">ربات‌های فرعی</div>
                    <div class="value"><?php echo count($subbots); ?></div>
                </div>
                <div class="stat-card">
                    <div class="icon">📢</div>
                    <div class="label">کانال‌ها</div>
                    <div class="value"><?php echo count($channels); ?></div>
                </div>
            </div>

            <!-- کاربران -->
            <div class="section">
                <h2>👥 کاربران</h2>
                <?php if ($users): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>شماره تلفن</th>
                                <th>موجودی</th>
                                <th>روش پرداخت</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($users, 0, 10) as $u): ?>
                            <tr>
                                <td>👤 <?php echo htmlspecialchars($u['first_name'] ?? 'نامشناس'); ?></td>
                                <td><?php echo htmlspecialchars($u['phone'] ?? '❌'); ?></td>
                                <td>💰 <?php echo number_format($u['balance'] ?? 0); ?></td>
                                <td><?php echo htmlspecialchars($u['payment_method'] ?? 'نامشخص'); ?></td>
                                <td><?php echo ($u['is_seller'] ? '🟢 فروشنده' : '🔵 خریدار'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">هنوز کاربری ثبت نشده‌است</div>
                <?php endif; ?>
            </div>

            <!-- خریدها -->
            <div class="section">
                <h2>🛍️ خریدها</h2>
                <div class="stat-row">
                    <div class="stat-box">
                        <div class="label">کل خریدها</div>
                        <div class="value"><?php echo count($purchases); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">در انتظار</div>
                        <div class="value"><?php echo count(array_filter($purchases, fn($p) => ($p['status'] ?? '') == 'pending')); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">تکمیل شده</div>
                        <div class="value"><?php echo count(array_filter($purchases, fn($p) => ($p['status'] ?? '') == 'completed')); ?></div>
                    </div>
                </div>

                <?php if ($purchases): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>تعداد</th>
                                <th>روش پرداخت</th>
                                <th>وضعیت</th>
                                <th>تاریخ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($purchases, 0, 10) as $p): ?>
                            <tr>
                                <td><code><?php echo substr($p['id'] ?? '', -8); ?></code></td>
                                <td>📦 <?php echo $p['count'] ?? 0; ?></td>
                                <td><?php echo htmlspecialchars($p['payment_method'] ?? 'نامشخص'); ?></td>
                                <td><?php echo ($p['status'] == 'pending' ? '⏳ در انتظار' : '✅ تکمیل'); ?></td>
                                <td><?php echo substr($p['created_at'] ?? '', 0, 10); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">هنوز خریدی انجام نشده‌است</div>
                <?php endif; ?>
            </div>

            <!-- فروش‌ها -->
            <div class="section">
                <h2>💰 فروش‌ها</h2>
                <div class="stat-row">
                    <div class="stat-box">
                        <div class="label">کل فروش‌ها</div>
                        <div class="value"><?php echo count($sales); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">فعال</div>
                        <div class="value"><?php echo count(array_filter($sales, fn($s) => ($s['status'] ?? '') == 'active')); ?></div>
                    </div>
                    <div class="stat-box">
                        <div class="label">درآمد کل</div>
                        <div class="value"><?php echo number_format($totalRevenue); ?></div>
                    </div>
                </div>

                <?php if ($sales): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>شناسه</th>
                                <th>تعداد</th>
                                <th>قیمت</th>
                                <th>سود</th>
                                <th>کل</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($sales, 0, 10) as $s): ?>
                            <tr>
                                <td><code><?php echo substr($s['id'] ?? '', -8); ?></code></td>
                                <td><?php echo $s['count'] ?? 0; ?></td>
                                <td><?php echo number_format($s['price_per'] ?? 0); ?></td>
                                <td>📈 <?php echo number_format($s['profit'] ?? 0); ?></td>
                                <td><strong><?php echo number_format($s['total'] ?? 0); ?></strong></td>
                                <td><?php echo ($s['status'] == 'active' ? '🟢 فعال' : '⚫ غیرفعال'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">هنوز فروشی انجام نشده‌است</div>
                <?php endif; ?>
            </div>

            <!-- ربات‌های فرعی -->
            <div class="section">
                <h2>🤖 ربات‌های فرعی</h2>
                <?php if ($subbots): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>نام</th>
                                <th>نام‌کاربری</th>
                                <th>شناسه</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subbots as $bot): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($bot['name'] ?? ''); ?></td>
                                <td>@<?php echo htmlspecialchars($bot['username'] ?? ''); ?></td>
                                <td><code><?php echo substr($bot['id'] ?? '', -8); ?></code></td>
                                <td><?php echo ($bot['status'] == 'active' ? '🟢 فعال' : '⚫ غیرفعال'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">هنوز ربات فرعی‌ای اضافه نشده‌است</div>
                <?php endif; ?>
            </div>

            <!-- کانال‌های اجباری -->
            <div class="section">
                <h2>📢 کانال‌های اجباری</h2>
                <?php if ($channels): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>کانال</th>
                                <th>ربات فرعی</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($channels as $ch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ch['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($ch['bot_name'] ?? 'نامشخص'); ?></td>
                                <td><?php echo ($ch['is_active'] ?? true ? '🟢 فعال' : '⚫ غیرفعال'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">هنوز کانالی اضافه نشده‌است</div>
                <?php endif; ?>
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
                        <input type="text" name="admin_id" placeholder="8213021584" required autofocus>
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

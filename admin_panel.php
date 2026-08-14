<?php
/**
 * 👑 پنل ادمین وب — سیستم فروش ممبرشیپ
 *
 * منطق داده از bot_master_membership.php می‌آید (کتابخانه مشترک)
 * تا هرگز دو نسخه ناهماهنگ از داده‌ها وجود نداشته باشد.
 */

// ⚙️ رمز پنل — حتما عوض کنید
define('ADMIN_PASSWORD', 'admin123456');

define('MEMBERSHIP_LIB_ONLY', true);
require_once __DIR__ . '/bot_master_membership.php';

session_start();

// ------------------------------------------------------------
// 🔐 احراز هویت
// ------------------------------------------------------------

function renderLogin($error) { ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود — پنل مدیریت</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:grid;place-items:center;font-family:system-ui,'Segoe UI',Tahoma,sans-serif;
background:linear-gradient(135deg,#667eea,#764ba2);padding:20px}
.card{background:#fff;padding:40px 32px;border-radius:20px;box-shadow:0 20px 60px rgba(0,0,0,.3);width:100%;max-width:380px;text-align:center}
h1{font-size:22px;margin-bottom:6px;color:#2d3748}
p.sub{color:#718096;font-size:13px;margin-bottom:24px}
input{width:100%;padding:14px 16px;border:2px solid #e2e8f0;border-radius:12px;font-size:15px;font-family:inherit;margin-bottom:14px;text-align:center}
input:focus{outline:none;border-color:#667eea}
button{width:100%;padding:14px;border:0;border-radius:12px;background:linear-gradient(135deg,#667eea,#764ba2);
color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}
button:hover{opacity:.92}
.err{background:#fed7d7;color:#c53030;padding:10px;border-radius:10px;font-size:13px;margin-bottom:14px}
</style>
</head>
<body>
<form class="card" method="post">
  <div style="font-size:44px">👑</div>
  <h1>پنل مدیریت</h1>
  <p class="sub">سیستم فروش ممبرشیپ</p>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <input type="password" name="password" placeholder="رمز عبور" autofocus required>
  <button type="submit">ورود</button>
</form>
</body>
</html>
<?php }

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$loginError = '';

if (empty($_SESSION['logged_in'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        // باگ قبلی: «$_POST['password'] ?? '' === ADMIN_PASSWORD» به خاطر تقدم
        // عملگرها به «$_POST['password'] ?? false» تبدیل می‌شد و هر رمزی وارد می‌شد.
        if (hash_equals(ADMIN_PASSWORD, (string)$_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        usleep(400000); // کند کردن حدس رمز
        $loginError = 'رمز عبور اشتباه است.';
    }
    renderLogin($loginError);
    exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('درخواست نامعتبر (CSRF).');
    }
}

function redirectSelf($flash = null, $type = 'ok') {
    if ($flash !== null) $_SESSION['flash'] = ['msg' => $flash, 'type' => $type];
    $tab = $_POST['tab'] ?? $_GET['tab'] ?? 'dashboard';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?tab=' . urlencode($tab));
    exit;
}

function panelBaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $host . $dir;
}

// ------------------------------------------------------------
// 📮 عملیات
// ------------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = $_POST['action'] ?? '';

    // --- ممبرشیپ ---
    if ($action === 'add_membership') {
        $name     = trim($_POST['name'] ?? '');
        $price    = str_replace(',', '', trim($_POST['price'] ?? ''));
        $currency = trim($_POST['currency'] ?? 'USDT');
        $limit    = (int)($_POST['limit'] ?? 0);
        $desc     = trim($_POST['desc'] ?? '');

        if ($name === '' || !is_numeric($price)) redirectSelf('نام و قیمت معتبر لازم است.', 'err');
        MembershipManager::create($name, (float)$price, $currency, max(0, $limit), $desc);
        redirectSelf('ممبرشیپ «' . $name . '» ساخته شد.');
    }

    if ($action === 'delete_membership') {
        $id = $_POST['id'] ?? '';
        mutate('memberships', function (&$all) use ($id) { unset($all[$id]); });
        mutate('bots', function (&$all) use ($id) {
            foreach ($all as $k => $b) if (($b['membership_id'] ?? null) === $id) $all[$k]['membership_id'] = null;
        });
        redirectSelf('ممبرشیپ حذف شد.');
    }

    if ($action === 'toggle_membership') {
        $id = $_POST['id'] ?? '';
        mutate('memberships', function (&$all) use ($id) {
            if (isset($all[$id])) $all[$id]['active'] = empty($all[$id]['active']);
        });
        redirectSelf('وضعیت ممبرشیپ تغییر کرد.');
    }

    if ($action === 'remove_member') {
        $mid = $_POST['membership_id'] ?? '';
        $uid = (int)($_POST['user_id'] ?? 0);
        MembershipManager::removeMember($mid, $uid);
        mutate('access', function (&$all) use ($mid, $uid) {
            foreach ($all as $c => $a) {
                if ($a['membership_id'] === $mid && (int)$a['user_id'] === $uid) $all[$c]['revoked'] = true;
            }
        });
        redirectSelf('عضو حذف و لینک دسترسی او باطل شد.');
    }

    // --- ربات ---
    if ($action === 'add_bot') {
        $token = trim($_POST['token'] ?? '');
        $mid   = $_POST['membership_id'] ?? '';
        if (!preg_match('/^\d{6,}:[A-Za-z0-9_\-]{30,}$/', $token)) {
            redirectSelf('فرمت توکن درست نیست.', 'err');
        }
        $me = tg($token, 'getMe', []);
        if (empty($me['ok'])) redirectSelf('توکن معتبر نیست: ' . ($me['description'] ?? ''), 'err');

        $username = $me['result']['username'];
        $bot = BotManager::create($token, $username, $mid ?: null);

        $hook = panelBaseUrl() . '/bot_master_membership.php?bot=' . $bot['id'];
        $r = tg($token, 'setWebhook', ['url' => $hook, 'drop_pending_updates' => 'true']);
        $note = !empty($r['ok'])
            ? ' وبهوک تنظیم شد.'
            : ' هشدار: وبهوک تنظیم نشد (' . ($r['description'] ?? '') . ').';
        redirectSelf('ربات @' . $username . ' اضافه شد.' . $note, !empty($r['ok']) ? 'ok' : 'warn');
    }

    if ($action === 'delete_bot') {
        $id  = $_POST['id'] ?? '';
        $bot = BotManager::get($id);
        if ($bot) tg($bot['token'], 'deleteWebhook', []);
        mutate('bots', function (&$all) use ($id) { unset($all[$id]); });
        mutate('memberships', function (&$all) use ($id) {
            foreach ($all as $k => $m) if (($m['bot_id'] ?? null) === $id) $all[$k]['bot_id'] = null;
        });
        redirectSelf('ربات حذف شد.');
    }

    if ($action === 'link_bot') {
        $bid = $_POST['bot_id'] ?? '';
        $mid = $_POST['membership_id'] ?? '';
        mutate('bots', function (&$all) use ($bid, $mid) {
            if (isset($all[$bid])) $all[$bid]['membership_id'] = $mid ?: null;
        });
        mutate('memberships', function (&$all) use ($bid, $mid) {
            foreach ($all as $k => $m) if (($m['bot_id'] ?? null) === $bid) $all[$k]['bot_id'] = null;
            if ($mid && isset($all[$mid])) $all[$mid]['bot_id'] = $bid;
        });
        redirectSelf('اتصال ربات به‌روزرسانی شد.');
    }

    if ($action === 'set_webhook') {
        $id  = $_POST['id'] ?? '';
        $bot = BotManager::get($id);
        if (!$bot) redirectSelf('ربات پیدا نشد.', 'err');
        $hook = panelBaseUrl() . '/bot_master_membership.php?bot=' . $bot['id'];
        $r = tg($bot['token'], 'setWebhook', ['url' => $hook, 'drop_pending_updates' => 'true']);
        redirectSelf(!empty($r['ok']) ? 'وبهوک تنظیم شد.' : 'خطا: ' . ($r['description'] ?? ''),
                     !empty($r['ok']) ? 'ok' : 'err');
    }

    if ($action === 'set_master_webhook') {
        $hook = panelBaseUrl() . '/bot_master_membership.php';
        $r = tg(BOT_TOKEN, 'setWebhook', ['url' => $hook, 'drop_pending_updates' => 'true']);
        redirectSelf(!empty($r['ok']) ? 'وبهوک ربات مادر تنظیم شد.' : 'خطا: ' . ($r['description'] ?? ''),
                     !empty($r['ok']) ? 'ok' : 'err');
    }

    // --- پرداخت: تایید / رد (قابلیتی که قبلا اصلا وجود نداشت) ---
    if ($action === 'approve_payment') {
        $id = $_POST['id'] ?? '';
        [$ok, $res] = PaymentManager::approve($id, ADMIN_ID);
        if (!$ok) redirectSelf($res, 'err');

        $p    = $res;
        $m    = MembershipManager::get($p['membership_id']);
        $link = AccessManager::linkFor($p['membership_id'], $p['user_id']);

        $txt = "✅ <b>پرداخت شما تایید شد!</b>\n\nممبرشیپ: " . h($m['name'] ?? '');
        if ($link) {
            $txt .= "\n\n🔗 لینک دسترسی اختصاصی شما:\n" . $link;
            sendMsg(BOT_TOKEN, $p['user_id'], $txt, [[bUrl('🚀 ورود به ربات', $link)]]);
        } else {
            $txt .= "\n\n⚠️ ربات اپلودری هنوز به این ممبرشیپ وصل نشده است.";
            sendMsg(BOT_TOKEN, $p['user_id'], $txt);
        }
        redirectSelf('پرداخت تایید شد و لینک برای کاربر ارسال شد.');
    }

    if ($action === 'reject_payment') {
        $id = $_POST['id'] ?? '';
        [$ok, $res] = PaymentManager::reject($id, ADMIN_ID);
        if (!$ok) redirectSelf($res, 'err');
        sendMsg(BOT_TOKEN, $res['user_id'], "❌ پرداخت شما تایید نشد.\nشناسه: <code>" . h($id) . "</code>");
        redirectSelf('پرداخت رد شد و به کاربر اطلاع داده شد.');
    }

    // --- تنظیمات ---
    if ($action === 'save_settings') {
        $keys = ['wallet_usdt', 'wallet_trx', 'card_number', 'support', 'welcome_text', 'domain'];
        $post = $_POST;
        mutate('settings', function (&$s) use ($keys, $post) {
            foreach ($keys as $k) if (isset($post[$k])) $s[$k] = trim($post[$k]);
        });
        redirectSelf('تنظیمات ذخیره شد.');
    }

    // --- کاربران ---
    if ($action === 'ban_user') {
        $uid = (string)(int)($_POST['user_id'] ?? 0);
        mutate('users', function (&$all) use ($uid) {
            if (isset($all[$uid])) $all[$uid]['banned'] = empty($all[$uid]['banned']);
        });
        redirectSelf('وضعیت کاربر تغییر کرد.');
    }

    if ($action === 'broadcast') {
        $text = trim($_POST['text'] ?? '');
        if ($text === '') redirectSelf('متن خالی است.', 'err');
        $users = load('users');
        $sent = 0; $fail = 0;
        foreach ($users as $u) {
            if (!empty($u['banned'])) continue;
            $r = sendMsg(BOT_TOKEN, $u['telegram_id'], $text);
            if (!empty($r['ok'])) $sent++; else $fail++;
            usleep(50000);
        }
        redirectSelf("ارسال شد — موفق: {$sent} | ناموفق: {$fail}");
    }

    redirectSelf();
}

// ------------------------------------------------------------
// 📊 داده‌های نمایش
// ------------------------------------------------------------

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$memberships = MembershipManager::all();
$bots        = BotManager::all();
$payments    = PaymentManager::all();
$users       = load('users');
$settings    = shopSettings();

uasort($payments, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

$pendingPays  = array_filter($payments, fn($p) => $p['status'] === PaymentManager::REVIEW);
$approvedPays = array_filter($payments, fn($p) => $p['status'] === PaymentManager::APPROVED);

$revenue = [];
foreach ($approvedPays as $p) {
    $c = $p['currency'];
    $revenue[$c] = ($revenue[$c] ?? 0) + (float)$p['amount'];
}

$totalMembers = 0;
foreach ($memberships as $m) $totalMembers += count($m['members']);

$tab = $_GET['tab'] ?? 'dashboard';

function statusBadge($status) {
    $map = [
        'pending'  => ['⏳ منتظر رسید', 'gray'],
        'review'   => ['🧾 در انتظار تایید', 'amber'],
        'approved' => ['✅ تایید شده', 'green'],
        'rejected' => ['❌ رد شده', 'red'],
    ];
    [$label, $color] = $map[$status] ?? ['—', 'gray'];
    return '<span class="badge ' . $color . '">' . $label . '</span>';
}

function userLabel($users, $id) {
    $u = $users[(string)$id] ?? null;
    if ($u && !empty($u['username']))   return '@' . $u['username'];
    if ($u && !empty($u['first_name'])) return $u['first_name'];
    return (string)$id;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل مدیریت ممبرشیپ</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,'Segoe UI',Tahoma,sans-serif;background:#f0f2f8;color:#2d3748;padding-bottom:60px}
a{color:inherit;text-decoration:none}
header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:26px 20px}
.wrap{max-width:1180px;margin:0 auto;padding:0 16px}
header h1{font-size:24px;display:flex;align-items:center;gap:10px}
header .row{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.logout{background:rgba(255,255,255,.2);padding:8px 16px;border-radius:10px;font-size:14px}
.logout:hover{background:rgba(255,255,255,.32)}

nav{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.06);position:sticky;top:0;z-index:10;overflow-x:auto}
nav .wrap{display:flex;gap:4px}
nav a{padding:15px 18px;font-size:14px;font-weight:600;color:#718096;border-bottom:3px solid transparent;white-space:nowrap}
nav a.on{color:#667eea;border-bottom-color:#667eea}
nav a:hover{color:#5a67d8}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin:24px 0}
.stat{background:#fff;padding:22px;border-radius:16px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.stat .n{font-size:30px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);
-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.stat .l{color:#718096;font-size:13px;margin-top:6px}

.card{background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(0,0,0,.06);margin-bottom:22px;overflow:hidden}
.card h2{padding:18px 22px;font-size:16px;border-bottom:1px solid #edf2f7}
.card .body{padding:22px}

.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
label{display:block;font-size:13px;font-weight:600;color:#4a5568;margin-bottom:6px}
input,select,textarea{width:100%;padding:11px 13px;border:2px solid #e2e8f0;border-radius:10px;
font-size:14px;font-family:inherit;background:#fff}
input:focus,select:focus,textarea:focus{outline:none;border-color:#667eea}
textarea{min-height:90px;resize:vertical}

.btn{display:inline-block;padding:11px 20px;border:0;border-radius:10px;font-size:14px;font-weight:700;
cursor:pointer;font-family:inherit;color:#fff;background:#667eea}
.btn:hover{opacity:.9}
.btn.g{background:#38a169}.btn.r{background:#e53e3e}.btn.b{background:#3182ce}
.btn.sm{padding:7px 13px;font-size:12.5px}
.btn.ghost{background:#edf2f7;color:#4a5568}

table{width:100%;border-collapse:collapse;font-size:13.5px}
th{background:#f7fafc;padding:12px;text-align:right;font-weight:700;color:#4a5568;white-space:nowrap}
td{padding:12px;border-top:1px solid #edf2f7;vertical-align:middle}
tr:hover td{background:#fafbfe}
.scroll{overflow-x:auto}

.badge{display:inline-block;padding:4px 11px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap}
.badge.green{background:#c6f6d5;color:#22543d}
.badge.amber{background:#feebc8;color:#7b341e}
.badge.red{background:#fed7d7;color:#742a2a}
.badge.gray{background:#e2e8f0;color:#4a5568}

.flash{padding:14px 18px;border-radius:12px;margin:20px 0;font-size:14px;font-weight:600}
.flash.ok{background:#c6f6d5;color:#22543d}
.flash.err{background:#fed7d7;color:#742a2a}
.flash.warn{background:#feebc8;color:#7b341e}

.empty{text-align:center;padding:36px;color:#a0aec0;font-size:14px}
code{background:#edf2f7;padding:2px 7px;border-radius:5px;font-size:12px;direction:ltr;display:inline-block}
.muted{color:#718096;font-size:12px}
.inline{display:inline}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:6px}
.chip{background:#edf2f7;padding:4px 10px;border-radius:8px;font-size:12px;display:inline-flex;align-items:center;gap:6px}
.chip button{background:none;border:0;color:#e53e3e;cursor:pointer;font-size:13px;padding:0;line-height:1}
@media(max-width:640px){.card .body{padding:16px}header h1{font-size:19px}}
</style>
</head>
<body>

<header>
  <div class="wrap row">
    <h1>👑 پنل مدیریت ممبرشیپ</h1>
    <a class="logout" href="?logout=1">🚪 خروج</a>
  </div>
</header>

<nav>
  <div class="wrap">
    <?php
    $tabs = [
      'dashboard'   => '📊 داشبورد',
      'payments'    => '🧾 پرداخت‌ها' . (count($pendingPays) ? ' (' . count($pendingPays) . ')' : ''),
      'memberships' => '🛍️ ممبرشیپ‌ها',
      'bots'        => '🤖 ربات‌ها',
      'users'       => '👥 کاربران',
      'settings'    => '⚙️ تنظیمات',
    ];
    foreach ($tabs as $k => $label): ?>
      <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</nav>

<div class="wrap">

<?php if ($flash): ?>
  <div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($tab === 'dashboard'): ?>

  <div class="stats">
    <div class="stat"><div class="n"><?= count($users) ?></div><div class="l">👥 کاربران</div></div>
    <div class="stat"><div class="n"><?= count($memberships) ?></div><div class="l">🛍️ ممبرشیپ‌ها</div></div>
    <div class="stat"><div class="n"><?= $totalMembers ?></div><div class="l">⭐ اعضای فعال</div></div>
    <div class="stat"><div class="n"><?= count($bots) ?></div><div class="l">🤖 ربات‌ها</div></div>
    <div class="stat"><div class="n"><?= count($pendingPays) ?></div><div class="l">⏳ منتظر تایید</div></div>
    <div class="stat"><div class="n"><?= count($approvedPays) ?></div><div class="l">✅ فروش موفق</div></div>
  </div>

  <div class="card">
    <h2>💰 درآمد تایید شده</h2>
    <div class="body">
      <?php if (!$revenue): ?>
        <div class="empty">هنوز فروشی ثبت نشده.</div>
      <?php else: ?>
        <div class="stats" style="margin:0">
        <?php foreach ($revenue as $cur => $amt): ?>
          <div class="stat">
            <div class="n"><?= h(rtrim(rtrim(number_format($amt, 2, '.', ','), '0'), '.')) ?></div>
            <div class="l"><?= h($cur) ?></div>
          </div>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>🔗 وبهوک ربات مادر</h2>
    <div class="body">
      <p class="muted" style="margin-bottom:12px">
        آدرس: <code><?= h(panelBaseUrl()) ?>/bot_master_membership.php</code>
      </p>
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="dashboard">
        <input type="hidden" name="action" value="set_master_webhook">
        <button class="btn b">تنظیم وبهوک ربات مادر</button>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'payments'): ?>

  <div class="card">
    <h2>🧾 در انتظار تایید (<?= count($pendingPays) ?>)</h2>
    <div class="body">
      <?php if (!$pendingPays): ?>
        <div class="empty">موردی در انتظار نیست.</div>
      <?php else: ?>
      <div class="scroll"><table>
        <tr><th>کاربر</th><th>ممبرشیپ</th><th>مبلغ</th><th>رسید</th><th>تاریخ</th><th>اقدام</th></tr>
        <?php foreach ($pendingPays as $p):
          $m = MembershipManager::get($p['membership_id']); ?>
        <tr>
          <td><?= h(userLabel($users, $p['user_id'])) ?><br><span class="muted"><?= h($p['user_id']) ?></span></td>
          <td><?= h($m['name'] ?? '—') ?></td>
          <td><b><?= h($p['amount']) ?></b> <?= h($p['currency']) ?></td>
          <td>
            <?php if ($p['receipt_type'] === 'text'): ?>
              <code><?= h(mb_substr((string)$p['receipt'], 0, 34)) ?></code>
            <?php else: ?>
              <span class="muted">🖼️ عکس (در تلگرام)</span>
            <?php endif; ?>
          </td>
          <td class="muted"><?= h($p['created_at']) ?></td>
          <td style="white-space:nowrap">
            <form method="post" class="inline" onsubmit="return confirm('تایید پرداخت و فعال‌سازی عضویت؟')">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="payments">
              <input type="hidden" name="action" value="approve_payment">
              <input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button class="btn g sm">✅ تایید</button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('رد این پرداخت؟')">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="payments">
              <input type="hidden" name="action" value="reject_payment">
              <input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button class="btn r sm">❌ رد</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <h2>📜 تاریخچه پرداخت‌ها</h2>
    <div class="body">
      <?php if (!$payments): ?>
        <div class="empty">پرداختی ثبت نشده.</div>
      <?php else: ?>
      <div class="scroll"><table>
        <tr><th>شناسه</th><th>کاربر</th><th>ممبرشیپ</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
        <?php foreach (array_slice($payments, 0, 100, true) as $p):
          $m = MembershipManager::get($p['membership_id']); ?>
        <tr>
          <td><code><?= h($p['id']) ?></code></td>
          <td><?= h(userLabel($users, $p['user_id'])) ?></td>
          <td><?= h($m['name'] ?? '—') ?></td>
          <td><?= h($p['amount']) ?> <?= h($p['currency']) ?></td>
          <td><?= statusBadge($p['status']) ?></td>
          <td class="muted"><?= h($p['created_at']) ?></td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'memberships'): ?>

  <div class="card">
    <h2>➕ افزودن ممبرشیپ</h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="memberships">
        <input type="hidden" name="action" value="add_membership">
        <div class="grid2">
          <div><label>نام ممبرشیپ</label><input name="name" required placeholder="مثلا: پریمیوم VIP"></div>
          <div><label>قیمت</label><input name="price" required placeholder="50"></div>
          <div><label>واحد پول</label>
            <select name="currency"><option>USDT</option><option>TRX</option><option>تومان</option></select>
          </div>
          <div><label>محدودیت اعضا (۰ = نامحدود)</label><input name="limit" type="number" min="0" value="0"></div>
        </div>
        <div style="margin-top:14px">
          <label>توضیح کوتاه (اختیاری)</label><input name="desc" placeholder="دسترسی کامل به آرشیو">
        </div>
        <div style="margin-top:16px"><button class="btn g">افزودن ممبرشیپ</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>🛍️ ممبرشیپ‌ها (<?= count($memberships) ?>)</h2>
    <div class="body">
      <?php if (!$memberships): ?>
        <div class="empty">هنوز ممبرشیپی نساخته‌اید.</div>
      <?php else: ?>
      <div class="scroll"><table>
        <tr><th>نام</th><th>قیمت</th><th>اعضا</th><th>ربات</th><th>وضعیت</th><th>اقدام</th></tr>
        <?php foreach ($memberships as $m):
          $bot = BotManager::botFor($m['id']);
          $cnt = count($m['members']);
          $cap = ((int)$m['limit']) > 0 ? $cnt . ' / ' . $m['limit'] : $cnt . ' / ∞'; ?>
        <tr>
          <td><b><?= h($m['name']) ?></b>
            <?php if (!empty($m['desc'])): ?><br><span class="muted"><?= h($m['desc']) ?></span><?php endif; ?>
          </td>
          <td><?= h($m['price']) ?> <?= h($m['currency']) ?></td>
          <td>
            <?= h($cap) ?>
            <?php if ($cnt): ?>
              <div class="chips">
              <?php foreach (array_slice($m['members'], 0, 12) as $memberId): ?>
                <span class="chip"><?= h(userLabel($users, $memberId)) ?>
                  <form method="post" class="inline" onsubmit="return confirm('حذف این عضو و باطل کردن لینکش؟')">
                    <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                    <input type="hidden" name="tab" value="memberships">
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="membership_id" value="<?= h($m['id']) ?>">
                    <input type="hidden" name="user_id" value="<?= h($memberId) ?>">
                    <button title="حذف عضو">✕</button>
                  </form>
                </span>
              <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= $bot ? '@' . h($bot['username']) : '<span class="muted">وصل نشده</span>' ?></td>
          <td><?= !empty($m['active'])
                ? '<span class="badge green">فعال</span>'
                : '<span class="badge gray">غیرفعال</span>' ?></td>
          <td style="white-space:nowrap">
            <form method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="memberships">
              <input type="hidden" name="action" value="toggle_membership">
              <input type="hidden" name="id" value="<?= h($m['id']) ?>">
              <button class="btn ghost sm"><?= !empty($m['active']) ? 'غیرفعال کن' : 'فعال کن' ?></button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('حذف ممبرشیپ؟ اعضای آن دسترسی را از دست می‌دهند.')">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="memberships">
              <input type="hidden" name="action" value="delete_membership">
              <input type="hidden" name="id" value="<?= h($m['id']) ?>">
              <button class="btn r sm">حذف</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'bots'): ?>

  <div class="card">
    <h2>➕ افزودن ربات اپلودری</h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="bots">
        <input type="hidden" name="action" value="add_bot">
        <div class="grid2">
          <div><label>توکن ربات (از @BotFather)</label>
            <input name="token" required placeholder="123456:ABC-DEF..." style="direction:ltr"></div>
          <div><label>اتصال به ممبرشیپ</label>
            <select name="membership_id">
              <option value="">— بعدا وصل می‌کنم —</option>
              <?php foreach ($memberships as $m): ?>
                <option value="<?= h($m['id']) ?>"><?= h($m['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <p class="muted" style="margin-top:10px">نام کاربری ربات خودکار خوانده می‌شود و وبهوک خودکار تنظیم می‌گردد.</p>
        <div style="margin-top:16px"><button class="btn g">افزودن ربات</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>🤖 ربات‌ها (<?= count($bots) ?>)</h2>
    <div class="body">
      <?php if (!$bots): ?>
        <div class="empty">رباتی ثبت نشده.</div>
      <?php else: ?>
      <div class="scroll"><table>
        <tr><th>ربات</th><th>ممبرشیپ</th><th>فایل‌ها</th><th>اقدام</th></tr>
        <?php foreach ($bots as $b): ?>
        <tr>
          <td><b>@<?= h($b['username']) ?></b><br><span class="muted"><?= h($b['created_at']) ?></span></td>
          <td>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="bots">
              <input type="hidden" name="action" value="link_bot">
              <input type="hidden" name="bot_id" value="<?= h($b['id']) ?>">
              <select name="membership_id" onchange="this.form.submit()">
                <option value="">— بدون اتصال —</option>
                <?php foreach ($memberships as $m): ?>
                  <option value="<?= h($m['id']) ?>" <?= ($b['membership_id'] ?? '') === $m['id'] ? 'selected' : '' ?>>
                    <?= h($m['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?= count(FileManager::listFiles($b['id'])) ?></td>
          <td style="white-space:nowrap">
            <form method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="bots">
              <input type="hidden" name="action" value="set_webhook">
              <input type="hidden" name="id" value="<?= h($b['id']) ?>">
              <button class="btn b sm">تنظیم وبهوک</button>
            </form>
            <form method="post" class="inline" onsubmit="return confirm('حذف این ربات؟')">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="bots">
              <input type="hidden" name="action" value="delete_bot">
              <input type="hidden" name="id" value="<?= h($b['id']) ?>">
              <button class="btn r sm">حذف</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($tab === 'users'): ?>

  <div class="card">
    <h2>📢 پیام همگانی</h2>
    <div class="body">
      <form method="post" onsubmit="return confirm('ارسال به همه کاربران؟')">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="users">
        <input type="hidden" name="action" value="broadcast">
        <textarea name="text" placeholder="متن پیام… (تگ‌های HTML تلگرام مجاز است)" required></textarea>
        <div style="margin-top:12px"><button class="btn b">ارسال به <?= count($users) ?> کاربر</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>👥 کاربران (<?= count($users) ?>)</h2>
    <div class="body">
      <?php if (!$users): ?>
        <div class="empty">هنوز کاربری ربات را استارت نکرده.</div>
      <?php else: ?>
      <div class="scroll"><table>
        <tr><th>کاربر</th><th>آیدی</th><th>عضویت‌ها</th><th>عضو از</th><th>وضعیت</th><th>اقدام</th></tr>
        <?php foreach (array_slice($users, 0, 200, true) as $u):
          $subs = [];
          foreach ($memberships as $m) {
              if (MembershipManager::isMember($m['id'], $u['telegram_id'])) $subs[] = $m['name'];
          } ?>
        <tr>
          <td><?= h(!empty($u['username']) ? '@' . $u['username'] : ($u['first_name'] ?? '—')) ?></td>
          <td><code><?= h($u['telegram_id']) ?></code></td>
          <td><?= $subs ? h(implode('، ', $subs)) : '<span class="muted">—</span>' ?></td>
          <td class="muted"><?= h($u['joined_at'] ?? '—') ?></td>
          <td><?= !empty($u['banned'])
                ? '<span class="badge red">مسدود</span>'
                : '<span class="badge green">فعال</span>' ?></td>
          <td>
            <form method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
              <input type="hidden" name="tab" value="users">
              <input type="hidden" name="action" value="ban_user">
              <input type="hidden" name="user_id" value="<?= h($u['telegram_id']) ?>">
              <button class="btn <?= !empty($u['banned']) ? 'g' : 'r' ?> sm">
                <?= !empty($u['banned']) ? 'رفع مسدودی' : 'مسدود کن' ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </table></div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <div class="card">
    <h2>⚙️ تنظیمات فروشگاه</h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
        <input type="hidden" name="tab" value="settings">
        <input type="hidden" name="action" value="save_settings">
        <div class="grid2">
          <div><label>آدرس کیف پول USDT (TRC20)</label>
            <input name="wallet_usdt" value="<?= h($settings['wallet_usdt']) ?>" style="direction:ltr"></div>
          <div><label>آدرس کیف پول TRX</label>
            <input name="wallet_trx" value="<?= h($settings['wallet_trx']) ?>" style="direction:ltr"></div>
          <div><label>شماره کارت (تومان)</label>
            <input name="card_number" value="<?= h($settings['card_number']) ?>" style="direction:ltr"></div>
          <div><label>یوزرنیم پشتیبانی (بدون @)</label>
            <input name="support" value="<?= h($settings['support']) ?>" style="direction:ltr"></div>
          <div><label>دامنه (برای وبهوک — اختیاری)</label>
            <input name="domain" value="<?= h($settings['domain']) ?>"
                   placeholder="<?= h(panelBaseUrl()) ?>" style="direction:ltr"></div>
        </div>
        <div style="margin-top:14px">
          <label>متن خوش‌آمدگویی ربات</label>
          <textarea name="welcome_text"><?= h($settings['welcome_text']) ?></textarea>
        </div>
        <div style="margin-top:16px"><button class="btn g">ذخیره تنظیمات</button></div>
      </form>
    </div>
  </div>

  <div class="card">
    <h2>🔐 امنیت</h2>
    <div class="body">
      <p class="muted" style="line-height:2">
        • رمز پنل در خط ۱۰ فایل <code>admin_panel.php</code> تعریف شده — حتما تغییرش دهید.<br>
        • آیدی ادمین: <code><?= h(ADMIN_ID) ?></code> (در فایل <code>bot_master_membership.php</code>)<br>
        • پوشه <code>data_master/</code> شامل توکن ربات‌هاست؛ دسترسی عمومی به آن را ببندید.
      </p>
    </div>
  </div>

<?php endif; ?>

</div>
</body>
</html>

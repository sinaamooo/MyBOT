<?php
/**
 * 👑 پنل مدیریت وب — فروشگاه + ربات‌های اپلودر
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
// 🔐 ورود
// ------------------------------------------------------------

function renderLogin($error) { ?>
<!DOCTYPE html><html lang="fa" dir="rtl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود — پنل مدیریت</title><style>
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
.err{background:#fed7d7;color:#c53030;padding:10px;border-radius:10px;font-size:13px;margin-bottom:14px}
</style></head><body>
<form class="card" method="post">
  <div style="font-size:44px">👑</div><h1>پنل مدیریت</h1><p class="sub">فروشگاه تلگرام</p>
  <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
  <input type="password" name="password" placeholder="رمز عبور" autofocus required>
  <button type="submit">ورود</button>
</form></body></html>
<?php }

if (isset($_GET['logout'])) {
    $_SESSION = []; session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
}

if (empty($_SESSION['logged_in'])) {
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (hash_equals(ADMIN_PASSWORD, (string)$_POST['password'])) {
            session_regenerate_id(true);
            $_SESSION['logged_in'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?')); exit;
        }
        usleep(400000);
        $err = 'رمز عبور اشتباه است.';
    }
    renderLogin($err); exit;
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('درخواست نامعتبر (CSRF).');
    }
}

function go($flash = null, $type = 'ok') {
    if ($flash !== null) $_SESSION['flash'] = ['msg' => $flash, 'type' => $type];
    $tab = $_POST['tab'] ?? $_GET['tab'] ?? 'dashboard';
    $extra = !empty($_POST['bot']) ? '&bot=' . urlencode($_POST['bot']) : '';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?tab=' . urlencode($tab) . $extra);
    exit;
}

function baseUrl() {
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
    $a = $_POST['action'] ?? '';

    // ---- دکمه‌ها ----
    if ($a === 'save_buttons') {
        $ids = array_keys(cfg()['buttons']);
        $post = $_POST;
        cfgSet(function (&$c) use ($ids, $post) {
            $c['ui']['mode'] = ($post['mode'] ?? 'menu') === 'glass' ? 'glass' : 'menu';
            $c['ui']['show_color_in_menu'] = !empty($post['show_color']);
            foreach ($ids as $id) {
                if (!isset($c['buttons'][$id])) continue;
                $c['buttons'][$id]['emoji'] = trim($post["emoji_$id"] ?? '');
                $c['buttons'][$id]['text']  = trim($post["text_$id"] ?? $c['buttons'][$id]['text']);
                $c['buttons'][$id]['color'] = $post["color_$id"] ?? 'none';
                $c['buttons'][$id]['row']   = max(1, (int)($post["row_$id"] ?? 1));
                $c['buttons'][$id]['on']    = !empty($post["on_$id"]);
            }
        });
        go('دکمه‌ها ذخیره شد.');
    }

    // ---- متن‌ها ----
    if ($a === 'save_texts') {
        $keys = array_keys(defaultConfig()['texts']);
        $post = $_POST;
        cfgSet(function (&$c) use ($keys, $post) {
            foreach ($keys as $k) if (isset($post['t_' . $k])) $c['texts'][$k] = $post['t_' . $k];
        });
        go('متن‌ها ذخیره شد.');
    }

    // ---- پشتیبانی ----
    if ($a === 'save_support') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $n = count($c['support_methods']);
            for ($i = 0; $i < $n; $i++) {
                $c['support_methods'][$i]['on']    = !empty($post["s_on_$i"]);
                $c['support_methods'][$i]['kind']  = ($post["s_kind_$i"] ?? 'direct') === 'indirect' ? 'indirect' : 'direct';
                $c['support_methods'][$i]['type']  = $post["s_type_$i"] ?? 'url';
                $c['support_methods'][$i]['emoji'] = trim($post["s_emoji_$i"] ?? '');
                $c['support_methods'][$i]['label'] = trim($post["s_label_$i"] ?? '');
                $c['support_methods'][$i]['value'] = trim($post["s_value_$i"] ?? '');
            }
        });
        go('روش‌های پشتیبانی ذخیره شد.');
    }

    // ---- تنظیمات عمومی ----
    if ($a === 'save_settings') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['wallets']['usdt']      = trim($post['usdt'] ?? '');
            $c['wallets']['trx']       = trim($post['trx'] ?? '');
            $c['wallets']['card']      = trim($post['card'] ?? '');
            $c['wallets']['card_name'] = trim($post['card_name'] ?? '');
            $c['referral']['on']       = !empty($post['ref_on']);
            $c['referral']['percent']  = max(0, min(100, (float)($post['ref_percent'] ?? 0)));
            $c['uploader']['delete_seconds']  = max(5, (int)($post['del_sec'] ?? 30));
            $c['uploader']['force_join']      = !empty($post['force_join']);
            $c['uploader']['protect_content'] = !empty($post['protect']);
        });
        go('تنظیمات ذخیره شد.');
    }

    // ---- محصولات ----
    if ($a === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $price = str_replace(',', '', trim($_POST['price'] ?? ''));
        if ($name === '' || !is_numeric($price)) go('نام و قیمت معتبر لازم است.', 'err');
        $p = Product::create($name, (float)$price, trim($_POST['currency'] ?? 'تومان'),
                             (int)($_POST['limit'] ?? 0), trim($_POST['desc'] ?? ''),
                             $_POST['bot_id'] ?: null);
        if (!empty($_POST['link_code'])) {
            $lc = trim($_POST['link_code']);
            mutate('products', function (&$all) use ($p, $lc) { $all[$p['id']]['link_code'] = $lc; });
        }
        go('محصول «' . $name . '» ساخته شد.');
    }
    if ($a === 'del_product') {
        $id = $_POST['id'] ?? '';
        mutate('products', function (&$all) use ($id) { unset($all[$id]); });
        go('محصول حذف شد.');
    }
    if ($a === 'toggle_product') {
        $id = $_POST['id'] ?? '';
        mutate('products', function (&$all) use ($id) {
            if (isset($all[$id])) $all[$id]['active'] = empty($all[$id]['active']);
        });
        go('وضعیت محصول تغییر کرد.');
    }
    if ($a === 'link_product') {
        $id = $_POST['id'] ?? '';
        $bid = $_POST['bot_id'] ?? '';
        $code = trim($_POST['link_code'] ?? '');
        mutate('products', function (&$all) use ($id, $bid, $code) {
            if (!isset($all[$id])) return;
            $all[$id]['bot_id'] = $bid ?: null;
            $all[$id]['link_code'] = $code;
        });
        go('محتوای محصول تنظیم شد.');
    }

    // ---- کانال‌های اجباری ----
    if ($a === 'add_channel') {
        $chat = trim($_POST['chat_id'] ?? '');
        if ($chat === '') go('آیدی کانال لازم است.', 'err');
        $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat]);
        if (empty($r['ok'])) go('کانال پیدا نشد: ' . ($r['description'] ?? ''), 'err');
        $title = $r['result']['title'] ?? $chat;
        $un = $r['result']['username'] ?? '';
        $url = trim($_POST['url'] ?? '') ?: ($un ? "https://t.me/$un" : ($r['result']['invite_link'] ?? ''));
        Channels::add($chat, $title, $url);
        go('کانال «' . $title . '» اضافه شد. ربات‌های اپلودر را در آن ادمین کنید.');
    }
    if ($a === 'del_channel') { Channels::remove($_POST['id'] ?? ''); go('کانال حذف شد.'); }
    if ($a === 'health') {
        $lines = [];
        $mh = Channels::health(BOT_TOKEN);
        foreach ($mh as $r) $lines[] = ($r['ok'] ? '✅' : '❌') . ' ربات مادر → ' . $r['title'] . ($r['ok'] ? '' : ' (' . $r['error'] . ')');
        foreach (BotManager::all() as $b) {
            foreach (Channels::health($b['token']) as $r) {
                $lines[] = ($r['ok'] ? '✅' : '❌') . ' @' . $b['username'] . ' → ' . $r['title'] . ($r['ok'] ? '' : ' (' . $r['error'] . ')');
            }
        }
        $_SESSION['health'] = $lines ?: ['کانالی برای بررسی نیست.'];
        go('بررسی انجام شد.');
    }
    if ($a === 'toggle_channel') {
        $id = $_POST['id'] ?? '';
        mutate('channels', function (&$c) use ($id) {
            if (isset($c[$id])) $c[$id]['on'] = empty($c[$id]['on']);
        });
        go('وضعیت کانال تغییر کرد.');
    }

    // ---- ربات‌ها ----
    if ($a === 'add_bot') {
        $token = trim($_POST['token'] ?? '');
        if (!preg_match('/^\d{6,}:[A-Za-z0-9_\-]{30,}$/', $token)) go('فرمت توکن درست نیست.', 'err');
        $me = tg($token, 'getMe', []);
        if (empty($me['ok'])) go('توکن معتبر نیست: ' . ($me['description'] ?? ''), 'err');
        $bot = BotManager::create($token, $me['result']['username']);
        $hook = baseUrl() . '/bot_master_membership.php?bot=' . $bot['id'];
        $r = tg($token, 'setWebhook', ['url' => $hook, 'drop_pending_updates' => 'true']);
        go('ربات @' . $bot['username'] . ' اضافه شد.' .
           (!empty($r['ok']) ? ' وبهوک تنظیم شد.' : ' هشدار: وبهوک تنظیم نشد.'),
           !empty($r['ok']) ? 'ok' : 'warn');
    }
    if ($a === 'del_bot') {
        $id = $_POST['id'] ?? '';
        $b = BotManager::get($id);
        if ($b) tg($b['token'], 'deleteWebhook', []);
        mutate('bots', function (&$all) use ($id) { unset($all[$id]); });
        go('ربات حذف شد.');
    }
    if ($a === 'bot_webhook') {
        $b = BotManager::get($_POST['id'] ?? '');
        if (!$b) go('ربات پیدا نشد.', 'err');
        $r = tg($b['token'], 'setWebhook',
            ['url' => baseUrl() . '/bot_master_membership.php?bot=' . $b['id'], 'drop_pending_updates' => 'true']);
        go(!empty($r['ok']) ? 'وبهوک تنظیم شد.' : 'خطا: ' . ($r['description'] ?? ''), !empty($r['ok']) ? 'ok' : 'err');
    }
    if ($a === 'master_webhook') {
        $r = tg(BOT_TOKEN, 'setWebhook',
            ['url' => baseUrl() . '/bot_master_membership.php', 'drop_pending_updates' => 'true']);
        go(!empty($r['ok']) ? 'وبهوک ربات مادر تنظیم شد.' : 'خطا: ' . ($r['description'] ?? ''), !empty($r['ok']) ? 'ok' : 'err');
    }
    if ($a === 'save_bot') {
        $id = $_POST['id'] ?? '';
        BotManager::setSetting($id, 'delete_seconds', max(5, (int)($_POST['del_sec'] ?? 30)));
        BotManager::setSetting($id, 'force_join', !empty($_POST['force_join']));
        BotManager::setSetting($id, 'protect_content', !empty($_POST['protect']));
        foreach (['start_text', 'join_text', 'joined_btn', 'warn_text', 'deleted_text', 'expired_text'] as $k) {
            if (isset($_POST[$k])) BotManager::setSetting($id, $k, $_POST[$k]);
        }
        go('تنظیمات ربات ذخیره شد.');
    }
    if ($a === 'del_link') {
        Links::remove($_POST['bot'] ?? '', $_POST['code'] ?? '');
        go('لینک حذف شد.');
    }

    // ---- سفارش‌ها ----
    if ($a === 'approve_order') {
        [$ok, $res] = Order::approve($_POST['id'] ?? '', ADMIN_ID);
        if (!$ok) go($res, 'err');
        $o = $res;
        if ($o['type'] === 'topup') {
            sendMsg(BOT_TOKEN, $o['user_id'],
                "✅ کیف پول شما <b>" . fmtNum($o['amount']) . "</b> تومان شارژ شد.\n💰 موجودی جدید: <b>" .
                fmtNum(getUser($o['user_id'])['balance'] ?? 0) . "</b> تومان");
        } else {
            sendMsg(BOT_TOKEN, $o['user_id'], T('approved'));
            deliverProduct($o['user_id'], $o['user_id'], $o['product_id']);
        }
        go('سفارش تایید شد و به کاربر اطلاع داده شد.');
    }
    if ($a === 'reject_order') {
        [$ok, $res] = Order::reject($_POST['id'] ?? '', ADMIN_ID);
        if (!$ok) go($res, 'err');
        sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
        go('سفارش رد شد.');
    }

    // ---- کاربران ----
    if ($a === 'ban_user') {
        $uid = (string)(int)($_POST['user_id'] ?? 0);
        mutate('users', function (&$all) use ($uid) {
            if (isset($all[$uid])) $all[$uid]['banned'] = empty($all[$uid]['banned']);
        });
        go('وضعیت کاربر تغییر کرد.');
    }
    if ($a === 'set_balance') {
        $uid = (string)(int)($_POST['user_id'] ?? 0);
        $val = (float)str_replace(',', '', $_POST['balance'] ?? '0');
        mutate('users', function (&$all) use ($uid, $val) {
            if (isset($all[$uid])) $all[$uid]['balance'] = $val;
        });
        go('موجودی به‌روزرسانی شد.');
    }
    if ($a === 'broadcast') {
        $text = trim($_POST['text'] ?? '');
        if ($text === '') go('متن خالی است.', 'err');
        $sent = 0; $fail = 0;
        foreach (load('users') as $u) {
            if (!empty($u['banned'])) continue;
            $r = sendMsg(BOT_TOKEN, $u['telegram_id'], $text);
            if (!empty($r['ok'])) $sent++; else $fail++;
            usleep(50000);
        }
        go("ارسال شد — موفق: {$sent} | ناموفق: {$fail}");
    }

    go();
}

// ------------------------------------------------------------
// 📊 داده‌ها
// ------------------------------------------------------------

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$C        = cfg();
$products = Product::all();
$bots     = BotManager::all();
$orders   = Order::all();
$users    = load('users');
$channels = Channels::all();

uasort($orders, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$pending  = array_filter($orders, fn($o) => $o['status'] === Order::REVIEW);
$approved = array_filter($orders, fn($o) => $o['status'] === Order::APPROVED);

$revenue = [];
foreach ($approved as $o) {
    if ($o['type'] !== 'product') continue;
    $revenue[$o['currency']] = ($revenue[$o['currency']] ?? 0) + (float)$o['amount'];
}
$totalBalance = 0;
foreach ($users as $u) $totalBalance += (float)($u['balance'] ?? 0);

$tab    = $_GET['tab'] ?? 'dashboard';
$curBot = $_GET['bot'] ?? '';

function uLabel($users, $id) {
    $u = $users[(string)$id] ?? null;
    if ($u && !empty($u['username']))   return '@' . $u['username'];
    if ($u && !empty($u['first_name'])) return $u['first_name'];
    return (string)$id;
}
function oBadge($s) {
    $m = ['pending' => ['⏳ منتظر رسید', 'gray'], 'review' => ['🧾 بررسی', 'amber'],
          'approved' => ['✅ تایید', 'green'], 'rejected' => ['❌ رد', 'red']];
    [$l, $c] = $m[$s] ?? ['—', 'gray'];
    return '<span class="badge ' . $c . '">' . $l . '</span>';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل مدیریت فروشگاه</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:system-ui,'Segoe UI',Tahoma,sans-serif;background:#f0f2f8;color:#2d3748;padding-bottom:60px}
a{color:inherit;text-decoration:none}
header{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:24px 20px}
.wrap{max-width:1200px;margin:0 auto;padding:0 16px}
header h1{font-size:23px}
header .row{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.logout{background:rgba(255,255,255,.2);padding:8px 16px;border-radius:10px;font-size:14px}
nav{background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.06);position:sticky;top:0;z-index:10;overflow-x:auto}
nav .wrap{display:flex;gap:2px}
nav a{padding:14px 15px;font-size:13.5px;font-weight:600;color:#718096;border-bottom:3px solid transparent;white-space:nowrap}
nav a.on{color:#667eea;border-bottom-color:#667eea}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin:22px 0}
.stat{background:#fff;padding:20px;border-radius:16px;box-shadow:0 4px 16px rgba(0,0,0,.06)}
.stat .n{font-size:27px;font-weight:800;background:linear-gradient(135deg,#667eea,#764ba2);
-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
.stat .l{color:#718096;font-size:12.5px;margin-top:5px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 16px rgba(0,0,0,.06);margin-bottom:20px;overflow:hidden}
.card h2{padding:17px 20px;font-size:15.5px;border-bottom:1px solid #edf2f7}
.card .body{padding:20px}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:13px}
label{display:block;font-size:12.5px;font-weight:600;color:#4a5568;margin-bottom:5px}
input,select,textarea{width:100%;padding:10px 12px;border:2px solid #e2e8f0;border-radius:10px;
font-size:13.5px;font-family:inherit;background:#fff}
input:focus,select:focus,textarea:focus{outline:none;border-color:#667eea}
textarea{min-height:90px;resize:vertical;line-height:1.9}
.btn{display:inline-block;padding:10px 18px;border:0;border-radius:10px;font-size:13.5px;font-weight:700;
cursor:pointer;font-family:inherit;color:#fff;background:#667eea}
.btn:hover{opacity:.9}
.btn.g{background:#38a169}.btn.r{background:#e53e3e}.btn.b{background:#3182ce}
.btn.sm{padding:6px 12px;font-size:12px}
.btn.ghost{background:#edf2f7;color:#4a5568}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:#f7fafc;padding:11px;text-align:right;font-weight:700;color:#4a5568;white-space:nowrap}
td{padding:11px;border-top:1px solid #edf2f7;vertical-align:middle}
.scroll{overflow-x:auto}
.badge{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11.5px;font-weight:700;white-space:nowrap}
.badge.green{background:#c6f6d5;color:#22543d}.badge.amber{background:#feebc8;color:#7b341e}
.badge.red{background:#fed7d7;color:#742a2a}.badge.gray{background:#e2e8f0;color:#4a5568}
.flash{padding:13px 17px;border-radius:12px;margin:18px 0;font-size:13.5px;font-weight:600}
.flash.ok{background:#c6f6d5;color:#22543d}.flash.err{background:#fed7d7;color:#742a2a}
.flash.warn{background:#feebc8;color:#7b341e}
.empty{text-align:center;padding:32px;color:#a0aec0;font-size:13.5px}
code{background:#edf2f7;padding:2px 6px;border-radius:5px;font-size:11.5px;direction:ltr;display:inline-block}
.muted{color:#718096;font-size:12px}
.inline{display:inline}
.brow{display:grid;grid-template-columns:44px 1fr 90px 70px 60px 46px;gap:8px;align-items:center;
padding:10px;border:1px solid #edf2f7;border-radius:10px;margin-bottom:8px}
.brow input,.brow select{padding:8px;font-size:13px}
.prev{background:#e8f0fe;border-radius:12px;padding:14px;margin-top:12px}
.pbtn{background:#fff;border-radius:9px;padding:10px;text-align:center;font-size:13.5px;
margin:4px 0;box-shadow:0 1px 3px rgba(0,0,0,.1)}
.pgrid{display:flex;gap:6px}
.pgrid .pbtn{flex:1;margin:0}
.srow{display:grid;grid-template-columns:40px 90px 100px 60px 1fr 1.4fr;gap:8px;align-items:center;
padding:9px;border:1px solid #edf2f7;border-radius:10px;margin-bottom:8px}
.srow input,.srow select{padding:8px;font-size:12.5px}
.tgrid{display:grid;gap:14px}
@media(max-width:760px){.brow,.srow{grid-template-columns:1fr 1fr;gap:6px}}
@media(max-width:640px){.card .body{padding:15px}header h1{font-size:18px}}
</style>
</head>
<body>

<header><div class="wrap row">
  <h1>👑 پنل مدیریت فروشگاه</h1>
  <a class="logout" href="?logout=1">🚪 خروج</a>
</div></header>

<nav><div class="wrap">
<?php
$tabs = [
  'dashboard' => '📊 داشبورد',
  'orders'    => '🧾 سفارش‌ها' . (count($pending) ? ' (' . count($pending) . ')' : ''),
  'products'  => '🛒 محصولات',
  'buttons'   => '🎨 دکمه‌ها',
  'texts'     => '📝 متن‌ها',
  'support'   => '📞 پشتیبانی',
  'bots'      => '🤖 ربات‌های اپلودر',
  'channels'  => '📢 کانال‌ها',
  'users'     => '👥 کاربران',
  'settings'  => '⚙️ تنظیمات',
];
foreach ($tabs as $k => $l): ?>
  <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?></a>
<?php endforeach; ?>
</div></nav>

<div class="wrap">
<?php if ($flash): ?><div class="flash <?= h($flash['type']) ?>"><?= h($flash['msg']) ?></div><?php endif; ?>

<?php // ================= داشبورد ================= ?>
<?php if ($tab === 'dashboard'): ?>
  <div class="stats">
    <div class="stat"><div class="n"><?= count($users) ?></div><div class="l">👥 کاربران</div></div>
    <div class="stat"><div class="n"><?= count($products) ?></div><div class="l">🛒 محصولات</div></div>
    <div class="stat"><div class="n"><?= count($bots) ?></div><div class="l">🤖 ربات اپلودر</div></div>
    <div class="stat"><div class="n"><?= count($channels) ?></div><div class="l">📢 کانال اجباری</div></div>
    <div class="stat"><div class="n"><?= count($pending) ?></div><div class="l">⏳ منتظر تایید</div></div>
    <div class="stat"><div class="n"><?= count($approved) ?></div><div class="l">✅ سفارش موفق</div></div>
    <div class="stat"><div class="n"><?= fmtNum($totalBalance) ?></div><div class="l">💰 کیف پول کاربران</div></div>
  </div>

  <div class="card"><h2>💰 فروش تایید شده</h2><div class="body">
    <?php if (!$revenue): ?><div class="empty">هنوز فروشی ثبت نشده.</div>
    <?php else: ?><div class="stats" style="margin:0">
      <?php foreach ($revenue as $cur => $amt): ?>
        <div class="stat"><div class="n"><?= h(fmtNum($amt)) ?></div><div class="l"><?= h($cur) ?></div></div>
      <?php endforeach; ?></div><?php endif; ?>
  </div></div>

  <div class="card"><h2>🔗 وبهوک و کران</h2><div class="body">
    <p class="muted" style="margin-bottom:10px">وبهوک مادر: <code><?= h(baseUrl()) ?>/bot_master_membership.php</code></p>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
      <input type="hidden" name="tab" value="dashboard">
      <input type="hidden" name="action" value="master_webhook">
      <button class="btn b">تنظیم وبهوک ربات مادر</button>
    </form>
    <p class="muted" style="margin-top:14px;line-height:1.9">
      برای اینکه حذف خودکار فایل‌ها حتی بدون فعالیت ربات هم دقیق کار کند،
      این آدرس را هر دقیقه در کران هاست صدا بزنید:<br>
      <code><?= h(baseUrl()) ?>/bot_master_membership.php?cron=<?= h(CRON_KEY) ?></code><br>
      کلید کران در خط ۳۰ فایل ربات قابل تغییر است.
    </p>
  </div></div>

<?php // ================= سفارش‌ها ================= ?>
<?php elseif ($tab === 'orders'): ?>
  <div class="card"><h2>🧾 منتظر تایید (<?= count($pending) ?>)</h2><div class="body">
    <?php if (!$pending): ?><div class="empty">موردی در انتظار نیست.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>رسید</th><th>تاریخ</th><th>اقدام</th></tr>
      <?php foreach ($pending as $o): ?>
      <tr>
        <td><?= h(uLabel($users, $o['user_id'])) ?><br><span class="muted"><?= h($o['user_id']) ?></span></td>
        <td><?= $o['type'] === 'topup' ? '➕ شارژ کیف پول'
              : '🛒 ' . h(Product::get($o['product_id'])['name'] ?? '—') ?></td>
        <td><b><?= h(fmtNum($o['amount'])) ?></b> <?= h($o['currency']) ?></td>
        <td><?= $o['receipt_type'] === 'text'
              ? '<code>' . h(mb_substr((string)$o['receipt'], 0, 30)) . '</code>'
              : '<span class="muted">🖼️ عکس (در تلگرام)</span>' ?></td>
        <td class="muted"><?= h($o['created_at']) ?></td>
        <td style="white-space:nowrap">
          <form method="post" class="inline" onsubmit="return confirm('تایید سفارش؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="orders">
            <input type="hidden" name="action" value="approve_order"><input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <button class="btn g sm">✅ تایید</button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('رد سفارش؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="orders">
            <input type="hidden" name="action" value="reject_order"><input type="hidden" name="id" value="<?= h($o['id']) ?>">
            <button class="btn r sm">❌ رد</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

  <div class="card"><h2>📜 تاریخچه</h2><div class="body">
    <?php if (!$orders): ?><div class="empty">سفارشی ثبت نشده.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>شناسه</th><th>کاربر</th><th>نوع</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr>
      <?php foreach (array_slice($orders, 0, 100, true) as $o): ?>
      <tr><td><code><?= h($o['id']) ?></code></td>
        <td><?= h(uLabel($users, $o['user_id'])) ?></td>
        <td><?= $o['type'] === 'topup' ? 'شارژ' : h(Product::get($o['product_id'])['name'] ?? '—') ?></td>
        <td><?= h(fmtNum($o['amount'])) ?> <?= h($o['currency']) ?></td>
        <td><?= oBadge($o['status']) ?></td>
        <td class="muted"><?= h($o['created_at']) ?></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

<?php // ================= محصولات ================= ?>
<?php elseif ($tab === 'products'): ?>
  <div class="card"><h2>➕ افزودن محصول</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="add_product">
      <div class="grid2">
        <div><label>نام محصول</label><input name="name" required placeholder="اشتراک یک ماهه"></div>
        <div><label>قیمت</label><input name="price" required placeholder="50000"></div>
        <div><label>واحد پول</label><select name="currency">
          <option>تومان</option><option>USDT</option><option>TRX</option></select></div>
        <div><label>محدودیت خرید (۰ = نامحدود)</label><input name="limit" type="number" min="0" value="0"></div>
        <div><label>ربات اپلودر تحویل</label><select name="bot_id">
          <option value="">— بدون محتوا —</option>
          <?php foreach ($bots as $b): ?><option value="<?= h($b['id']) ?>">@<?= h($b['username']) ?></option><?php endforeach; ?>
        </select></div>
        <div><label>کد لینک محتوا</label><input name="link_code" placeholder="از تب ربات‌ها کپی کنید"></div>
      </div>
      <div style="margin-top:12px"><label>توضیح</label><input name="desc" placeholder="دسترسی کامل به آرشیو"></div>
      <div style="margin-top:14px"><button class="btn g">افزودن محصول</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🛒 محصولات (<?= count($products) ?>)</h2><div class="body">
    <?php if (!$products): ?><div class="empty">محصولی ندارید.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>نام</th><th>قیمت</th><th>خریدار</th><th>محتوا</th><th>وضعیت</th><th>اقدام</th></tr>
      <?php foreach ($products as $p):
        $cnt = count($p['buyers']);
        $cap = ((int)$p['limit']) > 0 ? "$cnt / {$p['limit']}" : "$cnt / ∞"; ?>
      <tr>
        <td><b><?= h($p['name']) ?></b><?php if (!empty($p['desc'])): ?><br><span class="muted"><?= h($p['desc']) ?></span><?php endif; ?></td>
        <td><?= h(fmtNum($p['price'])) ?> <?= h($p['currency']) ?></td>
        <td><?= h($cap) ?></td>
        <td>
          <form method="post">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
            <input type="hidden" name="action" value="link_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <select name="bot_id" style="margin-bottom:5px">
              <option value="">— بدون —</option>
              <?php foreach ($bots as $b): ?>
                <option value="<?= h($b['id']) ?>" <?= ($p['bot_id'] ?? '') === $b['id'] ? 'selected' : '' ?>>@<?= h($b['username']) ?></option>
              <?php endforeach; ?>
            </select>
            <input name="link_code" value="<?= h($p['link_code'] ?? '') ?>" placeholder="کد لینک" style="margin-bottom:5px">
            <button class="btn ghost sm">ذخیره</button>
          </form>
        </td>
        <td><?= !empty($p['active']) ? '<span class="badge green">فعال</span>' : '<span class="badge gray">غیرفعال</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
            <input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <button class="btn ghost sm"><?= !empty($p['active']) ? 'غیرفعال' : 'فعال' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('حذف محصول؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
            <input type="hidden" name="action" value="del_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <button class="btn r sm">حذف</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

<?php // ================= دکمه‌ها ================= ?>
<?php elseif ($tab === 'buttons'): ?>
  <div class="card"><h2>🎨 دکمه‌های ربات</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="buttons">
      <input type="hidden" name="action" value="save_buttons">

      <div class="grid2" style="margin-bottom:16px">
        <div><label>حالت نمایش دکمه‌ها</label>
          <select name="mode">
            <option value="menu"  <?= $C['ui']['mode'] === 'menu' ? 'selected' : '' ?>>منو — کیبورد پایین صفحه</option>
            <option value="glass" <?= $C['ui']['mode'] === 'glass' ? 'selected' : '' ?>>شیشه‌ای — زیر پیام</option>
          </select></div>
        <div><label>&nbsp;</label>
          <label style="font-weight:500"><input type="checkbox" name="show_color" style="width:auto"
            <?= !empty($C['ui']['show_color_in_menu']) ? 'checked' : '' ?>> نمایش دایره رنگی در حالت منو</label></div>
      </div>

      <p class="muted" style="margin-bottom:10px">ایموجی، متن، رنگ و ردیف هر دکمه را می‌توانید عوض کنید. دکمه‌های هم‌ردیف کنار هم می‌آیند.</p>

      <div style="display:grid;grid-template-columns:44px 1fr 100px 70px 60px 46px;gap:8px;
                  font-size:11.5px;color:#718096;font-weight:700;padding:0 10px 6px">
        <div>ایموجی</div><div>متن دکمه</div><div>رنگ</div><div>ردیف</div><div>فعال</div><div></div>
      </div>

      <?php foreach ($C['buttons'] as $id => $b): ?>
      <div class="brow">
        <input name="emoji_<?= h($id) ?>" value="<?= h($b['emoji']) ?>" style="text-align:center">
        <input name="text_<?= h($id) ?>" value="<?= h($b['text']) ?>">
        <select name="color_<?= h($id) ?>">
          <?php foreach (colorMap() as $ck => $ce): ?>
            <option value="<?= h($ck) ?>" <?= $b['color'] === $ck ? 'selected' : '' ?>><?= $ce ?: '—' ?> <?= h($ck) ?></option>
          <?php endforeach; ?>
        </select>
        <input name="row_<?= h($id) ?>" type="number" min="1" max="20" value="<?= (int)$b['row'] ?>">
        <input type="checkbox" name="on_<?= h($id) ?>" <?= !empty($b['on']) ? 'checked' : '' ?> style="width:auto">
        <span class="muted"><?= h($id) ?></span>
      </div>
      <?php endforeach; ?>

      <div style="margin-top:14px"><button class="btn g">ذخیره دکمه‌ها</button></div>
    </form>

    <div class="prev">
      <div class="muted" style="margin-bottom:8px">پیش‌نمایش (<?= $C['ui']['mode'] === 'glass' ? 'شیشه‌ای' : 'منو' ?>):</div>
      <?php
      $rows = [];
      foreach ($C['buttons'] as $b) { if (!empty($b['on'])) $rows[(int)$b['row']][] = $b; }
      ksort($rows);
      foreach ($rows as $r): ?>
        <div class="pgrid">
          <?php foreach ($r as $b): ?>
            <div class="pbtn"><?= h(btnLabel($b)) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

<?php // ================= متن‌ها ================= ?>
<?php elseif ($tab === 'texts'): ?>
  <div class="card"><h2>📝 متن‌های ربات</h2><div class="body">
    <p class="muted" style="margin-bottom:14px;line-height:1.9">
      متغیرهای قابل استفاده — حساب کاربری: <code>{id}</code> <code>{name}</code> <code>{username}</code>
      <code>{balance}</code> <code>{orders}</code> <code>{referrals}</code> <code>{ref_earned}</code> <code>{joined}</code><br>
      زیرمجموعه: <code>{percent}</code> <code>{link}</code> — خوش‌آمد: <code>{name}</code>
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="texts">
      <input type="hidden" name="action" value="save_texts">
      <div class="tgrid">
      <?php
      $labels = [
        'welcome' => '👋 پیام خوش‌آمد', 'account' => '👤 حساب کاربری', 'trust' => '💚 چرا به ما اعتماد کنید',
        'support' => '📞 سربرگ پشتیبانی', 'referral' => '👥 زیر مجموعه گیری', 'topup' => '➕ افزایش موجودی',
        'buy_head' => '🛒 سربرگ محصولات', 'buy_empty' => '🛒 وقتی محصولی نیست',
        'orders_head' => '📊 سربرگ سفارش‌ها', 'orders_empty' => '📊 وقتی سفارشی نیست',
        'pay_info' => '💳 اطلاعات پرداخت', 'receipt_ask' => '🧾 درخواست رسید',
        'receipt_ok' => '✅ رسید ثبت شد', 'approved' => '✅ تایید سفارش',
        'rejected' => '❌ رد سفارش', 'no_balance' => '❌ موجودی کافی نیست', 'banned' => '🚫 کاربر مسدود',
      ];
      foreach ($labels as $k => $l): ?>
        <div><label><?= $l ?></label>
          <textarea name="t_<?= h($k) ?>"><?= h($C['texts'][$k] ?? '') ?></textarea></div>
      <?php endforeach; ?>
      </div>
      <div style="margin-top:16px"><button class="btn g">ذخیره متن‌ها</button></div>
    </form>
  </div></div>

<?php // ================= پشتیبانی ================= ?>
<?php elseif ($tab === 'support'): ?>
  <div class="card"><h2>📞 روش‌های ارتباط (۱۰ روش)</h2><div class="body">
    <p class="muted" style="margin-bottom:12px;line-height:1.9">
      <b>نوع:</b> لینک = دکمه‌ای که کاربر را به آدرس می‌برد ·
      تیکت = کاربر داخل ربات پیام می‌نویسد و برای شما می‌آید ·
      متن = نمایش یک متن · تلفن = نمایش شماره<br>
      <b>دسته:</b> مستقیم و غیرمستقیم جدا از هم در ربات نشان داده می‌شوند.
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="support">
      <input type="hidden" name="action" value="save_support">
      <div style="display:grid;grid-template-columns:40px 90px 100px 60px 1fr 1.4fr;gap:8px;
                  font-size:11.5px;color:#718096;font-weight:700;padding:0 9px 6px">
        <div>فعال</div><div>دسته</div><div>نوع</div><div>ایموجی</div><div>عنوان</div><div>مقدار</div>
      </div>
      <?php foreach ($C['support_methods'] as $i => $m): ?>
      <div class="srow">
        <input type="checkbox" name="s_on_<?= $i ?>" <?= !empty($m['on']) ? 'checked' : '' ?> style="width:auto">
        <select name="s_kind_<?= $i ?>">
          <option value="direct"   <?= ($m['kind'] ?? '') === 'direct' ? 'selected' : '' ?>>🟢 مستقیم</option>
          <option value="indirect" <?= ($m['kind'] ?? '') === 'indirect' ? 'selected' : '' ?>>🔵 غیرمستقیم</option>
        </select>
        <select name="s_type_<?= $i ?>">
          <?php foreach (['url' => 'لینک', 'ticket' => 'تیکت', 'text' => 'متن', 'phone' => 'تلفن'] as $tk => $tl): ?>
            <option value="<?= $tk ?>" <?= ($m['type'] ?? '') === $tk ? 'selected' : '' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <input name="s_emoji_<?= $i ?>" value="<?= h($m['emoji'] ?? '') ?>" style="text-align:center">
        <input name="s_label_<?= $i ?>" value="<?= h($m['label'] ?? '') ?>">
        <input name="s_value_<?= $i ?>" value="<?= h($m['value'] ?? '') ?>" placeholder="https://t.me/... یا متن یا شماره">
      </div>
      <?php endforeach; ?>
      <div style="margin-top:14px"><button class="btn g">ذخیره پشتیبانی</button></div>
    </form>
  </div></div>

<?php // ================= ربات‌های اپلودر ================= ?>
<?php elseif ($tab === 'bots'): ?>
  <div class="card"><h2>➕ افزودن ربات اپلودر</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
      <input type="hidden" name="action" value="add_bot">
      <label>توکن ربات (از @BotFather)</label>
      <input name="token" required placeholder="123456:ABC-DEF..." style="direction:ltr">
      <p class="muted" style="margin-top:8px">وبهوک و نام کاربری خودکار تنظیم می‌شود. تنظیمات پیش‌فرض تب «تنظیمات» روی ربات جدید اعمال می‌گردد.</p>
      <div style="margin-top:12px"><button class="btn g">افزودن ربات</button></div>
    </form>
  </div></div>

  <?php foreach ($bots as $b): $s = BotManager::settings($b['id']); $links = Links::all($b['id']); ?>
  <div class="card">
    <h2>🤖 @<?= h($b['username']) ?> — <?= count($links) ?> لینک</h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
        <input type="hidden" name="action" value="save_bot"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
        <div class="grid2">
          <div><label>⏱ حذف فایل بعد از (ثانیه)</label>
            <input name="del_sec" type="number" min="5" value="<?= (int)$s['delete_seconds'] ?>"></div>
          <div><label>گزینه‌ها</label>
            <label style="font-weight:500"><input type="checkbox" name="force_join" style="width:auto"
              <?= !empty($s['force_join']) ? 'checked' : '' ?>> 🔒 عضویت اجباری کانال‌ها</label>
            <label style="font-weight:500"><input type="checkbox" name="protect" style="width:auto"
              <?= !empty($s['protect_content']) ? 'checked' : '' ?>> 🛡 جلوگیری از فوروارد/ذخیره</label></div>
        </div>
        <div class="tgrid" style="margin-top:12px">
          <div><label>پیام شروع ربات (<code>{name}</code>)</label>
            <textarea name="start_text"><?= h($s['start_text']) ?></textarea></div>
          <div><label>متن عضویت اجباری</label>
            <textarea name="join_text"><?= h($s['join_text']) ?></textarea></div>
          <div class="grid2">
            <div><label>متن دکمه «عضو شدم»</label><input name="joined_btn" value="<?= h($s['joined_btn']) ?>"></div>
            <div><label>متن لینک نامعتبر</label><input name="expired_text" value="<?= h($s['expired_text']) ?>"></div>
          </div>
          <div><label>هشدار حذف (<code>{sec}</code>)</label>
            <textarea name="warn_text"><?= h($s['warn_text']) ?></textarea></div>
          <div><label>پیام بعد از حذف</label>
            <textarea name="deleted_text"><?= h($s['deleted_text']) ?></textarea></div>
        </div>
        <div style="margin-top:14px"><button class="btn g">ذخیره تنظیمات این ربات</button></div>
      </form>

      <div style="margin-top:16px;padding-top:14px;border-top:1px solid #edf2f7">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
          <input type="hidden" name="action" value="bot_webhook"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
          <button class="btn b sm">تنظیم دوباره وبهوک</button>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('حذف ربات؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
          <input type="hidden" name="action" value="del_bot"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
          <button class="btn r sm">حذف ربات</button>
        </form>
      </div>

      <?php if ($links): ?>
      <div style="margin-top:16px"><div class="scroll"><table>
        <tr><th>عنوان</th><th>کد</th><th>لینک</th><th>👁</th><th>📥</th><th></th></tr>
        <?php foreach (array_slice(array_reverse($links, true), 0, 40, true) as $code => $l): ?>
        <tr>
          <td><?= h($l['title'] ?: count($l['files']) . ' فایل') ?></td>
          <td><code><?= h($code) ?></code></td>
          <td><code><?= h(Links::url($b['id'], $code)) ?></code></td>
          <td><?= (int)$l['clicks'] ?></td><td><?= (int)$l['delivered'] ?></td>
          <td><form method="post" onsubmit="return confirm('حذف لینک؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
            <input type="hidden" name="action" value="del_link"><input type="hidden" name="bot" value="<?= h($b['id']) ?>">
            <input type="hidden" name="code" value="<?= h($code) ?>">
            <button class="btn r sm">حذف</button></form></td>
        </tr>
        <?php endforeach; ?>
      </table></div></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <?php if (!$bots): ?><div class="card"><div class="body"><div class="empty">هنوز ربات اپلودری اضافه نکرده‌اید.</div></div></div><?php endif; ?>

<?php // ================= کانال‌ها ================= ?>
<?php elseif ($tab === 'channels'): ?>
  <div class="card"><h2>📢 افزودن کانال عضویت اجباری</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="add_channel">
      <div class="grid2">
        <div><label>آیدی یا یوزرنیم کانال</label><input name="chat_id" required placeholder="@mychannel یا -1001234567890" style="direction:ltr"></div>
        <div><label>لینک عضویت (اختیاری)</label><input name="url" placeholder="https://t.me/..." style="direction:ltr"></div>
      </div>
      <p class="muted" style="margin-top:10px;line-height:1.9">
        ⚠️ <b>ربات مادر</b> و <b>همه ربات‌های اپلودر</b> باید در این کانال <b>ادمین</b> باشند،
        وگرنه امکان بررسی عضویت وجود ندارد و کانال نادیده گرفته می‌شود.
      </p>
      <div style="margin-top:12px"><button class="btn g">افزودن کانال</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🩺 بررسی سلامت</h2><div class="body">
    <p class="muted" style="margin-bottom:10px;line-height:1.9">
      اگر رباتی در کانالی ادمین نباشد، <b>قفل بسته می‌ماند</b> و آن ربات به هیچ‌کس فایل نمی‌دهد.
      با این دکمه مطمئن شوید همه ربات‌ها دسترسی دارند.
    </p>
    <form method="post" class="inline">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="health">
      <button class="btn b">بررسی دسترسی همه ربات‌ها</button>
    </form>
    <?php if (!empty($_SESSION['health'])): ?>
      <div style="margin-top:14px;background:#f7fafc;border-radius:10px;padding:14px;line-height:2;font-size:13px">
        <?php foreach ($_SESSION['health'] as $line): ?><div><?= h($line) ?></div><?php endforeach; ?>
      </div>
      <?php unset($_SESSION['health']); ?>
    <?php endif; ?>
  </div></div>

  <div class="card"><h2>📢 کانال‌ها (<?= count($channels) ?>)</h2><div class="body">
    <p class="muted" style="margin-bottom:12px">این کانال‌ها روی <b>همه</b> ربات‌های اپلودر اعمال می‌شوند.</p>
    <?php if (!$channels): ?><div class="empty">کانالی ثبت نشده.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>عنوان</th><th>آیدی</th><th>لینک</th><th>وضعیت</th><th>اقدام</th></tr>
      <?php foreach ($channels as $ch): ?>
      <tr><td><?= h($ch['title']) ?></td><td><code><?= h($ch['chat_id']) ?></code></td>
        <td><code><?= h($ch['url'] ?: '—') ?></code></td>
        <td><?= !empty($ch['on']) ? '<span class="badge green">فعال</span>' : '<span class="badge gray">خاموش</span>' ?></td>
        <td style="white-space:nowrap">
          <form method="post" class="inline">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
            <input type="hidden" name="action" value="toggle_channel"><input type="hidden" name="id" value="<?= h($ch['id']) ?>">
            <button class="btn ghost sm"><?= !empty($ch['on']) ? 'خاموش' : 'روشن' ?></button></form>
          <form method="post" class="inline" onsubmit="return confirm('حذف کانال؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
            <input type="hidden" name="action" value="del_channel"><input type="hidden" name="id" value="<?= h($ch['id']) ?>">
            <button class="btn r sm">حذف</button></form>
        </td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

<?php // ================= کاربران ================= ?>
<?php elseif ($tab === 'users'): ?>
  <div class="card"><h2>📢 پیام همگانی</h2><div class="body">
    <form method="post" onsubmit="return confirm('ارسال به همه کاربران؟')">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
      <input type="hidden" name="action" value="broadcast">
      <textarea name="text" placeholder="متن پیام… (تگ HTML تلگرام مجاز است)" required></textarea>
      <div style="margin-top:12px"><button class="btn b">ارسال به <?= count($users) ?> کاربر</button></div>
    </form>
  </div></div>

  <div class="card"><h2>👥 کاربران (<?= count($users) ?>)</h2><div class="body">
    <?php if (!$users): ?><div class="empty">هنوز کاربری ربات را استارت نکرده.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>کاربر</th><th>آیدی</th><th>موجودی</th><th>معرف</th><th>زیرمجموعه</th><th>وضعیت</th><th>اقدام</th></tr>
      <?php foreach (array_slice($users, 0, 200, true) as $u): ?>
      <tr>
        <td><?= h(!empty($u['username']) ? '@' . $u['username'] : ($u['first_name'] ?? '—')) ?></td>
        <td><code><?= h($u['telegram_id']) ?></code></td>
        <td>
          <form method="post" style="display:flex;gap:5px">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
            <input type="hidden" name="action" value="set_balance">
            <input type="hidden" name="user_id" value="<?= h($u['telegram_id']) ?>">
            <input name="balance" value="<?= h(fmtNum($u['balance'] ?? 0)) ?>" style="width:95px">
            <button class="btn ghost sm">ذخیره</button>
          </form>
        </td>
        <td><?= !empty($u['referrer']) ? h(uLabel($users, $u['referrer'])) : '<span class="muted">—</span>' ?></td>
        <td><?= countReferrals($u['telegram_id']) ?></td>
        <td><?= !empty($u['banned']) ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></td>
        <td><form method="post">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
          <input type="hidden" name="action" value="ban_user"><input type="hidden" name="user_id" value="<?= h($u['telegram_id']) ?>">
          <button class="btn <?= !empty($u['banned']) ? 'g' : 'r' ?> sm"><?= !empty($u['banned']) ? 'آزاد' : 'مسدود' ?></button>
        </form></td>
      </tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

<?php // ================= تنظیمات ================= ?>
<?php else: ?>
  <div class="card"><h2>⚙️ تنظیمات عمومی</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="save_settings">

      <h3 style="font-size:14px;margin-bottom:10px">💳 اطلاعات پرداخت</h3>
      <div class="grid2">
        <div><label>آدرس USDT (TRC20)</label><input name="usdt" value="<?= h($C['wallets']['usdt']) ?>" style="direction:ltr"></div>
        <div><label>آدرس TRX</label><input name="trx" value="<?= h($C['wallets']['trx']) ?>" style="direction:ltr"></div>
        <div><label>شماره کارت</label><input name="card" value="<?= h($C['wallets']['card']) ?>" style="direction:ltr"></div>
        <div><label>به نام</label><input name="card_name" value="<?= h($C['wallets']['card_name']) ?>"></div>
      </div>

      <h3 style="font-size:14px;margin:18px 0 10px">👥 زیر مجموعه گیری</h3>
      <div class="grid2">
        <div><label>درصد پورسانت</label><input name="ref_percent" type="number" min="0" max="100" step="0.5"
             value="<?= h($C['referral']['percent']) ?>"></div>
        <div><label>&nbsp;</label><label style="font-weight:500">
          <input type="checkbox" name="ref_on" style="width:auto" <?= !empty($C['referral']['on']) ? 'checked' : '' ?>>
          سیستم معرفی فعال باشد</label></div>
      </div>

      <h3 style="font-size:14px;margin:18px 0 10px">🤖 پیش‌فرض ربات‌های اپلودر</h3>
      <p class="muted" style="margin-bottom:10px">این مقادیر روی ربات‌های <b>جدید</b> اعمال می‌شود. برای ربات‌های موجود از تب «ربات‌های اپلودر» استفاده کنید.</p>
      <div class="grid2">
        <div><label>⏱ حذف فایل بعد از (ثانیه)</label>
          <input name="del_sec" type="number" min="5" value="<?= (int)$C['uploader']['delete_seconds'] ?>"></div>
        <div><label>گزینه‌ها</label>
          <label style="font-weight:500"><input type="checkbox" name="force_join" style="width:auto"
            <?= !empty($C['uploader']['force_join']) ? 'checked' : '' ?>> 🔒 عضویت اجباری</label>
          <label style="font-weight:500"><input type="checkbox" name="protect" style="width:auto"
            <?= !empty($C['uploader']['protect_content']) ? 'checked' : '' ?>> 🛡 محافظت فایل</label></div>
      </div>

      <div style="margin-top:16px"><button class="btn g">ذخیره تنظیمات</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🔐 امنیت</h2><div class="body">
    <p class="muted" style="line-height:2">
      • رمز پنل در خط ۱۰ فایل <code>admin_panel.php</code><br>
      • آیدی ادمین: <code><?= h(ADMIN_ID) ?></code> — خط ۲۵ فایل ربات<br>
      • کلید کران: <code><?= h(CRON_KEY) ?></code> — خط ۲۸ فایل ربات، حتما عوضش کنید<br>
      • پوشه <code>data_master/</code> شامل توکن ربات‌هاست؛ دسترسی عمومی به آن را ببندید
    </p>
  </div></div>
<?php endif; ?>

</div>
</body>
</html>

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


    // ═══════════ ⚡ خودکارسازی ═══════════

    if ($a === 'auto_api') {
        $base = rtrim(trim($_POST['base'] ?? ''), '/');
        $key  = trim($_POST['api_key'] ?? '');
        if ($base !== '' && !preg_match('#^https://#i', $base)) go('آدرس پنل باید با https شروع شود.', 'err');
        maSetRoot(function (&$m) use ($base, $key) {
            if ($base !== '') $m['fulfill']['base'] = $base;
            if ($key !== '')  $m['fulfill']['auth_value'] = $key;
            if (trim((string)($m['fulfill']['auth_key'] ?? '')) === '') $m['fulfill']['auth_key'] = 'Authorization';
            $m['fulfill']['on']       = !empty($_POST['f_on']);
            $m['fulfill']['auto_pay'] = !empty($_POST['f_auto']);
        });
        if (!empty($_POST['preset'])) axMarketPreset();
        go('اتصال پنل فروش ذخیره شد.');
    }

    if ($a === 'auto_wallet') {
        $addr = trim($_POST['w_addr'] ?? '');
        $mn   = trim($_POST['w_mn'] ?? '');
        $pw   = trim($_POST['w_pw'] ?? '');
        $api  = rtrim(trim($_POST['w_api'] ?? ''), '/');
        $akey = trim($_POST['w_apikey'] ?? '');

        if ($addr !== '') {
            try { tonParseAddress($addr); }
            catch (Throwable $e) { go('آدرس ولت معتبر نیست: ' . $e->getMessage(), 'err'); }
        }
        if ($mn !== '') {
            [$cOk, $cWhy] = tonCryptoReady();
            if (!$cOk) go($cWhy, 'err');
            $words = array_values(array_filter(preg_split('/\s+/u', $mn), fn($x) => $x !== ''));
            if (count($words) !== 24) go('عبارت بازیابی باید دقیقا ۲۴ کلمه باشد — الان ' . count($words) . ' کلمه.', 'err');
            try { tonKeyFromMnemonic($words); }
            catch (Throwable $e) { go('عبارت بازیابی خوانده نشد: ' . $e->getMessage(), 'err'); }
            $mn = strtolower(implode(' ', $words));
        }

        axSet(function (&$c) use ($addr, $mn, $pw, $api, $akey) {
            if ($addr !== '') { $c['wallet']['address'] = $addr;  $c['wallet']['verified'] = 0; }
            if ($mn   !== '') { $c['wallet']['mnemonic'] = $mn;   $c['wallet']['verified'] = 0; }
            // «-» یعنی پاکش کن؛ خالی یعنی دست نزن
            if ($pw === '-')  { $c['wallet']['passphrase'] = '';  $c['wallet']['verified'] = 0; }
            elseif ($pw !== ''){ $c['wallet']['passphrase'] = $pw; $c['wallet']['verified'] = 0; }
            if ($api  !== '') $c['wallet']['api'] = $api;
            if ($akey !== '') $c['wallet']['api_key'] = $akey;
            $c['wallet']['version'] = in_array($_POST['w_ver'] ?? '', ['v4r2','v3r2'], true) ? $_POST['w_ver'] : 'v4r2';
            $c['wallet']['max_ton'] = (string)(float)($_POST['w_max'] ?? 1);
            $c['wallet']['day_ton'] = (string)(float)($_POST['w_day'] ?? 5);
            $c['wallet']['dry']     = !empty($_POST['w_dry']);
            $c['wallet']['on']      = !empty($_POST['w_on']);
        });

        // روشن کردن بدون تایید مالکیت، یعنی امضای کور — اجازه نمی‌دهیم
        if (!empty($_POST['w_on'])) {
            [$vok, $verr] = axWalletVerify();
            if (!$vok) {
                axSet(function (&$c) { $c['wallet']['on'] = false; });
                go("<b>ذخیره شد، ولی روشن نشد.</b>\nتایید مالکیت ناموفق بود:\n\n" . $verr .
                   "\n\nتا وقتی این تیک سبز نشود ربات هیچ تراکنشی امضا نمی‌کند — " .
                   "امضای کور روی ولتی که مطمئن نیستیم مال شماست، خطرناک است.", 'err');
            }
        }
        go('تنظیمات ولت ذخیره شد.');
    }

    if ($a === 'auto_verify') {
        [$cOk, $cWhy] = tonCryptoReady();
        if (!$cOk) go($cWhy, 'err');
        [$vok, $verr] = axWalletVerify(true);
        $bal = axWalletBalance();
        go($vok
            ? '✅ عبارت بازیابی با همین آدرس می‌خواند.' . ($bal !== null ? ' موجودی: ' . $bal . ' TON' : '')
            : '❌ ' . $verr, $vok ? 'ok' : 'err');
    }

    if ($a === 'auto_wipe') {
        axSet(function (&$c) { $c['wallet']['mnemonic'] = ''; $c['wallet']['on'] = false; $c['wallet']['verified'] = 0; });
        go('عبارت بازیابی پاک شد و ولت خاموش شد.', 'warn');
    }

    if ($a === 'save_sales') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['sales']['on']        = !empty($post['sales_on']);
            $c['sales']['chat_id']   = trim($post['sales_chat'] ?? '');
            $c['sales']['template']  = $post['sales_tpl'] ?? '';
            $c['sales']['show_user'] = !empty($post['sales_user']);
        });
        go('تنظیمات کانال فروش ذخیره شد.');
    }

    if ($a === 'test_sales') {
        $s = cfg()['sales'];
        if (empty($s['chat_id'])) go('اول آیدی کانال را بگذارید.', 'err');
        $r = sendMsg(BOT_TOKEN, $s['chat_id'], strtr($s['template'], [
            '{product}' => 'محصول نمونه', '{code}' => 'or_test123', '{amount}' => '50,000',
            '{currency}' => 'تومان', '{count}' => '7', '{limit}' => '100', '{remaining}' => '93',
            '{limit_part}' => " از 100\n🎯 باقی‌مانده: <b>93</b>", '{user}' => '@example',
            '{user_id}' => '123456', '{date}' => nowStr(),
        ]));
        go(!empty($r['ok']) ? 'پیام آزمایشی ارسال شد.' : 'خطا: ' . ($r['description'] ?? ''),
           !empty($r['ok']) ? 'ok' : 'err');
    }

    if ($a === 'broadcast_child') {
        $text = trim($_POST['text'] ?? '');
        if ($text === '') go('متن خالی است.', 'err');
        $ids = $_POST['bots'] ?? null;
        [$sent, $fail] = broadcastToChildBots($text, is_array($ids) && $ids ? $ids : null);
        go("ارسال به ربات‌های زیرمجموعه — موفق: {$sent} | ناموفق: {$fail}");
    }

    // ---- متن‌ها ----

    // ---- پشتیبانی ----
    if ($a === 'save_support') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            // دو دکمه اصلی
            foreach (['direct', 'indirect'] as $mk) {
                $c['support_main'][$mk]['emoji'] = trim($post["sm_emoji_$mk"] ?? '');
                $c['support_main'][$mk]['text']  = trim($post["sm_text_$mk"] ?? $c['support_main'][$mk]['text']);
                $col = $post["sm_color_$mk"] ?? 'none';
                $c['support_main'][$mk]['color'] = isStyle($col) ? $col : 'none';
                $c['support_main'][$mk]['icon']  = trim($post["sm_icon_$mk"] ?? '');
                if ($mk === 'direct') $c['support_main'][$mk]['value'] = trim($post['sm_value_direct'] ?? '');
            }
            $n = count($c['support_methods']);
            for ($i = 0; $i < $n; $i++) {
                $c['support_methods'][$i]['on']    = !empty($post["s_on_$i"]);
                $c['support_methods'][$i]['kind']  = 'indirect';
                $c['support_methods'][$i]['type']  = $post["s_type_$i"] ?? 'url';
                $c['support_methods'][$i]['emoji'] = trim($post["s_emoji_$i"] ?? '');
                $c['support_methods'][$i]['label'] = trim($post["s_label_$i"] ?? '');
                $c['support_methods'][$i]['value'] = trim($post["s_value_$i"] ?? '');
            }
        });
        go('روش‌های پشتیبانی ذخیره شد.');
    }

    // ---- تنظیمات عمومی ----
    if ($a === 'save_tariff') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['tariff']['on']   = !empty($post['tf_on']);
            $c['tariff']['auto'] = !empty($post['tf_auto']);
            $txt = (string)($post['tf_text'] ?? '');
            if (trim($txt) !== '') $c['tariff']['text'] = $txt;
            $c['tariff']['btn']['text']   = trim($post['tf_btn_text'] ?? '') ?: 'لیست تعرفه‌ها';
            $c['tariff']['btn']['emoji']  = trim($post['tf_btn_emoji'] ?? '');
            $c['tariff']['btn']['color']  = isStyle($post['tf_btn_color'] ?? '') ? $post['tf_btn_color'] : 'none';
            $c['tariff']['back']['text']  = trim($post['tf_back_text'] ?? '') ?: 'برگشت';
            $c['tariff']['back']['emoji'] = trim($post['tf_back_emoji'] ?? '');
            $c['tariff']['back']['color'] = isStyle($post['tf_back_color'] ?? '') ? $post['tf_back_color'] : 'none';
        });
        go('لیست تعرفه‌ها ذخیره شد.');
    }

    if ($a === 'save_gateway') {
        $post = $_POST;
        $u = trim($post['gw_base'] ?? '');
        if ($u !== '' && !preg_match('#^https://#i', $u)) go('آدرس ربات باید با https:// شروع شود.', 'err');
        cfgSet(function (&$c) use ($post, $u) {
            $c['gateway']['on']       = !empty($post['gw_on']);
            $c['gateway']['provider'] = in_array($post['gw_prov'] ?? '', ['oxapay','nowpayments','custom'], true)
                                        ? $post['gw_prov'] : 'oxapay';
            $c['gateway']['api_key']   = trim($post['gw_key'] ?? '');
            $c['gateway']['ipn_secret']= trim($post['gw_ipn'] ?? '');
            $c['gateway']['base_url']  = $u;
            $c['gateway']['coin']      = strtoupper(trim($post['gw_coin'] ?? 'USDT'));
            $c['gateway']['network']   = strtoupper(trim($post['gw_net'] ?? ''));
            $c['gateway']['rate']      = max(0, (float)str_replace(',', '', $post['gw_rate'] ?? 0));
            $c['gateway']['expire']    = max(5, (int)($post['gw_exp'] ?? 30));
            $c['gateway']['min']       = max(0, (float)str_replace(',', '', $post['gw_min'] ?? 0));
            $c['gateway']['custom_url']= trim($post['gw_curl'] ?? '');
        });
        go('درگاه پرداخت ذخیره شد.');
    }
    if ($a === 'report_group_all') {
        $lnk = trim($_POST['glink'] ?? '');
        [$lc, ] = parseChatLink($lnk);
        if ($lc === null) go('لینک یا آیدی گروه شناخته نشد.', 'err');
        $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $lc], 8);
        if (empty($info['ok'])) go('ربات به این گروه دسترسی ندارد: ' . ($info['description'] ?? '—'), 'err');
        $n = 0;
        foreach (saleButtons() as $sb) { reportMutate($sb['id'], function (&$r) use ($lc) { $r['chat_id'] = $lc; $r['on'] = true; }); $n++; }
        foreach (Product::all() as $pr) { reportMutate($pr['id'], function (&$r) use ($lc) { $r['chat_id'] = $lc; $r['on'] = true; }); $n++; }
        go('گروه «' . ($info['result']['title'] ?? $lc) . '» روی ' . $n . ' محصول نشست. حالا لینک تاپیک هرکدام را بگذارید.');
    }
    if ($a === 'seen_channels') {
        mutate('channels', function (&$a2) {
            foreach ($a2 as $k => $c) { $a2[$k]['seen'] = true; unset($a2[$k]['lost_admin']); }
        });
        go('علامت‌ها پاک شد.');
    }
    if ($a === 'save_join') {
        $post = $_POST;
        cfgSet(function (&$c) use ($post) {
            $c['join']['on'] = !empty($post['jn_on']);
            $txt = (string)($post['jn_text'] ?? '');
            if (trim($txt) !== '') $c['join']['text'] = $txt;
            $c['join']['btn']['text'] = trim($post['jn_btn'] ?? '') ?: 'عضو شدم';
        });
        go('عضویت اجباری ذخیره شد.');
    }
    if ($a === 'add_join_channel') {
        $cid = trim($_POST['chat_id'] ?? '');
        if ($cid === '') go('آیدی کانال لازم است.', 'err');
        $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $cid], 8);
        if (empty($info['ok'])) go('ربات به این کانال دسترسی ندارد: ' . ($info['description'] ?? '—'), 'err');
        $r = $info['result'];
        $title = trim($_POST['title'] ?? '') ?: ($r['title'] ?? $cid);
        $url = trim($_POST['url'] ?? '');
        if ($url === '' && !empty($r['username'])) $url = 'https://t.me/' . $r['username'];
        if ($url === '') { $inv = tg(BOT_TOKEN, 'exportChatInviteLink', ['chat_id' => $cid], 8); $url = $inv['result'] ?? ''; }
        cfgSet(function (&$c) use ($cid, $title, $url) {
            if (!is_array($c['join']['channels'] ?? null)) $c['join']['channels'] = [];
            foreach ($c['join']['channels'] as $x) if ((string)$x['chat_id'] === (string)$cid) return;
            $c['join']['channels'][] = ['chat_id' => $cid, 'title' => $title, 'url' => $url];
            $c['join']['on'] = true;
        });
        go('کانال «' . $title . '» اضافه شد.');
    }
    if ($a === 'del_join_channel') {
        $i = (int)($_POST['i'] ?? -1);
        cfgSet(function (&$c) use ($i) {
            if (isset($c['join']['channels'][$i])) {
                unset($c['join']['channels'][$i]);
                $c['join']['channels'] = array_values($c['join']['channels']);
            }
        });
        go('کانال حذف شد.');
    }

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
            // این دو فقط از فرم «تنظیمات» می‌آیند؛ فرم‌های دیگر نباید صفرشان کنند
            if (!empty($post['adv_scope'])) {
                $c['test_mode']               = !empty($post['test_mode']);
                $c['ui']['speed_show_perday'] = !empty($post['speed_perday']);
                $c['auto_approve']            = !empty($post['auto_approve']);
                $c['campaign_keep_days']      = max(0, (int)($post['keep_days'] ?? 3));
            }
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
        $post = $_POST;
        mutate('products', function (&$all) use ($p, $post) {
            $id = $p['id'];
            if (!isset($all[$id])) return;
            $all[$id]['link_code'] = trim($post['link_code'] ?? '');
            $all[$id]['emoji']     = trim($post['emoji'] ?? '💠');
            $all[$id]['color']     = isStyle($post['color'] ?? '') ? $post['color'] : 'none';
            $all[$id]['icon']      = trim($post['icon'] ?? '');
            $all[$id]['row']       = max(0, (int)($post['row'] ?? 0));
            $all[$id]['order']     = max(1, (int)($post['order'] ?? 99));
        });
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
        $post = $_POST;
        mutate('products', function (&$all) use ($id, $post) {
            if (!isset($all[$id])) return;
            $all[$id]['bot_id']    = ($post['bot_id'] ?? '') ?: null;
            $all[$id]['link_code'] = trim($post['link_code'] ?? '');
            $all[$id]['name']      = trim($post['name'] ?? $all[$id]['name']);
            $all[$id]['price']     = (float)str_replace(',', '', $post['price'] ?? $all[$id]['price']);
            $all[$id]['emoji']     = trim($post['emoji'] ?? '');
            $all[$id]['color']     = isStyle($post['color'] ?? '') ? $post['color'] : 'none';
            $all[$id]['icon']      = trim($post['icon'] ?? '');
            $all[$id]['row']       = max(0, (int)($post['row'] ?? 0));
            $all[$id]['order']     = max(1, (int)($post['order'] ?? 99));
        });
        go('محصول به‌روزرسانی شد.');
    }

    // ---- قیمت‌گذاری دکمه‌های فروش (خودِ دکمه = محصول) ----
    if ($a === 'save_btn_price') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');

        $price = str_replace([',', '،', ' '], '', trim($_POST['price'] ?? ''));
        if (!is_numeric($price) || (float)$price < 0) go('قیمت باید عدد باشد.', 'err');

        $min = (int)str_replace([',', '،'], '', $_POST['min'] ?? '0');
        $max = (int)str_replace([',', '،'], '', $_POST['max'] ?? '0');
        $per = (int)str_replace([',', '،'], '', $_POST['per'] ?? '1000');
        if ($min < 1)    go('حداقل تعداد باید بزرگ‌تر از صفر باشد.', 'err');
        if ($max <= $min) go('حداکثر باید از حداقل بیشتر باشد.', 'err');
        if ($per < 1)    go('«قیمت به ازای هر …» باید بزرگ‌تر از صفر باشد.', 'err');

        $cur  = trim($_POST['currency'] ?? 'تومان');
        $desc = trim($_POST['desc'] ?? '');
        $post = $_POST;

        subMutate($bid, $sid, function (&$x) use ($price, $cur, $min, $max, $per, $desc, $post) {
            $x['price']    = (float)$price;
            $x['currency'] = $cur !== '' ? $cur : 'تومان';
            $x['desc']     = $desc;
            if (!is_array($x['flow'] ?? null)) $x['flow'] = [];
            $x['flow'] = array_merge(defaultFlow(), $x['flow'], ['on' => true, 'ask_admin' => true]);
            $x['flow']['min'] = $min;
            $x['flow']['max'] = $max;
            $x['flow']['per'] = $per;

            // متن، ایموجی، رنگ، ضریب، نفر/روز و توضیح هر سرعت
            foreach ($x['flow']['speeds'] as $i => $sp) {
                $id = $sp['id'];
                if (isset($post['mult'][$id]) && is_numeric(str_replace(',', '', $post['mult'][$id]))) {
                    $m = (float)str_replace(',', '', $post['mult'][$id]);
                    if ($m > 0) $x['flow']['speeds'][$i]['mult'] = $m;
                }
                if (isset($post['perday'][$id])) {
                    $pd = (int)str_replace([',', '،'], '', $post['perday'][$id]);
                    if ($pd >= 0) $x['flow']['speeds'][$i]['per_day'] = $pd;
                }
                if (isset($post['sptext'][$id])) {
                    $tx = trim((string)$post['sptext'][$id]);
                    if ($tx !== '') $x['flow']['speeds'][$i]['text'] = $tx;
                }
                if (isset($post['spemoji'][$id]))
                    $x['flow']['speeds'][$i]['emoji'] = trim((string)$post['spemoji'][$id]);
                if (isset($post['spdesc'][$id]))
                    $x['flow']['speeds'][$i]['desc'] = trim((string)$post['spdesc'][$id]);
                if (isset($post['spcolor'][$id]))
                    $x['flow']['speeds'][$i]['color'] = isStyle($post['spcolor'][$id]) ? $post['spcolor'][$id] : 'none';
                $x['flow']['speeds'][$i]['on'] = !empty($post['spon'][$id]);
            }
        });
        go('قیمت‌گذاری ذخیره شد.');
    }
    if ($a === 'save_btn_report') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');
        $post = $_POST;

        foreach ([0, 1] as $i) {
            $u = trim($post['burl'][$i] ?? '');
            if ($u !== '' && !preg_match('#^(https?://|tg://)#i', $u))
                go('لینک دکمه ' . ($i + 1) . ' باید با https:// شروع شود.', 'err');
        }

        // اگر لینک تاپیک داده شده، گروه و تاپیک را از آن بخوان
        $lnk = trim($post['rlink'] ?? '');
        if ($lnk !== '') {
            [$lc, $lt] = parseChatLink($lnk);
            if ($lc === null) go('لینک تاپیک شناخته نشد. از خود تاپیک Copy Link بگیرید.', 'err');
            $post['rchat'] = $lc; $post['rthread'] = $lt;
        }

        reportMutate(subProductId($bid, $sid), function (&$r) use ($post) {
            $r['on']        = !empty($post['ron']);
            $r['chat_id']   = trim($post['rchat'] ?? '');
            $r['thread_id'] = max(0, (int)($post['rthread'] ?? 0));
            $txt = (string)($post['rtext'] ?? '');
            if (trim($txt) !== '') $r['text'] = $txt;
            $r['btn_row'] = !empty($post['brow']);
            foreach ([0, 1] as $i) {
                if (!isset($r['buttons'][$i]))
                    $r['buttons'][$i] = ['text'=>'','url'=>'','color'=>'none','icon'=>'','on'=>true];
                $r['buttons'][$i]['text']  = trim($post['btext'][$i] ?? '');
                $r['buttons'][$i]['url']   = trim($post['burl'][$i] ?? '');
                $r['buttons'][$i]['color'] = isStyle($post['bcolor'][$i] ?? '') ? $post['bcolor'][$i] : 'none';
                $r['buttons'][$i]['on']    = !empty($post['bon'][$i]);
            }
        });
        go('گزارش خرید ذخیره شد.');
    }
    if ($a === 'test_btn_report') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        $pid = subProductId($bid, $sid);
        $p = Product::get($pid);
        if (!$p) go('دکمه پیدا نشد.', 'err');
        reportSale([
            'id' => 'or_TEST000000', 'type' => 'product', 'product_id' => $pid,
            'user_id' => ADMIN_ID, 'username' => 'admin', 'amount' => (float)$p['price'],
            'currency' => $p['currency'], 'created_at' => nowStr(),
            'meta' => ['link' => 'https://t.me/example', 'qty' => 5000, 'speed' => 'نمونه',
                       'per_day' => 5000, 'eta' => 'حدود 1 روز', 'chat_title' => 'کانال نمونه'],
        ], true);
        $rr = reportOf($p);
        if (trim((string)$rr['chat_id']) === '') go('اول آیدی گروه را تنظیم و ذخیره کنید.', 'err');
        go('گزارش آزمایشی فرستاده شد.' . (empty($rr['on'])
            ? ' توجه: گزارش این محصول خاموش است، پس خریدهای واقعی گزارش نمی‌شوند.'
            : ' اگر نرسید، ربات را در گروه ادمین کنید.'));
    }

    if ($a === 'toggle_btn_product') {
        $bid = $_POST['bid'] ?? ''; $sid = $_POST['sid'] ?? '';
        if (!findSub($bid, $sid)) go('دکمه پیدا نشد.', 'err');
        subMutate($bid, $sid, function (&$x) { $x['on'] = empty($x['on']); });
        go('وضعیت دکمه تغییر کرد.');
    }

    if ($a === 'save_product_layout') {
        $lay = $_POST['product_layout'] ?? '1';
        cfgSet(function (&$c) use ($lay) { $c['ui']['product_layout'] = trim($lay); });
        go('چیدمان محصولات ذخیره شد.');
    }

    // ---- دکمه سفارشی ----

    // ---- شرکا ----
    if ($a === 'add_partner') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') go('نام لازم است.', 'err');
        $p = Partner::create($name, trim($_POST['bot_username'] ?? ''), (int)($_POST['owner_id'] ?? 0));
        go('شریک «' . $name . '» ساخته شد. کلید API پایین صفحه است.');
    }
    if ($a === 'del_partner')    { Partner::remove($_POST['id'] ?? ''); go('شریک حذف شد.'); }
    if ($a === 'rotate_key')     { Partner::rotateKey($_POST['id'] ?? ''); go('کلید عوض شد — کلید قبلی دیگر کار نمی‌کند.'); }
    if ($a === 'toggle_partner') {
        $id = $_POST['id'] ?? '';
        mutate('partners', function (&$x) use ($id) {
            if (isset($x[$id])) $x[$id]['active'] = empty($x[$id]['active']);
        });
        go('وضعیت شریک تغییر کرد.');
    }

    // ---- کمپین‌ها (سفارش ممبر) ----
    if ($a === 'add_campaign') {
        $chat   = trim($_POST['chat_id'] ?? '');
        $target = (int)($_POST['target'] ?? 0);
        if ($chat === '' || $target <= 0) go('آیدی کانال و تعداد ممبر لازم است.', 'err');

        $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat], 8);
        if (empty($r['ok'])) go('کانال پیدا نشد: ' . ($r['description'] ?? '') . ' — ربات مادر را در کانال ادمین کنید.', 'err');
        $title = trim($_POST['title'] ?? '') ?: ($r['result']['title'] ?? $chat);
        $un    = $r['result']['username'] ?? '';
        $url   = trim($_POST['url'] ?? '') ?: ($un ? "https://t.me/$un" : '');
        if (!$url) {
            $inv = tg(BOT_TOKEN, 'createChatInviteLink', ['chat_id' => $chat, 'name' => 'کمپین'], 8);
            if (!empty($inv['ok'])) $url = $inv['result']['invite_link'];
        }
        Campaign::create($title, $chat, $url, $target,
                         (array)($_POST['partners'] ?? []), (array)($_POST['bots'] ?? []),
                         trim($_POST['note'] ?? ''));
        go('کمپین «' . $title . '» ساخته شد — ' . $target . ' ممبر.');
    }
    if ($a === 'del_campaign') { Campaign::remove($_POST['id'] ?? ''); go('کمپین حذف شد.'); }
    if ($a === 'toggle_campaign') {
        $id = $_POST['id'] ?? '';
        mutate('campaigns', function (&$x) use ($id) {
            if (isset($x[$id])) $x[$id]['active'] = empty($x[$id]['active']);
        });
        go('وضعیت کمپین تغییر کرد.');
    }
    if ($a === 'edit_campaign') {
        $id = $_POST['id'] ?? '';
        $post = $_POST;
        mutate('campaigns', function (&$x) use ($id, $post) {
            if (!isset($x[$id])) return;
            $x[$id]['target']   = max(0, (int)($post['target'] ?? 0));
            $x[$id]['title']    = trim($post['title'] ?? $x[$id]['title']);
            $x[$id]['url']      = trim($post['url'] ?? $x[$id]['url']);
            if (isset($post['chat_id'])) {
                $newChat = trim($post['chat_id']);
                if ($newChat !== '' && $newChat !== ($x[$id]['chat_id'] ?? '')) {
                    // آیدی کانال تازه آمد — خطاها را صفر و کمپین را روشن کن
                    $x[$id]['chat_id']       = $newChat;
                    $x[$id]['fails']         = 0;
                    $x[$id]['paused_reason'] = '';
                    $x[$id]['active']        = true;
                }
            }
            $x[$id]['partners'] = array_values((array)($post['partners'] ?? []));
            $x[$id]['bots']     = array_values((array)($post['bots'] ?? []));
        });
        go('کمپین به‌روزرسانی شد.');
    }

    // ---- مدیران ربات اپلودر ----
    if ($a === 'add_bot_admin') {
        $bid = $_POST['id'] ?? '';
        $u = (int)($_POST['user_id'] ?? 0);
        if ($u <= 0) go('آیدی عددی معتبر بدهید.', 'err');
        BotManager::addAdmin($bid, $u);
        $b = BotManager::get($bid);
        if ($b) sendMsg($b['token'], $u, "👑 شما به‌عنوان مدیر ربات @" . h($b['username']) . " ثبت شدید.\n\nبا /panel وارد پنل شوید.");
        go('مدیر اضافه شد.');
    }
    if ($a === 'del_bot_admin') {
        BotManager::removeAdmin($_POST['id'] ?? '', (int)($_POST['user_id'] ?? 0));
        go('مدیر حذف شد.');
    }

    // ---- تنظیمات کامل هر ربات اپلودر ----
    if ($a === 'save_bot_full') {
        $id = $_POST['id'] ?? '';
        $post = $_POST;
        if (!BotManager::get($id)) go('ربات پیدا نشد.', 'err');

        BotManager::setSetting($id, 'delete_seconds', max(5, (int)($post['del_sec'] ?? 30)));
        BotManager::setSetting($id, 'force_join', !empty($post['force_join']));
        BotManager::setSetting($id, 'protect_content', !empty($post['protect']));
        BotManager::setSetting($id, 'inline_wait', !empty($post['inline_wait']));
        foreach (['start_text','join_text','joined_btn','warn_text','deleted_text','expired_text','menu_text'] as $k) {
            if (isset($post[$k])) BotManager::setSetting($id, $k, $post[$k]);
        }
        $btns = BotManager::settings($id)['buttons'];
        foreach (array_keys($btns) as $bk) {
            $btns[$bk]['emoji'] = trim($post["b_emoji_$bk"] ?? '');
            $btns[$bk]['text']  = trim($post["b_text_$bk"] ?? $btns[$bk]['text']);
            $btns[$bk]['color'] = isStyle($post["b_color_$bk"] ?? '') ? $post["b_color_$bk"] : 'none';
            $btns[$bk]['icon']  = trim($post["b_icon_$bk"] ?? '');
            $btns[$bk]['row']   = max(1, (int)($post["b_row_$bk"] ?? 1));
            $btns[$bk]['order'] = max(1, (int)($post["b_order_$bk"] ?? 1));
            $btns[$bk]['on']    = !empty($post["b_on_$bk"]);
        }
        BotManager::setSetting($id, 'buttons', $btns);

        $gc = BotManager::settings($id)['glass_colors'];
        foreach (array_keys($gc) as $role) {
            $v = $post["bg_$role"] ?? 'none';
            $gc[$role] = isStyle($v) ? $v : 'none';
        }
        BotManager::setSetting($id, 'glass_colors', $gc);

        // کانال‌های مخصوص این ربات
        $chosen = $post['bot_channels'] ?? [];
        mutate('channels', function (&$all) use ($id, $chosen) {
            foreach ($all as $cid => $ch) {
                $bots = array_values(array_filter($ch['bots'] ?? [], fn($x) => $x !== $id));
                if (in_array($cid, (array)$chosen, true)) $bots[] = $id;
                $all[$cid]['bots'] = array_values(array_unique($bots));
            }
        });

        if (!empty($post['apply_all'])) {
            $src = BotManager::settings($id);
            foreach (BotManager::all() as $ob) {
                if ($ob['id'] === $id) continue;
                foreach (['delete_seconds','force_join','protect_content','inline_wait','start_text',
                          'join_text','joined_btn','warn_text','deleted_text','expired_text',
                          'menu_text','buttons','glass_colors'] as $k) {
                    BotManager::setSetting($ob['id'], $k, $src[$k]);
                }
            }
            go('تنظیمات ذخیره و روی همه ربات‌ها اعمال شد.');
        }
        go('تنظیمات ربات ذخیره شد.');
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
        Channels::add($chat, $title, $url, (array)($_POST['bots'] ?? []));
        go('کانال «' . $title . '» اضافه شد. ربات‌های اپلودر را در آن ادمین کنید.');
    }
    if ($a === 'del_channel') { Channels::remove($_POST['id'] ?? ''); go('کانال حذف شد.'); }
    if ($a === 'health') {
        $lines = [];
        foreach (Channels::health() as $r) {
            $lines[] = ($r['ok'] ? '✅' : '❌') . ' ' . $r['title'] .
                       ($r['ok'] ? ' — ربات مادر دسترسی دارد' : ' — ' . ($r['error'] ?: 'ربات مادر ادمین نیست'));
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
        // my_chat_member لازم است تا ثبت خودکار کانال کار کند
        $r = tg(BOT_TOKEN, 'setWebhook', [
            'url' => baseUrl() . '/bot_master_membership.php',
            'drop_pending_updates' => 'true',
            'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
        ]);
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
        completeApprovedOrder($res);   // اطلاع به کاربر + تحویل + اعلام در کانال فروش
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
$channels  = Channels::all();
$partners  = Partner::all();
$campaigns = Campaign::all();

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
.brow8{grid-template-columns:44px 1fr 96px 52px 90px 52px 52px 40px!important;gap:7px!important}
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
.bar{height:9px;background:#edf2f7;border-radius:20px;overflow:hidden}
.bar-in{height:100%;background:linear-gradient(90deg,#667eea,#38a169);border-radius:20px;transition:width .3s}
pre.code{background:#2d3748;color:#e2e8f0;padding:13px;border-radius:10px;font-size:11.5px;
line-height:1.75;overflow-x:auto;direction:ltr;text-align:left;white-space:pre;margin:0}
details summary::-webkit-details-marker{display:none}
.note{background:#e8f0fe;border-right:4px solid #667eea;border-radius:10px;padding:12px 14px;
margin-bottom:14px;font-size:12.5px;line-height:1.95;color:#2d3748}
.tbar{display:flex;flex-wrap:wrap;gap:5px;margin-bottom:6px}
.tbar button{background:#edf2f7;border:0;border-radius:7px;padding:5px 10px;font-size:11.5px;
cursor:pointer;font-family:inherit;color:#4a5568}
.tbar button:hover{background:#dde5ef}
.pbtn.pb-b{background:#3182ce;color:#fff}
.pbtn.pb-g{background:#38a169;color:#fff}
.pbtn.pb-r{background:#e53e3e;color:#fff}
@media(max-width:900px){.brow,.brow8,.srow{grid-template-columns:1fr 1fr!important;gap:6px!important}}
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
  'support'   => '📞 پشتیبانی',
  'bots'      => '🤖 ربات‌های اپلودر',
  'channels'  => '📢 کانال‌ها',
  'campaigns' => '🎯 سفارش ممبر',
  'partners'  => '🤝 ربات‌های شریک',
  'referral'  => '👥 رفرال',
  'users'     => '👥 کاربران',
  'auto'      => '⚡ خودکارسازی',
  'settings'  => '⚙️ تنظیمات',
];
foreach ($tabs as $k => $l): ?>
  <a href="?tab=<?= $k ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= $l ?></a>
<?php endforeach; ?>
</div></nav>

<div class="wrap">
<?php if ($flash): ?>
  <div class="flash <?= h($flash['type']) ?>" style="line-height:2">
    <?= nl2br(strip_tags((string)$flash['msg'], '<code><b>')) ?>
  </div>
<?php endif; ?>
<?php if (ADMIN_PASSWORD === 'admin123456'): ?>
  <div class="flash err">🔴 <b>رمز پنل هنوز پیش‌فرض است.</b>
  هرکس آدرس این صفحه را بداند وارد می‌شود — و از تب ⚡ خودکارسازی به ولت شما هم می‌رسد.
  خط ۱۰ فایل <code>admin_panel.php</code> را همین حالا عوض کنید.</div>
<?php endif; ?>

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

  <div class="card"><h2>✏️ دکمه‌ها و متن‌ها</h2><div class="body">
    <div class="note" style="margin:0">
      ویرایش <b>دکمه‌ها</b>، <b>متن‌ها</b>، <b>رنگ‌ها</b> و <b>متن دکمه‌های ثابت</b>
      حالا داخل <b>خود ربات</b> است — چون آنجا می‌توانید ایموجی پریمیوم و نقل‌قول
      را مستقیم تایپ کنید.<br><br>
      در ربات <code>/panel</code> را بزنید → 🎨 دکمه‌ها · 📝 متن‌ها · 💠 رنگ دکمه‌های شیشه‌ای
    </div>
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
  <?php $TF = cfg()['tariff'] ?? []; ?>
  <div class="card"><h2>📋 لیست تعرفه‌ها <?= !empty($TF['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      یک دکمه شیشه‌ای زیر بخش «خرید محصول». جدول قیمت‌ها را خودکار می‌سازد و
      زیرش یک دکمه <b>برگشت</b> دارد که به بخش ثبت سفارش برمی‌گردد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_tariff">

      <div style="margin:10px 0">
        <label style="font-weight:500"><input type="checkbox" name="tf_on" style="width:auto"
          <?= !empty($TF['on']) ? 'checked' : '' ?>> دکمه «لیست تعرفه‌ها» نشان داده شود</label>
        <label style="font-weight:500"><input type="checkbox" name="tf_auto" style="width:auto"
          <?= !empty($TF['auto']) ? 'checked' : '' ?>> 📊 جدول خودکار قیمت‌ها اضافه شود</label>
      </div>

      <label>متن لیست تعرفه‌ها</label>
      <textarea name="tf_text" rows="8" style="direction:rtl"><?= h($TF['text'] ?? '') ?></textarea>
      <div class="muted" style="margin-top:6px">
        اگر <code>{list}</code> بنویسید، جدول قیمت‌ها دقیقا همان‌جا می‌نشیند؛ وگرنه زیر متن اضافه می‌شود.<br>
        HTML مجاز: <code>&lt;b&gt;</code> <code>&lt;i&gt;</code> <code>&lt;code&gt;</code>
        <code>&lt;blockquote&gt;</code> <code>&lt;blockquote expandable&gt;</code><br>
        ✨ برای <b>ایموجی پریمیوم</b> متن را داخل ربات بنویسید: <code>/panel</code> ← 📋 لیست تعرفه‌ها ← ✏️ متن تعرفه
      </div>

      <div class="grid2" style="margin-top:14px">
        <div><label>🔘 متن دکمه تعرفه</label>
          <input name="tf_btn_text" value="<?= h($TF['btn']['text'] ?? 'لیست تعرفه‌ها') ?>"></div>
        <div><label>😀 ایموجی دکمه</label>
          <input name="tf_btn_emoji" value="<?= h($TF['btn']['emoji'] ?? '') ?>" style="text-align:center"></div>
        <div><label>🎨 رنگ دکمه</label><select name="tf_btn_color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= ($TF['btn']['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>◀️ متن دکمه برگشت</label>
          <input name="tf_back_text" value="<?= h($TF['back']['text'] ?? 'برگشت') ?>"></div>
        <div><label>😀 ایموجی برگشت</label>
          <input name="tf_back_emoji" value="<?= h($TF['back']['emoji'] ?? '') ?>" style="text-align:center"></div>
        <div><label>🎨 رنگ برگشت</label><select name="tf_back_color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= ($TF['back']['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
      </div>

      <div style="margin-top:14px"><button class="btn g">ذخیره لیست تعرفه‌ها</button></div>
    </form>

    <?php $tbl = tariffTable(); if (trim($tbl) !== ''): ?>
      <div style="margin-top:16px"><label>👁 پیش‌نمایش جدول</label>
        <div class="prev" style="white-space:pre-wrap;line-height:2"><?= $tbl ?></div></div>
    <?php endif; ?>
  </div></div>

  <?php $saleBtns = saleButtons(); ?>
  <?php if ($saleBtns): ?>
  <div class="card"><h2>📢 گروه گزارش خرید</h2><div class="body">
    <div class="note">
      یک گروه با <b>تاپیک</b> بسازید — برای هر محصول یک تاپیک. ربات را در گروه <b>ادمین</b> کنید.
      بعد لینک هر تاپیک را روی محصول خودش بگذارید (پایین‌تر، داخل کارت هر محصول).<br>
      لینک تاپیک را اینطور می‌گیرید: روی یکی از پیام‌های آن تاپیک نگه دارید ← <b>Copy Link</b>.
    </div>
    <form method="post" style="margin-top:12px;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="report_group_all">
      <div style="flex:1;min-width:240px"><label>لینک یا آیدی گروه — روی همه محصولات می‌نشیند</label>
        <input name="glink" required placeholder="https://t.me/c/1234567890/1 یا -1001234567890" style="direction:ltr"></div>
      <button class="btn g">اعمال روی همه</button>
    </form>
  </div></div>

  <div class="card"><h2>💰 قیمت‌گذاری دکمه‌های فروش</h2><div class="body">
    <div class="note">
      این دکمه‌ها خودشان محصول‌اند — رکورد محصول جداگانه لازم ندارند.
      مشتری که رویشان بزند، مستقیم می‌رود سراغ
      <b>لینک کانال ← تعداد ← سرعت ← ادمین کردن ربات ← فاکتور</b>.<br>
      قیمت، تعداد و ضریب سرعت‌ها را همین‌جا تنظیم کنید.
      (ایموجی، رنگ و ایموجی پریمیوم داخل خود ربات: <code>/panel</code> ← 🔘 دکمه‌ها.)
    </div>
  </div></div>

  <?php foreach ($saleBtns as $sb):
    [$bid, $sid] = $sb['btn'];
    $f = $sb['flow']; ?>
  <div class="card">
    <h2>
      <?= h(trim(($sb['emoji'] ?? '') . ' ' . $sb['name'])) ?>
      <?= (float)$sb['price'] > 0
            ? '<span class="badge green">' . h(number_format((float)$sb['price']) . ' ' . $sb['currency']) . '</span>'
            : '<span class="badge">قیمت ندارد</span>' ?>
      <?= empty($sb['active']) ? '<span class="badge">خاموش</span>' : '' ?>
    </h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="save_btn_price">
        <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">

        <?php $perNow = max(1, (int)$f['per']); $perMember = (float)$sb['price'] / $perNow; ?>
        <div class="note" style="margin-bottom:12px">
          💠 <b>قیمت هر ۱ ممبر: <?= h(rtrim(rtrim(number_format($perMember, 2), '0'), '.') ?: '0') ?>
          <?= h($sb['currency']) ?></b>
          — یعنی <?= number_format((float)$sb['price']) ?> برای هر <?= number_format($perNow) ?> نفر.<br>
          می‌خواهید ممبری قیمت بگذارید؟ «به ازای هر چند نفر» را <code>1</code> بگذارید و
          قیمت پایه را قیمت یک ممبر بنویسید.
        </div>
        <div class="grid2">
          <div><label>قیمت پایه</label>
            <input name="price" value="<?= h((float)$sb['price'] > 0 ? (0 + $sb['price']) : '') ?>"
                   placeholder="5000" style="direction:ltr" required></div>
          <div><label>به ازای هر چند نفر؟ (۱ = قیمت هر ممبر)</label>
            <input name="per" type="number" min="1" value="<?= (int)$f['per'] ?>" style="direction:ltr"></div>
          <div><label>واحد پول</label><select name="currency">
            <?php foreach (['تومان', 'USDT', 'TRX'] as $cu): ?>
              <option <?= $sb['currency'] === $cu ? 'selected' : '' ?>><?= h($cu) ?></option>
            <?php endforeach; ?></select></div>
          <div><label>حداقل تعداد سفارش</label>
            <input name="min" type="number" min="1" value="<?= (int)$f['min'] ?>" style="direction:ltr"></div>
          <div><label>حداکثر تعداد سفارش</label>
            <input name="max" type="number" min="2" value="<?= (int)$f['max'] ?>" style="direction:ltr"></div>
          <div><label>توضیح کوتاه (اختیاری)</label>
            <input name="desc" value="<?= h($sb['desc'] ?? '') ?>" placeholder="تحویل تدریجی"></div>
        </div>

        <div style="margin-top:16px"><label>⚡️ سرعت‌ها</label>
          <table style="margin-top:6px">
            <tr><th>ایموجی</th><th>متن دکمه</th><th>ضریب</th><th>نفر در روز</th>
                <th>قیمت هر <?= number_format((int)$f['per']) ?></th><th>رنگ</th><th>روشن</th></tr>
            <?php foreach ($f['speeds'] as $sp): ?>
              <tr>
                <td><input name="spemoji[<?= h($sp['id']) ?>]" value="<?= h($sp['emoji'] ?? '') ?>"
                           style="text-align:center;max-width:70px"></td>
                <td><input name="sptext[<?= h($sp['id']) ?>]" value="<?= h($sp['text'] ?? '') ?>"
                           style="min-width:130px"></td>
                <td><input name="mult[<?= h($sp['id']) ?>]" value="<?= h((string)$sp['mult']) ?>"
                           style="direction:ltr;max-width:90px"></td>
                <td><input name="perday[<?= h($sp['id']) ?>]" type="number" min="0"
                           value="<?= (int)($sp['per_day'] ?? 0) ?>" style="direction:ltr;max-width:120px"></td>
                <td class="muted"><?= h(number_format((float)$sb['price'] * (float)$sp['mult']) . ' ' . $sb['currency']) ?></td>
                <td><select name="spcolor[<?= h($sp['id']) ?>]" style="max-width:120px">
                  <?php foreach (styleMap() as $sk => $sl): ?>
                    <option value="<?= h($sk) ?>" <?= ($sp['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                  <?php endforeach; ?></select></td>
                <td><input type="checkbox" name="spon[<?= h($sp['id']) ?>]" value="1" style="width:auto"
                           <?= (!isset($sp['on']) || !empty($sp['on'])) ? 'checked' : '' ?>></td>
              </tr>
              <tr>
                <td class="muted">توضیح</td>
                <td colspan="6"><input name="spdesc[<?= h($sp['id']) ?>]" value="<?= h($sp['desc'] ?? '') ?>"
                       placeholder="یک خط توضیح — زیر متن انتخاب سرعت به مشتری نشان داده می‌شود"></td>
              </tr>
            <?php endforeach; ?>
          </table>
          <div class="muted" style="margin-top:6px">
            متن دکمه = ایموجی + متن + «نفر در روز». مثلا <code>🏃 نیمه‌سریع — 3,500/روز</code><br>
            ✨ ایموجی پریمیوم فقط داخل ربات تنظیم می‌شود:
            <code>/panel</code> ← 🔘 دکمه‌ها ← این دکمه ← ⚡️ سرعت‌ها
          </div>
        </div>

        <?php
          $exQty = min((int)$f['max'], max((int)$f['min'], 5000));
          $fast  = null;
          foreach ($f['speeds'] as $sp) if (!isset($sp['on']) || !empty($sp['on'])) { $fast = $sp; break; }
        ?>
        <?php if ($fast && (float)$sb['price'] > 0): ?>
          <div class="note" style="margin-top:14px">
            🧾 نمونه فاکتور — <?= number_format($exQty) ?> نفر با
            «<?= h(trim(($fast['emoji'] ?? '') . ' ' . $fast['text'])) ?>»:
            <b><?= h(number_format(round((float)$sb['price'] * (float)$fast['mult'] * ($exQty / max(1, (int)$f['per'])))) . ' ' . $sb['currency']) ?></b>
            <?php if ((int)($fast['per_day'] ?? 0) > 0): ?>
              · ⏳ <?= h(speedEta($fast, $exQty)) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn g">ذخیره قیمت‌گذاری</button>
        </div>
      </form>

      <?php $rp = reportOf($sb); ?>
      <details style="margin-top:16px"<?= !empty($rp['on']) ? ' open' : '' ?>>
        <summary style="cursor:pointer;font-weight:700;padding:8px 0">
          📢 گزارش خرید در گروه <?= !empty($rp['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?>
        </summary>
        <form method="post" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
          <input type="hidden" name="action" value="save_btn_report">
          <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">

          <div class="note">
            یک گروه با چند تاپیک بسازید و برای هر محصول <b>شماره تاپیک خودش</b> را بگذارید —
            گزارش خرید هر محصول در بخش خودش می‌افتد.
            ربات باید در گروه <b>ادمین</b> باشد.
          </div>

          <div style="margin:10px 0">
            <label style="font-weight:500"><input type="checkbox" name="ron" value="1" style="width:auto"
              <?= !empty($rp['on']) ? 'checked' : '' ?>> گزارش این محصول روشن باشد</label>
          </div>

          <div style="margin-bottom:10px"><label>🔗 لینک تاپیک (ساده‌ترین راه)</label>
            <input name="rlink" placeholder="https://t.me/c/1234567890/11" style="direction:ltr">
            <div class="muted" style="margin-top:6px">
              روی یکی از پیام‌های همان تاپیک نگه دارید ← <b>Copy Link</b> و اینجا بچسبانید.
              گروه و شماره تاپیک با هم پر می‌شوند و دو فیلد پایین را نادیده می‌گیرد.
            </div>
          </div>
          <div class="grid2">
            <div><label>آیدی گروه</label>
              <input name="rchat" value="<?= h($rp['chat_id']) ?>" placeholder="-1001234567890" style="direction:ltr"></div>
            <div><label>شماره تاپیک (۰ = بدون تاپیک)</label>
              <input name="rthread" type="number" min="0" value="<?= (int)$rp['thread_id'] ?>" style="direction:ltr"></div>
          </div>

          <div style="margin-top:12px"><label>متن گزارش</label>
            <textarea name="rtext" rows="7" style="direction:rtl"><?= h($rp['text']) ?></textarea>
            <div class="muted" style="margin-top:6px">
              متغیرها: <code>{product} {emoji} {qty} {speed} {per_day} {eta} {amount} {currency}
              {code} {link} {channel} {user} {user_id} {date} {delivered}</code><br>
              HTML مجاز است: <code>&lt;b&gt;</code> <code>&lt;i&gt;</code> <code>&lt;code&gt;</code>
              <code>&lt;blockquote&gt;</code> <code>&lt;blockquote expandable&gt;</code><br>
              ✨ برای <b>ایموجی پریمیوم</b> و نقل‌قول آماده، متن را داخل ربات بنویسید:
              <code>/panel</code> ← 🔘 دکمه‌ها ← این دکمه ← 📢 گزارش خرید ← ✏️ متن گزارش
            </div>
          </div>

          <div style="margin-top:14px">
            <label style="font-weight:500"><input type="checkbox" name="brow" value="1" style="width:auto"
              <?= (!isset($rp['btn_row']) || !empty($rp['btn_row'])) ? 'checked' : '' ?>>
              دو دکمه <b>کنار هم</b> باشند (تیک بردارید = زیر هم)</label>
          </div>
          <div style="margin-top:14px"><label>🔘 دو دکمه زیر گزارش</label>
            <table style="margin-top:6px">
              <tr><th>#</th><th>متن دکمه</th><th>لینک</th><th>رنگ</th><th>روشن</th></tr>
              <?php foreach ([0, 1] as $i): $b = $rp['buttons'][$i] ?? ['text'=>'','url'=>'','color'=>'none','on'=>true]; ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><input name="btext[<?= $i ?>]" value="<?= h($b['text'] ?? '') ?>" placeholder="ثبت سفارش"></td>
                  <td><input name="burl[<?= $i ?>]" value="<?= h($b['url'] ?? '') ?>"
                             placeholder="https://t.me/YourBot" style="direction:ltr"></td>
                  <td><select name="bcolor[<?= $i ?>]" style="max-width:120px">
                    <?php foreach (styleMap() as $sk => $sl): ?>
                      <option value="<?= h($sk) ?>" <?= ($b['color'] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
                    <?php endforeach; ?></select></td>
                  <td><input type="checkbox" name="bon[<?= $i ?>]" value="1" style="width:auto"
                             <?= !empty($b['on']) ? 'checked' : '' ?>></td>
                </tr>
              <?php endforeach; ?>
            </table>
            <div class="muted" style="margin-top:6px">
              دکمه بدون لینک نشان داده نمی‌شود. متن‌ها عمداً بدون ایموجی‌اند —
              ✨ ایموجی پریمیوم را داخل ربات بگذارید:
              <code>/panel</code> ← 📢 گزارش خرید ← محصول ← دکمه اول/دوم ← ✨ پریمیوم
            </div>
          </div>

          <div style="margin-top:14px"><button class="btn g">ذخیره گزارش</button></div>
        </form>

        <form method="post" style="margin-top:10px">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
          <input type="hidden" name="action" value="test_btn_report">
          <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">
          <button class="btn b">🧪 ارسال گزارش آزمایشی</button>
        </form>
      </details>

      <form method="post" style="margin-top:14px">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="toggle_btn_product">
        <input type="hidden" name="bid" value="<?= h($bid) ?>"><input type="hidden" name="sid" value="<?= h($sid) ?>">
        <button class="btn"><?= !empty($sb['active']) ? 'خاموش کردن دکمه' : 'روشن کردن دکمه' ?></button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="card"><h2>➕ ساخت محصول جداگانه (اختیاری)</h2><div class="body">
    <div class="note">
      هر محصول یک <b>دکمه</b> در بخش «خرید محصول» می‌سازد — مثلا «ممبر اخلاقی»، «ممبر فیک».
      برای هرکدام ایموجی، رنگ واقعی و ایموجی پریمیوم جدا تنظیم می‌شود.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="add_product">
      <div class="grid2">
        <div><label>نام محصول</label><input name="name" required placeholder="ممبر اخلاقی"></div>
        <div><label>قیمت</label><input name="price" required placeholder="50000"></div>
        <div><label>واحد پول</label><select name="currency">
          <option>تومان</option><option>USDT</option><option>TRX</option></select></div>
        <div><label>محدودیت خرید (۰ = نامحدود)</label><input name="limit" type="number" min="0" value="0"></div>
        <div><label>ایموجی دکمه</label><input name="emoji" value="💠" style="text-align:center"></div>
        <div><label>رنگ دکمه</label><select name="color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= $sk === 'success' ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>✨ ایموجی پریمیوم (کد)</label><input name="icon" placeholder="از /emoji در ربات" style="direction:ltr"></div>
        <div><label>ردیف (۰ = خودکار)</label><input name="row" type="number" min="0" value="0"></div>
        <div><label>ترتیب</label><input name="order" type="number" min="1" value="99"></div>
        <div><label>ربات اپلودر تحویل</label><select name="bot_id">
          <option value="">— بدون محتوا —</option>
          <?php foreach ($bots as $bb): ?><option value="<?= h($bb['id']) ?>">@<?= h($bb['username']) ?></option><?php endforeach; ?>
        </select></div>
        <div><label>کد لینک محتوا</label><input name="link_code" placeholder="از تب ربات‌ها"></div>
      </div>
      <div style="margin-top:12px"><label>توضیح</label><input name="desc" placeholder="تحویل آنی"></div>
      <div style="margin-top:14px"><button class="btn g">ساخت محصول</button></div>
    </form>
  </div></div>

  <div class="card"><h2>📐 چیدمان دکمه‌های محصول</h2><div class="body">
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="save_product_layout">
      <div style="flex:1;min-width:200px"><label>الگو (وقتی ردیف محصول ۰ باشد)</label>
        <input name="product_layout" value="<?= h($C['ui']['product_layout'] ?? '1') ?>" placeholder="2,1,1" style="direction:ltr"></div>
      <button class="btn b">ذخیره</button>
    </form>
    <div class="prev" style="margin-top:12px">
      <?php
      $prods = activeProducts("buy");
      $hasRows = false; foreach ($prods as $pp) if (!empty($pp['row'])) { $hasRows = true; break; }
      if ($hasRows) { $g = []; foreach ($prods as $pp) $g[(int)($pp['row'] ?: 99)][] = $pp; ksort($g); $g = array_values($g); }
      else $g = layoutRows($prods, $C['ui']['product_layout'] ?? '1');
      foreach ($g as $line): ?>
        <div class="pgrid"><?php foreach ($line as $pp):
          $cls = ['primary'=>'pb-b','success'=>'pb-g','danger'=>'pb-r'][$pp['color'] ?? ''] ?? ''; ?>
          <div class="pbtn <?= $cls ?>"><?= h(trim(($pp['emoji'] ?? '') . ' ' . $pp['name'] . ' — ' . fmtNum($pp['price']) . ' ' . $pp['currency'])) ?></div>
        <?php endforeach; ?></div>
      <?php endforeach; ?>
      <?php if (!$prods): ?><div class="muted">محصولی نساخته‌اید.</div><?php endif; ?>
    </div>
  </div></div>

  <?php foreach ($products as $p): ?>
  <div class="card"><h2><?= h(($p['emoji'] ?? '') . ' ' . $p['name']) ?> — <?= count($p['buyers']) ?> خریدار</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
      <input type="hidden" name="action" value="link_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
      <div class="grid2">
        <div><label>نام</label><input name="name" value="<?= h($p['name']) ?>"></div>
        <div><label>قیمت (<?= h($p['currency']) ?>)</label><input name="price" value="<?= h(fmtNum($p['price'])) ?>"></div>
        <div><label>ایموجی</label><input name="emoji" value="<?= h($p['emoji'] ?? '') ?>" style="text-align:center"></div>
        <div><label>رنگ دکمه</label><select name="color">
          <?php foreach (styleMap() as $sk => $sl): ?>
            <option value="<?= h($sk) ?>" <?= ($p['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>✨ ایموجی پریمیوم</label><input name="icon" value="<?= h($p['icon'] ?? '') ?>" style="direction:ltr"></div>
        <div><label>ردیف / ترتیب</label>
          <div style="display:flex;gap:6px">
            <input name="row" type="number" min="0" value="<?= (int)($p['row'] ?? 0) ?>">
            <input name="order" type="number" min="1" value="<?= (int)($p['order'] ?? 99) ?>">
          </div></div>
        <div><label>ربات اپلودر</label><select name="bot_id">
          <option value="">— بدون —</option>
          <?php foreach ($bots as $bb): ?>
            <option value="<?= h($bb['id']) ?>" <?= ($p['bot_id'] ?? '') === $bb['id'] ? 'selected' : '' ?>>@<?= h($bb['username']) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>کد لینک محتوا</label><input name="link_code" value="<?= h($p['link_code'] ?? '') ?>"></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid #edf2f7">
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
        <button class="btn ghost sm"><?= !empty($p['active']) ? 'غیرفعال کن' : 'فعال کن' ?></button>
      </form>
      <form method="post" class="inline" onsubmit="return confirm('حذف محصول؟')">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="products">
        <input type="hidden" name="action" value="del_product"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
        <button class="btn r sm">حذف</button>
      </form>
      <span class="muted" style="margin-right:8px">
        <?= !empty($p['active']) ? '<span class="badge green">فعال</span>' : '<span class="badge gray">غیرفعال</span>' ?>
        محدودیت: <?= ((int)$p['limit']) > 0 ? (int)$p['limit'] : '∞' ?>
      </span>
    </div>
  </div></div>
  <?php endforeach; ?>
  <?php if (!$products): ?><div class="card"><div class="body"><div class="empty">محصولی نساخته‌اید.</div></div></div><?php endif; ?>

<?php // ================= پشتیبانی ================= ?>
<?php elseif ($tab === 'support'): ?>
  <div class="card"><h2>📞 دو دکمه اصلی پشتیبانی</h2><div class="body">
    <div class="note">
      کاربر فقط <b>دو دکمه</b> می‌بیند: ارتباط مستقیم و ارتباط غیر مستقیم.<br>
      <b>مستقیم</b> یک لینک است و کاربر را یک‌راست می‌برد.
      <b>غیر مستقیم</b> فهرست پایین را باز می‌کند.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="support">
      <input type="hidden" name="action" value="save_support">

      <?php foreach (['direct' => '💬 ارتباط مستقیم', 'indirect' => '📨 ارتباط غیر مستقیم'] as $mk => $mlbl):
        $m = $C['support_main'][$mk]; ?>
        <h3 style="font-size:13.5px;margin:<?= $mk === 'direct' ? '0' : '18px' ?> 0 9px"><?= $mlbl ?></h3>
        <div class="grid2">
          <div><label>ایموجی</label><input name="sm_emoji_<?= $mk ?>" value="<?= h($m['emoji']) ?>" style="text-align:center"></div>
          <div><label>متن دکمه</label><input name="sm_text_<?= $mk ?>" value="<?= h($m['text']) ?>"></div>
          <div><label>رنگ</label><select name="sm_color_<?= $mk ?>">
            <?php foreach (styleMap() as $sk => $sl): ?>
              <option value="<?= h($sk) ?>" <?= ($m['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
            <?php endforeach; ?></select></div>
          <div><label>✨ ایموجی پریمیوم</label><input name="sm_icon_<?= $mk ?>" value="<?= h($m['icon'] ?? '') ?>" style="direction:ltr"></div>
          <?php if ($mk === 'direct'): ?>
          <div style="grid-column:1/-1"><label>لینک مقصد</label>
            <input name="sm_value_direct" value="<?= h($m['value']) ?>" placeholder="https://t.me/malakeBTC" style="direction:ltr"></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <h3 style="font-size:13.5px;margin:20px 0 9px">📨 گزینه‌های زیر «ارتباط غیر مستقیم»</h3>
      <div style="display:grid;grid-template-columns:40px 100px 60px 1fr 1.4fr;gap:8px;
                  font-size:11.5px;color:#718096;font-weight:700;padding:0 9px 6px">
        <div>فعال</div><div>نوع</div><div>ایموجی</div><div>عنوان</div><div>مقدار</div>
      </div>
      <?php foreach ($C['support_methods'] as $i => $m): ?>
      <div class="srow" style="grid-template-columns:40px 100px 60px 1fr 1.4fr">
        <input type="checkbox" name="s_on_<?= $i ?>" <?= !empty($m['on']) ? 'checked' : '' ?> style="width:auto">
        <select name="s_type_<?= $i ?>">
          <?php foreach (['url' => 'لینک', 'ticket' => 'تیکت', 'text' => 'متن', 'phone' => 'تلفن'] as $tk => $tl): ?>
            <option value="<?= $tk ?>" <?= ($m['type'] ?? '') === $tk ? 'selected' : '' ?>><?= $tl ?></option>
          <?php endforeach; ?>
        </select>
        <input name="s_emoji_<?= $i ?>" value="<?= h($m['emoji'] ?? '') ?>" style="text-align:center">
        <input name="s_label_<?= $i ?>" value="<?= h($m['label'] ?? '') ?>">
        <input name="s_value_<?= $i ?>" value="<?= h($m['value'] ?? '') ?>" placeholder="https://t.me/… یا متن یا شماره">
      </div>
      <?php endforeach; ?>
      <div style="margin-top:14px"><button class="btn g">ذخیره پشتیبانی</button></div>
    </form>

    <div class="prev">
      <div class="muted" style="margin-bottom:8px">پیش‌نمایش — چیزی که کاربر می‌بیند:</div>
      <?php foreach (['direct', 'indirect'] as $mk):
        $m = $C['support_main'][$mk];
        $cls = ['primary'=>'pb-b','success'=>'pb-g','danger'=>'pb-r'][$m['color'] ?? ''] ?? ''; ?>
        <div class="pbtn <?= $cls ?>"><?= h(trim($m['emoji'] . ' ' . $m['text'])) ?></div>
      <?php endforeach; ?>
    </div>
  </div></div>

<?php // ================= ربات‌های اپلودر ================= ?>
<?php elseif ($tab === 'bots'): ?>
  <div class="card"><h2>➕ افزودن ربات اپلودر</h2><div class="body">
    <div class="note">
      ربات‌های اپلودر <b>لازم نیست در هیچ کانالی عضو یا ادمین باشند</b> —
      بررسی عضویت اجباری همیشه با توکن <b>ربات مادر</b> انجام می‌شود.
      فقط ربات مادر باید در کانال ادمین باشد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
      <input type="hidden" name="action" value="add_bot">
      <label>توکن ربات (از @BotFather)</label>
      <input name="token" required placeholder="123456:ABC-DEF..." style="direction:ltr">
      <div style="margin-top:12px"><button class="btn g">افزودن ربات</button></div>
    </form>
  </div></div>

  <?php if (!$bots): ?>
    <div class="card"><div class="body"><div class="empty">هنوز ربات اپلودری اضافه نکرده‌اید.</div></div></div>
  <?php endif; ?>

  <?php foreach ($bots as $b):
    $bs = BotManager::settings($b['id']);
    $links = Links::all($b['id']);
    $myChans = [];
    foreach ($channels as $cid => $ch) if (in_array($b['id'], $ch['bots'] ?? [], true)) $myChans[] = $cid;
  ?>
  <div class="card">
    <h2>🤖 @<?= h($b['username']) ?> — <?= count($links) ?> لینک · <?= count(load('bots/' . $b['id'] . '/users')) ?> کاربر</h2>
    <div class="body">
      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
        <input type="hidden" name="action" value="save_bot_full"><input type="hidden" name="id" value="<?= h($b['id']) ?>">

        <h3 style="font-size:13.5px;margin-bottom:9px">⚙️ رفتار</h3>
        <div class="grid2">
          <div><label>⏱ حذف فایل بعد از (ثانیه)</label>
            <input name="del_sec" type="number" min="5" value="<?= (int)$bs['delete_seconds'] ?>"></div>
          <div><label>گزینه‌ها</label>
            <label style="font-weight:500"><input type="checkbox" name="force_join" style="width:auto"
              <?= !empty($bs['force_join']) ? 'checked' : '' ?>> 🔒 عضویت اجباری</label>
            <label style="font-weight:500"><input type="checkbox" name="protect" style="width:auto"
              <?= !empty($bs['protect_content']) ? 'checked' : '' ?>> 🛡 جلوگیری از فوروارد</label>
            <label style="font-weight:500"><input type="checkbox" name="inline_wait" style="width:auto"
              <?= !empty($bs['inline_wait']) ? 'checked' : '' ?>> ⏳ انتظار درون‌خطی (دقت بیشتر حذف)</label></div>
        </div>

        <h3 style="font-size:13.5px;margin:18px 0 9px">📢 کانال‌های این ربات</h3>
        <div class="note">هیچ‌کدام را نزنید = کانال‌های عمومی اعمال می‌شود. اگر بزنید، فقط همان‌ها برای این ربات چک می‌شوند.</div>
        <?php $applicable = count(Channels::forBot($b['id']));
        if (!empty($bs['force_join']) && $applicable === 0): ?>
          <div class="flash warn" style="margin:0 0 10px">
            ⚠️ عضویت اجباری روشن است ولی <b>هیچ کانالی</b> برای این ربات اعمال نمی‌شود —
            یعنی فایل‌ها بدون قفل تحویل داده می‌شوند. یک کانال انتخاب کنید یا کانالی بسازید که برای «همه» باشد.
          </div>
        <?php endif; ?>
        <?php if (!$channels): ?><div class="muted">کانالی ثبت نشده.</div>
        <?php else: ?>
        <div style="display:flex;flex-wrap:wrap;gap:9px;margin-bottom:6px">
          <?php foreach ($channels as $cid => $ch): ?>
            <label style="font-weight:500;background:#edf2f7;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="bot_channels[]" value="<?= h($cid) ?>" style="width:auto"
                <?= in_array($cid, $myChans, true) ? 'checked' : '' ?>> <?= h($ch['title']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h3 style="font-size:13.5px;margin:18px 0 9px">🎨 دکمه‌های شیشه‌ای این ربات</h3>
        <div style="display:grid;grid-template-columns:44px 1fr 96px 90px 52px 52px 40px;gap:7px;
                    font-size:11px;color:#718096;font-weight:700;padding:0 10px 6px">
          <div>ایموجی</div><div>متن</div><div>رنگ</div><div>✨ پریمیوم</div><div>ردیف</div><div>ترتیب</div><div>فعال</div>
        </div>
        <?php foreach ($bs['buttons'] as $bk => $bb): ?>
        <div class="brow" style="grid-template-columns:44px 1fr 96px 90px 52px 52px 40px">
          <input name="b_emoji_<?= h($bk) ?>" value="<?= h($bb['emoji'] ?? '') ?>" style="text-align:center">
          <input name="b_text_<?= h($bk) ?>" value="<?= h($bb['text']) ?>">
          <select name="b_color_<?= h($bk) ?>">
            <?php foreach (styleMap() as $sk => $sl): ?>
              <option value="<?= h($sk) ?>" <?= ($bb['color'] ?? '') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
            <?php endforeach; ?></select>
          <input name="b_icon_<?= h($bk) ?>" value="<?= h($bb['icon'] ?? '') ?>" placeholder="کد" style="direction:ltr">
          <input name="b_row_<?= h($bk) ?>" type="number" min="1" value="<?= (int)($bb['row'] ?? 1) ?>">
          <input name="b_order_<?= h($bk) ?>" type="number" min="1" value="<?= (int)($bb['order'] ?? 1) ?>">
          <input type="checkbox" name="b_on_<?= h($bk) ?>" <?= !empty($bb['on']) ? 'checked' : '' ?> style="width:auto">
        </div>
        <?php endforeach; ?>

        <h3 style="font-size:13.5px;margin:18px 0 9px">💠 رنگ دکمه‌های داخل ربات</h3>
        <div class="grid2">
          <?php
          $bgLabels = ['join'=>'📢 کانال عضویت','joined'=>'✅ عضو شدم','nav'=>'◀️ بازگشت',
                       'cancel'=>'↩️ انصراف','upload'=>'📤 آپلود','info'=>'ℹ️ اطلاعات'];
          foreach ($bgLabels as $role => $lbl): ?>
            <div><label><?= $lbl ?></label><select name="bg_<?= h($role) ?>">
              <?php foreach (styleMap() as $sk => $sl): ?>
                <option value="<?= h($sk) ?>" <?= ($bs['glass_colors'][$role] ?? 'none') === $sk ? 'selected' : '' ?>><?= h($sl) ?></option>
              <?php endforeach; ?></select></div>
          <?php endforeach; ?>
        </div>

        <h3 style="font-size:13.5px;margin:18px 0 9px">📝 متن‌های این ربات</h3>
        <div class="note">
          برای گذاشتن <b>ایموجی پریمیوم</b> داخل متن‌ها، از نوار ابزار ✨ استفاده کنید —
          کد را با دستور <code>/emoji</code> در ربات مادر بگیرید.
        </div>
        <div class="tgrid">
          <?php
          $tLabels = ['menu_text'=>'🤖 منوی پنل — {links} {sec} {join} {bot}',
                      'start_text'=>'👋 پیام شروع — {name}',
                      'join_text'=>'🔒 متن عضویت اجباری',
                      'warn_text'=>'⚠️ هشدار حذف — {sec}',
                      'deleted_text'=>'🗑 بعد از حذف',
                      'expired_text'=>'❌ لینک نامعتبر'];
          foreach ($tLabels as $tk => $tl):
            $fid = 'bt_' . $b['id'] . '_' . $tk; ?>
            <div><label><?= h($tl) ?></label>
              <div class="tbar">
                <button type="button" onclick="wrapSel('<?= $fid ?>','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
                <button type="button" onclick="wrapSel('<?= $fid ?>','<blockquote expandable>','</blockquote>')">❝ بازشو</button>
                <button type="button" onclick="wrapSel('<?= $fid ?>','<b>','</b>')"><b>پررنگ</b></button>
                <button type="button" onclick="premEmoji('<?= $fid ?>')">✨ ایموجی پریمیوم</button>
              </div>
              <textarea id="<?= $fid ?>" name="<?= h($tk) ?>"><?= h($bs[$tk] ?? '') ?></textarea></div>
          <?php endforeach; ?>
          <div><label>متن دکمه «عضو شدم»</label><input name="joined_btn" value="<?= h($bs['joined_btn']) ?>"></div>
        </div>

        <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
          <button class="btn g">ذخیره این ربات</button>
          <label style="font-weight:500;background:#fef5e7;padding:9px 13px;border-radius:9px">
            <input type="checkbox" name="apply_all" style="width:auto"> 📋 اعمال روی <b>همه</b> ربات‌ها
          </label>
        </div>
      </form>

      <h3 style="font-size:13.5px;margin:18px 0 9px">👑 مدیران این ربات</h3>
      <div class="note">مدیر می‌تواند در این ربات فایل آپلود کند و لینک بسازد — ولی به پنل وب و ربات مادر دسترسی ندارد.</div>
      <?php $ad = $b['admins'] ?? []; ?>
      <?php if ($ad): ?>
      <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:10px">
        <?php foreach ($ad as $au): ?>
          <span class="chip"><?= h(uLabel($users, $au)) ?> <code><?= h($au) ?></code>
            <form method="post" class="inline" onsubmit="return confirm('حذف مدیر؟')">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
              <input type="hidden" name="action" value="del_bot_admin"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
              <input type="hidden" name="user_id" value="<?= h($au) ?>"><button title="حذف">✕</button>
            </form></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
        <input type="hidden" name="action" value="add_bot_admin"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
        <div style="flex:1;min-width:170px"><label>آیدی عددی مدیر جدید</label>
          <input name="user_id" type="number" placeholder="کاربر با /id آیدیش را می‌گیرد" style="direction:ltr"></div>
        <button class="btn b">افزودن مدیر</button>
      </form>

      <div style="margin-top:16px;padding-top:14px;border-top:1px solid #edf2f7">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
          <input type="hidden" name="action" value="bot_webhook"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
          <button class="btn b sm">تنظیم دوباره وبهوک</button>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('حذف این ربات؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
          <input type="hidden" name="action" value="del_bot"><input type="hidden" name="id" value="<?= h($b['id']) ?>">
          <button class="btn r sm">حذف ربات</button>
        </form>
      </div>

      <?php if ($links): ?>
      <div style="margin-top:16px"><div class="scroll"><table>
        <tr><th>عنوان</th><th>لینک</th><th>👁</th><th>📥</th><th></th></tr>
        <?php foreach (array_slice(array_reverse($links, true), 0, 30, true) as $code => $l): ?>
        <tr><td><?= h($l['title'] ?: count($l['files']) . ' فایل') ?></td>
          <td><code><?= h(Links::url($b['id'], $code)) ?></code></td>
          <td><?= (int)$l['clicks'] ?></td><td><?= (int)$l['delivered'] ?></td>
          <td><form method="post" onsubmit="return confirm('حذف لینک؟')">
            <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="bots">
            <input type="hidden" name="action" value="del_link"><input type="hidden" name="bot" value="<?= h($b['id']) ?>">
            <input type="hidden" name="code" value="<?= h($code) ?>">
            <button class="btn r sm">حذف</button></form></td></tr>
        <?php endforeach; ?>
      </table></div></div>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>

<?php // ================= کانال‌ها ================= ?>
<?php elseif ($tab === 'channels'): ?>
  <?php
    $fresh = []; $lost = [];
    foreach (Channels::all() as $c9) {
      if (isset($c9['seen']) && empty($c9['seen'])) $fresh[] = $c9;
      if (!empty($c9['lost_admin'])) $lost[] = $c9;
    }
  ?>
  <?php if ($fresh || $lost): ?>
  <div class="card"><h2>🆕 تغییرات تازه</h2><div class="body">
    <div class="note">
      ربات دیگر برای این‌ها پیام نمی‌فرستد تا شلوغ نشود — همه‌شان اینجا نشان داده می‌شوند.
    </div>
    <table style="margin-top:12px">
      <tr><th>کانال</th><th>آیدی</th><th>وضعیت</th></tr>
      <?php foreach ($fresh as $c9): ?>
        <tr><td><?= h($c9['title']) ?></td><td><code><?= h($c9['chat_id']) ?></code></td>
            <td><span class="badge green">تازه ثبت شد</span>
                <?= !empty($c9['added_at']) ? '<span class="muted"> · ' . h($c9['added_at']) . '</span>' : '' ?></td></tr>
      <?php endforeach; ?>
      <?php foreach ($lost as $c9): ?>
        <tr><td><?= h($c9['title']) ?></td><td><code><?= h($c9['chat_id']) ?></code></td>
            <td><span class="badge">ربات دیگر ادمین نیست</span>
                <span class="muted"> · <?= h($c9['lost_admin']) ?></span></td></tr>
      <?php endforeach; ?>
    </table>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="seen_channels">
      <button class="btn">دیدم، پاک کن</button>
    </form>
  </div></div>
  <?php endif; ?>

  <div class="card"><h2>📢 افزودن کانال عضویت اجباری</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="channels">
      <input type="hidden" name="action" value="add_channel">
      <div class="grid2">
        <div><label>آیدی یا یوزرنیم کانال</label><input name="chat_id" required placeholder="@mychannel یا -1001234567890" style="direction:ltr"></div>
        <div><label>لینک عضویت (اختیاری)</label><input name="url" placeholder="https://t.me/..." style="direction:ltr"></div>
      </div>
      <?php if ($bots): ?>
      <div style="margin-top:12px"><label>فقط برای این ربات‌ها (خالی = همه)</label>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($bots as $bb): ?>
            <label style="font-weight:500;background:#edf2f7;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="bots[]" value="<?= h($bb['id']) ?>" style="width:auto"> @<?= h($bb['username']) ?>
            </label>
          <?php endforeach; ?>
        </div></div>
      <?php endif; ?>
      <p class="muted" style="margin-top:10px;line-height:1.9">
        ✅ فقط <b>ربات مادر</b> باید در این کانال <b>ادمین</b> باشد.
        ربات‌های اپلودر لازم نیست عضو یا ادمین کانال باشند — بررسی عضویت همیشه با توکن ربات مادر انجام می‌شود.<br>
        اگر ربات مادر را در کانالی ادمین کنید، کانال <b>خودکار</b> همین‌جا ثبت می‌شود.
      </p>
      <div style="margin-top:12px"><button class="btn g">افزودن کانال</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🩺 بررسی سلامت</h2><div class="body">
    <p class="muted" style="margin-bottom:10px;line-height:1.9">
      اگر <b>ربات مادر</b> در کانالی ادمین نباشد، <b>قفل بسته می‌ماند</b> و هیچ‌کس فایل نمی‌گیرد.
      با این دکمه دسترسی ربات مادر به همه کانال‌ها را بررسی کنید.
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
      <tr><th>عنوان</th><th>آیدی</th><th>ربات‌ها</th><th>وضعیت</th><th>اقدام</th></tr>
      <?php foreach ($channels as $ch): ?>
      <tr><td><?= h($ch['title']) ?></td><td><code><?= h($ch['chat_id']) ?></code></td>
        <td><?php if (empty($ch['bots'])): ?><span class="badge gray">همه</span><?php else:
          foreach ($ch['bots'] as $bid2) { $bb2 = BotManager::get($bid2);
            echo '<span class="badge green">@' . h($bb2['username'] ?? $bid2) . '</span> '; } endif; ?></td>
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

<?php // ================= سفارش ممبر (کمپین) ================= ?>
<?php elseif ($tab === 'campaigns'): ?>
  <div class="card"><h2>⚡️ خودکار است</h2><div class="body">
    <div class="note">
      <b>لازم نیست دستی چیزی بسازید.</b> به‌محض اینکه سفارش ممبری پرداخت و تایید شود،
      کانال مشتری <b>خودکار</b> در بخش عضویت اجباری همه ربات‌های اپلودر قفل می‌شود،
      و به‌محض رسیدن به تعداد سفارش <b>خودکار</b> برداشته می‌شود.<br><br>
      فرم پایین فقط برای موارد دستی است — مثلا وقتی مشتری خارج از ربات سفارش داده،
      یا ربات موقع سفارش در کانال ادمین نبوده.
    </div>
  </div></div>

  <div class="card"><h2>🎯 ثبت دستی سفارش ممبر</h2><div class="body">
    <div class="note">
      کانال مشتری تا رسیدن به تعداد سفارش، در بخش <b>عضویت اجباری</b> قفل می‌شود —
      هم در ربات‌های اپلودر خودمان، هم در ربات‌های شریک.
      به‌محض پر شدن سهمیه، کانال خودکار از قفل خارج می‌شود.<br>
      ⚠️ <b>ربات مادر</b> باید در کانال مشتری ادمین باشد تا بتواند عضویت را بشمارد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
      <input type="hidden" name="action" value="add_campaign">
      <div class="grid2">
        <div><label>آیدی یا یوزرنیم کانال مشتری</label>
          <input name="chat_id" required placeholder="@customer یا -100..." style="direction:ltr"></div>
        <div><label>تعداد ممبر سفارش</label><input name="target" type="number" min="1" required placeholder="1000"></div>
        <div><label>عنوان (خالی = از خود کانال)</label><input name="title" placeholder="کانال مشتری"></div>
        <div><label>لینک عضویت (خالی = خودکار)</label><input name="url" placeholder="https://t.me/..." style="direction:ltr"></div>
      </div>
      <div style="margin-top:12px"><label>یادداشت</label><input name="note" placeholder="سفارش آقای X — فاکتور ۱۲۳"></div>

      <div style="margin-top:12px"><label>روی کدام ربات‌های اپلودر؟ (خالی = همه)</label>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($bots as $bb): ?>
            <label style="font-weight:500;background:#edf2f7;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="bots[]" value="<?= h($bb['id']) ?>" style="width:auto"> @<?= h($bb['username']) ?></label>
          <?php endforeach; ?>
          <?php if (!$bots): ?><span class="muted">رباتی ندارید.</span><?php endif; ?>
        </div></div>

      <div style="margin-top:12px"><label>روی کدام ربات‌های شریک؟ (خالی = همه)</label>
        <div style="display:flex;flex-wrap:wrap;gap:9px">
          <?php foreach ($partners as $pp): ?>
            <label style="font-weight:500;background:#edf2f7;padding:7px 12px;border-radius:9px">
              <input type="checkbox" name="partners[]" value="<?= h($pp['id']) ?>" style="width:auto"> <?= h($pp['name']) ?></label>
          <?php endforeach; ?>
          <?php if (!$partners): ?><span class="muted">شریکی ندارید.</span><?php endif; ?>
        </div></div>

      <div style="margin-top:14px"><button class="btn g">ثبت سفارش</button></div>
    </form>
  </div></div>

  <?php foreach ($campaigns as $c):
    $done = Campaign::isDone($c);
    $cnt  = count($c['joined']);
    $pct  = ((int)$c['target']) > 0 ? min(100, round($cnt * 100 / (int)$c['target'])) : 0; ?>
  <div class="card">
    <h2>
      <?= $done ? '✅' : (!empty($c['active']) ? '🎯' : '⏸') ?> <?= h($c['title']) ?>
      — <?= $cnt ?> / <?= (int)$c['target'] ?>
      <?= $done ? '<span class="badge green">تکمیل</span>' : '' ?>
    </h2>
    <div class="body">
      <div class="bar"><div class="bar-in" style="width:<?= $pct ?>%"></div></div>
      <div class="muted" style="margin:6px 0 14px">
        <?= $pct ?>% · باقی‌مانده: <b><?= h(Campaign::remaining($c)) ?></b> ·
        <code><?= h($c['chat_id']) ?></code>
        <?= !empty($c['note']) ? ' · ' . h($c['note']) : '' ?>
        <?= !empty($c['done_at']) ? ' · تکمیل در ' . h($c['done_at']) : '' ?>
      </div>
      <?php if (!empty($c['order_id'])): ?>
        <div class="note" style="margin-bottom:12px">
          ⚡️ خودکار از سفارش <code><?= h($c['order_id']) ?></code>
          <?php if ((int)($c['per_day'] ?? 0) > 0): ?>
            · سقف روزانه <b><?= number_format((int)$c['per_day']) ?></b> نفر
            (امروز: <?= (($c['day'] ?? '') === substr(date('Y-m-d H:i:s'), 0, 10))
                      ? (int)($c['day_count'] ?? 0) : 0 ?>)
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($c['paused_reason'])): ?>
        <div class="note" style="margin-bottom:12px;background:#fff4f4;border-color:#f5c2c7">
          ⏸ <b>موقتا متوقف شد</b> — ربات نمی‌تواند عضویت را بررسی کند:
          <code><?= h($c['paused_reason']) ?></code><br>
          ربات مادر را دوباره در این کانال ادمین کنید، بعد از دکمه پایین روشنش کنید.
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
        <input type="hidden" name="action" value="edit_campaign"><input type="hidden" name="id" value="<?= h($c['id']) ?>">
        <div class="grid2">
          <div><label>عنوان</label><input name="title" value="<?= h($c['title']) ?>"></div>
          <div><label>آیدی کانال<?= trim((string)($c['chat_id'] ?? '')) === '' ? ' ⚠️ خالی است' : '' ?></label>
            <input name="chat_id" value="<?= h($c['chat_id'] ?? '') ?>" placeholder="@customer یا -100..." style="direction:ltr"></div>
          <div><label>تعداد سفارش</label><input name="target" type="number" min="1" value="<?= (int)$c['target'] ?>"></div>
          <div><label>لینک عضویت</label><input name="url" value="<?= h($c['url']) ?>" style="direction:ltr"></div>
        </div>
        <div style="margin-top:10px"><label>ربات‌های اپلودر (خالی = همه)</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($bots as $bb): ?>
              <label style="font-weight:500;background:#edf2f7;padding:6px 11px;border-radius:8px">
                <input type="checkbox" name="bots[]" value="<?= h($bb['id']) ?>" style="width:auto"
                  <?= in_array($bb['id'], $c['bots'] ?? [], true) ? 'checked' : '' ?>> @<?= h($bb['username']) ?></label>
            <?php endforeach; ?>
          </div></div>
        <div style="margin-top:10px"><label>ربات‌های شریک (خالی = همه)</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($partners as $pp): ?>
              <label style="font-weight:500;background:#edf2f7;padding:6px 11px;border-radius:8px">
                <input type="checkbox" name="partners[]" value="<?= h($pp['id']) ?>" style="width:auto"
                  <?= in_array($pp['id'], $c['partners'] ?? [], true) ? 'checked' : '' ?>> <?= h($pp['name']) ?></label>
            <?php endforeach; ?>
          </div></div>
        <div style="margin-top:12px"><button class="btn g">ذخیره</button></div>
      </form>

      <div style="margin-top:12px;padding-top:12px;border-top:1px solid #edf2f7">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
          <input type="hidden" name="action" value="toggle_campaign"><input type="hidden" name="id" value="<?= h($c['id']) ?>">
          <button class="btn ghost sm"><?= !empty($c['active']) ? 'توقف' : 'ادامه' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('حذف کمپین؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="campaigns">
          <input type="hidden" name="action" value="del_campaign"><input type="hidden" name="id" value="<?= h($c['id']) ?>">
          <button class="btn r sm">حذف</button></form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$campaigns): ?><div class="card"><div class="body"><div class="empty">هنوز سفارشی ثبت نشده.</div></div></div><?php endif; ?>

<?php // ================= ربات‌های شریک ================= ?>
<?php elseif ($tab === 'partners'):
  $apiBase = baseUrl() . '/bot_master_membership.php'; ?>
  <div class="card"><h2>🤝 افزودن ربات شریک</h2><div class="body">
    <div class="note">
      برای رباتی که <b>سورس خودش را دارد</b> و می‌خواهد فقط از بخش <b>عضویت اجباری</b> ما استفاده کند.
      توکن رباتشان را نمی‌گیریم — فقط یک کلید API می‌دهیم که با آن بپرسند
      «این کاربر باید عضو کدام کانال‌ها شود؟».
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
      <input type="hidden" name="action" value="add_partner">
      <div class="grid2">
        <div><label>نام شریک</label><input name="name" required placeholder="ربات فلانی"></div>
        <div><label>یوزرنیم رباتشان (اختیاری)</label><input name="bot_username" placeholder="their_bot" style="direction:ltr"></div>
        <div><label>آیدی عددی صاحبش (اختیاری)</label><input name="owner_id" type="number" style="direction:ltr"></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ساخت کلید API</button></div>
    </form>
  </div></div>

  <?php foreach ($partners as $pt): ?>
  <div class="card">
    <h2><?= !empty($pt['active']) ? '🟢' : '🔴' ?> <?= h($pt['name']) ?>
      <?= $pt['bot_username'] ? '— @' . h($pt['bot_username']) : '' ?></h2>
    <div class="body">
      <div class="grid2" style="margin-bottom:12px">
        <div><label>کلید API</label>
          <input value="<?= h($pt['key']) ?>" readonly onclick="this.select()" style="direction:ltr;background:#f7fafc"></div>
        <div><label>آمار</label>
          <div class="muted" style="padding-top:8px">
            بررسی: <b><?= (int)$pt['checks'] ?></b> · موفق: <b><?= (int)$pt['passed'] ?></b>
            <?= $pt['last_seen'] ? ' · آخرین: ' . h($pt['last_seen']) : '' ?>
          </div></div>
      </div>

      <details>
        <summary style="cursor:pointer;font-weight:700;font-size:13.5px;margin-bottom:8px">
          📋 کدی که باید به شریک بدهید (کپی کنید)</summary>

        <div class="muted" style="margin:10px 0 6px"><b>۱) آدرس‌ها</b></div>
        <pre class="code">بررسی عضویت:
POST <?= h($apiBase) ?>?api=check
     key=<?= h($pt['key']) ?>&user_id=123456

فهرست کانال‌ها:
POST <?= h($apiBase) ?>?api=channels
     key=<?= h($pt['key']) ?></pre>

        <div class="muted" style="margin:12px 0 6px"><b>۲) پاسخ</b></div>
        <pre class="code">{"ok":true,"allowed":false,
 "missing":[{"title":"کانال ما","url":"https://t.me/..."}],
 "message":"برای ادامه، در کانال‌های زیر عضو شوید."}</pre>

        <div class="muted" style="margin:12px 0 6px"><b>۳) نمونه PHP</b></div>
        <pre class="code">function joinGate($userId) {
    $ch = curl_init('<?= h($apiBase) ?>?api=check');
    curl_setopt_array($ch, [
        CURLOPT_POST =&gt; true,
        CURLOPT_POSTFIELDS =&gt; http_build_query([
            'key' =&gt; '<?= h($pt['key']) ?>',
            'user_id' =&gt; $userId,
        ]),
        CURLOPT_RETURNTRANSFER =&gt; true,
        CURLOPT_TIMEOUT =&gt; 8,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    // اگر سرویس در دسترس نبود، قفل را باز نکنید
    if (empty($r['ok'])) return ['allowed' =&gt; false, 'missing' =&gt; []];
    return $r;
}

$gate = joinGate($userId);
if (!$gate['allowed']) {
    $rows = [];
    foreach ($gate['missing'] as $m)
        $rows[] = [['text' =&gt; '📢 ' . $m['title'], 'url' =&gt; $m['url']]];
    $rows[] = [['text' =&gt; '✅ عضو شدم', 'callback_data' =&gt; 'recheck']];
    // پیام «اول عضو شوید» را با این دکمه‌ها بفرستید
} else {
    // فایل/محتوا را بفرستید
}</pre>

        <div class="muted" style="margin:12px 0 6px"><b>۴) نمونه Python</b></div>
        <pre class="code">import requests

def join_gate(user_id):
    try:
        r = requests.post('<?= h($apiBase) ?>?api=check',
                          data={'key': '<?= h($pt['key']) ?>',
                                'user_id': user_id}, timeout=8).json()
    except Exception:
        return {'allowed': False, 'missing': []}
    if not r.get('ok'):
        return {'allowed': False, 'missing': []}
    return r</pre>
      </details>

      <div style="margin-top:14px;padding-top:12px;border-top:1px solid #edf2f7">
        <form method="post" class="inline">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
          <input type="hidden" name="action" value="toggle_partner"><input type="hidden" name="id" value="<?= h($pt['id']) ?>">
          <button class="btn ghost sm"><?= !empty($pt['active']) ? 'غیرفعال کن' : 'فعال کن' ?></button></form>
        <form method="post" class="inline" onsubmit="return confirm('کلید قبلی از کار می‌افتد. مطمئنید؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
          <input type="hidden" name="action" value="rotate_key"><input type="hidden" name="id" value="<?= h($pt['id']) ?>">
          <button class="btn b sm">🔄 تعویض کلید</button></form>
        <form method="post" class="inline" onsubmit="return confirm('حذف شریک؟')">
          <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="partners">
          <input type="hidden" name="action" value="del_partner"><input type="hidden" name="id" value="<?= h($pt['id']) ?>">
          <button class="btn r sm">حذف</button></form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$partners): ?><div class="card"><div class="body"><div class="empty">هنوز شریکی اضافه نکرده‌اید.</div></div></div><?php endif; ?>

<?php // ================= رفرال ================= ?>
<?php elseif ($tab === 'referral'):
  $refCount = []; $refEarn = [];
  foreach ($users as $u) {
      if (!empty($u['referrer'])) $refCount[(string)$u['referrer']] = ($refCount[(string)$u['referrer']] ?? 0) + 1;
      if (!empty($u['ref_earned'])) $refEarn[(string)$u['telegram_id']] = (float)$u['ref_earned'];
  }
  arsort($refCount);
  $totalEarn = array_sum($refEarn);
  $withRef = count(array_filter($users, fn($u) => !empty($u['referrer'])));
?>
  <div class="stats">
    <div class="stat"><div class="n"><?= count($refCount) ?></div><div class="l">👤 معرف فعال</div></div>
    <div class="stat"><div class="n"><?= $withRef ?></div><div class="l">👥 کاربر معرفی‌شده</div></div>
    <div class="stat"><div class="n"><?= fmtNum($totalEarn) ?></div><div class="l">💵 پورسانت پرداختی</div></div>
    <div class="stat"><div class="n"><?= h($C['referral']['percent']) ?>%</div><div class="l">📈 درصد فعلی</div></div>
  </div>

  <div class="card"><h2>⚙️ تنظیم رفرال</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="referral">
      <input type="hidden" name="action" value="save_settings">
      <input type="hidden" name="usdt" value="<?= h($C['wallets']['usdt']) ?>">
      <input type="hidden" name="trx" value="<?= h($C['wallets']['trx']) ?>">
      <input type="hidden" name="card" value="<?= h($C['wallets']['card']) ?>">
      <input type="hidden" name="card_name" value="<?= h($C['wallets']['card_name']) ?>">
      <input type="hidden" name="del_sec" value="<?= (int)$C['uploader']['delete_seconds'] ?>">
      <?php if (!empty($C['uploader']['force_join'])): ?><input type="hidden" name="force_join" value="1"><?php endif; ?>
      <?php if (!empty($C['uploader']['protect_content'])): ?><input type="hidden" name="protect" value="1"><?php endif; ?>
      <div class="grid2">
        <div><label>درصد پورسانت از هر خرید</label>
          <input name="ref_percent" type="number" min="0" max="100" step="0.5" value="<?= h($C['referral']['percent']) ?>"></div>
        <div><label>&nbsp;</label><label style="font-weight:500">
          <input type="checkbox" name="ref_on" style="width:auto" <?= !empty($C['referral']['on']) ? 'checked' : '' ?>>
          سیستم معرفی فعال باشد</label></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🏆 برترین معرف‌ها</h2><div class="body">
    <?php if (!$refCount): ?><div class="empty">هنوز کسی زیرمجموعه نگرفته.</div>
    <?php else: ?><div class="scroll"><table>
      <tr><th>#</th><th>معرف</th><th>آیدی</th><th>تعداد زیرمجموعه</th><th>پورسانت دریافتی</th><th>موجودی</th></tr>
      <?php $i = 1; foreach (array_slice($refCount, 0, 50, true) as $rid => $cnt): ?>
      <tr><td><?= $i++ ?></td>
        <td><?= h(uLabel($users, $rid)) ?></td>
        <td><code><?= h($rid) ?></code></td>
        <td><b><?= $cnt ?></b></td>
        <td><?= h(fmtNum($refEarn[$rid] ?? 0)) ?></td>
        <td><?= h(fmtNum($users[$rid]['balance'] ?? 0)) ?></td></tr>
      <?php endforeach; ?>
    </table></div><?php endif; ?>
  </div></div>

  <div class="card"><h2>📢 کانال اعلام فروش</h2><div class="body">
    <div class="note">
      هر خرید موفق به‌صورت خودکار در یک کانال جدا اعلام می‌شود — با
      <b>کد خرید</b>، <b>مبلغ</b> و <b>تعداد ممبر فروخته‌شده</b>.
      ربات مادر باید در آن کانال ادمین باشد.
    </div>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="referral">
      <input type="hidden" name="action" value="save_sales">
      <div class="grid2">
        <div><label>آیدی کانال اعلام فروش</label>
          <input name="sales_chat" value="<?= h($C['sales']['chat_id']) ?>" placeholder="@saleschannel یا -100..." style="direction:ltr"></div>
        <div><label>گزینه‌ها</label>
          <label style="font-weight:500"><input type="checkbox" name="sales_on" style="width:auto"
            <?= !empty($C['sales']['on']) ? 'checked' : '' ?>> اعلام فروش فعال باشد</label>
          <label style="font-weight:500"><input type="checkbox" name="sales_user" style="width:auto"
            <?= !empty($C['sales']['show_user']) ? 'checked' : '' ?>> نمایش نام خریدار</label></div>
      </div>
      <div style="margin-top:12px">
        <label>قالب پیام</label>
        <div class="tbar">
          <button type="button" onclick="wrapSel('sales_tpl','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
          <button type="button" onclick="wrapSel('sales_tpl','<blockquote expandable>','</blockquote>')">❝ بازشو</button>
          <button type="button" onclick="wrapSel('sales_tpl','<b>','</b>')"><b>پررنگ</b></button>
          <button type="button" onclick="wrapSel('sales_tpl','<code>','</code>')">&lt;/&gt; کد</button>
        <button type="button" onclick="premEmoji('sales_tpl')">✨ ایموجی پریمیوم</button>
        </div>
        <textarea id="sales_tpl" name="sales_tpl" style="min-height:140px"><?= h($C['sales']['template']) ?></textarea>
        <div class="muted" style="margin-top:6px;line-height:1.9">
          متغیرها: <code>{product}</code> <code>{code}</code> <code>{amount}</code> <code>{currency}</code>
          <code>{count}</code> <code>{limit}</code> <code>{remaining}</code> <code>{limit_part}</code>
          <code>{user}</code> <code>{user_id}</code> <code>{date}</code>
        </div>
      </div>
      <div style="margin-top:14px">
        <button class="btn g">ذخیره</button>
      </div>
    </form>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="referral">
      <input type="hidden" name="action" value="test_sales">
      <button class="btn b sm">ارسال پیام آزمایشی به کانال</button>
    </form>
  </div></div>

<?php // ================= کاربران ================= ?>
<?php elseif ($tab === 'users'): ?>
  <div class="card"><h2>📢 پیام همگانی</h2><div class="body">
    <form method="post" onsubmit="return confirm('ارسال به همه کاربران؟')">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
      <input type="hidden" name="action" value="broadcast">
      <div class="tbar">
        <button type="button" onclick="wrapSel('bc_master','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
        <button type="button" onclick="wrapSel('bc_master','<blockquote expandable>','</blockquote>')">❝ بازشو</button>
        <button type="button" onclick="wrapSel('bc_master','<b>','</b>')"><b>پررنگ</b></button>
        <button type="button" onclick="wrapSel('bc_master','<tg-spoiler>','</tg-spoiler>')">🫥 اسپویلر</button>
        <button type="button" onclick="premEmoji('bc_master')">✨ ایموجی پریمیوم</button>
      </div>
      <textarea id="bc_master" name="text" placeholder="متن پیام… (تگ HTML تلگرام مجاز است)" required></textarea>
      <div style="margin-top:12px"><button class="btn b">ارسال به <?= count($users) ?> کاربر</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🤖 پیام همگانی به ربات‌های زیرمجموعه</h2><div class="body">
    <div class="note">
      این پیام با توکن <b>خود ربات اپلودر</b> فرستاده می‌شود، پس به کسانی هم می‌رسد
      که فقط با ربات فرعی چت کرده‌اند و ربات مادر را استارت نکرده‌اند.
    </div>
    <?php if (!$bots): ?><div class="empty">هنوز ربات اپلودری ندارید.</div>
    <?php else: ?>
    <form method="post" onsubmit="return confirm('ارسال به کاربران ربات‌های انتخاب‌شده؟')">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="users">
      <input type="hidden" name="action" value="broadcast_child">
      <label>ربات‌های مقصد (هیچ‌کدام = همه)</label>
      <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:12px">
        <?php foreach ($bots as $b): ?>
          <label style="font-weight:500;background:#edf2f7;padding:7px 12px;border-radius:9px">
            <input type="checkbox" name="bots[]" value="<?= h($b['id']) ?>" style="width:auto">
            @<?= h($b['username']) ?> (<?= count(load('bots/' . $b['id'] . '/users')) ?>)
          </label>
        <?php endforeach; ?>
      </div>
      <div class="tbar">
        <button type="button" onclick="wrapSel('bc_child','<blockquote>','</blockquote>')">❝ نقل‌قول</button>
        <button type="button" onclick="wrapSel('bc_child','<b>','</b>')"><b>پررنگ</b></button>
        <button type="button" onclick="premEmoji('bc_child')">✨ ایموجی پریمیوم</button>
      </div>
      <textarea id="bc_child" name="text" placeholder="متن پیام…" required></textarea>
      <div style="margin-top:12px"><button class="btn b">ارسال به ربات‌های زیرمجموعه</button></div>
    </form>
    <?php endif; ?>
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
<?php elseif ($tab === 'auto'):   // ================= ⚡ خودکارسازی =================
  $F   = maCfg()['fulfill'] ?? [];
  $W   = axCfg()['wallet'];
  $AU  = axAudit();
  $okN = 0; foreach ($AU as $r) if ($r['ok']) $okN++;
  $hasMn = trim((string)$W['mnemonic']) !== '';
  $bal   = $hasMn ? axWalletBalance() : null;

  // چهار گام تا فروش کاملا خودکار
  $step = [
    'api'    => trim((string)($F['base'] ?? '')) !== '' && trim((string)($F['auth_value'] ?? '')) !== '',
    'wallet' => $hasMn && trim((string)$W['address']) !== '',
    'verify' => (int)$W['verified'] > 0,
    'live'   => !empty($W['on']) && empty($W['dry']) && !empty($F['on']) && !empty($F['auto_pay']),
  ];
  $doneN = 0; foreach ($step as $v) if ($v) $doneN++;
?>

  <div class="card"><h2>⚡ چهار گام تا فروش کاملا خودکار</h2><div class="body">
    <div class="note">
      <b>سفارش خودکار یعنی چه؟</b> مشتری پرداخت می‌کند → ربات خودش سفارش را روی پنل فروش ثبت می‌کند →
      پنل یک <b>تراکنش امضانشده</b> برمی‌گرداند → ربات با ولت شما امضایش می‌کند و می‌فرستد →
      پنل پول را می‌بیند و محصول را تحویل مشتری می‌دهد.<br>
      <b>بدون گام سوم و چهارم، زنجیره وسط راه می‌ایستد و سفارش منتظر شما می‌ماند.</b>
    </div>

    <div class="bar" style="margin-bottom:6px"><div class="bar-in" style="width:<?= (int)($doneN/4*100) ?>%"></div></div>
    <p class="muted" style="margin-bottom:16px"><b><?= $doneN ?></b> از <b>۴</b> گام انجام شده</p>

    <div class="tgrid">
      <?php
      $labels = [
        'api'    => ['۱. اتصال به پنل فروش', 'آدرس پنل و کلید API — تا اینجا نباشد ربات اصلا نمی‌تواند سفارش بدهد'],
        'wallet' => ['۲. ثبت ولت', 'آدرس ولت و عبارت بازیابی ۲۴ کلمه‌ای — بدون این، تراکنش امضا نمی‌شود'],
        'verify' => ['۳. تایید مالکیت', 'ربات کلید عمومی روی زنجیره را با عبارت شما می‌سنجد'],
        'live'   => ['۴. خروج از حالت آزمایشی', 'تا وقتی آزمایشی روشن است، تراکنش ساخته می‌شود ولی فرستاده نمی‌شود'],
      ];
      foreach ($labels as $k => $l): ?>
        <div style="display:flex;gap:11px;align-items:flex-start">
          <span style="font-size:19px;line-height:1.3"><?= $step[$k] ? '✅' : '⚪️' ?></span>
          <div><b style="font-size:13.5px"><?= h($l[0]) ?></b>
            <div class="muted" style="line-height:1.85"><?= h($l[1]) ?></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

  <div class="card"><h2>گام ۱ — اتصال به پنل فروش</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
      <input type="hidden" name="action" value="auto_api">
      <div class="grid2">
        <div><label>آدرس پنل</label>
          <input name="base" dir="ltr" placeholder="https://api.marketapp.org"
                 value="<?= h((string)($F['base'] ?? '')) ?>"></div>
        <div><label>کلید API <?= trim((string)($F['auth_value'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '' ?></label>
          <input name="api_key" dir="ltr" placeholder="<?= trim((string)($F['auth_value'] ?? '')) !== '' ? 'برای تغییر، کلید تازه را بگذارید' : 'کلید را از پنل فروش بگیرید' ?>"></div>
      </div>
      <div style="margin-top:13px;display:flex;gap:18px;flex-wrap:wrap">
        <label class="inline"><input type="checkbox" name="f_on" style="width:auto" <?= !empty($F['on']) ? 'checked' : '' ?>> تحویل خودکار روشن</label>
        <label class="inline"><input type="checkbox" name="f_auto" style="width:auto" <?= !empty($F['auto_pay']) ? 'checked' : '' ?>> بلافاصله بعد از پرداخت</label>
        <label class="inline"><input type="checkbox" name="preset" style="width:auto" checked> تنظیمات آماده marketapp</label>
      </div>
      <p class="muted" style="margin-top:9px">
        «تنظیمات آماده» سه مسیر <code>/recipient/</code> و <code>/price/</code> و <code>/buy/</code> را با
        <code>currency=GRAM</code> می‌نشاند — همان قراردادی که در مستندات پنل هست.
      </p>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <div class="card"><h2>گام ۲ — ولت TON، امضای خودکار تراکنش</h2><div class="body">
    <?php [$cOk, $cWhy] = tonCryptoReady(); if (!$cOk): ?>
      <div class="flash err" style="margin-top:0">
        🔴 <b>این هاست هنوز نمی‌تواند تراکنش امضا کند</b><br><br>
        <?= nl2br($cWhy) ?>
      </div>
      <p class="muted" style="margin-bottom:14px">
        بقیه‌ی ربات — فروش ممبر، مخزن کانفیگ، سفارش دستی، گزارش‌ها — بدون این هم کار می‌کند.
        فقط امضای خودکار تراکنش TON به این افزونه نیاز دارد.
      </p>
    <?php endif; ?>

    <div class="flash warn" style="margin-top:0">
      ⚠️ <b>عبارت بازیابی روی همین هاست ذخیره می‌شود.</b>
      یک ولت <b>جداگانه</b> بسازید و فقط به اندازه‌ی فروش یکی دو روز داخلش پول بگذارید.
      ولت اصلی‌تان هرگز اینجا نیاید. هرکس به هاست دسترسی پیدا کند، به این ولت هم دسترسی دارد.
    </div>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
      <input type="hidden" name="action" value="auto_wallet">

      <div class="grid2">
        <div><label>آدرس ولت</label>
          <input name="w_addr" dir="ltr" placeholder="UQ… یا EQ…" value="<?= h((string)$W['address']) ?>"></div>
        <div><label>نسخه قرارداد ولت</label>
          <select name="w_ver">
            <option value="v4r2" <?= (string)$W['version'] === 'v4r2' ? 'selected' : '' ?>>v4R2 (رایج‌ترین)</option>
            <option value="v3r2" <?= (string)$W['version'] === 'v3r2' ? 'selected' : '' ?>>v3R2 (قدیمی‌تر)</option>
          </select></div>
      </div>

      <div style="margin-top:13px">
        <label>عبارت بازیابی ۲۴ کلمه‌ای
          <?= $hasMn ? '<span class="badge green">ثبت شده</span>' : '<span class="badge red">ثبت نشده</span>' ?></label>
        <textarea name="w_mn" dir="ltr" style="min-height:70px"
          placeholder="<?= $hasMn ? 'ثبت شده — برای تعویض، ۲۴ کلمه‌ی تازه را اینجا بگذارید' : 'word1 word2 word3 … word24' ?>"></textarea>
        <p class="muted">برای امنیت، عبارت ذخیره‌شده هرگز اینجا نمایش داده نمی‌شود.</p>
      </div>

      <div style="margin-top:13px">
        <label>🔒 رمز عبارت بازیابی <span class="muted">(اگر کیف پول موقع ساخت گرفته)</span>
          <?= trim((string)($W['passphrase'] ?? '')) !== '' ? '<span class="badge amber">ثبت شده</span>' : '' ?></label>
        <input name="w_pw" dir="ltr" autocomplete="off"
               placeholder="<?= trim((string)($W['passphrase'] ?? '')) !== '' ? 'ثبت شده — برای پاک کردن یک خط تیره - بگذارید' : 'اگر رمزی نبوده، خالی بگذارید' ?>">
        <p class="muted">⚠️ این با رمز یا پینِ باز کردن برنامه <b>فرق دارد</b>. آن پین فقط قفل خود اپ است
        و کلید ولت را عوض نمی‌کند. این فیلد فقط برای کیف پول‌هایی است که هنگام ساختِ
        عبارت بازیابی یک رمز اضافه می‌گیرند.</p>
      </div>

      <div class="grid2" style="margin-top:13px">
        <div><label>آدرس API شبکه</label>
          <input name="w_api" dir="ltr" value="<?= h((string)$W['api']) ?>"></div>
        <div><label>کلید API شبکه <span class="muted">(اختیاری)</span></label>
          <input name="w_apikey" dir="ltr" placeholder="<?= trim((string)$W['api_key']) !== '' ? 'ثبت شده' : 'toncenter بدون کلید هم کار می‌کند ولی کند' ?>"></div>
      </div>

      <div class="grid2" style="margin-top:13px">
        <div><label>🚧 سقف هر تراکنش (TON)</label>
          <input name="w_max" type="number" step="0.01" min="0.01" value="<?= h((string)$W['max_ton']) ?>"></div>
        <div><label>🚧 سقف مجموع یک روز (TON)</label>
          <input name="w_day" type="number" step="0.01" min="0.01" value="<?= h((string)$W['day_ton']) ?>"></div>
      </div>
      <p class="muted" style="margin-top:7px">
        این دو سقف تنها چیزی هستند که جلوی خالی شدن ولت را می‌گیرند. پایین بگذاریدشان.
        خرج امروز: <b><?= h(((string)$W['day'] === substr(nowStr(),0,10)) ? nanoToTon((string)$W['day_spent']) : '0') ?></b> TON
      </p>

      <div style="margin-top:15px;display:flex;gap:18px;flex-wrap:wrap">
        <label class="inline"><input type="checkbox" name="w_dry" style="width:auto" <?= !empty($W['dry']) ? 'checked' : '' ?>>
          🧪 حالت آزمایشی <span class="muted">(می‌سازد و امضا می‌کند ولی <b>نمی‌فرستد</b>)</span></label>
        <label class="inline"><input type="checkbox" name="w_on" style="width:auto" <?= !empty($W['on']) ? 'checked' : '' ?>>
          روشن</label>
      </div>

      <div style="margin-top:15px;display:flex;gap:9px;flex-wrap:wrap">
        <button class="btn g">ذخیره</button>
      </div>
    </form>

    <div style="margin-top:15px;padding-top:15px;border-top:1px solid #edf2f7;display:flex;gap:9px;flex-wrap:wrap;align-items:center">
      <form method="post" class="inline">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
        <input type="hidden" name="action" value="auto_verify">
        <button class="btn b">🧪 تایید مالکیت و موجودی</button>
      </form>
      <?php if ($hasMn): ?>
      <form method="post" class="inline" onsubmit="return confirm('عبارت بازیابی پاک شود؟ ولت هم خاموش می‌شود.')">
        <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="auto">
        <input type="hidden" name="action" value="auto_wipe">
        <button class="btn r">🗑 پاک کردن عبارت بازیابی</button>
      </form>
<?php else: ?>
  <div class="card"><h2>⚙️ تنظیمات عمومی</h2><div class="body">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="save_settings">
      <input type="hidden" name="adv_scope" value="1">

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

      <h3 style="font-size:14px;margin:18px 0 10px">🧪 تست و نمایش</h3>
      <div class="note">
        <b>حالت تست</b> اجازه می‌دهد سفارش با مبلغ <b>صفر</b> تا آخر برود — بدون پرداخت،
        خودکار تایید می‌شود و کمپین قفل کانالش هم ساخته می‌شود.
        برای امتحان کردن کل مسیر قبل از قیمت‌گذاری. <b>یادتان باشد بعد خاموشش کنید.</b>
      </div>
      <div style="margin-top:10px">
        <label style="font-weight:500"><input type="checkbox" name="test_mode" style="width:auto"
          <?= !empty($C['test_mode']) ? 'checked' : '' ?>> 🧪 حالت تست — سفارش با ۰ ریال مجاز باشد</label>
        <label style="font-weight:500"><input type="checkbox" name="speed_perday" style="width:auto"
          <?= !empty($C['ui']['speed_show_perday']) ? 'checked' : '' ?>>
          🚀 «نفر در روز» روی دکمه سرعت هم نوشته شود</label>
      </div>

      <h3 style="font-size:14px;margin:18px 0 10px">🤖 کار خودکار</h3>
      <div class="note">
        با <b>درگاه پرداخت</b> همه چیز از قبل خودکار است و نیازی به حضور شما نیست.
        گزینه زیر فقط برای <b>رسید کارت به کارت</b> است: رسید که برسد، بدون بررسی تایید می‌شود.
        ⚠️ ریسک دارد — فقط اگر می‌دانید چه می‌کنید.
      </div>
      <div style="margin-top:10px">
        <label style="font-weight:500"><input type="checkbox" name="auto_approve" style="width:auto"
          <?= !empty($C['auto_approve']) ? 'checked' : '' ?>>
          🤖 تایید خودکار رسیدهای کارت به کارت</label>
      </div>
      <div style="margin-top:10px;max-width:320px"><label>🧹 پاک کردن کمپین‌های تمام‌شده بعد از (روز · ۰ = هیچ‌وقت)</label>
        <input name="keep_days" type="number" min="0" value="<?= (int)($C['campaign_keep_days'] ?? 3) ?>"></div>

      <div style="margin-top:16px"><button class="btn g">ذخیره تنظیمات</button></div>
    </form>
  </div></div>

  <?php $G = cfg()['gateway'] ?? []; $J = cfg()['join'] ?? []; ?>

  <div class="card"><h2>💠 درگاه پرداخت خودکار <?= (!empty($G['on']) && trim((string)$G['api_key']) !== '' && trim((string)$G['base_url']) !== '') ? '<span class="badge green">آماده</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      مشتری «افزایش موجودی» می‌زند → ربات از درگاه یک <b>لینک پرداخت + آدرس ولت + مهلت</b> می‌گیرد →
      به‌محض واریز، درگاه به ربات خبر می‌دهد و کیف پول <b>خودکار</b> شارژ می‌شود.
      پول مستقیم به ولت خودتان در پنل درگاه می‌رود و از همان‌جا برداشت می‌کنید.
      <br><br>
      <b>راه‌اندازی:</b> در <a href="https://oxapay.com" target="_blank" rel="noopener">OxaPay</a> یا
      <a href="https://nowpayments.io" target="_blank" rel="noopener">NOWPayments</a> حساب بسازید،
      آدرس ولت خودتان را آنجا ثبت کنید، کلید API (Merchant Key) را بگیرید و اینجا بگذارید.
      بعد در پنل همان سایت، آدرس <b>Callback / IPN</b> را روی آدرس زیر بگذارید.
    </div>

    <?php $cbUrl = trim((string)($G['base_url'] ?? '')) !== '' ? gwCallbackUrl() : ''; ?>
    <?php if ($cbUrl): ?>
      <div class="note" style="margin-top:10px">
        📡 <b>آدرس Callback:</b> <code style="direction:ltr;display:inline-block"><?= h($cbUrl) ?></code>
      </div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="save_gateway">
      <div style="margin-bottom:10px">
        <label style="font-weight:500"><input type="checkbox" name="gw_on" style="width:auto"
          <?= !empty($G['on']) ? 'checked' : '' ?>> درگاه خودکار روشن باشد</label>
      </div>
      <div class="grid2">
        <div><label>سرویس</label><select name="gw_prov">
          <?php foreach (['oxapay'=>'OxaPay','nowpayments'=>'NOWPayments','custom'=>'دلخواه'] as $k2=>$v2): ?>
            <option value="<?= h($k2) ?>" <?= ($G['provider'] ?? 'oxapay') === $k2 ? 'selected' : '' ?>><?= h($v2) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>کلید API (Merchant Key)</label>
          <input name="gw_key" value="<?= h($G['api_key'] ?? '') ?>" style="direction:ltr"></div>
        <div><label>کلید IPN Secret (فقط NOWPayments)</label>
          <input name="gw_ipn" value="<?= h($G['ipn_secret'] ?? '') ?>" style="direction:ltr"></div>
        <div><label>آدرس عمومی فایل ربات</label>
          <input name="gw_base" value="<?= h($G['base_url'] ?? '') ?>" placeholder="https://site.com/bot.php" style="direction:ltr"></div>
        <div><label>ارز</label><input name="gw_coin" value="<?= h($G['coin'] ?? 'USDT') ?>" style="direction:ltr"></div>
        <div><label>شبکه</label><input name="gw_net" value="<?= h($G['network'] ?? '') ?>" placeholder="TRC20" style="direction:ltr"></div>
        <div><label>نرخ: هر ۱ واحد چند تومان؟ (۰ = تبدیل با خود درگاه)</label>
          <input name="gw_rate" value="<?= h((float)($G['rate'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>مهلت هر فاکتور (دقیقه)</label>
          <input name="gw_exp" type="number" min="5" value="<?= (int)($G['expire'] ?? 30) ?>"></div>
        <div><label>حداقل شارژ با درگاه (تومان)</label>
          <input name="gw_min" value="<?= h((float)($G['min'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>آدرس دلخواه (حالت custom)</label>
          <input name="gw_curl" value="<?= h($G['custom_url'] ?? '') ?>" placeholder="https://…?amount={amount}&order={order}&cb={callback}" style="direction:ltr"></div>
      </div>
      <div class="muted" style="margin-top:8px">
        زیر حداقل مبلغ، همان کارت به کارت با رسید استفاده می‌شود. اگر درگاه جواب ندهد هم خودکار به کارت به کارت برمی‌گردد.
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره درگاه</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🔒 عضویت اجباری ربات مادر <?= !empty($J['on']) ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      تا کاربر عضو کانال‌های زیر نشود، نمی‌تواند از ربات فروشگاه استفاده کند.
      ربات مادر باید در هر کانال <b>ادمین</b> باشد. خودتان هیچ‌وقت پشت این قفل نمی‌مانید.
    </div>

    <table style="margin-top:12px">
      <tr><th>#</th><th>کانال</th><th>آیدی</th><th>لینک</th><th></th></tr>
      <?php foreach (($J['channels'] ?? []) as $i => $c2): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= h($c2['title'] ?? '—') ?></td>
          <td><code><?= h($c2['chat_id'] ?? '') ?></code></td>
          <td><?= !empty($c2['url']) ? '<a href="' . h($c2['url']) . '" target="_blank" rel="noopener">باز کردن</a>' : '<span class="muted">—</span>' ?></td>
          <td>
            <form method="post" style="margin:0">
              <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
              <input type="hidden" name="action" value="del_join_channel"><input type="hidden" name="i" value="<?= $i ?>">
              <button class="btn r">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($J['channels'])): ?>
        <tr><td colspan="5" class="muted">هنوز کانالی اضافه نکرده‌اید.</td></tr>
      <?php endif; ?>
    </table>

    <form method="post" style="margin-top:12px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="add_join_channel">
      <div class="grid2">
        <div><label>آیدی کانال</label><input name="chat_id" required placeholder="@mychannel یا -100..." style="direction:ltr"></div>
        <div><label>عنوان (خالی = از خود کانال)</label><input name="title"></div>
        <div><label>لینک عضویت (خالی = خودکار)</label><input name="url" style="direction:ltr"></div>
      </div>
      <div style="margin-top:12px"><button class="btn g">افزودن کانال</button></div>
    </form>

    <form method="post" style="margin-top:16px">
      <input type="hidden" name="csrf" value="<?= h($CSRF) ?>"><input type="hidden" name="tab" value="settings">
      <input type="hidden" name="action" value="save_join">
      <div style="margin-bottom:10px">
        <label style="font-weight:500"><input type="checkbox" name="jn_on" style="width:auto"
          <?= !empty($J['on']) ? 'checked' : '' ?>> قفل عضویت روشن باشد</label>
      </div>
      <label>متن قفل</label>
      <textarea name="jn_text" rows="3" style="direction:rtl"><?= h($J['text'] ?? '') ?></textarea>
      <div style="margin-top:10px"><label>متن دکمه</label>
        <input name="jn_btn" value="<?= h($J['btn']['text'] ?? 'عضو شدم') ?>"></div>
      <div class="muted" style="margin-top:6px">✨ برای ایموجی پریمیوم، متن را داخل ربات بنویسید: <code>/panel</code> ← 🔒 عضویت اجباری</div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
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
      <span class="badge <?= (int)$W['verified'] > 0 ? 'green' : 'amber' ?>">
        <?= (int)$W['verified'] > 0 ? '✅ تایید شده ' . h(date('Y-m-d H:i', (int)$W['verified'])) : '⚠️ هنوز تایید نشده' ?></span>
      <?php if ($bal !== null): ?><span class="badge gray">موجودی: <?= h($bal) ?> TON</span><?php endif; ?>
    </div>
  </div></div>

  <div class="card"><h2>📋 ترتیب راه‌اندازی امن</h2><div class="body">
    <p class="muted" style="line-height:2.1">
      ۱. یک ولت <b>تازه</b> بسازید و ۲۴ کلمه‌اش را جایی امن نگه دارید<br>
      ۲. مقدار کمی TON داخلش بگذارید — مثلا ۱ تا ۲ تا<br>
      ۳. همین بالا آدرس و عبارت بازیابی را بگذارید و <b>ذخیره</b> کنید<br>
      ۴. <b>🧪 تایید مالکیت</b> را بزنید تا تیک سبز شود<br>
      ۵. با <b>حالت آزمایشی روشن</b> یک خرید واقعی از مینی‌اپ بزنید — ربات در تلگرام
         نشانتان می‌دهد چه تراکنشی ساخته و امضا شده، ولی چیزی نمی‌فرستد<br>
      ۶. اگر مبلغ و مقصد درست بود، تیک آزمایشی را بردارید و <b>یک خرید خیلی کوچک</b> واقعی بزنید<br>
      ۷. رسید که آمد، تمام است — از این به بعد بدون شما کار می‌کند
    </p>
  </div></div>

  <div class="card"><h2>🩺 بررسی کامل — چه چیزی واقعا خودکار است؟</h2><div class="body">
    <p class="muted" style="margin-bottom:14px"><b><?= $okN ?></b> از <b><?= count($AU) ?></b> مورد سرِ جایش است.
      هر ⚠️ یعنی آن بخش منتظر شماست، نه اینکه خراب باشد.</p>
    <div class="tgrid">
      <?php foreach ($AU as $r): ?>
        <div style="display:flex;gap:11px;align-items:flex-start">
          <span style="font-size:16px;line-height:1.5"><?= $r['ok'] ? '✅' : '⚠️' ?></span>
          <div><b style="font-size:13px"><?= h($r['name']) ?></b>
            <?php if (trim((string)$r['why']) !== ''): ?>
              <div class="muted" style="line-height:1.85"><?= h($r['why']) ?></div>
            <?php endif; ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div></div>

<?php endif; ?>

</div>

<script>
// انتخاب متن را داخل تگ می‌پیچد (نقل‌قول، پررنگ، ...)
function premEmoji(id) {
  var code = prompt('کد ایموجی پریمیوم را بگذارید:\n(با دستور /emoji در ربات مادر می‌گیرید)');
  if (!code) return;
  code = code.trim();
  if (!/^[0-9]+$/.test(code)) { alert('کد باید فقط عدد باشد.'); return; }
  wrapSel(id, '<tg-emoji emoji-id="' + code + '">', '</tg-emoji>');
}
function wrapSel(id, open, close) {
  var el = document.getElementById(id);
  if (!el) return;
  var s = el.selectionStart, e = el.selectionEnd, v = el.value;
  var sel = v.substring(s, e) || 'متن';
  el.value = v.substring(0, s) + open + sel + close + v.substring(e);
  el.focus();
  el.selectionStart = s + open.length;
  el.selectionEnd   = s + open.length + sel.length;
}
</script>
</body>
</html>

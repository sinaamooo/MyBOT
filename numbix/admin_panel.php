<?php
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

if (is_file(__DIR__ . '/config.local.php')) require_once __DIR__ . '/config.local.php';
define('MEMBERSHIP_LIB_ONLY', true);
require_once __DIR__ . '/bot_master_membership.php';
require_once nbMod('panel_auth');
monSection('admin', 'web_' . preg_replace('/[^a-z]/', '', (string)($_POST['action'] ?? $_GET['tab'] ?? 'home')));

if (isset($_GET['kycimg']) && function_exists('kycDir')) {
    $f = kycImgPath((int)$_GET['kycimg'], ($_GET['n'] ?? '') === '2' ? 'img2' : 'img');
    if ($f === '' || !is_file($f)) { http_response_code(404); exit; }
    $bin = (string)file_get_contents($f);
    $mime = str_starts_with($bin, "\x89PNG") ? 'image/png' : (str_starts_with($bin, 'RIFF') ? 'image/webp' : 'image/jpeg');
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    echo $bin;
    exit;
}

function checkCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400); exit('درخواست نامعتبر (CSRF).');
    }
}

function flashSafeHtml($s) {
    $s = h((string)$s);
    return strtr($s, [
        '&lt;b&gt;' => '<b>', '&lt;/b&gt;' => '</b>',
        '&lt;code&gt;' => '<code>', '&lt;/code&gt;' => '</code>',
        '&lt;br&gt;' => '<br>',
    ]);
}

function go($flash = null, $type = 'ok') {
    if ($flash !== null) $_SESSION['flash'] = ['msg' => $flash, 'type' => $type];
    $q = ['tab' => (string)($_POST['tab'] ?? $_GET['tab'] ?? 'dashboard')];
    parse_str((string)($_POST['ret'] ?? ''), $r);
    foreach (['s', 'st', 'f', 'q', 'page', 'id', 'banned', 'c', 'pc'] as $k)
        if (isset($r[$k]) && is_scalar($r[$k]) && (string)$r[$k] !== '') $q[$k] = (string)$r[$k];
    $open = preg_replace('/[^A-Za-z0-9_]/', '', (string)($r['open'] ?? ''));
    if ($open !== '') $q['open'] = $open;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($q) . ($open !== '' ? '#c-' . $open : ''));
    exit;
}

function baseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $host . $dir;
}

function pWeakPass() {
    return strlen(ADMIN_PASSWORD) < 12 || !preg_match('/[^A-Za-z0-9]/', ADMIN_PASSWORD);
}

function pLater(?callable $fn = null) {
    static $q = [];
    if ($fn !== null) { $q[] = $fn; return; }
    if (!$q) return;
    while (ob_get_level() > 0) @ob_end_flush();
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    else @flush();
    ignore_user_abort(true);
    foreach ($q as $f) { try { $f(); } catch (Throwable $e) { error_log('[panel] ' . $e->getMessage()); } }
    $q = [];
}

function pSwr($key, $maxAge, callable $fn) {
    $f = rtrim(DATA_DIR, '/') . '/.panel_' . preg_replace('/[^a-z0-9_]/', '', $key);
    $raw = is_file($f) ? @file_get_contents($f) : false;
    $v = ($raw !== false && $raw !== '') ? json_decode($raw, true) : null;
    if ($v === null) {
        $v = $fn();
        @file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX);
        return $v;
    }
    if (time() - (int)@filemtime($f) >= $maxAge) {
        @touch($f);
        pLater(function () use ($f, $fn) { @file_put_contents($f, json_encode($fn(), JSON_UNESCAPED_UNICODE), LOCK_EX); });
    }
    return $v;
}

function pCached($key, $maxAge, callable $fn) {
    $f = rtrim(DATA_DIR, '/') . '/.panel_' . preg_replace('/[^a-z0-9_]/', '', $key);
    if ($maxAge > 0 && is_file($f) && time() - (int)@filemtime($f) < $maxAge) {
        $raw = @file_get_contents($f);
        if ($raw !== false && $raw !== '') {
            $v = json_decode($raw, true);
            if ($v !== null) return $v;
        }
    }
    $v = $fn();
    @file_put_contents($f, json_encode($v, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $v;
}

function pUsersDb() {
    static $db = false;
    if ($db === false) $db = function_exists('usersDb') ? usersDb() : null;
    return $db;
}

function pUserCount($where = '', $bind = []) {
    $db = pUsersDb(); if (!$db) return 0;
    $sql = 'SELECT COUNT(*) FROM users' . ($where !== '' ? ' WHERE ' . $where : '');
    $st = $db->prepare($sql);
    if (!$st) return 0;
    foreach ($bind as $k => $v) $st->bindValue($k, $v);
    $r = $st->execute();
    return $r ? (int)$r->fetchArray(SQLITE3_NUM)[0] : 0;
}

function pWalletTotal($maxAge = 600) {
    return (float)pSwr('wallet_sum', $maxAge, function () {
        $db = pUsersDb(); if (!$db) return 0.0;
        return (float)(@$db->querySingle("SELECT SUM(CAST(json_extract(data,'$.balance') AS REAL)) FROM users") ?: 0);
    });
}

function pUsersPage($page, $per, $q = '', $onlyBanned = false) {
    $db = pUsersDb(); if (!$db) return [[], 0];
    $w = []; $b = [];
    $q = trim($q);
    if ($q !== '') {
        if (ctype_digit($q)) { $w[] = 'id = :id'; $b[':id'] = (int)$q; }
        elseif (preg_match('/^@([A-Za-z0-9_]{3,32})$/', $q, $um)) {
            $w[] = "lower(json_extract(data,'$.username')) = :u";
            $b[':u'] = strtolower($um[1]);
        }
        else {
            $w[] = "(lower(json_extract(data,'$.username')) LIKE :q OR lower(json_extract(data,'$.first_name')) LIKE :q)";
            $b[':q'] = '%' . strtolower($q) . '%';
        }
    }
    if ($onlyBanned) $w[] = "json_extract(data,'$.banned') IN (1,'1','true')";
    $where = $w ? implode(' AND ', $w) : '';
    $page  = max(1, (int)$page);
    $off   = ($page - 1) * $per;

    $filtered = ($where !== '');
    $fetch = $filtered ? $per + 1 : $per;

    $sql = 'SELECT id, data FROM users' . ($where !== '' ? ' WHERE ' . $where : '')
         . ' ORDER BY id DESC LIMIT :lim OFFSET :off';
    $st = $db->prepare($sql);
    if (!$st) return [[], 0];
    foreach ($b as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', (int)$fetch, SQLITE3_INTEGER);
    $st->bindValue(':off', (int)$off, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[(string)$row['id']] = $d;
    }

    if (!$filtered) return [$out, (int)pSwr('user_n', 120, fn() => pUserCount())];

    $more = count($out) > $per;
    if ($more) array_pop($out);
    $total = $off + count($out) + ($more ? 1 : 0);
    return [$out, $total, $more];
}

function pUser($id) {
    $db = pUsersDb(); if (!$db) return null;
    $st = $db->prepare('SELECT data FROM users WHERE id = :i');
    if (!$st) return null;
    $st->bindValue(':i', (int)$id, SQLITE3_INTEGER);
    $r = $st->execute();
    $row = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function pRefCount($id) {
    return pUserCount("CAST(json_extract(data,'$.referrer') AS INTEGER) = :r", [':r' => (int)$id]);
}

function pTopReferrers($limit = 20) {
    $limit = max(1, (int)$limit);
    return (array)pCached('toprefs_' . $limit, 60, function () use ($limit) {
        $db = pUsersDb(); if (!$db) return [];
        $res = @$db->query("SELECT CAST(json_extract(data,'$.referrer') AS INTEGER) AS r, COUNT(*) AS n
                            FROM users WHERE r > 0 GROUP BY r ORDER BY n DESC LIMIT " . $limit);
        $out = [];
        while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) $out[] = ['id' => (int)$row['r'], 'n' => (int)$row['n']];
        return $out;
    });
}

function atU($name) {
    return "\u{2066}@" . ltrim((string)$name, '@') . "\u{2069}";
}

function pLabel($id, $u = null) {
    $u = $u ?: pUser($id);
    if (!$u) return (string)$id;
    if (!empty($u['username']))   return atU($u['username']);
    if (!empty($u['first_name'])) return $u['first_name'];
    return (string)$id;
}

function pOrdersDb() {
    static $db = false;
    if ($db === false) $db = function_exists('ordersDb') ? ordersDb() : null;
    return $db;
}

function pOrdersPage($status, $q, $page, $per) {
    $db = pOrdersDb(); if (!$db) return [[], 0, 1, 1];
    $w = []; $b = [];
    if ($status !== 'all') { $w[] = 'status = :st'; $b[':st'] = (string)$status; }
    $q = trim((string)$q);
    if ($q !== '') {
        $w[] = '(id LIKE :q OR CAST(user_id AS TEXT) LIKE :q)';
        $b[':q'] = '%' . $q . '%';
    }
    $where = $w ? ' WHERE ' . implode(' AND ', $w) : '';

    $cs = $db->prepare('SELECT COUNT(*) FROM orders' . $where);
    foreach ($b as $k => $v) $cs->bindValue($k, $v);
    $cr = $cs->execute();
    $total = $cr ? (int)$cr->fetchArray(SQLITE3_NUM)[0] : 0;

    $pages = max(1, (int)ceil($total / $per));
    $page  = max(1, min($pages, (int)$page));

    $st = $db->prepare('SELECT id, data FROM orders' . $where
        . ' ORDER BY created_at DESC LIMIT :lim OFFSET :off');
    if (!$st) return [[], 0, 1, 1];
    foreach ($b as $k => $v) $st->bindValue($k, $v);
    $st->bindValue(':lim', (int)$per, SQLITE3_INTEGER);
    $st->bindValue(':off', ($page - 1) * $per, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[$row['id']] = $d;
    }
    return [$out, $total, $pages, $page];
}

function pOrdersOfUser($uid, $limit = 40) {
    $db = pOrdersDb(); if (!$db) return [[], 0, 0.0];
    $st = $db->prepare('SELECT data FROM orders WHERE user_id = :u ORDER BY created_at DESC LIMIT :l');
    if (!$st) return [[], 0, 0.0];
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':l', (int)$limit, SQLITE3_INTEGER);
    $res = $st->execute();
    $rows = [];
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $rows[] = $d;
    }
    $cs = $db->prepare('SELECT COUNT(*) FROM orders WHERE user_id = :u');
    $cs->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $cr = $cs->execute();
    $total = $cr ? (int)$cr->fetchArray(SQLITE3_NUM)[0] : 0;

    $s = $db->prepare("SELECT SUM(CAST(json_extract(data,'$.amount') AS REAL)) FROM orders
                       WHERE user_id = :u AND status = 'approved'");
    $s->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $r = $s->execute();
    return [$rows, $total, $r ? (float)($r->fetchArray(SQLITE3_NUM)[0] ?? 0) : 0.0];
}

function pNumSpent($uid) {
    $db = maOrdersDb(); if (!$db) return 0.0;
    $s = $db->prepare("SELECT SUM(CAST(json_extract(data,'$.total') AS REAL)) FROM orders
                       WHERE user_id = :u AND app = 'num' AND status = 'done'");
    $s->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $r = $s->execute();
    return $r ? (float)($r->fetchArray(SQLITE3_NUM)[0] ?? 0) : 0.0;
}

function pNumStats() {
    return pSwr('numstats', 120, function () {
        $out = ['done' => 0, 'revenue' => 0.0, 'today' => 0, 'today_rev' => 0.0];
        $db = maOrdersDb(); if (!$db) return $out;
        $res = @$db->query("SELECT COUNT(*) AS n, SUM(CAST(json_extract(data,'\$.total') AS REAL)) AS s,
                                   SUM(CASE WHEN substr(created_at,1,10) = '" . date('Y-m-d') . "' THEN 1 ELSE 0 END) AS tn,
                                   SUM(CASE WHEN substr(created_at,1,10) = '" . date('Y-m-d') . "'
                                            THEN CAST(json_extract(data,'\$.total') AS REAL) ELSE 0 END) AS ts
                            FROM orders WHERE app = 'num' AND status = 'done'");
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        if ($row) $out = ['done' => (int)$row['n'], 'revenue' => (float)$row['s'],
                          'today' => (int)$row['tn'], 'today_rev' => (float)$row['ts']];
        return $out;
    });
}

function pNumByPhone($q, $status, $limit) {
    $d  = preg_replace('/\D/', '', (string)$q);
    $db = strlen($d) >= 6 ? numActsDb() : null;
    if (!$db) return [[], 0];
    $st = $db->prepare("SELECT id FROM num_acts WHERE json_extract(data,'$.phone') LIKE :p ORDER BY created DESC LIMIT :n");
    $st->bindValue(':p', '%' . $d . '%', SQLITE3_TEXT);
    $st->bindValue(':n', (int)$limit, SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
        $o = MaOrder::get((string)$row['id']);
        if ($o && ($status === '' || ($o['status'] ?? '') === $status)) $out[] = $o;
    }
    return [$out, count($out)];
}

function pSparkBuckets($days = 7) {
    return pCached('spark' . (int)$days, 60, function () use ($days) {
    $b = [];
    for ($i = $days - 1; $i >= 0; $i--) $b[date('Y-m-d', strtotime("-$i day"))] = 0;
    $db = maOrdersDb(); if (!$db) return $b;
    $st = $db->prepare("SELECT substr(created_at,1,10) AS d, COUNT(*) AS n
                        FROM orders WHERE app = 'num' AND status = 'done' AND substr(created_at,1,10) >= :from
                        GROUP BY d");
    if (!$st) return $b;
    $st->bindValue(':from', array_key_first($b), SQLITE3_TEXT);
    $res = $st->execute();
    while ($res && $row = $res->fetchArray(SQLITE3_ASSOC))
        if (isset($b[$row['d']])) $b[$row['d']] = (int)$row['n'];
    return $b;
    });
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $a = $_POST['action'] ?? '';

    if (in_array($a, ['adm_write_test', 'adm_leak_test', 'adm_speed_test'], true)) {
        $report = $a === 'adm_write_test' ? admWriteTestText()
                : ($a === 'adm_leak_test' ? admLeakTestText() : admSpeedText());
        go($report, (str_contains($report, '🔴') || str_contains($report, '🚨')) ? 'err' : 'ok');
    }

    if ($a === 'ban_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $ban = ($_POST['ban'] ?? '') === '1';
        mutateUser($uid, function (&$user) use ($ban) {
            if ($user !== null) $user['banned'] = $ban;
        });
        go($ban ? 'کاربر مسدود شد.' : 'کاربر آزاد شد.');
    }
    if ($a === 'set_balance') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $val = (float)str_replace(',', '', $_POST['balance'] ?? '0');
        if (!is_finite($val) || abs($val) > 1e12) go('مبلغ نامعتبر است.', 'err');
        mutateUser($uid, function (&$user) use ($val, $uid) {
            if ($user === null) return;
            $old = (float)($user['balance'] ?? 0);
            $user['balance'] = round($val, 2);
            walletTx($uid, $old, $user['balance'], 'admin_set', 'panel');
        });
        go('موجودی به‌روزرسانی شد.');
    }

    if ($a === 'save_sup_group') {
        $link = trim((string)($_POST['sg_link'] ?? ''));
        $chat = trim((string)($_POST['sg_chat'] ?? ''));
        $th   = max(0, (int)($_POST['sg_thread'] ?? 0));
        if ($link !== '') {
            [$lc, $lt] = chatLinkResolve($link);
            if ($lc === null) go('لینکِ تاپیک شناخته نشد. روی یکی از پیام‌های همان تاپیک نگه دارید ← Copy Link.', 'err');
            $chat = (string)$lc;
            $th   = (int)$lt;
        }
        if ($chat !== '' && !preg_match('/^(-\d{5,20}|@[A-Za-z0-9_]{4,32})$/', $chat)) go('آیدیِ گروه نامعتبر است (مثلا -1001234567890).', 'err');
        $on = !empty($_POST['sg_on']);
        maSetRoot(function (&$m) use ($chat, $th, $on) {
            $m['sup']['chat']   = $chat;
            $m['sup']['thread'] = $th;
            $m['sup']['on']     = $on ? 1 : 0;
        });
        go($on && $chat !== '' ? 'تیکت‌ها از این به بعد به همان گروه/تاپیک می‌روند.' : 'تیکت‌ها به پیویِ مدیر می‌روند.');
    }
    if ($a === 'sup_group_test') {
        [$ok, $err] = maSupSend((int)ADMIN_ID, '', 'تستِ پنلِ وب', '', 'این یک پیامِ آزمایشی است. روی همین پیام ریپلای کنید تا پاسخ به شما برگردد.');
        go($ok ? 'پیامِ آزمایشی فرستاده شد.' : $err, $ok ? 'ok' : 'err');
    }
    if ($a === 'master_webhook') {
        $r = tg(BOT_TOKEN, 'setWebhook', [
            'url' => baseUrl() . '/bot_master_membership.php',
            'drop_pending_updates' => 'true',
            'allowed_updates' => json_encode(['message', 'callback_query', 'my_chat_member']),
            'secret_token' => WEBHOOK_SECRET,
            'max_connections' => nbHookMax(),
        ]);
        if (empty($r['ok'])) go('خطا: ' . ($r['description'] ?? '—'), 'err');
        maCachePut('menu_sig', '');
        maMenuSync();
        go('وبهوکِ ربات تنظیم شد.');
    }

    if ($a === 'api_base') {
        $u = rtrim(trim((string)($_POST['base_url'] ?? '')), '/');
        if ($u !== '' && !preg_match('#^https://[^\s]+$#i', $u)) go('آدرس باید با https:// شروع شود و فاصله نداشته باشد.', 'err');
        maSetRoot(function (&$m) use ($u) { $m['base_url'] = $u; });
        maMenuSync();
        go($u === '' ? 'آدرسِ مینی‌اپ خالی شد و از آدرسِ وبهوک حدس زده می‌شود.' : 'آدرسِ مینی‌اپ ذخیره شد.');
    }

    if ($a === 'api_num') {
        if (!function_exists('numSet')) go('بخشِ شماره مجازی در دسترس نیست.', 'err');
        $p = $_POST;
        $sw = false;
        numSet(function (&$n) use ($p, &$sw) {
            $n['provider']   = in_array($p['provider'] ?? '', array_keys(numProviders()), true) ? $p['provider'] : '5sim';
            $n['wait']       = max(60, (int)($p['wait'] ?? 900));
            $n['poll']       = max(3,  (int)($p['poll'] ?? 6));
            $n['markup']     = max(0, (float)str_replace(',', '', (string)($p['markup'] ?? 0)));
            $n['sync_price'] = !empty($p['sync_price']);
            if (!is_array($n['api'] ?? null)) $n['api'] = [];
            $n['api']['on']      = !empty($p['on']);
            $n['api']['base']    = numBaseNorm((string)($p['base'] ?? ''), $n['provider']);
            $pr = array_values(array_intersect(array_keys(numProducts()), (array)($p['prods'] ?? [])));
            $n['prods']    = $pr ?: ['telegram'];
            $n['pick']     = !empty($p['pick']);
            $n['pick_n']   = max(1, min(60, (int)($p['pick_n'] ?? 12)));
            $n['pick_ops'] = max(1, min(5, (int)($p['pick_ops'] ?? 2)));
            $n['all']      = !empty($p['all']);
            $n['api']['nl_svc_ig'] = trim((string)($p['nl_svc_ig'] ?? ''));
            $n['api']['nl_svc_wa'] = trim((string)($p['nl_svc_wa'] ?? ''));
            if (numBaseForeign($n['api']['base'], $n['provider'])) $n['api']['base'] = '';
            $n['api']['nl_svc']  = trim((string)($p['nl_svc'] ?? '1'));
            $n['api']['timeout'] = max(3, min(60, (int)($p['timeout'] ?? 15)));
            $n['api']['rate']    = max(0, (float)str_replace([',', '،'], '', (string)($p['rate'] ?? 0)));
            $n['api']['max']     = max(0, (float)str_replace([',', '،'], '', (string)($p['max'] ?? 0)));
            $t = preg_replace('/\s+/', '', (string)($p['token'] ?? ''));  if ($t !== '') $n['api']['token']  = $t;
            $k = preg_replace('/\s+/', '', (string)($p['nl_key'] ?? '')); if ($k !== '') $n['api']['nl_key'] = $k;
            if ($n['provider'] !== '5sim' && $k === '' && $t !== '' && numLooks5sim($t)) {
                $n['provider'] = '5sim';
                if (numBaseForeign($n['api']['base'], '5sim')) $n['api']['base'] = '';
                $sw = true;
            }
        });
        go('تنظیماتِ شماره مجازی ذخیره شد.' . ($sw ? ' توکنِ ۵سیم بود؛ فروشنده روی ۵سیم رفت. یک بار «📥 وارد کردن» را بزنید.' : ''));
    }

    if ($a === 'api_num_test') {
        $t0 = microtime(true);
        [$amt, $cur, $err] = numBalance();
        $ms = round((microtime(true) - $t0) * 1000);
        if ($err !== '') go('🔴 ' . numProvName() . ': ' . $err . ' — مقصد: ' . numBase() . ' (' . $ms . 'ms)', 'err');
        $rate = numNeedsRate() ? numRate() : 0;
        $pr = numProv() === '5sim' ? (num5User('profile')[0] ?? null) : null;
        go('✅ ' . numProvName() . ' وصل است — موجودی: ' . fmtNum($amt) . ' ' . $cur .
           ($rate > 0 ? ' ≈ ' . fmtNum(numRound100($amt * $rate)) . ' تومان' : '') .
           (is_array($pr) ? ' — بلوکه‌شده: ' . fmtNum((float)($pr['frozen_balance'] ?? 0)) . ' — امتیاز: ' . fmtNum((float)($pr['rating'] ?? 0)) . ' از ۹۶' : '') .
           ' (' . $ms . 'ms)');
    }

    if ($a === 'save_referral') {
        $on  = !empty($_POST['ref_on']);
        $pct = max(0, min(100, (float)($_POST['ref_percent'] ?? 0)));
        cfgSet(function (&$c) use ($on, $pct) {
            $c['referral']['on']      = $on;
            $c['referral']['percent'] = $pct;
        });
        go('رفرال ذخیره شد.');
    }
    if ($a === 'save_gateway') {
        $post = $_POST;
        $u = trim($post['gw_base'] ?? '');
        if ($u !== '' && !preg_match('#^https://#i', $u)) go('آدرس ربات باید با https:// شروع شود.', 'err');
        $kErr = gwKeyError((string)($post['gw_key'] ?? ''));
        if ($kErr !== '') go($kErr, 'err');
        cfgSet(function (&$c) use ($post, $u) {
            $c['gateway']['on']       = !empty($post['gw_on']);
            $c['gateway']['provider'] = in_array($post['gw_prov'] ?? '', ['oxapay','nowpayments','custom'], true)
                                        ? $post['gw_prov'] : 'oxapay';
            $gwKey = gwCleanKey($post['gw_key'] ?? '');
            $gwIpn = gwCleanKey($post['gw_ipn'] ?? '');
            if ($gwKey !== '') $c['gateway']['api_key']    = $gwKey;
            if ($gwIpn !== '') $c['gateway']['ipn_secret'] = $gwIpn;
            $c['gateway']['base_url']  = $u;
            $c['gateway']['coin']      = strtoupper(trim($post['gw_coin'] ?? 'USDT'));
            $c['gateway']['network']   = strtoupper(trim($post['gw_net'] ?? ''));
            $c['gateway']['rate']      = max(0, (float)str_replace(',', '', $post['gw_rate'] ?? 0));
            $c['gateway']['expire']    = max(5, (int)($post['gw_exp'] ?? 30));
            $c['gateway']['min']       = max(0, (float)str_replace(',', '', $post['gw_min'] ?? 0));
            $c['gateway']['custom_url']= trim($post['gw_curl'] ?? '');
            $c['gateway']['mode']      = ($post['gw_mode'] ?? '') === 'page' ? 'page' : 'address';
            $c['gateway']['underpaid'] = max(0, min(60, (float)maNum($post['gw_under'] ?? 1)));
        });
        go('درگاه پرداخت ذخیره شد.');
    }
    if ($a === 'test_gw') {
        if (!function_exists('gwCreateInvoice') || !gwOn())
            go('اول «درگاهِ خودکار روشن باشد» را بزنید، کلیدِ API را بگذارید و ذخیره کنید (آدرسِ عمومیِ ربات هم باید https باشد).', 'err');
        [$ok, $d, $err] = gwCreateInvoice('or_TEST' . bin2hex(random_bytes(3)), 100000);
        if (!$ok) go('❌ درگاهِ ارز دیجیتال جواب نداد: ' . mb_substr((string)$err, 0, 300), 'err');
        go('✅ درگاهِ ارز دیجیتال کار می‌کند — فاکتورِ آزمایشیِ ۱۰۰٬۰۰۰ تومانی ساخته شد: ' .
           (!empty($d['address']) ? 'آدرس ' . $d['address'] . ' · ' . $d['amount'] . ' ' . $d['coin'] . (!empty($d['network']) ? ' (' . $d['network'] . ')' : '')
                                  : 'لینک ' . (string)$d['url']) . ' — به آن واریز نکنید، خودش منقضی می‌شود.');
    }
    if ($a === 'test_irpay') {
        if (!function_exists('irCreate') || !irOn())
            go('اول «درگاهِ ایرانی روشن باشد» را بزنید، مرچنت را بگذارید و ذخیره کنید.', 'err');
        [$ok, $url, , $err] = irCreate('or_TEST' . bin2hex(random_bytes(3)), max(10000, (float)tuMin('iran')));
        if (!$ok) go('❌ درگاهِ ایرانی جواب نداد: ' . mb_substr((string)$err, 0, 300), 'err');
        go('✅ درگاهِ ایرانی کار می‌کند — لینکِ آزمایشی ساخته شد: ' . $url);
    }
    if ($a === 'test_zpotp') {
        $IRc = cfg()['irpay'] ?? [];
        if (trim((string)($IRc['oauth_id'] ?? '')) === '' || trim((string)($IRc['oauth_secret'] ?? '')) === '')
            go('اول client_id و client_secret زرین‌پال را بگذارید و ذخیره کنید.', 'err');
        $ph = function_exists('tuPhoneNorm') ? tuPhoneNorm((string)($_POST['phone'] ?? '')) : '';
        if (!preg_match('/^09\d{9}$/', (string)$ph)) go('یک شماره‌ی موبایلِ ایرانی (۰۹…) برای تست بنویسید.', 'err');
        [$d, $err] = zpOauth('initialize', ['username' => $ph, 'channel' => ($IRc['otp_ch'] ?? 'sms') === 'ussd' ? 'ussd' : 'sms']);
        if (!$d) go('❌ زرین‌پال کد نفرستاد: ' . mb_substr((string)$err, 0, 300), 'err');
        go('✅ اتصالِ OAuth زرین‌پال درست است — کدِ تایید به ' . $ph . ' فرستاده شد' .
           (!empty($d['ussd_code']) ? ' (USSD: ' . $d['ussd_code'] . ')' : '') . '.');
    }
    if ($a === 'save_irpay') {
        $post = $_POST;
        $m = gwCleanKey((string)($post['ir_merchant'] ?? ''));
        if ($m !== '' && !preg_match('/^[A-Za-z0-9\-]{4,64}$/', $m)) go('مرچنت فقط حروفِ انگلیسی، عدد و خط تیره است.', 'err');
        cfgSet(function (&$c) use ($post, $m) {
            $c['irpay']['on']         = !empty($post['ir_on']);
            $c['irpay']['provider']   = ($post['ir_prov'] ?? '') === 'zibal' ? 'zibal' : 'zarinpal';
            if ($m !== '') $c['irpay']['merchant'] = $m;
            if (!empty($post['ir_merchant_clear'])) $c['irpay']['merchant'] = '';
            $c['irpay']['sandbox']    = !empty($post['ir_sandbox']);
            $c['irpay']['min']        = max(1000, (float)maNum($post['ir_min'] ?? 10000));
            $c['irpay']['max']        = max(0, (float)maNum($post['ir_max'] ?? 0));
            $c['irpay']['kyc']        = !empty($post['ir_kyc']);
            $c['irpay']['kyc_limit']  = max(0, (float)maNum($post['ir_kyc_limit'] ?? 0));
            $c['irpay']['kyc_mode']   = in_array($post['ir_kyc_mode'] ?? '', ['auto', 'phone', 'docs'], true) ? $post['ir_kyc_mode'] : 'docs';
            $c['irpay']['otp_ch']     = ($post['ir_otp_ch'] ?? '') === 'ussd' ? 'ussd' : 'sms';
            $oi = preg_replace('/\D/', '', (string)($post['ir_oauth_id'] ?? ''));
            if ($oi !== '' || !empty($post['ir_oauth_clear'])) $c['irpay']['oauth_id'] = $oi;
            $os = gwCleanKey((string)($post['ir_oauth_secret'] ?? ''));
            if ($os !== '') $c['irpay']['oauth_secret'] = $os;
            if (!empty($post['ir_oauth_clear'])) { $c['irpay']['oauth_id'] = ''; $c['irpay']['oauth_secret'] = ''; }
            $c['irpay']['ir_only']    = !empty($post['ir_only']);
            $c['irpay']['card_check'] = !empty($post['ir_card_check']);
            $c['irpay']['desc']       = mb_substr(trim((string)($post['ir_desc'] ?? '')), 0, 120) ?: 'شارژ کیف پول';
        });
        go('درگاهِ ایرانی ذخیره شد.');
    }
    if ($a === 'kyc_decide') {
        $kuid = (int)($_POST['uid'] ?? 0);
        $ok = ($_POST['dec'] ?? '') === 'ok';
        if ($kuid <= 0 || !function_exists('kycDecide')) go('نامعتبر.', 'err');
        $done = kycDecide($kuid, $ok, ADMIN_ID, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 200));
        go($done ? ($ok ? 'احراز هویت تایید شد و به کاربر خبر داده شد.' : 'احراز هویت رد شد و به کاربر خبر داده شد.') : 'این درخواست قبلا بررسی شده بود.');
    }
    if ($a === 'num_import') {
        if (!numReady()) go('اول در «API و اتصال‌ها» کلیدِ ' . numProvName() . ' را ثبت و فروش را روشن کنید.', 'err');
        @set_time_limit(120);
        [$countries, $rows, $err] = numCatalog();
        if ($err !== '') go('فهرست از ' . numProvName() . ' نیامد: ' . mb_substr($err, 0, 240), 'err');
        [$nc, $ni, $up, $off] = numImport($countries, $rows, (float)numVal('markup', 0));
        [$delI] = numPruneCatalog(array_column($rows, 'sid'));
        go('وارد شد — کشورِ تازه: ' . fmtNum($nc) . ' · شماره‌ی تازه: ' . fmtNum($ni) .
           ' · به‌روزشده: ' . fmtNum($up) . ' · خاموش‌شده: ' . fmtNum($off) .
           ($delI ? ' · حذف‌شده: ' . fmtNum($delI) : ''));
    }

    if ($a === 'num_cat_save') {
        $cid = (string)($_POST['cid'] ?? '');
        $cat = maFindCat($cid);
        if (!$cat) go('این کشور پیدا نشد.', 'err');
        $p   = $_POST;
        $its = is_array($p['it'] ?? null) ? $p['it'] : [];
        maSetRoot(function (&$m) use ($cid, $p, $its) {
            foreach ((array)($m['cats'] ?? []) as $k => $c) {
                if ((string)($c['id'] ?? '') !== $cid) continue;
                $nm = mb_substr(trim((string)($p['name'] ?? '')), 0, 40);
                $em = mb_substr(trim((string)($p['emoji'] ?? '')), 0, 16);
                if ($nm !== '') $m['cats'][$k]['name'] = $nm;
                $m['cats'][$k]['emoji'] = $em !== '' ? $em : '🌍';
                $m['cats'][$k]['on']    = !empty($p['on']);
            }
            foreach ((array)($m['items'] ?? []) as $k => $i) {
                $iid = (string)($i['id'] ?? '');
                if ((string)($i['cat'] ?? '') !== $cid || !is_array($its[$iid] ?? null)) continue;
                $r  = $its[$iid];
                $nm = mb_substr(trim((string)($r['name'] ?? '')), 0, 40);
                $pr = maNum($r['price'] ?? 0);
                if ($nm !== '') $m['items'][$k]['name']  = $nm;
                if ($pr > 0)    $m['items'][$k]['price'] = $pr;
                $m['items'][$k]['badge'] = mb_substr(trim((string)($r['badge'] ?? '')), 0, 16);
                $m['items'][$k]['on']    = !empty($r['on']);
            }
        });
        go('«' . ($cat['name'] ?? '') . '» ذخیره شد.');
    }

    if ($a === 'num_bulk') {
        $pct = maNum($_POST['pct'] ?? 0);
        $dir = ($_POST['dir'] ?? '') === 'down' ? -1 : 1;
        $cid = (string)($_POST['cid'] ?? '');
        $cap = $dir < 0 ? 90 : 500;
        if ($pct <= 0 || $pct > $cap) go('درصد باید بیشتر از صفر و حداکثر ' . $cap . ' باشد.', 'err');
        if ($cid !== '' && !maFindCat($cid)) go('این کشور پیدا نشد.', 'err');
        $f = 1 + $dir * $pct / 100;
        $n = 0;
        maSetRoot(function (&$m) use ($cid, $f, &$n) {
            foreach ((array)($m['items'] ?? []) as $k => $i) {
                if ($cid !== '' && (string)($i['cat'] ?? '') !== $cid) continue;
                $old = (float)($i['price'] ?? 0);
                if ($old <= 0) continue;
                $m['items'][$k]['price'] = max(100.0, numRound100($old * $f));
                $n++;
            }
        });
        go('قیمتِ ' . fmtNum($n) . ' شماره ' . ($dir > 0 ? 'گران‌تر' : 'ارزان‌تر') . ' شد.');
    }

    if ($a === 'num_cancel') {
        [$ok, $err] = numFinish((string)($_POST['id'] ?? ''), 'cancel');
        go($ok ? 'شماره لغو شد و مبلغ به کیف پولِ کاربر برگشت.' : $err, $ok ? 'ok' : 'err');
    }

    if ($a === 'save_db') {
        [$dok, $derr] = dbSaveCreds([
            'on' => ($_POST['db_on'] ?? '') === '1',
            'host' => $_POST['db_host'] ?? '', 'port' => $_POST['db_port'] ?? '',
            'name' => $_POST['db_name'] ?? '', 'user' => $_POST['db_user'] ?? '',
            'pass' => $_POST['db_pass'] ?? '', 'prefix' => $_POST['db_prefix'] ?? '',
        ]);
        go($dok ? 'اطلاعاتِ دیتابیس ذخیره شد.' : $derr, $dok ? 'ok' : 'err');
    }
    if ($a === 'test_db') {
        [$dok, $dmsg] = dbPing([
            'host' => trim((string)($_POST['db_host'] ?? '')), 'port' => (int)($_POST['db_port'] ?? 3306),
            'name' => trim((string)($_POST['db_name'] ?? '')), 'user' => trim((string)($_POST['db_user'] ?? '')),
            'pass' => ($_POST['db_pass'] ?? '') !== '' ? (string)$_POST['db_pass'] : (string)dbCreds()['pass'],
            'charset' => 'utf8mb4',
        ]);
        go(($dok ? '✅ ' : '❌ ') . $dmsg, $dok ? 'ok' : 'err');
    }
    if ($a === 'migrate_db') {
        if (!function_exists('nbMigrateToMysql')) go('لایه‌ی دیتابیس در دسترس نیست.', 'err');
        [$mok, $mmsg, $mrep] = nbMigrateToMysql();
        $done = 0; $warn = 0; $tot = 0;
        foreach ($mrep as $r) { $tot += (int)$r['src']; $done += (int)$r['dst']; if (!$r['ok']) $warn++; }
        $txt = ($mok ? '✅ ' : '⚠️ ') . $mmsg . ' — ' . number_format($done) . ' ردیف منتقل شد'
             . ($warn ? ('؛ ' . $warn . ' جدول نیاز به بررسی دارد.') : '.');
        go($txt, $mok ? 'ok' : 'err');
    }
    if ($a === 'backup_db') {
        if (!function_exists('nbBackupFile')) go('لایه‌ی بکاپ در دسترس نیست.', 'err');
        [$bok, $bpath, $bname, $bmsg] = nbBackupFile();
        if (!$bok || !is_file($bpath)) go('❌ ' . ($bmsg ?: 'ساختِ بکاپ ناموفق بود.'), 'err');
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $bname . '"');
        header('Content-Length: ' . filesize($bpath));
        header('X-Content-Type-Options: nosniff');
        readfile($bpath);
        @unlink($bpath);
        exit;
    }
    if ($a === 'save_topup_min') {
        $v = maNum($_POST['topup_min'] ?? 0);
        if ($v < 1000) go('کمترین مبلغ باید دست‌کم ۱٬۰۰۰ تومان باشد.', 'err');
        cfgSet(function (&$c) use ($v) { $c['topup_min'] = $v; });
        go('کمترین مبلغِ شارژ ذخیره شد.');
    }

    if ($a === 'sv_api') {
        $p   = $_POST;
        $url = trim((string)($p['sv_url'] ?? ''));
        if ($url !== '' && !preg_match('#^https?://\S+$#i', $url)) go('آدرسِ API باید با https:// شروع شود و فاصله نداشته باشد.', 'err');
        $url2 = trim((string)($p['sv2_url'] ?? ''));
        if ($url2 !== '' && !preg_match('#^https?://\S+$#i', $url2)) go('آدرسِ API پنلِ دوم باید با https:// شروع شود و فاصله نداشته باشد.', 'err');
        $cur = array_key_exists((string)($p['sv_cur'] ?? ''), svCurrencies()) ? (string)$p['sv_cur'] : 'usd_live';
        $fx  = maNum($p['sv_fx'] ?? 0);
        if ($cur === 'usd' && $fx <= 0) go('برای «دلار با نرخِ ثابت»، نرخِ هر دلار به تومان را بنویسید.', 'err');
        $oldUrl = rtrim((string)svCfg()['url'], '/');
        $oldUrl2 = rtrim((string)svCfg()['p2']['url'], '/');
        svSet(function (&$c) use ($p, $url, $url2, $cur, $fx, $oldUrl, $oldUrl2) {
            if (rtrim($url !== '' ? $url : SV_DEFAULT_URL, '/') !== $oldUrl) { $c['pcur'] = ''; $c['pbal'] = 0; $c['pbal_at'] = 0; }
            $c['url']     = $url;
            $k = svKeyClean($p['sv_key'] ?? '');
            if ($k !== '') $c['key'] = $k;
            elseif (!empty($p['sv_key_del'])) $c['key'] = '';
            elseif (isset($c['key']) && svKeyClean($c['key']) !== (string)$c['key']) $c['key'] = svKeyClean($c['key']);
            if (!is_array($c['p2'] ?? null)) $c['p2'] = [];
            if (rtrim($url2 !== '' ? $url2 : SV_P2_URL, '/') !== $oldUrl2) { $c['p2']['pcur'] = ''; $c['p2']['pbal'] = 0; $c['p2']['pbal_at'] = 0; }
            $c['p2']['url'] = $url2;
            $k2 = svKeyClean($p['sv2_key'] ?? '');
            if ($k2 !== '') $c['p2']['key'] = $k2;
            elseif (!empty($p['sv2_key_del'])) $c['p2']['key'] = '';
            $c['cur']     = $cur;
            $c['fx']      = max(0.0, $fx);
            $c['markup']  = max(0.0, min(1000.0, maNum($p['sv_markup'] ?? 0)));
            $c['timeout'] = max(5, min(60, (int)($p['sv_timeout'] ?? 20)));
            $c['auto_on'] = !empty($p['sv_auto_on']) ? 1 : 0;
        });
        svFxRefresh();
        $note = trim(svCurNote('a') . ' ' . svCurNote('b'));
        go('تنظیماتِ پنل‌های خدمات ذخیره شد.' . (svCurEff() === 'usd_live' && svFx() <= 0
            ? ' ⚠️ قیمتِ لحظه‌ایِ تتر در دسترس نیست؛ تا وقتی نیاید، نرخِ ثابتی که نوشته‌اید استفاده می‌شود.' : '') .
            ($note !== '' ? ' ' . $note : ''));
    }
    if ($a === 'tl_api') {
        $p = $_POST;
        $pri = array_key_exists((string)($p['tl_primary'] ?? ''), tlProviders()) ? (string)$p['tl_primary'] : 'google';
        $lurl = trim((string)($p['tl_libre_url'] ?? ''));
        if ($lurl !== '') {
            if (!preg_match('#^https://\S+$#i', $lurl) && !(defined('SV_ALLOW_PRIVATE') && SV_ALLOW_PRIVATE)) go('آدرسِ LibreTranslate باید با https:// شروع شود.', 'err');
            $why = '';
            if (tlSafeTarget($lurl, $why) === null) go('آدرسِ LibreTranslate رد شد: ' . $why, 'err');
        }
        $email = trim((string)($p['tl_mm_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) go('ایمیلِ MyMemory درست نیست.', 'err');
        $clean = fn($v) => mb_substr(preg_replace('/[\s\x00-\x1f]+/u', '', (string)$v), 0, 200);
        tlSet(function (&$c) use ($p, $pri, $lurl, $email, $clean) {
            $api = is_array($c['api'] ?? null) ? $c['api'] : [];
            $api['primary'] = $pri;
            foreach (array_keys(tlProviders()) as $k) {
                if (!is_array($api[$k] ?? null)) $api[$k] = [];
                $api[$k]['on'] = !empty($p['tl_on_' . $k]) ? 1 : 0;
                if (tlProviders()[$k]['key']) {
                    $nk = $clean($p['tl_key_' . $k] ?? '');
                    if ($nk !== '') $api[$k]['key'] = $nk;
                    elseif (!empty($p['tl_del_' . $k])) $api[$k]['key'] = '';
                }
            }
            $api['libre']['url'] = $lurl;
            $api['mymemory']['email'] = $email;
            $api['azure']['region'] = preg_replace('/[^a-z0-9]/i', '', (string)($p['tl_azure_region'] ?? ''));
            $c['api'] = $api;
        });
        foreach (array_keys(tlProviders()) as $k) tlDown($k, 0);
        $ch = tlChain();
        go($ch ? 'سرویس‌های ترجمه ذخیره شد. ترتیب: ' . implode(' ← ', array_map(fn($k) => explode(' (', tlProviders()[$k]['label'])[0], $ch)) . '. «🧪 تست» را بزنید.'
               : 'ذخیره شد، ولی هیچ سرویسِ ترجمه‌ای روشن نیست — ترجمه کار نمی‌کند.', $ch ? 'ok' : 'err');
    }
    if ($a === 'tl_test') {
        $ready = array_values(array_filter(array_keys(tlProviders()), 'tlReady'));
        if (!$ready) go('هیچ سرویسِ ترجمه‌ای روشن و کامل نیست.', 'err');
        $lines = [];
        $any = false;
        foreach ($ready as $k) {
            [$ok, $ms, $msg] = tlTest($k);
            $any = $any || $ok;
            $lines[] = ($ok ? '✅ ' : '❌ ') . explode(' (', tlProviders()[$k]['label'])[0] . ' — ' . $ms . 'ms: ' . mb_substr((string)$msg, 0, 120);
        }
        go(implode(' | ', $lines), $any ? 'ok' : 'err');
    }
    if ($a === 'sv_test') {
        $only = in_array($_POST['pv'] ?? '', svPanels(), true) ? [(string)$_POST['pv']] : svPanels();
        $ready = array_values(array_filter($only, 'svReady'));
        if (!$ready) go('اول کلیدِ API را بگذارید و «ذخیره» بزنید.', 'err');
        $lines = [];
        $bad = false;
        foreach ($ready as $pv) {
            $t0 = microtime(true);
            [$bal, $cur, $err] = svBalance($pv);
            $ms = round((microtime(true) - $t0) * 1000);
            if ($err !== '') { $bad = true; $lines[] = '🔴 ' . svPanelName($pv) . ': ' . $err . ' (' . $ms . 'ms)'; continue; }
            $note = svCurNote($pv);
            $lines[] = '✅ ' . svPanelName($pv) . ' وصل است — موجودی: ' . rtrim(rtrim(number_format($bal, 4, '.', ','), '0'), '.') . ' ' . $cur .
                       (svCurEff($pv) === 'usd_live' ? ' · هر دلار: ' . (svFx($pv) > 0 ? fmtNum(svFx($pv)) . ' تومان' : 'نامعلوم') : '') .
                       ' (' . $ms . 'ms)' . ($note !== '' ? ' — ' . $note : '');
        }
        go(implode(' | ', $lines), $bad ? 'err' : 'ok');
    }
    if ($a === 'sv_import') {
        if (!svReady()) go('اول آدرس و کلیدِ API پنلِ خدمات را ذخیره کنید.', 'err');
        [$ok, $msg] = svImport();
        go($msg, $ok ? 'ok' : 'err');
    }
    if ($a === 'sv_save') {
        $rows = is_array($_POST['sv'] ?? null) ? $_POST['sv'] : [];
        $n = 0;
        foreach ($rows as $id => $f) {
            if (!is_array($f)) continue;
            if (svServiceSave((string)$id, [
                'on' => !empty($f['on']), 'name' => (string)($f['name'] ?? ''), 'app' => (string)($f['app'] ?? ''),
                'cat' => (string)($f['cat'] ?? ''), 'price' => maNum($f['price'] ?? 0),
            ])) $n++;
        }
        go(fmtNum($n) . ' سرویس ذخیره شد.');
    }
    if ($a === 'sv_bulk') {
        $ids = array_values(array_filter(array_map('strval', (array)($_POST['ids'] ?? [])), fn($x) => $x !== ''));
        $on  = ($_POST['to'] ?? '') === 'on';
        if (($_POST['scope'] ?? '') === 'filter') {
            $fa = (string)($_POST['f'] ?? '');
            if (!isset(svApps()[$fa])) go('برای کارِ گروهی، اول «تلگرام» یا «اینستاگرام» را انتخاب کنید.', 'err');
            $fs = in_array($_POST['st'] ?? '', ['on', 'off'], true) ? (string)$_POST['st'] : 'all';
            $fc = (string)($_POST['c'] ?? '');
            if ($fc !== '' && !isset(svCats($fa)[$fc])) $fc = '';
            [$all] = svAdminList($fa, trim((string)($_POST['q'] ?? '')), $fs, 1, 5000, $fc, (string)($_POST['pc'] ?? ''), (string)($_POST['pv'] ?? ''));
            $ids = array_map(fn($s) => (string)$s['id'], $all);
        }
        $n   = svServiceBulk($ids, $on);
        go(fmtNum($n) . ' سرویس ' . ($on ? 'روشن' : 'خاموش') . ' شد.');
    }
    if ($a === 'sp_save') {
        $in = is_array($_POST['slot'] ?? null) ? $_POST['slot'] : [];
        $on = !empty($_POST['pick']);
        $spA = isset(svApps()[$_POST['f'] ?? '']) ? (string)$_POST['f'] : '';
        $src = [];
        if ($spA !== '')
            foreach (spCats($spA) as $dc => $_)
                if (isset(svSrcModes()[$_POST['src'][$dc] ?? ''])) $src[$dc] = (string)$_POST['src'][$dc];
        svSet(function (&$c) use ($in, $on, $spA, $src) {
            $c['pick'] = $on ? 1 : 0;
            if ($spA !== '') foreach ($src as $dc => $v) $c['src'][$spA][$dc] = $v;
            if (!is_array($c['slots'] ?? null)) $c['slots'] = [];
            foreach ($in as $id => $f) {
                $id = preg_replace('/[^a-z0-9.]/', '', (string)$id);
                if ($id === '' || !is_array($f)) continue;
                $row = ['off' => empty($f['on']) ? 1 : 0,
                        'sid' => mb_substr(preg_replace('/[^\w\-]/u', '', (string)($f['sid'] ?? '')), 0, 40),
                        'title' => mb_substr(trim((string)($f['title'] ?? '')), 0, 60),
                        'price' => max(0.0, maNum($f['price'] ?? 0))];
                if (!$row['off'] && $row['sid'] === '' && $row['title'] === '' && $row['price'] <= 0) unset($c['slots'][$id]);
                else $c['slots'][$id] = $row;
            }
        });
        go('گلچینِ محصولات ذخیره شد' . ($on ? '.' : ' — خاموش شد؛ مینی‌اپ‌ها همه‌ی سرویس‌های روشن را نشان می‌دهند.'));
    }
    if ($a === 'sv_fa') {
        $all = ($_POST['all'] ?? '') === '1';
        $n = svFaRename($all);
        go('نامِ فارسیِ ' . fmtNum($n) . ' سرویس ساخته شد' . ($all ? ' (نام‌های دستی هم عوض شدند).' : '؛ نام‌هایی که دستی نوشته بودید دست نخوردند.'));
    }
    if ($a === 'svo_act') {
        [$ok, $msg] = svAdminResolve((string)($_POST['id'] ?? ''), (string)($_POST['how'] ?? ''));
        go($msg, $ok ? 'ok' : 'err');
    }
    if ($a === 'sv_sync') {
        $n = svSync(100, 0, 5);
        go('وضعیتِ ' . fmtNum($n) . ' سفارش عوض شد.');
    }

    go();
}

function qsWith($params) {
    $q = array_merge($_GET, $params);
    unset($q['bot']);
    return '?' . http_build_query($q);
}

function pager($page, $pages) {
    if ($pages <= 1) return;
    echo '<div class="pager">';
    if ($page > 1) echo '<a href="' . h(qsWith(['page' => $page - 1])) . '">‹ قبلی</a>';
    $start = max(1, $page - 2); $end = min($pages, $page + 2);
    if ($start > 1) { echo '<a href="' . h(qsWith(['page' => 1])) . '">1</a>'; if ($start > 2) echo '<span class="dots">…</span>'; }
    for ($i = $start; $i <= $end; $i++) {
        echo $i === $page ? '<span class="cur">' . $i . '</span>' : '<a href="' . h(qsWith(['page' => $i])) . '">' . $i . '</a>';
    }
    if ($end < $pages) { if ($end < $pages - 1) echo '<span class="dots">…</span>'; echo '<a href="' . h(qsWith(['page' => $pages])) . '">' . $pages . '</a>'; }
    if ($page < $pages) echo '<a href="' . h(qsWith(['page' => $page + 1])) . '">بعدی ›</a>';
    echo '</div>';
}

function oBadge($s) {
    $m = ['pending' => ['⏳ پرداخت‌نشده', 'gray'], 'review' => ['🧾 بررسی', 'amber'],
          'approved' => ['✅ تایید', 'green'], 'rejected' => ['❌ رد', 'red']];
    [$l, $c] = $m[$s] ?? ['—', 'gray'];
    return '<span class="badge ' . $c . '">' . $l . '</span>';
}

function fk($action, $ret = null) {
    global $CSRF, $tab;
    $o = '<input type="hidden" name="csrf" value="' . h($CSRF) . '">'
       . '<input type="hidden" name="tab" value="' . h($tab) . '">'
       . '<input type="hidden" name="action" value="' . h($action) . '">';
    if ($ret !== null)
        $o .= '<input type="hidden" name="ret" value="'
            . h(http_build_query(array_merge(array_diff_key($_GET, ['tab' => 1]), $ret))) . '">';
    return $o;
}

function uLink($o) {
    $id = (int)($o['user_id'] ?? 0);
    $n  = ltrim(trim((string)($o['username'] ?? '')), '@');
    return '<a href="?tab=users&amp;id=' . $id . '">' . h($n !== '' ? atU($n) : pLabel($id)) . '</a>';
}

function nBadge($o) {
    $s = (string)($o['status'] ?? '');
    $c = [MaOrder::PAID => 'blue', MaOrder::DONE => 'green', MaOrder::REJECT => 'red'][$s] ?? 'gray';
    return '<span class="badge ' . $c . '">' . h(MaOrder::statusLabel($s)) . '</span>';
}

function topupMethod($o) {
    $m = (string)($o['method'] ?? '');
    if ($m === 'iran') return 'درگاه ایرانی';
    if ($m === 'crypto' || !empty($o['gw'])) return 'ارز دیجیتال';
    return '—';
}

function dashSparkline($buckets) {
    $vals = array_values($buckets);
    $max = max(1, max($vals));
    $w = 280; $h = 52; $step = $w / max(1, count($vals) - 1);
    $pts = [];
    foreach ($vals as $i => $v) $pts[] = round($i * $step, 1) . ',' . round($h - ($v / $max) * ($h - 6) - 3, 1);
    $line = implode(' ', $pts);
    $fillPts = '0,' . $h . ' ' . $line . ' ' . $w . ',' . $h;
    $total = array_sum($vals);
    $out = '<svg class="sparkline" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" role="img" ' .
           'aria-label="شماره‌های تحویل‌شده در ۷ روزِ اخیر: مجموعا ' . $total . '">' .
           '<polygon points="' . h($fillPts) . '" fill="var(--primary)" opacity=".18"></polygon>' .
           '<polyline points="' . h($line) . '" fill="none" stroke="var(--primary)" stroke-width="2.2" ' .
           'stroke-linecap="round" stroke-linejoin="round"></polyline></svg>';
    return [$out, $total];
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
session_write_close();

$C      = cfg();
$tab    = $_GET['tab'] ?? 'dashboard';
$pg     = max(1, (int)($_GET['page'] ?? 1));
$qs     = trim((string)($_GET['q'] ?? ''));

function icon($name, $cls = '') {
    static $p = [
        'grid'     => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'users'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'gift'     => '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M12 8S10.5 3 8 3a2.5 2.5 0 0 0 0 5M12 8s1.5-5 4-5a2.5 2.5 0 0 1 0 5"/>',
        'headset'  => '<path d="M3 14v-2a9 9 0 0 1 18 0v2"/><path d="M21 15a2 2 0 0 1-2 2h-1v-5h1a2 2 0 0 1 2 2zM3 15a2 2 0 0 0 2 2h1v-5H5a2 2 0 0 0-2 2z"/><path d="M19 17v1a3 3 0 0 1-3 3h-3"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-2.7 1.1V21a2 2 0 1 1-4 0v-.1A1.6 1.6 0 0 0 7.9 19.4a1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0-1.1-2.7H2a2 2 0 1 1 0-4h.1A1.6 1.6 0 0 0 3.6 7.9a1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H8a1.6 1.6 0 0 0 1-1.5V2a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 2.7 1.1 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V8a1.6 1.6 0 0 0 1.5 1H22a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'menu'     => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18z"/>',
        'sim'      => '<path d="M7 3h7l5 5v11a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><rect x="8.5" y="11" width="7" height="6" rx="1"/><path d="M12 11v6M8.5 14h7"/>',
        'wallet'   => '<rect x="3" y="6" width="18" height="14" rx="2.5"/><path d="M3 10h18M16 15h2M6 6V5a2 2 0 0 1 2-2h9"/>',
        'plug'     => '<path d="M9 2v5M15 2v5M6 7h12v4a6 6 0 0 1-12 0zM12 17v5"/>',
        'panelL'   => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M15 3v18"/>',
        'layers'   => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'receipt'  => '<path d="M5 2.5h14v19l-2.3-1.6-2.4 1.6-2.3-1.6-2.3 1.6-2.4-1.6L5 21.5z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
    ];
    $d = $p[$name] ?? $p['grid'];
    return '<svg class="ic ' . h($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

$TABS = [
  'dashboard' => ['grid',     'داشبورد'],
  'numorders' => ['sim',      'سفارش‌های شماره'],
  'numbers'   => ['globe',    'کشورها و قیمت‌ها'],
  'svorders'  => ['receipt',  'سفارش‌های خدمات'],
  'svc'       => ['layers',   'سرویس‌ها و قیمت‌ها'],
  'orders'    => ['wallet',   'شارژ کیف پول'],
  'users'     => ['users',    'کاربران'],
  'referral'  => ['gift',     'رفرال'],
  'support'   => ['headset',  'پشتیبانی'],
  'apis'      => ['plug',     'API و اتصال‌ها'],
  'settings'  => ['settings', 'تنظیمات'],
];
if (!isset($TABS[$tab])) $tab = 'dashboard';

$NAV = [
  'نمای کلی' => [
    ['dashboard', []],
  ],
  'شماره مجازی' => [
    ['numorders', [
      ['همه',        'st=all',      fn() => (($_GET['st'] ?? 'all') === 'all')],
      ['منتظرِ کد',   'st=paid',     fn() => (($_GET['st'] ?? '') === 'paid')],
      ['تحویل‌شده',   'st=done',     fn() => (($_GET['st'] ?? '') === 'done')],
      ['برگشتِ وجه',  'st=rejected', fn() => (($_GET['st'] ?? '') === 'rejected')],
    ]],
    ['numbers', [
      ['همه‌ی کشورها', 'f=all', fn() => (($_GET['f'] ?? 'all') === 'all')],
      ['فعال',         'f=on',  fn() => (($_GET['f'] ?? '') === 'on')],
      ['خاموش',        'f=off', fn() => (($_GET['f'] ?? '') === 'off')],
    ]],
  ],
  'خدمات تلگرام و اینستاگرام' => [
    ['svorders', [
      ['همه',            'st=all',      fn() => (($_GET['st'] ?? 'all') === 'all')],
      ['در حال انجام',   'st=run',      fn() => (($_GET['st'] ?? '') === 'run')],
      ['نیاز به بررسی',  'st=check',    fn() => (($_GET['st'] ?? '') === 'check')],
      ['انجام‌شده',      'st=done',     fn() => (($_GET['st'] ?? '') === 'done')],
    ]],
    ['svc', [
      ['تلگرام',       'f=tg',   fn() => (($_GET['f'] ?? 'tg') === 'tg')],
      ['اینستاگرام',   'f=ig',   fn() => (($_GET['f'] ?? '') === 'ig')],
      ['بقیه‌ی شبکه‌ها', 'f=none', fn() => (($_GET['f'] ?? '') === 'none')],
    ]],
  ],
  'مالی و کاربران' => [
    ['orders', [
      ['موفق',        'st=approved', fn() => (($_GET['st'] ?? 'approved') === 'approved')],
      ['پرداخت‌نشده', 'st=pending',  fn() => (($_GET['st'] ?? '') === 'pending')],
      ['همه',         'st=all',      fn() => (($_GET['st'] ?? '') === 'all')],
    ]],
    ['users', [
      ['همه کاربران', '',         fn() => empty($_GET['banned'])],
      ['مسدودها',     'banned=1', fn() => !empty($_GET['banned'])],
    ]],
    ['referral', []],
    ['support',  []],
  ],
  'سیستم' => [
    ['apis',     []],
    ['settings', [
      ['همه',          's=all',   fn() => (($_GET['s'] ?? 'all') === 'all')],
      ['تشخیص و سرعت', 's=speed', fn() => (($_GET['s'] ?? '') === 'speed')],
      ['درگاه پرداخت', 's=gw',    fn() => (($_GET['s'] ?? '') === 'gw')],
      ['امنیت',        's=sec',   fn() => (($_GET['s'] ?? '') === 'sec')],
      ['دیتابیس',      's=db',    fn() => (($_GET['s'] ?? '') === 'db')],
    ]],
  ],
];

$SEC_KEYS = ['all', 'speed', 'gw', 'sec', 'db'];
$sec = (string)($_GET['s'] ?? 'all');
if (!in_array($sec, $SEC_KEYS, true)) $sec = 'all';

$PER = 25;

$waitN    = MaOrder::countBy(MaOrder::PAID);
$svCheckN = function_exists('svDb') && svDb() ? (int)svDb()->querySingle("SELECT COUNT(*) FROM svo WHERE status = 'check'") : 0;
ob_start('panelGlass');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<?php if (trim(panelFontCss()) === ''): ?>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" media="print" onload="this.media='all'"
      href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap">
<?php endif; ?>
<link rel="icon" href="data:,">
<title><?= h($TABS[$tab][1]) ?> — پنل مدیریت</title>
<style>
<?= panelFontCss() ?>
<?= panelGlassCss() ?>
:root{
  --primary:#7c6cff; --primary-2:#3b82f6; --primary-dim:#5847e6; --primary-soft:rgba(124,108,255,.14);
  --cyan:#22d3ee;
  --success:#10b981; --success-soft:rgba(16,185,129,.13);
  --warning:#f5a524; --warning-soft:rgba(245,165,36,.13);
  --danger:#f2546b;  --danger-soft:rgba(242,84,107,.13);
  --bg:#05060f;
  --surface:rgba(17,22,44,.82);
  --surface-2:rgba(25,31,60,.86);
  --surface-3:rgba(38,46,84,.9);
  --field:#0a0f22;
  --border:rgba(150,160,255,.15); --border-soft:rgba(150,160,255,.08);
  --text:#eef0ff; --text-2:#a6aed0; --text-3:#7d86ab;
  --r-sm:9px; --r:13px; --r-lg:18px;
  --shadow:0 18px 40px -30px rgba(0,0,0,.95);
  --hl:inset 0 1px 0 rgba(255,255,255,.06);
  --sidebar-w:252px; --header-h:58px;
  --grad:linear-gradient(135deg,#7c6cff 0%,#4f7bff 55%,#22b8e6 100%);
  --font:'Vazirmatn','Vazir',Tahoma,system-ui,-apple-system,'Segoe UI',sans-serif;
}
*{box-sizing:border-box;margin:0;padding:0}
html{-webkit-text-size-adjust:100%;background:var(--bg)}
body{background:transparent;color:var(--text);font-family:var(--font);font-size:14px;font-weight:400;line-height:1.75;
  min-height:100vh;-webkit-font-smoothing:antialiased}
.sky{position:fixed;inset:0;z-index:-1;pointer-events:none;overflow:hidden;contain:strict;
  background:
    radial-gradient(1100px 680px at 88% -12%,rgba(124,108,255,.24),transparent 62%),
    radial-gradient(820px 600px at -6% 18%,rgba(34,184,230,.15),transparent 62%),
    radial-gradient(1000px 760px at 46% 118%,rgba(219,70,160,.12),transparent 62%),
    linear-gradient(180deg,#05060f 0%,#070a1a 52%,#04050c 100%)}
.sky::before,.sky::after{content:'';position:absolute;inset:0;will-change:opacity;animation:tw 6s ease-in-out infinite alternate}
.sky::before{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='520' height='520'%3E%3Ccircle cx='168' cy='78' r='0.5' fill='%23bfe6ff' opacity='0.40'/%3E%3Ccircle cx='49' cy='303' r='0.8' fill='%23cfd8ff' opacity='0.37'/%3E%3Ccircle cx='217' cy='125' r='1.1' fill='%23bfe6ff' opacity='0.39'/%3E%3Ccircle cx='64' cy='116' r='0.5' fill='%23cfd8ff' opacity='0.73'/%3E%3Ccircle cx='26' cy='115' r='0.7' fill='%23fff' opacity='0.54'/%3E%3Ccircle cx='281' cy='297' r='0.7' fill='%23bfe6ff' opacity='0.42'/%3E%3Ccircle cx='332' cy='194' r='0.6' fill='%23bfe6ff' opacity='0.72'/%3E%3Ccircle cx='107' cy='354' r='1.1' fill='%23cfd8ff' opacity='0.86'/%3E%3Ccircle cx='304' cy='236' r='0.9' fill='%23fff' opacity='0.51'/%3E%3Ccircle cx='363' cy='127' r='0.9' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='379' cy='150' r='0.6' fill='%23cfd8ff' opacity='0.43'/%3E%3Ccircle cx='86' cy='178' r='1.3' fill='%23ffd9f3' opacity='0.62'/%3E%3Ccircle cx='40' cy='290' r='1.0' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='309' cy='302' r='1.3' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='491' cy='247' r='0.6' fill='%23ffd9f3' opacity='0.39'/%3E%3Ccircle cx='161' cy='301' r='1.3' fill='%23cfd8ff' opacity='0.53'/%3E%3Ccircle cx='461' cy='180' r='1.3' fill='%23bfe6ff' opacity='0.58'/%3E%3Ccircle cx='61' cy='31' r='0.9' fill='%23fff' opacity='0.43'/%3E%3Ccircle cx='207' cy='477' r='1.3' fill='%23cfd8ff' opacity='0.40'/%3E%3Ccircle cx='209' cy='144' r='0.7' fill='%23bfe6ff' opacity='0.88'/%3E%3Ccircle cx='145' cy='216' r='1.0' fill='%23cfd8ff' opacity='0.79'/%3E%3Ccircle cx='498' cy='78' r='0.7' fill='%23ffd9f3' opacity='0.45'/%3E%3Ccircle cx='121' cy='252' r='0.7' fill='%23fff' opacity='0.52'/%3E%3Ccircle cx='76' cy='278' r='1.0' fill='%23ffd9f3' opacity='0.97'/%3E%3Ccircle cx='447' cy='494' r='0.5' fill='%23ffd9f3' opacity='0.65'/%3E%3Ccircle cx='415' cy='204' r='1.1' fill='%23cfd8ff' opacity='0.61'/%3E%3Ccircle cx='330' cy='32' r='0.6' fill='%23cfd8ff' opacity='0.99'/%3E%3Ccircle cx='84' cy='177' r='0.5' fill='%23bfe6ff' opacity='0.42'/%3E%3Ccircle cx='79' cy='53' r='1.0' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='455' cy='319' r='0.7' fill='%23fff' opacity='0.76'/%3E%3Ccircle cx='313' cy='247' r='0.6' fill='%23cfd8ff' opacity='0.90'/%3E%3Ccircle cx='250' cy='162' r='0.7' fill='%23fff' opacity='0.42'/%3E%3Ccircle cx='385' cy='249' r='0.7' fill='%23fff' opacity='0.69'/%3E%3Ccircle cx='495' cy='275' r='0.7' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='394' cy='155' r='0.6' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='270' cy='472' r='1.0' fill='%23bfe6ff' opacity='0.85'/%3E%3Ccircle cx='282' cy='261' r='0.8' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='419' cy='426' r='0.8' fill='%23cfd8ff' opacity='0.48'/%3E%3Ccircle cx='185' cy='15' r='0.5' fill='%23cfd8ff' opacity='0.86'/%3E%3Ccircle cx='135' cy='360' r='1.0' fill='%23ffd9f3' opacity='0.64'/%3E%3Ccircle cx='514' cy='497' r='1.0' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='118' cy='102' r='0.8' fill='%23bfe6ff' opacity='0.66'/%3E%3Ccircle cx='437' cy='249' r='1.0' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='434' cy='62' r='1.1' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='249' cy='93' r='1.0' fill='%23ffd9f3' opacity='0.41'/%3E%3Ccircle cx='206' cy='209' r='0.6' fill='%23fff' opacity='0.82'/%3E%3Ccircle cx='516' cy='14' r='1.3' fill='%23fff' opacity='0.87'/%3E%3Ccircle cx='318' cy='310' r='1.3' fill='%23fff' opacity='0.78'/%3E%3Ccircle cx='81' cy='285' r='0.5' fill='%23ffd9f3' opacity='0.36'/%3E%3Ccircle cx='338' cy='274' r='0.7' fill='%23fff' opacity='0.63'/%3E%3Ccircle cx='430' cy='110' r='0.9' fill='%23bfe6ff' opacity='0.49'/%3E%3Ccircle cx='125' cy='305' r='0.9' fill='%23fff' opacity='0.70'/%3E%3Ccircle cx='32' cy='385' r='1.3' fill='%23bfe6ff' opacity='0.78'/%3E%3Ccircle cx='219' cy='477' r='0.7' fill='%23bfe6ff' opacity='0.70'/%3E%3Ccircle cx='265' cy='454' r='0.7' fill='%23fff' opacity='0.75'/%3E%3Ccircle cx='90' cy='246' r='0.6' fill='%23fff' opacity='0.71'/%3E%3Ccircle cx='355' cy='276' r='1.3' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='459' cy='30' r='0.8' fill='%23fff' opacity='0.53'/%3E%3Ccircle cx='264' cy='292' r='0.6' fill='%23bfe6ff' opacity='0.64'/%3E%3Ccircle cx='506' cy='315' r='0.8' fill='%23cfd8ff' opacity='0.80'/%3E%3Ccircle cx='264' cy='420' r='0.8' fill='%23fff' opacity='0.80'/%3E%3Ccircle cx='480' cy='464' r='0.8' fill='%23fff' opacity='0.90'/%3E%3Ccircle cx='217' cy='204' r='1.0' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='223' cy='111' r='0.9' fill='%23fff' opacity='0.86'/%3E%3Ccircle cx='489' cy='335' r='1.0' fill='%23fff' opacity='0.44'/%3E%3Ccircle cx='503' cy='114' r='0.6' fill='%23cfd8ff' opacity='0.61'/%3E%3Ccircle cx='85' cy='347' r='0.8' fill='%23cfd8ff' opacity='0.45'/%3E%3Ccircle cx='517' cy='210' r='1.1' fill='%23fff' opacity='0.48'/%3E%3Ccircle cx='48' cy='190' r='1.0' fill='%23cfd8ff' opacity='0.71'/%3E%3Ccircle cx='366' cy='200' r='0.9' fill='%23fff' opacity='0.68'/%3E%3C/svg%3E");background-size:520px 520px;opacity:.75}
.sky::after{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='860' height='860'%3E%3Ccircle cx='389' cy='481' r='2.0' fill='%23bfe6ff' opacity='0.64'/%3E%3Ccircle cx='163' cy='691' r='2.0' fill='%23fff' opacity='0.76'/%3E%3Ccircle cx='81' cy='261' r='0.9' fill='%23ffd9f3' opacity='0.70'/%3E%3Ccircle cx='546' cy='512' r='1.7' fill='%23ffd9f3' opacity='0.98'/%3E%3Ccircle cx='635' cy='559' r='0.8' fill='%23fff' opacity='0.89'/%3E%3Ccircle cx='51' cy='164' r='1.2' fill='%23cfd8ff' opacity='0.74'/%3E%3Ccircle cx='281' cy='508' r='1.2' fill='%23ffd9f3' opacity='0.69'/%3E%3Ccircle cx='253' cy='4' r='0.9' fill='%23fff' opacity='0.65'/%3E%3Ccircle cx='350' cy='474' r='0.9' fill='%23fff' opacity='0.81'/%3E%3Ccircle cx='652' cy='441' r='0.8' fill='%23fff' opacity='0.40'/%3E%3Ccircle cx='344' cy='728' r='1.7' fill='%23fff' opacity='0.39'/%3E%3Ccircle cx='729' cy='0' r='1.2' fill='%23fff' opacity='0.95'/%3E%3Ccircle cx='404' cy='843' r='1.7' fill='%23bfe6ff' opacity='0.62'/%3E%3Ccircle cx='541' cy='670' r='1.4' fill='%23fff' opacity='0.57'/%3E%3Ccircle cx='286' cy='829' r='0.9' fill='%23ffd9f3' opacity='0.44'/%3E%3Ccircle cx='87' cy='52' r='2.0' fill='%23bfe6ff' opacity='0.47'/%3E%3Ccircle cx='162' cy='438' r='1.0' fill='%23cfd8ff' opacity='0.62'/%3E%3Ccircle cx='100' cy='362' r='1.2' fill='%23bfe6ff' opacity='0.35'/%3E%3Ccircle cx='262' cy='761' r='1.2' fill='%23bfe6ff' opacity='0.47'/%3E%3Ccircle cx='552' cy='86' r='1.0' fill='%23fff' opacity='0.49'/%3E%3Ccircle cx='8' cy='525' r='1.4' fill='%23fff' opacity='0.60'/%3E%3Ccircle cx='78' cy='501' r='1.2' fill='%23fff' opacity='0.36'/%3E%3Ccircle cx='320' cy='390' r='2.0' fill='%23fff' opacity='0.89'/%3E%3Ccircle cx='745' cy='157' r='1.0' fill='%23fff' opacity='0.55'/%3E%3Ccircle cx='703' cy='215' r='1.2' fill='%23ffd9f3' opacity='0.45'/%3E%3Ccircle cx='809' cy='169' r='1.7' fill='%23bfe6ff' opacity='0.92'/%3E%3Ccircle cx='68' cy='41' r='0.9' fill='%23fff' opacity='0.38'/%3E%3Ccircle cx='205' cy='606' r='1.4' fill='%23bfe6ff' opacity='0.62'/%3E%3Ccircle cx='422' cy='447' r='0.9' fill='%23cfd8ff' opacity='0.43'/%3E%3Ccircle cx='481' cy='733' r='0.9' fill='%23fff' opacity='0.53'/%3E%3Ccircle cx='644' cy='59' r='1.7' fill='%23fff' opacity='0.64'/%3E%3Ccircle cx='40' cy='242' r='1.0' fill='%23fff' opacity='0.41'/%3E%3Ccircle cx='766' cy='843' r='1.0' fill='%23fff' opacity='0.73'/%3E%3Ccircle cx='408' cy='307' r='1.4' fill='%23fff' opacity='0.98'/%3E%3C/svg%3E");background-size:860px 860px;opacity:.5;animation-duration:9s;animation-delay:-4s}
@keyframes tw{from{opacity:.32}to{opacity:.9}}
a{color:#a99fff;text-decoration:none}
a:hover{color:#c9c2ff}
b,strong{font-weight:700}
::selection{background:var(--primary);color:#fff}
svg.ic{width:18px;height:18px;flex:0 0 18px;display:block}

header.top{position:sticky;top:0;z-index:40;height:var(--header-h);background:rgba(6,8,20,.94);
  border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;padding:0 16px}
header.top::after{content:'';position:absolute;inset-inline:0;bottom:-1px;height:1px;background:var(--grad);opacity:.55}
header.top .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:15.5px;white-space:nowrap;letter-spacing:.2px}
header.top .brand svg.ic{width:20px;height:20px;color:#b3a9ff}
header.top .brand span{background:var(--grad);-webkit-background-clip:text;background-clip:text;color:transparent}
header.top .sp{flex:1}
header.top .who{color:var(--text-3);font-size:12px;white-space:nowrap}
.iconbtn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:var(--r-sm);
  border:1px solid var(--border);background:var(--surface);color:var(--text-2);cursor:pointer;flex:0 0 auto}
.iconbtn:hover{color:var(--text);background:var(--surface-2)}

.psearch{position:relative;flex:0 1 320px;min-width:0}
.psearch svg.ic{position:absolute;inset-inline-start:11px;top:50%;transform:translateY(-50%);color:var(--text-3);pointer-events:none;width:16px;height:16px}
.psearch input{width:100%;height:36px;padding:0 36px 0 12px;background:var(--field);border:1px solid var(--border);
  border-radius:999px;color:var(--text);font:inherit;font-size:13px}
.psearch input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-soft)}
.presults{position:absolute;top:calc(100% + 6px);inset-inline:0;z-index:60;background:#0d1230;border:1px solid var(--border);
  border-radius:var(--r);box-shadow:0 20px 50px -20px rgba(0,0,0,.9);padding:5px;max-height:60vh;overflow:auto}
.presults a{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:var(--r-sm);color:var(--text-2);font-size:13px}
.presults a:hover,.presults a.hi{background:var(--surface-3);color:var(--text)}
.presults .none{padding:10px;color:var(--text-3);font-size:12.5px;text-align:center}

.shell{display:flex;align-items:flex-start}
.sidebar{width:var(--sidebar-w);flex:0 0 var(--sidebar-w);position:sticky;top:var(--header-h);height:calc(100vh - var(--header-h));
  overflow-y:auto;border-inline-start:1px solid var(--border-soft);padding:14px 10px 28px;scrollbar-width:thin;
  overscroll-behavior:contain;background:rgba(6,8,20,.72)}
.content{flex:1;min-width:0}
.wrap{max-width:1200px;margin:0 auto;padding:24px 22px 64px}
body.nav-collapsed .sidebar{display:none}
.navgroup{margin-bottom:14px}
.navgroup>.t{padding:6px 11px;color:var(--text-3);font-size:11px;font-weight:800;letter-spacing:.6px}
.navlink{display:flex;align-items:center;gap:10px;padding:9px 11px;border-radius:11px;color:var(--text-2);font-size:13.5px;
  font-weight:500;margin-bottom:2px;transition:background-color .12s,color .12s}
.navlink:hover{background:rgba(124,108,255,.09);color:var(--text)}
.navlink.on{background:var(--grad);color:#fff;font-weight:700;box-shadow:0 8px 22px -12px rgba(124,108,255,.9)}
.navlink svg.ic{color:var(--text-3)}
.navlink:hover svg.ic,.navlink.on svg.ic{color:currentColor}
.navlink .nbadge{margin-inline-start:auto;background:var(--danger);color:#fff;font-size:10.5px;font-weight:800;padding:1px 7px;
  border-radius:20px;min-width:20px;text-align:center}
.navlink.on .nbadge{background:rgba(255,255,255,.25)}
.navlink .nbadge.b{background:var(--primary-2)}
.subnav{margin:3px 0 7px;padding-inline-start:26px;display:flex;flex-direction:column;gap:1px;border-inline-start:1px solid var(--border-soft);margin-inline-start:20px}
.subnav a{padding:6px 10px;border-radius:7px;color:var(--text-3);font-size:12.5px}
.subnav a:hover{background:rgba(124,108,255,.08);color:var(--text-2)}
.subnav a.on{color:#c9c2ff;font-weight:700;background:var(--primary-soft)}
.nav-toggle-cb,.nav-backdrop{display:none}

.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);margin-bottom:16px;overflow:hidden;
  box-shadow:var(--hl),var(--shadow)}
.card>h2,.card>summary>h2{font-size:14.5px;font-weight:700;padding:14px 18px;border-bottom:1px solid var(--border-soft);
  background:linear-gradient(90deg,rgba(124,108,255,.10),rgba(34,184,230,.04) 60%,transparent);display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.card>h2 .sub,.card .sub{font-weight:400;color:var(--text-3);font-size:12px}
.card>h2 .more{margin-inline-start:auto;color:#a99fff}
.card>.body{padding:18px}
.card>.body>:last-child{margin-bottom:0}
details.card>summary{cursor:pointer;list-style:none}
details.card>summary::-webkit-details-marker{display:none}
details.card>summary h2{border-bottom:0}
details.card[open]>summary h2{border-bottom:1px solid var(--border-soft)}
details.card>summary>h2:after{content:'';margin-inline-start:auto;width:7px;height:7px;flex:0 0 7px;border:solid var(--text-3);
  border-width:0 0 2px 2px;transform:rotate(-45deg);transition:transform .15s}
details.card[open]>summary>h2:after{transform:rotate(135deg)}
.psec{scroll-margin-top:76px}
.hub{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-bottom:16px}
.hubt{display:flex;align-items:center;gap:12px;padding:14px 15px;border-radius:var(--r-lg);background:var(--surface);border:1px solid var(--border);
  box-shadow:var(--hl);color:var(--text);transition:border-color .15s,transform .15s}
.hubt:hover{border-color:rgba(124,108,255,.55);transform:translateY(-1px);color:var(--text)}
.hubt .hx{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1}
.hubt .hx b{font-size:14px;font-weight:800}
.hubt .hx small{font-size:11.5px;color:var(--text-3);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
input[type=number]{direction:ltr;text-align:left}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:12px;margin-bottom:18px}
.stat{position:relative;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:15px 16px;box-shadow:var(--hl);overflow:hidden}
.stat::before{content:'';position:absolute;inset-inline-start:0;top:14px;bottom:14px;width:3px;border-radius:3px;background:var(--grad);opacity:.85}
.stat .n{font-size:23px;font-weight:800;letter-spacing:-.4px;line-height:1.25;font-variant-numeric:tabular-nums}
.stat .n.amount{font-size:19px}
.stat .n small{font-size:13px;font-weight:600}
.stat .n.sm{font-size:17px}
.stat .l{color:var(--text-3);font-size:12px;margin-top:3px}
.stat.acc .n{color:#b3a9ff}.stat.ok .n{color:#34d8a5}.stat.warn .n{color:var(--warning)}
.stat.ok::before{background:linear-gradient(180deg,#10b981,#22d3ee)}
.stat.warn::before{background:linear-gradient(180deg,#f5a524,#f2546b)}
a.stat{display:block;color:inherit;transition:border-color .15s,transform .15s}
a.stat:hover{border-color:rgba(124,108,255,.55);color:inherit;transform:translateY(-1px)}
.stat.live .n{color:#7fb2ff}
.stat.live .l::before{content:'';display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--success);margin-inline-end:6px;
  vertical-align:middle;animation:blink 1.6s ease-in-out infinite}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}

.tw{overflow-x:auto;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch}
table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:540px}
th{text-align:right;padding:11px 13px;color:var(--text-3);font-weight:700;font-size:11.5px;border-bottom:1px solid var(--border);
  white-space:nowrap;background:#0c1130}
td{padding:11px 13px;border-bottom:1px solid var(--border-soft);vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover{background:rgba(124,108,255,.06)}
td.num,.num{font-variant-numeric:tabular-nums;white-space:nowrap}
.empty{text-align:center;color:var(--text-3);padding:40px 18px;font-size:13.5px}
.empty .ic{font-size:30px;line-height:1.45;display:block;margin:0 auto 10px;opacity:.55}
.empty svg.ic{width:30px;height:30px;opacity:.45}
table.its{min-width:640px}
table.its td{padding:7px 9px}
table.its input:not([type=checkbox]){padding:7px 10px}
table.its td:nth-child(3) input{max-width:150px}
tr.grp td{background:var(--surface-2);font-weight:800}

.badge{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11.5px;font-weight:700;border:1px solid transparent;white-space:nowrap}
.badge.green{background:var(--success-soft);color:#34d8a5;border-color:rgba(16,185,129,.32)}
.badge.red{background:var(--danger-soft);color:#ff8597;border-color:rgba(242,84,107,.32)}
.badge.amber{background:var(--warning-soft);color:#ffc35a;border-color:rgba(245,165,36,.32)}
.badge.gray{background:var(--surface-3);color:var(--text-2);border-color:var(--border)}
.badge.blue{background:var(--primary-soft);color:#b3a9ff;border-color:rgba(124,108,255,.35)}

label{display:block;color:var(--text-3);font-size:12px;margin-bottom:5px;font-weight:600}
input,select,textarea{width:100%;padding:9.5px 12px;background:var(--field);color:var(--text);border:1px solid var(--border);
  border-radius:var(--r-sm);font:inherit;font-size:13.5px}
input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px var(--primary-soft)}
textarea{resize:vertical;min-height:84px;line-height:1.85}
select{cursor:pointer}
input[type=checkbox],input[type=radio]{width:16px;height:16px;accent-color:var(--primary);cursor:pointer}
.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:13px}
.grid3{display:grid;grid-template-columns:2fr 1fr 1.4fr;gap:13px;align-items:end}
.grid2>.chk,.grid3>.chk{align-self:end;min-height:42px}
.fld{margin-bottom:13px}
.hint{color:var(--text-3);font-size:11.5px;margin-top:4px;line-height:1.7}
.chk{display:flex;align-items:center;gap:8px;cursor:pointer;color:var(--text);font-size:13.5px;font-weight:500;margin-bottom:0}
.chk input{width:16px;height:16px;margin:0}
.row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.row.fe{align-items:flex-end}
.kc{margin-top:3px;color:#34d8a5;font-size:12.5px}
span.kc{margin:0 6px 0 0}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:9.5px 17px;border-radius:11px;border:0;
  background:var(--grad);color:#fff;font:inherit;font-size:13.5px;font-weight:700;cursor:pointer;white-space:nowrap;
  box-shadow:0 8px 20px -12px rgba(124,108,255,.95),inset 0 1px 0 rgba(255,255,255,.18);transition:filter .12s,transform .07s}
.btn:hover{filter:brightness(1.1);color:#fff}
.btn:active{transform:translateY(1px)}
.btn.sm{padding:6px 12px;font-size:12.5px;border-radius:9px}
.btn:not(.ghost) .muted{color:rgba(255,255,255,.8)}
.btn.g{background:linear-gradient(135deg,#10b981,#14b8a6);color:#03140e;box-shadow:0 8px 20px -12px rgba(16,185,129,.95),inset 0 1px 0 rgba(255,255,255,.2)}
.btn.r{background:linear-gradient(135deg,#f2546b,#e23d8a);box-shadow:0 8px 20px -12px rgba(242,84,107,.95)}
.btn.ghost{background:rgba(255,255,255,.03);color:var(--text-2);border:1px solid var(--border);box-shadow:none}
.btn.ghost:hover{color:var(--text);border-color:rgba(124,108,255,.5);filter:none;background:rgba(124,108,255,.08)}
.btn[disabled]{opacity:.45;cursor:not-allowed}

.flash{position:relative;padding:12px 40px 12px 15px;border-radius:var(--r);margin-bottom:16px;font-size:13.5px;border:1px solid}
.flash.ok{background:rgba(16,185,129,.12);color:#8af0cb;border-color:rgba(16,185,129,.38)}
.flash.err{background:rgba(242,84,107,.12);color:#ffb3bf;border-color:rgba(242,84,107,.38)}
.flash .fx{position:absolute;inset-inline-end:9px;top:8px;background:none;border:none;color:inherit;opacity:.55;cursor:pointer;font-size:15px;width:auto;padding:2px 6px}
.flash .fx:hover{opacity:1}
.note{background:rgba(124,108,255,.06);border:1px solid var(--border-soft);border-inline-start:3px solid var(--primary);border-radius:var(--r-sm);
  padding:11px 13px;color:var(--text-2);font-size:12.5px;line-height:1.85;margin-bottom:12px}
.note.warn{border-inline-start-color:var(--warning);background:rgba(245,165,36,.06)}
.note.ok{border-inline-start-color:var(--success);background:rgba(16,185,129,.06)}
.cfm{position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;padding:18px;background:rgba(3,4,12,.82)}
.cfm.on{display:flex}
.cfm-b{width:100%;max-width:420px;background:#0e1333;border:1px solid var(--border);border-radius:18px;padding:22px 18px 16px;
  box-shadow:0 30px 60px -20px rgba(0,0,0,.85);text-align:center;animation:cfmIn .16s ease-out}
.cfm-i{font-size:28px;line-height:1;margin-bottom:10px;display:flex;justify-content:center}
.cfm-b p{margin:0 0 16px;color:var(--text);font-size:14px;line-height:1.9}
.cfm-r{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.cfm-r .btn{min-width:130px}
@keyframes cfmIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

.crumb{color:var(--text-3);font-size:12.5px;margin-bottom:14px}
.crumb b{color:var(--text)} .crumb span{margin:0 6px;opacity:.5}
.muted{color:var(--text-3)}
code,.secret-box{font-family:ui-monospace,'SF Mono',Menlo,monospace;font-size:.9em;background:#0b1028;padding:2px 6px;border-radius:6px;
  border:1px solid var(--border);overflow-wrap:anywhere;color:#dcd8ff}
.secret-box{display:block;padding:10px 12px;direction:ltr;text-align:left}
code{unicode-bidi:plaintext}
.secretin{display:flex;gap:6px;align-items:center}
.secretin input{flex:1;min-width:0}
.pager{display:flex;gap:5px;justify-content:center;margin-top:16px;flex-wrap:wrap}
.pager a,.pager span{padding:6px 12px;border-radius:var(--r-sm);border:1px solid var(--border);color:var(--text-2);font-size:12.5px}
.pager a:hover{border-color:var(--primary);color:var(--text)}
.pager .cur{background:var(--grad);border-color:transparent;color:#fff;font-weight:700}
.searchbar{display:flex;gap:8px;margin-bottom:15px;flex-wrap:wrap}
.searchbar input{flex:1;min-width:190px}
.sparkline{display:block;width:100%;height:56px;margin-top:10px}
hr{border:none;border-top:1px solid var(--border-soft);margin:15px 0}
h3{font-size:14px;font-weight:700;margin-bottom:10px}
p{margin-bottom:10px}
p:last-child{margin-bottom:0}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}
.step{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:var(--r);border:1px solid var(--border);background:var(--surface-2);
  color:var(--text-2);font-size:13px;line-height:1.6}
a.step:hover{border-color:rgba(124,108,255,.55);color:var(--text)}
.step b{flex:0 0 28px;height:28px;border-radius:50%;display:grid;place-items:center;background:var(--surface-3);color:var(--text);font-size:13px}
.step small{display:block;color:var(--text-3);font-size:11px}
.step.ok{border-color:rgba(16,185,129,.38);color:var(--text)}
.step.ok b{background:linear-gradient(135deg,#10b981,#14b8a6);color:#03140e}
details.cat>summary h2{font-size:14px}
details.cat>summary h2 .sub{margin-inline-start:auto}
details.cat>summary>h2:after{margin-inline-start:12px}
details.cat .flag{font-size:19px;line-height:1}
details.cat .cc{font-size:11px;color:var(--text-3)}

@media(max-width:900px){
  .psearch{flex:1 1 auto}
  header.top .who{display:none}
  .sidebar{position:fixed;top:0;bottom:0;right:0;left:auto;z-index:70;height:100vh;height:100dvh;width:272px;flex-basis:272px;max-width:86vw;
    padding-top:16px;background:#070a1c;transform:translateX(100%);visibility:hidden;
    transition:transform .22s cubic-bezier(.2,.8,.3,1),visibility 0s linear .22s;box-shadow:-12px 0 34px rgba(0,0,0,.6)}
  body.nav-collapsed .sidebar{display:block}
  .nav-toggle-cb:checked ~ .shell .sidebar{transform:none;visibility:visible;transition:transform .22s cubic-bezier(.2,.8,.3,1)}
  .nav-toggle-cb:checked ~ .nav-backdrop{display:block;position:fixed;inset:0;z-index:65;background:rgba(3,4,12,.62)}
  .wrap{padding:16px 13px 60px}
  .stat .n{font-size:20px}
  .card>.body{padding:15px}
  .btn{box-shadow:none}
  .card,.stat,.hubt{box-shadow:none}
}
@media(max-width:700px){.grid3{grid-template-columns:1fr}}
@media(max-width:640px){.stats{grid-template-columns:1fr 1fr;gap:10px}.hub{grid-template-columns:1fr 1fr;gap:9px}.hubt{flex-direction:column;align-items:flex-start;gap:8px;padding:12px}
  .hubt .hx small{white-space:normal}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
</style>
</head>
<body>
<div class="sky" aria-hidden="true"></div>

<input type="checkbox" id="navToggle" class="nav-toggle-cb">

<header class="top">
  <label for="navToggle" class="iconbtn m-only" aria-label="منو"><?= icon('menu') ?></label>
  <div class="brand"><?= icon('panelL') ?> <span>نامبیکس</span></div>

  <div class="psearch">
    <?= icon('search') ?>
    <input id="pq" type="search" autocomplete="off" placeholder="جست‌وجو در پنل…"
           aria-label="جست‌وجو در بخش‌های پنل">
    <div class="presults" id="pres" hidden></div>
  </div>

  <div class="sp"></div>
  <span class="who">تنظیم‌های بازی و مینی‌اپ در خودِ ربات</span>
  <a class="btn ghost sm" href="monitor.php">🛰 رصدِ زنده</a>
  <a class="iconbtn" href="?logout=1" aria-label="خروج" title="خروج"><?= icon('logout') ?></a>
</header>

<label for="navToggle" class="nav-backdrop"></label>

<div class="shell">
  <aside class="sidebar" id="sb">
    <?php foreach ($NAV as $gTitle => $items): ?>
      <nav class="navgroup">
        <div class="t"><?= h($gTitle) ?></div>
        <?php foreach ($items as [$k, $subs]):
          [$ico, $lbl] = $TABS[$k];
          $isOn = ($tab === $k);
          $n    = ['numorders' => $waitN, 'svorders' => $svCheckN][$k] ?? 0; ?>
          <a class="navlink<?= $isOn ? ' on' : '' ?>" href="?tab=<?= h($k) ?>">
            <?= icon($ico) ?><span><?= h($lbl) ?></span>
            <?php if ($n > 0): ?><span class="nbadge<?= $k === 'numorders' ? ' b' : '' ?>"><?= (int)$n ?></span><?php endif; ?>
          </a>
          <?php if ($isOn && $subs): ?>
            <div class="subnav">
              <?php foreach ($subs as [$vlbl, $vq, $vOn]):
                $href = '?tab=' . urlencode($k) . ($vq !== '' ? '&amp;' . $vq : ''); ?>
                <a class="<?= $vOn() ? 'on' : '' ?>" href="<?= $href ?>"><?= h($vlbl) ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
  </aside>

  <main class="content"><div class="wrap">

<?php if ($flash): ?>
  <div class="flash <?= h($flash['type']) ?>">
    <button type="button" class="fx" aria-label="بستن" onclick="this.parentElement.remove()">✕</button>
    <?= nl2br(flashSafeHtml($flash['msg'])) ?>
  </div>
<?php endif; ?>

<?php if (pWeakPass()): ?>
  <div class="flash err">
    <b>رمزِ پنل ضعیف است.</b> از این پنل می‌شود به موجودیِ کاربران و کلیدِ فروشنده رسید —
    رمز را در <code>config.local.php</code> به دست‌کم ۱۲ نویسه با حرف و عدد و علامت عوض کنید.
  </div>
<?php endif; ?>

<?php if ($tab === 'dashboard'):
  $NS    = pNumStats();
  $iOn   = count(array_filter(maItems(), fn($x) => !empty($x['on'])));
  $ready = numReady();
  $base  = maBaseUrl();
  $maOn  = !empty(maCfg()['on']);
?>
  <?php if (!$ready || $iOn === 0 || $base === '' || !$maOn): ?>
  <div class="card"><h2>🚦 تا شروعِ فروش چه مانده؟</h2><div class="body">
    <div class="steps">
      <a class="step<?= $ready ? ' ok' : '' ?>" href="?tab=apis"><b><?= $ready ? '✓' : '۱' ?></b><span>اتصال به <?= h(numProvName()) ?></span></a>
      <a class="step<?= $iOn > 0 ? ' ok' : '' ?>" href="?tab=numbers"><b><?= $iOn > 0 ? '✓' : '۲' ?></b><span>وارد کردنِ کشورها و قیمت‌ها</span></a>
      <a class="step<?= $base !== '' ? ' ok' : '' ?>" href="?tab=apis"><b><?= $base !== '' ? '✓' : '۳' ?></b><span>آدرسِ عمومیِ مینی‌اپ</span></a>
      <div class="step<?= $maOn ? ' ok' : '' ?>"><b><?= $maOn ? '✓' : '۴' ?></b><span>باز بودنِ مینی‌اپ <small>داخلِ ربات ← 🚀 مینی‌اپ</small></span></div>
    </div>
  </div></div>
  <?php endif; ?>

  <div class="stats">
    <div class="stat acc"><div class="n"><?= h(fmtNum(pSwr('user_n', 120, fn() => pUserCount()))) ?></div><div class="l">کاربر</div></div>
    <a class="stat<?= $waitN > 0 ? ' live' : '' ?>" href="?tab=numorders&amp;st=paid"><div class="n"><?= h(fmtNum($waitN)) ?></div><div class="l">منتظرِ کد، همین حالا</div></a>
    <div class="stat ok"><div class="n"><?= h(fmtNum($NS['today'])) ?></div><div class="l">شماره‌ی تحویل‌شده‌ی امروز</div></div>
    <div class="stat ok"><div class="n amount"><?= h(fmtNum($NS['today_rev'])) ?></div><div class="l">فروشِ شماره‌ی امروز (تومان)</div></div>
    <div class="stat"><div class="n"><?= h(fmtNum($NS['done'])) ?></div><div class="l">کلِ شماره‌های تحویل‌شده</div></div>
    <div class="stat"><div class="n amount"><?= h(fmtNum($NS['revenue'])) ?></div><div class="l">کلِ فروشِ شماره (تومان)</div></div>
    <div class="stat"><div class="n amount"><?= h(fmtNum(pWalletTotal())) ?></div><div class="l">مجموعِ کیف‌پول‌ها</div></div>
    <a class="stat" href="?tab=orders"><div class="n"><?= h(fmtNum(Order::countBy(Order::APPROVED))) ?></div><div class="l">شارژِ موفقِ کیف پول</div></a>
  </div>

  <?php $DS = svStats(); ?>
  <div class="card"><h2>🧩 خدماتِ تلگرام و اینستاگرام <a class="sub more" href="?tab=svorders">سفارش‌ها ←</a></h2><div class="body">
    <div class="stats" style="margin:0">
      <a class="stat<?= $DS['run'] > 0 ? ' live' : '' ?>" href="?tab=svorders&amp;st=run"><div class="n"><?= h(fmtNum($DS['run'])) ?></div><div class="l">در حالِ انجام</div></a>
      <a class="stat<?= $DS['check'] > 0 ? ' warn' : '' ?>" href="?tab=svorders&amp;st=check"><div class="n"><?= h(fmtNum($DS['check'])) ?></div><div class="l">🔎 نیاز به بررسی</div></a>
      <div class="stat ok"><div class="n"><?= h(fmtNum($DS['today'])) ?></div><div class="l">سفارشِ خدماتِ امروز</div></div>
      <div class="stat ok"><div class="n amount"><?= h(fmtNum($DS['sum'])) ?></div><div class="l">فروشِ خدماتِ امروز (تومان)</div></div>
      <?php foreach (svPanels() as $pv): $pcf = svPc($pv); ?>
        <a class="stat<?= svReady($pv) ? '' : ' warn' ?>" href="?tab=apis#api-svc"><div class="n sm ltr"><?= svReady($pv) ? ((int)$pcf['pbal_at'] > 0 ? h(rtrim(rtrim(number_format((float)$pcf['pbal'], 2, '.', ','), '0'), '.') . ' ' . $pcf['pcur']) : '—') : '✕' ?></div>
          <div class="l">💰 <?= h(svPanelName($pv)) ?><?= svReady($pv) ? '' : ' — وصل نیست' ?></div></a>
      <?php endforeach; ?>
    </div>
  </div></div>

  <?php [$sparkSvg, $sparkTotal] = dashSparkline(pSparkBuckets(7)); ?>
  <div class="card"><h2>📈 شماره‌های تحویل‌شده در ۷ روزِ گذشته <span class="sub">— مجموعا <?= h(fmtNum($sparkTotal)) ?></span></h2><div class="body">
    <?= $sparkSvg ?>
  </div></div>

  <?php [$recent] = MaOrder::page('', '', 0, 8); ?>
  <div class="card"><h2>🧾 آخرین سفارش‌های شماره <a class="sub more" href="?tab=numorders">همه ←</a></h2>
    <?php if (!$recent): ?>
      <div class="empty"><span class="ic">☎️</span>هنوز شماره‌ای فروخته نشده.</div>
    <?php else: ?>
    <div class="tw"><table>
      <thead><tr><th>کاربر</th><th>شماره</th><th>تلفن</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): $act = numGet((string)$o['id']); ?>
        <tr>
          <td><?= uLink($o) ?></td>
          <td><?= h(trim(($o['item_emoji'] ?? '') . ' ' . ($o['item_name'] ?? ''))) ?></td>
          <td class="num"><?= !empty($act['phone']) ? '<code>' . h((string)$act['phone']) . '</code>' : '<span class="muted">—</span>' ?></td>
          <td class="num"><?= h(fmtNum($o['total'] ?? 0)) ?></td>
          <td><?= nBadge($o) ?></td>
          <td class="muted num"><?= h((string)($o['created_at'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card"><h2>🔗 وبهوکِ ربات</h2><div class="body">
    <p class="hint" style="margin-bottom:11px">اگر ربات جواب نمی‌دهد، یک بار این را بزنید. دکمه‌ی منوی ربات هم دوباره روی مینی‌اپ تنظیم می‌شود.</p>
    <form method="post" class="row"><?= fk('master_webhook') ?><button class="btn">ثبتِ دوباره‌ی وبهوک</button></form>
  </div></div>


<?php elseif ($tab === 'numorders'):
  $NST = ['all' => ['📋 همه', ''], 'paid' => ['📲 منتظرِ کد', MaOrder::PAID],
          'done' => ['✅ تحویل‌شده', MaOrder::DONE], 'rejected' => ['↩️ برگشتِ وجه', MaOrder::REJECT]];
  $nst = (string)($_GET['st'] ?? 'all');
  if (!isset($NST[$nst])) $nst = 'all';
  $cnt = [];
  foreach ([MaOrder::PENDING, MaOrder::PAID, MaOrder::DONE, MaOrder::REJECT] as $sv) $cnt[$sv] = MaOrder::countBy($sv);
  $cnt[''] = array_sum($cnt);
  [$rows, $total] = MaOrder::page($NST[$nst][1], $qs, ($pg - 1) * $PER, $PER);
  if (!$rows && $qs !== '') [$rows, $total] = pNumByPhone($qs, $NST[$nst][1], $PER);
  $pages = max(1, (int)ceil($total / $PER));
  $now   = time();
?>
  <div class="card"><h2>☎️ سفارش‌های شماره <span class="sub">— <?= h(fmtNum($total)) ?> ردیف</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="numorders">
      <input type="hidden" name="st" value="<?= h($nst) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="آیدیِ عددیِ کاربر، شماره‌ی سفارش یا شماره تلفن…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=numorders&amp;st=<?= h($nst) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach ($NST as $k => [$lbl, $sv]): ?>
        <a class="btn <?= $nst === $k ? '' : 'ghost' ?> sm"
           href="?tab=numorders&amp;st=<?= h($k) ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">
          <?= h($lbl) ?> <span class="muted"><?= h(fmtNum($cnt[$sv])) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">☎️</span><?= $qs !== '' ? 'چیزی با این جست‌وجو پیدا نشد.' : 'سفارشی با این فیلتر نیست.' ?></div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>شماره</th><th>تلفن و کد</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o):
      $act = numGet((string)$o['id']);
      $ast = (string)($act['status'] ?? ''); ?>
      <tr>
        <td><?= uLink($o) ?></td>
        <td><?= h(trim(($o['item_emoji'] ?? '') . ' ' . ($o['item_name'] ?? ''))) ?>
          <div class="hint"><code><?= h((string)$o['id']) ?></code><?= !empty($o['coupon']) ? ' · 🎟 ' . h((string)$o['coupon']) : '' ?><?= !empty($o['gift']) ? ' · 💎 هدیه' : '' ?></div></td>
        <td class="num">
          <?php if (!empty($act['phone'])): ?>
            <code><?= h((string)$act['phone']) ?></code>
            <?php if ((string)($act['code'] ?? '') !== ''): ?>
              <div class="kc">🔑 <b><?= h((string)$act['code']) ?></b></div>
            <?php elseif ($ast === 'waiting'): $left = max(0, numWaitFor($act) - ($now - (int)($act['created'] ?? $now))); ?>
              <div class="hint">⏳ <?= intdiv($left, 60) ?>:<?= str_pad((string)($left % 60), 2, '0', STR_PAD_LEFT) ?> مانده</div>
            <?php endif; ?>
          <?php else: ?><span class="muted">—</span><?php endif; ?>
        </td>
        <td class="num"><?= h(fmtNum($o['total'] ?? 0)) ?></td>
        <td><?= nBadge($o) ?>
          <?php if (!empty($o['last_error']) && ($o['status'] ?? '') === MaOrder::REJECT): ?><div class="hint"><?= h(mb_substr((string)$o['last_error'], 0, 80)) ?></div><?php endif; ?></td>
        <td class="muted num"><?= h((string)($o['created_at'] ?? '—')) ?></td>
        <td>
          <?php if ($ast === 'waiting' || $ast === 'buying'): ?>
          <form method="post" data-confirm="این شماره لغو و مبلغ به کیف پولِ کاربر برگردانده شود؟">
            <?= fk('num_cancel', []) ?><input type="hidden" name="id" value="<?= h((string)$o['id']) ?>">
            <button class="btn r sm">لغو و برگشتِ وجه</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($pg, $pages); ?>
  <?php endif; ?>
  </div>


<?php elseif ($tab === 'numbers'):
  $f = (string)($_GET['f'] ?? 'all');
  if (!in_array($f, ['all', 'on', 'off'], true)) $f = 'all';
  $cats  = maCats();
  $items = maItems();
  $byCat = [];
  foreach ($items as $it) $byCat[(string)($it['cat'] ?? '')][] = $it;
  $sold = maSoldByCat();
  $cOn  = count(array_filter($cats,  fn($x) => !empty($x['on'])));
  $iOn  = count(array_filter($items, fn($x) => !empty($x['on'])));
  $ql   = mb_strtolower($qs);
  $list = array_values(array_filter($cats, function ($c) use ($f, $ql) {
      if ($f === 'on'  && empty($c['on']))  return false;
      if ($f === 'off' && !empty($c['on'])) return false;
      return $ql === '' || str_contains(mb_strtolower((string)($c['name'] ?? '') . ' ' . ($c['code'] ?? '')), $ql);
  }));
  $pages = max(1, (int)ceil(count($list) / 20));
  $cpg   = min($pg, $pages);
  $list  = array_slice($list, ($cpg - 1) * 20, 20);
  $open  = (string)($_GET['open'] ?? '');
  $pi    = numProvInfo();
  $mkS   = rtrim(rtrim(number_format((float)numVal('markup', 0), 1), '0'), '.');
  $sync  = !empty(numVal('sync_price', true));
?>
  <div class="stats">
    <div class="stat acc"><div class="n"><?= h(fmtNum($cOn)) ?> <small class="muted">/ <?= h(fmtNum(count($cats))) ?></small></div><div class="l">🌍 کشورِ فعال</div></div>
    <div class="stat ok"><div class="n"><?= h(fmtNum($iOn)) ?> <small class="muted">/ <?= h(fmtNum(count($items))) ?></small></div><div class="l">☎️ شماره‌ی فعال</div></div>
    <div class="stat"><div class="n sm"><?= h($pi['name']) ?></div><div class="l">🏪 فروشنده · <?= numReady() ? 'وصل' : 'وصل نیست' ?></div></div>
    <div class="stat"><div class="n"><?= h($mkS) ?>٪</div><div class="l">📈 سود روی قیمتِ فروشنده</div></div>
  </div>

  <div class="card"><h2>📥 وارد کردن از <?= h($pi['name']) ?> <?= numReady() ? '<span class="badge green">آماده</span>' : '<span class="badge red">وصل نیست</span>' ?></h2><div class="body">
    <div class="note">
      فهرستِ کشورها و قیمتِ روزِ شماره‌های <b>تلگرام</b> از <?= h($pi['name']) ?> گرفته می‌شود و سودِ
      <b><?= h($mkS) ?>٪</b> رویش می‌نشیند. کشورِ تازه ساخته می‌شود، ردیفی که دیگر روی فروشنده نیست حذف می‌شود
      و نام، ایموجی و برچسبی که خودتان نوشته‌اید دست نمی‌خورد.
      <?= $sync ? 'قیمتِ ردیف‌های موجود هم <b>از فروشنده به‌روز می‌شود</b>.' : 'قیمتِ ردیف‌های موجود <b>دست نمی‌خورد</b>.' ?>
      <br>درصدِ سود و «قیمت از فروشنده» در <a href="?tab=apis">API و اتصال‌ها ← ☎️ شماره مجازی</a> است.
    </div>
    <?php if (numReady()): ?>
    <form method="post" data-confirm="فهرست از فروشنده گرفته و کاتالوگ به‌روز شود؟">
      <?= fk('num_import', []) ?><button class="btn g">📥 وارد کردن و به‌روزرسانی</button>
    </form>
    <?php else: ?>
      <a class="btn" href="?tab=apis">اول فروشنده را وصل کنید</a>
    <?php endif; ?>
  </div></div>

  <?php if ($items): ?>
  <details class="card"><summary><h2>🧮 تغییرِ گروهیِ قیمت</h2></summary><div class="body">
    <?php if ($sync): ?>
      <div class="note warn">«قیمت از فروشنده» روشن است؛ با ورودِ بعدی قیمت‌ها دوباره از فروشنده ساخته می‌شوند.
        برای سودِ ماندگار، درصدِ سود را در <a href="?tab=apis">API و اتصال‌ها</a> عوض کنید.</div>
    <?php endif; ?>
    <form method="post" class="row fe" data-confirm="قیمت‌ها عوض شوند؟">
      <?= fk('num_bulk', []) ?>
      <div style="flex:2;min-width:190px"><label>کدام شماره‌ها</label><select name="cid">
        <option value="">همه‌ی کشورها</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= h((string)$c['id']) ?>"><?= h(trim(($c['emoji'] ?? '') . ' ' . ($c['name'] ?? ''))) ?></option>
        <?php endforeach; ?></select></div>
      <div style="flex:1;min-width:120px"><label>جهت</label><select name="dir">
        <option value="up">گران‌تر</option><option value="down">ارزان‌تر</option></select></div>
      <div style="flex:1;min-width:110px"><label>چند درصد</label>
        <input name="pct" type="number" min="0.1" max="500" step="0.1" required></div>
      <button class="btn">اعمال</button>
    </form>
    <div class="hint">قیمتِ تازه به نزدیک‌ترین ۱۰۰ تومان گِرد می‌شود.</div>
  </div></details>
  <?php endif; ?>

  <div class="card"><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="numbers">
      <input type="hidden" name="f" value="<?= h($f) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="نامِ کشور یا کدش، مثلا روسیه…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=numbers&amp;f=<?= h($f) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach (['all' => 'همه', 'on' => 'فعال', 'off' => 'خاموش'] as $k => $lbl): ?>
        <a class="btn <?= $f === $k ? '' : 'ghost' ?> sm"
           href="?tab=numbers&amp;f=<?= $k ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
    </div>
  </div></div>

  <?php if (!$cats): ?>
    <div class="empty"><span class="ic">🌍</span>هنوز کشوری نیست — «📥 وارد کردن» را بزنید.</div>
  <?php elseif (!$list): ?>
    <div class="empty"><span class="ic">🔍</span>کشوری با این فیلتر نیست.</div>
  <?php else: ?>
    <?php foreach ($list as $c):
      $cid = (string)($c['id'] ?? '');
      $its = $byCat[$cid] ?? [];
      $ion = array_filter($its, fn($x) => !empty($x['on']));
      $min = $ion ? min(array_map('maItemPrice', $ion)) : 0; ?>
    <details class="card cat" id="c-<?= h($cid) ?>"<?= $open === $cid ? ' open' : '' ?>>
      <summary><h2><span class="flag"><?= h((string)($c['emoji'] ?? '🌍')) ?></span> <?= h((string)($c['name'] ?? '')) ?>
        <?php if (!empty($c['code'])): ?><code class="cc"><?= h((string)$c['code']) ?></code><?php endif; ?>
        <?= !empty($c['on']) ? '<span class="badge green">فعال</span>' : '<span class="badge">خاموش</span>' ?>
        <span class="sub"><?= h(fmtNum(count($ion))) ?> از <?= h(fmtNum(count($its))) ?> شماره<?= $min > 0 ? ' · از ' . h(fmtNum($min)) . ' تومان' : '' ?><?= !empty($sold[$cid]) ? ' · ' . h(fmtNum($sold[$cid])) . ' فروش' : '' ?></span></h2></summary>
      <div class="body">
        <form method="post">
          <?= fk('num_cat_save', ['open' => $cid]) ?><input type="hidden" name="cid" value="<?= h($cid) ?>">
          <div class="grid3">
            <div><label>نامِ کشور</label><input name="name" value="<?= h((string)($c['name'] ?? '')) ?>" maxlength="40" required></div>
            <div><label>ایموجی / پرچم</label><input name="emoji" value="<?= h((string)($c['emoji'] ?? '')) ?>" style="text-align:center"></div>
            <label class="chk"><input type="checkbox" name="on" value="1" <?= !empty($c['on']) ? 'checked' : '' ?>> در مینی‌اپ نمایش داده شود</label>
          </div>
          <?php if ($its): ?>
          <div class="tw" style="margin-top:14px"><table class="its">
            <thead><tr><th>فعال</th><th>اپراتور</th><th>قیمت (تومان)</th><th>برچسب</th><th>شناسه</th></tr></thead>
            <tbody>
            <?php foreach ($its as $it): $iid = h((string)($it['id'] ?? '')); ?>
              <tr>
                <td><input type="checkbox" name="it[<?= $iid ?>][on]" value="1" <?= !empty($it['on']) ? 'checked' : '' ?>></td>
                <td><input name="it[<?= $iid ?>][name]" value="<?= h((string)($it['name'] ?? '')) ?>" maxlength="40"></td>
                <td><input name="it[<?= $iid ?>][price]" value="<?= h(fmtNum($it['price'] ?? 0)) ?>" inputmode="numeric" style="direction:ltr"></td>
                <td><input name="it[<?= $iid ?>][badge]" value="<?= h((string)($it['badge'] ?? '')) ?>" maxlength="16" placeholder="مثلا پرفروش"></td>
                <td class="muted"><code><?= h((string)($it['svc'] ?? '') ?: '—') ?></code></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php else: ?>
            <div class="hint" style="margin-top:10px">این کشور هنوز شماره‌ای ندارد.</div>
          <?php endif; ?>
          <div style="margin-top:14px"><button class="btn g">ذخیره‌ی <?= h((string)($c['name'] ?? '')) ?></button></div>
        </form>
      </div>
    </details>
    <?php endforeach; ?>
    <?php pager($cpg, $pages); ?>
  <?php endif; ?>


<?php elseif ($tab === 'orders'):
  $st = (string)($_GET['st'] ?? 'approved');
  if (!in_array($st, ['approved', 'pending', 'all'], true)) $st = 'approved';
  [$rows, $total, $pages, $opg] = pOrdersPage($st, $qs, $pg, $PER);
  $counts = [
    'approved' => Order::countBy(Order::APPROVED),
    'pending'  => Order::countBy(Order::PENDING),
  ];
  $counts['all'] = $counts['approved'] + $counts['pending'] + Order::countBy(Order::REVIEW) + Order::countBy(Order::REJECTED);
  $tmin = max(1000.0, (float)($C['topup_min'] ?? 10000));
?>
  <div class="card"><h2>💳 شارژِ کیف پول <span class="sub">— <?= h(fmtNum($total)) ?> ردیف</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="orders">
      <input type="hidden" name="st" value="<?= h($st) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="جست‌وجو با آیدیِ کاربر یا شماره‌ی سفارش…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=orders&amp;st=<?= h($st) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach (['approved' => 'موفق', 'pending' => 'پرداخت‌نشده', 'all' => 'همه'] as $k => $lbl): ?>
        <a class="btn <?= $st === $k ? '' : 'ghost' ?> sm"
           href="?tab=orders&amp;st=<?= h($k) ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">
          <?= h($lbl) ?> <span class="muted"><?= h(fmtNum($counts[$k])) ?></span></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">🗂</span>شارژی با این فیلتر نیست.</div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>زمان</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $oid => $o): ?>
      <tr>
        <td><?= uLink($o) ?><div class="hint"><code><?= h((string)$oid) ?></code></div></td>
        <td class="num"><b><?= h(fmtNum($o['amount'] ?? 0)) ?></b> <span class="muted"><?= h($o['currency'] ?? '') ?></span></td>
        <td><?= h(topupMethod($o)) ?></td>
        <td><?= oBadge($o['status'] ?? '') ?></td>
        <td class="muted num"><?= h($o['created_at'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($opg, $pages); ?>
  <?php endif; ?>
  </div>

  <div class="card"><h2>⚖️ کمترین مبلغِ شارژ</h2><div class="body">
    <form method="post" class="row fe">
      <?= fk('save_topup_min', ['st' => $st]) ?>
      <div style="flex:1;min-width:200px"><label>کمترین مبلغی که کاربر می‌تواند شارژ کند (تومان)</label>
        <input name="topup_min" value="<?= h(fmtNum($tmin)) ?>" inputmode="numeric" style="direction:ltr"></div>
      <button class="btn g">ذخیره</button>
    </form>
    <div class="hint">در ربات و مینی‌اپ، مبلغِ کمتر از این پذیرفته نمی‌شود.</div>
  </div></div>


<?php elseif ($tab === 'users'):
  $uid = trim((string)($_GET['id'] ?? ''));
?>
  <?php if ($uid !== '' && ($uD = pUser($uid))):
    [$uTopups, $uTopupsN, $uTopup] = pOrdersOfUser($uid, 20);
    $uSpent = pNumSpent($uid);
    $uNums  = MaOrder::forUser((int)$uid, 30);
  ?>
  <div class="crumb">کاربران <span>/</span> <b><?= h(pLabel($uid, $uD)) ?></b></div>
  <a href="?tab=users" class="btn ghost sm" style="margin-bottom:14px">بازگشت به فهرست</a>

  <div class="card"><h2>👤 اطلاعاتِ کاربر</h2><div class="body">
    <div class="grid2">
      <div><label>نام</label><div><?= h(!empty($uD['username']) ? atU($uD['username']) : ($uD['first_name'] ?? '—')) ?></div></div>
      <div><label>آیدیِ تلگرام</label><div><code><?= h($uid) ?></code></div></div>
      <div><label>وضعیت</label><div><?= !empty($uD['banned'])
        ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></div></div>
      <div><label>تاریخِ عضویت</label><div class="muted"><?= h($uD['joined_at'] ?? '—') ?></div></div>
      <div><label>زیرمجموعه‌ها</label><div><?= h(fmtNum(pRefCount($uid))) ?> نفر</div></div>
      <div><label>معرف</label><div>
        <?php $rf = (int)($uD['referrer'] ?? 0); ?>
        <?= $rf > 0 ? '<a href="?tab=users&amp;id=' . $rf . '">' . h(pLabel($rf)) . '</a>' : '<span class="muted">—</span>' ?>
      </div></div>
    </div>
    <hr>
    <form method="post" data-confirm="<?= !empty($uD['banned']) ? 'این کاربر آزاد شود؟' : 'این کاربر مسدود شود؟ دیگر نمی‌تواند از ربات استفاده کند.' ?>">
      <?= fk('ban_user', []) ?><input type="hidden" name="user_id" value="<?= h($uid) ?>"><input type="hidden" name="ban" value="<?= !empty($uD['banned']) ? '0' : '1' ?>">
      <button class="btn <?= !empty($uD['banned']) ? 'g' : 'r' ?> sm"><?= !empty($uD['banned']) ? 'آزاد کردن' : 'مسدود کردن' ?></button>
    </form>
  </div></div>

  <div class="card"><h2>💰 کیف پول</h2><div class="body">
    <div class="stats" style="margin-bottom:15px">
      <div class="stat acc"><div class="n amount"><?= h(fmtNum($uD['balance'] ?? 0)) ?></div><div class="l">موجودیِ فعلی</div></div>
      <div class="stat ok"><div class="n amount"><?= h(fmtNum($uTopup)) ?></div><div class="l">مجموعِ شارژ</div></div>
      <div class="stat"><div class="n amount"><?= h(fmtNum($uSpent)) ?></div><div class="l">مجموعِ خریدِ شماره</div></div>
    </div>
    <form method="post" class="row fe" data-confirm="موجودیِ این کاربر عوض شود؟">
      <?= fk('set_balance', []) ?><input type="hidden" name="user_id" value="<?= h($uid) ?>">
      <div style="flex:1;min-width:170px"><label>موجودیِ تازه (تومان)</label>
        <input name="balance" value="<?= h(fmtNum($uD['balance'] ?? 0)) ?>" inputmode="numeric" style="direction:ltr"></div>
      <button class="btn">ذخیره</button>
    </form>
    <?php $wTx = walletHistory((int)$uid, 20); $wKind = ['topup' => 'شارژ', 'purchase' => 'خرید', 'refund' => 'برگشتِ پول', 'sv_refund' => 'برگشتِ پولِ خدمات',
      'rollback' => 'لغوِ خرید', 'admin_set' => 'تغییرِ دستیِ مدیر', 'ref_withdraw' => 'برداشتِ پورسانت', 'airdrop' => 'ایردراپ', 'diamond_swap' => 'تبدیلِ الماس',
      'credit' => 'واریز', 'debit' => 'برداشت']; ?>
    <?php if ($wTx): ?>
    <hr><h3>📒 دفترِ کیف پول <span class="muted" style="font-weight:400;font-size:12px">— ۲۰ تراکنشِ آخر</span></h3>
    <div class="tw"><table>
      <thead><tr><th>نوع</th><th>مبلغ</th><th>موجودی بعد</th><th>مرجع</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($wTx as $w): ?>
        <tr>
          <td><?= h($wKind[$w['kind']] ?? $w['kind']) ?></td>
          <td class="num" style="color:<?= $w['delta'] >= 0 ? '#34d8a5' : '#ff8597' ?>"><?= $w['delta'] >= 0 ? '+' : '−' ?><?= h(fmtNum(abs($w['delta']))) ?></td>
          <td class="num"><?= h(fmtNum($w['bal'])) ?></td>
          <td class="muted"><code><?= h($w['ref'] !== '' ? $w['ref'] : '—') ?></code></td>
          <td class="muted num"><?= h(date('Y-m-d H:i', $w['at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div></div>

  <div class="card"><h2>☎️ شماره‌های این کاربر <span class="sub">— ۳۰ تای آخر</span></h2>
    <?php if (!$uNums): ?>
      <div class="empty"><span class="ic">☎️</span>هنوز شماره‌ای نخریده.</div>
    <?php else: ?>
    <div class="tw"><table>
      <thead><tr><th>شماره</th><th>تلفن و کد</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($uNums as $o): $act = numGet((string)$o['id']); ?>
        <tr>
          <td><?= h(trim(($o['item_emoji'] ?? '') . ' ' . ($o['item_name'] ?? ''))) ?></td>
          <td class="num"><?php if (!empty($act['phone'])): ?><code><?= h((string)$act['phone']) ?></code><?php if ((string)($act['code'] ?? '') !== ''): ?> <span class="kc">🔑 <b><?= h((string)$act['code']) ?></b></span><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
          <td class="num"><?= h(fmtNum($o['total'] ?? 0)) ?></td>
          <td><?= nBadge($o) ?></td>
          <td class="muted num"><?= h((string)($o['created_at'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <div class="card"><h2>💳 شارژهای این کاربر <span class="sub">— <?= h(fmtNum($uTopupsN)) ?> ردیف<?= $uTopupsN > 20 ? '، ۲۰ تای آخر' : '' ?></span></h2>
    <?php if (!$uTopups): ?>
      <div class="empty"><span class="ic">🗂</span>هنوز شارژی ندارد.</div>
    <?php else: ?>
    <div class="tw"><table>
      <thead><tr><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($uTopups as $o): ?>
        <tr>
          <td class="num"><?= h(fmtNum($o['amount'] ?? 0)) ?> <span class="muted"><?= h($o['currency'] ?? '') ?></span></td>
          <td><?= h(topupMethod($o)) ?></td>
          <td><?= oBadge($o['status'] ?? '') ?></td>
          <td class="muted num"><?= h($o['created_at'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

  <?php else:
    $onlyBanned = !empty($_GET['banned']);
    [$rows, $total] = pUsersPage($pg, $PER, $qs, $onlyBanned);
    $pages = max(1, (int)ceil($total / $PER));
  ?>
  <?php if ($uid !== ''): ?><div class="flash err">کاربری با آیدیِ <code><?= h($uid) ?></code> پیدا نشد.</div><?php endif; ?>
  <div class="card"><h2>👥 کاربران <span class="sub">— <?= h(fmtNum(pSwr('user_n', 120, fn() => pUserCount()))) ?> نفر</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="users">
      <?php if ($onlyBanned): ?><input type="hidden" name="banned" value="1"><?php endif; ?>
      <input name="q" value="<?= h($qs) ?>" placeholder="نامِ کاربری، نام، یا آیدیِ عددی…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=users<?= $onlyBanned ? '&amp;banned=1' : '' ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <a class="btn <?= $onlyBanned ? 'ghost' : '' ?> sm" href="?tab=users<?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">همه</a>
      <a class="btn <?= $onlyBanned ? '' : 'ghost' ?> sm" href="?tab=users&amp;banned=1<?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>">فقط مسدودها</a>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">🔍</span><?= $qs !== '' ? 'کسی با این مشخصات پیدا نشد.' : 'هنوز کاربری نیست.' ?></div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>آیدی</th><th>موجودی</th><th>وضعیت</th><th>عضویت</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $rid => $u): ?>
      <tr>
        <td><a href="?tab=users&amp;id=<?= h((string)$rid) ?>"><?= h(pLabel($rid, $u)) ?></a></td>
        <td><code><?= h((string)$rid) ?></code></td>
        <td class="num"><?= h(fmtNum($u['balance'] ?? 0)) ?></td>
        <td><?= !empty($u['banned']) ? '<span class="badge red">مسدود</span>' : '<span class="badge green">فعال</span>' ?></td>
        <td class="muted num"><?= h($u['joined_at'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($pg, $pages); ?>
  <?php endif; ?>
  </div>
  <?php endif; ?>


<?php elseif ($tab === 'referral'):
  $topRefs = pTopReferrers(50);
  $withRef = pUserCount("CAST(json_extract(data,'$.referrer') AS INTEGER) > 0");
?>
  <div class="stats">
    <div class="stat"><div class="n"><?= h(fmtNum(count($topRefs))) ?></div><div class="l">👤 معرفِ فعال</div></div>
    <div class="stat"><div class="n"><?= h(fmtNum($withRef)) ?></div><div class="l">👥 کاربرِ معرفی‌شده</div></div>
    <div class="stat acc"><div class="n"><?= h(fmtNum($C['referral']['percent'])) ?>٪</div><div class="l">📈 پورسانت از هر خرید</div></div>
    <div class="stat<?= !empty($C['referral']['on']) ? ' ok' : '' ?>"><div class="n sm"><?= !empty($C['referral']['on']) ? 'روشن' : 'خاموش' ?></div><div class="l">🎁 سیستمِ معرفی</div></div>
  </div>

  <div class="card"><h2>🎁 تنظیمِ رفرال</h2><div class="body">
    <div class="note">از هر شماره‌ای که زیرمجموعه می‌خرد، این درصد از مبلغ به کیف پولِ معرف اضافه می‌شود.</div>
    <form method="post">
      <?= fk('save_referral') ?>
      <div class="grid2">
        <div><label>درصدِ پورسانت از هر خرید</label>
          <input name="ref_percent" type="number" min="0" max="100" step="0.5" value="<?= h((string)$C['referral']['percent']) ?>"></div>
        <label class="chk"><input type="checkbox" name="ref_on" value="1" <?= !empty($C['referral']['on']) ? 'checked' : '' ?>> سیستمِ معرفی روشن باشد</label>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره</button></div>
    </form>
  </div></div>

  <div class="card"><h2>🏆 برترین معرف‌ها</h2>
    <?php if (!$topRefs): ?><div class="empty"><span class="ic">👥</span>هنوز کسی زیرمجموعه نگرفته.</div>
    <?php else: ?><div class="tw"><table>
      <thead><tr><th>#</th><th>معرف</th><th>آیدی</th><th>زیرمجموعه</th><th>موجودی</th></tr></thead>
      <tbody>
      <?php foreach ($topRefs as $i => $r): $ru = pUser($r['id']); ?>
      <tr><td><?= $i + 1 ?></td>
        <td><a href="?tab=users&amp;id=<?= (int)$r['id'] ?>"><?= h(pLabel($r['id'], $ru)) ?></a></td>
        <td><code><?= (int)$r['id'] ?></code></td>
        <td class="num"><b><?= h(fmtNum($r['n'])) ?></b></td>
        <td class="num"><?= h(fmtNum($ru['balance'] ?? 0)) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div><?php endif; ?>
  </div>


<?php elseif ($tab === 'support'):
  $SG = maCfg()['sup'] ?? [];
  $sgChat = trim((string)($SG['chat'] ?? ''));
  $sgOn = !empty($SG['on']) && $sgChat !== ''; ?>
  <div class="card"><h2>💬 تیکت‌ها و چتِ مینی‌اپ <?= $sgOn ? '<span class="badge green">گروه</span>' : '<span class="badge">پیویِ مدیر</span>' ?></h2><div class="body">
    <div class="note">
      پشتیبانیِ مینی‌اپ یک تیکت می‌سازد. اگر گروه بدهید، تیکت‌ها داخلِ همان گروه/تاپیک می‌افتند
      و هر ادمینِ گروه با <b>ریپلای روی تیکت</b> یا دکمه‌ی «💬 پاسخ به کاربر» جواب می‌دهد؛ متن، عکس، ویس یا فایل
      مستقیم به کاربر می‌رسد و داخلِ چتِ مینی‌اپ هم دیده می‌شود. ربات باید در گروه <b>ادمین</b> باشد.
    </div>
    <form method="post" style="margin-top:12px">
      <?= fk('save_sup_group') ?>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="sg_on" value="1" <?= !empty($SG['on']) ? 'checked' : '' ?>> تیکت‌ها به گروه بروند</label>
      <div class="fld"><label>لینکِ تاپیک (ساده‌ترین راه)</label>
        <input name="sg_link" placeholder="https://t.me/c/1234567890/11" style="direction:ltr">
        <div class="hint">روی یکی از پیام‌های همان تاپیک نگه دارید ← Copy Link. گروه و تاپیک با هم پر می‌شوند.</div></div>
      <div class="grid2">
        <div><label>آیدیِ گروه</label><input name="sg_chat" value="<?= h($sgChat) ?>" placeholder="-1001234567890" style="direction:ltr"></div>
        <div><label>شماره‌ی تاپیک (۰ = بدونِ تاپیک)</label><input name="sg_thread" type="number" min="0" value="<?= (int)($SG['thread'] ?? 0) ?>"></div>
      </div>
      <div class="row" style="margin-top:14px">
        <button class="btn g">ذخیره</button>
        <button type="submit" class="btn ghost" form="supGroupTest">🧪 ارسالِ آزمایشی</button>
      </div>
    </form>
    <form method="post" id="supGroupTest" hidden><?= fk('sup_group_test') ?></form>
  </div></div>

<?php elseif ($tab === 'apis'):
  $MA  = maCfg();
  $NUM = numCfg();
  $pi  = numProvInfo();
  $SV  = svCfg();
  $set = fn($v) => trim((string)$v) !== '' ? '••••••••  (ست شده)' : 'هنوز ست نشده';
  $prov = (string)$NUM['provider'];
?>
  <div class="crumb">سیستم <span>/</span> <b>API و اتصال‌ها</b></div>
  <div class="card"><h2>🧭 این صفحه چه کار می‌کند؟</h2><div class="body">
    <div class="steps">
      <a class="step<?= maBaseUrl() !== '' ? ' ok' : '' ?>" href="#api-base"><b><?= maBaseUrl() !== '' ? '✓' : '۱' ?></b><span>آدرسِ ربات<small>هر سه مینی‌اپ از همین آدرس باز می‌شوند</small></span></a>
      <a class="step<?= numReady() ? ' ok' : '' ?>" href="#api-num"><b><?= numReady() ? '✓' : '۲' ?></b><span>فروشنده‌ی شماره مجازی<small>۵سیم یا نامبرلند — مینی‌اپِ شماره</small></span></a>
      <a class="step<?= svReady() ? ' ok' : '' ?>" href="#api-svc"><b><?= svReady() ? '✓' : '۳' ?></b><span>پنل‌های خدمات (SMM)<small>JAP و SMMCheap — کفِ قیمت بینِ دو پنل، دو مینی‌اپِ خدمات</small></span></a>
      <a class="step<?= tlChain() ? ' ok' : '' ?>" href="#api-tl"><b><?= tlChain() ? '✓' : '۴' ?></b><span>ترجمه<small>گوگل، MyMemory، LibreTranslate، DeepL، مایکروسافت</small></span></a>
    </div>
    <div class="note" style="margin-top:12px;margin-bottom:0">
      هر بخش جداست: کلیدِ فروشنده‌ی شماره فقط برای خریدِ شماره است و کلیدِ پنلِ خدمات فقط برای ممبر، بازدید، فالوور و لایک.
      <b>کلیدی که خالی بفرستید پاک نمی‌شود</b> — همان قبلی می‌ماند.
    </div>
  </div></div>

  <div class="card" id="api-base"><h2>🌐 ۱. آدرسِ عمومیِ ربات <?= trim((string)$MA['base_url']) !== '' ? '<span class="badge green">ست شده</span>' : '<span class="badge">خودکار</span>' ?></h2><div class="body">
    <div class="note">
      آدرسِ کاملِ فایلِ ربات روی دامنه‌ی خودتان (https). دکمه‌ی مینی‌اپ، دکمه‌ی منوی ربات و عکسِ پروفایلِ کاربر
      از همین ساخته می‌شوند. خالی بماند، ربات از آدرسِ وبهوک حدس می‌زند.
      <br>آدرسِ فعلی: <code><?= h(maBaseUrl() ?: '—') ?></code>
      <br>حدسِ این سرور: <code><?= h(baseUrl() . '/bot_master_membership.php') ?></code>
    </div>
    <form method="post" class="row fe" style="margin-top:12px">
      <?= fk('api_base') ?>
      <div style="flex:1;min-width:260px"><label>آدرس (https)</label>
        <input name="base_url" value="<?= h((string)$MA['base_url']) ?>"
               placeholder="https://example.com/bot_master_membership.php" style="direction:ltr"></div>
      <button class="btn g">ذخیره</button>
    </form>
  </div></div>

  <div class="card" id="api-num"><h2>☎️ ۲. فروشنده‌ی شماره مجازی <?= !empty($NUM['api']['on'])
      ? (numKey() !== '' ? '<span class="badge green">روشن</span>' : '<span class="badge amber">روشن ولی بی‌کلید</span>')
      : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      سه کار: <b>فروشنده</b> را انتخاب کنید، <b>کلیدش</b> را بگذارید، <b>سودتان</b> را بنویسید و ذخیره کنید.
      بعد «🧪 تست» بزنید؛ اگر موجودی را نشان داد، وصل است. آخر سر در <a href="?tab=numbers">کشورها و قیمت‌ها</a> «📥 وارد کردن» را بزنید.
    </div>
    <form method="post" style="margin-top:12px">
      <?= fk('api_num') ?>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="on" value="1" <?= !empty($NUM['api']['on']) ? 'checked' : '' ?>> فروشِ شماره روشن باشد</label>
      <div class="grid2">
        <div><label>فروشنده</label><select name="provider" id="numProv" onchange="numProvShow()">
          <?php foreach (numProviders() as $pv => $pinfo): ?>
            <option value="<?= h($pv) ?>" <?= $prov === $pv ? 'selected' : '' ?>><?= h($pinfo['label']) ?></option>
          <?php endforeach; ?></select></div>
        <div data-prov="5sim"><label>توکنِ ۵سیم <small class="muted">(خالی = همان قبلی)</small></label>
          <div class="secretin"><input type="password" name="token" value="" autocomplete="off" placeholder="<?= h($set($NUM['api']['token'])) ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div>
          <div class="hint">5sim.net ← Settings ← API key (کلیدی که با eyJ شروع می‌شود)</div></div>
        <div data-prov="numberland"><label>کلیدِ API نامبرلند <small class="muted">(خالی = همان قبلی)</small></label>
          <div class="secretin"><input type="password" name="nl_key" value="" autocomplete="off" placeholder="<?= h($set($NUM['api']['nl_key'])) ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div>
          <div class="hint"><?= h((string)(numProviders()['numberland']['help'] ?? '')) ?></div></div>
        <div><label>سودِ شما روی قیمتِ فروشنده (٪)</label>
          <input name="markup" value="<?= h((string)$NUM['markup']) ?>" inputmode="decimal" style="direction:ltr">
          <div class="hint">مثلا ۲۰ یعنی شماره‌ای که ۱۰۰ هزار تومان تمام می‌شود، ۱۲۰ هزار تومان فروخته شود.</div></div>
        <div data-prov="5sim"><label>هر دلار چند تومان؟ <small class="muted">(۰ = قیمتِ لحظه‌ایِ تتر)</small></label>
          <input name="rate" value="<?= h(fmtNum($NUM['api']['rate'])) ?>" inputmode="numeric" style="direction:ltr">
          <div class="hint">۵سیم به دلار می‌فروشد؛ این عدد قیمت را تومانی می‌کند.</div></div>
        <div><label>مهلتِ رسیدنِ کد (ثانیه)</label>
          <input name="wait" type="number" min="60" value="<?= (int)$NUM['wait'] ?>">
          <div class="hint">اگر تا این مدت کدی نرسد، شماره بسته و پولِ کاربر برگردانده می‌شود.</div></div>
      </div>
      <hr>
      <label>شماره‌ی کدام برنامه‌ها فروخته شود؟</label>
      <div class="row" style="gap:16px;margin:6px 0 10px">
        <?php $prOn = numProdsOn(); foreach (numProducts() as $pk => $pinf): ?>
          <label class="chk"><input type="checkbox" name="prods[]" value="<?= h($pk) ?>" <?= in_array($pk, $prOn, true) ? 'checked' : '' ?>> <?= h($pinf['e'] . ' ' . $pinf['fa']) ?></label>
        <?php endforeach; ?>
      </div>
      <div class="grid2">
        <div><label class="chk" style="margin-top:4px"><input type="checkbox" name="pick" value="1" <?= !empty($NUM['pick']) ? 'checked' : '' ?>>
          🎯 گلچین — بهترین کشورها (موجودی، نرخِ موفقیت و قیمت) اول نشان داده شوند</label>
          <label class="chk" style="margin-top:8px"><input type="checkbox" name="all" value="1" <?= !empty($NUM['all']) ? 'checked' : '' ?>>
            بقیه‌ی کشورها هم وارد شوند (بعد از محبوب‌ها و با جستجو پیدا می‌شوند)</label>
          <div class="hint">گلچین خاموش = همه‌ی کشورها و اپراتورها بدونِ ترتیبِ ویژه.</div></div>
        <div class="grid2" style="gap:8px">
          <div><label>کشورِ محبوب برای هر برنامه</label><input name="pick_n" type="number" min="1" max="60" value="<?= (int)$NUM['pick_n'] ?>"></div>
          <div><label>اپراتور در هر کشور</label><input name="pick_ops" type="number" min="1" max="5" value="<?= (int)$NUM['pick_ops'] ?>"></div>
        </div>
      </div>
      <details style="margin-top:12px"><summary class="muted" style="cursor:pointer;font-size:12.5px;font-weight:700">⚙️ تنظیماتِ پیشرفته (معمولا لازم نیست)</summary>
        <div class="grid2" style="margin-top:10px">
          <div><label>آدرسِ پایه <small class="muted">(خالی = آدرسِ رسمیِ فروشنده)</small></label>
            <input name="base" value="<?= h(numBaseNorm((string)$NUM['api']['base'], $prov)) ?>" style="direction:ltr"></div>
          <div data-prov="numberland"><label>کدِ سرویسِ تلگرام نزدِ نامبرلند</label>
            <input name="nl_svc" value="<?= h((string)$NUM['api']['nl_svc']) ?>" style="direction:ltr"></div>
          <div data-prov="numberland"><label>کدِ سرویسِ اینستاگرام / واتساپ <small class="muted">(خالی = خودکار از فهرستِ نامبرلند)</small></label>
            <div class="grid2" style="gap:8px"><input name="nl_svc_ig" value="<?= h((string)($NUM['api']['nl_svc_ig'] ?? '')) ?>" placeholder="اینستاگرام" style="direction:ltr">
              <input name="nl_svc_wa" value="<?= h((string)($NUM['api']['nl_svc_wa'] ?? '')) ?>" placeholder="واتساپ" style="direction:ltr"></div></div>
          <div data-prov="5sim"><label>سقفِ قیمتِ هر خرید، به دلار <small class="muted">(۰ = بی‌سقف)</small></label>
            <input name="max" value="<?= h((string)$NUM['api']['max']) ?>" inputmode="decimal" style="direction:ltr"></div>
          <div><label>فاصله‌ی دو پرسش از فروشنده (ثانیه)</label>
            <input name="poll" type="number" min="3" value="<?= (int)$NUM['poll'] ?>"></div>
          <div><label>مهلتِ هر تماس با فروشنده (ثانیه)</label>
            <input name="timeout" type="number" min="3" max="60" value="<?= (int)$NUM['api']['timeout'] ?>"></div>
          <label class="chk"><input type="checkbox" name="sync_price" value="1" <?= !empty($NUM['sync_price']) ? 'checked' : '' ?>>
            هر بار «وارد کردن»، قیمت‌ها از فروشنده تازه شوند</label>
        </div>
      </details>
      <div class="row" style="margin-top:14px">
        <button class="btn g">ذخیره</button>
        <button type="submit" class="btn ghost" form="numTest">🧪 تست و خواندنِ موجودی</button>
      </div>
    </form>
    <form method="post" id="numTest" hidden><?= fk('api_num_test') ?></form>
  </div></div>

  <?php $svNotes = array_values(array_filter([svCurNote('a'), svCurNote('b')])); ?>
  <div class="card" id="api-svc"><h2>🧩 ۳. پنل‌های خدمات (SMM) <?= svReady('a') && svReady('b') ? '<span class="badge green">هر دو وصل</span>'
      : (svReady() ? '<span class="badge amber">یک پنل وصل</span>' : '<span class="badge">فقط کلید مانده</span>') ?></h2><div class="body">
    <div class="note">
      ربات هم‌زمان به <b>دو پنلِ خدمات</b> وصل است: <b>پنلِ اول</b> (پیش‌فرض JustAnotherPanel) و <b>پنلِ دوم</b> (پیش‌فرض SMMCheap).
      <b>ممبرِ تلگرام</b> و <b>فالوورِ اینستاگرام</b> از پنلِ دوم برداشته می‌شود؛ <b>لایک، کامنت، سینِ استوری</b> و بقیه‌ی دسته‌ها از هر دو پنل،
      هرکدام که <b>ارزان‌تر</b> بود (کفِ قیمت). منبعِ هر دسته در <a href="?tab=svc#picks">سرویس‌ها ← گلچین</a> عوض می‌شود.
      <br>۱) کلیدِ هر پنل را بگذارید و ذخیره ۲) «🧪 تست» ۳) «📥 دریافتِ سرویس‌ها» — از هر دو پنل با هم خوانده می‌شود؛ خدماتِ اینستاگرام به مینی‌اپِ
      اینستاگرام و خدماتِ تلگرام به مینی‌اپِ تلگرام می‌روند و بقیه‌ی شبکه‌ها کنار می‌مانند.
      <br>کلیدِ JAP: <a href="https://justanotherpanel.com/account" target="_blank" rel="noopener noreferrer">justanotherpanel.com ← Account</a> ·
      کلیدِ SMMCheap: <a href="https://smmcheap.com/account" target="_blank" rel="noopener noreferrer">smmcheap.com ← Account</a> (بخشِ <b>API key</b> ← Generate).
      قیمت‌های هر دو دلاری است و با قیمتِ لحظه‌ایِ تتر به تومان تبدیل می‌شود.
    </div>
    <?php foreach (svPanels() as $pv): $pcf = svPc($pv); if ((int)$pcf['pbal_at'] > 0): ?>
      <div class="note ok" style="margin-top:10px">💰 موجودیِ <?= h(svPanelName($pv)) ?>: <b class="ltr"><?= h(rtrim(rtrim(number_format((float)$pcf['pbal'], 4, '.', ','), '0'), '.') . ' ' . $pcf['pcur']) ?></b>
        <small class="muted">(آخرین تست: <?= h(date('Y-m-d H:i', (int)$pcf['pbal_at'])) ?>)</small></div>
    <?php endif; endforeach; ?>
    <?php foreach ($svNotes as $svNote): ?><div class="note warn" style="margin-top:10px">💱 <?= h($svNote) ?></div><?php endforeach; ?>
    <?php if (svFx('a') <= 0 && svFx('b') <= 0): ?><div class="note warn" style="margin-top:10px">⚠️ <b>قیمتِ دلار معلوم نیست</b> — تا وقتی نیاید، قیمتِ فروشِ همه‌ی
      سرویس‌ها صفر است و <b>هیچ محصولی در مینی‌اپ نشان داده نمی‌شود</b>. در کادرِ «هر دلار چند تومان؟» پایین نرخ را بنویسید و ذخیره کنید
      (قیمتِ لحظه‌ای هر وقت برسد خودش جایگزین می‌شود).</div><?php endif; ?>
    <form method="post" style="margin-top:12px">
      <?= fk('sv_api') ?>
      <div class="grid2">
        <?php foreach (['a' => ['sv', SV_DEFAULT_URL, 'پنلِ اول', 'JustAnotherPanel'], 'b' => ['sv2', SV_P2_URL, 'پنلِ دوم', 'SMMCheap']] as $pv => [$pf, $pdef, $plbl, $pbrand]): $pcf = svPc($pv); ?>
        <div>
          <h3 style="font-size:13.5px;margin:0 0 9px"><?= h($plbl . ' — ' . svPanelName($pv)) ?> <?= svReady($pv) ? '<span class="badge green">وصل</span>' : '<span class="badge">کلید ندارد</span>' ?></h3>
          <label>آدرسِ API <small class="muted">(پیش‌فرض: <?= h($pbrand) ?>)</small></label>
          <input name="<?= $pf ?>_url" value="<?= h((string)$pcf['url']) ?>" placeholder="<?= h($pdef) ?>" style="direction:ltr">
          <div class="hint">خالی = <span class="ltr"><?= h($pdef) ?></span></div>
          <label style="margin-top:8px">کلیدِ API <small class="muted">(خالی = همان قبلی)</small></label>
          <div class="secretin"><input type="password" name="<?= $pf ?>_key" value="" autocomplete="off" placeholder="<?= h($set($pcf['key'])) ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div>
          <?php if (svReady($pv)): ?><label class="chk" style="margin-top:8px"><input type="checkbox" name="<?= $pf ?>_key_del" value="1"> قطع کردنِ این پنل (پاک کردنِ کلید)</label><?php endif; ?>
          <?php if (svReady($pv)): ?><button type="submit" class="btn ghost sm" style="margin-top:8px" form="svTest" name="pv" value="<?= $pv ?>">🧪 تستِ <?= h(svPanelShort($pv)) ?></button><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <hr>
      <div class="grid2">
        <div><label>قیمت‌های پنل‌ها به چه واحدی است؟</label><select name="sv_cur" id="svCur" onchange="svCurShow()">
          <?php foreach (svCurrencies() as $ck => $cl): ?>
            <option value="<?= h($ck) ?>" <?= svCurEff() === $ck ? 'selected' : '' ?>><?= h($cl) ?></option>
          <?php endforeach; ?></select>
          <div class="hint">JAP و SMMCheap دلاری‌اند — «دلار با قیمتِ لحظه‌ایِ تتر» بهترین انتخاب است.
            اگر پنلی موقعِ تست بگوید واحدش چیزِ دیگری است، قیمت‌های همان پنل خودکار درست حساب می‌شود.</div></div>
        <div data-cur="usd usd_live"><label>هر دلار چند تومان؟ <small class="muted" data-cur="usd_live">(اگر قیمتِ لحظه‌ای نیامد)</small></label>
          <input name="sv_fx" value="<?= h(fmtNum($SV['fx'])) ?>" inputmode="numeric" style="direction:ltr">
          <div class="hint" data-cur="usd_live">الان: <?= svFx() > 0 ? h(fmtNum(svFx())) . ' تومان' : 'نامعلوم' ?></div></div>
        <div><label>سودِ شما روی قیمتِ پنل (٪)</label>
          <input name="sv_markup" value="<?= h((string)$SV['markup']) ?>" inputmode="decimal" style="direction:ltr">
          <div class="hint">روی همه‌ی سرویس‌های هر دو پنل می‌نشیند، مگر سرویسی که قیمتش را دستی نوشته‌اید.</div></div>
        <div><label>مهلتِ هر تماس با پنل (ثانیه)</label>
          <input name="sv_timeout" type="number" min="5" max="60" value="<?= (int)$SV['timeout'] ?>"></div>
        <div><label class="chk" style="margin-top:24px"><input type="checkbox" name="sv_auto_on" value="1" <?= !empty($SV['auto_on']) ? 'checked' : '' ?>>
          سرویس‌های تازه‌ی تلگرام و اینستاگرام خودکار روشن شوند</label>
          <div class="hint">با «دریافتِ سرویس‌ها» مستقیم در مینی‌اپ‌ها دیده می‌شوند؛ هرکدام را نخواستید خاموش کنید.</div></div>
      </div>
      <div class="hint" style="margin-top:10px">باز/بستنِ مینی‌اپ‌ها و عنوان و شعارشان در <b>پنلِ ربات ← مینی‌اپ ← 🧩 مینی‌اپ‌های خدمات</b> است.</div>
      <div class="row" style="margin-top:14px">
        <button class="btn g">ذخیره</button>
        <button type="submit" class="btn ghost" form="svTest">🧪 تست و خواندنِ موجودیِ هر دو</button>
        <button type="submit" class="btn ghost" form="svImp">📥 دریافتِ سرویس‌ها از هر دو پنل</button>
      </div>
    </form>
    <form method="post" id="svTest" hidden><?= fk('sv_test') ?></form>
    <form method="post" id="svImp" hidden data-confirm="فهرستِ سرویس‌ها از هر دو پنل گرفته شود؟ سرویس‌هایی که قبلا تنظیم کرده‌اید دست نمی‌خورند."><?= fk('sv_import') ?></form>
  </div></div>
  <?php $TA = tlApiCfg(); $tlChain = tlChain(); ?>
  <div class="card" id="api-tl"><h2>🌐 ۴. ترجمه <?= $tlChain ? '<span class="badge green">' . count($tlChain) . ' سرویس روشن</span>' : '<span class="badge amber">خاموش</span>' ?></h2><div class="body">
    <div class="note">
      ریپلای با «ترجمه» در گروه/پیوی، متن را با این سرویس‌ها ترجمه می‌کند. اول «اولویتِ اول» امتحان می‌شود؛ اگر جواب نداد،
      بقیه‌ی سرویس‌های روشن به‌ترتیب امتحان می‌شوند و سرویسِ خراب چند دقیقه کنار می‌رود تا ربات کند نشود.
      گوگل و MyMemory کلید نمی‌خواهند؛ اگر هاست به گوگل دسترسی ندارد، یکی از سرویس‌های کلیددار را هم روشن کنید.
      <b>کلیدی که خالی بفرستید پاک نمی‌شود.</b>
      <?php if ($tlChain): ?><br>ترتیبِ فعلی: <b><?= h(implode(' ← ', array_map(fn($k) => explode(' (', tlProviders()[$k]['label'])[0] . (tlDown($k) ? ' (موقتا کنار)' : ''), $tlChain))) ?></b><?php endif; ?>
    </div>
    <form method="post" style="margin-top:12px">
      <?= fk('tl_api') ?>
      <div class="grid2">
        <div><label>اولویتِ اول</label><select name="tl_primary">
          <?php foreach (tlProviders() as $k => $pi2): ?><option value="<?= h($k) ?>" <?= ($TA['primary'] ?? 'google') === $k ? 'selected' : '' ?>><?= h(explode(' (', $pi2['label'])[0]) ?></option><?php endforeach; ?>
        </select></div>
        <div></div>
        <?php foreach (tlProviders() as $k => $pi2): $x = (array)($TA[$k] ?? []); ?>
        <div class="tlp">
          <label class="chk" style="margin-bottom:6px"><input type="checkbox" name="tl_on_<?= h($k) ?>" value="1" <?= !empty($x['on']) ? 'checked' : '' ?>> <b><?= h($pi2['label']) ?></b>
            <?= tlReady($k) ? (tlDown($k) ? ' <span class="badge amber">موقتا کنار</span>' : ' <span class="badge green">آماده</span>') : '' ?></label>
          <?php if ($k === 'libre'): ?>
            <input name="tl_libre_url" value="<?= h((string)($x['url'] ?? '')) ?>" placeholder="https://libretranslate.example.com" style="direction:ltr">
          <?php endif; ?>
          <?php if ($k === 'mymemory'): ?>
            <input name="tl_mm_email" value="<?= h((string)($x['email'] ?? '')) ?>" placeholder="ایمیل (اختیاری — سهمیه‌ی روزانه‌ی بیشتر)" style="direction:ltr">
          <?php endif; ?>
          <?php if ($pi2['key']): ?>
            <div class="secretin" style="margin-top:6px"><input type="password" name="tl_key_<?= h($k) ?>" value="" autocomplete="off" placeholder="<?= h($set($x['key'] ?? '')) ?>" style="direction:ltr">
              <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div>
            <?php if (trim((string)($x['key'] ?? '')) !== ''): ?><label class="chk" style="margin-top:6px"><input type="checkbox" name="tl_del_<?= h($k) ?>" value="1"> پاک کردنِ کلید</label><?php endif; ?>
          <?php endif; ?>
          <?php if ($k === 'azure'): ?>
            <input name="tl_azure_region" value="<?= h((string)($x['region'] ?? '')) ?>" placeholder="ناحیه، مثلا westeurope (اختیاری)" style="direction:ltr;margin-top:6px">
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="row" style="margin-top:14px">
        <button class="btn g">ذخیره</button>
        <button type="submit" class="btn ghost" form="tlTest">🧪 تستِ همه‌ی سرویس‌های روشن</button>
      </div>
    </form>
    <form method="post" id="tlTest" hidden><?= fk('tl_test') ?></form>
  </div></div>
  <script>
  function numProvShow(){ var v = document.getElementById('numProv').value;
    document.querySelectorAll('[data-prov]').forEach(function(e){ e.style.display = e.getAttribute('data-prov') === v ? '' : 'none'; }); }
  function svCurShow(){ var v = document.getElementById('svCur').value;
    document.querySelectorAll('[data-cur]').forEach(function(e){ e.style.display = (' ' + e.getAttribute('data-cur') + ' ').indexOf(' ' + v + ' ') >= 0 ? '' : 'none'; }); }
  numProvShow(); svCurShow();
  </script>


<?php elseif ($tab === 'svc'):
  $f  = (string)($_GET['f'] ?? 'tg');
  if (!in_array($f, ['tg', 'ig', 'none', 'all'], true)) $f = 'tg';
  $sf = (string)($_GET['st'] ?? 'all');
  if (!in_array($sf, ['all', 'on', 'off'], true)) $sf = 'all';
  $fc = (string)($_GET['c'] ?? '');
  if ($fc !== '' && !(isset(svApps()[$f]) && isset(svCats($f)[$fc]))) $fc = '';
  $PC = svPcats($f);
  $fpc = (string)($_GET['pc'] ?? '');
  if ($fpc !== '' && !isset($PC[$fpc])) $fpc = '';
  $fpv = in_array($_GET['pv'] ?? '', svPanels(), true) ? (string)$_GET['pv'] : '';
  $SST = svStats();
  [$rows, $total] = svAdminList($f, $qs, $sf, $pg, 40, $fc, $fpc, $fpv);
  $pages = max(1, (int)ceil($total / 40));
  $fx = svFx();
  $mk = (float)svCfg()['markup'];
  $svq = function (array $o) use ($f, $sf, $fc, $fpc, $fpv, $qs) {
      $p = array_merge(['tab' => 'svc', 'f' => $f, 'st' => $sf, 'c' => $fc, 'pc' => $fpc, 'pv' => $fpv, 'q' => $qs], $o);
      return '?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null));
  };
?>
  <div class="stats">
    <div class="stat acc"><div class="n"><?= h(fmtNum($SST['tg_on'])) ?> <small class="muted">/ <?= h(fmtNum($SST['tg'])) ?></small></div><div class="l">✈️ سرویسِ فعالِ تلگرام</div></div>
    <div class="stat acc"><div class="n"><?= h(fmtNum($SST['ig_on'])) ?> <small class="muted">/ <?= h(fmtNum($SST['ig'])) ?></small></div><div class="l">📸 سرویسِ فعالِ اینستاگرام</div></div>
    <div class="stat"><div class="n"><?= h(fmtNum($mk)) ?>٪</div><div class="l">📈 سود روی قیمتِ پنل</div></div>
    <div class="stat<?= $fx > 0 ? '' : ' warn' ?>"><div class="n sm"><?= $fx > 0 ? h(fmtNum($fx)) : '—' ?></div><div class="l">💱 هر واحدِ پنل به تومان</div></div>
  </div>
  <?php $spOn = spOn(); $spApp = isset(svApps()[$f]) ? $f : 'ig'; $spRows = spAdminRows($spApp); ?>
  <div class="card" id="picks"><h2>🎯 گلچینِ محصولات — <?= h(svApps()[$spApp]['emoji'] . ' ' . svApps()[$spApp]['short']) ?>
      <?= $spOn ? '<span class="badge green">روشن</span>' : '<span class="badge">خاموش</span>' ?></h2><div class="body">
    <div class="note">مینی‌اپ فقط همین محصولاتِ منتخب را نشان می‌دهد (کف قیمت، متوسط، ویژه با ضمانت، فیک، ایرانی، روسی و …)، نه صدها سرویسِ پنل.
      هر محصول <b>خودکار</b> مناسب‌ترین سرویسِ روشنِ پنل را برمی‌دارد؛ اگر خواستید سرویسِ دیگری انتخاب کنید، اسم یا قیمتش را عوض کنید یا خاموشش کنید.
      محصولی که سرویسِ مناسبی برایش پیدا نشود نشان داده نمی‌شود. «قیمتِ دستی» برای هر ۱۰۰۰ تاست (۰ = خودکار).</div>
    <div class="row" style="margin:6px 0 10px">
      <?php foreach (svApps() as $ak => $ai): ?><a class="btn <?= $spApp === $ak ? '' : 'ghost' ?> sm" href="?tab=svc&amp;f=<?= h($ak) ?>#picks"><?= h($ai['emoji'] . ' ' . $ai['short']) ?></a><?php endforeach; ?>
    </div>
    <form method="post">
      <?= fk('sp_save', ['f' => $spApp]) ?><input type="hidden" name="f" value="<?= h($spApp) ?>">
      <label class="chk" style="margin-bottom:10px"><input type="checkbox" name="pick" value="1" <?= $spOn ? 'checked' : '' ?>> گلچین روشن باشد (پیشنهادی)</label>
      <h3 style="font-size:13.5px;margin:6px 0 6px">🔀 هر دسته از کدام پنل؟</h3>
      <div class="hint" style="margin-bottom:8px">«هر دو پنل — کف قیمت» یعنی هر محصول از پنلی برداشته می‌شود که همان محصول را ارزان‌تر می‌دهد.
        اگر پنلِ انتخابی در آن دسته هیچ سرویسی نداشت (یا کلیدش ثبت نشده بود)، از پنلِ دیگر برداشته می‌شود تا دسته خالی نماند.</div>
      <div class="grid2" style="margin-bottom:12px">
        <?php foreach (spCats($spApp) as $dc => [$dcn]): $srcCur = svSrc($spApp, $dc); ?>
        <div><label><?= h($dcn) ?></label><select name="src[<?= h($dc) ?>]">
          <?php foreach (svSrcModes() as $mk => $ml): ?><option value="<?= h($mk) ?>" <?= $srcCur === $mk ? 'selected' : '' ?>><?= h($mk === 'a' ? 'فقط ' . svPanelName('a') : ($mk === 'b' ? 'فقط ' . svPanelName('b') : $ml)) ?></option><?php endforeach; ?>
        </select></div>
        <?php endforeach; ?>
      </div>
      <div class="tw"><table class="its" style="min-width:900px">
        <thead><tr><th>نمایش</th><th>محصول</th><th>سرویسِ پنل (خودکار یا انتخابی)</th><th>قیمتِ فروش</th><th>نامِ دلخواه</th><th>قیمتِ دستی</th></tr></thead><tbody>
        <?php $dcs = spCats($spApp); $lastDc = ''; foreach ($spRows as $rw): $d = $rw['def']; $pk = $rw['pick']; $sv = $pk['svc'] ?? null; $sid = h($rw['id']);
              if ($d['dc'] !== $lastDc): $lastDc = $d['dc']; ?>
          <tr><td colspan="6" style="background:var(--surface-2);font-weight:800"><?= h($dcs[$d['dc']][0] ?? $d['dc']) ?></td></tr>
        <?php endif; ?>
          <tr>
            <td><input type="checkbox" name="slot[<?= $sid ?>][on]" value="1" <?= empty($rw['cfg']['off']) ? 'checked' : '' ?>></td>
            <td style="min-width:170px"><b><?= h($d['title']) ?></b><div class="hint"><?= h($d['badge']) ?></div></td>
            <td style="min-width:330px">
              <?php if ($d['tier'] === 'emoji'): $em = spEmojiMap(); ?>
                <?= $em ? h(implode(' ', array_map(fn($x) => $x['_e'], $em))) . '<div class="hint">' . h(fmtNum(count($em))) . ' ایموجی از سرویس‌های ری‌اکشنی که فقط یک ایموجی دارند؛ ارزان‌ترینِ هر ایموجی.</div>' : '<span class="muted">سرویسِ ری‌اکشنِ تک‌ایموجی پیدا نشد</span>' ?>
              <?php else: ?>
                <select name="slot[<?= $sid ?>][sid]" style="width:100%">
                  <option value="">🤖 خودکار<?= $sv && empty($rw['cfg']['sid']) ? ' — ' . h(svPanelShort($sv['pv'])) . ' #' . h(svPsid($sv)) . ' ' . h(mb_substr($sv['pname'], 0, 60)) : ($sv ? '' : ' — (پیدا نشد)') ?></option>
                  <?php foreach ($rw['cand'] as $c): ?>
                    <option value="<?= h($c['id']) ?>" <?= (string)($rw['cfg']['sid'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>><?= h(svPanelShort($c['pv'])) ?> #<?= h(svPsid($c)) ?> · <?= h(fmtNum($c['_p'])) ?> ت · <?= h(mb_substr($c['pname'], 0, 70)) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php if ($sv): ?><div class="hint"><span class="badge"><?= h(svPanelShort($sv['pv'])) ?></span> #<?= h(svPsid($sv)) ?> · <?= h(mb_substr($sv['pname'], 0, 80)) ?> · <span class="ltr"><?= h(rtrim(rtrim(number_format($sv['rate'], 4, '.', ','), '0'), '.')) ?></span></div><?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="num"><?= $sv ? '<b>' . h(fmtNum((float)($rw['cfg']['price'] ?? 0) > 0 ? svRound((float)$rw['cfg']['price']) : $sv['_p'])) . '</b>' : ($d['tier'] === 'emoji' ? '—' : '<span class="muted">نمایش داده نمی‌شود</span>') ?></td>
            <td><input name="slot[<?= $sid ?>][title]" value="<?= h((string)($rw['cfg']['title'] ?? '')) ?>" placeholder="<?= h($d['title']) ?>" maxlength="60"></td>
            <td><input name="slot[<?= $sid ?>][price]" value="<?= (float)($rw['cfg']['price'] ?? 0) > 0 ? h(fmtNum($rw['cfg']['price'])) : '0' ?>" inputmode="numeric" style="direction:ltr;max-width:110px"></td>
          </tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <div style="margin-top:12px"><button class="btn g">ذخیره‌ی گلچین</button></div>
    </form>
  </div></div>
  <div class="card"><h2>🔎 وضعیتِ نمایش در مینی‌اپ‌ها</h2><div class="body"><div class="grid2">
    <?php foreach (svApps() as $dA => $dI): $dg = svDiag($dA); ?>
      <div class="note <?= $dg['shown'] > 0 ? 'ok' : 'warn' ?>" style="margin:0">
        <b><?= h($dI['emoji'] . ' ' . $dI['name']) ?>:</b> <?= $dg['shown'] > 0 ? '✅ <b>' . h(fmtNum($dg['shown'])) . '</b> محصول در مینی‌اپ دیده می‌شود' : '❌ هیچ محصولی در مینی‌اپ نیست' ?>
        <div class="hint">دریافت‌شده: <?= h(fmtNum($dg['all'])) ?> · روشن: <?= h(fmtNum($dg['on'])) ?> · نمایش: <?= h(fmtNum($dg['shown'])) ?></div>
        <?php foreach ($dg['why'] as $w): ?><div>• <?= h($w) ?></div><?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div></div></div>
  <?php if (!svReady()): ?>
    <div class="note warn">پنلِ خدمات هنوز وصل نیست — اول در <a href="?tab=apis#api-svc">API و اتصال‌ها ← پنلِ خدمات</a> آدرس و کلید را بگذارید.</div>
  <?php endif; ?>
  <div class="card"><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="svc"><input type="hidden" name="f" value="<?= h($f) ?>"><input type="hidden" name="st" value="<?= h($sf) ?>">
      <?php if ($fc !== ''): ?><input type="hidden" name="c" value="<?= h($fc) ?>"><?php endif; ?>
      <?php if ($fpc !== ''): ?><input type="hidden" name="pc" value="<?= h($fpc) ?>"><?php endif; ?>
      <?php if ($fpv !== ''): ?><input type="hidden" name="pv" value="<?= h($fpv) ?>"><?php endif; ?>
      <input name="q" value="<?= h($qs) ?>" placeholder="نام، دسته یا شماره‌ی سرویس…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="<?= h($svq(['q' => ''])) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach (['tg' => '✈️ تلگرام', 'ig' => '📸 اینستاگرام', 'none' => '❔ بقیه‌ی شبکه‌ها', 'all' => 'همه'] as $k => $lbl): ?>
        <a class="btn <?= $f === $k ? '' : 'ghost' ?> sm" href="<?= h($svq(['f' => $k, 'c' => '', 'pc' => ''])) ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
      <span class="muted" style="margin:0 6px">|</span>
      <?php foreach (['all' => 'همه', 'on' => 'روشن', 'off' => 'خاموش'] as $k => $lbl): ?>
        <a class="btn <?= $sf === $k ? '' : 'ghost' ?> sm" href="<?= h($svq(['st' => $k])) ?>"><?= $lbl ?></a>
      <?php endforeach; ?>
      <?php if ($SST['pb'] > 0 || $fpv !== ''): ?>
      <span class="muted" style="margin:0 6px">|</span>
      <?php foreach (['' => 'هر دو پنل', 'a' => svPanelShort('a') . ' (' . fmtNum($SST['pa']) . ')', 'b' => svPanelShort('b') . ' (' . fmtNum($SST['pb']) . ')'] as $k => $lbl): ?>
        <a class="btn <?= $fpv === $k ? '' : 'ghost' ?> sm" href="<?= h($svq(['pv' => $k])) ?>"><?= h($lbl) ?></a>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php if (isset(svApps()[$f])): ?>
    <div class="row" style="margin-top:8px">
      <a class="btn <?= $fc === '' ? '' : 'ghost' ?> sm" href="<?= h($svq(['c' => ''])) ?>">همه‌ی دسته‌ها</a>
      <?php foreach (svCats($f) as $ck => [$cn]): ?>
        <a class="btn <?= $fc === $ck ? '' : 'ghost' ?> sm" href="<?= h($svq(['c' => $ck])) ?>"><?= h($cn) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (count($PC) > 1 || $fpc !== ''): ?>
    <form method="get" class="row" style="margin-top:8px">
      <input type="hidden" name="tab" value="svc"><input type="hidden" name="f" value="<?= h($f) ?>"><input type="hidden" name="st" value="<?= h($sf) ?>">
      <?php if ($fc !== ''): ?><input type="hidden" name="c" value="<?= h($fc) ?>"><?php endif; ?>
      <?php if ($qs !== ''): ?><input type="hidden" name="q" value="<?= h($qs) ?>"><?php endif; ?>
      <?php if ($fpv !== ''): ?><input type="hidden" name="pv" value="<?= h($fpv) ?>"><?php endif; ?>
      <label style="margin:0">دسته‌ی خودِ پنل:</label>
      <select name="pc" onchange="this.form.submit()" style="max-width:420px;direction:ltr">
        <option value="">— همه (<?= h(fmtNum(count($PC))) ?> دسته) —</option>
        <?php foreach ($PC as $pcn => [$pcN, $pcA]): ?>
          <option value="<?= h($pcn) ?>" <?= $fpc === (string)$pcn ? 'selected' : '' ?>><?= h(((string)$pcn !== '' ? mb_substr((string)$pcn, 0, 70) : '(بدونِ دسته)') . ' — ' . $pcN . ($pcA ? ' · ' . $pcA . ' on' : '')) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
    <div class="hint" style="margin-top:10px">
      «قیمتِ فروش» خودکار = قیمتِ پنل × نرخ × (۱ + سود). اگر در ستونِ «قیمتِ دستی» عددی بنویسید، همان عدد (برای هر ۱۰۰۰ تا) فروخته می‌شود؛ ۰ یعنی خودکار.
      فقط نوعِ «Default» (لینک + تعداد) و «Poll» (رای نظرسنجی — شماره‌ی گزینه از مشتری گرفته می‌شود) فروختنی است؛ Package، کامنت/منشنِ دلخواه و اشتراکِ خودکار روشن نمی‌شوند.
      بخش و دسته‌ای که دستی عوض کنید، با «دریافتِ دوباره‌ی سرویس‌ها» دست نمی‌خورد.
      <br>🔤 نامِ سرویس‌ها خودکار از انگلیسیِ پنل به فارسی ترجمه می‌شود (نامِ اصلی زیرِ هر ردیف هست). اگر نامی را دستی بنویسید همان می‌ماند؛ خالی‌اش کنید و ذخیره بزنید تا دوباره خودکار شود.
    </div>
    <div class="row" style="margin-top:10px">
      <form method="post" style="display:inline"><?= fk('sv_fa', []) ?><button class="btn ghost sm">🔤 ترجمه‌ی دوباره‌ی نام‌ها به فارسی</button></form>
      <form method="post" style="display:inline" data-confirm="همه‌ی نام‌ها، حتی آن‌هایی که دستی نوشته‌اید، با ترجمه‌ی خودکار عوض شوند؟"><?= fk('sv_fa', []) ?><input type="hidden" name="all" value="1"><button class="btn ghost sm">↺ همه‌ی نام‌ها (حتی دستی‌ها)</button></form>
    </div>
  </div></div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">🧩</span><?= $SST['tg'] + $SST['ig'] + $SST['none'] === 0 ? 'هنوز سرویسی دریافت نشده — در API و اتصال‌ها «📥 دریافتِ سرویس‌ها» را بزنید.' : 'سرویسی با این فیلتر نیست.' ?></div>
  <?php else: ?>
    <form method="post" id="svBulk" class="row" style="margin-bottom:10px" data-confirm="همه‌ی سرویس‌های همین صفحه عوض شوند؟">
      <?= fk('sv_bulk', []) ?>
      <?php foreach ($rows as $s): ?><input type="hidden" name="ids[]" value="<?= h($s['id']) ?>"><?php endforeach; ?>
      <button class="btn ghost sm" name="to" value="on">✅ روشن کردنِ همه‌ی این صفحه</button>
      <button class="btn ghost sm" name="to" value="off">⛔ خاموش کردنِ همه‌ی این صفحه</button>
    </form>
    <?php if (isset(svApps()[$f]) && $total > count($rows)): ?>
    <form method="post" class="row" style="margin:-4px 0 10px" data-confirm="همه‌ی <?= h((string)$total) ?> سرویسِ این فیلتر (همه‌ی صفحه‌ها) عوض شوند؟">
      <?= fk('sv_bulk', []) ?><input type="hidden" name="scope" value="filter">
      <input type="hidden" name="f" value="<?= h($f) ?>"><input type="hidden" name="st" value="<?= h($sf) ?>">
      <input type="hidden" name="c" value="<?= h($fc) ?>"><input type="hidden" name="pc" value="<?= h($fpc) ?>"><input type="hidden" name="pv" value="<?= h($fpv) ?>"><input type="hidden" name="q" value="<?= h($qs) ?>">
      <button class="btn ghost sm" name="to" value="on">✅ روشن کردنِ همه‌ی <?= h(fmtNum($total)) ?> نتیجه</button>
      <button class="btn ghost sm" name="to" value="off">⛔ خاموش کردنِ همه‌ی <?= h(fmtNum($total)) ?> نتیجه</button>
    </form>
    <?php endif; ?>
    <form method="post">
      <?= fk('sv_save', []) ?>
      <div class="tw"><table class="its" style="min-width:860px">
        <thead><tr><th>فعال</th><th>نامِ نمایشی</th><th>بخش و دسته</th><th>قیمتِ پنل</th><th>قیمتِ فروش (۱۰۰۰ تا)</th><th>قیمتِ دستی</th><th>تعداد</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): $sid = h($s['id']); $ok = svTypeOk($s['type']); $auto = svPrice1k(['rate' => $s['rate'], 'price' => 0, 'pv' => $s['pv']]); ?>
          <tr>
            <td><input type="checkbox" name="sv[<?= $sid ?>][on]" value="1" <?= $s['active'] ? 'checked' : '' ?> <?= $ok ? '' : 'disabled' ?>></td>
            <td style="min-width:240px"><input name="sv[<?= $sid ?>][name]" value="<?= h($s['name']) ?>" maxlength="90" dir="auto">
              <div class="hint"><span class="badge"><?= h(svPanelShort($s['pv'])) ?></span> <code>#<?= h(svPsid($s)) ?></code> <?= h(mb_substr($s['pname'], 0, 70)) ?><?= $s['pcat'] !== '' ? ' · ' . h(mb_substr($s['pcat'], 0, 40)) : '' ?>
                <?= $ok ? '' : ' · <span class="badge red">نوعِ ' . h($s['type']) . ' پشتیبانی نمی‌شود</span>' ?><?= svKind($s['type']) === 'poll' ? ' · 🗳 نظرسنجی' : '' ?><?= $s['refill'] ? ' · ♻️ ریفیل' : '' ?><?= $s['cancel'] ? ' · ⛔ لغوپذیر' : '' ?><?= $s['man'] ? ' · ✋ دستی' : '' ?></div></td>
            <td style="min-width:170px"><select name="sv[<?= $sid ?>][app]" onchange="svAppCats(this)">
                <option value="" <?= $s['app'] === '' ? 'selected' : '' ?>>❔ هیچ‌کدام (نفروش)</option>
                <?php foreach (svApps() as $ak => $ai): ?><option value="<?= h($ak) ?>" <?= $s['app'] === $ak ? 'selected' : '' ?>><?= h($ai['emoji'] . ' ' . $ai['short']) ?></option><?php endforeach; ?>
              </select>
              <select name="sv[<?= $sid ?>][cat]" data-cat="<?= h($s['cat']) ?>" style="margin-top:6px">
                <?php foreach (($s['app'] !== '' ? svCats($s['app']) : []) as $ck => [$cn]): ?><option value="<?= h($ck) ?>" <?= $s['cat'] === $ck ? 'selected' : '' ?>><?= h($cn) ?></option><?php endforeach; ?>
              </select></td>
            <td class="num ltr"><?= h(rtrim(rtrim(number_format($s['rate'], 4, '.', ','), '0'), '.')) ?></td>
            <td class="num"><b><?= h(fmtNum(svPrice1k($s))) ?></b><?= $s['price'] > 0 && $auto > 0 ? '<div class="hint">خودکار: ' . h(fmtNum($auto)) . '</div>' : '' ?></td>
            <td><input name="sv[<?= $sid ?>][price]" value="<?= $s['price'] > 0 ? h(fmtNum($s['price'])) : '0' ?>" inputmode="numeric" style="direction:ltr;max-width:120px"></td>
            <td class="num muted"><?= h(fmtNum($s['min'])) ?> تا <?= h(fmtNum($s['max'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <div style="margin-top:14px"><button class="btn g">ذخیره‌ی همین صفحه</button></div>
    </form>
    <?php pager($pg, $pages); ?>
    <script>
    var SV_CATS = <?= json_encode(array_map(fn($a) => array_map(fn($x) => $x[0], svCats($a)), array_combine(array_keys(svApps()), array_keys(svApps()))), JSON_UNESCAPED_UNICODE) ?>;
    function svAppCats(sel){
      var c = sel.parentNode.querySelector('select[data-cat]'), list = SV_CATS[sel.value] || {};
      c.innerHTML = Object.keys(list).map(function(k){ return '<option value="' + k + '">' + list[k] + '</option>'; }).join('');
    }
    </script>
  <?php endif; ?>


<?php elseif ($tab === 'svorders'):
  $SO = ['all' => 'همه', 'run' => '⏳ در حال انجام', 'check' => '🔎 نیاز به بررسی', 'done' => '✅ انجام‌شده',
         'partial' => '🟡 ناقص', 'canceled' => '↩️ لغوشده', 'failed' => '❌ ثبت‌نشده'];
  $so = (string)($_GET['st'] ?? 'all');
  if (!isset($SO[$so])) $so = 'all';
  [$rows, $total] = svAdminOrders($so, $qs, $pg, $PER);
  $pages = max(1, (int)ceil($total / $PER));
  $SST = svStats();
  $BC = ['run' => 'blue', 'done' => 'green', 'partial' => 'amber', 'check' => 'amber', 'canceled' => 'red', 'failed' => 'red'];
?>
  <div class="stats">
    <div class="stat live"><div class="n"><?= h(fmtNum($SST['run'])) ?></div><div class="l">در حال انجام</div></div>
    <div class="stat<?= $SST['check'] ? ' warn' : '' ?>"><div class="n"><?= h(fmtNum($SST['check'])) ?></div><div class="l">🔎 نیاز به بررسی</div></div>
    <div class="stat ok"><div class="n"><?= h(fmtNum($SST['today'])) ?></div><div class="l">سفارشِ امروز</div></div>
    <div class="stat"><div class="n amount"><?= h(fmtNum($SST['sum'])) ?></div><div class="l">فروشِ امروز (تومان)</div></div>
  </div>
  <div class="note">⛔ <b>لغو و برگشتِ پول</b> سفارش را همان لحظه در پنلِ خدمات لغو می‌کند و چند ثانیه منتظرِ جواب می‌ماند؛ پول دقیقا به اندازه‌ای
    که پنل برمی‌گرداند به کاربر برمی‌گردد (لغوِ کامل همه، ناقص فقط مابقی)، پس ضرری نمی‌کنید. اگر پنل لغو را نپذیرد، پولی برنمی‌گردد و دلیلش نشان داده می‌شود.</div>
  <?php if ($SST['check']): ?>
    <div class="note warn">«نیاز به بررسی» یعنی پنلِ خدمات موقعِ ثبت جواب نداد و معلوم نیست سفارش آنجا ثبت شده یا نه. در پنلِ خدمات
      (با لینک و زمان) نگاه کنید: اگر ثبت شده «✅ انجام‌شده» و اگر نه «↩️ برگشتِ پول» بزنید.</div>
  <?php endif; ?>
  <div class="card"><h2>🧾 سفارش‌های خدمات <span class="sub">— <?= h(fmtNum($total)) ?> ردیف</span></h2><div class="body">
    <form method="get" class="searchbar">
      <input type="hidden" name="tab" value="svorders"><input type="hidden" name="st" value="<?= h($so) ?>">
      <input name="q" value="<?= h($qs) ?>" placeholder="آیدیِ کاربر، شماره‌ی سفارش، شماره‌ی پنل یا لینک…">
      <button class="btn">جست‌وجو</button>
      <?php if ($qs !== ''): ?><a class="btn ghost" href="?tab=svorders&amp;st=<?= h($so) ?>">پاک کردن</a><?php endif; ?>
    </form>
    <div class="row">
      <?php foreach ($SO as $k => $lbl): ?>
        <a class="btn <?= $so === $k ? '' : 'ghost' ?> sm" href="?tab=svorders&amp;st=<?= h($k) ?><?= $qs !== '' ? '&amp;q=' . urlencode($qs) : '' ?>"><?= h($lbl) ?></a>
      <?php endforeach; ?>
      <form method="post" style="margin-inline-start:auto"><?= fk('sv_sync', []) ?><button class="btn ghost sm">🔄 به‌روزرسانیِ وضعیت از پنل</button></form>
    </div>
  </div></div>

  <?php if (!$rows): ?>
    <div class="empty"><span class="ic">🧾</span><?= $qs !== '' ? 'چیزی با این جست‌وجو پیدا نشد.' : 'سفارشی با این فیلتر نیست.' ?></div>
  <?php else: ?>
  <div class="tw"><table>
    <thead><tr><th>کاربر</th><th>سرویس</th><th>لینک</th><th>تعداد</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $o): $r = svRow($o); ?>
      <tr>
        <td><?= uLink(['user_id' => $o['uid'], 'username' => $o['uname']]) ?></td>
        <td><?= h((svApps()[$o['app']]['emoji'] ?? '') . ' ' . $o['name']) ?>
          <div class="hint"><code><?= h($o['id']) ?></code><?= ' · ' . h(svPanelShort(svOpv($o))) . ($o['pid'] !== '' ? ': <code>' . h($o['pid']) . '</code>' : '') ?> · سرویسِ <code><?= h($o['sid']) ?></code><?= (int)($o['ans'] ?? 0) > 0 ? ' · 🗳 گزینه‌ی ' . (int)$o['ans'] : '' ?><?= (string)($o['rfid'] ?? '') !== '' ? ' · ♻️ ' . h(svRefillText((string)$o['rfst'])) . ' <code>' . h((string)$o['rfid']) . '</code>' : '' ?></div></td>
        <td style="max-width:220px"><a href="<?= h($o['link']) ?>" target="_blank" rel="noopener noreferrer" class="ltr" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($o['link']) ?></a></td>
        <td class="num"><?= h(fmtNum($o['qty'])) ?><?= $r['st'] === 'run' && $r['rm'] >= 0 ? '<div class="hint">مانده: ' . h(fmtNum($r['rm'])) . '</div>' : '' ?></td>
        <td class="num"><?= h(fmtNum($o['total'])) ?><?= (float)$o['refunded'] > 0 ? '<div class="hint">برگشتی: ' . h(fmtNum($o['refunded'])) . '</div>' : '' ?></td>
        <td><span class="badge <?= $BC[$o['status']] ?? 'gray' ?>"><?= h($r['sx']) ?></span>
          <?php if ($o['err'] !== '' && in_array($o['status'], ['failed', 'check'], true)): ?><div class="hint"><?= h(mb_substr($o['err'], 0, 90)) ?></div><?php endif; ?></td>
        <td class="muted num"><?= h(date('Y-m-d H:i', (int)$o['created'])) ?></td>
        <td>
          <?php $oid = h($o['id']); $cxOn = (int)($o['cxat'] ?? 0) > 0; ?>
          <?php if ($o['status'] === 'run'): ?>
            <form method="post" style="display:inline"><?= fk('svo_act', []) ?><input type="hidden" name="id" value="<?= $oid ?>"><button class="btn ghost sm" name="how" value="sync" title="به‌روزرسانیِ وضعیت">🔄</button></form>
            <?php if ((string)$o['pid'] !== ''): ?>
              <form method="post" style="display:inline" data-confirm="سفارش همین حالا در پنلِ خدمات لغو شود؟ پول به همان اندازه‌ای که پنل برمی‌گرداند به کاربر برمی‌گردد (لغوِ کامل = همه‌ی پول، ناقص = مابقی) — بدونِ ضرر."><?= fk('svo_act', []) ?><input type="hidden" name="id" value="<?= $oid ?>"><button class="btn r sm" name="how" value="cancel"><?= $cxOn ? '⏳ لغوِ دوباره' : '⛔ لغو و برگشتِ پول' ?></button></form>
              <form method="post" style="display:inline" data-confirm="پول بدونِ لغو در پنل برگردد؟ پنلِ خدمات کار را ادامه می‌دهد و هزینه‌اش از حسابِ شما کم می‌شود — یعنی ضرر. فقط وقتی بزنید که مطمئنید سفارش در پنل انجام نمی‌شود."><?= fk('svo_act', []) ?><input type="hidden" name="id" value="<?= $oid ?>"><button class="btn ghost sm" name="how" value="force" title="با ضرر">↩️ بدونِ لغو</button></form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if ($o['status'] === 'check'): ?>
            <form method="post" style="display:inline" data-confirm="این سفارش در پنلِ خدمات ثبت شده و انجام‌شده حساب شود؟"><?= fk('svo_act', []) ?><input type="hidden" name="id" value="<?= $oid ?>"><button class="btn g sm" name="how" value="done">✅ انجام‌شده</button></form>
            <form method="post" style="display:inline" data-confirm="در پنلِ خدمات نگاه کردید و این سفارش آنجا ثبت نشده؟ کلِ مبلغ به کیف پولِ کاربر برگردد؟"><?= fk('svo_act', []) ?><input type="hidden" name="id" value="<?= $oid ?>"><button class="btn r sm" name="how" value="refund">↩️ برگشتِ پول</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php pager($pg, $pages); ?>
  <?php endif; ?>


<?php elseif ($tab === 'settings'):
  $secOpen = $sec !== 'all' ? ' open' : '';
  $G = $C['gateway'] ?? [];
?>
  <div class="crumb">سیستم <span>/</span> <b>تنظیمات</b></div>

  <?php if ($sec !== 'all'): ?>
    <style>.psec{display:none}.psec[data-s~="<?= h($sec) ?>"]{display:block}</style>
    <div class="note" style="margin-bottom:12px">
      فقط بخشِ «<?= h(['speed' => 'تشخیص و سرعت', 'gw' => 'درگاه پرداخت', 'sec' => 'امنیت', 'db' => 'دیتابیس'][$sec] ?? '') ?>»
      نشان داده می‌شود. <a href="?tab=settings&amp;s=all">نمایشِ همه</a>
    </div>
  <?php else:
    $KQn = function_exists('kycQueue') ? count(kycQueue()) : 0;
    $HUB = [
      ['speed', '#', 'steth', 'good', 'تشخیص و سرعت', 'سرعتِ ربات · نشتیِ داده · دیسک', ['', 'badge gray']],
      ['gw', '#gw-crypto', 'gem', 'cyan', 'درگاهِ رمزارز', 'شارژِ خودکار با ارز دیجیتال', gwOn() ? ['آماده', 'badge green'] : ['خاموش', 'badge gray']],
      ['gw', '#gw-ir', 'bank', 'blue', 'درگاهِ ایرانی', 'زرین‌پال و پرداختِ ریالی', (function_exists('irOn') && irOn()) ? ['آماده', 'badge green'] : ['خاموش', 'badge gray']],
      ['gw', '#gw-kyc', 'id', 'violet', 'احراز هویت', 'درخواست‌های منتظرِ بررسی', $KQn ? [$KQn . ' منتظر', 'badge amber'] : ['خالی', 'badge gray']],
      ['sec', '#', 'lock', pWeakPass() ? 'crit' : 'good', 'امنیت', 'رمزِ پنل · کلیدِ کران · داده‌ها', pWeakPass() ? ['رمز ضعیف', 'badge red'] : ['ایمن', 'badge green']],
    ];
  ?>
  <style>.psec{display:none}</style>
  <div class="hub">
    <?php foreach ($HUB as [$hs, $ha, $hi, $ht, $hn, $hd, [$hb, $hc]]): ?>
      <a class="hubt" href="?tab=settings&amp;s=<?= h($hs) ?><?= $ha !== '#' ? h($ha) : '' ?>">
        <?= mi($hi, $ht, 'lg') ?>
        <span class="hx"><b><?= h($hn) ?></b><small><?= h($hd) ?></small></span>
        <?php if ($hb !== ''): ?><span class="<?= h($hc) ?>"><?= h($hb) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <a class="hubt" href="monitor.php">
      <?= mi('satellite', 'cyan', 'lg') ?>
      <span class="hx"><b>رصدِ زنده و دستیار</b><small>وضعیتِ لحظه‌ای و راه‌حلِ هر مشکل</small></span>
    </a>
  </div>
  <?php endif; ?>

  <details class="card psec" data-s="speed"<?= $secOpen ?>><summary><h2>🩺 تشخیص و سرعت</h2></summary><div class="body">
    <div class="note">هرکدام یک گزارشِ لحظه‌ای می‌سازد و نتیجه بالای همین صفحه می‌آید.</div>
    <div class="row" style="margin-top:12px">
      <form method="post"><?= fk('adm_speed_test', []) ?><button class="btn">⚡ سرعتِ ربات</button></form>
      <form method="post"><?= fk('adm_leak_test', []) ?><button class="btn">🛡 تستِ نشتیِ داده</button></form>
      <form method="post"><?= fk('adm_write_test', []) ?><button class="btn">💾 تستِ نوشتن روی دیسک</button></form>
    </div>
  </div></details>

  <details class="card psec" id="gw-crypto" data-s="gw"<?= $secOpen ?>><summary><h2>💠 درگاه پرداخت خودکار <?= gwOn() ? '<span class="badge green">آماده</span>' : '<span class="badge">خاموش</span>' ?></h2></summary><div class="body">
    <div class="note">
      کاربر «افزایش موجودی» می‌زند ← ربات از درگاه یک <b>لینکِ پرداخت + آدرسِ ولت + مهلت</b> می‌گیرد ←
      به‌محضِ واریز، درگاه به ربات خبر می‌دهد و کیف پول <b>خودکار</b> شارژ می‌شود.
      پول مستقیم به ولتِ خودتان در پنلِ درگاه می‌رود.<br><br>
      <b>راه‌اندازیِ OxaPay (فقط یک کلید لازم است):</b> وارد <a href="https://oxapay.com" target="_blank" rel="noopener">oxapay.com</a> شوید ←
      بخشِ <b>Merchant Service</b> ← <b>Generate API Key</b> (ساختِ کلیدِ مرچنت) ← کلید را کپی کنید و در «کلیدِ API» بگذارید ← ذخیره ← «🧪 تستِ اتصال».
      کلید یک رشته‌ی حروف و عدد است (بدونِ https). <b>لینکی مثلِ pay.oxapay.com/… کلید نیست</b>؛ آن لینکِ پرداختِ ثابت است و نمی‌گوید چه کسی
      چقدر واریز کرده، پس شارژِ خودکار با آن ممکن نیست. آدرسِ Callback را هم لازم نیست در OxaPay بنویسید؛ با هر فاکتور خودکار فرستاده می‌شود.<br>
      <b>NOWPayments:</b> کلیدِ API + IPN Secret، و آدرسِ زیر را در بخشِ IPN همان سایت بگذارید.
    </div>
    <?php if (gwCallbackUrl() !== ''): ?>
      <div class="note" style="margin-top:10px">📡 <b>آدرسِ Callback</b> (خودکار با هر فاکتور فرستاده می‌شود): <code style="direction:ltr;display:inline-block"><?= h(gwCallbackUrl()) ?></code></div>
    <?php endif; ?>

    <form method="post" style="margin-top:12px">
      <?= fk('save_gateway', []) ?>
      <label class="chk" style="margin-bottom:12px"><input type="checkbox" name="gw_on" value="1" <?= !empty($G['on']) ? 'checked' : '' ?>> درگاهِ خودکار روشن باشد</label>
      <div class="grid2">
        <div><label>سرویس</label><select name="gw_prov" id="gwProv" onchange="gwProvUi()">
          <?php foreach (['oxapay' => 'OxaPay', 'nowpayments' => 'NOWPayments', 'custom' => 'دلخواه'] as $k2 => $v2): ?>
            <option value="<?= h($k2) ?>" <?= ($G['provider'] ?? 'oxapay') === $k2 ? 'selected' : '' ?>><?= h($v2) ?></option>
          <?php endforeach; ?></select></div>
        <div><label>کلیدِ API (Merchant Key)
          <?= trim((string)($G['api_key'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <div class="secretin"><input type="password" name="gw_key" autocomplete="off" value=""
            placeholder="<?= trim((string)($G['api_key'] ?? '')) !== '' ? 'ثبت شده — برای تعویض، کلیدِ تازه بگذارید' : 'کلیدِ Merchant' ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div id="gwIpnRow"<?= ($G['provider'] ?? 'oxapay') === 'nowpayments' ? '' : ' style="display:none"' ?>><label>کلیدِ IPN Secret (فقط NOWPayments)
          <?= trim((string)($G['ipn_secret'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <div class="secretin"><input type="password" name="gw_ipn" autocomplete="off" value=""
            placeholder="<?= trim((string)($G['ipn_secret'] ?? '')) !== '' ? 'ثبت شده — برای تعویض، مقدارِ تازه بگذارید' : 'IPN Secret' ?>" style="direction:ltr">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div><label>آدرسِ عمومیِ فایلِ ربات (اختیاری — خالی بماند، از آدرسِ مینی‌اپ برداشته می‌شود)</label>
          <input name="gw_base" value="<?= h($G['base_url'] ?? '') ?>" placeholder="https://site.com/bot_master_membership.php" style="direction:ltr"></div>
        <div><label>ارز</label><input name="gw_coin" value="<?= h($G['coin'] ?? 'USDT') ?>" style="direction:ltr"></div>
        <div><label>شبکه</label><input name="gw_net" value="<?= h($G['network'] ?? '') ?>" placeholder="TRC20" style="direction:ltr"></div>
        <div><label>نرخ: هر ۱ واحد چند تومان؟ (۰ = قیمتِ لحظه‌ایِ تتر)</label>
          <input name="gw_rate" value="<?= h((string)(float)($G['rate'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>مهلتِ هر فاکتور (دقیقه)</label>
          <input name="gw_exp" type="number" min="5" value="<?= (int)($G['expire'] ?? 30) ?>"></div>
        <div><label>از این مبلغ به بالا با درگاه (تومان)</label>
          <input name="gw_min" value="<?= h((string)(float)($G['min'] ?? 0)) ?>" style="direction:ltr"></div>
        <div><label>نمایش به مشتری</label><select name="gw_mode">
          <option value="address" <?= ($G['mode'] ?? 'address') !== 'page' ? 'selected' : '' ?>>آدرسِ ولت + کیوآر داخلِ ربات و مینی‌اپ</option>
          <option value="page" <?= ($G['mode'] ?? 'address') === 'page' ? 'selected' : '' ?>>صفحه‌ی پرداختِ خودِ درگاه</option></select></div>
        <div><label>پذیرشِ کم‌واریزی (٪) — برای کارمزدِ صرافی</label>
          <input name="gw_under" value="<?= h((string)(float)($G['underpaid'] ?? 1)) ?>" style="direction:ltr"></div>
        <div id="gwCurlRow"<?= ($G['provider'] ?? 'oxapay') === 'custom' ? '' : ' style="display:none"' ?>><label>آدرسِ دلخواه (حالتِ custom)</label>
          <input name="gw_curl" value="<?= h($G['custom_url'] ?? '') ?>" placeholder="https://…?amount={amount}&order={order}&cb={callback}" style="direction:ltr"></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره‌ی درگاه</button></div>
    </form>
    <form method="post" style="margin-top:10px">
      <?= fk('test_gw', []) ?>
      <button class="btn ghost">🧪 تستِ اتصال (ساختِ فاکتورِ آزمایشی)</button>
      <span class="hint">بعد از ذخیره‌ی کلید بزنید؛ اگر آدرسِ ولت یا لینک آمد، درگاه وصل است.</span>
    </form>
  </div></details>


  <?php $IR = $C['irpay'] ?? []; $KQ = function_exists('kycQueue') ? kycQueue() : []; arsort($KQ); ?>
  <details class="card psec" id="gw-ir" data-s="gw"<?= $secOpen ?>><summary><h2>🏦 درگاه پرداخت ایرانی <?= (function_exists('irOn') && irOn()) ? '<span class="badge green">آماده</span>' : '<span class="badge">خاموش</span>' ?></h2></summary><div class="body">
    <div class="note">
      روال: کاربر «درگاه ایرانی» را می‌زند ← <b>شماره‌ی موبایلِ خودش</b> را با دکمه می‌فرستد ← مبلغ ←
      تا سقفِ زیر همین شماره کافی است؛ بیشتر از سقف یک‌بار <b>احراز هویت</b>: ربات عکسِ راهنما می‌فرستد و کاربر
      <b>یک عکس</b> می‌فرستد: متنِ خرید در دفتر + امضا + <b>کارتِ ملی</b> و <b>کارتِ بانکیِ پرداخت</b> کنارِ هم؛ شما این‌جا یا در ربات تایید می‌کنید (فقط یک‌بار؛ بعد از تایید دیگر خواسته نمی‌شود) ←
      تایید و <b>لینکِ درگاه</b> با دکمه‌ی شیشه‌ای ←
      بعد از پرداخت، حساب <b>خودکار</b> شارژ می‌شود. آدرسِ بازگشت خودکار ساخته می‌شود.
      <br>زرین‌پال: مرچنتِ ۳۶ کاراکتری از <a href="https://next.zarinpal.com" target="_blank" rel="noopener">پنلِ زرین‌پال</a> · زیبال: کدِ مرچنت از <a href="https://zibal.ir" target="_blank" rel="noopener">پنلِ زیبال</a>.
    </div>
    <form method="post" style="margin-top:12px">
      <?= fk('save_irpay', []) ?>
      <div class="grid2">
        <label class="chk"><input type="checkbox" name="ir_on" value="1" <?= !empty($IR['on']) ? 'checked' : '' ?>> درگاهِ ایرانی روشن باشد</label>
        <div><label>درگاه</label><select name="ir_prov">
          <option value="zarinpal" <?= ($IR['provider'] ?? 'zarinpal') !== 'zibal' ? 'selected' : '' ?>>زرین‌پال</option>
          <option value="zibal" <?= ($IR['provider'] ?? '') === 'zibal' ? 'selected' : '' ?>>زیبال</option></select></div>
        <div><label>مرچنت <?= trim((string)($IR['merchant'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <div class="secretin"><input type="password" name="ir_merchant" autocomplete="off" value="" style="direction:ltr"
            placeholder="<?= trim((string)($IR['merchant'] ?? '')) !== '' ? 'ثبت شده — برای تعویض، مقدارِ تازه بگذارید' : 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' ?>">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <label class="chk"><input type="checkbox" name="ir_sandbox" value="1" <?= !empty($IR['sandbox']) ? 'checked' : '' ?>> حالتِ آزمایشی (sandbox)</label>
        <label class="chk"><input type="checkbox" name="ir_merchant_clear" value="1"> پاک کردنِ مرچنت</label>
        <div><label>حداقلِ هر پرداخت (تومان)</label><input name="ir_min" value="<?= h((string)(float)($IR['min'] ?? 10000)) ?>" style="direction:ltr"></div>
        <div><label>حداکثرِ هر پرداخت (تومان، ۰ = بی‌سقف)</label><input name="ir_max" value="<?= h((string)(float)($IR['max'] ?? 0)) ?>" style="direction:ltr"></div>
        <label class="chk"><input type="checkbox" name="ir_kyc" value="1" <?= !empty($IR['kyc']) ? 'checked' : '' ?>> احراز هویت برای مبالغِ بالا</label>
        <div><label>تا این مبلغ بدونِ احراز هویت (تومان)</label><input name="ir_kyc_limit" value="<?= h((string)(float)($IR['kyc_limit'] ?? 500000)) ?>" style="direction:ltr"></div>
        <div><label>احراز هویت برای بالای سقف (تا سقف فقط شماره‌ی موبایل)</label><select name="ir_kyc_mode">
          <option value="docs" <?= ($IR['kyc_mode'] ?? 'docs') === 'docs' ? 'selected' : '' ?>>یک عکس: دست‌نوشته در دفتر + امضا + کارتِ ملی و کارتِ بانکی کنارِ هم (با عکسِ راهنما)</option>
          <option value="phone" <?= ($IR['kyc_mode'] ?? '') === 'phone' ? 'selected' : '' ?>>فقط شماره‌ی موبایل + تاییدِ مدیر</option>
          <option value="auto" <?= ($IR['kyc_mode'] ?? '') === 'auto' ? 'selected' : '' ?>>کدِ پیامکیِ زرین‌پال (اگر OAuth ثبت شده) — وگرنه تاییدِ مدیر</option></select></div>
        <div><label>زرین‌پال OAuth — client_id <?= trim((string)($IR['oauth_id'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <input name="ir_oauth_id" value="<?= h((string)($IR['oauth_id'] ?? '')) ?>" style="direction:ltr" placeholder="100"></div>
        <div><label>زرین‌پال OAuth — client_secret <?= trim((string)($IR['oauth_secret'] ?? '')) !== '' ? '<span class="badge green">ثبت شده</span>' : '<span class="badge">ثبت نشده</span>' ?></label>
          <div class="secretin"><input type="password" name="ir_oauth_secret" autocomplete="off" value="" style="direction:ltr"
            placeholder="<?= trim((string)($IR['oauth_secret'] ?? '')) !== '' ? 'ثبت شده — برای تعویض، مقدارِ تازه بگذارید' : 'client_secret' ?>">
            <button type="button" class="btn ghost sm" onclick="toggleSecret(this)">نمایش</button></div></div>
        <div><label>کدِ تایید با</label><select name="ir_otp_ch">
          <option value="sms" <?= ($IR['otp_ch'] ?? 'sms') !== 'ussd' ? 'selected' : '' ?>>پیامک (SMS)</option>
          <option value="ussd" <?= ($IR['otp_ch'] ?? '') === 'ussd' ? 'selected' : '' ?>>کدِ دستوری (USSD)</option></select></div>
        <label class="chk"><input type="checkbox" name="ir_oauth_clear" value="1"> پاک کردنِ OAuth</label>
        <label class="chk"><input type="checkbox" name="ir_only" value="1" <?= !empty($IR['ir_only']) ? 'checked' : '' ?>> فقط شماره‌ی موبایلِ ایرانی</label>
        <label class="chk"><input type="checkbox" name="ir_card_check" value="1" <?= !empty($IR['card_check']) ? 'checked' : '' ?>> زیبال: کارت فقط به نامِ صاحبِ شماره</label>
        <div><label>توضیحِ تراکنش</label><input name="ir_desc" value="<?= h((string)($IR['desc'] ?? 'شارژ کیف پول')) ?>"></div>
      </div>
      <div style="margin-top:14px"><button class="btn g">ذخیره‌ی درگاهِ ایرانی</button></div>
    </form>
    <div class="grid2" style="margin-top:10px;align-items:end">
      <form method="post">
        <?= fk('test_irpay', []) ?>
        <button class="btn ghost">🧪 تستِ اتصالِ درگاه (ساختِ لینکِ آزمایشی)</button>
      </form>
      <form method="post" style="display:flex;gap:8px;align-items:end">
        <?= fk('test_zpotp', []) ?>
        <div style="flex:1"><label>تستِ کدِ پیامکیِ زرین‌پال — شماره</label><input name="phone" inputmode="tel" placeholder="09xxxxxxxxx" style="direction:ltr"></div>
        <button class="btn ghost">📩 فرستادنِ کد</button>
      </form>
    </div>
  </div></details>

  <details class="card psec" id="gw-kyc" data-s="gw"<?= $secOpen ?>><summary><h2>🪪 احراز هویت <?= $KQ ? '<span class="badge amber">' . count($KQ) . ' منتظر</span>' : '<span class="badge">خالی</span>' ?></h2></summary><div class="body">
    <?php if (!$KQ): ?><div class="muted">درخواستی منتظرِ بررسی نیست.</div><?php endif; ?>
    <?php foreach (array_slice(array_keys($KQ), 0, 30) as $ku): $KU = getUser((int)$ku) ?: []; $KK = (array)($KU['kyc'] ?? []); ?>
      <div class="card" style="margin-top:10px;padding:12px">
        <div class="grid2" style="align-items:start">
          <div>
            <b><?= h((string)($KU['first_name'] ?? $KU['name'] ?? $ku)) ?></b> <?= !empty($KU['username']) ? '<span class="muted">@' . h($KU['username']) . '</span>' : '' ?><br>
            آیدی: <code><?= (int)$ku ?></code><br>
            موبایل: <code><?= h((string)($KU['phone'] ?? '—')) ?></code><br>
            <?php if (trim((string)($KK['code'] ?? '')) !== ''): ?>کدِ ملی: <code><?= h((string)$KK['code']) ?></code><br><?php endif; ?>
            روش: <?= ($KK['mode'] ?? 'phone') === 'docs' ? (!empty($KK['photo2']) ? 'کارتِ ملی + دست‌نوشته با کارتِ بانکی و امضا (دو عکس)' : (trim((string)($KK['code'] ?? '')) !== '' ? 'کدِ ملی + عکس' : 'یک عکس: دست‌نوشته + امضا + کارتِ ملی و کارتِ بانکی')) : 'شماره‌ی موبایل (تاییدِ مدیر)' ?><br>
            <?php if ((float)($KK['amt'] ?? 0) > 0): ?>مبلغِ درخواستی: <b><?= h(fmtNum((float)$KK['amt'])) ?></b> تومان<br><?php endif; ?>
            <span class="muted"><?= !empty($KK['at']) ? h(date('Y-m-d H:i', (int)$KK['at'])) : '' ?></span>
          </div>
          <div><?php $kHas1 = kycImgPath((int)$ku) !== ''; $kHas2 = kycImgPath((int)$ku, 'img2') !== ''; ?>
            <?php if ($kHas1 || $kHas2): ?><div style="display:flex;gap:8px;flex-wrap:wrap">
              <?php foreach ([[$kHas1, '', $kHas2 ? '۱) کارتِ ملی' : (trim((string)($KK['code'] ?? '')) !== '' ? 'کارتِ ملی' : 'دست‌نوشته + امضا + کارتِ ملی و کارتِ بانکی')], [$kHas2, '&amp;n=2', '۲) دست‌نوشته + کارتِ بانکی + امضا']] as [$kOn, $kQ, $kL]): if (!$kOn) continue; ?>
                <figure style="margin:0;flex:1;min-width:140px"><a href="?kycimg=<?= (int)$ku ?><?= $kQ ?>" target="_blank" rel="noopener"><img src="?kycimg=<?= (int)$ku ?><?= $kQ ?>" alt="" style="width:100%;max-height:220px;object-fit:contain;border-radius:12px;border:1px solid var(--border)"></a>
                  <figcaption class="muted" style="font-size:12px;margin-top:4px"><?= $kL ?></figcaption></figure>
              <?php endforeach; ?></div>
            <?php elseif (($KK['mode'] ?? 'phone') === 'docs'): ?><span class="muted">عکس در ربات برای مدیر فرستاده شده.</span><?php endif; ?></div>
        </div>
        <div class="row" style="margin-top:10px">
          <form method="post"><?= fk('kyc_decide', ['s' => 'gw']) ?><input type="hidden" name="uid" value="<?= (int)$ku ?>"><input type="hidden" name="dec" value="ok"><button class="btn g sm">✅ تایید</button></form>
          <form method="post" style="display:flex;gap:6px"><?= fk('kyc_decide', ['s' => 'gw']) ?><input type="hidden" name="uid" value="<?= (int)$ku ?>"><input type="hidden" name="dec" value="no">
            <input name="note" placeholder="دلیلِ رد (اختیاری)" style="min-width:160px"><button class="btn r sm">❌ رد</button></form>
        </div>
      </div>
    <?php endforeach; ?>
  </div></details>

  <?php if (function_exists('payTextLabels')): ?>
  <div class="card psec" data-s="gw"><div class="body">
    <div class="note">✏️ <b>متن‌ها، رنگ، ایموجی و ایموجیِ پریمیومِ پیام‌ها و دکمه‌های شارژ</b> فقط از داخلِ ربات عوض می‌شوند:
      <code>/panel</code> ← 💳 پرداخت و شارژِ حساب ← ✏️ متن‌ها و دکمه‌های شارژ.
      آن‌جا ایموجیِ پریمیوم را مستقیم از کیبوردِ تلگرام می‌فرستید و رنگِ هر دکمه را با یک دکمه عوض می‌کنید.</div>
  </div></div>
  <?php endif; ?>

  <details class="card psec" data-s="sec"<?= $secOpen ?>><summary><h2>🔐 امنیت</h2></summary><div class="body">
    <p class="muted" style="line-height:2.1">
      • رمزِ پنل: <code>ADMIN_PANEL_PASS</code> در <code>config.local.php</code>
        <?php if (pWeakPass()): ?><span class="badge red">ضعیف است — دست‌کم ۱۲ نویسه با حرف، عدد و نماد</span><?php endif; ?><br>
      • آیدیِ مدیرها: <code><?= h(implode('، ', ADMIN_IDS)) ?></code> — کنارِ BOT_TOKEN در <code>config.local.php</code><br>
      • کلیدِ کران:
        <span class="secret-box">
          <code data-shown="0" data-masked="••••••••••••">••••••••••••</code>
          <button type="button" class="btn ghost sm" onclick="revealBox(this, <?= h(json_encode(CRON_KEY)) ?>)">👁 نمایش</button>
          <button type="button" class="btn ghost sm" onclick="copyText(<?= h(json_encode(CRON_KEY)) ?>, this)">کپی</button>
        </span>
        — <code>CRON_KEY</code> در <code>config.local.php</code><br>
      • پوشه‌ی <code>data_master/</code> تنظیمات، کلیدها و دیتابیس‌ها را دارد؛ دسترسیِ عمومی به آن را ببندید.<br>
      • فایلِ <code>setup.php</code>:
        <?= is_file(__DIR__ . '/setup.php') ? '<span class="badge red">هنوز روی هاست است — بعد از راه‌اندازی پاکش کنید</span>' : '<span class="badge green">پاک شده</span>' ?>
    </p>
  </div></details>

  <?= dbPanelCard($CSRF, $secOpen) ?>

  <?php if ($sec === 'all'): ?>
  <details class="card" id="setmap"><summary><h2>🧭 نقشه‌ی تنظیمات — کجا چه چیزی را تنظیم کنم؟</h2></summary><div class="body">
    <div class="note">
      <b>هر تنظیم دقیقا یک خانه دارد.</b> پنلِ وب فقط برای تنظیماتِ غیرظاهری است: کلیدها، اتصال‌ها، درگاه‌ها، قیمت‌ها، کاربران و سفارش‌ها.
      هر متن، دکمه، رنگ، ایموجی و چیدمان، و عضویت اجباری فقط داخلِ خودِ ربات (<code>/panel</code>) عوض می‌شود.
    </div>
    <div class="tw" style="margin-top:12px"><table>
      <tr><th>می‌خواهم…</th><th>خانه‌اش</th></tr>

      <tr class="grp"><td colspan="2">☎️ فروشِ شماره</td></tr>
      <tr><td>فروشنده، کلید، سود و مهلتِ کد</td><td><a href="?tab=apis">API و اتصال‌ها ← ☎️ فروشنده</a></td></tr>
      <tr><td>وارد کردنِ کشورها و قیمت‌ها</td><td><a href="?tab=numbers">کشورها و قیمت‌ها ← 📥 وارد کردن</a></td></tr>
      <tr><td>قیمت، نام، برچسب و روشن/خاموشِ هر شماره</td><td><a href="?tab=numbers">کشورها و قیمت‌ها ← کارتِ همان کشور</a></td></tr>
      <tr><td>دیدنِ سفارش‌ها و لغوِ شماره‌ی باز</td><td><a href="?tab=numorders">سفارش‌های شماره</a></td></tr>
      <tr><td>آدرسِ عمومیِ مینی‌اپ</td><td><a href="?tab=apis">API و اتصال‌ها ← 🌐 آدرس</a></td></tr>

      <tr class="grp"><td colspan="2">🧩 خدمات تلگرام و اینستاگرام</td></tr>
      <tr><td>آدرس و کلیدِ دو پنلِ خدمات (JAP و SMMCheap) و سود</td><td><a href="?tab=apis#api-svc">API و اتصال‌ها ← 🧩 پنل‌های خدمات</a></td></tr>
      <tr><td>هر دسته (ممبر، فالوور، لایک، کامنت، سین استوری…) از کدام پنل یا کفِ قیمتِ هر دو</td><td><a href="?tab=svc#picks">سرویس‌ها و قیمت‌ها ← 🎯 گلچین ← 🔀 منبع</a></td></tr>
      <tr><td>روشن کردنِ سرویس‌ها، نام، دسته و قیمتِ دستی</td><td><a href="?tab=svc">سرویس‌ها و قیمت‌ها</a></td></tr>
      <tr><td>دیدنِ سفارش‌ها، برگشتِ پول و سفارش‌های نیازمندِ بررسی</td><td><a href="?tab=svorders">سفارش‌های خدمات</a></td></tr>
      <tr><td>باز/بسته، عنوان و شعارِ دو مینی‌اپِ خدمات</td><td>داخلِ ربات: /panel ← 🚀 مینی‌اپ ← 🧩 مینی‌اپ‌های خدمات</td></tr>
      <tr><td>متن و رنگِ دکمه‌های «ثبت سفارش»</td><td>داخلِ ربات: /panel ← 🎨 ظاهر و متن‌ها ← 🛍 پیام و دکمه‌ی فروشگاه</td></tr>

      <tr class="grp"><td colspan="2">💳 پول و کاربران</td></tr>
      <tr><td>تاریخچه‌ی شارژها و کمترین مبلغِ شارژ</td><td><a href="?tab=orders">شارژ کیف پول</a></td></tr>
      <tr><td>درگاهِ رمزارز، درگاهِ ایرانی و احراز هویت</td><td><a href="?tab=settings&amp;s=gw">همین صفحه ← درگاه پرداخت</a></td></tr>
      <tr><td>موجودی یا مسدود کردنِ یک کاربر</td><td><a href="?tab=users">کاربران ← همان کاربر</a></td></tr>
      <tr><td>درصدِ پورسانتِ رفرال</td><td><a href="?tab=referral">رفرال</a></td></tr>
      <tr><td>گروه و تاپیکِ تیکت‌ها</td><td><a href="?tab=support">پشتیبانی</a></td></tr>

      <tr class="grp"><td colspan="2">📱 داخلِ خودِ ربات (<code>/panel</code>)</td></tr>
      <tr><td>باز/بسته کردن، عنوان، شعار، لوگو و صفحه‌ی لودینگِ مینی‌اپ</td><td>🚀 مینی‌اپ</td></tr>
      <tr><td>کانال‌های عضویت اجباری، روشن/خاموش و متنِ قفل</td><td>🔒 عضویت اجباری</td></tr>
      <tr><td>متن‌ها و دکمه‌های شارژ، عکسِ راهنمای احراز، بررسیِ احراز هویت</td><td>💳 پرداخت</td></tr>
      <tr><td>دکمه‌های پشتیبانی و لینکِ ارتباطِ مستقیم</td><td>🎨 ظاهر و متن‌ها ← 📞 دکمه‌های پشتیبانی</td></tr>
      <tr><td>کانال‌های گزارشِ خرید و شارژ</td><td>📡 کانال‌های گزارش</td></tr>
      <tr><td>کارتِ عکسِ همراهِ گزارشِ خرید</td><td>📡 کانال‌های گزارش ← 🖼 کارتِ گزارشِ خرید</td></tr>
      <tr><td>متنِ دکمه‌ها، رنگ‌ها، ایموجی پریمیوم</td><td>🎨 ظاهر و متن‌ها</td></tr>
      <tr><td>بازی‌ها و الماس</td><td>🎮 بازی‌ها · 💎 الماس</td></tr>
      <tr><td>قیمتِ لحظه‌ای در گروه</td><td>💹 قیمت لحظه‌ای</td></tr>
      <tr><td>پیامِ همگانی</td><td>📢 پیام همگانی</td></tr>
      <tr><td>سلامتِ همه‌ی بخش‌ها</td><td>🩺 چکاپِ بخش‌ها</td></tr>
    </table></div>
  </div></details>
  <?php endif; ?>

<?php endif; ?>


  </div></main>
</div>

<script>
function gwProvUi() {
  var s = document.getElementById('gwProv'); if (!s) return;
  var a = document.getElementById('gwIpnRow'), b = document.getElementById('gwCurlRow');
  if (a) a.style.display = s.value === 'nowpayments' ? '' : 'none';
  if (b) b.style.display = s.value === 'custom' ? '' : 'none';
}
function toggleSecret(btn) {
  var inp = btn.previousElementSibling;
  if (!inp) return;
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.textContent = inp.type === 'password' ? 'نمایش' : 'مخفی';
}
function revealBox(btn, full) {
  var code = btn.previousElementSibling;
  var showing = code.getAttribute('data-shown') === '1';
  code.textContent = showing ? code.getAttribute('data-masked') : full;
  code.setAttribute('data-shown', showing ? '0' : '1');
  btn.innerHTML = <?= json_encode([mi('eye', 'blue', 'in') . 'نمایش', mi('eyeoff', 'idle', 'in') . 'مخفی'], JSON_UNESCAPED_UNICODE) ?>[showing ? 0 : 1];
}
function copyText(text, btn) {
  var done = function () { var old = btn.textContent; btn.textContent = '✓ کپی شد'; setTimeout(function () { btn.textContent = old; }, 1400); };
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
  var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); done(); } catch (e) {}
  document.body.removeChild(ta);
}

(function () {
  var M = null, pend = null, last = null;
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('button, input[type="submit"]');
    if (b && (b.type || 'submit') === 'submit') last = b;
  }, true);
  function close() { pend = null; if (M) M.classList.remove('on'); }
  function ask(msg, ok) {
    if (!M) {
      M = document.createElement('div'); M.className = 'cfm'; M.setAttribute('role', 'dialog');
      M.innerHTML = '<div class="cfm-b"><div class="cfm-i">' + <?= json_encode(mi('alert', 'warn', 'lg')) ?> + '</div><p></p><div class="cfm-r">' +
        '<button type="button" class="btn g" data-y>بله، انجام بده</button><button type="button" class="btn ghost" data-n>انصراف</button></div></div>';
      document.body.appendChild(M);
      M.addEventListener('click', function (e) {
        if (e.target.closest('[data-y]')) { var f = pend; close(); if (f) f(); }
        else if (e.target.closest('[data-n]') || e.target === M) close();
      });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
    }
    M.querySelector('p').textContent = msg; pend = ok; M.classList.add('on');
    setTimeout(function () { var y = M.querySelector('[data-y]'); if (y) y.focus(); }, 30);
  }
  document.addEventListener('submit', function (ev) {
    var f = ev.target, msg = f && f.getAttribute ? f.getAttribute('data-confirm') : null;
    if (!msg) return;
    ev.preventDefault(); ev.stopImmediatePropagation();
    var sub = ev.submitter || (last && last.form === f ? last : null);
    ask(msg, function () {
      if (sub && sub.name) {
        var h = document.createElement('input'); h.type = 'hidden'; h.name = sub.name; h.value = sub.value; f.appendChild(h);
      }
      if (sub) { sub.disabled = true; sub.textContent = 'در حال انجام…'; }
      HTMLFormElement.prototype.submit.call(f);
    });
  }, true);
})();

document.querySelectorAll('form').forEach(function (f) {
  f.addEventListener('submit', function (ev) {
    if (ev.defaultPrevented || f.method.toLowerCase() !== 'post') return;
    var btn = ev.submitter || f.querySelector('button:not([type]), button[type="submit"]');
    if (!btn || btn.disabled) return;
    setTimeout(function () { btn.disabled = true; btn.textContent = 'در حال انجام…'; }, 0);
  });
});

(function () {
  var box = document.getElementById('pq'), out = document.getElementById('pres');
  if (!box || !out) return;
  var PAGES = [];
  document.querySelectorAll('.sidebar .navlink').forEach(function (a) {
    PAGES.push({ t: (a.querySelector('span') || {}).textContent || a.textContent, h: a.getAttribute('href'), i: (a.querySelector('svg') || {}).outerHTML || '' });
  });
  document.querySelectorAll('.sidebar .subnav a').forEach(function (a) {
    var g = a.closest('.navgroup').querySelector('.navlink.on span');
    PAGES.push({ t: (g ? g.textContent + ' — ' : '') + a.textContent, h: a.getAttribute('href'), i: '' });
  });
  var hi = -1;
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function draw(list) {
    hi = -1;
    if (!box.value.trim()) { out.hidden = true; return; }
    out.innerHTML = list.length
      ? list.map(function (p, n) { return '<a href="' + esc(p.h) + '" data-n="' + n + '">' + p.i + '<span>' + esc(p.t) + '</span></a>'; }).join('')
      : '<div class="none">چیزی پیدا نشد</div>';
    out.hidden = false;
  }
  function run() {
    var q = box.value.trim().toLowerCase();
    if (!q) return draw([]);
    draw(PAGES.filter(function (p) { return p.t.toLowerCase().indexOf(q) !== -1; }).slice(0, 8));
  }
  box.addEventListener('input', run);
  box.addEventListener('focus', run);
  box.addEventListener('keydown', function (e) {
    var links = out.querySelectorAll('a');
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault(); if (!links.length) return;
      hi = (hi + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
      links.forEach(function (l, n) { l.classList.toggle('hi', n === hi); });
    } else if (e.key === 'Enter') {
      if (!links.length) return;
      e.preventDefault();
      location.href = links[hi >= 0 ? hi : 0].getAttribute('href');
    } else if (e.key === 'Escape') { box.value = ''; out.hidden = true; box.blur(); }
  });
  document.addEventListener('click', function (e) { if (!e.target.closest('.psearch')) out.hidden = true; });
  document.addEventListener('keydown', function (e) {
    if (e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) { e.preventDefault(); box.focus(); }
  });
})();

(function () {
  try { if (localStorage.getItem('nsNav') === '0') document.body.classList.add('nav-collapsed'); } catch (e) {}
  var btn = document.querySelector('header.top .iconbtn.m-only');
  if (!btn) return;
  btn.addEventListener('click', function (e) {
    if (window.matchMedia('(min-width:901px)').matches) {
      e.preventDefault();
      document.body.classList.toggle('nav-collapsed');
      try { localStorage.setItem('nsNav', document.body.classList.contains('nav-collapsed') ? '0' : '1'); } catch (e2) {}
    }
  });
})();

(function () {
  var t = location.hash && document.getElementById(location.hash.slice(1));
  if (t && t.tagName === 'DETAILS') { t.open = true; t.scrollIntoView({ block: 'start' }); }
})();
</script>

</body>
</html>
<?php pLater();

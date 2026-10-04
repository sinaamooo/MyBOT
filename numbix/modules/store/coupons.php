<?php
defined('NB_ROOT') || exit;

function cpDbPath() { return DATA_DIR . '/coupons.sqlite'; }

function cpDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = cpDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[coupons] coupons.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec("CREATE TABLE IF NOT EXISTS coupons (
        code TEXT PRIMARY KEY,
        kind TEXT NOT NULL DEFAULT 'percent',
        value REAL NOT NULL DEFAULT 0,
        max_uses INTEGER NOT NULL DEFAULT 0,
        used INTEGER NOT NULL DEFAULT 0,
        per_user_limit INTEGER NOT NULL DEFAULT 1,
        min_total REAL NOT NULL DEFAULT 0,
        max_discount REAL NOT NULL DEFAULT 0,
        expires_at INTEGER NOT NULL DEFAULT 0,
        on_flag INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL DEFAULT 0,
        owner INTEGER NOT NULL DEFAULT 0
    )");
    $db->exec('CREATE TABLE IF NOT EXISTS coupon_redemptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        code TEXT NOT NULL,
        user_id INTEGER NOT NULL,
        order_id TEXT NOT NULL,
        amount REAL NOT NULL,
        created_at INTEGER NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_cpred_code_user ON coupon_redemptions(code, user_id)');
    $db->exec('CREATE TABLE IF NOT EXISTS coupon_active (
        uid INTEGER PRIMARY KEY,
        code TEXT NOT NULL,
        at INTEGER NOT NULL DEFAULT 0
    )');
    $cols = [];
    $res = @$db->query('PRAGMA table_info(coupons)');
    while ($res && ($x = $res->fetchArray(SQLITE3_ASSOC))) $cols[strtolower((string)$x['name'])] = true;
    if ($cols && !isset($cols['owner'])) @$db->exec('ALTER TABLE coupons ADD COLUMN owner INTEGER NOT NULL DEFAULT 0');
    return $db;
}

function cpNorm($code) { return strtoupper(trim((string)$code)); }

function cpGet($code) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return null;
    $stmt = $db->prepare('SELECT * FROM coupons WHERE code = :c');
    $stmt->bindValue(':c', $code, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return $row ?: null;
}

function cpUpsert($code, array $fields) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return false;
    $cur = cpGet($code) ?: [
        'code' => $code, 'kind' => 'percent', 'value' => 0, 'max_uses' => 0, 'used' => 0,
        'per_user_limit' => 1, 'min_total' => 0, 'max_discount' => 0, 'expires_at' => 0,
        'on_flag' => 1, 'created_at' => time(), 'owner' => 0,
    ];
    $row = array_merge($cur, $fields);
    $stmt = $db->prepare('INSERT OR REPLACE INTO coupons
        (code, kind, value, max_uses, used, per_user_limit, min_total, max_discount, expires_at, on_flag, created_at, owner)
        VALUES (:code,:kind,:value,:max_uses,:used,:pul,:min_total,:max_discount,:exp,:on,:created,:owner)');
    $stmt->bindValue(':code', $code, SQLITE3_TEXT);
    $stmt->bindValue(':kind', (string)$row['kind'], SQLITE3_TEXT);
    $stmt->bindValue(':value', (float)$row['value'], SQLITE3_FLOAT);
    $stmt->bindValue(':max_uses', (int)$row['max_uses'], SQLITE3_INTEGER);
    $stmt->bindValue(':used', (int)$row['used'], SQLITE3_INTEGER);
    $stmt->bindValue(':pul', (int)$row['per_user_limit'], SQLITE3_INTEGER);
    $stmt->bindValue(':min_total', (float)$row['min_total'], SQLITE3_FLOAT);
    $stmt->bindValue(':max_discount', (float)$row['max_discount'], SQLITE3_FLOAT);
    $stmt->bindValue(':exp', (int)$row['expires_at'], SQLITE3_INTEGER);
    $stmt->bindValue(':on', (int)$row['on_flag'], SQLITE3_INTEGER);
    $stmt->bindValue(':created', (int)$row['created_at'], SQLITE3_INTEGER);
    $stmt->bindValue(':owner', (int)($row['owner'] ?? 0), SQLITE3_INTEGER);
    return (bool)$stmt->execute();
}

function cpMakePercent($prefix, $percent, $days) {
    $percent = max(1, min(100, (float)$percent));
    $prefix  = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$prefix)) ?: 'OFF';
    for ($i = 0; $i < 8; $i++) {
        $code = $prefix . strtoupper(bin2hex(random_bytes(3)));
        if (cpGet($code) !== null) continue;
        $exp = time() + max(1, (int)$days) * 86400;
        $ok = cpUpsert($code, [
            'kind' => 'percent', 'value' => $percent, 'max_uses' => 1, 'used' => 0, 'per_user_limit' => 1,
            'min_total' => 0, 'max_discount' => 0, 'expires_at' => $exp, 'on_flag' => 1, 'created_at' => time(),
        ]);
        return $ok ? ['code' => $code, 'expires_at' => $exp, 'percent' => $percent] : null;
    }
    return null;
}

function cpValidate($code, $uid, $subtotal) {
    $c = cpGet($code);
    if (!$c) return [false, 0.0, 'کد تخفیف پیدا نشد.', ''];
    // A personal code (e.g. bought with airdrop crystals) only works for its owner,
    // so guessing or leaking someone else's code gives nothing.
    if ((int)($c['owner'] ?? 0) > 0 && (int)$c['owner'] !== (int)$uid) return [false, 0.0, 'کد تخفیف پیدا نشد.', ''];
    if (empty($c['on_flag'])) return [false, 0.0, 'این کد دیگر فعال نیست.', ''];
    if ((int)$c['expires_at'] > 0 && time() > (int)$c['expires_at']) return [false, 0.0, 'این کد منقضی شده.', ''];
    if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) return [false, 0.0, 'سقفِ استفاده از این کد پر شده.', ''];
    if ((float)$subtotal < (float)$c['min_total']) {
        return [false, 0.0, 'حداقلِ سفارش برای این کد ' . fmtNum((float)$c['min_total']) . ' تومان است.', ''];
    }

    $db = cpDb();
    if ($db && (int)$c['per_user_limit'] > 0) {
        $stmt = $db->prepare('SELECT COUNT(*) AS n FROM coupon_redemptions WHERE code = :c AND user_id = :u');
        $stmt->bindValue(':c', $c['code'], SQLITE3_TEXT);
        $stmt->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if ((int)($row['n'] ?? 0) >= (int)$c['per_user_limit']) {
            return [false, 0.0, 'شما قبلا از این کد استفاده کرده‌اید.', ''];
        }
    }

    $discount = $c['kind'] === 'fixed'
        ? (float)$c['value']
        : round((float)$subtotal * ((float)$c['value'] / 100), 0);
    if ((float)$c['max_discount'] > 0) $discount = min($discount, (float)$c['max_discount']);
    $discount = max(0.0, min($discount, (float)$subtotal));

    return [true, $discount, '', $c['code']];
}

function cpRedeem($code, $uid, $orderId, $amount) {
    for ($try = 0; $try < 3; $try++) {
        [$ok, $transient] = cpRedeemOnce($code, $uid, $orderId, $amount);
        if (!$transient) return $ok;
        usleep(random_int(30000, 90000));
    }
    error_log('[coupons] cpRedeem: سه تلاش هم زیرِ قفل شکست خورد — code=' . $code . ' uid=' . $uid);
    return false;
}

function cpRedeemOnce($code, $uid, $orderId, $amount) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return [false, false];

    if ($db->exec('BEGIN IMMEDIATE') === false) return [false, true];
    try {
        $stmt = $db->prepare('SELECT * FROM coupons WHERE code = :c');
        $stmt->bindValue(':c', $code, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) { $db->exec('ROLLBACK'); return [false, false]; }
        if ((int)($row['owner'] ?? 0) > 0 && (int)$row['owner'] !== (int)$uid) { $db->exec('ROLLBACK'); return [false, false]; }
        if (empty($row['on_flag']) || ((int)$row['expires_at'] > 0 && time() > (int)$row['expires_at'])) { $db->exec('ROLLBACK'); return [false, false]; }

        if ((int)$row['max_uses'] > 0 && (int)$row['used'] >= (int)$row['max_uses']) {
            $db->exec('ROLLBACK'); return [false, false];
        }
        if ((int)$row['per_user_limit'] > 0) {
            $cstmt = $db->prepare('SELECT COUNT(*) AS n FROM coupon_redemptions WHERE code = :c AND user_id = :u');
            $cstmt->bindValue(':c', $code, SQLITE3_TEXT);
            $cstmt->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
            $crow = $cstmt->execute()->fetchArray(SQLITE3_ASSOC);
            if ((int)($crow['n'] ?? 0) >= (int)$row['per_user_limit']) { $db->exec('ROLLBACK'); return [false, false]; }
        }

        $stmt = $db->prepare('UPDATE coupons SET used = used + 1 WHERE code = :c');
        $stmt->bindValue(':c', $code, SQLITE3_TEXT);
        $stmt->execute();

        $ins = $db->prepare('INSERT INTO coupon_redemptions (code, user_id, order_id, amount, created_at) VALUES (:c,:u,:o,:a,:t)');
        $ins->bindValue(':c', $code, SQLITE3_TEXT);
        $ins->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $ins->bindValue(':o', (string)$orderId, SQLITE3_TEXT);
        $ins->bindValue(':a', (float)$amount, SQLITE3_FLOAT);
        $ins->bindValue(':t', time(), SQLITE3_INTEGER);
        $ins->execute();

        $db->exec('COMMIT');
        return [true, false];
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        return [false, true];
    }
}

function cpRelease($code, $uid, $orderId) {
    $db = cpDb();
    $code = cpNorm($code);
    if (!$db || $code === '') return false;

    if ($db->exec('BEGIN IMMEDIATE') === false) return false;
    try {
        $del = $db->prepare('DELETE FROM coupon_redemptions WHERE code = :c AND user_id = :u AND order_id = :o');
        $del->bindValue(':c', $code, SQLITE3_TEXT);
        $del->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $del->bindValue(':o', (string)$orderId, SQLITE3_TEXT);
        $del->execute();
        $n = $db->changes();
        if ($n > 0) {
            $up = $db->prepare('UPDATE coupons SET used = MAX(0, used - :n) WHERE code = :c');
            $up->bindValue(':n', $n, SQLITE3_INTEGER);
            $up->bindValue(':c', $code, SQLITE3_TEXT);
            $up->execute();
        }
        $db->exec('COMMIT');
        return $n > 0;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        return false;
    }
}

function cpActiveCode($uid) {
    $db = cpDb();
    if (!$db) return '';
    $st = $db->prepare('SELECT code FROM coupon_active WHERE uid = :u');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    return (string)($st->execute()->fetchArray(SQLITE3_ASSOC)['code'] ?? '');
}

function cpActiveSet($uid, $code, $replace = true) {
    $db = cpDb();
    if (!$db) return false;
    $st = $db->prepare('INSERT OR ' . ($replace ? 'REPLACE' : 'IGNORE') . ' INTO coupon_active (uid, code, at) VALUES (:u, :c, :t)');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':c', cpNorm($code), SQLITE3_TEXT);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    return (bool)$st->execute();
}

function cpActiveClear($uid, $code) {
    $db = cpDb();
    if (!$db) return;
    $st = $db->prepare('DELETE FROM coupon_active WHERE uid = :u AND code = :c');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':c', cpNorm($code), SQLITE3_TEXT);
    $st->execute();
}

function cpActivate($uid, $code) {
    [$ok, , $msg, $c] = cpValidate($code, $uid, 1e15);
    if (!$ok) return [false, $msg, null];
    cpActiveSet($uid, $c);
    return [true, '', cpGet($c)];
}

function cpActiveFor($uid, $subtotal) {
    $code = cpActiveCode($uid);
    if ($code === '' || (float)$subtotal <= 0) return ['', 0.0];
    [$ok, $disc, , $c] = cpValidate($code, $uid, $subtotal);
    if ($ok) return [$c, (float)$disc];
    [$live] = cpValidate($code, $uid, 1e15);
    if (!$live) cpActiveClear($uid, $code);
    return ['', 0.0];
}

function cpActiveUse($uid, $code, $orderId, $discount) {
    $ok = cpRedeem($code, $uid, $orderId, $discount);
    cpActiveClear($uid, $code);
    return $ok;
}

function cpGiveBack($uid, $code, $orderId) {
    if (trim((string)$code) === '') return;
    if (cpRelease($code, $uid, $orderId)) cpActiveSet($uid, $code, false);
}

function cpActivePublic($uid) {
    $code = cpActiveCode($uid);
    if ($code === '') return null;
    [$ok] = cpValidate($code, $uid, 1e15);
    if (!$ok) { cpActiveClear($uid, $code); return null; }
    $c = cpGet($code);
    return ['code' => (string)$c['code'], 'kind' => $c['kind'] === 'fixed' ? 'fixed' : 'percent', 'value' => (float)$c['value'],
            'max' => (float)$c['max_discount'], 'min' => (float)$c['min_total'], 'exp' => (int)$c['expires_at']];
}

function cpValueText(array $c) {
    if (($c['kind'] ?? '') === 'fixed') return fmtNum((float)$c['value']) . ' تومان';
    return rtrim(rtrim(number_format((float)$c['value'], 2, '.', ''), '0'), '.') . '٪';
}

function cpDate($ts) {
    return function_exists('pxJalali') ? trim(explode('|', (string)pxJalali((int)$ts))[0]) : date('Y-m-d', (int)$ts);
}


// ─────────────────────────────────────────────────────────────────────────────
//  Admin coupon maker (/panel ← 🎟 کدهای تخفیف). Public codes have owner = 0.
// ─────────────────────────────────────────────────────────────────────────────

function cpAdminList($limit = 10) {
    $db = cpDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT * FROM coupons WHERE owner = 0 ORDER BY created_at DESC LIMIT :n');
    $st->bindValue(':n', max(1, (int)$limit), SQLITE3_INTEGER);
    $res = $st->execute();
    $out = [];
    while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $out[] = $r;
    return $out;
}

function cpDelete($code) {
    $db = cpDb();
    if (!$db) return false;
    $st = $db->prepare('DELETE FROM coupons WHERE code = :c AND owner = 0');
    $st->bindValue(':c', cpNorm($code), SQLITE3_TEXT);
    $st->execute();
    return $db->changes() > 0;
}

function cpDraftDefault() {
    return ['kind' => 'percent', 'value' => 0, 'count' => 1, 'per_user' => 1, 'days' => 0, 'min' => 0, 'code' => ''];
}

function cpDraftGet($uid) {
    $d = (array)(getState($uid)['data'] ?? []);
    return $d + cpDraftDefault();
}

function cpAdmValueText($d) {
    return $d['kind'] === 'fixed' ? fmtNum((float)$d['value']) . ' تومان'
                                  : rtrim(rtrim(number_format((float)$d['value'], 2, '.', ''), '0'), '.') . '٪';
}

function cpAdminHome($chatId, $msgId = null) {
    $list = cpAdminList(10);
    $t = "🎟 <b>کدهای تخفیف</b>\n";
    if ($list) {
        $t .= "\n";
        foreach ($list as $c) {
            $v = ($c['kind'] === 'fixed') ? fmtNum((float)$c['value']) . 'ت' : rtrim(rtrim(number_format((float)$c['value'], 2, '.', ''), '0'), '.') . '٪';
            $cap = (int)$c['max_uses'] > 0 ? ((int)$c['used'] . '/' . (int)$c['max_uses']) : ((int)$c['used'] . '/∞');
            $on  = empty($c['on_flag']) ? '🔴' : '🟢';
            $exp = (int)$c['expires_at'] > 0 ? (time() > (int)$c['expires_at'] ? ' · منقضی' : '') : '';
            $t .= $on . ' <code>' . h((string)$c['code']) . '</code> — ' . $v . ' — ' . $cap . $exp . "\n";
        }
    }
    $rows = [];
    foreach (array_slice($list, 0, 8) as $c) $rows[] = [btnCb('⚙️ ' . (string)$c['code'], 'cpadm_v_' . $c['code'], 'info')];
    $rows[] = [btnCb('➕ کد جدید', 'cpadm_new', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function cpAdminOne($chatId, $msgId, $code) {
    $c = cpGet($code);
    if (!$c || (int)($c['owner'] ?? 0) !== 0) { cpAdminHome($chatId, $msgId); return; }
    $t  = '🎟 <code>' . h((string)$c['code']) . "</code>\n\n";
    $t .= 'مقدار: <b>' . cpAdmValueText(['kind' => $c['kind'], 'value' => $c['value']]) . "</b>\n";
    $t .= 'استفاده: <b>' . (int)$c['used'] . '</b> از <b>' . ((int)$c['max_uses'] > 0 ? (int)$c['max_uses'] : '∞') . "</b>\n";
    $t .= 'هر نفر: <b>' . ((int)$c['per_user_limit'] > 0 ? (int)$c['per_user_limit'] : '∞') . "</b>\n";
    if ((float)$c['min_total'] > 0)    $t .= 'حداقل سفارش: <b>' . fmtNum((float)$c['min_total']) . "</b> تومان\n";
    if ((int)$c['expires_at'] > 0)     $t .= 'اعتبار تا: <b>' . h(cpDate((int)$c['expires_at'])) . "</b>\n";
    $t .= 'وضعیت: <b>' . (empty($c['on_flag']) ? '🔴 خاموش' : '🟢 روشن') . "</b>";
    $rows = [
        [btnCb(empty($c['on_flag']) ? '🟢 روشن کن' : '🔴 خاموش کن', 'cpadm_t_' . $c['code'], 'info')],
        [btnCb('🗑 حذف', 'cpadm_d_' . $c['code'], 'reject')],
        [btnCb(UT('back'), 'cpadm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function cpAdminNew($chatId, $msgId, $d) {
    $t  = "➕ <b>کد تخفیف جدید</b>\n\n";
    $t .= 'نوع: <b>' . ($d['kind'] === 'fixed' ? 'مبلغ ثابت' : 'درصدی') . "</b>\n";
    $t .= 'مقدار: <b>' . cpAdmValueText($d) . "</b>\n";
    $t .= 'تعداد استفاده: <b>' . ((int)$d['count'] > 0 ? (int)$d['count'] : '∞') . "</b>\n";
    $t .= 'هر نفر: <b>' . ((int)$d['per_user'] > 0 ? (int)$d['per_user'] : '∞') . "</b>\n";
    $t .= 'اعتبار: <b>' . ((int)$d['days'] > 0 ? (int)$d['days'] . ' روز' : 'بدون انقضا') . "</b>\n";
    $t .= 'حداقل سفارش: <b>' . ((float)$d['min'] > 0 ? fmtNum((float)$d['min']) . ' تومان' : '—') . "</b>\n";
    $t .= 'کد: <b>' . ($d['code'] !== '' ? h((string)$d['code']) : 'خودکار') . "</b>";
    $rows = [
        [btnCb('نوع: ' . ($d['kind'] === 'fixed' ? 'مبلغ ثابت' : 'درصدی'), 'cpn_type', 'info')],
        [btnCb('💯 مقدار', 'cpn_val', 'admin'), btnCb('🔢 تعداد', 'cpn_cnt', 'admin')],
        [btnCb('👤 هر نفر', 'cpn_peru', 'admin'), btnCb('⏳ اعتبار (روز)', 'cpn_days', 'admin')],
        [btnCb('🛒 حداقل سفارش', 'cpn_min', 'admin'), btnCb('✏️ کد دلخواه', 'cpn_code', 'admin')],
        [btnCb('✅ بساز و کد بده', 'cpn_make', 'confirm')],
        [btnCb(UT('back'), 'cpadm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function cpAdmAsk() {
    return [
        'val'  => ['مقدارِ تخفیف را بفرست (درصدی: ۱ تا ۱۰۰ — مبلغ ثابت: تومان).', 'value'],
        'cnt'  => ['تعدادِ کلِ استفاده را بفرست (۰ = نامحدود).', 'count'],
        'peru' => ['سقفِ استفاده برای هر نفر را بفرست (۰ = نامحدود).', 'per_user'],
        'days' => ['چند روز اعتبار داشته باشد؟ (۰ = بدون انقضا).', 'days'],
        'min'  => ['حداقلِ مبلغِ سفارش برای این کد (تومان، ۰ = ندارد).', 'min'],
        'code' => ['کدِ دلخواه را بفرست (خالی/یک خط تیره = خودکار). فقط حروفِ انگلیسی و عدد.', 'code'],
    ];
}

function cpAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'cpadm') && !str_starts_with((string)$data, 'cpn_')) return false;
    $uid = admStateUid($chatId);

    if ($data === 'cpadm_home') { answerCb(BOT_TOKEN, $cbId); clearState($uid); cpAdminHome($chatId, $msgId); return true; }
    if ($data === 'cpadm_new')  { answerCb(BOT_TOKEN, $cbId); setState($uid, 'cp_draft', cpDraftDefault()); cpAdminNew($chatId, $msgId, cpDraftDefault()); return true; }

    if (preg_match('/^cpadm_v_(.+)$/', (string)$data, $m)) { answerCb(BOT_TOKEN, $cbId); cpAdminOne($chatId, $msgId, $m[1]); return true; }
    if (preg_match('/^cpadm_t_(.+)$/', (string)$data, $m)) {
        $c = cpGet($m[1]);
        if ($c && (int)($c['owner'] ?? 0) === 0) cpUpsert($m[1], ['on_flag' => empty($c['on_flag']) ? 1 : 0]);
        answerCb(BOT_TOKEN, $cbId, '✅');
        cpAdminOne($chatId, $msgId, $m[1]);
        return true;
    }
    if (preg_match('/^cpadm_d_(.+)$/', (string)$data, $m)) {
        cpDelete($m[1]);
        answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد');
        cpAdminHome($chatId, $msgId);
        return true;
    }

    // new-code builder
    if (str_starts_with($data, 'cpn_')) {
        $d = cpDraftGet($uid);
        if ($data === 'cpn_type') {
            $d['kind'] = $d['kind'] === 'fixed' ? 'percent' : 'fixed';
            setState($uid, 'cp_draft', $d);
            answerCb(BOT_TOKEN, $cbId, '🔁');
            cpAdminNew($chatId, $msgId, $d);
            return true;
        }
        if ($data === 'cpn_make') {
            [$ok, $res] = cpAdminCreate($d);
            if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return true; }
            clearState($uid);
            answerCb(BOT_TOKEN, $cbId, '✅ ساخته شد');
            $c = cpGet($res);
            $t = "✅ <b>کد ساخته شد</b>\n\n🎟 <code>" . h($res) . "</code>\n" .
                 'مقدار: <b>' . cpAdmValueText(['kind' => $c['kind'], 'value' => $c['value']]) . "</b>\n" .
                 'تعداد: <b>' . ((int)$c['max_uses'] > 0 ? (int)$c['max_uses'] : '∞') . "</b>";
            editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb([
                [['text' => '📋 کپی کد', 'copy_text' => ['text' => $res]]],
                [btnCb('➕ کد دیگر', 'cpadm_new', 'confirm'), btnCb('🎟 لیست', 'cpadm_home', 'nav')],
            ]));
            return true;
        }
        $map = ['cpn_val' => 'val', 'cpn_cnt' => 'cnt', 'cpn_peru' => 'peru', 'cpn_days' => 'days', 'cpn_min' => 'min', 'cpn_code' => 'code'];
        if (isset($map[$data])) {
            $f = $map[$data];
            [$prompt, ] = cpAdmAsk()[$f];
            setState($uid, 'cp_in', $d + ['ask' => $f]);
            answerCb(BOT_TOKEN, $cbId);
            sendMsg(BOT_TOKEN, $chatId, $prompt, inlineKb([[btnCb(UT('cancel'), 'cpn_back', 'cancel')]]));
            return true;
        }
        if ($data === 'cpn_back') { answerCb(BOT_TOKEN, $cbId); setState($uid, 'cp_draft', $d); cpAdminNew($chatId, $msgId, $d); return true; }
    }

    answerCb(BOT_TOKEN, $cbId);
    return true;
}

function cpAdminCreate($d) {
    $kind = $d['kind'] === 'fixed' ? 'fixed' : 'percent';
    $val  = (float)$d['value'];
    if ($val <= 0) return [false, 'اول مقدار را تعیین کن'];
    if ($kind === 'percent' && $val > 100) $val = 100;
    $code = cpNorm($d['code'] ?? '');
    if ($code === '' || $code === '-' || $code === '—') {
        $code = '';
        for ($i = 0; $i < 8; $i++) { $try = 'OFF' . strtoupper(bin2hex(random_bytes(3))); if (cpGet($try) === null) { $code = $try; break; } }
        if ($code === '') return [false, 'ساختِ کد نشد؛ دوباره'];
    } else {
        if (!preg_match('/^[A-Z0-9]{3,24}$/', $code)) return [false, 'کد فقط حروفِ انگلیسی و عدد (۳ تا ۲۴)'];
        if (cpGet($code) !== null) return [false, 'این کد از قبل هست'];
    }
    $exp = (int)$d['days'] > 0 ? time() + (int)$d['days'] * 86400 : 0;
    $ok = cpUpsert($code, [
        'kind' => $kind, 'value' => $val,
        'max_uses' => max(0, (int)$d['count']), 'used' => 0,
        'per_user_limit' => max(0, (int)$d['per_user']),
        'min_total' => max(0.0, (float)$d['min']), 'max_discount' => 0,
        'expires_at' => $exp, 'on_flag' => 1, 'created_at' => time(), 'owner' => 0,
    ]);
    return $ok ? [true, $code] : [false, 'ذخیره نشد'];
}

function cpAdminState($action, $msg, $uid, $chatId) {
    if ($action !== 'cp_in') return false;
    if (!isAdmin($uid)) { clearState($uid); return true; }
    $d = cpDraftGet($uid);
    $f = (string)($d['ask'] ?? '');
    $ask = cpAdmAsk();
    if (!isset($ask[$f])) { clearState($uid); return true; }
    [, $field] = $ask[$f];
    $raw = trim((string)($msg['text'] ?? ''));

    if ($field === 'code') {
        $d['code'] = ($raw === '-' || $raw === '—') ? '' : cpNorm($raw);
    } else {
        $num = function_exists('maNum') ? maNum($raw) : (float)preg_replace('/[^0-9.]/', '', $raw);
        if ($raw === '' || !is_finite($num) || $num < 0) {
            sendMsg(BOT_TOKEN, $chatId, '⚠️ یک عددِ معتبر بفرست.');
            return true;
        }
        $d[$field] = in_array($field, ['count', 'per_user', 'days'], true) ? (int)round($num) : $num;
    }
    unset($d['ask']);
    setState($uid, 'cp_draft', $d);
    cpAdminNew($chatId, 0, $d);
    return true;
}

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
        created_at INTEGER NOT NULL DEFAULT 0
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
        'on_flag' => 1, 'created_at' => time(),
    ];
    $row = array_merge($cur, $fields);
    $stmt = $db->prepare('INSERT OR REPLACE INTO coupons
        (code, kind, value, max_uses, used, per_user_limit, min_total, max_discount, expires_at, on_flag, created_at)
        VALUES (:code,:kind,:value,:max_uses,:used,:pul,:min_total,:max_discount,:exp,:on,:created)');
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
        $stmt = $db->prepare('SELECT max_uses, used, per_user_limit FROM coupons WHERE code = :c');
        $stmt->bindValue(':c', $code, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$row) { $db->exec('ROLLBACK'); return [false, false]; }

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

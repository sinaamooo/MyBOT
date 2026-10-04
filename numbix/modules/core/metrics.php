<?php
defined('NB_ROOT') || exit;

function monPlanets() {
    return [
        'numbers'  => ['☎️', 'شماره مجازی'],
        'services' => ['🧩', 'خدماتِ تلگرام و اینستاگرام'],
        'wallet'   => ['💳', 'کیف پول و پرداخت'],
        'games'    => ['🎮', 'بازی‌ها و الماس'],
        'tools'    => ['💹', 'قیمت، ترجمه و پاسخ خودکار'],
        'groups'   => ['👥', 'پیام‌های گروه'],
        'menu'     => ['💬', 'منو و پیوی'],
        'support'  => ['🎧', 'پشتیبانی'],
        'airdrop'  => ['🎁', 'ایردراپ و کد تخفیف'],
        'admin'    => ['👑', 'مدیریت'],
        'cron'     => ['⏱', 'کارهای پس‌زمینه'],
        'media'    => ['🖼', 'تصاویرِ مینی‌اپ'],
        'shield'   => ['🛡', 'سپرِ ضدِ اسپم'],
        'other'    => ['✦', 'سایر'],
    ];
}

function monServices() {
    return [
        'telegram'  => 'تلگرام',
        'numprov'   => 'فروشنده‌ی شماره',
        'smm_a'     => 'پنلِ خدمات ۱',
        'smm_b'     => 'پنلِ خدمات ۲',
        'prices'    => 'منابعِ قیمت',
        'gateway'   => 'درگاهِ پرداخت',
        'translate' => 'ترجمه',
        'web'       => 'وب',
    ];
}

function &monRef() {
    static $s = null;
    if ($s === null) $s = ['on' => false, 'skip' => false, 'bg' => false, 't0' => microtime(true),
                           'p' => '', 'a' => '', 'x' => [], 'e' => 0, 'f' => []];
    return $s;
}

function monBegin() {
    if (PHP_SAPI === 'cli') return;
    $s = &monRef();
    if ($s['on']) return;
    $s['on'] = true;
    $s['t0'] = (float)($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    set_error_handler(function ($no) {
        if (error_reporting() & $no) { $r = &monRef(); $r['e']++; }
        return false;
    });
    register_shutdown_function('monEnd');
}

function monSection($planet, $act = '') {
    $s = &monRef();
    $p = preg_replace('/[^a-z0-9_]/', '', strtolower((string)$planet));
    $s['p'] = isset(monPlanets()[$p]) ? $p : 'other';
    $s['a'] = substr(preg_replace('/[^a-z0-9_]/', '', strtolower((string)$act)), 0, 24);
}

function monSkip() {
    $s = &monRef();
    $s['skip'] = true;
}

function monBucket($ms) {
    foreach ([50, 100, 250, 500, 1000, 2500, 5000] as $i => $b) if ($ms < $b) return $i;
    return 7;
}

function monExt($svc, $ms, $ok, $code = '') {
    $s = &monRef();
    if (!$s['on']) return;
    $k = preg_replace('/[^a-z0-9_]/', '', (string)$svc);
    if (!isset($s['x'][$k])) $s['x'][$k] = [0, 0, 0, 0, array_fill(0, 8, 0), []];
    if ($code !== '') $s['x'][$k][5][$code] = ($s['x'][$k][5][$code] ?? 0) + 1;
    $ms = (int)$ms;
    $s['x'][$k][0]++;
    if (!$ok) $s['x'][$k][1]++;
    $s['x'][$k][2] += $ms;
    if ($ms > $s['x'][$k][3]) $s['x'][$k][3] = $ms;
    $s['x'][$k][4][monBucket($ms)]++;
}

function monCode($ch, $failed) {
    if ($failed) return 'c' . (int)curl_errno($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return $code >= 400 ? 'h' . $code : '';
}

function monCurl($ch, $svc) {
    $t = microtime(true);
    $r = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    monExt($svc, (microtime(true) - $t) * 1000, $r !== false && $code > 0 && $code < 500 && $code !== 429, monCode($ch, $r === false));
    return $r;
}

function monCurlDone($h, $svc) {
    $code = (int)curl_getinfo($h, CURLINFO_HTTP_CODE);
    $bad = curl_errno($h) !== 0;
    monExt($svc, (float)curl_getinfo($h, CURLINFO_TOTAL_TIME) * 1000, !$bad && $code > 0 && $code < 500 && $code !== 429, monCode($h, $bad));
}

function monFunds($k) {
    $s = &monRef();
    $k = preg_replace('/[^a-z0-9_]/', '', (string)$k);
    if ($k === '' || isset($s['f'][$k])) return;
    $s['f'][$k] = 1;
    if (function_exists('adminAlertOnce'))
        adminAlertOnce('funds_' . $k, "🔴 <b>کمبودِ موجودی</b>\n\n" . h(monServices()[$k] ?? $k) .
            " سفارش را به‌خاطرِ موجودیِ ناکافی رد کرد. حسابِ فروشنده را شارژ کنید.\n🛰 جزئیات: پنلِ وب ← رصدِ زنده", 1800);
}

function monBlocked($why) {
    $s = &monRef();
    $s['skip'] = true;
    monWrite("B\t" . preg_replace('/[^a-z0-9_]/', '', (string)$why) . "\n");
}

function monWrite($line) {
    if (!defined('DATA_DIR')) return;
    $d = DATA_DIR . '/mon';
    if (!is_dir($d)) { @mkdir($d, 0700, true); if (!is_dir($d)) return; }
    @file_put_contents($d . '/r' . intdiv(time(), 60) . '.log', $line, FILE_APPEND);
}

function monLine(array $s, $ms) {
    $x = [];
    foreach ($s['x'] as $k => $v) {
        $cs = [];
        foreach ($v[5] as $c => $n) $cs[] = preg_replace('/[^a-z0-9]/', '', (string)$c) . '=' . (int)$n;
        $x[] = $k . ':' . $v[0] . ':' . $v[1] . ':' . $v[2] . ':' . $v[3] . ':' . implode('.', $v[4]) . ':' . implode(';', $cs);
    }
    $fatal = 0; $hang = 0;
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $fatal = 1;
        if (stripos((string)$e['message'], 'maximum execution time') !== false) $hang = 1;
    }
    $p = $s['p'] !== '' ? $s['p'] : 'other';
    $line = "R\t{$p}\t{$s['a']}\t" . max(0, (int)$ms) . "\t" . (int)$s['e'] . "\t{$fatal}\t{$hang}\t" .
            (int)(memory_get_peak_usage(true) / 1024) . "\t" . implode(',', $x) . "\n";
    foreach ($s['f'] as $k => $_) $line .= "F\t{$k}\n";
    return $line;
}

function monSplit() {
    $s = &monRef();
    if (!$s['on'] || $s['skip'] || $s['bg']) return;
    $now = microtime(true);
    monWrite(monLine($s, ($now - $s['t0']) * 1000));
    $s = ['on' => true, 'skip' => false, 'bg' => true, 't0' => $now, 'p' => 'cron', 'a' => 'bg', 'x' => [], 'e' => 0, 'f' => []];
}

function monEnd() {
    $s = &monRef();
    if (!$s['on'] || $s['skip']) return;
    $ms = (microtime(true) - $s['t0']) * 1000;
    if ($s['bg'] && $ms < 30 && !$s['x'] && !$s['e'] && !$s['f'] && !error_get_last()) return;
    monWrite(monLine($s, $ms));
}

function monForUpdate($u) {
    if (isset($u['callback_query'])) {
        $cb = $u['callback_query'];
        $uid = (int)($cb['from']['id'] ?? 0);
        $d = (string)($cb['data'] ?? '');
        $pre = preg_match('/^[a-z]+/', $d, $m) ? $m[0] : 'x';
        if (($cb['message']['chat']['type'] ?? 'private') === 'private' && function_exists('isAdmin') && isAdmin($uid))
            { monSection('admin', 'cb_' . $pre); return; }
        $map = [
            'games'    => '/^(gm|mn|qz|wh|tp|bk|dm|cv|gv|rs|to)/',
            'tools'    => '/^(px|tl|ar)/',
            'services' => '/^(sv|shop|trk)/',
            'numbers'  => '/^(num|ma|ocancel)/',
            'support'  => '/^(sup|reply)/',
            'wallet'   => '/^(tu|pay|akyc|aok|ano|gwchk|rcpt|cp|tr_)/',
        ];
        foreach ($map as $p => $re) if (preg_match($re, $d)) { monSection($p, 'cb_' . $pre); return; }
        monSection('menu', 'cb_' . $pre);
        return;
    }
    $msg = $u['message'] ?? $u['edited_message'] ?? $u['channel_post'] ?? null;
    if (is_array($msg)) {
        $ct = (string)($msg['chat']['type'] ?? 'private');
        $t = (string)($msg['text'] ?? '');
        $kind = $t !== '' ? ($t[0] === '/' ? 'cmd' : 'text') : (isset($msg['photo']) ? 'photo' : (isset($msg['document']) ? 'file' : 'other'));
        if ($ct !== 'private') { monSection('groups', $kind); return; }
        $uid = (int)($msg['from']['id'] ?? 0);
        monSection(function_exists('isAdmin') && isAdmin($uid) ? 'admin' : 'menu', $kind);
        return;
    }
    if (isset($u['my_chat_member']) || isset($u['chat_member'])) { monSection('groups', 'member'); return; }
    monSection('other', 'update');
}

function monForApi($action) {
    $a = (string)$action;
    if (str_starts_with($a, 'sv_'))        { monSection('services', $a); return; }
    if (str_starts_with($a, 'wh_'))        { monSection('games', $a); return; }
    if (str_starts_with($a, 'airdrop_'))   { monSection('airdrop', $a); return; }
    if (str_starts_with($a, 'cp_') || $a === 'coupon') { monSection('airdrop', $a); return; }
    if (str_starts_with($a, 'sup'))        { monSection('support', $a); return; }
    if (str_starts_with($a, 'pay') || str_starts_with($a, 'tu') || str_starts_with($a, 'kyc')) { monSection('wallet', $a); return; }
    monSection('numbers', $a !== '' ? $a : 'api');
}

function monDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    try {
        $db = nbRawOpen(DATA_DIR . '/monitor.sqlite');
        $db->busyTimeout(4000);
        $db->exec('PRAGMA journal_mode=WAL');
        $db->exec('PRAGMA synchronous=NORMAL');
        $h = '';
        for ($i = 0; $i < 8; $i++) $h .= ", h$i INTEGER NOT NULL DEFAULT 0";
        $db->exec("CREATE TABLE IF NOT EXISTS s (m INTEGER NOT NULL, p TEXT NOT NULL, a TEXT NOT NULL, n INTEGER NOT NULL, e INTEGER NOT NULL,
                   fa INTEGER NOT NULL, hg INTEGER NOT NULL, tsum INTEGER NOT NULL, tmax INTEGER NOT NULL, mem INTEGER NOT NULL $h, PRIMARY KEY (m, p, a))");
        $db->exec("CREATE TABLE IF NOT EXISTS x (m INTEGER NOT NULL, k TEXT NOT NULL, n INTEGER NOT NULL, e INTEGER NOT NULL,
                   tsum INTEGER NOT NULL, tmax INTEGER NOT NULL $h, PRIMARY KEY (m, k))");
        $db->exec('CREATE TABLE IF NOT EXISTS ev (m INTEGER NOT NULL, k TEXT NOT NULL, n INTEGER NOT NULL, PRIMARY KEY (m, k))');
        $db->exec('CREATE TABLE IF NOT EXISTS xc (m INTEGER NOT NULL, k TEXT NOT NULL, c TEXT NOT NULL, n INTEGER NOT NULL, PRIMARY KEY (m, k, c))');
    } catch (Throwable $e) {
        error_log('[monitor] ' . $e->getMessage());
        $db = false;
        return null;
    }
    return $db;
}

function monParse($file, array &$agg) {
    $fh = @fopen($file, 'r');
    if (!$fh) return;
    while (($l = fgets($fh)) !== false) {
        $c = explode("\t", rtrim($l, "\n"));
        if ($c[0] === 'R' && count($c) >= 9) {
            $k = $c[1] . '|' . $c[2];
            $ms = (int)$c[3];
            if (!isset($agg['s'][$k])) $agg['s'][$k] = [0, 0, 0, 0, 0, 0, 0, array_fill(0, 8, 0)];
            $r = &$agg['s'][$k];
            $r[0]++; $r[1] += (int)$c[4] > 0 ? 1 : 0; $r[2] += (int)$c[5]; $r[3] += (int)$c[6];
            $r[4] += $ms; $r[5] = max($r[5], $ms); $r[6] = max($r[6], (int)$c[7]); $r[7][monBucket($ms)]++;
            unset($r);
            if ($c[8] !== '') foreach (explode(',', $c[8]) as $xs) {
                $p = explode(':', $xs);
                if (count($p) < 6) continue;
                if (!isset($agg['x'][$p[0]])) $agg['x'][$p[0]] = [0, 0, 0, 0, array_fill(0, 8, 0)];
                $x = &$agg['x'][$p[0]];
                $x[0] += (int)$p[1]; $x[1] += (int)$p[2]; $x[2] += (int)$p[3]; $x[3] = max($x[3], (int)$p[4]);
                foreach (explode('.', $p[5]) as $i => $v) if ($i < 8) $x[4][$i] += (int)$v;
                unset($x);
                if (($p[6] ?? '') !== '') foreach (explode(';', $p[6]) as $cn) {
                    [$c, $n] = explode('=', $cn, 2) + [1 => 0];
                    if ($c !== '') $agg['xc'][$p[0] . '|' . $c] = ($agg['xc'][$p[0] . '|' . $c] ?? 0) + (int)$n;
                }
            }
        } elseif (($c[0] === 'B' || $c[0] === 'F') && isset($c[1])) {
            $k = $c[0] . ':' . $c[1];
            $agg['ev'][$k] = ($agg['ev'][$k] ?? 0) + 1;
        }
    }
    fclose($fh);
}

function monStore($db, $m, array $agg) {
    $hc = 'h0, h1, h2, h3, h4, h5, h6, h7';
    $hv = ':h0, :h1, :h2, :h3, :h4, :h5, :h6, :h7';
    $hu = '';
    for ($i = 0; $i < 8; $i++) $hu .= ", h$i = h$i + excluded.h$i";
    $st = $db->prepare("INSERT INTO s (m, p, a, n, e, fa, hg, tsum, tmax, mem, $hc) VALUES (:m, :p, :a, :n, :e, :fa, :hg, :ts, :tx, :mem, $hv)
                        ON CONFLICT(m, p, a) DO UPDATE SET n = n + excluded.n, e = e + excluded.e, fa = fa + excluded.fa, hg = hg + excluded.hg,
                        tsum = tsum + excluded.tsum, tmax = max(tmax, excluded.tmax), mem = max(mem, excluded.mem) $hu");
    foreach ($agg['s'] ?? [] as $k => $r) {
        [$p, $a] = explode('|', $k, 2) + [1 => ''];
        $st->reset();
        $st->bindValue(':m', $m, SQLITE3_INTEGER); $st->bindValue(':p', $p, SQLITE3_TEXT); $st->bindValue(':a', $a, SQLITE3_TEXT);
        foreach ([':n' => 0, ':e' => 1, ':fa' => 2, ':hg' => 3, ':ts' => 4, ':tx' => 5, ':mem' => 6] as $b => $i) $st->bindValue($b, (int)$r[$i], SQLITE3_INTEGER);
        for ($i = 0; $i < 8; $i++) $st->bindValue(":h$i", (int)$r[7][$i], SQLITE3_INTEGER);
        $st->execute();
    }
    $st = $db->prepare("INSERT INTO x (m, k, n, e, tsum, tmax, $hc) VALUES (:m, :k, :n, :e, :ts, :tx, $hv)
                        ON CONFLICT(m, k) DO UPDATE SET n = n + excluded.n, e = e + excluded.e, tsum = tsum + excluded.tsum, tmax = max(tmax, excluded.tmax) $hu");
    foreach ($agg['x'] ?? [] as $k => $x) {
        $st->reset();
        $st->bindValue(':m', $m, SQLITE3_INTEGER); $st->bindValue(':k', (string)$k, SQLITE3_TEXT);
        foreach ([':n' => 0, ':e' => 1, ':ts' => 2, ':tx' => 3] as $b => $i) $st->bindValue($b, (int)$x[$i], SQLITE3_INTEGER);
        for ($i = 0; $i < 8; $i++) $st->bindValue(":h$i", (int)$x[4][$i], SQLITE3_INTEGER);
        $st->execute();
    }
    $st = $db->prepare('INSERT INTO xc (m, k, c, n) VALUES (:m, :k, :c, :n) ON CONFLICT(m, k, c) DO UPDATE SET n = n + excluded.n');
    foreach ($agg['xc'] ?? [] as $kc => $n) {
        [$k, $c] = explode('|', $kc, 2);
        $st->reset();
        $st->bindValue(':m', $m, SQLITE3_INTEGER); $st->bindValue(':k', $k, SQLITE3_TEXT); $st->bindValue(':c', $c, SQLITE3_TEXT); $st->bindValue(':n', (int)$n, SQLITE3_INTEGER);
        $st->execute();
    }
    $st = $db->prepare('INSERT INTO ev (m, k, n) VALUES (:m, :k, :n) ON CONFLICT(m, k) DO UPDATE SET n = n + excluded.n');
    foreach ($agg['ev'] ?? [] as $k => $n) {
        $st->reset();
        $st->bindValue(':m', $m, SQLITE3_INTEGER); $st->bindValue(':k', (string)$k, SQLITE3_TEXT); $st->bindValue(':n', (int)$n, SQLITE3_INTEGER);
        $st->execute();
    }
}

function monCompact() {
    if (!defined('DATA_DIR') || !is_dir(DATA_DIR . '/mon')) return;
    $fp = @fopen(DATA_DIR . '/mon/.compact', 'c');
    if (!$fp) return;
    if (!flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return; }
    try {
        $db = monDb();
        if (!$db) return;
        $now = intdiv(time(), 60);
        $files = (glob(DATA_DIR . '/mon/r*.log') ?: []);
        sort($files);
        $done = 0;
        foreach ($files as $f) {
            $m = (int)substr(basename($f, '.log'), 1);
            if ($m > $now - 2) continue;
            if ($m >= $now - 4320) {
                $agg = ['s' => [], 'x' => [], 'ev' => [], 'xc' => []];
                monParse($f, $agg);
                $db->exec('BEGIN');
                monStore($db, $m, $agg);
                $db->exec('COMMIT');
            }
            @unlink($f);
            if (++$done >= 60) break;
        }
        $old = $now - 4320;
        foreach (['s', 'x', 'ev', 'xc'] as $__t) {
            $__st = $db->prepare("DELETE FROM $__t WHERE m < :o");
            if ($__st) { $__st->bindValue(':o', $old, SQLITE3_INTEGER); $__st->execute(); }
        }
    } catch (Throwable $e) {
        error_log('[monitor] ' . $e->getMessage());
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

function monP95(array $h) {
    $n = array_sum($h);
    if ($n <= 0) return 0;
    $edges = [50, 100, 250, 500, 1000, 2500, 5000, 10000];
    $need = $n * 0.95; $run = 0;
    foreach ($h as $i => $c) { $run += $c; if ($run >= $need) return $edges[$i]; }
    return 10000;
}

function monSecretList() {
    static $list = null;
    if ($list !== null) return $list;
    $list = [];
    foreach (['BOT_TOKEN', 'WEBHOOK_SECRET', 'CRON_KEY', 'HEALTH_KEY', 'ADMIN_PANEL_PASS', 'FIVESIM_TOKEN'] as $c)
        if (defined($c) && strlen((string)constant($c)) >= 6) $list[] = (string)constant($c);
    $c = function_exists('cfg') ? cfg() : [];
    $walk = function ($a) use (&$walk, &$list) {
        foreach ((array)$a as $k => $v) {
            if (is_array($v)) { $walk($v); continue; }
            if (is_string($v) && strlen($v) >= 8 && preg_match('/(key|token|secret|merchant|pass)/i', (string)$k)) $list[] = $v;
        }
    };
    $walk([$c['gateway'] ?? [], $c['irpay'] ?? [], $c['svc'] ?? [], $c['numbers'] ?? []]);
    usort($list, fn($a, $b) => strlen($b) - strlen($a));
    return $list = array_values(array_unique($list));
}

function monMask($s) {
    $s = (string)$s;
    foreach (monSecretList() as $sec) $s = str_replace($sec, '‹محرمانه›', $s);
    $s = preg_replace('/\d{6,12}:[A-Za-z0-9_-]{30,}/', '‹توکن›', $s);
    $s = preg_replace('/eyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}/', '‹JWT›', $s);
    $s = preg_replace('/\b(key|token|api_key|secret|password|pass|merchant|authority)=([^&\s"\']+)/i', '$1=‹محرمانه›', $s);
    $s = preg_replace('/\b[a-f0-9]{32,}\b/i', '‹hex›', $s);
    $s = preg_replace('/\b(\d{4})[ -]?\d{4}[ -]?\d{4}[ -]?(\d{4})\b/', '$1-••••-••••-$2', $s);
    $s = preg_replace('/(?<!\d)(?:\+?98|0)9\d{2}(\d{3})(\d{4})(?!\d)/', '09••••$2', $s);
    foreach (array_filter([defined('DATA_DIR') ? rtrim(DATA_DIR, '/') : '', rtrim(NB_ROOT, '/')]) as $p) $s = str_replace($p, '…', $s);
    return $s;
}

function monLogTail($bytes = 65536) {
    $f = DATA_DIR . '/php_errors.log';
    if (!is_file($f)) return [];
    $sz = (int)@filesize($f);
    $fh = @fopen($f, 'r');
    if (!$fh) return [];
    if ($sz > $bytes) fseek($fh, $sz - $bytes);
    $raw = (string)stream_get_contents($fh);
    fclose($fh);
    if ($sz > $bytes) $raw = (string)substr($raw, (int)strpos($raw, "\n") + 1);
    $groups = [];
    foreach (explode("\n", $raw) as $l) {
        $l = trim($l);
        if ($l === '') continue;
        $t = 0;
        if (preg_match('/^\[([^\]]+)\]\s*(.*)$/s', $l, $m)) { $t = (int)strtotime($m[1]); $l = $m[2]; }
        $lvl = preg_match('/PHP (Fatal|Parse)/i', $l) ? 'fatal' : (preg_match('/PHP (Warning|Deprecated|Notice)/i', $l) ? 'warn' : 'info');
        $msg = mb_substr(monMask($l), 0, 400);
        $sig = md5(preg_replace('/\d+/', '#', $msg));
        if (!isset($groups[$sig])) $groups[$sig] = ['msg' => $msg, 'lvl' => $lvl, 'n' => 0, 'last' => 0];
        $groups[$sig]['n']++;
        if ($t >= $groups[$sig]['last']) { $groups[$sig]['last'] = $t; $groups[$sig]['msg'] = $msg; }
    }
    $out = array_values($groups);
    usort($out, fn($a, $b) => $b['last'] <=> $a['last']);
    return array_slice($out, 0, 40);
}

function monNoNet($set = null) {
    static $on = false;
    if ($set !== null) $on = (bool)$set;
    return $on;
}

function monBalances($refresh = false) {
    $out = [];
    if (function_exists('numReady') && numReady()) {
        $c = function_exists('maCacheGet') ? maCacheGet('mon_bal_num', 0) : null;
        if (!$refresh && monNoNet() && !is_array($c)) $c = null;
        elseif ($refresh || (!monNoNet() && (!is_array($c) || time() - (int)($c['at'] ?? 0) > 600))) {
            [$bal, $cur, $err] = numBalanceDo();
            $c = ['bal' => (float)$bal, 'cur' => (string)$cur, 'err' => (string)$err, 'at' => time()];
            if (function_exists('maCachePut')) maCachePut('mon_bal_num', $c);
        }
        if (is_array($c)) $out[] = ['k' => 'numprov', 'name' => numProvName(), 'bal' => $c['bal'], 'cur' => $c['cur'], 'err' => monMask($c['err']), 'at' => $c['at']];
    }
    if (function_exists('svPanels'))
        foreach (svPanels() as $pv) {
            if (!svReady($pv)) continue;
            $c = function_exists('maCacheGet') ? maCacheGet('mon_bal_smm_' . $pv, 0) : null;
            if (!$refresh && monNoNet() && !is_array($c)) continue;
            if ($refresh || (!monNoNet() && (!is_array($c) || time() - (int)($c['at'] ?? 0) > 600))) {
                [$j, $err, $kind] = svHttp(['action' => 'balance'], 15, $pv);
                $c = is_array($j) && isset($j['balance'])
                    ? ['bal' => (float)str_replace(',', '', (string)$j['balance']), 'cur' => mb_strtoupper(mb_substr(trim((string)($j['currency'] ?? '')), 0, 10)), 'err' => '', 'at' => time()]
                    : ['bal' => 0.0, 'cur' => '', 'err' => !is_array($j) && $kind === 'api' ? svErrText($err) : (string)($err ?: 'پاسخِ «balance» پنل ناقص بود.'), 'at' => time()];
                if (function_exists('maCachePut')) maCachePut('mon_bal_smm_' . $pv, $c);
            }
            $out[] = ['k' => 'smm_' . $pv, 'name' => svPanelName($pv), 'bal' => (float)$c['bal'], 'cur' => (string)$c['cur'],
                      'err' => monMask((string)$c['err']), 'at' => (int)$c['at']];
        }
    return $out;
}

function monBgTick() {
    $mark = DATA_DIR . '/mon/.bal_at';
    if (!is_dir(DATA_DIR . '/mon') || time() - (@filemtime($mark) ?: 0) < 600) return;
    @touch($mark);
    try { monBalances(); } catch (Throwable $e) { error_log('[monitor] ' . monMask($e->getMessage())); }
}

function monQueues() {
    $q = [];
    try { $q['num_wait'] = class_exists('MaOrder') ? (int)MaOrder::countBy(MaOrder::PAID) : 0; } catch (Throwable $e) { $q['num_wait'] = 0; }
    try { $q['topup_open'] = class_exists('Order') ? (int)Order::countBy(Order::PENDING) : 0; } catch (Throwable $e) { $q['topup_open'] = 0; }
    $sv = function_exists('svStats') ? svStats() : [];
    $q['svc_run'] = (int)($sv['run'] ?? 0);
    $q['svc_check'] = (int)($sv['check'] ?? 0);
    $q['kyc'] = function_exists('kycQueue') ? count(kycQueue()) : 0;
    $b = function_exists('load') ? load('broadcast', true) : null;
    $q['bc'] = is_array($b) && empty($b['done']) && !empty($b['total'])
        ? ['sent' => (int)($b['sent'] ?? 0), 'fail' => (int)($b['fail'] ?? 0), 'total' => (int)$b['total']] : null;
    return $q;
}

function monSystem() {
    $o = ['php' => PHP_VERSION, 'mem_limit' => (string)ini_get('memory_limit'), 'max_exec' => (int)ini_get('max_execution_time')];
    $o['opcache'] = null;
    if (function_exists('opcache_get_status')) {
        $st = @opcache_get_status(false);
        if (is_array($st)) $o['opcache'] = ['on' => !empty($st['opcache_enabled']),
            'hit' => round((float)($st['opcache_statistics']['opcache_hit_rate'] ?? 0), 1),
            'mem' => round(((float)($st['memory_usage']['used_memory'] ?? 0)) / 1048576, 1)];
    }
    $free = @disk_free_space(DATA_DIR); $tot = @disk_total_space(DATA_DIR);
    $o['disk_free'] = $free !== false ? (int)$free : null;
    $o['disk_total'] = $tot !== false ? (int)$tot : null;
    $sz = 0; $dbs = [];
    foreach ((glob(DATA_DIR . '/*.sqlite') ?: []) as $f) { $s = (int)@filesize($f); $sz += $s; $dbs[basename($f, '.sqlite')] = $s; }
    foreach ((glob(DATA_DIR . '/*.json') ?: []) as $f) $sz += (int)@filesize($f);
    arsort($dbs);
    $o['data_size'] = $sz;
    $o['dbs'] = array_slice($dbs, 0, 6, true);
    $o['load'] = function_exists('sys_getloadavg') ? @sys_getloadavg() : null;
    $o['writable'] = is_writable(DATA_DIR);
    $o['users'] = null;
    if (function_exists('maCacheGet')) {
        $u = maCacheGet('mon_users', 120);
        if ($u === null && function_exists('usersDb') && ($db = usersDb())) {
            $u = (int)@$db->querySingle('SELECT COUNT(*) FROM users');
            maCachePut('mon_users', $u);
        }
        $o['users'] = $u;
    }
    return $o;
}

function monStatus($p95, $base, $err, $n, $fa, $hg, $slowMs) {
    if ($n <= 0) return 'idle';
    if ($hg > 0 || $fa > 0 || ($n >= 5 && $err / $n > 0.2)) return 'down';
    if ($p95 > max($slowMs, $base * 2) || ($n >= 5 && $err / $n > 0.05)) return 'slow';
    return 'ok';
}

function monSnapshot() {
    monCompact();
    $now = intdiv(time(), 60);
    $db = monDb();

    $P = monPlanets(); $X = monServices();
    $pl = [];
    foreach ($P as $k => [$ic, $nm]) $pl[$k] = ['k' => $k, 'icon' => $ic, 'name' => $nm, 'n15' => 0, 'e15' => 0, 'fa15' => 0, 'hg15' => 0,
        'h15' => array_fill(0, 8, 0), 'h24' => array_fill(0, 8, 0), 'n24' => 0, 'tmax15' => 0, 'series' => array_fill(0, 60, 0), 'eseries' => array_fill(0, 60, 0)];
    $acts = [];
    $sx = [];
    foreach ($X as $k => $nm) $sx[$k] = ['k' => $k, 'name' => $nm, 'n15' => 0, 'e15' => 0, 'h15' => array_fill(0, 8, 0), 'h24' => array_fill(0, 8, 0),
        'n24' => 0, 'tmax15' => 0, 'series' => array_fill(0, 60, 0)];
    $ev = ['blocked' => array_fill(0, 60, 0), 'b15' => 0, 'b24' => 0, 'why' => [], 'funds' => []];
    $xc = [];

    $addS = function ($m, $p, $a, $n, $e, $fa, $hg, $tsum, $tmax, array $h) use (&$pl, &$acts, $now) {
        if (!isset($pl[$p])) $p = 'other';
        $age = $now - $m;
        $pl[$p]['n24'] += $n;
        foreach ($h as $i => $c) $pl[$p]['h24'][$i] += $c;
        if ($age < 60 && $age >= 0) { $pl[$p]['series'][59 - $age] += $n; $pl[$p]['eseries'][59 - $age] += $e + $fa; }
        if ($age < 15) {
            $pl[$p]['n15'] += $n; $pl[$p]['e15'] += $e; $pl[$p]['fa15'] += $fa; $pl[$p]['hg15'] += $hg;
            $pl[$p]['tmax15'] = max($pl[$p]['tmax15'], $tmax);
            foreach ($h as $i => $c) $pl[$p]['h15'][$i] += $c;
        }
        if ($age < 60) {
            $ak = $p . '|' . $a;
            if (!isset($acts[$ak])) $acts[$ak] = ['p' => $p, 'a' => $a, 'n' => 0, 'e' => 0, 'tsum' => 0, 'tmax' => 0, 'h' => array_fill(0, 8, 0)];
            $acts[$ak]['n'] += $n; $acts[$ak]['e'] += $e + $fa; $acts[$ak]['tsum'] += $tsum; $acts[$ak]['tmax'] = max($acts[$ak]['tmax'], $tmax);
            foreach ($h as $i => $c) $acts[$ak]['h'][$i] += $c;
        }
    };
    $addX = function ($m, $k, $n, $e, $tmax, array $h) use (&$sx, $now) {
        if (!isset($sx[$k])) return;
        $age = $now - $m;
        $sx[$k]['n24'] += $n;
        foreach ($h as $i => $c) $sx[$k]['h24'][$i] += $c;
        if ($age < 60 && $age >= 0) $sx[$k]['series'][59 - $age] += $n;
        if ($age < 15) {
            $sx[$k]['n15'] += $n; $sx[$k]['e15'] += $e; $sx[$k]['tmax15'] = max($sx[$k]['tmax15'], $tmax);
            foreach ($h as $i => $c) $sx[$k]['h15'][$i] += $c;
        }
    };
    $addEv = function ($m, $k, $n) use (&$ev, $now) {
        $age = $now - $m;
        if (str_starts_with($k, 'B:')) {
            $ev['b24'] += $n;
            if ($age < 60 && $age >= 0) $ev['blocked'][59 - $age] += $n;
            if ($age < 15) { $ev['b15'] += $n; $w = substr($k, 2); $ev['why'][$w] = ($ev['why'][$w] ?? 0) + $n; }
        } elseif (str_starts_with($k, 'F:')) {
            $f = substr($k, 2);
            if (!isset($ev['funds'][$f])) $ev['funds'][$f] = ['n60' => 0, 'n24' => 0];
            $ev['funds'][$f]['n24'] += $n;
            if ($age < 60) $ev['funds'][$f]['n60'] += $n;
        }
    };

    if ($db) {
        $from = $now - 1440; $hour = $now - 59; $old = $now - 100;
        $hs = 'SUM(h0) h0, SUM(h1) h1, SUM(h2) h2, SUM(h3) h3, SUM(h4) h4, SUM(h5) h5, SUM(h6) h6, SUM(h7) h7';
        $hx = fn($r) => [(int)$r['h0'], (int)$r['h1'], (int)$r['h2'], (int)$r['h3'], (int)$r['h4'], (int)$r['h5'], (int)$r['h6'], (int)$r['h7']];
        $res = $db->query("SELECT p, SUM(n) n, $hs FROM s WHERE m >= $from AND m < $hour GROUP BY p");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $addS($old, $r['p'], '', (int)$r['n'], 0, 0, 0, 0, 0, $hx($r));
        $res = $db->query("SELECT * FROM s WHERE m >= $hour");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC)))
            $addS((int)$r['m'], $r['p'], $r['a'], (int)$r['n'], (int)$r['e'], (int)$r['fa'], (int)$r['hg'], (int)$r['tsum'], (int)$r['tmax'], $hx($r));
        $res = $db->query("SELECT k, SUM(n) n, $hs FROM x WHERE m >= $from AND m < $hour GROUP BY k");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $addX($old, $r['k'], (int)$r['n'], 0, 0, $hx($r));
        $res = $db->query("SELECT * FROM x WHERE m >= $hour");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $addX((int)$r['m'], $r['k'], (int)$r['n'], (int)$r['e'], (int)$r['tmax'], $hx($r));
        $res = $db->query("SELECT k, SUM(n) n FROM ev WHERE m >= $from AND m < $hour GROUP BY k");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $addEv($old, $r['k'], (int)$r['n']);
        $res = $db->query("SELECT * FROM ev WHERE m >= $hour");
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $addEv((int)$r['m'], $r['k'], (int)$r['n']);
        $res = $db->query('SELECT k, c, SUM(n) n FROM xc WHERE m >= ' . ($now - 14) . ' GROUP BY k, c');
        while ($res && ($r = $res->fetchArray(SQLITE3_ASSOC))) $xc[$r['k']][$r['c']] = ($xc[$r['k']][$r['c']] ?? 0) + (int)$r['n'];
    }
    foreach ((glob(DATA_DIR . '/mon/r*.log') ?: []) as $f) {
        $m = (int)substr(basename($f, '.log'), 1);
        if ($m < $now - 2) continue;
        $one = ['s' => [], 'x' => [], 'ev' => [], 'xc' => []];
        monParse($f, $one);
        foreach ($one['xc'] as $kc => $n) { [$k, $c] = explode('|', $kc, 2); $xc[$k][$c] = ($xc[$k][$c] ?? 0) + $n; }
        foreach ($one['s'] as $k => $r) { [$p, $a] = explode('|', $k, 2) + [1 => '']; $addS($m, $p, $a, $r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[7]); }
        foreach ($one['x'] as $k => $x) $addX($m, $k, $x[0], $x[1], $x[3], $x[4]);
        foreach ($one['ev'] as $k => $n) $addEv($m, $k, $n);
    }

    $worst = [];
    foreach ($acts as $a) {
        $q = monP95($a['h']);
        if ($a['a'] !== '' && (!isset($worst[$a['p']]) || $q > $worst[$a['p']][1])) $worst[$a['p']] = [$a['a'], $q, $a['tmax']];
    }
    $planets = [];
    foreach ($pl as $k => $p) {
        $p95 = monP95($p['h15']); $base = monP95($p['h24']);
        $slowMs = $k === 'cron' ? 20000 : ($k === 'admin' ? 4000 : 1500);
        $st = $k === 'shield' ? ($p['n15'] > 0 ? 'slow' : ($p['n24'] > 0 ? 'ok' : 'idle')) : monStatus($p95, $base, $p['e15'], $p['n15'], $p['fa15'], $p['hg15'], $slowMs);
        if ($st === 'idle' && $p['n24'] > 0) $st = 'ok';
        $planets[] = ['k' => $k, 'icon' => $p['icon'], 'name' => $p['name'], 'st' => $st, 'rpm' => round($p['n15'] / 15, 2), 'n15' => $p['n15'],
                      'n24' => $p['n24'], 'err' => $p['e15'] + $p['fa15'], 'hang' => $p['hg15'], 'p95' => $p95, 'base' => $base,
                      'tmax' => $p['tmax15'], 'series' => $p['series'], 'eseries' => $p['eseries'], 'fatal' => $p['fa15'],
                      'act' => $worst[$k][0] ?? '', 'act_p95' => $worst[$k][1] ?? 0];
    }
    $svcs = [];
    foreach ($sx as $k => $x) {
        if ($x['n24'] <= 0) continue;
        $p95 = monP95($x['h15']); $base = monP95($x['h24']);
        $svcs[] = ['k' => $k, 'name' => $k === 'numprov' && function_exists('numProvName') ? numProvName() : $x['name'],
                   'st' => monStatus($p95, $base, $x['e15'], $x['n15'], 0, 0, 3000), 'rpm' => round($x['n15'] / 15, 2), 'n15' => $x['n15'],
                   'err' => $x['e15'], 'p95' => $p95, 'base' => $base, 'tmax' => $x['tmax15'], 'series' => $x['series'], 'codes' => $xc[$k] ?? new stdClass()];
    }
    $slow = [];
    foreach ($acts as $a) {
        if ($a['n'] < 1) continue;
        $slow[] = ['p' => $a['p'], 'a' => $a['a'] !== '' ? $a['a'] : '—', 'n' => $a['n'], 'err' => $a['e'], 'avg' => (int)round($a['tsum'] / max(1, $a['n'])),
                   'p95' => monP95($a['h']), 'tmax' => $a['tmax']];
    }
    usort($slow, fn($a, $b) => [$b['p95'], $b['tmax']] <=> [$a['p95'], $a['tmax']]);

    $bal = [];
    foreach (monBalances() as $b) {
        $f = $ev['funds'][$b['k']] ?? ['n60' => 0, 'n24' => 0];
        $b['funds60'] = $f['n60']; $b['funds24'] = $f['n24'];
        $b['st'] = ($b['err'] === '' && $b['bal'] <= 0) || $f['n60'] > 0 ? 'down' : ($f['n24'] > 0 || $b['err'] !== '' ? 'slow' : 'ok');
        $bal[] = $b;
    }

    $snap = [
        'now' => time(),
        'planets' => $planets,
        'services' => $svcs,
        'slow' => array_slice($slow, 0, 10),
        'shield' => ['blocked' => $ev['blocked'], 'b15' => $ev['b15'], 'b24' => $ev['b24'], 'why' => $ev['why']],
        'balances' => $bal,
        'queues' => monQueues(),
        'system' => monSystem(),
        'logs' => monLogTail(),
    ];
    $snap['doctor'] = function_exists('drDiagnose') ? drDiagnose($snap) : null;
    return $snap;
}

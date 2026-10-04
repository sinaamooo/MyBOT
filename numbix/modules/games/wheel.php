<?php
defined('NB_ROOT') || exit;

function whDefaults() {
    return [
        'on'           => true,
        'from_hour'    => 10,
        'to_hour'      => 22,
        'tz_offset'    => 12600,
        'coupon_days'  => 7,
        'post_cap'     => 2,
        'gift_mode'    => 'support',
        'report_chat'  => '',
        'report_topic' => 0,
        'entry'        => 'app',
        'app_link'     => '',
        'photo'        => '',
        'title'        => 'گردونه شانس',
        'prizes'       => [
            ['id' => 'teddy',   'on' => true, 'type' => 'gift',   'emoji' => '🧸', 'label' => 'خرس تدی',      'gift_id' => '', 'weight' => 70,  'cap' => 2, 'color' => '#7C3AED'],
            ['id' => 'off5',    'on' => true, 'type' => 'coupon', 'emoji' => '🏷️', 'label' => 'کد تخفیف ۵٪',  'percent' => 5,  'weight' => 280, 'cap' => 0, 'color' => '#0891B2'],
            ['id' => 'rose',    'on' => true, 'type' => 'gift',   'emoji' => '🌹', 'label' => 'گل رز',        'gift_id' => '', 'weight' => 25,  'cap' => 2, 'color' => '#BE123C'],
            ['id' => 'none1',   'on' => true, 'type' => 'none',   'emoji' => '💨', 'label' => 'پوچ',          'weight' => 200, 'cap' => 0, 'color' => '#334155'],
            ['id' => 'diamond', 'on' => true, 'type' => 'gift',   'emoji' => '💎', 'label' => 'الماس',        'gift_id' => '', 'weight' => 2,   'cap' => 1, 'color' => '#D97706'],
            ['id' => 'heart',   'on' => true, 'type' => 'gift',   'emoji' => '💝', 'label' => 'قلب',          'gift_id' => '', 'weight' => 70,  'cap' => 2, 'color' => '#C026D3'],
            ['id' => 'off10',   'on' => true, 'type' => 'coupon', 'emoji' => '🎟️', 'label' => 'کد تخفیف ۱۰٪', 'percent' => 10, 'weight' => 120, 'cap' => 0, 'color' => '#059669'],
            ['id' => 'box',     'on' => true, 'type' => 'gift',   'emoji' => '🎁', 'label' => 'جعبه کادو',    'gift_id' => '', 'weight' => 25,  'cap' => 2, 'color' => '#1D4ED8'],
            ['id' => 'none2',   'on' => true, 'type' => 'none',   'emoji' => '💨', 'label' => 'پوچ',          'weight' => 200, 'cap' => 0, 'color' => '#334155'],
            ['id' => 'cake',    'on' => true, 'type' => 'gift',   'emoji' => '🎂', 'label' => 'کیک',          'gift_id' => '', 'weight' => 8,   'cap' => 1, 'color' => '#EA580C'],
        ],
        'icons' => ['btn_post' => '', 'btn_open' => '', 'btn_given' => ''],
        'btns'  => ['btn_post' => ['color' => 'success'], 'btn_open' => ['color' => 'success'], 'btn_given' => ['color' => 'success']],
        'texts' => [
            'post'          => "<b>گردونه شانس امروز باز شد</b>\n\nجایزه‌ها: گیفت‌های استارزی (خرس تدی، قلب، گل رز، جعبه کادو، کیک، الماس) و کد تخفیف ۵ و ۱۰ درصد\nهر نفر روزی یک بار",
            'entry'         => "<b>گردونه شانس</b>",
            'closed'        => "گردونه‌ی امروز هنوز باز نشده",
            'need_post'     => 'ورود فقط از پستِ گروه',
            'full'          => 'ظرفیتِ این گردونه پر شد',
            'win_gift'      => "<b>تبریک!</b>\n\nاز گردونه <b>{prize}</b> بردی و همین الان برایت فرستاده شد.",
            'win_gift_wait' => "<b>تبریک!</b>\n\nاز گردونه <b>{prize}</b> بردی؛ به‌زودی برایت فرستاده می‌شود.",
            'win_gift_support' => "<b>تبریک!</b>\n\nاز گردونه <b>{prize}</b> بردی.\nپشتیبانی به‌زودی جایزه را برایت می‌فرستد.",
            'win_given'     => "<b>جایزه‌ات رسید!</b>\n\n<b>{prize}</b>ِ گردونه برایت فرستاده شد.",
            'win_coupon'    => "<b>تبریک!</b>\n\nاز گردونه <b>{prize}</b> بردی.\n\nکد تخفیف: <code>{code}</code>\nاعتبار تا: {exp}",
            'win_diamond'   => "<b>تبریک!</b>\n\n<b>{amount}</b> الماس از گردونه بردی و به حسابت اضافه شد.",
            'win_none'      => "این بار شانس با تو نبود",
            'gift_note'     => 'جایزه‌ی گردونه شانس',
            'report'        => "<b>برنده‌ی گردونه</b>\n\nکاربر: {user}\nآیدی: <code>{uid}</code>\nیوزرنیم: {username}\nجایزه: <b>{prize}</b>\nکد: <code>{code}</code>\nزمان: {time}\n\nوضعیت: {status}",
            'st_wait'       => 'منتظرِ تحویل از طرفِ پشتیبانی',
            'st_given'      => 'تحویل شد — {by}',
            'st_auto'       => 'خودکار تحویل شد',
            'st_fail'       => 'ارسالِ خودکار نشد: {err}',
            'pop_given'     => 'ثبت شد',
            'pop_given_old' => 'این جایزه قبلا تحویل شده',
            'pop_staff'     => 'فقط ادمین‌های این گروه',
            'pop_done'      => 'امروز گردونه را چرخوندی',
            'btn_post'      => 'ورود به گردونه',
            'btn_open'      => 'چرخوندن گردونه',
            'btn_given'     => 'جایزه تحویل شد',
            'app_spin'      => 'بچرخون',
            'app_ready'     => 'امروز یک بار شانس داری',
            'app_done'      => 'امروز چرخوندی',
            'app_wait'      => 'گردونه‌ی امروز هنوز باز نشده',
            'app_off'       => 'گردونه خاموش است',
            'app_need'      => 'ورود فقط از پستِ گروه',
            'app_full'      => 'ظرفیتِ این گردونه پر شد',
            'app_win'       => 'تبریک! {prize} بردی',
            'app_lose'      => 'این بار پوچ شد',
            'app_sent'      => 'جزئیاتِ جایزه در چتِ ربات فرستاده شد',
            'app_support'   => 'جایزه به‌زودی از طرفِ پشتیبانی می‌رسد',
            'app_copy'      => 'کپیِ کد',
            'app_nodm'      => 'پیامِ پیوی ارسال نشد',
            'app_close'     => 'باشه',
            'app_rules'     => 'هر نفر روزی یک بار · کدِ تخفیف همین‌جا، گیفت از طرفِ پشتیبانی',
            'app_prizes'    => 'جایزه‌های گردونه',
        ],
    ];
}

function whCfg() {
    static $memo = null, $sig = null;
    $s = cfg()['wheel'] ?? null;
    $k = md5(json_encode($s));
    if ($memo !== null && $sig === $k) return $memo;
    $c = is_array($s) ? array_replace_recursive(whDefaults(), $s) : whDefaults();
    if (is_array($s['prizes'] ?? null)) $c['prizes'] = array_values(array_filter($s['prizes'], 'is_array'));
    $sig = $k;
    return $memo = $c;
}

function whSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['wheel'] ?? null)) $c['wheel'] = [];
        if (!is_array($c['wheel']['prizes'] ?? null)) $c['wheel']['prizes'] = whDefaults()['prizes'];
        $fn($c['wheel']);
    });
}

function whVal($path, $default = null) {
    $v = whCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function whOn() { return !empty(whVal('on')); }

function whT($slug, $vars = []) {
    $t = (string)whVal('texts.' . $slug, whDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function whBtnKeys() { return ['btn_post', 'btn_open', 'btn_given']; }

function whBtn($key, array $b) {
    $color = (string)whVal('btns.' . $key . '.color', '');
    if (isStyle($color)) $b['style'] = $color;
    return btnApplyLabel($b, whT($key), (string)whVal('icons.' . $key, ''));
}

function whNowLocal() { return time() + (int)whVal('tz_offset', 12600); }
function whDay($ts = null) { return gmdate('Y-m-d', ($ts ?? time()) + (int)whVal('tz_offset', 12600)); }

function whDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3') && !dbOn()) return $db = false;
    $path = DATA_DIR . '/wheel.sqlite';
    if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
    try { $db = nbRawOpen($path); }
    catch (Throwable $e) { error_log('[wheel] ' . $e->getMessage()); return $db = false; }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec("CREATE TABLE IF NOT EXISTS wh_spins (
        uid INTEGER NOT NULL, day TEXT NOT NULL, prize TEXT NOT NULL, label TEXT NOT NULL DEFAULT '',
        kind TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT '', code TEXT NOT NULL DEFAULT '',
        err TEXT NOT NULL DEFAULT '', name TEXT NOT NULL DEFAULT '', at INTEGER NOT NULL DEFAULT 0,
        uname TEXT NOT NULL DEFAULT '', given_by TEXT NOT NULL DEFAULT '',
        chat INTEGER NOT NULL DEFAULT 0, msg INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (uid, day))");
    $cols = [];
    $res = $db->query('PRAGMA table_info(wh_spins)');
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $cols[(string)$r['name']] = 1;
    foreach (['uname', 'given_by'] as $c)
        if (!isset($cols[$c]) && preg_match('/^[a-z_][a-z0-9_]*$/', $c)) $db->exec("ALTER TABLE wh_spins ADD COLUMN {$c} TEXT NOT NULL DEFAULT ''");
    foreach (['chat', 'msg'] as $c)
        if (!isset($cols[$c]) && preg_match('/^[a-z_][a-z0-9_]*$/', $c)) $db->exec("ALTER TABLE wh_spins ADD COLUMN {$c} INTEGER NOT NULL DEFAULT 0");
    $db->exec('CREATE INDEX IF NOT EXISTS wh_spins_day ON wh_spins (day, prize)');
    $db->exec("CREATE TABLE IF NOT EXISTS wh_days (
        day TEXT PRIMARY KEY, post_at INTEGER NOT NULL DEFAULT 0, sent_at INTEGER NOT NULL DEFAULT 0,
        msg_id INTEGER NOT NULL DEFAULT 0, chat TEXT NOT NULL DEFAULT '')");
    $db->exec("CREATE TABLE IF NOT EXISTS wh_posts (
        chat INTEGER NOT NULL, day TEXT NOT NULL, st TEXT NOT NULL DEFAULT '', msg INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (chat, day))");
    $db->exec('CREATE INDEX IF NOT EXISTS wh_posts_day ON wh_posts (day, st)');
    $db->exec("CREATE TABLE IF NOT EXISTS wh_tix (
        uid INTEGER PRIMARY KEY, day TEXT NOT NULL, chat INTEGER NOT NULL DEFAULT 0, msg INTEGER NOT NULL DEFAULT 0,
        emsg INTEGER NOT NULL DEFAULT 0, at INTEGER NOT NULL DEFAULT 0)");
    return $db;
}

function whPlanTs($day) {
    $off  = (int)whVal('tz_offset', 12600);
    $from = max(0, min(23, (int)whVal('from_hour', 10)));
    $to   = max($from + 1, min(24, (int)whVal('to_hour', 22)));
    $base = strtotime($day . ' 00:00:00 UTC') - $off;
    $lo   = max(time(), $base + $from * 3600);
    $hi   = $base + $to * 3600 - 60;
    return $lo >= $hi ? time() : random_int($lo, $hi);
}

function whDayRow($day, $create = true) {
    $db = whDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM wh_days WHERE day = :d');
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    $r = $st->execute()->fetchArray(SQLITE3_ASSOC);
    if ($r || !$create) return $r ?: null;
    $ins = $db->prepare('INSERT OR IGNORE INTO wh_days (day, post_at) VALUES (:d, :p)');
    $ins->bindValue(':d', $day, SQLITE3_TEXT);
    $ins->bindValue(':p', whPlanTs($day), SQLITE3_INTEGER);
    $ins->execute();
    return whDayRow($day, false);
}

function whUrl() {
    $b = function_exists('maBaseUrl') ? maBaseUrl() : '';
    if ($b === '' || !preg_match('#^https://#i', $b)) return '';
    return $b . (str_contains($b, '?') ? '&' : '?') . 'app=wheel&v=' . maViewVer();
}

function whGoUrls() {
    $out = [];
    $l = trim((string)whVal('app_link', ''));
    if (preg_match('#^https://t\.me/\S+$#i', $l)) $out[] = $l;
    $bot = function_exists('botUsername') ? botUsername() : '';
    if ($bot !== '') {
        if ((string)whVal('entry', 'app') !== 'bot') $out[] = 'https://t.me/' . $bot . '?startapp=wheel';
        $out[] = 'https://t.me/' . $bot . '?start=wheel';
    }
    return array_values(array_unique($out));
}

function whPostKb() {
    return whGoUrls() ? inlineKb([[whBtn('btn_post', ['callback_data' => 'whgo'])]]) : null;
}

function whPostTo($chat) {
    $kb    = whPostKb();
    $photo = trim((string)whVal('photo', ''));
    if ($photo !== '') {
        $data = ['chat_id' => $chat, 'photo' => $photo, 'caption' => whT('post'), 'parse_mode' => 'HTML'];
        if ($kb) $data['reply_markup'] = kbJson($kb);
        $res = tg(BOT_TOKEN, 'sendPhoto', $data);
        if (empty($res['ok']) && $kb && isStyleError($res)) {
            $data['reply_markup'] = json_encode(stripStyles($kb));
            $res = tg(BOT_TOKEN, 'sendPhoto', $data);
        }
        return $res;
    }
    return sendMsg(BOT_TOKEN, $chat, whT('post'), $kb);
}

function whGroups($day, $limit) {
    if (!function_exists('quizDb') || !($qdb = quizDb()) || !($db = whDb())) return [];
    if (function_exists('qzGroupSync')) qzGroupSync();
    $done = [];
    $st = $db->prepare('SELECT chat FROM wh_posts WHERE day = :d');
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    $res = $st->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) $done[(int)$row['chat']] = 1;
    $skip = trim((string)whVal('report_chat', ''));
    $out = [];
    $res = $qdb->query('SELECT chat FROM quiz_groups WHERE dead_at = 0');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $c = (int)$row['chat'];
        if (isset($done[$c]) || (string)$c === $skip) continue;
        $out[] = $c;
        if (count($out) >= $limit) break;
    }
    return $out;
}

function whGroupCount() {
    if (!function_exists('quizDb') || !($qdb = quizDb())) return 0;
    $st = $qdb->prepare('SELECT COUNT(*) c FROM quiz_groups WHERE dead_at = 0 AND chat <> :s');
    $st->bindValue(':s', (int)whVal('report_chat', 0), SQLITE3_INTEGER);
    return (int)($st->execute()->fetchArray(SQLITE3_ASSOC)['c'] ?? 0);
}

function whSentCount($day) {
    $db = whDb();
    if (!$db) return 0;
    $st = $db->prepare("SELECT COUNT(*) c FROM wh_posts WHERE day = :d AND st = 'ok'");
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    return (int)($st->execute()->fetchArray(SQLITE3_ASSOC)['c'] ?? 0);
}

function whFail($chat, $r) {
    $to = (int)($r['parameters']['migrate_to_chat_id'] ?? 0);
    if ($to && function_exists('qzGroupMigrate')) { qzGroupMigrate($chat, $to); return 'moved'; }
    $code = (int)($r['error_code'] ?? 0);
    if ($code === 429) return 'wait';
    if ($code === 403 || ($code === 400 && preg_match('/chat not found|kicked|not a member|deactivated/i', (string)($r['description'] ?? '')))) {
        if (function_exists('qzGroupPut')) qzGroupPut($chat, '', true);
        return 'dead';
    }
    return $code === 400 ? 'error' : 'retry';
}

function whSendGroups($day, $limit) {
    $db = whDb();
    if (!$db) return [0, false];
    $limit = max(1, (int)$limit);
    $chats = whGroups($day, $limit);
    $claim = $db->prepare("INSERT OR IGNORE INTO wh_posts (chat, day, st) VALUES (:c, :d, '')");
    $mark  = $db->prepare('UPDATE wh_posts SET st = :s, msg = :m WHERE chat = :c AND day = :d');
    $drop  = $db->prepare('DELETE FROM wh_posts WHERE chat = :c AND day = :d');
    $n = 0;
    $more = count($chats) >= $limit;
    foreach ($chats as $c) {
        $claim->bindValue(':c', $c, SQLITE3_INTEGER);
        $claim->bindValue(':d', $day, SQLITE3_TEXT);
        $claim->execute();
        $claim->reset();
        if (!$db->changes()) continue;
        $res = whPostTo($c);
        $mid = (int)($res['result']['message_id'] ?? 0);
        $s = $mid ? 'ok' : whFail($c, $res);
        if ($s === 'retry' || $s === 'wait') {
            $drop->bindValue(':c', $c, SQLITE3_INTEGER);
            $drop->bindValue(':d', $day, SQLITE3_TEXT);
            $drop->execute();
            $drop->reset();
            $more = true;
            if ($s === 'wait') break;
            continue;
        }
        $mark->bindValue(':s', $s, SQLITE3_TEXT);
        $mark->bindValue(':m', $mid, SQLITE3_INTEGER);
        $mark->bindValue(':c', $c, SQLITE3_INTEGER);
        $mark->bindValue(':d', $day, SQLITE3_TEXT);
        $mark->execute();
        $mark->reset();
        if ($s === 'ok') $n++;
    }
    return [$n, $more];
}

function whDayDone($day) {
    $db = whDb();
    if (!$db) return;
    $st = $db->prepare('UPDATE wh_days SET sent_at = :s WHERE day = :d');
    $st->bindValue(':s', time(), SQLITE3_INTEGER);
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    $st->execute();
}

function whTick($limit = 20) {
    if (!whOn() || !($db = whDb())) return 0;
    $mark = DATA_DIR . '/.wh_gc';
    if (time() - (@filemtime($mark) ?: 0) > 86400) {
        @touch($mark);
        $cut = gmdate('Y-m-d', whNowLocal() - 30 * 86400);
        foreach (['wh_posts', 'wh_days', 'wh_tix'] as $t) {
            $st = $db->prepare("DELETE FROM {$t} WHERE day < :d");
            $st->bindValue(':d', $cut, SQLITE3_TEXT);
            $st->execute();
        }
        $db->exec('DROP TABLE IF EXISTS wh_cards');
        $db->exec('DROP TABLE IF EXISTS wh_files');
        $old = DATA_DIR . '/wheel_cards';
        if (is_dir($old)) {
            foreach ((array)glob($old . '/*') as $f) @unlink($f);
            @rmdir($old);
        }
    }
    $day = whDay();
    $row = whDayRow($day);
    if (!$row || (int)$row['sent_at'] > 0 || time() < (int)$row['post_at']) return 0;
    [$n, $more] = whSendGroups($day, $limit);
    if (!$more) whDayDone($day);
    return $n;
}

function whPostNow() {
    $db = whDb();
    if (!$db) return [0, 0];
    $day = whDay();
    whDayRow($day);
    $st = $db->prepare('UPDATE wh_days SET post_at = MIN(post_at, :p), sent_at = 0 WHERE day = :d');
    $st->bindValue(':p', time(), SQLITE3_INTEGER);
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    $st->execute();
    [$n, $more] = whSendGroups($day, 25);
    if (!$more) whDayDone($day);
    return [$n, whGroupCount()];
}

function whOpen() {
    if (!whOn()) return false;
    $row = whDayRow(whDay());
    return $row && time() >= (int)$row['post_at'];
}

function whPrizes($all = false) {
    $out = [];
    foreach ((array)whVal('prizes', []) as $p) {
        if (!is_array($p) || (string)($p['id'] ?? '') === '') continue;
        if (!$all && empty($p['on'])) continue;
        $out[] = $p;
    }
    return $out;
}

function whPrize($id) {
    foreach (whPrizes(true) as $p) if ((string)$p['id'] === (string)$id) return $p;
    return null;
}

function whTodayCounts($day = null) {
    $db = whDb();
    if (!$db) return [];
    $st = $db->prepare('SELECT prize, COUNT(*) n FROM wh_spins WHERE day = :d GROUP BY prize');
    $st->bindValue(':d', $day ?? whDay(), SQLITE3_TEXT);
    $res = $st->execute();
    $out = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $out[(string)$r['prize']] = (int)$r['n'];
    return $out;
}

function whSpinOf($uid, $day = null) {
    $db = whDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM wh_spins WHERE uid = :u AND day = :d');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':d', $day ?? whDay(), SQLITE3_TEXT);
    return $st->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
}

function whSpinSet($uid, $day, array $f) {
    $db = whDb();
    if (!$db || !$f) return;
    $sets = [];
    foreach (array_keys($f) as $k) $sets[] = $k . ' = :' . $k;
    $st = $db->prepare('UPDATE wh_spins SET ' . implode(', ', $sets) . ' WHERE uid = :_u AND day = :_d');
    foreach ($f as $k => $v) $st->bindValue(':' . $k, $v, is_int($v) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    $st->bindValue(':_u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':_d', (string)$day, SQLITE3_TEXT);
    $st->execute();
}

function whPublic(array $p) {
    return ['id' => (string)$p['id'], 'label' => (string)($p['label'] ?? ''), 'emoji' => (string)($p['emoji'] ?? '🎁'),
            'color' => preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($p['color'] ?? '')) ? (string)$p['color'] : '#8B5CF6',
            'type' => (string)($p['type'] ?? 'gift')];
}

function whCap() { return max(0, (int)whVal('post_cap', 2)); }

function whTicketOf($uid) {
    $db = whDb();
    if (!$db) return null;
    $st = $db->prepare('SELECT * FROM wh_tix WHERE uid = :u AND day = :d');
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->bindValue(':d', whDay(), SQLITE3_TEXT);
    return $st->execute()->fetchArray(SQLITE3_ASSOC) ?: null;
}

function whTicket($uid, $chat, $msg) {
    $db = whDb();
    if (!$db) return;
    $up = $db->prepare('UPDATE wh_tix SET day = :d, chat = :c, msg = :m, at = :t WHERE uid = :u');
    $ins = $db->prepare('INSERT OR IGNORE INTO wh_tix (uid, day, chat, msg, at) VALUES (:u, :d, :c, :m, :t)');
    foreach ([$up, $ins] as $st) {
        $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $st->bindValue(':d', whDay(), SQLITE3_TEXT);
        $st->bindValue(':c', (int)$chat, SQLITE3_INTEGER);
        $st->bindValue(':m', (int)$msg, SQLITE3_INTEGER);
        $st->bindValue(':t', time(), SQLITE3_INTEGER);
        $st->execute();
    }
}

function whTicketMsg($uid, $emsg) {
    $db = whDb();
    if (!$db) return;
    $st = $db->prepare('UPDATE wh_tix SET emsg = :e WHERE uid = :u');
    $st->bindValue(':e', (int)$emsg, SQLITE3_INTEGER);
    $st->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
    $st->execute();
}

function whPostCount($chat, $msg, $day = null) {
    $db = whDb();
    if (!$db) return 0;
    $st = $db->prepare('SELECT COUNT(*) c FROM wh_spins WHERE day = :d AND chat = :c AND msg = :m');
    $st->bindValue(':d', $day ?? whDay(), SQLITE3_TEXT);
    $st->bindValue(':c', (int)$chat, SQLITE3_INTEGER);
    $st->bindValue(':m', (int)$msg, SQLITE3_INTEGER);
    return (int)($st->execute()->fetchArray(SQLITE3_ASSOC)['c'] ?? 0);
}

function whPostFull($chat, $msg) {
    $cap = whCap();
    return $cap > 0 && whPostCount($chat, $msg) >= $cap;
}

function whPostDrop($chat, $msg) {
    $res = tg(BOT_TOKEN, 'deleteMessage', ['chat_id' => $chat, 'message_id' => (int)$msg]);
    $db = whDb();
    if ($db) {
        $st = $db->prepare("UPDATE wh_posts SET st = 'full' WHERE chat = :c AND msg = :m");
        $st->bindValue(':c', (int)$chat, SQLITE3_INTEGER);
        $st->bindValue(':m', (int)$msg, SQLITE3_INTEGER);
        $st->execute();
    }
    return !empty($res['ok']);
}

function whAfterSpin($uid) {
    $t = whTicketOf($uid);
    if (!$t) return;
    if ((int)$t['emsg'] > 0) {
        tg(BOT_TOKEN, 'deleteMessage', ['chat_id' => (int)$uid, 'message_id' => (int)$t['emsg']]);
        whTicketMsg($uid, 0);
    }
    if ((int)$t['msg'] > 0 && whPostFull((int)$t['chat'], (int)$t['msg'])) whPostDrop((int)$t['chat'], (int)$t['msg']);
}

function whSpin($uid, $name, $uname = '') {
    if (!whOn()) return [false, 'off', whT('app_off'), []];
    if (!whOpen()) return [false, 'wait', whT('app_wait'), []];
    $day = whDay();
    if ($s = whSpinOf($uid, $day)) return [false, 'done', whT('app_done'), ['spin' => whSpinPublic($s)]];
    $tk = whTicketOf($uid);
    if (!$tk) return [false, 'need', whT('app_need'), []];

    $db = whDb();
    if (!$db) return [false, 'err', 'خطای سرور — دوباره امتحان کنید.', []];
    if (!@$db->exec('BEGIN IMMEDIATE')) return [false, 'err', 'خطای سرور — دوباره امتحان کنید.', []];
    try {
        $chk = $db->prepare('SELECT 1 FROM wh_spins WHERE uid = :u AND day = :d');
        $chk->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $chk->bindValue(':d', $day, SQLITE3_TEXT);
        if ($chk->execute()->fetchArray()) { $db->exec('COMMIT'); return [false, 'done', whT('app_done'), ['spin' => whSpinPublic(whSpinOf($uid, $day))]]; }
        $cap = whCap();
        if ($cap > 0 && whPostCount((int)$tk['chat'], (int)$tk['msg'], $day) >= $cap) { $db->exec('COMMIT'); return [false, 'full', whT('app_full'), []]; }
        $cnt = whTodayCounts($day);
        $pool = [];
        $sum = 0;
        foreach (whPrizes() as $p) {
            $w = max(0, (int)($p['weight'] ?? 0));
            $cap = max(0, (int)($p['cap'] ?? 0));
            if ($w <= 0 || ($cap > 0 && ($cnt[(string)$p['id']] ?? 0) >= $cap)) continue;
            $pool[] = [$p, $w];
            $sum += $w;
        }
        if (!$pool) { $db->exec('COMMIT'); return [false, 'empty', whT('app_off'), []]; }
        $roll = random_int(1, $sum);
        $pick = $pool[0][0];
        foreach ($pool as [$p, $w]) { if ($roll <= $w) { $pick = $p; break; } $roll -= $w; }
        $kind = (string)($pick['type'] ?? 'gift');
        $ins = $db->prepare('INSERT INTO wh_spins (uid, day, prize, label, kind, status, name, uname, at, chat, msg) VALUES (:u, :d, :p, :l, :k, :s, :n, :un, :t, :c, :m)');
        $ins->bindValue(':u', (int)$uid, SQLITE3_INTEGER);
        $ins->bindValue(':d', $day, SQLITE3_TEXT);
        $ins->bindValue(':p', (string)$pick['id'], SQLITE3_TEXT);
        $ins->bindValue(':l', (string)($pick['label'] ?? ''), SQLITE3_TEXT);
        $ins->bindValue(':k', $kind, SQLITE3_TEXT);
        $ins->bindValue(':s', $kind === 'none' ? 'none' : 'pending', SQLITE3_TEXT);
        $ins->bindValue(':n', mb_substr((string)$name, 0, 64), SQLITE3_TEXT);
        $ins->bindValue(':un', mb_substr(preg_replace('/[^A-Za-z0-9_]/', '', (string)$uname), 0, 32), SQLITE3_TEXT);
        $ins->bindValue(':t', time(), SQLITE3_INTEGER);
        $ins->bindValue(':c', (int)$tk['chat'], SQLITE3_INTEGER);
        $ins->bindValue(':m', (int)$tk['msg'], SQLITE3_INTEGER);
        $ins->execute();
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        error_log('[wheel] spin: ' . $e->getMessage());
        return [false, 'err', 'خطای سرور — دوباره امتحان کنید.', []];
    }

    $out = ['prize' => whPublic($pick), 'note' => $kind === 'none' ? '' : whT($kind === 'gift' && whMode() === 'support' ? 'app_support' : 'app_sent')];
    if ($kind === 'coupon' && function_exists('cpMakePercent')) {
        $cp = cpMakePercent('WHL', (float)($pick['percent'] ?? 5), (int)whVal('coupon_days', 7));
        if ($cp) {
            whSpinSet($uid, $day, ['code' => $cp['code'], 'status' => 'ok']);
            $out['coupon'] = ['code' => $cp['code'], 'exp' => whDate($cp['expires_at'])];
        } else {
            whSpinSet($uid, $day, ['status' => 'fail', 'err' => 'coupon']);
        }
    }
    return [true, '', '', $out];
}

function whSpinPublic($s) {
    if (!$s) return null;
    $p = whPrize((string)$s['prize']);
    $pub = $p ? whPublic($p) : ['id' => (string)$s['prize'], 'label' => (string)$s['label'], 'emoji' => '🎁', 'color' => '#8B5CF6', 'type' => (string)$s['kind']];
    return ['prize' => $pub, 'code' => (string)($s['kind'] === 'coupon' ? $s['code'] : ''), 'status' => (string)$s['status']];
}

function whDate($ts) {
    return function_exists('pxJalali') ? trim(explode('|', (string)pxJalali((int)$ts))[0]) : date('Y-m-d', (int)$ts);
}

function whGiftMap($fresh = false) {
    $hit = (!$fresh && function_exists('maCacheGet')) ? maCacheGet('wh_gifts', 43200) : null;
    if (is_array($hit) && $hit) return $hit;
    $r = tg(BOT_TOKEN, 'getAvailableGifts', [], 10);
    $map = [];
    foreach ((array)($r['result']['gifts'] ?? []) as $g) {
        $e = preg_replace('/[\x{FE0F}\x{FE0E}]/u', '', (string)($g['sticker']['emoji'] ?? ''));
        $id = (string)($g['id'] ?? '');
        if ($e === '' || $id === '') continue;
        $stars = (int)($g['star_count'] ?? 0);
        $lim = !empty($g['total_count']);
        if (isset($map[$e]) && (!$map[$e]['lim'] && $lim || $map[$e]['stars'] <= $stars && $map[$e]['lim'] === $lim)) continue;
        $map[$e] = ['id' => $id, 'stars' => $stars, 'lim' => $lim];
    }
    if ($map && function_exists('maCachePut')) maCachePut('wh_gifts', $map);
    return $map;
}

function whGiftId(array $p) {
    $id = trim((string)($p['gift_id'] ?? ''));
    if ($id !== '') return $id;
    $e = preg_replace('/[\x{FE0F}\x{FE0E}]/u', '', (string)($p['emoji'] ?? ''));
    return (string)(whGiftMap()[$e]['id'] ?? '');
}

function whMode() { return (string)whVal('gift_mode', 'support') === 'auto' ? 'auto' : 'support'; }

function whDeliver($uid, $day) {
    $s = whSpinOf($uid, $day);
    if (!$s) return false;
    $p = whPrize((string)$s['prize']);
    $kind = (string)$s['kind'];
    $label = (string)($p['label'] ?? $s['label']);
    if ($kind === 'none') { sendMsg(BOT_TOKEN, $uid, whT('win_none')); return true; }
    if ($kind === 'coupon') {
        if ((string)$s['code'] !== '') {
            $cp = function_exists('cpGet') ? cpGet((string)$s['code']) : null;
            sendMsg(BOT_TOKEN, $uid, whT('win_coupon', ['prize' => h($label), 'code' => h((string)$s['code']),
                'exp' => $cp ? whDate((int)$cp['expires_at']) : '']));
        }
        whReport($uid, $day);
        return (string)$s['code'] !== '';
    }
    if ($kind === 'diamond') {
        $amt = max(0, (float)($p['amount'] ?? 0));
        if ($amt > 0 && function_exists('gmAdd')) gmAdd((int)$uid, $amt, (string)$s['name']);
        whSpinSet($uid, $day, ['status' => 'ok']);
        sendMsg(BOT_TOKEN, $uid, whT('win_diamond', ['prize' => h($label), 'amount' => fmtNum($amt)]));
        whReport($uid, $day);
        return true;
    }
    if (whMode() === 'support') {
        sendMsg(BOT_TOKEN, $uid, whT('win_gift_support', ['prize' => h($label)]));
        whReport($uid, $day);
        return true;
    }
    $ok = whSendGift($uid, $day, $p ?: ['emoji' => '', 'label' => $label], true);
    whReport($uid, $day);
    return $ok;
}

function whSendGift($uid, $day, array $p, $notifyUser) {
    $label = (string)($p['label'] ?? '');
    $gid = whGiftId($p);
    $res = $gid !== '' ? tg(BOT_TOKEN, 'sendGift', ['user_id' => (int)$uid, 'gift_id' => $gid,
        'text' => mb_substr(whT('gift_note'), 0, 1000), 'text_parse_mode' => 'HTML'], 15) : ['ok' => false, 'description' => 'gift_id پیدا نشد'];
    if (!empty($res['ok'])) {
        whSpinSet($uid, $day, ['status' => 'ok', 'err' => '']);
        if ($notifyUser) sendMsg(BOT_TOKEN, $uid, whT('win_gift', ['prize' => h($label)]));
        return true;
    }
    whSpinSet($uid, $day, ['status' => 'pending', 'err' => mb_substr((string)($res['description'] ?? 'خطا'), 0, 200)]);
    if ($notifyUser) sendMsg(BOT_TOKEN, $uid, whT('win_gift_wait', ['prize' => h($label)]));
    return false;
}

function whReportTarget() {
    $chat = trim((string)whVal('report_chat', ''));
    if (!preg_match('/^(-\d{5,20}|@[A-Za-z0-9_]{4,32})$/', $chat)) return [null, 0];
    return [$chat, max(0, (int)whVal('report_topic', 0))];
}

function whStatusText(array $s) {
    $st = (string)$s['status'];
    if ($st === 'given') return whT('st_given', ['by' => h((string)$s['given_by'])]);
    if ($st === 'ok') return whT('st_auto');
    if ($st === 'fail' || (string)$s['err'] !== '') return whT('st_fail', ['err' => h((string)$s['err'])]);
    return whT('st_wait');
}

function whReportText(array $s) {
    $uid = (int)$s['uid'];
    $p = whPrize((string)$s['prize']);
    $name = trim((string)$s['name']) !== '' ? (string)$s['name'] : (string)$uid;
    $un = trim((string)$s['uname']);
    $tpl = str_replace('{code_line}', (string)whVal('texts.report_code_line', "\nکد: <code>{code}</code>"), whT('report'));
    return tplFill($tpl, [
        'user'      => '<a href="tg://user?id=' . $uid . '">' . h($name) . '</a>',
        'uid'       => $uid,
        'username'  => $un !== '' ? '@' . h($un) : '—',
        'prize'     => h(trim(($p['emoji'] ?? '') . ' ' . ($p['label'] ?? $s['label']))),
        'code'      => (string)$s['code'] !== '' ? h((string)$s['code']) : '',
        'time'      => h(whDate((int)$s['at']) . ' ' . gmdate('H:i', (int)$s['at'] + (int)whVal('tz_offset', 12600))),
        'status'    => whStatusText($s),
    ]);
}

function whReportKb(array $s) {
    if ((string)$s['kind'] !== 'gift' || in_array((string)$s['status'], ['ok', 'given'], true)) return null;
    $k = (int)$s['uid'] . '_' . (string)$s['day'];
    $row = [whBtn('btn_given', ['callback_data' => 'whg_' . $k])];
    if (whMode() === 'auto' && (string)$s['err'] !== '') $row[] = btnCb('🔁 ارسالِ دوباره', 'whr_' . $k, 'confirm');
    return inlineKb([$row]);
}

function whReport($uid, $day) {
    $s = whSpinOf($uid, $day);
    if (!$s || (string)$s['kind'] === 'none') return false;
    $text = whReportText($s);
    $kb = whReportKb($s);
    [$chat, $topic] = whReportTarget();
    if ($chat !== null) {
        $res = sendMsg(BOT_TOKEN, $chat, $text, $kb, $topic > 0 ? ['message_thread_id' => $topic] : []);
        if (empty($res['ok']) && $topic > 0 && tgThreadGone($res)) {
            $res = sendMsg(BOT_TOKEN, $chat, $text, $kb);
            if (!empty($res['ok'])) whSet(function (&$c) { $c['report_topic'] = 0; });
        }
        if (!empty($res['ok'])) return true;
        if (function_exists('adminAlertOnce'))
            adminAlertOnce('wh_report_fail', "🎡 <b>لیستِ برنده‌های گردونه به گروه فرستاده نشد</b>\n\n" . tgWhy($res) .
                "\n\nتا درست شود، گیفت‌هایی که باید دستی تحویل شوند برای مدیرها فرستاده می‌شوند.", 21600);
    }
    if ($kb && function_exists('notifyAdmins')) notifyAdmins($text, $kb);
    return false;
}

function whStaff($chatId, $uid) {
    if (isAdmin($uid)) return true;
    [$chat] = whReportTarget();
    if ($chat === null || (string)tgChatId($chat) !== (string)$chatId) return false;
    if (function_exists('maSupStaff')) return maSupStaff($chatId, $uid);
    $r = tg(BOT_TOKEN, 'getChatMember', ['chat_id' => $chatId, 'user_id' => (int)$uid], 8);
    return in_array((string)($r['result']['status'] ?? ''), ['creator', 'administrator'], true);
}

function whMarkGiven($wid, $day, $by) {
    $db = whDb();
    if (!$db) return false;
    $st = $db->prepare("UPDATE wh_spins SET status = 'given', given_by = :b, err = '' WHERE uid = :u AND day = :d AND kind = 'gift' AND status NOT IN ('given', 'ok')");
    $st->bindValue(':b', mb_substr((string)$by, 0, 80), SQLITE3_TEXT);
    $st->bindValue(':u', (int)$wid, SQLITE3_INTEGER);
    $st->bindValue(':d', (string)$day, SQLITE3_TEXT);
    $st->execute();
    if ($db->changes() < 1) return false;
    $s = whSpinOf($wid, $day);
    $p = whPrize((string)$s['prize']);
    sendMsg(BOT_TOKEN, (int)$wid, whT('win_given', ['prize' => h((string)($p['label'] ?? $s['label']))]));
    return true;
}

function whByName($from, $uid) {
    $by = trim((string)($from['first_name'] ?? '')) ?: (string)$uid;
    return !empty($from['username']) ? $by . ' (@' . $from['username'] . ')' : $by;
}

function whRetry($wid, $day) {
    $s = whSpinOf((int)$wid, $day);
    if (!$s || $s['kind'] !== 'gift' || in_array($s['status'], ['ok', 'given'], true)) return null;
    $p = whPrize((string)$s['prize']) ?: ['emoji' => '', 'label' => (string)$s['label']];
    $ok = whSendGift((int)$wid, $day, $p, false);
    if ($ok) sendMsg(BOT_TOKEN, (int)$wid, whT('win_gift', ['prize' => h((string)$s['label'])]));
    return $ok;
}

function whCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if ($data === 'whgo') {
        if (!whOpen()) { answerCb(BOT_TOKEN, $cbId, whT('closed'), true); return true; }
        if (whSpinOf($uid)) { answerCb(BOT_TOKEN, $cbId, whT('pop_done'), true); return true; }
        if (whPostFull($chatId, $msgId)) { answerCb(BOT_TOKEN, $cbId, whT('full'), true); whPostDrop($chatId, $msgId); return true; }
        whTicket($uid, $chatId, $msgId);
        foreach (whGoUrls() as $u)
            if (!empty(tg(BOT_TOKEN, 'answerCallbackQuery', ['callback_query_id' => $cbId, 'url' => $u])['ok'])) return true;
        answerCb(BOT_TOKEN, $cbId, whT('closed'), true);
        return true;
    }
    if (preg_match('/^whr_(\d+)_(\d{4}-\d{2}-\d{2})$/', (string)$data, $m)) {
        if (!isAdmin($uid)) { answerCb(BOT_TOKEN, $cbId, '🔒 فقط مدیرِ ربات (ارسالِ دوباره از استارزِ ربات خرج می‌کند).', true); return true; }
        $ok = whRetry((int)$m[1], $m[2]);
        answerCb(BOT_TOKEN, $cbId, $ok === null ? 'این گیفت قبلا تحویل شده یا پیدا نشد.' : ($ok ? '✅ گیفت فرستاده شد' : '⚠️ باز هم فرستاده نشد'), $ok !== true);
        if ($s = whSpinOf((int)$m[1], $m[2])) editMsg(BOT_TOKEN, $chatId, $msgId, whReportText($s), whReportKb($s));
        return true;
    }
    if (!preg_match('/^whg_(\d+)_(\d{4}-\d{2}-\d{2})$/', (string)$data, $m)) return false;
    if (!whStaff($chatId, $uid)) { answerCb(BOT_TOKEN, $cbId, whT('pop_staff'), true); return true; }
    $s = whSpinOf((int)$m[1], $m[2]);
    if (!$s || (string)$s['kind'] !== 'gift') { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد.', true); return true; }
    $fresh = whMarkGiven((int)$m[1], $m[2], whByName($from, $uid));
    $s = whSpinOf((int)$m[1], $m[2]);
    editMsg(BOT_TOKEN, $chatId, $msgId, whReportText($s), whReportKb($s));
    answerCb(BOT_TOKEN, $cbId, whT($fresh ? 'pop_given' : 'pop_given_old'));
    return true;
}

function whPostHere($chat) {
    $db = whDb();
    if (!$db) return ['error', []];
    if (function_exists('qzGroupPut')) qzGroupPut((int)$chat);
    $day = whDay();
    whDayRow($day);
    $st = $db->prepare('UPDATE wh_days SET post_at = MIN(post_at, :p) WHERE day = :d');
    $st->bindValue(':p', time(), SQLITE3_INTEGER);
    $st->bindValue(':d', $day, SQLITE3_TEXT);
    $st->execute();
    $res = whPostTo((int)$chat);
    $mid = (int)($res['result']['message_id'] ?? 0);
    if (!$mid) return [whFail((int)$chat, $res), $res];
    $mk = $db->prepare("INSERT OR REPLACE INTO wh_posts (chat, day, st, msg) VALUES (:c, :d, 'ok', :m)");
    $mk->bindValue(':c', (int)$chat, SQLITE3_INTEGER);
    $mk->bindValue(':d', $day, SQLITE3_TEXT);
    $mk->bindValue(':m', $mid, SQLITE3_INTEGER);
    $mk->execute();
    return ['ok', $res];
}

function whGroupList() {
    if (!function_exists('quizDb') || !($qdb = quizDb())) return [];
    if (function_exists('qzGroupSync')) qzGroupSync();
    $sent = [];
    if ($db = whDb()) {
        $st = $db->prepare('SELECT chat, st FROM wh_posts WHERE day = :d');
        $st->bindValue(':d', whDay(), SQLITE3_TEXT);
        $res = $st->execute();
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) $sent[(int)$r['chat']] = (string)$r['st'];
    }
    $out = [];
    $res = $qdb->query('SELECT chat, title FROM quiz_groups WHERE dead_at = 0 ORDER BY added DESC LIMIT 200');
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) $out[] = ['chat' => (int)$r['chat'], 'title' => (string)$r['title'], 'st' => $sent[(int)$r['chat']] ?? ''];
    return $out;
}

function whAdminGroups($chatId, $msgId, $page = 0) {
    $all = whGroupList();
    $page = max(0, min((int)$page, max(0, intdiv(count($all) - 1, 8))));
    $t = "🧪 <b>تستِ پستِ گردونه در یک گروه</b>\n\n✅ امروز فرستاده شده · 🏁 ظرفیت پر\n";
    if (!$all) $t .= "\nگروهی در فهرست نیست";
    $rows = [];
    foreach (array_slice($all, $page * 8, 8) as $g) {
        $mark = ['ok' => '✅ ', 'full' => '🏁 '][$g['st']] ?? '👥 ';
        $rows[] = [btnCb($mark . mb_substr($g['title'] !== '' ? $g['title'] : (string)$g['chat'], 0, 32), 'whp_to_' . $g['chat'], 'admin')];
    }
    $nav = [];
    if ($page > 0) $nav[] = btnCb('◀️ قبلی', 'whp_tg_' . ($page - 1), 'nav');
    if (count($all) > ($page + 1) * 8) $nav[] = btnCb('بعدی ▶️', 'whp_tg_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb('✍️ گروهی که در فهرست نیست (لینک یا آیدی)', 'whp_tgx', 'admin')];
    $rows[] = [btnCb(UT('back'), 'whp_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function whTestText($chat, array $r) {
    [$s, $res] = $r;
    if ($s === 'ok') return '✅ پستِ گردونه در گروهِ <code>' . h((string)$chat) . '</code> فرستاده شد و گردونه‌ی امروز باز است.';
    return '🔴 پستِ گردونه در گروهِ <code>' . h((string)$chat) . '</code> نرفت: ' . tgWhy((array)$res);
}

function whGroupCmd($msg, $uid, $chatId) {
    $text = trim((string)($msg['text'] ?? ''));
    if (preg_match('~^/wheel(?:@\w+)?$~i', $text)) {
        if (!isAdmin($uid)) return true;
        $r = whPostHere($chatId);
        if ($r[0] !== 'ok') sendMsg(BOT_TOKEN, $uid, whTestText($chatId, $r));
        return true;
    }
    if (!preg_match('~^/wheelwinners(?:@\w+)?$~i', $text)) return false;
    if (!isAdmin($uid)) return true;
    $topic = !empty($msg['is_topic_message']) ? (int)($msg['message_thread_id'] ?? 0) : 0;
    whSet(function (&$c) use ($chatId, $topic) { $c['report_chat'] = (string)$chatId; $c['report_topic'] = $topic; });
    sendMsg(BOT_TOKEN, $chatId, '✅ لیستِ برنده‌های گردونه از این به بعد همین‌جا فرستاده می‌شود.', null,
        array_merge(['reply_to_message_id' => (int)($msg['message_id'] ?? 0)], $topic > 0 ? ['message_thread_id' => $topic] : []));
    return true;
}

function whState($uid) {
    $s = whSpinOf($uid);
    $t = whTicketOf($uid);
    return [
        'on'     => whOn(),
        'open'   => whOpen(),
        'spin'   => whSpinPublic($s),
        'ticket' => $t !== null,
        'full'   => $t !== null && whPostFull((int)$t['chat'], (int)$t['msg']),
        'prizes' => array_map('whPublic', whPrizes()),
    ];
}

function whApiAction($action, array $body, $uid, $uname, $fname) {
    if ($action === 'wh_state') maApiOut(['ok' => true] + whState($uid));
    if ($action === 'wh_spin') {
        if (!maRateOk('whspin', $uid, 6, 60))
            maApiOut(['ok' => false, 'error' => 'rate_limited', 'message' => 'کمی صبر کنید.'], 429);
        [$ok, $err, $msg, $data] = whSpin($uid, $fname !== '' ? $fname : $uname, $uname);
        if (!$ok) maApiOut(['ok' => false, 'error' => $err, 'message' => $msg] + $data, 409);
        $day = whDay();
        $dm = ($data['prize']['type'] ?? '') === 'none'
            || !empty(tg(BOT_TOKEN, 'sendChatAction', ['chat_id' => (int)$uid, 'action' => 'typing'], 6)['ok']);
        maApiOut(['ok' => true, 'dm' => $dm] + $data, 200, function () use ($uid, $day) { whAfterSpin($uid); whDeliver($uid, $day); });
    }
    maApiOut(['ok' => false, 'error' => 'bad_action'], 400);
}

function whAppTexts() {
    $out = [];
    foreach (array_keys(whDefaults()['texts']) as $k)
        if (str_starts_with($k, 'app_')) $out[substr($k, 4)] = trim(html_entity_decode(strip_tags(whT($k)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    return $out;
}

function whBoot() {
    return [
        'title'   => (string)whVal('title', 'گردونه شانس'),
        'bot'     => function_exists('botUsername') ? (string)botUsername() : '',
        't'       => whAppTexts(),
    ];
}

function whServe() {
    if (!whOn()) { http_response_code(200); header('Content-Type: text/html; charset=utf-8'); echo maClosedPage(); exit; }
    $html = whView(whBoot());
    maSecurityHeaders();
    $tag = substr(hash('sha256', $html), 0, 32);
    header('ETag: W/"' . $tag . '"');
    if (strpos((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), $tag) !== false) { http_response_code(304); exit; }
    maEmit($html);
    exit;
}

function whMyText(array $s) {
    $p = whPrize((string)$s['prize']);
    $label = h((string)($p['label'] ?? $s['label']));
    $kind = (string)$s['kind'];
    if ($kind === 'coupon') {
        if ((string)$s['code'] === '') return whT('win_gift_support', ['prize' => $label]);
        $cp = function_exists('cpGet') ? cpGet((string)$s['code']) : null;
        return whT('win_coupon', ['prize' => $label, 'code' => h((string)$s['code']), 'exp' => $cp ? whDate((int)$cp['expires_at']) : '']);
    }
    if ($kind === 'diamond') return whT('win_diamond', ['prize' => $label, 'amount' => fmtNum(max(0, (float)($p['amount'] ?? 0)))]);
    if ($kind === 'gift') {
        $st = (string)$s['status'];
        if ($st === 'given') return whT('win_given', ['prize' => $label]);
        if ($st === 'ok') return whT('win_gift', ['prize' => $label]);
        return whT(whMode() === 'support' ? 'win_gift_support' : 'win_gift_wait', ['prize' => $label]);
    }
    return whT('win_none');
}

function whStartEntry($uid, $chatId) {
    if (whOn() && ($s = whSpinOf($uid))) { sendMsg(BOT_TOKEN, $chatId, whMyText($s)); return; }
    $url = whUrl();
    if (!whOn() || $url === '' || !whOpen()) { sendMsg(BOT_TOKEN, $chatId, whT('closed')); return; }
    $t = whTicketOf($uid);
    if (!$t) { sendMsg(BOT_TOKEN, $chatId, whT('need_post')); return; }
    if (whPostFull((int)$t['chat'], (int)$t['msg'])) { sendMsg(BOT_TOKEN, $chatId, whT('full')); return; }
    if ((int)$t['emsg'] > 0) delMsg(BOT_TOKEN, $chatId, (int)$t['emsg']);
    $res = sendMsg(BOT_TOKEN, $chatId, whT('entry'), inlineKb([[whBtn('btn_open', ['web_app' => ['url' => $url]])]]));
    whTicketMsg($uid, (int)($res['result']['message_id'] ?? 0));
}


function whLabels() {
    return [
        'post' => '📣 پستِ روزانه در گروه‌ها', 'entry' => '🚪 پیامِ ورود در پیویِ ربات', 'closed' => '⏳ گردونه هنوز باز نشده',
        'need_post' => '🚪 بدونِ زدنِ دکمه‌ی پستِ گروه آمده', 'full' => '👥 ظرفیتِ پست پر شد',
        'win_gift' => '🎁 بردِ گیفت — خودکار فرستاده شد', 'win_gift_wait' => '🎁 بردِ گیفت — ارسالِ خودکار نشد',
        'win_gift_support' => '🎁 بردِ گیفت — پشتیبانی می‌فرستد', 'win_given' => '🤝 پیام به برنده بعد از تحویل',
        'win_coupon' => '🏷 بردِ کد تخفیف', 'win_diamond' => '💎 بردِ الماسِ بازی', 'win_none' => '😶 پوچ',
        'gift_note' => '📝 متنی که روی خودِ گیفت می‌نشیند',
        'report' => '📋 لیستِ برنده‌ها: پیامِ هر برنده',
        'st_wait' => '📋 وضعیت: منتظرِ تحویل', 'st_given' => '📋 وضعیت: تحویل شد', 'st_auto' => '📋 وضعیت: خودکار تحویل شد',
        'st_fail' => '📋 وضعیت: ارسالِ خودکار نشد',
        'pop_given' => 'پاپ‌آپ: تحویل ثبت شد', 'pop_given_old' => 'پاپ‌آپ: قبلا تحویل شده', 'pop_staff' => 'پاپ‌آپ: فقط ادمین‌های گروه',
        'pop_done' => 'پاپ‌آپ: امروز چرخانده',
        'btn_post' => 'دکمه: زیرِ پستِ گروه', 'btn_open' => 'دکمه: باز کردنِ مینی‌اپ', 'btn_given' => 'دکمه: جایزه تحویل شد',
        'app_spin' => 'مینی‌اپ: دکمه‌ی چرخاندن', 'app_ready' => 'مینی‌اپ: شانسِ امروز آماده است',
        'app_done' => 'مینی‌اپ: امروز چرخانده', 'app_wait' => 'مینی‌اپ: هنوز باز نشده', 'app_off' => 'مینی‌اپ: خاموش',
        'app_need' => 'مینی‌اپ: بدونِ پستِ گروه آمده', 'app_full' => 'مینی‌اپ: ظرفیتِ پست پر شد', 'app_prizes' => 'مینی‌اپ: تیترِ جایزه‌ها',
        'app_win' => 'مینی‌اپ: تیترِ برد', 'app_lose' => 'مینی‌اپ: تیترِ پوچ', 'app_sent' => 'مینی‌اپ: جزئیات در ربات',
        'app_support' => 'مینی‌اپ: گیفت را پشتیبانی می‌فرستد',
        'app_copy' => 'مینی‌اپ: دکمه‌ی کپی', 'app_nodm' => 'مینی‌اپ: پیوی بسته است، کد را کپی کند', 'app_close' => 'مینی‌اپ: دکمه‌ی بستن', 'app_rules' => 'مینی‌اپ: قانونِ پایینِ صفحه',
    ];
}
function whLabel($k) { return whLabels()[$k] ?? $k; }

function whTextsCfg() {
    return [
        'title'  => 'متن‌های گردونه شانس',
        'keys'   => array_keys(whDefaults()['texts']),
        'meta'   => [
            'm' => '💬 پیام‌های ربات، گروه و لیستِ برنده‌ها',
            'a' => '📱 متن‌های داخلِ مینی‌اپ',
            'b' => '🔘 برچسبِ دکمه‌ها',
        ],
        'secOf'  => fn($k) => str_starts_with($k, 'btn_') ? 'b' : (str_starts_with($k, 'app_') ? 'a' : 'm'),
        'label'  => 'whLabel',
        'value'  => function ($k) { return whT($k); },
        'cb'     => 'whp_t_',
        'edit'   => 'whp_ts_',
        'back'   => 'whp_home',
    ];
}

function whTypes() { return ['gift' => '🎁 گیفتِ استارزی', 'coupon' => '🏷 کد تخفیف', 'diamond' => '💎 الماسِ بازی', 'none' => '😶 پوچ']; }

function whAdminHome($chatId, $msgId = null) {
    $c   = whCfg();
    $day = whDay();
    $row = whDayRow($day, false);
    $cnt = whTodayCounts($day);
    $off = (int)$c['tz_offset'];
    [$rc, $rt] = whReportTarget();
    $t  = "🎡 <b>گردونه شانس</b>\n\n";
    $t .= 'وضعیت: ' . (whOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '👥 گروه‌ها: <b>' . whGroupCount() . "</b>\n";
    $t .= sprintf("🕘 بازه‌ی پست: <b>%02d:00</b> تا <b>%02d:00</b> · تصادفی\n", (int)$c['from_hour'], (int)$c['to_hour']);
    if ($row) $t .= '📅 امروز: ' . (time() >= (int)$row['post_at']
        ? '✅ باز است — پست در <b>' . whSentCount($day) . '</b> گروه فرستاده شد'
        : '⏳ ساعتِ ' . gmdate('H:i', (int)$row['post_at'] + $off) . ' باز می‌شود') . "\n";
    $t .= '📋 لیستِ برنده‌ها: ' . ($rc !== null ? '<code>' . h($rc) . '</code>' . ($rt > 0 ? ' · تاپیکِ <code>' . $rt . '</code>' : '') : '<b>تنظیم نشده</b>') . "\n";
    $t .= '🎁 تحویلِ گیفت: ' . (whMode() === 'support' ? '🤝 پشتیبانی دستی می‌فرستد' : '⚡️ خودکار با استارزِ ربات') . "\n";
    $t .= '🏷 اعتبارِ کدهای تخفیف: <b>' . (int)$c['coupon_days'] . "</b> روز\n";
    $t .= '👥 ظرفیتِ هر پست: ' . (whCap() > 0 ? '<b>' . whCap() . '</b> نفر' : '<b>بی‌سقف</b>') . "\n";
    $t .= '🚪 دکمه‌ی پست: ' . (trim((string)$c['app_link']) !== '' ? 'لینکِ مستقیمِ اختصاصی' : ((string)$c['entry'] === 'bot' ? 'پیویِ ربات' : 'مینی‌اپِ گردونه')) . "\n";
    $t .= '🖼 عکسِ پست: ' . (trim((string)$c['photo']) !== '' ? '✅ دارد' : '— فقط متن') . "\n\n";
    $t .= '🎯 چرخش‌های امروز: <b>' . array_sum($cnt) . "</b>\n";
    $rows = [
        [btnCb(whOn() ? '✅ روشن' : '❌ خاموش', 'whp_x', 'info'), btnCb('📣 بازکردن و پست همین الان', 'whp_post', 'confirm')],
        [btnCb('🧪 تستِ پست در یک گروه', 'whp_tg', 'confirm')],
        [btnCb('📋 گروهِ لیستِ برنده‌ها', 'whp_rep', 'admin'), btnCb('🕘 بازه‌ی ساعت', 'whp_hours', 'admin')],
        [btnCb(whMode() === 'support' ? '🎁 تحویلِ گیفت: پشتیبانی' : '🎁 تحویلِ گیفت: خودکار', 'whp_gm', 'info')],
        [btnCb('🎁 جایزه‌ها و شانس‌ها', 'whp_pz', 'admin')],
        [btnCb('✏️ متن‌ها و دکمه‌ها', 'whp_t_home', 'admin'), btnCb('🎨 رنگِ دکمه‌ها', 'whp_colors', 'admin')],
        [btnCb('👥 ظرفیتِ هر پست', 'whp_cap', 'admin'), btnCb('🏷 اعتبارِ کد تخفیف', 'whp_cdays', 'admin')],
        [btnCb('🖼 عکسِ پست', 'whp_photo', 'admin')],
        [btnCb((string)$c['entry'] === 'bot' ? '🚪 ورود: از پیویِ ربات' : '🚪 ورود: مستقیم مینی‌اپ', 'whp_em', 'info'), btnCb('🔗 لینکِ مستقیمِ اختصاصی', 'whp_link', 'admin')],
        [btnCb('📊 برنده‌های امروز', 'whp_today', 'info'), btnCb('👁 پیش‌نمایشِ پست', 'whp_prev', 'info')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function whAdminPrizes($chatId, $msgId) {
    $cnt = whTodayCounts();
    $all = whPrizes(true);
    $sum = 0;
    foreach ($all as $p) if (!empty($p['on'])) $sum += max(0, (int)($p['weight'] ?? 0));
    $t = "🎁 <b>جایزه‌های گردونه</b>\n\n";
    $rows = [];
    foreach ($all as $p) {
        $w = max(0, (int)($p['weight'] ?? 0));
        $pc = $sum > 0 && !empty($p['on']) ? round($w * 100 / $sum, $w * 1000 < $sum ? 2 : 1) : 0;
        $t .= (!empty($p['on']) ? '' : '⏸ ') . h(($p['emoji'] ?? '') . ' ' . ($p['label'] ?? '')) . ' — ' . $pc . '٪' .
              ' · سقف ' . ((int)($p['cap'] ?? 0) ?: '∞') . ' · امروز ' . ($cnt[(string)$p['id']] ?? 0) . "\n";
        $rows[] = [btnCb(trim(($p['emoji'] ?? '') . ' ' . ($p['label'] ?? '')), 'whp_pz_' . $p['id'], 'admin')];
    }
    $rows[] = [btnCb('➕ جایزه‌ی تازه', 'whp_pzadd', 'confirm'), btnCb('🎁 فهرستِ گیفت‌های تلگرام', 'whp_gifts', 'info')];
    $rows[] = [btnCb(UT('back'), 'whp_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function whAdminPrize($chatId, $msgId, $id) {
    $p = whPrize($id);
    if (!$p) { whAdminPrizes($chatId, $msgId); return; }
    $type = (string)($p['type'] ?? 'gift');
    $t  = '🎁 <b>' . h(($p['emoji'] ?? '') . ' ' . ($p['label'] ?? '')) . "</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($p['on']) ? '✅ داخلِ گردونه' : '⏸ بیرون') . "\n";
    $t .= 'نوع: <b>' . (whTypes()[$type] ?? $type) . "</b>\n";
    $sum = 0;
    foreach (whPrizes() as $q) $sum += max(0, (int)($q['weight'] ?? 0));
    $w = max(0, (int)($p['weight'] ?? 0));
    $t .= '⚖️ شانس: <b>' . $w . '</b>' . ($sum > 0 && !empty($p['on']) ? ' (' . round($w * 100 / $sum, $w * 1000 < $sum ? 2 : 1) . '٪)' : '') . "\n";
    $t .= '🔢 سقفِ روزانه: <b>' . ((int)($p['cap'] ?? 0) ?: 'بی‌سقف') . "</b>\n";
    $t .= '🎨 رنگِ برش: <code>' . h((string)($p['color'] ?? '')) . "</code>\n";
    if ($type === 'gift') {
        $gid = whGiftId($p);
        $t .= '🆔 گیفت: ' . ($gid !== '' ? '<code>' . h($gid) . '</code>' . (trim((string)($p['gift_id'] ?? '')) === '' ? ' (خودکار از روی ایموجی)' : '') : '<b>پیدا نشد</b> — شناسه را دستی بگذارید') . "\n";
    }
    if ($type === 'coupon')  $t .= '٪ درصدِ تخفیف: <b>' . (float)($p['percent'] ?? 0) . "</b>\n";
    if ($type === 'diamond') $t .= '💎 تعدادِ الماس: <b>' . fmtNum((float)($p['amount'] ?? 0)) . "</b>\n";
    $rows = [
        [btnCb(!empty($p['on']) ? '⏸ بیرون ببر' : '✅ داخلِ گردونه', 'whp_pzx_' . $id, 'info'), btnCb('🔁 نوع', 'whp_pzk_' . $id, 'admin')],
        [btnCb('⚖️ شانس', 'whp_pzw_' . $id, 'admin'), btnCb('🔢 سقفِ روزانه', 'whp_pzc_' . $id, 'admin')],
        [btnCb('✏️ نام', 'whp_pzl_' . $id, 'admin'), btnCb('😀 ایموجی', 'whp_pze_' . $id, 'admin'), btnCb('🎨 رنگ', 'whp_pzh_' . $id, 'admin')],
    ];
    if ($type === 'gift')    $rows[] = [btnCb('🆔 شناسه‌ی گیفت', 'whp_pzg_' . $id, 'admin')];
    if ($type === 'coupon')  $rows[] = [btnCb('٪ درصدِ تخفیف', 'whp_pzp_' . $id, 'admin')];
    if ($type === 'diamond') $rows[] = [btnCb('💎 تعدادِ الماس', 'whp_pza_' . $id, 'admin')];
    $rows[] = [btnCb('🗑 حذفِ جایزه', 'whp_pzd_' . $id, 'reject')];
    $rows[] = [btnCb(UT('back'), 'whp_pz', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function whPrizeSet($id, callable $fn) {
    whSet(function (&$c) use ($id, $fn) {
        foreach ($c['prizes'] as $i => $p) if (is_array($p) && (string)($p['id'] ?? '') === (string)$id) { $fn($c['prizes'][$i]); return; }
    });
}

function whAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های گردونه</b>\n\n";
    $rows = [];
    foreach (whBtnKeys() as $k) {
        $cur = (string)whVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(whLabel($k)) . ': <b>' . h(styleMap()[$cur] ?? $cur) . "</b>\n";
        $line = [];
        foreach (styleMap() as $sk => $sl) $line[] = btnCb(($sk === $cur ? '✅' : '') . mb_substr($sl, 0, 2), 'whp_cv_' . $k . '_' . $sk, 'info');
        $rows[] = [btnCb('— ' . whLabel($k) . ' —', 'whp_nop', 'nav')];
        $rows[] = $line;
    }
    $rows[] = [btnCb(UT('back'), 'whp_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function whAdminToday($chatId, $msgId) {
    $db = whDb();
    $t = "📊 <b>برنده‌های امروزِ گردونه</b>\n\n✅ خودکار · 🤝 تحویلِ پشتیبانی · ⏳ منتظرِ تحویل\n\n";
    $rows = [];
    if ($db) {
        $st = $db->prepare("SELECT * FROM wh_spins WHERE day = :d AND kind <> 'none' ORDER BY at DESC LIMIT 40");
        $st->bindValue(':d', whDay(), SQLITE3_TEXT);
        $res = $st->execute();
        while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
            $t .= ['ok' => '✅', 'given' => '🤝', 'pending' => '⏳', 'fail' => '⚠️'][$r['status']] ?? '•';
            $t .= ' <a href="tg://user?id=' . (int)$r['uid'] . '">' . h((string)$r['name'] ?: (string)$r['uid']) . '</a> <code>' . (int)$r['uid'] . '</code> — ' . h((string)$r['label']);
            if ($r['code'] !== '') $t .= ' <code>' . h((string)$r['code']) . '</code>';
            $t .= "\n";
            if ($r['status'] === 'pending' && $r['kind'] === 'gift' && count($rows) < 8) {
                $who = mb_substr((string)$r['name'] ?: (string)$r['uid'], 0, 20) . ' — ' . $r['label'];
                $k = (int)$r['uid'] . '_' . $r['day'];
                $rows[] = whMode() === 'auto' && (string)$r['err'] !== ''
                    ? [btnCb('🔁 ' . $who, 'whp_rs_' . $k, 'confirm'), btnCb('🤝 تحویل شد', 'whp_gv_' . $k, 'confirm')]
                    : [btnCb('🤝 تحویل شد — ' . $who, 'whp_gv_' . $k, 'confirm')];
            }
        }
    }
    $rows[] = [btnCb(UT('back'), 'whp_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3900), inlineKb($rows));
}

function whAsk($chatId, $state, array $data, $text, $back) {
    setState(admStateUid($chatId), $state, $data);
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[btnCb('انصراف', $back, 'cancel')]]));
}

function whAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'whp_')) return false;
    $d = substr((string)$data, 4);
    if ($d === 'nop') { answerCb(BOT_TOKEN, $cbId); return true; }
    if ($d === 'home') { answerCb(BOT_TOKEN, $cbId); whAdminHome($chatId, $msgId); return true; }
    if (txRoute(whTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;
    if ($d === 'x')  { whSet(function (&$c) { $c['on'] = empty($c['on']) ? true : false; }); answerCb(BOT_TOKEN, $cbId, '✅'); whAdminHome($chatId, $msgId); return true; }
    if ($d === 'em') { whSet(function (&$c) { $c['entry'] = ($c['entry'] ?? 'app') === 'bot' ? 'app' : 'bot'; }); answerCb(BOT_TOKEN, $cbId, '✅'); whAdminHome($chatId, $msgId); return true; }
    if ($d === 'gm') { whSet(function (&$c) { $c['gift_mode'] = ($c['gift_mode'] ?? 'support') === 'auto' ? 'support' : 'auto'; }); answerCb(BOT_TOKEN, $cbId, '✅'); whAdminHome($chatId, $msgId); return true; }
    if ($d === 'post') {
        [$n, $all] = whPostNow();
        answerCb(BOT_TOKEN, $cbId, $all ? '✅ گردونه باز شد — پست در ' . $n . ' گروه فرستاده شد' . ($n < $all ? '؛ بقیه چند دقیقه‌ی دیگر' : '') : '✅ گردونه باز شد — ربات هنوز در هیچ گروهی نیست', true);
        whAdminHome($chatId, $msgId);
        return true;
    }
    if ($d === 'prev') { answerCb(BOT_TOKEN, $cbId); whPostTo($chatId); return true; }
    if ($d === 'today') { answerCb(BOT_TOKEN, $cbId); whAdminToday($chatId, $msgId); return true; }
    if (preg_match('/^tg(?:_(\d+))?$/', $d, $m)) { answerCb(BOT_TOKEN, $cbId); whAdminGroups($chatId, $msgId, (int)($m[1] ?? 0)); return true; }
    if (preg_match('/^to_(-?\d+)$/', $d, $m)) {
        $r = whPostHere((int)$m[1]);
        answerCb(BOT_TOKEN, $cbId, $r[0] === 'ok' ? '✅ فرستاده شد' : '🔴 نرفت', $r[0] !== 'ok');
        sendMsg(BOT_TOKEN, $chatId, whTestText($m[1], $r), inlineKb([[btnCb('🧪 تستِ دوباره', 'whp_tg', 'admin'), btnCb('🎡 گردونه شانس', 'whp_home', 'admin')]]));
        return true;
    }
    if (preg_match('/^gv_(\d+)_(\d{4}-\d{2}-\d{2})$/', $d, $m)) {
        $me = function_exists('getUser') ? (getUser($chatId) ?: []) : [];
        $ok = whMarkGiven((int)$m[1], $m[2], whByName(['first_name' => $me['first_name'] ?? '', 'username' => $me['username'] ?? ''], $chatId));
        answerCb(BOT_TOKEN, $cbId, whT($ok ? 'pop_given' : 'pop_given_old'));
        whAdminToday($chatId, $msgId);
        return true;
    }
    if (preg_match('/^rs_(\d+)_(\d{4}-\d{2}-\d{2})$/', $d, $m)) {
        $ok = whRetry((int)$m[1], $m[2]);
        answerCb(BOT_TOKEN, $cbId, $ok === null ? 'این گیفت قبلا تحویل شده یا پیدا نشد.' : ($ok ? '✅ گیفت فرستاده شد' : '⚠️ باز هم فرستاده نشد'), $ok !== true);
        whAdminToday($chatId, $msgId);
        return true;
    }
    if ($d === 'colors') { answerCb(BOT_TOKEN, $cbId); whAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^cv_(btn_post|btn_open|btn_given)_(none|primary|success|danger)$/', $d, $m)) {
        whSet(function (&$c) use ($m) { $c['btns'][$m[1]]['color'] = $m[2]; });
        answerCb(BOT_TOKEN, $cbId, '✅'); whAdminColors($chatId, $msgId); return true;
    }
    if ($d === 'pz') { answerCb(BOT_TOKEN, $cbId); whAdminPrizes($chatId, $msgId); return true; }
    if ($d === 'gifts') {
        answerCb(BOT_TOKEN, $cbId);
        $t = "🎁 <b>گیفت‌های تلگرام</b>\n\n";
        foreach (whGiftMap(true) as $e => $g) $t .= h($e) . ' — ' . (int)$g['stars'] . " ⭐️ — <code>" . h($g['id']) . "</code>\n";
        editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3900), inlineKb([[btnCb(UT('back'), 'whp_pz', 'nav')]]));
        return true;
    }
    if ($d === 'pzadd') {
        $nid = 'p' . bin2hex(random_bytes(3));
        whSet(function (&$c) use ($nid) {
            $c['prizes'][] = ['id' => $nid, 'on' => false, 'type' => 'coupon', 'emoji' => '🎟️', 'label' => 'جایزه‌ی تازه',
                              'percent' => 5, 'weight' => 5, 'cap' => 0, 'color' => '#8B5CF6'];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); whAdminPrize($chatId, $msgId, $nid); return true;
    }
    if (preg_match('/^pz(x|k|w|c|l|e|h|g|p|a|d|dy)?_([a-z0-9]+)$/', $d, $m)) {
        [, $op, $id] = $m;
        $p = whPrize($id);
        if (!$p) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); whAdminPrizes($chatId, $msgId); return true; }
        $back = 'whp_pz_' . $id;
        switch ($op) {
            case '':  answerCb(BOT_TOKEN, $cbId); whAdminPrize($chatId, $msgId, $id); return true;
            case 'x': whPrizeSet($id, function (&$x) { $x['on'] = empty($x['on']); }); answerCb(BOT_TOKEN, $cbId, '✅'); whAdminPrize($chatId, $msgId, $id); return true;
            case 'k':
                $keys = array_keys(whTypes());
                $next = $keys[((int)array_search((string)($p['type'] ?? 'gift'), $keys, true) + 1) % count($keys)];
                whPrizeSet($id, function (&$x) use ($next) {
                    $x['type'] = $next;
                    if ($next === 'coupon' && empty($x['percent'])) $x['percent'] = 5;
                    if ($next === 'diamond' && empty($x['amount'])) $x['amount'] = 1000;
                });
                answerCb(BOT_TOKEN, $cbId, whTypes()[$next]); whAdminPrize($chatId, $msgId, $id); return true;
            case 'd':
                answerCb(BOT_TOKEN, $cbId);
                editMsg(BOT_TOKEN, $chatId, $msgId, '🗑 این جایزه از گردونه حذف شود؟',
                    inlineKb([[btnCb('🗑 بله، حذف شود', 'whp_pzdy_' . $id, 'reject')], [btnCb(UT('back'), $back, 'nav')]]));
                return true;
            case 'dy':
                whSet(function (&$c) use ($id) { $c['prizes'] = array_values(array_filter($c['prizes'], fn($x) => !is_array($x) || (string)($x['id'] ?? '') !== $id)); });
                answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد'); whAdminPrizes($chatId, $msgId); return true;
        }
        $asks = [
            'w' => ['weight',  '⚖️ <b>شانس</b> (از ۱۰۰۰)'],
            'c' => ['cap',     '🔢 <b>سقفِ روزانه</b> (۰ = بی‌سقف)'],
            'l' => ['label',   '✏️ <b>نامِ جایزه</b>'],
            'e' => ['emoji',   '😀 <b>ایموجیِ جایزه</b>'],
            'h' => ['color',   '🎨 <b>رنگِ برش</b> · <code>#A78BFA</code>'],
            'g' => ['gift_id', '🆔 <b>شناسه‌ی گیفت</b> · <code>خودکار</code>'],
            'p' => ['percent', '٪ <b>درصدِ تخفیف</b> (۱–۱۰۰)'],
            'a' => ['amount',  '💎 <b>تعدادِ الماس</b>'],
        ];
        if (isset($asks[$op])) {
            answerCb(BOT_TOKEN, $cbId);
            whAsk($chatId, 'wh_pz', ['id' => $id, 'f' => $asks[$op][0]], $asks[$op][1], $back);
            return true;
        }
        return false;
    }
    $asks = [
        'rep'   => ['wh_report',  "📋 <b>گروهِ لیستِ برنده‌ها</b>\n\n<code>/wheelwinners</code> · لینکِ پیام · <code>-100…</code> · <code>-</code>"],
        'hours' => ['wh_hours',   "🕘 <b>بازه‌ی ساعتِ پست</b>\n\nفعلی: <code>" . (int)whCfg()['from_hour'] . '-' . (int)whCfg()['to_hour'] . "</code>"],
        'cdays' => ['wh_cdays',   "🏷 <b>اعتبارِ کد تخفیف (روز)</b>\n\nفعلی: <code>" . (int)whCfg()['coupon_days'] . "</code>"],
        'tgx'   => ['wh_to',      "🧪 <b>تستِ پست</b>\n\nلینکِ پیام · <code>-100…</code> · <code>@name</code>"],
        'cap'   => ['wh_cap',     "👥 <b>ظرفیتِ هر پست</b> (۰ = بی‌سقف)\n\nفعلی: <code>" . whCap() . "</code>"],
        'link'  => ['wh_link',    "🔗 <b>لینکِ مستقیمِ اختصاصی</b>\n\n<code>https://t.me/YourBot/wheel</code> · <code>-</code>\n\nWeb App URL:\n<code>" . h(whUrl()) . "</code>"],
        'photo' => ['wh_photo',   "🖼 <b>عکسِ پستِ گروه</b> · <code>-</code>"],
    ];
    if (isset($asks[$d])) {
        answerCb(BOT_TOKEN, $cbId);
        whAsk($chatId, $asks[$d][0], [], $asks[$d][1], 'whp_home');
        return true;
    }
    if (str_starts_with($d, 'ts_')) {
        $k = substr($d, 3);
        if (!array_key_exists($k, whDefaults()['texts'])) { answerCb(BOT_TOKEN, $cbId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        $cur = whT($k);
        if (in_array($k, whBtnKeys(), true))
            $ask = "✏️ <b>" . h(whLabel($k)) . "</b>\n\nالان: <code>" . h($cur) . '</code>';
        elseif (str_starts_with($k, 'app_'))
            $ask = "✏️ <b>" . h(whLabel($k)) . "</b>\n\nالان: <code>" . h($cur) . '</code>';
        else
            $ask = "✏️ <b>" . h(whLabel($k)) . "</b>\n\nالان:\n" . $cur;
        whAsk($chatId, 'wh_text', ['k' => $k], $ask, 'whp_t_home');
        return true;
    }
    return false;
}

function whStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'wh_') || !isAdmin($uid)) return false;
    $sd   = getState($uid)['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $done = function ($m = '✅ ذخیره شد.', $back = 'whp_home') use ($uid, $chatId) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, inlineKb([[btnCb('🎡 گردونه شانس', $back, 'admin')]]));
        return true;
    };

    if ($action === 'wh_report') {
        if ($text === '-') { whSet(function (&$c) { $c['report_chat'] = ''; $c['report_topic'] = 0; }); return $done('✅ گروهِ برنده‌ها برداشته شد'); }
        [$chat, $topic] = chatLinkResolve($text);
        if ($chat === null || !preg_match('/^(-\d{5,20}|@[A-Za-z0-9_]{4,32})$/', (string)$chat)) {
            sendMsg(BOT_TOKEN, $chatId, '⚠️ شناخته نشد · <code>https://t.me/c/1234567890/11</code> · <code>-100…</code>');
            return true;
        }
        $res = sendMsg(BOT_TOKEN, $chat, '✅ لیستِ برنده‌های گردونه', null, $topic > 0 ? ['message_thread_id' => (int)$topic] : []);
        if (empty($res['ok'])) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ ربات نتوانست آن‌جا پیام بفرستد: " . tgWhy($res));
            return true;
        }
        whSet(function (&$c) use ($chat, $topic) { $c['report_chat'] = (string)$chat; $c['report_topic'] = (int)$topic; });
        return $done('✅ لیستِ برنده‌ها: <code>' . h((string)$chat) . '</code>' . ($topic > 0 ? ' · تاپیکِ <code>' . (int)$topic . '</code>' : ''));
    }
    if ($action === 'wh_hours') {
        if (!preg_match('/^\s*(\d{1,2})\s*[-–ـ]\s*(\d{1,2})\s*$/u', norm_fa_digits($text), $m) || (int)$m[1] > 23 || (int)$m[2] > 24 || (int)$m[2] <= (int)$m[1]) {
            sendMsg(BOT_TOKEN, $chatId, '⚠️ قالب: <code>10-22</code>'); return true;
        }
        whSet(function (&$c) use ($m) { $c['from_hour'] = (int)$m[1]; $c['to_hour'] = (int)$m[2]; });
        $db = whDb();
        if ($db) {
            $day = whDay();
            $st = $db->prepare('UPDATE wh_days SET post_at = :p WHERE day = :d AND sent_at = 0 AND post_at > ' . time());
            $st->bindValue(':p', whPlanTs($day), SQLITE3_INTEGER);
            $st->bindValue(':d', $day, SQLITE3_TEXT);
            $st->execute();
        }
        return $done('✅ بازه: ' . (int)$m[1] . ' تا ' . (int)$m[2]);
    }
    if ($action === 'wh_cdays') {
        $v = (int)norm_fa_digits($text);
        if ($v < 1 || $v > 365) { sendMsg(BOT_TOKEN, $chatId, '⚠️ بین ۱ تا ۳۶۵ روز.'); return true; }
        whSet(function (&$c) use ($v) { $c['coupon_days'] = $v; });
        return $done('✅ اعتبارِ کدها: ' . $v . ' روز');
    }
    if ($action === 'wh_to') {
        [$chat, , $probe] = chatLinkResolve($text);
        if ($chat === null) { sendMsg(BOT_TOKEN, $chatId, '⚠️ شناخته نشد · <code>-100…</code> · <code>@name</code>'); return true; }
        if (empty($probe['ok']) || !preg_match('/^-\d+$/', (string)$chat)) { sendMsg(BOT_TOKEN, $chatId, '🔴 ربات به این گروه دسترسی ندارد: ' . tgWhy((array)$probe)); return true; }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, whTestText($chat, whPostHere((int)$chat)), inlineKb([[btnCb('🧪 تستِ دوباره', 'whp_tg', 'admin'), btnCb('🎡 گردونه شانس', 'whp_home', 'admin')]]));
        return true;
    }
    if ($action === 'wh_cap') {
        $v = norm_fa_digits($text);
        if (!preg_match('/^\d{1,4}$/', $v)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عدد نامعتبر'); return true; }
        whSet(function (&$c) use ($v) { $c['post_cap'] = (int)$v; });
        return $done('✅ ظرفیتِ هر پست: ' . ((int)$v > 0 ? (int)$v . ' نفر' : 'بی‌سقف'));
    }
    if ($action === 'wh_link') {
        if ($text === '-') { whSet(function (&$c) { $c['app_link'] = ''; }); return $done('✅ لینکِ اختصاصی برداشته شد.'); }
        if (!preg_match('#^https://t\.me/\S+$#i', $text)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ قالب: <code>https://t.me/YourBot/wheel</code>'); return true; }
        whSet(function (&$c) use ($text) { $c['app_link'] = $text; });
        return $done('✅ لینکِ مستقیم ذخیره شد.');
    }
    if ($action === 'wh_photo') {
        if ($text === '-') { whSet(function (&$c) { $c['photo'] = ''; }); return $done('✅ پست فقط‌متنی شد.'); }
        $ph = $msg['photo'] ?? null;
        $fid = is_array($ph) && $ph ? (string)(end($ph)['file_id'] ?? '') : '';
        if ($fid === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ عکس لازم است'); return true; }
        whSet(function (&$c) use ($fid) { $c['photo'] = $fid; });
        return $done('✅ عکسِ پست ذخیره شد.');
    }
    if ($action === 'wh_pz') {
        $id = (string)($sd['id'] ?? '');
        $f  = (string)($sd['f'] ?? '');
        $back = 'whp_pz_' . $id;
        $num = (float)str_replace([',', '٬'], '', norm_fa_digits($text));
        if (in_array($f, ['weight', 'cap'], true) && ($text === '' || $num < 0 || $num > 100000)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عدد نامعتبر'); return true; }
        if ($f === 'percent' && ($num < 1 || $num > 100)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ بین ۱ تا ۱۰۰.'); return true; }
        if ($f === 'amount' && $num <= 0) { sendMsg(BOT_TOKEN, $chatId, '⚠️ عدد نامعتبر'); return true; }
        if ($f === 'color' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $text)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ قالب: <code>#A78BFA</code>'); return true; }
        if (in_array($f, ['label', 'emoji'], true) && $text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ خالی نمی‌شود.'); return true; }
        $val = match ($f) {
            'weight', 'cap' => (int)$num,
            'percent', 'amount' => $num,
            'gift_id' => in_array($text, ['خودکار', 'auto', '-'], true) ? '' : preg_replace('/\D/', '', $text),
            'label' => mb_substr(strip_tags($text), 0, 40),
            'emoji' => mb_substr($text, 0, 8),
            default => $text,
        };
        whPrizeSet($id, function (&$x) use ($f, $val) { $x[$f] = $val; });
        return $done('✅ ذخیره شد.', $back);
    }
    if ($action === 'wh_text') {
        $k = (string)($sd['k'] ?? '');
        if (!array_key_exists($k, whDefaults()['texts'])) { clearState($uid); return true; }
        if (in_array($k, whBtnKeys(), true)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
            $ids  = customEmojiIds($msg);
            $icon = $ids ? (string)$ids[0] : '';
            $clean = $icon !== '' ? textWithoutCustomEmoji($msg) : $text;
            if ($clean === '') $clean = $text;
            whSet(function (&$c) use ($k, $clean, $icon) { $c['texts'][$k] = $clean; $c['icons'][$k] = $icon; });
            clearState($uid);
            $b = whBtn($k, ['callback_data' => 'whp_nop']);
            sendMsg(BOT_TOKEN, $chatId, '✅ ذخیره شد', inlineKb([[$b]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', inlineKb([[btnCb('✏️ متن‌های گردونه', 'whp_t_home', 'admin')]]));
            return true;
        }
        $val = str_starts_with($k, 'app_') ? mb_substr($text, 0, 300) : msgHtml($msg);
        if (trim(strip_tags($val)) === '' && !customEmojiIds($msg)) { sendMsg(BOT_TOKEN, $chatId, '⚠️ متن خالی نمی‌شود.'); return true; }
        whSet(function (&$c) use ($k, $val) { $c['texts'][$k] = $val; });
        return $done('✅ ذخیره شد.', 'whp_t_home');
    }
    clearState($uid);
    return true;
}

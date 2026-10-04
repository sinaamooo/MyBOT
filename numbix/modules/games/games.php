<?php
defined('NB_ROOT') || exit;

if (!defined('GM_LIB')) define('GM_LIB', 1);


function gmDefaults() {
    return [
        'btns' => [
            'duel_join'       => ['color' => 'success'],
            'duel_cancel'     => ['color' => 'danger'],
            'lbl_wbal'        => ['color' => 'success'],
            'lbl_lbal'        => ['color' => 'danger'],
            'lbl_cancel_duel' => ['color' => 'danger'],
            'bal_btn'         => ['color' => 'primary'],
            'send_bal'        => ['color' => 'primary'],
            'send_bal2'       => ['color' => 'primary'],
        ],
        'on'        => false,
        'duel_on'   => true,
        'ttt_on'    => true,
        'open_max'  => 2,
        'expire'    => 180,
        'word_duel' => 'چالش',
        'word_ttt'  => 'دوز',
        'word_bal'  => 'موجودی',
        'word_send' => 'انتقال,انتقال الماس',

        'min'       => 10,
        'max'       => 1000000000,
        'tax'       => 10,
        'send_tax'  => 10,

        'icons'     => [],

        'texts' => [
            'duel_open'  => "{emoji} <b>چالش</b>  {stake_big}\n\n" .
                            "<blockquote>👤 سازنده: {host}\n" .
                            "🏆 جایزه‌ی برنده:\n{prize_big}\n" .
                            "🧾 مالیات:\n{tax_big}</blockquote>\n\n" .
                            "نفر دوم که روی پیوستن بزند، برنده همان لحظه مشخص می‌شود.",
            'ttt_open'   => "⭕ <b>دوز</b>  {stake_big}\n\n" .
                            "<blockquote>👤 سازنده: {host}\n" .
                            "🏆 جایزه‌ی برنده:\n{prize_big}\n" .
                            "🧾 مالیات:\n{tax_big}</blockquote>\n\n" .
                            "نفر دوم که روی پیوستن بزند، صفحه‌ی دوز باز می‌شود.",
            'ttt_turn'   => "⭕ <b>دوز {stake} الماسی</b>\n\n" .
                            "<blockquote>{p1}\n{p2}\n" .
                            "🏆 جایزه: <b>{prize}</b> الماس</blockquote>\n\n" .
                            "نوبت: {turn}",
            'duel_win'   => "🎉 <b>نتیجه بازی مشخص شد</b>\n\n" .
                            "<blockquote>🏆 برنده: {wname}\n" .
                            "❌ بازنده: {lname}</blockquote>",
            'duel_draw'  => "🤝 <b>مساوی شد</b>\n\nشرطِ هر دو نفر برگشت.",
            'duel_join'  => "✅ پیوستن",
            'duel_cancel'=> "❌ لغو",

            'bal_pop'    => "💎 موجودی {name}: {points} الماس",
            'lbl_wbal'   => "💎 موجودی برنده",
            'lbl_lbal'   => "❌ موجودی بازنده",

            'bal_head'   => "{emoji} <b>موجودی شما</b>",
            'bal_btn'    => "💎 {points} الماس",

            'send_ok'    => "✅ <b>انتقال انجام شد</b>\n\n" .
                            "<blockquote>📤 فرستنده: {from}\n📥 گیرنده: {to}\n" .
                            "💎 مبلغ انتقال: <b>{amount}</b>\n" .
                            "🧾 مالیات: <b>{tax}</b>\n" .
                            "➖ کسر کل: <b>{total}</b></blockquote>",
            'send_bal'   => "💎 موجودی فرستنده",
            'send_bal2'  => "💎 موجودی گیرنده",
            'send_how'   => "⚠️ ریپلای + «{word} ۱۰۰»",
            'send_self'  => "❌ به خودت که نمی‌شود.",

            'off'        => "🎮 بازی فعلا خاموش است.",
            'low'        => "❌ الماس کافی نداری.\n💎 موجودی تو: <b>{points}</b> · لازم: <b>{need}</b>",
            'bad_stake'  => "❌ شرط باید بین <b>{min}</b> و <b>{max}</b> الماس باشد.",
            'not_yours'  => "این بازی مال تو نیست.",
            'not_turn'   => "نوبت تو نیست.",
            'taken'      => "این خانه پر است.",
            'gone'       => "این بازی تمام شده.",
            'cancelled'  => "❌ <b>بازی لغو شد</b>\n\nشرط برگشت.",
            'group_only' => "🎮 بازی فقط داخل گروه کار می‌کند.",
            'duel_how'   => "🎮 <code>{word} ۱۰۰</code> · {min} تا {max} الماس",
            'ttt_how'    => "⭕ <code>{word} ۱۰۰</code> · {min} تا {max} الماس",
            'already'    => "تو که خودت داخل این بازی هستی — منتظر حریف بمان.",
            'open_max'   => "⛔️ شما <b>{n}</b> بازی باز دارید.",
            'expired'    => "⏳ <b>کسی وارد نشد</b>\n\nشرط به سازنده برگشت.",
            'idle'       => "⏳ <b>بازی نیمه‌کاره ماند</b>\n\nکسی نوبتش را نزد؛ شرط هر دو نفر برگشت.",
            'in_progress'=> "<tg-emoji emoji-id=\"5116476703002068797\">🎮</tg-emoji> بــازی شــما در حــال انـجـام هـسـتـش",
            'lbl_cancel_duel' => "چالش لغو شد",
        ],
    ];
}

function gmCfg() {
    $c = cfg()['games'] ?? null;
    return is_array($c) ? array_replace_recursive(gmDefaults(), $c) : gmDefaults();
}

function gmSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!isset($c['games']) || !is_array($c['games'])) $c['games'] = [];
        $fn($c['games']);
    });
}

function gmVal($path, $default = null) {
    $cur = gmCfg();
    foreach (explode('.', (string)$path) as $p) {
        if (!is_array($cur) || !array_key_exists($p, $cur)) return $default;
        $cur = $cur[$p];
    }
    return $cur;
}

function gmT($slug, $vars = []) {
    $t = (string)gmVal('texts.' . $slug, '');
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
    return strtr($t, $map);
}

function gmOn() { return !empty(gmVal('on')); }

function gmDigitIds() {
    $d = [
        '1' => '5771785311034021057', '2' => '5773975314858251177',
        '3' => '5771591496339820740', '4' => '5771376816694498290',
        '5' => '5771694571259958563', '6' => '5773735118812221922',
        '7' => '5773913385724809382', '8' => '5773786959067484290',
        '9' => '5774138390471512165', '0' => '5771423202341303860',
    ];
    foreach ((array)gmVal('digits', []) as $k => $v) {
        $k = (string)$k;
        if (isset($d[$k]) && (ctype_digit((string)$v) || $v === '')) $d[$k] = (string)$v;
    }
    return $d;
}

function gmBigNum($n) {
    $ids = gmDigitIds();
    $s = preg_replace('/\D+/', '', (string)number_format((float)$n, 0, '.', ''));
    if ($s === '') $s = '0';

    $LRM = "\u{200E}";
    $out = $LRM;
    foreach (str_split($s) as $c) {
        $keycap = $c . "\u{FE0F}\u{20E3}";
        $out .= (($ids[$c] ?? '') !== ''
              ? '<tg-emoji emoji-id="' . $ids[$c] . '">' . $keycap . '</tg-emoji>'
              : $keycap) . $LRM;
    }
    return "\u{2066}\u{202A}" . $out . "\u{202C}\u{2069}";
}

function gmBtnKeys() {
    return ['duel_join', 'duel_cancel',
            'lbl_wbal', 'lbl_lbal',
            'lbl_cancel_duel',
            'bal_btn', 'send_bal', 'send_bal2'];
}

function gmIsBtn($k) { return in_array($k, gmBtnKeys(), true); }

function gmDropDoubleIcons() {
    gmSet(function (&$c) {
        foreach ((array)($c['icons'] ?? []) as $k => $id) {
            if (trim((string)$id) === '') continue;
            $t = (string)($c['texts'][$k] ?? '');
            if ($t === '') continue;
            $clean = preg_replace(
                '/^[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2190}-\x{21FF}\x{2B00}-\x{2BFF}' .
                '\x{FE0F}\x{20E3}\x{200D}0-9#*]+\s*/u', '', $t);
            $clean = trim((string)$clean);
            if ($clean !== '' && $clean !== $t) $c['texts'][$k] = $clean;
        }
    });
}

function gmBtn($key, $vars, $data, $style = null) {
    $b = ['callback_data' => $data];
    $cfg = (string)gmVal('btns.' . $key . '.color', '');
    if ($cfg === 'none')                  {  }
    elseif (function_exists('isStyle') && isStyle($cfg)) $b['style'] = $cfg;
    elseif ($style)                       $b['style'] = $style;
    $b = function_exists('btnApplyLabel')
        ? btnApplyLabel($b, gmT($key, $vars), gmVal('icons.' . $key, ''))
        : ['text' => strip_tags((string)(gmT($key, $vars)))] + $b;
    return $b;
}

function gmNum($n) {
    return number_format((float)$n, 0, '.', ',');
}


function gmPoints($uid) {
    $u = function_exists('dmUser') ? dmUser($uid) : null;
    return (float)($u['points'] ?? 0);
}

function gmAdd($uid, $delta, $name = '', $uname = '') {
    $ok = false;
    dmUserSet($uid, function (&$u) use ($delta, $name, $uname, &$ok) {
        $p = (float)($u['points'] ?? 0);
        if ($delta < 0 && $p + 1e-9 < -$delta) return false;
        $u['points'] = round($p + $delta, 2);
        if ($name !== '')  $u['name'] = $name;
        if ($uname !== '') $u['username'] = $uname;
        $ok = true;
        return true;
    });
    return $ok;
}


function gmPalette() {
    return [
        'red'  => ['e' => '🔴', 'n' => 'قرمز', 's' => 'danger'],
        'blue' => ['e' => '🔵', 'n' => 'آبی',  's' => 'primary'],
    ];
}

function gmColor($uid) {
    $pal = gmPalette();
    $u   = function_exists('dmUser') ? dmUser($uid) : null;
    $k   = (string)($u['color'] ?? '');
    if (isset($pal[$k])) return $k;

    $keys = array_keys($pal);
    $k = $keys[random_int(0, count($keys) - 1)];
    if (function_exists('dmUserSet'))
        dmUserSet($uid, function (&$x) use ($k) { $x['color'] = $k; return true; });
    return $k;
}

function gmColorEmoji($k) { return (string)(gmPalette()[$k]['e'] ?? '⚪️'); }
function gmColorName($k)  { return (string)(gmPalette()[$k]['n'] ?? ''); }
function gmColorStyle($k) { return (string)(gmPalette()[$k]['s'] ?? 'primary'); }

function gmColorIcons() {
    $d = [
        'red' => '5411225014148014586', 'blue' => '', 'green' => '5416081784641168838',
        'yellow' => '', 'purple' => '', 'orange' => '', 'brown' => '', 'black' => '', 'white' => '',
    ];
    foreach ((array)gmVal('color_icons', []) as $k => $v) {
        $k = (string)$k;
        if (isset($d[$k]) && (ctype_digit((string)$v) || $v === '')) $d[$k] = (string)$v;
    }
    return $d;
}
function gmColorIcon($k) { return (string)(gmColorIcons()[$k] ?? ''); }

function gmPickColor($uid, array $taken) {
    $k = gmColor($uid);
    if (!in_array($k, $taken, true)) return $k;
    foreach (array_keys(gmPalette()) as $alt)
        if (!in_array($alt, $taken, true)) return $alt;
    return $k;
}

function gmPlayerColor($g, $uid) {
    $k = (string)($g['players'][(string)$uid]['color'] ?? '');
    return isset(gmPalette()[$k]) ? $k : gmColor($uid);
}


function gamesDbPath() { return DATA_DIR . '/games.sqlite'; }

function gamesDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = gamesDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[shop-bot] games.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS games (id TEXT PRIMARY KEY, data TEXT NOT NULL, created INTEGER NOT NULL DEFAULT 0, status TEXT NOT NULL DEFAULT \'\')');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_games_created ON games(created)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_games_status ON games(status)');

    if ($fresh) {
        gamesImportFromJson($db);
    } else {
        gamesEnsureStatusColumn($db);
    }
    return $db;
}

function gamesEnsureStatusColumn($db) {
    $has = false;
    $res = $db->query('PRAGMA table_info(games)');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if (($row['name'] ?? '') === 'status') { $has = true; break; }
    }
    if ($has) return;

    $db->exec('ALTER TABLE games ADD COLUMN status TEXT NOT NULL DEFAULT \'\'');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_games_status ON games(status)');

    $db->exec('BEGIN');
    $res = $db->query('SELECT id, data FROM games');
    $up = $db->prepare('UPDATE games SET status = :status WHERE id = :id');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        $up->bindValue(':status', is_array($d) ? (string)($d['status'] ?? '') : '', SQLITE3_TEXT);
        $up->bindValue(':id', $row['id'], SQLITE3_TEXT);
        $up->execute();
        $up->reset();
    }
    $db->exec('COMMIT');
}

function gamesImportFromJson($db) {
    $old = dataPath('games');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO games (id, data, created, status) VALUES (:id, :data, :created, :status)');
        foreach ($arr as $k => $v) {
            if ($k === '' || !is_array($v)) continue;
            $stmt->bindValue(':id', (string)$k, SQLITE3_TEXT);
            $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->bindValue(':created', (int)($v['created'] ?? 0), SQLITE3_INTEGER);
            $stmt->bindValue(':status', (string)($v['status'] ?? ''), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function gmAll() {
    $db = gamesDb();
    if (!$db) return [];
    $out = [];
    $res = $db->query('SELECT id, data FROM games');
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[$row['id']] = $d;
    }
    return $out;
}

function gmOpenOrPlaying($limit = 1000) {
    $db = gamesDb();
    if (!$db) return [];
    $out = [];
    $stmt = $db->prepare("SELECT id, data FROM games WHERE status IN ('open','playing') LIMIT :limit");
    $stmt->bindValue(':limit', max(1, (int)$limit), SQLITE3_INTEGER);
    $res = $stmt->execute();
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        if (is_array($d)) $out[$row['id']] = $d;
    }
    return $out;
}

function gmGet($id) {
    $db = gamesDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM games WHERE id = :id');
    $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function gmSetGame($id, callable $fn) {
    $db = gamesDb();
    if (!$db) return null;
    $id = (string)$id;

    if (!@$db->exec('BEGIN IMMEDIATE')) return null;
    try {
        $stmt = $db->prepare('SELECT data FROM games WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $g = $row ? json_decode($row['data'], true) : null;
        if (!is_array($g)) { $db->exec('ROLLBACK'); return false; }

        $result = $fn($g);

        $up = $db->prepare('INSERT OR REPLACE INTO games (id, data, created, status) VALUES (:id, :data, :created, :status)');
        $up->bindValue(':id', $id, SQLITE3_TEXT);
        $up->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->bindValue(':created', (int)($g['created'] ?? 0), SQLITE3_INTEGER);
        $up->bindValue(':status', (string)($g['status'] ?? ''), SQLITE3_TEXT);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
        return $result;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[shop-bot] gmSetGame خطا: ' . $e->getMessage());
        return null;
    }
}

function gmPut($g) {
    $db = gamesDb();
    if (!$db) return;
    $stmt = $db->prepare('INSERT OR REPLACE INTO games (id, data, created, status) VALUES (:id, :data, :created, :status)');
    $stmt->bindValue(':id', (string)$g['id'], SQLITE3_TEXT);
    $stmt->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->bindValue(':created', (int)($g['created'] ?? 0), SQLITE3_INTEGER);
    $stmt->bindValue(':status', (string)($g['status'] ?? ''), SQLITE3_TEXT);
    $stmt->execute();
    gmGcMaybe($db);
}

function gmGcMaybe($db) {
    $mark = DATA_DIR . '/.games_gc_at';
    if (time() - (@filemtime($mark) ?: 0) < 300) return;
    @touch($mark);

    $cutoff = time() - 86400;
    $res = $db->query('SELECT id, data FROM games WHERE created > 0 AND created < ' . (int)$cutoff);
    $del = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $d = json_decode($row['data'], true);
        $st = is_array($d) ? (string)($d['status'] ?? '') : '';
        if ($st !== 'open' && $st !== 'playing') $del[] = $row['id'];
    }
    if ($del) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('DELETE FROM games WHERE id = :id');
        foreach ($del as $id) { $stmt->bindValue(':id', $id, SQLITE3_TEXT); $stmt->execute(); $stmt->reset(); }
        $db->exec('COMMIT');
    }
}


function gmKindWords() {
    $ttt  = gmWords(gmVal('word_ttt', 'دوز'));
    $low  = array_map('mb_strtolower', $ttt);
    $duel = array_values(array_filter(gmWords(gmVal('word_duel', 'چالش')),
                                      fn($w) => !in_array(mb_strtolower($w), $low, true)));
    $out = [];
    if (!empty(gmVal('ttt_on', true)))  $out['ttt']  = $ttt;
    if (!empty(gmVal('duel_on', true))) $out['duel'] = $duel;
    return $out;
}

function gmParse($raw) {
    $t = trim(norm_fa_digits((string)$raw));
    foreach (gmKindWords() as $kind => $list) {
        foreach ($list as $w) {
            if ($w === '') continue;
            $w = preg_quote($w, '/');
            if (preg_match('/^' . $w . '\s+([\d,٬]+)$/u', $t, $m) ||
                preg_match('/^([\d,٬]+)\s+' . $w . '$/u', $t, $m)) {
                $n = (float)str_replace([',', '٬'], '', $m[1]);
                if ($n > 0) return [$kind, $n];
            }
        }
    }
    return null;
}

function gmBareWord($raw) {
    $t = mb_strtolower(trim(norm_fa_digits((string)$raw)));
    foreach (gmKindWords() as $kind => $list)
        foreach ($list as $w)
            if ($w !== '' && $t === mb_strtolower($w)) return $kind;
    return null;
}

function gmWords($csv) {
    $out = [];
    foreach (explode(',', (string)$csv) as $w) { $w = trim($w); if ($w !== '') $out[] = $w; }
    return $out;
}

function gmCreate($kind, $stake, $uid, $chat, $name, $uname, $thread = 0) {
    $g = [
        'id'      => 'g_' . bin2hex(random_bytes(5)),
        'kind'    => $kind,
        'chat'    => (string)$chat,
        'thread'  => (int)$thread,
        'msg'     => 0,
        'host'    => (int)$uid,
        'stake'   => (float)$stake,
        'players' => [(string)$uid => ['id' => (int)$uid, 'name' => $name, 'uname' => $uname,
                                      'color' => gmColor($uid)]],
        'board'   => array_fill(0, 9, 0),
        'turn'    => (int)$uid,
        'status'  => 'open',
        'created' => time(),
        'ends'    => 0,
    ];
    gmPut($g);
    return $g;
}

function gmPrize($g) {
    $pot   = (float)$g['stake'] * 2;
    $taxPc = max(0.0, min(90.0, (float)gmVal('tax', 10)));
    $tax   = floor($pot * $taxPc / 100);
    return [$pot - $tax, $tax];
}


function gmName($p) {
    $c = (string)($p['color'] ?? '');
    $dot = isset(gmPalette()[$c]) ? gmColorEmoji($c) . ' ' : '';

    $u = trim((string)($p['uname'] ?? ''));
    if ($u !== '') return $dot . '@' . ltrim($u, '@');
    $n = trim((string)($p['name'] ?? ''));
    return $dot . ($n !== '' ? h($n) : ('<code>' . (int)($p['id'] ?? 0) . '</code>'));
}

function gmEmoji() { return (string)gmVal('emoji', '💎'); }

function gmText($g) {
    [$prize, $tax] = gmPrize($g);
    $ps = array_values($g['players']);

    $big = [
        'stake_big' => gmBigNum($g['stake']),
        'prize_big' => gmBigNum($prize),
        'tax_big'   => gmBigNum($tax),
    ];

    if ($g['status'] === 'open')
        return gmT(($g['kind'] ?? 'duel') === 'ttt' ? 'ttt_open' : 'duel_open',
                   $big + ['emoji' => gmEmoji(), 'stake' => gmNum($g['stake']),
                           'host' => gmName($ps[0]), 'prize' => gmNum($prize),
                           'tax' => gmNum($tax)]);
    return gmT('ttt_turn', $big + ['emoji' => gmEmoji(), 'stake' => gmNum($g['stake']),
                             'p1' => gmName($ps[0]), 'p2' => gmName($ps[1] ?? []),
                             'prize' => gmNum($prize),
                             'turn' => gmName($g['players'][(string)$g['turn']] ?? [])]);
}

function gmKb($g) {
    if ($g['status'] === 'open') {
        return inlineKb([[
            gmBtn('duel_join', [], 'gmj_' . $g['id'], 'success'),
            gmBtn('duel_cancel', [], 'gmc_' . $g['id'], 'danger'),
        ]]);
    }
    if ($g['status'] !== 'playing') return null;

    $ps  = array_values($g['players']);
    $col = [1 => gmPlayerColor($g, (int)($ps[0]['id'] ?? 0)),
            2 => gmPlayerColor($g, (int)($ps[1]['id'] ?? 0))];

    $rows = [];
    for ($r = 0; $r < 3; $r++) {
        $line = [];
        for ($c = 0; $c < 3; $c++) {
            $i = $r * 3 + $c;
            $v = (int)$g['board'][$i];
            $b = ['text' => $v === 0 ? '·' : gmColorEmoji($col[$v] ?? ''),
                  'callback_data' => 'gmm_' . $g['id'] . '_' . $i];
            if ($v !== 0) {
                $b['style'] = gmColorStyle($col[$v] ?? '');
                $ic = gmColorIcon($col[$v] ?? '');
                if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
            }
            $line[] = $b;
        }
        $rows[] = $line;
    }
    $rows[] = [gmBtn('duel_cancel', [], 'gmc_' . $g['id'], 'danger')];
    return inlineKb($rows);
}

function gmShow($g, $replyTo = null) {
    $extra = [];
    if ((int)($g['thread'] ?? 0) > 0) $extra['message_thread_id'] = (int)$g['thread'];

    if (!(int)$g['msg']) {
        if ($replyTo) { $extra['reply_to_message_id'] = $replyTo; $extra['allow_sending_without_reply'] = 'true'; }
        $r = sendMsg(BOT_TOKEN, $g['chat'], gmText($g), gmKb($g), $extra);
        $mid = (int)($r['result']['message_id'] ?? 0);
        if ($mid) gmSetGame($g['id'], function (&$x) use ($mid) { $x['msg'] = $mid; return true; });
        return $mid;
    }
    editMsg(BOT_TOKEN, $g['chat'], (int)$g['msg'], gmText($g), gmKb($g));
    return (int)$g['msg'];
}

function gmResultKb($winnerId, $loserId) {
    $rows = [[gmBtn('lbl_wbal', [], 'gmb_' . (int)$winnerId, 'success')]];
    if ((int)$loserId) $rows[] = [gmBtn('lbl_lbal', [], 'gmb_' . (int)$loserId, 'danger')];
    return inlineKb($rows);
}


function gmFinish($g, $winnerId, $loserId) {
    [$prize, $tax] = gmPrize($g);

    $w = $g['players'][(string)$winnerId] ?? ['id' => $winnerId];
    $l = $loserId ? ($g['players'][(string)$loserId] ?? ['id' => $loserId]) : [];

    $won = gmSetGame($g['id'], function (&$x) use ($winnerId, $loserId) {
        if (!in_array($x['status'] ?? '', ['open', 'playing'], true)) return false;
        $x['status'] = 'done'; $x['winner'] = (int)$winnerId; $x['loser'] = (int)$loserId;
        return true;
    });
    if ($won !== true) return;
    gmAdd($winnerId, $prize, $w['name'] ?? '', $w['uname'] ?? '');

    $text = gmT('duel_win', [
        'winner' => (int)$winnerId,
        'loser'  => (int)($loserId ?: 0),
        'wname'  => gmName($w),
        'lname'  => $l ? gmName($l) : '—',
        'prize'  => gmNum($prize),
        'tax'    => gmNum($tax),
        'stake'  => gmNum($g['stake']),
    ]);
    $kb = gmResultKb($winnerId, $loserId);

    if ((int)$g['msg']) editMsg(BOT_TOKEN, $g['chat'], (int)$g['msg'], $text, $kb);
    else                sendMsg(BOT_TOKEN, $g['chat'], $text, $kb);

}

function gmRefund($g, $why) {
    $back = gmSetGame($g['id'], function (&$x) {
        if (!in_array($x['status'] ?? '', ['open', 'playing'], true)) return false;
        $x['status'] = 'cancelled';
        return ['players' => (array)$x['players'], 'stake' => (float)$x['stake']];
    });
    if (!is_array($back)) return;
    foreach ($back['players'] as $p) gmAdd((int)$p['id'], $back['stake']);

    $kb = gmCancelKb($g);
    if ((int)$g['msg']) editMsg(BOT_TOKEN, $g['chat'], (int)$g['msg'], $why, $kb);
    else                sendMsg(BOT_TOKEN, $g['chat'], $why, $kb);
}

function gmCancelKb($g) {
    return inlineKb([[gmBtn('lbl_cancel_duel', [], 'gmnop', 'danger')]]);
}

function gmWinnerMark($b) {
    $lines = [[0,1,2],[3,4,5],[6,7,8],[0,3,6],[1,4,7],[2,5,8],[0,4,8],[2,4,6]];
    foreach ($lines as [$x, $y, $z])
        if ($b[$x] !== 0 && $b[$x] === $b[$y] && $b[$y] === $b[$z]) return $b[$x];
    return 0;
}

function gmBoardFull($b) {
    foreach ($b as $v) if ((int)$v === 0) return false;
    return true;
}


function gmTick($limit = 20) {
    $now  = time();
    $done = 0;
    $exp  = max(30, (int)gmVal('expire', 180));

    foreach (gmOpenOrPlaying() as $g) {
        if ($done >= $limit) break;
        if (($g['status'] ?? '') === 'playing') {
            $idle = (int)($g['moved'] ?? $g['created'] ?? 0);
            if ($idle > 0 && ($now - $idle) >= $exp) { gmRefund($g, gmT('idle')); $done++; }
            continue;
        }

        if (($g['status'] ?? '') !== 'open') continue;

        if (count($g['players'] ?? []) < 2
            && (int)($g['created'] ?? 0) > 0
            && ($now - (int)$g['created']) >= $exp) {
            gmRefund($g, gmT('expired'));
            $done++;
            continue;
        }
    }
    return $done;
}


function gmHandleText($text, $uid, $chatId, $name, $uname = '', $replyTo = null,
                      $isPrivate = false, $msg = null) {
    if (!gmOn()) return false;
    $raw = trim((string)$text);
    if ($raw === '' || mb_strlen($raw) > 40) return false;

    if (!function_exists('tickDue') || tickDue('gm', 20)) gmTick(3);

    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];

    foreach (gmWords(gmVal('word_bal', 'موجودی')) as $w) {
        if ($w === '' || mb_strtolower($raw) !== mb_strtolower($w)) continue;
        $pts = gmPoints($uid);
        sendMsg(BOT_TOKEN, $chatId, gmT('bal_head', ['emoji' => gmEmoji(), 'points' => gmNum($pts)]),
            inlineKb([[gmBtn('bal_btn', ['points' => gmNum($pts)], 'gmnop', 'primary')]]), $extra);
        return true;
    }

    if ($r = gmParseSend($raw)) {
        gmTransfer($r, $uid, $chatId, $name, $uname, $replyTo, $msg);
        return true;
    }
    foreach (gmWords(gmVal('word_send', 'انتقال')) as $w) {
        if ($w !== '' && mb_strtolower($raw) === mb_strtolower($w)) {
            sendMsg(BOT_TOKEN, $chatId, gmT('send_how', ['word' => $w]), null, $extra);
            return true;
        }
    }

    $min = max(1, (float)gmVal('min', 10));
    $max = max($min, (float)gmVal('max', 1e9));

    $p = gmParse($raw);
    if (!$p) {
        $bare = gmBareWord($raw);
        if ($bare === null) return false;
        if ($isPrivate) { sendMsg(BOT_TOKEN, $chatId, gmT('group_only'), null, $extra); return true; }
        $tip = gmT($bare === 'ttt' ? 'ttt_how' : 'duel_how', [
            'word' => (string)(gmKindWords()[$bare][0] ?? ($bare === 'ttt' ? 'دوز' : 'چالش')),
            'min'  => gmNum($min), 'max' => gmNum($max),
        ]);
        if (trim($tip) === '') return false;
        sendMsg(BOT_TOKEN, $chatId, $tip, null, $extra);
        return true;
    }
    [$kind, $stake] = $p;

    if ($isPrivate) { sendMsg(BOT_TOKEN, $chatId, gmT('group_only'), null, $extra); return true; }

    if ($stake < $min || $stake > $max) {
        sendMsg(BOT_TOKEN, $chatId, gmT('bad_stake', ['min' => gmNum($min), 'max' => gmNum($max)]), null, $extra);
        return true;
    }
    $max = max(1, (int)gmVal('open_max', 2));
    $mine = 0;
    foreach (gmOpenOrPlaying() as $og) {
        if ((int)($og['host'] ?? 0) !== (int)$uid) continue;
        $mine++;
    }
    if ($mine >= $max) {
        sendMsg(BOT_TOKEN, $chatId, gmT('open_max', ['n' => gmNum($mine)]), null, $extra);
        return true;
    }

    if (!gmAdd($uid, -$stake, $name, $uname)) {
        sendMsg(BOT_TOKEN, $chatId,
            gmT('low', ['points' => gmNum(gmPoints($uid)), 'need' => gmNum($stake)]), null, $extra);
        return true;
    }

    $thread = (int)($msg['message_thread_id'] ?? 0);
    $g = gmCreate($kind, $stake, $uid, $chatId, $name, $uname, $thread);
    gmShow($g, $replyTo);
    gmPruneMsgs($uid, $chatId);
    return true;
}

function gmPruneMsgs($uid, $chat, $keep = null) {
    $keep = $keep === null ? max(1, (int)gmVal('open_max', 2)) : max(0, (int)$keep);

    $mine = [];
    foreach (gmAll() as $g) {
        if ((int)($g['host'] ?? 0) !== (int)$uid) continue;
        if ((string)($g['chat'] ?? '') !== (string)$chat) continue;
        if ((int)($g['msg'] ?? 0) <= 0) continue;
        $mine[] = $g;
    }
    if (count($mine) <= $keep) return 0;

    usort($mine, fn($a, $b) => (int)($b['created'] ?? 0) <=> (int)($a['created'] ?? 0));
    $gone = 0;
    foreach (array_slice($mine, $keep) as $g) {
        delMsg(BOT_TOKEN, $g['chat'], (int)$g['msg']);
        gmSetGame($g['id'], function (&$x) { $x['msg'] = 0; return true; });
        $gone++;
    }
    return $gone;
}


function gmParseSend($raw) {
    $t = trim(norm_fa_digits((string)$raw));
    foreach (gmWords(gmVal('word_send', 'انتقال')) as $w) {
        if ($w === '') continue;
        $q = preg_quote($w, '/');
        if (preg_match('/^' . $q . '\s+([\d,٬]+)$/u', $t, $m) ||
            preg_match('/^([\d,٬]+)\s+' . $q . '$/u', $t, $m)) {
            $n = (float)str_replace([',', '٬'], '', $m[1]);
            if ($n > 0) return $n;
        }
    }
    return null;
}

function gmTransfer($amount, $uid, $chatId, $name, $uname, $replyTo, $msg) {
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
    $to = $msg['reply_to_message']['from'] ?? null;

    if (!$to || !empty($to['is_bot'])) {
        sendMsg(BOT_TOKEN, $chatId, gmT('send_how', ['word' => gmVal('word_send', 'انتقال')]), null, $extra);
        return;
    }
    $toId = (int)$to['id'];
    if ($toId === (int)$uid) { sendMsg(BOT_TOKEN, $chatId, gmT('send_self'), null, $extra); return; }

    $taxPc = max(0.0, min(90.0, (float)gmVal('send_tax', 10)));
    $tax   = floor($amount * $taxPc / 100);
    $total = $amount + $tax;

    if (!gmAdd($uid, -$total, $name, $uname)) {
        sendMsg(BOT_TOKEN, $chatId,
            gmT('low', ['points' => gmNum(gmPoints($uid)), 'need' => gmNum($total)]), null, $extra);
        return;
    }
    gmAdd($toId, $amount, $to['first_name'] ?? '', $to['username'] ?? '');

    $from = ['id' => $uid, 'name' => $name, 'uname' => $uname];
    $dst  = ['id' => $toId, 'name' => $to['first_name'] ?? '', 'uname' => $to['username'] ?? ''];

    sendMsg(BOT_TOKEN, $chatId, gmT('send_ok', [
        'from' => gmName($from), 'to' => gmName($dst),
        'amount' => gmNum($amount), 'tax' => gmNum($tax), 'total' => gmNum($total),
        'fbal' => gmNum(gmPoints($uid)), 'tbal' => gmNum(gmPoints($toId)),
    ]), null, $extra);

}


function gmCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if (!str_starts_with((string)$data, 'gm')) return false;
    if ($data === 'gmnop') { answerCb(BOT_TOKEN, $cbId); return true; }

    if (preg_match('/^gmb_(\d+)$/', (string)$data, $bm)) {
        $who = (int)$bm[1];
        $u   = function_exists('dmUser') ? dmUser($who) : null;
        $ck  = gmColor($who);
        answerCb(BOT_TOKEN, $cbId, gmT('bal_pop', [
            'name'   => trim((string)($u['name'] ?? '')) !== '' ? (string)$u['name'] : (string)$who,
            'points' => gmNum(gmPoints($who)),
        ]) . "\n" . gmColorEmoji($ck) . ' ' . gmColorName($ck), true);
        return true;
    }

    if (str_starts_with($data, 'gma')) return false;

    if (!preg_match('/^gm([jcm])_(g_[0-9a-f]+)(?:_(\d))?$/', $data, $m)) return false;
    [$all, $act, $gid] = $m;
    $g = gmGet($gid);
    if (!$g || !in_array($g['status'], ['open', 'playing'], true)) {
        answerCb(BOT_TOKEN, $cbId, gmT('gone'), true);
        if ($msgId) editKb(BOT_TOKEN, $chatId, (int)$msgId, gmCancelKb($g ?: ['kind' => 'duel']));
        return true;
    }

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    if ($act === 'c') {
        if ((int)$g['host'] !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, gmT('not_yours'), true); return true; }
        answerCb(BOT_TOKEN, $cbId, '❌');
        gmRefund($g, gmT('cancelled'));
        return true;
    }

    if ($act === 'j') {
        if (isset($g['players'][(string)$uid])) {
            answerCb(BOT_TOKEN, $cbId, strip_tags(gmT('already')), true);
            return true;
        }
        if (count($g['players']) >= 2) {
            answerCb(BOT_TOKEN, $cbId, gmT('gone'), true); return true;
        }
        if (!gmAdd($uid, -(float)$g['stake'], $name, $uname)) {
            answerCb(BOT_TOKEN, $cbId,
                strip_tags(gmT('low', ['points' => gmNum(gmPoints($uid)), 'need' => gmNum($g['stake'])])), true);
            return true;
        }

        $joined = false;
        gmSetGame($gid, function (&$x) use ($uid, $name, $uname, &$joined) {
            if (($x['status'] ?? '') !== 'open') return false;
            if (isset($x['players'][(string)$uid])) return false;
            if (count($x['players']) >= 2) return false;
            $taken = [];
            foreach ($x['players'] as $pp) if (!empty($pp['color'])) $taken[] = (string)$pp['color'];
            $x['players'][(string)$uid] = ['id' => (int)$uid, 'name' => $name, 'uname' => $uname,
                                           'color' => gmPickColor($uid, $taken)];
            $x['status'] = 'playing'; $x['turn'] = (int)$x['host']; $x['moved'] = time();
            $joined = true;
            return true;
        });
        if (!$joined) {
            gmAdd($uid, (float)$g['stake']);
            answerCb(BOT_TOKEN, $cbId, gmT('gone'), true);
            return true;
        }
        answerCb(BOT_TOKEN, $cbId, '✅');
        $g = gmGet($gid);

        if ($g && count($g['players']) >= 2 && ($g['kind'] ?? 'duel') !== 'ttt') {
            $ids = array_values(array_map(fn($p) => (int)$p['id'], $g['players']));
            $win = $ids[random_int(0, count($ids) - 1)];
            $lose = 0;
            foreach ($ids as $i) if ($i !== $win) { $lose = $i; break; }
            gmFinish($g, $win, $lose);
            return true;
        }

        gmShow($g);
        return true;
    }

    $cell = (int)($m[3] ?? -1);
    if ($g['status'] !== 'playing' || $cell < 0 || $cell > 8) {
        answerCb(BOT_TOKEN, $cbId, gmT('gone'), true); return true;
    }
    if (!isset($g['players'][(string)$uid])) { answerCb(BOT_TOKEN, $cbId, gmT('not_yours'), true); return true; }
    if ((int)$g['turn'] !== (int)$uid)       { answerCb(BOT_TOKEN, $cbId, gmT('not_turn'), true); return true; }

    $ids  = array_values(array_map(fn($p) => (int)$p['id'], $g['players']));
    $mark = ((int)$uid === (int)$ids[0]) ? 1 : 2;
    $next = ((int)$uid === (int)$ids[0]) ? $ids[1] : $ids[0];

    $moved = false;
    gmSetGame($gid, function (&$x) use ($cell, $mark, $next, $uid, &$moved) {
        if ((int)$x['turn'] !== (int)$uid) return false;
        if ((int)$x['board'][$cell] !== 0)  return false;
        $x['board'][$cell] = $mark;
        $x['turn'] = (int)$next;
        $x['moved'] = time();
        $moved = true;
        return true;
    });
    if (!$moved) { answerCb(BOT_TOKEN, $cbId, gmT('taken'), true); return true; }
    answerCb(BOT_TOKEN, $cbId);

    $g = gmGet($gid);
    $w = gmWinnerMark($g['board']);
    if ($w !== 0) {
        $winner = (int)$ids[$w - 1];
        $loser  = (int)$ids[$w === 1 ? 1 : 0];
        gmFinish($g, $winner, $loser);
        return true;
    }
    if (gmBoardFull($g['board'])) {
        gmRefund($g, gmT('duel_draw'));
        return true;
    }
    gmShow($g);
    return true;
}


function gmAdminHome($chatId, $msgId = null) {
    $c = gmCfg();
    $open = count(gmOpenOrPlaying());

    $t  = "🎮 <b>بازی‌ها</b>\n\n";
    $t .= 'وضعیت: ' . (gmOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= "🎯 بازی باز: <b>{$open}</b>\n\n";
    $t .= "کلمه‌ها:\n";
    $t .= '• ⚡ چالش (نتیجه‌ی فوری): <code>' . h($c['word_duel']) . " ۱۰۰</code>" .
          (empty($c['duel_on']) ? ' — خاموش' : '') . "\n";
    $t .= '• ⭕ دوز (صفحه‌ی بازی): <code>' . h($c['word_ttt']) . " ۱۰۰</code>" .
          (empty($c['ttt_on']) ? ' — خاموش' : '') . "\n";
    $t .= '• موجودی: <code>' . h($c['word_bal']) . "</code>\n";
    $t .= '• انتقال (ریپلای): <code>' . h($c['word_send']) . " ۱۰۰</code>\n\n";
    $t .= '🧾 مالیات جایزه: <b>' . $c['tax'] . "٪</b>\n";
    $t .= '🧾 مالیات انتقال: <b>' . $c['send_tax'] . "٪</b>\n";
    $t .= '💎 شرط: <b>' . gmNum($c['min']) . '</b> تا <b>' . gmNum($c['max']) . "</b>\n";

    $rows = [
        [btnCb(gmOn() ? '✅ روشن' : '❌ خاموش', 'gmax', 'info')],
        [btnCb('🧾 مالیات جایزه', 'gmatax', 'admin'), btnCb('📤 مالیات انتقال', 'gmastax', 'admin')],
        [btnCb('💎 کف و سقف شرط', 'gmarange', 'admin')],
        [btnCb('🗣 کلمه‌ها', 'gmaw_home', 'admin'), btnCb('✏️ متن‌ها', 'gmat_home', 'admin')],
        [btnCb(!empty($c['duel_on']) ? '⚡ چالش: روشن' : '⚡ چالش: خاموش', 'gmaduel', 'info'),
         btnCb(!empty($c['ttt_on']) ? '⭕ دوز: روشن' : '⭕ دوز: خاموش', 'gmattt', 'info')],
        [btnCb('🔢 ایموجی عددها', 'gmadig', 'admin')],
        [btnCb('🎨 ایموجیِ رنگ‌های دوز', 'gmacolors', 'admin'),
         btnCb('🖌 رنگِ دکمه‌ها', 'gmabcol', 'admin')],
        [btnCb('🔢 سقف بازی باز: ' . gmNum((int)gmVal('open_max', 2)), 'gmaopen', 'admin'),
         btnCb('⏰ مهلت بی‌حریف: ' . gmNum((int)gmVal('expire', 180)) . 'ث', 'gmaexp', 'admin')],
        [btnCb('🧹 بستن بازی‌های باز', 'gmaclose', 'danger')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function gmAdminDigits($chatId, $msgId) {
    $ids = gmDigitIds();
    $t  = "🔢 <b>ایموجی عددها</b>\n\n";
    $t .= "عددِ بازی با این ایموجی‌ها درشت نوشته می‌شود.\n";
    $t .= "کد هر ایموجی پریمیوم را با <code>/emoji</code> در ربات می‌گیرید.\n\n";
    $t .= "نمونه: " . gmBigNum(1234567890) . "\n\n";

    $rows = [];
    $line = [];
    foreach (['1','2','3','4','5','6','7','8','9','0'] as $d) {
        $has = ($ids[$d] ?? '') !== '';
        $line[] = btnCb(($has ? '✅ ' : '⬜️ ') . $d, 'gmad_' . $d, $has ? 'admin' : 'info');
        if (count($line) === 5) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    $rows[] = [btnCb(UT('back'), 'gm_home', 'nav')];

    $miss = [];
    foreach ($ids as $d => $v) if ($v === '') $miss[] = $d;
    if ($miss) $t .= '⬜️ بدون ایموجی (ساده نوشته می‌شود): <b>' . implode('، ', $miss) . "</b>\n";

    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function gmAdminColors($chatId, $msgId) {
    $ics = gmColorIcons();
    $t  = "🎨 <b>ایموجیِ رنگ‌های دوز</b>\n\n";
    $rows = [];
    foreach (gmPalette() as $k => $p) {
        $has = ($ics[$k] ?? '') !== '';
        $t .= gmColorEmoji($k) . ' <b>' . h($p['n']) . '</b>: ' . ($has ? '✅ پریمیوم دارد' : '⬜️ ساده') . "\n";
        $rows[] = [btnCb(gmColorEmoji($k) . ' ' . $p['n'] . ($has ? ' ✅' : ''), 'gmac_' . $k, $has ? 'admin' : 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'gm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function gmLabels() {
    return [
        'duel_how'  => 'چالش — بدون عدد',
        'duel_open' => 'چالش — پیام باز',
        'ttt_how'   => 'دوز — بدون عدد',
        'ttt_open'  => 'دوز — پیام باز',  'ttt_turn' => 'دوز — حین بازی',
        'duel_win'  => 'نتیجه (چالش و دوز)', 'duel_draw' => 'دوز — مساوی',
        'duel_join' => 'دکمه پیوستن',     'duel_cancel' => 'دکمه لغو',
        'bal_pop'   => 'پنجره‌ی موجودی (نتیجه)', 'lbl_wbal' => 'برچسب موجودی برنده',
        'lbl_lbal'  => 'برچسب موجودی بازنده',
        'bal_head'  => 'موجودی — متن',     'bal_btn' => 'موجودی — دکمه',
        'send_ok'   => 'انتقال — موفق',    'send_bal' => 'انتقال — برچسب فرستنده',
        'send_bal2' => 'انتقال — برچسب گیرنده', 'send_how' => 'انتقال — بدون عدد',
        'send_self' => 'انتقال — به خودت', 'off' => 'پیام خاموش بودن',
        'low'       => 'الماس کافی نیست',  'bad_stake' => 'شرط نامعتبر',
        'not_yours' => 'مال تو نیست',
        'not_turn'  => 'نوبت تو نیست',     'taken' => 'خانه پر است',
        'gone'      => 'بازی تمام شده',    'cancelled' => 'بازی لغو شد',
        'group_only'=> 'فقط داخل گروه', 'already' => 'خودت داخل بازی هستی',
        'open_max'  => 'سقف بازی باز',
        'expired'   => 'بی‌حریف — مهلت تمام شد',
        'idle'      => 'بازی نیمه‌کاره (رهاشده)',
        'lbl_cancel_duel' => 'دکمه‌ی «چالش لغو شد»',
        'in_progress' => 'پیامِ «بازی در حال انجام است»',
    ];
}

function gmLabel($k) { return gmLabels()[$k] ?? $k; }

function gmAdminBtnColors($chatId, $msgId) {
    $t = "🖌 <b>رنگِ دکمه‌های بازی</b>\n\n";
    $rows = [];
    foreach (gmBtnKeys() as $k) {
        $color = (string)gmVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(gmLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(gmLabel($k), 'gmabck_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'gm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function gmAdminBtnColorPick($chatId, $msgId, $k) {
    $cur = (string)gmVal('btns.' . $k . '.color', 'none');
    $t = "🖌 رنگِ <b>" . h(gmLabel($k)) . "</b>\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'gmabcv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'gmabcol', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function gmTextsCfg() {
    return [
        'title' => 'متن‌های چالش و دوز',
        'keys'  => array_keys(gmDefaults()['texts']),
        'popup' => ['already', 'bal_pop', 'gone', 'not_turn', 'not_yours', 'taken'],
        'btns'  => gmBtnKeys(),
        'label' => 'gmLabel',
        'value' => function ($k) { return (string)gmVal('texts.' . $k, ''); },
        'cb'    => 'gmat_',
        'edit'  => 'gmats_',
        'back'  => 'gm_home',
    ];
}

function gmAdminWords($chatId, $msgId) {
    $c = gmCfg();
    $t  = "🗣 <b>کلمه‌های بازی</b>\n\nهر کلمه را با ویرگول جدا کنید.\n\n";
    $map = gmWordLabels();
    $rows = [];
    foreach ($map as $k => $lbl) {
        $t .= '• <b>' . h($lbl) . '</b>: <code>' . h((string)$c[$k]) . "</code>\n";
        $rows[] = [btnCb($lbl, 'gmaws_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'gm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function gmWordLabels() {
    return ['word_duel' => '⚡ چالش (نتیجه‌ی فوری)', 'word_ttt' => '⭕ دوز (صفحه‌ی بازی)',
            'word_bal' => 'موجودی', 'word_send' => 'انتقال'];
}

function gmAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'gm')) return false;

    if ($data === 'gm_home') { answerCb(BOT_TOKEN, $cbId); gmAdminHome($chatId, $msgId); return true; }
    if ($data === 'gmax') {
        gmSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); gmAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'gmaclose') {
        $n = 0;
        foreach (gmOpenOrPlaying() as $g) { gmRefund($g, gmT('cancelled')); $n++; }
        answerCb(BOT_TOKEN, $cbId, "🧹 {$n} بازی بسته شد", true);
        gmAdminHome($chatId, $msgId);
        return true;
    }
    if ($data === 'gmaopen') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'gm_openmax', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🔢 هر نفر هم‌زمان چند بازیِ باز داشته باشد؟\n\nالان: <b>" .
            gmNum((int)gmVal('open_max', 2)) . "</b>",
            inlineKb([[btnUI('cancel', 'gm_home', 'cancel')]]));
        return true;
    }
    if ($data === 'gmaexp') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'gm_expire', []);
        sendMsg(BOT_TOKEN, $chatId,
            "⏰ بازیِ بی‌حریف بعد از چند ثانیه خودکار لغو شود؟\n\n" .
            "شرط همان لحظه به سازنده برمی‌گردد.\nالان: <b>" .
            gmNum((int)gmVal('expire', 180)) . "</b> ثانیه",
            inlineKb([[btnUI('cancel', 'gm_home', 'cancel')]]));
        return true;
    }
    if ($data === 'gmadig') { answerCb(BOT_TOKEN, $cbId); gmAdminDigits($chatId, $msgId); return true; }
    if ($data === 'gmaduel' || $data === 'gmattt') {
        $k = $data === 'gmaduel' ? 'duel_on' : 'ttt_on';
        $on = !empty(gmVal($k, true));
        gmSet(function (&$c) use ($k, $on) { $c[$k] = !$on; });
        answerCb(BOT_TOKEN, $cbId, '✅'); gmAdminHome($chatId, $msgId); return true;
    }
    if (str_starts_with($data, 'gmad_')) {
        $d = substr($data, 5);
        if (!ctype_digit($d) || strlen($d) !== 1) { answerCb(BOT_TOKEN, $cbId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'gm_digit', ['d' => $d]);
        sendMsg(BOT_TOKEN, $chatId,
            "🔢 <b>" . h($d) . "</b>\n\n" .
            "فقط عدد. با <code>/emoji</code> در ربات می‌گیریدش.\n" .
            "<code>-</code>",
            inlineKb([[btnUI('cancel', 'gmadig', 'cancel')]]));
        return true;
    }
    if ($data === 'gmacolors') { answerCb(BOT_TOKEN, $cbId); gmAdminColors($chatId, $msgId); return true; }
    if ($data === 'gmabcol')   { answerCb(BOT_TOKEN, $cbId); gmAdminBtnColors($chatId, $msgId); return true; }
    if (preg_match('/^gmabck_(\w+)$/', $data, $m) && in_array($m[1], gmBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); gmAdminBtnColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^gmabcv_(\w+)_(\w+)$/', $data, $m) && in_array($m[1], gmBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        gmSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); gmAdminBtnColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (str_starts_with($data, 'gmac_')) {
        $c = substr($data, 5);
        if (!isset(gmPalette()[$c])) { answerCb(BOT_TOKEN, $cbId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'gm_coloricon', ['c' => $c]);
        sendMsg(BOT_TOKEN, $chatId,
            "🎨 <b>" . h(gmPalette()[$c]['n']) . "</b>\n\n" .
            "<code>-</code>",
            inlineKb([[btnUI('cancel', 'gmacolors', 'cancel')]]));
        return true;
    }

    if ($data === 'gmaw_home') { answerCb(BOT_TOKEN, $cbId); gmAdminWords($chatId, $msgId); return true; }
    if (txRoute(gmTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;

    $asks = [
        'gmatax'   => ['gm_tax',   "🧾 چند درصد از جایزه به‌عنوان مالیات کم شود؟ (۰ تا ۹۰)"],
        'gmastax'  => ['gm_stax',  "📤 چند درصد مالیات روی انتقال الماس؟ (۰ تا ۹۰)"],
        'gmarange' => ['gm_range', "💎 کف و سقف · <code>10-1000000</code>"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'gm_home', 'cancel')]]));
        return true;
    }
    foreach (['gmats_' => ['gm_text', 'texts.'], 'gmaws_' => ['gm_word', '']] as $pre => [$act, $path]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if ($act === 'gm_word' && !isset(gmWordLabels()[$k])) { answerCb(BOT_TOKEN, $cbId); gmAdminWords($chatId, $msgId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['k' => $k]);
        $cur = (string)gmVal($path . $k, '');
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ <b>" . h($act === 'gm_word' ? gmWordLabels()[$k] : gmLabel($k)) . "</b>\n\n" .
            ($act === 'gm_text' && !gmIsBtn($k) && gmVars($k)
                ? implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', gmVars($k))) . "\n\n"
                : '') .
            "الان:\n" . ($act === 'gm_text' ? $cur : '<code>' . h($cur) . '</code>'),
            inlineKb([[btnCb('انصراف', 'gm_home', 'cancel')]]));
        return true;
    }
    return false;
}

function gmVars($k) {
    if (str_starts_with($k, 'duel_win'))
        return ['winner', 'loser', 'wname', 'lname', 'prize', 'tax', 'stake'];
    if ($k === 'ttt_turn')                return ['emoji', 'stake', 'p1', 'p2', 'prize', 'turn'];
    if ($k === 'duel_open' || $k === 'ttt_open')
        return ['emoji', 'stake', 'stake_big', 'host', 'prize', 'prize_big', 'tax', 'tax_big'];
    if (str_starts_with($k, 'bal_'))      return ['emoji', 'points'];
    if (str_starts_with($k, 'send_ok'))   return ['from', 'to', 'amount', 'tax', 'total', 'fbal', 'tbal'];
    if ($k === 'low')                     return ['points', 'need'];
    if ($k === 'bad_stake')               return ['min', 'max'];
    if ($k === 'bal_pop')                 return ['name', 'points'];
    if ($k === 'send_how')                return ['word'];
    if ($k === 'duel_how' || $k === 'ttt_how') return ['word', 'min', 'max'];
    return [];
}

function gmStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'gm_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('🎮 بازی‌ها', 'gm_home', 'admin')]]);

    if ($action === 'gm_expire') {
        $n = (int)str_replace([',', '،'], '', norm_fa_digits($text));
        if ($n < 30 || $n > 86400) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۳۰ تا ۸۶۴۰۰ ثانیه."); return true; }
        gmSet(function (&$c) use ($n) { $c['expire'] = $n; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ مهلت بی‌حریف: ' . gmNum($n) . ' ثانیه', $back);
        return true;
    }

    if ($action === 'gm_openmax') {
        $n = (int)str_replace([',', '،'], '', norm_fa_digits($text));
        if ($n < 1 || $n > 50) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا ۵۰."); return true; }
        gmSet(function (&$c) use ($n) { $c['open_max'] = $n; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, '✅ سقف بازی باز: ' . gmNum($n), $back);
        return true;
    }

    if ($action === 'gm_digit') {
        $d = (string)($sd['d'] ?? '');
        if ($d === '' || !ctype_digit($d)) { clearState($uid); return true; }
        $v = ($text === '-' || $text === '—') ? '' : preg_replace('/\D+/', '', norm_fa_digits($text));
        if ($v !== '' && strlen($v) < 10) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ کد ایموجی معتبر نیست — باید یک عدد بلند باشد.");
            return true;
        }
        gmSet(function (&$c) use ($d, $v) {
            if (!is_array($c['digits'] ?? null)) $c['digits'] = [];
            $c['digits'][$d] = $v;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            ($v === '' ? "✅ ایموجی رقم {$d} برداشته شد." : "✅ رقم {$d} → " . gmBigNum($d)),
            inlineKb([[btnCb('🔢 ایموجی عددها', 'gmadig', 'admin')]]));
        return true;
    }

    if ($action === 'gm_coloricon') {
        $c = (string)($sd['c'] ?? '');
        if ($c === '' || !isset(gmPalette()[$c])) { clearState($uid); return true; }
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $dash = ($text === '-' || $text === '—');
        $v = $ids ? (string)$ids[0] : ($dash ? '' : preg_replace('/\D/', '', norm_fa_digits($text)));
        if (!$dash && $v === '') {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ایموجی پیدا نشد.\n" .
                "<code>-</code>");
            return true;
        }
        gmSet(function (&$c2) use ($c, $v) {
            if (!is_array($c2['color_icons'] ?? null)) $c2['color_icons'] = [];
            $c2['color_icons'][$c] = $v;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            ($v === '' ? '✅ حذف شد.' : '✅ ثبت شد.'),
            inlineKb([[btnCb('🎨 ایموجیِ رنگ‌های دوز', 'gmacolors', 'admin')]]));
        return true;
    }

    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'gm_tax' || $action === 'gm_stax') {
        $v = (float)norm_fa_digits($text);
        if ($v < 0 || $v > 90) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۰ تا ۹۰ باشد."); return true; }
        $k = $action === 'gm_tax' ? 'tax' : 'send_tax';
        gmSet(function (&$c) use ($k, $v) { $c[$k] = $v; });
        return $done();
    }
    if ($action === 'gm_range') {
        if (!preg_match('/^\s*([\d,٬]+)\s*[-–ـ]\s*([\d,٬]+)\s*$/u', norm_fa_digits($text), $m)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ <code>10-1000000</code>"); return true;
        }
        $lo = (float)str_replace([',', '٬'], '', $m[1]);
        $hi = (float)str_replace([',', '٬'], '', $m[2]);
        if ($lo < 1 || $hi <= $lo) { sendMsg(BOT_TOKEN, $chatId, "⚠️ سقف باید از کف بزرگ‌تر باشد."); return true; }
        gmSet(function (&$c) use ($lo, $hi) { $c['min'] = $lo; $c['max'] = $hi; });
        return $done();
    }
    if ($action === 'gm_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ چیزی برای ذخیره نیست."); return true; }

        if (gmIsBtn($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            gmSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد",
                inlineKb([[gmBtn($k, ['points' => gmNum(12345)], 'gmnop', 'primary')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        gmSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد. پیش‌نمایش:");
        sendMsg(BOT_TOKEN, $chatId, gmPreview($k), $back);
        return true;
    }
    if ($action === 'gm_word') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '' || $text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی نمی‌شود."); return true; }
        gmSet(function (&$c) use ($k, $text) { $c[$k] = $text; });
        return $done();
    }
    clearState($uid);
    return true;
}

function gmPreview($k) {
    $sample = ['emoji' => gmEmoji(), 'stake' => gmNum(100), 'host' => '@host',
               'prize' => gmNum(180), 'tax' => gmNum(20), 'p1' => '@blue', 'p2' => '@green',
               'turn' => '@blue', 'count' => gmNum(3), 'left' => gmNum(42),
               'winner' => '8961325161', 'loser' => '8277251947',
               'wname' => '@winner', 'lname' => '@loser', 'points' => gmNum(30860),
               'need' => gmNum(100), 'min' => gmNum(10), 'max' => gmNum(1000000),
               'from' => '@a', 'to' => '@b', 'amount' => gmNum(100), 'total' => gmNum(110),
               'word' => gmVal('word_send', 'انتقال')];
    return gmT($k, $sample);
}

<?php
defined('NB_ROOT') || exit;


function mnDefaults() {
    return [
        'on'         => false,
        'group_only' => 1,
        'word'       => 'مین',

        'entry_min' => 10,
        'entry_max' => 1000000,

        'min_safe_for_protection' => 3,
        'rewards' => [100, 150, 250, 400, 600, 900, 1300, 1900],
        'reward_growth' => 1.5,
        'house_edge'    => 5,

        'max_active_games' => 200,
        'waiting_timeout'  => 60,
        'game_timeout'     => 1800,
        'expire_refund'    => 0,
        'game_cooldown'    => 0,

        'icons' => ['btn_field' => '', 'btn_join' => '', 'btn_cancel' => '', 'btn_cash' => '',
                    'cell_hidden' => '', 'cell_lock' => '', 'cell_gem' => '',
                    'cell_mine' => '', 'cell_empty' => ''],
        'btns'  => [
            'btn_field'  => ['color' => 'none'],
            'btn_join'   => ['color' => 'success'],
            'btn_cancel' => ['color' => 'danger'],
            'btn_cash'   => ['color' => 'success'],
            'cell_hidden' => ['color' => 'none'],
            'cell_lock'   => ['color' => 'none'],
            'cell_gem'    => ['color' => 'success'],
            'cell_mine'   => ['color' => 'danger'],
            'cell_empty'  => ['color' => 'none'],
        ],

        'texts' => [
            'btn_field'  => '💣 MINE FIELD',
            'btn_join'   => '🎮 پیوستن',
            'btn_cancel' => '❌ لغو',
            'btn_cash'   => '💰 برداشت جایزه',

            'cell_hidden' => '⬛️',
            'cell_lock'   => '🔒',
            'cell_gem'    => '💎',
            'cell_mine'   => '💥',
            'cell_empty'  => '⬜️',

            'preview' => "◈━━━━━━━━━━━━━━◈\n" .
                         "   💣 <b>M I N E   F I E L D</b>\n" .
                         "◈━━━━━━━━━━━━━━◈\n\n" .
                         "🎰 یک میدانِ ۳×۳ — هشت الماس، یک مین.\n\n" .
                         "┌─────────────\n" .
                         "│ 💰 ورودی    <code>{entry}</code>\n" .
                         "│ 🎯 خانه‌ی امن  جایزه بیشتر\n" .
                         "│ 💣 مین        بازی تمام\n" .
                         "└─────────────\n\n" .
                         "<b>«پیوستن»</b>",
            'need_join' => "🎮 «پیوستن»",
            'low_wallet' => "❌ <b>INSUFFICIENT DIAMONDS</b>\n\nبرای شروعِ بازی به:\n\n💎 {need} Diamond\n\nنیاز دارید.\n👛 موجودیِ شما: <b>{wallet}</b>",
            'pop_low'    => "❌ الماس کافی نداری — {need} الماس لازم است.",
            'bad_amount' => "❌ مبلغ باید بینِ {min} تا {max} باشد.",
            'busy'       => "⏳ یک بازیِ باز دارید.",
            'cooldown'   => "⏳ چند لحظه صبر",
            'full'       => "⏳ ظرفیتِ بازی‌های فعال پر است.",
            'not_yours'  => "❌ این بازی متعلق به شما نیست.",
            'gone'       => "این بازی دیگر در دسترس نیست.",
            'already'    => "این خانه قبلا انتخاب شده.",

            'active' => "◈━━━━━━━━━━━━━━◈\n" .
                        "   💣 <b>M I N E   F I E L D</b>\n" .
                        "◈━━━━━━━━━━━━━━◈\n\n" .
                        "👤 <b>{name}</b>\n" .
                        "🎖 مرحله: <b>{picks}</b> از ۸\n" .
                        "{progress}\n\n" .
                        "┌─────────────\n" .
                        "│ 💰 ورودی    <code>{entry}</code>\n" .
                        "│ 💎 جایزه    <b>{reward}</b>\n" .
                        "│ 📈 ضریب     <b>{mult}×</b>\n" .
                        "│ 🛡 حفاظت    {shield}\n" .
                        "└─────────────\n\n" .
                        "🎯 خانه‌ی بعدی: <b>+{next}</b>",

            'cancelled' => "❌ بازی لغو شد. چیزی از شما کم نشد.",

            'lost' => "💥💥💥  <b>B O O M</b>  💥💥💥\n" .
                      "◈━━━━━━━━━━━━━━◈\n\n" .
                      "💣 روی مین زدی.\n\n" .
                      "┌─────────────\n" .
                      "│ 🎯 خانه‌های امن  <b>{picks}</b>\n" .
                      "│ 💸 از دست رفت   <b>{entry}</b>\n" .
                      "└─────────────",

            'protected' => "🛡 <b>سپر فعال شد!</b>\n" .
                           "◈━━━━━━━━━━━━━━◈\n\n" .
                           "💥 روی مین زدی — بعد از <b>{picks}</b> خانه‌ی امن.\n\n" .
                           "┌─────────────\n" .
                           "│ 💸 ورودی و جایزه‌ی این دور برنگشت\n" .
                           "│ 🎯 خانه‌های امن  <b>{picks}</b>\n" .
                           "└─────────────",

            'cashout' => "🏆✨ <b>نقد کردی!</b> ✨🏆\n" .
                         "◈━━━━━━━━━━━━━━◈\n\n" .
                         "┌─────────────\n" .
                         "│ 💎 جایزه   <b>+{reward}</b>\n" .
                         "│ 👛 موجودی  <b>{wallet}</b>\n" .
                         "└─────────────",

            'expired' => "⏰ <b>GAME EXPIRED</b>\n\nبازی منقضی شد.",

            'gameover_head' => "💣 <b>GAME OVER</b>\n\n",
        ],
    ];
}

function mnCfg() {
    $c = cfg()['mine'] ?? null;
    return is_array($c) ? array_replace_recursive(mnDefaults(), $c) : mnDefaults();
}

function mnSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['mine'] ?? null)) $c['mine'] = mnDefaults();
        $fn($c['mine']);
    });
}

function mnVal($path, $default = null) {
    $v = mnCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function mnOn() { return !empty(mnVal('on')); }

function mnIsButtonKey($slug) {
    return in_array($slug, ['btn_field', 'btn_join', 'btn_cancel', 'btn_cash'], true);
}

function mnT($slug, $vars = [], $fill = false) {
    $t = (string)mnVal('texts.' . $slug, mnDefaults()['texts'][$slug] ?? $slug);
    if ($fill) return tplFill($t, $vars);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function mnBtn($key, $vars, $data) {
    $b = ['callback_data' => $data];
    $color = (string)mnVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $b = function_exists('btnApplyLabel')
        ? btnApplyLabel($b, mnT($key, $vars), mnVal('icons.' . $key, ''))
        : ['text' => strip_tags((string)(mnT($key, $vars)))] + $b;
    return $b;
}

function mnNum($n) { return number_format((float)$n, 0, '.', ','); }


function mineDbPath() { return DATA_DIR . '/mine_games.sqlite'; }

function mineDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = mineDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[mine] mine_games.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS mine_games (
        id TEXT PRIMARY KEY, user_id INTEGER NOT NULL, status TEXT NOT NULL,
        started INTEGER NOT NULL, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_mine_user   ON mine_games(user_id, status)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_mine_status ON mine_games(status, started)');
    return $db;
}

function mnGet($id) {
    $db = mineDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM mine_games WHERE id = :id');
    $stmt->bindValue(':id', (string)$id, SQLITE3_TEXT);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function mnSetGame($id, callable $fn) {
    $db = mineDb();
    if (!$db) return null;
    $id = (string)$id;

    if (!@$db->exec('BEGIN IMMEDIATE')) return null;
    try {
        $stmt = $db->prepare('SELECT data FROM mine_games WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $g = $row ? json_decode($row['data'], true) : null;
        if (!is_array($g)) { $db->exec('ROLLBACK'); return null; }

        $result = $fn($g);

        $up = $db->prepare('UPDATE mine_games SET data = :data, status = :status WHERE id = :id');
        $up->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        $up->bindValue(':status', (string)($g['status'] ?? ''), SQLITE3_TEXT);
        $up->bindValue(':id', $id, SQLITE3_TEXT);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
        return $result;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[mine] mnSetGame خطا: ' . $e->getMessage());
        return null;
    }
}

function mnCreate($uid, $chatId, $name, $uname, $entry) {
    $db = mineDb();
    if (!$db) return null;
    $id = 'm_' . bin2hex(random_bytes(6));
    $now = time();
    $g = [
        'id' => $id, 'user_id' => (int)$uid, 'chat_id' => $chatId, 'msg_id' => 0,
        'name' => $name, 'username' => $uname,
        'entry' => (float)$entry,
        'mine_pos' => random_int(1, 9),
        'selected' => [], 'safe_picks' => 0, 'reward' => 0.0,
        'status' => 'waiting',
        'started_at' => $now, 'joined_at' => 0, 'finished_at' => 0, 'updated_at' => $now,
    ];
    $stmt = $db->prepare('INSERT INTO mine_games (id, user_id, status, started, data) VALUES (:id, :uid, :st, :t, :data)');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
    $stmt->bindValue(':st', 'waiting', SQLITE3_TEXT);
    $stmt->bindValue(':t', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':data', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
    $stmt->execute();
    return $g;
}

function mnActiveCountForUser($uid) {
    $db = mineDb();
    if (!$db) return 0;
    $stmt = $db->prepare("SELECT COUNT(*) c FROM mine_games WHERE user_id = :uid AND status IN ('waiting','active')");
    $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    return (int)($row['c'] ?? 0);
}

function mnActiveCountAll() {
    $db = mineDb();
    if (!$db) return 0;
    return (int)$db->querySingle("SELECT COUNT(*) FROM mine_games WHERE status = 'active'");
}

function mnCooldownLeft($uid) {
    $secs = max(0, (int)mnVal('game_cooldown', 0));
    if ($secs <= 0) return 0;
    $db = mineDb();
    if (!$db) return 0;
    $stmt = $db->prepare("SELECT data FROM mine_games WHERE user_id = :uid AND status NOT IN ('waiting','active') ORDER BY started DESC LIMIT 1");
    $stmt->bindValue(':uid', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return 0;
    $d = json_decode($row['data'], true);
    $fin = (int)($d['finished_at'] ?? 0);
    if ($fin <= 0) return 0;
    $left = $fin + $secs - time();
    return max(0, $left);
}


function mnRewardStep($pickNumber) {
    $arr = mnVal('rewards', mnDefaults()['rewards']);
    if (!is_array($arr) || !$arr) $arr = mnDefaults()['rewards'];
    $arr = array_values($arr);
    $n = count($arr);
    if ($pickNumber <= $n) return (float)$arr[$pickNumber - 1];
    $growth = max(1.0, (float)mnVal('reward_growth', 1.5));
    $extra  = $pickNumber - $n;
    return round((float)$arr[$n - 1] * pow($growth, $extra), 2);
}

function mnCumReward($safePicks) {
    $sum = 0.0;
    for ($i = 1; $i <= $safePicks; $i++) $sum += mnRewardStep($i);
    return $sum;
}

// The configured prize table is fixed (it does not grow with the entry), so a
// 10-diamond entry could win 100 on the first pick ~89% of the time — free diamonds
// on repeat. The prize is therefore capped at the fair payout for this entry
// (1 mine in 9 cells => 9/(9-k) after k safe picks) minus the house edge.
function mnRewardFor($entry, $safePicks) {
    $k = max(0, min(8, (int)$safePicks));
    if ($k === 0) return 0.0;
    $edge = max(0.0, min(50.0, (float)mnVal('house_edge', 5))) / 100;
    $fair = (float)$entry * (1 - $edge) * 9 / (9 - $k);
    return (float)floor(min(mnCumReward($k), $fair));
}


function mnFieldRows($g, $revealAll = false) {
    $sel  = $g['selected'] ?? [];
    $mine = (int)($g['mine_pos'] ?? 0);
    $isPreview = ($g['status'] ?? '') === 'waiting';
    $rows = [];
    for ($r = 0; $r < 3; $r++) {
        $row = [];
        for ($c = 1; $c <= 3; $c++) {
            $pos = $r * 3 + $c;
            if ($revealAll && $pos === $mine) {
                $row[] = mnBtn('cell_mine', [], 'mn_done');
            } elseif (in_array($pos, $sel, true)) {
                $row[] = mnBtn('cell_gem', [], 'mn_done');
            } elseif ($revealAll) {
                $row[] = mnBtn('cell_empty', [], 'mn_done');
            } elseif ($isPreview) {
                $row[] = mnBtn('cell_lock', [], 'mn_pre_' . $g['id']);
            } else {
                $row[] = mnBtn('cell_hidden', [], 'mn_pick_' . $g['id'] . '_' . $pos);
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

function mnPreviewKb($g) {
    $rows = [[mnBtn('btn_field', [], 'mn_nop')]];
    foreach (mnFieldRows($g) as $r) $rows[] = $r;
    $rows[] = [mnBtn('btn_cancel', [], 'mn_cancel_' . $g['id']), mnBtn('btn_join', [], 'mn_join_' . $g['id'])];
    return inlineKb($rows);
}

function mnActiveKb($g) {
    $rows = [[mnBtn('btn_field', [], 'mn_nop')]];
    foreach (mnFieldRows($g) as $r) $rows[] = $r;
    if ((int)($g['safe_picks'] ?? 0) >= 1) $rows[] = [mnBtn('btn_cash', [], 'mn_cash_' . $g['id'])];
    return inlineKb($rows);
}

function mnFinishedKb($g) {
    $rows = [];
    foreach (mnFieldRows($g, true) as $r) $rows[] = $r;
    return inlineKb($rows);
}

function mnPreviewText($g) { return mnT('preview', ['entry' => mnNum($g['entry'])]); }

function mnProgressBar($picks) {
    $picks = max(0, min(8, (int)$picks));
    return str_repeat('🟩', $picks) . str_repeat('⬜️', 8 - $picks);
}

function mnActiveText($g) {
    $picks  = (int)($g['safe_picks'] ?? 0);
    $entry  = (float)($g['entry'] ?? 0);
    $reward = (float)($g['reward'] ?? 0);

    $next = $picks < 8 ? max(0, mnRewardFor($entry, $picks + 1) - $reward) : 0;
    $mult = $entry > 0 ? round($reward / $entry, 2) : 0;

    $need   = max(0, (int)mnVal('min_safe_for_protection', 3));
    $shield = $picks >= $need
        ? '✅ فعال'
        : '🔓 ' . max(0, $need - $picks) . ' خانه‌ی دیگر';

    return mnT('active', [
        'name'     => h($g['name'] ?? ''),
        'entry'    => mnNum($entry),
        'reward'   => mnNum($reward),
        'picks'    => $picks,
        'progress' => mnProgressBar($picks),
        'mult'     => $mult,
        'shield'   => $shield,
        'next'     => mnNum($next),
        'hint'     => '',
    ], true);
}

function mnRender($g, $chatId, $msgId = null) {
    $text = $g['status'] === 'waiting' ? mnPreviewText($g) : mnActiveText($g);
    $kb   = $g['status'] === 'waiting' ? mnPreviewKb($g) : mnActiveKb($g);
    if ($msgId) { editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb); return (int)$msgId; }
    $r = sendMsg(BOT_TOKEN, $chatId, $text, $kb);
    return (int)($r['result']['message_id'] ?? 0);
}


function mnParse($raw) {
    $t = trim(norm_fa_digits((string)$raw));
    foreach (explode(',', (string)mnVal('word', 'مین')) as $w) {
        $w = trim($w);
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

function mnBareWord($raw) {
    $t = mb_strtolower(trim(norm_fa_digits((string)$raw)));
    foreach (explode(',', (string)mnVal('word', 'مین')) as $w) {
        $w = trim($w);
        if ($w !== '' && $t === mb_strtolower($w)) return true;
    }
    return false;
}

function mnHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!mnOn()) return false;
    if (!empty(mnVal('group_only', 1)) && $isPrivate) return false;
    $raw = trim((string)$text);
    if ($raw === '' || mb_strlen($raw) > 40) return false;

    if (!function_exists('tickDue') || tickDue('mn', 20)) mnTick(3);
    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];

    $min = max(1, (float)mnVal('entry_min', 10));
    $max = max($min, (float)mnVal('entry_max', 1000000));

    $entry = mnParse($raw);
    if ($entry === null) {
        if (mnBareWord($raw)) {
            $w = trim(explode(',', (string)mnVal('word', 'مین'))[0]) ?: 'مین';
            sendMsg(BOT_TOKEN, $chatId, mnT('bad_amount', ['min' => mnNum($min), 'max' => mnNum($max)])
                . "\n<code>" . h($w) . ' ' . mnNum($min) . "</code>", null, $extra);
            return true;
        }
        return false;
    }

    if ($entry < $min || $entry > $max) {
        sendMsg(BOT_TOKEN, $chatId, mnT('bad_amount', ['min' => mnNum($min), 'max' => mnNum($max)]), null, $extra);
        return true;
    }
    if (mnActiveCountForUser($uid) > 0) {
        sendMsg(BOT_TOKEN, $chatId, mnT('busy'), null, $extra);
        return true;
    }
    if (mnCooldownLeft($uid) > 0) {
        sendMsg(BOT_TOKEN, $chatId, mnT('cooldown'), null, $extra);
        return true;
    }
    if (function_exists('gmPoints') && gmPoints($uid) < $entry) {
        sendMsg(BOT_TOKEN, $chatId, mnT('low_wallet', ['need' => mnNum($entry), 'wallet' => mnNum(gmPoints($uid))]), null, $extra);
        return true;
    }

    $g = mnCreate($uid, $chatId, $name, $uname, $entry);
    if (!$g) return true;
    $mid = mnRender($g, $chatId, null);
    if ($mid) mnSetGame($g['id'], function (&$x) use ($mid) { $x['msg_id'] = (int)$mid; return true; });
    return true;
}



function mnExpireIfNeeded(&$g) {
    if (!in_array($g['status'], ['waiting', 'active'], true)) return false;
    $timeout = $g['status'] === 'waiting'
        ? max(10, (int)mnVal('waiting_timeout', 60))
        : max(60, (int)mnVal('game_timeout', 1800));
    if (time() - (int)$g['started_at'] < $timeout) return false;

    if ($g['status'] === 'active' && !empty(mnVal('expire_refund', 0))) {
        gmAdd((int)$g['user_id'], (float)$g['entry'], $g['name'] ?? '', $g['username'] ?? '');
    }
    $g['status'] = 'expired';
    $g['finished_at'] = time();
    return true;
}

function mnCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if ($data === 'mn_nop' || $data === 'mn_done') { answerCb(BOT_TOKEN, $cbId); return true; }
    if (!preg_match('/^mn_(pre|join|cancel|cash|pick)_(m_[0-9a-f]+)(?:_(\d))?$/', (string)$data, $m)) return false;
    if (!mnOn()) { answerCb(BOT_TOKEN, $cbId); return true; }

    [$all, $act, $gid, $pos] = array_pad($m, 4, null);
    $pos = $pos !== null ? (int)$pos : 0;

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    $g = mnGet($gid);
    if (!$g) { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }
    if ((int)$g['user_id'] !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, mnT('not_yours'), true); return true; }

    if ($act === 'pre') { answerCb(BOT_TOKEN, $cbId, mnT('need_join'), true); return true; }

    if ($act === 'cancel') {
        $out = mnSetGame($gid, function (&$g) {
            if ($g['status'] !== 'waiting') return 'gone';
            $g['status'] = 'cancelled'; $g['finished_at'] = time();
            return 'ok';
        });
        answerCb(BOT_TOKEN, $cbId, $out === 'ok' ? '' : mnT('gone'), $out !== 'ok');
        if ($out === 'ok') editMsg(BOT_TOKEN, $chatId, $msgId, mnT('cancelled'));
        return true;
    }

    if ($act === 'join') {
        $entry = (float)$g['entry'];
        $res = null;
        if ($g['status'] !== 'waiting') {
            $res = 'gone';
        } elseif (mnActiveCountAll() >= max(1, (int)mnVal('max_active_games', 200))) {
            $res = 'full';
        } elseif (!gmAdd($uid, -$entry, $name, $uname)) {
            $res = 'low';
        } else {
            $out = mnSetGame($gid, function (&$g) use ($name, $uname) {
                if ($g['status'] !== 'waiting') return 'gone';
                $g['status'] = 'active'; $g['joined_at'] = time(); $g['updated_at'] = time();
                if ($name !== '')  $g['name'] = $name;
                if ($uname !== '') $g['username'] = $uname;
                return 'ok';
            });
            if ($out !== 'ok') { gmAdd($uid, $entry, $name, $uname); $res = $out ?: 'gone'; }
            else $res = 'ok';
        }

        if ($res === 'low')  { answerCb(BOT_TOKEN, $cbId, mnT('pop_low', ['need' => mnNum($entry), 'wallet' => mnNum(gmPoints($uid))]), true); return true; }
        if ($res === 'full') { answerCb(BOT_TOKEN, $cbId, mnT('full'), true); return true; }
        if ($res !== 'ok')   { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }

        answerCb(BOT_TOKEN, $cbId, '🎮');
        $g2 = mnGet($gid);
        mnRender($g2, $chatId, $msgId);
        return true;
    }

    if ($act === 'cash') {
        $out = mnSetGame($gid, function (&$g) {
            if ($g['status'] !== 'active') return ['err' => 'gone'];
            if ((int)($g['safe_picks'] ?? 0) < 1) return ['err' => 'gone'];
            $g['status'] = 'cashed_out'; $g['finished_at'] = time(); $g['updated_at'] = time();
            return ['ok' => true, 'reward' => $g['reward']];
        });
        if (empty($out['ok'])) { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }
        gmAdd($uid, (float)$out['reward'], $name, $uname);
        answerCb(BOT_TOKEN, $cbId, '🏆');
        $g2 = mnGet($gid);
        editMsg(BOT_TOKEN, $chatId, $msgId,
            mnT('cashout', ['reward' => mnNum($out['reward']), 'wallet' => mnNum(gmPoints($uid))]),
            mnFinishedKb($g2));
        return true;
    }

    if ($act === 'pick') {
        if ($pos < 1 || $pos > 9) { answerCb(BOT_TOKEN, $cbId); return true; }

        $out = mnSetGame($gid, function (&$g) use ($pos) {
            if ($g['status'] !== 'active') return ['err' => 'gone'];
            if (in_array($pos, $g['selected'] ?? [], true)) return ['err' => 'already'];

            $g['updated_at'] = time();
            if ($pos === (int)$g['mine_pos']) {
                $minSafe = max(0, (int)mnVal('min_safe_for_protection', 3));
                $picks = (int)($g['safe_picks'] ?? 0);
                if ($picks >= $minSafe) {
                    $g['status'] = 'protected_hit';
                } else {
                    $g['status'] = 'lost';
                }
                $g['finished_at'] = time();
                return ['err' => null, 'tier' => $g['status'], 'picks' => $picks, 'entry' => $g['entry']];
            }

            $g['selected'][] = $pos;
            $g['safe_picks'] = (int)($g['safe_picks'] ?? 0) + 1;
            $g['reward'] = mnRewardFor((float)$g['entry'], $g['safe_picks']);

            if ($g['safe_picks'] >= 8) {
                $g['status'] = 'won'; $g['finished_at'] = time();
                return ['err' => null, 'tier' => 'won', 'reward' => $g['reward']];
            }

            return ['err' => null, 'tier' => 'safe'];
        });

        if (!$out) { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }
        if ($out['err'] === 'already') { answerCb(BOT_TOKEN, $cbId, mnT('already'), true); return true; }
        if ($out['err'] === 'gone') { answerCb(BOT_TOKEN, $cbId, mnT('gone'), true); return true; }

        $g2 = mnGet($gid);

        if ($out['tier'] === 'safe') {
            answerCb(BOT_TOKEN, $cbId, '💎');
            mnRender($g2, $chatId, $msgId);
            return true;
        }
        if ($out['tier'] === 'won') {
            gmAdd($uid, (float)$out['reward'], $name, $uname);
            answerCb(BOT_TOKEN, $cbId, '🏆');
            editMsg(BOT_TOKEN, $chatId, $msgId,
                mnT('cashout', ['reward' => mnNum($out['reward']), 'wallet' => mnNum(gmPoints($uid))]),
                mnFinishedKb($g2));
            return true;
        }
        if ($out['tier'] === 'lost') {
            answerCb(BOT_TOKEN, $cbId, '💣', true);
            editMsg(BOT_TOKEN, $chatId, $msgId,
                mnT('gameover_head') . mnT('lost', ['picks' => $out['picks'], 'entry' => mnNum($out['entry'])]),
                mnFinishedKb($g2));
            return true;
        }
        if ($out['tier'] === 'protected_hit') {
            answerCb(BOT_TOKEN, $cbId, '🛡️');
            editMsg(BOT_TOKEN, $chatId, $msgId,
                mnT('gameover_head') . mnT('protected', ['picks' => $out['picks']]),
                mnFinishedKb($g2));
            return true;
        }
        answerCb(BOT_TOKEN, $cbId);
        return true;
    }

    return false;
}


function mnTick($limit = 50) {
    $db = mineDb();
    if (!$db) return 0;
    $done = 0;
    $now = time();
    $waitTimeout = max(10, (int)mnVal('waiting_timeout', 60));
    $gameTimeout = max(60, (int)mnVal('game_timeout', 1800));

    $res = $db->query(
        "SELECT id FROM mine_games WHERE " .
        "(status = 'waiting' AND started <= " . ($now - $waitTimeout) . ") OR " .
        "(status = 'active'  AND started <= " . ($now - $gameTimeout) . ") " .
        "LIMIT " . (int)$limit
    );
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $gid = $row['id'];
        $out = mnSetGame($gid, function (&$g) {
            $changed = mnExpireIfNeeded($g);
            return $changed ? $g : null;
        });
        if (!$out) continue;
        $done++;
        if (!empty($out['msg_id'])) {
            editMsg(BOT_TOKEN, $out['chat_id'], (int)$out['msg_id'], mnT('expired'));
        }
    }
    return $done;
}


function mnLabels() {
    return [
        'cell_hidden' => 'خانه: بسته', 'cell_lock' => 'خانه: قفل (پیش از شروع)',
        'cell_gem' => 'خانه: الماسِ پیداشده', 'cell_mine' => 'خانه: مین',
        'cell_empty' => 'خانه: بازنشده (بعدِ پایان)',
        'preview' => 'پیش‌نمایشِ بازی', 'need_join' => 'یادآوریِ پیوستن',
        'low_wallet' => '💎 کمبود موجودی (وقتی «مین ۵۰۰» می‌زند)', 'pop_low' => '💎 کمبود موجودی — پاپ‌آپِ دکمه‌ی پیوستن',
        'bad_amount' => 'مبلغِ نامعتبر',
        'busy' => 'یک بازیِ فعالِ دیگر هست', 'cooldown' => 'کول‌داونِ بازی',
        'full' => 'ظرفیتِ بازی‌ها پره', 'not_yours' => 'مالِ تو نیست', 'gone' => 'بازی تمام شده',
        'already' => 'این خانه قبلا انتخاب شده', 'active' => 'کارتِ بازیِ فعال',
        'cancelled' => 'بازی لغو شد', 'lost' => 'باخت (روی مین)',
        'protected' => 'برخورد با مین، ولی محافظت‌شده', 'cashout' => 'برداشتِ جایزه — موفق',
        'expired' => 'بازی منقضی شد', 'gameover_head' => 'سرِ پیامِ پایانِ بازی',
        'btn_field' => 'دکمه: سرِ میدان', 'btn_join' => 'دکمه: پیوستن',
        'btn_cancel' => 'دکمه: لغو', 'btn_cash' => 'دکمه: برداشتِ جایزه',
    ];
}
function mnLabel($k) { return mnLabels()[$k] ?? $k; }
function mnBtnKeys() {
    return ['btn_field', 'btn_join', 'btn_cancel', 'btn_cash',
            'cell_hidden', 'cell_lock', 'cell_gem', 'cell_mine', 'cell_empty'];
}

function mnAdminHome($chatId, $msgId = null) {
    $c = mnCfg();
    $t  = "💣 <b>مین‌یاب</b>\n\n";
    $t .= 'وضعیت: ' . (mnOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= 'کلمه‌ی شروع: <code>' . h($c['word']) . " ۵۰۰</code>\n\n";
    $t .= '💎 بازه‌ی ورودی: <b>' . mnNum($c['entry_min']) . '</b> تا <b>' . mnNum($c['entry_max']) . "</b>\n";
    $t .= '🛡 حداقلِ خانه‌ی امن برایِ حفاظت: <b>' . (int)$c['min_safe_for_protection'] . "</b>\n";
    $t .= '⏰ مهلتِ بی‌کاری (بعدِ Join): <b>' . (int)round($c['game_timeout'] / 60) . "</b> دقیقه\n";
    $t .= '⏰ مهلتِ Joinنشدن: <b>' . (int)($c['waiting_timeout'] ?? 60) . "</b> ثانیه\n";

    $rows = [
        [btnCb(mnOn() ? '✅ روشن' : '❌ خاموش', 'mnax', 'info')],
        [btnCb('🗣 کلمه‌ی شروع', 'mnaw_home', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'mnat_home', 'admin')],
        [btnCb('🎨 رنگِ دکمه‌ها', 'mnacolors', 'admin'), btnCb('💎 متنِ کمبود موجودی', 'mnats_low_wallet', 'admin')],
        [btnCb('💎 بازه‌ی ورودی', 'mnarange', 'admin'), btnCb('🛡 حدِ حفاظت', 'mnasafe', 'admin')],
        [btnCb('🏆 جایزه‌ی هر خانه', 'mnarewards', 'admin')],
        [btnCb('⏰ مهلتِ بی‌کاری (دقیقه)', 'mnatimeout', 'admin'), btnCb('⏰ مهلتِ Joinنشدن (ثانیه)', 'mnawaiting', 'admin')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function mnTextsCfg() {
    return [
        'title' => 'متن‌های مین‌یاب',
        'keys'  => array_keys((array)mnVal('texts', [])),
        'popup' => ['already', 'full', 'gone', 'pop_low', 'need_join', 'not_yours'],
        'btns'  => mnBtnKeys(),
        'label' => 'mnLabel',
        'value' => function ($k) { return (string)mnVal('texts.' . $k, ''); },
        'cb'    => 'mnat_',
        'edit'  => 'mnats_',
        'back'  => 'mn_home',
    ];
}

function mnAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های مین‌یاب</b>\n\n";
    $rows = [];
    foreach (mnBtnKeys() as $k) {
        $color = (string)mnVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(mnLabel($k)) . ': <b>' . h(mnStyleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(mnLabel($k), 'mnacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'mn_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnStyleMap() {
    $m = function_exists('styleMap') ? styleMap() : [];
    return [
        'none'    => $m['none']    ?? '— بدون رنگ',
        'success' => $m['success'] ?? '🟢 سبز',
        'danger'  => $m['danger']  ?? '🔴 قرمز',
    ];
}

function mnAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)mnVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(mnLabel($k)) . "</b>\n\nالان: <b>" . h(mnStyleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (mnStyleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'mnacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'mnacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnAdminRewards($chatId, $msgId) {
    $r = mnVal('rewards', mnDefaults()['rewards']);
    $t = "🏆 <b>جایزه‌ی هر خانه‌ی امن</b>\n\nهرکدام، جایزه‌ی همان شماره‌خانه است (نه تجمعی).\n\n";
    $rows = [];
    for ($i = 1; $i <= 8; $i++) {
        $t .= '• خانه‌ی #' . $i . ': <b>' . mnNum($r[$i - 1] ?? 0) . "</b>\n";
        $rows[] = [btnCb('#' . $i . ' — ' . mnNum($r[$i - 1] ?? 0), 'mnarw_' . $i, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'mn_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'mn')) return false;

    if ($data === 'mn_home') { answerCb(BOT_TOKEN, $cbId); mnAdminHome($chatId, $msgId); return true; }
    if ($data === 'mnax') {
        mnSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); mnAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'mnaw_home') { answerCb(BOT_TOKEN, $cbId); mnAdminWord($chatId, $msgId); return true; }
    if (txRoute(mnTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;
    if ($data === 'mnarewards') { answerCb(BOT_TOKEN, $cbId); mnAdminRewards($chatId, $msgId); return true; }

    if ($data === 'mnacolors') { answerCb(BOT_TOKEN, $cbId); mnAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^mnacolk_(\w+)$/', $data, $m) && in_array($m[1], mnBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); mnAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^mnacolv_(\w+)_(\w+)$/', $data, $m) && in_array($m[1], mnBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        mnSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); mnAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    if (preg_match('/^mnarw_(\d)$/', $data, $m)) {
        $i = (int)$m[1];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'mn_reward', ['i' => $i]);
        sendMsg(BOT_TOKEN, $chatId, "🏆 جایزه‌ی خانه‌ی امنِ #{$i} چند الماس باشد؟",
            inlineKb([[btnCb('انصراف', 'mnarewards', 'cancel')]]));
        return true;
    }

    $asks = [
        'mnasafe'    => ['mn_safe',    "🛡 چند خانه‌ی امنِ پیاپی، از جریمه‌ی برخورد با مین محافظت کند؟ (۰ تا ۸)"],
        'mnatimeout' => ['mn_timeout', "⏰ بازیِ Joinشده ولی بی‌کار، بعدِ چند دقیقه منقضی شود؟"],
        'mnawaiting' => ['mn_waiting', "⏰ اگر کسی Join نکرد، بعدِ چند ثانیه بازی خودش بسته شود؟"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'mn_home', 'cancel')]]));
        return true;
    }
    if ($data === 'mnarange') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'mn_range', []);
        sendMsg(BOT_TOKEN, $chatId, "💎 <b>کف و سقفِ ورودی</b> · <code>10-1000000</code>",
            inlineKb([[btnCb('انصراف', 'mn_home', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'mnats_')) {
        $k = substr($data, 6);
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'mn_text', ['k' => $k]);
        $cur  = (string)mnVal('texts.' . $k, '');
        $back = inlineKb([[btnCb(UT('back'), 'mnat_home', 'cancel')]]);
        if (mnIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(mnLabel($k)) . "</b>\n\n" .
                "الان: <code>" . h($cur) . "</code>", $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(mnLabel($k)) . "</b>\n\n" .
                "جای‌گذاری‌های داخلِ آکولاد ({name}، {entry}، ...) را دست‌نخورده نگه دار.\n\nالان:\n" . $cur, $back);
        }
        return true;
    }
    if ($data === 'mnaws_word') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'mn_word', []);
        sendMsg(BOT_TOKEN, $chatId, "🗣 <b>کلمه‌ی شروع</b>\n\nفعلی: <code>" . h(mnVal('word', 'مین')) . "</code>",
            inlineKb([[btnCb('انصراف', 'mnaw_home', 'cancel')]]));
        return true;
    }

    return false;
}

function mnAdminWord($chatId, $msgId) {
    $t = "🗣 <b>کلمه‌ی شروع</b>\n\n<code>" . h(mnVal('word', 'مین')) . " ۵۰۰</code>";
    $rows = [[btnCb('✏️ عوض کردنِ کلمه', 'mnaws_word', 'admin')], [btnCb(UT('back'), 'mn_home', 'nav')]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function mnStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'mn_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('💣 مین‌یاب', 'mn_home', 'admin')]]);
    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'mn_safe') {
        $v = (int)norm_fa_digits($text);
        if ($v < 0 || $v > 8) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۰ تا ۸."); return true; }
        mnSet(function (&$c) use ($v) { $c['min_safe_for_protection'] = $v; });
        return $done('✅ حدِ حفاظت: ' . $v . ' خانه‌ی امن');
    }
    if ($action === 'mn_timeout') {
        $v = (int)norm_fa_digits($text);
        if ($v < 1 || $v > 1440) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا ۱۴۴۰ دقیقه."); return true; }
        mnSet(function (&$c) use ($v) { $c['game_timeout'] = $v * 60; });
        return $done('✅ مهلتِ بی‌کاری: ' . $v . ' دقیقه');
    }
    if ($action === 'mn_waiting') {
        $v = (int)norm_fa_digits($text);
        if ($v < 10 || $v > 3600) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱۰ تا ۳۶۰۰ ثانیه."); return true; }
        mnSet(function (&$c) use ($v) { $c['waiting_timeout'] = $v; });
        return $done('✅ مهلتِ Joinنشدن: ' . $v . ' ثانیه');
    }
    if ($action === 'mn_range') {
        if (!preg_match('/^\s*([\d,٬]+)\s*[-–ـ]\s*([\d,٬]+)\s*$/u', norm_fa_digits($text), $m)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ قالب: <code>10-1000000</code>"); return true;
        }
        $lo = (float)str_replace([',', '٬'], '', $m[1]);
        $hi = (float)str_replace([',', '٬'], '', $m[2]);
        if ($lo < 1 || $hi <= $lo) { sendMsg(BOT_TOKEN, $chatId, "⚠️ سقف باید از کف بزرگ‌تر باشد."); return true; }
        mnSet(function (&$c) use ($lo, $hi) { $c['entry_min'] = $lo; $c['entry_max'] = $hi; });
        return $done();
    }
    if ($action === 'mn_reward') {
        $i = (int)($sd['i'] ?? 0);
        $v = (float)str_replace([',', '،'], '', norm_fa_digits($text));
        if ($i < 1 || $i > 8 || $v < 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عدد نامعتبر"); return true; }
        mnSet(function (&$c) use ($i, $v) {
            $r = $c['rewards'] ?? mnDefaults()['rewards'];
            $r[$i - 1] = $v;
            $c['rewards'] = $r;
        });
        return $done('✅ جایزه‌ی خانه‌ی #' . $i . ': ' . mnNum($v));
    }
    if ($action === 'mn_word') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی نمی‌شود."); return true; }
        mnSet(function (&$c) use ($text) { $c['word'] = $text; });
        return $done();
    }
    if ($action === 'mn_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ چیزی برای ذخیره نیست."); return true; }

        if (mnIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            mnSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد" . ($icon !== '' ? " — ایموجیِ پریمیوم هم رویِ دکمه نشست." : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[mnBtn($k, [], 'mn_nop')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        mnSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
        return true;
    }
    clearState($uid);
    return true;
}

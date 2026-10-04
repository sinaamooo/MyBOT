<?php
defined('NB_ROOT') || exit;


function tpDefaults() {
    return [
        'on'         => true,
        'word'       => 'تاپ الماسی',
        'aliases'    => 'تاپ الماس, برترین های الماسی, top diamond',
        'group_only' => 1,
        'card'       => 1,
        'n_global'   => 3,
        'n_group'    => 3,
        'texts'      => [
            'full'        => "🏆 <b>تاپ الماسی</b>\n" .
                             "{world}" .
                             "\n👥 <b>برترین‌های این گروه</b>\n{group}" .
                             "\n{me}\n",
            'row'         => "{medal} {tag} — <b>{points}</b> 💎\n",
            'w_head'      => "\n🌍 <b>برترین‌های کلِ ربات</b>\n",
            'g_none'      => "هنوز کسی در این گروه با ربات بازی نکرده.\n",
            'me'          => "📍 رتبه‌ی شما: <b>{rank}</b> — <b>{points}</b> 💎",
            'me_none'     => "📍 شما هنوز الماسی ندارید.",
            'off'         => '⛔️ این بخش فعلا خاموش است.',
        ],
    ];
}

function tpCfg() {
    $c = cfg()['toplist'] ?? null;
    return is_array($c) ? array_replace_recursive(tpDefaults(), $c) : tpDefaults();
}
function tpSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['toplist'] ?? null)) $c['toplist'] = tpDefaults();
        $fn($c['toplist']);
    });
}
function tpVal($path, $default = null) {
    $cur = tpCfg();
    foreach (explode('.', (string)$path) as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
        $cur = $cur[$k];
    }
    return $cur;
}
function tpOn() { return !empty(tpVal('on')); }

function tpBtnKeys() { return []; }
function tpIsButtonKey($s) { return in_array($s, tpBtnKeys(), true); }

function tpT($slug, $vars = []) {
    $t = (string)tpVal('texts.' . $slug, tpDefaults()['texts'][$slug] ?? $slug);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function tpNum($n) { return number_format((float)$n, 0, '.', ','); }


function tpDbPath() { return DATA_DIR . '/top_members.sqlite'; }

function tpDb() {
    static $db = null;
    if ($db !== null) return $db ?: null;
    if (!class_exists('SQLite3') && !dbOn()) return $db = false;

    $dir = dirname(tpDbPath());
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    try { $db = nbRawOpen(tpDbPath()); }
    catch (Throwable $e) { error_log('[toplist] ' . $e->getMessage()); return $db = false; }

    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS members (
        chat INTEGER NOT NULL, uid INTEGER NOT NULL, at INTEGER NOT NULL,
        PRIMARY KEY (chat, uid)
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_members_chat ON members(chat, at DESC)');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_members_at ON members(at)');
    return $db;
}

function tpTouch($chatId, $uid) {
    static $seen = [];
    $chatId = (int)$chatId; $uid = (int)$uid;
    if ($chatId >= 0 || $uid <= 0) return;
    $k = $chatId . ':' . $uid;
    if (isset($seen[$k])) return;
    $seen[$k] = 1;

    $db = tpDb();
    if (!$db) return;
    $st = $db->prepare('INSERT OR REPLACE INTO members (chat, uid, at) VALUES (:c, :u, :t)');
    if (!$st) return;
    $st->bindValue(':c', $chatId, SQLITE3_INTEGER);
    $st->bindValue(':u', $uid, SQLITE3_INTEGER);
    $st->bindValue(':t', time(), SQLITE3_INTEGER);
    @$st->execute();
}

function tpSweep($days = 120, $limit = 500) {
    $db = tpDb();
    if (!$db) return 0;
    $cut = time() - max(1, (int)$days) * 86400;
    $st = $db->prepare('DELETE FROM members WHERE rowid IN (SELECT rowid FROM members WHERE at < :c LIMIT :l)');
    if (!$st) return 0;
    $st->bindValue(':c', $cut, SQLITE3_INTEGER);
    $st->bindValue(':l', max(1, (int)$limit), SQLITE3_INTEGER);
    $st->execute();
    return $db->changes();
}


function tpRow($u) {
    return [
        'uid'    => (int)($u['id'] ?? 0),
        'name'   => trim((string)($u['name'] ?? '')),
        'uname'  => trim((string)($u['username'] ?? '')),
        'points' => (float)($u['points'] ?? 0),
    ];
}

function tpGlobalTop($n = 3) {
    if (!function_exists('dmTop')) return [];
    $out = [];
    foreach (dmTop(max(1, (int)$n)) as $u) $out[] = tpRow($u);
    return $out;
}

function tpGroupTop($chatId, $n = 3, $scan = 500) {
    $db = tpDb();
    if (!$db || !function_exists('dmUser')) return [];

    $st = $db->prepare('SELECT uid FROM members WHERE chat = :c ORDER BY at DESC LIMIT :n');
    if (!$st) return [];
    $st->bindValue(':c', (int)$chatId, SQLITE3_INTEGER);
    $st->bindValue(':n', max(1, (int)$scan), SQLITE3_INTEGER);
    $res = @$st->execute();
    if (!$res) return [];

    $rows = [];
    while ($r = $res->fetchArray(SQLITE3_ASSOC)) {
        $u = dmUser((int)$r['uid']);
        if (!is_array($u)) continue;
        $row = tpRow($u + ['id' => (int)$r['uid']]);
        if ($row['points'] > 0) $rows[] = $row;
    }
    usort($rows, fn($a, $b) => $b['points'] <=> $a['points']);
    return array_slice($rows, 0, max(1, (int)$n));
}

function tpRankOf($uid) {
    if (!function_exists('diamondDb')) return [0, 0.0];
    $u   = function_exists('dmUser') ? dmUser((int)$uid) : null;
    $pts = (float)($u['points'] ?? 0);
    if ($pts <= 0) return [0, 0.0];

    $db = diamondDb();
    if (!$db) return [0, $pts];
    $st = $db->prepare('SELECT COUNT(*) AS n FROM diamond_users WHERE points > :p');
    if (!$st) return [0, $pts];
    $st->bindValue(':p', $pts, SQLITE3_FLOAT);
    $res = @$st->execute();
    $n = $res ? (int)($res->fetchArray(SQLITE3_ASSOC)['n'] ?? 0) : 0;
    return [$n + 1, $pts];
}

function tpTag($r) {
    $nm = $r['name'] !== '' ? $r['name'] : ('کاربر ' . $r['uid']);
    if (function_exists('h')) $nm = h($nm);
    return $r['uname'] !== '' ? '@' . h($r['uname']) : $nm;
}

function tpMedal($i) { return ['🥇', '🥈', '🥉'][$i] ?? ('▫️'); }


function tpRows($list) {
    $out = '';
    foreach ($list as $i => $r)
        $out .= tpT('row', ['medal' => tpMedal($i), 'tag' => tpTag($r), 'points' => tpNum($r['points'])]);
    return $out;
}

function tpText($chatId, $uid, $global) {
    $world = tpCardOn() ? '' : tpT('w_head') . tpRows($global);

    $grp   = tpGroupTop($chatId, (int)tpVal('n_group', 3));
    $group = $grp ? tpRows($grp) : tpT('g_none');

    [$rank, $pts] = tpRankOf($uid);
    $me = $rank > 0
        ? tpT('me', ['rank' => tpNum($rank), 'points' => tpNum($pts)])
        : tpT('me_none');

    return tpT('full', ['world' => $world, 'group' => $group, 'me' => $me]);
}

function tpCardOn() {
    return !empty(tpVal('card')) && function_exists('bcReady') && bcReady();
}

function tpKb($uid) {
    return null;
}


function tpWordHit($raw, $words) {
    $r = trim(mb_strtolower(norm_fa_digits((string)$raw)));
    if ($r === '') return false;
    foreach (explode(',', (string)$words) as $w) {
        $w = trim(mb_strtolower(norm_fa_digits($w)));
        if ($w !== '' && $r === $w) return true;
    }
    return false;
}

function tpHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate) {
    if (!tpOn()) return false;
    if (!empty(tpVal('group_only', 1)) && $isPrivate) return false;

    $raw = trim((string)$text);
    if ($raw === '' || mb_strlen($raw) > 30) return false;
    if (!tpWordHit($raw, tpVal('word', 'تاپ الماسی') . ',' . tpVal('aliases', ''))) return false;

    $global = tpGlobalTop((int)tpVal('n_global', 3));
    $cap    = tpText($chatId, $uid, $global);
    $kb     = tpKb($uid);
    $extra  = $replyTo ? ['reply_to_message_id' => $replyTo] : [];

    if (tpCardOn() && $global) {
        $ck  = tpCardKey($global);
        $send = function ($photo) use ($chatId, $cap, $kb, $extra) {
            $data = array_merge([
                'chat_id'    => $chatId,
                'photo'      => $photo,
                'caption'    => mb_substr($cap, 0, 1024),
                'parse_mode' => 'HTML',
            ], $extra);
            if ($kb) $data['reply_markup'] = function_exists('kbJson') ? kbJson($kb) : json_encode($kb);
            $r = tg(BOT_TOKEN, 'sendPhoto', $data, 30);
            if (empty($r['ok']) && $kb && function_exists('isStyleError') && isStyleError($r)) {
                $data['reply_markup'] = json_encode(stripStyles($kb));
                $r = tg(BOT_TOKEN, 'sendPhoto', $data, 30);
            }
            return $r;
        };

        $fid = function_exists('maCacheGet') ? (string)(maCacheGet(tpPhotoIdKey($ck), 86400) ?? '') : '';
        if ($fid !== '') {
            $r = $send($fid);
            if (!empty($r['ok'])) return true;
            if (function_exists('maCachePut')) maCachePut(tpPhotoIdKey($ck), '');
        }

        $file = tpCard($global);
        if ($file && is_file($file)) {
            $r = $send(new CURLFile($file, 'image/jpeg', 'top.jpg'));
            if (!empty($r['ok'])) {
                $ph = $r['result']['photo'] ?? null;
                if (is_array($ph) && $ph && function_exists('maCachePut')) {
                    $last = $ph[count($ph) - 1] ?? [];
                    if (!empty($last['file_id'])) maCachePut(tpPhotoIdKey($ck), (string)$last['file_id']);
                }
                return true;
            }
            error_log('[toplist] sendPhoto نشد: ' . (string)($r['description'] ?? ''));
        }
    }

    sendMsg(BOT_TOKEN, $chatId, $cap, $kb, $extra);
    return true;
}

function tpCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if (!preg_match('/^tp_fc_\d+$/', (string)$data)) return false;
    answerCb(BOT_TOKEN, $cbId, 'این بخش دیگر فعال نیست.', true);
    return true;
}


const TP_VER = 3;

function tpCardKey(array $top) {
    $sig = TP_VER . '|';
    foreach ($top as $r) $sig .= (int)($r['uid'] ?? 0) . ':' . (int)($r['points'] ?? 0) . '|';
    $sig .= function_exists('botUsername') ? botUsername() : '';
    return substr(md5($sig), 0, 16);
}

function tpPhotoIdKey($ck) { return 'tpfid_' . $ck; }

function tpCard(array $top) {
    if (!function_exists('bcReady') || !bcReady() || !$top) return null;
    $fa    = (string)(bcFont(true) ?: bcFont());
    $im    = pxBase('E8B021', '12C488');
    $S     = PX_SS;
    $white = pxCol($im, 'F5F5F7');
    $lat   = pxLat(600);

    [, $tr] = pxTag($im, 232, 172, 'TOP ' . count($top), 'gold', 'l');
    pxTag($im, $tr + 10, 172, 'LIVE', 'green', 'l');
    $title = 'تاپ الماسی';
    pxSay($im, pxSayFit(38, $title, 966 - $tr - 120, 18, $fa), 966, 186, $white, $title, 'r', $fa);

    $medal = [1 => ['F2B705', 'FFE08A'], 2 => ['AEB6C2', 'E3E7EE'], 3 => ['CD7F32', 'F0B584']];
    $cols  = [1 => [600, 128, 334], 2 => [844, 102, 348], 3 => [356, 102, 348]];
    foreach ($cols as $rank => [$cx, $d, $cy]) {
        $r = $top[$rank - 1] ?? null;
        if (!$r) continue;
        $one = $rank === 1;
        pxRRect($im, ($cx - 114) * $S, ($one ? 226 : 244) * $S, ($cx + 114) * $S, 552 * $S, 26 * $S, pxCol($im, 'FFFFFF', $one ? 0.05 : 0.035));
        if ($one) {
            $ky = $cy - $d / 2 - 18;
            imagefilledpolygon($im, [
                (int)(($cx - 24) * $S), (int)(($ky + 10) * $S), (int)(($cx - 28) * $S), (int)(($ky - 12) * $S),
                (int)(($cx - 12) * $S), (int)(($ky - 1) * $S),  (int)($cx * $S), (int)(($ky - 17) * $S),
                (int)(($cx + 12) * $S), (int)(($ky - 1) * $S),  (int)(($cx + 28) * $S), (int)(($ky - 12) * $S),
                (int)(($cx + 24) * $S), (int)(($ky + 10) * $S),
            ], pxCol($im, $medal[1][0]));
        }
        $nm = bcCleanName((string)($r['name'] ?? ''), ($r['uname'] ?? '') !== '' ? (string)$r['uname'] : 'کاربر', 16);
        pxAvatar($im, $r['avatar'] ?? null, $cx, $cy, $d, $medal[$rank][0], 4, $nm, '1E1F22', $fa);
        $by = $cy + $d / 2 - 1;
        imagefilledellipse($im, $cx * $S, (int)round($by * $S), 38 * $S, 38 * $S, pxCol($im, '141518'));
        imagefilledellipse($im, $cx * $S, (int)round($by * $S), 32 * $S, 32 * $S, pxCol($im, $medal[$rank][0]));
        pxPut($im, pxLat(700), 16, $cx, $by + pxCapH(pxLat(700), 16) / 2, pxCol($im, '0B0B0C'), (string)$rank, 'c');

        $ny = $by + 56;
        pxSay($im, pxSayFit($one ? 22 : 20, $nm, 200, 12, $fa), $cx, $ny, $white, $nm, 'c', $fa);
        $pv = bcFmt($r['points'] ?? 0);
        $ps = pxFit($lat, $one ? 21 : 19, $pv, 160, 12);
        $pw = pxInkW($lat, $ps, $pv);
        $x0 = $cx - ($pw + 26) / 2;
        pxGem($im, $x0 + 9, $ny + 38, 18, $medal[$rank][0]);
        pxPut($im, $lat, $ps, $x0 + 26, $ny + 46, pxCol($im, $medal[$rank][1]), $pv);
    }

    pxFoot($im, 'DIAMOND LEADERBOARD');
    $bytes = pxJpg($im);

    $dir = DATA_DIR . '/cards';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $out = $dir . '/top_' . tpCardKey($top) . '.jpg';
    $tmp = $out . '.' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $bytes) === false) return null;
    @rename($tmp, $out);
    tpCardPrune($dir);
    return is_file($out) ? $out : null;
}

function tpCardPrune($dir) {
    $mark = $dir . '/.top_swept';
    if (is_file($mark) && time() - (@filemtime($mark) ?: 0) < 600) return;
    @touch($mark);
    $now = time();
    foreach ((array)@glob($dir . '/top_*.jpg') as $f)
        if ($now - (@filemtime($f) ?: $now) > 3600) @unlink($f);
}


function tpLabels() {
    return [
        'full'    => '📝 کلِ پیام (یک‌جا)',
        'row'     => 'یک ردیفِ فهرست',
        'w_head'  => 'سرِ فهرستِ کلِ ربات',
        'g_none'  => 'گروهِ خالی',
        'me'      => 'رتبه‌ی خودِ کاربر',
        'me_none' => 'رتبه‌ی خودِ کاربر — بدونِ الماس',
        'off'     => 'خاموش است',
    ];
}
function tpLabel($k) { return tpLabels()[$k] ?? $k; }

function tpTextsCfg() {
    return [
        'title' => 'متن‌های تاپ الماسی',
        'keys'  => array_keys(tpDefaults()['texts']),
        'popup' => ['off'],
        'btns'  => tpBtnKeys(),
        'label' => 'tpLabel',
        'value' => function ($k) { return (string)tpVal('texts.' . $k, ''); },
        'cb'    => 'tpat_',
        'edit'  => 'tpats_',
        'back'  => 'tp_home',
    ];
}

function tpAdminHome($chatId, $msgId = null) {
    $c = tpCfg();
    $db = tpDb();
    $groups = 0; $members = 0;
    if ($db) {
        $r = @$db->query('SELECT COUNT(DISTINCT chat) AS g, COUNT(*) AS m FROM members');
        if ($r && ($x = $r->fetchArray(SQLITE3_ASSOC))) { $groups = (int)$x['g']; $members = (int)$x['m']; }
    }

    $t  = "🏆 <b>تاپ الماسی</b>\n\n";
    $t .= 'وضعیت: ' . (tpOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'کارتِ تصویری: ' . (!empty($c['card'])
            ? (function_exists('bcReady') && bcReady() ? '✅ روشن' : '⚠️ روشن ولی GD/فونت نیست')
            : '❌ خاموش') . "\n\n";
    $t .= 'کلمه: <code>' . h($c['word']) . "</code>\n";
    $t .= 'کلمه‌های دیگر: <code>' . h(($c['aliases'] ?? '') ?: '—') . "</code>\n\n";
    $t .= '🌍 نفراتِ کارت: <b>' . (int)$c['n_global'] . "</b>\n";
    $t .= '👥 نفراتِ گروه: <b>' . (int)$c['n_group'] . "</b>\n\n";
    $t .= '📇 فهرستِ اعضا: <b>' . tpNum($members) . '</b> نفر در <b>' . tpNum($groups) . '</b> گروه';

    $rows = [
        [btnCb(tpOn() ? '✅ روشن' : '❌ خاموش', 'tpax', 'info'),
         btnCb(!empty($c['card']) ? '🖼 کارت: روشن' : '🖼 کارت: خاموش', 'tpacard', 'info')],
        [btnCb('🗣 کلمه‌ها', 'tpaw', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'tpat_home', 'admin')],
        [btnCb('🎨 رنگِ دکمه', 'tpacolors', 'admin')],
        [btnCb('🌍 نفراتِ کارت', 'tpang', 'admin'), btnCb('👥 نفراتِ گروه', 'tpanr', 'admin')],
        [btnCb('👀 نمونه‌ی کارت', 'tpaprev', 'confirm')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else        sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function tpAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه</b>";
    $rows = [];
    foreach (tpBtnKeys() as $k) $rows[] = [btnCb(tpLabel($k), 'tpacolk_' . $k, 'info')];
    $rows[] = [btnCb(UT('back'), 'tp_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function tpAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)tpVal('btns.' . $k . '.color', 'none');
    $t = '🎨 <b>' . h(tpLabel($k)) . "</b>\n\nالان: " . (styleMap()[$cur] ?? '—');
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'tpacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'tpacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function tpAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'tp')) return false;

    if ($data === 'tp_home') { answerCb(BOT_TOKEN, $cbId); tpAdminHome($chatId, $msgId); return true; }
    if ($data === 'tpax') {
        tpSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); tpAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'tpacard') {
        tpSet(function (&$c) { $c['card'] = empty($c['card']) ? 1 : 0; });
        $warn = (function_exists('bcReady') && !bcReady())
              ? '⚠️ GD یا فونت نیست — پیام متنی می‌ماند.' : '✅';
        answerCb(BOT_TOKEN, $cbId, $warn, true); tpAdminHome($chatId, $msgId); return true;
    }

    if (function_exists('txRoute') && txRoute(tpTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;

    if ($data === 'tpacolors') { answerCb(BOT_TOKEN, $cbId); tpAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^tpacolk_(\w+)$/', $data, $m) && tpIsButtonKey($m[1])) {
        answerCb(BOT_TOKEN, $cbId); tpAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^tpacolv_(\w+)_(\w+)$/', $data, $m) && tpIsButtonKey($m[1]) && isset(styleMap()[$m[2]])) {
        tpSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); tpAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    $tc = tpCfg();
    $asks = [
        'tpaw'   => ['tp_word', "🗣 <b>کلمه‌ی تاپ</b>\n\nفعلی: <code>" . h($tc['word']) . "</code>"],
        'tpang'  => ['tp_ng',   "🌍 <b>نفراتِ کارت</b> (۱–۳)\n\nفعلی: <code>" . (int)$tc['n_global'] . "</code>"],
        'tpanr'  => ['tp_nr',   "👥 <b>نفراتِ گروه</b> (۱–۱۰)\n\nفعلی: <code>" . (int)$tc['n_group'] . "</code>"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'tp_home', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'tpats_')) {
        $k = substr($data, 6);
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'tp_text', ['k' => $k]);
        sendMsg(BOT_TOKEN, $chatId,
            '✏️ «' . h(tpLabel($k)) . "»\n\n" .
            'الان: <code>' . h(mb_substr((string)tpVal('texts.' . $k, ''), 0, 300)) . '</code>',
            inlineKb([[btnCb('انصراف', 'tp_home', 'cancel')]]));
        return true;
    }

    if ($data === 'tpaprev') {
        answerCb(BOT_TOKEN, $cbId, '🖼 نمونه…');
        $top = tpGlobalTop((int)tpVal('n_global', 3));
        if (!$top) {
            sendMsg(BOT_TOKEN, $chatId, '⚠️ هنوز هیچ کاربری الماس ندارد، پس کارتی ساخته نمی‌شود.',
                inlineKb([[btnCb('🏆 تاپ الماسی', 'tp_home', 'admin')]]));
            return true;
        }
        $f = tpCard($top);
        if ($f && is_file($f)) {
            sendFile(BOT_TOKEN, $chatId, 'photo', new CURLFile($f, 'image/jpeg', 'top.jpg'), '👀 نمونه‌ی کارتِ تاپ');
        } else {
            sendMsg(BOT_TOKEN, $chatId, '⚠️ کارت ساخته نشد — GD یا فونت رویِ این هاست نیست.',
                inlineKb([[btnCb('🏆 تاپ الماسی', 'tp_home', 'admin')]]));
        }
        return true;
    }

    return false;
}

function tpStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'tp_')) return false;
    if (!isAdmin($uid)) { clearState($uid); return true; }
    $sd   = getState($uid)['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('🏆 تاپ الماسی', 'tp_home', 'admin')]]);
    $done = function ($m = '✅ ذخیره شد.') use ($uid, $chatId, $back) {
        clearState($uid); sendMsg(BOT_TOKEN, $chatId, $m, $back); return true;
    };

    if ($action === 'tp_word') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ خالی نمی‌شود.'); return true; }
        tpSet(function (&$c) use ($text) { $c['word'] = $text; });
        return $done('✅ کلمه: <code>' . h($text) . '</code>');
    }
    if ($action === 'tp_ng' || $action === 'tp_nr') {
        $v   = (int)norm_fa_digits($text);
        $max = $action === 'tp_ng' ? 3 : 10;
        if ($v < 1 || $v > $max) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۱ تا {$max}."); return true; }
        $key = $action === 'tp_ng' ? 'n_global' : 'n_group';
        tpSet(function (&$c) use ($key, $v) { $c[$key] = $v; });
        return $done('✅ ' . $v . ' نفر');
    }
    if ($action === 'tp_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ چیزی برای ذخیره نیست.'); return true; }

        if (tpIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ متنِ دکمه خالی نمی‌شود.'); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            $val  = ($icon !== '' && function_exists('textWithoutCustomEmoji'))
                  ? textWithoutCustomEmoji($msg) : $text;
            tpSet(function (&$c) use ($k, $val, $icon) {
                $c['texts'][$k] = $val !== '' ? $val : $c['texts'][$k];
                $c['icons'][$k] = $icon;
            });
            return $done($icon !== '' ? '✅ ذخیره شد (با ایموجیِ پریمیوم).' : '✅ ذخیره شد.');
        }

        $html = function_exists('entitiesToHtml') ? entitiesToHtml($msg['text'] ?? '', $msg['entities'] ?? null) : $text;
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, '⚠️ خالی نمی‌شود.'); return true; }
        tpSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        return $done();
    }
    return false;
}

<?php
defined('NB_ROOT') || exit;


function bkDefaults() {
    return [
        'on'         => true,
        'group_only' => 0,
        'word_bank'  => 'بانک,حساب بانکی',

        'level_step'     => 500000,
        'top_n'          => 10,
        'card_image'     => 1,
        'card_footer'    => 'کارتِ اختصاصیِ شما',

        'interest' => [
            'on'       => true,
            'day_secs' => 86400,
            'max_days' => 30,
            'tiers'    => [
                [0,          0.5],
                [100000,     0.8],
                [1000000,    1.2],
                [10000000,   1.6],
                [100000000,  2.0],
            ],
        ],

        'icons' => ['btn_send' => '', 'btn_send_confirm' => '', 'btn_back' => '',
                    'btn_dep' => '', 'btn_wd' => ''],
        'btns'  => [
            'btn_dep'          => ['color' => 'success'],
            'btn_wd'           => ['color' => 'none'],
            'btn_send'         => ['color' => 'none'],
            'btn_send_confirm' => ['color' => 'success'],
            'btn_back'         => ['color' => 'none'],
        ],

        'texts' => [
            'btn_dep'          => '🏦 انتقال به بانک',
            'btn_wd'           => '💸 برداشت از بانک',
            'btn_send'         => '🎁 ارسال به کاربر',
            'btn_send_confirm' => '✅ انتقال',
            'btn_back'         => '🔙 برگشت',

            'card' => "🏦 <b>BANK</b>\n\n" .
                      "👤 User: {name}\n\n" .
                      "💎 موجودیِ کیف‌پول: <b>{wallet}</b>\n" .
                      "🏦 موجودیِ بانک: <b>{vault}</b>\n" .
                      "💠 کریستالِ ایردراپ: <b>{crystals}</b>\n" .
                      "📈 سودِ روزانه: <b>{rate}٪</b>\n\n" .
                      "📊 Bank Level: {level}\n\n" .
                      "━━━━━━━━━━━━━━",

            'dep_ask' => "🏦 <b>انتقال به بانک</b>\n\n" .
                         "چند الماس؟\n\n" .
                         "💎 کیفِ‌پول: <b>{wallet}</b>\n" .
                         "🏦 صندوق: <b>{vault}</b>\n\n" .
                         "<code>همه</code>",
            'dep_ok'  => "✅ <b>{amount}</b> الماس به بانک منتقل شد.\n\n" .
                         "💎 کیفِ‌پول: <b>{wallet}</b>\n🏦 صندوق: <b>{vault}</b>\n" .
                         "📈 سودِ روزانه‌ی این مبلغ: <b>{rate}٪</b>",
            'dep_low' => "❌ این‌قدر الماس تویِ کیفِ‌پول نداری.\n💎 موجودی: <b>{wallet}</b>",

            'wd_ask' => "💸 <b>برداشت از بانک</b>\n\n" .
                        "چند الماس؟\n\n" .
                        "🏦 صندوق: <b>{vault}</b>\n" .
                        "💎 کیفِ‌پول: <b>{wallet}</b>\n\n" .
                        "<code>همه</code>",
            'wd_ok'  => "✅ <b>{amount}</b> الماس از بانک برداشته شد.\n\n" .
                        "💎 کیفِ‌پول: <b>{wallet}</b>\n🏦 صندوق: <b>{vault}</b>",
            'wd_low' => "❌ این‌قدر الماس تویِ صندوق نیست.\n🏦 صندوق: <b>{vault}</b>",

            'ask_bad_num' => "❌ عدد نامعتبر است.",
            'ask_expired' => "⌛️ این درخواست منقضی شده.",
            'not_your_card' => "🔒 این کارتِ بانکِ شما نیست.",

            'ask_send_amt'   => "🎁 <b>SEND DIAMONDS</b>\n\nچند تا الماس می‌خوای بفرستی؟",
            'ask_send_id'    => "👤 آیدی یا یوزرنیمِ گیرنده (ریپلای)",
            'send_need_reply' => "↩️ فقط با ریپلای روی همین پیام",
            'ask_send_badid' => "❌ این آیدی/یوزرنیم معتبر نبود.",
            'send_self'      => "😄 نمی‌تونی برایِ خودت بفرستی.",
            'send_no_target' => "❌ این آیدی برایِ ربات شناخته‌شده نیست — گیرنده باید قبلا با ربات پیام داده باشد.",
            'send_low_wallet'=> "❌ موجودیِ کیف‌پول کافی نیست.\n💎 Wallet: <b>{wallet}</b>",
            'send_confirm'   => "🎁 <b>تاییدِ ارسال</b>\n\n💎 مقدار: <b>{amount}</b>\n👤 گیرنده: {to_tag}",
            'send_ok'        => "✅ <b>SEND SUCCESS</b>\n\n💎 Sent: {amount}\n👤 From: {from_tag}\n👤 To: {to_tag}\n\n━━━━━━━━━━━━━━\n\n💎 Wallet: <b>{wallet}</b>",

            'top_head' => "🏆 <b>برترین‌های بانک</b>\n",
            'top_row'  => "{rank}. {name} — 🏦 <b>{bank}</b>",
            'top_none' => "هنوز کسی بانکی نساخته.",
        ],
    ];
}

function bkCfg() {
    $c = cfg()['bank'] ?? null;
    return is_array($c) ? array_replace_recursive(bkDefaults(), $c) : bkDefaults();
}

function bkSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['bank'] ?? null)) $c['bank'] = bkDefaults();
        $fn($c['bank']);
    });
}

function bkVal($path, $default = null) {
    $v = bkCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function bkOn() { return !empty(bkVal('on')); }

function bkIsButtonKey($slug) {
    return in_array($slug, ['btn_send', 'btn_send_confirm', 'btn_back',
                            'btn_dep', 'btn_wd'], true);
}

function bkT($slug, $vars = []) {
    $t = (string)bkVal('texts.' . $slug, bkDefaults()['texts'][$slug] ?? $slug);
    if (isset($vars['vault']) && !isset($vars['bank'])) $vars['bank'] = $vars['vault'];
    if (isset($vars['bank']) && !isset($vars['vault'])) $vars['vault'] = $vars['bank'];
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    $dead = '\{(?:wins|stolen|sec_status|protect_left)\}';
    if (preg_match('/' . $dead . '/u', $t)) {
        $t = preg_replace('/\s*\([^()\n]*' . $dead . '[^()\n]*\)/u', '', $t);
        $t = preg_replace('/^[^\n]*' . $dead . '[^\n]*\n?/mu', '', $t);
        if (function_exists('tgHtmlFix')) $t = tgHtmlFix($t);
    }
    return $t;
}

function bkBtn($key, $vars, $data) {
    $b = ['callback_data' => $data];
    $color = (string)bkVal('btns.' . $key . '.color', '');
    if (function_exists('isStyle') && isStyle($color)) $b['style'] = $color;
    $b = function_exists('btnApplyLabel')
        ? btnApplyLabel($b, bkT($key, $vars), bkVal('icons.' . $key, ''))
        : ['text' => strip_tags((string)(bkT($key, $vars)))] + $b;
    return $b;
}

function bkNum($n) { return number_format((float)$n, 0, '.', ','); }

function bkUserTag($id, $name, $uname) {
    $label = trim((string)$name) !== '' ? (string)$name : ('#' . (int)$id);
    $u = trim((string)$uname);
    return h($label) . ($u !== '' ? ' (@' . h($u) . ')' : '');
}


function bkUserDefault($uid) {
    return [
        'id' => (int)$uid, 'name' => '', 'username' => '',
        'vault' => 0.0, 'vault_at' => 0,
        'created_at' => time(), 'updated_at' => time(),
    ];
}

function bankDbPath() { return DATA_DIR . '/bank_users.sqlite'; }

function bankDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = bankDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[bank] bank_users.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS bank_users (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');

    if ($fresh) bankImportFromJson($db);
    return $db;
}

function bankImportFromJson($db) {
    $old = dataPath('bank_users');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO bank_users (id, data) VALUES (:id, :data)');
        foreach ($arr as $k => $v) {
            $id = (int)$k;
            if ($id <= 0 || !is_array($v)) continue;
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function bkUser($uid) {
    $db = bankDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM bank_users WHERE id = :id');
    $stmt->bindValue(':id', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function bkUserSet($uid, callable $fn) {
    $db = bankDb();
    if (!$db) return null;
    $id = (int)$uid;

    if (!@$db->exec('BEGIN IMMEDIATE')) return null;
    try {
        $stmt = $db->prepare('SELECT data FROM bank_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $u = $row ? json_decode($row['data'], true) : null;
        if (!is_array($u)) $u = bkUserDefault($id);

        $result = $fn($u);
        $u['updated_at'] = time();

        $up = $db->prepare('INSERT OR REPLACE INTO bank_users (id, data) VALUES (:id, :data)');
        $up->bindValue(':id', $id, SQLITE3_INTEGER);
        $up->bindValue(':data', json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
        return $result;
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[bank] bkUserSet خطا: ' . $e->getMessage());
        return null;
    }
}

function bkLevel($vault) {
    $step = max(1, (int)bkVal('level_step', 500000));
    return min(1000, (int)floor(max(0, (float)$vault) / $step) + 1);
}

function bkTop($n = 10) {
    $n = max(1, min(100, (int)$n));
    $ck = 'bk_top_' . $n;
    $hit = function_exists('maCacheGet') ? maCacheGet($ck, 30) : null;
    if (is_array($hit)) return $hit;
    $top = [];
    $bdb = bankDb();
    $ddb = function_exists('diamondDb') ? diamondDb() : null;
    if ($bdb && $ddb) {
        $chk = $bdb->prepare('SELECT data FROM bank_users WHERE id = :i');
        $st  = $ddb->prepare('SELECT id, points FROM diamond_users WHERE points > 0 ORDER BY points DESC LIMIT 200 OFFSET :o');
        for ($off = 0; count($top) < $n && $off < 10000; $off += 200) {
            $st->bindValue(':o', $off, SQLITE3_INTEGER);
            $res = $st->execute();
            $got = 0;
            while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) {
                $got++;
                $chk->bindValue(':i', (int)$row['id'], SQLITE3_INTEGER);
                $r = $chk->execute();
                $b = $r ? $r->fetchArray(SQLITE3_ASSOC) : null;
                $chk->reset();
                $u = $b ? json_decode((string)$b['data'], true) : null;
                if (!is_array($u)) continue;
                $u['id'] = (int)$row['id'];
                $u['bank_balance'] = (float)$row['points'];
                $top[] = $u;
                if (count($top) >= $n) break;
            }
            $st->reset();
            if ($got < 200) break;
        }
    }
    if (function_exists('maCachePut')) maCachePut($ck, $top);
    return $top;
}


function bkPendKey($uid, $chat) { return $uid . '_' . $chat; }

function bkPendSet($uid, $chat, $kind, $msgId, $data = []) {
    mutate('bank_states', function (&$s) use ($uid, $chat, $kind, $msgId, $data) {
        $s[bkPendKey($uid, $chat)] = ['kind' => $kind, 'msg' => (int)$msgId, 'at' => time(), 'data' => $data];
    });
}

function bkPendGet($uid, $chat) {
    $s = load('bank_states');
    $e = $s[bkPendKey($uid, $chat)] ?? null;
    if (count($s) > 30 && random_int(1, 50) === 1) bkPendSweep(100);
    if (!$e || time() - (int)($e['at'] ?? 0) > 300) return null;
    return $e;
}

function bkPendClear($uid, $chat) {
    mutate('bank_states', function (&$s) use ($uid, $chat) { unset($s[bkPendKey($uid, $chat)]); });
}

function bkPendSweep($limit = 200) {
    $now = time();
    $removed = 0;
    mutate('bank_states', function (&$s) use ($now, $limit, &$removed) {
        foreach (array_keys($s) as $k) {
            if ($removed >= $limit) break;
            if ($now - (int)($s[$k]['at'] ?? 0) > 300) { unset($s[$k]); $removed++; }
        }
    });
    return $removed;
}

function bkLooksLikeAmount($raw) {
    $n = trim(str_replace([',', '٬', ' '], '', norm_fa_digits(trim((string)$raw))));
    return $n !== '' && preg_match('/^\d+(\.\d+)?$/', $n) === 1;
}

function bkLooksLikeTarget($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return false;
    if ($raw[0] === '@') $raw = substr($raw, 1);
    if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $raw) === 1) return true;
    $digits = trim(norm_fa_digits($raw));
    return $digits !== '' && preg_match('/^\d+$/', $digits) === 1;
}

function bkResolveTarget($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return null;
    if ($raw[0] === '@') $raw = substr($raw, 1);

    $digits = trim(norm_fa_digits($raw));
    if ($digits !== '' && preg_match('/^\d+$/', $digits) === 1) return (int)$digits;

    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{2,31}$/', $raw) || !function_exists('usersDb') || !($db = usersDb())) return null;
    $st = $db->prepare("SELECT id FROM users WHERE lower(json_extract(data,'$.username')) = :u LIMIT 1");
    if (!$st) return null;
    $st->bindValue(':u', strtolower($raw), SQLITE3_TEXT);
    $r = $st->execute();
    $row = $r ? $r->fetchArray(SQLITE3_NUM) : null;
    return $row ? (int)$row[0] : null;
}


function bkCardText($uid, $name, $vault = null) {
    $wallet = gmPoints($uid);
    $vault  = $vault ?? bkVaultOf($uid);
    $tpl = preg_replace('/^[^\n]*\{(sec_status|protect_left|wins|stolen)\}[^\n]*\n?/mu', '', bkT('card'));
    $tpl = str_replace([' (قابلِ سرقت)', ' (امن)'], '', $tpl);
    $cr = function_exists('adCrystalsNow') ? adCrystalsNow($uid) : null;
    if ($cr !== null && !str_contains($tpl, '{crystals}'))
        $tpl = rtrim($tpl) . "\n💠 کریستالِ ایردراپ: <b>{crystals}</b>";
    $tpl = preg_replace('/\n{3,}/u', "\n\n", $tpl);
    $out = strtr($tpl, [
        '{name}'     => h($name),
        '{wallet}'   => bkNum($wallet),
        '{vault}'    => bkNum($vault),
        '{rate}'     => rtrim(rtrim(number_format(bkRate($vault), 2, '.', ''), '0'), '.'),
        '{bank}'     => bkNum($vault),
        '{level}'    => bkLevel($vault),
        '{crystals}' => bkNum(floor((float)$cr)),
    ]);
    $out = function_exists('tgHtmlFix') ? tgHtmlFix($out) : $out;
    return trim(preg_replace('/\n{3,}/u', "\n\n", $out));
}

function bkKb($uid) {
    return inlineKb([
        [bkBtn('btn_dep', [], 'bk_dep_' . (int)$uid), bkBtn('btn_wd', [], 'bk_wd_' . (int)$uid)],
        [bkBtn('btn_send', [], 'bk_send_' . (int)$uid)],
    ]);
}

function bkEditCard($chatId, $msgId, $text, $kb = null) {
    $msgId = (int)$msgId;
    if ($msgId <= 0) { sendMsg(BOT_TOKEN, $chatId, $text, $kb); return; }

    $data = ['chat_id' => $chatId, 'message_id' => $msgId,
             'caption' => mb_substr((string)$text, 0, 1024), 'parse_mode' => 'HTML'];
    if ($kb) $data['reply_markup'] = function_exists('kbJson') ? kbJson($kb) : json_encode($kb);
    $r = tg(BOT_TOKEN, 'editMessageCaption', $data);
    if (!empty($r['ok'])) return;
    if (function_exists('isNotModified') && isNotModified($r)) return;

    editMsg(BOT_TOKEN, $chatId, $msgId, $text, $kb);
}

function bkBackKb($uid) {
    return inlineKb([[bkBtn('btn_back', [], 'bk_back_' . (int)$uid)]]);
}

function bkShow($uid, $chatId, $name, $editMsgId = null, $replyToMsgId = null) {
    $vault = bkVaultOf($uid);
    $text  = bkCardText($uid, $name, $vault);
    if ($editMsgId) { editMsg(BOT_TOKEN, $chatId, $editMsgId, $text, bkKb($uid)); return; }

    $extra = $replyToMsgId ? ['reply_to_message_id' => (int)$replyToMsgId] : [];

    if (!empty(bkVal('card_image', 1)) && function_exists('bcSend')) {
        $u  = bkUser($uid);
        $ok = bcSend($chatId, $text, [
            'uid'      => (int)$uid,
            'name'     => $name,
            'username' => (string)($u['username'] ?? ''),
            'card_no'  => (string)($u['card_no'] ?? ''),
            'locked'   => $vault,
            'level'    => bkLevel($vault),
            'rank'     => 0,
            'footer'   => (string)bkVal('card_footer', 'کارتِ اختصاصیِ شما'),
        ], bkKb($uid), $extra);
        if ($ok) return;
    }

    sendMsg(BOT_TOKEN, $chatId, $text, bkKb($uid), $extra);
}

function bkAskEdit($chatId, $pendMsgId, $text, array $extraRows = [], $uid = 0) {
    $rows = $extraRows;
    $rows[] = [bkBtn('btn_back', [], 'bk_cancelflow_' . (int)$uid)];
    $kb = inlineKb($rows);
    if ($pendMsgId) { bkEditCard($chatId, (int)$pendMsgId, $text, $kb); return; }
    sendMsg(BOT_TOKEN, $chatId, $text, $kb);
}

function bkSendDiamond($fromUid, $toUid, $fromName, $fromUname, $amount) {
    $amount = (float)$amount;
    if ($amount <= 0 || floor($amount) != $amount) return [false, bkT('ask_bad_num')];
    if ((int)$toUid === (int)$fromUid) return [false, bkT('send_self')];

    $wallet = gmPoints($fromUid);
    if ($amount > $wallet + 1e-9) return [false, bkT('send_low_wallet', ['wallet' => bkNum($wallet)])];

    if (!gmAdd($fromUid, -$amount, $fromName, $fromUname)) {
        return [false, bkT('send_low_wallet', ['wallet' => bkNum(gmPoints($fromUid))])];
    }
    if (!gmAdd($toUid, $amount)) {
        gmAdd($fromUid, $amount, $fromName, $fromUname);
        return [false, bkT('ask_bad_num')];
    }

    $toUser = function_exists('getUser') ? getUser($toUid) : null;

    return [true, bkT('send_ok', [
        'amount'   => bkNum($amount),
        'to'       => $toUid,
        'from_tag' => bkUserTag($fromUid, $fromName, $fromUname),
        'to_tag'   => bkUserTag($toUid, $toUser['first_name'] ?? '', $toUser['username'] ?? ''),
        'wallet'   => bkNum(gmPoints($fromUid)),
    ])];
}

function bkVaultMove($uid, $amount, $dir, $name = '', $uname = '') {
    $amount = (float)$amount;
    if ($amount <= 0 || floor($amount) != $amount) return [false, bkT('ask_bad_num')];

    $vault = bkVaultOf($uid);
    $wallet = gmPoints($uid);

    if ($dir > 0) {
        if ($amount > $wallet + 1e-9)
            return [false, bkT('dep_low', ['wallet' => bkNum($wallet)])];
        if (!gmAdd($uid, -$amount, $name, $uname))
            return [false, bkT('dep_low', ['wallet' => bkNum(gmPoints($uid))])];

        $done = false;
        bkUserSet($uid, function (&$x) use ($amount, &$done) {
            bkSettleInterest($x);
            $x['vault']    = max(0.0, (float)($x['vault'] ?? 0)) + $amount;
            if ((int)($x['vault_at'] ?? 0) <= 0) $x['vault_at'] = time();
            $done = true;
        });
        if (!$done) { gmAdd($uid, $amount, $name, $uname); return [false, bkT('ask_bad_num')]; }

        $nv = bkVaultOf($uid);
        return [true, bkT('dep_ok', [
            'amount' => bkNum($amount),
            'wallet' => bkNum(gmPoints($uid)),
            'vault'  => bkNum($nv),
            'rate'   => rtrim(rtrim(number_format(bkRate($nv), 2, '.', ''), '0'), '.'),
        ])];
    }

    if ($amount > $vault + 1e-9)
        return [false, bkT('wd_low', ['vault' => bkNum($vault)])];

    $taken = false;
    bkUserSet($uid, function (&$x) use ($amount, &$taken) {
        bkSettleInterest($x);
        $cur = max(0.0, (float)($x['vault'] ?? 0));
        if ($cur + 1e-9 < $amount) return;
        $x['vault']    = $cur - $amount;
        $x['vault_at'] = time();
        $taken = true;
    });
    if (!$taken) return [false, bkT('wd_low', ['vault' => bkNum(bkVaultOf($uid))])];

    if (!gmAdd($uid, $amount, $name, $uname)) {
        bkUserSet($uid, function (&$x) use ($amount) {
            $x['vault'] = max(0.0, (float)($x['vault'] ?? 0)) + $amount;
        });
        return [false, bkT('ask_bad_num')];
    }

    return [true, bkT('wd_ok', [
        'amount' => bkNum($amount),
        'wallet' => bkNum(gmPoints($uid)),
        'vault'  => bkNum(bkVaultOf($uid)),
    ])];
}

function bkRate($amount) {
    $tiers = (array)bkVal('interest.tiers', bkDefaults()['interest']['tiers']);
    $rate  = 0.0;
    foreach ($tiers as $t) {
        $floor = (float)($t[0] ?? 0);
        if ((float)$amount + 1e-9 >= $floor) $rate = (float)($t[1] ?? 0);
    }
    return max(0.0, $rate);
}

function bkSettleInterest(array &$u) {
    if (empty(bkVal('interest.on', true))) return 0.0;

    $now   = time();
    $vault = max(0.0, (float)($u['vault'] ?? 0));
    $last  = (int)($u['vault_at'] ?? 0);
    if ($last <= 0) { $u['vault_at'] = $now; return 0.0; }
    if ($vault <= 0) { $u['vault_at'] = $now; return 0.0; }

    $day  = max(60, (int)bkVal('interest.day_secs', 86400));
    $dt   = max(0, $now - $last);
    $dt   = min($dt, max(1, (int)bkVal('interest.max_days', 30)) * $day);
    if ($dt <= 0) return 0.0;

    $rate = bkRate($vault) / 100.0;
    $gain = floor($vault * $rate * ($dt / $day));
    if ($gain <= 0) return 0.0;

    $u['vault']    = $vault + $gain;
    $u['vault_at'] = $now;
    return (float)$gain;
}

function bkVaultOf($uid) {
    $out = 0.0;
    bkUserSet($uid, function (&$u) use (&$out) {
        bkSettleInterest($u);
        $out = max(0.0, (float)($u['vault'] ?? 0));
    });
    return $out;
}

function bkTopText($n = null) {
    $rows = bkTop($n ?? (int)bkVal('top_n', 10));
    if (!$rows) return bkT('top_none');
    $out = bkT('top_head');
    $i = 1;
    foreach ($rows as $u) {
        $nm = trim((string)($u['name'] ?? ''));
        if ($nm === '' && function_exists('getUser')) $nm = trim((string)(getUser((int)($u['id'] ?? 0))['first_name'] ?? ''));
        if ($nm === '') $nm = (string)($u['id'] ?? '');
        $out .= "\n" . bkT('top_row', [
            'rank' => $i, 'name' => h($nm), 'bank' => bkNum($u['bank_balance'] ?? 0),
        ]);
        $i++;
    }
    return $out;
}


function bkHandlePending($raw, $uid, $chatId, $name, $uname, $pend, $replyToId = null) {
    $kind = $pend['kind'] ?? '';
    $pendMsg = (int)($pend['msg'] ?? 0);

    if ($kind === 'send_id') {
        if ($pendMsg > 0 && (int)$replyToId !== $pendMsg) {
            sendMsg(BOT_TOKEN, $chatId, bkT('send_need_reply'));
            return true;
        }
        $toId = bkResolveTarget($raw);
        if ($toId === null) {
            bkAskEdit($chatId, $pendMsg, bkT('ask_send_badid') . "\n\n" . bkT('ask_send_id'), [], $uid);
            return true;
        }
        if ($toId === (int)$uid) {
            bkAskEdit($chatId, $pendMsg, bkT('send_self') . "\n\n" . bkT('ask_send_id'), [], $uid);
            return true;
        }
        if (!function_exists('getUser') || !getUser($toId)) {
            bkAskEdit($chatId, $pendMsg, bkT('send_no_target') . "\n\n" . bkT('ask_send_id'), [], $uid);
            return true;
        }
        $amount = (float)($pend['data']['amount'] ?? 0);
        bkPendSet($uid, $chatId, 'send_confirm', $pendMsg, ['amount' => $amount, 'to' => $toId]);
        $toUser = function_exists('getUser') ? getUser($toId) : null;
        $toTag  = bkUserTag($toId, $toUser['first_name'] ?? '', $toUser['username'] ?? '');
        bkAskEdit($chatId, $pendMsg, bkT('send_confirm', ['amount' => bkNum($amount), 'to' => $toId, 'to_tag' => $toTag]),
            [[bkBtn('btn_send_confirm', [], 'bk_sendok_' . (int)$uid)]], $uid);
        return true;
    }

    if ($kind === 'send_amt') {
        $n = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        if ($n <= 0 || floor($n) != $n) { bkAskEdit($chatId, $pendMsg, bkT('ask_bad_num'), [], $uid); return true; }
        $wallet = gmPoints($uid);
        if ($n > $wallet + 1e-9) {
            bkPendClear($uid, $chatId);
            sendMsg(BOT_TOKEN, $chatId, bkT('send_low_wallet', ['wallet' => bkNum($wallet)]));
            if ($pendMsg) bkShow($uid, $chatId, $name, $pendMsg);
            return true;
        }
        bkPendSet($uid, $chatId, 'send_id', $pendMsg, ['amount' => $n]);
        bkAskEdit($chatId, $pendMsg, bkT('ask_send_id'), [], $uid);
        return true;
    }

    if ($kind === 'dep_amt' || $kind === 'wd_amt') {
        $dir  = $kind === 'dep_amt' ? 1 : -1;
        $word = mb_strtolower(trim($raw));
        if (in_array($word, ['همه', 'همش', 'all', 'کل'], true)) {
            $n = $dir > 0 ? floor(gmPoints($uid)) : floor(bkVaultOf($uid));
        } else {
            $n = (float)str_replace([',', '٬', ' '], '', norm_fa_digits($raw));
        }
        if ($n <= 0 || floor($n) != $n) { bkAskEdit($chatId, $pendMsg, bkT('ask_bad_num'), [], $uid); return true; }

        [$ok, $t] = bkVaultMove($uid, $n, $dir, $name, $uname);
        if (!$ok) { bkAskEdit($chatId, $pendMsg, $t, [], $uid); return true; }

        bkPendClear($uid, $chatId);
        bkEditCard($chatId, $pendMsg, $t, bkBackKb($uid));
        return true;
    }

    bkPendClear($uid, $chatId);
    if ($pendMsg) bkShow($uid, $chatId, $name, $pendMsg);
    return true;
}

function bkHandleText($text, $uid, $chatId, $name, $uname, $replyTo, $isPrivate, $msg = null) {
    if (!bkOn()) return false;
    if (!empty(bkVal('group_only', 0)) && $isPrivate) return false;

    $raw = trim((string)$text);
    if ($raw === '') return false;

    $pend = bkPendGet($uid, $chatId);
    if ($pend) {
        $kind = $pend['kind'] ?? '';
        if ($kind === 'send_id') {
            $looksLikeAnswer = bkLooksLikeTarget($raw);
        } elseif ($kind === 'send_amt' || $kind === 'dep_amt' || $kind === 'wd_amt') {
            $looksLikeAnswer = bkLooksLikeAmount($raw)
                || in_array(mb_strtolower(trim($raw)), ['همه', 'همش', 'all', 'کل'], true);
        } else {
            $looksLikeAnswer = false;
        }
        $replyToId = (int)($msg['reply_to_message']['message_id'] ?? 0);
        if ($looksLikeAnswer) return bkHandlePending($raw, $uid, $chatId, $name, $uname, $pend, $replyToId);
    }

    $said = bkNorm($raw);
    if ($said === bkNorm('برترین‌های بانک') || preg_match('~^/banktop(@\w+)?$~i', $raw)) {
        sendMsg(BOT_TOKEN, $chatId, bkTopText()); return true;
    }

    $isBank = (bool)preg_match('~^/bank(@\w+)?$~i', $raw);
    if (!$isBank) foreach (explode(',', (string)bkVal('word_bank', 'بانک')) as $w) {
        $w = bkNorm($w);
        if ($w !== '' && $said === $w) { $isBank = true; break; }
    }
    if ($isBank) { bkShow($uid, $chatId, $name, null, $msg['message_id'] ?? null); return true; }

    return false;
}

function bkNorm($s) {
    $s = mb_strtolower(trim((string)$s));
    $s = str_replace(['ي', 'ى', 'ك', 'ة', 'أ', 'إ'], ['ی', 'ی', 'ک', 'ه', 'ا', 'ا'], $s);
    $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $s);
    $s = preg_replace('/[\s\x{200C}\x{200D}\x{00A0}]+/u', '', (string)$s);
    return (string)preg_replace('/[!.؟?،,]+$/u', '', (string)$s);
}


function bkCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if ($data === 'bk_nop') { answerCb(BOT_TOKEN, $cbId); return true; }

    if (preg_match('/^bk_h_(high|normal|low)_\d+_\d+$/', (string)$data)) {
        answerCb(BOT_TOKEN, $cbId, bkT('ask_expired'), true);
        return true;
    }

    if (!preg_match('/^bk_(protect|send|sendok|cancelflow|back|dep|wd)_(\d+)$/', (string)$data, $m)) return false;
    $action  = $m[1];
    $ownerId = (int)$m[2];
    if (!bkOn()) { answerCb(BOT_TOKEN, $cbId); return true; }

    if ($ownerId !== (int)$uid) {
        answerCb(BOT_TOKEN, $cbId, bkT('not_your_card'), true);
        return true;
    }

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    if ($action === 'protect' || $action === 'back') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendClear($uid, $chatId);
        bkEditCard($chatId, $msgId, bkCardText($uid, $name), bkKb($uid));
        return true;
    }
    if ($action === 'send') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendSet($uid, $chatId, 'send_amt', $msgId);
        bkAskEdit($chatId, $msgId, bkT('ask_send_amt'), [], $uid);
        return true;
    }
    if ($action === 'dep' || $action === 'wd') {
        answerCb(BOT_TOKEN, $cbId);
        $vars = ['wallet' => bkNum(gmPoints($uid)), 'vault' => bkNum(bkVaultOf($uid))];
        bkPendSet($uid, $chatId, $action === 'dep' ? 'dep_amt' : 'wd_amt', $msgId);
        bkAskEdit($chatId, $msgId, bkT($action === 'dep' ? 'dep_ask' : 'wd_ask', $vars), [], $uid);
        return true;
    }
    if ($action === 'cancelflow') {
        answerCb(BOT_TOKEN, $cbId);
        bkPendClear($uid, $chatId);
        bkEditCard($chatId, $msgId, bkCardText($uid, $name), bkKb($uid));
        return true;
    }
    if ($action === 'sendok') {
        $pend = bkPendGet($uid, $chatId);
        if (!$pend || $pend['kind'] !== 'send_confirm') {
            answerCb(BOT_TOKEN, $cbId, bkT('ask_expired'), true);
            return true;
        }
        bkPendClear($uid, $chatId);
        $amount = (float)($pend['data']['amount'] ?? 0);
        $toId   = (int)($pend['data']['to'] ?? 0);
        [$ok, $t] = bkSendDiamond($uid, $toId, $name, $uname, $amount);
        answerCb(BOT_TOKEN, $cbId, $ok ? '🎁' : '', !$ok);
        sendMsg(BOT_TOKEN, $chatId, $t);
        if (!empty($pend['msg'])) bkShow($uid, $chatId, $name, (int)$pend['msg']);
        return true;
    }
    return false;
}


function bkLabels() {
    return [
        'card' => 'کارتِ بانک',
        'ask_bad_num' => 'عددِ نامعتبر', 'ask_expired' => 'درخواستِ منقضی', 'not_your_card' => 'کارتِ کسِ دیگری — رد شد',
        'top_head' => 'برترین‌ها — سر', 'top_row' => 'برترین‌ها — ردیف', 'top_none' => 'برترین‌ها — خالی',
        'ask_send_amt' => 'ارسال — مقدار', 'ask_send_id' => 'ارسال — آیدی', 'ask_send_badid' => 'ارسال — آیدیِ بد',
        'send_need_reply' => 'ارسال — بدونِ ریپلای',
        'send_self' => 'ارسال — به خودت', 'send_no_target' => 'ارسال — گیرنده ناشناس',
        'send_low_wallet' => 'ارسال — کیف‌پول کم', 'send_confirm' => 'ارسال — تاییدیه', 'send_ok' => 'ارسال — موفق',
        'btn_dep' => 'دکمه: انتقال به بانک', 'btn_wd' => 'دکمه: برداشت از بانک',
        'dep_ask' => 'انتقال به بانک — پرسشِ مبلغ', 'dep_ok' => 'انتقال به بانک — موفق',
        'int_on' => 'سودِ بانک',
        'dep_low' => 'انتقال به بانک — کیفِ‌پول کم',
        'wd_ask' => 'برداشت از بانک — پرسشِ مبلغ', 'wd_ok' => 'برداشت از بانک — موفق',
        'wd_low' => 'برداشت از بانک — صندوق کم',
        'btn_send' => 'دکمه: ارسال به کاربر', 'btn_send_confirm' => 'دکمه: تاییدِ ارسال', 'btn_back' => 'دکمه: برگشت',
    ];
}
function bkLabel($k) { return bkLabels()[$k] ?? $k; }
function bkBtnKeys() { return ['btn_dep', 'btn_wd', 'btn_send', 'btn_send_confirm', 'btn_back']; }

function bkAdminHome($chatId, $msgId = null) {
    $c = bkCfg();
    $t  = "🏦 <b>بانک</b>\n\n";
    $t .= 'وضعیت: ' . (bkOn() ? '✅ روشن' : '❌ خاموش') . "\n\n";
    $t .= 'کلمه‌ی باز کردنِ بانک: <code>' . h($c['word_bank']) . "</code> یا <code>/bank</code>\n";
    $t .= 'کجا کار کند: <b>' . (empty($c['group_only']) ? 'گروه و پیوی ربات' : 'فقط گروه') . "</b>\n";
    if (!empty($c['interest']['on'])) {
        $rs = [];
        foreach ((array)($c['interest']['tiers'] ?? []) as $tt)
            $rs[] = bkNum($tt[0] ?? 0) . '+ → ' . (float)($tt[1] ?? 0) . '٪';
        $t .= "\n📈 <b>سودِ روزانه‌ی صندوق</b>\n" . implode("\n", $rs) . "\n";
    }

    $rows = [
        [btnCb(bkOn() ? '✅ روشن' : '❌ خاموش', 'bkax', 'info')],
        [btnCb(empty($c['group_only']) ? '📍 گروه و پیوی' : '📍 فقط گروه', 'bkagrp', 'info')],
        [btnCb('🗣 کلمه‌ها', 'bkaw_home', 'admin'), btnCb('✏️ متن‌ها و دکمه‌ها', 'bkat_home', 'admin')],
        [btnCb((empty($c['card_image']) ? '🖼 کارتِ گرافیکی: خاموش' : '🖼 کارتِ گرافیکی: روشن')
               . (function_exists('bcReady') && !bcReady() ? ' ⚠️' : ''), 'bkacard', 'info')],
        [btnCb('🎨 رنگِ دکمه‌ها', 'bkacolors', 'admin'),
         btnCb('🔤 فونتِ کارت', 'bkafont', 'admin')],
        [btnCb(!empty(bkVal('interest.on', true)) ? '📈 سودِ بانک: روشن' : '📈 سودِ بانک: خاموش', 'bkaint', 'info')],
        [btnCb(UT('back'), 'ag_games', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function bkTextsCfg() {
    return [
        'title' => 'متن‌های بانک',
        'keys'  => array_keys(bkDefaults()['texts']),
        'popup' => ['ask_expired', 'not_your_card'],
        'btns'  => bkBtnKeys(),
        'label' => 'bkLabel',
        'value' => function ($k) { return (string)bkVal('texts.' . $k, ''); },
        'cb'    => 'bkat_',
        'edit'  => 'bkats_',
        'back'  => 'bk_home',
    ];
}

function bkAdminFont($chatId, $msgId) {
    if (!function_exists('bcFontList')) {
        editMsg(BOT_TOKEN, $chatId, $msgId, '⚠️ ماژولِ کارتِ گرافیکی در دسترس نیست.',
            inlineKb([[btnCb(UT('back'), 'bk_home', 'nav')]]));
        return;
    }
    $cur     = trim((string)bkVal('card_font', ''));
    $curBold = trim((string)bkVal('card_font_bold', ''));
    $eff     = function_exists('bcFont') ? (string)(bcFont(true) ?: '') : '';
    $follows = function_exists('bcFontFollowsPrices') && bcFontFollowsPrices();
    $list    = bcFontList();

    $t  = "🔤 <b>فونتِ کارتِ بانک</b>\n\n";
    $t .= 'الان: <code>' . h($eff !== '' ? basename($eff) : 'پیدا نشد') . "</code>\n";
    $t .= 'منبع: ' . ($cur !== '' ? '📌 مخصوصِ بانک'
                                  : ($follows ? '🔗 همان فونتِ «قیمت لحظه‌ای»' : '🔎 خودکار')) . "\n";
    if ($cur !== '' && $curBold !== '') $t .= 'بولد: <code>' . h(basename($curBold)) . "</code>\n";



    $rows = [];
    $i = 0;
    foreach ($list as $path => $m) {
        $on  = ($path === $cur) ? '✅ ' : '';
        $fa  = !empty($m['fa']) ? ' · فا' : '';
        $rows[] = [btnCb($on . mb_substr($m['name'], 0, 34) . $fa, 'bkafont_' . $i, 'info')];
        $i++;
        if ($i >= 20) break;
    }
    if (!$rows) $t .= "\n\n🔴 هیچ فونتی رویِ سرور پیدا نشد.";

    $rows[] = [btnCb('📤 فرستادن فونتِ تازه', 'bkafontup', 'confirm')];
    if ($cur !== '' || $curBold !== '')
        $rows[] = [btnCb('🔗 برگرد به فونتِ بخشِ قیمت', 'bkafontclr', 'reject')];
    $rows[] = [btnCb('💹 رفتن به فونتِ بخشِ قیمت', 'pxcard', 'admin')];
    $rows[] = [btnCb('👀 نمونه‌ی کارت', 'bkafontprev', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminColors($chatId, $msgId) {
    $t = "🎨 <b>رنگِ دکمه‌های بانک</b>\n\n";
    $rows = [];
    foreach (bkBtnKeys() as $k) {
        $color = (string)bkVal('btns.' . $k . '.color', 'none');
        $t .= '• ' . h(bkLabel($k)) . ': <b>' . h(styleMap()[$color] ?? $color) . "</b>\n";
        $rows[] = [btnCb(bkLabel($k), 'bkacolk_' . $k, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminColorPick($chatId, $msgId, $k) {
    $cur = (string)bkVal('btns.' . $k . '.color', 'none');
    $t = "🎨 رنگِ <b>" . h(bkLabel($k)) . "</b>\n\nالان: <b>" . h(styleMap()[$cur] ?? $cur) . "</b>";
    $rows = [];
    foreach (styleMap() as $sk => $sl) $rows[] = [btnCb(($sk === $cur ? '✅ ' : '') . $sl, 'bkacolv_' . $k . '_' . $sk, 'info')];
    $rows[] = [btnCb(UT('back'), 'bkacolors', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminWords($chatId, $msgId) {
    $c = bkCfg();
    $t = "🗣 <b>کلمه‌های بانک</b>\n\nهر کلمه را با ویرگول جدا کنید.\n\n";
    $map = ['word_bank' => 'باز کردنِ بانک'];
    $rows = [];
    foreach ($map as $k => $lbl) {
        $t .= '• <b>' . h($lbl) . '</b>: <code>' . h((string)$c[$k]) . "</code>\n";
        $rows[] = [btnCb($lbl, 'bkaws_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'bk_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function bkAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with((string)$data, 'bk')) return false;

    if ($data === 'bk_home') { answerCb(BOT_TOKEN, $cbId); bkAdminHome($chatId, $msgId); return true; }
    if ($data === 'bkacard') {
        bkSet(function (&$c) { $c['card_image'] = empty($c['card_image']) ? 1 : 0; });
        $warn = (function_exists('bcReady') && !bcReady())
            ? '⚠️ روی این هاست GD یا فونتِ فارسی نیست — کارت متنی می‌ماند.' : '✅';
        answerCb(BOT_TOKEN, $cbId, $warn, true); bkAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'bkax' || $data === 'bkagrp') {
        $k  = $data === 'bkax' ? 'on' : 'group_only';
        $on = !empty(bkVal($k));
        bkSet(function (&$c) use ($k, $on) { $c[$k] = $on ? 0 : 1; });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'bkaw_home') { answerCb(BOT_TOKEN, $cbId); bkAdminWords($chatId, $msgId); return true; }
    if (txRoute(bkTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;

    if ($data === 'bkafont') { answerCb(BOT_TOKEN, $cbId); bkAdminFont($chatId, $msgId); return true; }
    if (preg_match('/^bkafont_(\d+)$/', $data, $m) && function_exists('bcFontList')) {
        $paths = array_keys(bcFontList());
        $p = $paths[(int)$m[1]] ?? '';
        if ($p === '') { answerCb(BOT_TOKEN, $cbId, '❌ پیدا نشد', true); return true; }
        $bold = '';
        foreach (['-Bold', 'Bold', '-bold'] as $suf) {
            $cand = preg_replace('/(-?(Regular|regular))?\.ttf$/i', $suf . '.ttf', $p);
            if ($cand !== $p && is_file($cand)) { $bold = $cand; break; }
        }
        bkSet(function (&$c) use ($p, $bold) { $c['card_font'] = $p; $c['card_font_bold'] = $bold; });
        if (function_exists('bcFontBust')) bcFontBust();
        if (function_exists('bcIdCachePath')) @unlink(bcIdCachePath());
        answerCb(BOT_TOKEN, $cbId, '✅ ' . basename($p));
        bkAdminFont($chatId, $msgId);
        return true;
    }
    if ($data === 'bkafontclr') {
        bkSet(function (&$c) { $c['card_font'] = ''; $c['card_font_bold'] = ''; });
        if (function_exists('bcFontBust')) bcFontBust();
        if (function_exists('bcIdCachePath')) @unlink(bcIdCachePath());
        answerCb(BOT_TOKEN, $cbId, '🔗 از بخشِ قیمت');
        bkAdminFont($chatId, $msgId);
        return true;
    }
    if ($data === 'bkafontup') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'bk_font', []);
        sendMsg(BOT_TOKEN, $chatId,
            "🔤 <code>.ttf</code>",
            inlineKb([[btnCb('انصراف', 'bkafont', 'cancel')]]));
        return true;
    }
    if ($data === 'bkafontprev') {
        answerCb(BOT_TOKEN, $cbId, '🖼 نمونه…');
        if (function_exists('bcSend')) {
            bcSend($chatId, '👀 نمونه‌ی کارت با فونتِ فعلی', [
                'name' => 'محمدرضا حسینی', 'username' => 'numbix', 'uid' => 1,
                'locked' => 1234567, 'level' => 3,
            ]);
        }
        return true;
    }

    if ($data === 'bkaint') {
        bkSet(function (&$c) {
            if (!is_array($c['interest'] ?? null)) $c['interest'] = bkDefaults()['interest'];
            $c['interest']['on'] = empty($c['interest']['on']);
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminHome($chatId, $msgId); return true;
    }

    if ($data === 'bkacolors') { answerCb(BOT_TOKEN, $cbId); bkAdminColors($chatId, $msgId); return true; }
    if (preg_match('/^bkacolk_(\w+)$/', $data, $m) && in_array($m[1], bkBtnKeys(), true)) {
        answerCb(BOT_TOKEN, $cbId); bkAdminColorPick($chatId, $msgId, $m[1]); return true;
    }
    if (preg_match('/^bkacolv_(\w+)_(\w+)$/', $data, $m) && in_array($m[1], bkBtnKeys(), true) && isset(styleMap()[$m[2]])) {
        bkSet(function (&$c) use ($m) {
            if (!is_array($c['btns'][$m[1]] ?? null)) $c['btns'][$m[1]] = [];
            $c['btns'][$m[1]]['color'] = $m[2];
        });
        answerCb(BOT_TOKEN, $cbId, '✅'); bkAdminColorPick($chatId, $msgId, $m[1]); return true;
    }

    foreach (['bkats_' => ['bk_text', 'texts.'], 'bkaws_' => ['bk_word', '']] as $pre => [$act, $path]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if ($act === 'bk_word' && $k !== 'word_bank') { answerCb(BOT_TOKEN, $cbId); bkAdminWords($chatId, $msgId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['k' => $k]);
        $cur = (string)bkVal($path . $k, '');
        $back = inlineKb([[btnCb(UT('back'), $act === 'bk_text' ? 'bkat_home' : 'bkaw_home', 'cancel')]]);
        if ($act === 'bk_text' && bkIsButtonKey($k)) {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(bkLabel($k)) . "</b>\n<code>" . h($cur) . "</code>", $back);
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ <b>" . h(bkLabel($k) ?: $k) . "</b>\n\n" .
                "الان:\n" . ($act === 'bk_text' ? $cur : '<code>' . h($cur) . '</code>'), $back);
        }
        return true;
    }

    return false;
}

function bkStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'bk_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('🏦 بانک', 'bk_home', 'admin')]]);
    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };

    if ($action === 'bk_font') {
        $doc = $msg['document'] ?? null;
        if (!$doc) { sendMsg(BOT_TOKEN, $chatId, '⚠️ فایلِ <code>.ttf</code> لازم است'); return true; }

        $name = (string)($doc['file_name'] ?? 'font.ttf');
        if (!preg_match('/\.ttf$/i', $name)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط <code>.ttf</code>."); return true;
        }
        if ((int)($doc['file_size'] ?? 0) > 12 * 1024 * 1024) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل خیلی بزرگ است (بیشتر از ۱۲ مگابایت)."); return true;
        }

        $r    = tg(BOT_TOKEN, 'getFile', ['file_id' => (string)$doc['file_id']], 15);
        $path = (string)($r['result']['file_path'] ?? '');
        if ($path === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ فایل از تلگرام گرفته نشد."); return true; }

        $ch = curl_init(rtrim(TG_API_BASE, '/') . '/file/bot' . BOT_TOKEN . '/' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 8]);
        $bytes = monCurl($ch, 'telegram');
        curl_close($ch);
        if (!is_string($bytes) || strlen($bytes) < 1000) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ دانلودِ فونت نشد."); return true;
        }

        $dir = rtrim(DATA_DIR, '/') . '/fonts';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $dst = $dir . '/' . preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        if (@file_put_contents($dst, $bytes) === false) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ نوشتن نشد — پوشه‌ی داده اجازه‌ی نوشتن ندارد."); return true;
        }

        bkSet(function (&$c) use ($dst) { $c['card_font'] = $dst; $c['card_font_bold'] = $dst; });
        if (function_exists('bcFontBust')) bcFontBust();
        if (function_exists('bcIdCachePath')) @unlink(bcIdCachePath());
        clearState($uid);

        $fa = function_exists('bcFontHasFa') ? bcFontHasFa($dst) : true;
        sendMsg(BOT_TOKEN, $chatId,
            '✅ فونت ثبت شد: <code>' . h(basename($dst)) . '</code>' .
            ($fa ? '' : "\n\n⚠️ این فونت حرفِ فارسی ندارد — اسم‌های فارسی مربعِ خالی می‌شوند."),
            inlineKb([[btnCb('🔤 فونتِ کارت', 'bkafont', 'admin')]]));
        return true;
    }

    if ($action === 'bk_word') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '' || $text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی نمی‌شود."); return true; }
        bkSet(function (&$c) use ($k, $text) { $c[$k] = $text; });
        return $done();
    }
    if ($action === 'bk_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ چیزی برای ذخیره نیست."); return true; }

        if (bkIsButtonKey($k)) {
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
                $clean = textWithoutCustomEmoji($msg);
                if ($clean !== '') $text = $clean;
            }
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            bkSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد" . ($icon !== '' ? " — ایموجیِ پریمیوم هم رویِ دکمه نشست." : '.') . "\n\nاین‌طور دیده می‌شود:",
                inlineKb([[bkBtn($k, [], 'bk_nop')]]));
            sendMsg(BOT_TOKEN, $chatId, '👆', $back);
            return true;
        }

        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        bkSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
        return true;
    }
    clearState($uid);
    return true;
}

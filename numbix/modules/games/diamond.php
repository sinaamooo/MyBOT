<?php
defined('NB_ROOT') || exit;


function dmDefaults() {
    return [
        'on'         => false,
        'word'       => 'الماس',
        'aliases'    => '',
        'cooldown'   => 300,
        'base'       => 56.74,
        'ratio'      => 1.2336,
        'min'        => 20,
        'cap'        => 1000000000,

        'gift' => [
            'on'    => false,
            'cost'  => 100000,
            'item'  => '',
            'word'  => 'هدیه',
            'limit' => 0,
        ],
        'group_only' => 1,
        'top_n'      => 10,
        'level_step' => 10000,

        'jail' => [
            'words' => 'میو,هاپ',
            'need'  => 3,
            'secs'  => 3600,
            'color' => 'danger',
            'icon'  => '',
        ],

        'to_wallet'  => 0,
        'min_swap'   => 10000,

        'texts' => [
            'win'      => "💎 <b>الماس!</b> {name}\n\n✨ +{reward} الماس\n💰 موجودی: {points}\n⭐️ سطح: {level}",
            'levelup'  => "\n\n🎉 <b>لِول آپ!</b> به سطح {level} رسیدی!",
            'wait'     => "⏳ {name}، هنوز زود است!\n⌛️ {m} دقیقه و {s} ثانیه دیگر می‌توانی الماس بزنی 💎",
            'private'  => "💎 الماس فقط داخل گروه کار می‌کند.\nمن را به گروهت اضافه کن.",
            'me'       => "💎 <b>{name}</b>\n\n✨ الماس: <b>{points}</b>\n⭐️ سطح: <b>{level}</b>\n🔁 تعداد الماس: <b>{total}</b>\n📈 تا سطح بعد: <b>{left}</b>",
            'me_none'  => "هنوز الماسی نزده‌ای",
            'top_head' => "🏆 <b>برترین‌های الماس</b>\n",
            'top_row'  => "{rank}. {name} — <b>{points}</b> 💎 (سطح {level})",
            'top_none' => "هنوز کسی الماس نزده است.",
            'swap_ok'  => "✅ <b>{n}</b> الماس به <b>{toman}</b> تومان تبدیل شد.\n💰 موجودی کیف پول: <b>{bal}</b> تومان",
            'swap_low' => "❌ حداقل <b>{min}</b> الماس لازم است. الماس تو: <b>{points}</b>",
            'swap_off' => "🔒 تبدیل الماس به کیف پول فعلا بسته است.",
            'gift_ok'  => "🎁 <b>هدیه‌ات آماده شد!</b>\n\n<blockquote>☎️ {item}\n💎 خرج شد: <b>{cost}</b> الماس\n✨ الماس باقی‌مانده: <b>{points}</b></blockquote>\n\n📲 شماره و کد در پیوی ربات برایت می‌آید.\n🧾 کد پیگیری: <code>{code}</code>",
            'gift_fail'=> "❌ شماره‌ی هدیه الان آماده نشد — الماس‌هایت برگشت.\n\n{why}",
            'gift_low' => "❌ برای این هدیه <b>{cost}</b> الماس لازم است.\n💎 الماس تو: <b>{points}</b>",
            'gift_off' => "🔒 هدیه فعلا بسته است.",
            'gift_had' => "🎁 سقف دریافت این هدیه برای تو پر شده است.",

            'jail_hit'   => "🚨 {name} به دام افتاد و باید بره زندان!\n\n{need} نفر از اعضا باید تاییدش کنن.",
            'jail_prog'  => "🚨 {name} به دام افتاد و باید بره زندان!\n\n✅ {got} از {need} نفر تایید کردن.",
            'jail_btn'   => "🔒 بفرستش زندان",
            'jail_done'  => "🚔 {name} به زندان افتاد!\n\n⏳ به مدت {dur} نمی‌تواند الماس بزند.",
            'jail_self'  => "نمی‌تونی خودتو بفرستی زندان 😄",
            'jail_twice' => "قبلاً همینو تایید کرده بودی.",
            'jail_wait'  => "⛔️ {name}، الان زندانی‌ای!\n⌛️ {m} دقیقه و {s} ثانیه دیگر آزاد می‌شوی.",
        ],
    ];
}

function dmCfg() {
    $c = cfg()['diamond'] ?? null;
    return is_array($c) ? array_replace_recursive(dmDefaults(), $c) : dmDefaults();
}

function dmSet(callable $fn) {
    cfgSet(function (&$c) use ($fn) {
        if (!is_array($c['diamond'] ?? null)) $c['diamond'] = dmDefaults();
        $fn($c['diamond']);
    });
}

function dmVal($path, $default = null) {
    $v = dmCfg();
    foreach (explode('.', $path) as $seg) {
        if (!is_array($v) || !array_key_exists($seg, $v)) return $default;
        $v = $v[$seg];
    }
    return $v;
}

function dmT($slug, $vars = []) {
    $t = (string)(dmVal('texts.' . $slug) ?? dmDefaults()['texts'][$slug] ?? $slug);
    if (dmIsButtonTextKey($slug)) $t = strip_tags($t);
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function dmIsButtonTextKey($slug) {
    return in_array($slug, ['jail_btn'], true);
}

function dmOn() { return !empty(dmVal('on')); }


function dmStep() { return max(1, (int)dmVal('level_step', 10000)); }

function dmLevel($points) {
    $p = max(0.0, (float)$points);
    return min(1000, (int)floor($p / dmStep()) + 1);
}

function dmNextAt($level) {
    $level = (int)$level;
    return ($level >= 1000) ? 0 : $level * dmStep();
}

function dmReward($level) {
    $c = dmCfg();
    $base  = max(1.0, (float)$c['base']);
    $ratio = max(1.0, (float)$c['ratio']);
    $min   = max(1, (int)$c['min']);
    $cap   = max($min, (int)$c['cap']);

    $max = $base * pow($ratio, max(0, (int)$level - 1));
    if (!is_finite($max) || $max > $cap) $max = $cap;
    $max = (int)$max;
    if ($max <= $min) return $min;

    if ($level >= 43) {
        $mid = (int)($max * 0.60);
        if ($mid <= $min) $mid = $min + 1;
        return (random_int(1, 100) <= 60) ? random_int($min, $mid) : random_int($mid, $max);
    }
    return random_int($min, $max);
}


function diamondDbPath() { return DATA_DIR . '/diamond_users.sqlite'; }

function diamondDb() {
    static $db = null;
    if ($db) return $db;
    if (!class_exists('SQLite3') && !dbOn()) return null;

    $path = diamondDbPath();
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fresh = !is_file($path);

    try {
        $db = nbRawOpen($path);
    } catch (Throwable $e) {
        error_log('[diamond] diamond_users.sqlite باز نشد: ' . $e->getMessage());
        return null;
    }
    $db->busyTimeout(5000);
    $db->exec('PRAGMA journal_mode = WAL');
    $db->exec('PRAGMA synchronous = NORMAL');
    $db->exec('CREATE TABLE IF NOT EXISTS diamond_users (
        id INTEGER PRIMARY KEY, points REAL NOT NULL DEFAULT 0, data TEXT NOT NULL
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_diamond_points ON diamond_users(points DESC)');

    if ($fresh) diamondImportFromJson($db);
    return $db;
}

function diamondImportFromJson($db) {
    $old = dataPath('diamond_users');
    if (!is_file($old)) return;
    $raw = @file_get_contents($old);
    $arr = $raw ? json_decode($raw, true) : null;

    if (is_array($arr) && $arr) {
        $db->exec('BEGIN');
        $stmt = $db->prepare('INSERT OR REPLACE INTO diamond_users (id, points, data) VALUES (:id, :pts, :data)');
        foreach ($arr as $k => $v) {
            $id = (int)$k;
            if ($id <= 0 || !is_array($v)) continue;
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->bindValue(':pts', (float)($v['points'] ?? 0), SQLITE3_FLOAT);
            $stmt->bindValue(':data', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
            $stmt->execute();
            $stmt->reset();
        }
        $db->exec('COMMIT');
    }
    @rename($old, $old . '.migrated');
}

function dmUser($uid) {
    $db = diamondDb();
    if (!$db) return null;
    $stmt = $db->prepare('SELECT data FROM diamond_users WHERE id = :id');
    $stmt->bindValue(':id', (int)$uid, SQLITE3_INTEGER);
    $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $d = json_decode($row['data'], true);
    return is_array($d) ? $d : null;
}

function dmUserSet($uid, callable $fn) {
    $db = diamondDb();
    if (!$db) return null;
    $id = (int)$uid;
    $dPts = 0.0; $dHops = 0; $dUsers = 0;

    if (!@$db->exec('BEGIN IMMEDIATE')) return null;
    try {
        $stmt = $db->prepare('SELECT data FROM diamond_users WHERE id = :id');
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        $u = $row ? json_decode($row['data'], true) : null;
        if (!is_array($u)) {
            $u = ['id' => $id, 'name' => '', 'username' => '',
                  'points' => 0, 'total' => 0, 'level' => 1, 'last' => 0,
                  'joined_at' => nowStr()];
            $dUsers = 1;
        }
        $wasP = (float)($u['points'] ?? 0);
        $wasH = (int)  ($u['total']  ?? 0);
        $result = $fn($u);
        $dPts  = (float)($u['points'] ?? 0) - $wasP;
        $dHops = (int)  ($u['total']  ?? 0) - $wasH;

        $up = $db->prepare('INSERT OR REPLACE INTO diamond_users (id, points, data) VALUES (:id, :pts, :data)');
        $up->bindValue(':id', $id, SQLITE3_INTEGER);
        $up->bindValue(':pts', (float)($u['points'] ?? 0), SQLITE3_FLOAT);
        $up->bindValue(':data', json_encode($u, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), SQLITE3_TEXT);
        if (!$up->execute()) throw new RuntimeException('write');
        if (!@$db->exec('COMMIT')) throw new RuntimeException('commit');
    } catch (Throwable $e) {
        @$db->exec('ROLLBACK');
        error_log('[diamond] dmUserSet خطا: ' . $e->getMessage());
        return null;
    }

    dmSumBump($dPts, $dHops, $dUsers);
    return $result;
}


function dmSum() {
    $s = load('diamond_sum');
    if (!is_array($s) || !isset($s['points'])) return dmSumRebuild();
    return ['users'  => (int)($s['users'] ?? 0),
            'points' => (float)($s['points'] ?? 0),
            'total'  => (int)($s['total'] ?? 0)];
}

function dmSumBump($dPts, $dHops, $dUsers = 0) {
    if ((float)$dPts == 0.0 && (int)$dHops === 0 && (int)$dUsers === 0) return;
    mutate('diamond_sum', function (&$s) use ($dPts, $dHops, $dUsers) {
        if (!is_array($s) || !isset($s['points'])) { $s = dmSumWalk(); return; }
        $s['points'] = max(0, (float)$s['points'] + (float)$dPts);
        $s['total']  = max(0, (int)$s['total']  + (int)$dHops);
        $s['users']  = max(0, (int)$s['users']  + (int)$dUsers);
        $s['at']     = time();
    });
}

function dmSumWalk() {
    $db = diamondDb();
    $sum = 0.0; $hops = 0; $count = 0;
    if ($db) {
        $res = $db->query('SELECT data FROM diamond_users');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $u = json_decode($row['data'], true);
            if (!is_array($u)) continue;
            $sum += (float)($u['points'] ?? 0); $hops += (int)($u['total'] ?? 0); $count++;
        }
    }
    return ['users' => $count, 'points' => $sum, 'total' => $hops, 'at' => time()];
}

function dmSumRebuild() {
    $s = dmSumWalk();
    save('diamond_sum', $s);
    return ['users' => (int)$s['users'], 'points' => (float)$s['points'], 'total' => (int)$s['total']];
}

function dmTop($n = 10) {
    $n = max(1, (int)$n);
    $db = diamondDb();
    $top = [];
    if ($db) {
        $stmt = $db->prepare('SELECT data FROM diamond_users ORDER BY points DESC LIMIT :n');
        $stmt->bindValue(':n', $n, SQLITE3_INTEGER);
        $res = $stmt->execute();
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $u = json_decode($row['data'], true);
            if (is_array($u)) $top[] = $u;
        }
    }
    return $top;
}

function dmStats() { return dmSum(); }


function dmIsWord($text) {
    $t = trim(mb_strtolower(norm_fa_digits((string)$text)));
    if ($t === '') return false;
    $words = [trim(mb_strtolower((string)dmVal('word', 'الماس')))];
    foreach (explode(',', (string)dmVal('aliases', '')) as $w) {
        $w = trim(mb_strtolower($w));
        if ($w !== '') $words[] = $w;
    }
    return in_array($t, array_filter($words), true);
}

function dmSecs($text) {
    $t = mb_strtolower(trim(norm_fa_digits((string)$text)));
    if (!preg_match('/^([\d.,٬]+)\s*(.*)$/u', $t, $m)) return null;
    $n = (float)str_replace([',', '٬'], '', $m[1]);
    if ($n <= 0) return null;

    $unit = trim($m[2]);
    if ($unit === '') return (int)round($n);
    if (preg_match('/^(ث|ثانیه|s|sec|secs|second|seconds)$/u', $unit)) return (int)round($n);
    if (preg_match('/^(د|دق|دقیقه|m|min|mins|minute|minutes)$/u', $unit)) return (int)round($n * 60);
    if (preg_match('/^(س|ساعت|h|hr|hour|hours)$/u', $unit)) return (int)round($n * 3600);
    return null;
}

function dmDur($secs) {
    $s = max(0, (int)$secs);
    if ($s >= 3600 && $s % 3600 === 0) return ($s / 3600) . ' ساعت';
    if ($s >= 60   && $s % 60 === 0)   return ($s / 60) . ' دقیقه';
    if ($s >= 60) return intdiv($s, 60) . ' دقیقه و ' . ($s % 60) . ' ثانیه';
    return $s . ' ثانیه';
}

function dmHit($uid, $name, $username = '') {
    $now = time();

    $u0 = dmUser($uid);
    $jailLeft = (int)($u0['jailed_until'] ?? 0) - $now;
    if ($jailLeft > 0) {
        return [dmT('jail_wait', ['name' => $name, 'm' => intdiv($jailLeft, 60), 's' => $jailLeft % 60]), false];
    }

    $cd = max(5, (int)dmVal('cooldown', 300));

    $u = dmUser($uid);
    if ($u) {
        $left = $cd - ($now - (int)($u['last'] ?? 0));
        if ($left > 0) {
            return [dmT('wait', [
                'name' => $name,
                'm' => intdiv($left, 60),
                's' => $left % 60,
                'left' => $left,
            ]), false];
        }
    }

    $res = dmUserSet($uid, function (&$x) use ($name, $username, $now) {
        $cd = max(5, (int)dmVal('cooldown', 300));
        $waitLeft = $cd - ($now - (int)($x['last'] ?? 0));
        if ($waitLeft > 0) return ['wait' => true, 'left' => $waitLeft];

        $level  = dmLevel((float)($x['points'] ?? 0));
        $reward = dmReward($level);

        $x['name']     = $name;
        $x['username'] = $username;
        $x['total']    = (int)$x['total'] + 1;
        $x['points']   = (float)$x['points'] + $reward;
        $x['last']     = $now;
        $newLevel      = dmLevel((float)$x['points']);
        $x['level']    = $newLevel;

        return ['reward' => $reward, 'points' => $x['points'],
                'total' => $x['total'], 'level' => $newLevel, 'up' => $newLevel > $level];
    });

    if (!is_array($res)) return ['', false];
    if (!empty($res['wait'])) {
        $left = max(0, (int)$res['left']);
        return [dmT('wait', ['name' => $name, 'm' => intdiv($left, 60), 's' => $left % 60, 'left' => $left]), false];
    }

    $next = dmNextAt($res['level']);

    $msg = dmT('win', [
        'name'     => $name,
        'reward'   => number_format($res['reward']),
        'points'   => number_format($res['points']),
        'level'    => $res['level'],
        'total'    => number_format($res['total']),
        'progress' => $next > 0 ? number_format($res['points']) . '/' . number_format($next) : 'MAX',
    ]);
    if ($res['up']) $msg .= dmT('levelup', ['level' => $res['level']]);

    return [$msg, true];
}

function dmMeText($uid, $name) {
    $u = dmUser($uid);
    if (!$u) return dmT('me_none', ['word' => dmVal('word', 'الماس')]);
    $level = dmLevel((float)($u['points'] ?? 0));
    $next  = dmNextAt($level);
    return dmT('me', [
        'name'   => $u['name'] ?: $name,
        'points' => number_format((float)$u['points']),
        'level'  => $level,
        'total'  => number_format((int)$u['total']),
        'left'   => $next > 0 ? number_format(max(0, $next - (float)($u['points'] ?? 0))) : '—',
    ]);
}

function dmTopText() {
    $rows = dmTop((int)dmVal('top_n', 10));
    if (!$rows) return dmT('top_none');
    $t = dmT('top_head') . "\n";
    $i = 0;
    foreach ($rows as $u) {
        $i++;
        $t .= dmT('top_row', [
            'rank'   => $i,
            'name'   => h(trim((string)($u['name'] ?? '')) ?: ('کاربر ' . (int)($u['id'] ?? 0))),
            'points' => number_format((float)$u['points']),
            'level'  => dmLevel((float)($u['points'] ?? 0)),
        ]) . "\n";
    }
    return $t;
}

function dmSwap($uid) {
    $rate = (float)dmVal('to_wallet', 0);
    if ($rate <= 0) return dmT('swap_off');

    $u = dmUser($uid);
    $pts = (float)($u['points'] ?? 0);
    $min = (float)dmVal('min_swap', 10000);
    if ($pts < $min)
        return dmT('swap_low', ['min' => number_format($min), 'points' => number_format($pts)]);

    $taken = dmUserSet($uid, function (&$x) use ($min) {
        $p = (float)$x['points'];
        if ($p < $min) return 0;
        $x['points'] = 0;
        return $p;
    });
    if ($taken <= 0) return dmT('swap_low', ['min' => number_format($min), 'points' => '0']);

    $toman = round($taken * $rate);
    addBalance($uid, $toman, 'diamond_swap');
    return dmT('swap_ok', [
        'n'     => number_format($taken),
        'toman' => number_format($toman),
        'bal'   => number_format((float)(getUser($uid)['balance'] ?? 0)),
    ]);
}

function dmGift($uid, $name, $uname = '') {
    $g = (array)dmVal('gift', []);
    if (empty($g['on']) || !function_exists('maGrantNumber')) return dmT('gift_off');

    $cost = max(1, (float)($g['cost'] ?? 0));
    $item = dmGiftItem();
    if (!$item) return dmT('gift_off');

    $limit = (int)($g['limit'] ?? 0);
    if ($limit > 0 && (int)(dmUser($uid)['gifts'] ?? 0) >= $limit) return dmT('gift_had');

    $pts = (float)(dmUser($uid)['points'] ?? 0);
    if ($pts < $cost)
        return dmT('gift_low', ['cost' => number_format($cost), 'points' => number_format($pts)]);

    $ok = dmUserSet($uid, function (&$x) use ($cost, $limit) {
        if ((float)$x['points'] < $cost) return false;
        if ($limit > 0 && (int)($x['gifts'] ?? 0) >= $limit) return false;
        $x['points'] = (float)$x['points'] - $cost;
        $x['gifts']  = (int)($x['gifts'] ?? 0) + 1;
        return true;
    });
    if (!$ok) return dmT('gift_low',
        ['cost' => number_format($cost), 'points' => number_format((float)(dmUser($uid)['points'] ?? 0))]);

    [$gok, $res] = maGrantNumber($uid, $uname, (string)($item['id'] ?? ''), $cost);
    if (!$gok) return dmT('gift_fail', ['why' => h((string)$res)]);

    return dmT('gift_ok', [
        'item'   => h(maItemTitle($item)),
        'cost'   => number_format($cost),
        'points' => number_format((float)(dmUser($uid)['points'] ?? 0)),
        'code'   => $res,
    ]);
}

function dmGiftRestore($uid, $cost) {
    dmUserSet($uid, function (&$x) use ($cost) {
        $x['points'] = (float)($x['points'] ?? 0) + (float)$cost;
        $x['gifts']  = max(0, (int)($x['gifts'] ?? 0) - 1);
        return true;
    });
}

function dmGiftItem() {
    $id = (string)dmVal('gift.item', '');
    return ($id !== '' && function_exists('maFindItem')) ? maFindItem($id) : null;
}

function dmHandleText($text, $uid, $chatId, $name, $username = '', $replyTo = null, $isPrivate = false) {
    if (!dmOn()) return false;
    $raw = trim((string)$text);
    if ($raw === '' || mb_strlen($raw) > 30) return false;

    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];

    if (dmIsWord($raw)) {
        if ($isPrivate && !empty(dmVal('group_only'))) {
            sendMsg(BOT_TOKEN, $chatId, dmT('private'), null, $extra);
            return true;
        }
        [$msg, $won] = dmHit($uid, $name, $username);
        if ($msg !== '' && ($won || !function_exists('maRateOk') || maRateOk('dmw', $uid, 1, 20))) sendMsg(BOT_TOKEN, $chatId, $msg, null, $extra);
        return true;
    }

    $t = mb_strtolower($raw);
    $word = mb_strtolower((string)dmVal('word', 'الماس'));
    if (in_array($t, [$word . ' من', 'امتیاز من', 'امتیازم', 'الماس من', 'حساب من', 'حساب الماس'], true)) {
        sendMsg(BOT_TOKEN, $chatId, dmMeText($uid, $name), null, $extra);
        return true;
    }

    foreach (dmJailWords() as $jw) {
        if ($jw !== '' && $t === mb_strtolower($jw)) {
            if ($isPrivate && !empty(dmVal('group_only'))) {
                sendMsg(BOT_TOKEN, $chatId, dmT('private'), null, $extra);
                return true;
            }
            dmJailStart($uid, $name, $username, $chatId, $replyTo);
            return true;
        }
    }
    if (in_array($t, ['برترین‌ها', 'برترین ها', 'رتبه‌بندی', 'رتبه بندی', 'top', 'لیدربرد'], true)) {
        sendMsg(BOT_TOKEN, $chatId, dmTopText(), null, $extra);
        return true;
    }
    if (in_array($t, ['تبدیل الماس', $word . ' به تومان', 'تبدیل امتیاز'], true)) {
        sendMsg(BOT_TOKEN, $chatId, dmSwap($uid), null, $extra);
        return true;
    }

    $gw = mb_strtolower(trim((string)dmVal('gift.word', 'هدیه')));
    if ($gw !== '' && $t === $gw) {
        sendMsg(BOT_TOKEN, $chatId, dmGift($uid, $name, $username), null, $extra);
        return true;
    }
    return false;
}


function dmJailWords() {
    $out = [];
    foreach (explode(',', (string)dmVal('jail.words', 'میو,هاپ')) as $w) {
        $w = trim($w);
        if ($w !== '') $out[] = $w;
    }
    return $out;
}

function dmJailButtons($need, $data) {
    $color = (string)dmVal('jail.color', 'danger');
    $style = isStyle($color) ? $color : null;
    $icon  = (string)dmVal('jail.icon', '');
    $btns = [];
    for ($i = 0; $i < $need; $i++) {
        $b = btnCb(dmT('jail_btn'), $data, null, $style);
        if ($icon !== '') $b['icon_custom_emoji_id'] = $icon;
        $btns[] = $b;
    }
    return array_chunk($btns, 2);
}

function dmJailStart($uid, $name, $username, $chatId, $replyTo = null) {
    $need = max(1, (int)dmVal('jail.need', 3));
    $id   = 'j' . bin2hex(random_bytes(4));
    $rows = dmJailButtons($need, 'dmjail_' . $id);

    $go = mutate('dm_jails', function (&$a) use ($id, $uid, $name, $username, $chatId) {
        $now = time();
        $live = 0;
        foreach ($a as $k => $v) {
            $age = $now - (int)($v['created'] ?? 0);
            if ($age > 3600) { unset($a[$k]); continue; }
            if ($age > 600 || (string)($v['chat'] ?? '') !== (string)$chatId) continue;
            if ((int)($v['target'] ?? 0) === (int)$uid) return false;
            $live++;
        }
        if ($live >= 5) return false;
        $a[$id] = ['target' => (int)$uid, 'name' => (string)$name, 'username' => (string)$username,
                   'chat' => $chatId, 'msg' => 0, 'confirmed' => [], 'created' => $now];
        return true;
    });
    if (!$go) return;

    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];
    $res = sendMsg(BOT_TOKEN, $chatId, dmT('jail_hit', ['name' => h($name), 'need' => $need]),
        inlineKb($rows), $extra);
    $mid = (int)($res['result']['message_id'] ?? 0);

    mutate('dm_jails', function (&$a) use ($id, $mid) {
        if ($mid > 0 && isset($a[$id])) $a[$id]['msg'] = $mid;
        elseif (isset($a[$id])) unset($a[$id]);
    });
}

function dmCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if (!str_starts_with((string)$data, 'dmjail_')) return false;
    $id = substr($data, 7);

    $jails = load('dm_jails');
    $j = $jails[$id] ?? null;
    if (!$j) { answerCb(BOT_TOKEN, $cbId, '⌛️ این دیگه معتبر نیست.', true); return true; }

    if ((int)$j['target'] === (int)$uid) {
        answerCb(BOT_TOKEN, $cbId, strip_tags(dmT('jail_self')), true);
        return true;
    }
    if (isset($j['confirmed'][(string)$uid])) {
        answerCb(BOT_TOKEN, $cbId, strip_tags(dmT('jail_twice')), true);
        return true;
    }

    $need = max(1, (int)dmVal('jail.need', 3));
    $done = false; $got = 0;
    mutate('dm_jails', function (&$a) use ($id, $uid, $need, &$done, &$got) {
        if (!isset($a[$id])) return;
        if (isset($a[$id]['confirmed'][(string)$uid])) { $got = count($a[$id]['confirmed']); return; }
        $a[$id]['confirmed'][(string)$uid] = true;
        $got = count($a[$id]['confirmed']);
        if ($got >= $need) { $done = true; unset($a[$id]); }
    });

    answerCb(BOT_TOKEN, $cbId, '✅');

    if ($done) {
        $secs = max(60, (int)dmVal('jail.secs', 3600));
        dmUserSet($j['target'], function (&$x) use ($secs) { $x['jailed_until'] = time() + $secs; });
        editMsg(BOT_TOKEN, $j['chat'], (int)$j['msg'],
            dmT('jail_done', ['name' => h($j['name']), 'dur' => dmDur($secs)]), null);
    } else {
        $rows = dmJailButtons($need, $data);
        editMsg(BOT_TOKEN, $j['chat'], (int)$j['msg'],
            dmT('jail_prog', ['name' => h($j['name']), 'got' => $got, 'need' => $need]),
            inlineKb($rows));
    }
    return true;
}


function dmAdminHome($chatId, $msgId = null) {
    $c = dmCfg();
    $s = dmStats();

    $t  = "💎 <b>الماس</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'کلمه: <code>' . h($c['word']) . '</code>' .
          (trim((string)$c['aliases']) !== '' ? ' · <code>' . h($c['aliases']) . '</code>' : '') . "\n";
    $t .= '⏳ فاصله: <b>' . dmDur((int)$c['cooldown']) . "</b>\n";
    $t .= '🎁 جایزه پایه: <b>' . $c['base'] . '</b> · ضریب رشد: <b>' . $c['ratio'] . "</b>\n";
    $t .= '⭐️ هر <b>' . number_format(dmStep()) . "</b> امتیاز = یک سطح\n";
    $t .= '📍 جای بازی: ' . (!empty($c['group_only']) ? 'فقط گروه' : 'گروه و خصوصی') . "\n\n";
    $t .= "👥 بازیکن‌ها: <b>" . number_format($s['users']) . "</b>\n";
    $t .= "💎 مجموع الماسِ همه: <b>" . number_format($s['points']) . "</b>\n";
    $t .= "🔁 مجموع دفعات: <b>" . number_format($s['total']) . "</b>\n";
    if ((float)$c['to_wallet'] > 0)
        $t .= "💰 ارزشِ تومانیِ کلِ الماس‌ها: <b>" .
              number_format($s['points'] * (float)$c['to_wallet']) . "</b>\n";
    $t .= "\n";
    $t .= '🔁 تبدیل به کیف پول: ' . ((float)$c['to_wallet'] > 0
            ? 'هر ۱ الماس = <b>' . $c['to_wallet'] . '</b> تومان (حداقل ' . number_format((float)$c['min_swap']) . ')'
            : '❌ خاموش');

    $rows = [
        [btnCb(!empty($c['on']) ? '✅ روشن' : '❌ خاموش', 'dmx', 'info'),
         btnCb(!empty($c['group_only']) ? '📍 فقط گروه' : '📍 همه‌جا', 'dmg', 'info')],
        [btnCb('💬 کلمه', 'dmw', 'admin'), btnCb('➕ کلمه‌های دیگر', 'dma', 'admin')],
        [btnCb('⏳ فاصله', 'dmcd', 'admin'), btnCb('🎁 جایزه پایه', 'dmb', 'admin')],
        [btnCb('📈 ضریب رشد', 'dmr', 'admin'), btnCb('🔢 کف جایزه', 'dmmin', 'admin')],
        [btnCb('📊 سطح‌ها و قیمتِ الماس', 'dmlv', 'admin')],
        [btnCb('⭐️ امتیاز هر سطح', 'dmstep', 'admin')],
        [btnCb('🔁 نرخ تبدیل', 'dmsw', 'admin'), btnCb('🔢 حداقل تبدیل', 'dmms', 'admin')],
        [btnCb('✏️ متن‌ها', 'dmt_home', 'admin'), btnCb('🏆 برترین‌ها', 'dmtop', 'confirm')],
        [btnCb('🎁 دادن الماس به کاربر', 'dmgive', 'admin')],
        [btnCb('🚨 زندان', 'dmj_home', 'admin')],
        [btnCb('🧮 شمارش دوباره', 'dmsum', 'confirm')],
        [btnCb('🎁 هدیه با الماس', 'dmgift', 'confirm')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function dmAdminLevels($chatId, $msgId) {
    $step = dmStep();
    $rate = (float)dmVal('to_wallet', 0);

    $t  = "📊 <b>سطح‌ها و قیمتِ الماس</b>\n\n";
    $t .= '⭐️ هر <b>' . number_format($step) . "</b> الماس = یک سطح\n";
    $t .= '💰 قیمتِ هر الماس: ' . ($rate > 0
            ? '<b>' . rtrim(rtrim(number_format($rate, 4, '.', ','), '0'), '.') . '</b> تومان'
            : '❌ تبدیل خاموش است') . "\n\n";

    $t .= "<blockquote>";
    $t .= "سطح · الماسِ لازم · جایزه‌ی هر برداشت\n";
    $rows = [1, 2, 3, 5, 10, 20, 30, 50, 100];
    foreach ($rows as $lv) {
        $need = dmNextAt($lv);
        $rw   = dmReward($lv);
        $t .= 'سطح ' . $lv . ' · ' . number_format($need) . ' · ~' . number_format($rw) . "\n";
    }
    $t .= "</blockquote>\n";

    if ($rate > 0) {
        $t .= "\n💵 <b>ارزشِ تومانیِ هر سطح</b>\n";
        foreach ([1, 5, 10, 50] as $lv)
            $t .= '• رسیدن به سطح ' . ($lv + 1) . ': <b>' .
                  number_format(dmNextAt($lv) * $rate) . "</b> تومان\n";
    }


    $rows = [
        [btnCb('⭐️ امتیاز هر سطح', 'dmstep', 'admin')],
        [btnCb('🔁 نرخ تبدیل (قیمتِ الماس)', 'dmsw', 'admin'),
         btnCb('🔢 حداقل تبدیل', 'dmms', 'admin')],
        [btnCb('🎁 جایزه پایه', 'dmb', 'admin'), btnCb('📈 ضریب رشد', 'dmr', 'admin')],
        [btnCb(UT('back'), 'dm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function dmLabel($k) {
    $m = [
        'win'      => '💎 وقتی الماس می‌گیرد',
        'levelup'  => '🎉 وقتی سطحش بالا می‌رود',
        'wait'     => '⏳ وقتی هنوز زود است',
        'private'  => '🔒 وقتی در خصوصی می‌نویسد',
        'me'       => '👤 حساب الماس من',
        'me_none'  => '👤 وقتی هنوز الماسی ندارد',
        'top_head' => '🏆 سربرگ برترین‌ها',
        'top_row'  => '🏆 هر ردیف برترین‌ها',
        'top_none' => '🏆 وقتی کسی الماس ندارد',
        'swap_ok'  => '✅ تبدیل الماس انجام شد',
        'swap_low' => '❌ الماس برای تبدیل کم است',
        'swap_off' => '🔒 تبدیل بسته است',
        'gift_ok'  => '🎁 هدیه گرفته شد',
        'gift_low' => '🎁 الماس برای هدیه کم است',
        'gift_off' => '🎁 هدیه بسته است',
        'gift_had' => '🎁 قبلا هدیه گرفته',
        'gift_fail'=> '🎁 شماره‌ی هدیه آماده نشد',
        'jail_hit'   => '🚨 وقتی کسی به دام می‌افتد',
        'jail_prog'  => '🚨 وقتی یکی تاییدش کرد (هنوز کامل نشده)',
        'jail_btn'   => '🔒 متنِ دکمه‌ی تایید',
        'jail_done'  => '🚔 وقتی زندانی می‌شود',
        'jail_self'  => '🚨 وقتی خودشو تایید می‌کند',
        'jail_twice' => '🚨 وقتی دوباره تایید می‌کند',
        'jail_wait'  => '⛔️ وقتی زندانی است و الماس می‌زند',
    ];
    return $m[$k] ?? $k;
}

function dmTextsCfg() {
    return [
        'title' => 'متن‌های الماس',
        'keys'  => array_keys((array)dmVal('texts', [])),
        'popup' => ['jail_self', 'jail_twice'],
        'btns'  => ['jail_btn'],
        'label' => 'dmLabel',
        'value' => function ($k) { return (string)dmVal('texts.' . $k, ''); },
        'cb'    => 'dmt_',
        'edit'  => 'dmts_',
        'back'  => 'dm_home',
    ];
}

function dmAdminJail($chatId, $msgId) {
    $j = (array)dmVal('jail', []);
    $color = (string)($j['color'] ?? 'danger');
    $icon  = trim((string)($j['icon'] ?? ''));
    $t  = "🚨 <b>زندان</b>\n\n";
    $t .= 'کلمه‌ها: <code>' . h((string)($j['words'] ?? '')) . "</code>\n";
    $t .= 'تعدادِ لازم برای تایید: <b>' . (int)($j['need'] ?? 3) . "</b> نفر\n";
    $t .= 'مدتِ زندان: <b>' . dmDur((int)($j['secs'] ?? 3600)) . "</b>\n";
    $t .= 'رنگ دکمه‌ی تایید: <b>' . h(styleMap()[$color] ?? styleMap()['danger']) . "</b>\n";
    $t .= 'ایموجیِ پریمیومِ دکمه: ' . ($icon !== '' ? '<tg-emoji emoji-id="' . h($icon) . '">🌟</tg-emoji> ثبت شده' : '❌ تنظیم نشده');

    $rows = [
        [btnCb('💬 کلمه‌ها', 'dmjw', 'admin'), btnCb('👥 تعدادِ تایید', 'dmjn', 'admin')],
        [btnCb('⏳ مدتِ زندان', 'dmjs', 'admin'), btnCb('🎨 رنگ دکمه', 'dmjc', 'admin')],
        [btnCb('🌟 ایموجیِ پریمیومِ دکمه', 'dmji', 'admin')],
        [btnCb('✏️ متنِ به‌دام‌افتادن', 'dmts_jail_hit', 'admin')],
        [btnCb('✏️ متنِ دکمه‌ی تایید', 'dmts_jail_btn', 'admin')],
        [btnCb(UT('back'), 'dm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function dmAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'dm')) return false;

    if ($data === 'dm_home') { answerCb(BOT_TOKEN, $cbId); dmAdminHome($chatId, $msgId); return true; }
    if ($data === 'dmx') {
        dmSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); dmAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'dmg') {
        dmSet(function (&$c) { $c['group_only'] = empty($c['group_only']) ? 1 : 0; });
        answerCb(BOT_TOKEN, $cbId, '✅'); dmAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'dmlv') { answerCb(BOT_TOKEN, $cbId); dmAdminLevels($chatId, $msgId); return true; }
    if ($data === 'dmtop') {
        answerCb(BOT_TOKEN, $cbId);
        sendMsg(BOT_TOKEN, $chatId, dmTopText());
        return true;
    }
    if ($data === 'dmstep') {
        setState($chatId, 'dm_step', []);
        answerCb(BOT_TOKEN, $cbId);
        sendMsg(BOT_TOKEN, $chatId,
            "⭐️ <b>امتیاز هر سطح</b>\n\nفعلی: <code>" . dmStep() . "</code>",
            inlineKb([[btnCb('🔙 بی‌خیال', 'dm_home', 'nav')]]));
        return true;
    }
    if ($data === 'dmsum') {
        $s = dmSumRebuild();
        answerCb(BOT_TOKEN, $cbId, '💎 ' . number_format($s['points']), true);
        dmAdminHome($chatId, $msgId);
        return true;
    }
    if (txRoute(dmTextsCfg(), $data, $chatId, $msgId, $cbId)) return true;
    if ($data === 'dmj_home') { answerCb(BOT_TOKEN, $cbId); dmAdminJail($chatId, $msgId); return true; }
    if ($data === 'dmjc') {
        answerCb(BOT_TOKEN, $cbId);
        $rows = [];
        foreach (styleMap() as $sk => $sl) $rows[] = [btnCb($sl, 'dmjcC_' . $sk, 'info')];
        $rows[] = [btnCb(UT('back'), 'dmj_home', 'nav')];
        editMsg(BOT_TOKEN, $chatId, $msgId, "🎨 <b>رنگ دکمه‌ی تایید</b>", inlineKb($rows));
        return true;
    }
    if (preg_match('/^dmjcC_(\w+)$/', $data, $dmc)) {
        $col = isStyle($dmc[1]) ? $dmc[1] : 'none';
        dmSet(function (&$c) use ($col) { $c['jail']['color'] = $col; });
        answerCb(BOT_TOKEN, $cbId, '✅');
        dmAdminJail($chatId, $msgId);
        return true;
    }

    $asks = [
        'dmw'   => ['dm_word',  "💬 کلمه‌ی بازی"],
        'dma'   => ['dm_alias', "➕ کلمه‌های دیگر · <code>a,b</code> · <code>-</code>"],
        'dmcd'  => ['dm_cd',    "⏳ فاصله · <code>5 دقیقه</code> · <code>300</code>"],
        'dmb'   => ['dm_base',  "🎁 جایزه‌ی پایه در سطح ۱"],
        'dmr'   => ['dm_ratio', "📈 ضریب رشد جایزه"],
        'dmmin' => ['dm_min',   "🔢 کف جایزه"],
        'dmsw'  => ['dm_swap',  "🔁 تومانِ هر ۱ الماس · <code>0</code> = خاموش"],
        'dmms'  => ['dm_mins',  "🔢 حداقل الماس برای تبدیل"],
        'dmgive'=> ['dm_give',  "🎁 <code>123456789 5000</code>"],
        'dmjw'  => ['dm_jwords', "💬 کلمه‌های زندان · <code>a,b</code>"],
        'dmjn'  => ['dm_jneed',  "👥 تعداد تاییدکننده"],
        'dmjs'  => ['dm_jsecs',  "⏳ مدتِ زندان · <code>1 ساعت</code> · <code>3600</code>"],
        'dmji'  => ['dm_jicon',  "🌟 ایموجی پریمیوم · <code>-</code>"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'dm_home', 'cancel')]]));
        return true;
    }
    if ($data === 'dmgift') { answerCb(BOT_TOKEN, $cbId); dmAdminGift($chatId, $msgId); return true; }
    if ($data === 'dmgx') {
        dmSet(function (&$c) { $c['gift']['on'] = empty($c['gift']['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); dmAdminGift($chatId, $msgId); return true;
    }
    if ($data === 'dmgi' || str_starts_with($data, 'dmgi_')) {
        answerCb(BOT_TOKEN, $cbId);
        dmAdminGiftItems($chatId, $msgId, (int)substr($data, 5));
        return true;
    }
    if (str_starts_with($data, 'dmgp_')) {
        $id = substr($data, 5);
        if (!function_exists('maFindItem') || !maFindItem($id)) { answerCb(BOT_TOKEN, $cbId, 'این شماره دیگر نیست', true); return true; }
        dmSet(function (&$c) use ($id) { $c['gift']['item'] = $id; });
        answerCb(BOT_TOKEN, $cbId, '✅ انتخاب شد');
        dmAdminGift($chatId, $msgId);
        return true;
    }
    if ($data === 'dmgc') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'dm_gcost', []);
        sendMsg(BOT_TOKEN, $chatId, "💎 هزینه (الماس)",
            inlineKb([[btnUI('cancel', 'dmgift', 'cancel')]]));
        return true;
    }
    if ($data === 'dmgw') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'dm_gword', []);
        sendMsg(BOT_TOKEN, $chatId, "💬 کلمه‌ی هدیه",
            inlineKb([[btnUI('cancel', 'dmgift', 'cancel')]]));
        return true;
    }
    if ($data === 'dmgl') {
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'dm_glimit', []);
        sendMsg(BOT_TOKEN, $chatId, "🔢 سقفِ هر نفر · <code>0</code> = بی‌نهایت",
            inlineKb([[btnUI('cancel', 'dmgift', 'cancel')]]));
        return true;
    }

    if (str_starts_with($data, 'dmts_')) {
        $k = substr($data, 5);
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), 'dm_text', ['k' => $k]);
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ <b>" . h(dmLabel($k)) . "</b>\n\nالان:\n<code>" .
            h(mb_substr((string)dmT($k), 0, 500)) . '</code>',
            inlineKb([[btnUI('cancel', 'dm_home', 'cancel')]]));
        return true;
    }
    return false;
}

function dmAdminGift($chatId, $msgId) {
    $g    = (array)dmVal('gift', []);
    $item = dmGiftItem();
    $name = $item ? maItemTitle($item) : '';

    $t  = "🎁 <b>هدیه با الماس</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($g['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= '💎 هزینه: <b>' . number_format((float)($g['cost'] ?? 0)) . "</b> الماس\n";
    $t .= '☎️ شماره: ' . ($item
            ? '<b>' . h($name) . '</b>'
            : '<b>تنظیم نشده</b> — تا انتخاب نکنید کار نمی‌کند') . "\n";
    $t .= '💬 کلمه: <code>' . h((string)($g['word'] ?? 'هدیه')) . "</code>\n";
    $lim = (int)($g['limit'] ?? 0);
    $t .= '🔢 سقف هر نفر: <b>' . ($lim > 0 ? $lim . ' بار' : 'بی‌نهایت') . "</b>\n";


    $rows = [
        [btnCb(!empty($g['on']) ? '✅ روشن' : '❌ خاموش', 'dmgx', 'info')],
        [btnCb('💎 هزینه (الماس)', 'dmgc', 'admin'), btnCb('☎️ انتخاب شماره', 'dmgi', 'admin')],
        [btnCb('💬 کلمه', 'dmgw', 'admin'), btnCb('🔢 سقف هر نفر', 'dmgl', 'admin')],
        [btnCb(UT('back'), 'dm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function dmAdminGiftItems($chatId, $msgId, $page = 0) {
    if (!function_exists('maItems')) { editMsg(BOT_TOKEN, $chatId, $msgId, 'مینی‌اپ در دسترس نیست.'); return; }
    $all = [];
    foreach (maItems() as $i)
        if (!empty($i['on']) && maItemPrice($i) > 0) $all[] = [(string)($i['id'] ?? ''), maItemTitle($i)];

    $per   = 12;
    $pages = max(1, (int)ceil(count($all) / $per));
    $page  = max(0, min($page, $pages - 1));
    $slice = array_slice($all, $page * $per, $per);

    $t = "☎️ <b>شماره‌ی هدیه</b>\n\n" .
         ($all ? 'صفحه ' . ($page + 1) . ' از ' . $pages : 'شماره‌ی فعالی تعریف نشده');
    $rows = [];
    foreach ($slice as [$id, $name])
        $rows[] = [btnCb('☎️ ' . mb_substr($name, 0, 30), 'dmgp_' . $id, 'admin')];

    $nav = [];
    if ($page > 0)          $nav[] = btnCb('⬅️ قبلی', 'dmgi_' . ($page - 1), 'nav');
    if ($page < $pages - 1) $nav[] = btnCb('بعدی ➡️', 'dmgi_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'dmgift', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

function dmStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'dm_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('💎 الماس', 'dm_home', 'admin')]]);
    $num  = (float)str_replace([',', '،'], '', norm_fa_digits($text));

    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $back) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, $back);
        return true;
    };
    $bad = function ($m) use ($chatId) { sendMsg(BOT_TOKEN, $chatId, "⚠️ " . $m); return true; };

    if ($action === 'dm_word') {
        if (mb_strlen($text) < 2 || mb_strlen($text) > 20) return $bad('کلمه باید بین ۲ تا ۲۰ نویسه باشد.');
        dmSet(function (&$c) use ($text) { $c['word'] = $text; });
        return $done('✅ حالا کلمه‌ی بازی «' . h($text) . '» است.');
    }
    if ($action === 'dm_alias') {
        $v = ($text === '-' || $text === '—') ? '' : $text;
        dmSet(function (&$c) use ($v) { $c['aliases'] = $v; });
        return $done();
    }
    if ($action === 'dm_cd') {
        $sec = dmSecs($text);
        if ($sec === null || $sec < 5 || $sec > 86400)
            return $bad("بین ۵ ثانیه تا ۲۴ ساعت.\n\n" .
                        "واحد هم می‌شود نوشت: <code>5 دقیقه</code>");
        dmSet(function (&$c) use ($sec) { $c['cooldown'] = $sec; });

        cfg(true);
        $confirmed = (int)dmVal('cooldown', -1);
        if ($confirmed !== $sec) {
            dmSet(function (&$c) use ($sec) { $c['cooldown'] = $sec; });
            cfg(true);
            $confirmed = (int)dmVal('cooldown', -1);
        }
        if ($confirmed !== $sec) {
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ <b>ذخیره نشد</b>\n\n" .
                "خواستم <b>" . dmDur($sec) . "</b> ثبت بشه، ولی رو دیسک همچنان <b>" .
                dmDur($confirmed) . "</b> مونده.\n\n" .
                "این یعنی نوشتنِ فایلِ تنظیمات رو سرور شکست می‌خوره (شاید دسترسیِ نوشتن روی " .
                "پوشه‌ی data_master مشکل داره) — لطفاً همین پیام رو با اسکرین‌شات بفرست.",
                inlineKb([[btnCb('💎 الماس', 'dm_home', 'admin')]]));
            return true;
        }
        return $done('✅ فاصله‌ی بین دو الماس: <b>' . dmDur($sec) . '</b>');
    }
    if ($action === 'dm_jwords') {
        $v = ($text === '-' || $text === '—') ? '' : $text;
        dmSet(function (&$c) use ($v) { $c['jail']['words'] = $v; });
        return $done('✅ کلمه‌های زندان: <code>' . h($v) . '</code>');
    }
    if ($action === 'dm_jneed') {
        $n = (int)$num;
        if ($n < 1 || $n > 20) return $bad('بین ۱ تا ۲۰.');
        dmSet(function (&$c) use ($n) { $c['jail']['need'] = $n; });
        return $done('✅ تعدادِ لازم برای تایید: <b>' . $n . '</b> نفر');
    }
    if ($action === 'dm_jsecs') {
        $sec = dmSecs($text);
        if ($sec === null || $sec < 60 || $sec > 604800)
            return $bad("بین ۱ دقیقه تا ۷ روز.\n\n" .
                        "واحد هم می‌شود نوشت: <code>1 ساعت</code>");
        dmSet(function (&$c) use ($sec) { $c['jail']['secs'] = $sec; });

        cfg(true);
        $confirmed = (int)dmVal('jail.secs', -1);
        if ($confirmed !== $sec) {
            dmSet(function (&$c) use ($sec) { $c['jail']['secs'] = $sec; });
            cfg(true);
            $confirmed = (int)dmVal('jail.secs', -1);
        }
        if ($confirmed !== $sec) {
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ <b>ذخیره نشد</b>\n\n" .
                "خواستم <b>" . dmDur($sec) . "</b> ثبت بشه، ولی رو دیسک همچنان <b>" .
                dmDur($confirmed) . "</b> مونده.\n\n" .
                "این یعنی نوشتنِ فایلِ تنظیمات رو سرور شکست می‌خوره — لطفاً همین پیام رو با اسکرین‌شات بفرست.",
                inlineKb([[btnCb('💎 الماس', 'dm_home', 'admin')]]));
            return true;
        }
        return $done('✅ مدتِ زندان: <b>' . dmDur($sec) . '</b>');
    }
    if ($action === 'dm_jicon') {
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $dash = ($text === '-' || $text === '—');
        $v = $ids ? (string)$ids[0] : ($dash ? '' : preg_replace('/\D/', '', norm_fa_digits($text)));
        if (!$dash && $v === '')
            return $bad("ایموجی پیدا نشد.\n" .
                        "<code>-</code>");
        dmSet(function (&$c) use ($v) { $c['jail']['icon'] = $v; });
        return $done($v === '' ? '✅ حذف شد.' : '✅ ثبت شد.');
    }
    if ($action === 'dm_step') {
        if ($num < 10 || $num > 100000000) return $bad('بین ۱۰ تا ۱۰۰٬۰۰۰٬۰۰۰.');
        dmSet(function (&$c) use ($num) { $c['level_step'] = (int)$num; });
        return $done('✅ هر <b>' . number_format((int)$num) . '</b> امتیاز = یک سطح');
    }
    if ($action === 'dm_base') {
        if ($num < 1 || $num > 1000000) return $bad('بین ۱ تا ۱۰۰۰۰۰۰.');
        dmSet(function (&$c) use ($num) { $c['base'] = $num; });
        return $done();
    }
    if ($action === 'dm_ratio') {
        if ($num < 1 || $num > 3) return $bad('ضریب باید بین ۱ تا ۳ باشد — بالاتر از این، جایزه‌ها از کنترل خارج می‌شوند.');
        dmSet(function (&$c) use ($num) { $c['ratio'] = $num; });
        return $done();
    }
    if ($action === 'dm_min') {
        if ($num < 1 || $num > 1000000) return $bad('بین ۱ تا ۱۰۰۰۰۰۰.');
        dmSet(function (&$c) use ($num) { $c['min'] = (int)$num; });
        return $done();
    }
    if ($action === 'dm_swap') {
        if ($num < 0 || $num > 100000) return $bad('بین ۰ تا ۱۰۰۰۰۰.');
        dmSet(function (&$c) use ($num) { $c['to_wallet'] = $num; });
        return $done($num > 0 ? '✅ هر ۱ الماس = ' . $num . ' تومان' : '✅ تبدیل خاموش شد.');
    }
    if ($action === 'dm_mins') {
        if ($num < 1) return $bad('عدد نامعتبر');
        dmSet(function (&$c) use ($num) { $c['min_swap'] = $num; });
        return $done();
    }
    if ($action === 'dm_gcost') {
        if ($num < 1) return $bad('عدد نامعتبر');
        dmSet(function (&$c) use ($num) { $c['gift']['cost'] = $num; });
        return $done('✅ هزینه‌ی هدیه: ' . number_format($num) . ' الماس');
    }
    if ($action === 'dm_gword') {
        if (mb_strlen($text) < 2 || mb_strlen($text) > 20) return $bad('کلمه باید بین ۲ تا ۲۰ نویسه باشد.');
        dmSet(function (&$c) use ($text) { $c['gift']['word'] = $text; });
        return $done('✅ کلمه‌ی هدیه: «' . h($text) . '»');
    }
    if ($action === 'dm_glimit') {
        if ($num < 0 || $num > 10000) return $bad('بین ۰ تا ۱۰۰۰۰.');
        dmSet(function (&$c) use ($num) { $c['gift']['limit'] = (int)$num; });
        return $done($num > 0 ? '✅ هر نفر ' . (int)$num . ' بار' : '✅ بدون سقف');
    }

    if ($action === 'dm_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '') return $bad('متن خالی نمی‌شود.');
        if (dmIsButtonTextKey($k)) {
            if (trim($text) === '') return $bad('متن خالی نمی‌شود.');
            dmSet(function (&$c) use ($k, $text) { $c['texts'][$k] = $text; });
            return $done();
        }
        $html = function_exists('msgHtml') ? msgHtml($msg) : $text;
        if (trim($html) === '') return $bad('متن خالی نمی‌شود.');
        dmSet(function (&$c) use ($k, $html) { $c['texts'][$k] = $html; });
        return $done();
    }
    if ($action === 'dm_give') {
        $parts = preg_split('/\s+/', norm_fa_digits($text));
        $target = (int)($parts[0] ?? 0);
        $amount = (float)str_replace([',', '،'], '', (string)($parts[1] ?? 0));
        if ($target <= 0 || $amount == 0.0) return $bad('<code>123456789 5000</code>');
        dmUserSet($target, function (&$x) use ($amount) {
            $x['points'] = max(0, (float)$x['points'] + $amount);
        });
        return $done('✅ ' . number_format($amount) . ' الماس برای <code>' . $target .
                     '</code> اعمال شد.\nموجودی تازه: <b>' .
                     number_format((float)dmUser($target)['points']) . '</b>');
    }

    clearState($uid);
    return true;
}

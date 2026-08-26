<?php
/**
 * 💎 الماس — امتیازگیری داخل گروه
 *
 * کاربر داخل گروه کلمه‌ی «الماس» را می‌نویسد و امتیاز می‌گیرد. هرچه سطحش
 * بالاتر برود جایزه‌اش بیشتر می‌شود، ولی بین هر دو الماس باید صبر کند.
 *
 * همه‌ی عددها (کلمه، کولداون، پایه‌ی جایزه، ضریب رشد) و همه‌ی متن‌ها از
 * پنل قابل ویرایش‌اند — چون این بخش قرار است با حال‌وهوای هر گروه تنظیم شود.
 *
 * عمدا از کیف پول فروشگاه جداست: الماس یک امتیاز بازی است، نه پول.
 * اگر روزی خواستید تبدیلش کنید، «نرخ تبدیل» را روشن کنید.
 */

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function dmDefaults() {
    return [
        'on'         => false,       // تا وقتی ادمین روشنش نکند، خاموش
        'word'       => 'الماس',
        'aliases'    => '',          // کلمه‌های دیگر، با ویرگول
        'cooldown'   => 300,         // ثانیه بین دو الماس
        'base'       => 56.74,       // جایزه‌ی پایه در سطح ۱
        'ratio'      => 1.2336,      // رشد جایزه با هر سطح
        'min'        => 20,          // کف جایزه
        'cap'        => 1000000000,  // سقف جایزه — جلوی عددهای نجومی
        'group_only' => 1,           // فقط در گروه
        'top_n'      => 10,

        // 🔁 تبدیل الماس به موجودی کیف پول (۰ = خاموش)
        'to_wallet'  => 0,           // هر ۱ الماس چند تومان
        'min_swap'   => 10000,       // حداقل الماس برای تبدیل

        // ✏️ متن‌ها — همه قابل ویرایش
        'texts' => [
            'win'      => "💎 <b>الماس!</b> {name}\n\n✨ +{reward} الماس\n💰 موجودی: {points}\n⭐️ سطح: {level} · پیشرفت: {progress}",
            'levelup'  => "\n\n🎉 <b>لِول آپ!</b> به سطح {level} رسیدی!",
            'wait'     => "⏳ {name}، هنوز زود است!\n⌛️ {m} دقیقه و {s} ثانیه دیگر می‌توانی الماس بزنی 💎",
            'private'  => "💎 الماس فقط داخل گروه کار می‌کند.\nمن را به گروهت اضافه کن.",
            'me'       => "💎 <b>{name}</b>\n\n✨ الماس: <b>{points}</b>\n⭐️ سطح: <b>{level}</b>\n🔁 تعداد الماس: <b>{total}</b>\n📈 تا سطح بعد: <b>{left}</b>",
            'me_none'  => "هنوز الماسی نزده‌ای. داخل گروه بنویس «{word}».",
            'top_head' => "🏆 <b>برترین‌های الماس</b>\n",
            'top_row'  => "{rank}. {name} — <b>{points}</b> 💎 (سطح {level})",
            'top_none' => "هنوز کسی الماس نزده است.",
            'swap_ok'  => "✅ <b>{n}</b> الماس به <b>{toman}</b> تومان تبدیل شد.\n💰 موجودی کیف پول: <b>{bal}</b> تومان",
            'swap_low' => "❌ حداقل <b>{min}</b> الماس لازم است. الماس تو: <b>{points}</b>",
            'swap_off' => "🔒 تبدیل الماس به کیف پول فعلا بسته است.",
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
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

function dmOn() { return !empty(dmVal('on')); }

// ============================================================
// 📈 سطح و جایزه
// ============================================================

/**
 * آستانه‌ی هر سطح. اول تند بالا می‌رود بعد کند —
 * تا سطح‌های اول زود بیایند و سطح‌های بالا ارزش داشته باشند.
 */
function dmThresholds() {
    static $t = null;
    if ($t !== null) return $t;
    $t = [];
    $n = 10;
    for ($i = 0; $i < 1000; $i++) {
        $t[] = $n;
        if ($i < 9)        $n += 20;
        elseif ($i < 49)   $n += 50;
        elseif ($i < 199)  $n += 100;
        else               $n += 200;
    }
    return $t;
}

function dmLevel($total) {
    $total = (int)$total;
    $level = 1;
    foreach (dmThresholds() as $i => $th) {
        if ($total >= $th) $level = $i + 2;
        else break;
    }
    return min($level, 1000);
}

/** چند الماس تا سطح بعد */
function dmNextAt($level) {
    $t = dmThresholds();
    $level = (int)$level;
    return ($level >= 1000) ? 0 : (int)($t[$level - 1] ?? 0);
}

/**
 * جایزه‌ی یک الماس در این سطح.
 * از سطح ۴۳ به بالا بیشترِ دفعات جایزه‌ی نصفه می‌آید تا رشد رام بماند.
 */
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
        return (mt_rand(1, 100) <= 60) ? mt_rand($min, $mid) : mt_rand($mid, $max);
    }
    return mt_rand($min, $max);
}

// ============================================================
// 🗃 داده
// ============================================================

function dmUser($uid) {
    $a = load('diamond_users');
    return $a[(string)$uid] ?? null;
}

function dmUserSet($uid, callable $fn) {
    return mutate('diamond_users', function (&$a) use ($uid, $fn) {
        $k = (string)$uid;
        if (!isset($a[$k])) {
            $a[$k] = ['id' => (int)$uid, 'name' => '', 'username' => '',
                      'points' => 0, 'total' => 0, 'level' => 1, 'last' => 0,
                      'joined_at' => nowStr()];
        }
        return $fn($a[$k]);
    });
}

/** برترین‌ها */
function dmTop($n = 10) {
    $a = array_values(load('diamond_users'));
    usort($a, fn($x, $y) => (float)($y['points'] ?? 0) <=> (float)($x['points'] ?? 0));
    return array_slice($a, 0, max(1, (int)$n));
}

function dmStats() {
    $a = load('diamond_users');
    $sum = 0; $hops = 0;
    foreach ($a as $u) { $sum += (float)($u['points'] ?? 0); $hops += (int)($u['total'] ?? 0); }
    return ['users' => count($a), 'points' => $sum, 'total' => $hops];
}

// ============================================================
// 🎮 خودِ بازی
// ============================================================

/** آیا این متن یعنی «الماس»؟ */
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

/**
 * یک الماس ثبت می‌کند.
 * برگشت: [متن پاسخ, آیا امتیاز گرفت]
 */
function dmHit($uid, $name, $username = '') {
    $cd = max(5, (int)dmVal('cooldown', 300));
    $now = time();

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
        // 🔒 قفل دوم داخل خودِ نوشتن — دو پیام همزمان نباید دوبار امتیاز بدهد
        $cd = max(5, (int)dmVal('cooldown', 300));
        if ($now - (int)($x['last'] ?? 0) < $cd) return null;

        $level  = dmLevel((int)$x['total']);
        $reward = dmReward($level);

        $x['name']     = $name;
        $x['username'] = $username;
        $x['total']    = (int)$x['total'] + 1;
        $x['points']   = (float)$x['points'] + $reward;
        $x['last']     = $now;
        $newLevel      = dmLevel((int)$x['total']);
        $x['level']    = $newLevel;

        return ['reward' => $reward, 'points' => $x['points'],
                'total' => $x['total'], 'level' => $newLevel, 'up' => $newLevel > $level];
    });

    if (!is_array($res)) {
        return [dmT('wait', ['name' => $name, 'm' => 0, 's' => $cd, 'left' => $cd]), false];
    }

    $next = dmNextAt($res['level']);
    $progress = $next > 0 ? number_format($res['total']) . '/' . number_format($next) : 'MAX';

    $msg = dmT('win', [
        'name'     => $name,
        'reward'   => number_format($res['reward']),
        'points'   => number_format($res['points']),
        'level'    => $res['level'],
        'total'    => number_format($res['total']),
        'progress' => $progress,
    ]);
    if ($res['up']) $msg .= dmT('levelup', ['level' => $res['level']]);

    return [$msg, true];
}

/** متن وضعیت یک کاربر */
function dmMeText($uid, $name) {
    $u = dmUser($uid);
    if (!$u) return dmT('me_none', ['word' => dmVal('word', 'الماس')]);
    $level = dmLevel((int)$u['total']);
    $next  = dmNextAt($level);
    return dmT('me', [
        'name'   => $u['name'] ?: $name,
        'points' => number_format((float)$u['points']),
        'level'  => $level,
        'total'  => number_format((int)$u['total']),
        'left'   => $next > 0 ? number_format(max(0, $next - (int)$u['total'])) : '—',
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
            'name'   => h($u['name'] ?: ('کاربر ' . $u['id'])),
            'points' => number_format((float)$u['points']),
            'level'  => dmLevel((int)$u['total']),
        ]) . "\n";
    }
    return $t;
}

/** تبدیل الماس به موجودی کیف پول */
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
    addBalance($uid, $toman);
    return dmT('swap_ok', [
        'n'     => number_format($taken),
        'toman' => number_format($toman),
        'bal'   => number_format((float)(getUser($uid)['balance'] ?? 0)),
    ]);
}

/**
 * پیام گروه/خصوصی را می‌بیند.
 * برگشت true یعنی رسیدگی شد.
 */
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
        [$msg, ] = dmHit($uid, $name, $username);
        sendMsg(BOT_TOKEN, $chatId, $msg, null, $extra);
        return true;
    }

    $t = mb_strtolower($raw);
    $word = mb_strtolower((string)dmVal('word', 'الماس'));
    if (in_array($t, [$word . ' من', 'امتیاز من', 'امتیازم', 'الماس من', 'حساب من'], true)) {
        sendMsg(BOT_TOKEN, $chatId, dmMeText($uid, $name), null, $extra);
        return true;
    }
    if (in_array($t, ['برترین‌ها', 'برترین ها', 'رتبه‌بندی', 'رتبه بندی', 'top', 'لیدربرد'], true)) {
        sendMsg(BOT_TOKEN, $chatId, dmTopText(), null, $extra);
        return true;
    }
    if (in_array($t, ['تبدیل الماس', $word . ' به تومان', 'تبدیل امتیاز'], true)) {
        sendMsg(BOT_TOKEN, $chatId, dmSwap($uid), null, $extra);
        return true;
    }
    return false;
}

// ============================================================
// 👑 پنل مدیریت — الماس
// ============================================================

function dmAdminHome($chatId, $msgId = null) {
    $c = dmCfg();
    $s = dmStats();

    $t  = "💎 <b>الماس</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($c['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'کلمه: <code>' . h($c['word']) . '</code>' .
          (trim((string)$c['aliases']) !== '' ? ' · <code>' . h($c['aliases']) . '</code>' : '') . "\n";
    $t .= '⏳ فاصله: <b>' . number_format((int)$c['cooldown']) . "</b> ثانیه\n";
    $t .= '🎁 جایزه پایه: <b>' . $c['base'] . '</b> · ضریب رشد: <b>' . $c['ratio'] . "</b>\n";
    $t .= '📍 جای بازی: ' . (!empty($c['group_only']) ? 'فقط گروه' : 'گروه و خصوصی') . "\n\n";
    $t .= "👥 بازیکن‌ها: <b>" . number_format($s['users']) . "</b>\n";
    $t .= "💎 مجموع الماس: <b>" . number_format($s['points']) . "</b>\n";
    $t .= "🔁 مجموع دفعات: <b>" . number_format($s['total']) . "</b>\n\n";
    $t .= '🔁 تبدیل به کیف پول: ' . ((float)$c['to_wallet'] > 0
            ? 'هر ۱ الماس = <b>' . $c['to_wallet'] . '</b> تومان (حداقل ' . number_format((float)$c['min_swap']) . ')'
            : '❌ خاموش');

    $rows = [
        [btnCb(!empty($c['on']) ? '✅ روشن' : '❌ خاموش', 'dmx', 'info'),
         btnCb(!empty($c['group_only']) ? '📍 فقط گروه' : '📍 همه‌جا', 'dmg', 'info')],
        [btnCb('💬 کلمه', 'dmw', 'admin'), btnCb('➕ کلمه‌های دیگر', 'dma', 'admin')],
        [btnCb('⏳ فاصله', 'dmcd', 'admin'), btnCb('🎁 جایزه پایه', 'dmb', 'admin')],
        [btnCb('📈 ضریب رشد', 'dmr', 'admin'), btnCb('🔢 کف جایزه', 'dmmin', 'admin')],
        [btnCb('🔁 نرخ تبدیل', 'dmsw', 'admin'), btnCb('🔢 حداقل تبدیل', 'dmms', 'admin')],
        [btnCb('✏️ متن‌ها', 'dmt_home', 'admin'), btnCb('🏆 برترین‌ها', 'dmtop', 'confirm')],
        [btnCb('🎁 دادن الماس به کاربر', 'dmgive', 'admin')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function dmAdminTexts($chatId, $msgId) {
    $t = "✏️ <b>متن‌های الماس</b>\n\n";
    $t .= "جای‌گذاری‌ها: <code>{name}</code> <code>{reward}</code> <code>{points}</code> " .
          "<code>{level}</code> <code>{total}</code> <code>{progress}</code> <code>{m}</code> <code>{s}</code>\n\n";
    $rows = [];
    foreach ((array)dmVal('texts', []) as $k => $v) {
        $t .= '• <b>' . h($k) . '</b>: <code>' . h(mb_substr(str_replace("\n", ' ', (string)$v), 0, 40)) . "</code>\n";
        $rows[] = [btnCb($k, 'dmts_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'dm_home', 'nav')];
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
    if ($data === 'dmtop') {
        answerCb(BOT_TOKEN, $cbId);
        sendMsg(BOT_TOKEN, $chatId, dmTopText());
        return true;
    }
    if ($data === 'dmt_home') { answerCb(BOT_TOKEN, $cbId); dmAdminTexts($chatId, $msgId); return true; }

    $asks = [
        'dmw'   => ['dm_word',  "💬 کلمه‌ی بازی را بفرستید (مثلا الماس):"],
        'dma'   => ['dm_alias', "➕ کلمه‌های دیگر را با ویرگول بفرستید (خط تیره = هیچ‌کدام):"],
        'dmcd'  => ['dm_cd',    "⏳ چند ثانیه بین دو الماس فاصله باشد؟"],
        'dmb'   => ['dm_base',  "🎁 جایزه‌ی پایه در سطح ۱ (مثلا ۵۶٫۷۴):"],
        'dmr'   => ['dm_ratio', "📈 ضریب رشد جایزه با هر سطح (مثلا ۱٫۲۳۳۶):"],
        'dmmin' => ['dm_min',   "🔢 کف جایزه (کمترین عددی که ممکن است بگیرد):"],
        'dmsw'  => ['dm_swap',  "🔁 هر ۱ الماس چند تومان؟ (۰ = تبدیل خاموش)"],
        'dmms'  => ['dm_mins',  "🔢 حداقل الماس برای تبدیل:"],
        'dmgive'=> ['dm_give',  "🎁 آیدی عددی کاربر و مقدار را بفرستید.\n\nمثال: <code>123456789 5000</code>"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'dm_home', 'cancel')]]));
        return true;
    }
    if (str_starts_with($data, 'dmts_')) {
        $k = substr($data, 5);
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, 'dm_text', ['k' => $k]);
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ متن تازه‌ی <b>" . h($k) . "</b> را بفرستید.\n\nالان:\n<code>" .
            h(mb_substr((string)dmVal('texts.' . $k, ''), 0, 500)) . '</code>',
            inlineKb([[btnUI('cancel', 'dm_home', 'cancel')]]));
        return true;
    }
    return false;
}

function dmStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'dm_')) return false;
    if ($uid !== ADMIN_ID) return false;

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
        if ($num < 5 || $num > 86400) return $bad('بین ۵ تا ۸۶۴۰۰ ثانیه.');
        dmSet(function (&$c) use ($num) { $c['cooldown'] = (int)$num; });
        return $done();
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
        if ($num < 1) return $bad('عدد معتبر بفرستید.');
        dmSet(function (&$c) use ($num) { $c['min_swap'] = $num; });
        return $done();
    }
    if ($action === 'dm_text') {
        $k = (string)($sd['k'] ?? '');
        if ($k === '' || $text === '') return $bad('متن خالی نمی‌شود.');
        dmSet(function (&$c) use ($k, $text) { $c['texts'][$k] = $text; });
        return $done();
    }
    if ($action === 'dm_give') {
        $parts = preg_split('/\s+/', norm_fa_digits($text));
        $target = (int)($parts[0] ?? 0);
        $amount = (float)str_replace([',', '،'], '', (string)($parts[1] ?? 0));
        if ($target <= 0 || $amount == 0.0) return $bad('آیدی عددی و مقدار را با فاصله بفرستید. مثال: 123456789 5000');
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

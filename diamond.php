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

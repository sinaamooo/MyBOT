<?php
/**
 * 🎮 بازی‌های الماس
 *
 * دو بازی، هر دو با شرط‌بندی الماس و هر دو داخل گروه:
 *
 *   چالش ۱۰۰  → دوز دو نفره. نفر اول آبی، نفر دوم سبز. هر خانه‌ای که
 *               زده شود، رنگ همان بازیکن را می‌گیرد. برنده جایزه را
 *               می‌برد.
 *   بازی ۱۰۰  → قرعه. هرکس دکمه را بزند وارد می‌شود، و یک دقیقه بعد
 *               یکی کاملا شانسی برنده می‌شود.
 *
 * هر دو بازی را سازنده‌اش می‌تواند لغو کند، و همه‌ی متن‌ها از پنل
 * قابل ویرایش‌اند — با ایموجی پرمیوم و quote.
 *
 * رنگ دکمه‌ها همان سه رنگِ خود تلگرام است:
 *   primary = آبی (بازیکن اول) · success = سبز (بازیکن دوم) · danger = قرمز
 */

if (!defined('GM_LIB')) define('GM_LIB', 1);

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function gmDefaults() {
    return [
        'on'        => false,
        'word_duel' => 'چالش',
        'word_rand' => 'بازی',
        'word_bal'  => 'موجودی',
        'word_send' => 'انتقال',

        'min'       => 10,          // کمترین شرط
        'max'       => 1000000000,  // بیشترین شرط
        'tax'       => 10,          // درصدی که از جایزه کم می‌شود
        'wait'      => 8,           // ثانیه‌ی انتظار قرعه — کوتاه، تا نتیجه درجا بیاید
        'join_max'  => 50,          // بیشترین شرکت‌کننده در قرعه
        'send_tax'  => 10,          // درصد مالیات انتقال الماس

        // ✨ ایموجی پرمیومِ دکمه‌ها — خودکار از متنی که می‌فرستید برداشته
        //    می‌شود، چون برچسب دکمه جای HTML نیست.
        'icons'     => [],

        'texts' => [
            // ── چالش ──
            'duel_open'  => "{emoji} <b>چالش {stake} الماسی</b>\n\n" .
                            "<blockquote>👤 سازنده: {host}\n" .
                            "🏆 جایزه‌ی برنده: <b>{prize}</b> الماس\n" .
                            "🧾 مالیات: <b>{tax}</b> الماس</blockquote>\n\n" .
                            "برای شروع بازی، نفر دوم روی پیوستن بزند.",
            'duel_turn'  => "{emoji} <b>چالش {stake} الماسی</b>\n\n" .
                            "<blockquote>🔵 {p1}\n🟢 {p2}\n" .
                            "🏆 جایزه: <b>{prize}</b> الماس</blockquote>\n\n" .
                            "نوبت: {turn}",
            'duel_win'   => "🎉 <b>نتیجه بازی مشخص شد</b>\n\n" .
                            "<blockquote>🏆 کاربر برنده: <code>{winner}</code>\n" .
                            "❌ کاربر بازنده: <code>{loser}</code></blockquote>",
            'duel_draw'  => "🤝 <b>مساوی شد</b>\n\nشرطِ هر دو نفر برگشت.",
            'duel_join'  => "✅ پیوستن",
            'duel_cancel'=> "❌ لغو",

            // ── قرعه ──
            'rand_open'  => "{emoji} <b>بازی {stake} الماسی</b>\n\n" .
                            "<blockquote>👤 سازنده: {host}\n" .
                            "👥 شرکت‌کننده: <b>{count}</b>\n" .
                            "🏆 جایزه‌ی برنده: <b>{prize}</b> الماس\n" .
                            "🧾 مالیات: <b>{tax}</b> الماس</blockquote>\n\n" .
                            "⏳ تا قرعه: <b>{left}</b> ثانیه",
            'rand_win'   => "🎉 <b>نتیجه بازی مشخص شد</b>\n\n" .
                            "<blockquote>🏆 کاربر برنده: <code>{winner}</code>\n" .
                            "❌ کاربر بازنده: <code>{loser}</code></blockquote>",
            'rand_none'  => "😔 <b>بازی باطل شد</b>\n\nکسی وارد نشد؛ شرط برگشت.",
            'rand_join'  => "🎲 شرکت در بازی",

            // ── دکمه‌های نتیجه ──
            'lbl_prize'  => "🏆 جایزه برنده",
            'lbl_wbal'   => "💎 موجودی برنده",
            'lbl_lbal'   => "❌ موجودی بازنده",

            // ── موجودی ──
            'bal_head'   => "{emoji} <b>موجودی شما</b>",
            'bal_btn'    => "💎 {points} الماس",

            // ── انتقال ──
            'send_ok'    => "✅ <b>انتقال انجام شد</b>\n\n" .
                            "<blockquote>📤 فرستنده: {from}\n📥 گیرنده: {to}\n" .
                            "💎 مبلغ انتقال: <b>{amount}</b>\n" .
                            "🧾 مالیات: <b>{tax}</b>\n" .
                            "➖ کسر کل: <b>{total}</b></blockquote>",
            'send_bal'   => "💎 موجودی فرستنده",
            'send_bal2'  => "💎 موجودی گیرنده",
            'send_how'   => "برای انتقال، روی پیام طرف ریپلای کن و بنویس «{word} ۱۰۰».",
            'send_self'  => "❌ به خودت که نمی‌شود.",

            // ── خطاها ──
            'off'        => "🎮 بازی فعلا خاموش است.",
            'low'        => "❌ الماس کافی نداری.\n💎 موجودی تو: <b>{points}</b> · لازم: <b>{need}</b>",
            'bad_stake'  => "❌ شرط باید بین <b>{min}</b> و <b>{max}</b> الماس باشد.",
            'busy'       => "⏳ یک بازی باز داری. اول همان را تمام کن یا لغو کن.",
            'not_yours'  => "این بازی مال تو نیست.",
            'not_turn'   => "نوبت تو نیست.",
            'taken'      => "این خانه پر است.",
            'gone'       => "این بازی تمام شده.",
            'cancelled'  => "❌ <b>بازی لغو شد</b>\n\nشرط برگشت.",
            'group_only' => "🎮 بازی فقط داخل گروه کار می‌کند.",
            'already'    => "تو که خودت داخل این بازی هستی — منتظر حریف بمان.",
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

/**
 * متن‌هایی که روی دکمه می‌نشینند، نه داخل پیام.
 *
 * تلگرام داخل برچسبِ دکمه HTML نمی‌پذیرد؛ اگر <tg-emoji…> بفرستی،
 * همان رشته‌ی خام روی دکمه چاپ می‌شود. پس برای این‌ها متن ساده نگه
 * می‌داریم و ایموجی پرمیوم را جدا، در icons، به‌شکل شناسه.
 */
function gmBtnKeys() {
    return ['duel_join', 'duel_cancel', 'rand_join',
            'lbl_prize', 'lbl_wbal', 'lbl_lbal',
            'bal_btn', 'send_bal', 'send_bal2'];
}

function gmIsBtn($k) { return in_array($k, gmBtnKeys(), true); }

/** یک دکمه‌ی شیشه‌ای از روی متنِ ذخیره‌شده + ایموجی پرمیومش */
function gmBtn($key, $vars, $data, $style = null) {
    $b = ['text' => gmT($key, $vars), 'callback_data' => $data];
    if ($style) $b['style'] = $style;
    $ic = trim((string)gmVal('icons.' . $key, ''));
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
}

/** عددها همیشه فارسی و سه‌رقم‌سه‌رقم */
function gmNum($n) {
    $s = number_format((float)$n, 0, '.', '٬');
    return strtr($s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴',
                      '5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}

// ============================================================
// 💎 کیف الماس — همان انبارِ diamond.php
// ============================================================

function gmPoints($uid) {
    $u = function_exists('dmUser') ? dmUser($uid) : null;
    return (float)($u['points'] ?? 0);
}

/**
 * الماس کم یا زیاد می‌کند. برای کم کردن، همان لحظه‌ی نوشتن هم چک
 * می‌کند — وگرنه دو کلیکِ هم‌زمان می‌توانست موجودی را منفی کند.
 */
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

// ============================================================
// 🗄 انبار بازی‌ها
// ============================================================

function gmAll() { return load('games'); }
function gmGet($id) { $a = gmAll(); return $a[(string)$id] ?? null; }

function gmSetGame($id, callable $fn) {
    return mutate('games', function (&$a) use ($id, $fn) {
        $k = (string)$id;
        if (!isset($a[$k])) return false;
        return $fn($a[$k]);
    });
}

function gmPut($g) {
    mutate('games', function (&$a) use ($g) {
        $a[$g['id']] = $g;
        // خانه‌تکانی — بازی‌های تمام‌شده‌ی کهنه جا اشغال نکنند
        if (count($a) > 300) {
            $now = time();
            foreach ($a as $k => $v)
                if (($v['status'] ?? '') !== 'open' && ($v['status'] ?? '') !== 'playing'
                    && ($now - (int)($v['created'] ?? 0)) > 86400) unset($a[$k]);
        }
    });
}

/** بازی بازِ همین کاربر در همین گروه */
function gmOpenOf($uid, $chat) {
    foreach (gmAll() as $g) {
        if ((int)$g['host'] !== (int)$uid) continue;
        if ((string)$g['chat'] !== (string)$chat) continue;
        if (in_array($g['status'], ['open', 'playing'], true)) return $g;
    }
    return null;
}

// ============================================================
// 🎯 ساختن بازی
// ============================================================

/** شرط را از متن درمی‌آورد: «چالش ۱۰۰» یا «۱۰۰ چالش» */
function gmParse($raw) {
    $t = trim(norm_fa_digits((string)$raw));
    $words = [
        'duel' => gmWords(gmVal('word_duel', 'چالش')),
        'rand' => gmWords(gmVal('word_rand', 'بازی')),
    ];
    foreach ($words as $kind => $list) {
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
        'players' => [(string)$uid => ['id' => (int)$uid, 'name' => $name, 'uname' => $uname]],
        'board'   => array_fill(0, 9, 0),
        'turn'    => (int)$uid,
        'status'  => 'open',
        'created' => time(),
        'ends'    => $kind === 'rand' ? time() + max(3, (int)gmVal('wait', 8)) : 0,
    ];
    gmPut($g);
    return $g;
}

/**
 * جایزه و مالیات یک بازی.
 *
 * چالش همیشه دو نفره است، پس حتی وقتی هنوز نفر دوم نیامده هم باید
 * جایزه‌ی واقعی را اعلام کند — وگرنه روی پیامِ باز «۹۰» می‌نوشت و
 * بعد از پیوستنِ حریف می‌شد «۱۸۰».
 */
function gmPrize($g) {
    $n = ($g['kind'] === 'duel') ? 2 : max(1, count($g['players']));
    $pot   = (float)$g['stake'] * $n;
    $taxPc = max(0.0, min(90.0, (float)gmVal('tax', 10)));
    $tax   = floor($pot * $taxPc / 100);
    return [$pot - $tax, $tax];
}

// ============================================================
// 🖼 نمایش
// ============================================================

function gmName($p) {
    $u = trim((string)($p['uname'] ?? ''));
    if ($u !== '') return '@' . ltrim($u, '@');
    $n = trim((string)($p['name'] ?? ''));
    return $n !== '' ? h($n) : ('<code>' . (int)($p['id'] ?? 0) . '</code>');
}

function gmEmoji() { return (string)gmVal('emoji', '💎'); }

function gmText($g) {
    [$prize, $tax] = gmPrize($g);
    $ps = array_values($g['players']);

    if ($g['kind'] === 'duel') {
        if ($g['status'] === 'open')
            return gmT('duel_open', ['emoji' => gmEmoji(), 'stake' => gmNum($g['stake']),
                                     'host' => gmName($ps[0]), 'prize' => gmNum($prize),
                                     'tax' => gmNum($tax)]);
        return gmT('duel_turn', ['emoji' => gmEmoji(), 'stake' => gmNum($g['stake']),
                                 'p1' => gmName($ps[0]), 'p2' => gmName($ps[1] ?? []),
                                 'prize' => gmNum($prize),
                                 'turn' => gmName($g['players'][(string)$g['turn']] ?? [])]);
    }

    $left = max(0, (int)$g['ends'] - time());
    return gmT('rand_open', ['emoji' => gmEmoji(), 'stake' => gmNum($g['stake']),
                             'host' => gmName($ps[0]), 'count' => gmNum(count($ps)),
                             'prize' => gmNum($prize), 'tax' => gmNum($tax),
                             'left' => gmNum($left)]);
}

/**
 * دکمه‌های بازی.
 * برای دوز، هر خانه رنگ بازیکنی را می‌گیرد که زده — آبی برای اول،
 * سبز برای دوم. خانه‌ی خالی بی‌رنگ می‌ماند.
 */
function gmKb($g) {
    if ($g['status'] === 'open') {
        $jk = $g['kind'] === 'duel' ? 'duel_join' : 'rand_join';
        return inlineKb([[
            gmBtn($jk, [], 'gmj_' . $g['id'], 'success'),
            gmBtn('duel_cancel', [], 'gmc_' . $g['id'], 'danger'),
        ]]);
    }
    if ($g['kind'] !== 'duel' || $g['status'] !== 'playing') return null;

    $rows = [];
    for ($r = 0; $r < 3; $r++) {
        $line = [];
        for ($c = 0; $c < 3; $c++) {
            $i = $r * 3 + $c;
            $v = (int)$g['board'][$i];
            $b = ['text' => $v === 0 ? '·' : ($v === 1 ? '🔵' : '🟢'),
                  'callback_data' => 'gmm_' . $g['id'] . '_' . $i];
            if ($v === 1) $b['style'] = 'primary';    // بازیکن اول — آبی
            if ($v === 2) $b['style'] = 'success';    // بازیکن دوم — سبز
            $line[] = $b;
        }
        $rows[] = $line;
    }
    $rows[] = [gmBtn('duel_cancel', [], 'gmc_' . $g['id'], 'danger')];
    return inlineKb($rows);
}

/** پیام بازی را می‌سازد یا به‌روز می‌کند */
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

/**
 * 🏁 پیام نتیجه — چهار دکمه‌ی شیشه‌ای، دوتا بالا دوتا پایین.
 * راست‌ها برچسب‌اند و چپ‌ها عدد، دقیقا مثل کارت نتیجه‌ی خود بازی.
 */
function gmResultKb($prize, $wBal, $lBal) {
    return inlineKb([
        [['text' => gmNum($prize), 'callback_data' => 'gmnop', 'style' => 'success'],
         gmBtn('lbl_prize', [], 'gmnop', 'success')],
        [['text' => gmNum($lBal), 'callback_data' => 'gmnop', 'style' => 'danger'],
         gmBtn('lbl_lbal', [], 'gmnop', 'danger')],
    ]);
}

// ============================================================
// 🏁 پایان بازی
// ============================================================

function gmFinish($g, $winnerId, $loserId) {
    [$prize, $tax] = gmPrize($g);

    $w = $g['players'][(string)$winnerId] ?? ['id' => $winnerId];
    $l = $loserId ? ($g['players'][(string)$loserId] ?? ['id' => $loserId]) : [];

    gmAdd($winnerId, $prize, $w['name'] ?? '', $w['uname'] ?? '');
    gmSetGame($g['id'], function (&$x) use ($winnerId, $loserId) {
        $x['status'] = 'done'; $x['winner'] = (int)$winnerId; $x['loser'] = (int)$loserId;
        return true;
    });

    $slug = $g['kind'] === 'duel' ? 'duel_win' : 'rand_win';
    $text = gmT($slug, [
        'winner' => (int)$winnerId,
        'loser'  => (int)($loserId ?: 0),
        'wname'  => gmName($w),
        'lname'  => $l ? gmName($l) : '—',
        'prize'  => gmNum($prize),
        'tax'    => gmNum($tax),
        'stake'  => gmNum($g['stake']),
    ]);
    $kb = gmResultKb($prize, gmPoints($winnerId), $loserId ? gmPoints($loserId) : 0);

    if ((int)$g['msg']) editMsg(BOT_TOKEN, $g['chat'], (int)$g['msg'], $text, $kb);
    else                sendMsg(BOT_TOKEN, $g['chat'], $text, $kb);

}

/** شرط همه را برمی‌گرداند و بازی را می‌بندد */
function gmRefund($g, $why) {
    foreach ($g['players'] as $p) gmAdd((int)$p['id'], (float)$g['stake']);
    gmSetGame($g['id'], function (&$x) { $x['status'] = 'cancelled'; return true; });
    if ((int)$g['msg']) editMsg(BOT_TOKEN, $g['chat'], (int)$g['msg'], $why, null);
    else                sendMsg(BOT_TOKEN, $g['chat'], $why);
}

/** سه‌تایی برنده؟ برگشت شماره‌ی بازیکن یا ۰ */
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

// ============================================================
// ⏰ قرعه‌ها — چه با cron، چه با پیام بعدی
// ============================================================

/**
 * قرعه‌های رسیده را می‌کشد.
 *
 * هیچ بازی‌ای خودش باطل نمی‌شود. قبلا بازیِ بی‌حریف بعد از مدتی
 * خودبه‌خود لغو می‌شد؛ حالا تا وقتی حریف بیاید منتظر می‌ماند و فقط
 * سازنده — یا ادمین از پنل — می‌تواند ببنددش.
 */
function gmTick($limit = 20) {
    $now  = time();
    $done = 0;
    foreach (gmAll() as $g) {
        if ($done >= $limit) break;
        if (($g['status'] ?? '') !== 'open' || ($g['kind'] ?? '') !== 'rand') continue;
        if ((int)$g['ends'] <= 0 || $now < (int)$g['ends']) continue;
        gmDraw($g);
        $done++;
    }
    return $done;
}

/** قرعه‌کشی — یک برنده‌ی کاملا شانسی از بین شرکت‌کننده‌ها */
function gmDraw($g) {
    // قفلِ همان لحظه: دو درخواست هم‌زمان نباید دوبار جایزه بدهند
    $claimed = false;
    gmSetGame($g['id'], function (&$x) use (&$claimed) {
        if (($x['status'] ?? '') !== 'open') return false;
        $x['status'] = 'drawing';
        $claimed = true;
        return true;
    });
    if (!$claimed) return;

    $g = gmGet($g['id']) ?: $g;
    $ids = array_values(array_map(fn($p) => (int)$p['id'], $g['players']));

    if (count($ids) < 2) {
        // کسی نیامد؟ باطلش نمی‌کنیم — مهلت را از نو می‌گذاریم و همان‌جا
        // باز می‌ماند تا حریف پیدا شود. شرط هم دستِ کسی نمی‌ماند چون
        // بازی هنوز زنده است.
        gmSetGame($g['id'], function (&$x) {
            $x['status'] = 'open';
            $x['ends']   = time() + max(3, (int)gmVal('wait', 8));
            return true;
        });
        gmShow(gmGet($g['id']) ?: $g);
        return;
    }

    // شانسِ واقعی — نه mt_rand که قابل حدس زدن است
    $win = $ids[random_int(0, count($ids) - 1)];
    $others = array_values(array_filter($ids, fn($x) => $x !== $win));
    $lose = $others ? $others[random_int(0, count($others) - 1)] : 0;

    gmSetGame($g['id'], function (&$x) { $x['status'] = 'open'; return true; });  // تا gmFinish بتواند ببندد
    gmFinish(gmGet($g['id']) ?: $g, $win, $lose);
}

// ============================================================
// 💬 پیام‌های گروه
// ============================================================

function gmHandleText($text, $uid, $chatId, $name, $uname = '', $replyTo = null,
                      $isPrivate = false, $msg = null) {
    if (!gmOn()) return false;
    $raw = trim((string)$text);
    if ($raw === '' || mb_strlen($raw) > 40) return false;

    // قرعه‌های رسیده را همین‌جا هم می‌بندیم، تا بدون cron هم پیش برود
    gmTick(3);

    $extra = $replyTo ? ['reply_to_message_id' => $replyTo] : [];

    // 💎 موجودی — متن + یک دکمه‌ی شیشه‌ای
    foreach (gmWords(gmVal('word_bal', 'موجودی')) as $w) {
        if ($w === '' || mb_strtolower($raw) !== mb_strtolower($w)) continue;
        $pts = gmPoints($uid);
        sendMsg(BOT_TOKEN, $chatId, gmT('bal_head', ['emoji' => gmEmoji(), 'points' => gmNum($pts)]),
            inlineKb([[gmBtn('bal_btn', ['points' => gmNum($pts)], 'gmnop', 'primary')]]), $extra);
        return true;
    }

    // 📤 انتقال الماس — روی پیام طرف ریپلای کن
    if ($r = gmParseSend($raw)) {
        gmTransfer($r, $uid, $chatId, $name, $uname, $replyTo, $msg);
        return true;
    }

    $p = gmParse($raw);
    if (!$p) return false;
    [$kind, $stake] = $p;

    if ($isPrivate) { sendMsg(BOT_TOKEN, $chatId, gmT('group_only'), null, $extra); return true; }

    $min = max(1, (float)gmVal('min', 10));
    $max = max($min, (float)gmVal('max', 1e9));
    if ($stake < $min || $stake > $max) {
        sendMsg(BOT_TOKEN, $chatId, gmT('bad_stake', ['min' => gmNum($min), 'max' => gmNum($max)]), null, $extra);
        return true;
    }
    if ($old = gmOpenOf($uid, $chatId)) {
        // فقط «یک بازی باز داری» گفتن بن‌بست است؛ دکمه‌ی لغوِ همان بازی
        // را کنارش می‌گذاریم تا بشود همان‌جا رهایش کرد.
        sendMsg(BOT_TOKEN, $chatId, gmT('busy'),
            inlineKb([[gmBtn('duel_cancel', [], 'gmc_' . $old['id'], 'danger')]]), $extra);
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
    return true;
}

// ============================================================
// 📤 انتقال الماس
// ============================================================

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
    ]), inlineKb([
        [['text' => gmNum(gmPoints($uid)), 'callback_data' => 'gmnop', 'style' => 'primary'],
         gmBtn('send_bal', [], 'gmnop', 'primary')],
        [['text' => gmNum(gmPoints($toId)), 'callback_data' => 'gmnop', 'style' => 'success'],
         gmBtn('send_bal2', [], 'gmnop', 'success')],
    ]), $extra);

}

// ============================================================
// 🔘 دکمه‌ها
// ============================================================

function gmCallback($data, $uid, $chatId, $msgId, $cbId, $from = []) {
    if (!str_starts_with((string)$data, 'gm')) return false;
    if ($data === 'gmnop') { answerCb(BOT_TOKEN, $cbId); return true; }

    // پیشوندهای پنل، جای دیگری رسیدگی می‌شوند
    if (str_starts_with($data, 'gma')) return false;

    if (!preg_match('/^gm([jcm])_(g_[0-9a-f]+)(?:_(\d))?$/', $data, $m)) return false;
    [$all, $act, $gid] = $m;
    $g = gmGet($gid);
    if (!$g || !in_array($g['status'], ['open', 'playing'], true)) {
        answerCb(BOT_TOKEN, $cbId, gmT('gone'), true);
        return true;
    }

    $name  = (string)($from['first_name'] ?? '');
    $uname = (string)($from['username'] ?? '');

    // ❌ لغو — فقط سازنده
    if ($act === 'c') {
        if ((int)$g['host'] !== (int)$uid) { answerCb(BOT_TOKEN, $cbId, gmT('not_yours'), true); return true; }
        answerCb(BOT_TOKEN, $cbId, '❌');
        gmRefund($g, gmT('cancelled'));
        return true;
    }

    // قرعه‌ای که وقتش رسیده، همین‌جا بسته شود — نه اینکه منتظر پیام
    // بعدی گروه بماند. با مهلتِ کوتاه، همین یک خط فرقِ «درجا» و
    // «چند دقیقه بعد» است.
    if ($g['kind'] === 'rand' && $g['status'] === 'open'
        && (int)$g['ends'] > 0 && time() >= (int)$g['ends']) {
        gmDraw($g);
        answerCb(BOT_TOKEN, $cbId, gmT('gone'), true);
        return true;
    }

    // ✅ پیوستن
    if ($act === 'j') {
        if (isset($g['players'][(string)$uid])) {
            // سازنده روی بازیِ خودش می‌زند و هیچ اتفاقی نمی‌افتد — از بیرون
            // مثل این است که دکمه خراب است. پس صریح بگو منتظر حریفی.
            answerCb(BOT_TOKEN, $cbId, strip_tags(gmT('already')), true);
            return true;
        }
        if ($g['kind'] === 'duel' && count($g['players']) >= 2) {
            answerCb(BOT_TOKEN, $cbId, gmT('gone'), true); return true;
        }
        if ($g['kind'] === 'rand' && count($g['players']) >= max(2, (int)gmVal('join_max', 50))) {
            answerCb(BOT_TOKEN, $cbId, gmT('gone'), true); return true;
        }
        if (!gmAdd($uid, -(float)$g['stake'], $name, $uname)) {
            answerCb(BOT_TOKEN, $cbId,
                strip_tags(gmT('low', ['points' => gmNum(gmPoints($uid)), 'need' => gmNum($g['stake'])])), true);
            return true;
        }

        $joined = false;
        gmSetGame($gid, function (&$x) use ($uid, $name, $uname, &$joined) {
            if (isset($x['players'][(string)$uid])) return false;
            if ($x['kind'] === 'duel' && count($x['players']) >= 2) return false;
            $x['players'][(string)$uid] = ['id' => (int)$uid, 'name' => $name, 'uname' => $uname];
            if ($x['kind'] === 'duel') { $x['status'] = 'playing'; $x['turn'] = (int)$x['host']; }
            $joined = true;
            return true;
        });
        if (!$joined) {                       // یک نفر زودتر رسید — پول برگردد
            gmAdd($uid, (float)$g['stake']);
            answerCb(BOT_TOKEN, $cbId, gmT('gone'), true);
            return true;
        }
        answerCb(BOT_TOKEN, $cbId, '✅');
        $g = gmGet($gid);
        gmShow($g);
        // مهلت همین حالا تمام شد؟ منتظر تیکِ بعدی نمان
        if ($g && $g['kind'] === 'rand' && $g['status'] === 'open'
            && (int)$g['ends'] > 0 && time() >= (int)$g['ends']) gmDraw($g);
        return true;
    }

    // 🎯 زدن یک خانه‌ی دوز
    $cell = (int)($m[3] ?? -1);
    if ($g['kind'] !== 'duel' || $g['status'] !== 'playing' || $cell < 0 || $cell > 8) {
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

// ============================================================
// 👑 پنل
// ============================================================

function gmAdminHome($chatId, $msgId = null) {
    $c = gmCfg();
    $open = 0;
    foreach (gmAll() as $g) if (in_array($g['status'], ['open', 'playing'], true)) $open++;

    $t  = "🎮 <b>بازی‌ها</b>\n\n";
    $t .= 'وضعیت: ' . (gmOn() ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= "🎯 بازی باز: <b>{$open}</b>\n\n";
    $t .= "کلمه‌ها:\n";
    $t .= '• چالش دو نفره: <code>' . h($c['word_duel']) . " ۱۰۰</code>\n";
    $t .= '• قرعه‌ی شانسی: <code>' . h($c['word_rand']) . " ۱۰۰</code>\n";
    $t .= '• موجودی: <code>' . h($c['word_bal']) . "</code>\n";
    $t .= '• انتقال (ریپلای): <code>' . h($c['word_send']) . " ۱۰۰</code>\n\n";
    $t .= '🧾 مالیات جایزه: <b>' . $c['tax'] . "٪</b>\n";
    $t .= '🧾 مالیات انتقال: <b>' . $c['send_tax'] . "٪</b>\n";
    $t .= '⏳ انتظار قرعه: <b>' . $c['wait'] . "</b> ثانیه\n";
    $t .= '💎 شرط: <b>' . gmNum($c['min']) . '</b> تا <b>' . gmNum($c['max']) . "</b>\n";

    $rows = [
        [btnCb(gmOn() ? '✅ روشن' : '❌ خاموش', 'gmax', 'info')],
        [btnCb('🧾 مالیات جایزه', 'gmatax', 'admin'), btnCb('📤 مالیات انتقال', 'gmastax', 'admin')],
        [btnCb('⏳ انتظار قرعه', 'gmawait', 'admin'), btnCb('💎 کف و سقف شرط', 'gmarange', 'admin')],
        [btnCb('🗣 کلمه‌ها', 'gmaw_home', 'admin'), btnCb('✏️ متن‌ها', 'gmat_home', 'admin')],
        [btnCb('🧹 بستن بازی‌های باز', 'gmaclose', 'danger')],
        [btnCb(UT('back'), 'adm_home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function gmLabels() {
    return [
        'duel_open' => 'چالش — پیام باز', 'duel_turn' => 'چالش — حین بازی',
        'duel_win'  => 'چالش — نتیجه',    'duel_draw' => 'چالش — مساوی',
        'duel_join' => 'دکمه پیوستن',     'duel_cancel' => 'دکمه لغو',
        'rand_open' => 'قرعه — پیام باز', 'rand_win' => 'قرعه — نتیجه',
        'rand_none' => 'قرعه — بی‌شرکت‌کننده', 'rand_join' => 'دکمه شرکت در بازی',
        'lbl_prize' => 'برچسب جایزه برنده', 'lbl_wbal' => 'برچسب موجودی برنده',
        'lbl_lbal'  => 'برچسب موجودی بازنده',
        'bal_head'  => 'موجودی — متن',     'bal_btn' => 'موجودی — دکمه',
        'send_ok'   => 'انتقال — موفق',    'send_bal' => 'انتقال — برچسب فرستنده',
        'send_bal2' => 'انتقال — برچسب گیرنده', 'send_how' => 'انتقال — راهنما',
        'send_self' => 'انتقال — به خودت', 'off' => 'پیام خاموش بودن',
        'low'       => 'الماس کافی نیست',  'bad_stake' => 'شرط نامعتبر',
        'busy'      => 'بازی باز داری',    'not_yours' => 'مال تو نیست',
        'not_turn'  => 'نوبت تو نیست',     'taken' => 'خانه پر است',
        'gone'      => 'بازی تمام شده',    'cancelled' => 'بازی لغو شد',
        'group_only'=> 'فقط داخل گروه', 'already' => 'خودت داخل بازی هستی',
    ];
}

function gmLabel($k) { return gmLabels()[$k] ?? $k; }

function gmAdminTexts($chatId, $msgId, $page = 0) {
    $keys = array_keys((array)gmVal('texts', []));
    $per  = 12;
    $tot  = max(1, (int)ceil(count($keys) / $per));
    $page = max(0, min($tot - 1, (int)$page));
    $slice = array_slice($keys, $page * $per, $per);

    $t  = "✏️ <b>متن‌های بازی</b> — صفحه " . ($page + 1) . " از {$tot}\n\n";
    $t .= "هرچه بنویسید عینا همان می‌رود: ایموجی پرمیوم و quote سالم می‌مانند.\n\n";
    $rows = [];
    foreach ($slice as $k) {
        $v = (string)gmVal('texts.' . $k, '');
        $t .= '• <b>' . h(gmLabel($k)) . '</b>: <code>' .
              h(mb_substr(str_replace("\n", ' ', strip_tags($v)), 0, 34)) . "</code>\n";
        $rows[] = [btnCb(gmLabel($k), 'gmats_' . $k, 'admin')];
    }
    $nav = [];
    if ($page > 0)        $nav[] = btnCb('◀️', 'gmat_' . ($page - 1), 'nav');
    if ($page < $tot - 1) $nav[] = btnCb('▶️', 'gmat_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'gm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function gmAdminWords($chatId, $msgId) {
    $c = gmCfg();
    $t  = "🗣 <b>کلمه‌های بازی</b>\n\nهر کلمه را با ویرگول جدا کنید.\n\n";
    $map = ['word_duel' => 'چالش دو نفره', 'word_rand' => 'قرعه‌ی شانسی',
            'word_bal' => 'موجودی', 'word_send' => 'انتقال'];
    $rows = [];
    foreach ($map as $k => $lbl) {
        $t .= '• <b>' . h($lbl) . '</b>: <code>' . h((string)$c[$k]) . "</code>\n";
        $rows[] = [btnCb($lbl, 'gmaws_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'gm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
}

/** برگشت true یعنی این callback مال بخش بازی بود */
function gmAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'gm')) return false;

    if ($data === 'gm_home') { answerCb(BOT_TOKEN, $cbId); gmAdminHome($chatId, $msgId); return true; }
    if ($data === 'gmax') {
        gmSet(function (&$c) { $c['on'] = empty($c['on']); });
        answerCb(BOT_TOKEN, $cbId, '✅'); gmAdminHome($chatId, $msgId); return true;
    }
    if ($data === 'gmaclose') {
        $n = 0;
        foreach (gmAll() as $g)
            if (in_array($g['status'], ['open', 'playing'], true)) { gmRefund($g, gmT('cancelled')); $n++; }
        answerCb(BOT_TOKEN, $cbId, "🧹 {$n} بازی بسته شد", true);
        gmAdminHome($chatId, $msgId);
        return true;
    }
    if ($data === 'gmaw_home') { answerCb(BOT_TOKEN, $cbId); gmAdminWords($chatId, $msgId); return true; }
    if ($data === 'gmat_home') { answerCb(BOT_TOKEN, $cbId); gmAdminTexts($chatId, $msgId, 0); return true; }
    if (preg_match('/^gmat_(\d+)$/', $data, $m)) {
        answerCb(BOT_TOKEN, $cbId); gmAdminTexts($chatId, $msgId, (int)$m[1]); return true;
    }

    $asks = [
        'gmatax'   => ['gm_tax',   "🧾 چند درصد از جایزه به‌عنوان مالیات کم شود؟ (۰ تا ۹۰)"],
        'gmastax'  => ['gm_stax',  "📤 چند درصد مالیات روی انتقال الماس؟ (۰ تا ۹۰)"],
        'gmawait'  => ['gm_wait',  "⏳ قرعه چند ثانیه بعد کشیده شود؟ (پیشنهاد ۸ — کمترین ۳)"],
        'gmarange' => ['gm_range', "💎 کف و سقف شرط را با خط تیره بفرستید.\nمثال: <code>10-1000000</code>"],
    ];
    if (isset($asks[$data])) {
        [$act, $ask] = $asks[$data];
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, []);
        sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb('انصراف', 'gm_home', 'cancel')]]));
        return true;
    }
    foreach (['gmats_' => ['gm_text', 'texts.'], 'gmaws_' => ['gm_word', '']] as $pre => [$act, $path]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['k' => $k]);
        $cur = (string)gmVal($path . $k, '');
        sendMsg(BOT_TOKEN, $chatId,
            "✏️ <b>" . h(gmLabel($k)) . "</b> را بفرستید.\n\n" .
            ($act !== 'gm_text'
                ? "چند کلمه را با ویرگول جدا کنید.\n\n"
                : (gmIsBtn($k)
                    ? "🔘 این یکی روی <b>دکمه</b> می‌نشیند، پس متنِ ساده باشد.\n" .
                      "✨ ایموجی پرمیوم را جلوی متن بگذارید — خودش برداشته و درست روی دکمه گذاشته می‌شود.\n\n"
                    : "جای‌گذاری‌ها: " . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', gmVars($k))) . "\n\n")) .
            "الان:\n" . ($act === 'gm_text' ? $cur : '<code>' . h($cur) . '</code>'),
            inlineKb([[btnCb('انصراف', 'gm_home', 'cancel')]]));
        return true;
    }
    return false;
}

function gmVars($k) {
    if (str_starts_with($k, 'duel_win') || str_starts_with($k, 'rand_win'))
        return ['winner', 'loser', 'wname', 'lname', 'prize', 'tax', 'stake'];
    if (str_starts_with($k, 'duel_turn')) return ['emoji', 'stake', 'p1', 'p2', 'prize', 'turn'];
    if (str_starts_with($k, 'duel_open')) return ['emoji', 'stake', 'host', 'prize', 'tax'];
    if (str_starts_with($k, 'rand_open')) return ['emoji', 'stake', 'host', 'count', 'prize', 'tax', 'left'];
    if (str_starts_with($k, 'bal_'))      return ['emoji', 'points'];
    if (str_starts_with($k, 'send_ok'))   return ['from', 'to', 'amount', 'tax', 'total'];
    if ($k === 'low')                     return ['points', 'need'];
    if ($k === 'bad_stake')               return ['min', 'max'];
    if ($k === 'send_how')                return ['word'];
    return [];
}

/** برگشت true یعنی این گفتگو مال بخش بازی بود */
function gmStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'gm_')) return false;
    if ($uid !== ADMIN_ID) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $text = trim((string)($msg['text'] ?? ''));
    $back = inlineKb([[btnCb('🎮 بازی‌ها', 'gm_home', 'admin')]]);

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
    if ($action === 'gm_wait') {
        $v = (int)norm_fa_digits($text);
        if ($v < 3 || $v > 3600) { sendMsg(BOT_TOKEN, $chatId, "⚠️ بین ۳ تا ۳۶۰۰ ثانیه باشد."); return true; }
        gmSet(function (&$c) use ($v) { $c['wait'] = $v; });
        return $done();
    }
    if ($action === 'gm_range') {
        if (!preg_match('/^\s*([\d,٬]+)\s*[-–ـ]\s*([\d,٬]+)\s*$/u', norm_fa_digits($text), $m)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ مثل <code>10-1000000</code> بفرستید."); return true;
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
            // برچسب دکمه HTML نمی‌پذیرد. پس متنِ ساده ذخیره می‌شود و
            // ایموجی پرمیوم جدا، به‌شکل شناسه — همان‌طور که خودِ تلگرام
            // برای دکمه می‌خواهد.
            if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
            $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
            $icon = $ids ? (string)$ids[0] : '';
            gmSet(function (&$c) use ($k, $text, $icon) {
                $c['texts'][$k] = $text;
                if (!isset($c['icons']) || !is_array($c['icons'])) $c['icons'] = [];
                $c['icons'][$k] = $icon;          // نبود؟ یعنی برداشته شود
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد" . ($icon !== '' ? " — ایموجی پرمیوم هم روی دکمه نشست." : '.') .
                "\n\nاین‌طور دیده می‌شود:",
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

/** پیش‌نمایش یک متن با داده‌ی نمونه */
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

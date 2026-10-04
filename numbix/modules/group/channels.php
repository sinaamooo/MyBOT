<?php
defined('NB_ROOT') || exit;

if (!defined('CH_LIB')) define('CH_LIB', 1);


function chStreams() {
    return [
        'topup'    => '🧾 گزارش شارژ حساب',
        'mini_num' => '☎️ فروش شماره مجازی',
        'tech'     => '🛠 گزارش فنی',
        'ticket'   => '🎫 تیکت پشتیبانی',
    ];
}

function chDefaults() {
    return [
        'topup' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "<b>شارژ حساب</b>\n\n" .
                      "{user}\n<code>{uid}</code>\n" .
                      "<blockquote>مبلغ: <b>{amount}</b> تومان\n" .
                      "موجودی: <b>{balance}</b> تومان</blockquote>\n" .
                      "<code>{code}</code>\n{date}",
            'photo'   => false,
            'buttons' => [
                ['on' => 1, 'text' => '🤖 ربات', 'url' => '', 'color' => 'primary', 'icon' => ''],
            ],
        ],
        'tech' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "<b>گزارش فنی</b>\n\n{text}\n\n{date}",
            'photo' => false, 'buttons' => [],
        ],
        'ticket' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "<b>تیکت پشتیبانی</b>\n\n{user} (<code>{uid}</code>)\n\n{text}\n\n{date}",
            'photo' => false, 'buttons' => [],
        ],
        'mini_num' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "{icon} <b>گزارش خرید موفق</b>\n\n" .
                      "<blockquote>👤 خریدار : <code>{buyer}</code>\n" .
                      "☎️ سفارش : {product} — <code>{phone}</code>\n" .
                      "💰 مبلغ پرداخت شده : <b>{amount}</b> تومان</blockquote>",
            'photo' => false,
            'cards' => [],
            'buttons' => [
                ['on' => 1, 'text' => '☎️ خرید شماره', 'url' => '', 'color' => 'success', 'icon' => ''],
                ['on' => 1, 'text' => '💬 پشتیبانی',   'url' => '', 'color' => 'primary', 'icon' => ''],
            ],
            'premium_icon' => '',
        ],
    ];
}

function chCfg() {
    $c = cfg()['channels'] ?? null;
    $out = chDefaults();
    if (!is_array($c)) return $out;
    foreach ($out as $k => $def) {
        if (!isset($c[$k]) || !is_array($c[$k])) continue;
        $row = array_replace($def, $c[$k]);
        if (isset($c[$k]['buttons']) && is_array($c[$k]['buttons'])) {
            $row['buttons'] = [];
            foreach (array_values($c[$k]['buttons']) as $i => $b) {
                $base = $def['buttons'][$i] ?? ['on' => 1, 'text' => '', 'url' => '', 'color' => 'primary', 'icon' => ''];
                $row['buttons'][] = array_replace($base, is_array($b) ? $b : []);
            }
        }
        $out[$k] = $row;
    }
    return $out;
}

function chOf($stream) { return chCfg()[$stream] ?? chDefaults()['tech']; }

function chSet($stream, callable $fn) {
    cfgSet(function (&$c) use ($stream, $fn) {
        if (!isset($c['channels']) || !is_array($c['channels'])) $c['channels'] = [];
        if (!isset($c['channels'][$stream]) || !is_array($c['channels'][$stream]))
            $c['channels'][$stream] = chDefaults()[$stream] ?? [];
        $fn($c['channels'][$stream]);
    });
}

function chReady($stream) {
    $s = chOf($stream);
    return !empty($s['on']) && trim((string)$s['chat_id']) !== '';
}


function chSend($stream, array $vars, $photo = null, array $extraRows = []) {
    if (!chReady($stream)) return false;
    $s = chOf($stream);
    if ($dead = chatDown($s['chat_id'])) { chLastErr($dead); return false; }

    $vars += ['date' => chDate()];
    $text = chFill((string)$s['text'], $vars);
    if (trim($text) === '') return false;

    $extra = [];
    if ((int)$s['thread_id'] > 0) $extra['message_thread_id'] = (int)$s['thread_id'];

    $kb = chKeyboard($s);
    if ($extraRows) {
        $rows = array_merge($extraRows, $kb['inline_keyboard'] ?? []);
        $kb   = ['inline_keyboard' => $rows];
    }

    if ($photo !== null && !empty($s['photo'])) {
        $d = array_merge([
            'chat_id' => $s['chat_id'], 'photo' => $photo,
            'caption' => $text, 'parse_mode' => 'HTML',
        ], $extra);
        if ($kb) $d['reply_markup'] = json_encode($kb);
        $r = chCaptionOk($text) ? tg(BOT_TOKEN, 'sendPhoto', $d) : ['ok' => false, 'description' => 'caption too long'];
        if (empty($r['ok']) && $kb && isStyleError($r)) {
            $d['reply_markup'] = json_encode(stripStyles($kb));
            $r = tg(BOT_TOKEN, 'sendPhoto', $d);
        }
        if (!empty($r['ok'])) return true;
        chWarnPhoto($stream, (string)($r['description'] ?? ''));
    }

    $r = sendMsg(BOT_TOKEN, $s['chat_id'], $text, $kb, $extra);
    if (empty($r['ok']) && $extra && tgThreadGone($r)) {
        $r = sendMsg(BOT_TOKEN, $s['chat_id'], $text, $kb);
        if (!empty($r['ok'])) chSet($stream, function (&$x) { $x['thread_id'] = 0; });
    }
    chatDown($s['chat_id'], $r);
    if (empty($r['ok'])) {
        chLastErr($r);
        chWarn($stream, $r);
        return false;
    }
    return true;
}

function chLastErr($set = null) {
    static $e = [];
    if ($set !== null) $e = (array)$set;
    return $e;
}

function chWarn($stream, array $res) {
    if (!function_exists('adminAlertOnce')) return;
    $label = chStreams()[$stream] ?? $stream;
    $cid = trim((string)(chOf($stream)['chat_id'] ?? ''));
    adminAlertOnce('ch_' . $stream,
        "📡 <b>گزارش به کانال نرفت</b>\n\n" . h($label) . ($cid !== '' ? "\nگروه: <code>" . h($cid) . '</code>' : '') . "\n" . tgWhy($res) .
        (tgChatDead($res) ? "\n\nتا درست شود، این گزارش‌ها به پیویِ مدیر می‌آیند." : '') . "\n\n/panel ← 📡 کانال‌های گزارش");
}

function chWarnPhoto($stream, $why) {
    if (!function_exists('adminAlertOnce')) return;
    $label = chStreams()[$stream] ?? $stream;
    $long = $why === 'caption too long';
    adminAlertOnce('chp_' . $stream,
        "🖼 <b>عکسِ گزارش نرفت — گزارش بدونِ عکس فرستاده شد</b>\n\n" . h($label) . "\n" .
        ($long
            ? "متنِ گزارش برای زیرِ عکس بیشتر از ۱۰۲۴ حرف است؛ کوتاه‌ترش کنید."
            : "<code>" . h(mb_substr($why, 0, 180)) . "</code>\n\nاگر ربات را عوض کرده‌اید، کارت را دوباره اضافه کنید.") .
        "\n\n/panel ← 📡 کانال‌های گزارش");
}

function chCaptionOk($text) {
    return mb_strlen(html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) <= 1024;
}

function chCards($k = 'mini_num') {
    return array_values(array_filter(array_map('strval', (array)(chOf($k)['cards'] ?? [])), fn($x) => $x !== ''));
}

function chCardPick($k = 'mini_num') {
    $c = chCards($k);
    return $c ? $c[array_rand($c)] : null;
}

function chAdminCards($chatId, $msgId, $k = 'mini_num') {
    $s  = chOf($k);
    $c  = chCards($k);
    $on = !empty($s['photo']);
    $t  = "🖼 <b>کارتِ گزارشِ خرید</b>\n\n" .
          'ارسالِ کارت: ' . ($on ? '✅ روشن' : '❌ خاموش') . "\n" .
          'کارت‌ها: <b>' . fmtNum(count($c)) . '</b> از ۱۰' .
          (!chCaptionOk($s['text']) ? "\n\n⚠️ متنِ گزارش بیشتر از ۱۰۲۴ حرف است" : '');
    $rows = [[btnCb($on ? '✅ ارسالِ کارت: روشن' : '❌ ارسالِ کارت: خاموش', 'chcx_' . $k, 'info')]];
    foreach ($c as $i => $fid) {
        $rows[] = [btnCb('👀 کارت ' . fmtNum($i + 1), 'chcv_' . $k . '_' . $i, 'info'),
                   btnCb('🗑 حذف کارت ' . fmtNum($i + 1), 'chcd_' . $k . '_' . $i, 'reject')];
    }
    if (count($c) < 10) $rows[] = [btnCb('➕ افزودنِ کارت', 'chca_' . $k, 'confirm')];
    if ($c && chReady($k)) $rows[] = [btnCb('🧪 تست در کانال', 'cht_' . $k, 'confirm')];
    $rows[] = [btnCb(UT('back'), 'chs_' . $k, 'nav')];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function chBotLink() {
    static $u = null;
    if ($u === null) $u = function_exists('botUsername') ? trim((string)botUsername()) : '';
    return $u !== '' ? 'https://t.me/' . ltrim($u, '@') : '';
}

function chButtonUrl($b) {
    $url = trim((string)($b['url'] ?? ''));
    if ($url !== '') return str_replace('{bot}', ltrim(chBotLink() !== '' ? (string)botUsername() : '', '@'), $url);
    return chBotLink();
}

function chKeyboard($s) {
    $rows = [];
    $line = [];
    foreach ((array)($s['buttons'] ?? []) as $b) {
        if (empty($b['on'])) continue;
        $url = chButtonUrl($b);
        $txt = trim((string)($b['text'] ?? ''));
        if ($url === '' || $txt === '') continue;
        $btn = ['text' => $txt, 'url' => $url];
        if (function_exists('gs') && ($st = gs((string)($b['color'] ?? '')))) $btn['style'] = $st;
        if (trim((string)($b['icon'] ?? '')) !== '') $btn['icon_custom_emoji_id'] = (string)$b['icon'];
        $line[] = $btn;
        if (count($line) === 2) { $rows[] = $line; $line = []; }
    }
    if ($line) $rows[] = $line;
    return $rows ? ['inline_keyboard' => $rows] : null;
}

function chFill($tpl, array $vars) {
    $map = [];
    foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string)$v;
    return strtr((string)$tpl, $map);
}

function chDate() {
    if (function_exists('pxJalali')) return pxJalali();
    return date('Y/m/d H:i');
}

function chUser($uid, $uname = '', $fname = '') {
    $n = trim((string)$fname);
    $u = trim((string)$uname);
    if ($u !== '') return '@' . ltrim($u, '@');
    return $n !== '' ? emKeep(h($n)) : ('<code>' . (int)$uid . '</code>');
}


function chTopup($order) {
    if (!is_array($order)) return false;
    $uid = (int)($order['user_id'] ?? 0);
    $u   = function_exists('getUser') ? (getUser($uid) ?: []) : [];
    return chSend('topup', [
        'user'    => chUser($uid, $order['username'] ?? '', $u['name'] ?? ''),
        'uid'     => $uid,
        'amount'  => fmtNum((float)($order['amount'] ?? 0)),
        'balance' => fmtNum((float)($u['balance'] ?? 0)),
        'code'    => (string)($order['id'] ?? ''),
    ]);
}

function chBuy($uid, $uname, $productName, $amount, $code, $phone = '') {
    $label = chStreams()['mini_num'];
    $plainIcon = '';
    if (preg_match('/^(\X)\s+(.*)$/u', $label, $m)) { $plainIcon = $m[1]; $label = $m[2]; }

    $pid = trim((string)(chOf('mini_num')['premium_icon'] ?? ''));
    $icon = ($pid !== '' && ctype_digit($pid))
        ? '<tg-emoji emoji-id="' . h($pid) . '">' . h($plainIcon ?: '☎️') . '</tg-emoji>'
        : '';

    chSend('mini_num', [
        'user'    => chUser($uid, $uname, ''),
        'uid'     => (int)$uid,
        'buyer'   => chMaskId($uid),
        'product' => (string)$productName,
        'qty'     => 1,
        'phone'   => chMaskPhone($phone),
        'amount'  => fmtNum((float)$amount),
        'code'    => (string)$code,
        'section' => $label,
        'icon'    => $icon,
    ], !empty(chOf('mini_num')['photo']) ? chCardPick() : null);
}

function chMaskId($uid) {
    $d = preg_replace('/\D/', '', (string)$uid);
    $n = strlen($d);
    if ($n < 3) return str_repeat('×', max(1, $n));
    $m = $n >= 10 ? 5 : 4;
    if ($n <= $m + 1) return substr($d, 0, 1) . str_repeat('×', $n - 1);
    $head = max(1, $n - $m - 2);
    return substr($d, 0, $head) . str_repeat('×', $m) . substr($d, $head + $m);
}

function chMaskPhone($phone) {
    $d = preg_replace('/\D/', '', (string)$phone);
    $n = strlen($d);
    if ($n < 6) return '×××';
    $head = min(4, $n - 6);
    return '+' . substr($d, 0, $head) . str_repeat('×', $n - $head - 2) . substr($d, -2);
}


function chAdminHome($chatId, $msgId = null) {
    $t  = "📡 <b>کانال‌های متصل</b>\n\n";

    $rows = [];
    foreach (chStreams() as $k => $label) {
        $s = chOf($k);
        $set = trim((string)$s['chat_id']) !== '';
        $t .= (chReady($k) ? '✅' : ($set ? '⏸' : '⚪️')) . ' <b>' . h($label) . "</b>\n";
        $t .= '   ' . ($set
                ? '<code>' . h((string)$s['chat_id']) . '</code>' .
                  ((int)$s['thread_id'] > 0 ? ' · 🧵 ' . (int)$s['thread_id'] : '')
                : 'تنظیم نشده') . "\n";
        $rows[] = [btnCb($label, 'chs_' . $k, 'admin')];
    }
    $nc = count(chCards('mini_num'));
    $t .= "\n🖼 <b>کارتِ گزارشِ خرید</b>: " . ($nc ? fmtNum($nc) . ' کارت' . (!empty(chOf('mini_num')['photo']) ? ' — روشن' : ' — خاموش') : '—') . "\n";
    $rows[] = [btnCb('🖼 کارتِ گزارشِ خرید (' . fmtNum($nc) . ')', 'chc_mini_num', 'confirm')];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    $t = mb_substr($t, 0, 3800);
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function chAdminStream($chatId, $msgId, $k) {
    $label = chStreams()[$k] ?? null;
    if ($label === null) { chAdminHome($chatId, $msgId); return; }
    $s = chOf($k);

    $t  = '<b>' . h($label) . "</b>\n\n";
    $t .= 'وضعیت: ' . (!empty($s['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'مقصد: ' . (trim((string)$s['chat_id']) !== ''
            ? '<code>' . h((string)$s['chat_id']) . '</code>' : '— تنظیم نشده') . "\n";
    $t .= 'تاپیک: ' . ((int)$s['thread_id'] > 0 ? (int)$s['thread_id'] : 'بدون تاپیک') . "\n";
    if ($k === 'mini_num') {
        $nc = count(chCards($k));
        $t .= '🖼 کارتِ گزارش: ' . (!empty($s['photo']) && $nc ? '✅ روشن — ' . fmtNum($nc) . ' کارت' : ($nc ? '❌ خاموش — ' . fmtNum($nc) . ' کارت' : 'کارتی گذاشته نشده')) . "\n";
    }
    if ($k !== 'topup' && $k !== 'tech' && $k !== 'ticket') {
        $pid = trim((string)($s['premium_icon'] ?? ''));
        $t .= '🌟 ایموجیِ پریمیوم: ' . ($pid !== '' ? '<tg-emoji emoji-id="' . h($pid) . '">🛒</tg-emoji> تنظیم‌شده' : 'تنظیم‌نشده') . "\n";
    }
    $t .= "\n<b>متن گزارش:</b>\n" . $s['text'] . "\n\n";
    $t .= "جای‌گذاری‌ها: " . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', chVarsOf($k)));

    $rows = [
        [btnCb(!empty($s['on']) ? '✅ روشن' : '❌ خاموش', 'chx_' . $k, 'info'),
         btnCb('🧪 تست', 'cht_' . $k, 'confirm')],
        [btnCb('🔗 گروه و تاپیک', 'chl_' . $k, 'admin')],
        [btnCb('✏️ متن گزارش', 'chm_' . $k, 'admin')],
    ];
    $resetRow = $k !== 'topup' && $k !== 'tech' && $k !== 'ticket'
        ? [btnCb('🌟 ایموجیِ پریمیوم', 'chi_' . $k, 'admin'), btnCb('🔄 بازنشانی متن', 'chrs_' . $k, 'confirm')]
        : [btnCb('🔄 بازنشانی متن به پیش‌فرض', 'chrs_' . $k, 'confirm')];
    $rows[] = $resetRow;
    if ($k === 'mini_num') $rows[] = [btnCb('🖼 کارتِ گزارشِ خرید (' . fmtNum(count(chCards($k))) . ')', 'chc_' . $k, 'admin')];
    $t .= "\n\n<b>دکمه‌ها:</b>";
    foreach ((array)$s['buttons'] as $i => $b) {
        $eff = chButtonUrl($b);
        $t .= "\n" . (!empty($b['on']) ? '✅' : '❌') . ' ' . h(trim((string)$b['text']) ?: 'بی‌متن') . ' → ' .
              ($eff !== ''
                ? '<code>' . h(mb_substr($eff, 0, 60)) . '</code>' .
                  (trim((string)($b['url'] ?? '')) === '' ? ' <i>(خودِ ربات)</i>' : '')
                : '<i>لینک ندارد — دیده نمی‌شود</i>');
        $rows[] = [
            btnCb(!empty($b['on']) ? '✅' : '❌', 'chbx_' . $k . '_' . $i, 'info'),
            btnCb('✏️ ' . (trim((string)$b['text']) !== '' ? mb_substr($b['text'], 0, 12) : 'دکمه ' . ($i + 1)),
                  'chbt_' . $k . '_' . $i, 'admin'),
            btnCb('🔗 لینک', 'chbu_' . $k . '_' . $i, 'admin'),
        ];
    }
    $rows[] = [btnCb(UT('back'), 'ch_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, mb_substr($t, 0, 3800), inlineKb($rows));
}

function chVarsOf($k) {
    if ($k === 'topup')  return ['user', 'uid', 'amount', 'balance', 'code', 'date'];
    if ($k === 'tech')   return ['text', 'date'];
    if ($k === 'ticket') return ['user', 'uid', 'text', 'date'];
    return ['buyer', 'product', 'qty', 'phone', 'amount', 'user', 'uid', 'code', 'section', 'icon', 'date'];
}

function chTechAlert($text, $kb = null) {
    $rows = (is_array($kb) && !empty($kb['inline_keyboard'])) ? $kb['inline_keyboard'] : [];
    $sent = chReady('tech') ? chSend('tech', ['text' => $text], null, $rows) : false;
    if (!$sent && function_exists('notifyAdmins')) $sent = notifyAdmins($text, $kb);
    return $sent;
}

function chTicketAlert($uid, $uname, $fname, $text, $kb = null) {
    $vars = ['user' => chUser($uid, $uname, $fname), 'uid' => (int)$uid, 'text' => emKeep($text)];
    $rows = (is_array($kb) && !empty($kb['inline_keyboard'])) ? $kb['inline_keyboard'] : [];
    $sent = chReady('ticket') ? chSend('ticket', $vars, null, $rows) : false;
    if (!$sent && function_exists('notifyAdmins')) {
        $plain = "🎫 <b>تیکت جدید</b>\n\n" . $vars['user'] . " (<code>{$uid}</code>)\n\n" . $vars['text'];
        $sent = notifyAdmins($plain, $kb);
    }
    return $sent;
}

function chAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'ch')) return false;

    if ($data === 'ch_home')  { answerCb(BOT_TOKEN, $cbId); chAdminHome($chatId, $msgId); return true; }

    foreach (['chs_' => 'open', 'chx_' => 'toggle', 'cht_' => 'test', 'chrs_' => 'reset'] as $pre => $what) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!isset(chStreams()[$k])) { answerCb(BOT_TOKEN, $cbId); return true; }

        if ($what === 'toggle') {
            chSet($k, function (&$s) { $s['on'] = empty($s['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
        } elseif ($what === 'reset') {
            $def = chDefaults()[$k]['text'] ?? '';
            chSet($k, function (&$s) use ($def) { $s['text'] = $def; });
            answerCb(BOT_TOKEN, $cbId, '✅ بازنشانی شد');
        } elseif ($what === 'test') {
            answerCb(BOT_TOKEN, $cbId);
            if (!chReady($k)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ اول گروه را تنظیم و جریان را روشن کنید.");
                return true;
            }
            chatDown(chOf($k)['chat_id'], false);
            $ok = chSend($k, chSampleVars($k), $k === 'mini_num' && !empty(chOf($k)['photo']) ? chCardPick($k) : null);
            sendMsg(BOT_TOKEN, $chatId, $ok ? "✅ گزارشِ آزمایشی در گروه فرستاده شد." : "🔴 نرفت: " . tgWhy(chLastErr()));
            return true;
        } else {
            answerCb(BOT_TOKEN, $cbId);
        }
        chAdminStream($chatId, $msgId, $k);
        return true;
    }

    if (preg_match('/^chc(x|a)?_([a-z0-9_]+)$/', $data, $m) && $m[2] === 'mini_num') {
        $k = $m[2];
        if ($m[1] === 'x') {
            chSet($k, function (&$s) { $s['photo'] = empty($s['photo']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
        } elseif ($m[1] === 'a') {
            answerCb(BOT_TOKEN, $cbId);
            if (count(chCards($k)) >= 10) { sendMsg(BOT_TOKEN, $chatId, "⚠️ حداکثر ۱۰ کارت"); return true; }
            setState(admStateUid($chatId), 'ch_card', ['k' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "🖼 <b>کارتِ تازه</b> · عکس ۱۲۸۰×۷۲۰",
                inlineKb([[btnCb(UT('cancel'), 'chc_' . $k, 'cancel')]]));
            return true;
        } else {
            answerCb(BOT_TOKEN, $cbId);
            clearState(admStateUid($chatId));
        }
        chAdminCards($chatId, $msgId, $k);
        return true;
    }
    if (preg_match('/^chc([vd])_([a-z0-9_]+)_(\d+)$/', $data, $m) && $m[2] === 'mini_num') {
        $k = $m[2]; $i = (int)$m[3];
        $c = chCards($k);
        if (!isset($c[$i])) { answerCb(BOT_TOKEN, $cbId, 'این کارت دیگر نیست.', true); chAdminCards($chatId, $msgId, $k); return true; }
        if ($m[1] === 'v') {
            answerCb(BOT_TOKEN, $cbId);
            $r = sendFile(BOT_TOKEN, $chatId, 'photo', $c[$i], '🖼 کارت ' . fmtNum($i + 1), false,
                          inlineKb([[btnCb('✖️ بستن', 'chcq', 'nav')]]));
            if (empty($r['ok'])) sendMsg(BOT_TOKEN, $chatId, "⚠️ این کارت باز نشد؛ حذفش کنید و دوباره اضافه کنید.");
            return true;
        }
        $fid = $c[$i];
        chSet($k, function (&$s) use ($fid) {
            $s['cards'] = array_values(array_filter((array)($s['cards'] ?? []), fn($x) => (string)$x !== $fid));
        });
        answerCb(BOT_TOKEN, $cbId, '🗑 حذف شد');
        chAdminCards($chatId, $msgId, $k);
        return true;
    }
    if ($data === 'chcq') {
        answerCb(BOT_TOKEN, $cbId);
        if ($msgId) delMsg(BOT_TOKEN, $chatId, $msgId);
        return true;
    }

    if (preg_match('/^chbx_([a-z0-9_]+)_(\d+)$/', $data, $m)) {
        chSet($m[1], function (&$s) use ($m) {
            $i = (int)$m[2];
            if (isset($s['buttons'][$i])) $s['buttons'][$i]['on'] = empty($s['buttons'][$i]['on']) ? 1 : 0;
        });
        answerCb(BOT_TOKEN, $cbId, '✅');
        chAdminStream($chatId, $msgId, $m[1]);
        return true;
    }

    $asks = [
        'chl_'  => ['ch_link', "🔗 <b>مقصد</b>\n\n<code>https://t.me/c/1234567890/11</code> · <code>-100…</code> · <code>@channel</code> · <code>-</code>"],
        'chm_'  => ['ch_text', "✏️ <b>متن گزارش</b>"],
        'chi_'  => ['ch_icon', "🌟 <b>ایموجیِ پریمیوم</b> <code>{icon}</code> · <code>-</code>"],
    ];
    foreach ($asks as $pre => [$act, $ask]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!isset(chStreams()[$k])) { answerCb(BOT_TOKEN, $cbId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $act, ['k' => $k]);
        $more = ($act === 'ch_text')
            ? "\n\n" . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', chVarsOf($k))) .
              "\n\nالان:\n" . chOf($k)['text']
            : '';
        sendMsg(BOT_TOKEN, $chatId, $ask . $more, inlineKb([[btnCb('انصراف', 'chs_' . $k, 'cancel')]]));
        return true;
    }
    if (preg_match('/^chb([tu])_([a-z0-9_]+)_(\d+)$/', $data, $m)) {
        $isText = $m[1] === 't';
        answerCb(BOT_TOKEN, $cbId);
        setState(admStateUid($chatId), $isText ? 'ch_btntext' : 'ch_btnurl', ['k' => $m[2], 'i' => (int)$m[3]]);
        sendMsg(BOT_TOKEN, $chatId, $isText
            ? "✏️ <b>متن دکمه</b>"
            : "🔗 <b>لینک دکمه</b>\n\n<code>https://t.me/{bot}?start=shop</code> · <code>-</code>",
            inlineKb([[btnCb('انصراف', 'chs_' . $m[2], 'cancel')]]));
        return true;
    }
    return false;
}

function chSampleVars($k) {
    if ($k === 'topup') return ['user' => '@testuser', 'uid' => 123456789, 'amount' => fmtNum(500000),
                                'balance' => fmtNum(750000), 'code' => 'TEST-1234'];
    if ($k === 'tech')  return ['text' => '🧪 این یک گزارشِ فنیِ آزمایشی است.'];
    if ($k === 'ticket') return ['user' => '@testuser', 'uid' => 123456789,
                                 'text' => '🧪 این یک تیکتِ آزمایشی است.'];
    $label = chStreams()[$k];
    $plainIcon = '☎️';
    if (preg_match('/^(\X)\s+(.*)$/u', $label, $m)) { $plainIcon = $m[1]; $label = $m[2]; }
    $pid = trim((string)(chOf($k)['premium_icon'] ?? ''));
    $icon = ($pid !== '' && ctype_digit($pid))
        ? '<tg-emoji emoji-id="' . h($pid) . '">' . h($plainIcon) . '</tg-emoji>'
        : '';
    return ['user' => '@testuser', 'uid' => 123456789, 'buyer' => chMaskId(1234567890),
            'product' => '🇺🇸 آمریکا · اپراتور ۱', 'phone' => chMaskPhone('12025550143'),
            'amount' => fmtNum(149000), 'code' => 'TEST-1234',
            'section' => $label, 'icon' => $icon];
}

function chStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'ch_')) return false;
    if (!isAdmin($uid)) return false;

    $st   = getState($uid);
    $sd   = $st['data'] ?? [];
    $k    = (string)($sd['k'] ?? '');
    $text = trim((string)($msg['text'] ?? ''));
    $blank = ($text === '-' || $text === '—');
    if (!isset(chStreams()[$k])) { clearState($uid); return true; }

    $done = function ($m = "✅ ذخیره شد.") use ($uid, $chatId, $k) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $m, inlineKb([[btnCb('📡 کانال‌های متصل', 'chs_' . $k, 'admin')]]));
        return true;
    };

    if ($action === 'ch_link') {
        if ($blank) {
            chSet($k, function (&$s) { $s['chat_id'] = ''; $s['thread_id'] = 0; $s['on'] = false; });
            return $done("🧹 پاک شد.");
        }
        [$chat, $thread, $probe] = chatLinkResolve($text);
        if ($chat === null) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ آیدی از این لینک درنیامد");
            return true;
        }
        if (empty($probe['ok'])) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ ربات به این گروه دسترسی ندارد: " . tgWhy((array)$probe));
            return true;
        }
        chSet($k, function (&$s) use ($chat, $thread) {
            $s['chat_id'] = $chat; $s['thread_id'] = (int)$thread; $s['on'] = true;
        });
        return $done("✅ وصل شد: <code>" . h($chat) . '</code>' .
                     ($thread > 0 ? " · 🧵 {$thread}" : ''));
    }

    if ($action === 'ch_card') {
        $p = $msg['photo'] ?? null;
        if (!$p) {
            $mime = (string)($msg['document']['mime_type'] ?? '');
            sendMsg(BOT_TOKEN, $chatId, str_starts_with($mime, 'image/')
                ? "⚠️ فایل پذیرفته نیست؛ عکس لازم است"
                : "⚠️ عکس لازم است",
                inlineKb([[btnCb(UT('cancel'), 'chc_' . $k, 'cancel')]]));
            return true;
        }
        $fid = (string)($p[count($p) - 1]['file_id'] ?? '');
        if ($fid === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ عکس لازم است"); return true; }
        $n = 0;
        chSet($k, function (&$s) use ($fid, &$n) {
            $c = array_values(array_filter(array_map('strval', (array)($s['cards'] ?? []))));
            if (count($c) < 10 && !in_array($fid, $c, true)) $c[] = $fid;
            $s['cards'] = $c;
            $s['photo'] = true;
            $n = count($c);
        });
        clearState($uid);
        $s = chOf($k);
        $cap = chFill((string)$s['text'], chSampleVars($k) + ['date' => chDate()]);
        $r = chCaptionOk($cap) ? sendFile(BOT_TOKEN, $chatId, 'photo', $fid, $cap, false, chKeyboard($s)) : ['ok' => false];
        sendMsg(BOT_TOKEN, $chatId,
            (!empty($r['ok']) ? "✅ کارت اضافه شد؛ بالا پیش‌نمایشِ گزارش است." : "✅ کارت اضافه شد.\n⚠️ متنِ گزارش برای زیرِ عکس طولانی است؛ کوتاه‌ترش کنید.") .
            "\nکارت‌ها: " . fmtNum($n) . ' از ۱۰',
            inlineKb([[btnCb('🖼 کارت‌های گزارش', 'chc_' . $k, 'admin')]]));
        return true;
    }

    if ($action === 'ch_text') {
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        chSet($k, function (&$s) use ($html) { $s['text'] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد. پیش‌نمایش:");
        sendMsg(BOT_TOKEN, $chatId, chFill($html, chSampleVars($k) + ['date' => chDate()]),
                chKeyboard(chOf($k)) ?: inlineKb([[btnCb('📡 برگرد', 'chs_' . $k, 'admin')]]));
        return true;
    }

    if ($action === 'ch_icon') {
        $ids = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $v = $ids ? (string)$ids[0] : ($blank ? '' : preg_replace('/\D/', '', norm_fa_digits($text)));
        if (!$blank && $v === '') {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ایموجی پیدا نشد.\n" .
                "<code>-</code>");
            return true;
        }
        chSet($k, function (&$s) use ($v) { $s['premium_icon'] = $v; });
        return $done($v === '' ? '✅ حذف شد.' : '✅ ثبت شد.');
    }

    $i = (int)($sd['i'] ?? -1);
    if ($action === 'ch_btntext') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $icon = $ids ? (string)$ids[0] : '';
        if ($icon !== '' && function_exists('textWithoutCustomEmoji')) {
            $clean = textWithoutCustomEmoji($msg);
            if ($clean !== '') $text = $clean;
        }
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        chSet($k, function (&$s) use ($i, $text, $icon) {
            if (isset($s['buttons'][$i])) { $s['buttons'][$i]['text'] = $text; $s['buttons'][$i]['icon'] = $icon; }
        });
        return $done();
    }
    if ($action === 'ch_btnurl') {
        if (!$blank && !preg_match('#^https?://#i', $text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک باید با http شروع شود."); return true;
        }
        $url = $blank ? '' : $text;
        chSet($k, function (&$s) use ($i, $url) {
            if (isset($s['buttons'][$i])) $s['buttons'][$i]['url'] = $url;
        });
        $eff = chButtonUrl(chOf($k)['buttons'][$i] ?? []);
        return $done($eff !== ''
            ? "✅ دکمه به این آدرس می‌رود:\n<code>" . h($eff) . '</code>' .
              ($blank ? "\n\n(خودِ ربات — چون لینکی ندادید)" : '')
            : "✅ ذخیره شد.");
    }
    clearState($uid);
    return true;
}

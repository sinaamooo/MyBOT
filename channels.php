<?php
/**
 * 📡 کانال‌های متصل
 *
 * تا حالا گزارش‌ها پراکنده بودند: رسید شارژ فقط برای ادمین می‌رفت و
 * گزارش خرید هم زیر تنظیماتِ تک‌تک محصول‌ها گم بود. اینجا هر جریان
 * مقصد خودش را دارد، جدا از بقیه، با متن و دکمه‌های خودش:
 *
 *   🧾 رسید شارژ حساب   → هرکس کیف پولش را شارژ کرد، رسیدش اینجا می‌افتد
 *   🛒 گزارش خرید       → هر فروشِ ربات و مینی‌اپ
 *   💎 گزارش الماس      → برد و باخت بازی‌ها و انتقال الماس
 *
 * هرکدام می‌تواند یک گروه با تاپیک باشد؛ لینکِ همان تاپیک را بدهید،
 * آیدی و شماره‌ی تاپیک خودش درمی‌آید.
 */

if (!defined('CH_LIB')) define('CH_LIB', 1);

// ============================================================
// ⚙️ پیکربندی
// ============================================================

function chStreams() {
    return [
        'topup' => ['🧾 رسید شارژ حساب', 'وقتی کاربر برای شارژ کیف پول رسید می‌فرستد.'],
        'buy'   => ['🛒 گزارش خرید',      'هر فروشِ ربات و مینی‌اپ‌ها.'],
        'game'  => ['💎 گزارش الماس',     'برنده و بازنده‌ی بازی‌ها و انتقال الماس.'],
    ];
}

function chDefaults() {
    return [
        'topup' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "🧾 <b>رسید شارژ</b>\n\n" .
                      "👤 {user}\n🆔 <code>{uid}</code>\n" .
                      "<blockquote>💰 مبلغ: <b>{amount}</b> تومان\n" .
                      "💳 موجودی بعد از تایید: <b>{balance}</b> تومان</blockquote>\n" .
                      "🧾 <code>{code}</code>\n🕓 {date}",
            'photo'   => true,     // عکس رسید هم فرستاده شود
            'buttons' => [
                ['on' => 1, 'text' => '🤖 ربات', 'url' => '', 'color' => 'primary', 'icon' => ''],
            ],
        ],
        'buy' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "🛒 <b>فروش جدید</b>\n\n" .
                      "👤 {user}\n" .
                      "<blockquote>📦 {product}\n" .
                      "🔢 تعداد: <b>{qty}</b>\n" .
                      "💰 مبلغ: <b>{amount}</b> تومان</blockquote>\n" .
                      "🧾 <code>{code}</code>\n🕓 {date}",
            'photo'   => false,
            'buttons' => [
                ['on' => 1, 'text' => '🛒 ثبت سفارش', 'url' => '', 'color' => 'success', 'icon' => ''],
                ['on' => 1, 'text' => '💬 پشتیبانی',  'url' => '', 'color' => 'primary', 'icon' => ''],
            ],
        ],
        'game' => [
            'on' => false, 'chat_id' => '', 'thread_id' => 0,
            'text' => "💎 <b>{title}</b>\n\n" .
                      "<blockquote>{body}</blockquote>\n🕓 {date}",
            'photo'   => false,
            'buttons' => [
                ['on' => 1, 'text' => '🎮 بازی کن', 'url' => '', 'color' => 'success', 'icon' => ''],
            ],
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
        // فهرست دکمه‌ها عینا همان چیزی که ذخیره شده — نه ادغام عمقی،
        // وگرنه حذف یک دکمه هیچ‌وقت اثر نمی‌کند.
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

function chOf($stream) { return chCfg()[$stream] ?? chDefaults()['buy']; }

function chSet($stream, callable $fn) {
    cfgSet(function (&$c) use ($stream, $fn) {
        if (!isset($c['channels']) || !is_array($c['channels'])) $c['channels'] = [];
        if (!isset($c['channels'][$stream]) || !is_array($c['channels'][$stream]))
            $c['channels'][$stream] = chDefaults()[$stream] ?? [];
        $fn($c['channels'][$stream]);
    });
}

/** آماده‌ی ارسال است؟ */
function chReady($stream) {
    $s = chOf($stream);
    return !empty($s['on']) && trim((string)$s['chat_id']) !== '';
}

// ============================================================
// 📤 فرستادن
// ============================================================

/**
 * یک گزارش را روی کانالِ همان جریان می‌فرستد.
 * $vars جای‌گذاری‌های {…}، $photo شناسه‌ی عکس (رسید) اگر داشت.
 * برگشت false یعنی نرفت — ولی هیچ‌وقت جریانِ اصلی را نمی‌شکند.
 */
function chSend($stream, array $vars, $photo = null) {
    if (!chReady($stream)) return false;
    $s = chOf($stream);

    $vars += ['date' => chDate()];
    $text = chFill((string)$s['text'], $vars);
    if (trim($text) === '') return false;

    $extra = [];
    if ((int)$s['thread_id'] > 0) $extra['message_thread_id'] = (int)$s['thread_id'];

    $kb = chKeyboard($s);

    if ($photo !== null && !empty($s['photo'])) {
        $d = array_merge([
            'chat_id' => $s['chat_id'], 'photo' => $photo,
            'caption' => mb_substr($text, 0, 1000), 'parse_mode' => 'HTML',
        ], $extra);
        if ($kb) $d['reply_markup'] = json_encode($kb);
        $r = tg(BOT_TOKEN, 'sendPhoto', $d);
        if (!empty($r['ok'])) return true;
        // عکس نرفت؟ لااقل متن برود
    }

    $r = sendMsg(BOT_TOKEN, $s['chat_id'], $text, $kb, $extra);
    if (empty($r['ok'])) {
        chWarn($stream, (string)($r['description'] ?? 'بی‌پاسخ'));
        return false;
    }
    return true;
}

/** به ادمین بگو کدام کانال جواب نمی‌دهد — ولی نه هر بار، که آزاردهنده شود */
function chWarn($stream, $why) {
    if (!function_exists('adminAlertOnce')) return;
    [$label] = chStreams()[$stream] ?? [$stream];
    adminAlertOnce('ch_' . $stream,
        "📡 <b>گزارش به کانال نرفت</b>\n\n" . h($label) . "\n<code>" . h(mb_substr($why, 0, 180)) . "</code>\n\n" .
        "پنل ← 📡 کانال‌های متصل — ربات باید در آن گروه ادمین باشد.");
}

function chKeyboard($s) {
    $rows = [];
    $line = [];
    foreach ((array)($s['buttons'] ?? []) as $b) {
        if (empty($b['on'])) continue;
        $url = trim((string)($b['url'] ?? ''));
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

/** نام قابل نمایشِ یک کاربر */
function chUser($uid, $uname = '', $fname = '') {
    $n = trim((string)$fname);
    $u = trim((string)$uname);
    if ($u !== '') return '@' . ltrim($u, '@');
    return $n !== '' ? h($n) : ('<code>' . (int)$uid . '</code>');
}

// ============================================================
// 🔔 قلاب‌ها — جاهایی که گزارش ساخته می‌شود
// ============================================================

/** رسید شارژ کیف پول رسید */
function chTopupReceipt($order) {
    if (!is_array($order)) return;
    $uid = (int)($order['user_id'] ?? 0);
    $u   = function_exists('getUser') ? (getUser($uid) ?: []) : [];
    $amt = (float)($order['amount'] ?? 0);
    chSend('topup', [
        'user'    => chUser($uid, $order['username'] ?? '', $u['name'] ?? ''),
        'uid'     => $uid,
        'amount'  => fmtNum($amt),
        'balance' => fmtNum((float)($u['balance'] ?? 0) + $amt),
        'code'    => (string)($order['id'] ?? ''),
        'receipt' => (string)($order['receipt_type'] ?? '') === 'text' ? (string)$order['receipt'] : 'تصویر',
    ], ($order['receipt_type'] ?? '') === 'photo' ? ($order['receipt'] ?? null) : null);
}

/** یک فروش انجام شد */
function chBuy($uid, $uname, $productName, $qty, $amount, $code, $extra = []) {
    chSend('buy', array_merge([
        'user'    => chUser($uid, $uname, ''),
        'uid'     => (int)$uid,
        'product' => (string)$productName,
        'qty'     => fmtNum((float)$qty),
        'amount'  => fmtNum((float)$amount),
        'code'    => (string)$code,
    ], $extra));
}

/** یک اتفاق در بازی/الماس */
function chGame($title, $body) {
    chSend('game', ['title' => (string)$title, 'body' => (string)$body]);
}

// ============================================================
// 👑 پنل
// ============================================================

function chAdminHome($chatId, $msgId = null) {
    $t  = "📡 <b>کانال‌های متصل</b>\n\n";
    $t .= "هر جریان مقصد خودش را دارد. لینکِ همان <b>تاپیک</b> را بدهید،\n";
    $t .= "آیدی گروه و شماره تاپیک خودشان درمی‌آیند.\n";
    $t .= "⚠️ ربات باید در آن گروه <b>ادمین</b> باشد.\n\n";

    $rows = [];
    foreach (chStreams() as $k => [$label, $desc]) {
        $s = chOf($k);
        $set = trim((string)$s['chat_id']) !== '';
        $t .= (chReady($k) ? '✅' : ($set ? '⏸' : '⚪️')) . ' <b>' . h($label) . "</b>\n";
        $t .= '   ' . ($set
                ? '<code>' . h((string)$s['chat_id']) . '</code>' .
                  ((int)$s['thread_id'] > 0 ? ' · 🧵 ' . (int)$s['thread_id'] : '')
                : 'تنظیم نشده') . "\n";
        $rows[] = [btnCb($label, 'chs_' . $k, 'admin')];
    }
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $t, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $t, inlineKb($rows));
}

function chAdminStream($chatId, $msgId, $k) {
    $st = chStreams()[$k] ?? null;
    if (!$st) { chAdminHome($chatId, $msgId); return; }
    [$label, $desc] = $st;
    $s = chOf($k);

    $t  = h($label) . "\n\n" . h($desc) . "\n\n";
    $t .= 'وضعیت: ' . (!empty($s['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $t .= 'مقصد: ' . (trim((string)$s['chat_id']) !== ''
            ? '<code>' . h((string)$s['chat_id']) . '</code>' : '— تنظیم نشده') . "\n";
    $t .= 'تاپیک: ' . ((int)$s['thread_id'] > 0 ? (int)$s['thread_id'] : 'بدون تاپیک') . "\n";
    if ($k === 'topup') $t .= 'عکس رسید: ' . (!empty($s['photo']) ? '✅ فرستاده شود' : '❌ فقط متن') . "\n";
    $t .= "\n<b>متن گزارش:</b>\n" . $s['text'] . "\n\n";
    $t .= "جای‌گذاری‌ها: " . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', chVarsOf($k)));

    $rows = [
        [btnCb(!empty($s['on']) ? '✅ روشن' : '❌ خاموش', 'chx_' . $k, 'info'),
         btnCb('🧪 تست', 'cht_' . $k, 'confirm')],
        [btnCb('🔗 گروه و تاپیک', 'chl_' . $k, 'admin')],
        [btnCb('✏️ متن گزارش', 'chm_' . $k, 'admin')],
    ];
    if ($k === 'topup') $rows[] = [btnCb(!empty($s['photo']) ? '🖼 عکس رسید: روشن' : '🖼 عکس رسید: خاموش', 'chp_' . $k, 'info')];
    foreach ((array)$s['buttons'] as $i => $b) {
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
    if ($k === 'topup') return ['user', 'uid', 'amount', 'balance', 'code', 'receipt', 'date'];
    if ($k === 'game')  return ['title', 'body', 'date'];
    return ['user', 'uid', 'product', 'qty', 'amount', 'code', 'date'];
}

/** برگشت true یعنی این callback مال بخش کانال‌ها بود */
function chAdminCallback($data, $chatId, $msgId, $cbId) {
    if (!str_starts_with($data, 'ch')) return false;

    if ($data === 'ch_home') { answerCb(BOT_TOKEN, $cbId); chAdminHome($chatId, $msgId); return true; }

    foreach (['chs_' => 'open', 'chx_' => 'toggle', 'chp_' => 'photo', 'cht_' => 'test'] as $pre => $what) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!isset(chStreams()[$k])) { answerCb(BOT_TOKEN, $cbId); return true; }

        if ($what === 'toggle') {
            chSet($k, function (&$s) { $s['on'] = empty($s['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
        } elseif ($what === 'photo') {
            chSet($k, function (&$s) { $s['photo'] = empty($s['photo']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
        } elseif ($what === 'test') {
            answerCb(BOT_TOKEN, $cbId);
            if (!chReady($k)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ اول گروه را تنظیم و جریان را روشن کنید.");
                return true;
            }
            $ok = chSend($k, chSampleVars($k));
            sendMsg(BOT_TOKEN, $chatId, $ok
                ? "✅ گزارش آزمایشی رفت. اگر در گروه ندیدید، ربات آنجا ادمین نیست."
                : "🔴 نرفت. ربات را در آن گروه ادمین کنید و دوباره امتحان کنید.");
            return true;
        }
        chAdminStream($chatId, $msgId, $k);
        return true;
    }

    // روشن/خاموش کردن یک دکمه
    if (preg_match('/^chbx_([a-z]+)_(\d+)$/', $data, $m)) {
        chSet($m[1], function (&$s) use ($m) {
            $i = (int)$m[2];
            if (isset($s['buttons'][$i])) $s['buttons'][$i]['on'] = empty($s['buttons'][$i]['on']) ? 1 : 0;
        });
        answerCb(BOT_TOKEN, $cbId, '✅');
        chAdminStream($chatId, $msgId, $m[1]);
        return true;
    }

    // ورودی‌های متنی
    $asks = [
        'chl_'  => ['ch_link', "🔗 لینک گروه یا تاپیک را بفرستید.\n\n" .
                               "مثال: <code>https://t.me/c/1234567890/11</code>\n" .
                               "یا آیدی عددی گروه، یا <code>@نام‌کانال</code>.\n\n" .
                               "برای پاک کردن، <code>-</code> بفرستید."],
        'chm_'  => ['ch_text', "✏️ متن گزارش را بفرستید.\n\n" .
                               "ایموجی پرمیوم و نقل‌قول هرچه بگذارید سرِ جایش می‌ماند."],
    ];
    foreach ($asks as $pre => [$act, $ask]) {
        if (!str_starts_with($data, $pre)) continue;
        $k = substr($data, strlen($pre));
        if (!isset(chStreams()[$k])) { answerCb(BOT_TOKEN, $cbId); return true; }
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $act, ['k' => $k]);
        $more = ($act === 'ch_text')
            ? "\n\nجای‌گذاری‌ها: " . implode(' ', array_map(fn($x) => '<code>{' . $x . '}</code>', chVarsOf($k))) .
              "\n\nالان:\n" . chOf($k)['text']
            : '';
        sendMsg(BOT_TOKEN, $chatId, $ask . $more, inlineKb([[btnCb('انصراف', 'chs_' . $k, 'cancel')]]));
        return true;
    }
    if (preg_match('/^chb([tu])_([a-z]+)_(\d+)$/', $data, $m)) {
        $isText = $m[1] === 't';
        answerCb(BOT_TOKEN, $cbId);
        setState(ADMIN_ID, $isText ? 'ch_btntext' : 'ch_btnurl', ['k' => $m[2], 'i' => (int)$m[3]]);
        sendMsg(BOT_TOKEN, $chatId, $isText
            ? "✏️ متن دکمه را بفرستید.\n\n✨ ایموجی پرمیوم را جلوی متن بگذارید — خودش برداشته می‌شود."
            : "🔗 لینک دکمه را بفرستید. برای پاک کردن <code>-</code> بفرستید.",
            inlineKb([[btnCb('انصراف', 'chs_' . $m[2], 'cancel')]]));
        return true;
    }
    return false;
}

function chSampleVars($k) {
    if ($k === 'topup') return ['user' => '@testuser', 'uid' => 123456789, 'amount' => fmtNum(500000),
                                'balance' => fmtNum(750000), 'code' => 'TEST-1234', 'receipt' => 'آزمایشی'];
    if ($k === 'game')  return ['title' => 'نتیجه بازی', 'body' => "برنده: @a\nبازنده: @b\nجایزه: ۱۰۰ الماس"];
    return ['user' => '@testuser', 'uid' => 123456789, 'product' => '⭐️ ۵۰ استارز',
            'qty' => '1', 'amount' => fmtNum(149000), 'code' => 'TEST-1234'];
}

/** برگشت true یعنی این گفتگو مال بخش کانال‌ها بود */
function chStateHandle($action, $msg, $uid, $chatId) {
    if (!str_starts_with((string)$action, 'ch_')) return false;
    if ($uid !== ADMIN_ID) return false;

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
        [$chat, $thread] = parseChatLink($text);
        if ($chat === null) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ از این لینک آیدی درنیامد.\nلینک یک پیام داخل همان گروه را بفرستید.");
            return true;
        }
        // همان‌جا امتحان کن — بهتر از اینکه بعدا بی‌صدا نرود
        $probe = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat]);
        if (empty($probe['ok'])) {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ ربات به این گروه دسترسی ندارد:\n<code>" .
                h((string)($probe['description'] ?? 'بی‌پاسخ')) . "</code>\n\n" .
                "اول ربات را آنجا ادمین کنید، بعد دوباره بفرستید.");
            return true;
        }
        chSet($k, function (&$s) use ($chat, $thread) {
            $s['chat_id'] = $chat; $s['thread_id'] = (int)$thread; $s['on'] = true;
        });
        return $done("✅ وصل شد: <code>" . h($chat) . '</code>' .
                     ($thread > 0 ? " · 🧵 {$thread}" : '') . "\n\nبا دکمه 🧪 تست امتحانش کنید.");
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

    $i = (int)($sd['i'] ?? -1);
    if ($action === 'ch_btntext') {
        if ($text === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی نمی‌شود."); return true; }
        $ids  = function_exists('customEmojiIds') ? customEmojiIds($msg) : [];
        $icon = $ids ? (string)$ids[0] : '';
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
        return $done();
    }
    clearState($uid);
    return true;
}

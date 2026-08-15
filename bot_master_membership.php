<?php
/**
 * 🛍️ ربات فروشگاه + ربات‌های اپلودر عمومی
 *
 * ربات مادر : فروشگاه کامل با منوی دکمه‌ای (کیبورد)، کیف پول، زیرمجموعه‌گیری،
 *             پیگیری سفارش، پشتیبانی ۱۰ روشه، و پنل مدیریت کامل
 * ربات فرعی : اپلودر عمومی — فایل می‌گیرد، لینک می‌سازد؛ کاربر با کلیک روی لینک
 *             اول در کانال‌های اجباری عضو می‌شود، بعد فایل را می‌گیرد و
 *             فایل بعد از N ثانیه خودکار حذف می‌شود
 *
 * Webhook مادر : https://DOMAIN/bot_master_membership.php
 * Webhook فرعی : https://DOMAIN/bot_master_membership.php?bot=<BOT_ID>
 * Cron حذف     : https://DOMAIN/bot_master_membership.php?cron=<CRON_KEY>
 */

// ============================================================
// ⚙️ تنظیمات پایه
// ============================================================

if (!defined('BOT_TOKEN')) define('BOT_TOKEN', '8580931982:AAHQb5vGDnG6n9vFWqBMpPWksRiuyhsWv_g');
if (!defined('ADMIN_ID'))  define('ADMIN_ID',  8213021584);
if (!defined('DATA_DIR'))  define('DATA_DIR',  __DIR__ . '/data_master');
if (!defined('CRON_KEY'))  define('CRON_KEY',  'change-this-cron-key');

if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);

@ignore_user_abort(true);

// ============================================================
// 📚 ذخیره‌سازی اتمیک
// ============================================================

function dataPath($file) { return DATA_DIR . '/' . $file . '.json'; }

function load($file) {
    $path = dataPath($file);
    if (!file_exists($path)) return [];
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') return [];
    $out = json_decode($raw, true);
    return is_array($out) ? $out : [];
}

function save($file, $data) {
    $path = dataPath($file);
    $dir  = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $tmp  = $path . '.' . getmypid() . '.tmp';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return rename($tmp, $path);
}

/** تغییر با قفل انحصاری تا درخواست‌های همزمان همدیگر را پاک نکنند */
function mutate($file, callable $fn) {
    $lockPath = dataPath($file) . '.lock';
    $dir = dirname($lockPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = fopen($lockPath, 'c');
    if ($fp) flock($fp, LOCK_EX);
    $data   = load($file);
    $result = $fn($data);
    save($file, $data);
    if ($fp) { flock($fp, LOCK_UN); fclose($fp); }
    return $result;
}

function uid($p) { return $p . '_' . base_convert((string)time(), 10, 36) . bin2hex(random_bytes(3)); }
function genCode($len = 10) {
    return substr(rtrim(strtr(base64_encode(random_bytes(16)), '+/', 'ab'), '='), 0, $len);
}
function h($s)      { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function nowStr()   { return date('Y-m-d H:i:s'); }
function fmtNum($n) { return rtrim(rtrim(number_format((float)$n, 2, '.', ','), '0'), '.'); }

// ============================================================
// 🔌 تلگرام API
// ============================================================

function tg($token, $method, $data = [], $timeout = 20) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => max(3, (int)$timeout),
        CURLOPT_CONNECTTIMEOUT => min(10, max(3, (int)$timeout)),
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'curl error'];
    $out = json_decode($res, true);
    return is_array($out) ? $out : ['ok' => false, 'description' => 'bad response'];
}

/** کیبورد شیشه‌ای (زیر پیام) */
/** فیلدهای null را حذف می‌کند تا تلگرام style خالی نبیند */
function cleanRows($rows) {
    $out = [];
    foreach ($rows as $row) {
        if (empty($row)) continue;
        $line = [];
        foreach ($row as $btn) {
            if (!is_array($btn) || empty($btn['text'])) continue;
            $line[] = array_filter($btn, fn($v) => $v !== null && $v !== '');
        }
        if ($line) $out[] = $line;
    }
    return $out;
}

function inlineKb($rows) {
    $rows = cleanRows($rows);
    return $rows ? ['inline_keyboard' => $rows] : null;
}

/** منوی دکمه‌ای (پایین صفحه، کنار کادر تایپ) */
/**
 * منوی کیبورد.
 * is_persistent=true به کلاینت می‌گوید کیبورد را همیشه نشان بده و
 * دکمه بستن را از کاربر می‌گیرد — یعنی کاربر گیر می‌کند.
 * پس پیش‌فرض خاموش است تا کاربر بتواند کیبورد را ببندد.
 */
function menuKb($rows) {
    $rows = cleanRows($rows);
    if (!$rows) return ['remove_keyboard' => true];
    $kb = [
        'keyboard' => $rows,
        'resize_keyboard' => true,
        'input_field_placeholder' => cfg()['ui']['placeholder'] ?? '',
    ];
    if (!empty(cfg()['ui']['persistent'])) $kb['is_persistent'] = true;
    if ($kb['input_field_placeholder'] === '') unset($kb['input_field_placeholder']);
    return $kb;
}

function removeKb() { return ['remove_keyboard' => true]; }

/** خطای مربوط به style؟ (سرور Bot API قدیمی‌تر از 9.4) */
function isStyleError($res) {
    $d = strtolower($res['description'] ?? '');
    return $d !== '' && (str_contains($d, 'style') || str_contains($d, 'icon_custom_emoji_id'));
}

function sendMsg($token, $chatId, $text, $markup = null, $extra = []) {
    $data = array_merge([
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => 'true',
    ], $extra);
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    $res = tg($token, 'sendMessage', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'sendMessage', $data);
    }
    return $res;
}

function editMsg($token, $chatId, $msgId, $text, $markup = null) {
    $data = [
        'chat_id' => $chatId, 'message_id' => $msgId, 'text' => $text,
        'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
    ];
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    $res = tg($token, 'editMessageText', $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, 'editMessageText', $data);
    }
    if (empty($res['ok'])) sendMsg($token, $chatId, $text, $markup);
    return $res;
}

function answerCb($token, $cbId, $text = '', $alert = false) {
    return tg($token, 'answerCallbackQuery', [
        'callback_query_id' => $cbId, 'text' => $text,
        'show_alert' => $alert ? 'true' : 'false',
    ]);
}

function delMsg($token, $chatId, $msgId) {
    return tg($token, 'deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId]);
}

function sendFile($token, $chatId, $type, $fileId, $caption = '', $protect = false) {
    $map = [
        'document' => ['sendDocument', 'document'], 'photo' => ['sendPhoto', 'photo'],
        'video' => ['sendVideo', 'video'],          'audio' => ['sendAudio', 'audio'],
        'voice' => ['sendVoice', 'voice'],          'animation' => ['sendAnimation', 'animation'],
        'sticker' => ['sendSticker', 'sticker'],    'video_note' => ['sendVideoNote', 'video_note'],
    ];
    [$method, $field] = $map[$type] ?? $map['document'];
    $data = ['chat_id' => $chatId, $field => $fileId];
    if ($caption !== '' && !in_array($type, ['sticker', 'video_note'], true)) {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    if ($protect) $data['protect_content'] = 'true';
    return tg($token, $method, $data);
}

/**
 * تبدیل entityهای تلگرام به HTML — تا وقتی ادمین متن را داخل ربات می‌فرستد،
 * ایموجی پریمیوم، پررنگ، نقل‌قول و لینک‌ها حفظ شوند.
 * آفست‌های تلگرام بر حسب کد-یونیت UTF-16 است، پس در همان فضا کار می‌کنیم.
 */
function entitiesToHtml($text, $entities = null) {
    if ($text === null || $text === '') return '';
    if (empty($entities)) return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');

    $u16 = mb_convert_encoding($text, 'UTF-16LE', 'UTF-8');
    $len = (int)(strlen($u16) / 2);

    $open = array_fill(0, $len + 1, []);
    $close = array_fill(0, $len + 1, []);

    // entityهای بلندتر باید بیرونی‌تر بسته‌بندی شوند
    usort($entities, function ($a, $b) {
        if ($a['offset'] !== $b['offset']) return $a['offset'] <=> $b['offset'];
        return $b['length'] <=> $a['length'];
    });

    foreach ($entities as $e) {
        $o = (int)$e['offset'];
        $l = (int)$e['length'];
        if ($o < 0 || $l <= 0 || $o + $l > $len) continue;

        $ot = null; $ct = null;
        switch ($e['type']) {
            case 'bold':          $ot = '<b>';  $ct = '</b>';  break;
            case 'italic':        $ot = '<i>';  $ct = '</i>';  break;
            case 'underline':     $ot = '<u>';  $ct = '</u>';  break;
            case 'strikethrough': $ot = '<s>';  $ct = '</s>';  break;
            case 'spoiler':       $ot = '<tg-spoiler>'; $ct = '</tg-spoiler>'; break;
            case 'code':          $ot = '<code>'; $ct = '</code>'; break;
            case 'pre':
                $lang = !empty($e['language']) ? ' class="language-' . htmlspecialchars($e['language'], ENT_QUOTES, 'UTF-8') . '"' : '';
                $ot = '<pre><code' . $lang . '>'; $ct = '</code></pre>'; break;
            case 'blockquote':            $ot = '<blockquote>'; $ct = '</blockquote>'; break;
            case 'expandable_blockquote': $ot = '<blockquote expandable>'; $ct = '</blockquote>'; break;
            case 'text_link':
                if (empty($e['url'])) break;
                $ot = '<a href="' . htmlspecialchars($e['url'], ENT_QUOTES, 'UTF-8') . '">'; $ct = '</a>'; break;
            case 'text_mention':
                if (empty($e['user']['id'])) break;
                $ot = '<a href="tg://user?id=' . (int)$e['user']['id'] . '">'; $ct = '</a>'; break;
            case 'custom_emoji':
                if (empty($e['custom_emoji_id'])) break;
                $ot = '<tg-emoji emoji-id="' . htmlspecialchars($e['custom_emoji_id'], ENT_QUOTES, 'UTF-8') . '">';
                $ct = '</tg-emoji>'; break;
        }
        if ($ot === null) continue;
        $open[$o][]  = $ot;
        array_unshift($close[$o + $l], $ct);   // بستن به ترتیب معکوس
    }

    $out = '';
    for ($i = 0; $i < $len; $i++) {
        foreach ($close[$i] as $t) $out .= $t;
        foreach ($open[$i]  as $t) $out .= $t;
        $ch = mb_convert_encoding(substr($u16, $i * 2, 2), 'UTF-8', 'UTF-16LE');
        // نیمه بالای جفت جانشین را با نیمه بعدی بچسبان
        $cp = unpack('v', substr($u16, $i * 2, 2))[1];
        if ($cp >= 0xD800 && $cp <= 0xDBFF && $i + 1 < $len) {
            // جفت جانشین یک کاراکتر است؛ تلگرام هرگز entity را وسط آن نمی‌شکند
            $ch = mb_convert_encoding(substr($u16, $i * 2, 4), 'UTF-8', 'UTF-16LE');
            $i++;
        }
        $out .= htmlspecialchars($ch, ENT_NOQUOTES, 'UTF-8');
    }
    foreach ($close[$len] as $t) $out .= $t;
    return $out;
}

/** متن پیام ادمین را با حفظ قالب‌بندی و ایموجی پریمیوم برمی‌گرداند */
function msgHtml($msg) {
    $t = $msg['text'] ?? $msg['caption'] ?? '';
    $e = $msg['entities'] ?? $msg['caption_entities'] ?? null;
    return entitiesToHtml($t, $e);
}

/** شناسه‌های ایموجی پریمیوم داخل یک پیام */
function customEmojiIds($msg) {
    $out = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $e) {
        if (($e['type'] ?? '') === 'custom_emoji' && !empty($e['custom_emoji_id'])) {
            $out[$e['custom_emoji_id']] = true;
        }
    }
    return array_keys($out);
}

function extractFile($msg) {
    if (!empty($msg['document']))   return ['document', $msg['document']['file_id'], $msg['document']['file_name'] ?? 'file'];
    if (!empty($msg['video']))      return ['video', $msg['video']['file_id'], $msg['video']['file_name'] ?? 'video'];
    if (!empty($msg['audio']))      return ['audio', $msg['audio']['file_id'], $msg['audio']['title'] ?? 'audio'];
    if (!empty($msg['voice']))      return ['voice', $msg['voice']['file_id'], 'voice'];
    if (!empty($msg['animation']))  return ['animation', $msg['animation']['file_id'], 'animation'];
    if (!empty($msg['video_note'])) return ['video_note', $msg['video_note']['file_id'], 'video_note'];
    if (!empty($msg['sticker']))    return ['sticker', $msg['sticker']['file_id'], 'sticker'];
    if (!empty($msg['photo']))      { $p = $msg['photo']; return ['photo', $p[count($p) - 1]['file_id'], 'photo']; }
    return null;
}

// ============================================================
// 🎨 پیکربندی — رنگ‌ها، دکمه‌ها، متن‌ها، پشتیبانی
// ============================================================

/**
 * رنگ واقعی دکمه‌ها — Bot API 9.4 (۹ فوریه ۲۰۲۶) فیلد style را به
 * KeyboardButton و InlineKeyboardButton اضافه کرد:
 *   style: "danger" (قرمز) | "success" (سبز) | "primary" (آبی)
 * روی هر دو نوع دکمه — منو و شیشه‌ای — کار می‌کند.
 */
function styleMap() {
    return [
        'none'    => '— بدون رنگ',
        'primary' => '🔵 آبی',
        'success' => '🟢 سبز',
        'danger'  => '🔴 قرمز',
    ];
}
function isStyle($s) { return in_array($s, ['primary', 'success', 'danger'], true); }

/** دایره تزئینی — برای کلاینت‌های قدیمی که هنوز style را نشان نمی‌دهند */
function dotMap() {
    return ['', '🔵', '🟢', '🔴', '🟡', '🟣', '🟠', '⚪️', '⚫️', '🟤'];
}

/** نقش دکمه شیشه‌ای → رنگ (همه از پنل قابل تنظیم) */
function gs($role) {
    $c = cfg()['glass_colors'][$role] ?? 'none';
    return isStyle($c) ? $c : null;
}

function btnCb($label, $data, $role = null, $style = null) {
    $b = ['text' => $label, 'callback_data' => $data];
    $st = $style ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}
function btnUrl($label, $url, $role = null, $style = null) {
    $b = ['text' => $label, 'url' => $url];
    $st = $style ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    return $b;
}

/** حذف style — اگر سرور Bot API قدیمی باشد دوباره بدون رنگ می‌فرستیم */
function stripStyles($markup) {
    if (!is_array($markup)) return $markup;
    foreach (['inline_keyboard', 'keyboard'] as $k) {
        if (empty($markup[$k])) continue;
        foreach ($markup[$k] as $i => $row)
            foreach ($row as $j => $btn)
                unset($markup[$k][$i][$j]['style'], $markup[$k][$i][$j]['icon_custom_emoji_id']);
    }
    return $markup;
}

function defaultConfig() {
    return [
        // mode: menu = کیبورد پایین | glass = دکمه شیشه‌ای زیر پیام
        // layout: چیدمان دلخواه — مثلا "1,2,1,2,1" یعنی ردیف اول ۱ دکمه، دوم ۲ تا، ...
        'ui' => ['mode' => 'menu', 'layout' => '1,2,1,2,1', 'product_layout' => '1',
                 'show_dot' => false, 'persistent' => false,
                 'placeholder' => 'یک گزینه را انتخاب کنید…'],

        'buttons' => [
            'buy'      => ['emoji' => '🛒', 'text' => 'خرید محصول',                     'color' => 'success', 'dot' => '🟢', 'icon' => '', 'row' => 1, 'order' => 1, 'on' => true, 'action' => ''],
            'account'  => ['emoji' => '👤', 'text' => 'حساب کاربری',                    'color' => 'primary', 'dot' => '🔵', 'icon' => '', 'row' => 2, 'order' => 2, 'on' => true, 'action' => ''],
            'topup'    => ['emoji' => '➕', 'text' => 'افزایش موجودی',                  'color' => 'primary', 'dot' => '🔵', 'icon' => '', 'row' => 2, 'order' => 3, 'on' => true, 'action' => ''],
            'referral' => ['emoji' => '👥', 'text' => 'زیر مجموعه گیری',                'color' => 'danger',  'dot' => '🔴', 'icon' => '', 'row' => 3, 'order' => 4, 'on' => true, 'action' => ''],
            'orders'   => ['emoji' => '📊', 'text' => 'پیگیری سفارش',                   'color' => 'primary', 'dot' => '🔵', 'icon' => '', 'row' => 4, 'order' => 5, 'on' => true, 'action' => ''],
            'support'  => ['emoji' => '📞', 'text' => 'پشتیبانی',                       'color' => 'primary', 'dot' => '🔵', 'icon' => '', 'row' => 4, 'order' => 6, 'on' => true, 'action' => ''],
            'trust'    => ['emoji' => '💚', 'text' => 'چطوری میتوانم به شما اعتماد کنم', 'color' => 'danger',  'dot' => '🔴', 'icon' => '', 'row' => 5, 'order' => 7, 'on' => true, 'action' => ''],
        ],

        // متن دکمه‌های ثابت ربات — همه از داخل ربات قابل ویرایش
        'ui_texts' => [
            'back'      => '◀️ بازگشت',
            'home'      => '🏠 منوی اصلی',
            'cancel'    => '🔴 انصراف',
            'confirm'   => '🟢 تایید',
            'reject'    => '🔴 رد',
            'panel'     => '👑 پنل',
            'buy'       => '🟢 خرید',
            'redeliver' => '📦 دریافت مجدد',
            'full'      => '🔴 تکمیل ظرفیت',
            'receipt'   => '🟢 ارسال رسید',
            'wallet_pay'=> '💰 پرداخت از کیف پول',
            'direct_pay'=> '💳 پرداخت مستقیم',
            'topup'     => '➕ افزایش موجودی',
            'my_orders' => '📊 سفارش‌های من',
            'enter_bot' => '🚀 ورود به ربات',
            'get_link'  => '🔗 دریافت لینک',
            'open'      => '🔗 باز کردن',
            'hide_menu' => '❌ بستن منو',
        ],

        // رنگ همه دکمه‌های شیشه‌ای بر اساس نقششان
        'glass_colors' => [
            'buy'     => 'success',  // خرید / دریافت
            'confirm' => 'success',  // تایید
            'cancel'  => 'danger',   // انصراف
            'reject'  => 'danger',   // رد / حذف
            'nav'     => 'primary',  // بازگشت / منو
            'info'    => 'primary',  // اطلاعات / آمار
            'admin'   => 'primary',  // پنل مدیریت
            'link'    => 'success',  // لینک دریافت محتوا
            'support' => 'primary',  // دکمه‌های پشتیبانی
            'join'    => 'primary',  // کانال‌های عضویت اجباری
            'joined'  => 'success',  // «عضو شدم»
            'upload'  => 'success',  // آپلود
        ],

        // اعلام فروش در یک کانال جدا
        'sales' => [
            'on'       => false,
            'chat_id'  => '',
            'template' => "🎉 <b>فروش جدید</b>\n\n📦 محصول: {product}\n🧾 کد خرید: <code>{code}</code>\n💰 مبلغ: <b>{amount} {currency}</b>\n👥 تعداد ممبر: <b>{count}</b>{limit_part}\n📅 {date}",
            'show_user' => false,
        ],

        'texts' => [
            'welcome'      => "👋 سلام {name} عزیز\nبه فروشگاه ما خوش آمدید.\n\nاز منوی پایین یکی از گزینه‌ها را انتخاب کنید.",
            'account'      => "👤 <b>حساب کاربری</b>\n\n🆔 آیدی: <code>{id}</code>\n👤 نام: {name}\n📛 یوزرنیم: {username}\n\n💰 موجودی: <b>{balance}</b> تومان\n🛒 خریدها: {orders}\n👥 زیرمجموعه: {referrals}\n💵 درآمد معرفی: {ref_earned} تومان\n\n📅 عضویت: {joined}",
            'trust'        => "💚 <b>چرا می‌توانید به ما اعتماد کنید؟</b>\n\n✅ سال‌ها سابقه فعالیت\n✅ تحویل آنی و خودکار\n✅ پشتیبانی ۲۴ ساعته\n✅ ضمانت بازگشت وجه\n✅ هزاران مشتری راضی\n\nبرای مشاهده نظرات مشتریان به کانال ما مراجعه کنید.",
            'support'      => "📞 <b>پشتیبانی</b>\n\nاز روش‌های زیر می‌توانید با ما در ارتباط باشید:",
            'orders_empty' => "📊 هنوز سفارشی ثبت نکرده‌اید.",
            'orders_head'  => "📊 <b>سفارش‌های شما</b>\n",
            'referral'     => "👥 <b>زیر مجموعه گیری</b>\n\nبا دعوت دوستان خود <b>{percent}%</b> از هر خرید آن‌ها را دریافت کنید.\n\n🔗 لینک اختصاصی شما:\n{link}\n\n👥 تعداد زیرمجموعه: <b>{referrals}</b>\n💵 درآمد شما: <b>{ref_earned}</b> تومان",
            'topup'        => "➕ <b>افزایش موجودی</b>\n\nمبلغ مورد نظر را به تومان وارد کنید (فقط عدد):",
            'buy_empty'    => "🛒 در حال حاضر محصولی برای فروش موجود نیست.",
            'buy_head'     => "🛒 <b>محصولات</b>\n\nیکی از محصولات زیر را انتخاب کنید:\n",
            'pay_info'     => "💳 <b>اطلاعات پرداخت</b>\n\n{title}\nمبلغ: <b>{amount} {currency}</b>\nروش: {method}\n\n💠 مقصد پرداخت:\n<code>{wallet}</code>\n\n🧾 شناسه سفارش: <code>{id}</code>\n\n⚠️ بعد از واریز، دکمه «ارسال رسید» را بزنید.",
            'receipt_ask'  => "🧾 لطفا رسید پرداخت را بفرستید.\n\nمی‌توانید <b>عکس رسید</b> یا <b>کد تراکنش</b> ارسال کنید.",
            'receipt_ok'   => "✅ رسید شما ثبت شد.\n\n⏳ پس از تایید ادمین اطلاع داده می‌شود.",
            'approved'     => "✅ <b>سفارش شما تایید شد!</b>",
            'rejected'     => "❌ سفارش شما تایید نشد.\nدر صورت نیاز با پشتیبانی تماس بگیرید.",
            'no_balance'   => "❌ موجودی شما کافی نیست.\nموجودی فعلی: {balance} تومان",
            'banned'       => "🚫 دسترسی شما مسدود شده است.",
            'quote_hint'   => "",
        ],

        // ۱۰ روش پشتیبانی — مستقیم و غیرمستقیم
        'support_methods' => [
            ['on' => true,  'kind' => 'direct',   'type' => 'url',    'emoji' => '💬', 'label' => 'چت مستقیم با پشتیبان', 'value' => ''],
            ['on' => true,  'kind' => 'direct',   'type' => 'ticket', 'emoji' => '🎫', 'label' => 'ارسال تیکت در ربات',   'value' => ''],
            ['on' => true,  'kind' => 'direct',   'type' => 'phone',  'emoji' => '☎️', 'label' => 'تماس تلفنی',            'value' => ''],
            ['on' => true,  'kind' => 'direct',   'type' => 'url',    'emoji' => '📱', 'label' => 'واتساپ',                'value' => ''],
            ['on' => false, 'kind' => 'direct',   'type' => 'url',    'emoji' => '✈️', 'label' => 'ایتا',                  'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'url',    'emoji' => '📢', 'label' => 'کانال اطلاع‌رسانی',     'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'url',    'emoji' => '👥', 'label' => 'گروه گفتگو',            'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'text',   'emoji' => '❓', 'label' => 'سوالات متداول',         'value' => "❓ <b>سوالات متداول</b>\n\nهنوز متنی تنظیم نشده است."],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '🌐', 'label' => 'وب‌سایت',               'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'text',   'emoji' => '📧', 'label' => 'ایمیل',                 'value' => ''],
        ],

        'referral' => ['on' => true, 'percent' => 10],

        'wallets' => [
            'usdt' => '', 'trx' => '', 'card' => '', 'card_name' => '',
        ],

        // پیش‌فرض ربات‌های اپلودر — روی هر ربات جدید اعمال می‌شود
        'uploader' => [
            'delete_seconds'  => 30,
            'protect_content' => true,
            'force_join'      => true,
            'inline_wait'     => true,
            'start_text'      => "👋 سلام {name}\n\nاین ربات فایل‌های ما را برای شما ارسال می‌کند.\nبرای دریافت فایل روی لینکی که دریافت کرده‌اید کلیک کنید.",
            'join_text'       => "🔒 برای دریافت فایل، ابتدا در کانال‌های زیر عضو شوید:",
            'joined_btn'      => "✅ عضو شدم",
            'warn_text'       => "⚠️ این فایل تا <b>{sec} ثانیه</b> دیگر حذف می‌شود.\nلطفا آن را ذخیره یا فوروارد کنید.",
            'deleted_text'    => "🗑 فایل حذف شد.\nبرای دریافت دوباره، روی لینک کلیک کنید.",
            'expired_text'    => "❌ این لینک معتبر نیست یا حذف شده است.",
            'menu_text'       => "🤖 <b>پنل اپلودر</b>\n\n🔗 لینک‌ها: {links}\n🗑 حذف خودکار: {sec} ثانیه\n🔒 عضویت اجباری: {join}\n\nفایل بفرستید تا لینک بسازم.",
            'buttons' => [
                'single' => ['emoji' => '📤', 'text' => 'آپلود تکی',   'color' => 'success', 'icon' => '', 'row' => 1, 'order' => 1, 'on' => true],
                'batch'  => ['emoji' => '📦', 'text' => 'آپلود گروهی', 'color' => 'success', 'icon' => '', 'row' => 1, 'order' => 2, 'on' => true],
                'links'  => ['emoji' => '🔗', 'text' => 'لینک‌های من',  'color' => 'primary', 'icon' => '', 'row' => 2, 'order' => 3, 'on' => true],
                'stats'  => ['emoji' => '📊', 'text' => 'آمار',        'color' => 'primary', 'icon' => '', 'row' => 2, 'order' => 4, 'on' => true],
            ],
            'glass_colors' => [
                'join' => 'primary', 'joined' => 'success', 'nav' => 'primary',
                'cancel' => 'danger', 'upload' => 'success', 'info' => 'primary',
            ],
        ],
    ];
}

function cfg($refresh = false) {
    static $c = null;
    if ($c === null || $refresh) {
        $saved = load('config');
        $c = array_replace_recursive(defaultConfig(), is_array($saved) ? $saved : []);
        // این دو باید جایگزین شوند نه ادغام عمقی
        if (!empty($saved['support_methods'])) $c['support_methods'] = $saved['support_methods'];
        if (!empty($saved['buttons']))         $c['buttons'] = array_replace_recursive(defaultConfig()['buttons'], $saved['buttons']);
    }
    return $c;
}

function cfgSet(callable $fn) {
    mutate('config', function (&$c) use ($fn) {
        if (!is_array($c) || !$c) $c = defaultConfig();
        $fn($c);
    });
    cfg(true);   // کش را تازه کن وگرنه ادامه همین درخواست مقدار قدیمی را می‌بیند
}

/** متن دکمه‌های ثابت */
function UT($key) {
    $d = defaultConfig()['ui_texts'];
    $v = cfg()['ui_texts'][$key] ?? ($d[$key] ?? $key);
    return $v !== '' ? $v : ($d[$key] ?? $key);
}

function T($key, $vars = []) {
    $t = cfg()['texts'][$key] ?? '';
    foreach ($vars as $k => $v) $t = str_replace('{' . $k . '}', (string)$v, $t);
    return $t;
}

/** متن روی دکمه — رنگ واقعی جدا از متن اعمال می‌شود */
function btnLabel($b, $withDot = null) {
    $dot = ($withDot === null) ? !empty(cfg()['ui']['show_dot']) : $withDot;
    $out = trim(($b['emoji'] ?? '') . ' ' . ($b['text'] ?? ''));
    if ($dot && !empty($b['dot'])) $out = trim($b['dot'] . ' ' . $out);
    return $out;
}

/** دکمه‌های فعال، مرتب‌شده */
function activeButtons() {
    $list = [];
    foreach (cfg()['buttons'] as $id => $b) {
        if (empty($b['on'])) continue;
        $b['id'] = $id;
        $list[] = $b;
    }
    usort($list, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));
    return $list;
}

/** "3,2,1" → [3,2,1] — ارقام نامعتبر نادیده گرفته می‌شوند (سقف ۸ = محدودیت خود تلگرام) */
function parseLayout($str) {
    $out = [];
    foreach (preg_split('/[^0-9]+/', (string)$str) as $n) {
        if ($n === '') continue;
        $n = (int)$n;
        if ($n >= 1 && $n <= 8) $out[] = $n;
    }
    return $out;
}

/**
 * چیدمان دقیقا همان چیزی است که نوشته‌اید: «3,2,1» یعنی ۳ تا بالا، ۲ تا وسط، ۱ تا آخر.
 * اگر دکمه بیشتری از الگو بماند، هرکدام در ردیف خودش می‌آید (بدون تکرار الگو).
 */
function layoutRows(array $items, $layoutStr) {
    $layout = parseLayout($layoutStr);
    $rows = [];
    $i = 0; $n = count($items); $k = 0;
    while ($i < $n) {
        $take = ($k < count($layout)) ? $layout[$k] : 1;
        $rows[] = array_slice($items, $i, $take);
        $i += $take; $k++;
    }
    return $rows;
}

/** الگوی چیدمان را به شماره ردیف هر دکمه تبدیل می‌کند */
function applyLayoutToRows($layoutStr) {
    $items = activeButtons();
    $rows = layoutRows($items, $layoutStr);
    $map = [];
    foreach ($rows as $r => $line) foreach ($line as $b) $map[$b['id']] = $r + 1;
    return $map;
}

/** ردیف‌بندی نهایی: اگر دکمه‌ها شماره ردیف دارند از آن، وگرنه از الگو */
function menuRows() {
    $items = activeButtons();
    $hasRows = false;
    foreach ($items as $b) if (!empty($b['row'])) { $hasRows = true; break; }
    if (!$hasRows) return layoutRows($items, cfg()['ui']['layout'] ?? '');

    $byRow = [];
    foreach ($items as $b) $byRow[(int)($b['row'] ?: 99)][] = $b;
    ksort($byRow);
    return array_values($byRow);
}

/** منوی اصلی — منو یا شیشه‌ای، با رنگ واقعی تلگرام */
function mainKeyboard() {
    $c = cfg();
    $glass = ($c['ui']['mode'] === 'glass');
    $rows = menuRows();

    $out = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($r as $b) {
            $btn = ['text' => btnLabel($b)];
            if ($glass) $btn['callback_data'] = 'menu_' . $b['id'];
            if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = $b['icon'];
            $line[] = $btn;
        }
        if ($line) $out[] = $line;
    }
    return $glass ? inlineKb($out) : menuKb($out);
}

/** تشخیص اینکه کاربر کدام دکمه منو را زده (با یا بدون ایموجی/دایره) */
function findMenuAction($text) {
    $text = trim($text);
    if ($text === '') return null;
    foreach (cfg()['buttons'] as $id => $b) {
        if (empty($b['on'])) continue;
        if (btnLabel($b, true) === $text)  return $id;
        if (btnLabel($b, false) === $text) return $id;
        if (trim($b['text']) === $text)    return $id;
    }
    return null;
}

// ============================================================
// 👤 کاربران، کیف پول، زیرمجموعه
// ============================================================

function getUser($id) {
    $u = load('users');
    return $u[(string)$id] ?? null;
}

function touchUser($id, $username = '', $firstName = '', $referrer = null) {
    return mutate('users', function (&$users) use ($id, $username, $firstName, $referrer) {
        $k = (string)$id;
        $isNew = !isset($users[$k]);
        $users[$k] = array_merge([
            'telegram_id' => (int)$id,
            'balance'     => 0,
            'referrer'    => null,
            'ref_earned'  => 0,
            'banned'      => false,
            'joined_at'   => nowStr(),
        ], $users[$k] ?? [], [
            'username'   => $username,
            'first_name' => $firstName,
            'seen_at'    => nowStr(),
        ]);
        // معرف فقط یک بار و فقط برای کاربر جدید ثبت می‌شود
        if ($isNew && $referrer && (int)$referrer !== (int)$id && isset($users[(string)$referrer])) {
            $users[$k]['referrer'] = (int)$referrer;
        }
        return $users[$k];
    });
}

function addBalance($userId, $amount) {
    mutate('users', function (&$users) use ($userId, $amount) {
        $k = (string)$userId;
        if (!isset($users[$k])) return;
        $users[$k]['balance'] = round((float)$users[$k]['balance'] + (float)$amount, 2);
    });
}

function countReferrals($userId) {
    $n = 0;
    foreach (load('users') as $u) if ((int)($u['referrer'] ?? 0) === (int)$userId) $n++;
    return $n;
}

function payReferralCommission($buyerId, $amount) {
    $u = getUser($buyerId);
    if (!$u || empty($u['referrer'])) return;
    $c = cfg()['referral'];
    if (empty($c['on'])) return;
    $commission = round((float)$amount * ((float)$c['percent'] / 100), 2);
    if ($commission <= 0) return;
    addBalance($u['referrer'], $commission);
    mutate('users', function (&$users) use ($u, $commission) {
        $k = (string)$u['referrer'];
        if (isset($users[$k])) $users[$k]['ref_earned'] = round((float)($users[$k]['ref_earned'] ?? 0) + $commission, 2);
    });
    sendMsg(BOT_TOKEN, $u['referrer'],
        "🎉 یکی از زیرمجموعه‌های شما خرید کرد!\n💵 پورسانت شما: <b>" . fmtNum($commission) . "</b> تومان");
}

// ============================================================
// 🧠 وضعیت گفتگو
// ============================================================

function getState($uid) { $s = load('states'); return $s[(string)$uid] ?? null; }
function setState($uid, $action, $data = []) {
    mutate('states', function (&$s) use ($uid, $action, $data) {
        $s[(string)$uid] = ['action' => $action, 'data' => $data, 'at' => nowStr()];
    });
}
function clearState($uid) { mutate('states', function (&$s) use ($uid) { unset($s[(string)$uid]); }); }

// ============================================================
// 🛒 محصولات
// ============================================================

class Product
{
    public static function all() { return load('products'); }

    public static function get($id) { $a = load('products'); return $a[$id] ?? null; }

    public static function create($name, $price, $currency, $limit = 0, $desc = '', $botId = null) {
        $id = uid('pr');
        mutate('products', function (&$a) use ($id, $name, $price, $currency, $limit, $desc, $botId) {
            $a[$id] = [
                'id' => $id, 'name' => $name, 'desc' => $desc,
                'price' => (float)$price, 'currency' => $currency,
                'limit' => (int)$limit, 'buyers' => [],
                'bot_id' => $botId, 'link_code' => '',
                'emoji' => '💠', 'color' => 'success', 'icon' => '',
                'row' => 0, 'order' => 99,
                'active' => true, 'created_at' => nowStr(),
            ];
        });
        return self::get($id);
    }

    public static function isFull($p) {
        return ((int)$p['limit']) > 0 && count($p['buyers']) >= (int)$p['limit'];
    }

    public static function hasBought($pid, $uid) {
        $p = self::get($pid);
        return $p && in_array((int)$uid, array_map('intval', $p['buyers']), true);
    }

    public static function addBuyer($pid, $uid) {
        mutate('products', function (&$a) use ($pid, $uid) {
            if (!isset($a[$pid])) return;
            $b = array_map('intval', $a[$pid]['buyers']);
            if (!in_array((int)$uid, $b, true)) { $b[] = (int)$uid; $a[$pid]['buyers'] = array_values($b); }
        });
    }
}

// ============================================================
// 🧾 سفارش‌ها (خرید محصول + شارژ کیف پول)
// ============================================================

class Order
{
    const PENDING = 'pending', REVIEW = 'review', APPROVED = 'approved', REJECTED = 'rejected';

    public static function all() { return load('orders'); }
    public static function get($id) { $a = load('orders'); return $a[$id] ?? null; }

    public static function create($userId, $username, $type, $productId, $amount, $currency) {
        $id = uid('or');
        mutate('orders', function (&$a) use ($id, $userId, $username, $type, $productId, $amount, $currency) {
            $a[$id] = [
                'id' => $id, 'user_id' => (int)$userId, 'username' => $username,
                'type' => $type,                 // product | topup
                'product_id' => $productId,
                'amount' => (float)$amount, 'currency' => $currency,
                'status' => self::PENDING,
                'receipt_type' => null, 'receipt' => null,
                'created_at' => nowStr(), 'decided_at' => null, 'decided_by' => null,
            ];
        });
        return $id;
    }

    public static function attachReceipt($id, $type, $value) {
        return mutate('orders', function (&$a) use ($id, $type, $value) {
            if (!isset($a[$id]) || $a[$id]['status'] !== self::PENDING) return false;
            $a[$id]['receipt_type'] = $type;
            $a[$id]['receipt'] = $value;
            $a[$id]['status'] = self::REVIEW;
            return true;
        });
    }

    public static function approve($id, $adminId) {
        $r = mutate('orders', function (&$a) use ($id, $adminId) {
            if (!isset($a[$id])) return 'notfound';
            if (in_array($a[$id]['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $a[$id]['status'] = self::APPROVED;
            $a[$id]['decided_at'] = nowStr();
            $a[$id]['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($r === 'notfound') return [false, 'سفارش پیدا نشد.'];
        if ($r === 'done')     return [false, 'این سفارش قبلا بررسی شده است.'];

        $o = self::get($id);

        if ($o['type'] === 'topup') {
            addBalance($o['user_id'], $o['amount']);
            return [true, $o];
        }

        $p = Product::get($o['product_id']);
        if (!$p) return [false, 'محصول پیدا نشد.'];
        if (Product::isFull($p) && !Product::hasBought($p['id'], $o['user_id'])) {
            return [false, 'ظرفیت این محصول تکمیل است.'];
        }
        Product::addBuyer($p['id'], $o['user_id']);
        payReferralCommission($o['user_id'], $o['amount']);
        return [true, $o];
    }

    public static function reject($id, $adminId) {
        $r = mutate('orders', function (&$a) use ($id, $adminId) {
            if (!isset($a[$id])) return 'notfound';
            if (in_array($a[$id]['status'], [self::APPROVED, self::REJECTED], true)) return 'done';
            $a[$id]['status'] = self::REJECTED;
            $a[$id]['decided_at'] = nowStr();
            $a[$id]['decided_by'] = (int)$adminId;
            return 'ok';
        });
        if ($r === 'notfound') return [false, 'سفارش پیدا نشد.'];
        if ($r === 'done')     return [false, 'این سفارش قبلا بررسی شده است.'];
        return [true, self::get($id)];
    }

    public static function forUser($uid) {
        $out = [];
        foreach (self::all() as $o) if ((int)$o['user_id'] === (int)$uid) $out[] = $o;
        usort($out, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $out;
    }

    public static function countBy($status) {
        $n = 0;
        foreach (self::all() as $o) if ($o['status'] === $status) $n++;
        return $n;
    }

    public static function statusLabel($s) {
        return [
            'pending'  => '⏳ منتظر رسید',
            'review'   => '🧾 در حال بررسی',
            'approved' => '✅ تایید شده',
            'rejected' => '❌ رد شده',
        ][$s] ?? '—';
    }
}

// ============================================================
// 🤖 ربات‌های اپلودر
// ============================================================

class BotManager
{
    public static function all() { return load('bots'); }
    public static function get($id) { $a = load('bots'); return $a[$id] ?? null; }

    public static function create($token, $username) {
        $id = uid('b');
        mutate('bots', function (&$a) use ($id, $token, $username) {
            $a[$id] = [
                'id' => $id, 'token' => $token, 'username' => ltrim($username, '@'),
                'active' => true, 'created_at' => nowStr(), 'admins' => [],
                'settings' => cfg()['uploader'],   // تنظیمات پیش‌فرض روی هر ربات جدید
            ];
        });
        return self::get($id);
    }

    /** آیا این کاربر می‌تواند ربات را مدیریت کند؟ */
    public static function isManager($botId, $userId) {
        if ((int)$userId === (int)ADMIN_ID) return true;
        $b = self::get($botId);
        $admins = array_map('intval', $b['admins'] ?? []);
        return in_array((int)$userId, $admins, true);
    }

    public static function addAdmin($botId, $userId) {
        mutate('bots', function (&$a) use ($botId, $userId) {
            if (!isset($a[$botId])) return;
            $ad = array_map('intval', $a[$botId]['admins'] ?? []);
            if (!in_array((int)$userId, $ad, true)) $ad[] = (int)$userId;
            $a[$botId]['admins'] = array_values($ad);
        });
    }

    public static function removeAdmin($botId, $userId) {
        mutate('bots', function (&$a) use ($botId, $userId) {
            if (!isset($a[$botId])) return;
            $a[$botId]['admins'] = array_values(array_filter(
                array_map('intval', $a[$botId]['admins'] ?? []),
                fn($u) => $u !== (int)$userId));
        });
    }

    /** تنظیمات یک ربات: مقادیر خودش روی پیش‌فرض ربات مادر سوار می‌شود */
    public static function settings($botId) {
        $b = self::get($botId);
        $base = cfg()['uploader'];
        return array_merge($base, is_array($b['settings'] ?? null) ? $b['settings'] : []);
    }

    public static function setSetting($botId, $key, $value) {
        mutate('bots', function (&$a) use ($botId, $key, $value) {
            if (!isset($a[$botId])) return;
            if (!is_array($a[$botId]['settings'] ?? null)) $a[$botId]['settings'] = [];
            $a[$botId]['settings'][$key] = $value;
        });
    }
}

// ============================================================
// 📢 کانال‌های عضویت اجباری (سراسری — از ربات مادر)
// ============================================================

class Channels
{
    public static function all() { return load('channels'); }

    public static function get($id) { $a = load('channels'); return $a[$id] ?? null; }

    public static function add($chatId, $title, $url, $bots = []) {
        // اگر همین کانال قبلا ثبت شده، تکراری نساز
        foreach (self::all() as $ch) {
            if ((string)$ch['chat_id'] === (string)$chatId) return $ch['id'];
        }
        $id = uid('ch');
        mutate('channels', function (&$a) use ($id, $chatId, $title, $url, $bots) {
            $a[$id] = ['id' => $id, 'chat_id' => $chatId, 'title' => $title,
                       'url' => $url, 'on' => true, 'bots' => array_values($bots)];
        });
        return $id;
    }

    public static function remove($id) {
        mutate('channels', function (&$a) use ($id) { unset($a[$id]); });
    }

    /** کانال‌هایی که برای این ربات اعمال می‌شوند (bots خالی = همه ربات‌ها) */
    public static function forBot($botId) {
        $out = [];
        foreach (self::all() as $ch) {
            if (empty($ch['on'])) continue;
            $bots = $ch['bots'] ?? [];
            if (!$bots || in_array($botId, $bots, true)) $out[] = $ch;
        }
        return $out;
    }

    /**
     * بررسی عضویت — همیشه با توکن «ربات مادر».
     * پس فقط ربات مادر باید در کانال ادمین باشد؛ ربات‌های اپلودر
     * لازم نیست اصلا عضو کانال باشند.
     *
     * نتیجه در همین درخواست کش می‌شود تا کلیک‌های پشت‌سرهم ربات را کند نکند.
     * اگر بررسی ممکن نباشد، کانال «عضو نشده» حساب می‌شود (fail-closed)
     * تا کسی نتواند با خراب کردن دسترسی، قفل را دور بزند.
     */
    public static function isMemberOf($chatId, $userId) {
        static $cache = [];
        $k = $chatId . ':' . $userId;
        if (isset($cache[$k])) return $cache[$k];

        $r = tg(BOT_TOKEN, 'getChatMember',
                ['chat_id' => $chatId, 'user_id' => $userId], 6);

        if (empty($r['ok'])) {
            $desc = strtolower($r['description'] ?? '');
            // «کاربر پیدا نشد» یعنی واقعا عضو نیست، نه اینکه دسترسی نداریم
            $reallyNotMember = str_contains($desc, 'user not found')
                            || str_contains($desc, 'participant_id_invalid');
            return $cache[$k] = ['ok' => $reallyNotMember, 'member' => false, 'error' => $r['description'] ?? ''];
        }
        $st = $r['result']['status'] ?? '';
        $isIn = in_array($st, ['member', 'administrator', 'creator'], true);
        return $cache[$k] = ['ok' => true, 'member' => $isIn, 'error' => ''];
    }

    /** کانال‌هایی که کاربر هنوز عضو نشده — برای این ربات */
    public static function missing($userId, $botId = null) {
        $list = $botId ? self::forBot($botId)
                       : array_values(array_filter(self::all(), fn($c) => !empty($c['on'])));
        $missing = [];
        foreach ($list as $ch) {
            $res = self::isMemberOf($ch['chat_id'], $userId);
            if (!$res['ok']) { $ch['unverifiable'] = true; $ch['error'] = $res['error']; $missing[] = $ch; continue; }
            if (!$res['member']) $missing[] = $ch;
        }
        return $missing;
    }

    /** بررسی سلامت: آیا ربات مادر در کانال‌ها دسترسی دارد؟ */
    public static function health() {
        $out = [];
        foreach (self::all() as $ch) {
            $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $ch['chat_id']], 6);
            $m = tg(BOT_TOKEN, 'getChatMember',
                    ['chat_id' => $ch['chat_id'], 'user_id' => (int)ADMIN_ID], 6);
            $out[$ch['id']] = [
                'title' => $ch['title'],
                'ok'    => !empty($r['ok']) && !empty($m['ok']),
                'error' => $r['description'] ?? ($m['description'] ?? ''),
            ];
        }
        return $out;
    }
}

// ============================================================
// 🤝 ربات‌های شریک (سورس مستقل) — فقط عضویت اجباری
// ============================================================

class Partner
{
    public static function all() { return load('partners'); }
    public static function get($id) { $a = load('partners'); return $a[$id] ?? null; }

    public static function create($name, $botUsername, $ownerId = 0) {
        $id  = uid('pt');
        $key = 'pk_' . bin2hex(random_bytes(20));
        mutate('partners', function (&$a) use ($id, $key, $name, $botUsername, $ownerId) {
            $a[$id] = [
                'id' => $id, 'name' => $name, 'key' => $key,
                'bot_username' => ltrim($botUsername, '@'), 'owner_id' => (int)$ownerId,
                'active' => true, 'created_at' => nowStr(),
                'checks' => 0, 'passed' => 0, 'last_seen' => null,
            ];
        });
        return self::get($id);
    }

    /** پیدا کردن شریک از روی کلید — مقایسه زمان‌ثابت */
    public static function byKey($key) {
        if (!is_string($key) || strlen($key) < 20) return null;
        foreach (self::all() as $p) {
            if (hash_equals((string)$p['key'], $key)) return $p;
        }
        return null;
    }

    public static function rotateKey($id) {
        $key = 'pk_' . bin2hex(random_bytes(20));
        mutate('partners', function (&$a) use ($id, $key) {
            if (isset($a[$id])) $a[$id]['key'] = $key;
        });
        return $key;
    }

    public static function remove($id) {
        mutate('partners', function (&$a) use ($id) { unset($a[$id]); });
    }

    public static function bump($id, $passed) {
        mutate('partners', function (&$a) use ($id, $passed) {
            if (!isset($a[$id])) return;
            $a[$id]['checks'] = (int)$a[$id]['checks'] + 1;
            if ($passed) $a[$id]['passed'] = (int)$a[$id]['passed'] + 1;
            $a[$id]['last_seen'] = nowStr();
        });
    }

    /**
     * محدودیت نرخ ساده — جلوی سیل درخواست را می‌گیرد بدون اینکه
     * ربات‌های سالم را کند کند. پنجره ۶۰ ثانیه‌ای.
     */
    public static function rateOk($id, $limit = 600) {
        $win = (int)(time() / 60);
        return mutate('ratelimit', function (&$a) use ($id, $win, $limit) {
            if (($a[$id]['win'] ?? -1) !== $win) $a[$id] = ['win' => $win, 'n' => 0];
            $a[$id]['n']++;
            if (count($a) > 200) $a = array_slice($a, -100, null, true);  // نگهداری سبک
            return $a[$id]['n'] <= $limit;
        });
    }
}

// ============================================================
// 🎯 کمپین‌ها — سفارش ممبر: کانال قفل می‌ماند تا سهمیه پر شود
// ============================================================

class Campaign
{
    public static function all() { return load('campaigns'); }
    public static function get($id) { $a = load('campaigns'); return $a[$id] ?? null; }

    public static function create($title, $chatId, $url, $target, $partners = [], $bots = [], $note = '') {
        $id = uid('cm');
        mutate('campaigns', function (&$a) use ($id, $title, $chatId, $url, $target, $partners, $bots, $note) {
            $a[$id] = [
                'id' => $id, 'title' => $title, 'chat_id' => $chatId, 'url' => $url,
                'target' => max(0, (int)$target), 'joined' => [],
                'partners' => array_values($partners), 'bots' => array_values($bots),
                'note' => $note, 'active' => true,
                'created_at' => nowStr(), 'done_at' => null,
            ];
        });
        return self::get($id);
    }

    public static function isDone($c) {
        return ((int)$c['target']) > 0 && count($c['joined']) >= (int)$c['target'];
    }

    public static function remaining($c) {
        return ((int)$c['target']) > 0 ? max(0, (int)$c['target'] - count($c['joined'])) : '∞';
    }

    /** کمپین‌های فعالی که برای این ربات/شریک باید قفل شوند */
    public static function activeFor($botId = null, $partnerId = null) {
        $out = [];
        foreach (self::all() as $c) {
            if (empty($c['active']) || self::isDone($c)) continue;
            if ($botId !== null) {
                $sc = $c['bots'] ?? [];
                if ($sc && !in_array($botId, $sc, true)) continue;
            }
            if ($partnerId !== null) {
                $sp = $c['partners'] ?? [];
                if ($sp && !in_array($partnerId, $sp, true)) continue;
            }
            $out[] = $c;
        }
        return $out;
    }

    /** ثبت یک عضو تازه — هر کاربر فقط یک بار برای هر کمپین شمرده می‌شود */
    public static function credit($id, $userId) {
        return mutate('campaigns', function (&$a) use ($id, $userId) {
            if (!isset($a[$id]) || empty($a[$id]['active'])) return false;
            $j = array_map('intval', $a[$id]['joined']);
            if (in_array((int)$userId, $j, true)) return false;
            $t = (int)$a[$id]['target'];
            if ($t > 0 && count($j) >= $t) return false;
            $j[] = (int)$userId;
            $a[$id]['joined'] = array_values($j);
            if ($t > 0 && count($j) >= $t) $a[$id]['done_at'] = nowStr();
            return true;
        });
    }

    public static function remove($id) {
        mutate('campaigns', function (&$a) use ($id) { unset($a[$id]); });
    }
}

/**
 * کانال‌هایی که کاربر باید عضو شود = کانال‌های ثابت + کمپین‌های فعال.
 * برگشت: [missing[], creditable[]] — creditable شناسه کمپین‌هایی که
 * کاربر تازه عضوشان شده و باید شمرده شوند.
 */
function requiredMissing($userId, $botId = null, $partnerId = null) {
    $missing = [];
    $creditable = [];

    if ($botId !== null) {
        foreach (Channels::missing($userId, $botId) as $m) $missing[] = $m;
    }

    foreach (Campaign::activeFor($botId, $partnerId) as $c) {
        $res = Channels::isMemberOf($c['chat_id'], $userId);
        if (!$res['ok']) {
            $missing[] = ['title' => $c['title'], 'url' => $c['url'],
                          'chat_id' => $c['chat_id'], 'unverifiable' => true, 'error' => $res['error']];
            continue;
        }
        if (!$res['member']) {
            $missing[] = ['title' => $c['title'], 'url' => $c['url'], 'chat_id' => $c['chat_id']];
        } else {
            $creditable[] = $c['id'];
        }
    }
    return [$missing, $creditable];
}

// ============================================================
// 🔗 لینک‌های اپلودر
// ============================================================

class Links
{
    public static function file($botId) { return 'bots/' . $botId . '/links'; }

    public static function all($botId) { return load(self::file($botId)); }
    public static function get($botId, $code) { $a = load(self::file($botId)); return $a[$code] ?? null; }

    public static function create($botId, $files, $title = '') {
        $code = genCode(12);
        mutate(self::file($botId), function (&$a) use ($code, $files, $title) {
            $a[$code] = [
                'code' => $code, 'title' => $title,
                'files' => $files,            // [['type'=>..,'file_id'=>..,'name'=>..,'caption'=>..], ...]
                'clicks' => 0, 'delivered' => 0,
                'active' => true, 'created_at' => nowStr(),
            ];
        });
        return $code;
    }

    public static function hit($botId, $code, $field) {
        mutate(self::file($botId), function (&$a) use ($code, $field) {
            if (isset($a[$code])) $a[$code][$field] = (int)($a[$code][$field] ?? 0) + 1;
        });
    }

    public static function remove($botId, $code) {
        mutate(self::file($botId), function (&$a) use ($code) { unset($a[$code]); });
    }

    public static function url($botId, $code) {
        $b = BotManager::get($botId);
        return $b ? "https://t.me/{$b['username']}?start={$code}" : null;
    }
}

function botUserTouch($botId, $userId, $username, $firstName = '') {
    mutate('bots/' . $botId . '/users', function (&$a) use ($userId, $username, $firstName) {
        $k = (string)$userId;
        $a[$k] = array_merge(['joined_at' => nowStr()], $a[$k] ?? [], [
            'id' => (int)$userId, 'username' => $username,
            'first_name' => $firstName, 'seen_at' => nowStr(),
        ]);
    });
}

// ============================================================
// 🗑 صف حذف خودکار پیام‌ها
// ============================================================

function scheduleDelete($botId, $chatId, $msgIds, $seconds, $noticeId = null) {
    $due = time() + max(1, (int)$seconds);
    mutate('delqueue', function (&$q) use ($botId, $chatId, $msgIds, $due, $noticeId) {
        $q[] = [
            'bot_id' => $botId, 'chat_id' => $chatId,
            'msg_ids' => array_values(array_filter($msgIds)),
            'notice_id' => $noticeId, 'due' => $due,
        ];
    });
}

/** حذف موارد سررسیدشده — در هر درخواست و در cron صدا زده می‌شود */
function processDeleteQueue($limit = 60) {
    $due = [];
    mutate('delqueue', function (&$q) use (&$due, $limit) {
        if (!$q) return;
        $now = time();
        $keep = [];
        foreach ($q as $item) {
            if (count($due) < $limit && (int)$item['due'] <= $now) $due[] = $item;
            else $keep[] = $item;
        }
        $q = array_values($keep);
    });

    foreach ($due as $item) {
        $bot = BotManager::get($item['bot_id']);
        $token = $bot ? $bot['token'] : BOT_TOKEN;
        foreach ($item['msg_ids'] as $mid) delMsg($token, $item['chat_id'], $mid);
        if (!empty($item['notice_id'])) delMsg($token, $item['chat_id'], $item['notice_id']);
        $set = $bot ? BotManager::settings($item['bot_id']) : cfg()['uploader'];
        if (!empty($set['deleted_text'])) sendMsg($token, $item['chat_id'], $set['deleted_text']);
    }
    return count($due);
}

/**
 * اگر سرور اجازه بدهد، پاسخ را می‌بندد و در پس‌زمینه صبر می‌کند تا دقیقا سر وقت حذف کند.
 * توجه: این کار یک worker را تا پایان مهلت نگه می‌دارد؛ برای ترافیک بالا
 * بهتر است inline_wait را خاموش کنید و به‌جایش cron را هر دقیقه صدا بزنید.
 */
function tryImmediateDelete($botId, $chatId, $msgIds, $seconds, $noticeId = null) {
    $set = BotManager::settings($botId);
    if (empty($set['inline_wait'])) return false;
    if (!function_exists('fastcgi_finish_request')) return false;
    if ($seconds > 180) return false;     // مهلت طولانی → فقط از صف/کران استفاده کن

    scheduleDelete($botId, $chatId, $msgIds, $seconds, $noticeId);
    echo json_encode(['ok' => true]);
    @fastcgi_finish_request();
    @set_time_limit($seconds + 30);
    sleep((int)$seconds);
    processDeleteQueue();
    exit;
}

// ============================================================
// 🏠 ربات مادر — صفحه‌ها
// ============================================================

function showHome($uid, $chatId, $firstName) {
    sendMsg(BOT_TOKEN, $chatId, T('welcome', ['name' => h($firstName)]), mainKeyboard());
}

/** راهنمای بستن منو — یک بار برای هر کاربر */
function hintHideOnce($uid, $chatId) {
    if (cfg()['ui']['mode'] !== 'menu') return;
    $u = getUser($uid);
    if (!empty($u['hide_hint'])) return;
    mutate('users', function (&$a) use ($uid) {
        if (isset($a[(string)$uid])) $a[(string)$uid]['hide_hint'] = true;
    });
    sendMsg(BOT_TOKEN, $chatId, "ℹ️ برای بستن منو هر وقت خواستید /hide را بزنید.");
}

function showAccount($uid, $chatId) {
    $u = getUser($uid) ?: [];
    $orders = 0;
    foreach (Order::forUser($uid) as $o) if ($o['status'] === Order::APPROVED) $orders++;

    $text = T('account', [
        'id'         => $uid,
        'name'       => h($u['first_name'] ?? '—'),
        'username'   => !empty($u['username']) ? '@' . h($u['username']) : '—',
        'balance'    => fmtNum($u['balance'] ?? 0),
        'orders'     => $orders,
        'referrals'  => countReferrals($uid),
        'ref_earned' => fmtNum($u['ref_earned'] ?? 0),
        'joined'     => h($u['joined_at'] ?? '—'),
    ]);
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([
        [['text' => UT('topup'), 'callback_data' => 'menu_topup', 'style' => gs('buy') ?: null]],
        [['text' => UT('my_orders'), 'callback_data' => 'menu_orders', 'style' => gs('info') ?: null]],
    ]));
}

function productBtn($p, $uid) {
    $emoji = $p['emoji'] ?? '💠';
    if (Product::hasBought($p['id'], $uid)) {
        $b = btnCb(trim("$emoji " . $p['name'] . ' — ' . UT('redeliver')), 'redeliver_' . $p['id'], 'link');
    } elseif (Product::isFull($p)) {
        $b = btnCb(trim("$emoji " . $p['name'] . ' — ' . UT('full')), 'full_' . $p['id'], 'reject');
    } else {
        $b = btnCb(trim("$emoji " . $p['name'] . ' — ' . fmtNum($p['price']) . ' ' . $p['currency']),
                   'buy_' . $p['id']);
        if (isStyle($p['color'] ?? '')) $b['style'] = $p['color'];
        elseif (gs('buy')) $b['style'] = gs('buy');
    }
    if (!empty($p['icon'])) $b['icon_custom_emoji_id'] = $p['icon'];
    return $b;
}

function activeProducts() {
    $list = [];
    foreach (Product::all() as $p) {
        if (empty($p['active'])) continue;
        $list[] = $p;
    }
    usort($list, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));
    return $list;
}

function showProducts($uid, $chatId) {
    $prods = activeProducts();
    if (!$prods) { sendMsg(BOT_TOKEN, $chatId, T('buy_empty')); return; }

    $text = T('buy_head') . "\n";
    foreach ($prods as $p) {
        $cnt = count($p['buyers']);
        $cap = ((int)$p['limit']) > 0 ? "{$cnt}/{$p['limit']}" : "{$cnt}/∞";
        $text .= "\n" . ($p['emoji'] ?? '💠') . " <b>" . h($p['name']) . "</b>\n";
        if (!empty($p['desc'])) $text .= "   " . h($p['desc']) . "\n";
        $text .= "   💰 " . fmtNum($p['price']) . ' ' . h($p['currency']) . "  |  👥 {$cap}\n";
    }

    // چیدمان محصول‌ها: ردیف صریح، وگرنه الگوی محصولات
    $hasRows = false;
    foreach ($prods as $p) if (!empty($p['row'])) { $hasRows = true; break; }
    if ($hasRows) {
        $byRow = [];
        foreach ($prods as $p) $byRow[(int)($p['row'] ?: 99)][] = $p;
        ksort($byRow);
        $groups = array_values($byRow);
    } else {
        $groups = layoutRows($prods, cfg()['ui']['product_layout'] ?? '1');
    }

    $rows = [];
    foreach ($groups as $g) {
        $line = [];
        foreach ($g as $p) $line[] = productBtn($p, $uid);
        if ($line) $rows[] = $line;
    }
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

/** نمایش یک محصول تکی — برای دکمه‌های سفارشی «محصول» */
function showOneProduct($uid, $chatId, $p) {
    $cnt = count($p['buyers']);
    $cap = ((int)$p['limit']) > 0 ? "{$cnt}/{$p['limit']}" : "{$cnt}/∞";
    $text  = ($p['emoji'] ?? '💠') . " <b>" . h($p['name']) . "</b>\n\n";
    if (!empty($p['desc'])) $text .= h($p['desc']) . "\n\n";
    $text .= "💰 قیمت: <b>" . fmtNum($p['price']) . ' ' . h($p['currency']) . "</b>\n";
    $text .= "👥 خریداران: {$cap}";
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([[productBtn($p, $uid)]]));
}

function showOrders($uid, $chatId) {
    $orders = Order::forUser($uid);
    if (!$orders) { sendMsg(BOT_TOKEN, $chatId, T('orders_empty')); return; }

    $text = T('orders_head') . "\n";
    foreach (array_slice($orders, 0, 15) as $o) {
        $title = $o['type'] === 'topup'
            ? '➕ شارژ کیف پول'
            : '🛒 ' . h(Product::get($o['product_id'])['name'] ?? '—');
        $text .= "\n" . Order::statusLabel($o['status']) . "\n";
        $text .= "   {$title}\n";
        $text .= "   💰 " . fmtNum($o['amount']) . ' ' . h($o['currency']) . "\n";
        $text .= "   🧾 <code>" . h($o['id']) . "</code>\n";
        $text .= "   📅 " . h($o['created_at']) . "\n";
    }
    sendMsg(BOT_TOKEN, $chatId, $text);
}

function showReferral($uid, $chatId) {
    $u  = getUser($uid) ?: [];
    $me = tg(BOT_TOKEN, 'getMe', []);
    $un = $me['result']['username'] ?? '';
    $link = $un ? "https://t.me/{$un}?start=ref{$uid}" : '—';

    sendMsg(BOT_TOKEN, $chatId, T('referral', [
        'percent'    => cfg()['referral']['percent'],
        'link'       => $link,
        'referrals'  => countReferrals($uid),
        'ref_earned' => fmtNum($u['ref_earned'] ?? 0),
    ]));
}

function showSupport($uid, $chatId) {
    $methods = cfg()['support_methods'];
    $rows = [];
    $direct = []; $indirect = [];

    foreach ($methods as $i => $m) {
        if (empty($m['on'])) continue;
        $label = trim(($m['emoji'] ?? '') . ' ' . ($m['label'] ?? ''));
        if ($m['type'] === 'url' && !empty($m['value'])) {
            $btn = ['text' => $label, 'url' => $m['value']];
        } else {
            $btn = ['text' => $label, 'callback_data' => 'sup_' . $i, 'style' => gs('support') ?: null];
        }
        if (($m['kind'] ?? 'direct') === 'direct') $direct[] = $btn; else $indirect[] = $btn;
    }

    $text = T('support');
    if ($direct) {
        $text .= "\n\n🟢 <b>ارتباط مستقیم</b>";
        foreach (array_chunk($direct, 2) as $c) $rows[] = $c;
    }
    if ($indirect) {
        $text .= "\n🔵 <b>ارتباط غیر مستقیم</b>";
        foreach (array_chunk($indirect, 2) as $c) $rows[] = $c;
    }
    if (!$rows) { sendMsg(BOT_TOKEN, $chatId, "📞 هنوز راه ارتباطی تنظیم نشده است."); return; }

    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function startTopup($uid, $chatId) {
    setState($uid, 'topup_amount');
    sendMsg(BOT_TOKEN, $chatId, T('topup'), inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
}

function walletFor($currency) {
    $w = cfg()['wallets'];
    $c = strtoupper(trim($currency));
    if ($c === 'USDT') return ['USDT (TRC20)', $w['usdt'] ?: 'تنظیم نشده'];
    if ($c === 'TRX')  return ['TRX', $w['trx'] ?: 'تنظیم نشده'];
    $card = $w['card'] ?: 'تنظیم نشده';
    if (!empty($w['card_name'])) $card .= "\nبه نام: " . $w['card_name'];
    return ['کارت به کارت', $card];
}

function createOrderAndAsk($uid, $chatId, $username, $type, $productId, $amount, $currency, $title) {
    $oid = Order::create($uid, $username, $type, $productId, $amount, $currency);
    [$method, $wallet] = walletFor($currency);
    $text = T('pay_info', [
        'title' => $title, 'amount' => fmtNum($amount), 'currency' => h($currency),
        'method' => $method, 'wallet' => h($wallet), 'id' => h($oid),
    ]);
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb([
        [['text' => UT('receipt'), 'callback_data' => 'rcpt_' . $oid, 'style' => gs('confirm') ?: null]],
        [['text' => UT('cancel'), 'callback_data' => 'ocancel_' . $oid, 'style' => gs('cancel') ?: null]],
    ]));
    return $oid;
}

function deliverProduct($uid, $chatId, $productId) {
    $p = Product::get($productId);
    if (!$p) return;
    if (!empty($p['bot_id']) && !empty($p['link_code'])) {
        $url = Links::url($p['bot_id'], $p['link_code']);
        if ($url) {
            sendMsg(BOT_TOKEN, $chatId,
                "📦 <b>" . h($p['name']) . "</b>\n\n🔗 لینک دریافت محتوا:\n{$url}",
                inlineKb([[['text' => UT('enter_bot'), 'url' => $url, 'style' => gs('link') ?: null]]]));
            return;
        }
    }
    sendMsg(BOT_TOKEN, $chatId, "📦 <b>" . h($p['name']) . "</b>\n\n✅ خرید شما ثبت شد. برای دریافت با پشتیبانی تماس بگیرید.");
}

/**
 * اعلام فروش در کانال جدا — کد خرید، مبلغ، تعداد ممبر
 * از هر دو مسیر تایید (تلگرام و پنل وب) صدا زده می‌شود.
 */
function announceSale($order) {
    $s = cfg()['sales'];
    if (empty($s['on']) || empty($s['chat_id'])) return;
    if (($order['type'] ?? '') !== 'product') return;

    $p = Product::get($order['product_id']);
    if (!$p) return;

    $count = count($p['buyers']);
    $limit = (int)$p['limit'];
    $limitPart = $limit > 0
        ? " از {$limit}\n🎯 باقی‌مانده: <b>" . max(0, $limit - $count) . "</b>"
        : '';

    $user = '—';
    if (!empty($s['show_user'])) {
        $u = getUser($order['user_id']);
        $user = !empty($u['username']) ? '@' . $u['username'] : ($u['first_name'] ?? $order['user_id']);
    }

    $text = strtr($s['template'], [
        '{product}'    => h($p['name']),
        '{code}'       => h($order['id']),
        '{amount}'     => fmtNum($order['amount']),
        '{currency}'   => h($order['currency']),
        '{count}'      => $count,
        '{limit}'      => $limit > 0 ? $limit : '∞',
        '{remaining}'  => $limit > 0 ? max(0, $limit - $count) : '∞',
        '{limit_part}' => $limitPart,
        '{user}'       => h($user),
        '{user_id}'    => (int)$order['user_id'],
        '{date}'       => h(nowStr()),
    ]);

    $r = sendMsg(BOT_TOKEN, $s['chat_id'], $text);
    if (empty($r['ok'])) {
        error_log('[sales-channel] ' . ($r['description'] ?? 'unknown'));
    }
}

/** کارهای بعد از تایید سفارش — یک جا، تا پنل و ربات دقیقا یکسان رفتار کنند */
function completeApprovedOrder($order) {
    if (($order['type'] ?? '') === 'topup') {
        sendMsg(BOT_TOKEN, $order['user_id'],
            "✅ کیف پول شما <b>" . fmtNum($order['amount']) . "</b> تومان شارژ شد.\n" .
            "💰 موجودی جدید: <b>" . fmtNum(getUser($order['user_id'])['balance'] ?? 0) . "</b> تومان");
        return;
    }
    sendMsg(BOT_TOKEN, $order['user_id'], T('approved'));
    deliverProduct($order['user_id'], $order['user_id'], $order['product_id']);
    announceSale($order);
}

function notifyAdminOrder($orderId) {
    $o = Order::get($orderId);
    if (!$o) return;
    $title = $o['type'] === 'topup' ? '➕ شارژ کیف پول' : ('🛒 ' . (Product::get($o['product_id'])['name'] ?? '—'));
    $uname = $o['username'] ? '@' . $o['username'] : '—';

    $text  = "🧾 <b>سفارش جدید — منتظر تایید</b>\n\n";
    $text .= "👤 کاربر: " . h($uname) . " (<code>{$o['user_id']}</code>)\n";
    $text .= "📦 {$title}\n";
    $text .= "💰 " . fmtNum($o['amount']) . ' ' . h($o['currency']) . "\n";
    $text .= "🧾 <code>" . h($o['id']) . "</code>\n";
    $text .= "📅 " . h($o['created_at']) . "\n\n";
    $text .= $o['receipt_type'] === 'text'
        ? "رسید:\n<code>" . h($o['receipt']) . "</code>"
        : "رسید: تصویر ↓";

    $rows = [[
        ['text' => UT('confirm'), 'callback_data' => 'aok_' . $o['id']],
        ['text' => UT('reject'),   'callback_data' => 'ano_' . $o['id']],
    ]];

    if ($o['receipt_type'] === 'photo') {
        tg(BOT_TOKEN, 'sendPhoto', [
            'chat_id' => ADMIN_ID, 'photo' => $o['receipt'],
            'caption' => $text, 'parse_mode' => 'HTML',
            'reply_markup' => json_encode(inlineKb($rows)),
        ]);
    } else {
        sendMsg(BOT_TOKEN, ADMIN_ID, $text, inlineKb($rows));
    }
}

// ============================================================
// 👑 پنل ادمین در تلگرام
// ============================================================

function admHome($chatId, $msgId = null) {
    $text  = "👑 <b>پنل مدیریت</b>\n\n";
    $text .= "👥 کاربران: " . count(load('users')) . "\n";
    $text .= "🛒 محصولات: " . count(Product::all()) . "\n";
    $text .= "🤖 ربات‌های اپلودر: " . count(BotManager::all()) . "\n";
    $text .= "📢 کانال‌های اجباری: " . count(Channels::all()) . "\n";
    $text .= "⏳ منتظر تایید: " . Order::countBy(Order::REVIEW) . "\n";
    $text .= "✅ سفارش موفق: " . Order::countBy(Order::APPROVED) . "\n";

    $rows = [
        [btnCb('🎨 دکمه‌ها', 'ebuttons', 'admin'), btnCb('📝 متن‌ها', 'etexts', 'admin')],
        [btnCb('💠 رنگ دکمه‌های شیشه‌ای', 'eglass', 'admin')],
        [btnCb('🧾 سفارش‌ها', 'adm_orders', 'admin'), btnCb('🤖 ربات‌ها', 'adm_bots', 'admin')],
        [btnCb('📢 کانال‌ها', 'adm_chans', 'admin'), btnCb('📢 پیام همگانی', 'adm_bc', 'admin')],
        [btnCb('🌐 پنل وب', 'adm_web', 'info')],
        [btnCb(UT('home'), 'home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

function admOrders($chatId, $msgId) {
    $pending = array_filter(Order::all(), fn($o) => $o['status'] === Order::REVIEW);
    $text = "🧾 <b>سفارش‌های منتظر تایید</b>\n";
    $rows = [];
    if (!$pending) {
        $text .= "\nموردی در انتظار نیست.";
    } else {
        foreach (array_slice($pending, 0, 8, true) as $o) {
            $title = $o['type'] === 'topup' ? 'شارژ' : (Product::get($o['product_id'])['name'] ?? '—');
            $text .= "\n👤 " . h($o['username'] ?: $o['user_id']) . " — " . h($title) . "\n";
            $text .= "   💰 " . fmtNum($o['amount']) . ' ' . h($o['currency']) . "\n";
            $rows[] = [
                ['text' => '🟢 تایید ' . mb_substr($title, 0, 12), 'callback_data' => 'aok_' . $o['id']],
                ['text' => UT('reject'), 'callback_data' => 'ano_' . $o['id']],
            ];
        }
    }
    $rows[] = [['text' => UT('back'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function admBtns($chatId, $msgId) {
    $c = cfg();
    $text  = "🎨 <b>تنظیم دکمه‌ها</b>\n\n";
    $text .= "حالت فعلی: <b>" . ($c['ui']['mode'] === 'glass' ? 'شیشه‌ای (زیر پیام)' : 'منو (کیبورد پایین)') . "</b>\n\n";
    foreach ($c['buttons'] as $id => $b) {
        $text .= (!empty($b['on']) ? '✅' : '❌') . ' ' . colorMap()[$b['color']] . ' ' . h($b['emoji'] . ' ' . $b['text']) . "\n";
    }
    $text .= "\nبرای ویرایش دقیق‌تر از پنل وب استفاده کنید.";

    $rows = [
        [['text' => $c['ui']['mode'] === 'glass' ? '🔄 تبدیل به منو' : '🔄 تبدیل به شیشه‌ای',
          'callback_data' => 'adm_mode']],
    ];
    foreach ($c['buttons'] as $id => $b) {
        $rows[] = [
            ['text' => (!empty($b['on']) ? '✅ ' : '❌ ') . $b['text'], 'callback_data' => 'adm_btog_' . $id],
            ['text' => colorMap()[$b['color']] . ' رنگ', 'callback_data' => 'adm_bcol_' . $id],
        ];
    }
    $rows[] = [['text' => UT('back'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function admTexts($chatId, $msgId) {
    $rows = [];
    $labels = [
        'welcome' => 'خوش‌آمد', 'account' => 'حساب کاربری', 'trust' => 'اعتماد',
        'support' => 'پشتیبانی', 'referral' => 'زیرمجموعه', 'topup' => 'شارژ',
        'buy_head' => 'سر محصولات', 'orders_head' => 'سر سفارش‌ها',
    ];
    foreach ($labels as $k => $l) $rows[] = [['text' => '📝 ' . $l, 'callback_data' => 'adm_txt_' . $k, 'style' => gs('admin') ?: null]];
    $rows[] = [['text' => UT('back'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
    editMsg(BOT_TOKEN, $chatId, $msgId, "📝 <b>ویرایش متن‌ها</b>\n\nکدام متن را می‌خواهید عوض کنید؟", inlineKb($rows));
}

function admChans($chatId, $msgId) {
    $chs = Channels::all();
    $text = "📢 <b>کانال‌های عضویت اجباری</b>\n\nاین کانال‌ها روی <b>همه</b> ربات‌های اپلودر اعمال می‌شوند.\n";
    $rows = [];
    if (!$chs) $text .= "\nکانالی ثبت نشده.";
    foreach ($chs as $ch) {
        $text .= "\n" . (!empty($ch['on']) ? '✅' : '❌') . ' ' . h($ch['title']) . " — <code>" . h($ch['chat_id']) . "</code>";
        $rows[] = [['text' => '🔴 حذف ' . $ch['title'], 'callback_data' => 'adm_chdel_' . $ch['id']]];
    }
    $rows[] = [['text' => '🟢 افزودن کانال', 'callback_data' => 'adm_chadd', 'style' => gs('admin') ?: null]];
    $rows[] = [['text' => UT('back'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function admBots($chatId, $msgId) {
    $bots = BotManager::all();
    $text = "🤖 <b>ربات‌های اپلودر</b>\n";
    $rows = [];
    if (!$bots) $text .= "\nرباتی ثبت نشده.";
    foreach ($bots as $b) {
        $s = BotManager::settings($b['id']);
        $text .= "\n🤖 @" . h($b['username']) . "\n";
        $text .= "   🔗 لینک‌ها: " . count(Links::all($b['id'])) . "  |  🗑 حذف بعد از {$s['delete_seconds']} ثانیه\n";
        $rows[] = [['text' => '⚙️ @' . $b['username'], 'callback_data' => 'adm_bot_' . $b['id']]];
    }
    $rows[] = [['text' => '🟢 افزودن ربات', 'callback_data' => 'adm_addbot', 'style' => gs('admin') ?: null]];
    $rows[] = [['text' => UT('back'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

function admBotOne($chatId, $msgId, $botId) {
    $b = BotManager::get($botId);
    if (!$b) { admBots($chatId, $msgId); return; }
    $s = BotManager::settings($botId);

    $text  = "⚙️ <b>@" . h($b['username']) . "</b>\n\n";
    $text .= "🗑 حذف خودکار: <b>{$s['delete_seconds']}</b> ثانیه\n";
    $text .= "🔒 عضویت اجباری: " . (!empty($s['force_join']) ? 'روشن' : 'خاموش') . "\n";
    $text .= "🛡 محافظت فایل: " . (!empty($s['protect_content']) ? 'روشن' : 'خاموش') . "\n";
    $text .= "🔗 لینک‌ها: " . count(Links::all($botId)) . "\n";

    $rows = [
        [['text' => '⏱ زمان حذف', 'callback_data' => 'adm_bsec_' . $botId, 'style' => gs('admin') ?: null]],
        [['text' => (!empty($s['force_join']) ? '🔴 خاموش کردن' : '🟢 روشن کردن') . ' عضویت اجباری',
          'callback_data' => 'adm_bfj_' . $botId]],
        [['text' => (!empty($s['protect_content']) ? '🔴 خاموش کردن' : '🟢 روشن کردن') . ' محافظت',
          'callback_data' => 'adm_bpc_' . $botId]],
        [['text' => '🔴 حذف ربات', 'callback_data' => 'adm_bdel_' . $botId, 'style' => gs('admin') ?: null]],
        [['text' => UT('back'), 'callback_data' => 'adm_bots', 'style' => gs('admin') ?: null]],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

// ============================================================
// ✏️ ویرایشگر داخل ربات — دکمه‌ها، متن‌ها، رنگ‌ها
// ============================================================

/** فهرست همه متن‌های قابل ویرایش با برچسب فارسی */
function textLabels() {
    return [
        'welcome' => '👋 خوش‌آمد', 'account' => '👤 حساب کاربری',
        'trust' => '💚 اعتماد', 'support' => '📞 سربرگ پشتیبانی',
        'referral' => '👥 زیرمجموعه', 'topup' => '➕ افزایش موجودی',
        'buy_head' => '🛒 سربرگ محصولات', 'buy_empty' => '🛒 محصولی نیست',
        'orders_head' => '📊 سربرگ سفارش‌ها', 'orders_empty' => '📊 سفارشی نیست',
        'pay_info' => '💳 اطلاعات پرداخت', 'receipt_ask' => '🧾 درخواست رسید',
        'receipt_ok' => '✅ رسید ثبت شد', 'approved' => '✅ تایید سفارش',
        'rejected' => '❌ رد سفارش', 'no_balance' => '❌ موجودی کم',
        'banned' => '🚫 کاربر مسدود',
    ];
}

function uiTextLabels() {
    return [
        'back' => 'بازگشت', 'home' => 'منوی اصلی', 'cancel' => 'انصراف',
        'confirm' => 'تایید', 'reject' => 'رد', 'panel' => 'پنل',
        'buy' => 'خرید', 'redeliver' => 'دریافت مجدد', 'full' => 'تکمیل ظرفیت',
        'receipt' => 'ارسال رسید', 'wallet_pay' => 'پرداخت از کیف پول',
        'direct_pay' => 'پرداخت مستقیم', 'topup' => 'افزایش موجودی',
        'my_orders' => 'سفارش‌های من', 'enter_bot' => 'ورود به ربات',
        'get_link' => 'دریافت لینک', 'open' => 'باز کردن', 'hide_menu' => 'بستن منو',
    ];
}

function glassRoleLabels() {
    return [
        'buy' => '🛒 خرید محصول', 'confirm' => '✅ تایید', 'cancel' => '↩️ انصراف',
        'reject' => '🗑 رد و حذف', 'nav' => '◀️ بازگشت و منو', 'info' => 'ℹ️ اطلاعات',
        'admin' => '👑 پنل', 'link' => '🔗 لینک محتوا', 'support' => '📞 پشتیبانی',
        'join' => '📢 کانال', 'joined' => '✅ عضو شدم', 'upload' => '📤 آپلود',
    ];
}

function nextStyle($cur) {
    $keys = array_keys(styleMap());
    $i = array_search($cur, $keys, true);
    return $keys[(($i === false ? 0 : $i) + 1) % count($keys)];
}

/** 🎨 فهرست دکمه‌های منو */
function edButtons($chatId, $msgId) {
    $c = cfg();
    $text  = "🎨 <b>ویرایش دکمه‌ها</b>\n\n";
    $text .= "حالت: <b>" . ($c['ui']['mode'] === 'glass' ? 'شیشه‌ای' : 'منو') . "</b>\n";
    $text .= "کیبورد چسبان: <b>" . (!empty($c['ui']['persistent']) ? 'روشن' : 'خاموش') . "</b>\n\n";
    $text .= "روی هر دکمه بزنید تا ویرایشش کنید:";

    $rows = [];
    foreach ($c['buttons'] as $id => $b) {
        $col = styleMap()[$b['color'] ?? 'none'] ?? '';
        $rows[] = [btnCb((!empty($b['on']) ? '✅ ' : '❌ ') . btnLabel($b, false) . '  ' . mb_substr($col, 0, 2),
                         'eb_' . $id, 'info')];
    }
    $rows[] = [
        btnCb($c['ui']['mode'] === 'glass' ? '🔄 به منو' : '🔄 به شیشه‌ای', 'ebmode', 'admin'),
        btnCb('📐 چیدمان', 'eblay', 'admin'),
    ];
    $rows[] = [
        btnCb(!empty($c['ui']['persistent']) ? '📌 چسبان: روشن' : '📌 چسبان: خاموش', 'ebpin', 'admin'),
        btnCb('➕ دکمه جدید', 'ebnew', 'confirm'),
    ];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🎨 ویرایش یک دکمه */
function edButton($chatId, $msgId, $id) {
    $b = cfg()['buttons'][$id] ?? null;
    if (!$b) { edButtons($chatId, $msgId); return; }

    $text  = "🎨 <b>ویرایش دکمه</b>\n\n";
    $text .= "نمایش: " . h(btnLabel($b, true)) . "\n";
    $text .= "متن: <code>" . h($b['text']) . "</code>\n";
    $text .= "ایموجی: " . h($b['emoji'] ?: '—') . "\n";
    $text .= "رنگ: " . (styleMap()[$b['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "✨ پریمیوم: " . (!empty($b['icon']) ? '<code>' . h($b['icon']) . '</code>' : '—') . "\n";
    $text .= "ردیف: " . (int)($b['row'] ?? 0) . "  |  ترتیب: " . (int)($b['order'] ?? 0) . "\n";
    $text .= "وضعیت: " . (!empty($b['on']) ? '✅ روشن' : '❌ خاموش');
    if (!empty($b['action'])) {
        $text .= "\nنوع: " . h(['text' => 'متن', 'url' => 'لینک', 'product' => 'محصول'][$b['action']] ?? $b['action']);
    }

    $rows = [
        [btnCb('✏️ متن', 'ebt_' . $id, 'admin'), btnCb('😀 ایموجی', 'ebe_' . $id, 'admin')],
        [btnCb('🎨 رنگ', 'ebc_' . $id, 'admin'), btnCb('✨ پریمیوم', 'ebi_' . $id, 'admin')],
        [btnCb('📐 ردیف', 'ebr_' . $id, 'admin'), btnCb('🔢 ترتیب', 'ebo_' . $id, 'admin')],
        [btnCb(!empty($b['on']) ? '❌ خاموش کن' : '✅ روشن کن', 'ebx_' . $id, 'info')],
    ];
    if (!empty($b['action'])) {
        $rows[] = [btnCb('📝 مقدار', 'ebv_' . $id, 'admin'), btnCb('🗑 حذف دکمه', 'ebd_' . $id, 'reject')];
    }
    $rows[] = [btnCb(UT('back'), 'ebuttons', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 📝 فهرست متن‌ها */
function edTexts($chatId, $msgId, $page = 0) {
    $labels = textLabels();
    $keys = array_keys($labels);
    $per = 8;
    $slice = array_slice($keys, $page * $per, $per);

    $rows = [];
    foreach ($slice as $k) $rows[] = [btnCb($labels[$k], 'et_' . $k, 'info')];

    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'ets_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'ets_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb('🔤 متن دکمه‌های ثابت', 'euis', 'admin')];
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "📝 <b>ویرایش متن‌های ربات</b>\n\nمتن را انتخاب کنید. هنگام فرستادن متن جدید می‌توانید " .
        "<b>ایموجی پریمیوم</b>، نقل‌قول و قالب‌بندی بگذارید — همه حفظ می‌شود.",
        inlineKb($rows));
}

/** 📝 نمایش یک متن */
function edText($chatId, $msgId, $key) {
    $labels = textLabels();
    if (!isset($labels[$key])) { edTexts($chatId, $msgId); return; }
    $cur = cfg()['texts'][$key] ?? '';
    $isQuoted = str_starts_with(trim($cur), '<blockquote');

    $text  = "📝 <b>" . h($labels[$key]) . "</b>\n\n";
    $text .= "<b>پیش‌نمایش:</b>\n" . ($cur !== '' ? $cur : '<i>خالی</i>') . "\n\n";
    $text .= "<b>کد:</b>\n<code>" . h(mb_substr($cur, 0, 700)) . "</code>";

    $rows = [
        [btnCb('✏️ تغییر متن', 'ete_' . $key, 'confirm')],
        [btnCb($isQuoted ? '❝ حذف نقل‌قول' : '❝ نقل‌قول', 'etq_' . $key, 'admin'),
         btnCb('❝ نقل‌قول بازشو', 'etx_' . $key, 'admin')],
        [btnCb('♻️ بازگردانی پیش‌فرض', 'etr_' . $key, 'reject')],
        [btnCb(UT('back'), 'etexts', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🔤 متن دکمه‌های ثابت */
function edUiTexts($chatId, $msgId, $page = 0) {
    $labels = uiTextLabels();
    $keys = array_keys($labels);
    $per = 9;
    $slice = array_slice($keys, $page * $per, $per);

    $rows = [];
    foreach (array_chunk($slice, 2) as $pair) {
        $line = [];
        foreach ($pair as $k) $line[] = btnCb(UT($k), 'eu_' . $k, 'info');
        $rows[] = $line;
    }
    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'eus_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'eus_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'etexts', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🔤 <b>متن دکمه‌های ثابت</b>\n\nمتن دکمه‌هایی مثل «بازگشت»، «انصراف» و «تایید».\n" .
        "می‌توانید ایموجی پریمیوم هم بگذارید.",
        inlineKb($rows));
}

/** 💠 رنگ دکمه‌های شیشه‌ای */
function edGlass($chatId, $msgId) {
    $c = cfg()['glass_colors'];
    $rows = [];
    foreach (glassRoleLabels() as $role => $lbl) {
        $rows[] = [btnCb($lbl . ' — ' . (styleMap()[$c[$role] ?? 'none'] ?? ''), 'egc_' . $role, 'info')];
    }
    $rows[] = [btnCb(UT('back'), 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "💠 <b>رنگ دکمه‌های شیشه‌ای</b>\n\nروی هرکدام بزنید تا رنگش عوض شود:", inlineKb($rows));
}

// ============================================================
// 🎬 ربات مادر — پردازش
// ============================================================

function masterHandle($update) {

    // ---------------- ربات مادر در کانالی ادمین شد ----------------
    if (isset($update['my_chat_member'])) {
        handleMasterChatMember($update['my_chat_member']);
        return;
    }

    // ---------------- Callback ----------------
    if (isset($update['callback_query'])) {
        $cb     = $update['callback_query'];
        $uid    = (int)$cb['from']['id'];
        $chatId = $cb['message']['chat']['id'] ?? $uid;
        $msgId  = $cb['message']['message_id'] ?? null;
        $data   = $cb['data'] ?? '';
        $uname  = $cb['from']['username'] ?? '';
        $fname  = $cb['from']['first_name'] ?? '';
        $cbId   = $cb['id'];
        $isAdmin = ($uid === ADMIN_ID);

        $u = getUser($uid);
        if ($u && !empty($u['banned'])) { answerCb(BOT_TOKEN, $cbId, T('banned'), true); return; }

        // --- دکمه‌های منو در حالت شیشه‌ای ---
        if (str_starts_with($data, 'menu_')) {
            $act = substr($data, 5);
            answerCb(BOT_TOKEN, $cbId);
            runMenuAction($act, $uid, $chatId, $uname, $fname);
            return;
        }

        if ($data === 'cancel') {
            clearState($uid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ لغو شد.");
            return;
        }

        // --- پشتیبانی متنی ---
        if (str_starts_with($data, 'sup_')) {
            $i = (int)substr($data, 4);
            $m = cfg()['support_methods'][$i] ?? null;
            answerCb(BOT_TOKEN, $cbId);
            if (!$m) return;
            if ($m['type'] === 'ticket') {
                setState($uid, 'ticket');
                sendMsg(BOT_TOKEN, $chatId, "🎫 پیام خود را بنویسید تا برای پشتیبانی ارسال شود:",
                    inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
                return;
            }
            if ($m['type'] === 'phone') {
                sendMsg(BOT_TOKEN, $chatId, "☎️ <b>" . h($m['label']) . "</b>\n\n<code>" . h($m['value'] ?: 'تنظیم نشده') . "</code>");
                return;
            }
            sendMsg(BOT_TOKEN, $chatId, $m['value'] ?: 'متنی تنظیم نشده است.');
            return;
        }

        // --- خرید ---
        if (str_starts_with($data, 'full_')) { answerCb(BOT_TOKEN, $cbId, '🔴 ظرفیت تکمیل شده است.', true); return; }

        if (str_starts_with($data, 'redeliver_')) {
            answerCb(BOT_TOKEN, $cbId);
            $pid = substr($data, 10);
            if (Product::hasBought($pid, $uid)) deliverProduct($uid, $chatId, $pid);
            return;
        }

        if (str_starts_with($data, 'buy_')) {
            answerCb(BOT_TOKEN, $cbId);
            $pid = substr($data, 4);
            $p = Product::get($pid);
            if (!$p || empty($p['active'])) { sendMsg(BOT_TOKEN, $chatId, "❌ این محصول در دسترس نیست."); return; }
            if (Product::hasBought($pid, $uid)) { deliverProduct($uid, $chatId, $pid); return; }
            if (Product::isFull($p)) { sendMsg(BOT_TOKEN, $chatId, "🔴 ظرفیت این محصول تکمیل شده است."); return; }

            $bal = (float)(getUser($uid)['balance'] ?? 0);
            $rows = [];
            if (strtoupper($p['currency']) === 'تومان' || $p['currency'] === 'تومان') {
                $rows[] = [['text' => UT('wallet_pay') . ' (' . fmtNum($bal) . ')', 'callback_data' => 'wpay_' . $pid, 'style' => gs('buy') ?: null]];
            }
            $rows[] = [['text' => UT('direct_pay'), 'callback_data' => 'dpay_' . $pid, 'style' => gs('buy') ?: null]];
            $rows[] = [['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]];

            sendMsg(BOT_TOKEN, $chatId,
                "🛒 <b>" . h($p['name']) . "</b>\n💰 " . fmtNum($p['price']) . ' ' . h($p['currency']) .
                "\n\nروش پرداخت را انتخاب کنید:", inlineKb($rows));
            return;
        }

        if (str_starts_with($data, 'wpay_')) {
            $pid = substr($data, 5);
            $p = Product::get($pid);
            if (!$p) { answerCb(BOT_TOKEN, $cbId, 'محصول پیدا نشد', true); return; }
            $bal = (float)(getUser($uid)['balance'] ?? 0);
            if ($bal < (float)$p['price']) {
                answerCb(BOT_TOKEN, $cbId, '❌ موجودی کافی نیست', true);
                sendMsg(BOT_TOKEN, $chatId, T('no_balance', ['balance' => fmtNum($bal)]),
                    inlineKb([[['text' => UT('topup'), 'callback_data' => 'menu_topup', 'style' => gs('buy') ?: null]]]));
                return;
            }
            if (Product::isFull($p)) { answerCb(BOT_TOKEN, $cbId, 'ظرفیت تکمیل است', true); return; }

            addBalance($uid, -$p['price']);
            $oid = Order::create($uid, $uname, 'product', $pid, $p['price'], $p['currency']);
            Order::attachReceipt($oid, 'text', 'پرداخت از کیف پول');
            Order::approve($oid, ADMIN_ID);
            answerCb(BOT_TOKEN, $cbId, '✅ خرید انجام شد');
            sendMsg(BOT_TOKEN, $chatId, T('approved'));
            deliverProduct($uid, $chatId, $pid);
            announceSale(Order::get($oid));
            return;
        }

        if (str_starts_with($data, 'dpay_')) {
            answerCb(BOT_TOKEN, $cbId);
            $pid = substr($data, 5);
            $p = Product::get($pid);
            if (!$p) return;
            createOrderAndAsk($uid, $chatId, $uname, 'product', $pid, $p['price'], $p['currency'], '🛒 ' . h($p['name']));
            return;
        }

        if (str_starts_with($data, 'rcpt_')) {
            $oid = substr($data, 5);
            $o = Order::get($oid);
            if (!$o || (int)$o['user_id'] !== $uid) { answerCb(BOT_TOKEN, $cbId, 'سفارش نامعتبر', true); return; }
            if ($o['status'] !== Order::PENDING) { answerCb(BOT_TOKEN, $cbId, 'قبلا ثبت شده', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState($uid, 'receipt', ['order' => $oid]);
            sendMsg(BOT_TOKEN, $chatId, T('receipt_ask'), inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'ocancel_')) {
            clearState($uid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش لغو شد.");
            return;
        }

        // ---------------- ادمین ----------------
        // همه کال‌بک‌های مدیریتی — شامل ویرایشگر داخل ربات
        $adminPrefixes = ['aok_', 'ano_', 'adm_', 'eb', 'et', 'eg', 'eu'];
        $isAdminCb = false;
        foreach ($adminPrefixes as $pref) {
            if (str_starts_with($data, $pref)) { $isAdminCb = true; break; }
        }
        if ($isAdminCb) {
            if (!$isAdmin) { answerCb(BOT_TOKEN, $cbId, '🔒 دسترسی ندارید.', true); return; }
        } else {
            answerCb(BOT_TOKEN, $cbId);
            return;
        }

        if (str_starts_with($data, 'aok_')) {
            $oid = substr($data, 4);
            [$ok, $res] = Order::approve($oid, $uid);
            if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
            completeApprovedOrder($res);
            answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "✅ سفارش <code>" . h($oid) . "</code> تایید شد.",
                inlineKb([[['text' => UT('panel'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'ano_')) {
            $oid = substr($data, 4);
            [$ok, $res] = Order::reject($oid, $uid);
            if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
            sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
            answerCb(BOT_TOKEN, $cbId, 'رد شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش <code>" . h($oid) . "</code> رد شد.",
                inlineKb([[['text' => UT('panel'), 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
            return;
        }

        // ---------- ویرایشگر داخل ربات ----------
        if ($data === 'ebuttons') { answerCb(BOT_TOKEN, $cbId); edButtons($chatId, $msgId); return; }
        if ($data === 'etexts')   { answerCb(BOT_TOKEN, $cbId); edTexts($chatId, $msgId); return; }
        if ($data === 'eglass')   { answerCb(BOT_TOKEN, $cbId); edGlass($chatId, $msgId); return; }
        if ($data === 'euis')     { answerCb(BOT_TOKEN, $cbId); edUiTexts($chatId, $msgId); return; }
        if (str_starts_with($data, 'ets_')) { answerCb(BOT_TOKEN, $cbId); edTexts($chatId, $msgId, (int)substr($data, 4)); return; }
        if (str_starts_with($data, 'eus_')) { answerCb(BOT_TOKEN, $cbId); edUiTexts($chatId, $msgId, (int)substr($data, 4)); return; }
        if (str_starts_with($data, 'eb_'))  { answerCb(BOT_TOKEN, $cbId); edButton($chatId, $msgId, substr($data, 3)); return; }
        if (str_starts_with($data, 'et_'))  { answerCb(BOT_TOKEN, $cbId); edText($chatId, $msgId, substr($data, 3)); return; }

        if ($data === 'ebmode') {
            cfgSet(function (&$c) { $c['ui']['mode'] = ($c['ui']['mode'] === 'glass') ? 'menu' : 'glass'; });
            answerCb(BOT_TOKEN, $cbId, '✅ حالت عوض شد');
            edButtons($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی جدید:', mainKeyboard());
            return;
        }
        if ($data === 'ebpin') {
            cfgSet(function (&$c) { $c['ui']['persistent'] = empty($c['ui']['persistent']); });
            $on = !empty(cfg()['ui']['persistent']);
            answerCb(BOT_TOKEN, $cbId, $on ? 'چسبان روشن شد' : 'چسبان خاموش شد', true);
            edButtons($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId,
                $on ? '📌 کیبورد چسبان شد — کاربر نمی‌تواند ببنددش.'
                    : '✅ کیبورد قابل بستن شد — کاربر می‌تواند ببنددش.',
                mainKeyboard());
            return;
        }
        if ($data === 'eblay') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'ed_layout');
            sendMsg(BOT_TOKEN, $chatId,
                "📐 الگوی چیدمان را بفرستید.\n\nمثال: <code>2,1,1</code> یعنی ۲ دکمه بالا، بعد ۱، بعد ۱.\n" .
                "الگو تکرار نمی‌شود؛ دکمه‌های اضافه تک‌تک می‌آیند.",
                inlineKb([[btnCb(UT('cancel'), 'ebuttons', 'cancel')]]));
            return;
        }
        if ($data === 'ebnew') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'ed_newbtn');
            sendMsg(BOT_TOKEN, $chatId,
                "➕ متن دکمه جدید را بفرستید.\n\n(می‌توانید ایموجی پریمیوم هم بگذارید)",
                inlineKb([[btnCb(UT('cancel'), 'ebuttons', 'cancel')]]));
            return;
        }

        foreach ([['ebt_', 'ed_btext', '✏️ متن جدید دکمه را بفرستید (ایموجی پریمیوم مجاز است):'],
                  ['ebe_', 'ed_bemoji', '😀 ایموجی جدید را بفرستید (یا خط تیره برای حذف):'],
                  ['ebi_', 'ed_bicon', "✨ کد ایموجی پریمیوم را بفرستید.\nبا /emoji می‌گیرید. برای حذف خط تیره بفرستید."],
                  ['ebr_', 'ed_brow', '📐 شماره ردیف را بفرستید (۰ = خودکار):'],
                  ['ebo_', 'ed_border', '🔢 شماره ترتیب را بفرستید:'],
                  ['ebv_', 'ed_bvalue', '📝 مقدار دکمه را بفرستید (متن، آدرس، یا شناسه محصول):']] as $it) {
            [$pref, $act, $ask] = $it;
            if (str_starts_with($data, $pref)) {
                $bid = substr($data, strlen($pref));
                if (!isset(cfg()['buttons'][$bid])) { answerCb(BOT_TOKEN, $cbId, 'دکمه پیدا نشد', true); return; }
                answerCb(BOT_TOKEN, $cbId);
                setState(ADMIN_ID, $act, ['btn' => $bid]);
                sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnCb(UT('cancel'), 'eb_' . $bid, 'cancel')]]));
                return;
            }
        }

        if (str_starts_with($data, 'ebc_')) {
            $bid = substr($data, 4);
            cfgSet(function (&$c) use ($bid) {
                if (isset($c['buttons'][$bid])) $c['buttons'][$bid]['color'] = nextStyle($c['buttons'][$bid]['color'] ?? 'none');
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edButton($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'ebx_')) {
            $bid = substr($data, 4);
            cfgSet(function (&$c) use ($bid) {
                if (isset($c['buttons'][$bid])) $c['buttons'][$bid]['on'] = empty($c['buttons'][$bid]['on']);
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edButton($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'ebd_')) {
            $bid = substr($data, 4);
            if (!str_starts_with($bid, 'c_')) { answerCb(BOT_TOKEN, $cbId, 'فقط دکمه‌های ساخته‌شده حذف می‌شوند', true); return; }
            cfgSet(function (&$c) use ($bid) { unset($c['buttons'][$bid]); });
            answerCb(BOT_TOKEN, $cbId, 'حذف شد');
            edButtons($chatId, $msgId);
            return;
        }

        if (str_starts_with($data, 'ete_')) {
            $k = substr($data, 4);
            if (!isset(textLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'edit_text', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متن جدید را بفرستید.\n\n✨ ایموجی پریمیوم، نقل‌قول و قالب‌بندی همه حفظ می‌شود.",
                inlineKb([[btnCb(UT('cancel'), 'et_' . $k, 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'etq_') || str_starts_with($data, 'etx_')) {
            $exp = str_starts_with($data, 'etx_');
            $k = substr($data, 4);
            if (!isset(textLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $exp) {
                $t = trim($c['texts'][$k] ?? '');
                if (str_starts_with($t, '<blockquote')) {
                    $t = preg_replace('#^<blockquote[^>]*>#', '', $t);
                    $t = preg_replace('#</blockquote>$#', '', trim($t));
                    if ($exp) $t = '<blockquote expandable>' . trim($t) . '</blockquote>';
                } else {
                    $t = ($exp ? '<blockquote expandable>' : '<blockquote>') . $t . '</blockquote>';
                }
                $c['texts'][$k] = trim($t);
            });
            answerCb(BOT_TOKEN, $cbId, '❝ اعمال شد');
            edText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'etr_')) {
            $k = substr($data, 4);
            $d = defaultConfig()['texts'][$k] ?? null;
            if ($d === null) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $d) { $c['texts'][$k] = $d; });
            answerCb(BOT_TOKEN, $cbId, '♻️ بازگردانی شد');
            edText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'eu_')) {
            $k = substr($data, 3);
            if (!isset(uiTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'ed_uitext', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "🔤 متن جدید دکمه «" . h(uiTextLabels()[$k]) . "» را بفرستید.\n\nمقدار فعلی: " . h(UT($k)),
                inlineKb([[btnCb(UT('cancel'), 'euis', 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'egc_')) {
            $role = substr($data, 4);
            if (!isset(glassRoleLabels()[$role])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($role) { $c['glass_colors'][$role] = nextStyle($c['glass_colors'][$role] ?? 'none'); });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            edGlass($chatId, $msgId);
            return;
        }

        // منوهای ادمین
        if ($data === 'adm_home')   { answerCb(BOT_TOKEN, $cbId); admHome($chatId, $msgId); return; }
        if ($data === 'adm_orders') { answerCb(BOT_TOKEN, $cbId); admOrders($chatId, $msgId); return; }
        if ($data === 'adm_btns')   { answerCb(BOT_TOKEN, $cbId); admBtns($chatId, $msgId); return; }
        if ($data === 'adm_texts')  { answerCb(BOT_TOKEN, $cbId); admTexts($chatId, $msgId); return; }
        if ($data === 'adm_chans')  { answerCb(BOT_TOKEN, $cbId); admChans($chatId, $msgId); return; }
        if ($data === 'adm_bots')   { answerCb(BOT_TOKEN, $cbId); admBots($chatId, $msgId); return; }

        if ($data === 'adm_mode') {
            cfgSet(function (&$c) { $c['ui']['mode'] = ($c['ui']['mode'] === 'glass') ? 'menu' : 'glass'; });
            answerCb(BOT_TOKEN, $cbId, '✅ حالت عوض شد');
            admBtns($chatId, $msgId);
            sendMsg(BOT_TOKEN, $chatId, "منوی جدید:", mainKeyboard());
            return;
        }

        if (str_starts_with($data, 'adm_btog_')) {
            $id = substr($data, 9);
            cfgSet(function (&$c) use ($id) {
                if (isset($c['buttons'][$id])) $c['buttons'][$id]['on'] = empty($c['buttons'][$id]['on']);
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            admBtns($chatId, $msgId);
            return;
        }

        if (str_starts_with($data, 'adm_bcol_')) {
            $id = substr($data, 9);
            $keys = array_keys(colorMap());
            cfgSet(function (&$c) use ($id, $keys) {
                if (!isset($c['buttons'][$id])) return;
                $cur = array_search($c['buttons'][$id]['color'], $keys, true);
                $c['buttons'][$id]['color'] = $keys[(($cur === false ? 0 : $cur) + 1) % count($keys)];
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            admBtns($chatId, $msgId);
            return;
        }

        if (str_starts_with($data, 'adm_txt_')) {
            $key = substr($data, 8);
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'edit_text', ['key' => $key]);
            $cur = cfg()['texts'][$key] ?? '';
            sendMsg(BOT_TOKEN, $chatId,
                "📝 متن فعلی:\n\n<code>" . h($cur) . "</code>\n\nمتن جدید را بفرستید:",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if ($data === 'adm_chadd') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'chan_add');
            sendMsg(BOT_TOKEN, $chatId,
                "📢 آیدی کانال را بفرستید.\n\nمثال: <code>@mychannel</code> یا <code>-1001234567890</code>\n\n" .
                "⚠️ ربات‌های اپلودر باید در کانال <b>ادمین</b> باشند.",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'adm_chdel_')) {
            Channels::remove(substr($data, 10));
            answerCb(BOT_TOKEN, $cbId, 'حذف شد');
            admChans($chatId, $msgId);
            return;
        }

        if ($data === 'adm_addbot') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'bot_token');
            sendMsg(BOT_TOKEN, $chatId, "🤖 توکن ربات اپلودر را بفرستید:",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'adm_bot_'))  { answerCb(BOT_TOKEN, $cbId); admBotOne($chatId, $msgId, substr($data, 8)); return; }

        if (str_starts_with($data, 'adm_bfj_')) {
            $bid = substr($data, 8);
            $s = BotManager::settings($bid);
            BotManager::setSetting($bid, 'force_join', empty($s['force_join']));
            answerCb(BOT_TOKEN, $cbId, '✅');
            admBotOne($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'adm_bpc_')) {
            $bid = substr($data, 8);
            $s = BotManager::settings($bid);
            BotManager::setSetting($bid, 'protect_content', empty($s['protect_content']));
            answerCb(BOT_TOKEN, $cbId, '✅');
            admBotOne($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'adm_bsec_')) {
            $bid = substr($data, 9);
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'bot_sec', ['bot' => $bid]);
            sendMsg(BOT_TOKEN, $chatId, "⏱ چند ثانیه بعد فایل حذف شود؟ (عدد، مثلا 30)",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }
        if (str_starts_with($data, 'adm_bdel_')) {
            $bid = substr($data, 9);
            $b = BotManager::get($bid);
            if ($b) tg($b['token'], 'deleteWebhook', []);
            mutate('bots', function (&$a) use ($bid) { unset($a[$bid]); });
            answerCb(BOT_TOKEN, $cbId, 'حذف شد');
            admBots($chatId, $msgId);
            return;
        }

        if ($data === 'adm_bc') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'broadcast');
            sendMsg(BOT_TOKEN, $chatId, "📢 متن پیام همگانی را بفرستید:",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if ($data === 'adm_web' || $data === 'adm_wallets' || $data === 'adm_sup' || $data === 'adm_prods') {
            answerCb(BOT_TOKEN, $cbId);
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "🌐 <b>پنل وب</b>\n\nاین بخش‌ها در پنل وب هستند:\n\n" .
                "🛒 محصولات · 💳 کیف پول · 📞 پشتیبانی\n" .
                "🎯 سفارش ممبر · 🤝 ربات‌های شریک · 👥 رفرال · 👥 کاربران\n\n" .
                "آدرس: <code>admin_panel.php</code>",
                inlineKb([[btnCb(UT('back'), 'adm_home', 'nav')]]));
            return;
        }

        answerCb(BOT_TOKEN, $cbId);
        return;
    }

    // ---------------- Message ----------------
    if (!isset($update['message'])) return;

    $msg    = $update['message'];
    $uid    = (int)($msg['from']['id'] ?? 0);
    $chatId = $msg['chat']['id'] ?? $uid;
    $uname  = $msg['from']['username'] ?? '';
    $fname  = $msg['from']['first_name'] ?? '';
    $text   = trim($msg['text'] ?? '');
    if (!$uid) return;

    // /start [ref…]
    if (str_starts_with($text, '/start')) {
        $arg = trim(explode(' ', $text, 2)[1] ?? '');
        $ref = (str_starts_with($arg, 'ref')) ? (int)substr($arg, 3) : null;
        touchUser($uid, $uname, $fname, $ref);
        clearState($uid);
        showHome($uid, $chatId, $fname);
        hintHideOnce($uid, $chatId);
        return;
    }

    touchUser($uid, $uname, $fname);
    $u = getUser($uid);
    if ($u && !empty($u['banned'])) { sendMsg(BOT_TOKEN, $chatId, T('banned')); return; }

    if ($text === '/panel' || $text === '/admin') {
        if ($uid !== ADMIN_ID) { sendMsg(BOT_TOKEN, $chatId, "🔒 دسترسی ندارید."); return; }
        admHome($chatId);
        return;
    }
    if ($text === '/id')     { sendMsg(BOT_TOKEN, $chatId, "🆔 <code>{$uid}</code>"); return; }
    if ($text === '/emoji') {
        if ($uid !== ADMIN_ID) return;
        setState($uid, 'grab_emoji');
        sendMsg(BOT_TOKEN, $chatId,
            "✨ <b>گرفتن کد ایموجی پریمیوم</b>\n\n" .
            "یک پیام بفرستید که ایموجی‌های پریمیوم مورد نظرتان داخلش باشد.\n" .
            "کد هرکدام را به شما می‌دهم تا در پنل استفاده کنید.",
            inlineKb([[btnCb(UT('cancel'), 'cancel', 'cancel')]]));
        return;
    }
    if ($text === '/cancel') { clearState($uid); sendMsg(BOT_TOKEN, $chatId, "❌ لغو شد.", mainKeyboard()); return; }
    if ($text === '/menu')   { showHome($uid, $chatId, $fname); return; }
    if ($text === '/hide' || $text === UT('hide_menu')) {
        sendMsg(BOT_TOKEN, $chatId,
            "✅ منو بسته شد.\n\nبرای برگرداندنش /menu را بزنید.", removeKb());
        return;
    }

    // --- دکمه منو زده شد؟ ---
    $act = findMenuAction($text);
    if ($act) { clearState($uid); runMenuAction($act, $uid, $chatId, $uname, $fname); return; }

    // --- ادامه گفتگو ---
    $st = getState($uid);
    if (!$st) return;
    $action = $st['action'];
    $sd     = $st['data'] ?? [];

    if ($action === 'receipt') {
        $oid = $sd['order'] ?? '';
        $o = Order::get($oid);
        if (!$o || (int)$o['user_id'] !== $uid) { clearState($uid); return; }

        $type = null; $val = null;
        if (!empty($msg['photo'])) { $p = $msg['photo']; $type = 'photo'; $val = $p[count($p) - 1]['file_id']; }
        elseif ($text !== '')      { $type = 'text';  $val = $text; }

        if (!$type) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عکس رسید یا کد تراکنش بفرستید."); return; }
        if (!Order::attachReceipt($oid, $type, $val)) {
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "❌ این سفارش دیگر قابل ویرایش نیست.");
            return;
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, T('receipt_ok'), mainKeyboard());
        notifyAdminOrder($oid);
        return;
    }

    if ($action === 'topup_amount') {
        $amt = (float)str_replace(',', '', $text);
        if ($amt <= 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک عدد معتبر بفرستید."); return; }
        clearState($uid);
        createOrderAndAsk($uid, $chatId, $uname, 'topup', null, $amt, 'تومان', '➕ شارژ کیف پول');
        return;
    }

    if ($action === 'grab_emoji') {
        $ids = customEmojiIds($msg);
        if (!$ids) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ در این پیام ایموجی پریمیوم پیدا نشد. دوباره بفرستید.");
            return;
        }
        clearState($uid);
        $out = "✨ <b>کدهای ایموجی پریمیوم</b>\n\n";
        foreach ($ids as $id) {
            $out .= "<tg-emoji emoji-id=\"" . h($id) . "\">✨</tg-emoji>  <code>" . h($id) . "</code>\n";
        }
        $out .= "\nکد را کپی کنید و در پنل، فیلد «ایموجی پریمیوم» بگذارید.\n";
        $out .= "برای داخل متن‌ها هم می‌توانید بنویسید:\n";
        $out .= "<code>&lt;tg-emoji emoji-id=\"" . h($ids[0]) . "\"&gt;✨&lt;/tg-emoji&gt;</code>";
        sendMsg(BOT_TOKEN, $chatId, $out, mainKeyboard());
        return;
    }

    if ($action === 'ticket') {
        if ($text === '') return;
        clearState($uid);
        sendMsg(BOT_TOKEN, ADMIN_ID,
            "🎫 <b>تیکت جدید</b>\n\n👤 " . h($uname ? '@' . $uname : $fname) . " (<code>{$uid}</code>)\n\n" . h($text),
            inlineKb([[['text' => '💬 پاسخ', 'callback_data' => 'reply_' . $uid, 'style' => gs('admin') ?: null]]]));
        sendMsg(BOT_TOKEN, $chatId, "✅ پیام شما برای پشتیبانی ارسال شد.", mainKeyboard());
        return;
    }

    // ---- ادمین ----
    if ($uid !== ADMIN_ID) return;

    if ($action === 'edit_text') {
        $key = $sd['key'] ?? '';
        if (!array_key_exists($key, defaultConfig()['texts'])) { clearState($uid); return; }
        // با entity ذخیره می‌شود تا ایموجی پریمیوم و قالب‌بندی حفظ شود
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        cfgSet(function (&$c) use ($key, $html) { $c['texts'][$key] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ متن ذخیره شد.\n\n<b>پیش‌نمایش:</b>\n" . $html, mainKeyboard());
        return;
    }

    // ---- ویرایشگر دکمه‌ها ----
    if (str_starts_with($action, 'ed_b')) {
        $bid = $sd['btn'] ?? '';
        if (!isset(cfg()['buttons'][$bid])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnCb(UT('back'), 'eb_' . $bid, 'nav')]]);

        if ($action === 'ed_btext') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($bid, $plain, $ids) {
                $c['buttons'][$bid]['text'] = $plain;
                if ($ids) $c['buttons'][$bid]['icon'] = $ids[0];
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ متن دکمه شد: <b>" . h($plain) . "</b>" . ($ids ? "\n✨ ایموجی پریمیوم هم نشست." : ''),
                $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bemoji') {
            $em = ($plain === '-' || $plain === '—') ? '' : $plain;
            cfgSet(function (&$c) use ($bid, $em) { $c['buttons'][$bid]['emoji'] = $em; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ایموجی ذخیره شد.", $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bicon') {
            $ic = '';
            if ($ids) $ic = $ids[0];
            elseif (ctype_digit($plain)) $ic = $plain;
            elseif ($plain === '-' || $plain === '—') $ic = '';
            else { sendMsg(BOT_TOKEN, $chatId, "⚠️ یک ایموجی پریمیوم بفرستید، یا کد عددی، یا خط تیره."); return; }
            cfgSet(function (&$c) use ($bid, $ic) { $c['buttons'][$bid]['icon'] = $ic; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $ic ? "✅ ایموجی پریمیوم نشست." : "✅ حذف شد.", $back);
            return;
        }
        if ($action === 'ed_brow' || $action === 'ed_border') {
            if (!ctype_digit($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
            $f = ($action === 'ed_brow') ? 'row' : 'order';
            $v = (int)$plain;
            cfgSet(function (&$c) use ($bid, $f, $v) { $c['buttons'][$bid][$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            sendMsg(BOT_TOKEN, $chatId, '👇 منوی به‌روز:', mainKeyboard());
            return;
        }
        if ($action === 'ed_bvalue') {
            $html = msgHtml($msg);
            cfgSet(function (&$c) use ($bid, $html, $plain) {
                $c['buttons'][$bid]['value'] = (($c['buttons'][$bid]['action'] ?? '') === 'text') ? $html : $plain;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ مقدار ذخیره شد.", $back);
            return;
        }
    }

    if ($action === 'ed_layout') {
        if (!parseLayout($text)) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ چیدمان نامعتبر. مثال: <code>2,1,1</code>");
            return;
        }
        $map = applyLayoutToRows($text);
        cfgSet(function (&$c) use ($map, $text) {
            $c['ui']['layout'] = trim($text);
            foreach ($map as $b => $r) if (isset($c['buttons'][$b])) $c['buttons'][$b]['row'] = $r;
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ چیدمان اعمال شد.", mainKeyboard());
        return;
    }

    if ($action === 'ed_newbtn') {
        $plain = trim($msg['text'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $ids = customEmojiIds($msg);
        $nid = 'c_' . bin2hex(random_bytes(4));
        cfgSet(function (&$c) use ($nid, $plain, $ids) {
            $c['buttons'][$nid] = [
                'emoji' => '', 'text' => $plain, 'color' => 'none', 'dot' => '',
                'icon' => $ids ? $ids[0] : '', 'row' => 0, 'order' => 50,
                'on' => true, 'action' => 'text', 'value' => 'این متن را از پنل عوض کنید.',
            ];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ دکمه <b>" . h($plain) . "</b> ساخته شد.\n\nحالا متن، رنگ و ردیفش را تنظیم کنید:",
            inlineKb([[btnCb('⚙️ تنظیم دکمه', 'eb_' . $nid, 'admin')]]));
        return;
    }

    if ($action === 'ed_uitext') {
        $k = $sd['key'] ?? '';
        if (!isset(uiTextLabels()[$k])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        cfgSet(function (&$c) use ($k, $plain) { $c['ui_texts'][$k] = $plain; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد: <b>" . h($plain) . "</b>",
            inlineKb([[btnCb(UT('back'), 'euis', 'nav')]]));
        return;
    }

    if ($action === 'edit_btn_text') {
        $bid = $sd['btn'] ?? '';
        $html = msgHtml($msg);
        if (trim($html) === '') return;
        // متن دکمه نمی‌تواند HTML داشته باشد؛ فقط متن ساده + ایموجی پریمیوم جدا
        $plain = trim(strip_tags($msg['text'] ?? ''));
        $ids = customEmojiIds($msg);
        cfgSet(function (&$c) use ($bid, $plain, $ids) {
            if (!isset($c['buttons'][$bid])) return;
            if ($plain !== '') $c['buttons'][$bid]['text'] = $plain;
            if ($ids) $c['buttons'][$bid]['icon'] = $ids[0];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ دکمه ذخیره شد." . ($ids ? "\n✨ ایموجی پریمیوم روی دکمه نشست." : ''),
            mainKeyboard());
        return;
    }

    if ($action === 'chan_add') {
        $chat = $text;
        $r = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat]);
        if (empty($r['ok'])) {
            sendMsg(BOT_TOKEN, $chatId, "❌ کانال پیدا نشد: " . h($r['description'] ?? '') . "\nدوباره بفرستید.");
            return;
        }
        $title = $r['result']['title'] ?? $chat;
        $un    = $r['result']['username'] ?? '';
        $url   = $un ? "https://t.me/{$un}" : ($r['result']['invite_link'] ?? '');
        Channels::add($chat, $title, $url);
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ کانال «" . h($title) . "» اضافه شد.\n\n⚠️ ربات‌های اپلودر را در این کانال ادمین کنید.");
        return;
    }

    if ($action === 'bot_token') {
        if (!preg_match('/^\d{6,}:[A-Za-z0-9_\-]{30,}$/', $text)) {
            sendMsg(BOT_TOKEN, $chatId, "❌ فرمت توکن درست نیست."); return;
        }
        $me = tg($text, 'getMe', []);
        if (empty($me['ok'])) { sendMsg(BOT_TOKEN, $chatId, "❌ توکن معتبر نیست."); return; }
        $bot = BotManager::create($text, $me['result']['username']);

        $base = (isset($_SERVER['HTTP_HOST']))
            ? 'https://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/')
            : '';
        $hook = '';
        if ($base) {
            $url = $base . '/' . basename(__FILE__) . '?bot=' . $bot['id'];
            $r = tg($text, 'setWebhook', ['url' => $url, 'drop_pending_updates' => 'true']);
            $hook = !empty($r['ok']) ? "\n✅ وبهوک تنظیم شد." : "\n⚠️ وبهوک تنظیم نشد: " . h($r['description'] ?? '');
        }
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ ربات <b>@" . h($bot['username']) . "</b> اضافه شد." . $hook .
            "\n\nحالا داخل همان ربات فایل بفرستید تا لینک بسازد.",
            inlineKb([[['text' => '⚙️ تنظیمات ربات', 'callback_data' => 'adm_bot_' . $bot['id']]]]));
        return;
    }

    if ($action === 'bot_sec') {
        if (!ctype_digit($text)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
        BotManager::setSetting($sd['bot'], 'delete_seconds', max(5, (int)$text));
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ زمان حذف روی {$text} ثانیه تنظیم شد.");
        return;
    }

    if ($action === 'broadcast') {
        $html = msgHtml($msg);
        if (trim($html) === '') return;
        clearState($uid);
        $sent = 0; $fail = 0;
        foreach (load('users') as $u2) {
            if (!empty($u2['banned'])) continue;
            $r = sendMsg(BOT_TOKEN, $u2['telegram_id'], $html);
            if (!empty($r['ok'])) $sent++; else $fail++;
            usleep(50000);
        }
        sendMsg(BOT_TOKEN, $chatId, "📢 موفق: {$sent} | ناموفق: {$fail}");
        return;
    }
}

/**
 * پیام همگانی به کاربران ربات‌های اپلودر — هر ربات با توکن خودش می‌فرستد،
 * چون کاربر ممکن است فقط با ربات فرعی چت کرده باشد نه با ربات مادر.
 */
function broadcastToChildBots($text, $botIds = null) {
    $sent = 0; $fail = 0; $seen = [];
    foreach (BotManager::all() as $b) {
        if ($botIds !== null && !in_array($b['id'], $botIds, true)) continue;
        foreach (load('bots/' . $b['id'] . '/users') as $u) {
            $key = $b['id'] . ':' . $u['id'];
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $r = sendMsg($b['token'], $u['id'], $text);
            if (!empty($r['ok'])) $sent++; else $fail++;
            usleep(50000);
        }
    }
    return [$sent, $fail];
}

/**
 * وقتی ربات مادر در کانالی ادمین می‌شود، کانال خودکار ثبت شده و
 * برای همه ربات‌های اپلودر قابل استفاده می‌شود — چون بررسی عضویت
 * همیشه با توکن ربات مادر انجام می‌گیرد و ربات‌های اپلودر لازم نیست
 * اصلا عضو کانال باشند.
 */
function handleMasterChatMember($ev) {
    $chat = $ev['chat'] ?? [];
    $type = $chat['type'] ?? '';
    if (!in_array($type, ['channel', 'supergroup', 'group'], true)) return;

    $newStatus = $ev['new_chat_member']['status'] ?? '';
    $chatId    = $chat['id'] ?? null;
    $title     = $chat['title'] ?? (string)$chatId;
    $un        = $chat['username'] ?? '';
    if (!$chatId) return;

    if (in_array($newStatus, ['administrator', 'creator'], true)) {
        $url = $un ? "https://t.me/$un" : '';
        if (!$url) {
            $inv = tg(BOT_TOKEN, 'createChatInviteLink',
                      ['chat_id' => $chatId, 'name' => 'عضویت اجباری'], 8);
            if (!empty($inv['ok'])) $url = $inv['result']['invite_link'];
        }
        $existing = null;
        foreach (Channels::all() as $c) if ((string)$c['chat_id'] === (string)$chatId) $existing = $c;

        if ($existing) {
            mutate('channels', function (&$a) use ($existing, $title, $url) {
                if (!isset($a[$existing['id']])) return;
                $a[$existing['id']]['title'] = $title;
                if ($url) $a[$existing['id']]['url'] = $url;
                $a[$existing['id']]['on'] = true;
            });
            sendMsg(BOT_TOKEN, ADMIN_ID,
                "✅ کانال <b>" . h($title) . "</b> دوباره فعال شد.\n\nربات مادر در آن ادمین است.");
            return;
        }

        Channels::add($chatId, $title, $url);
        sendMsg(BOT_TOKEN, ADMIN_ID,
            "✅ <b>کانال جدید ثبت شد</b>\n\n📢 " . h($title) . "\n<code>" . h($chatId) . "</code>\n\n" .
            "به‌صورت پیش‌فرض برای <b>همه</b> ربات‌های اپلودر فعال است.\n" .
            "اگر می‌خواهید فقط برای یک ربات خاص باشد، از پنل → 📢 کانال‌ها تنظیمش کنید.\n\n" .
            "ℹ️ ربات‌های اپلودر لازم نیست در این کانال عضو یا ادمین باشند.",
            inlineKb([[btnCb('📢 مدیریت کانال‌ها', 'adm_chans', 'admin')]]));
        return;
    }

    if (in_array($newStatus, ['left', 'kicked', 'member'], true)) {
        foreach (Channels::all() as $c) {
            if ((string)$c['chat_id'] !== (string)$chatId) continue;
            sendMsg(BOT_TOKEN, ADMIN_ID,
                "⚠️ <b>ربات مادر دیگر در کانال «" . h($c['title']) . "» ادمین نیست.</b>\n\n" .
                "تا وقتی ادمین نشود، قفل عضویت این کانال بسته می‌ماند و کاربران فایل نمی‌گیرند.");
            return;
        }
    }
}

/** اجرای عملیات یک دکمه منو */
function runMenuAction($act, $uid, $chatId, $uname, $fname) {
    // دکمه‌های سفارشی که ادمین ساخته
    $b = cfg()['buttons'][$act] ?? null;
    if ($b && !empty($b['action'])) {
        switch ($b['action']) {
            case 'text':
                sendMsg(BOT_TOKEN, $chatId, $b['value'] ?: '—');
                return;
            case 'url':
                sendMsg(BOT_TOKEN, $chatId, btnLabel($b),
                    inlineKb([[btnUrl(UT('open'), $b['value'], 'link')]]));
                return;
            case 'product':
                $p = Product::get($b['value'] ?? '');
                if (!$p) { sendMsg(BOT_TOKEN, $chatId, T('buy_empty')); return; }
                showOneProduct($uid, $chatId, $p);
                return;
        }
    }
    switch ($act) {
        case 'buy':      showProducts($uid, $chatId); break;
        case 'account':  showAccount($uid, $chatId); break;
        case 'topup':    startTopup($uid, $chatId); break;
        case 'referral': showReferral($uid, $chatId); break;
        case 'orders':   showOrders($uid, $chatId); break;
        case 'support':  showSupport($uid, $chatId); break;
        case 'trust':    sendMsg(BOT_TOKEN, $chatId, T('trust')); break;
        default:         showHome($uid, $chatId, $fname); break;
    }
}

// ============================================================
// 🤖 ربات اپلودر — عمومی، فقط آپلود و تحویل فایل
// ============================================================

function childState($botId, $uid) { $s = load('bots/' . $botId . '/states'); return $s[(string)$uid] ?? null; }
function childSetState($botId, $uid, $action, $data = []) {
    mutate('bots/' . $botId . '/states', function (&$s) use ($uid, $action, $data) {
        $s[(string)$uid] = ['action' => $action, 'data' => $data];
    });
}
function childClearState($botId, $uid) {
    mutate('bots/' . $botId . '/states', function (&$s) use ($uid) { unset($s[(string)$uid]); });
}

/** صفحه عضویت اجباری */
function showJoinGate($bot, $chatId, $missing, $code) {
    $s = BotManager::settings($bot['id']);
    $rows = [];
    $broken = false;
    foreach ($missing as $ch) {
        if (!empty($ch['url'])) $rows[] = [['text' => '📢 ' . $ch['title'], 'url' => $ch['url']]];
        if (!empty($ch['unverifiable'])) $broken = true;
    }
    $rows[] = [['text' => $s['joined_btn'], 'callback_data' => 'jchk_' . $code]];

    $text = $s['join_text'];
    if ($broken) {
        // ربات در کانال ادمین نیست — به ادمین خبر بده، کاربر را سردرگم نگذار
        $text .= "\n\n⚠️ اگر عضو هستید ولی خطا می‌گیرید، به پشتیبانی اطلاع دهید.";
        notifyChannelProblem($bot);
    }
    sendMsg($bot['token'], $chatId, $text, inlineKb($rows));
}

/** حداکثر هر ۶ ساعت یک بار به ادمین هشدار می‌دهد که ربات در کانال ادمین نیست */
function notifyChannelProblem($bot) {
    $flag = 'bots/' . $bot['id'] . '/health';
    $st = load($flag);
    if (!empty($st['warned_at']) && (time() - (int)$st['warned_at']) < 21600) return;
    save($flag, ['warned_at' => time()]);
    sendMsg(BOT_TOKEN, ADMIN_ID,
        "⚠️ <b>مشکل عضویت اجباری</b>\n\n<b>ربات مادر</b> نمی‌تواند عضویت کانال‌های مربوط به " .
        "@" . h($bot['username']) . " را بررسی کند.\n\n" .
        "فقط کافی است <b>ربات مادر</b> را در آن کانال ادمین کنید — ربات‌های اپلودر لازم نیست عضو کانال باشند.");
}

/** تحویل فایل‌های یک لینک + زمان‌بندی حذف */
function deliverLink($bot, $chatId, $uid, $code) {
    $s    = BotManager::settings($bot['id']);
    $link = Links::get($bot['id'], $code);

    if (!$link || empty($link['active'])) {
        sendMsg($bot['token'], $chatId, $s['expired_text']);
        return;
    }

    Links::hit($bot['id'], $code, 'delivered');

    $msgIds = [];
    foreach ($link['files'] as $f) {
        $r = sendFile($bot['token'], $chatId, $f['type'], $f['file_id'],
                      $f['caption'] ?? '', !empty($s['protect_content']));
        if (!empty($r['ok'])) $msgIds[] = $r['result']['message_id'];
        usleep(40000);
    }

    if (!$msgIds) { sendMsg($bot['token'], $chatId, $s['expired_text']); return; }

    $sec = max(5, (int)$s['delete_seconds']);
    $warn = sendMsg($bot['token'], $chatId, str_replace('{sec}', $sec, $s['warn_text']));
    $warnId = $warn['result']['message_id'] ?? null;

    // اگر سرور اجازه بدهد دقیقا سر وقت حذف می‌کند، وگرنه از صف استفاده می‌شود
    tryImmediateDelete($bot['id'], $chatId, $msgIds, $sec, $warnId);
    scheduleDelete($bot['id'], $chatId, $msgIds, $sec, $warnId);
}

function childHandle($botId, $update) {
    $bot = BotManager::get($botId);
    if (!$bot || empty($bot['active'])) return;
    $token = $bot['token'];
    $s     = BotManager::settings($botId);

    // ---------- Callback ----------
    if (isset($update['callback_query'])) {
        $cb     = $update['callback_query'];
        $uid    = (int)$cb['from']['id'];
        $chatId = $cb['message']['chat']['id'] ?? $uid;
        $msgId  = $cb['message']['message_id'] ?? null;
        $data   = $cb['data'] ?? '';
        $cbId   = $cb['id'];
        $isOwner = BotManager::isManager($botId, $uid);

        if (str_starts_with($data, 'jchk_')) {
            $code = substr($data, 5);
            $creditable = [];
            if (!empty($s['force_join'])) {
                [$missing, $creditable] = requiredMissing($uid, $botId);
                if ($missing) {
                    answerCb($token, $cbId, '❌ هنوز در همه کانال‌ها عضو نشده‌اید.', true);
                    return;
                }
            }
            foreach ($creditable as $cid) Campaign::credit($cid, $uid);
            answerCb($token, $cbId, '✅ عضویت تایید شد');
            if ($msgId) delMsg($token, $chatId, $msgId);
            deliverLink($bot, $chatId, $uid, $code);
            return;
        }

        if (!$isOwner) { answerCb($token, $cbId); return; }

        if ($data === 'u_single') {
            answerCb($token, $cbId);
            childSetState($botId, $uid, 'single');
            sendMsg($token, $chatId, "📤 فایل را بفرستید تا لینک بسازم.",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'u_cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }
        if ($data === 'u_batch') {
            answerCb($token, $cbId);
            childSetState($botId, $uid, 'batch', ['files' => []]);
            sendMsg($token, $chatId, "📤 فایل‌ها را یکی‌یکی بفرستید.\nدر پایان /done را بزنید.",
                inlineKb([[['text' => UT('cancel'), 'callback_data' => 'u_cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }
        if ($data === 'u_cancel') {
            childClearState($botId, $uid);
            answerCb($token, $cbId, 'لغو شد');
            childMenu($bot, $chatId);
            return;
        }
        if ($data === 'u_links') {
            answerCb($token, $cbId);
            $links = Links::all($botId);
            if (!$links) { sendMsg($token, $chatId, "🔗 هنوز لینکی نساخته‌اید."); return; }
            $text = "🔗 <b>لینک‌های ساخته‌شده</b>\n";
            foreach (array_slice(array_reverse($links, true), 0, 20, true) as $c => $l) {
                $text .= "\n📦 " . h($l['title'] ?: (count($l['files']) . ' فایل')) . "\n";
                $text .= "   " . Links::url($botId, $c) . "\n";
                $text .= "   👁 " . (int)$l['clicks'] . "  |  📥 " . (int)$l['delivered'] . "\n";
            }
            sendMsg($token, $chatId, $text);
            return;
        }
        if ($data === 'u_stats') {
            answerCb($token, $cbId);
            $links = Links::all($botId);
            $users = load('bots/' . $botId . '/users');
            $cl = 0; $dl = 0;
            foreach ($links as $l) { $cl += (int)$l['clicks']; $dl += (int)$l['delivered']; }
            editMsg($token, $chatId, $msgId,
                "📊 <b>آمار</b>\n\n👥 کاربران: " . count($users) . "\n🔗 لینک‌ها: " . count($links) .
                "\n👁 کلیک: {$cl}\n📥 تحویل: {$dl}\n🗑 حذف بعد از: {$s['delete_seconds']} ثانیه",
                inlineKb([[['text' => UT('back'), 'callback_data' => 'u_home', 'style' => gs('nav') ?: null]]]));
            return;
        }
        if ($data === 'u_home') { answerCb($token, $cbId); childMenu($bot, $chatId, $msgId); return; }

        answerCb($token, $cbId);
        return;
    }

    // ---------- Message ----------
    if (!isset($update['message'])) return;
    $msg    = $update['message'];
    $uid    = (int)($msg['from']['id'] ?? 0);
    $chatId = $msg['chat']['id'] ?? $uid;
    $uname  = $msg['from']['username'] ?? '';
    $fname  = $msg['from']['first_name'] ?? '';
    $text   = trim($msg['text'] ?? '');
    if (!$uid) return;

    $isOwner = BotManager::isManager($botId, $uid);
    botUserTouch($botId, $uid, $uname, $fname);

    // ---- /start [code] ----
    if (str_starts_with($text, '/start')) {
        $code = trim(explode(' ', $text, 2)[1] ?? '');

        if ($code === '') {
            sendMsg($token, $chatId, str_replace('{name}', h($fname), $s['start_text']));
            if ($isOwner) childMenu($bot, $chatId);
            return;
        }

        $link = Links::get($botId, $code);
        if (!$link || empty($link['active'])) { sendMsg($token, $chatId, $s['expired_text']); return; }

        Links::hit($botId, $code, 'clicks');

        // 🔒 قفل عضویت اجباری — کانال‌های ثابت + کمپین‌های فعال
        if (!empty($s['force_join'])) {
            [$missing, $creditable] = requiredMissing($uid, $botId);
            if ($missing) { showJoinGate($bot, $chatId, $missing, $code); return; }
            foreach ($creditable as $cid) Campaign::credit($cid, $uid);
        }

        deliverLink($bot, $chatId, $uid, $code);
        return;
    }

    if (!$isOwner) return;   // ربات عمومی است ولی فقط مالک می‌تواند آپلود کند

    if ($text === '/panel' || $text === '/menu') { childMenu($bot, $chatId); return; }

    $st = childState($botId, $uid);
    if (!$st) return;

    if ($st['action'] === 'single') {
        $f = extractFile($msg);
        if (!$f) { sendMsg($token, $chatId, "⚠️ یک فایل بفرستید."); return; }
        [$type, $fid, $name] = $f;
        $code = Links::create($botId, [[
            'type' => $type, 'file_id' => $fid, 'name' => $name,
            'caption' => $msg['caption'] ?? '',
        ]], $name);
        childClearState($botId, $uid);
        $url = Links::url($botId, $code);
        sendMsg($token, $chatId,
            "✅ <b>لینک ساخته شد</b>\n\n📦 " . h($name) . "\n\n🔗 <code>{$url}</code>\n\n" .
            "کاربر با کلیک روی این لینک، بعد از عضویت در کانال‌ها فایل را می‌گیرد و " .
            "بعد از <b>{$s['delete_seconds']} ثانیه</b> فایل حذف می‌شود.",
            inlineKb([[['text' => '📤 آپلود بعدی', 'callback_data' => 'u_single', 'style' => gs('upload') ?: null]],
                      [['text' => '◀️ منو', 'callback_data' => 'u_home', 'style' => gs('nav') ?: null]]]));
        return;
    }

    if ($st['action'] === 'batch') {
        $files = $st['data']['files'] ?? [];

        if ($text === '/done') {
            if (!$files) { sendMsg($token, $chatId, "⚠️ هیچ فایلی نفرستادید."); return; }
            $code = Links::create($botId, $files, count($files) . ' فایل');
            childClearState($botId, $uid);
            $url = Links::url($botId, $code);
            sendMsg($token, $chatId,
                "✅ <b>لینک گروهی ساخته شد</b>\n\n📦 " . count($files) . " فایل\n\n🔗 <code>{$url}</code>",
                inlineKb([[['text' => '◀️ منو', 'callback_data' => 'u_home', 'style' => gs('nav') ?: null]]]));
            return;
        }

        $f = extractFile($msg);
        if (!$f) { sendMsg($token, $chatId, "⚠️ فایل بفرستید یا /done بزنید."); return; }
        [$type, $fid, $name] = $f;
        $files[] = ['type' => $type, 'file_id' => $fid, 'name' => $name, 'caption' => $msg['caption'] ?? ''];
        childSetState($botId, $uid, 'batch', ['files' => $files]);
        sendMsg($token, $chatId, "✅ " . count($files) . " فایل ثبت شد. (/done برای پایان)");
        return;
    }
}

/** رنگ دکمه شیشه‌ای یک ربات اپلودر */
function bgs($botId, $role) {
    $s = BotManager::settings($botId);
    $c = $s['glass_colors'][$role] ?? 'none';
    return isStyle($c) ? $c : null;
}

function childMenu($bot, $chatId, $msgId = null) {
    $s   = BotManager::settings($bot['id']);
    $set = $s['buttons'] ?? [];

    $text = strtr($s['menu_text'] ?? '', [
        '{links}' => count(Links::all($bot['id'])),
        '{sec}'   => (int)$s['delete_seconds'],
        '{join}'  => !empty($s['force_join']) ? 'روشن' : 'خاموش',
        '{bot}'   => h($bot['username']),
    ]);
    if (trim($text) === '') $text = '🤖 پنل اپلودر';

    $items = [];
    foreach ($set as $id => $b) {
        if (empty($b['on'])) continue;
        $b['id'] = $id;
        $items[] = $b;
    }
    usort($items, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));

    $byRow = [];
    foreach ($items as $b) $byRow[(int)($b['row'] ?: 99)][] = $b;
    ksort($byRow);

    $rows = [];
    foreach ($byRow as $line) {
        $out = [];
        foreach ($line as $b) {
            $btn = ['text' => trim(($b['emoji'] ?? '') . ' ' . $b['text']),
                    'callback_data' => 'u_' . $b['id']];
            if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = $b['icon'];
            $out[] = $btn;
        }
        if ($out) $rows[] = $out;
    }

    if ($msgId) editMsg($bot['token'], $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg($bot['token'], $chatId, $text, inlineKb($rows));
}

// ============================================================
// 🌐 API عضویت اجباری — برای ربات‌های شریک با سورس مستقل
// ============================================================

function apiOut($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function handleApi($action) {
    $key = $_POST['key'] ?? $_GET['key'] ?? '';
    $p   = Partner::byKey($key);

    if (!$p)                 apiOut(['ok' => false, 'error' => 'invalid_key'], 401);
    if (empty($p['active'])) apiOut(['ok' => false, 'error' => 'partner_disabled'], 403);
    if (!Partner::rateOk($p['id'])) apiOut(['ok' => false, 'error' => 'rate_limited'], 429);

    // ---- فهرست کانال‌هایی که این شریک باید قفل کند ----
    if ($action === 'channels') {
        $out = [];
        foreach (Campaign::activeFor(null, $p['id']) as $c) {
            $out[] = ['title' => $c['title'], 'url' => $c['url'],
                      'remaining' => Campaign::remaining($c)];
        }
        apiOut(['ok' => true, 'channels' => $out]);
    }

    // ---- بررسی عضویت یک کاربر ----
    if ($action === 'check') {
        $userId = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
        if ($userId <= 0) apiOut(['ok' => false, 'error' => 'bad_user_id'], 400);

        [$missing, $creditable] = requiredMissing($userId, null, $p['id']);

        $allowed = empty($missing);
        if ($allowed) {
            // فقط وقتی همه شرط‌ها برقرار است، عضویت‌ها شمرده می‌شوند
            foreach ($creditable as $cid) Campaign::credit($cid, $userId);
        }
        Partner::bump($p['id'], $allowed);

        $list = [];
        foreach ($missing as $m) {
            $list[] = ['title' => $m['title'], 'url' => $m['url'] ?? '',
                       'unverifiable' => !empty($m['unverifiable'])];
        }
        apiOut([
            'ok' => true,
            'allowed' => $allowed,
            'missing' => $list,
            'message' => $allowed ? '' : 'برای ادامه، در کانال‌های زیر عضو شوید.',
        ]);
    }

    apiOut(['ok' => false, 'error' => 'unknown_action'], 400);
}

// ============================================================
// 🎯 ورودی
// ============================================================

if (defined('MEMBERSHIP_LIB_ONLY')) return;

if (isset($_GET['api'])) {
    try { handleApi((string)$_GET['api']); }
    catch (Throwable $e) {
        error_log('[api] ' . $e->getMessage());
        apiOut(['ok' => false, 'error' => 'server_error'], 500);
    }
}

// اجرای صف حذف — با cron یا در هر درخواست
if (isset($_GET['cron'])) {
    http_response_code(200);
    if (!hash_equals(CRON_KEY, (string)$_GET['cron'])) { echo 'forbidden'; exit; }
    echo 'deleted: ' . processDeleteQueue(200);
    exit;
}

processDeleteQueue(20);

$raw = file_get_contents('php://input');
$update = json_decode($raw, true);

http_response_code(200);

if (is_array($update)) {
    $botId = $_GET['bot'] ?? null;
    try {
        if ($botId) childHandle($botId, $update);
        else        masterHandle($update);
    } catch (Throwable $e) {
        error_log('[shop-bot] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }
}

echo json_encode(['ok' => true]);

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

if (!defined('TG_API_BASE')) define('TG_API_BASE', 'https://api.telegram.org');

function tg($token, $method, $data = [], $timeout = 20) {
    // درگاه تست — در حالت عادی وجود ندارد
    if (function_exists('__tgHook')) return __tgHook($token, $method, $data);
    $ch = curl_init(TG_API_BASE . "/bot{$token}/{$method}");
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

function sendFile($token, $chatId, $type, $fileId, $caption = '', $protect = false, $markup = null) {
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
    if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
    $res = tg($token, $method, $data);
    if (empty($res['ok']) && $markup && !is_string($markup) && isStyleError($res)) {
        $data['reply_markup'] = json_encode(stripStyles($markup));
        $res = tg($token, $method, $data);
    }
    return $res;
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
    // شناسه ایموجی تا ۱۹ رقم است؛ اگر کلید آرایه شود PHP آن را به int
    // تبدیل می‌کند و ممکن است دقتش برود. پس رشته نگهش می‌داریم.
    $out = [];
    foreach (($msg['entities'] ?? $msg['caption_entities'] ?? []) as $e) {
        if (($e['type'] ?? '') !== 'custom_emoji') continue;
        $id = (string)($e['custom_emoji_id'] ?? '');
        if ($id !== '' && !in_array($id, $out, true)) $out[] = $id;
    }
    return $out;
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
                 'speed_show_perday' => false,
                 'placeholder' => 'یک گزینه را انتخاب کنید…'],

        // 🧪 حالت تست — اجازه سفارش با مبلغ صفر، برای امتحان کردن کل مسیر
        'test_mode' => false,

        // 📋 لیست تعرفه‌ها — دکمه شیشه‌ای زیر بخش محصولات
        'tariff' => [
            'on'   => true,
            'text' => '📋 <b>لیست تعرفه‌ها</b>',   // {list} = جدول خودکار قیمت‌ها
            'btn'  => ['emoji' => '📋', 'text' => 'لیست تعرفه‌ها', 'color' => 'primary', 'icon' => ''],
            'back' => ['emoji' => '◀️', 'text' => 'برگشت', 'color' => 'nav', 'icon' => ''],
            'auto' => true,   // جدول قیمت‌ها خودکار زیر متن اضافه شود
        ],

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
            'did_admin' => '✅ ادمین کردم',
            'sup_direct'   => '💬 ارتباط مستقیم',
            'sup_indirect' => '📨 ارتباط غیر مستقیم',
        ],

        // ایموجی پریمیوم و رنگ هر دکمه ثابت — کلید = همان کلید ui_texts
        'ui_icons' => [],
        'ui_colors' => [],

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

            // جریان سفارش محصول
            'flow_link'    => "🔗 لطفا لینک خصوصی کانال/گروه خود را ارسال کنید.\n\nنمونه لینک:\n<code>https://t.me/+uiTRrjKIPVk0NDM9</code>\n\nاگر لینک خصوصی ندارید، لینک عمومی را بفرستید:\n<code>https://t.me/YourChannel</code>\n\n📍 دقت کنید لینک حتما شبیه موارد بالا باشد.",
            'flow_link_bad'=> "❌ لینک معتبر نیست.\nلینک باید با <code>https://t.me/</code> شروع شود.",
            'flow_qty'     => "👥 مقدار سفارش خود را به عدد و انگلیسی ارسال کنید.\n\n📊 حداقل تعداد برای ثبت سفارش <b>{min}</b> و حداکثر <b>{max}</b> می‌باشد.",
            'flow_qty_bad' => "❌ عدد وارد شده معتبر نیست.\nحداقل <b>{min}</b> و حداکثر <b>{max}</b>.",
            'flow_speed'   => "👉 لطفا سرعت تکمیل سفارش و سرعت ممبرگیری کانال را انتخاب کنید؟",
            'flow_rate'    => "💰 برای کانال فوق، نرخ محاسبه ممبر به شرح زیر می‌باشد:\n\n👥 هزینه هر کا ممبر: <b>{rate}</b> تومان\n\n📗 جهت ادامه دکمه زیر را بزنید.",
            'flow_addbot'  => "🤖 <b>افزودن ربات به کانال</b>\n\n➤ لطفا ربات را با <b>دسترسی کامل ادمین</b> به کانال خود اضافه کنید.\n➤ اطمینان حاصل کنید که تمام گزینه‌های ادمینی فعال باشند ✅\n\n⚠️ بدون ادمین بودن ربات، سفارش قابل انجام نیست.\n\n👇 بعد از ادمین کردن، دکمه زیر را بزنید.",
            'flow_admin_ok'=> "✅ ربات با موفقیت در کانال <b>{title}</b> ادمین شد.",
            'flow_admin_no'=> "❌ هنوز ربات را در کانال ادمین نکرده‌اید.\n\nربات <b>{bot}</b> را به کانال اضافه و ادمین کنید، بعد دوباره دکمه را بزنید.",
            'flow_invoice' => "📋 کاربر گرامی، جزئیات سفارش شما به شرح زیر است:\n\n📣 برای کانال: <code>{link}</code>\n👥 تعداد کاربر درخواستی: <b>{qty}</b> نفر\n⚙️ نوع درخواستی: {product}\n⚡️ سرعت: {speed}\n🚀 سرعت ممبرگیری: <b>{per_day}</b> نفر در روز\n⏳ زمان تقریبی تکمیل: <b>{eta}</b>\n💵 مبلغ به ازای هر {per} نفر: <b>{rate}</b>\n\n💳 مبلغ قابل پرداخت: <b>{total} {currency}</b>\n\n❗️ تمامی سفارش‌ها بصورت آنی ثبت شده و بصورت سیستمی انجام می‌گیرند.\n\n👇 درصورت تایید، دکمه زیر را بزنید.",
            'sup_ticket'   => "💬 <b>ارتباط غیر مستقیم</b>\n\nپیام خود را ارسال کنید، ادمین بررسی می‌کند.",
            'sup_sent'     => "✅ پیام شما برای پشتیبانی ارسال شد.\nبه زودی پاسخ داده می‌شود.",
            'orders_item'  => "{status}\n   {title}\n   💰 {amount} {currency}\n   🧾 <code>{id}</code>\n   📅 {date}\n",
            'quote_hint'   => "",
        ],

        // دو دکمه اصلی پشتیبانی
        'support_main' => [
            'direct' => ['emoji' => '💬', 'text' => 'ارتباط مستقیم', 'color' => 'success',
                         'icon' => '', 'value' => 'https://t.me/malakeBTC'],
            'indirect' => ['emoji' => '📨', 'text' => 'ارتباط غیر مستقیم', 'color' => 'primary',
                           'icon' => '', 'value' => ''],
        ],

        // روش‌های زیرمجموعه «ارتباط غیر مستقیم»
        'support_methods' => [
            ['on' => true,  'kind' => 'indirect', 'type' => 'ticket', 'emoji' => '🎫', 'label' => 'ارسال تیکت در ربات', 'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'url',    'emoji' => '📢', 'label' => 'کانال اطلاع‌رسانی',  'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'url',    'emoji' => '👥', 'label' => 'گروه گفتگو',         'value' => ''],
            ['on' => true,  'kind' => 'indirect', 'type' => 'text',   'emoji' => '❓', 'label' => 'سوالات متداول',      'value' => "❓ <b>سوالات متداول</b>\n\nهنوز متنی تنظیم نشده است."],
            ['on' => false, 'kind' => 'indirect', 'type' => 'phone',  'emoji' => '☎️', 'label' => 'تماس تلفنی',         'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '📱', 'label' => 'واتساپ',             'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '🌐', 'label' => 'وب‌سایت',            'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'text',   'emoji' => '📧', 'label' => 'ایمیل',              'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'url',    'emoji' => '✈️', 'label' => 'ایتا',               'value' => ''],
            ['on' => false, 'kind' => 'indirect', 'type' => 'text',   'emoji' => '📋', 'label' => 'قوانین',             'value' => ''],
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
            'info_text'       => "📊 <b>اطلاعات فایل</b>\n\n📦 {title}\n📁 تعداد فایل: <b>{files}</b>\n👁 بازدید: <b>{clicks}</b>\n📥 دانلود: <b>{delivered}</b>\n📅 {created}",
            'file_layout'     => '1',
            'file_buttons'    => [
                ['id' => 'f1', 'emoji' => '📥', 'text' => 'تعداد دانلود', 'color' => 'success',
                 'icon' => '', 'row' => 1, 'order' => 1, 'on' => true, 'action' => 'downloads', 'value' => ''],
                ['id' => 'f2', 'emoji' => '👁', 'text' => 'بازدید', 'color' => 'primary',
                 'icon' => '', 'row' => 1, 'order' => 2, 'on' => true, 'action' => 'views', 'value' => ''],
            ],
            'file_layout'     => '2',
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

/** ایموجی پریمیوم دکمه‌های ثابت */
function UI($key) { return cfg()['ui_icons'][$key] ?? ''; }

/** رنگ اختصاصی یک دکمه ثابت (اگر تنظیم شده باشد بر رنگ نقش اولویت دارد) */
function UC($key) {
    $c = cfg()['ui_colors'][$key] ?? '';
    return isStyle($c) ? $c : null;
}

/** دکمه ثابت آماده — متن + رنگ + ایموجی پریمیوم */
function btnUI($key, $data, $role = null) {
    $b = ['text' => UT($key), 'callback_data' => $data];
    $st = UC($key) ?: ($role ? gs($role) : null);
    if (isStyle($st)) $b['style'] = $st;
    $ic = (string)UI($key);
    if ($ic !== '') $b['icon_custom_emoji_id'] = $ic;
    return $b;
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
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
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
// 🖼 اسلات پیام — به‌جای پیام جدید، همان پیام قبلی را عوض می‌کند
// ============================================================
//
// دو اسلات جدا داریم تا بخش‌ها روی هم نیفتند:
//   'menu' → حساب کاربری، پیگیری سفارش، پشتیبانی، اعتماد، زیرمجموعه
//   'shop' → خرید محصول و کل جریان سفارش
// هر کدام پیام خودش را عوض می‌کند و آن یکی دست‌نخورده می‌ماند.

function slotGet($uid, $slot) {
    $u = getUser($uid);
    $v = $u['slots'][$slot] ?? null;
    return $v ? (int)$v : null;
}

function slotSet($uid, $slot, $mid) {
    mutate('users', function (&$a) use ($uid, $slot, $mid) {
        $k = (string)$uid;
        if (!isset($a[$k])) return;
        if (!is_array($a[$k]['slots'] ?? null)) $a[$k]['slots'] = [];
        if ($mid) $a[$k]['slots'][$slot] = (int)$mid;
        else unset($a[$k]['slots'][$slot]);
    });
}

function slotClear($uid, $slot = null) {
    mutate('users', function (&$a) use ($uid, $slot) {
        $k = (string)$uid;
        if (!isset($a[$k])) return;
        if ($slot === null) $a[$k]['slots'] = [];
        else unset($a[$k]['slots'][$slot]);
    });
}

/**
 * پیام یک اسلات را نشان می‌دهد: اگر پیام قبلی هست ویرایشش می‌کند،
 * وگرنه پیام تازه می‌فرستد و شناسه‌اش را نگه می‌دارد.
 */
function panelShow($uid, $chatId, $slot, $text, $markup = null, $replyTo = null) {
    $mid = slotGet($uid, $slot);

    // اگر کاربر دکمه منو را زده، جواب باید ریپلای همان پیام باشد:
    // پیام قبلی این اسلات حذف و پیام تازه به‌عنوان ریپلای فرستاده می‌شود
    // تا هم شلوغ نشود، هم معلوم باشد جواب کدام دکمه است.
    if ($replyTo) {
        if ($mid) delMsg(BOT_TOKEN, $chatId, $mid);
        $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup, [
            'reply_to_message_id' => $replyTo,
            'allow_sending_without_reply' => 'true',
        ]);
        $nid = $r['result']['message_id'] ?? null;
        slotSet($uid, $slot, $nid);
        return $nid;
    }

    if ($mid) {
        $data = [
            'chat_id' => $chatId, 'message_id' => $mid, 'text' => $text,
            'parse_mode' => 'HTML', 'disable_web_page_preview' => 'true',
        ];
        if ($markup) $data['reply_markup'] = is_string($markup) ? $markup : json_encode($markup);
        $r = tg(BOT_TOKEN, 'editMessageText', $data);

        if (empty($r['ok']) && $markup && !is_string($markup) && isStyleError($r)) {
            $data['reply_markup'] = json_encode(stripStyles($markup));
            $r = tg(BOT_TOKEN, 'editMessageText', $data);
        }
        if (!empty($r['ok'])) return $mid;

        // «تغییری نکرده» یعنی پیام سر جایش هست — همان را نگه دار
        if (str_contains(strtolower($r['description'] ?? ''), 'not modified')) return $mid;
    }

    $r = sendMsg(BOT_TOKEN, $chatId, $text, $markup);
    $nid = $r['result']['message_id'] ?? null;
    slotSet($uid, $slot, $nid);
    return $nid;
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

/** تنظیمات پیش‌فرض جریان سفارش — یک جا تا همه‌جا یکسان بماند */
function defaultFlow() {
    return [
        'on' => true, 'ask_link' => true, 'ask_qty' => true, 'ask_admin' => true,
        'min' => 1000, 'max' => 100000, 'per' => 1000,
        'speeds' => [
            ['id' => 's1', 'text' => 'کند',       'emoji' => '🐢', 'mult' => 1,   'per_day' => 500,  'color' => 'primary', 'icon' => '', 'on' => true],
            ['id' => 's2', 'text' => 'متوسط',     'emoji' => '🚶', 'mult' => 1.3, 'per_day' => 2000, 'color' => 'success', 'icon' => '', 'on' => true],
            ['id' => 's3', 'text' => 'سرعت بالا', 'emoji' => '⚡️', 'mult' => 1.7, 'per_day' => 8000, 'color' => 'danger',  'icon' => '', 'on' => true],
        ],
        'speed_layout' => '1',
    ];
}

/** 📢 تنظیمات پیش‌فرض گزارش خرید هر محصول */
function defaultReport() {
    return [
        'on'        => false,
        'chat_id'   => '',    // آیدی گروه/کانال — مثلا -1001234567890
        'thread_id' => 0,     // شماره تاپیک داخل گروه (۰ = بدون تاپیک)
        'text'      => "🎉 <b>فروش جدید</b>\n\n📦 {product}\n👥 {qty} ممبر\n⚡️ {speed}\n💰 {amount} {currency}\n\n🧾 <code>{code}</code>\n📅 {date}",
        'buttons'   => [
            ['text' => '🛒 ثبت سفارش',  'url' => '', 'color' => 'success', 'icon' => '', 'on' => true],
            ['text' => '📞 پشتیبانی',   'url' => '', 'color' => 'primary', 'icon' => '', 'on' => true],
        ],
    ];
}

function reportOf($p) {
    $r = defaultReport();
    foreach (($p['report'] ?? []) as $k => $v) {
        if ($k === 'buttons' && is_array($v)) {
            foreach ($v as $i => $b) {
                if (!isset($r['buttons'][$i])) $r['buttons'][$i] = ['text' => '', 'url' => '', 'color' => 'none', 'icon' => '', 'on' => true];
                foreach ($b as $bk => $bv) $r['buttons'][$i][$bk] = $bv;
            }
            continue;
        }
        $r[$k] = $v;
    }
    return $r;
}

/** تغییر تنظیمات گزارش یک محصول — چه دکمه‌ای، چه محصول واقعی */
function reportMutate($pid, callable $fn) {
    if ($bs = parseSubProductId($pid)) {
        subMutate($bs[0], $bs[1], function (&$x) use ($fn) {
            if (!is_array($x['report'] ?? null)) $x['report'] = defaultReport();
            $fn($x['report']);
        });
        return;
    }
    mutate('products', function (&$a) use ($pid, $fn) {
        if (!isset($a[$pid])) return;
        if (!is_array($a[$pid]['report'] ?? null)) $a[$pid]['report'] = defaultReport();
        $fn($a[$pid]['report']);
    });
}

/**
 * 🔘 خودِ دکمه شیشه‌ای = محصول
 * هیچ رکورد محصولی لازم نیست؛ قیمت و جریان روی خود دکمه ذخیره می‌شود.
 * شناسه: btn:<شناسه‌دکمه>|<شناسه‌زیردکمه>
 */
function subProductId($bid, $sid) { return 'btn:' . $bid . '|' . $sid; }

function parseSubProductId($id) {
    if (!is_string($id) || !str_starts_with($id, 'btn:')) return null;
    $rest = substr($id, 4);
    $pos  = strrpos($rest, '|');
    if ($pos === false) return null;
    return [substr($rest, 0, $pos), substr($rest, $pos + 1)];
}

/** ساخت محصولِ مجازی از روی یک زیردکمه (بدون نوشتن در products.json) */
function subProduct($bid, $sid, $sub = null) {
    $sub = $sub ?: findSub($bid, $sid);
    if (!$sub) return null;
    $flow = defaultFlow();
    foreach (($sub['flow'] ?? []) as $k => $v) $flow[$k] = $v;
    return [
        'id'        => subProductId($bid, $sid),
        'name'      => trim((string)($sub['text'] ?? 'محصول')),
        'desc'      => (string)($sub['desc'] ?? ''),
        'price'     => (float)($sub['price'] ?? 0),
        'currency'  => (string)($sub['currency'] ?? (cfg()['currency'] ?? 'تومان')),
        'limit'     => (int)($sub['limit'] ?? 0),
        'buyers'    => array_values($sub['buyers'] ?? []),
        'bot_id'    => $sub['bot_id'] ?? null,
        'link_code' => (string)($sub['link_code'] ?? ''),
        'emoji'     => (string)($sub['emoji'] ?? '💠'),
        'color'     => (string)($sub['color'] ?? 'none'),
        'icon'      => (string)($sub['icon'] ?? ''),
        'row'       => (int)($sub['row'] ?? 0),
        'order'     => (int)($sub['order'] ?? 99),
        'flow'      => $flow,
        'report'    => $sub['report'] ?? [],
        'active'    => !empty($sub['on']),
        'virtual'   => true,
        'btn'       => [$bid, $sid],
    ];
}

/** همهٔ زیردکمه‌هایی که نقش محصول دارند (برای گزارش و پنل) */
function saleButtons() {
    $out = [];
    foreach (cfg()['buttons'] as $bid => $b) {
        foreach ($b['subs'] ?? [] as $sub) {
            if (trim((string)($sub['text'] ?? '')) === '') continue;
            if (in_array($sub['action'] ?? 'text', ['section', 'url'], true)) continue;
            if (($sub['action'] ?? '') === 'text' && !isPlaceholder($sub['value'] ?? '')
                && trim((string)($sub['value'] ?? '')) !== '') continue;
            if (($sub['action'] ?? '') === 'product' && !empty($sub['value'])
                && Product::get($sub['value'])) continue;
            $sp = subProduct($bid, $sub['id'], $sub);
            if ($sp) $out[] = $sp;
        }
    }
    return $out;
}

class Product
{
    public static function all() { return load('products'); }

    public static function get($id) {
        if ($bs = parseSubProductId($id)) return subProduct($bs[0], $bs[1]);
        $a = load('products'); return $a[$id] ?? null;
    }

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
                // جریان سفارش — اگر روشن باشد از مشتری سوال می‌پرسد
                'flow' => defaultFlow(),
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
        if ($bs = parseSubProductId($pid)) {
            subMutate($bs[0], $bs[1], function (&$x) use ($uid) {
                $b = array_map('intval', $x['buyers'] ?? []);
                if (!in_array((int)$uid, $b, true)) { $b[] = (int)$uid; $x['buyers'] = array_values($b); }
            });
            return;
        }
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

    public static function create($userId, $username, $type, $productId, $amount, $currency, $meta = []) {
        $id = uid('or');
        mutate('orders', function (&$a) use ($id, $userId, $username, $type, $productId, $amount, $currency, $meta) {
            $a[$id] = [
                'id' => $id, 'user_id' => (int)$userId, 'username' => $username,
                'type' => $type,                 // product | topup
                'product_id' => $productId,
                'amount' => (float)$amount, 'currency' => $currency,
                'status' => self::PENDING, 'meta' => $meta,
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
        campaignFromOrder($o);   // 🎯 کانال مشتری خودکار در ربات‌های اپلودر قفل می‌شود
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

    public static function create($title, $chatId, $url, $target, $partners = [], $bots = [],
                                  $note = '', $orderId = null, $perDay = 0) {
        $id = uid('cm');
        mutate('campaigns', function (&$a) use ($id, $title, $chatId, $url, $target, $partners,
                                                $bots, $note, $orderId, $perDay) {
            $a[$id] = [
                'id' => $id, 'title' => $title, 'chat_id' => $chatId, 'url' => $url,
                'target' => max(0, (int)$target), 'joined' => [],
                'partners' => array_values($partners), 'bots' => array_values($bots),
                'note' => $note, 'active' => true,
                'order_id' => $orderId,          // سفارشی که این کمپین را ساخته
                'per_day'  => max(0, (int)$perDay),
                'fails'    => 0,                 // چند بار پشت‌سرهم قابل بررسی نبوده
                'paused_reason' => '',
                'created_at' => nowStr(), 'done_at' => null,
            ];
        });
        return self::get($id);
    }

    public static function forOrder($orderId) {
        foreach (self::all() as $c) if (($c['order_id'] ?? null) === $orderId) return $c;
        return null;
    }

    /** سهمیه امروز — بیشتر از per_day در یک روز تحویل نمی‌دهیم */
    public static function overDailyQuota($c) {
        $pd = (int)($c['per_day'] ?? 0);
        if ($pd <= 0) return false;
        $today = substr(nowStr(), 0, 10);
        return (($c['day'] ?? '') === $today) && ((int)($c['day_count'] ?? 0)) >= $pd;
    }

    /** کانالی که ربات دیگر نمی‌تواند بررسی کند را موقتا کنار بگذار */
    public static function noteFailure($id, $why) {
        $paused = mutate('campaigns', function (&$a) use ($id, $why) {
            if (!isset($a[$id])) return false;
            $a[$id]['fails'] = ((int)($a[$id]['fails'] ?? 0)) + 1;
            if ($a[$id]['fails'] >= 3 && !empty($a[$id]['active'])) {
                $a[$id]['active'] = false;
                $a[$id]['paused_reason'] = $why;
                return $a[$id]['title'] . '|' . $why;
            }
            return false;
        });
        if ($paused) {
            [$title, $w] = explode('|', $paused, 2);
            sendMsg(BOT_TOKEN, ADMIN_ID,
                "⚠️ <b>کمپین موقتا متوقف شد</b>\n\n" .
                "کانال: <b>" . h($title) . "</b>\n" .
                "دلیل: ربات نمی‌تواند عضویت را بررسی کند (" . h($w) . ")\n\n" .
                "ربات را دوباره در آن کانال ادمین کنید، بعد از پنل وب ← 🎯 کمپین‌ها روشنش کنید.");
        }
    }

    public static function noteSuccess($id) {
        mutate('campaigns', function (&$a) use ($id) {
            if (isset($a[$id]) && ((int)($a[$id]['fails'] ?? 0)) > 0) $a[$id]['fails'] = 0;
        });
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
            if (trim((string)($c['chat_id'] ?? '')) === '') continue;  // هنوز آیدی کانال ندارد
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
            $today = substr(nowStr(), 0, 10);
            if (($a[$id]['day'] ?? '') !== $today) { $a[$id]['day'] = $today; $a[$id]['day_count'] = 0; }
            $a[$id]['day_count'] = ((int)$a[$id]['day_count']) + 1;
            if ($t > 0 && count($j) >= $t) {
                $a[$id]['done_at'] = nowStr();
                $a[$id]['active']  = false;   // سهمیه پر شد — قفل برداشته می‌شود
            }
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
/** سقف عضویت اجباری هر ربات و تعداد ربات هر کمپین */
if (!defined('MAX_JOIN_PER_BOT'))  define('MAX_JOIN_PER_BOT', 4);
if (!defined('BOTS_PER_CAMPAIGN')) define('BOTS_PER_CAMPAIGN', 2);

/**
 * 🎯 پخش کردن کمپین بین ربات‌ها
 * هر کانال فقط در ۲ ربات قفل می‌شود، و ربات‌هایی انتخاب می‌شوند که کمترین بار را دارند.
 */
function assignCampaignBots($cid) {
    $bots = [];
    foreach (BotManager::all() as $b) if (!empty($b['active'])) $bots[] = $b['id'];
    if (!$bots) return [];

    // بار فعلی هر ربات = تعداد کمپین‌های فعالی که رویش نشسته
    $load = array_fill_keys($bots, 0);
    foreach (Campaign::all() as $c) {
        if (($c['id'] ?? '') === $cid) continue;
        if (empty($c['active']) || Campaign::isDone($c)) continue;
        $on = $c['bots'] ?? [];
        if (!$on) { foreach ($bots as $b) $load[$b]++; continue; }   // قدیمی: روی همه
        foreach ($on as $b) if (isset($load[$b])) $load[$b]++;
    }

    // کانال‌های ثابت هم جا می‌گیرند
    $fixed = [];
    foreach ($bots as $b) $fixed[$b] = count(Channels::all($b));

    // اول ربات‌هایی که هنوز به سقف نرسیده‌اند، بعد کم‌بارترین‌ها
    $free = array_values(array_filter($bots, fn($b) => $load[$b] + $fixed[$b] < MAX_JOIN_PER_BOT));
    $pool = $free ?: $bots;
    usort($pool, fn($x, $y) => ($load[$x] + $fixed[$x]) <=> ($load[$y] + $fixed[$y]));

    $pick = array_slice($pool, 0, BOTS_PER_CAMPAIGN);
    mutate('campaigns', function (&$a) use ($cid, $pick) {
        if (isset($a[$cid])) $a[$cid]['bots'] = array_values($pick);
    });
    return $pick;
}

/**
 * کانال‌هایی که کاربر باید عضو شود = کانال‌های ثابت + کمپین‌های فعال.
 *
 * - کمپین‌ها همیشه قفل می‌کنند (سفارش مشتری است، به تنظیم هر ربات ربط ندارد)
 * - کانال‌های ثابت فقط وقتی «عضویت اجباری» آن ربات روشن باشد
 * - در مجموع بیشتر از MAX_JOIN_PER_BOT کانال به کاربر نشان داده نمی‌شود
 *
 * برگشت: [missing[], creditable[]]
 */
function requiredMissing($userId, $botId = null, $partnerId = null) {
    $missing = [];
    $creditable = [];

    $fixedOn = true;
    if ($botId !== null) {
        $bs = BotManager::settings($botId);
        $fixedOn = !empty($bs['force_join']);
    }

    if ($botId !== null && $fixedOn) {
        foreach (Channels::missing($userId, $botId) as $m) $missing[] = $m;
    }

    // کمپین‌ها — قدیمی‌ترین اول تا سفارش‌ها به‌ترتیب تمام شوند
    $camps = Campaign::activeFor($botId, $partnerId);
    usort($camps, fn($x, $y) => strcmp((string)($x['created_at'] ?? ''), (string)($y['created_at'] ?? '')));

    foreach ($camps as $c) {
        if (Campaign::overDailyQuota($c)) continue;

        $res = Channels::isMemberOf($c['chat_id'], $userId);
        if (!$res['ok']) {
            // کانال مشتری است، نه ما — اگر ربات دسترسی ندارد کل ربات را قفل نمی‌کنیم
            Campaign::noteFailure($c['id'], $res['error'] ?? 'unknown');
            continue;
        }
        Campaign::noteSuccess($c['id']);
        if (!$res['member']) {
            if (count($missing) >= MAX_JOIN_PER_BOT) continue;   // سقف پر شد، بقیه بماند برای بعد
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

function showAccount($uid, $chatId, $extra = [], $replyTo = null) {
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
    $rows = [[btnUI('topup', 'menu_topup', 'buy')], [btnUI('my_orders', 'menu_orders', 'info')]];
    foreach ($extra as $r) $rows[] = $r;
    panelShow($uid, $chatId, 'menu', $text, inlineKb($rows), $replyTo);
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
    if (!empty($p['icon'])) $b['icon_custom_emoji_id'] = (string)$p['icon'];
    return $b;
}

function activeProducts($excludeBtn = null) {
    // محصولاتی که خودشان دکمه اختصاصی دارند دوباره در فهرست نمی‌آیند
    $covered = [];
    if ($excludeBtn !== null) {
        foreach ((cfg()['buttons'][$excludeBtn]['subs'] ?? []) as $sub) {
            if (empty($sub['on'])) continue;
            if (($sub['action'] ?? '') === 'product' && !empty($sub['value'])) $covered[$sub['value']] = true;
        }
    }
    $list = [];
    foreach (Product::all() as $p) {
        if (empty($p['active'])) continue;
        if (isset($covered[$p['id']])) continue;
        $list[] = $p;
    }
    usort($list, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));
    return $list;
}

function showProducts($uid, $chatId, $extra = [], $replyTo = null) {
    $prods = activeProducts('buy');
    if (!$prods && !$extra) {
        panelShow($uid, $chatId, 'shop', T('buy_empty'), $extra ? inlineKb($extra) : null, $replyTo);
        return;
    }

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
    foreach ($extra as $r) $rows[] = $r;   // دکمه‌های شیشه‌ای دلخواه، زیر محصولات

    // 📋 دکمه لیست تعرفه‌ها — همیشه آخرین ردیف
    $tf = cfg()['tariff'] ?? [];
    if (!empty($tf['on'])) {
        $b = ['text' => trim(($tf['btn']['emoji'] ?? '') . ' ' . ($tf['btn']['text'] ?? 'لیست تعرفه‌ها')),
              'callback_data' => 'tariff'];
        if (isStyle($tf['btn']['color'] ?? '')) $b['style'] = $tf['btn']['color'];
        if (!empty($tf['btn']['icon'])) $b['icon_custom_emoji_id'] = (string)$tf['btn']['icon'];
        $rows[] = [$b];
    }
    panelShow($uid, $chatId, 'shop', $text, inlineKb($rows), $replyTo);
}

/** 📋 جدول خودکار تعرفه‌ها */
function tariffTable() {
    $items = activeProducts('buy');
    foreach (saleButtons() as $sb) if (!empty($sb['active'])) $items[] = $sb;
    if (!$items) return '';

    $out = '';
    foreach ($items as $p) {
        $per = max(1, (int)($p['flow']['per'] ?? 1000));
        $out .= "\n" . trim(($p['emoji'] ?? '💠') . ' <b>' . h($p['name']) . '</b>') . "\n";
        if ((float)$p['price'] <= 0) { $out .= "   قیمت به‌زودی\n"; continue; }
        foreach ($p['flow']['speeds'] ?? [] as $sp) {
            if (isset($sp['on']) && empty($sp['on'])) continue;
            $rate = (float)$p['price'] * (float)($sp['mult'] ?? 1);
            $out .= '   ' . h(speedLabel($sp)) . ' — <b>' . fmtNum($rate) . '</b> ' .
                    h($p['currency']) . ' / ' . number_format($per) . ' نفر';
            $pd = (int)($sp['per_day'] ?? 0);
            if ($pd > 0) $out .= ' · 🚀 ' . number_format($pd) . '/روز';
            $out .= "\n";
        }
        $out .= '   👥 از ' . number_format((int)$p['flow']['min']) .
                ' تا ' . number_format((int)$p['flow']['max']) . "\n";
    }
    return $out;
}

/** 📋 نمایش لیست تعرفه‌ها — با دکمه برگشت به بخش خرید */
function showTariff($uid, $chatId, $replyTo = null) {
    $tf = cfg()['tariff'] ?? [];
    $text = (string)($tf['text'] ?? '📋 <b>لیست تعرفه‌ها</b>');
    $table = !empty($tf['auto']) ? tariffTable() : '';
    $text = str_contains($text, '{list}')
        ? str_replace('{list}', $table, $text)
        : $text . ($table !== '' ? "\n" . $table : '');

    $b = ['text' => trim(($tf['back']['emoji'] ?? '◀️') . ' ' . ($tf['back']['text'] ?? 'برگشت')),
          'callback_data' => 'menu_buy'];
    if (isStyle($tf['back']['color'] ?? '')) $b['style'] = $tf['back']['color'];
    elseif (gs('nav')) $b['style'] = gs('nav');
    if (!empty($tf['back']['icon'])) $b['icon_custom_emoji_id'] = (string)$tf['back']['icon'];

    panelShow($uid, $chatId, 'shop', $text, inlineKb([[$b]]), $replyTo);
}

/** نمایش یک محصول تکی — برای دکمه‌های سفارشی «محصول» */
function showOneProduct($uid, $chatId, $p) {
    $cnt = count($p['buyers']);
    $cap = ((int)$p['limit']) > 0 ? "{$cnt}/{$p['limit']}" : "{$cnt}/∞";
    $text  = ($p['emoji'] ?? '💠') . " <b>" . h($p['name']) . "</b>\n\n";
    if (!empty($p['desc'])) $text .= h($p['desc']) . "\n\n";
    $text .= "💰 قیمت: <b>" . fmtNum($p['price']) . ' ' . h($p['currency']) . "</b>\n";
    $text .= "👥 خریداران: {$cap}";
    panelShow($uid, $chatId, 'shop', $text,
        inlineKb([[productBtn($p, $uid)], [btnUI('back', 'menu_buy', 'nav')]]));
}

/** وضعیت واقعی سفارش — پرداخت + تحویل */
function orderStage($o) {
    if ($o['status'] === Order::REJECTED) return ['rejected', '❌ رد شده'];
    if ($o['status'] === Order::PENDING)  return ['pending',  '⏳ منتظر پرداخت'];
    if ($o['status'] === Order::REVIEW)   return ['review',   '🧾 رسید در حال بررسی'];

    // تایید شده — حالا تحویل
    $cmp = Campaign::forOrder($o['id']);
    if (!$cmp) return ['done', '✅ انجام شد'];
    if (!empty($cmp['done_at'])) return ['done', '✅ انجام شد'];
    if (empty($cmp['active']))   return ['paused', '⏸ متوقف — نیاز به بررسی'];
    return ['running', '🔄 در حال انجام'];
}

/** خط پیشرفت تحویل، اگر کمپین داشته باشد */
function orderProgress($o, $indent = '   ') {
    $cmp = Campaign::forOrder($o['id']);
    if (!$cmp) return '';
    $done = count($cmp['joined']);
    $tgt  = max(1, (int)$cmp['target']);
    $pct  = min(100, (int)round($done / $tgt * 100));
    $bars = (int)round($pct / 10);
    $out  = "\n" . $indent . '📊 ' . str_repeat('█', $bars) . str_repeat('░', 10 - $bars) .
            ' ' . $pct . '%  (' . number_format($done) . '/' . number_format((int)$cmp['target']) . ')';
    if ((int)($cmp['per_day'] ?? 0) > 0 && empty($cmp['done_at'])) {
        $left = max(0, (int)$cmp['target'] - $done);
        $days = (int)ceil($left / (int)$cmp['per_day']);
        $out .= "\n" . $indent . '⏳ باقی‌مانده: ' . number_format($left) . ' نفر' .
                ($days > 0 ? ' · حدود ' . $days . ' روز' : '');
    }
    if (!empty($cmp['done_at'])) $out .= "\n" . $indent . '🏁 تکمیل: ' . h($cmp['done_at']);
    return $out;
}

/** یک سفارش، به شکل چند خط */
function orderLines($o, $indent = '   ') {
    $t = $o['type'] === 'topup'
        ? '➕ شارژ کیف پول'
        : '🛒 ' . h(Product::get($o['product_id'])['name'] ?? '—');
    $m = $o['meta'] ?? [];
    if ($m) {
        if (!empty($m['link']))  $t .= "\n" . $indent . '📣 ' . h($m['link']);
        if (!empty($m['qty']))   $t .= "\n" . $indent . '👥 ' . number_format((int)$m['qty']) . ' نفر' .
                                       (!empty($m['speed']) ? ' · ' . h($m['speed']) : '');
        if (!empty($m['per_day'])) $t .= "\n" . $indent . '🚀 ' . number_format((int)$m['per_day']) . ' نفر در روز';
        if (!empty($m['eta']))   $t .= "\n" . $indent . '⏳ زمان تقریبی: ' . h($m['eta']);
        $t .= orderProgress($o, $indent);
    }
    return $t;
}

/**
 * 📊 پیگیری سفارش — دسته‌بندی‌شده
 * در حال انجام · منتظر پرداخت · انجام‌شده · رد شده
 */
/**
 * 📊 پیگیری سفارش — فقط با کد
 * فهرست بلند نشان نمی‌دهد؛ کاربر کد را می‌فرستد، وضعیت همان سفارش می‌آید.
 */
function showOrders($uid, $chatId, $extra = [], $replyTo = null) {
    $orders = Order::forUser($uid);
    setState($uid, 'track');

    $text  = "🔍 <b>پیگیری سفارش</b>\n\n";
    $text .= "کد پیگیری سفارش خود را بفرستید.\n";
    $text .= "<i>نمونه:</i> <code>or_tjwodm15a1a3</code>";

    $rows = [];
    // آخرین سفارش‌ها فقط به شکل دکمه — بدون متن اضافه
    foreach (array_slice($orders, 0, 5) as $o) {
        [, $label] = orderStage($o);
        $name = $o['type'] === 'topup' ? '➕ شارژ' : (Product::get($o['product_id'])['name'] ?? 'محصول');
        $rows[] = [btnCb(mb_substr($label . ' · ' . $name, 0, 40), 'trk_' . $o['id'], 'info')];
    }
    foreach ($extra as $r) $rows[] = $r;
    $rows[] = [btnUI('cancel', 'cancel', 'cancel')];

    panelShow($uid, $chatId, 'menu', $text, inlineKb($rows), $replyTo);
}

/** 🔍 وضعیت یک سفارش با کد پیگیری */
function showOrderStatus($uid, $chatId, $code, $replyTo = null) {
    $code = trim($code);
    $o = Order::get($code);

    if (!$o || (int)$o['user_id'] !== (int)$uid) {
        // شاید ادمین کد کاربر دیگری را زده
        if ($o && $uid === ADMIN_ID) {
            // اجازه دارد
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "❌ سفارشی با کد <code>" . h($code) . "</code> پیدا نشد.\n\n" .
                "کد پیگیری را از پیام ثبت سفارش کپی کنید، یا 📊 پیگیری سفارش را بزنید.",
                mainKeyboard());
            return false;
        }
    }

    [$stage, $label] = orderStage($o);

    $text  = "🔍 <b>وضعیت سفارش</b>\n\n";
    $text .= "🧾 کد پیگیری: <code>" . h($o['id']) . "</code>\n";
    $text .= "📊 وضعیت: <b>" . $label . "</b>\n\n";
    $text .= orderLines($o, '') . "\n\n";
    $text .= "💰 مبلغ: <b>" . fmtNum($o['amount']) . ' ' . h($o['currency']) . "</b>\n";
    $text .= "📅 ثبت: " . h($o['created_at']) . "\n";
    if (!empty($o['decided_at'])) $text .= "✅ تایید: " . h($o['decided_at']) . "\n";

    $hint = [
        'pending'  => "\n👇 مبلغ را واریز کنید و رسید را بفرستید.",
        'review'   => "\n⏳ رسید شما رسید؛ بعد از تایید، سفارش شروع می‌شود.",
        'running'  => "\n🔄 سفارش در حال انجام است. این صفحه را هر وقت خواستید ببینید.",
        'paused'   => "\n⚠️ برای ادامه، ربات باید در کانال شما ادمین باشد.",
        'done'     => "\n🎉 سفارش شما کامل شد. ممنون از خریدتان.",
        'rejected' => "\n❌ این سفارش رد شد. با پشتیبانی تماس بگیرید.",
    ][$stage] ?? '';
    $text .= $hint;

    $rows = [];
    if ($stage === 'pending')  $rows[] = [btnCb(UT('receipt'), 'rcpt_' . $o['id'], 'confirm')];
    if ($stage === 'rejected' || $stage === 'paused') $rows[] = [btnUI('support', 'menu_support', 'info')];
    $rows[] = [btnCb('📊 همه سفارش‌ها', 'menu_orders', 'nav')];

    panelShow($uid, $chatId, 'menu', $text, inlineKb($rows), $replyTo);
    return true;
}

function showReferral($uid, $chatId, $extra = [], $replyTo = null) {
    $u  = getUser($uid) ?: [];
    $me = tg(BOT_TOKEN, 'getMe', []);
    $un = $me['result']['username'] ?? '';
    $link = $un ? "https://t.me/{$un}?start=ref{$uid}" : '—';

    $refText = T('referral', [
        'percent'    => cfg()['referral']['percent'],
        'link'       => $link,
        'referrals'  => countReferrals($uid),
        'ref_earned' => fmtNum($u['ref_earned'] ?? 0),
    ]);
    panelShow($uid, $chatId, 'menu', $refText, $extra ? inlineKb($extra) : null, $replyTo);
}

function supMainBtn($which, $cb) {
    $m = cfg()['support_main'][$which] ?? [];
    $b = ['text' => trim(($m['emoji'] ?? '') . ' ' . ($m['text'] ?? ''))];
    if ($which === 'direct' && !empty($m['value'])) $b['url'] = $m['value'];
    else $b['callback_data'] = $cb;
    if (isStyle($m['color'] ?? '')) $b['style'] = $m['color'];
    if (!empty($m['icon'])) $b['icon_custom_emoji_id'] = (string)$m['icon'];
    return $b;
}

/** پشتیبانی — فقط دو دکمه: مستقیم و غیر مستقیم */
function showSupport($uid, $chatId, $extra = [], $replyTo = null) {
    $rows = [[supMainBtn('direct', 'sup_direct')], [supMainBtn('indirect', 'sup_list')]];
    foreach ($extra as $r) $rows[] = $r;
    panelShow($uid, $chatId, 'menu', T('support'), inlineKb($rows), $replyTo);
}

/** ارتباط غیر مستقیم = ارسال پیام برای ادمین */
function showSupportIndirect($uid, $chatId, $msgId = null) {
    setState($uid, 'ticket');
    panelShow($uid, $chatId, 'menu', T('sup_ticket'),
        inlineKb([[btnUI('cancel', 'menu_support', 'cancel')]]));
}

function startTopup($uid, $chatId, $replyTo = null, $need = 0, $total = 0, $bal = null) {
    setState($uid, 'topup_amount');
    $bal = $bal === null ? (float)(getUser($uid)['balance'] ?? 0) : (float)$bal;

    $rows = [];
    $text = T('topup');
    if ($need > 0) {
        $text  = "❌ <b>موجودی ناکافی!</b>\n\n";
        $text .= "💰 موجودی شما: <b>" . fmtNum($bal) . "</b> تومان\n";
        if ($total > 0) $text .= "💳 مبلغ نهایی: <b>" . fmtNum($total) . "</b> تومان\n";
        $text .= "➖ کمبود: <b>" . fmtNum($need) . "</b> تومان\n\n";
        $text .= "👇 مبلغی که می‌خواهید شارژ کنید را بفرستید،\nیا دکمه زیر را بزنید:";
        $rows[] = [btnCb('💳 شارژ ' . fmtNum($need) . ' تومان', 'topupq_' . (int)ceil($need), 'buy')];
        $r2 = (int)(ceil($need / 50000) * 50000);
        if ($r2 > $need) $rows[] = [btnCb('💳 شارژ ' . fmtNum($r2) . ' تومان', 'topupq_' . $r2, 'confirm')];
    }
    $rows[] = [btnUI('cancel', 'cancel', 'cancel')];
    panelShow($uid, $chatId, 'wallet', $text, inlineKb($rows), $replyTo);
}

/**
 * 💳 اطلاعات پرداخت مستقیم — سفارش هنوز ساخته نمی‌شود
 * سفارش دقیقا وقتی ثبت می‌شود که مشتری رسید بفرستد.
 */
function askDirectPay($uid, $chatId, $p, $amount, $meta = []) {
    [$method, $wallet] = walletFor($p['currency']);

    if (str_contains($wallet, 'تنظیم نشده')) {
        sendMsg(BOT_TOKEN, $chatId,
            "⚠️ روش پرداخت هنوز تنظیم نشده است.\nلطفا با پشتیبانی تماس بگیرید.");
        sendMsg(BOT_TOKEN, ADMIN_ID,
            "🔴 <b>فروش از دست رفت — مقصد پرداخت خالی است!</b>\n\n" .
            "کاربر <code>{$uid}</code> می‌خواست <b>" . fmtNum($amount) . ' ' . h($p['currency']) .
            "</b> بابت «" . h($p['name']) . "» بپردازد.\n\n" .
            "پنل وب ← ⚙️ تنظیمات ← شماره کارت / آدرس کیف پول را پر کنید.");
        return;
    }

    setState($uid, 'pay_wait', ['pid' => $p['id'], 'amount' => $amount,
                                'currency' => $p['currency'], 'meta' => $meta]);

    $t  = "💳 <b>اطلاعات پرداخت</b>\n\n";
    $t .= "🛒 " . h($p['name']) . "\n";
    if (!empty($meta['qty']))   $t .= "👥 " . number_format((int)$meta['qty']) . " نفر" .
                                      (!empty($meta['speed']) ? ' · ' . h($meta['speed']) : '') . "\n";
    $t .= "💰 مبلغ: <b>" . fmtNum($amount) . ' ' . h($p['currency']) . "</b>\n";
    $t .= "🏦 روش: " . h($method) . "\n\n";
    $t .= "💠 مقصد پرداخت:\n<code>" . h($wallet) . "</code>\n\n";
    $t .= "⚠️ <b>سفارش شما هنوز ثبت نشده.</b>\n";
    $t .= "بعد از واریز، دکمه «ارسال رسید» را بزنید تا سفارش ثبت شود.";

    panelShow($uid, $chatId, 'shop', $t, inlineKb([
        [btnCb(UT('receipt'), 'payrcpt', 'confirm')],
        [btnUI('cancel', 'cancel', 'cancel')],
    ]));
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

function createOrderAndAsk($uid, $chatId, $username, $type, $productId, $amount, $currency, $title, $meta = []) {
    $oid = Order::create($uid, $username, $type, $productId, $amount, $currency, $meta);

    // ✅ اول تایید ثبت سفارش، بعد اطلاعات پرداخت
    $ok  = "✅ <b>سفارش شما ثبت شد</b>\n\n";
    $ok .= "🧾 کد پیگیری: <code>" . h($oid) . "</code>\n";
    if (!empty($meta['link'])) $ok .= "📣 کانال: " . h($meta['link']) . "\n";
    if (!empty($meta['qty']))  $ok .= "👥 تعداد: <b>" . number_format((int)$meta['qty']) . "</b> نفر\n";
    if (!empty($meta['speed'])) $ok .= "⚡️ سرعت: " . h($meta['speed']) . "\n";
    if (!empty($meta['eta']))   $ok .= "⏳ زمان تقریبی: " . h($meta['eta']) . "\n";
    $ok .= "💳 مبلغ: <b>" . fmtNum($amount) . ' ' . h($currency) . "</b>\n";
    $ok .= "📊 وضعیت: <b>در انتظار پرداخت</b>\n\n";
    $ok .= "👇 مبلغ را واریز کنید و رسید را بفرستید.\n";
    $ok .= "با همین کد پیگیری هر وقت خواستید وضعیت را ببینید: /track " . h($oid);
    sendMsg(BOT_TOKEN, $chatId, $ok);

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

/** جریان سفارش: شروع */
function flowStart($uid, $chatId, $p) {
    $f = $p['flow'] ?? [];
    setState($uid, 'flow', ['pid' => $p['id'], 'step' => 'link', 'data' => []]);
    if (!empty($f['ask_link'])) {
        panelShow($uid, $chatId, 'shop', T('flow_link'), inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
        return;
    }
    flowNext($uid, $chatId, 'qty');
}

function flowNext($uid, $chatId, $step) {
    $st = getState($uid);
    if (!$st || $st['action'] !== 'flow') return;
    $sd = $st['data'];
    $p  = Product::get($sd['pid']);
    if (!$p) { clearState($uid); return; }
    $f  = $p['flow'] ?? [];
    $sd['step'] = $step;
    setState($uid, 'flow', $sd);

    if ($step === 'qty') {
        if (empty($f['ask_qty'])) { flowNext($uid, $chatId, 'speed'); return; }
        panelShow($uid, $chatId, 'shop',
            T('flow_qty', ['min' => number_format((int)$f['min']), 'max' => number_format((int)$f['max'])]),
            inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
        return;
    }

    if ($step === 'speed') {
        $speeds = [];
        foreach ($f['speeds'] ?? [] as $sp) if (!isset($sp['on']) || !empty($sp['on'])) $speeds[] = $sp;

        if (count($speeds) < 2) {
            $one = $speeds[0] ?? [];
            $sd['data']['speed']   = speedLabel($one);
            $sd['data']['mult']    = (float)($one['mult'] ?? 1);
            $sd['data']['per_day'] = (int)($one['per_day'] ?? 0);
            $sd['data']['eta']     = speedEta($one, (int)($sd['data']['qty'] ?? 0));
            setState($uid, 'flow', $sd);
            flowNext($uid, $chatId, 'admin');
            return;
        }

        $items = [];
        foreach ($speeds as $sp) {
            $b = ['text' => speedBtnLabel($sp), 'callback_data' => 'fsp_' . $sp['id']];
            if (isStyle($sp['color'] ?? '')) $b['style'] = $sp['color'];
            elseif (gs('buy')) $b['style'] = gs('buy');
            if (!empty($sp['icon'])) $b['icon_custom_emoji_id'] = (string)$sp['icon'];
            $items[] = $b;
        }
        $rows = layoutRows($items, $f['speed_layout'] ?? '1');
        $rows[] = [btnUI('cancel', 'cancel', 'cancel')];
        $head = ($sd['data']['rate_note'] ?? '') !== '' ? $sd['data']['rate_note'] . "\n\n" : '';

        // توضیح هر سرعت، اگر ادمین نوشته باشد
        $notes = '';
        $qtyNow = (int)($sd['data']['qty'] ?? 0);
        foreach ($speeds as $sp) {
            $d = trim((string)($sp['desc'] ?? ''));
            if ($d === '') continue;
            $notes .= "\n" . h(speedLabel($sp)) . " — " . $d;
            if ($qtyNow > 0) $notes .= " (" . h(speedEta($sp, $qtyNow)) . ")";
        }
        if ($notes !== '') $notes = "\n" . $notes . "\n";

        panelShow($uid, $chatId, 'shop', $head . T('flow_speed') . $notes, inlineKb($rows));
        return;
    }

    if ($step === 'admin') {
        if (empty($f['ask_admin'])) { flowInvoice($uid, $chatId); return; }
        $rows = [];
        $un = botUsername();
        if ($un) $rows[] = [btnUrl('➕ افزودن ربات به کانال', "https://t.me/{$un}?startchannel&admin=invite_users+promote_members", 'buy')];
        $rows[] = [btnUI('did_admin', 'fadm', 'confirm')];
        $rows[] = [btnUI('cancel', 'cancel', 'cancel')];
        panelShow($uid, $chatId, 'shop', T('flow_addbot'), inlineKb($rows));
        return;
    }
}

/** نام کاربری ربات مادر — یک بار گرفته و کش می‌شود */
function botUsername() {
    static $u = null;
    if ($u !== null) return $u;
    $c = load('config');
    if (!empty($c['bot_username'])) return $u = $c['bot_username'];
    $r = tg(BOT_TOKEN, 'getMe', [], 8);
    $u = $r['result']['username'] ?? '';
    if ($u !== '') cfgSet(function (&$x) use ($u) { $x['bot_username'] = $u; });
    return $u;
}

/**
 * بررسی ادمین بودن ربات در کانال کاربر.
 * لینک عمومی را مستقیم چک می‌کند؛ لینک خصوصی از راه my_chat_member
 * تایید می‌شود (وقتی کاربر ربات را اضافه کرد، همان‌جا ثبت می‌شود).
 */
function flowCheckAdmin($uid) {
    $st = getState($uid);
    if (!$st || $st['action'] !== 'flow') return [false, ''];
    $sd = $st['data'];

    // اگر با افزودن ربات قبلا تایید شده
    if (!empty($sd['data']['admin_ok'])) return [true, $sd['data']['chat_title'] ?? ''];

    $link = $sd['data']['link'] ?? '';
    if (!preg_match('#^https?://t\.me/([A-Za-z0-9_]{4,})/?$#', $link, $m)) {
        return [false, ''];   // لینک خصوصی — فقط از راه افزودن ربات تایید می‌شود
    }
    $chat = '@' . $m[1];
    $info = tg(BOT_TOKEN, 'getChat', ['chat_id' => $chat], 8);
    if (empty($info['ok'])) return [false, ''];

    $me = tg(BOT_TOKEN, 'getMe', [], 8);
    $botId = $me['result']['id'] ?? 0;
    if (!$botId) return [false, ''];

    $mem = tg(BOT_TOKEN, 'getChatMember', ['chat_id' => $chat, 'user_id' => $botId], 8);
    if (empty($mem['ok'])) return [false, ''];
    $ok = in_array($mem['result']['status'] ?? '', ['administrator', 'creator'], true);
    if (!$ok) return [false, ''];

    $title = $info['result']['title'] ?? $chat;
    mutate('states', function (&$x) use ($uid, $chat, $title) {
        $k = (string)$uid;
        if (!isset($x[$k])) return;
        $x[$k]['data']['data']['admin_ok']   = true;
        $x[$k]['data']['data']['chat_id']    = $chat;
        $x[$k]['data']['data']['chat_title'] = $title;
    });
    return [true, $title];
}

function speedLabel($sp) {
    return trim(($sp['emoji'] ?? '') . ' ' . ($sp['text'] ?? '—'));
}

/** زمان تقریبی تکمیل سفارش بر اساس سرعت انتخابی */
function speedEta($sp, $qty) {
    $perDay = (int)($sp['per_day'] ?? 0);
    if ($perDay <= 0 || $qty <= 0) return '—';
    $days = $qty / $perDay;
    if ($days < 1)  return 'کمتر از یک روز';
    if ($days < 2)  return 'حدود 1 روز';
    return 'حدود ' . ceil($days) . ' روز';
}

/** برچسب دکمه سرعت با سرعت روزانه */
/**
 * متن روی دکمه سرعت — فقط ایموجی و نام.
 * عدد «نفر در روز» و قیمت روی دکمه نمی‌آید؛ در متن نرخ و فاکتور نشان داده می‌شود.
 * اگر خواستید برگردد: تنظیمات ← «نمایش نفر/روز روی دکمه سرعت».
 */
function speedBtnLabel($sp) {
    $l = speedLabel($sp);
    if (empty(cfg()['ui']['speed_show_perday'])) return $l;
    $pd = (int)($sp['per_day'] ?? 0);
    return $pd > 0 ? $l . ' — ' . number_format($pd) . '/روز' : $l;
}

/** محاسبه مبلغ نهایی */
function flowTotal($p, $qty, $mult) {
    $per = max(1, (int)($p['flow']['per'] ?? 1000));
    return round(((float)$p['price'] * $mult) * ($qty / $per), 2);
}

function flowInvoice($uid, $chatId) {
    $st = getState($uid);
    if (!$st || $st['action'] !== 'flow') return;
    $sd = $st['data'];
    $p  = Product::get($sd['pid']);
    if (!$p) { clearState($uid); return; }

    $qty   = (int)($sd['data']['qty'] ?? 0);
    $mult  = (float)($sd['data']['mult'] ?? 1);
    $rate  = round((float)$p['price'] * $mult, 2);
    $total = $qty > 0 ? flowTotal($p, $qty, $mult) : $rate;

    $sd['data']['total'] = $total;
    setState($uid, 'flow', $sd);

    $okLine = !empty($sd['data']['admin_ok'])
        ? T('flow_admin_ok', ['title' => h($sd['data']['chat_title'] ?? '—')]) . "\n\n"
        : '';

    $text = $okLine . T('flow_invoice', [
        'link'     => h($sd['data']['link'] ?? '—'),
        'qty'      => number_format($qty),
        'product'  => h($p['name']),
        'speed'    => h($sd['data']['speed'] ?? '—'),
        'per_day'  => number_format((int)($sd['data']['per_day'] ?? 0)),
        'eta'      => h($sd['data']['eta'] ?? '—'),
        'per'      => number_format((int)($p['flow']['per'] ?? 1000)),
        'rate'     => fmtNum($rate),
        'total'    => fmtNum($total),
        'currency' => h($p['currency']),
    ]);
    panelShow($uid, $chatId, 'shop', $text, inlineKb([
        [btnCb('✅ موافقم، نهایی سازی سفارش', 'fok', 'confirm')],
        [btnUI('cancel', 'cancel', 'cancel')],
    ]));
}

/** ثبت نهایی سفارش جریان‌دار */
function flowFinish($uid, $chatId, $uname) {
    $st = getState($uid);

    // دوباره زدن همان دکمه — پیام قبلی هنوز روی صفحه است
    if ($st && $st['action'] === 'flow_meta') {
        sendMsg(BOT_TOKEN, $chatId, "ℹ️ سفارش شما ثبت شده؛ روش پرداخت را از پیام بالا انتخاب کنید.");
        return false;
    }
    if (!$st || $st['action'] !== 'flow') {
        // حالت گم شده (ربات ری‌استارت شده یا خیلی وقت گذشته) — از اول، نه سکوت
        sendMsg(BOT_TOKEN, $chatId,
            "⚠️ این سفارش منقضی شده است.\n\nلطفا دوباره از «🛒 خرید محصول» شروع کنید.",
            mainKeyboard());
        return false;
    }

    $sd = $st['data'];
    $p  = Product::get($sd['pid']);
    if (!$p) {
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "⚠️ این محصول دیگر در دسترس نیست.", mainKeyboard());
        if ($uid !== ADMIN_ID) sendMsg(BOT_TOKEN, ADMIN_ID,
            "⚠️ کاربر <code>{$uid}</code> روی محصولی سفارش داد که دیگر وجود ندارد: <code>" .
            h((string)($sd['pid'] ?? '')) . "</code>");
        return false;
    }

    $total = (float)($sd['data']['total'] ?? 0);

    // 🧪 حالت تست — سفارش با مبلغ صفر تا آخر می‌رود
    if ($total <= 0 && !empty(cfg()['test_mode'])) {
        $meta = [
            'link'       => $sd['data']['link'] ?? '',
            'qty'        => (int)($sd['data']['qty'] ?? 0),
            'speed'      => $sd['data']['speed'] ?? '',
            'per_day'    => (int)($sd['data']['per_day'] ?? 0),
            'eta'        => $sd['data']['eta'] ?? '',
            'chat_id'    => $sd['data']['chat_id'] ?? '',
            'chat_title' => $sd['data']['chat_title'] ?? '',
            'admin_ok'   => !empty($sd['data']['admin_ok']),
            'test'       => true,
        ];
        clearState($uid);
        $oid = Order::create($uid, $uname, 'product', $p['id'], 0, $p['currency'], $meta);
        Order::attachReceipt($oid, 'text', '🧪 سفارش تستی — بدون پرداخت');
        Order::approve($oid, ADMIN_ID);
        $od = Order::get($oid);
        panelShow($uid, $chatId, 'shop', orderDoneText($od), orderDoneKb($od));
        announceSale($od);
        reportSale($od);
        return true;
    }

    if ($total <= 0) {
        // تقریبا همیشه یعنی: قیمت این دکمه هنوز تنظیم نشده
        clearState($uid);
        if ($uid === ADMIN_ID) {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ <b>قیمت این محصول صفر است، پس فاکتور ساخته نمی‌شود.</b>\n\n" .
                "محصول: <b>" . h($p['name']) . "</b>\n\n" .
                "قیمتش را بگذارید:\n" .
                "• داخل ربات: /panel ← 🔘 دکمه‌ها ← این دکمه ← 💰 قیمت\n" .
                "• یا پنل وب ← 🛒 محصولات",
                inlineKb(parseSubProductId($p['id'])
                    ? [[btnCb('💰 تنظیم قیمت همین حالا', 'sbpr_' . implode('|', $p['btn']), 'buy')]]
                    : [[btnCb('💰 تنظیم قیمت همین حالا', 'epp_' . $p['id'], 'buy')]]));
        } else {
            sendMsg(BOT_TOKEN, $chatId,
                "⚠️ قیمت این محصول هنوز تنظیم نشده است.\nلطفا با پشتیبانی تماس بگیرید.",
                mainKeyboard());
            sendMsg(BOT_TOKEN, ADMIN_ID,
                "🔴 <b>فروش از دست رفت!</b>\n\n" .
                "کاربر <code>{$uid}</code> خواست «<b>" . h($p['name']) . "</b>» بخرد " .
                "ولی قیمتش صفر است.\n\nهمین حالا قیمتش را بگذارید:",
                inlineKb(parseSubProductId($p['id'])
                    ? [[btnCb('💰 تنظیم قیمت', 'sbpr_' . implode('|', $p['btn']), 'buy')]]
                    : [[btnCb('💰 تنظیم قیمت', 'epp_' . $p['id'], 'buy')]]));
        }
        return false;
    }

    $meta = [
        'link'       => $sd['data']['link'] ?? '',
        'qty'        => (int)($sd['data']['qty'] ?? 0),
        'speed'      => $sd['data']['speed'] ?? '',
        'per_day'    => (int)($sd['data']['per_day'] ?? 0),
        'eta'        => $sd['data']['eta'] ?? '',
        'chat_id'    => $sd['data']['chat_id'] ?? '',
        'chat_title' => $sd['data']['chat_title'] ?? '',
        'admin_ok'   => !empty($sd['data']['admin_ok']),
    ];
    $note = trim($meta['link'] . ' · ' . number_format($meta['qty']) . ' نفر · ' . $meta['speed'] .
                 ($meta['eta'] ? ' · ' . $meta['eta'] : ''));
    setState($uid, 'flow_meta', $meta);
    // نسخه ماندگار — اگر کاربر وسط کار رفت شارژ کند، مشخصات سفارش گم نشود
    mutate('users', function (&$a) use ($uid, $meta, $p, $total) {
        if (!isset($a[(string)$uid])) return;
        $a[(string)$uid]['pending'] = ['pid' => $p['id'], 'amount' => $total,
                                       'currency' => $p['currency'], 'meta' => $meta, 'at' => nowStr()];
    });

    $bal   = (float)(getUser($uid)['balance'] ?? 0);
    $canW  = trim($p['currency']) === 'تومان';
    $short = max(0, $total - $bal);

    $rows = [];
    if ($canW) $rows[] = [btnCb(UT('wallet_pay') . ' (' . fmtNum($bal) . ')',
                                'fwpay_' . $p['id'] . '|' . $total, 'buy')];
    $rows[] = [btnCb(UT('direct_pay'), 'fdpay_' . $p['id'] . '|' . $total, 'buy')];
    if ($canW && $short > 0) $rows[] = [btnCb('➕ شارژ کیف پول (' . fmtNum($short) . ')',
                                              'topup_' . (int)ceil($short), 'confirm')];
    $rows[] = [btnUI('cancel', 'cancel', 'cancel')];

    $t  = "🧾 <b>" . h($p['name']) . "</b>\n" . h($note);
    $t .= "\n\n💳 مبلغ: <b>" . fmtNum($total) . ' ' . h($p['currency']) . "</b>";
    if ($canW) {
        $t .= "\n💰 موجودی شما: <b>" . fmtNum($bal) . " تومان</b>";
        if ($short > 0) $t .= "\n➖ کمبود: <b>" . fmtNum($short) . " تومان</b>";
    }
    $t .= "\n\n⚠️ سفارش هنوز ثبت نشده — بعد از پرداخت ثبت می‌شود.";
    $t .= "\n\nروش پرداخت را انتخاب کنید:";

    panelShow($uid, $chatId, 'shop', $t, inlineKb($rows));
    return true;
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
/**
 * 🎯 بعد از پرداخت، کانال مشتری خودکار قفل می‌شود
 *
 * سفارش ممبر تایید شد → یک کمپین ساخته می‌شود → کانال مشتری به لیست
 * عضویت اجباری همه ربات‌های اپلودر اضافه می‌شود → هر کاربری که بخواهد
 * فایل بگیرد باید اول عضو شود → به‌محض رسیدن به تعداد سفارش، قفل خودکار برداشته می‌شود.
 */
function campaignFromOrder($o) {
    if (($o['type'] ?? '') !== 'product') return null;
    if (Campaign::forOrder($o['id'])) return null;         // قبلا ساخته شده

    $meta = $o['meta'] ?? [];
    $qty  = (int)($meta['qty'] ?? 0);
    if ($qty <= 0) return null;                            // سفارش ممبر نیست

    // شناسه‌ای که تلگرام بشناسد: یا شناسه عددی از my_chat_member، یا @نام از لینک عمومی
    $chat = trim((string)($meta['chat_id'] ?? ''));
    if ($chat === '') {
        $link = (string)($meta['link'] ?? '');
        if (preg_match('#^https?://t\.me/([A-Za-z0-9_]{4,})/?$#', $link, $m)) $chat = '@' . $m[1];
    }
    $title = trim((string)($meta['chat_title'] ?? '')) ?: (string)($meta['link'] ?? 'کانال مشتری');
    $url   = (string)($meta['link'] ?? '');

    if ($chat === '') {
        // لینک خصوصی و ربات هیچ‌وقت ادمین نشده — هنوز نمی‌شود عضویت را شمرد.
        // سفارش گم نمی‌شود: کمپین ساخته می‌شود ولی خاموش، تا بعد از ادمین کردن روشنش کنید.
        $c = Campaign::create($title, '', $url, $qty, [], [], 'سفارش ' . $o['id'],
                              $o['id'], (int)($meta['per_day'] ?? 0));
        mutate('campaigns', function (&$a) use ($c) {
            if (!isset($a[$c['id']])) return;
            $a[$c['id']]['active'] = false;
            $a[$c['id']]['paused_reason'] = 'ربات در کانال ادمین نیست';
        });
        sendMsg(BOT_TOKEN, ADMIN_ID,
            "⏸ سفارش <code>" . h($o['id']) . "</code> پرداخت شد، کمپینش ساخته شد ولی <b>هنوز خاموش است</b>.\n\n" .
            "📣 کانال: " . h($title) . "\n" .
            "👥 هدف: <b>" . number_format($qty) . "</b> عضو\n\n" .
            "دلیل: ربات در کانال ادمین نیست، پس نمی‌تواند عضویت را بشمارد.\n\n" .
            "ربات را در کانال ادمین کنید، بعد پنل وب ← 🎯 کمپین‌ها ← آیدی کانال را بگذارید و روشنش کنید.");
        return Campaign::get($c['id']);
    }

    $c = Campaign::create(
        $title, $chat, $url, $qty,
        [],                                   // همه شریک‌ها
        [],                                   // همه ربات‌های اپلودر
        'سفارش ' . $o['id'],
        $o['id'],
        (int)($meta['per_day'] ?? 0)
    );
    $picked = assignCampaignBots($c['id']);   // 🎯 روی حداکثر ۲ ربات می‌نشیند
    $c = Campaign::get($c['id']);

    sendMsg(BOT_TOKEN, ADMIN_ID,
        "🎯 <b>کمپین خودکار ساخته شد</b>\n\n" .
        "📣 کانال: <b>" . h($title) . "</b>\n" .
        "👥 هدف: <b>" . number_format($qty) . "</b> عضو\n" .
        ((int)($meta['per_day'] ?? 0) > 0
            ? "🚀 سقف روزانه: <b>" . number_format((int)$meta['per_day']) . "</b> نفر\n" : '') .
        "🧾 سفارش: <code>" . h($o['id']) . "</code>\n\n" .
        (function () use ($picked) {
            if (!$picked) return "⚠️ هنوز ربات اپلودری ندارید — این کانال جایی قفل نمی‌شود.\n";
            $names = [];
            foreach ($picked as $bid) { $b = BotManager::get($bid); if ($b) $names[] = '@' . $b['username']; }
            return "🤖 قفل شد روی: <b>" . h(implode('، ', $names)) . "</b>\n";
        })() .
        "به‌محض رسیدن به هدف، خودکار برداشته می‌شود.");

    return $c;
}

/**
 * 📢 گزارش خرید در گروه — هر محصول تاپیک خودش
 *
 * یک گروه با چند تاپیک: هر دکمه محصول گزارشش را در تاپیک خودش می‌فرستد.
 * متن با ایموجی پریمیوم و quote پشتیبانی می‌شود (داخل ربات ویرایش می‌شود).
 */
function reportSale($order, $force = false) {
    if (($order['type'] ?? '') !== 'product') return;
    $p = Product::get($order['product_id']);
    if (!$p) return;

    $r = reportOf($p);
    if (trim((string)$r['chat_id']) === '') return;
    if (empty($r['on']) && !$force) return;

    $m = $order['meta'] ?? [];
    $u = getUser($order['user_id']) ?: [];
    $uname = !empty($u['username']) ? '@' . $u['username'] : ($u['first_name'] ?? (string)$order['user_id']);

    $cmp  = Campaign::forOrder($order['id']);
    $done = $cmp ? count($cmp['joined']) : 0;

    $text = strtr((string)$r['text'], [
        '{product}'  => h($p['name']),
        '{emoji}'    => (string)($p['emoji'] ?? ''),
        '{code}'     => h($order['id']),
        '{amount}'   => fmtNum($order['amount']),
        '{currency}' => h($order['currency']),
        '{qty}'      => number_format((int)($m['qty'] ?? 0)),
        '{speed}'    => h($m['speed'] ?? '—'),
        '{per_day}'  => number_format((int)($m['per_day'] ?? 0)),
        '{eta}'      => h($m['eta'] ?? '—'),
        '{link}'     => h($m['link'] ?? '—'),
        '{channel}'  => h($m['chat_title'] ?? ($m['link'] ?? '—')),
        '{delivered}'=> number_format($done),
        '{user}'     => h($uname),
        '{user_id}'  => (int)$order['user_id'],
        '{date}'     => h(nowStr()),
    ]);

    $rows = [];
    foreach ($r['buttons'] as $b) {
        if (empty($b['on'])) continue;
        $t = trim((string)($b['text'] ?? ''));
        $url = trim((string)($b['url'] ?? ''));
        if ($t === '' || $url === '') continue;
        $btn = ['text' => $t, 'url' => $url];
        if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
        if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
        $rows[] = [$btn];
    }

    $extra = [];
    if ((int)$r['thread_id'] > 0) $extra['message_thread_id'] = (int)$r['thread_id'];

    $res = sendMsg(BOT_TOKEN, $r['chat_id'], $text, $rows ? inlineKb($rows) : null, $extra);
    if (empty($res['ok'])) {
        error_log('[report] ' . ($res['description'] ?? 'unknown'));
        sendMsg(BOT_TOKEN, ADMIN_ID,
            "⚠️ <b>گزارش خرید ارسال نشد</b>\n\n" .
            "محصول: <b>" . h($p['name']) . "</b>\n" .
            "گروه: <code>" . h((string)$r['chat_id']) . "</code>" .
            ((int)$r['thread_id'] > 0 ? " · تاپیک <code>" . (int)$r['thread_id'] . "</code>" : '') . "\n" .
            "خطا: <code>" . h($res['description'] ?? '—') . "</code>\n\n" .
            "ربات را در گروه ادمین کنید و آیدی/تاپیک را بررسی کنید.");
    }
}

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
        $uidT = $order['user_id'];
        $balT = (float)(getUser($uidT)['balance'] ?? 0);
        $t = "✅ کیف پول شما <b>" . fmtNum($order['amount']) . "</b> تومان شارژ شد.\n" .
             "💰 موجودی جدید: <b>" . fmtNum($balT) . "</b> تومان";

        // سفارش نیمه‌تمام داشت؟ همان‌جا ادامه بدهد
        $pend = getUser($uidT)['pending'] ?? null;
        $rows = [];
        if ($pend && (float)($pend['amount'] ?? 0) > 0 && Product::get($pend['pid'] ?? '')) {
            $need = (float)$pend['amount'];
            $pp   = Product::get($pend['pid']);
            $t .= "\n\n🛒 سفارش نیمه‌تمام: <b>" . h($pp['name']) . "</b> — " .
                  fmtNum($need) . ' ' . h($pend['currency'] ?? 'تومان');
            $t .= $balT >= $need
                ? "\n✅ حالا موجودی کافی است."
                : "\n➖ هنوز " . fmtNum($need - $balT) . " تومان کم دارید.";
            if ($balT >= $need)
                $rows[] = [btnCb('✅ پرداخت و ثبت سفارش', 'fwpay_' . $pend['pid'] . '|' . $need, 'buy')];
        }
        sendMsg(BOT_TOKEN, $uidT, $t, $rows ? inlineKb($rows) : null);
        return;
    }
    // 📩 یک پیام کامل — نه سه تا
    sendMsg(BOT_TOKEN, $order['user_id'], orderDoneText($order), orderDoneKb($order));
    announceSale($order);
    reportSale($order);
}

/** ✅ پیام واحد بعد از تایید سفارش — همه اطلاعات یک‌جا */
function orderDoneText($order) {
    $p = Product::get($order['product_id']);
    $m = $order['meta'] ?? [];
    $isTest = !empty($m['test']);

    $t  = ($isTest ? "🧪 <b>سفارش تستی ثبت شد</b>\n\n" : T('approved') . "\n\n");
    $t .= "📦 محصول: <b>" . h($p['name'] ?? '—') . "</b>\n";
    if (!empty($m['link']))    $t .= "📣 کانال: " . h($m['link']) . "\n";
    if (!empty($m['qty']))     $t .= "👥 تعداد: <b>" . number_format((int)$m['qty']) . "</b> نفر\n";
    if (!empty($m['speed']))   $t .= "⚡️ سرعت: " . h($m['speed']) . "\n";
    if (!empty($m['per_day'])) $t .= "🚀 سرعت تحویل: " . number_format((int)$m['per_day']) . " نفر در روز\n";
    if (!empty($m['eta']))     $t .= "⏳ زمان تقریبی: <b>" . h($m['eta']) . "</b>\n";
    $t .= "💰 مبلغ: <b>" . ($isTest ? '0' : fmtNum($order['amount'])) . ' ' . h($order['currency']) . "</b>\n";
    $t .= "🧾 کد پیگیری: <code>" . h($order['id']) . "</code>\n";
    $t .= "📊 وضعیت: <b>" . orderStage($order)[1] . "</b>\n";

    // اگر محصول محتوا دارد، لینکش را همین‌جا بده — نه پیام جدا
    if ($p && !empty($p['bot_id']) && !empty($p['link_code'])) {
        $url = Links::url($p['bot_id'], $p['link_code']);
        if ($url) $t .= "\n🔗 لینک دریافت محتوا:\n" . $url . "\n";
    }

    $t .= "\n" . ($isTest
        ? "حالت تست روشن است، پس بدون پرداخت تایید شد."
        : "ممبرها به‌تدریج اضافه می‌شوند. وضعیت را با همان کد پیگیری ببینید.");
    return $t;
}

function orderDoneKb($order) {
    $rows = [[btnCb('🔍 وضعیت سفارش', 'trk_' . $order['id'], 'info')]];
    $p = Product::get($order['product_id']);
    if ($p && !empty($p['bot_id']) && !empty($p['link_code'])) {
        $url = Links::url($p['bot_id'], $p['link_code']);
        if ($url) $rows[] = [['text' => UT('enter_bot'), 'url' => $url, 'style' => gs('link') ?: null]];
    }
    return inlineKb($rows);
}

function notifyAdminOrder($orderId) {
    $o = Order::get($orderId);
    if (!$o) return;
    $title = $o['type'] === 'topup' ? '➕ شارژ کیف پول' : ('🛒 ' . (Product::get($o['product_id'])['name'] ?? '—'));
    $uname = $o['username'] ? '@' . $o['username'] : '—';

    $text  = "🧾 <b>سفارش جدید — منتظر تایید</b>\n\n";
    $text .= "👤 کاربر: " . h($uname) . " (<code>{$o['user_id']}</code>)\n";
    $text .= "📦 {$title}\n";
    $m = $o['meta'] ?? [];
    if ($m) {
        $text .= "📣 " . h($m['link'] ?? '—') . "\n";
        $text .= "👥 " . number_format((int)($m['qty'] ?? 0)) . " نفر · " . h($m['speed'] ?? '') . "\n";
        if (!empty($m['per_day'])) $text .= "🚀 " . number_format((int)$m['per_day']) . " نفر/روز · ⏳ " . h($m['eta'] ?? '') . "\n";
        $text .= (!empty($m['admin_ok'])
            ? "🤖 ربات ادمین است" . (!empty($m['chat_title']) ? ' — ' . h($m['chat_title']) : '')
            : "⚠️ ادمین بودن ربات تایید نشده") . "\n";
    }
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
        [btnCb('🔧 راه‌اندازی خودکار', 'setup', 'confirm')],
        [btnCb('🛒 محصولات', 'eprods', 'admin'),
         btnCb('🤖 ربات‌های زیرمجموعه', 'eupload', 'admin')],
        [btnCb('📢 گزارش خرید در گروه', 'adm_reports', 'admin')],
        [btnCb('🔒 قفل‌های عضویت اجباری', 'adm_locks', 'admin')],
        [btnCb('📋 لیست تعرفه‌ها', 'adm_tariff', 'admin')],
        [btnCb('🧾 سفارش‌ها', 'adm_orders', 'admin'), btnCb('🤖 ربات‌ها', 'adm_bots', 'admin')],
        [btnCb('📢 کانال‌ها', 'adm_chans', 'admin'), btnCb('📢 پیام همگانی', 'adm_bc', 'admin')],
        [btnCb('🌐 پنل وب', 'adm_web', 'info')],
        [btnCb(UT('home'), 'home', 'nav')],
    ];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

/** 🤖 انتخاب ربات اپلودر تحویل برای یک محصول */
function edProdBot($chatId, $msgId, $pid) {
    $p = Product::get($pid);
    if (!$p) return;
    $bots = BotManager::all();

    $text  = "🤖 <b>ربات تحویل — " . h($p['name']) . "</b>\n\n";
    $text .= "بعد از خرید، لینک محتوا از این ربات به مشتری داده می‌شود.\n";
    $text .= "برای فروش ممبر لازم نیست؛ برای فروش فایل بله.\n\n";
    $cur = !empty($p['bot_id']) ? BotManager::get($p['bot_id']) : null;
    $text .= "الان: " . ($cur ? '@' . h($cur['username']) : '<b>هیچ‌کدام</b>') . "\n";
    if (!empty($p['link_code'])) $text .= "کد لینک: <code>" . h($p['link_code']) . "</code>\n";

    $rows = [];
    foreach ($bots as $b) {
        $mark = (!empty($p['bot_id']) && $p['bot_id'] === $b['id']) ? '✅ ' : '';
        $rows[] = [btnCb($mark . '@' . $b['username'], 'sbbotset_' . $pid . '|' . $b['id'], 'info')];
    }
    if (!$bots) $text .= "\n⚠️ هنوز ربات اپلودری اضافه نکرده‌اید (پنل وب ← 🤖 ربات‌ها).";
    $rows[] = [btnCb('🚫 بدون ربات', 'sbbotset_' . $pid . '|', 'reject'),
               btnCb('🔗 کد لینک', 'sbcode_' . $pid, 'admin')];
    $bs = parseSubProductId($pid);
    $rows[] = [btnUI('back', $bs ? 'sb_' . $bs[0] . '|' . $bs[1] : 'ep_' . $pid, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🔒 نمای قفل‌های عضویت اجباری — کدام کانال روی کدام ربات */
function admLocks($chatId, $msgId) {
    $bots = array_filter(BotManager::all(), fn($b) => !empty($b['active']));
    $camps = [];
    foreach (Campaign::all() as $c) if (!empty($c['active']) && !Campaign::isDone($c)) $camps[] = $c;

    $text  = "🔒 <b>قفل‌های عضویت اجباری</b>\n\n";
    $text .= "هر کانال روی <b>" . BOTS_PER_CAMPAIGN . " ربات</b> قفل می‌شود، و هر کاربر حداکثر " .
             "<b>" . MAX_JOIN_PER_BOT . " کانال</b> می‌بیند.\n\n";

    if (!$bots) {
        $text .= "⚠️ هیچ ربات اپلودری ندارید. تا ربات اضافه نکنید، هیچ کانالی قفل نمی‌شود.";
    } elseif (!$camps) {
        $text .= "الان کمپین فعالی نیست.\n🤖 ربات‌ها: <b>" . count($bots) . "</b>";
    } else {
        foreach ($bots as $b) {
            $mine = [];
            foreach ($camps as $c) {
                $on = $c['bots'] ?? [];
                if (!$on || in_array($b['id'], $on, true)) $mine[] = $c['title'];
            }
            $fixed = count(Channels::all($b['id']));
            $tot = count($mine) + $fixed;
            $text .= ($tot > MAX_JOIN_PER_BOT ? '⚠️ ' : '✅ ') . '@' . h($b['username']) .
                     " — <b>{$tot}</b> کانال" . ($fixed ? " (‏{$fixed} ثابت)" : '') . "\n";
            foreach (array_slice($mine, 0, 6) as $t) $text .= "   🔒 " . h($t) . "\n";
            if (count($mine) > 6) $text .= "   … و " . (count($mine) - 6) . " تای دیگر\n";
        }
        $text .= "\n📣 کمپین فعال: <b>" . count($camps) . "</b>";
    }

    $rows = [[btnCb('🔄 پخش دوباره بین ربات‌ها', 'locks_rebalance', 'confirm')],
             [btnUI('back', 'adm_home', 'nav')]];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 📋 ویرایش لیست تعرفه‌ها */
function admTariff($chatId, $msgId) {
    $tf = cfg()['tariff'] ?? [];
    $text  = "📋 <b>لیست تعرفه‌ها</b>\n\n";
    $text .= "وضعیت: " . (!empty($tf['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= "جدول خودکار قیمت‌ها: " . (!empty($tf['auto']) ? '✅ اضافه می‌شود' : '❌ فقط متن خودتان') . "\n\n";
    $text .= "🔘 دکمه: " . h(trim(($tf['btn']['emoji'] ?? '') . ' ' . ($tf['btn']['text'] ?? ''))) .
             " · " . (styleMap()[$tf['btn']['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "◀️ دکمه برگشت: " . h(trim(($tf['back']['emoji'] ?? '') . ' ' . ($tf['back']['text'] ?? ''))) . "\n\n";
    $text .= "<b>متن فعلی:</b>\n" . (string)($tf['text'] ?? '—');

    $rows = [
        [btnCb(!empty($tf['on']) ? '❌ خاموش کردن' : '✅ روشن کردن', 'tfx', 'info'),
         btnCb(!empty($tf['auto']) ? '📊 جدول: روشن' : '📊 جدول: خاموش', 'tfauto', 'info')],
        [btnCb('✏️ متن تعرفه', 'tft', 'admin')],
        [btnCb('🔘 متن دکمه', 'tfbt', 'admin'), btnCb('😀 ایموجی دکمه', 'tfbe', 'admin')],
        [btnCb('◀️ متن دکمه برگشت', 'tfkt', 'admin'), btnCb('😀 ایموجی برگشت', 'tfke', 'admin')],
        [btnCb('🎨 رنگ دکمه', 'tfbc', 'admin'), btnCb('🎨 رنگ برگشت', 'tfkc', 'admin')],
        [btnCb('👁 پیش‌نمایش', 'tfprev', 'confirm')],
        [btnUI('back', 'adm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 📢 فهرست گزارش‌های خرید — یک گروه، هر محصول یک تاپیک */
function admReports($chatId, $msgId) {
    $items = saleButtons();
    foreach (Product::all() as $pr) if (!empty($pr['active'])) $items[] = $pr;

    $text  = "📢 <b>گزارش خرید در گروه</b>\n\n";
    $text .= "یک گروه بسازید و برای هر محصول یک <b>تاپیک</b> جدا.\n";
    $text .= "بعد از هر خرید، گزارشش در تاپیک خودش می‌افتد.\n";
    $text .= "⚠️ ربات باید در گروه <b>ادمین</b> باشد.\n\n";

    $rows = [];
    if (!$items) {
        $text .= "هنوز محصولی ندارید.";
    } else {
        foreach ($items as $p) {
            $r = reportOf($p);
            $mark = !empty($r['on']) && trim((string)$r['chat_id']) !== '' ? '✅' : '❌';
            $tag  = trim((string)$r['chat_id']) !== ''
                ? ((int)$r['thread_id'] > 0 ? '🧵 ' . (int)$r['thread_id'] : 'بدون تاپیک')
                : 'تنظیم نشده';
            $text .= "{$mark} " . h(trim(($p['emoji'] ?? '') . ' ' . $p['name'])) . " — {$tag}\n";
            $rows[] = [btnCb($mark . ' ' . trim(($p['emoji'] ?? '') . ' ' . $p['name']),
                             'rp_' . $p['id'], 'info')];
        }
        $rows[] = [btnCb('👥 یک گروه برای همه', 'rpall', 'buy')];
    }
    $rows[] = [btnUI('back', 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
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
        'flow_link' => '🔗 سوال لینک کانال', 'flow_link_bad' => '🔗 لینک نامعتبر',
        'flow_qty' => '👥 سوال تعداد', 'flow_qty_bad' => '👥 تعداد نامعتبر',
        'flow_speed' => '⚡️ سوال سرعت', 'flow_rate' => '💰 نمایش نرخ',
        'flow_addbot' => '🤖 افزودن ربات به کانال', 'flow_admin_ok' => '✅ ادمین شد',
        'flow_admin_no' => '❌ هنوز ادمین نشده',
        'flow_invoice' => '📋 فاکتور سفارش',
        'sup_ticket' => '💬 پیام ارتباط غیر مستقیم', 'sup_sent' => '✅ پیام ارسال شد',
        'orders_item' => '📊 قالب هر سفارش',
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
        'did_admin' => 'ادمین کردم',
        'sup_direct' => 'ارتباط مستقیم', 'sup_indirect' => 'ارتباط غیر مستقیم',
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
        [btnCb('💠 دکمه‌های شیشه‌ای زیرش (' . count($b['subs'] ?? []) . ')', 'sbs_' . $id, 'confirm')],
    ];
    if (!empty($b['action'])) {
        $rows[] = [btnCb('📝 مقدار', 'ebv_' . $id, 'admin'), btnCb('🗑 حذف دکمه', 'ebd_' . $id, 'reject')];
    }
    $rows[] = [btnCb(UT('back'), 'ebuttons', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 💠 زیردکمه‌های شیشه‌ای یک دکمه */
function edSubs($chatId, $msgId, $bid) {
    $b = cfg()['buttons'][$bid] ?? null;
    if (!$b) { edButtons($chatId, $msgId); return; }
    $subs = $b['subs'] ?? [];

    $text  = "💠 <b>دکمه‌های شیشه‌ای زیر «" . h($b['text']) . "»</b>\n\n";
    $text .= $subs
        ? "این دکمه‌ها زیر همان بخش نمایش داده می‌شوند.\nچیدمان: <code>" . h($b['sub_layout'] ?? '1') . "</code>"
        : "هنوز دکمه‌ای اضافه نکرده‌اید.";

    $rows = [];
    foreach ($subs as $sub) {
        $col = styleMap()[$sub['color'] ?? 'none'] ?? '';
        $rows[] = [btnCb((!empty($sub['on']) ? '✅ ' : '❌ ') .
                         trim(($sub['emoji'] ?? '') . ' ' . $sub['text']) . '  ' . mb_substr($col, 0, 2),
                         'sb_' . $bid . '|' . $sub['id'], 'info')];
    }
    $rows[] = [btnCb('➕ افزودن دکمه شیشه‌ای', 'sbnew_' . $bid, 'confirm'),
               btnCb('📐 چیدمان', 'sblay_' . $bid, 'admin')];
    if (count($subs) > 1) $rows[] = [btnCb('🔄 هماهنگ کردن همه با هم', 'sbsync_' . $bid, 'buy')];
    $rows[] = [btnUI('back', 'eb_' . $bid, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 💠 ویرایش یک زیردکمه */
function edSub($chatId, $msgId, $bid, $sid) {
    $sub = findSub($bid, $sid);
    if (!$sub) { edSubs($chatId, $msgId, $bid); return; }

    $actLabel = ['text' => 'نمایش متن', 'url' => 'باز کردن لینک',
                 'product' => 'رفتن به محصول', 'section' => 'رفتن به بخش'][$sub['action'] ?? 'text'] ?? '—';

    $text  = "💠 <b>ویرایش دکمه شیشه‌ای</b>\n\n";
    $text .= "نمایش: " . h(trim(($sub['emoji'] ?? '') . ' ' . $sub['text'])) . "\n";
    $text .= "رنگ: " . (styleMap()[$sub['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "✨ پریمیوم: " . (!empty($sub['icon']) ? '<code>' . h($sub['icon']) . '</code>' : '—') . "\n";
    $text .= "ردیف: " . (int)($sub['row'] ?? 0) . "  |  ترتیب: " . (int)($sub['order'] ?? 0) . "\n";
    $text .= "نوع: " . h($actLabel) . "\n";
    $linked = (($sub['action'] ?? '') === 'product') ? Product::get($sub['value'] ?? '') : null;
    if (($sub['action'] ?? '') === 'product') {
        $text .= "محصول: " . ($linked ? h($linked['name']) . (!empty($linked['flow']['on']) ? ' 🔄' : '') : '<b>انتخاب نشده</b>') . "\n";
    } elseif (($sub['action'] ?? '') === 'text' && !isPlaceholder($sub['value'] ?? '')
              && trim((string)($sub['value'] ?? '')) !== '') {
        $text .= "مقدار: <code>" . h(mb_substr((string)($sub['value'] ?? ''), 0, 80)) . "</code>\n";
    }

    // خود دکمه محصول است؟
    $sp = $linked ? null : subProduct($bid, $sid, $sub);
    if ($sp) {
        $text .= "\n🛒 <b>این دکمه خودش محصول است</b>\n";
        $text .= "💰 قیمت هر " . number_format((int)($sp['flow']['per'] ?? 1000)) . " عدد: " .
                 ((float)$sp['price'] > 0 ? '<b>' . fmtNum($sp['price']) . ' ' . h($sp['currency']) . '</b>'
                                          : '<b>تنظیم نشده ⚠️</b>') . "\n";
        $text .= "👥 تعداد: " . number_format((int)$sp['flow']['min']) . " تا " .
                 number_format((int)$sp['flow']['max']) . "\n";
        $ons = 0;
        foreach ($sp['flow']['speeds'] ?? [] as $spd) if (!isset($spd['on']) || !empty($spd['on'])) $ons++;
        $text .= "⚡️ سرعت‌ها: " . $ons . "\n";
        $dbot = !empty($sp['bot_id']) ? BotManager::get($sp['bot_id']) : null;
        $text .= "🤖 ربات تحویل: " . ($dbot ? '@' . h($dbot['username']) : '—') . "\n";
        $rp0 = reportOf($sp);
        $text .= "📢 گزارش: " . (!empty($rp0['on']) && trim((string)$rp0['chat_id']) !== ''
                 ? '✅ ' . ((int)$rp0['thread_id'] > 0 ? '🧵 ' . (int)$rp0['thread_id'] : 'بدون تاپیک')
                 : '❌ خاموش') . "\n";
    }
    $text .= "\nوضعیت: " . (!empty($sub['on']) ? '✅ روشن' : '❌ خاموش');

    $k = $bid . '|' . $sid;
    $rows = [
        [btnCb('✏️ متن', 'sbt_' . $k, 'admin'), btnCb('😀 ایموجی', 'sbe_' . $k, 'admin')],
        [btnCb('🎨 رنگ', 'sbc_' . $k, 'admin'), btnCb('✨ پریمیوم', 'sbi_' . $k, 'admin')],
        [btnCb('📐 ردیف', 'sbr_' . $k, 'admin'), btnCb('🔢 ترتیب', 'sbo_' . $k, 'admin')],
        [btnCb('🔀 نوع', 'sba_' . $k, 'admin'),
         (($sub['action'] ?? '') === 'product'
            ? btnCb('🛒 انتخاب محصول', 'sbpick_' . $k, 'buy')
            : btnCb('📝 مقدار', 'sbv_' . $k, 'admin'))],
        ($sp ? [btnCb('💰 قیمت', 'sbpr_' . $k, 'buy'), btnCb('👥 تعداد', 'sbm_' . $k, 'info')] : null),
        ($sp ? [btnCb('⚡️ سرعت‌ها', 'sbsp_' . $k, 'admin'),
                btnCb('📢 گزارش خرید', 'sbrp_' . $k, 'admin')] : null),
        ($sp ? [btnCb('🤖 ربات تحویل', 'sbbot_' . $k, 'admin')] : null),
        [btnCb(!empty($sub['on']) ? '❌ خاموش' : '✅ روشن', 'sbx_' . $k, 'info'),
         btnCb('🗑 حذف', 'sbd_' . $k, 'reject')],
        [btnUI('back', 'sbs_' . $bid, 'nav')],
    ];
    $rows = array_values(array_filter($rows));
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 📢 تنظیم گزارش خرید یک محصول */
function edReport($chatId, $msgId, $pid) {
    $p = Product::get($pid);
    if (!$p) return;
    $r = reportOf($p);

    $text  = "📢 <b>گزارش خرید — " . h($p['name']) . "</b>\n\n";
    $text .= "وضعیت: " . (!empty($r['on']) ? '✅ روشن' : '❌ خاموش') . "\n";
    $text .= "گروه: " . (trim((string)$r['chat_id']) !== ''
             ? '<code>' . h($r['chat_id']) . '</code>' : '<b>تنظیم نشده</b>') . "\n";
    $text .= "تاپیک: " . ((int)$r['thread_id'] > 0 ? (int)$r['thread_id'] : 'بدون تاپیک') . "\n\n";
    $text .= "<b>متن گزارش:</b>\n" . $r['text'] . "\n\n";

    $bl = [];
    foreach ($r['buttons'] as $i => $b) {
        $bl[] = ($i + 1) . ') ' . (empty($b['on']) ? '❌ ' : '') .
                h(trim((string)$b['text'])) .
                (trim((string)$b['url']) !== '' ? ' → ' . h($b['url']) : ' → <b>بدون لینک</b>');
    }
    $text .= "<b>دکمه‌ها:</b>\n" . implode("\n", $bl);

    $rows = [
        [btnCb(!empty($r['on']) ? '❌ خاموش کردن' : '✅ روشن کردن', 'rpx_' . $pid, 'info')],
        [btnCb('👥 آیدی گروه', 'rpc_' . $pid, 'admin'), btnCb('🧵 شماره تاپیک', 'rpth_' . $pid, 'admin')],
        [btnCb('✏️ متن گزارش', 'rpt_' . $pid, 'admin')],
        [btnCb('1️⃣ دکمه اول', 'rpb_' . $pid . '|0', 'buy'),
         btnCb('2️⃣ دکمه دوم', 'rpb_' . $pid . '|1', 'buy')],
        [btnCb('🧪 ارسال آزمایشی', 'rptest_' . $pid, 'confirm')],
    ];
    $bs = parseSubProductId($pid);
    $rows[] = [btnUI('back', $bs ? 'sb_' . $bs[0] . '|' . $bs[1] : 'ep_' . $pid, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 📢 ویرایش یکی از دو دکمه گزارش */
function edReportBtn($chatId, $msgId, $pid, $i) {
    $p = Product::get($pid);
    if (!$p) return;
    $r = reportOf($p);
    $b = $r['buttons'][$i] ?? null;
    if (!$b) { edReport($chatId, $msgId, $pid); return; }

    $text  = "🔘 <b>دکمه " . ($i + 1) . " گزارش</b>\n\n";
    $text .= "متن: " . h(trim((string)$b['text']) ?: '—') . "\n";
    $text .= "لینک: " . (trim((string)$b['url']) !== ''
             ? '<code>' . h($b['url']) . '</code>' : '<b>تنظیم نشده</b>') . "\n";
    $text .= "رنگ: " . (styleMap()[$b['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "✨ پریمیوم: " . (!empty($b['icon']) ? '<code>' . h($b['icon']) . '</code>' : '—') . "\n";
    $text .= "وضعیت: " . (!empty($b['on']) ? '✅ روشن' : '❌ خاموش');

    $k = $pid . '|' . $i;
    $rows = [
        [btnCb('✏️ متن', 'rpbt_' . $k, 'admin'), btnCb('🔗 لینک', 'rpbu_' . $k, 'admin')],
        [btnCb('🎨 رنگ', 'rpbc_' . $k, 'admin'), btnCb('✨ پریمیوم', 'rpbi_' . $k, 'admin')],
        [btnCb(!empty($b['on']) ? '❌ خاموش' : '✅ روشن', 'rpbx_' . $k, 'info')],
        [btnUI('back', 'rp_' . $pid, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** بهترین دکمه برای الگو گرفتن — اولی که قیمت دارد، وگرنه اولی */
function sourceSub($bid, $exceptSid = null) {
    $subs = cfg()['buttons'][$bid]['subs'] ?? [];
    $first = null;
    foreach ($subs as $sub) {
        if ($exceptSid !== null && ($sub['id'] ?? '') === $exceptSid) continue;
        if ($first === null) $first = $sub;
        if ((float)($sub['price'] ?? 0) > 0) return $sub;
    }
    return $first;
}

/**
 * 🔄 هماهنگ کردن همه دکمه‌های یک بخش با یک دکمه مرجع
 * قیمت، تعداد، سرعت‌ها و گروه گزارش یکی می‌شود؛ نام و ایموجی و تاپیک دست‌نخورده می‌ماند.
 */
function syncSubs($bid, $srcSid, $withPrice = true) {
    $src = findSub($bid, $srcSid);
    if (!$src) return 0;
    $n = 0;
    foreach ((cfg()['buttons'][$bid]['subs'] ?? []) as $sub) {
        if (($sub['id'] ?? '') === $srcSid) continue;
        subMutate($bid, $sub['id'], function (&$x) use ($src, $withPrice) {
            if ($withPrice) {
                $x['price']    = (float)($src['price'] ?? 0);
                $x['currency'] = (string)($src['currency'] ?? 'تومان');
            }
            $x['flow'] = array_merge(defaultFlow(), $src['flow'] ?? [], ['on' => true, 'ask_admin' => true]);
            if (!is_array($x['report'] ?? null)) $x['report'] = defaultReport();
            $srcR = array_merge(defaultReport(), $src['report'] ?? []);
            $x['report']['on']      = $srcR['on'];
            $x['report']['chat_id'] = $srcR['chat_id'];
            $x['report']['text']    = $srcR['text'];
            $x['report']['buttons'] = $srcR['buttons'];
            // تاپیک هر محصول مخصوص خودش می‌ماند
            if (!empty($src['bot_id'])) $x['bot_id'] = $src['bot_id'];
            $x['on'] = true;
        });
        $n++;
    }
    return $n;
}

function subMutate($bid, $sid, callable $fn) {
    cfgSet(function (&$c) use ($bid, $sid, $fn) {
        if (empty($c['buttons'][$bid]['subs'])) return;
        foreach ($c['buttons'][$bid]['subs'] as $i => $sub) {
            if (($sub['id'] ?? '') === $sid) { $fn($c['buttons'][$bid]['subs'][$i]); return; }
        }
    });
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
    foreach ($slice as $k) {
        $col = UC($k) ? (styleMap()[UC($k)] ?? '') : '—';
        $rows[] = [
            btnCb(UT($k), 'eu_' . $k, 'info'),
            btnCb('🎨 ' . mb_substr($col, 0, 2), 'euc_' . $k, 'admin'),
        ];
    }
    $nav = [];
    if ($page > 0) $nav[] = btnCb('⬅️ قبلی', 'eus_' . ($page - 1), 'nav');
    if (($page + 1) * $per < count($keys)) $nav[] = btnCb('بعدی ➡️', 'eus_' . ($page + 1), 'nav');
    if ($nav) $rows[] = $nav;
    $rows[] = [btnCb(UT('back'), 'etexts', 'nav')];

    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🔤 <b>متن دکمه‌های ثابت</b>\n\nمتن دکمه‌هایی که همه‌جای ربات تکرار می‌شوند.\n" .
        "هنگام فرستادن متن، <b>ایموجی پریمیوم</b> هم بگذارید تا روی دکمه بنشیند.",
        inlineKb($rows));
}

/** برچسب متن‌های ربات‌های زیرمجموعه */
function upTextLabels() {
    return [
        'menu_text'    => '🤖 منوی پنل اپلودر',
        'start_text'   => '👋 پیام شروع',
        'join_text'    => '🔒 متن عضویت اجباری',
        'joined_btn'   => '✅ متن دکمه «عضو شدم»',
        'warn_text'    => '⚠️ هشدار حذف — {sec}',
        'deleted_text' => '🗑 پیام بعد از حذف',
        'expired_text' => '❌ لینک نامعتبر',
        'info_text'    => '📊 اطلاعات فایل',
    ];
}

/** 🤖 تنظیمات پیش‌فرض ربات‌های زیرمجموعه */
function edUploader($chatId, $msgId) {
    $u = cfg()['uploader'];
    $text  = "🤖 <b>تنظیمات ربات‌های زیرمجموعه</b>\n\n";
    $text .= "این تنظیمات روی هر ربات <b>جدیدی</b> که اضافه می‌شود اعمال می‌گردد.\n";
    $text .= "برای ربات‌های موجود، دکمه «اعمال روی همه» را بزنید.\n\n";
    $text .= "⏱ حذف بعد از: <b>{$u['delete_seconds']}</b> ثانیه\n";
    $text .= "🔒 عضویت اجباری: " . (!empty($u['force_join']) ? 'روشن' : 'خاموش') . "\n";
    $text .= "🛡 محافظت فایل: " . (!empty($u['protect_content']) ? 'روشن' : 'خاموش') . "\n";
    $text .= "🤖 ربات‌های ثبت‌شده: <b>" . count(BotManager::all()) . "</b>";

    $rows = [
        [btnCb('📝 متن‌های ربات زیرمجموعه', 'eup_texts', 'admin')],
        [btnCb('⏱ زمان حذف', 'eup_sec', 'admin'),
         btnCb(!empty($u['force_join']) ? '🔒 اجباری: روشن' : '🔒 اجباری: خاموش', 'eup_fj', 'info')],
        [btnCb(!empty($u['protect_content']) ? '🛡 محافظت: روشن' : '🛡 محافظت: خاموش', 'eup_pc', 'info')],
        [btnCb('📋 اعمال روی همه ربات‌ها', 'eup_all', 'confirm')],
        [btnUI('back', 'adm_home', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** 🤖 فهرست متن‌های ربات زیرمجموعه */
function edUpTexts($chatId, $msgId) {
    $rows = [];
    foreach (upTextLabels() as $k => $lbl) $rows[] = [btnCb($lbl, 'eupt_' . $k, 'info')];
    $rows[] = [btnUI('back', 'eupload', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "📝 <b>متن‌های ربات‌های زیرمجموعه</b>\n\n" .
        "متن جدید را با <b>ایموجی پریمیوم</b> و <b>نقل‌قول</b> بفرستید — همه حفظ می‌شود.",
        inlineKb($rows));
}

/** 🤖 نمایش یک متن ربات زیرمجموعه */
function edUpText($chatId, $msgId, $key) {
    $labels = upTextLabels();
    if (!isset($labels[$key])) { edUpTexts($chatId, $msgId); return; }
    $cur = cfg()['uploader'][$key] ?? '';
    $isQ = str_starts_with(trim($cur), '<blockquote');

    $text  = "📝 <b>" . h($labels[$key]) . "</b>\n\n";
    $text .= "<b>پیش‌نمایش:</b>\n" . ($cur !== '' ? $cur : '<i>خالی</i>') . "\n\n";
    $text .= "<b>کد:</b>\n<code>" . h(mb_substr($cur, 0, 600)) . "</code>";

    $rows = [
        [btnCb('✏️ تغییر متن', 'eupe_' . $key, 'confirm')],
        [btnCb($isQ ? '❝ حذف نقل‌قول' : '❝ نقل‌قول', 'eupq_' . $key, 'admin'),
         btnCb('❝ بازشو', 'eupx_' . $key, 'admin')],
        [btnCb('♻️ پیش‌فرض', 'eupr_' . $key, 'reject')],
        [btnUI('back', 'eup_texts', 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/**
 * 🔧 راه‌اندازی خودکار — همه چیز را برای فروش ممبر آماده می‌کند.
 * چیزی را که خودش می‌تواند درست کند، درست می‌کند؛ بقیه را گزارش می‌دهد.
 */
function autoSetup($chatId, $msgId = null) {
    $log = [];

    // ۱) هر زیردکمهٔ شیشه‌ای خودش یک محصول است — فقط جریان سفارشش را روشن کن
    $ready = 0; $noPrice = [];
    foreach (cfg()['buttons'] as $bid => $b) {
        foreach ($b['subs'] ?? [] as $sub) {
            $name = trim((string)($sub['text'] ?? ''));
            if ($name === '') continue;
            if (($sub['action'] ?? '') === 'product' && !empty($sub['value'])
                && Product::get($sub['value'])) continue;   // دستی به محصولی وصل است، دست نزن

            subMutate($bid, $sub['id'], function (&$x) {
                if (!is_array($x['flow'] ?? null)) $x['flow'] = [];
                $x['flow'] = array_merge(defaultFlow(), $x['flow'], ['on' => true, 'ask_admin' => true]);
                if (!isset($x['price']))    $x['price']    = 0;
                if (!isset($x['currency'])) $x['currency'] = cfg()['currency'] ?? 'تومان';
                if (!isset($x['buyers']))   $x['buyers']   = [];
                $x['on'] = true;
            });
            $ready++;
            $sp = subProduct($bid, $sub['id']);
            if ($sp && (float)$sp['price'] <= 0) $noPrice[] = $sp['name'];
        }
    }
    if ($ready) $log[] = "✅ <b>{$ready}</b> دکمه آمادهٔ فروش شد (خود دکمه = محصول)";

    // ۲) محصولات جداگانه (اگر ساخته‌اید) هم جریانشان روشن شود
    $flowOn = 0;
    foreach (Product::all() as $p) {
        if (!empty($p['flow']['on']) && !empty($p['flow']['ask_admin'])) continue;
        mutate('products', function (&$a) use ($p) {
            if (!is_array($a[$p['id']]['flow'] ?? null)) $a[$p['id']]['flow'] = [];
            $a[$p['id']]['flow'] = array_merge(defaultFlow(), $a[$p['id']]['flow'],
                                               ['on' => true, 'ask_admin' => true]);
        });
        $flowOn++;
    }
    if ($flowOn) $log[] = "✅ جریان سفارش برای <b>{$flowOn}</b> محصول جداگانه روشن شد";

    // ۳) دکمه «خرید محصول» روشن باشد
    if (empty(cfg()['buttons']['buy']['on'])) {
        cfgSet(function (&$c) { $c['buttons']['buy']['on'] = true; });
        $log[] = "✅ دکمه «خرید محصول» روشن شد";
    }

    // ۴) گزارش
    $text  = "🔧 <b>راه‌اندازی خودکار</b>\n\n";
    $text .= $log ? implode("\n", $log) . "\n\n" : "همه چیز از قبل درست بود.\n\n";

    $items = saleButtons();
    $text .= "🛒 دکمه‌های فروش: <b>" . count($items) . "</b>\n";
    foreach (array_slice($items, 0, 10) as $sp) {
        $text .= "   " . (!empty($sp['active']) ? '✅' : '❌') . ' ' .
                 h(trim(($sp['emoji'] ?? '') . ' ' . $sp['name'])) . ' — ' .
                 ((float)$sp['price'] > 0 ? fmtNum($sp['price']) . ' ' . h($sp['currency'])
                                          : '<b>قیمت ندارد</b>') . "\n";
    }

    $probs = [];
    if ($noPrice) $probs[] = "قیمت این دکمه‌ها صفر است: <b>" . h(implode('، ', array_unique($noPrice))) . "</b>\n" .
                             "   /panel → 🔘 دکمه‌ها → روی دکمه → 💰 قیمت";
    if (!$items) $probs[] = "هیچ دکمهٔ فروشی ندارید — /panel → 🔘 دکمه‌ها → ➕ زیردکمه";
    $w = cfg()['wallets'];
    if (trim($w['card'] ?? '') === '' && trim($w['usdt'] ?? '') === '')
        $probs[] = "مقصد پرداخت تنظیم نشده — پنل وب → ⚙️ تنظیمات";

    if ($probs) $text .= "\n⚠️ <b>باقی‌مانده:</b>\n• " . implode("\n• ", $probs) . "\n";
    else $text .= "\n✅ <b>آماده فروش است.</b>\n";

    $text .= "\n🧪 تست: /start ← خرید محصول ← روی یکی از دکمه‌ها بزنید\n";
    $text .= "باید بپرسد: لینک کانال ← تعداد ← سرعت ← ادمین کردن ربات ← فاکتور";

    $rows = [[btnCb('🔧 دوباره بررسی کن', 'setup', 'admin')], [btnUI('back', 'adm_home', 'nav')]];
    if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
}

/** 🛒 فهرست محصولات در ربات */
function edProducts($chatId, $msgId) {
    $rows = [];
    foreach (Product::all() as $p) {
        $flow = !empty($p['flow']['on']) ? '🔄' : '';
        $rows[] = [btnCb((!empty($p['active']) ? '✅ ' : '❌ ') .
                         trim(($p['emoji'] ?? '') . ' ' . $p['name']) . ' ' . $flow,
                         'ep_' . $p['id'], 'info')];
    }
    $rows[] = [btnUI('back', 'adm_home', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "🛒 <b>محصولات</b>\n\n🔄 = جریان سفارش روشن است\n\nروی محصول بزنید:",
        inlineKb($rows));
}

/** 🛒 ویرایش یک محصول */
function edProduct($chatId, $msgId, $pid) {
    $p = Product::get($pid);
    if (!$p) { edProducts($chatId, $msgId); return; }
    $f = $p['flow'] ?? [];

    $text  = "🛒 <b>" . h($p['name']) . "</b>\n\n";
    $text .= "💰 قیمت: <b>" . fmtNum($p['price']) . ' ' . h($p['currency']) . "</b>\n";
    $text .= "🎨 رنگ: " . (styleMap()[$p['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "👥 خریداران: " . count($p['buyers']) . "\n";
    $text .= "🔄 جریان سفارش: " . (!empty($f['on']) ? 'روشن' : 'خاموش') . "\n";
    if (!empty($f['on'])) {
        $text .= "   • حداقل: " . number_format((int)$f['min']) . " · حداکثر: " . number_format((int)$f['max']) . "\n";
        $text .= "   • قیمت به ازای هر: " . number_format((int)$f['per']) . "\n";
        $on = 0;
        foreach ($f['speeds'] ?? [] as $sp) if (!isset($sp['on']) || !empty($sp['on'])) $on++;
        $text .= "   • سرعت‌های فعال: {$on}\n";
    }

    $rows = [
        [btnCb('✏️ نام', 'epn_' . $pid, 'admin'), btnCb('💰 قیمت', 'epp_' . $pid, 'admin')],
        [btnCb('😀 ایموجی', 'epe_' . $pid, 'admin'), btnCb('🎨 رنگ', 'epc_' . $pid, 'admin')],
        [btnCb(!empty($f['on']) ? '🔄 جریان: روشن' : '🔄 جریان: خاموش', 'epf_' . $pid, 'info')],
    ];
    if (!empty($f['on'])) {
        $rows[] = [btnCb('⚡️ سرعت‌ها', 'eps_' . $pid, 'confirm')];
        $rows[] = [btnCb('🔢 حداقل', 'epmin_' . $pid, 'admin'),
                   btnCb('🔢 حداکثر', 'epmax_' . $pid, 'admin'),
                   btnCb('➗ هر چند', 'epper_' . $pid, 'admin')];
    }
    $rows[] = [btnCb(!empty($p['active']) ? '❌ غیرفعال' : '✅ فعال', 'epa_' . $pid, 'info')];
    $rows[] = [btnUI('back', 'eprods', 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** ⚡️ سرعت‌های یک محصول */
function edSpeeds($chatId, $msgId, $pid) {
    $p = Product::get($pid);
    if (!$p) { edProducts($chatId, $msgId); return; }
    $rows = [];
    foreach ($p['flow']['speeds'] ?? [] as $sp) {
        $col = styleMap()[$sp['color'] ?? 'none'] ?? '';
        $on  = (!isset($sp['on']) || !empty($sp['on'])) ? '✅' : '❌';
        $rows[] = [btnCb("$on " . speedLabel($sp) . ' ×' . $sp['mult'] . '  ' . mb_substr($col, 0, 2),
                         'esp_' . $pid . '|' . $sp['id'], 'info')];
    }
    $rows[] = [btnCb('📐 چیدمان', 'espl_' . $pid, 'admin')];
    $bs = parseSubProductId($pid);
    $rows[] = [btnUI('back', $bs ? 'sb_' . $bs[0] . '|' . $bs[1] : 'ep_' . $pid, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        "⚡️ <b>سرعت‌های سفارش</b>\n\nهر سرعت یک ضریب قیمت دارد.\nروی هرکدام بزنید:",
        inlineKb($rows));
}

/** ⚡️ ویرایش یک سرعت */
function edSpeed($chatId, $msgId, $pid, $sid) {
    $p = Product::get($pid);
    $sp = null;
    foreach ($p['flow']['speeds'] ?? [] as $x) if ($x['id'] === $sid) $sp = $x;
    if (!$sp) { edSpeeds($chatId, $msgId, $pid); return; }

    $pd = (int)($sp['per_day'] ?? 0);
    $text  = "⚡️ <b>" . h(speedLabel($sp)) . "</b>\n\n";
    $text .= "متن دکمه: <code>" . h(speedBtnLabel($sp)) . "</code>\n";
    $text .= "ضریب قیمت: <b>×" . $sp['mult'] . "</b>\n";
    $text .= "قیمت نهایی هر " . number_format((int)$p['flow']['per']) . ": <b>" .
             fmtNum((float)$p['price'] * (float)$sp['mult']) . ' ' . h($p['currency']) . "</b>\n";
    $text .= "🚀 سرعت تحویل: <b>" . ($pd > 0 ? number_format($pd) . " نفر در روز" : 'تنظیم نشده') . "</b>\n";
    $text .= "⏳ نمونه زمان (۵٬۰۰۰ نفر): <b>" . h(speedEta($sp, 5000)) . "</b>\n";
    $text .= "رنگ: " . (styleMap()[$sp['color'] ?? 'none'] ?? '—') . "\n";
    $text .= "✨ پریمیوم: " . (!empty($sp['icon']) ? '<code>' . h($sp['icon']) . '</code>' : '—') . "\n";
    $text .= "\n💬 توضیح زیر دکمه: " . (trim((string)($sp['desc'] ?? '')) !== ''
             ? h($sp['desc']) : '—');

    $k = $pid . '|' . $sid;
    $rows = [
        [btnCb('✏️ متن دکمه', 'espt_' . $k, 'admin'), btnCb('😀 ایموجی', 'espe_' . $k, 'admin')],
        [btnCb('🎨 رنگ', 'espc_' . $k, 'admin'), btnCb('✨ پریمیوم', 'espi_' . $k, 'admin')],
        [btnCb('✖️ ضریب قیمت', 'espm_' . $k, 'admin'), btnCb('🚀 نفر در روز', 'espd_' . $k, 'admin')],
        [btnCb('💬 توضیح', 'espn_' . $k, 'admin')],
        [btnCb((!isset($sp['on']) || !empty($sp['on'])) ? '❌ خاموش' : '✅ روشن', 'espx_' . $k, 'info')],
        [btnUI('back', 'eps_' . $pid, 'nav')],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

/** تغییر یک فیلد از flow — چه محصول واقعی، چه دکمه‌ای که خودش محصول است */
function flowMutate($pid, callable $fn) {
    if ($bs = parseSubProductId($pid)) {
        subMutate($bs[0], $bs[1], function (&$x) use ($fn) {
            if (!is_array($x['flow'] ?? null)) $x['flow'] = [];
            $x['flow'] = array_merge(defaultFlow(), $x['flow']);
            $fn($x['flow']);
        });
        return;
    }
    mutate('products', function (&$a) use ($pid, $fn) {
        if (!isset($a[$pid])) return;
        if (!is_array($a[$pid]['flow'] ?? null)) $a[$pid]['flow'] = defaultFlow();
        $fn($a[$pid]['flow']);
    });
}

/** تغییر یک فیلد سطح‌بالای محصول (قیمت، نام، …) */
function prodMutate($pid, callable $fn) {
    if ($bs = parseSubProductId($pid)) { subMutate($bs[0], $bs[1], $fn); return; }
    mutate('products', function (&$a) use ($pid, $fn) { if (isset($a[$pid])) $fn($a[$pid]); });
}

function speedMutate($pid, $sid, callable $fn) {
    if (parseSubProductId($pid)) {
        flowMutate($pid, function (&$f) use ($sid, $fn) {
            foreach ($f['speeds'] ?? [] as $i => $sp) {
                if (($sp['id'] ?? '') === $sid) { $fn($f['speeds'][$i]); return; }
            }
        });
        return;
    }
    mutate('products', function (&$a) use ($pid, $sid, $fn) {
        if (empty($a[$pid]['flow']['speeds'])) return;
        foreach ($a[$pid]['flow']['speeds'] as $i => $sp) {
            if (($sp['id'] ?? '') === $sid) { $fn($a[$pid]['flow']['speeds'][$i]); return; }
        }
    });
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
        if ($data === 'tariff') { answerCb(BOT_TOKEN, $cbId); showTariff($uid, $chatId); return; }

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

        // --- پشتیبانی ---
        if ($data === 'sup_list') {
            answerCb(BOT_TOKEN, $cbId);
            showSupportIndirect($uid, $chatId, $msgId);
            return;
        }
        if ($data === 'sup_direct') { answerCb(BOT_TOKEN, $cbId); return; }

        // --- دکمه شیشه‌ای زیرمجموعه ---
        if (str_starts_with($data, 'sub_')) {
            $rest = substr($data, 4);
            $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            $bid = substr($rest, 0, $pos);
            $sid = substr($rest, $pos + 1);
            $sub = findSub($bid, $sid);
            answerCb(BOT_TOKEN, $cbId);
            if (!$sub) return;

            if (($sub['action'] ?? '') === 'section') {
                runMenuAction($sub['value'] ?? '', $uid, $chatId, $uname, $fname);
                return;
            }
            if (($sub['action'] ?? '') === 'url') {
                sendMsg(BOT_TOKEN, $chatId, (string)($sub['value'] ?? ''));
                return;
            }
            // دکمه‌ای که واقعا متن دارد، همان متن را نشان می‌دهد
            if (($sub['action'] ?? '') === 'text' && !isPlaceholder($sub['value'] ?? '')
                && trim((string)($sub['value'] ?? '')) !== '') {
                sendMsg(BOT_TOKEN, $chatId, $sub['value']);
                return;
            }

            // 🔘 خودِ دکمه یک محصول است — یک‌راست سراغ گرفتن لینک کانال
            $p = (($sub['action'] ?? '') === 'product' && !empty($sub['value']))
                ? Product::get($sub['value'])         // اگر دستی به محصولی وصل شده، همان
                : null;
            if (!$p) $p = subProduct($bid, $sid, $sub);   // وگرنه خود دکمه

            if ($p) {
                if (empty($p['active'])) { sendMsg(BOT_TOKEN, $chatId, T('buy_empty')); return; }
                if (!empty($p['flow']['on'])) { flowStart($uid, $chatId, $p); return; }
                showOneProduct($uid, $chatId, $p);
                return;
            }
            sendMsg(BOT_TOKEN, $chatId, T('buy_empty'));
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

            if (!empty($p['flow']['on'])) { flowStart($uid, $chatId, $p); return; }

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

        // --- جریان سفارش ---
        if (str_starts_with($data, 'fsp_')) {
            $sid = substr($data, 4);
            $st = getState($uid);
            if (!$st || $st['action'] !== 'flow') { answerCb(BOT_TOKEN, $cbId, 'منقضی شد', true); return; }
            $sd = $st['data'];
            $p = Product::get($sd['pid']);
            $chosen = null;
            foreach (($p['flow']['speeds'] ?? []) as $sp) if ($sp['id'] === $sid) $chosen = $sp;
            if (!$chosen) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            $sd['data']['speed']   = speedLabel($chosen);
            $sd['data']['mult']    = (float)$chosen['mult'];
            $sd['data']['per_day'] = (int)($chosen['per_day'] ?? 0);
            $sd['data']['eta']     = speedEta($chosen, (int)($sd['data']['qty'] ?? 0));
            setState($uid, 'flow', $sd);
            answerCb(BOT_TOKEN, $cbId);
            flowNext($uid, $chatId, 'admin');
            return;
        }
        if ($data === 'fadm') {
            [$ok, $title] = flowCheckAdmin($uid);
            if (!$ok) {
                answerCb(BOT_TOKEN, $cbId, '❌ هنوز ادمین نشده', true);
                $un = botUsername();
                $rows = [];
                if ($un) $rows[] = [btnUrl('➕ افزودن ربات به کانال', "https://t.me/{$un}?startchannel&admin=invite_users+promote_members", 'buy')];
                $rows[] = [btnUI('did_admin', 'fadm', 'confirm')];
                $rows[] = [btnUI('cancel', 'cancel', 'cancel')];
                panelShow($uid, $chatId, 'shop',
                    T('flow_addbot') . "\n\n" . T('flow_admin_no', ['bot' => '@' . h($un)]),
                    inlineKb($rows));
                return;
            }
            answerCb(BOT_TOKEN, $cbId, '✅ تایید شد');
            flowInvoice($uid, $chatId);
            return;
        }

        if (str_starts_with($data, 'trk_')) {
            answerCb(BOT_TOKEN, $cbId);
            showOrderStatus($uid, $chatId, substr($data, 4));
            return;
        }
        // 💳 شارژ سریع با مبلغ پیشنهادی
        if (str_starts_with($data, 'topupq_')) {
            $amt = (float)substr($data, 7);
            answerCb(BOT_TOKEN, $cbId);
            if ($amt <= 0) return;
            clearState($uid);
            createOrderAndAsk($uid, $chatId, $uname, 'topup', null, $amt, 'تومان', '➕ شارژ کیف پول');
            return;
        }
        if (str_starts_with($data, 'topup_')) {
            $need = (float)substr($data, 6);
            answerCb(BOT_TOKEN, $cbId);
            startTopup($uid, $chatId, null, $need, 0, null);
            return;
        }

        // 🧾 «ارسال رسید» برای پرداختی که هنوز سفارشی ندارد
        if ($data === 'payrcpt') {
            $st = getState($uid);
            answerCb(BOT_TOKEN, $cbId);
            if (!$st || $st['action'] !== 'pay_wait') {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ این پرداخت منقضی شده. لطفا دوباره سفارش دهید.", mainKeyboard());
                return;
            }
            setState($uid, 'pay_receipt', $st['data']);
            sendMsg(BOT_TOKEN, $chatId,
                "🧾 <b>رسید پرداخت</b>\n\n" .
                "عکس رسید یا کد پیگیری بانکی را بفرستید.\n" .
                "به‌محض دریافت، سفارش شما ثبت می‌شود.",
                inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
            return;
        }

        if ($data === 'track_ask') {
            answerCb(BOT_TOKEN, $cbId);
            setState($uid, 'track');
            sendMsg(BOT_TOKEN, $chatId,
                "🔍 <b>کد پیگیری</b> سفارش را بفرستید.\n\n" .
                "نمونه: <code>or_tjwodm15a1a3</code>",
                inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
            return;
        }

        if ($data === 'fok') {
            answerCb(BOT_TOKEN, $cbId);
            flowFinish($uid, $chatId, $uname);
            return;
        }
        if (str_starts_with($data, 'fwpay_') || str_starts_with($data, 'fdpay_')) {
            $wallet = str_starts_with($data, 'fwpay_');
            $rest = substr($data, 6);
            $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            $pid = substr($rest, 0, $pos);
            $amt = (float)substr($rest, $pos + 1);
            $p = Product::get($pid);
            if (!$p || $amt <= 0) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }

            $mst = getState($uid);
            $meta = ($mst && $mst['action'] === 'flow_meta') ? $mst['data'] : [];
            if (!$meta) {
                $pend = getUser($uid)['pending'] ?? null;
                if ($pend && ($pend['pid'] ?? '') === $pid) $meta = $pend['meta'] ?? [];
            }

            if ($wallet) {
                $bal = (float)(getUser($uid)['balance'] ?? 0);
                if ($bal < $amt) {
                    $short = $amt - $bal;
                    // پیام کوچک روی خود دکمه — دقیقا مثل چیزی که خواستید
                    answerCb(BOT_TOKEN, $cbId,
                        "❌ موجودی ناکافی!\n" .
                        "💰 موجودی: " . fmtNum($bal) . " تومان\n" .
                        "💳 مبلغ نهایی: " . fmtNum($amt) . " تومان\n" .
                        "➖ کمبود: " . fmtNum($short) . " تومان", true);
                    // و یک‌راست ببرش سراغ شارژ کیف پول
                    startTopup($uid, $chatId, null, (int)ceil($short), $amt, $bal);
                    return;
                }
                addBalance($uid, -$amt);
                clearState($uid);
                mutate('users', function (&$a) use ($uid) { unset($a[(string)$uid]['pending']); });
                $oid = Order::create($uid, $uname, 'product', $pid, $amt, $p['currency'], $meta);
                Order::attachReceipt($oid, 'text', 'پرداخت از کیف پول');
                Order::approve($oid, ADMIN_ID);
                answerCb(BOT_TOKEN, $cbId, '✅ ثبت شد');
                $od = Order::get($oid);
                sendMsg(BOT_TOKEN, $chatId, orderDoneText($od), orderDoneKb($od));
                announceSale($od);
                reportSale($od);
                return;
            }
            answerCb(BOT_TOKEN, $cbId);
            // ⚠️ سفارش هنوز ساخته نمی‌شود — فقط وقتی رسید بیاید
            askDirectPay($uid, $chatId, $p, $amt, $meta);
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
            $od = Order::get($oid);
            sendMsg(BOT_TOKEN, $chatId, orderDoneText($od), orderDoneKb($od));
            announceSale($od);
            reportSale($od);
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
            panelShow($uid, $chatId, $o['type'] === 'topup' ? 'wallet' : 'shop',
                T('receipt_ask'), inlineKb([[btnUI('cancel', 'cancel', 'cancel')]]));
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
        $adminPrefixes = ['aok_', 'ano_', 'adm_', 'eb', 'et', 'eg', 'eu', 'sb', 'ep', 'esp',
                          'rp', 'tf', 'reply_', 'setup'];
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

        // ---------- زیردکمه‌های شیشه‌ای ----------
        if (str_starts_with($data, 'sbs_')) { answerCb(BOT_TOKEN, $cbId); edSubs($chatId, $msgId, substr($data, 4)); return; }
        if (str_starts_with($data, 'sbnew_')) {
            $bid = substr($data, 6);
            if (!isset(cfg()['buttons'][$bid])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'sb_new', ['btn' => $bid]);
            sendMsg(BOT_TOKEN, $chatId, "➕ متن دکمه شیشه‌ای جدید را بفرستید (ایموجی پریمیوم مجاز است):",
                inlineKb([[btnUI('cancel', 'sbs_' . $bid, 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'sblay_')) {
            $bid = substr($data, 6);
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'sb_layout', ['btn' => $bid]);
            sendMsg(BOT_TOKEN, $chatId,
                "📐 چیدمان دکمه‌های شیشه‌ای را بفرستید.\n\nمثال: <code>2,1</code> یعنی ۲ تا بالا، ۱ تا پایین.",
                inlineKb([[btnUI('cancel', 'sbs_' . $bid, 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'sbsync_')) {
            $bid = substr($data, 7);
            $subs = cfg()['buttons'][$bid]['subs'] ?? [];
            answerCb(BOT_TOKEN, $cbId);
            if (count($subs) < 2) return;
            $rows = [];
            foreach ($subs as $sub) {
                $sp = subProduct($bid, $sub['id'], $sub);
                $rows[] = [btnCb(trim(($sub['emoji'] ?? '') . ' ' . $sub['text']) .
                                 ' — ' . ((float)$sp['price'] > 0 ? fmtNum($sp['price']) : 'بدون قیمت'),
                                 'sbsyncdo_' . $bid . '|' . $sub['id'], 'buy')];
            }
            $rows[] = [btnUI('back', 'sbs_' . $bid, 'nav')];
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "🔄 <b>هماهنگ کردن دکمه‌ها</b>\n\n" .
                "کدام دکمه درست تنظیم شده؟ روی آن بزنید تا <b>بقیه هم مثل آن شوند</b>:\n\n" .
                "کپی می‌شود: 💰 قیمت · 👥 تعداد · ⚡️ سرعت‌ها · 📢 گروه گزارش · 🤖 ربات تحویل\n" .
                "دست‌نخورده می‌ماند: نام · ایموجی · 🧵 شماره تاپیک هر محصول",
                inlineKb($rows));
            return;
        }
        if (str_starts_with($data, 'sbsyncdo_')) {
            $rest = substr($data, 9); $pos = strrpos($rest, '|');
            answerCb(BOT_TOKEN, $cbId);
            if ($pos === false) return;
            $bid = substr($rest, 0, $pos); $sid = substr($rest, $pos + 1);
            $n = syncSubs($bid, $sid);
            $src = findSub($bid, $sid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ <b>{$n}</b> دکمه با «" . h($src['text'] ?? '—') . "» هماهنگ شد.\n\n" .
                "یادتان نرود برای هر محصول <b>شماره تاپیک</b> گزارشش را جدا بگذارید.");
            edSubs($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'sbauto_')) {
            $bid = substr($data, 7);
            $done = 0; $miss = [];
            foreach ((cfg()['buttons'][$bid]['subs'] ?? []) as $sub) {
                $mp = productByName($sub['text'] ?? '');
                if ($mp) {
                    subMutate($bid, $sub['id'], function (&$x) use ($mp) {
                        $x['action'] = 'product';
                        $x['value']  = $mp['id'];
                    });
                    $done++;
                } elseif (($sub['action'] ?? '') !== 'product') {
                    $miss[] = $sub['text'];
                }
            }
            answerCb(BOT_TOKEN, $cbId, "✅ {$done} دکمه وصل شد", true);
            if ($miss) {
                sendMsg(BOT_TOKEN, $chatId,
                    "⚠️ برای این دکمه‌ها محصول هم‌نام پیدا نشد:\n• " . h(implode("\n• ", $miss)) .
                    "\n\nیا محصولی با همین نام بسازید، یا دستی وصلشان کنید.");
            }
            edSubs($chatId, $msgId, $bid);
            return;
        }
        if (str_starts_with($data, 'sbpick_')) {
            $rest = substr($data, 7); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            answerCb(BOT_TOKEN, $cbId);
            edSubPick($chatId, $msgId, substr($rest, 0, $pos), substr($rest, $pos + 1));
            return;
        }
        if (str_starts_with($data, 'sbp_')) {
            $parts = explode('|', substr($data, 4));
            if (count($parts) !== 3) { answerCb(BOT_TOKEN, $cbId); return; }
            [$bid, $sid, $pid] = $parts;
            if (!Product::get($pid)) { answerCb(BOT_TOKEN, $cbId, 'محصول پیدا نشد', true); return; }
            subMutate($bid, $sid, function (&$x) use ($pid) {
                $x['action'] = 'product';
                $x['value']  = $pid;
            });
            answerCb(BOT_TOKEN, $cbId, '✅ وصل شد');
            edSub($chatId, $msgId, $bid, $sid);
            return;
        }
        if (str_starts_with($data, 'sb_')) {
            $rest = substr($data, 3); $pos = strrpos($rest, '|');
            if ($pos !== false) { answerCb(BOT_TOKEN, $cbId); edSub($chatId, $msgId, substr($rest, 0, $pos), substr($rest, $pos + 1)); return; }
        }

        if (str_starts_with($data, 'sbbot_')) {
            $rest = substr($data, 6); $pos = strrpos($rest, '|');
            answerCb(BOT_TOKEN, $cbId);
            if ($pos === false) return;
            edProdBot($chatId, $msgId, subProductId(substr($rest, 0, $pos), substr($rest, $pos + 1)));
            return;
        }
        if (str_starts_with($data, 'sbbotset_')) {
            $rest = substr($data, 9); $pos = strrpos($rest, '|');
            answerCb(BOT_TOKEN, $cbId, '✅');
            if ($pos === false) return;
            $pid = substr($rest, 0, $pos); $bidNew = substr($rest, $pos + 1);
            prodMutate($pid, function (&$x) use ($bidNew) { $x['bot_id'] = $bidNew !== '' ? $bidNew : null; });
            edProdBot($chatId, $msgId, $pid);
            return;
        }
        if (str_starts_with($data, 'sbcode_')) {
            $pid = substr($data, 7);
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'sb_code', ['pid' => $pid]);
            sendMsg(BOT_TOKEN, $chatId,
                "🔗 کد لینک محتوا را بفرستید (خط تیره = حذف).\n\nاز پنل وب ← 🤖 ربات‌ها می‌گیرید.",
                inlineKb([[btnUI('cancel', 'sbbot_' . $pid, 'cancel')]]));
            return;
        }

        // 📋 لیست تعرفه‌ها
        if ($data === 'adm_tariff') { answerCb(BOT_TOKEN, $cbId); admTariff($chatId, $msgId); return; }
        if ($data === 'adm_reports') { answerCb(BOT_TOKEN, $cbId); admReports($chatId, $msgId); return; }
        if ($data === 'adm_locks')   { answerCb(BOT_TOKEN, $cbId); admLocks($chatId, $msgId); return; }
        if ($data === 'locks_rebalance') {
            $n = 0;
            foreach (Campaign::all() as $c) {
                if (empty($c['active']) || Campaign::isDone($c)) continue;
                mutate('campaigns', function (&$a) use ($c) { if (isset($a[$c['id']])) $a[$c['id']]['bots'] = []; });
                assignCampaignBots($c['id']);
                $n++;
            }
            answerCb(BOT_TOKEN, $cbId, "✅ {$n} کمپین پخش شد");
            admLocks($chatId, $msgId);
            return;
        }
        if ($data === 'tfx' || $data === 'tfauto') {
            $f = $data === 'tfx' ? 'on' : 'auto';
            cfgSet(function (&$c) use ($f) { $c['tariff'][$f] = empty($c['tariff'][$f]); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            admTariff($chatId, $msgId);
            return;
        }
        if ($data === 'tfbc' || $data === 'tfkc') {
            $w = $data === 'tfbc' ? 'btn' : 'back';
            cfgSet(function (&$c) use ($w) {
                $c['tariff'][$w]['color'] = nextStyle($c['tariff'][$w]['color'] ?? 'none');
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            admTariff($chatId, $msgId);
            return;
        }
        if ($data === 'tfprev') { answerCb(BOT_TOKEN, $cbId); showTariff($uid, $chatId); return; }
        foreach ([['tft',  'tf_text',  "✏️ متن لیست تعرفه‌ها را بفرستید.\n\n✨ ایموجی پریمیوم و نقل‌قول پشتیبانی می‌شود.\n\nاگر <code>{list}</code> بنویسید، جدول خودکار قیمت‌ها دقیقا همان‌جا می‌نشیند."],
                  ['tfbt', 'tf_btn',   '🔘 متن دکمه «لیست تعرفه‌ها»:'],
                  ['tfbe', 'tf_bemoji','😀 ایموجی دکمه تعرفه (خط تیره = حذف):'],
                  ['tfkt', 'tf_back',  '◀️ متن دکمه برگشت:'],
                  ['tfke', 'tf_kemoji','😀 ایموجی دکمه برگشت (خط تیره = حذف):']] as [$d0, $act, $ask]) {
            if ($data !== $d0) continue;
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, $act, []);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'adm_tariff', 'cancel')]]));
            return;
        }
        if ($data === 'rpall') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'rp_allchat', []);
            sendMsg(BOT_TOKEN, $chatId,
                "👥 آیدی گروه گزارش را بفرستید.\n\n" .
                "روی <b>همه محصولات</b> اعمال می‌شود و همه روشن می‌شوند.\n" .
                "بعد برای هر محصول <b>شماره تاپیک</b> خودش را جدا بگذارید.\n\n" .
                "مثال: <code>-1001234567890</code>",
                inlineKb([[btnUI('cancel', 'adm_reports', 'cancel')]]));
            return;
        }

        // 📢 گزارش خرید
        if (str_starts_with($data, 'sbrp_')) {
            $rest = substr($data, 5); $pos = strrpos($rest, '|');
            answerCb(BOT_TOKEN, $cbId);
            if ($pos === false) return;
            $bid = substr($rest, 0, $pos); $sid = substr($rest, $pos + 1);
            if (!findSub($bid, $sid)) return;
            edReport($chatId, $msgId, subProductId($bid, $sid));
            return;
        }
        if (str_starts_with($data, 'rp_')) {
            answerCb(BOT_TOKEN, $cbId); edReport($chatId, $msgId, substr($data, 3)); return;
        }
        if (str_starts_with($data, 'rpx_')) {
            $pid = substr($data, 4);
            reportMutate($pid, function (&$r) { $r['on'] = empty($r['on']); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edReport($chatId, $msgId, $pid);
            return;
        }
        if (str_starts_with($data, 'rptest_')) {
            $pid = substr($data, 7);
            $p = Product::get($pid);
            answerCb(BOT_TOKEN, $cbId);
            if (!$p) return;
            reportSale([
                'id' => 'or_TEST000000', 'type' => 'product', 'product_id' => $pid,
                'user_id' => $uid, 'username' => $uname, 'amount' => (float)$p['price'],
                'currency' => $p['currency'], 'created_at' => nowStr(),
                'meta' => ['link' => 'https://t.me/example', 'qty' => 5000,
                           'speed' => 'نمونه', 'per_day' => 5000, 'eta' => 'حدود 1 روز',
                           'chat_title' => 'کانال نمونه'],
            ], true);
            $rr = reportOf($p);
            sendMsg(BOT_TOKEN, $chatId,
                trim((string)$rr['chat_id']) === ''
                    ? "⚠️ اول آیدی گروه را تنظیم کنید."
                    : "🧪 گزارش آزمایشی فرستاده شد. اگر نرسید، پیام خطا را ببینید." .
                      (empty($rr['on']) ? "\n\n⚠️ ضمنا گزارش این محصول <b>خاموش</b> است؛ خریدهای واقعی گزارش نمی‌شوند." : ''));
            return;
        }
        if (str_starts_with($data, 'rpb_')) {
            $rest = substr($data, 4); $pos = strrpos($rest, '|');
            answerCb(BOT_TOKEN, $cbId);
            if ($pos === false) return;
            edReportBtn($chatId, $msgId, substr($rest, 0, $pos), (int)substr($rest, $pos + 1));
            return;
        }
        foreach ([['rpbc_', 'color'], ['rpbx_', 'on']] as [$pref, $what]) {
            if (!str_starts_with($data, $pref)) continue;
            $rest = substr($data, strlen($pref)); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            $pid = substr($rest, 0, $pos); $i = (int)substr($rest, $pos + 1);
            reportMutate($pid, function (&$r) use ($i, $what) {
                if (!isset($r['buttons'][$i])) return;
                if ($what === 'color') $r['buttons'][$i]['color'] = nextStyle($r['buttons'][$i]['color'] ?? 'none');
                else $r['buttons'][$i]['on'] = empty($r['buttons'][$i]['on']);
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edReportBtn($chatId, $msgId, $pid, $i);
            return;
        }
        foreach ([['rpc_', 'rp_chat', "👥 آیدی گروه را بفرستید.\n\nمثال: <code>-1001234567890</code>\n\nربات باید در آن گروه ادمین باشد."],
                  ['rpth_', 'rp_thread', "🧵 شماره تاپیک را بفرستید (۰ = بدون تاپیک).\n\nاز لینک پیام تاپیک، عدد بعد از آیدی گروه."],
                  ['rpt_', 'rp_text', "✏️ متن گزارش را بفرستید.\n\n✨ ایموجی پریمیوم و نقل‌قول (quote) پشتیبانی می‌شود.\n\nمتغیرها:\n<code>{product} {emoji} {qty} {speed} {per_day} {eta}</code>\n<code>{amount} {currency} {code} {link} {channel} {user} {user_id} {date} {delivered}</code>"]] as $it) {
            [$pref, $act, $ask] = $it;
            if (!str_starts_with($data, $pref)) continue;
            $pid = substr($data, strlen($pref));
            if (!Product::get($pid)) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, $act, ['pid' => $pid]);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'rp_' . $pid, 'cancel')]]));
            return;
        }
        foreach ([['rpbt_', 'rp_btn_text', '✏️ متن دکمه (ایموجی پریمیوم مجاز):'],
                  ['rpbu_', 'rp_btn_url', "🔗 لینک دکمه را بفرستید.\n\nمثال: <code>https://t.me/YourBot</code>"],
                  ['rpbi_', 'rp_btn_icon', '✨ ایموجی پریمیوم بفرستید یا کدش را (خط تیره = حذف):']] as $it) {
            [$pref, $act, $ask] = $it;
            if (!str_starts_with($data, $pref)) continue;
            $rest = substr($data, strlen($pref)); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, $act, ['pid' => substr($rest, 0, $pos), 'i' => (int)substr($rest, $pos + 1)]);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'rpb_' . $rest, 'cancel')]]));
            return;
        }

        // ⚡️ سرعت‌های یک دکمه‌ای که خودش محصول است
        if (str_starts_with($data, 'sbsp_')) {
            $rest = substr($data, 5); $pos = strrpos($rest, '|');
            answerCb(BOT_TOKEN, $cbId);
            if ($pos === false) return;
            $bid = substr($rest, 0, $pos); $sid = substr($rest, $pos + 1);
            if (!findSub($bid, $sid)) return;
            edSpeeds($chatId, $msgId, subProductId($bid, $sid));
            return;
        }

        foreach ([['sbt_', 'sb_text', '✏️ متن جدید را بفرستید (ایموجی پریمیوم مجاز است):'],
                  ['sbe_', 'sb_emoji', '😀 ایموجی را بفرستید (خط تیره = حذف):'],
                  ['sbi_', 'sb_icon', '✨ ایموجی پریمیوم بفرستید یا کدش را (خط تیره = حذف):'],
                  ['sbr_', 'sb_row', '📐 شماره ردیف (۰ = خودکار):'],
                  ['sbo_', 'sb_order', '🔢 شماره ترتیب:'],
                  ['sbv_', 'sb_value', "📝 مقدار را بفرستید.\n\nبسته به نوع: متن نمایشی، آدرس لینک، شناسه محصول، یا نام بخش."],
                  ['sbpr_', 'sb_price', "💰 قیمت هر <b>۱۰۰۰</b> عدد را بفرستید (فقط عدد):"],
                  ['sbm_', 'sb_minmax', "👥 حداقل و حداکثر تعداد را بفرستید، با خط تیره.\n\nمثال: <code>1000-100000</code>"]] as $it) {
            [$pref, $act, $ask] = $it;
            if (!str_starts_with($data, $pref)) continue;
            $rest = substr($data, strlen($pref)); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            $bid = substr($rest, 0, $pos); $sid = substr($rest, $pos + 1);
            if (!findSub($bid, $sid)) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, $act, ['btn' => $bid, 'sub' => $sid]);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'sb_' . $bid . '|' . $sid, 'cancel')]]));
            return;
        }

        foreach ([['sbc_', 'color'], ['sbx_', 'on'], ['sba_', 'action'], ['sbd_', 'del']] as [$pref, $what]) {
            if (!str_starts_with($data, $pref)) continue;
            $rest = substr($data, strlen($pref)); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            $bid = substr($rest, 0, $pos); $sid = substr($rest, $pos + 1);

            if ($what === 'del') {
                cfgSet(function (&$c) use ($bid, $sid) {
                    if (empty($c['buttons'][$bid]['subs'])) return;
                    $c['buttons'][$bid]['subs'] = array_values(array_filter(
                        $c['buttons'][$bid]['subs'], fn($x) => ($x['id'] ?? '') !== $sid));
                });
                answerCb(BOT_TOKEN, $cbId, 'حذف شد');
                edSubs($chatId, $msgId, $bid);
                return;
            }
            if ($what === 'color') subMutate($bid, $sid, function (&$x) { $x['color'] = nextStyle($x['color'] ?? 'none'); });
            if ($what === 'on')    subMutate($bid, $sid, function (&$x) { $x['on'] = empty($x['on']); });
            if ($what === 'action') subMutate($bid, $sid, function (&$x) {
                $order = ['text', 'url', 'product', 'section'];
                $i = array_search($x['action'] ?? 'text', $order, true);
                $x['action'] = $order[(($i === false ? 0 : $i) + 1) % count($order)];
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edSub($chatId, $msgId, $bid, $sid);
            return;
        }

        if ($data === 'setup') { answerCb(BOT_TOKEN, $cbId, '🔧'); autoSetup($chatId, $msgId); return; }

        // ---------- محصولات ----------
        if ($data === 'eprods') { answerCb(BOT_TOKEN, $cbId); edProducts($chatId, $msgId); return; }
        if (str_starts_with($data, 'eps_'))  { answerCb(BOT_TOKEN, $cbId); edSpeeds($chatId, $msgId, substr($data, 4)); return; }
        if (str_starts_with($data, 'esp_')) {
            $rest = substr($data, 4); $pos = strrpos($rest, '|');
            if ($pos !== false) { answerCb(BOT_TOKEN, $cbId); edSpeed($chatId, $msgId, substr($rest, 0, $pos), substr($rest, $pos + 1)); return; }
        }
        if (str_starts_with($data, 'ep_'))   { answerCb(BOT_TOKEN, $cbId); edProduct($chatId, $msgId, substr($data, 3)); return; }

        foreach ([['epn_', 'ep_name', '✏️ نام جدید محصول:'],
                  ['epp_', 'ep_price', '💰 قیمت جدید (عدد):'],
                  ['epe_', 'ep_emoji', '😀 ایموجی محصول (خط تیره = حذف):'],
                  ['epmin_', 'ep_min', '🔢 حداقل تعداد سفارش:'],
                  ['epmax_', 'ep_max', '🔢 حداکثر تعداد سفارش:'],
                  ['epper_', 'ep_per', '➗ قیمت به ازای هر چند نفر؟ (مثلا 1000):'],
                  ['espl_', 'ep_slayout', '📐 چیدمان دکمه‌های سرعت (مثلا 1 یا 3):']] as $it) {
            [$pref, $act, $ask] = $it;
            if (!str_starts_with($data, $pref)) continue;
            $pid = substr($data, strlen($pref));
            if (!Product::get($pid)) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, $act, ['pid' => $pid]);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'ep_' . $pid, 'cancel')]]));
            return;
        }

        if (str_starts_with($data, 'epc_') || str_starts_with($data, 'epa_') || str_starts_with($data, 'epf_')) {
            $what = substr($data, 0, 4);
            $pid = substr($data, 4);
            if (!Product::get($pid)) { answerCb(BOT_TOKEN, $cbId, 'پیدا نشد', true); return; }
            mutate('products', function (&$a) use ($pid, $what) {
                if ($what === 'epc_') $a[$pid]['color']  = nextStyle($a[$pid]['color'] ?? 'none');
                if ($what === 'epa_') $a[$pid]['active'] = empty($a[$pid]['active']);
                if ($what === 'epf_') {
                    if (!isset($a[$pid]['flow'])) $a[$pid]['flow'] = defaultConfig()['dummy'] ?? [];
                    $a[$pid]['flow']['on'] = empty($a[$pid]['flow']['on']);
                }
            });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edProduct($chatId, $msgId, $pid);
            return;
        }

        foreach ([['espt_', 'sp_text', '✏️ متن سرعت (ایموجی پریمیوم مجاز):'],
                  ['espe_', 'sp_emoji', '😀 ایموجی سرعت (خط تیره = حذف):'],
                  ['espi_', 'sp_icon', '✨ ایموجی پریمیوم بفرستید یا کدش را:'],
                  ['espm_', 'sp_mult', '✖️ ضریب قیمت (مثلا 1 یا 1.5):'],
                  ['espd_', 'sp_perday', "🚀 چند نفر در روز تحویل داده می‌شود؟ (فقط عدد)\n\nهمین عدد هم روی دکمه می‌آید، هم زمان تقریبی تحویل را می‌سازد."],
                  ['espn_', 'sp_desc', "💬 توضیح کوتاه این سرعت (خط تیره = حذف):\n\nزیر متن انتخاب سرعت نشان داده می‌شود."]] as $it) {
            [$pref, $act, $ask] = $it;
            if (!str_starts_with($data, $pref)) continue;
            $rest = substr($data, strlen($pref)); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, $act, ['pid' => substr($rest, 0, $pos), 'sid' => substr($rest, $pos + 1)]);
            sendMsg(BOT_TOKEN, $chatId, $ask, inlineKb([[btnUI('cancel', 'esp_' . $rest, 'cancel')]]));
            return;
        }
        foreach ([['espc_', 'color'], ['espx_', 'on']] as [$pref, $what]) {
            if (!str_starts_with($data, $pref)) continue;
            $rest = substr($data, strlen($pref)); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb(BOT_TOKEN, $cbId); return; }
            $pid = substr($rest, 0, $pos); $sid = substr($rest, $pos + 1);
            if ($what === 'color') speedMutate($pid, $sid, function (&$x) { $x['color'] = nextStyle($x['color'] ?? 'none'); });
            else speedMutate($pid, $sid, function (&$x) { $x['on'] = !(!isset($x['on']) || !empty($x['on'])); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edSpeed($chatId, $msgId, $pid, $sid);
            return;
        }

        // ---------- تنظیمات ربات‌های زیرمجموعه ----------
        if ($data === 'eupload')   { answerCb(BOT_TOKEN, $cbId); edUploader($chatId, $msgId); return; }
        if ($data === 'eup_texts') { answerCb(BOT_TOKEN, $cbId); edUpTexts($chatId, $msgId); return; }
        if (str_starts_with($data, 'eupt_')) { answerCb(BOT_TOKEN, $cbId); edUpText($chatId, $msgId, substr($data, 5)); return; }

        if ($data === 'eup_fj' || $data === 'eup_pc') {
            $f = ($data === 'eup_fj') ? 'force_join' : 'protect_content';
            cfgSet(function (&$c) use ($f) { $c['uploader'][$f] = empty($c['uploader'][$f]); });
            answerCb(BOT_TOKEN, $cbId, '✅');
            edUploader($chatId, $msgId);
            return;
        }
        if ($data === 'eup_sec') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'eup_sec');
            sendMsg(BOT_TOKEN, $chatId, "⏱ چند ثانیه بعد فایل حذف شود؟ (عدد)",
                inlineKb([[btnUI('cancel', 'eupload', 'cancel')]]));
            return;
        }
        if ($data === 'eup_all') {
            $u = cfg()['uploader'];
            $n = 0;
            foreach (BotManager::all() as $b) {
                foreach ($u as $k => $v) BotManager::setSetting($b['id'], $k, $v);
                $n++;
            }
            answerCb(BOT_TOKEN, $cbId, "✅ روی {$n} ربات اعمال شد", true);
            edUploader($chatId, $msgId);
            return;
        }
        if (str_starts_with($data, 'eupe_')) {
            $k = substr($data, 5);
            if (!isset(upTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'eup_text', ['key' => $k]);
            sendMsg(BOT_TOKEN, $chatId,
                "✏️ متن جدید را بفرستید.\n\n✨ ایموجی پریمیوم و نقل‌قول حفظ می‌شود.",
                inlineKb([[btnUI('cancel', 'eupt_' . $k, 'cancel')]]));
            return;
        }
        if (str_starts_with($data, 'eupq_') || str_starts_with($data, 'eupx_')) {
            $exp = str_starts_with($data, 'eupx_');
            $k = substr($data, 5);
            if (!isset(upTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $exp) {
                $t = trim($c['uploader'][$k] ?? '');
                if (str_starts_with($t, '<blockquote')) {
                    $t = preg_replace('#^<blockquote[^>]*>#', '', $t);
                    $t = preg_replace('#</blockquote>$#', '', trim($t));
                    if ($exp) $t = '<blockquote expandable>' . trim($t) . '</blockquote>';
                } else {
                    $t = ($exp ? '<blockquote expandable>' : '<blockquote>') . $t . '</blockquote>';
                }
                $c['uploader'][$k] = trim($t);
            });
            answerCb(BOT_TOKEN, $cbId, '❝ اعمال شد');
            edUpText($chatId, $msgId, $k);
            return;
        }
        if (str_starts_with($data, 'eupr_')) {
            $k = substr($data, 5);
            $d = defaultConfig()['uploader'][$k] ?? null;
            if ($d === null) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k, $d) { $c['uploader'][$k] = $d; });
            answerCb(BOT_TOKEN, $cbId, '♻️');
            edUpText($chatId, $msgId, $k);
            return;
        }

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
        if (str_starts_with($data, 'euc_')) {
            $k = substr($data, 4);
            if (!isset(uiTextLabels()[$k])) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            cfgSet(function (&$c) use ($k) {
                $cur = $c['ui_colors'][$k] ?? 'none';
                $c['ui_colors'][$k] = nextStyle($cur);
            });
            answerCb(BOT_TOKEN, $cbId, '🎨');
            $page = 0;
            $keys = array_keys(uiTextLabels());
            $i = array_search($k, $keys, true);
            if ($i !== false) $page = intdiv($i, 9);
            edUiTexts($chatId, $msgId, $page);
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

        if (str_starts_with($data, 'reply_')) {
            $target = (int)substr($data, 6);
            if ($target <= 0) { answerCb(BOT_TOKEN, $cbId, 'نامعتبر', true); return; }
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'reply_user', ['to' => $target]);
            sendMsg(BOT_TOKEN, $chatId, "💬 پاسخ خود را بفرستید (به <code>{$target}</code>):",
                inlineKb([[btnUI('cancel', 'adm_home', 'cancel')]]));
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
        slotClear($uid);   // منوی تازه، پیام‌های قدیمی رها می‌شوند
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
    if ($text === '/setup') {
        if ($uid !== ADMIN_ID) return;
        autoSetup($chatId);
        return;
    }
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
    if ($text === '/orders' || $text === '/track') {
        clearState($uid); showOrders($uid, $chatId, [], $msg['message_id'] ?? null); return;
    }
    if (str_starts_with($text, '/track ')) {
        clearState($uid);
        showOrderStatus($uid, $chatId, substr($text, 7), $msg['message_id'] ?? null);
        return;
    }
    // کد پیگیری را خالی فرستاده — بدون هیچ دستوری
    if (preg_match('/^or_[A-Za-z0-9]{6,}$/', $text)) {
        clearState($uid);
        showOrderStatus($uid, $chatId, $text, $msg['message_id'] ?? null);
        return;
    }
    if ($text === '/menu')   { showHome($uid, $chatId, $fname); return; }
    if ($text === '/hide' || $text === UT('hide_menu')) {
        sendMsg(BOT_TOKEN, $chatId,
            "✅ منو بسته شد.\n\nبرای برگرداندنش /menu را بزنید.", removeKb());
        return;
    }

    // --- دکمه منو زده شد؟ ---
    $act = findMenuAction($text);
    if ($act) {
        clearState($uid);
        runMenuAction($act, $uid, $chatId, $uname, $fname, $msg['message_id'] ?? null);
        return;
    }

    // --- ادامه گفتگو ---
    $st = getState($uid);
    if (!$st) return;
    $action = $st['action'];
    $sd     = $st['data'] ?? [];

    if ($action === 'sb_code') {
        $pid = $sd['pid'] ?? '';
        $plain = trim($msg['text'] ?? '');
        if (!Product::get($pid)) { clearState($uid); return; }
        $v = ($plain === '-' || $plain === '—') ? '' : $plain;
        prodMutate($pid, function (&$x) use ($v) { $x['link_code'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, $v !== '' ? "✅ کد لینک ذخیره شد." : "✅ حذف شد.",
            inlineKb([[btnCb('🤖 ربات تحویل', 'sbbot_' . $pid, 'admin')]]));
        return;
    }

    if (str_starts_with($action, 'tf_')) {
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnCb('📋 لیست تعرفه‌ها', 'adm_tariff', 'admin')]]);

        if ($action === 'tf_text') {
            $html = msgHtml($msg);
            if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($html) { $c['tariff']['text'] = $html; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ متن ذخیره شد." . ($ids ? "\n✨ ایموجی پریمیوم حفظ شد." : '') .
                (str_contains($html, '{list}') ? "\n📊 جدول قیمت‌ها جای <code>{list}</code> می‌نشیند." : ''),
                $back);
            return;
        }
        $map = ['tf_btn' => ['btn', 'text'], 'tf_bemoji' => ['btn', 'emoji'],
                'tf_back' => ['back', 'text'], 'tf_kemoji' => ['back', 'emoji']];
        if (isset($map[$action])) {
            [$w, $f] = $map[$action];
            $v = ($plain === '-' || $plain === '—') ? '' : $plain;
            if ($f === 'text' && $v === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            cfgSet(function (&$c) use ($w, $f, $v, $ids) {
                $c['tariff'][$w][$f] = $v;
                if ($f === 'text' && $ids) $c['tariff'][$w]['icon'] = $ids[0];
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد." . ($ids && $f === 'text' ? "\n✨ ایموجی پریمیوم نشست." : ''), $back);
            return;
        }
        clearState($uid);
        return;
    }

    if ($action === 'rp_allchat') {
        $plain = trim($msg['text'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ آیدی گروه را بفرستید."); return; }
        $n = 0;
        foreach (saleButtons() as $sb) {
            reportMutate($sb['id'], function (&$r) use ($plain) { $r['chat_id'] = $plain; $r['on'] = true; });
            $n++;
        }
        foreach (Product::all() as $pr) {
            reportMutate($pr['id'], function (&$r) use ($plain) { $r['chat_id'] = $plain; $r['on'] = true; });
            $n++;
        }
        clearState($uid);
        $t = tg(BOT_TOKEN, 'getChat', ['chat_id' => $plain], 8);
        sendMsg(BOT_TOKEN, $chatId,
            (!empty($t['ok'])
                ? "✅ گروه <b>" . h($t['result']['title'] ?? $plain) . "</b> روی <b>{$n}</b> محصول نشست."
                : "⚠️ روی <b>{$n}</b> محصول ذخیره شد، ولی ربات به گروه دسترسی ندارد:\n<code>" .
                  h($t['description'] ?? '—') . "</code>") .
            "\n\nحالا برای هر محصول <b>شماره تاپیک</b> خودش را بگذارید:",
            inlineKb([[btnCb('📢 گزارش‌ها', 'adm_reports', 'admin')]]));
        return;
    }

    if (str_starts_with($action, 'rp_')) {
        $pid = $sd['pid'] ?? '';
        $p   = Product::get($pid);
        if (!$p) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $i     = (int)($sd['i'] ?? 0);
        $back  = inlineKb([[btnUI('back', 'rp_' . $pid, 'nav')]]);
        $backB = inlineKb([[btnUI('back', 'rpb_' . $pid . '|' . $i, 'nav')]]);

        if ($action === 'rp_chat') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ آیدی گروه را بفرستید."); return; }
            reportMutate($pid, function (&$r) use ($plain) { $r['chat_id'] = $plain; $r['on'] = true; });
            clearState($uid);
            $t = tg(BOT_TOKEN, 'getChat', ['chat_id' => $plain], 8);
            sendMsg(BOT_TOKEN, $chatId, !empty($t['ok'])
                ? "✅ گروه ثبت شد: <b>" . h($t['result']['title'] ?? $plain) . "</b>\nگزارش هم روشن شد."
                : "⚠️ ذخیره شد ولی ربات به این گروه دسترسی ندارد:\n<code>" .
                  h($t['description'] ?? '—') . "</code>\n\nربات را در گروه ادمین کنید.", $back);
            return;
        }
        if ($action === 'rp_thread') {
            if (!ctype_digit($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
            $n = (int)$plain;
            reportMutate($pid, function (&$r) use ($n) { $r['thread_id'] = $n; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $n > 0 ? "✅ تاپیک: <b>{$n}</b>" : "✅ بدون تاپیک.", $back);
            return;
        }
        if ($action === 'rp_text') {
            $html = msgHtml($msg);   // ✨ پریمیوم + quote حفظ می‌شود
            if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            reportMutate($pid, function (&$r) use ($html) { $r['text'] = $html; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId,
                "✅ متن گزارش ذخیره شد.\n\n<b>پیش‌نمایش:</b>\n" . $html .
                ($ids ? "\n\n✨ ایموجی پریمیوم حفظ شد." : ''), $back);
            return;
        }
        if ($action === 'rp_btn_text') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            reportMutate($pid, function (&$r) use ($i, $plain, $ids) {
                if (!isset($r['buttons'][$i])) return;
                $r['buttons'][$i]['text'] = $plain;
                if ($ids) $r['buttons'][$i]['icon'] = $ids[0];
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد." . ($ids ? "\n✨ ایموجی پریمیوم نشست." : ''), $backB);
            return;
        }
        if ($action === 'rp_btn_url') {
            if (!preg_match('#^https?://#i', $plain) && !str_starts_with($plain, 'tg://')) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ لینک باید با <code>https://</code> شروع شود."); return;
            }
            reportMutate($pid, function (&$r) use ($i, $plain) {
                if (isset($r['buttons'][$i])) $r['buttons'][$i]['url'] = $plain;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ لینک ذخیره شد.", $backB);
            return;
        }
        if ($action === 'rp_btn_icon') {
            $ic = $ids ? $ids[0] : (ctype_digit($plain) ? $plain : '');
            if (!$ic && $plain !== '-' && $plain !== '—') {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم یا کد عددی بفرستید."); return;
            }
            reportMutate($pid, function (&$r) use ($i, $ic) {
                if (isset($r['buttons'][$i])) $r['buttons'][$i]['icon'] = $ic;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, $ic ? "✅ نشست." : "✅ حذف شد.", $backB);
            return;
        }
        clearState($uid);
        return;
    }

    if ($action === 'pay_receipt') {
        $pid = $sd['pid'] ?? '';
        $p   = Product::get($pid);
        $amt = (float)($sd['amount'] ?? 0);
        if (!$p || $amt <= 0) { clearState($uid); return; }

        $type = null; $val = null;
        if (!empty($msg['photo'])) { $ph = $msg['photo']; $type = 'photo'; $val = $ph[count($ph) - 1]['file_id']; }
        elseif ($text !== '')      { $type = 'text';  $val = $text; }
        if (!$type) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عکس رسید یا کد تراکنش بفرستید."); return; }

        // ✅ حالا سفارش ساخته می‌شود — نه زودتر
        $oid = Order::create($uid, $uname, 'product', $pid, $amt,
                             (string)($sd['currency'] ?? 'تومان'), $sd['meta'] ?? []);
        Order::attachReceipt($oid, $type, $val);
        clearState($uid);
        mutate('users', function (&$a) use ($uid) { unset($a[(string)$uid]['pending']); });

        $m = $sd['meta'] ?? [];
        $t  = "✅ <b>سفارش شما ثبت شد</b>\n\n";
        $t .= "🧾 کد پیگیری: <code>" . h($oid) . "</code>\n";
        $t .= "📦 " . h($p['name']) . "\n";
        if (!empty($m['link'])) $t .= "📣 " . h($m['link']) . "\n";
        if (!empty($m['qty']))  $t .= "👥 " . number_format((int)$m['qty']) . " نفر" .
                                      (!empty($m['speed']) ? ' · ' . h($m['speed']) : '') . "\n";
        $t .= "💰 " . fmtNum($amt) . ' ' . h($sd['currency'] ?? 'تومان') . "\n";
        $t .= "📊 وضعیت: <b>🧾 رسید در حال بررسی</b>\n\n";
        $t .= "به‌محض تایید، سفارش شروع می‌شود.";
        sendMsg(BOT_TOKEN, $chatId, $t,
            inlineKb([[btnCb('🔍 وضعیت سفارش', 'trk_' . $oid, 'info')]]));

        notifyAdminOrder($oid);
        return;
    }

    if ($action === 'track') {
        clearState($uid);
        showOrderStatus($uid, $chatId, $text, $msg['message_id'] ?? null);
        return;
    }

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
        panelShow($uid, $chatId, $o['type'] === 'topup' ? 'wallet' : 'shop', T('receipt_ok'));
        notifyAdminOrder($oid);
        return;
    }

    if ($action === 'flow') {
        $sd = $st['data'];
        $p  = Product::get($sd['pid'] ?? '');
        if (!$p) { clearState($uid); return; }
        $step = $sd['step'] ?? 'link';

        if ($step === 'link') {
            if (!preg_match('#^https?://t\.me/[A-Za-z0-9_+\-]{3,}#', $text)) {
                sendMsg(BOT_TOKEN, $chatId, T('flow_link_bad'));
                return;
            }
            $sd['data']['link'] = $text;
            setState($uid, 'flow', $sd);
            flowNext($uid, $chatId, 'qty');
            return;
        }

        if ($step === 'qty') {
            $f = $p['flow'];
            $q = (int)preg_replace('/[^0-9]/', '', $text);
            $min = (int)$f['min']; $max = (int)$f['max'];
            if ($q < $min || ($max > 0 && $q > $max)) {
                sendMsg(BOT_TOKEN, $chatId, T('flow_qty_bad',
                    ['min' => number_format($min), 'max' => number_format($max)]));
                return;
            }
            $sd['data']['qty'] = $q;
            setState($uid, 'flow', $sd);

            $sd['data']['rate_note'] = T('flow_rate', ['rate' => fmtNum($p['price'])]);
            setState($uid, 'flow', $sd);
            flowNext($uid, $chatId, 'speed');
            return;
        }
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
        $body = msgHtml($msg);
        if (trim($body) === '' && empty($msg['photo'])) {
            sendMsg(BOT_TOKEN, $chatId, "⚠️ لطفا متن پیام را بنویسید.");
            return;
        }
        clearState($uid);

        // ۱) ذخیره تیکت تا حتی اگر ارسال به ادمین ناموفق شد گم نشود
        $tid = uid('tk');
        mutate('tickets', function (&$a) use ($tid, $uid, $uname, $fname, $body) {
            $a[$tid] = ['id' => $tid, 'user_id' => (int)$uid,
                        'username' => $uname, 'name' => $fname,
                        'text' => $body, 'at' => nowStr(), 'answered' => false];
        });

        // ۲) اول تایید کاربر — تا اگر ارسال به ادمین گیر کرد، کاربر بلاتکلیف نماند
        sendMsg(BOT_TOKEN, $chatId, T('sup_sent'), mainKeyboard());

        // ۳) بعد اطلاع به ادمین (اگر ناموفق بود فقط لاگ می‌شود)
        $r = sendMsg(BOT_TOKEN, ADMIN_ID,
            "🎫 <b>تیکت جدید</b>\n\n👤 " . h($uname ? '@' . $uname : $fname) . " (<code>{$uid}</code>)\n\n" . $body,
            inlineKb([[btnCb('💬 پاسخ', 'reply_' . $uid, 'admin')]]));
        if (empty($r['ok'])) error_log('[ticket] admin notify failed: ' . ($r['description'] ?? ''));
        return;
    }

    // ---- ادمین ----
    if ($uid !== ADMIN_ID) return;

    if ($action === 'reply_user') {
        $to = (int)($sd['to'] ?? 0);
        $body = msgHtml($msg);
        if ($to <= 0 || trim($body) === '') { clearState($uid); return; }
        clearState($uid);
        $r = sendMsg(BOT_TOKEN, $to, "💬 <b>پاسخ پشتیبانی</b>\n\n" . $body);
        sendMsg(BOT_TOKEN, $chatId,
            !empty($r['ok']) ? "✅ پاسخ ارسال شد." : "❌ ارسال نشد: " . h($r['description'] ?? ''));
        return;
    }

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

    // ---- زیردکمه‌های شیشه‌ای ----
    if (str_starts_with($action, 'sb_')) {
        $bid = $sd['btn'] ?? '';
        if (!isset(cfg()['buttons'][$bid])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);

        if ($action === 'sb_new') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            $sid = 's' . bin2hex(random_bytes(3));

            // 🧬 از روی یک دکمه آماده، همه تنظیمات را به ارث ببر تا از همان اول کار کند
            $src = sourceSub($bid);
            cfgSet(function (&$c) use ($bid, $sid, $plain, $ids, $src) {
                if (!isset($c['buttons'][$bid]['subs'])) $c['buttons'][$bid]['subs'] = [];
                $new = [
                    'id' => $sid, 'emoji' => '', 'text' => $plain, 'color' => 'none',
                    'icon' => $ids ? $ids[0] : '', 'row' => 0, 'order' => 50,
                    'on' => true, 'action' => 'text', 'value' => '',
                    'price' => 0, 'currency' => cfg()['currency'] ?? 'تومان',
                    'buyers' => [], 'flow' => defaultFlow(), 'report' => defaultReport(),
                ];
                if ($src) {
                    $new['price']    = (float)($src['price'] ?? 0);
                    $new['currency'] = (string)($src['currency'] ?? ($c['currency'] ?? 'تومان'));
                    $new['color']    = (string)($src['color'] ?? 'none');
                    if (is_array($src['flow'] ?? null))
                        $new['flow'] = array_merge(defaultFlow(), $src['flow'], ['on' => true, 'ask_admin' => true]);
                    if (is_array($src['report'] ?? null)) {
                        $new['report'] = array_merge(defaultReport(), $src['report']);
                        $new['report']['thread_id'] = 0;   // تاپیک باید مخصوص خودش باشد
                    }
                    if (!empty($src['bot_id']))    $new['bot_id']    = $src['bot_id'];
                    if (!empty($src['link_code'])) $new['link_code'] = '';
                }
                $c['buttons'][$bid]['subs'][] = $new;
            });
            clearState($uid);

            $p = subProduct($bid, $sid);
            $msgTxt  = "✅ دکمه <b>" . h($plain) . "</b> ساخته شد و <b>آماده فروش است</b>.\n\n";
            if ($src) {
                $msgTxt .= "🧬 تنظیمات از «" . h($src['text']) . "» کپی شد:\n";
                $msgTxt .= "💰 قیمت: <b>" . fmtNum($p['price']) . ' ' . h($p['currency']) . "</b>\n";
                $msgTxt .= "👥 تعداد: " . number_format((int)$p['flow']['min']) . ' تا ' .
                           number_format((int)$p['flow']['max']) . "\n";
                $msgTxt .= "⚡️ سرعت‌ها: " . count($p['flow']['speeds']) . "\n";
                if (!empty($p['report']['chat_id']))
                    $msgTxt .= "📢 گروه گزارش: <code>" . h($p['report']['chat_id']) .
                               "</code> — <b>تاپیکش را بگذارید</b>\n";
                $msgTxt .= "\nاگر قیمتش فرق دارد، عوضش کنید:";
            } else {
                $msgTxt .= "⚠️ هنوز قیمت ندارد. اول قیمتش را بگذارید:";
            }

            $k = $bid . '|' . $sid;
            sendMsg(BOT_TOKEN, $chatId, $msgTxt, inlineKb([
                [btnCb('💰 قیمت', 'sbpr_' . $k, 'buy'), btnCb('😀 ایموجی', 'sbe_' . $k, 'admin')],
                [btnCb('📢 گزارش خرید', 'sbrp_' . $k, 'admin')],
                [btnCb('⚙️ همه تنظیمات', 'sb_' . $k, 'admin')],
            ]));
            return;
        }
        if ($action === 'sb_layout') {
            if (!parseLayout($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ نامعتبر. مثال: <code>2,1</code>"); return; }
            cfgSet(function (&$c) use ($bid, $plain) { $c['buttons'][$bid]['sub_layout'] = trim($plain); });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ چیدمان ذخیره شد.",
                inlineKb([[btnCb('💠 دکمه‌ها', 'sbs_' . $bid, 'admin')]]));
            return;
        }

        $sid = $sd['sub'] ?? '';
        if (!findSub($bid, $sid)) { clearState($uid); return; }
        $back = inlineKb([[btnUI('back', 'sb_' . $bid . '|' . $sid, 'nav')]]);

        if ($action === 'sb_text') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
            subMutate($bid, $sid, function (&$x) use ($plain, $ids) {
                $x['text'] = $plain;
                if ($ids) $x['icon'] = $ids[0];
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد." . ($ids ? "\n✨ ایموجی پریمیوم نشست." : ''), $back);
            return;
        }
        if ($action === 'sb_emoji') {
            $em = ($plain === '-' || $plain === '—') ? '' : $plain;
            subMutate($bid, $sid, function (&$x) use ($em) { $x['emoji'] = $em; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back); return;
        }
        if ($action === 'sb_icon') {
            $ic = $ids ? $ids[0] : (ctype_digit($plain) ? $plain : '');
            if (!$ic && $plain !== '-' && $plain !== '—') { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم یا کد عددی بفرستید."); return; }
            subMutate($bid, $sid, function (&$x) use ($ic) { $x['icon'] = $ic; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, $ic ? "✅ نشست." : "✅ حذف شد.", $back); return;
        }
        if ($action === 'sb_row' || $action === 'sb_order') {
            if (!ctype_digit($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
            $f = ($action === 'sb_row') ? 'row' : 'order'; $v = (int)$plain;
            subMutate($bid, $sid, function (&$x) use ($f, $v) { $x[$f] = $v; });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back); return;
        }
        if ($action === 'sb_price') {
            $n = str_replace([',', '،', ' '], '', $plain);
            if (!is_numeric($n) || (float)$n < 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
            subMutate($bid, $sid, function (&$x) use ($n) {
                $x['price'] = (float)$n;
                if (!isset($x['currency'])) $x['currency'] = cfg()['currency'] ?? 'تومان';
                if (!is_array($x['flow'] ?? null)) $x['flow'] = [];
                $x['flow'] = array_merge(defaultFlow(), $x['flow'], ['on' => true, 'ask_admin' => true]);
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ قیمت ذخیره شد: " . fmtNum((float)$n), $back); return;
        }
        if ($action === 'sb_minmax') {
            if (!preg_match('/^\s*(\d+)\s*[-–]\s*(\d+)\s*$/u', str_replace(['،', ','], '', $plain), $mm)) {
                sendMsg(BOT_TOKEN, $chatId, "⚠️ قالب درست: <code>1000-100000</code>"); return;
            }
            $lo = (int)$mm[1]; $hi = (int)$mm[2];
            if ($lo < 1 || $hi <= $lo) { sendMsg(BOT_TOKEN, $chatId, "⚠️ حداکثر باید از حداقل بیشتر باشد."); return; }
            subMutate($bid, $sid, function (&$x) use ($lo, $hi) {
                if (!is_array($x['flow'] ?? null)) $x['flow'] = [];
                $x['flow'] = array_merge(defaultFlow(), $x['flow']);
                $x['flow']['min'] = $lo; $x['flow']['max'] = $hi;
            });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ تعداد: " . number_format($lo) . " تا " . number_format($hi), $back); return;
        }
        if ($action === 'sb_value') {
            $html = msgHtml($msg);
            subMutate($bid, $sid, function (&$x) use ($html, $plain) {
                $x['value'] = (($x['action'] ?? '') === 'text') ? $html : $plain;
            });
            clearState($uid); sendMsg(BOT_TOKEN, $chatId, "✅ مقدار ذخیره شد.", $back); return;
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
                'on' => true, 'action' => 'text', 'value' => '',
            ];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ دکمه <b>" . h($plain) . "</b> ساخته شد.\n\nحالا متن، رنگ و ردیفش را تنظیم کنید:",
            inlineKb([[btnCb('⚙️ تنظیم دکمه', 'eb_' . $nid, 'admin')]]));
        return;
    }

    if (str_starts_with($action, 'ep_')) {
        $pid = $sd['pid'] ?? '';
        if (!Product::get($pid)) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $back = inlineKb([[btnUI('back', 'ep_' . $pid, 'nav')]]);
        $num  = (float)str_replace(',', '', $plain);

        $map = ['ep_name' => 'name', 'ep_price' => 'price', 'ep_emoji' => 'emoji'];
        if (isset($map[$action])) {
            if ($action === 'ep_price' && $num <= 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عدد معتبر بفرستید."); return; }
            if ($action === 'ep_name' && $plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی است."); return; }
            $f = $map[$action];
            $v = ($action === 'ep_price') ? $num : (($plain === '-' || $plain === '—') ? '' : $plain);
            prodMutate($pid, function (&$x) use ($f, $v) { $x[$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            return;
        }

        $fmap = ['ep_min' => 'min', 'ep_max' => 'max', 'ep_per' => 'per', 'ep_slayout' => 'speed_layout'];
        if (isset($fmap[$action])) {
            $f = $fmap[$action];
            if ($f === 'speed_layout') {
                if (!parseLayout($plain)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ نامعتبر. مثال: 1 یا 3"); return; }
                $v = trim($plain);
            } else {
                if ((int)$num <= 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عدد معتبر بفرستید."); return; }
                $v = (int)$num;
            }
            flowMutate($pid, function (&$f2) use ($f, $v) { $f2[$f] = $v; });
            clearState($uid);
            sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
            return;
        }
    }

    if (str_starts_with($action, 'sp_')) {
        $pid = $sd['pid'] ?? ''; $sid = $sd['sid'] ?? '';
        if (!Product::get($pid)) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        $ids   = customEmojiIds($msg);
        $back  = inlineKb([[btnUI('back', 'esp_' . $pid . '|' . $sid, 'nav')]]);

        if ($action === 'sp_text') {
            if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ خالی است."); return; }
            speedMutate($pid, $sid, function (&$x) use ($plain, $ids) {
                $x['text'] = $plain;
                if ($ids) $x['icon'] = $ids[0];
            });
        } elseif ($action === 'sp_emoji') {
            $em = ($plain === '-' || $plain === '—') ? '' : $plain;
            speedMutate($pid, $sid, function (&$x) use ($em) { $x['emoji'] = $em; });
        } elseif ($action === 'sp_icon') {
            $ic = $ids ? $ids[0] : (ctype_digit($plain) ? $plain : '');
            if (!$ic && $plain !== '-' && $plain !== '—') { sendMsg(BOT_TOKEN, $chatId, "⚠️ ایموجی پریمیوم یا کد بفرستید."); return; }
            speedMutate($pid, $sid, function (&$x) use ($ic) { $x['icon'] = $ic; });
        } elseif ($action === 'sp_mult') {
            $m = (float)str_replace(',', '', $plain);
            if ($m <= 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عدد بزرگ‌تر از صفر."); return; }
            speedMutate($pid, $sid, function (&$x) use ($m) { $x['mult'] = $m; });
        } elseif ($action === 'sp_perday') {
            $n = (int)str_replace([',', '،', ' '], '', $plain);
            if ($n <= 0) { sendMsg(BOT_TOKEN, $chatId, "⚠️ عدد بزرگ‌تر از صفر بفرستید."); return; }
            speedMutate($pid, $sid, function (&$x) use ($n) { $x['per_day'] = $n; });
            clearState($uid);
            $spNow = null;
            foreach (Product::get($pid)['flow']['speeds'] ?? [] as $x) if ($x['id'] === $sid) $spNow = $x;
            sendMsg(BOT_TOKEN, $chatId,
                "✅ ذخیره شد.\n\nمتن دکمه: <code>" . h(speedBtnLabel($spNow)) . "</code>\n" .
                "⏳ ۵٬۰۰۰ نفر ← " . h(speedEta($spNow, 5000)), $back);
            return;
        } elseif ($action === 'sp_desc') {
            $d = ($plain === '-' || $plain === '—') ? '' : msgHtml($msg);
            speedMutate($pid, $sid, function (&$x) use ($d) { $x['desc'] = $d; });
        } else { clearState($uid); return; }

        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.", $back);
        return;
    }

    if ($action === 'eup_sec') {
        if (!ctype_digit($text)) { sendMsg(BOT_TOKEN, $chatId, "⚠️ فقط عدد."); return; }
        $v = max(5, (int)$text);
        cfgSet(function (&$c) use ($v) { $c['uploader']['delete_seconds'] = $v; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ روی {$v} ثانیه تنظیم شد.",
            inlineKb([[btnCb('🤖 تنظیمات', 'eupload', 'admin')]]));
        return;
    }

    if ($action === 'eup_text') {
        $k = $sd['key'] ?? '';
        if (!isset(upTextLabels()[$k])) { clearState($uid); return; }
        $html = msgHtml($msg);
        if (trim($html) === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        cfgSet(function (&$c) use ($k, $html) { $c['uploader'][$k] = $html; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ ذخیره شد.\n\n<b>پیش‌نمایش:</b>\n" . $html,
            inlineKb([[btnCb('📋 اعمال روی همه ربات‌ها', 'eup_all', 'confirm')],
                      [btnUI('back', 'eupt_' . $k, 'nav')]]));
        return;
    }

    if ($action === 'ed_uitext') {
        $k = $sd['key'] ?? '';
        if (!isset(uiTextLabels()[$k])) { clearState($uid); return; }
        $plain = trim($msg['text'] ?? '');
        if ($plain === '') { sendMsg(BOT_TOKEN, $chatId, "⚠️ متن خالی است."); return; }
        $ids = customEmojiIds($msg);
        cfgSet(function (&$c) use ($k, $plain, $ids) {
            $c['ui_texts'][$k] = $plain;
            if ($ids) $c['ui_icons'][$k] = $ids[0];
        });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId,
            "✅ ذخیره شد: <b>" . h($plain) . "</b>" . ($ids ? "\n✨ ایموجی پریمیوم روی دکمه نشست." : ''),
            inlineKb([[btnUI('back', 'euis', 'nav')]]));
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

    // اگر کاربری که ربات را اضافه کرده وسط جریان سفارش است، همان‌جا تاییدش کن
    $byUser = (int)($ev['from']['id'] ?? 0);
    if ($byUser && in_array($newStatus, ['administrator', 'creator'], true)) {
        $st = getState($byUser);
        if ($st && $st['action'] === 'flow' && ($st['data']['step'] ?? '') === 'admin') {
            mutate('states', function (&$x) use ($byUser, $chatId, $title) {
                $k = (string)$byUser;
                if (!isset($x[$k])) return;
                $x[$k]['data']['data']['admin_ok']   = true;
                $x[$k]['data']['data']['chat_id']    = $chatId;
                $x[$k]['data']['data']['chat_title'] = $title;
            });
            // پیام تایید داخل خود فاکتور می‌آید، پس پیام جدا نمی‌فرستیم
            flowInvoice($byUser, $byUser);
            return;   // این افزودن مربوط به سفارش بود، نه کانال عضویت اجباری
        }
    }

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

/**
 * دکمه‌های شیشه‌ای زیرمجموعه یک دکمه منو —
 * زیر همان بخش نمایش داده می‌شوند، با چیدمان و رنگ دلخواه.
 */
function subRows($btnId) {
    $b = cfg()['buttons'][$btnId] ?? null;
    if (!$b || empty($b['subs'])) return [];

    $items = [];
    foreach ($b['subs'] as $sub) {
        if (empty($sub['on'])) continue;
        $items[] = $sub;
    }
    if (!$items) return [];
    usort($items, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));

    $hasRow = false;
    foreach ($items as $it) if (!empty($it['row'])) { $hasRow = true; break; }
    if ($hasRow) {
        $g = [];
        foreach ($items as $it) $g[(int)($it['row'] ?: 99)][] = $it;
        ksort($g);
        $groups = array_values($g);
    } else {
        $groups = layoutRows($items, $b['sub_layout'] ?? '1');
    }

    $rows = [];
    foreach ($groups as $line) {
        $out = [];
        foreach ($line as $sub) {
            $btn = ['text' => trim(($sub['emoji'] ?? '') . ' ' . ($sub['text'] ?? ''))];
            if (($sub['action'] ?? '') === 'url' && !empty($sub['value'])) $btn['url'] = $sub['value'];
            else $btn['callback_data'] = 'sub_' . $btnId . '|' . $sub['id'];
            if (isStyle($sub['color'] ?? '')) $btn['style'] = $sub['color'];
            if (!empty($sub['icon'])) $btn['icon_custom_emoji_id'] = (string)$sub['icon'];
            $out[] = $btn;
        }
        if ($out) $rows[] = $out;
    }
    return $rows;
}

/** فهرست محصولات برای بستن یک زیردکمه به محصول */
function edSubPick($chatId, $msgId, $bid, $sid) {
    $rows = [];
    foreach (Product::all() as $p) {
        $flow = !empty($p['flow']['on']) ? ' 🔄' : '';
        $rows[] = [btnCb(trim(($p['emoji'] ?? '') . ' ' . $p['name']) . $flow,
                         'sbp_' . $bid . '|' . $sid . '|' . $p['id'], 'buy')];
    }
    $rows[] = [btnUI('back', 'sb_' . $bid . '|' . $sid, 'nav')];
    editMsg(BOT_TOKEN, $chatId, $msgId,
        Product::all()
            ? "🛒 <b>این دکمه به کدام محصول وصل شود؟</b>\n\n🔄 = جریان سفارش دارد (لینک کانال، تعداد، سرعت)"
            : "🛒 هنوز محصولی نساخته‌اید.",
        inlineKb($rows));
}

/** متن‌های پیش‌فرضی که یعنی «هنوز تنظیم نشده» */
function isPlaceholder($v) {
    $v = trim(strip_tags((string)$v));
    return $v === '' || $v === 'متن این دکمه را تنظیم کنید.' || $v === 'این متن را از پنل عوض کنید.';
}

/** محصولی که نامش با متن دکمه یکی است */
function productByName($name) {
    $name = trim(preg_replace('/\s+/u', ' ', (string)$name));
    if ($name === '') return null;
    foreach (Product::all() as $p) {
        if (trim(preg_replace('/\s+/u', ' ', $p['name'])) === $name) return $p;
    }
    return null;
}

function findSub($btnId, $subId) {
    foreach ((cfg()['buttons'][$btnId]['subs'] ?? []) as $sub) {
        if (($sub['id'] ?? '') === $subId) return $sub;
    }
    return null;
}

/** اجرای عملیات یک دکمه منو */
function runMenuAction($act, $uid, $chatId, $uname, $fname, $replyTo = null) {
    // دکمه‌های سفارشی که ادمین ساخته
    $b = cfg()['buttons'][$act] ?? null;
    if ($b && !empty($b['action'])) {
        switch ($b['action']) {
            case 'text':
                if (isPlaceholder($b['value'] ?? '')) {
                    $mp = productByName($b['text'] ?? '');
                    if ($mp) {
                        if (!empty($mp['flow']['on'])) { flowStart($uid, $chatId, $mp); return; }
                        showOneProduct($uid, $chatId, $mp);
                        return;
                    }
                    if ($uid === ADMIN_ID) {
                        sendMsg(BOT_TOKEN, $chatId,
                            "⚠️ متن دکمه «" . h($b['text']) . "» هنوز تنظیم نشده.\n" .
                            "/panel → 🎨 دکمه‌ها → همین دکمه → 📝 مقدار");
                        return;
                    }
                    sendMsg(BOT_TOKEN, $chatId, T('buy_empty'));
                    return;
                }
                sendMsg(BOT_TOKEN, $chatId, $b['value']);
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
    $subs = subRows($act);
    switch ($act) {
        case 'buy':      showProducts($uid, $chatId, $subs, $replyTo); break;
        case 'account':  showAccount($uid, $chatId, $subs, $replyTo); break;
        case 'topup':    startTopup($uid, $chatId, $replyTo); break;
        case 'referral': showReferral($uid, $chatId, $subs, $replyTo); break;
        case 'orders':   showOrders($uid, $chatId, $subs, $replyTo); break;
        case 'support':  showSupport($uid, $chatId, $subs, $replyTo); break;
        case 'trust':    panelShow($uid, $chatId, 'menu', T('trust'), $subs ? inlineKb($subs) : null, $replyTo); break;
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
/** دکمه‌های شیشه‌ای زیر فایل تحویل‌شده */
function fileButtons($botId, $code) {
    $s = BotManager::settings($botId);
    $items = [];
    foreach ($s['file_buttons'] ?? [] as $b) if (!empty($b['on'])) $items[] = $b;
    if (!$items) return null;
    usort($items, fn($x, $y) => ((int)($x['order'] ?? 99)) <=> ((int)($y['order'] ?? 99)));

    $hasRow = false;
    foreach ($items as $i) if (!empty($i['row'])) { $hasRow = true; break; }
    if ($hasRow) {
        $g = []; foreach ($items as $i) $g[(int)($i['row'] ?: 99)][] = $i; ksort($g); $groups = array_values($g);
    } else {
        $groups = layoutRows($items, $s['file_layout'] ?? '1');
    }

    $rows = [];
    foreach ($groups as $line) {
        $out = [];
        foreach ($line as $b) {
            $btn = ['text' => trim(($b['emoji'] ?? '') . ' ' . $b['text'])];
            if (($b['action'] ?? '') === 'url' && !empty($b['value'])) $btn['url'] = $b['value'];
            else $btn['callback_data'] = 'fb_' . $b['id'] . '|' . $code;
            if (isStyle($b['color'] ?? '')) $btn['style'] = $b['color'];
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
            $out[] = $btn;
        }
        if ($out) $rows[] = $out;
    }
    return inlineKb($rows);
}

function deliverLink($bot, $chatId, $uid, $code) {
    $s    = BotManager::settings($bot['id']);
    $link = Links::get($bot['id'], $code);

    if (!$link || empty($link['active'])) {
        sendMsg($bot['token'], $chatId, $s['expired_text']);
        return;
    }

    Links::hit($bot['id'], $code, 'delivered');

    $sec   = max(5, (int)$s['delete_seconds']);
    $warn  = str_replace('{sec}', $sec, $s['warn_text']);
    $kb    = fileButtons($bot['id'], $code);
    $files = $link['files'];
    $last  = count($files) - 1;

    $msgIds = [];
    foreach ($files as $i => $f) {
        // متن هشدار و دکمه‌ها زیر خودِ فایل می‌آید — پیام جداگانه فرستاده نمی‌شود
        $cap = trim((string)($f['caption'] ?? ''));
        if ($i === $last) $cap = $cap !== '' ? $cap . "\n\n" . $warn : $warn;

        $r = sendFile($bot['token'], $chatId, $f['type'], $f['file_id'], $cap,
                      !empty($s['protect_content']), $i === $last ? $kb : null);
        if (!empty($r['ok'])) $msgIds[] = $r['result']['message_id'];
        usleep(40000);
    }

    if (!$msgIds) { sendMsg($bot['token'], $chatId, $s['expired_text']); return; }

    // استیکر و ویدیو-نوت کپشن نمی‌پذیرند — برای آن‌ها یک پیام کوتاه لازم است
    $lastType = $files[$last]['type'] ?? 'document';
    $warnId = null;
    if (in_array($lastType, ['sticker', 'video_note'], true)) {
        $w = sendMsg($bot['token'], $chatId, $warn, $kb);
        $warnId = $w['result']['message_id'] ?? null;
    }

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

        if (str_starts_with($data, 'fb_')) {
            $rest = substr($data, 3); $pos = strrpos($rest, '|');
            if ($pos === false) { answerCb($token, $cbId); return; }
            $fid = substr($rest, 0, $pos); $code = substr($rest, $pos + 1);
            $link = Links::get($botId, $code);
            $btn = null;
            foreach ($s['file_buttons'] ?? [] as $b) if (($b['id'] ?? '') === $fid) $btn = $b;
            if (!$btn || !$link) { answerCb($token, $cbId); return; }

            // آمار را به شکل پیام کوچک روی خود دکمه نشان بده — چت شلوغ نمی‌شود
            if (($btn['action'] ?? '') === 'downloads') {
                answerCb($token, $cbId, '📥 تعداد دانلود: ' . number_format((int)$link['delivered']), true);
                return;
            }
            if (($btn['action'] ?? '') === 'views') {
                answerCb($token, $cbId, '👁 بازدید: ' . number_format((int)$link['clicks']), true);
                return;
            }
            answerCb($token, $cbId);

            if (($btn['action'] ?? '') === 'info') {
                sendMsg($token, $chatId, strtr($s['info_text'], [
                    '{title}'     => h($link['title'] ?: count($link['files']) . ' فایل'),
                    '{files}'     => count($link['files']),
                    '{clicks}'    => (int)$link['clicks'],
                    '{delivered}' => (int)$link['delivered'],
                    '{created}'   => h($link['created_at']),
                ]));
                return;
            }
            sendMsg($token, $chatId, ($btn['value'] ?? '') !== '' ? $btn['value'] : '—');
            return;
        }

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
                $text .= "   👁 بازدید: " . (int)$l['clicks'] . "  |  📥 دانلود: " . (int)$l['delivered'] . "\n";
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
            $top = $links;
            uasort($top, fn($a, $b) => (int)$b['delivered'] <=> (int)$a['delivered']);
            $txt  = "📊 <b>آمار</b>\n\n";
            $txt .= "👥 کاربران: <b>" . count($users) . "</b>\n";
            $txt .= "🔗 لینک‌ها: <b>" . count($links) . "</b>\n";
            $txt .= "👁 بازدید کل: <b>{$cl}</b>\n";
            $txt .= "📥 دانلود کل: <b>{$dl}</b>\n";
            $txt .= "🗑 حذف بعد از: {$s['delete_seconds']} ثانیه\n";
            if ($top) {
                $txt .= "\n<b>پربازدیدترین‌ها:</b>\n";
                foreach (array_slice($top, 0, 5, true) as $c2 => $l2) {
                    $txt .= "• " . h($l2['title'] ?: count($l2['files']) . ' فایل') .
                            " — 👁 " . (int)$l2['clicks'] . " · 📥 " . (int)$l2['delivered'] . "\n";
                }
            }
            editMsg($token, $chatId, $msgId, $txt, inlineKb([[btnUI('back', 'u_home', 'nav')]]));
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

        // 🔒 قفل عضویت اجباری — کمپین‌ها همیشه، کانال‌های ثابت طبق تنظیم همان ربات
        [$missing, $creditable] = requiredMissing($uid, $botId);
        if ($missing) { showJoinGate($bot, $chatId, $missing, $code); return; }
        foreach ($creditable as $cid) Campaign::credit($cid, $uid);

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
            if (!empty($b['icon'])) $btn['icon_custom_emoji_id'] = (string)$b['icon'];
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

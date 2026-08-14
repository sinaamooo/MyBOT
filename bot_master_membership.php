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

function tg($token, $method, $data = []) {
    $ch = curl_init("https://api.telegram.org/bot{$token}/{$method}");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
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
function menuKb($rows) {
    $rows = cleanRows($rows);
    return $rows ? [
        'keyboard' => $rows,
        'resize_keyboard' => true,
        'is_persistent' => true,
    ] : ['remove_keyboard' => true];
}

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
        'ui' => ['mode' => 'menu', 'layout' => '1,2,1,2,1', 'show_dot' => false],

        'buttons' => [
            'buy'      => ['emoji' => '🛒', 'text' => 'خرید محصول',                     'color' => 'success', 'dot' => '🟢', 'order' => 1, 'on' => true],
            'account'  => ['emoji' => '👤', 'text' => 'حساب کاربری',                    'color' => 'primary', 'dot' => '🔵', 'order' => 2, 'on' => true],
            'topup'    => ['emoji' => '➕', 'text' => 'افزایش موجودی',                  'color' => 'primary', 'dot' => '🔵', 'order' => 3, 'on' => true],
            'referral' => ['emoji' => '👥', 'text' => 'زیر مجموعه گیری',                'color' => 'danger',  'dot' => '🔴', 'order' => 4, 'on' => true],
            'orders'   => ['emoji' => '📊', 'text' => 'پیگیری سفارش',                   'color' => 'primary', 'dot' => '🔵', 'order' => 5, 'on' => true],
            'support'  => ['emoji' => '📞', 'text' => 'پشتیبانی',                       'color' => 'primary', 'dot' => '🔵', 'order' => 6, 'on' => true],
            'trust'    => ['emoji' => '💚', 'text' => 'چطوری میتوانم به شما اعتماد کنم', 'color' => 'danger',  'dot' => '🔴', 'order' => 7, 'on' => true],
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

/** "1,2,1" → [1,2,1] ; مقادیر نامعتبر نادیده گرفته می‌شوند */
function parseLayout($str) {
    $out = [];
    foreach (explode(',', (string)$str) as $n) {
        $n = (int)trim($n);
        if ($n >= 1 && $n <= 8) $out[] = $n;
    }
    return $out;
}

/** چیدمان دکمه‌ها طبق الگوی دلخواه — مثلا 2,1,1 */
function layoutRows(array $items, $layoutStr) {
    $layout = parseLayout($layoutStr);
    $rows = [];
    $i = 0; $n = count($items); $k = 0;
    while ($i < $n) {
        $take = $layout ? $layout[min($k, count($layout) - 1)] : 1;
        $rows[] = array_slice($items, $i, $take);
        $i += $take; $k++;
    }
    return $rows;
}

/** منوی اصلی — منو یا شیشه‌ای، با رنگ واقعی تلگرام */
function mainKeyboard() {
    $c = cfg();
    $glass = ($c['ui']['mode'] === 'glass');
    $rows = layoutRows(activeButtons(), $c['ui']['layout'] ?? '');

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
                'active' => true, 'created_at' => nowStr(),
                'settings' => cfg()['uploader'],   // تنظیمات پیش‌فرض روی هر ربات جدید
            ];
        });
        return self::get($id);
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

    public static function add($chatId, $title, $url) {
        $id = uid('ch');
        mutate('channels', function (&$a) use ($id, $chatId, $title, $url) {
            $a[$id] = ['id' => $id, 'chat_id' => $chatId, 'title' => $title, 'url' => $url, 'on' => true];
        });
        return $id;
    }

    public static function remove($id) {
        mutate('channels', function (&$a) use ($id) { unset($a[$id]); });
    }

    /**
     * کانال‌هایی که کاربر هنوز عضو نشده.
     *
     * اگر بررسی ممکن نباشد (ربات در کانال ادمین نیست) کانال را
     * «عضو نشده» حساب می‌کنیم — یعنی قفل بسته می‌ماند. اگر برعکس عمل
     * می‌کردیم، یک کانال بدتنظیم بی‌سروصدا کل عضویت اجباری را خاموش می‌کرد.
     */
    public static function missing($token, $userId) {
        $missing = [];
        foreach (self::all() as $ch) {
            if (empty($ch['on'])) continue;
            $r = tg($token, 'getChatMember', ['chat_id' => $ch['chat_id'], 'user_id' => $userId]);
            if (empty($r['ok'])) { $ch['unverifiable'] = true; $missing[] = $ch; continue; }
            $status = $r['result']['status'] ?? '';
            if (!in_array($status, ['member', 'administrator', 'creator'], true)) $missing[] = $ch;
        }
        return $missing;
    }

    /** بررسی سلامت: آیا این ربات می‌تواند عضویت کانال‌ها را چک کند؟ */
    public static function health($token) {
        $out = [];
        foreach (self::all() as $ch) {
            $r = tg($token, 'getChatMember', ['chat_id' => $ch['chat_id'], 'user_id' => (int)ADMIN_ID]);
            $out[$ch['id']] = [
                'title' => $ch['title'],
                'ok'    => !empty($r['ok']),
                'error' => $r['description'] ?? '',
            ];
        }
        return $out;
    }
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
        [['text' => '➕ افزایش موجودی', 'callback_data' => 'menu_topup', 'style' => gs('buy') ?: null]],
        [['text' => '📊 سفارش‌های من', 'callback_data' => 'menu_orders', 'style' => gs('info') ?: null]],
    ]));
}

function showProducts($uid, $chatId) {
    $prods = array_filter(Product::all(), fn($p) => !empty($p['active']));
    if (!$prods) { sendMsg(BOT_TOKEN, $chatId, T('buy_empty')); return; }

    $text = T('buy_head') . "\n";
    $rows = [];
    foreach ($prods as $p) {
        $cnt  = count($p['buyers']);
        $cap  = ((int)$p['limit']) > 0 ? "{$cnt}/{$p['limit']}" : "{$cnt}/∞";
        $full = Product::isFull($p);
        $text .= "\n💠 <b>" . h($p['name']) . "</b>\n";
        if (!empty($p['desc'])) $text .= "   " . h($p['desc']) . "\n";
        $text .= "   💰 " . fmtNum($p['price']) . ' ' . h($p['currency']) . "  |  👥 {$cap}\n";

        if (Product::hasBought($p['id'], $uid)) {
            $rows[] = [['text' => '📦 دریافت مجدد — ' . $p['name'], 'callback_data' => 'redeliver_' . $p['id']]];
        } elseif ($full) {
            $rows[] = [['text' => '🔴 تکمیل ظرفیت — ' . $p['name'], 'callback_data' => 'full_' . $p['id']]];
        } else {
            $rows[] = [['text' => '🟢 خرید ' . $p['name'] . ' — ' . fmtNum($p['price']) . ' ' . $p['currency'],
                        'callback_data' => 'buy_' . $p['id']]];
        }
    }
    sendMsg(BOT_TOKEN, $chatId, $text, inlineKb($rows));
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
    sendMsg(BOT_TOKEN, $chatId, T('topup'), inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
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
        [['text' => '🟢 ارسال رسید', 'callback_data' => 'rcpt_' . $oid, 'style' => gs('confirm') ?: null]],
        [['text' => '🔴 انصراف', 'callback_data' => 'ocancel_' . $oid, 'style' => gs('cancel') ?: null]],
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
                inlineKb([[['text' => '🚀 دریافت محتوا', 'url' => $url, 'style' => gs('link') ?: null]]]));
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
        ['text' => '🟢 تایید', 'callback_data' => 'aok_' . $o['id']],
        ['text' => '🔴 رد',   'callback_data' => 'ano_' . $o['id']],
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
        [['text' => '🧾 سفارش‌ها', 'callback_data' => 'adm_orders', 'style' => gs('admin') ?: null],
         ['text' => '🛒 محصولات', 'callback_data' => 'adm_prods', 'style' => gs('admin') ?: null]],
        [['text' => '🤖 ربات‌ها', 'callback_data' => 'adm_bots', 'style' => gs('admin') ?: null],
         ['text' => '📢 کانال‌ها', 'callback_data' => 'adm_chans', 'style' => gs('admin') ?: null]],
        [['text' => '🎨 دکمه‌ها', 'callback_data' => 'adm_btns', 'style' => gs('admin') ?: null],
         ['text' => '📝 متن‌ها', 'callback_data' => 'adm_texts', 'style' => gs('admin') ?: null]],
        [['text' => '💳 کیف پول', 'callback_data' => 'adm_wallets', 'style' => gs('admin') ?: null],
         ['text' => '📞 پشتیبانی', 'callback_data' => 'adm_sup', 'style' => gs('admin') ?: null]],
        [['text' => '📢 پیام همگانی', 'callback_data' => 'adm_bc', 'style' => gs('admin') ?: null]],
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
                ['text' => '🔴 رد', 'callback_data' => 'ano_' . $o['id']],
            ];
        }
    }
    $rows[] = [['text' => '◀️ بازگشت', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
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
    $rows[] = [['text' => '◀️ بازگشت', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
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
    $rows[] = [['text' => '◀️ بازگشت', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
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
    $rows[] = [['text' => '◀️ بازگشت', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
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
    $rows[] = [['text' => '◀️ بازگشت', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]];
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
        [['text' => '◀️ بازگشت', 'callback_data' => 'adm_bots', 'style' => gs('admin') ?: null]],
    ];
    editMsg(BOT_TOKEN, $chatId, $msgId, $text, inlineKb($rows));
}

// ============================================================
// 🎬 ربات مادر — پردازش
// ============================================================

function masterHandle($update) {

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
                    inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
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
                $rows[] = [['text' => '💰 پرداخت از کیف پول (' . fmtNum($bal) . ' تومان)', 'callback_data' => 'wpay_' . $pid, 'style' => gs('buy') ?: null]];
            }
            $rows[] = [['text' => '💳 پرداخت مستقیم', 'callback_data' => 'dpay_' . $pid, 'style' => gs('buy') ?: null]];
            $rows[] = [['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]];

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
                    inlineKb([[['text' => '➕ افزایش موجودی', 'callback_data' => 'menu_topup', 'style' => gs('buy') ?: null]]]));
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
            sendMsg(BOT_TOKEN, $chatId, T('receipt_ask'), inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'ocancel_')) {
            clearState($uid);
            answerCb(BOT_TOKEN, $cbId, 'لغو شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش لغو شد.");
            return;
        }

        // ---------------- ادمین ----------------
        if (str_starts_with($data, 'aok_') || str_starts_with($data, 'ano_') || str_starts_with($data, 'adm_')) {
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
                inlineKb([[['text' => '👑 پنل', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
            return;
        }

        if (str_starts_with($data, 'ano_')) {
            $oid = substr($data, 4);
            [$ok, $res] = Order::reject($oid, $uid);
            if (!$ok) { answerCb(BOT_TOKEN, $cbId, $res, true); return; }
            sendMsg(BOT_TOKEN, $res['user_id'], T('rejected'));
            answerCb(BOT_TOKEN, $cbId, 'رد شد');
            if ($msgId) editMsg(BOT_TOKEN, $chatId, $msgId, "❌ سفارش <code>" . h($oid) . "</code> رد شد.",
                inlineKb([[['text' => '👑 پنل', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
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
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if ($data === 'adm_chadd') {
            answerCb(BOT_TOKEN, $cbId);
            setState(ADMIN_ID, 'chan_add');
            sendMsg(BOT_TOKEN, $chatId,
                "📢 آیدی کانال را بفرستید.\n\nمثال: <code>@mychannel</code> یا <code>-1001234567890</code>\n\n" .
                "⚠️ ربات‌های اپلودر باید در کانال <b>ادمین</b> باشند.",
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
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
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
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
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
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
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }

        if ($data === 'adm_wallets' || $data === 'adm_sup' || $data === 'adm_prods') {
            answerCb(BOT_TOKEN, $cbId);
            editMsg(BOT_TOKEN, $chatId, $msgId,
                "🌐 این بخش در <b>پنل وب</b> کامل‌تر است.\n\nآدرس: <code>admin_panel.php</code>",
                inlineKb([[['text' => '◀️ بازگشت', 'callback_data' => 'adm_home', 'style' => gs('admin') ?: null]]]));
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
    if ($text === '/cancel') { clearState($uid); sendMsg(BOT_TOKEN, $chatId, "❌ لغو شد.", mainKeyboard()); return; }
    if ($text === '/menu')   { showHome($uid, $chatId, $fname); return; }

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
        cfgSet(function (&$c) use ($key, $text) { $c['texts'][$key] = $text; });
        clearState($uid);
        sendMsg(BOT_TOKEN, $chatId, "✅ متن ذخیره شد.", mainKeyboard());
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
        if ($text === '') return;
        clearState($uid);
        $sent = 0; $fail = 0;
        foreach (load('users') as $u2) {
            if (!empty($u2['banned'])) continue;
            $r = sendMsg(BOT_TOKEN, $u2['telegram_id'], $text);
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

/** اجرای عملیات یک دکمه منو */
function runMenuAction($act, $uid, $chatId, $uname, $fname) {
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
        "⚠️ <b>مشکل عضویت اجباری</b>\n\nربات <b>@" . h($bot['username']) . "</b> نمی‌تواند عضویت کانال‌ها را بررسی کند.\n\n" .
        "این ربات را در همه کانال‌های اجباری <b>ادمین</b> کنید، وگرنه هیچ کاربری فایل دریافت نمی‌کند.");
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
        $isOwner = ($uid === ADMIN_ID);

        if (str_starts_with($data, 'jchk_')) {
            $code = substr($data, 5);
            $missing = !empty($s['force_join']) ? Channels::missing($token, $uid) : [];
            if ($missing) {
                answerCb($token, $cbId, '❌ هنوز در همه کانال‌ها عضو نشده‌اید.', true);
                return;
            }
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
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'u_cancel', 'style' => gs('cancel') ?: null]]]));
            return;
        }
        if ($data === 'u_batch') {
            answerCb($token, $cbId);
            childSetState($botId, $uid, 'batch', ['files' => []]);
            sendMsg($token, $chatId, "📤 فایل‌ها را یکی‌یکی بفرستید.\nدر پایان /done را بزنید.",
                inlineKb([[['text' => '🔴 انصراف', 'callback_data' => 'u_cancel', 'style' => gs('cancel') ?: null]]]));
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
                inlineKb([[['text' => '◀️ بازگشت', 'callback_data' => 'u_home', 'style' => gs('nav') ?: null]]]));
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

    $isOwner = ($uid === ADMIN_ID);
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

        // 🔒 قفل عضویت اجباری — کانال‌ها از ربات مادر می‌آیند
        if (!empty($s['force_join'])) {
            $missing = Channels::missing($token, $uid);
            if ($missing) { showJoinGate($bot, $chatId, $missing, $code); return; }
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

function childMenu($bot, $chatId, $msgId = null) {
    $s = BotManager::settings($bot['id']);
    $text  = "🤖 <b>پنل اپلودر @" . h($bot['username']) . "</b>\n\n";
    $text .= "🔗 لینک‌ها: " . count(Links::all($bot['id'])) . "\n";
    $text .= "🗑 حذف خودکار: {$s['delete_seconds']} ثانیه\n";
    $text .= "🔒 عضویت اجباری: " . (!empty($s['force_join']) ? 'روشن' : 'خاموش') . "\n\n";
    $text .= "فایل بفرستید تا لینک بسازم.";

    $rows = [
        [['text' => '📤 آپلود تکی', 'callback_data' => 'u_single', 'style' => gs('upload') ?: null],
         ['text' => '📦 آپلود گروهی', 'callback_data' => 'u_batch', 'style' => gs('upload') ?: null]],
        [['text' => '🔗 لینک‌های من', 'callback_data' => 'u_links', 'style' => gs('info') ?: null],
         ['text' => '📊 آمار', 'callback_data' => 'u_stats', 'style' => gs('info') ?: null]],
    ];
    if ($msgId) editMsg($bot['token'], $chatId, $msgId, $text, inlineKb($rows));
    else sendMsg($bot['token'], $chatId, $text, inlineKb($rows));
}

// ============================================================
// 🎯 ورودی
// ============================================================

if (defined('MEMBERSHIP_LIB_ONLY')) return;

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

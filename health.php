<?php
/**
 * 🩺 تشخیص سلامت ربات — فایل کاملا مستقل
 *
 * این فایل هیچ‌کدام از فایل‌های ربات را require نمی‌کند، پس حتی وقتی خود ربات
 * به خاطر خطای PHP بالا نمی‌آید هم کار می‌کند و می‌گوید مشکل کجاست.
 *
 * استفاده: این فایل را کنار بقیه فایل‌ها آپلود کنید و در مرورگر باز کنید:
 *   https://DOMAIN/health.php?key=8213021584
 *
 * بعد از رفع مشکل، این فایل را از هاست پاک کنید.
 */

// ⚠️ هیچ رمزی داخل این فایل نوشته نمی‌شود.
// توکن و کلید ورود از config.local.php (کنار همین فایل) یا متغیر محیطی
// خوانده می‌شوند. این فایل عمدا هیچ فایل دیگری از ربات را require نمی‌کند
// تا وقتی خود ربات بالا نمی‌آید هم کار کند؛ config.local.php استثناست
// چون فقط چند define ساده دارد.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

define('H_TOKEN', defined('BOT_TOKEN') ? BOT_TOKEN : (string)getenv('BOT_TOKEN'));
$H_KEY = defined('HEALTH_KEY') ? HEALTH_KEY : (string)getenv('HEALTH_KEY');

// بدون کلیدِ مخصوص، این صفحه اصلا باز نمی‌شود.
// شناسه‌ی عددی ادمین کلید نیست — همه‌جا دیده می‌شود و حدس‌زدنی است.
if (!is_string($H_KEY) || strlen($H_KEY) < 16) {
    http_response_code(404);
    exit('Not Found');
}

// مقایسه‌ی زمان‌ثابت تا با آزمون‌وخطای زمانی حدس زده نشود
$given = (string)($_GET['key'] ?? '');
if (!hash_equals($H_KEY, $given)) {
    // تاخیر کوچک تا حدس‌زدن پشت‌سرهم بی‌صرفه شود
    usleep(300000);
    http_response_code(404);
    exit('Not Found');
}

/** توکن هیچ‌وقت کامل چاپ نمی‌شود — فقط چند رقم اول برای شناسایی */
function h_mask($t) {
    $t = (string)$t;
    if ($t === '') return '—';
    $p = strpos($t, ':');
    return ($p > 0 ? substr($t, 0, $p) : substr($t, 0, 4)) . ':••••••••';
}

header('Content-Type: text/html; charset=utf-8');

$rows = [];
function row(&$rows, $ok, $title, $detail = '', $fix = '') {
    $rows[] = ['ok' => $ok, 'title' => $title, 'detail' => $detail, 'fix' => $fix];
}

// ───────── ۱) نسخه PHP — اولین چیزی که باید دید ─────────
// اگر این غلط باشد بقیه‌ی فایل‌ها اصلا خوانده نمی‌شوند و در لاگ
// «syntax error» می‌بینید، نه خطای منطقی. این فایل عمدا با ساختار
// قدیمی نوشته شده تا روی هر نسخه‌ای باز شود و بتواند همین را بگوید.
$phpOk = version_compare(PHP_VERSION, '8.0', '>=');
row($rows, $phpOk, 'نسخه PHP', PHP_VERSION . ($phpOk ? ' — مناسب' : ' — خیلی قدیمی'),
    'ربات به PHP 8.0 یا بالاتر نیاز دارد (8.1 یا 8.2 بهتر). ' .
    'در cPanel: «Select PHP Version» ← نسخه را روی 8.1 بگذارید و Set as current را بزنید. ' .
    '⚠️ افزونه‌ها برای هر نسخه جداگانه‌اند — بعد از عوض کردن نسخه، دوباره تیک sodium و curl و mbstring را بزنید.');

// ───────── ۲) افزونه‌های لازم ─────────

row($rows, function_exists('curl_init'), 'افزونه curl',
    function_exists('curl_init') ? 'فعال' : 'غیرفعال',
    'افزونه curl را از کنترل‌پنل هاست فعال کنید.');
row($rows, function_exists('json_encode'), 'افزونه json',
    function_exists('json_encode') ? 'فعال' : 'غیرفعال', 'افزونه json را فعال کنید.');
// ───────── تشخیص دقیق رمزنگاری ─────────
// «افزونه در cPanel تیک دارد ولی تابع نیست» چند علت دارد؛ اینجا
// همه‌شان را جدا جدا نشان می‌دهیم تا معلوم شود کدام است.
$cryptoLines = [];
$cryptoLines[] = 'نسخه PHP: ' . PHP_VERSION . '  (' . PHP_INT_SIZE * 8 . ' بیتی)';
$cryptoLines[] = 'php.ini اصلی: ' . (php_ini_loaded_file() ?: 'پیدا نشد');
$scanned = php_ini_scanned_files();
$cryptoLines[] = 'ini های اضافه: ' . ($scanned ? trim(str_replace(",\n", ' ', $scanned)) : '—');
$cryptoLines[] = 'extension_loaded("sodium"): ' . (extension_loaded('sodium') ? 'بله' : 'خیر');
$cryptoLines[] = 'extension_loaded("libsodium"): ' . (extension_loaded('libsodium') ? 'بله' : 'خیر');
foreach (['sodium_crypto_sign_seed_keypair', 'sodium_crypto_sign_detached',
          'sodium_crypto_sign_publickey', 'sodium_crypto_sign_secretkey'] as $fn) {
    $cryptoLines[] = $fn . '(): ' . (function_exists($fn) ? '✅ هست' : '❌ نیست');
}
$cryptoLines[] = '\Sodium\crypto_sign_seed_keypair(): ' .
    (function_exists('\Sodium\crypto_sign_seed_keypair') ? '✅ هست (نسخه قدیمی PECL)' : '❌ نیست');
$df = trim((string)ini_get('disable_functions'));
$cryptoLines[] = 'disable_functions: ' . ($df !== '' ? $df : '(خالی)');
if ($df !== '' && stripos($df, 'sodium') !== false)
    $cryptoLines[] = '🔴 توابع sodium در disable_functions بسته شده‌اند — از پشتیبانی هاست بخواهید بازشان کند.';
$cryptoLines[] = 'gmp: ' . (extension_loaded('gmp') ? '✅' : '❌') .
                 '   bcmath: ' . (extension_loaded('bcmath') ? '✅' : '❌') .
                 '   openssl: ' . (extension_loaded('openssl') ? '✅' : '❌');
$cryptoLines[] = 'sha512 در hash: ' . (in_array('sha512', hash_algos(), true) ? '✅' : '❌') .
                 '   hash_pbkdf2: ' . (function_exists('hash_pbkdf2') ? '✅' : '❌');

$hasSodium = function_exists('sodium_crypto_sign_seed_keypair');
$hasCompat = is_file(__DIR__ . '/sodium_compat/autoload.php') || is_file(__DIR__ . '/vendor/autoload.php');
row($rows, $hasSodium || $hasCompat, 'افزونه sodium (فقط برای امضای TON)',
    $hasSodium ? 'فعال' : ($hasCompat ? 'جایگزین PHP پیدا شد' : 'غیرفعال'),
    'فقط برای «امضای خودکار تراکنش TON» لازم است؛ بقیه ربات بدون آن کار می‌کند. ' .
    'در پنل هاست ← Select PHP Version ← Extensions تیک sodium را بزنید. ' .
    'اگر نشد، پوشه sodium_compat را از github.com/paragonie/sodium_compat کنار فایل‌ها بگذارید.');
row($rows, in_array('sha512', hash_algos(), true) && function_exists('hash_pbkdf2'),
    'hash با sha512', in_array('sha512', hash_algos(), true) ? 'فعال' : 'غیرفعال',
    'افزونه hash را فعال کنید.');
row($rows, function_exists('mb_substr'), 'افزونه mbstring',
    function_exists('mb_substr') ? 'فعال' : 'غیرفعال',
    'افزونه mbstring را فعال کنید — بدون آن متن فارسی درست بریده نمی‌شود.');

// ───────── ⚡️ opcache — بزرگ‌ترین عاملِ سرعتِ ربات ─────────
//
// کدِ ربات نزدیک به یک‌ونیم مگابایت است. بدون opcache، PHP در هر
// درخواست کلش را از نو می‌خواند و کامپایل می‌کند — روی سرورِ آزمون
// ۳۳ میلی‌ثانیه، و این پیش از آنکه ربات حتی یک کار انجام دهد.
// با opcache همان کار ۵ میلی‌ثانیه است.
//
// هیچ تنظیمی داخل کد نمی‌تواند روشنش کند؛ در php.ini است.
$opOn   = function_exists('opcache_get_status') && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN);
$opStat = $opOn && function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
$opUsed = is_array($opStat) && !empty($opStat['opcache_enabled']);

$opDetail = '';
if (!function_exists('opcache_get_status')) {
    $opDetail = 'افزونه‌ی opcache روی این سرور نصب نیست';
} elseif (!$opUsed) {
    $opDetail = 'نصب هست ولی خاموش است';
} else {
    $mem  = $opStat['memory_usage'] ?? [];
    $used = (float)($mem['used_memory'] ?? 0) / 1048576;
    $free = (float)($mem['free_memory'] ?? 0) / 1048576;
    $hits = (float)($opStat['opcache_statistics']['opcache_hit_rate'] ?? 0);
    $n    = (int)($opStat['opcache_statistics']['num_cached_scripts'] ?? 0);
    $opDetail = sprintf('روشن — %d فایل در کش · %.1f٪ اصابت · %.0f مگابایت مصرف از %.0f آزاد',
                        $n, $hits, $used, $free);
    if ($free < 8) $opDetail .= ' ⚠️ حافظه‌اش دارد تمام می‌شود';
}
row($rows, $opUsed, '⚡️ opcache (سرعت کل ربات)', $opDetail,
    'در php.ini این‌ها را بگذارید و PHP را ری‌استارت کنید:<br>' .
    '<code>opcache.enable=1</code><br>' .
    '<code>opcache.memory_consumption=128</code><br>' .
    '<code>opcache.max_accelerated_files=10000</code><br>' .
    '<code>opcache.validate_timestamps=1</code><br>' .
    '<code>opcache.revalidate_freq=2</code><br>' .
    'در سی‌پنل: Select PHP Version ← Extensions ← تیک opcache. ' .
    'این تنها تغییری است که سرعتِ کلِ ربات را چند برابر می‌کند.');

// ───────── ۳) فایل‌ها ─────────
$need = ['bot_master_membership.php', 'miniapps.php', 'miniapp_view_tg.php', 'miniapp_view_num.php', 'numbers.php', 'admin_ext.php', 'profit.php', 'ton_wallet.php'];
$missing = [];
foreach ($need as $f) if (!is_file(__DIR__ . '/' . $f)) $missing[] = $f;
row($rows, !$missing, 'فایل‌های ربات',
    $missing ? 'پیدا نشد: ' . implode('، ', $missing) : 'همه‌ی فایل‌ها کنار هم هستند',
    'همه‌ی این فایل‌ها باید در همین پوشه (' . __DIR__ . ') کنار هم باشند.');

// ───────── ۴) اجازه نوشتن ─────────
$dir = __DIR__ . '/data_master';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$canWrite = is_dir($dir) && is_writable($dir);
row($rows, $canWrite, 'پوشه داده (data_master)',
    $canWrite ? 'قابل نوشتن است' : (is_dir($dir) ? 'ساخته شد ولی قابل نوشتن نیست' : 'ساخته نشد'),
    'دسترسی پوشه را روی 755 (یا در صورت نیاز 775) بگذارید و مالکش را کاربر وب‌سرور کنید.');

// ───────── ۵) خطای نحوی خود فایل ربات ─────────
$syntax = 'بررسی نشد';
$syntaxOk = true;
if (is_file(__DIR__ . '/bot_master_membership.php') && function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)) {
    $out = []; $code = 0;
    @exec('php -l ' . escapeshellarg(__DIR__ . '/bot_master_membership.php') . ' 2>&1', $out, $code);
    if ($out) {
        $syntax   = implode(' ', $out);
        $syntaxOk = ($code === 0);
    }
}
row($rows, $syntaxOk, 'بررسی نحوی فایل ربات', $syntax,
    'اگر خطای نحوی دارد یعنی فایل موقع آپلود ناقص یا خراب شده — دوباره آپلود کنید (حتما در حالت Binary نه ASCII).');

// ───────── ۶) تلگرام: توکن ─────────
function h_api($method, $data = []) {
    $url = 'https://api.telegram.org/bot' . H_TOKEN . '/' . $method;
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return ['ok' => false, 'description' => 'خطای اتصال: ' . $err];
    $j = json_decode($res, true);
    return is_array($j) ? $j : ['ok' => false, 'description' => 'پاسخ نامعتبر: ' . substr((string)$res, 0, 200)];
}

$me = function_exists('curl_init') ? h_api('getMe') : ['ok' => false, 'description' => 'curl ندارد'];
row($rows, H_TOKEN !== '', 'توکن از کجا خوانده شد',
    H_TOKEN !== '' ? h_mask(H_TOKEN) . ' — از config.local.php یا متغیر محیطی' : 'هیچ توکنی تنظیم نشده',
    'کنار همین فایل یک <code>config.local.php</code> بسازید و داخلش ' .
    "<code>define('BOT_TOKEN', '…');</code> بگذارید.");

row($rows, !empty($me['ok']), 'توکن ربات',
    !empty($me['ok'])
        ? '@' . ($me['result']['username'] ?? '?') . ' — ' . ($me['result']['first_name'] ?? '')
        : ($me['description'] ?? 'نامشخص'),
    'اگر اینجا خطا می‌دهد یعنی سرور به تلگرام دسترسی ندارد (تحریم/فایروال) یا توکن اشتباه است. ' .
    'روی هاست ایرانی معمولا باید دامنه api.telegram.org را از فایروال باز کنید یا از هاست خارجی استفاده کنید.');

// ───────── ۷) تلگرام: وضعیت وبهوک ─────────
$wh = function_exists('curl_init') ? h_api('getWebhookInfo') : ['ok' => false];
$whUrl = $wh['result']['url'] ?? '';
$guess = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' .
         ($_SERVER['HTTP_HOST'] ?? 'DOMAIN') .
         rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/bot_master_membership.php';

row($rows, $whUrl !== '', 'آدرس وبهوک',
    $whUrl !== '' ? $whUrl : '<b>ست نشده</b>',
    'وبهوک ست نشده. از دکمه‌ی زیر استفاده کنید (توکن عمدا اینجا چاپ نمی‌شود ' .
    'تا اگر کسی این صفحه را دید نتواند ربات را بدزدد):<br>' .
    '<a class="fixbtn" href="?key=' . rawurlencode($given) . '&amp;setwebhook=1">🔗 وبهوک را همین‌جا ست کن</a>' .
    '<br><span class="muted">مقصد: <code>' . htmlspecialchars($guess, ENT_QUOTES, 'UTF-8') . '</code></span>');

// ست کردن وبهوک از خود همین صفحه — بدون اینکه توکن جایی چاپ شود
if (!empty($_GET['setwebhook']) && function_exists('curl_init')) {
    $sw = h_api('setWebhook', ['url' => $guess, 'drop_pending_updates' => 'true']);
    row($rows, !empty($sw['ok']), 'ست کردن وبهوک',
        !empty($sw['ok']) ? 'انجام شد → ' . $guess : ($sw['description'] ?? 'ناموفق'),
        'اگر ناموفق بود یعنی سرور به تلگرام دسترسی ندارد یا آدرس https معتبر نیست.');
    $wh    = h_api('getWebhookInfo');
    $whUrl = $wh['result']['url'] ?? '';
}

$sameFile = $whUrl !== '' && strpos($whUrl, 'bot_master_membership.php') !== false;
if ($whUrl !== '') {
    row($rows, $sameFile, 'وبهوک به فایل درست وصل است؟',
        $sameFile ? 'بله' : 'وبهوک به فایل دیگری وصل است',
        'وبهوک باید دقیقا به <code>bot_master_membership.php</code> وصل باشد.');
}

$lastErr  = $wh['result']['last_error_message'] ?? '';
$lastDate = !empty($wh['result']['last_error_date']) ? date('Y-m-d H:i:s', (int)$wh['result']['last_error_date']) : '';
row($rows, $lastErr === '', 'آخرین خطای وبهوک',
    $lastErr === '' ? 'خطایی ثبت نشده' : ($lastErr . ($lastDate ? ' — ' . $lastDate : '')),
    'این پیام دقیقا می‌گوید تلگرام موقع صدا زدن ربات چه دیده. ' .
    '«500 Internal Server Error» یعنی خطای PHP (معمولا نسخه PHP قدیمی)؛ ' .
    '«SSL error» یعنی گواهی https سالم نیست؛ «404» یعنی آدرس اشتباه است.');

$pending = (int)($wh['result']['pending_update_count'] ?? 0);
row($rows, $pending < 20, 'پیام‌های منتظر پردازش', (string)$pending,
    'اگر عدد بالا و ثابت است یعنی ربات جواب نمی‌دهد و پیام‌ها روی هم انباشته شده‌اند.');

// ───────── ۸) آدرس عمومی مینی‌اپ ─────────
$base = '';
$cfgFile = $dir . '/config.json';
if (is_file($cfgFile)) {
    $c = json_decode((string)@file_get_contents($cfgFile), true);
    $base = $c['miniapps']['base_url'] ?? '';
}
row($rows, $base !== '', 'آدرس عمومی مینی‌اپ‌ها',
    $base !== '' ? $base : 'هنوز ثبت نشده',
    'داخل ربات: /panel ← 🚀 مینی اپ‌ها ← 🔗 آدرس عمومی ← <code>' . htmlspecialchars($guess) . '</code>');

$bad  = array_values(array_filter($rows, function ($r) { return !$r['ok']; }));
$good = count($rows) - count($bad);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>تشخیص سلامت ربات</title>
<style>
body{background:#0B0E14;color:#E6EAF2;font-family:Tahoma,system-ui,sans-serif;margin:0;padding:20px;line-height:1.9}
.box{max-width:760px;margin:0 auto}
h1{font-size:21px;margin:0 0 6px}
.sum{font-size:13px;color:#8B93A7;margin-bottom:20px}
.item{border:1px solid #1E2533;border-radius:14px;padding:14px 16px;margin-bottom:11px;background:#111621}
.item.bad{border-color:#5A1E28;background:#17101300}
.item.bad{background:#180F13}
.t{font-weight:700;font-size:14.5px;display:flex;gap:8px;align-items:center}
.d{font-size:13px;color:#9FB0C9;margin-top:5px;word-break:break-all;direction:ltr;text-align:left}
.d.fa{direction:rtl;text-align:right}
.f{font-size:12.5px;color:#FFC46B;margin-top:9px;padding-top:9px;border-top:1px dashed #2A3242}
code{background:#0A0D14;padding:2px 6px;border-radius:6px;font-size:12px;word-break:break-all}
.ok{color:#4ADE80}.no{color:#F87171}
.muted{color:#6E7891}
.fixbtn{display:inline-block;margin-top:8px;background:#1D4ED8;color:#fff;text-decoration:none;
        padding:8px 15px;border-radius:10px;font-size:13px}
</style>
</head>
<body><div class="box">
<h1>🩺 تشخیص سلامت ربات</h1>
<div class="sum"><?= $good ?> مورد سالم · <?= count($bad) ?> مورد نیازمند رسیدگی</div>

<?php foreach ($rows as $r): ?>
  <div class="item<?= $r['ok'] ? '' : ' bad' ?>">
    <div class="t"><span class="<?= $r['ok'] ? 'ok' : 'no' ?>"><?= $r['ok'] ? '✔' : '✖' ?></span><?= $r['title'] ?></div>
    <div class="d<?= preg_match('/[\x{0600}-\x{06FF}]/u', strip_tags($r['detail'])) ? ' fa' : '' ?>"><?= $r['detail'] ?></div>
    <?php if (!$r['ok'] && $r['fix']): ?><div class="f">🔧 <?= $r['fix'] ?></div><?php endif; ?>
  </div>
<?php endforeach; ?>

<div class="item">
  <div class="t">🔐 جزئیات رمزنگاری (برای امضای TON)</div>
  <?php foreach ($cryptoLines as $cl): ?>
    <div class="d<?= preg_match('/[\x{0600}-\x{06FF}]/u', $cl) ? ' fa' : '' ?>"><?= htmlspecialchars($cl) ?></div>
  <?php endforeach; ?>
</div>

<div class="item">
  <div class="t">📍 مسیر روی سرور</div>
  <div class="d"><?= htmlspecialchars(__DIR__) ?></div>
  <div class="d">آدرس حدسی ربات: <?= htmlspecialchars($guess) ?></div>
</div>

<div class="sum" style="margin-top:18px">بعد از رفع مشکل، این فایل (health.php) را از هاست پاک کنید.</div>
</div></body>
</html>

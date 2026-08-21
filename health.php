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

const H_TOKEN = '8844162743:AAHkwPZ4svLSXgkZ2-PxvNRYNGMEWvhxHaQ';
const H_ADMIN = '8213021584';

if (($_GET['key'] ?? '') !== H_ADMIN) {
    http_response_code(403);
    exit('forbidden');
}

header('Content-Type: text/html; charset=utf-8');

$rows = [];
function row(&$rows, $ok, $title, $detail = '', $fix = '') {
    $rows[] = ['ok' => $ok, 'title' => $title, 'detail' => $detail, 'fix' => $fix];
}

// ───────── ۱) نسخه PHP ─────────
$php = PHP_VERSION;
row($rows, version_compare($php, '8.0.0', '>='),
    'نسخه PHP', $php,
    'کد به PHP 8.0 یا بالاتر نیاز دارد. از کنترل‌پنل هاست (cPanel ← Select PHP Version یا MultiPHP Manager) نسخه را روی 8.1 یا 8.2 بگذارید.');

// ───────── ۲) افزونه‌های لازم ─────────
row($rows, function_exists('curl_init'), 'افزونه curl',
    function_exists('curl_init') ? 'فعال' : 'غیرفعال',
    'افزونه curl را از کنترل‌پنل هاست فعال کنید.');
row($rows, function_exists('json_encode'), 'افزونه json',
    function_exists('json_encode') ? 'فعال' : 'غیرفعال', 'افزونه json را فعال کنید.');
row($rows, function_exists('mb_substr'), 'افزونه mbstring',
    function_exists('mb_substr') ? 'فعال' : 'غیرفعال',
    'افزونه mbstring را فعال کنید — بدون آن متن فارسی درست بریده نمی‌شود.');

// ───────── ۳) فایل‌ها ─────────
$need = ['bot_master_membership.php', 'miniapps.php', 'miniapp_view_tg.php', 'miniapp_view_cfg.php'];
$missing = [];
foreach ($need as $f) if (!is_file(__DIR__ . '/' . $f)) $missing[] = $f;
row($rows, !$missing, 'فایل‌های ربات',
    $missing ? 'پیدا نشد: ' . implode('، ', $missing) : 'هر چهار فایل کنار هم هستند',
    'هر چهار فایل باید در همین پوشه (' . __DIR__ . ') کنار هم باشند.');

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
    'وبهوک ست نشده. این آدرس را یک بار در مرورگر باز کنید:<br><code>https://api.telegram.org/bot' .
    H_TOKEN . '/setWebhook?url=' . rawurlencode($guess) . '</code>');

$sameFile = $whUrl !== '' && str_contains($whUrl, 'bot_master_membership.php');
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

$bad  = array_values(array_filter($rows, fn($r) => !$r['ok']));
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
  <div class="t">📍 مسیر روی سرور</div>
  <div class="d"><?= htmlspecialchars(__DIR__) ?></div>
  <div class="d">آدرس حدسی ربات: <?= htmlspecialchars($guess) ?></div>
</div>

<div class="sum" style="margin-top:18px">بعد از رفع مشکل، این فایل (health.php) را از هاست پاک کنید.</div>
</div></body>
</html>

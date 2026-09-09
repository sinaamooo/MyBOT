<?php

declare(strict_types=1);

/**
 * ============================================================================
 * setup.php — self-check / installer page.
 *
 * Exists because every failure on the webhook path is deliberately silent:
 * bot.php answers Telegram with 200 OK no matter what goes wrong (otherwise
 * Telegram retries in a storm), so a bot that does not respond to /start
 * gives you exactly nothing to go on. This page turns that silence into an
 * answer — PHP version, extensions, env.php, storage permissions, whether
 * the token is valid, and above all what getWebhookInfo() says, since a
 * missing or broken webhook is by far the most common cause.
 *
 * Open it in a browser:  https://YOURDOMAIN/setup.php
 *
 * The environment checks are open (they expose nothing an attacker could
 * use). Anything Telegram-related, the error log, and the "register
 * webhook" action are locked behind pasting the bot token, which proves
 * ownership. The token is posted, never put in the URL, so it does not end
 * up in the server's access log.
 *
 * DELETE THIS FILE once the bot works.
 * ============================================================================
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/signal.php';
require_once __DIR__ . '/bot.php';

// ============================================================================
// Checks
// ============================================================================

/** @return array{0:string,1:bool,2:string} [label, ok, detail] */
function check(string $label, bool $ok, string $detail = ''): array
{
    return [$label, $ok, $detail];
}

$configuredToken = '';
$tokenConfigured = false;
try {
    $configuredToken = Config::telegramBotToken();
    $tokenConfigured = true;
} catch (Throwable) {
    // Left false — reported as a failed check below.
}

$unlocked = false;
$badToken = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $tokenConfigured) {
    $posted = trim((string) ($_POST['token'] ?? ''));
    if ($posted !== '' && hash_equals($configuredToken, $posted)) {
        $unlocked = true;
    } else {
        $badToken = true;
    }
}

// -- environment -------------------------------------------------------------
$env = [];
$env[] = check(
    'نسخه PHP (حداقل 8.1)',
    PHP_VERSION_ID >= 80100,
    PHP_VERSION
);
foreach (['curl' => 'ارتباط با تلگرام و صرافی‌ها', 'pdo_sqlite' => 'دیتابیس', 'mbstring' => 'پردازش متن'] as $ext => $why) {
    $env[] = check("افزونه $ext ($why)", extension_loaded($ext));
}
$env[] = check(
    'افزونه gd (کارت تصویری) — اختیاری',
    CardConfig::available(),
    CardConfig::available() ? '' : 'بدون این افزونه ربات فقط پیام متنی می‌فرستد'
);
$env[] = check(
    'فونت فارسی روی کارت — اختیاری',
    CardConfig::supportsPersian(),
    CardConfig::supportsPersian()
        ? basename((string) CardConfig::fontPath())
        : 'فایل Vazirmatn-Regular.ttf پیدا نشد؛ برچسب کارت‌ها انگلیسی می‌شود'
);

// -- configuration -----------------------------------------------------------
$conf = [];
$conf[] = check(
    'فایل env.php',
    is_file(__DIR__ . '/env.php'),
    is_file(__DIR__ . '/env.php') ? '' : 'env.example.php را کپی کنید و اسمش را env.php بگذارید'
);
$conf[] = check(
    'TELEGRAM_BOT_TOKEN',
    $tokenConfigured,
    $tokenConfigured ? 'تنظیم شده' : 'خالی است — ربات بدون آن هیچ پیامی نمی‌فرستد'
);
$adminIds = Config::adminIds();
$conf[] = check(
    'ADMIN_IDS',
    !empty($adminIds),
    empty($adminIds) ? 'خالی است — پنل مدیریت برای هیچ‌کس باز نمی‌شود' : count($adminIds) . ' ادمین'
);
$secret = Config::telegramWebhookSecret();
$conf[] = check(
    'TELEGRAM_WEBHOOK_SECRET',
    true,
    $secret === ''
        ? 'خالی (بررسی امضا غیرفعال است — مشکلی ندارد)'
        : 'تنظیم شده — باید دقیقاً همین مقدار موقع setWebhook ثبت شده باشد'
);

// -- storage + database ------------------------------------------------------
$storage = [];
$storageDir = Config::storageDir();
$storageOk = false;
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0755, true);
}
$storageOk = is_dir($storageDir) && is_writable($storageDir);
$storage[] = check('پوشه storage قابل نوشتن', $storageOk, $storageDir);

$migrated = false;
$migrateError = '';
if ($storageOk) {
    try {
        Database::migrate();
        $migrated = true;
    } catch (Throwable $e) {
        $migrateError = $e->getMessage();
    }
}
$storage[] = check('ساخت جدول‌های دیتابیس', $migrated, $migrateError);

// -- telegram (locked) -------------------------------------------------------
$me = null;
$hook = null;
$hookActionMessage = '';
$expectedUrl = '';

if ($unlocked) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $expectedUrl = $scheme . '://' . $host . $dir . '/bot.php';

    $telegram = new TelegramClient();

    if (($_POST['action'] ?? '') === 'set_webhook') {
        $res = $telegram->setWebhook($expectedUrl, $secret);
        $hookActionMessage = ($res['ok'] ?? false)
            ? '✅ وبهوک با موفقیت روی ' . $expectedUrl . ' ثبت شد.'
            : '❌ ثبت وبهوک ناموفق بود: ' . (string) ($res['description'] ?? 'خطای نامشخص');
    }

    $meRes = $telegram->getMe();
    $me = ($meRes['ok'] ?? false) ? ($meRes['result'] ?? null) : null;

    $hookRes = $telegram->request('getWebhookInfo');
    $hook = ($hookRes['ok'] ?? false) ? ($hookRes['result'] ?? null) : null;
}

// -- recent errors (locked) --------------------------------------------------
$recentErrors = [];
if ($unlocked && $migrated) {
    try {
        $recentErrors = Database::pdo()->query(
            "SELECT created_at, channel, message FROM logs
             WHERE level IN ('error','critical') ORDER BY id DESC LIMIT 15"
        )->fetchAll();
    } catch (Throwable) {
        $recentErrors = [];
    }
}

$allOk = true;
foreach ([$env, $conf, $storage] as $group) {
    foreach ($group as [$label, $ok, $detail]) {
        // The two optional rows must not turn the overall verdict red.
        if (!$ok && !str_contains($label, 'اختیاری')) {
            $allOk = false;
        }
    }
}

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>بررسی نصب ربات سیگنال</title>
<style>
  :root { color-scheme: dark; }
  * { box-sizing: border-box; }
  body { margin:0; padding:24px; background:#080c0a; color:#e8f5ee;
         font-family: Tahoma, "Segoe UI", system-ui, sans-serif; line-height:1.9; }
  .wrap { max-width: 860px; margin: 0 auto; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  h2 { font-size: 17px; margin: 28px 0 10px; color:#2ee796; }
  .sub { color:#8fa89c; font-size:14px; margin-bottom:18px; }
  .card { background:#0f1713; border:1px solid #1e2c25; border-radius:14px; padding:14px 18px; margin-bottom:14px; }
  .row { display:flex; gap:10px; padding:7px 0; border-bottom:1px solid #16211c; align-items:flex-start; }
  .row:last-child { border-bottom:0; }
  .mark { flex:0 0 22px; font-size:15px; }
  .label { flex:1; }
  .detail { color:#8fa89c; font-size:13px; word-break:break-all; }
  /* Paths and URLs inside RTL text get their leading slash visually reordered;
     plaintext/auto direction lets each string pick its own direction. */
  .label { unicode-bidi: plaintext; }
  .ok { color:#2ee796; } .bad { color:#ff9a3c; }
  .banner { padding:14px 18px; border-radius:14px; margin-bottom:20px; font-weight:bold; }
  .banner.good { background:#0f2a1e; border:1px solid #2ee796; color:#2ee796; }
  .banner.warn { background:#2a1f0f; border:1px solid #ffa82e; color:#ffa82e; }
  input[type=password], input[type=text] { width:100%; padding:10px 12px; border-radius:10px;
      border:1px solid #24352c; background:#0a110d; color:#e8f5ee; font-family:inherit; font-size:14px; }
  button { margin-top:10px; padding:10px 22px; border-radius:10px; border:0; cursor:pointer;
      background:#2ee796; color:#05130c; font-weight:bold; font-family:inherit; font-size:14px; }
  code { background:#0a110d; padding:2px 6px; border-radius:6px; font-size:13px; direction:ltr;
      display:inline-block; word-break:break-all; }
  .note { color:#8fa89c; font-size:13px; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  td { padding:6px 4px; border-bottom:1px solid #16211c; vertical-align:top; }
  td.when { color:#8fa89c; white-space:nowrap; direction:ltr; }
</style>
</head>
<body>
<div class="wrap">

<h1>بررسی نصب ربات سیگنال</h1>
<div class="sub">اگر ربات به <code>/start</code> جواب نمی‌دهد، جواب معمولاً همین‌جاست.</div>

<div class="banner <?= $allOk ? 'good' : 'warn' ?>">
  <?= $allOk ? '✅ همه بررسی‌های پایه سالم هستند.' : '⚠️ چند مورد ایراد دارد — موارد نارنجی پایین را درست کنید.' ?>
</div>

<h2>۱. محیط سرور</h2>
<div class="card">
<?php foreach ($env as [$label, $ok, $detail]): ?>
  <div class="row">
    <div class="mark <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✖' ?></div>
    <div class="label"><?= h($label) ?><?php if ($detail !== ''): ?><div class="detail" dir="auto"><?= h($detail) ?></div><?php endif; ?></div>
  </div>
<?php endforeach; ?>
</div>

<h2>۲. تنظیمات (env.php)</h2>
<div class="card">
<?php foreach ($conf as [$label, $ok, $detail]): ?>
  <div class="row">
    <div class="mark <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✖' ?></div>
    <div class="label"><?= h($label) ?><?php if ($detail !== ''): ?><div class="detail" dir="auto"><?= h($detail) ?></div><?php endif; ?></div>
  </div>
<?php endforeach; ?>
</div>

<h2>۳. دیتابیس</h2>
<div class="card">
<?php foreach ($storage as [$label, $ok, $detail]): ?>
  <div class="row">
    <div class="mark <?= $ok ? 'ok' : 'bad' ?>"><?= $ok ? '✔' : '✖' ?></div>
    <div class="label"><?= h($label) ?><?php if ($detail !== ''): ?><div class="detail" dir="auto"><?= h($detail) ?></div><?php endif; ?></div>
  </div>
<?php endforeach; ?>
</div>

<h2>۴. تلگرام و وبهوک</h2>
<?php if (!$tokenConfigured): ?>
  <div class="card">
    <div class="bad">تا وقتی <code>TELEGRAM_BOT_TOKEN</code> در <code>env.php</code> تنظیم نشده، این بخش قابل بررسی نیست.
    همین احتمالاً دلیل جواب ندادن ربات است.</div>
  </div>
<?php elseif (!$unlocked): ?>
  <div class="card">
    <div class="note">برای دیدن وضعیت وبهوک و خطاها، توکن ربات را وارد کنید (فقط برای اثبات مالکیت؛ جایی ذخیره نمی‌شود).</div>
    <?php if ($badToken): ?><div class="bad">توکن وارد شده با توکن داخل env.php یکی نیست.</div><?php endif; ?>
    <form method="post">
      <input type="password" name="token" placeholder="123456789:AA..." autocomplete="off" required>
      <button type="submit">نمایش وضعیت</button>
    </form>
  </div>
<?php else: ?>
  <?php if ($hookActionMessage !== ''): ?>
    <div class="banner <?= str_starts_with($hookActionMessage, '✅') ? 'good' : 'warn' ?>"><?= h($hookActionMessage) ?></div>
  <?php endif; ?>
  <div class="card">
    <div class="row">
      <div class="mark <?= $me !== null ? 'ok' : 'bad' ?>"><?= $me !== null ? '✔' : '✖' ?></div>
      <div class="label">توکن معتبر است
        <div class="detail" dir="auto"><?= $me !== null ? '@' . h((string) ($me['username'] ?? '')) : 'تلگرام این توکن را قبول نکرد — توکن را از BotFather دوباره بگیرید' ?></div>
      </div>
    </div>
    <?php $hookUrl = (string) ($hook['url'] ?? ''); ?>
    <div class="row">
      <div class="mark <?= $hookUrl !== '' ? 'ok' : 'bad' ?>"><?= $hookUrl !== '' ? '✔' : '✖' ?></div>
      <div class="label">وبهوک ثبت شده
        <div class="detail" dir="auto"><?= $hookUrl !== '' ? h($hookUrl) : 'هیچ وبهوکی ثبت نشده — به همین دلیل تلگرام هیچ پیامی به سرور شما نمی‌فرستد' ?></div>
      </div>
    </div>
    <?php if ($hookUrl !== '' && $expectedUrl !== '' && $hookUrl !== $expectedUrl): ?>
    <div class="row">
      <div class="mark bad">✖</div>
      <div class="label">آدرس وبهوک با آدرس این سرور فرق دارد
        <div class="detail" dir="auto">ثبت‌شده: <?= h($hookUrl) ?><br>باید باشد: <?= h($expectedUrl) ?></div>
      </div>
    </div>
    <?php endif; ?>
    <?php $lastErr = (string) ($hook['last_error_message'] ?? ''); ?>
    <?php if ($lastErr !== ''): ?>
    <div class="row">
      <div class="mark bad">✖</div>
      <div class="label">آخرین خطای تلگرام موقع صدا زدن وبهوک
        <div class="detail" dir="auto"><?= h($lastErr) ?></div>
      </div>
    </div>
    <?php endif; ?>
    <div class="row">
      <div class="mark">•</div>
      <div class="label">پیام‌های در صف تلگرام
        <div class="detail" dir="auto"><?= (int) ($hook['pending_update_count'] ?? 0) ?></div>
      </div>
    </div>
    <?php if ($secret !== '' && !($hook['has_custom_certificate'] ?? false)): ?>
    <div class="row">
      <div class="mark">•</div>
      <div class="label">در env.php یک TELEGRAM_WEBHOOK_SECRET تنظیم شده
        <div class="detail" dir="auto">اگر وبهوک با مقدار دیگری ثبت شده باشد، bot.php همه پیام‌ها را رد می‌کند. دکمه زیر آن را با همین مقدار دوباره ثبت می‌کند.</div>
      </div>
    </div>
    <?php endif; ?>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="token" value="<?= h($configuredToken) ?>">
      <input type="hidden" name="action" value="set_webhook">
      <button type="submit">ثبت وبهوک روی <?= h($expectedUrl) ?></button>
    </form>
  </div>

  <h2>۵. آخرین خطاهای ثبت‌شده</h2>
  <div class="card">
    <?php if (empty($recentErrors)): ?>
      <div class="note">خطایی ثبت نشده. (توجه: <code>LOG_LEVEL</code> به‌صورت پیش‌فرض روی <code>error</code> است،
      یعنی فقط خطاهای واقعی اینجا می‌آیند.)</div>
    <?php else: ?>
      <table>
        <?php foreach ($recentErrors as $row): ?>
          <tr>
            <td class="when"><?= h((string) $row['created_at']) ?></td>
            <td><?= h((string) $row['channel']) ?></td>
            <td><?= h((string) $row['message']) ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<h2>بعد از درست شدن</h2>
<div class="card">
  <div class="note">
    ۱. در تلگرام به ربات <code>/start</code> بزنید، بعد <code>/panel</code> برای پنل مدیریت.<br>
    ۲. یک Cron Job هر دقیقه بسازید:
    <code>php <?= h(__DIR__) ?>/worker.php</code><br>
    ۳. <strong>این فایل (<code>setup.php</code>) را پاک کنید.</strong>
  </div>
</div>

</div>
</body>
</html>

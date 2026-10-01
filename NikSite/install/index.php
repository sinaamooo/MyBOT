<?php
/**
 * Numbix web installer — /install
 */
const NBX_VERSION = '1.0.0';
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');

require APP_PATH . '/core/DB.php';
require APP_PATH . '/core/helpers.php';
require APP_PATH . '/core/jdate.php';
require __DIR__ . '/seed.php';

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');
session_name('nbx_install');
session_start();

$lock = STORAGE_PATH . '/installed.lock';
$configFile = BASE_PATH . '/config.php';
$installed = is_file($lock) && is_file($configFile);

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$basePath = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php'))), '/.');
$guessUrl = ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath;
$assets = $basePath . '/assets';

$checks = [
    ['PHP نسخه ۸.۰ یا بالاتر', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION],
    ['افزونه PDO MySQL', extension_loaded('pdo_mysql'), ''],
    ['افزونه mbstring', extension_loaded('mbstring'), ''],
    ['افزونه cURL', extension_loaded('curl'), ''],
    ['افزونه JSON', extension_loaded('json'), ''],
    ['افزونه fileinfo', extension_loaded('fileinfo'), ''],
    ['پوشه storage قابل نوشتن', is_writable(STORAGE_PATH), ''],
    ['پوشه uploads قابل نوشتن', is_writable(BASE_PATH . '/uploads'), ''],
    ['امکان ساخت config.php', is_writable(BASE_PATH) || (is_file($configFile) && is_writable($configFile)), 'در صورت عدم امکان، محتوا نمایش داده می‌شود'],
];
$canInstall = !in_array(false, array_slice(array_column($checks, 1), 0, 6), true);

$error = null;
$done = null;
if (!$installed && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['_token'] ?? ''))) {
        $error = 'نشست منقضی شده است؛ صفحه را دوباره بارگذاری کنید.';
    } else {
        $in = fn($k, $d = '') => trim((string)($_POST[$k] ?? $d));
        $db = ['host' => $in('db_host', 'localhost'), 'port' => (int)$in('db_port', '3306'), 'name' => $in('db_name'), 'user' => $in('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')];
        $siteUrl = rtrim($in('site_url', $guessUrl), '/');
        $siteName = $in('site_name', 'نامبیکس') ?: 'نامبیکس';
        $adminName = $in('admin_name', 'مدیر');
        $adminEmail = mb_strtolower($in('admin_email'));
        $adminPass = (string)($_POST['admin_pass'] ?? '');
        try {
            if ($db['name'] === '' || $db['user'] === '') {
                throw new RuntimeException('نام دیتابیس و نام کاربری دیتابیس را وارد کنید.');
            }
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('ایمیل مدیر معتبر نیست.');
            }
            if (mb_strlen($adminPass) < 8) {
                throw new RuntimeException('رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.');
            }
            if (!preg_match('~^https?://~', $siteUrl)) {
                throw new RuntimeException('آدرس سایت باید با http:// یا https:// شروع شود.');
            }
            try {
                DB::connect($db);
            } catch (PDOException $e) {
                $hint = '';
                if (str_contains($e->getMessage(), 'Access denied')) {
                    $prefix = str_contains($db['name'], '_') ? explode('_', $db['name'], 2)[0] . '_' : '';
                    $hint = ' — نام کاربری یا رمز دیتابیس اشتباه است، یا کاربر به دیتابیس متصل نشده است.';
                    if ($prefix && !str_starts_with($db['user'], $prefix)) {
                        $hint .= ' در cPanel نام کاربری دیتابیس پیشوند دارد؛ احتمالاً باید «' . $prefix . $db['user'] . '» را وارد کنید.';
                    }
                }
                throw new RuntimeException('اتصال به دیتابیس ناموفق بود' . $hint . ' (' . $e->getMessage() . ')');
            }
            if ((int)DB::value("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'") > 0 && empty($_POST['overwrite'])) {
                throw new RuntimeException('این دیتابیس از قبل جداول نامبیکس را دارد. برای نصب مجدد، گزینه «حذف داده‌های قبلی» را فعال کنید.');
            }
            if (!empty($_POST['overwrite'])) {
                DB::query('SET FOREIGN_KEY_CHECKS = 0');
                foreach (['ticket_messages', 'tickets', 'transactions', 'orders', 'payments', 'coupon_uses', 'coupons', 'stock_logs', 'services', 'providers', 'categories', 'banners', 'posts', 'faqs', 'favorites', 'notifications', 'settings', 'login_attempts', 'password_resets', 'users'] as $t) {
                    DB::query("DROP TABLE IF EXISTS `$t`");
                }
                DB::query('SET FOREIGN_KEY_CHECKS = 1');
            }
            $sql = (string)file_get_contents(__DIR__ . '/schema.sql');
            foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
                $stmt = trim(preg_replace('/^--.*$/m', '', $stmt));
                if ($stmt !== '') {
                    DB::pdo()->exec($stmt);
                }
            }
            foreach (nbx_default_settings($siteName) as $k => $v) {
                DB::insert('settings', ['key' => $k, 'value' => $v]);
            }
            $parts = preg_split('/\s+/u', $adminName, 2);
            DB::insert('users', [
                'first_name' => $parts[0] ?? 'مدیر',
                'last_name' => $parts[1] ?? '',
                'email' => $adminEmail,
                'username' => 'admin',
                'password' => password_hash($adminPass, PASSWORD_DEFAULT),
                'role' => 'admin',
            ]);
            if (!empty($_POST['demo_content'])) {
                nbx_seed_content();
            } else {
                DB::insert('posts', ['type' => 'page', 'title' => 'قوانین و مقررات', 'slug' => 'terms', 'content' => '<p>قوانین سایت را از پنل مدیریت ویرایش کنید.</p>']);
                DB::insert('posts', ['type' => 'page', 'title' => 'درباره ما', 'slug' => 'about', 'content' => '<p>متن درباره ما را از پنل مدیریت ویرایش کنید.</p>']);
            }
            if (!empty($_POST['demo_orders']) && !empty($_POST['demo_content'])) {
                nbx_seed_demo_orders();
            }

            $config = [
                'db' => $db,
                'app' => [
                    'url' => $siteUrl,
                    'key' => bin2hex(random_bytes(16)),
                    'debug' => false,
                    'timezone' => 'Asia/Tehran',
                    'pretty_urls' => ($_POST['pretty_urls'] ?? '1') === '1',
                ],
                'cron_key' => bin2hex(random_bytes(12)),
            ];
            $php = "<?php\n// Generated by the Numbix installer on " . date('Y-m-d H:i') . "\nreturn " . var_export($config, true) . ";\n";
            $written = @file_put_contents($configFile, $php) !== false;
            @file_put_contents($lock, date('c'));
            $done = ['config' => $written ? null : $php, 'cron_key' => $config['cron_key'], 'url' => $siteUrl, 'pretty' => $config['app']['pretty_urls']];
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(16));
$v = fn($k, $d = '') => htmlspecialchars((string)($_POST[$k] ?? $d), ENT_QUOTES);
?>
<!doctype html>
<html lang="fa" dir="rtl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>نصب نامبیکس</title>
<link rel="icon" href="<?= $assets ?>/img/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="<?= $assets ?>/css/app.css?v=<?= NBX_VERSION ?>">
<style>
  .inst { min-height: 100vh; padding: 48px 16px; position: relative; isolation: isolate; }
  .inst-box { max-width: 920px; margin: 0 auto; }
  .inst-head { text-align: center; margin-bottom: 30px; }
  .inst-head .logo-mark { width: 84px; height: 84px; margin: 0 auto 16px; filter: drop-shadow(0 0 24px rgba(139,92,246,.8)); animation: floaty 6s ease-in-out infinite; }
  .inst-head h1 { font-size: 32px; font-weight: 900; color: #fff; }
  .inst-head p { color: rgba(255,255,255,.65); }
  .req { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 8px; }
  .req div { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px; background: var(--card-2); font-size: 13.5px; }
  .req .ok { color: var(--success); } .req .no { color: var(--danger); }
  .sec { font-size: 15px; font-weight: 800; margin: 26px 0 14px; display: flex; gap: 10px; align-items: center; }
  .sec span { width: 28px; height: 28px; border-radius: 9px; background: var(--grad); color: #fff; display: grid; place-items: center; font-size: 13px; }
  pre.cfg { direction: ltr; text-align: left; background: #0B0C22; color: #C4B5FD; padding: 16px; border-radius: 14px; overflow: auto; font-size: 12.5px; }
</style>
</head>
<body class="no-bottom-nav">
<canvas id="cosmos" aria-hidden="true"></canvas>
<div class="inst dark-zone">
  <div class="inst-box">
    <div class="inst-head">
      <svg class="logo-mark" viewBox="0 0 48 48"><defs><radialGradient id="ip" cx="34%" cy="30%" r="75%"><stop offset="0" stop-color="#F5F3FF"/><stop offset=".28" stop-color="#A78BFA"/><stop offset=".62" stop-color="#7C3AED"/><stop offset="1" stop-color="#2E1065"/></radialGradient><linearGradient id="ir" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#67E8F9"/><stop offset=".5" stop-color="#F0ABFC"/><stop offset="1" stop-color="#A78BFA"/></linearGradient></defs><path d="M5.5 30.5C2.6 26 9.6 19.1 21.2 15c11.6-4 22.4-3.6 25.3.9" fill="none" stroke="url(#ir)" stroke-width="2.4" stroke-linecap="round" opacity=".55"/><circle cx="24" cy="24" r="15" fill="url(#ip)"/><path d="M18 30V18l12 12V18" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M46.5 15.9c2.9 4.5-4.1 11.4-15.7 15.5-11.6 4.1-22.4 3.7-25.3-.9" fill="none" stroke="url(#ir)" stroke-width="2.4" stroke-linecap="round"/></svg>
      <h1>نصب <span class="gtext">نامبیکس</span></h1>
      <p>نصب خودکار در کمتر از یک دقیقه — فقط اطلاعات دیتابیس را وارد کنید.</p>
    </div>

    <?php if ($installed && !$done): ?>
      <div class="card card-pad center">
        <span class="btile btile-xl" style="--c1:#34D399;--c2:#059669;margin:0 auto 16px"><?= icon('check') ?></span>
        <h2>نامبیکس قبلاً نصب شده است</h2>
        <p class="muted">برای امنیت بیشتر، پوشه <b class="ltr">install</b> را از هاست حذف کنید.</p>
        <a class="btn btn-primary" href="<?= $basePath ?>/">ورود به سایت</a>
      </div>
    <?php elseif ($done): ?>
      <div class="card card-pad">
        <div class="center">
          <span class="btile btile-xl" style="--c1:#34D399;--c2:#059669;margin:0 auto 16px"><?= icon('check') ?></span>
          <h2>نصب با موفقیت انجام شد! 🎉</h2>
          <p class="muted">سایت شما آماده است. موارد زیر را حتماً انجام دهید:</p>
        </div>
        <?php if ($done['config']): ?>
          <div class="alert alert-warning mb-2"><?= icon('alert') ?><div><b>فایل config.php ساخته نشد.</b> یک فایل با نام <b class="ltr">config.php</b> در پوشه اصلی سایت بسازید و محتوای زیر را در آن قرار دهید:</div></div>
          <pre class="cfg"><?= htmlspecialchars($done['config']) ?></pre>
        <?php endif; ?>
        <div class="kv mt-2">
          <div><span>۱. حذف پوشه نصب</span><b>پوشه <span class="ltr">/install</span> را از هاست پاک کنید</b></div>
          <div><span>۲. کرون‌جاب (هر ۵ دقیقه)</span><b class="ltr small">php <?= htmlspecialchars(BASE_PATH) ?>/cron.php</b></div>
          <div><span>یا آدرس وب‌کرون</span><b class="ltr small"><?= htmlspecialchars($done['url'] . ($done['pretty'] ? '/cron/' : '/index.php?r=cron/') . $done['cron_key']) ?></b></div>
          <div><span>۳. تنظیمات درگاه</span><b>مرچنت زرین‌پال را در «تنظیمات سایت» وارد کنید</b></div>
        </div>
        <div class="row mt-3" style="justify-content:center;flex-wrap:wrap">
          <a class="btn btn-primary btn-lg" href="<?= $basePath ?>/<?= $done['pretty'] ? 'login' : 'index.php?r=login' ?>"><?= icon('login') ?> ورود به پنل مدیریت</a>
          <a class="btn btn-outline btn-lg" href="<?= $basePath ?>/"><?= icon('home') ?> مشاهده سایت</a>
        </div>
      </div>
    <?php else: ?>
      <form class="card card-pad" method="post" autocomplete="off">
        <input type="hidden" name="_token" value="<?= $_SESSION['csrf'] ?>">
        <input type="hidden" name="pretty_urls" id="pretty" value="1">
        <?php if ($error): ?><div class="alert alert-danger mb-2"><?= icon('x-circle') ?><div><?= htmlspecialchars($error) ?></div></div><?php endif; ?>

        <div class="sec" style="margin-top:0"><span>۱</span> بررسی پیش‌نیازها</div>
        <div class="req">
          <?php foreach ($checks as [$label, $ok, $note]): ?>
            <div><?= $ok ? icon('check-circle', 'ok') : icon('x-circle', 'no') ?><span><?= $label ?><?= $note ? ' <small class="muted">(' . htmlspecialchars($note) . ')</small>' : '' ?></span></div>
          <?php endforeach; ?>
          <div id="rw"><?= icon('loader', 'muted') ?><span>آدرس‌های زیبا (mod_rewrite) <small class="muted">در حال بررسی…</small></span></div>
        </div>

        <div class="sec"><span>۲</span> اطلاعات دیتابیس MySQL</div>
        <div class="grid g-2" style="gap:14px">
          <div class="field mb-0"><label class="label">نام دیتابیس</label><input class="input ltr" name="db_name" value="<?= $v('db_name') ?>" required></div>
          <div class="field mb-0"><label class="label">نام کاربری دیتابیس</label><input class="input ltr" name="db_user" value="<?= $v('db_user') ?>" required></div>
          <div class="field mb-0"><label class="label">رمز عبور دیتابیس</label><input class="input ltr" type="password" name="db_pass" value="<?= $v('db_pass') ?>"></div>
          <div class="grid" style="grid-template-columns:2fr 1fr;gap:10px">
            <div class="field mb-0"><label class="label">هاست</label><input class="input ltr" name="db_host" value="<?= $v('db_host', 'localhost') ?>"></div>
            <div class="field mb-0"><label class="label">پورت</label><input class="input ltr" name="db_port" value="<?= $v('db_port', '3306') ?>"></div>
          </div>
        </div>

        <div class="sec"><span>۳</span> اطلاعات سایت و مدیر</div>
        <div class="grid g-2" style="gap:14px">
          <div class="field mb-0"><label class="label">نام سایت</label><input class="input" name="site_name" value="<?= $v('site_name', 'نامبیکس') ?>"></div>
          <div class="field mb-0"><label class="label">آدرس سایت</label><input class="input ltr" name="site_url" value="<?= $v('site_url', $guessUrl) ?>"></div>
          <div class="field mb-0"><label class="label">نام مدیر</label><input class="input" name="admin_name" value="<?= $v('admin_name', 'مدیر سایت') ?>"></div>
          <div class="field mb-0"><label class="label">ایمیل مدیر (برای ورود)</label><input class="input ltr" type="email" name="admin_email" value="<?= $v('admin_email') ?>" required></div>
          <div class="field mb-0"><label class="label">رمز عبور مدیر</label><input class="input ltr" type="password" name="admin_pass" minlength="8" required></div>
        </div>

        <div class="sec"><span>۴</span> داده‌های اولیه</div>
        <div class="stack">
          <label class="check"><input type="checkbox" name="demo_content" value="1" <?= !$_POST || !empty($_POST['demo_content']) ? 'checked' : '' ?>><span class="box"><?= icon('check') ?></span>نصب دسته‌بندی‌ها، ۲۲ سرویس، مقالات، سوالات متداول و بنر نمونه (پیشنهادی)</label>
          <label class="check"><input type="checkbox" name="demo_orders" value="1" <?= !empty($_POST['demo_orders']) ? 'checked' : '' ?>><span class="box"><?= icon('check') ?></span>ساخت کاربران و سفارش‌های آزمایشی برای دیدن نمودارهای داشبورد (بعداً از تنظیمات قابل حذف است)</label>
          <label class="check"><input type="checkbox" name="overwrite" value="1"><span class="box"><?= icon('check') ?></span><span>حذف داده‌های قبلی نامبیکس در این دیتابیس <small class="muted">(فقط برای نصب مجدد)</small></span></label>
        </div>

        <button class="btn btn-primary btn-lg btn-block mt-3" <?= $canInstall ? '' : 'disabled' ?>><?= icon('rocket') ?> شروع نصب</button>
        <?php if (!$canInstall): ?><p class="error-text center">پیش‌نیازهای لازم روی هاست فراهم نیست.</p><?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
</div>
<script>
(function () {
  var el = document.getElementById('rw'); if (!el) return;
  var ok = '<?= str_replace("'", "\\'", icon('check-circle', 'ok')) ?>', no = '<?= str_replace("'", "\\'", icon('alert', 'no')) ?>';
  fetch('<?= $basePath ?>/rewrite-check', { cache: 'no-store' }).then(function (r) { return r.text(); }).then(function (t) {
    var good = t.indexOf('NUMBIX-REWRITE-OK') > -1;
    document.getElementById('pretty').value = good ? '1' : '0';
    el.innerHTML = (good ? ok : no) + '<span>آدرس‌های زیبا (mod_rewrite) <small class="muted">' + (good ? 'فعال است' : 'غیرفعال — از آدرس‌های index.php?r= استفاده می‌شود') + '</small></span>';
  }).catch(function () { document.getElementById('pretty').value = '0'; el.innerHTML = no + '<span>آدرس‌های زیبا غیرفعال</span>'; });
})();
</script>
<script src="<?= $assets ?>/js/cosmos.js?v=<?= NBX_VERSION ?>"></script>
</body>
</html>

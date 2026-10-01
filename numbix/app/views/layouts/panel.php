<?php
$u = auth();
$isAdmin = ($area ?? 'user') === 'admin';
if ($isAdmin) {
    $counts = DB::row("SELECT
        (SELECT COUNT(*) FROM orders WHERE status IN ('pending')) AS orders,
        (SELECT COUNT(*) FROM tickets WHERE status IN ('open','customer_reply')) AS tickets,
        (SELECT COUNT(*) FROM payments WHERE status = 'review') AS payments");
    $menu = [
        ['label' => 'اصلی'],
        ['admin', 'داشبورد', 'home', true],
        ['admin/orders', 'سفارشات کاربران', 'cart', false, $counts['orders']],
        ['admin/services', 'مدیریت محصولات', 'box'],
        ['admin/categories', 'خدمات و دسته‌بندی‌ها', 'grid'],
        ['admin/stock', 'افزایش موجودی', 'database'],
        ['admin/providers', 'اتصال API خودکار', 'server'],
        ['label' => 'کاربران و مالی'],
        ['admin/users', 'مدیریت کاربران', 'users'],
        ['admin/tickets', 'تیکت‌ها', 'message', false, $counts['tickets']],
        ['admin/payments', 'پرداخت‌ها', 'wallet', false, $counts['payments']],
        ['admin/coupons', 'کدهای تخفیف', 'tag'],
        ['label' => 'سایت'],
        ['admin/banners', 'بنرها و تبلیغات', 'megaphone'],
        ['admin/content', 'مدیریت محتوا', 'file'],
        ['admin/reports', 'گزارشات', 'chart'],
        ['admin/settings', 'تنظیمات سایت', 'settings'],
    ];
    $notifs = DB::all('SELECT * FROM notifications WHERE for_admin = 1 ORDER BY id DESC LIMIT 8');
    $unread = (int)DB::value('SELECT COUNT(*) FROM notifications WHERE for_admin = 1 AND is_read = 0');
    $readUrl = url('admin/notifications/read');
} else {
    $menu = [
        ['label' => 'حساب من'],
        ['dashboard', 'داشبورد', 'home', true],
        ['dashboard/new-order', 'ثبت سفارش جدید', 'plus'],
        ['dashboard/orders', 'سفارش‌های من', 'bag'],
        ['dashboard/wallet', 'کیف پول و پرداخت', 'wallet'],
        ['dashboard/tickets', 'تیکت‌های پشتیبانی', 'message'],
        ['label' => 'بیشتر'],
        ['dashboard/favorites', 'علاقه‌مندی‌ها', 'heart'],
        ['dashboard/notifications', 'اعلان‌ها', 'bell'],
        ['dashboard/api', 'وب‌سرویس API', 'code'],
        ['dashboard/profile', 'پروفایل و امنیت', 'user'],
    ];
    $notifs = DB::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 8', [$u['id']]);
    $unread = (int)DB::value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$u['id']]);
    $readUrl = url('dashboard/notifications/read');
}
$cmdItems = [];
foreach ($menu as $m) {
    if (isset($m[0])) {
        $cmdItems[] = ['title' => $m[1], 'url' => url($m[0]), 'icon' => icon($m[2])];
    }
}
$cmdItems[] = ['title' => 'مشاهده سایت', 'url' => url('/'), 'icon' => icon('globe')];
$cmdItems[] = ['title' => 'همه خدمات', 'url' => url('services'), 'icon' => icon('grid')];
$panelCss = true;
echo partial('head', get_defined_vars() + ['noindex' => true]);
?>
<body class="no-bottom-nav">
<?php if (!empty($_SESSION['impersonator'])): ?>
  <div class="imp-bar"><?= icon('eye') ?> شما در حال مشاهده حساب «<?= e(user_name($u)) ?>» هستید.
    <form action="<?= url('impersonate/stop') ?>" method="post"><?= csrf_field() ?><button>بازگشت به حساب مدیر</button></form></div>
<?php endif; ?>
<div class="panel">
  <aside class="sidebar">
    <div class="sb-top">
      <?= partial('logo') ?>
      <button class="sb-collapse" data-sb-collapse aria-label="جمع کردن منو"><?= icon('chevron-right') ?></button>
    </div>
    <nav class="sb-nav">
      <?php foreach ($menu as $m): ?>
        <?php if (isset($m['label'])): ?>
          <div class="sb-label"><?= e($m['label']) ?></div>
        <?php else: ?>
          <a href="<?= url($m[0]) ?>" class="sb-link<?= is_active('/' . $m[0], $m[3] ?? false) ? ' active' : '' ?>" title="<?= e($m[1]) ?>">
            <?= icon($m[2]) ?><span><?= e($m[1]) ?></span>
            <?php if (!empty($m[4])): ?><em class="sb-count"><?= fa($m[4]) ?></em><?php endif; ?>
          </a>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sb-foot">
      <?php if (!$isAdmin): ?>
        <div class="sb-card">
          <small>موجودی کیف پول</small>
          <b><?= num($u['balance']) ?></b> <span class="unit" style="color:rgba(255,255,255,.6)"><?= e(setting('currency', 'تومان')) ?></span>
          <a href="<?= url('dashboard/wallet') ?>" class="btn btn-glass btn-sm btn-block"><?= icon('plus') ?> افزایش موجودی</a>
        </div>
      <?php endif; ?>
      <a href="<?= url('/') ?>" class="sb-link" title="مشاهده سایت"><?= icon('globe') ?><span>مشاهده سایت</span></a>
      <form action="<?= url('logout') ?>" method="post"><?= csrf_field() ?>
        <button class="sb-link danger" style="width:100%;border:0;background:none;font:inherit;cursor:pointer" title="خروج"><?= icon('power') ?><span>خروج</span></button>
      </form>
    </div>
  </aside>
  <div class="sb-overlay"></div>

  <div class="main">
    <header class="topbar">
      <button class="hbtn tb-menu" data-sb-toggle aria-label="منو"><?= icon('menu') ?></button>
      <div class="tb-search">
        <button type="button" data-cmdk><?= icon('search') ?><span><?= $isAdmin ? 'جستجو در سفارش‌ها، کاربران، محصولات…' : 'جستجوی سریع در پنل و خدمات…' ?></span><kbd>Ctrl + K</kbd></button>
      </div>
      <div class="tb-actions">
        <button class="hbtn theme-toggle" data-theme-toggle aria-label="تغییر تم"><?= icon('moon', 'i-moon') ?><?= icon('sun', 'i-sun') ?></button>
        <div class="dropdown">
          <button class="hbtn" data-dropdown aria-label="اعلان‌ها"><?= icon('bell') ?><span class="count" data-notif-count data-n="<?= $unread ?>"><?= $unread ? fa($unread) : '' ?></span></button>
          <div class="dropdown-menu notif-menu">
            <div class="nm-head"><b>اعلان‌ها</b><?php if ($unread): ?><a href="#" class="btn btn-ghost btn-xs" data-notif-read="<?= e($readUrl) ?>" style="width:auto;padding:0 8px">خواندن همه</a><?php endif; ?></div>
            <div class="nm-list">
              <?php foreach ($notifs as $n): ?>
                <a href="<?= $n['link'] ? url($n['link']) : '#' ?>" class="notif-item<?= $n['is_read'] ? '' : ' unread' ?>">
                  <span class="ni-ic"><?= icon($n['icon'] ?: 'bell') ?></span>
                  <span><b><?= e($n['title']) ?></b><?php if ($n['body']): ?><small><?= e($n['body']) ?></small><?php endif; ?><small><?= time_ago($n['created_at']) ?></small></span>
                </a>
              <?php endforeach; ?>
              <?php if (!$notifs): ?><div class="empty" style="padding:30px">اعلانی ندارید</div><?php endif; ?>
            </div>
            <?php if (!$isAdmin): ?><a href="<?= url('dashboard/notifications') ?>" style="justify-content:center;border-top:1px solid var(--border);border-radius:0">مشاهده همه</a><?php endif; ?>
          </div>
        </div>
        <div class="dropdown">
          <button class="tb-user" data-dropdown>
            <?= avatar($u) ?>
            <div><b><?= e(user_name($u)) ?></b><small><?= $u['role'] === 'admin' ? 'مدیر سیستم' : 'کاربر' ?></small></div>
            <?= icon('chevron-down') ?>
          </button>
          <div class="dropdown-menu">
            <div class="dd-head"><?= avatar($u) ?><div><b><?= e(user_name($u)) ?></b><small><?= e($u['email'] ?: $u['mobile'] ?: $u['username']) ?></small></div></div>
            <?php if ($u['role'] === 'admin'): ?>
              <a href="<?= url($isAdmin ? 'dashboard' : 'admin') ?>"><?= icon($isAdmin ? 'user' : 'crown') ?> <?= $isAdmin ? 'پنل کاربری من' : 'پنل مدیریت' ?></a>
            <?php endif; ?>
            <a href="<?= url('dashboard/profile') ?>"><?= icon('settings') ?> پروفایل و امنیت</a>
            <a href="<?= url('dashboard/wallet') ?>"><?= icon('wallet') ?> کیف پول: <?= money($u['balance']) ?></a>
            <div class="dd-sep"></div>
            <form action="<?= url('logout') ?>" method="post"><?= csrf_field() ?><button class="dd-item danger"><?= icon('logout') ?> خروج از حساب</button></form>
          </div>
        </div>
      </div>
    </header>
    <main class="content"><?= $content ?></main>
  </div>
</div>

<div class="cmdk" id="cmdk" data-items="<?= e(json_encode($cmdItems, JSON_UNESCAPED_UNICODE)) ?>" data-endpoint="<?= e($isAdmin ? url('admin/search') : url('search')) ?>">
  <div class="cmdk-bg"></div>
  <div class="cmdk-box">
    <div class="cmdk-input"><?= icon('search') ?><input placeholder="<?= $isAdmin ? 'شماره سفارش، نام کاربر، ایمیل یا نام محصول…' : 'جستجو…' ?>" autocomplete="off"></div>
    <div class="cmdk-list"></div>
    <div class="cmdk-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> جابجایی</span><span><kbd>Enter</kbd> انتخاب</span><span><kbd>Esc</kbd> بستن</span></div>
  </div>
</div>
<?= partial('scripts', get_defined_vars()) ?>
</body>
</html>

<?php
$u = auth();
$dark = !empty($darkHeader);
$cartCount = Cart::count();
$nav = [
    ['', 'صفحه اصلی', true],
    ['services', 'خدمات', false],
    ['blog', 'وبلاگ', false],
    ['faq', 'سوالات متداول', false],
    ['contact', 'پشتیبانی', false],
];
echo partial('head', get_defined_vars());
?>
<body class="site<?= !empty($bodyClass) ? ' ' . e($bodyClass) : '' ?>">
<?php if ($ann = setting('announcement')): ?>
  <div class="announce"><?= e($ann) ?><?php if ($al = setting('announcement_link')): ?> — <a href="<?= e($al) ?>">مشاهده</a><?php endif; ?></div>
<?php endif; ?>

<header class="site-header<?= $dark ? ' on-dark' : ' solid' ?>">
  <div class="container">
    <button class="hbtn menu-toggle" data-open="#drawer" aria-label="منو"><?= icon('menu') ?></button>
    <?= partial('logo') ?>
    <nav class="nav" aria-label="منوی اصلی">
      <?php foreach ($nav as [$path, $label, $exact]): ?>
        <a href="<?= url($path) ?>" class="<?= is_active('/' . $path, $exact) ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php if (!$dark): ?>
      <div class="header-search" data-search-wrap>
        <form action="<?= url('services') ?>" method="get" class="input-wrap">
          <?= icon('search') ?>
          <input class="input" type="search" name="q" placeholder="جستجوی خدمات، مثلاً ممبر تلگرام…" data-search autocomplete="off">
        </form>
        <div class="search-suggest"></div>
      </div>
    <?php endif; ?>
    <div class="header-actions">
      <button class="hbtn theme-toggle" data-theme-toggle aria-label="تغییر تم"><?= icon('moon', 'i-moon') ?><?= icon('sun', 'i-sun') ?></button>
      <a href="<?= url('cart') ?>" class="hbtn" aria-label="سبد خرید"><?= icon('cart') ?><span class="count" data-cart-count data-n="<?= $cartCount ?>"><?= $cartCount ? fa($cartCount) : '' ?></span></a>
      <?php if ($u): ?>
        <div class="dropdown">
          <button class="hbtn" data-dropdown aria-label="حساب کاربری" style="width:auto;padding:0 4px 0 4px"><?= avatar($u, 'sm') ?></button>
          <div class="dropdown-menu">
            <div class="dd-head"><?= avatar($u) ?><div><b><?= e(user_name($u)) ?></b><small>موجودی: <?= money($u['balance']) ?></small></div></div>
            <?php if ($u['role'] === 'admin'): ?><a href="<?= url('admin') ?>"><?= icon('crown') ?> پنل مدیریت</a><?php endif; ?>
            <a href="<?= url('dashboard') ?>"><?= icon('grid') ?> داشبورد</a>
            <a href="<?= url('dashboard/orders') ?>"><?= icon('bag') ?> سفارش‌های من</a>
            <a href="<?= url('dashboard/wallet') ?>"><?= icon('wallet') ?> کیف پول</a>
            <a href="<?= url('dashboard/tickets') ?>"><?= icon('message') ?> تیکت‌ها</a>
            <div class="dd-sep"></div>
            <form action="<?= url('logout') ?>" method="post"><?= csrf_field() ?><button class="dd-item danger"><?= icon('logout') ?> خروج</button></form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= url('login') ?>" class="btn btn-primary header-login"><?= icon('user') ?><span>ورود / ثبت‌نام</span></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main id="main"><?= $content ?></main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-top">
      <div>
        <?= partial('logo') ?>
        <p><?= e(setting('footer_about', 'نامبیکس؛ مرجع تخصصی خدمات شبکه‌های اجتماعی. افزایش ممبر، فالوور، لایک و بازدید با کیفیت بالا، تحویل خودکار و پشتیبانی واقعی.')) ?></p>
        <div class="socials">
          <?php foreach (['telegram' => 'social_telegram', 'instagram' => 'social_instagram', 'youtube' => 'social_youtube', 'x' => 'social_x', 'whatsapp' => 'social_whatsapp'] as $b => $key): ?>
            <?php if ($link = setting($key)): ?><a href="<?= e($link) ?>" target="_blank" rel="noopener" aria-label="<?= e($b) ?>"><?= brand($b) ?></a><?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <h4>دسترسی سریع</h4>
        <ul class="footer-links">
          <li><a href="<?= url('services') ?>"><?= icon('chevron-left') ?>همه خدمات</a></li>
          <li><a href="<?= url('blog') ?>"><?= icon('chevron-left') ?>وبلاگ</a></li>
          <li><a href="<?= url('faq') ?>"><?= icon('chevron-left') ?>سوالات متداول</a></li>
          <li><a href="<?= url('page/about') ?>"><?= icon('chevron-left') ?>درباره ما</a></li>
          <li><a href="<?= url('page/terms') ?>"><?= icon('chevron-left') ?>قوانین و مقررات</a></li>
        </ul>
      </div>
      <div>
        <h4>خدمات محبوب</h4>
        <ul class="footer-links">
          <?php foreach (DB::all('SELECT name, slug FROM categories WHERE is_active = 1 ORDER BY sort, id LIMIT 5') as $fc): ?>
            <li><a href="<?= url('services/' . $fc['slug']) ?>"><?= icon('chevron-left') ?>خدمات <?= e($fc['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h4>ارتباط با ما</h4>
        <div class="footer-contact">
          <?php if ($v = setting('support_phone')): ?><div><?= icon('phone') ?><span class="ltr"><?= e(fa($v)) ?></span></div><?php endif; ?>
          <?php if ($v = setting('support_email')): ?><div><?= icon('mail') ?><span><?= e($v) ?></span></div><?php endif; ?>
          <?php if ($v = setting('support_hours')): ?><div><?= icon('clock') ?><span><?= e($v) ?></span></div><?php endif; ?>
          <?php if ($v = setting('address')): ?><div><?= icon('map-pin') ?><span><?= e($v) ?></span></div><?php endif; ?>
        </div>
        <?php if ($trust = setting('trust_badges')): ?><div class="trust mt-2"><?= $trust ?></div><?php endif; ?>
      </div>
    </div>
    <div class="footer-bottom">
      <span><?= e(setting('copyright', '© ' . jdate('Y') . ' — تمامی حقوق برای ' . site_name() . ' محفوظ است.')) ?></span>
      <span class="row" style="gap:6px">ساخته شده با <?= icon('heart', 'star-fill') ?> برای رشد شما</span>
    </div>
  </div>
</footer>

<nav class="bottom-nav" aria-label="منوی موبایل">
  <a href="<?= url('/') ?>" class="<?= is_active('/', true) ? 'active' : '' ?>"><?= icon('home') ?>خانه</a>
  <a href="<?= url('services') ?>" class="<?= is_active('/services') ? 'active' : '' ?>"><?= icon('grid') ?>خدمات</a>
  <a href="<?= url($u ? 'dashboard/new-order' : 'services') ?>" class="fab"><span class="fab-ic"><?= icon('plus') ?></span></a>
  <a href="<?= url('cart') ?>" class="<?= is_active('/cart') ? 'active' : '' ?>"><span class="count" data-cart-count data-n="<?= $cartCount ?>"><?= fa($cartCount) ?></span><?= icon('cart') ?>سبد</a>
  <a href="<?= url($u ? 'dashboard' : 'login') ?>" class="<?= is_active('/dashboard') ? 'active' : '' ?>"><?= icon('user') ?><?= $u ? 'حساب' : 'ورود' ?></a>
</nav>

<div class="drawer" id="drawer">
  <div class="drawer-backdrop"></div>
  <div class="drawer-panel">
    <div class="row-between mb-2"><?= partial('logo') ?><button class="btn btn-ghost btn-icon btn-sm" data-close><?= icon('x') ?></button></div>
    <form action="<?= url('services') ?>" class="input-wrap mb-2"><?= icon('search') ?><input class="input" name="q" placeholder="جستجوی خدمات…"></form>
    <?php foreach ($nav as [$path, $label, $exact]): ?>
      <a href="<?= url($path) ?>" class="d-link<?= is_active('/' . $path, $exact) ? ' active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= url('page/about') ?>" class="d-link">درباره ما</a>
    <div class="dd-sep" style="height:1px;background:var(--border);margin:10px 0"></div>
    <?php if ($u): ?>
      <a href="<?= url('dashboard') ?>" class="btn btn-primary btn-block"><?= icon('grid') ?> داشبورد من</a>
    <?php else: ?>
      <a href="<?= url('login') ?>" class="btn btn-primary btn-block"><?= icon('login') ?> ورود / ثبت‌نام</a>
    <?php endif; ?>
  </div>
</div>

<?= partial('quick_add') ?>
<?= partial('scripts', get_defined_vars()) ?>
</body>
</html>

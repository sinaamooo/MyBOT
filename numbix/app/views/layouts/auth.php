<?php
$isRegister = ($side ?? 'login') === 'register';
$feats = $isRegister ? [
    ['shield', 'امن و مطمئن', 'حفظ امنیت اطلاعات شما', '#34D399', '#059669'],
    ['zap', 'دسترسی فوری', 'فعال‌سازی سریع حساب', '#FBBF24', '#EA580C'],
    ['headset', 'پشتیبانی ۲۴ ساعته', 'همیشه در کنار شما', '#38BDF8', '#2563EB'],
    ['gift', 'تخفیف‌های ویژه', 'پیشنهادهای اختصاصی', '#F472B6', '#C026D3'],
] : [
    ['zap', 'تحویل فوری', 'شروع خودکار سفارش‌ها', '#A78BFA', '#6C4CF1'],
    ['shield', 'پرداخت امن', 'از طریق درگاه‌های معتبر', '#38BDF8', '#2563EB'],
    ['headset', 'پشتیبانی ۲۴ ساعته', 'همیشه در کنار شما', '#34D399', '#059669'],
    ['star', 'کیفیت تضمینی', 'رضایت شما اولویت ماست', '#FBBF24', '#EA580C'],
];
echo partial('head', get_defined_vars() + ['noindex' => true]);
?>
<body class="no-bottom-nav">
<div class="auth-page">
  <div class="auth-shell">
    <section class="auth-form">
      <div class="auth-top">
        <?= partial('logo') ?>
        <div class="row" style="gap:8px">
          <button class="hbtn theme-toggle" data-theme-toggle aria-label="تغییر تم"><?= icon('moon', 'i-moon') ?><?= icon('sun', 'i-sun') ?></button>
          <a href="<?= url($backUrl ?? '/') ?>" class="btn btn-soft btn-sm"><?= e($backLabel ?? 'بازگشت به سایت') ?> <?= icon('arrow-left') ?></a>
        </div>
      </div>
      <div class="inner"><?= $content ?></div>
    </section>
    <aside class="auth-side">
      <div class="aurora"><div class="blob b1"></div><div class="blob b2"></div><div class="grid-bg"></div><div class="noise"></div></div>
      <h2><?= $isRegister ? 'با <span class="gtext">' . e(site_name()) . '</span> شروع کنید' : 'به <span class="gtext">' . e(site_name()) . '</span> بپیوندید' ?></h2>
      <p class="lead"><?= $isRegister ? 'ثبت‌نام سریع و آسان؛ فقط چند ثانیه زمان می‌برد!' : 'دسترسی به دنیایی از خدمات دیجیتال، باکیفیت، سریع و مطمئن' ?></p>
      <div class="auth-feats">
        <?php foreach ($feats as [$ic, $t, $s, $c1, $c2]): ?>
          <div class="auth-feat"><span class="ic" style="--c:<?= $c1 ?>;--c2:<?= $c2 ?>"><?= icon($ic) ?></span><div><b><?= $t ?></b><small><?= $s ?></small></div></div>
        <?php endforeach; ?>
      </div>
      <div class="auth-art" aria-hidden="true">
        <div class="pedestal"></div>
        <svg class="shield" viewBox="0 0 200 230">
          <defs>
            <linearGradient id="sh1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C4B5FD"/><stop offset=".5" stop-color="#7C5CFF"/><stop offset="1" stop-color="#3730A3"/></linearGradient>
            <linearGradient id="sh2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".55"/><stop offset=".6" stop-color="#fff" stop-opacity="0"/></linearGradient>
            <linearGradient id="sh3" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#67E8F9"/><stop offset="1" stop-color="#2563EB"/></linearGradient>
          </defs>
          <path d="M100 8 182 40v62c0 58-36 100-82 120C54 202 18 160 18 102V40z" fill="url(#sh1)"/>
          <path d="M100 8 182 40v62c0 58-36 100-82 120C54 202 18 160 18 102V40z" fill="url(#sh2)"/>
          <path d="M100 30 162 54v48c0 46-27 80-62 96-35-16-62-50-62-96V54z" fill="none" stroke="rgba(255,255,255,.35)" stroke-width="2"/>
          <?php if ($isRegister): ?>
            <circle cx="100" cy="92" r="24" fill="#fff" opacity=".95"/><path d="M60 158a40 40 0 0 1 80 0" fill="#fff" opacity=".95"/>
            <circle cx="146" cy="150" r="22" fill="url(#sh3)" stroke="#fff" stroke-width="4"/><path d="M146 140v20M136 150h20" stroke="#fff" stroke-width="5" stroke-linecap="round"/>
          <?php else: ?>
            <rect x="66" y="98" width="68" height="58" rx="14" fill="#fff" opacity=".95"/>
            <path d="M80 98V84a20 20 0 0 1 40 0v14" fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round" opacity=".95"/>
            <circle cx="100" cy="122" r="8" fill="#6C4CF1"/><rect x="96" y="124" width="8" height="18" rx="4" fill="#6C4CF1"/>
          <?php endif; ?>
        </svg>
        <div class="float-tile t1"><?= brand_tile(['icon' => 'telegram', 'color' => '#38BDF8', 'color2' => '#0284C7'], 'lg') ?></div>
        <div class="float-tile t2"><span class="btile btile-md" style="--c1:#34D399;--c2:#059669"><?= icon('check') ?></span></div>
        <div class="float-tile t4"><span class="btile btile-md" style="--c1:#A78BFA;--c2:#7C3AED"><?= icon($isRegister ? 'user-plus' : 'user') ?></span></div>
        <div class="float-tile t5"><?= brand_tile(['icon' => 'instagram', 'color' => '#F472B6', 'color2' => '#C026D3'], 'md') ?></div>
      </div>
    </aside>
  </div>
</div>
<?= partial('scripts', get_defined_vars()) ?>
</body>
</html>

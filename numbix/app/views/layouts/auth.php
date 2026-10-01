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
      <h2><?= $isRegister ? 'با <span class="gtext">' . e(site_name()) . '</span> شروع کنید' : 'به <span class="gtext">' . e(site_name()) . '</span> بپیوندید' ?></h2>
      <p class="lead"><?= $isRegister ? 'ثبت‌نام سریع و آسان؛ فقط چند ثانیه زمان می‌برد!' : 'دسترسی به دنیایی از خدمات دیجیتال، باکیفیت، سریع و مطمئن' ?></p>
      <div class="auth-feats">
        <?php foreach ($feats as [$ic, $t, $s, $c1, $c2]): ?>
          <div class="auth-feat"><span class="ic" style="--c:<?= $c1 ?>;--c2:<?= $c2 ?>"><?= icon($ic) ?></span><div><b><?= $t ?></b><small><?= $s ?></small></div></div>
        <?php endforeach; ?>
      </div>
      <div class="auth-art" aria-hidden="true">
        <div class="galaxy"><i></i><i></i><i></i></div>
        <div class="stage" data-orbit-system data-cy=".5">
          <svg class="orbit-svg">
            <defs><linearGradient id="orbitGrad" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#67E8F9"/><stop offset=".5" stop-color="#C084FC"/><stop offset="1" stop-color="#F472B6"/></linearGradient></defs>
            <ellipse data-ring data-a=".55" data-b=".34" data-tilt="-12"/>
            <ellipse data-ring data-a=".85" data-b=".52" data-tilt="6"/>
          </svg>
          <div class="planet-wrap"><div class="planet-ring back"></div><div class="planet"></div><div class="planet-atmo"></div><div class="planet-ring front"></div></div>
          <span class="moon"></span>
          <?php foreach ([
              ['telegram', '#37BBFE', '#0369A1', .55, .34, .35, 30, -12],
              ['instagram', '#F77737', '#C13584', .55, .34, .35, 210, -12],
              ['youtube', '#FF4E45', '#B91C1C', .85, .52, -.22, 100, 6],
              ['tiktok', '#3A3A4A', '#05050A', .85, .52, -.22, 280, 6],
          ] as [$b, $c1, $c2, $a, $bb, $sp, $ph, $tl]): ?>
            <div class="sat sm" data-a="<?= $a ?>" data-b="<?= $bb ?>" data-speed="<?= $sp ?>" data-phase="<?= $ph ?>" data-tilt="<?= $tl ?>"><?= brand_tile(['icon' => $b, 'color' => $c1, 'color2' => $c2], 'md') ?></div>
          <?php endforeach; ?>
        </div>
      </div>
    </aside>
  </div>
</div>
<?= partial('scripts', get_defined_vars()) ?>
</body>
</html>

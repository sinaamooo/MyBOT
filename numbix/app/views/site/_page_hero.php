<section class="dark-zone page-hero">
  <div class="aurora"><div class="blob b1"></div><div class="blob b3"></div><div class="grid-bg"></div><div class="noise"></div></div>
  <div class="container">
    <nav class="crumbs"><a href="<?= url('/') ?>">خانه</a><?= icon('chevron-left') ?><span><?= e($heroTitle) ?></span></nav>
    <h1><?= e($heroTitle) ?></h1>
    <?php if (!empty($heroText)): ?><p><?= e($heroText) ?></p><?php endif; ?>
  </div>
</section>

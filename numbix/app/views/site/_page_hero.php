<section class="dark-zone page-hero">
  <span class="mini-planet" data-depth="1" aria-hidden="true"></span><span class="mini-planet b" data-depth="1.6" aria-hidden="true"></span>
  <div class="container">
    <nav class="crumbs"><a href="<?= url('/') ?>">خانه</a><?= icon('chevron-left') ?><span><?= e($heroTitle) ?></span></nav>
    <h1><?= e($heroTitle) ?></h1>
    <?php if (!empty($heroText)): ?><p><?= e($heroText) ?></p><?php endif; ?>
  </div>
</section>

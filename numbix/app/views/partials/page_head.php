<div class="page-head">
  <div class="ph-title">
    <span class="ph-ic"><?= icon($icon ?? 'grid') ?></span>
    <div><h1><?= e($h) ?></h1><?php if (!empty($sub)): ?><p><?= e($sub) ?></p><?php endif; ?></div>
  </div>
  <?php if (!empty($actions)): ?><div class="ph-actions"><?= $actions ?></div><?php endif; ?>
</div>

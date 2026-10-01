<div class="empty">
  <div class="empty-ic"><?= icon($icon ?? 'inbox') ?></div>
  <h4><?= e($title ?? 'موردی یافت نشد') ?></h4>
  <?php if (!empty($text)): ?><p class="mb-0"><?= e($text) ?></p><?php endif; ?>
  <?php if (!empty($action)): ?><div class="mt-2"><?= $action ?></div><?php endif; ?>
</div>

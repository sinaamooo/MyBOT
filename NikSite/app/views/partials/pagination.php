<?php
/** @var array $p paginate() result */
if (($p['pages'] ?? 1) <= 1) {
    return;
}
$cur = $p['page'];
$last = $p['pages'];
$window = array_unique(array_filter([1, $cur - 2, $cur - 1, $cur, $cur + 1, $cur + 2, $last], fn($x) => $x >= 1 && $x <= $last));
sort($window);
?>
<nav class="pagination" aria-label="صفحه‌بندی">
  <?php if ($cur > 1): ?><a href="<?= e(page_url($cur - 1)) ?>" aria-label="قبلی"><?= icon('chevron-right') ?></a><?php endif; ?>
  <?php $prev = 0; foreach ($window as $pg): ?>
    <?php if ($pg - $prev > 1): ?><span class="dots">…</span><?php endif; ?>
    <?php if ($pg === $cur): ?><span class="active"><?= fa($pg) ?></span><?php else: ?><a href="<?= e(page_url($pg)) ?>"><?= fa($pg) ?></a><?php endif; ?>
  <?php $prev = $pg; endforeach; ?>
  <?php if ($cur < $last): ?><a href="<?= e(page_url($cur + 1)) ?>" aria-label="بعدی"><?= icon('chevron-left') ?></a><?php endif; ?>
</nav>

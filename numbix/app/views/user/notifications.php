<?= partial('page_head', ['icon' => 'bell', 'h' => 'اعلان‌ها']) ?>
<div class="card" style="max-width:860px">
  <?php if ($page['items']): ?>
    <?php foreach ($page['items'] as $n): ?>
      <a href="<?= $n['link'] ? url($n['link']) : '#' ?>" class="list-item" style="<?= $n['is_read'] ? '' : 'background:color-mix(in srgb,var(--p-soft) 50%,transparent)' ?>">
        <span class="btile btile-sm" style="--c1:#A78BFA;--c2:#6C4CF1"><?= icon($n['icon'] ?: 'bell') ?></span>
        <div><b><?= e($n['title']) ?></b><?php if ($n['body']): ?><small><?= e($n['body']) ?></small><?php endif; ?></div>
        <small class="li-end"><?= time_ago($n['created_at']) ?></small>
      </a>
    <?php endforeach; ?>
    <div class="table-foot"><span></span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?>
    <?= partial('empty', ['icon' => 'bell', 'title' => 'اعلانی ندارید']) ?>
  <?php endif; ?>
</div>

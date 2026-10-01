<a href="<?= url('blog/' . $p['slug']) ?>" class="post-card reveal">
  <div class="post-cover"><?php if ($p['cover']): ?><img src="<?= e(upload_url($p['cover'])) ?>" alt="" loading="lazy"><?php else: ?><?= icon('file') ?><?php endif; ?></div>
  <div class="post-body">
    <h3><?= e($p['title']) ?></h3>
    <?php if ($p['excerpt']): ?><p><?= e(str_limit($p['excerpt'], 120)) ?></p><?php endif; ?>
    <div class="post-meta"><span><?= icon('calendar') ?><?= jdate('j F Y', $p['created_at']) ?></span><span><?= icon('eye') ?><?= num($p['views']) ?></span></div>
  </div>
</a>

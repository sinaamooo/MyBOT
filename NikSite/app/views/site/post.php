<?= render('site/_page_hero', ['heroTitle' => $p['title'], 'heroText' => $p['excerpt']]) ?>
<div class="container container-sm section">
  <article class="card card-pad">
    <div class="row mb-2" style="gap:16px;color:var(--muted);font-size:13px;flex-wrap:wrap">
      <span class="row" style="gap:6px"><?= icon('calendar') ?><?= jdate('l j F Y', $p['created_at']) ?></span>
      <span class="row" style="gap:6px"><?= icon('eye') ?><?= num($p['views']) ?> بازدید</span>
      <?php if ($p['first_name']): ?><span class="row" style="gap:6px"><?= icon('user') ?><?= e($p['first_name'] . ' ' . $p['last_name']) ?></span><?php endif; ?>
    </div>
    <?php if ($p['cover']): ?><img src="<?= e(upload_url($p['cover'])) ?>" alt="" style="border-radius:18px;width:100%;margin-bottom:20px"><?php endif; ?>
    <div class="prose"><?= $p['content'] ?></div>
  </article>
  <?php if ($more): ?>
    <h3 class="mt-4 mb-2">مطالب بیشتر</h3>
    <div class="grid g-3"><?php foreach ($more as $m): ?><?= render('site/_post_card', ['p' => $m]) ?><?php endforeach; ?></div>
  <?php endif; ?>
</div>

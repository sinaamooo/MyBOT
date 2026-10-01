<?= render('site/_page_hero', ['heroTitle' => 'وبلاگ', 'heroText' => 'آموزش‌ها، ترفندها و راهکارهای رشد در شبکه‌های اجتماعی']) ?>
<div class="container section">
  <?php if ($page['items']): ?>
    <div class="grid g-3"><?php foreach ($page['items'] as $p): ?><?= render('site/_post_card', ['p' => $p]) ?><?php endforeach; ?></div>
    <div class="mt-4 row" style="justify-content:center"><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?>
    <div class="card"><?= partial('empty', ['icon' => 'file', 'title' => 'هنوز مطلبی منتشر نشده است']) ?></div>
  <?php endif; ?>
</div>

<?= render('site/_page_hero', ['heroTitle' => $p['title'], 'heroText' => $p['excerpt']]) ?>
<div class="container container-sm section">
  <article class="card card-pad"><div class="prose"><?= $p['content'] ?></div></article>
</div>

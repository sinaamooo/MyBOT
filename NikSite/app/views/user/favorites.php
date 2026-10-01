<?= partial('page_head', ['icon' => 'heart', 'h' => 'علاقه‌مندی‌ها', 'sub' => 'سرویس‌هایی که نشان کرده‌اید']) ?>
<?php if ($items): ?>
  <div class="svc-grid"><?php foreach ($items as $s): ?><?= partial('service_card', ['s' => $s, 'favs' => $favs]) ?><?php endforeach; ?></div>
  <?= partial('quick_add') ?>
<?php else: ?>
  <div class="card"><?= partial('empty', ['icon' => 'heart', 'title' => 'لیست علاقه‌مندی‌ها خالی است', 'text' => 'روی آیکون قلب سرویس‌ها بزنید تا این‌جا ذخیره شوند.', 'action' => '<a href="' . url('services') . '" class="btn btn-primary">مشاهده خدمات</a>']) ?></div>
<?php endif; ?>

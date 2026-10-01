<?= render('site/_page_hero', ['heroTitle' => 'سوالات متداول', 'heroText' => 'پاسخ سوالات رایج درباره ثبت سفارش، پرداخت و پشتیبانی']) ?>
<div class="container container-sm section">
  <?php if ($faqs): ?>
    <div class="faq">
      <?php foreach ($faqs as $i => $f): ?>
        <details class="reveal" style="--d:<?= min($i, 8) * .04 ?>s"<?= $i === 0 ? ' open' : '' ?>><summary><?= e($f['question']) ?><?= icon('chevron-down') ?></summary><div class="ans"><?= nl2br(e($f['answer'])) ?></div></details>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="card"><?= partial('empty', ['icon' => 'help', 'title' => 'سوالی ثبت نشده است']) ?></div>
  <?php endif; ?>
  <div class="cta mt-4"><div class="row-between"><div><h2 style="font-size:24px">جواب سوالت رو پیدا نکردی؟</h2><p class="mb-0">تیم پشتیبانی ما آماده پاسخگویی است.</p></div><a href="<?= url('dashboard/tickets/new') ?>" class="btn btn-white btn-lg"><?= icon('message') ?> ارسال تیکت</a></div></div>
</div>

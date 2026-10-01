<?= render('site/_page_hero', ['heroTitle' => 'پشتیبانی و تماس با ما', 'heroText' => 'از طریق تیکت، پیام‌رسان‌ها یا تلفن با ما در ارتباط باشید']) ?>
<div class="container section">
  <div class="grid g-3">
    <div class="feat-card reveal" style="--fc:#A78BFA;--fc2:#6C4CF1">
      <div class="f-ic"><?= icon('message') ?></div><h3>تیکت پشتیبانی</h3>
      <p>سریع‌ترین راه پیگیری سفارش‌ها. پاسخگویی در کمتر از چند ساعت.</p>
      <a href="<?= url('dashboard/tickets/new') ?>" class="btn btn-primary mt-2">ارسال تیکت <?= icon('arrow-left') ?></a>
    </div>
    <div class="feat-card reveal" style="--d:.06s;--fc:#38BDF8;--fc2:#2563EB">
      <div class="f-ic"><?= brand('telegram') ?></div><h3>پیام‌رسان‌ها</h3>
      <p>در تلگرام و سایر پیام‌رسان‌ها هم پاسخگوی شما هستیم.</p>
      <div class="row mt-2" style="flex-wrap:wrap">
        <?php foreach (['telegram' => 'social_telegram', 'whatsapp' => 'social_whatsapp', 'instagram' => 'social_instagram'] as $b => $k): if ($l = setting($k)): ?>
          <a href="<?= e($l) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm"><?= brand($b) ?> <?= e(ucfirst($b)) ?></a>
        <?php endif; endforeach; ?>
      </div>
    </div>
    <div class="feat-card reveal" style="--d:.12s;--fc:#34D399;--fc2:#059669">
      <div class="f-ic"><?= icon('phone') ?></div><h3>اطلاعات تماس</h3>
      <div class="kv mt-1">
        <?php if ($v = setting('support_phone')): ?><div><span>تلفن</span><b class="ltr"><?= e(fa($v)) ?></b></div><?php endif; ?>
        <?php if ($v = setting('support_email')): ?><div><span>ایمیل</span><b><?= e($v) ?></b></div><?php endif; ?>
        <?php if ($v = setting('support_hours')): ?><div><span>ساعات کاری</span><b><?= e($v) ?></b></div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

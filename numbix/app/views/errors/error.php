<?php
$titles = [403 => 'دسترسی غیرمجاز', 404 => 'صفحه پیدا نشد', 405 => 'درخواست نامعتبر', 419 => 'نشست منقضی شده', 500 => 'خطای سرور'];
?>
<div class="dark-zone blank-page">
  <div class="aurora"><div class="blob b1"></div><div class="blob b2"></div><div class="grid-bg"></div><div class="noise"></div></div>
  <div>
    <div class="err-code"><?= fa($code) ?></div>
    <h1 style="font-size:28px"><?= e($titles[$code] ?? 'خطا') ?></h1>
    <p class="muted" style="max-width:460px;margin:0 auto 26px"><?= e($message ?: ($code === 404 ? 'صفحه‌ای که دنبالش هستید وجود ندارد یا جابه‌جا شده است.' : 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.')) ?></p>
    <div class="row" style="justify-content:center;flex-wrap:wrap">
      <a href="<?= url('/') ?>" class="btn btn-primary btn-lg"><?= icon('home') ?> صفحه اصلی</a>
      <a href="<?= url('services') ?>" class="btn btn-glass btn-lg">مشاهده خدمات</a>
    </div>
  </div>
</div>

<?php
$titles = [403 => 'دسترسی غیرمجاز', 404 => 'صفحه پیدا نشد', 405 => 'درخواست نامعتبر', 419 => 'نشست منقضی شده', 500 => 'خطای سرور'];
?>
<div class="dark-zone blank-page">
  <span class="mini-planet" aria-hidden="true"></span><span class="mini-planet b" aria-hidden="true"></span>
  <div>
    <div class="lost" aria-hidden="true"><div class="planet-wrap" style="position:absolute;inset:0;width:auto;height:auto;translate:none;top:0;left:0"><div class="planet-ring back"></div><div class="planet" style="inset:24px"></div><div class="planet-ring front"></div></div><span class="astro"><?= icon('user') ?></span></div>
    <div class="err-code"><?= fa($code) ?></div>
    <h1 style="font-size:28px"><?= e($titles[$code] ?? 'خطا') ?></h1>
    <p class="muted" style="max-width:460px;margin:0 auto 26px"><?= e($message ?: ($code === 404 ? 'به نظر می‌رسد در فضا گم شده‌اید! صفحه‌ای که دنبالش هستید وجود ندارد یا به مدار دیگری رفته است.' : 'مشکلی پیش آمد. لطفاً دوباره تلاش کنید.')) ?></p>
    <div class="row" style="justify-content:center;flex-wrap:wrap">
      <a href="<?= url('/') ?>" class="btn btn-primary btn-lg"><?= icon('home') ?> صفحه اصلی</a>
      <a href="<?= url('services') ?>" class="btn btn-glass btn-lg">مشاهده خدمات</a>
    </div>
  </div>
</div>

<?= partial('page_head', ['icon' => 'code', 'h' => 'وب‌سرویس API', 'sub' => 'ثبت و پیگیری خودکار سفارش‌ها از ربات یا پنل خودتان']) ?>
<div class="dash-grid">
  <div class="card span-5 card-pad">
    <h3 class="card-title mb-2"><?= icon('key') ?> کلید API شما</h3>
    <?php if ($u['api_key']): ?>
      <div class="copy-field mb-2"><code><?= e($u['api_key']) ?></code><button class="btn btn-soft btn-sm" data-copy="<?= e($u['api_key']) ?>"><?= icon('copy') ?></button></div>
      <p class="small muted">این کلید مانند رمز عبور است؛ آن را در اختیار دیگران قرار ندهید.</p>
    <?php else: ?>
      <p class="muted">هنوز کلیدی نساخته‌اید.</p>
    <?php endif; ?>
    <form method="post" action="<?= url('dashboard/api/regenerate') ?>" <?= $u['api_key'] ? 'data-confirm="کلید فعلی باطل و کلید جدید ساخته شود؟"' : '' ?>><?= csrf_field() ?>
      <button class="btn btn-primary btn-block"><?= icon('refresh') ?> <?= $u['api_key'] ? 'ساخت کلید جدید' : 'ساخت کلید API' ?></button></form>
    <div class="kv mt-3">
      <div><span>آدرس API</span><b class="ltr small"><?= e(abs_url('api/v2')) ?></b></div>
      <div><span>متد</span><b>POST</b></div>
      <div><span>پاسخ</span><b>JSON</b></div>
      <div><span>واحد قیمت</span><b><?= e(setting('currency', 'تومان')) ?> / هر ۱۰۰۰</b></div>
    </div>
  </div>
  <div class="card span-7">
    <div class="card-head"><h3><?= icon('file') ?> مستندات</h3></div>
    <div class="card-body stack" style="gap:18px">
      <?php foreach ([
          ['لیست سرویس‌ها', "key=API_KEY\naction=services", '[{"service":1,"name":"...","rate":"39000.00","min":100,"max":50000,"category":"..."}]'],
          ['ثبت سفارش', "key=API_KEY\naction=add\nservice=1\nlink=https://t.me/channel\nquantity=1000", '{"order":23501}'],
          ['وضعیت سفارش', "key=API_KEY\naction=status\norder=23501   (یا orders=1,2,3)", '{"charge":"39000.00","start_count":"1520","status":"In progress","remains":"400","currency":"IRT"}'],
          ['موجودی حساب', "key=API_KEY\naction=balance", '{"balance":"250000.00","currency":"IRT"}'],
      ] as [$t, $req, $res]): ?>
        <div><b class="mb-1" style="display:block"><?= $t ?></b><div class="code-box"><?= e($req) ?>

<span style="color:#64748B">→ <?= e($res) ?></span></div></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

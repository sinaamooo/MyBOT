<div class="blank-page" style="background:radial-gradient(800px 400px at 50% 0%, rgba(var(--p-rgb),.18), transparent 70%), var(--bg)">
  <div class="card card-pad" style="width:min(440px,100%)">
    <div class="center mb-3">
      <span class="btile btile-xl" style="--c1:#FBBF24;--c2:#EA580C;margin:0 auto 14px"><?= icon('wrench') ?></span>
      <h1 style="font-size:20px">درگاه پرداخت آزمایشی</h1>
      <p class="muted mb-0">این درگاه فقط برای تست فرایند خرید است و پول واقعی جابه‌جا نمی‌شود.</p>
    </div>
    <div class="kv mb-3">
      <div><span>شماره تراکنش</span><b>#<?= fa($p['id']) ?></b></div>
      <div><span>نوع</span><b><?= $p['purpose'] === 'order' ? 'پرداخت سفارش' : 'شارژ کیف پول' ?></b></div>
      <div><span>مبلغ</span><b><?= money($p['amount']) ?></b></div>
    </div>
    <form method="post" action="<?= url('payment/test/' . $p['id']) ?>" class="grid g-2" style="gap:10px">
      <?= csrf_field() ?>
      <button class="btn btn-success btn-lg" name="result" value="ok"><?= icon('check') ?> پرداخت موفق</button>
      <button class="btn btn-danger btn-lg" name="result" value="fail"><?= icon('x') ?> انصراف</button>
    </form>
  </div>
</div>

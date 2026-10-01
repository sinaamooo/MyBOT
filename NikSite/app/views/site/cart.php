<?php $cur = e(setting('currency', 'تومان')); ?>
<div class="container section" style="padding-top:32px">
  <div class="row-between mb-3">
    <div class="row"><span class="btile btile-lg" style="--c1:#8B5CF6;--c2:#4F46E5"><?= icon('cart') ?></span>
      <div><h1 style="font-size:26px;margin:0">سبد خرید</h1><span class="muted"><?= num(count($sum['lines'])) ?> سفارش در سبد شما</span></div></div>
    <a href="<?= url('services') ?>" class="btn btn-soft"><?= icon('plus') ?> افزودن سرویس دیگر</a>
  </div>

  <?php if (!$sum['lines']): ?>
    <div class="card"><?= partial('empty', ['icon' => 'cart', 'title' => 'سبد خرید شما خالی است', 'text' => 'از بین خدمات متنوع ما، سرویس مورد نظرتان را انتخاب کنید.', 'action' => '<a href="' . url('services') . '" class="btn btn-primary">' . icon('grid') . ' مشاهده خدمات</a>']) ?></div>
  <?php else: ?>
  <div class="cart-grid">
    <div class="card">
      <?php foreach ($sum['lines'] as $l): $s = $l['service']; ?>
        <div class="cart-line">
          <?= brand_tile($s, 'lg') ?>
          <div style="min-width:0">
            <h4><a href="<?= e(service_url($s)) ?>"><?= e($s['title']) ?></a></h4>
            <div class="meta">
              <span><?= icon('hash') ?><?= num($l['qty']) ?> عدد</span>
              <span class="ltr" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= icon('link') ?><?= e($l['link']) ?></span>
            </div>
            <?php if ($l['error']): ?><div class="error-text"><?= icon('alert') ?> <?= e($l['error']) ?></div><?php endif; ?>
            <details class="mt-1">
              <summary class="small" style="cursor:pointer;color:var(--p);font-weight:700">ویرایش</summary>
              <form action="<?= url('cart/update') ?>" method="post" class="row mt-1" style="flex-wrap:wrap;gap:8px">
                <?= csrf_field() ?><input type="hidden" name="key" value="<?= e($l['key']) ?>">
                <input class="input input-sm ltr" name="link" value="<?= e($l['link']) ?>" style="flex:2;min-width:180px">
                <input class="input input-sm" name="qty" value="<?= (int)$l['qty'] ?>" inputmode="numeric" style="flex:1;min-width:90px">
                <button class="btn btn-soft btn-sm">ذخیره</button>
              </form>
            </details>
          </div>
          <div class="row" style="justify-content:flex-end">
            <div style="text-align:left">
              <?php if ($l['discount']): ?><del class="muted small"><?= num($l['amount']) ?></del><br><?php endif; ?>
              <b style="font-size:17px"><?= num($l['amount'] - $l['discount']) ?></b> <span class="unit"><?= $cur ?></span>
            </div>
            <form action="<?= url('cart/remove') ?>" method="post"><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($l['key']) ?>">
              <button class="btn btn-danger btn-icon btn-sm" aria-label="حذف"><?= icon('trash') ?></button></form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <aside class="stack" style="position:sticky;top:calc(var(--header-h) + 16px)">
      <div class="card card-pad">
        <h3 class="card-title mb-2"><?= icon('tag') ?> کد تخفیف</h3>
        <?php if ($sum['coupon']): ?>
          <form action="<?= url('cart/coupon') ?>" method="post" class="row-between alert alert-success"><?= csrf_field() ?>
            <span><?= icon('check-circle') ?> کد <b class="ltr"><?= e($sum['coupon']['code']) ?></b> اعمال شد</span>
            <button class="btn btn-ghost btn-xs" name="remove" value="1">حذف</button>
          </form>
        <?php else: ?>
          <form action="<?= url('cart/coupon') ?>" method="post" class="input-group"><?= csrf_field() ?>
            <input class="input ltr" name="code" placeholder="CODE" value="<?= e($sum['coupon_code'] ?? '') ?>" style="text-transform:uppercase">
            <button class="btn btn-soft">اعمال</button>
          </form>
          <?php if ($sum['coupon_error']): ?><div class="error-text"><?= e($sum['coupon_error']) ?></div><?php endif; ?>
        <?php endif; ?>
      </div>

      <form class="card card-pad" action="<?= url('cart/checkout') ?>" method="post">
        <?= csrf_field() ?>
        <h3 class="card-title mb-2"><?= icon('receipt') ?> خلاصه سفارش</h3>
        <div class="summary-row"><span class="muted">جمع سفارش‌ها</span><span><?= money($sum['subtotal']) ?></span></div>
        <?php if ($sum['discount']): ?><div class="summary-row" style="color:var(--success)"><span>تخفیف</span><span>− <?= money($sum['discount']) ?></span></div><?php endif; ?>
        <div class="summary-row total"><span>مبلغ قابل پرداخت</span><span><b><?= num($sum['total']) ?></b> <span class="unit"><?= $cur ?></span></span></div>

        <?php if ($u): ?>
          <?php $enough = (int)$u['balance'] >= $sum['total']; $first = $enough ? 'wallet' : ($online ? 'online' : ($testGateway ? 'test' : 'wallet')); ?>
          <div class="mt-2">
            <label class="pay-opt<?= $enough ? '' : ' disabled' ?>"><input type="radio" name="method" value="wallet" <?= $first === 'wallet' ? 'checked' : '' ?>><span class="po-ic"><?= icon('wallet') ?></span>
              <span><b>پرداخت از کیف پول</b><small>موجودی: <?= money_text($u['balance']) ?><?= $enough ? '' : ' (کافی نیست)' ?></small></span></label>
            <?php if ($online): ?>
              <label class="pay-opt"><input type="radio" name="method" value="online" <?= $first === 'online' ? 'checked' : '' ?>><span class="po-ic"><?= icon('card') ?></span>
                <span><b>پرداخت آنلاین</b><small>کلیه کارت‌های عضو شتاب</small></span></label>
            <?php endif; ?>
            <?php if ($testGateway): ?>
              <label class="pay-opt"><input type="radio" name="method" value="test" <?= $first === 'test' ? 'checked' : '' ?>><span class="po-ic"><?= icon('wrench') ?></span>
                <span><b>درگاه آزمایشی</b><small>فقط برای تست سیستم</small></span></label>
            <?php endif; ?>
            <?php if (($online || $testGateway) && (int)$u['balance'] > 0 && !$enough): ?>
              <label class="check small mt-1"><input type="checkbox" name="use_wallet" value="1" checked><span class="box"><?= icon('check') ?></span>استفاده از موجودی کیف پول (<?= money_text($u['balance']) ?>) و پرداخت مابقی</label>
            <?php endif; ?>
          </div>
          <?php if (!$enough && !$online && !$testGateway): ?>
            <div class="alert alert-warning mt-2"><?= icon('alert') ?><div>موجودی کیف پول کافی نیست. ابتدا <a href="<?= url('dashboard/wallet') ?>"><b>کیف پول را شارژ کنید</b></a>.</div></div>
          <?php endif; ?>
          <button class="btn btn-primary btn-lg btn-block mt-2" <?= $sum['has_errors'] || (!$enough && !$online && !$testGateway) ? 'disabled' : '' ?>><?= icon('shield') ?> پرداخت و ثبت نهایی</button>
        <?php else: ?>
          <a href="<?= url('login', ['next' => url('cart')]) ?>" class="btn btn-primary btn-lg btn-block mt-2"><?= icon('login') ?> ورود و ادامه خرید</a>
          <p class="help center">برای ثبت سفارش ابتدا وارد حساب کاربری شوید.</p>
        <?php endif; ?>
      </form>
    </aside>
  </div>
  <?php endif; ?>
</div>

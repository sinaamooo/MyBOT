<div class="modal" id="quick-add" role="dialog" aria-modal="true">
  <div class="modal-backdrop"></div>
  <div class="modal-dialog">
    <form action="<?= url('cart/add') ?>" method="post" data-ajax data-order-form>
      <?= csrf_field() ?>
      <input type="hidden" name="service_id">
      <div class="modal-head">
        <h3><span data-qa-tile></span><span data-qa-title>افزودن به سبد</span></h3>
        <button type="button" class="btn btn-ghost btn-icon btn-sm" data-close aria-label="بستن"><?= icon('x') ?></button>
      </div>
      <div class="modal-body">
        <div class="field">
          <label class="label">لینک یا آیدی مقصد</label>
          <div class="input-wrap"><?= icon('link') ?><input class="input ltr" name="link" required autocomplete="off"></div>
        </div>
        <div class="field mb-0">
          <label class="label">تعداد <span class="hint" data-qty-hint data-qa-range></span></label>
          <div class="qty w-100"><button type="button" data-step="-"><?= icon('minus') ?></button><input name="qty" inputmode="numeric" class="w-100"><button type="button" data-step="+"><?= icon('plus') ?></button></div>
          <div class="presets"></div>
        </div>
        <div class="total-box mb-0"><span class="muted">مبلغ قابل پرداخت</span><span><b data-total>۰</b> <span class="unit"><?= e(setting('currency', 'تومان')) ?></span></span></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-outline" data-close>انصراف</button>
        <button class="btn btn-primary"><?= icon('cart') ?> افزودن به سبد خرید</button>
      </div>
    </form>
  </div>
</div>

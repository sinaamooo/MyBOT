<?= partial('page_head', ['icon' => 'plus', 'h' => 'ثبت سفارش جدید', 'sub' => 'سرویس را انتخاب کنید، لینک و تعداد را وارد کنید؛ بقیه‌اش با ما!']) ?>
<div class="dash-grid">
  <div class="span-8">
    <div class="card mb-3">
      <div class="card-head"><h3><?= icon('grid') ?> ۱. انتخاب پلتفرم</h3></div>
      <div class="card-body">
        <div class="cat-strip" style="margin:0"><div class="inner" style="box-shadow:none;border:0;padding:0;background:transparent;backdrop-filter:none" id="cat-pick">
          <button type="button" class="cat-tile active" data-cat="" style="--c1:#6C4CF1;border:0;background:none;font:inherit"><span class="btile btile-md" style="--c1:#8B5CF6;--c2:#4F46E5"><?= icon('grid') ?></span><b>همه</b></button>
          <?php foreach ($categories as $c): ?>
            <button type="button" class="cat-tile" data-cat="<?= (int)$c['id'] ?>" style="--c1:<?= e($c['color']) ?>;border:0;background:none;font:inherit"><?= brand_tile($c, 'md') ?><b><?= e($c['name']) ?></b></button>
          <?php endforeach; ?>
        </div></div>
      </div>
    </div>

    <form class="card" method="post" action="<?= url('dashboard/new-order') ?>" data-order-form id="order-form">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('cart') ?> ۲. جزئیات سفارش</h3></div>
      <div class="card-body">
        <div class="field">
          <label class="label">سرویس</label>
          <select class="select" name="service_id" id="svc-select" required>
            <option value="">— یک سرویس انتخاب کنید —</option>
            <?php $lastCat = null; foreach ($services as $s):
              if ($lastCat !== $s['category_id']): if ($lastCat !== null): ?></optgroup><?php endif; ?><optgroup label="<?= e($s['category_name']) ?>"><?php $lastCat = $s['category_id']; endif;
              [$sk] = stock_state($s); $max = (int)min($s['max_qty'], $s['stock'] ?? PHP_INT_MAX); ?>
              <option value="<?= (int)$s['id'] ?>" data-cat="<?= (int)$s['category_id'] ?>" data-price="<?= (int)$s['price'] ?>" data-min="<?= (int)$s['min_qty'] ?>" data-max="<?= $max ?>"
                data-hint="<?= e($s['link_hint'] ?: 'لینک یا آیدی مقصد') ?>" data-start="<?= e($s['start_time'] ?: 'فوری') ?>" data-delivery="<?= e($s['delivery_time'] ?: '—') ?>"
                data-quality="<?= e($s['quality'] ?: '—') ?>" data-guarantee="<?= e($s['guarantee'] ?: 'ندارد') ?>" data-desc="<?= e(str_limit($s['description'], 400)) ?>"
                <?= $sk === 'out' ? 'disabled' : '' ?> <?= $selected === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['title']) ?> — <?= money_text($s['price']) ?> / ۱۰۰۰<?= $sk === 'out' ? ' (ناموجود)' : '' ?>
              </option>
            <?php endforeach; if ($lastCat !== null): ?></optgroup><?php endif; ?>
          </select>
        </div>
        <div class="spec-grid mb-2 hidden" id="svc-info">
          <div class="spec"><span class="s-ic"><?= icon('zap') ?></span><div><small>زمان شروع</small><b data-i="start"></b></div></div>
          <div class="spec"><span class="s-ic"><?= icon('clock') ?></span><div><small>زمان تحویل</small><b data-i="delivery"></b></div></div>
          <div class="spec"><span class="s-ic"><?= icon('award') ?></span><div><small>کیفیت</small><b data-i="quality"></b></div></div>
          <div class="spec"><span class="s-ic"><?= icon('shield') ?></span><div><small>ضمانت</small><b data-i="guarantee"></b></div></div>
        </div>
        <div class="alert alert-info mb-2 hidden" id="svc-desc"><?= icon('info') ?><p data-i="desc" style="white-space:pre-line"></p></div>
        <div class="field">
          <label class="label">لینک یا آیدی مقصد</label>
          <div class="input-wrap"><?= icon('link') ?><input class="input ltr" name="link" required autocomplete="off" value="<?= e(old('link')) ?>"></div>
        </div>
        <div class="grid g-2" style="gap:16px">
          <div class="field mb-0">
            <label class="label">تعداد <span class="hint" data-qty-hint id="qty-hint"></span></label>
            <div class="qty w-100"><button type="button" data-step="-"><?= icon('minus') ?></button><input name="qty" class="w-100" inputmode="numeric" value="<?= e(old('qty', '1000')) ?>"><button type="button" data-step="+"><?= icon('plus') ?></button></div>
            <div class="presets"></div>
          </div>
          <div class="field mb-0">
            <label class="label">کد تخفیف <span class="hint">اختیاری</span></label>
            <div class="input-wrap"><?= icon('tag') ?><input class="input ltr" name="coupon" value="<?= e(old('coupon')) ?>" placeholder="CODE" style="text-transform:uppercase"></div>
          </div>
        </div>
      </div>
      <div class="card-foot">
        <div class="row-between">
          <div><small class="muted">مبلغ قابل پرداخت</small><div><b data-total style="font-size:26px;font-weight:900">۰</b> <span class="unit"><?= e(setting('currency', 'تومان')) ?></span></div></div>
          <div class="row" style="flex-wrap:wrap">
            <?php if ($online || $testGateway): ?>
              <select class="select input-sm" name="method" style="width:auto">
                <option value="wallet">پرداخت از کیف پول</option>
                <?php if ($online): ?><option value="online">پرداخت آنلاین (مابقی)</option><?php endif; ?>
                <?php if ($testGateway): ?><option value="test">درگاه آزمایشی</option><?php endif; ?>
              </select>
            <?php endif; ?>
            <button class="btn btn-primary btn-lg"><?= icon('rocket') ?> ثبت و پرداخت سفارش</button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="span-4">
    <div class="card card-pad mb-3" style="background:linear-gradient(135deg,var(--p-soft),var(--card))">
      <small class="muted">موجودی کیف پول شما</small>
      <div><b style="font-size:26px;font-weight:900"><?= num($u['balance']) ?></b> <span class="unit"><?= e(setting('currency', 'تومان')) ?></span></div>
      <a href="<?= url('dashboard/wallet') ?>" class="btn btn-soft btn-sm mt-1"><?= icon('plus') ?> افزایش موجودی</a>
    </div>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('info') ?> راهنمای ثبت سفارش</h3>
      <div class="timeline">
        <div class="tl-item done"><b>انتخاب سرویس</b><small>پلتفرم و سرویس مورد نظر را انتخاب کنید.</small></div>
        <div class="tl-item done"><b>وارد کردن لینک</b><small>پیج یا پست باید عمومی (Public) باشد.</small></div>
        <div class="tl-item now"><b>پرداخت</b><small>از کیف پول یا درگاه بانکی پرداخت کنید.</small></div>
        <div class="tl-item"><b>شروع خودکار</b><small>سفارش بلافاصله وارد صف انجام می‌شود.</small></div>
      </div>
    </div>
  </div>
</div>
<?php push('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.getElementById('order-form'), sel = document.getElementById('svc-select');
  var info = document.getElementById('svc-info'), desc = document.getElementById('svc-desc');
  function apply() {
    var o = sel.selectedOptions[0];
    if (!o || !o.value) { info.classList.add('hidden'); desc.classList.add('hidden'); return; }
    form.dataset.price = o.dataset.price; form.dataset.min = o.dataset.min; form.dataset.max = o.dataset.max;
    form.link.placeholder = o.dataset.hint;
    ['start', 'delivery', 'quality', 'guarantee'].forEach(function (k) { info.querySelector('[data-i=' + k + ']').textContent = o.dataset[k]; });
    info.classList.remove('hidden');
    desc.classList.toggle('hidden', !o.dataset.desc); desc.querySelector('[data-i=desc]').textContent = o.dataset.desc;
    document.getElementById('qty-hint').textContent = 'حداقل ' + NBX.fmt(o.dataset.min) + ' — حداکثر ' + NBX.fmt(o.dataset.max);
    var q = parseInt(NBX.en(form.qty.value), 10) || 0, min = +o.dataset.min, max = +o.dataset.max;
    if (q < min || q > max) form.qty.value = Math.max(min, Math.min(max, 1000));
    var p = [min, 1000, 5000, 10000, 50000].filter(function (v, i, a) { return v >= min && v <= max && a.indexOf(v) === i; }).slice(0, 5);
    form.querySelector('.presets').innerHTML = p.map(function (v) { return '<button type="button" data-q="' + v + '">' + NBX.fmt(v) + '</button>'; }).join('');
    form.qty.dispatchEvent(new Event('nbx:recalc'));
  }
  sel.addEventListener('change', apply); apply();
  document.querySelectorAll('#cat-pick [data-cat]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#cat-pick .cat-tile').forEach(function (x) { x.classList.toggle('active', x === b); });
      var c = b.dataset.cat;
      sel.querySelectorAll('option[data-cat]').forEach(function (o) { o.hidden = c && o.dataset.cat !== c; });
      sel.querySelectorAll('optgroup').forEach(function (g) { g.hidden = !g.querySelector('option:not([hidden])'); });
      var first = sel.querySelector('option[data-cat]:not([hidden]):not([disabled])');
      if (c && first && (sel.selectedOptions[0].hidden || !sel.value)) { sel.value = first.value; apply(); }
    });
  });
});
</script>
<?php endpush(); ?>

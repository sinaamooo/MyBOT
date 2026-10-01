<?php $cur = e(setting('currency', 'تومان')); $sel = null; foreach ($all as $a) { if ((int)$a['id'] === $selected) { $sel = $a; } } $sel ??= $list[0] ?? $all[0] ?? null; ?>
<?= partial('page_head', ['icon' => 'database', 'h' => 'افزایش موجودی', 'sub' => 'موجودی محصولات و سرویس‌ها را مدیریت و افزایش دهید',
  'actions' => '<button type="button" class="btn btn-soft" data-open="#stock-help">' . icon('help') . ' راهنمای افزایش موجودی</button>']) ?>
<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">کل محصولات</div><div class="st-value"><?= num($k['total']) ?></div></div><span class="st-ic"><?= icon('box') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">محصولات فعال</div><div class="st-value"><?= num($k['active']) ?></div></div><span class="st-ic"><?= icon('database') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">موجودی کم</div><div class="st-value"><?= num($k['low']) ?></div><div class="st-foot">نیاز به شارژ</div></div><span class="st-ic"><?= icon('alert') ?></span></div>
  <div class="stat s-rose"><div><div class="st-label">ناموجود</div><div class="st-value"><?= num($k['out_']) ?></div><div class="st-foot">نیاز به شارژ فوری</div></div><span class="st-ic"><?= icon('x') ?></span></div>
</div>
<div class="dash-grid">
  <form class="card span-6" method="post" action="<?= url('admin/stock') ?>">
    <?= csrf_field() ?>
    <div class="card-head"><h3><?= icon('box') ?> افزایش موجودی محصول</h3></div>
    <div class="card-body">
      <p class="muted small" style="margin-top:-6px">محصول مورد نظر را انتخاب کرده و مقدار موجودی را افزایش دهید.</p>
      <div class="field">
        <label class="label">محصول / سرویس</label>
        <div class="svc-pick mb-1" data-pick-card><span data-pick-tile></span><div><b data-pick-title></b><small data-pick-sub></small></div></div>
        <select class="select" name="service_id" data-stock-pick>
          <?php foreach ($all as $a): ?>
            <option value="<?= $a['id'] ?>" <?= $sel && (int)$sel['id'] === (int)$a['id'] ? 'selected' : '' ?> data-tile="<?= e(brand_tile($a, 'md')) ?>" data-cost="<?= (int)$a['cost'] ?>" data-price="<?= (int)$a['price'] ?>"
              data-sub="<?= e($a['category_name'] . ' — موجودی فعلی: ' . ($a['stock'] === null ? 'نامحدود' : num($a['stock']))) ?>"><?= e($a['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid g-2" style="gap:14px">
        <div class="field mb-0"><label class="label">مقدار افزایش</label><input class="input" name="amount" inputmode="numeric" value="1000" required><div class="help">برای کاهش، عدد منفی وارد کنید.</div></div>
        <div class="field mb-0"><label class="label">واحد</label><select class="select" disabled><option>عدد</option></select></div>
        <div class="field mb-0"><label class="label">قیمت خرید (<?= $cur ?> / ۱۰۰۰)</label><input class="input" name="cost" inputmode="numeric" data-touch data-money></div>
        <div class="field mb-0"><label class="label">قیمت فروش (<?= $cur ?> / ۱۰۰۰)</label><input class="input" name="price" inputmode="numeric" data-touch data-money></div>
      </div>
      <div class="field mt-2"><label class="label">یادداشت <span class="hint">اختیاری</span></label><input class="input" name="note" placeholder="مثلاً: خرید از تامین‌کننده X"></div>
      <label class="check mb-2"><input type="checkbox" name="activate" value="1" checked><span class="box"><?= icon('check') ?></span>در صورت غیرفعال بودن، محصول را فعال کن</label>
      <div class="alert alert-primary mb-2"><?= icon('info') ?><div><b>اطلاعات تکمیلی</b><p>با افزایش موجودی، محصول مجدداً برای کاربران قابل خرید خواهد بود و به صورت خودکار در سایت نمایش داده می‌شود.</p></div></div>
      <button class="btn btn-primary btn-lg btn-block"><?= icon('plus') ?> افزایش موجودی</button>
    </div>
  </form>

  <div class="span-6 stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('box') ?> لیست محصولات</h3>
        <form method="get" action="<?= url('admin/stock') ?>" class="row" style="gap:6px"><?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="admin/stock"><?php endif; ?>
          <input class="input input-sm" name="q" value="<?= e(input('q', '')) ?>" placeholder="جستجو در محصولات…" style="width:170px">
          <select class="select input-sm" name="cat" onchange="this.form.submit()" style="width:130px"><option value="">همه دسته‌ها</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= input_int('cat') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
        </form></div>
      <div class="table-wrap" style="max-height:360px"><table class="table table-compact">
        <thead><tr><th>محصول / سرویس</th><th>موجودی</th><th>وضعیت</th><th></th></tr></thead>
        <tbody><?php foreach ($list as $s): [$sk, $sl, $sc] = stock_state($s); ?>
          <tr><td><div class="t-svc" style="min-width:150px"><?= brand_tile($s, 'xs') ?><b class="small"><?= e($s['title']) ?></b></div></td>
            <td class="num"><?= num($s['stock']) ?></td><td><?= badge($sl, $sc) ?></td>
            <td><a href="<?= url('admin/stock', ['service' => $s['id']]) ?>" class="btn btn-soft btn-xs"><?= icon('plus') ?> افزایش</a></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php if (!$list): ?><?= partial('empty', ['icon' => 'database', 'title' => 'محصولی با مدیریت موجودی پیدا نشد']) ?><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('clock') ?> سوابق افزایش موجودی</h3></div>
      <div class="table-wrap"><table class="table table-compact">
        <thead><tr><th>#</th><th>محصول</th><th>مقدار</th><th>موجودی جدید</th><th>تاریخ</th></tr></thead>
        <tbody><?php foreach ($logs as $l): ?>
          <tr><td class="num muted">#<?= fa($l['id']) ?></td><td><div class="t-svc" style="min-width:140px"><?= brand_tile($l, 'xs') ?><span class="small"><?= e(str_limit($l['title'], 24)) ?></span></div></td>
            <td class="num" style="color:var(--<?= $l['amount'] >= 0 ? 'success' : 'danger' ?>)"><span class="ltr"><?= $l['amount'] >= 0 ? '+' : '−' ?><?= num(abs($l['amount'])) ?></span></td>
            <td class="num muted"><?= $l['stock_after'] !== null ? num($l['stock_after']) : '—' ?></td>
            <td class="muted small nowrap"><?= jdate('Y/m/d H:i', $l['created_at']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php if (!$logs): ?><?= partial('empty', ['icon' => 'clock', 'title' => 'سابقه‌ای ثبت نشده']) ?><?php endif; ?>
    </div>
  </div>
</div>
<div class="modal" id="stock-help"><div class="modal-backdrop"></div><div class="modal-dialog">
  <div class="modal-head"><h3><?= icon('help') ?> راهنمای موجودی</h3><button class="btn btn-ghost btn-icon btn-sm" data-close><?= icon('x') ?></button></div>
  <div class="modal-body prose">
    <ul>
      <li>موجودی بر حسب «عدد» است (مثلاً تعداد ممبر یا فالوور قابل فروش).</li>
      <li>با هر سفارش پرداخت‌شده، موجودی به‌صورت خودکار کم می‌شود و در صورت لغو یا انجام ناقص، خودکار برمی‌گردد.</li>
      <li>وقتی موجودی کمتر از حداقل سفارش شود، محصول در سایت «ناموجود» نمایش داده می‌شود.</li>
      <li>وقتی موجودی به حد هشدار برسد، اعلان (و در صورت تنظیم، پیام تلگرام) برای مدیر ارسال می‌شود.</li>
      <li>محصولاتی که موجودی آن‌ها «نامحدود» است (مثلاً متصل به API) در این لیست نمایش داده نمی‌شوند.</li>
    </ul>
  </div>
</div></div>

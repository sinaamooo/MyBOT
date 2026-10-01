<?= partial('page_head', ['icon' => 'download', 'h' => 'ورود سرویس از «' . $p['name'] . '»', 'sub' => 'سرویس‌ها را انتخاب کنید تا با سود دلخواه به فروشگاه اضافه شوند',
  'actions' => '<a href="' . url('admin/providers') . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>
<?php if ($error): ?>
  <div class="alert alert-danger"><?= icon('x-circle') ?><div><b>دریافت سرویس‌ها ناموفق بود</b><p><?= e($error) ?></p></div></div>
<?php else: ?>
<form method="post" action="<?= url('admin/providers/' . $p['id'] . '/import') ?>" class="card">
  <?= csrf_field() ?>
  <div class="toolbar">
    <div class="input-wrap grow"><?= icon('search') ?><input class="input" data-import-filter placeholder="فیلتر بر اساس نام، دسته یا شناسه…"></div>
    <select class="select w-sm" name="category_id" required><option value="">دسته‌بندی مقصد…</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select class="select w-sm" name="type"><?php foreach (service_types() as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select>
    <div class="input-wrap" style="width:150px"><?= icon('percent') ?><input class="input" name="markup" value="30" title="درصد سود"></div>
    <div class="input-wrap" style="width:170px"><?= icon('coins') ?><input class="input" name="rate" value="1" title="ضریب تبدیل ارز API به تومان (مثلاً قیمت دلار)"></div>
    <label class="check small"><input type="checkbox" name="activate" value="1" checked><span class="box"><?= icon('check') ?></span>فعال‌سازی</label>
    <button class="btn btn-primary"><?= icon('download') ?> ورود انتخاب‌شده‌ها</button>
  </div>
  <div class="bulk-bar" id="ibulk"><span><b data-selected>۰</b> سرویس انتخاب شده</span></div>
  <div class="table-wrap" style="max-height:620px"><table class="table table-compact">
    <thead><tr><th style="width:40px"><label class="check"><input type="checkbox" data-check-all="#ibulk"><span class="box"><?= icon('check') ?></span></label></th><th>شناسه</th><th>نام سرویس</th><th>دسته (API)</th><th>قیمت / ۱۰۰۰</th><th>حداقل</th><th>حداکثر</th></tr></thead>
    <tbody><?php foreach ($list as $s): $sid = (string)($s['service'] ?? ''); $has = isset($existing[$sid]); ?>
      <tr style="<?= $has ? 'opacity:.5' : '' ?>">
        <td><?php if (!$has): ?><label class="check"><input type="checkbox" class="row-check" name="pick[]" value="<?= e($sid) ?>"><span class="box"><?= icon('check') ?></span></label><?php else: ?><?= icon('check-circle') ?><?php endif; ?></td>
        <td class="num ltr"><?= e($sid) ?></td>
        <td class="small"><?= e($s['name'] ?? '') ?></td>
        <td class="small muted"><?= e($s['category'] ?? '') ?></td>
        <td class="num ltr"><?= e($s['rate'] ?? '') ?></td>
        <td class="num"><?= num($s['min'] ?? 0) ?></td>
        <td class="num"><?= num($s['max'] ?? 0) ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="table-foot"><span><?= num(count($list)) ?> سرویس در API — موارد کم‌رنگ قبلاً وارد شده‌اند</span></div>
</form>
<?php endif; ?>

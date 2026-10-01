<?php $e = null; foreach ($rows as $r) { if ((int)$r['id'] === $edit) { $e = $r; } } ?>
<?= partial('page_head', ['icon' => 'tag', 'h' => 'کدهای تخفیف', 'sub' => 'کمپین‌های تخفیف درصدی یا مبلغ ثابت']) ?>
<div class="dash-grid">
  <div class="card span-8">
    <?php if ($rows): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>کد</th><th>تخفیف</th><th>شرایط</th><th>استفاده</th><th>جمع تخفیف</th><th>انقضا</th><th>وضعیت</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $c): $expired = $c['expires_at'] && strtotime($c['expires_at']) < time(); ?>
        <tr>
          <td><span class="chip ltr" style="font-weight:800;letter-spacing:.05em"><?= e($c['code']) ?></span> <button type="button" class="btn btn-ghost btn-icon btn-xs" data-copy="<?= e($c['code']) ?>"><?= icon('copy') ?></button></td>
          <td class="num"><?= $c['type'] === 'percent' ? '٪' . fa($c['value']) : money($c['value']) ?><?= $c['max_discount'] ? '<div class="small muted">سقف ' . money_text($c['max_discount']) . '</div>' : '' ?></td>
          <td class="small muted"><?= $c['min_amount'] ? 'حداقل ' . money_text($c['min_amount']) : 'بدون حداقل' ?><?= $c['category_name'] ? '<br>فقط ' . e($c['category_name']) : '' ?></td>
          <td class="num"><?= num($c['used_count']) ?><?= $c['max_uses'] ? ' / ' . num($c['max_uses']) : '' ?></td>
          <td class="num"><?= num($c['total_discount']) ?></td>
          <td class="small nowrap"><?= $c['expires_at'] ? jdate('Y/m/d', $c['expires_at']) : '—' ?></td>
          <td><?= !$c['is_active'] ? badge('غیرفعال', 'muted') : ($expired ? badge('منقضی', 'danger') : badge('فعال', 'success')) ?></td>
          <td><div class="actions"><a href="<?= url('admin/coupons', ['edit' => $c['id']]) ?>" class="btn btn-soft btn-icon btn-xs"><?= icon('edit') ?></a>
            <form method="post" action="<?= url('admin/coupons/' . $c['id'] . '/delete') ?>" data-confirm="کد <?= e($c['code']) ?> حذف شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-icon btn-xs"><?= icon('trash') ?></button></form></div></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php else: ?><?= partial('empty', ['icon' => 'tag', 'title' => 'هنوز کد تخفیفی نساخته‌اید']) ?><?php endif; ?>
  </div>
  <form class="card span-4" method="post" action="<?= url('admin/coupons/save') ?>" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
    <div class="card-head"><h3><?= icon($e ? 'edit' : 'plus') ?> <?= $e ? 'ویرایش کد' : 'کد تخفیف جدید' ?></h3><?php if ($e): ?><a href="<?= url('admin/coupons') ?>" class="btn btn-ghost btn-xs">انصراف</a><?php endif; ?></div>
    <div class="card-body">
      <div class="field"><label class="label">کد <span class="hint">خالی = تولید خودکار</span></label><input class="input ltr" name="code" value="<?= e($e['code'] ?? '') ?>" style="text-transform:uppercase"></div>
      <div class="grid g-2" style="gap:10px">
        <div class="field mb-0"><label class="label">نوع</label><select class="select" name="type"><option value="percent">درصدی</option><option value="fixed" <?= ($e['type'] ?? '') === 'fixed' ? 'selected' : '' ?>>مبلغ ثابت</option></select></div>
        <div class="field mb-0"><label class="label">مقدار</label><input class="input" name="value" inputmode="numeric" value="<?= e($e['value'] ?? '10') ?>" required></div>
        <div class="field mb-0"><label class="label">سقف تخفیف</label><input class="input" name="max_discount" inputmode="numeric" value="<?= e($e['max_discount'] ?? '') ?>"></div>
        <div class="field mb-0"><label class="label">حداقل خرید</label><input class="input" name="min_amount" inputmode="numeric" value="<?= e($e['min_amount'] ?? '0') ?>"></div>
        <div class="field mb-0"><label class="label">کل دفعات</label><input class="input" name="max_uses" inputmode="numeric" value="<?= e($e['max_uses'] ?? '0') ?>"><div class="help">۰ = نامحدود</div></div>
        <div class="field mb-0"><label class="label">برای هر کاربر</label><input class="input" name="per_user" inputmode="numeric" value="<?= e($e['per_user'] ?? '1') ?>"></div>
        <div class="field mb-0"><label class="label">شروع (شمسی)</label><input class="input ltr" name="starts_at" value="<?= $e && $e['starts_at'] ? jdate('Y/m/d', $e['starts_at'], false) : '' ?>" placeholder="1405/07/01"></div>
        <div class="field mb-0"><label class="label">پایان (شمسی)</label><input class="input ltr" name="expires_at" value="<?= $e && $e['expires_at'] ? jdate('Y/m/d', $e['expires_at'], false) : '' ?>" placeholder="1405/08/01"></div>
      </div>
      <div class="field mt-2"><label class="label">محدود به دسته</label><select class="select" name="category_id"><option value="">همه خدمات</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)($e['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
      <label class="switch mb-2"><input type="checkbox" name="is_active" value="1" <?= ($e['is_active'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>فعال</label>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره کد</button>
    </div>
  </form>
</div>

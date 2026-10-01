<?php $e = null; foreach ($rows as $r) { if ((int)$r['id'] === $edit) { $e = $r; } } ?>
<?= partial('page_head', ['icon' => 'grid', 'h' => 'خدمات و دسته‌بندی‌ها', 'sub' => 'پلتفرم‌ها و دسته‌های نمایش داده شده در سایت']) ?>
<div class="dash-grid">
  <div class="card span-8">
    <div class="table-wrap"><table class="table">
      <thead><tr><th>دسته‌بندی</th><th>نامک</th><th>محصولات</th><th>سفارش‌ها</th><th>ترتیب</th><th>وضعیت</th><th></th></tr></thead>
      <tbody><?php foreach ($rows as $c): ?>
        <tr>
          <td><div class="t-svc"><?= brand_tile($c, 'md') ?><div><b><?= e($c['name']) ?></b><small><?= e(str_limit($c['description'], 40)) ?></small></div></div></td>
          <td class="ltr muted small"><?= e($c['slug']) ?></td>
          <td class="num"><?= num($c['services_count']) ?></td>
          <td class="num"><?= num($c['orders_count']) ?></td>
          <td class="num"><?= num($c['sort']) ?></td>
          <td><?= $c['is_active'] ? badge('فعال', 'success') : badge('غیرفعال', 'danger') ?></td>
          <td><div class="actions">
            <a href="<?= url('admin/categories', ['edit' => $c['id']]) ?>" class="btn btn-soft btn-icon btn-xs"><?= icon('edit') ?></a>
            <form method="post" action="<?= url('admin/categories/' . $c['id'] . '/delete') ?>" data-confirm="دسته «<?= e($c['name']) ?>» حذف شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-icon btn-xs"><?= icon('trash') ?></button></form>
          </div></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
  <form class="card span-4" method="post" action="<?= url('admin/categories/save') ?>" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
    <div class="card-head"><h3><?= icon($e ? 'edit' : 'plus') ?> <?= $e ? 'ویرایش دسته‌بندی' : 'دسته‌بندی جدید' ?></h3><?php if ($e): ?><a href="<?= url('admin/categories') ?>" class="btn btn-ghost btn-xs">انصراف</a><?php endif; ?></div>
    <div class="card-body">
      <div class="field"><label class="label">نام</label><input class="input" name="name" id="c-name" value="<?= e($e['name'] ?? '') ?>" required></div>
      <div class="field"><label class="label">نامک</label><input class="input ltr" name="slug" value="<?= e($e['slug'] ?? '') ?>" data-slug-from="#c-name"></div>
      <div class="field"><label class="label">آیکون برند</label>
        <select class="select" name="icon"><?php foreach (array_merge($brands, ['grid', 'globe', 'star', 'heart', 'zap', 'users']) as $b): ?><option value="<?= $b ?>" <?= ($e['icon'] ?? '') === $b ? 'selected' : '' ?>><?= $b ?></option><?php endforeach; ?></select></div>
      <div class="field"><label class="label">رنگ‌های گرادینت</label><div class="color-row"><input type="color" name="color" value="<?= e($e['color'] ?? '#6C4CF1') ?>"><input type="color" name="color2" value="<?= e($e['color2'] ?? '#4F46E5') ?>"></div></div>
      <div class="field"><label class="label">توضیح کوتاه</label><input class="input" name="description" value="<?= e($e['description'] ?? '') ?>"></div>
      <div class="field"><label class="label">ترتیب</label><input class="input" name="sort" value="<?= e($e['sort'] ?? '0') ?>" inputmode="numeric"></div>
      <label class="switch mb-2"><input type="checkbox" name="is_active" value="1" <?= ($e['is_active'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>فعال</label>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره</button>
    </div>
  </form>
</div>

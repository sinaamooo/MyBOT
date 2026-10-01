<?= partial('page_head', ['icon' => 'box', 'h' => 'مدیریت محصولات', 'sub' => 'سرویس‌ها، قیمت‌ها، موجودی و اتصال API',
  'actions' => '<a href="' . url('admin/providers') . '" class="btn btn-outline">' . icon('server') . ' ورود از API</a><a href="' . url('admin/services/create') . '" class="btn btn-primary">' . icon('plus') . ' محصول جدید</a>']) ?>
<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">کل محصولات</div><div class="st-value"><?= num($k['total']) ?></div></div><span class="st-ic"><?= icon('box') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">فعال</div><div class="st-value"><?= num($k['active']) ?></div></div><span class="st-ic"><?= icon('check-circle') ?></span></div>
  <div class="stat s-blue"><div><div class="st-label">متصل به API</div><div class="st-value"><?= num($k['api']) ?></div></div><span class="st-ic"><?= icon('server') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">ویژه صفحه اصلی</div><div class="st-value"><?= num($k['featured']) ?></div></div><span class="st-ic"><?= icon('star') ?></span></div>
</div>
<form class="card" method="post" action="<?= url('admin/services/bulk') ?>">
  <?= csrf_field() ?>
  <div class="toolbar">
    <div class="input-wrap grow"><?= icon('search') ?><input class="input" form="flt" name="q" value="<?= e(input('q', '')) ?>" placeholder="جستجوی عنوان یا شناسه…"></div>
    <select class="select w-sm" form="flt" name="cat" onchange="this.form.submit()"><option value="">همه دسته‌ها</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= input_int('cat') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select class="select w-sm" form="flt" name="state" onchange="this.form.submit()"><?php foreach (['' => 'همه وضعیت‌ها', 'active' => 'فعال', 'inactive' => 'غیرفعال', 'api' => 'متصل به API'] as $v => $l): ?><option value="<?= $v ?>" <?= input('state', '') === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-soft" form="flt"><?= icon('filter') ?> فیلتر</button>
  </div>
  <div class="bulk-bar" id="bulk">
    <span><b data-selected>۰</b> مورد انتخاب شده</span>
    <select class="select input-sm" name="action" style="width:auto"><option value="activate">فعال‌سازی</option><option value="deactivate">غیرفعال‌سازی</option><option value="feature">نمایش در صفحه اصلی</option><option value="price">تغییر قیمت (درصد)</option></select>
    <input class="input input-sm" name="percent" placeholder="٪ مثلاً 10 یا -5" style="width:150px">
    <button class="btn btn-primary btn-sm">اعمال</button>
  </div>
  <?php if ($page['items']): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th style="width:40px"><label class="check"><input type="checkbox" data-check-all="#bulk"><span class="box"><?= icon('check') ?></span></label></th><th>محصول</th><th>قیمت فروش / ۱۰۰۰</th><th>قیمت خرید</th><th>حداقل/حداکثر</th><th>موجودی</th><th>فروش</th><th>منبع</th><th>وضعیت</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($page['items'] as $s): [$sk, $sl, $sc] = stock_state($s); ?>
      <tr>
        <td><label class="check"><input type="checkbox" class="row-check" value="<?= $s['id'] ?>"><span class="box"><?= icon('check') ?></span></label></td>
        <td><div class="t-svc"><?= brand_tile($s, 'sm') ?><div><b><?= e($s['title']) ?></b><small><?= e($s['category_name']) ?> · #<?= fa($s['id']) ?><?= $s['is_featured'] ? ' · ⭐' : '' ?></small></div></div></td>
        <td class="num"><?= money($s['price']) ?></td>
        <td class="num muted"><?= num($s['cost']) ?></td>
        <td class="small muted nowrap"><?= num($s['min_qty']) ?> / <?= short_num((int)$s['max_qty']) ?></td>
        <td><?= $s['stock'] === null ? '<span class="muted small">نامحدود</span>' : badge(num($s['stock']), $sc) ?></td>
        <td class="num"><?= num($s['sales_count']) ?></td>
        <td><?= $s['provider_id'] ? badge($s['provider_name'] ?: 'API', 'info', 'server') : badge('دستی', 'muted') ?></td>
        <td><?= $s['is_active'] ? badge('فعال', 'success') : badge('غیرفعال', 'danger') ?></td>
        <td><div class="actions">
          <a href="<?= e(service_url($s)) ?>" target="_blank" class="btn btn-ghost btn-icon btn-xs" title="مشاهده"><?= icon('external') ?></a>
          <a href="<?= url('admin/stock', ['service' => $s['id']]) ?>" class="btn btn-ghost btn-icon btn-xs" title="موجودی"><?= icon('database') ?></a>
          <a href="<?= url('admin/services/' . $s['id'] . '/edit') ?>" class="btn btn-soft btn-icon btn-xs" title="ویرایش"><?= icon('edit') ?></a>
          <button form="del-<?= $s['id'] ?>" class="btn btn-danger btn-icon btn-xs" title="حذف"><?= icon('trash') ?></button>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <div class="table-foot"><span>نمایش <?= num(count($page['items'])) ?> از <?= num($page['total']) ?> محصول</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?><?= partial('empty', ['icon' => 'box', 'title' => 'محصولی پیدا نشد']) ?><?php endif; ?>
</form>
<form id="flt" method="get" action="<?= url('admin/services') ?>"><?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="admin/services"><?php endif; ?></form>
<?php foreach ($page['items'] as $s): ?><form id="del-<?= $s['id'] ?>" method="post" action="<?= url('admin/services/' . $s['id'] . '/delete') ?>" data-confirm="محصول «<?= e($s['title']) ?>» حذف شود؟"><?= csrf_field() ?></form><?php endforeach; ?>

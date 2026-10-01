<?php $cur = e(setting('currency', 'تومان')); $keep = array_diff_key($_GET, ['r' => 1, 'page' => 1]); ?>
<?= partial('page_head', ['icon' => 'cart', 'h' => 'سفارشات کاربران', 'sub' => 'مدیریت و مشاهده تمام سفارش‌های کاربران در ' . site_name(),
  'actions' => '<a href="' . url('admin/orders/create') . '" class="btn btn-primary">' . icon('plus') . ' ثبت سفارش جدید</a>']) ?>
<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">کل سفارشات</div><div class="st-value"><?= num($k['total']) ?></div><div class="st-foot"><?= AdminDashboardController::trend((float)$k['month_'], (float)$k['prev']) ?> نسبت به ماه قبل</div></div><span class="st-ic"><?= icon('cart') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">تکمیل شده</div><div class="st-value"><?= num($k['completed']) ?></div><div class="st-foot">٪<?= fa($k['total'] ? round($k['completed'] * 100 / $k['total']) : 0) ?> از کل سفارش‌ها</div></div><span class="st-ic"><?= icon('check') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">در حال پردازش</div><div class="st-value"><?= num($k['active']) ?></div><div class="st-foot">٪<?= fa($k['total'] ? round($k['active'] * 100 / $k['total']) : 0) ?> از کل سفارش‌ها</div></div><span class="st-ic"><?= icon('clock') ?></span></div>
  <div class="stat s-rose"><div><div class="st-label">سفارش لغو شده</div><div class="st-value"><?= num($k['canceled']) ?></div><div class="st-foot">٪<?= fa($k['total'] ? round($k['canceled'] * 100 / $k['total']) : 0) ?> از کل سفارش‌ها</div></div><span class="st-ic"><?= icon('box') ?></span></div>
</div>

<div class="card">
  <form class="toolbar" method="get" action="<?= url('admin/orders') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="admin/orders"><?php endif; ?>
    <div class="input-wrap grow"><?= icon('search') ?><input class="input" name="q" value="<?= e(input('q', '')) ?>" placeholder="جستجو در سفارش‌ها: شماره، کاربر، لینک…"></div>
    <select class="select w-sm" name="status"><option value="">همه وضعیت‌ها</option><?php foreach (order_statuses() as $kk => [$l]): ?><option value="<?= $kk ?>" <?= input('status') === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select class="select w-sm" name="service"><option value="">همه سرویس‌ها</option><?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>" <?= input_int('service') === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['title']) ?></option><?php endforeach; ?></select>
    <div class="input-wrap" style="width:150px"><?= icon('calendar') ?><input class="input" name="from" value="<?= e(input('from', '')) ?>" placeholder="از ۱۴۰۵/۰۱/۰۱"></div>
    <div class="input-wrap" style="width:150px"><?= icon('calendar') ?><input class="input" name="to" value="<?= e(input('to', '')) ?>" placeholder="تا تاریخ"></div>
    <button class="btn btn-primary"><?= icon('filter') ?> فیلتر</button>
    <a class="btn btn-soft" href="<?= url('admin/orders/export', $keep) ?>"><?= icon('download') ?> خروجی Excel</a>
  </form>
  <form method="post" action="<?= url('admin/orders/bulk') ?>" data-confirm="وضعیت سفارش‌های انتخاب‌شده تغییر کند؟">
    <?= csrf_field() ?>
    <div class="bulk-bar" id="obulk"><span><b data-selected>۰</b> سفارش انتخاب شده</span>
      <select class="select input-sm" name="status" style="width:auto"><?php foreach (order_statuses() as $kk => [$l]): if ($kk === 'unpaid') continue; ?><option value="<?= $kk ?>"><?= $l ?></option><?php endforeach; ?></select>
      <button class="btn btn-primary btn-sm">تغییر وضعیت</button></div>
    <?php if ($page['items']): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th style="width:40px"><label class="check"><input type="checkbox" data-check-all="#obulk"><span class="box"><?= icon('check') ?></span></label></th><th>شماره سفارش</th><th>کاربر</th><th>سرویس/محصول</th><th>مبلغ (<?= $cur ?>)</th><th>وضعیت</th><th>تاریخ ثبت</th><th>عملیات</th></tr></thead>
      <tbody><?php foreach ($page['items'] as $o): ?>
        <tr>
          <td><label class="check"><input type="checkbox" class="row-check" value="<?= $o['id'] ?>"><span class="box"><?= icon('check') ?></span></label></td>
          <td class="num">#<?= fa($o['id']) ?><?php if ($o['source'] === 'api'): ?> <span class="badge badge-info" style="height:20px;font-size:10px">API</span><?php endif; ?></td>
          <td><a class="t-user" href="<?= url('admin/users/' . $o['uid']) ?>"><?= avatar(['id' => $o['uid'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'avatar' => $o['avatar']], 'sm') ?><div><b><?= e(trim($o['first_name'] . ' ' . $o['last_name']) ?: '—') ?></b><small class="ltr"><?= e($o['username'] ?: $o['email']) ?></small></div></a></td>
          <td><div class="t-svc"><?= brand_tile($o, 'sm') ?><div><b><?= e($o['title']) ?></b><small><?= num($o['quantity']) ?> عدد</small></div></div></td>
          <td class="num"><?= num($o['price'] - $o['discount']) ?></td>
          <td><?= status_badge($o['status']) ?><?php if ($o['provider_error'] && $o['status'] === 'pending'): ?><div class="small" style="color:var(--danger)" title="<?= e($o['provider_error']) ?>"><?= icon('alert') ?> خطای API</div><?php endif; ?></td>
          <td class="muted nowrap small"><?= jdate('Y/m/d H:i', $o['created_at']) ?></td>
          <td><div class="actions">
            <a href="<?= url('admin/orders/' . $o['id']) ?>" class="btn btn-soft btn-icon btn-xs" title="مشاهده"><?= icon('eye') ?></a>
            <div class="dropdown"><button type="button" class="btn btn-ghost btn-icon btn-xs" data-dropdown><?= icon('more') ?></button>
              <div class="dropdown-menu" style="min-width:200px">
                <a href="<?= url('admin/orders/' . $o['id']) ?>"><?= icon('edit') ?> تغییر وضعیت</a>
                <a href="<?= e(safe_href($o['link'])) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> باز کردن لینک</a>
                <a href="<?= url('admin/users/' . $o['uid']) ?>"><?= icon('user') ?> پروفایل کاربر</a>
              </div></div>
          </div></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php else: ?><?= partial('empty', ['icon' => 'cart', 'title' => 'سفارشی پیدا نشد']) ?><?php endif; ?>
  </form>
  <div class="table-foot">
    <div class="row">تعداد در صفحه:
      <select class="select input-sm" style="width:80px" onchange="location.href=this.value"><?php foreach ([15, 30, 50, 100] as $pp): ?><option value="<?= e(url('admin/orders', array_merge($keep, ['per' => $pp]))) ?>" <?= $per === $pp ? 'selected' : '' ?>><?= fa($pp) ?></option><?php endforeach; ?></select>
      <span>نمایش <?= num(count($page['items'])) ?> از <?= num($page['total']) ?> سفارش</span></div>
    <?= partial('pagination', ['p' => $page]) ?>
  </div>
</div>

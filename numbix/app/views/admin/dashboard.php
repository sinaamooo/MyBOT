<?php $u = auth(); $cur = e(setting('currency', 'تومان')); $statusMap = array_column($byStatus, 'n', 'status'); ?>
<div class="page-head">
  <div class="ph-title">
    <div><h1>سلام <?= e($u['first_name'] ?: 'مدیر') ?> عزیز 👋</h1><p>به پنل مدیریت <?= e(site_name()) ?> خوش آمدید؛ امروز همه چیز تحت کنترل است.</p></div>
  </div>
  <div class="ph-actions">
    <span class="chip"><?= icon('calendar') ?> امروز <?= jdate('l j F Y') ?></span>
    <form method="post" action="<?= url('admin/cron/run') ?>"><?= csrf_field() ?><button class="btn btn-soft btn-sm"><?= icon('refresh') ?> اجرای اتوماسیون</button></form>
  </div>
</div>

<?php if ($k['pending'] || $k['tickets_open'] || $k['payments_review'] || $k['low_stock']): ?>
<div class="row mb-3" style="flex-wrap:wrap;gap:10px">
  <?php if ($k['pending']): ?><a href="<?= url('admin/orders', ['status' => 'pending']) ?>" class="chip" style="background:var(--warning-soft);color:var(--warning)"><?= icon('clock') ?> <?= fa($k['pending']) ?> سفارش در صف انجام</a><?php endif; ?>
  <?php if ($k['tickets_open']): ?><a href="<?= url('admin/tickets') ?>" class="chip" style="background:var(--info-soft);color:var(--info)"><?= icon('message') ?> <?= fa($k['tickets_open']) ?> تیکت منتظر پاسخ</a><?php endif; ?>
  <?php if ($k['payments_review']): ?><a href="<?= url('admin/payments', ['status' => 'review']) ?>" class="chip" style="background:var(--p-soft);color:var(--p)"><?= icon('receipt') ?> <?= fa($k['payments_review']) ?> فیش در انتظار تایید</a><?php endif; ?>
  <?php if ($k['low_stock']): ?><a href="<?= url('admin/stock') ?>" class="chip" style="background:var(--danger-soft);color:var(--danger)"><?= icon('alert') ?> <?= fa($k['low_stock']) ?> محصول با موجودی کم</a><?php endif; ?>
</div>
<?php endif; ?>

<div class="stats">
  <div class="stat s-rose"><div><div class="st-label">محصولات فعال</div><div class="st-value"><?= num($k['services_active']) ?></div><div class="st-foot">از <?= num($k['services_total']) ?> محصول</div></div><span class="st-ic"><?= icon('box') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">تعداد کاربران</div><div class="st-value"><?= num($k['users_total']) ?></div><div class="st-foot"><?= AdminDashboardController::trend($k['users_month'], $k['users_prev']) ?> نسبت به ماه قبل</div></div><span class="st-ic"><?= icon('users') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">درآمد امروز</div><div class="st-value"><?= num($k['revenue_today']) ?> <small><?= $cur ?></small></div><div class="st-foot"><?= AdminDashboardController::trend($k['revenue_today'], $k['revenue_yesterday']) ?> نسبت به دیروز</div></div><span class="st-ic"><?= icon('database') ?></span></div>
  <div class="stat s-violet"><div><div class="st-label">کل سفارش‌ها</div><div class="st-value"><?= num($k['orders_total']) ?></div><div class="st-foot"><?= AdminDashboardController::trend($k['orders_month'], $k['orders_prev']) ?> نسبت به ماه قبل</div></div><span class="st-ic"><?= icon('cart') ?></span></div>
</div>

<div class="dash-grid">
  <div class="card span-8">
    <div class="card-head">
      <h3><?= icon('chart') ?> نمودار فروش و سفارش‌ها</h3>
      <div class="row" style="flex-wrap:wrap">
        <div class="legend"><span><i style="background:#8B5CF6"></i>درآمد (<?= $cur ?>)</span><span><i style="background:#38BDF8"></i>تعداد سفارش‌ها</span></div>
        <div class="tabs" style="padding:3px"><?php foreach ([7 => '۷ روز', 30 => '۳۰ روز', 90 => '۹۰ روز'] as $d => $l): ?><a href="<?= url('admin', ['days' => $d]) ?>" class="<?= $days === $d ? 'active' : '' ?>" style="padding:4px 10px;font-size:12px"><?= $l ?></a><?php endforeach; ?></div>
      </div>
    </div>
    <div class="chart-box"><canvas data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>"></canvas></div>
  </div>
  <div class="card span-4">
    <div class="card-head"><h3><?= icon('wallet') ?> خلاصه مالی</h3></div>
    <div class="card-body">
      <div class="sys-row"><span><?= icon('trending-up') ?> درآمد این ماه</span><b><?= money($k['revenue_month']) ?></b></div>
      <div class="sys-row"><span><?= icon('cart') ?> سفارش‌های این ماه</span><b><?= num($k['orders_month']) ?></b></div>
      <div class="sys-row"><span><?= icon('users') ?> کاربران جدید ماه</span><b><?= num($k['users_month']) ?></b></div>
      <div class="sys-row"><span><?= icon('coins') ?> موجودی کیف پول کاربران</span><b><?= money($k['wallets']) ?></b></div>
      <h4 class="mt-2 mb-1" style="font-size:13px">وضعیت سفارش‌ها</h4>
      <?php $tot = max(1, array_sum($statusMap)); foreach (['completed', 'in_progress', 'processing', 'pending', 'partial', 'canceled'] as $st): $n = (int)($statusMap[$st] ?? 0); [$l, $c] = order_statuses()[$st]; ?>
        <div class="row-between small" style="margin-top:6px"><span><?= $l ?></span><b><?= num($n) ?></b></div>
        <div class="progress" style="height:6px"><i style="width:<?= round($n * 100 / $tot) ?>%;background:var(--<?= $c === 'primary' ? 'p' : $c ?>)"></i></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card span-6">
    <div class="card-head"><h3><?= icon('cart') ?> آخرین سفارش‌ها</h3><a href="<?= url('admin/orders') ?>" class="btn btn-ghost btn-sm">مشاهده همه <?= icon('arrow-left') ?></a></div>
    <div class="table-wrap"><table class="table table-compact">
      <thead><tr><th>#</th><th>کاربر</th><th>خدمت</th><th>مبلغ</th><th>وضعیت</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr onclick="location.href='<?= url('admin/orders/' . $o['id']) ?>'" style="cursor:pointer">
          <td class="num">#<?= fa($o['id']) ?></td>
          <td class="small"><?= e(trim($o['first_name'] . ' ' . $o['last_name']) ?: $o['email']) ?></td>
          <td><div class="t-svc" style="min-width:150px"><?= brand_tile($o, 'xs') ?><b class="small"><?= e(str_limit($o['title'], 22)) ?></b></div></td>
          <td class="num small"><?= num($o['price'] - $o['discount']) ?></td>
          <td><?= status_badge($o['status']) ?></td>
          <td class="muted small nowrap"><?= jdate('H:i', $o['created_at']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

  <div class="card span-3 keep">
    <div class="card-head"><h3><?= icon('flame') ?> محبوب‌ترین خدمات</h3></div>
    <?php foreach ($top as $i => $t): ?>
      <div class="list-item"><span class="rank r<?= $i + 1 ?>"><?= fa($i + 1) ?></span><?= brand_tile($t, 'sm') ?><div style="min-width:0"><b class="small" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:block"><?= e($t['title']) ?></b><small><?= num($t['cnt']) ?> سفارش</small></div></div>
    <?php endforeach; ?>
    <?php if (!$top): ?><?= partial('empty', ['icon' => 'flame', 'title' => 'هنوز فروشی ثبت نشده']) ?><?php endif; ?>
  </div>

  <div class="span-3 keep stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('cpu') ?> وضعیت سیستم</h3></div>
      <div class="card-body" style="padding-top:8px;padding-bottom:8px">
        <div class="sys-row"><span><?= icon('server') ?> وضعیت سرور</span><?= badge('آنلاین', 'success') ?></div>
        <div class="sys-row"><span><?= icon('database') ?> دیتابیس</span><b class="small"><?= num(round($sys['db_size'] / 1048576, 1), 1) ?> MB</b></div>
        <?php if ($sys['disk_used_pct'] !== null): ?><div class="sys-row"><span><?= icon('layers') ?> فضای دیسک</span><b class="small"><?= fa($sys['disk_used_pct']) ?>٪</b></div><?php endif; ?>
        <div class="sys-row"><span><?= icon('refresh') ?> اتوماسیون</span><b class="small"><?= $sys['cron'] ? time_ago(date('Y-m-d H:i:s', $sys['cron'])) : 'اجرا نشده' ?></b></div>
        <div class="sys-row"><span><?= icon('code') ?> PHP</span><b class="small ltr"><?= e($sys['php']) ?></b></div>
        <?php foreach ($providers as $p): ?>
          <div class="sys-row"><span><?= icon('link') ?> <?= e($p['name']) ?></span><?= $p['last_error'] ? badge('خطا', 'danger') : ($p['balance'] !== null ? '<b class="small ltr">' . e(number_format((float)$p['balance'], 2) . ' ' . $p['currency']) . '</b>' : badge('—', 'muted')) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('zap') ?> دسترسی سریع</h3>
      <div class="quick-grid">
        <a href="<?= url('admin/services/create') ?>" class="quick q1"><?= icon('plus') ?> محصول جدید</a>
        <a href="<?= url('admin/stock') ?>" class="quick q2"><?= icon('database') ?> افزایش موجودی</a>
        <a href="<?= url('admin/coupons') ?>" class="quick q3"><?= icon('tag') ?> کد تخفیف</a>
        <a href="<?= url('admin/settings') ?>" class="quick q4"><?= icon('settings') ?> تنظیمات</a>
      </div>
    </div>
  </div>
</div>

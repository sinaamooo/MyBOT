<div class="welcome">
  <div>
    <h2>سلام <?= e($u['first_name'] ?: user_name($u)) ?> عزیز 👋</h2>
    <p>امروز <?= jdate('l j F Y') ?> — به پنل کاربری <?= e(site_name()) ?> خوش آمدید.</p>
    <div class="row mt-2" style="flex-wrap:wrap">
      <a href="<?= url('dashboard/new-order') ?>" class="btn btn-white"><?= icon('plus') ?> ثبت سفارش جدید</a>
      <a href="<?= url('services') ?>" class="btn btn-glass"><?= icon('grid') ?> مشاهده خدمات</a>
    </div>
  </div>
  <div class="wl-balance">
    <small>موجودی کیف پول</small>
    <b><?= num($u['balance']) ?></b> <span class="unit"><?= e(setting('currency', 'تومان')) ?></span>
    <a href="<?= url('dashboard/wallet') ?>" class="btn btn-glass btn-sm btn-block mt-1"><?= icon('plus') ?> شارژ کیف پول</a>
  </div>
</div>

<?php if ((int)$stats['unpaid'] > 0): ?>
  <div class="alert alert-warning mb-3"><?= icon('hourglass') ?><div><b><?= fa($stats['unpaid']) ?> سفارش در انتظار پرداخت دارید.</b> <a href="<?= url('dashboard/orders', ['status' => 'unpaid']) ?>"><u>مشاهده و پرداخت</u></a></div></div>
<?php endif; ?>
<?php if ($tickets): ?>
  <div class="alert alert-info mb-3"><?= icon('message') ?><div><b><?= fa($tickets) ?> تیکت شما پاسخ داده شده است.</b> <a href="<?= url('dashboard/tickets') ?>"><u>مشاهده</u></a></div></div>
<?php endif; ?>

<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">کل سفارش‌ها</div><div class="st-value"><?= num($stats['total']) ?></div><div class="st-foot">از ابتدای عضویت</div></div><span class="st-ic"><?= icon('bag') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">سفارش‌های فعال</div><div class="st-value"><?= num($stats['active']) ?></div><div class="st-foot">در حال انجام</div></div><span class="st-ic"><?= icon('activity') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">تکمیل شده</div><div class="st-value"><?= num($stats['completed']) ?></div><div class="st-foot">با موفقیت تحویل شد</div></div><span class="st-ic"><?= icon('check-circle') ?></span></div>
  <div class="stat s-blue"><div><div class="st-label">مجموع خرید</div><div class="st-value"><?= short_num((int)$u['total_spent']) ?> <small><?= e(setting('currency', 'تومان')) ?></small></div><div class="st-foot"><?= money_text($u['total_spent']) ?></div></div><span class="st-ic"><?= icon('wallet') ?></span></div>
</div>

<div class="dash-grid">
  <div class="card span-8">
    <div class="card-head"><h3><?= icon('chart') ?> فعالیت ۱۴ روز اخیر</h3>
      <div class="legend"><span><i style="background:#38BDF8"></i>تعداد سفارش</span><span><i style="background:#8B5CF6"></i>مبلغ خرید</span></div></div>
    <div class="chart-box sm"><canvas data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>"></canvas></div>
  </div>
  <div class="card span-4">
    <div class="card-head"><h3><?= icon('zap') ?> دسترسی سریع</h3></div>
    <div class="card-body">
      <div class="quick-grid">
        <a href="<?= url('dashboard/new-order') ?>" class="quick q1"><?= icon('plus') ?> سفارش جدید</a>
        <a href="<?= url('dashboard/wallet') ?>" class="quick q2"><?= icon('wallet') ?> شارژ کیف پول</a>
        <a href="<?= url('dashboard/tickets/new') ?>" class="quick q3"><?= icon('message') ?> تیکت جدید</a>
        <a href="<?= url('dashboard/api') ?>" class="quick q4"><?= icon('code') ?> وب‌سرویس</a>
      </div>
      <div class="sys-row mt-2"><span><?= icon('user') ?> عضویت از</span><b><?= jdate('j F Y', $u['created_at']) ?></b></div>
      <div class="sys-row"><span><?= icon('clock') ?> آخرین ورود</span><b><?= $u['last_login_at'] ? time_ago($u['last_login_at']) : '—' ?></b></div>
    </div>
  </div>

  <div class="card span-12">
    <div class="card-head"><h3><?= icon('bag') ?> آخرین سفارش‌ها</h3><a href="<?= url('dashboard/orders') ?>" class="btn btn-ghost btn-sm">مشاهده همه <?= icon('arrow-left') ?></a></div>
    <?php if ($recent): ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>شماره</th><th>سرویس</th><th>لینک</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
        <tbody><?php foreach ($recent as $o): ?><?= partial('order_row', ['o' => $o]) ?><?php endforeach; ?></tbody>
      </table></div>
    <?php else: ?>
      <?= partial('empty', ['icon' => 'bag', 'title' => 'هنوز سفارشی ثبت نکرده‌اید', 'text' => 'اولین سفارش خود را همین حالا ثبت کنید.', 'action' => '<a href="' . url('dashboard/new-order') . '" class="btn btn-primary">' . icon('plus') . ' ثبت سفارش</a>']) ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($suggest): ?>
  <h3 class="mt-4 mb-2" style="display:flex;gap:8px;align-items:center"><?= icon('flame') ?> پیشنهاد برای شما</h3>
  <div class="svc-grid"><?php foreach ($suggest as $s): ?><?= partial('service_card', ['s' => $s, 'favs' => $favs]) ?><?php endforeach; ?></div>
  <?= partial('quick_add') ?>
<?php endif; ?>

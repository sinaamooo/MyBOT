<?= partial('page_head', ['icon' => 'wallet', 'h' => 'پرداخت‌ها', 'sub' => 'تراکنش‌های درگاه، فیش‌های کارت به کارت و شارژ کیف پول',
  'actions' => '<a href="' . url('admin/transactions') . '" class="btn btn-soft">' . icon('list') . ' تراکنش‌های کیف پول</a>']) ?>
<div class="stats">
  <div class="stat s-green"><div><div class="st-label">دریافتی امروز</div><div class="st-value"><?= short_num((int)$k['today']) ?></div><div class="st-foot"><?= money_text($k['today']) ?></div></div><span class="st-ic"><?= icon('coins') ?></span></div>
  <div class="stat s-violet"><div><div class="st-label">دریافتی این ماه</div><div class="st-value"><?= short_num((int)$k['month_']) ?></div><div class="st-foot"><?= money_text($k['month_']) ?></div></div><span class="st-ic"><?= icon('trending-up') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">در انتظار بررسی</div><div class="st-value"><?= num($k['review']) ?></div><div class="st-foot">فیش کارت به کارت</div></div><span class="st-ic"><?= icon('receipt') ?></span></div>
  <div class="stat s-blue"><div><div class="st-label">پرداخت‌های موفق</div><div class="st-value"><?= num($k['paid_count']) ?></div><div class="st-foot">از <?= num($k['total']) ?> تراکنش</div></div><span class="st-ic"><?= icon('check-circle') ?></span></div>
</div>
<div class="card">
  <form class="toolbar" method="get" action="<?= url('admin/payments') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="admin/payments"><?php endif; ?>
    <div class="input-wrap grow"><?= icon('search') ?><input class="input" name="q" value="<?= e(input('q', '')) ?>" placeholder="شماره، کد پیگیری یا ایمیل…"></div>
    <select class="select w-sm" name="status"><option value="">همه وضعیت‌ها</option><?php foreach (payment_statuses() as $kk => [$l]): ?><option value="<?= $kk ?>" <?= $status === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <select class="select w-sm" name="gateway"><option value="">همه روش‌ها</option><?php foreach (Payments::GATEWAYS as $kk => $l): ?><option value="<?= $kk ?>" <?= input('gateway') === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn btn-soft"><?= icon('filter') ?> فیلتر</button>
  </form>
  <?php if ($page['items']): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>کاربر</th><th>مبلغ</th><th>روش</th><th>نوع</th><th>کد پیگیری</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
    <tbody><?php foreach ($page['items'] as $p): ?>
      <tr>
        <td class="num">#<?= fa($p['id']) ?></td>
        <td><a class="t-user" href="<?= url('admin/users/' . $p['uid']) ?>"><?= avatar(['id' => $p['uid'], 'first_name' => $p['first_name'], 'last_name' => $p['last_name'], 'avatar' => $p['avatar']], 'sm') ?><div><b><?= e(trim($p['first_name'] . ' ' . $p['last_name'])) ?></b><small class="ltr"><?= e($p['email']) ?></small></div></a></td>
        <td class="num"><?= money($p['amount']) ?></td>
        <td class="small"><?= e(Payments::GATEWAYS[$p['gateway']] ?? $p['gateway']) ?></td>
        <td class="small"><?= $p['purpose'] === 'order' ? 'پرداخت سفارش' : 'شارژ کیف پول' ?></td>
        <td class="small ltr"><?= e($p['ref_id'] ?: $p['tracking_code'] ?: '—') ?><?= $p['card_pan'] ? '<div class="muted">' . e($p['card_pan']) . '</div>' : '' ?></td>
        <td><?= status_badge($p['status'], 'payment') ?><?php if ($p['admin_note']): ?><div class="small muted"><?= e(str_limit($p['admin_note'], 30)) ?></div><?php endif; ?></td>
        <td class="muted small nowrap"><?= jdate('Y/m/d H:i', $p['created_at']) ?></td>
        <td><?php if ($p['status'] === 'review'): ?><div class="actions">
          <?php if ($p['receipt']): ?><a href="<?= e(upload_url($p['receipt'])) ?>" target="_blank" class="btn btn-ghost btn-icon btn-xs" title="رسید"><?= icon('image') ?></a><?php endif; ?>
          <form method="post" action="<?= url('admin/payments/' . $p['id'] . '/approve') ?>" data-confirm="واریز <?= e(money_text($p['amount'])) ?> تایید و کیف پول شارژ شود؟"><?= csrf_field() ?><button class="btn btn-success btn-xs"><?= icon('check') ?> تایید</button></form>
          <form method="post" action="<?= url('admin/payments/' . $p['id'] . '/reject') ?>" data-confirm="این فیش رد شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-xs"><?= icon('x') ?> رد</button></form>
        </div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="table-foot"><span><?= num($page['total']) ?> پرداخت</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?><?= partial('empty', ['icon' => 'wallet', 'title' => 'پرداختی پیدا نشد']) ?><?php endif; ?>
</div>

<?= partial('page_head', ['icon' => 'bag', 'h' => 'سفارش‌های من', 'sub' => 'وضعیت همه سفارش‌ها را لحظه‌ای پیگیری کنید', 'actions' => '<a href="' . url('dashboard/new-order') . '" class="btn btn-primary">' . icon('plus') . ' سفارش جدید</a>']) ?>
<div class="card">
  <div class="toolbar">
    <div class="tabs">
      <?php foreach (['' => ['همه', $counts['all_']], 'active' => ['در حال انجام', $counts['active']], 'unpaid' => ['در انتظار پرداخت', $counts['unpaid']], 'completed' => ['تکمیل شده', $counts['completed']], 'canceled' => ['لغو شده', null]] as $k => [$label, $n]): ?>
        <a href="<?= url('dashboard/orders', $k ? ['status' => $k] : []) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $label ?><?php if ($n): ?> <span class="count"><?= fa($n) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
    <form class="input-wrap grow" method="get" action="<?= url('dashboard/orders') ?>">
      <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="dashboard/orders"><?php endif; ?>
      <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
      <?= icon('search') ?><input class="input" name="q" value="<?= e(input('q', '')) ?>" placeholder="شماره سفارش، لینک یا نام سرویس…">
    </form>
  </div>
  <?php if ($page['items']): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>شماره</th><th>سرویس</th><th>لینک</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ ثبت</th><th></th></tr></thead>
      <tbody><?php foreach ($page['items'] as $o): ?><?= partial('order_row', ['o' => $o]) ?><?php endforeach; ?></tbody>
    </table></div>
    <div class="table-foot"><span>نمایش <?= num(count($page['items'])) ?> از <?= num($page['total']) ?> سفارش</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?>
    <?= partial('empty', ['icon' => 'bag', 'title' => 'سفارشی پیدا نشد', 'action' => '<a href="' . url('dashboard/new-order') . '" class="btn btn-primary">' . icon('plus') . ' ثبت سفارش جدید</a>']) ?>
  <?php endif; ?>
</div>

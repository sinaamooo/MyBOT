<?php
$flow = ['pending' => 'در صف انجام', 'processing' => 'در حال پردازش', 'in_progress' => 'در حال انجام', 'completed' => 'تکمیل شده'];
$order = array_keys($flow);
$pos = array_search($o['status'], $order, true);
$paid = (int)$o['price'] - (int)$o['discount'];
$progress = $o['status'] === 'completed' ? 100 : ($o['remains'] !== null && $o['quantity'] > 0 ? max(0, min(100, round(100 - $o['remains'] * 100 / $o['quantity']))) : null);
?>
<?= partial('page_head', ['icon' => 'receipt', 'h' => 'سفارش #' . fa($o['id']), 'sub' => 'ثبت شده در ' . jdate('l j F Y — H:i', $o['created_at']),
  'actions' => '<a href="' . url('dashboard/tickets/new', ['order' => $o['id']]) . '" class="btn btn-outline">' . icon('message') . ' پیگیری با تیکت</a><a href="' . url('dashboard/orders') . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>

<?php if ($o['status'] === 'unpaid'): ?>
  <div class="card card-pad mb-3" style="border-color:rgba(37,99,235,.35);background:linear-gradient(135deg,var(--info-soft),var(--card))">
    <div class="row-between">
      <div class="row"><span class="btile btile-md" style="--c1:#60A5FA;--c2:#2563EB"><?= icon('hourglass') ?></span>
        <div><b>این سفارش هنوز پرداخت نشده است</b><div class="muted small">مبلغ قابل پرداخت: <?= money($paid) ?> — موجودی شما: <?= money($u['balance']) ?></div></div></div>
      <div class="row" style="flex-wrap:wrap">
        <form method="post" action="<?= url('dashboard/orders/' . $o['id'] . '/pay') ?>" class="row"><?= csrf_field() ?>
          <?php if ((int)$u['balance'] < $paid && ($online || $testGateway)): ?>
            <select name="method" class="select input-sm" style="width:auto"><?php if ($online): ?><option value="online">پرداخت آنلاین</option><?php endif; ?><?php if ($testGateway): ?><option value="test">درگاه آزمایشی</option><?php endif; ?></select>
          <?php endif; ?>
          <button class="btn btn-primary"><?= icon('card') ?> پرداخت سفارش</button>
        </form>
        <form method="post" action="<?= url('dashboard/orders/' . $o['id'] . '/cancel') ?>" data-confirm="این سفارش لغو شود؟"><?= csrf_field() ?><button class="btn btn-danger"><?= icon('x') ?> لغو</button></form>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="dash-grid">
  <div class="card span-8">
    <div class="card-head"><h3><?= brand_tile($o, 'sm') ?> <?= e($o['title']) ?></h3><?= status_badge($o['status']) ?></div>
    <div class="card-body">
      <?php if ($progress !== null): ?>
        <div class="row-between mb-1"><span class="muted small">پیشرفت سفارش</span><b><?= fa($progress) ?>٪</b></div>
        <div class="progress mb-3"><i style="width:<?= $progress ?>%"></i></div>
      <?php endif; ?>
      <div class="kv">
        <div><span>دسته‌بندی</span><b><?= e($o['category_name']) ?></b></div>
        <div><span>لینک / آیدی</span><b class="ltr"><?= e($o['link']) ?></b></div>
        <div><span>تعداد سفارش</span><b><?= num($o['quantity']) ?> عدد</b></div>
        <div><span>تعداد شروع</span><b><?= $o['start_count'] !== null ? num($o['start_count']) : '—' ?></b></div>
        <div><span>باقی‌مانده</span><b><?= $o['remains'] !== null ? num($o['remains']) : '—' ?></b></div>
        <div><span>زمان تقریبی تحویل</span><b><?= e($o['delivery_time'] ?: '—') ?></b></div>
        <?php if ($o['admin_note']): ?><div><span>توضیحات</span><b><?= e($o['admin_note']) ?></b></div><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="span-4 stack" style="gap:20px">
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('receipt') ?> جزئیات مالی</h3>
      <div class="kv">
        <div><span>مبلغ سفارش</span><b><?= money($o['price']) ?></b></div>
        <?php if ($o['discount']): ?><div><span>تخفیف</span><b style="color:var(--success)">− <?= money($o['discount']) ?></b></div><?php endif; ?>
        <div><span>مبلغ پرداختی</span><b><?= money($paid) ?></b></div>
        <?php if ($o['refunded']): ?><div><span>بازگشت به کیف پول</span><b style="color:var(--info)"><?= money($o['refunded']) ?></b></div><?php endif; ?>
      </div>
    </div>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('activity') ?> مراحل انجام</h3>
      <div class="timeline">
        <?php if (in_array($o['status'], ['canceled', 'refunded', 'partial', 'unpaid'], true)): ?>
          <div class="tl-item done"><b>ثبت سفارش</b><small><?= jdate('Y/m/d H:i', $o['created_at']) ?></small></div>
          <div class="tl-item now"><b><?= e(order_statuses()[$o['status']][0]) ?></b><small><?= $o['updated_at'] ? jdate('Y/m/d H:i', $o['updated_at']) : '' ?></small></div>
        <?php else: foreach ($flow as $k => $label): $i = array_search($k, $order, true); ?>
          <div class="tl-item <?= $pos !== false && $i < $pos ? 'done' : ($i === $pos ? ($k === 'completed' ? 'done' : 'now') : '') ?>"><b><?= $label ?></b><?php if ($k === 'completed' && $o['completed_at']): ?><small><?= jdate('Y/m/d H:i', $o['completed_at']) ?></small><?php endif; ?></div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</div>

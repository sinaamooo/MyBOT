<?php $cur = e(setting('currency', 'تومان')); $profit = (int)$sum['revenue'] - (int)$sum['cost']; ?>
<?= partial('page_head', ['icon' => 'chart', 'h' => 'گزارشات', 'sub' => 'فروش، سود و عملکرد سرویس‌ها',
  'actions' => '<div class="tabs">' . implode('', array_map(fn($d, $l) => '<a href="' . url('admin/reports', ['days' => $d]) . '" class="' . ($days === $d ? 'active' : '') . '">' . $l . '</a>', [7, 30, 90, 365], ['۷ روز', '۳۰ روز', '۹۰ روز', 'یک سال'])) . '</div>']) ?>
<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">فروش خالص</div><div class="st-value"><?= short_num((int)$sum['revenue']) ?></div><div class="st-foot"><?= money_text($sum['revenue']) ?></div></div><span class="st-ic"><?= icon('trending-up') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">سود تخمینی</div><div class="st-value"><?= short_num($profit) ?></div><div class="st-foot">حاشیه سود ٪<?= fa($sum['revenue'] > 0 ? round($profit * 100 / $sum['revenue']) : 0) ?></div></div><span class="st-ic"><?= icon('coins') ?></span></div>
  <div class="stat s-blue"><div><div class="st-label">سفارش‌ها</div><div class="st-value"><?= num($sum['orders']) ?></div><div class="st-foot"><?= num($sum['buyers']) ?> خریدار</div></div><span class="st-ic"><?= icon('cart') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">شارژ کیف پول</div><div class="st-value"><?= short_num($deposits) ?></div><div class="st-foot">تخفیف: <?= money_text($sum['discounts']) ?> · بازگشتی: <?= money_text($sum['refunds']) ?></div></div><span class="st-ic"><?= icon('wallet') ?></span></div>
</div>
<div class="dash-grid">
  <div class="card span-8"><div class="card-head"><h3><?= icon('chart') ?> درآمد و سود روزانه</h3><div class="legend"><span><i style="background:#8B5CF6"></i>درآمد</span><span><i style="background:#10B981"></i>سود</span></div></div>
    <div class="chart-box"><canvas data-chart="<?= e(json_encode($chart, JSON_UNESCAPED_UNICODE)) ?>"></canvas></div></div>
  <div class="card span-4"><div class="card-head"><h3><?= icon('pie') ?> سهم دسته‌بندی‌ها</h3></div>
    <div class="chart-box"><?php if ($byCat): ?><canvas data-chart="<?= e(json_encode($pie, JSON_UNESCAPED_UNICODE)) ?>"></canvas><?php else: ?><?= partial('empty', ['icon' => 'pie', 'title' => 'داده‌ای نیست']) ?><?php endif; ?></div></div>
  <div class="card span-8"><div class="card-head"><h3><?= icon('award') ?> پرفروش‌ترین سرویس‌ها</h3></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>سرویس</th><th>سفارش</th><th>تعداد کل</th><th>فروش</th><th>سود</th></tr></thead>
      <tbody><?php foreach ($topServices as $i => $s): ?>
        <tr><td><span class="rank r<?= $i + 1 ?>"><?= fa($i + 1) ?></span></td><td><div class="t-svc"><?= brand_tile($s, 'sm') ?><b><?= e($s['title']) ?></b></div></td><td class="num"><?= num($s['cnt']) ?></td><td class="num"><?= short_num((int)$s['qty']) ?></td><td class="num"><?= money($s['amt']) ?></td><td class="num" style="color:var(--success)"><?= num($s['profit']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php if (!$topServices): ?><?= partial('empty', ['icon' => 'award', 'title' => 'فروشی در این بازه نیست']) ?><?php endif; ?>
  </div>
  <div class="card span-4"><div class="card-head"><h3><?= icon('crown') ?> مشتریان برتر</h3></div>
    <?php foreach ($topUsers as $i => $u): ?><a class="list-item" href="<?= url('admin/users/' . $u['id']) ?>"><span class="rank r<?= $i + 1 ?>"><?= fa($i + 1) ?></span><?= avatar($u, 'sm') ?><div><b class="small"><?= e(user_name($u)) ?></b><small><?= num($u['cnt']) ?> سفارش</small></div><b class="li-end small"><?= short_num((int)$u['amt']) ?></b></a><?php endforeach; ?>
    <?php if (!$topUsers): ?><?= partial('empty', ['icon' => 'users', 'title' => 'داده‌ای نیست']) ?><?php endif; ?>
  </div>
</div>

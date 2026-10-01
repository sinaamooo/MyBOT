<?= partial('page_head', ['icon' => 'list', 'h' => 'تراکنش‌های کیف پول', 'sub' => 'تمام تغییرات موجودی کاربران', 'actions' => '<a href="' . url('admin/payments') . '" class="btn btn-soft">' . icon('arrow-right') . ' پرداخت‌ها</a>']) ?>
<div class="card">
  <div class="toolbar"><div class="tabs">
    <a href="<?= url('admin/transactions') ?>" class="<?= !input('type') ? 'active' : '' ?>">همه</a>
    <?php foreach (Wallet::TYPES as $k => [$l]): ?><a href="<?= url('admin/transactions', ['type' => $k]) ?>" class="<?= input('type') === $k ? 'active' : '' ?>"><?= $l ?></a><?php endforeach; ?>
  </div></div>
  <?php if ($page['items']): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>کاربر</th><th>نوع</th><th>شرح</th><th>مبلغ</th><th>مانده</th><th>تاریخ</th></tr></thead>
    <tbody><?php foreach ($page['items'] as $t): [$tl, $tc] = Wallet::TYPES[$t['type']] ?? [$t['type'], 'muted']; ?>
      <tr><td class="num muted">#<?= fa($t['id']) ?></td>
        <td><a class="t-user" href="<?= url('admin/users/' . $t['uid']) ?>"><?= avatar(['id' => $t['uid'], 'first_name' => $t['first_name'], 'last_name' => $t['last_name'], 'avatar' => $t['avatar']], 'sm') ?><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b></a></td>
        <td><?= badge($tl, $tc) ?></td><td class="small"><?= e($t['description']) ?></td>
        <td class="num" style="color:var(--<?= $t['amount'] >= 0 ? 'success' : 'danger' ?>)"><span class="ltr"><?= $t['amount'] >= 0 ? '+' : '−' ?><?= num(abs($t['amount'])) ?></span></td>
        <td class="num muted"><?= num($t['balance_after']) ?></td><td class="muted small nowrap"><?= jdate('Y/m/d H:i', $t['created_at']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="table-foot"><span><?= num($page['total']) ?> تراکنش</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?><?= partial('empty', ['icon' => 'list', 'title' => 'تراکنشی وجود ندارد']) ?><?php endif; ?>
</div>

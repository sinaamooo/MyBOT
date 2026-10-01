<?php $prio = ['high' => ['فوری', 'danger'], 'normal' => ['معمولی', 'muted'], 'low' => ['کم', 'muted']]; ?>
<?= partial('page_head', ['icon' => 'message', 'h' => 'تیکت‌ها', 'sub' => 'درخواست‌های پشتیبانی کاربران']) ?>
<div class="card">
  <div class="toolbar">
    <div class="tabs">
      <?php foreach (['waiting' => ['منتظر پاسخ', $counts['waiting']], 'answered' => ['پاسخ داده شده', $counts['answered']], 'closed' => ['بسته شده', $counts['closed']], 'all' => ['همه', $counts['total']]] as $k => [$l, $n]): ?>
        <a href="<?= url('admin/tickets', ['status' => $k]) ?>" class="<?= $status === $k ? 'active' : '' ?>"><?= $l ?> <span class="count"><?= fa((int)$n) ?></span></a>
      <?php endforeach; ?>
    </div>
    <form class="input-wrap grow" method="get" action="<?= url('admin/tickets') ?>"><?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="admin/tickets"><?php endif; ?><input type="hidden" name="status" value="<?= e($status) ?>"><?= icon('search') ?><input class="input" name="q" value="<?= e(input('q', '')) ?>" placeholder="موضوع، شماره تیکت یا ایمیل…"></form>
  </div>
  <?php if ($page['items']): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>#</th><th>موضوع</th><th>کاربر</th><th>دپارتمان</th><th>اولویت</th><th>وضعیت</th><th>بروزرسانی</th><th></th></tr></thead>
    <tbody><?php foreach ($page['items'] as $t): ?>
      <tr>
        <td class="num">#<?= fa($t['id']) ?></td>
        <td><a href="<?= url('admin/tickets/' . $t['id']) ?>"><b><?= e($t['subject']) ?></b></a><div class="small muted"><?= num($t['msgs']) ?> پیام</div></td>
        <td><div class="t-user"><?= avatar(['id' => $t['uid'], 'first_name' => $t['first_name'], 'last_name' => $t['last_name'], 'avatar' => $t['avatar']], 'sm') ?><div><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b><small class="ltr"><?= e($t['email']) ?></small></div></div></td>
        <td class="small"><?= e(UserController::departments()[$t['department']] ?? $t['department']) ?></td>
        <td><?= badge(...$prio[$t['priority']] ?? ['—', 'muted']) ?></td>
        <td><?= status_badge($t['status'], 'ticket') ?></td>
        <td class="muted small nowrap"><?= time_ago($t['updated_at']) ?></td>
        <td><a href="<?= url('admin/tickets/' . $t['id']) ?>" class="btn btn-soft btn-xs"><?= icon('message') ?> پاسخ</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="table-foot"><span><?= num($page['total']) ?> تیکت</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?><?= partial('empty', ['icon' => 'inbox', 'title' => 'تیکتی در این بخش نیست', 'text' => 'همه درخواست‌ها پاسخ داده شده‌اند 🎉']) ?><?php endif; ?>
</div>

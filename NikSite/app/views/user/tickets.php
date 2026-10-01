<?= partial('page_head', ['icon' => 'message', 'h' => 'تیکت‌های پشتیبانی', 'sub' => 'سوال یا مشکلی دارید؟ ما این‌جاییم', 'actions' => '<a href="' . url('dashboard/tickets/new') . '" class="btn btn-primary">' . icon('plus') . ' تیکت جدید</a>']) ?>
<div class="card">
  <?php if ($page['items']): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>موضوع</th><th>دپارتمان</th><th>وضعیت</th><th>آخرین بروزرسانی</th><th></th></tr></thead>
      <tbody><?php foreach ($page['items'] as $t): ?>
        <tr>
          <td class="num">#<?= fa($t['id']) ?></td>
          <td><a href="<?= url('dashboard/tickets/' . $t['id']) ?>"><b><?= e($t['subject']) ?></b></a><div class="small muted"><?= num($t['msgs']) ?> پیام</div></td>
          <td><?= e(UserController::departments()[$t['department']] ?? $t['department']) ?></td>
          <td><?= status_badge($t['status'], 'ticket') ?></td>
          <td class="muted nowrap"><?= time_ago($t['updated_at']) ?></td>
          <td><div class="actions"><a href="<?= url('dashboard/tickets/' . $t['id']) ?>" class="btn btn-soft btn-xs"><?= icon('eye') ?> مشاهده</a></div></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <div class="table-foot"><span><?= num($page['total']) ?> تیکت</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?>
    <?= partial('empty', ['icon' => 'message', 'title' => 'تیکتی ندارید', 'text' => 'هر سوالی داشتید، یک تیکت جدید ثبت کنید.', 'action' => '<a href="' . url('dashboard/tickets/new') . '" class="btn btn-primary">' . icon('plus') . ' تیکت جدید</a>']) ?>
  <?php endif; ?>
</div>

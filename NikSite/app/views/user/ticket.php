<?= partial('page_head', ['icon' => 'message', 'h' => $t['subject'], 'sub' => 'تیکت #' . fa($t['id']) . ' — ' . (UserController::departments()[$t['department']] ?? ''),
  'actions' => status_badge($t['status'], 'ticket') . ($t['status'] !== 'closed' ? '<form method="post" action="' . url('dashboard/tickets/' . $t['id'] . '/close') . '" data-confirm="تیکت بسته شود؟">' . csrf_field() . '<button class="btn btn-outline btn-sm">' . icon('lock') . ' بستن تیکت</button></form>' : '')]) ?>
<div class="card" style="max-width:960px">
  <?php if ($t['order_id']): ?><div class="toolbar"><span class="chip"><?= icon('bag') ?> سفارش مرتبط: <a href="<?= url('dashboard/orders/' . $t['order_id']) ?>">#<?= fa($t['order_id']) ?></a></span></div><?php endif; ?>
  <div class="thread">
    <?php foreach ($msgs as $m): $me = !$m['is_admin']; ?>
      <div class="msg <?= $me ? 'me' : 'them' ?>">
        <?= $me ? avatar(['id' => $m['uid'], 'first_name' => $m['first_name'], 'last_name' => $m['last_name'], 'avatar' => $m['avatar']], 'sm') : '<span class="avatar avatar-sm" style="--h:255">' . icon('headset') . '</span>' ?>
        <div>
          <div class="bubble"><?= e($m['message']) ?><?php if ($m['attachment']): ?><br><a class="attach" href="<?= e(upload_url($m['attachment'])) ?>" target="_blank"><?= icon('paperclip') ?> پیوست</a><?php endif; ?></div>
          <div class="m-meta"><b><?= $me ? 'شما' : 'پشتیبانی ' . e(site_name()) ?></b><span><?= jdate('Y/m/d H:i', $m['created_at']) ?></span></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($t['status'] !== 'closed'): ?>
    <form class="card-foot" method="post" action="<?= url('dashboard/tickets/' . $t['id'] . '/reply') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <textarea class="textarea mb-1" name="message" rows="3" required placeholder="پاسخ خود را بنویسید…"></textarea>
      <div class="row-between"><input type="file" name="attachment" accept="image/*,.pdf" class="small"><button class="btn btn-primary"><?= icon('send') ?> ارسال پاسخ</button></div>
    </form>
  <?php else: ?>
    <div class="card-foot center muted"><?= icon('lock') ?> این تیکت بسته شده است. برای سوال جدید، تیکت جدید ثبت کنید.</div>
  <?php endif; ?>
</div>

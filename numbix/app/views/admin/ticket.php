<?= partial('page_head', ['icon' => 'message', 'h' => $t['subject'], 'sub' => 'تیکت #' . fa($t['id']) . ' — ' . (UserController::departments()[$t['department']] ?? '') . ' — ' . jdate('j F Y H:i', $t['created_at']),
  'actions' => status_badge($t['status'], 'ticket') . '<a href="' . url('admin/tickets') . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>
<div class="dash-grid">
  <div class="card span-8">
    <div class="thread">
      <?php foreach ($msgs as $m): $admin = (bool)$m['is_admin']; ?>
        <div class="msg <?= $admin ? 'them' : 'me' ?>">
          <?= avatar(['id' => $m['uid'], 'first_name' => $m['first_name'], 'last_name' => $m['last_name'], 'avatar' => $m['avatar']], 'sm') ?>
          <div>
            <div class="bubble"><?= e($m['message']) ?><?php if ($m['attachment']): ?><br><a class="attach" href="<?= e(upload_url($m['attachment'])) ?>" target="_blank"><?= icon('paperclip') ?> پیوست</a><?php endif; ?></div>
            <div class="m-meta"><b><?= e(trim($m['first_name'] . ' ' . $m['last_name'])) ?><?= $admin ? ' (پشتیبانی)' : '' ?></b><span><?= jdate('Y/m/d H:i', $m['created_at']) ?></span></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <form class="card-foot" method="post" action="<?= url('admin/tickets/' . $t['id'] . '/reply') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <?php if ($replies): ?><div class="presets mb-1" style="margin-top:0"><?php foreach ($replies as $r): ?><button type="button" onclick="var a=document.getElementById('reply');a.value=a.value?a.value+'\n'+this.dataset.t:this.dataset.t;a.focus()" data-t="<?= e($r) ?>"><?= e(str_limit($r, 34)) ?></button><?php endforeach; ?></div><?php endif; ?>
      <textarea class="textarea mb-1" id="reply" name="message" rows="4" required placeholder="پاسخ خود را بنویسید…"></textarea>
      <div class="row-between">
        <div class="row"><input type="file" name="attachment" accept="image/*,.pdf" class="small"><label class="check small"><input type="checkbox" name="close" value="1"><span class="box"><?= icon('check') ?></span>بستن تیکت پس از پاسخ</label></div>
        <div class="row"><button class="btn btn-outline" name="next" value="list">ارسال و بازگشت</button><button class="btn btn-primary"><?= icon('send') ?> ارسال پاسخ</button></div>
      </div>
    </form>
  </div>
  <div class="span-4 stack" style="gap:20px">
    <div class="card card-pad">
      <a href="<?= url('admin/users/' . $t['uid']) ?>" class="row mb-2"><?= avatar(['id' => $t['uid'], 'first_name' => $t['first_name'], 'last_name' => $t['last_name'], 'avatar' => $t['avatar']], 'lg') ?><div><b><?= e(trim($t['first_name'] . ' ' . $t['last_name'])) ?></b><div class="small muted ltr"><?= e($t['email'] ?: $t['mobile']) ?></div></div></a>
      <div class="kv"><div><span>موجودی</span><b><?= money($t['balance']) ?></b></div><?php if ($t['order_id']): ?><div><span>سفارش مرتبط</span><b><a href="<?= url('admin/orders/' . $t['order_id']) ?>" style="color:var(--p)">#<?= fa($t['order_id']) ?></a></b></div><?php endif; ?></div>
    </div>
    <form class="card card-pad" method="post" action="<?= url('admin/tickets/' . $t['id'] . '/status') ?>">
      <?= csrf_field() ?>
      <h3 class="card-title mb-2"><?= icon('settings') ?> وضعیت تیکت</h3>
      <select class="select mb-1" name="status"><?php foreach (ticket_statuses() as $k => [$l]): ?><option value="<?= $k ?>" <?= $t['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <select class="select mb-2" name="priority"><?php foreach (['high' => 'فوری', 'normal' => 'معمولی', 'low' => 'کم'] as $k => $l): ?><option value="<?= $k ?>" <?= $t['priority'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
      <button class="btn btn-soft btn-block">ذخیره</button>
    </form>
    <?php if ($orders): ?><div class="card"><div class="card-head"><h3><?= icon('cart') ?> سفارش‌های اخیر کاربر</h3></div>
      <?php foreach ($orders as $o): ?><a class="list-item" href="<?= url('admin/orders/' . $o['id']) ?>"><div><b class="small">#<?= fa($o['id']) ?> — <?= e($o['title']) ?></b><small><?= num($o['quantity']) ?> عدد</small></div><span class="li-end"><?= status_badge($o['status']) ?></span></a><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>

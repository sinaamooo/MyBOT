<?= partial('page_head', ['icon' => 'message', 'h' => 'ارسال تیکت جدید', 'sub' => 'پاسخ تیکت‌ها معمولاً در کمتر از چند ساعت ارسال می‌شود']) ?>
<form class="card" method="post" action="<?= url('dashboard/tickets/new') ?>" enctype="multipart/form-data" style="max-width:860px">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="field"><label class="label">موضوع</label><input class="input" name="subject" required value="<?= e(old('subject')) ?>" placeholder="مثلاً: تاخیر در شروع سفارش"></div>
    <div class="grid g-3" style="gap:14px">
      <div class="field mb-0"><label class="label">دپارتمان</label><select class="select" name="department"><?php foreach (UserController::departments() as $k => $v): ?><option value="<?= $k ?>" <?= old('department') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
      <div class="field mb-0"><label class="label">اولویت</label><select class="select" name="priority"><option value="normal">معمولی</option><option value="high">فوری</option><option value="low">کم</option></select></div>
      <div class="field mb-0"><label class="label">سفارش مرتبط <span class="hint">اختیاری</span></label><select class="select" name="order_id"><option value="">—</option><?php foreach ($orders as $o): ?><option value="<?= $o['id'] ?>" <?= $orderId === (int)$o['id'] ? 'selected' : '' ?>>#<?= fa($o['id']) ?> — <?= e($o['title']) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="field mt-2"><label class="label">متن پیام</label><textarea class="textarea" name="message" rows="7" required placeholder="توضیحات کامل مشکل یا سوال خود را بنویسید…"><?= e(old('message')) ?></textarea></div>
    <div class="field mb-0"><label class="label">پیوست <span class="hint">تصویر یا PDF، حداکثر ۴ مگابایت</span></label><input class="input" type="file" name="attachment" accept="image/*,.pdf" style="padding-top:12px"></div>
  </div>
  <div class="card-foot row" style="justify-content:flex-end"><a href="<?= url('dashboard/tickets') ?>" class="btn btn-outline">انصراف</a><button class="btn btn-primary"><?= icon('send') ?> ارسال تیکت</button></div>
</form>

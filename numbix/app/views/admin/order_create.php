<?= partial('page_head', ['icon' => 'plus', 'h' => 'ثبت سفارش برای کاربر', 'sub' => 'سفارش دستی، هدیه یا ثبت سفارش تلفنی', 'actions' => '<a href="' . url('admin/orders') . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>
<form class="card" method="post" action="<?= url('admin/orders/create') ?>" style="max-width:820px">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="field"><label class="label">کاربر</label><select class="select" name="user_id" required><option value="">— انتخاب کاربر —</option><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= input_int('user') === (int)$u['id'] ? 'selected' : '' ?>>#<?= fa($u['id']) ?> — <?= e(user_name($u)) ?> (<?= e($u['email'] ?: $u['mobile']) ?>) — موجودی <?= money_text($u['balance']) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label class="label">سرویس</label><select class="select" name="service_id" required><option value="">— انتخاب سرویس —</option><?php foreach ($services as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['category_name'] . ' — ' . $s['title']) ?> (<?= money_text($s['price']) ?> / ۱۰۰۰)</option><?php endforeach; ?></select></div>
    <div class="grid g-2" style="gap:14px">
      <div class="field mb-0"><label class="label">لینک</label><input class="input ltr" name="link" required></div>
      <div class="field mb-0"><label class="label">تعداد</label><input class="input" name="qty" inputmode="numeric" required value="1000"></div>
    </div>
    <label class="check mt-2"><input type="checkbox" name="charge" value="1" checked><span class="box"><?= icon('check') ?></span>مبلغ از کیف پول کاربر کسر شود (در غیر این صورت رایگان/هدیه)</label>
  </div>
  <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icon('check') ?> ثبت سفارش</button></div>
</form>

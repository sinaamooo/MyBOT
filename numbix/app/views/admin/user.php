<?= partial('page_head', ['icon' => 'user', 'h' => user_name($u), 'sub' => 'کاربر #' . fa($u['id']) . ' · عضویت ' . jdate('j F Y', $u['created_at']) . ($u['last_ip'] ? ' · IP ' . $u['last_ip'] : ''),
  'actions' => ($u['role'] !== 'admin' ? '<form method="post" action="' . url('admin/users/' . $u['id'] . '/impersonate') . '" data-confirm="وارد حساب این کاربر می‌شوید؟">' . csrf_field() . '<button class="btn btn-outline">' . icon('eye') . ' ورود به حساب کاربر</button></form>' : '')
  . '<a href="' . url('admin/orders/create', ['user' => $u['id']]) . '" class="btn btn-soft">' . icon('plus') . ' ثبت سفارش</a>']) ?>
<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">موجودی کیف پول</div><div class="st-value"><?= num($u['balance']) ?></div><div class="st-foot"><?= e(setting('currency', 'تومان')) ?></div></div><span class="st-ic"><?= icon('wallet') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">مجموع خرید</div><div class="st-value"><?= short_num((int)$stats['spent']) ?></div><div class="st-foot"><?= money_text($stats['spent']) ?></div></div><span class="st-ic"><?= icon('coins') ?></span></div>
  <div class="stat s-blue"><div><div class="st-label">کل سفارش‌ها</div><div class="st-value"><?= num($stats['total']) ?></div><div class="st-foot"><?= num($stats['completed']) ?> تکمیل شده</div></div><span class="st-ic"><?= icon('cart') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">سفارش فعال</div><div class="st-value"><?= num($stats['active']) ?></div></div><span class="st-ic"><?= icon('activity') ?></span></div>
</div>
<div class="dash-grid">
  <div class="span-8 stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('cart') ?> آخرین سفارش‌ها</h3><a href="<?= url('admin/orders', ['user' => $u['id']]) ?>" class="btn btn-ghost btn-sm">همه <?= icon('arrow-left') ?></a></div>
      <?php if ($orders): ?><div class="table-wrap"><table class="table table-compact"><tbody><?php foreach ($orders as $o): ?>
        <tr onclick="location.href='<?= url('admin/orders/' . $o['id']) ?>'" style="cursor:pointer"><td class="num">#<?= fa($o['id']) ?></td><td><div class="t-svc"><?= brand_tile($o, 'xs') ?><b class="small"><?= e($o['title']) ?></b></div></td><td class="num small"><?= num($o['quantity']) ?></td><td class="num"><?= num($o['price'] - $o['discount']) ?></td><td><?= status_badge($o['status']) ?></td><td class="muted small nowrap"><?= jdate('Y/m/d', $o['created_at']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php else: ?><?= partial('empty', ['icon' => 'cart', 'title' => 'سفارشی ندارد']) ?><?php endif; ?>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('list') ?> تراکنش‌های کیف پول</h3></div>
      <?php if ($tx): ?><div class="table-wrap"><table class="table table-compact"><tbody><?php foreach ($tx as $t): [$tl, $tc] = Wallet::TYPES[$t['type']] ?? [$t['type'], 'muted']; ?>
        <tr><td><?= badge($tl, $tc) ?></td><td class="small"><?= e($t['description']) ?></td><td class="num" style="color:var(--<?= $t['amount'] >= 0 ? 'success' : 'danger' ?>)"><span class="ltr"><?= $t['amount'] >= 0 ? '+' : '−' ?><?= num(abs($t['amount'])) ?></span></td><td class="num muted"><?= num($t['balance_after']) ?></td><td class="muted small nowrap"><?= jdate('Y/m/d H:i', $t['created_at']) ?></td></tr>
      <?php endforeach; ?></tbody></table></div><?php else: ?><?= partial('empty', ['icon' => 'wallet', 'title' => 'تراکنشی ندارد']) ?><?php endif; ?>
    </div>
  </div>
  <div class="span-4 stack" style="gap:20px">
    <form class="card card-pad" method="post" action="<?= url('admin/users/' . $u['id'] . '/balance') ?>">
      <?= csrf_field() ?>
      <h3 class="card-title mb-2"><?= icon('wallet') ?> تغییر موجودی</h3>
      <div class="tabs mb-2" style="display:grid;grid-template-columns:1fr 1fr">
        <label class="check" style="justify-content:center;padding:8px"><input type="radio" name="mode" value="add" checked><span class="box"><?= icon('check') ?></span>افزایش</label>
        <label class="check" style="justify-content:center;padding:8px"><input type="radio" name="mode" value="sub"><span class="box"><?= icon('check') ?></span>کاهش</label>
      </div>
      <input class="input mb-1" name="amount" inputmode="numeric" placeholder="مبلغ" required data-money>
      <input class="input mb-2" name="reason" placeholder="علت (در تراکنش کاربر نمایش داده می‌شود)">
      <button class="btn btn-soft btn-block"><?= icon('check') ?> اعمال</button>
    </form>
    <form class="card card-pad" method="post" action="<?= url('admin/users/' . $u['id'] . '/update') ?>">
      <?= csrf_field() ?>
      <h3 class="card-title mb-2"><?= icon('edit') ?> ویرایش اطلاعات</h3>
      <div class="grid g-2" style="gap:10px"><input class="input" name="first_name" value="<?= e($u['first_name']) ?>" placeholder="نام"><input class="input" name="last_name" value="<?= e($u['last_name']) ?>" placeholder="نام خانوادگی"></div>
      <input class="input mt-1 ltr" name="email" value="<?= e($u['email']) ?>" placeholder="ایمیل">
      <input class="input mt-1 ltr" name="mobile" value="<?= e($u['mobile']) ?>" placeholder="موبایل">
      <input class="input mt-1 ltr" type="password" name="password" placeholder="رمز جدید (خالی = بدون تغییر)" autocomplete="new-password">
      <div class="grid g-2 mt-1" style="gap:10px">
        <select class="select" name="role"><option value="user">کاربر</option><option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>مدیر</option></select>
        <select class="select" name="status"><option value="active">فعال</option><option value="banned" <?= $u['status'] === 'banned' ? 'selected' : '' ?>>مسدود</option></select>
      </div>
      <textarea class="textarea mt-1" name="admin_note" rows="3" placeholder="یادداشت داخلی مدیر"><?= e($u['admin_note']) ?></textarea>
      <button class="btn btn-primary btn-block mt-2"><?= icon('check') ?> ذخیره</button>
    </form>
    <?php if ($tickets): ?><div class="card"><div class="card-head"><h3><?= icon('message') ?> تیکت‌ها</h3></div>
      <?php foreach ($tickets as $t): ?><a class="list-item" href="<?= url('admin/tickets/' . $t['id']) ?>"><div><b><?= e($t['subject']) ?></b><small><?= time_ago($t['updated_at']) ?></small></div><span class="li-end"><?= status_badge($t['status'], 'ticket') ?></span></a><?php endforeach; ?></div><?php endif; ?>
  </div>
</div>

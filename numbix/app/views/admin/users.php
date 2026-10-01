<?php $keep = array_diff_key($_GET, ['r' => 1, 'page' => 1]); ?>
<?= partial('page_head', ['icon' => 'users', 'h' => 'مدیریت کاربران', 'sub' => 'کاربران، موجودی کیف پول و دسترسی‌ها', 'actions' => '<button class="btn btn-primary" data-open="#new-user">' . icon('user-plus') . ' کاربر جدید</button>']) ?>
<div class="stats">
  <div class="stat s-violet"><div><div class="st-label">کل کاربران</div><div class="st-value"><?= num($k['total']) ?></div><div class="st-foot">امروز +<?= num($k['today']) ?></div></div><span class="st-ic"><?= icon('users') ?></span></div>
  <div class="stat s-green"><div><div class="st-label">عضویت این ماه</div><div class="st-value"><?= num($k['month_']) ?></div></div><span class="st-ic"><?= icon('user-plus') ?></span></div>
  <div class="stat s-blue"><div><div class="st-label">فعال ۷ روز اخیر</div><div class="st-value"><?= num($k['active7']) ?></div></div><span class="st-ic"><?= icon('activity') ?></span></div>
  <div class="stat s-amber"><div><div class="st-label">جمع کیف پول‌ها</div><div class="st-value"><?= short_num((int)$k['wallets']) ?></div><div class="st-foot"><?= money_text($k['wallets']) ?></div></div><span class="st-ic"><?= icon('wallet') ?></span></div>
</div>
<div class="card">
  <form class="toolbar" method="get" action="<?= url('admin/users') ?>">
    <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="admin/users"><?php endif; ?>
    <div class="input-wrap grow"><?= icon('search') ?><input class="input" name="q" value="<?= e(input('q', '')) ?>" placeholder="نام، ایمیل، موبایل یا شناسه…"></div>
    <select class="select w-sm" name="role"><option value="">همه نقش‌ها</option><option value="user" <?= input('role') === 'user' ? 'selected' : '' ?>>کاربر</option><option value="admin" <?= input('role') === 'admin' ? 'selected' : '' ?>>مدیر</option></select>
    <select class="select w-sm" name="sort"><?php foreach (['new' => 'جدیدترین', 'spent' => 'بیشترین خرید', 'balance' => 'بیشترین موجودی', 'login' => 'آخرین ورود'] as $kk => $l): ?><option value="<?= $kk ?>" <?= input('sort') === $kk ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <label class="check small"><input type="checkbox" name="status" value="banned" <?= input('status') === 'banned' ? 'checked' : '' ?>><span class="box"><?= icon('check') ?></span>فقط مسدودها</label>
    <button class="btn btn-soft"><?= icon('filter') ?> فیلتر</button>
  </form>
  <?php if ($page['items']): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>کاربر</th><th>تماس</th><th>موجودی</th><th>مجموع خرید</th><th>سفارش‌ها</th><th>نقش</th><th>آخرین ورود</th><th></th></tr></thead>
    <tbody><?php foreach ($page['items'] as $u): ?>
      <tr>
        <td><a class="t-user" href="<?= url('admin/users/' . $u['id']) ?>"><?= avatar($u, 'sm') ?><div><b><?= e(user_name($u)) ?></b><small>#<?= fa($u['id']) ?> · عضویت <?= jdate('Y/m/d', $u['created_at']) ?></small></div></a></td>
        <td class="small"><div class="ltr" style="text-align:right"><?= e($u['email'] ?: '—') ?></div><div class="muted ltr" style="text-align:right"><?= e($u['mobile'] ? fa($u['mobile']) : '') ?></div></td>
        <td class="num"><?= money($u['balance']) ?></td>
        <td class="num"><?= num($u['total_spent']) ?></td>
        <td class="num"><?= num($u['orders_count']) ?></td>
        <td><?= $u['role'] === 'admin' ? badge('مدیر', 'primary', 'crown') : ($u['status'] === 'banned' ? badge('مسدود', 'danger') : badge('کاربر', 'muted')) ?></td>
        <td class="muted small nowrap"><?= $u['last_login_at'] ? time_ago($u['last_login_at']) : '—' ?></td>
        <td><div class="actions"><a href="<?= url('admin/users/' . $u['id']) ?>" class="btn btn-soft btn-icon btn-xs"><?= icon('eye') ?></a><a href="<?= url('admin/orders', ['user' => $u['id']]) ?>" class="btn btn-ghost btn-icon btn-xs" title="سفارش‌ها"><?= icon('cart') ?></a></div></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <div class="table-foot"><span>نمایش <?= num(count($page['items'])) ?> از <?= num($page['total']) ?> کاربر</span><?= partial('pagination', ['p' => $page]) ?></div>
  <?php else: ?><?= partial('empty', ['icon' => 'users', 'title' => 'کاربری پیدا نشد']) ?><?php endif; ?>
</div>
<div class="modal" id="new-user"><div class="modal-backdrop"></div><div class="modal-dialog">
  <form method="post" action="<?= url('admin/users/create') ?>"><?= csrf_field() ?>
    <div class="modal-head"><h3><?= icon('user-plus') ?> کاربر جدید</h3><button type="button" class="btn btn-ghost btn-icon btn-sm" data-close><?= icon('x') ?></button></div>
    <div class="modal-body">
      <div class="grid g-2" style="gap:12px"><input class="input" name="first_name" placeholder="نام"><input class="input" name="last_name" placeholder="نام خانوادگی"></div>
      <input class="input mt-1 ltr" type="email" name="email" placeholder="ایمیل" required>
      <input class="input mt-1 ltr" name="mobile" placeholder="موبایل (اختیاری)">
      <input class="input mt-1 ltr" type="password" name="password" placeholder="رمز عبور (حداقل ۸ کاراکتر)" required>
      <select class="select mt-1" name="role"><option value="user">کاربر عادی</option><option value="admin">مدیر</option></select>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-outline" data-close>انصراف</button><button class="btn btn-primary">ساخت کاربر</button></div>
  </form>
</div></div>

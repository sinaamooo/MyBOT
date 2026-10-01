<?php $e = null; foreach ($rows as $r) { if ((int)$r['id'] === $edit) { $e = $r; } } ?>
<?= partial('page_head', ['icon' => 'server', 'h' => 'اتصال API خودکار', 'sub' => 'سفارش‌ها را به‌صورت خودکار به پنل‌های تامین‌کننده (SMM API v2) ارسال کنید']) ?>
<div class="alert alert-primary mb-3"><?= icon('zap') ?><div><b>چطور کار می‌کند؟</b><p>پنل تامین‌کننده را اضافه کنید، از «ورود سرویس‌ها» محصولات را با سود دلخواه وارد کنید. از این به بعد هر سفارش پس از پرداخت خودکار ارسال می‌شود، وضعیت آن هر چند دقیقه همگام می‌شود و در صورت لغو یا انجام ناقص، وجه خودکار به کیف پول کاربر برمی‌گردد.</p></div></div>
<div class="dash-grid">
  <div class="span-8 stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('server') ?> ارائه‌دهندگان</h3></div>
      <?php if ($rows): ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>نام</th><th>موجودی API</th><th>محصولات</th><th>سفارش‌ها</th><th>وضعیت</th><th></th></tr></thead>
        <tbody><?php foreach ($rows as $p): ?>
          <tr>
            <td><b><?= e($p['name']) ?></b><div class="small muted ltr"><?= e(parse_url($p['api_url'], PHP_URL_HOST)) ?></div></td>
            <td class="num ltr"><?= $p['balance'] !== null ? e(number_format((float)$p['balance'], 2) . ' ' . $p['currency']) : '—' ?></td>
            <td class="num"><?= num($p['services_count']) ?></td>
            <td class="num"><?= num($p['orders_count']) ?></td>
            <td><?= $p['last_error'] ? '<span title="' . e($p['last_error']) . '">' . badge('خطا', 'danger') . '</span>' : ($p['is_active'] ? badge('متصل', 'success') : badge('غیرفعال', 'muted')) ?></td>
            <td><div class="actions">
              <a href="<?= url('admin/providers/' . $p['id'] . '/import') ?>" class="btn btn-primary btn-xs"><?= icon('download') ?> ورود سرویس‌ها</a>
              <form method="post" action="<?= url('admin/providers/' . $p['id'] . '/check') ?>"><?= csrf_field() ?><button class="btn btn-soft btn-icon btn-xs" title="بررسی اتصال"><?= icon('refresh') ?></button></form>
              <a href="<?= url('admin/providers', ['edit' => $p['id']]) ?>" class="btn btn-soft btn-icon btn-xs"><?= icon('edit') ?></a>
              <form method="post" action="<?= url('admin/providers/' . $p['id'] . '/delete') ?>" data-confirm="ارائه‌دهنده حذف شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-icon btn-xs"><?= icon('trash') ?></button></form>
            </div></td>
          </tr>
          <?php if ($p['last_error']): ?><tr><td colspan="6" class="small" style="color:var(--danger);padding-top:0"><?= icon('alert') ?> <?= e($p['last_error']) ?></td></tr><?php endif; ?>
        <?php endforeach; ?></tbody>
      </table></div>
      <?php else: ?><?= partial('empty', ['icon' => 'server', 'title' => 'هنوز ارائه‌دهنده‌ای اضافه نشده', 'text' => 'آدرس و کلید API پنل تامین‌کننده را از فرم کناری وارد کنید.']) ?><?php endif; ?>
    </div>
    <?php if ($failing): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('alert') ?> سفارش‌های ارسال‌نشده</h3></div>
      <div class="table-wrap"><table class="table table-compact"><tbody>
        <?php foreach ($failing as $f): ?><tr><td class="num"><a href="<?= url('admin/orders/' . $f['id']) ?>">#<?= fa($f['id']) ?></a></td><td><?= e($f['title']) ?></td><td class="small" style="color:var(--danger)"><?= e($f['provider_error']) ?></td><td class="small muted"><?= fa($f['provider_attempts']) ?> تلاش</td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
    <?php endif; ?>
  </div>
  <form class="card span-4" method="post" action="<?= url('admin/providers/save') ?>" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
    <div class="card-head"><h3><?= icon($e ? 'edit' : 'plus') ?> <?= $e ? 'ویرایش ارائه‌دهنده' : 'افزودن ارائه‌دهنده' ?></h3></div>
    <div class="card-body">
      <div class="field"><label class="label">نام</label><input class="input" name="name" value="<?= e($e['name'] ?? '') ?>" placeholder="مثلاً: پنل اصلی"></div>
      <div class="field"><label class="label">آدرس API</label><input class="input ltr" name="api_url" value="<?= e($e['api_url'] ?? '') ?>" placeholder="https://provider.com/api/v2" required></div>
      <div class="field"><label class="label">کلید API</label><input class="input ltr" name="api_key" placeholder="<?= $e ? '(بدون تغییر)' : '' ?>" <?= $e ? '' : 'required' ?>></div>
      <label class="switch mb-2"><input type="checkbox" name="is_active" value="1" <?= ($e['is_active'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>فعال</label>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره و تست اتصال</button>
    </div>
  </form>
</div>

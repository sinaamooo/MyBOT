<?php $cur = e(setting('currency', 'تومان')); $paid = (int)$o['price'] - (int)$o['discount']; $profit = $paid - (int)$o['refunded'] - (int)$o['cost']; ?>
<?= partial('page_head', ['icon' => 'receipt', 'h' => 'سفارش #' . fa($o['id']), 'sub' => 'ثبت شده ' . jdate('l j F Y — H:i', $o['created_at']) . ' · منبع: ' . ['web' => 'سایت', 'api' => 'API', 'admin' => 'مدیریت'][$o['source']] ,
  'actions' => status_badge($o['status']) . '<a href="' . url('admin/orders') . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>
<div class="dash-grid">
  <div class="span-8 stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= brand_tile($o, 'sm') ?> <?= e($o['title']) ?></h3><a href="<?= url('service/' . $o['slug']) ?>" target="_blank" class="btn btn-ghost btn-xs"><?= icon('external') ?> صفحه سرویس</a></div>
      <div class="card-body">
        <div class="kv">
          <div><span>لینک / آیدی</span><b class="ltr"><a href="<?= e(safe_href($o['link'])) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--p)"><?= e($o['link']) ?></a></b></div>
          <div><span>تعداد</span><b><?= num($o['quantity']) ?> عدد</b></div>
          <div><span>تعداد شروع / باقی‌مانده</span><b><?= $o['start_count'] !== null ? num($o['start_count']) : '—' ?> / <?= $o['remains'] !== null ? num($o['remains']) : '—' ?></b></div>
          <div><span>ارائه‌دهنده API</span><b><?= $o['provider_name'] ? e($o['provider_name']) . ' · سرویس ' . e($o['provider_service_id']) : 'انجام دستی' ?></b></div>
          <?php if ($o['provider_order_id']): ?><div><span>شناسه سفارش در API</span><b class="ltr"><?= e($o['provider_order_id']) ?></b></div><?php endif; ?>
          <?php if ($o['provider_error']): ?><div><span>آخرین خطای API</span><b style="color:var(--danger)"><?= e($o['provider_error']) ?> (<?= fa($o['provider_attempts']) ?> تلاش)</b></div><?php endif; ?>
          <?php if ($o['completed_at']): ?><div><span>زمان تکمیل</span><b><?= jdate('Y/m/d H:i', $o['completed_at']) ?></b></div><?php endif; ?>
        </div>
        <?php if ($o['provider_id']): ?>
          <div class="row mt-2" style="flex-wrap:wrap">
            <?php if ($o['status'] === 'pending' && !$o['provider_order_id']): ?><form method="post" action="<?= url('admin/orders/' . $o['id'] . '/resend') ?>"><?= csrf_field() ?><button class="btn btn-soft btn-sm"><?= icon('send') ?> ارسال مجدد به API</button></form><?php endif; ?>
            <?php if ($o['provider_order_id']): ?><form method="post" action="<?= url('admin/orders/' . $o['id'] . '/sync') ?>"><?= csrf_field() ?><button class="btn btn-soft btn-sm"><?= icon('refresh') ?> دریافت وضعیت از API</button></form><?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <form class="card" method="post" action="<?= url('admin/orders/' . $o['id'] . '/update') ?>">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('edit') ?> تغییر وضعیت</h3></div>
      <div class="card-body">
        <div class="grid g-3" style="gap:14px">
          <div class="field mb-0"><label class="label">وضعیت</label><select class="select" name="status"><?php foreach (order_statuses() as $k => [$l]): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="field mb-0"><label class="label">تعداد شروع</label><input class="input" name="start_count" inputmode="numeric" value="<?= e($o['start_count']) ?>"></div>
          <div class="field mb-0"><label class="label">باقی‌مانده</label><input class="input" name="remains" inputmode="numeric" value="<?= e($o['remains']) ?>"></div>
        </div>
        <div class="field mt-2"><label class="label">لینک</label><input class="input ltr" name="link" value="<?= e($o['link']) ?>"></div>
        <div class="field"><label class="label">یادداشت (برای کاربر قابل مشاهده است)</label><input class="input" name="admin_note" value="<?= e($o['admin_note']) ?>"></div>
        <div class="alert alert-info mb-2"><?= icon('info') ?><p>با انتخاب «لغو شده» کل مبلغ و با «انجام ناقص» مبلغ بخش باقی‌مانده به‌صورت خودکار به کیف پول کاربر برمی‌گردد و موجودی محصول اصلاح می‌شود.</p></div>
        <div class="row-between"><label class="check"><input type="checkbox" name="notify" value="1" checked><span class="box"><?= icon('check') ?></span>اطلاع‌رسانی به کاربر</label><button class="btn btn-primary"><?= icon('check') ?> ذخیره تغییرات</button></div>
      </div>
    </form>
  </div>

  <div class="span-4 stack" style="gap:20px">
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('user') ?> مشتری</h3>
      <a href="<?= url('admin/users/' . $o['uid']) ?>" class="row mb-2"><?= avatar(['id' => $o['uid'], 'first_name' => $o['first_name'], 'last_name' => $o['last_name'], 'avatar' => $o['avatar']], 'lg') ?><div><b><?= e(trim($o['first_name'] . ' ' . $o['last_name'])) ?></b><div class="small muted ltr"><?= e($o['email'] ?: $o['mobile']) ?></div></div></a>
      <div class="kv"><div><span>موجودی کیف پول</span><b><?= money($o['balance']) ?></b></div></div>
    </div>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('coins') ?> مالی</h3>
      <div class="kv">
        <div><span>مبلغ سفارش</span><b><?= money($o['price']) ?></b></div>
        <?php if ($o['discount']): ?><div><span>تخفیف<?= $o['coupon_code'] ? ' (' . e($o['coupon_code']) . ')' : '' ?></span><b>− <?= money($o['discount']) ?></b></div><?php endif; ?>
        <div><span>دریافتی</span><b><?= money($paid) ?></b></div>
        <div><span>بازگشت داده شده</span><b><?= money($o['refunded']) ?></b></div>
        <div><span>هزینه تامین</span><b><?= money($o['cost']) ?></b></div>
        <div><span>سود خالص</span><b style="color:var(--<?= $profit >= 0 ? 'success' : 'danger' ?>)"><?= money($profit) ?></b></div>
      </div>
    </div>
    <?php if ($tx || $tickets): ?>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('list') ?> رویدادها</h3>
      <div class="timeline">
        <?php foreach ($tx as $t): ?><div class="tl-item done"><b><?= e(Wallet::TYPES[$t['type']][0] ?? $t['type']) ?>: <?= money($t['amount']) ?></b><small><?= jdate('Y/m/d H:i', $t['created_at']) ?></small></div><?php endforeach; ?>
        <?php foreach ($tickets as $t): ?><div class="tl-item now"><a href="<?= url('admin/tickets/' . $t['id']) ?>"><b>تیکت #<?= fa($t['id']) ?>: <?= e($t['subject']) ?></b></a><small><?= e(ticket_statuses()[$t['status']][0]) ?></small></div><?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

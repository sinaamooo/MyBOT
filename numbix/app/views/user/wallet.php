<?php $cur = e(setting('currency', 'تومان')); $card = setting('card_number'); $quick = [50000, 100000, 200000, 500000, 1000000]; ?>
<?= partial('page_head', ['icon' => 'wallet', 'h' => 'کیف پول', 'sub' => 'شارژ آنی، پرداخت سریع سفارش‌ها و بازگشت خودکار وجه']) ?>
<?php if ($orderId): ?><div class="alert alert-info mb-3"><?= icon('info') ?><div>برای پرداخت سفارش #<?= fa($orderId) ?>، ابتدا کیف پول خود را شارژ کنید.</div></div><?php endif; ?>

<div class="dash-grid">
  <div class="span-5 stack" style="gap:20px">
    <div class="welcome" style="margin:0;display:block">
      <small style="color:rgba(255,255,255,.65)">موجودی فعلی</small>
      <div><b style="font-size:36px;font-weight:900"><?= num($u['balance']) ?></b> <span class="unit" style="color:rgba(255,255,255,.65)"><?= $cur ?></span></div>
      <div class="grid g-2 mt-2" style="gap:10px">
        <div class="wl-balance" style="min-width:0;padding:12px 14px"><small>مجموع شارژها</small><b style="font-size:16px"><?= num($sum['deposits']) ?></b></div>
        <div class="wl-balance" style="min-width:0;padding:12px 14px"><small>بازگشت وجه</small><b style="font-size:16px"><?= num($sum['refunds']) ?></b></div>
      </div>
    </div>

    <?php if ($online || $testGateway): ?>
    <form class="card" method="post" action="<?= url('wallet/charge') ?>">
      <?= csrf_field() ?>
      <?php if ($orderId): ?><input type="hidden" name="order" value="<?= $orderId ?>"><?php endif; ?>
      <div class="card-head"><h3><?= icon('card') ?> شارژ آنلاین</h3></div>
      <div class="card-body">
        <div class="field">
          <label class="label">مبلغ (<?= $cur ?>)</label>
          <div class="input-wrap"><?= icon('coins') ?><input class="input" name="amount" id="charge-amount" inputmode="numeric" required value="<?= $presetAmount ?: '' ?>" placeholder="حداقل <?= num(setting('min_deposit', 10000)) ?>" data-money></div>
          <div class="presets"><?php foreach ($quick as $q): ?><button type="button" onclick="var i=document.getElementById('charge-amount');i.value=<?= $q ?>;i.dispatchEvent(new Event('input'))"><?= num($q) ?></button><?php endforeach; ?></div>
        </div>
        <?php if ($online && $testGateway): ?>
          <div class="field"><select class="select" name="method"><option value="online">درگاه زرین‌پال</option><option value="test">درگاه آزمایشی</option></select></div>
        <?php else: ?><input type="hidden" name="method" value="<?= $online ? 'online' : 'test' ?>"><?php endif; ?>
        <button class="btn btn-primary btn-lg btn-block"><?= icon('shield') ?> پرداخت امن و شارژ</button>
      </div>
    </form>
    <?php endif; ?>

    <?php if ($card): ?>
    <form class="card" method="post" action="<?= url('wallet/card') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('receipt') ?> کارت به کارت</h3></div>
      <div class="card-body">
        <div class="copy-field mb-2"><span class="btile btile-sm" style="--c1:#34D399;--c2:#059669"><?= icon('card') ?></span><code><?= e(trim(chunk_split(preg_replace('/\D/', '', $card), 4, ' '))) ?></code><button type="button" class="btn btn-soft btn-sm" data-copy="<?= e(preg_replace('/\D/', '', $card)) ?>"><?= icon('copy') ?></button></div>
        <p class="small muted">به نام <b><?= e(setting('card_holder', '—')) ?></b> — <?= e(setting('card_bank', '')) ?>. پس از واریز، مشخصات را ثبت کنید.</p>
        <div class="grid g-2" style="gap:12px">
          <div class="field mb-0"><label class="label">مبلغ واریزی</label><input class="input" name="amount" inputmode="numeric" required data-money></div>
          <div class="field mb-0"><label class="label">۴ رقم آخر کارت</label><input class="input ltr" name="card_pan" inputmode="numeric" maxlength="4"></div>
        </div>
        <div class="field mt-2"><label class="label">شماره پیگیری / مرجع</label><input class="input ltr" name="tracking_code" required></div>
        <div class="field"><label class="label">تصویر رسید <span class="hint">اختیاری</span></label><input class="input" type="file" name="receipt" accept="image/*" style="padding-top:12px"></div>
        <button class="btn btn-soft btn-block"><?= icon('upload') ?> ثبت فیش واریزی</button>
      </div>
    </form>
    <?php endif; ?>

    <?php if (!$online && !$testGateway && !$card): ?>
      <div class="alert alert-warning"><?= icon('alert') ?><div>روش پرداختی فعال نیست. برای شارژ کیف پول با پشتیبانی در ارتباط باشید.</div></div>
    <?php endif; ?>
  </div>

  <div class="span-7 stack" style="gap:20px">
    <?php if ($payments): ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('card') ?> پرداخت‌های اخیر</h3></div>
      <div class="table-wrap"><table class="table table-compact">
        <thead><tr><th>#</th><th>روش</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead>
        <tbody><?php foreach ($payments as $p): ?>
          <tr><td class="num">#<?= fa($p['id']) ?></td><td><?= e(Payments::GATEWAYS[$p['gateway']] ?? $p['gateway']) ?></td><td class="num"><?= money($p['amount']) ?></td><td><?= status_badge($p['status'], 'payment') ?></td><td class="muted nowrap"><?= jdate('Y/m/d H:i', $p['created_at']) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
    </div>
    <?php endif; ?>
    <div class="card">
      <div class="card-head"><h3><?= icon('list') ?> تراکنش‌های کیف پول</h3></div>
      <?php if ($tx['items']): ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>نوع</th><th>شرح</th><th>مبلغ</th><th>مانده</th><th>تاریخ</th></tr></thead>
          <tbody><?php foreach ($tx['items'] as $t): [$tl, $tc] = Wallet::TYPES[$t['type']] ?? [$t['type'], 'muted']; ?>
            <tr>
              <td><?= badge($tl, $tc) ?></td>
              <td class="small"><?= e($t['description']) ?></td>
              <td class="num" style="color:var(--<?= $t['amount'] >= 0 ? 'success' : 'danger' ?>)"><span class="ltr"><?= $t['amount'] >= 0 ? '+' : '−' ?><?= num(abs($t['amount'])) ?></span></td>
              <td class="num muted"><?= num($t['balance_after']) ?></td>
              <td class="muted nowrap"><?= jdate('Y/m/d H:i', $t['created_at']) ?></td>
            </tr>
          <?php endforeach; ?></tbody>
        </table></div>
        <div class="table-foot"><span><?= num($tx['total']) ?> تراکنش</span><?= partial('pagination', ['p' => $tx]) ?></div>
      <?php else: ?>
        <?= partial('empty', ['icon' => 'wallet', 'title' => 'هنوز تراکنشی ندارید']) ?>
      <?php endif; ?>
    </div>
  </div>
</div>

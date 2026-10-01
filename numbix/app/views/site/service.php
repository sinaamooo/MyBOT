<?php
[$stockKey, $stockLabel, $stockColor] = stock_state($s);
$out = $stockKey === 'out';
$max = (int)min($s['max_qty'], $s['stock'] ?? PHP_INT_MAX);
$presets = array_values(array_unique(array_filter([(int)$s['min_qty'], 1000, 2000, 5000, 10000, 50000], fn($v) => $v >= $s['min_qty'] && $v <= $max)));
$off = ($s['compare_price'] && $s['compare_price'] > $s['price']) ? (int)round(100 - $s['price'] * 100 / $s['compare_price']) : 0;
$badges = service_badges();
?>
<div class="container" style="padding-top:28px">
  <nav class="crumbs" style="justify-content:flex-start;color:var(--muted);margin-bottom:18px">
    <a href="<?= url('/') ?>">خانه</a><?= icon('chevron-left') ?>
    <a href="<?= url('services') ?>">خدمات</a><?= icon('chevron-left') ?>
    <a href="<?= url('services/' . $s['category_slug']) ?>"><?= e($s['category_name']) ?></a><?= icon('chevron-left') ?>
    <span style="color:var(--text)"><?= e($s['title']) ?></span>
  </nav>

  <div class="svc-hero">
    <div class="stack" style="gap:22px">
      <div class="svc-show-cover reveal" style="--c1:<?= e($s['color']) ?>;--c2:<?= e($s['color2']) ?>">
        <?php if ($s['image']): ?><img src="<?= e(upload_url($s['image'])) ?>" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-2"><?php endif; ?>
        <span class="big-ic"><?= brand($s['icon']) ?></span>
        <?php if ($s['badge'] && isset($badges[$s['badge']])): ?><span class="svc-badge <?= $badges[$s['badge']][1] ?>" style="top:20px;right:20px"><?= e($badges[$s['badge']][0]) ?></span><?php endif; ?>
        <button type="button" class="fav-btn<?= in_array((int)$s['id'], $favs, true) ? ' on' : '' ?>" data-fav="<?= (int)$s['id'] ?>" style="top:18px;left:18px;width:44px;height:44px" aria-label="علاقه‌مندی"><?= icon('heart') ?></button>
      </div>

      <div class="reveal">
        <div class="row mb-1" style="flex-wrap:wrap;gap:8px">
          <span class="chip"><?= brand($s['icon']) ?> <?= e($s['category_name']) ?></span>
          <span class="chip"><?= icon('tag') ?> <?= e(service_types()[$s['type']] ?? 'سایر') ?></span>
          <?= badge($stockLabel, $stockColor) ?>
        </div>
        <h1 style="font-size:clamp(24px,3vw,32px);font-weight:900;margin-bottom:6px"><?= e($s['title']) ?></h1>
        <?php if ($s['subtitle']): ?><p class="text-2" style="font-size:16px"><?= e($s['subtitle']) ?></p><?php endif; ?>
        <div class="row" style="gap:18px;flex-wrap:wrap;font-size:13.5px;color:var(--muted)">
          <?= stars((float)$s['rating']) ?>
          <span class="row" style="gap:6px"><?= icon('bag') ?> <?= num($s['sales_count']) ?> سفارش</span>
          <?php if ($completed): ?><span class="row" style="gap:6px"><?= icon('check-circle') ?> <?= num($completed) ?> تکمیل شده</span><?php endif; ?>
          <span class="row" style="gap:6px"><?= icon('eye') ?> <?= num($s['views']) ?> بازدید</span>
        </div>
      </div>

      <div class="spec-grid reveal">
        <div class="spec"><span class="s-ic"><?= icon('zap') ?></span><div><small>زمان شروع</small><b><?= e($s['start_time'] ?: 'فوری') ?></b></div></div>
        <div class="spec"><span class="s-ic"><?= icon('clock') ?></span><div><small>زمان تحویل</small><b><?= e($s['delivery_time'] ?: '—') ?></b></div></div>
        <div class="spec"><span class="s-ic"><?= icon('award') ?></span><div><small>کیفیت</small><b><?= e($s['quality'] ?: 'عالی') ?></b></div></div>
        <div class="spec"><span class="s-ic"><?= icon('shield') ?></span><div><small>ضمانت</small><b><?= e($s['guarantee'] ?: 'ندارد') ?></b></div></div>
        <div class="spec"><span class="s-ic"><?= icon('arrow-down') ?></span><div><small>حداقل سفارش</small><b><?= num($s['min_qty']) ?> عدد</b></div></div>
        <div class="spec"><span class="s-ic"><?= icon('arrow-up') ?></span><div><small>حداکثر سفارش</small><b><?= num($s['max_qty']) ?> عدد</b></div></div>
      </div>

      <?php if (trim((string)$s['description']) !== ''): ?>
        <div class="card card-pad reveal">
          <h3 class="card-title mb-2"><?= icon('file') ?> توضیحات سرویس</h3>
          <div class="prose"><?= nl2br(e($s['description'])) ?></div>
        </div>
      <?php endif; ?>

      <div class="alert alert-primary reveal"><?= icon('info') ?><div><b>نکات مهم قبل از سفارش</b><p>پیج یا کانال شما باید در طول انجام سفارش عمومی (Public) باشد. برای یک لینک، تا تکمیل سفارش قبلی، سفارش جدید از همین سرویس ثبت نکنید.</p></div></div>
    </div>

    <aside class="order-box">
      <form class="card" action="<?= url('cart/add') ?>" method="post" data-order-form data-price="<?= (int)$s['price'] ?>" data-min="<?= (int)$s['min_qty'] ?>" data-max="<?= $max ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
        <div class="card-head"><h3><?= icon('cart') ?> ثبت سفارش</h3>
          <div class="center" style="line-height:1.3">
            <?php if ($off): ?><del class="muted small"><?= num($s['compare_price']) ?></del> <span class="badge badge-danger" style="height:22px">٪<?= fa($off) ?></span><br><?php endif; ?>
            <b style="font-size:18px"><?= num($s['price']) ?></b> <span class="unit"><?= e(setting('currency', 'تومان')) ?> / هر ۱۰۰۰</span>
          </div>
        </div>
        <div class="card-body">
          <div class="field">
            <label class="label">لینک یا آیدی مقصد</label>
            <div class="input-wrap"><?= icon('link') ?><input class="input ltr" name="link" required placeholder="<?= e($s['link_hint'] ?: 'https://…') ?>" autocomplete="off" <?= $out ? 'disabled' : '' ?>></div>
          </div>
          <div class="field mb-0">
            <label class="label">تعداد <span class="hint" data-qty-hint>حداقل <?= num($s['min_qty']) ?> — حداکثر <?= num($max) ?></span></label>
            <div class="qty w-100"><button type="button" data-step="-" aria-label="کم"><?= icon('minus') ?></button><input name="qty" class="w-100" inputmode="numeric" value="<?= (int)($presets[1] ?? $s['min_qty']) ?>" <?= $out ? 'disabled' : '' ?>><button type="button" data-step="+" aria-label="زیاد"><?= icon('plus') ?></button></div>
            <div class="presets"><?php foreach (array_slice($presets, 0, 5) as $p): ?><button type="button" data-q="<?= $p ?>"><?= num($p) ?></button><?php endforeach; ?></div>
          </div>
          <div class="total-box"><span class="muted">مبلغ قابل پرداخت</span><span><b data-total>۰</b> <span class="unit"><?= e(setting('currency', 'تومان')) ?></span></span></div>
          <?php if ($out): ?>
            <div class="alert alert-danger"><?= icon('alert') ?> این سرویس در حال حاضر ناموجود است.</div>
          <?php else: ?>
            <button class="btn btn-primary btn-lg btn-block" name="go" value="cart"><?= icon('zap') ?> خرید سریع</button>
            <button class="btn btn-soft btn-block mt-1" name="go" value="stay"><?= icon('cart') ?> افزودن به سبد خرید</button>
          <?php endif; ?>
          <div class="row mt-2" style="justify-content:center;gap:16px;font-size:12px;color:var(--muted)">
            <span class="row" style="gap:5px"><?= icon('shield') ?> پرداخت امن</span>
            <span class="row" style="gap:5px"><?= icon('repeat') ?> بازگشت خودکار وجه</span>
          </div>
        </div>
      </form>
    </aside>
  </div>

  <?php if ($related): ?>
    <section class="section" style="padding-bottom:0">
      <div class="sec-head"><div><span class="sec-kicker"><?= icon('layers') ?> مشابه</span><h2 class="sec-title">سرویس‌های مرتبط</h2></div></div>
      <div class="svc-grid"><?php foreach ($related as $r): ?><?= partial('service_card', ['s' => $r, 'favs' => $favs]) ?><?php endforeach; ?></div>
    </section>
  <?php endif; ?>
</div>

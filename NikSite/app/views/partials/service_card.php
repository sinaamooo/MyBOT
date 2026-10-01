<?php
/** @var array $s service row joined with category (icon, color, color2, category_name) */
$favs = $favs ?? [];
$badges = service_badges();
[$stockKey] = stock_state($s);
$out = $stockKey === 'out';
$off = ($s['compare_price'] && $s['compare_price'] > $s['price']) ? (int)round(100 - $s['price'] * 100 / $s['compare_price']) : 0;
$tile = brand_tile(['icon' => $s['icon'], 'color' => $s['color'], 'color2' => $s['color2']], 'md');
$qa = ['id' => (int)$s['id'], 'title' => $s['title'], 'price' => (int)$s['price'], 'min' => (int)$s['min_qty'], 'max' => (int)min($s['max_qty'], $s['stock'] ?? PHP_INT_MAX), 'hint' => $s['link_hint'] ?: 'لینک یا آیدی مقصد', 'tile' => $tile];
?>
<article class="svc-card reveal<?= $out ? ' out' : '' ?>" data-tilt style="--c1:<?= e($s['color']) ?>;--c2:<?= e($s['color2']) ?>">
  <a href="<?= e(service_url($s)) ?>" class="svc-stage" aria-label="<?= e($s['title']) ?>">
    <?php if ($s['image']): ?><img class="svc-img" src="<?= e(upload_url($s['image'])) ?>" alt="" loading="lazy"><?php endif; ?>
    <span class="svc-orb"><i class="orbit-ring"></i><?= brand_tile(['icon' => $s['icon'], 'color' => $s['color'], 'color2' => $s['color2']], 'xl') ?></span>
    <?php if ($s['badge'] && isset($badges[$s['badge']])): ?><span class="svc-badge <?= $badges[$s['badge']][1] ?>"><?= e($badges[$s['badge']][0]) ?></span><?php endif; ?>
    <?php if ($off > 0): ?><span class="svc-off">٪<?= fa($off) ?> تخفیف</span><?php endif; ?>
    <?php if ($out): ?><span class="stock-tag badge badge-danger">ناموجود</span><?php endif; ?>
  </a>
  <button type="button" class="fav-btn<?= in_array((int)$s['id'], $favs, true) ? ' on' : '' ?>" data-fav="<?= (int)$s['id'] ?>" aria-label="علاقه‌مندی"><?= icon('heart') ?></button>
  <div class="svc-body">
    <h3 class="svc-title"><a href="<?= e(service_url($s)) ?>"><?= e($s['title']) ?></a></h3>
    <?php if ($s['subtitle']): ?><p class="svc-sub"><?= e($s['subtitle']) ?></p><?php endif; ?>
    <div class="svc-meta">
      <div title="زمان شروع"><?= icon('zap') ?><b><?= e($s['start_time'] ?: 'فوری') ?></b></div>
      <div title="زمان تحویل"><?= icon('clock') ?><b><?= e($s['delivery_time'] ?: '—') ?></b></div>
    </div>
    <div class="svc-price">
      <div class="p">
        <small>هر ۱۰۰۰ عدد</small>
        <b><?= num($s['price']) ?></b> <span class="unit"><?= e(setting('currency', 'تومان')) ?></span>
        <?php if ($off > 0): ?><del><?= num($s['compare_price']) ?></del><?php endif; ?>
      </div>
      <?= stars((float)$s['rating']) ?>
    </div>
    <div class="svc-actions">
      <?php if ($out): ?>
        <span class="btn btn-outline disabled">ناموجود</span>
      <?php else: ?>
        <a href="<?= e(service_url($s)) ?>" class="btn btn-primary"><?= icon('rocket') ?> خرید سرویس</a>
        <button type="button" class="btn btn-soft btn-icon" data-quick-add="<?= e(json_encode($qa, JSON_UNESCAPED_UNICODE)) ?>" aria-label="افزودن به سبد"><?= icon('cart') ?></button>
      <?php endif; ?>
    </div>
  </div>
</article>

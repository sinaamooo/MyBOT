<?php
$baseUrl = $cat ? 'services/' . $cat['slug'] : 'services';
$keep = array_filter(['q' => $q, 'sort' => $sort !== 'popular' ? $sort : null]);
?>
<div class="container" style="padding-top:28px">
  <?php if ($banner): ?>
    <a href="<?= $banner['link'] ? url('b/' . $banner['id']) : '#' ?>" class="promo theme-<?= e($banner['theme']) ?> reveal">
      <?php if ($banner['image']): ?><img class="promo-img" src="<?= e(upload_url($banner['image'])) ?>" alt=""><?php endif; ?>
      <div>
        <?php if ($banner['badge']): ?><span class="p-badge"><?= e($banner['badge']) ?></span><?php endif; ?>
        <h2><?= e($banner['title']) ?></h2>
        <?php if ($banner['subtitle']): ?><p><?= e($banner['subtitle']) ?></p><?php endif; ?>
        <?php if ($banner['button_text']): ?><span class="btn btn-white"><?= e($banner['button_text']) ?> <?= icon('arrow-left') ?></span><?php endif; ?>
      </div>
      <div class="promo-art" aria-hidden="true">
        <?= brand_tile(['icon' => 'instagram', 'color' => '#F58529', 'color2' => '#C13584'], 'xl') ?>
        <?= brand_tile(['icon' => 'telegram', 'color' => '#37BBFE', 'color2' => '#007DBB'], 'lg') ?>
        <?= brand_tile(['icon' => 'youtube', 'color' => '#FF4E45', 'color2' => '#C4302B'], 'lg') ?>
        <?= brand_tile(['icon' => 'tiktok', 'color' => '#2B2B2B', 'color2' => '#000'], 'md') ?>
      </div>
    </a>
  <?php endif; ?>

  <div class="cat-strip" style="margin:0 0 28px">
    <div class="inner" style="box-shadow:var(--sh-sm)">
      <a href="<?= url('services', $keep) ?>" class="cat-tile<?= !$cat ? ' active' : '' ?>" style="--c1:#6C4CF1">
        <span class="btile btile-md" style="--c1:#8B5CF6;--c2:#4F46E5"><?= icon('grid') ?></span><b>همه خدمات</b><small><?= num($totalActive) ?> سرویس</small>
      </a>
      <?php foreach ($categories as $c): ?>
        <a href="<?= url('services/' . $c['slug'], $keep) ?>" class="cat-tile<?= $cat && $cat['id'] == $c['id'] ? ' active' : '' ?>" style="--c1:<?= e($c['color']) ?>">
          <?= brand_tile($c, 'md') ?><b><?= e($c['name']) ?></b><small><?= num($c['services_count']) ?> سرویس</small>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="shop">
    <div class="filters" id="filters">
      <form class="card" method="get" action="<?= url($baseUrl) ?>" data-autosubmit>
        <?php if (!config('app.pretty_urls', true)): ?><input type="hidden" name="r" value="<?= e($baseUrl) ?>"><?php endif; ?>
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <div class="card-head"><h3><?= icon('filter') ?> فیلتر خدمات</h3>
          <div class="row" style="gap:4px">
            <a href="<?= url($baseUrl) ?>" class="btn btn-ghost btn-xs">حذف فیلترها</a>
            <button type="button" class="btn btn-ghost btn-icon btn-xs filter-toggle" data-close><?= icon('x') ?></button>
          </div>
        </div>
        <div class="card-body">
          <div class="f-group">
            <h5>دسته‌بندی</h5>
            <div class="f-list">
              <a href="<?= url('services', $keep) ?>" class="f-item"><span class="check"><input type="radio" <?= !$cat ? 'checked' : '' ?> disabled><span class="box"><?= icon('check') ?></span></span>همه خدمات<span class="cnt"><?= num($totalActive) ?></span></a>
              <?php foreach ($categories as $c): ?>
                <a href="<?= url('services/' . $c['slug'], $keep) ?>" class="f-item"><span class="check"><input type="radio" <?= $cat && $cat['id'] == $c['id'] ? 'checked' : '' ?> disabled><span class="box"><?= icon('check') ?></span></span><?= brand_tile($c, 'xs') ?><?= e($c['name']) ?><span class="cnt"><?= num($c['services_count']) ?></span></a>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="f-group">
            <h5>محدوده قیمت <small class="muted">(هر ۱۰۰۰ عدد)</small></h5>
            <div class="range" data-range data-min-out="#pmin-out" data-max-out="#pmax-out">
              <div class="track"><div class="fill"></div></div>
              <input type="range" name="pmin" min="0" max="<?= $maxPrice ?>" step="<?= max(1, (int)($maxPrice / 100)) ?>" value="<?= $pmin ?>" aria-label="حداقل قیمت">
              <input type="range" name="pmax" min="0" max="<?= $maxPrice ?>" step="<?= max(1, (int)($maxPrice / 100)) ?>" value="<?= min($pmax, $maxPrice) ?>" aria-label="حداکثر قیمت">
            </div>
            <div class="grid g-2 mt-1" style="gap:8px">
              <input class="input input-sm center" id="pmin-out" readonly tabindex="-1">
              <input class="input input-sm center" id="pmax-out" readonly tabindex="-1">
            </div>
          </div>
          <div class="f-group">
            <h5>نوع خدمات</h5>
            <div class="f-list">
              <?php foreach (service_types() as $k => $label): ?>
                <label class="f-item check"><input type="checkbox" name="type[]" value="<?= $k ?>" <?= in_array($k, $types, true) ? 'checked' : '' ?>><span class="box"><?= icon('check') ?></span><?= e($label) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="f-group">
            <h5>وضعیت</h5>
            <div class="f-list">
              <?php foreach (['all' => 'همه', 'stock' => 'فقط موجود', 'sale' => 'فقط تخفیف‌دار'] as $k => $label): ?>
                <label class="f-item check"><input type="radio" name="state" value="<?= $k ?>" <?= $state === $k ? 'checked' : '' ?>><span class="box"><?= icon('check') ?></span><?= $label ?></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </form>
    </div>

    <div>
      <div class="shop-bar">
        <div>
          <h2><?= e($cat ? 'خدمات ' . $cat['name'] : ($q !== '' ? 'نتایج «' . $q . '»' : 'همه خدمات')) ?></h2>
          <span class="muted">نمایش <?= num($page['total']) ?> سرویس فعال</span>
        </div>
        <div class="row">
          <button class="btn btn-outline btn-sm filter-toggle" data-open="#filters"><?= icon('filter') ?> فیلترها</button>
          <div class="dropdown">
            <button class="btn btn-outline btn-sm" data-dropdown><?= icon('sort') ?> مرتب‌سازی: <?= e($sorts[$sort][0]) ?> <?= icon('chevron-down') ?></button>
            <div class="dropdown-menu">
              <?php foreach ($sorts as $k => [$label]): ?>
                <a href="<?= e(url(trim(current_path(), '/'), array_merge(array_diff_key($_GET, ['r' => 1, 'page' => 1]), ['sort' => $k]))) ?>"><?= $k === $sort ? icon('check') : icon('minus') ?> <?= e($label) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <?php if ($page['items']): ?>
        <div class="svc-grid">
          <?php foreach ($page['items'] as $s): ?><?= partial('service_card', ['s' => $s, 'favs' => $favs]) ?><?php endforeach; ?>
        </div>
        <div class="mt-4 row" style="justify-content:center"><?= partial('pagination', ['p' => $page]) ?></div>
      <?php else: ?>
        <div class="card"><?= partial('empty', ['icon' => 'search', 'title' => 'سرویسی با این مشخصات پیدا نشد', 'text' => 'فیلترها را تغییر دهید یا عبارت دیگری جستجو کنید.', 'action' => '<a class="btn btn-soft" href="' . url('services') . '">نمایش همه خدمات</a>']) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

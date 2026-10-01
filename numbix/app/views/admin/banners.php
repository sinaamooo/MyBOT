<?php $e = null; foreach ($rows as $r) { if ((int)$r['id'] === $edit) { $e = $r; } } ?>
<?= partial('page_head', ['icon' => 'megaphone', 'h' => 'بنرها و تبلیغات', 'sub' => 'بنرهای تبلیغاتی سایت با آمار کلیک']) ?>
<div class="dash-grid">
  <div class="span-8 stack" style="gap:18px">
    <?php foreach ($rows as $b): ?>
      <div class="card">
        <div class="promo theme-<?= e($b['theme']) ?>" style="margin:0;min-height:150px;border-radius:20px 20px 0 0;padding:26px 30px">
          <?php if ($b['image']): ?><img class="promo-img" src="<?= e(upload_url($b['image'])) ?>" alt=""><?php endif; ?>
          <div><?php if ($b['badge']): ?><span class="p-badge"><?= e($b['badge']) ?></span><?php endif; ?><h2 style="font-size:22px"><?= e($b['title']) ?></h2><?php if ($b['subtitle']): ?><p class="mb-0"><?= e($b['subtitle']) ?></p><?php endif; ?></div>
          <?php if ($b['button_text']): ?><span class="btn btn-white btn-sm"><?= e($b['button_text']) ?></span><?php endif; ?>
        </div>
        <div class="card-foot row-between">
          <div class="row small muted" style="gap:16px"><span><?= icon('activity') ?> <?= num($b['clicks']) ?> کلیک</span><span><?= e(AdminContentController::POSITIONS[$b['position']] ?? '') ?></span><span><?= $b['is_active'] ? badge('فعال', 'success') : badge('غیرفعال', 'muted') ?></span></div>
          <div class="actions"><a href="<?= url('admin/banners', ['edit' => $b['id']]) ?>" class="btn btn-soft btn-xs"><?= icon('edit') ?> ویرایش</a>
            <form method="post" action="<?= url('admin/banners/' . $b['id'] . '/delete') ?>" data-confirm="بنر حذف شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-icon btn-xs"><?= icon('trash') ?></button></form></div>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><div class="card"><?= partial('empty', ['icon' => 'megaphone', 'title' => 'بنری ساخته نشده']) ?></div><?php endif; ?>
  </div>
  <form class="card span-4" method="post" action="<?= url('admin/banners/save') ?>" enctype="multipart/form-data" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
    <div class="card-head"><h3><?= icon($e ? 'edit' : 'plus') ?> <?= $e ? 'ویرایش بنر' : 'بنر جدید' ?></h3><?php if ($e): ?><a href="<?= url('admin/banners') ?>" class="btn btn-ghost btn-xs">انصراف</a><?php endif; ?></div>
    <div class="card-body">
      <div class="field"><label class="label">عنوان</label><input class="input" name="title" value="<?= e($e['title'] ?? '') ?>" required></div>
      <div class="field"><label class="label">زیرعنوان</label><input class="input" name="subtitle" value="<?= e($e['subtitle'] ?? '') ?>"></div>
      <div class="grid g-2" style="gap:10px">
        <div class="field mb-0"><label class="label">برچسب</label><input class="input" name="badge" value="<?= e($e['badge'] ?? '') ?>" placeholder="تخفیف ویژه"></div>
        <div class="field mb-0"><label class="label">متن دکمه</label><input class="input" name="button_text" value="<?= e($e['button_text'] ?? '') ?>"></div>
      </div>
      <div class="field mt-2"><label class="label">لینک</label><input class="input ltr" name="link" value="<?= e($e['link'] ?? '') ?>" placeholder="services/instagram یا https://…"></div>
      <div class="grid g-2" style="gap:10px">
        <div class="field mb-0"><label class="label">تم رنگی</label><select class="select" name="theme"><?php foreach (AdminContentController::THEMES as $k => $l): ?><option value="<?= $k ?>" <?= ($e['theme'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field mb-0"><label class="label">جایگاه</label><select class="select" name="position"><?php foreach (AdminContentController::POSITIONS as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="field mt-2"><label class="label">تصویر پس‌زمینه <span class="hint">اختیاری</span></label><input class="input" type="file" name="image" accept="image/*" style="padding-top:12px"></div>
      <div class="field"><label class="label">ترتیب</label><input class="input" name="sort" value="<?= e($e['sort'] ?? '0') ?>"></div>
      <label class="switch mb-2"><input type="checkbox" name="is_active" value="1" <?= ($e['is_active'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>فعال</label>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره بنر</button>
    </div>
  </form>
</div>

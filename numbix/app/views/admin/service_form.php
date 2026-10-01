<?php $v = fn($k, $d = '') => e(old($k, $s[$k] ?? $d)); $track = $s ? $s['stock'] !== null : true; ?>
<?= partial('page_head', ['icon' => 'box', 'h' => $s ? 'ویرایش: ' . $s['title'] : 'افزودن محصول جدید', 'sub' => 'قیمت‌ها بر اساس هر ۱۰۰۰ عدد وارد می‌شوند',
  'actions' => '<a href="' . url('admin/services') . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>
<form method="post" action="<?= url('admin/services/save') ?>" enctype="multipart/form-data" class="dash-grid">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($s['id'] ?? 0) ?>">
  <div class="span-8 stack" style="gap:20px">
    <div class="card">
      <div class="card-head"><h3><?= icon('file') ?> اطلاعات اصلی</h3></div>
      <div class="card-body">
        <div class="field"><label class="label">عنوان محصول</label><input class="input" name="title" id="f-title" value="<?= $v('title') ?>" required placeholder="مثلاً: افزایش ممبر تلگرام (واقعی)"></div>
        <div class="grid g-2" style="gap:14px">
          <div class="field mb-0"><label class="label">زیرعنوان</label><input class="input" name="subtitle" value="<?= $v('subtitle') ?>" placeholder="توضیح کوتاه زیر عنوان"></div>
          <div class="field mb-0"><label class="label">نامک (URL)</label><input class="input ltr" name="slug" value="<?= $v('slug') ?>" data-slug-from="#f-title"></div>
          <div class="field mb-0"><label class="label">دسته‌بندی</label><select class="select" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= (int)old('category_id', $s['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field mb-0"><label class="label">نوع خدمت</label><select class="select" name="type"><?php foreach (service_types() as $k => $l): ?><option value="<?= $k ?>" <?= old('type', $s['type'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field mt-2 mb-0"><label class="label">توضیحات</label><textarea class="textarea" name="description" rows="6"><?= $v('description') ?></textarea></div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('coins') ?> قیمت و محدودیت سفارش</h3></div>
      <div class="card-body">
        <div class="grid g-3" style="gap:14px">
          <div class="field mb-0"><label class="label">قیمت فروش (هر ۱۰۰۰)</label><input class="input" name="price" inputmode="numeric" value="<?= $v('price') ?>" required data-money></div>
          <div class="field mb-0"><label class="label">قیمت خرید (هر ۱۰۰۰)</label><input class="input" name="cost" inputmode="numeric" value="<?= $v('cost', '0') ?>" data-money></div>
          <div class="field mb-0"><label class="label">قیمت قبل از تخفیف <span class="hint">اختیاری</span></label><input class="input" name="compare_price" inputmode="numeric" value="<?= $v('compare_price') ?>" data-money></div>
          <div class="field mb-0"><label class="label">حداقل سفارش</label><input class="input" name="min_qty" inputmode="numeric" value="<?= $v('min_qty', '100') ?>"></div>
          <div class="field mb-0"><label class="label">حداکثر سفارش</label><input class="input" name="max_qty" inputmode="numeric" value="<?= $v('max_qty', '100000') ?>"></div>
          <div class="field mb-0"><label class="label">امتیاز (۰ تا ۵)</label><input class="input" name="rating" value="<?= $v('rating', '5.0') ?>"></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('database') ?> موجودی</h3></div>
      <div class="card-body">
        <label class="switch mb-2"><input type="checkbox" name="track_stock" value="1" <?= $track ? 'checked' : '' ?> data-toggle-target=".stock-fields"><span class="track"></span>مدیریت موجودی (در غیر این صورت نامحدود)</label>
        <div class="grid g-2 stock-fields<?= $track ? '' : ' hidden' ?>" style="gap:14px">
          <div class="field mb-0"><label class="label">موجودی فعلی (عدد)</label><input class="input" name="stock" inputmode="numeric" value="<?= $v('stock', '0') ?>"></div>
          <div class="field mb-0"><label class="label">هشدار موجودی کم</label><input class="input" name="stock_alert" inputmode="numeric" value="<?= $v('stock_alert', '0') ?>"><div class="help">۰ = مقدار پیش‌فرض تنظیمات</div></div>
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3><?= icon('server') ?> اتصال خودکار به API</h3></div>
      <div class="card-body">
        <div class="grid g-2" style="gap:14px">
          <div class="field mb-0"><label class="label">ارائه‌دهنده</label><select class="select" name="provider_id"><option value="">— انجام دستی —</option><?php foreach ($providers as $p): ?><option value="<?= $p['id'] ?>" <?= (int)($s['provider_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?></select></div>
          <div class="field mb-0"><label class="label">شناسه سرویس در API</label><input class="input ltr" name="provider_service_id" value="<?= $v('provider_service_id') ?>"></div>
        </div>
        <div class="help">با اتصال به API، سفارش‌ها بلافاصله پس از پرداخت به صورت خودکار ارسال و وضعیتشان همگام‌سازی می‌شود.</div>
      </div>
    </div>
  </div>
  <div class="span-4 stack" style="gap:20px">
    <div class="card card-pad">
      <label class="switch mb-2"><input type="checkbox" name="is_active" value="1" <?= ($s['is_active'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>فعال و قابل خرید</label>
      <label class="switch mb-2"><input type="checkbox" name="is_featured" value="1" <?= ($s['is_featured'] ?? 0) ? 'checked' : '' ?>><span class="track"></span>نمایش در «محبوب‌ترین‌ها»</label>
      <div class="field"><label class="label">برچسب</label><select class="select" name="badge"><option value="">بدون برچسب</option><?php foreach (service_badges() as $k => [$l]): ?><option value="<?= $k ?>" <?= ($s['badge'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
      <div class="field mb-0"><label class="label">ترتیب نمایش</label><input class="input" name="sort" inputmode="numeric" value="<?= $v('sort', '0') ?>"></div>
      <hr>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره محصول</button>
      <button class="btn btn-soft btn-block mt-1" name="stay" value="1">ذخیره و ادامه ویرایش</button>
    </div>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('info') ?> مشخصات نمایشی</h3>
      <div class="field"><label class="label">زمان شروع</label><input class="input" name="start_time" value="<?= $v('start_time') ?>" placeholder="فوری"></div>
      <div class="field"><label class="label">زمان تحویل</label><input class="input" name="delivery_time" value="<?= $v('delivery_time') ?>" placeholder="۱ تا ۲ ساعت"></div>
      <div class="field"><label class="label">کیفیت</label><input class="input" name="quality" value="<?= $v('quality') ?>" placeholder="واقعی و فعال"></div>
      <div class="field"><label class="label">ضمانت</label><input class="input" name="guarantee" value="<?= $v('guarantee') ?>" placeholder="۳۰ روز جبران ریزش"></div>
      <div class="field mb-0"><label class="label">راهنمای لینک</label><input class="input ltr" name="link_hint" value="<?= $v('link_hint') ?>" placeholder="https://t.me/channel"></div>
    </div>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('image') ?> تصویر کاور <span class="hint muted small">اختیاری</span></h3>
      <label class="img-preview"><?php if (!empty($s['image'])): ?><img src="<?= e(upload_url($s['image'])) ?>" alt=""><?php else: ?><span class="ph"><?= icon('upload') ?> انتخاب تصویر</span><?php endif; ?><input type="file" name="image" accept="image/*" data-preview></label>
      <?php if (!empty($s['image'])): ?><label class="check small mt-1"><input type="checkbox" name="remove_image" value="1"><span class="box"><?= icon('check') ?></span>حذف تصویر</label><?php endif; ?>
      <div class="help">بدون تصویر، کاور گرافیکی با رنگ برند ساخته می‌شود.</div>
    </div>
  </div>
</form>

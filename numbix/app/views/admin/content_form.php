<?= partial('page_head', ['icon' => 'file', 'h' => $p ? 'ویرایش: ' . $p['title'] : ($type === 'page' ? 'صفحه جدید' : 'مقاله جدید'), 'actions' => '<a href="' . url('admin/content', ['tab' => $type]) . '" class="btn btn-soft">' . icon('arrow-right') . ' بازگشت</a>']) ?>
<form class="dash-grid" method="post" action="<?= url('admin/content/save') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($p['id'] ?? 0) ?>"><input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="card span-8"><div class="card-body">
    <div class="field"><label class="label">عنوان</label><input class="input" name="title" id="p-title" value="<?= e($p['title'] ?? '') ?>" required style="font-size:17px;font-weight:700"></div>
    <div class="field"><label class="label">نامک</label><input class="input ltr" name="slug" value="<?= e($p['slug'] ?? '') ?>" data-slug-from="#p-title"></div>
    <div class="field"><label class="label">خلاصه</label><textarea class="textarea" name="excerpt" rows="2" style="min-height:70px"><?= e($p['excerpt'] ?? '') ?></textarea></div>
    <div class="field mb-0"><label class="label">محتوا <span class="hint">HTML مجاز است: &lt;h3&gt; &lt;p&gt; &lt;ul&gt; &lt;img&gt; &lt;a&gt;</span></label><textarea class="textarea ltr" name="content" rows="18" style="font-family:ui-monospace,monospace;font-size:13px;direction:rtl;text-align:right"><?= e($p['content'] ?? '') ?></textarea></div>
  </div></div>
  <div class="span-4 stack" style="gap:20px">
    <div class="card card-pad">
      <label class="switch mb-2"><input type="checkbox" name="is_published" value="1" <?= ($p['is_published'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>منتشر شود</label>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره</button>
    </div>
    <?php if ($type === 'post'): ?>
    <div class="card card-pad"><h3 class="card-title mb-2"><?= icon('image') ?> تصویر شاخص</h3>
      <label class="img-preview"><?php if (!empty($p['cover'])): ?><img src="<?= e(upload_url($p['cover'])) ?>" alt=""><?php else: ?><span class="ph"><?= icon('upload') ?> انتخاب تصویر</span><?php endif; ?><input type="file" name="cover" accept="image/*" data-preview></label></div>
    <?php endif; ?>
  </div>
</form>

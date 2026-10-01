<?php $e = null; if ($tab === 'faq') { foreach ($rows as $r) { if ((int)$r['id'] === $editFaq) { $e = $r; } } } ?>
<?= partial('page_head', ['icon' => 'file', 'h' => 'مدیریت محتوا', 'sub' => 'مقالات وبلاگ، صفحات ثابت و سوالات متداول',
  'actions' => $tab !== 'faq' ? '<a href="' . url('admin/content/create', ['type' => $tab]) . '" class="btn btn-primary">' . icon('plus') . ($tab === 'page' ? ' صفحه جدید' : ' مقاله جدید') . '</a>' : '']) ?>
<div class="tabs mb-3" style="display:inline-flex">
  <a href="<?= url('admin/content', ['tab' => 'post']) ?>" class="<?= $tab === 'post' ? 'active' : '' ?>"><?= icon('file') ?> مقالات <span class="count"><?= fa((int)$counts['posts']) ?></span></a>
  <a href="<?= url('admin/content', ['tab' => 'page']) ?>" class="<?= $tab === 'page' ? 'active' : '' ?>"><?= icon('layers') ?> صفحات <span class="count"><?= fa((int)$counts['pages']) ?></span></a>
  <a href="<?= url('admin/content', ['tab' => 'faq']) ?>" class="<?= $tab === 'faq' ? 'active' : '' ?>"><?= icon('help') ?> سوالات متداول <span class="count"><?= fa((int)$counts['faqs']) ?></span></a>
</div>
<?php if ($tab === 'faq'): ?>
<div class="dash-grid">
  <div class="card span-8">
    <?php foreach ($rows as $f): ?>
      <div class="list-item"><span class="rank"><?= fa($f['sort']) ?></span><div style="min-width:0"><b><?= e($f['question']) ?></b><small><?= e(str_limit($f['answer'], 110)) ?></small></div>
        <div class="li-end actions"><?= $f['is_active'] ? '' : badge('مخفی', 'muted') ?><a href="<?= url('admin/content', ['tab' => 'faq', 'edit' => $f['id']]) ?>" class="btn btn-soft btn-icon btn-xs"><?= icon('edit') ?></a>
          <form method="post" action="<?= url('admin/faqs/' . $f['id'] . '/delete') ?>" data-confirm="این سوال حذف شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-icon btn-xs"><?= icon('trash') ?></button></form></div></div>
    <?php endforeach; ?>
    <?php if (!$rows): ?><?= partial('empty', ['icon' => 'help', 'title' => 'سوالی ثبت نشده']) ?><?php endif; ?>
  </div>
  <form class="card span-4" method="post" action="<?= url('admin/faqs/save') ?>" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)($e['id'] ?? 0) ?>">
    <div class="card-head"><h3><?= icon($e ? 'edit' : 'plus') ?> <?= $e ? 'ویرایش سوال' : 'سوال جدید' ?></h3></div>
    <div class="card-body">
      <div class="field"><label class="label">سوال</label><input class="input" name="question" value="<?= e($e['question'] ?? '') ?>" required></div>
      <div class="field"><label class="label">پاسخ</label><textarea class="textarea" name="answer" rows="5" required><?= e($e['answer'] ?? '') ?></textarea></div>
      <div class="field"><label class="label">ترتیب</label><input class="input" name="sort" value="<?= e($e['sort'] ?? count($rows)) ?>"></div>
      <label class="switch mb-2"><input type="checkbox" name="is_active" value="1" <?= ($e['is_active'] ?? 1) ? 'checked' : '' ?>><span class="track"></span>نمایش در سایت</label>
      <button class="btn btn-primary btn-block"><?= icon('check') ?> ذخیره</button>
    </div>
  </form>
</div>
<?php else: ?>
<div class="card">
  <?php if ($rows): ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>عنوان</th><th>نامک</th><th>بازدید</th><th>وضعیت</th><th>تاریخ</th><th></th></tr></thead>
    <tbody><?php foreach ($rows as $p): ?>
      <tr><td><b><?= e($p['title']) ?></b><div class="small muted"><?= e(str_limit($p['excerpt'], 70)) ?></div></td><td class="ltr small muted"><?= e($p['slug']) ?></td><td class="num"><?= num($p['views']) ?></td>
        <td><?= $p['is_published'] ? badge('منتشر شده', 'success') : badge('پیش‌نویس', 'muted') ?></td><td class="muted small nowrap"><?= jdate('Y/m/d', $p['created_at']) ?></td>
        <td><div class="actions"><a href="<?= url(($p['type'] === 'page' ? 'page/' : 'blog/') . $p['slug']) ?>" target="_blank" class="btn btn-ghost btn-icon btn-xs"><?= icon('external') ?></a><a href="<?= url('admin/content/' . $p['id'] . '/edit') ?>" class="btn btn-soft btn-icon btn-xs"><?= icon('edit') ?></a>
          <form method="post" action="<?= url('admin/content/' . $p['id'] . '/delete') ?>" data-confirm="«<?= e($p['title']) ?>» حذف شود؟"><?= csrf_field() ?><button class="btn btn-danger btn-icon btn-xs"><?= icon('trash') ?></button></form></div></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php else: ?><?= partial('empty', ['icon' => 'file', 'title' => 'محتوایی وجود ندارد']) ?><?php endif; ?>
</div>
<?php endif; ?>

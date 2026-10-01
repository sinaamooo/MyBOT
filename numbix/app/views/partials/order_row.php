<?php /** @var array $o order + title, icon, color, color2 */ ?>
<tr>
  <td class="num">#<?= fa($o['id']) ?></td>
  <td><div class="t-svc"><?= brand_tile($o, 'sm') ?><div><b><?= e($o['title']) ?></b><small><?= num($o['quantity']) ?> عدد</small></div></div></td>
  <td><span class="ltr muted" style="display:inline-block;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle"><?= e($o['link']) ?></span></td>
  <td class="num"><?= money($o['price'] - $o['discount']) ?></td>
  <td><?= status_badge($o['status']) ?></td>
  <td class="muted nowrap"><?= jdate('Y/m/d H:i', $o['created_at']) ?></td>
  <td><div class="actions"><a href="<?= url('dashboard/orders/' . $o['id']) ?>" class="btn btn-soft btn-icon btn-xs" aria-label="مشاهده"><?= icon('eye') ?></a></div></td>
</tr>

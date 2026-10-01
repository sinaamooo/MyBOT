<?= partial('page_head', ['icon' => 'user', 'h' => 'پروفایل و امنیت', 'sub' => 'اطلاعات حساب و رمز عبور خود را مدیریت کنید']) ?>
<div class="dash-grid">
  <form class="card span-7" method="post" action="<?= url('dashboard/profile') ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="card-head"><h3><?= icon('user') ?> اطلاعات حساب</h3></div>
    <div class="card-body">
      <div class="row mb-3">
        <?= avatar($u, 'xl') ?>
        <div><b style="font-size:18px"><?= e(user_name($u)) ?></b><div class="muted small">عضو از <?= jdate('j F Y', $u['created_at']) ?></div>
          <label class="btn btn-soft btn-sm mt-1"><?= icon('upload') ?> تغییر تصویر<input type="file" name="avatar" accept="image/*" hidden></label></div>
      </div>
      <div class="grid g-2" style="gap:14px">
        <div class="field mb-0"><label class="label">نام</label><input class="input" name="first_name" value="<?= e($u['first_name']) ?>" required></div>
        <div class="field mb-0"><label class="label">نام خانوادگی</label><input class="input" name="last_name" value="<?= e($u['last_name']) ?>"></div>
        <div class="field mb-0"><label class="label">ایمیل</label><input class="input ltr" type="email" name="email" value="<?= e($u['email']) ?>"></div>
        <div class="field mb-0"><label class="label">موبایل</label><input class="input ltr" name="mobile" value="<?= e($u['mobile']) ?>" inputmode="numeric"></div>
        <div class="field mb-0"><label class="label">نام کاربری <span class="hint">اختیاری</span></label><input class="input ltr" name="username" value="<?= e($u['username']) ?>"></div>
      </div>
    </div>
    <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary"><?= icon('check') ?> ذخیره تغییرات</button></div>
  </form>
  <div class="span-5 stack" style="gap:20px">
    <form class="card" method="post" action="<?= url('dashboard/password') ?>">
      <?= csrf_field() ?>
      <div class="card-head"><h3><?= icon('lock') ?> تغییر رمز عبور</h3></div>
      <div class="card-body">
        <?php if ($u['password']): ?><div class="field"><label class="label">رمز فعلی</label><input class="input" type="password" name="current_password" required autocomplete="current-password"></div><?php endif; ?>
        <div class="field"><label class="label">رمز جدید</label><input class="input" type="password" name="password" required autocomplete="new-password" data-pw-meter="#pwm2"><div class="pw-meter" id="pwm2"><i></i><i></i><i></i><i></i></div></div>
        <div class="field"><label class="label">تکرار رمز جدید</label><input class="input" type="password" name="password_confirmation" required autocomplete="new-password"></div>
        <button class="btn btn-soft btn-block"><?= icon('key') ?> بروزرسانی رمز</button>
      </div>
    </form>
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('link') ?> حساب‌های متصل</h3>
      <div class="sys-row"><span><?= brand('google') ?> گوگل</span><?= $u['google_id'] ? badge('متصل', 'success') : badge('متصل نیست', 'muted') ?></div>
      <div class="sys-row"><span><?= brand('telegram') ?> تلگرام</span><?= $u['telegram_id'] ? badge('متصل', 'success') : (setting('telegram_login') === '1' ? '<a class="btn btn-soft btn-xs" href="' . url('auth/telegram', ['start' => 1]) . '">اتصال</a>' : badge('متصل نیست', 'muted')) ?></div>
    </div>
  </div>
</div>

<h1>خوش آمدید <span style="font-size:.9em">👋</span></h1>
<p class="sub">برای ورود به حساب کاربری، اطلاعات خود را وارد کنید.</p>
<form action="<?= url('login') ?>" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">
  <div class="field">
    <label class="label" for="login">ایمیل یا شماره موبایل</label>
    <div class="input-wrap"><?= icon('user') ?><input class="input" id="login" name="login" value="<?= e(old('login')) ?>" autocomplete="username" required autofocus placeholder="example@mail.com"></div>
  </div>
  <div class="field">
    <label class="label" for="password">رمز عبور <a href="<?= url('forgot') ?>" class="hint" style="color:var(--p)">رمز عبور را فراموش کرده‌اید؟</a></label>
    <div class="input-wrap"><?= icon('lock') ?><input class="input" id="password" type="password" name="password" autocomplete="current-password" required placeholder="••••••••">
      <button type="button" class="input-action" data-pw-toggle aria-label="نمایش رمز"><?= icon('eye-off') ?></button></div>
  </div>
  <label class="check mb-3"><input type="checkbox" name="remember" value="1" checked><span class="box"><?= icon('check') ?></span>مرا به خاطر بسپار</label>
  <button class="btn btn-primary btn-lg btn-block"><?= icon('login') ?> ورود به حساب کاربری</button>
</form>
<?= render('auth/_socials', ['socials' => $socials]) ?>
<?php if (setting('register_enabled', '1') === '1'): ?>
  <div class="auth-alt">حساب کاربری ندارید؟ <a href="<?= url('register', $next ? ['next' => $next] : []) ?>"><?= icon('user-plus') ?> ثبت‌نام کنید</a></div>
<?php endif; ?>

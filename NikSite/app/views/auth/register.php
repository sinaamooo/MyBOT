<h1><span class="h-ic"><?= icon('user-plus') ?></span>ایجاد حساب کاربری</h1>
<p class="sub">با عضویت در <?= e(site_name()) ?>، به دنیایی از خدمات دیجیتال دسترسی پیدا کنید.</p>
<form action="<?= url('register') ?>" method="post" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e(input('next', '')) ?>">
  <div class="grid g-2" style="gap:12px">
    <div class="field mb-0"><div class="input-wrap"><?= icon('user') ?><input class="input" name="first_name" value="<?= e(old('first_name')) ?>" placeholder="نام" required autofocus autocomplete="given-name"></div></div>
    <div class="field mb-0"><div class="input-wrap"><?= icon('user') ?><input class="input" name="last_name" value="<?= e(old('last_name')) ?>" placeholder="نام خانوادگی" autocomplete="family-name"></div></div>
  </div>
  <div class="field mt-2"><div class="input-wrap"><?= icon('mail') ?><input class="input" type="email" name="email" value="<?= e(old('email')) ?>" placeholder="ایمیل" required autocomplete="email"></div></div>
  <div class="field"><div class="input-wrap"><?= icon('phone') ?><input class="input" type="tel" name="mobile" value="<?= e(old('mobile')) ?>" placeholder="شماره موبایل (۰۹xxxxxxxxx)<?= setting('require_mobile', '0') === '1' ? '' : ' — اختیاری' ?>" autocomplete="tel" inputmode="numeric"></div></div>
  <div class="field mb-1">
    <div class="input-wrap"><?= icon('lock') ?><input class="input" type="password" name="password" placeholder="رمز عبور" required autocomplete="new-password" data-pw-meter="#pwm">
      <button type="button" class="input-action" data-pw-toggle aria-label="نمایش رمز"><?= icon('eye-off') ?></button></div>
    <div class="pw-meter" id="pwm"><i></i><i></i><i></i><i></i></div>
    <div class="help">رمز عبور باید حداقل ۸ کاراکتر و شامل عدد و حرف باشد.</div>
  </div>
  <div class="field mt-2"><div class="input-wrap"><?= icon('lock') ?><input class="input" type="password" name="password_confirmation" placeholder="تکرار رمز عبور" required autocomplete="new-password">
    <button type="button" class="input-action" data-pw-toggle aria-label="نمایش رمز"><?= icon('eye-off') ?></button></div></div>
  <label class="check mb-3"><input type="checkbox" name="terms" value="1" <?= old('terms') ? 'checked' : '' ?>><span class="box"><?= icon('check') ?></span><span><a href="<?= url('page/terms') ?>" target="_blank" style="color:var(--p);font-weight:700">قوانین و شرایط</a> استفاده از <?= e(site_name()) ?> را می‌پذیرم.</span></label>
  <button class="btn btn-primary btn-lg btn-block"><?= icon('arrow-left') ?> ثبت‌نام در <?= e(site_name()) ?></button>
</form>
<?= render('auth/_socials', ['socials' => $socials, 'label' => 'یا ثبت‌نام با']) ?>
<div class="auth-alt">قبلاً حساب کاربری دارید؟ <a href="<?= url('login') ?>">ورود به حساب کاربری</a></div>

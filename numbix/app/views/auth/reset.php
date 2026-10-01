<h1><span class="h-ic"><?= icon('lock') ?></span>رمز عبور جدید</h1>
<p class="sub">یک رمز عبور قوی برای حساب خود انتخاب کنید.</p>
<form action="<?= url('reset/' . $token) ?>" method="post">
  <?= csrf_field() ?>
  <div class="field"><label class="label">رمز عبور جدید</label><div class="input-wrap"><?= icon('lock') ?><input class="input" type="password" name="password" required autofocus data-pw-meter="#pwm"><button type="button" class="input-action" data-pw-toggle><?= icon('eye-off') ?></button></div><div class="pw-meter" id="pwm"><i></i><i></i><i></i><i></i></div></div>
  <div class="field"><label class="label">تکرار رمز عبور</label><div class="input-wrap"><?= icon('lock') ?><input class="input" type="password" name="password_confirmation" required></div></div>
  <button class="btn btn-primary btn-lg btn-block"><?= icon('check') ?> ذخیره رمز عبور</button>
</form>

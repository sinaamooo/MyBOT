<h1><span class="h-ic"><?= icon('key') ?></span>بازیابی رمز عبور</h1>
<p class="sub">ایمیل حساب کاربری خود را وارد کنید تا لینک تعیین رمز جدید برایتان ارسال شود.</p>
<form action="<?= url('forgot') ?>" method="post">
  <?= csrf_field() ?>
  <div class="field"><label class="label">ایمیل</label><div class="input-wrap"><?= icon('mail') ?><input class="input" type="email" name="email" required autofocus placeholder="example@mail.com"></div></div>
  <button class="btn btn-primary btn-lg btn-block"><?= icon('send') ?> ارسال لینک بازیابی</button>
</form>
<div class="auth-alt">رمز عبور را به خاطر آوردید؟ <a href="<?= url('login') ?>">ورود</a></div>

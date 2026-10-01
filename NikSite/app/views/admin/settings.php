<?php
$s = fn($k, $d = '') => e(setting($k, $d));
$chk = fn($k, $d = '0') => setting($k, $d) === '1' ? 'checked' : '';
$tabs = ['general' => ['عمومی', 'settings'], 'contact' => ['تماس و فوتر', 'phone'], 'payment' => ['پرداخت', 'card'], 'users' => ['کاربران و ورود', 'users'], 'automation' => ['اتوماسیون و API', 'zap']];
$field = function (string $label, string $html, string $help = '') {
    return '<div class="field"><label class="label">' . $label . '</label>' . $html . ($help ? '<div class="help">' . $help . '</div>' : '') . '</div>';
};
$sw = fn($k, $label, $d = '0') => '<label class="switch mb-2" style="display:flex"><input type="checkbox" name="' . $k . '" value="1" ' . $chk($k, $d) . '><span class="track"></span>' . $label . '</label>';
?>
<?= partial('page_head', ['icon' => 'settings', 'h' => 'تنظیمات سایت', 'sub' => 'همه چیز از این‌جا قابل تغییر است']) ?>
<div class="tabs mb-3" style="display:inline-flex;max-width:100%">
  <?php foreach ($tabs as $k => [$l, $ic]): ?><a href="<?= url('admin/settings', ['tab' => $k]) ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= icon($ic) ?> <?= $l ?></a><?php endforeach; ?>
</div>
<form method="post" action="<?= url('admin/settings') ?>" class="dash-grid">
  <?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="card span-8"><div class="card-body">
  <?php if ($tab === 'general'): ?>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('نام سایت', '<input class="input" name="site_name" value="' . $s('site_name', 'نامبیکس') . '">') ?>
      <?= $field('شعار', '<input class="input" name="site_tagline" value="' . $s('site_tagline') . '">') ?>
    </div>
    <?= $field('توضیحات سئو (meta description)', '<textarea class="textarea" name="site_description" rows="2" style="min-height:70px">' . $s('site_description') . '</textarea>') ?>
    <?= $field('متن معرفی هدر صفحه اصلی', '<textarea class="textarea" name="hero_text" rows="2" style="min-height:70px">' . $s('hero_text') . '</textarea>') ?>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('واحد پول', '<input class="input" name="currency" value="' . $s('currency', 'تومان') . '">') ?>
      <?= $field('تم پیش‌فرض', '<select class="select" name="site_theme">' . implode('', array_map(fn($k, $l) => '<option value="' . $k . '"' . (setting('site_theme', 'light') === $k ? ' selected' : '') . '>' . $l . '</option>', ['light', 'dark', 'auto'], ['روشن', 'تیره', 'خودکار (طبق سیستم کاربر)'])) . '</select>') ?>
    </div>
    <?= $field('نوار اطلاعیه بالای سایت', '<input class="input" name="announcement" value="' . $s('announcement') . '" placeholder="مثلاً: ۲۰٪ تخفیف ویژه با کد WELCOME10">', 'خالی = نمایش داده نمی‌شود') ?>
    <?= $field('لینک اطلاعیه', '<input class="input ltr" name="announcement_link" value="' . $s('announcement_link') . '">') ?>
    <hr>
    <?= $sw('home_stats', 'نمایش بخش آمار در صفحه اصلی', '1') ?>
    <?= $sw('home_live_orders', 'نمایش «آخرین سفارش‌ها» (ناشناس) در صفحه اصلی', '1') ?>
    <div class="grid g-3" style="gap:0 12px">
      <?= $field('افزوده آمار سفارش‌ها', '<input class="input" name="stats_orders_offset" value="' . $s('stats_orders_offset', '0') . '">') ?>
      <?= $field('افزوده آمار کاربران', '<input class="input" name="stats_users_offset" value="' . $s('stats_users_offset', '0') . '">') ?>
      <?= $field('افزوده آمار تکمیل‌شده', '<input class="input" name="stats_completed_offset" value="' . $s('stats_completed_offset', '0') . '">') ?>
    </div>
    <div class="help mb-2" style="margin-top:-8px">برای سایت‌هایی که سابقه فروش قبلی دارند (مثلاً از ربات تلگرام) — عدد واقعی گذشته را اضافه کنید.</div>
    <hr>
    <?= $sw('maintenance', '<b>حالت تعمیر و نگهداری</b> (سایت برای کاربران بسته می‌شود، مدیران دسترسی دارند)') ?>
    <?= $field('متن صفحه تعمیرات', '<input class="input" name="maintenance_text" value="' . $s('maintenance_text') . '">') ?>
  <?php elseif ($tab === 'contact'): ?>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('تلفن پشتیبانی', '<input class="input ltr" name="support_phone" value="' . $s('support_phone') . '">') ?>
      <?= $field('ایمیل پشتیبانی', '<input class="input ltr" name="support_email" value="' . $s('support_email') . '">') ?>
      <?= $field('ساعات پاسخگویی', '<input class="input" name="support_hours" value="' . $s('support_hours') . '">') ?>
      <?= $field('آدرس', '<input class="input" name="address" value="' . $s('address') . '">') ?>
      <?= $field('تلگرام', '<input class="input ltr" name="social_telegram" value="' . $s('social_telegram') . '" placeholder="https://t.me/…">') ?>
      <?= $field('اینستاگرام', '<input class="input ltr" name="social_instagram" value="' . $s('social_instagram') . '">') ?>
      <?= $field('یوتیوب', '<input class="input ltr" name="social_youtube" value="' . $s('social_youtube') . '">') ?>
      <?= $field('X (توییتر)', '<input class="input ltr" name="social_x" value="' . $s('social_x') . '">') ?>
      <?= $field('واتس‌اپ', '<input class="input ltr" name="social_whatsapp" value="' . $s('social_whatsapp') . '">') ?>
      <?= $field('متن کپی‌رایت', '<input class="input" name="copyright" value="' . $s('copyright') . '">', 'خالی = متن پیش‌فرض') ?>
    </div>
    <?= $field('درباره ما (فوتر)', '<textarea class="textarea" name="footer_about" rows="3" style="min-height:80px">' . $s('footer_about') . '</textarea>') ?>
    <?= $field('کد نماد اعتماد (اینماد / ساماندهی)', '<textarea class="textarea ltr" name="trust_badges" rows="3" style="min-height:80px">' . $s('trust_badges') . '</textarea>', 'کد HTML ارائه‌شده توسط enamad.ir را این‌جا قرار دهید.') ?>
    <?= $field('کدهای اضافه در &lt;head&gt;', '<textarea class="textarea ltr" name="head_code" rows="3" style="min-height:80px">' . $s('head_code') . '</textarea>', 'مثلاً کد گوگل آنالیتیکس یا چت آنلاین.') ?>
  <?php elseif ($tab === 'payment'): ?>
    <h4 class="mb-2"><?= icon('card') ?> درگاه زرین‌پال</h4>
    <?= $field('مرچنت کد', '<input class="input ltr" name="zarinpal_merchant" value="' . $s('zarinpal_merchant') . '" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">', 'آدرس بازگشت: <span class="ltr">' . e(abs_url('payment/callback/')) . '…</span>') ?>
    <?= $sw('zarinpal_sandbox', 'حالت سندباکس (تست) زرین‌پال') ?>
    <hr>
    <h4 class="mb-2"><?= icon('receipt') ?> کارت به کارت</h4>
    <div class="grid g-3" style="gap:0 12px">
      <?= $field('شماره کارت', '<input class="input ltr" name="card_number" value="' . $s('card_number') . '">', 'خالی = غیرفعال') ?>
      <?= $field('به نام', '<input class="input" name="card_holder" value="' . $s('card_holder') . '">') ?>
      <?= $field('بانک', '<input class="input" name="card_bank" value="' . $s('card_bank') . '">') ?>
    </div>
    <hr>
    <div class="grid g-3" style="gap:0 12px">
      <?= $field('حداقل شارژ', '<input class="input" name="min_deposit" value="' . $s('min_deposit', '10000') . '">') ?>
      <?= $field('حداکثر شارژ', '<input class="input" name="max_deposit" value="' . $s('max_deposit', '100000000') . '">') ?>
      <?= $field('مهلت پرداخت سفارش (ساعت)', '<input class="input" name="unpaid_order_hours" value="' . $s('unpaid_order_hours', '24') . '">') ?>
    </div>
    <hr>
    <?= $sw('test_gateway', 'درگاه آزمایشی برای مدیران (تست کامل خرید بدون پرداخت واقعی)', '1') ?>
    <?= $sw('test_gateway_public', '<span style="color:var(--danger)">نمایش درگاه آزمایشی به همه کاربران (فقط روی سایت تستی!)</span>') ?>
  <?php elseif ($tab === 'users'): ?>
    <?= $sw('register_enabled', 'ثبت‌نام کاربران جدید فعال باشد', '1') ?>
    <?= $sw('require_mobile', 'شماره موبایل در ثبت‌نام الزامی باشد') ?>
    <?= $field('هدیه ثبت‌نام (به کیف پول)', '<input class="input" name="register_bonus" value="' . $s('register_bonus', '0') . '">') ?>
    <hr>
    <h4 class="mb-2"><?= brand('google') ?> ورود با گوگل</h4>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('Client ID', '<input class="input ltr" name="google_client_id" value="' . $s('google_client_id') . '">') ?>
      <?= $field('Client Secret', '<input class="input ltr" name="google_client_secret" value="' . $s('google_client_secret') . '">') ?>
    </div>
    <div class="help mb-2" style="margin-top:-8px">Redirect URI در کنسول گوگل: <span class="ltr"><?= e(abs_url('auth/google/callback')) ?></span></div>
    <h4 class="mb-2"><?= brand('telegram') ?> ورود با تلگرام</h4>
    <?= $sw('telegram_login', 'فعال‌سازی ورود با تلگرام (از توکن ربات بخش اتوماسیون استفاده می‌کند)') ?>
    <div class="help mb-2">در BotFather دستور <span class="ltr">/setdomain</span> را برای دامنه سایت اجرا کنید.</div>
    <hr>
    <?= $field('پاسخ‌های آماده تیکت (هر خط یک پاسخ)', '<textarea class="textarea" name="canned_replies" rows="4">' . $s('canned_replies') . '</textarea>') ?>
    <?= $field('بستن خودکار تیکت‌های پاسخ‌داده‌شده پس از (روز)', '<input class="input" name="ticket_autoclose_days" value="' . $s('ticket_autoclose_days', '7') . '">') ?>
  <?php else: ?>
    <?= $sw('auto_cron', 'اجرای خودکار اتوماسیون با بازدیدهای سایت (اگر کرون‌جاب ندارید)', '1') ?>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('فاصله اجرا (دقیقه)', '<input class="input" name="auto_cron_minutes" value="' . $s('auto_cron_minutes', '5') . '">') ?>
      <?= $field('حد پیش‌فرض هشدار موجودی', '<input class="input" name="low_stock_default" value="' . $s('low_stock_default', '500') . '">') ?>
    </div>
    <hr>
    <h4 class="mb-2"><?= brand('telegram') ?> اطلاع‌رسانی تلگرامی به مدیر</h4>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('توکن ربات', '<input class="input ltr" name="telegram_bot_token" value="' . $s('telegram_bot_token') . '" placeholder="123456:ABC…">') ?>
      <?= $field('Chat ID مدیر', '<input class="input ltr" name="telegram_admin_chat" value="' . $s('telegram_admin_chat') . '">', 'سفارش جدید، تیکت، پرداخت و هشدار موجودی به این چت ارسال می‌شود.') ?>
    </div>
    <button class="btn btn-soft btn-sm mb-2" name="action" value="test_telegram" formnovalidate><?= icon('send') ?> ارسال پیام آزمایشی</button>
    <hr>
    <?= $sw('api_enabled', 'وب‌سرویس API برای کاربران (همکاران) فعال باشد', '1') ?>
    <div class="grid g-2" style="gap:0 16px">
      <?= $field('واحد پول در API', '<input class="input ltr" name="api_currency" value="' . $s('api_currency', 'IRT') . '">') ?>
      <?= $field('ایمیل فرستنده', '<input class="input ltr" name="mail_from" value="' . $s('mail_from') . '" placeholder="no-reply@yourdomain.com">') ?>
    </div>
  <?php endif; ?>
  </div>
  <div class="card-foot row" style="justify-content:flex-end"><button class="btn btn-primary btn-lg"><?= icon('check') ?> ذخیره تنظیمات</button></div>
  </div>

  <div class="span-4 stack" style="gap:20px">
    <div class="card card-pad">
      <h3 class="card-title mb-2"><?= icon('refresh') ?> اتوماسیون</h3>
      <div class="sys-row"><span>آخرین اجرا</span><b><?= $cronLast ? time_ago(date('Y-m-d H:i:s', $cronLast)) : 'هنوز اجرا نشده' ?></b></div>
      <?php if ($cronLog): ?><div class="code-box mt-1" style="font-size:11.5px;white-space:pre-wrap"><?= e($cronLog) ?></div><?php endif; ?>
      <p class="small muted mt-2 mb-1">کرون‌جاب پیشنهادی (هر ۵ دقیقه):</p>
      <div class="code-box" style="font-size:11.5px;white-space:pre-wrap">*/5 * * * * php <?= e(BASE_PATH) ?>/cron.php</div>
      <p class="small muted mt-2 mb-1">یا آدرس وب‌کرون:</p>
      <div class="copy-field"><code><?= e($cronUrl) ?></code><button type="button" class="btn btn-soft btn-xs" data-copy="<?= e($cronUrl) ?>"><?= icon('copy') ?></button></div>
    </div>
    <?php if ($demoUsers): ?>
    <div class="card card-pad" style="border-color:rgba(225,29,72,.3)">
      <h3 class="card-title mb-1" style="color:var(--danger)"><?= icon('trash') ?> داده‌های نمونه</h3>
      <p class="small muted"><?= fa($demoUsers) ?> کاربر نمونه (و سفارش‌هایشان) از نصب وجود دارد.</p>
      <button class="btn btn-danger btn-block" name="action" value="purge_demo" formnovalidate onclick="return confirm('کاربران و سفارش‌های نمونه حذف شوند؟')">حذف داده‌های نمونه</button>
    </div>
    <?php endif; ?>
  </div>
</form>

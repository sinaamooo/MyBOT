<?php

final class AdminSettingsController
{
    /** Allowed keys per tab — anything else posted is ignored. */
    public const FIELDS = [
        'general' => ['site_name', 'site_tagline', 'site_description', 'hero_text', 'currency', 'default_theme', 'announcement', 'announcement_link', 'maintenance', 'maintenance_text', 'home_stats', 'home_live_orders', 'stats_orders_offset', 'stats_users_offset', 'stats_completed_offset'],
        'contact' => ['support_phone', 'support_email', 'support_hours', 'address', 'footer_about', 'copyright', 'social_telegram', 'social_instagram', 'social_youtube', 'social_x', 'social_whatsapp', 'trust_badges', 'head_code'],
        'payment' => ['zarinpal_merchant', 'zarinpal_sandbox', 'card_number', 'card_holder', 'card_bank', 'min_deposit', 'max_deposit', 'test_gateway', 'test_gateway_public', 'unpaid_order_hours'],
        'users' => ['register_enabled', 'require_mobile', 'register_bonus', 'google_client_id', 'google_client_secret', 'telegram_login', 'canned_replies', 'ticket_autoclose_days'],
        'automation' => ['auto_cron', 'auto_cron_minutes', 'low_stock_default', 'api_enabled', 'api_currency', 'telegram_bot_token', 'telegram_admin_chat', 'mail_from'],
    ];
    public const CHECKBOXES = ['maintenance', 'home_stats', 'home_live_orders', 'zarinpal_sandbox', 'test_gateway', 'test_gateway_public', 'register_enabled', 'require_mobile', 'telegram_login', 'auto_cron', 'api_enabled'];

    public function index(): string
    {
        $tab = array_key_exists(input('tab'), self::FIELDS) ? input('tab') : 'general';
        return view('admin/settings', [
            'area' => 'admin',
            'title' => 'تنظیمات سایت',
            'tab' => $tab,
            'cronUrl' => abs_url('cron/' . config('cron_key')),
            'cronLog' => setting('cron_last_log', ''),
            'cronLast' => (int)setting('cron_last_run', 0),
            'demoUsers' => (int)DB::value("SELECT COUNT(*) FROM users WHERE admin_note = '[demo]'"),
        ], 'panel');
    }

    public function save(): never
    {
        $tab = array_key_exists(input('tab'), self::FIELDS) ? input('tab') : 'general';

        if (input('action') === 'purge_demo') {
            $ids = DB::column("SELECT id FROM users WHERE admin_note = '[demo]' AND role = 'user'");
            if ($ids) {
                $in = implode(',', array_map('intval', $ids));
                DB::query("DELETE FROM orders WHERE user_id IN ($in)");
                DB::query("DELETE FROM payments WHERE user_id IN ($in)");
                DB::query("DELETE FROM transactions WHERE user_id IN ($in)");
                DB::query("DELETE FROM tickets WHERE user_id IN ($in)");
                DB::query("DELETE FROM notifications WHERE user_id IN ($in)");
                DB::query("DELETE FROM users WHERE id IN ($in)");
            }
            flash('success', fa(count($ids)) . ' کاربر نمونه و سفارش‌هایشان حذف شدند.');
            redirect('admin/settings?tab=automation');
        }
        if (input('action') === 'test_telegram') {
            Notifier::telegram('✅ <b>' . e(site_name()) . '</b>' . "\nاتصال ربات اطلاع‌رسانی برقرار است.")
                ? flash('success', 'پیام آزمایشی به تلگرام ارسال شد.')
                : flash('error', 'ارسال ناموفق بود؛ توکن ربات و شناسه چت را بررسی کنید (ربات را در چت استارت کرده باشید).');
            redirect('admin/settings?tab=automation');
        }

        foreach (self::FIELDS[$tab] as $key) {
            if (in_array($key, self::CHECKBOXES, true)) {
                setting_set($key, isset($_POST[$key]) ? '1' : '0');
                continue;
            }
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $v = is_string($_POST[$key]) ? trim($_POST[$key]) : '';
            if (in_array($key, ['min_deposit', 'max_deposit', 'register_bonus', 'auto_cron_minutes', 'low_stock_default', 'unpaid_order_hours', 'ticket_autoclose_days', 'stats_orders_offset', 'stats_users_offset', 'stats_completed_offset'], true)) {
                $v = (string)max(0, (int)preg_replace('/\D/', '', en_digits($v)));
            }
            if ($key === 'card_number') {
                $v = preg_replace('/\D/', '', en_digits($v));
            }
            setting_set($key, $v);
        }
        flash('success', 'تنظیمات ذخیره شد.');
        redirect('admin/settings?tab=' . $tab);
    }
}

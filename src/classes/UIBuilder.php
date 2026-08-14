<?php

namespace TelegramShop\Classes;

class UIBuilder {
    public static function inlineButton($text, $callbackData, $color = 'blue', $emoji = null) {
        if ($emoji) {
            $text = $emoji . ' ' . $text;
        }
        return [
            'text' => $text,
            'callback_data' => $callbackData
        ];
    }

    public static function inlineKeyboard($buttons, $columns = 2) {
        $keyboard = [];
        $currentRow = [];

        foreach ($buttons as $button) {
            $currentRow[] = $button;

            if (count($currentRow) >= $columns) {
                $keyboard[] = $currentRow;
                $currentRow = [];
            }
        }

        if (!empty($currentRow)) {
            $keyboard[] = $currentRow;
        }

        return ['inline_keyboard' => $keyboard];
    }

    public static function mainMenu($userId) {
        $user = new User($userId);
        $emoji = EMOJI_PREMIUM;

        $balance = $user->getBalance();
        $balanceText = sprintf(
            "<b>%s موجودی: %s 💰</b>\n\n",
            $emoji['wallet'] ?? '',
            number_format($balance, 0, ',', ',')
        );

        $buttons = [
            self::inlineButton(
                'خرید اعضا',
                'action_buy_members',
                'blue',
                $emoji['shop']
            ),
            self::inlineButton(
                'فروش اعضا',
                'action_sell_members',
                'green',
                $emoji['money']
            ),
            self::inlineButton(
                'تاریخچه',
                'action_history',
                'blue',
                $emoji['history'] ?? ''
            ),
            self::inlineButton(
                'تنظیمات',
                'action_settings',
                'red',
                $emoji['settings'] ?? ''
            )
        ];

        // اگر کاربر مدیر باشد
        if ($userId == ADMIN_ID) {
            $buttons[] = self::inlineButton(
                'پنل مدیریت',
                'action_admin_panel',
                'red',
                $emoji['lock']
            );
        }

        return [
            'text' => $balanceText . 'لطفاً یک گزینه را انتخاب کنید:',
            'keyboard' => self::inlineKeyboard($buttons, 2)
        ];
    }

    public static function settingsMenu($userId) {
        $user = new User($userId);
        $userData = $user->getData();
        $emoji = EMOJI_PREMIUM;

        $phone = $userData['phone'] ?? '❌ تنظیم نشده';
        $paymentMethod = $userData['payment_method'] ?? 'انتخاب نشده';

        $text = sprintf(
            "<b>%s تنظیمات</b>\n\n" .
            "<b>📱 شماره تلفن:</b> %s\n" .
            "<b>💳 روش پرداخت:</b> %s\n\n" .
            "لطفاً یک گزینه را انتخاب کنید:",
            $emoji['settings'] ?? '⚙️',
            $phone,
            $paymentMethod
        );

        $buttons = [
            self::inlineButton(
                'تغییر شماره تلفن',
                'set_phone',
                'blue',
                '📱'
            ),
            self::inlineButton(
                'انتخاب روش پرداخت',
                'set_payment_method',
                'green',
                '💳'
            ),
            self::inlineButton(
                'بازگشت',
                'back_menu',
                'red',
                '◀️'
            )
        ];

        return [
            'text' => $text,
            'keyboard' => self::inlineKeyboard($buttons, 2)
        ];
    }

    public static function paymentMethodsMenu() {
        $emoji = EMOJI_PREMIUM;
        $text = sprintf(
            "<b>%s انتخاب روش پرداخت</b>\n\n" .
            "لطفاً روش پرداخت خود را انتخاب کنید:",
            $emoji['money']
        );

        $buttons = [];
        foreach (PAYMENT_METHODS as $key => $label) {
            $buttons[] = self::inlineButton(
                $label,
                'payment_' . $key,
                'blue',
                $emoji['check']
            );
        }

        $buttons[] = self::inlineButton(
            'بازگشت',
            'back_settings',
            'red',
            '◀️'
        );

        return [
            'text' => $text,
            'keyboard' => self::inlineKeyboard($buttons, 1)
        ];
    }

    public static function adminPanel() {
        $emoji = EMOJI_PREMIUM;
        $text = sprintf(
            "<b>%s پنل مدیریت</b>\n\n" .
            "لطفاً یک گزینه را انتخاب کنید:",
            $emoji['lock']
        );

        $buttons = [
            self::inlineButton(
                'مدیریت ربات های فرعی',
                'admin_sub_bots',
                'blue',
                '🤖'
            ),
            self::inlineButton(
                'مدیریت کانال ها',
                'admin_channels',
                'blue',
                '📢'
            ),
            self::inlineButton(
                'مدیریت کاربران',
                'admin_users',
                'blue',
                '👥'
            ),
            self::inlineButton(
                'گزارش فروش',
                'admin_reports',
                'green',
                '📊'
            ),
            self::inlineButton(
                'تنظیمات سیستم',
                'admin_settings',
                'red',
                '⚙️'
            ),
            self::inlineButton(
                'بازگشت',
                'back_menu',
                'red',
                '◀️'
            )
        ];

        return [
            'text' => $text,
            'keyboard' => self::inlineKeyboard($buttons, 2)
        ];
    }
}
